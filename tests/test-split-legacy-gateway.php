<?php
/**
 * SPLIT §5 (subtask B): Stripe, PayPal and PersiaPay on the "legacy gateway" bridge v1.
 *  - without the add-on (absent, off, or an add-on from before SPLIT that does not announce the contract):
 *    the field is replaced by the pay_not_ready notice (data-efb-pay-blocked, the owner's reason), the final submit is
 *    refused before the payment reference is read (pay_not_ready), a saved order nobody can check counts as unpaid,
 *    the payment readiness says pay_addon_inactive, the admin state is "update" for an old add-on;
 *  - with the add-on: its box, its localize (scripts and publishable key), its order state;
 *  - the add-ons' routes-efb.php: with a bridge core their own class takes the routes; with a core from before SPLIT the
 *    routes still go to core's methods;
 *  - core no longer carries the gateway code (methods, scripts, the dead persia_pay-efb.js).
 *
 * Run: php tests/test-split-legacy-gateway.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_URL', 'https://site.test/wp-content/plugins/easy-form-builder/');
define('EMSFB_PLUGIN_VERSION', '4.3.1');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
efb_pay_test_load();
if (!function_exists('esc_html__')) { function esc_html__($s, $d = '') { return $s; } }
if (!function_exists('esc_attr')) { function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('esc_url')) { function esc_url($s) { return (string) $s; } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'https://site.test/wp-admin/' . $p; } }
if (!function_exists('current_user_can')) { function current_user_can($c) { return !empty($GLOBALS['can_fix']); } }
function emsfb_is_addon_compatible_efb($k) { return true; }
function get_setting_Emsfb($m = 'decoded') { return $GLOBALS['settings_efb']; }
$GLOBALS['settings_efb'] = (object) array();
$GLOBALS['scripts'] = array();
function wp_register_script($h, $src = '') { $GLOBALS['scripts'][$h] = $src; }
function wp_enqueue_script($h) { }
function register_rest_route($ns, $r, $a) { $GLOBALS['routes'][$ns . '/' . $r] = $a; }
require_once __DIR__ . '/../includes/class-Emsfb-addon-catalog.php';
require_once __DIR__ . '/../includes/addon-contracts-functions-efb.php';
require_once __DIR__ . '/../includes/class-Emsfb-addon-keys.php';
require_once __DIR__ . '/../includes/class-Emsfb-builder-api.php';
require_once __DIR__ . '/../includes/class-Emsfb-split.php';
$t = 'efb_pay_t';
$vdir = function ($d) { foreach (array(getenv('EFB_' . strtoupper($d) . '_DIR'), __DIR__ . '/../vendor/' . $d, '/home/user/vendores-efb/' . $d) as $x) if ($x && is_dir($x)) return realpath($x); return ''; };

// Core's helpers, lifted from class-Emsfb-public.php.
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
preg_match('/\tpublic function legacy_payment_state_efb\(.*?\n\t}\n/s', $src, $m1);
preg_match('/\tpublic function legacy_gateway_blocked_efb\(.*?\n\t}\n/s', $src, $m2);
eval('namespace Emsfb; class _Public { public static $instance_efb = null; public $setting; public function resolve_payment_key_efb($s, $k) { return "pk_test_PLACEHOLDER"; } ' . $m1[0] . $m2[0] . ' }');
$pub = new \Emsfb\_Public();
\Emsfb\_Public::$instance_efb = $pub;

$stripe_form = array(array('type' => 'payment', 'currency' => 'usd', 'getway' => 'stripe'), array('id_' => '1', 'type' => 'step'), array('id_' => 'pp', 'type' => 'stripe', 'name' => 'Pay'));
$saved = array(array('id_' => 'payment', 'type' => 'payment', 'paymentGateway' => 'stripe', 'paymentIntent' => 'pi_123', 'paymentmethod' => 'charge'));

// ---- 1. no Stripe add-on
$t('no add-on: efb_legacy_gateway_ready(stripe) is false', efb_legacy_gateway_ready('stripe'), false);
$t('no add-on: the stored form is blocked at the final submit (pay_not_ready before payid)', $pub->legacy_gateway_blocked_efb($stripe_form), 'stripe');
$t('no add-on: a saved Stripe order nobody can check is unpaid', $pub->legacy_payment_state_efb($saved, 'stripe'), 'unpaid');
$t('no PayPal row in the saved order: none', $pub->legacy_payment_state_efb($saved, 'paypal'), 'none');
$r = \Emsfb\Payments\Readiness::of_form($stripe_form, 3);
$t('readiness: pay_addon_inactive for AdnSPF (one language with PAY00)', array($r['has_payment'], $r['ready'], $r['issues'][0]['code'], $r['issues'][0]['addon']), array(true, false, 'pay_addon_inactive', 'AdnSPF'));
$at = strpos($src, '$legacy_block_efb = $this->legacy_gateway_blocked_efb($form_fields_array);');
$t('the submit refuses before it reads payid or looks up the pending row', $at !== false && $at < strpos($src, "\$id = isset(\$request_data['payid'])") && $at < strpos($src, 'AND read_ = %d'), true);

// The field without the add-on: Formbuilder::legacy_gateway_ui_efb() (private), on a Formbuilder without constructor.
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($a) { return 'n'; } }
if (!function_exists('is_rtl')) { function is_rtl() { return false; } }
if (!function_exists('number_format_i18n')) { function number_format_i18n($n, $d = 0) { return number_format((float) $n, $d); } }
require_once __DIR__ . '/../includes/class-Emsfb-formbuilder.php';
$fb = (new ReflectionClass('\Emsfb\Formbuilder'))->newInstanceWithoutConstructor();
$fb->valj_efb = array((object) array('type' => 'payment', 'currency' => 'usd'));
$ui = new ReflectionMethod('\Emsfb\Formbuilder', 'legacy_gateway_ui_efb');
$ui->setAccessible(true);
$texts = array('payAmount' => 'Amount', 'onetime' => 'One time', 'cardNumber' => 'Card', 'cardExpiry' => 'Expiry', 'cardCVC' => 'CVC', 'payNow' => 'Pay now', 'payment' => 'Payment');
$html = $ui->invoke($fb, 'stripe', (object) array('id_' => 'pp', 'type' => 'stripe'), 'rnd1', '7', $texts);
$t('field without the add-on: the visitor reads the pay_not_ready sentence; Send is held (data-efb-pay-blocked)', array(strpos($html, \Emsfb\Payments\Readiness::visitor_text()) !== false, strpos($html, 'data-efb-pay-blocked="1"') !== false, strpos($html, 'stripeCardSectionEfb') === false), array(true, true, true));
$GLOBALS['can_fix'] = true;
$html = $ui->invoke($fb, 'stripe', (object) array('id_' => 'pp', 'type' => 'stripe'), 'rnd1', '7', $texts);
$t('…whoever can fix it reads which add-on is missing (plaintext direction)', array(strpos($html, 'The Stripe Payment add-on of this payment field is not active') !== false, strpos($html, 'unicode-bidi:plaintext') !== false), array(true, true));
$GLOBALS['can_fix'] = false;

// An add-on from before SPLIT: on, loaded, but it does not announce legacy_gateway.
$GLOBALS['settings_efb']->AdnSPF = 1;
$t('old add-on (no contract): still not ready', efb_legacy_gateway_ready('stripe'), false);
$t('old add-on: the admin state is "update"', \Emsfb\Split::addon_state('AdnSPF'), 'update');

// ---- 2. the Stripe add-on of this release (its routes-efb.php on a bridge core)
$sdir = $vdir('stripe');
$t('the Stripe add-on is found', $sdir !== '', true);
$GLOBALS['routes'] = array();
require $sdir . '/routes-efb.php';
$t('the add-on announces legacy_gateway v1 → ready', array(efb_addon_contracts()['legacy_gateway']['providers'], efb_legacy_gateway_ready('stripe')), array(array('AdnSPF' => 1.0), true));
$t('…and the admin state is active', \Emsfb\Split::addon_state('AdnSPF'), null);
do_action('efb_register_payment_rest_routes', $pub);
$cb = function ($r) { $c = $GLOBALS['routes']['Emsfb/v1/forms/payment/stripe/' . $r]['callback'] ?? null; return is_array($c) ? get_class($c[0]) . '::' . $c[1] : ''; };
$t('routes: card/add, confirm and pkey are the add-on\'s', array($cb('card/add'), $cb('confirm'), $cb('pkey')), array('Emsfb\Stripe_Legacy_Efb::pay_stripe_sub_Emsfb_api', 'Emsfb\Stripe_Legacy_Efb::pay_stripe_confirm_Emsfb_api', 'Emsfb\Stripe_Legacy_Efb::get_stripe_public_key_Emsfb_api'));
$t('the stored form is no longer blocked', $pub->legacy_gateway_blocked_efb($stripe_form), '');
$r = \Emsfb\Payments\Readiness::of_form($stripe_form, 3);
$t('readiness: the add-on\'s own flow decides (not a framework payment)', array($r['has_payment'], $r['ready']), array(false, true));
$html = $ui->invoke($fb, 'stripe', (object) array('id_' => 'pp', 'type' => 'stripe'), 'rnd1', '7', $texts);
$t('field with the add-on: the card box (the markup core had)', array(strpos($html, 'id="stripeCardSectionEfb"') !== false, strpos($html, 'id="btnStripeEfb" data-formid="7"') !== false, strpos($html, 'data-efb-pay-blocked') === false), array(true, true, true));
// PAY-OUT step 2: the add-on resolves its own keys (its pay_stripe key, else the stripePKey core's tab kept in the row).
$GLOBALS['settings_efb'] = (object) array_merge((array) $GLOBALS['settings_efb'], array('stripePKey' => 'pk_test_PLACEHOLDER'));
$ar = apply_filters('efb_legacy_gateway_localize', array('a' => 1), 'stripe', (object) array(), 7);
$t('localize: Stripe.js and the add-on\'s stripe_pay-efb.js, the publishable key', array($ar['paymentGateway'], $ar['paymentKey'], $GLOBALS['scripts']['stripe-js'], $GLOBALS['scripts']['stripe_js']), array('stripe', 'pk_test_PLACEHOLDER', 'https://js.stripe.com/v3/', EMSFB_PLUGIN_URL . 'vendor/stripe/assets/js/stripe_pay-efb.js'));
$t('the order state is the add-on\'s (Stripe_Legacy_Efb::payment_state on efb_legacy_payment_state; it leaves other gateways alone)',
	array(in_array(array('Emsfb\Stripe_Legacy_Efb', 'payment_state'), $GLOBALS['efb_filters']['efb_legacy_payment_state'], true), \Emsfb\Stripe_Legacy_Efb::payment_state(null, $saved, 'paypal')), array(true, null));

// ---- 3. PayPal and PersiaPay add-ons on the bridge
$GLOBALS['settings_efb']->AdnPAP = 1; $GLOBALS['settings_efb']->AdnPPF = 1; $GLOBALS['routes'] = array();
require $vdir('paypal') . '/routes-efb.php';
require $vdir('persiapay') . '/routes-efb.php';
do_action('efb_register_payment_rest_routes', $pub);
$c = function ($r) { $x = $GLOBALS['routes']['Emsfb/v1/forms/payment/' . $r]['callback'] ?? null; return is_array($x) ? get_class($x[0]) : ''; };
$t('PayPal and PersiaPay: their routes are their own classes; both ready', array($c('paypal/card/add'), $c('paypal/capture'), $c('persia/add'), efb_legacy_gateway_ready('paypal'), efb_legacy_gateway_ready('persiaPay')), array('Emsfb\Paypal_Legacy_Efb', 'Emsfb\Paypal_Legacy_Efb', 'Emsfb\PersiaPay_Legacy_Efb', true, true));
$v = apply_filters('efb_legacy_payment_verify', null, 'stripe', array(), 10, array());
$t('efb_legacy_payment_verify answers only for PersiaPay / ZarinPal', $v, null);

// ---- 4. a core from before SPLIT (no bridge functions): the old routes to core's methods, run in a child PHP
$php = 'define("ABSPATH","/"); function add_action($h,$cb){ $GLOBALS["a"][$h][]=$cb; } function register_rest_route($n,$r,$a){ $GLOBALS["r"][$r]=$a; }'
	. 'require ' . var_export($sdir . '/routes-efb.php', true) . '; $p = new stdClass(); foreach ($GLOBALS["a"]["efb_register_payment_rest_routes"] as $cb) $cb($p);'
	. 'echo json_encode(array_map(function($x) use ($p) { return $x["callback"][0] === $p ? $x["callback"][1] : "other"; }, $GLOBALS["r"]));';
$old = json_decode((string) shell_exec('php -r ' . escapeshellarg($php)), true);
$t('Stripe add-on on a core from before SPLIT: the routes still call core\'s methods (it has the code)', $old['forms/payment/stripe/card/add'] ?? '', 'pay_stripe_sub_Emsfb_api');

// ---- 5. core carries no gateway code any more
$fbsrc = file_get_contents(__DIR__ . '/../includes/class-Emsfb-formbuilder.php');
$t('core: no Stripe / PayPal / PersiaPay methods, no payment intents, no ZarinPal call', array(
	strpos($src, 'function pay_stripe_sub_Emsfb_api') === false, strpos($src, 'function paypal_order_status_efb') === false, strpos($src, 'function pay_persia_sub_Emsfb_api') === false,
	strpos($src, 'function pay_paypal_capture_Emsfb_api') === false, strpos($src, 'validate_payment_zarinPal') === false, strpos($src, 'paymentIntents->create') === false,
	strpos($fbsrc, 'function add_ui_stripe_efb') === false, strpos($fbsrc, 'function add_ui_zp_efb') === false), array_fill(0, 8, true));
$t('core: stripe_pay-efb.js and the dead persia_pay-efb.js are gone (SPLIT §20 item 4)', array(is_file(__DIR__ . '/../public/assets/js/stripe_pay-efb.js'), is_file(__DIR__ . '/../public/assets/js/persia_pay-efb.js'), is_file($sdir . '/assets/js/stripe_pay-efb.js')), array(false, false, true));
$t('core: the Stripe reconcile cron is the add-on\'s; core clears it when the add-on is removed', array(strpos($src, "add_action('emsfb_stripe_reconcile_efb'") === false, strpos(file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-admin.php'), "wp_clear_scheduled_hook('emsfb_stripe_reconcile_efb')") !== false), array(true, true));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
