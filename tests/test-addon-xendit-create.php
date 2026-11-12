<?php
/**
 * PAY07 (Xendit) sub-tasks A and B: registration, settings, test connection and Payment Session
 * creation of the Xendit add-on (vendor/xendit), against a fake api.xendit.co built from the
 * official examples (tests/fixtures/payments/xendit; no network, no real key).
 *  - registered through efb_register_payment_gateways as "xendit" (AdnXND), supports(): redirect,
 *    refunds (+ partial), no recurring, IDR PHP THB VND MYR SGD USD with per-currency min/max from
 *    the channel table, token webhooks; api_hosts api.xendit.co only; local logos;
 *  - settings: secret key and webhook token per mode (encrypted, pattern), country, channels, TTL;
 *    Test connection: GET /sessions/ps-000… (404 = key works), Test key on Test, Live key on Live,
 *    a key of the other mode, a refused key (401), a key without permission (403), unreachable;
 *  - create: POST /sessions with session_type PAY, mode PAYMENT_LINK, reference_id = uid, the
 *    server amount (fixed, computed, donation, variable) — IDR and VND whole numbers, PHP two
 *    decimals, IDR with sen refused before Xendit; channels from the visitor, the field and the
 *    owner; expires_at ≥ 10 minutes; locale; metadata without personal data; customer reference
 *    without the e-mail in it; success / cancel return URLs; live mode uses the live key;
 *    a Xendit answer with another amount, reference or a non-Xendit page is refused;
 *  - secrets never in the form JSON, the browser answer, public_info or capabilities.
 *
 * Run: php tests/test-addon-xendit-create.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/addon-xendit-lib.php';
$db = xnd_env();
use Emsfb\Payments\Registry;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Store;
use Emsfb\Payments\Service;
use Emsfb\Payments\Payment;
use Emsfb\Payments\Checkout_Context;
use Emsfb\Payments\Xendit\Xendit_Gateway;
use Emsfb\Payments\Xendit\Xendit_Api;
$t = 'efb_pay_t';
$F = 'Efb_Test_Xendit_Fake';

$g = Registry::get('xendit');
$t('registered as xendit through efb_register_payment_gateways', $g instanceof Xendit_Gateway, true);
$t('add-on key AdnXND, label Xendit', array($g->addon_key(), $g->label()), array('AdnXND', 'Xendit'));
$t('no registration error', Registry::errors(), array());
$sup = $g->supports();
$t('supports: redirect only, refund + partial, no recurring, token webhooks', array($sup['flows'], $sup['refund'], $sup['partial_refund'], $sup['recurring'], $sup['webhook_auth']), array(array('redirect'), true, true, false, 'token'));
$t('supports: IDR first, then PHP THB VND MYR SGD USD; countries ID PH TH VN MY SG', array($sup['currencies'], $sup['countries']), array(array('IDR', 'PHP', 'THB', 'VND', 'MYR', 'SGD', 'USD'), array('ID', 'PH', 'TH', 'VN', 'MY', 'SG')));
$t('min/max in EFB minor units: IDR 1 … 50,000,000,000 rupiah (×100), PHP 1.00 … 700,000.00, VND 1 … 50,000,000 (no decimals), MYR max 20,000.00', array($sup['min_minor']['IDR'], $sup['max_minor']['IDR'], $sup['min_minor']['PHP'], $sup['max_minor']['PHP'], $sup['min_minor']['VND'], $sup['max_minor']['VND'], $sup['max_minor']['MYR']),
	array(100, 5000000000000, 100, 70000000, 1, 50000000, 2000000));
$t('SGD / USD (cards, limits not published): a minimum of 1.00 and no maximum', array($sup['min_minor']['SGD'], isset($sup['max_minor']['SGD']), $sup['min_minor']['USD']), array(100, false, 100));
$t('api_hosts is api.xendit.co only', $g->api_hosts(), array('api.xendit.co'));
$t('the settings schema: key and token per mode (secret, key required), country (select), channels, TTL', array_map(function ($f) { return $f['key'] . ':' . $f['type'] . (!empty($f['per_mode']) ? ':per_mode' : '') . (!empty($f['required']) ? ':required' : ''); }, $g->settings_schema()),
	array('secret_key:secret:per_mode:required', 'callback_token:secret:per_mode', 'country:select', 'channels:text', 'session_ttl_minutes:text'));
$help = $g->settings_schema()[1]['help'];
$t('the token\'s help shows the webhook address to add in the Xendit Dashboard', strpos($help, 'https://site.example/wp-json/Emsfb/v1/payments/webhook/xendit') !== false && strpos($help, 'Webhooks') !== false, true);
$logos = $g->logos();
$t('logos: six local files of the add-on', array(count($logos), count(array_filter($logos, function ($l) { return strpos($l[0], 'vendor/xendit/assets/img/') !== false && substr($l[0], -4) === '.svg'; }))), array(6, 6));
$missing = array();
foreach (array_keys(Xendit_Api::CHANNELS) as $id) { $f = xnd_addon_dir() . '/assets/img/' . (strpos($id, 'va_') === 0 ? 'va' : $id) . '.svg'; if (!is_readable($f)) $missing[] = $id; }
$t('every channel has its badge in assets/img', $missing, array());
$m = $g->methods(Settings::load('xendit'));
$t('methods(): no channel list → "any" + the 21 channels, QRIS named for every app', array(count($m), $m[0][0], array_slice($m[1], 0, 2)), array(22, 'any', array('qris', 'QRIS (all banking and e-wallet apps)')));
$t('method extras: QRIS only IDR, 1 … 20,000,000 rupiah (×100); cards without SGD / USD limits', array($m[1][2]['currencies'], $m[1][2]['min_minor']['IDR'], $m[1][2]['max_minor']['IDR'], isset($m[21][2]['min_minor']['USD'])), array(array('IDR'), 100, 2000000000, false));
$t('every method id fits core\'s pf_methods sanitizer', \Emsfb\Payments\Field::sanitize_key('pf_methods', implode(',', array_map(function ($x) { return $x[0]; }, $m))) === implode(',', array_slice(array_map(function ($x) { return $x[0]; }, $m), 0, 20)), true);

// Settings and secrets.
$s = Settings::load('xendit');
$raw = json_encode($GLOBALS['opts']['emsfb_pgw_xendit']);
$t('keys and tokens are stored encrypted (nothing in plain text)', strpos($raw, xnd_key('test')) === false && strpos($raw, xnd_key('live')) === false && strpos($raw, xnd_token('test')) === false && strpos($raw, 'enc:v1:') !== false, true);
$t('the secrets of each mode decrypt', array($s->secret('secret_key', 'test'), $s->secret('secret_key', 'live'), $s->secret('callback_token', 'live')), array(xnd_key('test'), xnd_key('live'), xnd_token('live')));
$refused = $s->apply($g->settings_schema(), array('secret_key__test' => 'xnd_public_development_abc', 'channels' => 'qris,paypal', 'country' => 'US', 'session_ttl_minutes' => 'soon', 'callback_token__test' => 'short'));
$t('a public key, an unknown channel, a bad TTL and a short token are refused; an unknown country is not stored', array($refused, Settings::load('xendit')->get('country')), array(array('secret_key__test', 'callback_token__test', 'channels', 'session_ttl_minutes'), ''));
$t('...and the stored secrets are unchanged', array(Settings::load('xendit')->secret('secret_key', 'test'), Settings::load('xendit')->secret('callback_token', 'test')), array(xnd_key('test'), xnd_token('test')));
$t('configured in both modes', array($g->is_configured(Settings::load('xendit'), 'test'), $g->is_configured(Settings::load('xendit'), 'live')), array(true, true));

// Test connection.
$r = $g->test_connection(Settings::load('xendit'), 'test');
$req = $F::last('GET', '/sessions/');
$t('Test connection (Test): Connected after GET /sessions/ps-000… (404) with the Test key; nothing created', array($r->ok, $r->message, $req['path'], $req['key_mode'], count($F::state()['sessions'])), array(true, 'Connected to Xendit.', '/sessions/ps-000000000000000000000000', 'test', 0));
$r = $g->test_connection(Settings::load('xendit'), 'live');
$t('Test connection (Live): with the Live key', array($r->ok, $F::last('GET', '/sessions/')['key_mode']), array(true, 'live'));
$s2 = Settings::load('xendit');
$s2->apply($g->settings_schema(), array('secret_key__live' => xnd_key('test')));
$n = count($F::state()['log']);
$r = $g->test_connection(Settings::load('xendit'), 'live');
$t('a Test key in the Live field: wrong_mode, no request, not configured for Live', array($r->ok, $r->error, strpos($r->message, 'xnd_production_') !== false, count($F::state()['log']) - $n, $g->is_configured(Settings::load('xendit'), 'live')), array(false, 'wrong_mode', true, 0, false));
$s2->apply($g->settings_schema(), array('secret_key__live' => 'xnd' . '_production_' . 'RevokedFakeKeyForEfbTests'));
$r = $g->test_connection(Settings::load('xendit'), 'live');
$t('a key Xendit refuses (401 INVALID_API_KEY): a clear message', array($r->ok, $r->error, $r->message), array(false, 'http_401', 'Xendit refused the key. Check that you copied the whole secret key.'));
$s2->apply($g->settings_schema(), array('secret_key__live' => xnd_key('live'), 'callback_token__live' => '__clear'));
$r = $g->test_connection(Settings::load('xendit'), 'live');
$t('no Live webhook token: Connected, with the advice to add the token', array($r->ok, strpos($r->message, 'webhook verification token') !== false), array(true, true));
$s2->apply($g->settings_schema(), array('callback_token__live' => xnd_token('live')));
add_filter('pre_http_request', function ($pre, $args, $url) { return strpos($url, '/sessions/ps-000') !== false && !empty($GLOBALS['xnd_403']) ? array('headers' => array(), 'body' => '{"error_code":"REQUEST_FORBIDDEN_ERROR","message":"The API key is forbidden to perform this request"}', 'response' => array('code' => 403, 'message' => ''), 'cookies' => array(), 'filename' => null) : $pre; }, 5, 3);
$GLOBALS['efb_filters']['pre_http_request'] = array_reverse($GLOBALS['efb_filters']['pre_http_request']);
$GLOBALS['xnd_403'] = true;
$r = $g->test_connection(Settings::load('xendit'), 'test');
$t('a key without Money-in permission (403): says what to change', array($r->ok, $r->error, strpos($r->message, 'Money-in') !== false), array(false, 'http_403', true));
$GLOBALS['xnd_403'] = false;
$F::set_opt('down', true);
$t('Xendit unreachable: a network error code', strpos($g->test_connection(Settings::load('xendit'), 'test')->error, 'network:') === 0, true);
$F::set_opt('down', false);

// Create: fixed amount (radio 150000 + fee 2500) in IDR, QRIS.
$res = xnd_create($db);
$t('create: success, redirect to Xendit\'s hosted page (dev.xen.to in Test)', array($res['success'], $res['pay']['next']['type'], strpos($res['pay']['next']['url'], 'https://dev.xen.to/') === 0), array(true, 'redirect', true));
$p = xnd_row($res);
list($b, $raw, $req) = xnd_last_session_body();
$t('the quote: 150000 + 2500 rupiah = 15,250,000 in EFB minor units (the browser price 1 is ignored)', array($p['amount_minor'], $p['currency']), array(15250000, 'IDR'));
$t('IDR is sent as a whole number: "amount":152500', strpos($raw, '"amount":152500,') !== false, true);
$t('session_type PAY, mode PAYMENT_LINK, currency IDR, country ID, locale id', array($b['session_type'], $b['mode'], $b['currency'], $b['country'], $b['locale']), array('PAY', 'PAYMENT_LINK', 'IDR', 'ID', 'id'));
$t('reference_id = the payment uid; metadata efb_uid / form_id / track only (strings)', array($b['reference_id'], $b['metadata']), array($p['uid'], array('efb_uid' => $p['uid'], 'form_id' => (string) $p['form_id'], 'track' => $p['track'])));
$t('allowed_payment_channels: the visitor\'s QRIS', $b['allowed_payment_channels'], array('QRIS'));
$t('expires_at = now + 60 minutes (default), at least 10 minutes ahead', array(strtotime($b['expires_at']) - time() >= 3590 && strtotime($b['expires_at']) - time() <= 3610), array(true));
$t('success / cancel return URLs: core\'s signed return URL (cancel with c=1)', array($b['success_return_url'], $b['cancel_return_url']),
	array('https://site.example/wp-json/Emsfb/v1/payments/return/xendit?p=' . $p['uid'] . '&s=' . Service::sign($p['uid'], 'xendit'), 'https://site.example/wp-json/Emsfb/v1/payments/return/xendit?p=' . $p['uid'] . '&s=' . Service::sign($p['uid'], 'xendit') . '&c=1'));
$t('description: form name + confirmation code', $b['description'], 'Workshop ' . $p['track']);
$t('customer: reference efb-sha1(e-mail) (the e-mail is not in it), given_names / surname from the name field, e-mail', array($b['customer']['reference_id'], $b['customer']['type'], $b['customer']['individual_detail'], $b['customer']['email']),
	array('efb-' . sha1('payer@example.test'), 'INDIVIDUAL', array('given_names' => 'Siti', 'surname' => 'Nurhaliza Putri'), 'payer@example.test'));
$t('HTTP Basic with the Test key and an empty password, JSON body', array($req['key_mode'], $req['headers']['content-type']), array('test', 'application/json'));
$t('payment pending with the ps- reference, method qris, expiry stored', array($p['status'], Xendit_Api::is_session_id($p['gateway_ref']), $p['method'], $p['expires_at'] !== null), array('pending', true, 'qris', true));
$t('the browser answer has no key, no token, test_mode true', array(strpos(json_encode($res), 'xnd_') === false && strpos(json_encode($res), 'efbFakeCallback') === false, $res['pay']['test_mode'], $res['pay']['total_minor']), array(true, true, 15250000));

// IDR with sen: refused before Xendit.
$before = count($F::state()['sessions']);
$r = xnd_create($db, array('form' => xnd_form('idr', array(), array('150000.50', '1'))));
$last = Store::get((int) $db->get_var('SELECT id FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1'));
$t('IDR 152,500.50 (with sen): refused before Xendit, logged amount_not_whole, payment failed', array($r['success'], count($F::state()['sessions']) - $before, $last['status'], strpos(json_encode(Store::events($last['id'])), 'amount_not_whole') !== false), array(false, 0, 'failed', true));

// Zero-decimal VND and two-decimal PHP.
$r = xnd_create($db, array('form' => xnd_form('vnd', array(), array('250000', '1'), '5000'), 'method' => 'momo'));
list($b, $raw) = xnd_last_session_body();
$t('VND (0 decimals in EFB and Xendit): 255000 → "amount":255000, country VN, MOMO', array($r['success'], xnd_row($r)['amount_minor'], strpos($raw, '"amount":255000,') !== false, $b['country'], $b['allowed_payment_channels']), array(true, 255000, true, 'VN', array('MOMO')));
$r = xnd_create($db, array('form' => xnd_form('php', array(), array('1499.75', '1'), '0.25'), 'method' => 'gcash'));
list($b, $raw) = xnd_last_session_body();
$t('PHP two decimals: 1499.75 + 0.25 → "amount":1500.00, country PH, GCASH', array($r['success'], xnd_row($r)['amount_minor'], strpos($raw, '"amount":1500.00,') !== false, $b['country'], $b['allowed_payment_channels']), array(true, 150000, true, 'PH', array('GCASH')));
$r = xnd_create($db, array('form' => xnd_form('php', array(), array('12.34', '1'), ''), 'method' => 'cards'));
list($b, $raw) = xnd_last_session_body();
$limits = method_exists('\\Emsfb\\Payments\\Gateway', 'methods_for'); // cores that check a method's own limits before create()
if ($limits) $t('PHP 12.34 by card (minimum 20.00): core refuses with the method\'s own minimum (method_below_min)', array($r['success'], $r['code'], strpos($r['m'], '20.00') !== false || strpos($r['m'], '20') !== false), array(false, 'method_below_min', true));
else $t('PHP 12.34 by card (minimum 20.00): the visitor\'s card is not sent, no list → Xendit shows every activated channel (no allowed_payment_channels)', array($r['success'], isset($b['allowed_payment_channels'])), array(true, false));
$r = xnd_create($db, array('form' => xnd_form('kwd')));
$t('a currency Xendit does not take (KWD, three decimals): refused by the quote, no request', array($r['success'], $r['code']), array(false, 'currency_unsupported'));
$r = xnd_create($db, array('form' => xnd_form('usd', array(), array('10', '1'), ''), 'method' => 'cards'));
list($b) = xnd_last_session_body();
$t('USD by card: country from the setting (default ID)', array($r['success'], $b['country'], $b['allowed_payment_channels']), array(true, 'ID', array('CARDS')));
Settings::load('xendit')->apply($g->settings_schema(), array('country' => 'PH'));
xnd_create($db, array('form' => xnd_form('usd', array(), array('10', '1'), ''), 'method' => 'cards'));
list($b) = xnd_last_session_body();
$t('...business country Philippines → country PH', $b['country'], 'PH');

// Channels: visitor, field, owner.
$r = xnd_create($db, array('method' => 'gcash'));
list($b) = xnd_last_session_body();
$t('a channel that does not take IDR (GCash) is not sent: no list → every activated channel', array($r['success'], isset($b['allowed_payment_channels'])), array(true, false));
$r = xnd_create($db, array('method' => 'any', 'form' => xnd_form('idr', array('pf_methods' => 'qris,ovo,dana,gcash'))));
list($b) = xnd_last_session_body();
$t('"any" with the field\'s pf_methods qris,ovo,dana,gcash → QRIS, OVO, DANA (GCash dropped for IDR)', $b['allowed_payment_channels'], array('QRIS', 'OVO', 'DANA'));
$r = xnd_create($db, array('method' => 'va_bca', 'form' => xnd_form('idr', array('pf_methods' => 'qris,ovo'))));
list($b) = xnd_last_session_body();
$t('a channel outside the field\'s list (BCA VA) → the field\'s channels', $b['allowed_payment_channels'], array('QRIS', 'OVO'));
$r = xnd_create($db, array('method' => 'va_bca', 'form' => xnd_form('idr', array(), array('5000', '1'), '')));
list($b) = xnd_last_session_body();
if ($limits) $t('BCA VA below its 10,000 minimum (5,000): core refuses with the method\'s own minimum', array($r['success'], $r['code']), array(false, 'method_below_min'));
else $t('BCA VA below its 10,000 minimum (5,000) is not sent → no list', isset($b['allowed_payment_channels']), false);
$r = xnd_create($db, array('method' => '', 'form' => xnd_form('idr', array('pf_methods' => 'va_bca,qris'), array('5000', '1'), '')));
list($b) = xnd_last_session_body();
$t('no channel chosen, the field allows BCA VA and QRIS, 5,000 IDR: only QRIS is sent (BCA VA starts at 10,000)', array($r['success'], $b['allowed_payment_channels']), array(true, array('QRIS')));
Settings::load('xendit')->apply($g->settings_schema(), array('channels' => 'qris, dana, va_bni'));
$t('methods(): the owner\'s list in its order', array_map(function ($x) { return $x[0]; }, $g->methods(Settings::load('xendit'))), array('qris', 'dana', 'va_bni'));
$r = xnd_create($db, array('method' => 'ovo'));
list($b) = xnd_last_session_body();
$t('a channel the owner did not turn on (OVO) → the owner\'s channels', $b['allowed_payment_channels'], array('QRIS', 'DANA', 'BNI_VIRTUAL_ACCOUNT'));
$r = xnd_create($db, array('method' => 'dana', 'form' => xnd_form('idr', array('pf_methods' => 'qris,dana,ovo'))));
list($b) = xnd_last_session_body();
$t('owner ∩ field, visitor DANA → DANA', $b['allowed_payment_channels'], array('DANA'));
$before = count($F::state()['sessions']);
$r = xnd_create($db, array('method' => '', 'form' => xnd_form('php', array(), array('100', '1'), '')));
$t('owner\'s channels are IDR-only and the form is PHP: refused before Xendit (no_method)', array($r['success'], count($F::state()['sessions']) - $before), array(false, 0));
Settings::load('xendit')->apply($g->settings_schema(), array('channels' => '', 'session_ttl_minutes' => '5'));
xnd_create($db);
list($b) = xnd_last_session_body();
$t('TTL 5 minutes is raised to 11 (Xendit wants ≥ 10 minutes ahead)', abs(strtotime($b['expires_at']) - time() - 660) <= 5, true);
Settings::load('xendit')->apply($g->settings_schema(), array('session_ttl_minutes' => '2880'));
xnd_create($db);
list($b) = xnd_last_session_body();
$t('TTL 2880 minutes (2 days) kept', abs(strtotime($b['expires_at']) - time() - 172800) <= 5, true);
Settings::load('xendit')->apply($g->settings_schema(), array('session_ttl_minutes' => ''));

// Same attempt twice: one session.
$n = count($F::state()['sessions']);
$form = xnd_form();
$fid = $db->add_form($form, 'payment');
$args = array('form_id' => $fid, 'form' => $form, 'rows' => xnd_rows(), 'field_id' => 'pf', 'attempt_key' => '22222222-3333-4444-8555-666666666666', 'method' => 'qris', 'payer_email' => 'payer@example.test',
	'store_entry' => function ($rows) use ($db, $fid) { $id = $db->add_entry($fid, $rows, gmdate('Y-m-d H:i:s'), array('read_' => 2, 'track' => 'EFB77777')); return array('track' => 'EFB77777', 'msg_id' => $id); });
$a1 = Service::create($args);
$a2 = Service::create($args);
$t('same attempt (double click): one Xendit session, the same answer', array(count($F::state()['sessions']) - $n, $a2['pay']['uid'] === $a1['pay']['uid'], !empty($a2['replay']), $a2['pay']['next']['url'] === $a1['pay']['next']['url']), array(1, true, true, true));

// Donation (visitor's amount, within the field's limits) in IDR.
$don = xnd_form('idr', array('pf_fixed' => '', 'pf_amount' => 'donation', 'pf_presets' => '50000,100000', 'pf_other' => '1', 'pf_min' => '10000', 'pf_max' => '5000000'));
$rows = array(array('id_' => 'nm', 'type' => 'text', 'name' => 'Nama lengkap', 'value' => 'Budi'), array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'value' => 'donor@example.test'), array('id_' => 'pf', 'type' => 'xendit', 'value' => '75000'));
$r = xnd_create($db, array('form' => $don, 'rows' => $rows, 'method' => 'dana', 'payer_email' => 'donor@example.test'));
list($b, $raw) = xnd_last_session_body();
$t('donation 75,000 IDR → "amount":75000, DANA, given name Budi without surname', array($r['success'], strpos($raw, '"amount":75000,') !== false, $b['allowed_payment_channels'], $b['customer']['individual_detail']), array(true, true, array('DANA'), array('given_names' => 'Budi')));
$r = xnd_create($db, array('form' => $don, 'rows' => array_merge(array_slice($rows, 0, 2), array(array('id_' => 'pf', 'type' => 'xendit', 'value' => '500'))), 'method' => 'dana'));
$t('donation below the field minimum: refused, no session', array($r['success'], $r['code']), array(false, 'pwyw_out_of_range'));

// Variable amount (prcfld the visitor types, within Min/Max) in PHP.
$var = xnd_form('php', array('pf_fixed' => ''), array('100', '50'));
array_splice($var, 7, 0, array(array('id_' => 'tip', 'type' => 'prcfld', 'name' => 'Amount', 'milen' => '10', 'mlen' => '1000', 'step' => 1)));
$r = xnd_create($db, array('form' => $var, 'rows' => array_merge(xnd_rows('ro2'), array(array('id_' => 'tip', 'type' => 'prcfld', 'value' => '20.5', 'price' => '1'))), 'method' => 'paymaya'));
list($b, $raw) = xnd_last_session_body();
$t('variable amount (prcfld 20.50 + option 50) in PHP → "amount":70.50, PAYMAYA', array($r['success'], strpos($raw, '"amount":70.50,') !== false, $b['allowed_payment_channels']), array(true, true, array('PAYMAYA')));

// Computed amount (B7) with the real Conditional Logic engine.
$vf = null;
foreach (array(__DIR__ . '/../vendor/logic/logic/class-Emsfb-logic-validator.php', '/home/user/vendores-efb/logic/logic/class-Emsfb-logic-validator.php') as $f) if (is_readable($f)) { $vf = $f; break; }
if (!$vf) {
	echo "[SKIP] Conditional Logic validator not found: computed amount skipped\n";
} else {
	require_once $vf;
	$b7 = array(
		array('type' => 'payment', 'getway' => 'xendit', 'currency' => 'idr', 'email_to' => 'em', 'logic_rules' => array(array('id' => 'calc', 'enabled' => true, 'priority' => 1,
			'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('type' => 'condition', 'source' => 'field', 'field_id' => 'guests', 'compare' => 'is_not_empty', 'value' => ''))),
			'actions' => array(array('type' => 'calculate', 'target' => 'total', 'value' => '{guests} * 125000', 'decimals' => 0))))),
		array('type' => 'step', 'id_' => '1', 'step' => 1),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1),
		array('id_' => 'guests', 'type' => 'number', 'name' => 'Guests', 'step' => 1),
		array('id_' => 'total', 'type' => 'prcfld', 'name' => 'Total', 'step' => 1),
		array('id_' => 'pf', 'type' => 'xendit', 'name' => 'Pay', 'step' => 1),
	);
	$rows = array(array('id_' => 'em', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => 'guests', 'id_ob' => 'guests_', 'type' => 'number', 'value' => '3'), array('id_' => 'total', 'type' => 'prcfld', 'value' => '1', 'price' => '1'));
	$r = xnd_create($db, array('form' => $b7, 'rows' => $rows));
	list($b, $raw) = xnd_last_session_body();
	$t('computed (3 guests × 125,000 IDR) = "amount":375000 although the browser said 1', array($r['success'], strpos($raw, '"amount":375000,') !== false), array(true, true));
	$t('no name field: given_names from the e-mail', $b['customer']['individual_detail'], array('given_names' => 'payer'));
}

// No e-mail: no customer object (optional for PAY).
$noemail = xnd_form();
$noemail[0]['email_to'] = '';
$r = xnd_create($db, array('form' => $noemail, 'rows' => array(array('id_' => 'nm', 'type' => 'text', 'name' => 'Nama lengkap', 'value' => 'X'), array('id_' => 'rad', 'id_ob' => 'ro1', 'type' => 'payRadio')), 'payer_email' => ''));
list($b) = xnd_last_session_body();
$t('a form without an e-mail: the session is made without customer', array($r['success'], isset($b['customer'])), array(true, false));

// What Xendit created must be what EFB asked.
$F::set_opt('create_amount', 1);
$r = xnd_create($db);
$last = Store::get((int) $db->get_var('SELECT id FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1'));
$t('Xendit answers another amount: create refused (amount_mismatch), payment failed, visitor not sent there', array($r['success'], $last['status'], strpos(json_encode(Store::events($last['id'])), 'amount_mismatch') !== false), array(false, 'failed', true));
$F::set_opt('create_amount', null);
$gw = new Xendit_Gateway();
add_filter('pre_http_request', function ($pre, $args, $url) {
	if (empty($GLOBALS['xnd_bad_link']) || strpos($url, '/sessions') === false || ($args['method'] ?? '') !== 'POST') return $pre;
	$b = json_decode($args['body'], true);
	return array('headers' => array(), 'response' => array('code' => 201, 'message' => ''), 'cookies' => array(), 'filename' => null,
		'body' => json_encode(array('payment_session_id' => 'ps-' . str_repeat('a', 24), 'reference_id' => $GLOBALS['xnd_bad_link'] === 'ref' ? 'someoneElse' : $b['reference_id'], 'status' => 'ACTIVE', 'amount' => 152500, 'currency' => 'IDR', 'payment_link_url' => $GLOBALS['xnd_bad_link'] === 'ref' ? 'https://xen.to/x' : 'https://evil.example/pay')));
}, 5, 3);
$GLOBALS['efb_filters']['pre_http_request'] = array_reverse($GLOBALS['efb_filters']['pre_http_request']);
$p0 = new Payment(array('uid' => 'Q3xAbCdEfGhIjKlMnOpQrS', 'mode' => 'test', 'amount_minor' => 15250000, 'currency' => 'IDR', 'form_id' => 1, 'track' => 'EFB1'));
$ctx = new Checkout_Context(array('return_url' => 'https://site.example/r', 'cancel_url' => 'https://site.example/c', 'locale' => 'id_ID'));
$GLOBALS['xnd_bad_link'] = 'host';
$r1 = $gw->create($p0, Settings::load('xendit'), $ctx);
$GLOBALS['xnd_bad_link'] = 'ref';
$r2 = $gw->create($p0, Settings::load('xendit'), $ctx);
$GLOBALS['xnd_bad_link'] = '';
$t('a payment link outside xen.to / xendit.co, or another reference_id: refused', array($r1->ok, $r1->error, $r2->ok, $r2->error), array(false, 'no_checkout_url', false, 'ref_mismatch'));
$t('is_checkout_url: xen.to, dev.xen.to, checkout.xendit.co yes; http, look-alikes no', array(Xendit_Api::is_checkout_url('https://xen.to/a'), Xendit_Api::is_checkout_url('https://dev.xen.to/a'), Xendit_Api::is_checkout_url('https://checkout-staging.xendit.co/web/x'), Xendit_Api::is_checkout_url('http://xen.to/a'), Xendit_Api::is_checkout_url('https://xen.to.evil.example/a'), Xendit_Api::is_checkout_url('https://evilxen.to/a')),
	array(true, true, true, false, false, false));
$F::set_opt('down', true);
$r = xnd_create($db);
$t('Xendit down at create: "could not be started" (gateway_error), payment failed', array($r['success'], $r['code'], Store::get((int) $db->get_var('SELECT id FROM wp_emsfb_pmt_ ORDER BY id DESC LIMIT 1'))['status']), array(false, 'gateway_error', 'failed'));
$F::set_opt('down', false);

// Live mode uses the live key and xen.to.
Settings::load('xendit')->set_mode('live');
$r = xnd_create($db);
list($b, $raw, $req) = xnd_last_session_body();
$p = xnd_row($r);
$t('Live: the Live key, xen.to page, payment mode live', array($req['key_mode'], strpos($r['pay']['next']['url'], 'https://xen.to/') === 0, $p['mode'], $r['pay']['test_mode']), array('live', true, 'live', false));
Settings::load('xendit')->set_mode('test');

// Secrets never leave the server.
$info = Registry::public_info('xendit');
$t('public_info: no key or token, configured in both modes, methods and logos', array(strpos(json_encode($info), 'xnd_') === false && strpos(json_encode($info), 'efbFakeCallback') === false, $info['configured'], count($info['methods']) > 1, count($info['logos'])), array(true, array('test' => true, 'live' => true), true, 6));
if (class_exists('\Emsfb\Builder_Api') && method_exists('\Emsfb\Builder_Api', 'capabilities')) {
	$caps = json_encode(\Emsfb\Builder_Api::capabilities());
	$t('capabilities: no key or token', strpos($caps, 'xnd_') === false && strpos($caps, 'efbFakeCallback') === false, true);
}
$forms = json_encode($db->get_results('SELECT form_structer FROM wp_emsfb_form', ARRAY_A));
$t('no key or token in any stored form JSON', strpos($forms, 'xnd_') === false && strpos($forms, 'efbFakeCallback') === false, true);
$log = json_encode($F::state()['log']);
$t('the request log of the fake has no Authorization header kept and no token', strpos($log, 'efbFakeCallback') === false && strpos($log, 'Basic ') === false, true);
$ev = json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_evt_', ARRAY_A));
$t('no key or token in the payment events', strpos($ev, 'xnd_development') === false && strpos($ev, 'efbFakeCallback') === false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
