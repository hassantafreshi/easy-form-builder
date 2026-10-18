<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * fun_validation_pay_elements_efb() reads the submitted payment rows from the request
 * (Stripe here, and the PayPal add-on calls it too). A value that was not a list of
 * rows, or a row whose price or ids had the wrong type, was a TypeError in count(),
 * abs() or explode() on PHP 8: an HTTP 500 instead of the readable refusal.
 *
 * Real WordPress, no database writes.
 *
 *   cd wp-content/plugins/easy-form-builder && php tests/test-payment-validation-input-types.php
 */
define('DOING_AJAX', true);
require dirname(__DIR__, 4) . '/wp-load.php';

class EfbPayValidationEnd extends Exception {}
add_filter('wp_die_ajax_handler', static function () { return static function () { throw new EfbPayValidationEnd(); }; });

$passed = 0; $failed = 0;
$check = static function ($ok, $label) use (&$passed, &$failed) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . "\n";
    $ok ? $passed++ : $failed++;
};

$ref = new ReflectionClass('Emsfb\\_Public');
$public = $ref->newInstanceWithoutConstructor();
foreach (array('db' => $GLOBALS['wpdb']) as $k => $v) {
    if ($ref->hasProperty($k)) { $p = $ref->getProperty($k); $p->setAccessible(true); $p->setValue($public, $v); }
}

/** Runs the validator; returns ['refused' => bool, 'body' => array|null, 'result' => array|null, 'error' => string]. */
$run = static function ($val, $fs) use ($public) {
    ob_start();
    $out = array('refused' => false, 'body' => null, 'result' => null, 'error' => '');
    try {
        $out['result'] = $public->fun_validation_pay_elements_efb($val, $fs);
    } catch (EfbPayValidationEnd $e) {
        $out['refused'] = true;
    } catch (Throwable $e) {
        $out['error'] = get_class($e) . ': ' . $e->getMessage();
    }
    $body = ob_get_clean();
    $out['body'] = json_decode($body, true);
    return $out;
};

$fs = array(
    array('type' => 'form', 'formName' => 'Pay', 'paymentmethod' => 'charge', 'currency' => 'usd'),
    array('id_' => 'p1', 'type' => 'payRadio', 'name' => 'Plan'),
    array('id_' => 'o1', 'parent' => 'p1', 'value' => 'Gold', 'price' => 5, 'type' => 'r_option'),
);

$refusal = static function ($r) {
    return $r['refused'] && $r['error'] === '' && is_array($r['body']) && isset($r['body']['data']['success']) && $r['body']['data']['success'] === false && !empty($r['body']['data']['m']);
};

foreach (array(
    'null value (undecodable JSON)' => null,
    'a JSON string instead of rows' => 'abc',
    'a number instead of rows'      => 5,
) as $label => $val) {
    $r = $run($val, $fs);
    $check($refusal($r), "$label: readable refusal, no TypeError" . ($r['error'] ? " ({$r['error']})" : ''));
}

$r = $run(array(array('type' => 'payRadio', 'price' => array(5), 'id_ob' => 'o1', 'value' => 'Gold', 'name' => 'Plan')), $fs);
$check($refusal($r), 'array price: readable refusal' . ($r['error'] ? " ({$r['error']})" : ''));

$r = $run(array(array('type' => 'prcfld', 'price' => 'five', 'name' => 'Amount')), $fs);
$check($refusal($r), 'non-numeric price: readable refusal' . ($r['error'] ? " ({$r['error']})" : ''));

$r = $run(array(array('type' => 'payMultiselect', 'price' => 5, 'ids' => array('o1'), 'name' => 'Extras')), $fs);
$check($refusal($r), 'array ids: readable refusal' . ($r['error'] ? " ({$r['error']})" : ''));

$r = $run(array(array('type' => 'payRadio', 'price' => 5, 'id_ob' => 'o1', 'value' => 'Gold', 'name' => 'Plan'), 'junk', 7), $fs);
$check($r['error'] === '' && !$r['refused'] && is_array($r['result']) && (float) $r['result']['price_total'] === 5.0, 'scalar junk rows are skipped; the valid row is still priced' . ($r['error'] ? " ({$r['error']})" : ''));

$r = $run(array(array('type' => 'email', 'price' => 0, 'value' => array('x@example.com'))), $fs);
$check($r['error'] === '' && is_array($r['result']) && $r['result']['email'] === '', 'a non-string email value is ignored');

// Valid input is unchanged.
$r = $run(array(array('type' => 'payRadio', 'price' => '5', 'id_ob' => 'o1', 'value' => 'Gold', 'name' => 'Plan')), $fs);
$check($r['error'] === '' && !$r['refused'] && (float) $r['result']['price_total'] === 5.0 && count($r['result']['valobj']) === 1, 'a valid priced choice (numeric string) is accepted as before');

$r = $run(array(array('type' => 'prcfld', 'price' => 12.5, 'name' => 'Amount')), $fs);
$check($r['error'] === '' && !$r['refused'] && (float) $r['result']['price_total'] === 12.5, 'a valid typed amount is accepted as before');

echo "\nRESULTS: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
