/**
 * Generates tests/fixtures/conditional-logic-scenarios.json — the shared
 * scenario catalog that BOTH engines are held to.
 *
 * Why a catalog rather than two hand-written suites: the browser runtime and the
 * PHP validator are mirrors of one another, so a test written twice tends to be
 * written wrong twice. Here every scenario carries the outcome a reader can work
 * out from the rule itself, and the two runners
 * (test-conditional-logic-scenarios.js / .php) assert that same outcome
 * independently. Agreement between the engines is then a by-product of both
 * being right, not the thing being measured — which matters, because the two
 * bugs that reached production (step id/position, and `is` on a checkbox) were
 * present identically in both and a parity check saw nothing.
 *
 * The base form gives its three steps ids that DISAGREE with their positions
 * (id_ "10" at position 1, "3" at position 2, "2" at position 3), so every
 * scenario keeps that trap under load.
 *
 * Run: node tests/generate-conditional-logic-scenarios.js
 */

'use strict';

const fs = require('fs');
const path = require('path');

// ── base form ────────────────────────────────────────────────────────────────

function field(id, type, step, extra) {
  return Object.assign({
    id_: id, dataId: id + '-id', type, name: id, step: String(step),
    required: '0', value: '', placeholder: id, amount: 0,
  }, extra || {});
}
function option(id, parent, value, step) {
  return { id_: id, dataId: id + '-id', parent, type: 'option', value, id_op: id, step: String(step) };
}
function step(id, position, name) {
  return { id_: String(id), type: 'step', dataId: String(id), id: String(id), name, step: String(position) };
}

const BASE = [
  { type: 'form', steps: '3', formName: 'Scenario matrix', EfbVersion: '2', logic: '1' },

  step('10', 1, 'One'),     // id 10 -> position 1
  step('3', 2, 'Two'),      // id  3 -> position 2
  step('2', 3, 'Three'),    // id  2 -> position 3

  field('t_text', 'text', 1),
  field('t_area', 'textarea', 1),
  field('t_email', 'email', 1),
  field('t_url', 'url', 1),
  field('t_num', 'number', 1),
  field('t_range', 'range', 1),
  field('t_date', 'date', 1),

  field('t_sel', 'select', 1),
  option('so_a', 't_sel', 'Alpha', 1),
  option('so_b', 't_sel', 'Beta', 1),
  option('so_c', 't_sel', 'Gamma', 1),

  field('t_radio', 'radio', 1),
  option('ro_a', 't_radio', 'RA', 1),
  option('ro_b', 't_radio', 'RB', 1),

  field('t_check', 'checkbox', 1),
  option('co_a', 't_check', 'CA', 1),
  option('co_b', 't_check', 'CB', 1),
  option('co_c', 't_check', 'CC', 1),

  field('t_multi', 'multiselect', 1),
  option('mo_a', 't_multi', 'MA', 1),
  option('mo_b', 't_multi', 'MB', 1),

  field('t_yesno', 'yesno', 1),
  field('t_file', 'dadfile', 1),
  field('t_pay', 'stripe', 1),

  field('t_out1', 'text', 1),
  field('t_out2', 'text', 1),
  field('t_calc', 'number', 1),

  field('s2_req', 'text', 2, { required: '1' }),
  field('s2_opt', 'text', 2),
  field('s3_req', 'text', 3, { required: '1' }),
  field('s3_opt', 'textarea', 3),
];

// ── builders ─────────────────────────────────────────────────────────────────

function cond(field_id, compare, value, extra) {
  return Object.assign({ type: 'condition', source: 'field', field_id, compare, value }, extra || {});
}
function group(operator, items, extra) {
  return Object.assign({ type: 'group', operator, items }, extra || {});
}
function rule(id, conditions, actions, extra) {
  return Object.assign({
    id, name: id, scope: 'field', enabled: true, priority: 10, stop_processing: false,
    conditions, actions,
  }, extra || {});
}
/** A rule whose only job is to report "my condition matched". */
function probe(id, conditions) {
  return rule(id, conditions, [{ type: 'hide_field', target: 't_out1' }]);
}

const ROWS = {
  text:   { id_: 't_text',  type: 'text',        value: 'Hello World' },
  area:   { id_: 't_area',  type: 'textarea',    value: 'a longer answer' },
  email:  { id_: 't_email', type: 'email',       value: 'user@example.com' },
  url:    { id_: 't_url',   type: 'url',         value: 'https://example.com/a' },
  num:    { id_: 't_num',   type: 'number',      value: '42' },
  range:  { id_: 't_range', type: 'range',       value: '7' },
  date:   { id_: 't_date',  type: 'date',        value: '2026-06-15' },
  sel:    { id_: 't_sel',   type: 'select',      value: 'Alpha' },
  radio:  { id_: 't_radio', type: 'radio',       id_ob: 'ro_a', value: 'RA' },
  checkA: { id_: 't_check', type: 'checkbox',    id_ob: 'co_a', value: 'CA' },
  checkB: { id_: 't_check', type: 'checkbox',    id_ob: 'co_b', value: 'CB' },
  multi:  { id_: 't_multi', type: 'multiselect', value: 'MA@efb!MB' },
  yes:    { id_: 't_yesno', type: 'yesno',       id_ob: 't_yesno_1', value: 'yes' },
  no:     { id_: 't_yesno', type: 'yesno',       id_ob: 't_yesno_2', value: 'no' },
  calc:   { id_: 't_calc',  type: 'number',      value: '5' },
};
function baseRows(extra) {
  return [ROWS.text, ROWS.area, ROWS.email, ROWS.url, ROWS.num, ROWS.range, ROWS.date,
    ROWS.sel, ROWS.radio, ROWS.checkA, ROWS.multi, ROWS.yes].concat(extra || []);
}
const ENV = { query: {}, user: { logged_in: false, roles: [] }, current_step: 1 };

const scenarios = [];
function add(family, name, rules, rows, expect, env, extraFields) {
  scenarios.push({
    family, name, rules,
    rows: rows || baseRows(),
    env: env || ENV,
    extraFields: extraFields || [],
    expect,
  });
}
/** Operator/group scenario: the only question is whether the rule fired. */
function probeCase(family, name, conditions, fires, rows, env) {
  add(family, name, [probe('p', conditions)], rows, { matched: fires ? ['p'] : [] }, env);
}

// ── FAMILY 1: operators per field category ───────────────────────────────────
// Which operators the builder offers per category is not uniform (choice fields
// get only is/is_not/is_empty/is_not_empty), so each case below is one the
// builder can actually produce.

[
  ['text is (hit)',              cond('t_text', 'is', 'Hello World'), true],
  ['text is (case-insensitive)', cond('t_text', 'is', 'hello world'), true],
  ['text is (miss)',             cond('t_text', 'is', 'Goodbye'), false],
  ['text is_not (hit)',          cond('t_text', 'is_not', 'Goodbye'), true],
  ['text is_not (miss)',         cond('t_text', 'is_not', 'Hello World'), false],
  ['text contains (hit)',        cond('t_text', 'contains', 'lo Wo'), true],
  ['text contains (miss)',       cond('t_text', 'contains', 'zzz'), false],
  ['text not_contains (hit)',    cond('t_text', 'not_contains', 'zzz'), true],
  ['text not_contains (miss)',   cond('t_text', 'not_contains', 'Hello'), false],
  ['text starts_with (hit)',     cond('t_text', 'starts_with', 'Hello'), true],
  ['text starts_with (miss)',    cond('t_text', 'starts_with', 'World'), false],
  ['text ends_with (hit)',       cond('t_text', 'ends_with', 'World'), true],
  ['text ends_with (miss)',      cond('t_text', 'ends_with', 'Hello'), false],
  ['text is_empty (miss)',       cond('t_text', 'is_empty', ''), false],
  ['text is_not_empty (hit)',    cond('t_text', 'is_not_empty', ''), true],
  ['textarea contains',          cond('t_area', 'contains', 'longer'), true],
  ['email is',                   cond('t_email', 'is', 'user@example.com'), true],
  ['email ends_with domain',     cond('t_email', 'ends_with', '@example.com'), true],
  ['email contains (miss)',      cond('t_email', 'contains', '@other.'), false],
  ['url starts_with https',      cond('t_url', 'starts_with', 'https://'), true],
].forEach(t => probeCase('operators/text', t[0], group('AND', [t[1]]), t[2]));

[
  ['number is',              cond('t_num', 'is', '42'), true],
  ['number is (miss)',       cond('t_num', 'is', '43'), false],
  ['number is_not',          cond('t_num', 'is_not', '43'), true],
  ['number gt (hit)',        cond('t_num', 'gt', '41'), true],
  ['number gt (boundary)',   cond('t_num', 'gt', '42'), false],
  ['number gte (boundary)',  cond('t_num', 'gte', '42'), true],
  ['number lt (hit)',        cond('t_num', 'lt', '43'), true],
  ['number lt (boundary)',   cond('t_num', 'lt', '42'), false],
  ['number lte (boundary)',  cond('t_num', 'lte', '42'), true],
  ['number between (inside)',        cond('t_num', 'between', '10,50'), true],
  ['number between (low boundary)',  cond('t_num', 'between', '42,50'), true],
  ['number between (high boundary)', cond('t_num', 'between', '10,42'), true],
  ['number between (outside)',       cond('t_num', 'between', '50,60'), false],
  ['number not_between (outside)',   cond('t_num', 'not_between', '50,60'), true],
  ['number not_between (inside)',    cond('t_num', 'not_between', '10,50'), false],
  ['number gt non-numeric operand',  cond('t_num', 'gt', 'abc'), false],
  ['number between one-sided range', cond('t_num', 'between', '5'), false],
  ['range gt',                cond('t_range', 'gt', '5'), true],
  ['range lt',                cond('t_range', 'lt', '5'), false],
  ['number is_not_empty',     cond('t_num', 'is_not_empty', ''), true],
].forEach(t => probeCase('operators/number', t[0], group('AND', [t[1]]), t[2]));

[
  ['date is',                     cond('t_date', 'is', '2026-06-15'), true],
  ['date is_not',                 cond('t_date', 'is_not', '2026-06-16'), true],
  ['date_before (hit)',           cond('t_date', 'date_before', '2026-12-31'), true],
  ['date_before (miss)',          cond('t_date', 'date_before', '2026-01-01'), false],
  ['date_before (same day)',      cond('t_date', 'date_before', '2026-06-15'), false],
  ['date_after (hit)',            cond('t_date', 'date_after', '2026-01-01'), true],
  ['date_after (miss)',           cond('t_date', 'date_after', '2026-12-31'), false],
  ['date_after (same day)',       cond('t_date', 'date_after', '2026-06-15'), false],
  ['date_between (inside)',       cond('t_date', 'date_between', '2026-01-01,2026-12-31'), true],
  ['date_between (boundaries)',   cond('t_date', 'date_between', '2026-06-15,2026-06-15'), true],
  ['date_between (outside)',      cond('t_date', 'date_between', '2027-01-01,2027-12-31'), false],
  ['date_before unparseable',     cond('t_date', 'date_before', 'not-a-date'), false],
  ['date_between one-sided',      cond('t_date', 'date_between', '2026-01-01'), false],
].forEach(t => probeCase('operators/date', t[0], group('AND', [t[1]]), t[2]));

// choice fields: the operand the builder writes is the option's id_
[
  ['select is (option id)',        cond('t_sel', 'is', 'so_a'), true, undefined],
  ['select is (display text)',     cond('t_sel', 'is', 'Alpha'), true, undefined],
  ['select is (other option)',     cond('t_sel', 'is', 'so_b'), false, undefined],
  ['select is_not (other option)', cond('t_sel', 'is_not', 'so_b'), true, undefined],
  ['select is_not (chosen)',       cond('t_sel', 'is_not', 'so_a'), false, undefined],
  ['select is_not_empty',          cond('t_sel', 'is_not_empty', ''), true, undefined],
  ['radio is (option id)',         cond('t_radio', 'is', 'ro_a'), true, undefined],
  ['radio is (other option)',      cond('t_radio', 'is', 'ro_b'), false, undefined],
  ['radio is_not (other option)',  cond('t_radio', 'is_not', 'ro_b'), true, undefined],
  ['yesno is yes',                 cond('t_yesno', 'is', 'yes'), true, undefined],
  ['yesno is no',                  cond('t_yesno', 'is', 'no'), false, undefined],
].forEach(t => probeCase('operators/choice', t[0], group('AND', [t[1]]), t[2]));

probeCase('operators/choice', 'yesno is no (answered no)', group('AND', [cond('t_yesno', 'is', 'no')]), true,
  baseRows().filter(r => r.id_ !== 't_yesno').concat([ROWS.no]));
probeCase('operators/choice', 'select is_empty (nothing chosen)', group('AND', [cond('t_sel', 'is_empty', '')]), true,
  baseRows().filter(r => r.id_ !== 't_sel'));
probeCase('operators/choice', 'radio is_empty (nothing chosen)', group('AND', [cond('t_radio', 'is_empty', '')]), true,
  baseRows().filter(r => r.id_ !== 't_radio'));

// multi-value fields — the pairing that silently never fired before
[
  ['checkbox is (ticked, option id)',    cond('t_check', 'is', 'co_a'), true, baseRows()],
  ['checkbox is (ticked, display text)', cond('t_check', 'is', 'CA'), true, baseRows()],
  ['checkbox is (not ticked)',           cond('t_check', 'is', 'co_b'), false, baseRows()],
  ['checkbox is_not (not ticked)',       cond('t_check', 'is_not', 'co_b'), true, baseRows()],
  ['checkbox is_not (ticked)',           cond('t_check', 'is_not', 'co_a'), false, baseRows()],
  ['checkbox is_not_empty',              cond('t_check', 'is_not_empty', ''), true, baseRows()],
  ['multiselect is (first, option id)',  cond('t_multi', 'is', 'mo_a'), true, baseRows()],
  ['multiselect is (second, id)',        cond('t_multi', 'is', 'mo_b'), true, baseRows()],
  ['multiselect is (display text)',      cond('t_multi', 'is', 'MA'), true, baseRows()],
  ['multiselect is (absent)',            cond('t_multi', 'is', 'Gamma'), false, baseRows()],
  ['multiselect is_not (absent)',        cond('t_multi', 'is_not', 'Gamma'), true, baseRows()],
  ['multiselect is_not (present)',       cond('t_multi', 'is_not', 'mo_a'), false, baseRows()],
].forEach(t => probeCase('operators/multi', t[0], group('AND', [t[1]]), t[2], t[3]));

// two boxes ticked: each must be findable, and is_not must respect both
const twoTicked = baseRows().concat([ROWS.checkB]);
probeCase('operators/multi', 'checkbox two ticked: first found',  group('AND', [cond('t_check', 'is', 'co_a')]), true, twoTicked);
probeCase('operators/multi', 'checkbox two ticked: second found', group('AND', [cond('t_check', 'is', 'co_b')]), true, twoTicked);
probeCase('operators/multi', 'checkbox two ticked: third absent', group('AND', [cond('t_check', 'is', 'co_c')]), false, twoTicked);
probeCase('operators/multi', 'checkbox two ticked: is_not third', group('AND', [cond('t_check', 'is_not', 'co_c')]), true, twoTicked);
probeCase('operators/multi', 'checkbox is_empty (none ticked)',
  group('AND', [cond('t_check', 'is_empty', '')]), true, baseRows().filter(r => r.id_ !== 't_check'));
probeCase('operators/multi', 'checkbox is_not_empty (none ticked)',
  group('AND', [cond('t_check', 'is_not_empty', '')]), false, baseRows().filter(r => r.id_ !== 't_check'));

// file fields: presence only
probeCase('operators/file', 'file is_empty (no upload)',      group('AND', [cond('t_file', 'is_empty', '')]), true, baseRows());
probeCase('operators/file', 'file is_not_empty (no upload)',  group('AND', [cond('t_file', 'is_not_empty', '')]), false, baseRows());
probeCase('operators/file', 'file is_not_empty (uploaded)',   group('AND', [cond('t_file', 'is_not_empty', '')]), true,
  baseRows().concat([{ id_: 't_file', type: 'dadfile', value: 'photo.png', url: 'https://x/photo.png' }]));

// empty values must never satisfy a numeric or date operator
const noNum = baseRows().filter(r => r.id_ !== 't_num');
[
  ['empty number gt', cond('t_num', 'gt', '0'), false],
  ['empty number gte', cond('t_num', 'gte', '0'), false],
  ['empty number lt', cond('t_num', 'lt', '100'), false],
  ['empty number lte', cond('t_num', 'lte', '100'), false],
  ['empty number between', cond('t_num', 'between', '0,100'), false],
  ['empty number not_between', cond('t_num', 'not_between', '0,100'), false],
  ['empty number is_empty', cond('t_num', 'is_empty', ''), true],
  ['empty number is_not_empty', cond('t_num', 'is_not_empty', ''), false],
].forEach(t => probeCase('operators/empty', t[0], group('AND', [t[1]]), t[2], noNum));

const noDate = baseRows().filter(r => r.id_ !== 't_date');
[
  ['empty date date_before', cond('t_date', 'date_before', '2026-12-31'), false],
  ['empty date date_after', cond('t_date', 'date_after', '2020-01-01'), false],
  ['empty date is_empty', cond('t_date', 'is_empty', ''), true],
].forEach(t => probeCase('operators/empty', t[0], group('AND', [t[1]]), t[2], noDate));

// a condition on a field that was never answered
[
  ['unanswered field is_empty', cond('t_out2', 'is_empty', ''), true],
  ['unanswered field is_not_empty', cond('t_out2', 'is_not_empty', ''), false],
  ['unanswered field is X', cond('t_out2', 'is', 'X'), false],
  ['unanswered field is_not X', cond('t_out2', 'is_not', 'X'), true],
  ['condition on an unknown field id', cond('nope', 'is', 'X'), false],
].forEach(t => probeCase('operators/empty', t[0], group('AND', [t[1]]), t[2]));

// ── FAMILY 2: boolean composition ────────────────────────────────────────────
const HIT = cond('t_text', 'is', 'Hello World');       // true
const MISS = cond('t_num', 'is', '999');               // false
const HIT2 = cond('t_num', 'is', '42');                // true
const MISS2 = cond('t_sel', 'is', 'so_c');             // false

[
  ['AND true+true',   group('AND', [HIT, HIT2]), true],
  ['AND true+false',  group('AND', [HIT, MISS]), false],
  ['AND false+true',  group('AND', [MISS, HIT]), false],
  ['AND false+false', group('AND', [MISS, MISS2]), false],
  ['OR true+true',    group('OR', [HIT, HIT2]), true],
  ['OR true+false',   group('OR', [HIT, MISS]), true],
  ['OR false+true',   group('OR', [MISS, HIT]), true],
  ['OR false+false',  group('OR', [MISS, MISS2]), false],
  ['single true',     group('AND', [HIT]), true],
  ['single false',    group('AND', [MISS]), false],
  ['empty group',     group('AND', []), false],
  ['AND of three, all true',  group('AND', [HIT, HIT2, cond('t_sel', 'is', 'so_a')]), true],
  ['AND of three, last false', group('AND', [HIT, HIT2, MISS2]), false],
  ['OR of three, only last true', group('OR', [MISS, MISS2, HIT]), true],
].forEach(t => probeCase('logic/group', t[0], t[1], t[2]));

// per-item connector overrides the group operator
[
  ['per-item OR rescues a false first',  group('AND', [MISS, Object.assign({}, HIT2, { connector: 'OR' })]), true],
  ['per-item AND tightens an OR group',  group('OR', [HIT, Object.assign({}, MISS, { connector: 'AND' })]), false],
  ['per-item OR then AND',               group('AND', [MISS, Object.assign({}, HIT, { connector: 'OR' }), Object.assign({}, HIT2, { connector: 'AND' })]), true],
  ['per-item OR then failing AND',       group('AND', [MISS, Object.assign({}, HIT, { connector: 'OR' }), Object.assign({}, MISS2, { connector: 'AND' })]), false],
].forEach(t => probeCase('logic/connector', t[0], t[1], t[2]));

// negate: NOT / NAND / NOR
[
  ['NOT of true',    group('AND', [HIT], { negate: true }), false],
  ['NOT of false',   group('AND', [MISS], { negate: true }), true],
  ['NAND all true',  group('AND', [HIT, HIT2], { negate: true }), false],
  ['NAND one false', group('AND', [HIT, MISS], { negate: true }), true],
  ['NOR none true',  group('OR', [MISS, MISS2], { negate: true }), true],
  ['NOR one true',   group('OR', [HIT, MISS], { negate: true }), false],
].forEach(t => probeCase('logic/negate', t[0], t[1], t[2]));

// nested groups
[
  ['nested OR inside AND (true)',
    group('AND', [HIT, group('OR', [MISS, HIT2], { connector: 'AND' })]), true],
  ['nested OR inside AND (false)',
    group('AND', [HIT, group('OR', [MISS, MISS2], { connector: 'AND' })]), false],
  ['nested AND inside OR (true via nest)',
    group('OR', [MISS, group('AND', [HIT, HIT2], { connector: 'OR' })]), true],
  ['nested AND inside OR (false)',
    group('OR', [MISS, group('AND', [HIT, MISS2], { connector: 'OR' })]), false],
  ['negated nest inside AND',
    group('AND', [HIT, group('OR', [MISS], { connector: 'AND', negate: true })]), true],
  ['two levels of nesting',
    group('AND', [HIT, group('AND', [HIT2, group('OR', [MISS, MISS2], { connector: 'AND' })], { connector: 'AND' })]), false],
  ['two levels, inner rescued by OR',
    group('AND', [HIT, group('AND', [HIT2, group('OR', [MISS, HIT], { connector: 'AND' })], { connector: 'AND' })]), true],
].forEach(t => probeCase('logic/nested', t[0], t[1], t[2]));

// mixed field types in one condition set
probeCase('logic/mixed', 'text AND number AND select',
  group('AND', [HIT, HIT2, cond('t_sel', 'is', 'so_a')]), true);
probeCase('logic/mixed', 'checkbox AND yesno',
  group('AND', [cond('t_check', 'is', 'co_a'), cond('t_yesno', 'is', 'yes')]), true);
probeCase('logic/mixed', 'checkbox AND yesno (yesno wrong)',
  group('AND', [cond('t_check', 'is', 'co_a'), cond('t_yesno', 'is', 'no')]), false);
probeCase('logic/mixed', 'multiselect OR radio',
  group('OR', [cond('t_multi', 'is', 'Gamma'), cond('t_radio', 'is', 'ro_a')]), true);
probeCase('logic/mixed', 'date range AND number range',
  group('AND', [cond('t_date', 'date_between', '2026-01-01,2026-12-31'), cond('t_num', 'between', '40,50')]), true);

// ── FAMILY 3: actions ────────────────────────────────────────────────────────
const WHEN = group('AND', [HIT]);
const NEVER = group('AND', [MISS]);

add('actions/visibility', 'hide_field hides and ignores its target',
  [rule('r', WHEN, [{ type: 'hide_field', target: 't_out1' }])], baseRows(),
  { matched: ['r'], hidden: ['t_out1'], ignored: ['t_out1'] });

add('actions/visibility', 'show_field target starts hidden, rule reveals it',
  [rule('r', WHEN, [{ type: 'show_field', target: 't_out1' }])], baseRows(),
  { matched: ['r'], hidden: [], shown: ['t_out1'], ignored: [] });

add('actions/visibility', 'show_field target stays hidden when the rule misses',
  [rule('r', NEVER, [{ type: 'show_field', target: 't_out1' }])], baseRows(),
  { matched: [], hidden: ['t_out1'], shown: [], ignored: ['t_out1'] });

add('actions/visibility', 'hide_field on two targets',
  [rule('r', WHEN, [{ type: 'hide_field', target: 't_out1' }, { type: 'hide_field', target: 't_out2' }])],
  baseRows(), { matched: ['r'], hidden: ['t_out1', 't_out2'], ignored: ['t_out1', 't_out2'] });

add('actions/requirement', 'set_required marks the field',
  [rule('r', WHEN, [{ type: 'set_required', target: 't_out1' }])], baseRows(),
  { matched: ['r'], required: ['t_out1'] });

add('actions/requirement', 'set_optional releases a structurally required field',
  [rule('r', WHEN, [{ type: 'set_optional', target: 's2_req' }])], baseRows(),
  { matched: ['r'], optional: ['s2_req'] });

add('actions/requirement', 'set_required does not fire when the rule misses',
  [rule('r', NEVER, [{ type: 'set_required', target: 't_out1' }])], baseRows(),
  { matched: [], required: [] });

add('actions/availability', 'disable_field disables and ignores its target',
  [rule('r', WHEN, [{ type: 'disable_field', target: 't_out1' }])], baseRows(),
  { matched: ['r'], disabled: ['t_out1'], ignored: ['t_out1'] });

add('actions/availability', 'enable_field on an otherwise untouched field',
  [rule('r', WHEN, [{ type: 'enable_field', target: 't_out1' }])], baseRows(),
  { matched: ['r'], disabled: [], enabled: ['t_out1'], ignored: [] });

add('actions/value', 'set_value writes a literal',
  [rule('r', WHEN, [{ type: 'set_value', target: 't_out1', value: 'WRITTEN' }])], baseRows(),
  { matched: ['r'], setValues: { t_out1: 'WRITTEN' } });

add('actions/value', 'set_value on a select resolves the option id to its text',
  [rule('r', WHEN, [{ type: 'set_value', target: 't_sel', value: 'so_b' }])], baseRows(),
  { matched: ['r'], setValues: { t_sel: 'Beta' } });

add('actions/value', 'copy_value copies another field',
  [rule('r', WHEN, [{ type: 'copy_value', target: 't_out1', value: 't_text' }])], baseRows(),
  { matched: ['r'], setValues: { t_out1: 'Hello World' } });

add('actions/value', 'copy_value from an unknown field writes nothing',
  [rule('r', WHEN, [{ type: 'copy_value', target: 't_out1', value: 'nope' }])], baseRows(),
  { matched: ['r'], setValues: {} });

add('actions/value', 'clear_value empties an answered field',
  [rule('r', WHEN, [{ type: 'clear_value', target: 't_area' }])], baseRows(),
  { matched: ['r'], cleared: ['t_area'] });

add('actions/value', 'clear_value on an already empty field is a no-op',
  [rule('r', WHEN, [{ type: 'clear_value', target: 't_out1' }])], baseRows(),
  { matched: ['r'], cleared: [], setValues: {} });

[
  ['calculate multiplies', '{t_num} * 2', '84'],
  ['calculate adds two fields', '{t_num} + {t_range}', '49'],
  ['calculate subtracts', '{t_num} - {t_range}', '35'],
  ['calculate divides', '{t_num} / {t_range}', '6'],
  ['calculate respects precedence', '{t_num} + {t_range} * 2', '56'],
  ['calculate honours parentheses', '({t_num} + {t_range}) * 2', '98'],
  ['calculate with a literal only', '(1 + 2) * 3', '9'],
  ['calculate unary minus', '-{t_range} + 10', '3'],
].forEach(t => add('actions/calculate', t[0],
  [rule('r', WHEN, [{ type: 'calculate', target: 't_calc', value: t[1] }])], baseRows(),
  { matched: ['r'], setValues: { t_calc: t[2] }, stabilized: true }));

add('actions/calculate', 'calculate rounds to the requested decimals',
  [rule('r', WHEN, [{ type: 'calculate', target: 't_calc', value: '{t_num} / 3', decimals: 2 }])], baseRows(),
  { matched: ['r'], setValues: { t_calc: '14.00' } });

[
  ['calculate refuses division by zero', '{t_num} / 0'],
  ['calculate refuses a non-numeric operand', '{t_text} + 1'],
  ['calculate refuses an unknown field', '{t_num} + {nope}'],
  ['calculate refuses an unbalanced expression', '({t_num} + 1'],
  ['calculate refuses a dangling operator', '{t_num} +'],
  ['calculate refuses an empty formula', ''],
].forEach(t => add('actions/calculate', t[0],
  [rule('r', WHEN, [{ type: 'calculate', target: 't_calc', value: t[1] }])], baseRows(),
  { matched: ['r'], setValues: {} }));

add('actions/ui', 'set_placeholder is recorded',
  [rule('r', WHEN, [{ type: 'set_placeholder', target: 't_out1', value: 'PH' }])], baseRows(),
  { matched: ['r'], ui: ['t_out1:placeholder=PH'] });
add('actions/ui', 'set_label is recorded',
  [rule('r', WHEN, [{ type: 'set_label', target: 't_out1', value: 'LB' }])], baseRows(),
  { matched: ['r'], ui: ['t_out1:label=LB'] });
add('actions/ui', 'set_help is recorded',
  [rule('r', WHEN, [{ type: 'set_help', target: 't_out1', value: 'HP' }])], baseRows(),
  { matched: ['r'], ui: ['t_out1:help=HP'] });
add('actions/ui', 'all three UI actions on one target',
  [rule('r', WHEN, [
    { type: 'set_placeholder', target: 't_out1', value: 'PH' },
    { type: 'set_label', target: 't_out1', value: 'LB' },
    { type: 'set_help', target: 't_out1', value: 'HP' }])], baseRows(),
  { matched: ['r'], ui: ['t_out1:help=HP', 't_out1:label=LB', 't_out1:placeholder=PH'] });
add('actions/ui', 'show_message is recorded against its field',
  [rule('r', WHEN, [{ type: 'show_message', target: 't_out1', value: 'Note' }])], baseRows(),
  { matched: ['r'], messages: ['t_out1=Note'] });
add('actions/ui', 'focus_field is recorded',
  [rule('r', WHEN, [{ type: 'focus_field', target: 't_out1' }])], baseRows(),
  { matched: ['r'], focus: ['t_out1'] });
add('actions/ui', 'scroll_to_field is recorded',
  [rule('r', WHEN, [{ type: 'scroll_to_field', target: 't_out1' }])], baseRows(),
  { matched: ['r'], scroll: ['t_out1'] });
add('actions/ui', 'no UI change while the rule misses',
  [rule('r', NEVER, [{ type: 'set_label', target: 't_out1', value: 'LB' }])], baseRows(),
  { matched: [], ui: [] });

add('actions/veto', 'block_submit vetoes the submission',
  [rule('r', WHEN, [{ type: 'block_submit', value: 'Nope' }])], baseRows(),
  { matched: ['r'], blocked: true, blockMessages: ['Nope'] });
add('actions/veto', 'block_submit lifts when the rule misses',
  [rule('r', NEVER, [{ type: 'block_submit', value: 'Nope' }])], baseRows(),
  { matched: [], blocked: false, blockMessages: [] });
add('actions/veto', 'end_form vetoes and carries its message',
  [rule('r', WHEN, [{ type: 'end_form', value: 'Closed' }])], baseRows(),
  { matched: ['r'], blocked: true, endForm: 'Closed' });
add('actions/veto', 'end_form and block_submit together',
  [rule('r', WHEN, [{ type: 'block_submit', value: 'B' }, { type: 'end_form', value: 'E' }])], baseRows(),
  { matched: ['r'], blocked: true, endForm: 'E', blockMessages: ['B'] });

add('actions/combo', 'reveal + require + describe in one rule',
  [rule('r', WHEN, [
    { type: 'show_field', target: 't_out1' },
    { type: 'set_required', target: 't_out1' },
    { type: 'set_label', target: 't_out1', value: 'Now needed' }])], baseRows(),
  { matched: ['r'], shown: ['t_out1'], hidden: [], required: ['t_out1'], ui: ['t_out1:label=Now needed'] });

add('actions/combo', 'hide + clear in one rule',
  [rule('r', WHEN, [{ type: 'hide_field', target: 't_area' }, { type: 'clear_value', target: 't_area' }])],
  baseRows(), { matched: ['r'], hidden: ['t_area'], ignored: ['t_area'], cleared: ['t_area'] });

add('actions/combo', 'calculate then require the result',
  [rule('r', WHEN, [
    { type: 'calculate', target: 't_calc', value: '{t_num} * 2' },
    { type: 'set_required', target: 't_calc' }])], baseRows(),
  { matched: ['r'], setValues: { t_calc: '84' }, required: ['t_calc'] });

// ── FAMILY 4: rules interacting ──────────────────────────────────────────────
add('rules/priority', 'lower priority runs first, higher wins the same target',
  [rule('late', WHEN, [{ type: 'set_optional', target: 't_out1' }], { priority: 50 }),
   rule('early', WHEN, [{ type: 'set_required', target: 't_out1' }], { priority: 5 })],
  baseRows(), { matched: ['early', 'late'], required: [], optional: ['t_out1'] });

add('rules/priority', 'reversing the priorities reverses the winner',
  [rule('late', WHEN, [{ type: 'set_optional', target: 't_out1' }], { priority: 5 }),
   rule('early', WHEN, [{ type: 'set_required', target: 't_out1' }], { priority: 50 })],
  baseRows(), { matched: ['late', 'early'], required: ['t_out1'], optional: [] });

add('rules/priority', 'equal priorities fall back to authoring order',
  [rule('first', WHEN, [{ type: 'hide_field', target: 't_out1' }], { priority: 10 }),
   rule('second', WHEN, [{ type: 'show_field', target: 't_out1' }], { priority: 10 })],
  baseRows(), { matched: ['first', 'second'], shown: ['t_out1'], hidden: [] });

add('rules/enabled', 'a disabled rule never runs',
  [rule('off', WHEN, [{ type: 'hide_field', target: 't_out1' }], { enabled: false })],
  baseRows(), { matched: [], hidden: [] });

add('rules/enabled', 'a disabled rule does not even pre-hide its show_field target',
  [rule('off', WHEN, [{ type: 'show_field', target: 't_out1' }], { enabled: false })],
  baseRows(), { matched: [], hidden: [], ignored: [] });

add('rules/enabled', 'one disabled rule beside one live rule',
  [rule('off', WHEN, [{ type: 'hide_field', target: 't_out1' }], { enabled: false }),
   rule('on', WHEN, [{ type: 'hide_field', target: 't_out2' }])],
  baseRows(), { matched: ['on'], hidden: ['t_out2'] });

add('rules/stop', 'stop_processing freezes the target it acted on',
  [rule('first', WHEN, [{ type: 'hide_field', target: 't_out1' }], { priority: 1, stop_processing: true }),
   rule('second', WHEN, [{ type: 'show_field', target: 't_out1' }], { priority: 2 })],
  baseRows(), { matched: ['first'], hidden: ['t_out1'] });

add('rules/stop', 'stop_processing leaves other targets alone',
  [rule('first', WHEN, [{ type: 'hide_field', target: 't_out1' }], { priority: 1, stop_processing: true }),
   rule('second', WHEN, [{ type: 'hide_field', target: 't_out2' }], { priority: 2 })],
  baseRows(), { matched: ['first', 'second'], hidden: ['t_out1', 't_out2'] });

add('rules/stop', 'a later rule with one frozen and one free target still runs',
  [rule('first', WHEN, [{ type: 'hide_field', target: 't_out1' }], { priority: 1, stop_processing: true }),
   rule('second', WHEN, [{ type: 'show_field', target: 't_out1' }, { type: 'hide_field', target: 't_out2' }], { priority: 2 })],
  baseRows(), { matched: ['first', 'second'], hidden: ['t_out2'], shown: ['t_out1'] });

add('rules/stop', 'a step target and a field target of the same name are separate',
  [rule('first', WHEN, [{ type: 'hide_step', target: '2' }], { priority: 1, stop_processing: true }),
   rule('second', WHEN, [{ type: 'hide_field', target: 't_out1' }], { priority: 2 })],
  baseRows(), { matched: ['first', 'second'], hiddenSteps: ['2'], hidden: ['t_out1'] });

add('rules/stop', 'a non-matching stop rule freezes nothing',
  [rule('first', NEVER, [{ type: 'hide_field', target: 't_out1' }], { priority: 1, stop_processing: true }),
   rule('second', WHEN, [{ type: 'hide_field', target: 't_out1' }], { priority: 2 })],
  baseRows(), { matched: ['second'], hidden: ['t_out1'] });

add('rules/overlap', 'three rules on one target, last write wins',
  [rule('a', WHEN, [{ type: 'set_required', target: 't_out1' }], { priority: 1 }),
   rule('b', WHEN, [{ type: 'set_optional', target: 't_out1' }], { priority: 2 }),
   rule('c', WHEN, [{ type: 'set_required', target: 't_out1' }], { priority: 3 })],
  baseRows(), { matched: ['a', 'b', 'c'], required: ['t_out1'], optional: [] });

add('rules/overlap', 'rules on different targets all apply',
  [rule('a', WHEN, [{ type: 'hide_field', target: 't_out1' }]),
   rule('b', WHEN, [{ type: 'set_required', target: 't_out2' }]),
   rule('c', WHEN, [{ type: 'disable_field', target: 't_area' }])],
  baseRows(), { matched: ['a', 'b', 'c'], hidden: ['t_out1'], required: ['t_out2'], disabled: ['t_area'] });

add('rules/overlap', 'two rules, only one condition holds',
  [rule('a', WHEN, [{ type: 'hide_field', target: 't_out1' }]),
   rule('b', NEVER, [{ type: 'hide_field', target: 't_out2' }])],
  baseRows(), { matched: ['a'], hidden: ['t_out1'] });

add('rules/overlap', 'many rules, none match',
  [rule('a', NEVER, [{ type: 'hide_field', target: 't_out1' }]),
   rule('b', NEVER, [{ type: 'set_required', target: 't_out2' }]),
   rule('c', NEVER, [{ type: 'block_submit', value: 'x' }])],
  baseRows(), { matched: [], hidden: [], required: [], blocked: false });

add('rules/malformed', 'a rule with no actions is skipped',
  [rule('a', WHEN, []), rule('b', WHEN, [{ type: 'hide_field', target: 't_out1' }])],
  baseRows(), { matched: ['b'], hidden: ['t_out1'] });

add('rules/malformed', 'a rule with no conditions is skipped',
  [rule('a', group('AND', []), [{ type: 'hide_field', target: 't_out1' }]),
   rule('b', WHEN, [{ type: 'hide_field', target: 't_out2' }])],
  baseRows(), { matched: ['b'], hidden: ['t_out2'] });

// ── FAMILY 5: steps ──────────────────────────────────────────────────────────
// id_ "10" -> position 1, id_ "3" -> position 2, id_ "2" -> position 3
[
  ['hide step id 2 (position 3)', '2', ['s3_req', 's3_opt']],
  ['hide step id 3 (position 2)', '3', ['s2_req', 's2_opt']],
].forEach(t => add('steps/namespace', t[0],
  [rule('r', WHEN, [{ type: 'hide_step', target: t[1] }])], baseRows(),
  { matched: ['r'], hiddenSteps: [t[1]], ignored: t[2] }));

add('steps/namespace', 'hide step id 10 (position 1) ignores the first step only',
  [rule('r', WHEN, [{ type: 'hide_step', target: '10' }])], baseRows(),
  { matched: ['r'], hiddenSteps: ['10'],
    ignored: ['t_text', 't_area', 't_email', 't_url', 't_num', 't_range', 't_date', 't_sel',
      't_radio', 't_check', 't_multi', 't_yesno', 't_file', 't_pay', 't_out1', 't_out2', 't_calc'] });

add('steps/namespace', 'hiding both extra steps ignores exactly their fields',
  [rule('r', WHEN, [{ type: 'hide_step', target: '3' }, { type: 'hide_step', target: '2' }])], baseRows(),
  { matched: ['r'], hiddenSteps: ['3', '2'], ignored: ['s2_req', 's2_opt', 's3_req', 's3_opt'] });

add('steps/namespace', 'an unresolvable step target ignores nothing',
  [rule('r', WHEN, [{ type: 'hide_step', target: '404' }])], baseRows(),
  { matched: ['r'], hiddenSteps: ['404'], ignored: [] });

add('steps/visibility', 'show_step target starts hidden and the rule opens it',
  [rule('r', WHEN, [{ type: 'show_step', target: '3' }])], baseRows(),
  { matched: ['r'], hiddenSteps: [], shownSteps: ['3'], ignored: [] });

add('steps/visibility', 'show_step target stays shut when the rule misses',
  [rule('r', NEVER, [{ type: 'show_step', target: '3' }])], baseRows(),
  { matched: [], hiddenSteps: ['3'], ignored: ['s2_req', 's2_opt'] });

add('steps/visibility', 'one path opens a step and closes the other',
  [rule('r', WHEN, [{ type: 'show_step', target: '3' }, { type: 'hide_step', target: '2' }])], baseRows(),
  { matched: ['r'], hiddenSteps: ['2'], shownSteps: ['3'], ignored: ['s3_req', 's3_opt'] });

add('steps/visibility', 'the required field on the opened step survives',
  [rule('r', WHEN, [
    { type: 'show_step', target: '3' },
    { type: 'hide_step', target: '2' },
    { type: 'set_required', target: 's2_req' }])], baseRows(),
  { matched: ['r'], hiddenSteps: ['2'], required: ['s2_req'], ignored: ['s3_req', 's3_opt'] });

add('steps/visibility', 'a field required on a hidden step is ignored, not enforced',
  [rule('r', WHEN, [{ type: 'hide_step', target: '3' }, { type: 'set_required', target: 's2_req' }])],
  baseRows(), { matched: ['r'], hiddenSteps: ['3'], required: ['s2_req'], ignored: ['s2_req', 's2_opt'] });

add('steps/jump', 'jump_to_step is recorded',
  [rule('r', WHEN, [{ type: 'jump_to_step', target: '2' }])], baseRows(),
  { matched: ['r'], jumps: ['2'] });

add('steps/jump', 'jump_to_step does not hide anything by itself',
  [rule('r', WHEN, [{ type: 'jump_to_step', target: '3' }])], baseRows(),
  { matched: ['r'], jumps: ['3'], hiddenSteps: [], ignored: [] });

add('steps/jump', 'no jump while the rule misses',
  [rule('r', NEVER, [{ type: 'jump_to_step', target: '2' }])], baseRows(),
  { matched: [], jumps: [] });

// a three-way branch driven by one select — the shape of the reported form
const BRANCH = [
  rule('b_two', group('AND', [cond('t_sel', 'is', 'so_b')]),
    [{ type: 'show_step', target: '3' }, { type: 'hide_step', target: '2' }, { type: 'set_required', target: 's2_req' }], { priority: 10 }),
  rule('b_three', group('AND', [cond('t_sel', 'is', 'so_c')]),
    [{ type: 'show_step', target: '2' }, { type: 'hide_step', target: '3' }], { priority: 11 }),
  rule('b_none', group('AND', [cond('t_sel', 'is', 'so_a')]),
    [{ type: 'hide_step', target: '3' }, { type: 'hide_step', target: '2' }], { priority: 9 }),
];
function selRows(text) {
  return baseRows().filter(r => r.id_ !== 't_sel').concat([{ id_: 't_sel', type: 'select', value: text }]);
}
add('steps/branch', 'branch A closes both extra steps', BRANCH, selRows('Alpha'),
  { matched: ['b_none'], hiddenSteps: ['3', '2'], required: [], ignored: ['s2_req', 's2_opt', 's3_req', 's3_opt'] });
add('steps/branch', 'branch B opens step 2 and requires its field', BRANCH, selRows('Beta'),
  { matched: ['b_two'], hiddenSteps: ['2'], shownSteps: ['3'], required: ['s2_req'], ignored: ['s3_req', 's3_opt'] });
add('steps/branch', 'branch C opens step 3 only', BRANCH, selRows('Gamma'),
  { matched: ['b_three'], hiddenSteps: ['3'], shownSteps: ['2'], required: [], ignored: ['s2_req', 's2_opt'] });
add('steps/branch', 'no branch chosen leaves both extra steps shut', BRANCH,
  baseRows().filter(r => r.id_ !== 't_sel'),
  { matched: [], hiddenSteps: ['3', '2'], ignored: ['s2_req', 's2_opt', 's3_req', 's3_opt'] });

// ── FAMILY 6: value chains ───────────────────────────────────────────────────
add('chain/value', 'a set_value feeds a later condition',
  [rule('write', group('AND', [HIT]), [{ type: 'set_value', target: 't_out1', value: 'TOKEN' }], { priority: 1 }),
   rule('read', group('AND', [cond('t_out1', 'is', 'TOKEN')]), [{ type: 'set_required', target: 't_out2' }], { priority: 2 })],
  baseRows(), { matched: ['write', 'read'], setValues: { t_out1: 'TOKEN' }, required: ['t_out2'], stabilized: true });

add('chain/value', 'the same pair in the wrong priority order still settles',
  [rule('read', group('AND', [cond('t_out1', 'is', 'TOKEN')]), [{ type: 'set_required', target: 't_out2' }], { priority: 1 }),
   rule('write', group('AND', [HIT]), [{ type: 'set_value', target: 't_out1', value: 'TOKEN' }], { priority: 2 })],
  baseRows(), { matched: ['read', 'write'], setValues: { t_out1: 'TOKEN' }, required: ['t_out2'], stabilized: true });

add('chain/value', 'a copy chain of three fields resolves',
  [rule('c1', WHEN, [{ type: 'copy_value', target: 't_out1', value: 't_text' }], { priority: 1 }),
   rule('c2', WHEN, [{ type: 'copy_value', target: 't_out2', value: 't_out1' }], { priority: 2 })],
  baseRows(), { matched: ['c1', 'c2'], setValues: { t_out1: 'Hello World', t_out2: 'Hello World' }, stabilized: true });

add('chain/value', 'a calculation feeds a threshold rule',
  [rule('calc', WHEN, [{ type: 'calculate', target: 't_calc', value: '{t_num} * 2' }], { priority: 1 }),
   rule('gate', group('AND', [cond('t_calc', 'gt', '80')]), [{ type: 'block_submit', value: 'Too big' }], { priority: 2 })],
  baseRows(), { matched: ['calc', 'gate'], setValues: { t_calc: '84' }, blocked: true, stabilized: true });

add('chain/value', 'a calculation below the threshold leaves submission open',
  [rule('calc', WHEN, [{ type: 'calculate', target: 't_calc', value: '{t_range} * 2' }], { priority: 1 }),
   rule('gate', group('AND', [cond('t_calc', 'gt', '80')]), [{ type: 'block_submit', value: 'Too big' }], { priority: 2 })],
  baseRows(), { matched: ['calc'], setValues: { t_calc: '14' }, blocked: false, stabilized: true });

add('chain/value', 'set then clear in priority order leaves the field empty',
  [rule('set', WHEN, [{ type: 'set_value', target: 't_out1', value: 'X' }], { priority: 1 }),
   rule('clr', WHEN, [{ type: 'clear_value', target: 't_out1' }], { priority: 2 })],
  baseRows(), { matched: ['set', 'clr'], setValues: {}, cleared: [], stabilized: true });

add('chain/value', 'clear then set leaves the written value',
  [rule('clr', WHEN, [{ type: 'clear_value', target: 't_area' }], { priority: 1 }),
   rule('set', WHEN, [{ type: 'set_value', target: 't_area', value: 'X' }], { priority: 2 })],
  baseRows(), { matched: ['clr', 'set'], setValues: { t_area: 'X' }, stabilized: true });

[
  ['a self-referential multiply never settles', 't_calc', '{t_calc} * 2'],
  ['a self-referential increment never settles', 't_calc', '{t_calc} + 1'],
  ['a running sum never settles', 't_calc', '{t_calc} + {t_num}'],
].forEach(t => add('chain/divergence', t[0],
  [rule('r', WHEN, [{ type: 'calculate', target: t[1], value: t[2] }])],
  baseRows([ROWS.calc]), { matched: ['r'], stabilized: false, setValues: {}, cleared: [] }));

/* A relay: the empty field triggers rule a, whose write triggers rule b. The
 * reported state is the SETTLED one — once it has run, neither condition holds
 * any more, so matched_rules is empty while the value the pair produced stays.
 * Value actions deliberately outlive the condition that caused them. */
add('chain/divergence', 'a settled relay keeps its value and reports no live match',
  [rule('a', group('AND', [cond('t_out1', 'is', '')]), [{ type: 'set_value', target: 't_out1', value: 'A' }], { priority: 1 }),
   rule('b', group('AND', [cond('t_out1', 'is', 'A')]), [{ type: 'set_value', target: 't_out1', value: 'B' }], { priority: 2 })],
  baseRows(), { matched: [], setValues: { t_out1: 'B' }, stabilized: true });

add('chain/divergence', 'a hidden field still feeds a later condition',
  [rule('hide', WHEN, [{ type: 'hide_field', target: 't_num' }], { priority: 1 }),
   rule('read', group('AND', [cond('t_num', 'is', '42')]), [{ type: 'set_required', target: 't_out1' }], { priority: 2 })],
  baseRows(), { matched: ['hide', 'read'], hidden: ['t_num'], required: ['t_out1'] });

// ── FAMILY 7: non-field sources ──────────────────────────────────────────────
function env(query, loggedIn, roles, currentStep) {
  return { query: query || {}, user: { logged_in: !!loggedIn, roles: roles || [] },
    current_step: currentStep === undefined ? 1 : currentStep };
}
[
  ['query param is (hit)',  cond('plan', 'is', 'pro', { source: 'query_param', param: 'plan' }), true, env({ plan: 'pro' })],
  ['query param is (miss)', cond('plan', 'is', 'pro', { source: 'query_param', param: 'plan' }), false, env({ plan: 'free' })],
  ['query param absent is_empty', cond('plan', 'is_empty', '', { source: 'query_param', param: 'plan' }), true, env({})],
  ['query param absent is (miss)', cond('plan', 'is', 'pro', { source: 'query_param', param: 'plan' }), false, env({})],
  ['query param contains', cond('ref', 'contains', 'newsletter', { source: 'query_param', param: 'ref' }), true, env({ ref: 'aug-newsletter-2' })],
  ['query param is_not_empty', cond('ref', 'is_not_empty', '', { source: 'query_param', param: 'ref' }), true, env({ ref: 'x' })],
  ['query param numeric gt', cond('n', 'gt', '5', { source: 'query_param', param: 'n' }), true, env({ n: '9' })],
  ['logged_in is yes (guest)', cond('logged_in', 'is', 'yes', { source: 'user' }), false, env({}, false)],
  ['logged_in is yes (member)', cond('logged_in', 'is', 'yes', { source: 'user' }), true, env({}, true)],
  ['logged_in is no (guest)', cond('logged_in', 'is', 'no', { source: 'user' }), true, env({}, false)],
  ['role is (hit)', cond('role', 'is', 'administrator', { source: 'user' }), true, env({}, true, ['administrator'])],
  ['role is (miss)', cond('role', 'is', 'editor', { source: 'user' }), false, env({}, true, ['administrator'])],
  ['role is_not (hit)', cond('role', 'is_not', 'editor', { source: 'user' }), true, env({}, true, ['administrator'])],
  ['role among several', cond('role', 'is', 'editor', { source: 'user' }), true, env({}, true, ['subscriber', 'editor'])],
  ['role is_empty (guest)', cond('role', 'is_empty', '', { source: 'user' }), true, env({}, false, [])],
  ['role is_not_empty (member)', cond('role', 'is_not_empty', '', { source: 'user' }), true, env({}, true, ['subscriber'])],
  ['unknown user key never matches', cond('whatever', 'is', 'x', { source: 'user' }), false, env({}, true)],
  ['current_step is', cond('current_step', 'is', '2', { source: 'current_step' }), true, env({}, false, [], 2)],
  ['current_step is (miss)', cond('current_step', 'is', '3', { source: 'current_step' }), false, env({}, false, [], 2)],
  ['current_step gt', cond('current_step', 'gt', '1', { source: 'current_step' }), true, env({}, false, [], 2)],
  ['current_step lte', cond('current_step', 'lte', '2', { source: 'current_step' }), true, env({}, false, [], 2)],
  ['current_step unset is_empty', cond('current_step', 'is_empty', '', { source: 'current_step' }), true, env({}, false, [], null)],
].forEach(t => probeCase('sources', t[0], group('AND', [t[1]]), t[2], baseRows(), t[3]));

// non-field sources combined with field conditions
probeCase('sources/mixed', 'member AND field value',
  group('AND', [cond('logged_in', 'is', 'yes', { source: 'user' }), HIT]), true, baseRows(), env({}, true));
probeCase('sources/mixed', 'member AND field value (guest)',
  group('AND', [cond('logged_in', 'is', 'yes', { source: 'user' }), HIT]), false, baseRows(), env({}, false));
probeCase('sources/mixed', 'campaign param OR admin role',
  group('OR', [cond('utm', 'is', 'sale', { source: 'query_param', param: 'utm' }),
    cond('role', 'is', 'administrator', { source: 'user' })]), true, baseRows(), env({}, true, ['administrator']));
probeCase('sources/mixed', 'campaign param OR admin role (neither)',
  group('OR', [cond('utm', 'is', 'sale', { source: 'query_param', param: 'utm' }),
    cond('role', 'is', 'administrator', { source: 'user' })]), false, baseRows(), env({ utm: 'other' }, true, ['subscriber']));
probeCase('sources/mixed', 'step gate AND checkbox',
  group('AND', [cond('current_step', 'is', '1', { source: 'current_step' }), cond('t_check', 'is', 'co_a')]), true);

// ── FAMILY 8: payment ────────────────────────────────────────────────────────
function payRows(extra) { return baseRows().concat([Object.assign({ id_: 't_pay', type: 'stripe' }, extra)]); }
[
  ['is_paid with payment_status paid', 'is_paid', '', { payment_status: 'paid' }, true],
  ['is_paid with status succeeded', 'is_paid', '', { status: 'succeeded' }, true],
  ['is_paid with a payment intent', 'is_paid', '', { paymentIntent: 'pi_1' }, true],
  ['is_paid with a refId', 'is_paid', '', { refId: 'r1' }, true],
  ['is_paid when unpaid', 'is_paid', '', { value: '100' }, false],
  ['is_not_paid when unpaid', 'is_not_paid', '', { value: '100' }, true],
  ['is_not_paid when paid', 'is_not_paid', '', { payment_status: 'paid' }, false],
  ['amount_eq (hit)', 'amount_eq', '100', { amount: '100' }, true],
  ['amount_eq (miss)', 'amount_eq', '99', { amount: '100' }, false],
  ['amount_gt (hit)', 'amount_gt', '50', { amount: '100' }, true],
  ['amount_gt (boundary)', 'amount_gt', '100', { amount: '100' }, false],
  ['amount_lt (hit)', 'amount_lt', '150', { amount: '100' }, true],
  ['amount_lt (boundary)', 'amount_lt', '100', { amount: '100' }, false],
  ['amount with no amount recorded', 'amount_gt', '50', {}, false],
  ['amount against a non-numeric operand', 'amount_gt', 'abc', { amount: '100' }, false],
].forEach(t => probeCase('payment', t[0], group('AND', [cond('t_pay', t[1], t[2])]), t[4], payRows(t[3])));

probeCase('payment', 'paid AND a field answer',
  group('AND', [cond('t_pay', 'is_paid', ''), HIT]), true, payRows({ payment_status: 'paid' }));
probeCase('payment', 'unpaid blocks a combined rule',
  group('AND', [cond('t_pay', 'is_paid', ''), HIT]), false, payRows({}));

// ── FAMILY 9: required-field enforcement and stored rows (server only) ────────
add('validation', 'a structurally required field is enforced',
  [rule('r', WHEN, [{ type: 'hide_field', target: 't_out1' }])], baseRows(),
  { validate: { valid: false, missing: 's2_req' } });

add('validation', 'filling both required fields is accepted',
  [rule('r', WHEN, [{ type: 'hide_field', target: 't_out1' }])],
  baseRows([{ id_: 's2_req', type: 'text', value: 'A' }, { id_: 's3_req', type: 'text', value: 'B' }]),
  { validate: { valid: true, missing: null } });

add('validation', 'set_optional releases the requirement',
  [rule('r', WHEN, [{ type: 'set_optional', target: 's2_req' }, { type: 'set_optional', target: 's3_req' }])],
  baseRows(), { validate: { valid: true, missing: null } });

add('validation', 'set_required on an empty field rejects the submission',
  [rule('r', WHEN, [{ type: 'set_required', target: 't_out1' }])],
  baseRows([{ id_: 's2_req', type: 'text', value: 'A' }, { id_: 's3_req', type: 'text', value: 'B' }]),
  { validate: { valid: false, missing: 't_out1' } });

add('validation', 'a hidden step removes its requirement',
  [rule('r', WHEN, [{ type: 'hide_step', target: '3' }, { type: 'hide_step', target: '2' }])],
  baseRows(), { validate: { valid: true, missing: null } });

add('validation', 'a required field on the open step is still enforced',
  [rule('r', WHEN, [{ type: 'hide_step', target: '2' }])], baseRows(),
  { validate: { valid: false, missing: 's2_req' } });

add('validation', 'a hidden field is not enforced even when required',
  [rule('r', WHEN, [{ type: 'hide_field', target: 's2_req' }, { type: 'hide_step', target: '2' }])],
  baseRows(), { validate: { valid: true, missing: null } });

add('rows', 'a hidden step contributes nothing to the entry',
  [rule('r', WHEN, [{ type: 'hide_step', target: '2' }])],
  baseRows([{ id_: 's2_req', type: 'text', value: 'KEEP' }, { id_: 's3_req', type: 'text', value: 'DROP' }]),
  { keptRows: { has: ['s2_req', 't_text'], hasNot: ['s3_req'] } });

add('rows', 'a hidden field is dropped from the entry',
  [rule('r', WHEN, [{ type: 'hide_field', target: 't_area' }])], baseRows(),
  { keptRows: { has: ['t_text', 't_num'], hasNot: ['t_area'] } });

add('rows', 'a disabled field is dropped from the entry',
  [rule('r', WHEN, [{ type: 'disable_field', target: 't_area' }])], baseRows(),
  { keptRows: { has: ['t_text'], hasNot: ['t_area'] } });

add('rows', 'a calculated value is stored',
  [rule('r', WHEN, [{ type: 'calculate', target: 't_calc', value: '{t_num} * 2' }])], baseRows(),
  { keptRows: { has: ['t_calc', 't_num'], hasNot: [] } });

add('rows', 'a calculated value on a disabled field is not stored',
  [rule('r', WHEN, [{ type: 'calculate', target: 't_calc', value: '{t_num} * 2' },
    { type: 'disable_field', target: 't_calc' }])], baseRows(),
  { keptRows: { has: ['t_num'], hasNot: ['t_calc'] } });

add('rows', 'nothing is dropped when no rule matches',
  [rule('r', NEVER, [{ type: 'hide_field', target: 't_area' }])], baseRows(),
  { keptRows: { has: ['t_text', 't_area', 't_num'], hasNot: [] } });

add('rows', 'the branch that opens a step keeps its answer',
  BRANCH, selRows('Beta').concat([{ id_: 's2_req', type: 'text', value: 'TYPED' }]),
  { keptRows: { has: ['s2_req'], hasNot: ['s3_req'] } });

/* The row a gateway actually pushes after a successful charge. Captured from a
   real Stripe test-mode payment on 2026-08-21: `amount` is the EFB field
   ORDERING INDEX and is 0, while the money sits in paymentAmount and value.
   Reading `amount` as the paid figure made amount_gt always false and amount_lt
   always true — a "small payment" rule fired on a $10,000 charge. */
function gatewayRow(paid, extra) {
  return Object.assign({
    amount: 0, id_: 'payment', name: 'Payment', type: 'payment',
    paymentGateway: 'stripe', paymentcurrency: 'usd', payment_method: 'card',
    paymentmethod: 'charge', paymentCreated: '2026-08-21-01:00:01',
  }, paid ? { paymentIntent: 'pi_3U6s3BH3QbE1T7b40upSHTbJ' } : {}, extra || {});
}
[
  ['a real $120 charge is over 100',        'amount_gt', '100', { paymentAmount: 120, value: '120' }, true],
  ['a real $120 charge is not under 50',    'amount_lt', '50',  { paymentAmount: 120, value: '120' }, false],
  ['a real $30 charge is under 50',         'amount_lt', '50',  { paymentAmount: 30,  value: '30'  }, true],
  ['a real $30 charge is not over 100',     'amount_gt', '100', { paymentAmount: 30,  value: '30'  }, false],
  ['a real $77 charge equals 77',           'amount_eq', '77',  { paymentAmount: 77,  value: '77'  }, true],
  ['a real $77 charge does not equal 78',   'amount_eq', '78',  { paymentAmount: 77,  value: '77'  }, false],
  ['value carries it when paymentAmount is absent', 'amount_gt', '100', { value: '120' }, true],
  ['the ordering index is never the amount', 'amount_lt', '1',  { paymentAmount: 120, value: '120' }, false],
].forEach(function (t, i) {
  add('payment/gateway-row', t[0],
    [rule('g' + i, group('AND', [cond('t_pay', t[1], t[2])]),
      [{ type: 'hide_field', target: 't_out1' }])],
    baseRows().concat([gatewayRow(true, t[3])]),
    { matched: t[4] ? ['g' + i] : [] });
});

add('payment/gateway-row', 'a paid gateway row is recognised through its intent id',
  [rule('gp', group('AND', [cond('t_pay', 'is_paid', '')]), [{ type: 'hide_field', target: 't_out1' }])],
  baseRows().concat([gatewayRow(true, { paymentAmount: 55, value: '55' })]), { matched: ['gp'] });
add('payment/gateway-row', 'no gateway row at all means not paid',
  [rule('gn', group('AND', [cond('t_pay', 'is_not_paid', '')]), [{ type: 'hide_field', target: 't_out1' }])],
  baseRows(), { matched: ['gn'] });

// ── FAMILY 10: the worked example printed in the AI authoring guide ──────────
// sitepilot-ai/docs/EFB-CONDITIONAL-LOGIC-AUTHORING.md §9 shows this rule set as
// the reference for branching by step. Pinning it here means the guide cannot
// quietly become wrong: if the engines ever stop behaving the way it describes,
// these fail. It carries its own structure because the point of the example is
// its step ids (1 -> position 1, 3 -> position 2, 2 -> position 3).
const GUIDE_STRUCTURE = [
  { type: 'form', steps: '3', formName: 'Contact us', EfbVersion: '2', logic: '1' },
  step('1', 1, 'Contact'),
  step('3', 2, 'Pro User'),
  step('2', 3, 'Report Bug'),
  field('help_topic', 'select', 1, { required: '1', name: 'How can we help?' }),
  option('opt_general', 'help_topic', 'General Inquiry', 1),
  option('opt_presales', 'help_topic', 'Pre-Sales Question', 1),
  option('opt_pro', 'help_topic', 'Pro Support', 1),
  option('opt_bug', 'help_topic', 'Report a Bug', 1),
  field('activation_code', 'text', 2, { required: '1', name: 'Activation Code' }),
  field('bug_zip', 'dadfile', 3, { required: '1', name: 'Upload' }),
];
const GUIDE_RULES = [
  rule('rule_general', group('AND', [
      cond('help_topic', 'is', 'opt_general'),
      Object.assign(cond('help_topic', 'is', 'opt_presales'), { connector: 'OR' })]),
    [{ type: 'hide_step', target: '3' }, { type: 'hide_step', target: '2' }],
    { priority: 9, name: 'General enquiry' }),
  rule('rule_pro', group('AND', [cond('help_topic', 'is', 'opt_pro')]),
    [{ type: 'show_step', target: '3' }, { type: 'hide_step', target: '2' },
     { type: 'set_required', target: 'activation_code' }],
    { priority: 10, name: 'Pro support' }),
  rule('rule_bug', group('AND', [cond('help_topic', 'is', 'opt_bug')]),
    [{ type: 'show_step', target: '2' }, { type: 'hide_step', target: '3' }],
    { priority: 11, name: 'Bug report' }),
];
function guideCase(name, chosenText, expect, extraRows) {
  const rows = (chosenText ? [{ id_: 'help_topic', type: 'select', value: chosenText }] : [])
    .concat(extraRows || []);
  scenarios.push({ family: 'guide', name, rules: GUIDE_RULES, rows, env: ENV,
    extraFields: [], structure: GUIDE_STRUCTURE, expect });
}

guideCase('Pro Support opens step id 3 and requires the code on it', 'Pro Support', {
  matched: ['rule_pro'], hiddenSteps: ['2'], shownSteps: ['3'],
  required: ['activation_code'], ignored: ['bug_zip'],
  validate: { valid: false, missing: 'activation_code' },
});
guideCase('Pro Support with the code filled is accepted and stored', 'Pro Support', {
  validate: { valid: true, missing: null },
  keptRows: { has: ['activation_code', 'help_topic'], hasNot: ['bug_zip'] },
}, [{ id_: 'activation_code', type: 'text', value: 'ABC-123' }]);
guideCase('Report a Bug opens step id 2 and enforces its upload', 'Report a Bug', {
  matched: ['rule_bug'], hiddenSteps: ['3'], shownSteps: ['2'],
  ignored: ['activation_code'], validate: { valid: false, missing: 'bug_zip' },
});
guideCase('General Inquiry closes both extra steps', 'General Inquiry', {
  matched: ['rule_general'], hiddenSteps: ['2', '3'], required: [],
  ignored: ['activation_code', 'bug_zip'], validate: { valid: true, missing: null },
});
guideCase('Pre-Sales takes the same branch through the OR arm', 'Pre-Sales Question', {
  matched: ['rule_general'], hiddenSteps: ['2', '3'],
});
guideCase('nothing chosen leaves every show_step target shut', null, {
  matched: [], hiddenSteps: ['2', '3'], ignored: ['activation_code', 'bug_zip'],
});

// ── write ────────────────────────────────────────────────────────────────────
const out = { base: BASE, scenarios };
const dir = path.join(__dirname, 'fixtures');
if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
fs.writeFileSync(path.join(dir, 'conditional-logic-scenarios.json'), JSON.stringify(out, null, 1));

const byFamily = {};
scenarios.forEach(s => { byFamily[s.family] = (byFamily[s.family] || 0) + 1; });
console.log('scenarios: ' + scenarios.length);
Object.keys(byFamily).sort().forEach(f => console.log('  ' + f.padEnd(22) + byFamily[f]));
