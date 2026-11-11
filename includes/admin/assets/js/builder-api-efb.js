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
    var next = clone(items);
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

  var api = {
    version: API_VERSION,
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
