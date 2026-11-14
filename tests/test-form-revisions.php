<?php
/**
 * F15-C: form versions (includes/class-Emsfb-revisions.php) on the SQLite $wpdb stand-in, the table made from the
 * plugin's own DDL:
 *  - three saves that change the form keep three versions; a save that changes nothing keeps none;
 *  - the first save of a form without versions keeps the state it replaced first ("initial");
 *  - form_rev_keep = 2 keeps the current version and two earlier ones; 0 keeps nothing;
 *  - the plan caps it (Free 3, Free Plus 10, Pro 50) and efb_form_revisions_keep can lower it;
 *  - a form bigger than 2 MB keeps at most 5; the source / note / user of each version; the JSON is stored
 *    escaped like emsfb_form and read back to the same rows; a restore saved is a new version (source restore)
 *    and the earlier ones stay;
 *  - deleting a form (efb_form_deleted) deletes its versions; deleting a user / the eraser sets user_id 0;
 *  - on_saved() never lets an error stop the save; the save hooks' sources (created, builder, autopilot, cf7 → import).
 *
 * Run: php tests/test-form-revisions.php
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
$GLOBALS['efb_settings'] = (object) array();
$GLOBALS['efb_plan'] = 'free_plus';
$GLOBALS['efb_filters'] = array();
$GLOBALS['efb_uid'] = 7;
function get_setting_Emsfb($m = 'decoded') { return $GLOBALS['efb_settings']; }
function apply_filters($h, $v) { $a = func_get_args(); return isset($GLOBALS['efb_filters'][$h]) ? call_user_func_array($GLOBALS['efb_filters'][$h], array_slice($a, 1)) : $v; }
function add_action() {} function add_filter() {}
function get_current_user_id() { return $GLOBALS['efb_uid']; }
function current_time($t, $g = 0) { return gmdate('Y-m-d H:i:s'); }
function sanitize_text_field($s) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $s))); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function emsfb_json_normalize_efb($s) { return str_replace('\\"', '"', (string) $s); }
function get_user_by($f, $v) { return $v === 'editor@example.test' ? (object) array('ID' => 7) : false; }
function __($s, $d = null) { return $s; }
function wp_timezone_string() { return 'UTC'; }
function wp_date($f, $ts) { return gmdate($f, $ts); }
function get_option($k, $d = false) { if ($k === 'emsfb_pro') return array('free' => 2, 'free_plus' => 3, 'pro' => 1)[$GLOBALS['efb_plan']]; return $d; }
function get_userdata($id) { return $id ? (object) array('display_name' => 'User ' . $id) : false; }
require_once __DIR__ . '/../includes/class-Emsfb-builder-api.php';
// The plan of the site: Builder_Api::current_package() reads the option emsfb_pro (stand-in: $GLOBALS['efb_plan']).
require_once __DIR__ . '/../includes/class-Emsfb-revisions.php';
require_once __DIR__ . '/../includes/class-Emsfb-form-package.php';

class EfbRevTestDb extends EfbF11TestDb {
	public function exec_mysql_ddl($sql) {
		// SQLite: the AUTO_INCREMENT key as INTEGER PRIMARY KEY.
		$sql = preg_replace('/rev_id bigint\(20\) unsigned NOT NULL AUTO_INCREMENT/', 'rev_id INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
		$sql = preg_replace('/,\s*PRIMARY KEY\s+\(rev_id\)/', '', $sql);
		return parent::exec_mysql_ddl($sql);
	}
	public function get_charset_collate() { return ''; }
}
$db = $GLOBALS['wpdb'] = new EfbRevTestDb();
$ddl = \Emsfb\Revisions::ddl($db->prefix, '');
$db->exec_mysql_ddl($ddl['emsfb_form_rev_']);
t('the DDL has the columns and the (form_id, rev_id) key', strpos($ddl['emsfb_form_rev_'], 'KEY form_rev (form_id, rev_id)') !== false && strpos($ddl['emsfb_form_rev_'], 'struct_hash char(32)') !== false && strpos($ddl['emsfb_form_rev_'], 'source varchar(20)') !== false, true);
\Emsfb\Revisions::reset_ready();
t('ready() sees the table', \Emsfb\Revisions::ready(), true);


function form_rows($n, $extra = array()) {
	$rows = array(array_merge(array('type' => 'form', 'formName' => 'Rev "test"', 'steps' => 1), $extra), array('type' => 'step', 'id_' => '1', 'step' => 1, 'name' => 'S'));
	for ($i = 0; $i < $n; $i++) $rows[] = array('id_' => 'f' . $i, 'type' => 'text', 'name' => 'Field ' . $i . ' «فارسی»', 'step' => 1, 'amount' => $i + 2);
	$rows[] = array('id_' => 'o1', 'type' => 'option', 'parent' => 'f0', 'value' => 'x');
	return $rows;
}
$count = function ($fid) use ($db) { return (int) $db->get_var("SELECT COUNT(*) FROM {$db->prefix}emsfb_form_rev_ WHERE form_id = " . (int) $fid); };
$R = '\\Emsfb\\Revisions';

// plan / keep
$keepFor = function ($plan, $setting) { $GLOBALS['efb_plan'] = $plan; $GLOBALS['efb_settings'] = (object) ($setting === null ? array() : array('form_rev_keep' => $setting)); return \Emsfb\Revisions::keep(); };
t('keep: default 10 on Free Plus; Free is capped at 3; Pro allows 50', array($keepFor('free_plus', null), $keepFor('free', null), $keepFor('pro', '50'), $keepFor('free_plus', '50')), array(10, 3, 50, 10));
t('keep: 0 turns versions off; Persian digits; above 50 → 50', array($keepFor('pro', '0'), $keepFor('pro', '۲۰'), \Emsfb\Revisions::sanitize_setting('form_rev_keep', '99')), array(0, 20, '50'));
$GLOBALS['efb_filters']['efb_form_revisions_keep'] = function ($n) { return min($n, 4); };
t('the filter efb_form_revisions_keep can lower it', $keepFor('pro', '20'), 4);
unset($GLOBALS['efb_filters']['efb_form_revisions_keep']);

$GLOBALS['efb_plan'] = 'free_plus';
$GLOBALS['efb_settings'] = (object) array();

// three saves, a save without a change
$fid = 11;
$a = $R::record($fid, form_rows(2), array('source' => 'created'));
$b = $R::record($fid, form_rows(3), array('source' => 'builder'));
$c = $R::record($fid, form_rows(4), array('source' => 'autopilot', 'note' => 'AutoPilot <b>run</b> 42'));
$same = $R::record($fid, form_rows(4), array('source' => 'builder'));
t('three saves that change the form keep three versions; the same form again keeps none', array($a > 0, $b > $a, $c > $b, $same, $count($fid)), array(true, true, true, 0, 3));
$list = $R::list_for_form($fid);
t('list: newest first, the newest is "current", fields / steps counted (option rows not), sources and the note (no tags)', array($list[0]['rev_id'], $list[0]['current'], $list[1]['current'], $list[0]['fields'], $list[0]['steps'], $list[0]['source'], $list[0]['note'], $list[2]['source'], $list[0]['user']), array($c, true, false, 4, 1, 'autopilot', 'AutoPilot run 42', 'created', 'User 7'));
$g = $R::get($b);
t('get(): the rows come back exactly as saved (quotes, Persian text); stored escaped like emsfb_form', array($g['rows'] === form_rows(3), strpos((string) $db->get_var("SELECT form_structer FROM {$db->prefix}emsfb_form_rev_ WHERE rev_id = {$b}"), '\\"type\\"') !== false), array(true, true));

// the first save of a form without versions keeps the replaced state first
$fid2 = 12;
$x = $R::record($fid2, form_rows(5), array('source' => 'builder', 'old_rows' => form_rows(1)));
$l2 = $R::list_for_form($fid2);
t('first save of an older form: the replaced state is kept first ("initial", no user), then the save', array(count($l2), $l2[1]['source'], $l2[1]['fields'], $l2[0]['source'], $l2[0]['rev_id'] === $x), array(2, 'initial', 1, 'builder', true));
$R::record($fid2, form_rows(6), array('source' => 'builder', 'old_rows' => form_rows(5)));
t('the replaced state is only added while the form has no version', $count($fid2), 3);

// keep = 2
$GLOBALS['efb_settings'] = (object) array('form_rev_keep' => '2');
$fid3 = 13;
for ($i = 1; $i <= 6; $i++) $R::record($fid3, form_rows($i));
$l3 = $R::list_for_form($fid3);
t('form_rev_keep = 2: the current version and two earlier ones are kept (the newest three)', array(count($l3), $l3[0]['fields'], $l3[2]['fields']), array(3, 6, 4));
$GLOBALS['efb_settings'] = (object) array('form_rev_keep' => '0');
t('form_rev_keep = 0: nothing kept', array($R::record(14, form_rows(1)), $count(14)), array(0, 0));
$GLOBALS['efb_settings'] = (object) array();

// Free plan cap
$GLOBALS['efb_plan'] = 'free';
$fid4 = 15;
for ($i = 1; $i <= 8; $i++) $R::record($fid4, form_rows($i));
t('Free: the current version and 3 earlier ones', $count($fid4), 4);
$GLOBALS['efb_plan'] = 'free_plus';

// a big form keeps at most 5
$fid5 = 16;
$big = form_rows(1, array('pad' => str_repeat('x', 2100000)));
for ($i = 0; $i < 8; $i++) { $big[0]['n'] = $i; $R::record($fid5, $big); }
t('a form bigger than 2 MB keeps the current version and at most 5 earlier ones', $count($fid5), 6);

// restore = a save of the old rows: a new version, the others stay
$old = $R::get($a);
$r = $R::record($fid, $old['rows'], array('source' => 'restore', 'note' => 'Restored from the version of 2026-11-14'));
$l = $R::list_for_form($fid);
t('a restored version saved: a new version (source restore, the note), the earlier ones stay', array(count($l), $l[0]['source'], $l[0]['note'], $l[0]['hash'] === $l[3]['hash']), array(4, 'restore', 'Restored from the version of 2026-11-14', true));

// on_saved / on_created
$R::on_created(20, form_rows(1), array('source' => 'builder', 'user_id' => 9));
$R::on_saved(20, form_rows(2), array('source' => 'builder', 'user_id' => 9, 'rev_source' => 'autopilot'));
$R::on_saved(20, form_rows(3), array('source' => 'cf7'));
$R::on_saved(20, form_rows(4), array('source' => 'builder', 'rev_source' => 'nonsense'));
$l = $R::list_for_form(20);
t('hooks: created (builder), autopilot (rev_source), cf7 → import, an unknown rev_source → the context source', array_column($l, 'source'), array('builder', 'import', 'autopilot', 'created'));
t('the user of the hook context', $l[3]['user'], 'User 9');
$R::on_saved(20, 'not rows', array());
$R::on_saved(0, form_rows(1), array());
t('on_saved() ignores what is not a form, never throws', $count(20), 4);

// delete, users
t('deleting a form deletes its versions', array($R::delete_for_form(20) > 0, $count(20)), array(true, 0));
t('a deleted user: user_id 0 on their versions', array($R::forget_user(7) > 0, (int) $db->get_var("SELECT COUNT(*) FROM {$db->prefix}emsfb_form_rev_ WHERE user_id = 7")), array(true, 0));
$R::record(30, form_rows(1));
$e = $R::erase('editor@example.test');
t('the WordPress eraser for an editor\'s address sets user_id 0', array($e['items_removed'], $e['done'], (int) $db->get_var("SELECT COUNT(*) FROM {$db->prefix}emsfb_form_rev_ WHERE user_id = 7")), array(true, true, 0));
t('the eraser for an unknown address removes nothing', $R::erase('nobody@example.test')['items_removed'], false);

// F15-D / P23: the add-ons' blocks of the form are kept with each version (secrets removed) and applied again on a restore save.
$GLOBALS['efb_settings'] = (object) array('AdnSS' => 1);
$GLOBALS['efb_filters']['efb_form_export_extras'] = function ($x, $fid, $ctx) { $x['sms'] = array('v' => 1, 'addon' => 'AdnSS', 'data' => array('new_noti' => 'Text of ' . $fid . ' (' . $ctx['context'] . ')', 'admin_numbers' => '+1555')); return $x; };
$rx = $R::record(40, form_rows(2));
$gx = $R::get($rx);
t('a version keeps the extras of the form (context revision, the admin numbers removed)', $gx['extras'], array('sms' => array('v' => 1, 'addon' => 'AdnSS', 'data' => array('new_noti' => 'Text of 40 (revision)'))));
$seen = null;
$GLOBALS['efb_filters']['efb_form_import_extras'] = function ($rep, $fid, $blocks, $ctx) use (&$seen) { $seen = array($fid, array_keys($blocks), $ctx['context']); $rep['applied'][] = 'sms'; return $rep; };
$ap = $R::apply_extras(40, $rx);
t('apply_extras(): the blocks of the restored version go to efb_form_import_extras (context revision)', array($seen, $ap['applied']), array(array(40, array('sms'), 'revision'), array('sms')));
t('apply_extras() for a version of another form does nothing', $R::apply_extras(41, $rx), null);
unset($GLOBALS['efb_filters']['efb_form_export_extras'], $GLOBALS['efb_filters']['efb_form_import_extras']);
$GLOBALS['efb_settings'] = (object) array();

// registry
$caps = $R::capabilities(array('features' => array()), array('package' => 2));
t('registry: revisions (ajax actions, sources, limits) and setting:revisions (free, limits 3 / 10 / 50)', array($caps['revisions']['ajax']['list'], $caps['revisions']['limits'], $caps['features']['setting:revisions']['package'], $caps['features']['setting:revisions']['limits']['pro']), array('efb_list_revisions', array('free' => 3, 'free_plus' => 10, 'pro' => 50), 'free', 50));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
