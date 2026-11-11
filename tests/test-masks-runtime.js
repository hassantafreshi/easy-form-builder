/**
 * F01-E: the mask helpers of public/assets/js/core-efb.js, lifted out of the shipped file and run with stubs
 * (pattern of tests/test-response-viewer-escaping.js). Covers the fallbacks the browser test does not:
 * engine not loaded, preset missing from ajax_object_efm.masks, strict regex switch, custom checksum read
 * from the form structure, sensitive rows kept out of the localStorage draft, offline restore display.
 *
 * Run: node tests/test-masks-runtime.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const src = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'js', 'core-efb.js'), 'utf8');
const grab = (name) => {
  const m = src.match(new RegExp('function ' + name + '\\([\\s\\S]*?\\n}\\n'));
  if (!m) throw new Error('not found: ' + name);
  return m[0];
};
const names = ['efb_mask_fd_efb', 'efb_bind_mask_efb', 'efb_mask_error_efb', 'efb_mask_display_efb', 'efb_mask_row_sensitive_efb', 'valid_typed_value_efb', 'efb_sendback_for_storage_efb'];
const code = names.map(grab).join('\n');
const E = require('../public/assets/js/masks-efb.js');
const cat = require('../includes/data/masks-catalog-efb.json');
const entry = (id) => { const m = cat.masks.find((x) => x.id === id); const o = {}; for (const k of ['id', 'category', 'mask', 'formatter', 'regex', 'regex_strict', 'checksum', 'validator', 'calendar', 'date_order', 'year_range', 'strip', 'uppercase', 'fold_letters', 'pad_left', 'store', 'e164', 'inputmode', 'dir']) o[k] = m[k]; o.placeholder = m.placeholder.en; return o; };

let pass = 0, fail = 0;
const check = (label, actual, expected) => {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log(`[FAIL] ${label}: got ${JSON.stringify(actual)}, expected ${JSON.stringify(expected)}`); }
};

function makeEl(id, ds, value, attrs) {
  const a = Object.assign({}, attrs || {});
  return { id, type: ds.type || 'text', value: value || '', dataset: ds, className: 'form-control', attrs: a,
    getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; }, setAttribute(k, v) { this.attrs[k] = String(v); }, hasAttribute(k) { return k in this.attrs; },
    addEventListener() {}, selectionStart: null };
}
function sandbox(withEngine, masks, els) {
  const store = {};
  const msgs = {};
  const sb = {
    EfbMasks: withEngine ? E : undefined,
    ajax_object_efm: { masks: masks, text: { mskChecksum_: 'CHK:%s', mskFormat_: 'FMT:%1$s:%2$s', mskIncomplete_: 'INC:%s', mskRule_: 'RULE:%s' } },
    localStorage: { setItem(k, v) { store[k] = v; }, getItem(k) { return store[k] || null; } },
    document: { getElementById(id) { if (id.endsWith('-message')) { return msgs[id] || (msgs[id] = { textContent: '', style: {} }); } return els[id] || null; } },
    get_structure_by_form_id_efb() { return [{ type: 'form' }, { type: 'step' }, { id_: 'c', name: 'Card', mask_checksum: 'luhn' }, { id_: 'n', name: 'NID' }, { id_: 'x', name: 'Bad', mask_checksum: 'bogus' }]; },
    colorBorderChangerEfb(c, color) { return c + ' ' + color; },
    show_msg_efb(el) { el.style.display = 'block'; },
    valid_phone_emsFormBuilder() { return 'legacy-phone'; },
    valid_email_emsFormBuilder() { return true; },
    msgs, store, Object, JSON, Array, String, Number,
  };
  vm.createContext(sb);
  vm.runInContext(code, sb);
  return sb;
}

const masks = { 'ir.national_id': entry('ir.national_id'), 'ir.postal_code': entry('ir.postal_code'), 'ir.mobile': entry('ir.mobile'), 'intl.cvc': entry('intl.cvc') };

// Engine missing: masked fields fall back to their old behaviour.
let els = { t_: makeEl('t_', { maskPreset: 'ir.mobile', vid: 't', formid: '1', type: 'tel' }, '0912', { type: 'tel' }) };
els.t_.type = 'tel';
let s = sandbox(false, masks, els);
check('no engine: no field definition', s.efb_mask_fd_efb(els.t_), null);
check('no engine: bind does nothing', s.efb_bind_mask_efb(els.t_, 1), false);
check('no engine: masked tel uses the old phone check', s.valid_typed_value_efb('masked', els.t_), 'legacy-phone');
check('no engine: display value unchanged', s.efb_mask_display_efb(els.t_, '+989121234567'), '+989121234567');

// Preset missing from the page data (older/newer catalog): legacy, never locked.
s = sandbox(true, {}, els);
check('unknown preset: no field definition', s.efb_mask_fd_efb(els.t_), null);
check('unknown preset on a text field: accepted by the typed check', s.valid_typed_value_efb('masked', makeEl('u_', { maskPreset: 'zz.none', vid: 'u', formid: '1' }, 'x')), true);

// Strict switch and store.
s = sandbox(true, masks, {});
const pc = makeEl('p_', { maskPreset: 'ir.postal_code', maskStrict: '1', maskStore: 'display', vid: 'p', formid: '1' }, '1023456789');
const fd = s.efb_mask_fd_efb(pc);
check('strict: regex_strict used', fd.def.regex, masks['ir.postal_code'].regex_strict);
check('strict: catalog entry not modified', masks['ir.postal_code'].regex !== masks['ir.postal_code'].regex_strict, true);
check('store display read from data-mask-store', fd.store, 'display');
check('strict preset rejects a value the base rule accepts', s.valid_typed_value_efb('masked', pc), false);
check('…with the format message (field name from the structure is missing -> aria-label)', s.msgs['p_-message'].textContent.startsWith('FMT:'), true);

// Custom masks: checksum from the form structure (allow-list), broken mask ignored.
const cc = makeEl('c_', { mask: '9999 9999 9999 9999', vid: 'c', formid: '1' }, '4111 1111 1111 1112');
check('custom: checksum read from the structure', s.efb_mask_fd_efb(cc).def, { mask: '9999 9999 9999 9999', checksum: 'luhn' });
check('custom: bad luhn rejected with the checksum message', [s.valid_typed_value_efb('masked', cc), s.msgs['c_-message'].textContent], [false, 'CHK:Card']);
check('custom: unknown checksum in the structure ignored', s.efb_mask_fd_efb(makeEl('x_', { mask: '9999', vid: 'x', formid: '1' })).def.checksum, '');
check('custom: broken mask -> no definition', s.efb_mask_fd_efb(makeEl('b_', { mask: '9{', vid: 'b', formid: '1' })), null);

// Offline restore: canonical stored value back to the display form.
const mob = makeEl('m_', { maskPreset: 'ir.mobile', vid: 'm', formid: '1' }, '');
check('restore display: +989121234567 -> 0912 123 4567', s.efb_mask_display_efb(mob, '+989121234567'), '0912 123 4567');
check('restore display: custom mask', s.efb_mask_display_efb(makeEl('k_', { mask: '(999) 999-9999', vid: 'k', formid: '1' }), '2025550123'), '(202) 555-0123');

// Drafts in localStorage: passwords, national ids, CVC and custom masks with a checksum are left out.
els = {
  n_: makeEl('n_', { maskPreset: 'ir.national_id', vid: 'n', formid: '1' }),
  v_: makeEl('v_', { maskPreset: 'intl.cvc', vid: 'v', formid: '1' }),
  m_: makeEl('m_', { maskPreset: 'ir.mobile', vid: 'm', formid: '1' }),
  c_: makeEl('c_', { mask: '9999 9999 9999 9999', vid: 'c', formid: '1' }),
  o_: makeEl('o_', { vid: 'o', formid: '1' }),
};
s = sandbox(true, masks, els);
const rows = [
  { id_: 'n', id_ob: 'n_', type: 'text', value: '0012345679' }, { id_: 'v', id_ob: 'v_', type: 'text', value: '123' },
  { id_: 'm', id_ob: 'm_', type: 'tel', value: '+989121234567' }, { id_: 'c', id_ob: 'c_', type: 'text', value: '4111111111111111' },
  { id_: 'o', id_ob: 'o_', type: 'text', value: 'hello' }, { id_: 'p', id_ob: 'p_', type: 'password', value: 'secret' },
];
const kept = JSON.parse(s.efb_sendback_for_storage_efb(rows)).map((r) => r.id_);
check('localStorage draft keeps only non-sensitive rows', kept, ['m', 'o']);
check('the rows array itself is not changed (submit still sends everything)', rows.length, 6);

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
