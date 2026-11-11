/**
 * Browser side of the length, number, selection and file-size limits
 * (docs/feature-gaps/BUGS-FOUND.fa.md, "اعتبارسنجی"):
 *
 *   - a Min/Max length failure on an input printed by PHP (no data-type) was
 *     shown and then ignored: the field turned green;
 *   - a negative number minimum was read as -1 (el.minLength) and a decimal
 *     maximum was cut to an integer;
 *   - textarea line breaks ("@n#") counted as three characters;
 *   - optional multiselects never checked minSelect;
 *   - the upload size default was 8 MB here, 20 MB in the error text and on
 *     the server, and 8 MB in the builder;
 *   - setting any minimum in the builder made the field required.
 *
 * The helpers are extracted from public/assets/js/core-efb.js and run as they
 * are, against a small fake element. Builder changes are checked in source.
 *
 * Run: node tests/test-validation-limits.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let pass = 0;
let fail = 0;
function t(label, ok) {
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label); }
}

const root = path.join(__dirname, '..');
const core = fs.readFileSync(path.join(root, 'public/assets/js/core-efb.js'), 'utf8');

function extractFunction(src, name) {
  const start = src.indexOf('function ' + name + '(');
  if (start === -1) throw new Error(name + '() not found');
  let i = src.indexOf('{', start);
  let depth = 0;
  for (; i < src.length; i++) {
    if (src[i] === '{') depth++;
    else if (src[i] === '}') { depth--; if (depth === 0) return src.slice(start, i + 1); }
  }
  throw new Error(name + '() not closed');
}

const names = [
  'efb_value_length_efb', 'efb_number_bounds_efb', 'efb_length_violation_efb',
  'efb_length_failure_blocks_efb', 'efb_max_file_mb_efb',
  'efb_multiselect_count_efb', 'efb_selection_within_limits_efb', 'valid_typed_value_efb',
];
const ctx = {};
vm.createContext(ctx);
vm.runInContext(names.map((n) => extractFunction(core, n)).join('\n'), ctx);

/* An input as the browser exposes it: attributes as text, minLength/maxLength
 * reflected the way HTML does (non-negative integer, else -1). */
function input(type, attrs, dataset) {
  const reflect = (v) => (/^\s*\d+/.test(v) ? parseInt(v, 10) : -1);
  return {
    type,
    value: '',
    dataset: dataset || {},
    hasAttribute: (n) => Object.prototype.hasOwnProperty.call(attrs, n),
    getAttribute: (n) => (Object.prototype.hasOwnProperty.call(attrs, n) ? String(attrs[n]) : null),
    get minLength() { return 'minlength' in attrs ? reflect(attrs.minlength) : -1; },
    get maxLength() { return 'maxlength' in attrs ? reflect(attrs.maxlength) : -1; },
  };
}

/* ---- number bounds ------------------------------------------------------ */
// The page as PHP now prints it for milen "-10", mlen "9.5".
let el = input('number', { min: '-10', minlength: '-10', max: '9.5', maxlength: '9', step: 'any' });
t('the HTML reflection really does read minlength "-10" as -1', el.minLength === -1);
let b = ctx.efb_number_bounds_efb(el);
t('negative min read as -10, not -1', b.min === -10);
t('decimal max read as 9.5, not 9', b.max === 9.5);
t('-3 is accepted (it is above -10)', ctx.efb_length_violation_efb(el, '-3') === null);
t('-11 is refused with the min message', JSON.stringify(ctx.efb_length_violation_efb(el, '-11')) === JSON.stringify({ key: 'mcplen', limit: -10 }));
t('9.4 is accepted', ctx.efb_length_violation_efb(el, '9.4') === null);
t('9.6 is refused with the max message', JSON.stringify(ctx.efb_length_violation_efb(el, '9.6')) === JSON.stringify({ key: 'mxcplen', limit: 9.5 }));
el.value = '-3';
t('valid_typed_value_efb (step check) accepts -3', ctx.valid_typed_value_efb('number', el) === true);
el.value = '-11';
t('valid_typed_value_efb (step check) refuses -11', ctx.valid_typed_value_efb('number', el) === false);

// The builder preview and pages cached before this fix: minlength/maxlength only.
el = input('number', { minlength: '-5', maxlength: '100' });
b = ctx.efb_number_bounds_efb(el);
t('legacy markup: minlength "-5" read as -5', b.min === -5 && b.max === 100);
el = input('number', {});
b = ctx.efb_number_bounds_efb(el);
t('no attributes: no bounds', b.min === null && b.max === null);
el = input('number', { maxlength: '0' });
t('max 0 means no maximum', ctx.efb_number_bounds_efb(el).max === null);
// Price field: hard-coded min="0" plus the owner's minlength.
el = input('number', { min: '0', minlength: '5' });
t('price field: the larger of min and minlength wins', ctx.efb_number_bounds_efb(el).min === 5);

/* ---- text / textarea lengths ------------------------------------------ */
t('"a@n#b" is 3 characters', ctx.efb_value_length_efb('a@n#b') === 3);
t('emoji counts as one character (as mb_strlen)', ctx.efb_value_length_efb('😀') === 1);
let ta = input('textarea', { minlength: '5' });
t('textarea "ab@n#cd" (5 chars) passes minlength 5', ctx.efb_length_violation_efb(ta, 'ab@n#cd') === null);
t('textarea "a@n#b" (3 chars) fails minlength 5', ctx.efb_length_violation_efb(ta, 'a@n#b').key === 'mmplen');
let tx = input('text', { minlength: '0', maxlength: '4' });
t('text over maxlength fails', ctx.efb_length_violation_efb(tx, 'hello').key === 'mmxplen');
tx = input('text', {});
t('text without minlength: one character is fine (no hidden minimum of 2)', ctx.efb_length_violation_efb(tx, 'a') === null);

/* ---- failure gate ------------------------------------------------------ */
t('PHP-rendered input (no data-type): a length failure blocks', ctx.efb_length_failure_blocks_efb(input('text', {}, {})) === true);
t('checklist quantity box (chlCheckBox): still ignored as before', ctx.efb_length_failure_blocks_efb(input('text', {}, { type: 'chlCheckBox' })) === false);
t('change handler uses the gate', core.includes('if(lenResult===0 && efb_length_failure_blocks_efb(el)){'));
t('old data-type condition is gone', !core.includes("el.dataset.hasOwnProperty('type') && el.dataset.type!=\"chlCheckBox\""));
t('validate_len_efb_v4 no longer reads el.minLength', !/validate_len_efb_v4[\s\S]{0,400}el\.minLength/.test(core));

/* ---- multiselect ------------------------------------------------------- */
t('"Red@efb!Blue@efb!" is 2 picks', ctx.efb_multiselect_count_efb('Red@efb!Blue@efb!') === 2);
t('1 pick under minSelect 2 refused', ctx.efb_selection_within_limits_efb({ minSelect: '2', maxSelect: '3' }, 1) === false);
t('4 picks over maxSelect 3 refused', ctx.efb_selection_within_limits_efb({ minSelect: '2', maxSelect: '3' }, 4) === false);
t('missing limits: any count', ctx.efb_selection_within_limits_efb({}, 7) === true);
t('empty limits: any count', ctx.efb_selection_within_limits_efb({ minSelect: '', maxSelect: '' }, 7) === true);
t('optional multiselect is checked in the step validation', core.includes("valj_efb[row].type == 'multiselect' && valj_efb[row].required != true"));

/* ---- file size default ------------------------------------------------- */
t('no max_fsize: 20 MB', ctx.efb_max_file_mb_efb({}) === 20);
t('empty max_fsize: 20 MB', ctx.efb_max_file_mb_efb({ max_fsize: '' }) === 20);
t('max_fsize "5": 5 MB', ctx.efb_max_file_mb_efb({ max_fsize: '5' }) === 5);
t('numeric max_fsize 12 (CF7 import / JSON) works', ctx.efb_max_file_mb_efb({ max_fsize: 12 }) === 12);
t('core-efb.js has no 8 MB default left', !core.includes('8*1024*1024'));
const adminCore = fs.readFileSync(path.join(root, 'includes/admin/assets/js/core-efb.js'), 'utf8');
t('admin core-efb.js has no 8 MB default left', !adminCore.includes('8*1024*1024') && !/max_fsize\.length>0 \? val_in\.max_fsize : 8/.test(adminCore));
const valJs = fs.readFileSync(path.join(root, 'includes/admin/assets/js/val-efb.js'), 'utf8');
t('builder shows 20 MB when nothing is saved', /fileSizeMaxEls[\s\S]{0,300}: 20;/.test(valJs));

/* ---- builder: a minimum no longer makes the field required ------------- */
const adminJs = fs.readFileSync(path.join(root, 'includes/admin/assets/js/admin-efb.js'), 'utf8');
t('admin-efb.js: the always-true required line is gone', !adminJs.includes('valj_efb[indx].type!="range" || valj_efb[indx].type!="date")valj_efb[indx].required=1'));
// Run the miLenEl branch's tail in isolation: after the fix nothing sets required.
const miStart = adminJs.indexOf('case "miLenEl":');
const miEnd = adminJs.indexOf('case "adminFormEmailEl":', miStart);
t('miLenEl branch never writes required=1', miStart !== -1 && miEnd !== -1 && !/required\s*=\s*1/.test(adminJs.slice(miStart, miEnd)));

console.log(`\nRESULTS: ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
