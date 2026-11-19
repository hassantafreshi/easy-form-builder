/**
 * Help links of the add-on cards and the builder: link_url_emsFormBuilder() and efb_help_url_efb() (admin-efb.js) with
 * the help pages of the add-on catalogue (Emsfb\Addon_Catalog::HELP, efb_var.addon_catalog[code].help and
 * efb_var.help_topics).
 *
 * BUG (d2b12a0b): AdnPAP (PayPal), AdnTLG (Telegram) and AdnATF (auto-populate) had no case, so they opened the bare
 * documents root. On fa_IR, 'cachePlugin' did `link + …` (no assignment) and opened the documents root too.
 * BUG (HELP-01): 29 add-on keys and the builder's 'condi' and 'pickupByUser' still opened the documents root on en / de /
 * ar sites (37 on fa_IR; AdnATC / AdnCPF / AdnESZ an empty `break`, AdnPPF the home page, AdnSE a 404). Now every
 * address comes from the catalogue: an add-on's own page in the site's language, or '' when it has none, and then its
 * Help is hidden (owner decision 8) and nothing opens; the card's Help is a real link (it opened two tabs).
 * Run: node tests/test-doc-links.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

let passed = 0, failed = 0;
function t(name, actual, expected) {
  if (actual === expected) { console.log('[PASS] ' + name); passed++; return; }
  console.log('[FAIL] ' + name + ': expected ' + JSON.stringify(expected) + ', got ' + JSON.stringify(actual)); failed++;
}

const ROOT = path.join(__dirname, '..');
const src = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/admin-efb.js'), 'utf8');
function grab(name) {
  const start = src.indexOf('function ' + name + '(');
  if (start < 0) return 'function ' + name + '() { throw new Error("' + name + ' not found"); }';
  let i = src.indexOf('{', start), depth = 0;
  for (; i < src.length; i++) {
    if (src[i] === '{') depth++;
    else if (src[i] === '}' && --depth === 0) return src.slice(start, i + 1);
  }
  throw new Error(name + ' not closed');
}
const code = ['efb_help_url_efb', 'lan_subdomain_wsteam_efb', 'link_url_emsFormBuilder', 'Link_emsFormBuilder', 'efb_addon_help_link_efb'].map(grab).join('\n')
  + '\nreturn { link: link_url_emsFormBuilder, open: Link_emsFormBuilder, card: efb_addon_help_link_efb };';

/** The catalogue as the page gets it (Addon_Catalog::js_mirror() and help_topics() for a locale). */
const catalog = {};
function catalogFor(locale) {
  if (catalog[locale]) return catalog[locale];
  const php = 'define("ABSPATH", "/"); function get_locale() { return ' + JSON.stringify(locale) + '; } require ' + JSON.stringify(path.join(ROOT, 'includes/class-Emsfb-addon-catalog.php')) + ';'
    + ' echo json_encode(array("cat" => \\Emsfb\\Addon_Catalog::js_mirror(), "topics" => method_exists("\\\\Emsfb\\\\Addon_Catalog", "help_topics") ? \\Emsfb\\Addon_Catalog::help_topics() : null, "keys" => \\Emsfb\\Addon_Catalog::keys()));';
  catalog[locale] = JSON.parse(execFileSync('php', ['-r', php], { encoding: 'utf8' }));
  return catalog[locale];
}
const opened = [];
const fakeWindow = { open: (u) => { opened.push(u); return null; } };
const fakeDocument = { createElement: () => { const o = { set innerHTML(v) { o.value = String(v).replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&amp;/g, '&'); }, value: '' }; return o; } };
function api(language, withCatalog = true) {
  const c = catalogFor(language);
  const efb_var = { language, text: { help: 'Help', upgNewTab: '(opens in a new tab)' } };
  if (withCatalog) { efb_var.addon_catalog = c.cat; efb_var.help_topics = c.topics; }
  return new Function('efb_var', 'valj_efb', 'window', 'document', code)(efb_var, [], fakeWindow, fakeDocument);
}
const linkFor = (language, state) => api(language).link(state);

/** A documentation index or a home page, never a page of its own. */
const INDEX = /^https:\/\/([a-z]+\.)?(whitestudio\.team|easyformbuilder\.ir)\/?((document|documents|advanced-addons|%d8%af%d8%a7%da%a9%db%8c%d9%88%d9%85%d9%86%d8%aa|داکیومنت|addons)\/?)?(#.*)?$/i;

const pages = {
  AdnPAP: 'whitestudio.team/document/how-to-setup-paypal-payment-in-easy-form-builder/',
  paypal: 'whitestudio.team/document/how-to-setup-paypal-payment-in-easy-form-builder/',
  AdnTLG: 'whitestudio.team/document/wordpress-form-telegram-notifications/',
  AdnATF: 'whitestudio.team/advanced-addons/wordpress-easy-form-builder-auto-populate/',
  AdnSMF: 'whitestudio.team/advanced-addons/easy-form-builder-conditional-logic-complete-guide/',
  condi: 'whitestudio.team/advanced-addons/easy-form-builder-conditional-logic-complete-guide/',
};
for (const [state, page] of Object.entries(pages)) {
  t(`${state} (en_US) opens its own page`, linkFor('en_US', state), 'https://' + page);
  t(`${state} (de_DE) opens the German site's page`, linkFor('de_DE', state), 'https://de.' + page);
  t(`${state} (ar) opens the Arabic site's page`, linkFor('ar', state), 'https://ar.' + page);
  t(`${state} (fa_IR) opens the page`, linkFor('fa_IR', state), 'https://' + page);
}
t('fa_IR cachePlugin opens its page, not the documents root', linkFor('fa_IR', 'cachePlugin'), 'https://easyformbuilder.ir/داکیومنت/جلوگیری-از-کش-شدن-فرم-ساخته-توسط-فرم-ساز/');
t('stripe is unchanged', linkFor('en_US', 'stripe'), 'https://whitestudio.team/documents/how-to-setup-and-use-the-stripe-on-easy-form-builder');

// HELP-01: every key of the catalogue, in four languages: its own page, or '' (no page: Help hidden). Never an index.
for (const lang of ['en_US', 'de_DE', 'ar', 'fa_IR']) {
  const c = catalogFor(lang);
  const bad = [];
  let withPage = 0;
  for (const key of c.keys) {
    const url = linkFor(lang, key);
    if (url === '') continue;
    withPage++;
    if (typeof url !== 'string' || !/^https:\/\//.test(url) || INDEX.test(url)) bad.push(key + ' => ' + url);
  }
  t(`${lang}: every one of the ${c.keys.length} catalogue keys opens its own page or has none (Help hidden), never an index or a home page`, bad.join(' | '), '');
  t(`${lang}: the catalogue has the help pages that exist (at least 8 add-ons)`, withPage >= 8, true);
  for (const topic of ['condi', 'pickupByUser']) {
    const url = linkFor(lang, topic);
    t(`${lang}: the builder topic '${topic}' opens its own page`, typeof url === 'string' && /^https:\/\//.test(url) && !INDEX.test(url), true);
  }
}
t('pickupByUser (en_US) opens the location picker guide', linkFor('en_US', 'pickupByUser'), 'https://whitestudio.team/document/how-to-install-and-use-the-location-picker-geolocation-with-easy-form-builder/');
t('pickupByUser (fa_IR) opens the Persian map picker guide', decodeURIComponent(linkFor('fa_IR', 'pickupByUser')), 'https://easyformbuilder.ir/داکیومنت/چگونه-مکانیاب-انتخاب-گر-نقشه-فرم-افزون/');
t('AdnSS (fa_IR) opens the Persian SMS guide', decodeURIComponent(linkFor('fa_IR', 'AdnSS')), 'https://easyformbuilder.ir/داکیومنت/تنظیم-اطلاع-رسانی-پیامک-وردپرس-فرم-ساز/');
t('AdnPPF (fa_IR) opens the Persian payment-gateway guide (the old address answers 404)', decodeURIComponent(linkFor('fa_IR', 'AdnPPF')), 'https://easyformbuilder.ir/داکیومنت/ساخت-فرم-پرداخت-اتصال-درگاه-در-وردپرس/');
t('AdnPPF (en_US) has no English page: no Help (it opened the home page)', linkFor('en_US', 'AdnPPF'), '');
for (const k of ['AdnATC', 'AdnCPF', 'AdnESZ', 'AdnSE', 'AdnGoS', 'AdnBEF', 'AdnMOL', 'AdnCTS', 'AdnHSH']) {
  t(`${k} has no page yet: '' (Help hidden), in en_US and fa_IR`, [linkFor('en_US', k), linkFor('fa_IR', k)].join('|'), '|');
}
t('a page cached before the catalogue had help (no efb_var.addon_catalog): an add-on key gives \'\', not the documents root', api('en_US', false).link('AdnGoS'), '');

// Link_emsFormBuilder: nothing opens for a topic without a page; a page opens in a new tab without an opener.
opened.length = 0;
api('en_US').open('AdnGoS');
t('Link_emsFormBuilder opens nothing for an add-on without a page', opened.length, 0);
api('en_US').open('AdnPAP');
t('Link_emsFormBuilder opens the add-on\'s page', opened[0], 'https://' + pages.AdnPAP);

// The add-on card: a real link (href, new tab, noopener, the add-on named for screen readers) or no Help at all.
const card = api('en_US').card({ name: 'AdnPAP', title: 'PayPal &amp; cards' });
t('add-on card with a page: Help is a link to it in a new tab (rel noopener)', /<a [^>]*href="https:\/\/whitestudio\.team\/document\/how-to-setup-paypal-payment-in-easy-form-builder\/"[^>]*target="_blank"[^>]*rel="noopener noreferrer"/.test(card), true);
t('add-on card Help: no onclick and no ec-efb (one tab, not two)', !/onclick|ec-efb/i.test(card), true);
t('add-on card Help: screen readers hear the add-on and that a tab opens', /visually-hidden">\s*PayPal &amp; cards \(opens in a new tab\)</.test(card), true);
t('add-on card without a page: no Help at all', api('en_US').card({ name: 'AdnGoS', title: 'Google Sheets' }), '');
t('the cards no longer call Link_emsFormBuilder(\'${i.name}\') from an onclick', /onClick="Link_emsFormBuilder\('\$\{i\.name\}'\)"/.test(src), false);

console.log(`RESULTS: ${passed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
