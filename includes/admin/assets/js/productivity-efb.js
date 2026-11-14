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

  /* ================================================================== toolbar */

  /** Extra toolbar buttons of the builder (after the builder's own items). */
  function mountToolbar() {
    var ul = document.querySelector('#navbarTogglerDemo01 ul.navbar-nav');
    if (!ul || ul.querySelector('#efbPdTools')) return;
    var li = el('li', { class: 'efb nav-item efb-pd-tools', id: 'efbPdTools' });
    li.appendChild(el('button', { type: 'button', class: 'efb btn nav-link efb-pd-nav', id: 'efbScBtn', 'aria-label': T('shortcuts', 'Keyboard shortcuts') + ' (?)', title: T('shortcuts', 'Keyboard shortcuts') + ' (?)', onclick: function () { openShortcutsHelp(); } },
      [el('i', { class: 'efb bi-keyboard', 'aria-hidden': 'true' })]));
    ul.appendChild(li);
    if (typeof W.efbPdToolbarExtEfb === 'function') W.efbPdToolbarExtEfb(li);
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
    runAction: runAction, keyHandler: keyHandler, builderOn: builderOn, openDialogs: function () { return openDialogs.slice(); },
    pref: pref, setPref: function (name, v) { prefs[name] = String(v); },
  });
  return api;
});
