/**
 * F17-B / P22: the two twins agree. PHP (Emsfb\Address_Schemes, run through `php -r`) and JavaScript (EFBNameAddr)
 * build the same one-line and multi-line address of every format in English, Persian and Arabic, the same name of
 * every name format, the same groups from an answer's rows, and the page's data (runtime_data / display_data) is the
 * JSON definition itself (one source).
 *
 * Run: node tests/test-address-schemes-parity.js
 */
'use strict';
const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const NA = require(path.join(__dirname, '../public/assets/js/name-address-efb.js'));
const DATA = JSON.parse(fs.readFileSync(path.join(__dirname, '../includes/data/name-address-efb.json'), 'utf8'));
let pass = 0, fail = 0;
const t = (name, ok, info) => { if (ok) { pass++; console.log('[PASS] ' + name); } else { fail++; console.log('[FAIL] ' + name + (info ? ': ' + info : '')); } };

const sample = {};
Object.keys(DATA.schemes).forEach((k) => { sample[k] = {}; DATA.schemes[k].parts.forEach((p, i) => { sample[k][p.p] = i === 1 ? '' : (p.p + '-' + k + (i % 3 === 0 ? ' ۱۲' : '')); }); });
const labels = { plaque: 'Plaque', floor: 'Floor', unit: 'Unit', postal: 'Postal code', short_code: 'Short address', building_no: 'No.', makani: 'Makani', po_box: 'P.O. Box' };
const names = {}; Object.keys(DATA.name_formats).forEach((k) => { names[k] = {}; DATA.name_formats[k].parts.forEach((p) => { names[k][p.p] = p.p === 'middle' ? '' : 'N' + p.p; }); });
const rows = [
  { id_: 'a', name: 'First', type: 'text', value: 'علی‌رضا', grp: 'a', part: 'first', cmp: 'name', sch: 'fl' },
  { id_: 'b', name: 'Last', type: 'text', value: 'O@efb@dq#Neil', grp: 'a', part: 'last', cmp: 'name', sch: 'fl' },
  { id_: 'c', name: 'Street', type: 'address_line', value: '12 Main', grp: 'c', part: 'line1', cmp: 'address', sch: 'us', na_full: 'stored line' },
  { id_: 'd', name: 'ZIP', type: 'postalcode', value: '02134', grp: 'c', part: 'postal', cmp: 'address', sch: 'us' },
  { id_: 'e', name: 'Old', type: 'text', value: 'x' },
];
const php = `define('ABSPATH', '/'); function esc_attr($v){return $v;} function esc_html($v){return $v;}
require ${JSON.stringify(path.join(__dirname, '../includes/class-Emsfb-address-schemes.php'))};
require ${JSON.stringify(path.join(__dirname, '../includes/name-address-functions-efb.php'))};
$in = json_decode(stream_get_contents(STDIN), true); $o = array();
foreach ($in['sample'] as $k => $parts) foreach (array('en_US', 'fa_IR', 'ar') as $l) { $o['a'][$k][$l] = Emsfb\\Address_Schemes::address_text($k, $parts, $l, false, $in['labels']); $o['m'][$k][$l] = Emsfb\\Address_Schemes::address_text($k, $parts, $l, true, $in['labels']); }
foreach ($in['names'] as $k => $parts) $o['n'][$k] = Emsfb\\Address_Schemes::name_text($parts, $k);
$o['g'] = Emsfb\\Address_Schemes::groups($in['rows']);
$o['gt'] = array_map(function ($g) { return Emsfb\\Address_Schemes::group_text($g, '', false); }, $o['g']);
$o['gtl'] = array_map(function ($g) { return Emsfb\\Address_Schemes::group_text($g, 'en_US', false); }, $o['g']);
$o['rt'] = Emsfb\\Address_Schemes::runtime_data(array(array('type' => 'form'), array('addr_scheme' => 'ir', 'name_format' => 'ar4')));
$o['dd'] = Emsfb\\Address_Schemes::display_data();
$o['p22'] = array(efb_address_display($in['rows']), efb_name_display($in['rows']), efb_address_display(array('line1' => '1 A St', 'postal' => '90210', 'city_text' => 'LA', 'state' => 'CA'), 'en_US', array('scheme' => 'us', 'multiline' => true)), efb_name_display(array('first' => 'A', 'father' => 'B', 'family' => 'C'), '', array('format' => 'ar3')));
echo json_encode($o, JSON_UNESCAPED_UNICODE);`;
const out = JSON.parse(execFileSync('php', ['-r', php], { input: JSON.stringify({ sample, labels, names, rows }), encoding: 'utf8' }));
global.efb_na_data = out.dd;
Object.keys(sample).forEach((k) => ['en_US', 'fa_IR', 'ar'].forEach((l) => {
  const js = NA.addressText(k, sample[k], l, false, labels);
  t(`${k} ${l}: one line the same`, js === out.a[k][l], JSON.stringify([js, out.a[k][l]]));
  const jm = NA.addressText(k, sample[k], l, true, labels);
  t(`${k} ${l}: lines the same`, jm === out.m[k][l], JSON.stringify([jm, out.m[k][l]]));
}));
t('Persian / Arabic join with "، "', out.a.ir.fa_IR.indexOf('، ') > 0 && out.a.ir.en_US.indexOf('، ') < 0);
t('an empty part leaves no separator', !/(, ,|،\s*،)/.test(out.a.ir.en_US + out.a.ir.fa_IR));
t('a labelled part ("#plaque"): "Plaque <value>"', out.a.ir.en_US.indexOf('Plaque plaque-ir') !== -1);
Object.keys(names).forEach((k) => t(`name ${k} the same`, NA.nameText(names[k], k) === out.n[k], JSON.stringify([NA.nameText(names[k], k), out.n[k]])));
const jg = NA.groups(rows);
t('groups of an answer the same (PHP and JS)', JSON.stringify(jg) === JSON.stringify(out.g), JSON.stringify([jg, out.g]));
t('group text: the stored line without a locale, built with one', JSON.stringify(jg.map((g) => NA.groupText(g, '', false))) === JSON.stringify(out.gt) && JSON.stringify(jg.map((g) => NA.groupText(g, 'en_US', false))) === JSON.stringify(out.gtl), JSON.stringify([out.gt, out.gtl]));
t('the quote token comes back as a quote', out.g[0].parts.last === 'O"Neil');
t('runtime_data: only the formats the form uses', JSON.stringify(Object.keys(out.rt.schemes)) === '["ir"]' && JSON.stringify(Object.keys(out.rt.names)) === '["ar4"]');
t('display data = the JSON definition (one source)', Object.keys(DATA.schemes).every((k) => JSON.stringify(out.dd.schemes[k].disp) === JSON.stringify(DATA.schemes[k].disp)));
t('P22 PHP: efb_address_display(rows) / efb_name_display(rows) / a parts map / ar3', JSON.stringify(out.p22) === JSON.stringify(['stored line', 'علی‌رضا O"Neil', '1 A St\nLA, CA 90210', 'A B C']), JSON.stringify(out.p22));
console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
