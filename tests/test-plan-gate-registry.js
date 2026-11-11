/**
 * F18 sub-task A (JS): the browser reads the same registry as PHP (efb_var.capabilities, B3).
 *
 * plan-gate-efb.js runs in a vm with efb_var.capabilities produced by the real PHP
 * Builder_Api::capabilities(). Checked:
 * - efb_feature_required_efb(): esign free_plus, pdate/ardate/persiaPay pro, text free,
 *   setting:mobile_layout pro, an unknown key pro (as PHP);
 * - efb_current_package_efb() for package_type -1/0/1/2/3/10 and a missing setting;
 * - for every key and every plan 0/1/2/3, efb_feature_available_efb(key, plan) equals PHP
 *   efb_feature_available_efb($key, $plan) (plan and add-on);
 * - every palette field in fields_efb (val-efb.js) has the level its `pro` flag says;
 * - efb_feature_label_efb() names a feature from efb_var.text (fields: the palette name);
 * - no table of plans inside plan-gate-efb.js (one registry, README shared decision 3).
 *
 * Run: node tests/test-plan-gate-registry.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const FILE = path.join(ROOT, 'includes/admin/assets/js/plan-gate-efb.js');
let pass = 0; let fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};

const API = path.join(ROOT, 'includes/class-Emsfb-builder-api.php').replace(/\\/g, '/');
const FUNCS = path.join(ROOT, 'includes/functions.php').replace(/\\/g, '/');
function php() {
  const out = execFileSync('php', ['-r', `
    define('ABSPATH','/'); define('EMSFB_PLUGIN_VERSION','4.3.1'); define('EMSFB_API_VERSION',1);
    $GLOBALS['opt'] = array('emsfb_pro' => 2);
    function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opt']) ? $GLOBALS['opt'][$k] : $d; }
    function add_filter() {} function apply_filters($h, $v) { return $v; }
    function get_setting_Emsfb($m = 'decoded') { return (object) array('AdnSPF' => 1, 'AdnSS' => 0, 'AdnPAP' => 0, 'AdnSMF' => 1); }
    $src = file_get_contents('${FUNCS}');
    preg_match('/static \\$tiers = array\\(([\\s\\S]*?)\\);/', $src, $m);
    preg_match_all("/'(Adn\\\\w+)'\\\\s*=>\\\\s*(\\\\d)/", $m[1], $mm, PREG_SET_ORDER);
    $GLOBALS['tiers'] = array(); foreach ($mm as $t) $GLOBALS['tiers'][$t[1]] = (int) $t[2];
    class F { public function addon_required_package_efb($a) { return isset($GLOBALS['tiers'][$a]) ? $GLOBALS['tiers'][$a] : 1; } }
    function get_efbFunction() { return new F(); }
    require '${API}';
    $out = array('caps' => \\Emsfb\\Builder_Api::capabilities(), 'avail' => array(), 'req' => array());
    foreach (array_keys($out['caps']['features']) as $k) {
      $out['req'][$k] = efb_feature_required_package_efb($k);
      foreach (array(0, 1, 2, 3) as $p) $out['avail'][$k][$p] = efb_feature_available_efb($k, $p);
    }
    echo json_encode($out);
  `]).toString();
  return JSON.parse(out);
}

function load(setting, extra) {
  const ctx = Object.assign({ console, efb_var: Object.assign({ setting: setting, text: { esign: 'Signature', redirectPage: 'Redirect page', upgFtrMobileLayout: 'Mobile layout settings' } }, extra || {}) }, {});
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(fs.readFileSync(FILE, 'utf8'), ctx, { filename: 'plan-gate-efb.js' });
  return ctx;
}

if (!fs.existsSync(FILE)) {
  check('includes/admin/assets/js/plan-gate-efb.js exists', false);
} else {
  const P = php();
  // The same add-on flags the PHP side was given (get_setting_Emsfb above).
  const flags = { AdnSPF: 1, AdnSS: 0, AdnPAP: 0, AdnSMF: 1 };
  const g = load(Object.assign({ package_type: 2 }, flags), { capabilities: P.caps });
  const req = (k) => g.efb_feature_required_efb(k);
  check("efb_feature_required_efb('field:esign') === 'free_plus'", req('field:esign') === 'free_plus');
  check("field:pdate / field:ardate / field:persiaPay → 'pro'", ['field:pdate', 'field:ardate', 'field:persiaPay'].every((k) => req(k) === 'pro'));
  check("field:text → 'free'; setting:mobile_layout → 'pro'; unknown → 'pro'", req('field:text') === 'free' && req('setting:mobile_layout') === 'pro' && req('nope:x') === 'pro');

  const pk = { '-1': 2, 0: 0, 1: 1, 2: 2, 3: 3, 10: 2 };
  const pkOk = Object.entries(pk).every(([raw, want]) => load({ package_type: Number(raw) }, { capabilities: P.caps }).efb_current_package_efb() === want);
  check('efb_current_package_efb(): -1/10 → 2, 0/1/2/3 unchanged', pkOk);
  check('efb_current_package_efb() without a setting → 2; efb_plan_chosen_efb() false for -1/10', load({}, { capabilities: P.caps }).efb_current_package_efb() === 2
    && load({ package_type: -1 }).efb_plan_chosen_efb() === false && load({ package_type: 10 }).efb_plan_chosen_efb() === false && load({ package_type: 3 }).efb_plan_chosen_efb() === true);

  const lvl = { 0: 'free', 3: 'free_plus', 1: 'pro' };
  const badReq = Object.keys(P.req).filter((k) => req(k) !== lvl[P.req[k]]);
  check(`required level equals PHP for every key (${Object.keys(P.req).length})`, badReq.length === 0, badReq);
  const badAvail = [];
  for (const k of Object.keys(P.avail)) for (const p of [0, 1, 2, 3]) {
    if (g.efb_feature_available_efb(k, p) !== P.avail[k][p]) badAvail.push(`${k}@${p}`);
  }
  check('availability equals PHP efb_feature_available_efb($key, $plan) for every key × plan 0/1/2/3', badAvail.length === 0, badAvail.slice(0, 20));

  // Live plan: without the second argument the page's package_type decides (a plan change in the page counts).
  const live = load({ package_type: 2 }, { capabilities: P.caps });
  const before = live.efb_feature_available_efb('field:esign');
  live.efb_var = Object.assign({}, live.efb_var, { setting: { package_type: 3 } });
  check('the live plan is read at call time (Free → Free Plus unlocks field:esign)', before === false && live.efb_feature_available_efb('field:esign') === true);

  // fields_efb[].pro in val-efb.js
  const val = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/val-efb.js'), 'utf8');
  const block = val.slice(val.indexOf('const fields_efb = ['), val.indexOf('];', val.indexOf('const fields_efb = [')));
  const fields = [];
  block.replace(/id:\s*'([A-Za-z_]+)'[^}]*?pro:\s*(true|false)/g, (m, id, pro) => { fields.push([id, pro === 'true']); return m; });
  const addonPro = ['pdate', 'ardate', 'persiaPay'];
  const badF = fields.filter(([id, pro]) => req('field:' + id) !== (addonPro.includes(id) ? 'pro' : (pro ? 'free_plus' : 'free')));
  check(`every palette field (${fields.length}) has the level of fields_efb[].pro`, fields.length >= 40 && badF.length === 0, badF);

  check("efb_feature_label_efb('field:esign') is the field name; setting keys read their label key",
    g.efb_feature_label_efb('field:esign') === 'Signature' && g.efb_feature_label_efb('setting:thank_you_redirect') === 'Redirect page' && g.efb_feature_label_efb('setting:mobile_layout') === 'Mobile layout settings');
  check('an unknown key without a label falls back to a readable name, never "undefined"', !/undefined/.test(String(g.efb_feature_label_efb('nope:x'))));

  // A page without the registry (old cached page): the caller's own level is used.
  const bare = load({ package_type: 2 });
  check('without efb_var.capabilities: required falls back to opts / pro', bare.efb_feature_required_efb('field:esign', 'free_plus') === 'free_plus' && bare.efb_feature_required_efb('field:esign') === 'pro');

  const src = fs.readFileSync(FILE, 'utf8');
  check('plan-gate-efb.js keeps no second plan table (no hard-coded field or add-on levels)', !/['"]field:esign['"]\s*:|AdnSPF['"]?\s*:\s*\d|esign['"]?\s*:\s*\{\s*required/.test(src));
}

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
