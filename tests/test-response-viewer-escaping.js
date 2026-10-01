/**
 * The viewer's escapers and both CSV exporters, as units.
 *
 * fun_emsFormBuilder_show_messages() is the single choke point every stored
 * response passes on its way to innerHTML, and csvEscape_EFB /
 * convertToCSV_emsFormBuilder are the two places a stored value is written
 * into a file a spreadsheet will open. This file pins their behaviour without
 * a browser: the functions are lifted out of the shipped sources and run
 * directly, so a change to either file is caught here before any end-to-end
 * run.
 *
 * Browser proof of the same guarantees: tests/test-response-box-xss-browser.js
 *
 * Run: node tests/test-response-viewer-escaping.js
 */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const VIEWER = path.join(ROOT, 'includes/admin/assets/js/response-viewer-efb.js');
const LIST = path.join(ROOT, 'includes/admin/assets/js/list_form-efb.js');

const src = fs.readFileSync(VIEWER, 'utf8');

/** Lift one top-level function out of the shipped file. */
function grab(name) {
  const m = src.match(new RegExp('function ' + name + '\\([\\s\\S]*?\\n}\\n'));
  if (!m) throw new Error('missing ' + name + ' in response-viewer-efb.js');
  return m[0];
}

const { escHtmlEfb, escResponseTreeEfb, csvEscape_EFB } = new Function(
  grab('escHtmlEfb') + grab('escResponseTreeEfb') + grab('csvNeutralizeFormula_EFB') + grab('csvEscape_EFB') +
  'return { escHtmlEfb, escResponseTreeEfb, csvEscape_EFB };'
)();

let pass = 0;
let fail = 0;
function t(label, condition, detail) {
  if (condition) { pass++; console.log(`[PASS] ${label}`); }
  else { fail++; console.log(`[FAIL] ${label}${detail ? ' — ' + detail : ''}`); }
}

console.log('=== escHtmlEfb ===');
t('markup is neutralised', escHtmlEfb('<img src=x onerror=alert(1)>') === '&lt;img src=x onerror=alert(1)&gt;');
t('quotes and backtick are escaped', escHtmlEfb('"\'`') === '&quot;&#39;&#96;');
t('ampersand is escaped first, not doubled', escHtmlEfb('&lt;') === '&amp;lt;');
t('null and undefined become an empty string', escHtmlEfb(null) === '' && escHtmlEfb(undefined) === '');
t('the number 0 survives as "0"', escHtmlEfb(0) === '0');

console.log('\n=== escResponseTreeEfb ===');
const tree = escResponseTreeEfb([
  { type: 'maps', value: [{ lat: 1, lng: 2, address: '<b>x</b>' }] },
  { type: 'text', value: 'a@efb@nq#<script>alert(1)</script>' },
  { type: 'textarea', value: 'l1<br>l2' },
  { type: 'rating', value: 4, price: null },
]);
t('a nested maps address is escaped', tree[0].value[0].address === '&lt;b&gt;x&lt;/b&gt;');
t('numbers inside the tree are untouched', tree[0].value[0].lat === 1 && tree[0].value[0].lng === 2);
t('structure is preserved (array stays an array)', Array.isArray(tree[0].value));
t('a script tag cannot survive in a text value', tree[1].value.indexOf('<script') === -1 && tree[1].value.indexOf('&lt;script') !== -1);
t('the one legitimate tag, a bare <br>, is restored', tree[2].value === 'l1<br>l2');
t('non-string leaves are left alone', tree[3].value === 4 && tree[3].price === null);

console.log('\n=== CSV formula injection ===');
t('=formula is prefixed', csvEscape_EFB('=1+1') === "'=1+1");
t('a HYPERLINK formula is prefixed and quoted', csvEscape_EFB('=HYPERLINK("http://evil","x")') === '"\'=HYPERLINK(""http://evil"",""x"")"');
t('+cmd is prefixed', csvEscape_EFB('+cmd|calc') === "'+cmd|calc");
t('@SUM is prefixed', csvEscape_EFB('@SUM(A1)') === "'@SUM(A1)");
t('-2+3 is prefixed', csvEscape_EFB('-2+3') === "'-2+3");
/* This exporter trims first, so a leading tab/CR is gone before the check —
   the cell cannot start with one either way. The other exporter does not trim
   and prefixes them; its guard is asserted at the bottom of this file. */
t('a tab-led cell cannot keep its leading tab', csvEscape_EFB('\tx') === 'x');
t('a tab-led formula is still prefixed', csvEscape_EFB('\t=1+1') === "'=1+1");
t('a negative integer is left alone', csvEscape_EFB('-5') === '-5');
t('a negative decimal is left alone', csvEscape_EFB('-5.25') === '-5.25');
t('plain text is left alone', csvEscape_EFB('hello') === 'hello');
t('a cell holding a comma is still quoted', csvEscape_EFB('a,b') === '"a,b"');
t('null becomes an empty cell', csvEscape_EFB(null) === '');

console.log('\n=== the shipped sources still wire these in ===');
t('the render entry escapes the whole tree once', /content = escResponseTreeEfb\(content\);/.test(src));
t('the ip meta value is escaped', /userIp = escHtmlEfb\(userIp\);/.test(src));
t('the tracking code is escaped', /track = escHtmlEfb\(track\);/.test(src));
t('the date is escaped', /date = escHtmlEfb\(date\);/.test(src));
t('the sender name is escaped after it is resolved', /byName = escHtmlEfb\(byName\);/.test(src));
t('a saved reply draft is escaped before it re-enters the editor', /shortcodeToHtml\(escHtmlEfb\(savedValue\)/.test(src));
t('a signature src must be a PNG data URL', /\^data:image\\\/png;base64,\[A-Za-z0-9\+\\\/\]\+=\{0,2\}\$/.test(src));

const listSrc = fs.readFileSync(LIST, 'utf8');
const conv = listSrc.slice(listSrc.indexOf('function convertToCSV_emsFormBuilder'), listSrc.indexOf('function generat_csv_emsFormBuilder'));
t('the second CSV exporter carries the same formula guard',
  conv.indexOf('val = "\'" + val;') !== -1 && /\^\[=\+\\-@\\t\\r\]/.test(conv));

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail ? 1 : 0);
