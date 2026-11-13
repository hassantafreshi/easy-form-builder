#!/usr/bin/env node
/**
 * F07: the browser's save rules of the upload keys (EFBUpload.rules.sanitizeKey, upload-efb.js) on the
 * vectors tests/test-upload-keys-roundtrip.php runs through Emsfb\Uploads::sanitize_key(), the effective
 * configuration (rules.config, twin of Uploads::config) and the attributes the builder canvas gets.
 *
 * Run: node tests/test-upload-keys-parity.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

let pass = 0, fail = 0;
function test(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + label); }
  else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + JSON.stringify(expected) + '\n  actual   ' + JSON.stringify(actual)); }
}

const src = fs.readFileSync(path.join(__dirname, '..', 'public', 'assets', 'js', 'upload-efb.js'), 'utf8');
const win = { efb_var: { uploads: { max_files_cap: 20, plan: { multi: true, resize: true, media: true } } } };
win.window = win;
vm.createContext(win);
vm.runInContext(src, win);
const U = win.EFBUpload;
const vectors = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures', 'upload-keys-vectors.json'), 'utf8')).vectors;
vectors.forEach(([k, v, want]) => test(`sanitizeKey(${k}, ${JSON.stringify(v)})`, U.rules.sanitizeKey(k, v), want));
test('unknown key is empty', U.rules.sanitizeKey('nope', '5'), '');
test('an object is empty', U.rules.sanitizeKey('max_files', { a: 1 }), '');

// config (Uploads::config)
const base = { id_: 'f1', type: 'file', file: 'image', max_fsize: '5' };
let c = U.rules.config(Object.assign({}, base, { max_files: '5', min_files: '7', max_total_mb: '2', capture: 'environment', img_max_px: '1920' }));
test('config: max 5, min clamped to 5', [c.multi, c.max_files, c.min_files], [true, 5, 5]);
test('config: a total under the per-file size becomes the per-file size', c.max_total_mb, 5);
test('config: camera kept for an image preset', c.capture, 'environment');
test('config: resize kept for an image preset', c.img_max_px, 1920);
c = U.rules.config(Object.assign({}, base, { file: 'document', value: 'document', max_files: '3', capture: 'user', img_max_px: '1920' }));
test('config: no camera, no resize for documents', [c.capture, c.img_max_px], ['', 0]);
c = U.rules.config(Object.assign({}, base, { max_files: '5' }), { multi: false, resize: false, media: false });
test('config: the plan without several files keeps one', [c.multi, c.max_files], [false, 1]);
c = U.rules.config(Object.assign({}, base, { required: '1' }));
test('config: a required field of one file needs one', [c.max_files, c.min_files, c.required], [1, 1, true]);
c = U.rules.config({ id_: 'r', type: 'audio_recorder', max_files: '5', file_store: 'private' });
test('config: a recorder keeps one file in the uploads folder', [c.max_files, c.file_store], [1, '']);
c = U.rules.config(Object.assign({}, base, { max_files: '3', file_store: 'media' }), { multi: true, resize: true, media: false });
test('config: Media Library needs the plan', c.file_store, '');
test('config: a field without the keys = today (one file, 20 MB, uploads folder)', (() => { const x = U.rules.config({ id_: 'z', type: 'file' }); return [x.max_files, x.max_mb, x.file_store, x.preset]; })(), [1, 20, '', 'allformat']);

// canvas attributes and list markup
const a = U.attrs(Object.assign({}, base, { max_files: '4', capture: 'user', max_total_mb: '12', img_max_px: '1600' }));
test('attrs: multiple, capture and the limits', [/ multiple/.test(a), /capture="user"/.test(a), /data-max-files="4"/.test(a), /data-max-total="12"/.test(a), /data-img-max="1600"/.test(a)], [true, true, true, true, true]);
test('attrs: one file has no multiple', / multiple/.test(U.attrs(base)), false);
test('listMarkup only for several files', [U.listMarkup(base), /_-list/.test(U.listMarkup(Object.assign({}, base, { max_files: '2' })))], ['', true]);
test('listMarkup escapes the id', /<script/.test(U.listMarkup({ id_: '"><script>', type: 'file', max_files: '2' })), false);

console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
