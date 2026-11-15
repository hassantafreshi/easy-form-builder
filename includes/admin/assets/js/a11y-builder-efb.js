/**
 * F16 in the builder: the form settings "Check fields" (val_timing), "Show a list of problems at the top of the form"
 * (err_summary) and "Warn visitors who leave a half-filled form" (warn_unsaved), and a field's "Browser autofill"
 * (autocomplete). Spec: docs/feature-gaps/F16-a11y-inline-validation.fa.md (§5, §6).
 *
 * The controls write valj_efb directly with the save rules (efbA11yClean = Emsfb\A11y::sanitize_key), like the F07 / F03
 * controls, without change_el_edit_Efb (which runs on a timer). efbBuilderApi.setFormSettings / setAutocomplete use the
 * same rules. efbA11yAcFor is the twin of A11y::autocomplete_for() (the token the page gets; tests/test-a11y-render.php).
 */
(function (root) {
  'use strict';

  const T = (k, fb) => { try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return root.efb_var.text[k]; } catch (e) { /* no text */ } return fb; };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const CFG = () => (root.efb_var && root.efb_var.a11y) || {};
  const TOKENS = ['name', 'honorific-prefix', 'given-name', 'additional-name', 'family-name', 'honorific-suffix', 'nickname', 'username', 'new-password', 'current-password', 'one-time-code', 'organization-title', 'organization', 'street-address', 'address-line1', 'address-line2', 'address-line3', 'address-level4', 'address-level3', 'address-level2', 'address-level1', 'country', 'country-name', 'postal-code', 'cc-name', 'cc-given-name', 'cc-additional-name', 'cc-family-name', 'cc-number', 'cc-exp', 'cc-exp-month', 'cc-exp-year', 'cc-csc', 'cc-type', 'transaction-currency', 'transaction-amount', 'language', 'bday', 'bday-day', 'bday-month', 'bday-year', 'sex', 'url', 'photo', 'tel', 'tel-country-code', 'tel-national', 'tel-area-code', 'tel-local', 'tel-extension', 'email', 'impp'];
  const AUTO = { firstName: 'given-name', lastName: 'family-name', email: 'email', password: 'current-password', tel: 'tel', mobile: 'tel', url: 'url', number: 'off', prcfld: 'transaction-amount', address_line: 'street-address', postalcode: 'postal-code', conturyList: 'country-name', stateProvince: 'address-level1', cityList: 'address-level2', date: 'off', pdate: 'off', ardate: 'off', color: 'off', 'datetime-local': 'off' };
  /** Types with a Browser autofill control (§5.1: the rows that are not "—"). */
  const AC_TYPES = ['text', 'firstName', 'lastName', 'email', 'password', 'tel', 'url', 'number', 'textarea', 'address_line', 'postalcode', 'conturyList', 'stateProvince', 'cityList', 'date'];

  /** An autocomplete value the browser understands, else '' (automatic). */
  function token(v) {
    v = String(v == null ? '' : v).trim().toLowerCase().replace(/\s+/g, ' ');
    if (v === 'off' || v === 'on') return v;
    const m = v.match(/^(section-[a-z0-9-]{1,30} )?((shipping|billing) )?([a-z0-9-]{2,40})$/);
    return m && TOKENS.indexOf(m[4]) !== -1 ? v : '';
  }
  /** The save rule of an F16 key ('' = not kept). */
  function efbA11yClean(k, v) {
    if (v === true) v = '1'; else if (v === false) v = '0';
    if (v === null || typeof v === 'object') return '';
    v = String(v).trim().toLowerCase();
    if (k === 'val_timing') return v === 'blur' || v === 'change' ? v : '';
    if (k === 'err_summary' || k === 'warn_unsaved') return ['1', 'true', 'on'].indexOf(v) !== -1 ? '1' : '0';
    if (k === 'autocomplete') return token(v);
    return '';
  }
  /** The token a field gets on the page ('' = no attribute). */
  function efbA11yAcFor(elementId, row, row0) {
    row = row || {};
    row0 = row0 || (typeof valj_efb !== 'undefined' && valj_efb[0]) || {};
    const own = token(row.autocomplete);
    if (own) return own;
    if (elementId === 'password' && (row0.type === 'register' || row.pw_confirm == 1)) return 'new-password';
    if (AUTO[elementId]) return AUTO[elementId];
    return elementId === 'text' && row0.val_timing === undefined ? 'off' : '';
  }
  /** The keys of a new form (Emsfb\A11y::defaults(); a form of several steps also warns before leaving). */
  function efbA11yDefaults(steps) {
    const d = Object.assign({ val_timing: 'blur', err_summary: '1', warn_unsaved: '0' }, CFG().defaults || {});
    d.warn_unsaved = Number(steps) > 1 ? '1' : '0';
    return d;
  }

  function toggle(id, key, label, help) {
    const on = typeof valj_efb !== 'undefined' && valj_efb[0] && (valj_efb[0][key] === '1' || valj_efb[0][key] === 1 || valj_efb[0][key] === true);
    return `<div class="efb mx-1 my-3 efb">
      <button type="button" id="${id}" data-id="formSet" data-key="${key}" class="efb mx-0 btn h-s-efb btn-toggle efb-a11y-ctl ${on ? 'active' : ''}" aria-pressed="${on ? 'true' : 'false'}" ${help ? `aria-describedby="${id}Help"` : ''} autocomplete="off" onclick="efb_a11y_change_efb(this)"><div class="efb handle"></div></button>
      <label class="efb form-check-label pt-1" for="${id}">${esc(label)}</label>
      ${help ? `<small class="efb form-text text-muted d-block fs-8 mx-2" id="${id}Help">${esc(help)}</small>` : ''}
    </div>`;
  }
  /** Form settings (Advanced): the three F16 keys. A form without val_timing shows "When the value changes". */
  function efbA11yFormEls() {
    if (typeof valj_efb === 'undefined' || !valj_efb[0]) return '';
    const cur = valj_efb[0].val_timing === 'blur' ? 'blur' : 'change';
    return `<div class="efb mt-2 mb-1 efb-a11y-form">
      <label for="valTimingEl" class="efb mb-1 fs-7">${esc(T('a11yValTiming', 'Check fields'))}</label>
      <select class="efb form-select efb border-d rounded-4 efb-a11y-ctl" id="valTimingEl" data-id="formSet" data-key="val_timing" onchange="efb_a11y_change_efb(this)">
        <option value="blur" ${cur === 'blur' ? 'selected' : ''}>${esc(T('a11yValBlur', 'When the visitor leaves a field (recommended)'))}</option>
        <option value="change" ${cur === 'change' ? 'selected' : ''}>${esc(T('a11yValChange', 'When the value changes (previous behaviour)'))}</option>
      </select>
    </div>
    ${toggle('errSummaryEl', 'err_summary', T('a11yErrSummary', 'Show a list of problems at the top of the form'))}
    ${toggle('warnUnsavedEl', 'warn_unsaved', T('a11yWarnUnsaved', 'Warn visitors who leave a half-filled form'))}`;
  }
  /** A field's "Browser autofill" select (Automatic, Off, the tokens by group); '' for a type without one. */
  function efbA11yAutocompleteEls(indx, idset) {
    if (typeof valj_efb === 'undefined' || !valj_efb[indx]) return '';
    const r = valj_efb[indx];
    const type = r.elementId || r.type;
    if (AC_TYPES.indexOf(type) === -1) return '';
    const cur = token(r.autocomplete);
    const labels = CFG().labels || {};
    const groups = Object.keys(labels).map((g) => `<optgroup label="${esc(T(g, g))}">${Object.keys(labels[g]).map((t) => `<option value="${esc(t)}" ${cur === t ? 'selected' : ''}>${esc(labels[g][t])} (${esc(t)})</option>`).join('')}</optgroup>`).join('');
    const known = cur === '' || cur === 'off' || Object.keys(labels).some((g) => labels[g][cur] !== undefined);
    const auto = efbA11yAcFor(type, Object.assign({}, r, { autocomplete: '' }));
    return `<div class="efb mt-2 mb-1" id="autocompleteElBox">
      <label for="autocompleteEl" class="efb mb-1 fs-7">${esc(T('a11yAutocomplete', 'Browser autofill'))}</label>
      <select class="efb form-select efb border-d rounded-4 efb-a11y-ctl" id="autocompleteEl" data-id="${esc(idset)}" data-key="autocomplete" onchange="efb_a11y_change_efb(this)">
        <option value="" ${cur === '' ? 'selected' : ''}>${esc(T('a11yAcAuto', 'Automatic'))}${auto ? ` (${esc(auto)})` : ''}</option>
        <option value="off" ${cur === 'off' ? 'selected' : ''}>${esc(T('a11yAcOff', 'Off'))}</option>
        ${known ? '' : `<option value="${esc(cur)}" selected>${esc(cur)}</option>`}${groups}
      </select>
    </div>`;
  }
  /** A control changed: the row, the canvas attribute, the API's "edited by a person" mark. */
  function efb_a11y_change_efb(el) {
    if (!el || typeof valj_efb === 'undefined') return;
    const key = el.getAttribute('data-key');
    const id = el.getAttribute('data-id');
    const r = id === 'formSet' ? valj_efb[0] : valj_efb.find((x, i) => i > 0 && x && x.dataId == id);
    if (!r || !key) return;
    let v;
    if (el.classList.contains('btn-toggle')) {
      const on = !el.classList.contains('active');
      el.classList.toggle('active', on);
      el.setAttribute('aria-pressed', on ? 'true' : 'false');
      v = on ? '1' : '0';
    } else v = efbA11yClean(key, el.value);
    if (v === '') delete r[key]; else r[key] = v;
    if (key === 'autocomplete') document.querySelectorAll(`[id="${r.id_}_"], [id="${r.id_}_options"]`).forEach((n) => { if (/^(INPUT|SELECT|TEXTAREA)$/.test(n.tagName)) { const t = efbA11yAcFor(r.elementId || r.type, r); if (t) n.setAttribute('autocomplete', t); else n.removeAttribute('autocomplete'); } });
    if (typeof efbApiNoteUserEditEfb === 'function') { try { efbApiNoteUserEditEfb(); } catch (e) { /* no API */ } }
  }

  const api = { efbA11yClean, efbA11yAcFor, efbA11yDefaults, efbA11yFormEls, efbA11yAutocompleteEls, efb_a11y_change_efb, EFB_A11Y_TOKENS: TOKENS };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
  if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
