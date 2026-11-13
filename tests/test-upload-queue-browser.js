/**
 * F07-C / F07-F in Chromium, on the shipped public/assets/js/upload-efb.js (no WordPress):
 *
 *  C  a field with data-max-files="3": choosing files adds to the queue (never replaces it), at most two
 *     upload at once, each has its own progress bar (role=progressbar, aria-valuenow), a refused file is
 *     refused before sending (type, size, total, count) and the others go on; the answer has one row per
 *     stored file (id_ob = stored name, ut, fnm, fsz) and keeps a field of one file replacing its row
 *     (core-efb.js fun_sendBack); Remove takes the row out and moves the focus; Try again re-sends a
 *     failed file; check() says "wait" while files are on their way and asks for the minimum; the drop
 *     zone is a keyboard button and takes dropped files; the counter and sizes use Persian digits on
 *     fa_IR, with the values isolated (<bdi>) in a sentence that keeps its order; 44 px remove target.
 *  F  resizeImage(): a 4000 × 3000 JPEG with EXIF (GPS, orientation 6) becomes ≤ 1920 px on its longest
 *     side, turned as the camera held it, without EXIF; a GIF is untouched; a result larger than the
 *     original keeps the original; without createImageBitmap the original is sent.
 *
 * Run: node tests/test-upload-queue-browser.js   (EFB_CHROMIUM=/path/to/chrome to choose a build)
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
/* Playwright's own browser, else an installed headless shell (as tests/e2e-wp/lib.js). */
async function launch() {
  if (process.env.EFB_CHROMIUM) return chromium.launch({ executablePath: process.env.EFB_CHROMIUM });
  try { return await chromium.launch(); } catch (e) {
    const base = process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers';
    const dirs = fs.existsSync(base) ? fs.readdirSync(base).filter((d) => /^chromium_headless_shell-\d+$/.test(d)).sort().reverse() : [];
    for (const d of dirs) { const exe = path.join(base, d, 'chrome-headless-shell-linux64', 'chrome-headless-shell'); if (fs.existsSync(exe)) return chromium.launch({ executablePath: exe }); }
    throw e;
  }
}
const UP = path.join(__dirname, '..', 'public', 'assets', 'js', 'upload-efb.js');
const CSS = path.join(__dirname, '..', 'public', 'assets', 'css', 'upload-efb.css');
let failures = 0, passes = 0;
const check = (label, ok, detail) => { console.log((ok ? '[PASS] ' : '[FAIL] ') + label + (ok ? '' : ' ' + JSON.stringify(detail))); if (ok) passes++; else failures++; };

const PAGE = (lang, dir) => `<!doctype html><html lang="${lang}" dir="${dir}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head><body>
<div id="body_efb_7">
  <label for="docs_">Documents</label>
  <input type="file" id="docs_" class="efb emsFormBuilder_v" data-formid="7" accept=".pdf,.png,.jpg,image/png" data-max-files="3" data-min-files="2" data-max-mb="1" data-max-total="2">
  <ul id="docs_-list" class="efb efb-file-list list-unstyled"></ul><div class="efb efb-file-sum" id="docs_-sum" aria-live="polite"></div>
  <small id="docs_-message"></small>
  <div class="efb dadFile-efb" id="pics_box"><h6 id="pics_txt">Drag and drop files here</h6>
    <input type="file" hidden id="pics_" data-max-files="2" accept=".png" data-max-mb="1"></div>
  <ul id="pics_-list" class="efb efb-file-list"></ul><div id="pics_-sum"></div>
  <input type="file" id="one_" data-max-files="1">
</div></body></html>`;

const SETUP = (lan) => `
  window.efb_var = { wp_lan: '${lan}', page_id: 1, nonce: 'n', sid: 's', text: {} };
  window.ajax_object_efm = { text: {} };
  // As core-efb.js / new-efb.js declare them: global let bindings, not window properties.
  let sessionPub_emsFormBuilder = 'sess';
  let files_emsFormBuilder = [];
  let sendBack_emsFormBuilder_pub = [];
  window.valj_efb = [{ type: 'form' }, { id_: 'docs', type: 'file', name: 'Documents', amount: 2, required: 1 }, { id_: 'pics', type: 'dadfile', name: 'Pictures', amount: 3 }, { id_: 'one', type: 'file', name: 'One', amount: 4 }];
  window.get_structure_by_form_id_efb = () => window.valj_efb;
  window.efb_sendback_for_storage_efb = (r) => JSON.stringify(r);
  window.hide_msg_efb = () => {};
  // core-efb.js's fun_sendBack_emsFormBuilder file branch (F07): several files keep a row each.
  window.fun_sendBack_emsFormBuilder = (ob) => {
    const sb = sendBack_emsFormBuilder_pub;
    const indx = sb.findIndex((x) => x.id_ === ob.id_);
    if (indx === -1) { sb.push(ob); return; }
    if (ob.id_ob && window.EFBUpload.isMulti(ob.id_, ob.form_id)) {
      const same = sb.findIndex((x) => x.id_ === ob.id_ && x.id_ob === ob.id_ob);
      same === -1 ? sb.push(ob) : (sb[same] = ob);
    } else sb[indx] = ob;
  };
  // Fake upload route: every call waits until the test lets it finish.
  window.calls = []; window.active = 0; window.peak = 0; window.nextFail = 0; window.seq = 0;
  window.fetch_uploadFile = (file, id, pl, nonce, page, fid, sid, opts) => new Promise((resolve, reject) => {
    window.active++; window.peak = Math.max(window.peak, window.active);
    const call = { name: file.name, size: file.size, id, fid, sid, opts, resolve, reject };
    window.calls.push(call);
    call.finish = (ok) => { window.active--; opts.onProgress && opts.onProgress(100);
      if (ok) { const n = 'efb-PLG-261113-' + ('S' + (++window.seq)).padEnd(12, 'x') + '.' + file.name.split('.').pop();
        resolve({ success: true, data: { success: true, file: { url: 'https://site.test/wp-content/uploads/2026/11/' + n }, name: n, ut: '1999999999.' + 'a'.repeat(32), size: file.size } }); }
      else resolve({ success: true, data: { success: false, error: 'This file type is not accepted here.', efb_user_message: true } }); };
  });
`;

(async () => {
  const b = await launch();
  const ctx = await b.newContext({ viewport: { width: 375, height: 800 } });
  const p = await ctx.newPage();
  const errs = []; p.on('pageerror', (e) => errs.push(e.message));
  let lang = 'en', dir = 'ltr';
  await p.route('**/*', (r) => r.fulfill({ status: 200, contentType: 'text/html', body: PAGE(lang, dir) }));

  /* ---------------------------------------------------------------- C (en) */
  await p.goto('http://site.test/form');
  await p.addScriptTag({ content: SETUP('en_US') });
  await p.addStyleTag({ path: CSS });
  await p.addScriptTag({ path: UP });
  const bound = await p.evaluate(() => [EFBUpload.bind('docs', 7), EFBUpload.bind('pics', 7), EFBUpload.bind('one', 7)]);
  check('bind: several files yes, one file no (old path)', JSON.stringify(bound) === '[true,true,false]', bound);
  await p.setInputFiles('#docs_', [
    { name: 'a.pdf', mimeType: 'application/pdf', buffer: Buffer.alloc(300 * 1024, 1) },
    { name: 'b.pdf', mimeType: 'application/pdf', buffer: Buffer.alloc(300 * 1024, 2) },
    { name: 'evil.svg', mimeType: 'image/svg+xml', buffer: Buffer.from('<svg onload=alert(1)>') },
  ]);
  await p.waitForTimeout(80); // the session is asked for first (EFBRuntime / fun_sid_efb), then the upload starts
  let s = await p.evaluate(() => ({ calls: calls.map((c) => c.name), items: [...document.querySelectorAll('#docs_-list li')].map((li) => li.className), inputValue: document.getElementById('docs_').value, inflight: files_emsFormBuilder.filter((f) => f.state === 1).length }));
  check('two files upload, the SVG is refused before sending', JSON.stringify(s.calls) === '["a.pdf","b.pdf"]' && s.items.filter((c) => /rejected/.test(c)).length === 1, s);
  check('the input is emptied so the same file can be chosen again', s.inputValue === '', s);
  check('the uploads in flight hold Send (files_emsFormBuilder state 1)', s.inflight === 2, s);
  const busy = await p.evaluate(() => [EFBUpload.isBusy(7), EFBUpload.check('docs', 7)]);
  check('check(): wait while files are on their way', busy[0] === true && busy[1] && busy[1].code === 'uploading', busy);
  const bar = await p.evaluate(() => { calls[0].opts.onProgress(40); const b = document.querySelector('#docs_-list li .efb-file-bar'); return b && [b.getAttribute('role'), b.getAttribute('aria-valuenow'), b.getAttribute('aria-label')]; });
  check('each file has its own progress bar (role, value, name)', bar && bar[0] === 'progressbar' && bar[1] === '40' && bar[2] === 'a.pdf', bar);
  await p.setInputFiles('#docs_', [{ name: 'c.png', mimeType: 'image/png', buffer: Buffer.alloc(200 * 1024, 3) }]);
  await p.waitForTimeout(80);
  s = await p.evaluate(() => ({ calls: calls.length, peak: peak }));
  check('a third file waits: at most two at once', s.calls === 2 && s.peak === 2, s);
  await p.evaluate(() => calls[0].finish(true));
  await p.waitForTimeout(80);
  s = await p.evaluate(() => ({ calls: calls.map((c) => c.name), peak: peak }));
  check('when one finishes the next starts (still two at most)', s.calls.length === 3 && s.calls[2] === 'c.png' && s.peak === 2, s);
  await p.evaluate(() => { calls[1].finish(true); calls[2].finish(false); });
  await p.waitForTimeout(50);
  s = await p.evaluate(() => ({ rows: sendBack_emsFormBuilder_pub.map((r) => ({ id_: r.id_, id_ob: r.id_ob, ut: !!r.ut, fnm: r.fnm, fsz: r.fsz, value: r.value, form_id: r.form_id })), states: [...document.querySelectorAll('#docs_-list li')].map((li) => li.className.replace(/.*efb-file-/, '')), inflight: files_emsFormBuilder.length }));
  check('one answer row per stored file, with its stored name, token, name and size', s.rows.length === 2 && s.rows[0].id_ob !== s.rows[1].id_ob && s.rows.every((r) => r.id_ === 'docs' && r.ut && r.value === '@file@' && r.form_id === 7 && r.fsz === String(300 * 1024)) && s.rows[0].fnm === 'a.pdf', s);
  check('states: uploaded, uploaded, refused, failed', JSON.stringify(s.states) === '["done","done","rejected","error"]', s.states);
  check('nothing in flight any more', s.inflight === 0, s);
  await p.evaluate(() => { document.querySelector('#docs_-list .efb-file-retry').click(); });
  await p.waitForTimeout(80);
  const retry = await p.evaluate(() => [calls.length, calls[3] && calls[3].name]);
  check('Try again re-sends the failed file', retry[0] === 4 && retry[1] === 'c.png', retry);
  await p.evaluate(() => calls[3].finish(true));
  await p.waitForTimeout(50);
  const full = await p.evaluate(() => [document.getElementById('docs_').disabled, document.getElementById('docs_-sum').textContent, EFBUpload.count('docs', 7)]);
  check('three files: the field is full (input disabled, "Maximum number of files reached")', full[0] === true && /3 of 3 files/.test(full[1]) && /Maximum number of files reached/.test(full[1]) && full[2] === 3, full);
  check('the total is shown against the field total', /0\.8 MB/.test(full[1]) && /2 MB/.test(full[1]), full[1]);
  const over = await p.evaluate(() => { EFBUpload.queue('docs', 7, [new File([new Uint8Array(10)], 'd.pdf', { type: 'application/pdf' })]); const li = [...document.querySelectorAll('#docs_-list li')].pop(); return [li.className, li.textContent, calls.length]; });
  check('a fourth file is refused at once (count)', /rejected/.test(over[0]) && /Maximum number of files reached/.test(over[1]) && over[2] === 4, over);
  // Remove: the row leaves the answer, the focus moves on.
  const rm = await p.evaluate(() => {
    const before = sendBack_emsFormBuilder_pub.length;
    const btns = document.querySelectorAll('#docs_-list .efb-file-rm');
    const target = [...btns].find((b) => /a\.pdf/.test(b.getAttribute('aria-label')));
    const rect = target.getBoundingClientRect();
    target.focus(); target.click();
    return { before, after: sendBack_emsFormBuilder_pub.length, focus: document.activeElement && document.activeElement.className, label: target.getAttribute('aria-label'), w: rect.width, h: rect.height, ids: sendBack_emsFormBuilder_pub.map((r) => r.fnm) };
  });
  check('Remove: the file leaves the answer', rm.before === 3 && rm.after === 2 && rm.ids.indexOf('a.pdf') === -1, rm);
  check('Remove: labelled with the file name, 44 × 44 px, focus moves to the next button', rm.label === 'Remove a.pdf' && rm.w >= 44 && rm.h >= 44 && /efb-file-rm/.test(rm.focus), rm);
  const live = await p.evaluate(() => new Promise((res) => setTimeout(() => res(document.querySelector('#docs_-sum + [aria-live]').textContent), 80)));
  check('the removal is announced (aria-live)', live === 'a.pdf removed', live);
  // Minimum: a required field with min 2 holding only… two: fine; a field with one file and min 2 refused.
  const minChk = await p.evaluate(() => { const st = EFBUpload._fields['7|docs']; st.items = st.items.filter((i) => i.fnm !== 'x' && i.state === 'done').slice(0, 1); return EFBUpload.check('docs', 7); });
  check('check(): fewer than the minimum asks for it', minChk && minChk.code === 'min_files' && /at least 2 files to Documents/.test(minChk.message), minChk);
  // Drop zone: keyboard and drop.
  const dz = await p.evaluate(async () => {
    const box = document.getElementById('pics_box');
    let clicked = 0; document.getElementById('pics_').click = () => { clicked++; };
    box.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    box.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true }));
    const dt = new DataTransfer(); dt.items.add(new File([new Uint8Array(5)], 'p1.png', { type: 'image/png' })); dt.items.add(new File([new Uint8Array(5)], 'p2.gif', { type: 'image/gif' }));
    box.dispatchEvent(new DragEvent('dragover', { dataTransfer: dt, bubbles: true, cancelable: true }));
    const during = document.getElementById('pics_txt').textContent;
    box.dispatchEvent(new DragEvent('drop', { dataTransfer: dt, bubbles: true, cancelable: true }));
    await new Promise((r) => setTimeout(r, 80));
    return { role: box.getAttribute('role'), tab: box.getAttribute('tabindex'), clicked, during, after: document.getElementById('pics_txt').textContent, uploads: calls.filter((c) => c.id === 'pics').map((c) => c.name), refused: document.querySelectorAll('#pics_-list .efb-file-rejected').length };
  });
  check('drop zone: role=button, in the tab order, Enter and Space open the picker', dz.role === 'button' && dz.tab === '0' && dz.clicked === 2, dz);
  check('drop zone: "Release to upload" while dragging, then the text again', dz.during === 'Release to upload' && dz.after === 'Drag and drop files here', dz);
  check('drop zone: dropped files join the queue; a type the field does not take is refused', JSON.stringify(dz.uploads) === '["p1.png"]' && dz.refused === 1, dz);
  check('a field of one file keeps replacing its row (old path)', await p.evaluate(() => { fun_sendBack_emsFormBuilder({ id_: 'one', value: '@file@', url: 'u1', id_ob: 'efb-PLG-261113-AAAAAAAAAAAA.pdf', form_id: 7 }); fun_sendBack_emsFormBuilder({ id_: 'one', value: '@file@', url: 'u2', id_ob: 'efb-PLG-261113-BBBBBBBBBBBB.pdf', form_id: 7 }); return sendBack_emsFormBuilder_pub.filter((r) => r.id_ === 'one').map((r) => r.url).join(); }) === 'u2');
  const xss = await p.evaluate(() => { EFBUpload.queue('pics', 7, [new File([new Uint8Array(5)], '<img src=x onerror=window.pwned=1>.png', { type: 'image/png' })]); return [!!window.pwned, document.querySelectorAll('#pics_-list img[onerror]').length]; });
  check('a hostile file name is shown as text', xss[0] === false && xss[1] === 0, xss);

  /* ---------------------------------------------------------------- C (fa_IR, RTL, 375 px) */
  lang = 'fa'; dir = 'rtl';
  await p.goto('http://site.test/form-fa');
  await p.addScriptTag({ content: SETUP('fa_IR') });
  await p.addStyleTag({ path: CSS });
  await p.addScriptTag({ path: UP });
  await p.evaluate(() => EFBUpload.bind('docs', 7));
  await p.setInputFiles('#docs_', [{ name: 'قرارداد امضاشده.pdf', mimeType: 'application/pdf', buffer: Buffer.alloc(1536 * 1024, 1) }, { name: 'scan-2.pdf', mimeType: 'application/pdf', buffer: Buffer.alloc(100 * 1024, 1) }]);
  await p.waitForTimeout(80);
  await p.evaluate(() => { calls[0].finish(true); });
  await p.waitForTimeout(50);
  const fa = await p.evaluate(() => {
    const li = document.querySelectorAll('#docs_-list li');
    const meta = li[0].querySelector('.efb-file-meta');
    const name = li[1].querySelector('.efb-file-name');
    const rm = li[1].querySelector('.efb-file-rm').getBoundingClientRect();
    const main = li[1].querySelector('.efb-file-main').getBoundingClientRect();
    const sum = document.getElementById('docs_-sum');
    return { meta: meta.textContent, metaBdi: meta.querySelectorAll('bdi').length, nameDir: name.getAttribute('dir'), nameBidi: getComputedStyle(name).unicodeBidi, rmLeftOfText: rm.right <= main.left + 1 && Math.abs(rm.top - main.top) < 30, sum: sum.textContent, sumBdi: sum.querySelectorAll('bdi').length, scroll: document.documentElement.scrollWidth <= window.innerWidth };
  });
  check('fa_IR: a too large file is refused with the size in Persian digits', /۱/.test(fa.meta) && fa.metaBdi >= 1, fa);
  check('fa_IR: the counter in Persian digits, values isolated in <bdi>', /۱ of ۳ files/.test(fa.sum) && fa.sumBdi >= 2, fa);
  check('fa_IR: a Latin file name keeps its order (dir=auto, unicode-bidi plaintext)', fa.nameDir === 'auto' && fa.nameBidi === 'plaintext', fa);
  check('fa_IR: the remove button sits at the logical end (left in RTL)', fa.rmLeftOfText, fa);
  check('fa_IR: no horizontal scroll at 375 px', fa.scroll, fa);
  await p.screenshot({ path: path.join(process.env.EFB_SHOTS || require('os').tmpdir(), 'f07-upload-queue-fa-375.png') }).catch(() => {});

  /* ---------------------------------------------------------------- F: resizeImage */
  const rz = await p.evaluate(async () => {
    const jpeg = (w, h) => new Promise((res) => { const c = document.createElement('canvas'); c.width = w; c.height = h; const x = c.getContext('2d'); for (let i = 0; i < 40; i++) { x.fillStyle = `hsl(${i * 9},70%,50%)`; x.fillRect((i * w) / 40, 0, w / 40, h); } x.fillStyle = '#000'; x.fillRect(0, 0, w / 10, h / 10); c.toBlob((b) => res(b), 'image/jpeg', 0.95); });
    // A TIFF / EXIF APP1 with Orientation = 6 and a GPS IFD (latitude ref "N").
    const exif = () => {
      const b = []; const u16 = (v) => b.push((v >> 8) & 255, v & 255); const u32 = (v) => b.push((v >>> 24) & 255, (v >> 16) & 255, (v >> 8) & 255, v & 255);
      b.push(0x4d, 0x4d); u16(42); u32(8);              // big endian, IFD0 at 8
      u16(2);                                            // two entries
      u16(0x0112); u16(3); u32(1); u16(6); u16(0);      // Orientation = 6
      u16(0x8825); u16(4); u32(1); u32(38);             // GPS IFD at 38
      u32(0);                                            // next IFD
      u16(1); u16(0x0001); u16(2); u32(2); b.push(0x4e, 0, 0, 0); u32(0); // GPSLatitudeRef "N"
      const tiff = new Uint8Array(b); const head = new TextEncoder().encode('Exif\0\0');
      const len = 2 + head.length + tiff.length; const seg = new Uint8Array(4 + head.length + tiff.length);
      seg.set([0xff, 0xe1, (len >> 8) & 255, len & 255]); seg.set(head, 4); seg.set(tiff, 4 + head.length); return seg;
    };
    const withExif = async (blob) => { const a = new Uint8Array(await blob.arrayBuffer()); const e = exif(); const out = new Uint8Array(a.length + e.length); out.set(a.slice(0, 2)); out.set(e, 2); out.set(a.slice(2), 2 + e.length); return new File([out], 'IMG_0001.JPG', { type: 'image/jpeg' }); };
    const hasExif = async (blob) => { const t = new Uint8Array(await blob.arrayBuffer()); for (let i = 0; i < t.length - 6; i++) if (t[i] === 0x45 && t[i + 1] === 0x78 && t[i + 2] === 0x69 && t[i + 3] === 0x66 && t[i + 4] === 0 && t[i + 5] === 0) return true; return false; };
    const dims = async (blob) => { const bm = await createImageBitmap(blob); return [bm.width, bm.height]; };
    const big = await withExif(await jpeg(4000, 3000));
    const out = await EFBUpload.resizeImage(big, 1920, 0.85);
    const raw = await createImageBitmap(big, { imageOrientation: 'none' });
    const r = { inExif: await hasExif(big), rawDims: [raw.width, raw.height], outDims: await dims(out), outExif: await hasExif(out), outType: out.type, outName: out.name, smaller: out.size < big.size };
    const gif = new File([new Uint8Array([71, 73, 70, 56, 57, 97, 1, 0, 1, 0, 0, 0, 0, 59])], 'a.gif', { type: 'image/gif' });
    r.gifSame = (await EFBUpload.resizeImage(gif, 320, 0.85)) === gif;
    const tiny = new File([await jpeg(40, 30)], 'tiny.jpg', { type: 'image/jpeg' });
    r.tinySame = (await EFBUpload.resizeImage(tiny, 1920, 0.95)) === tiny;
    const keep = window.createImageBitmap; window.createImageBitmap = undefined;
    r.noApiSame = (await EFBUpload.resizeImage(big, 1920, 0.85)) === big;
    window.createImageBitmap = keep;
    return r;
  });
  check('fixture: the original carries EXIF (orientation 6, GPS)', rz.inExif, rz);
  check('resize: at most 1920 px, turned as the camera held it (orientation 6 → portrait 1440 × 1920)', rz.outDims[0] === 1440 && rz.outDims[1] === 1920, rz);
  check('resize: no EXIF (no GPS) in the result, JPEG, smaller', rz.outExif === false && rz.outType === 'image/jpeg' && rz.smaller && /\.jpg$/.test(rz.outName), rz);
  check('resize: a GIF is untouched', rz.gifSame, rz);
  check('resize: a result larger than the original keeps the original', rz.tinySame, rz);
  check('resize: without createImageBitmap the original is sent', rz.noApiSame, rz);
  // In the queue: a resized photo is what is uploaded.
  await p.goto('http://site.test/form-resize');
  await p.addScriptTag({ content: SETUP('en_US') });
  await p.addScriptTag({ path: UP });
  const q = await p.evaluate(async () => {
    document.getElementById('docs_').setAttribute('data-img-max', '800');
    document.getElementById('docs_').setAttribute('data-max-mb', '20');
    document.getElementById('docs_').setAttribute('data-max-total', '40');
    document.getElementById('docs_').setAttribute('accept', '.jpg,.png');
    EFBUpload.bind('docs', 7);
    const c = document.createElement('canvas'); c.width = 3000; c.height = 2000; const x = c.getContext('2d'); x.fillStyle = '#c33'; x.fillRect(0, 0, 3000, 2000); x.fillStyle = '#33c'; x.fillRect(100, 100, 2000, 1500);
    const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.98));
    EFBUpload.queue('docs', 7, [new File([blob], 'photo.jpg', { type: 'image/jpeg' })]);
    const state = document.querySelector('#docs_-list li').className;
    await new Promise((r) => setTimeout(r, 600));
    return { state, sentName: calls[0] && calls[0].name, sentSize: calls[0] && calls[0].size, orig: blob.size };
  });
  check('queue: a photo is prepared, then the smaller file is sent', /resizing|queued|uploading/.test(q.state) && q.sentName === 'photo.jpg' && q.sentSize < q.orig, q);

  check('no page error', errs.length === 0, errs);
  await b.close();
  console.log(`\nRESULTS: ${passes} passed, ${failures} failed`);
  process.exit(failures ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
