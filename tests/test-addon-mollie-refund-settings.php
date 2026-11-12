<?php
/**
 * Mollie add-on (PAY01 §4, §7.3, §11, §13): refunds, settings, keys and the package.
 *  - refunds through core's Service::refund(): partial then the rest, amount as a string,
 *    Idempotency-Key = core's emsfb_pmt_rfd_.idem_key (a retried call is the same refund), Mollie's
 *    pending refund → refund row pending with re_ id; more than what is left → refused before any
 *    call; Mollie's 422 → refund row failed, payment unchanged; JPY refund without decimals;
 *  - Test and Live keys: a key of the other mode is not saved (pattern), is_configured per mode, the
 *    payment's own mode picks the key even after the site switched mode, Test connection
 *    (GET /v2/methods 10.00 EUR) ok / no key / refused key (401) / wrong mode, methods cached per mode;
 *  - secrets: stored encrypted, never in public_info / builder vars / field markup / logs;
 *  - logos served from the add-on folder (no mollie.com URL for visitors);
 *  - i18n: every efb-mollie string of the code is in the phrase class and in languages/efb-mollie.pot;
 *    locales.json lists no locale yet (not translated by the owner's instruction);
 *  - mollie.zip: one top folder mollie/, sorted entries with folders, 0755/0644, fixed date, same
 *    files as the folder.
 *
 * Run: php tests/test-addon-mollie-refund-settings.php
 */
define('ABSPATH', __DIR__ . '/');
if (!defined('EMSFB_PLUGIN_URL')) define('EMSFB_PLUGIN_URL', 'https://site.example/wp-content/plugins/easy-form-builder/');
$passed = 0; $failed = 0;
require __DIR__ . '/mollie-test-lib.php';
$db = efb_mollie_env();
use Emsfb\Payments\Store;
use Emsfb\Payments\Service;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Secrets;
use Emsfb\Payments\Registry;
use Emsfb\Payments\Payment;
$t = 'efb_pay_t';
$gw = Registry::get('mollie');
$paid = function ($form = null) use ($db) {
	$r = efb_mollie_create($db, $form);
	$p = Store::find_by_uid($r['pay']['uid']);
	Efb_Test_Mollie_Fake::update($p['gateway_ref'], array('status' => 'paid'));
	efb_mollie_return($p['uid']);
	return Store::get($p['id']);
};

/* refunds */
$p = $paid();
$t('a paid 25.00 EUR payment to refund', array($p['status'], $p['amount_minor']), array('paid', 2500));
$r = Service::refund($p, 1000, 'customer asked', 1);
$req = efb_mollie_last('POST', '#/refunds$#');
$rf = Store::refunds($p['id']);
$t('partial refund 10.00: POST /v2/payments/{id}/refunds with "10.00" EUR', array($r['ok'], $req['path'], $req['body']['amount']), array(true, '/v2/payments/' . $p['gateway_ref'] . '/refunds', array('currency' => 'EUR', 'value' => '10.00')));
$t('...Idempotency-Key = core\'s refund idem key; description and metadata without personal data', array($req['idem'], $req['body']['description'], $req['body']['metadata']), array($rf[0]['idem_key'], 'EFB refund ' . $p['track'], array('efb_uid' => $p['uid'])));
$t('...Mollie\'s refund is pending: refund row pending with its re_ id; the payment counts it only when Mollie confirms (paid, 0)', array($rf[0]['status'], substr($rf[0]['gateway_ref'], 0, 3), Store::get($p['id'])['status'], (int) Store::get($p['id'])['refunded_minor'], $r['pending']), array('pending', 're_', 'paid', 0, true));
$n = count(Efb_Test_Mollie_Fake::state()['log']);
$r = Service::refund(Store::get($p['id']), 1600, '', 1);
$t('more than what is left (16.00 of 15.00, 10.00 pending) → refused by core, no request', array($r['ok'], $r['error'], count(Efb_Test_Mollie_Fake::state()['log']) - $n), array(false, 'bad_amount', 0));
Store::$now = time() + 120;
efb_mollie_webhook($p['gateway_ref'], $p['uid']);
$t('Mollie\'s next webhook (amountRefunded 10.00) → partially_refunded 1000, the refund row confirmed', array(Store::get($p['id'])['status'], (int) Store::get($p['id'])['refunded_minor'], Store::refunds($p['id'])[0]['status']), array('partially_refunded', 1000, 'refunded'));
$r = Service::refund(Store::get($p['id']), 1500, '', 1);
Store::$now = time() + 240;
efb_mollie_webhook($p['gateway_ref'], $p['uid']);
$t('the rest 15.00 → refunded once Mollie reports it', array($r['ok'], Store::get($p['id'])['status'], efb_mollie_last('POST', '#/refunds$#')['body']['amount']['value'], (int) Store::get($p['id'])['refunded_minor']), array(true, 'refunded', '15.00', 2500));
Store::$now = null;
$entry = $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $p['msg_id']);
$t('the entry\'s payment row says refunded', strpos($entry['content'], 'refunded') !== false, true);

$q = $paid();
$rid = Store::insert_refund(array('payment_id' => $q['id'], 'amount_minor' => 500, 'currency' => 'EUR', 'idem_key' => hash('sha256', 'retry-test'), 'created_by' => 1));
$a = $gw->refund(new Payment($q), 500, '', Settings::load('mollie'));
$b = $gw->refund(new Payment($q), 500, '', Settings::load('mollie'));
$t('a retried refund call sends the same Idempotency-Key → Mollie answers the same refund (refunded once)', array($a->ok, $b->ok, $a->gateway_ref === $b->gateway_ref, efb_mollie_last('POST', '#/refunds$#')['idem'], Efb_Test_Mollie_Fake::payment($q['gateway_ref'])['refunded']), array(true, true, true, hash('sha256', 'retry-test'), 500));
$db->query('DELETE FROM wp_emsfb_pmt_rfd_ WHERE id = ' . (int) $rid);
Efb_Test_Mollie_Fake::update($q['gateway_ref'], array('refunded' => 0));

$GLOBALS['mollie_refund_force'] = 'error-422';
add_filter('pre_http_request', function ($pre, $args, $url) {
	if (empty($GLOBALS['mollie_refund_force']) || strpos($url, '/refunds') === false) return $pre;
	$f = Efb_Test_Mollie_Fake::fixture($GLOBALS['mollie_refund_force']);
	$body = $f['body'];
	if (isset($GLOBALS['mollie_refund_status'])) $body['status'] = $GLOBALS['mollie_refund_status'];
	return array('headers' => array(), 'body' => json_encode($body), 'response' => array('code' => $f['code']), 'cookies' => array());
}, 5, 3);
$r = Service::refund(Store::get($q['id']), 500, '', 1);
$rf = Store::refunds($q['id']);
$t('Mollie refuses the refund (422) → not refunded: refund row failed, payment still paid', array($r['ok'], end($rf)['status'], Store::get($q['id'])['status']), array(false, 'failed', 'paid'));
$GLOBALS['mollie_refund_force'] = 'refund-201';
$GLOBALS['mollie_refund_status'] = 'failed';
$r = Service::refund(Store::get($q['id']), 400, '', 1);
$t('Mollie answers a refund with status "failed" → not refunded', array($r['ok'], Store::get($q['id'])['status']), array(false, 'paid'));
$GLOBALS['mollie_refund_force'] = null;
unset($GLOBALS['mollie_refund_status']);

$jform = efb_mollie_form('jpy');
$jform[4]['price'] = '1500';
$j = $paid($jform);
Service::refund($j, 500, '', 1);
$t('JPY refund: "500" JPY (no decimals)', efb_mollie_last('POST', '#/refunds$#')['body']['amount'], array('currency' => 'JPY', 'value' => '500'));

/* settings and keys */
$schema = $gw->settings_schema();
$t('settings: two secret fields, test_api_key ^test_…, live_api_key ^live_…', array_map(function ($f) { return array($f['key'], $f['type'], $f['pattern']); }, $schema), array(array('test_api_key', 'secret', '^test_[A-Za-z0-9]{20,}$'), array('live_api_key', 'secret', '^live_[A-Za-z0-9]{20,}$')));
$GLOBALS['opts']['emsfb_pgw_mollie'] = array();
$s = Settings::load('mollie');
$refused = $s->apply($schema, array('test_api_key' => efb_mollie_key('live', 'Swap'), 'live_api_key' => efb_mollie_key('test', 'Swap')), 'test');
$t('a Live key pasted in the Test field (and the reverse) is not saved', array($refused, $s->secret('test_api_key', 'test'), $s->secret('live_api_key', 'live')), array(array('test_api_key', 'live_api_key'), '', ''));
$s->apply($schema, array('test_api_key' => efb_mollie_key('test')), 'test');
$s = Settings::load('mollie');
$t('only a Test key: configured for Test, not for Live', array($gw->is_configured($s, 'test'), $gw->is_configured($s, 'live')), array(true, false));
$raw = json_encode(get_option('emsfb_pgw_mollie'));
$t('the key is stored encrypted (enc:v1:), never as plain text', strpos($raw, efb_mollie_key('test')) === false && strpos($raw, 'enc:v1:') !== false, true);
$c = $gw->test_connection($s, 'live');
$t('Test connection (Live) without a Live key → "No keys yet."', array($c->ok, $c->error, $c->message), array(false, 'no_key', 'No keys yet.'));
$c = $gw->test_connection($s, 'test');
$ml = efb_mollie_last('GET', '#^/v2/methods$#');
$t('Test connection (Test): GET /v2/methods for 10.00 EUR with the Test key → ok', array($c->ok, strpos($ml['query'], 'amount%5Bvalue%5D=10.00&amount%5Bcurrency%5D=EUR') === 0, $ml['key_mode']), array(true, true, 'test'));
$cache = Settings::load('mollie')->cache('methods_test');
$t('...and keeps the account\'s methods for Test mode (the field\'s method list)', array(array_map(function ($m) { return $m[0]; }, $cache['list']), Settings::load('mollie')->cache('methods_live')), array(array('ideal', 'creditcard', 'bancontact', 'paypal', 'banktransfer'), null));
$o = get_option('emsfb_pgw_mollie');
$o['secret']['test']['test_api_key'] = Secrets::encrypt(efb_mollie_key('test', 'REVOKED'));
update_option('emsfb_pgw_mollie', $o);
$c = $gw->test_connection(Settings::load('mollie'), 'test');
$t('a key Mollie refuses (401) → "Mollie refused this key…"', array($c->ok, $c->error, strpos($c->message, 'Mollie refused this key') === 0), array(false, 'unauthorized', true));
$o['secret']['live']['live_api_key'] = Secrets::encrypt(efb_mollie_key('test'));
$o['secret']['test']['test_api_key'] = Secrets::encrypt(efb_mollie_key('test'));
update_option('emsfb_pgw_mollie', $o);
$c = $gw->test_connection(Settings::load('mollie'), 'live');
$t('a Test key that ended up in the Live slot → "This looks like a Test key. Paste it in the Test field."', array($c->ok, $c->error, $c->message), array(false, 'wrong_mode', 'This looks like a Test key. Paste it in the Test field.'));
$t('...and Live is not "configured", so no payment is created with it', $gw->is_configured(Settings::load('mollie'), 'live'), false);

$s = Settings::load('mollie');
$s->apply($schema, array('test_api_key' => efb_mollie_key('test'), 'live_api_key' => efb_mollie_key('live')), 'test');
$tp = efb_mollie_create($db);
$tp = Store::find_by_uid($tp['pay']['uid']);
Settings::load('mollie')->set_mode('live');
$gw->fetch(new Payment($tp), Settings::load('mollie'));
$t('a Test payment is read with the Test key even after the site switched to Live', efb_mollie_last('GET', '#^/v2/payments/#')['key_mode'], 'test');
Settings::load('mollie')->set_mode('test');

/* secrets never reach a browser */
if (!function_exists('current_user_can')) { function current_user_can($c) { return false; } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'https://site.example/wp-admin/' . $p; } }
$info = json_encode(Registry::public_info('mollie'));
$bv = json_encode(\Emsfb\Payments\Frontend::builder_vars());
$t('public_info and the builder vars carry no key (configured flags only)', array(strpos($info . $bv, 'test_Fake') === false && strpos($info . $bv, 'live_Fake') === false && strpos($info . $bv, 'enc:v1') === false, json_decode($info, true)['configured']), array(true, array('test' => true, 'live' => true)));
$html = \Emsfb\Payments\Frontend::field_markup(array('id_' => 'pf', 'type' => 'mollie', 'name' => 'Pay'), array('button_color' => 'btn-darkb'), 7);
$t('the public field markup has the test banner and methods, and no key', array(strpos($html, 'data-gw="mollie"') !== false, strpos($html, 'iDEAL | Wero') !== false, strpos($html, 'test_') === false && strpos($html, 'live_') === false), array(true, true, false === false));
$logs = json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_evt_', ARRAY_A)) . json_encode(Efb_Test_Mollie_Fake::state()['log']);
$t('no key in the event log or in what the fake recorded of the requests', strpos($logs, efb_mollie_key('test')) === false && strpos($logs, efb_mollie_key('live')) === false, true);

/* logos */
$logos = $gw->logos();
$t('logos are the add-on\'s own SVGs (no request to mollie.com from the visitor), files exist', array(count($logos) > 0, count(array_filter($logos, function ($l) { return strpos($l[0], EMSFB_PLUGIN_URL . 'vendor/mollie/assets/img/') === 0 && is_readable(efb_mollie_dir() . '/assets/img/' . basename($l[0])); })) === count($logos), strpos(json_encode($logos), 'mollie.com') === false), array(true, true, true));

/* i18n */
$dir = efb_mollie_dir();
$code = '';
foreach (glob($dir . '/*.php') as $f) $code .= file_get_contents($f);
preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)',\s*'efb-mollie'\s*\)/", $code, $m);
$used = array_values(array_unique(array_map('stripslashes', $m[1])));
$group = array_values(Emsfb_Addon_Phrases_Mollie::group());
$pot = file_get_contents($dir . '/languages/efb-mollie.pot');
$missing = array_values(array_filter($used, function ($s) use ($group, $pot) { return !in_array($s, $group, true) || strpos($pot, 'msgid "' . addcslashes($s, '"\\') . '"') === false; }));
$t('every efb-mollie string of the code is in the phrase class and in efb-mollie.pot (' . count($used) . ' strings)', $missing, array());
$t('phrase class SLUG / DOMAIN', array(Emsfb_Addon_Phrases_Mollie::SLUG, Emsfb_Addon_Phrases_Mollie::DOMAIN), array('mollie', 'efb-mollie'));
$loc = json_decode(file_get_contents($dir . '/languages/locales.json'), true);
$t('languages/locales.json: domain efb-mollie, no locale yet (not translated, owner\'s instruction)', $loc, array('domain' => 'efb-mollie', 'locales' => array()));
$req = array('vendor/mollie/routes-efb.php', 'vendor/mollie/mollie-phrases-efb.php', 'vendor/mollie/languages/locales.json');
$t('the files core requires for AdnMOL exist', array_values(array_filter($req, function ($f) use ($dir) { return !is_readable($dir . '/' . substr($f, strlen('vendor/mollie/'))); })), array());

/* zip */
$zip = dirname(realpath($dir)) . '/mollie.zip';
if (!is_readable($zip) || !class_exists('ZipArchive')) {
	echo "[SKIP] mollie.zip not built yet\n";
} else {
	$z = new ZipArchive();
	$z->open($zip);
	$names = array(); $bad = array();
	for ($i = 0; $i < $z->numFiles; $i++) {
		$st = $z->statIndex($i);
		$names[] = $st['name'];
		$z->getExternalAttributesIndex($i, $os, $attr);
		$mode = ($attr >> 16) & 0777;
		if ($mode !== (substr($st['name'], -1) === '/' ? 0755 : 0644) || date('Y-m-d H:i', $st['mtime']) !== '2026-10-18 00:00') $bad[] = $st['name'];
	}
	$sorted = $names;
	usort($sorted, function ($a, $b) { return strcmp(rtrim($a, '/'), rtrim($b, '/')); });
	$files = array('mollie/');
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
	foreach ($it as $f) $files[] = 'mollie/' . substr($f->getPathname(), strlen($dir) + 1) . ($f->isDir() ? '/' : '');
	sort($files);
	$n2 = $names; sort($n2);
	$t('mollie.zip: one top folder mollie/, sorted, folders included, 0755/0644, 2026-10-18 00:00, same files as the folder', array(count(array_filter($names, function ($n) { return strpos($n, 'mollie/') !== 0; })), $names === $sorted, $bad, $n2 === $files), array(0, true, array(), true));
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
