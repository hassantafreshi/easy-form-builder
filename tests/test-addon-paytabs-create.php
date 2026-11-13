<?php
/**
 * PAY05 (PayTabs) sub-tasks A and B: registration, regional settings, Test connection and payment
 * creation of the PayTabs add-on (vendor/paytabs), against a fake PayTabs on the seven regional
 * hosts built from the official examples (tests/fixtures/payments/paytabs; no network).
 *  - registered through efb_register_payment_gateways as "paytabs" (AdnPTB); supports(): redirect
 *    and embedded (framed), refunds, no recurring, 12 currencies, min 0.01 / max 9999999999.99;
 *    api_hosts() = the seven official hosts only;
 *  - settings: region, profile id and server key per mode (key encrypted), methods, framed,
 *    page language; Test connection: Test → sa host with the Test profile and key, Live → ae; a
 *    wrong region → "authentication" with the region hint; a profile of another key; no settings;
 *    PayTabs unreachable;
 *  - create: fixed (radio + fee), variable (prcfld), computed (B7, the real logic engine when
 *    present) and donation amounts always from the server quote; SAR two decimals, KWD / BHD
 *    three, written exactly ("cart_amount":200.00 / 12.625); cart_id = the payment uid;
 *    paypage_lang from the locale or the setting; payment_methods per method ("all" sends none);
 *    local schemes only in their currency; the owner's list wins; callback and return ≤ 255;
 *    customer_details from the entry; the answer checked (cart, amount, currency, page host);
 *    live mode uses the live region, profile and key;
 *  - secrets never in the form JSON, the browser answer, public_info, capabilities or the log.
 *
 * Run: php tests/test-addon-paytabs-create.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-paytabs-lib.php';
$db = ptb_env();
use Emsfb\Payments\Registry;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Store;
use Emsfb\Payments\Service;
use Emsfb\Paytabs\Paytabs_Gateway;
use Emsfb\Paytabs\Client;
$t = 'efb_pay_t';
$last_id = function () use ($db) { return (int) $db->get_var('SELECT id FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1'); };

/* A. Registration and settings */
$g = Registry::get('paytabs');
$t('registered as paytabs through efb_register_payment_gateways', $g instanceof Paytabs_Gateway, true);
$t('add-on key AdnPTB (the registry maps it to the folder paytabs), label PayTabs', array($g->addon_key(), Registry::addons()['AdnPTB'], $g->label()), array('AdnPTB', 'paytabs', 'PayTabs'));
$t('no registration error', Registry::errors(), array());
$sup = $g->supports();
$t('supports: redirect + embedded (framed), refund + partial, no recurring / authorize, signature callbacks', array($sup['flows'], $sup['refund'], $sup['partial_refund'], $sup['recurring'], $sup['authorize'], $sup['webhook_auth']), array(array('redirect', 'embedded'), true, true, false, false, 'signature'));
$t('supports: SAR AED EGP QAR USD EUR GBP KWD BHD OMR JOD IQD', $sup['currencies'], array('SAR', 'AED', 'EGP', 'QAR', 'USD', 'EUR', 'GBP', 'KWD', 'BHD', 'OMR', 'JOD', 'IQD'));
$t('min/max from PayTabs (0.01 … 9999999999.99) in minor units: SAR 1 … 999999999999, KWD 10 … 9999999999990', array($sup['min_minor']['SAR'], $sup['max_minor']['SAR'], $sup['min_minor']['KWD'], $sup['max_minor']['KWD']), array(1, 999999999999, 10, 9999999999990));
$t('api_hosts: exactly the seven official regional hosts', $g->api_hosts(), array('secure.paytabs.sa', 'secure.paytabs.com', 'secure-egypt.paytabs.com', 'secure-oman.paytabs.com', 'secure-jordan.paytabs.com', 'secure-iraq.paytabs.com', 'secure-global.paytabs.com'));
$t('the settings schema: region / profile / server key per mode (required), methods, framed, page language', array_map(function ($f) { return $f['key'] . ':' . $f['type'] . (!empty($f['per_mode']) ? ':per_mode' : '') . (!empty($f['required']) ? ':required' : ''); }, $g->settings_schema()),
	array('region:select:per_mode:required', 'profile_id:text:per_mode:required', 'server_key:secret:per_mode:required', 'methods:text', 'framed:checkbox', 'paypage_lang:select'));
$t('region options: sa ae eg om jo iq global, with names', array_keys($g->settings_schema()[0]['options']) === array('sa', 'ae', 'eg', 'om', 'jo', 'iq', 'global') && $g->settings_schema()[0]['options']['global'] === 'Other countries (global)', true);
$t('the region help says a wrong region causes authentication errors', strpos($g->settings_schema()[0]['help'], 'wrong region causes authentication errors') !== false, true);
$t('logos are local files of the add-on (9 marks)', count(array_filter($g->logos(), function ($l) { return strpos($l[0], 'vendor/paytabs/assets/img/') === strlen(EMSFB_PLUGIN_URL) && substr($l[0], -4) === '.svg' && is_readable(ptb_addon_dir() . '/assets/img/' . basename($l[0])); })), 9);
$t('the adapter script and style are local files of the add-on', array_map('basename', array_merge($g->public_assets()['js'], $g->public_assets()['css'])), array('paytabs-efb.js', 'paytabs-efb.css'));

$s = Settings::load('paytabs');
$raw = json_encode($GLOBALS['opts']['emsfb_pgw_paytabs']);
$t('server keys are stored encrypted (no plain key in the option)', strpos($raw, ptb_key('test')) === false && strpos($raw, ptb_key('live')) === false && strpos($raw, 'enc:v1:') !== false, true);
$t('per mode: region, profile and key of Test and Live', array($s->get('region', 'test'), $s->get('profile_id', 'test'), $s->secret('server_key', 'test'), $s->get('region', 'live'), $s->get('profile_id', 'live'), $s->secret('server_key', 'live')), array('sa', '10001', ptb_key('test'), 'ae', '10002', ptb_key('live')));
$refused = $s->apply($g->settings_schema(), array('region__test' => 'kw', 'profile_id__test' => '12a', 'server_key__test' => 'bad key!', 'methods' => 'mada,paypal', 'paypage_lang' => 'de'));
$t('an unknown region is not stored, a non-numeric profile, a malformed key and an unknown method are refused', array($refused, Settings::load('paytabs')->get('region', 'test')), array(array('profile_id__test', 'server_key__test', 'methods'), ''));
$t('...the region empty → not configured for Test, still for Live', array($g->is_configured(Settings::load('paytabs'), 'test'), $g->is_configured(Settings::load('paytabs'), 'live')), array(false, true));
$r = $g->test_connection(Settings::load('paytabs'), 'test');
$t('Test connection without a region: not_configured, no request', array($r->ok, $r->error, count(PtbFake::$requests)), array(false, 'not_configured', 0));
ptb_settings('test');
$t('configured in both modes again; the stored key is unchanged', array($g->is_configured(Settings::load('paytabs'), 'test'), Settings::load('paytabs')->secret('server_key', 'test')), array(true, ptb_key('test')));

// Test connection.
$r = $g->test_connection(Settings::load('paytabs'), 'test');
$req = PtbFake::last('/payment/query');
$t('Test connection (Test): ok "Connected to PayTabs (Saudi Arabia)", POST secure.paytabs.sa/payment/query with the Test profile and key', array($r->ok, $r->message, $req['host'], json_decode($req['body'], true)['profile_id'], $req['headers']['authorization']), array(true, 'Connected to PayTabs (Saudi Arabia).', 'secure.paytabs.sa', 10001, ptb_key('test')));
$t('...nothing created at PayTabs', count(PtbFake::$tx), 0);
$r = $g->test_connection(Settings::load('paytabs'), 'live');
$req = PtbFake::last('/payment/query');
$t('Test connection (Live): the UAE host secure.paytabs.com with the Live profile and key', array($r->ok, $req['host'], json_decode($req['body'], true)['profile_id'], $req['headers']['authorization']), array(true, 'secure.paytabs.com', 10002, ptb_key('live')));
ptb_settings('test', array('region__live' => 'eg'));
$r = $g->test_connection(Settings::load('paytabs'), 'live');
$t('a Live profile set to the wrong region (Egypt): authentication, the message points at the region', array($r->ok, $r->error, PtbFake::last('/payment/query')['host'], strpos($r->message, 'a wrong region causes this error') !== false), array(false, 'authentication', 'secure-egypt.paytabs.com', true));
ptb_settings('test', array('profile_id__live' => '10001'));
$r = $g->test_connection(Settings::load('paytabs'), 'live');
$t('the Test profile id with the Live key: profile refused', array($r->ok, $r->error), array(false, 'profile'));
ptb_settings('test', array('server_key__live' => 'S' . 'REVOKEDFAKE-KEY0000000-ZZZZZZZZZZ'));
$r = $g->test_connection(Settings::load('paytabs'), 'live');
$t('a key PayTabs refuses: authentication with a clear message', array($r->ok, $r->error, strpos($r->message, 'PayTabs refused this server key') === 0), array(false, 'authentication', true));
ptb_settings('test');
PtbFake::$down = true;
$t('PayTabs unreachable: a network error code', strpos($g->test_connection(Settings::load('paytabs'), 'test')->error, 'network:') === 0, true);
PtbFake::$down = false;

/* B. Create: fixed amount (radio 150 + fee 50) in SAR, all methods. */
$res = ptb_create($db);
$t('create: success, redirect to the PayTabs page on the Saudi host', array($res['success'], $res['pay']['next']['type'], strpos($res['pay']['next']['url'], 'https://secure.paytabs.sa/payment/page/') === 0), array(true, 'redirect', true));
$p = Store::find_by_uid($res['pay']['uid']);
list($b, $raw, $req) = ptb_last_request();
$t('the quote: 150 + 50 = 20000 halalas (the browser price 0.01 is ignored)', array($p['amount_minor'], $p['currency']), array(20000, 'SAR'));
$t('cart_amount written exactly with two decimals: "cart_amount":200.00', strpos($raw, '"cart_amount":200.00,') !== false, true);
$t('POST secure.paytabs.sa/payment/request with the Test key, profile 10001', array($req['host'], parse_url($req['url'], PHP_URL_PATH), $req['headers']['authorization'], $b['profile_id']), array('secure.paytabs.sa', '/payment/request', ptb_key('test'), 10001));
$t('sale / ecom, cart_id = payment uid, currency SAR, hide_shipping', array($b['tran_type'], $b['tran_class'], $b['cart_id'], $b['cart_currency'], $b['hide_shipping']), array('sale', 'ecom', $p['uid'], 'SAR', true));
$t('cart_description: the form text and the tracking code (≤ 128)', $b['cart_description'], 'Workshop registration ' . $p['track']);
$t('paypage_lang from the payer\'s locale (ar)', $b['paypage_lang'], 'ar');
$t('"all" sends no payment_methods (PayTabs shows every method of the profile)', isset($b['payment_methods']), false);
$t('not framed by default', isset($b['framed']), false);
$t('callback = webhook URL, return = signed return URL', array($b['callback'], $b['return']), array('https://site.example/wp-json/Emsfb/v1/payments/webhook/paytabs', Service::return_url($p)));
$t('callback and return are within PayTabs\' 255 characters', strlen($b['callback']) <= 255 && strlen($b['return']) <= 255, true);
$t('customer_details from the entry: the Arabic name (label الاسم) and the e-mail, nothing invented', $b['customer_details'], array('name' => 'فاطمة العتيبي', 'email' => 'payer@example.test'));
$t('user_defined: efb, form id, 32 chars of the idem key', $b['user_defined'], array('udf1' => 'efb', 'udf2' => (string) $p['form_id'], 'udf3' => substr($p['idem_key'], 0, 32)));
$t('payment pending with the tran_ref, method all, mode test', array($p['status'], $p['gateway_ref'], $p['method'], $p['mode']), array('pending', PtbFake::last('/payment/request') ? array_keys(PtbFake::$tx)[0] : '', 'all', 'test'));
$t('the browser answer has no key, test_mode true, total 20000', array(strpos(json_encode($res), ptb_key('test')) === false, $res['pay']['test_mode'], $res['pay']['total_minor']), array(true, true, 20000));

// The same attempt (double click): one transaction.
$n = count(PtbFake::$tx);
$form = ptb_form();
$fid = $db->add_form($form, 'payment');
$args = array('form_id' => $fid, 'form' => $form, 'rows' => ptb_rows(), 'field_id' => 'pf', 'attempt_key' => '22222222-3333-4444-8555-666666666666', 'method' => '', 'payer_email' => 'payer@example.test',
	'store_entry' => function ($rows) use ($db, $fid) { $id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'EFB77777')); return array('track' => 'EFB77777', 'msg_id' => $id); });
$a1 = Service::create($args);
$a2 = Service::create($args);
$t('same attempt twice: one PayTabs transaction, the same answer (replay)', array(count(PtbFake::$tx) - $n, $a2['pay']['uid'] === $a1['pay']['uid'], !empty($a2['replay'])), array(1, true, true));

// Methods and currencies.
ptb_settings('test', array('methods' => 'all,creditcard,mada,stcpay,applepay,knet,omannet,meezaqr,valu,aman,urpay,amex,unionpay,tabby,tamara,souhoola'));
$cases = array(
	array('sar', 'mada', true), array('kwd', 'mada', false), array('sar', 'stcpay', true), array('kwd', 'knet', true), array('sar', 'knet', false),
	array('omr', 'omannet', true), array('egp', 'meezaqr', true), array('egp', 'valu', true), array('usd', 'valu', false), array('egp', 'aman', true),
	array('usd', 'creditcard', true), array('aed', 'applepay', true), array('aed', 'tabby', true), array('jod', 'unionpay', true),
);
foreach ($cases as $c) {
	$before = count(PtbFake::$tx);
	$r = ptb_create($db, array('form' => ptb_form($c[0]), 'method' => $c[1]));
	if ($c[2]) {
		list($b) = ptb_last_request();
		$t(strtoupper($c[0]) . " + {$c[1]} → payment_methods [{$c[1]}]", array($r['success'], $b['payment_methods'], $b['cart_currency']), array(true, array($c[1]), strtoupper($c[0])));
	} else {
		$t(strtoupper($c[0]) . " + {$c[1]} is refused before PayTabs (scheme currency)", array($r['success'], $r['code'], count(PtbFake::$tx) - $before), array(false, 'method_unavailable', 0));
	}
}
ptb_create($db, array('form' => ptb_form('sar'), 'method' => 'knet'));
$t('...the refusal is logged as method_currency and the payment is failed', array(strpos(json_encode(Store::events($last_id())), 'method_currency') !== false, Store::get($last_id())['status']), array(true, 'failed'));
$r = ptb_create($db, array('form' => ptb_form('thb')));
$t('a currency the add-on does not send (THB): refused by core readiness (pay_not_ready), no request', array($r['success'], $r['code']), array(false, 'pay_not_ready'));
ptb_settings('test', array('methods' => 'mada,creditcard'));
$t('methods(): the owner\'s list in its order, with labels; a local scheme names its currency (core offers it only there and warns at Save)', $g->methods(Settings::load('paytabs')), array(array('mada', 'mada', array('currencies' => array('SAR'))), array('creditcard', 'Credit or debit card')));
ptb_create($db, array('method' => 'applepay'));
list($b) = ptb_last_request();
$t('a method the owner did not turn on (applepay) falls back to the first of the list (mada)', $b['payment_methods'], array('mada'));
ptb_settings('test', array('methods' => ''));
$t('empty method list = "all" only', $g->methods(Settings::load('paytabs')), array(array('all', 'All methods (choose on the PayTabs page)')));

// Three decimals.
$kwd = ptb_form('kwd', array('pf_fixed' => '0.125'));
$kwd[5]['price'] = '12.5';
$r = ptb_create($db, array('form' => $kwd));
list($b, $raw) = ptb_last_request();
$p = Store::find_by_uid($r['pay']['uid']);
$t('KWD: 12.500 + 0.125 = 12625 fils, "cart_amount":12.625 (three decimals, exact)', array($r['success'], $p['amount_minor'], strpos($raw, '"cart_amount":12.625,') !== false), array(true, 12625, true));
$don = ptb_form('bhd', array('pf_fixed' => '', 'pf_amount' => 'donation', 'pf_presets' => '5,10', 'pf_other' => '1', 'pf_min' => '1', 'pf_max' => '500'));
$drows = array(array('id_' => 'nm', 'type' => 'text', 'name' => 'Full name', 'value' => 'Ali Hassan'), array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'value' => 'donor@example.test'), array('id_' => 'pf', 'type' => 'paytabs', 'value' => '7.005'));
$r = ptb_create($db, array('form' => $don, 'rows' => $drows, 'payer_email' => 'donor@example.test'));
list($b, $raw) = ptb_last_request();
$t('donation 7.005 BHD → "cart_amount":7.005, customer Ali Hassan', array($r['success'], strpos($raw, '"cart_amount":7.005,') !== false, $b['customer_details']['name']), array(true, true, 'Ali Hassan'));
$r = ptb_create($db, array('form' => $don, 'rows' => array_merge(array_slice($drows, 0, 2), array(array('id_' => 'pf', 'type' => 'paytabs', 'value' => '0.5')))));
$t('donation below the field minimum: refused, no request', array($r['success'], $r['code']), array(false, 'pwyw_out_of_range'));
$r = ptb_create($db, array('form' => ptb_form('iqd', array('pf_fixed' => '25000')), 'rows' => ptb_rows('ro2')));
list($b, $raw) = ptb_last_request();
$t('IQD (three ISO decimals): 25020.750 → "cart_amount":25020.750', array($r['success'], strpos($raw, '"cart_amount":25020.750,') !== false), array(true, true));

// Variable amount (prcfld the visitor types, within its limits).
$var = ptb_form('sar', array('pf_fixed' => ''));
array_splice($var, 7, 0, array(array('id_' => 'tip', 'type' => 'prcfld', 'name' => 'Amount', 'milen' => '10', 'mlen' => '1000', 'step' => 1)));
$r = ptb_create($db, array('form' => $var, 'rows' => array_merge(ptb_rows('ro2'), array(array('id_' => 'tip', 'type' => 'prcfld', 'value' => '30.5', 'price' => '1')))));
list($b, $raw) = ptb_last_request();
$t('variable amount (prcfld 30.50 + option 20.75) → "cart_amount":51.25', array($r['success'], strpos($raw, '"cart_amount":51.25,') !== false), array(true, true));

// Computed amount (B7) with the real Conditional Logic engine.
$vf = null;
foreach (array(__DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) if (is_readable($f)) { $vf = $f; break; }
if (!$vf) {
	echo "[SKIP] Conditional Logic validator not found: computed amount skipped\n";
} else {
	require_once $vf;
	$b7 = array(
		array('type' => 'payment', 'getway' => 'paytabs', 'currency' => 'omr', 'email_to' => 'em', 'logic_rules' => array(array('id' => 'calc', 'enabled' => true, 'priority' => 1,
			'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'is_not_empty', 'value' => ''))),
			'actions' => array(array('type' => 'calculate', 'target' => 'total', 'value' => '{guests} * 4.125', 'decimals' => 3))))),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
		array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests', 'step' => 1),
		array('id_' => 'total', 'type' => 'prcfld', 'name' => 'Total', 'step' => 1),
		array('id_' => 'pf', 'type' => 'paytabs', 'name' => 'Pay', 'step' => 1),
	);
	$rows = array(array('id_' => 'em', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => 'guests', 'id_ob' => 'guests_', 'type' => 'number', 'value' => '3'), array('id_' => 'total', 'type' => 'prcfld', 'value' => '0.001', 'price' => '0.001'));
	$r = ptb_create($db, array('form' => $b7, 'rows' => $rows));
	list($b, $raw) = ptb_last_request();
	$t('computed (3 guests x 4.125 OMR) = "cart_amount":12.375 although the browser said 0.001', array($r['success'], strpos($raw, '"cart_amount":12.375,') !== false), array(true, true));
	$t('no name field: customer_details has the e-mail only', $b['customer_details'], array('email' => 'payer@example.test'));
}

// No e-mail and no name: no customer_details (PayTabs asks on its page).
$noemail = ptb_form();
$noemail[0]['email_to'] = '';
$r = ptb_create($db, array('form' => $noemail, 'rows' => array(array('id_' => 'rad', 'id_ob' => 'ro1', 'type' => 'payRadio')), 'payer_email' => ''));
list($b) = ptb_last_request();
$t('no e-mail, no name: created without customer_details', array($r['success'], isset($b['customer_details'])), array(true, false));

// Page language.
foreach (array(array('', 'fr_FR', 'fr'), array('', 'en_US', 'en'), array('', 'fa_IR', 'en'), array('ar', 'en_US', 'ar'), array('auto', 'ar_EG', 'ar')) as $c) {
	ptb_settings('test', array('paypage_lang' => $c[0]));
	ptb_create($db, array('locale' => $c[1]));
	list($b) = ptb_last_request();
	$t("paypage_lang: setting '{$c[0]}', locale {$c[1]} → {$c[2]}", $b['paypage_lang'], $c[2]);
}
ptb_settings('test', array('paypage_lang' => 'auto'));

// The answer is checked.
PtbFake::$tamper = function ($r) { $r['redirect_url'] = 'https://evil.example/payment/page/X'; return $r; };
$r = ptb_create($db);
$t('a redirect_url outside the PayTabs hosts: refused (no_payment_page), payment failed', array($r['success'], strpos(json_encode(Store::events($last_id())), 'no_payment_page') !== false, Store::get($last_id())['status']), array(false, true, 'failed'));
PtbFake::$tamper = function ($r) { $r['redirect_url'] = 'http://secure.paytabs.sa/payment/page/X'; return $r; };
$t('...http (not https) on a PayTabs host: refused', ptb_create($db)['success'], false);
PtbFake::$tamper = function ($r) { $r['cart_amount'] = '20.00'; return $r; };
$t('PayTabs echoes another amount: refused (amount_mismatch)', array(ptb_create($db)['success'], strpos(json_encode(Store::events($last_id())), 'amount_mismatch') !== false), array(false, true));
PtbFake::$tamper = function ($r) { $r['cart_currency'] = 'AED'; return $r; };
$t('PayTabs echoes another currency: refused', ptb_create($db)['success'], false);
PtbFake::$tamper = function ($r) { $r['cart_id'] = 'SomeoneElsesCart0000001'; return $r; };
$t('PayTabs echoes another cart_id: refused', ptb_create($db)['success'], false);
PtbFake::$tamper = function ($r) { return array('code' => 113, 'message' => 'cart_amount: invalid value', 'trace' => 'PMNT'); };
$r = ptb_create($db);
$t('a PayTabs error answer: gateway_error for the visitor, PayTabs\' message in the log', array($r['success'], $r['code'], strpos(json_encode(Store::events($last_id())), 'cart_amount: invalid value') !== false), array(false, 'gateway_error', true));
PtbFake::$tamper = null;
PtbFake::$down = true;
$r = ptb_create($db);
$t('PayTabs unreachable at creation: provider_unavailable, nothing charged', array($r['success'], $r['code'], Store::get($last_id())['status']), array(false, 'provider_unavailable', 'failed'));
PtbFake::$down = false;

// Return or callback address longer than 255 characters.
$GLOBALS['efb_filters']['efb_payment_create_args'][] = function ($ctx) { $ctx->webhook_url = 'https://site.example/' . str_repeat('a', 260); return $ctx; };
$before = count(PtbFake::$tx);
$r = ptb_create($db);
$t('a callback address over 255 characters: refused before PayTabs (url_too_long)', array($r['success'], count(PtbFake::$tx) - $before, strpos(json_encode(Store::events($last_id())), 'url_too_long') !== false), array(false, 0, true));
unset($GLOBALS['efb_filters']['efb_payment_create_args']);

// Framed.
ptb_settings('test', array('framed' => '1'));
$r = ptb_create($db);
list($b) = ptb_last_request();
$p = Store::find_by_uid($r['pay']['uid']);
$t('framed: framed true, the return is the site\'s small frame page (signed, ≤ 255)', array($b['framed'], $b['return'], strlen($b['return']) <= 255), array(true, 'https://site.example/?efb_ptb_frame=1&p=' . $p['uid'] . '&s=' . Service::sign($p['uid'], 'paytabs'), true));
$n = $r['pay']['next'];
$t('framed: next = embedded with the PayTabs page, the official hosts and the site origin (public data only)', array($n['type'], strpos($n['url'], 'https://secure.paytabs.sa/payment/page/') === 0, $n['client']['url'] === $n['url'], count($n['client']['hosts']), $n['client']['origin'], $n['client']['text']['title']), array('embedded', true, true, 7, 'https://site.example', 'PayTabs secure payment page'));
$t('framed: no key in the browser answer', strpos(json_encode($r), ptb_key('test')) === false, true);
ptb_settings('test', array('framed' => ''));

// Live mode.
Settings::load('paytabs')->set_mode('live');
$r = ptb_create($db);
$p = Store::find_by_uid($r['pay']['uid']);
list($b, $raw, $req) = ptb_last_request();
$t('Live mode: the UAE host, the Live profile and key, mode live, test_mode false, SFT reference', array($req['host'], $b['profile_id'], $req['headers']['authorization'], $p['mode'], $r['pay']['test_mode'], substr($p['gateway_ref'], 0, 3)), array('secure.paytabs.com', 10002, ptb_key('live'), 'live', false, 'SFT'));
$t('Live: redirect to the PayTabs page on the UAE host', strpos($r['pay']['next']['url'], 'https://secure.paytabs.com/payment/page/') === 0, true);
Settings::load('paytabs')->set_mode('test');

// Secrets never leave the server.
$info = Registry::public_info('paytabs');
$t('public_info (builder / field) has no secret', strpos(json_encode($info), ptb_key('test')) === false && strpos(json_encode($info), ptb_key('live')) === false && strpos(json_encode($info), 'enc:v1') === false, true);
$t('public_info: configured per mode, label PayTabs', array($info['configured'], $info['label']), array(array('test' => true, 'live' => true), 'PayTabs'));
$t('the form JSON holds no key or profile', strpos(json_encode(ptb_form()), ptb_key('test')) === false && strpos(json_encode(ptb_form()), '10001') === false, true);
if (method_exists('\Emsfb\Builder_Api', 'capabilities')) {
	$caps = json_encode(\Emsfb\Builder_Api::capabilities());
	$t('capabilities (AutoPilot / SitePilot) carry no secret', strpos($caps, ptb_key('test')) === false && strpos($caps, ptb_key('live')) === false && strpos($caps, 'enc:v1') === false, true);
}
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'https://site.example/wp-admin/' . $p; } }
$vars = json_encode(\Emsfb\Payments\Frontend::builder_vars());
$t('the builder\'s efb_var.pay carries no secret', strpos($vars, ptb_key('test')) === false && strpos($vars, 'enc:v1') === false, true);
$st = Service::status_answer(Store::find_by_uid($res['pay']['uid']), 'status');
$t('the status answer (REST payments/status) carries no secret or reference', strpos(json_encode($st), ptb_key('test')) === false && strpos(json_encode($st), 'TST') === false, true);
$log = json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_evt_', ARRAY_A));
$t('the event log never holds a key', strpos($log, ptb_key('test')) === false && strpos($log, ptb_key('live')) === false, true);

// PAY00 optional hooks adopted: the rows and the payer core hands to create(), Remote_State::mismatch().
$px = Store::find_by_uid(ptb_create($db)['pay']['uid']);
$ctx = new \Emsfb\Payments\Checkout_Context(array('return_url' => \Emsfb\Payments\Service::return_url($px), 'webhook_url' => \Emsfb\Payments\Service::webhook_url('paytabs'), 'locale' => 'en_US',
	'rows' => array(array('id_' => 'nm', 'type' => 'text', 'name' => 'Full name', 'value' => 'Noura Saleh')), 'payer' => array('email' => 'noura@example.test', 'name' => '')));
$noentry = new \Emsfb\Payments\Payment(array_merge($px, array('msg_id' => 0, 'attempt_key' => $px['attempt_key'], 'idem_key' => hash('sha256', 'adopt'))));
$g->create($noentry, Settings::load('paytabs'), $ctx);
$bb = ptb_last_request();
$bb = is_array($bb) && isset($bb[0]) ? $bb[0] : $bb;
$t('customer_details from $ctx->rows and $ctx->payer (no read of the stored entry)', isset($bb['customer_details']) ? $bb['customer_details'] : null, array('name' => 'Noura Saleh', 'email' => 'noura@example.test'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
