/**
 * AutoPilot B7 (browser): the total shown counts a price field whose value a logic rule wrote.
 *
 * The Conditional Logic runtime replaces the price field's row with {id_, type:'prcfld', value}
 * (no `price` key), and fun_total_pay_efb() summed only `price`, so "3 guests x $5" showed $0.
 * Lifts fun_total_pay_efb() from new-efb.js and runs it with stubs.
 *
 * Run: node tests/test-ap-b7-total-display.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const src = fs.readFileSync(path.join(__dirname, '..', 'includes/admin/assets/js/new-efb.js'), 'utf8');
const s = src.indexOf('function fun_total_pay_efb(form_id) {');
const e = src.indexOf('\nfun_currency_no_convert_efb', s);
let pass = 0, fail = 0;
const check = (l, ok, d) => { if (ok) { pass++; console.log('[PASS] ' + l); } else { fail++; console.log('[FAIL] ' + l + (d !== undefined ? ' ' + JSON.stringify(d) : '')); } };

function total(rows) {
  const label = { innerHTML: '', classList: { contains: () => false } };
  const run = new Function('rows', 'label', `
    let valj_efb = [{ currency: 'USD', getway: 'stripe' }];
    let form_ID_emsFormBuilder = 1;
    const sendBack_emsFormBuilder_pub = rows;
    const lan_name_emsFormBuilder = 'en-US';
    const document = { querySelectorAll: () => [label] };
    const setTimeout = (fn) => fn();
    let updateTotal_efb;
    ${src.slice(s, e)}
    fun_total_pay_efb(1);
    return label.innerHTML;`);
  return run(rows, label);
}
check('runtime row without price: 3 guests x $5 shows $15.00', total([{ id_: 'g', type: 'number', value: '3' }, { id_: 't', type: 'prcfld', value: '15.00' }]) === '$15.00', total([{ id_: 't', type: 'prcfld', value: '15.00' }]));
check('a typed price row (with price) counts once', total([{ id_: 't', type: 'prcfld', value: '12', price: '12' }]) === '$12.00');
check('options keep counting by price', total([{ id_: 'o', type: 'payRadio', value: 'Fish', price: '7' }, { id_: 't', type: 'prcfld', value: '15' }]) === '$22.00');
check('an empty computed value adds nothing', total([{ id_: 't', type: 'prcfld', value: '' }]) === '$0.00');
console.log(`\n========================================\nRESULTS: ${pass} passed, ${fail} failed\n========================================`);
process.exit(fail ? 1 : 0);
