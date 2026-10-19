/**
 * Real browser: a submission never depends on forms/session/prepare.
 *
 * The route is answered in the browser (page.route) with every failure a site
 * can produce - offline, 429, a WAF's HTML 403, a REST allow-list's 404/401,
 * efb_refresh_origin_denied, 503 and a malformed 200 - and each time the form
 * must still reach forms/message/add with the page nonce and sid and no
 * runtime_token/request_id (the request 4.3.0 sent), get HTTP 200 and show
 * the confirmation code. Refusals a retry would not change (login required,
 * context expired) must still stop with their message and send nothing. With
 * EFB_USER/EFB_PASS a logged-in visitor is checked the same way, and with
 * EFB_TRACKER_PATH the tracker lookup and a response box reply go out while
 * prepare is throttled.
 *
 * Needs a published page holding one simple guest form (text fields, no
 * captcha, tracking code on). Each passing case stores one real entry (and
 * the tracker case one reply).
 *
 * Run: EFB_BASE=http://127.0.0.1:8080 EFB_FORM_PATH=/fallback/ \
 *      [EFB_TRACKER_PATH=/tracker/] [EFB_USER=member EFB_PASS=secret] \
 *      [EFB_CHROMIUM=/path/to/chromium] node tests/test-prepare-fallback-browser.js
 */
const { chromium } = require('playwright');

const BASE = (process.env.EFB_BASE || 'http://127.0.0.1/wp').replace(/\/$/, '');
const FORM = process.env.EFB_FORM_PATH || '/';
const TRACKER = process.env.EFB_TRACKER_PATH || '';
const USER = process.env.EFB_USER || '', PASS = process.env.EFB_PASS || '';
const reply = (status, body) => ({ status, contentType: 'application/json', body: JSON.stringify(body) });
const MODES = {
  normal: null,
  offline: 'abort',
  r429: reply(429, { code: 'rest_too_many_requests', message: 'Too many requests.', data: { status: 429 } }),
  waf403: { status: 403, contentType: 'text/html', body: '<html><body>Access denied</body></html>' },
  noroute: reply(404, { code: 'rest_no_route', message: 'No route was found.', data: { status: 404 } }),
  rest401: reply(401, { code: 'rest_not_logged_in', message: 'REST API restricted.', data: { status: 401 } }),
  origin: reply(403, { code: 'efb_refresh_origin_denied', message: 'Token refresh requires a same-origin request.', data: { status: 403 } }),
  s503: reply(503, { code: 'efb_session_storage_failed', message: 'The form session could not be saved.', data: { status: 503 } }),
  malformed: reply(200, { nonce: 'incomplete' }),
  login: reply(403, { code: 'efb_login_required', message: 'Please log in to use this form.', data: { status: 403 } }),
  expired: reply(409, { code: 'efb_context_expired', message: 'The form context expired. Reopen the form before continuing.', data: { status: 409 } }),
};

let pass = 0, fail = 0;
function check(ok, label, detail) {
  if (ok) pass++; else fail++;
  console.log(`  [${ok ? 'PASS' : 'FAIL'}] ${label}${ok ? '' : ' — ' + JSON.stringify(detail)}`);
}

async function newContext(browser, user) {
  const context = await browser.newContext();
  // Only the site itself: unreachable third-party assets would stall page loads.
  await context.route(url => !url.href.startsWith(BASE), route => route.abort());
  if (user) {
    const page = await context.newPage();
    await page.goto(BASE + '/wp-login.php');
    await page.fill('#user_login', USER); await page.fill('#user_pass', PASS);
    await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
    await page.close();
  }
  return context;
}

async function submit(browser, mode, user) {
  const context = await newContext(browser, user);
  const page = await context.newPage();
  const errors = [], submits = [];
  let prepares = 0;
  page.on('pageerror', e => errors.push(e.message));
  if (MODES[mode]) await page.route('**/forms/session/prepare', route => MODES[mode] === 'abort' ? route.abort('failed') : route.fulfill(MODES[mode]));
  page.on('request', r => { if (r.url().includes('forms/session/prepare')) prepares++; });
  page.on('response', async r => {
    if (!r.url().includes('forms/message/add')) return;
    const sent = JSON.parse(r.request().postData() || '{}');
    let data = null; try { data = (await r.json()).data; } catch (e) {}
    submits.push({ status: r.status(), nonce: r.request().headers()['x-wp-nonce'], runtime: 'runtime_token' in sent, request_id: 'request_id' in sent, sid: sent.sid, success: !!(data && data.success), track: data && data.track });
  });
  await page.goto(BASE + FORM, { waitUntil: 'domcontentloaded' });
  await page.waitForSelector('#btn_send_efb', { timeout: 20000 });
  await page.waitForTimeout(500);
  const page_state = await page.evaluate(() => ({
    nonce: window.efb_var && efb_var.nonce,
    sid: (typeof valj_efb_new !== 'undefined' && valj_efb_new[0] && valj_efb_new[0].sid) || (window.efb_var && efb_var.sid) || '',
  }));
  for (const input of await page.$$('.emsFormBuilder_v')) {
    if (await input.isVisible().catch(() => false)) { await input.click(); await input.fill('prepare fallback ' + mode); }
  }
  await page.click('#btn_send_efb');
  await page.waitForTimeout(4500);
  const text = await page.evaluate(() => document.body.innerText);
  await context.close();
  const s = submits[0] || {};
  return { submits: submits.length, prepares, status: s.status, success: s.success, runtime: s.runtime, request_id: s.request_id, pageNonce: s.nonce === page_state.nonce, pageSid: s.sid === page_state.sid, shown: !!(s.track && text.includes(s.track)), track: s.track, errors, text: text.replace(/\s+/g, ' ').slice(0, 160) };
}

(async () => {
  // EFB_CHROMIUM points at a system Chromium when Playwright's bundled browser is not installed.
  const browser = await chromium.launch(process.env.EFB_CHROMIUM ? { headless: true, executablePath: process.env.EFB_CHROMIUM } : { headless: true });
  let track = '';
  for (const user of USER ? ['', USER] : ['']) {
    const who = user ? 'logged-in' : 'guest';
    let r = await submit(browser, 'normal', user);
    check(r.status === 200 && r.success && r.runtime && r.request_id && r.shown && !r.errors.length, `${who}: live runtime submission`, r);
    track = track || r.track;
    for (const mode of ['offline', 'r429', 'waf403', 'noroute', 'rest401', 'origin', 's503', 'malformed']) {
      r = await submit(browser, mode, user);
      check(r.status === 200 && r.success && r.pageNonce && r.pageSid && r.runtime === false && r.request_id === false && r.shown && r.prepares === 1 && !r.errors.length,
        `${who}: prepare ${mode} -> legacy request, saved, confirmation shown, one prepare call`, r);
    }
    for (const [mode, message] of [['login', 'Please log in to use this form.'], ['expired', 'The form context expired']]) {
      r = await submit(browser, mode, user);
      check(r.submits === 0 && r.text.includes(message) && !r.errors.length, `${who}: prepare ${mode} still stops with its message`, r);
    }
  }
  if (TRACKER && track) {
    const context = await newContext(browser, '');
    const page = await context.newPage();
    const lookups = [];
    await page.route('**/forms/session/prepare', route => route.fulfill(MODES.r429));
    page.on('response', async r => { if (r.url().includes('forms/response/get')) { let ok = false; try { ok = !!(await r.json()).data.success; } catch (e) {} lookups.push({ status: r.status(), ok }); } });
    await page.goto(BASE + TRACKER, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#trackingCodeEfb', { timeout: 20000 });
    await page.fill('#trackingCodeEfb', track);
    await page.click('#vaid_check_emsFormBuilder');
    await page.waitForTimeout(3500);
    check(lookups.length === 1 && lookups[0].status === 200 && lookups[0].ok, 'tracker lookup goes out while prepare is throttled', lookups);
    const replies = [];
    page.on('response', async r => { if (r.url().includes('forms/response/add')) { let ok = false; try { ok = !!(await r.json()).data.success; } catch (e) {} replies.push({ status: r.status(), ok, runtime: 'runtime_token' in JSON.parse(r.request().postData() || '{}') }); } });
    await page.waitForSelector('#efb_rich_editor', { timeout: 10000 });
    await page.click('#efb_rich_editor');
    await page.keyboard.type('Reply while prepare is throttled');
    await page.click('#replayB_emsFormBuilder');
    await page.waitForTimeout(4000);
    const shown = await page.evaluate(() => document.body.innerText.includes('Reply while prepare is throttled'));
    check(replies.length === 1 && replies[0].status === 200 && replies[0].ok && replies[0].runtime === false && shown, 'response box reply is saved while prepare is throttled', { replies, shown });
    await context.close();
  }
  await browser.close();
  console.log(`\n${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
