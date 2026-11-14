/**
 * F05 in the builder: choice fields (spec docs/feature-gaps/F05-choices.fa.md §4, §6).
 *
 *  - "Add in bulk" (lines "Label|value", ready-made lists by AJAX efb_choice_presets_Emsfb), replace or
 *    append, a live preview (counts, repeated labels, refused characters), 500 lines at most;
 *  - per choice (the "Show attributes" row): the stored value (opt_value, unique in the field), "Keep in
 *    place" (op_lock) and the capacity (inv_cap);
 *  - per field: Shuffle, "Other", "Select all", min / max of a checkbox, per-choice capacity (Pro),
 *    choices from the site (Pro: posts, terms, users, a CSV file; preview by AJAX efb_dyn_preview_Emsfb),
 *    and the ranking's options;
 *  - the canvas: the "Other" choice, "Select all" and a "Shuffled" badge; the ranking widget.
 * Every key is written to valj_efb directly with the save rules (EfbChoices.rules.sanitizeKey, the twin
 * of \Emsfb\Choices::sanitize_key()); the server checks every key again on save and every answer on submit.
 */
(function (root) {
  'use strict';

  const T = (k, fb) => {
    try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return root.efb_var.text[k]; } catch (e) {}
    return fb;
  };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const C = () => root.EfbChoices || (typeof EfbChoices !== 'undefined' ? EfbChoices : null);
  const rules = () => (C() ? C().rules : null);
  const list = () => (typeof valj_efb !== 'undefined' ? valj_efb : []);
  const rowById = (id) => list().find((r) => r && r.id_ === id) || null;
  const optsOf = (pid) => list().filter((r) => r && r.type === 'option' && r.parent === pid);
  const on = (r, k) => !!r && (r[k] === '1' || r[k] === 1 || r[k] === true);
  const has = (type, part) => { const R = rules(); return !!R && R.TYPES[part] && R.TYPES[part].indexOf(type) !== -1; };
  const icon = () => (typeof iconMarginGlobal !== 'undefined' ? iconMarginGlobal : 'me-1');

  const CAP = { inventory: 'setting:choice_inventory', dynamic: 'setting:choice_dynamic', ranking: 'field:ranking' };
  function isPaid() {
    return (typeof pro_efb !== 'undefined' && pro_efb === true) || !!(root.efb_var && (root.efb_var.pro == '1' || root.efb_var.pro === 1 || root.efb_var.pro === true));
  }
  function plan(part) {
    const key = CAP[part];
    if (!key) return true;
    const cap = root.efb_var && root.efb_var.capabilities && root.efb_var.capabilities.features;
    if (cap && cap[key]) return !!cap[key].available_now;
    if (typeof efb_capability_available_efb === 'function') { try { return efb_capability_available_efb(key, isPaid()); } catch (e) {} }
    return isPaid();
  }
  function gem(part) {
    if (plan(part)) return '';
    const key = CAP[part];
    return ` <a type="button" role="button" tabindex="0" class="efb pro-version-efb efb-ch-gem" data-cap="${key}" onclick="efb_ch_gem_efb(this)" aria-label="${esc(T('proUnlockMsg', 'Pro'))}"><i class="efb bi-gem text-light" aria-hidden="true"></i></a>`;
  }
  function efb_ch_gem_efb(btn) {
    const cap = btn && btn.getAttribute('data-cap');
    if (typeof efb_upgrade_prompt_efb === 'function') efb_upgrade_prompt_efb({ feature: cap, fallback: 'pro', origin: 'field_settings', opener: btn });
    else if (typeof efb_capability_prompt_efb === 'function') efb_capability_prompt_efb(cap, 1, btn);
  }
  const lock = (part) => (part && !plan(part) ? ' disabled aria-disabled="true"' : '');

  /* ------------------------------------------------------------------ controls */

  function toggleEl(r, id, key, label, opts) {
    opts = opts || {};
    const active = on(r, key);
    return `<div class="efb mx-1 my-2 efb-ch-row ${opts.wrap || ''}">
      <button type="button" id="${id}" data-id="${esc(r.id_)}" data-key="${key}" class="efb mx-0 btn h-s-efb btn-toggle efb-ch ${active ? 'active' : ''}" aria-pressed="${active ? 'true' : 'false'}" autocomplete="off" onclick="efb_ch_change_efb(this)"${opts.plan ? lock(opts.plan) : ''}>
      <div class="efb handle"></div>
      </button>
      <label class="efb form-check-label fs-7" for="${id}">${esc(label)}${opts.plan ? gem(opts.plan) : ''}</label>
      ${opts.help ? `<small class="efb form-text text-muted d-block fs-8 mx-2" id="${id}Help">${esc(opts.help)}</small>` : ''}
    </div>`;
  }
  function inputEl(r, id, key, label, opts) {
    opts = opts || {};
    const v = r && r[key] !== undefined && r[key] !== null ? String(r[key]) : '';
    return `<div class="efb mt-2 mb-1 ${opts.wrap || ''}">
      <label for="${id}" class="efb mb-1 fs-7">${esc(label)}${opts.plan ? gem(opts.plan) : ''}</label>
      <input type="text" ${opts.numeric ? 'inputmode="numeric" maxlength="7"' : `maxlength="${opts.max || 200}"`} class="efb form-control efb h-d-efb border-d rounded-4 efb-ch" id="${id}" data-id="${esc(r.id_)}" data-key="${key}" value="${esc(v)}" placeholder="${esc(opts.placeholder || '')}" dir="auto" onchange="efb_ch_change_efb(this)"${opts.plan ? lock(opts.plan) : ''}${opts.help ? ` aria-describedby="${id}Help"` : ''}>
      ${opts.help ? `<small class="efb form-text text-muted d-block mt-1 fs-8" id="${id}Help">${esc(opts.help)}</small>` : ''}
    </div>`;
  }
  function selectEl(r, id, key, label, options, dflt, opts) {
    opts = opts || {};
    const cur = r && typeof r[key] === 'string' && r[key] !== '' ? r[key] : dflt;
    return `<div class="efb mt-2 mb-1 ${opts.wrap || ''}">
      <label for="${id}" class="efb mb-1 fs-7">${esc(label)}${opts.plan ? gem(opts.plan) : ''}</label>
      <select class="efb form-select efb border-d rounded-4 efb-ch" id="${id}" data-id="${esc(r.id_)}" data-key="${key}" onchange="efb_ch_change_efb(this)"${opts.plan ? lock(opts.plan) : ''}>
        ${options.map((o) => `<option value="${esc(o[0])}" ${cur === o[0] ? 'selected' : ''}>${esc(o[1])}</option>`).join('')}
      </select>
    </div>`;
  }

  /** "Add in bulk" next to the "+" of the choices list. */
  function efbBulkBtnEl(pid, tag) {
    if (['table_matrix', 'conturyList', 'stateProvince', 'cityList', 'trmCheckbox'].indexOf(tag) !== -1) return '';
    return `<button type="button" id="bulkAddOptEl" onclick="efb_bulk_options_modal('${esc(pid)}','${esc(tag)}')" class="efb btn btn-sm btn-outline-pink mx-1 fs-7" data-parent="${esc(pid)}" data-tag="${esc(tag)}"><i class="efb bi-list-ul ${icon()}" aria-hidden="true"></i>${esc(T('bulkAdd', 'Add in bulk'))}</button>`;
  }

  /** The F05 attributes of one choice, in its "Show attributes" row (the stored value, keep in place, capacity). */
  function efbChoicesOptionEls(pid, optId, tag) {
    const o = rowById(optId);
    const p = rowById(pid);
    if (!o || !p || o.type !== 'option' || tag === 'table_matrix') return '';
    const oid = esc(o.id_);
    let out = `<div class="efb mx-0 px-0 col-sm-12 row efb-ch-optattrs">
      <label for="ElValueOptions-${oid}" class="efb form-label mx-1 my-0 py-0 fs-7 col-sm-3">${esc(T('optValue', 'Stored value'))}</label>
      <input type="text" maxlength="200" placeholder="${esc(o.value || '')}" id="ElValueOptions-${oid}" value="${esc(o.opt_value || '')}" data-id="${oid}" data-key="opt_value" dir="auto" class="efb efb-ch text-muted mb-1 fs-7 border-d rounded-4 col-sm-8" onchange="efb_ch_change_efb(this)" aria-describedby="ElValueOptions-${oid}Help">
      <small class="efb form-text text-muted fs-8 col-sm-12 mb-1" id="ElValueOptions-${oid}Help">${esc(T('optValPublic', 'Visitors can see this value in the page source.'))}</small>`;
    if (has(p.type, 'random') && p.type !== 'ranking') {
      out += `<div class="efb col-sm-12 d-flex align-items-center">${toggleEl(o, 'ElLockOptions-' + oid, 'op_lock', T('opLock', 'Keep in place'))}</div>`;
    }
    if (has(p.type, 'inventory') && on(p, 'inv_on')) {
      out += `<div class="efb col-sm-12">${inputEl(o, 'ElCapOptions-' + oid, 'inv_cap', T('invCap', 'Available'), { numeric: true, plan: 'inventory', help: T('invCapNone', 'Empty = no limit') })}</div>`;
    }
    return out + '</div>';
  }

  /** The field settings of F05 (under the choices list of a choice field). */
  function efbChoicesPanelEls(indx, idset, tag) {
    const r = list()[indx];
    if (!r || !rules()) return '<!-- F05: choices-efb.js not loaded -->';
    const t = r.type;
    const id = r.id_;
    let html = `<div class="efb mt-3 efb-ch-panel" id="efbChPanel" data-id="${esc(id)}">`;
    if (has(t, 'random')) html += toggleEl(r, 'opRandomEl', 'op_random', T('opRandom', 'Shuffle choices'), { help: t === 'ranking' ? '' : T('opRandomBadge', 'Shuffled for each visitor') });
    if (has(t, 'other')) {
      html += toggleEl(r, 'opOtherEl', 'op_other', T('otherAllow', 'Add an “Other” choice'));
      if (on(r, 'op_other')) {
        html += inputEl(r, 'opOtherLabelEl', 'op_other_label', T('otherLabel', 'Text of the “Other” choice'), { max: 80, placeholder: T('otherOpt', 'Other') });
        html += inputEl(r, 'opOtherMaxEl', 'op_other_max', T('otherMax', 'Longest “Other” answer (characters)'), { numeric: true, placeholder: '200' });
      }
    }
    if (has(t, 'select_all')) {
      const max = parseInt(r.maxSelect || '0', 10) || 0;
      const n = optsOf(id).length;
      html += toggleEl(r, 'opSelectAllEl', 'op_select_all', T('selectAll', 'Select all'), { help: on(r, 'op_select_all') && max > 0 && max < n ? T('selAllMax', '“Select all” is hidden because the maximum is lower than the number of choices.') : '' });
    }
    if (has(t, 'minmax') && t.indexOf('ulti') === -1) {
      html += `<div class="efb row mx-0">${inputEl(r, 'chMinSelectEl', 'minSelect', T('minSelect', 'Min selection'), { numeric: true, wrap: 'col-6 px-1', placeholder: '0' })}${inputEl(r, 'chMaxSelectEl', 'maxSelect', T('maxSelect', 'Max selection'), { numeric: true, wrap: 'col-6 px-1', placeholder: '0' })}</div>
        <small class="efb form-text text-muted d-block fs-8 mx-1">${esc(T('minMaxNoLimit', '0 or empty = no limit'))}</small>`;
    }
    if (has(t, 'inventory') && !(list()[0] && (list()[0].booking == 1 || list()[0].booking === true))) {
      html += `<div class="efb mt-3 border-top pt-2 efb-ch-inv">` + toggleEl(r, 'invOnEl', 'inv_on', T('invTrack', 'Limit how many times each choice can be picked'), { plan: 'inventory' });
      if (on(r, 'inv_on')) {
        html += selectEl(r, 'invShowEl', 'inv_show', T('invShowMode', 'When a choice is used up'), [['disable', T('invShowDisable', 'Show it turned off')], ['hide', T('invShowHide', 'Hide it')], ['label', T('invShowLabel', 'Show “Fully booked” next to it')]], 'disable', { plan: 'inventory' });
        html += toggleEl(r, 'invLeftEl', 'inv_left', T('invShowLeft', 'Show how many are left'), { plan: 'inventory' });
        html += toggleEl(r, 'invReleaseEl', 'inv_release', T('invRelease', 'Free the place when an entry is deleted'), { plan: 'inventory' });
        html += `<small class="efb form-text text-muted d-block fs-8 mx-2">${esc(T('invCap', 'Available'))}: ${esc(T('shwattr', 'Show attributes'))}</small>`;
      }
      html += '</div>';
    }
    if (has(t, 'dynamic')) html += dynEls(r);
    if (t === 'ranking') {
      html += inputEl(r, 'rankTopEl', 'rank_top', T('rankTop', 'Rank only the first (0 = all)'), { numeric: true, placeholder: '0' });
      html += toggleEl(r, 'rankTouchEl', 'rank_touch', T('rankTouch', 'The visitor must move an item or confirm the order'));
    }
    return html + '</div>';
  }

  function dynEls(r) {
    const src = r.dyn_src || '';
    let html = `<div class="efb mt-3 border-top pt-2 efb-ch-dyn">` + selectEl(r, 'dynSrcEl', 'dyn_src', T('dynSource', 'Choices come from'),
      [['', T('dynManual', 'Typed here')], ['posts', T('dynPosts', 'Posts or pages')], ['terms', T('dynTerms', 'Categories or tags')], ['users', T('dynUsers', 'Users')], ['csv', T('dynCsv', 'CSV file')]], '', { plan: 'dynamic' });
    if (src) {
      html += `<small class="efb form-text text-muted d-block fs-8 mx-1">${esc(T('dynManualKept', 'Your typed choices are kept and come back when you switch to “Typed here”.'))}</small>`;
      if (src === 'posts') html += inputEl(r, 'dynPostTypeEl', 'dyn_post_type', T('dynPostType', 'Content type'), { placeholder: 'post', max: 40 }) + inputEl(r, 'dynTaxEl', 'dyn_tax', T('dynTax', 'Taxonomy'), { placeholder: 'category', max: 40 }) + inputEl(r, 'dynTermEl', 'dyn_term', T('dynTerm', 'Only these categories (ids or slugs, comma separated)'), { max: 200 });
      if (src === 'terms') html += inputEl(r, 'dynTaxEl', 'dyn_tax', T('dynTax', 'Taxonomy'), { placeholder: 'category', max: 40 });
      if (src === 'users') html += `<div class="efb alert alert-warning fs-7 my-2 py-1" role="note">${esc(T('dynUsersPublic', 'User display names will be visible to everyone who opens the form.'))}</div>` + inputEl(r, 'dynRoleEl', 'dyn_role', T('dynRole', 'Only these roles'), { max: 200 });
      if (src === 'csv') html += `<div class="efb mt-2"><button type="button" class="efb btn btn-sm btn-outline-pink" id="dynCsvPickEl" onclick="efb_dyn_csv_pick_efb('${esc(r.id_)}')"><i class="efb bi-filetype-csv ${icon()}" aria-hidden="true"></i>${esc(T('dynCsvPick', 'Choose a CSV file'))}</button> <span class="efb fs-8 text-muted" id="dynCsvIdEl">${r.dyn_csv ? '#' + esc(r.dyn_csv) : ''}</span></div>`;
      if (src === 'posts' || src === 'terms') html += selectEl(r, 'dynOrderByEl', 'dyn_orderby', T('dynOrderBy', 'Order by'), [['title', T('title', 'Title')], ['date', T('date', 'Date')], ['menu_order', 'menu_order'], ['name', 'name']], 'title');
      html += inputEl(r, 'dynLimitEl', 'dyn_limit', T('dynLimit', 'How many choices'), { numeric: true, placeholder: '100' });
      html += `<div class="efb mt-2"><button type="button" class="efb btn btn-sm btn-outline-pink" onclick="efb_dyn_preview_efb('${esc(r.id_)}')">${esc(T('dynPreviewN', 'Preview (first %s)').replace('%s', '50'))}</button><ul class="efb fs-7 mt-2 mb-0 ps-3" id="dynPreviewEl" aria-live="polite"></ul></div>`;
    }
    return html + '</div>';
  }

  /* ------------------------------------------------------------------ handlers */

  /** One F05 key from a control: the save rule, then the canvas (and the panel when it changes shape). */
  function efb_ch_change_efb(el) {
    const id = el.dataset.id;
    const key = el.dataset.key;
    const r = rowById(id);
    const R = rules();
    if (!r || !R || !key) return;
    let v;
    if (el.classList.contains('btn-toggle')) {
      const nowOn = !el.classList.contains('active');
      el.classList.toggle('active', nowOn);
      el.setAttribute('aria-pressed', nowOn ? 'true' : 'false');
      v = key === 'rank_touch' ? (nowOn ? '1' : '0') : (nowOn ? '1' : '');
    } else v = el.value;
    const clean = R.sanitizeKey(key, v);
    if (key === 'opt_value' && clean !== '') {
      const dup = optsOf(r.parent).some((o) => o.id_ !== r.id_ && o.opt_value === clean);
      if (dup) {
        el.value = r.opt_value || '';
        el.classList.add('border-danger');
        if (typeof alert_message_efb === 'function') alert_message_efb(T('optValDup', 'Two choices cannot have the same stored value.'), '', 6, 'warning');
        return;
      }
      el.classList.remove('border-danger');
    }
    if (clean === '' && key !== 'rank_touch') delete r[key]; else r[key] = clean;
    if (!el.classList.contains('btn-toggle') && el.value !== clean) el.value = clean;
    if (key === 'minSelect' || key === 'maxSelect') {
      const mn = parseInt(r.minSelect || '0', 10) || 0, mx = parseInt(r.maxSelect || '0', 10) || 0;
      if (mn > 0 && mx > 0 && mn > mx) { r.minSelect = String(mx); }
    }
    const field = r.type === 'option' ? rowById(r.parent) : r;
    if (['op_other', 'inv_on', 'dyn_src', 'op_select_all'].indexOf(key) !== -1) refreshPanel();
    if (field && ['opt_value', 'op_other_max', 'inv_release', 'inv_cap', 'dyn_limit', 'dyn_term', 'dyn_role', 'dyn_tax', 'dyn_post_type', 'dyn_orderby'].indexOf(key) === -1) redraw(field.id_);
    changed();
  }
  function changed() {
    try { if (typeof sessionStorage !== 'undefined') sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb)); } catch (e) {}
    try { document.dispatchEvent(new CustomEvent('efb:form-changed', { detail: { source: 'user', action: 'choices', ids: [] } })); } catch (e) {}
  }
  function refreshPanel() {
    try { if (root.efbBuilderApi && root.efbBuilderApi.refreshSettingsPanel) { root.efbBuilderApi.refreshSettingsPanel(); return; } } catch (e) {}
  }
  /** Draws one field of the canvas again (its own markup only). */
  function redraw(fid) {
    const r = rowById(fid);
    if (!r || typeof addNewElement !== 'function' || typeof document === 'undefined') return;
    const old = document.querySelector('#dropZoneEFB [data-id="' + (r.dataId || r.id_ + '-id') + '"]');
    if (!old) return;
    try {
      const html = addNewElement(r.type, r.id_, true, false);
      const box = document.createElement('div');
      box.innerHTML = html;
      const fresh = box.querySelector('[data-id="' + (r.dataId || r.id_ + '-id') + '"]') || box.firstElementChild;
      if (!fresh) return;
      const sel = old.classList.contains('efb-field-selected') || old.classList.contains('field-selected-efb');
      old.replaceWith(fresh);
      if (sel) fresh.classList.add(old.classList.contains('efb-field-selected') ? 'efb-field-selected' : 'field-selected-efb');
      if (typeof funSetPosElEfb === 'function') funSetPosElEfb(r.dataId, r.label_position);
      if (typeof fub_shwBtns_efb === 'function') fub_shwBtns_efb();
    } catch (e) { /* the next full draw shows it */ }
  }

  /* ------------------------------------------------------------------ the canvas */

  /** Parts of the canvas of a radio / checkbox field: Select all (before), Other (after), the shuffle badge. */
  function efbChoicesCanvasParts(r) {
    const out = { before: '', after: '', badge: '' };
    if (!r || !rules()) return out;
    const id = esc(r.id_);
    const radio = /radio/i.test(r.type);
    if (has(r.type, 'random') && on(r, 'op_random')) out.badge = `<span class="efb badge bg-light text-dark fs-8 mx-1 efb-ch-badge"><i class="efb bi-shuffle ${icon()}" aria-hidden="true"></i>${esc(T('opRandomBadge', 'Shuffled for each visitor'))}</span>`;
    if (has(r.type, 'select_all') && on(r, 'op_select_all')) {
      const max = parseInt(r.maxSelect || '0', 10) || 0;
      if (!(max > 0 && max < optsOf(r.id_).length)) out.before = `<div class="efb form-check efb-selall mt-1" id="${id}_all-v"><input class="efb form-check-input" type="checkbox" id="${id}_all" disabled><label class="efb form-check-label hStyleOpEfb" for="${id}_all">${esc(T('selectAll', 'Select all'))}</label></div>`;
    }
    if (has(r.type, 'other') && on(r, 'op_other')) {
      out.after = `<div class="efb form-check efb-other mt-1" id="${id}_oth-v" data-id="${id}_oth"><input class="efb form-check-input" type="${radio ? 'radio' : 'checkbox'}" id="${id}_oth" disabled><label class="efb form-check-label hStyleOpEfb" for="${id}_oth">${esc(r.op_other_label || T('otherOpt', 'Other'))}</label><input type="text" class="efb form-control efb-other-t border-d rounded-3 mt-1" disabled aria-label="${esc(r.op_other_label || T('otherOpt', 'Other'))}"></div>`;
    }
    return out;
  }

  /** The ranking on the canvas and in the preview (the same markup as the page; moves only in the preview). */
  function efbRankingEl(r, opts) {
    opts = opts || {};
    const id = esc(r.id_);
    const items = optsOf(r.id_);
    const D = C() ? C().digits : (s) => String(s);
    const lis = items.map((o, i) => `<li class="efb efb-rank-item" tabindex="0" data-op="${esc(o.id_)}" data-label="${esc(o.value || '')}" id="${esc(o.id_)}-v">
        <span class="efb efb-rank-grip bi-grip-vertical" aria-hidden="true"></span>
        <span class="efb efb-rank-n" aria-hidden="true">${esc(D(i + 1))}</span>
        <span class="efb efb-rank-label" dir="auto" id="${esc(o.id_)}_lab">${esc(o.value || '')}</span>
        <span class="efb efb-rank-btns"><button type="button" class="efb efb-rank-up" aria-label="${esc(T('rnkUp', 'Move %s up').replace('%s', o.value || ''))}"${opts.preview ? '' : ' disabled'}><i class="efb bi-arrow-up" aria-hidden="true"></i></button><button type="button" class="efb efb-rank-down" aria-label="${esc(T('rnkDown', 'Move %s down').replace('%s', o.value || ''))}"${opts.preview ? '' : ' disabled'}><i class="efb bi-arrow-down" aria-hidden="true"></i></button></span>
      </li>`).join('');
    const touch = r.rank_touch !== '0';
    return `<div class="efb efb-rank ${r.required == 1 || r.required === true ? 'required' : ''}" id="${id}_options" data-efb-rank="1" data-formid="${esc(opts.formId || 0)}" data-vid="${id}" data-top="${parseInt(r.rank_top || '0', 10) || 0}" data-touch="${touch ? '1' : '0'}" data-random="${on(r, 'op_random') && opts.preview ? '1' : '0'}" role="group" aria-labelledby="${id}_labG" aria-describedby="${id}_rnkhelp">
      <p class="efb efb-rank-help fs-7 text-muted" id="${id}_rnkhelp" dir="auto">${esc(T('rnkHelp', 'Drag an item, use its arrows, or press Alt and the up or down arrow.'))}</p>
      <ol class="efb efb-rank-list" role="list">${lis}</ol>
      ${touch ? `<button type="button" class="efb efb-rank-confirm fs-7" aria-pressed="false"${opts.preview ? '' : ' disabled'}>${esc(T('rnkConfirm', 'Keep this order'))}</button>` : ''}
      <div class="efb efb-rank-live" aria-live="polite" role="status"></div>
    </div>`;
  }

  /* ------------------------------------------------------------------ "Add in bulk" */

  const bulkState = { pid: '', tag: '' };
  function ajax(action, data) {
    const url = (root.efb_var && root.efb_var.ajax_url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
    const nonce = typeof _efb_nonce_ !== 'undefined' ? _efb_nonce_ : (root.efb_var && root.efb_var.nonce);
    const body = new URLSearchParams(Object.assign({ action, nonce }, data || {}));
    return fetch(url, { method: 'POST', credentials: 'same-origin', body }).then((r) => r.json());
  }
  function presetOptions() {
    const idx = (root.efb_var && root.efb_var.choices && root.efb_var.choices.presets) || {};
    const groups = { fa: 'فارسی', ar: 'العربية', en: 'English' };
    let html = `<option value="">${esc(T('presetNone', 'Type or paste your own'))}</option>`;
    Object.keys(groups).forEach((lang) => {
      const ids = Object.keys(idx).filter((k) => idx[k].lang === lang);
      if (!ids.length) return;
      html += `<optgroup label="${esc(groups[lang])}" lang="${lang}">` + ids.map((k) => `<option value="${esc(k)}">${esc(T(idx[k].name_key, idx[k].name))} (${idx[k].count})</option>`).join('') + '</optgroup>';
    });
    return html;
  }
  function efb_bulk_options_modal(pid, tag) {
    bulkState.pid = pid; bulkState.tag = tag;
    const body = `<div class="efb efb-bulk" id="efbBulkBox">
      <label for="efbBulkPreset" class="efb form-label fs-7 mb-1">${esc(T('presetLists', 'Ready-made lists'))}</label>
      <select id="efbBulkPreset" class="efb form-select efb border-d rounded-4 mb-1" onchange="efb_bulk_preset_efb(this.value)">${presetOptions()}</select>
      <small class="efb form-text text-muted d-block fs-8 mb-2">${esc(T('plCountryHint', 'For countries, use the Country field.'))}</small>
      <label for="efbBulkText" class="efb form-label fs-7 mb-1">${esc(T('bulkHelp', 'One choice per line. Use “Label|value” to store a different value.'))}</label>
      <textarea id="efbBulkText" class="efb form-control efb border-d rounded-4" rows="9" dir="auto" style="unicode-bidi:plaintext" spellcheck="false" oninput="efb_bulk_preview_efb()" aria-describedby="efbBulkPreview"></textarea>
      <div class="efb d-flex flex-wrap gap-3 my-2" role="radiogroup" aria-label="${esc(T('bulkAdd', 'Add in bulk'))}">
        <div class="efb form-check"><input class="efb form-check-input" type="radio" name="efbBulkMode" id="efbBulkModeAppend" value="append" checked onchange="efb_bulk_preview_efb()"><label class="efb form-check-label fs-7" for="efbBulkModeAppend">${esc(T('bulkAppend', 'Add to the end'))}</label></div>
        <div class="efb form-check"><input class="efb form-check-input" type="radio" name="efbBulkMode" id="efbBulkModeReplace" value="replace" onchange="efb_bulk_preview_efb()"><label class="efb form-check-label fs-7" for="efbBulkModeReplace">${esc(T('bulkReplace', 'Replace current choices'))}</label></div>
      </div>
      <div id="efbBulkPreview" class="efb fs-7 text-muted" aria-live="polite"></div>
      <button type="button" class="efb btn btn-primary btn-sm mt-2" id="efbBulkApply" onclick="efb_bulk_apply_efb()" disabled>${esc(T('bulkApply', 'Add choices'))}</button>
    </div>`;
    if (typeof show_modal_efb === 'function') {
      const painted = show_modal_efb(body, T('bulkAdd', 'Add in bulk'), 'bi-list-ul', 'saveBox', { flow: 'bulk-options', onShown: () => { const t = document.getElementById('efbBulkText'); if (t) t.focus(); } });
      if (painted && typeof state_modal_show_efb === 'function') state_modal_show_efb(1);
    }
  }
  function efb_bulk_preset_efb(id) {
    if (!id) return;
    ajax('efb_choice_presets_Emsfb', { preset: id }).then((res) => {
      const d = res && res.success && res.data;
      const t = document.getElementById('efbBulkText');
      if (!d || !t) return;
      t.value = d.lines;
      t.setAttribute('lang', d.lang);
      efb_bulk_preview_efb();
    }).catch(() => {});
  }
  function bulkParse() {
    const t = document.getElementById('efbBulkText');
    const mode = (document.querySelector('input[name="efbBulkMode"]:checked') || {}).value || 'append';
    const existing = mode === 'append' ? optsOf(bulkState.pid).map((o) => ({ label: o.value, value: o.opt_value || '' })) : [];
    return { mode, res: C().parseBulk(t ? t.value : '', { calc: !!(root.efb_var && root.efb_var.choices && root.efb_var.choices.calc), existing }) };
  }
  function efb_bulk_preview_efb() {
    const box = document.getElementById('efbBulkPreview');
    const btn = document.getElementById('efbBulkApply');
    if (!box || !C()) return;
    const { res } = bulkParse();
    const D = C().digits;
    const lines = [];
    lines.push(T('bulkCount', '%1$s choices, %2$s repeated labels').replace('%1$s', D(res.items.length)).replace('%2$s', D(res.dupLabels.length)));
    if (res.stripped.length) lines.push(T('bulkStripped', 'The characters " < and > were removed from lines %s.').replace('%s', res.stripped.map(D).join('، ')));
    if (res.tooMany) lines.push(T('bulkTooMany', 'Only the first 500 lines are added.'));
    if (res.skippedGroups) lines.push(T('bulkGroupsLater', 'Lines that start with “## ” (choice groups) are not used yet.'));
    if (res.dupValues.length) lines.push(T('bulkDupValue', 'These stored values are used twice: %s').replace('%s', res.dupValues.slice(0, 10).join(', ')));
    box.textContent = '';
    lines.forEach((l, i) => { const p = document.createElement('div'); p.textContent = l; p.setAttribute('dir', 'auto'); if (i > 0) p.className = 'efb text-warning'; box.appendChild(p); });
    if (btn) btn.disabled = res.items.length === 0 || res.dupValues.length > 0;
  }
  /** Adds the parsed choices (replace or append), in batches of one animation frame so a 500-line list does not freeze the builder. */
  function efb_bulk_apply_efb() {
    const pid = bulkState.pid;
    const tag = bulkState.tag;
    const p = rowById(pid);
    if (!p || !C()) return Promise.resolve(0);
    const { mode, res } = bulkParse();
    if (!res.items.length || res.dupValues.length) return Promise.resolve(0);
    return applyItems(pid, tag, res.items, mode).then((n) => {
      if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0);
      // The field's settings again (a click in the dialog closes the side panel), with the new choices.
      setTimeout(() => { try { if (root.efbBuilderApi) root.efbBuilderApi.openSettings(pid); } catch (e) {} }, 400);
      return n;
    });
  }
  function applyItems(pid, tag, items, mode) {
    if (mode === 'replace') {
      const keep = list().filter((r) => !(r && r.type === 'option' && r.parent === pid));
      list().length = 0;
      keep.forEach((r) => list().push(r));
    }
    const p = rowById(pid);
    const pay = typeof tag === 'string' && tag.indexOf('pay') !== -1 && tag.indexOf('img') === -1;
    const rid = () => Math.random().toString(36).substr(2, 9);
    let i = 0;
    return new Promise((done) => {
      const step = () => {
        const end = Math.min(items.length, i + 100);
        for (; i < end; i++) {
          const it = items[i];
          const id = rid();
          const row = { id_: id, dataId: id + '-id', parent: pid, type: 'option', value: it.label, id_op: id, step: p ? p.step : 1, amount: p ? p.amount : 0 };
          if (pay) row.price = 0;
          if (it.value !== undefined && it.value !== '') row.opt_value = it.value;
          if (it.calc !== undefined) row.calc = it.calc;
          if (tag === 'imgRadio') { row.sub_value = T('sampleDescription', ''); row.src = ''; }
          list().push(row);
        }
        if (i < items.length) { (root.requestAnimationFrame || setTimeout)(step); return; }
        if (typeof efbNormalizeOrderEfb === 'function') { try { efbNormalizeOrderEfb(valj_efb); } catch (e) {} }
        const ol = document.getElementById('optionListefb');
        if (ol && typeof efb_add_opt_setting === 'function') {
          const objOptions = optsOf(pid);
          const fake = { dataset: { tag } };
          const s = tag.indexOf('pay') !== -1;
          try { ol.innerHTML = objOptions.length ? efb_add_opt_setting(objOptions, fake, s, rid(), s ? 'payment' : '') : ''; } catch (e) {}
          for (const el of document.querySelectorAll('#optionListefb .elEdit')) el.addEventListener('change', () => { if (typeof change_el_edit_Efb === 'function') change_el_edit_Efb(el); });
        }
        redraw(pid);
        changed();
        done(items.length);
      };
      step();
    });
  }

  /* ------------------------------------------------------------------ dynamic choices */

  function efb_dyn_preview_efb(fid) {
    const r = rowById(fid);
    const box = document.getElementById('dynPreviewEl');
    if (!r || !box) return;
    const data = {};
    ['dyn_src', 'dyn_post_type', 'dyn_tax', 'dyn_term', 'dyn_role', 'dyn_orderby', 'dyn_order', 'dyn_limit', 'dyn_csv'].forEach((k) => { if (r[k] !== undefined) data[k] = r[k]; });
    box.textContent = '…';
    ajax('efb_dyn_preview_Emsfb', data).then((res) => {
      const d = res && res.success && res.data;
      box.textContent = '';
      const items = d && Array.isArray(d.items) ? d.items : [];
      if (!items.length) { const li = document.createElement('li'); li.textContent = T('dynEmpty', 'Nothing found for this source.'); box.appendChild(li); return; }
      items.forEach((it) => { const li = document.createElement('li'); li.setAttribute('dir', 'auto'); li.textContent = it.label + (it.value !== it.label ? ' — ' + it.value : ''); box.appendChild(li); });
    }).catch(() => { box.textContent = ''; });
  }
  /** CSV source: an attachment picked in the Media Library (text/csv), never a URL. */
  function efb_dyn_csv_pick_efb(fid) {
    const r = rowById(fid);
    if (!r || !root.wp || !root.wp.media) return;
    const frame = root.wp.media({ title: T('dynCsvPick', 'Choose a CSV file'), library: { type: 'text/csv' }, multiple: false });
    frame.on('select', () => {
      const a = frame.state().get('selection').first();
      if (!a) return;
      const j = a.toJSON();
      if (!/\.csv$/i.test(j.filename || j.url || '')) return;
      r.dyn_csv = rules().sanitizeKey('dyn_csv', j.id);
      const s = document.getElementById('dynCsvIdEl');
      if (s) s.textContent = '#' + r.dyn_csv + ' ' + (j.filename || '');
      changed();
    });
    frame.open();
  }

  const api = { efbChoicesPanelEls, efbChoicesOptionEls, efbBulkBtnEl, efbChoicesCanvasParts, efbRankingEl, efb_ch_change_efb, efb_ch_gem_efb, efb_bulk_options_modal, efb_bulk_preset_efb, efb_bulk_preview_efb, efb_bulk_apply_efb, efb_dyn_preview_efb, efb_dyn_csv_pick_efb, efb_choices_apply_items_efb: applyItems, efb_choices_redraw_efb: redraw };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
  if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
