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

  /* ---------------- step 1 of a blank form ---------------- */

  // Create → blank form leaves only row 0; the builder adds the step-1 row with the first
  // palette field. A field at index 1 is where the renderers expect that step (stored, but
  // never drawn on the public form), so the API adds the same row before the first field.
  function hasFirstStep(list) { return !!(list[1] && list[1].type === 'step'); }
  function firstStepRow() {
    if (typeof efb_first_step_row_efb === 'function') return efb_first_step_row_efb(1);
    // admin-efb.js older than this file (browser cache): the same row, built here.
    var t = (typeof efb_var === 'object' && efb_var && efb_var.text) || {};
    var name = (typeof efb_first_step_name_efb === 'function') ? efb_first_step_name_efb() : String(t.form || '').toUpperCase();
    return {
      id_: '1', type: 'step', dataId: '1', classes: '', id: '1', name: name, icon: 'bi-ui-checks-grid', step: 1, amount: 1, EfbVersion: 2, message: '',
      label_text_size: 'fs-5', el_text_size: 'fs-5', label_text_color: 'text-darkb', el_text_color: 'text-labelEfb',
      message_text_color: (typeof pub_message_text_color_efb !== 'undefined') ? pub_message_text_color_efb : 'text-muted',
      icon_color: (typeof pub_icon_color_efb !== 'undefined') ? pub_icon_color_efb : 'text-pinkEfb', visible: 1,
    };
  }
  // Returns the list with the step-1 row at index 1 (a new array when it had to be added).
  function withFirstStep(list) {
    if (list.length < 1 || hasFirstStep(list)) return list;
    return [list[0], firstStepRow()].concat(list.slice(1));
  }
  function syncStepCount(list) {
    if (!(Number(list[0].steps) >= 1)) list[0].steps = 1;
    if (typeof step_el_efb !== 'undefined') step_el_efb = Number(list[0].steps);
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
    var next = clone(items);
    // Fields without the step-1 row (a blank form) get it; row 0 alone stays a blank form.
    var addedStep = false;
    if (next.length > 1) { var withStep = withFirstStep(next); addedStep = withStep !== next; next = withStep; }
    if (next.length > 1) checkRows(next.slice(1), []);
    if (typeof efbNormalizeOrderEfb === 'function') efbNormalizeOrderEfb(next);
    // The caller's amounts were counted without that step: number them again (A2 rule).
    if (addedStep) renumber(next);
    valj_efb = next;
    if (typeof form_type_emsFormBuilder !== 'undefined') form_type_emsFormBuilder = next[0].type;
    if (typeof formName_Efb !== 'undefined' && next[0].formName) formName_Efb = next[0].formName;
    if (next.length > 1) syncStepCount(next);
    else if (typeof step_el_efb !== 'undefined') step_el_efb = Number(next[0].steps) || 1;
    var max = 0;
    next.forEach(function (r) { max = Math.max(max, orderOf(r)); });
    if (typeof amount_el_efb !== 'undefined') amount_el_efb = max;
    apiChanged('setForm', []);
    if (opts && opts.render === false) return Promise.resolve({ complete: false, pending: [], rendered: false });
    return render(opts);
  }

  function insertItems(items, opts) {
    opts = opts || {};
    var live = rows();
    // Positions are worked out on the form with its step-1 row; live is changed only once
    // every check has passed, so an error leaves a blank form blank.
    var list = withFirstStep(live);
    checkRows(items, list);
    var add = clone(items);
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
    if (list !== live) {
      live.splice(1, 0, list[1]);
      list = live;
      syncStepCount(list);
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

  var api = {
    version: API_VERSION,
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
