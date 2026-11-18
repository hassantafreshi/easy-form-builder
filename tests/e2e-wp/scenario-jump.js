/**
 * Scenario "jump" of tests/e2e-wp/run.js: the owner's rule for a Conditional Logic forward jump, in a real browser on
 * the real WordPress. Forms from `ctl.php jump-form`: "Plan" on step 1 = Express jumps to step 3, so step 2 (a required
 * text and a required file; in the payment form also a priced choice and the payment field) is skipped.
 *  a  Express jumps to step 3; step 2 is marked skipped (data-logic-skipped) and left out of Back; Previous returns to
 *     step 1; the visitor chooses Full: step 2 opens again, Next lands on it and holds on its required field; the
 *     visitor types on step 2, goes back and chooses Express again: the jump, the typed answer cleared and out of the
 *     answers; Send stores the entry without step 2;
 *  b  the payment form: Express, Send: an ordinary entry, no payments/create request, no payment row.
 * The engine and server sides: tests/test-conditional-logic-scenarios.{js,php} (steps/jump), tests/test-logic-jump-http.php.
 */
'use strict';
const PE = require('./pay-errors-e2e.js');

module.exports = function (S) {
  const { scenario, R, ctl, sleep, SHOT } = S;
  const byId = (id) => `[id="${id}"]`;

  async function state(g, fid, J) {
    return g.evaluate(({ fid, J }) => {
      const body = document.getElementById('body_efb_' + fid);
      const fs = (n) => body.querySelector('[data-step="step-' + n + '-efb"]');
      const rows = (window.sendBack_emsFormBuilder_pub || []).filter((r) => r && Number(r.form_id) === Number(fid)).map((r) => r.id_);
      const det = document.getElementById(J.det + '_');
      return {
        step: body.dataset.currentstep,
        skipped2: fs(2) ? fs(2).dataset.logicSkipped || '' : null,
        hidden2: fs(2) ? fs(2).dataset.logicHidden || '' : null,
        visible: [1, 2, 3].filter((n) => fs(n) && !fs(n).classList.contains('d-none')),
        detValue: det ? det.value : null,
        detRow: rows.indexOf(J.det) !== -1,
        detStar: (() => { const m = document.getElementById(J.det + '_req'); return m ? m.style.display !== 'none' : null; })(),
      };
    }, { fid, J });
  }

  async function choose(g, J, label) {
    await g.selectOption(byId(J.plan + '_options'), { label });
    await sleep(700); // the add-on evaluates 120 ms after a change; the jump moves the step
  }

  scenario('jump', 'Conditional Logic forward jump (owner rule): the skipped step is optional and stores nothing, Back skips it, going back and choosing differently opens it again; a skipped payment field takes no payment', async ({ context }) => {
    ctl('plan', '3');
    ctl('addon-flag', 'AdnSMF', '1');
    const J = ctl('jump-form', 'form', 'Jump e2e form');
    const fid = Number(J.form_id);
    const url = ctl('page', String(fid)).url;

    /* a */
    const gctx = await context({}, 'guest-jump');
    const g = await gctx.newPage();
    await g.goto(url, { waitUntil: 'networkidle' });
    await g.waitForSelector('#body_efb_' + fid, { timeout: 20000 });
    await g.fill(`[data-id="${J.em}-el"]`, 'visitor@example.test');
    await choose(g, J, 'Express');
    let s = await state(g, fid, J);
    R.check('jump/a: Express jumps from step 1 to step 3; step 2 is marked skipped', s.step === '3' && s.skipped2 === '1' && s.hidden2 === '1' && s.visible.join() === '3', s);
    await g.click(`#body_efb_${fid} #prev_efb`);
    await sleep(500);
    s = await state(g, fid, J);
    R.check('jump/a: Previous goes back to step 1 (the skipped step is left out of Back)', s.step === '1' && s.visible.join() === '1', s);
    await choose(g, J, 'Full');
    s = await state(g, fid, J);
    R.check('jump/a: choosing Full instead: step 2 is no longer skipped, its required field is required again', s.step === '1' && s.skipped2 === '0' && s.hidden2 === '0' && s.detStar === true, s);
    await g.click(`#body_efb_${fid} #next_efb`);
    await sleep(600);
    s = await state(g, fid, J);
    R.check('jump/a: Next lands on step 2', s.step === '2' && s.visible.join() === '2', s);
    await g.click(`#body_efb_${fid} #next_efb`);
    await sleep(600);
    s = await state(g, fid, J);
    const held = await g.evaluate((id) => (document.getElementById(id + '_-message') || {}).textContent || '', J.det);
    R.check('jump/a: Next on step 2 holds on its empty required field', s.step === '2' && held.trim() !== '', { s, held });
    await g.fill(byId(J.det + '_'), 'typed on step 2');
    await g.dispatchEvent(byId(J.det + '_'), 'change');
    await sleep(300);
    await g.click(`#body_efb_${fid} #prev_efb`);
    await sleep(500);
    await choose(g, J, 'Express');
    s = await state(g, fid, J);
    R.check('jump/a: back on step 1, Express again: the jump to step 3; the answer typed on step 2 is cleared and not in the answers', s.step === '3' && s.skipped2 === '1' && s.detValue === '' && s.detRow === false, s);
    await g.screenshot({ path: SHOT('jump-a-step3') });
    await g.fill(byId(J.last + '_'), 'the end');
    await g.dispatchEvent(byId(J.last + '_'), 'change');
    const before = ctl('entries', String(fid)).length;
    await g.click(`#body_efb_${fid} #next_efb`); // on the last step Next sends
    await g.waitForFunction(() => /Confirmation Code|Thank you/i.test(document.body.innerText), null, { timeout: 20000 }).catch(() => {});
    const E = ctl('entries', String(fid));
    const ids = E.length ? (Array.isArray(E[E.length - 1].content) ? E[E.length - 1].content : []).map((r) => r && r.id_) : [];
    R.check('jump/a: Send stores the entry without step 2 (no Details, no Document)', E.length === before + 1 && ids.indexOf(J.last) !== -1 && ids.indexOf(J.det) === -1 && ids.indexOf(J.doc) === -1, { n: E.length, ids });
    await gctx.close();

    /* b */
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    const P = ctl('jump-form', 'pay', 'Jump e2e payment form');
    const pid = Number(P.form_id);
    const purl = ctl('page', String(pid)).url;
    const pctx = await context({}, 'guest-jump-pay');
    const p = await pctx.newPage();
    const creates = PE.countCreates(p);
    await p.goto(purl, { waitUntil: 'networkidle' });
    await p.waitForSelector('#body_efb_' + pid, { timeout: 20000 });
    await p.fill(`[data-id="${P.em}-el"]`, 'payer@example.test');
    await choose(p, P, 'Express');
    s = await state(p, pid, P);
    R.check('jump/b: the payment form: Express jumps over the payment step', s.step === '3' && s.skipped2 === '1', s);
    await p.fill(byId(P.last + '_'), 'the end');
    await p.dispatchEvent(byId(P.last + '_'), 'change');
    await p.click(`#body_efb_${pid} #next_efb`);
    await p.waitForFunction(() => /Confirmation Code|Thank you/i.test(document.body.innerText), null, { timeout: 20000 }).catch(() => {});
    const PEn = ctl('entries', String(pid));
    R.check('jump/b: Send stores an ordinary entry; no payments/create request, no payment row (no charge)', PEn.length === 1 && String(PEn[0].read_) === '0' && creates.count === 0 && ctl('payments', String(pid)).length === 0, { entries: PEn.length, read: PEn[0] && PEn[0].read_, creates: creates.count });
    await p.screenshot({ path: SHOT('jump-b-payment') });
    await pctx.close();
  });
};
