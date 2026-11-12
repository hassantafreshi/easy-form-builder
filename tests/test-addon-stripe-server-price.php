<?php
/**
 * Stripe add-on: verified to charge only server-computed amounts (B7). No add-on change needed.
 *
 * Unlike PayPal and Persia Pay, the Stripe add-on (vendores-efb stripe/) prices nothing itself:
 * its REST routes call core's _Public::pay_stripe_sub_Emsfb_api() / pay_stripe_confirm_Emsfb_api(),
 * and core creates the PaymentIntent / subscription price from fun_validation_pay_elements_efb()
 * (which runs Builder_Api::apply_server_prices(), see tests/test-ap-b7-computed-price.php).
 * The add-on's refund screen refunds the amount stored in the payment table.
 *
 * Checks: the routes the add-on registers and their callbacks; that no add-on PHP file (the
 * bundled stripe-php SDK aside) creates a charge, price or subscription or reads an amount from
 * the request; that core's intent uses price_total and never a posted amount; that a refund
 * uses the stored amount.
 * Add-on source: EFB_STRIPE_DIR, vendor/stripe (git-ignored symlink), or /home/user/vendores-efb/stripe.
 *
 * Run: php tests/test-addon-stripe-server-price.php
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$dir = null;
foreach (array(getenv('EFB_STRIPE_DIR') ?: '', __DIR__ . '/../vendor/stripe', '/home/user/vendores-efb/stripe') as $d) {
	if ($d !== '' && is_readable($d . '/routes-efb.php')) { $dir = $d; break; }
}
if (!$dir) { echo "[SKIP] the Stripe add-on source was not found\n"; exit(0); }

$pass = 0; $fail = 0;
function st($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail) : '') . "\n"; }
}

// 1. Routes: registered on efb_register_payment_rest_routes, handled by core.
$GLOBALS['actions'] = array(); $GLOBALS['routes'] = array();
function add_action($hook, $cb) { $GLOBALS['actions'][$hook][] = $cb; }
function register_rest_route($ns, $route, $args) { $GLOBALS['routes'][$ns . '/' . $route] = $args; }
require $dir . '/routes-efb.php';
$public = new stdClass();
foreach ($GLOBALS['actions']['efb_register_payment_rest_routes'] ?? array() as $cb) $cb($public);
$cb = function ($r) use ($public) { $c = $GLOBALS['routes'][$r]['callback'] ?? null; return is_array($c) && $c[0] === $public ? $c[1] : null; };
st('card/add is core\'s pay_stripe_sub_Emsfb_api', $cb('Emsfb/v1/forms/payment/stripe/card/add') === 'pay_stripe_sub_Emsfb_api', $GLOBALS['routes']);
st('confirm is core\'s pay_stripe_confirm_Emsfb_api', $cb('Emsfb/v1/forms/payment/stripe/confirm') === 'pay_stripe_confirm_Emsfb_api');
st('the add-on registers no other payment-creating route', count(array_filter(array_keys($GLOBALS['routes']), function ($r) { return strpos($r, 'payment/stripe') !== false; })) === 3, array_keys($GLOBALS['routes']));

// 2. The add-on's own PHP never creates a charge from a request amount.
$own = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
	$p = str_replace('\\', '/', $f->getPathname());
	if (substr($p, -4) === '.php' && strpos($p, '/stripe-php/') === false && strpos($p, '/languages/') === false) $own[] = $p;
}
/** A request read that names an amount: $_POST['amount'], $data_POST['price'], get_param('total')... */
function efb_reads_posted_amount($s) {
	return (bool) preg_match('/(\$_POST|\$_REQUEST|\$_GET|\$data_POST|get_json_params\(\)|get_param\()\s*(\[\s*|\(\s*)?[\'"](amount|price|price_total|total)[\'"]/i', $s);
}
$creates = array(); $posted_amount = array();
foreach ($own as $p) {
	$s = file_get_contents($p);
	if (preg_match('/paymentIntents->create|prices->create|subscriptions->create|PaymentIntent::create|Price::create|Subscription::create|Charge::create/', $s)) $creates[] = basename($p);
	if (efb_reads_posted_amount($s)) $posted_amount[] = basename($p);
}
st('add-on PHP files scanned (SDK excluded)', count($own) >= 3, $own);
st('no add-on file creates a PaymentIntent, price, subscription or charge', $creates === array(), $creates);
st('no add-on file reads an amount, price or total from the request', $posted_amount === array(), $posted_amount);
st('(the scan does see such a read: the old PayPal handler line)', efb_reads_posted_amount("\$amount = sanitize_text_field( \$data_POST['amount'] ?? '' );"));

// 3. Core's intent: the amount is the server total.
$core = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
preg_match('/\tpublic function pay_stripe_sub_Emsfb_api\(.*?\n\t}\n/s', $core, $m);
$body = $m[0] ?? '';
st('core pay_stripe_sub_Emsfb_api found', $body !== '');
st('core prices the intent with fun_validation_pay_elements_efb()', strpos($body, '$obj = $this->fun_validation_pay_elements_efb($val_ , $fs_);') !== false && strpos($body, "\$price_f = \$obj['price_total'];") !== false);
st('the one-time intent and the subscription price use that amount', preg_match("/'amount' => \\\$price_f,/", $body) === 1 && preg_match("/'unit_amount' => \\\$price_f,/", $body) === 1);
st('core never reads a posted amount/price for Stripe', !preg_match("/\\\$data_POST\\[\\s*'(amount|price|price_total|total)'\\s*\\]/", $body));
st('fun_validation_pay_elements_efb() runs Builder_Api::apply_server_prices() (B7)', strpos($core, '\Emsfb\Builder_Api::apply_server_prices($val_, $fs_)') !== false);

// 4. Refunds (admin screen of the add-on) use the stored amount.
$pay = file_get_contents($dir . '/class-Emsfb-stripe-payment.php');
st('a refund is the stored payment amount', strpos($pay, "\$refund_params['amount'] = \$this->amount_to_stripe( (float) \$row->amount, \$row->currency );") !== false);

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail > 0 ? 1 : 0);
