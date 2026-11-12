<?php
/**
 * PAY06 (Paymob) sub-tasks A and B: registration, settings, Test connection and intention
 * creation of the Paymob add-on (vendor/paymob), against the fake Paymob of
 * tests/paymob-fake-provider.php (official example shapes, tests/fixtures/payments/paymob; no network).
 *  - registered through efb_register_payment_gateways as "paymob" (AdnPMB); supports(): redirect,
 *    refunds, no recurring, the currency of the account region, per-currency min/max; api_hosts are
 *    the five regional hosts;
 *  - settings: region, secret and public key per mode, API key and HMAC secret (encrypted),
 *    integration IDs per mode (method=ID), patterns; Test connection: shapes (mode, region), API
 *    key (auth token) and secret key + integration (an unpaid intention), refusals, network;
 *  - create: fixed (radio + fixed fee), donation, variable and computed amounts always from the
 *    server quote; EGP cents, OMR baisa (three decimals), PKR; integration IDs per method, "all",
 *    the owner's list wins, method / currency refused before Paymob; billing data from the entry
 *    (name, e-mail, mobile with Arabic-Indic digits); items that add up; special_reference = uid;
 *    notification and redirection URLs; Unified Checkout URL per region; live keys in Live;
 *  - secrets never in the form JSON, the browser answer, public_info, capabilities or the event log.
 *
 * Run: php tests/test-addon-paymob-create.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-paymob-lib.php';
$db = pmb_env();
use Emsfb\Payments\Registry;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Store;
use Emsfb\Payments\Service;
use Emsfb\Paymob\Paymob_Gateway;
use Emsfb\Paymob\Client;
$t = 'efb_pay_t';

$g = Registry::get('paymob');
$t('registered as paymob through efb_register_payment_gateways', $g instanceof Paymob_Gateway, true);
$t('add-on key AdnPMB, label Paymob', array($g->addon_key(), $g->label()), array('AdnPMB', 'Paymob'));
$t('no registration error', Registry::errors(), array());
$sup = $g->supports();
$t('supports: redirect only, refund + partial, no recurring, signature webhooks', array($sup['flows'], $sup['refund'], $sup['partial_refund'], $sup['recurring'], $sup['webhook_auth']), array(array('redirect'), true, true, false, 'signature'));
$t('supports: the Egypt account takes EGP (country EG)', array($sup['currencies'], $sup['countries']), array(array('EGP'), array('EG')));
$t('min/max in minor units: EGP 1.00 … 1 000 000.00', array($sup['min_minor']['EGP'], $sup['max_minor']['EGP']), array(100, 100000000));
$t('api_hosts: the five regional API hosts only', $g->api_hosts(), array('accept.paymob.com', 'ksa.paymob.com', 'uae.paymob.com', 'oman.paymob.com', 'pakistan.paymob.com'));
$t('the settings schema', array_map(function ($f) { return $f['key'] . ':' . $f['type'] . (!empty($f['per_mode']) ? ':per_mode' : '') . (!empty($f['required']) ? ':required' : ''); }, $g->settings_schema()),
	array('region:select:required', 'secret_key:secret:per_mode:required', 'public_key:text:per_mode:required', 'api_key:secret:required', 'hmac_secret:secret:required', 'integrations:text:per_mode:required'));
$t('region options eg, sa, ae, om, pk', array_keys($g->settings_schema()[0]['options']), array('eg', 'sa', 'ae', 'om', 'pk'));
$t('logos are local files of the add-on', count(array_filter($g->logos(), function ($l) { return strpos($l[0], 'vendor/paymob/assets/img/') !== false && substr($l[0], -4) === '.svg' && is_readable(pmb_addon_dir() . '/assets/img/' . basename($l[0])); })), 5);
$t('format_amount: the integer smallest unit (EGP cents, OMR baisa)', array($g->format_amount(60000, 'EGP'), $g->format_amount(12625, 'OMR')), array(60000, 12625));

// Settings and secrets.
$s = Settings::load('paymob');
$raw = json_encode($GLOBALS['opts']['emsfb_pgw_paymob']);
$t('secret keys, API key and HMAC secret are stored encrypted', strpos($raw, PmbFake::key('sk', 'test')) === false && strpos($raw, PmbFake::key('sk', 'live')) === false && strpos($raw, PmbFake::api_key()) === false && strpos($raw, PmbFake::hmac_secret()) === false && substr_count($raw, 'enc:v1:') >= 4, true);
$t('the secret of each mode decrypts; API key and HMAC secret are the same in both modes', array($s->secret('secret_key', 'test') === PmbFake::key('sk', 'test'), $s->secret('secret_key', 'live') === PmbFake::key('sk', 'live'), $s->secret('api_key', 'live') === PmbFake::api_key(), $s->secret('hmac_secret', 'test') === PmbFake::hmac_secret()), array(true, true, true, true));
$t('public key and integrations per mode', array($s->get('public_key', 'live') === PmbFake::key('pk', 'live'), $s->get('integrations', 'test')), array(true, 'card=1110001, wallet=1110002, kiosk=1110003, valu=1110004, meeza=1110005'));
$refused = $s->apply($g->settings_schema(), array('secret_key__test' => PmbFake::key('pk', 'test'), 'public_key__live' => PmbFake::key('sk', 'live'), 'integrations__test' => 'card=12, paypal=5', 'hmac_secret' => 'short', 'region' => 'xx'));
$t('a public key as secret, a secret key as public, an unknown method, a short HMAC secret are refused', $refused, array('secret_key__test', 'public_key__live', 'hmac_secret', 'integrations__test'));
$t('...the stored values are unchanged; an unknown region is emptied (select)', array(Settings::load('paymob')->secret('secret_key', 'test') === PmbFake::key('sk', 'test'), Settings::load('paymob')->get('integrations', 'test') !== '', Settings::load('paymob')->get('region')), array(true, true, ''));
pmb_settings('eg');
$t('configured in both modes', array($g->is_configured(Settings::load('paymob'), 'test'), $g->is_configured(Settings::load('paymob'), 'live')), array(true, true));
$t('integration_map: known methods only, first one wins', Client::integration_map('card=12, wallet=34,card=99 paypal=7 kiosk=0'), array('card' => 12, 'wallet' => 34));
$t('methods(): "all" first, then the owner\'s integrations in order (Test)', array_map(function ($m) { return $m[0]; }, $g->methods(Settings::load('paymob'))), array('all', 'card', 'wallet', 'kiosk', 'valu', 'meeza'));

// Test connection.
$r = $g->test_connection(Settings::load('paymob'), 'test');
$tok = PmbFake::last('POST', '/api/auth/tokens');
$int = PmbFake::last('POST', '/v1/intention/');
$t('Test connection (Test): ok; auth token from the API key, then an unpaid 10.00 EGP intention with the Test secret key and the first Test integration', array($r->ok, $tok['host'], $int['key_mode'], $int['body']['amount'], $int['body']['currency'], $int['body']['payment_methods']), array(true, 'accept.paymob.com', 'test', 1000, 'EGP', array(1110001)));
$r = $g->test_connection(Settings::load('paymob'), 'live');
$t('Test connection (Live): the live secret key and the first Live integration', array($r->ok, PmbFake::last('POST', '/v1/intention/')['key_mode'], PmbFake::last('POST', '/v1/intention/')['body']['payment_methods']), array(true, 'live', array(2220001)));
$n = count(PmbFake::log());
pmb_settings('eg', 'test', array('secret_key__live' => PmbFake::key('sk', 'test')));
$r = $g->test_connection(Settings::load('paymob'), 'live');
$t('a Test key in the Live field: wrong_mode, no request', array($r->ok, $r->error, strpos($r->message, 'Live') !== false, count(PmbFake::log()) - $n), array(false, 'wrong_mode', true, 0));
pmb_settings('sa', 'test', array('secret_key__test' => PmbFake::key('sk', 'test', 'egy'), 'public_key__test' => PmbFake::key('pk', 'test', 'egy')));
$r = $g->test_connection(Settings::load('paymob'), 'test');
$t('Egypt keys with the region Saudi Arabia: wrong_region (names the region), no request', array($r->error, strpos($r->message, 'Saudi Arabia') !== false, count(PmbFake::log()) - $n), array('wrong_region', true, 0));
pmb_settings('eg', 'test', array('api_key' => 'Revoked' . str_repeat('x', 20)));
$r = $g->test_connection(Settings::load('paymob'), 'test');
$t('an API key Paymob refuses (403): a clear message', array($r->ok, $r->error, $r->message), array(false, 'http_403', 'Paymob refused the API key. Copy it again from the Paymob dashboard.'));
pmb_settings('eg', 'test', array('integrations__test' => 'card=9990001'));
$r = $g->test_connection(Settings::load('paymob'), 'test');
$t('an integration ID Paymob does not know (404): secret key or integration refused', array($r->ok, $r->error, strpos($r->message, 'integration ID') !== false), array(false, 'http_404', true));
pmb_settings('eg', 'test', array('integrations__live' => ''));
$r = $g->test_connection(Settings::load('paymob'), 'live');
$t('no Live integration: no_integration; not configured for Live', array($r->error, $g->is_configured(Settings::load('paymob'), 'live')), array('no_integration', false));
pmb_settings('eg', 'test', array('hmac_secret' => '__clear'));
$t('no HMAC secret: no_key and not configured', array($g->test_connection(Settings::load('paymob'), 'test')->error, $g->is_configured(Settings::load('paymob'), 'test')), array('no_key', false));
pmb_settings('eg');
PmbFake::set_flag('down', true);
$t('Paymob unreachable: a network error code', strpos($g->test_connection(Settings::load('paymob'), 'test')->error, 'network:') === 0, true);
PmbFake::set_flag('down', false);
$t('Test connection keeps no auth token anywhere (options, transients)', strpos(json_encode(array(array_diff_key($GLOBALS['opts'], array(Efb_Test_Paymob_Fake::OPTION => 1)), $GLOBALS['efb_transients'])), 'FakeAuthToken') === false, true);

// Create: fixed amount (radio 500 + fee 100) in EGP, card.
$res = pmb_create($db);
$t('create: success, redirect to Unified Checkout (Egypt)', array($res['success'], $res['pay']['next']['type'], strpos($res['pay']['next']['url'], 'https://eg.checkout.paymob.com/?publicKey=' . PmbFake::key('pk', 'test') . '&clientSecret=egy_csk_test_') === 0), array(true, 'redirect', true));
$p = Store::find_by_uid($res['pay']['uid']);
$b = pmb_last_intention();
$t('the quote: 500 + 100 = 60000 piasters (the browser price 0.01 is ignored)', array($p['amount_minor'], $p['currency'], $b['amount'], $b['currency']), array(60000, 'EGP', 60000, 'EGP'));
$t('payment_methods = the card integration (Test), Token auth with the Test secret key', array($b['payment_methods'], PmbFake::last('POST', '/v1/intention/')['key_mode']), array(array(1110001), 'test'));
$t('items: the quote lines, adding up to the amount (names ≤ 50)', $b['items'], array(array('name' => 'Workshop: Workshop seat', 'amount' => 50000, 'quantity' => 1), array('name' => 'Materials', 'amount' => 10000, 'quantity' => 1)));
$t('billing_data from the entry: first / last name, e-mail, mobile (Arabic-Indic digits made Latin)', $b['billing_data'], array('first_name' => 'Mona', 'last_name' => 'Abdel Rahman', 'email' => 'payer@example.test', 'phone_number' => '01012345678'));
$t('special_reference = payment uid; extras without the key efb_uid', array($b['special_reference'], $b['extras']['efb_payment'], $b['extras']['track'], isset($b['extras']['efb_uid'])), array($p['uid'], $p['uid'], $p['track'], false));
$t('notification_url = webhook, redirection_url = signed return URL, expiration one hour', array($b['notification_url'], strpos($b['redirection_url'], 'payments/return/paymob?p=' . $p['uid'] . '&s=' . Service::sign($p['uid'], 'paymob')) !== false, $b['expiration']), array('https://site.example/wp-json/Emsfb/v1/payments/webhook/paymob', true, 3600));
$o = PmbFake::intention_of($p['gateway_ref']);
$t('payment pending, reference = the Paymob order id (intention_order_id), method card, expires in an hour', array($p['status'], $p['gateway_ref'], $p['method'], $p['expires_at'] !== null), array('pending', (string) $o['body']['intention_order_id'], 'card', true));
$t('the browser answer has no secret key, API key or HMAC secret; test_mode true', array(preg_match('/_sk_|FakePaymobApiKey|FakePaymobHmac/', json_encode($res)), $res['pay']['test_mode'], $res['pay']['total_minor']), array(0, true, 60000));

// The same attempt (double click): one intention.
$n = count(PmbFake::state()['intentions']);
$form = pmb_form();
$fid = $db->add_form($form, 'payment');
$args = array('form_id' => $fid, 'form' => $form, 'rows' => pmb_rows(), 'field_id' => 'pf', 'attempt_key' => '22222222-3333-4444-8555-666666666666', 'method' => 'card', 'payer_email' => 'payer@example.test',
	'store_entry' => function ($rows) use ($db, $fid) { $id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'EFB77777')); return array('track' => 'EFB77777', 'msg_id' => $id); });
$a1 = Service::create($args);
$a2 = Service::create($args);
$t('same attempt: one Paymob intention, the same answer', array(count(PmbFake::state()['intentions']) - $n, $a2['pay']['uid'] === $a1['pay']['uid'], !empty($a2['replay']), $a2['pay']['next']['url'] === $a1['pay']['next']['url']), array(1, true, true, true));
$p1 = Store::find_by_uid($a1['pay']['uid']);
$again = (new Paymob_Gateway())->create(new \Emsfb\Payments\Payment($p1), Settings::load('paymob'), new \Emsfb\Payments\Checkout_Context(array('method' => 'card', 'payer_email' => 'payer@example.test', 'return_url' => 'https://site.example/r', 'webhook_url' => 'https://site.example/w')));
$t('a retried create of the same payment: Paymob refuses the second intention (special_reference is unique), nothing new', array($again->ok, $again->error, count(PmbFake::state()['intentions']) - $n), array(false, 'http_400', 1));

// Methods.
$res = pmb_create($db, array('method' => 'wallet'));
$t('wallet → the wallet integration', pmb_last_intention()['payment_methods'], array(1110002));
$res = pmb_create($db, array('method' => 'all'));
$b = pmb_last_intention();
$t('"all" → every Test integration of the account (Paymob shows them all)', $b['payment_methods'], array(1110001, 1110002, 1110003, 1110004, 1110005));
$t('...with a kiosk integration among them: expiration three days', $b['expiration'], 259200);
$res = pmb_create($db, array('method' => 'kiosk'));
$p = Store::find_by_uid($res['pay']['uid']);
$t('kiosk → its integration, 72 h expiration, pending_ttl 73 h', array(pmb_last_intention()['payment_methods'], (new Paymob_Gateway())->pending_ttl(new \Emsfb\Payments\Payment($p))), array(array(1110003), 262800));
$res = pmb_create($db, array('method' => 'stcpay'));
$t('a method without an integration (stcpay in Egypt): "all"', pmb_last_intention()['payment_methods'], array(1110001, 1110002, 1110003, 1110004, 1110005));
$res = pmb_create($db, array('method' => '<b>x</b>'));
$t('a garbage method from the browser: "all"', array($res['success'], count(pmb_last_intention()['payment_methods'])), array(true, 5));
Settings::load('paymob')->apply($g->settings_schema(), array('integrations__test' => 'wallet=1110002'));
$res = pmb_create($db, array('method' => 'card'));
$t('one integration only (wallet): card from the browser → wallet; methods() has no "all"', array(pmb_last_intention()['payment_methods'], Store::find_by_uid($res['pay']['uid'])['method'], array_map(function ($m) { return $m[0]; }, $g->methods(Settings::load('paymob')))), array(array(1110002), 'wallet', array('wallet')));
$before = count(PmbFake::state()['intentions']);
$res = pmb_create($db, array('rows' => pmb_rows('ro1', array(), '')));
$ev = json_encode(Store::events((int) $db->get_var("SELECT id FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1")));
$t('wallet without any mobile number: refused before Paymob (needs_phone logged)', array($res['success'], count(PmbFake::state()['intentions']) - $before, strpos($ev, 'needs_phone') !== false), array(false, 0, true));
pmb_settings('eg');
$res = pmb_create($db, array('rows' => pmb_rows('ro1', array(), '')));
$t('card without a mobile number: phone_number "NA" (as Paymob\'s own plugins)', array($res['success'], pmb_last_intention()['billing_data']['phone_number']), array(true, 'NA'));
$res = pmb_create($db, array('rows' => pmb_rows('ro1', array(), '+20 (10) ۱۲۳۴-۵۶۷۸')));
$t('mobile "+20 (10) ۱۲۳۴-۵۶۷۸" → +201012345678', pmb_last_intention()['billing_data']['phone_number'], '+201012345678');
$noemail = pmb_form();
$noemail[0]['email_to'] = '';
$before = count(PmbFake::state()['intentions']);
$res = pmb_create($db, array('form' => $noemail, 'rows' => array(array('id_' => 'rad', 'id_ob' => 'ro1', 'type' => 'payRadio')), 'payer_email' => ''));
$t('no e-mail at all: refused before Paymob (needs_email)', array($res['success'], count(PmbFake::state()['intentions']) - $before, strpos(json_encode(Store::events((int) $db->get_var("SELECT id FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1"))), 'needs_email') !== false), array(false, 0, true));
$res = pmb_create($db, array('rows' => array(array('id_' => 'em', 'type' => 'email', 'value' => 'solo@example.test'), array('id_' => 'rad', 'id_ob' => 'ro2', 'type' => 'payRadio')), 'payer_email' => 'solo@example.test'));
$b = pmb_last_intention();
$t('no name field: first name from the e-mail, last name "NA"', array($b['billing_data']['first_name'], $b['billing_data']['last_name']), array('solo', 'NA'));
$t('75.50 + 100 = 17550 piasters', $b['amount'], 17550);

// Currencies and limits.
$r = pmb_create($db, array('form' => pmb_form('usd')));
$t('a currency the Egypt account does not take (USD): refused by the quote, no request', array($r['success'], $r['code']), array(false, 'currency_unsupported'));
$r = pmb_create($db, array('form' => pmb_form('egp', array('pf_fixed' => '2000000'))));
$t('above 1 000 000 EGP: refused before Paymob (above_max)', array($r['success'], $r['code']), array(false, 'above_max'));
$r = pmb_create($db, array('form' => pmb_form('egp', array('pf_fixed' => '')), 'rows' => array_slice(pmb_rows(), 0, 3)));
$t('nothing chosen and no fee: no_amount', array($r['success'], $r['code']), array(false, 'no_amount'));

// Donation.
$don = pmb_form('egp', array('pf_fixed' => '', 'pf_amount' => 'donation', 'pf_presets' => '50,100', 'pf_other' => '1', 'pf_min' => '10', 'pf_max' => '5000'));
$rows = array_merge(array_slice(pmb_rows(), 0, 3), array(array('id_' => 'pf', 'type' => 'paymob', 'value' => '۲۵۰.۷۵')));
$r = pmb_create($db, array('form' => $don, 'rows' => $rows));
$b = pmb_last_intention();
$t('donation 250.75 EGP typed in Persian digits → 25075, one item', array($r['success'], $b['amount'], count($b['items']), $b['items'][0]['amount']), array(true, 25075, 1, 25075));
$r = pmb_create($db, array('form' => $don, 'rows' => array_merge(array_slice(pmb_rows(), 0, 3), array(array('id_' => 'pf', 'type' => 'paymob', 'value' => '5')))));
$t('donation below the field minimum: refused, no intention', array($r['success'], $r['code']), array(false, 'pwyw_out_of_range'));

// Variable amount (prcfld).
$var = pmb_form('egp', array('pf_fixed' => ''));
array_splice($var, 8, 0, array(array('id_' => 'tip', 'type' => 'prcfld', 'name' => 'Amount', 'milen' => '10', 'mlen' => '1000', 'step' => 1)));
$r = pmb_create($db, array('form' => $var, 'rows' => pmb_rows('ro2', array(array('id_' => 'tip', 'type' => 'prcfld', 'value' => '20.25', 'price' => '1')))));
$t('variable amount (20.25 + option 75.50) → 9575', array($r['success'], pmb_last_intention()['amount']), array(true, 9575));

// Computed amount (B7) with the real Conditional Logic engine.
$vf = null;
foreach (array(__DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) if (is_readable($f)) { $vf = $f; break; }
if (!$vf) {
	echo "[SKIP] Conditional Logic validator not found: computed amount skipped\n";
} else {
	require_once $vf;
	$b7 = array(
		array('type' => 'payment', 'getway' => 'paymob', 'currency' => 'egp', 'email_to' => 'em', 'logic_rules' => array(array('id' => 'calc', 'enabled' => true, 'priority' => 1,
			'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'is_not_empty', 'value' => ''))),
			'actions' => array(array('type' => 'calculate', 'target' => 'total', 'value' => '{guests} * 150.5', 'decimals' => 2))))),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
		array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests', 'step' => 1),
		array('id_' => 'total', 'type' => 'prcfld', 'name' => 'Total', 'step' => 1),
		array('id_' => 'pf', 'type' => 'paymob', 'name' => 'Pay', 'step' => 1),
	);
	$rows = array(array('id_' => 'em', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => 'guests', 'id_ob' => 'guests_', 'type' => 'number', 'value' => '3'), array('id_' => 'total', 'type' => 'prcfld', 'value' => '1', 'price' => '1'));
	$r = pmb_create($db, array('form' => $b7, 'rows' => $rows));
	$t('computed (3 guests x 150.50 EGP) = 45150 although the browser said 1.00', array($r['success'], pmb_last_intention()['amount']), array(true, 45150));
}

// Live mode.
Settings::load('paymob')->set_mode('live');
$r = pmb_create($db);
$p = Store::find_by_uid($r['pay']['uid']);
$l = PmbFake::last('POST', '/v1/intention/');
$t('Live mode: the live secret key, the Live card integration, the live public key in the URL, test_mode false', array($l['key_mode'], $l['body']['payment_methods'], strpos($r['pay']['next']['url'], 'publicKey=' . PmbFake::key('pk', 'live')) !== false, $p['mode'], $r['pay']['test_mode']), array('live', array(2220001), true, 'live', false));
Settings::load('paymob')->set_mode('test');

// Regions: Oman (OMR, three decimals), Saudi Arabia, UAE, Pakistan.
pmb_settings('om');
$t('Oman: supports OMR, min 1.000 = 1000 baisa', array($g->supports()['currencies'], $g->supports()['min_minor']['OMR']), array(array('OMR'), 1000));
$om = pmb_form('omr', array('pf_fixed' => '0.125'));
$om[6]['price'] = '12.5';
$r = pmb_create($db, array('form' => $om, 'method' => 'omannet'));
$b = pmb_last_intention();
$l = PmbFake::last('POST', '/v1/intention/');
$t('Oman: 12.500 + 0.125 OMR → amount 12625 baisa (x1000), OmanNet integration, oman.paymob.com, om.checkout.paymob.com', array($r['success'], $b['amount'], $b['currency'], $b['payment_methods'], $l['host'], strpos($r['pay']['next']['url'], 'https://om.checkout.paymob.com/?publicKey=omn_pk_test_') === 0), array(true, 12625, 'OMR', array(4440002), 'oman.paymob.com', true));
$t('...items add up in baisa (12500 + 125)', array_map(function ($i) { return $i['amount']; }, $b['items']), array(12500, 125));
$r = pmb_create($db, array('form' => $om, 'method' => 'wallet'));
$t('Oman: wallet (not configured there) → "all" of the Oman account', pmb_last_intention()['payment_methods'], array(4440001, 4440002));
pmb_settings('sa');
$r = pmb_create($db, array('form' => pmb_form('sar'), 'method' => 'mada'));
$t('Saudi Arabia: SAR, mada integration, ksa.paymob.com, ksa.checkout.paymob.com', array($r['success'], pmb_last_intention()['payment_methods'], PmbFake::last('POST', '/v1/intention/')['host'], strpos($r['pay']['next']['url'], 'https://ksa.checkout.paymob.com/') === 0), array(true, array(3330002), 'ksa.paymob.com', true));
pmb_settings('ae');
$r = pmb_create($db, array('form' => pmb_form('aed'), 'method' => 'tabby'));
$t('UAE: AED, Tabby integration, uae.checkout.paymob.com', array($r['success'], pmb_last_intention()['payment_methods'], strpos($r['pay']['next']['url'], 'https://uae.checkout.paymob.com/') === 0), array(true, array(6660002), true));
pmb_settings('pk');
$r = pmb_create($db, array('form' => pmb_form('pkr'), 'method' => 'easypaisa'));
$t('Pakistan: PKR, Easypaisa integration, pakistan.paymob.com/unifiedcheckout/', array($r['success'], pmb_last_intention()['payment_methods'], PmbFake::last('POST', '/v1/intention/')['host'], strpos($r['pay']['next']['url'], 'https://pakistan.paymob.com/unifiedcheckout/?publicKey=pak_pk_test_') === 0), array(true, array(5550002), 'pakistan.paymob.com', true));
$gw = new Paymob_Gateway();
$cx = new \Emsfb\Payments\Checkout_Context(array('method' => 'wallet', 'payer_email' => 'a@example.test', 'return_url' => 'https://site.example/r', 'webhook_url' => 'https://site.example/w'));
$pp = new \Emsfb\Payments\Payment(array('uid' => 'U1', 'amount_minor' => 1000, 'currency' => 'PKR', 'mode' => 'test'));
$before = count(PmbFake::log());
Settings::load('paymob')->apply($g->settings_schema(), array('integrations__test' => 'wallet=5550009'));
$x = $gw->create($pp, Settings::load('paymob'), $cx);
$t('an Egyptian wallet integration on a PKR payment: method_currency, no request', array($x->ok, $x->error, count(PmbFake::log()) - $before), array(false, 'method_currency', 0));
pmb_settings('eg');

// Paymob refuses / answers badly.
pmb_settings('eg', 'test', array('integrations__test' => 'card=9990001'));
$r = pmb_create($db);
$t('an integration Paymob does not know (404 at creation): gateway_error, payment failed', array($r['success'], $r['code'], Store::get((int) $db->get_var("SELECT id FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1"))['status']), array(false, 'gateway_error', 'failed'));
pmb_settings('eg');
PmbFake::set_flag('down', true);
$r = pmb_create($db);
$t('Paymob unreachable at creation: provider_unavailable, nothing charged', array($r['success'], $r['code']), array(false, 'provider_unavailable'));
PmbFake::set_flag('down', false);
add_filter('efb_paymob_intention_body', function ($b) { $b['amount'] = 1; $b['currency'] = 'USD'; $b['extras']['coupon'] = 'X'; return $b; });
$r = pmb_create($db);
$b = pmb_last_intention();
$t('the efb_paymob_intention_body filter may add fields but never change amount or currency', array($r['success'], $b['amount'], $b['currency'], $b['extras']['coupon']), array(true, 60000, 'EGP', 'X'));
unset($GLOBALS['efb_filters']['efb_paymob_intention_body']);

// Optional core hooks (Gateway API 1: method extras, field_schema, field_defaults, form_warnings, Checkout_Context::$payer).
$ms = $g->methods(Settings::load('paymob'));
$t('methods(): local methods carry the currencies they take (wallet, kiosk, valU, Meeza: EGP); card and "all" none', array(count($ms[0]), count($ms[1]), $ms[2][2]['currencies'], $ms[3][2]['currencies']), array(2, 2, array('EGP'), array('EGP')));
if (method_exists('\Emsfb\Payments\Registry', 'normalize_methods')) {
	$egp = array_map(function ($m) { return $m[0]; }, \Emsfb\Payments\Registry::normalize_methods($ms, 'EGP'));
	$sar = array_map(function ($m) { return $m[0]; }, \Emsfb\Payments\Registry::normalize_methods($ms, 'SAR'));
	$t('core keeps every method for an EGP form and drops the Egyptian ones for a SAR form', array($egp, $sar), array(array('all', 'card', 'wallet', 'kiosk', 'valu', 'meeza'), array('all', 'card')));
}
if (method_exists('\Emsfb\Payments\Registry', 'field_schema')) {
	$fs = \Emsfb\Payments\Registry::field_schema('paymob');
	$t('field_schema (cleaned by core): pgw_map_phone and pgw_map_name, form-field pickers', array_map(function ($d) { return $d['key'] . ':' . $d['type']; }, $fs), array('pgw_map_phone:field', 'pgw_map_name:field'));
	$t('...the phone option is labelled "Mobile number (needed by Paymob)"', $fs[0]['label'], 'Mobile number (needed by Paymob)');
}
$t('field_defaults: the security note and the list style', $g->field_defaults(), array('texts' => array('secure' => 'Payments are processed by Paymob.'), 'pf' => array('pf_methods_style' => 'list')));
$nophone = pmb_form();
unset($nophone[4]);
$nophone = array_values($nophone);
$t('form_warnings: wallet offered and no mobile field → paymob_no_phone', array_map(function ($w) { return $w['code']; }, $g->form_warnings($nophone, $nophone[7], Settings::load('paymob'))), array('paymob_no_phone'));
$t('...no warning with a mobile field, with a mapped phone field, or when the field offers card only', array($g->form_warnings(pmb_form(), pmb_form()[8], Settings::load('paymob')), $g->form_warnings($nophone, array_merge($nophone[7], array('pgw_map_phone' => 'nm')), Settings::load('paymob')), $g->form_warnings($nophone, array_merge($nophone[7], array('pf_methods' => 'card')), Settings::load('paymob'))), array(array(), array(), array()));
$mapped = pmb_form('egp', array('pgw_map_phone' => 'alt'));
array_splice($mapped, 5, 0, array(array('id_' => 'alt', 'type' => 'text', 'name' => 'WhatsApp for Paymob', 'step' => 1)));
$r = pmb_create($db, array('form' => $mapped, 'rows' => pmb_rows('ro1', array(array('id_' => 'alt', 'type' => 'text', 'name' => 'WhatsApp for Paymob', 'value' => '011 2222 3333'))), 'method' => 'wallet'));
if (property_exists('\Emsfb\Payments\Checkout_Context', 'payer')) {
	$t('the mapped phone field (pgw_map_phone) wins over the first mobile field', array($r['success'], pmb_last_intention()['billing_data']['phone_number']), array(true, '01122223333'));
} else {
	$t('an older core without Checkout_Context::$payer: the first mobile field', array($r['success'], pmb_last_intention()['billing_data']['phone_number']), array(true, '01012345678'));
}

// Secrets never leave the server.
$info = Registry::public_info('paymob');
$ij = json_encode($info);
$t('public_info (builder / field) has no secret', !preg_match('/_sk_|FakePaymobApiKey|FakePaymobHmac|enc:v1/', $ij), true);
$t('public_info: configured per mode, methods with labels, currencies', array($info['configured'], $info['methods'][0], array_slice($info['methods'][2], 0, 2), $info['currencies']), array(array('test' => true, 'live' => true), array('all', 'All methods (choose on the Paymob page)'), array('wallet', 'Mobile wallet'), array('EGP')));
$t('the form JSON holds no key', !preg_match('/_sk_|_pk_|FakePaymob/', json_encode(pmb_form())), true);
if (method_exists('\Emsfb\Builder_Api', 'capabilities')) {
	$caps = json_encode(\Emsfb\Builder_Api::capabilities());
	$t('capabilities (AutoPilot / SitePilot) carry no secret', !preg_match('/_sk_(test|live)|FakePaymobApiKey|FakePaymobHmac|enc:v1/', $caps), true);
}
$log = json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_evt_', ARRAY_A));
$t('the event log never holds a key, API key, HMAC secret or auth token', !preg_match('/_sk_(test|live)|FakePaymobApiKey|FakePaymobHmac|FakeAuthToken/', $log), true);

// PAY00 optional hooks adopted: the submitted rows core hands to create() ($ctx->rows), not the stored entry.
$px = Store::find_by_uid(pmb_create($db)['pay']['uid']);
$gx = \Emsfb\Payments\Registry::get('paymob');
$ctx = new \Emsfb\Payments\Checkout_Context(array('return_url' => \Emsfb\Payments\Service::return_url($px), 'webhook_url' => \Emsfb\Payments\Service::webhook_url('paymob'), 'locale' => 'en_US', 'payer_email' => 'rows@example.test',
	'rows' => array(array('id_' => 'nmx', 'type' => 'text', 'name' => 'Full name', 'value' => 'Hana Fouad'), array('id_' => 'mox', 'type' => 'mobile', 'name' => 'Mobile', 'value' => '01098765432'))));
$gx->create(new \Emsfb\Payments\Payment(array_merge($px, array('msg_id' => 0, 'idem_key' => hash('sha256', 'adopt-pmb')))), Settings::load('paymob'), $ctx);
$bd = pmb_last_intention()['billing_data'];
$t('billing_data from $ctx->rows (no read of the stored entry)', array($bd['first_name'], $bd['last_name'], $bd['phone_number']), array('Hana', 'Fouad', '01098765432'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
