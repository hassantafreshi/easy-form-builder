<?php
/**
 * PAY00 sub-task B: the payment tables and the state machine (Store).
 *
 * The real Store::ddl() runs on an in-memory SQLite database (tests/pay-test-db.php):
 *  - the four tables and their unique keys exist;
 *  - every allowed transition of §6.2 succeeds once, a repeat is a no-op, every other one is refused;
 *  - paid never goes back to pending/failed; failed/canceled/expired may still become paid;
 *  - claim_finalize() is true exactly once, and only for a paid payment;
 *  - uq_attempt: the same (form, attempt_key) cannot be inserted twice;
 *  - claim_event(): the same (gateway, event) is stored once, another gateway may reuse the id;
 *  - due_for_reconcile() back-off; anonymize_for_messages() (F13);
 *  - Install: DB version 1.5, the step sits before the early return and creates only missing tables.
 *
 * Run: php tests/test-pay-store.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0;
$failed = 0;
require __DIR__ . '/pay-test-db.php';
efb_pay_test_load();
use Emsfb\Payments\Store;
$t = 'efb_pay_t';

$db = $GLOBALS['wpdb'] = new EfbPayTestDb();
$db->create_payment_tables();
foreach (array('emsfb_pmt_', 'emsfb_pmt_evt_', 'emsfb_pmt_rfd_', 'emsfb_coupon_') as $s) {
	$t("table wp_{$s} exists", $db->count('wp_' . $s), 0);
}
$t('Store::ready()', Store::ready(), true);

$mk = function ($attempt, $extra = array()) {
	return Store::insert(array_merge(array('form_id' => 7, 'field_id' => 'pf1', 'gateway' => 'efbmock', 'mode' => 'test', 'amount_minor' => 1250, 'currency' => 'eur', 'attempt_key' => $attempt), $extra));
};
$p = $mk('11111111-1111-4111-8111-111111111111');
$t('insert: status created', $p['status'], 'created');
$t('insert: uid is 22 base62 characters', (bool) preg_match('/^[0-9A-Za-z]{22}$/', $p['uid']), true);
$t('insert: currency upper case', $p['currency'], 'EUR');
$t('insert: amount stored in minor units', $p['amount_minor'], 1250);
$t('insert: idem_key sha256', strlen($p['idem_key']), 64);
$dup = $mk('11111111-1111-4111-8111-111111111111');
$t('uq_attempt: same form + attempt key refused', $dup, null);
$t('...the first row is found by attempt', Store::find_by_attempt(7, '11111111-1111-4111-8111-111111111111')['id'], $p['id']);
$other = $mk('11111111-1111-4111-8111-111111111111', array('form_id' => 8));
$t('uq_attempt: same key on another form is a new payment', is_array($other) && $other['id'] !== $p['id'], true);
$t('a status passed to insert() is ignored (always created)', $mk('22222222-1111-4111-8111-111111111111', array('status' => 'paid'))['status'], 'created');

// Transitions: the whole table.
$all = Store::STATUSES;
$allowed = 0; $refused = 0; $bad = array();
foreach ($all as $from) {
	foreach ($all as $to) {
		$q = $mk('t-' . $from . '-' . $to);
		$db->query("UPDATE wp_emsfb_pmt_ SET status = '{$from}' WHERE id = " . (int) $q['id']);
		$ok = Store::transition($q['id'], $to);
		$expect = Store::can_move($from, $to);
		if ($ok !== $expect) $bad[] = "{$from}->{$to}";
		if ($expect) $allowed++; else $refused++;
		$now = Store::get($q['id'])['status'];
		if ($now !== ($expect ? $to : $from)) $bad[] = "{$from}->{$to} left {$now}";
	}
}
$t('transition table: every pair behaves as §6.2 says (' . $allowed . ' allowed, ' . $refused . ' refused)', $bad, array());
$t('paid can never go back to pending or failed', array(Store::can_move('paid', 'pending'), Store::can_move('paid', 'failed'), Store::can_move('paid', 'created')), array(false, false, false));
$t('failed / canceled / expired may still become paid (late bank payment)', array(Store::can_move('failed', 'paid'), Store::can_move('canceled', 'paid'), Store::can_move('expired', 'paid')), array(true, true, true));
$t('refunded is final', Store::TRANSITIONS['refunded'], array());

// A repeat is a no-op.
$q = $mk('repeat-1');
$t('created -> pending', Store::transition($q['id'], 'pending', array('gateway_ref' => 'mck_1')), true);
$t('...the extra column is written with it', Store::get($q['id'])['gateway_ref'], 'mck_1');
$t('pending -> paid', Store::transition($q['id'], 'paid', array('paid_at' => Store::now())), true);
$t('pending -> paid again: no-op (already done)', Store::transition($q['id'], 'paid'), false);
$t('a column outside the allow-list is ignored', Store::transition($q['id'], 'refunded', array('amount_minor' => 1)) && Store::get($q['id'])['amount_minor'] === 1250, true);

// Finalize lock.
$f = $mk('fin-1');
Store::transition($f['id'], 'pending');
$t('claim_finalize on a pending payment: refused', Store::claim_finalize($f['id']), false);
Store::transition($f['id'], 'paid');
$wins = 0;
for ($i = 0; $i < 10; $i++) $wins += Store::claim_finalize($f['id']) ? 1 : 0;
$t('claim_finalize ten times on a paid payment: exactly one winner', $wins, 1);
$t('finalized_at is set', Store::get($f['id'])['finalized_at'] !== null, true);

// Events.
$t('claim_event: first delivery stored', Store::claim_event('efbmock', 'evt_1', array('type' => 'payment.updated', 'payload' => array('authorization' => 'Bearer sk_test_abcdefgh', 'card' => '4242424242424242', 'id' => 'evt_1'))), true);
$t('claim_event: duplicate ignored', Store::claim_event('efbmock', 'evt_1'), false);
$t('claim_event: same id from another gateway is its own event', Store::claim_event('othergw', 'evt_1'), true);
$row = $db->row('wp_emsfb_pmt_evt_', "gateway = 'efbmock'");
$t('event payload is redacted (no token, card cut to last four)', strpos($row['payload'], 'sk_test') === false && strpos($row['payload'], '4242424242424242') === false && strpos($row['payload'], '4242') !== false, true);
Store::log_event($f['id'], 'efbmock', 'admin', array('note' => 'a'));
Store::log_event($f['id'], 'efbmock', 'admin', array('note' => 'b'));
$t('log_event never collides', count(Store::events($f['id'])), 2);
Store::attach_event('efbmock', 'evt_1', $f['id']);
$t('attach_event links an early event to its payment', count(Store::events($f['id'])), 3);

// Reconcile selection and back-off.
Store::$now = time();
$r1 = $mk('rec-1');
Store::transition($r1['id'], 'pending');
$db->query("UPDATE wp_emsfb_pmt_ SET created_at = '" . gmdate('Y-m-d H:i:s', time() - 3600) . "' WHERE id = " . (int) $r1['id']);
$r2 = $mk('rec-2');
Store::transition($r2['id'], 'pending');
$db->query("UPDATE wp_emsfb_pmt_ SET created_at = '" . gmdate('Y-m-d H:i:s', time() - 60) . "' WHERE id = " . (int) $r2['id']);
$r3 = $mk('rec-3');
Store::transition($r3['id'], 'pending');
$db->query("UPDATE wp_emsfb_pmt_ SET created_at = '" . gmdate('Y-m-d H:i:s', time() - 11 * 86400) . "' WHERE id = " . (int) $r3['id']);
$ids = array_map(function ($r) { return $r['id']; }, Store::due_for_reconcile(25));
$t('due: an hour-old pending payment', in_array($r1['id'], $ids, true), true);
$t('not due: younger than 5 minutes / older than 10 days', array(in_array($r2['id'], $ids, true), in_array($r3['id'], $ids, true)), array(false, false));
Store::touch_checked($r1['id']);
$t('after a check it waits 5 minutes', in_array($r1['id'], array_map(function ($r) { return $r['id']; }, Store::due_for_reconcile(25)), true), false);
Store::$now = time() + 6 * 60;
$t('...and is due again after them', in_array($r1['id'], array_map(function ($r) { return $r['id']; }, Store::due_for_reconcile(25)), true), true);
Store::$now = null;
$t('back-off steps: 5 min, 15 min, 1 h, then 6 h', array(Store::backoff(1), Store::backoff(2), Store::backoff(3), Store::backoff(9)), array(300, 900, 3600, 21600));

// F13: anonymize with the entry.
$a = $mk('anon-1', array('payer_email' => 'payer@example.test', 'payer_name' => 'P', 'ip' => '203.0.113.5'));
Store::update($a['id'], array('msg_id' => 55, 'gateway_ref' => 'mck_anon'));
Store::claim_event('efbmock', 'evt_anon', array('payment_id' => $a['id'], 'payload' => array('email' => 'payer@example.test')));
Store::on_entries_purged(array(55), 'delete');
$after = Store::get($a['id']);
$t('F13: payer e-mail, name and IP emptied', array($after['payer_email'], $after['payer_name'], $after['ip']), array('', '', ''));
$t('F13: amount, currency and reference kept', array($after['amount_minor'], $after['currency'], $after['gateway_ref']), array(1250, 'EUR', 'mck_anon'));
$t('F13: event payloads dropped', $db->get_var("SELECT payload FROM wp_emsfb_pmt_evt_ WHERE payment_id = " . (int) $a['id']), null);

// Search.
$res = Store::search(array('status' => 'paid', 'per_page' => 20));
$t('search by status', count(array_filter($res['rows'], function ($r) { return $r['status'] !== 'paid'; })), 0);
$t('search by reference', Store::search(array('q' => 'mck_anon'))['total'], 1);

// Install.
$main = file_get_contents(__DIR__ . '/../emsfb.php');
$t('EMSFB_DB_VERSION is 1.5', (bool) preg_match('/define\("EMSFB_DB_VERSION",\s*1\.5\)/', $main), true);
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-install.php');
$up = substr($src, strpos($src, 'static function upgrade_schema'));
$t('payments step sits before the early return of upgrade_schema()', strpos($up, 'self::upgrade_payments_schema()') < strpos($up, 'return $applied;'), true);
// Run the real step twice against the SQLite database (dbDelta = create the table here).
function dbDelta($sql) { $GLOBALS['wpdb']->exec_mysql_ddl($sql); $GLOBALS['dbdelta_calls'][] = $sql; return array(); }
require_once __DIR__ . '/../includes/class-Emsfb-install.php';
$db2 = $GLOBALS['wpdb'] = new EfbPayTestDb();
$first = \Emsfb\Install::upgrade_payments_schema();
$t('upgrade step creates the four tables', $first, array('emsfb_pmt_: CREATE TABLE', 'emsfb_pmt_evt_: CREATE TABLE', 'emsfb_pmt_rfd_: CREATE TABLE', 'emsfb_coupon_: CREATE TABLE'));
$GLOBALS['dbdelta_calls'] = array();
$second = \Emsfb\Install::upgrade_payments_schema();
$t('second run creates nothing (idempotent)', array($second, $GLOBALS['dbdelta_calls']), array(array(), array()));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
