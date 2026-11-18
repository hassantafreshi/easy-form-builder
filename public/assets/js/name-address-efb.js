/*
 * Easy Form Builder — F17 localized name and address (window.EFBNameAddr, contract version 1, F20 P22).
 * Twin of Emsfb\Address_Schemes (includes/class-Emsfb-address-schemes.php), same data (includes/data/name-address-efb.json).
 *
 * Display (any page: entries, CSV, the customer portal): nameText(), addressText(), groups(), groupText().
 * On a form: the check of a part while the visitor types (core-efb.js calls check()), the Saudi "short address or full
 * address" rule (notRequired()), a city box when the province's city list cannot load (cityFailed()), and fill() /
 * parts() for the Address & Postal add-on (address search, postcode → city). The server checks everything again.
 * The page gets only the formats its form uses (window.efb_na_data, merged by every form on the page).
 */
(function (W) {
  'use strict';
  var DIG = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9', '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9', '−': '-', '‐': '-', '–': '-', '‏': '', '‎': '' };
  var RX = {};
  function data() { var d = W.efb_na_data || (W.efb_var && W.efb_var.name_address && W.efb_var.name_address.display) || {}; return { schemes: d.schemes || {}, names: d.names || {}, words: d.words || [] }; }
  function latin(s) { return String(s == null ? '' : s).replace(/[۰-۹٠-٩−‐–‎‏]/g, function (c) { return DIG[c]; }); }
  function rx(src) {
    if (!RX[src]) { try { RX[src] = new RegExp(String(src).replace(/\\x\{/g, '\\u{'), 'u'); } catch (e) { RX[src] = /^[\s\S]*$/; } }
    return RX[src];
  }
  function len(s) { return Array.from(s).length; }
  /** The stored value of a part (Address_Schemes::normalize). def: {n, nm, fmt, ltr}. */
  function normalize(def, v) {
    v = String(v == null ? '' : v).replace(/\s+/g, ' ').trim();
    var nm = def.nm || '';
    if (def.n || nm || def.ltr) v = latin(v);
    if (nm.indexOf('u') >= 0) v = v.toUpperCase();
    if (nm.indexOf('s') >= 0) v = v.replace(/[ -]/g, '');
    if (nm.indexOf('x') >= 0) v = v.replace(/ /g, '');
    if (def.fmt === 'us' && /^\d{9}$/.test(v)) v = v.slice(0, 5) + '-' + v.slice(5);
    if ((def.fmt === 'uk' || def.fmt === 'ca') && /^[A-Z0-9]{5,7}$/.test(v)) v = v.slice(0, -3) + ' ' + v.slice(-3);
    if (def.fmt === 'jp' && /^\d{7}$/.test(v)) v = v.slice(0, 3) + '-' + v.slice(3);
    return v;
  }
  function nameOk(v) { return /^[\p{L}\p{M}][\p{L}\p{M}‌‍ '’.\-]{0,59}$/u.test(String(v)); }
  /** {ok, value, code} (Address_Schemes::check_value). */
  function checkValue(def, value, words) {
    var v = normalize(def, value);
    if (v === '') return { ok: true, value: '', code: '' };
    if (/[\x00-\x1F\x7F]/.test(v)) return { ok: false, value: v, code: 'format' };
    var n = len(v);
    if ((def.mn !== undefined && def.mn !== '' && n < Number(def.mn)) || (def.mx !== undefined && def.mx !== '' && n > Number(def.mx))) return { ok: false, value: v, code: 'length' };
    if (def.rx) {
      var ok = rx(def.rx).test(v);
      if (!ok && def.w) ok = (words || data().words).indexOf(v.toLowerCase()) !== -1;
      if (!ok) return { ok: !!def.opt, value: v, code: def.opt ? 'hint' : 'format' };
    }
    return { ok: true, value: v, code: '' };
  }

  /* ---------------- display (P22) ---------------- */

  function sep(locale) {
    var l = String(locale || (W.efb_var && W.efb_var.wp_lan) || (typeof document !== 'undefined' && document.documentElement.lang) || 'en').slice(0, 2).toLowerCase();
    return ['fa', 'ar', 'ur', 'ps', 'ku'].indexOf(l) >= 0 ? '، ' : (['ja', 'zh'].indexOf(l) >= 0 ? '、' : ', ');
  }
  function nameText(parts, format) {
    var order = (format && data().names[format]) || ['prefix', 'first', 'full', 'middle', 'father', 'grandfather', 'last', 'family'];
    var out = [];
    order.forEach(function (p) { var v = parts && parts[p] != null ? String(parts[p]).trim() : ''; if (v) out.push(v); });
    return out.join(' ');
  }
  function addressText(scheme, parts, locale, multiline, labels) {
    parts = parts || {}; labels = labels || {};
    var s = data().schemes[scheme];
    var lines = s && s.disp ? s.disp : [Object.keys(parts)];
    var sp = sep(locale), done = {}, out = [];
    lines.forEach(function (line) {
      var items = [];
      line.forEach(function (tok) {
        var lab = tok.charAt(0) === '#';
        tok = tok.replace(/^#/, '');
        var txt = '', pend = '';
        tok.split(/([+~])/).forEach(function (x) {
          if (x === '+' || x === '~') { pend = x === '+' ? ' ' : '-'; return; }
          done[x] = true;
          var v = parts[x] != null ? String(parts[x]).trim() : '';
          if (!v) return;
          txt += (txt ? pend : '') + v;
          pend = '';
        });
        if (!txt) return;
        if (lab && labels[tok] && String(labels[tok]).trim()) txt = String(labels[tok]).trim() + ' ' + txt;
        items.push(txt);
      });
      if (items.length) out.push(items.join(sp));
    });
    Object.keys(parts).forEach(function (p) { var v = String(parts[p] == null ? '' : parts[p]).trim(); if (!done[p] && v) out.push(v); });
    return out.join(multiline ? '\n' : sp);
  }
  /** The groups of an answer's rows (Address_Schemes::groups). */
  function groups(rows, labels) {
    var map = {}, order = [];
    (rows || []).forEach(function (r) {
      if (!r || !r.grp || !r.part || !r.cmp) return;
      var g = String(r.grp);
      if (!map[g]) { map[g] = { grp: g, kind: String(r.cmp), set: r.sch ? String(r.sch) : '', parts: {}, labels: {}, full: '' }; order.push(g); }
      map[g].parts[r.part] = r.value == null ? '' : String(r.value).replace(/@efb@dq#/g, '"');
      map[g].labels[r.part] = labels && labels[r.id_] ? labels[r.id_] : (r.name || '');
      if (r.na_full) map[g].full = String(r.na_full);
    });
    return order.map(function (g) { return map[g]; });
  }
  function groupText(g, locale, multiline) {
    if (!g) return '';
    if (g.kind === 'name') return nameText(g.parts, g.set);
    if (!multiline && !locale && g.full) return g.full;
    return addressText(g.set, g.parts, locale, multiline, g.labels);
  }

  /* ---------------- the form ---------------- */

  function T(k, fb) { var t = (W.efb_var && W.efb_var.text) || (W.ajax_object_efm && W.ajax_object_efm.text) || {}; return t[k] || fb; }
  function defOf(el) {
    var d = el && el.dataset ? el.dataset : {};
    return { n: d.naN === '1', nm: d.naNm || '', fmt: d.naFmt || '', rx: d.naRx || '', mn: d.naMn, mx: d.naMx, w: d.naW === '1', opt: d.naOpt === '1', ltr: el && el.getAttribute && el.getAttribute('dir') === 'ltr' };
  }
  /** The field's name for a sentence, isolated (FSI … PDI) so a Persian label keeps its order in an English message. */
  function labelOf(row, el) { return '\u2068' + ((row && row.name) || (el && el.getAttribute && el.getAttribute('aria-label')) || '') + '\u2069'; }
  /**
   * A part's value while the visitor fills the form (core-efb.js, after the type check). Returns {ok, value, msg}:
   * the value to keep (digits Latin, the national shape) or the message to show.
   */
  function check(el, row, value) {
    if (!row || !row.cmp_grp || !el || !el.dataset) return { ok: true, value: value };
    var d = el.dataset;
    if (d.namePart) {
      var v = String(value).replace(/\s+/g, ' ').trim();
      if (d.naStrict === '1' && v && !nameOk(v)) return { ok: false, value: v, msg: T('nmInvalidX', '%s can only have letters, spaces, hyphens, apostrophes and dots.').replace('%s', labelOf(row, el)) };
      return { ok: true, value: v };
    }
    if (!d.addrPart || el.tagName === 'SELECT') return { ok: true, value: value };
    if (row.type === 'cityList') { // a typed city (the list could not load)
      var c = String(value).trim();
      return /[\x00-\x1F\x7F\d<>]/.test(c) || len(c) > 100 ? { ok: false, value: c, msg: T('addrInvalidX', '%s is not valid for this address format.').replace('%s', labelOf(row, el)) } : { ok: true, value: c };
    }
    var r = checkValue(defOf(el), value);
    if (r.ok) {
      if (r.value !== el.value && (typeof document === 'undefined' || document.activeElement !== el)) el.value = r.value;
      return { ok: true, value: r.value };
    }
    return { ok: false, value: r.value, msg: (d.addrPart === 'postal' ? T('addrPostalX', 'Enter a valid postal code for %s.') : T('addrInvalidX', '%s is not valid for this address format.')).replace('%s', labelOf(row, el)) };
  }
  function scope(form_id) { return (typeof document !== 'undefined' && (document.querySelector('[data-formid="' + form_id + '"].body_efb') || document.getElementById('body_efb_' + form_id))) || document; }
  /** The part elements of a group: {part: element}. */
  function parts(form_id, grp) {
    var out = {};
    var root = typeof document !== 'undefined' ? (form_id ? scope(form_id) : document) : null;
    if (!root) return out;
    Array.prototype.forEach.call(root.querySelectorAll('[data-grp="' + String(grp).replace(/"/g, '') + '"]'), function (el) {
      var p = el.dataset.addrPart || el.dataset.namePart;
      if (p && !el.hidden && !(el.classList && el.classList.contains('d-none') && el.tagName === 'SELECT' && el.dataset.naManual)) out[p] = el;
    });
    return out;
  }
  /** Saudi "short address or full address": a part other than the short code is not required while a valid short code is there. */
  function notRequired(row, form_id) {
    if (!row || !row.cmp_grp || row.addr_scheme !== 'sa' || row.addr_part === 'short_code') return false;
    var root = typeof document !== 'undefined' ? scope(form_id) : null;
    if (!root || !root.querySelector('[data-grp="' + String(row.cmp_grp).replace(/"/g, '') + '"][data-na-mode="sa_short_or_full"]')) return false;
    var el = parts(form_id, row.cmp_grp).short_code;
    return !!(el && /^[A-Z]{4}\d{4}$/.test(normalize({ nm: 'su' }, el.value)));
  }
  /**
   * The city list of a province could not load (offline, the list service blocked): a text box takes its place so the
   * form is never stuck. Called by pro_els-efb.js. Returns true when it handled the select.
   */
  function cityFailed(select) {
    if (!select || !select.dataset || select.dataset.cascade !== '1' || typeof document === 'undefined') return false;
    var vid = select.dataset.vid, form_id = select.dataset.formid;
    select.classList.remove('is-loading');
    select.removeAttribute('aria-busy');
    if (document.getElementById(vid + '_')) return true;
    var box = document.createElement('input');
    box.type = 'text';
    box.id = vid + '_';
    box.className = select.className.replace(/\bform-select\b/, 'form-control input-efb');
    box.dataset.vid = vid;
    box.dataset.formid = form_id;
    box.dataset.grp = select.dataset.grp || '';
    box.dataset.addrPart = 'city';
    box.setAttribute('autocomplete', 'address-level2');
    box.setAttribute('dir', 'auto');
    box.setAttribute('aria-label', select.getAttribute('aria-label') || '');
    box.setAttribute('placeholder', T('addrCityManual', 'Type the city name'));
    if (select.getAttribute('aria-required')) box.setAttribute('aria-required', select.getAttribute('aria-required'));
    var db = select.getAttribute('aria-describedby'); if (db) box.setAttribute('aria-describedby', db);
    select.dataset.naManual = '1';
    select.disabled = true;
    select.classList.add('d-none');
    select.setAttribute('aria-hidden', 'true');
    select.parentNode.insertBefore(box, select.nextSibling);
    box.addEventListener('change', function () { if (typeof W.handle_change_event_efb_v4 === 'function') W.handle_change_event_efb_v4(box, form_id); });
    var lab = document.getElementById(vid + '_labG'); if (lab) lab.setAttribute('for', box.id);
    return true;
  }
  /** Before a city list loads: busy, and the list stays closed until the province is chosen. */
  function cityLoading(select, busy) {
    if (!select) return;
    if (busy) select.setAttribute('aria-busy', 'true'); else select.removeAttribute('aria-busy');
  }
  function optionMatch(sel, v) {
    var want = String(v).trim().toLowerCase();
    if (!want) return null;
    var best = null;
    Array.prototype.some.call(sel.options, function (o) {
      if (o.disabled && !o.value) return false;
      var t = String(o.textContent || o.value).trim().toLowerCase();
      if (t === want || String(o.value).toLowerCase() === want) { best = o; return true; }
      if (!best && (t.indexOf(want) !== -1 || want.indexOf(t) !== -1) && t.length > 1) best = o;
      return false;
    });
    return best;
  }
  function fire(el) { try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) { /* old browsers */ } }
  /**
   * Fill a group's parts ({part: value}); a select takes the option whose native or English name matches; the city of
   * a cascading list waits for the province's cities. Returns a Promise of the parts it filled. (AdnADR uses it.)
   */
  function fill(form_id, grp, map) {
    var els = parts(form_id, grp), done = [];
    var setText = function (p) { var el = els[p]; if (!el || el.tagName === 'SELECT' || map[p] == null || map[p] === '') return; el.value = String(map[p]); fire(el); done.push(p); };
    var setSel = function (p) { var el = els[p]; if (!el || el.tagName !== 'SELECT' || map[p] == null) return false; var o = optionMatch(el, map[p]); if (!o) return false; el.value = o.value; o.selected = true; fire(el); done.push(p); return true; };
    Object.keys(map).forEach(function (p) { if (p !== 'city' && p !== 'state' && p !== 'country') setText(p); });
    setSel('country');
    var stateSet = setSel('state');
    return new Promise(function (resolve) {
      var city = els.city;
      if (!city || map.city == null || map.city === '') return resolve(done);
      if (city.tagName !== 'SELECT') { setText('city'); return resolve(done); }
      var tries = 0;
      (function wait() {
        var c = parts(form_id, grp).city;
        if (c && c.tagName !== 'SELECT') { els.city = c; setText('city'); return resolve(done); }
        if (setSel('city') || !stateSet || ++tries > 40) return resolve(done);
        setTimeout(wait, 150);
      })();
    });
  }

  var api = {
    version: 1,
    latinDigits: latin, normalize: normalize, checkValue: checkValue, nameOk: nameOk,
    sep: sep, nameText: nameText, addressText: addressText, groups: groups, groupText: groupText,
    check: check, notRequired: notRequired, cityFailed: cityFailed, cityLoading: cityLoading, parts: parts, fill: fill,
  };
  W.EFBNameAddr = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
