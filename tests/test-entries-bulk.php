<?php
/**
 * F14-C on the server: star, read / unread, spam, trash, restore, delete permanently (Entries::apply_op), the
 * "all N results" filter, the trash job, the notes and history going with a deleted entry, the settings, the
 * plan gates and the registry entries.
 *
 *  - read takes read_ 0 / 3 to 1 and never touches 2 (pending payment) or 4 (closed); unread takes 1 to 0 only;
 *  - spam / not spam move the spam count of the entry's own day; trash sets trashed_at; restore clears it;
 *  - each change writes one history row (old → new), no address in meta;
 *  - "Delete permanently" works only in the trash and goes through F13's purge: replies, notes and history go;
 *    the F05 / F03 / F11 hooks run (efb_entries_purged, efb_message_deleted); trash and spam alone run none;
 *  - an anonymization deletes the notes and empties the history values;
 *  - the trash job deletes entries trashed more than trash_days ago (0 = never), not newer ones;
 *  - the filter of "select all" (conditions included) gives the same ids as the list;
 *  - AJAX: nonce / capability, an unknown op, field conditions and field columns need Free Plus;
 *  - settings trash_days / ai_entries_access are allow-listed; the registry has the setting:entries_* entries.
 *
 * Run: php tests/test-entries-bulk.php
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
if (!function_exists('get_setting_Emsfb')) { function get_setting_Emsfb($m = 'decoded') { return isset($GLOBALS['efb_settings']) ? (object) $GLOBALS['efb_settings'] : new stdClass(); } }
if (!function_exists('efb_plan_at_least_efb')) { function efb_plan_at_least_efb($need) { $rank = array('free' => 0, 'free_plus' => 1, 'pro' => 2); return $rank[$GLOBALS['efb_plan']] >= $rank[$need]; } }
if (!function_exists('admin_url')) { function admin_url($p = '') { return 'https://site.example/wp-admin/' . $p; } }
require_once __DIR__ . '/../includes/class-Emsfb-privacy.php';
require_once __DIR__ . '/../includes/admin/class-Emsfb-entries.php';
use Emsfb\Entries;

$db = $GLOBALS['wpdb'] = new EfbEntriesTestDb();
$db->create_f14_tables();
Entries::reset_ready();
Entries::register();
$GLOBALS['efb_plan'] = 'free_plus';
$GLOBALS['efb_uid'] = 1;
$GLOBALS['efb_hooks']['efb_message_deleted'][] = function ($id) { $GLOBALS['deleted_hook'][] = $id; };
$uploads = sys_get_temp_dir() . '/efb-f14-up-' . getmypid();
@mkdir($uploads, 0777, true);
efb_privacy_test_wp_stubs($uploads);

$F = 4;
$e = array();
foreach (array(0, 3, 2, 4, 1, 0) as $i => $read) $e[] = $db->add_entry($F, array(array('id_' => 'nm', 'name' => 'Name', 'type' => 'text', 'value' => 'P' . $i)), '2026-11-0' . ($i + 1) . ' 10:00:00', array('read_' => $read));
$st = function ($id) use ($db) { return $db->row('wp_emsfb_msg_', 'msg_id = ' . (int) $id); };

/* ------------------------------------------------------------------ read / unread */
$r = Entries::apply_op($F, 'read', $e);
t('read: only read_ 0 and 3 change (2 pending payment and 4 closed stay)', array($r['done'], array_map(function ($id) use ($st) { return (int) $st($id)['read_']; }, $e)), array(3, array(1, 1, 2, 4, 1, 1)));
$r = Entries::apply_op($F, 'unread', $e);
t('unread: only read_ 1 goes back to 0', array_map(function ($id) use ($st) { return (int) $st($id)['read_']; }, $e), array(0, 0, 2, 4, 0, 0));

/* ------------------------------------------------------------------ star */
Entries::apply_op($F, 'star', array($e[0], $e[1]));
t('star', array((int) $st($e[0])['starred'], (int) $st($e[1])['starred'], (int) $st($e[2])['starred']), array(1, 1, 0));
t('star a second time changes nothing', Entries::apply_op($F, 'star', array($e[0]))['done'], 0);
Entries::apply_op($F, 'unstar', array($e[1]));
t('unstar', (int) $st($e[1])['starred'], 0);

/* ------------------------------------------------------------------ spam and the day's count */
Entries::apply_op($F, 'spam', array($e[0], $e[1]));
t('spam: entry_status 1', array((int) $st($e[0])['entry_status'], (int) $st($e[1])['entry_status']), array(1, 1));
t('the spam count of each entry\'s own day', $db->get_results('SELECT day, spam FROM wp_emsfb_stats_ WHERE form_id = 4 ORDER BY day', ARRAY_A), array(array('day' => '2026-11-01', 'spam' => 1), array('day' => '2026-11-02', 'spam' => 1)));
Entries::apply_op($F, 'unspam', array($e[1]));
t('not spam: back to active, its day\'s count back to 0', array((int) $st($e[1])['entry_status'], (int) $db->get_var("SELECT spam FROM wp_emsfb_stats_ WHERE day = '2026-11-02'")), array(0, 0));

/* ------------------------------------------------------------------ trash / restore */
$GLOBALS['efb_actions_log'] = array();
Entries::apply_op($F, 'trash', array($e[2], $e[3]));
t('trash: entry_status 2 and trashed_at set', array((int) $st($e[2])['entry_status'], strlen((string) $st($e[2])['trashed_at']) === 19), array(2, true));
$purged = array_filter($GLOBALS['efb_actions_log'], function ($a) { return in_array($a[0], array('efb_entries_purged', 'efb_message_deleted'), true); });
t('trash keeps the entry\'s places, stock and unique values: no purge hook runs', count($purged), 0);
t('efb_entry_status_changed for each (add-ons, webhooks)', count(array_filter($GLOBALS['efb_actions_log'], function ($a) { return $a[0] === 'efb_entry_status_changed' && $a[1][3] === 'trash'; })), 2);
Entries::apply_op($F, 'restore', array($e[3]));
t('restore: active again, trashed_at cleared', array((int) $st($e[3])['entry_status'], $st($e[3])['trashed_at']), array(0, null));
t('delete does nothing outside the trash', Entries::apply_op($F, 'delete', array($e[3], $e[4]))['done'], 0);
t('an entry of another form is not touched (form_id is part of the WHERE)', Entries::apply_op(99, 'star', array($e[4]))['done'], 0);

/* ------------------------------------------------------------------ history */
$log = $db->get_results('SELECT msg_id, action, old_value, new_value, meta, user_id FROM wp_emsfb_msg_log_ ORDER BY log_id', ARRAY_A);
$by = function ($id, $action) use ($log) { return array_values(array_map(function ($l) { return $l['old_value'] . '>' . $l['new_value']; }, array_filter($log, function ($l) use ($id, $action) { return (int) $l['msg_id'] === $id && $l['action'] === $action; }))); };
t('history: status changes of entry 3 (active>trash, trash>active)', $by($e[3], 'status'), array('active>trash', 'trash>active'));
t('history: star and read changes, by the current user', array($by($e[0], 'star'), $by($e[0], 'read'), (int) $log[0]['user_id']), array(array('0>1'), array('0>1', '1>0'), 1));

/* ------------------------------------------------------------------ delete permanently, notes and history go */
$db->add_reply($e[2], array(array('id_' => 'message', 'type' => 'text', 'value' => 'a reply')), '2026-11-10 10:00:00');
$db->insert('wp_emsfb_msg_notes_', array('msg_id' => $e[2], 'user_id' => 1, 'note' => 'called', 'date' => '2026-11-10 11:00:00'));
$GLOBALS['efb_actions_log'] = array();
$r = Entries::apply_op($F, 'delete', array($e[2]));
t('delete permanently (from the trash): the entry, its reply, its note and its history are gone', array($r['done'], $db->count('wp_emsfb_msg_', 'msg_id = ' . $e[2]), $db->count('wp_emsfb_rsp_', 'msg_id = ' . $e[2]), $db->count('wp_emsfb_msg_notes_', 'msg_id = ' . $e[2]), $db->count('wp_emsfb_msg_log_', 'msg_id = ' . $e[2])), array(1, 0, 0, 0, 0));
t('through F13\'s purge: efb_entries_purged and efb_message_deleted ran (F05 / F03 / F11 give back)', array(count(array_filter($GLOBALS['efb_actions_log'], function ($a) { return $a[0] === 'efb_entries_purged'; })), $GLOBALS['deleted_hook']), array(1, array($e[2])));
$db->insert('wp_emsfb_msg_notes_', array('msg_id' => $e[5], 'user_id' => 1, 'note' => 'personal: +98912', 'date' => '2026-11-10 11:00:00'));
Entries::apply_op($F, 'star', array($e[5]));
\Emsfb\Privacy::purge_entries(array($e[5]), 'anon', false, false);
t('anonymization: the notes go, the history keeps who / when (3 rows) but not the values', array($db->count('wp_emsfb_msg_notes_', 'msg_id = ' . $e[5]), $db->count('wp_emsfb_msg_log_', 'msg_id = ' . $e[5]), $db->count('wp_emsfb_msg_log_', 'msg_id = ' . $e[5] . ' AND (old_value IS NOT NULL OR new_value IS NOT NULL)')), array(0, 3, 0));

/* ------------------------------------------------------------------ the trash job */
$old = $db->add_entry($F, array(array('id_' => 'nm', 'type' => 'text', 'value' => 'old')), '2026-01-01 10:00:00', array('entry_status' => 2, 'trashed_at' => gmdate('Y-m-d H:i:s', time() - 40 * 86400)));
$new = $db->add_entry($F, array(array('id_' => 'nm', 'type' => 'text', 'value' => 'new')), '2026-01-01 10:00:00', array('entry_status' => 2, 'trashed_at' => gmdate('Y-m-d H:i:s', time() - 5 * 86400)));
$act = $db->add_entry($F, array(array('id_' => 'nm', 'type' => 'text', 'value' => 'active')), '2025-01-01 10:00:00');
$GLOBALS['efb_settings'] = array('trash_days' => '0');
t('trash_days 0: the trash is never emptied by the job', Entries::purge_trash()['deleted'], 0);
$GLOBALS['efb_settings'] = array('trash_days' => '30');
$r = Entries::purge_trash();
t('trash_days 30: the entry trashed 40 days ago is deleted; 5 days ago and an old active entry stay', array($r['deleted'], $db->count('wp_emsfb_msg_', "msg_id IN ($old)"), $db->count('wp_emsfb_msg_', "msg_id IN ($new, $act)")), array(1, 0, 2));
$GLOBALS['efb_filters']['efb_trash_days'][] = function ($d) { return 1; };
t('filter efb_trash_days', Entries::trash_days(), 1);
unset($GLOBALS['efb_filters']['efb_trash_days']);

/* ------------------------------------------------------------------ "select all N results" */
for ($i = 0; $i < 7; $i++) $db->add_entry(5, array(array('id_' => 'c', 'type' => 'checkbox', 'value' => $i % 2 ? 'Cat' : 'Dog')), '2026-11-11 10:00:00');
$all = Entries::ids_of_filter(array('form_id' => 5, 'tab' => 'all'));
$cats = Entries::ids_of_filter(array('form_id' => 5, 'tab' => 'all', 'conds' => array(array('id' => 'c', 'op' => 'eq', 'v' => 'cat'))));
t('the filter of "select all": 7 ids, and 3 with the condition (the list\'s own rules)', array(count($all), count($cats)), array(7, 3));

/* ------------------------------------------------------------------ AJAX */
$ajax = function ($fn, $post) { $_POST = $post; try { Entries::$fn(); } catch (EfbJsonExit $x) { return $x->data; } return null; };
$GLOBALS['efb_nonce_ok'] = true; $GLOBALS['efb_caps'] = array('read' => true);
t('bulk without the entries capability → efb_forbidden', $ajax('ajax_bulk', array('op' => 'star', 'form_id' => 5, 'ids' => json_encode($all)))['code'], 'efb_forbidden');
$GLOBALS['efb_caps'] = array('manage_options' => true);
t('an unknown op → efb_invalid', $ajax('ajax_bulk', array('op' => 'drop', 'form_id' => 5, 'ids' => '[1]'))['code'], 'efb_invalid');
$res = $ajax('ajax_bulk', array('op' => 'star', 'form_id' => 5, 'filter' => json_encode(array('tab' => 'all', 'conds' => array(array('id' => 'c', 'op' => 'eq', 'v' => 'Dog'))))));
t('bulk with the filter: 4 starred, the counts back', array($res['done'], $res['counts']['starred']), array(4, 4));
$GLOBALS['efb_plan'] = 'free';
t('Free: a bulk with field conditions needs Free Plus (efb_plan_required, setting:entries_filter)', array_intersect_key($ajax('ajax_bulk', array('op' => 'star', 'form_id' => 5, 'filter' => json_encode(array('conds' => array(array('id' => 'c', 'op' => 'eq', 'v' => 'Dog')))))), array('code' => 1, 'feature' => 1)), array('code' => 'efb_plan_required', 'feature' => 'setting:entries_filter'));
t('Free: the same bulk without conditions works', $ajax('ajax_bulk', array('op' => 'unstar', 'form_id' => 5, 'filter' => json_encode(array('tab' => 'starred'))))['done'], 4);
t('Free: field columns need Free Plus', $ajax('ajax_cols', array('form_id' => 5, 'cols' => json_encode(array('c'))))['code'], 'efb_plan_required');
t('Free: entry details columns (_ip, _notes) are free; ids cleaned', $ajax('ajax_cols', array('form_id' => 5, 'cols' => json_encode(array('_ip', "_notes<script>", '_ip'))))['cols'], array('_ip', '_notesscript'));
$GLOBALS['efb_plan'] = 'free_plus';
t('Free Plus: a field column is saved in user meta efb_cols_5', array($ajax('ajax_cols', array('form_id' => 5, 'cols' => json_encode(array('c', '_ip'))))['success'], $GLOBALS['efb_umeta'][1]['efb_cols_5']), array(true, array('c', '_ip')));

/* ------------------------------------------------------------------ settings, registry, localize */
t('trash_days: digits only (Persian digits read), at most 3650', array(Entries::sanitize_setting('trash_days', '۴۵'), Entries::sanitize_setting('trash_days', '99999'), Entries::sanitize_setting('trash_days', 'x')), array('45', '3650', '30'));
t('ai_entries_access: off | stats | rows, anything else off', array(Entries::sanitize_setting('ai_entries_access', 'ROWS'), Entries::sanitize_setting('ai_entries_access', 'all')), array('rows', 'off'));
$caps = Entries::capabilities(array('features' => array(), 'plan' => array('package_type' => 2)));
t('registry: setting:entries_filter Free Plus, not available on Free; print without the credit is Pro', array($caps['features']['setting:entries_filter']['package'], $caps['features']['setting:entries_filter']['available_now'], $caps['features']['setting:entries_print_nobrand']['package']), array('free_plus', false, 'pro'));
t('registry: capabilities.entries names the read-only API and the setting', array($caps['entries']['api']['read_only'], $caps['entries']['api']['setting']), array(true, 'ai_entries_access'));
$admin = file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-admin.php');
t('set_settings_Emsfb: the F14 keys are an administrator\'s (manage_options) and kept when absent', strpos($admin, 'Entries::is_setting_key($key)') !== false && strpos($admin, 'Entries::setting_defaults()') !== false, true);

array_map('unlink', glob($uploads . '/*') ?: array());
@rmdir($uploads);
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
