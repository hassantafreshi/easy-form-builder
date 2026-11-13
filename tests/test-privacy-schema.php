<?php
/**
 * F13 sub-task A: schema for privacy and retention.
 *
 * emsfb_msg_ gains `uid` (the logged-in sender, 0 for guests) and `prv_state`
 * (1 = anonymized), plus the indexes the retention cron and the privacy
 * exporter need (form_date, track, date); emsfb_rsp_ gains KEY msg_id.
 *
 * A fake $wpdb records every statement and answers SHOW TABLES / SHOW COLUMNS /
 * SHOW INDEX from an in-memory schema, so the test checks:
 *  - an upgrade from DB version 1.3 adds the two columns and four indexes;
 *  - a second run issues no ALTER at all (idempotent);
 *  - the steps run even when emsfb_stts_ is missing (they sit before the early return);
 *  - fresh installs get the same columns and keys in CREATE TABLE;
 *  - EMSFB_DB_VERSION moved to 1.4 and the version is recorded;
 *  - maybe_upgrade_schema_efb() takes the emsfb_schema_lock transient;
 *  - insert_message_db() writes uid.
 *
 * Run: php tests/test-privacy-schema.php
 */

define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('OBJECT', 'OBJECT');

$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}

$GLOBALS['opts'] = array();
$GLOBALS['transients'] = array();
function update_option($k, $v) { $GLOBALS['opts'][$k] = $v; return true; }
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function get_transient($k) { return isset($GLOBALS['transients'][$k]) ? $GLOBALS['transients'][$k] : false; }
function set_transient($k, $v, $ttl = 0) { $GLOBALS['transients'][$k] = $v; $GLOBALS['transient_log'][] = array('set', $k, $ttl); return true; }
function delete_transient($k) { unset($GLOBALS['transients'][$k]); $GLOBALS['transient_log'][] = array('delete', $k); return true; }

class FakeSchemaDb {
	public $prefix = 'wp_';
	public $log = array();
	public $tables = array();   // name => ['cols' => [..], 'keys' => [..]]
	public function prepare($q) {
		$args = array_slice(func_get_args(), 1);
		if (count($args) === 1 && is_array($args[0])) $args = $args[0];
		foreach ($args as $a) $q = preg_replace('/%s|%d/', "'" . $a . "'", $q, 1);
		return $q;
	}
	public function get_var($q) {
		$this->log[] = $q;
		if (preg_match("/SHOW TABLES LIKE '([^']+)'/", $q, $m)) return isset($this->tables[$m[1]]) ? $m[1] : null;
		return null;
	}
	public function get_results($q, $out = OBJECT) {
		$this->log[] = $q;
		if (preg_match("/SHOW COLUMNS FROM `([^`]+)` LIKE '([^']+)'/", $q, $m)) {
			return isset($this->tables[$m[1]]) && in_array($m[2], $this->tables[$m[1]]['cols'], true) ? array((object) array('Field' => $m[2])) : array();
		}
		if (preg_match('/SHOW INDEX FROM `([^`]+)`/', $q, $m)) {
			$rows = array();
			foreach (isset($this->tables[$m[1]]) ? $this->tables[$m[1]]['keys'] : array() as $k) $rows[] = array('Key_name' => $k);
			return $rows;
		}
		return array();
	}
	public function query($q) {
		$this->log[] = $q;
		if (preg_match('/ALTER TABLE `([^`]+)` ADD COLUMN `(\w+)`/', $q, $m)) { $this->tables[$m[1]]['cols'][] = $m[2]; return true; }
		if (preg_match('/ALTER TABLE `([^`]+)` ADD KEY `?(\w+)`?/', $q, $m)) { $this->tables[$m[1]]['keys'][] = $m[2]; return true; }
		return true;
	}
	public function alters() { return array_values(array_filter($this->log, function ($q) { return stripos($q, 'ALTER TABLE') === 0; })); }
}

function old_schema($with_stts = true) {
	$t = array(
		'wp_emsfb_form' => array('cols' => array('form_id', 'form_name', 'form_structer', 'status'), 'keys' => array('PRIMARY')),
		'wp_emsfb_msg_' => array('cols' => array('msg_id', 'form_id', 'track', 'ip', 'form_title_x', 'content', 'date', 'read_date', 'read_', 'read_by'), 'keys' => array('PRIMARY')),
		'wp_emsfb_rsp_' => array('cols' => array('rsp_id', 'msg_id', 'ip', 'content', 'date', 'read_by', 'read_date', 'read_', 'reader_ip', 'rsp_by'), 'keys' => array('PRIMARY')),
	);
	if ($with_stts) $t['wp_emsfb_stts_'] = array('cols' => array('id', 'sid'), 'keys' => array('PRIMARY', 'sid', 'lookup', 'date'));
	return $t;
}

// ------------------------------------------------------------------ the constant
$main = file_get_contents(__DIR__ . '/../emsfb.php');
// 1.4 is F13's version; later steps (1.5 = PAY00 payments) may only move it forward.
t('EMSFB_DB_VERSION is 1.4 or later', (bool) preg_match('/define\("EMSFB_DB_VERSION",\s*1\.([4-9]|\d\d)\)/', $main), true);
define('EMSFB_DB_VERSION', 1.4);

require_once __DIR__ . '/../includes/class-Emsfb-install.php';

// ------------------------------------------------------------------ upgrade from 1.3
$wpdb = new FakeSchemaDb();
$wpdb->tables = old_schema(true);
$GLOBALS['wpdb'] = $wpdb;
$applied = \Emsfb\Install::upgrade_schema();
$msg = $wpdb->tables['wp_emsfb_msg_'];
$rsp = $wpdb->tables['wp_emsfb_rsp_'];
t('msg has uid after upgrade', in_array('uid', $msg['cols'], true), true);
t('msg has prv_state after upgrade', in_array('prv_state', $msg['cols'], true), true);
foreach (array('form_date', 'track', 'date') as $k) t("msg has KEY {$k}", in_array($k, $msg['keys'], true), true);
t('rsp has KEY msg_id', in_array('msg_id', $rsp['keys'], true), true);
t('six privacy steps reported as applied', count(array_filter($applied, function ($a) { return strpos($a, 'emsfb_msg_') === 0 || strpos($a, 'emsfb_rsp_') === 0; })), 6);
$alter_sql = implode("\n", $wpdb->alters());
t('uid is BIGINT UNSIGNED NOT NULL DEFAULT 0', (bool) preg_match('/ADD COLUMN `uid` BIGINT UNSIGNED NOT NULL DEFAULT 0/', $alter_sql), true);
t('prv_state is TINYINT NOT NULL DEFAULT 0', (bool) preg_match('/ADD COLUMN `prv_state` TINYINT NOT NULL DEFAULT 0/', $alter_sql), true);
t('form_date covers (form_id, date)', (bool) preg_match('/ADD KEY `form_date` \(`form_id`,\s*`date`\)/', $alter_sql), true);
t('version recorded as 1.4', get_option('Emsfb_db_version'), EMSFB_DB_VERSION);

// ------------------------------------------------------------------ second run is a no-op
$wpdb->log = array();
$again = \Emsfb\Install::upgrade_schema();
t('second run issues no ALTER', count($wpdb->alters()), 0);
t('second run reports nothing applied', $again, array());

// ------------------------------------------------------------------ missing emsfb_stts_
$wpdb2 = new FakeSchemaDb();
$wpdb2->tables = old_schema(false);
$GLOBALS['wpdb'] = $wpdb2;
$GLOBALS['opts'] = array();
\Emsfb\Install::upgrade_schema();
t('without emsfb_stts_ msg still gets uid', in_array('uid', $wpdb2->tables['wp_emsfb_msg_']['cols'], true), true);
t('without emsfb_stts_ msg still gets KEY form_date', in_array('form_date', $wpdb2->tables['wp_emsfb_msg_']['keys'], true), true);
t('without emsfb_stts_ rsp still gets KEY msg_id', in_array('msg_id', $wpdb2->tables['wp_emsfb_rsp_']['keys'], true), true);

// ------------------------------------------------------------------ missing msg/rsp tables: no error, no ALTER on them
$wpdb3 = new FakeSchemaDb();
$wpdb3->tables = array('wp_emsfb_form' => array('cols' => array('status'), 'keys' => array()));
$GLOBALS['wpdb'] = $wpdb3;
\Emsfb\Install::upgrade_schema();
t('no ALTER on absent msg/rsp tables', count(array_filter($wpdb3->alters(), function ($q) { return strpos($q, 'emsfb_msg_') !== false || strpos($q, 'emsfb_rsp_') !== false; })), 0);

// ------------------------------------------------------------------ fresh install DDL
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-install.php');
preg_match('/\$sql = "CREATE TABLE \{\$table_name_msg\} \((.*?)\) \{\$charset_collate\};";/s', $src, $m_msg);
preg_match('/\$sql = "CREATE TABLE \{\$table_name_rsp\} \((.*?)\) \{\$charset_collate\};";/s', $src, $m_rsp);
$ddl_msg = isset($m_msg[1]) ? $m_msg[1] : '';
$ddl_rsp = isset($m_rsp[1]) ? $m_rsp[1] : '';
t('CREATE msg has uid', (bool) preg_match('/`uid` BIGINT UNSIGNED NOT NULL DEFAULT 0/', $ddl_msg), true);
t('CREATE msg has prv_state', (bool) preg_match('/`prv_state` TINYINT NOT NULL DEFAULT 0/', $ddl_msg), true);
t('CREATE msg has KEY form_date', (bool) preg_match('/KEY form_date \(form_id,\s*`?date`?\)/', $ddl_msg), true);
t('CREATE msg has KEY track', (bool) preg_match('/KEY track \(track\)/', $ddl_msg), true);
t('CREATE msg has KEY date', (bool) preg_match('/KEY date \(`?date`?\)/', $ddl_msg), true);
t('CREATE rsp has KEY msg_id', (bool) preg_match('/KEY msg_id \(msg_id\)/', $ddl_rsp), true);
// The steps must come before the stts early return.
$up = substr($src, strpos($src, 'static function upgrade_schema'));
$pos_steps = strpos($up, 'self::upgrade_privacy_schema()');
$pos_return = strpos($up, 'return $applied;');
t('privacy steps sit before the early return', $pos_steps !== false && $pos_return !== false && $pos_steps < $pos_return, true);

// ------------------------------------------------------------------ schema lock in maybe_upgrade_schema_efb()
$core = file_get_contents(__DIR__ . '/../includes/class-Emsfb.php');
preg_match('/public function maybe_upgrade_schema_efb\(\): void \{(.*?)\n    \}/s', $core, $mm);
$body = isset($mm[1]) ? $mm[1] : '';
t('maybe_upgrade checks emsfb_schema_lock', strpos($body, "get_transient('emsfb_schema_lock')") !== false, true);
t('maybe_upgrade sets the lock for 60 s', (bool) preg_match("/set_transient\('emsfb_schema_lock',\s*[^,]+,\s*60\)/", $body), true);
t('maybe_upgrade releases the lock', strpos($body, "delete_transient('emsfb_schema_lock')") !== false, true);

// ------------------------------------------------------------------ insert_message_db writes uid
$pub = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
preg_match('/public function insert_message_db\(.*?\n\t\}/s', $pub, $mi);
$ins = isset($mi[0]) ? $mi[0] : '';
t("insert_message_db writes 'uid' => get_current_user_id()", (bool) preg_match("/'uid'\s*=>\s*(?:\(int\)\s*)?get_current_user_id\(\)/", $ins), true);

// Behaviour: the real method, extracted, against a database that has / has not got the column yet.
eval('namespace Emsfb; class Form_Runtime { static function begin() { return "op"; } static function abort() {} static function finish() {} }
class Request_Health { static function mark_saved() {} static function record() {} }');
function wp_date($f) { return date($f); }
function get_current_user_id() { return 7; }
function get_setting_Emsfb() { return new stdClass(); }
require_once __DIR__ . '/../includes/class-Emsfb-privacy.php'; // insert_message_db() shapes the IP through it
function wp_create_nonce($x) { return 'n'; }
function __($s) { return $s; }
function wp_send_json_error($d) { throw new Exception('json_error'); }
class InsertDb {
	public $prefix = 'wp_'; public $rows = array(); public $last_error = ''; public $has_uid;
	public function __construct($has_uid) { $this->has_uid = $has_uid; }
	public function insert($t, $row) {
		if (!$this->has_uid && array_key_exists('uid', $row)) { $this->last_error = "Unknown column 'uid' in 'field list'"; return false; }
		$this->last_error = ''; $this->rows[] = $row; return 1;
	}
}
$method = preg_replace('/^\tpublic function/', 'public function', $ins);
$method = str_replace(array('\\Emsfb\\Form_Runtime', '\\Emsfb\\Request_Health'), array('\\Emsfb\\Form_Runtime', '\\Emsfb\\Request_Health'), $method);
// insert_message_db() runs the idempotency guard through these two (F13-C).
$ops = '';
foreach (array('begin_operation_efb', 'finish_operation_efb', 'gate_reserve_efb', 'gate_after_insert_efb', 'unique_claim_efb', 'verification_consume_efb', 'verification_after_insert_efb') as $op) { // F11: the form gate's places; F03: unique values; F02: one-time codes
	if (preg_match('/\tprivate function ' . $op . '\(.*?\n\t\}\n/s', $pub, $mo)) $ops .= $mo[0];
}
eval('class InsertFixture { public $db; public $name = "F"; public $value = "[]"; public $id = 3; public $ip = "203.0.113.9"; private $operation_key = ""; private $operation_data = array();
	private function generate_track_code_efb($s) { return "TRACK1"; } private function claim_uploaded_files_efb($c) {} ' . $ops . $method . ' }');
$fx = new InsertFixture();
$fx->db = new InsertDb(true);
$fx->insert_message_db(0, false);
t('uid of the logged-in sender is stored', isset($fx->db->rows[0]['uid']) ? $fx->db->rows[0]['uid'] : null, 7);
$fx2 = new InsertFixture();
$fx2->db = new InsertDb(false);
$code = $fx2->insert_message_db(0, false);
t('without the column yet, the entry is still stored', count($fx2->db->rows), 1);
t('...without uid', array_key_exists('uid', $fx2->db->rows[0]), false);
t('...and the tracking code is returned', $code, 'TRACK1');

// ------------------------------------------------------------------ migration note
$readme = @file_get_contents(__DIR__ . '/../docs/migrations/README.md');
t('docs/migrations/README.md links the privacy note', (bool) preg_match('/privacy-retention-\d{4}-\d{2}-\d{2}\.md/', (string) $readme), true);
$notes = glob(__DIR__ . '/../docs/migrations/privacy-retention-*.md');
t('the migration note exists', !empty($notes), true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
