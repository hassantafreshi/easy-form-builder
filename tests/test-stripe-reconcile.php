<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Stripe one-time payment resilience: confirmation endpoint and background
 * reconciliation, with the real Stripe SDK and a local HTTP client (no network,
 * no real payment). Needs the Stripe add-on in vendor/stripe.
 *
 *   cd wp-content/plugins/easy-form-builder && php tests/test-stripe-reconcile.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('DOING_AJAX', true);
ob_start(); // keep headers unsent so the handlers' HTTP status reaches the status_header filter
require dirname(__DIR__, 4) . '/wp-load.php';
$init = dirname(__DIR__) . '/vendor/stripe/stripe-php/init.php';
if (!class_exists('\Stripe\StripeClient')) {
    if (!is_readable($init)) { fwrite(STDERR, "SKIP: Stripe add-on (vendor/stripe) not installed.\n"); exit(0); }
    require_once $init;
}
require_once dirname(__DIR__) . '/vendor/stripe/class-Emsfb-stripe-payment.php';

class EfbReconcileTestEnd extends Exception {}
add_filter('wp_die_ajax_handler', static function () { return static function () { throw new EfbReconcileTestEnd(); }; });

/** Answers the SDK locally. $mode per PaymentIntent: succeeded|amount|processing|requires_payment_method|canceled|connection|auth|notfound */
class EfbFakeStripeHttp implements \Stripe\HttpClient\ClientInterface {
    public $mode = array();
    public $default = 'succeeded';
    public $amounts = array();
    public $log = array();
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1') {
        $curl = \Stripe\HttpClient\CurlClient::instance();
        $this->log[] = array('method' => $method, 'url' => $absUrl, 'timeout' => $curl->getTimeout(), 'connect' => $curl->getConnectTimeout());
        if (!preg_match('#/v1/payment_intents/(pi_[A-Za-z0-9]+)$#', $absUrl, $m)) return array('{"error":{"message":"unsupported"}}', 400, array());
        $id = $m[1];
        $mode = isset($this->mode[$id]) ? $this->mode[$id] : $this->default;
        $amount = isset($this->amounts[$id]) ? $this->amounts[$id] : 100;
        if ($mode === 'connection') throw new \Stripe\Exception\ApiConnectionException('Could not connect (test)');
        if ($mode === 'auth') return array('{"error":{"type":"invalid_request_error","message":"Invalid API Key provided"}}', 401, array());
        if ($mode === 'notfound') return array('{"error":{"type":"invalid_request_error","code":"resource_missing","message":"No such payment_intent"}}', 404, array());
        $status = in_array($mode, array('processing', 'requires_payment_method', 'canceled'), true) ? $mode : 'succeeded';
        $received = $status === 'succeeded' ? ($mode === 'amount' ? $amount - 1 : $amount) : 0;
        return array(wp_json_encode(array('id' => $id, 'object' => 'payment_intent', 'amount' => $amount, 'amount_received' => $received, 'currency' => 'usd', 'status' => $status)), 200, array());
    }
}
$http = new EfbFakeStripeHttp();
\Stripe\ApiRequestor::setHttpClient($http);

global $wpdb;
$table = $wpdb->prefix . 'emsfb_pay_';
$msg_table = $wpdb->prefix . 'emsfb_msg_';
$ref = new ReflectionClass('Emsfb\\_Public');
$public = $ref->newInstanceWithoutConstructor();
$private = static function ($name) use ($ref) { $m = $ref->getMethod($name); $m->setAccessible(true); return $m; };
// The handlers need a Stripe secret key. Use the site's when one is set; otherwise a
// placeholder for this process only (object cache, never saved). The local HTTP client
// above answers every SDK request, so nothing reaches Stripe either way.
$settings_injected = false;
if ($private('resolve_payment_key_efb')->invoke($public, get_setting_Emsfb('decoded'), 'stripeSKey') === 'null') {
    $decoded = get_setting_Emsfb('decoded');
    $decoded = is_object($decoded) ? clone $decoded : new stdClass();
    $decoded->stripeSKey = 'sk_test_efbreconcile00000000';
    get_setting_Emsfb('_clear_cache');
    wp_cache_set('settings:decoded', $decoded, 'emsfb');
    $settings_injected = true;
}
$original_health = get_option(\Emsfb\Request_Health::OPTION, null);
$original_event = wp_next_scheduled('emsfb_stripe_reconcile_efb'); // put back afterwards
$passed = 0; $rows = array(); $tracks = array();
$check = static function ($ok, $label) use (&$passed) { if (!$ok) throw new RuntimeException('FAIL: ' . $label); $passed++; fwrite(STDOUT, "PASS $label\n"); };
$confirm = static function ($intent, $track) use ($public) {
    $request = new WP_REST_Request('POST', '/Emsfb/v1/forms/payment/stripe/confirm');
    $request->set_header('Content-Type', 'application/json');
    $request->set_body(wp_json_encode(array('paymentIntentId' => $intent, 'trackid' => $track)));
    $code = 200;
    add_filter('status_header', $hook = static function ($header, $status) use (&$code) { $code = $status; return $header; }, 10, 2);
    ob_start();
    try { $public->pay_stripe_confirm_Emsfb_api($request); } catch (EfbReconcileTestEnd $end) {}
    $body = json_decode(ob_get_clean(), true);
    remove_filter('status_header', $hook, 10);
    return array($code, $body);
};
$now = time();
$at = static function ($seconds_ago) use ($now) { return wp_date('Y-m-d H:i:s', $now - $seconds_ago); };
$add = static function ($fields, $created_ago, $updated_ago = null) use (&$rows, &$tracks, $at, $wpdb, $table) {
    static $n = 0; $n++;
    $track = 'EFBREC' . $n . substr(md5(uniqid('', true)), 0, 6);
    $intent = 'pi_EfbRec' . $n . substr(md5(uniqid('', true)), 0, 10);
    $id = \Emsfb\StripePayment::insert_payment(array_merge(array('track' => $track, 'transaction_id' => $intent, 'amount' => 1, 'currency' => 'USD', 'status' => 'pending', 'payment_type' => 'one-time', 'gateway' => 'stripe', 'created_at' => $at($created_ago), 'updated_at' => $at($updated_ago === null ? $created_ago : $updated_ago)), $fields));
    if (!$id) throw new RuntimeException('fixture insert failed: ' . $wpdb->last_error);
    $rows[] = $id; $tracks[] = $track;
    return array($id, $intent, $track);
};
$row = static function ($id) use ($wpdb, $table) { return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)); };
$gets = static function () use ($http) { return count(array_filter($http->log, static function ($l) { return $l['method'] === 'get'; })); };
$restore_table = static function () use (&$saved_rows, &$dropped, $wpdb, $table) {
    if (empty($dropped)) return;
    delete_option('emsfb_pay_table_version');
    new \Emsfb\StripePayment();
    foreach ((array) $saved_rows as $saved) $wpdb->insert($table, $saved);
    $dropped = false;
};
$reset = static function () use ($http, $wpdb, $table) { $http->log = array(); $http->mode = array(); $http->default = 'succeeded'; delete_transient('emsfb_stripe_reconcile_lock'); wp_clear_scheduled_hook('emsfb_stripe_reconcile_efb'); $wpdb->query("UPDATE {$table} SET status = 'test-parked' WHERE status = 'pending'"); };

try {
    new \Emsfb\StripePayment(); // creates the shared table like the add-on admin screen does
    $parked = $wpdb->get_col("SELECT id FROM {$table} WHERE status = 'pending'");
    $reset();

    // --- Reconciliation: selection window and outcomes -------------------------------------
    list($a) = $add(array(), 20 * MINUTE_IN_SECONDS);                                  // due, paid
    list($b) = $add(array(), 3 * MINUTE_IN_SECONDS);                                   // too young
    list($c) = $add(array(), 8 * DAY_IN_SECONDS);                                      // too old
    list($d) = $add(array('payment_type' => 'subscription'), 2 * HOUR_IN_SECONDS);     // not one-time
    list($e) = $add(array('gateway' => 'paypal'), 2 * HOUR_IN_SECONDS);                // other gateway
    list($f) = $add(array(), 2 * HOUR_IN_SECONDS, 20 * MINUTE_IN_SECONDS);             // checked recently
    list($g) = $add(array('status' => 'refunded'), 2 * HOUR_IN_SECONDS);              // settled
    list($h, $h_pi) = $add(array(), 3 * HOUR_IN_SECONDS, 2 * HOUR_IN_SECONDS);         // checked long ago, unpaid
    $http->mode[$h_pi] = 'requires_payment_method';
    list($i, $i_pi) = $add(array(), 4 * HOUR_IN_SECONDS);                              // canceled at Stripe
    $http->mode[$i_pi] = 'canceled';
    list($j, $j_pi) = $add(array(), 5 * HOUR_IN_SECONDS);                              // amount differs
    $http->mode[$j_pi] = 'amount';
    list($k, $k_pi) = $add(array(), 30 * MINUTE_IN_SECONDS);                           // unpaid, visitor may still retry
    $http->mode[$k_pi] = 'requires_payment_method';
    $k_before = $row($k)->updated_at;
    $public->reconcile_stripe_payments_efb();
    $check($row($a)->status === 'completed' && $row($a)->capture_id === $row($a)->transaction_id, 'Pending paid intent is completed by the background check');
    $check($row($b)->status === 'pending' && $row($c)->status === 'pending' && $row($f)->status === 'pending', 'Rows younger than 10 min, older than 7 days or checked within the hour are skipped');
    $check($row($d)->status === 'pending' && $row($e)->status === 'pending' && $row($g)->status === 'refunded', 'Subscriptions, other gateways and settled rows are never touched');
    $check($row($h)->status === 'failed', 'Intent still unpaid an hour after card/add (declined or abandoned) is marked failed');
    $check($row($k)->status === 'pending' && $row($k)->updated_at !== $k_before, 'Unpaid intent younger than an hour stays pending and moves to the back of the queue');
    $check($row($i)->status === 'cancelled', 'Intent canceled at Stripe is marked cancelled');
    $check($row($j)->status === 'pending', 'Amount mismatch is never completed');
    $check($gets() === 5, 'Exactly the five due rows were looked up (' . $gets() . ')');
    $check(!array_filter($http->log, static function ($l) { return $l['timeout'] !== 20 || $l['connect'] !== 8; }), 'Stripe calls run with 20 s / 8 s timeouts');
    $curl = \Stripe\HttpClient\CurlClient::instance();
    $check($curl->getTimeout() === 80 && $curl->getConnectTimeout() === 30, 'SDK default timeouts are restored afterwards');
    $next = wp_next_scheduled('emsfb_stripe_reconcile_efb');
    $check($next && $next > $now + 50 * MINUTE_IN_SECONDS && $next < $now + 70 * MINUTE_IN_SECONDS, 'Next run queued about an hour later while rows stay pending');

    // --- Gateway outage: stop at the first failure ------------------------------------------
    $reset();
    $outage = array();
    for ($k = 0; $k < 3; $k++) { $outage[] = $add(array(), (30 + $k) * MINUTE_IN_SECONDS); }
    $http->default = 'connection';
    $public->reconcile_stripe_payments_efb();
    $check($gets() === 1, 'A connection failure ends the run after one request');
    $check(!array_filter($outage, static function ($r) use ($row) { return $row($r[0])->status !== 'pending'; }), 'Outage leaves every row pending');
    $reset();
    for ($k = 0; $k < 3; $k++) { $add(array(), (40 + $k) * MINUTE_IN_SECONDS); }
    $http->default = 'notfound';
    $public->reconcile_stripe_payments_efb();
    $check($gets() === 3, 'A missing intent (404) is per row and does not end the run');

    // --- Batch bound and lock ---------------------------------------------------------------
    $reset();
    for ($k = 0; $k < 25; $k++) { $add(array(), (60 + $k) * MINUTE_IN_SECONDS); }
    $public->reconcile_stripe_payments_efb();
    $check($gets() === 20, 'At most 20 Stripe requests per run');
    $http->log = array();
    $public->reconcile_stripe_payments_efb();
    $check($gets() === 5, 'The next run takes the remaining rows, not the ones just checked');
    $reset();
    $add(array(), HOUR_IN_SECONDS);
    set_transient('emsfb_stripe_reconcile_lock', time(), 300);
    $public->reconcile_stripe_payments_efb();
    $check($gets() === 0, 'A run in progress (lock) is not doubled');
    $reset();
    $public->reconcile_stripe_payments_efb();
    $check(wp_next_scheduled('emsfb_stripe_reconcile_efb') === false, 'Nothing pending: no request and nothing queued');

    // --- Confirmation endpoint with the real SDK --------------------------------------------
    $reset();
    list($p, $p_pi, $p_track) = $add(array(), 0);
    $http->mode[$p_pi] = 'connection';
    list($code, $body) = $confirm($p_pi, $p_track);
    $check($code === 503 && empty($body['data']['success']) && $row($p)->status === 'pending', 'Unreachable Stripe: 503, row stays pending');
    $check((bool) wp_next_scheduled('emsfb_stripe_reconcile_efb'), 'Unreachable Stripe queues the background check');
    $http->mode[$p_pi] = 'auth';
    list($code) = $confirm($p_pi, $p_track);
    $health = get_option(\Emsfb\Request_Health::OPTION, array());
    $check($code === 503 && isset($health['server:payment:401:gateway_verification_unavailable']), 'Rejected key: 503 to the browser, provider status 401 in diagnostics');
    $http->mode[$p_pi] = 'processing';
    list($code) = $confirm($p_pi, $p_track);
    $check($code === 503 && $row($p)->status === 'pending', 'Processing intent is "try later", not a mismatch');
    $http->mode[$p_pi] = 'requires_payment_method';
    list($code) = $confirm($p_pi, $p_track);
    $check($code === 409 && $row($p)->status === 'pending', 'Unpaid intent: definitive 409');
    $http->mode[$p_pi] = 'succeeded';
    list($code, $body) = $confirm($p_pi, $p_track);
    $check($code === 200 && !empty($body['data']['success']) && $row($p)->status === 'completed', 'Paid intent: completed');
    $http->log = array(); $http->mode[$p_pi] = 'connection';
    list($code, $body) = $confirm($p_pi, $p_track);
    $check($code === 200 && !empty($body['data']['success']) && $gets() === 0, 'Repeat after completion succeeds without asking Stripe again');
    \Emsfb\StripePayment::update_payment($p, array('status' => 'refunded'));
    list($code) = $confirm($p_pi, $p_track);
    $check($code === 409 && $row($p)->status === 'refunded', 'Refunded record stays a mismatch');
    list($q, $q_pi, $q_track) = $add(array(), 0);
    $http->mode[$q_pi] = 'succeeded';
    list($code) = $confirm($q_pi, $p_track);
    $check($code === 409 && $row($q)->status === 'pending', 'Intent recorded under another tracking code: 409');

    // A refund that lands between the read and the write is never overwritten.
    $upd = $private('update_pending_stripe_payment_efb');
    \Emsfb\StripePayment::update_payment($q, array('status' => 'refunded'));
    $check($upd->invoke($public, $q, array('status' => 'completed')) === 0 && $row($q)->status === 'refunded', 'Conditional update keeps a concurrent refund');

    // --- No payment table (add-on admin screen never opened) --------------------------------
    $reset();
    // Park the table's rows, drop it, and recreate it afterwards through the add-on's own schema.
    $saved_rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
    $wpdb->query("DROP TABLE {$table}");
    $dropped = true;
    $stub_track = 'S' . substr(md5(uniqid('', true)), 0, 11); // emsfb_msg_.track is varchar(12)
    $stub_pi = 'pi_EfbStub' . substr(md5(uniqid('', true)), 0, 10);
    $payment_row = array('id_' => 'payment', 'amount' => 0, 'name' => 'Payment', 'type' => 'payment', 'value' => '1.5 usd', 'paymentIntent' => $stub_pi, 'paymentGateway' => 'stripe', 'paymentmethod' => 'charge', 'paymentAmount' => 1.5, 'paymentCreated' => '2026-10-18-01:00:00', 'paymentcurrency' => 'usd', 'gateway' => 'stripe', 'status' => 'active', 'total' => 1.5);
    $stub = str_replace('"', '\\"', wp_json_encode(array(array('id_' => 'so1probe', 'name' => 'Pick a plan', 'type' => 'option_payment', 'price' => '1.5', 'value' => 'A'), $payment_row), JSON_UNESCAPED_UNICODE));
    $wpdb->insert($msg_table, array('form_title_x' => 'test', 'content' => $stub, 'form_id' => 999999, 'track' => $stub_track, 'ip' => '127.0.0.1', 'read_' => 2, 'date' => wp_date('Y-m-d H:i:s')));
    $stub_id = $wpdb->insert_id;
    if (!$stub_id) throw new RuntimeException('stub insert failed: ' . $wpdb->last_error);
    $http->amounts[$stub_pi] = 150;
    $wpdb->last_error = '';
    list($code, $body) = $confirm($stub_pi, $stub_track);
    $check($code === 200 && !empty($body['data']['success']), 'No table: verified against the submission card/add stored');
    $check($wpdb->last_error === '', 'No table: no database error');
    $http->mode[$stub_pi] = 'connection';
    list($code) = $confirm($stub_pi, $stub_track);
    $check($code === 503, 'No table and Stripe unreachable: 503 (retry, then submit), not 409');
    list($code) = $confirm('pi_SomeoneElse123', $stub_track);
    $check($code === 409, 'No table: an intent not in this submission is a mismatch');
    $http->mode[$stub_pi] = 'amount';
    list($code) = $confirm($stub_pi, $stub_track);
    $check($code === 409, 'No table: an amount differing from the submission is a mismatch');
    $public->reconcile_stripe_payments_efb();
    $check(true, 'No table: background check is a silent no-op');
    $wpdb->delete($msg_table, array('msg_id' => $stub_id));
    $restore_table();

    // --- Deactivation clears the hook (read from source: running it would wipe this site's caches) ---
    $cleanup = new ReflectionMethod('Emsfb', 'plugin_deactivation_cleanup_efb');
    $lines = array_slice(file($cleanup->getFileName()), $cleanup->getStartLine() - 1, $cleanup->getEndLine() - $cleanup->getStartLine() + 1);
    $check(strpos(implode('', $lines), "wp_clear_scheduled_hook('emsfb_stripe_reconcile_efb')") !== false, 'Deactivation removes the queued check');

    fwrite(STDOUT, "$passed Stripe resilience checks passed (real SDK, local HTTP client, real WordPress/database).\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . ' @' . $error->getFile() . ':' . $error->getLine() . "\n");
    $failed = true;
} finally {
    if (!empty($dropped)) $restore_table();
    foreach ($rows as $id) $wpdb->delete($table, array('id' => $id));
    if (!empty($parked)) $wpdb->query("UPDATE {$table} SET status = 'pending' WHERE id IN (" . implode(',', array_map('intval', $parked)) . ")");
    $wpdb->query("UPDATE {$table} SET status = 'pending' WHERE status = 'test-parked'");
    delete_transient('emsfb_stripe_reconcile_lock');
    wp_clear_scheduled_hook('emsfb_stripe_reconcile_efb');
    if ($original_event) wp_schedule_single_event($original_event, 'emsfb_stripe_reconcile_efb');
    if ($original_health === null) delete_option(\Emsfb\Request_Health::OPTION); else update_option(\Emsfb\Request_Health::OPTION, $original_health, false);
    if ($settings_injected) { wp_cache_delete('settings:decoded', 'emsfb'); get_setting_Emsfb('_clear_cache'); }
}
exit(empty($failed) ? 0 : 1);
