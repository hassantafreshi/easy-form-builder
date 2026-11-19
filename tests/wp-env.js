/**
 * Not a test. Where the WordPress-backed browser tests find WordPress, its address, its administrator,
 * PHP and Chromium, so the same test runs on the owner's XAMPP machine and on any other box. Node twin
 * of tests/wp-env.php (same order, same variables):
 *
 *   WordPress  EFB_WP_ROOT | EFB_E2E_SITE_DIR | dirname(EFB_WP_LOAD), then the local e2e site, then the
 *              owner's layout (this plugin in <wordpress>/wp-content/plugins/, then C:/xampp/htdocs/wp).
 *              EFB_WP_ROOT=none turns WordPress off.
 *   address    EFB_WP_URL | EFB_E2E_URL | EFB_BASE, else http://127.0.0.1:8088 (e2e site) or
 *              http://127.0.0.1/wp (owner's).
 *   admin      EFB_WP_USER/EFB_WP_PASS | EFB_E2E_USER/EFB_E2E_PASS | EFB_ADMIN_USER/EFB_ADMIN_PASS, else the
 *              site's own (admin / admin-pass-123 on the e2e site, admin / admin on the owner's).
 *   PHP        EFB_PHP, else C:\xampp\php\php.exe on Windows when it is there, else `php`.
 *   Chromium   EFB_CHROMIUM, else Playwright's own browser launched with no options, else
 *              /opt/pw-browsers/chromium, else an installed headless shell, else the owner's Chrome.
 *
 * These tests write to that site: run them one at a time, under the e2e lock when other runs share it.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const E2E_SITE = '/tmp/claude-0/-home-user/6470513e-3182-5834-893e-38f1388be77b/scratchpad/wpsite/site';
const PLUGIN = path.resolve(__dirname, '..');
const OWNER_DIRS = [path.resolve(PLUGIN, '..', '..', '..'), 'C:/xampp/htdocs/wp'];
const OWNER_PHP = 'C:\\xampp\\php\\php.exe';
const OWNER_CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

const slash = (p) => String(p).replace(/\\/g, '/').replace(/\/+$/, '');
const env = (...names) => { for (const n of names) { const v = process.env[n]; if (v) return v; } return ''; };
const hasWp = (dir) => { try { return fs.statSync(path.join(dir, 'wp-load.php')).isFile(); } catch (e) { return false; } };

let siteCache = null;
/** { root, kind: 'env'|'e2e'|'owner'|'none', from } */
function site() {
  if (siteCache) return siteCache;
  for (const n of ['EFB_WP_ROOT', 'EFB_E2E_SITE_DIR']) {
    const v = process.env[n];
    if (!v) continue;
    if (v.toLowerCase() === 'none') return (siteCache = { root: '', kind: 'none', from: n + '=none' });
    return (siteCache = { root: slash(v), kind: 'env', from: n });
  }
  if (process.env.EFB_WP_LOAD) return (siteCache = { root: slash(path.dirname(process.env.EFB_WP_LOAD)), kind: 'env', from: 'EFB_WP_LOAD' });
  if (hasWp(E2E_SITE)) return (siteCache = { root: E2E_SITE, kind: 'e2e', from: 'the e2e site' });
  for (const d of OWNER_DIRS) if (hasWp(d)) return (siteCache = { root: slash(d), kind: 'owner', from: 'the owner layout' });
  return (siteCache = { root: '', kind: 'none', from: 'nothing found (set EFB_WP_ROOT)' });
}

const root = () => site().root;
const available = () => site().root !== '' && hasWp(site().root);
const wpLoad = () => (site().root ? site().root + '/wp-load.php' : `(no WordPress: ${site().from})/wp-load.php`);
const url = () => slash(env('EFB_WP_URL', 'EFB_E2E_URL', 'EFB_BASE') || (site().kind === 'owner' ? 'http://127.0.0.1/wp' : 'http://127.0.0.1:8088'));
function admin() {
  const user = env('EFB_WP_USER', 'EFB_E2E_USER', 'EFB_ADMIN_USER') || 'admin';
  const pass = env('EFB_WP_PASS', 'EFB_E2E_PASS', 'EFB_ADMIN_PASS') || (site().kind === 'owner' ? 'admin' : 'admin-pass-123');
  return { user, pass };
}
const php = () => env('EFB_PHP') || (process.platform === 'win32' && fs.existsSync(OWNER_PHP) ? OWNER_PHP : 'php');

/** The start of a `php -r` snippet that boots WordPress without a theme. */
const boot = () => `define("WP_USE_THEMES",false);require ${JSON.stringify(wpLoad())};`;

/** Runs PHP code with WordPress loaded; returns what it printed. */
function wpEval(code, opts = {}) {
  return execFileSync(php(), ['-r', boot() + code], { encoding: 'utf8', maxBuffer: 50 * 1024 * 1024, ...opts });
}

/** Runs a PHP script of tests/ (a seed script) with the same WordPress; returns what it printed. */
function phpScript(file, args = [], opts = {}) {
  return execFileSync(php(), [path.isAbsolute(file) ? file : path.join(__dirname, file), ...args.map(String)], {
    encoding: 'utf8', maxBuffer: 50 * 1024 * 1024, env: { ...process.env, EFB_WP_ROOT: site().root || 'none' }, ...opts,
  });
}

/** Prints the skip line the test runners read and ends the run (no WordPress here). */
function skipWithoutWp(name) {
  if (available()) return false;
  if (site().kind === 'env') { console.log(`[FAIL] ${site().from} names a WordPress that is not there: ${wpLoad()}`); process.exit(1); }
  console.log(`[SKIP] ${name || 'this test'} needs WordPress (${site().from}): set EFB_WP_ROOT and EFB_WP_URL (see tests/wp-env.js)`);
  console.log('RESULTS: 0 passed, 0 failed');
  process.exit(0);
}

/** Chromium for the browser tests; `options` are passed on (e.g. a viewport is set per page, not here). */
async function launch(options = {}) {
  const { chromium } = require('playwright');
  const tried = [];
  if (process.env.EFB_CHROMIUM) return chromium.launch({ ...options, executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(options); } catch (e) { tried.push(e); }
  const exes = ['/opt/pw-browsers/chromium'];
  const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
  if (fs.existsSync(base)) {
    for (const d of fs.readdirSync(base).filter((x) => /^chromium_headless_shell-\d+$/.test(x)).sort().reverse()) {
      exes.push(path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell'));
    }
  }
  if (process.platform === 'win32') exes.push(OWNER_CHROME);
  for (const exe of exes) {
    if (!fs.existsSync(exe)) continue;
    try { return await chromium.launch({ ...options, executablePath: exe }); } catch (e) { tried.push(e); }
  }
  throw tried[0] || new Error('no Chromium found (set EFB_CHROMIUM)');
}

module.exports = { site, root, available, wpLoad, url, admin, php, boot, wpEval, phpScript, skipWithoutWp, launch, E2E_SITE };
