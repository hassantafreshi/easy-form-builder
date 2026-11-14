/**
 * F10 in the builder (docs/feature-gaps/F10-layout-special-fields.fa.md §6).
 *
 *  - A: the review page (form settings: review_before_submit, review_hide_empty, review_title); not offered on a
 *    form with a Stripe / PayPal / PersiaPay field (the visitor pays inside the form, before Send).
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
    </div>`;
  }

  /* ------------------------------------------------------------------ change handler */

  /** change_el_edit_Efb for the F10 controls. Returns false when nothing was written. */
  function efbLayoutBuilderChange(el, indx) {
    if (!el) return false;
    const L = list();
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
    }
    return false;
  }

  const api = { efbLayoutFormEls, efbLayoutBuilderChange };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
})(typeof window !== 'undefined' ? window : globalThis);
