/**
 * Scenarios "payout", "payout-mixed" and "payout-shots" of tests/e2e-wp/run.js: PAY-OUT (the Stripe, PayPal and Persia
 * Pay code left core for their add-ons) in a real browser on the real WordPress, test mode. The providers are the fakes
 * of tests/payout-fake-providers.php (mu-plugins/efb-e2e-payout.php; the legacy Stripe field's SDK is the stand-in of
 * the same mu-plugin): the browser's trips to checkout.stripe.com, www.sandbox.paypal.com and sandbox.zarinpal.com are
 * routed to the fake Pay / Fail / Cancel page of the site. Nothing leaves the machine and no real key is used.
 *
 *  payout        step 5: Settings › Payments lists the forms of the older fields; Check now moves the one-time forms
 *                (the subscription stays); for each new field (Stripe Checkout, PayPal, ZarinPal) a guest pays (the
 *                provider asks for the server's amount) and reads the receipt (confirmation code), a declined payment
 *                says so and Try again pays; the admin refunds Stripe and PayPal from the payment's page (Refunded) and
 *                reads ZarinPal's "refund in the ZarinPal panel" and records one made there (Mark as refunded: Refunded);
 *                Move back (confirm) puts the original form back, Move now moves it again.
 *  payout-mixed  each step with mixed versions (tests/e2e-wp/payout-versions.php): new core + OLD add-ons and OLD core +
 *                new add-ons: the builder's Stripe panel (step 1), Settings › Payments (step 2), the visitor's Stripe
 *                field (steps 3–4), the moved-forms list (step 5) — taking payments or the clear "update" notice.
 *  payout-shots  fa_IR and ar at 375 px, each moved screen beside its old place (old core e890a6db with the add-ons
 *                5c4051e, then the working copies): the builder's Stripe panel, Settings › Payments (the keys before,
 *                the add-ons' rows and the moved-forms list now), the add-ons' key screens, the Stripe payments page,
 *                the visitor's payment field of a moved form. Each one: right to left, nothing off-screen.
 */
'use strict';
const path = require('path');
const { execFileSync } = require('child_process');

module.exports = function (S) {
  const { scenario, R, ctl, wp, CFG, sleep, SHOT, builderReady, canvasDrawn, closePanel } = S;
  const pctl = (...args) => {
    const out = wp('eval-file', path.join(__dirname, 'ctl-payout.php'), ...args).trim().split('\n');
    return JSON.parse(out[out.length - 1]);
  };
  const versions = (...args) => {
    let out = '';
    try { out = execFileSync('php', [path.join(__dirname, 'payout-versions.php'), ...args], { encoding: 'utf8', timeout: 600000, env: process.env }); } catch (e) { out = String(e.stdout || ''); }
    const lines = out.trim().split('\n');
    return JSON.parse(lines[lines.length - 1] || '{}');
  };
  const rows = (fid) => ctl('payments', String(fid)) || [];
  const settingsUrl = CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=payments';

  function base() {
    ctl('plan', '3');
    pctl('mu');
    for (const c of ['AdnSPF', 'AdnPAP', 'AdnPPF']) ctl('addon-flag', c, '1');
    pctl('fake', '1');
    pctl('gw-fake', '1');
    pctl('keys', 'old');
    pctl('migration', 'reset');
    pctl('dev-mode', '1');
    pctl('rate-reset');
  }
  function end() {
    try { pctl('fake', '0'); pctl('gw-fake', '0'); pctl('dev-mode', '0'); } catch (e) { /* the site is down */ }
  }

  /** The browser's trips to the providers land on the fake Pay / Fail / Cancel page of the site. */
  async function routeProviders(ctx) {
    const to = (gw, id) => CFG.base + '/wp-json/efb-e2e/v1/payout-checkout?gw=' + gw + '&id=' + encodeURIComponent(id || '');
    await ctx.route(/^https:\/\/checkout\.stripe\.com\//, (r) => r.fulfill({ status: 302, headers: { location: to('stripe', new URL(r.request().url()).pathname.split('/').pop()) } }));
    await ctx.route(/^https:\/\/www\.(sandbox\.)?paypal\.com\//, (r) => r.fulfill({ status: 302, headers: { location: to('paypal', new URL(r.request().url()).searchParams.get('token')) } }));
    await ctx.route(/^https:\/\/(sandbox|payment)\.zarinpal\.com\/pg\/StartPay\//, (r) => r.fulfill({ status: 302, headers: { location: to('zarinpal', new URL(r.request().url()).pathname.split('/').pop()) } }));
  }
  async function guest(context, F, label, vp) {
    const ctx = await context(vp || {}, label);
    await routeProviders(ctx);
    const g = await ctx.newPage();
    await g.goto(ctl('page', String(F.form_id)).url, { waitUntil: 'networkidle' });
    await g.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
    await g.fill(`[data-id="${F.email}-el"]`, 'payer@example.test');
    await g.check(`[id="${F.o1}"]`);
    await sleep(400);
    return g;
  }
  const toProvider = async (g, btn) => {
    await Promise.all([g.waitForURL(/efb-e2e\/v1\/payout-checkout/, { timeout: 25000 }), g.click(btn || '.efb-pf [data-pf-pay]')]);
    await g.waitForSelector('#pay', { timeout: 10000 });
  };
  const leave = (g, btn) => Promise.all([g.waitForURL((u) => u.toString().indexOf('payout-checkout') === -1, { timeout: 25000 }), g.click(btn)]);
  const moves = (a) => a.evaluate(() => Array.from(document.querySelectorAll('#efbPayMovesList .efb-cpa-row')).map((r) => ({ id: Number(r.getAttribute('data-form')), status: r.getAttribute('data-status'), text: r.querySelector('.efb-cpa-st').textContent, btn: (r.querySelector('button') || {}).textContent || '' })));
  async function openSettings(a) {
    await a.goto(settingsUrl, { waitUntil: 'load' });
    await a.waitForSelector('#efbPayAddons', { timeout: 20000 });
    await a.waitForFunction(() => document.querySelector('#efbPayMovesList .efb-cpa-row') || !document.getElementById('efbPayMoves'), null, { timeout: 20000 }).catch(() => {});
    await sleep(500);
  }
  /** Opens a stored form in the builder and its gateway field's settings panel. */
  async function builderPanel(a, fid, type) {
    await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await a.waitForFunction(() => typeof fun_get_form_by_id === 'function', null, { timeout: 20000 });
    await a.evaluate((id) => { const b = document.querySelector(`.efb-act-edit[data-id="${id}"]`); if (b) b.click(); else fun_get_form_by_id(Number(id)); }, fid);
    await builderReady(a);
    await canvasDrawn(a);
    const row = await a.evaluate((t) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.type === t) || null)), type);
    if (!row) return null;
    // As openField(), without waiting for a label box (the "update the add-on" panel has none).
    await closePanel(a).catch(() => {});
    await a.click(`#dropZoneEFB [data-id="${row.dataId}"]`, { position: { x: 20, y: 10 } });
    await a.waitForFunction(() => document.getElementById('sideBoxEfb').classList.contains('show'), null, { timeout: 8000 });
    await sleep(800);
    return row;
  }

  /* ================================================================== payout */
  scenario('payout', 'PAY-OUT step 5: the admin moves the older Stripe / PayPal / Persia Pay forms (Check now; a subscription stays); each new field pays, fails and pays on Try again with the receipt; refunds as the admin sees them; Move back and Move now', async ({ context, adminPage }) => {
    base();
    try {
      const S1 = pctl('legacy-form', 'stripe', 'PAY-OUT e2e Stripe');
      const P1 = pctl('legacy-form', 'paypal', 'PAY-OUT e2e PayPal');
      const Z1 = pctl('legacy-form', 'persiaPay', 'PAY-OUT e2e Persia');
      const R1 = pctl('legacy-form', 'stripe', 'PAY-OUT e2e Subscription', 'paymentmethod=month');
      const sha = pctl('form-json', String(S1.form_id)).sha;
      const a = await adminPage({}, 'payout-admin');
      await openSettings(a);
      let m = await moves(a);
      const pend = [S1, P1, Z1, R1].map((F) => (m.find((x) => x.id === Number(F.form_id)) || {}).status);
      R.check('payout/a: Settings › Payments lists the four forms of the older fields as "Waiting to be moved", with Check now', pend.every((s) => s === 'pending') && await a.isVisible('#efbPayMovesRun'), { pend, m });
      await Promise.all([a.waitForResponse((r) => /legacy-migration\/run/.test(r.url()), { timeout: 30000 }), a.click('#efbPayMovesRun')]);
      await a.waitForFunction((id) => { const r = document.querySelector(`#efbPayMovesList [data-form="${id}"]`); return r && r.getAttribute('data-status') === 'migrated'; }, Number(S1.form_id), { timeout: 20000 });
      m = await moves(a);
      const st = (F) => m.find((x) => x.id === Number(F.form_id)) || {};
      R.check('payout/a: Check now: Stripe, PayPal and Persia Pay "Moved" with Move back; the subscription "Stays on the older field" without a button', [S1, P1, Z1].every((F) => st(F).status === 'migrated' && st(F).text === 'Moved' && st(F).btn === 'Move back') && st(R1).status === 'recurring' && st(R1).text === 'Stays on the older field' && st(R1).btn === '', m);
      await a.screenshot({ path: SHOT('payout-a-moved'), fullPage: true });

      const GW = [['stripecheckout', S1, 'stripe', '2500 USD'], ['paypalcheckout', P1, 'paypal', '25.00 USD'], ['zarinpalgate', Z1, 'zarinpal', '250000 IRR']];
      const paid = {};
      for (const [slug, F, fake, amount] of GW) {
        pctl('rate-reset');
        let g = await guest(context, F, 'payout-' + slug);
        R.check(`payout/b ${slug}: the moved form shows the new payment field, test mode`, await g.isVisible(`.efb-pf[data-gw="${slug}"] [data-pf-pay]`) && await g.evaluate(() => !document.querySelector('.efb-pf [data-pf-el="test"]').hidden));
        await toProvider(g);
        R.check(`payout/b ${slug}: the provider's page asks for the server's amount (${amount})`, (await g.textContent('#amount')).indexOf(amount) === 0, await g.textContent('#amount'));
        await leave(g, '#pay');
        await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 20000 });
        const P = rows(F.form_id)[0];
        const text = await g.evaluate(() => document.body.innerText);
        R.check(`payout/b ${slug}: paid → the receipt with the confirmation code`, P.status === 'paid' && text.indexOf(P.track) !== -1, { status: P.status });
        paid[slug] = P;
        await g.screenshot({ path: SHOT('payout-b-paid-' + slug) });
        await g.context().close();

        pctl('rate-reset');
        g = await guest(context, F, 'payout-fail-' + slug);
        await toProvider(g);
        await leave(g, '#fail');
        await g.waitForSelector('.efb-pf-retry', { state: 'visible', timeout: 25000 });
        const why = await g.textContent('.efb-pf [data-pf-status]');
        R.check(`payout/c ${slug}: declined → "not completed / not charged" and Try again`, /not (been )?charged|not completed/i.test(why), why);
        await toProvider(g, '.efb-pf-retry');
        await leave(g, '#pay');
        await g.waitForFunction(() => /Confirmation Code/i.test(document.body.innerText), null, { timeout: 20000 });
        const after = rows(F.form_id);
        R.check(`payout/c ${slug}: Try again → paid for the same entry (a new attempt; the declined one stays)`, after[0].status === 'paid' && after[0].msg_id === after[1].msg_id && after[1].status !== 'paid', after.slice(0, 2).map((p) => p.status));
        await g.context().close();
      }

      /* refunds */
      for (const slug of ['stripecheckout', 'paypalcheckout']) {
        const P = paid[slug];
        await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + P.id, { waitUntil: 'load' });
        await a.fill('#efbPayRefundAmt', '25');
        await a.click('[data-efb-pay="refund"]');
        await a.waitForFunction(() => /Refunded/.test((document.querySelector('.efb-pay-actions .efb-pay-msg') || {}).textContent || ''), null, { timeout: 20000 }).catch(() => {});
        await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + P.id, { waitUntil: 'load' });
        const now = rows(P.form_id).find((x) => x.id === P.id);
        R.check(`payout/d ${slug}: Refund from the payment's page → Refunded (the page shows it)`, now.status === 'refunded' && /Refunded/.test(await a.evaluate(() => document.body.innerText)), now.status);
        await a.screenshot({ path: SHOT('payout-d-refunded-' + slug), fullPage: true });
      }
      const PZ = paid.zarinpalgate;
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + PZ.id, { waitUntil: 'load' });
      const zText = await a.evaluate(() => document.body.innerText);
      R.check('payout/d zarinpalgate: no Refund button; the page says refunds are made in ZarinPal\'s own panel', !(await a.isVisible('[data-efb-pay="refund"]')) && /ZarinPal/.test(zText), zText.slice(0, 400));
      // ZarinPal refunds are made in its panel; the admin records it here ("Mark as refunded outside EFB").
      await a.click('.efb-pay-ext summary');
      await a.fill('#efbPayExtAmt', '250000');
      await a.fill('#efbPayExtReason', 'Refunded in the ZarinPal panel');
      await a.click('[data-efb-pay="mark_refunded"]');
      await a.waitForFunction(() => /Recorded as refunded/.test((document.querySelector('.efb-pay-actions .efb-pay-msg') || {}).textContent || ''), null, { timeout: 20000 }).catch(() => {});
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_payments&payment=' + PZ.id, { waitUntil: 'load' });
      R.check('payout/d zarinpalgate: refunded in the ZarinPal panel and recorded here ("Mark as refunded outside EFB") → Refunded', rows(PZ.form_id).find((x) => x.id === PZ.id).status === 'refunded' && /Refunded/.test(await a.evaluate(() => document.body.innerText)));
      await a.screenshot({ path: SHOT('payout-d-refunded-zarinpalgate'), fullPage: true });

      /* rollback */
      await openSettings(a);
      await Promise.all([a.waitForResponse((r) => /legacy-migration\/rollback/.test(r.url()), { timeout: 20000 }), a.click(`#efbPayMovesList [data-form="${S1.form_id}"] [data-pmv="rollback"]`)]);
      await a.waitForFunction((id) => { const r = document.querySelector(`#efbPayMovesList [data-form="${id}"]`); return r && r.getAttribute('data-status') === 'rolled_back'; }, Number(S1.form_id), { timeout: 20000 });
      m = await moves(a);
      R.check('payout/e: Move back (confirmed) → "Moved back" with Move now; the form is its original JSON byte for byte', (m.find((x) => x.id === Number(S1.form_id)) || {}).btn === 'Move now' && pctl('form-json', String(S1.form_id)).sha === sha, m);
      const g2ctx = await context({}, 'payout-legacy-again');
      const g2 = await g2ctx.newPage();
      await g2.goto(ctl('page', String(S1.form_id)).url, { waitUntil: 'load' });
      await g2.waitForSelector('.stripe.emsFormBuilder_v', { timeout: 20000 }).catch(() => {});
      R.check('payout/e: the visitor sees the older Stripe field again (its card box, no new payment field)', await g2.evaluate(() => !!document.querySelector('.stripe.emsFormBuilder_v, [id$="-stripe"], .efb.stripe') && !document.querySelector('.efb-pf')));
      await g2ctx.close();
      await Promise.all([a.waitForResponse((r) => /legacy-migration\/migrate/.test(r.url()), { timeout: 20000 }), a.click(`#efbPayMovesList [data-form="${S1.form_id}"] [data-pmv="migrate"]`)]);
      await a.waitForFunction((id) => { const r = document.querySelector(`#efbPayMovesList [data-form="${id}"]`); return r && r.getAttribute('data-status') === 'migrated'; }, Number(S1.form_id), { timeout: 20000 });
      R.check('payout/e: Move now → "Moved" again (stripecheckout)', pctl('form-json', String(S1.form_id)).getway === 'stripecheckout');
      await a.screenshot({ path: SHOT('payout-e-list'), fullPage: true });
    } finally {
      end();
    }
  });

  /* ================================================================== payout-mixed */
  scenario('payout-mixed', 'PAY-OUT mixed versions in the browser (each step ships on its own): new core + OLD add-ons, OLD core + new add-ons — the builder\'s Stripe panel, Settings › Payments, the visitor\'s Stripe field, the moved-forms list', async ({ context, adminPage }) => {
    base();
    try {
      for (const [name, core, add] of [['newcore-oldaddons', 'new', 'old'], ['oldcore-newaddons', 'old', 'new']]) {
        const v = versions('use', core, add);
        R.check(`payout-mixed ${name}: the versions are in place`, v.ok === true, v);
        if (!v.ok) continue;
        pctl('fake', '1');
        pctl('keys', 'old');
        const F = pctl('legacy-form', 'stripe', 'PAY-OUT mixed ' + name);
        const a = await adminPage({}, 'payout-mixed-' + name);
        /* step 1 */
        await builderPanel(a, Number(F.form_id), 'stripe');
        const p1 = await a.evaluate(() => ({ full: !!document.querySelector('#sideBoxEfb #paymentMethodEl'), update: (document.querySelector('#sideBoxEfb [data-efb-legacy-panel="stripe"]') || {}).textContent || '' }));
        R.check(`payout-mixed ${name} step 1: the builder's Stripe panel — ${core === 'new' ? 'the old add-on has no panel hook: "Update … to keep using it with this version."' : 'the old core draws its own panel'}`, core === 'new' ? (!p1.full && /Update .+ to keep using it with this version\./.test(p1.update)) : p1.full, p1);
        await closePanel(a).catch(() => {});
        /* step 2 */
        await a.goto(settingsUrl, { waitUntil: 'load' });
        await a.waitForSelector('#nav-stripe', { state: 'attached', timeout: 20000 });
        await sleep(800);
        const p2 = await a.evaluate(() => ({ keys: !!document.getElementById('stripeSKey_emsFormBuilder'), rows: Array.from(document.querySelectorAll('#efbPayAddons .efb-cpa-row')).filter((r) => !r.closest('#efbPayMoves')).map((r) => r.querySelector('.efb-cpa-st').textContent) }));
        R.check(`payout-mixed ${name} step 2: Settings › Payments — ${core === 'new' ? 'no key boxes; each old add-on "Update or turn on the add-on"' : 'the old core keeps its key boxes'}`, core === 'new' ? (!p2.keys && p2.rows.length === 3 && p2.rows.every((t) => t === 'Update or turn on the add-on')) : p2.keys, p2);
        /* steps 3–4 */
        const gctx = await context({}, 'payout-mixed-guest-' + name);
        const g = await gctx.newPage();
        // js.stripe.com is not reachable here: wait for the page and the field, not for the network to be idle.
        await g.goto(ctl('page', String(F.form_id)).url, { waitUntil: 'load' });
        await g.waitForSelector('.stripe.emsFormBuilder_v, [data-efb-pay-blocked]', { timeout: 20000 }).catch(() => {});
        const p3 = await g.evaluate(() => ({ blocked: !!document.querySelector('[data-efb-pay-blocked]'), box: !!document.querySelector('.stripe.emsFormBuilder_v'), text: (document.querySelector('[data-efb-pay-blocked]') || {}).textContent || '' }));
        R.check(`payout-mixed ${name} steps 3–4: the visitor's Stripe field — ${core === 'new' ? 'the clear "not available" notice in its place (no card box)' : 'the card box (payments taken as before)'}`, core === 'new' ? (p3.blocked && !p3.box) : (p3.box && !p3.blocked), p3);
        await gctx.close();
        /* step 5 */
        if (core === 'new') {
          pctl('migration', 'reset');
          await openSettings(a);
          await Promise.all([a.waitForResponse((r) => /legacy-migration\/run/.test(r.url()), { timeout: 30000 }), a.click('#efbPayMovesRun')]);
          await sleep(1500);
          const m = await moves(a);
          const row = m.find((x) => x.id === Number(F.form_id)) || {};
          R.check(`payout-mixed ${name} step 5: the old add-on has no new field: the form "Waiting: update or turn on the add-on", not written`, row.status === 'waiting_addon' && row.text === 'Waiting: update or turn on the add-on' && pctl('form-json', String(F.form_id)).getway === 'stripe', row);
        } else {
          R.check(`payout-mixed ${name} step 5: the old core has no migration; the form keeps its older field`, pctl('form-json', String(F.form_id)).getway === 'stripe');
        }
        await a.context().close();
      }
    } finally {
      versions('restore');
      end();
    }
  });

  /* ================================================================== payout-shots */
  /** A part of a page at 375 px, right to left: direction, no horizontal scroll, nothing off-screen. */
  const rtl = (p, sel) => p.evaluate((sel) => {
    const root = document.querySelector(sel);
    if (!root) return { missing: sel };
    const vw = document.documentElement.clientWidth;
    const vis = (e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(e).visibility !== 'hidden'; };
    const off = Array.from(root.querySelectorAll('*')).filter((e) => vis(e) && !e.closest('.efb-sr-only, [aria-hidden="true"], .screen-reader-text')).filter((e) => { const r = e.getBoundingClientRect(); return r.left < -1 || r.right > vw + 1; }).map((e) => (e.id || e.className || e.tagName).toString().slice(0, 40));
    return { dir: getComputedStyle(root).direction, scroll: document.documentElement.scrollWidth - vw, off: off.slice(0, 6) };
  }, sel);
  const rtlOk = (x) => !!x && !x.missing && x.dir === 'rtl' && x.scroll <= 1 && x.off.length === 0;
  /* The builder's settings panel: its content right to left and inside the panel's own box. (The builder screen itself
     scrolls sideways at 375 px before and after PAY-OUT; that is compared, not changed here.) */
  const panelCheck = (p) => p.evaluate(() => {
    const box = document.getElementById('sideBoxEfb');
    const root = document.getElementById('sideMenuConEfb');
    if (!box || !root || !box.classList.contains('show')) return { missing: 'panel' };
    const b = box.getBoundingClientRect();
    const vis = (e) => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(e).visibility !== 'hidden'; };
    const out = Array.from(root.querySelectorAll('*')).filter(vis).filter((e) => { const r = e.getBoundingClientRect(); return r.left < b.left - 1 || r.right > b.right + 1; }).map((e) => (e.id || e.className || e.tagName).toString().slice(0, 40));
    return { dir: getComputedStyle(root).direction, out: out.slice(0, 6), text: root.innerText.replace(/\s+/g, ' ').slice(0, 160), scroll: document.documentElement.scrollWidth - document.documentElement.clientWidth };
  });
  const scrolls = {};

  scenario('payout-shots', 'PAY-OUT fa_IR and ar at 375 px: each moved screen beside its old place (old core + old add-ons, then the working copies): the builder\'s Stripe panel, Settings › Payments, the add-ons\' key screens, the Stripe payments page, the moved-forms list and the new payment field', async ({ context, adminPage }) => {
    base();
    const shots = [];
    try {
      for (const when of ['old', 'new']) {
        const v = versions('use', when, when);
        R.check(`payout-shots ${when}: the versions are in place`, v.ok === true, v);
        if (!v.ok) continue;
        pctl('fake', '1');
        pctl('keys', 'old');
        let moved = null;
        if (when === 'new') {
          // One form moved to the new field, then the one that keeps the older field (made after the run).
          pctl('migration', 'reset');
          moved = pctl('legacy-form', 'stripe', 'فرم منتقل‌شده');
          pctl('migration-run');
        }
        const F = pctl('legacy-form', 'stripe', when === 'old' ? 'فرم پرداخت قدیم' : 'فرم پرداخت');
        for (const loc of ['fa_IR', 'ar']) {
          ctl('locale', loc);
          const vp = { viewport: { width: 375, height: 812 }, locale: loc.replace('_', '-') };
          try {
            const a = await adminPage(vp, `payout-shots-${when}-${loc}`);
            const shot = async (p, name, sel) => {
              const x = sel ? await rtl(p, sel) : null;
              if (sel) R.check(`payout-shots ${when} (${loc}): ${name} — right to left, nothing off-screen`, rtlOk(x), x);
              const file = SHOT(`payout-${when}-${name}-${loc}-375`);
              await p.screenshot({ path: file, fullPage: true });
              shots.push(file);
            };
            // The builder's Stripe panel (step 1).
            await builderPanel(a, Number(F.form_id), 'stripe');
            let pc = await panelCheck(a);
            scrolls[when + loc] = pc.scroll;
            R.check(`payout-shots ${when} (${loc}): the builder's Stripe panel — drawn (currency, payment method), right to left, inside its box${when === 'new' ? '; the screen scrolls no more than before PAY-OUT' : ''}`, !pc.missing && pc.dir === 'rtl' && pc.out.length === 0 && pc.text.length > 10 && (when === 'old' || pc.scroll <= (scrolls['old' + loc] === undefined ? pc.scroll : scrolls['old' + loc])), pc);
            await shot(a, 'builder-stripe-panel', null);
            await closePanel(a).catch(() => {});
            // Settings › Payments (step 2: the keys before; the add-ons' rows now; step 5: the moved-forms list).
            await a.goto(settingsUrl, { waitUntil: 'load' });
            await a.waitForSelector('#nav-stripe', { state: 'attached', timeout: 20000 });
            await sleep(1500);
            await a.evaluate(() => { const e = document.getElementById('nav-stripe'); if (e) e.scrollIntoView({ block: 'start' }); });
            await shot(a, 'settings-payments', '#nav-stripe');
            if (when === 'new') {
              // The add-ons' key screens (step 2) and the moved form's builder panel and visitor field (step 5).
              for (const [slug, label] of [['Emsfb_stripe_keys', 'stripe'], ['Emsfb_paypal_keys', 'paypal'], ['Emsfb_persiapay_keys', 'persiapay']]) {
                await a.goto(CFG.base + '/wp-admin/admin.php?page=' + slug, { waitUntil: 'load' });
                await a.waitForSelector('#efbPayKeysScreen', { timeout: 20000 });
                await shot(a, 'keys-' + label, '#efbPayKeysScreen');
              }
              await builderPanel(a, Number(moved.form_id), 'stripecheckout');
              pc = await panelCheck(a);
              // Core's payment-field panel (PAY00, the one every framework gateway uses): at 375 px the builder's settings strip
              // is narrower than its inputs for every framework gateway (not changed by PAY-OUT; reported to the owner).
              R.check(`payout-shots new (${loc}): the moved form's Stripe Checkout panel (core's payment-field panel) — drawn, right to left`, !pc.missing && pc.dir === 'rtl' && /Stripe/.test(pc.text), pc);
              await shot(a, 'builder-stripecheckout-panel', null);
              await closePanel(a).catch(() => {});
              const gctx = await context(vp, `payout-shots-guest-${loc}`);
              const g = await gctx.newPage();
              await g.goto(ctl('page', String(moved.form_id)).url, { waitUntil: 'load' });
              await g.waitForSelector('.efb-pf', { timeout: 20000 });
              await g.evaluate(() => document.querySelector('.efb-pf').scrollIntoView({ block: 'center' }));
              await shot(g, 'visitor-stripecheckout', '.efb-pf');
              await gctx.close();
            } else {
              const gctx = await context(vp, `payout-shots-guest-old-${loc}`);
              const g = await gctx.newPage();
              await g.goto(ctl('page', String(F.form_id)).url, { waitUntil: 'load' });
              await g.waitForSelector('.stripe.emsFormBuilder_v', { timeout: 20000 }).catch(() => {});
              await sleep(1000);
              await shot(g, 'visitor-stripe', null);
              await gctx.close();
            }
            // The Stripe payments page (step 4: loaded by the add-on now, by core before).
            await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_stripe_payment', { waitUntil: 'load' });
            await sleep(1500);
            await shot(a, 'stripe-payments-page', null);
            await a.context().close();
          } finally { ctl('locale', ''); }
        }
      }
      R.check('payout-shots: the screenshots were taken', shots.length >= 16, shots.length);
    } finally {
      versions('restore');
      end();
    }
  });
};
