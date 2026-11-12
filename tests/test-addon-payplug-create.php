<?php
/**
 * Payplug add-on (PAY02 sub-tasks A and B): settings, Test connection, payment creation.
 * The add-on is read from vendor/payplug (symlink to vendores-efb/payplug); api.payplug.com is
 * answered by EfbPayplugFake from the documented responses (tests/fixtures/payments/payplug/).
 *
 *  - registry: slug payplug, add-on AdnPPG, EUR only, 30..2,000,000 cents, refetch webhooks;
 *  - settings: keys per mode, encrypted, never in the option as plain text, never in the
 *    gateway's public info; a key that does not look like a key is refused;
 *  - Test connection: GET /v1/payments?per_page=1 with the key of that mode; refused key; a legacy
 *    key of the other mode; is_live of the account's payments not matching the mode;
 *  - create (fixed / computed / donation): the amount is the server quote in cents (a browser price
 *    of 0.01 is ignored), PayPlug-Version header, Bearer key of the payment's mode, billing and
 *    shipping from the mapped form fields (cleaned), notification URL with our token, return and
 *    cancel URLs, metadata; the payer goes to hosted_payment.payment_url;
 *  - JPY (zero decimals) and KWD (three decimals) are refused before any request (EUR only);
 *    below 0.30 / above 20,000 refused by the server quote;
 *  - methods: a method not switched on is refused; Oney only from 100 to 3,000 EUR; Bancontact sends
 *    payment_method;
 *  - Integrated Payment: integration INTEGRATED_PAYMENT, next embedded with public data only;
 *  - a provider error or an is_live that does not match → the payment fails, nothing is shown;
 *  - a lost answer: the same attempt key never creates a second Payplug payment;
 *  - sandbox vs live key selection.
 *
 * Run: php tests/test-addon-payplug-create.php
 */
$passed = 0; $failed = 0;
require __DIR__ . '/addon-payplug-test-lib.php';
use Emsfb\Payments\Registry;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Payplug_Gateway;
$t = 'efb_pay_t';

$db = efb_ppg_env('test');
$g = Registry::get('payplug');
$t('the add-on registers the gateway "payplug" (AdnPPG) through the registry', array($g instanceof Payplug_Gateway, $g ? $g->addon_key() : '', Registry::errors()), array(true, 'AdnPPG', array()));
$sup = $g->supports();
$t('supports: EUR only, 30..2,000,000 cents, refunds and partial refunds, no recurring in phase 1, refetch', array($sup['currencies'], $sup['min_minor'], $sup['max_minor'], $sup['refund'], $sup['partial_refund'], $sup['recurring'], $sup['webhook_auth']), array(array('EUR'), array('EUR' => 30), array('EUR' => 2000000), true, true, false, 'refetch'));
$t('api_hosts: api.payplug.com only', $g->api_hosts(), array('api.payplug.com'));

// Settings and secrets.
$opt = $GLOBALS['opts']['emsfb_pgw_payplug'];
$t('the keys are stored encrypted, per mode, never as plain text', strpos(json_encode($opt), 'unittest-ppg-key') === false && strpos($opt['secret']['test']['api_key'], 'enc:v1:') === 0 && strpos($opt['secret']['live']['api_key'], 'enc:v1:') === 0, true);
$s = Settings::load('payplug');
$t('...and read back per mode (sandbox and live keys stay apart)', array($s->secret('api_key', 'test'), $s->secret('api_key', 'live')), array(efb_ppg_key('test'), efb_ppg_key('live')));
$info = Registry::public_info('payplug');
$t('public info (builder, capabilities): configured for both modes, without any key', array($info['configured'], strpos(json_encode($info), 'unittest-ppg') === false, $info['currencies']), array(array('test' => true, 'live' => true), true, array('EUR')));
$refused = $s->apply($g->settings_schema(), array('api_key__test' => 'not a key'));
$t('a value that does not look like a Payplug key is refused and the stored key stays', array($refused, Settings::load('payplug')->secret('api_key', 'test')), array(array('api_key__test'), efb_ppg_key('test')));
$m0 = $g->methods(Settings::load('payplug'));
$t('methods: card only by default (with its CB logo)', array(count($m0), $m0[0][0], $m0[0][1], substr($m0[0][2]['logo'], -25)), array(1, 'card', 'Card (CB, Visa, Mastercard)', 'payplug/assets/img/cb.svg'));
$t('field_schema(): the billing address mapping, the e-mail picker limited to e-mail fields (core draws and sanitizes it)', array(array_column($g->field_schema(), 'key'), $g->field_schema()[2]['field_types']), array(array('pgw_map_first', 'pgw_map_last', 'pgw_map_email', 'pgw_map_address', 'pgw_map_postcode', 'pgw_map_city', 'pgw_map_country'), array('email')));
$t('form_warnings(): no address mapped → a warning; mapped → none', array(count($g->form_warnings(array(), array('pgw_map_first' => 'a'), Settings::load('payplug'))), count($g->form_warnings(array(), array('pgw_map_city' => 'c'), Settings::load('payplug')))), array(1, 0));
$t('logos are local files of the add-on (CB, Visa, Mastercard)', array_map(function ($l) { return basename($l[0]); }, $g->logos()), array('cb.svg', 'visa.svg', 'mastercard.svg'));
foreach ($g->logos() as $l) $ok_logo = is_readable(__DIR__ . '/../vendor/payplug/assets/img/' . basename($l[0]));
$t('...and exist in the add-on folder', $ok_logo, true);

// Test connection.
EfbPayplugFake::$log = array();
$r = $g->test_connection(Settings::load('payplug'), 'test');
$l = efb_ppg_last('GET', '/v1/payments');
$t('Test connection (Test): GET /v1/payments?per_page=1 with the Test key and PayPlug-Version → ok', array($r->ok, strpos($l['url'], 'per_page=1') !== false, $l['headers']['Authorization'], $l['headers']['PayPlug-Version']), array(true, true, 'Bearer ' . efb_ppg_key('test'), '2019-08-06'));
$r = $g->test_connection(Settings::load('payplug'), 'live');
$t('Test connection (Live) uses the Live key', array($r->ok, efb_ppg_last('GET', '/v1/payments')['headers']['Authorization']), array(true, 'Bearer ' . efb_ppg_key('live')));
EfbPayplugFake::$once['GET /v1/payments'] = 'error-401';
$r = $g->test_connection(Settings::load('payplug'), 'test');
$t('a refused key → "Payplug refused this key."', array($r->ok, $r->error, $r->message), array(false, 'auth', 'Payplug refused this key.'));
EfbPayplugFake::$once['GET /v1/payments'] = array(200, array('object' => 'list', 'data' => array(array('id' => 'pay_aaaaaaaaaa', 'is_live' => true))));
$r = $g->test_connection(Settings::load('payplug'), 'test');
$t('the account answers with Live payments for the Test key → wrong_mode', array($r->ok, $r->error), array(false, 'wrong_mode'));
$s2 = Settings::load('payplug');
$s2->apply($g->settings_schema(), array('api_key__test' => efb_ppg_legacy_key('live')));
EfbPayplugFake::$log = array();
$r = $g->test_connection(Settings::load('payplug'), 'test');
$t('a legacy Live key in the Test field → wrong_mode, no request', array($r->ok, $r->error, count(EfbPayplugFake::$log)), array(false, 'wrong_mode', 0));
efb_ppg_settings('test');

// Create: fixed amount (option 25 + fixed 8 = 33.00).
EfbPayplugFake::$log = array();
list($res, $p) = efb_ppg_create($db);
$req = efb_ppg_last('POST', '/v1/payments');
$b = $req['body'];
$t('fixed: create succeeds and sends the visitor to Payplug\'s payment page', array($res['success'], $res['pay']['next']['type'], $res['pay']['next']['url']), array(true, 'redirect', EfbPayplugFake::$payments[$p['gateway_ref']]['hosted_payment']['payment_url']));
$t('fixed: the amount is the server quote in cents: 2500 + 800 = 3300 EUR (the browser said 0.01)', array($b['amount'], $b['currency'], $p['amount_minor']), array(3300, 'EUR', 3300));
$t('the request has PayPlug-Version 2019-08-06 and the Test key', array($req['headers']['PayPlug-Version'], $req['headers']['Authorization']), array('2019-08-06', 'Bearer ' . efb_ppg_key('test')));
$t('billing from the mapped fields (tags removed), e-mail, country, language fr', $b['billing'], array('first_name' => 'Jeanne', 'last_name' => 'Martin b', 'address1' => '1 rue de la Paix', 'postcode' => '75002', 'city' => 'Paris', 'email' => 'jeanne@example.test', 'country' => 'FR', 'language' => 'fr'));
$t('shipping = billing + delivery_type DIGITAL_GOODS', array($b['shipping']['delivery_type'], $b['shipping']['city']), array('DIGITAL_GOODS', 'Paris'));
parse_str(parse_url($b['notification_url'], PHP_URL_QUERY), $nq);
$t('notification_url: core webhook route with p = uid and our token t', array(strpos($b['notification_url'], 'https://site.example/wp-json/Emsfb/v1/payments/webhook/payplug?') === 0, $nq['p'], $nq['t']), array(true, $p['uid'], Payplug_Gateway::notify_token($p['uid'])));
$t('return_url and cancel_url are core\'s signed return route (cancel with c=1)', array(strpos($b['hosted_payment']['return_url'], 'payments/return/payplug') !== false && strpos($b['hosted_payment']['return_url'], 's=' . Service::sign($p['uid'], 'payplug')) !== false, strpos($b['hosted_payment']['cancel_url'], 'c=1') !== false), array(true, true));
$t('metadata: efb_uid, form_id, track, efb_idem; description', array($b['metadata']['efb_uid'], $b['metadata']['track'], $b['metadata']['efb_idem'], $b['description']), array($p['uid'], $p['track'], $p['idem_key'], 'Inscription atelier'));
$t('no payment_method for a card payment, no integration, save_card false', array(isset($b['payment_method']), isset($b['integration']), $b['save_card']), array(false, false, false));
$t('payment pending with the Payplug reference; entry pending payment (read_ 2)', array($p['status'], (bool) preg_match('/^pay_/', $p['gateway_ref']), (string) $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $p['msg_id'])['read_']), array('pending', true, '2'));
$t('the browser answer has no key and no token', preg_match('/unittest-ppg|notify/', json_encode($res)) === 0, true);
$t('the notification token is not the return signature', Payplug_Gateway::notify_token($p['uid']) !== Service::sign($p['uid'], 'payplug'), true);

// Sandbox vs live.
efb_ppg_settings('live');
list($res, $pl) = efb_ppg_create($db);
$t('Live mode: the Live key is used and the row is live', array(efb_ppg_last('POST', '/v1/payments')['headers']['Authorization'], $pl['mode']), array('Bearer ' . efb_ppg_key('live'), 'live'));
efb_ppg_settings('test');

// Computed amount (B7 with the real Conditional Logic engine) and donation.
$vf = null;
foreach (array(__DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) { if (is_readable($f)) { $vf = $f; break; } }
if ($vf) {
	require_once $vf;
	$form = efb_ppg_form(array('pf_fixed' => ''), array('logic_rules' => array(array('id' => 'calc', 'enabled' => true, 'priority' => 1,
		'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'is_not_empty', 'value' => ''))),
		'actions' => array(array('type' => 'calculate', 'target' => 'total', 'value' => '{guests} * 12.5', 'decimals' => 2))))));
	array_splice($form, 9, 0, array(array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests', 'step' => 1), array('id_' => 'total', 'type' => 'prcfld', 'name' => 'Total', 'step' => 1)));
	$rows = array(array('id_' => 'em', 'type' => 'email', 'value' => 'jeanne@example.test'), array('id_' => 'guests', 'type' => 'number', 'value' => '3'), array('id_' => 'total', 'type' => 'prcfld', 'value' => '1', 'price' => '1'));
	list($res, $pc) = efb_ppg_create($db, $form, $rows);
	$t('computed: 3 guests x 12.50 = 3750 cents sent to Payplug (the browser said 1.00)', array($res['success'], efb_ppg_last('POST', '/v1/payments')['body']['amount'], $pc['amount_minor']), array(true, 3750, 3750));
} else {
	echo "[SKIP] Conditional Logic validator not found: computed amount skipped\n";
}
$form = efb_ppg_form(array('pf_fixed' => '', 'pf_amount' => 'donation', 'pf_presets' => '10,25,50', 'pf_other' => '1', 'pf_min' => '5', 'pf_max' => '500'));
$rows = array(array('id_' => 'em', 'type' => 'email', 'value' => 'jeanne@example.test'), array('id_' => 'pf', 'type' => 'payplug', 'value' => '42.5'));
list($res, $pd) = efb_ppg_create($db, $form, $rows);
$t('donation: "other amount" 42.50 → 4250 cents', array($res['success'], efb_ppg_last('POST', '/v1/payments')['body']['amount']), array(true, 4250));
$rows[1]['value'] = '2';
EfbPayplugFake::$log = array();
list($res, ) = efb_ppg_create($db, $form, $rows);
$t('donation below the field minimum: refused by the server, no request', array($res['success'], $res['code'], count(EfbPayplugFake::$log)), array(false, 'pwyw_out_of_range', 0));
$t('donation without the mapped fields: billing has e-mail, default country and language only', efb_ppg_last('POST', '/v1/payments') === null, true);
list($res, $pd2) = efb_ppg_create($db, efb_ppg_form(array('pf_fixed' => '', 'pf_amount' => 'donation', 'pf_presets' => '10', 'pf_other' => '1', 'pgw_map_first' => 'nope', 'pgw_map_last' => '../x')), array(array('id_' => 'pf', 'type' => 'payplug', 'value' => '10')), array('payer_email' => 'donor@example.test', 'locale' => 'it_IT'));
$t('...e-mail from the payer, country FR, language it; unknown or malformed map ids ignored', efb_ppg_last('POST', '/v1/payments')['body']['billing'], array('email' => 'donor@example.test', 'country' => 'FR', 'language' => 'it'));

// Limits and currencies.
EfbPayplugFake::$log = array();
list($res, ) = efb_ppg_create($db, efb_ppg_form(array('pf_fixed' => '0.20')), array(array('id_' => 'em', 'type' => 'email', 'value' => 'a@example.test')));
$t('0.20 EUR: below Payplug\'s 0.30 → below_min "at least €0.30", no request', array($res['success'], $res['code'], (bool) preg_match('/0[.,]30/', $res['m']), count(EfbPayplugFake::$log)), array(false, 'below_min', true, 0));
list($res, ) = efb_ppg_create($db, efb_ppg_form(array('pf_fixed' => '20000.01')), array(array('id_' => 'em', 'type' => 'email', 'value' => 'a@example.test')));
$t('20,000.01 EUR: above_max', array($res['success'], $res['code']), array(false, 'above_max'));
foreach (array('JPY' => 'zero decimals', 'KWD' => 'three decimals') as $cur => $what) {
	list($res, ) = efb_ppg_create($db, efb_ppg_form(array(), array('currency' => strtolower($cur))));
	$t("{$cur} ({$what}): refused before any request (Payplug takes EUR only)", array($res['success'], $res['code'], count(EfbPayplugFake::$log)), array(false, 'currency_unsupported', 0));
}
$pp = new \Emsfb\Payments\Payment(array('uid' => str_repeat('a', 22), 'amount_minor' => 1000, 'currency' => 'KWD', 'mode' => 'test'));
$t('the gateway itself refuses another currency too', $g->create($pp, Settings::load('payplug'), new \Emsfb\Payments\Checkout_Context())->error, 'currency_unsupported');

// Methods.
list($res, ) = efb_ppg_create($db, null, null, array('method' => 'bancontact'));
$t('Bancontact not switched on in the settings → the payment is not started', array($res['success'], $res['code'], count(EfbPayplugFake::$log)), array(false, 'method_unavailable', 0));
efb_ppg_settings('test', array('method_bancontact' => '1', 'oney' => 'x3x4_with_fees'));
$t('methods after switching on Bancontact and Oney 3x/4x', array_map(function ($m) { return $m[0]; }, $g->methods(Settings::load('payplug'))), array('card', 'bancontact', 'oney_x3_with_fees', 'oney_x4_with_fees'));
list($res, ) = efb_ppg_create($db, null, null, array('method' => 'bancontact'));
$t('Bancontact → payment_method "bancontact"', array($res['success'], efb_ppg_last('POST', '/v1/payments')['body']['payment_method']), array(true, 'bancontact'));
EfbPayplugFake::$log = array();
list($res, ) = efb_ppg_create($db, null, null, array('method' => 'oney_x3_with_fees'));
$t('Oney for 33.00 EUR (below 100): refused, no request', array($res['success'], count(EfbPayplugFake::$log)), array(false, 0));
list($res, ) = efb_ppg_create($db, null, efb_ppg_rows('ro2'), array('method' => 'oney_x3_with_fees'));
$t('Oney for 158.00 EUR → payment_method oney_x3_with_fees', array($res['success'], efb_ppg_last('POST', '/v1/payments')['body']['payment_method'], efb_ppg_last('POST', '/v1/payments')['body']['amount']), array(true, 'oney_x3_with_fees', 15800));
$t('the logos follow the methods (Bancontact, Oney once)', array_map(function ($l) { return $l[1]; }, $g->logos()), array('CB', 'Visa', 'Mastercard', 'Bancontact', 'Oney'));

// Integrated Payment.
efb_ppg_settings('test', array('card_mode' => 'integrated', 'scheme_choice' => '1'));
list($res, $pi) = efb_ppg_create($db);
$b = efb_ppg_last('POST', '/v1/payments')['body'];
$t('Integrated: integration INTEGRATED_PAYMENT; next embedded with payment_id, test_mode, scheme_choice', array($b['integration'], $res['pay']['next']['type'], $res['pay']['next']['client']['payment_id'], $res['pay']['next']['client']['test_mode'], $res['pay']['next']['client']['scheme_choice']), array('INTEGRATED_PAYMENT', 'embedded', $pi['gateway_ref'], true, true));
$t('...the embedded answer has no URL, no key, no token', array(isset($res['pay']['next']['url']), preg_match('/unittest-ppg/', json_encode($res))), array(false, 0));
list($res, ) = efb_ppg_create($db, null, null, array('method' => 'bancontact'));
$t('Integrated mode still redirects for Bancontact', $res['pay']['next']['type'] ?? '', 'redirect');
efb_ppg_settings('test', array('card_mode' => 'hosted', 'scheme_choice' => '', 'method_bancontact' => '', 'oney' => ''));

// Provider errors.
EfbPayplugFake::$once['POST /v1/payments'] = 'error-400';
list($res, ) = efb_ppg_create($db);
$last = $db->get_row('SELECT * FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1', ARRAY_A);
$t('a 400 from Payplug: "could not be started", payment failed, the reason in the event log', array($res['success'], $res['code'], $last['status'], strpos(json_encode(Store::events($last['id'])), 'greater than or equal to 30') !== false), array(false, 'gateway_error', 'failed', true));
EfbPayplugFake::$once['POST /v1/payments'] = array(201, array_merge(efb_ppg_fixture('create-201')['response']['body'], array('is_live' => true)));
list($res, ) = efb_ppg_create($db);
$t('Payplug answers a Live payment for a Test row: refused (wrong_mode)', array($res['success'], strpos(json_encode(Store::events($db->get_var('SELECT MAX(id) FROM wp_emsfb_pmt_'))), 'wrong_mode') !== false), array(false, true));
EfbPayplugFake::$once['POST /v1/payments'] = array(201, array_merge(efb_ppg_fixture('create-201')['response']['body'], array('amount' => 100)));
list($res, ) = efb_ppg_create($db);
$t('Payplug created another amount: refused (amount_mismatch)', $res['success'], false);

// Lost answer: the same attempt never creates a second Payplug payment.
$form = efb_ppg_form();
$att = efb_ppg_uuid();
$n = count(EfbPayplugFake::$payments);
EfbPayplugFake::$lose_next = true;
list($r1, $p1) = efb_ppg_create($db, $form, null, array('attempt_key' => $att, 'form_id' => 777));
$r2 = Service::create(array('form_id' => 777, 'form' => $form, 'rows' => efb_ppg_rows(), 'field_id' => 'pf', 'attempt_key' => $att, 'page_url' => '', 'store_entry' => function () { return array('track' => 'X', 'msg_id' => 1); }));
$t('the answer of create was lost: "could not be started"; the same attempt again → no second request (attempt used)', array($r1['success'], $r2['success'], $r2['code'], count(EfbPayplugFake::$payments) - $n), array(false, false, 'attempt_used', 1));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
