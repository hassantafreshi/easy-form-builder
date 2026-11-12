<?php
/**
 * Payplug add-on: the keys never leave the encrypted option. Checked in everything a browser or
 * an agent (AutoPilot / SitePilot) can read: the capability registry (field:payplug,
 * payment_gateways), the builder's efb_var.pay, the gateway's public info, the saved form JSON
 * (the payment field row goes through the core sanitizer), the create / status / poll answers,
 * the webhook answers and the event log; and the AdnPPG add-on is known to core (required files,
 * i18n files, plan level).
 *
 * Run: php tests/test-addon-payplug-secrets.php
 */
$passed = 0; $failed = 0;
require __DIR__ . '/addon-payplug-test-lib.php';
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Builder_Api;
$t = 'efb_pay_t';
$db = efb_ppg_env('test', array('method_bancontact' => '1'));
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'https://site.example/wp-admin/' . $p; } }
$no_key = function ($x) { $j = json_encode($x); return strpos($j, 'unittest-ppg-key') === false && strpos($j, 'notasignature0') === false && strpos($j, 'enc:v1:') === false; };

$cap = Builder_Api::capabilities(array('package' => 3, 'settings' => (object) array()));
$gw = $cap['payment_gateways']['payplug'] ?? null;
$t('capabilities: payment_gateways.payplug (AdnPPG, configured test/live, EUR, methods card + bancontact)', array($gw['addon'], $gw['configured'], $gw['currencies'], array_map(function ($m) { return $m[0]; }, $gw['methods'])), array('AdnPPG', array('test' => true, 'live' => true), array('EUR'), array('card', 'bancontact')));
$t('capabilities: payment_keys.payplug is a yes/no only', $cap['payment_keys']['payplug'], true);
$t('capabilities: field:payplug needs the AdnPPG add-on, Free Plus', array($cap['features']['field:payplug']['package'] ?? null, $cap['features']['addon:AdnPPG']['package'] ?? null), array('free_plus', 'free_plus'));
$t('capabilities carry no key', $no_key($cap), true);
$t('builder vars (efb_var.pay) carry no key', $no_key(\Emsfb\Payments\Frontend::builder_vars()), true);

// The saved form: the payment field row through the core sanitizer.
require_once __DIR__ . '/../includes/functions.php';
if (class_exists('efbFunction')) {
	$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();
	$clean = $fn->sanitize_obj_msg_efb(array(array('type' => 'payment', 'getway' => 'payplug', 'currency' => 'EUR'), array('id_' => 'pf', 'type' => 'payplug', 'pf_btn_text' => '<b>Payer</b> %amount%', 'pf_methods' => 'card,bancontact,../x', 'pgw_map_first' => 'fn<script>', 'pgw_map_city' => 'ci')));
	$t('form JSON: pf_* keys through Payments\\Field (tags removed, method ids checked)', array($clean[1]['pf_btn_text'], $clean[1]['pf_methods']), array('b Payer /b %amount%', 'card,bancontact'));
	$t('form JSON: pgw_map_* through core\'s gateway option sanitizer (a field id or nothing)', array($clean[1]['pgw_map_first'], $clean[1]['pgw_map_city']), array('', 'ci'));
	$t('form JSON has no key', $no_key($clean), true);
} else {
	echo "[SKIP] efbFunction not loadable here\n";
}

// Create / status / poll / webhook answers and the event log.
list($res, $p) = efb_ppg_create($db);
$t('create answer has no key', $no_key($res), true);
EfbPayplugFake::set($p['gateway_ref'], 'get-paid');
$rq = new WP_REST_Request('GET'); $rq->set_param('p', $p['uid']); $rq->set_param('s', Service::sign($p['uid'], 'payplug'));
$st = Service::handle_status($rq);
$t('status answer: paid, no key, no-store', array($st->data['state'], $no_key($st->data), $st->headers['Cache-Control']), array('paid', true, 'no-store, max-age=0'));
$wh = Service::handle_webhook(efb_ppg_notify($p, array('id' => $p['gateway_ref'], 'object' => 'payment', 'is_live' => false)));
$t('webhook answers have no body', $wh->data, null);
$t('the event log has no key and no Authorization header', $no_key($db->get_results('SELECT * FROM wp_emsfb_pmt_evt_', ARRAY_A)) && strpos(json_encode($db->get_results('SELECT * FROM wp_emsfb_pmt_evt_', ARRAY_A)), 'Bearer') === false, true);
$t('the payment rows have no key', $no_key($db->get_results('SELECT * FROM wp_emsfb_pmt_', ARRAY_A)), true);

// Add-on registry in core.
if (class_exists('efbFunction')) {
	$req = $fn->get_addon_required_files_efb();
	$i18n = $fn->get_addon_i18n_required_files_efb();
	$t('core required file for AdnPPG exists in the add-on', array($req['AdnPPG'], is_readable(__DIR__ . '/../' . $req['AdnPPG'][0])), array(array('vendor/payplug/routes-efb.php'), true));
	$t('core i18n files for AdnPPG exist (phrases class + locales.json)', array_map(function ($f) { return is_readable(__DIR__ . '/../' . $f); }, $i18n['AdnPPG']), array(true, true));
}
$loc = json_decode(file_get_contents(__DIR__ . '/../vendor/payplug/languages/locales.json'), true);
$t('locales.json: domain efb-payplug, no translations shipped yet (English source only)', array($loc['domain'], $loc['locales']), array('efb-payplug', array()));
$pot = file_get_contents(__DIR__ . '/../vendor/payplug/languages/efb-payplug.pot');
$src = file_get_contents(__DIR__ . '/../vendor/payplug/class-Emsfb-payplug-gateway.php') . file_get_contents(__DIR__ . '/../vendor/payplug/payplug-phrases-efb.php');
preg_match_all("/__\\(\\s*(['\"])((?:\\\\.|(?!\\1).)*)\\1\\s*,\\s*'efb-payplug'/", $src, $m);
$missing = array();
foreach ($m[2] as $i => $s) { $s = $m[1][$i] === "'" ? str_replace("\\'", "'", $s) : $s; if (strpos($pot, 'msgid "' . addcslashes($s, '"') . '"') === false) $missing[] = $s; }
$t('every efb-payplug string of the add-on is in efb-payplug.pot', $missing, array());
$t('Emsfb_Addon_Phrases_Payplug::group() has the PAY02 §13 keys', array_keys(Emsfb_Addon_Phrases_Payplug::group()), array('payplug', 'payplugKeyTest', 'payplugKeyLive', 'payplugCardMode', 'payplugHosted', 'payplugIntegrated', 'payplugSchemeChoice', 'payplugSchemeLabel', 'payplugEurOnly', 'payplugAmountRange', 'payplugOney', 'payplugOneyRange', 'payplugBillingMap', 'payplugSecureNote'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
