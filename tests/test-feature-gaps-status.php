<?php
/**
 * B1 DOC-01: the status table of docs/feature-gaps/README.fa.md follows each spec's own status line.
 * The table said «سند آماده» (spec ready, not built) for twelve specs whose first lines say they are implemented
 * (F02, F03, F06, F07, F08, F11, F12, F12b, F15, F16, F17, SPLIT), so the team and later agents planned from a wrong
 * status. Each row now has the spec's status and a column of what is still open for the owner or phase 2.
 *
 * Run: php tests/test-feature-gaps-status.php   (no WordPress)
 */
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

$dir = "$root/docs/feature-gaps";
$readme = (string) @file_get_contents("$dir/README.fa.md");
t('docs/feature-gaps/README.fa.md exists', $readme !== '');
t('the table has an "open for the owner or phase 2" column', strpos($readme, '| تسک | محتوا | اولویت | تلاش | وابستگی | وضعیت | باز برای مالک یا فاز ۲ |') !== false);

$rows = 0;
// The phase-1 queue only (the payment gateway queue further down is another table).
$i = strpos($readme, '## فاز ۱');
$j = $i === false ? false : strpos($readme, "\n## ", $i + 5);
$phase1 = $i === false ? '' : ($j === false ? substr($readme, $i) : substr($readme, $i, $j - $i)); // (PHP 7.4: a null length is 0)
foreach (explode("\n", $phase1) as $line) {
	if (!preg_match('/^\| \[([A-Z0-9a-z]+) — [^\]]*\]\(([^)]+)\)/u', $line, $m)) continue;
	$rows++;
	$key = $m[1];
	$cells = array_map('trim', explode('|', trim($line, " |")));
	t("$key: the row has 7 cells", count($cells) === 7, (string) count($cells));
	$status = $cells[5] ?? '';
	$spec_path = $m[2][0] === '.' ? realpath("$dir/" . $m[2]) : "$dir/" . $m[2];
	$head = $spec_path && is_file($spec_path) ? implode("\n", array_slice(file($spec_path), 0, 12)) : '';
	$built = (bool) preg_match('/(وضعیت|status)[^\n]*(پیاده|ساخته شد|implemented)/iu', $head);
	if ($built) {
		t("$key: the spec says implemented, so the row does not say «سند آماده»", strpos($status, 'سند آماده') === false, mb_substr($status, 0, 40));
	}
}
t('the table lists the 24 specs and the AutoPilot row', $rows >= 24, (string) $rows);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
