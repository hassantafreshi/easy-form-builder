<?php
/**
 * F14-B: the list on the server (Entries::args / build_where / post_match / counts / query, AJAX
 * efb_entries_query_Emsfb) on the SQLite stand-in with stored content as the plugin writes it
 * (quotes escaped, @efb@dq#, "/" as \/).
 *
 *  - parameters: per_page allow-list, tab and sort allow-lists (an injected sort or tab falls back), dates checked
 *    with checkdate (2026-02-30 dropped), Persian digits read, q cut at 100, at most 3 conditions with ids of
 *    [A-Za-z0-9_-] and ops of the allow-list (an injected id is dropped, never reaches SQL);
 *  - every value is a placeholder: the recorded SQL holds no user text;
 *  - tabs and their counts (one query), spam / trash apart, read_ 2 / 4 untouched;
 *  - dates: from 00:00:00, to 23:59:59 (site time, the stored format);
 *  - search: exact tracking code, answers, replies, notes; % and _ literal; a quote and a "/" as stored;
 *  - pages: no entry repeated or skipped when several share the same second (order date, msg_id);
 *  - field conditions: eq on one choice of a checkbox (option rows), multiselect @efb!, case and Persian digits,
 *    has, empty (an entry without the field matches), filled, repeater rows (any row), a stored value (ov);
 *    the scan stops at 5000 rows with total_is_estimate;
 *  - AJAX: no nonce → efb_forbidden, no capability → efb_forbidden, no schema → efb_db_upgrade;
 *  - the old get_messages_id_Emsfb: prepared, without spam / trash, and die() after its error.
 *
 * Run: php tests/test-entries-query.php
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
if (!function_exists('efb_plan_at_least_efb')) { function efb_plan_at_least_efb($need) { return true; } }
require_once __DIR__ . '/../includes/class-Emsfb-privacy.php';
require_once __DIR__ . '/../includes/admin/class-Emsfb-entries.php';
use Emsfb\Entries;

$db = $GLOBALS['wpdb'] = new EfbEntriesTestDb();
$db->create_f14_tables();
Entries::reset_ready();

/** Stores rows the way efb_encode_rows_for_storage_efb() does. */
function store($rows) {
	array_walk_recursive($rows, function (&$v) { if (is_string($v)) $v = str_replace('"', '@efb@dq#', $v); });
	return str_replace('"', '\\"', json_encode($rows, JSON_UNESCAPED_UNICODE));
}
function add($form, $rows, $date, $extra = array()) {
	global $db;
	$db->insert('wp_emsfb_msg_', array_merge(array('form_id' => $form, 'track' => 'T' . (count($db->queries) + mt_rand(100, 999999)), 'ip' => '203.0.113.9', 'content' => store($rows), 'date' => $date, 'read_' => 1), $extra));
	return $db->insert_id;
}
$txt = function ($id, $v, $extra = array()) { return array_merge(array('id_' => $id, 'name' => $id, 'type' => 'text', 'value' => $v, 'amount' => 1), $extra); };

/* ------------------------------------------------------------------ parameters */
$a = Entries::args(array('form_id' => '5', 'per_page' => '1000', 'tab' => "trash' OR 1=1", 'sort' => 'date_desc; DROP TABLE x', 'from' => '2026-02-30', 'to' => '۲۰۲۶-۱۱-۱۴', 'page' => '۳', 'q' => str_repeat('x', 150)));
t('per_page outside 20/50/100 → 20', $a['per_page'], 20);
t('an injected tab → all', $a['tab'], 'all');
t('an injected sort → date_desc', $a['sort'], 'date_desc');
t('2026-02-30 is not a date: dropped', $a['from'], '');
t('a Persian-digit date is read', $a['to'], '2026-11-14');
t('a Persian-digit page is read', $a['page'], 3);
t('q cut at 100 characters', strlen($a['q']), 100);
$a = Entries::args(array('conds' => json_encode(array(
	array('id' => "x' OR '1'='1", 'op' => 'eq', 'v' => 'a'),
	array('id' => 'fld1', 'op' => 'regexp', 'v' => 'a'),
	array('id' => 'fld1', 'op' => 'eq', 'v' => ''),
	array('id' => 'f1', 'op' => 'eq', 'v' => 'a'), array('id' => 'f2', 'op' => 'has', 'v' => 'b'), array('id' => 'f3', 'op' => 'empty'), array('id' => 'f4', 'op' => 'filled'),
))));
t('conditions: an injected id, an unknown op and an empty eq value are dropped; at most three kept', array_column($a['conds'], 'id'), array('f1', 'f2', 'f3'));

/* ------------------------------------------------------------------ fixtures */
$F = 9;
$e1 = add($F, array($txt('nm', 'Ali "the 1st" 100% sure'), $txt('mail', 'a/b@example.com', array('type' => 'email'))), '2026-11-01 00:00:00', array('track' => 'TRK0001', 'read_' => 0));
$e2 = add($F, array($txt('nm', 'Sara 100 sure'), array('id_' => 'chk', 'id_ob' => 'o1', 'name' => 'Pets', 'type' => 'checkbox', 'value' => 'Cat'), array('id_' => 'chk', 'id_ob' => 'o2', 'name' => 'Pets', 'type' => 'checkbox', 'value' => 'Bird')), '2026-11-02 23:59:59', array('read_' => 3));
$e3 = add($F, array($txt('nm', 'Reza_x'), array('id_' => 'ms', 'name' => 'Langs', 'type' => 'multiselect', 'value' => 'PHP@efb!Go@efb!')), '2026-11-03 00:00:00', array('starred' => 1));
$e4 = add($F, array($txt('nm', 'Spam bot'), $txt('n', '۱۲۳', array('type' => 'number'))), '2026-11-03 10:00:00', array('entry_status' => 1));
$e5 = add($F, array($txt('nm', 'Trashed'), array('id_' => 'rad', 'name' => 'Size', 'type' => 'radio', 'value' => 'Large', 'ov' => 'L')), '2026-11-03 10:00:00', array('entry_status' => 2, 'read_' => 0));
$e6 = add($F, array($txt('nm', 'Pending'), array('id_' => 'g', 'type' => 'repeater', 'value' => '2'), $txt('who', 'Mina', array('rg' => 'g', 'ri' => '0')), $txt('who', 'Omid', array('rg' => 'g', 'ri' => '1'))), '2026-11-04 08:00:00', array('read_' => 2));
$e7 = add($F, array($txt('nm', 'Closed'), array('id_' => 'rad', 'name' => 'Size', 'type' => 'radio', 'value' => 'Large', 'ov' => 'L'), $txt('n', '123', array('type' => 'number'))), '2026-11-04 08:00:00', array('read_' => 4));
add(10, array($txt('nm', 'Other form 100% sure')), '2026-11-04 08:00:00');
$db->insert('wp_emsfb_rsp_', array('msg_id' => $e2, 'ip' => '', 'content' => store(array($txt('message', 'reply about wombats'))), 'date' => '2026-11-05 00:00:00'));
$db->insert('wp_emsfb_msg_notes_', array('msg_id' => $e3, 'user_id' => 1, 'note' => 'called back about penguins', 'date' => '2026-11-05 00:00:00'));
$db->insert('wp_emsfb_msg_notes_', array('msg_id' => $e3, 'user_id' => 1, 'note' => 'second', 'date' => '2026-11-05 00:01:00'));

$ids = function ($r) { return array_map(function ($x) { return $x['msg_id']; }, $r['rows']); };
$q = function ($args, $opts = array()) use ($F) { return Entries::query(array_merge(array('form_id' => $F), $args), $opts); };

/* ------------------------------------------------------------------ tabs and counts */
t('counts of every tab in one query', Entries::counts($F), array('all' => 5, 'unread' => 2, 'starred' => 1, 'pending' => 1, 'closed' => 1, 'spam' => 1, 'trash' => 1));
t('all: active only, newest first (same second: higher msg_id first)', $ids($q(array())), array($e7, $e6, $e3, $e2, $e1));
t('unread: read_ 0 or 3', $ids($q(array('tab' => 'unread'))), array($e2, $e1));
t('starred', $ids($q(array('tab' => 'starred'))), array($e3));
t('pending payment: read_ 2', $ids($q(array('tab' => 'pending'))), array($e6));
t('closed: read_ 4', $ids($q(array('tab' => 'closed'))), array($e7));
t('spam', $ids($q(array('tab' => 'spam'))), array($e4));
t('trash (read_ 0 there does not make it unread)', $ids($q(array('tab' => 'trash'))), array($e5));
t('oldest first', $ids($q(array('sort' => 'date_asc'))), array($e1, $e2, $e3, $e6, $e7));

/* ------------------------------------------------------------------ dates */
t('from 2026-11-03 includes 00:00:00', $ids($q(array('from' => '2026-11-03'))), array($e7, $e6, $e3));
t('to 2026-11-02 includes 23:59:59', $ids($q(array('to' => '2026-11-02'))), array($e2, $e1));
t('a one-day range', $ids($q(array('from' => '2026-11-02', 'to' => '2026-11-02'))), array($e2));

/* ------------------------------------------------------------------ search */
t('exact tracking code', $ids($q(array('q' => 'TRK0001'))), array($e1));
t('"100%" finds the literal percent only (esc_like), not "100 sure"', $ids($q(array('q' => '100%'))), array($e1));
t('"o_x" is literal: no match ("_" is not a wildcard)', $ids($q(array('q' => 'a_x'))), array($e3));
t('"Rez_" finds nothing: "_" literal', $ids($q(array('q' => 'Rez_'))), array());
t('a quote as typed finds the stored @efb@dq# form', $ids($q(array('q' => '"the 1st"'))), array($e1));
t('a "/" as typed finds the stored \/ form', $ids($q(array('q' => 'a/b@'))), array($e1));
t('the text of a reply', $ids($q(array('q' => 'wombats'))), array($e2));
t('the text of a note', $ids($q(array('q' => 'penguins'))), array($e3));
t('search stays in the form (another form holds the same text)', in_array(9999, $ids($q(array('q' => 'Other form'))), true), false);
$db->queries = array();
$q(array('q' => "x' OR '1'='1", 'conds' => json_encode(array(array('id' => 'nm', 'op' => 'has', 'v' => "'; DROP TABLE wp_emsfb_msg_; --")))));
$sql = implode("\n", $db->queries);
t('injected text reaches SQL only as quoted literals (the table is still there)', (int) $db->get_var('SELECT COUNT(*) FROM wp_emsfb_msg_') >= 8 && strpos($sql, "''1''='''") === false, true);

/* ------------------------------------------------------------------ pages */
for ($i = 0; $i < 45; $i++) add(11, array($txt('nm', 'P' . $i)), '2026-10-01 12:00:00');
$seen = array();
for ($p = 1; $p <= 3; $p++) $seen = array_merge($seen, $ids(Entries::query(array('form_id' => 11, 'page' => $p, 'per_page' => 20))));
t('45 entries of the same second over 3 pages: 45 different ids, none twice', count($seen) === 45 && count(array_unique($seen)) === 45, true);
$r = Entries::query(array('form_id' => 11, 'page' => 3, 'per_page' => 20));
t('page 3: 5 rows of total 45', array(count($r['rows']), $r['total']), array(5, 45));

/* ------------------------------------------------------------------ field conditions */
$c = function ($id, $op, $v = '') { return json_encode(array(array('id' => $id, 'op' => $op, 'v' => $v))); };
t('eq on one choice of a checkbox (option rows)', $ids($q(array('conds' => $c('chk', 'eq', 'Bird')))), array($e2));
t('eq is exact: "Bir" does not match', $ids($q(array('conds' => $c('chk', 'eq', 'Bir')))), array());
t('eq ignores case', $ids($q(array('conds' => $c('chk', 'eq', 'cat')))), array($e2));
t('multiselect: eq on one of the @efb! values', $ids($q(array('conds' => $c('ms', 'eq', 'Go')))), array($e3));
t('has', $ids($q(array('conds' => $c('nm', 'has', 'sar')))), array($e2));
t('Persian digits typed match Latin digits stored', $ids($q(array('conds' => $c('n', 'eq', '۱۲۳')))), array($e7));
t('and Persian digits stored match Latin digits typed (spam tab)', $ids($q(array('tab' => 'spam', 'conds' => $c('n', 'eq', '123')))), array($e4));
t('a stored value (ov) matches too', $ids($q(array('tab' => 'trash', 'conds' => $c('rad', 'eq', 'L')))), array($e5));
t('empty: entries without the field match', $ids($q(array('conds' => $c('chk', 'empty')))), array($e7, $e6, $e3, $e1));
t('filled', $ids($q(array('conds' => $c('chk', 'filled')))), array($e2));
t('repeater rows: any row', $ids($q(array('conds' => $c('who', 'eq', 'Omid')))), array($e6));
t('two conditions: AND', $ids($q(array('conds' => json_encode(array(array('id' => 'nm', 'op' => 'has', 'v' => 'a'), array('id' => 'chk', 'op' => 'filled')))))), array($e2));
$db->pdo->beginTransaction();
for ($i = 0; $i < 5200; $i++) $db->pdo->exec("INSERT INTO wp_emsfb_msg_ (form_id, track, ip, content, date, read_) VALUES (12, 'B{$i}', '', " . $db->pdo->quote(store(array($txt('nm', $i % 1000 === 0 ? 'needle' : 'hay')))) . ", '2026-09-01 00:00:00', 1)");
$db->pdo->commit();
$r = Entries::query(array('form_id' => 12, 'conds' => $c('nm', 'filled')));
t('the scan stops at 5000 rows: total "more than 5000", total_is_estimate', array($r['total'], $r['total_is_estimate'], count($r['rows'])), array(5000, true, 20));
$r = Entries::query(array('form_id' => 12, 'conds' => $c('nm', 'eq', 'needle')));
t('a rare value: the SQL pre-filter narrows first, every match found, exact total', array($r['total'], $r['total_is_estimate']), array(6, false));

/* ------------------------------------------------------------------ rows */
$r = $q(array('q' => 'penguins'));
t('a row: notes_count from one GROUP BY, starred, date_iso raw, no IP unless asked', array($r['rows'][0]['notes_count'], $r['rows'][0]['starred'], $r['rows'][0]['date_iso'], isset($r['rows'][0]['ip'])), array(2, 1, '2026-11-03 00:00:00', false));
t('the IP only when its column is chosen', $q(array('q' => 'penguins'), array('ip' => true))['rows'][0]['ip'], '203.0.113.9');

/* ------------------------------------------------------------------ AJAX */
$ajax = function ($post) { $_POST = $post; try { Entries::ajax_query(); } catch (EfbJsonExit $e) { return $e->data; } return null; };
$GLOBALS['efb_nonce_ok'] = false; $GLOBALS['efb_caps'] = array('manage_options' => true);
t('no nonce → efb_forbidden', $ajax(array('form_id' => $F))['code'], 'efb_forbidden');
$GLOBALS['efb_nonce_ok'] = true; $GLOBALS['efb_caps'] = array('read' => true);
t('a user without the entries capability → efb_forbidden', $ajax(array('form_id' => $F))['code'], 'efb_forbidden');
$GLOBALS['efb_caps'] = array('Emsfb' => true);
$res = $ajax(array('form_id' => (string) $F, 'tab' => 'all', 'per_page' => '50', 'conds' => $c('chk', 'eq', 'Bird')));
t('the plugin role (Emsfb) may list: rows, counts, nonce of the response box', array($res['success'], count($res['rows']), $res['counts']['spam'], $res['nonce_msg']), array(true, 1, 1, 'nonce-efb9'));
t('the page size is remembered (user meta efb_pp)', $GLOBALS['efb_umeta'][1]['efb_pp'], '50');
$db2 = $GLOBALS['wpdb'] = new EfbEntriesTestDb(); // a site before the upgrade
Entries::reset_ready();
t('before the DB 1.10 upgrade → efb_db_upgrade (the page keeps the old list)', $ajax(array('form_id' => $F))['code'], 'efb_db_upgrade');
$GLOBALS['wpdb'] = $db;
Entries::reset_ready();

/* ------------------------------------------------------------------ the old action and the global search */
$admin = file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-admin.php');
$fn = substr($admin, strpos($admin, 'public function get_messages_id_Emsfb'), 2500);
t('get_messages_id_Emsfb: prepared (%d), no interpolated id', strpos($fn, "WHERE form_id = %d") !== false && strpos($fn, "form_id = '\$id'") === false, true);
t('get_messages_id_Emsfb: without spam and trash', strpos($fn, 'Entries::active_sql()') !== false, true);
t('get_messages_id_Emsfb: die() after its error answer', (bool) preg_match('/wp_send_json_success\(\$response, 200\);\s*die\(\);/', $fn), true);
$gs = substr($admin, strpos($admin, 'public function get_ajax_track_admin'), 2500);
t('global search: % and _ literal (esc_like)', strpos($gs, 'esc_like($id)') !== false, true);
$pub = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
t('public tracking-code page: spam / trash are "not found"', (bool) preg_match('/WHERE track = %s" \. \(class_exists\(.*Entries.*active_sql\(\)/', $pub), true);
t('survey results: active entries only', (bool) preg_match('/SELECT content FROM \$table_name WHERE form_id = %d" \. \(class_exists\(.*Entries.*active_sql\(\)/', $pub), true);
$panel = file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-panel.php');
t('panel unread count: active only', strpos($panel, '(read_=0 OR read_=3){$active}') !== false, true);
t('active_sql() on the upgraded table', Entries::active_sql('m'), ' AND m.entry_status = 0');

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
