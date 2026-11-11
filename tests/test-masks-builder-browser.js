/**
 * F01-C: builder "Format / mask" control in Chromium, with the shipped builder scripts
 * (jquery, new-efb.js, admin-efb.js, val-efb.js) and masks-efb.js. The catalog AJAX answer is the real
 * \Emsfb\Masks::builder_catalog() output (php CLI), served for admin-ajax.php.
 *
 * Checks: the select is filled with grouped presets (phones only for a tel field); choosing a preset
 * writes mask_preset, fills an empty placeholder and updates the canvas input (data-mask-preset,
 * inputmode, dir, placeholder); a field with min/max length asks before removing them; an invalid custom
 * mask is refused and not saved; a valid one is saved with its live preview; checksum / store / strict
 * write their keys; "None" removes every mask key. Old (unmasked) fields render the panel unchanged.
 *
 * Run: node tests/test-masks-builder-browser.js   (EFB_CHROMIUM=/path/to/chrome to pick a build)
 */
'use strict';
const { chromium } = require('playwright');
const path = require('path');
const { execFileSync } = require('child_process');

const ROOT = path.join(__dirname, '..');
const R = path.join(ROOT, 'includes', 'admin', 'assets', 'js') + path.sep;
let pass = 0, fail = 0;
const check = (label, ok, detail) => {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + (detail !== undefined ? ' ' + JSON.stringify(detail) : '')); }
};

const catalogJson = execFileSync('php', ['-r', `
  define('ABSPATH', '/'); function __($t, $d = '') { return $t; }
  require '${path.join(ROOT, 'includes', 'class-Emsfb-masks.php').replace(/\\/g, '/')}';
  echo json_encode(array('success' => true, 'data' => \\Emsfb\\Masks::builder_catalog('en_US', 'en_US')), JSON_UNESCAPED_UNICODE);
`]).toString('utf8');

(async () => {
  const launch = process.env.EFB_CHROMIUM ? { executablePath: process.env.EFB_CHROMIUM } : {};
  const browser = await chromium.launch(launch);
  const page = await browser.newPage();
  const errors = [];
  let ajaxCalls = 0;
  page.on('dialog', (d) => { page.lastDialog = d.message(); d.accept(); });
  page.on('pageerror', (e) => errors.push(e.message));
  await page.route('**/*', (r) => {
    const u = r.request().url();
    if (u.includes('admin-ajax.php')) {
      const body = r.request().postData() || '';
      if (body.includes('action=heartbeat_Emsfb')) return r.fulfill({ status: 200, contentType: 'application/json', body: '{"success":true,"data":{"success":true,"newNonce":"n0nce"}}' });
      ajaxCalls++;
      if (!body.includes('action=efb_masks_catalog_Emsfb') || !body.includes('nonce=n0nce')) return r.fulfill({ status: 200, contentType: 'application/json', body: '{"success":true,"data":{"success":false}}' });
      return r.fulfill({ status: 200, contentType: 'application/json', body: catalogJson });
    }
    return r.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><datalist id="color_list_efb"><option value="#000000"></option></datalist><div id="sideMenuFEfb"></div><div id="sideBoxEfb"></div><div id="sideMenuConEfb"></div><div id="overlay_efb" class="d-none"></div><div id="canvas"></div>' });
  });
  await page.goto('http://site.test/wp-admin/admin.php?page=Emsfb_create');
  await page.addScriptTag({ content: "var efb_var={rtl:0,nonce:'n0nce',pro:1,text:new Proxy({}, {get:(t,k)=>typeof k==='string'?'T:'+k:undefined}),images:{},setting:{},addons:{}};var ajaxurl='http://site.test/wp-admin/admin-ajax.php';" });
  for (const f of ['jquery.min-efb.js', 'new-efb.js', 'admin-efb.js', 'steps-progress-builder-efb.js', 'val-efb.js']) await page.addScriptTag({ path: R + f });
  await page.addScriptTag({ path: path.join(ROOT, 'public', 'assets', 'js', 'masks-efb.js') });
  const bootErrors = errors.splice(0);

  // A form with an unmasked text field (old form), a text field with min/max, and a tel field.
  await page.evaluate(() => {
    _efb_nonce_ = 'n0nce';
    valj_efb = [
      { type: 'form', steps: 1, formName: 'T', email: '', trackingCode: true, EfbVersion: 2, button_single_text: 'Send', button_color: 'btn-primary', icon: 'bi-ui-checks-grid',
        button_Next_text: 'Next', button_Previous_text: 'Prev', button_Next_icon: 'bi-chevron-right', button_Previous_icon: 'bi-chevron-left', button_state: 'single',
        label_text_color: 'text-dark', el_text_color: 'text-dark', message_text_color: 'text-muted', icon_color: 'text-white', el_height: 'h-d-efb', email_to: false,
        show_icon: true, show_pro_bar: true, captcha: false, private: false, sendEmail: false, font: true, stateForm: 0, dShowBg: true, btns_align: 'justify-content-center',
        thank_you: 'msg', thank_you_message: { icon: 'bi-hand-thumbs-up', thankYou: 'Thanks', done: 'Done', trackingCode: 'Code', error: 'Error', pleaseFillInRequiredFields: 'Fill' }, email_temp: '' },
      { type: 'step', id_: '1', dataId: '1-id', step: 1 },
    ];
    // Field rows with the builder's own defaults (sampleElpush_efb), as a dropped field gets them.
    for (const [id, el] of [['ftxt', 'text'], ['ftel', 'tel'], ['fmail', 'email'], ['fname', 'firstName']]) sampleElpush_efb(id, el);
    const t = valj_efb.find((r) => r.id_ === 'ftxt');
    t.milen = '2'; t.mlen = '30'; t.placeholder = '';
    const c = document.getElementById('canvas');
    for (const [id, tag] of [['ftxt', 'text'], ['ftel', 'tel'], ['fmail', 'email'], ['fname', 'text']]) {
      c.insertAdjacentHTML('beforeend', `<div data-id="${id}-id" data-tag="${tag}"><input id="${id}_" data-id="${id}-el" type="${tag === 'tel' ? 'tel' : 'text'}"></div>`);
    }
  });
  const open = async (id) => {
    await page.evaluate((i) => { window.efbOpenSettingIdEfb = null; document.getElementById('sideBoxEfb').classList.remove('show'); show_setting_window_efb(i); }, id);
    await page.waitForTimeout(150);
  };
  const change = async (sel, value) => {
    await page.evaluate(([s, v]) => { const el = document.querySelector(s); if (v !== undefined) el.value = v; el.dispatchEvent(new Event('change')); }, [sel, value]);
    await page.waitForTimeout(120); // change_el_edit_Efb runs in a setTimeout
  };
  const row = (id) => page.evaluate((i) => JSON.parse(JSON.stringify(valj_efb.find((r) => r.id_ === i))), id);

  // Panels of fields that get no mask control stay as they were.
  await open('fmail-id');
  check('email field: no mask control', await page.evaluate(() => !document.getElementById('maskPresetEl') && !!document.getElementById('mLenEl')));
  await open('fname-id');
  check('first-name field: no mask control', await page.evaluate(() => !document.getElementById('maskPresetEl')));

  // Text field: grouped catalog, every preset.
  await open('ftxt-id');
  await page.waitForFunction(() => document.querySelectorAll('#maskPresetEl optgroup').length > 0, null, { timeout: 5000 });
  const sel = await page.evaluate(() => {
    const s = document.getElementById('maskPresetEl');
    return { groups: s.querySelectorAll('optgroup').length, options: s.querySelectorAll('optgroup option').length, first: s.querySelector('optgroup').label, loading: !!s.querySelector('[data-efb-loading]'), minLen: !!document.getElementById('miLenEl') };
  });
  check('text field: 21 groups, 99 presets, loading option gone', sel.groups === 21 && sel.options === 99 && !sel.loading, sel);
  check('en_US: United States group first', sel.first === 'United States', sel.first);
  check('catalog requested once with the builder nonce', ajaxCalls === 1, ajaxCalls);

  // Choose ir.mobile: placeholder, canvas, confirm about min/max length.
  page.lastDialog = null;
  await change('#maskPresetEl', 'ir.mobile');
  let r = await row('ftxt');
  check('mask_preset written', r.mask_preset === 'ir.mobile', r);
  check('empty placeholder filled from the preset', r.placeholder === '0912 345 6789', r.placeholder);
  check('min/max length removed after confirm', page.lastDialog === 'T:mskLenReplaced' && !('milen' in r) && !('mlen' in r), { dialog: page.lastDialog, r });
  const canvas = await page.evaluate(() => { const i = document.getElementById('ftxt_'); return { p: i.getAttribute('data-mask-preset'), im: i.getAttribute('inputmode'), dir: i.getAttribute('dir'), ph: i.placeholder }; });
  check('canvas input updated (data-mask-preset, inputmode, dir, placeholder)', canvas.p === 'ir.mobile' && canvas.im === 'tel' && canvas.dir === 'ltr' && canvas.ph === '0912 345 6789', canvas);
  check('length controls hidden for a masked field', await page.evaluate(() => document.getElementById('miLenEl').classList.contains('d-none')));
  check('store choice shown', await page.evaluate(() => !document.getElementById('maskStoreBoxEl').classList.contains('d-none')));

  // Switch to another preset: the placeholder that came from the previous preset follows.
  await change('#maskPresetEl', 'ir.national_id');
  r = await row('ftxt');
  check('placeholder follows the preset when it was the previous preset placeholder', r.placeholder !== '0912 345 6789' && r.placeholder.length > 0, r.placeholder);
  await change('#maskPresetEl', 'ir.postal_code');
  check('strict toggle shown only for presets with a strict rule', await page.evaluate(() => !document.getElementById('maskStrictBoxEl').classList.contains('d-none')));
  await page.evaluate(() => { const b = document.getElementById('maskStrictEl'); b.classList.add('active'); fun_switch_form_efb(b); });
  await page.waitForTimeout(120);
  check('mask_strict written', (await row('ftxt')).mask_strict === 1);
  await change('#maskStoreDisplayEl', undefined);
  await page.evaluate(() => { const e = document.getElementById('maskStoreDisplayEl'); e.checked = true; e.dispatchEvent(new Event('change')); });
  await page.waitForTimeout(120);
  check('mask_store display written', (await row('ftxt')).mask_store === 'display');

  // Custom mask: invalid refused, valid saved with preview.
  await change('#maskPresetEl', 'custom');
  check('custom box shown', await page.evaluate(() => !document.getElementById('maskCustomBoxEl').classList.contains('d-none')));
  await change('#maskCustomEl', '99{');
  r = await row('ftxt');
  check('invalid custom mask not saved', !r.mask_custom, r.mask_custom);
  check('invalid custom mask flagged', await page.evaluate(() => document.getElementById('maskCustomEl').classList.contains('is-invalid') && !document.getElementById('maskCustomErrEl').classList.contains('d-none')));
  for (const m of ['0\\999 999 9999', 'aa99[ ****]', '9{3,4}', '99  99']) {
    await change('#maskCustomEl', m);
    r = await row('ftxt');
    check('custom mask saved as typed: ' + m, r.mask_custom === m, r.mask_custom);
  }
  await change('#maskCustomEl', '(999) 999-9999');
  const prev = await page.evaluate(() => document.getElementById('maskPreviewEl').textContent);
  check('live preview of the custom mask', prev === '(123) 456-7890', prev);
  check('canvas carries data-mask for a custom mask', await page.evaluate(() => document.getElementById('ftxt_').getAttribute('data-mask') === '(999) 999-9999'));
  await change('#maskChecksumEl', 'luhn');
  check('mask_checksum written', (await row('ftxt')).mask_checksum === 'luhn');

  // Reopen: the saved state comes back into the panel.
  await open('ftxt-id');
  await page.waitForFunction(() => document.querySelectorAll('#maskPresetEl optgroup').length > 0, null, { timeout: 5000 });
  const re = await page.evaluate(() => ({ sel: document.getElementById('maskPresetEl').value, custom: document.getElementById('maskCustomEl').value, cs: document.getElementById('maskChecksumEl').value, disp: document.getElementById('maskStoreDisplayEl').checked }));
  check('reopened panel shows the saved values', re.sel === 'custom' && re.custom === '(999) 999-9999' && re.cs === 'luhn' && re.disp, re);
  check('catalog not fetched again', ajaxCalls === 1, ajaxCalls);

  // None removes every mask key and the canvas attributes.
  await change('#maskPresetEl', '');
  r = await row('ftxt');
  check('"None" removes every mask key', !Object.keys(r).some((k) => k.startsWith('mask_')), Object.keys(r));
  check('"None" clears the canvas attributes', await page.evaluate(() => { const i = document.getElementById('ftxt_'); return !i.hasAttribute('data-mask') && !i.hasAttribute('data-mask-preset') && !i.hasAttribute('dir'); }));

  // Tel field: phone presets only.
  await open('ftel-id');
  await page.waitForFunction(() => document.querySelectorAll('#maskPresetEl optgroup').length > 0, null, { timeout: 5000 });
  const tel = await page.evaluate(() => Array.from(document.querySelectorAll('#maskPresetEl optgroup option')).map((o) => o.value));
  const cats = JSON.parse(catalogJson).data.groups.flatMap((g) => g.presets).filter((p) => p.category === 'phone_mobile' || p.category === 'phone_landline').map((p) => p.id).sort();
  check('tel field lists only phone presets', JSON.stringify(tel.slice().sort()) === JSON.stringify(cats) && tel.length === 22, tel.length);

  check('no page errors while using the control', errors.length === 0, errors);
  if (bootErrors.length) console.log('note: errors while loading the builder scripts without WordPress (not from F01): ' + JSON.stringify(bootErrors));
  await browser.close();
  console.log('\n========================================');
  console.log(`RESULTS: ${pass} passed, ${fail} failed`);
  console.log('========================================');
  process.exit(fail > 0 ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
