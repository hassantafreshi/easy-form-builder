<?php
/**
 * BUG-03 / BUGS-FOUND 275: send_email_state_new() took isset($state[2]) as the content type, so a string state gave one
 * letter of its own name ('recovery'[2] = 'c', 'resumeLink'[2] = 's'). The type is now explicit (an 8th argument),
 * else the third item of a paired state array, else traking_link.
 *
 * Run: php tests/test-email-content-type.php   (no WordPress needed)
 */
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../includes/class-email-handler.php';
$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

$ok = method_exists('EmsfbEmailHandler', 'content_type_for_state');
t('EmsfbEmailHandler::content_type_for_state() exists', $ok);
if ($ok) {
	$c = function ($s, $e = null) { return EmsfbEmailHandler::content_type_for_state($s, $e); };
	foreach (array('recovery', 'resumeLink', 'newMessage', 'otpCode', 'register', 'ab', '') as $st) {
		t("string state '$st' is a name, not a list: traking_link", $c($st) === 'traking_link', var_export($c($st), true));
	}
	t('a paired state array keeps its type (just_message)', $c(array('newMessage', 'notiToUserFormFilled_TrackingCode', 'just_message')) === 'just_message');
	t('a paired state array keeps its type (message_link)', $c(array('newMessage', 'newMessage', 'message_link')) === 'message_link');
	t('a paired state array without a type: traking_link', $c(array('newMessage', 'newMessage')) === 'traking_link');
	t('a non-string third item is ignored', $c(array('a', 'b', array('x'))) === 'traking_link');
	t('an explicit type wins over a string state', $c('resumeLink', 'just_message') === 'just_message');
	t('an explicit type wins over the array', $c(array('a', 'b', 'message_link'), 'just_message') === 'just_message');
	t('an empty explicit type falls back', $c(array('a', 'b', 'message_link'), '') === 'message_link');
}
$src = file_get_contents(__DIR__ . '/../includes/class-email-handler.php');
t('send_email_state_new() takes the type explicitly and asks the helper', strpos($src, 'public function send_email_state_new($to, $sub, $cont, $pro, $state, $link, $st = "null", $content_type = null)') !== false
	&& strpos($src, '$email_content_type = self::content_type_for_state($state, $content_type);') !== false);
// B6 (B5 hand-off): efbFunction's wrapper passes the 8th argument on (it dropped it).
$fn = (string) file_get_contents(__DIR__ . '/../includes/functions.php');
t('efbFunction::send_email_state_new() takes and passes the content type (8th argument; the declared seven stay, for subclasses)', strpos($fn, 'public function send_email_state_new($to, $sub, $cont, $pro, $state, $link, $st = "null") {' . "\n\t\t" . '$content_type = func_num_args() > 7 ? func_get_arg(7) : null;') !== false
	&& strpos($fn, '$emailHandler->send_email_state_new($to, $sub, $cont, $pro, $state, $link, $st, $content_type);') !== false);
t('the old isset($state[2]) read is gone', strpos($src, "\$email_content_type = isset(\$state[2]) ? \$state[2] : 'traking_link';") === false);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
