<?php
/**
 * Not a test. Shared set-up of the Paymob add-on tests (tests/test-addon-paymob-*.php):
 *  - the PAY00 test database and WordPress stand-ins (pay-test-db.php);
 *  - the add-on from vendor/paymob (git-ignored symlink to vendores-efb/paymob), registered the way
 *    core loads it (routes-efb.php → efb_register_payment_gateways);
 *  - the fake Paymob of tests/paymob-fake-provider.php behind pre_http_request (answers in the
 *    shape of the official examples in tests/fixtures/payments/paymob; no network, no real key:
 *    keys are assembled at run time);
 *  - callbacks signed by the fake's independent HMAC (field by field).
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/pay-test-db.php';
require_once __DIR__ . '/paymob-fake-provider.php';
if (!defined('EMSFB_PLUGIN_URL')) define('EMSFB_PLUGIN_URL', 'https://site.example/wp-content/plugins/easy-form-builder/');

function pmb_addon_dir() {
	foreach (array(getenv('EFB_PAYMOB_DIR') ?: '', __DIR__ . '/../vendor/paymob', '/home/user/vendores-efb/paymob') as $d) {
		if ($d !== '' && is_readable($d . '/routes-efb.php')) return $d;
	}
	fwrite(STDERR, "Paymob add-on not found (vendor/paymob symlink or EFB_PAYMOB_DIR)\n");
	exit(2);
}

/** Short names of the fake. */
class PmbFake extends Efb_Test_Paymob_Fake {
	public static function log() { return self::state()['log']; }
	public static function last($method, $path_part) {
		$log = self::log();
		for ($i = count($log) - 1; $i >= 0; $i--) {
			if ($log[$i]['method'] === $method && strpos($log[$i]['path'], $path_part) !== false) return $log[$i];
		}
		return null;
	}
	public static function count_calls($path_part, $method = null) {
		return count(array_filter(self::log(), function ($l) use ($path_part, $method) { return strpos($l['path'], $path_part) !== false && ($method === null || $l['method'] === $method); }));
	}
	public static function order($id) { return self::state()['orders'][(string) $id]; }
	public static function intention_of($order) { $s = self::state(); return $s['intentions'][$s['orders'][(string) $order]['intention']]; }
}

/** A Transaction Processed callback request (raw JSON body, hmac in the query). */
function pmb_callback_req($txn, $secret = null, $hmac = null, $body = null) {
	$c = PmbFake::callback($txn, $secret);
	$r = new WP_REST_Request('POST');
	$r->set_param('gateway', 'paymob');
	if ($hmac !== false) $r->set_param('hmac', $hmac !== null ? $hmac : $c['hmac']);
	$r->set_body($body !== null ? $body : $c['body']);
	return $r;
}

/** The payment environment with the Paymob add-on registered and both modes' keys saved. */
function pmb_env($mode = 'test', $region = 'eg', $extra = array()) {
	require_once __DIR__ . '/../includes/class-Emsfb-builder-api.php';
	efb_pay_test_load();
	require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-frontend.php';
	$GLOBALS['opts'] = array('emsfb_pro' => 3);
	$GLOBALS['efb_transients'] = $GLOBALS['efb_mails'] = $GLOBALS['efb_http_log'] = $GLOBALS['efb_filters'] = $GLOBALS['efb_hooks'] = array();
	$db = $GLOBALS['wpdb'] = new EfbPayTestDb();
	$db->create_payment_tables();
	\Emsfb\Payments\Registry::reset();
	PmbFake::reset();
	add_filter('pre_http_request', array('Efb_Test_Paymob_Fake', 'http'), 10, 3);
	require pmb_addon_dir() . '/routes-efb.php';
	pmb_settings($region, $mode, $extra);
	return $db;
}

function pmb_settings($region = 'eg', $mode = 'test', $extra = array()) {
	$s = \Emsfb\Payments\Settings::load('paymob');
	$refused = $s->apply((new \Emsfb\Paymob\Paymob_Gateway())->settings_schema(), array_merge(PmbFake::settings($region), $extra), $mode);
	\Emsfb\Paymob\Paymob_Gateway::forget_tokens();
	return $refused;
}

/** An EGP form: name, email, mobile, a priced radio (500 / 75.50), the Paymob field (fixed 100 fee). */
function pmb_form($currency = 'egp', $field = array()) {
	return array(
		array('type' => 'payment', 'getway' => 'paymob', 'currency' => $currency, 'trackingCode' => '1', 'email' => 'owner@example.test', 'email_to' => 'em'),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'nm', 'type' => 'text', 'name' => 'الاسم الكامل', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
		array('id_' => 'mob', 'type' => 'mobile', 'name' => 'Mobile', 'step' => 1),
		array('id_' => 'rad', 'type' => 'payRadio', 'name' => 'Workshop', 'step' => 1),
		array('id_' => 'ro1', 'parent' => 'rad', 'type' => 'option', 'value' => 'Workshop seat', 'price' => '500'),
		array('id_' => 'ro2', 'parent' => 'rad', 'type' => 'option', 'value' => 'Recording only', 'price' => '75.5'),
		array_merge(array('id_' => 'pf', 'type' => 'paymob', 'name' => 'Pay', 'step' => 1, 'pf_fixed' => '100', 'pf_fixed_label' => 'Materials'), $field),
	);
}

function pmb_rows($option = 'ro1', $extra = array(), $mobile = '٠١٠١٢٣٤٥٦٧٨') {
	return array_merge(array(
		array('id_' => 'nm', 'type' => 'text', 'name' => 'الاسم الكامل', 'value' => 'Mona Abdel Rahman'),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'value' => 'payer@example.test'),
		array('id_' => 'mob', 'type' => 'mobile', 'name' => 'Mobile', 'value' => $mobile),
		array('id_' => 'rad', 'id_ob' => $option, 'type' => 'payRadio', 'value' => 'x', 'price' => '0.01'),
	), $extra);
}

/** Service::create() for a Paymob form, the entry stored in the test db. */
function pmb_create($db, $args = array()) {
	$form = isset($args['form']) ? $args['form'] : pmb_form();
	unset($args['form']);
	$fid = $db->add_form($form, 'payment');
	$attempt = sprintf('%08x-%04x-4%03x-8%03x-%012x', mt_rand(), mt_rand(0, 65535), mt_rand(0, 4095), mt_rand(0, 4095), mt_rand());
	return \Emsfb\Payments\Service::create(array_merge(array(
		'form_id' => $fid, 'form' => $form, 'rows' => pmb_rows(), 'field_id' => 'pf', 'attempt_key' => $attempt, 'method' => 'card',
		'expected_minor' => null, 'page_url' => 'https://site.example/register/', 'locale' => 'ar', 'ip' => '203.0.113.7', 'payer_email' => 'payer@example.test',
		'description' => 'Workshop registration',
		'store_entry' => function ($rows) use ($db, $fid) {
			$id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'EFB' . mt_rand(10000, 99999)));
			return array('track' => $db->get_var('SELECT track FROM wp_emsfb_msg_ WHERE msg_id = ' . (int) $id), 'msg_id' => $id);
		},
	), $args));
}

/** The last intention request body, decoded. */
function pmb_last_intention() {
	$l = PmbFake::last('POST', '/v1/intention/');
	return $l ? $l['body'] : null;
}

/** A return request (the visitor back from Paymob) with an optional Paymob query. */
function pmb_return_req($p, $query = array(), $sig = null) {
	$r = new WP_REST_Request('GET');
	$r->set_param('gateway', 'paymob');
	$r->set_param('p', $p['uid']);
	$r->set_param('s', $sig !== null ? $sig : \Emsfb\Payments\Service::sign($p['uid'], 'paymob'));
	foreach ($query as $k => $v) $r->set_param($k, $v);
	return $r;
}
