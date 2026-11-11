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
const fs = require('fs');
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
  await page.addScriptTag({ content: "var efb_var={rtl:0,language:'en_US',nonce:'n0nce',pro:1,text:new Proxy({}, {get:(t,k)=>typeof k==='string'?'T:'+k:undefined}),images:{},setting:{},addons:{}};var ajaxurl='http://site.test/wp-admin/admin-ajax.php';" });
  for (const f of ['jquery.min-efb.js', 'new-efb.js', 'admin-efb.js', 'steps-progress-builder-efb.js', 'val-efb.js', 'core-efb.js']) await page.addScriptTag({ path: R + f });
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
  const catData = JSON.parse(catalogJson).data;
  const nPresets = catData.groups.reduce((n, g) => n + g.presets.length, 0);
  check(`text field: ${catData.groups.length} groups, ${nPresets} presets, loading option gone`, sel.groups === catData.groups.length && sel.options === nPresets && nPresets >= 140 && !sel.loading, sel);
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
  await change('#maskCaseEl', 'upper');
  check('I2: mask_case written and on the canvas', (await row('ftxt')).mask_case === 'upper' && await page.evaluate(() => document.getElementById('ftxt_').getAttribute('data-mask-case') === 'upper'));
  await change('#maskChecksumEl', 'luhn');
  check('mask_checksum written', (await row('ftxt')).mask_checksum === 'luhn');

  // Reopen: the saved state comes back into the panel.
  await open('ftxt-id');
  await page.waitForFunction(() => document.querySelectorAll('#maskPresetEl optgroup').length > 0, null, { timeout: 5000 });
  const re = await page.evaluate(() => ({ sel: document.getElementById('maskPresetEl').value, custom: document.getElementById('maskCustomEl').value, cs: document.getElementById('maskChecksumEl').value, disp: document.getElementById('maskStoreDisplayEl').checked }));
  check('reopened panel shows the saved values', re.sel === 'custom' && re.custom === '(999) 999-9999' && re.cs === 'luhn' && re.disp, re);
  check('catalog not fetched again', ajaxCalls === 1, ajaxCalls);

  // I1: number / amount option.
  await change('#maskPresetEl', 'numeric');
  check('I1: numeric box shown', await page.evaluate(() => !document.getElementById('maskNumBoxEl').classList.contains('d-none')));
  await change('#numDigitsEl', '2');
  await change('#numMaxEl', '5000');
  await change('#numMinEl', '1e5');
  await page.evaluate(() => { const e = document.getElementById('numNegativeEl'); e.checked = true; e.dispatchEvent(new Event('change')); });
  await page.waitForTimeout(120);
  r = await row('ftxt');
  check('I1: numeric keys written, invalid min refused', r.mask_preset === 'numeric' && r.num_digits === '2' && r.num_max === '5000' && !('num_min' in r) && r.num_negative === '1', r);
  check('I1: canvas marked numeric', await page.evaluate(() => document.getElementById('ftxt_').getAttribute('data-mask-preset') === 'numeric'));

  // None removes every mask key and the canvas attributes.
  await change('#maskPresetEl', '');
  r = await row('ftxt');
  check('"None" removes every mask and number key', !Object.keys(r).some((k) => k.startsWith('mask_') || k.startsWith('num_')), Object.keys(r));
  check('"None" clears the canvas attributes', await page.evaluate(() => { const i = document.getElementById('ftxt_'); return !i.hasAttribute('data-mask') && !i.hasAttribute('data-mask-preset') && !i.hasAttribute('dir'); }));

  // H: mobile keyboard select.
  await open('fmail-id');
  check('H: email field has the keyboard select, set to auto', await page.evaluate(() => document.getElementById('inputmodeEl') && document.getElementById('inputmodeEl').value === 'auto'));
  await change('#inputmodeEl', 'email');
  check('H: inputmode written and shown on the canvas', (await row('fmail')).inputmode === 'email' && await page.evaluate(() => document.getElementById('fmail_').getAttribute('inputmode') === 'email'));
  await change('#inputmodeEl', 'auto');
  check('H: auto removes the key and the attribute', !('inputmode' in (await row('fmail'))) && await page.evaluate(() => !document.getElementById('fmail_').hasAttribute('inputmode')));
  await page.evaluate(() => { const s = document.getElementById('inputmodeEl'); const o = document.createElement('option'); o.value = 'bogus'; s.appendChild(o); });
  await change('#inputmodeEl', 'bogus');
  check('H: a forged value is treated as auto', !('inputmode' in (await row('fmail'))));
  await open('fname-id');
  check('H: first-name field has the keyboard select', await page.evaluate(() => !!document.getElementById('inputmodeEl')));

  // Tel field: phone presets only.
  await open('ftel-id');
  await page.waitForFunction(() => document.querySelectorAll('#maskPresetEl optgroup').length > 0, null, { timeout: 5000 });
  const tel = await page.evaluate(() => Array.from(document.querySelectorAll('#maskPresetEl optgroup option')).map((o) => o.value));
  const cats = JSON.parse(catalogJson).data.groups.flatMap((g) => g.presets).filter((p) => p.category === 'phone_mobile' || p.category === 'phone_landline').map((p) => p.id).sort();
  check('tel field lists only phone presets', JSON.stringify(tel.slice().sort()) === JSON.stringify(cats) && tel.length === cats.length && tel.length >= 22, tel.length);

  // JS renderer twin (canvas + in-admin preview), with the catalog loaded.
  const tw = await page.evaluate(() => ({
    tel: efb_mask_attrs_efb({ mask_preset: 'ir.mobile' }, 'tel'),
    custom: efb_mask_attrs_efb({ mask_preset: 'custom', mask_custom: '(999) 999-9999', mask_store: 'display' }, 'text'),
    none: efb_mask_attrs_efb({ placeholder: 'x' }, 'text'),
    im: efb_mask_attrs_efb({ inputmode: 'decimal' }, 'number'),
    name: efb_mask_attrs_efb({ mask_preset: 'ir.mobile' }, 'firstName'),
    ac: efb_mask_attrs_efb({ mask_preset: 'intl.card', autocomplete: 'off' }, 'text'),
  }));
  check('JS twin: preset attrs match the PHP renderer', tw.tel.masked && tw.tel.attrs === 'data-mask-preset="ir.mobile" data-mask-store="canonical" inputmode="tel" dir="ltr"' && tw.tel.autocomplete === 'tel-national', tw.tel);
  check('JS twin: custom attrs match the PHP renderer', tw.custom.attrs === 'data-mask="(999) 999-9999" data-mask-store="display" inputmode="numeric" dir="ltr" maxlength="14"', tw.custom);
  check('JS twin: unmasked field adds nothing', !tw.none.masked && tw.none.attrs === '', tw.none);
  check('JS twin: inputmode alone (H)', !tw.im.masked && tw.im.attrs === 'inputmode="decimal"', tw.im);
  check('JS twin: name parts ignore masks', !tw.name.masked, tw.name);
  check('JS twin: explicit autocomplete wins', tw.ac.autocomplete === '', tw.ac);
  // In-admin preview: masks bound from the builder catalog's runtime entries, same checks as the public page.
  const pv = await page.evaluate(async () => {
    const c = document.getElementById('canvas');
    c.insertAdjacentHTML('beforeend', '<div id="pvw"><input id="ftel_" data-vid="ftel" data-formid="-1" type="tel" class="efb emsFormBuilder_v form-control" data-mask-preset="ir.national_id" data-mask-store="canonical" dir="ltr"><small id="ftel_-message"></small></div>');
    const old = document.querySelectorAll('#ftel_');
    if (old.length > 1) old[0].id = 'ftel_old';
    const el = document.querySelectorAll('#ftel_')[0];
    sendBack_emsFormBuilder_pub = [];
    await efb_masks_preview_bind_efb();
    el.focus(); el.value = ''; el.value = '1234567892'; el.dispatchEvent(new Event('input'));
    const shown = el.value;
    let err = null;
    try { handle_change_event_efb(el); } catch (e) { err = e.message; }
    const msg = document.getElementById('ftel_-message').textContent;
    el.value = '0012345679'; el.dispatchEvent(new Event('input'));
    try { handle_change_event_efb(el); } catch (e) { err = err || e.message; }
    const row = sendBack_emsFormBuilder_pub.find((r) => r.id_ === 'ftel');
    return { shown, err, msg, stored: row ? row.value : null };
  });
  check('preview: mask bound and formats while typing', pv.shown === '123-456789-2', pv);
  check('preview: change handler runs without errors', pv.err === null, pv.err);
  check('preview: checksum message (phrase mskChecksum_)', pv.msg === 'T:mskChecksum_', pv.msg);
  check('preview: valid value stored canonical', pv.stored === '0012345679', pv.stored);
  check('no page errors while using the control', errors.length === 0, errors);
  if (bootErrors.length) console.log('note: errors while loading the builder scripts without WordPress (not from F01): ' + JSON.stringify(bootErrors));
  await browser.close();
  console.log('\n========================================');
  console.log(`RESULTS: ${pass} passed, ${fail} failed`);
  console.log('========================================');
  process.exit(fail > 0 ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
