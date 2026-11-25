<?php
/**
 * FX-ADMIN (4.3.2): small source checks of fixes that have no behaviour of their own to drive.
 *  - capabilities.payments.migration.backup says what Move back restores now: the form before the latest move (only the
 *    field change is undone), not "original JSON per form" (FX-PAY hand-off).
 *
 * Run: php tests/test-fx-admin-source.php
 */
$root = dirname(__DIR__);
$pass = 0;
$fail = 0;
function t($name, $ok, $detail = '') {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $name\n"; } else { $fail++; echo "[FAIL] $name" . ($detail !== '' ? ' — ' . json_encode($detail) : '') . "\n"; }
}
$api = (string) file_get_contents($root . '/includes/class-Emsfb-builder-api.php');
t('capabilities: the migration backup is "the form before the latest move"', strpos($api, "'backup' => 'the form before the latest move'") !== false && strpos($api, 'original JSON per form') === false);
echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
