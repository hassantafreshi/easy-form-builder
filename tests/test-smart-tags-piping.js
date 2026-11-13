#!/usr/bin/env node
/**
 * F08-C/D in Chromium on a fixture page (smart-tags-efb.js as the page loads it; core-efb.js's globals as
 * global let bindings): answer piping, tag defaults / placeholders, hidden values, URL prefill, two forms.
 *
 *  C  a label piece follows every change of the answer it reads ({field:name}) through textContent, an
 *     answer "<img src=x onerror=…>" shows as text (no element, no script), the thank-you pieces take the
 *     server's values first, a field's aria-label follows its label, two forms on one page stay apart,
 *     a default ({get:email}) is filled once and never over what the visitor typed, placeholders.
 *  D  only fields with a prefill_key take a parameter (a parameter without a key, a password or a
 *     disabled field change nothing), select by opt_value / option id / label (case, Persian digits and
 *     invisible marks ignored), radio, checkbox (comma and repeated), a hidden field takes its key over its
 *     template, an invalid number is ignored, prefill_lock makes the field read-only, the old iefb / sefb /
 *     defb / hefb / vefb parameters do nothing, the value goes through the field's own change handler.
 *
 * Run: node tests/test-smart-tags-piping.js
 */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
async function launch() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) { const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell'); if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe }); }
    throw e;
  }
}
const SMART = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'js', 'smart-tags-efb.js'), 'utf8');
let failures = 0, passes = 0;
const check = (label, ok, detail) => { console.log((ok ? '[PASS] ' : '[FAIL] ') + label + (ok ? '' : ' ' + JSON.stringify(detail))); if (ok) passes++; else failures++; };

const FORM = (fid, p) => `<div id="body_efb_${fid}" data-formid="${fid}">
  <label for="${p}nm_" id="${p}nm_labG"><span id="${p}nm_lab">Name</span></label><input type="text" id="${p}nm_" data-vid="${p}nm" data-formid="${fid}" class="emsFormBuilder_v">
  <label for="${p}q_" id="${p}q_labG"><span id="${p}q_lab">Why, <bdi class="efb efb-st" data-st="field:${p}nm|friend">friend</bdi>?</span></label><input type="text" id="${p}q_" data-vid="${p}q" data-formid="${fid}" aria-label="Why, friend?">
  <input type="email" id="${p}em_" data-vid="${p}em" data-formid="${fid}" value="" data-st-value="{get:email}" placeholder="" data-st-ph="Hi {get:who|guest}">
  <input type="password" id="${p}pw_" data-vid="${p}pw" data-formid="${fid}">
  <select id="${p}sel_options" data-vid="${p}sel" data-formid="${fid}"><option id="efbNotingSelected" disabled selected>Nothing</option><option id="${p}o1" value="Pro plan">Pro plan</option><option id="${p}o2" value="Basic">Basic</option></select>
  <input type="radio" name="${p}r" id="${p}r1" value="Red" data-vid="${p}r" data-formid="${fid}"><label id="${p}r1_lab" for="${p}r1">Red</label>
  <input type="radio" name="${p}r" id="${p}r2" value="Blue" data-vid="${p}r" data-formid="${fid}">
  <input type="checkbox" name="${p}c" id="${p}c1" value="PHP" data-vid="${p}c" data-formid="${fid}"><input type="checkbox" name="${p}c" id="${p}c2" value="JS" data-vid="${p}c" data-formid="${fid}"><input type="checkbox" name="${p}c" id="${p}c3" value="Go" data-vid="${p}c" data-formid="${fid}">
  <input type="number" id="${p}n_" data-vid="${p}n" data-formid="${fid}">
  <input type="text" id="${p}dis_" data-vid="${p}dis" data-formid="${fid}">
  <input type="text" id="${p}free_" data-vid="${p}free" data-formid="${fid}">
  <div class="d-none"><input type="hidden" id="${p}h1_" data-type="hiddenField" data-vid="${p}h1" data-formid="${fid}" value=""><input type="hidden" id="${p}h2_" data-type="hiddenField" data-vid="${p}h2" data-formid="${fid}" value=""></div>
  <div id="${p}done"></div>
</div>`;
const ROWS = (p, extra) => [
  Object.assign({ type: 'form', formName: 'F', st_uses: 'get,field,prefill' }, extra || {}),
  { id_: p + 'nm', type: 'text', name: 'Name', prefill_key: p + 'name' },
  { id_: p + 'q', type: 'text', name: 'Why, {field:' + p + 'nm|friend}?' },
  { id_: p + 'em', type: 'email', name: 'E', value: '{get:email}', prefill_key: p + 'mail' },
  { id_: p + 'pw', type: 'password', name: 'P' },
  { id_: p + 'sel', type: 'select', name: 'Plan', prefill_key: p + 'plan' },
  { id_: p + 'o1', type: 'option', parent: p + 'sel', value: 'Pro plan', opt_value: 'pro' },
  { id_: p + 'o2', type: 'option', parent: p + 'sel', value: 'Basic' },
  { id_: p + 'r', type: 'radio', name: 'Color', prefill_key: p + 'color' },
  { id_: p + 'r1', type: 'option', parent: p + 'r', value: 'Red' },
  { id_: p + 'r2', type: 'option', parent: p + 'r', value: 'Blue' },
  { id_: p + 'c', type: 'checkbox', name: 'Langs', prefill_key: p + 'langs' },
  { id_: p + 'c1', type: 'option', parent: p + 'c', value: 'PHP' },
  { id_: p + 'c2', type: 'option', parent: p + 'c', value: 'JS' },
  { id_: p + 'c3', type: 'option', parent: p + 'c', value: 'Go' },
  { id_: p + 'n', type: 'number', name: 'N', prefill_key: p + 'num' },
  { id_: p + 'dis', type: 'text', name: 'D', prefill_key: p + 'dis', disabled: 1 },
  { id_: p + 'free', type: 'text', name: 'F' },
  { id_: p + 'h1', type: 'hiddenField', name: 'UTM', value: '{get:utm_source|none}-{field:' + p + 'nm}' },
  { id_: p + 'h2', type: 'hiddenField', name: 'Src', value: '{get:x}', prefill_key: p + 'src' },
];
const PAGE = `<!doctype html><html><head><meta charset="utf-8"></head><body>${FORM(7, 'a')}${FORM(8, 'b')}</body></html>`;
const SETUP = `
  let sessionPub_emsFormBuilder = 'sess';
  let sendBack_emsFormBuilder_pub = [];
  let valj_efb_new = [{ id: 7, form_structer: ${JSON.stringify(ROWS('a'))} }, { id: 8, form_structer: ${JSON.stringify(ROWS('b'))} }];
  window.ajax_object_efm = { page_id: 3, smart: { tz_offset: 0, site: { name: 'S', url: 'https://s.test/' }, post: {} } };
  window.get_structure_by_form_id_efb = (fid) => (valj_efb_new.find((f) => Number(f.id) === Number(fid)) || {}).form_structer || [];
  window.efb_sendback_for_storage_efb = (r) => JSON.stringify(r);
  window.changes = [];
  /* As core-efb.js: the change handler stores the row and tells EFBSmart. */
  window.handle_change_event_efb_v4 = (el, fid) => {
    changes.push(el.id);
    const vid = el.dataset.vid;
    let at = sendBack_emsFormBuilder_pub.findIndex((r) => r.id_ === vid && Number(r.form_id) === Number(fid) && (el.type !== 'checkbox' || r.id_ob === el.id));
    if (el.type === 'email' && !/^[^@\\s]+@[^@\\s]+\\.[a-z]{2,}$/i.test(el.value)) { el.classList.add('border-danger'); if (at !== -1) sendBack_emsFormBuilder_pub.splice(at, 1); return; }
    const row = { id_: vid, id_ob: el.id, type: el.type === 'select-one' ? 'select' : el.type, value: el.value, form_id: Number(fid) };
    if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) { if (at !== -1) sendBack_emsFormBuilder_pub.splice(at, 1); }
    else if (at === -1 || el.type === 'checkbox') sendBack_emsFormBuilder_pub.push(row); else sendBack_emsFormBuilder_pub[at] = row;
    if (window.EFBSmart) EFBSmart.refresh(Number(fid), vid);
  };`;

(async () => {
  const browser = await launch();
  try {
    const run = async (query) => {
      const page = await browser.newPage();
      const errors = [];
      page.on('pageerror', (e) => errors.push(e.message));
      let dialogs = 0;
      page.on('dialog', async (d) => { dialogs++; await d.dismiss(); });
      await page.route('https://form.test/**', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: PAGE }));
      await page.goto('https://form.test/page/' + query);
      await page.addScriptTag({ content: SETUP });
      await page.addScriptTag({ content: SMART });
      await page.evaluate(() => EFBSmart.init());
      return { page, errors, dialogs: () => dialogs };
    };
    const st = (page) => page.evaluate(() => sendBack_emsFormBuilder_pub.map((r) => [r.form_id, r.id_, r.value]));

    /* C: piping */
    let { page, errors, dialogs } = await run('?utm_source=google&email=ana%40example.test&who=Ana');
    const lab = () => page.evaluate(() => document.getElementById('aq_lab').textContent);
    check('C: the piece shows its fallback before an answer', await lab() === 'Why, friend?', await lab());
    await page.fill('#anm_', 'Sara'); await page.evaluate(() => handle_change_event_efb_v4(document.getElementById('anm_'), 7));
    check('C: the piece follows the answer', await lab() === 'Why, Sara?', await lab());
    await page.fill('#anm_', 'Sam'); await page.evaluate(() => handle_change_event_efb_v4(document.getElementById('anm_'), 7));
    check('C: … at every change', await lab() === 'Why, Sam?', await lab());
    check('C: the aria-label of the field follows its label', await page.evaluate(() => document.getElementById('aq_').getAttribute('aria-label')) === 'Why, Sam?', null);
    await page.fill('#anm_', '<img src=x onerror="window.pwned=1">'); await page.evaluate(() => handle_change_event_efb_v4(document.getElementById('anm_'), 7));
    await page.waitForTimeout(300);
    const xss = await page.evaluate(() => ({ text: document.getElementById('aq_lab').textContent, imgs: document.querySelectorAll('#aq_lab img').length, pwned: !!window.pwned }));
    check('C: an answer with markup is shown as text (no element, no script)', xss.text === 'Why, <img src=x onerror="window.pwned=1">?' && xss.imgs === 0 && !xss.pwned, xss);
    check('C: the other form on the page is untouched', await page.evaluate(() => document.getElementById('bq_lab').textContent) === 'Why, friend?', null);
    const hid = await page.evaluate(() => [document.getElementById('ah1_').value, sendBack_emsFormBuilder_pub.find((r) => r.id_ === 'ah1').value]);
    check('C: a hidden value with {field:…} follows the answer (row and input)', hid[0] === 'google-<img src=x onerror="window.pwned=1">' && hid[1] === hid[0], hid);
    check('C: a tag default is filled from the address and goes through the change handler', await page.evaluate(() => [document.getElementById('aem_').value, changes.includes('aem_')]).then((v) => v[0] === 'ana@example.test' && v[1]), null);
    check('C: a tag placeholder', await page.evaluate(() => document.getElementById('aem_').getAttribute('placeholder')) === 'Hi Ana', null);
    await page.fill('#bem_', 'typed@example.test');
    await page.evaluate(() => EFBSmart.bind(8, { noPrefill: true }));
    check('C: a default never replaces what the visitor typed', await page.evaluate(() => document.getElementById('bem_').value) === 'typed@example.test', null);
    // thank-you pieces
    await page.evaluate(() => { document.getElementById('adone').innerHTML = EFBSmart.messageMarkup('Thanks {field:anm|friend}, code {entry:track}, plan {field:asel.value|-}'); });
    await page.evaluate(() => EFBSmart.fillMessage(document.getElementById('adone'), 7, { 'field:anm|friend': 'Server Name <b>' }, { track: 'ABC123' }));
    const done = await page.evaluate(() => ({ t: document.getElementById('adone').textContent, b: document.querySelectorAll('#adone b').length }));
    check('C: thank-you pieces: the server\'s value first, the page\'s for the rest, all as text', done.t === 'Thanks Server Name <b>, code ABC123, plan -' && done.b === 0, done);
    check('C: messageMarkup keeps the owner\'s text and escapes the fallback', await page.evaluate(() => EFBSmart.messageMarkup('<b>Hi</b> {get:x|"><svg onload=1>}')) === '<b>Hi</b> <bdi class="efb efb-st" data-st-where="message" data-st="get:x|&quot;&gt;&lt;svg onload=1&gt;">&quot;&gt;&lt;svg onload=1&gt;</bdi>', null);
    const dg = await page.evaluate(() => {
      document.getElementById('anm_lab').insertAdjacentHTML('beforeend', ' <bdi class="efb efb-st" data-st="date:Y">x</bdi>');
      ajax_object_efm.language = 'fa_IR'; EFBSmart.refresh(7); const fa = document.querySelector('#anm_lab [data-st="date:Y"]').textContent;
      ajax_object_efm.language = 'ar'; EFBSmart.refresh(7); const ar = document.querySelector('#anm_lab [data-st="date:Y"]').textContent;
      ajax_object_efm.language = 'en_US'; EFBSmart.refresh(7); const en = document.querySelector('#anm_lab [data-st="date:Y"]').textContent;
      return [fa, ar, en, String(new Date().getUTCFullYear())];
    });
    check('C: a date in a label reads in Persian / Arabic-Indic digits on fa / ar pages, Latin elsewhere', /^[\u06F0-\u06F9]{4}$/.test(dg[0]) && /^[\u0660-\u0669]{4}$/.test(dg[1]) && dg[2] === dg[3], dg);
    check('C: no script error and no dialog', errors.length === 0 && dialogs() === 0, errors);
    await page.close();

    /* D: prefill */
    ({ page, errors } = await run('?aname=Ana%E2%80%8C+M&amail=bad-mail&aplan=PRO&acolor=%DB%B1&acolor=blue&alangs=php,%20JS&alangs=go&anum=12x&adis=no&afree=yes&apw=secret&asrc=ad%3Cb%3E1&bplan=Basic&iefb=anm&vefb=hack&defb=0&utm_source=g'));
    const v = await page.evaluate(() => ({
      nm: document.getElementById('anm_').value, em: document.getElementById('aem_').value, emBad: document.getElementById('aem_').classList.contains('border-danger'),
      sel: document.getElementById('asel_options').value, r: [document.getElementById('ar1').checked, document.getElementById('ar2').checked],
      c: [document.getElementById('ac1').checked, document.getElementById('ac2').checked, document.getElementById('ac3').checked],
      n: document.getElementById('an_').value, dis: document.getElementById('adis_').value, free: document.getElementById('afree_').value, pw: document.getElementById('apw_').value,
      h2: sendBack_emsFormBuilder_pub.find((r) => r.id_ === 'ah2') ? sendBack_emsFormBuilder_pub.find((r) => r.id_ === 'ah2').value : null,
      bsel: document.getElementById('bsel_options').value, aEmRow: sendBack_emsFormBuilder_pub.some((r) => r.id_ === 'aem'),
    }));
    check('D: a text field with a key is filled (as typed text, through its change handler)', v.nm === 'Ana‌ M', v);
    check('D: an invalid e-mail is marked invalid and not stored', v.em === 'bad-mail' && v.emBad && !v.aEmRow, v);
    check('D: a select by its opt_value (case ignored)', v.sel === 'Pro plan', v);
    check('D: a radio by label (case ignored); the last value wins', JSON.stringify(v.r) === '[false,true]', v);
    check('D: checkboxes by comma and by repeating the key', JSON.stringify(v.c) === '[true,true,true]', v);
    check('D: an invalid number is ignored', v.n === '', v);
    check('D: a disabled field is not filled', v.dis === '', v);
    check('D: a field without a key, and a password, are not filled', v.free === '' && v.pw === '', v);
    check('D: a hidden field takes its key over its template (cleaned)', v.h2 === 'ad<b>1', v);
    check('D: each form reads only its own keys', v.bsel === 'Basic', v);
    check('D: the old iefb / vefb / defb parameters change nothing', await page.evaluate(() => document.getElementById('adis_').disabled === false && document.getElementById('anm_').value !== 'hack'), null);
    check('D: EFBSmart.isPrefilled reports the filled fields', await page.evaluate(() => [EFBSmart.isPrefilled(7, 'anm'), EFBSmart.isPrefilled(7, 'afree')]).then((x) => x[0] === true && x[1] === false), null);
    await page.close();
    ({ page, errors } = await run('?aname=Locked'));
    await page.evaluate(() => { valj_efb_new[0].form_structer[1].prefill_lock = '1'; document.getElementById('anm_').value = ''; EFBSmart.applyPrefill(7); });
    check('D: prefill_lock makes the filled field read-only', await page.evaluate(() => document.getElementById('anm_').readOnly && document.getElementById('anm_').value === 'Locked'), null);
    check('D: no script error', errors.length === 0, errors);
    await page.close();
  } finally { await browser.close(); }
  console.log(`\nRESULTS: ${passes} passed, ${failures} failed`);
  process.exit(failures ? 1 : 0);
})().catch((e) => { console.log('[FAIL] ' + e.message); console.log('\nRESULTS: 0 passed, 1 failed'); process.exit(1); });
