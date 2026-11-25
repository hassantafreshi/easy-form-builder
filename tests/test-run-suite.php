<?php
/**
 * B10 QA-01: tests/run-suite.php, the one runner of the unit suite.
 *  - It sorts the files into pure and site-backed ones (a file, or a helper it requires, that names the test site).
 *  - Pure files run in parallel with EFB_WP_ROOT=none, so none of them can fall back to the shared site.
 *  - Site files run only while the runner holds the e2e lock; when another run holds it they are skipped with a
 *    [SKIP] line (no site test starts, the exit is 0), so a unit run never overlaps an e2e run (the coordinator saw
 *    21 false failures when they did).
 *  - EFB_WP_ROOT=none or --no-site skips the site files without touching the lock.
 * The checks use fixture tests in a temporary folder and a private lock file; the shared lock is never taken.
 *
 * Run: php tests/test-run-suite.php   (no WordPress)
 */
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

$runner = "$root/tests/run-suite.php";
t('tests/run-suite.php exists and lints', is_file($runner) && strpos((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($runner) . ' 2>&1'), 'No syntax errors') !== false);
if (!is_file($runner)) { echo "\nRESULTS: $pass passed, $fail failed\n"; exit(1); }

$tmp = sys_get_temp_dir() . '/efb-run-suite-test-' . getmypid();
@mkdir($tmp, 0777, true);
$lock = "$tmp/e2e.lock";
// (Variable names built from parts: this file must not name the test site itself, or the runner would class it as site-backed.)
$LOCK_VAR = 'EFB_' . 'E2E_LOCK';
$HELD_VAR = $LOCK_VAR . '_HELD';
/** Runs the runner with an environment; returns [exit code, output]. */
$run = function (array $args, array $env = array()) use ($runner, $root, $lock, $LOCK_VAR) {
	$env = array_merge(getenv(), array($LOCK_VAR => $lock), $env);
	foreach ($env as $k => $v) if ($v === null) unset($env[$k]);
	$p = proc_open(array_merge(array(PHP_BINARY, $runner), $args), array(0 => array('file', '/dev/null', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, $root, $env);
	$out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
	fclose($pipes[1]); fclose($pipes[2]);
	return array(proc_close($p), $out);
};

/* The classification of real files. */
list($code, $list) = $run(array('--list'));
t('--list prints each file\'s kind and the totals', $code === 0 && (bool) preg_match('/^(\d+) pure, (\d+) site$/m', $list, $tot) && (int) $tot[1] > 300 && (int) $tot[2] > 100, isset($tot[0]) ? $tot[0] : substr($list, -200));
foreach (array('tests/test-human-shield-live.js' => 'site', 'tests/test-upload-multi-submit-http.php' => 'site', 'tests/test-pay-money.php' => 'pure', 'tests/test-repeater-calc.js' => 'pure', 'tests/test-release-readme.php' => 'pure') as $f => $k) {
	t("$f is $k", (bool) preg_match('#^' . $k . '\s+' . preg_quote($f, '#') . '$#m', $list));
}
t('tests/core-i18n is not part of the suite (its own batch runs it)', strpos($list, 'tests/core-i18n/') === false);

/* Fixtures: two pure files that sleep, report EFB_WP_ROOT and pass; one site file that would write a marker. */
$pure_a = "$tmp/test-fixture-a.php";
$pure_b = "$tmp/test-fixture-b.php";
$site_f = "$tmp/test-fixture-site.php";
$marker = "$tmp/site-ran.txt";
$pure_src = '<?php sleep(1); $r = getenv("EFB_WP_ROOT"); echo "EFB_WP_ROOT=$r\n"; echo $r === "none" ? "RESULTS: 1 passed, 0 failed\n" : "RESULTS: 0 passed, 1 failed\n"; exit($r === "none" ? 0 : 1);';
file_put_contents($pure_a, $pure_src);
file_put_contents($pure_b, $pure_src);
file_put_contents($site_f, '<?php $site = "http://127.0.0.1' . ':8088"; /* the test site */ file_put_contents(' . var_export($marker, true) . ', getenv("' . $HELD_VAR . '")); echo "RESULTS: 1 passed, 0 failed\n";');

$t0 = microtime(true);
list($code, $out) = $run(array('--jobs=2', '--no-site', $pure_a, $pure_b), array('EFB_WP_ROOT' => null));
$secs = microtime(true) - $t0;
t('pure files run with EFB_WP_ROOT=none even when the caller has it unset', $code === 0 && substr_count($out, '[PASS] ') === 2, $out);
t('--jobs=2 runs them side by side (two 1-second files in under 1.9 s)', $secs < 1.9, sprintf('%.2f s', $secs));
t('the summary counts files and checks', strpos($out, '2 files: 2 passed, 0 failed, 0 skipped; checks: 2 passed, 0 failed') !== false && strpos($out, 'RESULTS: 2 passed, 0 failed') !== false);

/* The lock: free → the site file runs under it; busy → skipped, nothing started. */
@unlink($marker);
list($code, $out) = $run(array($site_f), array('EFB_WP_ROOT' => null, $HELD_VAR => null));
t('with the lock free, the site file runs, one at a time, under the lock', $code === 0 && is_file($marker) && strpos($out, 'under the e2e lock (' . $lock . ')') !== false, $out);
t('the site file knows the runner holds the lock (' . $HELD_VAR . '=1)', is_file($marker) && file_get_contents($marker) === '1');

@unlink($marker);
$fh = fopen($lock, 'c');
$held = flock($fh, LOCK_EX | LOCK_NB);
$t0 = microtime(true);
list($code, $out) = $run(array($site_f, $pure_a), array('EFB_WP_ROOT' => null, $HELD_VAR => null));
$secs = microtime(true) - $t0;
t('(the test holds the private lock)', $held);
t('with the lock busy, the site file is skipped with a clear line and never started', strpos($out, '[SKIP] 1 site-backed tests not run: the e2e lock is held by another run (' . $lock . ')') !== false && !is_file($marker), $out);
t('...the pure files still run, and the run passes (a skip is not a failure)', $code === 0 && strpos($out, '[PASS] ' . $pure_a) !== false);
t('...and it does not wait for the lock by default', $secs < 6, sprintf('%.1f s', $secs));
list($code, $out) = $run(array('--lock-wait=2', $site_f), array('EFB_WP_ROOT' => null, $HELD_VAR => null));
t('--lock-wait waits that long, then skips', strpos($out, 'lock is held by another run') !== false && !is_file($marker));
flock($fh, LOCK_UN); fclose($fh);

/* EFB_WP_ROOT=none: no site file and no lock at all. */
$fh = fopen($lock, 'c'); flock($fh, LOCK_EX);
list($code, $out) = $run(array($site_f), array('EFB_WP_ROOT' => 'none', $HELD_VAR => null));
t('EFB_WP_ROOT=none skips the site files without asking for the lock', $code === 0 && strpos($out, '[SKIP] 1 site-backed tests not run (EFB_WP_ROOT=none)') !== false && !is_file($marker), $out);
flock($fh, LOCK_UN); fclose($fh);

/* A failing file fails the run and is named with its log. */
$bad = "$tmp/test-fixture-bad.php";
file_put_contents($bad, '<?php echo "[FAIL] something\nRESULTS: 3 passed, 1 failed\n";');
list($code, $out) = $run(array('--no-site', $bad));
t('a file with a failed check fails the run, with its log named', $code === 1 && strpos($out, '[FAIL] ' . $bad . '  (3 passed, 1 failed') !== false && strpos($out, '-- FAIL ' . $bad) !== false, $out);
$crash = "$tmp/test-fixture-crash.php";
file_put_contents($crash, '<?php echo "RESULTS: 2 passed, 0 failed\n"; exit(3);');
list($code, $out) = $run(array('--no-site', $crash));
t('a file that exits non-zero fails even when its count is clean', $code === 1 && strpos($out, 'exit 3') !== false, $out);

array_map('unlink', glob("$tmp/*"));
@rmdir($tmp);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
