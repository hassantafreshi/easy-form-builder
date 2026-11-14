/**
 * F05: choice fields in the page and in the builder (window.EfbChoices; module.exports in Node).
 * Spec: docs/feature-gaps/F05-choices.fa.md.
 *
 *  - rules: the F05 keys and their save rules (twin of \Emsfb\Choices::sanitize_key(),
 *    tests/test-choices-parity.js), used by the builder controls and efbBuilderApi;
 *  - parseBulk(): the "Add in bulk" lines (Label | Label|value | Label|value|calc, "\|" = "|");
 *  - in the page: shuffled order kept for the visitor's session, "Select all", the maximum lock,
 *    the "Other" text box, the "N left" / fully booked marks from forms/session/prepare, the
 *    minimum / Other / ranking checks of Next and Send, and the ranking widget (drag, buttons,
 *    keyboard, aria-live).
 * Every text is written with textContent. The server checks every answer again
 * (includes/class-Emsfb-choices.php, includes/class-Emsfb-stock.php).
 */
(function (root, factory) {
  const api = factory(root);
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) root.EfbChoices = api;
})(typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : this), function (root) {
  'use strict';

  /* ------------------------------------------------------------------ keys and their rules */

  const OPTION_KEYS = ['opt_value', 'op_lock', 'inv_cap'];
  const PARENT_KEYS = ['op_random', 'op_other', 'op_other_label', 'op_other_max', 'op_select_all', 'minSelect', 'maxSelect',
    'inv_on', 'inv_show', 'inv_left', 'inv_release', 'dyn_src', 'dyn_post_type', 'dyn_tax', 'dyn_term', 'dyn_role',
    'dyn_orderby', 'dyn_order', 'dyn_limit', 'dyn_csv', 'rank_top', 'rank_touch'];
  const TYPES = {
    random: ['radio', 'checkbox', 'select', 'multiselect', 'imgRadio', 'payRadio', 'payCheckbox', 'paySelect', 'payMultiselect', 'chlRadio', 'chlCheckBox', 'ranking'],
    other: ['radio', 'checkbox', 'payRadio', 'payCheckbox', 'chlRadio', 'chlCheckBox'],
    select_all: ['checkbox', 'payCheckbox'],
    minmax: ['checkbox', 'payCheckbox', 'chlCheckBox', 'multiselect', 'payMultiselect'],
    inventory: ['radio', 'checkbox', 'select', 'multiselect', 'imgRadio', 'payRadio', 'payCheckbox', 'paySelect', 'payMultiselect'],
    dynamic: ['radio', 'checkbox', 'select', 'multiselect'],
    ranking: ['ranking'],
  };
  const FLAGS = ['op_lock', 'op_random', 'op_other', 'op_select_all', 'inv_on', 'inv_left', 'inv_release'];
  const ENUMS = {
    inv_show: ['disable', 'hide', 'label'],
    dyn_src: ['posts', 'terms', 'users', 'csv'],
    dyn_orderby: ['title', 'date', 'menu_order', 'name'],
    dyn_order: ['ASC', 'DESC'],
  };
  const LIMITS = { op_other_max: [1, 1000], minSelect: [0, 500], maxSelect: [0, 500], inv_cap: [0, 1000000], dyn_limit: [1, 500], rank_top: [0, 500], dyn_csv: [1, 2147483647] };
  const MAX_BULK = 500;

  const toAscii = (s) => String(s == null ? '' : s)
    .replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06F0))
    .replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660));
  /** sanitize_text_field() in short: no tags, no control characters, one line, trimmed. */
  function plain(v, max) {
    let s = String(v == null ? '' : v).replace(/<[^>]*>?/g, '').replace(/[\u0000-\u001F\u007F]+/g, ' ').replace(/\s+/g, ' ').trim();
    s = s.replace(/["<>]/g, '');
    const a = Array.from(s);
    return a.length > max ? a.slice(0, max).join('').trim() : s;
  }
  /** One F05 key as the server stores it ('' = not set). Twin of \Emsfb\Choices::sanitize_key(). */
  function sanitizeKey(k, v) {
    if (v === null || v === undefined || typeof v === 'object') return '';
    if (FLAGS.indexOf(k) !== -1) return (v === true || v === 1 || v === '1' || v === 'true' || v === 'on') ? '1' : '';
    if (k === 'rank_touch') return (v === false || v === 0 || v === '0' || v === 'false') ? '0' : ((v === '' ) ? '' : '1');
    if (typeof v === 'boolean') v = v ? '1' : ''; // PHP's (string) true / false
    if (ENUMS[k]) { const s = String(v).trim(); const m = ENUMS[k].find((x) => x.toLowerCase() === s.toLowerCase()); return m || ''; }
    if (LIMITS[k]) {
      const s = toAscii(String(v)).trim();
      if (!/^\d{1,10}$/.test(s)) return '';
      const n = Math.min(LIMITS[k][1], Math.max(LIMITS[k][0], parseInt(s, 10)));
      return String(n);
    }
    switch (k) {
      case 'opt_value': return plain(v, 200);
      case 'op_other_label': return plain(v, 80);
      case 'dyn_post_type':
      case 'dyn_tax': return String(v).toLowerCase().replace(/[^a-z0-9_-]/g, '').slice(0, 40);
      case 'dyn_term': return String(v).toLowerCase().split(',').map((x) => x.trim().replace(/[^a-z0-9_-]/g, '')).filter(Boolean).slice(0, 50).join(',');
      case 'dyn_role': return String(v).toLowerCase().split(',').map((x) => x.trim().replace(/[^a-z0-9_-]/g, '')).filter(Boolean).slice(0, 20).join(',');
    }
    return plain(v, 200);
  }
  const rules = { OPTION_KEYS, PARENT_KEYS, TYPES, FLAGS, ENUMS, LIMITS, MAX_BULK, sanitizeKey, plain };

  /* ------------------------------------------------------------------ "Add in bulk" */

  /**
   * Lines → choices. "Label", "Label|value", "Label|value|calc" ("\|" is a "|" in the text). Empty lines
   * are skipped; "## Name" lines (choice groups, a later phase) are skipped and counted; " < > are removed
   * and reported. opts: {calc: bool (the third column is kept only then), existing: [{label, value}]}.
   * @return {{items: Array<{label: string, value: string, calc?: string}>, stripped: number[], dupLabels: string[],
   *           dupValues: string[], skippedGroups: number, tooMany: boolean, lines: number}}
   */
  function parseBulk(text, opts) {
    opts = opts || {};
    const out = { items: [], stripped: [], dupLabels: [], dupValues: [], skippedGroups: 0, tooMany: false, lines: 0, emptyLabel: [] };
    const seenL = {}, seenV = {};
    (opts.existing || []).forEach((e) => {
      if (!e) return;
      if (e.label) seenL[String(e.label).toLowerCase()] = true;
      const ev = e.value !== undefined && e.value !== '' ? String(e.value) : String(e.label || '');
      if (ev) seenV[ev] = true;
    });
    const lines = String(text == null ? '' : text).replace(/\r\n?/g, '\n').split('\n');
    lines.forEach((raw, n) => {
      const line = raw.replace(/^﻿/, '');
      if (line.trim() === '') return;
      if (/^##\s/.test(line.trim())) { out.skippedGroups++; return; }
      out.lines++;
      if (out.items.length >= MAX_BULK) { out.tooMany = true; return; }
      const parts = line.replace(/\\\|/g, '\u0000').split('|').map((p) => p.replace(/\u0000/g, '|'));
      let label = parts[0], value = parts.length > 1 ? parts[1] : '', calc = parts.length > 2 ? parts[2] : '';
      if (/["<>]/.test(label + value + calc)) out.stripped.push(n + 1);
      label = plain(label, 200);
      value = plain(value, 200);
      calc = toAscii(plain(calc, 40));
      if (label === '') { out.emptyLabel.push(n + 1); return; }
      const item = { label };
      if (value !== '' && value !== label) item.value = value;
      if (opts.calc && calc !== '' && /^-?\d+(\.\d+)?$/.test(calc)) item.calc = calc;
      const lk = label.toLowerCase();
      if (seenL[lk]) out.dupLabels.push(label); else seenL[lk] = true;
      const vk = item.value !== undefined ? item.value : label;
      if (seenV[vk] && item.value !== undefined) out.dupValues.push(vk);
      seenV[vk] = true;
      out.items.push(item);
    });
    return out;
  }

  /* ------------------------------------------------------------------ helpers for the page */

  /* The page's own globals: core-efb.js / new-efb.js declare several with let (not window properties), so they are
     read by name here (shared global scope of classic scripts), with window as the fallback (and Node: none). */
  /* global sendBack_emsFormBuilder_pub, sessionPub_emsFormBuilder, valj_efb, efb_var */
  const G = {
    rows() { try { if (typeof sendBack_emsFormBuilder_pub !== 'undefined') return sendBack_emsFormBuilder_pub; } catch (e) {} return root.sendBack_emsFormBuilder_pub; },
    session() { try { if (typeof sessionPub_emsFormBuilder !== 'undefined') return sessionPub_emsFormBuilder; } catch (e) {} return root.sessionPub_emsFormBuilder; },
    valj() { try { if (typeof valj_efb !== 'undefined') return valj_efb; } catch (e) {} return root.valj_efb; },
    ev() { try { if (typeof efb_var !== 'undefined' && efb_var) return efb_var; } catch (e) {} return root.efb_var; },
  };
  const T = (k, fb) => {
    try {
      const ev = G.ev();
      const t = (ev && ev.text) || (root.ajax_object_efm && root.ajax_object_efm.text) || {};
      if (t[k]) return t[k];
    } catch (e) { /* no texts */ }
    return fb;
  };
  function locale() {
    try { if (root.ajax_object_efm && root.ajax_object_efm.language) return root.ajax_object_efm.language; } catch (e) {}
    try { const ev = G.ev(); if (ev && ev.wp_lan) return ev.wp_lan; } catch (e) {}
    try { return root.document.documentElement.lang || ''; } catch (e) { return ''; }
  }
  /** The language's digits (Persian, Arabic-Indic, else Latin). */
  function digits(s, loc) {
    const lang = String(loc === undefined ? locale() : loc).toLowerCase().split(/[_-]/)[0];
    let base = 0;
    if (['fa', 'ps', 'azb', 'haz'].indexOf(lang) !== -1) base = 0x06F0;
    else if (['ar', 'ary', 'arq', 'ckb', 'ur'].indexOf(lang) !== -1) base = 0x0660;
    if (!base) return String(s);
    return String(s).replace(/[0-9]/g, (d) => String.fromCharCode(base + Number(d)));
  }
  /** A sentence with values put in: each value isolated (FSI … PDI) so a mixed-script sentence keeps its order. */
  function fill(tpl, vals) {
    let i = 0;
    const iso = (v) => '⁨' + String(v) + '⁩';
    return String(tpl).replace(/%(\d)\$s|%s/g, (m, n) => {
      const v = n ? vals[Number(n) - 1] : vals[i++];
      return v === undefined ? m : iso(v);
    });
  }
  function rowsOf(fid) {
    try { if (typeof root.get_structure_by_form_id_efb === 'function') return root.get_structure_by_form_id_efb(fid) || []; } catch (e) {}
    const v = G.valj();
    return Array.isArray(v) ? v : [];
  }
  const on = (r, k) => !!r && (r[k] === '1' || r[k] === 1 || r[k] === true);
  const num = (r, k) => { const n = parseInt(toAscii(r && r[k] !== undefined ? r[k] : ''), 10); return isNaN(n) ? 0 : n; };
  function byId(fid, id) {
    const doc = root.document;
    if (!doc) return null;
    const body = doc.getElementById('body_efb_' + fid);
    if (body) {
      try { const e = body.querySelector('[id="' + String(id).replace(/"/g, '\\"') + '"]'); if (e) return e; } catch (x) {}
    }
    return doc.getElementById(id);
  }

  /* ------------------------------------------------------------------ order (shuffle) */

  function rand(n) {
    try {
      if (root.crypto && root.crypto.getRandomValues) { const a = new Uint32Array(1); root.crypto.getRandomValues(a); return a[0] % n; }
    } catch (e) {}
    return Math.floor(Math.random() * n);
  }
  /**
   * The order of the ids of one field for this visitor: the order kept in sessionStorage
   * (efb_ord_{form}_{field}) when it holds the same ids, else a new Fisher–Yates order of the
   * free places (a locked id keeps its place), kept for the session.
   */
  function sessionOrder(fid, field, ids, locked, store) {
    const key = 'efb_ord_' + fid + '_' + field;
    store = store || (function () { try { return root.sessionStorage; } catch (e) { return null; } })();
    try {
      const old = store ? JSON.parse(store.getItem(key) || 'null') : null;
      if (Array.isArray(old) && old.length === ids.length && old.slice().sort().join('\u0001') === ids.slice().sort().join('\u0001')) return old;
    } catch (e) { /* storage blocked or broken: a new order */ }
    const lock = {};
    (locked || []).forEach((x) => { lock[x] = true; });
    const free = [];
    ids.forEach((id, i) => { if (!lock[id]) free.push(i); });
    const out = ids.slice();
    const pool = free.map((i) => ids[i]);
    for (let i = pool.length - 1; i > 0; i--) { const j = rand(i + 1); const t = pool[i]; pool[i] = pool[j]; pool[j] = t; }
    free.forEach((pos, k) => { out[pos] = pool[k]; });
    try { if (store) store.setItem(key, JSON.stringify(out)); } catch (e) {}
    return out;
  }
  /** Moves the child nodes to the given order of ids (getId(node) → id); nodes without an id stay put. */
  function applyOrder(parent, nodes, order, getId) {
    if (!parent || !nodes.length) return;
    const map = {};
    nodes.forEach((n) => { map[getId(n)] = n; });
    const anchor = nodes[nodes.length - 1].nextSibling;
    order.forEach((id) => { if (map[id]) parent.insertBefore(map[id], anchor); });
  }

  /* ------------------------------------------------------------------ the page */

  const STATE = { stock: {}, bound: {} };

  function fieldRow(fid, id) { return rowsOf(fid).find((r) => r && r.id_ === id) || null; }
  function optionsOf(fid, id) { return rowsOf(fid).filter((r) => r && r.type === 'option' && r.parent === id); }

  function shuffleField(fid, f, box) {
    const t = f.type;
    if (t === 'select' || t === 'paySelect') {
      const sel = byId(fid, f.id_ + '_options');
      if (!sel) return;
      const nodes = Array.from(sel.querySelectorAll('option')).filter((o) => o.dataset && o.dataset.op);
      const ids = nodes.map((o) => o.dataset.op);
      const order = sessionOrder(fid, f.id_, ids, nodes.filter((o) => o.dataset.lock === '1').map((o) => o.dataset.op));
      applyOrder(sel, nodes, order, (o) => o.dataset.op);
      return;
    }
    if (t === 'multiselect' || t === 'payMultiselect') {
      const list = root.document.querySelector('[data-list="menu-' + f.id_ + '"] tbody') || root.document.querySelector('table.menu-' + f.id_ + ' tbody');
      if (!list) return;
      const nodes = Array.from(list.querySelectorAll('tr.efblist[data-row]'));
      const ids = nodes.map((n) => n.dataset.row);
      const order = sessionOrder(fid, f.id_, ids, nodes.filter((n) => n.dataset.lock === '1').map((n) => n.dataset.row));
      applyOrder(list, nodes, order, (n) => n.dataset.row);
      return;
    }
    if (!box) return;
    const nodes = Array.from(box.children).filter((n) => n.dataset && n.dataset.id && n.classList.contains('form-check') && !n.classList.contains('efb-selall'));
    const ids = nodes.map((n) => n.dataset.id);
    const locked = nodes.filter((n) => n.dataset.lock === '1' || n.classList.contains('efb-other')).map((n) => n.dataset.id);
    applyOrder(box, nodes, sessionOrder(fid, f.id_, ids, locked), (n) => n.dataset.id);
  }

  function choiceInputs(box) {
    return Array.from(box.querySelectorAll('input.form-check-input.emsFormBuilder_v')).filter((i) => !i.closest('.efb-selall'));
  }
  /** "Select all" box: checked / mixed / clear from the real choices (not the "Other" choice). */
  function syncSelectAll(box) {
    const all = box.querySelector('.efb-selall input');
    if (!all) return;
    const list = choiceInputs(box).filter((i) => !i.closest('.efb-other') && !i.disabled);
    const n = list.filter((i) => i.checked).length;
    all.checked = n > 0 && n === list.length;
    all.indeterminate = n > 0 && n < list.length;
    all.setAttribute('aria-checked', all.indeterminate ? 'mixed' : (all.checked ? 'true' : 'false'));
  }
  /** At the maximum the unchecked choices are off (disabled + aria-disabled) and the field says so. */
  function syncMax(fid, f, box) {
    const max = num(f, 'maxSelect');
    if (!max) return;
    const list = choiceInputs(box);
    const n = list.filter((i) => i.checked).length;
    const full = n >= max;
    list.forEach((i) => {
      if (i.checked || i.dataset.soldout === '1') return;
      if (full) { if (!i.disabled) { i.disabled = true; i.dataset.efbMaxOff = '1'; i.setAttribute('aria-disabled', 'true'); } }
      else if (i.dataset.efbMaxOff === '1') { i.disabled = false; delete i.dataset.efbMaxOff; i.removeAttribute('aria-disabled'); }
    });
    const msg = byId(fid, f.id_ + '_-message');
    if (msg) {
      if (full) { msg.textContent = fill(T('maxSelM_', 'Choose at most %2$s options in %1$s.'), [f.name || '', digits(max)]); msg.setAttribute('aria-live', 'polite'); msg.dataset.efbChMsg = 'max'; msg.style.display = 'block'; msg.classList.remove('d-none'); }
      else if (msg.dataset.efbChMsg === 'max') { msg.textContent = ''; delete msg.dataset.efbChMsg; msg.style.display = 'none'; }
    }
  }
  /** The "Other" text box follows its choice: enabled and focused when chosen, the answer row carries the text. */
  function bindOther(fid, f, box) {
    const radio = byId(fid, f.id_ + '_oth');
    const text = byId(fid, f.id_ + '_oth_t');
    if (!radio || !text || text.dataset.efbBound === '1') return;
    text.dataset.efbBound = '1';
    const push = () => {
      radio.value = text.value.replace(/\s+/g, ' ').trim();
      if (!radio.checked) return;
      if (radio.value === '') { dropOther(fid, f); return; }
      if (typeof root.handle_change_event_efb_v4 === 'function') root.handle_change_event_efb_v4(radio, fid);
    };
    const follow = () => {
      text.disabled = !radio.checked;
      text.setAttribute('aria-disabled', radio.checked ? 'false' : 'true');
      if (radio.checked) { if (root.document.activeElement !== text) { try { text.focus(); } catch (e) {} } if (text.value.trim() === '') dropOther(fid, f); }
    };
    radio.addEventListener('change', follow);
    if (f.type.indexOf('adio') !== -1) box.addEventListener('change', (e) => { if (e.target !== radio) follow(); });
    text.addEventListener('input', push);
    text.addEventListener('change', push);
    follow();
  }
  /** "Other" chosen with no text yet: no answer row for it (a radio drops the previous choice's row too). */
  function dropOther(fid, f) {
    const list = G.rows();
    if (!Array.isArray(list)) return;
    const radio = f.type.indexOf('adio') !== -1;
    for (let i = list.length - 1; i >= 0; i--) {
      const r = list[i];
      if (r && r.id_ === f.id_ && Number(r.form_id) === Number(fid) && (radio || r.id_ob === f.id_ + '_oth')) list.splice(i, 1);
    }
    if (typeof root.sendback_state_handler_efb_v4 === 'function') root.sendback_state_handler_efb_v4(f.id_, !(f.required == 1 || f.required === true), 0, fid);
  }

  /** forms/session/prepare's stock {optionId: left} on the page (cached HTML has older numbers). */
  function applyStock(fid, stock) {
    if (!stock || typeof stock !== 'object') return;
    STATE.stock[fid] = stock;
    rowsOf(fid).forEach((f) => {
      if (!f || f.type === 'option' || !(on(f, 'inv_on') || f.inv_mode === 'booking')) return;
      const mode = f.inv_show || 'disable';
      optionsOf(fid, f.id_).forEach((o) => {
        if (!Object.prototype.hasOwnProperty.call(stock, o.id_)) return;
        const left = Math.max(0, parseInt(stock[o.id_], 10) || 0);
        markOption(fid, f, o, left, mode);
      });
    });
  }
  function markOption(fid, f, o, left, mode) {
    const doc = root.document;
    const wrap = byId(fid, o.id_ + '-v');
    const input = byId(fid, o.id_);
    const sold = left <= 0;
    const leftText = on(f, 'inv_left') && !sold ? fill(T('invLeftN', '%s left'), [digits(left)]) : '';
    if (f.type === 'select' || f.type === 'paySelect') {
      const opt = doc.querySelector('#' + CSSesc(f.id_ + '_options') + ' option[data-op="' + o.id_ + '"]');
      if (!opt) return;
      opt.disabled = sold;
      if (sold && mode === 'hide') opt.hidden = true;
      const base = opt.dataset.label || opt.textContent;
      opt.dataset.label = base;
      opt.textContent = sold ? base + ' — ' + T('soldOut', 'Fully booked') : (leftText ? base + ' (' + leftText + ')' : base);
      return;
    }
    if (f.type === 'multiselect' || f.type === 'payMultiselect') {
      const tr = doc.querySelector('[data-list="menu-' + f.id_ + '"] tr[data-row="' + o.id_ + '"]');
      if (!tr) return;
      tr.classList.toggle('efb-soldout', sold);
      tr.classList.toggle('d-none', sold && mode === 'hide');
      tr.setAttribute('aria-disabled', sold ? 'true' : 'false');
      return;
    }
    if (!wrap) return;
    wrap.classList.toggle('efb-soldout', sold);
    if (sold && mode === 'hide') wrap.classList.add('d-none');
    if (input && sold && !input.checked) { input.disabled = true; input.dataset.soldout = '1'; input.setAttribute('aria-disabled', 'true'); }
    let tag = wrap.querySelector('.efb-ch-left');
    if (!tag) {
      tag = doc.createElement('span');
      tag.className = 'efb efb-ch-left fs-7 text-muted mx-1';
      tag.setAttribute('dir', 'auto');
      const lab = wrap.querySelector('label');
      (lab || wrap).appendChild(tag);
    }
    tag.textContent = sold ? T('soldOut', 'Fully booked') : leftText;
    tag.classList.toggle('d-none', !tag.textContent);
  }
  const CSSesc = (s) => (root.CSS && root.CSS.escape ? root.CSS.escape(String(s)) : String(s).replace(/[^A-Za-z0-9_-]/g, '\\$&'));

  /* ------------------------------------------------------------------ ranking */

  function rankWidgets(fid) {
    const doc = root.document;
    const body = doc && doc.getElementById('body_efb_' + fid);
    return body ? Array.from(body.querySelectorAll('[data-efb-rank]')) : [];
  }
  function rankItems(w) { return Array.from(w.querySelectorAll('.efb-rank-item')); }
  function rankAnnounce(w, li) {
    const items = rankItems(w);
    const live = w.querySelector('.efb-rank-live');
    const pos = items.indexOf(li) + 1;
    if (live) live.textContent = fill(T('rnkMoved', '%1$s, position %2$s of %3$s'), [li.dataset.label || '', digits(pos), digits(items.length)]);
    renumber(w);
  }
  function renumber(w) {
    rankItems(w).forEach((li, i) => {
      const n = li.querySelector('.efb-rank-n');
      if (n) n.textContent = digits(i + 1);
      const top = parseInt(w.dataset.top || '0', 10) || 0;
      li.classList.toggle('efb-rank-out', top > 0 && i >= top);
      const up = li.querySelector('.efb-rank-up'), down = li.querySelector('.efb-rank-down');
      if (up) up.disabled = i === 0;
      if (down) down.disabled = i === rankItems(w).length - 1;
    });
  }
  function rankMove(w, li, to, focusBtn) {
    const items = rankItems(w);
    const from = items.indexOf(li);
    to = Math.max(0, Math.min(items.length - 1, to));
    if (from === -1 || from === to) return;
    const list = li.parentNode;
    const ref = to > from ? items[to].nextSibling : items[to];
    list.insertBefore(li, ref);
    w.dataset.touched = '1';
    rankAnnounce(w, li);
    rankPush(w);
    if (focusBtn) { const b = li.querySelector(focusBtn); if (b && !b.disabled) b.focus(); else li.focus(); } else li.focus();
  }
  /** The answer row: labels in order joined with @efb!, the ids with "," (only the first rank_top when set). */
  function rankPush(w, force) {
    const fid = w.dataset.formid;
    const id = w.dataset.vid;
    const f = fieldRow(fid, id);
    if (!f) return;
    const touched = w.dataset.touched === '1' || w.dataset.confirmed === '1' || w.dataset.touch === '0' || force;
    if (!touched) return;
    const top = parseInt(w.dataset.top || '0', 10) || 0;
    let items = rankItems(w);
    if (top > 0) items = items.slice(0, top);
    const row = {
      id_: id, name: f.name, id_ob: id + '_', amount: f.amount, type: 'ranking',
      value: items.map((li) => li.dataset.label || '').join('@efb!') + '@efb!',
      ids: items.map((li) => li.dataset.op).join(','),
      session: G.session(), form_id: fid,
    };
    const list = G.rows();
    if (Array.isArray(list)) {
      const i = list.findIndex((r) => r && r.id_ === id && Number(r.form_id) === Number(fid));
      if (i === -1) list.push(row); else list[i] = row;
      try { root.localStorage.setItem('sendback', typeof root.efb_sendback_for_storage_efb === 'function' ? root.efb_sendback_for_storage_efb(list) : JSON.stringify(list)); } catch (e) {}
    }
    if (typeof root.sendback_state_handler_efb_v4 === 'function') root.sendback_state_handler_efb_v4(id, true, 0, fid);
    const msg = byId(fid, id + '_-message');
    if (msg && msg.dataset.efbChMsg) { msg.textContent = ''; delete msg.dataset.efbChMsg; msg.style.display = 'none'; }
    if (typeof root.updateStepButtonState_efb === 'function') { try { root.updateStepButtonState_efb(Number(fid)); } catch (e) {} }
    if (typeof root.efb_reevaluate_logic_after_change_efb === 'function') { try { root.efb_reevaluate_logic_after_change_efb(fid, w); } catch (e) {} }
  }
  function bindRanking(w) {
    if (w.dataset.efbBound === '1') return;
    w.dataset.efbBound = '1';
    const fid = w.dataset.formid;
    const id = w.dataset.vid;
    const list = w.querySelector('.efb-rank-list');
    if (!list) return;
    if (w.dataset.random === '1') {
      const nodes = rankItems(w);
      applyOrder(list, nodes, sessionOrder(fid, id, nodes.map((n) => n.dataset.op), []), (n) => n.dataset.op);
    }
    renumber(w);
    w.addEventListener('click', (e) => {
      const b = e.target.closest && e.target.closest('button');
      if (!b || !w.contains(b) || w.dataset.disabled === '1') return;
      if (b.classList.contains('efb-rank-confirm')) {
        w.dataset.confirmed = '1';
        b.setAttribute('aria-pressed', 'true');
        rankPush(w, true);
        return;
      }
      const li = b.closest('.efb-rank-item');
      if (!li) return;
      const i = rankItems(w).indexOf(li);
      if (b.classList.contains('efb-rank-up')) rankMove(w, li, i - 1, '.efb-rank-up');
      else if (b.classList.contains('efb-rank-down')) rankMove(w, li, i + 1, '.efb-rank-down');
    });
    w.addEventListener('keydown', (e) => {
      const li = e.target.closest && e.target.closest('.efb-rank-item');
      if (!li || e.target !== li || w.dataset.disabled === '1') return;
      const i = rankItems(w).indexOf(li);
      if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) { e.preventDefault(); rankMove(w, li, e.key === 'ArrowUp' ? i - 1 : i + 1); }
      else if (e.key === 'Home' && (e.altKey || e.ctrlKey || !e.shiftKey)) { e.preventDefault(); rankMove(w, li, 0); }
      else if (e.key === 'End' && (e.altKey || e.ctrlKey || !e.shiftKey)) { e.preventDefault(); rankMove(w, li, rankItems(w).length - 1); }
      else if (!e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
        e.preventDefault();
        const items = rankItems(w);
        const n = items[i + (e.key === 'ArrowUp' ? -1 : 1)];
        if (n) n.focus();
      }
    });
    // Pointer drag (mouse and touch): an 8 px threshold, so scrolling the page is not a drag.
    let drag = null;
    list.addEventListener('pointerdown', (e) => {
      const h = e.target.closest && e.target.closest('.efb-rank-grip');
      if (!h || w.dataset.disabled === '1') return;
      const li = h.closest('.efb-rank-item');
      drag = { li, y: e.clientY, on: false, id: e.pointerId };
      try { h.setPointerCapture(e.pointerId); } catch (x) {}
    });
    list.addEventListener('pointermove', (e) => {
      if (!drag || e.pointerId !== drag.id) return;
      if (!drag.on && Math.abs(e.clientY - drag.y) < 8) return;
      drag.on = true;
      e.preventDefault();
      drag.li.classList.add('efb-rank-drag');
      const items = rankItems(w);
      const over = items.find((x) => { const r = x.getBoundingClientRect(); return e.clientY >= r.top && e.clientY <= r.bottom; });
      if (over && over !== drag.li) {
        const to = items.indexOf(over);
        const from = items.indexOf(drag.li);
        list.insertBefore(drag.li, to > from ? over.nextSibling : over);
        renumber(w);
      }
    });
    const end = (e) => {
      if (!drag || (e && e.pointerId !== drag.id)) return;
      const d = drag;
      drag = null;
      d.li.classList.remove('efb-rank-drag');
      if (d.on) { w.dataset.touched = '1'; rankAnnounce(w, d.li); rankPush(w); }
    };
    list.addEventListener('pointerup', end);
    list.addEventListener('pointercancel', end);
    if (w.dataset.touch === '0') rankPush(w, true);
  }
  /** A saved ranking (a draft, the offline copy) puts the items back in its order. */
  function restoreRanking(fid, row) {
    const w = rankWidgets(fid).find((x) => x.dataset.vid === row.id_);
    if (!w) return false;
    const ids = String(row.ids || '').split(',').filter(Boolean);
    if (!ids.length) return false;
    const list = w.querySelector('.efb-rank-list');
    const nodes = rankItems(w);
    const rest = nodes.map((n) => n.dataset.op).filter((x) => ids.indexOf(x) === -1);
    applyOrder(list, nodes, ids.concat(rest), (n) => n.dataset.op);
    w.dataset.touched = '1';
    renumber(w);
    rankPush(w, true);
    return true;
  }

  /* ------------------------------------------------------------------ checks of Next and Send */

  /** The F05 refusal of one field now, or null: {msg}. Shown at the field by check(). */
  function problem(fid, f) {
    if (!f || !f.id_) return null;
    const list = Array.isArray(G.rows()) ? G.rows().filter((r) => r && r.id_ === f.id_ && Number(r.form_id) === Number(fid)) : [];
    const t = f.type;
    if (TYPES.other.indexOf(t) !== -1 && on(f, 'op_other')) {
      const radio = byId(fid, f.id_ + '_oth');
      const text = byId(fid, f.id_ + '_oth_t');
      if (radio && radio.checked && text && text.value.trim() === '') return { msg: fill(T('otherReq', 'Please describe your “Other” answer for %s.'), [f.name || '']) };
    }
    if (TYPES.minmax.indexOf(t) !== -1) {
      let n = 0;
      if (t === 'multiselect' || t === 'payMultiselect') { const r = list[0]; n = r && typeof r.value === 'string' ? r.value.split('@efb!').filter((x) => x.trim() !== '').length : 0; }
      else n = list.filter((r) => r.id_ob).length;
      const min = num(f, 'minSelect'), max = num(f, 'maxSelect');
      if (min > 0 && n > 0 && n < min) return { msg: fill(T('minSelM_', 'Choose at least %2$s options in %1$s.'), [f.name || '', digits(min)]) };
      if (max > 0 && n > max) return { msg: fill(T('maxSelM_', 'Choose at most %2$s options in %1$s.'), [f.name || '', digits(max)]) };
    }
    if (t === 'ranking' && (f.required == 1 || f.required === true) && list.length === 0) {
      return { msg: fill(T('rnkReq', 'Please put the items in %s in order.'), [f.name || '']) };
    }
    return null;
  }
  /** Next / Send: false (and the sentence at the field, role=alert) when an F05 rule refuses this field. */
  function check(f, fid) {
    const p = problem(fid, f);
    const msg = byId(fid, f.id_ + '_-message');
    if (!p) {
      if (msg && msg.dataset.efbChMsg === 'chk') { msg.textContent = ''; delete msg.dataset.efbChMsg; msg.style.display = 'none'; }
      return true;
    }
    if (msg) {
      msg.textContent = p.msg;
      msg.setAttribute('role', 'alert');
      msg.dataset.efbChMsg = 'chk';
      msg.style.display = 'block';
      msg.classList.remove('d-none');
      if (typeof root.show_msg_efb === 'function') { try { root.show_msg_efb(msg); } catch (e) {} }
    }
    return false;
  }

  /**
   * A refusal of the server at a field (efb_choice_*): the field's choices are cleared (core dropped their answer
   * rows), a choice that filled up meanwhile is marked as such, and the field shows the server's sentence (text).
   */
  function serverError(fid, fieldId, code, m, optionId) {
    fid = String(fid);
    const f = fieldRow(fid, fieldId);
    if (!f) return;
    const box = byId(fid, fieldId + '_options');
    if (box) {
      Array.from(box.querySelectorAll('input[type="checkbox"], input[type="radio"]')).forEach((i) => { i.checked = false; i.indeterminate = false; });
      if (box.tagName === 'SELECT') { try { box.selectedIndex = 0; } catch (e) {} }
      const t = byId(fid, fieldId + '_oth_t');
      if (t) { t.value = ''; t.disabled = true; }
      if (box.dataset.select !== undefined) { box.dataset.select = ''; }
      if (num(f, 'maxSelect') > 0 && f.type.indexOf('ulti') === -1) syncMax(fid, f, box);
    }
    if (code === 'efb_choice_full' && optionId) {
      const o = optionsOf(fid, fieldId).find((x) => x.id_ === optionId);
      if (o) { STATE.stock[fid] = Object.assign({}, STATE.stock[fid] || {}, { [optionId]: 0 }); markOption(fid, f, o, 0, f.inv_show || 'disable'); }
    }
    const msg = byId(fid, fieldId + '_-message');
    if (msg) {
      let text = String(m || '');
      try { text = new root.DOMParser().parseFromString(text, 'text/html').body.textContent || ''; } catch (e) { text = text.replace(/<[^>]*>/g, ''); }
      msg.textContent = text;
      msg.setAttribute('role', 'alert');
      msg.dataset.efbChMsg = 'chk';
      msg.style.display = 'block';
      msg.classList.remove('d-none');
    }
  }

  /* ------------------------------------------------------------------ boot */

  function initForm(fid) {
    fid = String(fid);
    rowsOf(fid).forEach((f, i) => {
      if (i < 1 || !f || f.type === 'option' || !f.id_) return;
      const box = byId(fid, f.id_ + '_options');
      if (on(f, 'op_random') && TYPES.random.indexOf(f.type) !== -1 && f.type !== 'ranking') {
        const k = fid + ':' + f.id_ + ':r';
        if (!STATE.bound[k]) { STATE.bound[k] = 1; shuffleField(fid, f, box); }
      }
      if (!box) return;
      if (TYPES.other.indexOf(f.type) !== -1 && on(f, 'op_other')) bindOther(fid, f, box);
      if (TYPES.minmax.indexOf(f.type) !== -1 && f.type.indexOf('ulti') === -1 && num(f, 'maxSelect') > 0 && box.dataset.efbMax !== '1') {
        box.dataset.efbMax = '1';
        box.addEventListener('change', () => syncMax(fid, f, box));
        syncMax(fid, f, box);
      }
      const all = box.querySelector('.efb-selall input');
      if (all && all.dataset.efbBound !== '1') {
        all.dataset.efbBound = '1';
        all.addEventListener('change', () => {
          const want = all.checked;
          choiceInputs(box).filter((x) => !x.closest('.efb-other') && !x.disabled && x.checked !== want).forEach((x) => {
            x.checked = want;
            if (typeof root.handle_change_event_efb_v4 === 'function') root.handle_change_event_efb_v4(x, fid);
          });
          syncSelectAll(box);
          if (num(f, 'maxSelect') > 0) syncMax(fid, f, box);
        });
        box.addEventListener('change', (e) => { if (e.target !== all) syncSelectAll(box); });
        syncSelectAll(box);
      }
    });
    rankWidgets(fid).forEach(bindRanking);
    if (STATE.stock[fid]) applyStock(fid, STATE.stock[fid]);
  }
  function initAll() {
    const doc = root.document;
    if (!doc) return;
    doc.querySelectorAll('[id^="body_efb_"]').forEach((b) => {
      const fid = b.id.slice(9);
      if (/^\d+$/.test(fid)) initForm(fid);
    });
  }
  /** A saved row of an F05 kind (Other text, a ranking) back in the page; true when handled. */
  function restoreRow(row, fid) {
    if (!row || typeof row !== 'object') return false;
    if (row.type === 'ranking') return restoreRanking(fid, row) || true;
    if (typeof row.id_ob === 'string' && row.id_ob === row.id_ + '_oth') {
      const radio = byId(fid, row.id_ob);
      const text = byId(fid, row.id_ob + '_t');
      if (!radio || !text) return true;
      radio.checked = true;
      text.disabled = false;
      text.value = String(row.value || '');
      radio.value = text.value;
      if (typeof root.handle_change_event_efb_v4 === 'function') root.handle_change_event_efb_v4(radio, fid);
      return true;
    }
    return false;
  }

  if (root && root.document && root.addEventListener) {
    root.document.addEventListener('efb:prepared', (e) => {
      const d = e && e.detail;
      if (d && d.formId && d.stock) applyStock(String(d.formId), d.stock);
    });
  }

  return {
    rules, parseBulk, sessionOrder, applyOrder, digits, fill,
    initForm, initAll, applyStock, check, problem, restoreRow, restoreRanking, serverError,
    _state: STATE,
  };
});
