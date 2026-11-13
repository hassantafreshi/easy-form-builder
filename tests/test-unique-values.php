<?php
/**
 * F03-D: unique values (Emsfb\Unique_Values, Field_Rules::check) on the SQLite stand-in of the
 * F11 tests (tests/f11-test-db.php), with the table made from the real DDL.
 *
 *  1. Keys: "Gmail.COM" = "gmail.com", Persian digits, full-width @, invisible marks; a masked
 *     field's canonical value ("۰۹۱۲…" = "0912…" = "+98 912…"); numbers as numbers; text in lower
 *     case, one space, NFC. The hash never contains the value and does not use wp_salt (own secret,
 *     made once, not autoloaded).
 *  2. Concurrency: 20 processes (pcntl_fork) claim the same value on one file database at the same
 *     moment: exactly one succeeds (the UNIQUE KEY), three runs. A claim of several values is all
 *     or nothing.
 *  3. check(): a used value is refused with msg_unique / the default sentence (code unique); a new
 *     one passes with its claim; no plan → no rule; after 10 refusals in a minute from one visitor
 *     the answer is the general sentence without the field.
 *  4. Delete / anonymize / erase: efb_entries_purged forgets the entry's claims (the value is free
 *     again); deleting the form forgets all; claims of dead requests (no entry after an hour) and
 *     "not stored" claims past the retention go in the daily job.
 *  5. Backfill: 500 older answers in batches of 200 with a cursor (3 steps, done), duplicates among
 *     them counted not deleted, a second run adds nothing; until it is done the pre-check finds an
 *     older answer by scanning (the stored JSON shape); the save hook starts it and WP-Cron goes on.
 *  6. Payments: a failed payment gives the value back (hold), another visitor may use it, "Try again"
 *     is then refused with efb_field_unique (before F11 takes places); a payment paid after its value
 *     came back takes it again; a retry with the value still free takes it.
 *  7. WordPress personal data: the exporter lists the fingerprints of an address (not the address),
 *     the eraser removes them.
 *
 * Run: php tests/test-unique-values.php
 */
define('ABSPATH', __DIR__ . '/');
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/f11-test-db.php';
class EfbUniqTestDb extends EfbF11TestDb {
	public function exec_mysql_ddl($sql) {
		return parent::exec_mysql_ddl(preg_replace('/ CHARACTER SET \w+/', '', $sql));
	}
}
foreach (array('class-Emsfb-masks.php', 'class-Emsfb-rate-limit.php', 'class-Emsfb-drafts.php', 'class-Emsfb-privacy.php', 'class-Emsfb-client-ip.php', 'class-Emsfb-field-rules.php', 'class-Emsfb-unique.php') as $file) require_once __DIR__ . '/../includes/' . $file;
use Emsfb\Field_Rules as FR;
use Emsfb\Unique_Values as U;

$GLOBALS['efb_f03_plan'] = 'free';
add_filter('efb_f03_plan', function ($need) { return $GLOBALS['efb_f03_plan']; });
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
function fresh_db($file = null) {
	$db = $GLOBALS['wpdb'] = new EfbUniqTestDb($file);
	$db->create_f11_tables();
	$db->exec_mysql_ddl(U::ddl($db->prefix, ''));
	U::reset_ready();
	\Emsfb\Rate_Limit::reset_ready();
	return $db;
}
$db = fresh_db();
U::register();
$em = array('id_' => 'em', 'type' => 'email', 'name' => 'Email', 'unique' => '1');
$mob = array('id_' => 'mb', 'type' => 'tel', 'name' => 'Mobile', 'unique' => '1', 'mask_preset' => 'ir.mobile');
$num = array('id_' => 'nm', 'type' => 'number', 'name' => 'N', 'unique' => '1');
$txt = array('id_' => 'tx', 'type' => 'text', 'name' => 'Code', 'unique' => '1');

/* 1 keys */
t('1 e-mail: case', U::key($em, 'Ali@Gmail.COM'), U::key($em, 'ali@gmail.com'));
t('1 e-mail: Persian digits, full-width @, invisible marks', U::key($em, "\u{200E}ali۱۲\u{FF20}example.com "), 'ali12@example.com');
t('1 masked mobile: Persian digits = Latin = +98', array(U::key($mob, '۰۹۱۲۱۲۳۴۵۶۷'), U::key($mob, '0912 123 4567'), U::key($mob, '+98 912 123 4567')), array(U::key($mob, '09121234567'), U::key($mob, '09121234567'), U::key($mob, '09121234567')));
t('1 number: 12.50 = 12.5 = ۱۲٫۵', array(U::key($num, '12.50'), U::key($num, '۱۲٫۵')), array('12.5', '12.5'));
t('1 text: lower case, one space, digits, NFC', array(U::key($txt, '  AB  ۱۲ '), U::key($txt, "Cafe\u{0301}")), array('ab 12', "caf\u{00E9}"));
$h = U::vhash(5, 'em', U::key($em, 'ali@gmail.com'));
t('1 the hash is 64 hex characters and does not contain the value', (bool) preg_match('/^[0-9a-f]{64}$/', $h) && strpos($h, 'ali') === false, true);
t('1 own secret, 64 hex, made once, not wp_salt', array(strlen(get_option('emsfb_uniq_secret')), U::secret() === get_option('emsfb_uniq_secret'), strpos(U::secret(), 'unit-test-salt') === false), array(64, true, true));
t('1 another form or field gives another hash', count(array_unique(array($h, U::vhash(6, 'em', 'ali@gmail.com'), U::vhash(5, 'em2', 'ali@gmail.com')))), 3);

/* 2 concurrency */
if (!function_exists('pcntl_fork')) {
	echo "[SKIP] concurrency: pcntl is not available\n";
} else {
	for ($round = 1; $round <= 3; $round++) {
		$file = sys_get_temp_dir() . '/efb-uq-' . getmypid() . '-' . $round . '.sqlite';
		@unlink($file);
		$setup = fresh_db($file);
		$setup = null;
		$pids = array();
		$go = microtime(true) + 0.4;
		for ($k = 0; $k < 20; $k++) {
			$pid = pcntl_fork();
			if ($pid === 0) {
				$GLOBALS['wpdb'] = new EfbUniqTestDb($file);
				U::reset_ready();
				while (microtime(true) < $go) usleep(200);
				$r = U::claim(5, array(array('f' => 'em', 'h' => $h)));
				exit($r['ok'] ? 0 : 3);
			}
			$pids[] = $pid;
		}
		$ok = 0;
		foreach ($pids as $pid) { pcntl_waitpid($pid, $st); if (pcntl_wexitstatus($st) === 0) $ok++; }
		$check = new EfbUniqTestDb($file);
		$rows = (int) $check->get_var("SELECT COUNT(*) FROM wp_emsfb_uniq_");
		t("2 round {$round}: 20 processes, one value: exactly one claim succeeds, one row", array($ok, $rows), array(1, 1));
		@unlink($file);
	}
}
$db = fresh_db();
$r1 = U::claim(5, array(array('f' => 'a', 'h' => str_repeat('a', 64)), array('f' => 'b', 'h' => str_repeat('b', 64))));
$r2 = U::claim(5, array(array('f' => 'c', 'h' => str_repeat('c', 64)), array('f' => 'b', 'h' => str_repeat('b', 64))));
t('2 several values: all or nothing (the refused claim left no row behind)', array($r1['ok'], $r2['ok'], $r2['field_id'], (int) $db->get_var("SELECT COUNT(*) FROM wp_emsfb_uniq_ WHERE field_id = 'c'")), array(true, false, 'b', 0));
$GLOBALS['wpdb'] = new EfbUniqTestDb();
U::reset_ready();
t('2 no table: fail closed', U::claim(5, array(array('f' => 'a', 'h' => $h)))['ok'], false);
$db = fresh_db();

/* 3 check() */
$item = function ($f, $v) { return array('id_' => $f['id_'], 'type' => $f['type'], 'value' => $v); };
$chk = function ($f, $v, $lan = array()) use ($item) { return FR::check($f, $item($f, $v), array(), array(), $lan, array('form_id' => 5)); };
$r = $chk($em, 'ali@gmail.com');
t('3 a new value passes with its claim', array($r['ok'], $r['unique']['f'], $r['unique']['h'] === $h), array(true, 'em', true));
U::claim(5, array($r['unique']), 11);
$r = $chk($em, 'Ali@Gmail.COM');
t('3 the same address in other case: refused, code unique, default sentence', array($r['ok'], $r['code'], $r['m']), array(false, 'unique', 'This value has already been used.'));
t('3 msg_unique', $chk($em + array('msg_unique' => 'You are already registered.'), 'ali@gmail.com')['m'], 'You are already registered.');
$GLOBALS['efb_f03_plan'] = 'free_plus';
t('3 without the plan: no rule (the form still works)', $chk($em, 'ali@gmail.com')['ok'], true);
$GLOBALS['efb_f03_plan'] = 'free';
for ($k = 0; $k < 8; $k++) $chk($em, 'ali@gmail.com');
$r = $chk($em, 'ali@gmail.com', array('pleaseEnterVaildValue' => 'Please enter a valid value.'));
t('3 after 10 refusals in a minute: the general sentence, field hidden', array($r['ok'], $r['m'], !empty($r['hide'])), array(false, 'Please enter a valid value.', true));
t('3 an empty value runs no rule', $chk($em, '')['ok'], true);
t('3 a field without unique: no claim', FR::check(array_merge($em, array('unique' => '0')), $item($em, 'x@y.z'), array(), array(), array(), array('form_id' => 5))['unique'], null);

/* 4 delete, retention */
$db = fresh_db();
U::claim(5, array(array('f' => 'em', 'h' => $h)), 21);
U::claim(5, array(array('f' => 'em', 'h' => str_repeat('d', 64))), 22);
do_action('efb_entries_purged', array(21), 'delete', array(), array(5));
t('4 deleting an entry frees its value, the other stays', array(U::claim(5, array(array('f' => 'em', 'h' => $h)), 23)['ok'], (int) $db->get_var("SELECT COUNT(*) FROM wp_emsfb_uniq_")), array(true, 2));
do_action('efb_form_deleted', 5);
t('4 deleting the form forgets every value', (int) $db->get_var("SELECT COUNT(*) FROM wp_emsfb_uniq_"), 0);
U::$now = strtotime('2026-11-13 12:00:00 UTC');
U::claim(7, array(array('f' => 'x', 'h' => str_repeat('1', 64))));                // a request that died 2 h ago
$db->query("UPDATE wp_emsfb_uniq_ SET date = '2026-11-13 10:00:00'");
U::claim(7, array(array('f' => 'x', 'h' => str_repeat('2', 64))));                // a request still running
U::claim(7, array(array('f' => 'x', 'h' => str_repeat('3', 64))), 0);             // not stored (F13), 1 day old
U::claim(8, array(array('f' => 'x', 'h' => str_repeat('4', 64))), 0);             // not stored, no retention
$db->query("UPDATE wp_emsfb_uniq_ SET date = '2026-10-01 00:00:00' WHERE vhash IN ('" . str_repeat('3', 64) . "','" . str_repeat('4', 64) . "')");
$GLOBALS['opts']['emsfb_prv_forms'] = array(7 => array('days' => '30'));
if (!class_exists('\\Emsfb\\Form_Gate')) require_once __DIR__ . '/../includes/class-Emsfb-form-gate.php';
U::daily();
$left = $db->get_col("SELECT vhash FROM wp_emsfb_uniq_ ORDER BY id");
t('4 daily: dead claim removed, running one kept, "not stored" past the 30-day retention removed, none without retention kept', array_map(function ($x) { return $x[0]; }, $left), array('2', '4'));
U::$now = null;

/* 5 backfill */
$db = fresh_db();
$rows = array(array('type' => 'form'), array('type' => 'step'), $em);
for ($k = 1; $k <= 500; $k++) {
	$v = $k % 50 === 0 ? 'dup@example.com' : 'p' . $k . '@example.com';
	$content = str_replace('"', '\\"', json_encode(array(array('id_' => 'em', 'name' => 'Email', 'type' => 'email', 'value' => $v), array('type' => 'w_link', 'value' => 'x', 'amount' => -1))));
	$db->insert('wp_emsfb_msg_', array('form_id' => 9, 'track' => 'T' . $k, 'content' => $content, 'date' => '2026-11-01 10:00:00'));
}
t('5 before the backfill: the pre-check finds an older answer by scanning', U::taken(9, $em, array('value' => 'p251@example.com'), U::vhash(9, 'em', 'p251@example.com')), true);
t('5 … and not a new address', U::taken(9, $em, array('value' => 'new@example.com'), U::vhash(9, 'em', 'new@example.com')), false);
update_option('emsfb_uniq_bf_9', array('fields' => array('em'), 'cursor' => 0, 'done' => false, 'inserted' => 0, 'duplicates' => 0));
$steps = array();
$cur = 0;
$ins = 0; $dups = 0;
do { $s = U::backfill_step(9, $cur, 200, $rows); $steps[] = $s['cursor']; $cur = $s['cursor']; $ins += $s['inserted']; $dups += $s['duplicates']; } while (!$s['done']);
t('5 500 answers in batches of 200 with a cursor: 3 steps, done', array(count($steps), $s['done'], $ins, $dups), array(3, true, 491, 9));
$again = U::backfill_step(9, 0, 200, $rows);
t('5 a second run adds nothing', $again['inserted'], 0);
update_option('emsfb_uniq_bf_9', array('fields' => array('em'), 'cursor' => $cur, 'done' => true));
t('5 after the backfill the claim table answers alone', U::taken(9, $em, array('value' => 'P7@example.com'), U::vhash(9, 'em', U::key($em, 'P7@example.com'))), true);
$db2 = fresh_db();
$GLOBALS['efb_cron'] = array();
$db2->insert('wp_emsfb_msg_', array('form_id' => 12, 'track' => 'A', 'content' => str_replace('"', '\\"', json_encode(array(array('id_' => 'em', 'type' => 'email', 'value' => 'a@b.co')))), 'date' => '2026-11-01'));
U::on_form_saved(12, $rows);
$st = get_option('emsfb_uniq_bf_12');
t('5 the save hook starts and (small form) finishes the backfill', array($st['fields'], $st['done'], $st['inserted']), array(array('em'), true, 1));
U::on_form_saved(12, array(array('type' => 'form'), array('type' => 'step'), array('id_' => 'em', 'type' => 'email')));
t('5 no unique field any more: the state goes', get_option('emsfb_uniq_bf_12', null), null);

/* 6 payments */
$db = fresh_db();
$GLOBALS['efb_hooks'] = array();
$GLOBALS['efb_filters'] = array('efb_f03_plan' => $GLOBALS['efb_filters']['efb_f03_plan']);
U::register();
$hp = U::vhash(5, 'em', 'pay@example.com');
U::claim(5, array(array('f' => 'em', 'h' => $hp)), 31); // payments/create stored the pending entry 31
do_action('efb_payment_status_changed', array('id' => 1, 'msg_id' => 31, 'form_id' => 5), 'pending', 'failed', 'webhook');
t('6 failed payment: the value is given back (held for a retry)', array((int) $db->get_var("SELECT COUNT(*) FROM wp_emsfb_uniq_"), is_array(U::hold_get(31))), array(0, true));
U::claim(5, array(array('f' => 'em', 'h' => $hp)), 32);
$g = apply_filters('efb_payment_retry_gate', null, array('msg_id' => 31, 'form_id' => 5));
t('6 another visitor used it: "Try again" is refused with efb_field_unique', is_array($g) ? $g['code'] : $g, 'efb_field_unique');
$db->query('DELETE FROM wp_emsfb_uniq_');
$g = apply_filters('efb_payment_retry_gate', null, array('msg_id' => 31, 'form_id' => 5));
t('6 still free: "Try again" takes it again', array($g, (int) $db->get_var("SELECT msg_id FROM wp_emsfb_uniq_"), U::hold_get(31)), array(null, 31, null));
do_action('efb_payment_status_changed', array('id' => 2, 'msg_id' => 31, 'form_id' => 5), 'pending', 'expired', 'cron');
do_action('efb_payment_status_changed', array('id' => 2, 'msg_id' => 31, 'form_id' => 5), 'expired', 'paid', 'webhook');
t('6 paid after the value came back: taken again for the entry', array((int) $db->get_var("SELECT msg_id FROM wp_emsfb_uniq_"), U::hold_get(31)), array(31, null));
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-unique.php');
t('6 the retry gate runs before F11 (priority 9 < 10)', strpos($src, "add_filter('efb_payment_retry_gate', array(__CLASS__, 'retry_gate'), 9, 2);") !== false, true);

/* 7 personal data */
$db = fresh_db();
$db->insert('wp_emsfb_form', array('form_name' => 'Signup', 'form_structer' => str_replace('"', '\\"', json_encode(array(array('type' => 'form'), array('type' => 'step'), $em)))));
$fid = (int) $db->insert_id;
U::claim($fid, array(array('f' => 'em', 'h' => U::vhash($fid, 'em', U::key($em, 'Me@Example.com')))), 0);
$x = U::export('me@example.com');
t('7 exporter: one item, form and field named, the address not in it', array(count($x['data']), $x['data'][0]['data'][0]['value'], strpos(json_encode($x), 'me@example') === false), array(1, 'Signup', true));
$e = U::erase('ME@example.com');
t('7 eraser removes the fingerprint', array($e['items_removed'], (int) $db->get_var("SELECT COUNT(*) FROM wp_emsfb_uniq_")), array(true, 0));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
