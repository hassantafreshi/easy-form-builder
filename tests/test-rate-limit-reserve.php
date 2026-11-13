<?php
/**
 * F11-A: the atomic counters (Emsfb\Rate_Limit, F02 §5.3/§5.4 design).
 *
 *  1. reserve() is exact: under the limit it counts and answers true; at the limit it answers
 *     false and leaves the row untouched; a seed starts a new bucket from a real count;
 *     release() gives one back and never goes below 0; peek() reads; force() ignores the limit.
 *  2. hit() is a fixed-window rate limit; a new window starts at 1 again.
 *  3. Concurrency: 20 processes (pcntl_fork) call reserve(limit = 5) on one file database at the
 *     same moment: exactly 5 succeed and the row holds 5. Run 3 times.
 *  4. The raw key never reaches the table (only HMAC buckets); reset_scopes(); cleanup() removes
 *     ended windows only (never "ever"); a missing table fails closed.
 *  5. Optional, real WordPress: with EFB_F11_WP_LOAD=/path/to/wp-load.php, 20 PHP processes that
 *     each load WordPress (own database connection) do the same on the site's own table
 *     ([SKIP] otherwise).
 *
 * Run: php tests/test-rate-limit-reserve.php
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

require __DIR__ . '/f11-test-db.php';
require_once __DIR__ . '/../includes/class-Emsfb-rate-limit.php';
require_once __DIR__ . '/../includes/class-Emsfb-drafts.php';
use Emsfb\Rate_Limit;

/* ---------------------------------------------------------------- 1. exact cap */
$db = $GLOBALS['wpdb'] = new EfbF11TestDb();
$db->create_f11_tables();
Rate_Limit::reset_ready();
t('table ready', Rate_Limit::ready(), true);
$ok = array();
for ($i = 0; $i < 4; $i++) $ok[] = Rate_Limit::reserve('lt:5', '', '0', Rate_Limit::NEVER, 3);
t('limit 3: three true, then false', $ok, array(true, true, true, false));
t('the refused call did not count', Rate_Limit::peek('lt:5', '', '0'), 3);
t('release gives one place back', Rate_Limit::release('lt:5', '', '0'), true);
t('after release the next reserve succeeds', Rate_Limit::reserve('lt:5', '', '0', Rate_Limit::NEVER, 3), true);
t('peek of an unknown bucket is null', Rate_Limit::peek('lt:9', '', '0'), null);
t('seed: a new bucket starts from 2 (two entries already), limit 3 → one more', array(Rate_Limit::reserve('lt:6', '', '0', Rate_Limit::NEVER, 3, 2), Rate_Limit::reserve('lt:6', '', '0', Rate_Limit::NEVER, 3, 2)), array(true, false));
t('seed at or above the limit: full at once', Rate_Limit::reserve('lt:7', '', '0', Rate_Limit::NEVER, 3, 5), false);
t('seed is used only for a new bucket', Rate_Limit::peek('lt:7', '', '0'), 5);
Rate_Limit::reserve('lu:5', '42', 'd20261113', '2026-11-14 00:01:00', 1);
for ($i = 0; $i < 3; $i++) Rate_Limit::release('lu:5', '42', 'd20261113');
t('release never goes below 0', Rate_Limit::peek('lu:5', '42', 'd20261113'), 0);
$b = Rate_Limit::bucket('lt:6', '', '0');
t('force counts over the limit', array(Rate_Limit::force_bucket($b, 'lt:6', Rate_Limit::NEVER), Rate_Limit::peek_bucket($b)), array(true, 4));
t('limit 0 never reserves', Rate_Limit::reserve('lt:8', '', '0', Rate_Limit::NEVER, 0), false);

/* ---------------------------------------------------------------- 2. hit() */
Rate_Limit::$now = 1700000000;
$h = array();
for ($i = 0; $i < 4; $i++) $h[] = Rate_Limit::hit('dsv', '203.0.113.9', 3600, 3);
t('hit: 3 per hour, the 4th refused', $h, array(true, true, true, false));
Rate_Limit::$now = 1700000000 + 3600;
t('hit: a new window starts again', Rate_Limit::hit('dsv', '203.0.113.9', 3600, 3), true);
t('hit: another key has its own bucket', Rate_Limit::hit('dsv', '203.0.113.10', 3600, 3), true);
t('hit with limit 0 is unlimited', Rate_Limit::hit('dsv', 'x', 60, 0), true);

/* ---------------------------------------------------------------- 4. privacy, resets, cleanup */
$dump = json_encode($db->get_results('SELECT * FROM wp_emsfb_vrfy_rl_', ARRAY_A));
t('no raw key (IP, user id) in the table', strpos($dump, '203.0.113') === false && strpos($dump, '"42"') === false, true);
t('buckets are 64 hex characters', (bool) preg_match('/^[0-9a-f]{64}$/', $db->get_var('SELECT bucket FROM wp_emsfb_vrfy_rl_ LIMIT 1')), true);
t('scope stored for per-form resets', (int) $db->get_var("SELECT COUNT(*) FROM wp_emsfb_vrfy_rl_ WHERE scope = 'lt:5'"), 1);
t('reset_scopes removes only those scopes', array(Rate_Limit::reset_scopes(array('lt:5', 'lt:7')), Rate_Limit::peek('lt:5', '', '0'), Rate_Limit::peek('lt:6', '', '0')), array(2, null, 4));
Rate_Limit::$now = strtotime('2026-11-14 00:02:00 UTC');
$before = (int) $db->get_var('SELECT COUNT(*) FROM wp_emsfb_vrfy_rl_');
$removed = Rate_Limit::cleanup(1, 50);
t('cleanup removes ended windows (the day bucket and three hit windows), in batches of 1', $removed, 4);
t('cleanup keeps "ever" buckets', Rate_Limit::peek('lt:6', '', '0'), 4);
t('cleanup left the rest', (int) $db->get_var('SELECT COUNT(*) FROM wp_emsfb_vrfy_rl_'), $before - 4);
Rate_Limit::$now = null;
$db->pdo->exec('DROP TABLE wp_emsfb_vrfy_rl_');
Rate_Limit::reset_ready();
t('missing table: reserve fails closed', Rate_Limit::reserve('lt:5', '', '0', Rate_Limit::NEVER, 3), false);
t('missing table: hit fails closed', Rate_Limit::hit('dsv', 'x', 60, 3), false);

/* ---------------------------------------------------------------- 3. concurrency */
if (!function_exists('pcntl_fork')) {
	echo "[SKIP] concurrency: pcntl is not available\n";
} else {
	for ($round = 1; $round <= 3; $round++) {
		$file = sys_get_temp_dir() . '/efb-rl-' . getmypid() . '-' . $round . '.sqlite';
		@unlink($file);
		$setup = new EfbF11TestDb($file);
		$setup->create_f11_tables();
		$setup = null;
		$go = microtime(true) + 0.4;
		$kids = array();
		for ($i = 0; $i < 20; $i++) {
			$pid = pcntl_fork();
			if ($pid === 0) {
				$GLOBALS['wpdb'] = new EfbF11TestDb($file);
				Rate_Limit::reset_ready();
				while (microtime(true) < $go) usleep(200);
				$r = Rate_Limit::reserve('lt:42', '', '0', Rate_Limit::NEVER, 5, 0);
				exit($r ? 10 : 20);
			}
			$kids[] = $pid;
		}
		$yes = 0; $no = 0; $other = 0;
		foreach ($kids as $pid) {
			pcntl_waitpid($pid, $status);
			$code = pcntl_wexitstatus($status);
			if ($code === 10) $yes++; elseif ($code === 20) $no++; else $other++;
		}
		$check = new EfbF11TestDb($file);
		$GLOBALS['wpdb'] = $check;
		Rate_Limit::reset_ready();
		t("concurrency round {$round}: 20 processes, limit 5 → exactly 5 succeed, 15 refused", array($yes, $no, $other), array(5, 15, 0));
		t("concurrency round {$round}: the counter holds 5", Rate_Limit::peek('lt:42', '', '0'), 5);
		$check = null;
		@unlink($file);
	}
}

/* ---------------------------------------------------------------- 5. real WordPress (optional) */
$wp_load = getenv('EFB_F11_WP_LOAD');
if (!$wp_load || !is_readable($wp_load)) {
	echo "[SKIP] real WordPress: set EFB_F11_WP_LOAD=/path/to/wp-load.php\n";
} else {
	$child = sys_get_temp_dir() . '/efb-rl-wp-child-' . getmypid() . '.php';
	$scope = 'zz:' . substr((string) getmypid(), -5);
	file_put_contents($child, '<?php $_SERVER["HTTP_HOST"]="127.0.0.1"; require ' . var_export($wp_load, true) . '; while (microtime(true) < (float) $argv[1]) usleep(200); echo \Emsfb\Rate_Limit::reserve(' . var_export($scope, true) . ', "", "0", \Emsfb\Rate_Limit::NEVER, 5) ? "Y" : "N";');
	$go = microtime(true) + 6;
	$procs = array();
	for ($i = 0; $i < 20; $i++) {
		$procs[] = proc_open(array(PHP_BINARY, $child, (string) $go), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		$out[$i] = $pipes;
	}
	$res = '';
	foreach ($procs as $i => $p) { $res .= trim(stream_get_contents($out[$i][1])); proc_close($p); }
	t('real WordPress: 20 processes, limit 5 → exactly 5 Y', array(substr_count($res, 'Y'), substr_count($res, 'N')), array(5, 15));
	$clean = sys_get_temp_dir() . '/efb-rl-wp-clean-' . getmypid() . '.php';
	file_put_contents($clean, '<?php $_SERVER["HTTP_HOST"]="127.0.0.1"; require ' . var_export($wp_load, true) . '; echo \Emsfb\Rate_Limit::reset_scopes(array(' . var_export($scope, true) . '));');
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($clean), $o);
	t('real WordPress: test bucket removed', trim(implode('', $o)), '1');
	@unlink($child); @unlink($clean);
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
