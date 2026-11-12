<?php
/**
 * PAY00 §7.6: the reconcile job (emsfb_pmt_reconcile).
 *  - picks open payments created 5 minutes to 10 days ago, checks them, applies the provider's state
 *    (a payment the visitor never came back from is still finalized);
 *  - back-off after a check; at most $limit per run; one run at a time (lock);
 *  - stops calling a gateway after a network error;
 *  - an unpaid payment past pending_ttl() becomes expired;
 *  - reschedules itself while open payments remain.
 *
 * Run: php tests/test-pay-reconcile.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
require __DIR__ . '/pay-test-db.php';
$db = efb_pay_env();
use Emsfb\Payments\Service;
use Emsfb\Payments\Store;
use Emsfb\Payments\Mock_Provider;
$t = 'efb_pay_t';
$age = function ($id, $sec) use ($db) { $db->query("UPDATE wp_emsfb_pmt_ SET created_at = '" . gmdate('Y-m-d H:i:s', time() - $sec) . "' WHERE id = " . (int) $id); };
$mk = function () use ($db) { $r = efb_pay_create($db); return Store::find_by_uid($r['pay']['uid']); };
$a = $mk(); $b = $mk(); $c = $mk(); $young = $mk();
foreach (array($a, $b, $c) as $x) $age($x['id'], 3600);
$age($young['id'], 60);
Mock_Provider::set_status($a['gateway_ref'], 'paid');
Mock_Provider::set_status($b['gateway_ref'], 'canceled');
$runs = array();
add_action('efb_payment_run_actions', function ($p, $when) use (&$runs) { $runs[] = $p['uid']; });
$out = Service::reconcile();
$t('three due payments checked, the 1-minute-old one not', $out['checked'], 3);
$t('paid at the provider (visitor never returned) → paid and finalized', array(Store::get($a['id'])['status'], $runs), array('paid', array($a['uid'])));
$t('canceled at the provider → canceled', Store::get($b['id'])['status'], 'canceled');
$t('still open → pending, checked once', array(Store::get($c['id'])['status'], Store::get($c['id'])['check_count']), array('pending', 1));
$t('a second run right away checks nothing (back-off)', Service::reconcile()['checked'], 0);
$t('the lock: a run while another holds it does nothing', (function () { set_transient('emsfb_pmt_reconcile_lock', 1); $n = Service::reconcile()['checked']; delete_transient('emsfb_pmt_reconcile_lock'); return $n; })(), 0);
Store::$now = time() + 6 * 60;
$d = $mk(); $age($d['id'], 3600);
$GLOBALS['opts']['efb_mock_provider_down'] = 1;
$o2 = Service::reconcile();
$t('provider unreachable: the gateway is stopped after the first error', array($o2['checked'], isset($o2['stopped']['efbmock'])), array(1, true));
unset($GLOBALS['opts']['efb_mock_provider_down']);
Store::$now = null;
$e = $mk(); $age($e['id'], 2 * 86400);
Service::reconcile();
$t('unpaid after pending_ttl (1 day) → expired', Store::get($e['id'])['status'], 'expired');
$t('limit per run', (function () use ($mk, $age) { for ($i = 0; $i < 5; $i++) { $x = $mk(); $age($x['id'], 7200); } return Service::reconcile(2)['checked']; })(), 2);
$GLOBALS['efb_cron'] = array();
Store::$now = time() + 7200;
Service::reconcile(1);
Store::$now = null;
$t('reschedules itself while open payments remain', isset($GLOBALS['efb_cron']['emsfb_pmt_reconcile']), true);
$t('last run time recorded (shown on the Payments screen)', get_option('emsfb_pmt_reconcile_last') > 0, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
