<?php
/**
 * Not a test. Shared set-up of the PayTabs add-on tests (tests/test-addon-paytabs-*.php):
 *  - the PAY00 test database and WordPress stand-ins (pay-test-db.php);
 *  - the add-on from vendor/paytabs (git-ignored symlink to vendores-efb/paytabs), registered the
 *    way core loads it (routes-efb.php → efb_register_payment_gateways);
 *  - a fake PayTabs on the seven regional hosts behind pre_http_request, answering in the shape of
 *    the official examples in tests/fixtures/payments/paytabs/*.json. Each fake profile lives in
 *    one region: a key sent to another region's host gets the authentication error, as at PayTabs.
 *    No network, no real key or profile (fake keys are assembled at run time);
 *  - independent signers: the callback HMAC of the raw body, and the return fields HMAC written
 *    after PayTabs' PHP sample (array_filter, ksort, http_build_query).
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/pay-test-db.php';
if (!defined('EMSFB_PLUGIN_URL')) define('EMSFB_PLUGIN_URL', 'https://site.example/wp-content/plugins/easy-form-builder/');

function ptb_addon_dir() {
	foreach (array(getenv('EFB_PAYTABS_DIR') ?: '', __DIR__ . '/../vendor/paytabs', '/home/user/vendores-efb/paytabs') as $d) {
		if ($d !== '' && is_readable($d . '/routes-efb.php')) return $d;
	}
	fwrite(STDERR, "PayTabs add-on not found (vendor/paytabs symlink or EFB_PAYTABS_DIR)\n");
	exit(2);
}

/** Fake server keys, never a real one (assembled here). Test profile 10001 in sa, Live 10002 in ae. */
function ptb_key($mode) { return 'S' . 'UNITFAKE' . ($mode === 'live' ? 'LV' : 'TS') . '-' . 'NOTAREALKEY' . '-' . ($mode === 'live' ? 'LIVEPROFIL' : 'TESTPROFIL'); }
function ptb_profile($mode) { return $mode === 'live' ? '10002' : '10001'; }
function ptb_region($mode) { return $mode === 'live' ? 'ae' : 'sa'; }

function ptb_fixture($name) {
	$f = json_decode(file_get_contents(__DIR__ . '/fixtures/payments/paytabs/' . $name . '.json'), true);
	if (!is_array($f)) { fwrite(STDERR, "bad fixture $name\n"); exit(2); }
	return $f;
}

/** The fake PayTabs. Transactions and refunds live in static arrays; every request is recorded. */
class PtbFake {
	const HOSTS = array('sa' => 'secure.paytabs.sa', 'ae' => 'secure.paytabs.com', 'eg' => 'secure-egypt.paytabs.com', 'om' => 'secure-oman.paytabs.com', 'jo' => 'secure-jordan.paytabs.com', 'iq' => 'secure-iraq.paytabs.com', 'global' => 'secure-global.paytabs.com');
	public static $tx = array();
	public static $requests = array();
	public static $down = false;
	/** 'notfound' (an opened, unpaid page answers 400 "not found") or 'noresult' (200 without payment_result). */
	public static $unpaid = 'notfound';
	public static $refund_status = 'A';
	/** Answer of payment/request to tamper with (e.g. another redirect host). */
	public static $tamper = null;
	private static $n = 0;

	public static function reset() {
		self::$tx = self::$requests = array();
		self::$down = false; self::$unpaid = 'notfound'; self::$refund_status = 'A'; self::$tamper = null;
	}

	private static function answer($code, $body) {
		return array('response' => array('code' => $code, 'message' => ''), 'body' => is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES), 'headers' => array('content-type' => 'application/json'), 'cookies' => array(), 'filename' => null);
	}

	public static function ref($live) {
		self::$n++;
		return ($live ? 'SFT' : 'TST') . '22316' . str_pad((string) self::$n, 8, '0', STR_PAD_LEFT);
	}

	private static function auth_error() { return self::answer(401, ptb_fixture('error-auth')['response']['body']); }

	public static function http($pre, $args, $url) {
		$host = parse_url($url, PHP_URL_HOST);
		$region = array_search($host, self::HOSTS, true);
		if ($region === false) return $pre;
		$method = isset($args['method']) ? strtoupper($args['method']) : 'GET';
		$raw = isset($args['body']) && is_string($args['body']) ? $args['body'] : '';
		self::$requests[] = array('method' => $method, 'url' => $url, 'host' => $host, 'headers' => $args['headers'], 'body' => $raw);
		if (self::$down) return new WP_Error('http_request_failed', 'paytabs down');
		$auth = isset($args['headers']['authorization']) ? $args['headers']['authorization'] : '';
		$mode = $auth === ptb_key('test') ? 'test' : ($auth === ptb_key('live') ? 'live' : '');
		$b = json_decode($raw, true);
		// A key works only on its profile's regional host and with its own profile id.
		if ($mode === '' || ptb_region($mode) !== $region) return self::auth_error();
		if (!is_array($b) || (string) ($b['profile_id'] ?? '') !== ptb_profile($mode)) return self::answer(400, array('code' => 4, 'message' => 'Profile ID is invalid', 'trace' => 'PMNT0401.00000000.00000004'));
		$path = parse_url($url, PHP_URL_PATH);
		if ($method === 'POST' && $path === '/payment/query') {
			$ref = (string) ($b['tran_ref'] ?? '');
			if (!isset(self::$tx[$ref]) || (self::$tx[$ref]['_status'] === '' && self::$unpaid === 'notfound')) return self::answer(400, ptb_fixture('error-not-found')['response']['body']);
			return self::answer(200, self::tx_body($ref));
		}
		if ($method === 'POST' && $path === '/payment/request') {
			foreach (array('tran_type', 'tran_class', 'cart_id', 'cart_currency', 'cart_amount', 'cart_description') as $k) {
				if (!isset($b[$k])) return self::answer(400, array('code' => 113, 'message' => "$k is required", 'trace' => 'PMNT'));
			}
			if ($b['tran_type'] === 'refund') return self::refund($b, $mode, $raw);
			$r = ptb_fixture('request-200')['response']['body'];
			$ref = self::ref($mode === 'live');
			$amount = self::raw_amount($raw);
			$r['tran_ref'] = $ref;
			$r['cart_id'] = $b['cart_id'];
			$r['cart_description'] = $b['cart_description'];
			$r['cart_currency'] = $b['cart_currency'];
			$r['cart_amount'] = $amount;
			$r['tran_currency'] = $b['cart_currency'];
			$r['tran_total'] = $amount;
			$r['callback'] = $b['callback'] ?? '';
			$r['return'] = $b['return'] ?? '';
			$r['profileId'] = (int) ptb_profile($mode);
			$r['redirect_url'] = 'https://' . $host . '/payment/page/' . strtoupper(substr(md5($ref), 0, 24)) . (!empty($b['framed']) ? '?framed=1' : '');
			self::$tx[$ref] = array('_status' => '', '_mode' => $mode, '_request' => $b, '_amount' => $amount, 'tran_ref' => $ref, 'tran_type' => 'Sale', 'cart_id' => $b['cart_id'], 'cart_description' => $b['cart_description'], 'cart_currency' => $b['cart_currency'], 'cart_amount' => $amount);
			if (is_callable(self::$tamper)) $r = call_user_func(self::$tamper, $r);
			return self::answer(200, $r);
		}
		return self::answer(404, array('code' => 404, 'message' => 'not found', 'trace' => 'PMNT'));
	}

	/** cart_amount exactly as sent (the JSON number's text, not a float). */
	public static function raw_amount($raw) {
		return preg_match('/"cart_amount":(-?[0-9.]+)/', $raw, $m) ? $m[1] : '';
	}

	private static function refund($b, $mode, $raw) {
		$orig = (string) ($b['tran_ref'] ?? '');
		if (!isset(self::$tx[$orig]) || self::$tx[$orig]['_status'] !== 'A') return self::answer(400, array('code' => 207, 'message' => 'Transaction is not refundable', 'trace' => 'PMNT'));
		$r = ptb_fixture('refund-A')['response']['body'];
		$ref = self::ref($mode === 'live');
		$r['tran_ref'] = $ref;
		$r['cart_id'] = $b['cart_id'];
		$r['cart_description'] = $b['cart_description'];
		$r['cart_currency'] = $b['cart_currency'];
		$r['cart_amount'] = self::raw_amount($raw);
		$r['previous_tran_ref'] = $orig;
		$r['payment_result']['response_status'] = self::$refund_status;
		if (self::$refund_status === 'D') $r['payment_result']['response_message'] = 'Refund declined';
		self::$tx[$ref] = array_merge($r, array('_status' => self::$refund_status, '_mode' => $mode, '_request' => $b, '_amount' => $r['cart_amount'], 'tran_type' => 'Refund'));
		return self::answer(200, $r);
	}

	/** The transaction as the query (and the callback) shows it. */
	public static function tx_body($ref) {
		$t = self::$tx[$ref];
		$b = ptb_fixture('query-A')['response']['body'];
		$b['tran_ref'] = $ref;
		$b['tran_type'] = $t['tran_type'];
		$b['cart_id'] = $t['cart_id'];
		$b['cart_description'] = $t['cart_description'];
		$b['cart_currency'] = $t['cart_currency'];
		$b['cart_amount'] = $t['cart_amount'];
		$b['profileId'] = (int) ptb_profile($t['_mode']);
		if ($t['_status'] === '') { unset($b['payment_result'], $b['payment_info']); return $b; }
		$b['payment_result']['response_status'] = $t['_status'];
		$b['payment_result']['response_message'] = array('A' => 'Authorised', 'D' => 'Declined', 'E' => 'Error', 'H' => 'Authorised (hold)', 'C' => 'Cancelled', 'V' => 'Voided', 'X' => 'Expired', 'P' => 'Pending')[$t['_status']] ?? 'x';
		if (isset($t['_method'])) $b['payment_info']['payment_method'] = $t['_method'];
		return $b;
	}

	/** PayTabs' side of a payment on its page: status, and optionally what it really charged. */
	public static function set($ref, $status, $amount = null, $currency = null, $cart_id = null) {
		self::$tx[$ref]['_status'] = $status;
		if ($amount !== null) self::$tx[$ref]['cart_amount'] = $amount;
		if ($currency !== null) self::$tx[$ref]['cart_currency'] = $currency;
		if ($cart_id !== null) self::$tx[$ref]['cart_id'] = $cart_id;
	}

	public static function last($path_part) {
		for ($i = count(self::$requests) - 1; $i >= 0; $i--) {
			if (strpos(self::$requests[$i]['url'], $path_part) !== false) return self::$requests[$i];
		}
		return null;
	}

	public static function count($path_part) {
		return count(array_filter(self::$requests, function ($r) use ($path_part) { return strpos($r['url'], $path_part) !== false; }));
	}
}

/** A callback as PayTabs posts it: raw JSON body + "Signature" (independent hash_hmac of the body). */
function ptb_callback_req($ref, $key = null, $sig = null, $body = null) {
	$raw = $body !== null ? $body : json_encode(PtbFake::tx_body($ref), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	$r = new WP_REST_Request('POST');
	$r->set_param('gateway', 'paytabs');
	$r->set_body($raw);
	$r->set_header('Signature', $sig !== null ? $sig : hash_hmac('sha256', $raw, $key !== null ? $key : ptb_key(PtbFake::$tx[$ref]['_mode'] ?? 'test')));
	$r->set_header('Content-Type', 'application/json');
	return $r;
}

/** The browser POST of the return, signed after PayTabs' PHP sample (array_filter, ksort, http_build_query). */
function ptb_return_fields($ref, $uid, $status = 'A', $key = null) {
	$f = array('acquirerMessage' => '', 'acquirerRRN' => '', 'cartId' => $uid, 'customerEmail' => 'payer@example.test', 'respCode' => 'G96376', 'respMessage' => 'Authorised', 'respStatus' => $status, 'token' => '', 'tranRef' => $ref);
	$s = $f;
	$s = array_filter($s);
	ksort($s);
	$f['signature'] = hash_hmac('sha256', http_build_query($s), $key !== null ? $key : ptb_key('test'));
	return $f;
}

/** The payment environment with the PayTabs add-on registered and Test + Live settings saved. */
function ptb_env($mode = 'test', $extra_settings = array()) {
	require_once __DIR__ . '/../includes/class-Emsfb-builder-api.php';
	efb_pay_test_load();
	require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-frontend.php';
	$GLOBALS['opts'] = array('emsfb_pro' => 3);
	$GLOBALS['efb_transients'] = $GLOBALS['efb_mails'] = $GLOBALS['efb_http_log'] = $GLOBALS['efb_filters'] = $GLOBALS['efb_hooks'] = array();
	$db = $GLOBALS['wpdb'] = new EfbPayTestDb();
	$db->create_payment_tables();
	\Emsfb\Payments\Registry::reset();
	PtbFake::reset();
	$_POST = array();
	add_filter('pre_http_request', array('PtbFake', 'http'), 10, 3);
	require ptb_addon_dir() . '/routes-efb.php';
	ptb_settings($mode, $extra_settings);
	return $db;
}

function ptb_settings($mode = 'test', $extra = array()) {
	$s = \Emsfb\Payments\Settings::load('paytabs');
	return $s->apply((new \Emsfb\Paytabs\Paytabs_Gateway())->settings_schema(), array_merge(array(
		'region__test' => ptb_region('test'), 'profile_id__test' => ptb_profile('test'), 'server_key__test' => ptb_key('test'),
		'region__live' => ptb_region('live'), 'profile_id__live' => ptb_profile('live'), 'server_key__live' => ptb_key('live'),
	), $extra), $mode);
}

/** A SAR form: name, email, a priced radio (150 / 20.75), the PayTabs field (fixed 50.00 fee). */
function ptb_form($currency = 'sar', $field = array()) {
	return array(
		array('type' => 'payment', 'getway' => 'paytabs', 'currency' => $currency, 'trackingCode' => '1', 'email' => 'owner@example.test', 'email_to' => 'em'),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'nm', 'type' => 'text', 'name' => 'الاسم الكامل', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
		array('id_' => 'rad', 'type' => 'payRadio', 'name' => 'Workshop', 'step' => 1),
		array('id_' => 'ro1', 'parent' => 'rad', 'type' => 'option', 'value' => 'Full day', 'price' => '150'),
		array('id_' => 'ro2', 'parent' => 'rad', 'type' => 'option', 'value' => 'Half day', 'price' => '20.75'),
		array_merge(array('id_' => 'pf', 'type' => 'paytabs', 'name' => 'Pay', 'step' => 1, 'pf_fixed' => '50', 'pf_fixed_label' => 'Fee'), $field),
	);
}

function ptb_rows($option = 'ro1', $extra = array()) {
	return array_merge(array(
		array('id_' => 'nm', 'type' => 'text', 'name' => 'الاسم الكامل', 'value' => 'فاطمة العتيبي'),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'value' => 'payer@example.test'),
		array('id_' => 'rad', 'id_ob' => $option, 'type' => 'payRadio', 'value' => 'x', 'price' => '0.01'),
	), $extra);
}

/** Service::create() for a PayTabs form, the entry stored in the test db. */
function ptb_create($db, $args = array()) {
	$form = isset($args['form']) ? $args['form'] : ptb_form();
	unset($args['form']);
	$fid = $db->add_form($form, 'payment');
	$attempt = sprintf('%08x-%04x-4%03x-8%03x-%012x', mt_rand(), mt_rand(0, 65535), mt_rand(0, 4095), mt_rand(0, 4095), mt_rand());
	return \Emsfb\Payments\Service::create(array_merge(array(
		'form_id' => $fid, 'form' => $form, 'rows' => ptb_rows(), 'field_id' => 'pf', 'attempt_key' => $attempt, 'method' => '',
		'expected_minor' => null, 'page_url' => 'https://site.example/register/', 'locale' => 'ar', 'ip' => '203.0.113.7', 'payer_email' => 'payer@example.test',
		'description' => 'Workshop registration',
		'store_entry' => function ($rows) use ($db, $fid) {
			$id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'EFB' . mt_rand(10000, 99999)));
			return array('track' => $db->get_var('SELECT track FROM wp_emsfb_msg_ WHERE msg_id = ' . (int) $id), 'msg_id' => $id);
		},
	), $args));
}

/** The JSON body of the last payment/request, decoded, plus its raw text and the request. */
function ptb_last_request() {
	$r = PtbFake::last('/payment/request');
	return array(json_decode($r['body'], true), $r['body'], $r);
}
