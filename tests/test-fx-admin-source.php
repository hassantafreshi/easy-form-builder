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
// QA1-022: the onboarding's group name is a phrase, its step number in the site's digits; no English aria-label left.
$val = (string) file_get_contents($root . '/includes/admin/assets/js/val-efb.js');
t('onboarding: aria-label from the phrase onboardingActions, the step through efb_admin_date_efb()', strpos($val, "aria-label=\"' + t('onboardingActions', 'Setup actions') + '\"") !== false && strpos($val, "efb_admin_date_efb('2')") !== false);
t('val-efb.js: no aria-label written as English text', preg_match('/aria-label="[A-Za-z][A-Za-z ]+"/', $val) === 0);
echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
