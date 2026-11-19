/**
 * Help links of the add-on cards and settings: link_url_emsFormBuilder() (admin-efb.js).
 *
 * BUG: the add-on cards call Link_emsFormBuilder('<add-on key>'); AdnPAP (PayPal), AdnTLG (Telegram) and AdnATF
 * (auto-populate) had no case, so they opened the bare documents root. On fa_IR, 'cachePlugin' did `link + …` (no
 * assignment) and opened the documents root too. Now each opens its own page, on the visitor's language subdomain
 * (de. / ar.) like the other English links; fa_IR keeps the English page as 'paypal' did.
 * Run: node tests/test-doc-links.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

let passed = 0, failed = 0;
function t(name, actual, expected) {
  if (actual === expected) { console.log('[PASS] ' + name); passed++; return; }
  console.log('[FAIL] ' + name + ': expected ' + JSON.stringify(expected) + ', got ' + JSON.stringify(actual)); failed++;
}

const src = fs.readFileSync(path.join(__dirname, '../includes/admin/assets/js/admin-efb.js'), 'utf8');
function grab(name) {
  const start = src.indexOf('function ' + name + '(');
  if (start < 0) throw new Error(name + ' not found');
  let i = src.indexOf('{', start), depth = 0;
  for (; i < src.length; i++) {
    if (src[i] === '{') depth++;
    else if (src[i] === '}' && --depth === 0) return src.slice(start, i + 1);
  }
  throw new Error(name + ' not closed');
}
const code = grab('lan_subdomain_wsteam_efb') + '\n' + grab('link_url_emsFormBuilder') + '\nreturn link_url_emsFormBuilder;';
function linkFor(language, state) {
  const efb_var = { language };
  const valj_efb = [];
  return new Function('efb_var', 'valj_efb', code)(efb_var, valj_efb)(state);
}

const pages = {
  AdnPAP: 'whitestudio.team/document/how-to-setup-paypal-payment-in-easy-form-builder/',
  paypal: 'whitestudio.team/document/how-to-setup-paypal-payment-in-easy-form-builder/',
  AdnTLG: 'whitestudio.team/document/wordpress-form-telegram-notifications/',
  AdnATF: 'whitestudio.team/advanced-addons/wordpress-easy-form-builder-auto-populate/',
};
for (const [state, page] of Object.entries(pages)) {
  t(`${state} (en_US) opens its own page`, linkFor('en_US', state), 'https://' + page);
  t(`${state} (de_DE) opens the German site's page`, linkFor('de_DE', state), 'https://de.' + page);
  t(`${state} (ar) opens the Arabic site's page`, linkFor('ar', state), 'https://ar.' + page);
  t(`${state} (fa_IR) opens the page`, linkFor('fa_IR', state), 'https://' + page);
}
t('no add-on key opens the bare documents root', ['AdnPAP', 'AdnTLG', 'AdnATF'].every((k) => !/\/document\/?$/.test(linkFor('en_US', k))), true);
t('fa_IR cachePlugin opens its page, not the documents root', linkFor('fa_IR', 'cachePlugin'), 'https://easyformbuilder.ir/داکیومنت/جلوگیری-از-کش-شدن-فرم-ساخته-توسط-فرم-ساز/');
t('stripe is unchanged', linkFor('en_US', 'stripe'), 'https://whitestudio.team/documents/how-to-setup-and-use-the-stripe-on-easy-form-builder');

console.log(`RESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
