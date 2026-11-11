/**
 * AutoPilot B8: installing an add-on updates the page and can avoid an immediate reload.
 *
 * Server: Builder_Api::addon_install_response() (the add_addons_Emsfb answer) keeps r = done and adds
 * the fresh add-on flags, the capability registry and requires_reload; the old `new` key no longer
 * carries the Stripe secret, the licence code or other secrets. Browser (real builder scripts):
 * actionSendAddons_efb() patches efb_var.addons / efb_var.setting / efb_var.capabilities, dispatches
 * a cancelable efb:addon-installed, and reloads only when no listener cancelled it.
 *
 * Run: node tests/test-ap-b8-addon-installed.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { openBuilder, makeChecker, blankForm, ROOT } = require('./ap-builder-harness.js');
const c = makeChecker();

const php = JSON.parse(execFileSync('php', ['-r', `
  define('ABSPATH','/'); define('EMSFB_API_VERSION',1); define('EMSFB_PLUGIN_VERSION','4.3.1');
  function get_option($k,$d=false){ return $k==='emsfb_pro' ? 1 : $d; } function add_filter(){}
  class F8 { function fun_get_addons_list_efb($ac){ $o=array(); foreach(array('AdnSS','AdnSPF','AdnTLG') as $k) $o[$k]=isset($ac->$k)?intval($ac->$k):0; return $o; }
    function get_all_addon_keys_efb(){ return array('AdnSS','AdnSPF','AdnTLG'); } function addon_required_package_efb($a){ return $a==='AdnSPF'?3:1; } }
  require '${path.join(ROOT, 'includes/class-Emsfb-builder-api.php').replace(/\\/g, '/')}';
  $ac = (object) array('AdnSS'=>1,'AdnSPF'=>0,'stripeSKey'=>'sk_live_SECRET','stripePKey'=>'pk_live_x','paypalSKey'=>'PPSECRET','activeCode'=>'LICENCE123','secretKey'=>'RECAPTCHASECRET','siteKey'=>'site','smtp'=>'1','emailSupporter'=>'a@b.c','payToken'=>'TOKEN1');
  echo json_encode(\\Emsfb\\Builder_Api::addon_install_response('AdnSS', $ac, new F8()));
`]).toString());
c.check('server: r = done, the addon, the fresh flags, requires_reload', php.r === 'done' && php.success === true && php.addon === 'AdnSS' && php.addons.AdnSS === 1 && php.requires_reload === true, php.addons);
c.check('server: the capability registry is included (SMS now installed)', php.capabilities && php.capabilities.features['addon:AdnSS'].installed === true);
const all = JSON.stringify(php);
c.check('server: no secret in the answer (old `new` key redacted)', !/sk_live_SECRET|PPSECRET|LICENCE123|RECAPTCHASECRET|TOKEN1/.test(all) && JSON.parse(php.new).siteKey === 'site' && JSON.parse(php.new).stripePKey === 'pk_live_x');
const adm = fs.readFileSync(path.join(ROOT, 'includes/admin/class-Emsfb-admin.php'), 'utf8');
c.check('add_addons_Emsfb() answers through addon_install_response()', /\$response = Builder_Api::addon_install_response\(\$post_value, \$ac, \$efbFunction\);/.test(adm));

(async () => {
  for (const cancel of [true, false]) {
    const h = await openBuilder({ builder: true, pro: 1, packageType: 1, setting: { AdnSS: 0 }, addons: { AdnSS: 0 }, form: blankForm(),
      extraVar: { capabilities: { features: { 'addon:AdnSS': { installed: false } } } },
      ajax: (body) => (body.includes('action=add_addons_Emsfb') ? { success: true, r: 'done', value: 'add_addons_Emsfb', addon: 'AdnSS', addons: { AdnSS: 1 }, capabilities: { features: { 'addon:AdnSS': { installed: true } } }, requires_reload: true } : undefined) });
    const { page } = h;
    let navigations = 0;
    page.on('framenavigated', (f) => { if (f === page.mainFrame()) navigations++; });
    await page.evaluate((cancel) => {
      window.__ev = null;
      document.addEventListener('efb:addon-installed', (e) => { window.__ev = e.detail; if (cancel) e.preventDefault(); });
      actionSendAddons_efb('AdnSS');
    }, cancel);
    await page.waitForTimeout(1500);
    if (cancel) {
      const r = await page.evaluate(() => ({ ev: window.__ev, addons: efb_var.addons.AdnSS, setting: efb_var.setting.AdnSS, caps: efb_var.capabilities.features['addon:AdnSS'].installed }));
      c.check('browser: efb:addon-installed carries the addon and the flags', r.ev && r.ev.addon === 'AdnSS' && r.ev.addons.AdnSS === 1 && r.ev.requires_reload === true, r);
      c.check('browser: efb_var.addons, efb_var.setting and efb_var.capabilities are fresh', r.addons === 1 && r.setting === 1 && r.caps === true, r);
      c.check('browser: a listener that cancels keeps the page (no reload)', navigations === 0, navigations);
    } else {
      c.check('browser: without a cancelling listener the page reloads as before', navigations >= 1, navigations);
    }
    await h.browser.close();
  }
  c.done();
})();
