/**
 * Batch B7 of the 4.3.2 backlog (lifecycle and the Settings screen) on the real site, in Chromium, as the owner.
 *
 *  b7-uninstall  LIFE-01: Settings › Privacy › "Delete all data when the plugin is deleted": off by default; ticking it
 *                shows the warning; Save stores prv_delete_on_uninstall = '1' through the settings save (set_setting_Emsfb)
 *                and uninstall.php would read it as on (Lifecycle::wants_data_removal()); the page shows it ticked after
 *                a reload; unticking and Save store '' again. fa_IR at 375 px: no sideways scroll.
 *  b7-plans      FR-01: the first-run plan chooser: three cards, one badge (Recommended on Free Plus), Free Plus's
 *                locked line is the Pro add-ons, payments are free add-ons there, no "Most Popular" or "premium
 *                experience"; the Close button has a name; axe finds no serious or critical issue in the chooser.
 *  b7-search     BUG-03 row 389: the global search shows an entry's date in the site's format (as the entries screen,
 *                Entries::display_date()), in Persian digits on fa_IR; a partial code finds the entry (the UNION).
 *
 * Wired in tests/e2e-wp/run.js by B6.
 */
'use strict';
const path = require('path');

module.exports = function (S) {
  const { scenario, R, ctl, sleep, SHOT } = S;
  const ev = (code) => { const out = S.wp('eval', code).trim().split('\n'); return out[out.length - 1]; };
  const setting = () => ev("echo json_encode(array('v' => \\Emsfb\\Privacy::setting('prv_delete_on_uninstall'), 'lc' => (require_once WP_PLUGIN_DIR . '/easy-form-builder/includes/class-Emsfb-lifecycle.php') && \\Emsfb\\Lifecycle::wants_data_removal()));");
  const reset = () => ev("efbFunction::set_setting_Emsfb((object) array('prv_delete_on_uninstall' => '')); delete_transient('emsfb_settings_transient'); echo 'ok';");

  async function openPrivacy(page) {
    await page.goto(S.CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=privacy', { waitUntil: 'load' });
    await page.waitForSelector('#prvDeleteOnUninstall_efb', { timeout: 30000 });
    await page.evaluate(() => { const t = document.getElementById('nav-privacy-tab'); if (t) t.click(); });
    await sleep(400);
  }
  async function save(page) {
    const resp = page.waitForResponse((r) => r.url().includes('admin-ajax.php') && /action=set_settings_Emsfb|set_settings_Emsfb/.test(r.request().postData() || ''), { timeout: 30000 });
    await page.evaluate(() => { const b = document.getElementById('save-stng-efb'); b.scrollIntoView(); b.click(); });
    const r = await resp;
    await sleep(800);
    return r.status();
  }

  scenario('b7-uninstall', 'LIFE-01: "Delete all data when the plugin is deleted" is off by default, saved on and off through the settings save', async ({ adminPage }) => {
    reset();
    try {
      const page = await adminPage();
      await openPrivacy(page);
      const st0 = await page.evaluate(() => ({ checked: document.getElementById('prvDeleteOnUninstall_efb').checked, warn: document.getElementById('prvUninstallWarn_efb').hidden, legend: document.querySelector('#prvUninstallSet_efb legend').textContent.trim() }));
      R.check('b7-uninstall: the switch is off by default, no warning', st0.checked === false && st0.warn === true && st0.legend === 'When the plugin is deleted', st0);
      R.check('b7-uninstall: the stored setting is \'\' and uninstall.php would keep the data', JSON.parse(setting()).v === '' && JSON.parse(setting()).lc === false, setting());
      await page.check('#prvDeleteOnUninstall_efb');
      R.check('b7-uninstall: ticking it shows the warning', await page.evaluate(() => document.getElementById('prvUninstallWarn_efb').hidden === false));
      R.check('b7-uninstall: Save answers', (await save(page)) === 200);
      const on = JSON.parse(setting());
      R.check('b7-uninstall: stored as \'1\' (set_setting_Emsfb), and uninstall.php reads it as on', on.v === '1' && on.lc === true, on);
      await openPrivacy(page);
      R.check('b7-uninstall: after a reload the box is ticked and the warning shown', await page.evaluate(() => document.getElementById('prvDeleteOnUninstall_efb').checked && !document.getElementById('prvUninstallWarn_efb').hidden));
      await page.uncheck('#prvDeleteOnUninstall_efb');
      await save(page);
      const off = JSON.parse(setting());
      R.check('b7-uninstall: unticked and saved: \'\' again, the data would be kept', off.v === '' && off.lc === false, off);
    } finally { reset(); }
    ctl('locale', 'fa_IR');
    try {
      const page = await adminPage({ viewport: { width: 375, height: 812 }, isMobile: true, hasTouch: true }, 'admin-fa');
      await openPrivacy(page);
      await page.check('#prvDeleteOnUninstall_efb');
      await page.locator('#prvUninstallSet_efb').scrollIntoViewIfNeeded();
      R.check('b7-uninstall (fa_IR 375): no sideways scroll', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
      await page.screenshot({ path: SHOT('b7-uninstall-fa_IR-375') });
    } finally { ctl('locale', ''); }
  });

  scenario('b7-plans', 'FR-01: the plan chooser says what each plan gets, one badge, accessible', async ({ adminPage }) => {
    const page = await adminPage();
    await page.goto(S.CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await page.waitForFunction(() => typeof showSetupAsOverlayPage === 'function', null, { timeout: 20000 });
    await page.evaluate(() => showSetupAsOverlayPage({}));
    await page.waitForSelector('#efb-setup-overlay .efb-plan-card', { timeout: 10000 });
    await sleep(1000);
    const info = await page.evaluate(() => {
      const o = document.getElementById('efb-setup-overlay');
      const plus = o.querySelector('.efb-recommended');
      return { cards: o.querySelectorAll('.efb-plan-card').length, badges: o.querySelectorAll('.efb-recommended-badge, .efb-pro-badge').length,
        plusLocked: [...plus.querySelectorAll('.efb-feature-locked')].map((x) => x.textContent.replace(/\s+/g, ' ').trim()),
        text: o.innerText, close: (o.querySelector('.efb-overlay-close') || {}).getAttribute ? o.querySelector('.efb-overlay-close').getAttribute('aria-label') : null };
    });
    R.check('b7-plans: three cards, one badge (Recommended on Free Plus)', info.cards === 3 && info.badges === 1, info.badges);
    R.check('b7-plans: Free Plus\'s only locked line is the Pro add-ons', JSON.stringify(info.plusLocked) === JSON.stringify(['Not included: Pro add-ons: SMS, Telegram, Google Sheets, booking and more']), info.plusLocked);
    R.check('b7-plans: payments are Free Plus\'s free add-ons; the credit link said plainly', info.text.includes('Payments: Stripe, PayPal and other gateways (free add-ons)') && info.text.includes('Shows a small “Powered by Easy Form Builder” link'));
    R.check('b7-plans: no "Most Popular", "premium experience" or "credit line"', !/Most Popular|premium experience|credit line/i.test(info.text));
    R.check('b7-plans: the Close button has a name', !!info.close, info.close);
    await page.addScriptTag({ path: path.join(__dirname, '..', '..', 'node_modules', 'axe-core', 'axe.min.js') });
    const v = await page.evaluate(async () => { const r = await axe.run(document.getElementById('efb-setup-overlay'), { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] }, resultTypes: ['violations'] }); return r.violations.filter((x) => x.impact === 'serious' || x.impact === 'critical').map((x) => x.id + '×' + x.nodes.length); });
    R.check('b7-plans: axe finds no serious or critical issue in the chooser', v.length === 0, v);
    await page.screenshot({ path: SHOT('b7-plans-en-1280') });
  });

  scenario('b7-search', 'BUG-03 row 389: the global search shows dates as the entries list does; a partial code finds the entry', async ({ adminPage }) => {
    const rowOf = () => JSON.parse(ev("global $wpdb; $r = $wpdb->get_row(\"SELECT track, date FROM {$wpdb->prefix}emsfb_msg_ WHERE track <> '' ORDER BY msg_id DESC LIMIT 1\", ARRAY_A); echo json_encode(array('track' => $r['track'], 'disp' => \\Emsfb\\Entries::display_date($r['date']), 'raw' => $r['date']));"));
    for (const [loc, w] of [['', 1280], ['fa_IR', 375]]) {
      if (loc) ctl('locale', loc);
      try {
        const row = rowOf(); // in the site's language (month names), as the entries screen shows it
        const page = await adminPage({ viewport: { width: w, height: w < 600 ? 812 : 900 } }, 'admin-' + (loc || 'en'));
        await page.goto(S.CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
        await page.waitForFunction(() => typeof search_comprehensive_efb === 'function', null, { timeout: 20000 });
        for (const term of [row.track, row.track.slice(0, Math.max(4, row.track.length - 3))]) {
          await page.evaluate((t) => search_comprehensive_efb(t), term);
          await page.waitForSelector('#emsFormBuilder-list td[data-label] bdi', { timeout: 20000 });
          await sleep(400);
          const dates = await page.evaluate(() => [...document.querySelectorAll('#emsFormBuilder-list td[data-label] bdi')].map((x) => x.textContent));
          const want = loc === 'fa_IR' ? row.disp.replace(/[0-9]/g, (d) => String.fromCharCode(0x06F0 + Number(d))) : row.disp;
          R.check(`b7-search (${loc || 'en'} ${w}, "${term}"): the entry's date reads "${want}" (stored ${row.raw})`, dates.includes(want), dates.slice(0, 3));
        }
      } finally { if (loc) ctl('locale', ''); }
    }
  });
};
