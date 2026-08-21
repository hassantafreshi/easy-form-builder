<?php
/**
 * Runs the shared scenario catalog through the SERVER conditional-logic
 * validator and checks each scenario against the outcome the catalog states.
 *
 * This is the authoritative side: whatever the browser allows, this is what
 * decides which answers are stored and whether a submission is accepted. It
 * asserts the same outcomes as test-conditional-logic-scenarios.js without
 * consulting it, plus the two things only the server can answer — required-field
 * enforcement (validate_required_fields) and which rows survive into the entry
 * (prepare_submission).
 *
 * Regenerate the catalog: node tests/generate-conditional-logic-scenarios.js
 * Run:   C:\xampp\php\php.exe tests/test-conditional-logic-scenarios.php
 *        C:\xampp\php\php.exe tests/test-conditional-logic-scenarios.php steps/
 */

if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }

if (!function_exists('add_filter'))    { function add_filter() { return true; } }
if (!function_exists('apply_filters')) { function apply_filters($hook, $value) { return $value; } }
if (!function_exists('do_action'))     { function do_action() {} }

require_once __DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php';

use Emsfb\Emsfb_Logic_Validator;

$catalog = json_decode(file_get_contents(__DIR__ . '/fixtures/conditional-logic-scenarios.json'), true);
if (!is_array($catalog)) {
    fwrite(STDERR, "Catalog missing — run: node tests/generate-conditional-logic-scenarios.js\n");
    exit(1);
}

$filter = isset($argv[1]) ? $argv[1] : '';
$pass = 0;
$fail = 0;
$failures = array();

/** Sorted string list, so a scenario states a SET and not an ordering. */
function cl_list($value) {
    $out = array_map('strval', is_array($value) ? array_values($value) : array());
    sort($out);
    return $out;
}
function cl_pluck($rows, $key) {
    $out = array();
    foreach ((array) $rows as $row) $out[] = isset($row[$key]) ? $row[$key] : '';
    return $out;
}

foreach ($catalog['scenarios'] as $scenario) {
    if ($filter !== '' && strpos($scenario['family'], $filter) !== 0 && strpos($scenario['name'], $filter) === false) continue;

    $label = $scenario['family'] . ' :: ' . $scenario['name'];

    /* A scenario may bring its own whole form when the shared base does not fit
     * — used by the `guide/` family, which pins the example printed in
     * sitepilot-ai/docs/EFB-CONDITIONAL-LOGIC-AUTHORING.md against the real
     * engines so the documentation cannot drift away from the code. */
    $structure = isset($scenario['structure']) ? $scenario['structure'] : $catalog['base'];
    $structure[0]['logic_rules'] = $scenario['rules'];
    if (!empty($scenario['extraFields'])) $structure = array_merge($structure, $scenario['extraFields']);

    $validator = new Emsfb_Logic_Validator();
    $validator->set_environment($scenario['env']);

    try {
        $result = $validator->evaluate($structure, $scenario['rows']);
    } catch (Throwable $e) {
        $fail++;
        $failures[] = $label . "\n    threw: " . $e->getMessage();
        continue;
    }

    $checks = array(
        'matched'       => cl_list($result['matched_rules']),
        'hidden'        => cl_list($result['hidden_fields']),
        'shown'         => cl_list($result['shown_fields']),
        'required'      => cl_list($result['required_fields']),
        'optional'      => cl_list($result['optional_fields']),
        'disabled'      => cl_list($result['disabled_fields']),
        'enabled'       => cl_list($result['enabled_fields']),
        'ignored'       => cl_list($result['ignored_fields']),
        'hiddenSteps'   => cl_list($result['hidden_steps']),
        'shownSteps'    => cl_list($result['shown_steps']),
        'cleared'       => cl_list($result['cleared_fields']),
        'jumps'         => cl_list(cl_pluck($result['jumps'], 'target')),
        'focus'         => cl_list(cl_pluck($result['focus_fields'], 'target')),
        'scroll'        => cl_list(cl_pluck($result['scroll_fields'], 'target')),
        'blockMessages' => cl_list(cl_pluck($result['block_messages'], 'value')),
        'blocked'       => (bool) $result['submit_blocked'],
        'stabilized'    => (bool) $result['stabilized'],
        'endForm'       => isset($result['end_form']['message']) ? $result['end_form']['message'] : null,
    );

    $messages = array();
    foreach ($result['messages'] as $m) $messages[] = $m['target'] . '=' . $m['value'];
    $checks['messages'] = cl_list($messages);

    $ui = array();
    foreach ($result['ui_changes'] as $u) $ui[] = $u['target'] . ':' . $u['prop'] . '=' . $u['value'];
    $checks['ui'] = cl_list($ui);

    $wrong = array();
    foreach ($scenario['expect'] as $key => $expected) {
        if ($key === 'setValues') {
            $actual = $result['set_values'];
            ksort($actual);
            $want = (array) $expected;
            ksort($want);
            /* json_encode keeps an empty PHP array as [] and an empty JS object
             * as {}; compare the contents, not the serialization. */
            if ($actual != $want) {
                $wrong[] = "setValues\n      expected " . json_encode($want) . "\n      actual   " . json_encode($actual);
            }
            continue;
        }

        if ($key === 'validate') {
            $verdict = $validator->validate_required_fields($structure, $scenario['rows'], $result);
            if ((bool) $verdict['valid'] !== (bool) $expected['valid']) {
                $wrong[] = "validate.valid\n      expected " . json_encode($expected['valid']) . "\n      actual   " . json_encode($verdict['valid']);
            }
            $missing = $verdict['missing_field'];
            if ($missing !== $expected['missing']) {
                $wrong[] = "validate.missing\n      expected " . json_encode($expected['missing']) . "\n      actual   " . json_encode($missing);
            }
            continue;
        }

        if ($key === 'keptRows') {
            $prepared = $validator->prepare_submission($structure, $scenario['rows']);
            $kept = cl_pluck($prepared['submitted_values'], 'id_');
            foreach ((array) $expected['has'] as $id) {
                if (!in_array($id, $kept, true)) $wrong[] = "keptRows: '$id' should have been stored, kept " . json_encode($kept);
            }
            foreach ((array) $expected['hasNot'] as $id) {
                if (in_array($id, $kept, true)) $wrong[] = "keptRows: '$id' should NOT have been stored, kept " . json_encode($kept);
            }
            continue;
        }

        if (!array_key_exists($key, $checks)) { $wrong[] = "unknown expectation key \"$key\""; continue; }

        $want = is_array($expected) ? cl_list($expected) : $expected;
        if ($checks[$key] !== $want) {
            $wrong[] = "$key\n      expected " . json_encode($want) . "\n      actual   " . json_encode($checks[$key]);
        }
    }

    if ($wrong) { $fail++; $failures[] = $label . "\n    " . implode("\n    ", $wrong); }
    else $pass++;
}

if ($failures) {
    echo "FAILURES\n\n";
    foreach ($failures as $f) echo '  ' . $f . "\n\n";
}
echo "========================================\n";
echo "RESULTS: $pass passed, $fail failed  (server validator)\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
