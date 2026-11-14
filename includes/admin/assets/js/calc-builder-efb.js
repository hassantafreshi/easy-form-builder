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
    if (tag === 'range') html += sel('rangeModeEl', 'range_mode', r, [['single', T('rangeSingle', 'One handle')], ['dual', T('rangeDual', 'Range (two handles)')]], T('rangeMode', 'Slider type'), '');
    if (tag !== 'calc') html += inp('numStepEl', 'num_step', r, T('numStep', 'Step'), tag === 'range' ? '1' : '0.5', T('numStepHelp', 'Empty = any value.'), true);
    html += sel('numDecimalsEl', 'num_decimals', r, tag === 'calc' ? dec.slice(1) : dec, T('numDecimals', 'Decimal places'), '');
    html += efbNumFormatEls(r, tag);
    return html + '</div>';
  }

  function toggle(id, key, r, label, help) {
    const on = r[key] === '1' || r[key] === 'form' || r[key] === 1 || r[key] === true;
    return `<div class="efb mx-1 my-2 d-flex align-items-center">
      <button type="button" id="${id}" data-id="${esc(r.dataId || r.id_ + '-id')}" data-key="${key}" class="efb mx-0 btn h-s-efb btn-toggle efb-calc-ctl ${on ? 'active' : ''}" data-toggle="button" aria-pressed="${on ? 'true' : 'false'}" onclick="fun_switch_form_efb(this)"${help ? ` aria-describedby="${id}Help"` : ''}><div class="efb handle"></div></button>
      <label class="efb form-check-label pt-1 mx-2" for="${id}">${esc(label)}</label></div>
      ${help ? `<small class="efb form-text text-muted fs-8 d-block" id="${id}Help">${esc(help)}</small>` : ''}`;
  }

  /** The display format: thousands separator, separators, digits shown, prefix / suffix, form currency. */
  function efbNumFormatEls(r, tag) {
    let html = `<div class="efb mt-2 efb-calc-format"><span class="efb form-label fw-bold d-block mt-2">${esc(T('numFormat', 'Number format'))}</span>`;
    html += sel('numFormatEl', 'num_format', r, [['', T('numFmtNone', 'Plain')], ['group', T('numGrouped', 'Thousands separator')]], T('numFormat', 'Number format'), '');
    html += sel('numDecSepEl', 'num_dec_sep', r, [['', T('numSepAuto', 'Language default')], ['.', '. (12.5)'], [',', ', (12,5)'], ['momayez', '٫ (۱۲٫۵)']], T('numDecSep', 'Decimal separator'), '');
    html += sel('numGrpSepEl', 'num_grp_sep', r, [['', T('numSepAuto', 'Language default')], [',', ', (1,234)'], ['.', '. (1.234)'], ['space', T('numSepSpace', 'Space')], ['apos', "' (1'234)"], ['arabic', '٬ (۱٬۲۳۴)'], ['none', T('numSepNone', 'None')]], T('numGrouped', 'Thousands separator'), '');
    html += sel('numScriptEl', 'num_digits', r, [['', T('numDigitsLatin', '0-9')], ['auto', T('numDigitsAuto', 'Site language')], ['native', T('numDigitsNative', 'Language digits')]], T('numDigits', 'Digits shown'), '');
    if (tag !== 'number') {
      // A number field has the F03 Prefix / Suffix (its input group); a calc or a slider has these.
      html += inp('numPfxEl', 'num_prefix', r, T('numPrefix', 'Prefix'), '$', '', false);
      html += inp('numSfxEl', 'num_suffix', r, T('numSuffix', 'Suffix'), 'kg', '', false);
    }
    html += toggle('numCurrencyEl', 'num_currency', r, T('numUseCurrency', 'Show as form currency'), '');
    if (tag !== 'calc') html += toggle('numMaxZeroEl', 'num_max_zero', r, T('numMaxZero', 'Maximum 0 is a real maximum'), '');
    const c = cfgOf(r);
    const sample = E() ? E().format(tag === 'calc' ? '1234567.5' : '1234.5', c) : '';
    html += `<div class="efb fs-7 text-muted mt-1">${esc(T('calcPreview', 'Preview'))}: <bdi dir="auto" id="numFmtPreviewEl">${esc(sample)}</bdi></div>`;
    return html + '</div>';
  }

  /* ------------------------------------------------------------------ change handler */

  /** A settings control of F04 changed (change_el_edit_Efb hands it here). Returns true when handled. */
  function efbCalcBuilderChange(el, indx) {
    const r = list()[indx];
    const key = el && el.dataset ? el.dataset.key : '';
    if (!r || !key || !E()) return false;
    let v = el.classList.contains('btn-toggle') ? (el.classList.contains('active') ? (key === 'num_currency' ? 'form' : '1') : '') : el.value;
    const clean = E().sanitizeKey(key, v, key === 'calc' ? 'option' : r.type);
    if ((key === 'num_dec_sep' || key === 'num_grp_sep') && clean !== '') {
      const D = { '.': '.', ',': ',', momayez: '\u066B' }, G = { ',': ',', '.': '.', space: ' ', apos: "'", arabic: '\u066C', none: '' };
      const d = key === 'num_dec_sep' ? D[clean] : (r.num_dec_sep ? D[r.num_dec_sep] : null);
      const g = key === 'num_grp_sep' ? G[clean] : (r.num_grp_sep ? G[r.num_grp_sep] : null);
      if (d !== null && g !== null && d === g) {
        if (typeof alert_message_efb === 'function') alert_message_efb('', T('numSepSame', 'The decimal and thousands separators must differ.'), 6, 'warning');
        el.value = r[key] || '';
        return true;
      }
    }
    if (key === 'num_step' && String(v).trim() !== '' && clean === '') {
      el.classList.add('border-danger');
      if (typeof alert_message_efb === 'function') alert_message_efb('', T('numStepBad', 'The step must be a positive number, such as 0.5 or 5.'), 6, 'warning');
      el.value = r.num_step || '';
      return true;
    }
    el.classList.remove('border-danger');
    if (key === 'formula' && String(v).trim() !== '' && clean === '') {
      // Characters outside the grammar (< > " % …): kept out of the save; the box says where.
      const p = E().parse(v);
      showFormulaErr(p.ok ? { code: 'calcSyntax', near: (String(v).match(/[^A-Za-z0-9_{}().,:\s+\-*\/]/) || [''])[0] } : p);
      return true;
    }
    if (clean === '' && key !== 'formula') delete r[key]; else r[key] = clean;
    if (key === 'range_mode') {
      // A two-handle slider starts on its whole range ("min..max"); one handle on the middle again.
      const lo = r.milen !== undefined && r.milen !== '' ? String(r.milen) : '0', hi = r.mlen !== undefined && r.mlen !== '' && Number(r.mlen) !== 0 ? String(r.mlen) : '100';
      if (clean === 'dual' && !E().splitRange(r.value)) r.value = lo + '..' + hi;
      if (clean !== 'dual' && E().splitRange(r.value)) r.value = '';
      const valueEl = document.getElementById('valueEl');
      if (valueEl) { valueEl.type = 'text'; valueEl.value = r.value || ''; }
      redrawCanvas(r);
    }
    if (key === 'formula') {
      const e = E().formErrors(list())[r.id_];
      if (e) r.formula_err = '1'; else delete r.formula_err;
      showFormulaErr(e);
    }
    if (el.tagName !== 'SELECT' && !el.classList.contains('btn-toggle') && el.value !== clean && key !== 'formula') el.value = clean;
    redraw(r);
    changed();
    return true;
  }

  function showFormulaErr(e) {
    const box = document.getElementById('calcFormulaErrEl');
    if (!box) return;
    box.textContent = efbCalcErrText(e);
    box.classList.toggle('d-none', !e);
  }

  /** The canvas copy of a field again (attributes of a number box, the calc output, the slider). */
  function redraw(r) {
    try {
      const pv = document.getElementById('numFmtPreviewEl');
      if (pv && E()) pv.textContent = E().format(r.type === 'calc' ? '1234567.5' : '1234.5', cfgOf(r));
      const fml = document.getElementById(`${r.id_}_fml`);
      if (fml) fml.textContent = '= ' + (r.formula || '');
      const out = r.type === 'calc' ? document.getElementById(`${r.id_}_out`) : null;
      if (out && E() && fml) { const c = cfgOf(r); out.textContent = E().format(c.cur ? '1234.5' : '0', c); }
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


  /* ------------------------------------------------------------------ the calc field */

  const FN_BUTTONS = ['round', 'ceil', 'floor', 'min', 'max', 'sum', 'avg', 'abs', 'if', 'gt', 'lt', 'eq', 'and', 'or', 'not', 'mod', 'pow'];
  const READABLE = (r) => r && r.type !== 'option' && r.type !== 'step' && r.type !== 'form' && E() && E().FORBIDDEN_TYPES.indexOf(r.type) === -1 && E().LAYOUT_TYPES.indexOf(r.type) === -1;

  /** The text of a formula problem (Calc::form_errors() / EFBCalc.formErrors() code). */
  function efbCalcErrText(e) {
    if (!e) return '';
    switch (e.code) {
      case 'calcRef': return T('calcErrRef', 'No field matches %s.').replace('%s', e.near);
      case 'calcForbidden': return T('calcErrForbidden', '%s cannot be used in a calculation (password, file, signature, map or HTML).').replace('%s', e.near);
      case 'calcFn': return T('calcErrFn', 'Unknown function “%s”.').replace('%s', e.near);
      case 'calcArgs': return T('calcErrArgs', '“%s” has the wrong number of values.').replace('%s', e.near);
      case 'calcCycle': case 'calcSelf': return T('calcCycle', 'This formula uses its own result (directly or through another calculation).');
      case 'calcLong': case 'calcDepth': case 'calcTokens': case 'calcRefs': return T('calcErrLong', 'The formula is too long or too deep.');
      default: return T('calcErrAt', 'The formula has an error near “%s”.').replace('%s', e.near || '…');
    }
  }

  /** The formula box, "Insert field", the function buttons, show / empty, and (payment forms) the amount switch. */
  function efbCalcPanelEls(indx, idset) {
    const r = list()[indx];
    if (!r || !E()) return '<!-- F04: calc-efb.js not loaded -->';
    const did = esc(r.dataId || r.id_ + '-id');
    const fields = list().filter((x, i) => i > 0 && READABLE(x) && x.id_ !== r.id_);
    const err = E().formErrors(list())[r.id_];
    const pay = list()[0] && list()[0].type === 'payment';
    let html = `<div class="efb mt-2 efb-calc-panel" id="efbCalcPanel" data-id="${esc(r.id_)}">
      <label for="calcFormulaEl" class="efb form-label mt-2 mb-1">${esc(T('calcFormula', 'Formula'))}</label>
      <textarea id="calcFormulaEl" rows="2" maxlength="2000" dir="ltr" spellcheck="false" data-id="${did}" data-key="formula" class="efb elEdit form-control efb-calc-ctl text-muted border-d rounded-4 mb-1 font-monospace" aria-describedby="calcFormulaErrEl calcFormulaHelp" placeholder="({price} + 50) * {qty}">${esc(r.formula || '')}</textarea>
      <div id="calcFormulaErrEl" class="efb text-danger fs-7 ${err ? '' : 'd-none'}" role="alert" dir="auto">${esc(efbCalcErrText(err))}</div>
      <small id="calcFormulaHelp" class="efb form-text text-muted fs-8 d-block">${esc(T('calcHelp', 'Use {field} references, numbers, + - * / ( ) and the functions below.'))}</small>
      <label for="calcInsertEl" class="efb form-label mt-2 mb-1">${esc(T('calcInsertField', 'Insert field'))}</label>
      <select id="calcInsertEl" class="efb form-select border-d rounded-4 mb-1" onchange="efb_calc_insert_efb(this.value ? '{' + this.value + '}' : ''); this.value='';">
        <option value="">—</option>${fields.map((x) => `<option value="${esc(x.id_)}">${esc((x.name || x.type) + ' — {' + x.id_ + '}')}</option>`).join('')}
      </select>
      <div class="efb mt-2" role="group" aria-label="${esc(T('calcFunctions', 'Functions'))}"><span class="efb fs-7 d-block mb-1">${esc(T('calcFunctions', 'Functions'))}</span>
        ${FN_BUTTONS.map((fn) => `<button type="button" class="efb btn btn-sm btn-outline-secondary m-1 efb-calc-fn" dir="ltr" onclick="efb_calc_insert_efb('${fn}(')">${fn}()</button>`).join('')}
      </div>`;
    html += sel('calcShowEl', 'calc_show', r, [['visible', T('calcShowRes', 'Show the result to the visitor')], ['hidden', T('hide', 'Hide')]], T('calcShowRes', 'Show the result to the visitor'), '');
    html += sel('calcEmptyEl', 'calc_empty', r, [['zero', T('calcEmptyZero', 'Treat empty answers as 0')], ['blank', T('calcEmptyBlank', 'Leave the result empty')]], T('calcEmptyZero', 'Treat empty answers as 0'), '');
    if (pay) {
      // G (Pro): the plan dialog instead of the switch on a lower plan.
      const ok = planOk('calc_price');
      html += toggle('calcAsPriceEl', 'calc_as_price', r, T('calcAsPrice', 'Use the result as the amount to pay'), T('calcAsPriceHelp', 'The server computes the amount again from the answers; a negative result is refused.'));
      if (!ok) html = html.replace('id="calcAsPriceEl"', 'id="calcAsPriceEl" disabled aria-disabled="true"') + `<a type="button" role="button" tabindex="0" class="efb pro-version-efb" onclick="if (typeof efb_upgrade_prompt_efb === 'function') efb_upgrade_prompt_efb({ feature: 'setting:calc_price', fallback: 'pro', origin: 'field_settings', opener: this }); else if (typeof pro_show_efb === 'function') pro_show_efb(1);" aria-label="${esc(T('proUnlockMsg', 'Pro'))}"><i class="efb bi-gem text-light" aria-hidden="true"></i></a>`;
    }
    html += `<small class="efb form-text text-muted fs-8 d-block mt-2"><i class="efb bi-eye ${typeof iconMarginGlobal !== 'undefined' ? iconMarginGlobal : 'me-1'}" aria-hidden="true"></i>${esc(T('calcPublic', 'Visitors can read the formula in the page source.'))}</small>`;
    return html + '</div>';
  }

  /** Inserts a piece at the caret of the formula box and saves it. */
  function efb_calc_insert_efb(piece) {
    const ta = document.getElementById('calcFormulaEl');
    if (!ta || !piece) return;
    const a = ta.selectionStart === undefined ? ta.value.length : ta.selectionStart, b = ta.selectionEnd === undefined ? a : ta.selectionEnd;
    ta.value = ta.value.slice(0, a) + piece + ta.value.slice(b);
    try { ta.focus(); ta.setSelectionRange(a + piece.length, a + piece.length); } catch (e) {}
    if (typeof change_el_edit_Efb === 'function') change_el_edit_Efb(ta);
  }

  /** The canvas (builder) or preview copy of a calc field: the same markup as Calc::calc_markup(). */
  function efbCalcCanvas(r, preview) {
    if (!r || !E()) return '';
    const c = cfgOf(r);
    const json = JSON.stringify({ decimals: c.decimals, step: c.step, min: c.min, max: c.max, dec: c.dec, grp: c.grp, group: c.group, script: c.script, prefix: c.prefix, suffix: c.suffix, cur: c.cur, rtl: c.rtl });
    const req = Number(r.required) === 1;
    return `<div class="efb efb-calc" data-efb-calc="1" data-vid="${esc(r.id_)}" data-formid="0" data-numcfg="${esc(json)}">
      <output class="efb efb-calc-out form-control-plaintext ${esc(r.el_text_color || '')}${req ? ' required' : ''}" id="${esc(r.id_)}_out" role="status" aria-live="polite" aria-atomic="true" aria-labelledby="${esc(r.id_)}_labG" dir="auto">${preview ? '' : esc(E().format(c.cur ? '1234.5' : '0', c))}</output>
      <input type="hidden" class="efb emsFormBuilder_v" data-vid="${esc(r.id_)}" data-type="calc" data-formid="0" id="${esc(r.id_)}_" value="">
      ${preview ? '' : `<small class="efb efb-calc-fml text-muted fs-8 d-block" dir="ltr" id="${esc(r.id_)}_fml">= ${esc(r.formula || '')}</small>`}
    </div>`;
  }

  /* ------------------------------------------------------------------ a choice's calculation value */

  function planOk(part) {
    const p = root.efb_var && root.efb_var.calc && root.efb_var.calc.plan;
    return p ? !!p[part] : true;
  }

  /** "Calculation value" in the "Show attributes" row of a choice (F05's row). */
  function efbCalcOptionEls(o, p) {
    if (!o || !p || E() === null || E().CHOICE_TYPES.indexOf(p.type) === -1) return '';
    const oid = esc(o.id_);
    const lock = planOk('option_calc') ? '' : ' disabled aria-disabled="true"';
    return `<label for="ElCalcOptions-${oid}" class="efb form-label mx-1 my-0 py-0 fs-7 col-sm-3">${esc(T('calcOptValue', 'Calculation value'))}</label>
      <input type="text" inputmode="decimal" dir="ltr" maxlength="24" placeholder="0" id="ElCalcOptions-${oid}" value="${esc(o.calc || '')}" data-id="${oid}" data-key="calc" class="efb efb-calc-opt text-muted mb-1 fs-7 border-d rounded-4 col-sm-8" onchange="efb_calc_opt_change_efb(this)"${lock}>`;
  }
  function efb_calc_opt_change_efb(el) {
    const o = rowById(el.dataset.id);
    if (!o || !E()) return;
    const v = E().sanitizeKey('calc', el.value, 'option');
    if (String(el.value).trim() !== '' && v === '') {
      el.classList.add('border-danger');
      if (typeof alert_message_efb === 'function') alert_message_efb('', T('numLimitBad', 'Please enter a number, such as -10 or 9.5.'), 6, 'warning');
      el.value = o.calc || '';
      return;
    }
    el.classList.remove('border-danger');
    if (v === '') delete o.calc; else o.calc = v;
    el.value = v;
    changed();
  }


  /* ------------------------------------------------------------------ the two-handle slider */

  /** The canvas / preview copy of a two-handle slider (twin of Calc::range2_markup()). */
  function efbRange2Canvas(r, preview) {
    if (!r || !E()) return '';
    const c = cfgOf(r);
    const dn = (v) => (/^-?\d{1,15}(\.\d{1,6})?$/.test(String(v || '').trim()) ? String(v).trim() : null);
    const min = dn(r.milen) || '0';
    let max = dn(r.mlen) !== null && Number(r.mlen) !== 0 ? dn(r.mlen) : '100';
    if (Number(max) < Number(min)) max = min;
    const pair = E().splitRange(r.value);
    let lo = pair ? pair.lo : min, hi = pair ? pair.hi : max;
    if (Number(lo) < Number(min) || Number(lo) > Number(max)) lo = min;
    if (Number(hi) > Number(max) || Number(hi) < Number(lo)) hi = max;
    const step = c.step || 'any';
    const json = JSON.stringify({ decimals: c.decimals, step: c.step, min: c.min, max: c.max, dec: c.dec, grp: c.grp, group: c.group, script: c.script, prefix: c.prefix, suffix: c.suffix, cur: c.cur, rtl: c.rtl });
    const rtl = !!(root.efb_var && Number(root.efb_var.rtl) === 1);
    const name = r.name || '';
    const dis = preview ? '' : ' disabled';
    const one = (w, v, lab) => `<input type="range" class="efb efb-range2-${w}" id="${esc(r.id_)}_${w}" min="${esc(min)}" max="${esc(max)}" step="${esc(step)}" value="${esc(v)}" aria-label="${esc(lab + ': ' + name)}" aria-valuetext="${esc(E().format(v, c))}"${dis}>`;
    if (preview) setTimeout(() => { try { E().sliders.bindAll(); } catch (e) {} }, 0);
    return `<div class="efb efb-range2${Number(r.required) === 1 ? ' required' : ''}" data-efb-range2="1" data-vid="${esc(r.id_)}" data-formid="0" data-numcfg="${esc(json)}" dir="${rtl ? 'rtl' : 'ltr'}" role="group" aria-labelledby="${esc(r.id_)}_labG">
      <div class="efb efb-range2-track"><span class="efb efb-range2-fill" aria-hidden="true"></span>${one('lo', lo, T('rngLo', 'Minimum'))}${one('hi', hi, T('rngHi', 'Maximum'))}</div>
      <output class="efb efb-range2-out fs-6" id="${esc(r.id_)}_rv" aria-live="polite" dir="auto">${esc(E().format(lo, c) + ' – ' + E().format(hi, c))}</output>
      <input type="hidden" class="efb emsFormBuilder_v" data-vid="${esc(r.id_)}" data-type="range" data-formid="0" id="${esc(r.id_)}_" value="${esc(lo + '..' + hi)}">
    </div>`;
  }

  /** The canvas copy of a slider after its type changed. */
  function redrawCanvas(r) {
    try {
      const f = document.getElementById(`${r.id_}-f`);
      if (!f) return;
      const old = f.querySelector('.efb-range2, .slider');
      if (!old) return;
      const tmp = document.createElement('div');
      if (r.range_mode === 'dual') tmp.innerHTML = efbRange2Canvas(r, false);
      else tmp.innerHTML = `<div class="efb slider m-0 p-2 ${esc(r.el_height || '')} ${esc(r.el_text_color || '')} efb1" data-css="${esc(r.id_)}" id="${esc(r.id_)}-range"><input type="range" class="efb input-efb px-2 mb-0 emsFormBuilder_v w-100 efbField" data-vid="${esc(r.id_)}" id="${esc(r.id_)}_" min="${esc(r.milen || 0)}" max="${esc(r.mlen || 100)}" readonly><p id="${esc(r.id_)}_rv" class="efb mx-1 py-0 my-1 fs-6 text-darkb">50</p></div>`;
      old.replaceWith(tmp.firstElementChild);
    } catch (e) { /* drawn again on the next open */ }
  }

  const api = { efbRange2Canvas, efbCalcPanelEls, efbCalcErrText, efb_calc_insert_efb, efbCalcCanvas, efbCalcOptionEls, efb_calc_opt_change_efb, efbCalcIsF4: isF4, efbNumPanelEls, efbCalcBuilderChange, efbNumInputAttrs, efbNumLimit, efb_calc_redraw_efb: redraw };
  Object.keys(api).forEach((k) => { root[k] = api[k]; });
  if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
