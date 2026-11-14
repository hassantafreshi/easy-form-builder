/**
 * F14: the entries screen of a form (docs/feature-gaps/F14-entries-management.fa.md §4, §6).
 *
 * One page of entries from the server (efb_entries_query_Emsfb), never every entry in the browser: status tabs
 * with counts, search, a date range with shortcuts, field conditions, chosen columns, pages, star, read / unread,
 * spam, trash with Undo, restore, delete permanently, "select all N results", and keyboard shortcuts (j / k / s / #).
 * Everything written into the page is escaped (escHtmlEfb); numbers and dates use the site's digits (fa / ar); a
 * mixed-script sentence keeps its order (unicode-bidi: plaintext and <bdi> around every inserted value).
 *
 * The response viewer, the old list (fun_ws_show_list_messages) and its CSV stay: a site whose database has not
 * reached DB 1.10 keeps them (efb_db_upgrade), and the survey chart still reads the whole form from the old action.
 * No build step: this file is loaded as is on the panel (class-Emsfb-panel.php) after list_form-efb.js.
 */
(function (root) {
  'use strict';
  const W = root;
  const D = W.document;

  /* ------------------------------------------------------------------ helpers */
  /* Phrases arrive escaped once by esc_html__(): decoded here, escaped again where they are written (esc). */
  const dec = (s) => String(s).replace(/&#0?39;|&#x27;/g, "'").replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
  const T = (k, fb) => { const t = (W.efb_var && W.efb_var.text) || {}; return t[k] !== undefined && t[k] !== null && t[k] !== '' ? dec(t[k]) : fb; };
  const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const cfg = () => (W.efb_var && W.efb_var.entries) || {};
  const locale = () => String((W.efb_var && (W.efb_var.wp_lan || W.efb_var.language)) || 'en_US');
  const script = () => { const l = locale(); return /^(fa|ps|ckb)/.test(l) ? 'fa' : (/^ar(?!y)/.test(l) ? 'ar' : ''); };
  const isRtl = () => !!((W.efb_var && Number(W.efb_var.rtl) === 1) || (D && D.documentElement && D.documentElement.dir === 'rtl'));
  /** Latin digits to the site's (Persian ۰-۹ for fa, Arabic-Indic ٠-٩ for ar). */
  const digits = (s) => { const sc = script(); if (!sc) return String(s); const base = sc === 'fa' ? 0x06F0 : 0x0660; return String(s).replace(/[0-9]/g, (d) => String.fromCharCode(base + Number(d))); };
  /** Persian / Arabic-Indic digits typed by a person to Latin. */
  const latin = (s) => String(s == null ? '' : s).replace(/[۰-۹]/g, (d) => String(d.charCodeAt(0) - 0x06F0)).replace(/[٠-٩]/g, (d) => String(d.charCodeAt(0) - 0x0660));
  /** A count in the site's digits with grouping (1,234 / ۱٬۲۳۴ / ١٬٢٣٤). */
  const num = (n) => { const v = Number(n) || 0; let s; try { s = v.toLocaleString(locale().replace('_', '-')); } catch (e) { s = String(v); } return digits(s); };
  /** A sentence with values: every value is isolated (<bdi>) so a mixed-script sentence keeps its order. */
  const fill = (tpl, vals) => { let out = esc(tpl); vals.forEach((v, i) => { out = out.split(`%${i + 1}$s`).join(`<bdi>${v}</bdi>`); }); if (vals.length === 1) out = out.split('%s').join(`<bdi>${vals[0]}</bdi>`); return out; };
  const icon = (name) => `<i class="efb bi ${name}" aria-hidden="true"></i>`;

  /* The admin scripts keep their state in top-level let bindings (list_form-efb.js, admin-efb.js), which are not
     properties of window: read and write them by name when they exist, else on window (tests, other screens). */
  function getGlobal(name) {
    switch (name) {
      case 'valueJson_ws_messages': return typeof valueJson_ws_messages !== 'undefined' ? valueJson_ws_messages : W[name]; // eslint-disable-line no-undef
      case 'form_type_emsFormBuilder': return typeof form_type_emsFormBuilder !== 'undefined' ? form_type_emsFormBuilder : W[name]; // eslint-disable-line no-undef
      case 'form_ID_emsFormBuilder': return typeof form_ID_emsFormBuilder !== 'undefined' ? form_ID_emsFormBuilder : W[name]; // eslint-disable-line no-undef
    }
    return W[name];
  }
  function setGlobal(name, v) {
    /* eslint-disable no-undef, no-global-assign */
    switch (name) {
      case 'valueJson_ws_messages': if (typeof valueJson_ws_messages !== 'undefined') { valueJson_ws_messages = v; return; } break;
      case 'form_ID_emsFormBuilder': if (typeof form_ID_emsFormBuilder !== 'undefined') { form_ID_emsFormBuilder = v; return; } break;
    }
    /* eslint-enable no-undef, no-global-assign */
    W[name] = v;
  }

  /* ------------------------------------------------------------------ state */
  const S = {
    fid: 0, formType: 'form', page: 1, per_page: 20, tab: 'all', from: '', to: '', q: '', conds: [], sort: 'date_desc',
    cols: [], fields: null, rows: [], total: 0, estimate: false, counts: {}, selected: new Set(), allMatching: false,
    loading: false, focusIdx: -1, filtersOpen: false, undo: null, lastLive: '',
  };
  const TABS = [['all', 'entAll', 'All'], ['unread', 'entUnread', 'Unread'], ['starred', 'entStarred', 'Starred'], ['pending', 'entPending', 'Payment pending'], ['closed', 'entClosed', 'Closed'], ['spam', 'entSpam', 'Spam'], ['trash', 'entTrash', 'Trash']];
  const META_COLS = [['_ip', 'ip', 'IP'], ['_notes', 'entNotes', 'Notes'], ['_edited', 'entEditedCol', 'Last edit'], ['_status', 'entStatusCol', 'Status']];
  const planOk = (part) => { const p = cfg().plan; return !p || p[part] !== false; };
  const feature = (part) => (cfg().features || {})[part] || ('setting:entries_' + part);
  const gate = (part, opener) => { if (planOk(part)) return true; if (typeof W.efb_gate_efb === 'function') W.efb_gate_efb(feature(part), { fallback: part === 'nobrand' ? 'pro' : 'free_plus', origin: 'entries', opener }); return false; };

  /* ------------------------------------------------------------------ server */
  function post(data) {
    const nonce = (typeof W._efb_core_nonce_ !== 'undefined' && W._efb_core_nonce_) || (W.efb_var && W.efb_var.nonce) || '';
    const body = new URLSearchParams(Object.assign({ nonce }, data));
    const url = (W.ajax_object_efm && W.ajax_object_efm.ajax_url) || (W.efb_var && W.efb_var.ajax_url) || W.ajaxurl;
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body })
      .then((r) => r.json()).then((j) => (j && j.data !== undefined ? j.data : j)).catch(() => ({ success: false, code: 'efb_network', m: T('somethingWentWrongPleaseRefresh', 'Something went wrong. Please refresh the page.') }));
  }
  const filterArgs = () => ({ tab: S.tab, from: S.from, to: S.to, q: S.q, conds: S.conds.filter((c) => c.id && c.op && (c.op === 'empty' || c.op === 'filled' || String(c.v).trim() !== '')), sort: S.sort });

  /* ------------------------------------------------------------------ the stored answers */
  function rowsOf(content) {
    try {
      const raw = typeof W.replaceContentMessageEfb === 'function' ? W.replaceContentMessageEfb(content, true) : String(content).replace(/\\"/g, '"');
      const v = typeof W.efbParseJson === 'function' ? W.efbParseJson(raw) : JSON.parse(raw);
      return Array.isArray(v) ? v : [];
    } catch (e) { return []; }
  }
  /** The answer of one field as text (checkbox options and repeater rows joined), stored quote token decoded. */
  function valueOf(rows, id) {
    const out = [];
    for (const r of rows) {
      if (!r || String(r.id_) !== String(id) || r.value === undefined || r.value === null || typeof r.value === 'object') continue;
      let v = String(r.value);
      if (v === '@file@') v = r.url ? String(r.url).split('/').pop() : '';
      if (v === '@prv@') v = T('prvRedacted', 'Removed for privacy');
      v = v.replace(/@efb@dq#/g, '"').replace(/@efb!/g, ', ').replace(/@n#/g, ' ').replace(/,\s*$/, '').trim();
      if (typeof r.disp === 'string' && r.disp !== '') v = r.disp;
      if (v !== '') out.push(r.ri !== undefined && r.ri !== null && r.ri !== '' ? `${v}` : v);
    }
    return out.join(' | ');
  }
  /**
   * The content preview: "label: value | label: value", the first 50 characters. Each label and each value is
   * isolated (<bdi>) so a Persian label beside a Latin value, or the reverse, keeps the order it was typed in.
   * short is HTML (escaped), full is plain text (the tooltip).
   */
  function preview(content) {
    const skip = ['maps', 'esign', 'payment', 'w_link', 'repeater'];
    const parts = [];
    for (const r of rowsOf(content)) {
      if (!r || r.value === undefined || r.value === null || r.value === '' || r.value === '@file@' || typeof r.value === 'object' || skip.includes(r.type)) continue;
      const v = (r.value === '@prv@' ? T('prvRedacted', 'Removed for privacy') : String(typeof r.disp === 'string' && r.disp !== '' ? r.disp : r.value))
        .replace(/<[^>]*>/g, '').replace(/@efb@dq#/g, '"').replace(/@efb!/g, ', ').replace(/@n#/g, ' ').replace(/@efb[^#]*#/g, ' ').replace(/,\s*$/, '').trim();
      if (v === '') continue;
      parts.push([String(r.name || r.id_ || '').replace(/@efb@dq#/g, '"'), v]);
    }
    if (!parts.length) return { short: '—', full: '—' };
    const full = parts.map((x) => (x[0] ? x[0] + ': ' : '') + x[1]).join(' | ');
    let left = 50;
    const out = [];
    for (const [l, v] of parts) {
      if (left <= 0) break;
      const lab = l.slice(0, left); left -= lab.length + 2;
      const val = left > 0 ? (v.length > left ? v.slice(0, Math.max(0, left)) + '…' : v) : '';
      left -= val.length + 3;
      out.push(`${lab ? `<bdi>${esc(lab)}</bdi>: ` : ''}${val ? `<bdi>${esc(val)}</bdi>` : '…'}`);
    }
    return { short: out.join(' | '), full };
  }

  /* ------------------------------------------------------------------ rendering (pure: state → HTML) */
  function colLabel(c) {
    const meta = META_COLS.find((m) => m[0] === c);
    if (meta) return T(meta[1], meta[2]);
    const f = (S.fields || []).find((x) => x.id === c);
    return f ? f.name : c;
  }

  /** The table head for the chosen columns (aria-sort on the date). */
  function renderHead(cols) {
    const sortDir = S.sort === 'date_asc' ? 'ascending' : 'descending';
    const extra = cols.map((c) => `<th scope="col" class="efb efb-ent-col" data-col="${esc(c)}">${esc(colLabel(c))}</th>`).join('');
    return `<tr>
      <th scope="col" class="efb efb-ent-cb"><input class="efb form-check-input efb-ent-all" type="checkbox" aria-label="${esc(T('entSelectPage', 'Select every entry on this page'))}"></th>
      <th scope="col" class="efb efb-ent-starcol"><span class="visually-hidden">${esc(T('entStarred', 'Starred'))}</span>${icon('bi-star')}</th>
      <th scope="col" class="efb">${esc(T('content', 'Content'))}</th>
      <th scope="col" class="efb">${esc(T('trackNo', 'Confirmation Code'))}</th>
      <th scope="col" class="efb" aria-sort="${sortDir}"><button type="button" class="efb efb-ent-sort" data-ent="sort">${esc(T('ddate', 'Date'))} ${icon(S.sort === 'date_asc' ? 'bi-sort-up' : 'bi-sort-down')}</button></th>
      ${extra}
      <th scope="col" class="efb">${esc(T('actions', 'Actions'))}</th>
    </tr>`;
  }

  function cellOf(row, rows, c) {
    if (c === '_ip') return `<bdi dir="ltr">${esc(row.ip || '')}</bdi>`;
    if (c === '_notes') return row.notes_count ? `<span class="efb badge bg-warning text-dark">${num(row.notes_count)}</span>` : '';
    if (c === '_edited') return row.edited_at ? `<bdi>${esc(digits(row.edited_at))}</bdi>` : '';
    if (c === '_status') return statusBadge(row);
    const full = valueOf(rows, c);
    const short = full.length > 60 ? full.slice(0, 60) + '…' : full;
    return `<span class="efb efb-ent-val" title="${esc(full)}"><bdi>${esc(short)}</bdi></span>`;
  }

  function statusBadge(row) {
    if (Number(row.entry_status) === 1) return `<span class="efb badge efb-ent-badge efb-ent-badge-spam">${esc(T('entSpam', 'Spam'))}</span>`;
    if (Number(row.entry_status) === 2) return `<span class="efb badge efb-ent-badge efb-ent-badge-trash">${esc(T('entTrash', 'Trash'))}</span>`;
    if (Number(row.read_) === 2) return `<span class="efb badge efb-ent-badge efb-ent-badge-pending">${esc(T('entPending', 'Payment pending'))}</span>`;
    return '';
  }

  /** One row: check, star, preview, code, date, chosen columns, actions; every cell has data-label (phone cards). */
  function renderRow(row, cols) {
    const id = Number(row.msg_id);
    const rows = rowsOf(row.content);
    const pv = preview(row.content);
    const unread = Number(row.entry_status) === 0 && (Number(row.read_) === 0 || Number(row.read_) === 3);
    const starred = Number(row.starred) === 1;
    const st = Number(row.entry_status);
    const sel = S.allMatching || S.selected.has(id);
    const extra = cols.map((c) => `<td class="efb efb-ent-col" data-col="${esc(c)}" data-label="${esc(colLabel(c))}">${cellOf(row, rows, c)}</td>`).join('');
    const edited = row.edited_at ? ` <span class="efb badge efb-ent-badge efb-ent-badge-edited" title="${esc(digits(row.edited_at))}">${esc(T('entEditedShort', 'Edited'))}</span>` : '';
    const notes = row.notes_count && !cols.includes('_notes') ? ` <span class="efb efb-ent-notes-ind" title="${esc(T('entNotes', 'Notes'))}">${icon('bi-sticky')}<bdi>${num(row.notes_count)}</bdi></span>` : '';
    return `<tr class="efb efb-ent-row${unread ? ' efb-ent-unread' : ''}${sel ? ' efb-ent-selected' : ''}" data-msgid="${id}" tabindex="-1">
      <td class="efb efb-ent-cb" data-label="${esc(T('select', 'Select'))}"><input class="efb form-check-input efb-ent-one" type="checkbox" value="${id}" ${sel ? 'checked' : ''} aria-label="${esc(T('select', 'Select'))} ${esc(row.track)}"></td>
      <td class="efb efb-ent-starcol" data-label="${esc(T('entStarred', 'Starred'))}"><button type="button" class="efb efb-ent-star${starred ? ' is-on' : ''}" data-ent="star" data-msgid="${id}" aria-pressed="${starred ? 'true' : 'false'}" aria-label="${esc(starred ? T('entUnstar', 'Remove star') : T('entStar', 'Star'))}">${icon(starred ? 'bi-star-fill' : 'bi-star')}</button></td>
      <td class="efb efb-content-cell" data-label="${esc(T('content', 'Content'))}"><button type="button" class="efb efb-ent-open-txt" data-ent="open" data-msgid="${id}"><span class="efb-preview-text" title="${esc(pv.full)}">${pv.short}</span></button>${statusBadge(row) ? ' ' + statusBadge(row) : ''}${edited}${notes}</td>
      <td class="efb" data-label="${esc(T('trackNo', 'Confirmation Code'))}"><code class="efb text-muted user-select-all"><bdi dir="ltr">${esc(row.track)}</bdi></code></td>
      <td class="efb" data-label="${esc(T('ddate', 'Date'))}"><bdi>${esc(digits(row.date))}</bdi></td>
      ${extra}
      <td class="efb efb-actions-cell" data-label="${esc(T('actions', 'Actions'))}"><div class="efb-actions-group">
        <button type="button" class="efb efb-act-btn efb-act-open${unread ? ' efb-has-badge' : ''}" id="btn-m-${id}" data-ent="open" data-msgid="${id}" aria-label="${esc(unread ? T('newResponse', 'New response') : T('read', 'Read'))}">${icon(unread ? 'bi-envelope-fill' : 'bi-envelope-open')}</button>
        ${st === 2
          ? `<button type="button" class="efb efb-act-btn" data-ent="op" data-op="restore" data-msgid="${id}" aria-label="${esc(T('entRestore', 'Restore'))}">${icon('bi-arrow-counterclockwise')}</button>
             <button type="button" class="efb efb-act-btn efb-act-delete" data-ent="op" data-op="delete" data-msgid="${id}" aria-label="${esc(T('entDeleteForever', 'Delete permanently'))}">${icon('bi-x-octagon')}</button>`
          : `<button type="button" class="efb efb-act-btn efb-act-delete" data-ent="op" data-op="trash" data-msgid="${id}" aria-label="${esc(T('entMoveTrash', 'Move to trash'))}">${icon('bi-trash3')}</button>`}
        <button type="button" class="efb efb-act-btn efb-ent-more" data-ent="menu" data-msgid="${id}" aria-haspopup="menu" aria-expanded="false" aria-label="${esc(T('entMore', 'More actions'))}">${icon('bi-three-dots-vertical')}</button>
      </div></td>
    </tr>`;
  }

  function renderTabs() {
    return `<div class="efb efb-ent-tabs" role="tablist" aria-label="${esc(T('entStatusTabs', 'Entry status'))}">${TABS.filter((t) => t[0] !== 'pending' || S.formType === 'payment' || (S.counts.pending || 0) > 0).map((t) => {
      const on = S.tab === t[0];
      return `<button type="button" role="tab" class="efb efb-ent-tab${on ? ' active' : ''}" id="efbEntTab-${t[0]}" aria-selected="${on ? 'true' : 'false'}" aria-controls="efbEntPanel" tabindex="${on ? '0' : '-1'}" data-ent="tab" data-tab="${t[0]}">${esc(T(t[1], t[2]))} <span class="efb efb-ent-count"><bdi>${num(S.counts[t[0]] || 0)}</bdi></span></button>`;
    }).join('')}</div>`;
  }

  function renderConds() {
    const fields = (S.fields || []).filter((f) => f.type !== 'repeater');
    return S.conds.map((c, i) => {
      const f = fields.find((x) => x.id === c.id);
      const opts = f && Array.isArray(f.options) && f.options.length;
      const val = c.op === 'empty' || c.op === 'filled' ? '' : (opts
        ? `<select class="efb form-select form-select-sm efb-ent-cond-v" data-i="${i}" aria-label="${esc(T('value', 'Value'))}"><option value=""></option>${f.options.map((o) => `<option value="${esc(o.value)}" ${o.value === c.v ? 'selected' : ''}>${esc(o.value)}</option>`).join('')}</select>`
        : `<input type="text" class="efb form-control form-control-sm efb-ent-cond-v" data-i="${i}" value="${esc(c.v)}" aria-label="${esc(T('value', 'Value'))}" dir="auto">`);
      return `<div class="efb efb-ent-cond" data-i="${i}">
        <select class="efb form-select form-select-sm efb-ent-cond-f" data-i="${i}" aria-label="${esc(T('field', 'Field'))}"><option value=""></option>${fields.map((x) => `<option value="${esc(x.id)}" ${x.id === c.id ? 'selected' : ''}>${esc(x.name)}</option>`).join('')}</select>
        <select class="efb form-select form-select-sm efb-ent-cond-op" data-i="${i}" aria-label="${esc(T('entCondition', 'Condition'))}">${[['eq', 'entOpEq', 'is'], ['has', 'entOpHas', 'contains'], ['empty', 'entOpEmpty', 'is empty'], ['filled', 'entOpFilled', 'is not empty']].map((o) => `<option value="${o[0]}" ${o[0] === c.op ? 'selected' : ''}>${esc(T(o[1], o[2]))}</option>`).join('')}</select>
        ${val}
        <button type="button" class="efb btn btn-sm btn-outline-secondary" data-ent="cond-del" data-i="${i}" aria-label="${esc(T('delete', 'Delete'))}">${icon('bi-x-lg')}</button>
      </div>`;
    }).join('');
  }

  function renderFilters() {
    const gem = planOk('filter') ? '' : ` ${icon('bi-gem')}`;
    return `<div class="efb efb-ent-filters${S.filtersOpen ? ' is-open' : ''}">
      <button type="button" class="efb btn btn-sm btn-outline-secondary efb-ent-filters-toggle" data-ent="filters" aria-expanded="${S.filtersOpen ? 'true' : 'false'}" aria-controls="efbEntFiltersBody">${icon('bi-funnel')} ${esc(T('entFilters', 'Filters'))}</button>
      <div class="efb efb-ent-filters-body" id="efbEntFiltersBody">
        <div class="efb efb-ent-search"><label class="efb visually-hidden" for="efbEntQ">${esc(T('search', 'Search'))}</label>
          <input type="search" class="efb form-control form-control-sm" id="efbEntQ" maxlength="100" value="${esc(S.q)}" placeholder="${esc(T('entSearchPh', 'Search answers, codes and notes'))}" dir="auto"></div>
        <div class="efb efb-ent-dates">
          <label class="efb" for="efbEntFrom">${esc(T('entFrom', 'From'))}</label><input type="date" class="efb form-control form-control-sm" id="efbEntFrom" value="${esc(S.from)}">
          <label class="efb" for="efbEntTo">${esc(T('entTo', 'To'))}</label><input type="date" class="efb form-control form-control-sm" id="efbEntTo" value="${esc(S.to)}">
          <span class="efb efb-ent-shortcuts">${[['today', 'entToday', 'Today'], ['7', 'entLast7', 'Last 7 days'], ['30', 'entLast30', 'Last 30 days'], ['month', 'entThisMonth', 'This month']].map((x) => `<button type="button" class="efb btn btn-sm btn-link" data-ent="range" data-range="${x[0]}">${esc(T(x[1], x[2]))}</button>`).join('')}</span>
        </div>
        <div class="efb efb-ent-conds">${renderConds()}</div>
        <div class="efb efb-ent-filter-actions">
          ${S.conds.length < 3 ? `<button type="button" class="efb btn btn-sm btn-outline-pink" data-ent="cond-add">${icon('bi-plus-lg')} ${esc(T('entAddCond', 'Add condition'))}${gem}</button>` : ''}
          <button type="button" class="efb btn btn-sm btn-outline-secondary" data-ent="clear">${esc(T('entClearFilters', 'Clear filters'))}</button>
        </div>
      </div>
    </div>`;
  }

  function renderPager() {
    const from = S.total ? (S.page - 1) * S.per_page + 1 : 0;
    const to = Math.min(S.total, (S.page - 1) * S.per_page + S.rows.length);
    const pages = Math.max(1, Math.ceil(S.total / S.per_page));
    const totalTxt = S.estimate ? fill(T('entMoreThan', 'More than %s'), [num(S.total)]) : `<bdi>${num(S.total)}</bdi>`;
    const list = [];
    const add = (p) => { if (p >= 1 && p <= pages && !list.includes(p)) list.push(p); };
    [1, S.page - 1, S.page, S.page + 1, pages].forEach(add);
    list.sort((a, b) => a - b);
    let btns = '';
    let last = 0;
    for (const p of list) {
      if (last && p - last > 1) btns += '<span class="efb efb-ent-gap" aria-hidden="true">…</span>';
      btns += `<button type="button" class="efb btn btn-sm ${p === S.page ? 'btn-primary' : 'btn-outline-secondary'}" data-ent="page" data-page="${p}" ${p === S.page ? 'aria-current="page"' : ''} aria-label="${esc(T('page', 'Page'))} ${digits(p)}">${digits(p)}</button>`;
      last = p;
    }
    return `<nav class="efb efb-ent-pager" aria-label="${esc(T('entPages', 'Pages'))}">
      <span class="efb efb-ent-showing">${esc(T('entShowingXY', 'Showing %1$s–%2$s of %3$s')).split('%1$s').join(`<bdi>${num(from)}</bdi>`).split('%2$s').join(`<bdi>${num(to)}</bdi>`).split('%3$s').join(totalTxt)}</span>
      <span class="efb efb-ent-pages">
        <button type="button" class="efb btn btn-sm btn-outline-secondary" data-ent="page" data-page="${S.page - 1}" ${S.page <= 1 ? 'disabled' : ''}>${esc(T('previous', 'Previous'))}</button>
        ${btns}
        <button type="button" class="efb btn btn-sm btn-outline-secondary" data-ent="page" data-page="${S.page + 1}" ${S.page >= pages ? 'disabled' : ''}>${esc(T('next', 'Next'))}</button>
      </span>
      <label class="efb efb-ent-pp"><span>${esc(T('entPerPage', 'Per page'))}</span> <select class="efb form-select form-select-sm" id="efbEntPP">${[20, 50, 100].map((n) => `<option value="${n}" ${n === S.per_page ? 'selected' : ''}>${digits(n)}</option>`).join('')}</select></label>
    </nav>`;
  }

  /** The actions of the selection (or of one row's menu): the trash has restore / delete, the spam "not spam". */
  function bulkButtons() {
    const b = (op, k, fb, ic, extra) => `<button type="button" class="efb btn btn-sm ${extra || 'btn-outline-secondary'}" data-ent="bulk" data-op="${op}">${icon(ic)} <span>${esc(T(k, fb))}</span></button>`;
    if (S.tab === 'trash') return b('restore', 'entRestore', 'Restore', 'bi-arrow-counterclockwise') + b('delete', 'entDeleteForever', 'Delete permanently', 'bi-x-octagon', 'btn-danger');
    let h = b('read', 'mread', 'Mark as read', 'bi-envelope-open') + b('unread', 'entMarkUnread', 'Mark as unread', 'bi-envelope') + b('star', 'entStar', 'Star', 'bi-star-fill') + b('unstar', 'entUnstar', 'Remove star', 'bi-star');
    h += S.tab === 'spam' ? b('unspam', 'entNotSpam', 'Not spam', 'bi-shield-check') : b('spam', 'entMarkSpam', 'Mark as spam', 'bi-shield-exclamation');
    h += b('trash', 'entMoveTrash', 'Move to trash', 'bi-trash3', 'btn-outline-danger');
    for (const x of (W.EfbEntries && W.EfbEntries.extraBulk) || []) h += x();
    return h;
  }

  function renderBulkBar() {
    const n = S.allMatching ? S.total : S.selected.size;
    return `<div class="efb efb-ent-bulkbar${n ? ' is-on' : ''}" role="region" aria-label="${esc(T('entSelection', 'Selected entries'))}" ${n ? '' : 'hidden'}>
      <span class="efb efb-ent-nsel">${fill(T('entNSelected', '%s selected'), [num(n)])}</span>
      <span class="efb efb-ent-bulkbtns">${bulkButtons()}</span>
      <button type="button" class="efb btn btn-sm btn-link" data-ent="unselect">${esc(T('entUnselect', 'Clear selection'))}</button>
    </div>`;
  }

  function renderSelectAll() {
    const pageAll = S.rows.length > 0 && S.rows.every((r) => S.selected.has(Number(r.msg_id)));
    if (S.allMatching) return `<div class="efb efb-ent-selall">${fill(T('entAllSelectedN', 'All %s results are selected.'), [num(S.total)])} <button type="button" class="efb btn btn-sm btn-link" data-ent="unselect">${esc(T('entUnselect', 'Clear selection'))}</button></div>`;
    if (pageAll && S.total > S.rows.length) return `<div class="efb efb-ent-selall">${fill(T('entPageSelected', 'All %s entries on this page are selected.'), [num(S.rows.length)])} <button type="button" class="efb btn btn-sm btn-link" data-ent="selall">${fill(T('entSelectAllN', 'Select all %s results'), [S.estimate ? T('entMoreThan', 'More than %s').replace('%s', num(S.total)) : num(S.total)])}</button></div>`;
    return '';
  }

  function renderEmpty() {
    const filtered = S.q || S.from || S.to || S.conds.length;
    return `<tr class="efb"><td colspan="${6 + S.cols.length}" class="efb text-center py-5"><div class="efb-empty-state">${icon('bi-inbox efb-empty-icon')}
      <p class="efb-empty-title">${esc(filtered ? T('entNoMatch', 'No entries match these filters.') : (S.tab === 'trash' ? T('entTrashEmpty', 'The trash is empty.') : T('noResponse', 'No response')))}</p>
      ${S.tab === 'trash' && cfg().trash_days ? `<p class="efb-empty-desc">${fill(T('entTrashAuto', 'Entries in the trash are deleted after %s days.'), [num(cfg().trash_days)])}</p>` : ''}</div></td></tr>`;
  }

  /** The whole screen (head buttons, tabs, filters, table, pages, selection bar, live region). */
  function renderAll() {
    const t = (k, fb) => esc(T(k, fb));
    const hb = (W.EfbEntries && W.EfbEntries.headButtons) || [];
    const head = hb.length ? hb.map((f) => f()).join('') : `<button type="button" class="efb btn btn-sm btn-primary text-white ec-efb" data-eventform="generateCSV" data-formtype="${esc(S.formType)}" title="${t('downloadCSVFileSub', 'Download CSV')}">${icon('bi-download')} <span>${t('downloadCSVFile', 'CSV')}</span></button>`;
    const partial = (S.formType === 'form' || S.formType === 'payment') ? `<button type="button" class="efb btn btn-sm btn-outline-pink" id="efbPartialTabBtn" onclick="efb_partial_tab_efb()">${icon('bi-hourglass-split')} <span>${t('partialEntries', 'Partial')}</span></button>` : '';
    const survey = S.formType === 'survey' ? `<button type="button" class="efb btn btn-sm btn-outline-pink" data-ent="chart">${icon('bi-bar-chart-line')} <span>${t('chart', 'Chart')}</span></button>` : '';
    return `<div class="efb efb-ent ${isRtl() ? 'efb-ent-rtl' : ''}" id="efbEnt" data-fid="${S.fid}">
      <div class="efb efb-ent-head">
        <h2 class="efb efb-ent-title fs-5" id="efbEntTitle">${icon('bi-archive')} ${t('messages', 'Responses')}</h2>
        <div class="efb efb-ent-headbtns">${head}${survey}${partial}
          <button type="button" class="efb btn btn-sm btn-outline-secondary" data-ent="cols" aria-haspopup="dialog">${icon('bi-layout-three-columns')} <span>${t('entColumns', 'Columns')}</span></button>
        </div>
      </div>
      ${renderTabs()}
      ${renderFilters()}
      <div class="efb visually-hidden" id="efbEntLive" role="status" aria-live="polite" aria-atomic="true"></div>
      <div id="efbEntPanel" role="tabpanel" aria-labelledby="efbEntTab-${S.tab}">
        ${renderSelectAll()}
        <div class="efb card efb efb-ent-card">
        <table class="efb table table-hover efb-ent-table" id="emsFormBuilder-list" aria-labelledby="efbEntTitle" aria-busy="${S.loading ? 'true' : 'false'}">
          <thead class="efb">${renderHead(S.cols)}</thead>
          <tbody class="efb">${S.rows.length ? S.rows.map((r) => renderRow(r, S.cols)).join('') : renderEmpty()}</tbody>
        </table>
        </div>
        ${renderPager()}
      </div>
      ${renderBulkBar()}
      <div class="efb efb-ent-toast" id="efbEntToast" role="status" aria-live="polite" hidden></div>
      ${typeof W.efb_powered_by === 'function' ? W.efb_powered_by() : ''}
    </div>`;
  }

  /* ------------------------------------------------------------------ actions */
  function announce(text) {
    const live = D.getElementById('efbEntLive');
    if (!live) return;
    live.textContent = '';
    setTimeout(() => { live.textContent = text; }, 30);
    S.lastLive = text;
  }
  const plain = (html) => String(html).replace(/<[^>]*>/g, '').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>');

  function saveState() {
    const q = filterArgs();
    q.page = S.page;
    const enc = (() => { try { return btoa(unescape(encodeURIComponent(JSON.stringify(q)))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); } catch (e) { return ''; } })();
    try { sessionStorage.setItem('efb_entq_' + S.fid, JSON.stringify(q)); } catch (e) { /* storage off: the URL keeps it */ }
    try {
      const u = new URL(W.location.href);
      u.searchParams.set('efbq', enc);
      W.history.replaceState(W.history.state, '', u.toString());
    } catch (e) { /* no URL API */ }
  }
  function restoreState() {
    let q = null;
    try { const u = new URL(W.location.href); const v = u.searchParams.get('efbq'); if (v) q = JSON.parse(decodeURIComponent(escape(atob(v.replace(/-/g, '+').replace(/_/g, '/'))))); } catch (e) { q = null; }
    if (!q) { try { q = JSON.parse(sessionStorage.getItem('efb_entq_' + S.fid) || 'null'); } catch (e) { q = null; } }
    if (!q || typeof q !== 'object') return;
    if (TABS.some((t) => t[0] === q.tab)) S.tab = q.tab;
    S.from = /^\d{4}-\d{2}-\d{2}$/.test(q.from || '') ? q.from : '';
    S.to = /^\d{4}-\d{2}-\d{2}$/.test(q.to || '') ? q.to : '';
    S.q = typeof q.q === 'string' ? q.q.slice(0, 100) : '';
    S.conds = Array.isArray(q.conds) ? q.conds.slice(0, 3).filter((c) => c && typeof c.id === 'string') : [];
    S.sort = q.sort === 'date_asc' ? 'date_asc' : 'date_desc';
    S.page = Math.max(1, Number(q.page) || 1);
  }

  function load(opts) {
    opts = opts || {};
    S.loading = true;
    const tbl = D.getElementById('emsFormBuilder-list');
    if (tbl) tbl.setAttribute('aria-busy', 'true');
    const args = filterArgs();
    const data = { action: 'efb_entries_query_Emsfb', form_id: S.fid, page: S.page, per_page: S.per_page, tab: args.tab, from: args.from, to: args.to, q: args.q, sort: args.sort, conds: JSON.stringify(args.conds) };
    if (S.fields === null) data.fields = '1';
    return post(data).then((res) => {
      S.loading = false;
      if (!res || res.success !== true) {
        if (res && res.code === 'efb_db_upgrade') return legacy();
        if (res && res.code === 'efb_plan_required') { S.conds = []; gate('filter'); return load(); }
        if (typeof W.alert_message_efb === 'function') W.alert_message_efb('', (res && res.m) || T('somethingWentWrongPleaseRefresh', 'Something went wrong. Please refresh the page.'), 8, 'danger');
        return null;
      }
      if (Array.isArray(res.fields)) S.fields = res.fields;
      if (Array.isArray(res.cols)) S.cols = res.cols.filter((c) => c.charAt(0) === '_' || (S.fields || []).some((f) => f.id === c));
      S.rows = res.rows || [];
      S.total = Number(res.total) || 0;
      S.estimate = !!res.total_is_estimate;
      S.counts = res.counts || {};
      // efb_var may be frozen on this screen (the old list's same assignments failed silently there too).
      try { if (res.nonce_msg && W.efb_var) W.efb_var.nonce_msg = res.nonce_msg; } catch (e) { /* read-only */ }
      try { if (W.efb_var) W.efb_var.msg_id = S.fid; } catch (e) { /* read-only */ }
      // The response viewer reads the open page from valueJson_ws_messages (the old list's shape, string ids).
      setGlobal('valueJson_ws_messages', S.rows.map((r) => ({ msg_id: String(r.msg_id), form_id: String(r.form_id), track: r.track, content: r.content, date: r.date, ip: r.ip || '', read_: String(r.read_), read_by: '0', entry_status: r.entry_status, starred: r.starred, edited_at: r.edited_at })));
      if (S.page > 1 && !S.rows.length && S.total > 0) { S.page = Math.max(1, Math.ceil(S.total / S.per_page)); return load(opts); }
      paint();
      // The page's CSV of the old list (window.efb_export_rows_efb, in memory) stays for this page's rows.
      if (S.formType !== 'login' && typeof W.fun_export_rows_for_Subscribe_emsFormBuilder === 'function') { W.efb_export_src_efb = getGlobal('valueJson_ws_messages'); try { W.fun_export_rows_for_Subscribe_emsFormBuilder(getGlobal('valueJson_ws_messages')); } catch (e) { /* the CSV of the page only */ } }
      saveState();
      const n = S.estimate ? T('entMoreThan', 'More than %s').replace('%s', num(S.total)) : num(S.total);
      announce(plain(fill(S.total === 1 ? T('entOneResult', '%s result') : T('entNResults', '%s results'), [n])));
      if (opts.focus) { const el = D.querySelector(opts.focus); if (el) el.focus(); }
      return res;
    });
  }

  /** The old list (a database that has not reached DB 1.10 yet): the old action and renderer, as before F14. */
  function legacy() {
    if (typeof W.fun_get_messages_by_id_legacy_efb === 'function') W.fun_get_messages_by_id_legacy_efb(S.fid);
    return null;
  }

  function paint() {
    const host = D.getElementById('content-efb');
    if (!host) return;
    const active = D.activeElement && D.activeElement.id ? D.activeElement.id : '';
    host.innerHTML = renderAll();
    if (active && D.getElementById(active)) D.getElementById(active).focus();
    const all = host.querySelector('.efb-ent-all');
    if (all) { const n = S.rows.filter((r) => S.selected.has(Number(r.msg_id))).length; all.checked = S.allMatching || (n > 0 && n === S.rows.length); all.indeterminate = !S.allMatching && n > 0 && n < S.rows.length; }
    if (W.EfbEntries && Array.isArray(W.EfbEntries.afterPaint)) W.EfbEntries.afterPaint.forEach((f) => f(host));
  }

  /** Bulk or one-row operation. ids = list, or null for the selection (or every matching result). */
  function op(name, ids, opener) {
    if (name === 'delete' && typeof W.efb_gate_efb === 'function' && !W.efb_gate_efb('setting:delete_response', { fallback: 'free_plus', origin: 'responses', opener })) return Promise.resolve(null);
    if ((name === 'read' || name === 'unread') && !ids && typeof W.efb_gate_efb === 'function' && !W.efb_gate_efb('setting:bulk_read', { fallback: 'free_plus', origin: 'responses', opener })) return Promise.resolve(null);
    const data = { action: 'efb_entries_bulk_Emsfb', form_id: S.fid, op: name };
    let list = ids;
    if (!list && S.allMatching) data.filter = JSON.stringify(filterArgs());
    else data.ids = JSON.stringify(list || Array.from(S.selected));
    if (!data.filter && !JSON.parse(data.ids).length) return Promise.resolve(null);
    const doIt = () => post(data).then((res) => {
      if (!res || res.success !== true) {
        if (res && res.code === 'efb_plan_required') gate('filter', opener);
        else if (typeof W.alert_message_efb === 'function') W.alert_message_efb('', (res && res.m) || T('error', 'Error'), 8, 'danger');
        return null;
      }
      const n = Number(res.done) || 0;
      S.counts = res.counts || S.counts;
      if (!ids) { S.selected.clear(); S.allMatching = false; }
      const msgs = { trash: ['entMovedTrash', '%s entries moved to trash.'], restore: ['entRestored', '%s entries restored.'], delete: ['entDeleted', '%s entries deleted permanently.'], spam: ['entMarkedSpam', '%s entries marked as spam.'], unspam: ['entMarkedNotSpam', '%s entries moved back from spam.'], read: ['entMarkedRead', '%s entries marked as read.'], unread: ['entMarkedUnread', '%s entries marked as unread.'], star: ['entStarredN', '%s entries starred.'], unstar: ['entUnstarredN', '%s stars removed.'] };
      const text = plain(fill(T(msgs[name][0], msgs[name][1]), [num(n)]));
      return load().then(() => {
        announce(text);
        if (name === 'trash' && n > 0) toast(text, () => op('restore', res.ids, null));
        else if (n > 0 && name !== 'star' && name !== 'unstar') toast(text, null);
        return res;
      });
    });
    if (name === 'delete') {
      // The question in this screen's own dialog (focus kept inside, Escape = no).
      return new Promise((resolve) => {
        const d = dialog(T('entDeleteForever', 'Delete permanently'), `<p class="efb m-0">${esc(T('entDeleteConfirm', 'Delete permanently? This cannot be undone.'))}</p>`,
          `<button type="button" class="efb btn btn-sm btn-danger" id="efbEntDelYes">${icon('bi-x-octagon')} ${esc(T('entDeleteForever', 'Delete permanently'))}</button>`, { focus: '#efbEntDelYes' });
        d.querySelector('#efbEntDelYes').addEventListener('click', () => { closeDialog(); resolve(doIt()); });
      });
    }
    return doIt();
  }

  /** A short message with an optional Undo, for 8 seconds (spec §4.1). */
  function toast(text, undo) {
    const el = D.getElementById('efbEntToast');
    if (!el) return;
    clearTimeout(S.toastTimer);
    el.innerHTML = `<span>${esc(text)}</span>${undo ? ` <button type="button" class="efb btn btn-sm btn-light" id="efbEntUndo">${esc(T('entUndo', 'Undo'))}</button>` : ''}`;
    el.hidden = false;
    if (undo) {
      const b = D.getElementById('efbEntUndo');
      b.addEventListener('click', () => { el.hidden = true; clearTimeout(S.toastTimer); undo(); });
    }
    S.toastTimer = setTimeout(() => { el.hidden = true; }, 8000);
  }

  /** The star of one row, at once (optimistic), put back if the server refuses. */
  function star(id, btn) {
    const row = S.rows.find((r) => Number(r.msg_id) === Number(id));
    if (!row) return;
    const on = Number(row.starred) !== 1;
    row.starred = on ? 1 : 0;
    if (btn) {
      btn.classList.toggle('is-on', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.setAttribute('aria-label', on ? T('entUnstar', 'Remove star') : T('entStar', 'Star'));
      btn.innerHTML = icon(on ? 'bi-star-fill' : 'bi-star');
    }
    S.counts.starred = Math.max(0, (S.counts.starred || 0) + (on ? 1 : -1));
    const tab = D.querySelector('#efbEntTab-starred .efb-ent-count bdi');
    if (tab) tab.textContent = num(S.counts.starred);
    post({ action: 'efb_entries_bulk_Emsfb', form_id: S.fid, op: on ? 'star' : 'unstar', ids: JSON.stringify([Number(id)]) }).then((res) => {
      if (!res || res.success !== true) { row.starred = on ? 0 : 1; paint(); return; }
      S.counts = res.counts || S.counts;
      announce(on ? T('entStarredOne', 'Starred') : T('entUnstarredOne', 'Star removed'));
    });
  }

  /** Opens an entry in the response viewer (the full entry is read first: IP, the reader, notes, history). */
  function open(id) {
    const row = S.rows.find((r) => Number(r.msg_id) === Number(id));
    if (!row) return;
    const state = Number(row.read_);
    const go = () => {
      if (typeof W.fun_open_message_emsFormBuilder === 'function') W.fun_open_message_emsFormBuilder(Number(id), state);
      if (state === 0 || state === 3) { row.read_ = 1; S.counts.unread = Math.max(0, (S.counts.unread || 0) - 1); const tr = D.querySelector(`.efb-ent-row[data-msgid="${Number(id)}"]`); if (tr) tr.classList.remove('efb-ent-unread'); }
    };
    post({ action: 'efb_entry_get_Emsfb', msg_id: id }).then((res) => {
      if (res && res.success === true && res.entry) {
        const list = getGlobal('valueJson_ws_messages') || [];
        const i = list.findIndex((x) => String(x.msg_id) === String(id));
        if (i !== -1) Object.assign(list[i], { ip: res.entry.ip || '', read_by: res.entry.read_by || '0', content: res.entry.content });
        S.current = res;
      } else {
        S.current = null;
      }
      go();
    });
  }

  function menu(btn, id) {
    closeMenu();
    const row = S.rows.find((r) => Number(r.msg_id) === Number(id));
    if (!row) return;
    const st = Number(row.entry_status);
    const unread = Number(row.read_) === 0 || Number(row.read_) === 3;
    const items = [];
    const it = (op, k, fb, ic) => items.push(`<button type="button" role="menuitem" class="efb dropdown-item" data-ent="op" data-op="${op}" data-msgid="${Number(id)}" tabindex="-1">${icon(ic)} ${esc(T(k, fb))}</button>`);
    if (st === 2) { it('restore', 'entRestore', 'Restore', 'bi-arrow-counterclockwise'); it('delete', 'entDeleteForever', 'Delete permanently', 'bi-x-octagon'); }
    else {
      if (Number(row.read_) !== 2 && Number(row.read_) !== 4) unread ? it('read', 'mread', 'Mark as read', 'bi-envelope-open') : it('unread', 'entMarkUnread', 'Mark as unread', 'bi-envelope');
      st === 1 ? it('unspam', 'entNotSpam', 'Not spam', 'bi-shield-check') : it('spam', 'entMarkSpam', 'Mark as spam', 'bi-shield-exclamation');
      it('trash', 'entMoveTrash', 'Move to trash', 'bi-trash3');
    }
    for (const x of (W.EfbEntries && W.EfbEntries.extraMenu) || []) { const h = x(row); if (h) items.push(h); }
    const m = D.createElement('div');
    m.className = 'efb efb-ent-menu dropdown-menu show';
    m.setAttribute('role', 'menu');
    m.id = 'efbEntMenu';
    m.innerHTML = items.join('');
    D.body.appendChild(m);
    const r = btn.getBoundingClientRect();
    const w = m.offsetWidth || 200;
    const left = isRtl() ? Math.max(8, r.left) : Math.max(8, Math.min(W.innerWidth - w - 8, r.right - w));
    m.style.position = 'fixed';
    m.style.top = Math.min(W.innerHeight - m.offsetHeight - 8, r.bottom + 4) + 'px';
    m.style.left = left + 'px';
    btn.setAttribute('aria-expanded', 'true');
    S.menuBtn = btn;
    const first = m.querySelector('[role="menuitem"]');
    if (first) first.focus();
  }
  function closeMenu(refocus) {
    const m = D.getElementById('efbEntMenu');
    if (m) m.remove();
    if (S.menuBtn) { S.menuBtn.setAttribute('aria-expanded', 'false'); if (refocus) S.menuBtn.focus(); }
    S.menuBtn = null;
  }

  /** A small dialog of this screen (columns, export, resend, print): focus kept inside, Escape closes. */
  function dialog(title, body, buttons, opts) {
    opts = opts || {};
    closeDialog();
    const back = D.createElement('div');
    back.className = 'efb efb-ent-dlg-back';
    back.id = 'efbEntDlg';
    back.innerHTML = `<div class="efb efb-ent-dlg${isRtl() ? ' efb-ent-rtl' : ''}" role="dialog" aria-modal="true" aria-labelledby="efbEntDlgTitle" dir="${isRtl() ? 'rtl' : 'ltr'}">
      <div class="efb efb-ent-dlg-head"><h3 class="efb fs-6 m-0" id="efbEntDlgTitle">${esc(title)}</h3><button type="button" class="efb btn-close" data-ent="dlg-close" aria-label="${esc(T('close', 'Close'))}"></button></div>
      <div class="efb efb-ent-dlg-body">${body}</div>
      <div class="efb efb-ent-dlg-foot">${buttons || ''}<button type="button" class="efb btn btn-sm btn-outline-secondary" data-ent="dlg-close">${esc(T('entCancel', 'Cancel'))}</button></div>
    </div>`;
    S.dlgOpener = D.activeElement;
    D.body.appendChild(back);
    back.addEventListener('click', (e) => { if (e.target === back || (e.target.closest && e.target.closest('[data-ent="dlg-close"]'))) closeDialog(); });
    back.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') { e.stopPropagation(); closeDialog(); return; }
      if (e.key !== 'Tab') return;
      const f = Array.from(back.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter((x) => !x.disabled && x.offsetParent !== null);
      if (!f.length) return;
      if (e.shiftKey && D.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); } else if (!e.shiftKey && D.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    });
    const first = back.querySelector(opts.focus || 'input, select, textarea, button:not(.btn-close)');
    if (first) first.focus();
    return back;
  }
  function closeDialog() {
    const d = D.getElementById('efbEntDlg');
    if (d) d.remove();
    if (S.dlgOpener && S.dlgOpener.focus && D.contains(S.dlgOpener)) S.dlgOpener.focus();
    S.dlgOpener = null;
  }

  /** The columns dialog: meta columns for every plan, field columns on Free Plus; order with up / down. */
  function colsDialog(opener) {
    const fields = (S.fields || []).filter((f) => f.type !== 'repeater');
    const all = META_COLS.map((m) => ({ id: m[0], name: T(m[1], m[2]), meta: true })).concat(fields.map((f) => ({ id: f.id, name: f.name, meta: false })));
    let order = S.cols.slice();
    const draw = () => {
      const chosen = order.map((id) => all.find((x) => x.id === id)).filter(Boolean);
      const rest = all.filter((x) => !order.includes(x.id));
      const li = (x, on, i) => `<li class="efb efb-ent-colitem"><label class="efb"><input type="checkbox" class="efb form-check-input" data-colid="${esc(x.id)}" ${on ? 'checked' : ''} ${!x.meta && !planOk('columns') ? 'data-gem="1"' : ''}> <bdi>${esc(x.name)}</bdi>${!x.meta && !planOk('columns') ? ' ' + icon('bi-gem') : ''}</label>
        ${on ? `<span class="efb efb-ent-colmove"><button type="button" class="efb btn btn-sm btn-link" data-colup="${i}" aria-label="${esc(T('entMoveUp', 'Move up'))} ${esc(x.name)}" ${i === 0 ? 'disabled' : ''}>${icon('bi-arrow-up')}</button><button type="button" class="efb btn btn-sm btn-link" data-coldown="${i}" aria-label="${esc(T('entMoveDown', 'Move down'))} ${esc(x.name)}" ${i === chosen.length - 1 ? 'disabled' : ''}>${icon('bi-arrow-down')}</button></span>` : ''}</li>`;
      return `<ul class="efb list-unstyled efb-ent-collist">${chosen.map((x, i) => li(x, true, i)).join('')}${rest.map((x) => li(x, false, -1)).join('')}</ul>`;
    };
    const dlg = dialog(T('entColumns', 'Columns'), draw(), `<button type="button" class="efb btn btn-sm btn-primary" id="efbEntColsSave">${esc(T('save', 'Save'))}</button>`);
    const body = dlg.querySelector('.efb-ent-dlg-body');
    body.addEventListener('change', (e) => {
      const cb = e.target.closest('[data-colid]');
      if (!cb) return;
      if (cb.dataset.gem && cb.checked) { cb.checked = false; gate('columns', cb); return; }
      const id = cb.dataset.colid;
      order = cb.checked ? order.concat([id]) : order.filter((x) => x !== id);
      body.innerHTML = draw();
      const again = body.querySelector(`[data-colid="${CSS.escape ? CSS.escape(id) : id}"]`);
      if (again) again.focus();
    });
    body.addEventListener('click', (e) => {
      const up = e.target.closest('[data-colup]'); const dn = e.target.closest('[data-coldown]');
      if (!up && !dn) return;
      const i = Number((up || dn).dataset[up ? 'colup' : 'coldown']);
      const j = up ? i - 1 : i + 1;
      if (j < 0 || j >= order.length) return;
      [order[i], order[j]] = [order[j], order[i]];
      body.innerHTML = draw();
      const b = body.querySelector(`[data-${up ? 'colup' : 'coldown'}="${j}"]`) || body.querySelector(`[data-colup="${j}"]`) || body.querySelector(`[data-coldown="${j}"]`);
      if (b) b.focus();
    });
    D.getElementById('efbEntColsSave').addEventListener('click', () => {
      post({ action: 'efb_entries_cols_Emsfb', form_id: S.fid, cols: JSON.stringify(order) }).then((res) => {
        if (res && res.success === true) { S.cols = res.cols; closeDialog(); load(); }
        else if (res && res.code === 'efb_plan_required') gate('columns', opener);
      });
    });
  }

  function setRange(kind) {
    const pad = (n) => String(n).padStart(2, '0');
    const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const now = new Date();
    S.to = fmt(now);
    if (kind === 'today') S.from = S.to;
    else if (kind === 'month') S.from = fmt(new Date(now.getFullYear(), now.getMonth(), 1));
    else S.from = fmt(new Date(now.getFullYear(), now.getMonth(), now.getDate() - (Number(kind) - 1)));
    S.page = 1;
    load();
  }

  /* ------------------------------------------------------------------ export (H): CSV / XLSX from the server */
  const EXPORT_META = [['_track', 'trackNo', 'Confirmation Code'], ['_date', 'ddate', 'Date'], ['_status', 'entStatusCol', 'Status'], ['_read', 'read', 'Read'], ['_star', 'entStarred', 'Starred'], ['_ip', 'ip', 'IP'], ['_notes', 'entNotes', 'Notes'], ['_edited', 'entEditedCol', 'Last edit']];
  const PAY_META = [['_pay', 'payAmount', 'Amount'], ['_pay_status', 'entPayStatus', 'Payment status'], ['_tid', 'entTid', 'Transaction ID']];

  /** The export dialog: which entries, which format, which columns, how choices are written. */
  function exportDialog(opener, onlySelected) {
    const sel = S.allMatching ? 0 : S.selected.size;
    const useSel = onlySelected && sel > 0;
    const fields = (S.fields || []).filter((f) => !f.group);
    const meta = EXPORT_META.concat(S.formType === 'payment' ? PAY_META : []);
    const defOn = (id) => ['_track', '_date', '_pay', '_pay_status', '_tid'].includes(id) || id.charAt(0) !== '_';
    const cb = (id, label) => `<div class="efb form-check efb-ent-colitem"><input type="checkbox" class="efb form-check-input efb-ent-xcol" id="efbXc-${esc(id)}" value="${esc(id)}" ${defOn(id) ? 'checked' : ''}><label class="efb form-check-label" for="efbXc-${esc(id)}"><bdi>${esc(label)}</bdi></label></div>`;
    const xlsxGem = planOk('xlsx') ? '' : ` ${icon('bi-gem')}`;
    /* Radios outside their label (the admin stylesheet hides a radio inside a label). */
    const rad = (name, value, label, on, extra) => `<div class="efb form-check"><input type="radio" class="efb form-check-input" name="${name}" id="${name}-${value}" value="${value}" ${on ? 'checked' : ''} ${extra || ''}><label class="efb form-check-label" for="${name}-${value}">${label}</label></div>`;
    const body = `<fieldset class="efb mb-3"><legend class="efb fs-6 fw-bold">${esc(T('entExportRows', 'Entries'))}</legend>
        ${rad('efbXScope', 'filter', fill(T('entExportFiltered', 'Entries matching the current filters (%s)'), [S.estimate ? T('entMoreThan', 'More than %s').replace('%s', num(S.total)) : num(S.total)]), !useSel)}
        ${sel ? rad('efbXScope', 'ids', fill(T('entExportSelected', 'Selected entries (%s)'), [num(sel)]), useSel) : ''}
      </fieldset>
      <fieldset class="efb mb-3"><legend class="efb fs-6 fw-bold">${esc(T('entExportFmt', 'File format'))}</legend>
        ${rad('efbXFmt', 'csv', 'CSV', true)}
        ${rad('efbXFmt', 'xlsx', 'XLSX (Excel)' + xlsxGem, false, planOk('xlsx') ? '' : 'data-gem="1"')}
      </fieldset>
      <fieldset class="efb mb-3"><legend class="efb fs-6 fw-bold">${esc(T('entExportMulti', 'Several choices'))}</legend>
        ${rad('efbXSplit', '0', esc(T('entExportJoin', 'In one cell')), true)}
        ${rad('efbXSplit', '1', esc(T('entExportSplit', 'One column per choice')), false)}
        <div class="efb efb-ent-sublabel">${esc(T('csvOptVal', 'Export choices as'))}</div>
        ${rad('efbXOv', 'label', esc(T('csvOptLabel', 'Labels')), (W.efb_csv_opt_val_now || 'label') !== 'value')}
        ${rad('efbXOv', 'value', esc(T('csvOptValue', 'Stored values')), W.efb_csv_opt_val_now === 'value')}
      </fieldset>
      <fieldset class="efb"><legend class="efb fs-6 fw-bold">${esc(T('entExportCols', 'Columns to export'))} <button type="button" class="efb btn btn-sm btn-link" id="efbXAll">${esc(T('entSelectAllCols', 'Select all'))}</button></legend>
        <div class="efb efb-ent-xcols">${meta.map((m) => cb(m[0], T(m[1], m[2]))).join('')}${fields.map((f) => cb(f.id, f.name)).join('')}</div>
      </fieldset>
      <p class="efb text-danger mt-2 mb-0" id="efbXErr" role="alert"></p>`;
    const d = dialog(T('entExport', 'Export'), body, `<button type="button" class="efb btn btn-sm btn-primary" id="efbXGo">${icon('bi-download')} ${esc(T('entExport', 'Export'))}</button>`);
    d.addEventListener('change', (e) => {
      if (e.target.name === 'efbXFmt' && e.target.dataset.gem && e.target.checked) { e.target.checked = false; d.querySelector('input[name="efbXFmt"][value="csv"]').checked = true; gate('xlsx', e.target); }
    });
    d.querySelector('#efbXAll').addEventListener('click', () => d.querySelectorAll('.efb-ent-xcol').forEach((x) => { x.checked = true; }));
    d.querySelector('#efbXGo').addEventListener('click', () => {
      const scope = (d.querySelector('input[name="efbXScope"]:checked') || {}).value || 'filter';
      const cols = Array.from(d.querySelectorAll('.efb-ent-xcol:checked')).map((x) => x.value);
      if (fields.length && cols.length === fields.length + meta.filter((m) => defOn(m[0])).length) cols.push('_deleted');
      const data = Object.assign({ form_id: S.fid, format: d.querySelector('input[name="efbXFmt"]:checked').value, split_multi: d.querySelector('input[name="efbXSplit"]:checked').value,
        opt_val: d.querySelector('input[name="efbXOv"]:checked').value, sep_multi: ' | ', cols: JSON.stringify(cols) },
        scope === 'ids' ? { ids: JSON.stringify(Array.from(S.selected)) } : (() => { const f = filterArgs(); return { tab: f.tab, from: f.from, to: f.to, q: f.q, sort: f.sort, conds: JSON.stringify(f.conds) }; })());
      const err = d.querySelector('#efbXErr');
      err.textContent = '';
      post(Object.assign({ action: 'efb_entries_export_check_Emsfb' }, data)).then((res) => {
        if (!res || res.success !== true) {
          if (res && res.code === 'efb_plan_required') { gate(res.feature === feature('xlsx') ? 'xlsx' : 'filter', opener); return; }
          err.textContent = (res && res.m) || T('error', 'Error');
          return;
        }
        const f = D.createElement('form');
        f.method = 'POST';
        f.action = cfg().export_url || 'admin-post.php';
        f.style.display = 'none';
        const add = (k, v) => { const i = D.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v; f.appendChild(i); };
        add('action', 'efb_export_entries');
        add('_wpnonce', cfg().export_nonce || '');
        Object.keys(data).forEach((k) => add(k, data[k]));
        D.body.appendChild(f);
        f.submit();
        setTimeout(() => f.remove(), 1000);
        closeDialog();
        announce(plain(fill(T('entExported', '%s entries exported.'), [num(res.count)])));
      });
    });
  }

  /* ------------------------------------------------------------------ edit (F) */
  /** The text of a stored row for a person (tokens decoded). */
  const rowText = (r) => String(r && r.value != null ? r.value : '').replace(/@efb@dq#/g, '"').replace(/@n#/g, '\n').replace(/@efb!/g, ', ').replace(/,\s*$/, '');
  function editControl(f, rows, ri) {
    const key = `${f.id}-${ri === null ? 'x' : ri}`;
    const cid = `efbEd-${key}`;
    const errId = `${cid}-err`;
    const label = `<label class="efb form-label fw-bold mb-1" for="${cid}"><bdi>${esc(f.name)}</bdi>${f.required ? ' <span class="efb text-danger" aria-hidden="true">*</span>' : ''}</label>`;
    const attrs = `id="${cid}" data-fid="${esc(f.id)}" data-ri="${ri === null ? '' : ri}" aria-describedby="${errId}"${f.required ? ' aria-required="true"' : ''}`;
    const err = `<div class="efb efb-ent-edit-err text-danger small" id="${errId}" role="alert"></div>`;
    if (f.mode === 'readonly') {
      const v = rows.map(rowText).filter(Boolean).join(' | ');
      return `<div class="efb efb-ent-edit-row efb-ent-edit-ro"><div class="efb fw-bold"><bdi>${esc(f.name)}</bdi></div><div><bdi>${esc(v || '—')}</bdi></div><small class="efb text-muted">${esc(f.why === 'paid' ? T('entPaidLocked', 'The payment of this entry cannot change: price fields stay as paid.') : T('entReadOnly', 'This answer cannot be edited.'))}</small></div>`;
    }
    if (f.mode === 'file') {
      const files = rows.filter((r) => r && r.value === '@file@' && r.url);
      return `<div class="efb efb-ent-edit-row" data-file="${esc(f.id)}" data-ri="${ri === null ? '' : ri}"><div class="efb fw-bold mb-1"><bdi>${esc(f.name)}</bdi></div>
        ${files.map((r, i) => `<div class="efb form-check"><input type="checkbox" class="efb form-check-input efb-ent-rmfile" id="${cid}-rm${i}" value="${esc(r.url)}"><label class="efb form-check-label" for="${cid}-rm${i}">${esc(T('entRemoveFile', 'Remove file'))}: <bdi dir="ltr">${esc(String(r.url).split('/').pop())}</bdi></label></div>`).join('')}
        <label class="efb form-label small mt-1" for="${cid}-up">${esc(T('entReplaceFile', 'Replace file'))}</label>
        <input type="file" class="efb form-control form-control-sm efb-ent-upfile" id="${cid}-up" data-fid="${esc(f.id)}" data-ri="${ri === null ? '' : ri}" aria-describedby="${errId}">${err}</div>`;
    }
    const opts = Array.isArray(f.options) ? f.options : [];
    const stored = rows.map((r) => String(r.value == null ? '' : r.value).replace(/@efb@dq#/g, '"'));
    let input = '';
    let init = '';
    if (f.single) {
      const hit = opts.find((o) => rows.some((r) => String(r.id_ob) === o.id) || stored.includes(o.value));
      const old = !hit && stored[0] ? stored[0] : '';
      init = hit ? hit.id : (old ? '__old__' : '');
      input = `<select class="efb form-select" ${attrs} data-init="${esc(init)}"><option value=""></option>${old ? `<option value="__old__" selected>${esc(T('entOldValue', '(old value) %s').replace('%s', old))}</option>` : ''}${opts.map((o) => `<option value="${esc(o.id)}" ${hit && hit.id === o.id ? 'selected' : ''}>${esc(o.value)}</option>`).join('')}</select>`;
    } else if (f.multi) {
      const parts = stored.join('@efb!').split('@efb!').filter(Boolean);
      const on = opts.filter((o) => rows.some((r) => String(r.id_ob) === o.id) || parts.includes(o.value)).map((o) => o.id);
      const olds = parts.filter((p) => !opts.some((o) => o.value === p));
      init = on.slice().sort().join(',') + (olds.length ? '|old' : '');
      input = `<div class="efb efb-ent-edit-multi" role="group" aria-labelledby="${cid}-lb" ${attrs} data-init="${esc(init)}">${opts.map((o, i) => `<div class="efb form-check"><input type="checkbox" class="efb form-check-input" id="${cid}-o${i}" value="${esc(o.id)}" ${on.includes(o.id) ? 'checked' : ''}><label class="efb form-check-label" for="${cid}-o${i}">${esc(o.value)}</label></div>`).join('')}
        ${olds.map((o) => `<div class="efb small text-muted">${esc(T('entOldValue', '(old value) %s').replace('%s', o))}</div>`).join('')}</div>`;
      return `<div class="efb efb-ent-edit-row"><div class="efb form-label fw-bold mb-1" id="${cid}-lb"><bdi>${esc(f.name)}</bdi>${f.required ? ' <span class="efb text-danger" aria-hidden="true">*</span>' : ''}</div>${input}${err}</div>`;
    } else if (f.type === 'switch') {
      init = stored[0] !== undefined && stored[0] === f.on ? '1' : '0';
      input = `<div class="efb form-check form-switch"><input type="checkbox" role="switch" class="efb form-check-input" ${attrs} data-init="${init}" ${init === '1' ? 'checked' : ''}><label class="efb form-check-label" for="${cid}">${esc(f.on || 'On')}</label></div>`;
      return `<div class="efb efb-ent-edit-row"><div class="efb fw-bold mb-1"><bdi>${esc(f.name)}</bdi></div>${input}${err}</div>`;
    } else {
      init = stored.length ? rowText(rows[0]) : '';
      const typ = { email: 'email', url: 'url', color: 'color', date: /^\d{4}-\d{2}-\d{2}$/.test(init) || !init ? 'date' : 'text' }[f.type] || 'text';
      const num = ['number', 'range', 'rating', 'pointr5', 'pointr10', 'prcfld'].includes(f.type) ? ' inputmode="decimal"' : (['tel', 'mobile'].includes(f.type) ? ' inputmode="tel"' : '');
      input = f.type === 'textarea'
        ? `<textarea class="efb form-control" rows="3" dir="auto" ${attrs} data-init="${esc(init)}">${esc(init)}</textarea>`
        : `<input type="${typ}" class="efb form-control" dir="auto"${num} ${attrs} data-init="${esc(init)}" value="${esc(init)}">`;
    }
    const missing = !rows.length && f.mode === 'edit';
    if (missing) return `<div class="efb efb-ent-edit-row efb-ent-edit-missing"><div class="efb fw-bold mb-1"><bdi>${esc(f.name)}</bdi></div><button type="button" class="efb btn btn-sm btn-outline-secondary" data-ent="ed-add" data-target="${cid}-wrap">${icon('bi-plus-lg')} ${esc(T('entAddValue', 'Add value'))}</button><div id="${cid}-wrap" hidden>${label}${input}</div>${err}</div>`;
    return `<div class="efb efb-ent-edit-row">${label}${input}${err}</div>`;
  }

  /** The edit form in place of the answers (spec §4.3). */
  function editOpen() {
    const c = S.current;
    if (!c || !c.entry || !gate('edit')) return;
    const rows = rowsOf(c.entry.content);
    const fields = c.fields || [];
    const byId = (id, ri) => rows.filter((r) => r && String(r.id_) === String(id) && (ri === null ? (r.ri === undefined || r.ri === null || r.ri === '') : String(r.ri) === String(ri)));
    let html = '';
    for (const f of fields) {
      if (f.group) continue;
      if (f.type === 'repeater') {
        const kids = fields.filter((x) => x.group === f.id);
        const ris = Array.from(new Set(rows.filter((r) => r && r.rg === f.id && r.ri !== undefined).map((r) => String(r.ri)))).sort((a, b) => Number(a) - Number(b));
        html += `<fieldset class="efb efb-ent-edit-group"><legend class="efb fs-6"><bdi>${esc(f.name)}</bdi></legend>${ris.map((ri) => `<fieldset class="efb efb-ent-edit-rowset"><legend class="efb small">${fill(T('rowN', 'Row %s'), [num(Number(ri) + 1)])}</legend>${kids.map((k) => editControl(k, byId(k.id, ri), ri)).join('')}</fieldset>`).join('')}</fieldset>`;
        continue;
      }
      html += editControl(f, byId(f.id, null), null);
    }
    const paid = fields.some((f) => f.why === 'paid');
    const form = `<form class="efb efb-ent-edit" id="efbEntEdit" novalidate aria-labelledby="efbEntEditT">
      <h3 class="efb fs-6" id="efbEntEditT">${esc(T('entEdit', 'Edit entry'))}</h3>
      <p class="efb small text-muted" role="note">${icon('bi-info-circle')} ${esc(T('entLogicNote', 'Field visibility rules are not applied when an administrator edits an entry.'))}</p>
      ${paid ? `<p class="efb small text-warning-emphasis" role="note">${icon('bi-credit-card')} ${esc(T('entPaidLocked', 'The payment of this entry cannot change: price fields stay as paid.'))}</p>` : ''}
      ${html}
      <label class="efb form-label mt-2" for="efbEntEditReason">${esc(T('entReason', 'Reason (optional)'))}</label>
      <input type="text" class="efb form-control" id="efbEntEditReason" maxlength="255" dir="auto">
      <p class="efb text-danger mt-2 mb-0" id="efbEntEditMsg" role="alert"></p>
      <div class="efb d-flex gap-2 mt-2"><button type="submit" class="efb btn btn-primary btn-sm" id="efbEntEditSave">${icon('bi-check2')} ${esc(T('save', 'Save'))}</button><button type="button" class="efb btn btn-outline-secondary btn-sm" data-ent="ed-cancel">${esc(T('entCancel', 'Cancel'))}</button></div>
    </form>`;
    const box = D.getElementById('resp_efb');
    if (!box) return;
    S.editing = { added: {}, uploading: 0 };
    box.innerHTML = form;
    box.style.maxHeight = 'none';
    const first = box.querySelector('input:not([type=hidden]), select, textarea');
    if (first) first.focus();
  }
  function editClose(rerender) {
    S.editing = null;
    const box = D.getElementById('resp_efb');
    if (!box || !S.current) return;
    const e = S.current.entry;
    if (typeof W.fun_emsFormBuilder_show_messages === 'function') box.innerHTML = W.fun_emsFormBuilder_show_messages(rowsOf(e.content), '#first', e.ip || '', e.track, e.date);
    box.style.maxHeight = '';
    const b = D.querySelector('#efbEntView [data-ent="edit"]');
    if (b && !rerender) b.focus();
  }
  function editChanges() {
    const out = [];
    const form = D.getElementById('efbEntEdit');
    form.querySelectorAll('[data-fid][data-init]').forEach((el) => {
      const wrap = el.closest('[hidden]');
      if (wrap) return; // "Add value" not opened
      const id = el.dataset.fid;
      const ri = el.dataset.ri === '' ? undefined : el.dataset.ri;
      let v; let now;
      if (el.classList.contains('efb-ent-edit-multi')) {
        v = Array.from(el.querySelectorAll('input:checked')).map((x) => x.value);
        now = v.slice().sort().join(',') + (el.dataset.init.indexOf('|old') !== -1 && v.length === 0 ? '' : '');
        if (v.slice().sort().join(',') === el.dataset.init.replace('|old', '')) return;
      } else if (el.type === 'checkbox') {
        v = el.checked ? '1' : '0'; now = v;
        if (now === el.dataset.init) return;
      } else {
        v = el.value; now = v;
        if (now === el.dataset.init || v === '__old__') return;
      }
      out.push(Object.assign({ id_: id, value: v, op: 'set' }, ri !== undefined ? { ri } : {}));
    });
    form.querySelectorAll('.efb-ent-rmfile:checked').forEach((cb) => { const w = cb.closest('[data-file]'); out.push(Object.assign({ id_: w.dataset.file, op: 'remove_file', url: cb.value }, w.dataset.ri !== '' ? { ri: w.dataset.ri } : {})); });
    Object.keys(S.editing.added).forEach((k) => { const a = S.editing.added[k]; out.push(Object.assign({ id_: a.id, op: 'add_file', url: a.url }, a.ri !== '' ? { ri: a.ri } : {})); });
    return out;
  }
  function editSave(e) {
    if (e) e.preventDefault();
    const form = D.getElementById('efbEntEdit');
    if (!form || !S.current || S.editing.uploading) return;
    form.querySelectorAll('.efb-ent-edit-err').forEach((x) => { x.textContent = ''; });
    form.querySelectorAll('[aria-invalid="true"]').forEach((x) => x.removeAttribute('aria-invalid'));
    const msg = D.getElementById('efbEntEditMsg');
    msg.textContent = '';
    const changes = editChanges();
    if (!changes.length) { msg.textContent = T('entNoChange', 'Nothing was changed.'); return; }
    const btn = D.getElementById('efbEntEditSave');
    btn.disabled = true;
    post({ action: 'efb_entry_update_Emsfb', msg_id: S.current.entry.msg_id, hash: S.current.entry.hash, changes: JSON.stringify(changes), reason: D.getElementById('efbEntEditReason').value }).then((res) => {
      btn.disabled = false;
      if (!res || res.success !== true) {
        const text = plain(String((res && res.m) || T('error', 'Error')));
        if (res && res.code === 'efb_plan_required') { gate('edit', btn); return; }
        const sel = res && res.field_id ? `#efbEd-${CSS.escape ? CSS.escape(res.field_id) : res.field_id}-${res.ri === null || res.ri === undefined ? 'x' : res.ri}` : '';
        const el = sel ? D.querySelector(sel) || D.querySelector(`[data-fid="${res.field_id}"]`) : null;
        if (el) {
          el.setAttribute('aria-invalid', 'true');
          const er = D.getElementById(el.getAttribute('aria-describedby') || '') || (el.closest('.efb-ent-edit-row') || {}).querySelector && el.closest('.efb-ent-edit-row').querySelector('.efb-ent-edit-err');
          if (er) er.textContent = text;
          el.focus();
        } else msg.textContent = text;
        return;
      }
      S.current = res;
      const row = S.rows.find((r) => Number(r.msg_id) === Number(res.entry.msg_id));
      if (row) { row.content = res.entry.content; row.edited_at = res.entry.edited_at; }
      const list = getGlobal('valueJson_ws_messages') || [];
      const i = list.findIndex((x) => String(x.msg_id) === String(res.entry.msg_id));
      if (i !== -1) list[i].content = res.entry.content;
      editClose(true);
      viewerRepaint('#efbEntView [data-ent="edit"]');
      if (D.getElementById('efbEnt')) paint();
      viewerSay(T('entSaved', 'Changes saved.'));
      if (typeof W.alert_message_efb === 'function') W.alert_message_efb('', T('entSaved', 'Changes saved.'), 4, 'success');
    });
  }
  function editUpload(input) {
    const f = input.files && input.files[0];
    if (!f || !S.current) return;
    const fd = new FormData();
    fd.append('action', 'efb_entry_upload_Emsfb');
    fd.append('nonce', (typeof W._efb_core_nonce_ !== 'undefined' && W._efb_core_nonce_) || (W.efb_var && W.efb_var.nonce) || '');
    fd.append('msg_id', S.current.entry.msg_id);
    fd.append('field_id', input.dataset.fid);
    fd.append('file', f);
    S.editing.uploading++;
    const er = D.getElementById(input.getAttribute('aria-describedby'));
    if (er) er.textContent = '';
    fetch((W.ajax_object_efm && W.ajax_object_efm.ajax_url) || W.ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd }).then((r) => r.json()).then((j) => {
      S.editing.uploading--;
      const res = j && j.data !== undefined ? j.data : j;
      if (!res || res.success !== true) { if (er) er.textContent = plain(String((res && res.m) || T('error', 'Error'))); return; }
      S.editing.added[input.id] = { id: input.dataset.fid, ri: input.dataset.ri, url: res.url };
    }).catch(() => { S.editing.uploading--; });
  }

  /* ------------------------------------------------------------------ statistics (J) */
  /** A small line chart in SVG (views and submissions per day), with the numbers in a table for screen readers. */
  function statsChart(series) {
    const W0 = 640, H0 = 180, pad = 28;
    const max = Math.max(1, ...series.map((d) => Math.max(d.views, d.submits)));
    const x = (i) => pad + (series.length < 2 ? 0 : (i * (W0 - 2 * pad)) / (series.length - 1));
    const y = (v) => H0 - pad - (v * (H0 - 2 * pad)) / max;
    const line = (k) => series.map((d, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(d[k]).toFixed(1)}`).join(' ');
    const ticks = [0, Math.ceil(max / 2), max];
    const rtl = isRtl();
    return `<svg class="efb efb-ent-chart" viewBox="0 0 ${W0} ${H0}" role="img" aria-labelledby="efbEntChartT" preserveAspectRatio="none" ${rtl ? 'style="transform:scaleX(-1)"' : ''}>
      <title id="efbEntChartT">${esc(T('entViews', 'Views'))} / ${esc(T('entSubmits', 'Submissions'))}</title>
      ${ticks.map((v) => `<line x1="${pad}" x2="${W0 - pad}" y1="${y(v)}" y2="${y(v)}" class="efb-ent-chart-grid"/>`).join('')}
      <path d="${line('views')}" class="efb-ent-chart-views" fill="none"/>
      <path d="${line('submits')}" class="efb-ent-chart-submits" fill="none"/>
    </svg>
    <div class="efb efb-ent-chart-legend"><span class="efb-ent-lg efb-ent-lg-views"></span> ${esc(T('entViews', 'Views'))} <span class="efb-ent-lg efb-ent-lg-submits"></span> ${esc(T('entSubmits', 'Submissions'))} · <bdi>${num(0)}</bdi>–<bdi>${num(max)}</bdi></div>
    <table class="efb visually-hidden"><caption>${esc(T('entStats', 'Statistics'))}</caption><thead><tr><th scope="col">${esc(T('ddate', 'Date'))}</th><th scope="col">${esc(T('entViews', 'Views'))}</th><th scope="col">${esc(T('entSubmits', 'Submissions'))}</th></tr></thead>
    <tbody>${series.map((d) => `<tr><th scope="row">${esc(digits(d.day))}</th><td>${num(d.views)}</td><td>${num(d.submits)}</td></tr>`).join('')}</tbody></table>`;
  }
  function statsHtml(st) {
    const tile = (k, fb, v, cls) => `<div class="efb efb-ent-tile ${cls || ''}"><div class="efb efb-ent-tile-v"><bdi>${v}</bdi></div><div class="efb efb-ent-tile-l">${esc(T(k, fb))}</div></div>`;
    const conv = st.conversion === null || st.conversion === undefined ? '—' : `${num(st.conversion)}%`;
    return `<div class="efb efb-ent-stats-head"><h3 class="efb fs-6 m-0" id="efbEntStatsT">${esc(T('entStats', 'Statistics'))}</h3>
      <div class="efb btn-group" role="group" aria-label="${esc(T('entStats', 'Statistics'))}">${[7, 30, 90].map((d) => `<button type="button" class="efb btn btn-sm ${d === st.days ? 'btn-primary' : 'btn-outline-secondary'}" data-ent="stats-days" data-days="${d}" aria-pressed="${d === st.days}">${fill(T('entDays', 'Last %s days'), [num(d)])}</button>`).join('')}</div></div>
      <div class="efb efb-ent-tiles">${tile('entViews', 'Views', st.has_views ? num(st.totals.views) : esc(T('entNoStatsYet', 'No data yet')))}${tile('entSubmits', 'Submissions', num(st.totals.submits))}${tile('entConv', 'Conversion rate', conv)}${tile('entUnread', 'Unread', num(st.unread))}${tile('entSpam', 'Spam', num(st.spam_now))}</div>
      ${statsChart(st.series)}
      <p class="efb small text-muted mb-0">${esc(T('entStatsHelp', 'Views count each new visit session of the form page.'))}</p>`;
  }
  function statsToggle(btn, days) {
    if (!gate('stats', btn)) return;
    let box = D.getElementById('efbEntStats');
    if (box && !days) { box.remove(); S.statsOpen = false; btn.setAttribute('aria-expanded', 'false'); return; }
    S.statsOpen = true;
    post({ action: 'efb_form_stats_Emsfb', form_id: S.fid, days: days || S.statsDays || 30 }).then((res) => {
      if (!res || res.success !== true) { if (res && res.code === 'efb_plan_required') gate('stats', btn); return; }
      S.statsDays = res.days;
      if (!box) { box = D.createElement('section'); box.id = 'efbEntStats'; box.className = 'efb efb-ent-stats'; box.setAttribute('aria-labelledby', 'efbEntStatsT'); const tabs = D.querySelector('#efbEnt .efb-ent-tabs'); if (tabs) tabs.parentNode.insertBefore(box, tabs); }
      box.innerHTML = statsHtml(res);
      const hb = D.querySelector('#efbEnt [data-ent="stats"]');
      if (hb) hb.setAttribute('aria-expanded', 'true');
      if (days) { const b = box.querySelector(`[data-days="${days}"]`); if (b) b.focus(); }
    });
  }

  /* ------------------------------------------------------------------ print (I) */
  /**
   * Prints entries (at most 50): each through the response viewer's own renderer, one per page, with its notes
   * only when "Include notes" is ticked. ids = list, or null for the selection / every matching result.
   */
  function printEntries(ids, withNotes) {
    const data = { action: 'efb_entries_print_Emsfb', form_id: S.fid, notes: withNotes ? '1' : '0' };
    if (!ids && S.allMatching) data.filter = JSON.stringify(filterArgs());
    else data.ids = JSON.stringify(ids || Array.from(S.selected));
    return post(data).then((res) => {
      if (!res || res.success !== true) {
        if (typeof W.alert_message_efb === 'function') W.alert_message_efb('', (res && res.m) || T('error', 'Error'), 8, 'warning');
        return null;
      }
      const sections = res.entries.map((e) => {
        let html = typeof W.fun_emsFormBuilder_show_messages === 'function' ? W.fun_emsFormBuilder_show_messages(rowsOf(e.content), '#first', e.ip || '', e.track, e.date) : esc(e.track);
        if (withNotes && e.notes && e.notes.length) {
          html += `<div class="efb-print-notes"><h3>${esc(T('entNotes', 'Notes'))}</h3><ul>${e.notes.map((n) => `<li><bdi>${esc(n.author || '')}</bdi> · <bdi>${esc(digits(n.date))}</bdi>: <bdi>${esc(n.note)}</bdi></li>`).join('')}</ul></div>`;
        }
        return html;
      });
      if (!sections.length || typeof W.generatePDF_EFB !== 'function') return null;
      W.generatePDF_EFB(null, { sections, title: T('entPrint', 'Print') });
      return sections.length;
    });
  }
  function printDialog(ids, opener) {
    const n = ids ? ids.length : (S.allMatching ? S.total : S.selected.size);
    if (n > (Number(cfg().max_print) || 50)) {
      if (typeof W.alert_message_efb === 'function') W.alert_message_efb('', T('entPrintMax', 'At most %s entries can be printed at once.').replace('%s', num(Number(cfg().max_print) || 50)), 8, 'warning');
      return;
    }
    const d = dialog(T('entPrint', 'Print'), `<p class="efb">${fill(T('entNSelected', '%s selected'), [num(n)])}</p>
      <div class="efb form-check"><input type="checkbox" class="efb form-check-input" id="efbPrNotes"><label class="efb form-check-label" for="efbPrNotes">${esc(T('entPrintNotes', 'Include notes'))}</label></div>`,
      `<button type="button" class="efb btn btn-sm btn-primary" id="efbPrGo">${icon('bi-printer')} ${esc(T('entPrint', 'Print'))}</button>`, { focus: '#efbPrGo' });
    d.querySelector('#efbPrGo').addEventListener('click', () => { const notes = d.querySelector('#efbPrNotes').checked; closeDialog(); printEntries(ids, notes); });
  }

  /* ------------------------------------------------------------------ the entry in the response viewer (D) */
  const STATUS = { 0: ['entStActive', 'Active'], 1: ['entSpam', 'Spam'], 2: ['entTrash', 'Trash'] };
  function cur(msgId) { return S.current && S.current.entry && Number(S.current.entry.msg_id) === Number(msgId) ? S.current : null; }

  /** "2 hours ago" in the site's language and digits (Intl), from a stored site-time date. */
  function ago(iso) {
    const t = Date.parse(String(iso).replace(' ', 'T'));
    if (!isFinite(t)) return '';
    const diff = (t - Date.now()) / 1000;
    const units = [['year', 31536000], ['month', 2592000], ['day', 86400], ['hour', 3600], ['minute', 60], ['second', 1]];
    for (const [u, sec] of units) {
      if (Math.abs(diff) >= sec || u === 'second') {
        try { return digits(new Intl.RelativeTimeFormat(locale().replace('_', '-'), { numeric: 'auto' }).format(Math.round(diff / sec), u)); } catch (e) { return ''; }
      }
    }
    return '';
  }

  /** The bar above the answers: star, status, "Edited" and the actions other parts add (edit, resend, print). */
  function viewerHead(msgId) {
    const c = cur(msgId);
    if (!c) return '';
    const e = c.entry;
    const starred = Number(e.starred) === 1;
    const st = Number(e.entry_status);
    const btns = ((W.EfbEntries && W.EfbEntries.viewerButtons) || []).map((f) => f(e)).join('');
    return `<div class="efb efb-ent-view-head" id="efbEntView" data-msgid="${Number(e.msg_id)}">
      <button type="button" class="efb efb-ent-star${starred ? ' is-on' : ''}" data-ent="vstar" data-msgid="${Number(e.msg_id)}" aria-pressed="${starred ? 'true' : 'false'}" aria-label="${esc(starred ? T('entUnstar', 'Remove star') : T('entStar', 'Star'))}">${icon(starred ? 'bi-star-fill' : 'bi-star')}</button>
      ${st ? `<span class="efb badge efb-ent-badge efb-ent-badge-${st === 1 ? 'spam' : 'trash'}">${esc(T(STATUS[st][0], STATUS[st][1]))}</span>` : ''}
      ${e.edited_at ? `<button type="button" class="efb btn btn-link btn-sm efb-ent-edited-link" data-ent="vhist" data-msgid="${Number(e.msg_id)}">${fill(T('entEdited', 'Edited %s'), [esc(digits(e.edited_at_display || e.edited_at))])}</button>` : ''}
      <span class="efb efb-ent-view-btns">${btns}</span>
    </div>`;
  }

  function notesHtml(c) {
    const max = Number(cfg().note_max) || 5000;
    const list = (c.notes || []).map((n) => `<li class="efb efb-ent-note" data-note="${Number(n.note_id)}">
        <div class="efb efb-ent-note-meta"><bdi class="efb fw-bold">${esc(n.author || '—')}</bdi> · <time datetime="${esc(n.date_iso)}" title="${esc(digits(n.date))}">${esc(ago(n.date_iso))}</time>
        ${n.can_delete ? `<button type="button" class="efb btn btn-sm btn-link efb-ent-note-del" data-ent="note-del" data-note="${Number(n.note_id)}" aria-label="${esc(T('delete', 'Delete'))}">${icon('bi-trash3')}</button>` : ''}</div>
        <div class="efb efb-ent-note-text" dir="auto">${esc(n.note)}</div></li>`).join('');
    return `<p class="efb efb-ent-note-private">${icon('bi-lock')} ${esc(T('entNotePrivate', 'Only administrators can see notes.'))}</p>
      <ul class="efb list-unstyled efb-ent-notes">${list || `<li class="efb text-muted">${esc(T('entNoNotes', 'No notes yet.'))}</li>`}</ul>
      <label class="efb visually-hidden" for="efbEntNoteTxt">${esc(T('entNoteAdd', 'Add note'))}</label>
      <textarea class="efb form-control efb-ent-note-input" id="efbEntNoteTxt" rows="3" maxlength="${max}" dir="auto" placeholder="${esc(T('entNoteAdd', 'Add note'))}"></textarea>
      <div class="efb d-flex justify-content-between align-items-center mt-1"><small class="efb text-muted" id="efbEntNoteErr" role="alert"></small>
      <button type="button" class="efb btn btn-sm btn-primary" data-ent="note-add">${icon('bi-plus-lg')} ${esc(T('entNoteAdd', 'Add note'))}</button></div>`;
  }

  /** One history line in words ("Ali changed “Name” from “a” to “b”"), every inserted value isolated. */
  function historyLine(h) {
    const who = `<bdi>${esc(h.user || '—')}</bdi>`;
    const v = (x) => `<bdi>${esc(x === null || x === undefined ? '' : String(x).length > 300 ? String(x).slice(0, 300) + '…' : x)}</bdi>`;
    const st = (x) => { const k = { active: STATUS[0], spam: STATUS[1], trash: STATUS[2] }[x]; return `<bdi>${esc(k ? T(k[0], k[1]) : x || '')}</bdi>`; };
    const sub = (tpl, vals) => { let o = esc(tpl); vals.forEach((x, i) => { o = o.split(`%${i + 1}$s`).join(x); }); return o; };
    switch (h.action) {
      case 'edit': return sub(T('entHistEdit', '%1$s changed “%2$s” from “%3$s” to “%4$s”'), [who, `<bdi>${esc(h.field)}</bdi>`, v(h.old), v(h.new)]) + (h.meta ? ` <span class="efb text-muted">(<bdi>${esc(h.meta)}</bdi>)</span>` : '');
      case 'status': return sub(T('entHistStatus', '%1$s moved the entry from %2$s to %3$s'), [who, st(h.old), st(h.new)]);
      case 'star': return sub(h.new === '1' ? T('entHistStar', '%1$s starred the entry') : T('entHistUnstar', '%1$s removed the star'), [who]);
      case 'read': return sub(h.new === '1' ? T('entHistRead', '%1$s marked the entry as read') : T('entHistUnread', '%1$s marked the entry as unread'), [who]);
      case 'resend': return sub(T('entHistResend', '%1$s resent the notification to %2$s recipients'), [who, `<bdi>${num((String(h.meta).match(/\d+/) || [0])[0])}</bdi>`]);
      case 'note_del': return sub(T('entHistNoteDel', '%1$s deleted a note'), [who]);
      case 'api_read': return sub(T('entHistApi', '%1$s read the entries through %2$s'), [who, `<bdi>${esc(h.meta)}</bdi>`]);
      default: return `${who} <bdi>${esc(h.action)}</bdi>`;
    }
  }
  function historyHtml(c) {
    const items = (c.log || []).map((h) => `<li class="efb efb-ent-hist"><time datetime="${esc(h.date_iso)}" title="${esc(digits(h.date))}">${esc(ago(h.date_iso))}</time> — <span class="efb efb-ent-hist-txt">${historyLine(h)}</span></li>`).join('');
    return `<ol class="efb list-unstyled efb-ent-history">${items || `<li class="efb text-muted">${esc(T('entNoHistory', 'No changes yet.'))}</li>`}</ol>`;
  }

  /** Notes and History, two tabs under the answers. */
  function viewerExtra(msgId) {
    const c = cur(msgId);
    if (!c) return '';
    const tab = S.viewTab === 'hist' ? 'hist' : 'notes';
    return `<div class="efb efb-ent-view-extra" id="efbEntViewExtra" data-msgid="${Number(c.entry.msg_id)}">
      <div class="efb efb-ent-tabs" role="tablist" aria-label="${esc(T('entNotes', 'Notes'))} / ${esc(T('entHistory', 'History'))}">
        <button type="button" role="tab" class="efb efb-ent-tab${tab === 'notes' ? ' active' : ''}" id="efbEntVtNotes" aria-selected="${tab === 'notes'}" aria-controls="efbEntVpNotes" tabindex="${tab === 'notes' ? 0 : -1}" data-ent="vtab" data-vtab="notes">${esc(T('entNotes', 'Notes'))} <span class="efb efb-ent-count"><bdi>${num((c.notes || []).length)}</bdi></span></button>
        <button type="button" role="tab" class="efb efb-ent-tab${tab === 'hist' ? ' active' : ''}" id="efbEntVtHist" aria-selected="${tab === 'hist'}" aria-controls="efbEntVpHist" tabindex="${tab === 'hist' ? 0 : -1}" data-ent="vtab" data-vtab="hist">${esc(T('entHistory', 'History'))}</button>
      </div>
      <div class="efb efb-ent-vpanel efb-ent-notes-panel" id="efbEntVpNotes" role="tabpanel" aria-labelledby="efbEntVtNotes" ${tab === 'notes' ? '' : 'hidden'}>${notesHtml(c)}</div>
      <div class="efb efb-ent-vpanel" id="efbEntVpHist" role="tabpanel" aria-labelledby="efbEntVtHist" ${tab === 'hist' ? '' : 'hidden'}>${historyHtml(c)}</div>
      <div class="efb visually-hidden" id="efbEntViewLive" role="status" aria-live="polite"></div>
    </div>`;
  }
  function viewerRepaint(focusSel) {
    const box = D.getElementById('efbEntViewExtra');
    if (box && S.current) box.outerHTML = viewerExtra(S.current.entry.msg_id);
    const head = D.getElementById('efbEntView');
    if (head && S.current) head.outerHTML = viewerHead(S.current.entry.msg_id);
    if (focusSel) { const el = D.querySelector(focusSel); if (el) el.focus(); }
  }
  function viewerSay(text) { const l = D.getElementById('efbEntViewLive'); if (l) { l.textContent = ''; setTimeout(() => { l.textContent = text; }, 30); } }
  function noteAdd() {
    const ta = D.getElementById('efbEntNoteTxt');
    if (!ta || !S.current) return;
    const v = ta.value;
    if (!v.trim()) return;
    post({ action: 'efb_entry_notes_Emsfb', op: 'add', msg_id: S.current.entry.msg_id, note: v }).then((res) => {
      const err = D.getElementById('efbEntNoteErr');
      if (!res || res.success !== true) { if (err) err.textContent = (res && res.m) || T('error', 'Error'); return; }
      S.current.notes = res.notes; S.current.log = res.log;
      const row = S.rows.find((r) => Number(r.msg_id) === Number(S.current.entry.msg_id));
      if (row) row.notes_count = res.notes.length;
      viewerRepaint('#efbEntNoteTxt');
      viewerSay(T('entNoteSaved', 'Note added.'));
    });
  }
  function noteDel(id) {
    if (!S.current) return;
    post({ action: 'efb_entry_notes_Emsfb', op: 'delete', note_id: id }).then((res) => {
      if (!res || res.success !== true) return;
      S.current.notes = res.notes; S.current.log = res.log;
      const row = S.rows.find((r) => Number(r.msg_id) === Number(S.current.entry.msg_id));
      if (row) row.notes_count = res.notes.length;
      viewerRepaint('#efbEntNoteTxt');
      viewerSay(T('entNoteDeleted', 'Note deleted.'));
    });
  }
  function viewerStar(btn) {
    if (!S.current) return;
    const e = S.current.entry;
    const on = Number(e.starred) !== 1;
    post({ action: 'efb_entries_bulk_Emsfb', form_id: e.form_id, op: on ? 'star' : 'unstar', ids: JSON.stringify([Number(e.msg_id)]) }).then((res) => {
      if (!res || res.success !== true) return;
      e.starred = on ? 1 : 0;
      const row = S.rows.find((r) => Number(r.msg_id) === Number(e.msg_id));
      if (row) row.starred = e.starred;
      if (res.counts) S.counts = res.counts;
      viewerRepaint('#efbEntView [data-ent="vstar"]');
      if (D.getElementById('efbEnt')) paint();
      refreshExtras();
    });
  }
  /** Notes and history again from the server (after an action that wrote a history row). */
  function refreshExtras() {
    if (!S.current) return Promise.resolve();
    const id = S.current.entry.msg_id;
    return post({ action: 'efb_entry_notes_Emsfb', op: 'list', msg_id: id }).then((r) => {
      if (!r || r.success !== true || !S.current || Number(S.current.entry.msg_id) !== Number(id)) return;
      S.current.notes = r.notes; S.current.log = r.log;
      const a = D.activeElement && D.activeElement.id ? '#' + D.activeElement.id : null;
      viewerRepaint(a);
    });
  }
  function viewerInit(msgId) {
    S.viewTab = S.viewTab || 'notes';
    // The response viewer may have been drawn before the entry arrived (an old caller): draw the parts now.
    if (cur(msgId) && !D.getElementById('efbEntViewExtra')) {
      const msgs = D.getElementById('resp_efb');
      if (msgs) { msgs.insertAdjacentHTML('beforebegin', viewerHead(msgId)); msgs.insertAdjacentHTML('afterend', viewerExtra(msgId)); }
    }
  }
  W.efb_entries_viewer_head_efb = viewerHead;
  W.efb_entries_viewer_extra_efb = viewerExtra;
  W.efb_entries_viewer_init_efb = viewerInit;

  /* ------------------------------------------------------------------ events */
  let wired = false;
  function wire() {
    if (wired || !D) return;
    wired = true;
    D.addEventListener('click', (e) => {
      if (!e.target.closest) return;
      const menuOpen = D.getElementById('efbEntMenu');
      if (menuOpen && !e.target.closest('#efbEntMenu') && !e.target.closest('[data-ent="menu"]')) closeMenu();
      const el = e.target.closest('[data-ent]');
      if (!el || !(el.closest('#efbEnt') || el.closest('#efbEntMenu') || el.closest('#efbEntDlg') || el.closest('#efbEntView') || el.closest('#efbEntViewExtra') || el.closest('#efbEntEdit') || el.closest('.efb-ent-scope'))) return;
      const id = Number(el.dataset.msgid || 0);
      switch (el.dataset.ent) {
        case 'tab': if (S.tab !== el.dataset.tab) { S.tab = el.dataset.tab; S.page = 1; S.selected.clear(); S.allMatching = false; load({ focus: `#efbEntTab-${S.tab}` }); } break;
        case 'page': { const p = Number(el.dataset.page); if (p >= 1 && p !== S.page) { S.page = p; S.allMatching = false; load({ focus: '#efbEntPanel .efb-ent-pager [aria-current="page"]' }); } break; }
        case 'sort': S.sort = S.sort === 'date_asc' ? 'date_desc' : 'date_asc'; S.page = 1; load({ focus: '.efb-ent-sort' }); break;
        case 'star': star(id, el); break;
        case 'open': open(id); break;
        case 'menu': if (D.getElementById('efbEntMenu') && S.menuBtn === el) closeMenu(); else menu(el, id); break;
        case 'op': closeMenu(); op(el.dataset.op, [id], el); break;
        case 'bulk': op(el.dataset.op, null, el); break;
        case 'selall': S.allMatching = true; paint(); announce(plain(fill(T('entAllSelectedN', 'All %s results are selected.'), [num(S.total)]))); break;
        case 'unselect': S.selected.clear(); S.allMatching = false; paint(); break;
        case 'filters': S.filtersOpen = !S.filtersOpen; paint(); break;
        case 'range': setRange(el.dataset.range); break;
        case 'cond-add':
          if (!gate('filter', el)) break;
          if (S.conds.length < 3) { S.conds.push({ id: '', op: 'eq', v: '' }); paint(); const s = D.querySelector(`.efb-ent-cond-f[data-i="${S.conds.length - 1}"]`); if (s) s.focus(); }
          break;
        case 'cond-del': S.conds.splice(Number(el.dataset.i), 1); S.page = 1; load(); break;
        case 'clear': S.q = ''; S.from = ''; S.to = ''; S.conds = []; S.page = 1; load({ focus: '#efbEntQ' }); break;
        case 'cols': colsDialog(el); break;
        case 'chart': surveyChart(); break;
        case 'vstar': viewerStar(el); break;
        case 'vtab': S.viewTab = el.dataset.vtab === 'hist' ? 'hist' : 'notes'; viewerRepaint(S.viewTab === 'hist' ? '#efbEntVtHist' : '#efbEntVtNotes'); break;
        case 'vhist': S.viewTab = 'hist'; viewerRepaint('#efbEntVtHist'); { const p = D.getElementById('efbEntViewExtra'); if (p && p.scrollIntoView) p.scrollIntoView({ block: 'nearest' }); } break;
        case 'note-add': noteAdd(); break;
        case 'note-del': noteDel(Number(el.dataset.note)); break;
        default:
          if (W.EfbEntries && W.EfbEntries.actions && typeof W.EfbEntries.actions[el.dataset.ent] === 'function') W.EfbEntries.actions[el.dataset.ent](el, id);
      }
    });
    D.addEventListener('change', (e) => {
      const t = e.target;
      if (!t.closest || !t.closest('#efbEnt')) return;
      if (t.classList.contains('efb-ent-one')) {
        const id = Number(t.value);
        if (S.allMatching) { S.allMatching = false; S.rows.forEach((r) => S.selected.add(Number(r.msg_id))); }
        t.checked ? S.selected.add(id) : S.selected.delete(id);
        paint();
        const again = D.querySelector(`.efb-ent-one[value="${id}"]`);
        if (again) again.focus();
      } else if (t.classList.contains('efb-ent-all')) {
        S.allMatching = false;
        S.rows.forEach((r) => (t.checked ? S.selected.add(Number(r.msg_id)) : S.selected.delete(Number(r.msg_id))));
        paint();
        const again = D.querySelector('.efb-ent-all');
        if (again) again.focus();
      } else if (t.id === 'efbEntFrom' || t.id === 'efbEntTo') {
        S[t.id === 'efbEntFrom' ? 'from' : 'to'] = /^\d{4}-\d{2}-\d{2}$/.test(t.value) ? t.value : '';
        S.page = 1; load({ focus: '#' + t.id });
      } else if (t.id === 'efbEntPP') {
        S.per_page = [20, 50, 100].includes(Number(t.value)) ? Number(t.value) : 20; S.page = 1; load({ focus: '#efbEntPP' });
      } else if (t.classList.contains('efb-ent-cond-f') || t.classList.contains('efb-ent-cond-op') || t.classList.contains('efb-ent-cond-v')) {
        const i = Number(t.dataset.i);
        const c = S.conds[i];
        if (!c) return;
        if (t.classList.contains('efb-ent-cond-f')) { c.id = t.value; c.v = ''; }
        else if (t.classList.contains('efb-ent-cond-op')) c.op = t.value;
        else c.v = latin(t.value).slice(0, 200) === t.value ? t.value.slice(0, 200) : t.value.slice(0, 200);
        S.page = 1;
        const focusSel = `.${t.classList.contains('efb-ent-cond-f') ? 'efb-ent-cond-f' : (t.classList.contains('efb-ent-cond-op') ? 'efb-ent-cond-op' : 'efb-ent-cond-v')}[data-i="${i}"]`;
        if (c.id && (c.op === 'empty' || c.op === 'filled' || String(c.v).trim() !== '')) load({ focus: focusSel }); else { paint(); const el = D.querySelector(focusSel); if (el) el.focus(); }
      }
    });
    let qTimer = null;
    D.addEventListener('input', (e) => {
      if (e.target.id !== 'efbEntQ') return;
      clearTimeout(qTimer);
      const v = e.target.value;
      qTimer = setTimeout(() => { if (v === S.q) return; S.q = v.slice(0, 100); S.page = 1; load({ focus: '#efbEntQ' }); }, 450);
    });
    D.addEventListener('keydown', (e) => {
      const menuEl = D.getElementById('efbEntMenu');
      if (menuEl && (e.key === 'Escape' || e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
        if (e.key === 'Escape') { e.preventDefault(); closeMenu(true); return; }
        const items = Array.from(menuEl.querySelectorAll('[role="menuitem"]'));
        const i = items.indexOf(D.activeElement);
        const n = e.key === 'ArrowDown' ? (i + 1) % items.length : (i - 1 + items.length) % items.length;
        if (items[n]) { e.preventDefault(); items[n].focus(); }
        return;
      }
      const tab = e.target.closest && e.target.closest('[role="tab"]');
      if (tab && (tab.closest('#efbEnt') || tab.closest('#efbEntViewExtra')) && (e.key === 'ArrowRight' || e.key === 'ArrowLeft' || e.key === 'Home' || e.key === 'End')) {
        const tabs = Array.from(tab.closest('[role="tablist"]').querySelectorAll('[role="tab"]'));
        let i = tabs.indexOf(tab);
        const fwd = (e.key === 'ArrowRight') !== isRtl();
        i = e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : (fwd ? (i + 1) % tabs.length : (i - 1 + tabs.length) % tabs.length);
        e.preventDefault();
        tabs[i].click();
        return;
      }
      if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && e.target.id === 'efbEntNoteTxt') { e.preventDefault(); noteAdd(); return; }
      if (e.key === 'Enter' && e.target.id === 'efbEntQ') { e.preventDefault(); clearTimeout(qTimer); S.q = e.target.value.slice(0, 100); S.page = 1; load({ focus: '#efbEntQ' }); return; }
      shortcuts(e);
    });
  }

  /** j / k next / previous row, s star, # trash, Enter open: only on this screen, never while typing. */
  function shortcuts(e) {
    if (!D.getElementById('efbEnt') || D.getElementById('efbEntDlg') || e.ctrlKey || e.metaKey || e.altKey) return;
    const t = e.target;
    if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
    const modal = D.getElementById('settingModalEfb');
    if (modal && modal.classList.contains('show')) return;
    const rows = Array.from(D.querySelectorAll('#efbEnt .efb-ent-row'));
    if (!rows.length) return;
    const cur = t && t.closest ? t.closest('.efb-ent-row') : null;
    let i = cur ? rows.indexOf(cur) : S.focusIdx;
    if (e.key === 'j' || e.key === 'k') {
      i = e.key === 'j' ? Math.min(rows.length - 1, i + 1) : Math.max(0, i - 1);
      S.focusIdx = i;
      rows[i].focus();
      e.preventDefault();
      return;
    }
    if (i < 0 || !rows[i]) return;
    const id = Number(rows[i].dataset.msgid);
    if (e.key === 's') { e.preventDefault(); star(id, rows[i].querySelector('.efb-ent-star')); }
    else if (e.key === '#') { e.preventDefault(); op(S.tab === 'trash' ? 'restore' : 'trash', [id], rows[i]); }
    else if (e.key === 'Enter' && cur === t) { e.preventDefault(); open(id); }
  }

  /** The survey chart reads every active answer of the form (the old action), as before F14. */
  function surveyChart() {
    post({ action: 'get_messages_id_Emsfb', id: S.fid }).then((res) => {
      if (!res || res.success !== true || !Array.isArray(res.ajax_value)) return;
      if (typeof W.fun_export_rows_for_Subscribe_emsFormBuilder === 'function') W.fun_export_rows_for_Subscribe_emsFormBuilder(res.ajax_value);
      if (typeof W.convert_to_dataset_emsFormBuilder === 'function') W.convert_to_dataset_emsFormBuilder();
    });
  }

  /* ------------------------------------------------------------------ entry point */
  /** Opens the entries screen of a form (replaces the old list when the database is ready). */
  function openForm(fid, formType) {
    wire();
    S.fid = Number(fid);
    S.formType = formType || getGlobal('form_type_emsFormBuilder') || 'form';
    S.page = 1; S.tab = 'all'; S.from = ''; S.to = ''; S.q = ''; S.conds = []; S.sort = 'date_desc';
    S.fields = null; S.cols = []; S.selected = new Set(); S.allMatching = false; S.rows = []; S.counts = {}; S.statsOpen = false;
    S.per_page = [20, 50, 100].includes(Number(cfg().per_page)) ? Number(cfg().per_page) : 20;
    setGlobal('form_ID_emsFormBuilder', S.fid);
    restoreState();
    return load();
  }

  W.EfbEntries = Object.assign(W.EfbEntries || {}, {
    state: S, openForm, load, paint, op, open, star, dialog, closeDialog, post, toast, announce, gate, planOk, viewerRepaint, viewerSay, historyLine, ago, cur, refreshExtras, printEntries, statsHtml, editOpen, editChanges,
    rowsOf, valueOf, digits, latin, num, fill, esc, T, isRtl,
    renderHead, renderRow, renderAll, renderPager, renderTabs, renderFilters,
    headButtons: (W.EfbEntries && W.EfbEntries.headButtons) || [],
    extraBulk: (W.EfbEntries && W.EfbEntries.extraBulk) || [],
    extraMenu: (W.EfbEntries && W.EfbEntries.extraMenu) || [],
    afterPaint: (W.EfbEntries && W.EfbEntries.afterPaint) || [],
    actions: (W.EfbEntries && W.EfbEntries.actions) || {},
    viewerButtons: (W.EfbEntries && W.EfbEntries.viewerButtons) || [],
  });
  /* H: the Export button (the server's CSV / XLSX) replaces the old page CSV button; "Export" on a selection. */
  if (!W.EfbEntries.headButtons.some((f) => f.efbExport)) {
    const b = () => `<button type="button" class="efb btn btn-sm btn-primary text-white" data-ent="export" aria-haspopup="dialog">${icon('bi-download')} <span>${esc(T('entExport', 'Export'))}</span></button>`;
    b.efbExport = true;
    W.EfbEntries.headButtons.unshift(b);
    const x = () => (S.tab === 'trash' ? '' : `<button type="button" class="efb btn btn-sm btn-outline-secondary" data-ent="export-sel">${icon('bi-download')} <span>${esc(T('entExport', 'Export'))}</span></button>`);
    W.EfbEntries.extraBulk.push(x);
    W.EfbEntries.actions.export = (el) => exportDialog(el, false);
    W.EfbEntries.actions['export-sel'] = (el) => exportDialog(el, true);
  }

  /* I: Print on a row's menu, on a selection and in the response viewer. */
  if (!W.EfbEntries.actions.print) {
    W.EfbEntries.extraMenu.push((row) => (Number(row.entry_status) === 2 ? '' : `<button type="button" role="menuitem" class="efb dropdown-item" data-ent="print" data-msgid="${Number(row.msg_id)}" tabindex="-1">${icon('bi-printer')} ${esc(T('entPrint', 'Print'))}</button>`));
    W.EfbEntries.extraBulk.push(() => (S.tab === 'trash' ? '' : `<button type="button" class="efb btn btn-sm btn-outline-secondary" data-ent="print-sel">${icon('bi-printer')} <span>${esc(T('entPrint', 'Print'))}</span></button>`));
    W.EfbEntries.viewerButtons.push((e) => `<button type="button" class="efb btn btn-sm btn-outline-secondary" data-ent="print" data-msgid="${Number(e.msg_id)}">${icon('bi-printer')} ${esc(T('entPrint', 'Print'))}</button>`);
    W.EfbEntries.actions.print = (el, id) => { closeMenu(); printDialog([Number(id)], el); };
    W.EfbEntries.actions['print-sel'] = (el) => printDialog(null, el);
  }
  W.efb_print_entries_efb = (ids, withNotes) => printEntries(ids, !!withNotes);

  /* J: the form's statistics (Free Plus) above the tabs. */
  if (!W.EfbEntries.actions.stats) {
    const sb = () => `<button type="button" class="efb btn btn-sm btn-outline-secondary" data-ent="stats" aria-expanded="${S.statsOpen ? 'true' : 'false'}" aria-controls="efbEntStats">${icon('bi-graph-up')} <span>${esc(T('entStats', 'Statistics'))}</span>${planOk('stats') ? '' : ' ' + icon('bi-gem')}</button>`;
    W.EfbEntries.headButtons.push(sb);
    W.EfbEntries.actions.stats = (el) => statsToggle(el, 0);
    W.EfbEntries.actions['stats-days'] = (el) => statsToggle(el, Number(el.dataset.days));
    W.EfbEntries.afterPaint.push(() => { if (S.statsOpen && !D.getElementById('efbEntStats')) { const b = D.querySelector('#efbEnt [data-ent="stats"]'); if (b) statsToggle(b, S.statsDays || 30); } });
  }

  /* F: Edit in the response viewer (Free Plus). */
  if (!W.EfbEntries.actions.edit) {
    W.EfbEntries.viewerButtons.unshift((e) => `<button type="button" class="efb btn btn-sm btn-outline-primary" data-ent="edit" data-msgid="${Number(e.msg_id)}">${icon('bi-pencil')} ${esc(T('entEdit', 'Edit entry'))}${planOk('edit') ? '' : ' ' + icon('bi-gem')}</button>`);
    W.EfbEntries.actions.edit = (el) => { if (gate('edit', el)) editOpen(); };
    W.EfbEntries.actions['ed-cancel'] = () => editClose(false);
    W.EfbEntries.actions['ed-add'] = (el) => { const w = D.getElementById(el.dataset.target); if (w) { w.hidden = false; el.remove(); const i = w.querySelector('input, select, textarea'); if (i) i.focus(); } };
  }
  if (D && !W.EfbEntries.editWired) {
    W.EfbEntries.editWired = true;
    D.addEventListener('submit', (e) => { if (e.target && e.target.id === 'efbEntEdit') editSave(e); });
    D.addEventListener('change', (e) => { if (e.target && e.target.classList && e.target.classList.contains('efb-ent-upfile')) editUpload(e.target); });
  }

  /* The renderers by the names of the spec (§6). */
  W.efb_entries_render_head_efb = renderHead;
  W.efb_entries_render_row_efb = renderRow;
})(typeof window !== 'undefined' ? window : globalThis);
