/**
 * BUGS-FOUND 214 (B6): a gateway that counts a currency with another exponent (efb_currency_exponent, e.g. Xendit's IDR
 * without decimals) gets the same amounts on the page as on the server: Registry::public_info()['exponents'] reaches
 * payment-core-efb.js (quote() ctx.exponents, the last argument of fromDecimal / toDecimal / format), and the runtime
 * passes its gateway's map. Without the map nothing changes (ISO 4217).
 *
 * Run: node tests/test-pay-gateway-exponent.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const core = require('../public/assets/js/payment-core-efb.js');
let passed = 0, failed = 0;
const t = (name, ok, info) => { if (ok) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + (info !== undefined ? ': ' + JSON.stringify(info) : '')); } };
const X = { IDR: 0 };

t('exponent: ISO by default, the gateway\'s when given', core.exponent('IDR') === 2 && core.exponent('IDR', X) === 0 && core.exponent('EUR', X) === 2, [core.exponent('IDR'), core.exponent('IDR', X)]);
t('toDecimal / fromDecimal with the gateway\'s map', core.toDecimal(150000, 'IDR', X) === '150000' && core.fromDecimal('150000', 'IDR', X) === 150000 && core.fromDecimal('150000', 'IDR') === 15000000);
const f = core.format(150000, 'IDR', 'en', 'latn', X);
t('format: IDR 150,000 without decimals for that gateway (1,500.00 without the map)', /150,000/.test(f) && !/\.00/.test(f) && /1,500\.00/.test(core.format(150000, 'IDR', 'en', 'latn')), [f]);
const form = [{ type: 'payment', currency: 'idr' }, { id_: '1', type: 'step' }, { id_: 'pf', type: 'efbmock', name: 'Pay', pf_fixed: '150000', pf_fixed_label: 'Fee' }];
const q0 = core.quote(form, [], { field_id: 'pf' });
const q1 = core.quote(form, [], { field_id: 'pf', exponents: X });
t('quote: 150000 IDR is 15000000 minor units by ISO, 150000 with the gateway\'s exponent', q0.ok && q0.total_minor === 15000000 && q1.ok && q1.total_minor === 150000, [q0.total_minor, q1.total_minor]);
t('the map applies only inside that quote()', core.exponent('IDR') === 2);
const rt = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'js', 'payment-runtime-efb.js'), 'utf8');
t('the runtime passes its gateway\'s exponents to quote() and to the amounts it shows', /exponents: g\.exponents/.test(rt) && /gw\(el\)\.exponents/.test(rt));
const reg = fs.readFileSync(path.join(__dirname, '..', 'includes', 'payments', 'class-Emsfb-payment-registry.php'), 'utf8');
t('Registry::public_info() lists the gateway\'s exponents (builder and page)', /'exponents'\s*=>/.test(reg) && /Currency::exponent\( \$c, \$slug \)/.test(reg));
console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
