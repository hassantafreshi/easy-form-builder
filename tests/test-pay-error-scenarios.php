<?php
/**
 * PAY00 error scenarios (docs/payments/payment-error-matrix.en.md), gateway-neutral core:
 *  1 Reasons: a provider's decline code or message → one of a small set of safe reasons; error codes → reasons;
 *  2 builder / Save / AutoPilot: every check with a level (error = the form can never take the payment):
 *    no keys, Test mode, local site in Live, currency, fixed / donation amount outside the gateway's
 *    limits, a chosen method that cannot take the currency, a required payer field not chosen (the
 *    confirmation e-mail field counts for the e-mail), a mapped field not marked Required, two payment
 *    fields, a payment field on a form that cannot pay;
 *  3 visitor: a payer value empty or in the wrong format (email, phone, phone_e164, country and
 *    allowed countries) is answered with the field before anything is stored (no entry, no payment);
 *  4 create() refused: the reason → its own safe message (insufficient funds, 3-D Secure …), a payer
 *    value the provider refused → the field, keys refused → "cannot take payments" for the visitor and
 *    an alert for the owner (settings card + one e-mail a day), network → provider_unavailable, 429 → rate_limited;
 *  5 status page: the reason of a failed / canceled / expired payment in safe words (the gateway's own first);
 *    the provider's cancel link while still open → efb_pc=1;
 *  6 admin: refund answers worded (unsupported, partial, amount, duplicate, the gateway's reason);
 *    the settings card's own notices (no keys, Test mode, keys refused, refused webhooks, local site).
 *
 * Every check here fails on the code before this change.
 *
 * Run: php tests/test-pay-error-scenarios.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$db = efb_pay_env();
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-admin.php';
use Emsfb\Payments\Service;
use Emsfb\Payments\Registry;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Store;
use Emsfb\Payments\Reasons;
use Emsfb\Payments\Remote_State;
use Emsfb\Payments\Create_Result;
use Emsfb\Payments\Admin;
$t = function ($name, $actual, $expected = true) { efb_pay_t($name, $actual, $expected); };

/* ---- 1. Reasons ------------------------------------------------------------------------------ */
$cases = array('Insufficient Funds' => 'insufficient_funds', 'insufficient_fund' => 'insufficient_funds', '3DS authentication failed' => 'auth_failed', 'authentication_abandoned' => 'auth_failed',
	'possible_fraud' => 'fraud_blocked', 'RESTRICTED' => 'fraud_blocked', 'DAILY_LIMIT_EXCEEDED' => 'limit_exceeded', 'invalid_card_number' => 'card_invalid', 'card_expired' => 'card_invalid',
	'USER_DECLINED_THE_TRANSACTION' => 'canceled', 'ABANDONED' => 'canceled', 'TIMEDOUT' => 'expired', 'payment_expired' => 'expired', 'Do not honour' => 'declined', 'card_declined' => 'declined',
	'Rate limit exceeded' => 'rate_limited', 'Service unavailable' => 'provider_unavailable', 'all fine' => '', '' => '');
$got = array(); foreach ($cases as $in => $want) $got[$in] = Reasons::from_text($in);
$t('1: Reasons::from_text maps provider codes and messages to the safe set', $got, $cases);
$t('1: Reasons::of_error: network / 5xx → provider_unavailable, 429 → rate_limited, keys → config, currency / method → method_unavailable',
	array_map(array(Reasons::class, 'of_error'), array('network_timeout', 'http_503', 'http_429', 'http_401', 'wrong_mode', 'no_method', 'method_currency', 'bad_response')),
	array('provider_unavailable', 'provider_unavailable', 'rate_limited', 'config', 'config', 'method_unavailable', 'method_unavailable', ''));
$t('1: Reasons::clean keeps known reasons only', array(Reasons::clean('declined'), Reasons::clean('nope'), Reasons::clean(array())), array('declined', '', ''));

/* ---- a gateway that declares payer formats and answers as the test says ----------------------- */
class Err_Gateway extends \Emsfb\Payments\Mock_Gateway {
	public static $create = null;   // a Create_Result to answer instead of the provider
	public static $state = null;    // a Remote_State for fetch()
	public static $messages = array();
	public function slug() { return 'efberr'; }
	public function label() { return 'ErrPay'; }
	public function supports() { return array_merge(parent::supports(), array('currencies' => array('EUR', 'THB'), 'min_minor' => array('EUR' => 100, 'THB' => 2000), 'max_minor' => array('EUR' => 500000, 'THB' => 15000000), 'refund' => true, 'partial_refund' => false)); }
	public function methods($s) { return array(array('card', 'Card'), array('oney', 'Oney 3x', array('currencies' => array('EUR'))), array('qr', 'QR', array('currencies' => array('THB')))); }
	public function field_schema() {
		return array(
			array('key' => 'pgw_map_email', 'type' => 'field', 'label' => 'Payer e-mail', 'field_types' => array('email'), 'required' => true, 'format' => 'email'),
			array('key' => 'pgw_map_phone', 'type' => 'field', 'label' => 'Mobile number', 'required_for' => array('oney'), 'format' => 'phone_e164'),
			array('key' => 'pgw_map_country', 'type' => 'field', 'label' => 'Country', 'required_for' => array('oney'), 'format' => 'country', 'countries' => array('FR', 'be', 'XX1')),
			array('key' => 'pgw_map_name', 'type' => 'field', 'label' => 'Name', 'format' => 'nonsense'),
		);
	}
	public function messages() { return self::$messages; }
	public function create(\Emsfb\Payments\Payment $p, Settings $s, \Emsfb\Payments\Checkout_Context $ctx) {
		if (self::$create) return self::$create;
		return parent::create($p, $s, $ctx);
	}
	public function fetch(\Emsfb\Payments\Payment $p, Settings $s) {
		if (self::$state) return self::$state;
		return parent::fetch($p, $s);
	}
}
add_action('efb_register_payment_gateways', function ($reg) { $reg->register(new Err_Gateway()); });
Registry::reset();
Settings::load('efberr')->apply((new Err_Gateway())->settings_schema(), array('api_key__test' => 'mock_test_ABCDEF123', 'webhook_secret__test' => 'whs_unit_test_secret'), 'test');
$schema = Registry::field_schema('efberr');
$t('the schema keeps format (known ones only), countries (ISO, upper case) for field options', array($schema[0]['format'], $schema[1]['format'], $schema[2]['countries'], $schema[3]['format']), array('email', 'phone_e164', array('FR', 'BE'), ''));

function err_form($field = array(), $f0 = array(), $extra = array()) {
	$rows = array(
		array_merge(array('type' => 'payment', 'getway' => 'efberr', 'currency' => 'EUR', 'email_to' => ''), $f0),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'required' => 1),
		array('id_' => 'mo', 'type' => 'mobile', 'name' => 'Mobile', 'required' => 1),
		array('id_' => 'co', 'type' => 'conturyList', 'name' => 'Country', 'required' => 1),
		array('id_' => 'cfr', 'parent' => 'co', 'type' => 'option', 'value' => 'France', 'id_op' => 'FR'),
		array('id_' => 'cde', 'parent' => 'co', 'type' => 'option', 'value' => 'Germany', 'id_op' => 'DE'),
		array_merge(array('id_' => 'pf', 'type' => 'efberr', 'name' => 'Pay', 'pf_fixed' => '150', 'pgw_map_email' => 'em', 'pgw_map_phone' => 'mo', 'pgw_map_country' => 'co'), $field),
	);
	return array_merge($rows, $extra);
}
function codes_of($w) { return array_map(function ($x) { return $x['code'] . ':' . $x['level']; }, array_values(array_filter($w, function ($x) { return $x['gateway'] === 'efberr'; }))); }
function w_of($form) { return array_values(array_filter(Registry::form_warnings($form), function ($x) { return $x['gateway'] === 'efberr'; })); }

/* ---- 2. builder / Save / AutoPilot ------------------------------------------------------------- */
$GLOBALS['efb_home'] = 'https://shop.example.com';
add_filter('efb_payment_site_is_local', function ($v, $u) { return isset($GLOBALS['efb_local']) ? $GLOBALS['efb_local'] : $v; }, 10, 2);
$GLOBALS['efb_local'] = false;
$t('2: a correct form in Test mode: only the info "Test mode" (it never pops up)', codes_of(w_of(err_form())), array('pay_test_mode:info'));
$w = w_of(err_form());
$t('2: ...worded with the gateway and where to switch', $w[0]['m'], 'ErrPay is in Test mode: no real payments are taken. Switch it to Live in Payments → Gateways when you are ready.');
Settings::load('efberr')->apply((new Err_Gateway())->settings_schema(), array(), 'live');
$w = w_of(err_form());
$t('2: Live without Live keys → error pay_not_configured (red), worded with the mode', array(codes_of($w), $w[0]['m']), array(array('pay_not_configured:error'), 'ErrPay has no keys for Live mode yet, so visitors cannot pay. Add them in Payments → Gateways.'));
$GLOBALS['efb_local'] = true;
$t('2: Live on a site the provider cannot reach → warning pay_webhook_local', in_array('pay_webhook_local:warning', codes_of(w_of(err_form())), true));
$GLOBALS['efb_local'] = false;
Settings::load('efberr')->apply((new Err_Gateway())->settings_schema(), array(), 'test');
$w = w_of(err_form(array('pf_fixed' => '6000')));
$t('2: a fixed amount above the gateway\'s maximum → error pay_amount_limit with both amounts', array(codes_of($w), strpos($w[1]['m'], 'up to') !== false && strpos($w[1]['m'], '5,000.00') !== false && strpos($w[1]['m'], '6,000.00') !== false), array(array('pay_test_mode:info', 'pay_amount_limit:error'), true), $w);
$w = w_of(err_form(array('pf_fixed' => '0.5')));
$t('2: a fixed amount below the minimum → warning (other choices may raise the total)', array(codes_of($w), strpos($w[1]['m'], 'other choices raise the total') !== false), array(array('pay_test_mode:info', 'pay_amount_limit:warning'), true));
$w = w_of(err_form(array('pf_fixed' => '', 'pf_amount' => 'donation', 'pf_min' => '0.10', 'pf_max' => '9000')));
$t('2: donation minimum / maximum outside the limits → two warnings', codes_of($w), array('pay_test_mode:info', 'pay_amount_limit:warning', 'pay_amount_limit:warning'));
$w = w_of(err_form(array('pf_methods' => 'card,qr')));
$t('2: a chosen method that cannot take the currency → warning naming it', array(codes_of($w), $w[1]['m']), array(array('pay_test_mode:info', 'pay_method_currency:warning'), 'ErrPay: QR cannot take EUR, so it is not offered on this form. Choose other methods or change the currency.'));
$t('2: ...no chosen method left → error', codes_of(w_of(err_form(array('pf_methods' => 'qr')))), array('pay_test_mode:info', 'pay_method_currency:error'));
$w = w_of(err_form(array('pgw_map_email' => '', 'pgw_map_phone' => '', 'pgw_map_country' => '')));
$t('2: the e-mail (every method) not chosen → error; the mobile and country (Oney only) → warnings', codes_of($w), array('pay_test_mode:info', 'pay_option_required:error', 'pay_option_required:warning', 'pay_option_required:warning'));
$t('2: ...actionable: the kind of field and the option, the method', array($w[1]['m'], $w[2]['m']), array('ErrPay requires "Payer e-mail". Add an Email field to the form and select it in the payment field\'s "Payer e-mail" option.', 'ErrPay requires "Mobile number" to pay with Oney 3x. Add a field for it to the form and select it in the payment field\'s "Mobile number" option.'));
$t('2: the e-mail option empty but the form sends its confirmation to an Email field → no e-mail warning', codes_of(w_of(err_form(array('pgw_map_email' => ''), array('email_to' => 'em')))), array('pay_test_mode:info'));
$f = err_form(); $f[3]['required'] = 0;
$w = w_of($f);
$t('2: a mapped field not marked Required → info naming the form\'s field', array(codes_of($w), $w[1]['m']), array(array('pay_test_mode:info', 'pay_option_not_required:info'), '"Mobile" is needed by ErrPay for the payment but is not marked Required. Visitors are asked for it when they press Pay; mark it Required so they see it earlier.'));
$w = w_of(err_form(array(), array(), array(array('id_' => 'pf2', 'type' => 'efberr', 'name' => 'Pay 2', 'pgw_map_email' => 'em'))));
$t('2: two payment fields → error pay_multiple_fields (once)', array_count_values(codes_of($w))['pay_multiple_fields:error'], 1);
$w = w_of(err_form(array(), array('type' => 'survey')));
$t('2: a payment field on a survey form → error pay_form_type naming the type', array(codes_of($w)[0], $w[0]['m']), array('pay_form_type:error', 'A payment field only works in a payment form. This form is a "survey" form: create a payment form or remove the payment field.'));
$w = w_of(err_form(array(), array('currency' => 'USD')));
$t('2: a currency the gateway does not take → error (no method noise on top)', codes_of($w), array('pay_test_mode:info', 'pay_currency:error'));
$t('2: every answer carries code, m, level, field_id, gateway (AutoPilot reads the same)', array_keys(w_of(err_form())[0]), array('code', 'm', 'level', 'field_id', 'gateway'));

/* ---- 3. visitor: payer values checked before anything is stored -------------------------------- */
function err_create($db, $method, $vals, $field = array(), $extra = array()) {
	$form = err_form($field);
	$fid = $db->add_form($form, 'payment');
	$rows = array();
	foreach ($vals as $id => $v) $rows[] = array('id_' => $id, 'type' => $id === 'em' ? 'email' : ($id === 'co' ? 'conturyList' : 'mobile'), 'name' => $id, 'value' => $v);
	return Service::create(array_merge(array('form_id' => $fid, 'form' => $form, 'rows' => $rows, 'field_id' => 'pf', 'method' => $method, 'locale' => 'en_US',
		'attempt_key' => sprintf('%08x-%04x-4%03x-8%03x-%012x', mt_rand(), mt_rand(0, 65535), mt_rand(0, 4095), mt_rand(0, 4095), mt_rand()), 'page_url' => '',
		'store_entry' => function ($rows) use ($db, $fid) { $id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2)); return array('track' => 'T' . $id, 'msg_id' => $id); }), $extra));
}
$e0 = $db->count('wp_emsfb_msg_'); $p0 = $db->count('wp_emsfb_pmt_');
$ok = array('em' => 'ana@example.test', 'mo' => '+33 6 12 34 56 78', 'co' => 'France');
$r = err_create($db, 'card', array('em' => '') + $ok);
$t('3: e-mail empty → payer_missing at that field, the form\'s own field name', array($r['success'], $r['code'], $r['field_id'], $r['m']), array(false, 'payer_missing', 'em', 'Please fill in "Email": it is needed for the payment.'));
$t('3: ...and nothing is stored (no entry left half-way, no payment row)', array($db->count('wp_emsfb_msg_') - $e0, $db->count('wp_emsfb_pmt_') - $p0), array(0, 0));
$r = err_create($db, 'card', array('em' => 'ana@example') + $ok);
$t('3: e-mail without a domain → payer_invalid (email)', array($r['code'], $r['field_id'], $r['m']), array('payer_invalid', 'em', 'Please enter a valid e-mail address in "Email".'));
$r = err_create($db, 'oney', array('mo' => '06 12 34 56 78') + $ok);
$t('3: Oney: a mobile number without the country code → payer_invalid (phone_e164)', array($r['code'], $r['field_id'], $r['m']), array('payer_invalid', 'mo', 'Please enter the phone number in "Mobile" with its country code, starting with + (for example +33 6 12 34 56 78).'));
$r = err_create($db, 'oney', array('co' => 'Germany') + $ok);
$t('3: Oney: a country (option name → its ISO code) outside the gateway\'s list → payer_invalid (country_not_allowed)', array($r['code'], $r['field_id'], strpos($r['m'], 'not available for the country in "Country"') !== false), array('payer_invalid', 'co', true));
$r = err_create($db, 'oney', array('co' => 'Atlantis') + $ok);
$t('3: a country the form does not know → "Please choose a country"', array($r['code'], $r['m']), array('payer_invalid', 'Please choose a country in "Country".'));
$r = err_create($db, 'oney', array('mo' => '') + $ok);
$t('3: Oney: mobile empty → payer_missing naming the method', $r['m'], 'Please fill in "Mobile": it is needed to pay with Oney 3x.');
$r = err_create($db, 'card', array('mo' => '12', 'co' => 'Germany') + $ok);
$t('3: card: phone and country are not needed for the card, so a value the card does not need is not checked (passed on as typed)', array($r['success'], $db->count('wp_emsfb_pmt_') - $p0), array(true, 1));
$p0++; $e0++;
$t('3: none of these stored anything', array($db->count('wp_emsfb_msg_') - $e0, $db->count('wp_emsfb_pmt_') - $p0), array(0, 0));
Err_Gateway::$messages = array('payer_invalid' => 'ErrPay needs %field% in its own format.');
$r = err_create($db, 'card', array('em' => 'bad') + $ok);
$t('3: the gateway\'s own payer_invalid text (messages(), %field% filled)', $r['m'], 'ErrPay needs Email in its own format.');
Err_Gateway::$messages = array();
$r = err_create($db, 'oney', $ok);
$t('3: everything right → the payment starts; the payer country is the ISO code', array($r['success'], \Emsfb\Payments\Mock_Gateway::$last_ctx->payer['country']), array(true, 'FR'));

/* ---- 4. create() refused ----------------------------------------------------------------------- */
$cases4 = array(
	array(Create_Result::fail('card_declined', 'Insufficient funds on card')->because('insufficient_funds'), 'insufficient_funds', 'The payment was declined because there were not enough funds. You have not been charged. Please use another card or payment method.'),
	array(Create_Result::fail('3ds')->because('auth_failed'), 'auth_failed', 'The card security check (3-D Secure) was not completed. You have not been charged. Please try again or use another card.'),
	array(Create_Result::fail('network_timeout', 'cURL error 28'), 'provider_unavailable', 'The payment service is not responding right now. You have not been charged. Please try again in a few minutes.'),
	array(Create_Result::fail('http_429'), 'rate_limited', 'Too many payment attempts. Please wait a minute and try again.'),
	array(Create_Result::fail('method_currency'), 'method_unavailable', 'This payment method is not available for this payment right now. Please choose another payment method.'),
	array(Create_Result::fail('weird_thing'), 'gateway_error', 'The payment could not be started. You have not been charged. Please try again in a moment.'),
	array(Create_Result::fail('declined')->because('declined')->for_visitor('Use a Visa card.'), 'gateway_declined', 'Use a Visa card.'),
);
foreach ($cases4 as $c) {
	Err_Gateway::$create = $c[0];
	$r = err_create($db, 'card', $ok);
	$t('4: create refused (' . $c[0]->error . ') → ' . $c[1], array($r['success'], $r['code'], $r['m']), array(false, $c[1], $c[2]));
}
Err_Gateway::$messages = array('insufficient_funds' => 'ErrPay: not enough money. Try the QR.');
Err_Gateway::$create = Create_Result::fail('x')->because('insufficient_funds');
$t('4: the gateway\'s own text for a reason (messages())', err_create($db, 'card', $ok)['m'], 'ErrPay: not enough money. Try the QR.');
Err_Gateway::$messages = array();
Err_Gateway::$create = Create_Result::fail('invalid_phone', 'customer.phone invalid')->because('payer_invalid', 'pgw_map_phone');
$r = err_create($db, 'card', $ok);
$t('4: the provider refused a payer value → payer_invalid at that field (from the field\'s mapping)', array($r['code'], $r['field_id'], $r['m']), array('payer_invalid', 'mo', 'Please enter the phone number in "Mobile" with its country code, starting with + (for example +33 6 12 34 56 78).'));
$GLOBALS['efb_mails'] = array();
delete_option('emsfb_pmt_alert_efberr');
Err_Gateway::$create = Create_Result::fail('http_401', 'Invalid API key sk_test_SECRETVALUE123456');
$r = err_create($db, 'card', $ok);
$alert = get_option('emsfb_pmt_alert_efberr');
$t('4: keys refused → the visitor reads "cannot take payments" (nothing technical)', array($r['code'], $r['m']), array('pay_unavailable', 'This form cannot take payments at the moment. Please contact the site owner.'));
$t('4: ...the owner gets an alert (mode, code, the provider\'s text without the key) and one e-mail', array($alert['mode'], $alert['error'], strpos($alert['m'], 'SECRETVALUE') === false, count($GLOBALS['efb_mails']), strpos($GLOBALS['efb_mails'][0]['subject'], 'Visitors cannot pay with ErrPay') === 0), array('test', 'http_401', true, 1, true), $alert);
err_create($db, 'card', $ok);
$t('4: ...a second refusal the same day: no second e-mail', count($GLOBALS['efb_mails']), 1);
$notes = Admin::core_notices(Registry::get('efberr'), Settings::load('efberr'));
$t('4: ...the settings card shows it as an error with what to do', in_array('pay_config_failed', array_column($notes, 'code'), true) && strpos(implode(' ', array_column($notes, 'm')), 'press Test connection') !== false);
Err_Gateway::$create = null;
err_create($db, 'card', $ok);
$t('4: the next payment that starts clears the alert', get_option('emsfb_pmt_alert_efberr'), false);

/* ---- 5. status page ------------------------------------------------------------------------------- */
$r = err_create($db, 'card', $ok);
$p = Store::find_by_uid($r['pay']['uid']);
Err_Gateway::$state = Remote_State::of('failed', null, '', array('reason' => 'insufficient_funds'));
Store::update($p['id'], array('last_checked_at' => null));
$a = Service::status_answer(Store::get($p['id']), 'status');
$t('5: failed with a reason → that reason\'s words, Try again, the reason in the answer', array($a['state'], $a['reason'], $a['message'], $a['can_retry']), array('failed', 'insufficient_funds', 'The payment was declined because there were not enough funds. You have not been charged. Please use another card or payment method.', true));
$t('5: ...kept with the payment and logged once', array(json_decode(Store::get($p['id'])['next_json'], true)['fail_reason'], count(array_filter(Store::events($p['id']), function ($e) { return $e['note'] === 'reason insufficient_funds'; }))), array('insufficient_funds', 1));
Err_Gateway::$messages = array('insufficient_funds' => 'ErrPay: the card has no funds.');
$t('5: the gateway\'s own words for the reason', Service::status_answer(Store::get($p['id']), 'status')['message'], 'ErrPay: the card has no funds.');
Err_Gateway::$messages = array();
$r = err_create($db, 'card', $ok);
$p = Store::find_by_uid($r['pay']['uid']);
Err_Gateway::$state = Remote_State::of('canceled', null, '');
$a = Service::status_answer($p, 'status');
$t('5: canceled (no reason given) → "was cancelled, not charged"', array($a['reason'], $a['message']), array('canceled', 'The payment was cancelled. You have not been charged. You can try again when you are ready.'));
Err_Gateway::$state = Remote_State::of('pending', null, '');
$r = err_create($db, 'card', $ok);
$p = Store::find_by_uid($r['pay']['uid']);
$req = new WP_REST_Request('GET'); $req->set_param('gateway', 'efberr'); $req->set_param('p', $p['uid']); $req->set_param('s', Service::sign($p['uid'], 'efberr')); $req->set_param('c', '1');
$res = Service::handle_return($req);
$t('5: back through the provider\'s cancel link while it is still open → efb_pc=1 on the form page (wording only)', strpos($res->headers['Location'], 'efb_pc=1') !== false);
$req->set_param('c', '');
$t('5: ...a normal return has no efb_pc', strpos(Service::handle_return($req)->headers['Location'], 'efb_pc') === false);
Err_Gateway::$state = null;

/* ---- 6. admin ---------------------------------------------------------------------------------- */
$pp = array('id' => 999999, 'status' => 'paid', 'amount_minor' => 15000, 'refunded_minor' => 5000, 'currency' => 'EUR');
$t('6: refund answers are worded for the owner', array(
	Service::refund_message(array('error' => 'unsupported'), $pp),
	Service::refund_message(array('error' => 'unsupported_partial'), $pp),
	Service::refund_message(array('error' => 'bad_amount'), $pp),
	Service::refund_message(array('error' => 'duplicate'), $pp),
	Service::refund_message(array('error' => 'refund_declined', 'message' => 'Refunds must be at least €0.10'), $pp),
	Service::refund_message(array('error' => 'bad_amount'), array('status' => 'pending') + $pp),
), array(
	'This gateway cannot refund through Easy Form Builder. Refund it in the gateway\'s dashboard, then use "Mark as refunded outside EFB".',
	'This gateway can only refund the full remaining amount (€100.00).',
	'The refund amount must be more than 0 and at most €100.00 (what is left after earlier and pending refunds).',
	'This refund was already requested.',
	'The gateway refused the refund: Refunds must be at least €0.10. Nothing was refunded.',
	'Only a paid payment can be refunded.',
));
$r = err_create($db, 'card', $ok);
$paid = Store::find_by_uid($r['pay']['uid']);
\Emsfb\Payments\Mock_Provider::set_status($paid['gateway_ref'], 'paid');
Service::refresh($paid, 'api');
$paid = Store::get($paid['id']);
$x = Service::refund($paid, 100, 'part', 1);
$t('6: a partial refund on a gateway without partial refunds → unsupported_partial (worded with what is left)', array($x['error'], Service::refund_message($x, $paid)), array('unsupported_partial', 'This gateway can only refund the full remaining amount (€150.00).'));
$s = Settings::load('efberr');
$codes = function () { return array_column(Admin::core_notices(Registry::get('efberr'), Settings::load('efberr')), 'code'); };
$t('6: settings card in Test mode: the info notice', $codes(), array('pay_test_mode'));
for ($i = 0; $i < 3; $i++) Store::log_event(null, 'efberr', 'webhook', array('type' => 'auth_failed', 'http_code' => 401));
$t('6: 3 refused webhooks in 24 hours → a warning naming the count', in_array('pay_webhook_rejected', $codes(), true) && strpos(implode(' ', array_column(Admin::core_notices(Registry::get('efberr'), Settings::load('efberr')), 'm')), 'sent 3 payment confirmations') !== false);
Settings::load('efberr')->apply((new Err_Gateway())->settings_schema(), array(), 'live');
$GLOBALS['efb_local'] = true;
$t('6: Live without keys → error first; a local site → warning', array_slice($codes(), 0, 1) === array('pay_keys_missing') && in_array('pay_webhook_local', $codes(), true));
$GLOBALS['efb_local'] = null;
$t('6: site_is_local: localhost, 127.0.0.1, 10.x, .local, .test are local; a public host is not', array(Service::site_is_local('http://localhost:8080/'), Service::site_is_local('http://127.0.0.1:8088/'), Service::site_is_local('https://10.1.2.3/'), Service::site_is_local('https://shop.local/'), Service::site_is_local('https://wp.test/'), Service::site_is_local('https://shop.example.com/')), array(true, true, true, true, true, false));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
