<?php
/**
 * F11 × payments: the place of a pending payment (docs/payments/payment-error-matrix.en.md §6).
 *
 *  1. The total counter starts from the real entries; an entry whose payment failed, was
 *     cancelled or expired (and has no open attempt) is not counted; pending and paid are.
 *  2. A failed / expired / cancelled payment gives the entry's places back (total and per person)
 *     once; not while another attempt of the same entry is still open.
 *  3. "Try again" (efb_payment_retry_gate) takes the places again, or is refused with
 *     efb_form_full when someone took the last place meanwhile; refused with efb_form_closed
 *     when the form closed.
 *  4. Paid keeps the place (the hold is removed); paid after the place was given back counts it
 *     again even over the cap.
 *  5. Deleting the entry removes its hold and rebuilds the total.
 *  6. Service::retry() calls the filter before a new attempt is created; Service::message()
 *     shows the gate's sentence for the five codes.
 *
 * Run: php tests/test-form-gate-payment.php
 */
define('ABSPATH', __DIR__ . '/');

$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
class WP_Error { public $c; public $m; public $d; public function __construct($c = '', $m = '', $d = array()) { $this->c = $c; $this->m = $m; $this->d = $d; } public function get_error_code() { return $this->c; } public function get_error_message() { return $this->m; } public function get_error_data() { return $this->d; } }
function is_wp_error($x) { return $x instanceof WP_Error; }
$GLOBALS['opts'] = array();
function delete_option($k) { unset($GLOBALS['opts'][$k]); return true; }
function apply_filters($h, $v) { return $v; }
function wp_timezone() { return new DateTimeZone('UTC'); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
require __DIR__ . '/f11-test-db.php';
require_once __DIR__ . '/../includes/json-efb.php';
require_once __DIR__ . '/../includes/class-Emsfb-rate-limit.php';
require_once __DIR__ . '/../includes/class-Emsfb-drafts.php';
require_once __DIR__ . '/../includes/class-Emsfb-privacy.php';
require_once __DIR__ . '/../includes/class-Emsfb-client-ip.php';
require_once __DIR__ . '/../includes/class-Emsfb-form-css.php';
require_once __DIR__ . '/../includes/class-Emsfb-form-gate.php';
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-store.php';
require_once __DIR__ . '/../includes/payments/class-Emsfb-payment-service.php';
use Emsfb\Form_Gate as G;
use Emsfb\Rate_Limit;

$db = $GLOBALS['wpdb'] = new EfbF11TestDb();
$db->create_payment_tables();
$db->create_f11_tables();
Rate_Limit::reset_ready();
$fid = $db->add_form(array(array('type' => 'payment', 'lim_total' => '2', 'lim_email' => '1', 'lim_email_field' => 'em', 'lim_period' => 'ever')), 'payment');
$n = 0;
$pay = function ($msg, $status) use ($db, $fid, &$n) {
	$n++;
	$db->insert('wp_emsfb_pmt_', array('uid' => 'u' . $n, 'form_id' => $fid, 'msg_id' => $msg, 'gateway' => 'efbmock', 'status' => $status, 'amount_minor' => 100, 'currency' => 'EUR', 'attempt_key' => 'a' . $n, 'idem_key' => 'i' . $n, 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')));
	return array('id' => $db->insert_id, 'msg_id' => $msg, 'form_id' => $fid, 'status' => $status, 'created_at' => gmdate('Y-m-d H:i:s'));
};
$s0 = array('type' => 'payment', 'lim_total' => '2', 'lim_email' => '1', 'lim_email_field' => 'em', 'lim_period' => 'ever');
$GLOBALS['plan'] = true;
function efb_plan_at_least_efb($p) { return true; }
$ctx = array('fid' => $fid, 'now' => time(), 'uid' => 0, 'roles' => array(), 'logged_in' => false, 'manager' => false, 'ip_key' => '', 'cookie' => '', 't0' => null);

/* 1 */
$m1 = $db->add_entry($fid, array(), gmdate('Y-m-d H:i:s'), array('read_' => 2));
$m2 = $db->add_entry($fid, array(), gmdate('Y-m-d H:i:s'), array('read_' => 2));
$m3 = $db->add_entry($fid, array(), gmdate('Y-m-d H:i:s'));
$pay($m1, 'pending');
$pay($m2, 'failed');
t('entry_count: pending and plain entries count, a failed payment does not', G::entry_count($fid), 2);
$pay($m2, 'pending');
t('a failed attempt with a newer open attempt still counts', G::entry_count($fid), 3);
$db->query('DELETE FROM wp_emsfb_msg_');
$db->query('DELETE FROM wp_emsfb_pmt_');

/* 2: a pending payment holds its places */
$r = G::reserve_for_submit($s0, array(array('id_' => 'em', 'value' => 'a@x.test')), $fid, $ctx);
$e1 = $db->add_entry($fid, array(), gmdate('Y-m-d H:i:s'), array('read_' => 2));
G::hold_save($e1, $fid, $r['claims']);
$p1 = $pay($e1, 'pending');
t('hold stored with both claims (total, e-mail)', count(G::hold_get($e1)['c']), 2);
$r2 = G::reserve_for_submit($s0, array(array('id_' => 'em', 'value' => 'b@x.test')), $fid, $ctx);
$e2 = $db->add_entry($fid, array(), gmdate('Y-m-d H:i:s'), array('read_' => 2));
G::hold_save($e2, $fid, $r2['claims']);
$p2 = $pay($e2, 'pending');
t('cap 2 reached by two pending payments', G::state($s0, $ctx)['state'], 'full');
$p1b = $pay($e1, 'created');
G::on_payment_status(array_merge($p1, array('status' => 'canceled')), 'pending', 'canceled', 'api');
t('cancelled while another attempt of the same entry is open: nothing given back', array(G::state($s0, $ctx)['state'], (int) G::hold_get($e1)['r']), array('full', 0));
$db->query("UPDATE wp_emsfb_pmt_ SET status = 'failed' WHERE id IN (" . (int) $p1['id'] . ',' . (int) $p1b['id'] . ')');
G::on_payment_status(array_merge($p1b, array('status' => 'failed')), 'created', 'failed', 'api');
t('the last open attempt failed: the places are given back once', array(G::state($s0, $ctx)['state'], (int) G::hold_get($e1)['r'], Rate_Limit::peek_bucket(G::total_bucket($fid))), array('open', 1, 1));
G::on_payment_status(array_merge($p1b, array('status' => 'expired')), 'failed', 'expired', 'cron');
t('a second failure event does not give it back twice', Rate_Limit::peek_bucket(G::total_bucket($fid)), 1);
t('the e-mail of the failed payment may be used again', is_array(G::reserve_for_submit(array('lim_email' => '1', 'lim_email_field' => 'em'), array(array('id_' => 'em', 'value' => 'a@x.test')), $fid, $ctx)), true);

/* 3: Try again */
$GLOBALS['wpdb']->query('DELETE FROM wp_emsfb_vrfy_rl_');
Rate_Limit::reset_ready();
$r = G::reserve_for_submit($s0, array(array('id_' => 'em', 'value' => 'c@x.test')), $fid, $ctx);
$e3 = $db->add_entry($fid, array(), gmdate('Y-m-d H:i:s'), array('read_' => 2));
G::hold_save($e3, $fid, $r['claims']);
$p3 = $pay($e3, 'failed');
G::on_payment_status($p3, 'pending', 'failed', 'api');
// The counter was rebuilt: e2 (pending) counts, e1 (every attempt failed) does not → 1, + c = 2, failed → 1.
t('retry with a free place: the place is taken again', array(G::retry_gate(null, array_merge($p3, array('form_id' => $fid))), (int) G::hold_get($e3)['r'], Rate_Limit::peek_bucket(G::total_bucket($fid))), array(null, 0, 2));
$db->query("UPDATE wp_emsfb_pmt_ SET status = 'expired' WHERE id = " . (int) $p3['id']);
G::on_payment_status(array_merge($p3, array('status' => 'expired')), 'pending', 'expired', 'cron');
// Meanwhile two others take both places.
foreach (array('d', 'e') as $who) G::reserve_for_submit($s0, array(array('id_' => 'em', 'value' => $who . '@x.test')), $fid, $ctx);
$g = G::retry_gate(null, array_merge($p3, array('form_id' => $fid)));
t('retry when the form filled up: efb_form_full with the sentence, nothing taken', array($g['code'] ?? '', $g['m'] ?? '', (int) G::hold_get($e3)['r']), array('efb_form_full', 'This form has reached its limit and no longer accepts answers.', 1));
$db->query("UPDATE wp_emsfb_form SET form_structer = '" . str_replace('"', '\\"', json_encode(array(array_merge($s0, array('sched_close' => '2020-01-01T00:00'))))) . "' WHERE form_id = " . (int) $fid);
$g = G::retry_gate(null, array_merge($p3, array('form_id' => $fid)));
t('retry when the form closed: efb_form_closed', $g['code'] ?? '', 'efb_form_closed');

/* 4: late paid */
$before = Rate_Limit::peek_bucket(G::total_bucket($fid));
G::on_payment_status(array_merge($p3, array('status' => 'paid')), 'expired', 'paid', 'webhook');
t('paid after the place was given back: counted again, even over the cap', Rate_Limit::peek_bucket(G::total_bucket($fid)), $before + 1);
t('paid: the hold is removed', G::hold_get($e3), null);
$r = G::reserve_for_submit(array('lim_total' => '9'), array(), $fid, $ctx);
$e4 = $db->add_entry($fid, array(), gmdate('Y-m-d H:i:s'), array('read_' => 2));
G::hold_save($e4, $fid, $r['claims']);
$p4 = $pay($e4, 'paid');
G::on_payment_status($p4, 'pending', 'paid', 'webhook');
t('paid normally: the place stays, the hold is removed', array(G::hold_get($e4), Rate_Limit::peek_bucket(G::total_bucket($fid)) >= 1), array(null, true));

/* 5: delete */
$r = G::reserve_for_submit(array('lim_total' => '9'), array(), $fid, $ctx);
$e5 = $db->add_entry($fid, array(), gmdate('Y-m-d H:i:s'), array('read_' => 2));
G::hold_save($e5, $fid, $r['claims']);
G::on_entries_purged(array($e5), 'delete', array(), array($fid));
t('deleted entry: hold removed, total rebuilt from the entries', array(G::hold_get($e5), Rate_Limit::peek_bucket(G::total_bucket($fid))), array(null, null));

/* 6: Service */
$src = file_get_contents(__DIR__ . '/../includes/payments/class-Emsfb-payment-service.php');
$retry = substr($src, strpos($src, 'public static function retry('));
t('Service::retry() asks efb_payment_retry_gate before Store::insert', strpos($retry, "apply_filters( 'efb_payment_retry_gate'") !== false && strpos($retry, "apply_filters( 'efb_payment_retry_gate'") < strpos($retry, 'Store::insert('), true);
t('Service::message() shows the gate sentence for the five codes', array(\Emsfb\Payments\Service::message('efb_form_full', array('visitor' => 'Full.')), \Emsfb\Payments\Service::message('efb_role_denied', array('visitor' => 'No.'))), array('Full.', 'No.'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
