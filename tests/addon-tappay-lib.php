<?php
/**
 * Not a test. Shared set-up of the Tap Payments add-on tests (tests/test-addon-tappay-*.php):
 *  - the PAY00 test database and WordPress stand-ins (pay-test-db.php);
 *  - the add-on from vendor/tappay (git-ignored symlink to vendores-efb/tappay), registered the way
 *    core loads it (routes-efb.php → efb_register_payment_gateways);
 *  - a fake api.tap.company behind pre_http_request, answering from the official examples in
 *    tests/fixtures/payments/tap/*.json (no network, no real key: the keys are assembled at run time
 *    from obviously fake parts);
 *  - an independent hashstring signer (number_format, not the add-on's string arithmetic).
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/pay-test-db.php';
if (!defined('EMSFB_PLUGIN_URL')) define('EMSFB_PLUGIN_URL', 'https://site.example/wp-content/plugins/easy-form-builder/');

function tap_addon_dir() {
	foreach (array(getenv('EFB_TAPPAY_DIR') ?: '', __DIR__ . '/../vendor/tappay', '/home/user/vendores-efb/tappay') as $d) {
		if ($d !== '' && is_readable($d . '/routes-efb.php')) return $d;
	}
	fwrite(STDERR, "Tap add-on not found (vendor/tappay symlink or EFB_TAPPAY_DIR)\n");
	exit(2);
}

/** Fake keys, never shaped like real ones in the source (push protection). */
function tap_key($mode) { return 'sk' . '_' . $mode . '_' . ($mode === 'live' ? 'UnitFakeLiveKey0002' : 'UnitFakeTestKey0001'); }

function tap_fixture($name) {
	$f = json_decode(file_get_contents(__DIR__ . '/fixtures/payments/tap/' . $name . '.json'), true);
	if (!is_array($f)) { fwrite(STDERR, "bad fixture $name\n"); exit(2); }
	return $f;
}

/** The fake Tap API. Charges and refunds live in static arrays; every request is recorded. */
class TapFake {
	public static $charges = array();
	public static $refunds = array();
	public static $requests = array();
	public static $idem = array();
	public static $down = false;
	public static $refund_status = 'REFUNDED';
	public static $create_status = 'INITIATED';
	private static $n = 0;

	public static function reset() {
		self::$charges = self::$refunds = self::$requests = self::$idem = array();
		self::$down = false; self::$refund_status = 'REFUNDED'; self::$create_status = 'INITIATED';
	}

	private static function answer($code, $body) {
		return array('response' => array('code' => $code, 'message' => ''), 'body' => is_string($body) ? $body : json_encode($body), 'headers' => array('content-type' => 'application/json'), 'cookies' => array(), 'filename' => null);
	}

	public static function id($prefix) {
		self::$n++;
		return $prefix . 'TS' . str_pad((string) self::$n, 4, '0', STR_PAD_LEFT) . substr(md5(uniqid('', true)), 0, 18);
	}

	public static function http($pre, $args, $url) {
		$host = parse_url($url, PHP_URL_HOST);
		if ($host !== 'api.tap.company') return $pre;
		$method = isset($args['method']) ? strtoupper($args['method']) : 'GET';
		$raw = isset($args['body']) && is_string($args['body']) ? $args['body'] : '';
		self::$requests[] = array('method' => $method, 'url' => $url, 'headers' => $args['headers'], 'body' => $raw);
		if (self::$down) return new WP_Error('http_request_failed', 'tap down');
		$auth = isset($args['headers']['Authorization']) ? substr($args['headers']['Authorization'], 7) : '';
		if ($auth !== tap_key('test') && $auth !== tap_key('live')) return self::answer(401, tap_fixture('error-401')['response']['body']);
		$live = $auth === tap_key('live');
		$path = parse_url($url, PHP_URL_PATH);
		$b = json_decode($raw, true);
		if ($method === 'POST' && $path === '/v2/charges/list') return self::answer(200, array('object' => 'list', 'charges' => array(), 'has_more' => false));
		if ($method === 'POST' && $path === '/v2/charges') {
			foreach (array('amount', 'currency', 'customer', 'source', 'redirect') as $k) {
				if (!isset($b[$k])) return self::answer(400, array('errors' => array(array('code' => '1104', 'description' => "$k is required"))));
			}
			if (empty($b['customer']['first_name']) || empty($b['customer']['email'])) return self::answer(400, array('errors' => array(array('code' => '1107', 'description' => 'customer first_name and email are required'))));
			// An address Tap's own check refuses (the tests use a "reject" mailbox for it).
			if (strpos($b['customer']['email'], 'reject') === 0) return self::answer(400, array('errors' => array(array('code' => '1108', 'description' => 'customer.email is invalid'))));
			$idem = isset($b['reference']['idempotent']) ? $b['reference']['idempotent'] : '';
			if ($idem !== '' && isset(self::$idem[$idem])) return self::answer(200, self::$charges[self::$idem[$idem]]);
			$c = tap_fixture('charge-create-200')['response']['body'];
			$c['id'] = self::id('chg_');
			$c['live_mode'] = $live;
			$c['status'] = self::$create_status;
			$c['amount'] = $b['amount'];
			$c['currency'] = $b['currency'];
			$c['transaction']['amount'] = $b['amount'];
			$c['transaction']['currency'] = $b['currency'];
			$c['transaction']['created'] = (string) (1762950000000 + self::$n);
			$c['transaction']['url'] = 'https://checkout.payments.tap.company?mode=page&token=' . substr(md5($c['id']), 0, 24);
			$c['metadata'] = isset($b['metadata']) ? $b['metadata'] : array();
			$c['reference'] = array_merge(isset($b['reference']) ? $b['reference'] : array(), array('track' => 'tck_' . substr(md5($c['id']), 0, 20)));
			$c['source'] = array('object' => 'source', 'id' => $b['source']['id']);
			$c['customer'] = $b['customer'];
			$c['redirect'] = array('status' => 'PENDING', 'url' => $b['redirect']['url']);
			$c['post'] = array('status' => 'PENDING', 'url' => isset($b['post']['url']) ? $b['post']['url'] : '');
			if (isset($b['merchant'])) $c['merchant'] = $b['merchant'];
			self::$charges[$c['id']] = $c;
			if ($idem !== '') self::$idem[$idem] = $c['id'];
			return self::answer(200, $c);
		}
		if ($method === 'GET' && preg_match('#^/v2/charges/(chg_\w+)$#', $path, $m)) {
			return isset(self::$charges[$m[1]]) ? self::answer(200, self::$charges[$m[1]]) : self::answer(404, array('errors' => array(array('code' => '1130', 'description' => 'Charge not found'))));
		}
		if ($method === 'POST' && $path === '/v2/refunds') {
			$idem = isset($b['reference']['idempotent']) ? 'r|' . $b['reference']['idempotent'] : '';
			if ($idem !== '' && isset(self::$idem[$idem])) return self::answer(200, self::$refunds[self::$idem[$idem]]);
			if (!isset(self::$charges[$b['charge_id']]) || self::$charges[$b['charge_id']]['status'] !== 'CAPTURED') return self::answer(400, array('errors' => array(array('code' => '1146', 'description' => 'Charge is not captured'))));
			$r = tap_fixture('refund-200')['response']['body'];
			$r['id'] = self::id('re_');
			$r['charge_id'] = $b['charge_id'];
			$r['amount'] = $b['amount'];
			$r['currency'] = $b['currency'];
			$r['status'] = self::$refund_status;
			$r['reference'] = array_merge($r['reference'], isset($b['reference']) ? $b['reference'] : array());
			$r['live_mode'] = $live;
			self::$refunds[$r['id']] = $r;
			if ($idem !== '') self::$idem[$idem] = $r['id'];
			return self::answer(200, $r);
		}
		return self::answer(404, array('errors' => array(array('code' => '404', 'description' => 'not found'))));
	}

	/** Tap's side of a payment on its hosted page. */
	public static function set($id, $status, $amount = null, $currency = null) {
		self::$charges[$id]['status'] = $status;
		if ($amount !== null) self::$charges[$id]['amount'] = $amount;
		if ($currency !== null) self::$charges[$id]['currency'] = $currency;
		if ($status === 'CAPTURED') {
			self::$charges[$id]['reference']['gateway'] = '1234567890';
			self::$charges[$id]['reference']['payment'] = '4327230736106619650';
			self::$charges[$id]['response'] = array('code' => '000', 'message' => 'Captured');
		}
	}

	public static function last($method, $path_part) {
		for ($i = count(self::$requests) - 1; $i >= 0; $i--) {
			if (self::$requests[$i]['method'] === $method && strpos(self::$requests[$i]['url'], $path_part) !== false) return self::$requests[$i];
		}
		return null;
	}
}

/** Independent hashstring (docs/webhook): number_format of the float at the documented decimals. */
function tap_indep_hash(array $o, $secret, $created_key = 'transaction') {
	$dec = array('AED' => 2, 'BHD' => 3, 'KWD' => 3, 'OMR' => 3, 'QAR' => 2, 'SAR' => 2, 'USD' => 2, 'EUR' => 2, 'GBP' => 2, 'EGP' => 2, 'JOD' => 3);
	$cur = $o['currency'];
	$created = $created_key === 'transaction' ? $o['transaction']['created'] : $o['created'];
	$s = 'x_id' . $o['id'] . 'x_amount' . number_format((float) $o['amount'], $dec[$cur], '.', '') . 'x_currency' . $cur
		. 'x_gateway_reference' . (isset($o['reference']['gateway']) ? $o['reference']['gateway'] : '')
		. 'x_payment_reference' . (isset($o['reference']['payment']) ? $o['reference']['payment'] : '')
		. 'x_status' . $o['status'] . 'x_created' . $created;
	return hash_hmac('sha256', $s, $secret);
}

/** A webhook request as Tap posts it (raw JSON body + hashstring header). */
function tap_webhook_req(array $o, $secret = null, $hash = null, $created_key = 'transaction') {
	$r = new WP_REST_Request('POST');
	$r->set_param('gateway', 'tappay');
	$r->set_body(json_encode($o, JSON_UNESCAPED_SLASHES));
	$r->set_header('hashstring', $hash !== null ? $hash : tap_indep_hash($o, $secret !== null ? $secret : tap_key(!empty($o['live_mode']) ? 'live' : 'test'), $created_key));
	return $r;
}

/** The payment environment with the Tap add-on registered and test + live keys saved. */
function tap_env($mode = 'test', $extra_settings = array()) {
	require_once __DIR__ . '/../includes/class-Emsfb-builder-api.php';
	efb_pay_test_load();
	require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-frontend.php';
	$GLOBALS['opts'] = array('emsfb_pro' => 3);
	$GLOBALS['efb_transients'] = $GLOBALS['efb_mails'] = $GLOBALS['efb_http_log'] = $GLOBALS['efb_filters'] = $GLOBALS['efb_hooks'] = array();
	$db = $GLOBALS['wpdb'] = new EfbPayTestDb();
	$db->create_payment_tables();
	\Emsfb\Payments\Registry::reset();
	TapFake::reset();
	add_filter('pre_http_request', array('TapFake', 'http'), 10, 3);
	require tap_addon_dir() . '/routes-efb.php';
	$s = \Emsfb\Payments\Settings::load('tappay');
	$s->apply((new \Emsfb\Tappay\Tap_Gateway())->settings_schema(), array_merge(array('secret_key__test' => tap_key('test'), 'secret_key__live' => tap_key('live')), $extra_settings), $mode);
	return $db;
}

/** A KWD form: name, email, a priced radio (12.5 / 0.75), the Tap field (fixed 0.125 fee), donation and computed variants. */
function tap_form($currency = 'kwd', $field = array()) {
	return array(
		array('type' => 'payment', 'getway' => 'tappay', 'currency' => $currency, 'trackingCode' => '1', 'email' => 'owner@example.test', 'email_to' => 'em'),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'nm', 'type' => 'text', 'name' => 'Full name', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
		array('id_' => 'rad', 'type' => 'payRadio', 'name' => 'Course', 'step' => 1),
		array('id_' => 'ro1', 'parent' => 'rad', 'type' => 'option', 'value' => 'Full course', 'price' => '12.5'),
		array('id_' => 'ro2', 'parent' => 'rad', 'type' => 'option', 'value' => 'Trial', 'price' => '0.75'),
		array_merge(array('id_' => 'pf', 'type' => 'tappay', 'name' => 'Pay', 'step' => 1, 'pf_fixed' => '0.125', 'pf_fixed_label' => 'Fee'), $field),
	);
}

function tap_rows($option = 'ro1', $extra = array()) {
	return array_merge(array(
		array('id_' => 'nm', 'type' => 'text', 'name' => 'Full name', 'value' => 'Fatima Al Sabah'),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'value' => 'payer@example.test'),
		array('id_' => 'rad', 'id_ob' => $option, 'type' => 'payRadio', 'value' => 'x', 'price' => '0.001'),
	), $extra);
}

/** Service::create() for a Tap form, the entry stored in the test db. */
function tap_create($db, $args = array()) {
	$form = isset($args['form']) ? $args['form'] : tap_form();
	unset($args['form']);
	$fid = $db->add_form($form, 'payment');
	$attempt = sprintf('%08x-%04x-4%03x-8%03x-%012x', mt_rand(), mt_rand(0, 65535), mt_rand(0, 4095), mt_rand(0, 4095), mt_rand());
	return \Emsfb\Payments\Service::create(array_merge(array(
		'form_id' => $fid, 'form' => $form, 'rows' => tap_rows(), 'field_id' => 'pf', 'attempt_key' => $attempt, 'method' => 'knet',
		'expected_minor' => null, 'page_url' => 'https://site.example/register/', 'locale' => 'ar', 'ip' => '203.0.113.7', 'payer_email' => 'payer@example.test',
		'description' => 'Course registration',
		'store_entry' => function ($rows) use ($db, $fid) {
			$id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'EFB' . mt_rand(10000, 99999)));
			return array('track' => $db->get_var('SELECT track FROM wp_emsfb_msg_ WHERE msg_id = ' . (int) $id), 'msg_id' => $id);
		},
	), $args));
}

/** The JSON body of the last charge request, decoded, plus its raw text. */
function tap_last_charge_body() {
	$r = TapFake::last('POST', '/v2/charges');
	return array(json_decode($r['body'], true), $r['body'], $r);
}
