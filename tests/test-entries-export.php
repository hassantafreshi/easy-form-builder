<?php
/**
 * F14-H: the server export (Entries::export_args / export_ids / export_scan / export_columns / export_rows,
 * AJAX efb_entries_export_check_Emsfb, admin-post efb_export_entries) on the SQLite stand-in.
 *
 *  - columns by field id: a form with 150 fields gives 150 answer columns (no 100-column cut); two fields named
 *    "Notes" are two columns; a field the form no longer has, held by an old entry, is "Phone (deleted)";
 *  - F09 rows: "Travellers (count)", "Travellers › Name #1", "… #2" for as many rows as the entries hold;
 *  - F05: labels or stored values (ov), "Other: text", a ranking "A > B"; several choices in one cell
 *    (" | ") or one column per choice; F04 numbers raw (a number in XLSX); F13 "Removed for privacy";
 *    F07 a private file as a signed link; signatures as the admin link;
 *  - entry details: code, date, status, read state, star, IP, notes, last edit; payment amount / status / id;
 *  - the export of the list's filter equals the list's ids; "selected" exports those ids only, of this form;
 *  - CSV cells are formula-safe ('=…'), the whole file is UTF-8 with BOM and CRLF;
 *  - 20,000 entries are read 500 at a time (40 content queries), with little memory;
 *  - more than efb_export_sync_max → efb_export_too_many; XLSX and field conditions need Free Plus;
 *  - admin-post refuses a missing / wrong nonce and a user without the entries capability.
 *
 * Run: php tests/test-entries-export.php
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
if (!function_exists('check_admin_referer')) { function check_admin_referer($a) { return !empty($GLOBALS['efb_admin_nonce_ok']); } }
if (!function_exists('wp_die')) { function wp_die($m = '', $t = '', $a = array()) { throw new EfbJsonExit(array('die' => $m, 'status' => isset($a['response']) ? $a['response'] : 500)); } }
if (!function_exists('esc_html')) { function esc_html($s) { return htmlspecialchars((string) $s); } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'https://site.example/wp-admin/' . $p; } }
if (!function_exists('efb_plan_at_least_efb')) { function efb_plan_at_least_efb($need) { $rank = array('free' => 0, 'free_plus' => 1, 'pro' => 2); return $rank[$GLOBALS['efb_plan']] >= $rank[$need]; } }
$GLOBALS['efb_plan'] = 'free_plus';
require_once __DIR__ . '/../includes/class-Emsfb-privacy.php';
require_once __DIR__ . '/../includes/class-Emsfb-upload-guard.php';
require_once __DIR__ . '/../includes/class-Emsfb-uploads.php';
require_once __DIR__ . '/../includes/admin/class-Emsfb-entries.php';
use Emsfb\Entries;

$db = $GLOBALS['wpdb'] = new EfbEntriesTestDb();
$db->create_f14_tables();
Entries::reset_ready();
Entries::register();
$GLOBALS['efb_uid'] = 1;

/* ------------------------------------------------------------------ a form of 150 fields and more */
$f = array(array('type' => 'form', 'formName' => 'Big form'));
for ($i = 1; $i <= 150; $i++) $f[] = array('id_' => 'f' . $i, 'type' => 'text', 'name' => 'Field ' . $i, 'step' => 1, 'amount' => $i);
$f[] = array('id_' => 'n1', 'type' => 'text', 'name' => 'Notes', 'step' => 2, 'amount' => 1);
$f[] = array('id_' => 'n2', 'type' => 'textarea', 'name' => 'Notes', 'step' => 2, 'amount' => 2);
$f[] = array('id_' => 'ck', 'type' => 'checkbox', 'name' => 'Pets', 'step' => 2, 'amount' => 3);
$f[] = array('id_' => 'o1', 'type' => 'option', 'parent' => 'ck', 'value' => 'Cat');
$f[] = array('id_' => 'o2', 'type' => 'option', 'parent' => 'ck', 'value' => 'Bird');
$f[] = array('id_' => 'ms', 'type' => 'multiselect', 'name' => 'Langs', 'step' => 2, 'amount' => 4);
$f[] = array('id_' => 'sz', 'type' => 'radio', 'name' => 'Size', 'step' => 2, 'amount' => 5);
$f[] = array('id_' => 'rk', 'type' => 'ranking', 'name' => 'Rank', 'step' => 2, 'amount' => 6);
$f[] = array('id_' => 'num', 'type' => 'number', 'name' => 'Count', 'step' => 2, 'amount' => 7);
$f[] = array('id_' => 'g', 'type' => 'repeater', 'name' => 'Travellers', 'step' => 2, 'amount' => 8);
$f[] = array('id_' => 'gn', 'type' => 'text', 'name' => 'Name', 'group' => 'g', 'step' => 2, 'amount' => 9);
$f[] = array('id_' => 'sig', 'type' => 'esign', 'name' => 'Signature', 'step' => 2, 'amount' => 10);
$f[] = array('id_' => 'up', 'type' => 'file', 'name' => 'Passport', 'step' => 2, 'amount' => 11);
$fid = $db->add_form($f);
$rows1 = array();
for ($i = 1; $i <= 150; $i++) $rows1[] = array('id_' => 'f' . $i, 'type' => 'text', 'name' => 'Field ' . $i, 'value' => 'v' . $i);
$rows1 = array_merge($rows1, array(
	array('id_' => 'n1', 'type' => 'text', 'name' => 'Notes', 'value' => 'first notes'),
	array('id_' => 'n2', 'type' => 'textarea', 'name' => 'Notes', 'value' => 'second@n#line'),
	array('id_' => 'ck', 'id_ob' => 'o1', 'type' => 'checkbox', 'name' => 'Pets', 'value' => 'Cat', 'ov' => 'cat'),
	array('id_' => 'ck', 'id_ob' => 'o2', 'type' => 'checkbox', 'name' => 'Pets', 'value' => 'Bird', 'ov' => 'bird'),
	array('id_' => 'ms', 'type' => 'multiselect', 'name' => 'Langs', 'value' => 'PHP@efb!Go@efb!'),
	array('id_' => 'sz', 'type' => 'radio', 'name' => 'Size', 'value' => 'Huge', 'other' => '1'),
	array('id_' => 'rk', 'type' => 'ranking', 'name' => 'Rank', 'value' => 'A@efb!B@efb!', 'ov' => 'a@efb!b@efb!'),
	array('id_' => 'num', 'type' => 'number', 'name' => 'Count', 'value' => '12.5', 'disp' => '12.50 $'),
	array('id_' => 'g', 'type' => 'repeater', 'name' => 'Travellers', 'value' => '2'),
	array('id_' => 'gn', 'type' => 'text', 'name' => 'Name', 'value' => 'Sara', 'rg' => 'g', 'ri' => '0'),
	array('id_' => 'gn', 'type' => 'text', 'name' => 'Name', 'value' => '=HYPERLINK("x")', 'rg' => 'g', 'ri' => '1'),
	array('id_' => 'sig', 'type' => 'esign', 'name' => 'Signature', 'value' => 'data:image/png;base64,AAAA'),
	array('id_' => 'up', 'type' => 'file', 'name' => 'Passport', 'value' => '@file@', 'url' => 'https://site.example/wp-content/uploads/efb-private/2026/11/efb-PLG-261114-abcdefabcdef.pdf'),
	array('id_' => 'gone', 'type' => 'text', 'name' => 'Phone', 'value' => '+98912'),
	array('type' => 'w_link', 'value' => 'https://site.example/'),
));
$e1 = $db->add_entry($fid, $rows1, '2026-11-01 10:00:00', array('starred' => 1, 'track' => 'T-ONE'));
$e2 = $db->add_entry($fid, array(array('id_' => 'f1', 'type' => 'text', 'name' => 'Field 1', 'value' => '@prv@'), array('id_' => 'n1', 'type' => 'text', 'name' => 'Notes', 'value' => '-5')), '2026-11-02 10:00:00', array('read_' => 4, 'edited_at' => '2026-11-03 09:00:00'));
$e3 = $db->add_entry($fid, array(array('id_' => 'f1', 'type' => 'text', 'name' => 'Field 1', 'value' => 'spam')), '2026-11-03 10:00:00', array('entry_status' => 1));
$db->insert('wp_emsfb_msg_notes_', array('msg_id' => $e1, 'user_id' => 1, 'note' => 'called', 'date' => '2026-11-04 00:00:00'));

$collect = function ($in) use ($fid) {
	$a = Entries::export_args(array_merge(array('form_id' => $fid), $in));
	$ids = Entries::export_ids($a);
	$out = array();
	Entries::export_rows($a, $ids, function ($cells) use (&$out) { $out[] = $cells; });
	return $out;
};
$x = $collect(array());
$h = $x[0];
$col = function ($name) use ($h) { return array_search($name, $h, true); };
t('default columns: code and date first', array($h[0], $h[1]), array('Confirmation Code', 'Date'));
t('150 fields → 150 answer columns, the last one kept (no 100-column cut)', count(array_filter($h, function ($c) { return strpos($c, 'Field ') === 0; })) === 150 && $x[1][$col('Field 150')] === 'v150', true);
t('two fields named "Notes" are two columns with their own answers', array(count(array_keys($h, 'Notes', true)), $x[1][array_keys($h, 'Notes', true)[0]], $x[1][array_keys($h, 'Notes', true)[1]]), array(2, 'first notes', "second@n#line"));
t('the deleted field of an old entry: "Phone (deleted)"', $x[1][$col('Phone (deleted)')], '+98912');
t('F09: "Travellers (count)", "Travellers › Name #1", "… #2"', array($x[1][$col('Travellers (count)')], $x[1][$col('Travellers › Name #1')], $x[1][$col('Travellers › Name #2')]), array('2', 'Sara', '=HYPERLINK("x")'));
t('F05: checkbox labels in one cell; multiselect; Other; ranking', array($x[1][$col('Pets')], $x[1][$col('Langs')], $x[1][$col('Size')], $x[1][$col('Rank')]), array('Cat | Bird', 'PHP | Go', 'Other: Huge', 'A > B'));
t('F04: a number exported raw (not the display text)', $x[1][$col('Count')], '12.5');
t('F07: a private file as a signed link; a signature as the admin link', array(strpos($x[1][$col('Passport')], 'files/signed') !== false && strpos($x[1][$col('Passport')], 'sig=') !== false, $x[1][$col('Signature')]), array(true, 'https://site.example/wp-admin/admin-ajax.php?action=efb_esign_image&msg=' . $e1 . '&field=sig'));
t('F13: "@prv@" is "Removed for privacy" (in the CSV cell)', \Emsfb\Entries::csv_cell($x[2][$col('Field 1')]), 'Removed for privacy');
t('the spam entry is not in "All" (2 rows)', count($x) - 1, 2);
$v = $collect(array('opt_val' => 'value', 'split_multi' => '1', 'cols' => json_encode(array('ck', 'rk', '_status', '_read', '_star', '_ip', '_notes', '_edited'))));
t('stored values and one column per choice: "Pets: Cat" / "Pets: Bird" = 1, ranking a > b', array($v[0][0], $v[0][1], $v[1][0], $v[1][1], $v[1][2]), array('Pets: Cat', 'Pets: Bird', '1', '1', 'a > b'));
t('entry details: status, read state, star, IP, notes, last edit', array_slice($v[1], 3), array('Active', 'new', '1', '203.0.113.5', 'called', ''));
t('… of the second entry: closed, edited', array($v[2][4], $v[2][8]), array('closed', '2026-11-03 09:00:00'));
$s = $collect(array('tab' => 'spam', 'cols' => json_encode(array('_track', 'f1'))));
t('the Spam tab exports the spam entry only', array(count($s) - 1, $s[1][1]), array(1, 'spam'));
$i = $collect(array('ids' => json_encode(array($e2, 999999)), 'cols' => json_encode(array('n1'))));
t('"selected": only those ids, of this form', array(count($i) - 1, $i[1][0]), array(1, '-5'));
$csv = implode("\r\n", array_map(array('Emsfb\\Entries', 'csv_line'), $x));
t('CSV: the row-2 name "=HYPERLINK(...)" is written as text (\'=)', strpos($csv, "\"'=HYPERLINK(\"\"x\"\")\"") !== false, true);
t('CSV: "-5" stays a number', strpos(implode("\r\n", array_map(array('Emsfb\\Entries', 'csv_line'), $i)), "\r\n-5") !== false, true);

/* ------------------------------------------------------------------ big set: batches and memory */
$big = $db->add_form(array(array('type' => 'form'), array('id_' => 'a', 'type' => 'text', 'name' => 'A', 'step' => 1, 'amount' => 1)));
$db->pdo->beginTransaction();
$st = $db->pdo->prepare("INSERT INTO wp_emsfb_msg_ (form_id, track, ip, content, date, read_) VALUES (?, ?, '', ?, '2026-10-01 00:00:00', 1)");
for ($k = 0; $k < 20000; $k++) $st->execute(array($big, 'B' . $k, str_replace('"', '\\"', json_encode(array(array('id_' => 'a', 'type' => 'text', 'name' => 'A', 'value' => 'value ' . $k . ' ' . str_repeat('x', 200)))))));
$db->pdo->commit();
$a = Entries::export_args(array('form_id' => $big));
$ids = Entries::export_ids($a);
$db->queries = array();
$m0 = memory_get_usage();
$n = 0; $bytes = 0;
Entries::export_rows($a, $ids, function ($cells) use (&$n, &$bytes) { $n++; $bytes += strlen(\Emsfb\Entries::csv_line($cells)); });
$peak = memory_get_peak_usage() - $m0;
$content_q = count(array_filter($db->queries, function ($q) { return strpos($q, 'SELECT msg_id, form_id, track, ip, content') === 0; }));
echo '  20,000 entries: ' . round($bytes / 1048576, 1) . ' MB of CSV, ' . $content_q . ' content queries, peak +' . round($peak / 1048576, 1) . " MB\n";
t('20,000 entries: header + 20,000 rows', $n, 20001);
t('read 500 at a time: 40 content queries for the rows (+40 for the column scan)', $content_q, 80);
t('memory stays low (less than 64 MB more)', $peak < 64 * 1048576, true);

/* ------------------------------------------------------------------ limits and plans */
$GLOBALS['efb_filters']['efb_export_sync_max'][] = function () { return 5; };
$ajax = function ($fn, $post) { $_POST = $post; try { Entries::$fn(); } catch (EfbJsonExit $x) { return $x->data; } return null; };
$GLOBALS['efb_nonce_ok'] = true; $GLOBALS['efb_caps'] = array('manage_options' => true);
$r = $ajax('ajax_export_check', array('form_id' => $big, 'format' => 'csv'));
t('more than efb_export_sync_max → efb_export_too_many, "Choose a shorter date range."', array($r['code'], $r['m']), array('efb_export_too_many', 'Too many entries. Choose a shorter date range.'));
$r = $ajax('ajax_export_check', array('form_id' => $fid, 'format' => 'csv'));
t('within the limit: the count', array($r['success'], $r['count']), array(true, 2));
$GLOBALS['efb_plan'] = 'free';
t('Free: XLSX needs Free Plus', $ajax('ajax_export_check', array('form_id' => $fid, 'format' => 'xlsx'))['feature'], 'setting:entries_xlsx');
t('Free: CSV with chosen columns and filters is free', $ajax('ajax_export_check', array('form_id' => $fid, 'format' => 'csv', 'cols' => json_encode(array('_track')), 'q' => 'v1'))['success'], true);
$GLOBALS['efb_plan'] = 'free_plus';
unset($GLOBALS['efb_filters']['efb_export_sync_max']);
$_POST = array('form_id' => $fid, 'format' => 'csv');
$GLOBALS['efb_admin_nonce_ok'] = false;
try { Entries::admin_post_export(); $r = null; } catch (EfbJsonExit $x) { $r = $x->data; }
t('admin-post without a valid efb_export nonce → 403', $r['status'] ?? null, 403);
$GLOBALS['efb_admin_nonce_ok'] = true; $GLOBALS['efb_caps'] = array('read' => true);
try { Entries::admin_post_export(); $r = null; } catch (EfbJsonExit $x) { $r = $x->data; }
t('admin-post for a user without the entries capability → 403', $r['status'] ?? null, 403);
t('no history row written for refused exports', $db->count('wp_emsfb_msg_log_', "action = 'export'"), 0);
$src = file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-entries.php');
t('admin_post_efb_export_entries is registered; CSV streams to php://output with a BOM', strpos($src, "add_action('admin_post_efb_export_entries'") !== false && strpos($src, "fopen('php://output', 'wb')") !== false && strpos($src, '"\xEF\xBB\xBF"') !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
