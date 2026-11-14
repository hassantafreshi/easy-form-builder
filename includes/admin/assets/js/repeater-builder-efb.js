/**
 * F09 in the builder: the repeater group (spec docs/feature-gaps/F09-repeater.fa.md §4.2, §6).
 *
 *  - the group on the canvas is a container ("Drop fields here", data-rpt-body) that holds its children;
 *    a field dropped in it, or moved in with "Move into group", gets group = <the group's id_> and the
 *    group's step; "Move out of group" puts it back after the group. The DOM is where membership lives,
 *    as it is where the order lives: sort_obj_el_efb_() writes `group` from it (efbRptSyncFromDom);
 *  - types a row may not hold (EFB_REPEATER_CHILD_TYPES) and groups inside groups are refused;
 *  - the settings: minimum / maximum / starting rows (1 ≤ max ≤ 50, 0 ≤ min ≤ max, min ≤ start ≤ max),
 *    "Number of rows from field" (a number / choice field before the group), row title with {n}, collapsible
 *    rows, layout, the Add button's text, "Ask before removing a row";
 *  - deleting a group deletes its fields and their options; duplicating it copies them with new ids;
 *  - Save refuses a group without fields (and names it).
 * Every key goes through the save rule EFBRepeaterCore.sanitizeKey (twin of \Emsfb\Repeater::sanitize_key()).
 */
(function (root) {
  'use strict';

  const T = (k, fb) => {
    try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return root.efb_var.text[k]; } catch (e) {}
    return fb;
  };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const C = () => root.EFBRepeaterCore || (typeof EFBRepeaterCore !== 'undefined' ? EFBRepeaterCore : null);
  const list = () => (typeof valj_efb !== 'undefined' ? valj_efb : []);
  const rowById = (id) => list().find((r) => r && r.id_ === id) || null;
  const cfg = () => (root.efb_var && root.efb_var.repeater) || {};
  const capOf = (k, d) => { const c = cfg().caps || {}; return Number(c[k]) > 0 ? Number(c[k]) : d; };
  const repeatOk = () => { const p = cfg().plan; return !p || p.repeat !== false; };
  const childTypes = () => (typeof EFB_REPEATER_CHILD_TYPES !== 'undefined' ? EFB_REPEATER_CHILD_TYPES : (cfg().child_types || (C() ? C().CHILD_TYPES : [])));
  const sanitize = (k, v) => (C() ? C().sanitizeKey(k, v, { rows: capOf('rows', 50) }) : String(v));
  const changed = (action, ids) => {
    try { if (typeof sessionStorage !== 'undefined') sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb)); } catch (e) {}
    try { document.dispatchEvent(new CustomEvent('efb:form-changed', { detail: { source: 'user', action: action || 'repeater', ids: ids || [] } })); } catch (e) {}
  };
  const say = (msg, kind) => { if (typeof alert_message_efb === 'function') alert_message_efb(kind === 'success' ? '' : T('error', 'Error'), msg, 8, kind || 'danger'); };
  const digits = (s) => (C() ? C().asciiDigits(s) : String(s));

  function isGroup(r) { return !!r && r.type === 'repeater'; }
  /** Children of a group in array order (base rows only). */
  function childrenOf(gid) { return list().filter((r, i) => i > 0 && r && r.group === gid && r.type !== 'option' && !isGroup(r)); }
  /** Whether a type may go into a row ('name' = first and last name). */
  function allowed(t) { return t === 'name' || childTypes().indexOf(t) !== -1; }

  /* ------------------------------------------------------------------ canvas */

  /** The container drawn inside the group's "-f" wrapper (addNewElement case 'repeater'). */
  function efbRptCanvas(r, preview) {
    if (!r) return '';
    const id = esc(r.id_);
    if (preview) return typeof efbRptPreviewEls === 'function' ? efbRptPreviewEls(r) : '';
    const l = C() ? C().limits(r, repeatOk()) : { min: 1, max: 10 };
    const add = r.btn_add_text ? r.btn_add_text : T('addRow', 'Add another');
    return `<div class="efb efb-rpt-cv-meta fs-7 text-muted mb-1" id="${id}_rptmeta" dir="auto">${esc(String(T('rptRowsRange', 'Rows: %1$s – %2$s')).replace('%1$s', l.min).replace('%2$s', l.max))}</div>
      <div class="efb efb-rpt-body row mx-0" data-rpt-body="${id}" id="${id}_rptbody" role="group" aria-label="${esc(r.name || T('repeater', 'Repeater group'))}"></div>
      <div class="efb efb-rpt-drop-hint fs-7 text-muted text-center py-2" id="${id}_rpthint" aria-hidden="true"><i class="efb bi-box-arrow-in-down"></i> ${esc(T('rptDropHere', 'Drop fields here'))}</div>
      <button type="button" class="efb btn btn-sm btn-outline-pink efb-rpt-add-cv mt-1" id="${id}_rptaddcv" tabindex="-1" disabled><i class="efb bi-plus-lg"></i> <span id="${id}_rptaddtxt">${esc(add)}</span></button>`;
  }

  /** Buttons in a field's toolbar: Move into group / Move out of group (CSS shows the one that applies). */
  function efbRptToolbar(r, rndm) {
    if (!r || isGroup(r) || !allowed(r.elementId || r.type) || !allowed(r.type)) return '';
    return `<button type="button" class="efb btn btn-edit btn-sm efb-rpt-in" data-id="${esc(rndm)}-id" title="${esc(T('moveToGroup', 'Move into group'))}" aria-label="${esc(T('moveToGroup', 'Move into group'))}" onclick="efbRptMoveIntoAsk('${esc(rndm)}')"><i class="efb bi-box-arrow-in-right text-muted"></i></button>`
      + `<button type="button" class="efb btn btn-edit btn-sm efb-rpt-out" data-id="${esc(rndm)}-id" title="${esc(T('moveOutGroup', 'Move out of group'))}" aria-label="${esc(T('moveOutGroup', 'Move out of group'))}" onclick="efbRptMoveOut('${esc(rndm)}')"><i class="efb bi-box-arrow-left text-muted"></i></button>`;
  }

  /** After the canvas is drawn from valj_efb (editFormEfb, a template): each child into its group's container. */
  function efbRptPlaceChildren() {
    const L = list();
    const groups = {};
    L.forEach((r, i) => { if (i > 0 && isGroup(r)) groups[r.id_] = true; });
    L.forEach((r, i) => {
      if (i < 1 || !r || !r.group || !groups[r.group] || r.type === 'option' || isGroup(r)) return;
      const body = document.getElementById(`${r.group}_rptbody`);
      const el = document.getElementById(r.id_); // the canvas <section> (its inner div has the same id)
      if (body && el && el !== body && !el.contains(body)) body.appendChild(el);
    });
    hints();
  }

  /** The "Drop fields here" hint only on an empty group. */
  function hints() {
    document.querySelectorAll('[data-rpt-body]').forEach((b) => {
      const h = document.getElementById(`${b.dataset.rptBody}_rpthint`);
      if (h) h.classList.toggle('d-none', b.querySelector('.efbField') !== null);
    });
  }

  /** Membership from the DOM (called by sort_obj_el_efb_ after it wrote amount and step). */
  function efbRptSyncFromDom() {
    const L = list();
    if (!document.querySelector('[data-rpt-body]') && !L.some((r) => r && r.group)) return;
    L.forEach((r, i) => {
      if (i < 1 || !r || r.type === 'option' || r.type === 'step' || r.type === 'form') return;
      const el = document.getElementById(r.id_);
      if (!el) return;
      const body = el.parentElement ? el.parentElement.closest('[data-rpt-body]') : null;
      const gid = body ? body.dataset.rptBody : '';
      if (gid && gid !== r.id_ && !isGroup(r)) {
        r.group = gid;
        const g = rowById(gid);
        if (g) r.step = g.step;
      } else if (Object.prototype.hasOwnProperty.call(r, 'group')) {
        delete r.group;
      }
    });
    hints();
  }

  /* ------------------------------------------------------------------ adding and moving */

  /** A palette field dropped into a group (or added with its button). Returns the new ids or null. */
  function efbRptDropInto(gid, t, afterEl) {
    const g = rowById(gid);
    if (!g || !isGroup(g)) return null;
    if (t === 'repeater' || !allowed(t)) { say(T('rptTypeNotAllowed', 'This field type cannot be placed inside a repeater group.')); return null; }
    if (childrenOf(gid).length >= capOf('children', 25)) { say(String(T('rptChildrenCap', 'A repeater group holds at most %s fields.')).replace('%s', capOf('children', 25))); return null; }
    const body = document.getElementById(`${gid}_rptbody`);
    if (!body) return null;
    if (typeof step_el_efb !== 'undefined') step_el_efb = Number(g.step) || 1;
    const types = t === 'name' ? ['firstName', 'lastName'] : [t];
    const ids = [];
    let ref = afterEl && afterEl.parentElement === body ? afterEl.nextSibling : null;
    types.forEach((tt) => {
      const rndm = Math.random().toString(36).substr(2, 9);
      const html = addNewElement(tt, rndm, false, false);
      if (!html || html === 'null') return;
      const tmp = document.createElement('div');
      tmp.innerHTML = html;
      Array.from(tmp.children).forEach((n) => body.insertBefore(n, ref));
      const r = rowById(rndm);
      if (r) { r.group = gid; r.step = g.step; ids.push(rndm); }
    });
    if (typeof sort_obj_el_efb_ === 'function') sort_obj_el_efb_();
    if (typeof fub_shwBtns_efb === 'function') fub_shwBtns_efb();
    if (typeof efbProjectMobileViewEfb === 'function') efbProjectMobileViewEfb();
    hints();
    changed('add', ids);
    return ids;
  }

  /** A new group gets one text field ("Name"), so it is never empty. */
  function efbRptAfterAdd(gid) {
    const g = rowById(gid);
    if (!g || childrenOf(gid).length) return;
    const ids = efbRptDropInto(gid, 'text', null);
    if (ids && ids[0]) {
      const r = rowById(ids[0]);
      if (r) { r.name = T('name', 'Name'); const lab = document.getElementById(`${ids[0]}_lab`); if (lab) lab.textContent = r.name; }
    }
  }

  function moveInto(fid, gid) {
    const r = rowById(fid), g = rowById(gid);
    if (!r || !g || !isGroup(g)) return false;
    if (isGroup(r) || !allowed(r.elementId || r.type) || !allowed(r.type)) { say(T('rptTypeNotAllowed', 'This field type cannot be placed inside a repeater group.')); return false; }
    if (childrenOf(gid).length >= capOf('children', 25)) { say(String(T('rptChildrenCap', 'A repeater group holds at most %s fields.')).replace('%s', capOf('children', 25))); return false; }
    const body = document.getElementById(`${gid}_rptbody`);
    const el = document.getElementById(fid);
    if (!body || !el) return false;
    body.appendChild(el);
    r.group = gid;
    r.step = g.step;
    if (typeof sort_obj_el_efb_ === 'function') sort_obj_el_efb_();
    hints();
    changed('move', [fid]);
    return true;
  }

  /** "Move into group": the only group, or a choice of groups. */
  function efbRptMoveIntoAsk(fid) {
    const groups = list().filter((r, i) => i > 0 && isGroup(r));
    if (!groups.length) { say(T('rptNoGroup', 'Add a repeater group to the form first.'), 'warning'); return; }
    if (groups.length === 1) { moveInto(fid, groups[0].id_); return; }
    const opts = groups.map((g) => `<option value="${esc(g.id_)}">${esc(g.name || g.id_)}</option>`).join('');
    const body = `<div class="efb p-2"><label class="efb form-label" for="efbRptPickEl">${esc(T('moveToGroup', 'Move into group'))}</label>
      <select class="efb form-select" id="efbRptPickEl">${opts}</select>
      <button type="button" class="efb btn btn-primary mt-3" id="efbRptPickOk">${esc(T('ok', 'OK'))}</button></div>`;
    if (typeof show_modal_efb === 'function') {
      show_modal_efb(body, T('moveToGroup', 'Move into group'), 'efb bi-box-arrow-in-right mx-2', 'deleteBox', {
        onShown: () => {
          const ok = document.getElementById('efbRptPickOk');
          if (ok) ok.addEventListener('click', () => { moveInto(fid, document.getElementById('efbRptPickEl').value); if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0); });
        }
      });
      if (typeof state_modal_show_efb === 'function') state_modal_show_efb(1);
    }
  }

  /** "Move out of group": right after the group, as an ordinary field. */
  function efbRptMoveOut(fid) {
    const r = rowById(fid);
    if (!r || !r.group) return false;
    const gEl = document.getElementById(r.group);
    const el = document.getElementById(fid);
    if (!gEl || !el || !gEl.parentElement) return false;
    gEl.parentElement.insertBefore(el, gEl.nextSibling);
    delete r.group;
    if (typeof sort_obj_el_efb_ === 'function') sort_obj_el_efb_();
    hints();
    changed('move', [fid]);
    return true;
  }

  /* ------------------------------------------------------------------ deleting and duplicating */

  /** The rows a group's delete takes with it (children, their options); rows_from that read them cleared. */
  function efbRptOnDelete(gid) {
    const L = list();
    const kids = childrenOf(gid).map((r) => r.id_);
    const gone = new Set(kids);
    for (let i = L.length - 1; i > 0; i--) {
      const r = L[i];
      if (r && (gone.has(r.id_) || (r.type === 'option' && gone.has(r.parent)) || (r.type === 'r_matrix' && gone.has(r.parent)))) L.splice(i, 1);
    }
    L.forEach((r) => { if (r && isGroup(r) && (r.rows_from === gid || gone.has(r.rows_from))) r.rows_from = ''; });
    return kids;
  }

  /** The text of the delete question for a group ("Delete the group and the N fields inside it?"). */
  function efbRptDeleteText(gid) {
    const n = childrenOf(gid).length;
    return String(T('rptDeleteConfirm', 'Delete the group and the %s fields inside it?')).replace('%s', n);
  }

  /** Duplicate a group with its fields and options (new ids; group remapped). */
  function efbRptDuplicate(gid) {
    const L = list();
    const gi = L.findIndex((r) => r && r.id_ === gid);
    if (gi < 1) return false;
    const nid = () => Math.random().toString(36).substr(2, 9);
    const newG = nid();
    const copy = Object.assign({}, L[gi], { id_: newG, dataId: `${newG}-id`, name: `${L[gi].name || ''} - ${T('copy', 'copy')}` });
    const block = [copy];
    let last = gi;
    const kids = childrenOf(gid);
    kids.forEach((c) => {
      const ci = L.indexOf(c);
      const id = nid();
      block.push(Object.assign({}, c, { id_: id, dataId: `${id}-id`, group: newG }));
      L.forEach((o, oi) => {
        if (o && o.type === 'option' && o.parent === c.id_) {
          const oid = nid();
          block.push(Object.assign({}, o, { id_: oid, id_op: oid, dataId: `${oid}-id`, parent: id }));
          if (oi > last) last = oi;
        }
      });
      if (ci > last) last = ci;
    });
    L.splice(last + 1, 0, ...block);
    L.forEach((r, i) => { if (i > 0 && r) r.amount = i; });
    changed('duplicate', [newG]);
    return newG;
  }

  /* ------------------------------------------------------------------ settings */

  function num(id, key, r, label, min, max, locked) {
    const v = r[key] === undefined || r[key] === null ? (C() ? C().DEFAULTS[key] : '') : String(r[key]);
    return `<label for="${id}" class="efb form-label mt-2 mb-1">${esc(label)}</label>
      <input type="text" inputmode="numeric" dir="ltr" data-min="${min}" data-max="${max}" data-id="${esc(r.dataId || r.id_ + '-id')}" data-key="${key}" class="efb elEdit form-control efb-rpt-ctl text-muted border-d rounded-4 h-d-efb mb-1" id="${id}" value="${esc(v)}"${locked ? ' disabled aria-disabled="true"' : ''}>`;
  }
  function text(id, key, r, label, ph, help) {
    const v = r[key] === undefined || r[key] === null ? '' : String(r[key]);
    return `<label for="${id}" class="efb form-label mt-2 mb-1">${esc(label)}</label>
      <input type="text" data-id="${esc(r.dataId || r.id_ + '-id')}" data-key="${key}" class="efb elEdit form-control efb-rpt-ctl text-muted border-d rounded-4 h-d-efb mb-1" id="${id}" dir="auto" placeholder="${esc(ph || '')}" value="${esc(v)}"${help ? ` aria-describedby="${id}Help"` : ''}>
      ${help ? `<small class="efb form-text text-muted fs-8 d-block" id="${id}Help">${esc(help)}</small>` : ''}`;
  }
  function check(id, key, r, label, locked) {
    const on = String(r[key] === undefined ? (C() ? C().DEFAULTS[key] : '0') : r[key]) === '1';
    return `<div class="efb form-check form-switch mt-2"><input type="checkbox" role="switch" data-id="${esc(r.dataId || r.id_ + '-id')}" data-key="${key}" class="efb elEdit form-check-input efb-rpt-ctl" id="${id}" ${on ? 'checked' : ''}${locked ? ' disabled aria-disabled="true"' : ''}>
      <label class="efb form-check-label" for="${id}">${esc(label)}</label></div>`;
  }
  function gem() {
    return repeatOk() ? '' : `<a type="button" role="button" tabindex="0" class="efb pro-version-efb" onclick="if (typeof efb_upgrade_prompt_efb === 'function') efb_upgrade_prompt_efb({ feature: 'setting:repeater_rows', fallback: 'pro', origin: 'field_settings', opener: this });" aria-label="${esc(T('proUnlockMsg', 'Pro'))}"><i class="efb bi-gem text-light" aria-hidden="true"></i></a>`;
  }
  /** Fields whose value may give the number of rows: number / slider / select / radio / calc before the group, outside any group. */
  function sourcesFor(gid) {
    const L = list();
    const gi = L.findIndex((r) => r && r.id_ === gid);
    const kinds = C() ? C().COUNT_SOURCE_TYPES : ['number', 'range', 'select', 'radio', 'calc'];
    return L.filter((r, i) => i > 0 && i < gi && r && kinds.indexOf(r.type) !== -1 && !r.group);
  }

  /** The group's settings (show_setting_window_efb case 'repeater'). */
  function efbRptPanelEls(indx, idset) {
    const r = list()[indx];
    if (!r) return '';
    const cap = capOf('rows', 50);
    const locked = !repeatOk();
    const src = sourcesFor(r.id_);
    const opts = [`<option value="" ${!r.rows_from ? 'selected' : ''}>${esc(T('rowsFromManual', 'Visitor adds rows'))}</option>`]
      .concat(src.map((s) => `<option value="${esc(s.id_)}" ${r.rows_from === s.id_ ? 'selected' : ''}>${esc(s.name || s.id_)}</option>`)).join('');
    const layout = r.rpt_layout === 'inline' ? 'inline' : 'blocks';
    return `<div class="efb mt-2 efb-rpt-panel" id="efbRptPanel" data-id="${esc(r.id_)}">
      ${gem()}
      ${num('minRowsEl', 'min_rows', r, T('minRows', 'Minimum rows'), 0, cap, locked)}
      ${num('maxRowsEl', 'max_rows', r, T('maxRows', 'Maximum rows'), 1, cap, locked)}
      ${num('defaultRowsEl', 'default_rows', r, T('defaultRows', 'Rows shown at start'), 0, cap, locked)}
      <small class="efb form-text text-muted fs-8 d-block" id="efbRptLimitsHelp">${esc(String(T('rptLimitsHelp', 'At most %s rows.')).replace('%s', cap))}</small>
      <label for="rowsFromEl" class="efb form-label mt-2 mb-1">${esc(T('rowsFrom', 'Number of rows from field'))}</label>
      <select data-id="${esc(r.dataId || r.id_ + '-id')}" data-key="rows_from" class="efb elEdit form-select efb-rpt-ctl border-d rounded-4 mb-1" id="rowsFromEl"${locked ? ' disabled aria-disabled="true"' : ''}>${opts}</select>
      ${text('rowLabelEl', 'row_label', r, T('rowLabel', 'Row title'), String(T('rowN', 'Row %s')).replace('%s', '{n}'), T('rowLabelHelp', 'Use {n} for the row number.'))}
      ${text('btnAddTextEl', 'btn_add_text', r, T('rptBtnAdd', 'Add button text'), T('addRow', 'Add another'))}
      ${check('collapsibleEl', 'collapsible', r, T('collapsibleRows', 'Rows can be collapsed'), locked)}
      ${check('collapsePrevEl', 'collapse_prev', r, T('collapsePrev', 'Collapse earlier rows when a row is added'), locked)}
      ${check('rmvConfirmEl', 'rmv_confirm', r, T('rmvConfirm', 'Ask before removing a row'), false)}
      <label for="rptLayoutEl" class="efb form-label mt-2 mb-1">${esc(T('rptLayout', 'Layout'))}</label>
      <select data-id="${esc(r.dataId || r.id_ + '-id')}" data-key="rpt_layout" class="efb elEdit form-select efb-rpt-ctl border-d rounded-4 mb-1" id="rptLayoutEl"${locked ? ' disabled aria-disabled="true"' : ''}>
        <option value="blocks" ${layout === 'blocks' ? 'selected' : ''}>${esc(T('rptLayoutBlocks', 'One card per row'))}</option>
        <option value="inline" ${layout === 'inline' ? 'selected' : ''}>${esc(T('rptLayoutInline', 'Fields side by side'))}</option>
      </select>
    </div>`;
  }

  /** change_el_edit_Efb for the group's settings. Returns false when the value was refused. */
  function efbRptBuilderChange(el, indx) {
    const r = list()[indx];
    if (!r || !el) return false;
    const key = el.dataset.key;
    const cap = capOf('rows', 50);
    const int = (v) => { const s = digits(v).trim(); return /^\d{1,4}$/.test(s) ? parseInt(s, 10) : NaN; };
    const lim = C() ? C().limits(r, true) : { min: 1, max: 10, 'default': 1 };
    const refuse = (msg) => { say(msg); el.value = r[key] !== undefined ? r[key] : (C() ? C().DEFAULTS[key] : ''); return false; };
    switch (el.id) {
      case 'minRowsEl': case 'maxRowsEl': case 'defaultRowsEl': {
        const n = int(el.value);
        if (isNaN(n)) return refuse(T('rptLimitsBad', 'Please enter a whole number.'));
        if (el.id === 'maxRowsEl' && (n < 1 || n > cap)) return refuse(String(T('rptMaxBad', 'The maximum must be between 1 and %s.')).replace('%s', cap));
        if (el.id === 'minRowsEl' && n > (r.max_rows !== undefined ? int(r.max_rows) : lim.max)) return refuse(T('rptMinBad', 'The minimum cannot be more than the maximum.'));
        if (el.id === 'maxRowsEl' && n < (r.min_rows !== undefined ? int(r.min_rows) : lim.min)) return refuse(T('rptMinBad', 'The minimum cannot be more than the maximum.'));
        if (el.id === 'defaultRowsEl' && (n < (r.min_rows !== undefined ? int(r.min_rows) : lim.min) || n > (r.max_rows !== undefined ? int(r.max_rows) : lim.max))) return refuse(T('rptDefaultBad', 'Rows shown at start must be between the minimum and the maximum.'));
        r[key] = sanitize(key, String(n));
        break;
      }
      case 'rowsFromEl': {
        const v = sanitize('rows_from', el.value);
        if (v && !sourcesFor(r.id_).some((s) => s.id_ === v)) return refuse(T('rptFromBad', 'Choose a number or choice field placed before the group.'));
        r.rows_from = v;
        break;
      }
      case 'rowLabelEl': r.row_label = sanitize('row_label', el.value); break;
      case 'btnAddTextEl': r.btn_add_text = sanitize('btn_add_text', el.value); break;
      case 'collapsibleEl': case 'collapsePrevEl': case 'rmvConfirmEl': r[key] = el.checked ? '1' : '0'; break;
      case 'rptLayoutEl': r.rpt_layout = sanitize('rpt_layout', el.value); break;
      default: return false;
    }
    // Canvas: the limits line and the Add button's text.
    const l = C() ? C().limits(r, repeatOk()) : lim;
    const meta = document.getElementById(`${r.id_}_rptmeta`);
    if (meta) meta.textContent = String(T('rptRowsRange', 'Rows: %1$s – %2$s')).replace('%1$s', l.min).replace('%2$s', l.max);
    const add = document.getElementById(`${r.id_}_rptaddtxt`);
    if (add) add.textContent = r.btn_add_text ? r.btn_add_text : T('addRow', 'Add another');
    changed('setting', [r.id_]);
    return true;
  }

  /* ------------------------------------------------------------------ save */

  /** '' when the groups are fine; else the message Save shows (a group without fields; a child in another step is moved). */
  function efbRptSaveCheck() {
    const L = list();
    const groups = L.filter((r, i) => i > 0 && isGroup(r));
    if (!groups.length) return '';
    if (groups.length > capOf('groups', 10)) return String(T('rptGroupsCap', 'A form holds at most %s repeater groups.')).replace('%s', capOf('groups', 10));
    for (const g of groups) {
      const kids = childrenOf(g.id_);
      if (!kids.length) return String(T('rptNoChildren', 'Add at least one field to the repeater group %s.')).replace('%s', g.name || g.id_);
      kids.forEach((c) => { if (String(c.step) !== String(g.step)) c.step = g.step; });
      const bad = kids.find((c) => !allowed(c.type));
      if (bad) return `${T('rptTypeNotAllowed', 'This field type cannot be placed inside a repeater group.')} (${bad.name || bad.id_})`;
    }
    return '';
  }

  const api = { efbRptCanvas, efbRptToolbar, efbRptPlaceChildren, efbRptSyncFromDom, efbRptDropInto, efbRptAfterAdd, efbRptMoveIntoAsk, efbRptMoveOut,
    efbRptMoveInto: moveInto, efbRptOnDelete, efbRptDeleteText, efbRptDuplicate, efbRptPanelEls, efbRptBuilderChange, efbRptSaveCheck, efbRptChildrenOf: childrenOf };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
  if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
