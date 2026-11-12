<?php
/**
 * Not a test. Shared set-up of the Xendit add-on tests (tests/test-addon-xendit-*.php):
 *  - the PAY00 test database and WordPress stand-ins (pay-test-db.php);
 *  - the add-on from vendor/xendit (git-ignored symlink to vendores-efb/xendit), registered the way
 *    core loads it (routes-efb.php → efb_register_payment_gateways);
 *  - a fake api.xendit.co behind pre_http_request (tests/xendit-fake-provider.php, answers from the
 *    official examples in tests/fixtures/payments/xendit; no network, no real key: the keys and
 *    tokens are assembled at run time from obviously fake parts);
 *  - forms in IDR (whole rupiah), PHP, VND and helpers for webhooks as Xendit posts them.
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/pay-test-db.php';
require_once __DIR__ . '/xendit-fake-provider.php';
if (!defined('EMSFB_PLUGIN_URL')) define('EMSFB_PLUGIN_URL', 'https://site.example/wp-content/plugins/easy-form-builder/');
if (!function_exists('delete_option')) { function delete_option($k) { unset($GLOBALS['opts'][$k]); return true; } }

function xnd_addon_dir() {
	foreach (array(getenv('EFB_XENDIT_DIR') ?: '', __DIR__ . '/../vendor/xendit', '/home/user/vendores-efb/xendit') as $d) {
		if ($d !== '' && is_readable($d . '/routes-efb.php')) return $d;
	}
	fwrite(STDERR, "Xendit add-on not found (vendor/xendit symlink or EFB_XENDIT_DIR)\n");
	exit(2);
}

function xnd_key($mode) { return Efb_Test_Xendit_Fake::key($mode); }
function xnd_token($mode) { return Efb_Test_Xendit_Fake::token($mode); }

/** The payment environment with the Xendit add-on registered, keys and webhook tokens of both modes saved. */
function xnd_env($mode = 'test', $extra_settings = array()) {
	require_once __DIR__ . '/../includes/class-Emsfb-builder-api.php';
	efb_pay_test_load();
	require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-frontend.php';
	$GLOBALS['opts'] = array('emsfb_pro' => 3);
	$GLOBALS['efb_transients'] = $GLOBALS['efb_mails'] = $GLOBALS['efb_http_log'] = $GLOBALS['efb_filters'] = $GLOBALS['efb_hooks'] = array();
	$db = $GLOBALS['wpdb'] = new EfbPayTestDb();
	$db->create_payment_tables();
	\Emsfb\Payments\Registry::reset();
	\Emsfb\Payments\Store::$now = null;
	Efb_Test_Xendit_Fake::reset(true);
	add_filter('pre_http_request', array('Efb_Test_Xendit_Fake', 'http'), 10, 3);
	require xnd_addon_dir() . '/routes-efb.php';
	\Emsfb\Payments\Registry::all(); // loads the gateway class
	$s = \Emsfb\Payments\Settings::load('xendit');
	$s->apply((new \Emsfb\Payments\Xendit\Xendit_Gateway())->settings_schema(), array_merge(array(
		'secret_key__test' => xnd_key('test'), 'secret_key__live' => xnd_key('live'),
		'callback_token__test' => xnd_token('test'), 'callback_token__live' => xnd_token('live'),
	), $extra_settings), $mode);
	return $db;
}

/** A form: name, email, a priced radio (150000 / 25000 in IDR), the Xendit field (+ fixed fee). */
function xnd_form($currency = 'idr', $field = array(), $prices = array('150000', '25000'), $fee = '2500') {
	return array(
		array('type' => 'payment', 'getway' => 'xendit', 'currency' => $currency, 'trackingCode' => '1', 'email' => 'owner@example.test', 'email_to' => 'em', 'formName' => 'Workshop'),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'nm', 'type' => 'text', 'name' => 'Nama lengkap', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
		array('id_' => 'rad', 'type' => 'payRadio', 'name' => 'Ticket', 'step' => 1),
		array('id_' => 'ro1', 'parent' => 'rad', 'type' => 'option', 'value' => 'Full', 'price' => $prices[0]),
		array('id_' => 'ro2', 'parent' => 'rad', 'type' => 'option', 'value' => 'Half', 'price' => $prices[1]),
		array_merge(array('id_' => 'pf', 'type' => 'xendit', 'name' => 'Pay', 'step' => 1, 'pf_fixed' => $fee, 'pf_fixed_label' => 'Fee'), $field),
	);
}

function xnd_rows($option = 'ro1', $extra = array(), $name = 'Siti Nurhaliza Putri') {
	return array_merge(array(
		array('id_' => 'nm', 'type' => 'text', 'name' => 'Nama lengkap', 'value' => $name),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'value' => 'payer@example.test'),
		array('id_' => 'rad', 'id_ob' => $option, 'type' => 'payRadio', 'value' => 'x', 'price' => '1'),
	), $extra);
}

/** Service::create() for a Xendit form, the entry stored in the test db. */
function xnd_create($db, $args = array()) {
	$form = isset($args['form']) ? $args['form'] : xnd_form();
	unset($args['form']);
	$fid = $db->add_form($form, 'payment');
	$attempt = sprintf('%08x-%04x-4%03x-8%03x-%012x', mt_rand(), mt_rand(0, 65535), mt_rand(0, 4095), mt_rand(0, 4095), mt_rand());
	return \Emsfb\Payments\Service::create(array_merge(array(
		'form_id' => $fid, 'form' => $form, 'rows' => xnd_rows(), 'field_id' => 'pf', 'attempt_key' => $attempt, 'method' => 'qris',
		'expected_minor' => null, 'page_url' => 'https://site.example/daftar/', 'locale' => 'id_ID', 'ip' => '203.0.113.7', 'payer_email' => 'payer@example.test',
		'description' => '',
		'store_entry' => function ($rows) use ($db, $fid) {
			$id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'EFB' . mt_rand(10000, 99999)));
			return array('track' => $db->get_var('SELECT track FROM wp_emsfb_msg_ WHERE msg_id = ' . (int) $id), 'msg_id' => $id);
		},
	), $args));
}

/** The last POST /sessions: decoded body, raw JSON, log row. */
function xnd_last_session_body() {
	$r = Efb_Test_Xendit_Fake::last('POST', '/sessions');
	return array(json_decode($r['raw'], true), $r['raw'], $r);
}

/** The payment row of a create answer. */
function xnd_row($res) {
	return \Emsfb\Payments\Store::find_by_uid($res['pay']['uid']);
}

/** A webhook request as Xendit posts it: raw JSON body + x-callback-token (default: the test token). */
function xnd_webhook_req($body, $token = null) {
	$r = new WP_REST_Request('POST');
	$r->set_param('gateway', 'xendit');
	$r->set_body($body);
	if ($token !== false) $r->set_header('x-callback-token', $token === null ? xnd_token('test') : $token);
	return $r;
}

/** Core's webhook route for a body; returns the HTTP status. */
function xnd_post_webhook($body, $token = null) {
	$GLOBALS['efb_transients'] = array_filter($GLOBALS['efb_transients'], function ($k) { return strpos($k, 'efb_pmt_rl_') !== 0; }, ARRAY_FILTER_USE_KEY);
	return \Emsfb\Payments\Service::handle_webhook(xnd_webhook_req($body, $token))->get_status();
}

/** Core's return route (?p&s[&c=1]) for a payment row; returns the 303 Location. */
function xnd_return($p, $cancel = false) {
	$r = new WP_REST_Request('GET');
	$r->set_param('gateway', 'xendit');
	$r->set_param('p', $p['uid']);
	$r->set_param('s', \Emsfb\Payments\Service::sign($p['uid'], 'xendit'));
	if ($cancel) $r->set_param('c', '1');
	$res = \Emsfb\Payments\Service::handle_return($r);
	return isset($res->headers['Location']) ? $res->headers['Location'] : '';
}

/** Count of requests in the fake's log matching method + path part. */
function xnd_count($method, $part) {
	return count(array_filter(Efb_Test_Xendit_Fake::state()['log'], function ($l) use ($method, $part) { return $l['method'] === $method && strpos($l['path'], $part) !== false; }));
}
