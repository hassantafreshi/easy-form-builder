<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * F09-L: the e-mail of an entry with repeater rows (Emsfb\_Public::email_get_content_efb on a real WordPress).
 *
 * Checks: the group is one block at its own row: its name and number of rows, then "Row 1", "Row 2" each with its
 * own answers (a checkbox's choices of that row only); at most 20 rows (filter efb_email_repeater_rows) and a line
 * "N more rows. See the whole answer in the dashboard."; a hostile value in a row is escaped; the rows are not
 * printed a second time as loose answers; an entry without rows is printed exactly as before (same output as the
 * same rows with the repeater code path off).
 *
 * Needs the e2e site: EFB_WP_LOAD=/path/wp-load.php, or EFB_E2E_SITE_DIR; [SKIP] otherwise.
 * Run: php tests/test-email-repeater.php
 */
$wp_load = getenv('EFB_WP_LOAD') ?: (getenv('EFB_E2E_SITE_DIR') ? rtrim(getenv('EFB_E2E_SITE_DIR'), '/') . '/wp-load.php' : '');
if ($wp_load === '' || !is_readable($wp_load)) { echo "[SKIP] WordPress bootstrap was not found (EFB_WP_LOAD / EFB_E2E_SITE_DIR)\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
require $wp_load;
$passed = 0; $failed = 0;
$check = function ($ok, $label, $detail = null) use (&$passed, &$failed) {
	if ($ok) { $passed++; echo "[PASS] $label\n"; return; }
	$failed++; echo "[FAIL] $label" . ($detail !== null ? ' ' . substr(is_string($detail) ? $detail : wp_json_encode($detail), 0, 900) : '') . "\n";
};
$pub = (new ReflectionClass('Emsfb\\_Public'))->newInstanceWithoutConstructor();
$pub->efbFunction = get_efbFunction();
$rows = array(
	array('id_' => 'g', 'name' => 'Travellers', 'type' => 'repeater', 'value' => '2', 'amount' => 3),
	array('id_' => 'nm', 'name' => 'Name', 'type' => 'text', 'value' => 'Sara', 'rg' => 'g', 'ri' => '0', 'amount' => 4),
	array('id_' => 'ex', 'name' => 'Extras', 'type' => 'checkbox', 'value' => 'Bag', 'id_ob' => 'e1', 'rg' => 'g', 'ri' => '0', 'amount' => 5),
	array('id_' => 'nm', 'name' => 'Name', 'type' => 'text', 'value' => '<script>alert(1)</script>Reza', 'rg' => 'g', 'ri' => '1', 'amount' => 4),
	array('id_' => 'ex', 'name' => 'Extras', 'type' => 'checkbox', 'value' => 'Seat', 'id_ob' => 'e2', 'rg' => 'g', 'ri' => '1', 'amount' => 5),
	array('id_' => 'after', 'name' => 'Note', 'type' => 'text', 'value' => 'Thanks', 'amount' => 9),
	array('type' => 'w_link', 'value' => home_url('/x/'), 'amount' => -1),
);
$h = $pub->email_get_content_efb($rows, 'TRK1');
$plain = wp_strip_all_tags($h);
$check(strpos($plain, 'Travellers:') !== false && strpos($plain, '2 rows') !== false, 'the group\'s name and number of rows', $plain);
$p1 = strpos($plain, 'Row 1'); $p2 = strpos($plain, 'Row 2');
$check($p1 !== false && $p2 !== false && $p1 < strpos($plain, 'Sara') && strpos($plain, 'Sara') < $p2 && $p2 < strpos($plain, 'Reza'), '"Row 1" with Sara, then "Row 2" with Reza', $plain);
$check(substr_count($plain, 'Sara') === 1 && substr_count($plain, 'Reza') === 1, 'the rows are not printed again as loose answers');
$check(strpos($h, '<script>') === false && strpos($h, '&lt;script&gt;') !== false, 'a hostile value in a row is escaped');
$r1 = substr($plain, $p1, $p2 - $p1);
$check(strpos($r1, 'Bag') !== false && strpos($r1, 'Seat') === false, 'a checkbox of row 1 shows only row 1\'s choice');
$check(strpos($plain, 'Thanks') !== false, 'a field outside the group as usual');
$many = array(array('id_' => 'g', 'name' => 'Lines', 'type' => 'repeater', 'value' => '25', 'amount' => 3));
for ($i = 0; $i < 25; $i++) $many[] = array('id_' => 'nm', 'name' => 'Name', 'type' => 'text', 'value' => 'P' . $i, 'rg' => 'g', 'ri' => (string) $i, 'amount' => 4);
$hm = wp_strip_all_tags($pub->email_get_content_efb($many, 'T'));
$check(strpos($hm, 'P19') !== false && strpos($hm, 'P20') === false && strpos($hm, '5 more rows. See the whole answer in the dashboard.') !== false, 'at most 20 rows in an e-mail, then "5 more rows…"', substr($hm, -300));
add_filter('efb_email_repeater_rows', function () { return 2; });
$h2 = wp_strip_all_tags($pub->email_get_content_efb($many, 'T'));
$check(strpos($h2, 'P1') !== false && strpos($h2, 'P2') === false && strpos($h2, '23 more rows') !== false, 'efb_email_repeater_rows changes the cap');
$old = array(array('id_' => 'a', 'name' => 'A', 'type' => 'text', 'value' => 'x', 'amount' => 2), array('id_' => 'c', 'name' => 'C', 'type' => 'checkbox', 'value' => 'Y', 'id_ob' => 'o', 'amount' => 3));
$ho = $pub->email_get_content_efb($old, 'T');
$check(strpos($ho, 'Row 1') === false && strpos(wp_strip_all_tags($ho), 'x') !== false && substr_count($ho, '<table') === 1, 'an entry without rows: one table, its answers, nothing of the repeater');
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
