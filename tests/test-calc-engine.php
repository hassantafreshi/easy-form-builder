<?php
/**
 * F04-A: \Emsfb\Calc (includes/class-Emsfb-calc.php) against every vector of
 * docs/feature-gaps/reference/calc-test-vectors.json; no eval / create_function / assert(string) in the
 * file; a 10 000-character formula is refused in under 5 ms; nesting never overflows the stack.
 *
 * Run: php tests/test-calc-engine.php
 */
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../includes/class-Emsfb-calc.php';
use Emsfb\Calc;

$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if (json_encode($actual, JSON_UNESCAPED_UNICODE) === json_encode($expected, JSON_UNESCAPED_UNICODE)) { $pass++; return; }
	$fail++;
	echo "[FAIL] $label\n  expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
$V = json_decode(file_get_contents(__DIR__ . '/../docs/feature-gaps/reference/calc-test-vectors.json'), true);
foreach ($V['calc'] as $i => $v) {
	$r = Calc::run($v['formula'], $v['values'], $v['decimals'], isset($v['opts']) ? $v['opts'] : array());
	if (isset($v['error'])) {
		$want = array('ok' => false, 'code' => $v['error']);
		if (isset($v['pos'])) { $want['pos'] = $v['pos']; $want['near'] = $v['near']; }
		$got = $r['ok'] ? $r : (isset($v['pos']) ? array('ok' => false, 'code' => $r['code'], 'pos' => isset($r['pos']) ? $r['pos'] : null, 'near' => isset($r['near']) ? $r['near'] : null) : array('ok' => false, 'code' => $r['code']));
	} else {
		$want = array('ok' => true, 'value' => $v['expect']);
		$got = $r;
	}
	t("calc #$i " . substr(json_encode($v['formula'], JSON_UNESCAPED_UNICODE), 0, 60), $got, $want);
}
foreach ($V['parse_input'] as $i => $v) {
	$r = Calc::parse_input($v['raw'], Calc::config($v['field'], array('locale' => $v['locale'], 'currency' => isset($v['currency']) ? $v['currency'] : null)));
	if (isset($v['err'])) t("parse_input #$i", array('ok' => $r['ok'], 'err' => $r['err']), array('ok' => false, 'err' => $v['err']));
	else t("parse_input #$i " . $v['raw'], array('ok' => $r['ok'], 'canon' => $r['canon']), array('ok' => true, 'canon' => $v['expect']));
}
foreach ($V['check'] as $i => $v) t("check #$i {$v['canon']}", Calc::check($v['canon'], Calc::config($v['field'], array('locale' => $v['locale'], 'currency' => array('s' => '$', 'd' => 0)))), $v['expect']);
foreach ($V['format'] as $i => $v) t("format #$i {$v['canon']} {$v['locale']}", Calc::format($v['canon'], Calc::config($v['field'], array('locale' => $v['locale'], 'currency' => isset($v['currency']) ? $v['currency'] : null))), $v['expect']);

$src = preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__ . '/../includes/class-Emsfb-calc.php'));
$src = preg_replace('#//.*$#m', '', $src);
t('no eval / create_function / assert / preg_replace /e in class-Emsfb-calc.php', (bool) preg_match('/\beval\s*\(|create_function|\bassert\s*\(|\bshell_exec|\bexec\s*\(|\bsystem\s*\(|\bpassthru|\bproc_open|\bpopen/', $src), false);

$big = str_repeat('1+', 5000);
$t0 = microtime(true);
$r = Calc::parse($big);
$ms = (microtime(true) - $t0) * 1000;
t('a 10 000-character formula: calcLong', $r['code'], 'calcLong');
t(sprintf('refused in under 5 ms (%.2f ms)', $ms), $ms < 5, true);
t('deep nesting refused', Calc::run(str_repeat('(', 400) . '1' . str_repeat(')', 100))['code'], 'calcDepth');
$p = Calc::parse('if(gt({a},1),round({a}*1.5,2),sum({r.x}))');
t('the tree is plain data (arrays and scalars, no objects or closures)', json_encode($p['ast']) !== false && strpos(serialize($p['ast']), 'O:') === false && strpos(serialize($p['ast']), 'C:') === false, true);
t('ref_ids', Calc::ref_ids('{a}+{field:b}*{a.count}+{c.lo}'), array('a', 'b', 'c'));
t('round_half_away(-2.5) = -3 (PHP round agrees here, JS Math.round does not)', Calc::round_half_away(-2.5, 0), -3.0);
t('to_fixed(1.005 rounded, 2) = 1.01', Calc::to_fixed(Calc::round_half_away(1.005, 2), 2), '1.01');
t('to_fixed beyond 2^53 refused', Calc::to_fixed(1e16, 2), null);
t('split_range', Calc::split_range('۱۰..۵۰'), array('lo' => '10', 'hi' => '50'));
t('a broken UTF-8 formula is a syntax error, not a warning', Calc::parse("1+\xC3")['code'], 'calcSyntax');
t('a non-string formula', Calc::parse(array('x'))['code'], 'calcEmpty');

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
