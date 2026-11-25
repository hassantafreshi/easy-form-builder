<?php
/**
 * LIFE-01 (owner decision 5), without WordPress: deactivation and uninstall.php cover everything the code stores.
 *
 *  - uninstall.php exists, stops unless WordPress runs it (WP_UNINSTALL_PLUGIN) and hands over to Emsfb\Lifecycle;
 *  - every WP-Cron hook the code schedules (core, emsfb.php and the add-ons in vendor/ or vendores-efb) is one
 *    Lifecycle::clear_cron() removes: listed in CRON_HOOKS or starting with emsfb_;
 *  - every option name the code writes is one delete_options() removes (emsfb_/Emsfb_, OPTION_PREFIXES, OPTIONS),
 *    every user meta key one delete_user_meta() removes, every post meta key delete_post_meta() / delete_media();
 *  - the deactivation handler clears every event through Lifecycle (it cleared only four named hooks);
 *  - the switch "Delete all data when the plugin is deleted" (prv_delete_on_uninstall): off by default, '1' when
 *    ticked, read from the stored settings row by setting_on() (also a double-escaped row);
 *  - the Privacy tab shows the switch, sends it with the settings and its phrases are in the phrase list.
 *
 * Run: php tests/test-lifecycle-uninstall-lists.php
 */

$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . json_encode($expected) . ', got ' . json_encode($actual) . "\n"; $failed++;
}

$root = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', sys_get_temp_dir() . '/');

/* ---- uninstall.php ---- */
$un = @file_get_contents($root . '/uninstall.php');
t('uninstall.php exists', is_string($un), true);
$un = (string) $un;
t('uninstall.php stops unless WordPress runs it (WP_UNINSTALL_PLUGIN)', (bool) preg_match("/if\s*\(\s*!\s*defined\(\s*'WP_UNINSTALL_PLUGIN'\s*\)\s*\)\s*\{?\s*exit/", $un), true);
t('uninstall.php hands over to Emsfb\\Lifecycle::uninstall()', strpos($un, "class-Emsfb-lifecycle.php") !== false && strpos($un, 'Lifecycle::uninstall()') !== false, true);

$lc_file = $root . '/includes/class-Emsfb-lifecycle.php';
t('includes/class-Emsfb-lifecycle.php exists', is_file($lc_file), true);
if (!is_file($lc_file)) {
	echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
	exit(1);
}
require_once $lc_file;
$L = '\\Emsfb\\Lifecycle';

/* ---- the sources ---- */
function efb_lc_php_files($dir) {
	$out = array();
	if (!is_dir($dir)) return $out;
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS));
	foreach ($it as $f) {
		$p = str_replace('\\', '/', $f->getPathname());
		if (substr($p, -4) !== '.php' || preg_match('#/(tests?|node_modules|vendor/composer|composer|\.git|languages|e2e)/#', $p)) continue;
		$out[] = $p;
	}
	return $out;
}
$files = array_merge(efb_lc_php_files($root . '/includes'), array($root . '/emsfb.php'));
$addons = is_dir('/home/user/vendores-efb') ? '/home/user/vendores-efb' : $root . '/vendor';
$files = array_merge($files, efb_lc_php_files($addons));
t('the scan reads the core and the add-ons (' . count($files) . ' PHP files)', count($files) > 150, true);

$consts = array();
$src = array();
foreach ($files as $f) {
	$code = (string) file_get_contents($f);
	$src[$f] = $code;
	if (preg_match_all("/const\s+([A-Z0-9_]+)\s*=\s*'([A-Za-z0-9_@\-]+)'/", $code, $m, PREG_SET_ORDER)) {
		foreach ($m as $x) $consts[basename($f)][$x[1]] = $x[2];
	}
}
/** A literal or a self::CONST first argument, resolved in that file. */
function efb_lc_names($code, $file, $consts, $call_re) {
	$out = array();
	if (preg_match_all('/' . $call_re . '\(\s*(?:\$[^,]*?,\s*)?(?:\'([A-Za-z0-9_@\-]+)\'|"([A-Za-z0-9_@\-]+)"|self::([A-Z0-9_]+))/', $code, $m, PREG_SET_ORDER)) {
		foreach ($m as $x) {
			if (!empty($x[1])) $out[] = $x[1];
			elseif (!empty($x[2])) $out[] = $x[2];
			elseif (!empty($x[3]) && isset($consts[basename($file)][$x[3]])) $out[] = $consts[basename($file)][$x[3]];
		}
	}
	return $out;
}

/* ---- cron hooks ---- */
$hooks = array();
foreach ($src as $f => $code) {
	if (preg_match_all('/wp_schedule_(?:single_)?event\s*\(([^;]*?)\)\s*;/s', $code, $m)) {
		foreach ($m[1] as $args) {
			if (preg_match_all("/(?<!\\[)'([a-z0-9_]+)'/", $args, $lit)) foreach ($lit[1] as $h) if (strpos($h, '_') !== false && !in_array($h, array('daily', 'hourly', 'twicedaily', 'weekly', 'emsfb_weekly'), true)) $hooks[$h] = basename($f);
			if (preg_match_all('/self::([A-Z0-9_]+)/', $args, $c)) foreach ($c[1] as $k) if (isset($consts[basename($f)][$k])) $hooks[$consts[basename($f)][$k]] = basename($f);
		}
	}
}
$hooks['emsfb_language_packs_daily'] = 'class-Emsfb-language-packs.php (prefix + key)';
$hooks['emsfb_language_packs_sync'] = 'class-Emsfb-language-packs.php (prefix + key)';
t('the scan finds the scheduled hooks (' . count($hooks) . ')', count($hooks) >= 15, true);
$missing = array();
foreach ($hooks as $h => $where) {
	if (!in_array($h, $L::CRON_HOOKS, true) && stripos($h, 'emsfb_') !== 0) $missing[] = "$h ($where)";
}
t('every scheduled hook is removed on deactivation and uninstall (CRON_HOOKS or emsfb_)', $missing, array());
foreach (array('emsfb_daily_maintenance', 'emsfb_revalidate_license', 'efb_entries_stats_backfill', 'emsfb_check_file_access_after_activation', 'delete_preview_page_efb') as $h) {
	t("CRON_HOOKS names {$h} (left behind before 4.3.2)", in_array($h, $L::CRON_HOOKS, true), true);
}

/* ---- options ---- */
$opt_covered = function ($name) use ($L) {
	if (stripos($name, 'emsfb_') === 0 || stripos($name, 'emsfb') === 0) return true;
	if (in_array($name, $L::OPTIONS, true)) return true;
	foreach ($L::OPTION_PREFIXES as $p) if (strpos($name, $p) === 0) return true;
	return strpos($name, 'preview@') === 0; // delete_preview_pages()
};
$opt_missing = array();
$not_ours = array('WPLANG', 'active_plugins', 'admin_email', 'blog_charset', 'date_format', 'start_of_week', 'time_format', 'trp_settings', 'efb_b8_snap_');
foreach ($src as $f => $code) {
	foreach (efb_lc_names($code, $f, $consts, '(?:update_option|add_option)') as $o) {
		if (in_array($o, $not_ours, true) || strpos($o, 'efb_e2e') === 0) continue;
		if (!$opt_covered($o)) $opt_missing[] = "$o (" . basename($f) . ')';
	}
	if (preg_match_all("/(?:update_option|add_option)\(\s*'(efb_[a-z0-9_]+_)'\s*\./", $code, $m)) {
		foreach ($m[1] as $o) if ($o !== 'efb_b8_snap_' && !$opt_covered($o . 'x')) $opt_missing[] = "$o… (" . basename($f) . ')';
	}
	if (preg_match_all('/update_option\(\s*self::([A-Z_]+)\s*\./', $code, $m)) {
		foreach ($m[1] as $k) {
			$o = isset($consts[basename($f)][$k]) ? $consts[basename($f)][$k] : '';
			if ($o !== '' && !$opt_covered($o . 'x')) $opt_missing[] = "$o… (" . basename($f) . ')';
		}
	}
}
// The idempotency records of Form_Runtime: 'efb_op_' . …
t('Form_Runtime\'s operation records (efb_op_…) are covered', $opt_covered('efb_op_1234abcd_ff'), true);
t('every option the code writes is removed with the data (emsfb_, OPTION_PREFIXES, OPTIONS)', array_values(array_unique($opt_missing)), array());

/* ---- user meta ---- */
$um_covered = function ($key) use ($L) {
	if (in_array($key, $L::USER_META, true)) return true;
	foreach ($L::USER_META_PREFIXES as $p) if (strpos($key, $p) === 0) return true;
	return false;
};
$um_missing = array();
foreach ($src as $f => $code) {
	foreach (efb_lc_names($code, $f, $consts, '(?:update_user_meta|add_user_meta)') as $k) {
		if (in_array($k, array('first_name', 'last_name'), true)) continue;
		if (!$um_covered($k)) $um_missing[] = "$k (" . basename($f) . ')';
	}
	if (preg_match_all("/update_user_meta\([^,]+,\s*'(efb_[a-z_]+_)'\s*\./", $code, $m)) foreach ($m[1] as $k) if (!$um_covered($k . '1')) $um_missing[] = "$k… (" . basename($f) . ')';
}
t('every user meta key the code writes is removed with the data', array_values(array_unique($um_missing)), array());
foreach (array('efb_dt_card_dismissed', 'efb_pending_verification', 'efb_cols_12', 'emsfb_notice_snooze') as $k) t("user meta {$k} is covered", $um_covered($k), true);

/* ---- post meta ---- */
$pm_missing = array();
foreach ($src as $f => $code) {
	foreach (efb_lc_names($code, $f, $consts, '(?:update_post_meta|add_post_meta)') as $k) {
		if (in_array($k, array('_efb_e2e'), true)) continue;
		if (!in_array($k, $L::POST_META, true)) $pm_missing[] = "$k (" . basename($f) . ')';
	}
}
t('every post meta key the code writes is removed with the data', array_values(array_unique($pm_missing)), array());

/* ---- deactivation ---- */
$main = (string) file_get_contents($root . '/includes/class-Emsfb.php');
preg_match('/function plugin_deactivation_cleanup_efb\([^)]*\)\s*\{(.*?)\n    \}\n/s', $main, $m);
$body = isset($m[1]) ? $m[1] : '';
t('the deactivation handler clears the events through Lifecycle::deactivate()', strpos($body, 'Lifecycle::deactivate(') !== false, true);
t('… once more at shutdown while the plugin is inactive', strpos($body, "add_action('shutdown'") !== false, true);
t('the deactivation handler takes the network flag', (bool) preg_match('/function plugin_deactivation_cleanup_efb\(\s*\$network_wide\s*=\s*false\s*\)/', $main), true);
t('Lifecycle::deactivate() visits every site on a network deactivation', (bool) preg_match('/function deactivate\(.*?switch_to_blog.*?clear_cron\(\).*?restore_current_blog/s', file_get_contents($lc_file)), true);

/* ---- the switch ---- */
t('Lifecycle::SETTING is prv_delete_on_uninstall', $L::SETTING, 'prv_delete_on_uninstall');
t('setting_on: no row', $L::setting_on(null), false);
t('setting_on: the key absent (the default)', $L::setting_on('{"prv_days":"0"}'), false);
t('setting_on: off', $L::setting_on('{"prv_delete_on_uninstall":""}'), false);
t('setting_on: on', $L::setting_on('{"prv_days":"0","prv_delete_on_uninstall":"1"}'), true);
t('setting_on: on, a double-escaped row', $L::setting_on('{\\"prv_delete_on_uninstall\\":\\"1\\"}'), true);
t('setting_on: on, a row JSON cannot read', $L::setting_on('{"text":"broken \\x","prv_delete_on_uninstall":"1"'), true);
t('setting_on: "0" is off', $L::setting_on('{"prv_delete_on_uninstall":"0"}'), false);
t('setting_on: unrelated "1"', $L::setting_on('{"prv_files":"1"}'), false);

$prv = (string) file_get_contents($root . '/includes/class-Emsfb-privacy.php');
t('Privacy::setting_defaults(): prv_delete_on_uninstall off by default', (bool) preg_match("/'prv_delete_on_uninstall'\s*=>\s*''/", $prv), true);
t('Privacy::sanitize_setting(): prv_delete_on_uninstall is a checkbox (\'1\' or \'\')', (bool) preg_match("/case 'prv_files':\s*case 'prv_delete_on_uninstall':\s*return in_array/", $prv), true);
t('the default settings carry prv_delete_on_uninstall = \'\'', (bool) preg_match("/defaults->prv_delete_on_uninstall\s*=\s*''/", $main), true);

$js = (string) file_get_contents($root . '/includes/admin/assets/js/list_form-efb.js');
t('Settings › Privacy shows the switch (unchecked unless the setting is \'1\')', strpos($js, 'id="prvDeleteOnUninstall_efb" ${on ? \'checked\' : \'\'}') !== false && strpos($js, "const on = prv.prv_delete_on_uninstall === '1';") !== false, true);
t('the settings save sends prv_delete_on_uninstall', strpos($js, "out.prv_delete_on_uninstall = un.checked ? '1' : '';") !== false, true);
t('the warning is tied to the switch (aria-describedby, shown while ticked)', strpos($js, 'aria-describedby="prvUninstallHelp_efb prvUninstallWarn_efb"') !== false && strpos($js, 'w.hidden = !el.checked;') !== false, true);
$fn = (string) file_get_contents($root . '/includes/functions.php');
foreach (array('prvUninstallTitle', 'prvDeleteOnUninstall', 'prvUninstallHelp', 'prvUninstallWarn') as $k) {
	t("phrase {$k} is in the phrase list, with a translators comment", (bool) preg_match('#/\* translators:[^*]*\*/\s*"' . $k . '" =>#', $fn), true);
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
