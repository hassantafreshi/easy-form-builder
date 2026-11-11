<?php
/**
 * F01-G: the generic value row of notification emails (email_get_content_efb) wraps phone numbers, IDs and
 * IBANs in <span dir="ltr" style="unicode-bidi:embed"> so RTL mail clients do not show "+98912…" as
 * "…98912+". Runs the real snippet extracted from includes/class-Emsfb-public.php together with the real
 * email_escape_leaf_efb(); hostile values stay escaped and free text is not forced left to right.
 *
 * Run: php tests/test-masks-email-display.php
 */
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$start = strpos($src, "if (isset(\$c['value']) && is_string(\$c['value'])) {", strpos($src, 'function email_get_content_efb'));
$end = strpos($src, "if (isset(\$c['qty']))", $start);
if ($start === false || $end === false) {
	fwrite(STDERR, "email value snippet not found\n");
	exit(1);
}
$snippet = substr($src, $start, $end - $start);
if (!preg_match('/\tprivate function email_escape_leaf_efb\(.*?\n\t}\n/s', $src, $m) && !preg_match('/\t(?:public |private |protected )?(?:static )?function email_escape_leaf_efb\(.*?\n\t}\n/s', $src, $m)) {
	fwrite(STDERR, "email_escape_leaf_efb not found\n");
	exit(1);
}
eval('class MaskEmailFixture {' . $m[0] . ' public function row($c) { $q = ""; ' . $snippet . ' return $q; } }');

$pass = 0;
$fail = 0;
function et($label, $cond, $detail = '') {
	global $pass, $fail;
	if ($cond) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label" . ($detail !== '' ? "\n      $detail" : '') . "\n"; }
}
$fx = new MaskEmailFixture();
$wrap = function ($inner) { return '<span dir="ltr" style="unicode-bidi:embed"><b>' . $inner . '</b></span>'; };
foreach (array('+989121234567', '0012345679', 'IR840120000000000123456789', '0912 123 4567', '(202) 555-0123', '12345-6789') as $v) {
	$out = $fx->row(array('value' => $v));
	et("email: $v left to right", $out === $wrap(htmlspecialchars($v, ENT_QUOTES, 'UTF-8')) || $out === $wrap($v), $out);
}
foreach (array('سلام دنیا', 'Hello world', 'ir84') as $v) {
	$out = $fx->row(array('value' => $v));
	et("email: free text not forced ltr ($v)", strpos($out, 'dir="ltr"') === false && strpos($out, '<b>') === 0, $out);
}
$out = $fx->row(array('value' => '+98<img src=x onerror=alert(1)>'));
et('email: hostile value escaped and not wrapped', strpos($out, '<img') === false && strpos($out, 'dir="ltr"') === false, $out);
$out = $fx->row(array('value' => '@file@'));
et('email: upload marker untouched', $out === '@file@', $out);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
