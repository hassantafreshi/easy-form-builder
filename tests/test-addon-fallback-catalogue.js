/**
 * QA3-007 / QA1-014: the Add-ons page when the add-on server's catalogue (addons.js) does not arrive.
 *  - the fallback cards are built from efb_var.addon_catalog (Emsfb\Addon_Catalog), so every current add-on is there:
 *    the 7 PAY00 gateways, the 5 CAPTCHA add-ons, Persia Payment, Visitor Country, the SPLIT ones; codes without files
 *    (AdnATC …) get no card; a one-word product name (hCaptcha, ALTCHA) is not read as a phrase key;
 *  - nothing waits ~30 s any more: the page waits for the script's load / error, at most a few seconds;
 *  - a card's search tags are no bare classes ("hidden" hid Visitor Country, "map" stretched a row);
 *  - the catalogue script is async (never holds the page).
 *
 * Run: node tests/test-addon-fallback-catalogue.js
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.join(__dirname, '..');
let passed = 0, failed = 0;
const t = (name, ok, detail) => { if (ok) { passed++; console.log('[PASS] ' + name); } else { failed++; console.log('[FAIL] ' + name + (detail ? ' -- ' + detail : '')); } };

const js = fs.readFileSync(path.join(root, 'includes/admin/assets/js/admin-efb.js'), 'utf8');
const php = fs.readFileSync(path.join(root, 'includes/class-Emsfb-addon-catalog.php'), 'utf8');
const addonPhp = fs.readFileSync(path.join(root, 'includes/admin/class-Emsfb-addon.php'), 'utf8');

// The catalogue as efb_var.addon_catalog carries it (code => name, package, contract), from Addon_Catalog::ADDONS.
const catalog = {};
const dirs = {};
for (const m of php.matchAll(/'(Adn[A-Za-z]{2,5})'\s*=>\s*array\('dir' => '([^']*)', 'name' => '([^']*)', 'package' => (\d), 'contract' => (null|'[a-z_]+')/g)) {
  catalog[m[1]] = { name: m[3], package: Number(m[4]), contract: m[5] === 'null' ? null : m[5].slice(1, -1), help: '', screen: '', state: '' };
  dirs[m[1]] = m[2];
}
t('the catalogue was read (' + Object.keys(catalog).length + ' codes)', Object.keys(catalog).length >= 35);

const grab = (re) => { const m = js.match(re); return m ? m[0] : ''; };
const ui = grab(/const efb_addon_card_ui_efb = \{[\s\S]*?\n\};/);
const fallback = grab(/function fun_default_addons_efb_admin\(\) \{[\s\S]*?\n\}/);
const resolve = grab(/function efb_resolve_addon_display_efb\(i\) \{[\s\S]*?\n\}/);
t('the fallback, its words table and the resolver are in admin-efb.js', ui !== '' && fallback !== '' && resolve !== '');
const ctx = { efb_var: { addon_catalog: catalog, text: { smsTAddon: 'SMS service Add-on', mollieTAddon: 'Mollie payments', addonPayDescT: 'Take payments in your forms with %s.', addonCaptchaDescT: 'Protect your forms from spam with %s.' } } };
vm.createContext(ctx);
vm.runInContext(ui.replace('const efb_addon_card_ui_efb', 'var efb_addon_card_ui_efb') + '\n' + fallback + '\n' + resolve + '\nvar out = fun_default_addons_efb_admin();', ctx);
const names = ctx.out.map((a) => a.name);
const real = Object.keys(dirs).filter((c) => dirs[c] !== '');
const missing = real.filter((c) => !names.includes(c));
t('QA3-007: every add-on with files has a fallback card (PAY00, CAPTCHA, Persia Payment, Visitor Country, SPLIT)', missing.length === 0, 'missing ' + missing.join(','));
for (const c of ['AdnMOL', 'AdnPPG', 'AdnTAP', 'AdnOMS', 'AdnPTB', 'AdnPMB', 'AdnXND', 'AdnCTS', 'AdnHCP', 'AdnALT', 'AdnQCP', 'AdnFRC', 'AdnPPF', 'AdnGEO']) {
  if (!names.includes(c)) t(c + ' in the fallback', false);
}
t('... and codes without files (AdnATC, AdnWHS …) get none', !names.includes('AdnATC') && !names.includes('AdnWHS') && !names.includes('AdnMSF'));
t('... with the plan of the catalogue and no version gate (the server checks it)', ctx.out.every((a) => a.package === catalog[a.name].package && a.v_required === 0));
const hcp = ctx.out.find((a) => a.name === 'AdnHCP');
const shown = vm.runInContext('efb_resolve_addon_display_efb(' + JSON.stringify(hcp) + ')', ctx);
t('a one-word product name (hCaptcha) stays as it is, with a sentence naming it', shown.title === 'hCaptcha' && shown.desc === 'Protect your forms from spam with hCaptcha.', JSON.stringify(shown));
const mol = ctx.out.find((a) => a.name === 'AdnMOL');
t('a gateway without its own description gets one naming it', mol.desc === 'Take payments in your forms with Mollie payments.', mol.desc);

// The wait.
t('QA3-007: the ~30 s polling is gone', js.indexOf('addon_wait_attempts_efb') === -1);
const wait = js.match(/const EFB_ADDONS_WAIT_MS = (\d+);/);
t('... the page waits for the catalogue at most a few seconds', !!wait && Number(wait[1]) <= 6000, wait ? wait[1] : 'none');
t('... and shows the fallback as soon as the script fails', /tag\.addEventListener\('error', show\)/.test(js));
t('QA1-014: the catalogue script is loaded async (a dead host never holds the page)', addonPhp.indexOf("array('in_footer' => true, 'strategy' => 'async')") !== -1);
t('the search tags are prefixed classes (efb-tag-…), never bare words such as hidden or map', js.indexOf("map((w) => 'efb-tag-' + w)") !== -1 && js.indexOf('col ${efb_var.rtl == 1 ? \'rtl-text\' : \'\'} ${i.tag}"') === -1);

console.log(`\nRESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
