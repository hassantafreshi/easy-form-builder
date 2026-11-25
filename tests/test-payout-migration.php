<?php
/**
 * PAY-OUT step 5: the forms of the older Stripe, PayPal and Persia Pay fields move to the add-ons' framework fields
 * (core's Payments\Legacy_Migration with the add-ons' filters), against the SQLite test database:
 *
 *  A  the map: only gateways registered now; a form waits (nothing written) without its add-on or its keys;
 *  B  a move: the keys are copied from the add-on's own (seed), only the gateway field and row 0 change, the original
 *     JSON is kept byte for byte (backup, read back), the save hooks run (form versions), the move is logged;
 *  C  once: moving again, or another batch, changes nothing and logs nothing new;
 *  D  the same amount: every priced choice through the moved form's quote equals what the older field sent the
 *     provider (Stripe minor units incl. JPY, PayPal values incl. JPY, ZarinPal rials); a currency whose amount would
 *     change (ISK at Stripe: the older field sent x100) or a fraction PayPal would round (12.5 JPY) is not moved;
 *  E  what stays: subscriptions, a Persia Pay sub-gateway other than ZarinPal, two gateway fields (final, logged);
 *  F  batches of 20, waiting forms looked at again, a run lock;
 *  G  rollback: byte for byte, not moved again by the next batch, "Move again" on request, no backup → refused;
 *  H  entries and receipts untouched; a moved form whose add-on is off says which add-on it needs (pay_addon_inactive);
 *  I  the contract: legacy_gateway 2.2, deprecated 4.3.2, removed in 5.0; an add-on announcing 2 is still accepted.
 *
 * Run: php tests/test-payout-migration.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require_once __DIR__ . '/pay-test-db.php';
require_once __DIR__ . '/payout-fake-providers.php';
if (!defined('EMSFB_PLUGIN_URL')) define('EMSFB_PLUGIN_URL', 'https://site.example/wp-content/plugins/easy-form-builder/');
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);
if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
if (!function_exists('delete_option')) { function delete_option($k) { unset($GLOBALS['opts'][$k]); return true; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 1; } }
if (!function_exists('current_user_can')) { function current_user_can($c) { return !empty($GLOBALS['efb_can']); } }
if (!function_exists('get_setting_Emsfb')) { function get_setting_Emsfb($m = 'decoded') { return isset($GLOBALS['efb_settings']) ? $GLOBALS['efb_settings'] : null; } }
require_once __DIR__ . '/../includes/addon-contracts-functions-efb.php';
use Emsfb\Payments\Registry;
use Emsfb\Payments\Settings;
use Emsfb\Payments\Legacy_Migration as M;
use Emsfb\Payments\Readiness;
$t = 'efb_pay_t';
$F = 'Efb_Test_Payout_Fake';
$addons = getenv('EFB_ADDONS_REPO') ?: dirname((string) @readlink(__DIR__ . '/../vendor/stripe'));

require_once __DIR__ . '/../includes/class-Emsfb-builder-api.php';
efb_pay_test_load();
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-legacy-migration.php';
function mg_env($with = array('stripe', 'paypal', 'persiapay')) {
	global $addons;
	$GLOBALS['opts'] = array('emsfb_pro' => 3);
	$GLOBALS['efb_transients'] = $GLOBALS['efb_mails'] = $GLOBALS['efb_filters'] = $GLOBALS['efb_hooks'] = $GLOBALS['efb_actions_log'] = $GLOBALS['efb_cron'] = array();
	$db = $GLOBALS['wpdb'] = new EfbPayTestDb();
	$db->create_payment_tables();
	Registry::reset();
	Efb_Test_Payout_Fake::reset();
	add_filter('pre_http_request', array('Efb_Test_Payout_Fake', 'http'), 10, 3);
	foreach ($with as $a) require $addons . '/' . $a . '/routes-efb.php';
	foreach (array('Stripe_Keys_Efb', 'Paypal_Keys_Efb', 'PersiaPay_Keys_Efb') as $k) if (class_exists('\\Emsfb\\' . $k)) call_user_func(array('\\Emsfb\\' . $k, 'forget'));
	Registry::all();
	return $db;
}
/* The settings row of a site whose keys were never moved: the older Settings › Payments keys (placeholders). */
function mg_old_keys() {
	$GLOBALS['efb_settings'] = (object) array('stripePKey' => 'pk_test_' . str_repeat('EfbFake', 4), 'stripeSKey' => Efb_Test_Payout_Fake::stripe_key('test'),
		'paypalPKey' => Efb_Test_Payout_Fake::paypal_id(), 'paypalSKey' => Efb_Test_Payout_Fake::paypal_secret(), 'payToken' => Efb_Test_Payout_Fake::zarinpal_merchant());
}
function mg_form($type, $over = array(), $prices = null, $cur = null) {
	$persia = $type === 'persiaPay';
	$prices = $prices ?: ($persia ? array('250000', '125000') : array('25', '12.5'));
	$r0 = array_merge(array('type' => 'payment', 'formName' => 'Legacy ' . $type, 'getway' => $type, 'currency' => $cur ?: ($persia ? 'irr' : 'usd'), 'paymentmethod' => 'charge', 'email' => 'owner@example.test', 'trackingCode' => '1', 'steps' => 1, 'email_to' => 'em'), $persia ? array('persiaPay' => 'zarinPal') : array(), $over);
	return array(
		$r0,
		array('id_' => '1', 'type' => 'step', 'step' => 1, 'name' => 'Step'),
		array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'step' => 1, 'required' => 1),
		array('id_' => 'rad', 'type' => 'payRadio', 'name' => 'Ticket', 'step' => 1, 'required' => 1),
		array('id_' => 'o1', 'parent' => 'rad', 'type' => 'option', 'value' => 'Adult', 'price' => $prices[0]),
		array('id_' => 'o2', 'parent' => 'rad', 'type' => 'option', 'value' => 'Child', 'price' => $prices[1]),
		array('id_' => 'pf', 'type' => $type, 'elementId' => $type, 'name' => ucfirst($type), 'step' => 1),
	);
}
function mg_raw($db, $id) { return (string) $db->get_var('SELECT form_structer FROM wp_emsfb_form WHERE form_id = ' . (int) $id); }
function mg_rows($db, $id) { return json_decode(str_replace('\\"', '"', mg_raw($db, $id)), true); }

/* ---------------------------------------------------------------- A */
echo "\n=== A the map; waiting forms are not written\n";
$db = mg_env();
$t('A targets: each older type → the add-on\'s framework field (registered)', M::targets(), array('stripe' => 'stripecheckout', 'paypal' => 'paypalcheckout', 'persiaPay' => 'zarinpalgate', 'zarinPal' => 'zarinpalgate', 'persiapay' => 'zarinpalgate'));
$db2 = mg_env(array('stripe'));
$t('A only the gateways registered now (PayPal / Persia Pay add-ons off: no target)', M::targets(), array('stripe' => 'stripecheckout'));
$fid = $db2->add_form(mg_form('paypal'), 'payment');
$raw = mg_raw($db2, $fid);
$e = M::migrate_form($fid);
$t('A a PayPal form while its add-on is off (or older): waiting_addon, not written, no backup', array($e['status'], mg_raw($db2, $fid) === $raw, M::backup_get($fid)), array('waiting_addon', true, null));
$db = mg_env();
$GLOBALS['efb_settings'] = (object) array();
$fid = $db->add_form(mg_form('stripe'), 'payment');
$raw = mg_raw($db, $fid);
$e = M::migrate_form($fid);
$t('A no keys anywhere: waiting_keys, not written, no backup', array($e['status'], mg_raw($db, $fid) === $raw, M::backup_get($fid)), array('waiting_keys', true, null));
$t('A waiting is not final: still pending for the next batch', in_array($fid, M::pending(), true), true);

/* ---------------------------------------------------------------- B */
echo "\n=== B a move\n";
mg_old_keys();
\Emsfb\Stripe_Keys_Efb::forget();
$e = M::migrate_form($fid);
$rows = mg_rows($db, $fid);
$t('B with the older keys: migrated (from stripe to stripecheckout)', array($e['status'], $e['from'], $e['to']), array('migrated', 'stripe', 'stripecheckout'));
$s = Settings::load('stripecheckout');
$t('B the keys were copied into the gateway\'s settings (test mode by the key, encrypted)', array($s->mode(), $s->secret('secret_key', 'test') === $F::stripe_key('test'), strpos(json_encode($GLOBALS['opts']['emsfb_pgw_stripecheckout']), $F::stripe_key('test')) === false), array('test', true, true));
$t('B only the gateway field and row 0 changed', array($rows[6]['type'], $rows[6]['elementId'], $rows[0]['getway'], $rows[0]['currency'], array_slice($rows, 1, 5) === array_slice(mg_form('stripe'), 1, 5), $rows[6]['id_']), array('stripecheckout', 'stripecheckout', 'stripecheckout', 'usd', true, 'pf'));
$bak = M::backup_get($fid);
$t('B the original JSON kept byte for byte (not autoloaded option, checksum)', array($bak['raw'] === $raw, $bak['type']), array(true, 'payment'));
$saved = array_values(array_filter($GLOBALS['efb_actions_log'], function ($a) { return $a[0] === 'efb_form_saved'; }));
$t('B the save hooks ran (form versions: source api, the note, the replaced rows)', array(count($saved), $saved[0][1][0], $saved[0][1][2]['source'], strpos($saved[0][1][2]['rev_note'], 'stripe → stripecheckout') !== false, $saved[0][1][2]['old_rows'] === mg_form('stripe')), array(1, $fid, 'api', true, true));
$log = M::log_get($fid);
$t('B logged per form: waiting_keys then migrated, by cron, with when', array(array_map(function ($ev) { return $ev[1]; }, $log['events']), $log['by'], (bool) preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $log['moved_at'])), array(array('waiting_keys', 'migrated'), 'cron', true));
$t('B the moved form is ready to take payments (Readiness)', Readiness::of_form($rows, $fid)['ready'], true);

/* ---------------------------------------------------------------- C */
echo "\n=== C once\n";
$raw2 = mg_raw($db, $fid);
$n = count(M::log_get($fid)['events']);
$e = M::migrate_form($fid);
$r = M::run_batch();
$t('C moving again / another batch: nothing written, no new event, not pending', array($e['status'], mg_raw($db, $fid) === $raw2, count(M::log_get($fid)['events']), in_array($fid, M::pending(), true), isset($r['done'][$fid])), array('migrated', true, $n, false, false));
$t('C the backup is still the first original', M::backup_get($fid)['raw'], $raw);

/* ---------------------------------------------------------------- D */
echo "\n=== D the same amount\n";
require_once $addons . '/stripe/legacy-price-efb.php';
$q = \Emsfb\Payments\Pricing::quote($rows, array(array('id_' => 'rad', 'id_ob' => 'o2', 'type' => 'payRadio', 'value' => 'Child')), array('gateway' => 'stripecheckout', 'field_id' => 'pf'));
$old = \Emsfb\Legacy_Server_Price_V1::price(array(array('id_' => 'rad', 'id_ob' => 'o2', 'type' => 'payRadio', 'value' => 'Child', 'price' => '12.5', 'name' => 'Ticket')), mg_form('stripe'));
$t('D Child: the older field priced 12.5 USD (Stripe 1250), the moved form quotes 1250', array($old['price_total'], $q['total_minor']), array(12.5, 1250));
$cases = array(
	array('stripe', 'jpy', array('500', '250'), 'migrated'),
	array('stripe', 'isk', array('500', '250'), 'amount_differs'),
	array('stripe', 'kwd', array('1.5', '0.25'), 'migrated'),
	array('paypal', 'jpy', array('500', '250'), 'migrated'),
	array('paypal', 'jpy', array('12.5', '250'), 'migrated'), // both send 13 (PayPal JPY has no decimals; both round)
	array('paypal', 'twd', array('12.5', '250'), 'amount_differs'), // the older field sent "13", the new one cannot send 12.50 TWD
	array('paypal', 'eur', array('19.99', '0.5'), 'migrated'),
	array('persiaPay', 'usd', array('250000', '125000'), 'migrated'),
	array('persiaPay', 'irr', array('2500.5', '125000'), 'amount_differs'), // ZarinPal never takes half a rial
);
foreach ($cases as $c) {
	$id = $db->add_form(mg_form($c[0], array(), $c[2], $c[1]), 'payment');
	$raw_c = mg_raw($db, $id);
	$e = M::migrate_form($id);
	$t("D {$c[0]} " . strtoupper($c[1]) . " " . implode('/', $c[2]) . ": {$c[3]}" . ($c[3] === 'migrated' ? '' : ' (not written)'), array($e['status'], $c[3] === 'migrated' ? true : mg_raw($db, $id) === $raw_c), array($c[3], true));
	if ($c[0] === 'persiaPay' && $c[3] === 'migrated') {
		$pr = mg_rows($db, $id);
		$qq = \Emsfb\Payments\Pricing::quote($pr, array(array('id_' => 'rad', 'id_ob' => 'o1', 'type' => 'payRadio', 'value' => 'Adult')), array('gateway' => 'zarinpalgate', 'field_id' => 'pf'));
		$t('D Persia Pay: row 0 currency irr, the sub-gateway key gone; Adult 250000 rials as before (IRR minor units, ZarinPal sent whole rials)', array($pr[0]['currency'], isset($pr[0]['persiaPay']), $pr[6]['type'], $qq['total_minor'], $qq['currency'], \Emsfb\Payments\Zarinpal_Gateway::rials($qq['total_minor'])), array('irr', false, 'zarinpalgate', 25000000, 'IRR', 250000));
	}
}
$id = $db->add_form(mg_form('stripe', array(), array('25', '12.5'), 'usd'), 'payment');
$fr = mg_form('stripe');
$fr[] = array('id_' => 'tip', 'type' => 'prcfld', 'name' => 'Tip', 'step' => 1, 'milen' => '2.5');
$id = $db->add_form($fr, 'payment');
$t('D a typed amount (Min 2.5) charges the same: migrated', M::migrate_form($id)['status'], 'migrated');

/* ---------------------------------------------------------------- E */
echo "\n=== E what stays on the older field\n";
foreach (array(
	array(mg_form('stripe', array('paymentmethod' => 'month')), 'recurring', 'month'),
	array(mg_form('paypal', array('paymentmethod' => 'year')), 'recurring', 'year'),
	array(mg_form('persiaPay', array('persiaPay' => 'payping')), 'gateway_unsupported', 'payping'),
) as $c) {
	$id = $db->add_form($c[0], 'payment');
	$raw_c = mg_raw($db, $id);
	$e = M::migrate_form($id);
	$t("E {$c[1]} ({$c[2]}): stays, logged with why, final (not pending)", array($e['status'], $e['reason'], mg_raw($db, $id) === $raw_c, in_array($id, M::pending(), true)), array($c[1], $c[2], true, false));
}
$two = mg_form('stripe');
$two[] = array('id_' => 'pf2', 'type' => 'paypal', 'name' => 'PayPal', 'step' => 1);
$id = $db->add_form($two, 'payment');
$t('E two gateway fields: several_gateways, final', M::migrate_form($id)['status'], 'several_gateways');

/* ---------------------------------------------------------------- F */
echo "\n=== F batches\n";
$db = mg_env();
mg_old_keys();
$ids = array();
for ($i = 0; $i < 23; $i++) $ids[] = $db->add_form(mg_form($i % 3 === 0 ? 'paypal' : ($i % 3 === 1 ? 'stripe' : 'persiaPay')), 'payment');
$plain = $db->add_form(array(array('type' => 'form', 'formName' => 'No payment'), array('id_' => 'x', 'type' => 'text', 'name' => 'stripe', 'step' => 1)), 'form');
$t('F candidates: the 23 forms with an older field (a text field named "stripe" is not one)', array(count(M::candidates()), in_array($plain, M::candidates(), true)), array(23, false));
$r1 = M::run_batch();
$t('F first batch: 20 moved, 3 left', array(count($r1['done']), count(array_filter($r1['done'], function ($s) { return $s === 'migrated'; })), $r1['left']), array(20, 20, 3));
$r2 = M::run_batch();
$t('F second batch: the 3 others, none left', array(count($r2['done']), $r2['left']), array(3, 0));
$r3 = M::run_batch();
$t('F a third batch: nothing to do; runs counted', array(count($r3['done']), M::log_all()['runs']), array(0, 3));
set_transient(M::LOCK, 1, 120);
$t('F one run at a time (lock): a second run does nothing', M::run_batch()['ran'], false);
delete_transient(M::LOCK);
$db = mg_env(array('stripe'));
mg_old_keys();
$a = $db->add_form(mg_form('paypal'), 'payment');
$b = $db->add_form(mg_form('stripe'), 'payment');
M::cron();
$t('F cron: one moved, one waiting (PayPal add-on off): one more look a minute later', array(M::log_get($a)['status'], M::log_get($b)['status'], isset($GLOBALS['efb_cron'][M::HOOK])), array('waiting_addon', 'migrated', true));
$GLOBALS['efb_cron'] = array();
M::cron();
$t('F cron with only a waiting form left: nothing queued (no busy loop; the admin queues a look at most hourly), not logged again', array(isset($GLOBALS['efb_cron'][M::HOOK]), count(M::log_get($a)['events'])), array(false, 1));
for ($i = 0; $i < 22; $i++) $db->add_form(mg_form('stripe'), 'payment');
$GLOBALS['efb_cron'] = array();
M::cron();
$next = isset($GLOBALS['efb_cron'][M::HOOK]) ? $GLOBALS['efb_cron'][M::HOOK] : 0;
$t('F cron that moved forms with more left: the next batch a minute later; the waiting form not logged again', array($next > time() && $next <= time() + 61, count(M::log_get($a)['events'])), array(true, 1));
M::cron();
require $addons . '/paypal/routes-efb.php';
Registry::reset(); Registry::all();
M::run_batch();
$t('F the PayPal add-on updated: the waiting form moves on the next batch (one waiting event, then migrated)', array(M::log_get($a)['status'], array_map(function ($ev) { return $ev[1]; }, M::log_get($a)['events'])), array('migrated', array('waiting_addon', 'migrated')));

/* ---------------------------------------------------------------- G */
echo "\n=== G rollback\n";
$orig = mg_raw($db, $b);
$bak = M::backup_get($b);
$r = M::rollback($b);
$t('G rollback: the original JSON back byte for byte, logged rolled_back by admin', array($r['ok'], mg_raw($db, $b) === $bak['raw'], M::log_get($b)['status'], M::log_get($b)['by']), array(true, true, 'rolled_back', 'admin'));
$t('G the next batches leave it on the older field', array(M::run_batch()['done'], mg_rows($db, $b)[6]['type']), array(array(), 'stripe'));
$e = M::migrate_form($b, 'admin', true);
$t('G "Move again" on request: migrated, the backup still the first original', array($e['status'], mg_rows($db, $b)[6]['type'], M::backup_get($b)['raw'] === $bak['raw']), array('migrated', 'stripecheckout', true));
$c = $db->add_form(mg_form('stripe'), 'payment');
$t('G rollback of a form never moved: no_backup, nothing written', array(M::rollback($c)['code'], mg_raw($db, $c) === str_replace('"', '\\"', json_encode(mg_form('stripe'), JSON_UNESCAPED_UNICODE))), array('no_backup', true));
$GLOBALS['opts'][M::BAK . $b]['raw'] .= 'x';
$t('G a backup that does not match its checksum is not used', M::rollback($b)['code'], 'no_backup');

/* ---------------------------------------------------------------- H */
echo "\n=== H entries, receipts, an add-on off\n";
$db = mg_env();
mg_old_keys();
$fid = $db->add_form(mg_form('stripe'), 'payment');
$entry = array(array('id_' => 'em', 'type' => 'email', 'value' => 'payer@example.test'), array('id_' => 'payment', 'type' => 'payment', 'amount' => 25, 'paymentIntent' => 'pi_EfbFake0001', 'paymentGateway' => 'stripe', 'paymentCreated' => '2026-11-01', 'paymentcurrency' => 'usd', 'paymentmethod' => 'charge'));
$mid = $db->add_entry($fid, $entry, '2026-11-01 10:00:00', array('read_' => 0, 'track' => 'EFB12345'));
$before = $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $mid);
M::migrate_form($fid);
M::rollback($fid);
M::migrate_form($fid, 'admin', true);
$t('H the earlier entry and its receipt (Stripe paymentIntent, amount) are untouched by moves and rollbacks', $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $mid), $before);
$moved = mg_rows($db, $fid);
$db = mg_env(array());
$rd = Readiness::of_form($moved, $fid);
$t('H a moved form whose Stripe add-on is off: pay_addon_inactive naming AdnSPF (never an unknown field)', array($rd['ready'], $rd['issues'][0]['code'], $rd['issues'][0]['addon']), array(false, 'pay_addon_inactive', 'AdnSPF'));
$t('H Readiness knows the three new fields while their add-ons are off', array(Readiness::is_payment_type('stripecheckout'), Readiness::is_payment_type('paypalcheckout'), Readiness::is_payment_type('zarinpalgate'), Readiness::addon_of('zarinpalgate')), array(true, true, true, 'AdnPPF'));

require_once __DIR__ . '/../includes/class-Emsfb-form-package.php';
$kt = \Emsfb\Form_Package::known_types();
$t('H a form package (Data Tools) with a moved form imports whole while the add-on is off (the three types are known)', array(in_array('stripecheckout', $kt, true), in_array('paypalcheckout', $kt, true), in_array('zarinpalgate', $kt, true), in_array('stripe', $kt, true)), array(true, true, true, true));

/* ---------------------------------------------------------------- I */
echo "\n=== I the contract and REST\n";
$v = efb_addon_contract_versions()['legacy_gateway'];
$t('I legacy_gateway 2.2 (min 2), deprecated 4.3.2, removed in 5.0', array($v['version'], $v['min'], $v['deprecated'], $v['removal']), array(2.2, 2, '4.3.2', '5.0'));
add_filter('efb_addon_contracts', function ($c) { $c['legacy_gateway']['AdnSPF'] = 2; return $c; });
$c = efb_addon_contracts()['legacy_gateway'];
$t('I an add-on announcing 2 is accepted (active, not outdated); the answer says deprecated', array($c['active'], $c['outdated'], $c['deprecated'], $c['removal']), array(true, false, '4.3.2', '5.0'));
M::routes();
$perm = $GLOBALS['efb_routes']['Emsfb/v1/payments/legacy-migration/rollback']['permission_callback'];
$GLOBALS['efb_can'] = false;
$t('I REST: only an administrator (manage_options)', $perm(), false);
$GLOBALS['efb_can'] = true;
$t('I REST rollback without form_id: 400', M::rest_rollback(new WP_REST_Request('POST'))->get_status(), 400);
$t('I REST routes: status, run, rollback, rollback-all (QA4-003), migrate', array_values(array_filter(array_keys($GLOBALS['efb_routes']), function ($k) { return strpos($k, 'legacy-migration') !== false; })), array('Emsfb/v1/payments/legacy-migration', 'Emsfb/v1/payments/legacy-migration/run', 'Emsfb/v1/payments/legacy-migration/rollback', 'Emsfb/v1/payments/legacy-migration/rollback-all', 'Emsfb/v1/payments/legacy-migration/migrate'));

/* ---------------------------------------------------------------- J */
echo "\n=== J Move back keeps the owner's edits (QA3-003); every form back before an older version (QA4-003)\n";
if (!function_exists('_n')) { function _n($a, $b, $n, $d = null) { return $n == 1 ? $a : $b; } }
if (!function_exists('number_format_i18n')) { function number_format_i18n($n, $d = 0) { return number_format((float) $n, $d); } }
if (!function_exists('esc_html__')) { function esc_html__($s, $d = null) { return $s; } }
if (!defined('EMSFB_PLUGIN_VERSION')) define('EMSFB_PLUGIN_VERSION', '4.3.2');
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'https://site.example/wp-admin/' . $p; } }
/* The owner's edit in the builder after the move: the stored rows changed by $fn (written the way a save writes them). */
function mg_edit($db, $id, $fn) {
	$rows = $fn(mg_rows($db, $id));
	$db->update('wp_emsfb_form', array('form_structer' => str_replace('"', '\\"', json_encode($rows, JSON_UNESCAPED_UNICODE))), array('form_id' => (int) $id));
	return $rows;
}
$db = mg_env();
mg_old_keys();
$fid = $db->add_form(mg_form('stripe'), 'payment');
$orig = mg_raw($db, $fid);
M::migrate_form($fid);
$edited = mg_edit($db, $fid, function ($r) { $r[4]['price'] = '30'; $r[0]['formName'] = 'Legacy stripe (2027)'; $r[6]['name'] = 'Pay by card'; $r[] = array('id_' => 'nt', 'type' => 'text', 'name' => 'Notes', 'step' => 1); return $r; });
$r = M::rollback($fid);
$back = mg_rows($db, $fid);
$t('J Move back after edits: the gateway field and row 0 are the older ones again (stripe), logged rolled_back, edits_kept', array($r['ok'], $back[6]['type'], $back[6]['elementId'], $back[0]['getway'], M::log_get($fid)['status'], M::log_get($fid)['reason']), array(true, 'stripe', 'stripe', 'stripe', 'rolled_back', 'edits_kept'));
$t('J ...the Adult price of 30, the new form name, the field\'s new label and the added field are kept (old code: the backup with 25)', array($back[4]['price'], $back[0]['formName'], $back[6]['name'], end($back)['id_'], count($back)), array('30', 'Legacy stripe (2027)', 'Pay by card', 'nt', 8));
$exp = $edited; $exp[6]['type'] = 'stripe'; $exp[6]['elementId'] = 'stripe'; $exp[0]['getway'] = 'stripe';
$t('J ...the stored form is the edited form with only the move undone', $back, $exp);
$saved = array_values(array_filter($GLOBALS['efb_actions_log'], function ($a) { return $a[0] === 'efb_form_saved' && strpos($a[1][2]['rev_note'], 'moved back') !== false; }));
$t('J ...the save hooks ran (a form version of the move back)', count($saved), 1);
// A second move: the backup is the form right before it (with the edits), and the next Move back does not bring the older form.
M::migrate_form($fid, 'admin', true);
$t('J a second move: the backup is the edited form (not the first original)', array(M::backup_get($fid)['raw'] === mg_raw($db, $fid), M::backup_get($fid)['raw'] === $orig, json_decode(str_replace('\\"', '"', M::backup_get($fid)['raw']), true)[4]['price']), array(false, false, '30'));
$t('J ...Move back right away gives that form back byte for byte (price 30)', array(M::rollback($fid)['ok'], mg_rows($db, $fid)[4]['price'], mg_rows($db, $fid)[6]['type']), array(true, '30', 'stripe'));
// An owner's own change of a value the move wrote is kept (row 0's currency after a ZarinPal move).
$z = $db->add_form(mg_form('persiaPay'), 'persiaPay');
M::migrate_form($z);
mg_edit($db, $z, function ($r) { $r[0]['currency'] = 'irt'; $r[5]['price'] = '150000'; return $r; });
$r = M::rollback($z);
$zb = mg_rows($db, $z);
$t('J ZarinPal: Move back restores the field (persiaPay), row 0 persiaPay = zarinPal, the form type; keeps the owner\'s currency and price', array($r['ok'], $zb[6]['type'], $zb[0]['persiaPay'], $zb[0]['getway'], $zb[0]['currency'], $zb[5]['price'], (string) $db->get_var('SELECT form_type FROM wp_emsfb_form WHERE form_id = ' . (int) $z)), array(true, 'persiaPay', 'zarinPal', 'persiaPay', 'irt', '150000', 'persiaPay'));
// The moved field given to another gateway or removed since: nothing is guessed.
$c = $db->add_form(mg_form('stripe'), 'payment');
M::migrate_form($c);
$before = mg_edit($db, $c, function ($r) { $r[6]['type'] = 'paypalcheckout'; $r[0]['getway'] = 'paypalcheckout'; return $r; });
$r = M::rollback($c);
$t('J the moved field changed to another gateway since: field_changed, nothing written, still migrated', array($r['ok'], $r['code'], mg_rows($db, $c) === $before, M::log_get($c)['status']), array(false, 'field_changed', true, 'migrated'));
$GLOBALS['efb_routes'] = array();
M::routes();
$GLOBALS['efb_can'] = true;
$rq = new WP_REST_Request('POST');
$rq->set_param('form_id', $c);
$rr = M::rest_rollback($rq);
$t('J REST rollback answers field_changed with an error status (the per-form button shows the failure)', array($rr->get_status(), $rr->get_data()['code']), array(409, 'field_changed'));

/* QA4-003: every moved form back at once, then the upgrader's warning. */
$db = mg_env();
mg_old_keys();
$ids = array($db->add_form(mg_form('stripe'), 'payment'), $db->add_form(mg_form('paypal'), 'payment'), $db->add_form(mg_form('persiaPay'), 'persiaPay'));
$raws = array_map(function ($i) use ($db) { return mg_raw($db, $i); }, $ids);
$keep = $db->add_form(mg_form('stripe', array('paymentmethod' => 'subscription')), 'payment'); // stays on the older field
M::run_batch();
$t('J three forms moved (one subscription form stays)', array(M::moved_ids(), M::log_get($keep)['status']), array($ids, 'recurring'));
mg_edit($db, $ids[1], function ($r) { $r[2]['name'] = 'Your e-mail'; return $r; });
$GLOBALS['efb_routes'] = array();
M::routes();
$res = M::rest_rollback_all(new WP_REST_Request('POST'));
$d = $res->get_data();
$t('J REST payments/legacy-migration/rollback-all: every moved form back (200), none left moved', array($res->get_status(), $d['done'], $d['failed'], M::moved_ids()), array(200, $ids, array(), array()));
$t('J ...each unedited form equals its backup byte for byte (the 4.3.1 form)', array(mg_raw($db, $ids[0]) === $raws[0], mg_raw($db, $ids[2]) === $raws[2]), array(true, true));
$pb = mg_rows($db, $ids[1]);
$exp = json_decode(str_replace('\\"', '"', $raws[1]), true); $exp[2]['name'] = 'Your e-mail';
$t('J ...the edited one equals its backup plus the later edit (label), on the older PayPal field', $pb, $exp);
$t('J the REST route is registered (administrators only)', isset($GLOBALS['efb_routes']['Emsfb/v1/payments/legacy-migration/rollback-all']) && $GLOBALS['efb_routes']['Emsfb/v1/payments/legacy-migration/rollback-all']['permission_callback']() === true, true);
// The upgrader: an older Easy Form Builder about to replace this one while forms are moved.
M::migrate_form($ids[0], 'admin', true);
$pkg = sys_get_temp_dir() . '/efb-pkg-' . getmypid();
@mkdir($pkg);
file_put_contents($pkg . '/emsfb.php', "<?php\n/**\n * Plugin Name: Easy Form Builder\n * Version: 4.3.1\n */\n");
$skin = new class { public $said = array(); public function feedback($m) { $this->said[] = $m; } };
$up = (object) array('skin' => $skin);
$t('J upgrader_source_selection: 4.3.1 over 4.3.2 with a moved form → the upgrader says so with the link; the source unchanged', array(M::downgrade_feedback($pkg, '', $up, array()), count($skin->said), strpos($skin->said[0], '1 payment form uses a new payment field') !== false, strpos($skin->said[0], 'page=Emsfb_payments&amp;tab=downgrade') !== false || strpos($skin->said[0], 'page=Emsfb_payments&tab=downgrade') !== false), array($pkg, 1, true, true));
file_put_contents($pkg . '/emsfb.php', "<?php\n/**\n * Plugin Name: Easy Form Builder\n * Version: 4.3.3\n */\n");
M::downgrade_feedback($pkg, '', $up, array());
file_put_contents($pkg . '/emsfb.php', "<?php\n/**\n * Plugin Name: Other plugin\n * Version: 1.0\n */\n");
M::downgrade_feedback($pkg, '', $up, array());
$t('J ...a newer Easy Form Builder or another plugin: nothing said', count($skin->said), 1);
$html = M::downgrade_compare('<table></table>', array('Name' => 'Easy Form Builder', 'Version' => '4.3.2'), array('Name' => 'Easy Form Builder', 'Version' => '4.3.1'));
$t('J Plugins › Upload "Replace current with uploaded": the warning and "Move the forms back first" under the comparison', array(strpos($html, '<table></table>') === 0, strpos($html, 'efb-pay-downgrade-warn') !== false, strpos($html, 'Move the forms back first') !== false), array(true, true, true));
$t('J ...not for an upgrade', M::downgrade_compare('<table></table>', array('Name' => 'Easy Form Builder'), array('Version' => '4.3.3')), '<table></table>');
M::rollback_all();
$t('J ...nor when no form is moved', array(M::downgrade_compare('<t/>', array('Name' => 'Easy Form Builder'), array('Version' => '4.3.1')), M::downgrade_feedback($pkg, '', $up, array()), count($skin->said)), array('<t/>', $pkg, 1));
@unlink($pkg . '/emsfb.php'); @rmdir($pkg);
$readme = (string) file_get_contents(__DIR__ . '/../readme.txt');
$t('J readme.txt tells how to go back to 4.3.1 (move the payment forms back first, where)', array((bool) preg_match('/= How do I go back to (version )?4\.3\.1\b.*?=/i', $readme), (bool) preg_match('/Payments > Moved forms.{0,40}Move every form back/', $readme)), array(true, true));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
