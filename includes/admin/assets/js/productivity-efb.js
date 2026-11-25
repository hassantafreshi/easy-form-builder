/*!
 * Easy Form Builder - builder productivity (F15, docs/feature-gaps/F15-builder-productivity.fa.md).
 *
 *  A  field search in the palette (§4.5) and the keyboard shortcuts with their help dialog (§4.4);
 *  B  undo / redo around EfbHistory (history-efb.js): toolbar buttons, the gesture wiring, the
 *     labels of the builder's entry points (efbHistHintEfb) and efb_history_apply_efb();
 *  C  form versions (revisions) in the builder: list, preview, compare, restore;
 *  D  export / import of forms (the forms list and the builder).
 *
 * The pure parts (text normalization, palette matching, the shortcut table, the form comparison)
 * have no DOM and are exported for Node tests (module.exports); in a page they are
 * window.EfbProductivity. Everything that touches the page runs only in a browser, after
 * admin-efb.js, val-efb.js and new-efb.js. No build step; ES2017.
 *
 * Texts come from efb_var.text (English until the owner's translation pass); values put into a
 * sentence (a name, a date, a number) are isolated with <bdi> so a Persian or Arabic sentence
 * keeps its order. Nothing typed by a person is ever written with innerHTML.
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module === 'object' && module.exports) { module.exports = api; } else { root.EfbProductivity = api; }
})(typeof self !== 'undefined' ? self : this, function (root) {
  'use strict';

  /* ================================================================== pure */

  /** Text for matching: lower case, no diacritics / harakat / tatweel / half-space, one form of
   *  Persian-Arabic letters, Persian and Arabic-Indic digits as ASCII, separators as one space. */
  function normalize(s) {
    s = s === null || s === undefined ? '' : String(s);
    try { s = s.normalize('NFKD'); } catch (e) { /* old engines */ }
    s = s.toLowerCase()
      .replace(/[̀-ͯ]/g, '')
      .replace(/[ً-ٰٟۖ-ۭ]/g, '')
      .replace(/ـ/g, '')
      .replace(/[​-‏­⁦-⁩‪-‮]/g, '')
      .replace(/[يى]/g, 'ی')        // ي ى → ی
      .replace(/ك/g, 'ک')                // ك → ک
      .replace(/[ةۀ]/g, 'ه')        // ة ۀ → ه
      .replace(/[أإآٱ]/g, 'ا') // أ إ آ ٱ → ا
      .replace(/ؤ/g, 'و')                // ؤ → و
      .replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); })
      .replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); })
      .replace(/[\s\-_.,،؛;:\/|()]+/g, ' ')
      .trim();
    return s;
  }

  /** The fixed English names and synonyms of the palette (never translated: a Persian or Arabic
   *  admin who types "phone" still finds the mobile field). Translated synonyms come from the
   *  phrases palette_syn_<id>. */
  var PALETTE_EN = {
    text: 'text, single line, short answer, input', name: 'name, full name, first name, last name',
    password: 'password, secret, pin', email: 'email, e-mail, mail', number: 'number, numeric, digits, quantity, amount',
    textarea: 'long text, paragraph, multi line, textarea, comment, message', steps: 'step, page, page break, wizard',
    checkbox: 'checkbox, check boxes, multiple choice, tick', radio: 'radio, single choice, option, multiple choice',
    select: 'dropdown, select, drop down list, menu', multiselect: 'multi select, multiple select, dropdown, tags',
    tel: 'phone, telephone, tel', mobile: 'mobile, phone, cell, cellphone, mobile number, sms',
    range: 'range, slider, scale', date: 'date, day, calendar, birthday', advdate: 'advanced date, date range, time, calendar, booking',
    file: 'file, upload, attachment, document', dadfile: 'drag and drop, file, upload, dropzone, attachment',
    audio_recorder: 'audio, voice, record, microphone', video_recorder: 'video, camera, record, webcam',
    screen_recorder: 'screen, record, screencast, capture', address: 'address, street, city, zip, postal code, location',
    payCheckbox: 'payment checkbox, price, products', payRadio: 'payment radio, price, product', prcfld: 'price, amount, product price',
    ttlprc: 'total, sum, total price, cart total', maps: 'map, location, gps, place, pin', stripe: 'stripe, card, credit card, payment',
    paypal: 'paypal, payment', url: 'url, link, website, web address', conturyList: 'country, nation, countries',
    stateProvince: 'state, province, region', cityList: 'city, town', esign: 'signature, sign, e-signature',
    'switch': 'switch, toggle, on off', chlCheckBox: 'checklist, quantity, product list', heading: 'heading, title, header',
    section: 'section, group, divider, collapsible', color: 'color, colour, picker', rating: 'rating, stars, review, score',
    yesNo: 'yes no, two buttons, confirm', link: 'link, button, url', html: 'html, code, custom content, embed',
    pointr5: 'score 5, points, likert', pointr10: 'score 10, nps, net promoter, points', imgRadio: 'image choice, picture, image radio',
    pdate: 'persian date, jalali, shamsi, calendar', ardate: 'arabic date, hijri, calendar', trmCheckbox: 'terms, consent, agree, gdpr, privacy',
    ranking: 'ranking, order, sort, priority', calc: 'calculation, formula, calculator, total, math', repeater: 'repeater, repeat, rows, add more',
    hiddenField: 'hidden, hidden field, utm, tracking', table_matrix: 'matrix, table, grid, likert, survey', persiaPay: 'persiapay, zarinpal, payment',
  };

  /** Everything a palette tile can be found by: its translated name, its id, the English list and
   *  the translated synonyms (text = efb_var.text). */
  function paletteHaystack(id, name, text, tags) {
    var t = text || {};
    var parts = [name || '', id || '', PALETTE_EN[id] || '', t['palette_syn_' + id] || ''];
    if (tags && / payment\b|^payment\b/.test(' ' + tags)) parts.push('payment, gateway');
    return normalize(parts.join(' | '));
  }

  /** Every word of the query must be found in the tile's haystack (a prefix of a name is enough). */
  function paletteMatch(query, id, name, text, tags) {
    var q = normalize(query);
    if (q === '') return true;
    var hay = paletteHaystack(id, name, text, tags);
    var words = q.split(' ');
    for (var i = 0; i < words.length; i++) if (words[i] && hay.indexOf(words[i]) === -1) return false;
    return true;
  }

  var TEXT_INPUT_TYPES = ['text', 'search', 'email', 'number', 'password', 'tel', 'url', 'date', 'datetime-local', 'month', 'time', 'week', ''];

  /** Whether typing goes into this element (then only the standard shortcuts are ours). */
  function isTextTarget(el) {
    if (!el || el.nodeType !== 1) return false;
    var tag = String(el.tagName || '').toUpperCase();
    if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
    if (tag === 'INPUT') return TEXT_INPUT_TYPES.indexOf(String(el.type || '').toLowerCase()) !== -1;
    if (el.isContentEditable) return true;
    var role = el.getAttribute ? el.getAttribute('role') : '';
    return role === 'textbox' || role === 'combobox' || role === 'searchbox';
  }

  /**
   * The builder action of a key press, matched on KeyboardEvent.code (the physical key: a Persian or
   * Arabic layout types ظ / ئ on KeyZ). ctx: {inText, modal, singleKeys}. null = not ours.
   */
  function shortcutAction(ev, ctx) {
    ctx = ctx || {};
    var code = String(ev.code || '');
    var mod = !!(ev.ctrlKey || ev.metaKey);
    var alt = !!ev.altKey, shift = !!ev.shiftKey;
    if (code === 'KeyS' && mod && !alt && !shift) return 'save';
    if (code === 'Escape' && !mod && !alt) return 'close';
    if (ctx.modal || ctx.inText) return null;
    if (code === 'KeyZ' && mod && !alt) return shift ? 'redo' : 'undo';
    if (code === 'KeyY' && ev.ctrlKey && !alt && !shift) return 'redo';
    if (code === 'KeyD' && mod && !alt && !shift) return 'duplicate';
    if ((code === 'ArrowUp' || code === 'ArrowDown') && alt && !mod && !shift) return code === 'ArrowUp' ? 'moveUp' : 'moveDown';
    if (code === 'KeyP' && alt && !mod && !shift) return 'preview';
    if ((code === 'Delete' || code === 'Backspace') && !mod && !alt && !shift) return 'delete';
    if (code === 'Slash' && !mod && !alt && ctx.singleKeys !== false) return shift ? 'help' : 'search';
    return null;
  }

  /** The help dialog's rows: action, phrase key, English, keys. mac: ⌘ instead of Ctrl. */
  function shortcutRows(mac) {
    var c = mac ? '⌘' : 'Ctrl';
    return [
      ['save', 'scSave', 'Save', [[c, 'S']]],
      ['undo', 'scUndo', 'Undo', [[c, 'Z']]],
      ['redo', 'scRedo', 'Redo', [[c, 'Shift', 'Z'], ['Ctrl', 'Y']]],
      ['search', 'scSearch', 'Search fields', [['/']]],
      ['delete', 'scDelete', 'Delete selected field', [['Delete'], ['Backspace']]],
      ['duplicate', 'scDuplicate', 'Duplicate selected field', [[c, 'D']]],
      ['move', 'scMove', 'Move selected field', [['Alt', '↑'], ['Alt', '↓']]],
      ['close', 'scClose', 'Close panel', [['Esc']]],
      ['preview', 'scPreview', 'Preview', [['Alt', 'P']]],
      ['help', 'scHelp', 'Show shortcuts', [['?']]],
    ];
  }

  var pure = {
    normalize: normalize, PALETTE_EN: PALETTE_EN, paletteHaystack: paletteHaystack, paletteMatch: paletteMatch,
    isTextTarget: isTextTarget, shortcutAction: shortcutAction, shortcutRows: shortcutRows,
  };

  /* Row-0 keys the add-ons keep outside the form (texts, numbers, tokens): never compared, never put in a version. */
  var SENSITIVE_ROW0 = ['sms_msg_new_noti', 'sms_msg_responsed_noti', 'sms_msg_recived_usr', 'sms_msg_recived_user', 'sms_admins_phone_no', 'telegram_msg_new_noti', 'telegram_msg_responsed_noti', 'telegram_msg_recived_usr', 'telegram_msg_recived_user', 'telegram_bot_token', 'telegram_admin_chat_ids'];
  function diffFormsImpl(oldRows, nowRows) {
    var isField = function (r, i) { return i > 0 && r && r.type !== 'option' && r.type !== 'r_matrix'; };
    var byId = function (list) { var m = {}; (list || []).forEach(function (r, i) { if (isField(r, i)) m[String(r.id_)] = r; }); return m; };
    var opts = function (list, id) { return JSON.stringify((list || []).filter(function (r) { return r && (r.type === 'option' || r.type === 'r_matrix') && String(r.parent) === id; }).map(function (r) { return [r.id_, r.value, r.price, r.id_op]; })); };
    var a = byId(oldRows), b = byId(nowRows);
    var out = { onlyOld: [], onlyNow: [], changed: [], settings: [] };
    Object.keys(a).forEach(function (id) {
      if (!b[id]) { out.onlyOld.push({ id: id, name: a[id].name || a[id].type, type: a[id].type }); return; }
      var keys = {};
      Object.keys(a[id]).concat(Object.keys(b[id])).forEach(function (k) { if (k !== 'amount' && JSON.stringify(a[id][k]) !== JSON.stringify(b[id][k])) keys[k] = 1; });
      if (opts(oldRows, id) !== opts(nowRows, id)) keys.options = 1;
      if (Object.keys(keys).length) out.changed.push({ id: id, name: b[id].name || b[id].type, type: b[id].type, keys: Object.keys(keys) });
    });
    Object.keys(b).forEach(function (id) { if (!a[id]) out.onlyNow.push({ id: id, name: b[id].name || b[id].type, type: b[id].type }); });
    var r0a = (oldRows && oldRows[0]) || {}, r0b = (nowRows && nowRows[0]) || {};
    Object.keys(r0a).concat(Object.keys(r0b)).forEach(function (k) { if (SENSITIVE_ROW0.indexOf(k) === -1 && out.settings.indexOf(k) === -1 && JSON.stringify(r0a[k]) !== JSON.stringify(r0b[k])) out.settings.push(k); });
    return out;
  }  /** What restoring a version would change (pure; also used by the Versions dialog). */
  pure.diffForms = function (oldRows, nowRows) { return diffFormsImpl(oldRows, nowRows); };

  if (typeof window === 'undefined' || typeof document === 'undefined') return pure;

  /* ================================================================== page helpers */

  var W = window;
  function T(k, d) { return (W.efb_var && W.efb_var.text && W.efb_var.text[k]) || d; }
  function rtl() { return !!(W.efb_var && Number(W.efb_var.rtl) === 1) || document.documentElement.dir === 'rtl'; }
  function locale() { var l = (W.efb_var && (W.efb_var.wp_lan || W.efb_var.language)) || document.documentElement.lang || 'en'; return String(l).replace('_', '-'); }
  function num(n) { try { return Number(n).toLocaleString(locale()); } catch (e) { return String(n); } }
  function el(tag, attrs, kids) {
    var e = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      var v = attrs[k];
      if (v === null || v === undefined || v === false) return;
      if (k === 'text') e.textContent = String(v);
      else if (k === 'class') e.className = v;
      else if (k.slice(0, 2) === 'on' && typeof v === 'function') e.addEventListener(k.slice(2), v);
      else e.setAttribute(k, v === true ? '' : String(v));
    });
    (kids || []).forEach(function (c) { if (c !== null && c !== undefined) e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return e;
  }
  /** A sentence with %s / %1$s … filled with values isolated in <bdi> (mixed scripts keep their order). */
  function sentence(tpl, values, cls) {
    var out = el('span', { class: 'efb efb-pd-sent' + (cls ? ' ' + cls : '') });
    var i = 0;
    String(tpl).split(/(%(?:\d+\$)?s)/).forEach(function (part) {
      var m = /^%(?:(\d+)\$)?s$/.exec(part);
      if (m) {
        var v = values[m[1] ? Number(m[1]) - 1 : i++];
        out.appendChild(el('bdi', { text: v === undefined ? '' : v }));
      } else if (part) out.appendChild(document.createTextNode(part));
    });
    return out;
  }
  function isMac() { return /Mac|iPhone|iPad/.test((navigator.platform || '') + ' ' + (navigator.userAgent || '')); }
  function ajax(data) {
    var url = W.ajaxurl || (W.efb_var && W.efb_var.ajax_url) || '/wp-admin/admin-ajax.php';
    var nonce = (typeof W._efb_nonce_ !== 'undefined' && W._efb_nonce_) || (W.efb_var && W.efb_var.nonce) || (typeof W._efb_core_nonce_ !== 'undefined' ? W._efb_core_nonce_ : '');
    var body = new URLSearchParams();
    body.set('nonce', nonce);
    Object.keys(data).forEach(function (k) { body.set(k, typeof data[k] === 'object' ? JSON.stringify(data[k]) : String(data[k])); });
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
      .then(function (r) { return r.json(); })
      .then(function (j) { return j && j.data !== undefined ? j.data : j; });
  }
  function toast(msg, kind) {
    if (typeof W.alert_message_efb === 'function') { W.alert_message_efb('', msg, 4, kind || 'info'); return; }
  }
  function builderOn() { return !!document.getElementById('dropZoneEFB') && !!document.getElementById('pCreatorEfb'); }

  /* ---------------------------------------------------------------- dialog */

  var openDialogs = [];
  /** An accessible modal dialog of its own (role=dialog, aria-modal, labelled, focus kept inside,
   *  Esc closes, focus goes back to the opener). opts: {id, title, body (Node), foot (Node[]), wide, onClose}. */
  function dialog(opts) {
    var prev = document.activeElement;
    var titleId = (opts.id || 'efbPdDlg') + 'Title';
    var box = el('div', { class: 'efb efb-pd-dlg' + (opts.wide ? ' efb-pd-wide' : ''), role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId, id: opts.id || null, dir: rtl() ? 'rtl' : 'ltr', tabindex: '-1' });
    var head = el('div', { class: 'efb efb-pd-head' }, [
      el('h2', { class: 'efb efb-pd-title', id: titleId, text: opts.title }),
      el('button', { type: 'button', class: 'efb efb-pd-x', 'aria-label': T('close', 'Close'), 'data-pd': 'close' }, [el('i', { class: 'efb bi-x-lg', 'aria-hidden': 'true' })]),
    ]);
    var bodyBox = el('div', { class: 'efb efb-pd-body' }, [opts.body]);
    box.appendChild(head);
    box.appendChild(bodyBox);
    if (opts.foot && opts.foot.length) box.appendChild(el('div', { class: 'efb efb-pd-foot' }, opts.foot));
    var back = el('div', { class: 'efb efb-pd-back' }, [box]);
    var api = {
      el: box, body: bodyBox,
      close: function () {
        if (!back.parentNode) return;
        back.parentNode.removeChild(back);
        openDialogs = openDialogs.filter(function (d) { return d !== api; });
        if (typeof opts.onClose === 'function') { try { opts.onClose(); } catch (e) { /* the caller's */ } }
        if (prev && typeof prev.focus === 'function' && document.contains(prev)) { try { prev.focus(); } catch (e) { /* gone */ } }
      },
    };
    back.addEventListener('mousedown', function (e) { if (e.target === back) api.close(); });
    box.addEventListener('click', function (e) { var t = e.target.closest && e.target.closest('[data-pd="close"]'); if (t) api.close(); });
    box.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); api.close(); return; }
      if (e.key !== 'Tab') return;
      var f = Array.prototype.filter.call(box.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'), function (x) { return x.offsetParent !== null || x === document.activeElement; });
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    document.body.appendChild(back);
    openDialogs.push(api);
    setTimeout(function () {
      var f = box.querySelector('[data-pd-focus]') || box.querySelector('button:not([data-pd="close"]),input,select,textarea') || box;
      try { f.focus(); } catch (e) { /* hidden */ }
    }, 30);
    return api;
  }

  /* ================================================================== A: palette search */

  var searchQuery = '';
  var paletteObserver = null;

  function tiles() { return Array.prototype.slice.call(document.querySelectorAll('#efbPaletteTilesEfb .efb.tag')); }
  function tileVisible(t) { return !t.classList.contains('d-none') && !t.classList.contains('efb-search-miss'); }
  function tileName(t) { var s = t.querySelector('button span'); return s ? s.textContent : (t.getAttribute('title') || ''); }

  /** Filters the tiles: the query and the category both apply (a tile shows only when it passes
   *  both); efb-search-miss is separate from the category's d-none so neither undoes the other. */
  function paletteSearch(q) {
    searchQuery = typeof q === 'string' ? q : '';
    var text = (W.efb_var && W.efb_var.text) || {};
    var shown = 0;
    tiles().forEach(function (t) {
      var ok = paletteMatch(searchQuery, t.id, tileName(t), text, t.className);
      t.classList.toggle('efb-search-miss', !ok);
      if (tileVisible(t)) shown++;
    });
    var live = document.getElementById('efbPaletteSearchLive');
    if (live) {
      live.textContent = '';
      if (normalize(searchQuery) !== '') {
        live.appendChild(shown === 0
          ? sentence(T('noFieldFound', 'No field matches "%s"'), [searchQuery])
          : sentence(T('nFieldsFound', '%s fields found'), [num(shown)]));
      }
      live.classList.toggle('efb-pd-miss', normalize(searchQuery) !== '' && shown === 0);
    }
    if (typeof efbScheduleWorkspaceFitEfb === 'function') efbScheduleWorkspaceFitEfb(); // eslint-disable-line no-undef
    return shown;
  }

  /** Enter in the search box: the first tile that can be added is added (the same route as a
   *  click on mobile, fun_efb_add_el); a locked tile shows its own notice. */
  function paletteAddFirst() {
    var vis = tiles().filter(tileVisible);
    var usable = vis.filter(function (t) { return t.getAttribute('draggable') === 'true'; });
    if (usable.length && typeof fun_efb_add_el === 'function') { fun_efb_add_el(usable[0].id); return usable[0].id; } // eslint-disable-line no-undef
    if (vis.length) { var b = vis[0].querySelector('button'); if (b) b.click(); return null; }
    return null;
  }

  function mountPaletteSearch() {
    var box = document.getElementById('efbPaletteSearch');
    if (!box || box.dataset.efbMounted === '1') return;
    box.dataset.efbMounted = '1';
    box.addEventListener('input', function () { paletteSearch(box.value); });
    box.addEventListener('search', function () { paletteSearch(box.value); });
    box.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); paletteAddFirst(); }
      else if (e.key === 'Escape') {
        e.preventDefault(); e.stopPropagation();
        if (box.value !== '') { box.value = ''; paletteSearch(''); } else box.blur();
      }
    });
    // The tiles are redrawn by a plan change (plan-gate-efb.js): the query applies to the new ones.
    var holder = document.getElementById('efbPaletteTilesEfb');
    if (holder && W.MutationObserver) {
      if (paletteObserver) paletteObserver.disconnect();
      paletteObserver = new MutationObserver(function () { if (normalize(searchQuery) !== '') paletteSearch(searchQuery); });
      paletteObserver.observe(holder, { childList: true });
    }
    if (searchQuery) { box.value = searchQuery; paletteSearch(searchQuery); }
  }

  /* ================================================================== A: shortcuts */

  /* efb_var is frozen by admin-efb.js (deepFreeze_efb_admin): a preference changed in the page lives here. */
  var prefs = {};
  function pref(name) {
    if (Object.prototype.hasOwnProperty.call(prefs, name)) return prefs[name];
    var p = W.efb_var && W.efb_var.builder_prefs;
    return p && p[name] !== undefined ? String(p[name]) : '1';
  }
  function singleKeysOn() { return pref('single_key_shortcuts') !== '0'; }
  function modalOpen() {
    if (openDialogs.length) return true;
    var m = document.getElementById('settingModalEfb');
    if (m && m.classList.contains('show')) return true;
    if (document.body.classList.contains('efb-dlg-open')) return true;
    return !!document.querySelector('.modal.show');
  }
  function panelOpen() { var b = document.getElementById('sideBoxEfb'); return !!(b && b.classList.contains('show')); }
  /* The builder's state lives in script-scope globals (let valj_efb, let activeEl_efb in new-efb.js):
     they are read by name, never through window. */
  function rows() { return typeof valj_efb !== 'undefined' && Array.isArray(valj_efb) ? valj_efb : null; } // eslint-disable-line no-undef
  function selectedRow() {
    var id = activeId();
    var list = rows();
    if (!id || !list) return null;
    var i = list.findIndex(function (r, n) { return n > 0 && r && (r.dataId === id || r.id_ === id); });
    if (i < 1) return null;
    var r = list[i];
    if (!r || r.type === 'option' || r.type === 'r_matrix') return null;
    return { row: r, index: i };
  }
  function activeId() {
    try { return typeof activeEl_efb !== 'undefined' ? activeEl_efb : 0; } catch (e) { return 0; } // eslint-disable-line no-undef
  }

  /** Alt+↑ / Alt+↓: the selected field changes places with its neighbour in the same step (the
   *  canvas order, then the builder's own sort_obj_el_efb_(), as a drag does). */
  function moveSelected(dir) {
    var sel = selectedRow();
    if (!sel || sel.row.type === 'step') return false;
    var node = document.getElementById(sel.row.id_);
    if (!node || !node.classList.contains('efbField')) return false;
    var sib = dir < 0 ? node.previousElementSibling : node.nextElementSibling;
    while (sib && !sib.classList.contains('efbField')) sib = dir < 0 ? sib.previousElementSibling : sib.nextElementSibling;
    if (!sib || sib.classList.contains('stepNo') || sib.classList.contains('stepNavEfb')) return false;
    if (typeof W.efbHistHintEfb === 'function') W.efbHistHintEfb(String(T('hMoveField', 'Move %s')).replace('%s', sel.row.name || sel.row.type), 'move|' + sel.row.id_);
    if (dir < 0) sib.parentNode.insertBefore(node, sib); else sib.parentNode.insertBefore(sib, node);
    if (typeof sort_obj_el_efb_ === 'function') sort_obj_el_efb_(); // eslint-disable-line no-undef
    if (typeof W.efbHistCommitEfb === 'function') W.efbHistCommitEfb();
    try { node.scrollIntoView({ block: 'nearest' }); } catch (e) { /* old browsers */ }
    return true;
  }

  function runAction(action, ev) {
    var sel;
    switch (action) {
      case 'save': {
        // A text box commits on change: let it fire, and the builder's timer run, before saving.
        var a = document.activeElement;
        if (isTextTarget(a) && a.closest && a.closest('#sideBoxEfb, #sideMenuConEfb, #pCreatorEfb')) { try { a.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) { /* old */ } }
        var wait = (rows() ? rows().length : 0) * 6 + 150;
        setTimeout(function () { if (typeof saveFormEfb === 'function') saveFormEfb(1); }, wait); // eslint-disable-line no-undef
        return true;
      }
      case 'close':
        if (openDialogs.length) { openDialogs[openDialogs.length - 1].close(); return true; }
        if (modalOpen()) return false; // the builder's own dialog handles Esc
        if (panelOpen() && typeof sideMenuEfb === 'function') {
          sideMenuEfb(0); // eslint-disable-line no-undef
          // The focus must not stay in a box of the closed panel (the next keys would be typed into it).
          var f = document.activeElement;
          if (f && f.closest && f.closest('#sideBoxEfb, #sideMenuConEfb')) { try { f.blur(); } catch (e) { /* gone */ } }
          return true;
        }
        if (activeId() && typeof deactive_element_efb === 'function') { deactive_element_efb(); return true; } // eslint-disable-line no-undef
        return false;
      case 'undo': return typeof W.efbUndoEfb === 'function' ? (W.efbUndoEfb(), true) : false;
      case 'redo': return typeof W.efbRedoEfb === 'function' ? (W.efbRedoEfb(), true) : false;
      case 'search': {
        var s = document.getElementById('efbPaletteSearch');
        if (!s) return false;
        s.focus(); s.select();
        return true;
      }
      case 'help': openShortcutsHelp(); return true;
      case 'preview': if (typeof previewFormEfb === 'function') { previewFormEfb('pc'); return true; } return false; // eslint-disable-line no-undef
      case 'delete':
        sel = selectedRow();
        if (!sel || typeof show_delete_window_efb !== 'function') return false; // eslint-disable-line no-undef
        if (sel.row.type === 'step' && Number(sel.row.step) === 1) return false;
        show_delete_window_efb(sel.row.dataId, sel.index); // eslint-disable-line no-undef
        return true;
      case 'duplicate':
        sel = selectedRow();
        if (!sel || sel.row.type === 'step' || typeof fun_confirm_dup_emsFormBuilder !== 'function') return false; // eslint-disable-line no-undef
        if (!document.getElementById('dupElEFb-' + sel.row.id_) && sel.row.type !== 'repeater') return false;
        fun_confirm_dup_emsFormBuilder(sel.row.id_, 'input'); // eslint-disable-line no-undef
        return true;
      case 'moveUp': return moveSelected(-1);
      case 'moveDown': return moveSelected(1);
    }
    return false;
  }

  function keyHandler(ev) {
    if (!builderOn()) return;
    if (document.getElementById('efb-email-builder')) return; // the e-mail template editor has its own keys
    if (ev.defaultPrevented || ev.isComposing) return;
    var target = ev.target;
    var inText = isTextTarget(target);
    var action = shortcutAction(ev, { inText: inText, modal: modalOpen(), singleKeys: singleKeysOn() });
    if (!action) return;
    if (action === 'close' && target && target.id === 'efbPaletteSearch') return; // the box clears itself
    if (runAction(action, ev)) { ev.preventDefault(); ev.stopPropagation(); }
  }

  function initShortcuts() {
    if (W._efbBuilderKeyHandler) document.removeEventListener('keydown', W._efbBuilderKeyHandler);
    W._efbBuilderKeyHandler = keyHandler;
    document.addEventListener('keydown', W._efbBuilderKeyHandler);
  }

  function kbd(keys) {
    var out = el('span', { class: 'efb efb-pd-keys', dir: 'ltr' });
    keys.forEach(function (k, i) { if (i) out.appendChild(document.createTextNode('+')); out.appendChild(el('kbd', { class: 'efb', text: k })); });
    return out;
  }

  /** The "Keyboard shortcuts" dialog (Shift+/ or the toolbar's keyboard button). */
  function openShortcutsHelp() {
    var rows = shortcutRows(isMac());
    var tbody = el('tbody');
    rows.forEach(function (r) {
      var keys = el('td', { class: 'efb' });
      r[3].forEach(function (k, i) { if (i) keys.appendChild(el('span', { class: 'efb efb-pd-or', text: ' / ' })); keys.appendChild(kbd(k)); });
      tbody.appendChild(el('tr', { 'data-sc': r[0] }, [el('th', { scope: 'row', class: 'efb', text: T(r[1], r[2]) }), keys]));
    });
    var table = el('table', { class: 'efb efb-pd-table' }, [
      el('thead', null, [el('tr', null, [el('th', { scope: 'col', class: 'efb', text: T('scAction', 'Action') }), el('th', { scope: 'col', class: 'efb', text: T('scKeys', 'Keys') })])]),
      tbody,
    ]);
    var cb = el('input', { type: 'checkbox', id: 'efbScSingle', class: 'efb form-check-input' });
    cb.checked = singleKeysOn();
    var status = el('div', { class: 'efb efb-pd-status', role: 'status', 'aria-live': 'polite', id: 'efbScStatus' });
    cb.addEventListener('change', function () {
      var v = cb.checked ? '1' : '0';
      prefs.single_key_shortcuts = v;
      ajax({ action: 'efb_set_builder_pref', pref: 'single_key_shortcuts', value: v }).then(function (d) {
        status.textContent = d && d.success ? T('saved', 'Saved') : ((d && d.m) || T('error', 'Error'));
      }, function () { status.textContent = T('error', 'Error'); });
    });
    var body = el('div', null, [
      el('p', { class: 'efb efb-pd-note', text: T('scNote', 'Shortcuts work while the builder is open and no text box has the focus (Save and Esc work everywhere).') }),
      table,
      el('div', { class: 'efb form-check efb-pd-check' }, [cb, el('label', { for: 'efbScSingle', class: 'efb form-check-label', text: T('scSingleKeys', 'Allow single-key shortcuts (/ and ?)') })]),
      status,
    ]);
    return dialog({ id: 'efbScDlg', title: T('shortcuts', 'Keyboard shortcuts'), body: body, foot: [el('button', { type: 'button', class: 'efb btn btn-primary efb-pd-btn', 'data-pd': 'close', text: T('close', 'Close') })] });
  }

  /* ================================================================== B: undo / redo */

  var hist = null;
  var histUiTimer = null;
  var lastSel = 0;
  var API_LABELS = { setForm: ['hApiSetForm', 'Replace the form'], insertItems: ['hApiInsert', 'Add fields'], removeItems: ['hApiRemove', 'Remove fields'], setFormType: ['hApiType', 'Change the form type'], setFormSettings: ['hApiSettings', 'Change the form settings'] };

  /* amount / step are numbers in the builder (efbNormalizeOrderEfb, what editFormEfb() does before drawing):
     made so before each snapshot, a redrawn state is the same text as the state that was recorded. */
  function readState() {
    var l = rows();
    if (!l) return null;
    try { if (typeof efbNormalizeOrderEfb === 'function') efbNormalizeOrderEfb(l); return JSON.stringify(l); } catch (e) { return null; } // eslint-disable-line no-undef
  }
  function settleMs() { var l = rows(); return Math.max(300, (l ? l.length : 0) * 6 + 150); }
  function maxOrder(list) { var m = 0; list.forEach(function (r) { var a = Number(r && r.amount); if (a > m) m = a; }); return m; }

  /**
   * efb_history_apply_efb(json): puts a state of the form back into the builder (undo, redo, a restored
   * version). editFormEfb() starts from sessionStorage.valj_efb, so that is written first; the
   * builder's globals follow the state; the panel closes and the selected field is selected again
   * when it is still there; the mobile view is kept by editFormEfb() itself.
   */
  function applyState(json, info) {
    var list = JSON.parse(json);
    if (!Array.isArray(list) || !list[0]) return Promise.resolve(false);
    valj_efb = list; // eslint-disable-line no-undef
    try { sessionStorage.setItem('valj_efb', json); } catch (e) { /* storage blocked */ }
    try { form_type_emsFormBuilder = list[0].type; } catch (e) { /* not the builder */ } // eslint-disable-line no-undef
    try { if (list[0].formName) formName_Efb = list[0].formName; } catch (e) { /* not the builder */ } // eslint-disable-line no-undef
    try { step_el_efb = Number(list[0].steps) || 1; amount_el_efb = maxOrder(list); } catch (e) { /* not the builder */ } // eslint-disable-line no-undef
    var sel = activeId();
    if (panelOpen() && typeof sideMenuEfb === 'function') sideMenuEfb(0); // eslint-disable-line no-undef
    try { activeEl_efb = 0; } catch (e) { /* not the builder */ } // eslint-disable-line no-undef
    var drawn = typeof editFormEfb === 'function' ? editFormEfb() : null; // eslint-disable-line no-undef
    return Promise.resolve(drawn).then(function () {
      if (sel) {
        var n = document.querySelector('#dropZoneEFB [data-id="' + String(sel).replace(/["\\]/g, '') + '"]');
        if (n && typeof active_element_efb === 'function') { try { active_element_efb(n); } catch (e) { /* not selectable */ } } // eslint-disable-line no-undef
      }
      if (typeof store_form_efb === 'function') { try { store_form_efb(); } catch (e) { /* autosave off */ } } // eslint-disable-line no-undef
      try { document.dispatchEvent(new CustomEvent('efb:form-changed', { detail: { source: 'history', action: info && info.dir ? info.dir : 'apply', ids: [] } })); } catch (e) { /* old browsers */ }
      return true;
    });
  }

  function ensureHist() {
    if (hist) return hist;
    var H = root.EfbHistory || W.EfbHistory;
    if (!H) return null;
    var cfg = (W.efb_var && W.efb_var.f15) || {};
    hist = H.create({ read: readState, write: applyState, limit: cfg.undo_limit || 50, maxChars: cfg.undo_chars || 20e6, mergeMs: cfg.merge_ms || 1000, settle: settleMs });
    hist.on('change', function () { if (histUiTimer) clearTimeout(histUiTimer); histUiTimer = setTimeout(updateHistUi, 0); });
    return hist;
  }

  function showToast(node) {
    var old = document.getElementById('efbPdToast');
    if (old) old.remove();
    var t = el('div', { id: 'efbPdToast', class: 'efb efb-pd-toast', role: 'status', 'aria-live': 'polite', dir: rtl() ? 'rtl' : 'ltr' }, [node]);
    document.body.appendChild(t);
    setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 3500);
  }

  function updateHistUi() {
    histUiTimer = null;
    var st = hist ? hist.state() : { canUndo: false, canRedo: false, undoLabel: '', redoLabel: '' };
    var mod = isMac() ? '\u2318' : 'Ctrl';
    [['efbUndoBtn', st.canUndo, T('undo', 'Undo'), st.undoLabel, mod + '+Z'], ['efbRedoBtn', st.canRedo, T('redo', 'Redo'), st.redoLabel, mod + '+Shift+Z']].forEach(function (b) {
      var btn = document.getElementById(b[0]);
      if (!btn) return;
      btn.disabled = !b[1];
      btn.setAttribute('aria-disabled', b[1] ? 'false' : 'true');
      var what = b[1] && b[3] ? b[2] + ': ' + b[3] : b[2];
      btn.title = what + ' (' + b[4] + ')';
      btn.setAttribute('aria-label', what + ' (' + b[4] + ')');
    });
    try { document.dispatchEvent(new CustomEvent('efb:history-change', { detail: { canUndo: st.canUndo, canRedo: st.canRedo, undoLabel: st.undoLabel, redoLabel: st.redoLabel, size: st.size } })); } catch (e) { /* old browsers */ }
  }

  function undoRedo(dir) {
    var h = ensureHist();
    if (!h) return null;
    var r = dir === 'undo' ? h.undo() : h.redo();
    if (r) showToast(sentence(dir === 'undo' ? T('undoneX', 'Undone: %s') : T('redoneX', 'Redone: %s'), [r.label || T('hEdit', 'Change')]));
    updateHistUi();
    return r;
  }

  /** The name of a settings control (its label), else the field's name: "Change Label". */
  function editLabel(ctl) {
    var name = '';
    try {
      var lab = ctl && ctl.id ? document.querySelector('label[for="' + ctl.id.replace(/["\\]/g, '') + '"]') : null;
      name = lab ? lab.textContent.replace(/\s+/g, ' ').trim() : '';
      if (!name && ctl && ctl.dataset && ctl.dataset.id && rows()) {
        var id = ctl.dataset.id;
        var r = rows().find(function (x, i) { return i > 0 && x && (x.dataId === id || x.dataId === id + '-id'); });
        name = r ? (r.name || r.type) : T('formSetting', 'Form settings');
      }
    } catch (e) { name = ''; }
    return String(T('hEditSetting', 'Change %s')).replace('%s', name.slice(0, 60) || T('field', 'field'));
  }

  /* The builder's entry points name the change that follows (admin-efb.js, pro_els-efb.js). */
  W.efbHistHintEfb = function (label, key) { var h = ensureHist(); if (!h) return; h.begin(label, key); h.commit(); };
  W.efbHistEditEfb = function (ctl) { W.efbHistHintEfb(editLabel(ctl), (ctl && ctl.id ? ctl.id : '') + '|' + (ctl && ctl.dataset ? ctl.dataset.id || '' : '')); };
  W.efbHistCommitEfb = function () { var h = ensureHist(); if (h) h.commit(); };
  W.efbHistLateEfb = function (labelKey, fallback) { var h = ensureHist(); if (h) h.late(T(labelKey, fallback || labelKey)); };
  W.efbHistResetEfb = function () { var h = ensureHist(); if (h) { h.reset(); updateHistUi(); } };
  W.efbHistRecordEfb = function (label) { var h = ensureHist(); return h ? h.record(label) : null; };
  W.efbHistBatchEfb = function (label, fn) { var h = ensureHist(); return h ? h.batch(label, fn) : fn(); };
  W.efbHistStateEfb = function () { var h = ensureHist(); return h ? h.state() : null; };
  W.efbUndoEfb = function () { return undoRedo('undo'); };
  W.efbRedoEfb = function () { return undoRedo('redo'); };
  W.efb_history_apply_efb = applyState;

  /* A person's action starts on pointerdown / keydown (capture: before the builder's handlers) and
     ends when the builder's timers settled after change / click / drop / keyup. */
  function inOwnUi(t) { return !!(t && t.closest && t.closest('.efb-pd-back, #efbPaletteSearch, .efb-pd-tools')); }
  ['pointerdown', 'keydown'].forEach(function (type) {
    document.addEventListener(type, function (e) {
      if (!builderOn() || inOwnUi(e.target)) return;
      var h = ensureHist();
      if (h && !h.isApplying()) h.gesture();
    }, true);
  });
  ['change', 'click', 'drop', 'dragend', 'pointerup', 'keyup'].forEach(function (type) {
    document.addEventListener(type, function (e) {
      if (!builderOn() || inOwnUi(e.target)) return;
      var h = ensureHist();
      if (h && !h.isApplying()) h.commit();
    }, true);
  });
  // Every efbBuilderApi call that changes the form (AutoPilot) is one entry.
  document.addEventListener('efb:form-changed', function (e) {
    var d = e.detail || {};
    if (d.source !== 'api') return;
    var h = ensureHist();
    if (!h) return;
    var l = API_LABELS[d.action] ? T(API_LABELS[d.action][0], API_LABELS[d.action][1]) : String(d.action || '');
    h.record(String(T('hAutoPilot', 'AutoPilot: %s')).replace('%s', l), 'api|' + (d.action || ''));
  });

  /* ================================================================== C: form versions */

  var saveMeta = { restore: null, revId: 0, api: false, user: false };
  document.addEventListener('efb:form-changed', function (e) {
    var d = e.detail || {};
    if (d.source === 'api') saveMeta.api = true;
    else if (d.source === 'user') saveMeta.user = true;
  });
  /** rev_source / rev_note sent with a save (actionSendData_emsFormBuilder): a restored version, AutoPilot, the builder. */
  W.efbSaveMetaEfb = function () {
    if (saveMeta.restore) return { rev_source: 'restore', rev_note: saveMeta.restore, rev_extras: saveMeta.revId || 0 };
    return { rev_source: saveMeta.api && !saveMeta.user ? 'autopilot' : 'builder', rev_note: '' };
  };
  /** After a successful save (actionSendData_emsFormBuilder dispatches efb:form-saved). */
  document.addEventListener('efb:form-saved', function () {
    saveMeta = { restore: null, revId: 0, api: false, user: false };
    updateRevBtn();
  });

  function formId() { try { return Number(form_ID_emsFormBuilder) || 0; } catch (e) { return 0; } } // eslint-disable-line no-undef
  function isSaved() { try { return formId() > 0 && Number(state_check_ws_p) !== 1; } catch (e) { return formId() > 0; } } // eslint-disable-line no-undef
  function revCfg() { return (W.efb_var && W.efb_var.f15 && W.efb_var.f15.revisions) || { on: true }; }
  function updateRevBtn() {
    var b = document.getElementById('efbRevBtn');
    if (b) b.hidden = !isSaved();
  }

  var SRC = { builder: ['revSrcBuilder', 'Builder'], autopilot: ['revSrcAutoPilot', 'AutoPilot'], restore: ['revSrcRestore', 'Restored version'], created: ['revSrcCreated', 'Form created'], initial: ['revSrcInitial', 'Before versions were kept'], api: ['revSrcApi', 'API'], 'import': ['revSrcImport', 'Import'], duplicate: ['revSrcDuplicate', 'Copy of a form'] };

  function revList(fid) { return ajax({ action: 'efb_list_revisions', form_id: fid || formId() }); }
  function revGet(id) { return ajax({ action: 'efb_get_revision', rev_id: id }); }

  /** What restoring the version would change: fields only in it, only in the form now, different; form settings. */
  function diffForms(oldRows, nowRows) { return diffFormsImpl(oldRows, nowRows); }


  /** The version as the builder's state: row 0's SMS / Telegram texts and numbers of the form now are kept
   *  (they live in the add-ons' tables, never in a version). */
  function revisionState(revRows) {
    var cur = rows();
    var next = JSON.parse(JSON.stringify(revRows));
    if (cur && cur[0] && next[0]) SENSITIVE_ROW0.forEach(function (k) { if (cur[0][k] !== undefined && next[0][k] === undefined) next[0][k] = cur[0][k]; });
    return next;
  }

  /** Puts a version into the builder: unsaved, one undo step; the next Save keeps it (source "restore"). */
  function restoreRevision(rev) {
    if (!rev || Number(rev.form_id) !== formId()) return Promise.reject(new Error('revision_other_form'));
    var h = ensureHist();
    var next = revisionState(rev.rows);
    var when = rev.date || rev.created || '';
    if (h) h.flush();
    var p = applyState(JSON.stringify(next), { dir: 'restore' });
    if (h) h.record(String(T('hRestoreRev', 'Restore version from %s')).replace('%s', when), 'restore|' + rev.rev_id);
    saveMeta.restore = String(T('revRestoredNote', 'Restored from the version of %s')).replace('%s', when).slice(0, 190);
    saveMeta.revId = Number(rev.rev_id) || 0;
    showToast(sentence(T('revRestored', 'Version from %s loaded. Save to keep it.'), [when]));
    updateHistUi();
    return p;
  }

  function srcLabel(k) { var x = SRC[k]; return x ? T(x[0], x[1]) : String(k || ''); }

  function outline(rows) {
    var box = el('div', { class: 'efb efb-pd-outline' });
    var ul = null;
    (rows || []).forEach(function (r, i) {
      if (i === 0 || !r) return;
      if (r.type === 'step') { box.appendChild(el('h3', { class: 'efb efb-pd-h3', text: String(T('revStepN', 'Step %s')).replace('%s', num(r.step || 1)) + (r.name ? ': ' + r.name : '') })); ul = el('ul', { class: 'efb efb-pd-ul' }); box.appendChild(ul); return; }
      if (r.type === 'option' || r.type === 'r_matrix') return;
      if (!ul) { ul = el('ul', { class: 'efb efb-pd-ul' }); box.appendChild(ul); }
      var nOpts = rows.filter(function (o) { return o && (o.type === 'option' || o.type === 'r_matrix') && o.parent === r.id_; }).length;
      var li = el('li', null, [el('bdi', { text: String(r.name || r.type) }), el('span', { class: 'efb efb-pd-muted', text: ' · ' + r.type + (nOpts ? ' · ' + num(nOpts) : '') + (Number(r.required) === 1 || r.required === true || r.required === '1' ? ' · ' + T('required', 'Required') : '') })]);
      ul.appendChild(li);
    });
    return box;
  }

  function diffView(d) {
    var box = el('div', { class: 'efb efb-pd-diff' });
    if (!d.onlyOld.length && !d.onlyNow.length && !d.changed.length && !d.settings.length) { box.appendChild(el('p', { class: 'efb efb-pd-note', text: T('revSame', 'This version is the same as the current form.') })); return box; }
    var part = function (title, list, fmt) {
      if (!list.length) return;
      box.appendChild(el('h3', { class: 'efb efb-pd-h3', text: title + ' (' + num(list.length) + ')' }));
      var ul = el('ul', { class: 'efb efb-pd-ul' });
      list.slice(0, 50).forEach(function (x) { ul.appendChild(el('li', null, fmt(x))); });
      box.appendChild(ul);
    };
    part(T('revOnlyOld', 'Only in this version'), d.onlyOld, function (x) { return [el('bdi', { text: String(x.name) }), el('span', { class: 'efb efb-pd-muted', text: ' · ' + x.type })]; });
    part(T('revOnlyNow', 'Only in the current form'), d.onlyNow, function (x) { return [el('bdi', { text: String(x.name) }), el('span', { class: 'efb efb-pd-muted', text: ' · ' + x.type })]; });
    part(T('revDiffer', 'Changed'), d.changed, function (x) { return [el('bdi', { text: String(x.name) }), el('span', { class: 'efb efb-pd-muted', dir: 'ltr', text: ' · ' + x.keys.slice(0, 6).join(', ') })]; });
    if (d.settings.length) box.appendChild(el('p', { class: 'efb efb-pd-note' }, [sentence(T('revSettingsChanged', 'Form settings changed: %s'), [d.settings.slice(0, 12).join(', ')])]));
    return box;
  }

  /** The "Versions" dialog of the open form. */
  function openRevisions() {
    var body = el('div', { class: 'efb efb-pd-revs' });
    var status = el('div', { class: 'efb efb-pd-status', role: 'status', 'aria-live': 'polite', id: 'efbRevStatus' });
    var dlg = dialog({ id: 'efbRevDlg', title: T('revisions', 'Versions'), body: el('div', null, [body, status]), wide: true, foot: [el('button', { type: 'button', class: 'efb btn btn-outline-secondary efb-pd-btn', 'data-pd': 'close', text: T('close', 'Close') })] });
    if (!isSaved()) { body.appendChild(el('p', { class: 'efb efb-pd-note', text: T('revSaveFirst', 'Save the form first: versions are kept from the first save.') })); return dlg; }
    status.textContent = T('loading', 'Loading') + '…';
    var showList = function (data) {
      body.textContent = '';
      status.textContent = '';
      var revs = (data && data.revisions) || [];
      if (!revCfg().on || (data && Number(data.keep) === 0)) body.appendChild(el('p', { class: 'efb efb-pd-note', text: T('revOff', 'Versions are turned off (Settings › General).') }));
      if (!revs.length) { body.appendChild(el('p', { class: 'efb efb-pd-note', text: T('revNone', 'No earlier versions yet. A version is kept each time you save.') })); return; }
      body.appendChild(el('p', { class: 'efb efb-pd-note' }, [sentence(T('revCount', '%1$s versions kept (up to %2$s earlier ones with this plan and setting).'), [num(revs.length), num(data.keep)])]));
      var tb = el('tbody');
      revs.forEach(function (r) {
        var acts = el('td', { class: 'efb efb-pd-acts' });
        acts.appendChild(el('button', { type: 'button', class: 'efb btn btn-sm btn-outline-secondary efb-pd-btn', 'data-rev': r.rev_id, 'data-op': 'view', text: T('revPreview', 'View') }));
        if (!r.current) {
          acts.appendChild(el('button', { type: 'button', class: 'efb btn btn-sm btn-outline-secondary efb-pd-btn', 'data-rev': r.rev_id, 'data-op': 'compare', text: T('revCompare', 'Compare with current') }));
          acts.appendChild(el('button', { type: 'button', class: 'efb btn btn-sm btn-primary efb-pd-btn', 'data-rev': r.rev_id, 'data-op': 'restore', text: T('revRestore', 'Restore') }));
        }
        tb.appendChild(el('tr', { 'data-rev': r.rev_id }, [
          el('td', { class: 'efb', 'data-label': T('revDate', 'Date') }, [el('time', { datetime: r.iso, text: r.date }), r.current ? el('span', { class: 'efb badge bg-success efb-pd-badge', text: T('revCurrent', 'Current') }) : null]),
          el('td', { class: 'efb', 'data-label': T('revSource', 'Saved by') }, [el('bdi', { text: r.user || '—' }), el('span', { class: 'efb efb-pd-muted', text: ' · ' + srcLabel(r.source) })]),
          el('td', { class: 'efb', 'data-label': T('revFields', 'Fields') }, [document.createTextNode(num(r.fields) + ' / ' + num(r.steps))]),
          el('td', { class: 'efb', 'data-label': T('revNote', 'Note'), 'data-empty': r.note ? null : '1' }, [el('bdi', { text: r.note || '' })]),
          acts,
        ]));
      });
      body.appendChild(el('table', { class: 'efb efb-pd-table efb-pd-revtable' }, [
        el('thead', null, [el('tr', null, [T('revDate', 'Date'), T('revSource', 'Saved by'), T('revFields', 'Fields') + ' / ' + T('revSteps', 'Steps'), T('revNote', 'Note'), T('scAction', 'Action')].map(function (x) { return el('th', { scope: 'col', class: 'efb', text: x }); }))]),
        tb,
      ]));
      body.querySelectorAll('button[data-op]').forEach(function (b) {
        b.addEventListener('click', function () { act(b.getAttribute('data-op'), Number(b.getAttribute('data-rev')), revs.find(function (x) { return x.rev_id === Number(b.getAttribute('data-rev')); })); });
      });
    };
    var back = function () { return el('button', { type: 'button', class: 'efb btn btn-sm btn-link efb-pd-btn', text: T('revBack', 'Back to the list'), onclick: function () { load(); } }); };
    var act = function (op, id, meta) {
      status.textContent = T('loading', 'Loading') + '…';
      revGet(id).then(function (d) {
        status.textContent = '';
        if (!d || !d.success || !d.revision) { status.textContent = (d && d.m) || T('error', 'Error'); status.classList.add('efb-pd-err'); return; }
        var rev = d.revision;
        rev.date = meta ? meta.date : rev.created;
        if (Number(rev.form_id) !== formId()) { status.textContent = T('error', 'Error'); return; }
        if (op === 'view' || op === 'compare') {
          body.textContent = '';
          body.appendChild(el('p', { class: 'efb efb-pd-note' }, [el('time', { datetime: meta ? meta.iso : '', text: rev.date }), el('span', { class: 'efb efb-pd-muted', text: ' · ' + srcLabel(rev.source) })]));
          body.appendChild(op === 'view' ? outline(rev.rows) : diffView(diffForms(rev.rows, rows() || [])));
          body.appendChild(back());
          var b = body.querySelector('button');
          if (b) b.focus();
          return;
        }
        if (op === 'restore') {
          body.textContent = '';
          var yes = el('button', { type: 'button', class: 'efb btn btn-primary efb-pd-btn', id: 'efbRevYes', 'data-pd-focus': '1', text: T('revRestore', 'Restore') });
          body.appendChild(el('p', { class: 'efb efb-pd-note' }, [sentence(T('revRestoreQ', 'Load the version from %s into the builder? Nothing is saved until you click Save, and you can undo it.'), [rev.date])]));
          body.appendChild(diffView(diffForms(rev.rows, rows() || [])));
          body.appendChild(el('div', { class: 'efb efb-pd-row' }, [yes, back()]));
          yes.addEventListener('click', function () { dlg.close(); restoreRevision(rev); });
          yes.focus();
        }
      }, function () { status.textContent = T('error', 'Error'); status.classList.add('efb-pd-err'); });
    };
    var load = function () { revList().then(function (d) { if (!d || !d.success) { status.textContent = (d && d.m) || T('error', 'Error'); status.classList.add('efb-pd-err'); return; } showList(d); }, function () { status.textContent = T('error', 'Error'); status.classList.add('efb-pd-err'); }); };
    load();
    return dlg;
  }

  W.efbOpenRevisionsEfb = openRevisions;
  W.efbRevListEfb = function (fid) { return revList(fid); };
  W.efbRevGetEfb = function (id) { return revGet(id); };
  W.efbRevRestoreEfb = function (id) { return revGet(id).then(function (d) { if (!d || !d.success) throw new Error((d && d.code) || 'no_revision'); return restoreRevision(d.revision).then(function () { return { rev_id: id, restored: true, saved: false }; }); }); };

  /* Settings › General: how many earlier versions each form keeps (list_form-efb.js draws it). */
  W.efb_revisions_settings_html_efb = function (settings, cls) {
    var cfg = revCfg();
    var v = settings && settings.form_rev_keep !== undefined && settings.form_rev_keep !== 'NotFoundEl' ? String(settings.form_rev_keep) : '10';
    var esc = function (x) { return String(x).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
    return '<div class="efb card-body ' + esc(cls || '') + ' py-1 mt-3" data-f15="revisions">'
      + '<label class="efb form-label fw-bold fs-6 mb-1" for="formRevKeep_efb">' + esc(T('revKeep', 'Number of earlier versions to keep per form')) + '</label>'
      + '<input type="text" inputmode="numeric" maxlength="2" autocomplete="off" class="efb form-control w-25 h-d-efb border-d efb-rounded" id="formRevKeep_efb" value="' + esc(v) + '" aria-describedby="formRevKeepHelp_efb">'
      + '<small class="efb text-muted d-block mt-1 fs-7 efb-pd-sentx" id="formRevKeepHelp_efb">' + esc(String(T('revKeepHelp', '0 turns versions off. Your plan keeps up to %s.')).replace('%s', String(cfg.cap || 10))) + '</small></div>';
  };
  W.efb_revisions_settings_patch_efb = function () {
    var i = document.getElementById('formRevKeep_efb');
    if (!i) return {};
    var d = normalize(i.value).replace(/[^0-9]/g, '');
    return { form_rev_keep: String(Math.min(50, Number(d || 0))) };
  };

  /* ================================================================== toolbar */

  /** Extra toolbar buttons of the builder (after the builder's own items). */
  function mountToolbar() {
    var ul = document.querySelector('#navbarTogglerDemo01 ul.navbar-nav');
    if (!ul || ul.querySelector('#efbPdTools')) return;
    var li = el('li', { class: 'efb nav-item efb-pd-tools', id: 'efbPdTools' });
    var mod = isMac() ? '\u2318' : 'Ctrl';
    li.appendChild(el('button', { type: 'button', class: 'efb btn nav-link efb-pd-nav', id: 'efbUndoBtn', disabled: true, 'aria-disabled': 'true', 'aria-label': T('undo', 'Undo') + ' (' + mod + '+Z)', title: T('undo', 'Undo') + ' (' + mod + '+Z)', onclick: function () { W.efbUndoEfb(); } },
      [el('i', { class: 'efb bi-arrow-counterclockwise', 'aria-hidden': 'true' }), el('span', { class: 'efb efb-pd-nav-label', text: T('undo', 'Undo') })]));
    li.appendChild(el('button', { type: 'button', class: 'efb btn nav-link efb-pd-nav', id: 'efbRedoBtn', disabled: true, 'aria-disabled': 'true', 'aria-label': T('redo', 'Redo') + ' (' + mod + '+Shift+Z)', title: T('redo', 'Redo') + ' (' + mod + '+Shift+Z)', onclick: function () { W.efbRedoEfb(); } },
      [el('i', { class: 'efb bi-arrow-clockwise', 'aria-hidden': 'true' }), el('span', { class: 'efb efb-pd-nav-label', text: T('redo', 'Redo') })]));
    li.appendChild(el('button', { type: 'button', class: 'efb btn nav-link efb-pd-nav', id: 'efbRevBtn', hidden: !isSaved(), 'aria-label': T('revisions', 'Versions'), title: T('revisions', 'Versions'), onclick: function () { openRevisions(); } },
      [el('i', { class: 'efb bi-clock-history', 'aria-hidden': 'true' }), el('span', { class: 'efb efb-pd-nav-label', text: T('revisions', 'Versions') })]));
    li.appendChild(el('button', { type: 'button', class: 'efb btn nav-link efb-pd-nav', id: 'efbScBtn', 'aria-label': T('shortcuts', 'Keyboard shortcuts') + ' (?)', title: T('shortcuts', 'Keyboard shortcuts') + ' (?)', onclick: function () { openShortcutsHelp(); } },
      [el('i', { class: 'efb bi-keyboard', 'aria-hidden': 'true' })]));
    ul.appendChild(li);
    if (typeof W.efbPdToolbarExtEfb === 'function') W.efbPdToolbarExtEfb(li);
    updateHistUi();
  }

  /** Called at the end of creator_form_builder_Efb(): the builder screen was (re)built. */
  function onBuilderBuilt() {
    mountPaletteSearch();
    mountToolbar();
    initShortcuts();
    if (typeof W.efbHistResetEfb === 'function') W.efbHistResetEfb();
  }

  W.efb_palette_search_efb = paletteSearch;
  W.efb_builder_shortcuts_init_efb = initShortcuts;
  W.efbPdBuilderBuiltEfb = onBuilderBuilt;
  W.efbPdOpenShortcutsEfb = openShortcutsHelp;

  var api = Object.assign({}, pure, {
    dialog: dialog, sentence: sentence, el: el, T: T, num: num, ajax: ajax, toast: toast, rtl: rtl,
    paletteSearch: paletteSearch, paletteAddFirst: paletteAddFirst, openShortcutsHelp: openShortcutsHelp,
    runAction: runAction, keyHandler: keyHandler, builderOn: builderOn, history: function () { return ensureHist(); }, applyState: applyState,
    openRevisions: openRevisions, restoreRevision: restoreRevision, revisionState: revisionState, openDialogs: function () { return openDialogs.slice(); },
    pref: pref, setPref: function (name, v) { prefs[name] = String(v); },
  });
  return api;
});
