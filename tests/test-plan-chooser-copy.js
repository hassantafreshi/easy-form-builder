/**
 * FR-01: the first-run plan chooser (val-efb.js show_setting_up_easy_form_builder_Efb()) says what each plan gets,
 * the way the add-on catalogue (includes/class-Emsfb-addon-catalog.php, package 0 = every plan, 3 = Free Plus,
 * 1 = Pro) gives it.
 *
 *  - one badge: "Recommended" on Free Plus; no "Most Popular" on Pro;
 *  - Free Plus: payments are free add-ons there (Stripe and PayPal are package 3 in the catalogue), so no locked
 *    "Add-ons & extensions"; its locked line is the Pro add-ons (SMS, Telegram, Google Sheets, booking: package 1);
 *    the credit sentence says what it is ("Shows a small “Powered by Easy Form Builder” link"), not "supported by a
 *    credit line";
 *  - Free: the free add-ons it really has (Offline Forms, Address & Postal: package 0);
 *  - Pro: no lower-case "premium experience"; Human Shield (package 1) and every add-on;
 *  - every sentence is a phrase of the phrase list (functions.php, with a translators comment) and the screen renders
 *    them from efb_var.text; icons are aria-hidden and a locked line says "Not included:" to a screen reader.
 *
 * Run: node tests/test-plan-chooser-copy.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
let pass = 0, fail = 0;
const t = (name, actual, expected) => {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + name); } else { fail++; console.log('[FAIL] ' + name + ': expected ' + JSON.stringify(expected) + ', got ' + JSON.stringify(actual)); }
};

const val = fs.readFileSync(path.join(root, 'includes/admin/assets/js/val-efb.js'), 'utf8');
const fnPhp = fs.readFileSync(path.join(root, 'includes/functions.php'), 'utf8');
const cat = fs.readFileSync(path.join(root, 'includes/class-Emsfb-addon-catalog.php'), 'utf8');

/* The phrase list's English, as efb_var.text carries it. */
const text = {};
const phraseRe = /"([A-Za-z0-9_]+)" => \$state && isset\(\$ac->text->\1\) \? \$ac->text->\1 : esc_html__\('((?:[^'\\]|\\.)*)','easy-form-builder'\)/g;
let m;
while ((m = phraseRe.exec(fnPhp))) text[m[1]] = m[2].replace(/\\'/g, "'");
const start = val.indexOf('function show_setting_up_easy_form_builder_Efb()');
const end = val.indexOf('\nfunction efb_plan_downgrade_copy_efb', start);
t('the plan chooser function is found', start > 0 && end > start, true);
const src = val.slice(start, end);
const html = new Function('efb_var', src + '\nreturn show_setting_up_easy_form_builder_Efb();')({ text, setting: {} });
const cards = html.split('<div class="efb-plan-card').slice(1).map((c) => c.split('<div class="efb-plan-action">')[0]);
t('three plan cards', cards.length, 3);
const strip = (s) => s.replace(/<style[\s\S]*?<\/style>/g, '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
const lines = (c, kind) => (c.match(new RegExp('<li class="efb-feature-item efb-feature-' + kind + '">[\\s\\S]*?</li>', 'g')) || []).map(strip);
const [free, plus, pro] = cards;

t('one badge: "Recommended" on Free Plus', (html.match(/efb-recommended-badge">/g) || []).length === 1 && plus.includes('efb-recommended-badge'), true);
t('no "Most Popular" badge (and no efb-pro-badge element)', /Most Popular|class="efb-pro-badge"/i.test(html), false);
t('no lower-case "premium experience", no "credit line"', /premium experience|credit line/i.test(strip(html)), false);

t('Free Plus: payments are its free add-ons', lines(plus, 'included').includes('Payments: Stripe, PayPal and other gateways (free add-ons)'), true);
t('Free Plus: no locked "Add-ons & extensions"; its locked line is the Pro add-ons', lines(plus, 'locked'), ['Not included: Pro add-ons: SMS, Telegram, Google Sheets, booking and more']);
t('Free Plus: the credit link, said plainly', lines(plus, 'info'), ['Shows a small “Powered by Easy Form Builder” link']);
t('Free Plus: its description names the credit link, not "a credit line"', strip(plus).includes('Advanced fields and features at no cost, with a small credit link on your forms.'), true);
t('Free: the free add-ons it has', lines(free, 'included').includes('Free add-ons: offline forms, addresses and postal codes'), true);
t('Free: locked advanced fields and the Free Plus add-ons', lines(free, 'locked'), ['Not included: Advanced form fields', 'Not included: Payments and the other Free Plus add-ons']);
t('Pro: everything in Free Plus, every add-on, Human Shield, no credits', lines(pro, 'included'), ['Everything in Free Plus', 'Every add-on: SMS, Telegram, Google Sheets, booking and more', 'Human Shield security and spam protection', 'No credits or promotional messages']);

/* The catalogue says the same. */
const pkg = {};
for (const x of cat.matchAll(/'(Adn[A-Za-z]+)'\s*=>\s*array\('dir' => '[^']*', 'name' => '([^']*)', 'package' => (\d)/g)) pkg[x[1]] = Number(x[3]);
t('catalogue: Stripe and PayPal are Free Plus add-ons (package 3)', [pkg.AdnSPF, pkg.AdnPAP], [3, 3]);
t('catalogue: conditional logic, calculations and the CAPTCHA add-ons are Free Plus', [pkg.AdnSMF, pkg.AdnCLC, pkg.AdnCTS, pkg.AdnHCP], [3, 3, 3, 3]);
t('catalogue: SMS, Telegram, Google Sheets, booking, Human Shield are Pro (package 1)', [pkg.AdnSS, pkg.AdnTLG, pkg.AdnGoS, pkg.AdnBEF, pkg.AdnHSH], [1, 1, 1, 1, 1]);
t('catalogue: Offline Forms and Address & Postal are on every plan (package 0)', [pkg.AdnOF, pkg.AdnADR], [0, 0]);

/* Phrases and accessibility. */
const keys = [...new Set([...src.matchAll(/efb_var\.text\.([A-Za-z0-9_]+)/g)].map((x) => x[1]))];
t('every sentence of the chooser is in the phrase list', keys.filter((k) => !(k in text)), []);
const newKeys = ['planFreeAddons', 'planFreePlusAddonsLocked', 'planFreePlusDesc', 'planPaymentsFreeAddons', 'planFreePlusAddons', 'planProAddonsLocked', 'planPoweredLink', 'planProAllAddons', 'planProSecurity', 'planNotIncluded'];
t('the new phrases have a translators comment', newKeys.filter((k) => !new RegExp('/\\* translators:[^*]*\\*/\\s*"' + k + '" =>').test(fnPhp)), []);
t('the replaced phrases left the list', ['premiumExperience', 'mostPopular', 'unlockAdvancedFeatures', 'planStripePaypal', 'poweredByCredit', 'advancedIntegrations', 'addonsIncluded', 'addonsExtensions'].filter((k) => k in text), []);
t('every feature icon is aria-hidden', (html.match(/<i class="bi bi-(check-circle-fill|lock-fill|info-circle-fill)"(?! aria-hidden="true")/g) || []).length, 0);

console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
