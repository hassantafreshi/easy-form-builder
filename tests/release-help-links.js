/**
 * HELP-01, release-time check (network): every help page of the add-on catalogue (Emsfb\Addon_Catalog::HELP and
 * HELP_TOPICS) answers HTTP 200 in each language it is shown in (en_US, de_DE, ar, fa_IR), after its redirects, and lands
 * on a page of its own (not a documentation index or a home page). Release exit criterion "Every Help link opens a
 * specific page (HTTP 200, not an index page)".
 *
 * Not part of the unit suite (it needs the internet): run it before a release, and after adding a help_url.
 * Run: node tests/release-help-links.js            (prints [SKIP] without network access)
 */
'use strict';
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const LOCALES = ['en_US', 'de_DE', 'ar', 'fa_IR'];
const INDEX = /^https:\/\/([a-z]+\.)?(whitestudio\.team|easyformbuilder\.ir)\/?((document|documents|advanced-addons|blog|%d8%af%d8%a7%da%a9%db%8c%d9%88%d9%85%d9%86%d8%aa|داکیومنت|addons)\/?)?(#.*)?$/i;

function catalogFor(locale) {
  const php = 'define("ABSPATH", "/"); function get_locale() { return ' + JSON.stringify(locale) + '; } require ' + JSON.stringify(path.join(ROOT, 'includes/class-Emsfb-addon-catalog.php')) + ';'
    + ' echo json_encode(array("cat" => \\Emsfb\\Addon_Catalog::js_mirror(), "topics" => \\Emsfb\\Addon_Catalog::help_topics()));';
  return JSON.parse(execFileSync('php', ['-r', php], { encoding: 'utf8' }));
}

async function status(url) {
  const ctl = new AbortController();
  const timer = setTimeout(() => ctl.abort(), 30000);
  try {
    const r = await fetch(url, { redirect: 'follow', signal: ctl.signal, headers: { 'user-agent': 'efb-release-check' } });
    return { code: r.status, final: r.url };
  } finally { clearTimeout(timer); }
}

(async () => {
  const urls = new Map(); // url => [where]
  for (const loc of LOCALES) {
    const c = catalogFor(loc);
    for (const [code, e] of Object.entries(c.cat)) if (e.help) urls.set(e.help, (urls.get(e.help) || []).concat(loc + ' ' + code));
    for (const [topic, u] of Object.entries(c.topics)) if (u) urls.set(u, (urls.get(u) || []).concat(loc + ' ' + topic));
  }
  try { await status('https://whitestudio.team/'); } catch (e) {
    console.log('[SKIP] no network access: ' + (e && e.message));
    console.log('RESULTS: 0 passed, 0 failed');
    return;
  }
  let passed = 0, failed = 0;
  for (const [url, where] of urls) {
    let r;
    try { r = await status(url); } catch (e) { r = { code: 0, final: String(e && e.message) }; }
    const ok = r.code === 200 && !INDEX.test(r.final);
    console.log(`[${ok ? 'PASS' : 'FAIL'}] ${r.code} ${decodeURIComponent(r.final)} (${where.join(', ')})`);
    if (ok) passed++; else failed++;
  }
  console.log(`RESULTS: ${passed} passed, ${failed} failed`);
  process.exit(failed ? 1 : 0);
})();
