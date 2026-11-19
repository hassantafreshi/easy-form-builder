<?php
/**
 * PUB-03: visitor and API answers of the public class were fixed English ("Email is not valid", "Track not found",
 * "Form ID not found", "Error 406"), a gettext call glued a code into its phrase ('…message.' . ' E500' inside
 * esc_html__(): no translation could ever match it), and core-efb.js read a phrase key that never existed
 * (checkYourEmail, in a branch that could not run). new-efb.js showed "Please Hard Refresh", "Network error or upload
 * failed" and "Invalid JSON response from server. Check console for details." to visitors.
 *
 * Each answer now goes through gettext with a phrase the packs already translate (checked against fa_IR and de_DE).
 *
 * Run: php tests/test-public-visitor-strings.php   (no WordPress needed)
 */
$root = dirname(__DIR__);
$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

$pub = file_get_contents("$root/includes/class-Emsfb-public.php");
foreach (array('Email is not valid', 'Track not found', 'Form ID not found', 'Error 406') as $s) {
	t("no fixed English answer '$s'", strpos($pub, "'$s'") === false);
}
/* No answer row of the public class carries a bare English literal. */
preg_match_all("/'(?:m|message|error|msg)'\s*=>\s*'([A-Z][a-z][^']*)'/", $pub, $mm);
t('no visitor answer is a bare English literal (\'m\' => \'Xyz…\')', empty($mm[1]), implode(' | ', $mm[1]));
/* No gettext call with a concatenated text (NonSingularStringLiteralText). */
preg_match_all("/(?:esc_html__|esc_attr__|__|_e|esc_html_e)\(\s*'[^']*'\s*\.\s*/", $pub, $nc);
t('no gettext call glues text into its phrase', empty($nc[0]), implode(' | ', $nc[0]));
t('"Not allowed to respond…" keeps its code outside the phrase', strpos($pub, "esc_html__('Not allowed to respond to this message.', 'easy-form-builder') . ' E500'") !== false);

/* The phrases used are translated in the packs that ship. */
$used = array('Invalid Email address', 'Invalid Confirmation Code.', 'Form not found.', 'Not allowed to respond to this message.');
foreach (array('fa_IR', 'de_DE') as $loc) {
	$po = (string) @file_get_contents("$root/languages/easy-form-builder-$loc.po");
	$missing = array();
	foreach ($used as $u) {
		if (!preg_match('/^msgid "' . preg_quote($u, '/') . '"\nmsgstr "(.+)"$/m', $po, $m) || $m[1] === '') $missing[] = $u;
	}
	t("$loc: every phrase used is translated", $po !== '' && !$missing, implode(', ', $missing));
}
foreach ($used as $u) t("the answer uses esc_html__('$u')", strpos($pub, "esc_html__('$u', 'easy-form-builder')") !== false);

/* JavaScript. */
$core = file_get_contents("$root/public/assets/js/core-efb.js");
t('core-efb.js reads no checkYourEmail phrase (it never existed)', strpos($core, 'checkYourEmail') === false);
$new = file_get_contents("$root/includes/admin/assets/js/new-efb.js");
foreach (array('Please Hard Refresh', 'Network error or upload failed', 'Invalid JSON response from server') as $s) t("new-efb.js shows no '$s'", strpos($new, $s) === false);
t('new-efb.js answers with the translated phrases (somethingWentWrongPleaseRefresh / errorSomthingWrong)', strpos($new, 'tx.somethingWentWrongPleaseRefresh || tx.errorSomthingWrong') !== false && strpos($new, 'efb_var.text.errorSomthingWrong || efb_var.text.somethingWentWrongPleaseRefresh') !== false);
/* Those keys are in the phrase list the public page sends (text_ of the form page). */
foreach (array('somethingWentWrongPleaseRefresh', 'errorSomthingWrong', 'fileUploadNetworkError') as $k) {
	t("the form page sends the phrase $k", preg_match('/\$this->text_ = \[[^;]*["\']' . $k . '["\']/s', $pub) === 1);
}

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
