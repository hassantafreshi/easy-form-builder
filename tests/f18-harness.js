/**
 * Helpers for the F18 (upgrade prompt) browser tests. Not a test on its own.
 *
 * - phrases(locale): every text_efb() phrase in English (from includes/functions.php), or in
 *   Persian through languages/easy-form-builder-fa_IR.po, so the dialog is checked with the real copy;
 * - openF18(opts): ap-builder-harness openBuilder() plus the admin stylesheets (layout checks),
 *   the real phrases and an admin-ajax recorder that answers efb_save_plan_selection like the server.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { openBuilder, makeChecker, blankForm, ROOT } = require('./ap-builder-harness.js');

function unescapePhp(s) { return s.replace(/\\'/g, "'").replace(/\\\\/g, '\\'); }

let cache = {};
function phrases(locale) {
  if (cache[locale]) return cache[locale];
  const src = fs.readFileSync(path.join(ROOT, 'includes/functions.php'), 'utf8');
  const en = {};
  const re = /"([A-Za-z0-9_]+)"\s*=>\s*\$state[^\n]*?esc_html__\('((?:[^'\\]|\\.)*)'/g;
  let m;
  while ((m = re.exec(src)) !== null) if (!(m[1] in en)) en[m[1]] = unescapePhp(m[2]);
  if (locale !== 'fa_IR') { cache[locale] = en; return en; }
  const po = fs.readFileSync(path.join(ROOT, 'languages/easy-form-builder-fa_IR.po'), 'utf8');
  const map = {};
  const pr = /\nmsgid "((?:[^"\\]|\\.)*)"\nmsgstr "((?:[^"\\]|\\.)*)"/g;
  while ((m = pr.exec(po)) !== null) {
    const id = m[1].replace(/\\"/g, '"').replace(/\\\\/g, '\\');
    const str = m[2].replace(/\\"/g, '"').replace(/\\\\/g, '\\');
    if (str) map[id] = str;
  }
  const fa = {};
  for (const k of Object.keys(en)) fa[k] = map[en[k]] || en[k];
  cache[locale] = fa;
  return fa;
}

/* The registry the page gets from PHP (Builder_Api::capabilities()) for a plan and add-on flags. */
function capabilities(pkg, settings) {
  const { execFileSync } = require('child_process');
  const API = path.join(ROOT, 'includes/class-Emsfb-builder-api.php').replace(/\\/g, '/');
  const FUNCS = path.join(ROOT, 'includes/functions.php').replace(/\\/g, '/');
  const out = execFileSync('php', ['-r', `
    define('ABSPATH','/'); define('EMSFB_PLUGIN_VERSION','4.3.1'); define('EMSFB_API_VERSION',1);
    function get_option($k, $d = false) { return $d; }
    function add_filter() {} function apply_filters($h, $v) { return $v; }
    function get_setting_Emsfb($m = 'decoded') { return json_decode($GLOBALS['settings']); }
    $GLOBALS['settings'] = stream_get_contents(STDIN);
    $src = file_get_contents('${FUNCS}');
    preg_match('/static \\$tiers = array\\(([\\s\\S]*?)\\);/', $src, $m);
    preg_match_all("/'(Adn\\\\w+)'\\\\s*=>\\\\s*(\\\\d)/", $m[1], $mm, PREG_SET_ORDER);
    $GLOBALS['tiers'] = array(); foreach ($mm as $t) $GLOBALS['tiers'][$t[1]] = (int) $t[2];
    class F { public function addon_required_package_efb($a) { return isset($GLOBALS['tiers'][$a]) ? $GLOBALS['tiers'][$a] : 1; } }
    function get_efbFunction() { return new F(); }
    require '${API}';
    echo json_encode(\\Emsfb\\Builder_Api::capabilities(array('package' => ${Number(pkg)})));
  `], { input: JSON.stringify(settings || {}) }).toString();
  return JSON.parse(out);
}

const CSS = ['bootstrap.min-efb.css', 'bootstrap-icons-efb.css', 'style-efb.css', 'admin-efb.css', 'modal-system-efb.css'];

/**
 * opts: openBuilder() options plus
 *   locale 'en_US'|'fa_IR', width/height (viewport), planAnswer(plan_data) → {status, body} for
 *   efb_save_plan_selection (default: success with package_type of the selected plan).
 */
async function openF18(opts) {
  opts = Object.assign({ builder: true, form: blankForm() }, opts || {});
  const locale = opts.locale || 'en_US';
  const rtl = locale === 'fa_IR';
  opts.text = Object.assign({}, phrases(locale), opts.text || {});
  const pkg = opts.packageType === undefined ? 1 : opts.packageType;
  const caps = opts.noRegistry ? undefined : capabilities([0, 1, 2, 3].includes(Number(pkg)) ? Number(pkg) : 2, Object.assign({}, opts.setting || {}, opts.addons || {}));
  opts.extraVar = Object.assign({ rtl: rtl ? 1 : 0, language: locale, wp_lan: locale, capabilities: caps, api_version: 1, ajax_url: 'http://site.test/wp-admin/admin-ajax.php' }, opts.extraVar || {});
  const h = await openBuilder(opts);
  const { page } = h;
  if (opts.width) await page.setViewportSize({ width: opts.width, height: opts.height || 740 });
  if (rtl) {
    await page.evaluate(() => { document.documentElement.setAttribute('dir', 'rtl'); document.documentElement.lang = 'fa-IR'; document.body.classList.add('rtl'); });
  }
  for (const f of CSS.concat(rtl ? ['admin-rtl-efb.css'] : [])) {
    const p = path.join(ROOT, 'includes/admin/assets/css', f);
    if (fs.existsSync(p)) await page.addStyleTag({ path: p });
  }
  h.planCalls = [];
  await page.route('**/admin-ajax.php*', async (route) => {
    const body = route.request().postData() || '';
    const params = new URLSearchParams(body);
    if (params.get('action') !== 'efb_save_plan_selection') return route.fallback();
    const planData = JSON.parse(params.get('plan_data') || '{}');
    h.planCalls.push({ planData, nonce: params.get('nonce') });
    let ans = typeof opts.planAnswer === 'function' ? opts.planAnswer(planData, h.planCalls.length) : null;
    if (!ans) {
      const pkg = planData.selected_plan === 'free_plus' ? 3 : (planData.selected_plan === 'free' ? 2 : 1);
      ans = { status: 200, body: { success: true, data: { success: true, plan: planData.selected_plan, package_type: pkg, previous_package_type: 2, plan_changed: true, action: 'Free Plus plan activated with enhanced features.' } } };
    }
    if (ans.delay) await new Promise((r) => setTimeout(r, ans.delay));
    return route.fulfill({ status: ans.status || 200, contentType: 'application/json', body: JSON.stringify(ans.body) });
  });
  return h;
}

/* What the dialog shows now. */
async function dialogState(page) {
  return page.evaluate(() => {
    const shell = document.getElementById('settingModalEfb');
    const root = document.querySelector('#settingModalEfb .efb-upg');
    if (!shell || !shell.classList.contains('show') || !root) return { open: false };
    const btns = Array.from(document.querySelectorAll('#save-result-foot-efb [data-efb-upg-action]')).map((b) => ({
      id: b.getAttribute('data-efb-upg-action'), tag: b.tagName, text: b.textContent.replace(/\s+/g, ' ').trim(),
      href: b.getAttribute('href'), target: b.getAttribute('target'), rel: b.getAttribute('rel'), describedby: b.getAttribute('aria-describedby'),
    }));
    return {
      open: true, state: root.getAttribute('data-efb-upg-state'), feature: root.getAttribute('data-efb-feature'),
      title: (document.getElementById('settingModalEfb-title') || {}).textContent,
      headline: document.getElementById('efbUpgHeadEfb').textContent.trim(),
      headlineHtml: document.getElementById('efbUpgHeadEfb').innerHTML,
      text: document.getElementById('efbUpgTextEfb').textContent.trim(),
      disclosure: document.getElementById('efbUpgDiscEfb') ? document.getElementById('efbUpgDiscEfb').textContent.replace(/\s+/g, ' ').trim() : null,
      alert: (document.getElementById('efbUpgAlertEfb') || {}).textContent || '',
      buttons: btns, labelledby: shell.getAttribute('aria-labelledby'), describedby: shell.getAttribute('aria-describedby'), role: shell.getAttribute('role'),
      active: document.activeElement ? (document.activeElement.id || document.activeElement.getAttribute('data-efb-upg-action') || document.activeElement.tagName) : null,
    };
  });
}

async function waitClosed(page) {
  await page.waitForFunction(() => !document.querySelector('#settingModalEfb.show .efb-upg'), null, { timeout: 4000 }).catch(() => {});
}

module.exports = { openF18, phrases, capabilities, dialogState, waitClosed, makeChecker, blankForm, ROOT };
