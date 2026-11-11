/**
 * F18 sub-task B: the upgrade dialog (efb_upgrade_prompt_efb) and the pro_show_efb() shim.
 *
 * The real builder scripts and admin stylesheets run in Chromium (tests/f18-harness.js), with the
 * real English and Persian phrases. Checked:
 * - every state of the matrix §4.2 (S1, S2, S3, S4a, S4a confirm, S4b, S5, S6, S7, S8): headline,
 *   buttons and their order, the credit disclosure next to the Activate button (aria-describedby),
 *   "Get Pro" is a link to the price page in a new tab with the price;
 * - an unlocked feature opens no dialog and runs the caller's action;
 * - the shim: pro_show_efb(1|2|3|"sentence"); a caller's text is never written as markup;
 * - WAI-ARIA dialog: role, aria-labelledby/-describedby, focus on the headline, Tab and Shift+Tab
 *   stay inside, Escape closes, focus returns to the opener, attributes restored;
 * - Activate: the request (source upgrade_prompt, credit_ack, feature), busy state, success closes
 *   with a notice; 409 → in-place confirm; 403 → session message; 500 → error + Try again;
 *   a script's click (isTrusted false) never activates;
 * - "Not now" three times → a short inline note instead of the dialog;
 * - fa_IR RTL at 375 px: exact Persian headline, Latin names in <bdi>, buttons stacked full width,
 *   44 px targets, no horizontal scroll. Screenshots: tests/screenshots/f18-*.png.
 *
 * Run: node tests/test-upgrade-prompt-states.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { openF18, phrases, dialogState, waitClosed, makeChecker, ROOT } = require('./f18-harness.js');
const c = makeChecker();
const SHOTS = path.join(ROOT, 'tests', 'screenshots');
if (!fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });
const EN = phrases('en_US');
const FA = phrases('fa_IR');

const open = (page, o) => page.evaluate((o) => { const r = efb_upgrade_prompt_efb(o); return r; }, o);
const ids = (st) => (st.buttons || []).map((b) => b.id).join(',');

async function scenario(label, opts, fn) {
  const h = await openF18(opts);
  try { await fn(h.page, h); } catch (e) { c.check(label + ': ran without exception', false, String(e && e.stack || e)); }
  c.check(label + ': no page errors', h.errors.length === 0 && h.bootErrors.length === 0, h.errors.concat(h.bootErrors));
  await h.browser.close();
}

(async () => {
  // ---------------------------------------------------------------- the matrix, English
  await scenario('Free', { pro: 0, packageType: 2, width: 1280, height: 900 }, async (page, h) => {
    let r = await open(page, { feature: 'field:esign', origin: 'palette' });
    await page.waitForTimeout(150);
    let st = await dialogState(page);
    c.check('S1: Free + field:esign opens S1', r.shown === true && st.state === 'S1', { r, st });
    c.check('S1: headline names the field and the plan', st.headline === 'Signature is included in Free Plus — free', st.headline);
    c.check('S1: title "Unlock Signature"', st.title === 'Unlock Signature', st.title);
    c.check('S1: buttons Activate, Get Pro, Not now (in this order)', ids(st) === 'activate,buy,notnow', ids(st));
    const act = st.buttons[0]; const buy = st.buttons[1];
    c.check('S1: Activate is a <button> described by the disclosure', act.tag === 'BUTTON' && act.describedby === 'efbUpgDiscEfb' && act.text === 'Activate Free Plus', act);
    c.check('S1: the credit disclosure is visible text, before the click', st.disclosure && st.disclosure.includes('“Powered by Easy Form Builder” credit with a link') && st.disclosure.includes('What will appear?'), st.disclosure);
    c.check('S1: Get Pro names the price and opens the price page in a new tab', buy.tag === 'A' && /#pricing$/.test(buy.href) && buy.target === '_blank' && /noopener/.test(buy.rel) && buy.text.includes('Get Pro — no credit · $27/year') && buy.text.includes('(opens in a new tab)'), buy);
    c.check('S1: role dialog, aria-labelledby the headline, aria-describedby the text', st.role === 'dialog' && st.labelledby === 'efbUpgHeadEfb' && st.describedby === 'efbUpgTextEfb', st);
    c.check('S1: focus starts on the headline', st.active === 'efbUpgHeadEfb', st.active);
    const vis = await page.evaluate(() => { const d = document.getElementById('efbUpgDiscEfb'); const b = d.getBoundingClientRect(); const a = document.querySelector('[data-efb-upg-action="activate"]').getBoundingClientRect(); return { h: b.height, above: b.bottom <= a.top + 1 }; });
    c.check('S1: disclosure is rendered above the Activate button', vis.h > 20 && vis.above, vis);
    await page.screenshot({ path: path.join(SHOTS, 'f18-s1-en-1280.png') });

    // Tab trap, Shift+Tab, Escape, focus return
    const seen = [];
    for (let i = 0; i < 8; i++) {
      await page.keyboard.press('Tab');
      seen.push(await page.evaluate(() => !!document.activeElement.closest('#settingModalEfb')));
    }
    c.check('a11y: Tab keeps the focus inside the dialog', seen.every(Boolean), seen);
    await page.focus('[data-efb-upg-action="activate"]');
    await page.keyboard.press('Shift+Tab');
    const back = await page.evaluate(() => document.activeElement.closest('#settingModalEfb') ? (document.activeElement.getAttribute('data-efb-upg-action') || document.activeElement.tagName) : 'outside');
    c.check('a11y: Shift+Tab from the first button stays inside (wraps)', back !== 'outside', back);
    await page.keyboard.press('Escape');
    await waitClosed(page);
    st = await dialogState(page);
    const aria = await page.evaluate(() => document.getElementById('settingModalEfb').getAttribute('aria-labelledby'));
    c.check('a11y: Escape closes; aria-labelledby is restored', st.open === false && aria !== 'efbUpgHeadEfb', { st, aria });

    // Focus return to the opener (a palette tile button)
    await page.evaluate(() => {
      const b = document.createElement('button'); b.id = 'openerT'; b.textContent = 'tile';
      b.onclick = () => efb_upgrade_prompt_efb({ feature: 'field:maps' }); document.body.appendChild(b);
      window.__ev = []; document.addEventListener('efb:upgrade-prompt', (e) => __ev.push(e.detail.action + ':' + e.detail.feature));
    });
    await page.click('#openerT');
    await page.waitForTimeout(200);
    await page.click('[data-efb-upg-action="notnow"]');
    await waitClosed(page);
    await page.waitForTimeout(50);
    const fr = await page.evaluate(() => ({ a: document.activeElement.id, ev: __ev.slice() }));
    c.check('a11y: Not now returns the focus to the opener', fr.a === 'openerT', fr);
    c.check('events: efb:upgrade-prompt shown and dismiss with the feature', fr.ev.join('|') === 'shown:field:maps|dismiss:field:maps', fr.ev);

    // S2 Pro feature
    await open(page, { feature: 'setting:mobile_layout' }); await page.waitForTimeout(150);
    st = await dialogState(page);
    c.check('S2: Free + Pro feature: headline, Get Pro first, licence link, no Activate', st.state === 'S2' && st.headline === 'Mobile layout settings is a Pro feature' && ids(st) === 'buy,licence,notnow' && st.buttons[0].text.includes('Get Pro · $27/year'), st);
    c.check('S2: says Free Plus does not include it; no credit disclosure', /Free Plus does not include this\./.test(await page.evaluate(() => document.querySelector('.efb-upg').textContent)) && st.disclosure === null);
    await page.keyboard.press('Escape'); await waitClosed(page);

    // S5 steps
    await open(page, { feature: 'limit:steps' }); await page.waitForTimeout(150);
    st = await dialogState(page);
    c.check('S5: step limit: "Free allows up to 2 steps per form", Activate + disclosure', st.state === 'S5' && st.headline === 'Free allows up to 2 steps per form' && st.text === 'Free Plus and Pro allow unlimited steps.' && ids(st) === 'activate,buy,notnow' && !!st.disclosure, st);
    await page.keyboard.press('Escape'); await waitClosed(page);

    // shim
    const shim = [];
    for (const arg of [1, 2, 3, 'You use <img src=x onerror="window.__xss=1"> Pro elements']) {
      await page.evaluate((a) => pro_show_efb(a), arg); await page.waitForTimeout(150);
      shim.push(await dialogState(page));
      await page.keyboard.press('Escape'); await waitClosed(page);
    }
    c.check('shim: pro_show_efb(1) → Pro dialog (generic)', shim[0].state === 'S2' && shim[0].headline === 'This is a Pro feature', shim[0].headline);
    c.check('shim: pro_show_efb(2) → step limit', shim[1].state === 'S5');
    c.check('shim: pro_show_efb(3) → Free Plus activation (generic)', shim[2].state === 'S1' && shim[2].headline === 'This feature is included in Free Plus — free', shim[2].headline);
    c.check('shim: a caller sentence is text, not markup', shim[3].state === 'S2' && shim[3].text.startsWith('You use <img src=x') && (await page.evaluate(() => !window.__xss && !document.querySelector('#settingModalEfb img[src="x"]'))), shim[3].text);
    await open(page, { feature: 'field:esign', label: '<b>Bold</b>' }); await page.waitForTimeout(150);
    st = await dialogState(page);
    c.check('a caller label is escaped', st.headline.startsWith('<b>Bold</b>') && !/<b>/.test(st.headlineHtml.replace(/&lt;b&gt;/g, '')), st.headlineHtml);

    // Untrusted click never activates
    await page.evaluate(() => document.querySelector('[data-efb-upg-action="activate"]').click());
    await page.waitForTimeout(200);
    st = await dialogState(page);
    c.check('agent safety: a script click on Activate sends nothing and says why', h.planCalls.length === 0 && st.open && /only be activated by a click/.test(st.alert), { calls: h.planCalls.length, alert: st.alert });
    await page.keyboard.press('Escape'); await waitClosed(page);

    // Three "Not now" → inline note
    await page.evaluate(() => { sessionStorage.removeItem('efb_upg_notnow_efb'); });
    for (let i = 0; i < 3; i++) { await open(page, { feature: 'field:switch' }); await page.waitForTimeout(120); await page.click('[data-efb-upg-action="notnow"]'); await waitClosed(page); }
    r = await open(page, { feature: 'field:switch' });
    await page.waitForTimeout(100);
    const tip = await page.evaluate(() => { const t = document.getElementById('efbUpgTipEfb'); return t ? { role: t.getAttribute('role'), text: t.textContent } : null; });
    c.check('after three "Not now" the next click shows the inline note, not the dialog', r.state === 'tip' && tip && tip.role === 'status' && /Included in Free Plus \(free\)/.test(tip.text) && !(await dialogState(page)).open, { r, tip });
    await page.click('#efbUpgTipEfb .efb-upg-tip__more'); await page.waitForTimeout(150);
    c.check('the note\'s "See options" still opens the dialog', (await dialogState(page)).state === 'S1');
    await page.keyboard.press('Escape'); await waitClosed(page);
  });

  // ---------------------------------------------------------------- activation request paths
  let n = 0;
  await scenario('Free activation', {
    pro: 0, packageType: 2,
    planAnswer: (pd, i) => {
      n = i;
      if (pd.feature === 'field:maps') return { status: 500, body: { success: false, data: { message: 'Database is busy.' } } };
      if (pd.feature === 'setting:custom_colors') return { status: 403, body: { success: false, data: { success: false, m: 'no' } } };
      return { delay: 300, status: 200, body: { success: true, data: { success: true, package_type: 3, previous_package_type: 2, plan_changed: true } } };
    },
  }, async (page, h) => {
    await page.evaluate(() => { window.__resumed = []; efb_run_resume_efb = (r) => { __resumed.push(r.kind); return null; }; });
    await open(page, { feature: 'field:esign', resume: { kind: 'none' } }); await page.waitForTimeout(150);
    await page.click('[data-efb-upg-action="activate"]');
    await page.waitForTimeout(80);
    const busy = await page.evaluate(() => { const b = document.querySelector('[data-efb-upg-action="activate"]'); const o = document.querySelector('[data-efb-upg-action="notnow"]'); return { busy: b.getAttribute('aria-busy'), dis: b.disabled, label: b.textContent.trim(), other: o.disabled }; });
    c.check('activate: busy state (aria-busy, "Activating…", other buttons disabled)', busy.busy === 'true' && busy.dis && busy.label === 'Activating…' && busy.other, busy);
    await waitClosed(page);
    await page.waitForTimeout(100);
    const call = h.planCalls[0] || {};
    c.check('activate: one request: free_plus, source upgrade_prompt, credit_ack, feature, nonce', h.planCalls.length === 1 && call.planData.selected_plan === 'free_plus' && call.planData.source === 'upgrade_prompt' && call.planData.credit_ack === true && call.planData.feature === 'field:esign' && call.nonce === 'n0nce', call);
    const after = await page.evaluate(() => ({ pkg: efb_var.setting.package_type, pro: efb_var.pro, toast: (document.querySelector('.efb-notification') || {}).textContent || '', resumed: __resumed }));
    c.check('activate: success closes the dialog, plan 3 in the page, notice names the feature, caller action runs', after.pkg === 3 && after.pro === '1' && /Free Plus is on\. Signature is ready\./.test(after.toast) && after.resumed.join() === 'none', after);

    // 500 → error + Try again (plan back to Free for the next scenarios)
    await page.evaluate(() => efb_apply_package_type_efb(2));
    await open(page, { feature: 'field:maps' }); await page.waitForTimeout(150);
    await page.click('[data-efb-upg-action="activate"]'); await page.waitForTimeout(250);
    let st = await dialogState(page);
    c.check('error: the dialog stays, role=alert names the failure and the server message, Try again', st.open && /Free Plus could not be activated\. Database is busy\./.test(st.alert) && (await page.$('#efbUpgAlertEfb [data-efb-upg-retry]')) !== null, st.alert);
    c.check('error: Activate is usable again', await page.evaluate(() => !document.querySelector('[data-efb-upg-action="activate"]').disabled));
    await page.keyboard.press('Escape'); await waitClosed(page);
    await open(page, { feature: 'setting:custom_colors' }); await page.waitForTimeout(150);
    await page.click('[data-efb-upg-action="activate"]'); await page.waitForTimeout(250);
    st = await dialogState(page);
    c.check('403: "Your session has expired…"', /Your session has expired\. Save your work and reload the page\./.test(st.alert), st.alert);
    await page.keyboard.press('Escape'); await waitClosed(page);
  });

  // ---------------------------------------------------------------- Free Plus, lapsed Pro, roles, add-on
  await scenario('Free Plus', { pro: 1, packageType: 3 }, async (page) => {
    let r = await open(page, { feature: 'field:esign', resume: { kind: 'x' } });
    const resumed = await page.evaluate(() => typeof efb_run_resume_efb);
    c.check('Free Plus + field:esign: no dialog (already unlocked)', r.shown === false && r.state === 'open' && !(await dialogState(page)).open, r);
    await open(page, { feature: 'setting:mobile_layout' }); await page.waitForTimeout(150);
    let st = await dialogState(page);
    c.check('S3: Free Plus + Pro feature: "Your site uses Free Plus…", Get Pro, no Activate', st.state === 'S3' && st.text.startsWith('Your site uses Free Plus. Mobile layout settings needs Pro.') && ids(st) === 'buy,licence,notnow', st);
    await page.keyboard.press('Escape'); await waitClosed(page);
    await open(page, { feature: 'limit:logic', limit: { max: 3, unit_label: 'rules' } }); await page.waitForTimeout(150);
    st = await dialogState(page);
    c.check('S6: Free Plus limit: "Free Plus allows up to 3 rules per form"', st.state === 'S6' && st.headline === 'Free Plus allows up to 3 rules per form' && ids(st) === 'buy,licence,notnow', st);
    await page.keyboard.press('Escape'); await waitClosed(page);
    await open(page, { feature: 'field:stripe' }); await page.waitForTimeout(150);
    st = await dialogState(page);
    c.check('S8: Free Plus + Stripe without its add-on: install step', st.state === 'S8' && ids(st) === 'install,notnow' && /install the .*Stripe.* add-on/i.test(st.text), st);
    await page.keyboard.press('Escape'); await waitClosed(page);
  });

  await scenario('Lapsed Pro', { pro: 0, packageType: 0 }, async (page) => {
    await open(page, { feature: 'field:esign' }); await page.waitForTimeout(150);
    let st = await dialogState(page);
    c.check('S4a: lapsed Pro + Free Plus feature: Renew, Check licence, Use Free Plus instead', st.state === 'S4a' && st.headline === 'Your Pro licence is not active' && ids(st) === 'renew,licence,usefp,notnow' && st.text === 'Signature needs Free Plus. Renew Pro to get it back, or check your licence key.', st);
    await page.click('[data-efb-upg-action="usefp"]'); await page.waitForTimeout(150);
    st = await dialogState(page);
    c.check('S4a: "Use Free Plus instead" swaps the dialog in place: disclosure + key removal + Switch / Back', st.state === 'S4a-confirm' && /removes the stored licence key/.test(st.disclosure) && ids(st) === 'activate,back' && st.buttons[0].text === 'Switch to Free Plus', st);
    await page.click('[data-efb-upg-action="back"]'); await page.waitForTimeout(150);
    c.check('S4a: Back returns to the renew view', (await dialogState(page)).state === 'S4a');
    await page.keyboard.press('Escape'); await waitClosed(page);
    await open(page, { feature: 'setting:mobile_layout' }); await page.waitForTimeout(150);
    st = await dialogState(page);
    c.check('S4b: lapsed Pro + Pro feature: Renew, Check licence (no Free Plus)', st.state === 'S4b' && ids(st) === 'renew,licence,notnow', st);
  });

  await scenario('Lapsed Pro with a stored key (409)', {
    pro: 0, packageType: 0,
    planAnswer: (pd) => (pd.downgrade_confirmed ? null : { status: 409, body: { success: false, data: { code: 'downgrade_confirmation_required', activation_code_present: true, message: 'confirm' } } }),
  }, async (page, h) => {
    await page.evaluate(() => efb_apply_package_type_efb(2)); // looks Free, server knows the key
    await open(page, { feature: 'field:esign' }); await page.waitForTimeout(150);
    await page.click('[data-efb-upg-action="activate"]'); await page.waitForTimeout(300);
    let st = await dialogState(page);
    c.check('409: the key-removal confirmation appears in place (no second dialog)', st.state === 'S4a-confirm' && /removes the stored licence key/.test(st.disclosure), st.state);
    await page.click('[data-efb-upg-action="activate"]'); await waitClosed(page);
    c.check('409: Switch to Free Plus resends with downgrade_confirmed and succeeds', h.planCalls.length === 2 && h.planCalls[1].planData.downgrade_confirmed === true && (await page.evaluate(() => efb_var.setting.package_type)) === 3, h.planCalls.map((x) => x.planData.downgrade_confirmed));
  });

  await scenario('No permission', { pro: 0, packageType: 2, extraVar: { can_change_plan: false } }, async (page) => {
    await open(page, { feature: 'field:esign' }); await page.waitForTimeout(150);
    const st = await dialogState(page);
    c.check('S7: a user who may not change the plan: ask an administrator, Close only', st.state === 'S7' && st.text === 'Only a site administrator can change the plan. Ask them to activate Free Plus (free) or Pro.' && ids(st) === 'close', st);
  });

  // ---------------------------------------------------------------- Persian, RTL, phone width
  const noOverflow = () => {
    const mc = document.querySelector('#settingModalEfb_ .modal-content').getBoundingClientRect();
    const inner = ['#save-result-foot-efb', '#settingModalEfb .efb-upg', '#settingModalEfb-body'].every((q) => { const e = document.querySelector(q); return e && e.scrollWidth <= e.clientWidth + 1; });
    return { inViewport: mc.left >= -1 && mc.right <= window.innerWidth + 1, inner, pageGrew: document.documentElement.scrollWidth > window.__baseW };
  };
  await scenario('fa_IR 375px', { pro: 0, packageType: 2, locale: 'fa_IR', width: 375, height: 740 }, async (page) => {
    await page.evaluate(() => { window.__baseW = document.documentElement.scrollWidth; });
    await open(page, { feature: 'field:esign' }); await page.waitForTimeout(300);
    const st = await dialogState(page);
    const want = FA.upgInFreePlus.replace('%s', FA.esign);
    c.check('fa: exact Persian headline', st.headline === want, { got: st.headline, want });
    c.check('fa: "Free Plus" is isolated in <bdi>', /<bdi>Free Plus<\/bdi>/.test(st.headlineHtml), st.headlineHtml);
    c.check('fa: Activate label in Persian', st.buttons[0].text === FA.upgActivateFreePlus, st.buttons[0].text);
    const lay = await page.evaluate(() => {
      const bs = Array.from(document.querySelectorAll('#save-result-foot-efb .efb-dlg-btn')).map((b) => b.getBoundingClientRect());
      const foot = document.getElementById('save-result-foot-efb').getBoundingClientRect();
      return {
        dir: getComputedStyle(document.getElementById('efbUpgHeadEfb')).direction,
        stacked: bs.every((b, i) => i === 0 || b.top >= bs[i - 1].bottom - 1),
        full: bs.every((b) => b.width >= foot.width - 40),
        tall: bs.every((b) => b.height >= 44),
      };
    });
    const ov = await page.evaluate(noOverflow);
    c.check('fa 375px: direction rtl', lay.dir === 'rtl', lay);
    c.check('fa 375px: buttons stacked, full width, at least 44 px tall', lay.stacked && lay.full && lay.tall, lay);
    c.check('fa 375px: the dialog fits the viewport, nothing inside scrolls sideways, the page does not grow', ov.inViewport && ov.inner && !ov.pageGrew, ov);
    await page.screenshot({ path: path.join(SHOTS, 'f18-s1-fa-375.png') });
  });
  await scenario('en 375px', { pro: 0, packageType: 2, width: 375, height: 740 }, async (page) => {
    await page.evaluate(() => { window.__baseW = document.documentElement.scrollWidth; });
    await open(page, { feature: 'setting:thank_you_redirect' }); await page.waitForTimeout(300);
    const ov = await page.evaluate(noOverflow);
    const hd = await page.evaluate(() => document.getElementById('efbUpgHeadEfb').textContent);
    c.check('en 375px: the dialog fits, no sideways scroll; headline "Redirect page is included in Free Plus — free"', ov.inViewport && ov.inner && !ov.pageGrew && hd === 'Redirect page is included in Free Plus — free', { ov, hd });
    await page.screenshot({ path: path.join(SHOTS, 'f18-s1-en-375.png') });
  });

  c.done();
})();
