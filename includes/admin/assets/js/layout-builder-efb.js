/**
 * F10 in the builder (docs/feature-gaps/F10-layout-special-fields.fa.md §6).
 *
 *  - A: the review page (form settings: review_before_submit, review_hide_empty, review_title); not offered on a
 *    form with a Stripe / PayPal / PersiaPay field (the visitor pays inside the form, before Send).
 *  - B: the section (palette "Section"): title, description, collapsible, collapsed; "Keep only one section open at a
 *    time" (section_accordion) in the form settings. efbLayoutSectionMarkup() is the twin of the PHP renderer's
 *    generate_section_efb() (byte for byte, tests/test-section-render.php); on the canvas it is inert and marks
 *    where the section starts, in the preview it opens and closes like the page.
 *  - C: "Rich text editor" and its toolbar (basic / full) on a textarea (Free Plus: the switch is locked with the
 *    plan gem below Free Plus; the server draws a plain textarea then).
 *  - D: "Show this field to" (vis_mode all / in / out / roles, vis_roles from efb_var.roles) in every field's
 *    advanced settings (Pro: below Pro the control is locked, a saved rule can still be set back to everyone; the
 *    server applies a saved rule on every plan). The canvas and the preview show every field, a lock mark on the
 *    ones with a rule.
 *  - E: "How visitors sign" (esign_mode draw / type / both) and "Signature font" (auto / latin / arabic) on an
 *    e-signature field (the field's own plan); efbLayoutSigParts() is the twin of the PHP renderer's
 *    esign_typed_parts_efb() (tabs, canvas attributes, the name box).
 *
 * Every key goes through the save rule EFBLayoutCore.sanitizeKey (layout-efb.js, twin of \Emsfb\Layout::sanitize_key()).
 */
(function (root) {
  'use strict';

  const T = (k, fb) => {
    try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return root.efb_var.text[k]; } catch (e) { /* no phrases */ }
    return fb;
  };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const C = () => root.EFBLayoutCore || (typeof EFBLayoutCore !== 'undefined' ? EFBLayoutCore : null);
  const list = () => (typeof valj_efb !== 'undefined' ? valj_efb : []);
  const sanitize = (k, v) => (C() ? C().sanitizeKey(k, v) : String(v));
  const on = (v) => v === true || v === 1 || v === '1' || v === 'true';
  const changed = (action, ids) => {
    try { if (typeof sessionStorage !== 'undefined') sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb)); } catch (e) { /* storage off */ }
    try { document.dispatchEvent(new CustomEvent('efb:form-changed', { detail: { source: 'user', action: action || 'layout', ids: ids || [] } })); } catch (e) { /* old browser */ }
  };
  const legacyPay = () => (C() ? C().legacyPayment(list()) : false);

  /** A switch (checkbox) of the settings panel; key is the stored key, checked when on. */
  function check(id, key, row, label, disabled, help) {
    const v = row && row[key] !== undefined ? on(row[key]) : on(C() ? C().DEFAULTS[key] : '0');
    return `<div class="efb form-check form-switch mx-1 my-2">
      <input type="checkbox" role="switch" class="efb elEdit form-check-input" id="${id}" data-id="formSet" data-key="${key}" ${v ? 'checked' : ''}${disabled ? ' disabled aria-disabled="true"' : ''}${help ? ` aria-describedby="${id}-help"` : ''}>
      <label class="efb form-check-label" for="${id}">${esc(label)}</label>
      ${help ? `<small class="efb form-text text-muted d-block fs-8" id="${id}-help">${esc(help)}</small>` : ''}
    </div>`;
  }

  /* ------------------------------------------------------------------ A: review page (form settings) */

  /** The review settings in the form panel (show_setting_window_efb case 'formSet'). */
  function efbLayoutFormEls() {
    const r0 = list()[0] || {};
    const t = r0.type;
    if (t === 'login' || t === 'register') return '';
    const pay = legacyPay();
    const isOn = on(r0.review_before_submit) && !pay;
    return `<div class="efb efb-layout-form mt-2" id="efbLayoutForm">
      ${check('reviewBeforeSubmitEl', 'review_before_submit', r0, T('rvwBfrSbmt', 'Review answers before submitting'), pay, pay ? T('rvwNotPayment', 'Review is not available on forms that take a payment inside the form (Stripe, PayPal, PersiaPay).') : '')}
      <div class="efb efb-layout-review-more ${isOn ? '' : 'd-none'}" id="efbLayoutReviewMore">
        ${check('reviewHideEmptyEl', 'review_hide_empty', r0, T('rvwHdEmpty', 'Hide empty answers in the review'), false)}
        <label for="reviewTitleEl" class="efb form-label mt-2 mb-1">${esc(T('rvwTtlLbl', 'Review page title'))}</label>
        <input type="text" class="efb elEdit form-control text-muted h-d-efb border-d rounded-4 mb-1" id="reviewTitleEl" data-id="formSet" data-key="review_title" maxlength="${C() ? C().LIMITS.review_title : 120}" dir="auto" placeholder="${esc(T('rvwTtl', 'Review your answers'))}" value="${esc(r0.review_title || '')}">
      </div>
      ${efbLayoutAccordionEls()}
    </div>`;
  }

  /* ------------------------------------------------------------------ B: sections */

  const attr = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  /**
   * The section's markup: the twin of Formbuilder::generate_section_efb() (same string for the same row). inert
   * (the canvas): an extra class and a header that is not a control.
   */
  function efbLayoutSectionMarkup(r, formId, inert) {
    if (!r) return '';
    const id = String(r.id_ == null ? '' : r.id_);
    const collapsible = r.collapsible === undefined || r.collapsible === '' || on(r.collapsible);
    const collapsed = collapsible && on(r.collapsed);
    const name = r.name == null || typeof r.name === 'object' ? '' : String(r.name);
    const msg = r.message == null || typeof r.message === 'object' ? '' : String(r.message);
    const size = typeof r.el_text_size === 'string' && /^(fs-[1-7]|display-[1-6])$/.test(r.el_text_size) ? r.el_text_size : 'fs-5';
    const color = typeof r.el_text_color === 'string' && /^[a-zA-Z0-9_-]{1,40}$/.test(r.el_text_color) ? r.el_text_color : 'text-labelEfb';
    const align = ['txt-left', 'txt-right', 'txt-center'].indexOf(r.label_align) !== -1 ? r.label_align : '';
    const icon = typeof r.icon === 'string' && /^bi-[a-z0-9-]{1,40}$/.test(r.icon) ? `<i class="efb ${r.icon} efb-section-icon" aria-hidden="true"></i>` : '';
    const extra = typeof r.classes === 'string' ? r.classes.replace(/,/g, ' ').replace(/[^A-Za-z0-9_ -]/g, '').trim() : '';
    const title = `<span id="${attr(id)}_lab" class="efb efb-section-title">${esc(name)}</span>`;
    const head = collapsible
      ? `<button type="button" class="efb efb-section-btn ${color}" id="${attr(id)}_btn" aria-expanded="true" aria-controls="${attr(id)}-region" data-section="${attr(id)}"${inert ? ' tabindex="-1"' : ''}>${icon}${title}<i class="efb bi-chevron-down efb-section-chev" aria-hidden="true"></i></button>`
      : icon + title;
    const desc = msg !== '' ? `<div class="efb efb-section-desc"><small id="${attr(id)}-des" class="efb form-text text-muted">${esc(msg)}</small></div>` : '';
    return `<div class="efb px-0 mx-0 col-12 efb-section${extra !== '' ? ' ' + extra : ''}${inert ? ' efb-section-canvas' : ''}" id="${attr(id)}-f" data-formid="${attr(String(formId))}" data-section="${attr(id)}" data-collapsible="${collapsible ? '1' : '0'}" data-collapsed="${collapsed ? '1' : '0'}">`
      + `<h3 class="efb efb-section-head ${size} ${color}${align !== '' ? ' ' + align : ''}" id="${attr(id)}-h">${head}</h3>`
      + desc
      + `<div class="efb efb-section-region row mx-0 px-0" id="${attr(id)}-region" role="region" aria-labelledby="${attr(id)}-h"></div></div>`;
  }
  /** The canvas drawn again after a setting changed (the description line comes and goes). */
  function efbLayoutSectionRedraw(indx) {
    const r = list()[indx];
    if (!r) return;
    const old = document.getElementById(`${r.id_}-f`);
    if (!old) return;
    const t = document.createElement('div');
    t.innerHTML = efbLayoutSectionMarkup(r, typeof form_ID_emsFormBuilder !== 'undefined' ? form_ID_emsFormBuilder : 0, !old.closest('#dropZoneEFB') ? false : true);
    old.replaceWith(t.firstElementChild);
  }
  /** The section's own settings (show_setting_window_efb case 'section'). */
  function efbLayoutSectionPanelEls(indx, idset) {
    const r = list()[indx];
    if (!r) return '';
    const sw = (id, key, label, dflt, disabled) => {
      const v = r[key] === undefined || r[key] === '' ? dflt : on(r[key]);
      return `<div class="efb form-check form-switch mx-1 my-2">
        <input type="checkbox" role="switch" class="efb elEdit form-check-input" id="${id}" data-id="${esc(idset)}" data-key="${key}" ${v ? 'checked' : ''}${disabled ? ' disabled aria-disabled="true"' : ''}>
        <label class="efb form-check-label" for="${id}">${esc(label)}</label></div>`;
    };
    const coll = r.collapsible === undefined || r.collapsible === '' || on(r.collapsible);
    return `<div class="efb efb-layout-section mt-2" id="efbLayoutSection" data-id="${esc(r.id_)}">
      ${sw('sectionCollapsibleEl', 'collapsible', T('sectionCollapsible', 'Visitors can collapse this section'), true, false)}
      ${sw('sectionCollapsedEl', 'collapsed', T('sectionCollapsed', 'Collapsed when the form opens'), false, !coll)}
      <small class="efb form-text text-muted fs-8 d-block">${esc(T('sectionHelp', 'The section holds the fields after it in this step, up to the next section.'))}</small>
    </div>`;
  }
  /** The accordion switch in the form settings (only when the form has a section). */
  function efbLayoutAccordionEls() {
    const L = list();
    if (!L.some((r, i) => i > 0 && r && r.type === 'section')) return '';
    return check('sectionAccordionEl', 'section_accordion', L[0] || {}, T('sectionAccordion', 'Keep only one section open at a time'), false);
  }
  /** The builder's PC preview: sections open and close like the page. */
  function efbLayoutPreviewInit(root) {
    if (root && root.EFBLayout === undefined && typeof EFBLayout !== 'undefined') root = document;
    try { if (typeof EFBLayout !== 'undefined') EFBLayout.mountSections(root || document, { accordion: on((list()[0] || {}).section_accordion) }); } catch (e) { /* the preview without sections */ }
    // The preview's typed signatures (never the canvas).
    try { if (typeof EFBSign !== 'undefined') (root || document).querySelectorAll('canvas[data-sig-mode]').forEach((cv) => { if (!cv.closest('#dropZoneEFB')) EFBSign.mount(cv); }); } catch (e) { /* drawing only */ }
    // The preview's rich textareas get the editor (never the canvas: its textareas are not data-rte there until the preview).
    try { if (typeof EFBRich !== 'undefined') (root || document).querySelectorAll('textarea[data-rte="1"]').forEach((ta) => { if (!ta.closest('#dropZoneEFB')) EFBRich.mount(ta); }); } catch (e) { /* plain textareas */ }
  }

  /* ------------------------------------------------------------------ C: rich text */

  /** Whether a plan part is open on this site (the registry efb_var.capabilities, else efb_var.pro). */
  const planOk = (key, fallback) => (typeof efb_capability_available_efb === 'function' ? efb_capability_available_efb(key, fallback) : fallback);
  const gemFor = (key, need) => `<a type="button" role="button" tabindex="0" class="efb pro-version-efb" onclick="if (typeof efb_upgrade_prompt_efb === 'function') efb_upgrade_prompt_efb({ feature: '${key}', fallback: '${need}', origin: 'field_settings', opener: this });" aria-label="${esc(T(need === 'pro' ? 'proUnlockMsg' : 'thisFeatureAvailableFreePlusPro', 'Upgrade'))}"><i class="efb bi-gem text-light" aria-hidden="true"></i></a>`;
  /** The textarea's attributes for the editor (twin of the PHP renderer's data-rte / data-rte-tb). */
  function efbLayoutRichAttrs(r) {
    if (!r || r.type !== 'textarea' || !on(r.rich_text) || !planOk('setting:rich_text', !!(root.efb_var && (efb_var.pro == 1 || efb_var.pro === true)))) return '';
    return `data-rte="1" data-rte-tb="${r.rich_toolbar === 'full' ? 'full' : 'basic'}"`;
  }
  /** The rich text switch and the toolbar choice (show_setting_window_efb, textarea). */
  function efbLayoutRichEls(indx, idset) {
    const r = list()[indx];
    if (!r || r.type !== 'textarea') return '';
    const ok = planOk('setting:rich_text', !!(root.efb_var && (efb_var.pro == 1 || efb_var.pro === true)));
    const isOn = on(r.rich_text);
    const tb = r.rich_toolbar === 'full' ? 'full' : 'basic';
    return `<div class="efb efb-layout-rich mt-2" id="efbLayoutRich">
      <div class="efb form-check form-switch mx-1 my-2 d-flex align-items-center gap-2">
        <input type="checkbox" role="switch" class="efb elEdit form-check-input" id="richTextEl" data-id="${esc(idset)}" data-key="rich_text" ${isOn ? 'checked' : ''}${ok ? '' : ' disabled aria-disabled="true"'}>
        <label class="efb form-check-label" for="richTextEl">${esc(T('richText', 'Rich text editor'))}</label>${ok ? '' : gemFor('setting:rich_text', 'free_plus')}
      </div>
      <div class="efb ${isOn && ok ? '' : 'd-none'}" id="efbLayoutRichTb">
        <label for="richToolbarEl" class="efb form-label mt-2 mb-1">${esc(T('richToolbar', 'Toolbar'))}</label>
        <select class="efb elEdit form-select border-d rounded-4 mb-1" id="richToolbarEl" data-id="${esc(idset)}" data-key="rich_toolbar">
          <option value="basic" ${tb === 'basic' ? 'selected' : ''}>${esc(T('richBasic', 'Basic'))}</option>
          <option value="full" ${tb === 'full' ? 'selected' : ''}>${esc(T('richFull', 'Full'))}</option>
        </select>
        <small class="efb form-text text-muted fs-8 d-block">${esc(T('richHelp', 'Bold, italic, underline and lists; Full adds links, quotes and headings. The site keeps only these formats.'))}</small>
      </div>
    </div>`;
  }
  /** The canvas: the textarea gets the same attributes and a small "Rich text" badge. */
  function richCanvas(r) {
    const ta = document.getElementById(`${r.id_}_`);
    if (!ta) return;
    const attrs = efbLayoutRichAttrs(r);
    if (attrs) { ta.setAttribute('data-rte', '1'); ta.setAttribute('data-rte-tb', r.rich_toolbar === 'full' ? 'full' : 'basic'); } else { ta.removeAttribute('data-rte'); ta.removeAttribute('data-rte-tb'); }
    let badge = document.getElementById(`${r.id_}_rtebadge`);
    if (attrs && !badge) {
      badge = document.createElement('span');
      badge.id = `${r.id_}_rtebadge`;
      badge.className = 'efb badge bg-light text-dark border fs-8 efb-rte-badge';
      badge.innerHTML = `<i class="efb bi-type-bold" aria-hidden="true"></i> `;
      badge.appendChild(document.createTextNode(T('richText', 'Rich text editor')));
      ta.parentNode.insertBefore(badge, ta);
    } else if (!attrs && badge) badge.remove();
  }

  /* ------------------------------------------------------------------ D: who sees a field */

  const NO_VIS_TYPES = ['form', 'step', 'option', 'r_matrix', 'buttonNav', 'steps'];
  const VIS_FIXED_IDS = ['emaillogin', 'passwordlogin', 'usernameRegisterEFB', 'passwordRegisterEFB', 'repasswordRegisterEFB', 'emailRegisterEFB'];
  const siteRoles = () => { try { return Array.isArray(root.efb_var && root.efb_var.roles) ? root.efb_var.roles : []; } catch (e) { return []; } };
  const visApplies = (r) => !!r && NO_VIS_TYPES.indexOf(r.type) === -1 && VIS_FIXED_IDS.indexOf(String(r.id_)) === -1
    && ['login', 'register'].indexOf((list()[0] || {}).type) === -1;
  const visMode = (r) => (C() ? C().VIS_MODES : ['all', 'in', 'out', 'roles']).indexOf(r && r.vis_mode) !== -1 ? r.vis_mode : 'all';
  /** "Signed-in visitors", "Editor, Author" … the words of a rule (the canvas mark, the preview). */
  function visText(r) {
    const m = visMode(r);
    if (m === 'in') return T('visIn', 'Signed-in visitors');
    if (m === 'out') return T('visOut', 'Guests (not signed in)');
    if (m !== 'roles') return '';
    const names = String(r.vis_roles || '').split(',').filter(Boolean).map((slug) => { const f = siteRoles().find((x) => x.slug === slug); return f ? f.name : slug; });
    return names.length ? names.join(', ') : T('visNoRole', 'No role chosen: nobody sees this field');
  }
  /** The canvas / preview mark of a field with a rule ('' for everyone). */
  function efbLayoutVisMark(r) {
    if (!visApplies(r) || visMode(r) === 'all') return `<span id="${attr(r && r.id_)}-vis" class="efb d-none"></span>`;
    const t = `${T('visBadge', 'Only for')}: ${visText(r)}`;
    return `<span id="${attr(r.id_)}-vis" class="efb efb-vis-mark badge bg-light text-dark border fs-8 mx-2" title="${attr(t)}"><i class="efb bi-person-lock" aria-hidden="true"></i> ${esc(t)}</span>`;
  }
  function visMarkRedraw(r) {
    const old = document.getElementById(`${r.id_}-vis`);
    if (!old) return;
    const t = document.createElement('div');
    t.innerHTML = efbLayoutVisMark(r);
    old.replaceWith(t.firstElementChild);
  }
  /** "Show this field to" (show_setting_window_efb, Nadvanced and the section / heading panels). */
  function efbLayoutVisEls(indx, idset) {
    const r = list()[indx];
    if (!visApplies(r)) return '';
    const ok = planOk('setting:vis_mode', !!(root.efb_var && (efb_var.pro == 1 || efb_var.pro === true)));
    const m = visMode(r);
    const opt = (v, label) => `<option value="${v}" ${m === v ? 'selected' : ''}${!ok && v !== 'all' && v !== m ? ' disabled' : ''}>${esc(label)}</option>`;
    const chosen = String(r.vis_roles || '').split(',').filter(Boolean);
    const roles = siteRoles().map((x, i) => `<div class="efb form-check mx-1">
        <input type="checkbox" class="efb elEdit form-check-input" id="visRoleEl_${i}" data-id="${esc(idset)}" data-key="vis_roles" data-role="${attr(x.slug)}" ${chosen.indexOf(x.slug) !== -1 ? 'checked' : ''}${ok ? '' : ' disabled'}>
        <label class="efb form-check-label" for="visRoleEl_${i}">${esc(x.name)}</label></div>`).join('');
    return `<div class="efb efb-layout-vis mt-3" id="efbLayoutVis">
      <label for="visModeEl" class="efb form-label mb-1 d-flex align-items-center gap-2"><i class="efb bi-person-lock fs-7" aria-hidden="true"></i>${esc(T('visFor', 'Show this field to'))}${ok ? '' : gemFor('setting:vis_mode', 'pro')}</label>
      <select class="efb elEdit form-select border-d rounded-4 mb-1" id="visModeEl" data-id="${esc(idset)}" data-key="vis_mode" aria-describedby="visModeEl-help"${!ok && m === 'all' ? ' disabled aria-disabled="true"' : ''}>
        ${opt('all', T('visAll', 'Everyone'))}${opt('in', T('visIn', 'Signed-in visitors'))}${opt('out', T('visOut', 'Guests (not signed in)'))}${opt('roles', T('visRoles', 'Chosen user roles'))}
      </select>
      <fieldset class="efb ${m === 'roles' ? '' : 'd-none'} border rounded-3 px-2 py-1 mb-1" id="efbLayoutVisRoles"><legend class="efb fs-7 float-none w-auto px-1 mb-0">${esc(T('visRoles', 'Chosen user roles'))}</legend>${roles}</fieldset>
      <small class="efb form-text text-muted fs-8 d-block" id="visModeEl-help">${esc(T('visHelp', 'The site leaves this field out of the page and out of the sent answers for anyone else, and it is never required for them. Administrators follow the rule too.'))}</small>
    </div>`;
  }

  /* E: the typed signature's settings and canvas are the Typed Signature add-on's (AdnTSG, SPLIT §7). */

  /* ------------------------------------------------------------------ change handler */

  /** change_el_edit_Efb for the F10 controls. Returns false when nothing was written. */
  function efbLayoutBuilderChange(el, indx) {
    if (!el) return false;
    const L = list();
    if (/^visRoleEl_\d+$/.test(el.id)) {
      const r = L[indx];
      if (!visApplies(r) || !planOk('setting:vis_mode', false)) return false;
      const box = document.getElementById('efbLayoutVisRoles');
      const picked = box ? Array.from(box.querySelectorAll('input[data-role]')).filter((x) => x.checked).map((x) => x.dataset.role) : [];
      r.vis_roles = sanitize('vis_roles', picked.join(','));
      visMarkRedraw(r);
      changed('setting', [r.id_]);
      return true;
    }
    switch (el.id) {
      case 'reviewBeforeSubmitEl': {
        if (legacyPay()) { el.checked = false; return false; }
        L[0].review_before_submit = sanitize('review_before_submit', el.checked ? '1' : '0');
        // The defaults are written with the switch, so the stored form says what the page does.
        if (el.checked && L[0].review_hide_empty === undefined) L[0].review_hide_empty = '1';
        if (el.checked && L[0].review_title === undefined) L[0].review_title = '';
        const more = document.getElementById('efbLayoutReviewMore');
        if (more) more.classList.toggle('d-none', !el.checked);
        changed('setting', []);
        return true;
      }
      case 'reviewHideEmptyEl':
        L[0].review_hide_empty = sanitize('review_hide_empty', el.checked ? '1' : '0');
        changed('setting', []);
        return true;
      case 'reviewTitleEl':
        L[0].review_title = sanitize('review_title', el.value);
        if (el.value !== L[0].review_title) el.value = L[0].review_title;
        changed('setting', []);
        return true;
      case 'richTextEl': {
        const r = L[indx];
        if (!r || r.type !== 'textarea') return false;
        if (!planOk('setting:rich_text', false)) { el.checked = false; if (typeof efb_upgrade_prompt_efb === 'function') efb_upgrade_prompt_efb({ feature: 'setting:rich_text', fallback: 'free_plus', origin: 'field_settings', opener: el }); return false; }
        r.rich_text = sanitize('rich_text', el.checked ? '1' : '0');
        if (el.checked && r.rich_toolbar === undefined) r.rich_toolbar = 'basic';
        const tb = document.getElementById('efbLayoutRichTb');
        if (tb) tb.classList.toggle('d-none', !el.checked);
        richCanvas(r);
        changed('setting', [r.id_]);
        return true;
      }
      case 'richToolbarEl': {
        const r = L[indx];
        if (!r || r.type !== 'textarea') return false;
        r.rich_toolbar = sanitize('rich_toolbar', el.value);
        richCanvas(r);
        changed('setting', [r.id_]);
        return true;
      }
      case 'visModeEl': {
        const r = L[indx];
        if (!visApplies(r)) return false;
        if (!planOk('setting:vis_mode', false) && el.value !== 'all') { el.value = visMode(r); if (typeof efb_upgrade_prompt_efb === 'function') efb_upgrade_prompt_efb({ feature: 'setting:vis_mode', fallback: 'pro', origin: 'field_settings', opener: el }); return false; }
        r.vis_mode = sanitize('vis_mode', el.value);
        if (r.vis_mode === 'roles' && r.vis_roles === undefined) r.vis_roles = '';
        if (r.vis_mode !== 'roles') delete r.vis_roles;
        const box = document.getElementById('efbLayoutVisRoles');
        if (box) box.classList.toggle('d-none', r.vis_mode !== 'roles');
        if (!planOk('setting:vis_mode', false) && r.vis_mode === 'all') el.disabled = true;
        visMarkRedraw(r);
        changed('setting', [r.id_]);
        return true;
      }
      case 'sigModeEl':
      case 'sigFontEl':
        // SPLIT §7: the Typed Signature add-on's settings (typed-signature-builder-efb.js).
        return typeof root.efbSigBuilderChange === 'function' ? root.efbSigBuilderChange(el, indx) : false;
      case 'sectionAccordionEl':
        L[0].section_accordion = sanitize('section_accordion', el.checked ? '1' : '0');
        changed('setting', []);
        return true;
      case 'sectionCollapsibleEl':
      case 'sectionCollapsedEl': {
        const r = L[indx];
        if (!r || r.type !== 'section') return false;
        if (el.id === 'sectionCollapsibleEl') {
          r.collapsible = el.checked ? '1' : '0';
          if (!el.checked) r.collapsed = '0';
          const c = document.getElementById('sectionCollapsedEl');
          if (c) { c.disabled = !el.checked; if (!el.checked) c.checked = false; }
        } else {
          r.collapsed = sanitize('collapsed', el.checked ? '1' : '0');
        }
        efbLayoutSectionRedraw(indx);
        changed('setting', [r.id_]);
        return true;
      }
    }
    return false;
  }

  const api = { efbLayoutFormEls, efbLayoutBuilderChange, efbLayoutSectionMarkup, efbLayoutSectionRedraw, efbLayoutSectionPanelEls, efbLayoutAccordionEls, efbLayoutPreviewInit,
    efbLayoutRichAttrs, efbLayoutRichEls, efbLayoutVisEls, efbLayoutVisMark };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
})(typeof window !== 'undefined' ? window : globalThis);
