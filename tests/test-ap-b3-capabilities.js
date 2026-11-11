/**
 * AutoPilot B3: one plan and add-on registry (efb_var.capabilities / get_efbFunction()->capabilities_efb()).
 *
 * PHP: plan helpers for emsfb_pro -1/0/1/2/3/10, the registry entries the brief lists (Free Plus:
 * email_sub, email_noti_type, redirect, loading, pro palette fields, >2 steps, AdnSMF; Pro only:
 * mobile layout and every add-on but AdnOF/AdnSMF/AdnSPF/AdnPAP), available_now per plan and add-on,
 * no secret in the output, add-on levels equal addon_required_package_efb(), field levels equal
 * fields_efb[].pro, EMSFB_API_VERSION / efb_var.api_version = 1.
 * JS: the builder's gates and dialogs read the same entry (change the entry, the dialog follows),
 * and a plan change in the page is honoured.
 *
 * Run: node tests/test-ap-b3-capabilities.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { openBuilder, makeChecker, blankForm, ROOT } = require('./ap-builder-harness.js');
const c = makeChecker();
const API = path.join(ROOT, 'includes/class-Emsfb-builder-api.php').replace(/\\/g, '/');

function php(pkg, settings) {
  const out = execFileSync('php', ['-r', `
    define('ABSPATH','/'); define('EMSFB_PLUGIN_VERSION','4.3.1'); define('EMSFB_API_VERSION',1);
    $GLOBALS['opt'] = array('emsfb_pro' => ${JSON.stringify(pkg)});
    function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opt']) ? $GLOBALS['opt'][$k] : $d; }
    $GLOBALS['filters'] = array();
    function add_filter($h, $cb, $p = 10, $n = 1) { $GLOBALS['filters'][$h] = $cb; }
    function get_setting_Emsfb($m = 'decoded') { return json_decode($GLOBALS['settings']); }
    $GLOBALS['settings'] = stream_get_contents(STDIN);
    require '${API}';
    $loc = call_user_func($GLOBALS['filters']['efb_admin_localize_vars'], array('pro' => 1), 'create');
    echo json_encode(array(
      'plan' => efb_plan_efb(), 'pkg' => efb_current_package_efb(),
      'atLeastFP' => efb_plan_at_least_efb('free_plus'), 'atLeastPro' => efb_plan_at_least_efb('pro'), 'atLeast3' => efb_plan_at_least_efb(3), 'atLeast1' => efb_plan_at_least_efb(1),
      'req_esign' => efb_feature_required_package_efb('field:esign'), 'req_unknown' => efb_feature_required_package_efb('nope'),
      'avail_redirect' => efb_feature_available_efb('setting:thank_you_redirect'),
      'caps' => \\Emsfb\\Builder_Api::capabilities(), 'loc_api' => $loc['api_version'], 'loc_has_caps' => isset($loc['capabilities']['features']),
    ));
  `], { input: JSON.stringify(settings || {}) }).toString();
  return JSON.parse(out);
}

const SECRET = { stripeSKey: 'sk_live_SECRET123', stripePKey: 'pk_live_PUBLIC123', paypalSKey: 'NotFoundEl', paypalPKey: '', AdnSS: 1, AdnSPF: 1 };
const plans = { '-1': 'free', '0': 'pro_pending', '1': 'pro', '2': 'free', '3': 'free_plus', '10': 'free' };
for (const [p, name] of Object.entries(plans)) {
  const r = php(Number(p), SECRET);
  c.check(`emsfb_pro=${p}: efb_plan_efb() = ${name}`, r.plan === name, r.plan);
  const fp = name === 'free_plus' || name === 'pro', pro = name === 'pro';
  c.check(`emsfb_pro=${p}: efb_plan_at_least_efb() (names and numbers)`, r.atLeastFP === fp && r.atLeast3 === fp && r.atLeastPro === pro && r.atLeast1 === pro, r);
}
const free = php(2, SECRET), fplus = php(3, SECRET), pro = php(1, SECRET);
const F = (r, k) => r.caps.features[k] || {};
c.check('api_version 1 in the registry and in efb_var (efb_admin_localize_vars)', free.caps.api_version === 1 && free.loc_api === 1 && free.loc_has_caps);
c.check('free fields: text, rating, conturyList are free', ['text', 'rating', 'conturyList', 'trmCheckbox', 'heading'].every((t) => F(free, 'field:' + t).package === 'free'));
c.check('Free Plus fields: esign, mobile, address, maps', ['esign', 'mobile', 'address', 'maps', 'switch'].every((t) => F(free, 'field:' + t).package === 'free_plus'));
c.check('add-on fields: pdate/ardate/persiaPay Pro, stripe/paypal Free Plus with their add-on',
  F(free, 'field:pdate').package === 'pro' && F(free, 'field:pdate').addon === 'AdnPDP' && F(free, 'field:ardate').package === 'pro' && F(free, 'field:persiaPay').package === 'pro'
  && F(free, 'field:stripe').package === 'free_plus' && F(free, 'field:stripe').addon === 'AdnSPF' && F(free, 'field:paypal').addon === 'AdnPAP');
for (const k of ['setting:email_sub', 'setting:email_noti_type', 'setting:thank_you_redirect', 'setting:loading_type', 'limit:steps', 'addon:AdnSMF', 'addon:AdnSPF', 'addon:AdnPAP']) {
  c.check(`${k} is Free Plus with dialog state 3 and its phrase key`, F(free, k).package === 'free_plus' && F(free, k).prompt_state === 3 && F(free, k).message_key === 'thisFeatureAvailableFreePlusPro', F(free, k));
}
for (const k of ['setting:mobile_layout', 'addon:AdnSS', 'addon:AdnTLG', 'addon:AdnPPF', 'addon:AdnPDP', 'addon:AdnATF', 'addon:AdnGoS', 'setting:sms_notifications']) {
  c.check(`${k} is Pro only with dialog state 1`, F(free, k).package === 'pro' && F(free, k).prompt_state === 1, F(free, k));
}
c.check('AdnOF is free on every plan', F(free, 'addon:AdnOF').package === 'free');
c.check('Free: redirect not available (reason plan); Free Plus and Pro: available', !F(free, 'setting:thank_you_redirect').available_now && F(free, 'setting:thank_you_redirect').reason === 'plan'
  && F(fplus, 'setting:thank_you_redirect').available_now && F(pro, 'setting:thank_you_redirect').available_now && !free.avail_redirect && fplus.avail_redirect);
c.check('SMS: Pro with the add-on installed is available; Free Plus is not (plan)', F(pro, 'setting:sms_notifications').available_now && !F(fplus, 'setting:sms_notifications').available_now && F(fplus, 'setting:sms_notifications').reason === 'plan');
const proNoSms = php(1, { AdnSS: 0 });
c.check('Pro without the SMS add-on: not available, reason addon, can_install', !F(proNoSms, 'setting:sms_notifications').available_now && F(proNoSms, 'setting:sms_notifications').reason === 'addon' && F(proNoSms, 'addon:AdnSS').can_install === true && F(proNoSms, 'addon:AdnSS').installed === false);
c.check('logic limits: Free Plus 3 field rules, 2 notification rules, 2 conditions; calculate, confirmation and webhook rules are Pro',
  JSON.stringify(F(free, 'limit:logic').limits.free_plus) === '{"field_rules":3,"notification_rules":2,"conditions_per_group":2}' && F(free, 'setting:logic_calculate').package === 'pro' && F(free, 'setting:confirmation_rules').package === 'pro');
c.check('steps: Free allows 2', F(free, 'limit:steps').limits.free === 2);
c.check('efb_feature_required_package_efb(): 3 for esign, 1 for an unknown key (F18 §14)', free.req_esign === 3 && free.req_unknown === 1);
const json = JSON.stringify(free) + JSON.stringify(fplus) + JSON.stringify(pro);
c.check('no secret in the output (keys reported as configured true/false)', !json.includes('SECRET123') && !json.includes('PUBLIC123') && free.caps.payment_keys.stripe === true && free.caps.payment_keys.paypal === false, free.caps.payment_keys);

// Add-on levels equal addon_required_package_efb() in functions.php.
const fn = fs.readFileSync(path.join(ROOT, 'includes/functions.php'), 'utf8');
const tiers = {};
(fn.match(/static \$tiers = array\(([\s\S]*?)\);/) || ['', ''])[1].replace(/'(Adn\w+)'\s*=>\s*(\d)/g, (m, k, v) => { tiers[k] = Number(v); return m; });
const keys = (fn.match(/function get_all_addon_keys_efb\(\)\{\s*return array\(([\s\S]*?)\);/) || ['', ''])[1].match(/'(Adn\w+)'/g).map((s) => s.slice(1, -1));
const lvl = (n) => (n === 0 ? 'free' : n === 3 ? 'free_plus' : 'pro');
const bad = keys.filter((k) => F(free, 'addon:' + k).package !== lvl(k in tiers ? tiers[k] : 1));
c.check(`every add-on (${keys.length}) has the level of addon_required_package_efb()`, keys.length >= 20 && bad.length === 0, bad);
// Field levels equal fields_efb[].pro (through the generated data file).
const pal = JSON.parse(fs.readFileSync(path.join(ROOT, 'includes/data/field-templates-efb.json'), 'utf8')).palette;
const addonFields = ['pdate', 'ardate', 'persiaPay', 'stripe', 'paypal'];
const badF = Object.keys(pal).filter((t) => !addonFields.includes(t) && F(free, 'field:' + t).package !== (pal[t].pro ? 'free_plus' : 'free'));
c.check(`every palette field (${Object.keys(pal).length}) has the level of fields_efb[].pro`, badF.length === 0, badF);
c.check('EMSFB_API_VERSION = 1 in emsfb.php', /define\("EMSFB_API_VERSION", 1\);/.test(fs.readFileSync(path.join(ROOT, 'emsfb.php'), 'utf8')));

(async () => {
  // JS: gates and dialogs read the registry.
  const h = await openBuilder({ builder: true, pro: 0, packageType: 2, form: blankForm(), extraVar: { capabilities: free.caps, api_version: 1 } });
  const { page } = h;
  const r = await page.evaluate(async () => {
    const out = {};
    window.__calls = [];
    pro_show_efb = (s) => __calls.push(s);
    alert_message_efb = () => __calls.push('alert');
    out.freeRedirect = efb_capability_available_efb('setting:thank_you_redirect', true);
    out.freeState = efb_capability_state_efb('setting:thank_you_redirect', 1);
    show_setting_window_efb('formSet');
    await new Promise((r) => setTimeout(r, 200));
    out.gems = Array.from(document.querySelectorAll('#sideMenuConEfb .pro-version-efb:not(.efb-mobile-pro-gem)')).map((a) => a.getAttribute('onclick'));
    // The registry decides: say the email subject became Pro only; the gate's dialog follows.
    // (efb_var is frozen in the builder; efb_var_patch_efb() is how the page replaces it.)
    const caps = JSON.parse(JSON.stringify(efb_var.capabilities));
    caps.features['setting:email_sub'].package = 'pro';
    caps.features['setting:email_sub'].prompt_state = 1;
    efb_var_patch_efb({ capabilities: caps });
    const el = document.getElementById('FormEmailSubjectEl');
    el.value = 'Hello'; el.dispatchEvent(new Event('change'));
    await new Promise((r) => setTimeout(r, 150));
    out.subjectDialog = __calls.slice();
    window.efbOpenSettingIdEfb = null; document.getElementById('sideBoxEfb').classList.remove('show');
    show_setting_window_efb('formSet');
    await new Promise((r) => setTimeout(r, 200));
    out.subjectGem = document.getElementById('FormEmailSubjectEl').parentElement.querySelector('.pro-version-efb').getAttribute('onclick');
    // A plan change in the page (efb_apply_package_type_efb writes setting.package_type) is honoured.
    efb_apply_package_type_efb(3);
    out.afterChange = efb_capability_available_efb('setting:thank_you_redirect', false);
    out.smsAfterChange = efb_capability_available_efb('setting:sms_notifications', true);
    out.fallback = efb_capability_available_efb('setting:not_in_registry', 'FB');
    return out;
  });
  c.check('JS: Free has no redirect and its dialog is state 3', r.freeRedirect === false && r.freeState === 3, r);
  c.check('JS: form-settings gems take their state from the registry', r.gems.length >= 3 && r.gems.every((g) => /pro_show_efb\(3\)/.test(g)), r.gems);
  c.check('JS: change the registry entry and the gate opens the dialog it names', JSON.stringify(r.subjectDialog) === '[1]' && /pro_show_efb\(1\)/.test(r.subjectGem), r);
  c.check('JS: a plan change in the page is honoured; SMS still needs Pro', r.afterChange === true && r.smsAfterChange === false, r);
  c.check('JS: a key the registry does not know keeps the caller fallback (old cached page)', r.fallback === 'FB');
  c.check('no page errors', h.errors.length === 0 && h.bootErrors.length === 0, h.errors);
  await h.browser.close();
  c.done();
})();
