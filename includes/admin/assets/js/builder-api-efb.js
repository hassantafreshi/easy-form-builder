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
    });
    valj_efb = list.filter(function (r, i) { return i === 0 || !(r && (drop[r.id_] || drop[r.parent])); });
    renumber(valj_efb);
    apiChanged('removeItems', Object.keys(drop));
    return { ids: Object.keys(drop) };
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
    var values = {};
    keys.forEach(function (k) {
      var v = patch[k];
      var f11 = typeof efb_limits_value_efb === 'function' ? efb_limits_value_efb(k, v) : null;
      values[k] = f11 !== null ? f11 : (v === true ? '1' : v === false ? '' : String(v == null ? '' : v));
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

  var api = {
    version: API_VERSION,
    paymentReadiness: paymentReadiness,
    setFormSettings: setFormSettings,
    setFieldRules: setFieldRules,
    setDateConfig: setDateConfig,
    upgradeDateField: upgradeDateField,
    rollbackDateField: rollbackDateField,
    setVerification: setVerification,
    setUploadConfig: setUploadConfig,
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
