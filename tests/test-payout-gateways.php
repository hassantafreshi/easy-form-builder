<?php
/**
 * PAY-OUT step 5: Stripe, PayPal and ZarinPal as payment-framework gateways of their add-ons (stripecheckout,
 * paypalcheckout, zarinpalgate), through core's real Service and Store (SQLite) against the fake providers
 * (tests/payout-fake-providers.php: no network, no real key). For each gateway:
 *
 *  1  registration (slug, add-on, label, supports, settings schema, api hosts), Test connection (ok, a refused key, a key
 *     of the other mode or none);
 *  2  pay: the provider is asked for the server's amount (whatever the answers say), with an idempotency key; the
 *     visitor comes back, the payment is read from the provider (never from the return URL) and is paid once; the
 *     entry is finalized once (read_ 0) and holds the receipt (status paid, the provider's reference, the method);
 *  3  fail: the provider says not paid → failed with a reason, the entry stays pending payment, nothing finalized;
 *  4  retry: a second attempt of the same entry is created and paid (the failed one stays failed);
 *  5  refund (Stripe, PayPal: full and partial; ZarinPal: refused before any request, the panel's REVERSED shows as
 *     refunded) and how the refund shows on the payment (refunded_minor, status);
 *  6  webhooks: Stripe signed (accepted), forged (401), unsigned (a trigger to read again only); PayPal (a trigger);
 *     ZarinPal none; a webhook never pays by itself;
 *  7  errors: the provider down (503) → no payment link, the reason provider_unavailable; a forged return (another
 *     reference) changes nothing.
 *
 * Run: php tests/test-payout-gateways.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require_once __DIR__ . '/pay-test-db.php';
require_once __DIR__ . '/payout-fake-providers.php';
if (!defined('EMSFB_PLUGIN_URL')) define('EMSFB_PLUGIN_URL', 'https://site.example/wp-content/plugins/easy-form-builder/');
if (!function_exists('delete_option')) { function delete_option($k) { unset($GLOBALS['opts'][$k]); return true; } }
if (!function_exists('get_transient')) { function get_transient($k) { return isset($GLOBALS['efb_transients'][$k]) ? $GLOBALS['efb_transients'][$k] : false; } }
if (!function_exists('set_transient')) { function set_transient($k, $v, $t = 0) { $GLOBALS['efb_transients'][$k] = $v; return true; } }
if (!function_exists('delete_transient')) { function delete_transient($k) { unset($GLOBALS['efb_transients'][$k]); return true; } }
if (!function_exists('get_bloginfo')) { function get_bloginfo($k = '') { return 'Site'; } }
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
use Emsfb\Payments\Registry;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Store;
use Emsfb\Payments\Service;
$t = 'efb_pay_t';
$F = 'Efb_Test_Payout_Fake';
$addons = getenv('EFB_ADDONS_REPO') ?: dirname((string) @readlink(__DIR__ . '/../vendor/stripe'));

function po_env() {
	global $addons;
	require_once __DIR__ . '/../includes/class-Emsfb-builder-api.php';
	efb_pay_test_load();
	$GLOBALS['opts'] = array('emsfb_pro' => 3);
	$GLOBALS['efb_transients'] = $GLOBALS['efb_mails'] = $GLOBALS['efb_http_log'] = $GLOBALS['efb_filters'] = $GLOBALS['efb_hooks'] = array();
	$db = $GLOBALS['wpdb'] = new EfbPayTestDb();
	$db->create_payment_tables();
	Registry::reset();
	Store::$now = null;
	Efb_Test_Payout_Fake::reset();
	add_filter('pre_http_request', array('Efb_Test_Payout_Fake', 'http'), 10, 3);
	foreach (array('stripe', 'paypal', 'persiapay') as $a) require $addons . '/' . $a . '/routes-efb.php';
	Registry::all();
	return $db;
}
$db = po_env();

$G = array(
	'stripecheckout' => array('cur' => 'usd', 'p' => array('25', '12.5'), 'minor' => 2500, 'keys' => array('secret_key__test' => $F::stripe_key('test')), 'fake' => 'stripe'),
	'paypalcheckout' => array('cur' => 'usd', 'p' => array('25', '12.5'), 'minor' => 2500, 'keys' => array('client_id__test' => $F::paypal_id(), 'secret__test' => $F::paypal_secret()), 'fake' => 'paypal'),
	'zarinpalgate'   => array('cur' => 'irr', 'p' => array('250000', '125000'), 'minor' => 250000, 'keys' => array('merchant_id__test' => $F::zarinpal_merchant()), 'fake' => 'zarinpal'),
);
function po_form($slug, $cur, $prices) {
	return array(
		array('type' => 'payment', 'getway' => $slug, 'currency' => $cur, 'trackingCode' => '1', 'email' => 'owner@example.test', 'email_to' => 'em', 'formName' => 'Workshop'),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
		array('id_' => 'rad', 'type' => 'payRadio', 'name' => 'Ticket', 'step' => 1),
		array('id_' => 'ro1', 'parent' => 'rad', 'type' => 'option', 'value' => 'Adult', 'price' => $prices[0]),
		array('id_' => 'ro2', 'parent' => 'rad', 'type' => 'option', 'value' => 'Child', 'price' => $prices[1]),
		array('id_' => 'pf', 'type' => $slug, 'name' => 'Pay', 'step' => 1),
	);
}
function po_create($db, $slug, $form, $fid = null, $msg = null) {
	$fid = $fid ?: $db->add_form($form, 'payment');
	$attempt = sprintf('%08x-%04x-4%03x-8%03x-%012x', mt_rand(), mt_rand(0, 65535), mt_rand(0, 4095), mt_rand(0, 4095), mt_rand());
	$args = array('form_id' => $fid, 'form' => $form, 'rows' => array(array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'value' => 'payer@example.test'), array('id_' => 'rad', 'id_ob' => 'ro1', 'type' => 'payRadio', 'value' => 'x', 'price' => '0.01')),
		'field_id' => 'pf', 'attempt_key' => $attempt, 'method' => '', 'expected_minor' => null, 'page_url' => 'https://site.example/pay/', 'locale' => 'en_US', 'ip' => '203.0.113.7', 'payer_email' => 'payer@example.test', 'description' => '',
		'store_entry' => function ($rows) use ($db, $fid, $msg) {
			if ($msg) return $msg;
			$id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'EFB' . mt_rand(10000, 99999)));
			return array('track' => $db->get_var('SELECT track FROM wp_emsfb_msg_ WHERE msg_id = ' . (int) $id), 'msg_id' => $id);
		});
	return array(Service::create($args), $fid);
}
function po_return($p, $extra = array()) {
	$r = new WP_REST_Request('GET');
	$r->set_param('gateway', $p['gateway']);
	$r->set_param('p', $p['uid']);
	$r->set_param('s', Service::sign($p['uid'], $p['gateway']));
	foreach ($extra as $k => $v) $r->set_param($k, $v);
	$res = Service::handle_return($r);
	return isset($res->headers['Location']) ? $res->headers['Location'] : '';
}
function po_back_params($slug, $p, $ok = true) {
	if ($slug === 'stripecheckout') return array('session_id' => $p['gateway_ref']);
	if ($slug === 'paypalcheckout') return array('token' => $p['gateway_ref'], 'PayerID' => 'EFBFAKEPAYER');
	return array('Authority' => $p['gateway_ref'], 'Status' => $ok ? 'OK' : 'NOK');
}
function po_pay_fake($fake, $ref, $ok = true) {
	if ($fake === 'stripe') Efb_Test_Payout_Fake::update('stripe', $ref, $ok ? array('status' => 'complete', 'payment_status' => 'paid', 'pi_status' => 'succeeded') : array('status' => 'complete', 'payment_status' => 'unpaid', 'pi_status' => 'requires_payment_method'));
	elseif ($fake === 'paypal') Efb_Test_Payout_Fake::update('paypal', $ref, array('status' => 'APPROVED', 'decline' => $ok ? 0 : 1));
	else Efb_Test_Payout_Fake::update('zarinpal', $ref, array('status' => $ok ? 'PAID' : 'FAILED'));
}
$entry = function ($db, $msg) {
	$e = $db->get_row('SELECT content, read_ FROM wp_emsfb_msg_ WHERE msg_id = ' . (int) $msg, ARRAY_A);
	$rows = Service::decode_content($e['content']);
	$pay = null;
	foreach ((array) $rows as $r) if (is_array($r) && ($r['type'] ?? '') === 'payment') $pay = $r;
	return array((string) $e['read_'], $pay);
};

foreach ($G as $slug => $c) {
	echo "\n=== {$slug}\n";
	$g = Registry::get($slug);
	/* 1 */
	$t("$slug 1 registered by its add-on", $g ? array($g->slug(), $g->addon_key()) : null, array($slug, array('stripecheckout' => 'AdnSPF', 'paypalcheckout' => 'AdnPAP', 'zarinpalgate' => 'AdnPPF')[$slug]));
	$sup = $g->supports();
	$t("$slug 1 redirect flow, one-time only, refunds " . ($slug === 'zarinpalgate' ? 'not here' : 'full and partial'), array($sup['flows'], $sup['recurring'], $sup['refund'], $sup['partial_refund']), array(array('redirect'), false, $slug !== 'zarinpalgate', $slug !== 'zarinpalgate'));
	$t("$slug 1 its own API hosts only", count(array_filter($g->api_hosts(), function ($h) { return !preg_match('/(stripe\.com|paypal\.com|zarinpal\.com)$/', $h); })), 0);
	$t("$slug 1 Test connection without keys: refused (no_key)", $g->test_connection(Settings::load($slug), 'test')->error, 'no_key');
	$s = Settings::load($slug);
	$t("$slug 1 the keys are accepted by the schema (secrets stored encrypted)", $s->apply($g->settings_schema(), $c['keys'], 'test'), array());
	$enc = json_encode($GLOBALS['opts']['emsfb_pgw_' . $slug]);
	$t("$slug 1 no key in clear in the settings option", array_filter($c['keys'], function ($v) use ($enc) { return strpos($enc, $v) !== false && strlen($v) > 20 && strpos($v, 'AEfb') !== 0; }), array());
	$t("$slug 1 Test connection with the keys: ok", $g->test_connection(Settings::load($slug), 'test')->ok, true);

	/* 2 */
	$form = po_form($slug, $c['cur'], $c['p']);
	list($res, $fid) = po_create($db, $slug, $form);
	$p = Store::find_by_uid($res['pay']['uid']);
	$fk = Efb_Test_Payout_Fake::get($c['fake'], $p['gateway_ref']);
	$asked = $c['fake'] === 'stripe' ? $fk['amount'] : ($c['fake'] === 'paypal' ? $fk['amount']['value'] : $fk['amount']);
	$t("$slug 2 the provider is asked for the server's amount (Adult), not the 0.01 the answers say", array($res['success'] ?? null, (string) $asked, $p['amount_minor'], $p['status']), array(true, array('stripe' => '2500', 'paypal' => '25.00', 'zarinpal' => '250000')[$c['fake']], $c['minor'], 'pending'));
	$t("$slug 2 the visitor is sent to the provider's page", (bool) preg_match('#^https://#', (string) ($res['pay']['next']['url'] ?? '')), true);
	$idem = $c['fake'] === 'zarinpal' ? true : Efb_Test_Payout_Fake::state()['log'][count(Efb_Test_Payout_Fake::state()['log']) - 1]['idem'] !== '';
	$t("$slug 2 with an idempotency key (Stripe Idempotency-Key / PayPal-Request-Id)", $idem, true);
	$loc = po_return($p, po_back_params($slug, $p));
	$t("$slug 2 coming back before paying: still pending (the return URL proves nothing)", Store::get($p['id'])['status'], 'pending');
	po_pay_fake($c['fake'], $p['gateway_ref'], true);
	$loc = po_return($p, po_back_params($slug, $p));
	$now = Store::get($p['id']);
	list($read, $pay) = $entry($db, $p['msg_id']);
	$t("$slug 2 paid after the provider says so; the entry finalized (read_ 0)", array($now['status'], $read, strpos($loc, 'efb_pay=' . $p['uid']) !== false), array('paid', '0', true));
	$t("$slug 2 the receipt in the entry: status paid, the provider's reference, the amount", array($pay['status'] ?? null, ($pay['paymentIntent'] ?? '') === $p['gateway_ref'], isset($pay['pay_uid'])), array('paid', true, true));
	po_return($p, po_back_params($slug, $p));
	$t("$slug 2 a second return: finalized once (one finalized event)", count(array_filter(Store::events($p['id']), function ($e) { return $e['type'] === 'finalized'; })), 1);
	$paid = Store::get($p['id']);

	/* 3 + 4 */
	list($res2, $fid2) = po_create($db, $slug, $form);
	$p2 = Store::find_by_uid($res2['pay']['uid']);
	po_pay_fake($c['fake'], $p2['gateway_ref'], false);
	po_return($p2, po_back_params($slug, $p2, false));
	$f2 = Store::get($p2['id']);
	list($read2) = $entry($db, $p2['msg_id']);
	$why = array_values(array_filter(Store::events($p2['id']), function ($e) { return in_array($e['to_status'], array('failed', 'canceled'), true); }));
	$t("$slug 3 not paid at the provider: failed (the change logged as an event); the entry stays pending payment", array(in_array($f2['status'], array('failed', 'canceled'), true), count($why) >= 1, $read2), array(true, true, '2'));
	list($res3) = po_create($db, $slug, $form, $fid2, array('track' => $p2['track'], 'msg_id' => $p2['msg_id']));
	$p3 = Store::find_by_uid($res3['pay']['uid']);
	po_pay_fake($c['fake'], $p3['gateway_ref'], true);
	po_return($p3, po_back_params($slug, $p3));
	list($read3, $pay3) = $entry($db, $p2['msg_id']);
	$t("$slug 4 Try again: a new attempt for the same entry, paid; the entry finalized with it; the failed one stays failed", array(Store::get($p3['id'])['status'], $p3['msg_id'] === $p2['msg_id'], $read3, ($pay3['paymentIntent'] ?? '') === $p3['gateway_ref'], Store::get($p2['id'])['status']), array('paid', true, '0', true, $f2['status']));

	/* 5 */
	if ($slug !== 'zarinpalgate') {
		$half = (int) ($paid['amount_minor'] / 2);
		$r = Service::refund($paid, $half, 'part', 1);
		$t("$slug 5 a partial refund: done at the provider, the payment partially refunded", array($r['ok'] ?? null, Store::get($paid['id'])['status'], (int) Store::get($paid['id'])['refunded_minor']), array(true, 'partially_refunded', $half));
		$r = Service::refund(Store::get($paid['id']), $paid['amount_minor'] - $half, 'rest', 1);
		$t("$slug 5 the rest: refunded", array($r['ok'] ?? null, Store::get($paid['id'])['status'], (int) Store::get($paid['id'])['refunded_minor']), array(true, 'refunded', $paid['amount_minor']));
		Service::refresh(Store::get($paid['id']), 'api');
		$t("$slug 5 reading the provider again keeps it refunded (the provider's refunded amount)", Store::get($paid['id'])['status'], 'refunded');
	} else {
		$n = count(Efb_Test_Payout_Fake::state()['log']);
		$r = Service::refund($paid, $paid['amount_minor'], 'x', 1);
		$t("$slug 5 refunds are made in the ZarinPal panel: refused before any request, still paid", array($r['ok'] ?? null, count(Efb_Test_Payout_Fake::state()['log']) - $n, Store::get($paid['id'])['status']), array(false, 0, 'paid'));
		Efb_Test_Payout_Fake::update('zarinpal', $paid['gateway_ref'], array('status' => 'REVERSED'));
		Service::refresh(Store::get($paid['id']), 'api');
		$t("$slug 5 a payment reversed in the panel shows as refunded", Store::get($paid['id'])['status'], 'refunded');
	}

	/* 6 */
	list($res4) = po_create($db, $slug, $form);
	$p4 = Store::find_by_uid($res4['pay']['uid']);
	if ($slug === 'stripecheckout') {
		$body = json_encode(array('id' => 'evt_EfbFake000001', 'object' => 'event', 'type' => 'checkout.session.completed', 'livemode' => false, 'data' => array('object' => array('id' => $p4['gateway_ref'], 'object' => 'checkout.session'))));
		$wr = function ($sig) use (&$body) { $r = new WP_REST_Request('POST'); $r->set_param('gateway', 'stripecheckout'); $r->set_body($body); if ($sig !== null) $r->set_header('Stripe-Signature', $sig); $GLOBALS['efb_transients'] = array_filter($GLOBALS['efb_transients'], function ($k) { return strpos($k, 'efb_pmt_rl_') !== 0; }, ARRAY_FILTER_USE_KEY); return Service::handle_webhook($r)->get_status(); };
		$t("$slug 6 an unsigned event while no signing secret is set: 200, read again — still pending (not paid by the event)", array($wr(null), Store::get($p4['id'])['status']), array(200, 'pending'));
		Settings::load($slug)->apply($g->settings_schema(), array('webhook_secret__test' => 'whsec_' . str_repeat('EfbFake', 4)));
		$ts = (string) Store::time();
		$body = str_replace('evt_EfbFake000001', 'evt_EfbFake000002', $body); // a new event (the first one was taken as a trigger already)
		$good = 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $body, 'whsec_' . str_repeat('EfbFake', 4));
		$t("$slug 6 a forged signature: 401", $wr('t=' . $ts . ',v1=' . str_repeat('0', 64)), 401);
		$t("$slug 6 no signature while a secret is set: 401", $wr(null), 401);
		po_pay_fake('stripe', $p4['gateway_ref'], true);
		$t("$slug 6 a signed checkout.session.completed: 200 and the payment read again → paid", array($wr($good), Store::get($p4['id'])['status']), array(200, 'paid'));
		$t("$slug 6 Stripe-Signature older than 5 minutes is refused", \Emsfb\Payments\Stripe_Checkout_Gateway::signature_ok($good, $body, 'whsec_' . str_repeat('EfbFake', 4), (int) $ts + 301), false);
	} elseif ($slug === 'paypalcheckout') {
		$body = json_encode(array('id' => 'WH-EFBFAKE-0001', 'event_type' => 'CHECKOUT.ORDER.APPROVED', 'resource' => array('id' => $p4['gateway_ref'], 'status' => 'COMPLETED')));
		$r = new WP_REST_Request('POST'); $r->set_param('gateway', $slug); $r->set_body($body);
		$t("$slug 6 an event that claims COMPLETED for an order not approved: 200, read again — still pending", array(Service::handle_webhook($r)->get_status(), Store::get($p4['id'])['status']), array(200, 'pending'));
		po_pay_fake('paypal', $p4['gateway_ref'], true);
		$GLOBALS['efb_transients'] = array_filter($GLOBALS['efb_transients'], function ($k) { return strpos($k, 'efb_pmt_rl_') !== 0; }, ARRAY_FILTER_USE_KEY);
		$r = new WP_REST_Request('POST'); $r->set_param('gateway', $slug); $r->set_body(str_replace('WH-EFBFAKE-0001', 'WH-EFBFAKE-0002', $body));
		$t("$slug 6 after the buyer approved: the event triggers the read and the one capture → paid", array(Service::handle_webhook($r)->get_status(), Store::get($p4['id'])['status'], Efb_Test_Payout_Fake::count('paypal', 'POST', '/capture') >= 1), array(200, 'paid', true));
	} else {
		$t("$slug 6 no webhooks at ZarinPal: the route answers without reading anything", $g->parse_webhook(new WP_REST_Request('POST'), Settings::load($slug)), null);
	}

	/* 7 */
	$st = Efb_Test_Payout_Fake::state(); $st['down'][$c['fake']] = 1; Efb_Test_Payout_Fake::save($st);
	$GLOBALS['efb_transients'] = array_filter($GLOBALS['efb_transients'], function ($k) { return strpos($k, 'efb_ppc_tok_') !== 0; }, ARRAY_FILTER_USE_KEY);
	list($res5) = po_create($db, $slug, $form);
	$t("$slug 7 the provider down: no payment link, the visitor is told to try again later", array(empty($res5['success']), isset($res5['pay']['next']['url'])), array(true, false));
	$st = Efb_Test_Payout_Fake::state(); $st['down'] = array(); Efb_Test_Payout_Fake::save($st);
	list($res6) = po_create($db, $slug, $form);
	$p6 = Store::find_by_uid($res6['pay']['uid']);
	po_pay_fake($c['fake'], $p6['gateway_ref'], true);
	$bad = po_back_params($slug, $p6);
	foreach ($bad as $k => $v) if ($v === $p6['gateway_ref']) $bad[$k] = $p['gateway_ref'];
	po_return($p6, $bad);
	$t("$slug 7 a return that names another payment's reference changes nothing (still pending)", Store::get($p6['id'])['status'], 'pending');
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
