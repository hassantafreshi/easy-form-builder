<?php
/**
 * PayPal add-on (vendores-efb paypal): a refund always uses PayPal's CAPTURE id, never the ORDER id.
 *
 * BUG: PaypalPayment::ajax_refund_payment() did `$captureId = $row->capture_id ?: $row->transaction_id;`. Rows written
 * by older builds keep status "pending" and an empty capture_id; their transaction_id is the ORDER id, so the refund
 * was sent to /v2/payments/captures/{ORDER id}/refund. Now, for a one-time row without a capture id:
 *  1. GET /v2/checkout/orders/{transaction_id};
 *  2. COMPLETED → purchase_units[0].payments.captures[0].id is saved on the row with status "completed" through
 *     PaypalPayment::update_payment(), then the refund goes to that capture;
 *  3. an order that was never captured (APPROVED, CREATED …) or is unknown answers a clear error and no refund is sent.
 * Also checked: the capture endpoint (PaypalHandler::handle_capture) stores the capture id and "completed" on the row.
 *
 * Plain PHP with stubs: no WordPress, no network. Every PayPal call is answered by a local router and recorded.
 * Add-on source: EFB_PAYPAL_DIR, vendor/paypal (git-ignored symlink), or /home/user/vendores-efb/paypal.
 * Run: php tests/test-addon-paypal-refund-capture.php
 */
namespace {

$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . json_encode($expected) . ', got ' . json_encode($actual) . "\n"; $failed++;
}

$dir = null;
foreach (array(getenv('EFB_PAYPAL_DIR') ?: '', __DIR__ . '/../vendor/paypal', '/home/user/vendores-efb/paypal') as $d) {
	if ($d !== '' && is_readable($d . '/class-Emsfb-paypal-payment.php')) { $dir = realpath($d); break; }
}
if (!$dir) { echo "[SKIP] the PayPal add-on source was not found\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

/* EMSFB_PLUGIN_DIRECTORY . '/vendor/paypal' must be the add-on itself (the refund requires the API helper from there). */
$root = sys_get_temp_dir() . '/efb-paypal-refund-' . getmypid();
@mkdir($root . '/vendor', 0777, true);
@unlink($root . '/vendor/paypal');
symlink($dir, $root . '/vendor/paypal');
register_shutdown_function(function () use ($root) { @unlink($root . '/vendor/paypal'); @rmdir($root . '/vendor'); @rmdir($root); });

define('ABSPATH', $root . '/');
define('EMSFB_PLUGIN_DIRECTORY', $root);

/* ---------------------------------------------------------------- WordPress stubs */
class EfbJsonResponse extends \Exception { public $ok; public $data; public function __construct($ok, $data) { parent::__construct('json'); $this->ok = $ok; $this->data = $data; } }
class WP_Error { public $m; public function __construct($c = '', $m = '') { $this->m = $m; } public function get_error_message() { return $this->m; } }
class WP_REST_Request { private $p; public function __construct($p) { $this->p = $p; } public function get_json_params() { return $this->p; } }

function __($s, $d = null) { return $s; }
function esc_html__($s, $d = null) { return $s; }
function sanitize_text_field($s) { return trim((string) $s); }
function wp_date($f) { return gmdate($f); }
function wp_json_encode($v) { return json_encode($v); }
function wp_parse_args($a, $d) { return array_merge($d, $a); }
function add_action() {}
function current_user_can($c) { return true; }
function check_ajax_referer() { return true; }
function is_wp_error($v) { return $v instanceof WP_Error; }
function wp_send_json_success($d = null, $code = null) { throw new EfbJsonResponse(true, $d); }
function wp_send_json_error($d = null, $code = null) { throw new EfbJsonResponse(false, $d); }
function get_setting_Emsfb($as = 'raw') {
	$s = array('paypalPKey' => 'CLIENT-ID-PLACEHOLDER', 'paypalSKey' => 'SECRET-PLACEHOLDER');
	return $as === 'decoded' ? (object) $s : json_encode($s);
}

/* PayPal, answered locally. $GLOBALS['pp_routes'] maps "METHOD path" → [code, body]; every call is recorded. */
$GLOBALS['pp_calls'] = array();
$GLOBALS['pp_routes'] = array();
function efb_pp_answer($method, $url) {
	$path = preg_replace('#^https://api-m(\.sandbox)?\.paypal\.com/#', '', $url);
	$GLOBALS['pp_calls'][] = $method . ' ' . $path;
	if ($path === 'v1/oauth2/token') return array('code' => 200, 'body' => json_encode(array('access_token' => 'TOKEN-PLACEHOLDER')));
	$key = $method . ' ' . $path;
	if (!isset($GLOBALS['pp_routes'][$key])) return array('code' => 404, 'body' => json_encode(array('name' => 'RESOURCE_NOT_FOUND')));
	return $GLOBALS['pp_routes'][$key];
}
function wp_remote_get($url, $args = array()) { return efb_pp_answer('GET', $url); }
function wp_remote_post($url, $args = array()) { return efb_pp_answer('POST', $url); }
function wp_remote_retrieve_response_code($r) { return (int) $r['code']; }
function wp_remote_retrieve_body($r) { return (string) $r['body']; }

/* A small $wpdb holding the emsfb_pay_ rows. */
class EfbFakeWpdb {
	public $prefix = 'wp_';
	public $rows = array();
	public $updates = array();
	public function prepare($sql, ...$args) {
		if (count($args) === 1 && is_array($args[0])) $args = $args[0];
		foreach ($args as $a) { $sql = preg_replace('/%[ds]/', is_int($a) ? (string) $a : "'" . addslashes((string) $a) . "'", $sql, 1); }
		return $sql;
	}
	public function get_row($sql) {
		foreach ($this->rows as $r) {
			if (preg_match('/WHERE\s+id\s*=\s*(\d+)/', $sql, $m) && (int) $r['id'] === (int) $m[1]) return (object) $r;
			if (preg_match("/transaction_id\s*=\s*'([^']*)'/", $sql, $m) && $r['transaction_id'] === $m[1]) return (object) $r;
		}
		return null;
	}
	public function update($table, $data, $where) {
		$this->updates[] = array($table, $data, $where);
		foreach ($this->rows as $i => $r) {
			if ((int) $r['id'] === (int) $where['id']) { $this->rows[$i] = array_merge($r, $data); return 1; }
		}
		return 0;
	}
	public function row($id) { foreach ($this->rows as $r) if ((int) $r['id'] === (int) $id) return $r; return null; }
}
$GLOBALS['wpdb'] = new EfbFakeWpdb();

function efb_pp_row($over) {
	return array_merge(array(
		'id' => 1, 'form_id' => 7, 'track' => 'EFB-TEST', 'gateway' => 'paypal', 'payment_type' => 'one-time',
		'transaction_id' => '', 'subscription_id' => '', 'plan_id' => '', 'product_id' => '', 'capture_id' => '',
		'amount' => '25.00', 'currency' => 'USD', 'status' => 'pending', 'meta' => '', 'payer_email' => '', 'payer_name' => '',
	), $over);
}
function efb_pp_order($order_id, $status, $capture_id = null) {
	$o = array('id' => $order_id, 'status' => $status, 'purchase_units' => array(array('payments' => array())));
	if ($capture_id !== null) $o['purchase_units'][0]['payments']['captures'] = array(array('id' => $capture_id, 'status' => 'COMPLETED'));
	return array('code' => 200, 'body' => json_encode($o));
}

/* ---------------------------------------------------------------- the add-on */
class_alias('WP_Error', 'Emsfb\WP_Error');
class_alias('WP_REST_Request', 'Emsfb\WP_REST_Request');
class Payment_Core_Present {} // stands in for core's \Emsfb\Payment below

} // namespace

namespace Emsfb { class Payment {} }

namespace {

require_once $dir . '/class-Emsfb-paypal.php';
require_once $dir . '/class-Emsfb-paypal-payment.php';
require_once $dir . '/class-Emsfb-paypal-handler.php';

function efb_pp_payment_page() {
	$rc = new \ReflectionClass('Emsfb\PaypalPayment');
	$o = $rc->newInstanceWithoutConstructor();
	foreach (array('db' => $GLOBALS['wpdb'], 'table_name' => 'wp_emsfb_pay_') as $k => $v) { $p = $rc->getProperty($k); $p->setAccessible(true); $p->setValue($o, $v); }
	return $o;
}
function efb_pp_refund($id) {
	$_POST = array('id' => $id, 'nonce' => 'x');
	$GLOBALS['pp_calls'] = array();
	try { efb_pp_payment_page()->ajax_refund_payment(); } catch (EfbJsonResponse $r) { return $r; }
	return null;
}
function efb_pp_refund_calls() { return array_values(array_filter($GLOBALS['pp_calls'], function ($c) { return strpos($c, '/refund') !== false; })); }

/* --- 1. Capture updates the row ------------------------------------------------------------------------------ */
$GLOBALS['wpdb']->rows = array(efb_pp_row(array('id' => 11, 'transaction_id' => 'ORDER11AAAA')));
$GLOBALS['pp_routes'] = array('POST v2/checkout/orders/ORDER11AAAA/capture' => efb_pp_order('ORDER11AAAA', 'COMPLETED', 'CAPTURE11BBBB'));
$h = new \Emsfb\PaypalHandler((object) array('paypalPKey' => 'CLIENT-ID-PLACEHOLDER', 'paypalSKey' => 'SECRET-PLACEHOLDER'));
$res = null;
try { $h->handle_capture(new WP_REST_Request(array('orderID' => 'ORDER11AAAA'))); } catch (EfbJsonResponse $r) { $res = $r; }
t('capture: the handler answers success', $res && $res->ok && !empty($res->data['success']), true);
t('capture: it reports the capture id, not the order id', $res ? ($res->data['captureID'] ?? null) : null, 'CAPTURE11BBBB');
$row = $GLOBALS['wpdb']->row(11);
t('capture: the row gets the capture id', $row['capture_id'], 'CAPTURE11BBBB');
t('capture: the row is completed', $row['status'], 'completed');
t('capture: the order id stays in transaction_id', $row['transaction_id'], 'ORDER11AAAA');

/* --- 2. Old row (pending, empty capture_id, one-time), order COMPLETED -------------------------------------- */
$GLOBALS['wpdb']->rows = array(efb_pp_row(array('id' => 21, 'transaction_id' => 'ORDER21AAAA')));
$GLOBALS['wpdb']->updates = array();
$GLOBALS['pp_routes'] = array(
	'GET v2/checkout/orders/ORDER21AAAA' => efb_pp_order('ORDER21AAAA', 'COMPLETED', 'CAPTURE21BBBB'),
	'POST v2/payments/captures/CAPTURE21BBBB/refund' => array('code' => 201, 'body' => json_encode(array('id' => 'REFUND21CCCC', 'status' => 'COMPLETED'))),
);
$res = efb_pp_refund(21);
t('old row: PayPal is asked for the order first', in_array('GET v2/checkout/orders/ORDER21AAAA', $GLOBALS['pp_calls'], true), true);
$get_at = array_search('GET v2/checkout/orders/ORDER21AAAA', $GLOBALS['pp_calls'], true);
$ref_at = array_search('POST v2/payments/captures/CAPTURE21BBBB/refund', $GLOBALS['pp_calls'], true);
t('old row: the order is read before the refund', $get_at !== false && $ref_at !== false && $get_at < $ref_at, true);
t('old row: exactly one refund call, to the capture id', efb_pp_refund_calls(), array('POST v2/payments/captures/CAPTURE21BBBB/refund'));
t('old row: the order id is never used as a capture id', in_array('POST v2/payments/captures/ORDER21AAAA/refund', $GLOBALS['pp_calls'], true), false);
$first = $GLOBALS['wpdb']->updates[0] ?? array(null, array());
t('old row: the capture id is saved first, with status completed (update_payment)', array($first[1]['capture_id'] ?? null, $first[1]['status'] ?? null, $first[2]['id'] ?? null), array('CAPTURE21BBBB', 'completed', 21));
t('old row: update_payment stamps updated_at', isset($first[1]['updated_at']), true);
t('old row: the refund answers success with the refund id', $res && $res->ok ? ($res->data['refund_id'] ?? null) : null, 'REFUND21CCCC');
$row = $GLOBALS['wpdb']->row(21);
t('old row: after the refund the row is refunded and keeps the capture id', array($row['status'], $row['capture_id']), array('refunded', 'CAPTURE21BBBB'));
t('old row: the refund id is in the row meta', json_decode($row['meta'], true)['refund_id'] ?? null, 'REFUND21CCCC');

/* the same, for a row an older build marked completed without a capture id */
$GLOBALS['wpdb']->rows = array(efb_pp_row(array('id' => 22, 'transaction_id' => 'ORDER22AAAA', 'status' => 'completed')));
$GLOBALS['pp_routes'] = array(
	'GET v2/checkout/orders/ORDER22AAAA' => efb_pp_order('ORDER22AAAA', 'COMPLETED', 'CAPTURE22BBBB'),
	'POST v2/payments/captures/CAPTURE22BBBB/refund' => array('code' => 201, 'body' => json_encode(array('id' => 'REFUND22CCCC', 'status' => 'COMPLETED'))),
);
$res = efb_pp_refund(22);
t('completed row without a capture id: refunded through the looked-up capture', array($res && $res->ok, efb_pp_refund_calls()), array(true, array('POST v2/payments/captures/CAPTURE22BBBB/refund')));

/* an older build stored the order id AS the capture id */
$GLOBALS['wpdb']->rows = array(efb_pp_row(array('id' => 23, 'transaction_id' => 'ORDER23AAAA', 'capture_id' => 'ORDER23AAAA')));
$GLOBALS['pp_routes'] = array(
	'GET v2/checkout/orders/ORDER23AAAA' => efb_pp_order('ORDER23AAAA', 'COMPLETED', 'CAPTURE23BBBB'),
	'POST v2/payments/captures/CAPTURE23BBBB/refund' => array('code' => 201, 'body' => json_encode(array('id' => 'REFUND23CCCC', 'status' => 'COMPLETED'))),
);
$res = efb_pp_refund(23);
t('capture_id equal to the order id is not trusted: the real capture is refunded', array($res && $res->ok, efb_pp_refund_calls(), $GLOBALS['wpdb']->row(23)['capture_id']), array(true, array('POST v2/payments/captures/CAPTURE23BBBB/refund'), 'CAPTURE23BBBB'));

/* a row that already has its capture id: no lookup */
$GLOBALS['wpdb']->rows = array(efb_pp_row(array('id' => 24, 'transaction_id' => 'ORDER24AAAA', 'capture_id' => 'CAPTURE24BBBB', 'status' => 'completed')));
$GLOBALS['pp_routes'] = array('POST v2/payments/captures/CAPTURE24BBBB/refund' => array('code' => 201, 'body' => json_encode(array('id' => 'REFUND24CCCC', 'status' => 'COMPLETED'))));
$res = efb_pp_refund(24);
t('row with a capture id: refunded directly, the order is not read', array($res && $res->ok, in_array('GET v2/checkout/orders/ORDER24AAAA', $GLOBALS['pp_calls'], true)), array(true, false));

/* --- 3. Uncaptured order: clear error, no refund, row untouched ---------------------------------------------- */
foreach (array('APPROVED', 'CREATED', 'VOIDED') as $i => $st) {
	$id = 31 + $i;
	$GLOBALS['wpdb']->rows = array(efb_pp_row(array('id' => $id, 'transaction_id' => 'ORDER' . $id . 'AAAA')));
	$GLOBALS['wpdb']->updates = array();
	$GLOBALS['pp_routes'] = array('GET v2/checkout/orders/ORDER' . $id . 'AAAA' => efb_pp_order('ORDER' . $id . 'AAAA', $st));
	$res = efb_pp_refund($id);
	t("uncaptured ({$st}): an error, not success", $res ? $res->ok : null, false);
	t("uncaptured ({$st}): the message says it was never captured and names the status", $res && is_string($res->data) && strpos($res->data, 'never captured') !== false && strpos($res->data, $st) !== false, true);
	t("uncaptured ({$st}): no refund call at all", efb_pp_refund_calls(), array());
	t("uncaptured ({$st}): the row is not changed", array($GLOBALS['wpdb']->updates, $GLOBALS['wpdb']->row($id)['status']), array(array(), 'pending'));
}

/* unknown order (404), PayPal error (500), no order id, completed order without captures */
$GLOBALS['wpdb']->rows = array(efb_pp_row(array('id' => 41, 'transaction_id' => 'ORDER41AAAA')));
$GLOBALS['pp_routes'] = array();
$res = efb_pp_refund(41);
t('unknown order (404): a clear error and no refund', array($res ? $res->ok : null, is_string($res->data) && strpos($res->data, 'has no order ORDER41AAAA') !== false, efb_pp_refund_calls()), array(false, true, array()));

$GLOBALS['wpdb']->rows = array(efb_pp_row(array('id' => 42, 'transaction_id' => 'ORDER42AAAA')));
$GLOBALS['pp_routes'] = array('GET v2/checkout/orders/ORDER42AAAA' => array('code' => 500, 'body' => '{}'));
$res = efb_pp_refund(42);
t('PayPal error (500): a clear error with the HTTP code and no refund', array($res ? $res->ok : null, is_string($res->data) && strpos($res->data, 'HTTP 500') !== false, efb_pp_refund_calls()), array(false, true, array()));

$GLOBALS['wpdb']->rows = array(efb_pp_row(array('id' => 43, 'transaction_id' => '')));
$GLOBALS['pp_routes'] = array();
$res = efb_pp_refund(43);
t('no order id on the row: a clear error, PayPal is not asked', array($res ? $res->ok : null, is_string($res->data) && strpos($res->data, 'no PayPal order ID') !== false, count(array_filter($GLOBALS['pp_calls'], function ($c) { return strpos($c, 'checkout/orders') !== false; }))), array(false, true, 0));

$GLOBALS['wpdb']->rows = array(efb_pp_row(array('id' => 44, 'transaction_id' => 'ORDER44AAAA')));
$GLOBALS['pp_routes'] = array('GET v2/checkout/orders/ORDER44AAAA' => efb_pp_order('ORDER44AAAA', 'COMPLETED'));
$res = efb_pp_refund(44);
t('completed order without a capture: a clear error and no refund', array($res ? $res->ok : null, efb_pp_refund_calls()), array(false, array()));

/* --- the pure helper: the order id is never accepted as a capture id ------------------------------------------ */
$o = json_decode(efb_pp_order('ORDER51AAAA', 'COMPLETED', 'ORDER51AAAA')['body'], true);
t('capture_from_order_efb refuses a capture id equal to the order id', \Emsfb\PaypalPayment::capture_from_order_efb($o, 'ORDER51AAAA')['ok'], false);
$o = json_decode(efb_pp_order('ORDER52AAAA', 'COMPLETED', 'CAPTURE52BBBB')['body'], true);
t('capture_from_order_efb returns purchase_units[0].payments.captures[0].id', \Emsfb\PaypalPayment::capture_from_order_efb($o, 'ORDER52AAAA'), array('ok' => true, 'capture_id' => 'CAPTURE52BBBB', 'code' => '', 'message' => ''));

/* --- the shipped zip is built from these files -------------------------------------------------------------- */
$zip = dirname($dir) . '/paypal.zip';
if (is_readable($zip) && class_exists('ZipArchive')) {
	$z = new \ZipArchive();
	$z->open($zip);
	$same = true; $names = array();
	for ($i = 0; $i < $z->numFiles; $i++) {
		$n = $z->getNameIndex($i); $names[] = $n;
		if (substr($n, -1) === '/') continue;
		if (strpos($n, 'paypal/') !== 0) { $same = false; break; }
		$f = $dir . '/' . substr($n, 7);
		if (!is_file($f) || $z->getFromIndex($i) !== file_get_contents($f)) { $same = false; break; }
	}
	$z->close();
	$disk = 0;
	foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) $disk++;
	$files_in_zip = count(array_filter($names, function ($n) { return substr($n, -1) !== '/'; }));
	t('paypal.zip: every file is under paypal/ and equals the folder', $same, true);
	t('paypal.zip: the same number of files as the folder', $files_in_zip, $disk);
} else {
	echo "[SKIP] paypal.zip or ZipArchive not available\n";
}

echo "RESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);

}
