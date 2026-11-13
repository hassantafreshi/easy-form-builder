<?php
/**
 * F11-G: Emsfb\Form_Css (per-form custom CSS), standalone, on tests/fixtures/f11-css-vectors.json
 * (59 hostile and normal vectors) plus the storage format:
 *  - </style><script>, @import (also escaped), @charset, @namespace, @page, expression( (spaced,
 *    escaped, split by a comment), -moz-binding, behavior:, javascript: / vbscript: (escaped \6a,
 *    \00006A), http / protocol-relative / ftp / SVG / text-html data URLs never come out; the rule
 *    that holds them is removed whole, the others stay;
 *  - scoping: every selector inside #body_efb_{id}; :root, html and body become it; @media,
 *    @supports, @container and @layer recursively; @keyframes / @font-face kept as they are;
 *  - stored as "b64:" + base64; a bad value is dropped; scoped() never prints "<";
 *  - the size cap is 20 KB.
 * Run: php tests/test-form-css-sanitize.php
 */
define('ABSPATH', __DIR__ . '/');
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/../includes/class-Emsfb-form-css.php';
use Emsfb\Form_Css as C;

$vectors = json_decode(file_get_contents(__DIR__ . '/fixtures/f11-css-vectors.json'), true);
t('at least 40 vectors', count($vectors) >= 40, true);
foreach ($vectors as $v) {
	$out = isset($v['scoped']) ? C::scope(C::sanitize($v['css']), $v['scoped']) : C::sanitize($v['css']);
	$ok = true;
	$why = array();
	if (!empty($v['empty']) && $out !== '') { $ok = false; $why[] = 'not empty'; }
	foreach (isset($v['absent']) ? $v['absent'] : array() as $a) if (stripos($out, $a) !== false) { $ok = false; $why[] = 'has ' . $a; }
	foreach (isset($v['present']) ? $v['present'] : array() as $p) if (strpos($out, $p) === false) { $ok = false; $why[] = 'lacks ' . $p; }
	if (strpos($out, '<') !== false) { $ok = false; $why[] = 'has <'; }
	t($v['name'] . ($ok ? '' : ' → ' . json_encode($out) . ' ' . implode('; ', $why)), $ok, true);
}

// Storage and printing.
$css = ".efb h4{color:#123}\nbody{background:#fff}";
$stored = 'b64:' . base64_encode($css);
t('sanitize_stored keeps a clean value (as base64)', C::decode(C::sanitize_stored($stored)), ".efb h4{color:#123}\nbody{background:#fff}");
t('sanitize_stored drops a value without the prefix', C::sanitize_stored($css), '');
t('sanitize_stored drops bad base64', C::sanitize_stored('b64:###'), '');
t('sanitize_stored drops invalid UTF-8', C::sanitize_stored('b64:' . base64_encode("\xff\xfe.a{}")), '');
t('sanitize_stored drops a value that is only dangerous', C::sanitize_stored('b64:' . base64_encode('@import url(x.css);')), '');
t('scoped(): printed for #body_efb_7, html/body → wrapper', C::scoped($stored, 7), "\n#body_efb_7 .efb h4{color:#123}\n#body_efb_7{background:#fff}\n");
t('scoped(): sanitizes again (a stored value written around the save)', C::scoped('b64:' . base64_encode('</style><script>x</script>{}.a{b:c}'), 3), "\n#body_efb_3 .a{b:c}\n");
t('scoped(): nothing for a form without custom CSS', array(C::scoped('', 1), C::scoped(null, 1)), array('', ''));
t('decode_escapes: \\3c and \\000075', C::decode_escapes('\\3c \\000075rl'), '<url');
t('url_ok', array(C::url_ok('https://x'), C::url_ok('a/b.png'), C::url_ok(' JavaScript:x'), C::url_ok('data:image/webp;base64,x'), C::url_ok('data:image/svg+xml,x')), array(true, true, false, true, false));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
