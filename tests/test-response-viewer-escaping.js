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

console.log('\n=== F01: left-to-right values (viewer <bdi>, email span) ===');
{
  const ltrRe = /^[+(0-9A-Z][0-9A-Z +\-\/().:]*$/;
  t('viewer wraps the generic value in <bdi>', /<bdi\$\{ltrEfb \? ' dir="ltr"' : ''\}>\$\{text_nr_efb\(q,1\)\}<\/bdi>/.test(src));
  t('viewer uses the same LTR test as the email', src.includes("/^[+(0-9A-Z][0-9A-Z +\\-\\/().:]*$/.test(c.value)"));
  const pub = fs.readFileSync(path.join(ROOT, 'includes/class-Emsfb-public.php'), 'utf8');
  t('email wraps matching values in <span dir="ltr" style="unicode-bidi:embed">', pub.includes(`$q = '<span dir="ltr" style="unicode-bidi:embed">'.$q.'</span>';`) && pub.includes("preg_match('/^[+(0-9A-Z][0-9A-Z +\\-\\/().:]*$/', $c['value'])"));
  for (const v of ['+989121234567', '0012345679', 'IR840120000000000123456789', '0912 123 4567', '(202) 555-0123', '12345-6789']) t('LTR value: ' + v, ltrRe.test(v));
  for (const v of ['سلام', 'hello', '+98<img>', '"x"', '', 'ir84']) t('not forced LTR: ' + JSON.stringify(v), !ltrRe.test(v));
}

console.log('\n=== the shipped sources still wire these in ===');
t('the render entry escapes the whole tree once', /content = escResponseTreeEfb\(content\);/.test(src));
t('the ip meta value is escaped', /userIp = escHtmlEfb\(userIp\);/.test(src));
t('the tracking code is escaped', /track = escHtmlEfb\(track\);/.test(src));
t('the date is escaped (after the admin\'s date formatter, QA1-028)', /date = escHtmlEfb\(typeof efb_admin_date_efb === 'function' \? efb_admin_date_efb\(date\) : date\);/.test(src));
t('the sender name is escaped after it is resolved', /byName = escHtmlEfb\(byName\);/.test(src));
t('a saved reply draft is escaped before it re-enters the editor', /shortcodeToHtml\(escHtmlEfb\(savedValue\)/.test(src));
t('a signature src must be a PNG data URL', /\^data:image\\\/png;base64,\[A-Za-z0-9\+\\\/\]\+=\{0,2\}\$/.test(src));

const listSrc = fs.readFileSync(LIST, 'utf8');
const conv = listSrc.slice(listSrc.indexOf('function convertToCSV_emsFormBuilder'), listSrc.indexOf('function generat_csv_emsFormBuilder'));
t('the second CSV exporter carries the same formula guard',
  conv.indexOf('val = "\'" + val;') !== -1 && /\^\[=\+\\-@\\t\\r\]/.test(conv));

console.log('\n=== F11-F partial answers tab ===');
{
  // The list of abandoned answers: contact, date and numbers are written with efb_partial_esc_efb; the
  // answers themselves open through fun_emsFormBuilder_show_messages (the choke point above).
  const part = listSrc.slice(listSrc.indexOf('let efb_partial_rows_efb'), listSrc.indexOf('function efb_partial_open_efb'));
  const page = { html: '' };
  const run = new Function('document', 'efb_var', part.replace('let efb_partial_rows_efb = [];', 'var efb_partial_rows_efb = [];') + '; return { render: efb_partial_render_efb, set: (r) => { efb_partial_rows_efb = r; } };');
  const api = run({ getElementById: () => ({ set innerHTML(v) { page.html = v; } }) }, { text: { partialContact: 'Contact <i>' } });
  api.set([{ id: '3"><img src=x onerror=alert(1)>', date: '<script>alert(1)</script>', progress: '50"><b>', step: '2<x>', contact_mask: 'j***@e<img src=x>.com', rows: [] }]);
  api.render(7);
  t('hostile partial row: no markup from the stored values', page.html.indexOf('<script>') === -1 && page.html.indexOf('<img') === -1 && page.html.indexOf('<b>') === -1);
  t('hostile partial row: contact and date escaped', page.html.indexOf('j***@e&lt;img src=x&gt;.com') !== -1 && page.html.indexOf('&lt;script&gt;alert(1)&lt;/script&gt;') !== -1);
  t('hostile partial row: ids and numbers forced to numbers', /efb_partial_open_efb\(NaN\)|efb_partial_open_efb\(3\)|efb_partial_open_efb\(0\)/.test(page.html) || page.html.indexOf('onerror') === -1);
  t('a phrase with markup is escaped too', page.html.indexOf('Contact &lt;i&gt;') !== -1);
  t('the partial viewer goes through fun_emsFormBuilder_show_messages', /fun_emsFormBuilder_show_messages\(content, '#first'/.test(listSrc));
}

console.log('\n=== F05: stored values (ov), "Other" and rankings in the viewer and the CSV ===');
{
  // efbChoiceTextEfb() gets rows of the escaped tree; a hostile ov / Other text / ranking label stays text.
  const fn = new Function('efb_var', grab('efbChoiceTextEfb') + 'return efbChoiceTextEfb;')({ text: { otherOpt: 'Other' } });
  const rows = escResponseTreeEfb([
    { type: 'radio', value: 'Fine', ov: '<img src=x onerror=alert(1)>' },
    { type: 'checkbox', value: '<b>x</b>', other: '1' },
    { type: 'radio', value: 'Same', ov: 'Same' },
    { type: 'multiselect', value: 'A@efb!B@efb!', ov: 'a@efb!b@efb!' },
  ]);
  const a = fn(rows[0]), b = fn(rows[1]), c = fn(rows[2]), d = fn(rows[3]);
  t('a hostile stored value is shown as text beside the label', a.indexOf('<img') === -1 && a.indexOf('&lt;img src=x onerror=alert(1)&gt;') !== -1 && a.indexOf('efb-msg-ov') !== -1, a);
  t('an "Other" answer: "Other: <text>", the text escaped and isolated', b === 'Other: <bdi>&lt;b&gt;x&lt;/b&gt;</bdi>', b);
  t('a stored value equal to the label is not repeated', c === 'Same', c);
  t('a multiselect lists its stored values with ", "', /\(a, b\)<\/small>$/.test(d), d);
  t('the ranking branch builds an <ol> from the escaped labels', /efb-msg-ranking/.test(src) && /String\(c\.value \|\| ''\)\.split\('@efb!'\)/.test(src));
  const lsrc = listSrc.slice(listSrc.indexOf('function efb_csv_choice_row_efb'), listSrc.indexOf('function efb_csv_choice_row_efb') + 1200);
  const conv2 = new Function('efb_var', lsrc.slice(0, lsrc.indexOf('\n}\n') + 3) + 'return efb_csv_choice_row_efb;')({ text: { otherOpt: 'Other' } });
  t('CSV: a ranking as "A > B > C" (labels), its stored values in value mode', [conv2({ type: 'ranking', value: 'A@efb!B@efb!', ov: 'a@efb!b@efb!' }, 'label').value, conv2({ type: 'ranking', value: 'A@efb!B@efb!', ov: 'a@efb!b@efb!' }, 'value').value].join('|') === 'A > B|a > b');
  t('CSV: "Other: text" with labels, the text alone with values; a choice\'s ov only in value mode', [conv2({ type: 'radio', value: 'Teal', other: '1' }, 'label').value, conv2({ type: 'radio', value: 'Teal', other: '1' }, 'value').value, conv2({ type: 'radio', value: 'Tehran', ov: 'IR-23' }, 'label').value, conv2({ type: 'radio', value: 'Tehran', ov: 'IR-23' }, 'value').value].join('|') === 'Other: Teal|Teal|Tehran|IR-23');
  t('CSV: a stored value that is a formula is still neutralised by the exporter', csvEscape_EFB(conv2({ type: 'radio', value: 'x', ov: '=HYPERLINK("e")' }, 'value').value).indexOf("'=") !== -1);
}

console.log('\n=== F04: a formatted number (disp) in the viewer and the CSV ===');
{
  // The number branch prints c.disp of the escaped tree inside <bdi dir="auto">, the canonical value in data-csv.
  const tree4 = escResponseTreeEfb([{ type: 'number', name: 'Amount', value: '1250000', disp: '<img src=x onerror=alert(1)>$1,250,000' }, { type: 'calc', name: 'Total', value: '-12', disp: '-۱۲ تومان' }]);
  t('F04: a hostile prefix in disp stays text', tree4[0].disp.indexOf('<img') === -1 && tree4[0].disp.indexOf('&lt;img') !== -1);
  const branch = src.match(/else if \(\(c\.type == "number" \|\| c\.type == "calc" \|\| c\.type == "range"\) && typeof c\.disp === 'string'[\s\S]*?m \+= `[^`]*`;/);
  t('F04: the viewer branch for number / calc / range exists', !!branch);
  t('F04: the display is isolated (bdi dir=auto) and the CSV gets the canonical number (data-csv)', !!branch && /<bdi class="efb efb-msg-num" dir="auto" data-csv="\$\{String\(c\.value\)/.test(branch[0]));
  t('F04: a negative canonical number is not prefixed in the CSV', csvEscape_EFB('-12') === '-12' && csvEscape_EFB('1250000.5') === '1250000.5');
}

console.log('\n=== F09: repeater rows in the viewer ===');
{
  global.efb_var = { text: { rowN: 'Row %s', rptRowsCount: '%s rows', rptCount: '%s (count)' }, wp_lan: 'en_US' };
  const { efbRptViewerSplitEfb, efbRptViewerBlockEfb } = new Function(grab('efbRptViewerSplitEfb') + grab('efbRptViewerBlockEfb') + 'return { efbRptViewerSplitEfb, efbRptViewerBlockEfb };')();
  const tree9 = escResponseTreeEfb([
    { id_: 'g', type: 'repeater', name: '<img src=x onerror=alert(1)>Trip', value: '2' },
    { id_: 'nm', type: 'text', name: 'Name', value: '<script>alert(2)</script>', rg: 'g', ri: '1' },
    { id_: 'nm', type: 'text', name: 'Name', value: 'Sara', rg: 'g', ri: '0' },
    { id_: 'x', type: 'text', name: 'Other', value: 'y' },
  ]);
  const sp = efbRptViewerSplitEfb(tree9);
  t('F09: the rows are taken out of the list and grouped by row', sp.rest.length === 2 && sp.groups.g.rows['0'][0].value === 'Sara' && sp.groups.g.rows['1'].length === 1);
  t('F09: an old entry (no rg) is not split', efbRptViewerSplitEfb([{ id_: 'a', value: 'b' }]) === null);
  const html9 = efbRptViewerBlockEfb(sp.groups.g, tree9[0], (rows) => rows.map((r) => `<span>${r.value}</span>`).join(''));
  t('F09: the block: "Row 1" before "Row 2", in row order (ri 0 before ri 1)', html9.indexOf('Row 1') < html9.indexOf('Sara') && html9.indexOf('Sara') < html9.indexOf('Row 2') && html9.indexOf('Row 2') < html9.indexOf('&lt;script&gt;'));
  t('F09: a hostile group name and value stay text', html9.indexOf('<img') === -1 && html9.indexOf('<script>') === -1);
  t('F09: the head carries the CSV key "{group} (count)" and the number of rows', /data-csv-key="&lt;img[^"]*Trip \(count\)"/.test(html9) && html9.indexOf('2 rows') !== -1);
}

console.log('\n=== F17: the full name / address line of a group (P22) ===');
{
  const NA = require(path.join(ROOT, 'public/assets/js/name-address-efb.js'));
  global.efb_na_data = { schemes: { ir: { disp: [['state', 'city', 'street', '#plaque']] } }, names: { fl: ['first', 'last'] }, words: [] };
  const rows = escResponseTreeEfb([
    { id_: 's', name: 'Street', type: 'address_line', value: '<img src=x onerror=alert(1)>', grp: 'g', part: 'street', cmp: 'address', sch: 'ir', na_full: '<img src=x onerror=alert(2)>' },
    { id_: 'p', name: '<b>Plaque</b>', type: 'address_line', value: '12', grp: 'g', part: 'plaque', cmp: 'address', sch: 'ir' },
    { id_: 'f', name: 'First', type: 'text', value: '"><svg onload=1>', grp: 'n', part: 'first', cmp: 'name', sch: 'fl' },
  ]);
  const g = NA.groups(rows);
  const stored = NA.groupText(g[0], '', false), built = NA.groupText(g[0], 'en_US', false), name = NA.groupText(g[1], '', false);
  t('the stored line of a hostile street is escaped', stored.indexOf('<img') === -1 && stored.indexOf('&lt;img') !== -1);
  t('the built line (street and a hostile label) is escaped', built.indexOf('<') === -1 && built.indexOf('&lt;b&gt;Plaque&lt;/b&gt; 12') !== -1, built);
  t('a hostile name part is escaped in the full name', name.indexOf('<svg') === -1 && name.indexOf('"') === -1);
  t('old answers have no group: no line', NA.groups(escResponseTreeEfb([{ id_: 'x', type: 'text', value: 'a' }])).length === 0);
  t('the viewer draws the line from EFBNameAddr on the escaped tree, inside <bdi> with unicode-bidi plaintext', /naLine = naGrp \? EFBNameAddr\.groupText/.test(src) && /unicode-bidi:plaintext"><bdi>\$\{naLine\}<\/bdi>/.test(src));
}

console.log('\n========================================');
console.log(`RESULTS: ${pass} passed, ${fail} failed`);
console.log('========================================');
process.exit(fail ? 1 : 0);
