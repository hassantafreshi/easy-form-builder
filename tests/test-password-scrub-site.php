<?php
/**
 * SEC-04 follow-up (owner decision 6) on a real WordPress: password answers stored before 4.3.2 become the mask.
 * Runs on a PRIVATE COPY of the test site (the shared site is only read; its plugin folder is copied, links
 * followed). Through WP-CLI on the copy:
 *  - entries as 4.3.1 stored them: a form's password field, a register form's password rows (passwordRegisterEFB,
 *    repasswordRegisterEFB, type pswRegister), a login row, a value already masked, a text answer that only
 *    mentions "password", an entry whose id is far past the others (slices by id), and edits of a password field
 *    in the entries' history (emsfb_msg_log_);
 *  - before the scrub: the CSV / Excel export of those entries already shows the mask (Entries::export_rows());
 *  - the update from 4.3.1 starts it (Emsfb::run_upgrade_tasks_efb('4.3.1', …)) and schedules emsfb_password_scrub;
 *    WP-Cron runs it (wp cron event run) until the log says done;
 *  - every password value of every entry of the copy is the mask afterwards, the history too; every other answer
 *    and the other columns are unchanged; the log counts what was done; a second start / run changes nothing.
 * Prints the counts (entries scanned and changed, values masked, history rows, runs).
 *
 * Needs: the test site (tests/wp-env.php), WP-CLI (EFB_E2E_WPCLI, else `wp`), rsync. Run under the e2e lock.
 */

require_once __DIR__ . '/wp-env.php';

$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . json_encode($expected) . ', got ' . json_encode($actual) . "\n"; $failed++;
}

$site = efb_test_wp_root();
if ($site === '' || !is_file($site . '/wp-load.php')) { echo "[SKIP] no WordPress site (tests/wp-env.php)\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
if (trim((string) shell_exec('command -v rsync')) === '') { echo "[SKIP] rsync is not installed\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$wpcli = trim((string) (getenv('EFB_E2E_WPCLI') ?: 'wp'));
$repo = realpath(dirname(__DIR__));
$tmp = rtrim(sys_get_temp_dir(), '/') . '/efb-pwscrub-' . getmypid() . '-' . bin2hex(random_bytes(3));
$copy = $tmp . '/site';
register_shutdown_function(function () use ($tmp) {
	if (getenv('EFB_PWSCRUB_KEEP')) { echo "(kept: {$tmp})\n"; return; }
	if (is_dir($tmp) && strpos($tmp, sys_get_temp_dir()) === 0) shell_exec('rm -rf ' . escapeshellarg($tmp));
});
function sh_efb($cmd) { $o = array(); exec($cmd . ' 2>&1', $o, $c); return array($c, implode("\n", $o)); }
function wp_efb($args) { global $wpcli, $copy; return sh_efb('cd ' . escapeshellarg($copy) . ' && ' . $wpcli . ' --allow-root --skip-themes ' . $args); }
function json_out_efb($out) { $p = strrpos($out, "\n{"); $j = json_decode(trim($p === false ? $out : substr($out, $p)), true); return is_array($j) ? $j : array('raw' => $out); }

@mkdir($copy, 0777, true);
list($c) = sh_efb('rsync -a --exclude=wp-content/plugins/easy-form-builder --exclude=wp-content/plugins/efb-wptests --exclude=wp-content/plugins/efb-customer-portal --exclude=wp-content/mu-plugins --exclude=wp-content/uploads --exclude=wp-content/debug.log ' . escapeshellarg($site . '/') . ' ' . escapeshellarg($copy . '/'));
t('a private copy of the test site', $c, 0);
$ex = '';
foreach (array('.git', 'node_modules', 'tests', 'docs', 'temp', '_workspace', 'dist', '*.zip', 'vendor/*/tests', '.claude') as $e) $ex .= ' --exclude=' . escapeshellarg($e);
list($c) = sh_efb('rsync -aL' . $ex . ' ' . escapeshellarg($repo . '/') . ' ' . escapeshellarg($copy . '/wp-content/plugins/easy-form-builder/'));
t('the plugin copied in as a real folder', $c === 0 && trim((string) shell_exec('find ' . escapeshellarg($copy) . ' -type l | head -1')) === '', true);

$php = $tmp . '/scrub.php';
file_put_contents($php, <<<'PHP'
<?php
global $wpdb;
$step = getenv('EFB_STEP');
$t = $wpdb->prefix . 'emsfb_msg_';
$L = $wpdb->prefix . 'emsfb_msg_log_';
$S = "\u{2022}\u{2022}\u{2022}\u{2022}";
$enc = function ($rows) { return \Emsfb\Privacy::encode_content($rows); };
$secret = function ($r) { return is_array($r) && (in_array($r['type'] ?? '', array('password', 'pswRegister', 'pswLogin'), true) || in_array($r['id_'] ?? '', array('passwordRegisterEFB', 'repasswordRegisterEFB', 'passwordlogin'), true)); };
/* Every password value of the table, and a fingerprint of everything else. */
$census = function () use ($wpdb, $t, $secret, $S) {
	$plain = 0; $masked = 0; $other = array();
	foreach ($wpdb->get_results("SELECT msg_id, form_id, track, date, read_, content FROM `$t` ORDER BY msg_id", ARRAY_A) as $e) {
		$rows = \Emsfb\Privacy::decode_content($e['content']);
		if (!is_array($rows)) { $other[] = $e['msg_id'] . ':' . md5($e['content']); continue; }
		$keep = array();
		foreach ($rows as $r) {
			if ($secret($r) && isset($r['value']) && is_scalar($r['value']) && (string) $r['value'] !== '') { if ($r['value'] === $S) $masked++; else $plain++; $r['value'] = '*'; }
			$keep[] = $r;
		}
		$other[] = $e['msg_id'] . ':' . $e['form_id'] . $e['track'] . $e['date'] . $e['read_'] . ':' . md5(wp_json_encode($keep));
	}
	return array('plain' => $plain, 'masked' => $masked, 'other' => md5(implode('|', $other)));
};
if ($step === 'seed') {
	$form = (int) $wpdb->get_var("SELECT form_id FROM {$wpdb->prefix}emsfb_form ORDER BY form_id LIMIT 1");
	$base = array('form_id' => $form, 'ip' => '', 'form_title_x' => 'PW', 'read_' => 0, 'read_by' => '', 'date' => '2025-05-01 10:00:00');
	$ins = function ($track, $rows, $id = null) use ($wpdb, $t, $base, $enc) { $d = $base + array('track' => $track, 'content' => $enc($rows)); if ($id) $d['msg_id'] = $id; $wpdb->insert($t, $d); return (int) $wpdb->insert_id; };
	$a = $ins('PWA1', array(array('id_' => 'pw1', 'name' => 'Password', 'type' => 'password', 'value' => 'Secr3t!"x', 'amount' => 1), array('id_' => 'em1', 'name' => 'Email', 'type' => 'email', 'value' => 'a@example.test', 'amount' => 2), array('id_' => 'tx1', 'name' => 'Note', 'type' => 'text', 'value' => 'my password is long', 'amount' => 3)));
	$b = $ins('PWB1', array(array('id_' => 'usernameRegisterEFB', 'type' => 'text', 'value' => 'neo'), array('id_' => 'passwordRegisterEFB', 'type' => 'pswRegister', 'value' => 'hunter2'), array('id_' => 'repasswordRegisterEFB', 'type' => 'pswRegister', 'value' => 'hunter2')));
	$c = $ins('PWC1', array(array('id_' => 'passwordlogin', 'type' => 'text', 'value' => 'loginpass')));
	$d = $ins('PWD1', array(array('id_' => 'pw1', 'type' => 'password', 'value' => $S), array('id_' => 'tx1', 'type' => 'text', 'value' => 'password reset please')));
	$far = (int) $wpdb->get_var("SELECT MAX(msg_id) FROM `$t`") + 7000;
	$e = $ins('PWE1', array(array('id_' => 'pw9', 'type' => 'password', 'value' => 'far-away')), $far);
	$log = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $L)) === $L;
	if ($log) {
		$wpdb->insert($L, array('msg_id' => $a, 'user_id' => 1, 'action' => 'edit', 'field_id' => 'pw1', 'old_value' => 'OldSecret', 'new_value' => 'Secr3t!"x', 'meta' => '', 'date' => '2025-05-02 10:00:00'));
		$wpdb->insert($L, array('msg_id' => $a, 'user_id' => 1, 'action' => 'edit', 'field_id' => 'em1', 'old_value' => 'b@example.test', 'new_value' => 'a@example.test', 'meta' => '', 'date' => '2025-05-02 10:00:00'));
	}
	delete_option(\Emsfb\Password_Scrub::LOG_OPTION);
	wp_unschedule_hook(\Emsfb\Password_Scrub::HOOK);
	// The export before the scrub (CSV and Excel read Entries::export_rows()).
	$cells = array();
	\Emsfb\Entries::export_rows(\Emsfb\Entries::export_args(array('form_id' => $form, 'ids' => array($a, $b, $c, $e))), \Emsfb\Entries::export_ids(\Emsfb\Entries::export_args(array('form_id' => $form, 'ids' => array($a, $b, $c, $e)))), function ($line) use (&$cells) { foreach ($line as $x) $cells[] = (string) $x; });
	$leak = array_values(array_filter($cells, function ($x) { return preg_match('/Secr3t|hunter2|loginpass|far-away/', $x); }));
	echo wp_json_encode(array('ids' => array($a, $b, $c, $d, $e), 'far' => $far, 'log_table' => $log, 'export_leak' => $leak, 'export_mask' => count(array_filter($cells, function ($x) use ($S) { return $x === $S; })), 'census' => $census()));
} elseif ($step === 'upgrade') {
	$m = new ReflectionMethod('Emsfb', 'run_upgrade_tasks_efb');
	$m->setAccessible(true);
	$m->invoke((new ReflectionClass('Emsfb'))->newInstanceWithoutConstructor(), '4.3.1', '4.3.2');
	echo wp_json_encode(array('log' => \Emsfb\Password_Scrub::log(), 'scheduled' => (bool) wp_next_scheduled(\Emsfb\Password_Scrub::HOOK)));
} elseif ($step === 'state') {
	$ids = array_map('intval', explode(',', (string) getenv('EFB_IDS')));
	$rows = array();
	foreach ($ids as $id) $rows[$id] = \Emsfb\Privacy::decode_content($wpdb->get_var($wpdb->prepare("SELECT content FROM `$t` WHERE msg_id = %d", $id)));
	$hist = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $L)) === $L ? $wpdb->get_results($wpdb->prepare("SELECT field_id, old_value, new_value FROM `$L` WHERE msg_id = %d ORDER BY log_id", $ids[0]), ARRAY_A) : array();
	echo wp_json_encode(array('log' => \Emsfb\Password_Scrub::log(), 'scheduled' => (bool) wp_next_scheduled(\Emsfb\Password_Scrub::HOOK), 'rows' => $rows, 'history' => $hist, 'census' => $census()));
} elseif ($step === 'again') {
	$before = $census();
	\Emsfb\Password_Scrub::start();
	\Emsfb\Password_Scrub::run(2);
	echo wp_json_encode(array('log' => \Emsfb\Password_Scrub::log(), 'census' => $census(), 'same' => $before === $census()));
}
PHP
);

/* ---- seed ---- */
list($c, $out) = sh_efb('cd ' . escapeshellarg($copy) . ' && EFB_STEP=seed ' . $wpcli . ' --allow-root --skip-themes eval-file ' . escapeshellarg($php));
$seed = json_out_efb($out);
t('seeded five entries as 4.3.1 stored them (and two history rows)', isset($seed['ids']) && count($seed['ids']) === 5 && $seed['log_table'] === true, true);
$ids = isset($seed['ids']) ? $seed['ids'] : array(0, 0, 0, 0, 0);
echo 'census before: ' . json_encode(isset($seed['census']) ? $seed['census'] : $out) . "\n";
t('before the scrub, the export carries no password as typed', isset($seed['export_leak']) ? $seed['export_leak'] : $out, array());
t('… it shows the mask in its place (5 values)', isset($seed['export_mask']) ? $seed['export_mask'] : -1, 5);
$plain_before = isset($seed['census']['plain']) ? $seed['census']['plain'] : 0;
t('the copy holds passwords as typed before the scrub (' . $plain_before . ')', $plain_before >= 5, true);

/* ---- the update starts it ---- */
list($c, $out) = sh_efb('cd ' . escapeshellarg($copy) . ' && EFB_STEP=upgrade ' . $wpcli . ' --allow-root --skip-themes eval-file ' . escapeshellarg($php));
$up = json_out_efb($out);
t('the update from 4.3.1 starts the scrub (state running, up to the last entry id)', isset($up['log']['state'], $up['log']['max_id']) ? array($up['log']['state'], $up['log']['max_id'] >= $seed['far']) : $out, array('running', true));
t('… and schedules emsfb_password_scrub (WP-Cron, not the update request)', isset($up['scheduled']) ? $up['scheduled'] : null, true);

/* ---- WP-Cron runs it ---- */
$runs = 0;
$st = array();
do {
	wp_efb('cron event run ' . 'emsfb_password_scrub');
	$runs++;
	list($c, $out) = sh_efb('cd ' . escapeshellarg($copy) . ' && EFB_STEP=state EFB_IDS=' . implode(',', $ids) . ' ' . $wpcli . ' --allow-root --skip-themes eval-file ' . escapeshellarg($php));
	$st = json_out_efb($out);
} while ($runs < 30 && isset($st['log']['state']) && $st['log']['state'] === 'running');
$log = isset($st['log']) ? $st['log'] : array();
echo 'log: ' . json_encode($log) . "\n";
echo 'census after: ' . json_encode(isset($st['census']) ? $st['census'] : $out) . "\n";
t('WP-Cron finished it (state done, ' . $runs . ' event run(s))', isset($log['state']) ? $log['state'] : null, 'done');
t('no password as typed is left in any entry of the copy', isset($st['census']['plain']) ? $st['census']['plain'] : -1, 0);
t('… every one of them is the mask now', isset($st['census']['masked']) ? $st['census']['masked'] : -1, $plain_before + (isset($seed['census']['masked']) ? $seed['census']['masked'] : 0));
t('every other answer and column of every entry is unchanged', isset($st['census']['other']) ? $st['census']['other'] : null, isset($seed['census']['other']) ? $seed['census']['other'] : 'x');
$S = "\u{2022}\u{2022}\u{2022}\u{2022}";
$val = function ($rows, $id) { foreach ((array) $rows as $r) if (is_array($r) && ($r['id_'] ?? '') === $id) return $r['value'] ?? null; return null; };
$rows = isset($st['rows']) ? $st['rows'] : array();
$r = function ($i) use ($rows, $ids) { return isset($rows[$ids[$i]]) ? $rows[$ids[$i]] : (isset($rows[(string) $ids[$i]]) ? $rows[(string) $ids[$i]] : array()); };
t('a form\'s password field: the mask; its e-mail and text answers as they were', array($val($r(0), 'pw1'), $val($r(0), 'em1'), $val($r(0), 'tx1')), array($S, 'a@example.test', 'my password is long'));
t('a register form: both password rows masked, the user name kept', array($val($r(1), 'passwordRegisterEFB'), $val($r(1), 'repasswordRegisterEFB'), $val($r(1), 'usernameRegisterEFB')), array($S, $S, 'neo'));
t('a login form\'s password row masked', $val($r(2), 'passwordlogin'), $S);
t('an entry already masked and its text unchanged', array($val($r(3), 'pw1'), $val($r(3), 'tx1')), array($S, 'password reset please'));
t('an entry 7,000 ids past the others (slices by id) masked', $val($r(4), 'pw9'), $S);
$h = isset($st['history']) ? $st['history'] : array();
t('the history: the password field\'s old and new values masked, the e-mail field\'s kept', $h, array(array('field_id' => 'pw1', 'old_value' => $S, 'new_value' => $S), array('field_id' => 'em1', 'old_value' => 'b@example.test', 'new_value' => 'a@example.test')));
t('the log counts it: entries changed, values masked, history rows', array(isset($log['entries']) && $log['entries'] >= 4, isset($log['values']) && $log['values'] === $plain_before, isset($log['history']) ? $log['history'] : null, isset($log['finished']) && $log['finished'] > 0), array(true, true, 2, true));
t('no event left once done', isset($st['scheduled']) ? $st['scheduled'] : null, false);

/* ---- idempotent ---- */
list($c, $out) = sh_efb('cd ' . escapeshellarg($copy) . ' && EFB_STEP=again ' . $wpcli . ' --allow-root --skip-themes eval-file ' . escapeshellarg($php));
$ag = json_out_efb($out);
t('starting and running it again changes nothing', isset($ag['same'], $ag['log']['runs']) ? array($ag['same'], $ag['log']['runs'], $ag['log']['state']) : $out, array(true, $log['runs'] ?? -1, 'done'));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
