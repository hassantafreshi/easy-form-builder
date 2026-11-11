/**
 * AutoPilot A3: the redirect address shows and re-saves correctly.
 *
 * rePage is stored with "/" written as @efb@ ("https:@efb@example.com@efb@thanks"). The form
 * settings panel decoded it with a single replace(/(@efb@)+/g, '/'), which shows
 * "https:/example.com/thanks" (one slash). Editing that field again failed the ^https?:// check
 * and the form silently fell back to "show a message".
 *
 * Part 1 runs the encode/decode pair from new-efb.js (and checks it agrees with the server's
 * string_to_url()); part 2 opens the real form-settings panel in Chromium.
 *
 * Run: node tests/test-ap-a3-redirect-url.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { openBuilder, makeChecker, blankForm, ROOT } = require('./ap-builder-harness.js');

const c = makeChecker();
const newEfb = fs.readFileSync(path.join(ROOT, 'includes/admin/assets/js/new-efb.js'), 'utf8');
const block = (newEfb.match(/\/\* efbUrlCodec:start \*\/[\s\S]*?\/\* efbUrlCodec:end \*\//) || [null])[0];
c.check('new-efb.js defines the URL codec pair', !!block);

const samples = ['https://example.com/thanks', 'http://example.com/a/b?x=1&y=/z', 'https://www.example.com/', 'https://example.com'];
if (block) {
  const { efb_url_to_storage_efb, efb_url_from_storage_efb } = new Function(block + '; return { efb_url_to_storage_efb, efb_url_from_storage_efb };')();
  for (const u of samples) {
    const s = efb_url_to_storage_efb(u);
    c.check('stored form has no slash: ' + u, !s.includes('/') && s.includes('@efb@'), s);
    c.check('round trip: ' + u, efb_url_from_storage_efb(s) === u, efb_url_from_storage_efb(s));
  }
  c.check('legacy stored value decodes with both slashes', efb_url_from_storage_efb('https:@efb@example.com@efb@thanks') === 'https://example.com/thanks');
  c.check('a plain URL (not encoded) is read as it is', efb_url_from_storage_efb('https://example.com/thanks') === 'https://example.com/thanks');
  c.check('a value broken by the old display (one slash) is repaired', efb_url_from_storage_efb('https:/example.com/thanks') === 'https://example.com/thanks');
  c.check('empty / non-string', efb_url_from_storage_efb('') === '' && efb_url_from_storage_efb(undefined) === '' && efb_url_to_storage_efb(null) === '');
  // Server twin: Emsfb\_Public::string_to_url() decodes what the builder stores.
  let php = null;
  try {
    const src = fs.readFileSync(path.join(ROOT, 'includes/class-Emsfb-public.php'), 'utf8');
    const m = src.match(/public function string_to_url\(\$string\) \{[\s\S]*?\n\t}\n/);
    php = JSON.parse(execFileSync('php', ['-r', `class T { ${m[0]} } $t = new T(); echo json_encode(array_map(function($s){ return (new T())->string_to_url($s); }, json_decode($argv[1], true)));`, JSON.stringify(samples.map(efb_url_to_storage_efb).concat(['https:/example.com/thanks']))]).toString());
  } catch (e) { php = null; }
  c.check('server string_to_url() decodes the builder encoding to the same URL', Array.isArray(php) && php.join('|') === samples.concat(['https://example.com/thanks']).join('|'), php);
}

(async () => {
  const h = await openBuilder({ builder: true, pro: 1, packageType: 3, form: blankForm({ thank_you: 'rdrct', rePage: 'https:@efb@example.com@efb@thanks' }) });
  const { page } = h;
  await page.evaluate(() => { window.efbOpenSettingIdEfb = null; show_setting_window_efb('formSet'); });
  await page.waitForTimeout(200);
  const shown = await page.evaluate(() => document.getElementById('thankYouredirectEl').value);
  c.check('panel shows the redirect with both slashes', shown === 'https://example.com/thanks', shown);

  // Save it again unchanged (the owner tabs through the field).
  await page.evaluate(() => { const el = document.getElementById('thankYouredirectEl'); el.dispatchEvent(new Event('change')); });
  await page.waitForTimeout(150);
  const after = await page.evaluate(() => ({ rePage: valj_efb[0].rePage, thank_you: valj_efb[0].thank_you }));
  c.check('re-saving keeps the redirect (thank_you stays rdrct)', after.thank_you === 'rdrct', after);
  c.check('re-saving stores the same encoded value', after.rePage === 'https:@efb@example.com@efb@thanks', after);

  // A new address typed by the owner.
  await page.evaluate(() => { const el = document.getElementById('thankYouredirectEl'); el.value = 'https://shop.example.org/order/done?ok=1'; el.dispatchEvent(new Event('change')); });
  await page.waitForTimeout(150);
  const typed = await page.evaluate(() => valj_efb[0].rePage);
  c.check('a typed address is stored in the @efb@ form', typed === 'https:@efb@shop.example.org@efb@order@efb@done?ok=1', typed);
  await page.evaluate(() => { window.efbOpenSettingIdEfb = null; document.getElementById('sideBoxEfb').classList.remove('show'); show_setting_window_efb('formSet'); });
  await page.waitForTimeout(200);
  c.check('and reopens as typed', await page.evaluate(() => document.getElementById('thankYouredirectEl').value) === 'https://shop.example.org/order/done?ok=1');
  c.check('no page errors', h.errors.length === 0 && h.bootErrors.length === 0, h.errors.concat(h.bootErrors));
  await h.browser.close();
  c.done();
})();
