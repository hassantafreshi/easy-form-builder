/**
 * Conditional Logic add-on (public runtime): the order total is redrawn when a rule changes a price.
 *
 * A calculate / set_value / clear_value rule on a price field (prcfld), or a rule hiding it,
 * changes the rows core's total counts (core B7: fun_total_pay_efb() counts a prcfld row by its
 * value). The runtime (vendores-efb logic/logic/assets/public/js/conditional-logic-efb.js,
 * evaluate() -> syncResultData()) rewrote those rows but never asked core to redraw, so
 * "3 guests x $5" kept showing the old total (and the PayPal button kept its old state) until
 * the visitor touched a priced field. Now evaluate() calls fun_total_pay_efb(formId) once when
 * the priced rows changed, and not otherwise.
 *
 * Loads the real runtime with a window stub. Add-on source: EFB_LOGIC_PUBLIC_JS (a file),
 * vendor/logic (git-ignored symlink), or /home/user/vendores-efb/logic.
 *
 * Run: node tests/test-addon-logic-total-redraw.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

const file = [process.env.EFB_LOGIC_PUBLIC_JS || '', path.join(__dirname, '../vendor/logic/logic/assets/public/js/conditional-logic-efb.js'), '/home/user/vendores-efb/logic/logic/assets/public/js/conditional-logic-efb.js'].find(f => f && fs.existsSync(f));
if (!file) { console.log('[SKIP] the Conditional Logic add-on source was not found'); process.exit(0); }

let pass = 0, fail = 0;
const check = (l, ok, d) => { if (ok) { pass++; console.log('[PASS] ' + l); } else { fail++; console.log('[FAIL] ' + l + (d !== undefined ? ' ' + JSON.stringify(d) : '')); } };

const totals = [];
global.window = global;
global.document = { getElementById: () => null, querySelector: () => null, querySelectorAll: () => [] };
global.sendBack_emsFormBuilder_pub = [];
global.fun_total_pay_efb = (formId) => { totals.push({ formId, rows: JSON.parse(JSON.stringify(sendBack_emsFormBuilder_pub)) }); };

const cond = (field_id, compare, value) => ({ type: 'condition', source: 'field', field_id, compare, value: value === undefined ? '' : value });
const structure = [
  { id_: 'form', type: 'form', steps: 1, logic_rules: [
    { id: 'calc', enabled: true, priority: 1, conditions: { type: 'group', operator: 'AND', items: [cond('guests', 'is_not_empty')] },
      actions: [{ type: 'calculate', target: 'total', value: '{guests} * 5', decimals: 2 }] },
    { id: 'hide', enabled: true, priority: 2, conditions: { type: 'group', operator: 'AND', items: [cond('guests', 'gt', '10')] },
      actions: [{ type: 'hide_field', target: 'total' }] },
    { id: 'note', enabled: true, priority: 3, conditions: { type: 'group', operator: 'AND', items: [cond('name', 'is', 'Ann')] },
      actions: [{ type: 'set_value', target: 'greeting', value: 'Hi Ann' }] },
  ] },
  { id_: 's1', type: 'step', step: '1', name: 'Step 1' },
  { id_: 'guests', type: 'number', name: 'Guests', step: '1', amount: 1 },
  { id_: 'total', type: 'prcfld', name: 'Total', step: '1', amount: 2 },
  { id_: 'name', type: 'text', name: 'Name', step: '1', amount: 3 },
  { id_: 'greeting', type: 'text', name: 'Greeting', step: '1', amount: 4 },
];
global.valj_efb_new = [{ id: 5, form_structer: structure }];

const runtime = require(file);
const setAnswer = (id, value) => {
  const rows = sendBack_emsFormBuilder_pub;
  const i = rows.findIndex(r => r.id_ === id);
  const row = { id_: id, type: structure.find(f => f.id_ === id).type, value: String(value), form_id: 5 };
  if (i === -1) rows.push(row); else rows[i] = row;
};
const totalRow = () => sendBack_emsFormBuilder_pub.find(r => r.id_ === 'total');

runtime.init(5);
totals.length = 0;
setAnswer('guests', 3);
runtime.evaluate(5);
check('3 guests: the runtime writes total = 15', totalRow() && Number(totalRow().value) === 15, totalRow());
check('3 guests: core\'s total is redrawn once, for form 5', totals.length === 1 && totals[0].formId === 5, totals.map(t => t.formId));
check('the redraw sees the computed row', totals[0] && totals[0].rows.some(r => r.id_ === 'total' && Number(r.value) === 15));

totals.length = 0;
runtime.evaluate(5);
check('evaluated again with nothing changed: no redraw', totals.length === 0, totals.length);

totals.length = 0;
setAnswer('guests', 4);
runtime.evaluate(5);
check('4 guests: total 20 and one redraw', totalRow() && Number(totalRow().value) === 20 && totals.length === 1, { row: totalRow(), redraws: totals.length });

totals.length = 0;
setAnswer('guests', 11);
runtime.evaluate(5);
check('11 guests: the rule hides the price field, its row goes', !totalRow(), totalRow());
check('and the total is redrawn without it', totals.length === 1 && !totals[0].rows.some(r => r.id_ === 'total'), totals.length);

setAnswer('guests', 2);
runtime.evaluate(5);
totals.length = 0;
setAnswer('name', 'Ann');
runtime.evaluate(5);
check('a rule writing a non-price field: no redraw', sendBack_emsFormBuilder_pub.some(r => r.id_ === 'greeting' && r.value === 'Hi Ann') && totals.length === 0, totals.length);

const keep = global.fun_total_pay_efb;
delete global.fun_total_pay_efb;
setAnswer('guests', 6);
let threw = null;
try { runtime.evaluate(5); } catch (e) { threw = e.message; }
check('without core\'s fun_total_pay_efb (older core): no error', threw === null && Number(totalRow().value) === 30, threw);
global.fun_total_pay_efb = () => { throw new Error('display'); };
setAnswer('guests', 7);
threw = null;
try { runtime.evaluate(5); } catch (e) { threw = e.message; }
check('a failing redraw does not stop the rules', threw === null && Number(totalRow().value) === 35, threw);
global.fun_total_pay_efb = keep;

console.log(`\n========================================\nRESULTS: ${pass} passed, ${fail} failed\n========================================`);
process.exit(fail ? 1 : 0);
