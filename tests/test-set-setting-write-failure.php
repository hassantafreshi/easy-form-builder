<?php
/**
 * efbFunction::set_setting_Emsfb() (includes/functions.php): when the settings table write fails, the method answers
 * false and leaves the emsfb_settings option, the transient, emsfb_pro and the object caches as they were; a write
 * that works (an update of 0 rows because nothing changed included) caches the new JSON as before, and the wipe guard
 * (a key the caller never sent keeps its stored value) is unchanged. BUGS-FOUND (F17 brief, 2026-11-15).
 *
 * The real method is read out of includes/functions.php and run on an in-memory table whose writes can fail
 * (return false) or report an error ($wpdb->last_error).
 *
 * Run: php tests/test-set-setting-write-failure.php
 */

define('ABSPATH', __DIR__ . '/');
$GLOBALS['T'] = array('options' => array(), 'transients' => array(), 'cache_deleted' => 0, 'mode' => 'ok', 'cleared' => 0);
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['T']['options']) ? $GLOBALS['T']['options'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['T']['options'][$k] = $v; return true; }
function set_transient($k, $v, $t = 0) { $GLOBALS['T']['transients'][$k] = $v; return true; }
function wp_cache_delete($k, $g = '') { $GLOBALS['T']['cache_deleted']++; return true; }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function get_current_user_id() { return 1; }
function wp_date($f) { return gmdate($f); }
function emsfb_json_normalize_efb($raw) { return (string) $raw; }
function emsfb_json_decode_efb($raw, $assoc = false) { return json_decode((string) $raw, $assoc); }
function get_setting_Emsfb($mode = 'decoded') { if ($mode === '_clear_cache') $GLOBALS['T']['cleared']++; return true; }

/** emsfb_setting in memory. mode: ok | false (the write returns false) | error (it returns 1 but last_error is set) | same (update: 0 rows). */
class EfbSetDb {
	public $prefix = 'wp_';
	public $rows = array();
	public $last_error = '';
	public function last() { return $this->rows ? end($this->rows)['setting'] : null; }
	public function get_var($q) {
		$this->last_error = '';
		if (stripos($q, 'COUNT(*)') !== false) return count($this->rows);
		if (stripos($q, 'MAX(id)') !== false) return $this->rows ? max(array_keys($this->rows)) : null;
		if (stripos($q, 'SELECT setting') !== false) return $this->last();
		return null;
	}
	private function fails() {
		$this->last_error = '';
		if ($GLOBALS['T']['mode'] === 'false') { $this->last_error = 'MySQL server has gone away'; return false; }
		if ($GLOBALS['T']['mode'] === 'error') { $this->last_error = 'Disk full'; return 1; }
		return null;
	}
	public function update($t, $data, $where) {
		$f = $this->fails(); if ($f !== null) return $f;
		if ($GLOBALS['T']['mode'] === 'same') return 0;
		$this->rows[$where['id']] = array_merge($this->rows[$where['id']], $data); return 1;
	}
	public function insert($t, $data) {
		$f = $this->fails(); if ($f !== null) return $f;
		$this->rows[count($this->rows) + 1] = $data; return 1;
	}
}
$GLOBALS['wpdb'] = new EfbSetDb();

$src = file_get_contents(dirname(__DIR__) . '/includes/functions.php');
$a = strpos($src, '/** Whether this request holds the settings lock');  // the lock helper, then the method
$b = strpos($src, "\n    }\n", strpos($src, 'public static function set_setting_Emsfb'));
if ($a === false || $b === false) { echo "[FAIL] set_setting_Emsfb() not found\n\nRESULTS: 0 passed, 1 failed\n"; exit(1); }
$method = substr($src, $a, $b - $a + 6);
eval('class EfbFnSet { ' . $method . ' }');

$pass = 0; $fail = 0;
function t($name, $ok, $info = '') {
	global $pass, $fail;
	if ($ok) { echo "[PASS] {$name}\n"; $pass++; return; }
	echo "[FAIL] {$name}" . ($info !== '' ? ": {$info}" : '') . "\n"; $fail++;
}
function snap() { return array($GLOBALS['T']['options'], $GLOBALS['T']['transients'], $GLOBALS['T']['cache_deleted'], $GLOBALS['T']['cleared']); }

// The guard is exactly as it was: the wipe-guard block is in the method, byte for byte.
t('the wipe guard is kept (a key absent from the new payload keeps its stored value)', strpos($method, 'if (!property_exists($new_decoded, $k)) {') !== false && strpos($method, '$new_decoded->$k = $v;') !== false);

// 1. A first save into an empty table (insert) that works.
$ok = EfbFnSet::set_setting_Emsfb(array('emailSupporter' => 'a@site.test', 'package_type' => 3, 'smtp' => '1'));
t('insert works: true', $ok === true);
t('insert works: the option and the transient hold the JSON', isset($GLOBALS['T']['options']['emsfb_settings']) && strpos($GLOBALS['T']['options']['emsfb_settings'], 'a@site.test') !== false && isset($GLOBALS['T']['transients']['emsfb_settings_transient']));
t('insert works: emsfb_pro follows package_type', (int) get_option('emsfb_pro') === 3);
t('insert works: the caches are cleared', $GLOBALS['T']['cache_deleted'] === 4 && $GLOBALS['T']['cleared'] === 1);
EfbFnSet::set_setting_Emsfb(array('emailSupporter' => 'a@site.test', 'package_type' => 3, 'smtp' => '1'));
EfbFnSet::set_setting_Emsfb(array('emailSupporter' => 'a@site.test', 'package_type' => 3, 'smtp' => '1'));
t('three rows: later saves update the last row', count($GLOBALS['wpdb']->rows) === 3);

// 2. An update that fails (false).
$before = snap();
$GLOBALS['T']['mode'] = 'false';
$r = EfbFnSet::set_setting_Emsfb(array('emailSupporter' => 'b@site.test', 'package_type' => 1, 'smtp' => '1'));
t('update returns false: the method answers false', $r === false);
t('update returns false: option, transient, emsfb_pro and caches untouched', snap() === $before, json_encode(snap()));
t('update returns false: the table still holds the old settings', strpos((string) $GLOBALS['wpdb']->last(), 'a@site.test') !== false);

// 3. An update that reports an error in last_error.
$GLOBALS['T']['mode'] = 'error';
$r = EfbFnSet::set_setting_Emsfb(array('emailSupporter' => 'c@site.test', 'package_type' => 1, 'smtp' => '1'));
t('$wpdb->last_error after the write: false', $r === false);
t('$wpdb->last_error after the write: nothing cached', snap() === $before);

// 4. An update of 0 rows (the same JSON again) is not a failure.
$GLOBALS['T']['mode'] = 'same';
$r = EfbFnSet::set_setting_Emsfb(array('emailSupporter' => 'a@site.test', 'package_type' => 3, 'smtp' => '1'));
t('update of 0 rows (nothing changed): true, caches cleared', $r === true && $GLOBALS['T']['cache_deleted'] === $before[2] + 4);

// 5. The wipe guard on a working update: a key the caller did not send keeps its stored value.
$GLOBALS['T']['mode'] = 'ok';
$r = EfbFnSet::set_setting_Emsfb(array('emailSupporter' => 'd@site.test', 'package_type' => 3));
$row = json_decode((string) $GLOBALS['wpdb']->last(), true);
t('wipe guard: smtp (not sent) kept in the table, the new e-mail written', $r === true && isset($row['smtp']) && $row['smtp'] === '1' && $row['emailSupporter'] === 'd@site.test');
t('wipe guard: the option holds the merged JSON', strpos($GLOBALS['T']['options']['emsfb_settings'], '"smtp":"1"') !== false);

// 6. An insert that fails (an empty table on a new site).
$GLOBALS['wpdb']->rows = array();
$GLOBALS['T'] = array('options' => array(), 'transients' => array(), 'cache_deleted' => 0, 'mode' => 'false', 'cleared' => 0);
$r = EfbFnSet::set_setting_Emsfb(array('emailSupporter' => 'e@site.test', 'package_type' => 1));
t('insert returns false: false, no option, no transient, no emsfb_pro, no cache work', $r === false && !$GLOBALS['T']['options'] && !$GLOBALS['T']['transients'] && $GLOBALS['T']['cache_deleted'] === 0 && $GLOBALS['T']['cleared'] === 0);

// 7. Empty input and broken JSON are refused before any write (as before).
t('empty settings: false', EfbFnSet::set_setting_Emsfb(array()) === false);
t('a JSON string that does not decode: false', EfbFnSet::set_setting_Emsfb('{broken') === false);

echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
