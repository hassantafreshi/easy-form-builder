<?php
/**
 * PAY00 optional gateway hooks (Gateway API 1, all optional), server side, with the test gateway:
 *  1 the browser adapter's token reaches create() as $ctx->client_token and is never stored or
 *    logged (payment row, events, entry, options); bad or long tokens are dropped; client_data limited
 *  2/5 field_schema(): the field's options and the payer (pgw_map_*) reach create() as $ctx->field,
 *    $ctx->payer and the read-only $ctx->rows; payer e-mail and name are stored on the payment
 *  3 methods with extras: per-method logo, currencies (methods_for / public_info by currency) and
 *    limits checked before create() with a specific message; Result::for_visitor() text shown
 *  4 field_defaults(): texts and style keys, the field's own values win
 *  6 form_warnings(): core's checks from the declarations plus the gateway's own
 *  7 settings card: notices, extra buttons, the gateway's own Test connection message
 *
 * Run: php tests/test-pay-hooks.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$db = efb_pay_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Registry;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Mock_Gateway;
use Emsfb\Payments\Mock_Provider;
use Emsfb\Payments\Frontend;
use Emsfb\Payments\Result;
$t = 'efb_pay_t';
if (!function_exists('get_user_locale')) { function get_user_locale() { return 'en_US'; } }

/** The test gateway with every optional hook a gateway add-on may use. */
class Hooks_Gateway extends Mock_Gateway {
	public static $ttl = 7200;
	public function slug() { return 'efbhooks'; }
	public function supports() { $s = parent::supports(); $s['currencies'] = array('EUR', 'USD'); return $s; }
	public function methods($s) {
		return array(
			array('card', 'Card', array('logo' => 'https://site.example/img/card.svg')),
			array('ideal', 'iDEAL', array('logo' => 'javascript:alert(1)', 'currencies' => array('EUR'), 'min_minor' => array('EUR' => 5000), 'max_minor' => array('EUR' => 900000))),
			array('usonly', 'US only', array('currencies' => array('usd'))),
			array('Bad Id', 'x'),
		);
	}
	public function field_schema() {
		return array(
			array('key' => 'pgw_map_email', 'type' => 'field', 'label' => 'Payer e-mail', 'field_types' => array('email'), 'required' => true),
			array('key' => 'pgw_map_first', 'type' => 'field', 'label' => 'First name', 'field_types' => array('text')),
			array('key' => 'pgw_map_last', 'type' => 'field', 'label' => 'Last name', 'field_types' => array('text')),
			array('key' => 'pgw_map_country', 'type' => 'field', 'label' => 'Country'),
			array('key' => 'pgw_region', 'type' => 'select', 'label' => 'Region', 'options' => array('eu' => 'Europe', 'us' => 'USA'), 'default' => 'eu'),
		);
	}
	public function field_defaults() {
		return array('texts' => array('btn' => 'Show the QR for %amount%', 'secure' => '<b>Scan</b> with your bank app', 'test' => 'never', 'nope' => 'x'),
			'pf' => array('pf_methods_style' => 'grid', 'pf_btn_icon' => 'bi-qr-code', 'pf_btn_text' => 'not a style key', 'pf_layout' => 'bogus'));
	}
	public function form_warnings(array $form, array $field, $s) {
		return array(array('code' => 'needs_country', 'm' => 'Hooks gateway works better with a <b>country</b> field.'), array('code' => 'BAD CODE', 'm' => 'x'), 'junk');
	}
	public function pending_ttl(\Emsfb\Payments\Payment $p) { return self::$ttl; }
}
add_action('efb_register_payment_gateways', function ($reg) { $reg->register(new Hooks_Gateway()); });
Registry::reset();
Settings::load('efbhooks')->apply((new Hooks_Gateway())->settings_schema(), array('api_key__test' => 'mock_test_ABCDEF123', 'webhook_secret__test' => 'whs_unit_test_secret'), 'test');

function hooks_form($cur = 'EUR', $field = array()) {
	return array(
		array('type' => 'payment', 'getway' => 'efbhooks', 'currency' => $cur, 'trackingCode' => '1', 'email_to' => 'em'),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email'),
		array('id_' => 'fn', 'type' => 'text', 'name' => 'First'),
		array('id_' => 'ln', 'type' => 'text', 'name' => 'Last'),
		array('id_' => 'co', 'type' => 'select', 'name' => 'Country'),
		array_merge(array('id_' => 'pf', 'type' => 'efbhooks', 'name' => 'Pay', 'pf_fixed' => '60', 'pf_methods' => 'ideal,card', 'pgw_map_email' => 'em', 'pgw_map_first' => 'fn', 'pgw_map_last' => 'ln', 'pgw_map_country' => 'co'), $field),
	);
}
function hooks_create($db, $extra = array(), $form = null) {
	$form = $form ?: hooks_form();
	$fid = $db->add_form($form, 'payment');
	$rows = array(array('id_' => 'em', 'type' => 'email', 'value' => 'ana@example.test'), array('id_' => 'fn', 'type' => 'text', 'value' => 'Ana María'), array('id_' => 'ln', 'type' => 'text', 'value' => 'de la Cruz'), array('id_' => 'co', 'type' => 'select', 'value' => 'NL'));
	return Service::create(array_merge(array(
		'form_id' => $fid, 'form' => $form, 'rows' => $rows, 'field_id' => 'pf', 'attempt_key' => sprintf('%08x-%04x-4%03x-8%03x-%012x', mt_rand(), mt_rand(0, 65535), mt_rand(0, 4095), mt_rand(0, 4095), mt_rand()),
		'method' => 'card', 'expected_minor' => null, 'page_url' => 'https://site.example/p/', 'locale' => 'en_US', 'ip' => '', 'payer_email' => '',
		'store_entry' => function ($rows) use ($db, $fid) { $id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'TRK' . mt_rand(10000, 99999))); return array('track' => $db->get_var('SELECT track FROM wp_emsfb_msg_ WHERE msg_id = ' . (int) $id), 'msg_id' => $id); },
	), $extra));
}

/* ---- 1. browser token --------------------------------------------------- */
$tok = 'tok_' . str_repeat('Ab9-_.', 20);
$res = hooks_create($db, array('client_token' => $tok, 'client_data' => array('brand' => 'visa', 'last4' => '4242', 'Bad Key' => 'x', 'nested' => array('a' => 1), 'long' => str_repeat('x', 300))));
$ctx = Mock_Gateway::$last_ctx;
$t('1: the adapter\'s token reaches create() as $ctx->client_token', $ctx && $res['success'] ? 'seen' : 'missing', 'seen');
$all = Mock_Provider::all(); $pp = end($all);
$t('1: ...and the provider got it (it keeps only a hash, like a real provider)', $pp['token_hash'], hash('sha256', $tok));
$t('1: core clears it from the context after create()', $ctx->client_token, '');
$t('1: client_data: flat scalar values, known-shaped keys, texts cut to 200', array($ctx->client_data['brand'], $ctx->client_data['last4'], isset($ctx->client_data['Bad Key']), isset($ctx->client_data['nested']), strlen($ctx->client_data['long'])), array('visa', '4242', false, false, 200));
$p = Store::find_by_uid($res['pay']['uid']);
$dump = json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_', ARRAY_A)) . json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_evt_', ARRAY_A)) . json_encode($db->get_results('SELECT * FROM wp_emsfb_msg_', ARRAY_A)) . json_encode($GLOBALS['opts']) . json_encode($res);
$t('1: the token is stored nowhere (payment row, events, entry, options, answer)', strpos($dump, 'Ab9-_.Ab9') === false, true);
foreach (array('bad token' => 'tok with space', 'too long' => str_repeat('a', 4097), 'control' => "tok\x01x", 'array' => array('x')) as $why => $bad) {
	hooks_create($db, array('client_token' => $bad));
	$t('1: a ' . $why . ' is dropped (create() sees "")', Mock_Gateway::$last_ctx->client_token, '');
}
$t('1: Service::client_token(): 4096 printable characters is the limit', array(Service::client_token(str_repeat('a', 4096)) !== '', Service::client_token('')), array(true, ''));
$d = hooks_create($db, array('client_token' => 'tok_declined'));
$t('3: a gateway refusal with Result::for_visitor() shows its own text (not the generic one)', array($d['success'], $d['code'], $d['m']), array(false, 'gateway_declined', 'The test card was declined.'));

/* ---- 2/5. field options, payer, rows ------------------------------------- */
$t('5: $ctx->payer from the mapped fields (name joined, country as typed, e-mail)', array($ctx->payer['first_name'], $ctx->payer['last_name'], $ctx->payer['name'], $ctx->payer['email'], $ctx->payer['country']), array('Ana María', 'de la Cruz', 'Ana María de la Cruz', 'ana@example.test', 'NL'));
$t('5: $ctx->rows: the submitted rows without the payment row', array(count($ctx->rows), $ctx->rows[0]['id_']), array(4, 'em'));
$t('2: $ctx->field: pf_methods and every schema option (default for an empty one)', $ctx->field, array('pf_methods' => 'ideal,card', 'pgw_map_email' => 'em', 'pgw_map_first' => 'fn', 'pgw_map_last' => 'ln', 'pgw_map_country' => 'co', 'pgw_region' => 'eu'));
$t('5: the payment row keeps the payer e-mail and name core resolved', array($p['payer_email'], $p['payer_name']), array('ana@example.test', 'Ana María de la Cruz'));
$t('5: the provider got the payer from the context (no entry read by the gateway)', $pp['payer'], array('email' => 'ana@example.test', 'name' => 'Ana María de la Cruz'));
$f2 = hooks_form(); unset($f2[6]['pgw_map_email']);
hooks_create($db, array('payer_email' => 'confirm@example.test'), $f2);
$t('5: without an e-mail mapping, the form\'s confirmation e-mail is the payer\'s', Mock_Gateway::$last_ctx->payer['email'], 'confirm@example.test');

/* ---- 3. methods: currency, logo, limits ----------------------------------- */
$eur = Registry::public_info('efbhooks', array('currency' => 'EUR'));
$usd = Registry::public_info('efbhooks', array('currency' => 'usd'));
$t('3: public_info by currency: a method for other currencies is dropped; a bad id is dropped', array(array_column($eur['methods'], 0), array_column($usd['methods'], 0)), array(array('card', 'ideal'), array('card', 'usonly')));
$t('3: a method keeps its safe logo; an unsafe one is dropped', array($eur['methods'][0][2]['logo'], isset($eur['methods'][1][2]['logo'])), array('https://site.example/img/card.svg', false));
$t('3: limits per method are kept for the page (EUR 50.00 to 9000.00 for iDEAL)', array($eur['methods'][1][2]['min_minor'], $eur['methods'][1][2]['max_minor']), array(array('EUR' => 5000), array('EUR' => 900000)));
$low = hooks_create($db, array('method' => 'ideal'), hooks_form('EUR', array('pf_fixed' => '20')));
$t('3: an amount below the chosen method\'s minimum → a message naming the method and its minimum, nothing created', array($low['success'], $low['code'], strpos($low['m'], 'iDEAL') !== false && strpos($low['m'], '50.00') !== false), array(false, 'method_below_min', true));
$ok = hooks_create($db, array('method' => 'card'), hooks_form('EUR', array('pf_fixed' => '20')));
$t('3: the same amount with a method without limits is created', $ok['success'], true);

/* ---- 4. defaults ------------------------------------------------------------ */
$t('4: field_defaults(): texts checked (no markup, no "test", unknown keys dropped)', $eur['defaults']['texts'], array('btn' => 'Show the QR for %amount%', 'secure' => 'b Scan /b with your bank app'));
$t('4: field_defaults(): only style keys with valid values', $eur['defaults']['pf'], array('pf_methods_style' => 'grid', 'pf_btn_icon' => 'bi-qr-code'));
$dt = Frontend::default_texts('Hooks', $eur['defaults']['texts']);
$t('4: the gateway\'s button text replaces core\'s "Pay %amount%"; the test banner stays core\'s', array($dt['btn'], $dt['test']), array('Show the QR for %amount%', 'Test mode: no real payment will be taken.'));
$GLOBALS['opts']['emsfb_pro'] = 3;
$html = Frontend::field_markup(hooks_form()[6], hooks_form()[0], 5);
$t('4: the public field uses them (grid, QR icon, gateway button text) with the method logos', array(strpos($html, 'data-style="grid"') !== false, strpos($html, 'bi-qr-code') !== false, strpos($html, 'Show the QR for') !== false, strpos($html, 'efb-pf-method-logo" src="https://site.example/img/card.svg"') !== false), array(true, true, true, true));
$own = Frontend::field_markup(hooks_form('EUR', array('pf_methods_style' => 'list', 'pf_btn_text' => 'Pay now'))[6], hooks_form()[0], 5);
$t('4: the field\'s own values win', array(strpos($own, 'data-style="list"') !== false, strpos($own, 'Pay now') !== false), array(true, true));

/* ---- 6. warnings ------------------------------------------------------------ */
$wf = hooks_form('THB'); unset($wf[6]['pgw_map_email']); $wf[6]['pgw_map_first'] = 'em';
$w = Registry::form_warnings($wf);
$codes = array_column($w, 'code');
$t('6: currency outside supports(), required option empty, wrong field type, plus the gateway\'s own', $codes, array('pay_currency', 'pay_option_required', 'pay_option_field_type', 'pay_gw_needs_country'));
$t('6: the messages name the gateway and the option; the gateway\'s own text is plain text', array(strpos($w[0]['m'], 'EUR, USD') !== false && strpos($w[0]['m'], 'THB') !== false, strpos($w[1]['m'], 'Payer e-mail') !== false, $w[3]['m']), array(true, true, 'Hooks gateway works better with a b country /b field.'));
$t('6: a form without a gateway field has no payment warnings', Registry::form_warnings(array(array('type' => 'form'), array('id_' => 'x', 'type' => 'text'))), array());

/* ---- 7. settings card -------------------------------------------------------- */
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-admin.php';
$g = Registry::get('efbmock');
$s = Settings::load('efbmock');
$t('7: settings notices and extra buttons from the gateway', array(\Emsfb\Payments\Admin::gateway_notices($g, $s), \Emsfb\Payments\Admin::gateway_actions($g, $s)), array(array(array('level' => 'info', 'm' => 'Test gateway: no real money moves.')), array(array('id' => 'ping', 'label' => 'Ping the mock provider'))));
$t('7: Test connection shows the gateway\'s own success message', \Emsfb\Payments\Admin::result_message($g->test_connection($s, 'test'), 'Connected.'), 'The mock provider answered (test).');
$t('7: ...and core\'s "Connected." when it has none', \Emsfb\Payments\Admin::result_message(Result::ok(), 'Connected.'), 'Connected.');
$t('7: an extra button runs the gateway\'s action', \Emsfb\Payments\Admin::result_message($g->run_settings_action('ping', $s, 'test'), 'Done.'), 'The mock provider answered (test).');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
