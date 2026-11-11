/**
 * AutoPilot A4: the plan dialog names the plan that really unlocks the click.
 *
 * pro_show_efb(1) says "Pro only"; pro_show_efb(3) says "Free Plus and Pro". The redirect after
 * submit is open on Free Plus (the gate is pro_efb), but its handler opened state 1. SMS and Telegram
 * opened state 1 and then fell through to a second message ("install the add-on"), and on Free Plus
 * they asked for an add-on that plan cannot install. The gems of Free Plus settings also opened 1.
 *
 * The real builder runs in Chromium with the plan dialogs and alert_message_efb counted. Since F18
 * core opens efb_upgrade_prompt_efb({feature}) instead of pro_show_efb(n); a call is counted as the
 * state it shows (3 = Free Plus and Pro, 1 = Pro only), read from the feature's level, and the
 * feature key is recorded too.
 * The second half is a static audit of every pro_show_efb( call site against the F18 audit table.
 *
 * Run: node tests/test-ap-a4-plan-messages.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { openBuilder, makeChecker, blankForm, ROOT } = require('./ap-builder-harness.js');
const c = makeChecker();

async function scenario(label, opts, fn) {
  const h = await openBuilder(Object.assign({ builder: true, form: blankForm() }, opts));
  await h.page.evaluate(() => {
    window.__calls = { pro: [], alert: [], feature: [] };
    pro_show_efb = (s) => { window.__calls.pro.push(s); };
    // F18: the state the dialog shows for a feature: its required level (registry, else the caller's fallback).
    window.__stateOf = (o) => ((o.required || efb_feature_required_efb(o.feature, o.fallback)) === 'free_plus' ? 3 : 1);
    efb_upgrade_prompt_efb = (o) => { window.__calls.pro.push(window.__stateOf(o)); window.__calls.feature.push(o.feature); return { shown: true }; };
    const a = alert_message_efb;
    alert_message_efb = function () { window.__calls.alert.push(String(arguments[1] || arguments[0])); };
    window.efbOpenSettingIdEfb = null;
    show_setting_window_efb('formSet');
  });
  await h.page.waitForTimeout(200);
  await fn(h.page, label);
  c.check(label + ': no page errors', h.errors.length === 0 && h.bootErrors.length === 0, h.errors.concat(h.bootErrors));
  await h.browser.close();
}
const calls = (page) => page.evaluate(() => JSON.parse(JSON.stringify(window.__calls)));
const reset = (page) => page.evaluate(() => { window.__calls = { pro: [], alert: [], feature: [] }; });
/* The state a gem's onclick opens: pro_show_efb(n), or the level of efb_upgrade_prompt_efb({feature, fallback}). */
const gemStates = (page, sel) => page.evaluate((sel) => Array.from(document.querySelectorAll(sel)).map((a) => {
  const g = a.getAttribute('onclick') || '';
  const old = g.match(/pro_show_efb\((\d)\)/);
  if (old) return Number(old[1]);
  const f = g.match(/feature:'([^']+)'/); const fb = g.match(/fallback:'([^']+)'/);
  return f ? window.__stateOf({ feature: f[1], fallback: fb ? fb[1] : undefined }) : 0;
}), sel);
const toggle = async (page, id) => {
  await page.evaluate((i) => { const b = document.getElementById(i); b.classList.add('active'); change_el_edit_Efb(b); }, id);
  await page.waitForTimeout(150);
};

(async () => {
  // Free (pro 0, package 2)
  // The Telegram toggle is drawn only when that add-on is installed (a site that was Pro before).
  await scenario('Free', { pro: 0, packageType: 2, addons: { AdnTLG: 1 } }, async (page, l) => {
    await page.evaluate(() => { const el = document.getElementById('thankYouredirectEl'); el.value = 'https://example.com/x'; el.dispatchEvent(new Event('change')); });
    await page.waitForTimeout(150);
    let r = await calls(page);
    c.check(l + ': redirect opens the Free Plus + Pro dialog (3)', r.pro.length === 1 && r.pro[0] === 3 && r.feature[0] === 'setting:thank_you_redirect', r);
    c.check(l + ': redirect is not stored', await page.evaluate(() => valj_efb[0].thank_you === 'msg' && valj_efb[0].rePage === ''));
    for (const id of ['smsEnableEl', 'telegramEnableEl']) {
      await reset(page);
      await toggle(page, id);
      r = await calls(page);
      c.check(`${l}: ${id} shows exactly one message, the Pro dialog`, r.pro.length === 1 && r.pro[0] === 1 && r.alert.length === 0, r);
      c.check(`${l}: ${id} is switched off again`, await page.evaluate((i) => !document.getElementById(i).classList.contains('active') && !(valj_efb[0].smsnoti == 1 || valj_efb[0].telegramnoti == 1), id));
    }
    const gems = await gemStates(page, '#sideMenuConEfb .pro-version-efb:not(.efb-mobile-pro-gem)');
    c.check(l + ': every Free Plus gem in the form settings opens the Free Plus + Pro dialog', gems.length >= 3 && gems.every((g) => g === 3), gems);
    const mobile = await gemStates(page, '#sideMenuConEfb .efb-mobile-pro-gem');
    c.check(l + ': the mobile-layout gem (real Pro) keeps the Pro dialog', mobile.every((g) => g === 1), mobile);
  });

  // Free Plus (pro 1, package 3), SMS / Telegram add-ons are Pro and not installed
  await scenario('Free Plus', { pro: 1, packageType: 3 }, async (page, l) => {
    for (const id of ['smsEnableEl']) {
      await reset(page);
      await toggle(page, id);
      const r = await calls(page);
      c.check(`${l}: ${id} (Pro add-on) opens the Pro dialog, not "install the add-on"`, r.pro.length === 1 && r.pro[0] === 1 && r.alert.length === 0, r);
    }
    await reset(page);
    await page.evaluate(() => { const el = document.getElementById('thankYouredirectEl'); el.value = 'https://example.com/x'; el.dispatchEvent(new Event('change')); });
    await page.waitForTimeout(150);
    const r = await calls(page);
    c.check(l + ': redirect is allowed (no dialog)', r.pro.length === 0 && (await page.evaluate(() => valj_efb[0].thank_you)) === 'rdrct', r);
  });

  // Pro (pro 1, package 1), add-on not installed: the install message, once
  await scenario('Pro without the SMS add-on', { pro: 1, packageType: 1 }, async (page, l) => {
    await toggle(page, 'smsEnableEl');
    const r = await calls(page);
    c.check(l + ': one "install the add-on" message, no plan dialog', r.pro.length === 0 && r.alert.length === 1, r);
  });

  // Static audit: every remaining pro_show_efb(1) / funProEfb() is a real Pro lock (F18 §2.3).
  const files = ['includes/admin/assets/js/admin-efb.js', 'includes/admin/assets/js/val-efb.js', 'includes/admin/assets/js/pro_els-efb.js',
    'includes/admin/assets/js/response-viewer-efb.js', 'includes/admin/assets/js/list_form-efb.js'];
  const ones = [];
  for (const f of files) {
    fs.readFileSync(path.join(ROOT, f), 'utf8').split('\n').forEach((line, i) => {
      if (/pro_show_efb\(\s*1\s*\)/.test(line) && !/^\s*(\/\/|<!--)/.test(line)) ones.push(path.basename(f) + ': ' + line.trim());
    });
  }
  const allowed = [/efb-mobile-pro-gem/, /smsEnableEl|telegramEnableEl/, /lock_email_stats/, /^admin-efb\.js: pro_show_efb\(1\);$/, /^list_form-efb\.js: pro_show_efb\(1\);$/, /efb_addon|requiredPackage|required_package/, /btn-pro-efb/, /funProEfb ?= ?\(state ?= ?1\)/];
  const unexplained = ones.filter((l) => !allowed.some((re) => re.test(l))).map((l) => l.slice(0, 110));
  c.check('every remaining pro_show_efb(1) is a real Pro lock (mobile layout, SMS, Telegram, email stats, Pro add-ons)', unexplained.length === 0, unexplained);
  const val = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/val-efb.js'), 'utf8');
  // F18: the gems name a Free Plus registry key (with fallback 'free_plus' for a page without the registry).
  c.check('Free Plus settings gems (custom required message, colours, email subject/content, redirect) use state 3',
    !/onclick="pro_show_efb\(1\)" class="efb pro-version-efb" data-bs-toggle="tooltip" data-bs-placement="top" title="' \+ \(efb_var\.text\.fieldAvailableInProversion/.test(val)
    && (val.match(/funProEfb\('setting:(email_sub|email_noti_type|thank_you_redirect)', \{ fallback: 'free_plus'/g) || []).length >= 3
    && (val.match(/feature:\\'setting:(custom_colors|custom_required_msg)\\', fallback:\\'free_plus\\'/g) || []).length === 7);
  c.done();
})();
