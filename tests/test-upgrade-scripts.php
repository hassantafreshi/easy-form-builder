<?php
/**
 * B10 UPG-01: the 4.3.1 -> 4.3.2 upgrade test on MySQL / MariaDB (tests/upgrade/run-upgrade-431.sh) stays runnable:
 * its scripts parse, it runs WP-CLI from its own folder (emsfb.php requires includes/class-Emsfb.php by a relative
 * path, which PHP resolves against the current folder first), it keeps the site off the network, and its comparison
 * covers the release exit criteria (same rows and content of forms, entries, replies and payments, a second run that
 * changes nothing, at most 30 s, no PHP notices). The run itself needs a database server: see the script's header
 * and the "upgrade" job of .github/workflows/ci.yml.
 *
 * Run: php tests/test-upgrade-scripts.php   (no WordPress, no database)
 */
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

$sh  = (string) @file_get_contents("$root/tests/upgrade/run-upgrade-431.sh");
$lib = (string) @file_get_contents("$root/tests/upgrade/upgrade-lib.php");
$mu  = (string) @file_get_contents("$root/tests/upgrade/efb-upgrade-timer.php");
t('the driver is valid bash', $sh !== '' && trim((string) shell_exec('bash -n ' . escapeshellarg("$root/tests/upgrade/run-upgrade-431.sh") . ' 2>&1')) === '');
foreach (array('upgrade-lib.php', 'efb-upgrade-timer.php') as $f) {
	t("tests/upgrade/$f lints", strpos((string) shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg("$root/tests/upgrade/$f") . ' 2>&1'), 'No syntax errors') !== false);
}
t('the driver runs WP-CLI from its own folder (cd "$DIR")', (bool) preg_match('/^cd "\$DIR"$/m', $sh));
t('the driver starts from the wordpress.org 4.3.1 zip (sha256 checked when given)', strpos($sh, 'https://downloads.wordpress.org/plugin/easy-form-builder.4.3.1.zip') !== false && strpos($sh, 'EFB_UPG_OLD_SHA256') !== false);
t('100,000 entries and 30 s by default', strpos($sh, 'ENTRIES="${EFB_UPG_ENTRIES:-100000}"') !== false && strpos($sh, 'MAX="${EFB_UPG_MAX_SECONDS:-30}"') !== false);
t('the database is the script\'s own (dropped and created), WP_DEBUG on, WP-Cron off', strpos($sh, 'DROP DATABASE IF EXISTS') !== false && strpos($sh, "define( 'WP_DEBUG', true );") !== false && strpos($sh, "define( 'DISABLE_WP_CRON', true );") !== false);
t('the mu-plugin keeps the site off the network and times the update', strpos($mu, "add_filter('pre_http_request'") !== false && strpos($mu, "update_option('efb_upgrade_test_timing'") !== false);
foreach (array('emsfb_form', 'emsfb_msg_', 'emsfb_rsp_', 'emsfb_pay_', 'emsfb_setting') as $tb) {
	t("the comparison checks the rows and content of $tb", strpos($lib, "'$tb' =>") !== false);
}
t('...after the update and after its background jobs, with a second run that applies nothing', strpos($lib, "still the same after the update's background jobs") !== false && strpos($lib, 'a second run of the upgrade applies nothing') !== false && strpos($sh, 'cron event run --due-now') !== false);
t('...the time against the limit, and PHP errors, warnings, notices and deprecations', strpos($lib, 'the schema update took at most') !== false && strpos($sh, 'PHP (Fatal|Parse|Warning|Notice|Deprecated)') !== false);
t('legacy Stripe, PayPal and Persia Pay forms and custom Localization texts are seeded', strpos($lib, "'stripe'") !== false && strpos($lib, "'paypal'") !== false && strpos($lib, "'persiaPay'") !== false && strpos($lib, '$s->text->sending') !== false);
t('no real key in the seed (placeholders only)', !preg_match('/sk_(live|test)_[A-Za-z0-9]{20,}/', $lib));

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
