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
 *  qa1-block     QA1-007 / QA1-008 / QA1-026: the block editor in fa_IR (375 and 1280 px): the block's own words come
 *                from the translation (editorScript of block.json with its text domain), the shortcode is code (left to
 *                right), the sentence takes its own direction; its stylesheet reaches the editor once and WordPress does
 *                not warn that it was added to the iframe the wrong way. en 1280 for comparison.
 *  qa1-entries   QA1-020: reloading a form's entries (?state=show-messages&id=…&efbq=…) shows the same list with the same
 *                tab and search (it went back to the forms list). QA1-009: fa_IR / ar at 375 px the entry viewer is a
 *                sheet of the screen's width (8 px gutters) and the answers are not a small box scrolling inside it.
 *                QA1-028: the forms list, the entries list and the viewer write dates in the site's digits through one
 *                formatter (the viewer showed Latin digits).
 *  qa1-upgrade   FR re-show: a site updated from 4.3.1 whose plan was chosen and whose first-run e-mail check was still
 *                "pending" sees neither the plan picker nor the e-mail check again: the update ends the first-run guide.
 *  qa1-builder   QA1-016: on Free Plus, without the old add-ons, the Jalali / Hijri tiles add Date (advanced) with that
 *                calendar and open no upgrade dialog (fa_IR). QA1-017: the help "?" and documentation links are real
 *                links (href, Tab reaches them, a name). QA1-024: the save result's shortcode panel is inside the dialog
 *                (en 1280, fa_IR / ar 375).
 *  qa1-keyboard  QA1-002: a "Skip to the form" link after the palette search reaches the canvas; a field is a tab stop
 *                named "Edit field: …", shows its toolbar while focused and opens its settings with Enter. QA1-003: the
 *                shared dialog (the save result, the delete question) takes the focus, keeps Tab inside, closes on Esc
 *                and gives the focus back; its No / Yes are <button>s.
 *  qa1-autosave  QA1-019: with builder work kept in the browser, Settings and a form's entries show no "An auto-saved
 *                version exists" dialog (it covered their messages); the forms list still offers it.
 *  qa1-stale     QA5-002: a form open in the builder while it is saved elsewhere (the assistant's form.update, another
 *                tab): the builder's Save does not overwrite that change silently; it answers stale_hash and asks
 *                "Overwrite" or "Reload" in plain words; Overwrite then saves this version.
 */
'use strict';

module.exports = function (S) {
  const { scenario, R, ctl, wp, CFG, sleep, SHOT, openNewForm } = S;

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

  /* ================================================================== qa1-block */
  async function openBlockEditor(p, postId) {
    await p.goto(CFG.base + '/wp-admin/post.php?post=' + postId + '&action=edit', { waitUntil: 'load' });
    await p.waitForFunction(() => window.wp && wp.data && wp.data.select('core/block-editor') && wp.data.select('core/block-editor').getBlocks().length > 0, null, { timeout: 60000 });
    await p.evaluate(() => {
      try { wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false); } catch (e) { /* older editor */ }
      try { wp.data.dispatch('core/preferences').set('core', 'fixedToolbar', false); } catch (e) { /* ignore */ }
      const b = wp.data.select('core/block-editor').getBlocks().find((x) => x.name === 'easy-form-builder/form');
      if (b) wp.data.dispatch('core/block-editor').selectBlock(b.clientId);
    });
    await sleep(2500);
  }
  const canvas = (p) => p.frame({ name: 'editor-canvas' }) || p.mainFrame();

  scenario('qa1-block', 'QA1-007 / QA1-008 / QA1-026: the Easy Form Builder block in the editor speaks the site language, its shortcode reads left to right, its stylesheet is added once', async ({ adminPage }) => {
    const F = ctl('f11-form', 'QA1 block form', '', '1');
    const pg = ctl('page', String(F.form_id), 'block');
    try {
      for (const [loc, w] of [['fa_IR', 375], ['fa_IR', 1280], ['', 1280]]) {
        if (loc) ctl('locale', loc);
        try {
          const p = await adminPage({ viewport: { width: w, height: w < 600 ? 812 : 900 }, locale: loc ? loc.replace('_', '-') : 'en-US' }, 'qa1-block-' + (loc || 'en') + '-' + w);
          const warns = [];
          p.on('console', (m) => { if (/efb|easy-form-builder/i.test(m.text()) && /iframe|enqueue_block_assets|block\.json/i.test(m.text())) warns.push(m.type() + ': ' + m.text().slice(0, 200)); });
          await openBlockEditor(p, pg.id);
          const tag = `${loc || 'en'} ${w}`;
          const ui = await p.evaluate(() => ({
            t: wp.i18n.__('Form will be displayed here on the frontend.', 'easy-form-builder'),
            name: wp.i18n.__('Easy Form Builder', 'easy-form-builder'),
            handle: !!(window.wp && wp.blocks && wp.blocks.getBlockType('easy-form-builder/form')),
            styleLinks: document.querySelectorAll('link[href*="page-builders/gutenberg/editor.css"]').length,
          }));
          const fr = canvas(p);
          const blk = await fr.evaluate(() => {
            const code = document.querySelector('.wp-block-easy-form-builder-form code, [data-type="easy-form-builder/form"] code');
            const par = code ? code.parentElement.querySelector('p') : null;
            return {
              code: code ? { text: code.textContent, dir: getComputedStyle(code).direction, attr: code.getAttribute('dir') } : null,
              p: par ? { text: par.textContent, dir: getComputedStyle(par).direction } : null,
              styleLinks: document.querySelectorAll('link[href*="page-builders/gutenberg/editor.css"]').length,
              docDir: document.documentElement.dir || getComputedStyle(document.body).direction,
            };
          });
          if (loc === 'fa_IR') {
            R.check(`qa1-block (${tag}) QA1-007: the block's words are Persian (the script's translations are loaded)`, /[\u0600-\u06FF]/.test(ui.t) && /[\u0600-\u06FF]/.test(ui.name) && !!blk.p && /[\u0600-\u06FF]/.test(blk.p.text), { ui, p: blk.p });
            R.check(`qa1-block (${tag}) QA1-008: the shortcode is code, left to right ([EMS_Form_Builder id="${F.form_id}"] in order); the sentence takes its own direction (rtl)`, !!blk.code && blk.code.dir === 'ltr' && blk.code.text === `[EMS_Form_Builder id="${F.form_id}"]` && blk.p.dir === 'rtl', blk);
          } else {
            R.check(`qa1-block (${tag}): English stays English; the shortcode left to right`, ui.t === 'Form will be displayed here on the frontend.' && !!blk.code && blk.code.dir === 'ltr', { ui, blk });
          }
          R.check(`qa1-block (${tag}) QA1-026: the block's stylesheet is in the editor's canvas once (and at most once around it), and WordPress does not warn about it`, blk.styleLinks === 1 && ui.styleLinks <= 1 && warns.length === 0, { parent: ui.styleLinks, iframe: blk.styleLinks, warns });
          await p.screenshot({ path: SHOT(`qa1-block-${loc || 'en'}-${w}`) });
          await p.context().close();
        } finally { if (loc) ctl('locale', ''); }
      }
    } finally {
      try { wp('post', 'delete', String(pg.id), '--force'); } catch (e) { /* the wipe of scenario 1 removes it too */ }
      try { ctl('f12-delete-form', String(F.form_id)); } catch (e) { /* ignore */ }
    }
  });

  /* ================================================================== qa1-entries */
  async function openEntries(p, fid) {
    await p.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await p.waitForFunction(() => typeof emsFormBuilder_messages === 'function' && typeof ajax_object_efm !== 'undefined', null, { timeout: 20000 });
    await p.evaluate((id) => emsFormBuilder_messages(Number(id)), fid);
    await p.waitForSelector('#efbEnt .efb-ent-open-txt, .efb-ent-open-txt', { timeout: 20000 });
    await sleep(600);
  }
  scenario('qa1-entries', 'QA1-020 / QA1-009 / QA1-028: an entries URL survives a reload with its filters; the entry viewer is a full-width sheet on a phone; dates in the site\'s digits everywhere', async ({ adminPage }) => {
    ctl('plan', '3');
    const F = ctl('f11-form', 'QA1 entries form', '', '1');
    ctl('f13-seed', String(F.form_id), '1');
    try {
      // QA1-020
      const a = await adminPage({ viewport: { width: 1280, height: 900 } }, 'qa1-entries-en');
      await openEntries(a, F.form_id);
      await a.click('#efbEntTab-unread');
      await sleep(1200);
      const before = await a.evaluate(() => ({ url: location.search, tab: (document.querySelector('.efb-ent-tab.active') || {}).id || '' }));
      await a.reload({ waitUntil: 'load' });
      await a.waitForSelector('#efbEnt .efb-ent-tab.active, .efb-ent-tab.active', { timeout: 20000 }).catch(() => {});
      await sleep(1000);
      const after = await a.evaluate(() => ({ url: location.search, tab: (document.querySelector('.efb-ent-tab.active') || {}).id || '', table: !!document.getElementById('emsFormBuilder-list'), formsList: !!document.querySelector('.efb-act-edit') }));
      R.check('qa1-entries (QA1-020): after a reload the same form\'s entries are shown, on the same tab (Unread), with &efbq= kept; not the forms list', /state=show-messages/.test(before.url) && /efbq=/.test(before.url) && after.url === before.url && after.tab === 'efbEntTab-unread' && before.tab === 'efbEntTab-unread' && after.table && !after.formsList, { before, after });
      await a.context().close();
      // QA1-009 / QA1-028
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        try {
          const p = await adminPage({ viewport: { width: 375, height: 812 }, locale: loc.replace('_', '-'), isMobile: true, hasTouch: true }, 'qa1-entries-' + loc);
          await p.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
          await p.waitForFunction(() => typeof emsFormBuilder_messages === 'function', null, { timeout: 20000 });
          await sleep(800);
          const listDate = await p.evaluate((fid) => { const c = document.querySelector(`td.emsFormBuilder-tr[data-id="${fid}"][data-label] bdi.efb-admin-date`) || document.querySelector('bdi.efb-admin-date'); return c ? c.textContent : null; }, F.form_id);
          await p.evaluate((id) => emsFormBuilder_messages(Number(id)), F.form_id);
          await p.waitForSelector('.efb-ent-open-txt', { timeout: 20000 });
          await sleep(600);
          const rowDate = await p.evaluate(() => { const c = document.querySelector('#emsFormBuilder-list tbody bdi.efb-admin-date'); return c ? c.textContent : null; });
          await p.click('.efb-ent-open-txt >> nth=0');
          await p.waitForSelector('#settingModalEfb.show .efb-resp-messages', { timeout: 20000 });
          await sleep(1500);
          const m = await p.evaluate(() => {
            const d = document.querySelector('#settingModalEfb .modal-dialog').getBoundingClientRect();
            const box = document.querySelector('#settingModalEfb .efb-resp-messages');
            const cs = getComputedStyle(box);
            const vd = document.querySelector('#settingModalEfb .efb-msg-meta-val bdi.efb-admin-date');
            return { left: Math.round(d.left), right: Math.round(innerWidth - d.right), w: Math.round(d.width), vw: innerWidth, innerScroll: box.scrollHeight > box.clientHeight + 2 && cs.overflowY !== 'visible', maxH: cs.maxHeight, viewDate: vd ? vd.textContent : null, sw: document.documentElement.scrollWidth };
          });
          const local = loc === 'fa_IR' ? /[۰-۹]/ : /[٠-٩]/;
          R.check(`qa1-entries (${loc} 375) QA1-009: the entry viewer is a sheet of the screen's width (8 px each side, ≥ viewport − 24), the answers are not a box scrolling inside it, no sideways scroll`, m.w >= m.vw - 24 && Math.abs(m.left - m.right) <= 2 && !m.innerScroll && m.maxH === 'none' && m.sw <= m.vw + 1, m);
          R.check(`qa1-entries (${loc} 375) QA1-028: the forms list, the entries list and the viewer write dates in the site's digits, the list and the viewer the same date`, !!listDate && local.test(listDate) && !/[0-9]/.test(listDate) && !!rowDate && local.test(rowDate) && !!m.viewDate && local.test(m.viewDate) && !/[0-9]/.test(m.viewDate) && m.viewDate === rowDate, { listDate, rowDate, viewDate: m.viewDate });
          await p.screenshot({ path: SHOT(`qa1-entries-viewer-${loc}-375`) });
          await p.context().close();
        } finally { ctl('locale', ''); }
      }
    } finally {
      try { ctl('f12-delete-form', String(F.form_id)); } catch (e) { /* ignore */ }
    }
  });

  /* ================================================================== qa1-upgrade */
  scenario('qa1-upgrade', 'FR re-show: a site updated from 4.3.1 (plan chosen, e-mail check pending) sees no first-run guide again', async ({ adminPage }) => {
    const opt = (k) => { try { return wp('option', 'get', k).trim(); } catch (e) { return ''; } };
    const before = { v: opt('emsfb_version'), pend: opt('emsfb_onboarding_pending'), init: opt('emsfb_onboarding_initial_install') };
    try {
      ctl('plan', '3');
      wp('option', 'update', 'emsfb_version', '4.3.1');
      wp('option', 'update', 'emsfb_onboarding_pending', '1');
      wp('option', 'update', 'emsfb_onboarding_initial_install', '1');
      const a = await adminPage({ viewport: { width: 1280, height: 900 } }, 'qa1-upgrade');
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
      await sleep(2000);
      // The first admin load runs the update; the next screen is what the owner sees from then on.
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_create', { waitUntil: 'load' });
      await sleep(2000);
      const seen = await a.evaluate(() => ({ overlay: !!document.getElementById('efb-setup-overlay'), pending: efb_var.onboarding_pending }));
      const after = { v: opt('emsfb_version'), pend: opt('emsfb_onboarding_pending') };
      R.check('qa1-upgrade (FR): after the update from 4.3.1 the first-run guide is over: no plan picker, no e-mail check, the pending flag cleared', !seen.overlay && !seen.pending && after.pend === '0' && after.v !== '4.3.1', { seen, after });
      await a.screenshot({ path: SHOT('qa1-upgrade-create-en-1280') });
      await a.context().close();
    } finally {
      if (before.v) wp('option', 'update', 'emsfb_version', before.v);
      if (before.pend !== '') wp('option', 'update', 'emsfb_onboarding_pending', before.pend);
      if (before.init !== '') wp('option', 'update', 'emsfb_onboarding_initial_install', before.init); else { try { wp('option', 'delete', 'emsfb_onboarding_initial_install'); } catch (e) { /* ignore */ } }
    }
  });

  /* ================================================================== qa1-builder */
  scenario('qa1-builder', 'QA1-016 / QA1-017 / QA1-024: the Jalali and Hijri tiles add Date (advanced); help links are real links; the save dialog\'s shortcode panel is not clipped', async ({ adminPage }) => {
    ctl('plan', '3');
    const made = [];
    try {
      for (const [loc, w] of [['', 1280], ['fa_IR', 375], ['ar', 375]]) {
        if (loc) ctl('locale', loc);
        try {
          const a = await adminPage({ viewport: { width: w, height: w < 600 ? 812 : 900 }, locale: loc ? loc.replace('_', '-') : 'en-US' }, 'qa1-builder-' + (loc || 'en'));
          const tag = `${loc || 'en'} ${w}`;
          await openNewForm(a);
          if (loc !== 'ar') {
            // QA1-016
            for (const [tile, cal] of [['pdate', 'persian'], ['ardate', 'islamic-umalqura']]) {
              const has = await a.evaluate((t) => !!document.getElementById(t + '_b'), tile);
              if (!has) continue;
              const n = await a.evaluate(() => valj_efb.length);
              await a.evaluate((t) => { const b = document.getElementById(t + '_b'); b.scrollIntoView({ block: 'center' }); b.click(); }, tile);
              await sleep(1500);
              const r = await a.evaluate(([n0]) => ({ added: valj_efb.slice(1).filter((x) => x.type === 'advdate').map((x) => ({ cal: x.cal_default, allowed: x.cal_allowed })), n: valj_efb.length - n0, upgrade: !!document.querySelector('.efb-upg-dialog, #efbUpgradeDlg, [data-efb-upgrade-dialog]') || /Pro feature|افزونه/.test((document.querySelector('#settingModalEfb.show') || { textContent: '' }).textContent) }), [n]);
              const last = r.added[r.added.length - 1] || {};
              R.check(`qa1-builder (${tag}) QA1-016: the "${tile}" tile adds Date (advanced) with the ${cal} calendar, no upgrade dialog`, r.n >= 1 && last.cal === cal && !r.upgrade, r);
            }
          }
          // QA1-024: save, then measure the shortcode panel inside the dialog.
          if (loc === '') await a.evaluate(() => { const i = valj_efb.findIndex((x, k) => k > 0 && x.type === 'advdate'); if (i < 0) add_element_dpz_efb('text'); });
          else await a.evaluate(() => { if (valj_efb.length < 2) add_element_dpz_efb('text'); });
          await sleep(800);
          await a.evaluate(() => saveFormEfb(1));
          await a.waitForSelector('#settingModalEfb.show .efb-dlg__code-panel', { timeout: 30000 });
          await sleep(900);
          const box = await a.evaluate(() => {
            const p = document.querySelector('#settingModalEfb .efb-dlg__code-panel').getBoundingClientRect();
            const c = document.querySelector('#settingModalEfb .modal-content').getBoundingClientRect();
            return { pl: Math.round(p.left), pr: Math.round(p.right), cl: Math.round(c.left), cr: Math.round(c.right), fid: typeof form_ID_emsFormBuilder !== 'undefined' ? form_ID_emsFormBuilder : 0 };
          });
          if (box.fid) made.push(box.fid);
          R.check(`qa1-builder (${tag}) QA1-024: the shortcode panel sits inside the dialog (8 px or more from each edge), nothing clipped`, box.pl >= box.cl + 8 && box.pr <= box.cr - 8, box);
          await a.screenshot({ path: SHOT(`qa1-builder-saved-${loc || 'en'}-${w}`) });
          await a.context().close();
        } finally { if (loc) ctl('locale', ''); }
      }
      // QA1-017: Settings › SMS: the WP SMS "?" and "Learn more" are links Tab reaches, with names; Settings › Email's links too.
      const s = await adminPage({ viewport: { width: 1280, height: 900 } }, 'qa1-builder-help');
      await s.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=sms', { waitUntil: 'load' });
      await s.waitForSelector('#nav-smsconfig.active', { timeout: 20000 });
      await sleep(600);
      const h = await s.evaluate(() => {
        const q = document.querySelector('#nav-smsconfig a.efb-help-link');
        const docs = Array.from(document.querySelectorAll('#nav-email a[target="_blank"], #nav-smsconfig p a[target="_blank"]'));
        return { help: q ? { href: q.getAttribute('href'), name: q.getAttribute('aria-label'), tab: q.tabIndex, rel: q.getAttribute('rel') } : null, docs: docs.map((x) => ({ href: x.getAttribute('href'), text: x.textContent.trim() })), bare: document.querySelectorAll('#wpbody-content i[data-eventform="links"], #wpbody-content a[data-eventform="links"]:not([href])').length };
      });
      R.check('qa1-builder QA1-017: the WP SMS "?" is a link (href, Tab reaches it, rel noopener) named "Help: … (opens in a new tab)"; the doc links of Email and SMS have an href; no bare "links" icon left', !!h.help && /wp-sms/.test(h.help.href) && /^Help: .+\(opens in a new tab\)$/.test(h.help.name || '') && h.help.tab === 0 && h.docs.length >= 3 && h.docs.every((d) => /^https:\/\//.test(d.href) && d.text) && h.bare === 0, h);
      // Tab reaches it (from the SMS section's radio).
      await s.focus('#wp_sms_plugin');
      let reached = false;
      for (let i = 0; i < 4 && !reached; i++) { await s.keyboard.press('Tab'); reached = await s.evaluate(() => document.activeElement && document.activeElement.classList.contains('efb-help-link')); }
      R.check('qa1-builder QA1-017: Tab from the WP SMS choice reaches its "?"', reached, reached);
      await s.context().close();
    } finally {
      for (const id of made) { try { ctl('f12-delete-form', String(id)); } catch (e) { /* ignore */ } }
    }
  });

  /* ================================================================== qa1-keyboard */
  scenario('qa1-keyboard', 'QA1-002 / QA1-003: the builder and its dialogs with the keyboard only', async ({ adminPage }) => {
    ctl('plan', '3');
    let fid = 0;
    try {
      const a = await adminPage({ viewport: { width: 1280, height: 900 } }, 'qa1-keyboard');
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb_create', { waitUntil: 'load' });
      await a.waitForSelector('button#contact.efbCreateNewForm', { timeout: 20000 });
      await a.click('button#contact.efbCreateNewForm');
      await a.waitForFunction(() => typeof valj_efb !== 'undefined' && valj_efb.length > 2 && document.querySelector('#dropZoneEFB section[tabindex="0"]'), null, { timeout: 30000 });
      await sleep(1200);
      // QA1-002
      await a.focus('#efbPaletteSearch');
      await a.keyboard.press('Tab');
      const skip = await a.evaluate(() => ({ cls: document.activeElement.className, text: document.activeElement.textContent.trim() }));
      await a.keyboard.press('Enter');
      await sleep(300);
      // The first field after the step header.
      await a.keyboard.press('Tab');
      for (let i = 0; i < 8 && !(await a.evaluate(() => /-id$/.test(document.activeElement.dataset.id || ''))); i++) await a.keyboard.press('Tab');
      const f = await a.evaluate(() => { const e = document.activeElement; const h = e && e.querySelector('.btn-edit-holder'); return { inCanvas: !!e.closest('#dropZoneEFB'), id: e.dataset.id || '', name: e.getAttribute('aria-label') || '', toolbar: !!h && !h.classList.contains('d-none') && h.offsetParent !== null }; });
      R.check('qa1-keyboard (QA1-002): Tab after the palette search reaches "Skip to the form"; Enter moves into the form, Tab reaches a field named "Edit field: …", its toolbar shown', /efb-skip-canvas/.test(skip.cls) && !!skip.text && f.inCanvas && !!f.id && /^Edit field: \S/.test(f.name) && f.toolbar, { skip, f });
      await a.keyboard.press('Enter');
      await a.waitForFunction(() => document.getElementById('sideBoxEfb').classList.contains('show'), null, { timeout: 8000 }).catch(() => {});
      const panel = await a.evaluate(() => ({ open: document.getElementById('sideBoxEfb').classList.contains('show'), label: !!document.getElementById('labelEl') }));
      R.check('qa1-keyboard (QA1-002): Enter on the field opens its settings (the label box is there)', panel.open && panel.label, panel);
      await a.keyboard.press('Escape');
      await sleep(500);
      // Tab moves from field to field (one stop each).
      const cur = await a.evaluate(() => { const e = document.querySelector('#dropZoneEFB section[data-id$="-id"][tabindex="0"]'); e.focus(); return e.dataset.id; });
      await a.keyboard.press('Tab');
      let hops = 0, next = '';
      for (; hops < 25; hops++) { next = await a.evaluate((c) => (document.activeElement.dataset.id !== c && document.activeElement.getAttribute('aria-label')) || '', cur); if (/^Edit field:/.test(next)) break; await a.keyboard.press('Tab'); }
      R.check('qa1-keyboard (QA1-002): the next field is reached with a few Tabs (its preview and toolbar between)', /^Edit field:/.test(next) && hops < 25, { hops, next });
      // QA1-003: the save result.
      await a.keyboard.press('Control+s');
      await a.waitForSelector('#settingModalEfb.show .efb-dlg__code-panel', { timeout: 30000 });
      await sleep(400);
      fid = await a.evaluate(() => (typeof form_ID_emsFormBuilder !== 'undefined' ? Number(form_ID_emsFormBuilder) : 0));
      const inDlg = await a.evaluate(() => document.getElementById('settingModalEfb').contains(document.activeElement));
      let stays = true;
      for (let i = 0; i < 12; i++) { await a.keyboard.press('Tab'); if (!(await a.evaluate(() => document.getElementById('settingModalEfb').contains(document.activeElement)))) stays = false; }
      await a.keyboard.press('Escape');
      await sleep(500);
      const after = await a.evaluate(() => ({ open: document.getElementById('settingModalEfb').classList.contains('show'), back: document.activeElement && document.activeElement !== document.body && !!document.activeElement.closest('#wpbody-content') }));
      R.check('qa1-keyboard (QA1-003): Ctrl+S → the save dialog has the focus, Tab stays inside, Esc closes it and the focus is back on the page', inDlg && stays && !after.open && after.back, { inDlg, stays, after });
      // QA1-003: the delete question.
      // The field's own Delete in its toolbar (shown while the field has the focus), pressed with Enter.
      await a.evaluate(() => { const e = document.querySelector('#dropZoneEFB section[data-id$="-id"][tabindex="0"]'); e.focus(); const d = e.querySelector('.btn-edit-holder [onclick*="delete" i], .btn-edit-holder .btn-delete, .btn-edit-holder button:last-child'); if (d) d.focus(); });
      await a.keyboard.press('Enter');
      await a.waitForSelector('#settingModalEfb.show #modalConfirmBtnEfb', { timeout: 8000 }).catch(() => {});
      await sleep(400);
      const q = await a.evaluate(() => ({ tags: Array.from(document.querySelectorAll('#modal-footer-efb .efb-btn-cancel, #modalConfirmBtnEfb')).map((b) => b.tagName + ':' + b.textContent.trim()), focus: document.getElementById('settingModalEfb').contains(document.activeElement) }));
      await a.keyboard.press('Escape');
      await sleep(400);
      const closed = await a.evaluate(() => !document.getElementById('settingModalEfb').classList.contains('show'));
      R.check('qa1-keyboard (QA1-003, QA1-027): the delete question is two <button>s "No" / "Yes" with the focus inside; Esc closes it', q.tags.length === 2 && q.tags.every((x) => /^BUTTON:/.test(x)) && q.tags[0] === 'BUTTON:No' && q.focus && closed, { q, closed });
      await a.screenshot({ path: SHOT('qa1-keyboard-en-1280') });
      await a.context().close();
    } finally {
      if (fid) { try { ctl('f12-delete-form', String(fid)); } catch (e) { /* ignore */ } }
    }
  });

  /* ================================================================== qa1-autosave */
  scenario('qa1-autosave', 'QA1-019: the auto-save restore dialog only on the forms list and the template chooser', async ({ adminPage }) => {
    const a = await adminPage({ viewport: { width: 1280, height: 900 } }, 'qa1-autosave');
    const seed = () => a.evaluate(() => { localStorage.setItem('efb_auto_save', '1'); localStorage.setItem('efb_auto_save_valj_efb', JSON.stringify([{ type: 'form', formName: 'Draft', steps: 1 }])); localStorage.setItem('efb_auto_save_time', String(Date.now())); });
    const shown = () => a.evaluate(() => document.getElementById('settingModalEfb') && document.getElementById('settingModalEfb').classList.contains('show') && /bi-clock-history/.test(document.getElementById('settingModalEfb').innerHTML));
    try {
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb&state=setting&tab=email', { waitUntil: 'load' });
      await seed();
      const where = {};
      for (const [k, u] of [['settings', '?page=Emsfb&state=setting&tab=email'], ['help', '?page=Emsfb&state=help'], ['list', '?page=Emsfb']]) {
        await a.goto(CFG.base + '/wp-admin/admin.php' + u, { waitUntil: 'load' });
        await sleep(5000);
        where[k] = await shown();
        if (where[k]) await a.evaluate(() => state_modal_show_efb(0));
      }
      R.check('qa1-autosave (QA1-019): Settings and Help show no auto-save dialog; the forms list offers it', !where.settings && !where.help && where.list, where);
    } finally {
      await a.evaluate(() => { ['efb_auto_save', 'efb_auto_save_valj_efb', 'efb_auto_save_time'].forEach((k) => localStorage.removeItem(k)); }).catch(() => {});
      await a.context().close();
    }
  });

  /* ================================================================== qa1-stale */
  scenario('qa1-stale', 'QA5-002: the builder does not overwrite a change saved elsewhere after it opened the form', async ({ adminPage }) => {
    ctl('plan', '3');
    const F = ctl('f11-form', 'QA1 stale form', '', '1');
    const elsewhere = (label) => wp('eval', `$s = \\Emsfb\\Form_Update::stored(${F.form_id}); $r = $s['rows']; foreach ($r as $i => $x) { if ($i > 0 && isset($x['type']) && $x['type'] === 'text') { $r[$i]['name'] = '${label}'; break; } } global $wpdb; $wpdb->update($wpdb->prefix . 'emsfb_form', array('form_structer' => str_replace('"', '\\"', wp_json_encode($r, JSON_UNESCAPED_UNICODE))), array('form_id' => ${F.form_id})); wp_cache_flush(); echo 'ok';`);
    const label = () => { const rows = ctl('form', String(F.form_id)).form_structer || []; const r = rows.find((x, i) => i > 0 && x.type === 'text'); return r ? r.name : ''; };
    try {
      const a = await adminPage({ viewport: { width: 1280, height: 900 } }, 'qa1-stale');
      await a.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
      await a.waitForFunction(() => typeof fun_get_form_by_id === 'function', null, { timeout: 20000 });
      await a.evaluate((id) => fun_get_form_by_id(Number(id)), F.form_id);
      await a.waitForFunction(() => typeof valj_efb !== 'undefined' && valj_efb.length > 1 && window.efb_base_hash_efb, null, { timeout: 30000 });
      await sleep(1000);
      elsewhere('Changed by the assistant');
      await a.evaluate(() => { const r = valj_efb.find((x, i) => i > 0 && x.type === 'text'); r.name = 'Changed in this tab'; });
      await a.evaluate(() => saveFormEfb(1));
      await a.waitForSelector('#settingModalEfb.show #modalConfirmBtnEfb', { timeout: 30000 });
      await sleep(500);
      const q = await a.evaluate(() => ({ text: document.getElementById('settingModalEfb').innerText, yes: document.getElementById('modalConfirmBtnEfb').textContent.trim(), no: (document.querySelector('#modal-footer-efb .efb-btn-cancel') || {}).textContent.trim() }));
      const kept = label();
      R.check('qa5-002: the builder\'s Save is refused while the form was saved elsewhere: the other change is kept, the dialog says so and offers "Overwrite" / "Reload"', kept === 'Changed by the assistant' && /changed somewhere else/.test(q.text) && q.yes === 'Overwrite' && q.no === 'Reload', { kept, q: { yes: q.yes, no: q.no, text: q.text.slice(0, 200) } });
      await a.screenshot({ path: SHOT('qa1-stale-en-1280') });
      await a.click('#modalConfirmBtnEfb');
      await a.waitForFunction(() => document.querySelector('#settingModalEfb.show .efb-dlg__code-panel'), null, { timeout: 30000 }).catch(() => {});
      await sleep(800);
      R.check('qa5-002: Overwrite saves this tab\'s version', label() === 'Changed in this tab', label());
      // A second save from the same tab needs no question (the builder got the new hash).
      await a.evaluate(() => { state_modal_show_efb(0); const r = valj_efb.find((x, i) => i > 0 && x.type === 'text'); r.name = 'Second save'; });
      await sleep(400);
      await a.evaluate(() => saveFormEfb(1));
      await a.waitForFunction(() => document.querySelector('#settingModalEfb.show .efb-dlg__code-panel'), null, { timeout: 30000 }).catch(() => {});
      await sleep(800);
      R.check('qa5-002: the next save of the same tab goes through without a question', label() === 'Second save', label());
      await a.context().close();
    } finally {
      try { ctl('f12-delete-form', String(F.form_id)); } catch (e) { /* ignore */ }
    }
  });
};
