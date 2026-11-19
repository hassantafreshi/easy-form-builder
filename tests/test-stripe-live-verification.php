<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Actual Stripe test-mode transactions; no live keys, receipts or production records.
 *
 * Owner-only: it needs a real Stripe test-mode secret key (sk_test_...) saved on the site's Stripe add-on screen
 * (Easy Form Builder > Stripe keys) and network access to api.stripe.com. Elsewhere it prints [SKIP]: no key, or the
 * repository's own placeholder keys (tests/e2e-wp fixtures), which Stripe answers with HTTP 401.
 * Run: EFB_WP_ROOT=/path/to/wordpress php tests/test-stripe-live-verification.php (on XAMPP: php tests/...).
 */
define('DOING_AJAX', true);
require_once __DIR__ . '/wp-env.php';
require efb_test_wp_load_or_skip();
require_once dirname(__DIR__) . '/vendor/stripe/class-Emsfb-stripe-payment.php';
if (!class_exists('Stripe\\StripeClient')) require is_readable(dirname(__DIR__) . '/vendor/stripe/stripe-php/init.php') ? dirname(__DIR__) . '/vendor/stripe/stripe-php/init.php' : dirname(__DIR__) . '/vendor/autoload.php';
class EfbStripeTestEnd extends Exception {}
add_filter('wp_die_ajax_handler', static function () { return static function () { throw new EfbStripeTestEnd(); }; });
// PAY-OUT (docs/feature-gaps/SPLIT-core-addons.fa.md §5.4, steps 2-4): the Stripe keys and handlers left core's public
// class for the Stripe add-on (Stripe_Keys_Efb, Stripe_Legacy_Efb over core's members); core keeps only a shim.
if (!class_exists('Emsfb\\Stripe_Legacy_Efb')) { echo "[SKIP] the Stripe add-on (AdnSPF) is not active on this site: its handlers are what this test checks\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$public = \Emsfb\Stripe_Legacy_Efb::instance((new ReflectionClass('Emsfb\\_Public'))->newInstanceWithoutConstructor());
$key = $public->resolve_payment_key_efb(get_setting_Emsfb('decoded'), 'stripeSKey');
if ($key === 'null' || $key === '') { echo "[SKIP] no Stripe secret key is saved on this site: owner-only test, needs a real Stripe test-mode key (see the header)\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
if (strpos($key, 'sk_test_') !== 0) { fwrite(STDERR, "REFUSED: configured Stripe key is not a test key.\n"); exit(1); }
// The placeholders the repository's own fixtures save (tests/e2e-wp/ctl-payout.php, the reconcile and guard tests).
if (preg_match('/EfbE2e|efbreconcile|efbguards/i', $key)) { echo "[SKIP] the site's Stripe key is a placeholder of the test fixtures: owner-only test, needs a real Stripe test-mode key (see the header)\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$stripe = new \Stripe\StripeClient($key);
\Stripe\Stripe::setMaxNetworkRetries(0);
$intents = array(); $rows = array(); $passed = 0;
$confirm = static function ($intent, $track) use ($public) {
    $req = new WP_REST_Request('POST', '/Emsfb/v1/forms/payment/stripe/confirm');
    $req->set_header('Content-Type', 'application/json');
    $req->set_body(wp_json_encode(array('paymentIntentId' => $intent, 'trackid' => $track)));
    ob_start();
    try { $public->pay_stripe_confirm_Emsfb_api($req); } catch (EfbStripeTestEnd $end) {}
    return json_decode(ob_get_clean(), true);
};
$check = static function ($ok, $label) use (&$passed) { if (!$ok) throw new RuntimeException($label); $passed++; echo "PASS $label\n"; };
$status = static function ($id) use ($wpdb) { return $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}emsfb_pay_ WHERE id = %d", $id)); };
try {
    // Load the real schema without registering an administrator session.
    new \Emsfb\StripePayment();
    $unpaid = $stripe->paymentIntents->create(array('amount'=>100, 'currency'=>'usd', 'payment_method_types'=>array('card'), 'description'=>'EFB isolated test: unpaid verification'));
    $intents[] = $unpaid->id;
    $check(!$unpaid->livemode, 'Stripe confirms test mode');
    $track = 'EFBTEST-' . substr(str_replace('-','',wp_generate_uuid4()),0,12);
    $row = \Emsfb\StripePayment::insert_payment(array('track'=>$track, 'transaction_id'=>$unpaid->id, 'amount'=>1, 'currency'=>'USD'));
    if (!$row) throw new RuntimeException('Fixture record could not be inserted');
    $rows[] = $row;
    $res = $confirm($unpaid->id, $track);
    $check(empty($res['data']['success']) && $status($row)==='pending', 'Unpaid real intent cannot complete a payment');
    $paid = $stripe->paymentIntents->create(array('amount'=>100, 'currency'=>'usd', 'payment_method_types'=>array('card'), 'payment_method'=>'pm_card_visa', 'confirm'=>true, 'description'=>'EFB isolated test: confirmation verification'));
    $intents[] = $paid->id;
    $check(!$paid->livemode && $paid->status==='succeeded', 'Real Stripe sandbox card payment succeeds');
    $res = $confirm($paid->id, $track);
    $check(empty($res['data']['success']) && $status($row)==='pending', 'A different paid intent cannot complete the unpaid record');
    \Emsfb\StripePayment::update_payment($row, array('transaction_id'=>$paid->id, 'amount'=>2));
    $res = $confirm($paid->id, $track);
    $check(empty($res['data']['success']) && $status($row)==='pending', 'Amount mismatch rejected');
    \Emsfb\StripePayment::update_payment($row, array('amount'=>1, 'currency'=>'EUR'));
    $res = $confirm($paid->id, $track);
    $check(empty($res['data']['success']) && $status($row)==='pending', 'Currency mismatch rejected');
    \Emsfb\StripePayment::update_payment($row, array('currency'=>'USD'));
    $res = $confirm($paid->id, $track . '-wrong');
    $check(empty($res['data']['success']) && $status($row)==='pending', 'Tracking association required');
    $res = $confirm($paid->id, $track);
    $check(!empty($res['data']['success']) && $status($row)==='completed', 'Verified payment saved by the actual confirmation handler');
    $res = $confirm($paid->id, $track);
    $check(!empty($res['data']['success']) && $status($row)==='completed', 'Confirmation replay returns success without a second charge');
    echo "$passed Stripe live API checks passed (TEST mode).\n";
} catch (Throwable $error) {
    // Do not expose SDK exception details or credentials.
    fwrite(STDERR, 'FAIL: ' . ($error instanceof \Stripe\Exception\ApiErrorException ? 'Stripe API request failed (' . get_class($error) . ', HTTP ' . $error->getHttpStatus() . ', code ' . preg_replace('/[^a-zA-Z0-9_]/', '', (string) $error->getStripeCode()) . ')' : $error->getMessage()) . "\n");
    $failed = true;
} finally {
    foreach ($intents as $id) {
        try {
            $intent = $stripe->paymentIntents->retrieve($id);
            if ($intent->status === 'succeeded') $stripe->refunds->create(array('payment_intent'=>$id));
            elseif (!in_array($intent->status,array('canceled','processing'),true)) $stripe->paymentIntents->cancel($id);
        } catch (Throwable $error) { fwrite(STDERR, "Test transaction cleanup needs attention.\n"); $failed = true; }
    }
    foreach ($rows as $id) $wpdb->delete($wpdb->prefix . 'emsfb_pay_', array('id'=>$id));
}
exit(empty($failed) ? 0 : 1);
