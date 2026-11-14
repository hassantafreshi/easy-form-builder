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

  var core = {
    CHILD_TYPES: CHILD_TYPES, COUNT_SOURCE_TYPES: COUNT_SOURCE_TYPES, KEYS: KEYS, RUNTIME_KEYS: RUNTIME_KEYS, DEFAULTS: DEFAULTS, CAPS: CAPS,
    asciiDigits: asciiDigits, caps: caps, childTypes: childTypes, isGroup: isGroup, childrenMap: childrenMap, childGroups: childGroups,
    limits: limits, sanitizeKey: sanitizeKey
  };

  var page = {};

  return { core: core, page: page };
});
