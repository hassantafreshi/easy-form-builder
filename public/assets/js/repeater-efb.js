/*!
 * Easy Form Builder - repeater group (F09, docs/feature-gaps/F09-repeater.fa.md).
 *
 * Pure part (EFBRepeaterCore): the children map, the limits of a group, the save rules of its keys, the
 * copies of a row (virtual ids {child}_r{n}), the wire shape (base ids + rg / ri) and the formula of a calc
 * child in a row. Twin of \Emsfb\Repeater (includes/class-Emsfb-repeater.php); tests/test-repeater-model.php
 * and tests/test-repeater-parity.js compare them.
 *
 * Page part (EFBRepeater): add / remove rows from the row <template> the server rendered, min / max,
 * collapsible rows, rows_from, numbering in the page's digits, announcements, the in-memory structure of
 * the form (row copies are registered there so every reader of valj_efb works on them unchanged).
 *
 * ES5, no dependencies. Browser: window.EFBRepeaterCore and window.EFBRepeater; Node: module.exports.
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module === 'object' && module.exports) { module.exports = api; } else { root.EFBRepeaterCore = api.core; root.EFBRepeater = api.page; }
})(typeof self !== 'undefined' ? self : this, function (root) {
  'use strict';

  var CHILD_TYPES = ['text', 'firstName', 'lastName', 'email', 'number', 'tel', 'url', 'textarea', 'date', 'advdate', 'range', 'color',
    'select', 'radio', 'checkbox', 'multiselect', 'switch', 'yesNo', 'rating', 'pointr5', 'pointr10', 'heading', 'html', 'file', 'mobile',
    'calc', 'hiddenField', 'ranking'];
  var COUNT_SOURCE_TYPES = ['number', 'range', 'select', 'radio', 'calc'];
  var KEYS = ['min_rows', 'max_rows', 'default_rows', 'rows_from', 'row_label', 'collapsible', 'collapse_prev', 'rpt_layout', 'btn_add_text', 'rmv_confirm'];
  var RUNTIME_KEYS = ['rpt_base', 'rpt_n', 'rpt_group', 'rpt_ri'];
  var DEFAULTS = { min_rows: '1', max_rows: '10', default_rows: '1', rows_from: '', row_label: '', collapsible: '0', collapse_prev: '1', rpt_layout: 'blocks', btn_add_text: '', rmv_confirm: '1' };
  var CAPS = { rows: 50, children: 25, groups: 10, submission_rows: 1500, email_rows: 20 };
  var ID_RE = /^[A-Za-z0-9_-]{1,40}$/;

  function str(v) { return v === null || v === undefined || typeof v === 'object' ? '' : String(v); }
  function asciiDigits(s) {
    return str(s).replace(/[۰-۹]/g, function (c) { return String(c.charCodeAt(0) - 0x06F0); })
      .replace(/[٠-٩]/g, function (c) { return String(c.charCodeAt(0) - 0x0660); });
  }
  function intOf(v, d) {
    var s = asciiDigits(v).trim();
    return /^\d{1,6}$/.test(s) ? parseInt(s, 10) : d;
  }
  function caps(over) {
    var c = {};
    for (var k in CAPS) if (Object.prototype.hasOwnProperty.call(CAPS, k)) c[k] = CAPS[k];
    if (over) for (var j in over) if (Object.prototype.hasOwnProperty.call(over, j) && over[j] > 0) c[j] = over[j];
    return c;
  }
  function childTypes(list) { return (list || CHILD_TYPES).filter(function (t) { return t !== 'repeater'; }); }
  function isGroup(r) { return !!r && r.type === 'repeater' && str(r.id_) !== ''; }

  /** Group id => indexes of its children (twin of Repeater::children_map()). */
  function childrenMap(structure, opts) {
    opts = opts || {};
    var groups = {}, has = false;
    if (!(structure instanceof Array)) return groups;
    structure.forEach(function (r, i) { if (i > 0 && isGroup(r)) { groups[str(r.id_)] = []; has = true; } });
    if (!has) return groups;
    var allowed = {};
    childTypes(opts.childTypes).forEach(function (t) { allowed[t] = true; });
    var cap = caps(opts.caps).children;
    structure.forEach(function (r, i) {
      if (i < 1 || !r) return;
      var g = str(r.group), t = str(r.type);
      if (g === '' || !Object.prototype.hasOwnProperty.call(groups, g) || t === 'option' || t === 'repeater' || t === 'step' || !allowed[t] || str(r.id_) === '') return;
      if (r.rpt_base !== undefined) return; // a row copy is never a base child
      if (groups[g].length >= cap) return;
      groups[g].push(i);
    });
    return groups;
  }

  /** child id => group id. */
  function childGroups(structure, opts) {
    var out = {}, m = childrenMap(structure, opts);
    Object.keys(m).forEach(function (g) { m[g].forEach(function (i) { out[str(structure[i].id_)] = g; }); });
    return out;
  }

  /** {min, max, default} of a group (twin of Repeater::limits()). */
  function limits(g, repeatOk, capsOver) {
    var cap = caps(capsOver).rows;
    var max = Math.max(1, Math.min(cap, intOf(g && g.max_rows !== undefined ? g.max_rows : DEFAULTS.max_rows, 10)));
    var min = Math.max(0, Math.min(max, intOf(g && g.min_rows !== undefined ? g.min_rows : DEFAULTS.min_rows, 1)));
    var req = g && (str(g.required) === '1' || str(g.required) === 'true');
    if (req && min < 1) min = 1;
    var def = Math.max(min, Math.min(max, intOf(g && g.default_rows !== undefined ? g.default_rows : DEFAULTS.default_rows, 1)));
    if (repeatOk === false) { max = 1; min = Math.min(min, 1); def = Math.max(min, Math.min(1, def)); }
    return { min: min, max: max, 'default': def };
  }

  /** One key as it may be stored ('' = drop it). Twin: Repeater::sanitize_key(). */
  function sanitizeKey(k, v, capsOver) {
    if (RUNTIME_KEYS.indexOf(k) !== -1) return '';
    if (typeof v === 'boolean') v = v ? '1' : '0';
    v = str(v);
    switch (k) {
      case 'min_rows': case 'max_rows': case 'default_rows':
        var s = asciiDigits(v).trim();
        if (!/^\d{1,4}$/.test(s)) return '';
        return String(Math.min(parseInt(s, 10), caps(capsOver).rows));
      case 'rows_from': case 'group':
        return ID_RE.test(v) ? v : '';
      case 'collapsible': case 'collapse_prev': case 'rmv_confirm':
        return v === '1' || v === 'true' || v === 'on' ? '1' : '0';
      case 'rpt_layout':
        return v === 'blocks' || v === 'inline' ? v : 'blocks';
      case 'row_label': case 'btn_add_text':
        var t = v.replace(/<[^>]*>/g, '').replace(/%[a-f0-9]{2}/gi, '').replace(/[\r\n\t ]+/g, ' ').trim();
        return t.slice(0, k === 'row_label' ? 200 : 100);
    }
    return '';
  }

  /** A number in a locale's digits (twin of Form_Gate::locale_digits: fa/ps/azb/haz Persian, ar/ary/arq/ckb/ur Arabic-Indic). */
  function digits(s, locale) {
    var lang = String(locale || 'en_US').split(/[_-]/)[0].toLowerCase();
    var base = ['fa', 'ps', 'azb', 'haz'].indexOf(lang) !== -1 ? 0x06F0 : (['ar', 'ary', 'arq', 'ckb', 'ur'].indexOf(lang) !== -1 ? 0x0660 : 0);
    if (!base) return String(s);
    return String(s).replace(/[0-9]/g, function (d) { return String.fromCharCode(base + Number(d)); });
  }

  /** The formula of a calc child in a row: siblings read the same row (twin of Repeater::rewrite_formula()). */
  function rewriteFormula(formula, siblings, suffix) {
    if (typeof formula !== 'string' || formula === '' || !siblings || !siblings.length) return str(formula);
    var set = {};
    siblings.forEach(function (x) { set[String(x)] = true; });
    return formula.replace(/\{(field:)?([A-Za-z0-9_-]{1,40})(\.[A-Za-z0-9_-]{1,40})?\}/g, function (m, f, id, part) {
      if (!set[id]) return m;
      return '{' + (f || '') + id + suffix + (part || '') + '}';
    });
  }

  /**
   * The rows of row n of group g (twin of the copies Formbuilder::generate_repeater_efb() draws): every child
   * as {child}_r{n} with rpt_base / rpt_n / rpt_group, its options as {option}_r{n} (parent remapped).
   */
  function copyRows(structure, g, n, opts) {
    var m = childrenMap(structure, opts), out = [];
    var idx = m[g] || [];
    var kids = idx.map(function (i) { return str(structure[i].id_); });
    var sfx = '_r' + n;
    idx.forEach(function (i) {
      var c = structure[i], copy = {};
      for (var k in c) if (Object.prototype.hasOwnProperty.call(c, k)) copy[k] = c[k];
      copy.id_ = str(c.id_) + sfx;
      copy.dataId = copy.id_ + '-id';
      copy.rpt_base = str(c.id_);
      copy.rpt_n = n;
      copy.rpt_group = g;
      delete copy.group;
      if (copy.type === 'file') copy.max_files = '1';
      if (typeof copy.formula === 'string') copy.formula = rewriteFormula(copy.formula, kids, sfx);
      out.push(copy);
      structure.forEach(function (o, oi) {
        if (oi < 1 || !o || (o.type !== 'option' && o.type !== 'r_matrix') || str(o.parent) !== str(c.id_)) return;
        var oc = {};
        for (var kk in o) if (Object.prototype.hasOwnProperty.call(o, kk)) oc[kk] = o[kk];
        oc.id_ = str(o.id_) + sfx;
        if (oc.id_op !== undefined) oc.id_op = oc.id_;
        oc.dataId = oc.id_ + '-id';
        oc.parent = copy.id_;
        oc.rpt_base = str(o.id_);
        oc.rpt_n = n;
        oc.rpt_group = g;
        out.push(oc);
      });
    });
    return out;
  }

  var core = {
    CHILD_TYPES: CHILD_TYPES, COUNT_SOURCE_TYPES: COUNT_SOURCE_TYPES, KEYS: KEYS, RUNTIME_KEYS: RUNTIME_KEYS, DEFAULTS: DEFAULTS, CAPS: CAPS,
    asciiDigits: asciiDigits, caps: caps, childTypes: childTypes, isGroup: isGroup, childrenMap: childrenMap, childGroups: childGroups,
    limits: limits, sanitizeKey: sanitizeKey, digits: digits, rewriteFormula: rewriteFormula, copyRows: copyRows
  };

  /* ================================================================== page runtime (EFBRepeater) */

  var page = (function () {
    var S = {}; // fid|g -> state
    function doc() { return typeof document !== 'undefined' ? document : null; }
    function ev() { return (typeof efb_var !== 'undefined' && efb_var) || root.efb_var || (typeof ajax_object_efm !== 'undefined' ? ajax_object_efm : null) || {}; }
    function T(k, fb) { var e = ev(); return (e.text && e.text[k]) || fb; }
    function locale() { var e = ev(); return e.wp_lan || e.language || 'en_US'; }
    function num(n) { return digits(String(n), locale()); }
    function fill(t, a, b) {
      // Inserted values are isolated (FSI … PDI) so a number keeps its place in a right-to-left sentence.
      var iso = function (x) { return '⁨' + x + '⁩'; };
      return String(t).replace('%1$s', iso(a)).replace('%2$s', iso(b === undefined ? '' : b)).replace('%s', iso(a));
    }
    function forms() { return typeof valj_efb_new !== 'undefined' && valj_efb_new instanceof Array ? valj_efb_new : (root.valj_efb_new instanceof Array ? root.valj_efb_new : []); }
    /** The form's structure array itself (row copies are added to it, so every reader of the form sees them). */
    function structure(fid) {
      var f = forms().filter(function (x) { return x && Number(x.id) === Number(fid); })[0];
      if (f && f.form_structer instanceof Array) return f.form_structer;
      return typeof valj_efb !== 'undefined' && valj_efb instanceof Array ? valj_efb : [];
    }
    function sendBack() { return typeof sendBack_emsFormBuilder_pub !== 'undefined' ? sendBack_emsFormBuilder_pub : (root.sendBack_emsFormBuilder_pub || []); }
    function key(fid, g) { return String(Number(fid)) + '|' + g; }
    function stateOf(fid, g) { return S[key(fid, g)] || null; }
    function emit(name, detail) { try { var d = doc(); if (d) d.dispatchEvent(new CustomEvent(name, { detail: detail })); } catch (e) { /* old browsers */ } }
    function fsOf(fid, g) {
      var d = doc(); if (!d) return null;
      var all = d.querySelectorAll('fieldset[data-rpt]');
      for (var i = 0; i < all.length; i++) if (all[i].getAttribute('data-rpt') === g && Number(all[i].getAttribute('data-formid')) === Number(fid)) return all[i];
      return null;
    }
    function rowEls(st) { return st && st.rows ? Array.prototype.slice.call(st.rows.querySelectorAll(':scope > .efb-rpt-row')) : []; }

    /** {n, el, ri} of every row in page order (ri = place from 0, what is sent). */
    function rowsOf(fid, g) {
      var st = stateOf(fid, g);
      return rowEls(st).map(function (el, i) { return { n: Number(el.getAttribute('data-rpt-n')), el: el, ri: i }; });
    }

    /** Row copies of row n into the structure, right after the group's other rows. */
    function register(fid, g, n) {
      var st0 = structure(fid);
      if (st0.some(function (r) { return r && r.rpt_group === g && String(r.rpt_n) === String(n) && r.type !== 'option'; })) return;
      var copies = copyRows(st0, g, n);
      var mine = {};
      st0.forEach(function (r) { if (r && (r.group === g || r.rpt_group === g)) mine[str(r.id_)] = true; });
      var at = -1;
      st0.forEach(function (r, i) {
        if (!r) return;
        if (str(r.id_) === g || r.group === g || r.rpt_group === g || (r.type === 'option' && mine[str(r.parent)])) at = i;
      });
      if (at < 0) at = st0.length - 1;
      Array.prototype.splice.apply(st0, [at + 1, 0].concat(copies));
    }
    function unregister(fid, g, n) {
      var st0 = structure(fid), gone = {};
      for (var i = st0.length - 1; i > 0; i--) {
        var r = st0[i];
        if (r && r.rpt_group === g && String(r.rpt_n) === String(n)) { gone[str(r.id_)] = true; st0.splice(i, 1); }
      }
      var sb = sendBack();
      var removed = [];
      for (var j = sb.length - 1; j >= 0; j--) {
        var x = sb[j];
        if (x && gone[str(x.id_)] && Number(x.form_id) === Number(fid)) removed.unshift(sb.splice(j, 1)[0]);
      }
      if (typeof files_emsFormBuilder !== 'undefined' && files_emsFormBuilder instanceof Array) {
        for (var k = files_emsFormBuilder.length - 1; k >= 0; k--) if (files_emsFormBuilder[k] && gone[str(files_emsFormBuilder[k].id_)]) files_emsFormBuilder.splice(k, 1);
      }
      return removed;
    }
    function persist() {
      try { if (typeof localStorage !== 'undefined' && typeof efb_sendback_for_storage_efb === 'function') localStorage.setItem('sendback', efb_sendback_for_storage_efb(sendBack())); } catch (e) { /* storage blocked */ }
    }

    /** The group's own answer row: {id_: g, type: 'repeater', value: '<rows>'} (always sent, also "0"). */
    function summary(fid, g) {
      var st = stateOf(fid, g);
      if (!st) return;
      var count = rowEls(st).length;
      var sb = sendBack(), row = null;
      for (var i = 0; i < sb.length; i++) if (sb[i] && sb[i].id_ === g && Number(sb[i].form_id) === Number(fid)) row = sb[i];
      var def = structure(fid).filter(function (r) { return r && r.id_ === g; })[0] || {};
      if (row) row.value = String(count);
      else sb.push({ id_: g, name: def.name || '', id_ob: g + '-f', amount: def.amount, type: 'repeater', value: String(count), session: typeof sessionPub_emsFormBuilder !== 'undefined' ? sessionPub_emsFormBuilder : 'reciveFromClient', form_id: Number(fid) });
      persist();
    }

    function titleOf(st, pos, rowEl) {
      var tpl = st.label || T('rowN', 'Row %s').replace('%s', '{n}');
      return tpl.replace(/\{n\}/g, num(pos + 1)).replace(/%s/g, num(pos + 1)).replace(/\{field:([A-Za-z0-9_-]{1,40})\}/g, function (m, id) {
        var n = rowEl.getAttribute('data-rpt-n');
        var sb = sendBack();
        for (var i = 0; i < sb.length; i++) if (sb[i] && sb[i].id_ === id + '_r' + n && typeof sb[i].value === 'string') return sb[i].value.split('@efb!').join(', ');
        return '';
      }).replace(/\s+/g, ' ').trim();
    }
    function labelOf(fid, vid) {
      var r = structure(fid).filter(function (x) { return x && x.id_ === vid; })[0];
      return r && r.name ? String(r.name) : '';
    }

    /** Titles, button names and the accessible name of every field, after any change of the rows. */
    function renumber(fid, g) {
      var st = stateOf(fid, g);
      if (!st) return;
      var els = rowEls(st), count = els.length;
      els.forEach(function (el, i) {
        var n = el.getAttribute('data-rpt-n');
        var t = el.querySelector('.efb-rpt-title');
        if (t) t.textContent = titleOf(st, i, el);
        var rm = el.querySelector('.efb-rpt-remove');
        if (rm) {
          rm.setAttribute('aria-label', T('removeRow', 'Remove row %s').replace('%s', num(i + 1)));
          rm.disabled = st.disabled || count <= st.min;
        }
        var tg = el.querySelector('.efb-rpt-toggle');
        if (tg) tg.setAttribute('aria-label', T(el.classList.contains('efb-rpt-collapsed') ? 'expandRow' : 'collapseRow', el.classList.contains('efb-rpt-collapsed') ? 'Show row %s' : 'Hide row %s').replace('%s', num(i + 1)));
        var fields = el.querySelectorAll('.emsFormBuilder_v, .efb-rank, [data-efb-cal]');
        for (var k = 0; k < fields.length; k++) {
          var f = fields[k];
          var vid = f.getAttribute('data-vid') || '';
          if (!vid || f.type === 'hidden') continue;
          var base = labelOf(fid, vid);
          if (base) f.setAttribute('aria-label', T('rptChildLabel', '%1$s, row %2$s').replace('%1$s', base).replace('%2$s', num(i + 1)));
        }
        summarize(st, el);
      });
      if (st.add) {
        st.add.disabled = st.disabled || count >= st.max;
        if (st.maxMsg) st.maxMsg.hidden = count < st.max || st.max <= 1;
      }
      st.fs.setAttribute('data-rows', String(count));
    }
    /** A folded row shows its first text answer (or nothing) beside its title. */
    function summarize(st, el) {
      var s = el.querySelector('.efb-rpt-summary');
      if (!s) return;
      var n = el.getAttribute('data-rpt-n'), out = '';
      var sb = sendBack();
      for (var i = 0; i < st.kids.length && !out; i++) {
        for (var j = 0; j < sb.length; j++) if (sb[j] && sb[j].id_ === st.kids[i] + '_r' + n && typeof sb[j].value === 'string' && sb[j].value !== '' && sb[j].value !== '@file@') { out = sb[j].value.split('@efb!').join(', '); break; }
      }
      s.textContent = out;
    }
    function announce(st, text) {
      if (!st.live) return;
      st.live.textContent = '';
      setTimeout(function () { st.live.textContent = text; }, 30);
    }
    function setCollapsed(st, el, on) {
      el.classList.toggle('efb-rpt-collapsed', !!on);
      var tg = el.querySelector('.efb-rpt-toggle');
      if (tg) tg.setAttribute('aria-expanded', on ? 'false' : 'true');
      renumber(st.fid, st.g);
    }
    function firstField(el) {
      return el.querySelector('input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button.btn-toggle, [tabindex="0"]');
    }

    /** Every widget of a new row: the fields' own set-up (efb_init_fields_in_efb) and the modules that bind by form. */
    function initRow(fid, el) {
      if (typeof efb_init_fields_in_efb === 'function') { try { efb_init_fields_in_efb(el); } catch (e) { /* a broken widget must not stop the row */ } }
      try { if (root.EFBCalendar && typeof root.EFBCalendar.mount === 'function') root.EFBCalendar.mount(el); } catch (e) {}
      try { if (root.EFBCalc && root.EFBCalc.sliders && typeof root.EFBCalc.sliders.bindAll === 'function') root.EFBCalc.sliders.bindAll(); } catch (e) {}
      try { if (root.EfbChoices && typeof root.EfbChoices.initForm === 'function') root.EfbChoices.initForm(String(fid)); } catch (e) {}
      try { if (root.EFBSmart && typeof root.EFBSmart.refresh === 'function') root.EFBSmart.refresh(Number(fid)); } catch (e) {}
      try { if (root.EFBCalc && root.EFBCalc.page) root.EFBCalc.page.schedule(Number(fid)); } catch (e) {}
    }
    function after(fid) {
      try { if (typeof updateStepButtonState_efb === 'function') updateStepButtonState_efb(Number(fid)); } catch (e) {}
      try { if (root.EFBCalc && root.EFBCalc.page) root.EFBCalc.page.schedule(Number(fid)); } catch (e) {}
    }

    /** A new row from the template: copies registered, the fields set up, numbering, focus, announcement. */
    function addRow(fid, g, opts) {
      opts = opts || {};
      var st = stateOf(fid, g);
      if (!st) return null;
      var count = rowEls(st).length;
      if (count >= st.max) { if (st.maxMsg && st.max > 1) st.maxMsg.hidden = false; return null; }
      var n = opts.n !== undefined ? Number(opts.n) : st.counter;
      st.counter = Math.max(st.counter, n + 1);
      var html = String(st.tpl).replace(/<\\\//g, '</').replace(/__RI__/g, String(n)).replace(/__NUM__/g, num(count + 1));
      var box = doc().createElement('div');
      box.innerHTML = html;
      var el = box.firstElementChild;
      if (!el) return null;
      if (st.collapsible && st.collapsePrev && !opts.silent) rowEls(st).forEach(function (r) { r.classList.add('efb-rpt-collapsed'); var tg = r.querySelector('.efb-rpt-toggle'); if (tg) tg.setAttribute('aria-expanded', 'false'); });
      st.rows.appendChild(el);
      register(fid, g, n);
      wireRow(st, el);
      initRow(fid, el);
      renumber(fid, g);
      summary(fid, g);
      after(fid);
      if (!opts.silent) {
        announce(st, T('rowAdded', 'Row %s added').replace('%s', num(count + 1)));
        var f = firstField(el);
        if (opts.focus !== false && f) {
          try { if (el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e) {}
          try { f.focus({ preventScroll: true }); } catch (e) { f.focus(); }
        }
      }
      emit('efb:repeater:rowadded', { form_id: Number(fid), group: g, n: n });
      return el;
    }

    function hasData(fid, el) {
      var n = el.getAttribute('data-rpt-n'), sb = sendBack();
      var st = null;
      for (var k in S) if (S[k].rows && S[k].rows.contains(el)) st = S[k];
      if (!st) return false;
      return sb.some(function (x) { return x && Number(x.form_id) === Number(fid) && st.kids.some(function (c) { return x.id_ === c + '_r' + n && x.value !== '' && x.value !== undefined; }); });
    }

    /** Remove a row (min respected); asks first when it holds answers and the group says so. */
    function removeRow(fid, g, el, opts) {
      opts = opts || {};
      var st = stateOf(fid, g);
      if (!st || !el) return false;
      var els = rowEls(st), pos = els.indexOf(el);
      if (pos === -1) return false;
      if (!opts.force && els.length <= st.min) return false;
      if (!opts.force && !opts.confirmed && st.confirm && hasData(fid, el)) {
        var c = el.querySelector('.efb-rpt-confirm');
        if (c) {
          c.hidden = false;
          var yes = c.querySelector('.efb-rpt-yes');
          if (yes) yes.focus();
          return false;
        }
      }
      var n = Number(el.getAttribute('data-rpt-n'));
      var removed = unregister(fid, g, n);
      if (opts.stash) st.stash.push({ n: n, el: el, rows: removed });
      el.parentNode.removeChild(el);
      renumber(fid, g);
      summary(fid, g);
      persist();
      after(fid);
      if (!opts.silent) {
        announce(st, T('rowRemoved', 'Row removed'));
        var rest = rowEls(st);
        var target = rest[Math.max(0, pos - 1)];
        var f = target ? (target.querySelector('.efb-rpt-remove:not([disabled])') || firstField(target)) : null;
        if (!f && st.add && !st.add.disabled) f = st.add;
        if (f) { try { f.focus(); } catch (e) {} }
      }
      emit('efb:repeater:rowremoved', { form_id: Number(fid), group: g, n: n });
      return true;
    }

    /** Exactly `count` rows (rows_from, a saved draft): rows taken away keep their answers in memory (never sent) and come back. */
    function ensureRows(fid, g, count, opts) {
      opts = opts || {};
      var st = stateOf(fid, g);
      if (!st) return 0;
      count = Math.max(st.min, Math.min(st.max, Number(count) || 0));
      var els = rowEls(st);
      while (els.length > count) { removeRow(fid, g, els[els.length - 1], { force: true, silent: true, stash: opts.keep !== false }); els = rowEls(st); }
      while (rowEls(st).length < count) {
        var back = st.stash.pop();
        if (back) {
          st.rows.appendChild(back.el);
          register(fid, g, back.n);
          var sb = sendBack();
          back.rows.forEach(function (r) { sb.push(r); });
          renumber(fid, g);
          summary(fid, g);
          emit('efb:repeater:rowadded', { form_id: Number(fid), group: g, n: back.n });
        } else if (!addRow(fid, g, { silent: true, focus: false })) break;
      }
      persist();
      after(fid);
      return rowEls(st).length;
    }

    function wireRow(st, el) {
      if (el.getAttribute('data-rpt-wired') === '1') return;
      el.setAttribute('data-rpt-wired', '1');
      var rm = el.querySelector('.efb-rpt-remove');
      if (rm) rm.addEventListener('click', function () { removeRow(st.fid, st.g, el); });
      var tg = el.querySelector('.efb-rpt-toggle');
      if (tg) tg.addEventListener('click', function () { setCollapsed(st, el, !el.classList.contains('efb-rpt-collapsed')); });
      var c = el.querySelector('.efb-rpt-confirm');
      if (c) {
        var yes = c.querySelector('.efb-rpt-yes'), no = c.querySelector('.efb-rpt-no');
        if (yes) yes.addEventListener('click', function () { c.hidden = true; removeRow(st.fid, st.g, el, { confirmed: true }); });
        if (no) no.addEventListener('click', function () { c.hidden = true; if (rm) rm.focus(); });
        c.addEventListener('keydown', function (e) { if (e.key === 'Escape') { c.hidden = true; if (rm) rm.focus(); } });
      }
      // Answers change the folded summary and a title with {field:…}.
      el.addEventListener('change', function () { setTimeout(function () { renumber(st.fid, st.g); }, 0); });
    }

    /** The number of rows a source field asks for: a number / slider value, a choice's stored number or its leading digits. */
    function countFrom(fid, st, ob) {
      var v = ob && ob.value !== undefined && ob.value !== null ? String(ob.value) : '';
      var src = structure(fid).filter(function (x) { return x && x.id_ === st.from; })[0] || {};
      if (src.type === 'select' || src.type === 'radio') {
        var opt = structure(fid).filter(function (x) { return x && x.type === 'option' && x.parent === st.from && (x.id_ === ob.id_ob || x.value === v); })[0];
        if (opt && opt.opt_value !== undefined && /^\d+$/.test(asciiDigits(opt.opt_value).trim())) return parseInt(asciiDigits(opt.opt_value), 10);
      }
      var m = /^\s*(\d+)/.exec(asciiDigits(v));
      return m ? parseInt(m[1], 10) : 0;
    }
    /** core-efb.js tells every stored answer: a rows_from source changes the rows. */
    function onAnswer(fid, ob) {
      if (!ob || !ob.id_) return;
      for (var k in S) {
        var st = S[k];
        if (st.fid === Number(fid) && st.from && st.from === ob.id_) ensureRows(fid, st.g, countFrom(fid, st, ob));
      }
    }

    function initGroup(fs) {
      var fid = Number(fs.getAttribute('data-formid') || 0), g = fs.getAttribute('data-rpt');
      if (!g || stateOf(fid, g)) return;
      // Looked up inside the group's fieldset: the same form may be on the page twice (same ids).
      var q = function (sfx) { return fs.querySelector('[id="' + g + sfx + '"]'); };
      var tplEl = q('_tpl');
      var st = {
        fid: fid, g: g, fs: fs, rows: q('_rows'), add: q('_add'), live: q('_live'), maxMsg: q('_maxmsg'),
        tpl: tplEl ? tplEl.textContent : '', min: Number(fs.getAttribute('data-min') || 0), max: Number(fs.getAttribute('data-max') || 1), from: fs.getAttribute('data-from') || '',
        collapsible: fs.getAttribute('data-collapsible') === '1', collapsePrev: fs.getAttribute('data-collapse-prev') !== '0', confirm: fs.getAttribute('data-confirm') !== '0',
        label: fs.getAttribute('data-label') || '', disabled: fs.getAttribute('data-disabled') === '1', counter: 0, stash: [], kids: []
      };
      if (!st.rows) return;
      S[key(fid, g)] = st;
      var m = childrenMap(structure(fid));
      st.kids = (m[g] || []).map(function (i) { return str(structure(fid)[i].id_); });
      rowEls(st).forEach(function (el) {
        var n = Number(el.getAttribute('data-rpt-n'));
        st.counter = Math.max(st.counter, n + 1);
        register(fid, g, n);
        wireRow(st, el);
      });
      if (st.add) st.add.addEventListener('click', function () { addRow(fid, g); });
      summary(fid, g);
    }

    /** The groups of the page: copies of the server's first rows registered, buttons wired (before the fields are set up). */
    function init() {
      var d = doc();
      if (!d) return;
      var all = d.querySelectorAll('fieldset[data-rpt]');
      for (var i = 0; i < all.length; i++) initGroup(all[i]);
    }
    /** After the page's fields are set up: numbering, accessible names, a rows_from source that already has a value. */
    function ready() {
      for (var k in S) {
        var st = S[k];
        renumber(st.fid, st.g);
        if (st.from) {
          var sb = sendBack(), have = null;
          for (var i = 0; i < sb.length; i++) if (sb[i] && sb[i].id_ === st.from && Number(sb[i].form_id) === st.fid) have = sb[i];
          if (have) ensureRows(st.fid, st.g, countFrom(st.fid, st, have));
        }
      }
    }

    /** null, or {code, message} when the number of rows is outside [min, max] (the step check, spec §9.1). */
    function check(fid, g) {
      var st = stateOf(fid, g);
      if (!st) return null;
      var n = rowEls(st).length;
      var name = labelOf(fid, g);
      if (n < st.min) return { code: 'min', message: fill(T('rptMinRowsMsg', '%1$s needs at least %2$s rows.'), name, num(st.min)) };
      if (n > st.max) return { code: 'max', message: fill(T('rptMaxRowsMsg', '%1$s allows at most %2$s rows.'), name, num(st.max)) };
      return null;
    }
    /** Unfold the row that holds an element (an id inside a row, or a field id {child}_r{n}). */
    function reveal(id, fid) {
      var d = doc();
      if (!d || !id) return false;
      var el = d.getElementById(id) || d.getElementById(id + '_');
      var row = el && el.closest ? el.closest('.efb-rpt-row') : null;
      if (!row || !row.classList.contains('efb-rpt-collapsed')) return false;
      for (var k in S) if (S[k].rows && S[k].rows.contains(row)) { setCollapsed(S[k], row, false); row.classList.add('efb-rpt-err'); return true; }
      return false;
    }
    /** The element of a field the server names by its base id and row (row_index), or null. */
    function fieldAt(fid, base, ri) {
      var gs = structure(fid).filter(function (r) { return r && r.id_ === base; })[0];
      var g = gs ? str(gs.group) : '';
      var rows = g ? rowsOf(fid, g) : [];
      var r = rows[Number(ri)];
      return r ? base + '_r' + r.n : null;
    }

    return { init: init, ready: ready, addRow: addRow, check: check, reveal: reveal, fieldAt: fieldAt, removeRow: removeRow, ensureRows: ensureRows, rowsOf: rowsOf, onAnswer: onAnswer, renumber: renumber,
      state: stateOf, groups: function () { return Object.keys(S).map(function (k) { return { form_id: S[k].fid, group: S[k].g }; }); }, structure: structure };
  })();

  return { core: core, page: page };
});
