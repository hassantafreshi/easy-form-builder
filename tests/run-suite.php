<?php
/**
 * QA-01: the one runner of the unit suite.
 *
 *   php tests/run-suite.php [options] [test files...]
 *
 * Finds tests/test-*.php|js, tests/addons/test-* and tests/addon-i18n/test-* (not tests/core-i18n, not the e2e
 * scenarios), and sorts each file into one of two kinds by what it (or a helper it requires) uses:
 *   pure  needs no WordPress: run in parallel, always with EFB_WP_ROOT=none, so a test that would fall back to the
 *         shared site skips instead of touching it;
 *   site  uses the test site (tests/wp-env.php|js, wp-load.php, the *-http-lib helpers, EFB_E2E_*, 127.0.0.1:8088):
 *         run one at a time while this runner holds the e2e lock, so they never overlap an e2e run or another
 *         site run. When the lock is busy they are skipped with a [SKIP] line (or wait up to --lock-wait seconds).
 *
 * Options:
 *   --no-site            skip the site tests (also when EFB_WP_ROOT=none is set: those tests would skip anyway)
 *   --site-only          only the site tests
 *   --jobs=N             parallel pure tests (default: CPUs, at most 4)
 *   --lock-wait=SEC      wait this long for a busy e2e lock before skipping (default 0)
 *   --e2e="1 2 3"        after the site tests, still under the lock: node tests/e2e-wp/run.js with these scenarios
 *   --filter=REGEX       only files whose path matches (PCRE, without delimiters)
 *   --timeout=SEC        per file (default 600 pure, 1200 site)
 *   --list               print each file's kind and stop
 *   --json=PATH          write the results as JSON
 *   --logs=DIR           keep each file's output there (default: a temporary folder, printed at the end)
 *
 * The e2e lock: EFB_E2E_LOCK, else <the e2e site's parent>/e2e.lock (the shared site's convention), else
 * <temp>/efb-e2e.lock. EFB_E2E_LOCK_HELD=1 says the caller already holds it (for example `flock "$lock" php
 * tests/run-suite.php`), so the runner does not try to take it again (that would wait for itself). The e2e suite
 * (tests/e2e-wp/run.js) is run under the same lock: `flock "$lock" node tests/e2e-wp/run.js`, or --e2e here.
 *
 * Exit: 0 when no file failed (skips are not failures), 1 otherwise, 2 on a usage error. PHP 7.4 or later.
 */

if (PHP_SAPI !== 'cli') exit(2);
$root = dirname(__DIR__);
require_once __DIR__ . '/wp-env.php';

$opt = array('no-site' => false, 'site-only' => false, 'jobs' => 0, 'lock-wait' => 0, 'e2e' => '', 'filter' => '', 'timeout' => 0, 'list' => false, 'json' => '', 'logs' => '');
$only = array();
foreach (array_slice($argv, 1) as $a) {
	if (preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/', $a, $m)) {
		if (!array_key_exists($m[1], $opt)) { fwrite(STDERR, "unknown option --{$m[1]} (see the top of tests/run-suite.php)\n"); exit(2); }
		$opt[$m[1]] = isset($m[2]) ? $m[2] : true;
		continue;
	}
	$only[] = $a;
}
$wp_none = strtolower((string) getenv('EFB_WP_ROOT')) === 'none';
$no_site = $opt['no-site'] || $wp_none;
$jobs = (int) $opt['jobs'] > 0 ? (int) $opt['jobs'] : max(1, min(4, efb_suite_cpus()));

/** CPUs of this machine (1 when unknown). */
function efb_suite_cpus() {
	$n = (int) @shell_exec('nproc 2>/dev/null');
	if ($n < 1 && is_readable('/proc/cpuinfo')) $n = substr_count((string) file_get_contents('/proc/cpuinfo'), "\nprocessor");
	return $n > 0 ? $n : 1;
}

/** The e2e lock file. */
function efb_suite_lock_path() {
	$env = getenv('EFB_E2E_LOCK');
	if (is_string($env) && $env !== '') return $env;
	$site_parent = dirname(EFB_TEST_E2E_SITE, 2);
	if (is_dir($site_parent)) return $site_parent . '/e2e.lock';
	return rtrim(sys_get_temp_dir(), '/') . '/efb-e2e.lock';
}

/* 1. The files and their kind. */
$files = array();
if ($only) {
	foreach ($only as $f) {
		$p = is_file($f) ? realpath($f) : (is_file("$root/$f") ? realpath("$root/$f") : false);
		if ($p === false) { fwrite(STDERR, "no such test: $f\n"); exit(2); }
		$files[] = $p;
	}
} else {
	foreach (array('tests', 'tests/addons', 'tests/addon-i18n') as $d) {
		foreach (array('php', 'js') as $ext) foreach (glob("$root/$d/test-*.$ext") ?: array() as $f) $files[] = $f;
	}
}
$files = array_values(array_unique($files));
sort($files);
if ($opt['filter'] !== '') {
	$re = '#' . str_replace('#', '\#', (string) $opt['filter']) . '#';
	if (@preg_match($re, '') === false) { fwrite(STDERR, "bad --filter\n"); exit(2); }
	$files = array_values(array_filter($files, function ($f) use ($re, $root) { return (bool) preg_match($re, substr($f, strlen($root) + 1)); }));
}

/** The code of a file without its comments (a comment that mentions the e2e suite does not make a file site-backed). */
function efb_suite_code($src, $is_js) {
	if ($is_js) return (string) preg_replace(array('#/\*.*?\*/#s', '#^\s*//.*$#m'), '', $src);
	if (!function_exists('token_get_all')) return $src;
	$out = '';
	foreach (token_get_all($src) as $tk) {
		if (is_array($tk)) { if ($tk[0] !== T_COMMENT && $tk[0] !== T_DOC_COMMENT) $out .= $tk[1]; }
		else $out .= $tk;
	}
	return $out;
}

/**
 * 'site' when the file's code, or a helper it requires from tests/ (followed three levels down), names the test site.
 */
function efb_suite_kind($file, $depth = 0, array &$seen = array()) {
	if (isset($seen[$file]) || !is_readable($file)) return 'pure';
	$seen[$file] = true;
	$src = efb_suite_code((string) file_get_contents($file), substr($file, -3) === '.js');
	if (preg_match('/wp-env|wp-load\.php|-http-lib|EFB_E2E_|EFB_TEST_E2E_SITE|127\.0\.0\.1:8088|efb_test_wp_|wpEnv\b|e2e-wp\/|wpsite\/site|EFB_WP_URL|EFB_WP_LOAD/', $src)) return 'site';
	if ($depth >= 3) return 'pure';
	$dir = dirname($file);
	$libs = array();
	if (preg_match_all('#(?:require|include)(?:_once)?\s*\(?\s*__DIR__\s*\.\s*[\'"]/([A-Za-z0-9_./-]+\.php)[\'"]#', $src, $m)) foreach ($m[1] as $x) $libs[] = "$dir/$x";
	if (preg_match_all('#require\(\s*[\'"](\.\.?/[A-Za-z0-9_./-]+?)(\.js)?[\'"]\s*\)#', $src, $m)) foreach ($m[1] as $x) $libs[] = "$dir/$x.js";
	foreach ($libs as $l) if (efb_suite_kind($l, $depth + 1, $seen) === 'site') return 'site';
	return 'pure';
}
$kinds = array();
foreach ($files as $f) { $seen = array(); $kinds[$f] = efb_suite_kind($f, 0, $seen); }
$rel = function ($f) use ($root) { return strpos($f, $root . '/') === 0 ? substr($f, strlen($root) + 1) : $f; };

if ($opt['list']) {
	foreach ($files as $f) echo str_pad($kinds[$f], 5) . ' ' . $rel($f) . "\n";
	$c = array_count_values($kinds);
	echo ($c['pure'] ?? 0) . " pure, " . ($c['site'] ?? 0) . " site\n";
	exit(0);
}

$pure = array_values(array_filter($files, function ($f) use ($kinds) { return $kinds[$f] === 'pure'; }));
$site = array_values(array_filter($files, function ($f) use ($kinds) { return $kinds[$f] === 'site'; }));
if ($opt['site-only']) $pure = array();
$site_skipped = array();
if ($no_site) { $site_skipped = $site; $site = array(); }

$logs = $opt['logs'] !== '' ? rtrim((string) $opt['logs'], '/') : rtrim(sys_get_temp_dir(), '/') . '/efb-run-suite-' . getmypid();
if (!is_dir($logs) && !@mkdir($logs, 0777, true)) { fwrite(STDERR, "cannot create $logs\n"); exit(2); }

$results = array();
$t0 = microtime(true);

/** The command of a test file. */
function efb_suite_cmd($file) {
	if (substr($file, -3) === '.js') { $node = getenv('EFB_NODE'); return array($node !== false && $node !== '' ? $node : 'node', $file); }
	return array(PHP_BINARY, $file);
}

/** Reads a finished file's output into a result row. */
function efb_suite_result($file, $code, $secs, $log, $timed_out) {
	$out = (string) @file_get_contents($log);
	$passed = 0; $failed = 0; $found = false;
	if (preg_match_all('/RESULTS?:\s*(\d+)\s+passed,\s*(\d+)\s+failed/i', $out, $m, PREG_SET_ORDER)) { $x = end($m); $passed = (int) $x[1]; $failed = (int) $x[2]; $found = true; }
	elseif (preg_match_all('/"passed"\s*:\s*(\d+)\s*,\s*"failed"\s*:\s*(\d+)/', $out, $m, PREG_SET_ORDER)) { $x = end($m); $passed = (int) $x[1]; $failed = (int) $x[2]; $found = true; }
	elseif (preg_match_all('/(\d+)\s+passed,\s*(\d+)\s+failed/i', $out, $m, PREG_SET_ORDER)) { $x = end($m); $passed = (int) $x[1]; $failed = (int) $x[2]; $found = true; }
	elseif (preg_match_all('/passed\s+(\d+)\s+failed\s+(\d+)/i', $out, $m, PREG_SET_ORDER)) { $x = end($m); $passed = (int) $x[1]; $failed = (int) $x[2]; $found = true; }
	$skip = !$timed_out && $code === 0 && $passed + $failed === 0 && preg_match('/\[SKIP\]|^SKIP\b/m', $out);
	$status = $timed_out || $code !== 0 || $failed > 0 ? 'FAIL' : ($skip ? 'SKIP' : 'PASS');
	$why = $timed_out ? 'timed out' : ($code !== 0 && $failed === 0 ? "exit $code" : '');
	if ($status === 'SKIP' && preg_match('/\[SKIP\]\s*(.+)/', $out, $sm)) $why = trim($sm[1]);
	return array('status' => $status, 'passed' => $passed, 'failed' => $failed, 'counted' => $found, 'exit' => $code, 'seconds' => round($secs, 1), 'why' => $why, 'log' => $log);
}

/** Runs files, up to $parallel at a time; prints a line per file as it ends. */
function efb_suite_run(array $list, $parallel, array $env, $timeout, $logs, callable $rel, array &$results) {
	$queue = $list;
	$running = array();
	while ($queue || $running) {
		while ($queue && count($running) < $parallel) {
			$f = array_shift($queue);
			$log = $logs . '/' . str_replace('/', '__', $rel($f)) . '.log';
			$p = proc_open(efb_suite_cmd($f), array(0 => array('file', '/dev/null', 'r'), 1 => array('file', $log, 'w'), 2 => array('file', $log, 'a')), $pipes, dirname(__DIR__), $env);
			if (!is_resource($p)) { $results[$rel($f)] = array('status' => 'FAIL', 'passed' => 0, 'failed' => 0, 'counted' => false, 'exit' => -1, 'seconds' => 0, 'why' => 'could not start', 'log' => $log); continue; }
			$running[] = array('f' => $f, 'p' => $p, 't' => microtime(true), 'log' => $log);
		}
		usleep(50000);
		foreach ($running as $i => $r) {
			$st = proc_get_status($r['p']);
			$late = microtime(true) - $r['t'] > $timeout;
			if ($st['running'] && !$late) continue;
			if ($st['running']) { proc_terminate($r['p'], 9); }
			$code = $st['running'] ? -1 : (int) $st['exitcode'];
			proc_close($r['p']);
			$res = efb_suite_result($r['f'], $code, microtime(true) - $r['t'], $r['log'], $st['running']);
			$results[$rel($r['f'])] = $res;
			$counts = $res['counted'] ? "{$res['passed']} passed" . ($res['failed'] ? ", {$res['failed']} failed" : '') : 'no count';
			echo "[{$res['status']}] " . $rel($r['f']) . "  ($counts" . ($res['why'] !== '' ? "; {$res['why']}" : '') . ", {$res['seconds']} s)\n";
			unset($running[$i]);
		}
		$running = array_values($running);
	}
}

$base_env = getenv();
if (!is_array($base_env)) $base_env = $_ENV;

/* 2. Pure tests, in parallel, never on a site. */
if ($pure) {
	echo "== " . count($pure) . " pure tests ($jobs at a time, EFB_WP_ROOT=none)\n";
	$env = array_merge($base_env, array('EFB_WP_ROOT' => 'none'));
	efb_suite_run($pure, $jobs, $env, (int) $opt['timeout'] > 0 ? (int) $opt['timeout'] : 600, $logs, $rel, $results);
}

/* 3. Site tests and the e2e scenarios, one at a time, under the e2e lock. */
$lock_note = '';
if ($site_skipped) {
	$why = $wp_none ? 'EFB_WP_ROOT=none' : '--no-site';
	echo "[SKIP] " . count($site_skipped) . " site-backed tests not run ($why); run them with the test site: php tests/run-suite.php --site-only\n";
	foreach ($site_skipped as $f) $results[$rel($f)] = array('status' => 'SKIP', 'passed' => 0, 'failed' => 0, 'counted' => false, 'exit' => 0, 'seconds' => 0, 'why' => $why, 'log' => '');
} elseif ($site || $opt['e2e'] !== '') {
	$lock = efb_suite_lock_path();
	$held = getenv('EFB_E2E_LOCK_HELD') === '1';
	$fh = null;
	if (!$held) {
		$fh = @fopen($lock, 'c');
		$got = $fh && flock($fh, LOCK_EX | LOCK_NB);
		$deadline = microtime(true) + max(0, (int) $opt['lock-wait']);
		while ($fh && !$got && microtime(true) < $deadline) { sleep(1); $got = flock($fh, LOCK_EX | LOCK_NB); }
		if (!$got) {
			if ($fh) fclose($fh);
			$fh = null;
			$lock_note = "e2e lock busy ($lock)";
			echo "[SKIP] " . count($site) . " site-backed tests" . ($opt['e2e'] !== '' ? ' and the e2e scenarios' : '') . " not run: the e2e lock is held by another run ($lock). Run again later, or wait with --lock-wait=SECONDS.\n";
			foreach ($site as $f) $results[$rel($f)] = array('status' => 'SKIP', 'passed' => 0, 'failed' => 0, 'counted' => false, 'exit' => 0, 'seconds' => 0, 'why' => 'e2e lock busy', 'log' => '');
			$site = array();
			$opt['e2e'] = '';
		}
	}
	if ($site || $opt['e2e'] !== '') {
		echo "== " . count($site) . " site tests, one at a time, under the e2e lock ($lock" . ($held ? ', held by the caller' : '') . ")\n";
		$env = array_merge($base_env, array('EFB_E2E_LOCK_HELD' => '1'));
		efb_suite_run($site, 1, $env, (int) $opt['timeout'] > 0 ? (int) $opt['timeout'] : 1200, $logs, $rel, $results);
		if ($opt['e2e'] !== '') {
			$ids = preg_split('/[\s,]+/', trim((string) $opt['e2e']));
			$log = "$logs/e2e-wp.log";
			echo "== e2e scenarios " . implode(' ', $ids) . " (tests/e2e-wp/run.js)\n";
			$t = microtime(true);
			$p = proc_open(array_merge(array(getenv('EFB_NODE') ?: 'node', "$root/tests/e2e-wp/run.js"), $ids), array(0 => array('file', '/dev/null', 'r'), 1 => array('file', $log, 'w'), 2 => array('file', $log, 'a')), $pipes, $root, $env);
			$code = is_resource($p) ? proc_close($p) : -1;
			$res = efb_suite_result("$root/tests/e2e-wp/run.js", $code, microtime(true) - $t, $log, false);
			$results['tests/e2e-wp/run.js ' . implode(' ', $ids)] = $res;
			echo "[{$res['status']}] tests/e2e-wp/run.js " . implode(' ', $ids) . "  (" . ($res['counted'] ? "{$res['passed']} passed, {$res['failed']} failed" : "exit $code") . ", {$res['seconds']} s)\n";
		}
	}
	if ($fh) { flock($fh, LOCK_UN); fclose($fh); }
}

/* 4. Summary. */
$sum = array('PASS' => 0, 'FAIL' => 0, 'SKIP' => 0);
$checks = array('passed' => 0, 'failed' => 0);
foreach ($results as $r) { $sum[$r['status']]++; $checks['passed'] += $r['passed']; $checks['failed'] += $r['failed']; }
$secs = round(microtime(true) - $t0, 1);
echo "\n== " . count($results) . " files: {$sum['PASS']} passed, {$sum['FAIL']} failed, {$sum['SKIP']} skipped; checks: {$checks['passed']} passed, {$checks['failed']} failed; {$secs} s\n";
foreach ($results as $name => $r) {
	if ($r['status'] !== 'FAIL') continue;
	echo "\n-- FAIL $name ({$r['why']}" . ($r['failed'] ? "{$r['failed']} failed" : '') . ") log: {$r['log']}\n";
	$tail = array_slice(array_filter(explode("\n", (string) @file_get_contents($r['log'])), function ($l) { return preg_match('/FAIL|Error|error|Fatal|Warning/', $l); }), 0, 8);
	foreach ($tail as $l) echo '   ' . (strlen($l) > 200 ? substr($l, 0, 200) . '...' : $l) . "\n";
}
echo "logs: $logs\n";
if ($opt['json'] !== '') {
	file_put_contents((string) $opt['json'], json_encode(array('files' => count($results), 'summary' => $sum, 'checks' => $checks, 'seconds' => $secs, 'lock' => $lock_note, 'results' => $results), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}
echo "RESULTS: {$sum['PASS']} passed, {$sum['FAIL']} failed\n";
exit($sum['FAIL'] ? 1 : 0);
