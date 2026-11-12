<?php
/**
 * SMS add-on: the contact / sent-list queries are prepared (B5).
 *
 * smssendefb (vendores-efb smssended/smsefb.php) wrote the SMS texts into its SQL between
 * quotes: add_sms_contact_efb() with "We've received your message" broke the INSERT/UPDATE and
 * the form's SMS settings were silently not stored. The same functions, and the get/delete
 * helpers, put the form id / row id into the SQL unquoted, so "0 OR 1=1" read or deleted
 * every row. Now every value goes through $wpdb->prepare() / insert() / update() / delete().
 *
 * $wpdb is a small stand-in backed by an in-memory SQLite database (pdo_sqlite), so a broken
 * query really fails and an injected one really runs; prepare() quotes like WordPress does
 * (strings quoted and escaped, %d cast to an integer).
 * Add-on source: EFB_SMS_DIR, vendor/smssended (git-ignored symlink), or /home/user/vendores-efb/smssended.
 *
 * Run: php tests/test-addon-smssended-prepared-sql.php
 */
namespace {
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!extension_loaded('pdo_sqlite')) { echo "[SKIP] pdo_sqlite is not available\n"; exit(0); }
$dir = null;
foreach (array(getenv('EFB_SMS_DIR') ?: '', __DIR__ . '/../vendor/smssended', '/home/user/vendores-efb/smssended') as $d) {
	if ($d !== '' && is_readable($d . '/smsefb.php')) { $dir = $d; break; }
}
if (!$dir) { echo "[SKIP] the SMS add-on source was not found\n"; exit(0); }
function add_action() {} function get_option($k, $d = false) { return $d; } function update_option() { return true; }
function get_current_user_id() { return 1; }

class EfbSqliteWpdb {
	public $prefix = 'wp_'; public $insert_id = 0; public $last_error = ''; public $queries = array();
	private $pdo;
	public function __construct() {
		$this->pdo = new PDO('sqlite::memory:');
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
		$this->pdo->exec('CREATE TABLE wp_emsfb_sms_contact (id INTEGER PRIMARY KEY AUTOINCREMENT, admin_numbers TEXT NOT NULL, form_id INTEGER NOT NULL, recived_message_noti_user TEXT NOT NULL, new_message_noti_user TEXT NOT NULL, new_message_noti_admin TEXT NOT NULL, new_response_noti TEXT NOT NULL, date TEXT DEFAULT CURRENT_TIMESTAMP)');
		$this->pdo->exec('CREATE TABLE wp_emsfb_sms_sent_list (id INTEGER PRIMARY KEY AUTOINCREMENT, number TEXT NOT NULL, message TEXT NOT NULL, status TEXT NOT NULL, date TEXT DEFAULT CURRENT_TIMESTAMP, form_id INTEGER NOT NULL)');
	}
	/** As wpdb::prepare(): %d -> integer, %f -> float, %s -> quoted, escaped string. */
	public function prepare($query, ...$args) {
		if (count($args) === 1 && is_array($args[0])) $args = $args[0];
		$i = 0; $pdo = $this->pdo;
		return preg_replace_callback('/%[dfs]/', function ($m) use (&$i, $args, $pdo) {
			$v = $args[$i++] ?? '';
			if ($m[0] === '%d') return (string) (int) $v;
			if ($m[0] === '%f') return (string) (float) $v;
			return $pdo->quote((string) $v);
		}, $query);
	}
	private function run($sql) {
		$this->queries[] = $sql;
		$st = $this->pdo->query($sql);
		if ($st === false) { $this->last_error = implode(' ', $this->pdo->errorInfo()); return false; }
		$this->last_error = '';
		return $st;
	}
	public function query($sql) { $st = $this->run($sql); if ($st === false) return false; $this->insert_id = (int) $this->pdo->lastInsertId(); return $st->rowCount(); }
	public function get_results($sql) { $st = $this->run($sql); return $st === false ? array() : $st->fetchAll(PDO::FETCH_OBJ); }
	public function get_row($sql) { $st = $this->run($sql); if ($st === false) return null; $r = $st->fetch(PDO::FETCH_OBJ); return $r === false ? null : $r; }
	private function exec_prepared($sql, $params) {
		$this->queries[] = $sql;
		$st = $this->pdo->prepare($sql);
		if ($st === false || !$st->execute($params)) { $this->last_error = 'prepared failed'; return false; }
		return $st->rowCount();
	}
	public function insert($table, $data, $format = null) {
		$cols = array_keys($data);
		$r = $this->exec_prepared("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($data));
		if ($r !== false) $this->insert_id = (int) $this->pdo->lastInsertId();
		return $r;
	}
	public function update($table, $data, $where, $format = null, $where_format = null) {
		$set = implode(',', array_map(function ($c) { return "$c = ?"; }, array_keys($data)));
		$w = implode(' AND ', array_map(function ($c) { return "$c = ?"; }, array_keys($where)));
		return $this->exec_prepared("UPDATE $table SET $set WHERE $w", array_merge(array_values($data), array_values($where)));
	}
	public function delete($table, $where, $where_format = null) {
		$w = implode(' AND ', array_map(function ($c) { return "$c = ?"; }, array_keys($where)));
		return $this->exec_prepared("DELETE FROM $table WHERE $w", array_values($where));
	}
	public function count_rows($table) { return (int) $this->pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn(); }
	public function raw($sql) { return $this->pdo->query($sql)->fetchAll(PDO::FETCH_OBJ); }
}
require_once $dir . '/smsefb.php';

$pass = 0; $fail = 0;
function sm($label, $ok, $detail = null) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($detail !== null ? ' ' . json_encode($detail, JSON_UNESCAPED_UNICODE) : '') . "\n"; }
}
function sm_fresh() {
	$GLOBALS['wpdb'] = new EfbSqliteWpdb();
	return (new ReflectionClass('\Emsfb\smssendefb'))->newInstanceWithoutConstructor(); // the constructor runs dbDelta()
}

// 1. A text with an apostrophe is stored (insert).
$sms = sm_fresh();
$id = $sms->add_sms_contact_efb(7, '+491701234567', "We've received your message. Code: [confirmation_code]", "New message: it's here", "New message: it's here", "Reply's ready: [link_response]");
$row = $GLOBALS['wpdb']->raw('SELECT * FROM wp_emsfb_sms_contact WHERE form_id = 7');
sm('insert: the contact row with apostrophes is stored', count($row) === 1, array('last_error' => $GLOBALS['wpdb']->last_error, 'rows' => $row));
sm('insert: every text is stored exactly as given', isset($row[0]) && $row[0]->recived_message_noti_user === "We've received your message. Code: [confirmation_code]" && $row[0]->new_message_noti_user === "New message: it's here" && $row[0]->new_message_noti_admin === "New message: it's here" && $row[0]->new_response_noti === "Reply's ready: [link_response]" && $row[0]->admin_numbers === '+491701234567', $row);
sm('insert: returns the new row id', isset($row[0]) && (int) $id === (int) $row[0]->id && (int) $id > 0, $id);

// 2. Update path: the second save of the same form, with apostrophes.
$sms = sm_fresh();
$first = $sms->add_sms_contact_efb(7, '+491701234567', 'Got it', 'New', 'New', 'Reply');
$again = $sms->add_sms_contact_efb(7, '+491709999999', "We've got it", "Don't miss it", "Don't miss it", "It's answered");
$row = $GLOBALS['wpdb']->raw('SELECT * FROM wp_emsfb_sms_contact WHERE form_id = 7');
sm('update: still one row for the form', count($row) === 1, $row);
sm('update: the new texts with apostrophes replace the old ones', isset($row[0]) && $row[0]->recived_message_noti_user === "We've got it" && $row[0]->new_message_noti_user === "Don't miss it" && $row[0]->new_response_noti === "It's answered" && $row[0]->admin_numbers === '+491709999999', array('last_error' => $GLOBALS['wpdb']->last_error, 'rows' => $row));
sm('update: returns the existing row id', (int) $again === (int) $first, array($first, $again));

// 3. A text written to break out of the quotes is stored as text, nothing else runs.
$sms = sm_fresh();
$GLOBALS['wpdb']->query("INSERT INTO wp_emsfb_sms_contact (form_id, admin_numbers, recived_message_noti_user, new_message_noti_user, new_message_noti_admin, new_response_noti) VALUES (3, '+1', 'a', 'b', 'b', 'c')");
$evil = "x', admin_numbers = '+666";
$sms->add_sms_contact_efb(7, '+491701234567', $evil, 'n', 'n', 'r');
$sms->add_sms_contact_efb(7, '+491701234567', $evil, 'n', 'n', 'r'); // the UPDATE path
$row7 = $GLOBALS['wpdb']->raw('SELECT * FROM wp_emsfb_sms_contact WHERE form_id = 7');
$row3 = $GLOBALS['wpdb']->raw('SELECT * FROM wp_emsfb_sms_contact WHERE form_id = 3');
sm('injection: the text is stored literally', isset($row7[0]) && $row7[0]->recived_message_noti_user === $evil && $row7[0]->admin_numbers === '+491701234567', $row7);
sm('injection: no other column or form is changed', isset($row3[0]) && $row3[0]->admin_numbers === '+1', $row3);

// 4. Ids are integers in the SQL: "0 OR 1=1" matches nothing.
$sms = sm_fresh();
$sms->add_sms_contact_efb(3, '+1', 'a', 'b', 'b', 'c');
$sms->add_sms_contact_efb(7, '+2', 'a', 'b', 'b', 'c');
$r = $sms->get_sms_contact_efb('0 OR 1=1');
sm('get_sms_contact_efb("0 OR 1=1") returns nothing', $r === null, $r);
$r = $sms->get_sms_contact_efb(7);
sm('get_sms_contact_efb(7) returns form 7', is_object($r) && (int) $r->form_id === 7 && $r->admin_numbers === '+2', $r);
$sms->delete_sms_contact_efb('0 OR 1=1');
sm('delete_sms_contact_efb("0 OR 1=1") deletes nothing', $GLOBALS['wpdb']->count_rows('wp_emsfb_sms_contact') === 2);
$sms->delete_sms_contact_efb(3);
sm('delete_sms_contact_efb(3) deletes form 3 only', $GLOBALS['wpdb']->count_rows('wp_emsfb_sms_contact') === 1 && $sms->get_sms_contact_efb(7) !== null);

foreach (array(array(5, '+1'), array(5, '+2'), array(9, '+3')) as $s) {
	$GLOBALS['wpdb']->query("INSERT INTO wp_emsfb_sms_sent_list (number, message, status, form_id) VALUES ('{$s[1]}', 'm', 'wpsms', {$s[0]})");
}
$list = $sms->get_sms_sent_list_efb('5 OR 1=1');
sm('get_sms_sent_list_efb("5 OR 1=1") lists form 5 only', is_array($list) && count($list) === 2, $list);
sm('get_sms_sent_list_efb(0) still lists every row', count($sms->get_sms_sent_list_efb(0)) === 3);
sm('get_sms_sent_list_efb(9) lists form 9', count($sms->get_sms_sent_list_efb(9)) === 1);
$sms->delete_sms_efb('0 OR 1=1');
sm('delete_sms_efb("0 OR 1=1") deletes nothing', $GLOBALS['wpdb']->count_rows('wp_emsfb_sms_sent_list') === 3);
$first_id = (int) $GLOBALS['wpdb']->raw('SELECT MIN(id) AS i FROM wp_emsfb_sms_sent_list')[0]->i;
$sms->delete_sms_efb($first_id);
sm('delete_sms_efb(id) deletes that row', $GLOBALS['wpdb']->count_rows('wp_emsfb_sms_sent_list') === 2);

echo "\n========================================\nRESULTS: $pass passed, $fail failed\n========================================\n";
exit($fail > 0 ? 1 : 0);
}
