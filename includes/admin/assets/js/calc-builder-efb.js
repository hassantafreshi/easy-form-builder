/**
 * F04 in the builder: numbers and calculations (spec docs/feature-gaps/F04-number-calculation.fa.md §7).
 *
 *  - number / slider / calc settings: step (num_step, never "step": that key is the form step), decimal
 *    places, the display format (thousands separator, decimal separator, digits shown), prefix / suffix
 *    (calc and slider; a number field uses the F03 Prefix / Suffix), "Show as form currency";
 *  - the calc field: the formula (one line, left to right) with "Insert field" and the function buttons,
 *    its error under the box (EFBCalc.parse, the twin of the server's parser), show / hide the result,
 *    empty answers as 0 or an empty result, the cycle and "visitors can read the formula" notes;
 *  - the option's calculation value (the "Show attributes" row of a choice field);
 *  - the slider type (one handle / a range with two handles).
 * Every key is written with the save rule EFBCalc.sanitizeKey (twin of \Emsfb\Calc::sanitize_key()); the
 * server checks every key again on save (formula_err) and computes every result again on submit.
 */
(function (root) {
  'use strict';

  const T = (k, fb) => {
    try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return root.efb_var.text[k]; } catch (e) {}
    return fb;
  };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const E = () => root.EFBCalc || (typeof EFBCalc !== 'undefined' ? EFBCalc : null);
  const list = () => (typeof valj_efb !== 'undefined' ? valj_efb : []);
  const rowById = (id) => list().find((r) => r && r.id_ === id) || null;
  const locale = () => (root.efb_var && (root.efb_var.wp_lan || root.efb_var.language)) || 'en_US';
  const currency = () => {
    const r0 = list()[0] || {};
    const code = String(r0.currency || 'USD').toUpperCase();
    const map = (root.efb_var && root.efb_var.calc && root.efb_var.calc.currencies) || {};
    return map[code] || { s: code, d: 2 };
  };
  const cfgOf = (r) => (E() ? E().config(r, { locale: locale(), currency: currency() }) : {});
  const changed = () => {
    try { if (typeof sessionStorage !== 'undefined') sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb)); } catch (e) {}
    try { document.dispatchEvent(new CustomEvent('efb:form-changed', { detail: { source: 'user', action: 'calc', ids: [] } })); } catch (e) {}
  };

  /* ------------------------------------------------------------------ number settings */

  function sel(id, key, r, opts, label, help) {
    const cur = r[key] === undefined || r[key] === null ? '' : String(r[key]);
    return `<label for="${id}" class="efb form-label mt-2 mb-1">${esc(label)}</label>
      <select data-id="${esc(r.dataId || r.id_ + '-id')}" data-key="${key}" class="efb elEdit form-select efb-calc-ctl border-d rounded-4 mb-1" id="${id}"${help ? ` aria-describedby="${id}Help"` : ''}>
      ${opts.map((o) => `<option value="${esc(o[0])}" ${cur === o[0] ? 'selected' : ''}>${esc(o[1])}</option>`).join('')}</select>
      ${help ? `<small class="efb form-text text-muted fs-8 d-block" id="${id}Help">${esc(help)}</small>` : ''}`;
  }
  function inp(id, key, r, label, ph, help, ltr) {
    const v = r[key] === undefined || r[key] === null ? '' : String(r[key]);
    return `<label for="${id}" class="efb form-label mt-2 mb-1">${esc(label)}</label>
      <input type="text" data-id="${esc(r.dataId || r.id_ + '-id')}" data-key="${key}" class="efb elEdit form-control efb-calc-ctl text-muted border-d rounded-4 h-d-efb mb-1" id="${id}" ${ltr ? 'dir="ltr" inputmode="decimal"' : 'dir="auto"'} placeholder="${esc(ph || '')}" value="${esc(v)}"${help ? ` aria-describedby="${id}Help"` : ''}>
      ${help ? `<small class="efb form-text text-muted fs-8 d-block" id="${id}Help">${esc(help)}</small>` : ''}`;
  }

  /** Step and decimal places (number, slider and calc fields). */
  function efbNumPanelEls(indx, idset, tag) {
    const r = list()[indx];
    if (!r || !E()) return '<!-- F04: calc-efb.js not loaded -->';
    const dec = [['', T('numAnyDec', 'Any')]].concat(['0', '1', '2', '3', '4', '5', '6'].map((d) => [d, d]));
    let html = `<div class="efb mt-2 efb-calc-panel" id="efbNumPanel" data-id="${esc(r.id_)}">`;
    if (tag !== 'calc') html += inp('numStepEl', 'num_step', r, T('numStep', 'Step'), tag === 'range' ? '1' : '0.5', T('numStepHelp', 'Empty = any value.'), true);
    html += sel('numDecimalsEl', 'num_decimals', r, tag === 'calc' ? dec.slice(1) : dec, T('numDecimals', 'Decimal places'), '');
    return html + '</div>';
  }

  /* ------------------------------------------------------------------ change handler */

  /** A settings control of F04 changed (change_el_edit_Efb hands it here). Returns true when handled. */
  function efbCalcBuilderChange(el, indx) {
    const r = list()[indx];
    const key = el && el.dataset ? el.dataset.key : '';
    if (!r || !key || !E()) return false;
    let v = el.classList.contains('btn-toggle') ? (el.classList.contains('active') ? '1' : '') : el.value;
    const clean = E().sanitizeKey(key, v, key === 'calc' ? 'option' : r.type);
    if (key === 'num_step' && String(v).trim() !== '' && clean === '') {
      el.classList.add('border-danger');
      if (typeof alert_message_efb === 'function') alert_message_efb('', T('numStepBad', 'The step must be a positive number, such as 0.5 or 5.'), 6, 'warning');
      el.value = r.num_step || '';
      return true;
    }
    el.classList.remove('border-danger');
    if (clean === '' && key !== 'formula') delete r[key]; else r[key] = clean;
    if (el.tagName !== 'SELECT' && !el.classList.contains('btn-toggle') && el.value !== clean && key !== 'formula') el.value = clean;
    redraw(r);
    changed();
    return true;
  }

  /** The canvas copy of a field again (attributes of a number box, the calc output, the slider). */
  function redraw(r) {
    try {
      const input = document.getElementById(`${r.id_}_`);
      if (r.type === 'number' && input) {
        const a = efbNumInputAttrs(r);
        if (a) {
          input.setAttribute('data-num', '1');
          input.setAttribute('data-numcfg', a.json);
          input.setAttribute('inputmode', 'decimal');
          input.setAttribute('dir', 'ltr');
          if (input.type === 'number') { input.removeAttribute('minlength'); input.removeAttribute('maxlength'); try { input.type = 'text'; } catch (e) {} }
        }
      }
    } catch (e) { /* the canvas is redrawn on the next open */ }
  }

  /* ------------------------------------------------------------------ canvas / preview (twin of the PHP renderer) */

  const NUM_KEYS = ['num_step', 'num_decimals', 'num_format', 'num_dec_sep', 'num_grp_sep', 'num_digits', 'num_prefix', 'num_suffix', 'num_currency', 'num_max_zero'];
  /** Whether a field uses an F04 key (twin of Calc::is_f4()). */
  function isF4(r) { return !!r && ['number', 'range', 'calc'].indexOf(r.type) !== -1 && NUM_KEYS.some((k) => r[k] !== undefined && r[k] !== null && typeof r[k] !== 'object' && String(r[k]) !== ''); }

  /** A number field with F04 keys: {json, attrs} for the text box (Calc::input_attrs()), or null. */
  function efbNumInputAttrs(r) {
    if (!r || !E() || r.type !== 'number' || !isF4(r)) return null;
    const c = cfgOf(r);
    const json = JSON.stringify({ decimals: c.decimals, step: c.step, min: c.min, max: c.max, dec: c.dec, grp: c.grp, group: c.group, script: c.script, prefix: c.prefix, suffix: c.suffix, cur: c.cur, rtl: c.rtl });
    return { json, attrs: `inputmode="decimal" dir="ltr" data-num="1" data-numcfg="${esc(json)}"` };
  }

  /** The minimum / maximum of a number, slider or calc field (values: negative and decimal accepted). */
  function efbNumLimit(el, indx, key) {
    const r = list()[indx];
    if (!r || !E()) return false;
    // A number without the F04 keys keeps its old rules (0 / -1 = no minimum), as the page and the server do.
    if (['range', 'calc'].indexOf(r.type) === -1 && !(r.type === 'number' && isF4(r))) return false;
    const s = E().normalizeDigits(String(el.value || '')).trim().replace(/[٫]/g, '.');
    if (s === '') { delete r[key]; el.classList.remove('border-danger'); redraw(r); changed(); return true; }
    if (!/^-?\d{1,15}(\.\d{1,6})?$/.test(s)) {
      el.classList.add('border-danger');
      if (typeof alert_message_efb === 'function') alert_message_efb('', T('numLimitBad', 'Please enter a number, such as -10 or 9.5.'), 6, 'warning');
      return true;
    }
    el.classList.remove('border-danger');
    const other = key === 'milen' ? r.mlen : r.milen;
    if (other !== undefined && other !== '' && !(key === 'mlen' && Number(s) === 0 && r.num_max_zero !== '1') && !(key === 'milen' && Number(other) === 0 && r.num_max_zero !== '1')) {
      const lo = key === 'milen' ? s : String(other), hi = key === 'milen' ? String(other) : s;
      if (/^-?\d+(\.\d+)?$/.test(lo) && /^-?\d+(\.\d+)?$/.test(hi) && E().cmpDec(hi, lo) < 0) {
        if (typeof alert_message_efb === 'function') alert_message_efb('', T('mxlmn', 'The maximum must not be less than the minimum.'), 8, 'warning');
        el.value = r[key] === undefined ? '' : r[key];
        return true;
      }
    }
    r[key] = s;
    if (el.value !== s) el.value = s;
    redraw(r);
    changed();
    return true;
  }

  const api = { efbCalcIsF4: isF4, efbNumPanelEls, efbCalcBuilderChange, efbNumInputAttrs, efbNumLimit, efb_calc_redraw_efb: redraw };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
  if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
