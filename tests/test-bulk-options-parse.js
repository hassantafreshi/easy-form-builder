/**
 * F05-A: EfbChoices.parseBulk(), the "Add in bulk" lines (public/assets/js/choices-efb.js): "Label",
 * "Label|value", "Label|value|calc" (kept only with F04), "\|" = "|", empty lines and "## group" lines
 * skipped, " < > removed and reported by line, repeated labels warned, repeated stored values reported
 * (also against the choices already there when appending), 500 items at most, a BOM ignored, Persian
 * digits in the calc column read; 500 lines parsed in well under 2 s.
 *
 * Run: node tests/test-bulk-options-parse.js
 */
'use strict';
const path = require('path');
const C = require(path.join(__dirname, '..', 'public', 'assets', 'js', 'choices-efb.js'));
let pass = 0, fail = 0;
function t(label, actual, expected) {
  const a = JSON.stringify(actual), e = JSON.stringify(expected);
  if (a === e) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '\n  expected ' + e + '\n  actual   ' + a); }
}
let r = C.parseBulk('Tehran\nIsfahan|IR-10\n\n  \nShiraz|IR-07|۱۲');
t('label only, label|value, empty lines skipped; calc dropped without F04', r.items, [{ label: 'Tehran' }, { label: 'Isfahan', value: 'IR-10' }, { label: 'Shiraz', value: 'IR-07' }]);
r = C.parseBulk('Shiraz|IR-07|۱۲\nX|x|abc', { calc: true });
t('the calc column with F04 (Persian digits read, a word refused)', r.items, [{ label: 'Shiraz', value: 'IR-07', calc: '12' }, { label: 'X', value: 'x' }]);
r = C.parseBulk('A \\| B|ab\nC|c\\|d');
t('"\\|" is a "|" in the label and the value', r.items, [{ label: 'A | B', value: 'ab' }, { label: 'C', value: 'c|d' }]);
r = C.parseBulk('<b>Bold</b>|v1\nQuote "x"|v2\nfine|v3');
t('tags removed, " < > removed, the lines reported', [r.items.map((i) => i.label), r.stripped], [['Bold', 'Quote x', 'fine'], [1, 2]]);
r = C.parseBulk('Same|a\nsame|b\nOther|a');
t('a repeated label is warned (case-insensitive), a repeated stored value reported', [r.dupLabels, r.dupValues, r.items.length], [['same'], ['a'], 3]);
r = C.parseBulk('New|x\nKept', { existing: [{ label: 'Kept', value: '' }, { label: 'Old', value: 'x' }] });
t('appending: values and labels already in the field count', [r.dupValues, r.dupLabels], [['x'], ['Kept']]);
r = C.parseBulk('Label|Label');
t('a stored value equal to its label is not stored (value = label)', r.items, [{ label: 'Label' }]);
r = C.parseBulk('## Group one\nA\n## Group two\nB');
t('group lines are skipped and counted (a later phase)', [r.items.length, r.skippedGroups], [2, 2]);
r = C.parseBulk('﻿First\r\nSecond\rThird');
t('a BOM and CRLF / CR line ends', r.items.map((i) => i.label), ['First', 'Second', 'Third']);
r = C.parseBulk('|only value\n   |   ');
t('a line without a label is skipped and reported', [r.items.length, r.emptyLabel], [0, [1, 2]]);
r = C.parseBulk(Array.from({ length: 520 }, (_, i) => 'L' + i + '|v' + i).join('\n'));
t('500 items at most; the rest reported', [r.items.length, r.tooMany], [500, true]);
r = C.parseBulk('  spaced   out  |  val  ');
t('spaces trimmed and folded', r.items, [{ label: 'spaced out', value: 'val' }]);
r = C.parseBulk('x'.repeat(250) + '|' + 'y'.repeat(250));
t('label and value cut at 200 characters (the save limit)', [r.items[0].label.length, r.items[0].value.length], [200, 200]);
r = C.parseBulk('تهران|IR-23\nالرياض|SA-01\nCalifornia|CA');
t('Persian, Arabic and Latin lines', r.items.map((i) => i.value), ['IR-23', 'SA-01', 'CA']);
const big = Array.from({ length: 500 }, (_, i) => 'استان ' + i + '|IR-' + i).join('\n');
const t0 = Date.now();
r = C.parseBulk(big);
t('500 lines parsed in under 200 ms', [r.items.length, Date.now() - t0 < 200], [500, true]);
// The builder parses with this function (no second copy of the syntax in the builder).
const builder = require('fs').readFileSync(path.join(__dirname, '..', 'includes', 'admin', 'assets', 'js', 'choices-builder-efb.js'), 'utf8');
t('the builder dialog uses EfbChoices.parseBulk (one parser)', /C\(\)\.parseBulk\(/.test(builder) && !/split\('\|'\)/.test(builder), true);
console.log('\nRESULTS: ' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
