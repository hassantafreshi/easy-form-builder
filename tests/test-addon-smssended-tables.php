<?php
/**
 * SMS add-on (vendores-efb smssended): its two tables are made in the form dbDelta() reads (F20 §15 / F20-core brief).
 *
 * BUG: smsefb.php and class-Emsfb-sms.php gave dbDelta "CREATE TABLE IF NOT EXISTS …", which dbDelta does not parse
 * (its |CREATE TABLE ([^ ]*)| takes the word IF for the table name — core's class-Emsfb-install.php says so): a missing
 * table was created, but a column missing from an existing table was never added. Now:
 *  - one definition (smssendefb::tables_sql()): plain CREATE TABLE, one column per line with single spaces (dbDelta
 *    reads the type after one space), "PRIMARY KEY  (id)"; no "IF NOT EXISTS" left in the add-on's code;
 *  - class-Emsfb-sms.php uses the same definition;
 *  - on the real WordPress (the e2e site, when available): a column taken off an existing table comes back on the
 *    next load (emsfb_sms_db older than DB_VERSION), a second run changes nothing, and the old statement would not
 *    have brought the column back (SQLite cannot add a NOT NULL column to a table with rows, so the site's rows are
 *    put aside around the check and written back; MySQL fills such a column with '');
 *  - the zip the add-on ships is built from these files.
 *
 * Add-on source: EFB_SMS_DIR, vendor/smssended (git-ignored symlink), or /home/user/vendores-efb/smssended.
 * Run: php tests/test-addon-smssended-tables.php   (the WordPress part under the e2e lock, with the e2e env)
 */
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . json_encode($expected) . ', got ' . json_encode($actual) . "\n"; $failed++;
}
$dir = null;
foreach (array(getenv('EFB_SMS_DIR') ?: '', __DIR__ . '/../vendor/smssended', '/home/user/vendores-efb/smssended') as $d) {
	if ($d !== '' && is_readable($d . '/smsefb.php')) { $dir = realpath($d); break; }
}
if (!$dir) { echo "[SKIP] the SMS add-on source was not found\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

/* --- the definition, without WordPress --- */
$code = '';
foreach (array('smsefb.php', 'class-Emsfb-sms.php') as $f) {
	$toks = token_get_all(file_get_contents($dir . '/' . $f));
	foreach ($toks as $tk) if (!is_array($tk) || !in_array($tk[0], array(T_COMMENT, T_DOC_COMMENT), true)) $code .= is_array($tk) ? $tk[1] : $tk;
}
t('no "CREATE TABLE IF NOT EXISTS" left in the add-on\'s code (comments aside)', stripos($code, 'IF NOT EXISTS'), false);
t('class-Emsfb-sms.php makes its tables through smssendefb::install_tables()', (bool) preg_match('/function create_sms_tables_efb\(\)\s*\{[^}]*smssendefb::install_tables\(\)/', $code), true);

if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$GLOBALS['wpdb'] = new class { public $prefix = 'wp_'; public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; } };
if (!function_exists('add_action')) { function add_action() {} function get_option($k, $d = false) { return $d; } function update_option() { return true; } }
require_once $dir . '/smsefb.php';
$sql = \Emsfb\smssendefb::tables_sql();
t('two statements, each a plain CREATE TABLE of its table', array_map(function ($q) { return preg_match('/^CREATE TABLE (\S+) \(\n/', $q, $m) ? $m[1] : null; }, $sql), array('wp_emsfb_sms_sent_list', 'wp_emsfb_sms_contact'));
$cols = array();
foreach ($sql as $q) {
	$lines = array_slice(explode("\n", trim($q)), 1, -1);
	foreach ($lines as $l) $cols[] = $l;
}
$bad = array_values(array_filter($cols, function ($l) { return strpos($l, 'PRIMARY KEY') === false && !preg_match('/^`\w+` [a-z]+(\(\d+\))?( [A-Z_]+)*( [A-Z_]+| DEFAULT [A-Z_]+)*,$/', $l); }));
t('one column per line, single spaces, the type right after the name (dbDelta reads it so)', $bad, array());
t('"PRIMARY KEY  (id)" with two spaces, last in each table', array_values(array_filter($cols, function ($l) { return strpos($l, 'PRIMARY KEY') !== false; })), array('PRIMARY KEY  (id)', 'PRIMARY KEY  (id)'));
t('the columns are those the add-on reads and writes', array_map(function ($l) { return preg_match('/^`(\w+)`/', $l, $m) ? $m[1] : 'pk'; }, $cols),
	array('id', 'number', 'message', 'status', 'date', 'form_id', 'pk', 'id', 'admin_numbers', 'form_id', 'recived_message_noti_user', 'new_message_noti_user', 'new_message_noti_admin', 'new_response_noti', 'date', 'pk'));
t('a table version to bring existing sites up to date once', \Emsfb\smssendefb::DB_VERSION, '2');

/* --- the zip --- */
$zip = dirname($dir) . '/smssended.zip';
if (is_readable($zip) && class_exists('ZipArchive')) {
	$z = new ZipArchive(); $z->open($zip);
	t('smssended.zip ships these files (smsefb.php, class-Emsfb-sms.php)', array($z->getFromName('smssended/smsefb.php') === file_get_contents($dir . '/smsefb.php'), $z->getFromName('smssended/class-Emsfb-sms.php') === file_get_contents($dir . '/class-Emsfb-sms.php')), array(true, true));
}

/* --- the real WordPress --- */
$site = getenv('EFB_E2E_SITE_DIR') ?: '';
$wpcli = getenv('EFB_E2E_WPCLI') ?: '';
if ($site === '' || $wpcli === '' || !is_dir($site)) {
	echo "[SKIP] the WordPress part: EFB_E2E_SITE_DIR / EFB_E2E_WPCLI are not set\n";
} else {
	$probe = tempnam(sys_get_temp_dir(), 'efb-sms-tables-') . '.php';
	file_put_contents($probe, '<?php
global $wpdb;
require_once ' . var_export($dir . '/smsefb.php', true) . ';
require_once ABSPATH . "wp-admin/includes/upgrade.php";
$t = $wpdb->prefix . "emsfb_sms_contact";
$cols = function () use ($wpdb, $t) { return array_map(function ($r) { return $r->Field; }, (array) $wpdb->get_results("DESCRIBE $t")); };
$out = array();
\Emsfb\smssendefb::install_tables();
// SQLite (this site) cannot add a NOT NULL column to a table that has rows (MySQL fills it with an empty text): the rows are put
// aside around the check and written back after it.
$keep = (array) $wpdb->get_results("SELECT * FROM $t", ARRAY_A);
$wpdb->query("DELETE FROM $t");
$wpdb->query("ALTER TABLE $t DROP COLUMN new_response_noti");
$out["dropped"] = !in_array("new_response_noti", $cols(), true);
// What the add-on did before: the same columns behind "IF NOT EXISTS".
dbDelta(str_replace("CREATE TABLE ", "CREATE TABLE IF NOT EXISTS ", \Emsfb\smssendefb::tables_sql()[1]));
$out["old_back"] = in_array("new_response_noti", $cols(), true);
update_option("emsfb_addon_AdnSS", 2);
delete_option("emsfb_sms_db");
new \Emsfb\smssendefb();
$out["back"] = in_array("new_response_noti", $cols(), true);
foreach ($keep as $row) $wpdb->insert($t, $row);
$out["rows_back"] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $t") === count($keep);
$out["version"] = get_option("emsfb_sms_db");
$out["again"] = \Emsfb\smssendefb::install_tables();
$out["sent_list"] = array_map(function ($r) { return $r->Field; }, (array) $wpdb->get_results("DESCRIBE {$wpdb->prefix}emsfb_sms_sent_list"));
echo json_encode($out), "\n";
');
	$cmd = $wpcli . ' eval-file ' . escapeshellarg($probe) . ' --path=' . escapeshellarg($site) . ' --allow-root 2>/dev/null';
	$lines = array_values(array_filter(explode("\n", (string) shell_exec($cmd)), 'strlen'));
	@unlink($probe);
	$r = json_decode($lines ? end($lines) : '', true);
	if (!is_array($r)) { t('the WordPress part ran', false, true); }
	else {
		t('a column taken off the contacts table (the state of a site whose table predates a column)', $r['dropped'], true);
		t('BUG: the old "IF NOT EXISTS" statement does not bring it back', $r['old_back'], false);
		t('the next load (emsfb_sms_db older) brings it back through dbDelta and stores the version; the rows written back', array($r['back'], $r['version'], $r['rows_back']), array(true, '2', true));
		t('a second run changes nothing (no ALTER on every load)', $r['again'], array());
		t('the sent-list table has its columns', $r['sent_list'], array('id', 'number', 'message', 'status', 'date', 'form_id'));
	}
}
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
