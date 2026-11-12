/**
 * PAY00 error scenarios: the browser's twins (efbPayCoreEfb.warnings / payerProblem / countryIso /
 * confirmationEmailField in public/assets/js/payment-core-efb.js) give exactly what the server's
 * Field::warnings / payer_problem / country_iso give, for the new checks: levels, no keys, Test mode,
 * local site in Live, amount limits (fixed, donation), methods that cannot take the currency, the
 * confirmation e-mail field standing in for the payer e-mail, a mapped field not marked Required, two
 * payment fields, a form type that cannot pay; and the payer formats (email, phone, phone_e164,
 * country with allowed countries, Persian / Arabic digits, 00 prefix).
 *
 * Run: node tests/test-pay-error-twins.js
 */
'use strict';
const { core, php, js, norm } = require('./pay-parity-lib.js');
let passed = 0, failed = 0;
const t = (name, ok, info) => { if (ok) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + (info !== undefined ? ': ' + JSON.stringify(info) : '')); } };

const schema = [
  { key: 'pgw_map_email', type: 'field', label: 'Payer e-mail', field_types: ['email'], required: true, format: 'email' },
  { key: 'pgw_map_phone', type: 'field', label: 'Mobile', required_for: ['oney'], format: 'phone_e164' },
  { key: 'pgw_map_country', type: 'field', label: 'Country', required_for: ['oney'], format: 'country', countries: ['fr', 'BE', 'xx1'] },
  { key: 'pgw_x', type: 'select', label: 'X', options: { a: 'A' }, format: 'email', countries: ['FR'] }
];
const methods = [['card', 'Card'], ['oney', 'Oney', { currencies: ['EUR'] }], ['qr', 'QR', { currencies: ['THB'] }]];
const base = (field, f0, extra) => [Object.assign({ type: 'payment', currency: 'EUR', email_to: '' }, f0 || {}),
  { id_: 'em', type: 'email', name: 'Email', required: 1 }, { id_: 'mo', type: 'mobile', name: 'Mobile', required: '1' }, { id_: 'co', type: 'conturyList', name: 'Country', required: true },
  { id_: 'cfr', parent: 'co', type: 'option', value: 'France', id_op: 'FR' }, { id_: 'cde', parent: 'co', type: 'option', value: 'Germany', id_op: 'de' },
  Object.assign({ id_: 'pf', type: 'gw', pf_fixed: '150', pgw_map_email: 'em', pgw_map_phone: 'mo', pgw_map_country: 'co' }, field || {})].concat(extra || []);
const info = (o) => ({ gw: Object.assign({ currencies: ['EUR', 'THB'], field_schema: schema, methods, mode: 'test', ready: true, min_minor: { EUR: 100, THB: 2000 }, max_minor: { EUR: 500000, THB: 15000000 } }, o || {}) });

const W = [
  ['correct form, Test mode', base(), info()],
  ['Live, no keys, local site', base(), info({ mode: 'live', ready: false }), { webhook_local: true }],
  ['Live, keys, public site', base(), info({ mode: 'live' }), { webhook_local: false }],
  ['ready unknown (null) is not a warning', base(), info({ ready: null })],
  ['fixed above max', base({ pf_fixed: '6000' }), info()],
  ['fixed below min (Persian digits)', base({ pf_fixed: '۰٫۵' }), info()],
  ['fixed in a 3-decimal currency', base({ pf_fixed: '1.2345' }, { currency: 'KWD' }), info({ currencies: [], min_minor: { KWD: 100 }, max_minor: { KWD: 1000 } })],
  ['donation min / max outside', base({ pf_fixed: '', pf_amount: 'donation', pf_min: '0.1', pf_max: '9000' }), info()],
  ['donation inside', base({ pf_fixed: '', pf_amount: 'donation', pf_min: '5', pf_max: '50' }), info()],
  ['method that cannot take EUR', base({ pf_methods: 'card,qr' }), info()],
  ['no chosen method left', base({ pf_methods: 'qr' }), info()],
  ['currency refused: no method noise', base({ pf_methods: 'qr' }, { currency: 'usd' }), info()],
  ['nothing mapped', base({ pgw_map_email: '', pgw_map_phone: '', pgw_map_country: '' }), info()],
  ['nothing mapped, card only', base({ pgw_map_email: '', pgw_map_phone: '', pgw_map_country: '', pf_methods: 'card' }), info()],
  ['nothing mapped, oney only', base({ pgw_map_email: '', pgw_map_phone: '', pgw_map_country: '', pf_methods: 'oney' }), info()],
  ['e-mail from the confirmation field', base({ pgw_map_email: '' }, { email_to: 'em' }), info()],
  ['confirmation field that is not an Email field', base({ pgw_map_email: '' }, { email_to: 'mo' }), info()],
  ['mapped field not required', base().map((r) => (r.id_ === 'mo' ? Object.assign({}, r, { required: 0 }) : r)), info()],
  ['mapped field not required, method not offered', base({ pf_methods: 'card' }).map((r) => (r.id_ === 'mo' ? Object.assign({}, r, { required: false }) : r)), info()],
  ['two payment fields', base({}, {}, [{ id_: 'pf2', type: 'gw', pgw_map_email: 'zz' }]), info()],
  ['survey form', base({}, { type: 'survey' }), info()],
  ['no type', base({}, { type: null }), info()],
  ['hostile values', base({ pf_fixed: '<b>1</b>', pf_methods: 'qr,<x>', pgw_map_email: { a: 1 } }, { currency: ['EUR'] }), info({ min_minor: { EUR: 'x' }, max_minor: null })]
];
const P = [
  ['email ok', { key: 'pgw_map_email', type: 'field', format: 'email' }, 'ana@example.test'],
  ['email no domain', { key: 'pgw_map_email', type: 'field', format: 'email' }, 'ana@example'],
  ['email two @', { key: 'pgw_map_email', type: 'field', format: 'email' }, 'a@b@c.de'],
  ['email empty', { key: 'pgw_map_email', type: 'field', format: 'email' }, '  '],
  ['phone ok', { key: 'pgw_map_phone', type: 'field', format: 'phone' }, '0100 123 4567'],
  ['phone Arabic digits', { key: 'pgw_map_phone', type: 'field', format: 'phone' }, '٠١٠٠١٢٣٤٥٦٧'],
  ['phone too short', { key: 'pgw_map_phone', type: 'field', format: 'phone' }, '12-34'],
  ['phone letters', { key: 'pgw_map_phone', type: 'field', format: 'phone' }, '0100 ABC 4567'],
  ['e164 ok', { key: 'pgw_map_phone', type: 'field', format: 'phone_e164' }, '+33 6 12 34 56 78'],
  ['e164 00 prefix', { key: 'pgw_map_phone', type: 'field', format: 'phone_e164' }, '0033 (6) 12.34.56.78'],
  ['e164 Persian digits', { key: 'pgw_map_phone', type: 'field', format: 'phone_e164' }, '+۹۸۹۱۲۱۲۳۴۵۶۷'],
  ['e164 no plus', { key: 'pgw_map_phone', type: 'field', format: 'phone_e164' }, '06 12 34 56 78'],
  ['e164 +0', { key: 'pgw_map_phone', type: 'field', format: 'phone_e164' }, '+0612345678'],
  ['country code', { key: 'pgw_map_country', type: 'field', format: 'country', countries: ['FR'] }, 'fr'],
  ['country name from the form', { key: 'pgw_map_country', type: 'field', format: 'country', countries: ['FR'] }, 'France', 'co'],
  ['country not allowed', { key: 'pgw_map_country', type: 'field', format: 'country', countries: ['FR'] }, 'Germany', 'co'],
  ['country any', { key: 'pgw_map_country', type: 'field', format: 'country' }, 'Germany', 'co'],
  ['country unknown', { key: 'pgw_map_country', type: 'field', format: 'country' }, 'Atlantis', 'co'],
  ['country by option id', { key: 'pgw_map_country', type: 'field', format: 'country' }, 'cde', 'co'],
  ['no format', { key: 'pgw_map_name', type: 'field' }, 'anything'],
  ['format on a select is ignored', { key: 'pgw_x', type: 'select', options: { a: 'A' }, format: 'email' }, 'a'],
  ['unknown format', { key: 'pgw_map_name', type: 'field', format: 'iban' }, 'x']
];
const form = base();
const cases = W.map(([name, f, i, site]) => ({ op: 'warnings', form: f, infos: i, site: site || {}, name }))
  .concat(P.map(([name, d, value, fid]) => ({ op: 'payer', d, value, form, field_id: fid || '', name })));
const out = php(cases);
const diff = [];
cases.forEach((c, i) => { const a = norm(js(c)), b = norm(out[i]); if (a !== b) diff.push(c.name + '\n js=' + a.slice(0, 500) + '\nphp=' + b.slice(0, 500)); });
t(`PHP and JS agree on ${cases.length} cases (${W.length} forms, ${P.length} payer values)`, diff.length === 0, diff.slice(0, 3));

const R = (name) => out[cases.findIndex((c) => c.name === name)];
const codes = (name) => R(name).map((w) => w.code + ':' + w.level).join(' ');
t('warnings: a correct form in Test mode → only the info', codes('correct form, Test mode') === 'pay_test_mode:info', codes('correct form, Test mode'));
t('warnings: Live without keys on a local site → error + warning', codes('Live, no keys, local site') === 'pay_not_configured:error pay_webhook_local:warning', codes('Live, no keys, local site'));
t('warnings: Live with keys on a public site → none', R('Live, keys, public site').length === 0);
t('warnings: fixed above the maximum → error, below the minimum → warning (Persian digits read)', codes('fixed above max') === 'pay_test_mode:info pay_amount_limit:error' && codes('fixed below min (Persian digits)') === 'pay_test_mode:info pay_amount_limit:warning' && R('fixed below min (Persian digits)')[1].amount_minor === 50);
t('warnings: 3 decimals: 1.2345 KWD = 1235 fils, above 1.000', R('fixed in a 3-decimal currency')[1].amount_minor === 1235 && R('fixed in a 3-decimal currency')[1].limit === 'max');
t('warnings: donation limits → two warnings; inside → none', codes('donation min / max outside') === 'pay_test_mode:info pay_amount_limit:warning pay_amount_limit:warning' && codes('donation inside') === 'pay_test_mode:info');
t('warnings: QR (THB) on a EUR form → warning; nothing left → error; refused currency → no method noise', codes('method that cannot take EUR') === 'pay_test_mode:info pay_method_currency:warning' && codes('no chosen method left') === 'pay_test_mode:info pay_method_currency:error' && codes('currency refused: no method noise') === 'pay_test_mode:info pay_currency:error');
t('warnings: nothing mapped → e-mail error, Oney fields warnings; card only → e-mail only; Oney only → all errors', codes('nothing mapped') === 'pay_test_mode:info pay_option_required:error pay_option_required:warning pay_option_required:warning' && codes('nothing mapped, card only') === 'pay_test_mode:info pay_option_required:error' && codes('nothing mapped, oney only') === 'pay_test_mode:info pay_option_required:error pay_option_required:error pay_option_required:error', [codes('nothing mapped'), codes('nothing mapped, oney only')]);
t('warnings: the confirmation e-mail field stands in for the payer e-mail (only an Email field)', codes('e-mail from the confirmation field') === 'pay_test_mode:info' && codes('confirmation field that is not an Email field') === 'pay_test_mode:info pay_option_required:error');
t('warnings: a mapped field not Required → info (only when the payment needs it)', codes('mapped field not required') === 'pay_test_mode:info pay_option_not_required:info' && codes('mapped field not required, method not offered') === 'pay_test_mode:info');
t('warnings: two payment fields → one error, the second field still checked', codes('two payment fields').indexOf('pay_multiple_fields:error') !== -1 && codes('two payment fields').indexOf('pay_option_field_missing:error') !== -1);
t('warnings: survey / no type → pay_form_type error', codes('survey form').indexOf('pay_form_type:error') === 0 && codes('no type').indexOf('pay_form_type:error') === 0);
const pr = (name) => R(name).problem + (R(name).iso ? '/' + R(name).iso : '');
t('payer: e-mail formats', [pr('email ok'), pr('email no domain'), pr('email two @'), pr('email empty')].join(',') === ',format,format,', [pr('email ok'), pr('email no domain'), pr('email two @'), pr('email empty')]);
t('payer: phone 7-15 digits (Arabic digits read), letters refused', [pr('phone ok'), pr('phone Arabic digits'), pr('phone too short'), pr('phone letters')].join(',') === ',,format,format');
t('payer: phone_e164: + or 00 and the country code (Persian digits read)', [pr('e164 ok'), pr('e164 00 prefix'), pr('e164 Persian digits'), pr('e164 no plus'), pr('e164 +0')].join(',') === ',,,format,format');
t('payer: country: a code, the form\'s option (name or id) → its ISO code; outside the list → country; unknown → format', [pr('country code'), pr('country name from the form'), pr('country not allowed'), pr('country any'), pr('country unknown'), pr('country by option id')].join(',') === '/FR,/FR,country/DE,/DE,format,/DE', [pr('country code'), pr('country name from the form'), pr('country not allowed'), pr('country any'), pr('country unknown'), pr('country by option id')]);
t('payer: no / unknown format, or a format on a non-field option → nothing to check', pr('no format') === '' && pr('format on a select is ignored') === '' && pr('unknown format') === '');
t('confirmationEmailField: only an Email field of the form', core.confirmationEmailField(base({}, { email_to: 'em' })) === 'em' && core.confirmationEmailField(base({}, { email_to: 'mo' })) === '' && core.confirmationEmailField(base({}, { email_to: 'gone' })) === '');

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
