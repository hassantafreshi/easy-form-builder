<?php
/**
 * F14-A: the DB 1.10 schema (Install::upgrade_f14_schema(), Entries::upgrade_msg_table(), the statistics backfill)
 * on the SQLite stand-in.
 *
 *  - EMSFB_DB_VERSION is '1.10' or later (a string: version_compare() puts it after 1.9; the number 1.10 would be 1.1);
 *  - the step sits after F05's and before the emsfb_stts_ early return; install() creates the tables and the
 *    fresh emsfb_msg_ already has the columns and keys;
 *  - a 1.9 site: entry_status / starred / trashed_at / edited_at and the keys form_status_date / read_ in ONE
 *    ALTER TABLE; when the server refuses a multi-clause ALTER, one clause at a time; old entries read 0 / 0;
 *  - the three tables from the real DDL (notes and log keyed by msg_id, stats keyed by form + day);
 *  - a second run applies nothing and issues no ALTER;
 *  - the backfill is scheduled once (never run inside the upgrade), counts the entries that existed at the
 *    upgrade in batches by msg_id, and the views of the 7 days emsfb_stts_ still holds (visit + send);
 *  - bump(): INSERT IGNORE + UPDATE (a second row of the same day is refused), never below 0.
 *
 * Run: php tests/test-entries-schema.php
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
function dbDelta($sql) { $GLOBALS['wpdb']->exec_mysql_ddl($sql); $GLOBALS['dbdelta'][] = $sql; return array(); }
if (!function_exists('get_role')) { function get_role($r) { return null; } }
function emsfb_json_normalize_efb($s) { return str_replace('\\"', '"', (string) $s); }
function emsfb_json_decode_efb($s, $a = true) { return json_decode($s, $a); }

$main = file_get_contents(__DIR__ . '/../emsfb.php');
t("EMSFB_DB_VERSION is a string, '1.10' or later (F15: '1.11')", (bool) preg_match('/define\("EMSFB_DB_VERSION",\s*\'(1\.\d\d)\'\)/', $main, $vm) && version_compare($vm[1], '1.10', '>='), true);
t('version_compare puts 1.10 after 1.9 (the upgrade runs on a 1.9 site)', version_compare('1.9', '1.10', '<'), true);
t('and a site already on 1.10 is not upgraded again', version_compare('1.10', '1.10', '>='), true);
define('EMSFB_DB_VERSION', '1.10');
$GLOBALS['opts'] = array();
foreach (array('class-Emsfb-rate-limit.php', 'class-Emsfb-drafts.php', 'payments/class-Emsfb-payment-store.php', 'class-Emsfb-masks.php', 'class-Emsfb-field-rules.php', 'class-Emsfb-unique.php', 'class-Emsfb-choices.php', 'class-Emsfb-stock.php', 'admin/class-Emsfb-entries.php', 'class-Emsfb-install.php') as $f) require_once __DIR__ . '/../includes/' . $f;
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-install.php');
$up = substr($src, strpos($src, 'static function upgrade_schema'));
t('F14 step after F05 and before the early return of upgrade_schema()', strpos($up, 'self::upgrade_f05_schema()') < strpos($up, 'self::upgrade_f14_schema()') && strpos($up, 'self::upgrade_f14_schema()') < strpos($up, 'return $applied;'), true);
t('install() creates the F14 tables too', strpos($src, 'self::f14_ddl( $wpdb->prefix, $charset_collate )') !== false, true);
preg_match('/\$sql = "CREATE TABLE \{\$table_name_msg\} \((.*?)\) \{\$charset_collate\};";/s', $src, $m);
$fresh = isset($m[1]) ? $m[1] : '';
foreach (array('`entry_status` TINYINT NOT NULL DEFAULT 0', '`starred` TINYINT NOT NULL DEFAULT 0', '`trashed_at` datetime NULL DEFAULT NULL', '`edited_at` datetime NULL DEFAULT NULL', 'KEY form_status_date (form_id,entry_status,`date`)', 'KEY read_ (read_)') as $piece) {
	t("fresh install emsfb_msg_ has {$piece}", strpos($fresh, $piece) !== false, true);
}
$ddl = \Emsfb\Entries::ddl('wp_', '');
t('three tables in the DDL', array_keys($ddl), array('emsfb_msg_notes_', 'emsfb_msg_log_', 'emsfb_stats_'));
t('notes: note_id, msg_id key, TEXT note', strpos($ddl['emsfb_msg_notes_'], 'KEY msg_id (msg_id)') !== false && strpos($ddl['emsfb_msg_notes_'], '`note` text') !== false, true);
t('log: action varchar(20), field_id varchar(40), meta varchar(255), old/new mediumtext', strpos($ddl['emsfb_msg_log_'], '`action` varchar(20)') !== false && strpos($ddl['emsfb_msg_log_'], '`meta` varchar(255)') !== false && substr_count($ddl['emsfb_msg_log_'], 'mediumtext') === 2, true);
t('stats: PRIMARY KEY (form_id, day)', (bool) preg_match('/PRIMARY KEY\s+\(form_id,day\)/', $ddl['emsfb_stats_']), true);

/* ------------------------------------------------------------------ a 1.9 site */
$db = $GLOBALS['wpdb'] = new EfbEntriesTestDb();
$db->pdo->exec("INSERT INTO wp_emsfb_msg_ (form_id, track, ip, content, date, read_) VALUES (3, 'A', '', '[]', '2026-11-01 10:00:00', 0), (3, 'B', '', '[]', '2026-11-01 11:00:00', 1), (3, 'C', '', '[]', '2026-11-02 09:00:00', 0), (4, 'D', '', '[]', '2026-11-02 09:30:00', 4)");
$GLOBALS['efb_cron'] = array();
$first = \Emsfb\Install::upgrade_f14_schema();
t('a 1.9 site: the columns, keys and tables, and the backfill scheduled', $first, array(
	'emsfb_msg_: ADD COLUMN entry_status', 'emsfb_msg_: ADD COLUMN starred', 'emsfb_msg_: ADD COLUMN trashed_at', 'emsfb_msg_: ADD COLUMN edited_at',
	'emsfb_msg_: ADD KEY form_status_date', 'emsfb_msg_: ADD KEY read_',
	'emsfb_msg_notes_: CREATE TABLE', 'emsfb_msg_log_: CREATE TABLE', 'emsfb_stats_: CREATE TABLE', 'emsfb_stats_: backfill scheduled'));
t('ONE ALTER TABLE carries every clause (a big table is rebuilt once)', count($db->alters), 1);
t('it lists the four columns and the two keys', substr_count($db->alters[0], 'ADD COLUMN') === 4 && substr_count($db->alters[0], 'ADD KEY') === 2, true);
t('old entries read entry_status 0 and starred 0', $db->get_col('SELECT entry_status || starred FROM wp_emsfb_msg_'), array('00', '00', '00', '00'));
t('Entries::ready() after the upgrade', \Emsfb\Entries::ready(), true);
t('the backfill is one single event, not run inside the upgrade', count($GLOBALS['efb_cron']) === 1 && isset($GLOBALS['efb_cron']['efb_entries_stats_backfill']) && (int) $db->get_var('SELECT COUNT(*) FROM wp_emsfb_stats_') === 0, true);
t('the backfill state: up to the last entry that existed (msg_id 4)', $GLOBALS['opts']['emsfb_stats_bf']['max'], 4);

$db->alters = array();
$second = \Emsfb\Install::upgrade_f14_schema();
t('second run applies nothing', $second, array());
t('second run issues no ALTER', $db->alters, array());

/* ------------------------------------------------------------------ the fallback: no multi-clause ALTER */
$db2 = $GLOBALS['wpdb'] = new EfbEntriesTestDb();
$db2->refuse_multi = true;
\Emsfb\Entries::reset_ready();
$r2 = \Emsfb\Entries::upgrade_msg_table();
t('a server that refuses the combined statement: one clause at a time, every part applied', count($r2) === 6 && count($db2->alters) === 7, true);
t('then the columns exist', count($db2->get_results("SHOW COLUMNS FROM wp_emsfb_msg_ LIKE 'edited_at'")), 1);

/* ------------------------------------------------------------------ the backfill */
$GLOBALS['wpdb'] = $db;
\Emsfb\Entries::reset_ready();
$db->pdo->exec("INSERT INTO wp_emsfb_msg_ (form_id, track, ip, content, date, read_) VALUES (3, 'E', '', '[]', '2026-11-02 12:00:00', 0)"); // after the upgrade: bump() counts it, not the backfill
$db->pdo->exec("INSERT INTO wp_emsfb_stts_ (sid, fid, status) VALUES ('s1', 3, 'visit'), ('s2', 3, 'send'), ('s3', 0, 'visit'), ('s4', 4, 'admin')");
$db->pdo->exec("ALTER TABLE wp_emsfb_stts_ ADD COLUMN date TEXT");
$db->pdo->exec("UPDATE wp_emsfb_stts_ SET date = '2026-11-02 08:00:00'");
$db->pdo->exec("INSERT INTO wp_emsfb_stts_ (sid, fid, status, date) VALUES ('s5', 3, 'visit', '2999-01-01 00:00:00')"); // after the upgrade time
$GLOBALS['efb_cron'] = array();
$s = \Emsfb\Entries::run_backfill(2);
t('batch 1 (msg_id 1-2): not done, rescheduled', $s['cursor'] === 2 && $s['done'] === 0 && count($GLOBALS['efb_cron']) === 1, true);
$s = \Emsfb\Entries::run_backfill(2);
t('batch 2 (msg_id 3-4) and the views: done', $s['cursor'] === 4 && $s['done'] === 1, true);
$stats = $db->get_results('SELECT form_id, day, views, submits, spam FROM wp_emsfb_stats_ ORDER BY form_id, day', ARRAY_A);
t('submits per form and day (entry 5 came after the upgrade: not counted twice)', array_map(function ($r) { return $r['form_id'] . '@' . $r['day'] . '=' . $r['submits']; }, $stats), array('3@2026-11-01=2', '3@2026-11-02=1', '4@2026-11-02=1'));
t('views: visit + send rows of form 3 before the upgrade (fid 0 and admin rows skipped)', (int) $db->get_var("SELECT views FROM wp_emsfb_stats_ WHERE form_id = 3 AND day = '2026-11-02'"), 2);
t('a run after done changes nothing', \Emsfb\Entries::run_backfill(2)['done'], 1);
t('schedule_backfill() a second time: no new state', \Emsfb\Entries::schedule_backfill(), false);

/* ------------------------------------------------------------------ bump() */
t('bump creates the day row', \Emsfb\Entries::bump(7, 'views', 1, '2026-11-14'), true);
\Emsfb\Entries::bump(7, 'views', 1, '2026-11-14');
\Emsfb\Entries::bump(7, 'spam', 1, '2026-11-14');
t('one row per form and day (the key refuses a second)', (int) $db->get_var("SELECT COUNT(*) FROM wp_emsfb_stats_ WHERE form_id = 7"), 1);
t('views 2, spam 1', array_map('intval', $db->get_row("SELECT views, spam FROM wp_emsfb_stats_ WHERE form_id = 7", ARRAY_A)), array('views' => 2, 'spam' => 1));
\Emsfb\Entries::bump(7, 'spam', -3, '2026-11-14');
t('never below 0', (int) $db->get_var("SELECT spam FROM wp_emsfb_stats_ WHERE form_id = 7"), 0);
t('an unknown column is refused (no SQL)', \Emsfb\Entries::bump(7, 'views`=0;--', 1), false);
t('form 0 is refused', \Emsfb\Entries::bump(0, 'views', 1), false);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
