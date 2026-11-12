<?php
/**
 * PAY04 Omise add-on, sub-tasks A and B (create): registration, settings, Test connection, and the
 * PromptPay charge, against a fake api.omise.co (pre_http_request, fixtures from the official
 * examples in tests/fixtures/payments/omise/). No network, no real key.
 *  - the add-on registers through its own routes-efb.php (key AdnOMS, field type omise, flows qr,
 *    THB only, 2000–15000000 satang);
 *  - keys are stored encrypted, never in the form JSON, the public info, the capabilities or an answer;
 *  - Test connection: GET /account with the mode's secret key (Live key in Test refused before any
 *    request), 401 → "Omise refused the key", GET /capability with the public key → PromptPay off;
 *  - create: the server amount (fixed, computed, donation; a browser price is ignored) in satang,
 *    THB, source[type]=promptpay, ref_id = the payment's idem key, expires_at = now + QR minutes,
 *    metadata efb_uid; the QR fetched only from api.omise.co, cleaned, sent as a data: URI;
 *  - other currencies (JPY zero-decimal, KWD three-decimal) and amounts outside the PromptPay
 *    range are refused before Omise is called;
 *  - Test and Live keys: each payment uses the keys of its own mode;
 *  - what Omise created must match (amount, currency, mode), the QR host and redirect rules,
 *    the SVG cleaner against hostile input.
 *
 * Run: php tests/test-addon-omise-create.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-omise-test-lib.php';
$db = efb_omise_env('test');
use Emsfb\Payments\Registry;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Pricing;
use Emsfb\Payments\Omise\Omise_Api;
$t = 'efb_pay_t';
$K = efb_omise_keys();
$ALL_KEYS = '/' . implode('|', array_map('preg_quote', array_values($K))) . '|enc:v1/';

/* ---- A: registration and settings ---- */
$g = Registry::get('omise');
$t('the add-on registers Omise_Gateway through routes-efb.php', $g instanceof \Emsfb\Payments\Omise\Omise_Gateway, true);
$t('slug omise, add-on key AdnOMS, label Omise, AdnOMS → omise in core', array($g->slug(), $g->addon_key(), $g->label(), Registry::addons()['AdnOMS']), array('omise', 'AdnOMS', 'Omise', 'omise'));
$sup = $g->supports();
$t('supports: QR flow, THB only, 20–150,000 baht, no API refund, signature webhooks', array($sup['flows'], $sup['currencies'], $sup['min_minor'], $sup['max_minor'], $sup['refund'], $sup['webhook_auth']), array(array('qr'), array('THB'), array('THB' => 2000), array('THB' => 15000000), false, 'signature'));
$t('api_hosts() is the SSRF allow-list: api.omise.co, vault.omise.co', $g->api_hosts(), array('api.omise.co', 'vault.omise.co'));
$t('method PromptPay, local logo from the add-on folder', array($g->methods(Settings::load('omise'))[0][0], substr($g->logos()[0][0], -24)), array('promptpay', 'assets/img/promptpay.svg'));
$schema = array_column($g->settings_schema(), null, 'key');
$t('settings: secret key (secret, per mode, required), public key (text), webhook secret (secret), QR minutes, per-charge webhook', array($schema['secret_key']['type'], $schema['secret_key']['per_mode'], $schema['secret_key']['required'], $schema['public_key']['type'], $schema['webhook_secret']['type'], isset($schema['qr_ttl_minutes'], $schema['webhook_per_charge'])), array('secret', true, true, 'text', 'secret', true));
$t('the webhook help names the REST address', strpos($schema['webhook_secret']['help'], 'https://site.example/wp-json/Emsfb/v1/payments/webhook/omise') !== false, true);
$raw = json_encode($GLOBALS['opts']['emsfb_pgw_omise']);
$t('secret and webhook keys stored encrypted (not readable in the option)', preg_match('/skey_|' . preg_quote($K['whsec_test'], '/') . '/', $raw) === 0 && strpos($raw, 'enc:v1:') !== false, true);
$s = Settings::load('omise');
$t('...and readable per mode', array($s->secret('secret_key', 'test'), $s->secret('secret_key', 'live')), array($K['skey_test'], $K['skey_live']));
$refused = $s->apply($g->settings_schema(), array('secret_key__test' => 'sk_test_notomise123456', 'public_key__live' => 'publickey'));
$t('a key that is not an Omise key is refused (pattern), the stored one kept', array($refused, Settings::load('omise')->secret('secret_key', 'test')), array(array('secret_key__test', 'public_key__live'), $K['skey_test']));
$t('configured in both modes', array($g->is_configured(Settings::load('omise'), 'test'), $g->is_configured(Settings::load('omise'), 'live')), array(true, true));
$t('QR minutes: default 15; 30 kept; 2 → 5; 5000 → 1440', (function () use ($g) {
	$o = array($g->qr_ttl(Settings::load('omise')));
	foreach (array('30', '2', '5000') as $v) { $s = Settings::load('omise'); $s->apply($g->settings_schema(), array('qr_ttl_minutes' => $v)); $o[] = $g->qr_ttl(Settings::load('omise')); }
	Settings::load('omise')->apply($g->settings_schema(), array('qr_ttl_minutes' => ''));
	return $o;
})(), array(15, 30, 5, 1440));

/* ---- A: Test connection ---- */
$GLOBALS['omise']['calls'] = array();
$r = $g->test_connection(Settings::load('omise'), 'test');
$c = efb_omise_calls();
$t('Test connection (Test): ok after GET /account with the test secret key and GET /capability with the test public key', array($r->ok, count($c), $c[0]['url'], $c[0]['key'], $c[1]['url'], $c[1]['key']), array(true, 2, 'https://api.omise.co/account', $K['skey_test'], 'https://api.omise.co/capability', $K['pkey_test']));
$t('...Basic auth with the key as user name, Omise-Version fixed', array($c[0]['headers']['Authorization'], $c[0]['headers']['Omise-Version']), array('Basic ' . base64_encode($K['skey_test'] . ':'), '2019-05-29'));
$GLOBALS['omise']['calls'] = array();
$t('Test connection (Live) uses the live keys', array($g->test_connection(Settings::load('omise'), 'live')->ok, efb_omise_calls()[0]['key']), array(true, $K['skey_live']));
$GLOBALS['omise']['calls'] = array();
efb_omise_keys_store('test', true, array('secret_key__test' => $K['skey_live']));
$r = $g->test_connection(Settings::load('omise'), 'test');
$t('a Live key in the Test field: refused with a clear message, before any request', array($r->ok, $r->error, strpos($r->message, 'Live key') !== false, count(efb_omise_calls())), array(false, 'wrong_mode', true, 0));
efb_omise_keys_store('test', true, array('secret_key__test' => 'skey_' . 'test_' . 'efbunknownkey000'));
$r = $g->test_connection(Settings::load('omise'), 'test');
$t('a key Omise does not know: 401 → "Omise refused the key"', array($r->ok, $r->error, strpos($r->message, 'refused') !== false), array(false, 'authentication_failure', true));
efb_omise_keys_store('test');
$GLOBALS['omise']['capability']['payment_methods'] = array(array('object' => 'payment_method', 'name' => 'card', 'currencies' => array('THB')));
$r = $g->test_connection(Settings::load('omise'), 'test');
$t('PromptPay not enabled for the account (GET /capability) → says to ask Omise', array($r->ok, $r->error, strpos($r->message, 'enabled by Omise') !== false), array(false, 'promptpay_disabled', true));
efb_omise_fake_reset();
$s = Settings::load('omise'); $s->apply($g->settings_schema(), array('secret_key__test' => '__clear'));
$r = $g->test_connection(Settings::load('omise'), 'test');
$t('no key: "No secret key for this mode yet", not configured', array($r->error, $g->is_configured(Settings::load('omise'), 'test')), array('missing', false));
efb_omise_keys_store('test');
$GLOBALS['omise']['down'] = true;
$r = $g->test_connection(Settings::load('omise'), 'test');
$t('Omise unreachable: the message says so with the code', array($r->ok, strpos($r->message, 'could not be reached') !== false), array(false, true));
$GLOBALS['omise']['down'] = false;

/* ---- B: create, fixed amount ---- */
Store::$now = strtotime('2026-11-12 12:00:00 UTC');
$GLOBALS['omise']['calls'] = array();
$res = efb_omise_create($db);
$p = Store::find_by_uid($res['pay']['uid']);
$post = efb_omise_calls('POST /charges');
parse_str($post[0]['body'], $body);
$t('create succeeds with a QR next step', array($res['success'], $res['pay']['next']['type']), array(true, 'qr'));
$t('the amount is the server quote: 4,000 + 20 fixed = 4,020.00 THB = 402000 satang (browser said 0.01)', array($p['amount_minor'], $p['currency'], $body['amount'], $body['currency']), array(402000, 'THB', '402000', 'THB'));
$t('one POST /charges with source[type]=promptpay, Basic auth with the test key, form encoded', array(count($post), $body['source']['type'], $post[0]['key'], $post[0]['headers']['Content-Type']), array(1, 'promptpay', $K['skey_test'], 'application/x-www-form-urlencoded'));
$t('ref_id = the payment idem key (64 hex, once per account at Omise)', array($body['ref_id'], strlen($body['ref_id'])), array($p['idem_key'], 64));
$t('expires_at = now + 15 minutes (ISO 8601 UTC)', $body['expires_at'], '2026-11-12T12:15:00Z');
$t('metadata: efb_uid, form_id, track', array($body['metadata']['efb_uid'], $body['metadata']['form_id'], $body['metadata']['track']), array($p['uid'], (string) $p['form_id'], $p['track']));
$t('no webhook_endpoints unless the setting asks for it', isset($body['webhook_endpoints']), false);
$t('payment pending, reference chrg_test_…, method promptpay, expiry stored', array($p['status'], $p['gateway_ref'], $p['method'], $p['expires_at']), array('pending', 'chrg_test_efbfake000001', 'promptpay', '2026-11-12 12:15:00'));
$qr = efb_omise_calls('GET /charges/chrg_test_efbfake000001/documents');
$t('the QR is fetched from the charge\'s download_uri on api.omise.co with the same key', array(count($qr), $qr[0]['key'], parse_url($qr[0]['url'], PHP_URL_HOST)), array(1, $K['skey_test'], 'api.omise.co'));
$cl = $res['pay']['next']['client'];
$svg = base64_decode(substr($cl['image'], strlen('data:image/svg+xml;base64,')));
$t('the browser gets the QR as a data: URI of the cleaned SVG, its expiry and the amount text', array(strpos($cl['image'], 'data:image/svg+xml;base64,') === 0, strpos($svg, '<svg') === 0, strpos($svg, '<rect') !== false, $cl['expires_at'], $res['pay']['next']['expires_at']), array(true, true, true, '2026-11-12T12:15:00Z', '2026-11-12T12:15:00Z'));
$t('the amount text is formatted for Thai (฿4,020.00) and the texts come with it', array(strpos($cl['amount_text'], '4,020.00') !== false, isset($cl['text']['scan'], $cl['text']['newQr'], $cl['text']['expires'], $cl['text']['alt'])), array(true, true));
$t('the answer has no key, no download address and no webhook secret', preg_match($ALL_KEYS, json_encode($res)) === 0 && strpos(json_encode($res), 'download') === false && strpos(json_encode($res), 'omise.co') === false, true);
$t('pending_ttl = expires_at + 5 minutes (20 minutes after creation)', $g->pending_ttl(new \Emsfb\Payments\Payment($p)), 1200);
$t('dashboard link to the test charge', $g->dashboard_url(new \Emsfb\Payments\Payment($p)), 'https://dashboard.omise.co/test/charges/chrg_test_efbfake000001');

$n = count(efb_omise_calls('POST /charges'));
$fid = $p['form_id'];
$again = Service::create(array('form_id' => $fid, 'form' => efb_omise_form(), 'rows' => array(array('id_' => 'rad', 'id_ob' => 'ro1')), 'field_id' => 'pf', 'attempt_key' => $p['attempt_key'], 'store_entry' => function () { return null; }));
$t('the same attempt again (double click): the same QR, no second charge at Omise', array($again['pay']['uid'], !empty($again['replay']), $again['pay']['next']['type'], count(efb_omise_calls('POST /charges'))), array($p['uid'], true, 'qr', $n));

/* QR minutes and per-charge webhook address */
Settings::load('omise')->apply($g->settings_schema(), array('qr_ttl_minutes' => '30', 'webhook_per_charge' => '1'));
$res2 = efb_omise_create($db, null, array(array('id_' => 'rad', 'id_ob' => 'ro2')));
$b2 = array(); parse_str(efb_omise_calls('POST /charges')[1]['body'], $b2);
$t('Student 1,500.50 + 20 = 152050 satang; QR 30 minutes', array($b2['amount'], $b2['expires_at']), array('152050', '2026-11-12T12:30:00Z'));
$t('with "send the webhook address with each charge": webhook_endpoints[] = the REST address', $b2['webhook_endpoints'], array('https://site.example/wp-json/Emsfb/v1/payments/webhook/omise'));
Settings::load('omise')->apply($g->settings_schema(), array('qr_ttl_minutes' => '', 'webhook_per_charge' => ''));

/* ---- computed amount (B7) ---- */
$vf = null;
foreach (array(getenv('EFB_LOGIC_VALIDATOR') ?: '', __DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) {
	if ($f !== '' && is_readable($f)) { $vf = $f; break; }
}
if (!$vf) {
	echo "[SKIP] Conditional Logic validator not found: computed amount skipped\n";
} else {
	require_once $vf;
	$cf = array(
		array('type' => 'payment', 'getway' => 'omise', 'currency' => 'thb', 'logic_rules' => array(array('id' => 'calc', 'enabled' => true, 'priority' => 1,
			'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'is_not_empty', 'value' => ''))),
			'actions' => array(array('type' => 'calculate', 'target' => 'total', 'value' => '{guests} * 350', 'decimals' => 2))))),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests', 'step' => 1, 'amount' => 2),
		array('id_' => 'total', 'type' => 'prcfld', 'name' => 'Total', 'step' => 1, 'amount' => 3),
		array('id_' => 'pf', 'type' => 'omise', 'step' => 1, 'amount' => 4),
	);
	$rc = efb_omise_create($db, $cf, array(array('id_' => 'guests', 'id_ob' => 'guests_', 'type' => 'number', 'value' => '3'), array('id_' => 'total', 'type' => 'prcfld', 'value' => '1', 'price' => '1')));
	$bc = array(); $pc = efb_omise_calls('POST /charges'); parse_str(end($pc)['body'], $bc);
	$t('computed: 3 guests × 350 = 1,050.00 THB → 105000 satang although the browser row says 1', array($rc['success'], $bc['amount'], Store::find_by_uid($rc['pay']['uid'])['amount_minor']), array(true, '105000', 105000));
}

/* ---- donation ---- */
$df = efb_omise_form(array('pf_fixed' => '', 'pf_amount' => 'donation', 'pf_presets' => '100,500,1000', 'pf_other' => '1', 'pf_min' => '20', 'pf_max' => '150000'));
unset($df[3], $df[4], $df[5]);
$df = array_values($df);
$rd = efb_omise_create($db, $df, array(array('id_' => 'pf', 'type' => 'omise', 'value' => '250')));
$bd = array(); $pd = efb_omise_calls('POST /charges'); parse_str(end($pd)['body'], $bd);
$t('donation: another amount of 250 baht → 25000 satang', array($rd['success'], $bd['amount']), array(true, '25000'));
$n = count(efb_omise_calls('POST /charges'));
$low = efb_omise_create($db, efb_omise_form(array('pf_fixed' => '', 'pf_amount' => 'donation', 'pf_presets' => '10', 'pf_other' => '1', 'pf_min' => '1')), array(array('id_' => 'pf', 'type' => 'omise', 'value' => '10')));
$t('10 baht is below PromptPay\'s 20 baht: refused (below_min) before Omise is called', array($low['success'], $low['code'], count(efb_omise_calls('POST /charges'))), array(false, 'below_min', $n));
$high = efb_omise_create($db, efb_omise_form(array('pf_fixed' => '', 'pf_amount' => 'donation', 'pf_other' => '1', 'pf_max' => '999999')), array(array('id_' => 'pf', 'type' => 'omise', 'value' => '150000.01')));
$t('150,000.01 baht is above PromptPay\'s maximum: refused (above_max) before Omise is called', array($high['success'], $high['code'], count(efb_omise_calls('POST /charges'))), array(false, 'above_max', $n));

/* ---- currencies: zero-decimal and three-decimal ---- */
foreach (array('jpy' => 'JPY (zero-decimal)', 'kwd' => 'KWD (three-decimal)', 'usd' => 'USD') as $cur => $name) {
	$f = efb_omise_form(); $f[0]['currency'] = $cur;
	$rx = efb_omise_create($db, $f);
	$t("a {$name} form: currency_unsupported (PromptPay is THB only), nothing sent to Omise", array($rx['success'], $rx['code'], count(efb_omise_calls('POST /charges'))), array(false, 'currency_unsupported', $n));
}
$t('format_amount: Omise takes the smallest unit as an integer (JPY 500 → 500, THB 4020.00 → 402000, KWD 12.500 → 12500)', array($g->format_amount(500, 'JPY'), $g->format_amount(402000, 'THB'), $g->format_amount(12500, 'KWD')), array(500, 402000, 12500));
$pp = new \Emsfb\Payments\Payment(array('currency' => 'JPY', 'amount_minor' => 5000, 'mode' => 'test', 'idem_key' => str_repeat('a', 64)));
$t('create() itself refuses a non-THB payment (defence in depth)', $g->create($pp, Settings::load('omise'), new \Emsfb\Payments\Checkout_Context())->error, 'currency_unsupported');

/* ---- Test and Live keys ---- */
efb_omise_keys_store('live');
$GLOBALS['omise']['calls'] = array();
$rl = efb_omise_create($db);
$pl = Store::find_by_uid($rl['pay']['uid']);
$t('Live mode: the payment is live, charged with the live key, a live charge', array($pl['mode'], efb_omise_calls('POST /charges')[0]['key'], strpos($pl['gateway_ref'], 'chrg_test_'), $rl['pay']['test_mode']), array('live', $K['skey_live'], false, false));
efb_omise_keys_store('test');
$GLOBALS['omise']['calls'] = array();
$st = $g->fetch(new \Emsfb\Payments\Payment($pl), Settings::load('omise'));
$t('back in Test mode, the live payment is still read with the live key', array($st->ok, efb_omise_calls()[0]['key']), array(true, $K['skey_live']));
$st = $g->fetch(new \Emsfb\Payments\Payment(array_merge($pl, array('mode' => 'test'))), Settings::load('omise'));
$t('a live charge read for a test payment is refused as a mismatch (core logs it, answers 200, never applies it)', array($st->ok, $st->error, $st->mismatch), array(false, 'mode_mismatch', true));
$st = $g->fetch(new \Emsfb\Payments\Payment(array_merge($pl, array('uid' => 'AnotherUid000000000000'))), Settings::load('omise'));
$t('a charge whose metadata.efb_uid is another payment\'s is refused as a mismatch (ref_mismatch)', array($st->ok, $st->error, $st->mismatch), array(false, 'ref_mismatch', true));
$info = \Emsfb\Payments\Registry::public_info('omise', array('currency' => 'THB'));
$t('field_defaults(): the button says "Show PromptPay QR (%amount%)" with the QR icon (core uses them unless the field has its own)', array($info['defaults']['texts']['btn'], $info['defaults']['pf']['pf_btn_icon']), array('Show PromptPay QR (%amount%)', 'bi-qr-code'));
$nt = $g->settings_notices(Settings::load('omise'));
$t('settings card: the refund notice points to "Mark as refunded outside EFB"', strpos(json_encode($nt), 'Mark as refunded outside EFB') !== false, true);
$t('the Payments screen gets the add-on\'s own words for a refund it cannot make', $g->refund(new \Emsfb\Payments\Payment($pl), 100, '', Settings::load('omise'))->unsupported, true);

/* ---- what Omise answers must match ---- */
$GLOBALS['omise']['create_override'] = function ($body) {
	$c = efb_omise_fixture('charge-promptpay-pending.json')['response']['body'];
	$c['id'] = 'chrg_test_efbwrongamount1'; $c['amount'] = 100;
	return efb_omise_answer(200, $c);
};
$rw = efb_omise_create($db);
$pw = $db->get_row('SELECT * FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1', ARRAY_A);
$t('Omise created another amount than asked: create fails, payment failed, the visitor sees "could not be started"', array($rw['success'], $rw['code'], $pw['status']), array(false, 'gateway_error', 'failed'));
$t('...the event log says amount_mismatch', strpos(json_encode(Store::events($pw['id'])), 'amount_mismatch') !== false, true);
$GLOBALS['omise']['create_override'] = function ($body) {
	$c = efb_omise_fixture('charge-promptpay-pending.json')['response']['body'];
	parse_str($body, $q);
	$c['id'] = 'chrg_test_efbevilqrhost01'; $c['amount'] = (int) $q['amount'];
	$c['source']['scannable_code']['image']['download_uri'] = 'https://evil.example/qr.svg';
	return efb_omise_answer(200, $c);
};
$GLOBALS['omise']['calls'] = array();
$GLOBALS['efb_http_log'] = array();
$re = efb_omise_create($db);
$t('a download_uri on another host: no request to it (SSRF), create fails', array($re['success'], count(array_filter($GLOBALS['efb_http_log'], function ($c) { return strpos($c['url'], 'evil.example') !== false; }))), array(false, 0));
$GLOBALS['omise']['create_override'] = function ($body) {
	return efb_omise_answer(400, array('object' => 'error', 'location' => 'https://www.omise.co/api-errors#invalid-charge', 'code' => 'invalid_charge', 'message' => 'currency is currently not supported'));
};
$ri = efb_omise_create($db);
$pi = $db->get_row('SELECT * FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1', ARRAY_A);
$t('Omise answers an error (invalid_charge): payment failed, its code logged, the visitor may retry', array($ri['code'], $pi['status'], strpos(json_encode(Store::events($pi['id'])), 'invalid_charge') !== false), array('gateway_error', 'failed', true));
$GLOBALS['omise']['create_override'] = null;

/* QR redirect rules */
$GLOBALS['omise']['qr_status'] = 302;
$GLOBALS['omise']['qr_headers'] = array('Location' => 'https://cdn-files.omise.co/qr/47B72F81.svg');
$GLOBALS['omise']['calls'] = array();
$rr = efb_omise_create($db);
$cdn = array_values(array_filter($GLOBALS['omise']['calls'], function ($c) { return strpos($c['url'], 'cdn-files.omise.co') !== false; }));
$t('a QR download that redirects to another *.omise.co host: followed once, without the key', array($rr['success'], count($cdn), isset($cdn[0]['headers']['Authorization'])), array(true, 1, false));
$GLOBALS['omise']['qr_headers'] = array('Location' => 'https://evil.example/qr.svg');
$GLOBALS['efb_http_log'] = array();
$rr2 = efb_omise_create($db);
$t('...a redirect to any other host is not followed: create fails', array($rr2['success'], count(array_filter($GLOBALS['efb_http_log'], function ($c) { return strpos($c['url'], 'evil.example') !== false; }))), array(false, 0));
$GLOBALS['omise']['qr_status'] = 200;
$GLOBALS['omise']['qr_headers'] = array();

/* ---- the SVG cleaner ---- */
$bad = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)" viewBox="0 0 10 10">'
	. '<script>alert(2)</script><style>rect{fill:url(https://evil.example/x)}</style><foreignObject><div>x</div></foreignObject>'
	. '<a xlink:href="javascript:alert(3)"><rect width="1" height="1"/></a><image href="https://evil.example/t.png"/>'
	. '<rect width="10" height="10" fill="#fff" style="fill:url(#x)" onclick="alert(4)"/><path d="M1 1h2v2H1z" fill="url(https://evil.example/p)"/><g transform="translate(1 1)"><rect x="2" y="2" width="1" height="1"/></g></svg>';
$clean = Omise_Api::clean_svg($bad);
$t('hostile SVG: script, style, foreignObject, links, image, event handlers, style= and url() all removed', preg_match('/script|style|foreignObject|<a |image|onload|onclick|url\(|javascript|evil|passwd|ENTITY/i', $clean), 0);
$t('...the shapes stay (rect, path, g)', substr_count($clean, '<rect') === 2 && strpos($clean, '<path d="M1 1h2v2H1z"') !== false && strpos($clean, '<g transform="translate(1 1)"') !== false, true);
$t('not an SVG (an HTML page) → empty', Omise_Api::clean_svg('<html><body>Not found</body></html>'), '');
$t('an SVG without any shape → empty', Omise_Api::clean_svg('<svg xmlns="http://www.w3.org/2000/svg"><text>hi</text></svg>'), '');
$GLOBALS['omise']['svg'] = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
$rq = efb_omise_create($db);
$t('Omise returns a QR without any shape: create fails rather than showing an empty code', $rq['success'], false);
efb_omise_fake_reset();

/* ---- no secret anywhere public ---- */
$info = Registry::public_info('omise');
$t('public info (builder / renderer): configured both modes, no key', array($info['configured'], preg_match($ALL_KEYS, json_encode($info))), array(array('test' => true, 'live' => true), 0));
$cap = \Emsfb\Builder_Api::capabilities(array('package' => 3, 'settings' => (object) array()));
$t('capabilities: field:omise (Free Plus), payment_gateways.omise without any key or secret', array($cap['features']['field:omise']['package'], $cap['payment_gateways']['omise']['addon'], $cap['payment_keys']['omise'], preg_match($ALL_KEYS, json_encode($cap))), array('free_plus', 'AdnOMS', true, 0));
$form_json = json_encode(efb_omise_form());
$t('the form JSON a visitor gets holds no key (keys live only in emsfb_pgw_omise)', preg_match($ALL_KEYS, $form_json), 0);
$sa = Service::status_answer(Store::find_by_uid($res['pay']['uid']), 'status');
$t('the status answer (poll) holds no key', preg_match($ALL_KEYS, json_encode($sa)), 0);
$ev = json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_evt_', ARRAY_A));
$t('the event log holds no key', preg_match('/skey_|pkey_|' . preg_quote($K['whsec_test'], '/') . '/', $ev), 0);

Store::$now = null;
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
