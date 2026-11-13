/**
 * PAY00 readiness in the browser (Chromium; the notice markup from the real
 * Payments\Readiness::public_notice(), core-efb.js's own efb_pay_blocked_efb / efb_pay_block_send_efb,
 * the builder's efb_pay_readiness_note_efb (admin-efb.js) and the forms list's efb_pay_badge_efb
 * (list_form-efb.js), each taken from its file):
 *  1 a visitor of a form that is not ready reads one plain sentence in place of the payment field,
 *    no detail and no link; the form's Send is shown disabled (aria-disabled) and a click on it moves
 *    the visitor to the notice (focus) instead of sending;
 *  2 a user who can fix the form also reads the issues with their links;
 *  3 Persian and Arabic pages (right to left) at 375 px: the notice and its list run right to left,
 *    nothing overflows (screenshots pay-readiness-<lang>-375.png);
 *  4 the "Saved" dialog note: "Saved, but visitors cannot pay yet:" with each issue and its fix
 *    (a button that opens the payment field, a link to the gateway settings); nothing when ready;
 *  5 the forms list badge "Payment setup incomplete" with what is missing in its title (quotes in
 *    the messages are escaped); nothing for a form that is ready.
 * Before this change the notice had no data-efb-pay-blocked, the page kept Send active, and none of
 * these functions existed.
 *
 * Run: node tests/test-pay-readiness-browser.js   (EFB_CHROMIUM picks a browser build)
 */
'use strict';
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

let passed = 0, failed = 0;
const t = (name, ok, info) => { if (ok) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + (info !== undefined ? ': ' + JSON.stringify(info) : '')); } };
async function launch() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) { const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell'); if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe }); }
    throw e;
  }
}
const ROOT = path.join(__dirname, '..');
const JS = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8');
const OUT = process.env.EFB_TEST_OUT || os.tmpdir();
const ORIGIN = 'https://site.example';

/** A top-level function (or a block between two markers) of a script, as source. */
function cut(src, start, end) {
  const a = src.indexOf(start);
  if (a === -1) throw new Error('not found: ' + start);
  const b = src.indexOf(end, a);
  if (b === -1) throw new Error('end not found: ' + end);
  return src.slice(a, b);
}
const CORE = JS('public/assets/js/core-efb.js');
const PAGE_FNS = cut(CORE, 'function efb_pay_blocked_efb(', 'async function btn_navigate_handle_efb(');
const ADMIN = JS('includes/admin/assets/js/admin-efb.js');
const NOTE_FNS = cut(ADMIN, 'function efb_pay_readiness_note_efb(', '/* A5: the server saved the form');
const LIST = JS('includes/admin/assets/js/list_form-efb.js');
const BADGE_FN = cut(LIST, 'function efb_pay_badge_efb(', 'function fun_emsFormBuilder_render_view(');

// The notice the server draws (a form whose gateway has no keys), for a visitor and for the owner.
const PHP = `define('ABSPATH', getcwd() . '/tests/'); $passed = 0; $failed = 0; require 'tests/pay-test-db.php';
$GLOBALS['efb_can'] = false;
function current_user_can($c) { return !empty($GLOBALS['efb_can']); }
function admin_url($p = '') { return 'https://site.example/wp-admin/' . $p; }
$db = efb_pay_env();
$GLOBALS['opts'][\\Emsfb\\Payments\\Settings::option_name('efbmock')] = array();
$form = efb_pay_form();
$r = \\Emsfb\\Payments\\Readiness::of_form($form, 9);
$out = array('visitor' => \\Emsfb\\Payments\\Readiness::public_notice($r, 'pf', 9), 'answer' => \\Emsfb\\Payments\\Readiness::save_answer(array('success' => true), $form, 9));
$GLOBALS['efb_can'] = true;
$out['admin'] = \\Emsfb\\Payments\\Readiness::public_notice($r, 'pf', 9);
echo json_encode($out);`;
const SRV = JSON.parse(execFileSync('php', ['-r', PHP], { cwd: ROOT }).toString());

const pageHtml = (dir, lang, notice) => `<!doctype html><html dir="${dir}" lang="${lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<style>${JS('includes/admin/assets/css/style-efb.css')}</style>${dir === 'rtl' ? '<style>' + JS('includes/admin/assets/css/admin-rtl-efb.css') + '</style>' : ''}<style>${JS('includes/admin/assets/css/bootstrap.min-efb.css')}</style><style>${JS('public/assets/css/style-efb.css')}</style></head>
<body><div id="body_efb_9" class="efb" data-steps="1" data-currentstep="1"><fieldset data-step="step-1-efb" class="efb">
<div class="efb row my-1"><label class="efb">${lang === 'fa' ? 'نام و نام خانوادگی' : (lang === 'ar' ? 'الاسم الكامل' : 'Full name')}</label><input class="efb form-control" type="text"></div>
<div class="efb my-1" id="pf"><div class="efb px-0 mx-0" id="pf-f">${notice}</div></div></fieldset>
<div class="efb d-flex" id="f_btn_send_efb"><a id="btn_send_efb" role="button" tabindex="0" class="efb btn btn-primary">${lang === 'fa' ? 'ارسال' : (lang === 'ar' ? 'إرسال' : 'Send')}</a></div></div></body></html>`;

(async () => {
  const browser = await launch();
  const page = await browser.newPage();
  let created = 0;
  let html = pageHtml('ltr', 'en', SRV.visitor);
  await page.route('**/*', (route) => {
    const u = route.request().url();
    if (/payments\/create|get_form_Emsfb|forms\/message\/add/.test(u)) { created++; return route.fulfill({ status: 500, body: '' }); }
    if (u.startsWith(ORIGIN)) return route.fulfill({ contentType: 'text/html', body: html });
    return route.fulfill({ status: 404, body: '' });
  });
  const load = async (h) => {
    html = h;
    await page.goto(ORIGIN + '/');
    await page.addScriptTag({ content: PAGE_FNS + '\nefb_pay_block_send_efb(document);\n' + NOTE_FNS + '\n' + BADGE_FN });
  };

  /* 1 visitor */
  await page.setViewportSize({ width: 1280, height: 800 });
  await load(pageHtml('ltr', 'en', SRV.visitor));
  const v = await page.evaluate(() => {
    const n = document.querySelector('[data-efb-pay-blocked="1"]');
    const b = document.getElementById('btn_send_efb');
    return { text: n.textContent.trim(), role: n.getAttribute('role'), admin: !!n.querySelector('[data-efb-pay-admin]'), links: n.querySelectorAll('a').length, pay: !!document.querySelector('[data-pf-pay]'), aria: b.getAttribute('aria-disabled'), cls: b.className, title: b.title };
  });
  t('visitor: one plain sentence in place of the payment field (role alert), no detail, no link, no Pay button', v.text === 'Online payment for this form is not available right now. Please try again later or contact the site.' && v.role === 'alert' && !v.admin && v.links === 0 && !v.pay, v);
  t('...the form\'s Send is shown disabled (aria-disabled, disabled class, the sentence as its title)', v.aria === 'true' && /\bdisabled\b/.test(v.cls) && v.title === v.text, v);
  const blocked = await page.evaluate(() => { const n = efb_pay_blocked_efb('9'); return { found: !!n, focus: document.activeElement === n, tab: n && n.getAttribute('tabindex') }; });
  t('...pressing Send finds the notice and moves the focus to it (the send handler stops there)', blocked.found && blocked.focus && blocked.tab === '-1', blocked);
  t('...nothing was sent', created === 0, created);
  const none = await page.evaluate(() => { document.body.innerHTML = '<div id="body_efb_5"><a id="btn_send_efb">Send</a></div>'; efb_pay_block_send_efb(document); return { found: efb_pay_blocked_efb('5'), aria: document.getElementById('btn_send_efb').getAttribute('aria-disabled') }; });
  t('a form that is ready (no notice): Send untouched, the guard lets it send', none.found === null && none.aria === null, none);

  /* 2 owner */
  await load(pageHtml('ltr', 'en', SRV.admin));
  const a = await page.evaluate(() => {
    const n = document.querySelector('[data-efb-pay-blocked="1"]');
    const box = n.querySelector('[data-efb-pay-admin="1"]');
    return { box: !!box, items: box ? Array.from(box.querySelectorAll('li')).map((li) => li.getAttribute('data-code')) : [], href: box && box.querySelector('a') ? box.querySelector('a').getAttribute('href') : '', text: box ? box.textContent : '' };
  });
  t('owner: "Only you see this" with each blocking issue and its fix link (gateway settings)', a.box && a.items.join() === 'pay_not_configured' && /page=Emsfb_payments&tab=gateways/.test(a.href) && /Only you see this/.test(a.text) && /no keys/.test(a.text), a);
  await page.screenshot({ path: path.join(OUT, 'pay-readiness-en-1280.png') });

  /* 3 right to left at 375 px */
  for (const lang of ['fa', 'ar']) {
    await page.setViewportSize({ width: 375, height: 800 });
    await load(pageHtml('rtl', lang, SRV.admin));
    const r = await page.evaluate(() => {
      const n = document.querySelector('[data-efb-pay-blocked="1"]');
      const ul = n.querySelector('ul');
      const li = n.querySelector('li');
      const nb = n.getBoundingClientRect();
      const lb = li.getBoundingClientRect();
      return { dir: getComputedStyle(n).direction, ulDir: getComputedStyle(ul).direction, overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1, inside: nb.left >= 0 && nb.right <= window.innerWidth + 0.5, bulletRight: parseFloat(getComputedStyle(ul).paddingRight) > parseFloat(getComputedStyle(ul).paddingLeft), plain: getComputedStyle(n.querySelector('p')).unicodeBidi, aria: document.getElementById('btn_send_efb').getAttribute('aria-disabled') };
    });
    t(`${lang} right to left at 375 px: the notice and its list run right to left (bullets on the right), each sentence keeps its own direction (unicode-bidi plaintext: an English sentence keeps its full stop at the end), inside the screen, no horizontal scroll, Send disabled`, r.dir === 'rtl' && r.ulDir === 'rtl' && !r.overflow && r.inside && r.bulletRight && r.plain === 'plaintext' && r.aria === 'true', r);
    await page.screenshot({ path: path.join(OUT, `pay-readiness-${lang}-375.png`), fullPage: true });
  }

  /* 4 the "Saved" dialog note */
  await page.setViewportSize({ width: 1280, height: 800 });
  await load(pageHtml('ltr', 'en', ''));
  const note = await page.evaluate((ans) => {
    window.efb_var = { pay: { labels: {} } };
    const h = efb_pay_readiness_note_efb(ans);
    const ok = efb_pay_readiness_note_efb({ payment_ready: true, payment_issues: [] });
    const none = efb_pay_readiness_note_efb({ success: true });
    const fieldIssue = efb_pay_readiness_note_efb({ payment_ready: false, payment_title: 'Saved, but visitors cannot pay yet:', payment_issues: [{ code: 'pay_option_required', m: 'X requires "Payer e-mail". <b>Add</b> it.', field_id: 'pf', fix: { kind: 'field', label: 'Fix it in the payment field', url: '' } }] });
    document.body.innerHTML = h + fieldIssue;
    const n = document.getElementById('efbPayReadyNote');
    const btn = document.querySelector('[data-efb-pay-fix-field="pf"]');
    let opened = null;
    window.efbBuilderApi = { openSettings: (id) => { opened = id; } };
    window.state_modal_show_efb = () => {};
    if (btn) btn.click();
    return { title: n ? n.querySelector('strong').textContent : '', items: n ? Array.from(n.querySelectorAll('li')).map((l) => l.getAttribute('data-code')) : [], link: n && n.querySelector('a') ? n.querySelector('a').getAttribute('href') : '', ok, none, opened, bold: document.querySelectorAll('#efbPayReadyNote b').length, role: n ? n.getAttribute('role') : '' };
  }, SRV.answer);
  t('"Saved" dialog: "Saved, but visitors cannot pay yet:" with the issue and the gateway settings link (role alert)', note.title === 'Saved, but visitors cannot pay yet:' && note.items.join() === 'pay_not_configured' && /tab=gateways/.test(note.link) && note.role === 'alert', note);
  t('...an issue of the field has a button that opens the payment field\'s settings; the message is text, not markup', note.opened === 'pf' && note.bold === 0, note);
  t('...nothing when the form is ready or has no payment field', note.ok === '' && note.none === '', note);

  /* 5 the forms list badge */
  const badge = await page.evaluate(() => {
    window.ajax_object_efm = { pay_not_ready: { 12: { name: 'Shop', issues: [{ code: 'pay_option_required', m: 'Tap requires "Payer e-mail".' }, { code: 'pay_not_configured', m: 'No keys.' }] } }, pay_badge: { label: 'Payment setup incomplete', hint: 'Visitors cannot pay with this form yet.' } };
    const h = efb_pay_badge_efb(12);
    document.body.innerHTML = '<table><tr><td>Shop' + h + '</td></tr></table>';
    const b = document.querySelector('[data-efb-pay-notready="12"]');
    return { text: b ? b.textContent.trim() : '', title: b ? b.getAttribute('title') : '', other: efb_pay_badge_efb(13) };
  });
  t('forms list: "Payment setup incomplete" badge with what is missing in its title (quotes kept as text)', badge.text === 'Payment setup incomplete' && badge.title.indexOf('Tap requires "Payer e-mail".') !== -1 && /No keys/.test(badge.title), badge);
  t('...no badge for a form that is ready', badge.other === '', badge.other);

  await browser.close();
  console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
