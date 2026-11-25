<?php
/**
 * F14-J: per-form statistics (Entries::bump / stats / top_forms, AJAX efb_form_stats_Emsfb) and where views and
 * submissions are counted.
 *
 *  - bump(): one row per form and day; 50 processes bumping at the same moment on one SQLite file give exactly 50
 *    (INSERT IGNORE + "n = n + 1", atomic);
 *  - stats(): a row for each of the last 7 / 30 / 90 days (missing days are 0), totals, conversion = submits ÷ views,
 *    null ("—") when there is no view, has_views false before any view was counted;
 *  - top_forms(): most views first, a deleted form left out;
 *  - AJAX: nonce / capability, Free Plus (efb_plan_required on Free), the unread / spam counts with it;
 *  - the public class counts a view only where a NEW session is created (prepare_form_session_api and the page
 *    render), never on efb_code_touch_session; a submission in insert_message_db() after the insert and in the
 *    "do not store" path (F13);
 *  - the dashboard widget's answer has top_forms, its earlier data untouched.
 *
 * Run: php tests/test-entries-stats.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/entries-test-db.php';
if (!function_exists('emsfb_json_normalize_efb')) { function emsfb_json_normalize_efb($s) { return str_replace('\\"', '"', (string) $s); } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($a) { return 'nonce-' . $a; } }
class EfbJsonExit extends Exception { public $data; public function __construct($d) { $this->data = $d; parent::__construct('json'); } }
if (!function_exists('wp_send_json_success')) { function wp_send_json_success($d = null, $s = 200) { throw new EfbJsonExit($d); } }
if (!function_exists('check_ajax_referer')) { function check_ajax_referer($a, $f = false, $die = true) { return !empty($GLOBALS['efb_nonce_ok']); } }
if (!function_exists('efb_plan_at_least_efb')) { function efb_plan_at_least_efb($need) { $rank = array('free' => 0, 'free_plus' => 1, 'pro' => 2); return $rank[$GLOBALS['efb_plan']] >= $rank[$need]; } }
$GLOBALS['efb_plan'] = 'free_plus';
require_once __DIR__ . '/../includes/class-Emsfb-privacy.php';
require_once __DIR__ . '/../includes/admin/class-Emsfb-entries.php';
use Emsfb\Entries;

/* ------------------------------------------------------------------ concurrency */
if (!function_exists('pcntl_fork')) {
	echo "[SKIP] concurrency: pcntl is not available\n";
} else {
	$file = sys_get_temp_dir() . '/efb-f14-stats-' . getmypid() . '.sqlite';
	@unlink($file);
	$setup = new EfbEntriesTestDb($file);
	$setup->pdo->exec('CREATE TABLE IF NOT EXISTS wp_emsfb_msg_ (msg_id INTEGER PRIMARY KEY)');
	foreach (Entries::ddl('wp_', '') as $sql) if (strpos($sql, 'emsfb_stats_') !== false) $setup->exec_mysql_ddl($sql);
	$setup = null;
	$go = microtime(true) + 0.5;
	$kids = array();
	for ($i = 0; $i < 50; $i++) {
		$pid = pcntl_fork();
		if ($pid === 0) {
			$GLOBALS['wpdb'] = new EfbEntriesTestDb($file);
			Entries::reset_ready();
			while (microtime(true) < $go) usleep(200);
			exit(Entries::bump(9, 'views', 1, '2026-11-14') ? 10 : 20);
		}
		$kids[] = $pid;
	}
	$ok = 0;
	foreach ($kids as $pid) { pcntl_waitpid($pid, $st); if (pcntl_wexitstatus($st) === 10) $ok++; }
	$chk = new EfbEntriesTestDb($file);
	t('50 processes at the same moment: 50 bumps, the day row says 50, one row only', array($ok, (int) $chk->get_var("SELECT views FROM wp_emsfb_stats_ WHERE form_id = 9"), (int) $chk->get_var('SELECT COUNT(*) FROM wp_emsfb_stats_')), array(50, 50, 1));
	$chk = null;
	@unlink($file);
}

/* ------------------------------------------------------------------ stats() */
$db = $GLOBALS['wpdb'] = new EfbEntriesTestDb();
$db->create_f14_tables();
Entries::reset_ready();
$today = gmdate('Y-m-d');
$ago = function ($n) { return gmdate('Y-m-d', time() - $n * 86400); };
Entries::bump(3, 'views', 10, $today);
Entries::bump(3, 'submits', 4, $today);
Entries::bump(3, 'views', 5, $ago(3));
Entries::bump(3, 'submits', 1, $ago(3));
Entries::bump(3, 'spam', 1, $ago(3));
Entries::bump(3, 'views', 100, $ago(40));
$s = Entries::stats(3, 7);
t('7 days: one row per day, oldest first, today last', array(count($s['series']), $s['series'][6]['day'], $s['series'][6]['views']), array(7, $today, 10));
t('totals and conversion (5 of 15 = 33.3 %)', array($s['totals'], $s['conversion'], $s['has_views']), array(array('views' => 15, 'submits' => 5, 'spam' => 1), 33.3, true));
t('30 days still leave out the day 40 days ago; 90 days count it', array(Entries::stats(3, 30)['totals']['views'], Entries::stats(3, 90)['totals']['views']), array(15, 115));
t('an unknown period is 30 days', Entries::stats(3, 12)['days'], 30);
Entries::bump(4, 'submits', 2, $today);
$z = Entries::stats(4, 7);
t('no view: conversion null ("—"), has_views false ("No data yet")', array($z['conversion'], $z['has_views'], $z['totals']['submits']), array(null, false, 2));

/* ------------------------------------------------------------------ top_forms() */
$f3 = $db->add_form(array(array('type' => 'form')));
$f4 = $db->add_form(array(array('type' => 'form')));
$db->pdo->exec("UPDATE wp_emsfb_stats_ SET form_id = {$f3} WHERE form_id = 3");
$db->pdo->exec("UPDATE wp_emsfb_stats_ SET form_id = {$f4} WHERE form_id = 4");
Entries::bump(777, 'views', 999, $today); // a form that was deleted
$top = Entries::top_forms($ago(6), 5);
t('top forms: most views first, the deleted form left out, conversion null without views', array(array_column($top, 'form_id'), $top[0]['views'], $top[1]['conversion']), array(array($f3, $f4), 15, null));
// QA4-007: after an upgrade the backfill brings past submissions, views are counted from the upgrade on.
$f5 = $db->add_form(array(array('type' => 'form')));
Entries::bump($f5, 'views', 1, $today);
Entries::bump($f5, 'submits', 5, $today);
$row5 = null;
foreach (Entries::top_forms($ago(6), 5) as $r) if ($r['form_id'] === $f5) $row5 = $r;
t('QA4-007: top forms never show more than 100 % (1 view, 5 submissions: conversion null, shown "—")', $row5 ? array($row5['views'], $row5['submits'], $row5['conversion']) : 'missing', array(1, 5, null));
t('QA4-007: stats() of that form: conversion null too', Entries::stats($f5, 7)['conversion'], null);
t('QA4-007: conversion_rate(): 100 % at most, one decimal, null without views', array(Entries::conversion_rate(4, 4), Entries::conversion_rate(3, 1), Entries::conversion_rate(0, 2), Entries::conversion_rate(1, 2)), array(100.0, 33.3, null, null));

/* ------------------------------------------------------------------ AJAX */
$ajax = function ($post) { $_POST = $post; try { Entries::ajax_stats(); } catch (EfbJsonExit $x) { return $x->data; } return null; };
$GLOBALS['efb_nonce_ok'] = true; $GLOBALS['efb_caps'] = array('read' => true);
t('stats for a Subscriber → efb_forbidden', $ajax(array('form_id' => $f3))['code'], 'efb_forbidden');
$GLOBALS['efb_caps'] = array('Emsfb' => true);
$GLOBALS['efb_plan'] = 'free';
t('Free: statistics need Free Plus (setting:entries_stats)', $ajax(array('form_id' => $f3))['feature'], 'setting:entries_stats');
$GLOBALS['efb_plan'] = 'free_plus';
$db->add_entry($f3, array(), $today . ' 10:00:00', array('read_' => 0));
$db->add_entry($f3, array(), $today . ' 10:00:00', array('read_' => 1, 'entry_status' => 1));
$r = $ajax(array('form_id' => $f3, 'days' => '7'));
t('Free Plus: the statistics with the unread and spam counts of now', array($r['success'], $r['days'], $r['totals']['views'], $r['unread'], $r['spam_now']), array(true, 7, 15, 1, 1));

/* ------------------------------------------------------------------ where counting happens */
$pub = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$prep = substr($pub, strpos($pub, 'public function prepare_form_session_api'), 6000);
$create = strpos($prep, "efb_code_validate_create(\$fid,0,'visit',0);");
$bumpp = strpos($prep, "Entries::bump(\$fid, 'views')");
$touch = strpos($prep, 'efb_code_touch_session($sid,$fid)');
t('prepare_form_session_api: a view only where a new session is created (not on touch), never with a runtime token, not for the live session the page was drawn with', $create !== false && $bumpp > $create && $bumpp < $touch && strpos(substr($prep, $create, $bumpp - $create), '!$proof') !== false && strpos(substr($prep, $create, $bumpp - $create), "efb_code_validate_check(\$page_sid_efb, \$fid)") !== false, true);
$rh = file_get_contents(__DIR__ . '/../public/assets/js/request-health-efb.js');
t('the page sends its own session (page_sid) with its first prepare', strpos($rh, "page_sid:context.value ? '' : legacy(fid,{}).sid") !== false, true);
t('the page render: a view with the page\'s own session', (bool) preg_match("/efb_code_validate_create\( \\\$this->id , 0, 'visit' , 0\);\s*\/\/[^\n]*\n\s*if \(\\\$sid && \\\$this->id > 0[^\n]*Entries::bump\(\\\$this->id, 'views'\);/", $pub), true);
$ins = substr($pub, strpos($pub, 'public function insert_message_db'), 5000);
t('insert_message_db(): a submission after the insert succeeded (after the storage-failed exit)', strpos($ins, "Entries::bump((int) \$this->id, 'submits')") > strpos($ins, "efb_storage_failed"), true);
$nost = substr($pub, strpos($pub, 'public function store_entry_efb'), 2500);
t('"do not store" (F13): the submission still counts', strpos($nost, "Entries::bump((int) \$this->id, 'submits')") !== false, true);
$dw = file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-dashboard-widget.php');
t('dashboard widget: top_forms added, visits / submissions / chart kept', strpos($dw, "'top_forms'   => \$top_forms") !== false && strpos($dw, "'visits'      => \$visits") !== false && strpos($dw, "'chart'       => [") !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
