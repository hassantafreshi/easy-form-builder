/*
 * Easy Form Builder - public builder API (window.efbBuilderApi), version 1.
 *
 * For integrations that change the form open in the builder (AI Site Assistant
 * AutoPilot first) without touching valj_efb, sessionStorage or the settings panel
 * toggle themselves, and without guessing when a render is finished.
 * Contract and examples: docs/autopilot-integration.md (B1, B2, B4).
 *
 * Loaded after admin-efb.js, val-efb.js, pro_els-efb.js and new-efb.js on the
 * builder screens. No build step; plain ES2017.
 *
 * Events on document:
 *   efb:form-changed     detail {source:'user'|'api', action, ids}
 *   efb:canvas-rendered  detail {source:'user'|'api', complete, pending}
 */
(function () {
  'use strict';
  if (typeof window === 'undefined' || typeof document === 'undefined') return;

  var API_VERSION = 1;
  var OPTION_TYPES = ['option', 'r_matrix'];
  var apiRenderDepth = 0;
  var baselineJson = null;
  var userCheckTimer = null;

  function fail(code, message) {
    var e = new Error(message || code);
    e.code = code;
    return e;
  }
  function clone(x) { return JSON.parse(JSON.stringify(x)); }
  function rows() {
    if (typeof valj_efb === 'undefined' || !Array.isArray(valj_efb)) throw fail('builder_not_ready', 'The form builder is not loaded.');
    return valj_efb;
  }
  function isOptionRow(r) { return r && OPTION_TYPES.indexOf(r.type) !== -1; }
  function orderOf(r) { return typeof efbOrderOf === 'function' ? efbOrderOf(r) : (Number(r && r.amount) || 0); }
  function snapshot() { try { return JSON.stringify(valj_efb); } catch (e) { return null; } }
  function persist() { try { sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb)); } catch (e) { /* storage blocked: the canvas still renders from valj_efb */ } }
  function emit(name, detail) {
    try { document.dispatchEvent(new CustomEvent(name, { detail: detail })); } catch (e) { /* old browsers */ }
  }

  /* ---------------- change tracking ---------------- */

  function apiChanged(action, ids) {
    persist();
    baselineJson = snapshot();
    emit('efb:form-changed', { source: 'api', action: action, ids: ids || [] });
  }
  // Every builder edit (settings panel, drag and drop, delete, duplicate, an add-on
  // panel) ends in valj_efb; compare after the builder's own timers have settled.
  function scheduleUserCheck() {
    if (userCheckTimer) clearTimeout(userCheckTimer);
    var len = (typeof valj_efb !== 'undefined' && Array.isArray(valj_efb)) ? valj_efb.length : 0;
    userCheckTimer = setTimeout(function () {
      userCheckTimer = null;
      var now = snapshot();
      if (now === null || baselineJson === null) { baselineJson = now; return; }
      if (now !== baselineJson) {
        baselineJson = now;
        emit('efb:form-changed', { source: 'user', action: 'edit', ids: [] });
      }
    }, Math.max(300, len * 6 + 80));
  }
  ['change', 'click', 'drop', 'dragend', 'keyup'].forEach(function (t) {
    document.addEventListener(t, scheduleUserCheck, true);
  });

  /* ---------------- numbering (brief A2) ---------------- */

  // Fields and steps numbered one after another; options carry their parent's amount
  // and step; each field takes the step of the step row above it.
  function renumber(list) {
    var n = 0;
    var step = 1;
    var byId = {};
    for (var i = 1; i < list.length; i++) {
      var r = list[i];
      if (!r || typeof r !== 'object' || isOptionRow(r)) continue;
      n += 1;
      r.amount = n;
      if (r.type === 'step') step = Number(r.step) || step;
      else r.step = step;
      if (r.id_ !== undefined) byId[r.id_] = r;
    }
    for (var j = 1; j < list.length; j++) {
      var o = list[j];
      if (o && isOptionRow(o) && byId[o.parent]) {
        o.amount = byId[o.parent].amount;
        o.step = byId[o.parent].step;
      }
    }
    if (typeof amount_el_efb !== 'undefined') amount_el_efb = n;
    return list;
  }

  function fieldIndex(list, id) {
    for (var i = 1; i < list.length; i++) {
      var r = list[i];
      if (r && !isOptionRow(r) && (r.id_ === id || r.dataId === id)) return i;
    }
    return -1;
  }
  // Last index that belongs to a field: its own row and every row whose parent it is.
  function fieldEnd(list, idx) {
    var id = list[idx].id_;
    var end = idx;
    for (var i = idx + 1; i < list.length; i++) if (list[i] && list[i].parent === id) end = i;
    return end;
  }

  /* ---------------- render ---------------- */

  function canvasReady(list) {
    var pending = [];
    var hasIti = typeof window.intlTelInput === 'function';
    for (var i = 1; i < list.length; i++) {
      var r = list[i];
      if (!r || isOptionRow(r) || !r.dataId) continue;
      if (!document.querySelector('[data-id="' + cssEscape(r.dataId) + '"]')) { pending.push(r.id_); continue; }
      if (r.type === 'maps' && !document.querySelector('[data-id="' + cssEscape(r.id_ + '-mapsdiv') + '"]')) pending.push(r.id_);
      else if (r.type === 'mobile' && hasIti) {
        var input = document.getElementById(r.id_ + '_');
        if (input && !input.closest('.iti')) pending.push(r.id_);
      }
    }
    return pending;
  }
  function cssEscape(s) { return window.CSS && CSS.escape ? CSS.escape(String(s)) : String(s).replace(/["\\]/g, '\\$&'); }

  function waitReady(timeout) {
    var started = Date.now();
    return new Promise(function (resolve) {
      (function poll() {
        var pending = [];
        try { pending = canvasReady(rows()); } catch (e) { pending = []; }
        if (pending.length === 0 || Date.now() - started >= timeout) {
          resolve({ complete: pending.length === 0, pending: pending });
          return;
        }
        setTimeout(poll, 50);
      })();
    });
  }

  // Called by editFormEfb() (admin-efb.js) when it has drawn the canvas, whoever asked.
  window.efb_after_canvas_draw_efb = function () {
    if (apiRenderDepth > 0) return;
    waitReady(15000).then(function (r) {
      emit('efb:canvas-rendered', { source: 'user', complete: r.complete, pending: r.pending });
    });
  };

  function render(opts) {
    opts = opts || {};
    var timeout = Number(opts.timeout) > 0 ? Number(opts.timeout) : 15000;
    if (!document.getElementById('dropZoneEFB') || typeof editFormEfb !== 'function') {
      return Promise.reject(fail('builder_not_ready', 'The builder canvas is not on this page.'));
    }
    var list = rows();
    if (typeof efbNormalizeOrderEfb === 'function') efbNormalizeOrderEfb(list);
    persist(); // editFormEfb() starts from sessionStorage.valj_efb
    apiRenderDepth += 1;
    var drawn;
    try { drawn = editFormEfb(); } catch (e) { apiRenderDepth -= 1; return Promise.reject(e); }
    return Promise.resolve(drawn).then(function () {
      return waitReady(timeout);
    }).then(function (r) {
      apiRenderDepth -= 1;
      baselineJson = snapshot();
      var detail = { source: 'api', complete: r.complete, pending: r.pending };
      emit('efb:canvas-rendered', detail);
      return detail;
    }, function (e) { apiRenderDepth -= 1; throw e; });
  }

  /* ---------------- settings panel ---------------- */

  function panelOpen() {
    var box = document.getElementById('sideBoxEfb');
    return !!(box && box.classList.contains('show'));
  }
  function resolveSettingId(id) {
    if (id === 'formSet' || id === 'button_group') return id;
    var list = rows();
    var idx = fieldIndex(list, id);
    if (idx < 0) throw fail('unknown_field', 'No field with id ' + id + '.');
    return list[idx].dataId || list[idx].id_;
  }
  function openSettings(id) {
    var dataId = resolveSettingId(id === undefined ? 'formSet' : String(id));
    if (dataId !== 'formSet' && !document.querySelector('[data-id="' + cssEscape(dataId) + '"]')) {
      throw fail('not_rendered', 'The field is not on the canvas yet; await render() first.');
    }
    // An open panel showing the same element is redrawn, never toggled shut.
    show_setting_window_efb(dataId, { refresh: true });
    return dataId;
  }
  function closeSettings() {
    if (typeof sideMenuEfb === 'function') sideMenuEfb(0);
    window.efbOpenSettingIdEfb = null;
  }
  function refreshSettingsPanel() {
    var id = window.efbOpenSettingIdEfb;
    if (!panelOpen() || !id) return { refreshed: false, closed: false, id: null };
    if (id !== 'formSet' && id !== 'button_group') {
      var list = rows();
      var exists = list.some(function (r, i) { return i > 0 && r && r.dataId === id; });
      if (!exists || !document.querySelector('[data-id="' + cssEscape(id) + '"]')) {
        closeSettings();
        return { refreshed: false, closed: true, id: id };
      }
    }
    show_setting_window_efb(id, { refresh: true });
    return { refreshed: true, closed: false, id: id };
  }

  /* ---------------- form data ---------------- */

  function checkRows(items, existing) {
    if (!Array.isArray(items) || items.length === 0) throw fail('invalid_items', 'items must be a non-empty array.');
    var seen = {};
    (existing || []).forEach(function (r, i) { if (i > 0 && r && r.id_ !== undefined) seen[String(r.id_)] = true; });
    var mine = {};
    items.forEach(function (r) {
      if (!r || typeof r !== 'object' || Array.isArray(r)) throw fail('invalid_items', 'every item must be an object.');
      if (typeof r.id_ !== 'string' || r.id_ === '' || !/^[A-Za-z0-9_-]+$/.test(r.id_)) throw fail('invalid_id', 'every item needs an id_ of letters, digits, - or _.');
      if (typeof r.type !== 'string' || r.type === '' || r.type === 'form') throw fail('invalid_type', 'item ' + r.id_ + ' has no valid type.');
      if (seen[r.id_] || mine[r.id_]) throw fail('duplicate_id', 'id_ ' + r.id_ + ' is already used.');
      mine[r.id_] = true;
    });
    items.forEach(function (r) {
      if (isOptionRow(r) && !mine[r.parent] && !(existing || []).some(function (x) { return x && x.id_ === r.parent && !isOptionRow(x); })) {
        throw fail('orphan_option', 'option ' + r.id_ + ' has no parent field ' + r.parent + '.');
      }
    });
  }

  function setForm(items, opts) {
    if (!Array.isArray(items) || !items[0] || typeof items[0] !== 'object' || typeof items[0].type !== 'string') {
      throw fail('invalid_form', 'setForm() needs the whole form: row 0 holds the form settings.');
    }
    if (items.length > 1) checkRows(items.slice(1), []);
    var frErrs = fieldRulesErrors(items, 0);
    if (frErrs.length) throw failRules(frErrs);
    var next = clone(items);
    var adErrs = advdateCheck(next.slice(1), [next[0]], 1);
    if (adErrs.length) throw failAdvdate(adErrs);
    var upErrs = uploadCheck(next, 0);
    if (upErrs.length) throw failUpload(upErrs);
    var stErrs = smartCheck(next, 0, next);
    if (stErrs.length) throw failSmart(stErrs);
    var chErrs = choicesCheck(next.slice(1), 1, next.slice(1), opts);
    if (chErrs.length) throw failChoices(chErrs);
    var caErrs = calcCheck(next.slice(1), 1, next.slice(1), opts);
    if (caErrs.length) throw failCalc(caErrs);
    var rpErrs = rptCheck(next.slice(1), 1, next.slice(1));
    if (rpErrs.length) throw failRpt(rpErrs);
    var lyErrs = layoutCheck(next.slice(1), 1, next.slice(1), next[0]);
    if (lyErrs.length) throw failLayout(lyErrs);
    var spErrs = spamCheck(next[0], 'rows[0].');
    if (spErrs.length) throw failSpam(spErrs);
    rptPlace(next);
    if (typeof efbNormalizeOrderEfb === 'function') efbNormalizeOrderEfb(next);
    valj_efb = next;
    if (typeof form_type_emsFormBuilder !== 'undefined') form_type_emsFormBuilder = next[0].type;
    if (typeof formName_Efb !== 'undefined' && next[0].formName) formName_Efb = next[0].formName;
    if (typeof step_el_efb !== 'undefined') step_el_efb = Number(next[0].steps) || 1;
    var max = 0;
    next.forEach(function (r) { max = Math.max(max, orderOf(r)); });
    if (typeof amount_el_efb !== 'undefined') amount_el_efb = max;
    apiChanged('setForm', []);
    if (opts && opts.render === false) return Promise.resolve({ complete: false, pending: [], rendered: false });
    return render(opts);
  }

  function insertItems(items, opts) {
    opts = opts || {};
    var list = rows();
    checkRows(items, list);
    var frErrs = fieldRulesErrors(items, 0);
    if (frErrs.length) throw failRules(frErrs);
    var add = clone(items);
    var adErrs = advdateCheck(add, list, 0);
    if (adErrs.length) throw failAdvdate(adErrs);
    var upErrs = uploadCheck(add, 0, true);
    if (upErrs.length) throw failUpload(upErrs);
    var stErrs = smartCheck(add, 0, list.concat(add), true);
    if (stErrs.length) throw failSmart(stErrs);
    var chErrs = choicesCheck(add, 0, list.slice(1).concat(add), opts);
    if (chErrs.length) throw failChoices(chErrs);
    var caErrs = calcCheck(add, 0, list.slice(1).concat(add), opts);
    if (caErrs.length) throw failCalc(caErrs);
    var rpErrs = rptCheck(add, 0, list.slice(1).concat(add));
    if (rpErrs.length) throw failRpt(rpErrs);
    var lyErrs = layoutCheck(add, 0, list.slice(1).concat(add));
    if (lyErrs.length) throw failLayout(lyErrs);
    if (items && items.formPatch && typeof items.formPatch === 'object') {
      var fpErrs = uploadCheck([items.formPatch], 0);
      if (fpErrs.length) throw failUpload(fpErrs);
    }
    // The canvas finds a row by dataId (id_ + '-id'); fill it when the caller left it out.
    add.forEach(function (r) { if (!r.dataId && r.type !== 'step') r.dataId = r.id_ + '-id'; });
    var at;
    var stepRow = null;
    if (add.some(function (r) { return r.type === 'step'; })) {
      if (add.length !== 1 || opts.after || opts.before) throw fail('unsupported_position', 'a step is inserted alone, at the end of the form.');
      var next = (Number(list[0].steps) || 1) + 1;
      stepRow = add[0];
      stepRow.id_ = stepRow.dataId = stepRow.id = String(next);
      stepRow.step = next;
      if (list.some(function (r, i) { return i > 0 && r && r.id_ === stepRow.id_; })) throw fail('duplicate_id', 'step ' + next + ' exists.');
      at = list.length;
    } else if (opts.after !== undefined) {
      var a = fieldIndex(list, String(opts.after));
      if (a < 0) throw fail('unknown_target', 'No field with id ' + opts.after + '.');
      at = fieldEnd(list, a) + 1;
    } else if (opts.before !== undefined) {
      var b = fieldIndex(list, String(opts.before));
      if (b < 0) throw fail('unknown_target', 'No field with id ' + opts.before + '.');
      if (list[b].type === 'step' && Number(list[b].step) === 1) throw fail('unsupported_position', 'nothing goes before step 1.');
      at = b;
    } else if (opts.step !== undefined) {
      var n = Number(opts.step);
      var s = -1, nextStep = list.length;
      for (var i = 1; i < list.length; i++) {
        if (list[i] && list[i].type === 'step') {
          if (Number(list[i].step) === n) s = i;
          else if (s >= 0 && Number(list[i].step) > n) { nextStep = i; break; }
        }
      }
      if (s < 0) throw fail('unknown_step', 'No step ' + opts.step + '.');
      at = nextStep;
    } else {
      at = list.length;
    }
    Array.prototype.splice.apply(list, [at, 0].concat(add));
    if (stepRow) {
      list[0].steps = stepRow.step;
      if (typeof step_el_efb !== 'undefined') step_el_efb = stepRow.step;
    }
    if (items.formPatch && typeof items.formPatch === 'object') {
      Object.keys(items.formPatch).forEach(function (k) { list[0][k] = clone(items.formPatch[k]); });
      if (items.formPatch.type && typeof form_type_emsFormBuilder !== 'undefined') form_type_emsFormBuilder = items.formPatch.type;
    }
    if (add.some(function (r) { return r && (r.type === 'repeater' || (r.group !== undefined && r.group !== '')); })) rptPlace(list);
    renumber(list);
    var ids = add.map(function (r) { return r.id_; });
    apiChanged('insertItems', ids);
    if (opts.render === true) return render(opts).then(function () { return { ids: ids }; });
    return { ids: ids };
  }

  function removeItems(ids) {
    var list = rows();
    var drop = {};
    (Array.isArray(ids) ? ids : [ids]).forEach(function (id) {
      var i = fieldIndex(list, String(id));
      if (i < 0) throw fail('unknown_field', 'No field with id ' + id + '.');
      if (list[i].type === 'step') throw fail('unsupported_type', 'steps are removed in the builder.');
      drop[list[i].id_] = true;
      // F09: a repeater group goes with its fields (their options follow them through parent).
      if (list[i].type === 'repeater') list.forEach(function (k) { if (k && k.group === list[i].id_ && k.type !== 'option') drop[k.id_] = true; });
    });
    valj_efb = list.filter(function (r, i) { return i === 0 || !(r && (drop[r.id_] || drop[r.parent])); });
    renumber(valj_efb);
    apiChanged('removeItems', Object.keys(drop));
    // F04: a field another calc field reads is gone: a warning per formula (the formula keeps its text; the save marks it).
    var warnings = [];
    if (window.EFBCalc) {
      valj_efb.forEach(function (r) {
        if (!r || r.type !== 'calc') return;
        window.EFBCalc.refIds(String(r.formula || '')).forEach(function (rid) { if (drop[rid]) warnings.push({ code: 'dangling_reference', field: r.id_, ref: rid }); });
      });
    }
    return warnings.length ? { ids: Object.keys(drop), warnings: warnings } : { ids: Object.keys(drop) };
  }

  /* ---------------- B2: field templates without the DOM ---------------- */

  var COMPOSITES = {
    name: ['firstName', 'lastName'],
    address: ['conturyList', 'stateProvince', 'cityList', 'address_line', 'postalcode'],
  };
  function paletteIds() {
    var ids = (typeof fields_efb !== 'undefined' && Array.isArray(fields_efb)) ? fields_efb.map(function (f) { return f.id; }) : [];
    ['firstName', 'lastName', 'address_line', 'postalcode', 'paySelect', 'payMultiselect', 'persiaPay', 'chlRadio'].forEach(function (x) { if (ids.indexOf(x) < 0) ids.push(x); });
    return ids;
  }
  function randomId() { return Math.random().toString(36).substr(2, 9); }

  /* Runs the builder's own addNewElement() on a scratch copy of the form, so the rows
     are exactly what a drop on the canvas creates, while every side effect is held
     back: the canvas, the button zone, styles, timers (map and email checks, the
     intlTelInput init) and the plan dialog. The live form, the DOM and the globals
     are restored before returning. */
  function newItems(paletteId, opts) {
    opts = opts || {};
    paletteId = String(paletteId || '');
    if (typeof addNewElement !== 'function') throw fail('builder_not_ready', 'The form builder is not loaded.');
    var parts = COMPOSITES[paletteId] || [paletteId];
    // PAY00: a framework gateway field exists once its add-on registered the gateway.
    var payAddon = { mollie: 'AdnMOL', payplug: 'AdnPPG', tappay: 'AdnTAP', omise: 'AdnOMS', paytabs: 'AdnPTB', paymob: 'AdnPMB', xendit: 'AdnXND' }[paletteId];
    if (payAddon && !(typeof efbPayIsGatewayEfb === 'function' && efbPayIsGatewayEfb(paletteId))) {
      throw fail('addon_required', paletteId + ' needs the ' + payAddon + ' add-on.');
    }
    if (typeof efbPayPaletteEfb === 'function' && typeof fields_efb !== 'undefined') efbPayPaletteEfb(fields_efb);
    if (!COMPOSITES[paletteId] && paletteIds().indexOf(paletteId) < 0) throw fail('unknown_palette_id', 'Unknown palette id ' + paletteId + '.');
    // The builder draws a gateway only when its add-on is installed (otherwise it shows a notice).
    var gatewayAddon = { stripe: 'AdnSPF', paypal: 'AdnPAP', persiaPay: 'AdnPPF' }[paletteId];
    if (gatewayAddon && !(typeof addons_emsFormBuilder === 'object' && addons_emsFormBuilder && Number(addons_emsFormBuilder[gatewayAddon]) === 1)) {
      throw fail('addon_required', paletteId + ' needs the ' + gatewayAddon + ' add-on.');
    }

    var live = rows();
    var saved = {
      valj: valj_efb, step: step_el_efb, amount: amount_el_efb, type: form_type_emsFormBuilder,
      setTimeout: window.setTimeout, buttons: (typeof add_buttons_zone_efb !== 'undefined') ? add_buttons_zone_efb : null,
      style: window.fun_addStyle_costumize_efb, warn: window.efb_builder_maybe_warn_email_delivery,
      pro: window.pro_show_efb, upg: window.efb_upgrade_prompt_efb, iti: window.load_intlTelInput_efb,
    };
    var row0 = clone(live[0] || { type: 'form', steps: 1 });
    var stepRow = clone(live[1] && live[1].type === 'step' ? live[1] : { type: 'step', id_: '1', dataId: '1', step: 1, amount: 1 });
    var scratch = [row0, stepRow];
    var blocked = false;
    var out;
    try {
      valj_efb = scratch;
      step_el_efb = 1;
      amount_el_efb = 1;
      window.setTimeout = function () { return 0; };
      if (saved.buttons) add_buttons_zone_efb = function () { return ''; };
      window.fun_addStyle_costumize_efb = function () {};
      window.efb_builder_maybe_warn_email_delivery = function () {};
      window.pro_show_efb = function () { blocked = true; };
      // F18: core locks open efb_upgrade_prompt_efb() directly; a template must never show it.
      if (saved.upg) window.efb_upgrade_prompt_efb = function () { blocked = true; return { shown: false }; };
      window.load_intlTelInput_efb = function () {};
      try {
        parts.forEach(function (p) { addNewElement(p, randomId(), false, false); });
      } catch (e) {
        throw fail('template_failed', 'The builder could not create ' + paletteId + ': ' + (e && e.message));
      }
      out = clone(scratch.slice(2));
      var patch = {};
      Object.keys(scratch[0]).forEach(function (k) {
        if (k === 'steps') return; // insertItems() counts steps itself
        if (JSON.stringify(scratch[0][k]) !== JSON.stringify(live[0] ? live[0][k] : undefined)) patch[k] = clone(scratch[0][k]);
      });
      out.__patch = patch;
    } finally {
      valj_efb = saved.valj;
      step_el_efb = saved.step;
      amount_el_efb = saved.amount;
      form_type_emsFormBuilder = saved.type;
      window.setTimeout = saved.setTimeout;
      if (saved.buttons) add_buttons_zone_efb = saved.buttons;
      window.fun_addStyle_costumize_efb = saved.style;
      window.efb_builder_maybe_warn_email_delivery = saved.warn;
      window.pro_show_efb = saved.pro;
      if (saved.upg) window.efb_upgrade_prompt_efb = saved.upg;
      window.load_intlTelInput_efb = saved.iti;
    }
    if (blocked || out.length === 0) throw fail('plan_required', 'The builder refused ' + paletteId + ' on this plan.');
    var formPatch = out.__patch;
    delete out.__patch;

    // Fixed option ids (the UK states and cities of a new address) are renamed when
    // the open form already has them, so a second address block can be inserted.
    var used = {};
    live.forEach(function (r) { if (r && r.id_ !== undefined) used[String(r.id_)] = true; });
    out.forEach(function (r) {
      if (isOptionRow(r) && used[r.id_]) { var n = randomId(); r.id_ = n; r.dataId = n + '-id'; }
      used[r.id_] = true;
    });

    var fieldsOut = out.filter(function (r) { return !isOptionRow(r); });
    if (opts.label !== undefined && fieldsOut.length === 1) fieldsOut[0].name = String(opts.label);
    if (opts.required !== undefined) fieldsOut.forEach(function (r) { if ('required' in r) r.required = opts.required ? 1 : 0; });
    if (opts.size !== undefined) fieldsOut.forEach(function (r) { if ('size' in r) r.size = opts.size; });
    if (Array.isArray(opts.options) && fieldsOut.length === 1) {
      var parent = fieldsOut[0];
      var tpl = out.filter(function (r) { return r.parent === parent.id_; })[0];
      if (!tpl) throw fail('no_options', paletteId + ' has no option rows.');
      out = out.filter(function (r) { return r.parent !== parent.id_; });
      opts.options.forEach(function (o) {
        var row = clone(tpl);
        var id = randomId();
        var value = (o && typeof o === 'object') ? o.value : o;
        row.id_ = id; row.dataId = id + '-id'; row.id_op = (o && typeof o === 'object' && o.id_op) ? String(o.id_op) : id;
        row.value = String(value == null ? '' : value);
        if (o && typeof o === 'object' && o.price !== undefined && 'price' in tpl) row.price = Number(o.price) || 0;
        out.push(row);
      });
    }
    if (Object.keys(formPatch).length) Object.defineProperty(out, 'formPatch', { value: formPatch, enumerable: false });
    return out;
  }

  /* ---------------- B4: login / register form type ---------------- */

  var FORM_TYPES = ['form', 'payment', 'survey', 'login', 'register'];
  function authFieldSpec(type) {
    var t = (typeof efb_var !== 'undefined' && efb_var.text) ? efb_var.text : {};
    if (type === 'login') return [
      { id: 'emaillogin', palette: 'text', label: t.emailOrUsername },
      { id: 'passwordlogin', palette: 'password', label: t.password },
    ];
    if (type === 'register') return [
      { id: 'usernameRegisterEFB', palette: 'text', label: t.username },
      { id: 'passwordRegisterEFB', palette: 'password', label: t.password },
      { id: 'emailRegisterEFB', palette: 'email', label: t.email, extra: { noti: 1 } },
    ];
    return [];
  }
  function authFieldIds(type) { return authFieldSpec(type).map(function (s) { return s.id; }); }

  /* Changes valj_efb[0].type. login / register add the fixed-id fields the public handler
     reads (emaillogin, passwordlogin / usernameRegisterEFB, passwordRegisterEFB,
     emailRegisterEFB) right after the step-1 heading when they are missing. Leaving login /
     register keeps those fields unless opts.removeAuthFields is true. */
  function setFormTypeCore(type, opts) {
    opts = opts || {};
    if (FORM_TYPES.indexOf(type) < 0) throw fail('invalid_type', 'Unknown form type ' + type + '.');
    var list = rows();
    var old = opts.previous !== undefined ? opts.previous : list[0].type;
    list[0].type = type;
    if (typeof form_type_emsFormBuilder !== 'undefined') form_type_emsFormBuilder = type;
    var removed = [];
    if (opts.removeAuthFields === true && old !== type && (old === 'login' || old === 'register')) {
      var gone = authFieldIds(old);
      valj_efb = list.filter(function (r, i) { if (i > 0 && r && gone.indexOf(r.id_) >= 0) { removed.push(r.id_); return false; } return true; });
      list = valj_efb;
    }
    var added = [];
    var want = authFieldSpec(type).filter(function (s) { return !list.some(function (r, i) { return i > 0 && r && r.id_ === s.id; }); });
    if (want.length) {
      var newRows = [];
      want.forEach(function (s) {
        var made = newItems(s.palette, { required: true });
        var f = made[0];
        f.id_ = s.id; f.dataId = s.id + '-id';
        if (s.label) { f.name = s.label; f.placeholder = s.label; }
        if (s.extra) Object.keys(s.extra).forEach(function (k) { f[k] = s.extra[k]; });
        newRows.push(f);
        added.push(s.id);
      });
      var first = -1;
      for (var i = 2; i < list.length; i++) { if (list[i] && list[i].type !== 'step' && !isOptionRow(list[i])) { first = i; break; } if (list[i] && list[i].type === 'step') break; }
      Array.prototype.splice.apply(list, [first > 0 ? first : 2, 0].concat(newRows));
    }
    if (type === 'register' && (!list[0].email_to || list[0].email_to === 'false')) list[0].email_to = 'emailRegisterEFB';
    renumber(list);
    persist();
    baselineJson = snapshot();
    emit('efb:form-changed', { source: opts.source || 'api', action: 'setFormType', ids: added.concat(removed) });
    return { type: type, previous: old, added: added, removed: removed };
  }
  function setFormType(type, opts) {
    var result = setFormTypeCore(String(type), opts);
    if (opts && opts.render === false) return Promise.resolve(result);
    return render(opts).then(function () { refreshSettingsPanel(); return result; });
  }
  // Called by the form-type select (change_el_edit_Efb, case formTypeEl) after it set the type.
  window.efb_after_form_type_change_efb = function (oldType, newType) {
    var leaving = (oldType === 'login' || oldType === 'register') && oldType !== newType;
    var present = leaving ? authFieldIds(oldType).filter(function (id) { return rows().some(function (r, i) { return i > 0 && r && r.id_ === id; }); }) : [];
    var ask = present.length && typeof window.efb_confirm_efb === 'function'
      ? window.efb_confirm_efb({ variant: 'warning', message: (efb_var.text && efb_var.text.authFieldsRemoveQ) || 'Remove the login and registration fields from this form?' })
      : Promise.resolve(false);
    return ask.then(function (yes) {
      var r = setFormTypeCore(newType, { removeAuthFields: yes === true, source: 'user', previous: oldType });
      if (r.added.length || r.removed.length) return render().then(function () { refreshSettingsPanel(); return r; });
      return r;
    });
  };

  /**
   * PAY00 readiness: whether visitors can pay with this form, and why not. Read only; the server
   * computes it (Payments\Readiness::of_form). opts.draft (default true) = the form as it is now in the
   * builder, saved or not; draft:false = the stored form (opts.formId, default the open form).
   * Resolves {form_id, has_payment, ready, issues:[{code, level, m, field_id, gateway, fix:{kind, url, label}}]}.
   */
  function paymentReadiness(opts) {
    opts = opts || {};
    var ev = window.efb_var || {};
    var cap = ev.capabilities && ev.capabilities.payments && ev.capabilities.payments.readiness;
    var url = (cap && cap.rest) || ((ev.rest_url || '/wp-json/') + 'Emsfb/v1/payments/readiness');
    var id = Number(opts.formId || (typeof form_ID_emsFormBuilder !== 'undefined' ? form_ID_emsFormBuilder : 0)) || 0;
    var draft = opts.draft !== false;
    var headers = { 'X-WP-Nonce': ev.nonce || '' };
    var init = { credentials: 'same-origin', headers: headers };
    if (draft) {
      if (!rows()) return Promise.reject(fail('builder_not_ready', 'The builder is not ready.'));
      headers['Content-Type'] = 'application/json';
      init.method = 'POST';
      init.body = JSON.stringify({ form: clone(rows()), form_id: id });
    } else {
      if (!id) return Promise.reject(fail('unknown_form', 'The form is not saved yet.'));
      init.method = 'GET';
      url += (url.indexOf('?') === -1 ? '?' : '&') + 'form_id=' + id;
    }
    return fetch(url, init).then(function (r) {
      return r.json().then(function (j) {
        if (!r.ok) throw fail((j && j.code) || 'readiness_failed', (j && j.message) || 'Readiness could not be read.');
        return j;
      });
    });
  }

  /**
   * F11: change form settings (row 0) with the server's rules, e.g.
   * setFormSettings({lim_total: '100', sched_close: '2026-12-31T23:59', lim_period: 'day'}).
   * The F11 keys (efb_var.capabilities.form_gate.keys) are normalized as the save keeps them
   * (Persian / Arabic digits accepted); a value the server would drop, closing before opening,
   * or custom_css without unfiltered_html throws (code invalid_setting / sched_order /
   * no_unfiltered_html) and changes nothing. Other scalar keys are written as strings.
   * Returns {changed: [keys], values: {key: stored value}}. Saving stays the owner's Save.
   */
  function setFormSettings(patch, opts) {
    var r = rows();
    if (!r || !r[0]) throw fail('builder_not_ready', 'The builder is not ready.');
    if (!patch || typeof patch !== 'object' || Array.isArray(patch)) throw fail('invalid_setting', 'setFormSettings() needs an object of row-0 keys.');
    var keys = Object.keys(patch);
    keys.forEach(function (k) {
      var v = patch[k];
      if (!/^[A-Za-z0-9_]+$/.test(k) || k === 'type' || k === 'id_') throw fail('invalid_setting', 'key ' + k + ' cannot be set here.');
      if (v !== null && typeof v === 'object') throw fail('invalid_setting', k + ' must be a string, number or boolean.');
    });
    if (typeof efb_limits_validate_efb === 'function') {
      var errs = efb_limits_validate_efb(patch, r[0]);
      if (errs.length) throw fail(errs[0].code, errs[0].message);
    }
    // F10: the review / section keys of row 0 with the save rules (a form paid inside the form has no review page).
    var lay0 = {};
    ['review_before_submit', 'review_hide_empty', 'review_title', 'section_accordion', 'vis_view', 'vis_empty'].forEach(function (k) { if (patch[k] !== undefined) lay0[k] = patch[k]; });
    if (Object.keys(lay0).length) {
      var lyErrs0 = layoutCheck([], 0, r.slice(1), lay0);
      if (lyErrs0.length) throw failLayout(lyErrs0);
    }
    // F12: the anti-spam keys with the save rules; secrets and the site's own settings are refused.
    var spam0 = {};
    keys.forEach(function (k) { spam0[k] = patch[k]; });
    var spErrs0 = spamCheck(spam0, '');
    if (spErrs0.length) throw failSpam(spErrs0);
    var values = {};
    keys.forEach(function (k) {
      var v = patch[k];
      var f11 = typeof efb_limits_value_efb === 'function' ? efb_limits_value_efb(k, v) : null;
      values[k] = lay0 && lay0[k] !== undefined ? lay0[k] : (SPAM_FORM_KEYS.indexOf(k) !== -1 ? spam0[k] : (f11 !== null ? f11 : (v === true ? '1' : v === false ? '' : String(v == null ? '' : v))));
    });
    keys.forEach(function (k) { r[0][k] = values[k]; });
    try { sessionStorage.setItem('valj_efb', JSON.stringify(r)); } catch (e) {}
    apiChanged('setFormSettings', []);
    if (!opts || opts.refresh !== false) { try { refreshSettingsPanel(); } catch (e) {} }
    return { changed: keys, values: values };
  }

  /* F03: which rule (EfbRules.TYPES) a field key belongs to; msg_* fit every text-like field. */
  var FR_PART = { unique: 'unique', unique_scope: 'unique', wmin: 'words', wmax: 'words', counter: 'counter', counter_style: 'counter',
    pattern: 'pattern', pattern_b64: 'pattern', pattern_case: 'pattern', pattern_ascii: 'pattern', email_domain_mode: 'domain', email_domains: 'domain',
    readonly: 'readonly', prefix: 'affix', suffix: 'affix', message_place: 'help', pw_confirm: 'password', pw_meter: 'password', pw_toggle: 'password',
    pw_strength_min: 'password', pw_confirm_label: 'password' };
  /** The F03 errors of rows written by the API (a pattern the save would empty): [{code, path, reason, pos}]. */
  function fieldRulesErrors(items, offset) {
    var R = window.EfbRules;
    var out = [];
    if (!R) return out;
    (items || []).forEach(function (r, i) {
      if (!r || r.pattern_b64 === undefined || r.pattern_b64 === '' || r.pattern_b64 === null) return;
      var src = typeof r.pattern_b64 === 'string' ? R.b64urlDecode(r.pattern_b64) : null;
      var e = src === null ? { code: 'encoding', pos: 0 } : R.lintRegex(src);
      if (e) out.push({ code: 'pattern_invalid', path: 'rows[' + (i + (offset || 0)) + '].pattern_b64', reason: e.code, pos: e.pos });
    });
    return out;
  }

  /* ---------------- F06 advanced date ---------------- */

  /**
   * F06: the advdate rows of items are written with the save rules (EFBCalendar.rules.sanitizeKey, twin
   * of Calendar::sanitize_key): a value the rules cannot use (an unknown calendar, a limit that is not a
   * limit, a date that does not exist, a word outside a list) is refused with advdate_invalid and the
   * path of the key; limits that contradict each other (min after max, a circle of field limits) with
   * advdate_config. Nothing changes on a throw.
   */
  function advdateCheck(items, existing, offset) {
    var C = window.EFBCalendar;
    var out = [];
    if (!C || !C.rules || !C.rules.sanitizeKey) return out;
    var all = (existing || []).concat(items || []);
    (items || []).forEach(function (r, i) {
      if (!r || r.type !== 'advdate') return;
      var path = 'rows[' + (i + (offset || 0)) + '].';
      C.rules.fieldKeys.forEach(function (k) {
        if (r[k] === undefined || r[k] === null) return;
        var v = r[k], s = C.rules.sanitizeKey(k, v), bad = false;
        if (['cal_default', 'cal_secondary', 'cal_digits', 'date_mode', 'holiday_mode', 'range_unit', 'store_format', 'input_mode', 'display_format', 'tz', 'availability', 'cal_locale'].indexOf(k) !== -1) bad = String(v) !== String(s) && !(v === '' && (s === '' || s === 'auto' || s === 'none'));
        else if (k === 'cal_allowed' || k === 'disabled_weekdays' || k === 'holiday_sets' || k === 'disabled_dates' || k === 'enabled_dates' || k === 'holidays_custom') bad = !Array.isArray(v) || s.length !== v.length;
        else if (k === 'date_min' || k === 'date_max') bad = !!(v && typeof v === 'object' && v.type && v.type !== s.type);
        else if (k === 'time_min' || k === 'time_max') bad = v !== '' && s === '';
        else if (k === 'time_by_weekday') bad = !v || typeof v !== 'object' || Array.isArray(v) || Object.keys(v).some(function (d) { return !Array.isArray(v[d]) || !s[d] || s[d].length !== v[d].length; });
        else if (['cal_hijri_adjust', 'range_min_days', 'range_max_days', 'multi_max', 'time_step', 'age_min', 'age_max', 'week_start'].indexOf(k) !== -1) bad = v !== '' && v !== 'auto' && String(s) !== String(Number(v));
        if (bad) out.push({ code: 'advdate_invalid', path: path + k, key: k, field: r.id_ });
        else r[k] = s;
      });
      C.rules.configIssues(r, all).forEach(function (x) {
        if (x.code === 'dangling_reference' || x.code === 'default_not_allowed') return;
        out.push({ code: 'advdate_config', reason: x.code, path: path + x.key, key: x.key, field: r.id_ });
      });
    });
    return out;
  }
  function failAdvdate(errs) {
    var e = fail(errs[0].code, errs[0].code === 'advdate_config' ? 'These date limits contradict each other: ' + errs[0].reason + ' (' + errs[0].path + ')' : 'This value cannot be used for ' + errs[0].key + ' (' + errs[0].path + ')');
    e.path = errs[0].path; e.errors = errs; if (errs[0].reason) e.reason = errs[0].reason;
    return e;
  }
  /**
   * F06: change the advanced date keys of one field with the save rules, e.g.
   * setDateConfig('appt', {cal_default: 'persian', cal_allowed: ['persian', 'gregory'], cal_switch: 1,
   * date_min: {type: 'relative', value: 1}, disabled_weekdays: [5], holiday_sets: ['ir-official']}).
   * A key that is not an advdate key throws invalid_setting; a value the rules cannot use advdate_invalid;
   * contradicting limits advdate_config. Returns {changed, values}. Saving stays the owner's Save.
   */
  function setDateConfig(id, patch) {
    var C = window.EFBCalendar;
    if (!C || !C.rules) throw fail('builder_not_ready', 'The calendar engine (efb-calendar-core.js) is not loaded.');
    var list = rows();
    var idx = fieldIndex(list, String(id));
    if (idx < 0) idx = list.findIndex(function (r, i) { return i > 0 && r && r.dataId === String(id); });
    if (idx < 1 || list[idx].type !== 'advdate') throw fail('unknown_field', 'No advanced date field with id ' + id + '.');
    if (!patch || typeof patch !== 'object' || Array.isArray(patch)) throw fail('invalid_setting', 'setDateConfig() needs an object of advdate keys.');
    Object.keys(patch).forEach(function (k) { if (C.rules.fieldKeys.indexOf(k) === -1 && k !== 'value' && k.indexOf('bk_') !== 0) throw fail('invalid_setting', 'key ' + k + ' is not an advanced date key.'); });
    var next = clone(list[idx]);
    Object.keys(patch).forEach(function (k) { next[k] = clone(patch[k]); });
    var probe = list.slice(); probe[idx] = next;
    var errs = advdateCheck([next], probe.filter(function (r, i) { return i !== idx; }), idx);
    if (errs.length) throw failAdvdate(errs);
    list[idx] = next;
    apiChanged('setDateConfig', [next.id_]);
    var values = {};
    Object.keys(patch).forEach(function (k) { values[k] = clone(next[k]); });
    return { changed: Object.keys(patch), values: values };
  }
  /** F06 §6.4: date / pdate / ardate -> advdate (old keys kept for the way back); opts.keepLocal = store the local string. */
  function upgradeDateField(id, opts) {
    var C = window.EFBCalendar;
    if (!C || !C.rules) throw fail('builder_not_ready', 'The calendar engine (efb-calendar-core.js) is not loaded.');
    var list = rows();
    var idx = fieldIndex(list, String(id));
    if (idx < 1 || ['date', 'pdate', 'ardate'].indexOf(list[idx].type) === -1) throw fail('unknown_field', 'No date, pdate or ardate field with id ' + id + '.');
    list[idx] = C.rules.upgradeRow(list[idx], { keepLocal: !!(opts && opts.keepLocal) });
    apiChanged('upgradeDateField', [list[idx].id_]);
    return { id: list[idx].id_, row: clone(list[idx]) };
  }
  function rollbackDateField(id) {
    var C = window.EFBCalendar;
    if (!C || !C.rules) throw fail('builder_not_ready', 'The calendar engine (efb-calendar-core.js) is not loaded.');
    var list = rows();
    var idx = fieldIndex(list, String(id));
    if (idx < 1 || list[idx].type !== 'advdate' || !list[idx].upg_from) throw fail('unknown_field', 'No upgraded date field with id ' + id + '.');
    list[idx] = C.rules.rollbackRow(list[idx]);
    apiChanged('rollbackDateField', [list[idx].id_]);
    return { id: list[idx].id_, row: clone(list[idx]) };
  }
  /**
   * F07: the upload keys of the rows (file / dadfile: max_files, min_files, max_total_mb, capture,
   * img_max_px, img_quality, file_store; row 0: email_attach) with the save rules
   * (EFBUpload.rules.sanitizeKey = Emsfb\Uploads::sanitize_key). A value the rules change or drop is
   * refused with upload_invalid and its path; a value written in Persian / Arabic digits is accepted
   * and stored in Latin digits. Rows are rewritten with the stored values.
   */
  function uploadCheck(items, offset, noRow0) {
    var Up = window.EFBUpload;
    var out = [];
    if (!Up || !Up.rules) return out;
    (items || []).forEach(function (r, i) {
      if (!r || typeof r !== 'object') return;
      var path = 'rows[' + (i + (offset || 0)) + '].';
      var keys = (i === 0 && !noRow0 && r.type !== 'file' && r.type !== 'dadfile') ? Up.rules.FORM_KEYS : ((r.type === 'file' || r.type === 'dadfile') ? Up.rules.FIELD_KEYS : []);
      keys.forEach(function (k) {
        if (r[k] === undefined || r[k] === null || r[k] === '') return;
        if (typeof r[k] === 'object') { out.push({ code: 'upload_invalid', path: path + k, key: k, field: r.id_ }); return; }
        var sv = Up.rules.sanitizeKey(k, r[k]);
        var asNum = String(r[k]).replace(/[\u06F0-\u06F9]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); }).replace(/[\u0660-\u0669]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); });
        var same = sv === String(r[k]) || sv === asNum || (sv !== '' && Number(sv) === Number(asNum)) || (k === 'email_attach' && (r[k] === 1 || r[k] === true) && sv === '1') || (k === 'capture' || k === 'file_store' ? sv === String(r[k]).trim().toLowerCase() : false);
        if (!same) out.push({ code: 'upload_invalid', path: path + k, key: k, field: r.id_, stored: sv });
        else r[k] = sv;
      });
      if ((r.type === 'file' || r.type === 'dadfile') && r.min_files && r.max_files && Number(r.min_files) > Number(r.max_files)) {
        out.push({ code: 'upload_config', reason: 'min_after_max', path: path + 'min_files', key: 'min_files', field: r.id_ });
      }
    });
    return out;
  }
  function failUpload(errs) {
    var e = fail(errs[0].code, errs[0].code === 'upload_config' ? 'min_files is larger than max_files (' + errs[0].path + ')' : 'This value cannot be used for ' + errs[0].key + ' (' + errs[0].path + ')');
    e.path = errs[0].path; e.errors = errs; if (errs[0].reason) e.reason = errs[0].reason;
    return e;
  }
  /**
   * F07: change the upload keys of one file / dadfile field (by id_ or dataId) with the save rules, e.g.
   * setUploadConfig('docs', {max_files: 5, min_files: 1, max_total_mb: 50, file_store: 'private'}).
   * A key that is not an upload key throws invalid_setting; a value the rules cannot keep upload_invalid
   * (e.path); min_files above max_files upload_config. A part the plan does not include is stored but
   * ignored on the page and on submit (e.plan in the answer lists them). Returns {changed, values, plan}.
   */
  function setUploadConfig(id, patch, opts) {
    var Up = window.EFBUpload;
    if (!Up || !Up.rules) throw fail('builder_not_ready', 'The upload rules (upload-efb.js) are not loaded.');
    var list = rows();
    var idx = fieldIndex(list, String(id));
    if (idx < 0) idx = list.findIndex(function (r, i) { return i > 0 && r && r.dataId === String(id); });
    if (idx < 1 || (list[idx].type !== 'file' && list[idx].type !== 'dadfile')) throw fail('unknown_field', 'No file or dadfile field with id ' + id + '.');
    if (!patch || typeof patch !== 'object' || Array.isArray(patch)) throw fail('invalid_setting', 'setUploadConfig() needs an object of upload keys.');
    Object.keys(patch).forEach(function (k) { if (Up.rules.FIELD_KEYS.indexOf(k) === -1) throw fail('invalid_setting', 'key ' + k + ' is not an upload key.'); });
    var next = clone(list[idx]);
    Object.keys(patch).forEach(function (k) { next[k] = patch[k]; });
    var errs = uploadCheck([next], idx, true);
    if (errs.length) throw failUpload(errs);
    list[idx] = next;
    persist();
    apiChanged('setUploadConfig', [next.id_]);
    if (!opts || opts.refresh !== false) { try { refreshSettingsPanel(); } catch (e) {} }
    var values = {};
    Object.keys(patch).forEach(function (k) { values[k] = next[k]; });
    var cap = (window.efb_var && window.efb_var.capabilities && window.efb_var.capabilities.features) || {};
    var gated = [];
    if (Number(values.max_files || next.max_files) > 1 && cap['setting:upload_multi'] && cap['setting:upload_multi'].available_now === false) gated.push('max_files');
    if (Number(values.img_max_px) > 0 && cap['setting:upload_resize'] && cap['setting:upload_resize'].available_now === false) gated.push('img_max_px');
    if (values.file_store === 'media' && cap['setting:upload_media'] && cap['setting:upload_media'].available_now === false) gated.push('file_store');
    return { changed: Object.keys(values), values: values, plan: gated };
  }
  /**
   * F08: hidden fields, smart tags and prefill keys with the save rules (EFBSmart = Emsfb\Smart_Tags):
   * hiddenField needs a scalar value (a template, at most 500 characters, no unknown namespace: smart_invalid
   * with the path); hdn_visitor_mail / prefill_lock "1" or nothing; prefill_key [A-Za-z0-9_-]{1,40}, not a
   * WordPress query variable, only on the types an address may fill (smart_prefill), once per form
   * (smart_prefill_dup); st_uses is the server's (dropped from row 0). Rows are rewritten with the stored values.
   */
  function smartCheck(items, offset, all, noRow0) {
    var S = window.EFBSmart;
    var out = [];
    if (!S) return out;
    var keys = {};
    (all || []).forEach(function (r) { if (r && typeof r.prefill_key === 'string' && r.prefill_key !== '' && (items || []).indexOf(r) === -1) keys[r.prefill_key.toLowerCase()] = r.id_; });
    (items || []).forEach(function (r, i) {
      if (!r || typeof r !== 'object') return;
      var path = 'rows[' + (i + (offset || 0)) + '].';
      // st_uses is the server's (computed on save): a copy in row 0 is dropped, never trusted.
      if (i === 0 && !noRow0 && r.st_uses !== undefined) delete r.st_uses;
      if (r.type === 'hiddenField') {
        if (r.value !== undefined && typeof r.value !== 'string' && typeof r.value !== 'number') { out.push({ code: 'smart_invalid', path: path + 'value', key: 'value', field: r.id_ }); return; }
        var raw = String(r.value === undefined ? '' : r.value);
        var clean = S.cleanTemplate(raw);
        var unknown = (raw.match(/\{([a-z][a-z0-9_]{0,15}):[^{}]{0,200}\}/g) || []).filter(function (u) { return S.NS.indexOf(/^\{([a-z][a-z0-9_]*)/.exec(u)[1]) === -1; });
        if (unknown.length || Array.from(raw).length > S.MAX) { out.push({ code: 'smart_invalid', path: path + 'value', key: 'value', field: r.id_, reason: unknown.length ? 'unknown_tag' : 'too_long', stored: clean }); return; }
        r.value = clean;
        delete r.required;
      }
      ['hdn_visitor_mail', 'prefill_lock'].forEach(function (k) {
        if (r[k] === undefined || r[k] === '' || r[k] === null) return;
        var sv = S.sanitizeKey(k, r[k]);
        if (sv === '') out.push({ code: 'smart_invalid', path: path + k, key: k, field: r.id_ }); else r[k] = sv;
      });
      if (r.prefill_key !== undefined && r.prefill_key !== '' && r.prefill_key !== null) {
        var pk = S.sanitizeKey('prefill_key', r.prefill_key);
        if (pk === '' || S.PREFILL_TYPES.indexOf(r.type) === -1) { out.push({ code: 'smart_prefill', path: path + 'prefill_key', key: 'prefill_key', field: r.id_, reason: pk === '' ? (S.RESERVED_KEYS.indexOf(String(r.prefill_key).toLowerCase()) !== -1 ? 'reserved' : 'invalid') : 'type' }); return; }
        if (keys[pk.toLowerCase()] && keys[pk.toLowerCase()] !== r.id_) { out.push({ code: 'smart_prefill_dup', path: path + 'prefill_key', key: 'prefill_key', field: r.id_, other: keys[pk.toLowerCase()] }); return; }
        keys[pk.toLowerCase()] = r.id_;
        r.prefill_key = pk;
      }
    });
    return out;
  }
  function failSmart(errs) {
    var e0 = errs[0];
    var e = fail(e0.code, e0.code === 'smart_prefill_dup' ? 'Another field (' + e0.other + ') already uses this prefill key (' + e0.path + ')' : 'This value cannot be used for ' + e0.key + ' (' + e0.path + ')');
    e.path = e0.path; e.errors = errs; if (e0.reason) e.reason = e0.reason;
    return e;
  }
  /**
   * F08: change a hidden field (by id_ or dataId): setHiddenField('utm', {value: '{get:utm_source}', hdn_visitor_mail: ''})
   * or a preset name: setHiddenField('utm', {preset: 'utm_campaign'}) / {preset: 'param', param: 'ref_code'}.
   * Throws unknown_field, invalid_setting (a key that is not value / preset / param / hdn_visitor_mail / name /
   * prefill_key) or the codes of smartCheck. Returns {id, value}.
   */
  function setHiddenField(id, patch, opts) {
    var S = window.EFBSmart;
    if (!S) throw fail('builder_not_ready', 'The smart-tag rules (smart-tags-efb.js) are not loaded.');
    var list = rows();
    var idx = fieldIndex(list, String(id));
    if (idx < 0) idx = list.findIndex(function (r, i) { return i > 0 && r && r.dataId === String(id); });
    if (idx < 1 || list[idx].type !== 'hiddenField') throw fail('unknown_field', 'No hidden field with id ' + id + '.');
    if (!patch || typeof patch !== 'object' || Array.isArray(patch)) throw fail('invalid_setting', 'setHiddenField() needs an object.');
    var next = clone(list[idx]);
    Object.keys(patch).forEach(function (k) {
      if (['value', 'preset', 'param', 'hdn_visitor_mail', 'name', 'prefill_key'].indexOf(k) === -1) throw fail('invalid_setting', 'key ' + k + ' is not a hidden-field key.');
    });
    if (patch.preset !== undefined) {
      if (!Object.prototype.hasOwnProperty.call(S.PRESETS, patch.preset)) throw fail('invalid_setting', 'unknown preset ' + patch.preset + '.');
      var tpl = S.PRESETS[patch.preset];
      if (tpl.indexOf('<name>') !== -1) {
        if (!/^[A-Za-z0-9_\-[\].]{1,64}$/.test(String(patch.param || ''))) throw fail('invalid_setting', 'preset ' + patch.preset + ' needs param (a parameter or cookie name).');
        tpl = tpl.replace('<name>', String(patch.param));
      }
      next.value = patch.preset === 'static' ? String(patch.value || '') : tpl;
    } else if (patch.value !== undefined) next.value = patch.value;
    if (patch.name !== undefined) next.name = String(patch.name);
    if (patch.hdn_visitor_mail !== undefined) { if (patch.hdn_visitor_mail === '' || patch.hdn_visitor_mail === false || patch.hdn_visitor_mail === 0) delete next.hdn_visitor_mail; else next.hdn_visitor_mail = patch.hdn_visitor_mail; }
    if (patch.prefill_key !== undefined) { if (patch.prefill_key === '' || patch.prefill_key === null) delete next.prefill_key; else next.prefill_key = patch.prefill_key; }
    var errs = smartCheck([next], idx, list, true);
    if (errs.length) throw failSmart(errs);
    list[idx] = next;
    persist();
    apiChanged('setHiddenField', [next.id_]);
    if (!opts || opts.refresh !== false) { try { refreshSettingsPanel(); } catch (e) {} }
    return { id: next.id_, value: next.value };
  }
  /** F08: the URL prefill key of a field: setPrefill('email', 'email', {lock: true}); setPrefill('email', '') removes it. */
  function setPrefill(id, key, o) {
    var S = window.EFBSmart;
    if (!S) throw fail('builder_not_ready', 'The smart-tag rules (smart-tags-efb.js) are not loaded.');
    var list = rows();
    var idx = fieldIndex(list, String(id));
    if (idx < 0) idx = list.findIndex(function (r, i) { return i > 0 && r && r.dataId === String(id); });
    if (idx < 1) throw fail('unknown_field', 'No field with id ' + id + '.');
    var next = clone(list[idx]);
    if (key === '' || key === null || key === undefined) { delete next.prefill_key; delete next.prefill_lock; }
    else {
      next.prefill_key = key;
      if (o && o.lock !== undefined) { if (o.lock) next.prefill_lock = '1'; else delete next.prefill_lock; }
    }
    var errs = smartCheck([next], idx, list, true);
    if (errs.length) throw failSmart(errs);
    list[idx] = next;
    persist();
    apiChanged('setPrefill', [next.id_]);
    return { id: next.id_, prefill_key: next.prefill_key || '', prefill_lock: next.prefill_lock || '' };
  }
  function failRules(errs) {
    var e = fail('pattern_invalid', 'This pattern cannot be used: ' + errs[0].reason + ' (' + errs[0].path + ')');
    e.path = errs[0].path; e.reason = errs[0].reason; e.pos = errs[0].pos;
    return e;
  }

  /**
   * F03: set the rules of one field (by id_ or dataId) with the server's save rules, e.g.
   * setFieldRules('email1', {unique: '1', email_domain_mode: 'allow', email_domains: 'example.com, *.edu'})
   * or setFieldRules('code1', {pattern: '[A-Z]{2}\\d{4}', msg_pattern: 'Like AB1234'}). "pattern" is the plain
   * regular expression (stored as pattern_b64); a refused pattern throws pattern_invalid (reason = lint
   * code, pos), a key the field type has no use for throws unsupported_key, any other bad key
   * invalid_setting. Nothing changes on a throw. Returns {changed, values}. Saving stays the owner's Save.
   */
  function setFieldRules(id, patch, opts) {
    var R = window.EfbRules;
    if (!R) throw fail('builder_not_ready', 'The field rule engine (field-rules-efb.js) is not loaded.');
    var list = rows();
    var idx = fieldIndex(list, String(id));
    if (idx < 0) idx = list.findIndex(function (r, i) { return i > 0 && r && r.dataId === String(id); });
    if (idx < 1 || isOptionRow(list[idx])) throw fail('unknown_field', 'No field with id ' + id + '.');
    if (!patch || typeof patch !== 'object' || Array.isArray(patch)) throw fail('invalid_setting', 'setFieldRules() needs an object of field-rule keys.');
    var f = list[idx];
    var values = {};
    Object.keys(patch).forEach(function (k) {
      var v = patch[k];
      if (v !== null && typeof v === 'object') throw fail('invalid_setting', k + ' must be a string, number or boolean.');
      if (k !== 'pattern' && R.KEYS.indexOf(k) === -1 && k !== 'milen' && k !== 'mlen') throw fail('invalid_setting', 'key ' + k + ' is not a field rule.');
      var part = FR_PART[k];
      if (part && !R.typeOk(f, part === 'counter' ? 'counter' : part)) throw fail('unsupported_key', k + ' does not apply to a ' + f.type + ' field.');
      if (k === 'pattern') {
        var src = String(v == null ? '' : v);
        var err = src === '' ? null : R.lintRegex(src);
        if (err) { var e = fail('pattern_invalid', 'This pattern cannot be used: ' + err.code); e.reason = err.code; e.pos = err.pos; e.path = 'pattern'; throw e; }
        values.pattern_b64 = src === '' ? '' : R.b64urlEncode(src);
        return;
      }
      if (k === 'pattern_b64' && v !== '' && v != null && R.sanitizeKey(k, v) === '') { var e2 = fail('pattern_invalid', 'pattern_b64 is not a usable base64url pattern.'); e2.path = 'pattern_b64'; throw e2; }
      values[k] = k === 'milen' || k === 'mlen' ? (/^\d{0,6}$/.test(String(v)) ? String(v) : (function () { throw fail('invalid_setting', k + ' must be a number.'); })()) : R.sanitizeKey(k, v);
    });
    Object.keys(values).forEach(function (k) { f[k] = values[k]; });
    if (values.readonly === '1' && Number(f.disabled) === 1) f.disabled = 0; // read-only and disabled exclude each other
    persist();
    apiChanged('setFieldRules', [f.id_]);
    if (!opts || opts.refresh !== false) { try { refreshSettingsPanel(); } catch (e) {} }
    return { changed: Object.keys(values), values: values };
  }

  /**
   * F02: one-time code keys of a mobile or e-mail field, written with the save rules (EFBVerify.sanitizeKey
   * = Verification::sanitize_key). setVerification('mobile1', {otp_enabled: 1, otp_countries: ['ir']}) or
   * setVerification('email1', {email_confirm_mode: 'retype_otp'}). Throws unsupported_key (a mobile key on
   * an e-mail field and the reverse), invalid_setting, unknown_field, plan (the plan does not include it),
   * efb_verify_unavailable (a code this site cannot send now: e.mode = 'sms' | 'email', e.reason). Turning a
   * code off on a saved form is a security change worth telling the owner. Returns {changed, values}.
   */
  var VRF_MOBILE = ['otp_enabled', 'otp_channel', 'otp_required_when', 'otp_countries', 'otp_btn_label'];
  var VRF_EMAIL = ['email_confirm_mode', 'email_retype_label', 'email_otp_required_when', 'email_otp_subject', 'email_otp_body'];
  function setVerification(id, patch, opts) {
    var Vf = window.EFBVerify;
    if (!Vf) throw fail('builder_not_ready', 'The verification rules (verify-efb.js) are not loaded.');
    var list = rows();
    var idx = fieldIndex(list, String(id));
    if (idx < 0) idx = list.findIndex(function (r, i) { return i > 0 && r && r.dataId === String(id); });
    if (idx < 1 || isOptionRow(list[idx])) throw fail('unknown_field', 'No field with id ' + id + '.');
    if (!patch || typeof patch !== 'object' || Array.isArray(patch)) throw fail('invalid_setting', 'setVerification() needs an object of verification keys.');
    var f = list[idx];
    var allowed = f.type === 'mobile' ? VRF_MOBILE : (f.type === 'email' ? VRF_EMAIL : []);
    var values = {};
    Object.keys(patch).forEach(function (k) {
      if (VRF_MOBILE.indexOf(k) === -1 && VRF_EMAIL.indexOf(k) === -1) throw fail('invalid_setting', 'key ' + k + ' is not a verification key.');
      if (allowed.indexOf(k) === -1) throw fail('unsupported_key', k + ' does not apply to a ' + f.type + ' field.');
      var v = patch[k];
      if (v !== null && typeof v === 'object' && !(k === 'otp_countries' && Array.isArray(v))) throw fail('invalid_setting', k + ' must be a string, number or boolean.');
      values[k] = Vf.sanitizeKey(k, v);
    });
    var after = Object.assign({}, f, values);
    var spec = Vf.fieldSpec(after);
    var vs = (window.efb_var && window.efb_var.verify) || {};
    var cap = (window.efb_var && window.efb_var.capabilities && window.efb_var.capabilities.features) || {};
    if (spec && spec.otp) {
      var key = spec.channel === 'sms' ? 'setting:verify_sms_otp' : 'setting:verify_email_otp';
      if (cap[key] && cap[key].reason === 'plan') { var ep = fail('plan', 'This plan does not include ' + (spec.channel === 'sms' ? 'SMS' : 'e-mail') + ' codes.'); ep.package = cap[key].package; throw ep; }
      var st = vs[spec.channel];
      if (st && st.ready === false) { var eu = fail('efb_verify_unavailable', 'Codes by ' + spec.channel + ' cannot be sent on this site now.'); eu.mode = spec.channel; eu.reason = st.reason; throw eu; }
    }
    if (values.email_otp_body && values.email_otp_body.indexOf('[otp_code]') === -1) throw fail('invalid_setting', 'email_otp_body must contain [otp_code].');
    Object.keys(values).forEach(function (k) { f[k] = values[k]; });
    if (values.otp_enabled === '1') { f.otp_channel = 'sms'; if (!f.otp_required_when) f.otp_required_when = 'filled'; }
    persist();
    apiChanged('setVerification', [f.id_]);
    if (!opts || opts.refresh !== false) { try { refreshSettingsPanel(); } catch (e) {} }
    return { changed: Object.keys(values), values: values };
  }

  /**
   * F11: the stored form's gate as the server sees it now (read only):
   * {form_id, state: open|pending|closed|full|limited|role, code, message, opens_at, closes_at,
   *  timezone, total: {limit, used, left}|null, person, roles, drafts, inactive: [reasons]}.
   */
  function formGate(opts) {
    opts = opts || {};
    var ev = window.efb_var || {};
    var cap = ev.capabilities && ev.capabilities.form_gate;
    var url = (cap && cap.rest) || ((ev.rest_url || '/wp-json/') + 'Emsfb/v1/forms/gate');
    var id = Number(opts.formId || (typeof form_ID_emsFormBuilder !== 'undefined' ? form_ID_emsFormBuilder : 0)) || 0;
    if (!id) return Promise.reject(fail('unknown_form', 'The form is not saved yet.'));
    url += (url.indexOf('?') === -1 ? '?' : '&') + 'form_id=' + id;
    return fetch(url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': ev.nonce || '' } }).then(function (res) {
      return res.json().then(function (j) {
        if (!res.ok) throw fail((j && j.code) || 'gate_failed', (j && j.message) || 'The form gate could not be read.');
        return j;
      });
    });
  }

  /* ---------------- F05 choices ---------------- */

  /**
   * F05: the choice keys of items with the save rules (EfbChoices.rules.sanitizeKey = Choices::sanitize_key): a value the
   * rules cannot keep throws choice_invalid (e.path); a stored value (opt_value) used twice in one field choice_value_dup;
   * dyn_src "users" publishes display names, so it needs opts.allow.dyn_users (choice_dyn_users). Rows are rewritten with
   * the stored values; ov / other / dyn / inv_mode are the server's and are dropped.
   */
  function choicesCheck(items, offset, all, opts) {
    var C = window.EfbChoices;
    var out = [];
    if (!C || !C.rules) return out;
    var R = C.rules;
    var keys = R.OPTION_KEYS.concat(R.PARENT_KEYS);
    (items || []).forEach(function (r, i) {
      if (!r || typeof r !== 'object') return;
      var path = 'rows[' + (i + (offset || 0)) + '].';
      ['ov', 'other', 'dyn', 'inv_mode'].forEach(function (k) { delete r[k]; });
      keys.forEach(function (k) {
        if (r[k] === undefined || r[k] === null || r[k] === '') return;
        var raw = r[k];
        var v = R.sanitizeKey(k, raw);
        var falsy = raw === false || raw === 0 || raw === '0';
        if (v === '' && !falsy) { out.push({ code: 'choice_invalid', path: path + k, key: k, field: r.id_ }); return; }
        if (v === '' && k !== 'rank_touch') delete r[k]; else r[k] = v;
      });
      if (r.dyn_src === 'users' && !(opts && opts.allow && opts.allow.dyn_users)) out.push({ code: 'choice_dyn_users', path: path + 'dyn_src', key: 'dyn_src', field: r.id_ });
    });
    var seen = {};
    (all || []).forEach(function (r) {
      if (!r || r.type !== 'option' || typeof r.opt_value !== 'string' || r.opt_value === '') return;
      var k = String(r.parent) + '\u0000' + r.opt_value;
      if (seen[k] && seen[k] !== r.id_) {
        var i = (items || []).indexOf(r);
        out.push({ code: 'choice_value_dup', path: 'rows[' + (i === -1 ? '?' : i + (offset || 0)) + '].opt_value', key: 'opt_value', field: r.parent, value: r.opt_value });
      } else seen[k] = r.id_;
    });
    return out;
  }
  function failChoices(errs) {
    var e0 = errs[0];
    var e = fail(e0.code, e0.code === 'choice_value_dup' ? 'Two choices of ' + e0.field + ' have the stored value ' + e0.value + ' (' + e0.path + ')' : (e0.code === 'choice_dyn_users' ? 'Choices from users publish display names; pass {allow: {dyn_users: true}} (' + e0.path + ')' : 'This value cannot be used for ' + e0.key + ' (' + e0.path + ')'));
    e.path = e0.path; e.errors = errs;
    return e;
  }
  function choiceField(id) {
    var list = rows();
    var idx = fieldIndex(list, String(id));
    var C = window.EfbChoices;
    if (!C) throw fail('builder_not_ready', 'The choice rules (choices-efb.js) are not loaded.');
    var T = C.rules.TYPES;
    var ok = idx > 0 && Object.keys(T).some(function (p) { return T[p].indexOf(list[idx].type) !== -1; });
    if (!ok) throw fail('unknown_field', 'No choice field with id ' + id + '.');
    return { list: list, idx: idx, row: list[idx], C: C };
  }
  /**
   * F05: the choices of one field from lines ("Label", "Label|value", "\|" = "|") or [{label, value}], in one call:
   * setOptions('size', 'Small|s\nLarge|l', {mode: 'append'|'replace' (default)}). Throws choice_value_dup (a stored value
   * twice), choice_too_many (over 500), invalid_items (nothing to add). Returns {added, ids, stripped, dupLabels}.
   */
  function setOptions(id, lines, opts) {
    opts = opts || {};
    var f = choiceField(id);
    var text = Array.isArray(lines) ? lines.map(function (l) { return typeof l === 'string' ? l : (l && l.label !== undefined ? String(l.label).replace(/\|/g, '\\|') + (l.value !== undefined && l.value !== '' ? '|' + String(l.value).replace(/\|/g, '\\|') : '') : ''); }).join('\n') : String(lines == null ? '' : lines);
    var mode = opts.mode === 'append' ? 'append' : 'replace';
    var existing = mode === 'append' ? f.list.filter(function (r) { return r && r.type === 'option' && r.parent === f.row.id_; }).map(function (o) { return { label: o.value, value: o.opt_value || '' }; }) : [];
    var res = f.C.parseBulk(text, { existing: existing, calc: !!(window.efb_var && window.efb_var.choices && window.efb_var.choices.calc) });
    if (res.tooMany) throw fail('choice_too_many', 'At most ' + f.C.rules.MAX_BULK + ' choices are added at once.');
    if (res.dupValues.length) { var e = fail('choice_value_dup', 'These stored values are used twice: ' + res.dupValues.join(', ')); e.values = res.dupValues; throw e; }
    if (!res.items.length) throw fail('invalid_items', 'No choice in the lines.');
    var list = f.list;
    if (mode === 'replace') {
      for (var i = list.length - 1; i > 0; i--) if (list[i] && list[i].type === 'option' && list[i].parent === f.row.id_) list.splice(i, 1);
    }
    var at = fieldEnd(list, fieldIndex(list, f.row.id_)) + 1;
    var pay = /pay/.test(f.row.type);
    var ids = [];
    var add = res.items.map(function (it) {
      var oid = randomId();
      ids.push(oid);
      var o = { id_: oid, dataId: oid + '-id', parent: f.row.id_, type: 'option', value: it.label, id_op: oid, step: f.row.step, amount: f.row.amount };
      if (pay) o.price = 0;
      if (it.value !== undefined) o.opt_value = it.value;
      if (it.calc !== undefined) o.calc = it.calc;
      return o;
    });
    Array.prototype.splice.apply(list, [at, 0].concat(add));
    renumber(list);
    persist();
    apiChanged('setOptions', [f.row.id_]);
    if (opts.refresh !== false) { try { refreshSettingsPanel(); } catch (x) {} }
    return { added: add.length, ids: ids, stripped: res.stripped, dupLabels: res.dupLabels };
  }
  /**
   * F05: the choice keys of one field: setChoices('size', {op_random: true, op_other: true, maxSelect: 2, inv_on: true,
   * dyn_src: 'posts', rank_top: 3}). A key that is not an F05 field key throws invalid_setting; the codes of choicesCheck.
   * Returns {changed, values, plan} (plan = keys stored but off on this plan: inventory and dynamic sources are Pro).
   */
  function setChoices(id, patch, opts) {
    var f = choiceField(id);
    if (!patch || typeof patch !== 'object' || Array.isArray(patch)) throw fail('invalid_setting', 'setChoices() needs an object of choice keys.');
    var R = f.C.rules;
    Object.keys(patch).forEach(function (k) { if (R.PARENT_KEYS.indexOf(k) === -1) throw fail('invalid_setting', 'key ' + k + ' is not a choice-field key.'); });
    var next = clone(f.row);
    Object.keys(patch).forEach(function (k) { next[k] = patch[k] === true ? '1' : (patch[k] === false ? (k === 'rank_touch' ? '0' : '') : patch[k]); if (next[k] === '') delete next[k]; });
    var errs = choicesCheck([next], f.idx, null, opts);
    if (errs.length) throw failChoices(errs);
    f.list[f.idx] = next;
    persist();
    apiChanged('setChoices', [next.id_]);
    if (!opts || opts.refresh !== false) { try { refreshSettingsPanel(); } catch (x) {} }
    var values = {};
    Object.keys(patch).forEach(function (k) { values[k] = next[k] === undefined ? '' : next[k]; });
    var cap = (window.efb_var && window.efb_var.capabilities && window.efb_var.capabilities.features) || {};
    var gated = [];
    if (next.inv_on === '1' && cap['setting:choice_inventory'] && cap['setting:choice_inventory'].available_now === false) gated.push('inv_on');
    if (next.dyn_src && cap['setting:choice_dynamic'] && cap['setting:choice_dynamic'].available_now === false) gated.push('dyn_src');
    return { changed: Object.keys(values), values: values, plan: gated };
  }

  /* ---------------- F04 numbers and calculations ---------------- */

  /**
   * F04: the number / calc / slider keys of items with the save rules (EFBCalc.sanitizeKey = Calc::sanitize_key):
   * a value the rules cannot keep throws calc_invalid (e.path); formula_err is the server's and dropped; every
   * calc formula of the whole form must parse and read fields that exist (calc_formula with e.formula_code =
   * calcSyntax / calcRef / calcForbidden / calcCycle / …, the same codes as the server); a calc used as the
   * amount to pay needs opts.allow.payment (calc_payment). "step" is never a number setting (it is the form step).
   */
  function calcCheck(items, offset, all, opts) {
    var E = window.EFBCalc;
    var out = [];
    if (!E) return out;
    (items || []).forEach(function (r, i) {
      if (!r || typeof r !== 'object') return;
      var path = 'rows[' + (i + (offset || 0)) + '].';
      delete r.formula_err;
      Object.keys(r).forEach(function (k) {
        if (!E.isKey(k, r.type) || r[k] === undefined || r[k] === null || r[k] === '') return;
        var v = E.sanitizeKey(k, r[k], r.type);
        if (v === '' && k === 'formula') { var pf = E.parse(String(r[k])); out.push({ code: 'calc_formula', path: path + k, key: k, field: r.id_, formula_code: pf.ok ? 'calcSyntax' : pf.code, near: pf.ok ? '' : pf.near }); return; }
        if (v === '' && r[k] !== false && r[k] !== 0 && r[k] !== '0') { out.push({ code: 'calc_invalid', path: path + k, key: k, field: r.id_ }); return; }
        if (v === '' && k !== 'formula') delete r[k]; else r[k] = v;
      });
      if (r.type === 'calc' && r.calc_as_price === '1' && !(opts && opts.allow && opts.allow.payment)) out.push({ code: 'calc_payment', path: path + 'calc_as_price', key: 'calc_as_price', field: r.id_ });
    });
    var full = [{ type: 'form' }].concat((all || []).filter(function (r) { return r && r.type !== 'form'; }));
    var errs = E.formErrors(full);
    (items || []).forEach(function (r, i) {
      if (r && r.type === 'calc' && errs[r.id_] && !out.some(function (x) { return x.field === r.id_ && x.key === 'formula'; })) out.push({ code: 'calc_formula', path: 'rows[' + (i + (offset || 0)) + '].formula', key: 'formula', field: r.id_, formula_code: errs[r.id_].code === 'calcSelf' ? 'calcCycle' : errs[r.id_].code, near: errs[r.id_].near });
    });
    return out;
  }
  function failCalc(errs) {
    var e0 = errs[0];
    var e = fail(e0.code, e0.code === 'calc_formula' ? 'The formula of ' + e0.field + ' has a problem: ' + e0.formula_code + (e0.near ? ' near ' + e0.near : '') + ' (' + e0.path + ')'
      : (e0.code === 'calc_payment' ? 'A calculation used as the amount to pay needs {allow: {payment: true}} (' + e0.path + ')' : 'This value cannot be used for ' + e0.key + ' (' + e0.path + ')'));
    e.path = e0.path; e.errors = errs;
    if (e0.formula_code) e.formula_code = e0.formula_code;
    return e;
  }
  /**
   * validateFormula('({price}+50)*{qty}', rows?) → {ok, code, pos, near, refs}: the same answer as the server's
   * check on save (Calc::form_errors) and the AutoPilot validator, against the builder's form (or rows).
   */
  function validateFormula(formula, list) {
    var E = window.EFBCalc;
    if (!E) throw fail('builder_not_ready', 'The calc engine (calc-efb.js) is not loaded.');
    var p = E.parse(String(formula == null ? '' : formula));
    if (!p.ok) return { ok: false, code: p.code, pos: p.pos, near: p.near, refs: [] };
    var base = clone(Array.isArray(list) ? list : rows());
    if (!base.length || !base[0] || base[0].type === undefined || base[0].id_ !== undefined) base.unshift({ type: 'form' });
    base.push({ id_: '__efb_formula_check__', type: 'calc', formula: String(formula) });
    var e = E.formErrors(base)['__efb_formula_check__'];
    return e ? { ok: false, code: e.code === 'calcSelf' ? 'calcCycle' : e.code, pos: e.pos, near: e.near, refs: E.refIds(p) } : { ok: true, code: '', pos: -1, near: '', refs: E.refIds(p) };
  }
  /** F04: the number / calc / slider keys of one field: setCalc('total', {formula: '{a}*2', num_decimals: '2'}). */
  function setCalc(id, patch, opts) {
    var list = rows();
    var idx = fieldIndex(list, String(id));
    if (idx < 1 || ['number', 'range', 'calc', 'option'].indexOf(list[idx].type) === -1) throw fail('unknown_field', 'No number, slider, calc field or choice with id ' + id + '.');
    if (!patch || typeof patch !== 'object' || Array.isArray(patch)) throw fail('invalid_setting', 'setCalc() needs an object of number / calc keys.');
    var E = window.EFBCalc;
    if (!E) throw fail('builder_not_ready', 'The calc engine (calc-efb.js) is not loaded.');
    Object.keys(patch).forEach(function (k) {
      if (k === 'step') throw fail('invalid_setting', 'the step of a number is num_step; "step" is the form step.');
      if (!E.isKey(k, list[idx].type) && k !== 'milen' && k !== 'mlen') throw fail('invalid_setting', 'key ' + k + ' is not a number / calc key of a ' + list[idx].type + '.');
    });
    var row = clone(list[idx]);
    Object.keys(patch).forEach(function (k) { row[k] = patch[k]; });
    if (patch.milen !== undefined || patch.mlen !== undefined) ['milen', 'mlen'].forEach(function (k) { if (patch[k] !== undefined && patch[k] !== '' && !/^-?\d{1,15}(\.\d{1,6})?$/.test(E.normalizeDigits(String(patch[k])).replace(/\u066B/g, '.'))) throw fail('calc_invalid', k + ' must be a number.'); if (patch[k] !== undefined) row[k] = E.normalizeDigits(String(patch[k])).replace(/\u066B/g, '.'); });
    var all = list.slice(1).map(function (r, i) { return i + 1 === idx ? row : r; });
    var errs = calcCheck([row], idx, all, opts);
    if (errs.length) throw failCalc(errs);
    var values = {};
    Object.keys(patch).forEach(function (k) { values[k] = row[k] === undefined ? '' : row[k]; });
    list[idx] = row;
    apiChanged('setCalc', [row.id_]);
    var cap = (window.efb_var && efb_var.capabilities && efb_var.capabilities.features) || {};
    var gated = [];
    if (row.type === 'calc' && cap['field:calc'] && cap['field:calc'].available_now === false) gated.push('calc');
    if (row.calc !== undefined && row.type === 'option' && cap['setting:calc_option'] && cap['setting:calc_option'].available_now === false) gated.push('calc');
    if (row.calc_as_price === '1' && cap['setting:calc_price'] && cap['setting:calc_price'].available_now === false) gated.push('calc_as_price');
    return { changed: Object.keys(values), values: values, plan: gated };
  }

  /* ------------------------------------------------------------ F09 repeater groups */

  function rptCore() { return window.EFBRepeaterCore || null; }
  function rptCfg() { return (window.efb_var && efb_var.repeater) || {}; }
  function rptCap(k, d) { var c = rptCfg().caps || {}; return Number(c[k]) > 0 ? Number(c[k]) : d; }
  function rptChildTypes() {
    var C = rptCore();
    var t = (typeof EFB_REPEATER_CHILD_TYPES !== 'undefined' && Array.isArray(EFB_REPEATER_CHILD_TYPES)) ? EFB_REPEATER_CHILD_TYPES : (rptCfg().child_types || (C ? C.CHILD_TYPES : []));
    return t.filter(function (x) { return x !== 'repeater'; });
  }
  /**
   * The rules of a form's groups (twin of what Save keeps, Repeater::normalize_rows() / sanitize_key()): every
   * group key valid; `group` names a repeater group of the form; a child's type may go into a row; no group in a
   * group; at most 10 groups and 25 fields a group. Errors {code, path, key, field}.
   */
  function rptCheck(items, offset, all) {
    var C = rptCore(), out = [];
    if (!C) return out;
    var groups = {};
    (all || []).forEach(function (r) { if (r && r.type === 'repeater' && r.id_ !== undefined) groups[String(r.id_)] = r; });
    var allowed = rptChildTypes();
    (items || []).forEach(function (r, i) {
      if (!r || typeof r !== 'object') return;
      var path = 'rows[' + (i + (offset || 0)) + '].';
      C.RUNTIME_KEYS.forEach(function (k) { if (r[k] !== undefined) out.push({ code: 'repeater_invalid', path: path + k, key: k, field: r.id_ }); });
      if (r.type === 'repeater') {
        C.KEYS.forEach(function (k) {
          if (r[k] === undefined || r[k] === null || r[k] === '') return;
          var v = C.sanitizeKey(k, r[k], { rows: rptCap('rows', 50) });
          if (v === '' || ((k === 'min_rows' || k === 'max_rows' || k === 'default_rows') && String(C.asciiDigits(String(r[k]))).trim() !== v)) out.push({ code: 'repeater_invalid', path: path + k, key: k, field: r.id_ });
          else r[k] = v;
        });
        if (r.max_rows !== undefined && r.max_rows !== '' && Number(r.max_rows) < 1) out.push({ code: 'repeater_invalid', path: path + 'max_rows', key: 'max_rows', field: r.id_ });
        var mx = Number(r.max_rows || 10), mn = Number(r.min_rows === undefined || r.min_rows === '' ? 1 : r.min_rows), df = Number(r.default_rows === undefined || r.default_rows === '' ? 1 : r.default_rows);
        if (mn > mx) out.push({ code: 'repeater_invalid', path: path + 'min_rows', key: 'min_rows', field: r.id_ });
        else if (df < mn || df > mx) out.push({ code: 'repeater_invalid', path: path + 'default_rows', key: 'default_rows', field: r.id_ });
        if (r.group !== undefined && r.group !== '') out.push({ code: 'repeater_nested', path: path + 'group', key: 'group', field: r.id_ });
        return;
      }
      if (r.group === undefined || r.group === '' || r.type === 'option' || r.type === 'r_matrix') return;
      if (r.type === 'step') { out.push({ code: 'repeater_child_type', path: path + 'group', key: 'group', field: r.id_ }); return; }
      if (!groups[String(r.group)]) { out.push({ code: 'repeater_group', path: path + 'group', key: 'group', field: r.id_ }); return; }
      if (allowed.indexOf(r.type) === -1) out.push({ code: 'repeater_child_type', path: path + 'group', key: 'group', field: r.id_ });
    });
    var nG = Object.keys(groups).length;
    if (nG > rptCap('groups', 10)) out.push({ code: 'repeater_cap', path: 'rows', key: 'groups', field: '' });
    Object.keys(groups).forEach(function (g) {
      var n = (all || []).filter(function (r) { return r && r.group === g && r.type !== 'option' && r.type !== 'r_matrix'; }).length;
      if (n > rptCap('children', 25)) out.push({ code: 'repeater_cap', path: 'rows', key: 'children', field: g });
    });
    return out;
  }
  function failRpt(errs) {
    var e0 = errs[0];
    var msg = {
      repeater_invalid: 'This value cannot be used for ' + e0.key,
      repeater_nested: 'A repeater group cannot go inside another group',
      repeater_group: 'group must be the id of a repeater group of this form',
      repeater_child_type: 'This field type cannot go into a repeater row',
      repeater_cap: e0.key === 'groups' ? 'A form holds at most ' + rptCap('groups', 10) + ' repeater groups' : 'A repeater group holds at most ' + rptCap('children', 25) + ' fields',
    }[e0.code] || e0.code;
    var e = fail(e0.code, msg + ' (' + e0.path + ')');
    e.path = e0.path; e.errors = errs;
    return e;
  }
  /** Children (and their options) right after their group, in their order, with the group's step (as Save does). */
  function rptPlace(list) {
    var groups = {};
    list.forEach(function (r, i) { if (i > 0 && r && r.type === 'repeater') groups[String(r.id_)] = r; });
    if (!Object.keys(groups).length) return list;
    var isKid = function (r) { return r && r.type !== 'option' && r.type !== 'r_matrix' && r.group !== undefined && groups[String(r.group)]; };
    var optsOf = {};
    list.forEach(function (r) { if (r && (r.type === 'option' || r.type === 'r_matrix') && r.parent !== undefined) (optsOf[String(r.parent)] = optsOf[String(r.parent)] || []).push(r); });
    var kidsOf = {}, taken = {};
    list.forEach(function (r) {
      if (!isKid(r)) return;
      r.step = groups[String(r.group)].step;
      (kidsOf[String(r.group)] = kidsOf[String(r.group)] || []).push(r);
      taken[String(r.id_)] = true;
      (optsOf[String(r.id_)] || []).forEach(function (o) { taken['o:' + String(o.id_)] = true; });
    });
    var out = [];
    list.forEach(function (r, i) {
      if (i > 0 && r && (taken[String(r.id_)] && isKid(r) || ((r.type === 'option' || r.type === 'r_matrix') && taken['o:' + String(r.id_)]))) return;
      out.push(r);
      if (i > 0 && r && r.type === 'repeater') {
        (kidsOf[String(r.id_)] || []).forEach(function (k) { out.push(k); (optsOf[String(k.id_)] || []).forEach(function (o) { out.push(o); }); });
      }
    });
    list.length = 0;
    Array.prototype.push.apply(list, out);
    return list;
  }

  /**
   * A new repeater group: settings (name, min_rows, max_rows, default_rows, rows_from, row_label, collapsible,
   * collapse_prev, rpt_layout, btn_add_text, rmv_confirm), placed like insertItems (opts.after / before / step);
   * opts.fields: ids of fields to move into it, in that order. Returns {id, ids}.
   */
  function addGroup(settings, opts) {
    opts = opts || {};
    settings = settings || {};
    var C = rptCore();
    if (!C) throw fail('builder_not_ready', 'The repeater runtime (repeater-efb.js) is not loaded.');
    var list = rows();
    var id = settings.id_ ? String(settings.id_) : randomId();
    var row = { id_: id, dataId: id + '-id', type: 'repeater', elementId: 'repeater', name: settings.name ? String(settings.name) : 'Repeater group', step: 1, required: 0,
      message: '', classes: '', size: 100, label_text_size: 'fs-6', label_position: 'up', el_text_size: 'fs-6', label_text_color: 'text-labelEfb', el_border_color: 'border-d',
      el_text_color: 'text-labelEfb', message_text_color: 'text-muted', el_height: 'h-d-efb', label_align: (window.efb_var && efb_var.rtl == 1) ? 'txt-right' : 'txt-left',
      message_align: 'justify-content-start', el_align: 'justify-content-start' };
    Object.keys(C.DEFAULTS).forEach(function (k) { row[k] = C.DEFAULTS[k]; });
    Object.keys(settings).forEach(function (k) { if (k !== 'id_' && k !== 'fields' && k !== 'type') row[k] = settings[k]; });
    if (opts.step !== undefined) row.step = Number(opts.step);
    var errs = rptCheck([row], 0, list.concat([row]));
    if (errs.length) throw failRpt(errs);
    // The fields to move in are checked first: nothing changes when one of them cannot go into a row.
    if (Array.isArray(opts.fields) && opts.fields.length) {
      var trial = opts.fields.map(function (fid, n) {
        var f = list.filter(function (x, i) { return i > 0 && x && x.id_ === String(fid) && x.type !== 'option' && x.type !== 'r_matrix'; })[0];
        if (!f) throw fail('unknown_target', 'No field with id ' + fid + '.');
        var t = clone(f); t.group = id; return t;
      });
      var fErrs = rptCheck(trial, 0, list.concat([row]));
      if (fErrs.length) throw failRpt(fErrs.map(function (e) { e.path = e.path.replace(/^rows\[(\d+)\]/, function (m, n) { return 'fields[' + n + ']'; }); return e; }));
    }
    var res = insertItems([row], { after: opts.after, before: opts.before, step: opts.step });
    // The group's step is the step it was put in (the row before it is a step or a field of that step).
    var L2 = rows(), gi = fieldIndex(L2, id);
    if (gi > 1 && opts.step === undefined && L2[gi - 1] && L2[gi - 1].step !== undefined) L2[gi].step = L2[gi - 1].step;
    var moved = [];
    if (Array.isArray(opts.fields) && opts.fields.length) moved = moveIntoGroup(opts.fields, id, { render: false }).ids;
    apiChanged('addGroup', [id].concat(moved));
    if (opts.render === true) return render(opts).then(function () { return { id: id, ids: moved, inserted: res.ids }; });
    return { id: id, ids: moved };
  }

  /** Fields into a group (after its other fields, in the order given); their options follow them. Returns {ids}. */
  function moveIntoGroup(ids, gid, opts) {
    opts = opts || {};
    var list = rows();
    var g = list.filter(function (r, i) { return i > 0 && r && r.id_ === gid && r.type === 'repeater'; })[0];
    if (!g) throw fail('unknown_target', 'No repeater group with id ' + gid + '.');
    var want = (Array.isArray(ids) ? ids : [ids]).map(String);
    var rowsTo = want.map(function (id) {
      var r = list.filter(function (x, i) { return i > 0 && x && x.id_ === id && x.type !== 'option' && x.type !== 'r_matrix'; })[0];
      if (!r) throw fail('unknown_target', 'No field with id ' + id + '.');
      return r;
    });
    var trial = rowsTo.map(function (r) { var c = clone(r); c.group = gid; return c; });
    var errs = rptCheck(trial, 0, list.map(function (r) { var t = trial.filter(function (x) { return r && x.id_ === r.id_; })[0]; return t || r; }));
    if (errs.length) throw failRpt(errs.map(function (e) { e.path = e.path.replace(/^rows\[(\d+)\]/, function (m, n) { return 'ids[' + n + ']'; }); return e; }));
    // Moved to the end of the group's fields, in the order given.
    rowsTo.forEach(function (r) { r.group = gid; r.step = g.step; });
    var at = list.indexOf(g);
    var block = [];
    rowsTo.forEach(function (r) {
      block.push(r);
      list.forEach(function (o) { if (o && (o.type === 'option' || o.type === 'r_matrix') && o.parent === r.id_) block.push(o); });
    });
    var rest = list.filter(function (r) { return block.indexOf(r) === -1; });
    var gi = rest.indexOf(g), end = gi + 1;
    while (end < rest.length && rest[end] && ((rest[end].group === gid && rest[end].type !== 'option') || ((rest[end].type === 'option' || rest[end].type === 'r_matrix') && rest.some(function (k) { return k && k.group === gid && k.id_ === rest[end].parent; })))) end += 1;
    Array.prototype.splice.apply(rest, [end, 0].concat(block));
    list.length = 0;
    Array.prototype.push.apply(list, rest);
    rptPlace(list);
    renumber(list);
    apiChanged('moveIntoGroup', want);
    if (opts.render === true) return render(opts).then(function () { return { ids: want }; });
    return { ids: want, at: at };
  }

  /** Fields out of their group: they become ordinary fields right after the group's block. Returns {ids}. */
  function moveOutOfGroup(ids, opts) {
    opts = opts || {};
    var list = rows();
    var want = (Array.isArray(ids) ? ids : [ids]).map(String);
    want.forEach(function (id) {
      var r = list.filter(function (x, i) { return i > 0 && x && x.id_ === id && x.type !== 'option'; })[0];
      if (!r) throw fail('unknown_target', 'No field with id ' + id + '.');
      if (r.group === undefined || r.group === '') throw fail('not_in_group', 'The field ' + id + ' is not in a repeater group.');
    });
    want.forEach(function (id) {
      var r = list.filter(function (x, i) { return i > 0 && x && x.id_ === id && x.type !== 'option'; })[0];
      var gid = r.group;
      delete r.group;
      var block = [r].concat(list.filter(function (o) { return o && (o.type === 'option' || o.type === 'r_matrix') && o.parent === r.id_; }));
      var rest = list.filter(function (x) { return block.indexOf(x) === -1; });
      var gi = -1;
      rest.forEach(function (x, i) { if (x && x.id_ === gid && x.type === 'repeater') gi = i; });
      var end = gi + 1;
      while (gi > 0 && end < rest.length && rest[end] && (rest[end].group === gid || ((rest[end].type === 'option' || rest[end].type === 'r_matrix') && rest.some(function (k) { return k && k.group === gid && k.id_ === rest[end].parent; })))) end += 1;
      Array.prototype.splice.apply(rest, [gi > 0 ? end : rest.length, 0].concat(block));
      list.length = 0;
      Array.prototype.push.apply(list, rest);
    });
    renumber(list);
    apiChanged('moveOutOfGroup', want);
    if (opts.render === true) return render(opts).then(function () { return { ids: want }; });
    return { ids: want };
  }

  /** A group's settings (the keys of addGroup, and name). Returns {changed, values, plan}. */
  function setGroup(gid, patch) {
    var list = rows();
    var idx = fieldIndex(list, String(gid));
    if (idx < 1 || list[idx].type !== 'repeater') throw fail('unknown_target', 'No repeater group with id ' + gid + '.');
    if (!patch || typeof patch !== 'object') throw fail('invalid_setting', 'setGroup() needs an object of settings.');
    var C = rptCore();
    Object.keys(patch).forEach(function (k) { if (k !== 'name' && (!C || C.KEYS.indexOf(k) === -1)) throw fail('invalid_setting', 'key ' + k + ' is not a repeater group setting.'); });
    var row = clone(list[idx]);
    Object.keys(patch).forEach(function (k) { row[k] = patch[k]; });
    var errs = rptCheck([row], idx, list.map(function (r, i) { return i === idx ? row : r; }));
    if (errs.length) throw failRpt(errs);
    var values = {};
    Object.keys(patch).forEach(function (k) { values[k] = row[k]; });
    list[idx] = row;
    apiChanged('setGroup', [row.id_]);
    var gated = [];
    var plan = rptCfg().plan || {};
    if (plan.repeat === false && ['max_rows', 'default_rows', 'rows_from', 'collapsible', 'rpt_layout'].some(function (k) { return patch[k] !== undefined; })) gated.push('repeat');
    return { changed: Object.keys(values), values: values, plan: gated };
  }

  /* ------------------------------------------------------------ F10 layout and special fields */

  function layCore() { return window.EFBLayoutCore || null; }
  var LAY_FLAGS = [true, false, 1, 0, '1', '0', 'true', 'false', ''];
  var LAY_FLAG_KEYS = ['review_before_submit', 'review_hide_empty', 'section_accordion', 'collapsed', 'rich_text'];
  var LAY_NO_VIS = ['step', 'option', 'r_matrix', 'form'];
  var LAY_FIXED_IDS = ['emaillogin', 'passwordlogin', 'usernameRegisterEFB', 'passwordRegisterEFB', 'repasswordRegisterEFB', 'emailRegisterEFB'];
  /**
   * The F10 keys of rows written by the API, with the save rules (EFBLayoutCore.sanitizeKey = Layout::sanitize_key)
   * and the rules between keys (Layout::normalize_rows): a flag is one of true / false / 1 / 0; the review title is
   * text; rich_text / rich_toolbar only on a textarea, esign_mode / esign_font only on an e-signature, vis_mode /
   * vis_roles on a field (not a step, an option or a login / register row), vis_roles = roles of this site
   * (efb_var.roles) with vis_mode "roles"; a section never in a repeater group; no review page on a form with a
   * Stripe / PayPal / PersiaPay field; rich text below Free Plus and a visibility rule below Pro are plan_required.
   * vis_view / vis_empty are the page's own marks. Values are written normalized. Errors {code, path, key, field}.
   */
  function layoutCheck(items, offset, all, row0) {
    var C = layCore(), out = [];
    if (!C) return out;
    var cap = (window.efb_var && efb_var.capabilities && efb_var.capabilities.features) || {};
    var roles = (window.efb_var && Array.isArray(efb_var.roles)) ? efb_var.roles.map(function (x) { return String(x && x.slug); }) : null;
    var err = function (code, path, key, field) { out.push({ code: code, path: path + key, key: key, field: field === undefined ? '' : field }); };
    var flag = function (r, k, path, id) {
      if (r[k] === undefined) return;
      if (LAY_FLAGS.indexOf(r[k]) === -1) { err('layout_invalid', path, k, id); return; }
      r[k] = C.sanitizeKey(k, r[k]);
    };
    var checkRow0 = function (r, path) {
      C.RUNTIME_KEYS.forEach(function (k) { if (r[k] !== undefined) err('layout_invalid', path, k); });
      ['review_before_submit', 'review_hide_empty', 'section_accordion'].forEach(function (k) { flag(r, k, path); });
      if (r.review_title !== undefined) {
        if (r.review_title !== null && typeof r.review_title === 'object') err('layout_invalid', path, 'review_title');
        else r.review_title = C.sanitizeKey('review_title', r.review_title);
      }
      if (r.review_before_submit === '1' && C.legacyPayment([r].concat(all || []))) err('layout_review_payment', path, 'review_before_submit');
    };
    if (row0) checkRow0(row0, 'rows[0].');
    (items || []).forEach(function (r, i) {
      if (!r || typeof r !== 'object') return;
      var path = 'rows[' + (i + (offset || 0)) + '].';
      var t = String(r.type || ''), id = r.id_;
      C.RUNTIME_KEYS.forEach(function (k) { if (r[k] !== undefined) err('layout_invalid', path, k, id); });
      if (t === 'section') {
        if (r.group !== undefined && r.group !== '') err('layout_section_group', path, 'group', id);
        flag(r, 'collapsed', path, id);
        if (r.collapsible !== undefined) { if (LAY_FLAGS.indexOf(r.collapsible) === -1) err('layout_invalid', path, 'collapsible', id); else r.collapsible = C.on(r.collapsible) ? '1' : '0'; }
      } else if (r.collapsed !== undefined) err('layout_invalid', path, 'collapsed', id);
      if (r.rich_text !== undefined || r.rich_toolbar !== undefined) {
        if (t !== 'textarea') { err('layout_invalid', path, r.rich_text !== undefined ? 'rich_text' : 'rich_toolbar', id); }
        else {
          flag(r, 'rich_text', path, id);
          if (r.rich_toolbar !== undefined) { if (['basic', 'full'].indexOf(r.rich_toolbar) === -1) err('layout_invalid', path, 'rich_toolbar', id); }
          if (r.rich_text === '1' && cap['setting:rich_text'] && cap['setting:rich_text'].available_now === false) err('plan_required', path, 'rich_text', id);
        }
      }
      if (r.esign_mode !== undefined || r.esign_font !== undefined) {
        if (t !== 'esign') err('layout_invalid', path, r.esign_mode !== undefined ? 'esign_mode' : 'esign_font', id);
        else {
          if (r.esign_mode !== undefined && C.SIG_MODES.indexOf(r.esign_mode) === -1) err('layout_invalid', path, 'esign_mode', id);
          if (r.esign_font !== undefined && C.SIG_FONTS.indexOf(r.esign_font) === -1) err('layout_invalid', path, 'esign_font', id);
        }
      }
      if (r.vis_mode !== undefined || r.vis_roles !== undefined) {
        if (LAY_NO_VIS.indexOf(t) !== -1 || LAY_FIXED_IDS.indexOf(String(id)) !== -1) { err('layout_invalid', path, r.vis_mode !== undefined ? 'vis_mode' : 'vis_roles', id); return; }
        var m = r.vis_mode === undefined ? 'all' : r.vis_mode;
        if (C.VIS_MODES.indexOf(m) === -1) { err('layout_invalid', path, 'vis_mode', id); return; }
        if (r.vis_roles !== undefined) {
          if (m !== 'roles' || (r.vis_roles !== null && typeof r.vis_roles === 'object')) { err('layout_invalid', path, 'vis_roles', id); return; }
          var want = String(r.vis_roles).split(',').map(function (x) { return x.trim(); }).filter(Boolean);
          var bad = roles ? want.filter(function (x) { return roles.indexOf(x) === -1; }) : [];
          if (bad.length) { err('layout_vis_role', path, 'vis_roles', id); return; }
          r.vis_roles = C.sanitizeKey('vis_roles', r.vis_roles);
        } else if (m === 'roles') r.vis_roles = '';
        if (m !== 'all' && cap['setting:vis_mode'] && cap['setting:vis_mode'].available_now === false) err('plan_required', path, 'vis_mode', id);
      }
    });
    return out;
  }
  /* ------------------------------------------------------------ F12 anti-spam keys of row 0 */

  var SPAM_FORM_KEYS = ['captcha_provider', 'rc3_threshold', 'spam_off', 'spam_akismet'];
  var SPAM_SECRETS = ['secretKey', 'rc3SecretKey', 'hcSecretKey', 'tsSecretKey'];
  var SPAM_SITE_ONLY = ['siteKey', 'rc3SiteKey', 'hcSiteKey', 'tsSiteKey', 'rc3Threshold', 'rc3_low_action', 'spam_honeypot', 'spam_min_time', 'spam_require_js', 'spam_action', 'spam_disallowed_keys', 'spam_retention_days'];
  /**
   * The F12 keys of row 0 with the save rules (EFBSpamCore.sanitizeKey = Spam::sanitize_form_key()): captcha_provider
   * one of the four services ('' = the site's), rc3_threshold a score 0.0 … 1.0 (Persian / Arabic-Indic digits
   * accepted), spam_off and spam_akismet flags. A secret key (spam_secret) or a site setting (spam_site_only) is never
   * a form key: an assistant cannot set them. Values are written normalized ('' = not set). Errors {code, path, key}.
   */
  function spamCheck(r0, path) {
    var out = [];
    if (!r0 || typeof r0 !== 'object') return out;
    var err = function (code, k) { out.push({ code: code, path: (path || '') + k, key: k }); };
    SPAM_SECRETS.forEach(function (k) { if (r0[k] !== undefined) err('spam_secret', k); });
    SPAM_SITE_ONLY.forEach(function (k) { if (r0[k] !== undefined) err('spam_site_only', k); });
    var C = window.EFBSpamCore;
    SPAM_FORM_KEYS.forEach(function (k) {
      if (r0[k] === undefined) return;
      var raw = r0[k];
      if (raw !== null && typeof raw === 'object') { err('spam_invalid', k); return; }
      var s0 = raw === true ? '1' : raw === false ? '' : String(raw == null ? '' : raw).trim();
      var v = C ? C.sanitizeKey(k, raw) : s0;
      var neutral = s0 === '' || (k === 'spam_off' && (s0 === '0' || s0 === 'false' || raw === false)) || (k === 'spam_akismet' && (s0 === '1' || s0 === 'true' || s0 === 'on' || raw === true));
      if (v === '' && !neutral) { err('spam_invalid', k); return; }
      r0[k] = v;
    });
    return out;
  }
  function failSpam(errs) {
    var e0 = errs[0];
    var msg = {
      spam_secret: e0.key + ' is a site secret: it is set in Settings, never in a form',
      spam_site_only: e0.key + ' is a site setting (Settings › Captchas), not a form key',
      spam_invalid: 'This value cannot be used for ' + e0.key,
    }[e0.code] || e0.code;
    var e = fail(e0.code, msg);
    e.errors = errs;
    return e;
  }

  function failLayout(errs) {
    var e0 = errs[0];
    var msg = {
      layout_invalid: 'This value cannot be used for ' + e0.key,
      layout_section_group: 'A section cannot go inside a repeater group',
      layout_review_payment: 'The review page is not available on a form with a Stripe, PayPal or PersiaPay field',
      layout_vis_role: 'vis_roles names a role this site does not have',
      plan_required: e0.key + ' needs a higher plan',
    }[e0.code] || e0.code;
    var e = fail(e0.code, msg + ' (' + e0.path + ')');
    e.path = e0.path; e.errors = errs;
    return e;
  }
  /**
   * F10: set the layout keys of one field (section collapsible / collapsed, rich_text / rich_toolbar, vis_mode /
   * vis_roles, esign_mode / esign_font) with the same rules as insertItems. Returns {changed, values}.
   */
  function setFieldLayout(id, patch) {
    var list = rows();
    var idx = fieldIndex(list, String(id));
    if (idx < 1) throw fail('unknown_target', 'No field with id ' + id + '.');
    if (!patch || typeof patch !== 'object' || Array.isArray(patch)) throw fail('layout_invalid', 'setFieldLayout() needs an object of keys.');
    var C = layCore();
    var allowed = (C ? C.FIELD_KEYS : []).concat(['collapsible']);
    Object.keys(patch).forEach(function (k) { if (allowed.indexOf(k) === -1) throw fail('layout_invalid', 'key ' + k + ' cannot be set with setFieldLayout().'); });
    var row = clone(list[idx]);
    Object.keys(patch).forEach(function (k) { row[k] = patch[k]; });
    if (row.vis_mode !== undefined && row.vis_mode !== 'roles') delete row.vis_roles;
    var errs = layoutCheck([row], idx, list.slice(1));
    if (errs.length) throw failLayout(errs);
    list[idx] = row;
    apiChanged('setFieldLayout', [row.id_]);
    var values = {};
    Object.keys(patch).forEach(function (k) { values[k] = row[k]; });
    return { changed: Object.keys(values), values: values };
  }

  /* ---------------- F15: undo / redo (productivity-efb.js keeps the history) ---------------- */

  // Every call above that changes the form is one undo entry ("AutoPilot: Add fields"); batch()
  // makes several calls one entry. The builder's own buttons and Ctrl+Z undo them like any edit.
  var history = {
    state: function () { return typeof efbHistStateEfb === 'function' ? efbHistStateEfb() : null; },
    canUndo: function () { var s = history.state(); return !!(s && s.canUndo); },
    canRedo: function () { var s = history.state(); return !!(s && s.canRedo); },
    undo: function () { if (typeof efbUndoEfb !== 'function') throw fail('history_unavailable', 'The undo history is not loaded.'); return efbUndoEfb(); },
    redo: function () { if (typeof efbRedoEfb !== 'function') throw fail('history_unavailable', 'The undo history is not loaded.'); return efbRedoEfb(); },
    batch: function (label, fn) {
      if (typeof fn !== 'function') throw fail('invalid_items', 'batch(label, fn) needs a function.');
      var l = String((typeof efb_var !== 'undefined' && efb_var.text && efb_var.text.hAutoPilot) || 'AutoPilot: %s').replace('%s', String(label || ''));
      return typeof efbHistBatchEfb === 'function' ? efbHistBatchEfb(l, fn) : fn();
    },
  };

  /* ---------------- F15-C: the versions of the open form (read; a restore is one undo step, never a save) ---------------- */
  var revisions = {
    list: function () { if (typeof efbRevListEfb !== 'function') return Promise.reject(fail('revisions_unavailable', 'Versions are not loaded.')); return efbRevListEfb(); },
    get: function (revId) { if (typeof efbRevGetEfb !== 'function') return Promise.reject(fail('revisions_unavailable', 'Versions are not loaded.')); return efbRevGetEfb(Number(revId)); },
    restore: function (revId) { if (typeof efbRevRestoreEfb !== 'function') return Promise.reject(fail('revisions_unavailable', 'Versions are not loaded.')); return efbRevRestoreEfb(Number(revId)); },
  };

  var api = {
    version: API_VERSION,
    history: history,
    revisions: revisions,
    addGroup: addGroup,
    moveIntoGroup: moveIntoGroup,
    moveOutOfGroup: moveOutOfGroup,
    setGroup: setGroup,
    setFieldLayout: setFieldLayout,
    setOptions: setOptions,
    setChoices: setChoices,
    setCalc: setCalc,
    validateFormula: validateFormula,
    paymentReadiness: paymentReadiness,
    setFormSettings: setFormSettings,
    setFieldRules: setFieldRules,
    setDateConfig: setDateConfig,
    upgradeDateField: upgradeDateField,
    rollbackDateField: rollbackDateField,
    setVerification: setVerification,
    setUploadConfig: setUploadConfig,
    setHiddenField: setHiddenField,
    setPrefill: setPrefill,
    formGate: formGate,
    newItems: newItems,
    setFormType: setFormType,
    paletteIds: function () { return paletteIds().slice(); },
    getForm: function () { return clone(rows()); },
    setForm: setForm,
    insertItems: insertItems,
    removeItems: removeItems,
    render: render,
    refreshSettingsPanel: refreshSettingsPanel,
    openSettings: openSettings,
    closeSettings: closeSettings,
  };
  window.efbBuilderApi = api;
  try { baselineJson = snapshot(); } catch (e) { baselineJson = null; }
})();
