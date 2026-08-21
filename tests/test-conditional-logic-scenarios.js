/**
 * Runs the shared scenario catalog through the BROWSER conditional-logic
 * runtime and checks each scenario against the outcome the catalog states.
 *
 * The PHP counterpart (test-conditional-logic-scenarios.php) asserts the same
 * outcomes against the server validator. Neither runner compares itself to the
 * other: both are held to the catalog, so a bug that is mirrored in both
 * engines still fails here — which is precisely how the two shipped defects
 * (step id vs position, and `is` on a checkbox) escaped a parity-only check.
 *
 * Regenerate the catalog: node tests/generate-conditional-logic-scenarios.js
 * Run:                    node tests/test-conditional-logic-scenarios.js
 *                         node tests/test-conditional-logic-scenarios.js steps/   (filter)
 */

'use strict';

const fs = require('fs');
const path = require('path');

const runtime = require(path.join(__dirname, '../vendor/logic/logic/assets/public/js/conditional-logic-efb.js'));
const catalog = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures/conditional-logic-scenarios.json'), 'utf8'));

const filter = process.argv[2] || '';

let pass = 0, fail = 0;
const failures = [];

/** Sorted string list, so a scenario states a SET and not an ordering. */
function list(value) {
  return (value || []).map(String).slice().sort();
}
function eq(a, b) { return JSON.stringify(a) === JSON.stringify(b); }

function buildStructure(scenario) {
  /* A scenario may bring its own whole form when the shared base does not fit —
     used by the `guide/` family, which pins the example printed in
     sitepilot-ai/docs/EFB-CONDITIONAL-LOGIC-AUTHORING.md against the real
     engines so the documentation cannot drift away from the code. */
  const structure = JSON.parse(JSON.stringify(scenario.structure || catalog.base));
  structure[0].logic_rules = scenario.rules;
  return structure.concat(scenario.extraFields || []);
}

/* Only the keys a scenario actually states are checked; everything else is
 * free to vary. Keys the browser runtime cannot answer without a DOM
 * (validate / keptRows) belong to the PHP runner and are skipped here. */
const CHECKS = {
  matched:     r => list(r.matched_rules),
  hidden:      r => list(r.hidden_fields),
  shown:       r => list(r.shown_fields),
  required:    r => list(r.required_fields),
  optional:    r => list(r.optional_fields),
  disabled:    r => list(r.disabled_fields),
  enabled:     r => list(r.enabled_fields),
  ignored:     r => list(r.ignored_fields),
  hiddenSteps: r => list(r.hidden_steps),
  shownSteps:  r => list(r.shown_steps),
  cleared:     r => list(r.cleared_fields),
  jumps:       r => list((r.jumps || []).map(j => j.target)),
  messages:    r => list((r.messages || []).map(m => m.target + '=' + m.value)),
  ui:          r => list((r.ui_changes || []).map(u => u.target + ':' + u.prop + '=' + u.value)),
  focus:       r => list((r.focus_fields || []).map(f => f.target)),
  scroll:      r => list((r.scroll_fields || []).map(f => f.target)),
  blockMessages: r => list((r.block_messages || []).map(b => b.value)),
  blocked:     r => !!r.submit_blocked,
  stabilized:  r => !!r.stabilized,
  endForm:     r => (r.end_form ? r.end_form.message : null),
  setValues:   r => r.set_values || {},
};
const PHP_ONLY = new Set(['validate', 'keptRows']);

catalog.scenarios.forEach(scenario => {
  if (filter && scenario.family.indexOf(filter) !== 0 && scenario.name.indexOf(filter) === -1) return;

  const label = scenario.family + ' :: ' + scenario.name;
  let result;
  try {
    result = runtime.evaluateDefinition(buildStructure(scenario), scenario.rows, scenario.env);
  } catch (error) {
    fail++;
    failures.push(label + '\n    threw: ' + error.message);
    return;
  }

  const wrong = [];
  Object.keys(scenario.expect).forEach(key => {
    if (PHP_ONLY.has(key)) return;
    const read = CHECKS[key];
    if (!read) { wrong.push('unknown expectation key "' + key + '"'); return; }
    const expected = key === 'setValues' ? scenario.expect[key] : (Array.isArray(scenario.expect[key]) ? list(scenario.expect[key]) : scenario.expect[key]);
    const actual = read(result);
    if (!eq(actual, expected)) {
      wrong.push(key + '\n      expected ' + JSON.stringify(expected) + '\n      actual   ' + JSON.stringify(actual));
    }
  });

  if (wrong.length) { fail++; failures.push(label + '\n    ' + wrong.join('\n    ')); }
  else pass++;
});

if (failures.length) {
  console.log('FAILURES\n');
  failures.forEach(f => console.log('  ' + f + '\n'));
}
console.log('========================================');
console.log('RESULTS: ' + pass + ' passed, ' + fail + ' failed  (browser runtime)');
console.log('========================================');
process.exit(fail > 0 ? 1 : 0);
