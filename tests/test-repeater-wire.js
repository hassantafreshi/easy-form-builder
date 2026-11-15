/**
 * F09-G: the answers as they are sent (EFBRepeaterCore.wire) and folded back on the server (Repeater::fold), and
 * the browser's own conversion in core-efb.js (efb_repeater_wire_rows_efb).
 *
 * Checks: a copy's row gets its field's own id_, id_ob mapped back through the copies (text "{vid}_", a choice's
 * option id, a ranking's ids), rg = the group, ri = the row's place (not its n: after a removed row the places
 * are 0, 1, …); a row of a row that is gone is left out; the group's own row and every other row are the same
 * objects (an old form's answers are sent byte for byte as before); no virtual id in the result; JS wire and PHP
 * fold give the same rows for the same answers (n = ri); actionSendData uses it once, after localStorage is
 * written, and the legacy Stripe route too.
 *
 * Run: node tests/test-repeater-wire.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const ROOT = path.join(__dirname, '..');
const C = require(path.join(ROOT, 'public', 'assets', 'js', 'repeater-efb.js')).core;
let pass = 0, fail = 0;
const t = (label, actual, expected) => {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + JSON.stringify(expected) + '\n  actual   ' + JSON.stringify(actual)); }
};

const base = [{ type: 'form' }, { id_: '1', type: 'step' },
  { id_: 'g', type: 'repeater', name: 'G' },
  { id_: 'nm', type: 'text', group: 'g' }, { id_: 'ex', type: 'checkbox', group: 'g' }, { id_: 'ex1', type: 'option', parent: 'ex', value: 'A' }, { id_: 'ex2', type: 'option', parent: 'ex', value: 'B' },
  { id_: 'rk', type: 'ranking', group: 'g' }, { id_: 'rk1', type: 'option', parent: 'rk' }, { id_: 'rk2', type: 'option', parent: 'rk' },
  { id_: 'after', type: 'email' }];
const st = base.concat(C.copyRows(base, 'g', 0), C.copyRows(base, 'g', 2), C.copyRows(base, 'g', 5));
const order = { g: { 0: 0, 2: 1 } }; // row n=5 was removed
const rows = [
  { id_: 'g', type: 'repeater', value: '2', form_id: 9 },
  { id_: 'nm_r0', id_ob: 'nm_r0_', type: 'text', value: 'Sara', form_id: 9 },
  { id_: 'ex_r0', id_ob: 'ex2_r0', type: 'checkbox', value: 'B', form_id: 9 },
  { id_: 'nm_r2', id_ob: 'nm_r2_', type: 'text', value: 'Reza', form_id: 9 },
  { id_: 'rk_r2', id_ob: 'rk_r2_', type: 'ranking', value: 'x@efb!y', ids: 'rk2_r2,rk1_r2', form_id: 9 },
  { id_: 'nm_r5', id_ob: 'nm_r5_', type: 'text', value: 'Gone', form_id: 9 },
  { id_: 'after', id_ob: 'after_', type: 'email', value: 'a@b.c', form_id: 9 },
];
const w = C.wire(rows, st, order);
t('the group\'s own row and other rows are the same objects', [w[0] === rows[0], w[w.length - 1] === rows[6]], [true, true]);
t('a text copy: own id, id_ob "nm_", rg, ri 0', w[1], { id_: 'nm', id_ob: 'nm_', type: 'text', value: 'Sara', form_id: 9, rg: 'g', ri: '0' });
t('a checkbox choice: the option\'s own id', [w[2].id_, w[2].id_ob, w[2].ri], ['ex', 'ex2', '0']);
t('the row n=2 is sent as ri 1 (its place)', [w[3].id_, w[3].ri], ['nm', '1']);
t('a ranking: ids mapped back', [w[4].id_, w[4].ids], ['rk', 'rk2,rk1']);
t('a row of a removed row (n=5) is left out', w.some((r) => r.value === 'Gone'), false);
t('no virtual id in what is sent', /_r\d/.test(JSON.stringify(w)), false);
t('the browser\'s rows are not changed (the page keeps the copies\' ids)', rows[1].id_, 'nm_r0');
const old = [{ type: 'form' }, { id_: 'a', type: 'text' }];
const oldRows = [{ id_: 'a', type: 'text', value: 'x' }];
t('a form without a group: the same array', C.wire(oldRows, old, {}) === oldRows, true);

/* JS wire = PHP fold for the same answers (n = ri). */
const st2 = base.concat(C.copyRows(base, 'g', 0), C.copyRows(base, 'g', 1));
const rows2 = rows.slice(0, 5).map((r) => Object.assign({}, r, { id_: r.id_.replace('_r2', '_r1'), id_ob: String(r.id_ob).replace('_r2', '_r1'), ids: r.ids ? r.ids.replace(/_r2/g, '_r1') : undefined }));
rows2.forEach((r) => { if (r.ids === undefined) delete r.ids; });
const jsOut = C.wire(rows2, st2, { g: { 0: 0, 1: 1 } });
const virtual = {};
st2.forEach((r) => { if (r.rpt_base) virtual[r.id_] = [r.rpt_base, r.rpt_group, r.rpt_n]; });
const php = execFileSync('php', ['-r', `define('ABSPATH','/'); function apply_filters($h,$v){return $v;} require '${path.join(ROOT, 'includes', 'class-Emsfb-repeater.php').replace(/\\/g, '/')}'; $in = json_decode(stream_get_contents(STDIN), true); echo json_encode(\\Emsfb\\Repeater::fold($in['rows'], $in['virtual']));`], { input: JSON.stringify({ rows: rows2, virtual }) }).toString('utf8');
t('JS wire and PHP fold give the same rows', JSON.parse(php), JSON.parse(JSON.stringify(jsOut)));

/* core-efb.js uses it once, after localStorage, before the request is built; the legacy Stripe route too. */
const core = fs.readFileSync(path.join(ROOT, 'public', 'assets', 'js', 'core-efb.js'), 'utf8');
// actionSendData ends where the next function starts (F10-A's efb_review_quote_efb, which sends wire rows too, follows it).
const sendEnd = [core.indexOf('async function efb_review_quote_efb'), core.indexOf('function efb_page_query_efb')].filter((i) => i > core.indexOf('async function actionSendData_emsFormBuilder')).sort((x, y) => x - y)[0];
const send = core.slice(core.indexOf('async function actionSendData_emsFormBuilder'), sendEnd);
t('actionSendData: one conversion, after localStorage is written and before data is built', [send.split('efb_repeater_wire_rows_efb(').length - 1, send.indexOf("localStorage.setItem('sendback'") < send.indexOf('efb_repeater_wire_rows_efb('), send.indexOf('efb_repeater_wire_rows_efb(') < send.indexOf('let  data = {')], [1, true, true]);
const stripe = fs.readFileSync(/* SPLIT §5: the Stripe add-on's script */ (function(){const fs=require('fs'),p=require('path');const d=[process.env.EFB_STRIPE_DIR,p.join(__dirname,'..','vendor','stripe'),'/home/user/vendores-efb/stripe'].filter(Boolean).find(x=>fs.existsSync(p.join(x,'assets','js','stripe_pay-efb.js')));return p.join(d,'assets','js','stripe_pay-efb.js');})(), 'utf8');
t('stripe_pay-efb.js sends the converted rows', (stripe.match(/efb_repeater_wire_rows_efb\(sendBack_emsFormBuilder_pub, form_id\)/g) || []).length, 2);
console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
