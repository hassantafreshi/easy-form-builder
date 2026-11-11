<?php
/**
 * Standalone test for atomic booking capacity (F05 sub-task D).
 *
 * Loads the real includes/class-Emsfb-stock.php and the real
 * Install::upgrade_schema() against an in-memory $wpdb stub that executes
 * each SQL statement atomically, as MySQL does for a single UPDATE on one row.
 * Two requests are interleaved statement by statement with PHP Fibers, which
 * is the worst case the old read-check-write code lost.
 *
 * Covers:
 *   1. Two concurrent claims on the last seat: exactly one wins (no oversell).
 *      The same interleaving against the legacy algorithm oversells, so the
 *      test really exercises the race.
 *   2. A submit rejected by captcha/Shield uses no capacity, and a failed
 *      entry insert gives its seat back.
 *   3. Lazy seed from the stored registered_count; multi-claim all-or-nothing.
 *   4. upgrade_schema() creates emsfb_stock_ before the emsfb_stts_ early
 *      return and is safe to run more than once.
 *   5. Source guards on includes/class-Emsfb-public.php: the submit path no
 *      longer writes form_structer, and the seat is taken inside
 *      insert_message_db() after Form_Runtime::begin() and before the insert.
 *
 * Run: php tests/test-stock-reserve.php
 */

$pass = 0;
$fail = 0;
function test($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) {
		$pass++;
		echo "[PASS] $label\n";
	} else {
		$fail++;
		echo "[FAIL] $label\n";
		echo "  Expected: " . json_encode($expected) . "\n";
		echo "  Actual:   " . json_encode($actual) . "\n";
	}
}

if (!class_exists('Fiber')) {
	echo "[SKIP] PHP " . PHP_VERSION . " has no Fiber (needs 8.1+)\n";
	exit(0);
}

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('EMSFB_DB_VERSION', 1.4);

$GLOBALS['efb_options'] = array();
function update_option($k, $v) { $GLOBALS['efb_options'][$k] = $v; return true; }
function get_option($k, $d = false) { return $GLOBALS['efb_options'][$k] ?? $d; }

/**
 * In-memory $wpdb. Understands only the statements Stock and
 * Install::upgrade_schema() send. Each query() is one atomic step; inside a
 * Fiber it yields *before* running, so the scheduler can interleave requests
 * between any two statements.
 */
class FakeWpdb {
	public $prefix = 'wp_';
	public $tables = array('wp_emsfb_form' => true);   // emsfb_stts_ missing on purpose
	public $stock = array();                            // "fid|field|opt" => used
	public $log = array();
	public $fail_create = false;
	private $suppress = false;

	public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; }
	public function suppress_errors($s = true) { $o = $this->suppress; $this->suppress = $s; return $o; }

	public function prepare($q, ...$args) {
		if (count($args) === 1 && is_array($args[0])) $args = $args[0];
		$i = 0;
		return preg_replace_callback('/%[dsi]/', function ($m) use (&$i, $args) {
			$v = $args[$i++];
			return $m[0] === '%d' ? (string) (int) $v : "'" . addslashes((string) $v) . "'";
		}, $q);
	}

	private function step() {
		if (Fiber::getCurrent() !== null) Fiber::suspend();
	}

	private function stock_table() { return $this->prefix . 'emsfb_stock_'; }

	public function query($sql) {
		$this->step();
		$this->log[] = $sql;
		$t = $this->stock_table();
		if (preg_match('/^CREATE TABLE IF NOT EXISTS (\S+)/', $sql, $m)) {
			if ($this->fail_create) return false;
			$this->tables[$m[1]] = true;
			return 0;
		}
		if (strpos($sql, "`{$t}`") !== false && !isset($this->tables[$t])) return false; // table missing
		if (preg_match("/^INSERT IGNORE INTO `[^`]+` .* VALUES \\((\\d+), '([^']*)', '([^']*)', (\\d+),/", $sql, $m)) {
			$k = "$m[1]|$m[2]|$m[3]";
			if (isset($this->stock[$k])) return 0;
			$this->stock[$k] = (int) $m[4];
			return 1;
		}
		if (preg_match("/^UPDATE `[^`]+` SET used = used \\+ 1, .* WHERE form_id = (\\d+) AND field_id = '([^']*)' AND option_id = '([^']*)' AND used < (\\d+)$/", $sql, $m)) {
			$k = "$m[1]|$m[2]|$m[3]";
			if (!isset($this->stock[$k]) || $this->stock[$k] >= (int) $m[4]) return 0;
			$this->stock[$k]++;
			return 1;
		}
		if (preg_match("/^UPDATE `[^`]+` SET used = used - 1, .* WHERE form_id = (\\d+) AND field_id = '([^']*)' AND option_id = '([^']*)' AND used > 0$/", $sql, $m)) {
			$k = "$m[1]|$m[2]|$m[3]";
			if (!isset($this->stock[$k]) || $this->stock[$k] <= 0) return 0;
			$this->stock[$k]--;
			return 1;
		}
		if (preg_match("/^DELETE FROM `[^`]+` WHERE form_id = (\\d+)$/", $sql, $m)) {
			$n = 0;
			foreach (array_keys($this->stock) as $k) if (strpos($k, $m[1] . '|') === 0) { unset($this->stock[$k]); $n++; }
			return $n;
		}
		if (strpos($sql, 'ALTER TABLE') === 0) return 1;
		throw new RuntimeException("FakeWpdb: unexpected query: $sql");
	}

	public function get_var($sql) {
		if (preg_match("/^SHOW TABLES LIKE '([^']*)'$/", $sql, $m)) return isset($this->tables[$m[1]]) ? $m[1] : null;
		throw new RuntimeException("FakeWpdb: unexpected get_var: $sql");
	}

	public function get_results($sql, $out = null) {
		if (strpos($sql, 'SHOW COLUMNS') === 0) return array(array('Field' => 'status'));
		if (preg_match("/^SELECT field_id, option_id, used FROM `[^`]+` WHERE form_id = (\\d+)$/", $sql, $m)) {
			if (!isset($this->tables[$this->stock_table()])) return null;
			$rows = array();
			foreach ($this->stock as $k => $used) {
				list($f, $field, $opt) = explode('|', $k);
				if ($f === $m[1]) $rows[] = array('field_id' => $field, 'option_id' => $opt, 'used' => (string) $used);
			}
			return $rows;
		}
		throw new RuntimeException("FakeWpdb: unexpected get_results: $sql");
	}
}

$GLOBALS['wpdb'] = new FakeWpdb();
require __DIR__ . '/../includes/class-Emsfb-stock.php';
require __DIR__ . '/../includes/class-Emsfb-install.php';

use Emsfb\Stock;

/** Run callables as Fibers, resuming them in turn: one SQL statement each per round. */
function run_interleaved(array $jobs) {
	$fibers = array();
	$results = array();
	foreach ($jobs as $name => $job) {
		$fibers[$name] = new Fiber($job);
		$fibers[$name]->start();
	}
	while ($fibers) {
		foreach ($fibers as $name => $f) {
			if ($f->isTerminated()) {
				$results[$name] = $f->getReturn();
				unset($fibers[$name]);
				continue;
			}
			$f->resume();
		}
	}
	return $results;
}

// A booking form as stored by the builder: option "Slot A" holds 4 seats and 3
// are already taken in the legacy JSON counter.
$form_id = 7;
$form_rows = array(
	array('formName' => 'Booking', 'booking' => 1, 'type' => 'form'),
	array('id_' => 'fld1', 'type' => 'radio', 'required' => true),
	array('id_' => 'optA', 'parent' => 'fld1', 'type' => 'option', 'value' => 'Slot A', 'mlen' => '4', 'registered_count' => 3),
	array('id_' => 'optB', 'parent' => 'fld1', 'type' => 'option', 'value' => 'Slot B', 'mlen' => '2', 'registered_count' => 0),
);
$form_json_before = json_encode($form_rows);
$claimA = Stock::claim_from_option($form_rows[2]);

test('claim built from stored option (cap from mlen, seed from registered_count)',
	array($claimA['field_id'], $claimA['option_id'], $claimA['cap'], $claimA['seed']), array('fld1', 'optA', 4, 3));
test('option without mlen has no claim', Stock::claim_from_option(array('id_' => 'x', 'parent' => 'p', 'type' => 'option')), null);
test('empty mlen counts as full (same as the old "" <= count test)', Stock::claim_from_option(array('id_' => 'x', 'parent' => 'p', 'mlen' => ''))['cap'], 0);

// ---------------------------------------------------------------- schema
echo "\n-- upgrade_schema()\n";
$db = $GLOBALS['wpdb'];
$applied1 = \Emsfb\Install::upgrade_schema();
test('table created even though emsfb_stts_ is missing (before the early return)', isset($db->tables['wp_emsfb_stock_']), true);
test('first run reports the CREATE', in_array('emsfb_stock_: CREATE TABLE', $applied1, true), true);
$creates_before = count(preg_grep('/^CREATE TABLE/', $db->log));
$applied2 = \Emsfb\Install::upgrade_schema();
test('second run is a no-op for the stock table', in_array('emsfb_stock_: CREATE TABLE', $applied2, true), false);
test('second run sends no CREATE', count(preg_grep('/^CREATE TABLE/', $db->log)), $creates_before);

$db2 = new FakeWpdb();
$db2->fail_create = true;
$GLOBALS['wpdb'] = $db2;
$GLOBALS['efb_options'] = array();
\Emsfb\Install::upgrade_schema();
test('failed CREATE does not record the DB version (retried next load)', get_option('Emsfb_db_version', 'unset'), 'unset');
$r = Stock::reserve($form_id, array($claimA), $db2);
test('missing table: reserve refuses instead of overselling', array($r['ok'], $r['reason']), array(false, 'unavailable'));
$GLOBALS['wpdb'] = $db;

// ---------------------------------------------------------------- race
echo "\n-- two concurrent claims on the last seat (3 of 4 taken)\n";

// Legacy algorithm, as it was in class-Emsfb-public.php: read the JSON counter,
// check mlen <= registered_count, then write the whole form back. Each step is
// one statement, interleaved exactly like the new code below.
$legacy_store = array('form_structer' => $form_json_before);
$legacy = function () use (&$legacy_store) {
	$rows = json_decode($legacy_store['form_structer'], true);   // SELECT form_structer
	Fiber::suspend();
	$opt = $rows[2];
	if ($opt['mlen'] <= $opt['registered_count']) return 'full';
	$rows[2]['registered_count']++;
	$legacy_store['form_structer'] = json_encode($rows);         // UPDATE emsfb_form SET form_structer
	Fiber::suspend();
	return 'booked';
};
$res = run_interleaved(array('r1' => $legacy, 'r2' => $legacy));
test('control: legacy read-check-write books both requests (the bug)', array_values($res), array('booked', 'booked'));
test('control: legacy counter ends at 4 for 5 real bookings', json_decode($legacy_store['form_structer'], true)[2]['registered_count'], 4);

$new = function () use ($form_id, $claimA, $db) {
	return Stock::reserve($form_id, array($claimA), $db);
};
$res = run_interleaved(array('r1' => $new, 'r2' => $new));
$wins = array_values(array_filter($res, function ($r) { return $r['ok']; }));
$lost = array_values(array_filter($res, function ($r) { return !$r['ok']; }));
test('exactly one request gets the last seat', count($wins), 1);
test('the other is refused as full', $lost[0]['reason'] ?? null, 'full');
test('refusal names the full option (for the bkFlM_ message)', $lost[0]['claim']['label'] ?? null, 'Slot A');
test('counter equals capacity: seeded 3 + 1 = 4, no oversell', $db->stock["$form_id|fld1|optA"], 4);

echo "\n-- 20 concurrent claims on a fresh option with 2 seats\n";
$claimB = Stock::claim_from_option($form_rows[3]);
$jobs = array();
for ($i = 0; $i < 20; $i++) $jobs["r$i"] = function () use ($form_id, $claimB, $db) { return Stock::reserve($form_id, array($claimB), $db); };
$res = run_interleaved($jobs);
test('20 interleaved requests on capacity 2: exactly 2 booked', count(array_filter($res, function ($r) { return $r['ok']; })), 2);
test('counter stops at capacity', $db->stock["$form_id|fld1|optB"], 2);

// ---------------------------------------------------------------- pipeline
echo "\n-- submit pipeline: rejected and failed submits use no capacity\n";

/**
 * Mirrors the order in Emsfb\_Public::submit after this change:
 * validation only collects claims; captcha/Shield reject before anything is
 * stored; insert_message_db() reserves, inserts, and releases on failure.
 */
function simulate_submit(FakeWpdb $db, $form_id, array $claims, $captcha_ok, $insert_ok) {
	if (!$captcha_ok) return 'rejected:captcha';            // errorCaptcha / errorMRobot, die()
	$r = Stock::reserve($form_id, $claims, $db);              // reserve_stock_efb()
	if (!$r['ok']) return 'rejected:' . $r['reason'];
	if (!$insert_ok) {                                        // $inserted === false
		Stock::release($form_id, $r['taken'], $db);       // release_stock_efb()
		return 'error:storage';
	}
	return 'saved';
}

$db3 = new FakeWpdb();
$db3->tables['wp_emsfb_stock_'] = true;
$claimLast = Stock::claim_from_option($form_rows[2]);   // 3 of 4 taken
test('captcha-rejected submit is refused', simulate_submit($db3, $form_id, array($claimLast), false, true), 'rejected:captcha');
test('rejected submit touched no counter (no row, no seat)', isset($db3->stock["$form_id|fld1|optA"]), false);
test('failed insert returns the storage error', simulate_submit($db3, $form_id, array($claimLast), true, false), 'error:storage');
test('failed insert released its seat (back to the seeded 3)', $db3->stock["$form_id|fld1|optA"], 3);
test('a real visitor still gets the last seat afterwards', simulate_submit($db3, $form_id, array($claimLast), true, true), 'saved');
test('next one is full', simulate_submit($db3, $form_id, array($claimLast), true, true), 'rejected:full');
test('counter == capacity', $db3->stock["$form_id|fld1|optA"], 4);

echo "\n-- multi-claim all-or-nothing, release floor, display overlay\n";
$db4 = new FakeWpdb();
$db4->tables['wp_emsfb_stock_'] = true;
$free = array('field_id' => 'chk', 'option_id' => 'o1', 'cap' => 5, 'seed' => 0, 'label' => 'Free');
$full = array('field_id' => 'chk', 'option_id' => 'o2', 'cap' => 1, 'seed' => 1, 'label' => 'Gone');
$r = Stock::reserve($form_id, array($full, $free, $free), $db4);
test('one full option refuses the whole submission', array($r['ok'], $r['claim']['label']), array(false, 'Gone'));
test('seat taken for the free option before the failure is released', $db4->stock["$form_id|chk|o1"], 0);
$r = Stock::reserve($form_id, array($free, $free), $db4);
test('duplicate claim in one submit counts once', $db4->stock["$form_id|chk|o1"], 1);
Stock::release($form_id, $r['taken'], $db4);
Stock::release($form_id, $r['taken'], $db4);
test('release never goes below zero', $db4->stock["$form_id|chk|o1"], 0);

$shown = json_decode(Stock::overlay_json($form_json_before, $form_id, $db), true);
test('render overlay shows the live count to the browser', array($shown[2]['registered_count'], $shown[3]['registered_count']), array(4, 2));
test('overlay leaves a form without counters untouched', Stock::overlay_json($form_json_before, 999, $db), $form_json_before);
test('form definition was never written by any submit above', json_encode($form_rows), $form_json_before);

// ---------------------------------------------------------------- source guards
echo "\n-- source guards (includes/class-Emsfb-public.php)\n";
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
test('no registered_count increment left in the submit path', preg_match("/\\['registered_count'\\]\\s*=/", $src), 0);
test("no update of emsfb_form form_structer from the booking branch", preg_match('/update\(\$table_name,\s*\[\'form_structer\'/', $src), 0);
preg_match('/public function insert_message_db\(.*?\n\t}\n/s', $src, $m);
$body = $m[0] ?? '';
$p_begin = strpos($body, 'Form_Runtime::begin');
$p_res = strpos($body, 'reserve_stock_efb()');
$p_ins = strpos($body, '->insert(');
$p_rel = strpos($body, 'release_stock_efb(');
test('insert_message_db: begin() < reserve < insert < release-on-failure',
	$p_begin !== false && $p_res !== false && $p_ins !== false && $p_rel !== false && $p_begin < $p_res && $p_res < $p_ins && $p_ins < $p_rel, true);
$p_captcha = strpos($src, "'gate'    => 'recaptcha_verification'");
$p_shield = strpos($src, "apply_filters('efb_submit_bot_decision'");
$p_case = strpos($src, 'case "form":', $p_captcha);
test('captcha and Shield gates come before the form insert', $p_shield !== false && $p_shield < $p_captcha && $p_captcha < $p_case, true);
test('payment branch reserves before update_message_db and releases on failure',
	preg_match('/reserve_stock_efb\(\);\s*\$db_update_result = \$this->update_message_db\(\);\s*if \(\$db_update_result != 1\) \$this->release_stock_efb/', $src), 1);

$js = file_get_contents(__DIR__ . '/../public/assets/js/core-efb.js');
test('core-efb.js: hard-coded English booking messages are gone',
	strpos($js, 'Unfortunately, the option you selected is no longer available') === false
	&& strpos($js, 'its expiration date has passed') === false, true);
test("core-efb.js uses bkXpM_ and bkFlM_", strpos($js, "bk_text_efb('bkXpM_'") !== false && strpos($js, "bk_text_efb('bkFlM_'") !== false, true);
test("bkXpM_/bkFlM_ are sent to the public page", preg_match("/'bkXpM_','bkFlM_',/", $src), 1);

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
