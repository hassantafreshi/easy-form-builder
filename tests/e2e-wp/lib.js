/**
 * Shared helpers for the real-WordPress end-to-end suite (run.js). See README.md.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { chromium } = require('playwright');

const ROOT = path.join(__dirname, '..', '..');

/* Settings: environment variables, with the local test site as the default. */
const CFG = {
  base: (process.env.EFB_E2E_URL || 'http://127.0.0.1:8088').replace(/\/$/, ''),
  user: process.env.EFB_E2E_USER || 'admin',
  pass: process.env.EFB_E2E_PASS || 'admin-pass-123',
  // WP-CLI: a command (EFB_E2E_WPCLI, e.g. "wp" or "php /path/wp-cli.phar") and the site folder.
  wpcli: (process.env.EFB_E2E_WPCLI || 'wp').split(' ').filter(Boolean),
  site: process.env.EFB_E2E_SITE_DIR || '',
  out: process.env.EFB_E2E_OUT || path.join(require('os').tmpdir(), 'efb-e2e-out'),
  slow: Number(process.env.EFB_E2E_SLOWMO || 0),
};
fs.mkdirSync(CFG.out, { recursive: true });

function wp(...args) {
  const cmd = CFG.wpcli[0];
  const pre = CFG.wpcli.slice(1).concat(['--allow-root']);
  if (CFG.site) pre.push('--path=' + CFG.site);
  return execFileSync(cmd, pre.concat(args.map(String)), { encoding: 'utf8', cwd: ROOT, maxBuffer: 50 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'] });
}

/** tests/e2e-wp/ctl.php through `wp eval-file`; returns the JSON it printed last. */
function ctl(...args) {
  const out = wp('eval-file', path.join(__dirname, 'ctl.php'), ...args).trim().split('\n');
  return JSON.parse(out[out.length - 1]);
}

/** A fresh install: deactivate, remove everything the plugin stored, activate again. */
function freshInstall() {
  try { wp('plugin', 'deactivate', 'easy-form-builder'); } catch (e) { /* already inactive */ }
  const wiped = ctl('wipe');
  wp('plugin', 'activate', 'easy-form-builder');
  return wiped;
}

/* Playwright's own browser first (the task contract: chromium.launch() with no options); the
 * repository pins an older Playwright than the browsers some machines have, so fall back to an
 * installed headless shell, like the other browser tests do. EFB_CHROMIUM picks a build. */
async function launch() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) {
      const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell');
      if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe });
    }
    throw e;
  }
}

/* -------------------------------------------------------------------------------------------
 * Console guard (scenario 7): any uncaught page error, and any console error that comes from the
 * plugin (its script files, an inline script of a page, or no location), fails the scenario.
 * ALLOW lists genuine third-party noise only; each entry says why.
 * ----------------------------------------------------------------------------------------- */
const ALLOW = [
  // The browser's own message for a resource another host refused (fonts/CDN outside the test box);
  // the request itself is reported separately when it is the plugin's.
  { re: /^Failed to load resource: net::ERR_(NAME_NOT_RESOLVED|CONNECTION_REFUSED|INTERNET_DISCONNECTED|TUNNEL_CONNECTION_FAILED|PROXY_CONNECTION_FAILED|CERT_AUTHORITY_INVALID|TIMED_OUT|CONNECTION_RESET)/, external: true, why: 'external host not reachable from the test box' },
  // WordPress core: the block editor / admin bar sometimes logs JQMIGRATE notices (warnings, not errors).
  { re: /^JQMIGRATE:/, why: 'WordPress core jQuery Migrate notice' },
  // F12b follow-up: too many replies is answered with HTTP 429 + Retry-After (owner decision 2026-11-15); the browser
  // logs every non-2xx response itself. Only that route: the page shows the countdown (checked by f12b-9).
  { re: /^Failed to load resource: the server responded with a status of 429/, src: /\/wp-json\/Emsfb\/v1\/forms\/response\/add/, why: 'the reply limit answers 429 by design' },
  // F12b-L: the settings screen of a CAPTCHA add-on that is not installed or active answers 404 by design (f12b-10).
  { re: /^Failed to load resource: the server responded with a status of 404/, src: /\/wp-admin\/admin\.php\?page=Emsfb_captcha&provider=/, why: 'the screen of a missing CAPTCHA add-on is a 404 page by design' },
];

function isPluginSource(url, base) {
  if (!url) return true; // inline or eval'd code: the plugin prints inline scripts
  if (url.includes('/plugins/easy-form-builder/')) return true;
  if (url.startsWith(base) && !url.includes('/wp-includes/') && !url.includes('/wp-admin/js/') && !url.includes('/themes/') && !/\/plugins\/(?!easy-form-builder)/.test(url)) return true; // the page itself (inline)
  return false;
}

/* Page errors that are the browser's and not the plugin's, matched by their exact text (and no plugin file in the stack).
 * WordPress 7.1's cross-document page transitions (@view-transition): when a reload (the settings Save reloads the
 * screen) is followed by another navigation before the new page is revealed, Chromium rejects the transition's
 * promise with this DOMException. No script of the page throws it (no stack); f15c/7 failed on it now and then. */
const BENIGN_PAGEERRORS = ['Transition was aborted because of invalid state. Page already revealed'];

function guard(page, sink, label) {
  page.on('pageerror', (e) => BENIGN_PAGEERRORS.includes(String(e && e.message || e)) && !String(e && e.stack || '').includes('/plugins/') ? null : sink.push({ kind: 'pageerror', where: label, url: page.url(), text: String(e && e.message || e), stack: String(e && e.stack || '').split('\n').slice(0, 4).join(' | ') }));
  page.on('console', (m) => {
    if (m.type() !== 'error') return;
    const text = m.text();
    const loc = m.location() || {};
    const allowed = ALLOW.find((a) => a.re.test(text) && (!a.src || a.src.test(loc.url || '')));
    if (allowed) {
      // The external-host allowance only covers resources of other hosts.
      if (!allowed.external || !(loc.url || '').startsWith(CFG.base)) return;
    }
    // A request the scenario fails on purpose (page.efbFailOnPurpose, a RegExp of its URL): the browser's own line.
    if (page.efbFailOnPurpose && page.efbFailOnPurpose.test(loc.url || '') && /^Failed to load resource:/.test(text)) return;
    if (!isPluginSource(loc.url, CFG.base) && !/efb|emsfb|Easy Form/i.test(text)) return;
    sink.push({ kind: 'console', where: label, url: page.url(), text: text.slice(0, 500), src: loc.url ? `${loc.url}:${loc.lineNumber}` : '' });
  });
  page.on('requestfailed', (r) => {
    const u = r.url();
    if (page.efbFailOnPurpose && page.efbFailOnPurpose.test(u)) return;
    if (u.startsWith(CFG.base) && !/favicon|heartbeat|wp-cron/.test(u) && !/ERR_ABORTED/.test((r.failure() || {}).errorText || '')) {
      sink.push({ kind: 'requestfailed', where: label, url: u, text: (r.failure() || {}).errorText || '' });
    }
  });
  page.on('dialog', (d) => { page.__dialogs = (page.__dialogs || []).concat(d.message()); d.accept().catch(() => {}); });
}

async function login(page, user, pass) {
  await page.goto(CFG.base + '/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('#wp-submit')]);
  if (/wp-login\.php/.test(page.url())) throw new Error('login failed for ' + user);
}

/* Checks: one line per check, counted per scenario. */
function makeReport() {
  const scenarios = [];
  let cur = null;
  return {
    start(id, title) { cur = { id, title, pass: 0, fail: 0, checks: [] }; scenarios.push(cur); console.log(`\n=== ${id} ${title}`); },
    check(label, ok, detail) {
      if (!cur) throw new Error('no scenario');
      const row = { label, ok: !!ok };
      if (ok) cur.pass++; else { cur.fail++; row.detail = detail; }
      cur.checks.push(row);
      console.log((ok ? '[PASS] ' : '[FAIL] ') + label + (!ok && detail !== undefined ? '  ' + JSON.stringify(detail).slice(0, 1500) : ''));
      return !!ok;
    },
    scenarios,
    get current() { return cur; },
  };
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

module.exports = { CFG, ROOT, wp, ctl, freshInstall, launch, guard, login, makeReport, sleep, ALLOW, BENIGN_PAGEERRORS };
