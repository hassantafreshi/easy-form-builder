/*
 * Authorized local integration test for EFB Human Shield.
 *
 * Uses the local XAMPP WordPress site only. It switches the add-on on through
 * its admin interface, executes guest-facing REST flows, and restores the
 * exact previous settings in finally(). No successful form is submitted.
 */
const { chromium } = require('playwright');

const BASE = 'http://127.0.0.1/wp';
const ADMIN = `${BASE}/wp-admin`;
const FORM_PAGE = `${BASE}/?p=209`; // Local test post containing form id 134.
const PROTECTED = '/wp-json/Emsfb/v1/forms/message/add';
const SHIELD = '/wp-json/EmsfbShield/v1';

let passed = 0;
let failed = 0;
const findings = [];

function check(condition, label, detail = '') {
  if (condition) {
    passed += 1;
    console.log(`PASS ${label}${detail ? ` — ${detail}` : ''}`);
  } else {
    failed += 1;
    console.log(`FAIL ${label}${detail ? ` — ${detail}` : ''}`);
  }
}

function finding(id, title, evidence) {
  findings.push({ id, title, evidence });
  console.log(`FINDING ${id}: ${title} — ${evidence}`);
}

async function rawPost(path, body, headers = {}) {
  const response = await fetch(`${BASE}${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...headers },
    body: JSON.stringify(body),
  });
  const text = await response.text();
  let json = null;
  try { json = JSON.parse(text); } catch (_) { /* reported by caller */ }
  return { status: response.status, headers: Object.fromEntries(response.headers), json, text };
}

function humanMetrics(overrides = {}) {
  return {
    durationMs: 18000,
    firstInteractionDelayMs: 800,
    focusCount: 5,
    inputCount: 6,
    keyCount: 45,
    pointerMoveCount: 30,
    pointerDistance: 900,
    touchCount: 0,
    clickCount: 3,
    scrollCount: 2,
    pasteCount: 0,
    fieldsTouched: 4,
    fieldCount: 4,
    formFieldCount: 4,
    visibilityChanges: 0,
    touchCapable: false,
    webdriver: false,
    honeypotFilled: false,
    ...overrides,
  };
}

async function mintToken(formId, sid, metrics, agent = 'EFB-HS-Test-Agent') {
  const route = '/Emsfb/v1/forms/message/add';
  const headers = { 'User-Agent': agent };
  const challenge = await rawPost(`${SHIELD}/challenge`, { formId, route, sid }, headers);
  if (!challenge.json || !challenge.json.success) return { challenge, attest: null };
  const attest = await rawPost(`${SHIELD}/attest`, {
    challengeId: challenge.json.challengeId,
    formId,
    route,
    sid,
    metrics,
  }, headers);
  return { challenge, attest };
}

async function setViaUi(page, values) {
  await page.locator('.efb-hs-tab[data-tab="protection"]').click();
  await page.waitForSelector('[data-setting="enabled"]');
  for (const [key, value] of Object.entries(values)) {
    const control = page.locator(`[data-setting="${key}"]`);
    const type = await control.getAttribute('type');
    const tag = await control.evaluate((node) => node.tagName);
    // The styled switch's decorative span overlays the native checkbox. A
    // normal human click on its containing label works; force the native state
    // here so the integration test reaches the save handler deterministically.
    if (type === 'checkbox') await control.setChecked(Boolean(value), { force: true });
    else if (tag === 'SELECT') await control.selectOption(String(value));
    else await control.fill(String(value));
  }
  const response = page.waitForResponse((res) =>
    res.url().includes('admin-ajax.php') && res.request().postData().includes('efb_human_shield_save_settings')
  );
  await page.locator('[data-action="save"]').click();
  const saved = await response;
  check(saved.status() === 200, 'Admin settings save returns HTTP 200');
  const data = await saved.json();
  check(data.success === true, 'Admin settings save succeeds');
  await page.waitForTimeout(250);
  return data;
}

async function restoreViaAdminAjax(page, settings) {
  return page.evaluate(async (original) => {
    const cfg = window.EFBHumanShieldAdmin;
    const body = new URLSearchParams({
      action: 'efb_human_shield_save_settings',
      nonce: cfg.nonce,
      settings: JSON.stringify(original),
    });
    const response = await fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body,
    });
    return response.json();
  }, settings);
}

async function patchViaAdminAjax(page, patch) {
  return page.evaluate(async (changes) => {
    const cfg = window.EFBHumanShieldAdmin;
    const settings = { ...cfg.settings, ...changes };
    const body = new URLSearchParams({
      action: 'efb_human_shield_save_settings',
      nonce: cfg.nonce,
      settings: JSON.stringify(settings),
    });
    const response = await fetch(cfg.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body,
    });
    return response.json();
  }, patch);
}

(async () => {
  const browser = await chromium.launch({
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  });
  const adminContext = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const adminPage = await adminContext.newPage();
  const browserErrors = [];
  adminPage.on('console', (message) => {
    if (message.type() === 'error') browserErrors.push(message.text());
  });
  adminPage.on('pageerror', (error) => browserErrors.push(error.message));

  let originalSettings = null;
  try {
    await adminPage.goto(`${ADMIN}/`);
    if (await adminPage.locator('#user_login').count()) {
      await adminPage.fill('#user_login', 'admin');
      await adminPage.fill('#user_pass', 'admin');
      await adminPage.click('#wp-submit');
      await adminPage.waitForURL('**/wp-admin/**');
    }

    await adminPage.goto(`${ADMIN}/admin.php?page=Emsfb_human_shield_efb`);
    await adminPage.waitForLoadState('networkidle');
    await adminPage.waitForFunction(() => Boolean(window.EFBHumanShieldAdmin));
    originalSettings = await adminPage.evaluate(() => ({ ...window.EFBHumanShieldAdmin.settings }));
    check(originalSettings.enabled === 0, 'Pre-test add-on state is disabled');
    const heading = await adminPage.locator('h1').first().innerText();
    check(heading === 'Form Security & Spam Protection', 'Admin heading decodes the ampersand once', heading);

    // This is a real UI activation test, not a direct database change.
    await setViaUi(adminPage, { enabled: 1, mode: 'soft_block', submit_ip_per_minute: 10 });
    const challengeProbe = await rawPost(`${SHIELD}/challenge`, {
      formId: 134,
      route: '/Emsfb/v1/forms/message/add',
      sid: 'runtime-probe',
    });
    check(challengeProbe.status === 200 && challengeProbe.json?.code === 'session_invalid',
      'Enabled add-on rejects a challenge without a live EFB session');
    check((challengeProbe.headers['cache-control'] || '').includes('no-store'),
      'Challenge response is non-cacheable');

    // A human-like guest browser journey: wait, move, scroll and fill controls,
    // then make an invalid (therefore non-persisting) request. The public wrapper
    // must mint and attach a Human Shield token before the core REST handler.
    const guestContext = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const guestPage = await guestContext.newPage();
    const guestRequests = [];
    guestPage.on('request', (request) => {
      if (request.url().includes('/wp-json/')) {
        guestRequests.push({ url: request.url(), headers: request.headers() });
      }
    });
    await guestPage.goto(FORM_PAGE);
    await guestPage.waitForLoadState('networkidle');
    await guestPage.waitForFunction(() => Boolean(window.EFBHumanShield));
    await guestPage.mouse.move(250, 300);
    await guestPage.mouse.wheel(0, 260);
    const editable = guestPage.locator('input:not([type="hidden"]):not([data-efb-hs-honeypot]):not([disabled]):visible, textarea:not([disabled]):visible');
    const editableCount = await editable.count();
    for (let i = 0; i < Math.min(editableCount, 2); i += 1) {
      await editable.nth(i).fill(`Human test ${i + 1}`);
    }
    await guestPage.waitForTimeout(5200); // form has four fields, dynamic minimum is five seconds.
    const guestResponse = await guestPage.evaluate(async () => {
      const nonce = window.ajax_object_efm && (window.ajax_object_efm.nonce || window.ajax_object_efm._wpnonce);
      const sid = window.ajax_object_efm?.sid || window.efb_var?.sid || '';
      const response = await fetch('/wp/wp-json/Emsfb/v1/forms/message/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...(nonce ? { 'X-WP-Nonce': nonce } : {}) },
        body: JSON.stringify({ id: 134, value: [], name: 'Safe Human Test', sid }),
      });
      return { status: response.status, body: await response.text(), sid };
    });
    check(guestRequests.some((r) => r.url.includes('/EmsfbShield/v1/challenge')),
      'Real guest flow requests a challenge');
    check(guestRequests.some((r) => r.url.includes('/EmsfbShield/v1/attest')),
      'Real guest flow submits browser attestation');
    check(guestRequests.some((r) => r.url.includes('/forms/message/add') && r.headers['x-efb-human-token']),
      'Real guest flow attaches Human Shield header');
    check(guestResponse.status >= 200 && guestResponse.status < 500,
      'Invalid safe guest submission returns a controlled response', String(guestResponse.status));
    await guestContext.close();

    // Soft-block mode: no token must stop at Human Shield, before core's nonce
    // permission callback is reached.
    const missing = await rawPost(PROTECTED, { id: 134, sid: 'no-token' });
    check(missing.status === 200 && missing.json && missing.json.data && missing.json.data.code === 'efb_human_shield_blocked',
      'Soft block rejects a missing token before core endpoint');

    // Simulate a security firewall that blocks the two attestation endpoints.
    // The protected form call must fail in a controlled way (not crash or
    // bypass the server guard), making the required WAF allowlist explicit.
    const wafContext = await browser.newContext();
    const wafPage = await wafContext.newPage();
    await wafPage.route('**/wp-json/EmsfbShield/v1/**', async (route) => {
      await route.fulfill({
        status: 403,
        contentType: 'application/json',
        body: JSON.stringify({ code: 'waf_blocked', message: 'Blocked by test firewall.' }),
      });
    });
    await wafPage.goto(FORM_PAGE);
    await wafPage.waitForLoadState('networkidle');
    await wafPage.waitForFunction(() => Boolean(window.EFBHumanShield));
    const wafBlocked = await wafPage.evaluate(async () => {
      const sid = window.ajax_object_efm?.sid || window.efb_var?.sid || '';
      const response = await fetch('/wp/wp-json/Emsfb/v1/forms/message/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: 134, value: [], name: 'WAF compatibility test', sid }),
      });
      return { status: response.status, json: await response.json() };
    });
    check(wafBlocked.status === 200 && wafBlocked.json?.data?.code === 'efb_human_shield_blocked',
      'A WAF-blocked attestation fails safely through the server guard');
    await wafContext.close();

    // Synthetic browser counters are not a credential: a direct client without
    // a live EFB session cannot obtain a signed token.
    const forged = await mintToken(136, 'forged-session', humanMetrics(), 'Forged-Agent-A');
    check(forged.challenge.json?.code === 'session_invalid' && !forged.attest,
      'Synthetic metrics without a live session cannot mint a token');

    const uaBound = await mintToken(134, guestResponse.sid, humanMetrics(), 'Bound-Agent-A');
    const uaMismatch = await rawPost(PROTECTED, { id: 134, sid: guestResponse.sid }, {
      'X-EFB-Human-Token': uaBound.attest?.json?.token || '',
      'User-Agent': 'Bound-Agent-B',
    });
    check(uaBound.attest?.json?.success === true && uaMismatch.status === 200 && uaMismatch.json?.data?.code === 'efb_human_shield_blocked',
      'Token bound to one User-Agent is blocked on another User-Agent');

    // A signed hard-fail flag must win even if other metrics score above the
    // quarantine threshold.
    const honey = await mintToken(134, guestResponse.sid, humanMetrics({ honeypotFilled: true }), 'Honeypot-Agent');
    check(honey.attest && honey.attest.json && honey.attest.json.success && honey.attest.json.score === 47,
      'Filled honeypot token is issued for a guard-side hard-fail check', honey.attest && honey.attest.json ? String(honey.attest.json.score) : 'no token');
    const honeyUse = await rawPost(PROTECTED, { id: 134, sid: guestResponse.sid }, {
      'X-EFB-Human-Token': honey.attest.json.token,
      'User-Agent': 'Honeypot-Agent',
    });
    check(honeyUse.status === 200 && honeyUse.json?.data?.code === 'efb_human_shield_blocked',
      'Honeypot-filled request is blocked by Human Shield before core');

    // Race/replay is a positive control: only the first request can pass the
    // conditional UPDATE that marks the signed token used.
    const replay = await mintToken(134, guestResponse.sid, humanMetrics(), 'Replay-Agent');
    const [replayOne, replayTwo] = await Promise.all([
      rawPost(PROTECTED, { id: 134, sid: guestResponse.sid }, { 'X-EFB-Human-Token': replay.attest.json.token, 'User-Agent': 'Replay-Agent' }),
      rawPost(PROTECTED, { id: 134, sid: guestResponse.sid }, { 'X-EFB-Human-Token': replay.attest.json.token, 'User-Agent': 'Replay-Agent' }),
    ]);
    const replayStatuses = [replayOne, replayTwo];
    check(replayStatuses.some((r) => r.status === 403 && r.json && r.json.code === 'rest_forbidden') &&
      replayStatuses.some((r) => r.status === 200 && r.json && r.json.data && r.json.data.code === 'efb_human_shield_blocked'),
      'Concurrent replay allows once and blocks the duplicate');

    // Route-specific response-add rate cap is 2/min by default. No-token calls
    // are sufficient and do not create a response or send notifications.
    const responseAttempts = [];
    for (let i = 0; i < 3; i += 1) {
      responseAttempts.push(await rawPost('/wp-json/Emsfb/v1/forms/response/add', { id: 137, track: 'rate-test' }));
    }
    check(responseAttempts[0].json?.data?.code === 'efb_human_shield_blocked' &&
      responseAttempts[1].json?.data?.code === 'efb_human_shield_blocked' &&
      responseAttempts[2].json?.data?.code === 'efb_human_shield_blocked' &&
      /Too many requests/i.test(responseAttempts[2].json?.data?.m || ''),
      'Response-add route is rate-limited after two requests');

    // A forwarding header is ignored unless REMOTE_ADDR is an explicitly
    // configured trusted proxy. The local Apache peer is 127.0.0.1, so use a
    // different address first to model a directly reachable origin.
    await adminPage.locator('.efb-hs-tab[data-tab="system"]').click();
    check(await adminPage.locator('[data-setting="trusted_proxy_ips"]').count() === 1,
      'System settings expose the trusted-proxy allowlist');
    const proxySettings = await patchViaAdminAjax(adminPage, {
      enabled: 1,
      mode: 'soft_block',
      trusted_proxy_headers: 1,
      trusted_proxy_ips: '127.0.0.2',
      ip_allowlist: '203.0.113.7',
    });
    check(proxySettings.success === true, 'Proxy-header test settings save succeeds');
    const spoofedProxyIp = await rawPost(PROTECTED, { id: 141, sid: 'spoofed-proxy-ip' }, {
      'CF-Connecting-IP': '203.0.113.7',
    });
    check(spoofedProxyIp.status === 200 && spoofedProxyIp.json?.data?.code === 'efb_human_shield_blocked',
      'Untrusted peer cannot spoof CF-Connecting-IP through the IP allowlist');

    const trustedProxySettings = await patchViaAdminAjax(adminPage, { trusted_proxy_ips: '127.0.0.1' });
    check(trustedProxySettings.success === true, 'Trusted-proxy allowlist save succeeds');
    const trustedProxyIp = await rawPost(PROTECTED, { id: 141, sid: 'trusted-proxy-ip' }, {
      'CF-Connecting-IP': '203.0.113.7',
    });
    check(trustedProxyIp.status === 403 && trustedProxyIp.json?.code === 'rest_forbidden',
      'Configured proxy peer may forward CF-Connecting-IP to the allowlist');

    // Strict mode returns an HTTP signal suitable for a WAF/CDN.
    await setViaUi(adminPage, { enabled: 1, mode: 'strict' });
    const strictMissing = await rawPost(PROTECTED, { id: 139, sid: 'strict-no-token' });
    check(strictMissing.status === 403 && strictMissing.json?.data?.code === 'efb_human_shield_blocked',
      'Strict mode returns HTTP 403 for missing token');

    // Monitor mode remains non-disruptive: the request reaches the original
    // WordPress nonce gate and gets its core rest_forbidden response.
    await setViaUi(adminPage, { enabled: 1, mode: 'monitor' });
    const monitored = await rawPost(PROTECTED, { id: 140, sid: 'monitor-no-token' });
    check(monitored.status === 403 && monitored.json?.code === 'rest_forbidden',
      'Monitor mode records missing token without replacing the core response');

    check(browserErrors.length === 0, 'Admin browser run has no console/page errors', browserErrors.join(' | '));
  } finally {
    if (originalSettings) {
      const restored = await restoreViaAdminAjax(adminPage, originalSettings);
      check(restored && restored.success === true, 'Original Human Shield settings restored');
    }
    await browser.close();
  }

  console.log(JSON.stringify({ passed, failed, findings }, null, 2));
  process.exit(failed ? 1 : 0);
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
