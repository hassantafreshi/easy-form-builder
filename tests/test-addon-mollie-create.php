<?php
/**
 * Mollie add-on (PAY01 §7.1): creating a payment through core's Service::create() and the gateway,
 * against a fake api.mollie.com (pre_http_request; answers from tests/fixtures/payments/mollie).
 *  - the amount is the server quote: fixed (option + pf_fixed), computed (B7 rule), donation; a
 *    browser price is never sent; zero-decimal JPY as "1500"; KWD (3 decimals) is not a Mollie
 *    currency and is refused before any call;
 *  - the request: string amount, description = stored form name + confirmation code (not the
 *    browser's name), signed return / cancel URLs, webhook URL with p + t, metadata without personal
 *    data, Mollie locale, Idempotency-Key = the payment's idem key, Bearer key of the payment's mode;
 *  - methods: visitor's choice within pf_methods, pf_methods cut to what Mollie offers for the
 *    amount, nothing the owner allowed available → refused before creating;
 *  - webhookUrl left out on a local site in Test mode, kept in Live;
 *  - a repeated click (same attempt) and a repeated create (same Idempotency-Key) create one payment;
 *  - Mollie's 422 / 409 / network error → "could not be started", the row failed, no secret anywhere.
 *
 * Run: php tests/test-addon-mollie-create.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/mollie-test-lib.php';
$db = efb_mollie_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Registry;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Payment;
use Emsfb\Payments\Checkout_Context;
$t = 'efb_pay_t';

$gw = Registry::get('mollie');
$t('the add-on registers the gateway "mollie" (AdnMOL) through efb_register_payment_gateways', array($gw ? get_class($gw) : null, $gw ? $gw->addon_key() : ''), array('Emsfb\Payments\Mollie_Gateway', 'AdnMOL'));
$sup = $gw->supports();
$t('supports: redirect, refunds (partial too), refetch webhooks, EUR/JPY/ISK yes, KWD no, per-currency minimum', array($sup['flows'], $sup['refund'], $sup['partial_refund'], $sup['webhook_auth'], in_array('EUR', $sup['currencies'], true), in_array('JPY', $sup['currencies'], true), in_array('KWD', $sup['currencies'], true), $sup['min_minor']['EUR'], $sup['max_minor']['EUR']), array(array('redirect'), true, true, 'refetch', true, true, false, 1, 5000000));
$t('api_hosts allow-list is only api.mollie.com', $gw->api_hosts(), array('api.mollie.com'));

/* fixed amount */
$res = efb_mollie_create($db);
$p = Store::find_by_uid($res['pay']['uid']);
$req = efb_mollie_last('POST', '#^/v2/payments$#');
$b = $req['body'];
$t('create: redirect to Mollie\'s checkout page', array($res['success'], $res['pay']['next']['type'], strpos($res['pay']['next']['url'], 'https://www.mollie.com/checkout/select-method/') === 0), array(true, 'redirect', true));
$t('the amount is the server quote 25.00 EUR as a string (browser price 0.01 ignored)', $b['amount'], array('currency' => 'EUR', 'value' => '25.00'));
$t('description = stored form name + confirmation code (not the browser-sent name)', $b['description'], 'Workshop registration – ' . $p['track']);
$t('redirectUrl and cancelUrl are core\'s signed return URL (cancel with c=1)', strpos($b['redirectUrl'], 'payments/return/mollie?p=' . $p['uid'] . '&s=' . Service::sign($p['uid'], 'mollie')) !== false && strpos($b['cancelUrl'], 'c=1') !== false, true);
parse_str((string) parse_url($b['webhookUrl'], PHP_URL_QUERY), $wq);
$t('webhookUrl = payments/webhook/mollie with p=uid and its token t', array(strpos($b['webhookUrl'], 'https://site.example/wp-json/Emsfb/v1/payments/webhook/mollie?') === 0, $wq['p'], $wq['t']), array(true, $p['uid'], \Emsfb\Payments\Mollie_Gateway::webhook_token($p['uid'])));
$t('metadata has no personal data: efb_uid, form_id, track only', array_keys($b['metadata']), array('efb_uid', 'form_id', 'track'));
$t('locale nl_NL; no method sent when the field does not restrict methods', array($b['locale'], array_key_exists('method', $b)), array('nl_NL', false));
$t('Idempotency-Key = the payment\'s idem key (64 hex); Bearer key of Test mode', array($req['idem'], strlen($req['idem']), $req['key_mode']), array($p['idem_key'], 64, 'test'));
$t('payment pending with the tr_ reference and Mollie\'s expiry', array($p['status'], preg_match('/^tr_[A-Za-z0-9]+$/', $p['gateway_ref']), $p['expires_at'] !== null), array('pending', 1, true));
$t('the expiry also reaches the browser answer (next.expires_at)', isset($res['pay']['next']['expires_at']), true);
$t('no key in the browser answer', strpos(json_encode($res), efb_mollie_key('test')) === false && strpos(json_encode($res), 'test_Fake') === false, true);

/* same attempt again; same Idempotency-Key again */
$form = efb_mollie_form();
$fid = $db->add_form($form, 'payment');
$args = array('form_id' => $fid, 'form' => $form, 'rows' => array(array('id_' => 'rad', 'id_ob' => 'ro2')), 'field_id' => 'pf', 'attempt_key' => efb_mollie_uuid(), 'page_url' => '', 'store_entry' => function ($rows) use ($db, $fid) { $id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2)); return array('track' => 'T' . $id, 'msg_id' => $id); });
$n0 = efb_mollie_count('POST', '#^/v2/payments$#');
$a1 = Service::create($args);
$a2 = Service::create($args);
$t('a double click (same attempt key): one Mollie payment, the same answer', array($a1['pay']['uid'] === $a2['pay']['uid'], !empty($a2['replay']), efb_mollie_count('POST', '#^/v2/payments$#') - $n0), array(true, true, 1));
$pp = Store::find_by_uid($a1['pay']['uid']);
$again = $gw->create(new Payment($pp), Settings::load('mollie'), new Checkout_Context(array('return_url' => Service::return_url($pp), 'cancel_url' => Service::return_url($pp) . '&c=1', 'webhook_url' => Service::webhook_url('mollie'), 'locale' => 'en_US', 'metadata' => array())));
$t('the same create sent twice (lost answer): Mollie answers the same tr_ for the same Idempotency-Key', array($again->ok, $again->gateway_ref), array(true, $pp['gateway_ref']));
$t('12.50 sent as "12.50"', efb_mollie_last('POST', '#^/v2/payments$#')['body']['amount']['value'], '12.50');

/* pf_fixed */
$res = efb_mollie_create($db, efb_mollie_form('eur', array('pf_fixed' => '2', 'pf_fixed_label' => 'Fee')));
$t('fixed amount on the field: 25.00 + 2.00 → "27.00"', array($res['success'], efb_mollie_last('POST', '#^/v2/payments$#')['body']['amount']['value']), array(true, '27.00'));

/* computed (B7) */
$vf = null;
foreach (array(getenv('EFB_LOGIC_VALIDATOR') ?: '', __DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) { if ($f !== '' && is_readable($f)) { $vf = $f; break; } }
if (!$vf) {
	echo "[SKIP] Conditional Logic validator not found: computed amount skipped\n";
} else {
	require_once $vf;
	$cform = array(
		array('type' => 'payment', 'getway' => 'mollie', 'currency' => 'eur', 'formName' => 'Dinner', 'logic_rules' => array(array('id' => 'calc', 'enabled' => true, 'priority' => 1,
			'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'is_not_empty', 'value' => ''))),
			'actions' => array(array('type' => 'calculate', 'target' => 'total', 'value' => '{guests} * 5', 'decimals' => 2))))),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests', 'step' => 1, 'amount' => 2),
		array('id_' => 'total', 'type' => 'prcfld', 'name' => 'Total', 'step' => 1, 'amount' => 3),
		array('id_' => 'pf', 'type' => 'mollie', 'step' => 1, 'amount' => 4),
	);
	$res = efb_mollie_create($db, $cform, array(array('id_' => 'guests', 'id_ob' => 'guests_', 'type' => 'number', 'value' => '3'), array('id_' => 'total', 'type' => 'prcfld', 'value' => '1', 'price' => '1')));
	$t('computed amount (B7): 3 guests × 5 → "15.00" although the browser said 1', array($res['success'], efb_mollie_last('POST', '#^/v2/payments$#')['body']['amount']['value']), array(true, '15.00'));
}

/* donation */
$dform = efb_mollie_form('eur', array('pf_amount' => 'donation', 'pf_presets' => '10,25,50', 'pf_other' => '1', 'pf_min' => '5', 'pf_max' => '500', 'pf_default' => '25'));
$dform = array_values(array_filter($dform, function ($r) { return !in_array($r['id_'] ?? '', array('rad', 'ro1', 'ro2'), true); }));
$res = efb_mollie_create($db, $dform, array(array('id_' => 'pf', 'type' => 'mollie', 'value' => '70')));
$t('donation: other amount 70 → "70.00"', array($res['success'], efb_mollie_last('POST', '#^/v2/payments$#')['body']['amount']['value']), array(true, '70.00'));
$n0 = efb_mollie_count('POST', '#^/v2/payments$#');
$res = efb_mollie_create($db, $dform, array(array('id_' => 'pf', 'type' => 'mollie', 'value' => '2')));
$t('donation below the field minimum → refused by core, Mollie never asked', array($res['success'], $res['code'], efb_mollie_count('POST', '#^/v2/payments$#') - $n0), array(false, 'pwyw_out_of_range', 0));

/* currencies */
$jform = efb_mollie_form('jpy');
$jform[4]['price'] = '1500';
$res = efb_mollie_create($db, $jform);
$t('JPY (zero decimals): "1500" JPY', array($res['success'], efb_mollie_last('POST', '#^/v2/payments$#')['body']['amount']), array(true, array('currency' => 'JPY', 'value' => '1500')));
$jp = Store::find_by_uid($res['pay']['uid']);
$t('...stored as 1500 minor units', array($jp['amount_minor'], $jp['currency']), array(1500, 'JPY'));
$n0 = count(Efb_Test_Mollie_Fake::state()['log']);
$res = efb_mollie_create($db, efb_mollie_form('kwd'));
$t('KWD (3 decimals) is not a Mollie currency: refused before any request', array($res['success'], $res['code'], count(Efb_Test_Mollie_Fake::state()['log']) - $n0), array(false, 'currency_unsupported', 0));

/* methods */
$mform = efb_mollie_form('eur', array('pf_methods' => 'ideal,creditcard'));
efb_mollie_create($db, $mform, null, array('method' => 'ideal'));
$t('the visitor chose iDEAL (allowed by pf_methods) → method "ideal"', efb_mollie_last('POST', '#^/v2/payments$#')['body']['method'], 'ideal');
$ml = efb_mollie_last('GET', '#^/v2/methods$#');
$t('...after asking Mollie which methods take 25.00 EUR', $ml['query'], 'amount%5Bvalue%5D=25.00&amount%5Bcurrency%5D=EUR&locale=nl_NL&includeWallets=applepay');
efb_mollie_create($db, $mform);
$t('no choice → the field\'s methods in its order ["ideal","creditcard"]', efb_mollie_last('POST', '#^/v2/payments$#')['body']['method'], array('ideal', 'creditcard'));
efb_mollie_create($db, $mform, null, array('method' => 'paypal'));
$t('a method outside pf_methods (posted by hand) → the field\'s methods instead', efb_mollie_last('POST', '#^/v2/payments$#')['body']['method'], array('ideal', 'creditcard'));
Efb_Test_Mollie_Fake::set('disabled', array('ideal'));
efb_mollie_create($db, $mform);
$t('a method Mollie does not offer now is left out → "creditcard"', efb_mollie_last('POST', '#^/v2/payments$#')['body']['method'], 'creditcard');
Efb_Test_Mollie_Fake::set('disabled', array('ideal', 'creditcard'));
$n0 = efb_mollie_count('POST', '#^/v2/payments$#');
$res = efb_mollie_create($db, $mform);
$pf = Store::find_by_uid($res['pay']['uid'] ?? '') ?: $db->get_row("SELECT * FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1", ARRAY_A);
$t('nothing the owner allowed is available → not started, no Mollie payment, the row failed', array($res['success'], $res['code'], efb_mollie_count('POST', '#^/v2/payments$#') - $n0, $pf['status']), array(false, 'gateway_error', 0, 'failed'));
Efb_Test_Mollie_Fake::set('disabled', array('paypal'));
efb_mollie_create($db, null, null, array('method' => 'paypal'));
$t('no pf_methods and the chosen method is not active → no method sent (Mollie shows what fits)', array_key_exists('method', efb_mollie_last('POST', '#^/v2/payments$#')['body']), false);
Efb_Test_Mollie_Fake::set('disabled', array());
$t('methods(): the account\'s activated methods (after Test connection), iDEAL shown as "iDEAL | Wero"', ($gw->test_connection(Settings::load('mollie'), 'test')->ok ? $gw->methods(Settings::load('mollie')) : array())[0], array('ideal', 'iDEAL | Wero'));

/* locale */
$loc = function ($l) { return \Emsfb\Payments\Mollie_Client::locale($l); };
$t('locale mapping: de_DE_formal → de_DE, nl_BE → nl_BE, en_AU → en_US, fa_IR → left out', array($loc('de_DE_formal'), $loc('nl_BE'), $loc('en_AU'), $loc('fa_IR'), $loc('')), array('de_DE', 'nl_BE', 'en_US', '', ''));
efb_mollie_create($db, null, null, array('locale' => 'fa_IR'));
$t('fa_IR: no locale sent (Mollie uses the browser\'s language)', array_key_exists('locale', efb_mollie_last('POST', '#^/v2/payments$#')['body']), false);

/* local site */
$lp = Store::find_by_uid($a1['pay']['uid']);
$lp['idem_key'] = hash('sha256', 'local-test');
$ctx = new Checkout_Context(array('return_url' => 'http://localhost:8080/wp-json/Emsfb/v1/payments/return/mollie?p=x', 'cancel_url' => 'http://localhost:8080/r?c=1', 'webhook_url' => 'http://localhost:8080/wp-json/Emsfb/v1/payments/webhook/mollie', 'locale' => 'en_US'));
$gw->create(new Payment($lp), Settings::load('mollie'), $ctx);
$t('local site (localhost) in Test mode: webhookUrl left out (Mollie cannot reach it)', array_key_exists('webhookUrl', efb_mollie_last('POST', '#^/v2/payments$#')['body']), false);
$h = array('http://127.0.0.1:8088/x', 'https://shop.test/x', 'https://site.local/x', 'http://192.168.1.4/x', 'https://example.com/x', 'https://shop.example.org/x');
$t('local hosts: 127.0.0.1, .test, .local, private IP yes; public names no', array_map(array('\Emsfb\Payments\Mollie_Client', 'is_local_url'), $h), array(true, true, true, true, false, false));
$lp['mode'] = 'live';
$lp['idem_key'] = hash('sha256', 'local-live');
$gw->create(new Payment($lp), Settings::load('mollie'), $ctx);
$t('in Live mode the webhookUrl is always sent', array_key_exists('webhookUrl', efb_mollie_last('POST', '#^/v2/payments$#')['body']), true);

/* Live mode */
Settings::load('mollie')->set_mode('live');
$res = efb_mollie_create($db);
$lp = Store::find_by_uid($res['pay']['uid']);
$t('Live mode: the Live key is used, the row and Mollie\'s payment are live, no test banner', array(efb_mollie_last('POST', '#^/v2/payments$#')['key_mode'], $lp['mode'], Efb_Test_Mollie_Fake::payment($lp['gateway_ref'])['mode'], $res['pay']['test_mode']), array('live', 'live', 'live', false));
Settings::load('mollie')->set_mode('test');

/* errors */
$prev = Efb_Test_Mollie_Fake::state();
add_filter('pre_http_request', function ($pre, $args, $url) {
	if (!empty($GLOBALS['mollie_force']) && strpos($url, '/v2/payments') !== false && ($args['method'] ?? '') === 'POST') {
		$f = Efb_Test_Mollie_Fake::fixture($GLOBALS['mollie_force']);
		return array('headers' => array(), 'body' => json_encode($f['body']), 'response' => array('code' => $f['code']), 'cookies' => array());
	}
	return $pre;
}, 5, 3);
$GLOBALS['mollie_force'] = 'error-422';
$res = efb_mollie_create($db);
$row = $db->get_row("SELECT * FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1", ARRAY_A);
$note = $db->get_var("SELECT note FROM wp_emsfb_pmt_evt_ WHERE payment_id = " . (int) $row['id'] . " AND type = 'create_failed'");
$t('Mollie 422 → "could not be started", row failed, Mollie\'s detail in the event log', array($res['success'], $res['code'], $row['status'], strpos($note, 'The amount is lower than the minimum') !== false), array(false, 'gateway_error', 'failed', true));
$GLOBALS['mollie_force'] = 'idempotency-409';
$res = efb_mollie_create($db);
$t('Mollie 409 (same key still being processed) → not started', array($res['success'], $res['code']), array(false, 'gateway_error'));
$GLOBALS['mollie_force'] = null;
Efb_Test_Mollie_Fake::set('down', 1);
$res = efb_mollie_create($db);
$t('network error → not started (POST is never retried by core)', array($res['success'], $res['code']), array(false, 'gateway_error'));
Efb_Test_Mollie_Fake::set('down', 0);

$all = json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_evt_', ARRAY_A)) . json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_', ARRAY_A));
$t('no API key in the payment rows or the event log', strpos($all, efb_mollie_key('test')) === false && strpos($all, efb_mollie_key('live')) === false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
