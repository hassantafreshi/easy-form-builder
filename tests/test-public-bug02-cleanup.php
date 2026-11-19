<?php
/**
 * BUG-02: small open BUGS-FOUND rows in the public code (rows 46/290, 67, 326, 403).
 *  - the same-host check of an uploaded file's URL accepted "http://wwww.<host>" (four w), so a file served from
 *    www.<host> was refused unless the site's own host already had the www (row 67, two places);
 *  - "$still_processing == false;" compared instead of assigning, so a disabled field that refused a value kept
 *    looking at the next rows of that field (row 46 / 290);
 *  - _Public::file_upload_public() was hooked to nothing and answered with the file's server path (row 326);
 *  - Formbuilder had a second, private, uncalled filter_attributes_by_type_efb() (and its two helpers) that had drifted
 *    from the one the submit path uses (row 403).
 * The prefix lists are lifted from the source and run against a www URL.
 *
 * Run: php tests/test-public-bug02-cleanup.php   (no WordPress needed)
 */
$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

$pub = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$fb  = file_get_contents(__DIR__ . '/../includes/class-Emsfb-formbuilder.php');

/* Row 67: both host-prefix lists, evaluated as written. */
$host = 'example.test';
$lists = array();
if (preg_match('/foreach \(array\((\'http:\/\/w+\.\' \. \$host.*?)\) as \$prefix\)/', $pub, $m)) $lists['upload rows'] = eval('$host = ' . var_export($host, true) . '; return array(' . $m[1] . ');');
if (preg_match('/\$ar = \[(\'http:\/\/w+\.\' \. \$d.*?)\];/', $pub, $m)) $lists['field loop'] = eval('$d = ' . var_export($host, true) . '; return array(' . $m[1] . ');');
t('both same-host prefix lists are found', count($lists) === 2, implode(',', array_keys($lists)));
foreach ($lists as $where => $prefixes) {
	$ok = false;
	foreach ($prefixes as $p) if (strpos('https://www.example.test/wp-content/uploads/efb-PLG-1.pdf', $p) === 0) $ok = true;
	t("$where: a file on https://www.<host> counts as this site", $ok, implode(' ', $prefixes));
}
t("no 'wwww.' prefix is left in the public class", strpos($pub, 'wwww.') === false);

/* Row 46 / 290: no statement is a bare comparison. */
t('the disabled-field branch stops processing ($still_processing = false;)', preg_match('/\$is_valid = 0;\s*\n\s*\$still_processing = false;\s*\n\s*\/\/ Name the field/', $pub) === 1);
$noop = preg_match_all('/^\s*\$[A-Za-z_][A-Za-z0-9_]*\s*===?\s*(?:true|false|\d+)\s*;/m', $pub . "\n" . $fb, $mm);
t('no statement in the public or builder class is a comparison that does nothing', $noop === 0, implode(' | ', $mm[0] ?? array()));

/* Row 326 / 403: the dead code is gone. */
t('_Public::file_upload_public() (hooked to nothing, returned the server path) is gone', strpos($pub, 'function file_upload_public(') === false);
t('the admin upload handler is still hooked (class-Emsfb-admin.php)', strpos(file_get_contents(__DIR__ . '/../includes/admin/class-Emsfb-admin.php'), "add_action('wp_ajax_update_file_Emsfb', array( \$this,'file_upload_public'))") !== false);
t('Formbuilder has no second filter_attributes_by_type_efb()', strpos($fb, 'function filter_attributes_by_type_efb(') === false);
t('Formbuilder has no uncalled sanitize_value_efb() / filter_and_sanitize_attributes_efb()', strpos($fb, 'function sanitize_value_efb(') === false && strpos($fb, 'function filter_and_sanitize_attributes_efb(') === false);
t('the submit path keeps its own filter_attributes_by_type_efb()', strpos($pub, 'private function filter_attributes_by_type_efb($data,$type)') !== false);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
