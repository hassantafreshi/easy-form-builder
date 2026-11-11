/**
 * F01-E: public runtime of input masks in Chromium. Two forms on one page, inputs rendered by the real PHP
 * renderer (Formbuilder), the shipped public scripts (jquery, new-efb.js, masks-efb.js, core-efb.js) and
 * ajax_object_efm.masks from \Emsfb\Masks::runtime_subset(). Checks typing with Persian digits, caret in the
 * middle, IME composition, paste with +98 and RTL marks, checksum message and Next blocked, the old tel
 * filter off on masked fields, sensitive values kept out of localStorage, display storage for a custom mask,
 * no cross-talk between the two forms, an RTL page (fa_IR) and a mobile context.
 *
 * Run: node tests/test-masks-browser.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};

const forms = {
  101: [
    { id_: 'mob', type: 'tel', elementId: 'tel', name: 'Mobile', required: '1', mask_preset: 'ir.mobile' },
    { id_: 'nid', type: 'text', elementId: 'text', name: 'National code', required: '1', mask_preset: 'ir.national_id' },
    { id_: 'card', type: 'text', elementId: 'text', name: 'Card', required: '0', mask_preset: 'ir.bank_card' },
    { id_: 'cus', type: 'text', elementId: 'text', name: 'US phone', required: '0', mask_preset: 'custom', mask_custom: '(999) 999-9999', mask_store: 'display' },
    { id_: 'ptel', type: 'tel', elementId: 'tel', name: 'Plain phone', required: '0' },
  ],
  202: [
    { id_: 'smob', type: 'tel', elementId: 'tel', name: 'Saudi mobile', required: '0', mask_preset: 'sa.mobile' },
    { id_: 'alt', type: 'tel', elementId: 'tel', name: 'BR phone', required: '0', mask_preset: 'custom', mask_custom: '(99) 9999-9999||(99) 99999-9999' },
  ],
};
// Markup from the real PHP renderer, and the runtime subset the page would get.
const php = execFileSync('php', ['-r', `
  define('ABSPATH', '/');
  function get_option($o, $d = false) { return $d; }
  function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
  require '${ROOT.replace(/\\/g, '/')}/includes/class-Emsfb-masks.php';
  require '${ROOT.replace(/\\/g, '/')}/includes/class-Emsfb-formbuilder.php';
  $forms = json_decode(stream_get_contents(STDIN), true);
  $m = new ReflectionMethod('\\\\Emsfb\\\\Formbuilder', 'generateElementSpecificFields_efb'); $m->setAccessible(true);
  $out = array('html' => array(), 'masks' => array());
  $ids = array();
  foreach ($forms as $fid => $rows) {
    foreach ($rows as $r) {
      $vj = (object) array_merge(array('placeholder' => '', 'el_border_color' => 'border-d', 'value' => '', 'classes' => ''), $r);
      if (!empty($r['mask_preset']) && $r['mask_preset'] !== 'custom') $ids[] = $r['mask_preset'];
      $b = new \\Emsfb\\Formbuilder(array($vj), false);
      $res = $m->invoke($b, $r['elementId'], $r['id_'], $vj, array('', '', '', 'col-md-12'),
        '', '<label for="' . $r['id_'] . '_">' . $r['name'] . '</label>', '<small id="' . $r['id_'] . '_-message" class="efb text-danger"></small>', '<div id="' . $r['id_'] . '-f">', '', '', $fid, array());
      $out['html'][$fid][] = '<div id="' . $r['id_'] . '" data-formid="' . $fid . '">' . $res['ui'] . '</div></div>';
    }
  }
  $out['masks'] = \\Emsfb\\Masks::runtime_subset($ids, 'fa_IR');
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
`], { input: JSON.stringify(forms) }).toString('utf8');
const rendered = JSON.parse(php);

function page_html(dir) {
  const body = Object.keys(forms).map((fid) => `
  <div id="body_efb_${fid}" class="efb body_efb" data-formid="${fid}" data-currentstep="1" data-steps="1">
    <fieldset data-step="step-1-efb" id="step-1-efb" data-formid="${fid}">${rendered.html[fid].join('\n')}</fieldset>
  </div>`).join('\n');
  const text = { mskFormat_: 'FMT:%1$s:%2$s', mskChecksum_: 'CHK:%s', mskRule_: 'RULE:%s', mskIncomplete_: 'INC:%s', enterThePhones: 'PHONE?', enterTheValueThisField: 'REQ', mmplen: 'min NN', mmxplen: 'max NN' };
  const ajax = {
    ajax_value: JSON.stringify(Object.values(forms)), text, language: dir === 'rtl' ? 'fa_IR' : 'en_US', rtl: dir === 'rtl', form_setting: {}, masks: rendered.masks, nonce: 'n', id: 101,
    ajax_value_forms: Object.keys(forms).map((fid) => ({ id: Number(fid), type: 'form', form_structer: JSON.stringify([{ type: 'form', steps: 1, captcha: 0 }, { type: 'step', id_: '1', step: 1 }].concat(forms[fid].map((r, i) => Object.assign({ step: 1, amount: i + 2, el_border_color: 'border-d' }, r)))) })),
  };
  return `<!doctype html><html dir="${dir}" lang="${dir === 'rtl' ? 'fa' : 'en'}"><head><meta charset="utf-8">
  ${dir === 'rtl' ? '<link rel="stylesheet" href="/f/admin-rtl-efb.css">' : ''}
  <script>var ajax_object_efm = ${JSON.stringify(ajax)}; var efb_var = ajax_object_efm;</script>
  </head><body>${body}
  <script src="/f/jquery.min-efb.js"></script><script src="/f/new-efb.js"></script>
  <script src="/f/masks-efb.js"></script><script src="/f/core-efb.js"></script></body></html>`;
}
const files = {
  'jquery.min-efb.js': path.join(ROOT, 'includes/admin/assets/js/jquery.min-efb.js'),
  'new-efb.js': path.join(ROOT, 'includes/admin/assets/js/new-efb.js'),
  'masks-efb.js': path.join(ROOT, 'public/assets/js/masks-efb.js'),
  'core-efb.js': path.join(ROOT, 'public/assets/js/core-efb.js'),
  'admin-rtl-efb.css': path.join(ROOT, 'includes/admin/assets/css/admin-rtl-efb.css'),
};

async function open(browser, ctxOpts, dir) {
  const ctx = await browser.newContext(ctxOpts);
  const page = await ctx.newPage();
  page.errors = [];
  page.on('pageerror', (e) => page.errors.push(e.message));
  await page.route('**/*', (r) => {
    const u = new URL(r.request().url());
    if (u.pathname.startsWith('/f/')) {
      const f = files[u.pathname.slice(3)];
      return r.fulfill({ status: 200, contentType: f.endsWith('.css') ? 'text/css' : 'application/javascript', body: fs.readFileSync(f) });
    }
    if (u.pathname === '/form') return r.fulfill({ status: 200, contentType: 'text/html', body: page_html(dir) });
    return r.fulfill({ status: 204, body: '' });
  });
  await page.goto('http://site.test/form');
  await page.waitForFunction(() => document.getElementById('mob_') && document.getElementById('mob_').getAttribute('data-efb-mask-bound') === '1', null, { timeout: 8000 });
  return { ctx, page };
}
const sendBack = (page, id) => page.evaluate((i) => { const r = sendBack_emsFormBuilder_pub.find((x) => x.id_ === i); return r ? r.value : null; }, id);
const val = (page, id) => page.evaluate((i) => document.getElementById(i).value, id);
const blur = (page) => page.evaluate(() => { document.activeElement && document.activeElement.blur(); });
async function typeIn(page, id, text) {
  await page.click('#' + id);
  await page.evaluate((i) => { const e = document.getElementById(i); e.value = ''; }, id);
  await page.keyboard.type(text);
}

/* EFB_CHROMIUM picks a build; otherwise Playwright's own, then any installed headless shell
 * (the repository pins an older Playwright than the browsers some machines have). */
async function launchChromiumEfb() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) {
      const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell');
      if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe });
    }
    throw e;
  }
}

(async () => {
  const browser = await launchChromiumEfb();
  let { ctx, page } = await open(browser, {}, 'ltr');

  // Rendered attributes reach the page; only this page's presets are in ajax_object_efm.masks.
  const attrs = await page.evaluate(() => { const e = document.getElementById('mob_'); return { p: e.dataset.maskPreset, im: e.getAttribute('inputmode'), dir: e.getAttribute('dir'), pat: e.hasAttribute('pattern'), keys: Object.keys(ajax_object_efm.masks).sort() }; });
  check('masked tel rendered with data-mask-preset, inputmode=tel, dir=ltr and no pattern', attrs.p === 'ir.mobile' && attrs.im === 'tel' && attrs.dir === 'ltr' && !attrs.pat, attrs);
  check('ajax_object_efm.masks holds only the presets on the page', JSON.stringify(attrs.keys) === JSON.stringify(['ir.bank_card', 'ir.mobile', 'ir.national_id', 'sa.mobile']), attrs.keys);

  // 1. Persian digits typed -> formatted, canonical E.164 in sendBack.
  await typeIn(page, 'mob_', '۰۹۱۲۱۲۳۴۵۶۷');
  check('typing ۰۹۱۲۱۲۳۴۵۶۷ shows 0912 123 4567', (await val(page, 'mob_')) === '0912 123 4567', await val(page, 'mob_'));
  await blur(page);
  await page.waitForTimeout(100);
  check('sendBack holds +989121234567', (await sendBack(page, 'mob')) === '+989121234567', await sendBack(page, 'mob'));

  // 2. Caret stays after the inserted digit.
  await typeIn(page, 'mob_', '091212345');
  await page.evaluate(() => { const e = document.getElementById('mob_'); e.setSelectionRange(6, 6); });
  await page.keyboard.type('3');
  const caret = await page.evaluate(() => { const e = document.getElementById('mob_'); return [e.value, e.selectionStart]; });
  check('insert in the middle keeps the caret after the new digit', caret[0] === '0912 132 345' && caret[1] === 7, caret);

  // 3. IME composition: no rewrite until compositionend.
  const ime = await page.evaluate(() => {
    const e = document.getElementById('mob_'); e.focus(); e.value = '';
    e.dispatchEvent(new CompositionEvent('compositionstart'));
    e.value = '0912123'; e.dispatchEvent(new InputEvent('input', { isComposing: true }));
    const during = e.value;
    e.dispatchEvent(new CompositionEvent('compositionend'));
    return [during, e.value];
  });
  check('IME: value untouched while composing, formatted after', ime[0] === '0912123' && ime[1] === '0912 123', ime);

  // 4. Paste: +98 prefix, RTL marks and Persian dashes.
  await page.click('#mob_'); await page.evaluate(() => { document.getElementById('mob_').value = ''; });
  await page.keyboard.insertText('+98 912 345 6789');
  check('paste +98 912 345 6789 -> 0912 345 6789', (await val(page, 'mob_')) === '0912 345 6789', await val(page, 'mob_'));
  await page.evaluate(() => { document.getElementById('mob_').value = ''; });
  await page.keyboard.insertText('‏۰۹۱۲-۱۲۳-۴۵۶۷‎');
  check('paste with RLM/LRM and Persian digits -> 0912 123 4567', (await val(page, 'mob_')) === '0912 123 4567', await val(page, 'mob_'));
  await page.evaluate(() => { document.getElementById('mob_').value = ''; });
  await page.keyboard.insertText('00989121234567');
  check('paste 0098... -> 0912 123 4567', (await val(page, 'mob_')) === '0912 123 4567', await val(page, 'mob_'));
  await blur(page);

  // 5. The old tel keydown filter is off on the masked field and still on the plain one.
  const kd = await page.evaluate(() => {
    const ev = (id) => { const e = document.getElementById(id); const k = new KeyboardEvent('keydown', { key: 'a', cancelable: true, bubbles: true }); e.dispatchEvent(k); return k.defaultPrevented; };
    return { masked: ev('mob_'), plain: ev('ptel_') };
  });
  check('old tel keydown filter: off on masked tel, on for plain tel', kd.masked === false && kd.plain === true, kd);

  // 6. National code: bad checksum -> message, border, step blocked; good one passes.
  await typeIn(page, 'nid_', '1234567892');
  check('national code formatted while typing', (await val(page, 'nid_')) === '123-456789-2', await val(page, 'nid_'));
  await blur(page); await page.waitForTimeout(100);
  const bad = await page.evaluate(async () => ({ msg: document.getElementById('nid_-message').textContent, cls: document.getElementById('nid_').className, step: await fun_validation_efb_v4('101'), sb: sendBack_emsFormBuilder_pub.some((r) => r.id_ === 'nid') }));
  check('bad national code: checksum message', bad.msg === 'CHK:National code', bad.msg);
  check('bad national code: red border, no sendBack row', bad.cls.includes('border-danger') && !bad.sb, bad);
  check('bad national code: Next/Send blocked', bad.step === false, bad.step);
  await typeIn(page, 'nid_', '۰۰۱۲۳۴۵۶۷۹');
  await blur(page); await page.waitForTimeout(100);
  check('good national code stored as digits', (await sendBack(page, 'nid')) === '0012345679', await sendBack(page, 'nid'));
  check('step passes with valid masked values', (await page.evaluate(() => fun_validation_efb_v4('101'))) === true);

  // 7. Card numbers stay out of localStorage drafts.
  await typeIn(page, 'card_', '6037991234567893');
  check('card formatted 6037 9912 3456 7893', (await val(page, 'card_')) === '6037 9912 3456 7893', await val(page, 'card_'));
  await blur(page); await page.waitForTimeout(100);
  const store = await page.evaluate(() => ({ sb: sendBack_emsFormBuilder_pub.find((r) => r.id_ === 'card'), ls: localStorage.getItem('sendback') || '' }));
  check('card value in sendBack (for submit)', store.sb && store.sb.value === '6037991234567893', store.sb);
  check('card and national code not in localStorage.sendback; mobile is', !store.ls.includes('6037991234567893') && !store.ls.includes('0012345679') && store.ls.includes('+98'), store.ls);

  // 8. Custom mask stored as displayed.
  await typeIn(page, 'cus_', '2025550123');
  await blur(page); await page.waitForTimeout(100);
  check('custom mask display storage', (await val(page, 'cus_')) === '(202) 555-0123' && (await sendBack(page, 'cus')) === '(202) 555-0123', [await val(page, 'cus_'), await sendBack(page, 'cus')]);
  await typeIn(page, 'cus_', '20255');
  await blur(page); await page.waitForTimeout(100);
  const inc = await page.evaluate(() => [document.getElementById('cus_-message').textContent, sendBack_emsFormBuilder_pub.some((r) => r.id_ === 'cus')]);
  check('custom mask incomplete -> incomplete message, row dropped', inc[0] === 'INC:US phone' && inc[1] === false, inc);

  // 9. Second form: its own preset, no cross-talk.
  await typeIn(page, 'smob_', '+966 50 123 4567');
  await blur(page); await page.waitForTimeout(100);
  const two = await page.evaluate(() => ({ v: document.getElementById('smob_').value, r: sendBack_emsFormBuilder_pub.find((x) => x.id_ === 'smob'), mob: sendBack_emsFormBuilder_pub.find((x) => x.id_ === 'mob') }));
  check('second form: sa.mobile formatted and stored for form 202', two.v === '050 123 4567' && two.r && two.r.value === '+966501234567' && Number(two.r.form_id) === 202, two);
  check('first form untouched by the second', two.mob && Number(two.mob.form_id) === 101 && two.mob.value === '+989121234567', two.mob);
  // I4: alternation switches from the landline to the mobile mask at the 11th digit.
  await typeIn(page, 'alt_', '1134567890');
  const alt10 = await val(page, 'alt_');
  await page.keyboard.type('1');
  const alt11 = await val(page, 'alt_');
  await blur(page); await page.waitForTimeout(100);
  check('alternation: 10 digits landline, 11 digits mobile', alt10 === '(11) 3456-7890' && alt11 === '(11) 34567-8901', [alt10, alt11]);
  check('alternation: stored clean value', (await sendBack(page, 'alt')) === '11345678901', await sendBack(page, 'alt'));
  check('no page errors (LTR)', page.errors.length === 0, page.errors);
  await ctx.close();

  // 10. RTL page (fa_IR): input stays LTR and right-aligned.
  ({ ctx, page } = await open(browser, {}, 'rtl'));
  const rtl = await page.evaluate(() => { const e = document.getElementById('mob_'); const cs = getComputedStyle(e); const l = getComputedStyle(document.querySelector('label[for="mob_"]')); return { dir: cs.direction, align: cs.textAlign, label: l.direction }; });
  check('RTL: masked input direction ltr, text-align right, label rtl', rtl.dir === 'ltr' && rtl.align === 'right' && rtl.label === 'rtl', rtl);
  await typeIn(page, 'mob_', '۰۹۱۲۱۲۳۴۵۶۷');
  check('RTL: Persian digits formatted', (await val(page, 'mob_')) === '0912 123 4567');
  check('no page errors (RTL)', page.errors.length === 0, page.errors);
  await ctx.close();

  // 11. Mobile context: numeric keyboards and touch typing.
  ({ ctx, page } = await open(browser, { isMobile: true, hasTouch: true, viewport: { width: 390, height: 844 } }, 'ltr'));
  const im = await page.evaluate(() => ['mob_', 'nid_', 'card_', 'cus_'].map((i) => document.getElementById(i).getAttribute('inputmode')));
  check('mobile: inputmode tel / numeric / numeric / numeric', JSON.stringify(im) === JSON.stringify(['tel', 'numeric', 'numeric', 'numeric']), im);
  await page.tap('#mob_');
  await page.keyboard.type('09121234567');
  check('mobile: typing formats', (await val(page, 'mob_')) === '0912 123 4567');
  check('no page errors (mobile)', page.errors.length === 0, page.errors);
  await ctx.close();

  await browser.close();
  console.log('\n========================================');
  console.log(`RESULTS: ${pass} passed, ${fail} failed`);
  console.log('========================================');
  process.exit(fail > 0 ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
