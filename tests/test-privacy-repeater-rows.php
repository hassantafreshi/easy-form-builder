<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * F09-L / F13: anonymizing an entry with repeater rows (Emsfb\Privacy::anonymize_rows, row_is_personal); F10-E: the
 * name of a typed signature is cleared with it and named in the personal-data export.
 *
 * Checks: every row's copy of a personal child (same id_, rg / ri) is cleared, not only the first; a child marked
 * prv_pii "0" keeps its values in every row; the group's own row (its number of rows) is kept; the file of a row
 * is listed for removal; rg / ri stay so the viewer still shows the rows.
 *
 * Run: php tests/test-privacy-repeater-rows.php
 */
define('ABSPATH', __DIR__ . '/');
function apply_filters($h, $v) { return $v; }
require __DIR__ . '/../includes/class-Emsfb-privacy.php';
$passed = 0; $failed = 0;
$check = function ($ok, $label, $d = null) use (&$passed, &$failed) {
	if ($ok) { $passed++; echo "[PASS] $label\n"; return; }
	$failed++; echo "[FAIL] $label" . ($d !== null ? ' ' . json_encode($d) : '') . "\n";
};
$rows = array(
	array('id_' => 'g', 'type' => 'repeater', 'value' => '2'),
	array('id_' => 'nm', 'type' => 'text', 'value' => 'Sara', 'rg' => 'g', 'ri' => '0'),
	array('id_' => 'nt', 'type' => 'text', 'value' => 'note 1', 'rg' => 'g', 'ri' => '0'),
	array('id_' => 'nm', 'type' => 'text', 'value' => 'Reza', 'rg' => 'g', 'ri' => '1'),
	array('id_' => 'nt', 'type' => 'text', 'value' => 'note 2', 'rg' => 'g', 'ri' => '1'),
	array('id_' => 'doc', 'type' => 'file', 'value' => '', 'url' => 'http://x.test/wp-content/uploads/efb-PLG-202611-Ab12Cd.pdf', 'rg' => 'g', 'ri' => '1'),
);
list($out, $files) = \Emsfb\Privacy::anonymize_rows($rows, array('nt' => '0'));
$check($out[1]['value'] === \Emsfb\Privacy::REDACTED && $out[3]['value'] === \Emsfb\Privacy::REDACTED, 'a personal child is cleared in every row', $out);
$check($out[2]['value'] === 'note 1' && $out[4]['value'] === 'note 2', 'a child marked not personal keeps its values in every row');
$check($out[0]['value'] === '2', 'the group\'s number of rows is kept');
$check($out[5]['url'] === '' && count($files) >= 1, 'the file of a row is cleared and listed for removal', $files);
$check($out[3]['rg'] === 'g' && $out[3]['ri'] === '1', 'rg / ri stay on the cleared rows');
// F10-E: a typed signature's name is personal data: anonymizing clears it with the image; the export reads it.
list($sg) = \Emsfb\Privacy::anonymize_rows(array(array('id_' => 'sg', 'type' => 'esign', 'value' => 'data:image/png;base64,iVBORw0KGgo=', 'sig_mode' => 'type', 'sig_text' => 'محمد رضایی', 'sig_font' => 'arabic')));
$check($sg[0]['value'] === \Emsfb\Privacy::REDACTED && $sg[0]['sig_text'] === '', 'F10-E: a typed signature\'s name is cleared with the image', $sg);
$check(\Emsfb\Privacy::readable_value(array('type' => 'esign', 'value' => 'data:image/png;base64,x', 'sig_mode' => 'type', 'sig_text' => 'Jane Doe'), array('prvRedacted' => '[redacted]', 'esign' => 'Signature')) === '[Signature] Jane Doe', 'F10-E: the personal-data export names a typed signature');
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
