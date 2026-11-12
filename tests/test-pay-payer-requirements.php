<?php
/**
 * PAY00 payer requirements (owner request): a gateway declares, in field_schema(), which payer
 * values it needs, for every payment (required) or per method (required_for), with its own words
 * for the owner (owner_message) and the visitor (visitor_message); and its own visitor messages for
 * error codes (Gateway::messages(), filter efb_payment_message).
 *  - Save / builder / AutoPilot: an actionable warning naming the kind of field to add, the option
 *    to select it in and the methods that need it; nothing when the field does not offer them;
 *  - public form: the payment stops at the empty field with a field-level message (field_id),
 *    the gateway's own text first, core's "Please fill in …" otherwise;
 *  - error codes: the gateway's text for a core code, then the filter.
 *
 * Run: php tests/test-pay-payer-requirements.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$db = efb_pay_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Registry;
use Emsfb\Payments\Settings;
$t = 'efb_pay_t';

class Req_Gateway extends \Emsfb\Payments\Mock_Gateway {
	public static $owner = '';
	public function slug() { return 'efbreq'; }
	public function label() { return 'ReqPay'; }
	public function methods($s) { return array(array('card', 'Card'), array('wallet', 'Mobile wallet')); }
	public function field_schema() {
		return array(
			array('key' => 'pgw_map_email', 'type' => 'field', 'label' => 'Payer e-mail', 'field_types' => array('email'), 'required' => true),
			array('key' => 'pgw_map_phone', 'type' => 'field', 'label' => 'Mobile number', 'field_types' => array('mobile'), 'required_for' => array('wallet', 'BAD ID'), 'owner_message' => self::$owner,
				'visitor_message' => 'Enter your mobile number: it is needed to pay with a mobile wallet.'),
		);
	}
	public function messages() { return array('provider_unavailable' => 'ReqPay is not answering. Try the card instead.', 'BAD' => 'x'); }
}
add_action('efb_register_payment_gateways', function ($reg) { $reg->register(new Req_Gateway()); });
Registry::reset();
Settings::load('efbreq')->apply((new Req_Gateway())->settings_schema(), array('api_key__test' => 'mock_test_ABCDEF123', 'webhook_secret__test' => 'whs_unit_test_secret'), 'test');

function req_form($field = array(), $email_to = 'em') {
	return array(
		array('type' => 'payment', 'getway' => 'efbreq', 'currency' => 'EUR', 'email_to' => $email_to),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email'),
		array('id_' => 'mo', 'type' => 'mobile', 'name' => 'Mobile'),
		array_merge(array('id_' => 'pf', 'type' => 'efbreq', 'name' => 'Pay', 'pf_fixed' => '10'), $field),
	);
}

/* ---- owner: Save / builder / AutoPilot ---------------------------------------------- */
// Warnings of level info (Test mode, a mapped field not marked Required) are left out here (test-pay-error-scenarios.php checks them).
function req_w($form) { return array_values(array_filter(Registry::form_warnings($form), function ($x) { return $x['level'] !== 'info'; })); }
$w = req_w(req_form(array(), ''));
$m = array_column($w, 'm');
$t('nothing mapped: the e-mail (every payment) and the mobile (wallet only) are asked for', array_column($w, 'code'), array('pay_option_required', 'pay_option_required'));
$t('...actionable: the kind of field to add and the option to select it in', $m[0], 'ReqPay requires "Payer e-mail". Add an Email field to the form and select it in the payment field\'s "Payer e-mail" option.');
$t('...per method: names the method that needs it', $m[1], 'ReqPay requires "Mobile number" to pay with Mobile wallet. Add a phone number field to the form and select it in the payment field\'s "Mobile number" option.');
$t('the field offers only the card → no mobile warning', count(req_w(req_form(array('pf_methods' => 'card', 'pgw_map_email' => 'em')))), 0);
$t('the payer e-mail option empty but the form sends its confirmation to an Email field → no e-mail warning (core uses that field)', array_column(req_w(req_form(array('pf_methods' => 'card'))), 'code'), array());
Req_Gateway::$owner = 'Mobile wallets need a mobile number field: add one and pick it under "Mobile number".';
$t('the gateway\'s own owner_message wins', array_column(req_w(req_form(array('pgw_map_email' => 'em'))), 'm'), array(Req_Gateway::$owner));
Req_Gateway::$owner = '';
$schema = Registry::field_schema('efbreq');
$t('required_for keeps valid method ids only; the messages are plain text', array($schema[1]['required_for'], $schema[1]['visitor_message']), array(array('wallet'), 'Enter your mobile number: it is needed to pay with a mobile wallet.'));
$t('AutoPilot reads the same declaration (payment_gateways.<slug>.field_schema)', Registry::public_info('efbreq')['field_schema'][1]['required_for'], array('wallet'));

/* ---- visitor: Pay stops at the empty field -------------------------------------------- */
function req_create($db, $method, $rows, $field = array('pgw_map_email' => 'em', 'pgw_map_phone' => 'mo')) {
	$form = req_form($field);
	$fid = $db->add_form($form, 'payment');
	return Service::create(array('form_id' => $fid, 'form' => $form, 'rows' => $rows, 'field_id' => 'pf', 'method' => $method,
		'attempt_key' => sprintf('%08x-%04x-4%03x-8%03x-%012x', mt_rand(), mt_rand(0, 65535), mt_rand(0, 4095), mt_rand(0, 4095), mt_rand()), 'page_url' => '',
		'store_entry' => function ($rows) use ($db, $fid) { $id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2)); return array('track' => 'T' . $id, 'msg_id' => $id); }));
}
$n0 = $db->count('wp_emsfb_pmt_');
$r = req_create($db, 'wallet', array(array('id_' => 'em', 'type' => 'email', 'value' => 'a@example.test'), array('id_' => 'mo', 'type' => 'mobile', 'value' => '')));
$t('wallet without a mobile number → payer_missing on that field with the gateway\'s words; nothing created (old code: the provider\'s generic refusal)', array($r['success'], $r['code'], $r['field_id'], $r['m'], $db->count('wp_emsfb_pmt_') - $n0), array(false, 'payer_missing', 'mo', 'Enter your mobile number: it is needed to pay with a mobile wallet.', 0));
$r = req_create($db, 'card', array(array('id_' => 'em', 'type' => 'email', 'value' => 'a@example.test')));
$t('card without a mobile number → created (the mobile is required for the wallet only)', $r['success'], true);
$r = req_create($db, 'card', array(array('id_' => 'em', 'type' => 'email', 'value' => '  ')));
$t('an empty e-mail (required for every payment, no visitor text) → core\'s field-level message with the field\'s name', array($r['code'], $r['field_id'], $r['m']), array('payer_missing', 'em', 'Please fill in "Email": it is needed for the payment.'));
$r = req_create($db, 'wallet', array(array('id_' => 'em', 'type' => 'email', 'value' => 'a@example.test')), array('pgw_map_email' => 'em'));
$t('an option the owner did not map is not checked on the page (the owner was warned at Save)', $r['success'], true);

/* ---- error code → message map ---------------------------------------------------------- */
update_option('efb_mock_provider_down', 1);
$r = req_create($db, 'card', array(array('id_' => 'em', 'type' => 'email', 'value' => 'a@example.test')));
$t('Gateway::messages(): the gateway\'s own text for a core code (provider down → provider_unavailable)', array($r['code'], $r['m']), array('provider_unavailable', 'ReqPay is not answering. Try the card instead.'));
add_filter('efb_payment_message', function ($m, $code, $extra, $slug) { return $code === 'provider_unavailable' ? $m . ' [' . $slug . ']' : $m; });
$r = req_create($db, 'card', array(array('id_' => 'em', 'type' => 'email', 'value' => 'a@example.test')));
$t('...then the filter efb_payment_message (code, extra, gateway)', $r['m'], 'ReqPay is not answering. Try the card instead. [efbreq]');
update_option('efb_mock_provider_down', 0);
$GLOBALS['efb_filters']['efb_payment_message'] = array();
$t('a gateway without its own text keeps core\'s', Service::message('gateway_error', array('gateway' => 'efbmock')), 'The payment could not be started. You have not been charged. Please try again in a moment.');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
