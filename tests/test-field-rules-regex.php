<?php
/**
 * F03-C: safe owner patterns on the server (Field_Rules), on tests/fixtures/regex-vectors.json
 * (shared with tests/test-field-rules-regex.js).
 *
 *  1. lint_regex(): the 20 known evil patterns (OWASP ReDoS list and friends) are refused, the 20
 *     common ones (staff codes, SKUs, coupons, postal codes, Persian letters) pass, every case of
 *     §9.3 is refused with its code.
 *  2. check_pattern() on every (pattern, value) pair: the whole value must match, Persian / Arabic /
 *     full-width digits count as 0-9 (pattern_ascii, default on), bidi and zero-width marks are
 *     ignored, a textarea is one line, the case switch.
 *  3. preg_safe(): ^(a+)+$ on 30 "a" and a "b" answers "refused" in under 50 ms and puts
 *     pcre.backtrack_limit / recursion_limit back; one log line per form and day, without the value.
 *  4. The save rule: a pattern with < " %41 and double spaces comes back unchanged through
 *     sanitize_key (base64url); a pattern the lint refuses, bad base64, invalid UTF-8 or more than
 *     344 characters is stored as ""; pattern_case / pattern_ascii booleans.
 *  5. check(): code "pattern" with msg_pattern or the default; no rule without the plan (efb_f03_plan).
 *
 * Run: php tests/test-field-rules-regex.php
 */
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
$GLOBALS['plan'] = 'free';
$GLOBALS['transients'] = array();
function apply_filters($h, $v) { return $h === 'efb_f03_plan' ? $GLOBALS['plan'] : $v; }
function efb_plan_at_least_efb($need) { return false; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function get_transient($k) { return isset($GLOBALS['transients'][$k]) ? $GLOBALS['transients'][$k] : false; }
function set_transient($k, $v, $e) { $GLOBALS['transients'][$k] = $v; return true; }
require __DIR__ . '/../includes/class-Emsfb-masks.php';
require __DIR__ . '/../includes/class-Emsfb-field-rules.php';
use Emsfb\Field_Rules as FR;
$V = json_decode(file_get_contents(__DIR__ . '/fixtures/regex-vectors.json'), true);

/* 1 */
$bad = array();
foreach ($V['evil'] as $p) if (FR::lint_regex($p) === null) $bad[] = $p;
t('1 the 20 evil patterns are refused', array(count($V['evil']), $bad), array(20, array()));
$bad = array();
foreach ($V['common'] as $p) if (($r = FR::lint_regex($p)) !== null) $bad[] = $p . ' ' . $r['code'];
t('1 the 20 common patterns pass', array(count($V['common']), $bad), array(20, array()));
foreach ($V['reject'] as $x) {
	$r = FR::lint_regex($x['src']);
	t('1 refused ' . (strlen($x['src']) > 30 ? substr($x['src'], 0, 30) . '…' : $x['src']) . ' → ' . $x['code'], $r ? $r['code'] : null, $x['code']);
}
t('1 the position points at the problem (code points)', FR::lint_regex('ب{2}(?<=a)'), array('code' => 'lookbehind', 'pos' => 4));

/* 2 */
foreach ($V['pairs'] as $x) {
	$f = array('pattern_b64' => FR::b64url_encode($x['src']), 'pattern_case' => $x['i'] ? '1' : '0');
	if (isset($x['ascii']) && $x['ascii'] === false) $f['pattern_ascii'] = '0';
	t('2 ' . $x['src'] . ' ~ ' . json_encode($x['v'], JSON_UNESCAPED_UNICODE) . ($x['i'] ? ' (i)' : ''), FR::check_pattern($f, $x['v']), $x['ok']);
}
t('2 a value longer than max(1000, mlen) is refused', FR::check_pattern(array('pattern_b64' => FR::b64url_encode('a+')), str_repeat('a', 1001)), false);
t('2 … unless mlen allows it', FR::check_pattern(array('pattern_b64' => FR::b64url_encode('a+'), 'mlen' => '2000'), str_repeat('a', 1500)), true);

/* 3 */
ini_set('pcre.backtrack_limit', '1000000');
ini_set('pcre.recursion_limit', '100000');
$logf = tempnam(sys_get_temp_dir(), 'efbpat');
ini_set('error_log', $logf);
$t0 = microtime(true);
$r = FR::preg_safe('~^(?:(a+)+)$~uD', str_repeat('a', 30) . 'b', 9);
$ms = (microtime(true) - $t0) * 1000;
t('3 ^(a+)+$ on 30 a + b: refused', $r, false);
t('3 … in under 50 ms (' . round($ms, 1) . ' ms)', $ms < 50, true);
t('3 the PCRE limits are back', array(ini_get('pcre.backtrack_limit'), ini_get('pcre.recursion_limit')), array('1000000', '100000'));
FR::preg_safe('~^(?:(a+)+)$~uD', str_repeat('a', 30) . 'b', 9);
$log = (string) file_get_contents($logf);
t('3 one log line for the form and day, the value is not in it', array(substr_count($log, '[EFB pattern] form 9'), strpos($log, 'aaaa')), array(1, false));
@unlink($logf);
t('3 a normal match still works', FR::preg_safe('~^(?:a+)$~uD', 'aaa'), true);

/* 4 */
$src = 'x<y "q" %41  z';
$b = FR::b64url_encode($src);
t('4 a pattern with < " %41 and double spaces survives the save rule', FR::b64url_decode(FR::sanitize_key('pattern_b64', $b)), $src);
t('4 padding / standard base64 is refused (+ /)', FR::sanitize_key('pattern_b64', base64_encode('a+?b/')), '');
t('4 a pattern the lint refuses is stored as ""', FR::sanitize_key('pattern_b64', FR::b64url_encode('(a+)+')), '');
t('4 invalid UTF-8 is stored as ""', FR::sanitize_key('pattern_b64', FR::b64url_encode("\xff\xfe")), '');
t('4 more than 344 characters is stored as ""', FR::sanitize_key('pattern_b64', str_repeat('QUFB', 87)), '');
t('4 an array is stored as ""', FR::sanitize_key('pattern_b64', array('x')), '');
t('4 pattern_case / pattern_ascii', array(FR::sanitize_key('pattern_case', true), FR::sanitize_key('pattern_case', 'x'), FR::sanitize_key('pattern_ascii', '0'), FR::sanitize_key('pattern_ascii', '')), array('1', '0', '0', '1'));

/* 5 */
$f = array('id_' => 'p', 'type' => 'text', 'name' => 'Code', 'pattern_b64' => FR::b64url_encode('[A-Z]{2}\d{3}'));
$run = function ($f, $v) { return FR::check($f, array('id_' => 'p', 'type' => 'text', 'value' => $v), array(), array(), array(), array('form_id' => 3)); };
$r = $run($f, 'ab123');
t('5 refused: code pattern, default sentence', array($r['ok'], $r['code'], $r['m']), array(false, 'pattern', 'The <b>Code</b> field is not in the expected format.'));
t('5 msg_pattern', $run($f + array('msg_pattern' => 'Like AB123'), 'x')['m'], 'Like AB123');
t('5 a match passes', $run($f, 'AB۱۲۳')['ok'], true);
$GLOBALS['plan'] = 'free_plus';
t('5 without the plan the rule is skipped (the form still works)', $run($f, 'x')['ok'], true);
$GLOBALS['plan'] = 'free';
t('5 an email field takes a pattern too; a number field does not', array(FR::active_rules(array('type' => 'email') + $f), FR::active_rules(array('type' => 'number') + $f)), array(array('pattern'), array()));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
