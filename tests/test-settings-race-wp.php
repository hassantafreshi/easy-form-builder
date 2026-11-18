<?php
/**
 * BUGS-FOUND (F12b-L, final test d): whole-row writers of the settings row race. Each reads the whole settings object
 * (get_setting_Emsfb()), changes one key and hands the whole object back to efbFunction::set_setting_Emsfb(); two of
 * them at once used to lose a key: the later write carried the earlier reader's stale copy of the other key (the wipe
 * guard only keeps keys that are absent).
 *
 * On the real WordPress of the e2e suite (tests/e2e-wp), 8 WP-CLI processes start at the same instant; each reads the
 * settings, waits 300 ms (another request's write lands meanwhile) and writes its own key back with the whole object,
 * the way the SPLIT scan, the spam notice and the licence code do. A CAPTCHA add-on's one-key save
 * (Captcha\Admin::save() style: only its key) runs alongside. Every key must be in the row, the option and the
 * transient afterwards, and a key changed on purpose must keep its new value.
 *
 * Run (the e2e site, under the e2e lock): php tests/test-settings-race-wp.php
 */
require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n      expected " . json_encode($expected) . "\n      actual   " . json_encode($actual) . "\n";
}

$tmp = sys_get_temp_dir() . '/efb-race-' . getmypid();
@mkdir($tmp);
/** A WP-CLI process running $php in the site (started, not waited for). */
$spawn = function ($php) use ($H, $tmp) {
	$f = $tmp . '/w' . md5($php) . '.php';
	file_put_contents($f, "<?php\n" . $php);
	$parts = array_merge(preg_split('/\s+/', trim($H->wpcli)), array('eval-file', $f, '--path=' . $H->site, '--allow-root'));
	return proc_open(implode(' ', array_map('escapeshellarg', $parts)), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
};
$wait = function (array $procs) { foreach ($procs as $p) { proc_close($p); } };
/** The row, the option and the transient, decoded. */
$read = function () use ($H, $tmp) {
	$f = $tmp . '/read.php';
	file_put_contents($f, '<?php global $wpdb; $raw = $wpdb->get_var("SELECT setting FROM `{$wpdb->prefix}emsfb_setting` ORDER BY id DESC LIMIT 1");'
		. ' echo json_encode(array("row" => json_decode($raw, true), "opt" => json_decode((string) get_option("emsfb_settings"), true), "tr" => json_decode((string) get_transient("emsfb_settings_transient"), true)));');
	$parts = array_merge(preg_split('/\s+/', trim($H->wpcli)), array('eval-file', $f, '--path=' . $H->site, '--allow-root'));
	$lines = array_values(array_filter(explode("\n", (string) shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null')), 'strlen'));
	return json_decode($lines ? end($lines) : '', true);
};
$keys = function ($set, $prefix) { $o = array(); foreach ((array) $set as $k => $v) if (strpos($k, $prefix) === 0) $o[$k] = $v; ksort($o); return $o; };
/** Remove the test's keys from the row, the option and the transient (test clean-up only, straight to the table). */
$cleanup = function () use ($H, $tmp) {
	$f = $tmp . '/clean.php';
	file_put_contents($f, '<?php global $wpdb; $t = $wpdb->prefix . "emsfb_setting"; $id = $wpdb->get_var("SELECT MAX(id) FROM `$t`"); $s = json_decode($wpdb->get_var("SELECT setting FROM `$t` WHERE id=" . (int) $id));'
		. ' foreach (get_object_vars($s) as $k => $v) if (strpos($k, "efb_race_") === 0) unset($s->$k); $j = wp_json_encode($s, JSON_UNESCAPED_UNICODE);'
		. ' $wpdb->update($t, array("setting" => $j), array("id" => $id)); update_option("emsfb_settings", $j); delete_transient("emsfb_settings_transient"); echo "ok";');
	$parts = array_merge(preg_split('/\s+/', trim($H->wpcli)), array('eval-file', $f, '--path=' . $H->site, '--allow-root'));
	shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null');
};

$N = 8;
try {
	$cleanup();
	// A key the run changes on purpose, set before it starts.
	$seed = $spawn('$fn = get_efbFunction(); $s = get_setting_Emsfb(); $s->efb_race_keep = "before"; $s->efb_race_changed = "old"; $fn->set_setting_Emsfb($s, isset($s->emailSupporter) ? $s->emailSupporter : "");');
	$wait(array($seed));

	echo "--- {$N} whole-row writers at once, each changing one key ---\n";
	$at = microtime(true) + 6;
	$procs = array();
	for ($i = 0; $i < $N; $i++) {
		$extra = $i === 0 ? '$s->efb_race_changed = "new";' : '';
		$procs[] = $spawn('time_sleep_until(' . var_export($at, true) . '); $fn = get_efbFunction(); $s = get_setting_Emsfb(); usleep(300000);'
			. ' $s->efb_race_w' . $i . ' = "v' . $i . '"; ' . $extra . ' $fn->set_setting_Emsfb($s, isset($s->emailSupporter) ? $s->emailSupporter : ""); echo "done";');
	}
	// A CAPTCHA add-on's save at the same moment: only its own key.
	$procs[] = $spawn('time_sleep_until(' . var_export($at + 0.1, true) . '); get_efbFunction()->set_setting_Emsfb((object) array("efb_race_one" => "solo"), "");');
	$wait($procs);

	$r = $read();
	$want = array('efb_race_changed' => 'new', 'efb_race_keep' => 'before', 'efb_race_one' => 'solo');
	for ($i = 0; $i < $N; $i++) $want['efb_race_w' . $i] = 'v' . $i;
	ksort($want);
	t('every writer\'s key is in the settings row (none lost)', $keys($r['row'], 'efb_race_'), $want);
	t('… the option emsfb_settings holds the same keys', $keys($r['opt'], 'efb_race_'), $want);
	t('… the transient is the row or gone (never an older copy)', $r['tr'] === null || $keys($r['tr'], 'efb_race_') === $want, true);
	t('the key one writer changed keeps its new value; a key nobody changed keeps its value', array($r['row']['efb_race_changed'] ?? null, $r['row']['efb_race_keep'] ?? null), array('new', 'before'));

	echo "--- the writes still go one at a time through set_setting_Emsfb() ---\n";
	$src = file_get_contents(dirname(__DIR__) . '/includes/functions.php');
	$body = substr($src, strpos($src, 'public static function set_setting_Emsfb'), 9000);
	t('set_setting_Emsfb() takes the settings lock before it reads the stored row', (bool) preg_match('/settings_lock_efb\(\s*true\s*\).*SELECT setting FROM/s', $body), true);
	t('… and lets it go on every way out (finally)', (bool) preg_match('/\} finally \{\s*self::settings_lock_efb\(false\);/', $body), true);
	$core = file_get_contents(dirname(__DIR__) . '/includes/class-Emsfb.php');
	t('the settings repairs in get_setting_Emsfb() and the upgrade only replace the very value they read (compare-and-swap)', array(substr_count($core, "['id' => \$latest_id, 'setting' => \$original_raw]"), substr_count($core, "['id' => \$row->id, 'setting' => \$raw]"), substr_count($core, "['id' => \$row->id, 'setting' => \$row->setting]")), array(2, 1, 1));
} finally {
	$cleanup();
	array_map('unlink', glob($tmp . '/*') ?: array());
	@rmdir($tmp);
}
echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
