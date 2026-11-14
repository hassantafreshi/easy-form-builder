/**
 * F04 for AutoPilot / SitePilot AI in the real builder (window.efbBuilderApi):
 *  - insertItems / setForm take number keys, a calc field and an option's calc value with the save rules;
 *    a value the rules cannot keep throws calc_invalid; a formula with a problem calc_formula (formula_code =
 *    the server's code: calcRef, calcForbidden, calcCycle, calcSyntax); a calc used as the amount needs
 *    {allow: {payment: true}} (calc_payment);
 *  - validateFormula() gives the same answer as Calc::validate_formula() (PHP, run here);
 *  - setCalc() writes keys of one field (never "step"), reports plan-gated parts;
 *  - removeItems() of a field a formula reads: dangling_reference;
 *  - capabilities: field:calc Free Plus, setting:number_format free, setting:calc_price Pro, capabilities.calc;
 *  - the canvas draws the calc output and a two-handle slider.
 *
 * Run: node tests/test-ap-calc.js
 */
'use strict';
const path = require('path');
const { execFileSync } = require('child_process');
const { openBuilder, makeChecker, blankForm } = require('./ap-builder-harness.js');
const { capabilities } = require('./f18-harness.js');
const c = makeChecker();
const base = (id, type, name, extra) => Object.assign({ id_: id, dataId: id + '-id', type, elementId: type, name, placeholder: '', value: '', size: 100, message: '', id: '', classes: '', required: 0,
  amount: 2, step: 1, label_text_size: 'fs-6', label_position: 'up', el_text_size: 'fs-6', label_text_color: 'text-labelEfb', el_border_color: 'border-d', el_text_color: 'text-labelEfb',
  message_text_color: 'text-muted', el_height: 'h-d-efb', label_align: 'txt-left', message_align: 'justify-content-start', el_align: 'justify-content-start', pro: false, icon_input: '' }, extra || {});

(async () => {
  const form = blankForm();
  form.push(base('price', 'number', 'Price', { amount: '2' }));
  form.push(base('pw', 'password', 'Secret', { amount: '3' }));
  const caps = capabilities(1);
  const h = await openBuilder({ builder: true, pro: 1, packageType: 1, form, extraVar: { capabilities: caps } });
  const { page } = h;
  const r = await page.evaluate(() => {
    const o = {};
    const tryit = (f) => { try { const v = f(); return v === undefined ? 'ok' : v; } catch (e) { return { code: e.code, formula_code: e.formula_code, path: e.path }; } };
    o.ins = tryit(() => { efbBuilderApi.insertItems([{ id_: 'qty', type: 'number', name: 'Qty', num_step: '۱', num_decimals: '0', milen: '1' }, { id_: 'tot', type: 'calc', name: 'Total', formula: '({price}+50)*{qty}', num_decimals: '2', formula_err: '1' }]); return 'inserted'; });
    o.row = JSON.parse(JSON.stringify(valj_efb.find((x) => x.id_ === 'qty')));
    o.tot = JSON.parse(JSON.stringify(valj_efb.find((x) => x.id_ === 'tot')));
    o.bad = tryit(() => efbBuilderApi.insertItems([{ id_: 'b1', type: 'number', name: 'B', num_step: 'abc' }]));
    o.ref = tryit(() => efbBuilderApi.insertItems([{ id_: 'c2', type: 'calc', name: 'C', formula: '{nope}+1' }]));
    o.pw = tryit(() => efbBuilderApi.insertItems([{ id_: 'c3', type: 'calc', name: 'C', formula: '{pw}*2' }]));
    o.syn = tryit(() => efbBuilderApi.insertItems([{ id_: 'c4', type: 'calc', name: 'C', formula: '{price} > 2' }]));
    o.pay = tryit(() => efbBuilderApi.insertItems([{ id_: 'c5', type: 'calc', name: 'C', formula: '{price}', calc_as_price: '1' }]));
    o.payOk = tryit(() => { efbBuilderApi.insertItems([{ id_: 'c6', type: 'calc', name: 'C', formula: '{price}', calc_as_price: true }], { allow: { payment: true } }); return 'inserted'; });
    o.v1 = efbBuilderApi.validateFormula('round({price}*1.09,2)');
    o.v2 = efbBuilderApi.validateFormula('{tot}+{nothere}');
    o.v3 = efbBuilderApi.validateFormula('max(1,');
    o.cyc = tryit(() => efbBuilderApi.setCalc('tot', { formula: '{tot}+1' }));
    o.step = tryit(() => efbBuilderApi.setCalc('price', { step: '0.5' }));
    o.set = tryit(() => efbBuilderApi.setCalc('price', { num_step: '0.5', num_format: 'group', milen: '-10', mlen: '۹٫۵' }));
    o.price = JSON.parse(JSON.stringify(valj_efb.find((x) => x.id_ === 'price')));
    o.dangling = tryit(() => efbBuilderApi.removeItems(['qty']));
    const cap = efb_var.capabilities;
    o.cap = { calc: cap.features['field:calc'].package, num: cap.features['setting:number_format'].package, price: cap.features['setting:calc_price'].package, opt: cap.features['setting:calc_option'].package, block: !!cap.calc && cap.calc.functions.indexOf('round') !== -1 && cap.calc.never.step.length > 0 };
    return o;
  });
  c.check('insertItems: number keys with the save rules (۱ → 1), formula_err dropped (the server rebuilds it)', r.ins === 'inserted' && r.row.num_step === '1' && r.row.num_decimals === '0' && r.tot.formula === '({price}+50)*{qty}' && !('formula_err' in r.tot), r);
  c.check('a value the rules cannot keep: calc_invalid with its path', r.bad.code === 'calc_invalid' && r.bad.path === 'rows[0].num_step', r.bad);
  c.check('formula problems: calcRef, calcForbidden, calcSyntax (the server\'s codes)', r.ref.code === 'calc_formula' && r.ref.formula_code === 'calcRef' && r.pw.formula_code === 'calcForbidden' && r.syn.formula_code === 'calcSyntax', [r.ref, r.pw, r.syn]);
  c.check('a calc used as the amount needs allow.payment', r.pay.code === 'calc_payment' && r.payOk === 'inserted', [r.pay, r.payOk]);
  c.check('validateFormula: ok with refs / calcRef / calcSyntax', r.v1.ok === true && JSON.stringify(r.v1.refs) === '["price"]' && r.v2.code === 'calcRef' && r.v3.code === 'calcSyntax', [r.v1, r.v2, r.v3]);
  c.check('setCalc: a cycle refused (calcCycle); "step" refused (it is the form step)', r.cyc.formula_code === 'calcCycle' && r.step.code === 'invalid_setting', [r.cyc, r.step]);
  c.check('setCalc: num_step, format and decimal / negative limits written (۹٫۵ → 9.5)', r.set && r.set.changed && r.price.num_step === '0.5' && r.price.num_format === 'group' && r.price.milen === '-10' && r.price.mlen === '9.5', [r.set, r.price]);
  c.check('removeItems of a field a formula reads: dangling_reference', r.dangling.warnings && r.dangling.warnings[0].code === 'dangling_reference' && r.dangling.warnings[0].ref === 'qty', r.dangling);
  c.check('capabilities: field:calc Free Plus, number format free, calc option Free Plus, calc as price Pro, capabilities.calc', JSON.stringify(r.cap) === JSON.stringify({ calc: 'free_plus', num: 'free', price: 'pro', opt: 'free_plus', block: true }), r.cap);
  // The same answers as the PHP validator.
  const formNow = await page.evaluate(() => JSON.parse(JSON.stringify(valj_efb)));
  const cases = ['round({price}*1.09,2)', '{tot}+{nothere}', 'max(1,', '{pw}+1', '{price}*2'];
  const js = await page.evaluate((cs) => cs.map((f) => efbBuilderApi.validateFormula(f)), cases);
  const php = JSON.parse(execFileSync('php', ['-r', `define('ABSPATH','x'); require ${JSON.stringify(path.join(__dirname, '..', 'includes', 'class-Emsfb-calc.php'))}; $in = json_decode(stream_get_contents(STDIN), true); $o = array(); foreach ($in['c'] as $f) $o[] = \\Emsfb\\Calc::validate_formula($f, $in['form']); echo json_encode($o);`], { input: JSON.stringify({ c: cases, form: formNow }) }).toString());
  c.check('validateFormula (JS) = Calc::validate_formula (PHP) for 5 formulas', JSON.stringify(js) === JSON.stringify(php), [js, php]);
  // The canvas.
  await page.evaluate(() => efbBuilderApi.insertItems([{ id_: 'rg', type: 'range', name: 'Budget', range_mode: 'dual', milen: '10', mlen: '50', value: '10..50' }]));
  await page.evaluate(() => efbBuilderApi.render());
  await page.waitForTimeout(800);
  const canvas = await page.evaluate(() => ({ out: !!document.querySelector('#dropZoneEFB #tot_out[role="status"][aria-live="polite"]'), lo: !!document.querySelector('#dropZoneEFB #rg_lo'), hi: !!document.querySelector('#dropZoneEFB #rg_hi') }));
  c.check('the canvas draws the calc output and the two-handle slider', canvas.out && canvas.lo && canvas.hi, canvas);
  c.check('no page errors', h.errors.length === 0, h.errors);
  await h.browser.close();
  c.done();
})().catch((e) => { console.error(e); process.exit(1); });
