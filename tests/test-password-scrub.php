<?php
/**
 * SEC-04 follow-up (owner decision 6), without WordPress: the one-time scrub of password answers stored before 4.3.2
 * and the exports of such entries.
 *
 *  - Password_Scrub::MASK is _Public::PASSWORD_MASK_EFB (the value new entries store);
 *  - scrub_content(): a password field, the register form's two rows (type pswRegister or ids passwordRegisterEFB /
 *    repasswordRegisterEFB), the login row (passwordlogin) become the mask; an empty answer stays empty; a masked one
 *    and every other answer stay as they were (bytes untouched when nothing changes); content that is not JSON is
 *    reported (null), never rewritten;
 *  - the update to 4.3.2 starts it (run_upgrade_tasks_efb), WP-Cron and an administrator's screen continue it, in
 *    id slices with a time budget, behind a lock; its hook is one deactivation and uninstall remove;
 *  - until it has reached an entry, the exports never carry its password: the server export (CSV and Excel,
 *    Entries::export_row_text()) and the screen's CSV (efb_csv_mask_secrets_efb() in list_form-efb.js, run by node).
 * The real run (a copy of the test site, WP-Cron): tests/test-password-scrub-site.php.
 *
 * Run: php tests/test-password-scrub.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
$root = dirname(__DIR__);
$file = $root . '/includes/class-Emsfb-password-scrub.php';
t('includes/class-Emsfb-password-scrub.php exists', is_file($file), true);
if (!is_file($file)) { echo "\nRESULTS: {$passed} passed, {$failed} failed\n"; exit(1); }
require __DIR__ . '/entries-test-db.php';
if (!function_exists('emsfb_json_normalize_efb')) { function emsfb_json_normalize_efb($s) { return str_replace('\\"', '"', (string) $s); } }
require_once $root . '/includes/class-Emsfb-privacy.php';
require_once $file;
require_once $root . '/includes/admin/class-Emsfb-entries.php';
use Emsfb\Password_Scrub as P;
$S = "\u{2022}\u{2022}\u{2022}\u{2022}";

$pub = (string) file_get_contents($root . '/includes/class-Emsfb-public.php');
preg_match('/const PASSWORD_MASK_EFB = "([^"]+)";/', $pub, $m);
t('MASK is _Public::PASSWORD_MASK_EFB (four bullets)', P::MASK, isset($m[1]) ? json_decode('"' . str_replace('\\u{2022}', '\\u2022', $m[1]) . '"') : '?');
t('… which is four U+2022', P::MASK, $S);

$enc = function ($rows) { return \Emsfb\Privacy::encode_content($rows); };
$raw = $enc(array(
	array('id_' => 'pw1', 'name' => 'Password', 'type' => 'password', 'value' => 'Secr3t!"x'),
	array('id_' => 'pw2', 'type' => 'password', 'value' => ''),
	array('id_' => 'em1', 'type' => 'email', 'value' => 'a@example.test'),
	array('id_' => 'tx1', 'type' => 'text', 'value' => 'the password is long'),
	array('id_' => 'passwordRegisterEFB', 'type' => 'pswRegister', 'value' => 'hunter2'),
	array('id_' => 'repasswordRegisterEFB', 'type' => 'text', 'value' => 'hunter2'),
	array('id_' => 'passwordlogin', 'type' => 'text', 'value' => 'loginpass'),
	array('id_' => 'pl', 'type' => 'pswLogin', 'value' => 'x1'),
	array('id_' => 'pw3', 'type' => 'password', 'value' => $S),
));
$r = P::scrub_content($raw);
$rows = \Emsfb\Privacy::decode_content($r[0]);
$v = array(); foreach ($rows as $x) $v[$x['id_']] = $x['value'];
t('a password field, the register rows, the login rows: the mask', array($v['pw1'], $v['passwordRegisterEFB'], $v['repasswordRegisterEFB'], $v['passwordlogin'], $v['pl']), array($S, $S, $S, $S, $S));
t('an empty password answer stays empty; a masked one stays', array($v['pw2'], $v['pw3']), array('', $S));
t('other answers (an e-mail, a text that says "password") unchanged', array($v['em1'], $v['tx1']), array('a@example.test', 'the password is long'));
t('five values masked; the password rows\' ids reported (for the history)', array($r[1], $r[2]), array(5, array('pw1', 'pw2', 'passwordRegisterEFB', 'repasswordRegisterEFB', 'passwordlogin', 'pl', 'pw3')));
t('written in the storage format of the entries (quotes escaped)', strpos($r[0], '\\"id_\\":\\"pw1\\"') !== false, true);
$clean = $enc(array(array('id_' => 'pw1', 'type' => 'password', 'value' => $S), array('id_' => 'tx1', 'type' => 'text', 'value' => 'password')));
$r2 = P::scrub_content($clean);
t('nothing to mask: the content comes back byte for byte, 0 values', array($r2[0] === $clean, $r2[1]), array(true, 0));
t('content that is not JSON: reported (null), never rewritten', P::scrub_content('password=plain; not json'), null);

/* ---- the run ---- */
$src = (string) file_get_contents($file);
$main = (string) file_get_contents($root . '/includes/class-Emsfb.php');
t('the update to 4.3.2 starts it (run_upgrade_tasks_efb, old version < 4.3.2)', strpos($main, "if (version_compare((string) \$old_version, '4.3.2', '<') && class_exists('\\Emsfb\\Password_Scrub')) {\n            \\Emsfb\\Password_Scrub::start();") !== false, true);
t('it is loaded and registered on every request (the event fires in wp-cron.php)', strpos($main, "require_plugin_file_efb('includes/class-Emsfb-password-scrub.php')") !== false && strpos($main, '\\Emsfb\\Password_Scrub::register();') !== false, true);
$start = substr($src, strpos($src, 'public static function start()'), strpos($src, 'public static function run(') - strpos($src, 'public static function start()'));
t('start() schedules the event, it never runs inside the update request', strpos($start, 'self::schedule(30);') !== false && strpos($start, 'self::run(') === false && strpos($start, 'self::slice(') === false, true);
t('slices by entry id with a time budget, behind a lock', strpos($src, 'msg_id > %d AND msg_id <= %d') !== false && strpos($src, "set_transient(\$lock, 1, 120)") !== false && strpos($src, 'microtime(true) < $until') !== false, true);
t('an administrator\'s screen continues it when WP-Cron has not run it for an hour', strpos($src, "add_action('admin_init', array(__CLASS__, 'maybe_continue')") !== false && strpos($src, 'HOUR_IN_SECONDS') !== false, true);
t('a finished scrub is never started again (idempotent)', (bool) preg_match("/in_array\(\\\$log\['state'\], array\('running', 'done'\), true\)\) \{\s*return \\\$log;/", $src), true);
t('logged: option and one PHP error-log line when it masked something', strpos($src, "const LOG_OPTION = 'emsfb_password_scrub';") !== false && strpos($src, 'error_log(sprintf(') !== false, true);
require_once $root . '/includes/class-Emsfb-lifecycle.php';
t('its hook is removed on deactivation and uninstall', in_array(P::HOOK, \Emsfb\Lifecycle::CRON_HOOKS, true), true);

/* ---- the server export ---- */
$m = new ReflectionMethod('\\Emsfb\\Entries', 'export_row_text');
$m->setAccessible(true);
$a = array('sep_multi' => ' | ', 'opt_val' => 'label');
t('server export: a password field as typed → the mask', $m->invoke(null, array('id_' => 'pw1', 'type' => 'password', 'value' => 'Secr3t'), 1, $a), $S);
t('server export: the register and login rows → the mask', array($m->invoke(null, array('id_' => 'passwordRegisterEFB', 'type' => 'pswRegister', 'value' => 'hunter2'), 1, $a), $m->invoke(null, array('id_' => 'passwordlogin', 'type' => 'text', 'value' => 'p'), 1, $a)), array($S, $S));
t('server export: an empty password answer stays empty; a text answer is itself', array($m->invoke(null, array('id_' => 'pw2', 'type' => 'password', 'value' => ''), 1, $a), $m->invoke(null, array('id_' => 'tx', 'type' => 'text', 'value' => 'password'), 1, $a)), array('', 'password'));

/* ---- the screen's CSV (node) ---- */
$js = (string) file_get_contents($root . '/includes/admin/assets/js/list_form-efb.js');
preg_match('/function efb_csv_mask_secrets_efb\(content\) \{.*?\n\}/s', $js, $fm);
t('list_form-efb.js has efb_csv_mask_secrets_efb() and the CSV rows pass through it', isset($fm[0]) && strpos($js, "(typeof efb_csv_mask_secrets_efb === 'function' ? efb_csv_mask_secrets_efb : (c) => c)(") !== false, true);
if (isset($fm[0]) && trim((string) shell_exec('command -v node')) !== '') {
	$prog = $fm[0] . "\nconsole.log(JSON.stringify(efb_csv_mask_secrets_efb(" . json_encode(array(
		array('id_' => 'pw1', 'type' => 'password', 'value' => 'Secr3t'), array('id_' => 'passwordRegisterEFB', 'type' => 'pswRegister', 'value' => 'h'),
		array('id_' => 'passwordlogin', 'type' => 'text', 'value' => 'p'), array('id_' => 'pw2', 'type' => 'password', 'value' => ''), array('id_' => 'tx', 'type' => 'text', 'value' => 'password'),
	)) . ').map((r) => r.value)));';
	$tmpf = tempnam(sys_get_temp_dir(), 'efbpw') . '.js';
	file_put_contents($tmpf, $prog);
	$out = trim((string) shell_exec('node ' . escapeshellarg($tmpf) . ' 2>&1'));
	@unlink($tmpf);
	t('screen CSV: password, register and login rows → the mask; empty stays empty; text stays', json_decode($out, true), array($S, $S, $S, '', 'password'));
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
