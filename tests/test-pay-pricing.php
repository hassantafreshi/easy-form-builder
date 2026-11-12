<?php
/**
 * PAY00 §8.1: Pricing::quote(), the amount a framework gateway charges.
 *  - every price comes from the stored form; a browser price is never read (option rows, prcfld
 *    within Min/Max, fixed amount, donation presets / other / Min / Max);
 *  - B7: a price field a logic rule computes ({guests} * 5) is priced by the add-on's engine
 *    (the real Emsfb_Logic_Validator from vendores-efb; [SKIP] without it), a lowered or missing
 *    browser value changes nothing, a rule that cannot compute refuses (price_not_computable);
 *  - gateway limits (currency, min, max), the quote hash and the efb_payment_quote filter.
 *
 * Run: php tests/test-pay-pricing.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
require __DIR__ . '/../includes/class-Emsfb-builder-api.php';
efb_pay_test_load();
use Emsfb\Payments\Pricing;
$t = 'efb_pay_t';
$V = json_decode(file_get_contents(__DIR__ . '/fixtures/pay-pricing-vectors.json'), true);
$form = $V['quote'][0]['form'];
$q = function ($rows, $ctx = array()) use ($form) { return Pricing::quote($form, $rows, $ctx); };

$r = $q(array(array('id_' => 'rad', 'id_ob' => 'ro1', 'type' => 'payRadio', 'price' => '0.01')), array('field_id' => 'pf'));
$t('payRadio priced from the saved option (25.00), the fixed amount added (2.00)', array($r['total_minor'], $r['currency'], count($r['items'])), array(2700, 'EUR', 2));
$t('quote carries a sha256 hash', strlen($r['hash']), 64);
$r2 = $q(array(array('id_' => 'rad', 'id_ob' => 'ro1', 'type' => 'payRadio', 'price' => '999')), array('field_id' => 'pf'));
$t('the same choice with another browser price has the same quote and hash', array($r2['total_minor'], $r2['hash']), array(2700, $r['hash']));
$t('an option id of another field is refused', $q(array(array('id_' => 'rad', 'id_ob' => 'co1')))['error'], 'invalid_selection');
$t('an unknown select text is refused', $q(array(array('id_' => 'sel', 'value' => 'Huge')))['error'], 'invalid_selection');
$t('prcfld above Max is refused', $q(array(array('id_' => 'tip', 'value' => '51')))['error'], 'pwyw_out_of_range');
$t('prcfld: the typed value counts, not its price key', $q(array(array('id_' => 'tip', 'value' => '7.5', 'price' => '1')))['total_minor'], 750);
$t('no amount at all', $q(array())['error'], 'no_amount');
$t('donation default (25) when nothing was chosen', $q(array(), array('field_id' => 'don'))['total_minor'], 2500);
$t('donation outside the presets without "other" is refused', $q(array(array('id_' => 'don2', 'value' => '11')), array('field_id' => 'don2'))['error'], 'pwyw_out_of_range');
$t('fields that are not pay fields are ignored (email row)', $q(array(array('id_' => 'rad', 'id_ob' => 'ro1'), array('id_' => 'x', 'type' => 'email', 'price' => '100')))['total_minor'], 2500);
$t('gateway currency list', $q(array(array('id_' => 'rad', 'id_ob' => 'ro1')), array('supports' => array('currencies' => array('THB'))))['error'], 'currency_unsupported');
$t('gateway minimum (Payplug 0.30 / Omise style)', $q(array(array('id_' => 'rad', 'id_ob' => 'ro2')), array('supports' => array('min_minor' => array('EUR' => 3000))))['error'], 'below_min');
$f0 = $form; $f0[0]['currency'] = '';
$t('a form without a currency cannot be quoted', Pricing::quote($f0, array(array('id_' => 'rad', 'id_ob' => 'ro1')))['error'], 'currency_unsupported');
$fk = $form; $fk[0]['currency'] = 'kwd';
$t('KWD: 25 → 25000 (three decimals)', Pricing::quote($fk, array(array('id_' => 'rad', 'id_ob' => 'ro1')))['total_minor'], 25000);
add_filter('efb_payment_quote', function ($q) { $q['fee_minor'] = 100; $q['total_minor'] += 100; return $q; });
$t('efb_payment_quote may add a fee', $q(array(array('id_' => 'rad', 'id_ob' => 'ro1')))['total_minor'], 2600);
add_filter('efb_payment_quote', function ($q) { return 'broken'; });
$t('a filter chain that ends in garbage is ignored (the server quote stays)', $q(array(array('id_' => 'rad', 'id_ob' => 'ro1')))['total_minor'], 2500);
$GLOBALS['efb_filters']['efb_payment_quote'] = array();

// B7 with the real Conditional Logic engine.
$vf = null;
foreach (array(getenv('EFB_LOGIC_VALIDATOR') ?: '', __DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) {
	if ($f !== '' && is_readable($f)) { $vf = $f; break; }
}
if (!$vf) {
	echo "[SKIP] Conditional Logic validator not found: B7 checks skipped\n";
} else {
	require_once $vf;
	$b7 = array(
		array('type' => 'payment', 'getway' => 'efbmock', 'currency' => 'usd', 'logic_rules' => array(array('id' => 'calc', 'enabled' => true, 'priority' => 1,
			'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'is_not_empty', 'value' => ''))),
			'actions' => array(array('type' => 'calculate', 'target' => 'total', 'value' => '{guests} * 5', 'decimals' => 2))))),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests', 'step' => 1, 'amount' => 2),
		array('id_' => 'total', 'type' => 'prcfld', 'name' => 'Total', 'step' => 1, 'amount' => 3),
		array('id_' => 'pay', 'type' => 'efbmock', 'step' => 1, 'amount' => 4),
	);
	$guests = array('id_' => 'guests', 'id_ob' => 'guests_', 'type' => 'number', 'name' => 'Guests', 'value' => '3');
	$t('B7: 3 guests x 5 = 15.00 although the browser row says 1', Pricing::quote($b7, array($guests, array('id_' => 'total', 'type' => 'prcfld', 'value' => '1', 'price' => '1')), array('field_id' => 'pay'))['total_minor'], 1500);
	$t('B7: the computed row missing from the request is still charged 15.00', Pricing::quote($b7, array($guests), array('field_id' => 'pay'))['total_minor'], 1500);
	$b7b = $b7; $b7b[0]['logic_rules'][0]['actions'][0]['value'] = '100 / {missing}';
	$t('B7: a rule that cannot compute refuses (price_not_computable)', Pricing::quote($b7b, array($guests), array('field_id' => 'pay'))['error'], 'price_not_computable');
	$t('B7: server_prices off (display twin) uses the browser value', Pricing::quote($b7, array($guests, array('id_' => 'total', 'value' => '1')), array('server_prices' => false))['total_minor'], 100);
}
$src = file_get_contents(__DIR__ . '/../includes/payments/class-Emsfb-payment-pricing.php');
$lines = array_filter(explode("\n", $src), function ($l) { return strpos($l, "\$row['price']") !== false; });
$t('a row\'s price is read only as the prcfld fallback for its value, never for a choice', count($lines) === 1 && strpos(reset($lines), "\$row['value']") !== false, true);
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
