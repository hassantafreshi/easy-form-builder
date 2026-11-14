<?php
/**
 * F14-D: internal notes and the history of an entry (Entries::note_add / note_delete / notes_of / log_of, AJAX
 * efb_entry_notes_Emsfb and the viewer's efb_entry_get_Emsfb).
 *
 *  - a note is plain text: HTML is refused (not silently stripped), at most 5000 characters (counted in characters,
 *    so Persian text is not cut short), an empty note and a note on a missing entry are refused;
 *  - only its author or an administrator (manage_options) deletes a note; the plugin role (Emsfb) deletes its own;
 *    the deletion is in the history without the text;
 *  - the notes are never where a visitor can see them: not in emsfb_rsp_ (the response box), not read by the
 *    public tracking / reply routes, not in the WordPress personal-data export (spec §5.2);
 *  - the history: each row with its author and, for an edit, the field's current label;
 *  - efb_entry_get_Emsfb answers the entry with its notes and history; nonce and capability checked;
 *  - deleting the entry permanently removes its notes and history (efb_entries_purged).
 *
 * Run: php tests/test-entries-notes.php
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
require_once __DIR__ . '/../includes/class-Emsfb-privacy.php';
require_once __DIR__ . '/../includes/admin/class-Emsfb-entries.php';
use Emsfb\Entries;

$db = $GLOBALS['wpdb'] = new EfbEntriesTestDb();
$db->create_f14_tables();
Entries::reset_ready();
Entries::register();
$fid = $db->add_form(array(array('type' => 'form'), array('id_' => 'nm', 'type' => 'text', 'name' => 'Full name', 'step' => 1, 'amount' => 2)));
$m = $db->add_entry($fid, array(array('id_' => 'nm', 'type' => 'text', 'name' => 'Full name', 'value' => 'Ali')), '2026-11-01 10:00:00');
$GLOBALS['efb_uid'] = 2; $GLOBALS['efb_caps'] = array('Emsfb' => true);

/* ------------------------------------------------------------------ add */
t('HTML is refused, not stripped', Entries::note_add($m, 'call <img src=x onerror=alert(1)>')['code'], 'efb_note_html');
t('a <script> tag too', Entries::note_add($m, "<script>alert(1)</script>")['code'], 'efb_note_html');
t('a plain "<" in text is fine (2 < 3)', is_int(Entries::note_add($m, '2 < 3 and 5 > 4')), true);
t('an empty note is refused', Entries::note_add($m, "  \n ")['code'], 'efb_invalid');
t('5001 characters are refused', Entries::note_add($m, str_repeat('a', 5001))['code'], 'efb_note_long');
t('5000 Persian characters are accepted (characters, not bytes)', is_int(Entries::note_add($m, str_repeat('ش', 5000))), true);
t('a note on a missing entry is refused', Entries::note_add(99999, 'x')['code'], 'efb_not_found');
$own = Entries::note_add($m, "first line\nsecond line");
t('new lines are kept (sanitize_textarea_field)', $db->get_var("SELECT note FROM wp_emsfb_msg_notes_ WHERE note_id = {$own}"), "first line\nsecond line");
$GLOBALS['efb_uid'] = 3;
$other = Entries::note_add($m, 'by another person');

/* ------------------------------------------------------------------ list */
$GLOBALS['efb_uid'] = 2;
$list = Entries::notes_of($m);
t('notes_of: oldest first, author names, can_delete only on one\'s own (Emsfb role)', array(count($list), $list[2]['author'], $list[2]['can_delete'], $list[3]['can_delete']), array(4, 'User 2', true, false));

/* ------------------------------------------------------------------ delete */
t('the plugin role cannot delete another person\'s note', Entries::note_delete($other)['code'], 'efb_forbidden');
t('its author deletes it', Entries::note_delete($own), $m);
$GLOBALS['efb_caps'] = array('manage_options' => true);
t('an administrator deletes anyone\'s note', Entries::note_delete($other), $m);
$log = $db->get_results("SELECT action, old_value, new_value FROM wp_emsfb_msg_log_ WHERE msg_id = {$m}", ARRAY_A);
t('each deletion is in the history, without the note\'s text', array(count($log), $log[0]['action'], $log[0]['old_value'], $log[0]['new_value']), array(2, 'note_del', null, null));
Entries::log($m, 'edit', 'nm', 'Ali', 'Ali Rezaei', 'typo');
$h = Entries::log_of($m, $fid);
t('log_of: newest first, the field\'s current label, the reason in meta', array($h[0]['action'], $h[0]['field'], $h[0]['old'], $h[0]['new'], $h[0]['meta'], $h[0]['user']), array('edit', 'Full name', 'Ali', 'Ali Rezaei', 'typo', 'User 2'));
Entries::log($m, 'edit', 'nm', str_repeat('x', 70000), 'y');
t('a value over 64 KB is cut', strlen($db->get_var("SELECT old_value FROM wp_emsfb_msg_log_ WHERE msg_id = {$m} ORDER BY log_id DESC LIMIT 1")) <= 65535 + 3, true);

/* ------------------------------------------------------------------ never public */
t('notes are not replies: nothing in emsfb_rsp_', $db->count('wp_emsfb_rsp_'), 0);
$pub = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
t('the public class (tracking page, response box, replies) never reads the notes table', strpos($pub, 'emsfb_msg_notes_') === false && strpos($pub, 'Entries::NOTES') === false && strpos($pub, 'notes_of') === false, true);
$prv = file_get_contents(__DIR__ . '/../includes/class-Emsfb-privacy.php');
t('the WordPress personal-data exporter does not include notes (spec §5.2)', strpos($prv, 'emsfb_msg_notes_') === false && strpos($prv, 'notes_of') === false, true);

/* ------------------------------------------------------------------ AJAX */
$ajax = function ($fn, $post) { $_POST = $post; try { Entries::$fn(); } catch (EfbJsonExit $x) { return $x->data; } return null; };
$GLOBALS['efb_nonce_ok'] = false;
t('notes AJAX without the nonce → efb_forbidden', $ajax('ajax_notes', array('op' => 'list', 'msg_id' => $m))['code'], 'efb_forbidden');
$GLOBALS['efb_nonce_ok'] = true; $GLOBALS['efb_caps'] = array('read' => true);
t('notes AJAX for a Subscriber → efb_forbidden', $ajax('ajax_notes', array('op' => 'add', 'msg_id' => $m, 'note' => 'x'))['code'], 'efb_forbidden');
$GLOBALS['efb_caps'] = array('Emsfb' => true);
$r = $ajax('ajax_notes', array('op' => 'add', 'msg_id' => $m, 'note' => 'from the screen'));
t('notes AJAX add → the notes and the history back', array($r['success'], count($r['notes']), end($r['notes'])['note']), array(true, 3, 'from the screen'));
t('notes AJAX: HTML refused with its message', $ajax('ajax_notes', array('op' => 'add', 'msg_id' => $m, 'note' => '<b>x</b>'))['code'], 'efb_note_html');
t('notes AJAX: an unknown op', $ajax('ajax_notes', array('op' => 'drop', 'msg_id' => $m))['code'], 'efb_invalid');
$g = $ajax('ajax_get', array('msg_id' => $m));
t('efb_entry_get_Emsfb: the entry with its IP, its notes and its history', array($g['success'], $g['entry']['ip'], count($g['notes']), count($g['log']) >= 3, $g['entry']['hash'] === md5($g['entry']['content'])), array(true, '203.0.113.5', 3, true, true));
t('efb_entry_get_Emsfb: a missing entry', $ajax('ajax_get', array('msg_id' => 424242))['code'], 'efb_not_found');

/* ------------------------------------------------------------------ permanent delete */
$db->pdo->exec("UPDATE wp_emsfb_msg_ SET entry_status = 2 WHERE msg_id = {$m}");
Entries::apply_op($fid, 'delete', array($m));
t('deleting the entry permanently removes its notes and its history', array($db->count('wp_emsfb_msg_notes_', "msg_id = {$m}"), $db->count('wp_emsfb_msg_log_', "msg_id = {$m}")), array(0, 0));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
