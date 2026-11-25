/**
 * Scenarios "qa1-*" of tests/e2e-wp/run.js: the fixes of the QA-1 report (batch FX-ADMIN, 4.3.2), in a real browser
 * on the real WordPress. Each check fails on the code before the fix.
 *
 *  qa1-settings  QA1-001: Settings › Verification has one Save: the page's Save (#save-stng-efb) stores the tab's
 *                values (they were dropped while "Saved" was shown); no second Save on the tab. QA1-011: the limits are
 *                labelled in words (no "ip hour sms"). QA1-010: a number out of its range (-1, abc, too large) in
 *                General, Captchas, Privacy and Verification is refused with its reason (aria-invalid, a message tied
 *                with aria-describedby, the tab opened and the box focused); nothing is sent and the stored value
 *                stays. The server refuses the same (invalid_<key>).
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT } = S;

  const settingsUrl = (tab) => CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting' + (tab ? '&tab=' + tab : '');
  async function openSettings(a, tab) {
    await a.goto(settingsUrl(tab), { waitUntil: 'load' });
    await a.waitForSelector('#nav-tabContent', { timeout: 20000 });
    if (tab === 'verify') await a.waitForSelector('#vrfs-SmsTtl', { timeout: 20000 });
    await sleep(600);
  }

  /* ================================================================== qa1-settings */
  scenario('qa1-settings', 'QA1-001 / QA1-010 / QA1-011: one Save on Settings › Verification; numbers out of range refused with their reason; the limits in words', async ({ adminPage }) => {
    const v0 = ctl('f02-settings').settings;
    const a = await adminPage({ viewport: { width: 1280, height: 900 } }, 'qa1-settings');
    const sent = [];
    a.on('request', (r) => { const d = r.postData() || ''; if (/admin-ajax\.php/.test(r.url()) && /action=(set_settings_Emsfb|efb_verify_settings_Emsfb)/.test(d) && !/op=get/.test(d)) sent.push(/set_settings_Emsfb/.test(d) ? 'settings' : 'verify'); });
    try {
      // QA1-001 / QA1-011
      await openSettings(a, 'verify');
      const look = await a.evaluate(() => ({
        ownSave: !!document.getElementById('vrfs-SaveBtn'),
        labels: Array.from(document.querySelectorAll('#efb-verify-body label[for^="vrfs-L_"], #efb-verify-body label[for*="DestHour"], #efb-verify-body label[for*="DestDay"]')).map((l) => l.textContent.trim()),
        keys: Array.from(document.querySelectorAll('#efb-verify-body input[id^="vrfs-L_"]')).map((i) => i.id.slice(7).replace(/_/g, ' ')),
      }));
      R.check('qa1-settings (QA1-001): the Verification tab has no Save of its own (the page\'s Save stores it)', look.ownSave === false, look.ownSave);
      R.check('qa1-settings (QA1-011): the 14 limits are labelled in words: no key with spaces ("ip hour sms"), no "SMS / destination / hour"', look.labels.length === 14 && look.labels.every((l) => !look.keys.includes(l) && !/ \/ /.test(l) && /^[A-Z]/.test(l) && l.split(' ').length >= 4), look);
      await a.screenshot({ path: SHOT('qa1-settings-verify-en-1280'), fullPage: true });
      await a.fill('#vrfs-SmsTtl', '420');
      await a.fill('#vrfs-Blocked', 'mailinator.com');
      sent.length = 0;
      // The Save reloads the page (replaceState, then a new document): wait for that document's load event.
    await Promise.all([a.waitForEvent('load', { timeout: 30000 }), a.click('#save-stng-efb')]);
      const v1 = ctl('f02-settings').settings;
      R.check('qa1-settings (QA1-001): the page\'s Save keeps the tab\'s changes: code lifetime 420, blocked mailinator.com (were 300 and empty after "Saved")', v1.sms.ttl === 420 && JSON.stringify(v1.email.blocked_domains) === '["mailinator.com"]' && sent.join(',') === 'verify,settings', { ttl: v1.sms.ttl, blocked: v1.email.blocked_domains, sent });
      await a.waitForSelector('#vrfs-SmsTtl', { timeout: 20000 });
      R.check('qa1-settings (QA1-001): after the reload the Verification tab is open again with 420', await a.evaluate(() => document.getElementById('vrfs-SmsTtl').value === '420' && document.querySelector('#nav-verify-tab').classList.contains('active')), true);

      // QA1-010: refused, never clamped.
      const stored = { formRevKeep_efb: ctl('setting', 'form_rev_keep'), spamMinTime_efb: ctl('setting', 'spam_min_time'), entTrashDays_efb: ctl('setting', 'trash_days'), prvDays_efb: ctl('setting', 'prv_days') };
      const cases = [
        ['general', 'formRevKeep_efb', ['abc', '-1', '99']],
        ['captchas', 'spamMinTime_efb', ['-4', '61']],
        ['captchas', 'rc3Threshold_efb', ['2', '-0.1']],
        ['privacy', 'entTrashDays_efb', ['-1']],
        ['privacy', 'prvDays_efb', ['-5', '4000']],
        ['verify', 'vrfs-L_ip_hour_sms', ['-3']],
        ['verify', 'vrfs-SmsLen', ['99', 'abc']],
      ];
      await openSettings(a, 'verify');
      for (const [tab, id, values] of cases) {
        for (const bad of values) {
          // From another tab: the Save opens the tab of the refused box.
          await a.click('#nav-general-tab');
          await sleep(400); // Bootstrap's fade: a person does not press Save within the same 150 ms
          const old = await a.evaluate((i) => document.getElementById(i).value, id);
          await a.evaluate(([i, v]) => { const e = document.getElementById(i); e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); }, [id, bad]);
          sent.length = 0;
          await a.click('#save-stng-efb');
          await sleep(1200);
          const st = await a.evaluate((i) => {
            const e = document.getElementById(i);
            const ids = String(e.getAttribute('aria-describedby') || '').split(/\s+/);
            const m = ids.map((x) => document.getElementById(x)).find((x) => x && x.classList.contains('efb-num-err'));
            const pane = e.closest('.tab-pane');
            return { inv: e.getAttribute('aria-invalid'), msg: m ? m.textContent : '', focus: document.activeElement === e, shown: !!pane && pane.classList.contains('active'), url: location.search };
          }, id);
          R.check(`qa1-settings (QA1-010, ${tab}): "${bad}" in ${id} is refused with its reason (aria-invalid, "Enter a … number from … to …" tied to the box), its tab opened and the box focused; nothing sent`, st.inv === 'true' && /^Enter a (whole )?number from \S+ to \S+\.$/.test(st.msg) && st.focus && st.shown && sent.length === 0 && !/save=ok/.test(st.url), Object.assign({ sent }, st));
          await a.evaluate(([i, v]) => { const e = document.getElementById(i); e.value = v; e.dispatchEvent(new Event('change', { bubbles: true })); }, [id, old]);
          const cleared = await a.evaluate((i) => { const e = document.getElementById(i); return !e.hasAttribute('aria-invalid') && !document.getElementById(i + '-numErr'); }, id);
          if (!cleared) R.check(`qa1-settings (QA1-010): ${id} is valid again once the old value is back`, false, id);
        }
      }
      await a.screenshot({ path: SHOT('qa1-settings-invalid-en-1280') });
      const after = { formRevKeep_efb: ctl('setting', 'form_rev_keep'), spamMinTime_efb: ctl('setting', 'spam_min_time'), entTrashDays_efb: ctl('setting', 'trash_days'), prvDays_efb: ctl('setting', 'prv_days') };
      const v2 = ctl('f02-settings').settings;
      R.check('qa1-settings (QA1-010): the stored values did not change (no 0, 1, 50, 4 or 3 stored from a refused value)', JSON.stringify(after) === JSON.stringify(stored) && v2.limits.ip_hour_sms === v0.limits.ip_hour_sms && v2.sms.code_length === v0.sms.code_length, { stored, after });
      // The server refuses the same (a client that skips the screen's check).
      const srv = await a.evaluate(async () => {
        const body = new URLSearchParams({ action: 'efb_verify_settings_Emsfb', nonce: _efb_core_nonce_, op: 'save', settings: JSON.stringify({ limits: { ip_hour_sms: '-3' } }) });
        const r = await fetch(ajax_object_efm.ajax_url, { method: 'POST', credentials: 'same-origin', body });
        return (await r.json()).data;
      });
      R.check('qa1-settings (QA1-010): the server answers invalid_limits_ip_hour_sms with the range', srv && srv.success === false && srv.code === 'invalid_limits_ip_hour_sms' && /1 to 10,000/.test(srv.m), srv);
      // fa_IR at 375 px: the refused number in Persian, the message in the page's direction.
      ctl('locale', 'fa_IR');
      try {
        const p = await adminPage({ viewport: { width: 375, height: 812 }, locale: 'fa-IR' }, 'qa1-settings-fa');
        await openSettings(p, 'verify');
        await p.evaluate(() => { const e = document.getElementById('vrfs-SmsTtl'); e.value = '۹۹'; });
        await p.click('#save-stng-efb');
        await sleep(800);
        const fa = await p.evaluate(() => ({ inv: document.getElementById('vrfs-SmsTtl').getAttribute('aria-invalid'), msg: (document.getElementById('vrfs-SmsTtl-numErr') || {}).textContent || '', sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
        R.check('qa1-settings (fa_IR 375): "۹۹" seconds is refused, the range in Persian digits, no sideways scroll', fa.inv === 'true' && /۱۲۰/.test(fa.msg) && /۶۰۰/.test(fa.msg) && fa.sw <= fa.cw + 1, fa);
        await p.screenshot({ path: SHOT('qa1-settings-invalid-fa_IR-375'), fullPage: false });
        await p.context().close();
      } finally { ctl('locale', ''); }
    } finally {
      ctl('f02-settings', JSON.stringify({ sms: { ttl: v0.sms.ttl }, email: { blocked_domains: (v0.email.blocked_domains || []).join(',') } }));
      await a.context().close();
    }
  });
};
