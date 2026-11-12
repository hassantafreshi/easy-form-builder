<?php
/**
 * PAY03 (Tap) sub-tasks A and B: registration, settings, test connection and charge creation of
 * the Tap Payments add-on (vendor/tappay), against a fake api.tap.company built from the official
 * examples (tests/fixtures/payments/tap; no network).
 *  - registered through efb_register_payment_gateways as "tappay" (AdnTAP), supports(): redirect,
 *    refunds, no recurring, the 11 currencies with known decimals, per-currency min/max;
 *  - settings: secret keys per mode (encrypted, pattern), merchant ids, method list; Test
 *    connection: sandbox key on Test, live key on Live, a key of the other mode, a refused key;
 *  - create: fixed (radio + fixed fee), computed (B7, with the real logic engine when present) and
 *    donation amounts always from the server quote; KWD three decimals written exactly (12.625),
 *    SAR two; source.id per method; mada / KNET / BENEFIT only in their currency; the owner's
 *    method list wins; reference.idempotent; customer name and e-mail from core's payer (mapping, else the confirmation e-mail and a "name" field of the rows); merchant id
 *    per country; redirect and post URLs; live mode uses the live key;
 *  - secrets never in the form JSON, the browser answer, public_info or capabilities.
 *
 * Run: php tests/test-addon-tappay-create.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-tappay-lib.php';
$db = tap_env();
use Emsfb\Payments\Registry;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Store;
use Emsfb\Payments\Service;
use Emsfb\Tappay\Tap_Gateway;
$t = 'efb_pay_t';

$g = Registry::get('tappay');
$t('registered as tappay through efb_register_payment_gateways', $g instanceof Tap_Gateway, true);
$t('add-on key AdnTAP, label Tap Payments', array($g->addon_key(), $g->label()), array('AdnTAP', 'Tap Payments'));
$t('no registration error', Registry::errors(), array());
$sup = $g->supports();
$t('supports: redirect only, refund + partial, no recurring, signature webhooks', array($sup['flows'], $sup['refund'], $sup['partial_refund'], $sup['recurring'], $sup['webhook_auth']), array(array('redirect'), true, true, false, 'signature'));
$t('supports: KWD BHD OMR JOD SAR AED QAR EGP USD EUR GBP', $sup['currencies'], array('KWD', 'BHD', 'OMR', 'JOD', 'SAR', 'AED', 'QAR', 'EGP', 'USD', 'EUR', 'GBP'));
$t('min/max per currency in minor units (KWD 0.100 … 100000.000, SAR 1.00 … 100000.00)', array($sup['min_minor']['KWD'], $sup['max_minor']['KWD'], $sup['min_minor']['SAR'], $sup['max_minor']['SAR']), array(100, 100000000, 100, 10000000));
$t('api_hosts is api.tap.company only', $g->api_hosts(), array('api.tap.company'));
$t('the settings schema: secret per mode (required, pattern), merchant ids, methods', array_map(function ($f) { return $f['key'] . ':' . $f['type'] . (!empty($f['per_mode']) ? ':per_mode' : '') . (!empty($f['required']) ? ':required' : ''); }, $g->settings_schema()),
	array('secret_key:secret:per_mode:required', 'merchant_id:text', 'merchant_ids:text', 'methods:text'));
$t('logos are local files of the add-on', count(array_filter($g->logos(), function ($l) { return strpos($l[0], 'vendor/tappay/assets/img/') !== false && substr($l[0], -4) === '.svg'; })), 6);
foreach ($g->logos() as $l) {
	$f = tap_addon_dir() . '/assets/img/' . basename($l[0]);
	if (!is_readable($f)) { $t('logo file ' . basename($l[0]) . ' exists', false, true); }
}

// Settings and secrets.
$s = Settings::load('tappay');
$raw = json_encode($GLOBALS['opts']['emsfb_pgw_tappay']);
$t('keys are stored encrypted (no plain key in the option)', strpos($raw, tap_key('test')) === false && strpos($raw, tap_key('live')) === false && strpos($raw, 'enc:v1:') !== false, true);
$t('the secret of each mode decrypts', array($s->secret('secret_key', 'test'), $s->secret('secret_key', 'live')), array(tap_key('test'), tap_key('live')));
$refused = $s->apply($g->settings_schema(), array('secret_key__test' => 'pk_test_notasecret0000', 'methods' => 'card,paypal', 'merchant_ids' => 'KW=1;drop'));
$t('a publishable key, an unknown method and a bad merchant map are refused', $refused, array('secret_key__test', 'merchant_ids', 'methods'));
$t('...and the stored secret is unchanged', Settings::load('tappay')->secret('secret_key', 'test'), tap_key('test'));
$t('configured in both modes', array($g->is_configured(Settings::load('tappay'), 'test'), $g->is_configured(Settings::load('tappay'), 'live')), array(true, true));

// Test connection.
$s = Settings::load('tappay');
$r = $g->test_connection($s, 'test');
$req = TapFake::last('POST', '/v2/charges/list');
$t('Test connection (Test): ok, POST /v2/charges/list with the sandbox key, nothing created', array($r->ok, $req['headers']['Authorization'] === 'Bearer ' . tap_key('test'), count(TapFake::$charges)), array(true, true, 0));
$r = $g->test_connection($s, 'live');
$t('Test connection (Live): ok with the live key', array($r->ok, TapFake::last('POST', '/v2/charges/list')['headers']['Authorization'] === 'Bearer ' . tap_key('live')), array(true, true));
$s2 = Settings::load('tappay');
$s2->apply($g->settings_schema(), array('secret_key__live' => tap_key('test')));
$r = $g->test_connection(Settings::load('tappay'), 'live');
$t('a sandbox key in the Live field: wrong_mode, no request', array($r->ok, $r->error, strpos($r->message, 'Live') !== false), array(false, 'wrong_mode', true));
$s2->apply($g->settings_schema(), array('secret_key__live' => 'sk' . '_live_' . 'RevokedFakeKey0003'));
$r = $g->test_connection(Settings::load('tappay'), 'live');
$t('a key Tap refuses (401): a clear message', array($r->ok, $r->error, $r->message), array(false, 'http_401', 'Tap refused this key. Copy the secret key again from the Tap dashboard.'));
$s2->apply($g->settings_schema(), array('secret_key__live' => '__clear'));
$t('no live key: no_key, not configured for Live', array($g->test_connection(Settings::load('tappay'), 'live')->error, $g->is_configured(Settings::load('tappay'), 'live')), array('no_key', false));
TapFake::$down = true;
$t('Tap unreachable: a network error code', strpos($g->test_connection(Settings::load('tappay'), 'test')->error, 'network:') === 0, true);
TapFake::$down = false;
$s2->apply($g->settings_schema(), array('secret_key__live' => tap_key('live')));

// Create: fixed amount (radio 12.5 + fee 0.125) in KWD, KNET.
$res = tap_create($db);
$t('create: success, redirect to Tap\'s hosted page', array($res['success'], $res['pay']['next']['type'], strpos($res['pay']['next']['url'], 'https://checkout.payments.tap.company') === 0), array(true, 'redirect', true));
$p = Store::find_by_uid($res['pay']['uid']);
list($b, $raw, $req) = tap_last_charge_body();
$t('the quote: 12.500 + 0.125 = 12625 fils (the browser price 0.001 is ignored)', array($p['amount_minor'], $p['currency']), array(12625, 'KWD'));
$t('the amount is written exactly with three decimals: "amount":12.625', strpos($raw, '"amount":12.625,') !== false, true);
$t('currency KWD, source src_kw.knet (KNET), 3-D Secure on, no saved card', array($b['currency'], $b['source']['id'], $b['threeDSecure'], $b['save_card']), array('KWD', 'src_kw.knet', true, false));
$t('reference: transaction = tracking code, order = payment uid, idempotent = 32 chars of the idem key', array($b['reference']['transaction'], $b['reference']['order'], $b['reference']['idempotent']), array($p['track'], $p['uid'], substr($p['idem_key'], 0, 32)));
$t('customer from the entry: first / last name and the e-mail field', $b['customer'], array('first_name' => 'Fatima', 'email' => 'payer@example.test', 'last_name' => 'Al Sabah'));
$t('metadata efb_uid / form / track', array($b['metadata']['efb_uid'], $b['metadata']['track']), array($p['uid'], $p['track']));
$t('redirect = signed return URL, post = webhook URL', array(strpos($b['redirect']['url'], 'payments/return/tappay?p=' . $p['uid'] . '&s=' . Service::sign($p['uid'], 'tappay')) !== false, $b['post']['url']), array(true, 'https://site.example/wp-json/Emsfb/v1/payments/webhook/tappay'));
$t('the sandbox key was used (mode test)', array($req['headers']['Authorization'], $p['mode']), array('Bearer ' . tap_key('test'), 'test'));
$t('payment pending with the chg_ reference and method knet', array($p['status'], strpos($p['gateway_ref'], 'chg_') === 0, $p['method']), array('pending', true, 'knet'));
$t('no merchant object without a merchant id', isset($b['merchant']), false);
$t('the browser answer has no key, no track, test_mode true, amount text with three decimals', array(strpos(json_encode($res), 'sk_') === false, isset($res['pay']['track']), $res['pay']['test_mode'], $res['pay']['total_minor']), array(true, false, true, 12625));

// The same attempt (double click): no second charge.
$n = count(TapFake::$charges);
$form = tap_form();
$fid = $db->add_form($form, 'payment');
$args = array('form_id' => $fid, 'form' => $form, 'rows' => tap_rows(), 'field_id' => 'pf', 'attempt_key' => '22222222-3333-4444-8555-666666666666', 'method' => 'knet', 'payer_email' => 'payer@example.test',
	'store_entry' => function ($rows) use ($db, $fid) { $id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'EFB77777')); return array('track' => 'EFB77777', 'msg_id' => $id); });
$a1 = Service::create($args);
$a2 = Service::create($args);
$t('same attempt: one Tap charge, the same answer', array(count(TapFake::$charges) - $n, $a2['pay']['uid'] === $a1['pay']['uid'], !empty($a2['replay'])), array(1, true, true));
$p1 = Store::find_by_uid($a1['pay']['uid']);
$tg = new Tap_Gateway();
$again = $tg->create(new \Emsfb\Payments\Payment($p1), Settings::load('tappay'), new \Emsfb\Payments\Checkout_Context(array('method' => 'knet', 'payer_email' => 'payer@example.test', 'return_url' => 'https://site.example/r', 'webhook_url' => 'https://site.example/w')));
$t('a retried create with the same idem key: Tap answers the first charge (reference.idempotent)', array($again->gateway_ref, count(TapFake::$charges) - $n), array($p1['gateway_ref'], 1));

// Methods and currencies.
$cases = array(
	array('sar', 'mada', 'src_sa.mada', true), array('kwd', 'mada', '', false), array('bhd', 'benefit', 'src_bh.benefit', true), array('sar', 'benefit', '', false),
	array('kwd', 'knet', 'src_kw.knet', true), array('usd', 'knet', '', false), array('usd', 'card', 'src_card', true), array('omr', 'all', 'src_all', true), array('kwd', '', 'src_all', true), array('kwd', 'bogus', 'src_all', true),
);
foreach ($cases as $c) {
	$before = count(TapFake::$charges);
	$r = tap_create($db, array('form' => tap_form($c[0]), 'method' => $c[1]));
	if ($c[3]) {
		list($b) = tap_last_charge_body();
		$t(strtoupper($c[0]) . " + '{$c[1]}' → {$c[2]}", array($r['success'], $b['source']['id'], $b['currency']), array(true, $c[2], strtoupper($c[0])));
	} else {
		$t(strtoupper($c[0]) . " + '{$c[1]}' is refused before any charge (scheme currency)", array($r['success'], $r['code'], count(TapFake::$charges) - $before), array(false, 'method_unavailable', 0));
	}
}
$r = tap_create($db, array('form' => tap_form('sar'), 'method' => 'knet'));
$ev = json_encode(Store::events((int) $db->get_var("SELECT id FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1")));
$t('...the refusal is logged as method_currency on the payment, which is failed', array(strpos($ev, 'method_currency') !== false, Store::get((int) $db->get_var("SELECT id FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1"))['status']), array(true, 'failed'));
$r = tap_create($db, array('form' => tap_form('thb')));
$t('a currency Tap does not take (THB): refused by the quote, no request', array($r['success'], $r['code']), array(false, 'currency_unsupported'));
$r = tap_create($db, array('form' => tap_form('kwd', array('pf_fixed' => '')), 'rows' => tap_rows('ro2')));
$t('0.750 KWD (no fee) is above the 0.100 minimum', $r['success'], true);
$r = tap_create($db, array('form' => tap_form('kwd', array('pf_fixed' => '200000'))));
$t('above the 100000.000 KWD maximum: refused before Tap (above_max)', array($r['success'], $r['code']), array(false, 'above_max'));

// The owner's method list wins.
$s3 = Settings::load('tappay');
$s3->apply($g->settings_schema(), array('methods' => 'knet,card'));
$t('methods(): the owner\'s list in its order', array_map(function ($m) { return $m[0]; }, $g->methods(Settings::load('tappay'))), array('knet', 'card'));
tap_create($db, array('method' => 'all'));
list($b) = tap_last_charge_body();
$t('a method the owner turned off (all) falls back to card', $b['source']['id'], 'src_card');
$s3->apply($g->settings_schema(), array('methods' => ''));
$t('empty method list = all five', count($g->methods(Settings::load('tappay'))), 5);

// Donation (visitor's amount within the field's limits) in BHD, three decimals.
$don = tap_form('bhd', array('pf_fixed' => '', 'pf_amount' => 'donation', 'pf_presets' => '5,10', 'pf_other' => '1', 'pf_min' => '1', 'pf_max' => '500'));
$rows = array(array('id_' => 'nm', 'type' => 'text', 'name' => 'Full name', 'value' => 'Ali'), array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'value' => 'donor@example.test'), array('id_' => 'pf', 'type' => 'tappay', 'value' => '7.125'));
$r = tap_create($db, array('form' => $don, 'rows' => $rows, 'method' => 'benefit', 'payer_email' => 'donor@example.test'));
list($b, $raw) = tap_last_charge_body();
$t('donation 7.125 BHD → "amount":7.125, BENEFIT', array($r['success'], strpos($raw, '"amount":7.125,') !== false, $b['source']['id'], $b['customer']['first_name']), array(true, true, 'src_bh.benefit', 'Ali'));
$r = tap_create($db, array('form' => $don, 'rows' => array_merge(array_slice($rows, 0, 2), array(array('id_' => 'pf', 'type' => 'tappay', 'value' => '0.5'))), 'method' => 'benefit'));
$t('donation below the field minimum: refused, no charge', array($r['success'], $r['code']), array(false, 'pwyw_out_of_range'));

// Variable amount: a prcfld the visitor types (within Min/Max) in SAR.
$var = tap_form('sar', array('pf_fixed' => ''));
array_splice($var, 7, 0, array(array('id_' => 'tip', 'type' => 'prcfld', 'name' => 'Amount', 'milen' => '10', 'mlen' => '1000', 'step' => 1)));
$r = tap_create($db, array('form' => $var, 'rows' => array_merge(tap_rows('ro2'), array(array('id_' => 'tip', 'type' => 'prcfld', 'value' => '20.5', 'price' => '1'))), 'method' => 'mada'));
list($b, $raw) = tap_last_charge_body();
$t('variable amount (prcfld 20.50 + option 0.75) in SAR → "amount":21.25, mada', array($r['success'], strpos($raw, '"amount":21.25,') !== false, $b['source']['id']), array(true, true, 'src_sa.mada'));

// Computed amount (B7) with the real Conditional Logic engine.
$vf = null;
foreach (array(__DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) if (is_readable($f)) { $vf = $f; break; }
if (!$vf) {
	echo "[SKIP] Conditional Logic validator not found: computed amount skipped\n";
} else {
	require_once $vf;
	$b7 = array(
		array('type' => 'payment', 'getway' => 'tappay', 'currency' => 'kwd', 'email_to' => 'em', 'logic_rules' => array(array('id' => 'calc', 'enabled' => true, 'priority' => 1,
			'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'is_not_empty', 'value' => ''))),
			'actions' => array(array('type' => 'calculate', 'target' => 'total', 'value' => '{guests} * 2.125', 'decimals' => 3))))),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
		array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests', 'step' => 1),
		array('id_' => 'total', 'type' => 'prcfld', 'name' => 'Total', 'step' => 1),
		array('id_' => 'pf', 'type' => 'tappay', 'name' => 'Pay', 'step' => 1),
	);
	$rows = array(array('id_' => 'em', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => 'guests', 'id_ob' => 'guests_', 'type' => 'number', 'value' => '3'), array('id_' => 'total', 'type' => 'prcfld', 'value' => '0.001', 'price' => '0.001'));
	$r = tap_create($db, array('form' => $b7, 'rows' => $rows));
	list($b, $raw) = tap_last_charge_body();
	$t('computed (3 guests x 2.125 KWD) = 6.375 although the browser said 0.001', array($r['success'], strpos($raw, '"amount":6.375,') !== false), array(true, true));
	$t('no name field: first name from the e-mail, no last name', array($b['customer']['first_name'], isset($b['customer']['last_name'])), array('payer', false));
}

// No e-mail at all: Tap cannot be asked.
$noemail = tap_form();
$noemail[0]['email_to'] = '';
$before = count(TapFake::$charges);
$r = tap_create($db, array('form' => $noemail, 'rows' => array(array('id_' => 'nm', 'type' => 'text', 'name' => 'Full name', 'value' => 'X'), array('id_' => 'rad', 'id_ob' => 'ro1', 'type' => 'payRadio')), 'payer_email' => ''));
$t('a form without an e-mail: refused before Tap (needs_name_email logged)', array($r['success'], count(TapFake::$charges) - $before, strpos(json_encode(Store::events((int) $db->get_var("SELECT id FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1"))), 'needs_name_email') !== false), array(false, 0, true));

// Merchant id per country.
Settings::load('tappay')->apply($g->settings_schema(), array('merchant_id' => '599424', 'merchant_ids' => 'KW=111111, SA=222222'));
tap_create($db, array('form' => tap_form('kwd')));
list($b) = tap_last_charge_body();
$t('KWD form → merchant.id of KW', $b['merchant'], array('id' => '111111'));
tap_create($db, array('form' => tap_form('sar'), 'method' => 'card'));
list($b) = tap_last_charge_body();
$t('SAR form → merchant.id of SA', $b['merchant'], array('id' => '222222'));
tap_create($db, array('form' => tap_form('usd'), 'method' => 'card'));
list($b) = tap_last_charge_body();
$t('USD form → the default merchant id', $b['merchant'], array('id' => '599424'));
Settings::load('tappay')->apply($g->settings_schema(), array('merchant_id' => '', 'merchant_ids' => ''));

// Live mode.
Settings::load('tappay')->set_mode('live');
$r = tap_create($db);
$p = Store::find_by_uid($r['pay']['uid']);
list($b, $raw, $req) = tap_last_charge_body();
$t('Live mode: the live key, mode live, test_mode false', array($req['headers']['Authorization'], $p['mode'], $r['pay']['test_mode']), array('Bearer ' . tap_key('live'), 'live', false));
Settings::load('tappay')->set_mode('test');

// Tap answers an error / a declined charge at once.
TapFake::$create_status = 'DECLINED';
$r = tap_create($db);
$t('a charge Tap declines at creation: declined (its own words for the visitor), payment failed', array($r['success'], $r['code'], Store::get((int) $db->get_var("SELECT id FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1"))['status']), array(false, 'declined', 'failed'));
TapFake::$create_status = 'INITIATED';
TapFake::$down = true;
$r = tap_create($db);
$t('Tap unreachable at creation: provider_unavailable, nothing charged', array($r['success'], $r['code']), array(false, 'provider_unavailable'));
TapFake::$down = false;

// Secrets never leave the server.
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-frontend.php';
$info = Registry::public_info('tappay');
$t('public_info (builder / field) has no secret', strpos(json_encode($info), 'sk_') === false && strpos(json_encode($info), 'enc:v1') === false, true);
$t('public_info: configured per mode, methods with labels', array($info['configured'], $info['methods'][0]), array(array('test' => true, 'live' => true), array('all', 'All methods (choose on the Tap page)')));
$form_json = json_encode(tap_form());
$t('the form JSON holds no key', strpos($form_json, 'sk_') === false, true);
if (method_exists('\Emsfb\Builder_Api', 'capabilities')) {
	$caps = json_encode(\Emsfb\Builder_Api::capabilities());
	$t('capabilities (AutoPilot / SitePilot) carry no secret', strpos($caps, tap_key('test')) === false && strpos($caps, tap_key('live')) === false && strpos($caps, 'enc:v1') === false, true);
}
$log = json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_evt_', ARRAY_A));
$t('the event log never holds a key', strpos($log, tap_key('test')) === false && strpos($log, tap_key('live')) === false, true);

// PAY00 optional hooks.
$gw = \Emsfb\Payments\Registry::get('tappay');
$kwd = \Emsfb\Payments\Registry::public_info('tappay', array('currency' => 'KWD'));
$sar = \Emsfb\Payments\Registry::public_info('tappay', array('currency' => 'SAR'));
$t('methods by currency (core): KWD keeps KNET, drops mada and BENEFIT; SAR keeps mada', array(array_column($kwd['methods'], 0), array_column($sar['methods'], 0)), array(array('all', 'card', 'knet'), array('all', 'card', 'mada')));
$t('each local scheme tile has its own mark', array(substr($kwd['methods'][2][2]['logo'], -8), substr($sar['methods'][2][2]['logo'], -8)), array('knet.svg', 'mada.svg'));
$t('field_schema(): payer name and e-mail pickers (e-mail fields only for the e-mail)', array(array_column($gw->field_schema(), 'key'), $gw->field_schema()[1]['field_types']), array(array('pgw_map_name', 'pgw_map_email'), array('email')));
$noemail = tap_form(); unset($noemail[0]['email_to']); unset($noemail[3]);
$t('form_warnings(): Tap has none of its own any more: the required e-mail is core\'s check (field_schema required + format)', array(count($gw->form_warnings(array_values($noemail), $noemail[7], Settings::load('tappay'))), $gw->field_schema()[1]['required'], $gw->field_schema()[1]['format']), array(0, true, 'email'));
$w = array_values(array_filter(\Emsfb\Payments\Registry::form_warnings(array_values($noemail)), function ($x) { return $x['level'] !== 'info'; }));
$t('...a form without any e-mail → core\'s error pay_option_required naming the option to set', array(array_column($w, 'code'), array_column($w, 'level'), $w[0]['m']), array(array('pay_option_required'), array('error'), 'Tap Payments requires "Payer e-mail". Add an Email field to the form and select it in the payment field\'s "Payer e-mail" option.'));
$t('...the form\'s confirmation e-mail field counts (no warning)', array_column(array_filter(\Emsfb\Payments\Registry::form_warnings(tap_form()), function ($x) { return $x['level'] !== 'info'; }), 'code'), array());
$mapped = tap_create($db, array('form' => tap_form('kwd', array('pgw_map_name' => 'nk', 'pgw_map_email' => 'em2')), 'rows' => tap_rows('ro1', array(array('id_' => 'nk', 'type' => 'text', 'name' => 'Contact', 'value' => 'Noor Haddad'), array('id_' => 'em2', 'type' => 'email', 'name' => 'Work e-mail', 'value' => 'noor@example.test')))));
list($cb) = tap_last_charge_body();
$t('the payer comes from the mapped fields core resolved ($ctx->payer), not from the stored entry', array($mapped['success'], $cb['customer']['first_name'], $cb['customer']['last_name'], $cb['customer']['email']), array(true, 'Noor', 'Haddad', 'noor@example.test'));
tap_create($db, array('form' => tap_form('kwd', array('pf_methods' => 'knet,mada')), 'method' => ''));
list($cb) = tap_last_charge_body();
$t('the field allows KNET and mada, the form is in KWD, no method from the page → KNET sent (the field\'s pf_methods from $ctx->field)', $cb['source']['id'], 'src_kw.knet');
$t('Test connection names the result (core shows the gateway\'s own message)', $gw->test_connection(Settings::load('tappay'), 'test')->message, 'Connected to Tap.');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
