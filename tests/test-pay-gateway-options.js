/**
 * PAY00 optional hooks, field side (PHP Field and its JS twin efbPayCoreEfb give the same output):
 *  - a gateway's field_schema() is cleaned the same way (bad keys / types dropped, typed values);
 *  - pgw_* options are sanitized from that schema (field ids, select allow-list, text and code
 *    limits, checkbox), and kept conservatively when the schema does not know them;
 *  - gateway_options() gives create() pf_methods and every option (defaults when empty);
 *  - the save-time warnings from what gateways declare (currency outside supports, a required
 *    option empty, a mapped field that is gone or of the wrong type);
 *  - field_defaults()['pf'] style defaults (the field's own value wins; only style keys);
 *  - a method's own logo inside its tile (escaped; unsafe addresses dropped).
 *
 * Run: node tests/test-pay-gateway-options.js
 */
'use strict';
const { core, php, js, norm } = require('./pay-parity-lib.js');
let passed = 0, failed = 0;
const t = (name, ok, info) => { if (ok) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + (info ? ': ' + info : '')); } };

const schema = [
  { key: 'pgw_map_email', type: 'field', label: 'Payer e-mail', field_types: ['email'], required: true },
  { key: 'pgw_map_name', type: 'field', label: 'Payer <b>name</b>', field_types: ['text', 'firstName'] },
  { key: 'pgw_region', type: 'select', label: 'Region', options: { eu: 'Europe', ae: 'UAE', 'bad key!': 'x' }, default: 'eu' },
  { key: 'pgw_note', type: 'text', label: 'Note', max: 10 },
  { key: 'pgw_profile', type: 'code', label: 'Profile', max: 12 },
  { key: 'pgw_save', type: 'checkbox', label: 'Save card' },
  { key: 'pgw_map_email', type: 'text', label: 'duplicate key' },
  { key: 'PGW_UPPER', type: 'text' },
  { key: 'pgw_bad_type', type: 'html' },
  { key: 'pgw_nolabel', type: 'code', max: 999 },
  'not an entry', null
];
const cases = [];
const add = (c) => { cases.push(c); return cases.length - 1; };
const iSchema = add({ op: 'gwschema', schema });
const iTwice = add({ op: 'gwsanitize', k: 'pgw_region', v: 'ae', schema: null });
const values = [
  ['pgw_map_email', 'em1'], ['pgw_map_email', 'a b'], ['pgw_map_email', 'x"><script>'], ['pgw_map_email', 12], ['pgw_map_email', true], ['pgw_map_email', 'x'.repeat(41)],
  ['pgw_region', 'ae'], ['pgw_region', 'us'], ['pgw_region', 1], ['pgw_region', 'bad key!'],
  ['pgw_note', '  hello <b>world</b> and more '], ['pgw_note', 'short'], ['pgw_note', ['x']],
  ['pgw_profile', 'pfl_ABC.12'], ['pgw_profile', 'pfl ABC'], ['pgw_profile', 'x'.repeat(13)],
  ['pgw_save', '1'], ['pgw_save', 1], ['pgw_save', true], ['pgw_save', 'yes'], ['pgw_save', 0],
  ['pgw_map_phone', 'ph1'], ['pgw_map_phone', 'p h'], ['pgw_other', '<i>x</i>\u0000y'], ['pgw_other', { a: 1 }],
  ['pgw_', 'x'], ['pgwx', 'x'], ['pf_btn_text', 'x'], ['pgw_Upper', 'x']
];
const iVals = values.map(([k, v]) => add({ op: 'gwsanitize', k, v, schema }));
const iNoSchema = values.map(([k, v]) => add({ op: 'gwsanitize', k, v }));
const iOpts = add({ op: 'gwoptions', row: { pf_methods: 'b,a,b,BAD', pgw_map_email: 'em1', pgw_region: '', pgw_note: 'n<x>', pgw_save: '1', pgw_unknown: 'x' }, schema });
const form = [
  { type: 'payment', currency: 'usd' },
  { id_: 'em1', type: 'email', name: 'E-mail' },
  { id_: 'tx1', type: 'text', name: 'Name' },
  { id_: 'pf1', type: 'gwa', pgw_map_name: 'em1' },
  { id_: 'pf2', type: 'gwa', pgw_map_email: 'em1', pgw_map_name: 'zz9' },
  { id_: 'pf3', type: 'gwb' },
  { id_: 'pf4', type: 'unknown', pgw_map_email: '' }
];
const infos = { gwa: { currencies: ['EUR'], field_schema: schema }, gwb: { currencies: [], field_schema: [{ key: 'pgw_x', type: 'select', options: { a: 'A' }, required: true, default: 'a' }, { key: 'pgw_y', type: 'code', required: true }] } };
const iWarn = add({ op: 'warnings', form, infos });
const iWarnOk = add({ op: 'warnings', form: [{ type: 'payment', currency: 'EUR' }, { id_: 'em1', type: 'email' }, { id_: 'tx', type: 'text' }, { id_: 'pf', type: 'gwa', pgw_map_email: 'em1', pgw_map_name: 'tx' }], infos });
const iWarnBad = add({ op: 'warnings', form: 'x', infos });

const defaults = { btn: 'Pay %amount%', secure: 'S', summary: 'Sum', methods: 'Methods', pending: 'P', success: 'OK', fail: 'F', redirect: 'R', test: 'T', amount: 'A', other: 'O', total: 'Total' };
const methods = [['card', 'Card', { logo: 'https://site.example/l/card.svg', min_minor: { EUR: 100 } }], ['qr', 'QR <b>', { logo: 'javascript:alert(1)' }], ['bank', 'Bank', { logo: '/wp-content/b.svg' }], ['x', 'X', { logo: 'https://e.test/a b.svg' }], 'bad', [null, 'n']];
const fieldCase = (id, row, opts) => add({ op: 'field', row: Object.assign({ id_: id, type: 'gwa', name: 'Pay' }, row), form0: { button_color: 'btn-darkb' }, opts, locale: 'en_US', defaults, ctx: { id, gateway: 'gwa', form_id: '7', label: 'Pay', test: true, preview: false, placeholder: '' } });
const iF1 = fieldCase('f1', {}, { methods, logos: [], mobile: false, defaults: { pf_methods_style: 'grid', pf_btn_icon: 'bi-qr-code', pf_btn_text: 'NOT A STYLE KEY', pf_layout: 'bogus', pf_btn_bg: '#123456' } });
const iF2 = fieldCase('f2', { pf_methods_style: 'select', pf_btn_icon: 'none' }, { methods, logos: [], mobile: false, defaults: { pf_methods_style: 'grid', pf_btn_icon: 'bi-qr-code' } });
const iF3 = fieldCase('f3', { pf_methods: 'bank,card' }, { methods, logos: [], mobile: true });

cases[iTwice].schema = JSON.parse(JSON.stringify(core.cleanSchema(schema)));
const P = php(cases);
const bad = [];
cases.forEach((c, i) => { if (norm(js(c)) !== norm(P[i])) bad.push(c.op + ' ' + JSON.stringify(c.k || '') + ' ' + JSON.stringify(c.v === undefined ? '' : c.v) + '\n js=' + norm(js(c)).slice(0, 300) + '\nphp=' + norm(P[i]).slice(0, 300)); });
t(`PHP and JS twins agree on ${cases.length} cases (schema, sanitize with and without schema, options, warnings, field)`, bad.length === 0, bad.slice(0, 3).join('\n'));

const S = P[iSchema];
t('schema: bad entries dropped (duplicate key, upper case, unknown type, non-objects); 7 kept', S.map((d) => d.key).join(',') === 'pgw_map_email,pgw_map_name,pgw_region,pgw_note,pgw_profile,pgw_save,pgw_nolabel', S.map((d) => d.key).join(','));
t('schema: labels are plain text, select options keep only safe values, code max capped at 60', S[1].label === 'Payer b name/b' || S[1].label.indexOf('<') === -1, S[1].label);
t('schema: select options are [value, label] pairs, bad value dropped; default checked against them', JSON.stringify(S[2].options) === JSON.stringify([['eu', 'Europe'], ['ae', 'UAE']]) && S[2].default === 'eu');
t('schema: a schema that is already clean gives the same options (select pairs)', P[iTwice] === 'ae');
t('schema: code max capped at 60, text max as given', S[6].max === 60 && S[3].max === 10);
const v = (k, val) => P[iVals[values.findIndex(([a, b]) => a === k && JSON.stringify(b) === JSON.stringify(val))]];
t('field option: a field id is kept; spaces, markup, too long or a boolean → empty', v('pgw_map_email', 'em1') === 'em1' && v('pgw_map_email', 'a b') === '' && v('pgw_map_email', 'x"><script>') === '' && v('pgw_map_email', 'x'.repeat(41)) === '' && v('pgw_map_email', true) === '');
t('field option: an integer id becomes its string', v('pgw_map_email', 12) === '12');
t('select option: only a listed value', v('pgw_region', 'ae') === 'ae' && v('pgw_region', 'us') === '' && v('pgw_region', 1) === '' && v('pgw_region', 'bad key!') === '');
t('text option: no tags, cut to its max', v('pgw_note', '  hello <b>world</b> and more ') === 'hello b wo' && v('pgw_note', ['x']) === '');
t('code option: [A-Za-z0-9_.-] up to its max', v('pgw_profile', 'pfl_ABC.12') === 'pfl_ABC.12' && v('pgw_profile', 'pfl ABC') === '' && v('pgw_profile', 'x'.repeat(13)) === '');
t('checkbox option: "1" or empty', v('pgw_save', '1') === '1' && v('pgw_save', 1) === '1' && v('pgw_save', true) === '1' && v('pgw_save', 'yes') === '' && v('pgw_save', 0) === '');
t('unknown pgw_map_* key: kept as a field id', v('pgw_map_phone', 'ph1') === 'ph1' && v('pgw_map_phone', 'p h') === '');
t('unknown pgw_* key: kept as plain text (no tags, no control characters)', v('pgw_other', '<i>x</i>\u0000y') === ' i x /i y'.trim() || v('pgw_other', '<i>x</i>\u0000y').indexOf('<') === -1);
t('malformed keys are not gateway options', v('pgw_', 'x') === '' && v('pgwx', 'x') === '' && v('pf_btn_text', 'x') === '' && v('pgw_Upper', 'x') === '');
const ns = (k, val) => P[iNoSchema[values.findIndex(([a, b]) => a === k && JSON.stringify(b) === JSON.stringify(val))]];
t('without a schema (add-on not active): nothing is flattened to markup, ids stay ids', ns('pgw_map_email', 'em1') === 'em1' && ns('pgw_region', 'us') === 'us' && ns('pgw_note', ['x']) === '' && ns('pgw_save', true) === '');
const O = P[iOpts];
t('gateway_options: pf_methods sanitized, defaults for empty options, unknown keys not passed', JSON.stringify(O) === JSON.stringify({ pf_methods: 'b,a', pgw_map_email: 'em1', pgw_map_name: '', pgw_region: 'eu', pgw_note: 'n x', pgw_profile: '', pgw_save: '1', pgw_nolabel: '' }), JSON.stringify(O));
const W = P[iWarn];
const codes = W.map((w) => w.field_id + ':' + w.code + (w.key ? ':' + w.key : '')).join(' ');
t('warnings: currency outside the gateway\'s list (per gateway field)', W.filter((w) => w.code === 'pay_currency').length === 2 && W[0].currency === 'USD' && JSON.stringify(W[0].allowed) === '["EUR"]', codes);
t('warnings: a required option left empty', codes.indexOf('pf1:pay_option_required:pgw_map_email') !== -1);
t('warnings: a mapped field of the wrong type', codes.indexOf('pf1:pay_option_field_type:pgw_map_name') !== -1);
t('warnings: a mapped field that is not in the form', codes.indexOf('pf2:pay_option_field_missing:pgw_map_name') !== -1);
t('warnings: a required select with a default is fine; a required code without one is not', codes.indexOf('pf3:pay_option_required:pgw_x') === -1 && codes.indexOf('pf3:pay_option_required:pgw_y') !== -1);
t('warnings: an unknown field type (gateway not active) is not checked', codes.indexOf('pf4') === -1);
t('warnings: a correct form has none; a broken form gives none', P[iWarnOk].length === 0 && P[iWarnBad].length === 0, JSON.stringify(P[iWarnOk]));

const F1 = P[iF1], F2 = P[iF2], F3 = P[iF3];
t('defaults: the gateway\'s style defaults apply when the field has none (grid, qr icon, colour)', F1.resolved.methods_style === 'grid' && F1.resolved.btn.icon === 'bi-qr-code' && F1.resolved.btn.bg === '#123456');
t('defaults: only style keys and only valid values (bogus layout ignored)', F1.resolved.layout === 'stack');
t('defaults: the field\'s own value wins', F2.resolved.methods_style === 'select' && F2.resolved.btn.icon === 'none');
t('method logos: a safe https or site-relative logo is shown inside the tile, decorative (alt="", aria-hidden)', F1.html.indexOf('<img class="efb efb-pf-method-logo" src="https://site.example/l/card.svg" alt="" aria-hidden="true"') !== -1 && F1.html.indexOf('src="/wp-content/b.svg"') !== -1);
t('method logos: javascript: and addresses with spaces are dropped; labels escaped', F1.html.indexOf('javascript') === -1 && F1.html.indexOf('a b.svg') === -1 && F1.html.indexOf('QR &lt;b&gt;') !== -1);
t('method logos: malformed method entries are skipped', JSON.stringify(F1.resolved.methods.map((m) => m[0])) === '["card","qr","bank","x"]');
t('method logos: pf_methods order kept with logos', JSON.stringify(F3.resolved.methods) === JSON.stringify([['bank', 'Bank', '/wp-content/b.svg'], ['card', 'Card', 'https://site.example/l/card.svg']]));
t('select style: options carry no image', F2.html.indexOf('<option value="card" selected>Card</option>') !== -1);

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
