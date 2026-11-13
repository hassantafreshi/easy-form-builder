#!/usr/bin/env node
/**
 * F07-E in the shipped admin scripts, without WordPress:
 *  - response-viewer-efb.js: the files of one field as cards (efbFileCardsEfb), the extension read from
 *    the address (the old lastIndexOf(callback) always answered -1), a private file opened through the
 *    admin download action only (never its address), only YYYY/MM/efb-… references, sizes in the site's
 *    digits (Persian on fa_IR);
 *  - list_form-efb.js: the form CSV puts every file of a field into its one cell joined with " | "
 *    (the second file used to overwrite the first), a field of one file as before.
 *
 * Run: node tests/test-upload-viewer-csv.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
let pass = 0, fail = 0;
const t = (label, actual, expected) => { const ok = JSON.stringify(actual) === JSON.stringify(expected); if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + JSON.stringify(expected) + '\n  actual   ' + JSON.stringify(actual)); } };
const ok = (label, cond, detail) => t(label + (cond ? '' : ' ' + JSON.stringify(detail)), !!cond, true);

const J = path.join(__dirname, '..', 'includes', 'admin', 'assets', 'js');
const viewer = fs.readFileSync(path.join(J, 'response-viewer-efb.js'), 'utf8');
const helpers = viewer.slice(viewer.indexOf('/* ------------------------------------------------------------------ F07: files in the viewer */'));
const ctx = { efb_var: { wp_lan: 'en_US', text: {}, uploads: { download: 'https://site.test/wp-admin/admin-ajax.php?action=efb_file_get_Emsfb&nonce=abc', private_marker: '/efb-private/' } }, Intl };
vm.createContext(ctx);
vm.runInContext(helpers, ctx);
const u = 'https://site.test/wp-content/uploads/2026/11/';
t('the extension of an address (query and hash ignored)', [ctx.efbFileExtEfb(u + 'a.MP4?x=1#y'), ctx.efbFileExtEfb(u + 'noext'), ctx.efbFileExtEfb(u + 'clip.avi')], ['mp4', '', 'avi']);
t('a public file keeps its address', ctx.efbFileHrefEfb(u + 'efb-PLG-261113-AAAAAAAAAAAA.pdf'), u + 'efb-PLG-261113-AAAAAAAAAAAA.pdf');
const priv = 'https://site.test/wp-content/uploads/efb-private/2026/11/efb-PLG-261113-BBBBBBBBBBBB.pdf';
t('a private file goes through the admin download (never its address)', ctx.efbFileHrefEfb(priv), 'https://site.test/wp-admin/admin-ajax.php?action=efb_file_get_Emsfb&nonce=abc&f=2026%2F11%2Fefb-PLG-261113-BBBBBBBBBBBB.pdf');
t('a private reference that is not YYYY/MM/efb-… is not linked', [ctx.efbFileHrefEfb('https://site.test/wp-content/uploads/efb-private/../../wp-config.php'), ctx.efbFileHrefEfb('https://site.test/wp-content/uploads/efb-private/2026/11/other.pdf')], ['#', '#']);
const cards = ctx.efbFileCardsEfb([
  { url: u + 'efb-PLG-261113-CCCCCCCCCCCC.png', fnm: 'photo.png', fsz: '2048' },
  { url: u + 'efb-PLG-261113-DDDDDDDDDDDD.pdf', fsz: '3145728' },
  { url: priv, fnm: '&lt;img src=x onerror=alert(1)&gt;.pdf', fsz: '10' },
]);
ok('three cards in one list, labelled with the count', (cards.match(/class="efb efb-file-card"/g) || []).length === 3 && /role="list" aria-label="3 files"/.test(cards), cards);
ok('an image gets a thumbnail, the others an icon', /<img src="https:\/\/site\.test\/wp-content\/uploads\/2026\/11\/efb-PLG-261113-CCCCCCCCCCCC\.png" alt="" class="efb efb-file-card-thumb"/.test(cards) && (cards.match(/bi-file-earmark/g) || []).length === 2, cards);
ok('the visitor\'s name when kept, else the stored name; sizes', /title="photo\.png">photo\.png</.test(cards) && />efb-PLG-261113-DDDDDDDDDDDD\.pdf</.test(cards) && />2 KB</.test(cards) && />3 MB</.test(cards), cards);
ok('the escaped name stays escaped (the rows are escaped on entry)', cards.indexOf('<img src=x') === -1 && cards.indexOf('&lt;img src=x onerror=alert(1)&gt;.pdf') !== -1, cards);
ok('every card carries its CSV address (data-csv)', (cards.match(/data-csv="/g) || []).length === 3 && cards.indexOf('data-csv="' + priv) === -1, cards);
ctx.efb_var.wp_lan = 'fa_IR';
ok('sizes in Persian digits on fa_IR', /۲ KB/.test(ctx.efbFileSizeEfb(2048)) && /۳ MB/.test(ctx.efbFileSizeEfb(3145728)), [ctx.efbFileSizeEfb(2048), ctx.efbFileSizeEfb(3145728)]);
ok('the viewer no longer uses lastIndexOf with a callback', viewer.indexOf('lastIndexOf(x => x == ".")') === -1, null);

/* The form CSV (list_form-efb.js fun_export_rows_for_Subscribe_emsFormBuilder). */
const list = fs.readFileSync(path.join(J, 'list_form-efb.js'), 'utf8');
const start = list.indexOf('function fun_export_rows_for_Subscribe_emsFormBuilder(value)');
const end = list.indexOf('\n}\n', list.indexOf('window.efb_export_rows_efb = exp;', start)) + 3;
const c2 = { efb_var: { text: { createDate: 'Date' } }, form_type_emsFormBuilder: 'form', window: {}, localStorage: { removeItem() {}, getItem() { return null; } }, console };
c2.window = c2;
c2.efbParseJson = (s) => JSON.parse(s);
c2.replaceContentMessageEfb = (s) => s;
c2.efbFileHrefEfb = ctx.efbFileHrefEfb;
vm.createContext(c2);
vm.runInContext(list.slice(start, end), c2);
const content = JSON.stringify([
  { id_: 'docs', name: 'Documents', type: 'file', value: '@file@', url: u + 'efb-PLG-261113-AAAAAAAAAAAA.pdf', amount: 2 },
  { id_: 'docs', name: 'Documents', type: 'file', value: '@file@', url: u + 'efb-PLG-261113-BBBBBBBBBBBB.png', amount: 2 },
  { id_: 'cv', name: 'CV', type: 'file', value: '@file@', url: priv, amount: 3 },
  { id_: 'nm', name: 'Name', type: 'text', value: 'Sara', amount: 4 },
]);
c2.fun_export_rows_for_Subscribe_emsFormBuilder([{ msg_id: '7', date: '2026-11-13', content }, { msg_id: '8', date: '2026-11-13', content: JSON.stringify([{ id_: 'docs', name: 'Documents', type: 'file', value: '@file@', url: u + 'efb-PLG-261113-ZZZZZZZZZZZZ.pdf', amount: 2 }]) }]);
const rows = c2.efb_export_rows_efb || [];
const head = rows[0] || [];
const col = (n) => head.indexOf(n);
t('CSV: both files of the field in its one cell', rows[1] && rows[1][col('Documents')], u + 'efb-PLG-261113-AAAAAAAAAAAA.pdf | ' + u + 'efb-PLG-261113-BBBBBBBBBBBB.png');
t('CSV: the next entry starts its cell afresh', rows[2] && rows[2][col('Documents')], u + 'efb-PLG-261113-ZZZZZZZZZZZZ.pdf');
t('CSV: a private file as its admin download link', rows[1] && /action=efb_file_get_Emsfb/.test(rows[1][col('CV')]), true);
t('CSV: other answers as before', rows[1] && rows[1][col('Name')], 'Sara');

console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
