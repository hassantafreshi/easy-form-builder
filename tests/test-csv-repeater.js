/**
 * F09-L: the CSV of a form's entries (list_form-efb.js fun_export_rows_for_Subscribe_emsFormBuilder) with
 * repeater rows, and the single-entry CSV keys of the response viewer (response-viewer-efb.js efbCsvKeyEfb).
 *
 * Checks: the column scheme "{group} › {field} #k" (k from 1) and "{group} (count)"; two entries with 2 and 3 rows
 * share the columns (row 3 empty for the first); a field outside the group keeps its column; an old entry (no rg)
 * keeps exactly its columns and values; a form with 120 answer columns is not cut at 100 (the old Array(100)).
 * The functions are taken from the shipped files and run with the plugin's own helpers.
 *
 * Run: node tests/test-csv-repeater.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
const t = (label, actual, expected) => {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + JSON.stringify(expected) + '\n  actual   ' + JSON.stringify(actual)); }
};
const lf = fs.readFileSync(path.join(ROOT, 'includes', 'admin', 'assets', 'js', 'list_form-efb.js'), 'utf8');
const ne = fs.readFileSync(path.join(ROOT, 'includes', 'admin', 'assets', 'js', 'new-efb.js'), 'utf8');
const fn = (src, name) => {
  const i = src.indexOf('function ' + name + '(');
  if (i === -1) throw new Error('missing ' + name);
  let depth = 0, j = src.indexOf('{', i);
  for (; j < src.length; j++) { if (src[j] === '{') depth++; else if (src[j] === '}' && --depth === 0) break; }
  return src.slice(i, j + 1);
};
const code = [fn(ne, 'efbParseJson'), fn(ne, 'replaceContentMessageEfb'), fn(lf, 'efb_csv_choice_row_efb'), fn(lf, 'efb_csv_repeater_rows_efb'), fn(lf, 'fun_export_rows_for_Subscribe_emsFormBuilder')].join('\n');
const sandbox = {};
const run = new Function('window', 'efb_var', 'localStorage', 'form_type_emsFormBuilder', 'add_multi', `${code}; return { exp: fun_export_rows_for_Subscribe_emsFormBuilder, split: efb_csv_repeater_rows_efb };`);
const W = { efb_csv_opt_val_now: 'label' };
const api = run(W, { text: { createDate: 'Date', noComment: '', rptCount: '%s (count)' } }, { removeItem() {}, setItem() {}, getItem() { return null; } }, 'form', null);
const entry = (id, rows) => ({ msg_id: String(id), date: '2026-11-14', content: JSON.stringify(rows) });
const g = (n) => ({ id_: 'g1', name: 'Travellers', type: 'repeater', value: String(n), amount: 3 });
const nm = (v, ri) => ({ id_: 'nm', name: 'Name', type: 'text', value: v, rg: 'g1', ri: String(ri), amount: 4 });
const age = (v, ri) => ({ id_: 'age', name: 'Age', type: 'number', value: v, rg: 'g1', ri: String(ri), amount: 5 });
const after = (v) => ({ id_: 'after', name: 'E-mail', type: 'email', value: v, amount: 9 });
api.exp([
  entry(1, [g(2), nm('Sara', 0), age('34', 0), nm('Reza', 1), age('8', 1), after('a@x.test')]),
  entry(2, [g(3), nm('Ali', 0), nm('Mina', 1), nm('Omid', 2), after('b@x.test')]),
  entry(3, [{ id_: 'old', name: 'Old field', type: 'text', value: 'kept', amount: 2 }]),
]);
const rows = W.efb_export_rows_efb;
const head = rows[0];
const col = (h) => head.indexOf(h);
t('columns: "{group} (count)", "{group} › {field} #k", the field outside its own', ['Travellers (count)', 'Travellers › Name #1', 'Travellers › Age #1', 'Travellers › Name #2', 'Travellers › Age #2', 'E-mail', 'Travellers › Name #3', 'Old field'].every((h) => col(h) !== -1), true);
t('entry 1: 2 rows; Name #3 empty', [rows[1][col('Travellers (count)')], rows[1][col('Travellers › Name #1')], rows[1][col('Travellers › Age #2')], rows[1][col('Travellers › Name #3')], rows[1][col('E-mail')]], ['2', 'Sara', '8', '', 'a@x.test']);
t('entry 2: 3 rows in the same columns', [rows[2][col('Travellers (count)')], rows[2][col('Travellers › Name #1')], rows[2][col('Travellers › Name #3')], rows[2][col('Travellers › Age #1')]], ['3', 'Ali', 'Omid', '']);
t('an old entry: its own column and value, nothing else', [rows[3][col('Old field')], rows[3][col('Travellers › Name #1')]], ['kept', '']);
const old = [{ id_: 'a', name: 'A', type: 'text', value: 'x' }];
t('efb_csv_repeater_rows_efb: an entry without rows comes back as it is (same array)', api.split(old) === old, true);

// 120 answer columns: none lost.
W.efb_export_rows_efb = null;
const many = [];
for (let i = 0; i < 120; i++) many.push({ id_: 'f' + i, name: 'Field ' + i, type: 'text', value: 'v' + i, amount: i + 2 });
api.exp([entry(9, many)]);
const h2 = W.efb_export_rows_efb[0];
t('120 answer columns are all exported (not cut at 100)', [h2.indexOf('Field 0') !== -1, h2.indexOf('Field 119') !== -1, W.efb_export_rows_efb[1][h2.indexOf('Field 119')]], [true, true, 'v119']);

// The response viewer's single-entry CSV keys (efbCsvKeyEfb on a DOM-like row).
const rv = fs.readFileSync(path.join(ROOT, 'includes', 'admin', 'assets', 'js', 'response-viewer-efb.js'), 'utf8');
const keyFn = new Function(`${fn(rv, 'efbCsvKeyEfb')}; return efbCsvKeyEfb;`)();
const blk = { getAttribute: (k) => (k === 'data-name' ? 'Travellers' : null), querySelectorAll: () => [r0, r1] };
const r0 = { closest: (s) => (s === '.efb-msg-rpt' ? blk : null) }, r1 = { closest: (s) => (s === '.efb-msg-rpt' ? blk : null) };
const row = (rr) => ({ closest: (s) => (s === '.efb-msg-rpt-row' ? rr : null), classList: { contains: () => false } });
const lbl = (text, key) => ({ getAttribute: (k) => (k === 'data-csv-key' ? key || null : null), textContent: text });
t('viewer CSV: a field of row 2 → "Travellers › Name #2"', keyFn(row(r1), lbl('Name:')), 'Travellers › Name #2');
t('viewer CSV: the group\'s head → "Travellers (count)"; any other row its label', [keyFn(row(null), lbl('Travellers:', 'Travellers (count)')), keyFn(row(null), lbl('E-mail:'))], ['Travellers (count)', 'E-mail']);
console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
