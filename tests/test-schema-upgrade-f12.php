<?php
/**
 * F12-A: anti-spam needs no new column or table. The spec planned entry_status / spam_reason on emsfb_msg_; F14 came
 * first with entry_status (TINYINT 0 active, 1 spam, 2 trash) and the history table, so F12 reuses them: a spam answer
 * is entry_status 1 and its reasons are a history row (action "spam", meta "honeypot,too_fast").
 *
 *  - standalone: EMSFB_DB_VERSION is '1.10' or later (a string, compared with version_compare; F15 made it '1.11'); class-Emsfb-install.php
 *    and class-Emsfb-entries.php name no spam_reason column; the insert writes entry_status only when the column is
 *    there (Entries::has_status_column(), before the DB 1.10 upgrade an entry is stored as before);
 *  - on the e2e site (when EFB_E2E_SITE_DIR is set): the stored version is 1.10, two upgrades in a row apply
 *    nothing the second time (idempotent), and entry_status is there.
 *
 * Run: php tests/test-schema-upgrade-f12.php
 */
$passed = 0;
$failed = 0;
function t($name, $ok) { global $passed, $failed; if ($ok) { echo "[PASS] {$name}\n"; $passed++; } else { echo "[FAIL] {$name}\n"; $failed++; } }
$root = dirname(__DIR__);
$main = file_get_contents($root . '/emsfb.php');
t('EMSFB_DB_VERSION is a string, 1.10 or later', (bool) preg_match('/define\("EMSFB_DB_VERSION",\s*\'(1\.\d\d)\'\)/', $main, $vm) && version_compare($vm[1], '1.10', '>='));
t('version_compare: 1.10 is newer than 1.9 (a string, never a float)', version_compare('1.10', '1.9', '>'));
$install = file_get_contents($root . '/includes/class-Emsfb-install.php') . file_get_contents($root . '/includes/admin/class-Emsfb-entries.php');
t('no spam_reason column anywhere in the schema', strpos($install, 'spam_reason') === false);
$pub = file_get_contents($root . '/includes/class-Emsfb-public.php');
t('the insert writes entry_status 1 for spam only when the column exists', (bool) preg_match('/if \(\$spam_efb && class_exists\(\'\\\\\\\\Emsfb\\\\\\\\Entries\'\) && \\\\Emsfb\\\\Entries::has_status_column\(\)\) \$row_efb\[\'entry_status\'\] = 1;/', $pub));
$spam = file_get_contents($root . '/includes/class-Emsfb-spam.php');
t('the reasons go to the history (Entries::log action spam)', strpos($spam, "Entries::log((int) \$msg_id, 'spam', '', null, null,") !== false);

require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) {
	echo "[SKIP] site checks: {$why}\n";
} else {
	$u = $H->ctl('f12-upgrade');
	t('site: the stored DB version is the code\'s (1.10 or later)', is_array($u) && (string) $u['db_version'] === (string) $u['const'] && version_compare((string) $u['const'], '1.10', '>='));
	t('site: a second upgrade applies nothing (idempotent)', is_array($u) && $u['second'] === array());
	t('site: emsfb_msg_ has entry_status (F14, DB 1.10)', is_array($u) && $u['has_status'] === true);
}
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
