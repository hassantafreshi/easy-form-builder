<?php
/**
 * PAY02 Payplug add-on: error scenarios (docs/payments/payment-error-matrix.en.md), fake api.payplug.com:
 *  - Oney (instalments) needs the payer's identity, e-mail, mobile number (with the country code) and
 *    a billing address in a country Oney serves: declared required for the Oney methods; core warns the
 *    owner per method, the page / server stop at the empty or malformed field before anything is sent;
 *    the card needs none of it;
 *  - the mobile number goes to Payplug as billing.mobile_phone_number (E.164);
 *  - Payplug refusing a billing value (400 naming the field) → payer_invalid at that field;
 *  - failure.code → the reason the visitor reads (insufficient funds, 3-D Secure, card details, fraud,
 *    aborted → cancelled, timeout → time ran out, declined).
 * Each check fails before this change.
 *
 * Run: php tests/test-addon-payplug-errors.php
 */
$passed = 0; $failed = 0;
require __DIR__ . '/addon-payplug-test-lib.php';
use Emsfb\Payments\Registry;
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Payplug_Gateway;
$t = function ($name, $actual, $expected = true) { efb_pay_t($name, $actual, $expected); };
$db = efb_ppg_env('test', array('oney' => 'x3_with_fees'));
$cw = function ($form) { return array_map(function ($w) { return $w['code'] . ':' . $w['level'] . ':' . (isset($w['m']) ? substr($w['m'], 0, 0) : ''); }, array_values(array_filter(Registry::form_warnings($form), function ($w) { return $w['level'] !== 'info' && $w['code'] !== 'pay_gw_billing_unmapped'; }))); };

$methods = array_column(Registry::public_info('payplug')['methods'], 0);
$t('set-up: card and Oney 3x switched on', $methods, array('card', 'oney_x3_with_fees'));
$w = array_values(array_filter(Registry::form_warnings(efb_ppg_form()), function ($w) { return $w['code'] === 'pay_option_required'; }));
$t('owner: Oney offered and no mobile / country field chosen → warnings naming Oney (the card still works)', array(array_column($w, 'level'), strpos($w[0]['m'], 'to pay with Pay in 3 instalments with Oney') !== false), array(array('warning', 'warning'), true));
$oneyOnly = efb_ppg_form(array('pf_methods' => 'oney_x3_with_fees'));
$t('owner: a field that only offers Oney → the same are errors (the form can never pay)', array_unique(array_column(array_values(array_filter(Registry::form_warnings($oneyOnly), function ($w) { return $w['code'] === 'pay_option_required'; })), 'level')), array('error'));
$t('owner: card only → no Oney requirement', count(array_filter(Registry::form_warnings(efb_ppg_form(array('pf_methods' => 'card'))), function ($w) { return $w['code'] === 'pay_option_required'; })), 0);

$form = efb_ppg_form(array('pgw_map_phone' => 'mo', 'pgw_map_country' => 'co'));
array_splice($form, 8, 0, array(array('id_' => 'mo', 'type' => 'mobile', 'name' => 'Mobile', 'step' => 1), array('id_' => 'co', 'type' => 'conturyList', 'name' => 'Pays', 'step' => 1),
	array('id_' => 'cfr', 'parent' => 'co', 'type' => 'option', 'value' => 'France', 'id_op' => 'FR'), array('id_' => 'cbe', 'parent' => 'co', 'type' => 'option', 'value' => 'Belgique', 'id_op' => 'BE')));
$rows = function ($mo, $co, $opt = 'ro2') { return efb_ppg_rows($opt, array(array('id_' => 'mo', 'type' => 'mobile', 'value' => $mo), array('id_' => 'co', 'type' => 'conturyList', 'value' => $co))); };
$n0 = count(EfbPayplugFake::$log); $e0 = $db->count('wp_emsfb_msg_');
list($r) = efb_ppg_create($db, $form, $rows('', 'France'), array('method' => 'oney_x3_with_fees'));
$t('visitor: Oney and the mobile empty → payer_missing at the mobile field naming Oney; nothing sent, nothing stored', array($r['code'], $r['field_id'], $r['m'], count(EfbPayplugFake::$log) - $n0, $db->count('wp_emsfb_msg_') - $e0), array('payer_missing', 'mo', 'Please fill in "Mobile": it is needed to pay with Pay in 3 instalments with Oney.', 0, 0));
list($r) = efb_ppg_create($db, $form, $rows('06 12 34 56 78', 'France'), array('method' => 'oney_x3_with_fees'));
$t('visitor: Oney and a mobile number without the country code → payer_invalid (phone_e164)', array($r['code'], $r['field_id'], strpos($r['m'], 'with its country code') !== false, count(EfbPayplugFake::$log) - $n0), array('payer_invalid', 'mo', true, 0));
list($r) = efb_ppg_create($db, $form, $rows('+33 6 12 34 56 78', 'Belgique'), array('method' => 'oney_x3_with_fees'));
$t('visitor: Oney and a billing country Oney does not serve (BE) → payer_invalid at the country field', array($r['code'], $r['field_id'], strpos($r['m'], 'not available for the country in "Pays"') !== false), array('payer_invalid', 'co', true));
list($r) = efb_ppg_create($db, $form, $rows('', 'Belgique', 'ro1'), array('method' => 'card'));
$t('visitor: the card needs none of it (an empty mobile and a Belgian address pay by card)', $r['success'], true);
list($r, $p) = efb_ppg_create($db, $form, $rows('0033 6 12 34 56 78', 'France'), array('method' => 'oney_x3_with_fees'));
$b = efb_ppg_last('POST', '/v1/payments')['body'];
$t('Oney with everything → started; billing.mobile_phone_number in E.164, country FR from the form\'s country field', array($r['success'], $b['billing']['mobile_phone_number'], $b['billing']['country'], $b['shipping']['mobile_phone_number']), array(true, '+33612345678', 'FR', '+33612345678'));

// Payplug's documented Oney rules (https://docs.payplug.com/api/apiref.html, "Specific parameters rules
// for Oney payments"); the fake refuses what Payplug refuses. Each check fails on the add-on before this change.
$t('Oney: authorized_amount (not amount) and auto_capture', array($b['authorized_amount'], array_key_exists('amount', $b), $b['auto_capture']), array(15800, false, true));
$cart = isset($b['payment_context']['cart'][0]) ? $b['payment_context']['cart'][0] : array();
$t('Oney: payment_context.cart with one line of the payment (price, quantity 1, total, delivery type, date)', array(count($b['payment_context']['cart']), $cart['price'], $cart['quantity'], $cart['total_amount'], $cart['delivery_type'], (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $cart['expected_delivery_date']), $cart['merchant_item_id'] === $p['uid'], $cart['name'] !== '' && $cart['brand'] !== ''),
	array(1, 15800, 1, 15800, 'edelivery', true, true, true));
$t('Oney: shipping has company_name (the payer\'s name) and delivery_type, the same country as billing, and the e-mail', array($b['shipping']['company_name'], $b['shipping']['delivery_type'], $b['shipping']['country'], $b['shipping']['email']), array($b['billing']['first_name'] . ' ' . $b['billing']['last_name'], 'DIGITAL_GOODS', 'FR', 'jeanne@example.test'));
$schema = (new Payplug_Gateway())->field_schema();
$country = array_values(array_filter($schema, function ($d) { return $d['key'] === 'pgw_map_country'; }))[0];
$t('Oney countries as documented: FR, IT, GP, MQ, GF, RE, YT (Italy in, Monaco out)', $country['countries'], array('FR', 'IT', 'GP', 'MQ', 'GF', 'RE', 'YT'));
array_splice($form, 10, 0, array(array('id_' => 'cit', 'parent' => 'co', 'type' => 'option', 'value' => 'Italia', 'id_op' => 'IT'), array('id_' => 'cmc', 'parent' => 'co', 'type' => 'option', 'value' => 'Monaco', 'id_op' => 'MC'), array('id_' => 'ces', 'parent' => 'co', 'type' => 'option', 'value' => 'España', 'id_op' => 'ES')));
list($r) = efb_ppg_create($db, $form, $rows('+39 312 345 6789', 'Italia'), array('method' => 'oney_x3_with_fees'));
$t('Oney with an Italian address → started, country IT (before: refused at the country field)', array($r['success'], efb_ppg_last('POST', '/v1/payments')['body']['billing']['country']), array(true, 'IT'));
foreach (array('Monaco' => 'MC', 'España' => 'ES') as $cn => $cc) {
	$n1 = count(EfbPayplugFake::$log);
	list($r) = efb_ppg_create($db, $form, $rows('+377 6 12 34 56 78', $cn), array('method' => 'oney_x3_with_fees'));
	$t("Oney with a {$cc} address → payer_invalid at the country field, nothing sent (before: MC was allowed)", array($r['code'], $r['field_id'], count(EfbPayplugFake::$log) - $n1), array('payer_invalid', 'co', 0));
}
$plus = efb_ppg_rows('ro2', array(array('id_' => 'mo', 'type' => 'mobile', 'value' => '+33 6 12 34 56 78'), array('id_' => 'co', 'type' => 'conturyList', 'value' => 'France')));
$plus[0]['value'] = 'jeanne+atelier@example.test';
$n1 = count(EfbPayplugFake::$log); $e1 = $db->count('wp_emsfb_msg_');
list($r) = efb_ppg_create($db, $form, $plus, array('method' => 'oney_x3_with_fees'));
$t('Oney and an e-mail with "+" → payer_invalid at the e-mail field in plain words, nothing sent, nothing stored', array($r['code'], $r['field_id'], $r['m'], count(EfbPayplugFake::$log) - $n1, $db->count('wp_emsfb_msg_') - $e1),
	array('payer_invalid', 'em', 'This payment method does not accept + in "Email". Please enter it without +, or choose another payment method.', 0, 0));
list($r, $po) = efb_ppg_create($db, $form, $rows('+33 6 12 34 56 78', 'France'), array('method' => 'oney_x3_with_fees'));
EfbPayplugFake::$payments[$po['gateway_ref']]['payment_method'] = array('type' => 'oney_x3_with_fees', 'is_pending' => true);
$a = Service::status_answer(Store::get($po['id']), 'status');
$t('"Oney pending" (payment_method.is_pending) → still pending, the visitor reads that Oney is reviewing the request', array($a['state'], strpos(isset($a['message']) ? $a['message'] : '', 'Oney is reviewing') !== false), array('pending', true));
list($r) = efb_ppg_create($db, $form, array_merge($plus, array()), array('method' => 'card'));
$t('...the card takes that e-mail (the "+" rule is Oney\'s only)', $r['success'], true);

EfbPayplugFake::$once['POST /v1/payments'] = array(400, array('object' => 'error', 'message' => 'The parameters are invalid.', 'details' => array('billing' => array('postcode' => 'Invalid postcode for this country.'))));
list($r) = efb_ppg_create($db, $form, $rows('+33 6 12 34 56 78', 'France'), array('method' => 'oney_x3_with_fees'));
$t('Payplug refuses a billing value (400 billing.postcode) → payer_invalid at the postcode field', array($r['code'], $r['field_id'], strpos($r['m'], '"Code postal"') !== false), array('payer_invalid', 'pc', true));

$st = function ($failure) { return Payplug_Gateway::reason_of(array('id' => 'pay_x', 'is_paid' => false, 'failure' => $failure)); };
$t('failure.code → reason', array($st(array('code' => 'insufficient_funds')), $st(array('code' => '3ds_declined')), $st(array('code' => 'incorrect_number')), $st(array('code' => 'fraud_suspected')), $st(array('code' => 'aborted')), $st(array('code' => 'timeout')), $st(array('code' => 'card_declined')), $st(array('code' => 'other', 'message' => 'Amount exceeds the card limit'))),
	array('insufficient_funds', 'auth_failed', 'card_invalid', 'fraud_blocked', 'canceled', 'expired', 'declined', 'limit_exceeded'));
list($r, $p) = efb_ppg_create($db);
EfbPayplugFake::$payments[$p['gateway_ref']]['failure'] = array('code' => '3ds_declined', 'message' => 'The 3-D Secure authentication failed.');
EfbPayplugFake::$payments[$p['gateway_ref']]['is_paid'] = false;
$a = Service::status_answer(Store::get($p['id']), 'status');
$t('the visitor\'s page: 3-D Secure failed in safe words, Try again', array($a['state'], $a['reason'], strpos($a['message'], '3-D Secure') !== false, $a['can_retry']), array('failed', 'auth_failed', true, true));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
