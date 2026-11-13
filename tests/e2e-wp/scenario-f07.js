/**
 * F07 scenarios of tests/e2e-wp/run.js (docs/feature-gaps/F07-file-upload.fa.md), on the real site.
 * The plan of every F07 part, e-mail capture with attachments and a small quota come from the mu-plugin
 * efb-e2e-f07.php (copied by `ctl.php f07-mu`), through the plugin's own filters.
 *
 *  f07a  Builder: the file tile, the F07 controls (maximum, minimum, total, camera for image presets
 *        only, resizing, where to store), the private folder check (PHP's own server ignores .htaccess:
 *        the warning with the nginx rule), the canvas input (multiple, capture), Save keeps the keys;
 *        the form switch "Attach uploaded files" (Pro); AutoPilot: setUploadConfig, insertItems refused
 *        with upload_invalid, capabilities.uploads and setting:upload_*; forms/{id}/schema (visitor_only).
 *  f07b  A guest (en_US): three files chosen at once and one dropped later join one list, each with its
 *        progress; a fourth is refused at once; Remove (focus moves on, announced); Send with fewer than
 *        the minimum is held at the field; Send stores one row per file; a request whose file was
 *        swapped for another form's file is refused at the field (efb_file_not_owned); the response
 *        viewer shows the files as cards (names escaped) and the CSV joins them with " | ".
 *  f07c  fa_IR and ar at 375 px: right to left, the counter and sizes in Persian / Arabic-Indic digits,
 *        a Latin file name keeps its order, the remove button at the logical end, the label aligned
 *        like the other labels, no horizontal scroll; screenshots.
 *  f07d  Camera and resizing: capture="environment" on an image field; a 4000 × 3000 photo with EXIF GPS
 *        is stored at most 1920 px wide without EXIF (checked on the stored file).
 *  f07e  Private storage and deletion: the viewer opens the file through the admin download, a visitor
 *        cannot; the e-mail has a signed link; deleting the entry from the panel removes the file.
 *  f07pay A paid form (test gateway) with a field of two files: payments/create checks the files (a
 *        swapped file is refused before anything is charged), the paid entry keeps both files.
 */
'use strict';
const zlib = require('zlib');

module.exports = function (S) {
  const { scenario, R, ctl, CFG, sleep, SHOT, openNewForm, addTile, closePanel } = S;
  const byId = (id) => `[id="${id}"]`;
  const form = (name, fields, row0) => ctl('f03-form', name, JSON.stringify(fields), row0 || '', '1');
  const pageOf = (fid) => ctl('page', String(fid)).url;
  const setup = () => {
    if (!['1', '3'].includes(String(ctl('state').emsfb_pro))) ctl('plan', 3);
    ctl('f11-mu'); ctl('f07-mu'); ctl('f07-reset');
    ctl('set-option', 'efb_e2e_f07_plan', '1');
    ctl('set-option', 'efb_e2e_capture_mail', '1');
    ctl('set-option', 'efb_e2e_mail_bodies', 'del');
  };
  /* A small valid PNG (solid colour), made here so no fixture file is needed. */
  const png = (w, h, rgb) => {
    const crcT = []; for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; crcT[n] = c >>> 0; }
    const crc = (b) => { let c = 0xffffffff; for (const x of b) c = crcT[(c ^ x) & 255] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; };
    const chunk = (t, d) => { const l = Buffer.alloc(4); l.writeUInt32BE(d.length); const td = Buffer.concat([Buffer.from(t), d]); const c = Buffer.alloc(4); c.writeUInt32BE(crc(td)); return Buffer.concat([l, td, c]); };
    const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(h, 4); ihdr[8] = 8; ihdr[9] = 2;
    const raw = Buffer.alloc((w * 3 + 1) * h); for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) raw.set(rgb, y * (w * 3 + 1) + 1 + x * 3);
    return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', zlib.deflateSync(raw)), chunk('IEND', Buffer.alloc(0))]);
  };
  const pdf = (n) => Buffer.from('%PDF-1.4\n% ' + 'x'.repeat(n) + '\n%%EOF\n');
  async function open(context, url, label, opts) {
    const g = await (await context(opts || {}, label)).newPage();
    await g.goto(url, { waitUntil: 'load' });
    await g.waitForSelector('[id^="body_efb_"]', { state: 'attached', timeout: 20000 });
    await g.waitForFunction(() => window.EFBUpload && Object.keys(EFBUpload._fields).length > 0, null, { timeout: 20000 });
    await sleep(800);
    return g;
  }
  async function send(page) {
    const resp = page.waitForResponse((x) => /forms\/message\/add|forms%2Fmessage%2Fadd/.test(x.url()), { timeout: 30000 }).catch(() => null);
    await page.click('#btn_send_efb');
    const r = await resp;
    let j = null;
    try { j = r ? await r.json() : null; } catch (e) { j = null; }
    await sleep(900);
    return j && (j.data || j);
  }
  const uploadsDone = (page, n) => page.waitForFunction((k) => (typeof sendBack_emsFormBuilder_pub !== 'undefined' ? sendBack_emsFormBuilder_pub : []).filter((r) => r && r.value === '@file@' && r.url).length >= k && !EFBUpload.isBusy(Number(document.querySelector('[id^="body_efb_"]').id.replace('body_efb_', ''))), n, { timeout: 30000 });
  const lastEntry = (fid) => { const e = ctl('entries', String(fid)) || []; e.sort((a, b) => Number(b.msg_id) - Number(a.msg_id)); return e[0] || null; };
  const fileRows = (entry, id) => ((entry && entry.content) || []).filter((r) => r && r.id_ === id && r.value === '@file@');

  /* ------------------------------------------------------------------ f07a */
  scenario('f07a', 'F07 builder: the upload controls, the private folder check, Save, AutoPilot, the schema', async ({ adminPage }) => {
    setup();
    const admin = await adminPage();
    await openNewForm(admin);
    const t = await addTile(admin, 'file');
    R.check('f07a: the file tile adds a file field without F07 keys (one file, as before)', t && t.type === 'file' && t.max_files === undefined && t.file_store === undefined, t);
    await admin.click(`#dropZoneEFB [data-id="${t.dataId}"] label, #dropZoneEFB [data-id="${t.dataId}"]`, { position: { x: 20, y: 10 } });
    await admin.waitForSelector('#maxFilesEl', { timeout: 10000 });
    const pane = await admin.evaluate(() => ({ max: !!document.getElementById('maxFilesEl'), cap: document.getElementById('fileCaptureWrapEl').classList.contains('d-none'), size: document.getElementById('fileSizeMaxEl').value, store: [...document.querySelectorAll('#fileStoreEl option')].map((o) => o.value) }));
    R.check('f07a: the panel: maximum / minimum / total / store; the camera hidden for "all formats"; the size shows 20 MB', pane.max && pane.cap === true && pane.size === '20' && JSON.stringify(pane.store) === '["","media","private"]', pane);
    await admin.fill('#maxFilesEl', '۵'); await admin.press('#maxFilesEl', 'Tab');
    await admin.fill('#minFilesEl', '9'); await admin.press('#minFilesEl', 'Tab');
    await admin.fill('#maxTotalMbEl', '50'); await admin.press('#maxTotalMbEl', 'Tab');
    await admin.selectOption('#fileTypeEl', 'image');
    await sleep(700);
    const capShown = await admin.evaluate(() => !document.getElementById('fileCaptureWrapEl').classList.contains('d-none'));
    R.check('f07a: the image preset shows the camera option', capShown, capShown);
    await admin.selectOption('#fileCaptureEl', 'environment');
    await admin.fill('#imgMaxPxEl', '1920'); await admin.press('#imgMaxPxEl', 'Tab');
    const probe = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /efb_private_dir_probe_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.selectOption('#fileStoreEl', 'private');
    await probe;
    await admin.waitForSelector('#fileStoreProbeEl [role=alert], #fileStoreProbeEl small', { timeout: 15000 });
    const warn = await admin.evaluate(() => { const a = document.querySelector('#fileStoreProbeEl [role=alert]'); return a ? { t: a.textContent, code: (a.querySelector('code') || {}).textContent } : (document.querySelector('#fileStoreProbeEl') || {}).textContent; });
    R.check('f07a: the private folder check warns on a server that ignores .htaccess, with the nginx rule', warn && /serves the private folder publicly/.test(warn.t) && /location \^~ .*efb-private\/ \{ deny all; return 403; \}/.test(warn.code), warn);
    const r = await admin.evaluate((i) => JSON.parse(JSON.stringify(valj_efb.find((x) => x.id_ === i))), t.id_);
    R.check('f07a: the panel writes the keys with the save rules (Persian digits, minimum ≤ maximum)', r.max_files === '5' && r.min_files === '5' && r.max_total_mb === '50' && r.capture === 'environment' && r.img_max_px === '1920' && r.file_store === 'private' && r.file === 'image', r);
    const canvas = await admin.evaluate((i) => { const e = document.getElementById(i + '_'); return { multiple: e.multiple, capture: e.getAttribute('capture'), max: e.dataset.maxFiles }; }, t.id_);
    R.check('f07a: the canvas input: multiple, capture, data-max-files', canvas.multiple && canvas.capture === 'environment' && canvas.max === '5', canvas);
    await admin.selectOption('#fileTypeEl', 'document');
    await sleep(700);
    const cleared = await admin.evaluate((i) => { const x = valj_efb.find((y) => y.id_ === i); return [x.capture, x.img_max_px, document.getElementById('fileCaptureWrapEl').classList.contains('d-none')]; }, t.id_);
    R.check('f07a: a document preset clears the camera and resizing', JSON.stringify(cleared) === '["","",true]', cleared);
    await admin.selectOption('#fileTypeEl', 'image');
    await sleep(700); // change_el_edit_Efb applies the preset on a timer
    await admin.selectOption('#fileCaptureEl', 'environment');
    await admin.fill('#imgMaxPxEl', '1920'); await admin.press('#imgMaxPxEl', 'Tab');
    await closePanel(admin);
    // AutoPilot.
    const ap = await admin.evaluate((i) => {
      const out = {};
      try { out.low = efbBuilderApi.setUploadConfig(i, { max_files: '4' }); } catch (e) { out.low = { code: e.code, reason: e.reason }; }
      try { out.set = efbBuilderApi.setUploadConfig(i, { max_files: '4', min_files: '4', max_total_mb: '۲۰' }); } catch (e) { out.set = { code: e.code }; }
      try { efbBuilderApi.insertItems([{ id_: 'apfile1', type: 'file', name: 'API files', max_files: 'many' }]); out.bad = 'inserted'; } catch (e) { out.bad = { code: e.code, path: e.path }; }
      try { efbBuilderApi.setUploadConfig(i, { pattern: 'x' }); out.key = 'set'; } catch (e) { out.key = e.code; }
      try { efbBuilderApi.setUploadConfig(i, { min_files: '9', max_files: '2' }); out.cfg = 'set'; } catch (e) { out.cfg = { code: e.code, reason: e.reason }; }
      const cap = efb_var.capabilities || {};
      out.up = cap.uploads ? { keys: cap.uploads.keys, builder: cap.uploads.builder, assistant: cap.uploads.assistant, prefix: cap.uploads.error_code_prefix } : null;
      out.feat = cap.features ? ['setting:upload_multi', 'setting:upload_capture', 'setting:upload_resize', 'setting:upload_media', 'setting:upload_private', 'setting:email_attach'].map((k) => cap.features[k] ? cap.features[k].package : null) : null;
      return out;
    }, t.id_);
    R.check('f07a: AutoPilot setUploadConfig refuses a maximum under the stored minimum (upload_config)', ap.low && ap.low.code === 'upload_config' && ap.low.reason === 'min_after_max', ap.low);
    R.check('f07a: AutoPilot setUploadConfig (Persian digits accepted)', ap.set && JSON.stringify(ap.set.values) === JSON.stringify({ max_files: '4', min_files: '4', max_total_mb: '20' }), ap.set);
    R.check('f07a: AutoPilot insertItems with a bad max_files: upload_invalid and its path', ap.bad && ap.bad.code === 'upload_invalid' && /max_files$/.test(ap.bad.path), ap.bad);
    R.check('f07a: AutoPilot: a key that is not an upload key is invalid_setting; minimum over maximum is upload_config', ap.key === 'invalid_setting' && ap.cfg && ap.cfg.code === 'upload_config' && ap.cfg.reason === 'min_after_max', ap);
    R.check('f07a: capabilities.uploads and the plan of each part', ap.up && ap.up.builder === 'efbBuilderApi.setUploadConfig' && ap.up.assistant === 'visitor_only' && ap.up.prefix === 'efb_file_' && JSON.stringify(ap.feat) === JSON.stringify(['free_plus', 'free', 'free_plus', 'free_plus', 'free', 'pro']), ap);
    // Form settings: "Attach uploaded files to the admin email" is Pro: on this Free Plus site it is locked with
    // a gem that opens the upgrade dialog; the key is then set as an integration would (row 0).
    await admin.evaluate(() => show_setting_window_efb('formSet'));
    await admin.waitForSelector('#emailAttachEl', { timeout: 10000 });
    const lock = await admin.evaluate(() => ({ dis: document.getElementById('emailAttachEl').disabled, gem: (document.querySelector('.efb-up-attach .efb-up-gem') || {}).getAttribute ? document.querySelector('.efb-up-attach .efb-up-gem').getAttribute('data-cap') : null }));
    R.check('f07a: "Attach uploaded files to the admin email" is locked on Free Plus with a gem (Pro)', lock.dis === true && lock.gem === 'setting:email_attach', lock);
    await admin.evaluate(() => { valj_efb[0].email_attach = EFBUpload.rules.sanitizeKey('email_attach', 1); });
    const attach = await admin.evaluate(() => valj_efb[0].email_attach);
    R.check('f07a: email_attach set with the save rule', attach === '1', attach);
    await closePanel(admin);
    const saved = admin.waitForResponse((x) => /admin-ajax\.php/.test(x.url()) && /add_form_Emsfb|update_form_Emsfb/.test(x.request().postData() || ''), { timeout: 20000 }).catch(() => null);
    await admin.evaluate(() => saveFormEfb(1));
    const sres = await saved;
    const sj = sres ? await sres.json() : {};
    await sleep(800);
    const fid = Number(String((sj.data && (sj.data.value || sj.data.id)) || '').replace(/\D+/g, '')) || await admin.evaluate(() => Number(form_ID_emsFormBuilder));
    const rows = (ctl('form', String(fid)) || {}).form_structer || [];
    const st = rows.find((x) => x && x.id_ === t.id_) || {};
    R.check('f07a: Save keeps the keys through the real sanitizer', st.max_files === '4' && st.min_files === '4' && st.max_total_mb === '20' && st.capture === 'environment' && st.img_max_px === '1920' && st.file_store === 'private' && rows[0].email_attach === '1', [st, rows[0].email_attach]);
    // The schema an assistant reads.
    const url = pageOf(fid);
    const g = await admin.context().newPage();
    await g.goto(url, { waitUntil: 'load' });
    const sc = await g.evaluate(async (f) => (await (await fetch('/wp-json/Emsfb/v1/forms/' + f + '/schema')).json()), fid);
    const se = (sc.fields || []).find((x) => x.id === t.id_) || {};
    R.check('f07a: forms/{id}/schema: visitor_only, accept, max_files, sizes, no storage mode', se.assistant === 'visitor_only' && se.fill && se.fill.api === 'visitor_only' && se.upload && se.upload.max_files === 4 && /\.png/.test(se.upload.accept) && se.upload.max_total_mb === 20 && JSON.stringify(sc).indexOf('private') === -1, se);
    const pin = await g.evaluate((i) => { const e = document.getElementById(i + '_'); return e && { m: e.multiple, c: e.getAttribute('capture'), max: e.dataset.maxFiles, imgMax: e.dataset.imgMax, list: !!document.getElementById(i + '_-list') }; }, t.id_);
    R.check('f07a: the public input: multiple, capture, data-max-files, data-img-max and the file list', pin && pin.m && pin.c === 'environment' && pin.max === '4' && pin.imgMax === '1920' && pin.list, pin);
  });

  /* ------------------------------------------------------------------ f07b */
  scenario('f07b', 'F07 guest (en_US): several files, limits in the page, Remove, the minimum, Send, a swapped file refused, viewer, CSV', async ({ context, adminPage }) => {
    setup();
    const F = form('F07 e2e documents', [
      { key: 'docs', type: 'file', name: 'Documents', file: 'allformat', value: 'allformat', max_files: '4', min_files: '2', max_total_mb: '3', max_fsize: '1', required: 1 },
      { key: 'name', type: 'text', name: 'Name' },
    ]);
    const O = form('F07 e2e other', [{ key: 'other', type: 'file', name: 'Other', max_files: '2' }]);
    const url = pageOf(F.form_id);
    const id = F.ids.docs;
    const g = await open(context, url, 'f07b');
    await g.setInputFiles(byId(id + '_'), [
      { name: 'contract.pdf', mimeType: 'application/pdf', buffer: pdf(2000) },
      { name: '<img src=x onerror=alert(1)>.png', mimeType: 'image/png', buffer: png(8, 8, [200, 30, 30]) },
      { name: 'too-big.pdf', mimeType: 'application/pdf', buffer: pdf(1100 * 1024) },
    ]);
    await uploadsDone(g, 2);
    const s1 = await g.evaluate((i) => ({ items: [...document.querySelectorAll(`[id="${i}_-list"] li`)].map((li) => li.className.replace(/.*efb-file-/, '')), sum: document.getElementById(i + '_-sum').textContent, bars: document.querySelectorAll(`[id="${i}_-list"] [role=progressbar]`).length, xss: !!window.alerted }), id);
    R.check('f07b: three files chosen at once: two uploaded, one too large refused in the page', JSON.stringify(s1.items) === '["done","done","rejected"]' && /2 of 4 files/.test(s1.sum), s1);
    // A file dropped onto the field later joins the list (never replaces it).
    await g.setInputFiles(byId(id + '_'), [{ name: 'photo.png', mimeType: 'image/png', buffer: png(16, 16, [20, 120, 220]) }]);
    await uploadsDone(g, 3);
    const s2 = await g.evaluate((i) => [...document.querySelectorAll(`[id="${i}_-list"] li`)].length, id);
    R.check('f07b: a second choice adds to the list', s2 === 4, s2);
    await g.setInputFiles(byId(id + '_'), [{ name: 'four.pdf', mimeType: 'application/pdf', buffer: pdf(10) }, { name: 'five.pdf', mimeType: 'application/pdf', buffer: pdf(10) }]);
    await uploadsDone(g, 4);
    const s3 = await g.evaluate((i) => ({ last: [...document.querySelectorAll(`[id="${i}_-list"] li`)].pop().textContent, disabled: document.getElementById(i + '_').disabled, sum: document.getElementById(i + '_-sum').textContent }), id);
    await g.screenshot({ path: SHOT('f07b-list-en-1280'), fullPage: true });
    R.check('f07b: the fifth file is refused at once; the field is full', /Maximum number of files reached/.test(s3.last) && s3.disabled && /4 of 4 files/.test(s3.sum), s3);
    // Remove two (keyboard), then Send with one: held at the field.
    for (const name of ['contract.pdf', 'four.pdf', 'photo.png']) {
      await g.focus(`[id="${id}_-list"] .efb-file-rm[aria-label="Remove ${name}"]`);
      await g.keyboard.press('Enter');
      await sleep(250);
    }
    const s4 = await g.evaluate((i) => ({ rows: sendBack_emsFormBuilder_pub.filter((r) => r && r.id_ === i).length, focus: document.activeElement && document.activeElement.className, live: (document.querySelector(`[id="${i}_-sum"] + [aria-live]`) || {}).textContent }), id);
    R.check('f07b: Remove (keyboard) takes the files out; the focus moves on; announced', s4.rows === 1 && /efb-file-rm|emsFormBuilder_v/.test(s4.focus) && /photo\.png removed/.test(s4.live), s4);
    await g.fill(byId(F.ids.name + '_'), 'Guest');
    await g.click('#btn_send_efb');
    await sleep(800);
    const held = await g.evaluate((i) => (document.getElementById(i + '_-message') || {}).textContent, id);
    R.check('f07b: Send with one file when two are needed is held at the field', /at least 2 files to Documents/.test(held), held);
    await g.setInputFiles(byId(id + '_'), [{ name: 'scan 2.pdf', mimeType: 'application/pdf', buffer: pdf(500) }]);
    await uploadsDone(g, 2);
    const a = await send(g);
    R.check('f07b: Send stores the answer', a && a.success === true, a);
    const rows = fileRows(lastEntry(F.form_id), id);
    R.check('f07b: one stored row per file, each with its stored name, size and the visitor\'s name (cleaned)', rows.length === 2 && rows[0].id_ob !== rows[1].id_ob && rows.every((r) => /^efb-PLG-\d{6}-[A-Za-z0-9]{12}\./.test(r.id_ob) && Number(r.fsz) > 0 && r.fnm && r.fnm.indexOf('<') === -1), rows);
    // A request whose file was swapped for another form's file.
    const go = await open(context, pageOf(O.form_id), 'f07b-other');
    await go.setInputFiles(byId(O.ids.other + '_'), [{ name: 'theirs.pdf', mimeType: 'application/pdf', buffer: pdf(30) }]);
    await uploadsDone(go, 1);
    const theirs = await go.evaluate(() => sendBack_emsFormBuilder_pub.find((r) => r && r.value === '@file@'));
    const g2 = await open(context, url, 'f07b-2');
    await g2.setInputFiles(byId(id + '_'), [{ name: 'a.pdf', mimeType: 'application/pdf', buffer: pdf(40) }, { name: 'b.pdf', mimeType: 'application/pdf', buffer: pdf(41) }]);
    await uploadsDone(g2, 2);
    await g2.fill(byId(F.ids.name + '_'), 'Swap');
    await g2.route('**/*', async (route) => {
      const req = route.request();
      if (/forms\/message\/add|forms%2Fmessage%2Fadd/.test(req.url()) && req.method() === 'POST') {
        const b = JSON.parse(req.postData());
        let swapped = false;
        b.value = JSON.stringify(JSON.parse(b.value).map((r) => (!swapped && r.value === '@file@' && (swapped = true) ? Object.assign(r, { url: theirs.url, id_ob: theirs.id_ob, ut: theirs.ut }) : r)));
        return route.continue({ postData: JSON.stringify(b) });
      }
      return route.continue();
    });
    const a2 = await send(g2);
    R.check('f07b: another form\'s file put into the request is refused at the field (efb_file_not_owned)', a2 && a2.success === false && a2.code === 'efb_file_not_owned' && a2.field_id === id && /could not be verified/.test(a2.m), a2);
    await g2.unroute('**/*');
    // Response viewer and CSV (admin panel).
    const admin = await adminPage();
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_emsFormBuilder_show_messages === 'function', null, { timeout: 20000 });
    const view = await admin.evaluate((rs) => {
      const hostile = Object.assign({}, rs[0], { fnm: '<img src=x onerror=window.pwned=1>.pdf' });
      const out = fun_emsFormBuilder_show_messages([hostile, rs[1]], 'guest', '', 'TRK', '2026-01-01');
      const box = document.createElement('div'); box.innerHTML = typeof out === 'string' ? out : ''; document.body.appendChild(box);
      return { cards: box.querySelectorAll('.efb-file-card').length, csv: [...box.querySelectorAll('[data-csv]')].map((x) => x.getAttribute('data-csv')), names: [...box.querySelectorAll('.efb-file-card-name')].map((x) => x.textContent), imgOnerror: box.querySelectorAll('img[onerror]').length, rows: box.querySelectorAll('.efb-msg-field-row').length };
    }, rows);
    R.check('f07b: the viewer shows the two files of the field as two cards in one row', view.cards === 2 && view.csv.length === 2, view);
    R.check('f07b: a hostile file name is shown as text', view.imgOnerror === 0 && view.names.includes('<img src=x onerror=window.pwned=1>.pdf'), view);
    const csv = await admin.evaluate(([rs, fid]) => {
      form_type_emsFormBuilder = 'form';
      const v = [{ msg_id: '1', date: '2026-11-13', content: JSON.stringify(rs.concat([{ id_: 'n', name: 'Name', type: 'text', value: 'Guest', amount: 3 }])) }];
      fun_export_rows_for_Subscribe_emsFormBuilder(v);
      return JSON.stringify(window.efb_export_rows_efb || '');
    }, [rows, F.form_id]);
    R.check('f07b: the form CSV puts both files in one cell, joined with " | "', csv.indexOf(rows[0].url + ' | ' + rows[1].url) !== -1, csv.slice(0, 400));
  });

  /* ------------------------------------------------------------------ f07c */
  scenario('f07c', 'F07 fa_IR and ar at 375 px: right to left, local digits, mixed-script names, no horizontal scroll', async ({ context }) => {
    setup();
    try {
      for (const loc of ['fa_IR', 'ar']) {
        ctl('locale', loc);
        const F = form('F07 e2e ' + loc, [
          { key: 'name', type: 'text', name: loc === 'ar' ? 'الاسم' : 'نام' },
          { key: 'docs', type: 'dadfile', name: loc === 'ar' ? 'المستندات' : 'مدارک', file: 'allformat', value: 'allformat', max_files: '3', max_total_mb: '5', max_fsize: '2', icon: 'bi-cloud-arrow-up', icon_color: 'text-darkb', button_color: 'btn-primary' },
        ]);
        const id = F.ids.docs;
        const g = await open(context, pageOf(F.form_id), 'f07c-' + loc, { viewport: { width: 375, height: 800 }, locale: loc === 'ar' ? 'ar' : 'fa-IR' });
        await g.setInputFiles(byId(id + '_'), [{ name: 'Report-2026 final.pdf', mimeType: 'application/pdf', buffer: pdf(1500 * 1024) }, { name: 'قرارداد.pdf', mimeType: 'application/pdf', buffer: pdf(300) }]);
        await uploadsDone(g, 2);
        const m = await g.evaluate(([i, n]) => {
          const li = document.querySelectorAll(`[id="${i}_-list"] li`);
          const name = li[0].querySelector('.efb-file-name');
          const rm = li[0].querySelector('.efb-file-rm').getBoundingClientRect();
          const main = li[0].querySelector('.efb-file-main').getBoundingClientRect();
          const sum = document.getElementById(i + '_-sum');
          const lab = (x) => { const l = document.getElementById(x + '_labG') || document.querySelector(`label[for="${x}_"]`); return l ? getComputedStyle(l).textAlign : null; };
          const box = document.getElementById(i + '_box');
          return { dir: getComputedStyle(document.querySelector('[id^="body_efb_"]')).direction, sum: sum.textContent, sumBdi: sum.querySelectorAll('bdi').length, bidi: getComputedStyle(name).unicodeBidi, ndir: name.getAttribute('dir'), meta: li[0].querySelector('.efb-file-meta').textContent,
            rmAtEnd: rm.right <= main.left + 1, rmSize: [Math.round(rm.width), Math.round(rm.height)], labels: [lab(n), lab(i)], box: box.getAttribute('role'), scroll: document.documentElement.scrollWidth <= window.innerWidth + 1 };
        }, [id, F.ids.name]);
        const digits = loc === 'ar' ? /[٠-٩]/ : /[۰-۹]/;
        R.check(`f07c ${loc}: right to left; the counter in local digits, the values in <bdi>`, m.dir === 'rtl' && digits.test(m.sum) && m.sumBdi >= 4 && / of .* files/.test(m.sum), m);
        R.check(`f07c ${loc}: the size in local digits; a Latin file name keeps its order (dir=auto, plaintext)`, digits.test(m.meta) && m.bidi === 'plaintext' && m.ndir === 'auto', m);
        R.check(`f07c ${loc}: the remove button at the logical end (left), 44 px; the drop zone is a button`, m.rmAtEnd && m.rmSize[0] >= 44 && m.rmSize[1] >= 44 && m.box === 'button', m);
        R.check(`f07c ${loc}: the field label aligns like the other labels; no horizontal scroll at 375 px`, m.labels[0] === m.labels[1] && m.scroll, m);
        await g.screenshot({ path: SHOT(`f07c-${loc}-375`), fullPage: true });
      }
    } finally { ctl('locale', ''); }
  });

  /* ------------------------------------------------------------------ f07d */
  scenario('f07d', 'F07 camera and resizing: capture on an image field; a 4000 × 3000 photo with GPS stored ≤ 1920 px without EXIF', async ({ context }) => {
    setup();
    const F = form('F07 e2e photos', [{ key: 'pic', type: 'file', name: 'Photos', file: 'image', value: 'image', max_files: '2', capture: 'environment', img_max_px: '1920', img_quality: '0.8', max_fsize: '20' }]);
    const id = F.ids.pic;
    const g = await open(context, pageOf(F.form_id), 'f07d');
    const attrs = await g.evaluate((i) => { const e = document.getElementById(i + '_'); return { capture: e.getAttribute('capture'), multiple: e.multiple, accept: e.getAttribute('accept') }; }, id);
    R.check('f07d: the image field asks for the back camera (capture=environment), several files, image types only', attrs.capture === 'environment' && attrs.multiple && /\.jpg/.test(attrs.accept) && !/\.pdf|svg/.test(attrs.accept), attrs);
    const sent = await g.evaluate(async (i) => {
      const c = document.createElement('canvas'); c.width = 4000; c.height = 3000; const x = c.getContext('2d');
      for (let k = 0; k < 50; k++) { x.fillStyle = `hsl(${k * 7},60%,50%)`; x.fillRect(k * 80, 0, 80, 3000); }
      const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.95));
      const a = new Uint8Array(await blob.arrayBuffer());
      const b = []; const u16 = (v) => b.push((v >> 8) & 255, v & 255); const u32 = (v) => b.push((v >>> 24) & 255, (v >> 16) & 255, (v >> 8) & 255, v & 255);
      b.push(0x4d, 0x4d); u16(42); u32(8); u16(1); u16(0x8825); u16(4); u32(1); u32(26); u32(0); u16(1); u16(0x0001); u16(2); u32(2); b.push(0x4e, 0, 0, 0); u32(0);
      const head = new TextEncoder().encode('Exif\0\0'); const len = 2 + head.length + b.length;
      const seg = new Uint8Array(4 + head.length + b.length); seg.set([0xff, 0xe1, (len >> 8) & 255, len & 255]); seg.set(head, 4); seg.set(b, 4 + head.length);
      const out = new Uint8Array(a.length + seg.length); out.set(a.slice(0, 2)); out.set(seg, 2); out.set(a.slice(2), 2 + seg.length);
      EFBUpload.queue(i, Number(document.querySelector('[id^="body_efb_"]').id.replace('body_efb_', '')), [new File([out], 'IMG_2026.JPG', { type: 'image/jpeg' })]);
      return out.length;
    }, id);
    await uploadsDone(g, 1);
    const up = await g.evaluate((i) => sendBack_emsFormBuilder_pub.find((r) => r && r.id_ === i), id);
    const f = ctl('f07-file', up.url);
    R.check('f07d: the stored photo is at most 1920 px (4000 × 3000 → 1920 × 1440)', f.dims && f.dims[0] === 1920 && f.dims[1] === 1440, f);
    R.check('f07d: the stored photo has no EXIF / GPS and is smaller than the original', f.exif ? (f.exif.gps === false) : true, f);
    R.check('f07d: …and smaller than what the visitor chose', f.size > 0 && f.size < sent, [f.size, sent]);
  });

  /* ------------------------------------------------------------------ f07e */
  scenario('f07e', 'F07 private storage: the viewer through the admin download, a visitor cannot, the e-mail link, deletion with the entry', async ({ context, adminPage }) => {
    setup();
    const F = form('F07 e2e private', [{ key: 'cv', type: 'file', name: 'CV', file: 'document', value: 'document', max_files: '2', file_store: 'private', max_fsize: '2' }, { key: 'em', type: 'email', name: 'Email' }]);
    const id = F.ids.cv;
    const g = await open(context, pageOf(F.form_id), 'f07e');
    await g.setInputFiles(byId(id + '_'), [{ name: 'cv.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n% private cv\n%%EOF\n') }]);
    await uploadsDone(g, 1);
    await g.fill(byId(F.ids.em + '_'), 'visitor@example.test');
    const a = await send(g);
    R.check('f07e: the entry is stored', a && a.success === true, a);
    const row = fileRows(lastEntry(F.form_id), id)[0];
    R.check('f07e: the stored address is in the private folder', row && /\/efb-private\/\d{4}\/\d{2}\/efb-PLG-/.test(row.url), row);
    // A visitor's own browser session (no login), outside the page so the refusal is not a console error.
    const anonRes = await g.context().request.get(CFG.base + '/wp-admin/admin-ajax.php?action=efb_file_get_Emsfb&f=' + encodeURIComponent(row.url.split('/efb-private/')[1]) + '&nonce=x');
    const anon = [anonRes.status(), (await anonRes.text()).indexOf('private cv') !== -1];
    R.check('f07e: a visitor cannot use the admin download', [400, 403].includes(anon[0]) && anon[1] === false, anon);
    const admin = await adminPage();
    await admin.goto(CFG.base + '/wp-admin/admin.php?page=Emsfb', { waitUntil: 'load' });
    await admin.waitForFunction(() => typeof fun_emsFormBuilder_show_messages === 'function' && window.efb_var && efb_var.uploads, null, { timeout: 20000 });
    const href = await admin.evaluate((r) => efbFileHrefEfb(r.url), row);
    const dl = await admin.evaluate(async (h) => { const r = await fetch(h); return [r.status, r.headers.get('x-content-type-options'), (await r.text()).indexOf('private cv') !== -1]; }, href);
    R.check('f07e: the viewer\'s link opens the file for the owner (admin download, nosniff)', /action=efb_file_get_Emsfb/.test(href) && dl[0] === 200 && dl[1] === 'nosniff' && dl[2] === true, [href, dl]);
    const log = ctl('option', 'efb_e2e_mail_bodies') || [];
    const mails = Array.isArray(log) ? log : Object.values(log || {});
    const owner = mails.filter((m) => JSON.stringify(m.to).indexOf('owner@example.test') !== -1).pop();
    R.check('f07e: the owner\'s e-mail links to a signed address (never the private one)', !!owner && /files\/signed\?f=/.test(owner.message.replace(/&#038;|&amp;/g, '&')) && owner.message.indexOf('/efb-private/') === -1, owner ? owner.message.slice(0, 300) : mails.length);
    const path = ctl('f07-file', row.url).path;
    // Delete the entry from the panel (Settings > Privacy keeps "also delete uploaded files" on by default).
    const msg = lastEntry(F.form_id).msg_id;
    const del = await admin.evaluate(async (mid) => new Promise((res) => jQuery.post(ajaxurl, { action: 'remove_message_id_Emsfb', nonce: _efb_core_nonce_, id: mid }, (r) => res(r && r.data)).fail(() => res(null))), msg);
    await sleep(500);
    R.check('f07e: deleting the entry from the panel removes its private file', del && del.success !== false && path !== '' && ctl('file-exists', path) === false, [del, path]);
  });

  /* ------------------------------------------------------------------ f07pay */
  scenario('f07pay', 'F07 a paid form with a field of two files (test gateway): payments/create checks the files', async ({ context }) => {
    setup();
    ctl('pay-mock', '1');
    ctl('pay-keys', 'test');
    const P = ctl('pay-form', 'file', 'F07 e2e paid files');
    const fill = async (label) => {
      const g = await (await context({}, label)).newPage();
      await g.goto(pageOf(P.form_id), { waitUntil: 'networkidle' });
      await g.waitForSelector('.efb-pf [data-pf-pay]', { timeout: 20000 });
      await g.waitForFunction(() => window.EFBUpload && Object.keys(EFBUpload._fields).length > 0, null, { timeout: 20000 });
      await g.fill(`[data-id="${P.email}-el"]`, 'payer@example.test');
      await g.check(`[id="${P.o1}"]`).catch(async () => { await g.click(`label[for="${P.o1}"]`); });
      await g.setInputFiles(byId(P.file + '_'), [{ name: 'passport.pdf', mimeType: 'application/pdf', buffer: pdf(100) }, { name: 'visa.png', mimeType: 'image/png', buffer: png(4, 4, [1, 2, 3]) }]);
      await uploadsDone(g, 2);
      return g;
    };
    const pay = async (g) => {
      const r = g.waitForResponse((x) => /payments\/create/.test(x.url()), { timeout: 30000 }).catch(() => null);
      await g.click('.efb-pf [data-pf-pay]');
      const res = await r;
      const j = res ? await res.json().catch(() => null) : null;
      await sleep(1200);
      return j && (j.data || j);
    };
    const before = (ctl('payments', String(P.form_id)) || []).length;
    // A create request whose second file was swapped for another stored file of the site: refused before anything is created.
    const site = ctl('f13-seed', '0', '0', '', '1');
    const g1 = await fill('f07pay-1');
    await g1.route('**/wp-json/Emsfb/v1/payments/create', async (route) => {
      const b = JSON.parse(route.request().postData());
      b.value = JSON.stringify(JSON.parse(b.value).map((r) => (r.value === '@file@' && r.fnm === 'visa.png' && site && site.url ? Object.assign(r, { url: site.url, id_ob: String(site.url).split('/').pop() }) : r)));
      return route.continue({ postData: JSON.stringify(b) });
    });
    const d1 = await pay(g1);
    R.check('f07pay: a swapped file is refused by payments/create at the field, nothing created', d1 && d1.success === false && /^efb_file_/.test(d1.code || '') && d1.field_id === P.file && (ctl('payments', String(P.form_id)) || []).length === before, d1);
    const g2 = await fill('f07pay-2');
    await pay(g2);
    const pays = ctl('payments', String(P.form_id)) || [];
    R.check('f07pay: the request of the page creates the payment', pays.length === before + 1, pays.length);
    const fr = fileRows(lastEntry(P.form_id), P.file);
    R.check('f07pay: the pending entry keeps both files', fr.length === 2 && fr.every((x) => /efb-PLG-/.test(x.id_ob)), fr);
  });
};
