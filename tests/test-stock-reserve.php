<?php
/**
 * F05-D: the per-choice capacity (\Emsfb\Stock) on the SQLite stand-in of $wpdb (MySQL / MariaDB run the same
 * single conditional UPDATE under a row lock; SQLite runs every write under one lock).
 *
 *  1. claims(): one per chosen choice with a capacity (radio / checkbox by id, select by its text, multiselect
 *     each part), none for "Other" or a choice without inv_cap, sorted keys; inv_on needs the plan; an older
 *     booking form: mlen is the capacity and registered_count where the count starts.
 *  2. reserve(): all or nothing (a full second choice gives the first back), release() never below 0, force()
 *     over the cap, left(); the stored form is never read for writing.
 *  3. Oversell: 20 processes (pcntl_fork) reserve a choice with 5 places at the same moment, 3 rounds: exactly 5.
 *     And 20 processes taking two choices (one with 5 places, one with 3): exactly 3, neither left over-counted.
 *  4. Payments: a pending entry's hold; a failed / cancelled / expired payment gives the places back once (not
 *     while another attempt lives); "Try again" takes them again or is refused (efb_choice_full) when taken
 *     meanwhile; a payment paid after it was given back counts again; deleting an entry gives its places back
 *     only with inv_release, and never twice after a failed payment.
 *  5. A missing table fails closed (error unavailable, nothing taken); the form's rows are deleted with it.
 *  6. migrate_booking_counts(): the JSON counts of booking forms become rows, a second run changes nothing.
 *
 * Run: php tests/test-stock-reserve.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/f11-test-db.php';
$GLOBALS['plan_ok'] = true;
function efb_plan_at_least_efb($p) { return $GLOBALS['plan_ok']; }
function current_time($t, $gmt = 0) { return gmdate('Y-m-d H:i:s'); }
require_once __DIR__ . '/../includes/class-Emsfb-choices.php';
require_once __DIR__ . '/../includes/class-Emsfb-stock.php';
use Emsfb\Stock;

function stock_db($file = null) {
	$db = new EfbF11TestDb($file);
	$db->exec_mysql_ddl(Stock::ddl($db->prefix, ''));
	return $db;
}
$db = $GLOBALS['wpdb'] = stock_db();
Stock::reset_ready();
t('table ready (from the real DDL)', Stock::ready(), true);

/* 1. claims */
$form = array(array('type' => 'form'),
	array('id_' => 'seat', 'type' => 'radio', 'name' => 'Seat', 'inv_on' => '1'),
	array('id_' => 'a', 'type' => 'option', 'parent' => 'seat', 'value' => 'Front', 'inv_cap' => '2'),
	array('id_' => 'b', 'type' => 'option', 'parent' => 'seat', 'value' => 'Back'),
	array('id_' => 'tags', 'type' => 'checkbox', 'name' => 'Tags', 'inv_on' => '1', 'op_other' => '1'),
	array('id_' => 't1', 'type' => 'option', 'parent' => 'tags', 'value' => 'T1', 'inv_cap' => '5'),
	array('id_' => 't2', 'type' => 'option', 'parent' => 'tags', 'value' => 'T2', 'inv_cap' => '3'),
	array('id_' => 'sel', 'type' => 'select', 'name' => 'Size', 'inv_on' => '1'),
	array('id_' => 's1', 'type' => 'option', 'parent' => 'sel', 'value' => 'S &amp; M', 'inv_cap' => '1'),
	array('id_' => 'ms', 'type' => 'multiselect', 'name' => 'Multi', 'inv_on' => '1'),
	array('id_' => 'm1', 'type' => 'option', 'parent' => 'ms', 'value' => 'One', 'inv_cap' => '4'),
	array('id_' => 'm2', 'type' => 'option', 'parent' => 'ms', 'value' => 'Two', 'inv_cap' => '4'),
	array('id_' => 'off', 'type' => 'radio', 'name' => 'Off'),
	array('id_' => 'o1', 'type' => 'option', 'parent' => 'off', 'value' => 'X', 'inv_cap' => '1'),
);
$items = array(
	array('id_' => 'seat', 'id_ob' => 'a', 'value' => 'Front'), array('id_' => 'seat', 'id_ob' => 'b', 'value' => 'Back'),
	array('id_' => 'tags', 'id_ob' => 't2', 'value' => 'T2'), array('id_' => 'tags', 'id_ob' => 'tags_oth', 'value' => 'T1', 'other' => '1'),
	array('id_' => 'sel', 'id_ob' => 'sel_options', 'value' => 'S & M'), array('id_' => 'ms', 'value' => 'One@efb!Two@efb!'),
	array('id_' => 'off', 'id_ob' => 'o1', 'value' => 'X'),
);
$c = Stock::claims($form, $items);
t('claims: radio by id, checkbox by id, select by text, multiselect each part; none for Other, no cap, inv_on off; sorted', array_map(function ($x) { return $x['f'] . '/' . $x['o'] . '/' . $x['cap']; }, $c), array('ms/m1/4', 'ms/m2/4', 'seat/a/2', 'sel/s1/1', 'tags/t2/3'));
$GLOBALS['plan_ok'] = false;
t('claims: inv_on without the plan counts nothing', Stock::claims($form, $items), array());
$GLOBALS['plan_ok'] = true;
$booking = array(array('type' => 'form', 'booking' => 1),
	array('id_' => 'slot', 'type' => 'radio', 'name' => 'Slot'),
	array('id_' => 'm', 'type' => 'option', 'parent' => 'slot', 'value' => 'Morning', 'mlen' => '4', 'registered_count' => '3'),
	array('id_' => 'n', 'type' => 'option', 'parent' => 'slot', 'value' => 'Noon', 'mlen' => ''),
);
$GLOBALS['plan_ok'] = false;
$bc = Stock::claims($booking, array(array('id_' => 'slot', 'id_ob' => 'm', 'value' => 'Morning'), array('id_' => 'slot', 'id_ob' => 'n', 'value' => 'Noon')));
t('an older booking form (any plan): mlen is the capacity, registered_count the start; mlen "" no limit', array(count($bc), $bc[0]['cap'], $bc[0]['seed']), array(1, 4, 3));
t('left() of the booking form before any submit: 4 - 3 = 1', Stock::left(7, $booking), array('m' => 1));
$r1 = Stock::reserve(7, $bc);
$r2 = Stock::reserve(7, $bc);
t('registered_count 3 and mlen 4: one more entry, then full', array($r1['ok'], $r2['ok'], $r2['claim']['label']), array(true, false, 'Morning'));
$GLOBALS['plan_ok'] = true;

/* 2. reserve / release / force / left */
$two = array(array('f' => 'seat', 'o' => 'a', 'cap' => 1, 'seed' => 0, 'label' => 'Front'), array('f' => 'tags', 'o' => 't2', 'cap' => 1, 'seed' => 0, 'label' => 'T2'));
Stock::reserve(5, array($two[1]));
$r = Stock::reserve(5, $two);
$used = function ($fid, $o) use ($db) { return (int) $db->get_var("SELECT used FROM wp_emsfb_stock_ WHERE form_id = $fid AND option_id = '$o'"); };
t('all or nothing: the second choice is full, the first is given back', array($r['ok'], $r['claim']['o'], $used(5, 'a'), $used(5, 't2')), array(false, 't2', 0, 1));
Stock::release(5, array($two[1]));
Stock::release(5, array($two[1]));
t('release never goes below 0', $used(5, 't2'), 0);
Stock::force(5, array($two[0]));
Stock::force(5, array($two[0]));
t('force counts over the capacity', $used(5, 'a'), 2);
t('left() never below 0', Stock::left(5, array(array('type' => 'form'), $form[1], array('id_' => 'a', 'type' => 'option', 'parent' => 'seat', 'value' => 'Front', 'inv_cap' => '1')))['a'], 0);

/* 3. oversell */
if (!function_exists('pcntl_fork')) {
	echo "[SKIP] concurrency: pcntl is not available\n";
} else {
	foreach (array(1, 2, 3) as $round) {
		$file = sys_get_temp_dir() . '/efb-stock-' . getmypid() . '-' . $round . '.sqlite';
		@unlink($file);
		stock_db($file);
		$go = microtime(true) + 0.4;
		$kids = array();
		for ($i = 0; $i < 20; $i++) {
			$pid = pcntl_fork();
			if ($pid === 0) {
				$GLOBALS['wpdb'] = new EfbF11TestDb($file);
				Stock::reset_ready();
				while (microtime(true) < $go) usleep(200);
				$r = $round < 3
					? Stock::reserve(42, array(array('f' => 'seat', 'o' => 'last', 'cap' => 5, 'seed' => 0, 'label' => 'L')))
					: Stock::reserve(42, array(array('f' => 'p', 'o' => 'x', 'cap' => 5, 'seed' => 0, 'label' => 'X'), array('f' => 'p', 'o' => 'y', 'cap' => 3, 'seed' => 0, 'label' => 'Y')));
				exit($r['ok'] ? 10 : 20);
			}
			$kids[] = $pid;
		}
		$yes = 0; $no = 0; $other = 0;
		foreach ($kids as $pid) { pcntl_waitpid($pid, $st); $code = pcntl_wexitstatus($st); if ($code === 10) $yes++; elseif ($code === 20) $no++; else $other++; }
		$check = new EfbF11TestDb($file);
		if ($round < 3) {
			t("oversell round {$round}: 20 processes, 5 places → exactly 5 taken", array($yes, $no, $other, (int) $check->get_var("SELECT used FROM wp_emsfb_stock_ WHERE option_id = 'last'")), array(5, 15, 0, 5));
		} else {
			t('oversell, two choices (5 and 3 places) taken together: exactly 3, x given back by the refused ones', array($yes, (int) $check->get_var("SELECT used FROM wp_emsfb_stock_ WHERE option_id = 'x'"), (int) $check->get_var("SELECT used FROM wp_emsfb_stock_ WHERE option_id = 'y'")), array(3, 3, 3));
		}
		$check = null;
		@unlink($file);
	}
	$GLOBALS['wpdb'] = $db;
	Stock::reset_ready();
}

/* 4. payments */
class FakeGate { }
$claims = array(array('f' => 'seat', 'o' => 'a', 'cap' => 1, 'seed' => 0, 'label' => 'Front'));
$db->query('DELETE FROM wp_emsfb_stock_');
$r = Stock::reserve(9, $claims);
Stock::hold_save(101, 9, $r['taken']);
Stock::on_payment_status(array('msg_id' => 101, 'id' => 1, 'form_id' => 9), 'pending', 'failed', 'webhook');
t('a failed payment gives the place back', $used(9, 'a'), 0);
Stock::on_payment_status(array('msg_id' => 101, 'id' => 1, 'form_id' => 9), 'failed', 'expired', 'cron');
t('a second failure gives nothing twice', $used(9, 'a'), 0);
t('another visitor takes the place', Stock::reserve(9, $claims)['ok'], true);
$rg = Stock::retry_gate(null, array('msg_id' => 101, 'form_id' => 9));
t('"Try again" is refused: the choice is full now (efb_choice_full, the choice named)', array($rg['code'], strpos($rg['m'], 'Front') !== false), array('efb_choice_full', true));
Stock::release(9, $claims);
t('"Try again" when a place is free: taken again (null = go on)', array(Stock::retry_gate(null, array('msg_id' => 101, 'form_id' => 9)), $used(9, 'a')), array(null, 1));
Stock::on_payment_status(array('msg_id' => 101, 'id' => 2, 'form_id' => 9), 'pending', 'paid', 'webhook');
t('paid: the hold is closed, the place stays taken', array(Stock::hold_get(101), $used(9, 'a')), array(null, 1));
$r = Stock::reserve(9, array(array('f' => 'tags', 'o' => 't9', 'cap' => 1, 'seed' => 0, 'label' => 'T9')));
Stock::hold_save(102, 9, $r['taken']);
Stock::on_payment_status(array('msg_id' => 102, 'id' => 3, 'form_id' => 9), 'pending', 'canceled', 'return');
Stock::reserve(9, array(array('f' => 'tags', 'o' => 't9', 'cap' => 1, 'seed' => 0, 'label' => 'T9')));
Stock::on_payment_status(array('msg_id' => 102, 'id' => 3, 'form_id' => 9), 'canceled', 'paid', 'webhook');
t('paid after the place was given back and taken by another: counted again (over the capacity, logged)', $used(9, 't9'), 2);

/* 4b. deleting an entry */
$GLOBALS['opts']['form_rows_9'] = null;
eval('namespace Emsfb\Payments; class Service { static function form_rows($fid) { return $GLOBALS["form_" . $fid]; } }');
$GLOBALS['form_9'] = array(array('type' => 'form'),
	array('id_' => 'seat', 'type' => 'radio', 'inv_on' => '1', 'inv_release' => '1'),
	array('id_' => 'a', 'type' => 'option', 'parent' => 'seat', 'value' => 'Front', 'inv_cap' => '9'),
	array('id_' => 'tags', 'type' => 'checkbox', 'inv_on' => '1'),
	array('id_' => 't9', 'type' => 'option', 'parent' => 'tags', 'value' => 'T9', 'inv_cap' => '9'));
$before = array($used(9, 'a'), $used(9, 't9'));
Stock::on_message_deleted(200, 9, 'TRK', array(array('id_' => 'seat', 'id_ob' => 'a', 'value' => 'Front'), array('id_' => 'tags', 'id_ob' => 't9', 'value' => 'T9')));
t('a deleted entry gives its places back only where the field says so (inv_release)', array($used(9, 'a'), $used(9, 't9')), array($before[0] - 1, $before[1]));
$r = Stock::reserve(9, array(array('f' => 'seat', 'o' => 'a', 'cap' => 9, 'seed' => 0, 'label' => 'Front')));
Stock::hold_save(300, 9, $r['taken']);
Stock::on_payment_status(array('msg_id' => 300, 'id' => 4, 'form_id' => 9), 'pending', 'failed', 'webhook');
$mid = $used(9, 'a');
Stock::on_message_deleted(300, 9, 'TRK', array(array('id_' => 'seat', 'id_ob' => 'a', 'value' => 'Front')));
t('a deleted entry whose failed payment gave the place back: nothing given twice; the hold is gone', array($used(9, 'a'), Stock::hold_get(300)), array($mid, null));

/* 5. form deleted, missing table */
Stock::forget_form(9);
t('deleting the form removes its counts', (int) $db->get_var('SELECT COUNT(*) FROM wp_emsfb_stock_ WHERE form_id = 9'), 0);
$db->pdo->exec('DROP TABLE wp_emsfb_stock_');
Stock::reset_ready();
$r = Stock::reserve(9, $claims);
t('a missing table fails closed (unavailable, nothing taken)', array($r['ok'], $r['error']), array(false, 'unavailable'));
t('no claims: nothing to take, ok even without the table', Stock::reserve(9, array())['ok'], true);

/* 6. migration of booking counts */
$db->exec_mysql_ddl(Stock::ddl($db->prefix, ''));
Stock::reset_ready();
$db->pdo->exec('CREATE TABLE IF NOT EXISTS wp_emsfb_form (form_id INTEGER PRIMARY KEY, form_structer TEXT)');
$json = str_replace('"', '\\"', json_encode($booking));
$db->pdo->exec("INSERT INTO wp_emsfb_form (form_id, form_structer) VALUES (31, " . $db->pdo->quote($json) . ")");
$db->pdo->exec("INSERT INTO wp_emsfb_form (form_id, form_structer) VALUES (32, " . $db->pdo->quote('[{\\"type\\":\\"form\\"}]') . ")");
function emsfb_json_normalize_efb($s) { return str_replace('\\"', '"', (string) $s); }
function emsfb_json_decode_efb($s, $a = true) { return json_decode($s, $a); }
t('migration: the registered_count of a booking form becomes its row', array(Stock::migrate_booking_counts(), $used(31, 'm')), array(1, 3));
Stock::reserve(31, array(array('f' => 'slot', 'o' => 'm', 'cap' => 4, 'seed' => 3, 'label' => 'Morning')));
t('a second run changes nothing (INSERT IGNORE), the count moved by a submit is kept', array(Stock::migrate_booking_counts(), $used(31, 'm')), array(0, 4));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
