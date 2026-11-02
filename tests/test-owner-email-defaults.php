<?php
/**
 * The owner's first form must email them, with the submission in it.
 *
 * Covers the retention fixes of 4.3.1:
 *   1. a new install starts with "This site can send emails" ON;
 *   2. an email check never switches sending off (the helper is gone and the
 *      reply always says sending_turned_off = false);
 *   3. a form that never chose its email content sends the submitted content
 *      and the link ("msg"); a form that chose "cc" keeps it;
 *   4. the owner's copy carries Reply-To = the visitor's address, the
 *      visitor's own copy does not, and only the owner's copy drops the
 *      "do not reply" line;
 *   5. on the default the submitted content goes to the owner only, so the
 *      form cannot be used to mail arbitrary text to any address.
 *
 * Nothing is sent: wp_mail is short-circuited with pre_wp_mail and the
 * headers are captured.
 *
 * Run from inside a WordPress install (the plugin active), or with
 * EFB_WP_LOAD pointing at wp-load.php:
 *   php tests/test-owner-email-defaults.php
 */

$wp_load = getenv('EFB_WP_LOAD') ?: dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit(0);
}
require_once $wp_load;

$passed = 0;
$failed = 0;
function efb_oe_check($label, $condition, $detail = '') {
	global $passed, $failed;
	if ($condition) { $passed++; echo "[PASS] {$label}\n"; return; }
	$failed++;
	echo "[FAIL] {$label}" . ('' !== $detail ? " -- {$detail}" : '') . "\n";
}

// 1. New installs: sending on.
$install_src = file_get_contents(dirname(__DIR__) . '/includes/class-Emsfb-install.php');
efb_oe_check('a new install seeds "This site can send emails" ON', false !== strpos($install_src, "'smtp' => true,") && false === strpos($install_src, "'smtp' => false,"));
$defaults = \Emsfb::get_default_settings_efb();
efb_oe_check('the default settings object has sending ON', true === $defaults->smtp);

// 2. A check never switches sending off.
$admin_src = file_get_contents(dirname(__DIR__) . '/includes/admin/class-Emsfb-admin.php');
efb_oe_check('the "turn sending off after a failed check" helper is gone', false === strpos($admin_src, 'turn_sending_off_after_failed_check_efb'));
efb_oe_check('no check path writes smtp = false', 0 === preg_match('/->smtp\s*=\s*false/', $admin_src));

// 3. Default email content.
if (!class_exists('\Emsfb\efbPublic') && !class_exists('Emsfb\efbPublic')) {
	$public_class = null;
	foreach (get_declared_classes() as $c) {
		if (method_exists($c, 'efb_email_noti_type_efb')) { $public_class = $c; break; }
	}
} else {
	$public_class = class_exists('\Emsfb\efbPublic') ? '\Emsfb\efbPublic' : 'Emsfb\efbPublic';
}
if (null === $public_class) {
	require_once dirname(__DIR__) . '/includes/class-Emsfb-public.php';
	foreach (get_declared_classes() as $c) {
		if (method_exists($c, 'efb_email_noti_type_efb')) { $public_class = $c; break; }
	}
}
efb_oe_check('the public class exposes efb_email_noti_type_efb()', null !== $public_class);
if (null !== $public_class) {
	$ref = new ReflectionClass($public_class);
	$pub = $ref->newInstanceWithoutConstructor();
	efb_oe_check('a form that never chose sends content and link ("msg")', 'msg' === $pub->efb_email_noti_type_efb([['type' => 'form']]));
	efb_oe_check('a form that chose "cc" keeps code and link', 'cc' === $pub->efb_email_noti_type_efb([['email_noti_type' => 'cc']]));
	efb_oe_check('a form that chose "just_msg" keeps content only', 'just_msg' === $pub->efb_email_noti_type_efb([['email_noti_type' => 'just_msg']]));
	efb_oe_check('an unknown value falls back to "msg"', 'msg' === $pub->efb_email_noti_type_efb([['email_noti_type' => '<script>']]));

	// The visitor's address for Reply-To.
	$m = $ref->getMethod('efb_submitter_email_efb');
	$m->setAccessible(true);
	efb_oe_check('Reply-To is the first valid email field', 'sara@example.com' === $m->invoke($pub, [
		['type' => 'text', 'value' => 'Sara'],
		['type' => 'email', 'value' => 'not-an-email'],
		['type' => 'email', 'value' => ' sara@example.com '],
		['type' => 'email', 'value' => 'other@example.com'],
	]));
	efb_oe_check('no email field: no Reply-To', '' === $m->invoke($pub, [['type' => 'text', 'value' => 'x@y.z']]));
	efb_oe_check('garbage input: no Reply-To', '' === $m->invoke($pub, 'nope'));
}

// 4. Reply-To on the owner's copy only.
if (!class_exists('EmsfbEmailHandler')) {
	require_once dirname(__DIR__) . '/includes/class-email-handler.php';
}
$captured = [];
$capture = function ($null, $atts) use (&$captured) {
	$captured[] = ['to' => $atts['to'], 'headers' => (array) $atts['headers'], 'message' => (string) $atts['message']];
	return true; // short-circuit: nothing leaves the site
};
add_filter('pre_wp_mail', $capture, 10, 2);
$reply = function () { return 'visitor@example.com'; };
add_filter('emsfb_owner_reply_to_efb', $reply);
add_filter('efb_shield_allow_side_effect', '__return_true', 999);

$handler = new EmsfbEmailHandler();
$handler->send_email_state_new(
	['owner@example.com', 'visitor@example.com'],
	['New message', 'We received your message'],
	['ABC123', 'ABC123'],
	false,
	['newMessage', 'notiToUserFormFilled', 'message_link'],
	['https://site.test/a', 'https://site.test/b'],
	'null'
);
remove_filter('emsfb_owner_reply_to_efb', $reply);
remove_filter('pre_wp_mail', $capture, 10);

$owner = null; $visitor = null;
foreach ($captured as $mail) {
	$to = is_array($mail['to']) ? implode(',', $mail['to']) : (string) $mail['to'];
	if (false !== strpos($to, 'owner@example.com')) $owner = $mail;
	if (false !== strpos($to, 'visitor@example.com')) $visitor = $mail;
}
$has_reply = function ($mail) {
	if (!$mail) return false;
	foreach ($mail['headers'] as $h) { if (0 === stripos(trim($h), 'Reply-To:') && false !== strpos($h, 'visitor@example.com')) return true; }
	return false;
};
efb_oe_check('both copies were composed (captured, not sent)', null !== $owner && null !== $visitor, 'captured: ' . count($captured));
efb_oe_check('the owner\'s copy has Reply-To: the visitor', $has_reply($owner));
efb_oe_check('the visitor\'s own copy has no Reply-To', null !== $visitor && !$has_reply($visitor));
efb_oe_check('the owner\'s copy (with Reply-To) does not say "do not reply"', null !== $owner && false === stripos($owner['message'], 'Please do not reply'));
efb_oe_check('the visitor\'s copy still says "do not reply"', null !== $visitor && false !== stripos($visitor['message'], 'Please do not reply'));

// 5. On the default, the submitted content goes to the owner only.
$captured = [];
add_filter('pre_wp_mail', $capture, 10, 2);
$secret = 'Unique-Submitted-Text-' . wp_generate_password(8, false);
$handler->send_email_state_new(
	['owner@example.com', 'visitor@example.com'],
	['New message', 'We received your message'],
	[['ABC123', '<p>' . $secret . '</p>'], 'ABC123'],
	false,
	['newMessage', 'notiToUserFormFilled_TrackingCode', 'message_link', 'traking_link'],
	['https://site.test/a', 'https://site.test/b'],
	'null'
);
remove_filter('pre_wp_mail', $capture, 10);
$owner = null; $visitor = null;
foreach ($captured as $mail) {
	$to = is_array($mail['to']) ? implode(',', $mail['to']) : (string) $mail['to'];
	if (false !== strpos($to, 'owner@example.com')) $owner = $mail;
	if (false !== strpos($to, 'visitor@example.com')) $visitor = $mail;
}
efb_oe_check('default: the owner\'s copy carries the submitted content', null !== $owner && false !== strpos($owner['message'], $secret));
efb_oe_check('default: the visitor\'s copy does not carry it', null !== $visitor && false === strpos($visitor['message'], $secret));
efb_oe_check('default: the visitor\'s copy still has the confirmation code', null !== $visitor && false !== strpos($visitor['message'], 'ABC123'));

if (null !== $public_class) {
	efb_oe_check('a form that never chose is not "chosen"', false === $pub->efb_email_noti_type_chosen_efb([['type' => 'form']]));
	efb_oe_check('a form that chose "msg" is "chosen"', true === $pub->efb_email_noti_type_chosen_efb([['email_noti_type' => 'msg']]));
}

// Without the filter, no Reply-To at all.
$captured = [];
add_filter('pre_wp_mail', $capture, 10, 2);
$handler->send_email_state_new(
	['owner@example.com', 'null'],
	['New message', ''],
	['ABC123', ''],
	false,
	['newMessage', 'notiToUserFormFilled', 'message_link'],
	['https://site.test/a', ''],
	'null'
);
remove_filter('pre_wp_mail', $capture, 10);
efb_oe_check('no visitor address: the owner\'s copy has no Reply-To', !empty($captured) && !$has_reply($captured[0]));

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
