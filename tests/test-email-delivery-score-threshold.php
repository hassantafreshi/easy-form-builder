<?php
/**
 * The delivery score threshold: a test email that arrives but scores badly is
 * not a working mail setup.
 *
 * The tester service answers can_send_email on arrival alone, so a message that
 * landed in the spam folder with a score of 12 still comes back as a success.
 * Email_Monitor draws the line at MIN_DELIVERY_SCORE, and the stored status is
 * what makes render_delivery_failure_notice speak up in wp-admin.
 *
 * Run: php tests/test-email-delivery-score-threshold.php
 */

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!file_exists($wp_load)) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit(0);
}

require_once $wp_load;

function efb_delivery_score_test($label, $condition) {
	if (!$condition) {
		fwrite(STDERR, "[FAIL] {$label}\n");
		exit(1);
	}
	echo "[PASS] {$label}\n";
}

efb_delivery_score_test(
	'email monitor service is loaded',
	class_exists('\Emsfb\Email_Monitor')
);

$min = \Emsfb\Email_Monitor::MIN_DELIVERY_SCORE;

efb_delivery_score_test(
	'the minimum sits below the healthy threshold',
	$min > 0 && $min < \Emsfb\Email_Monitor::HEALTHY_SCORE
);

efb_delivery_score_test(
	'a delivered probe scoring above the minimum is confirmed',
	\Emsfb\Email_Monitor::is_delivery_confirmed(['can_send_email' => true, 'score' => 60]) === true
);

efb_delivery_score_test(
	'a delivered probe scoring below the minimum is not confirmed',
	\Emsfb\Email_Monitor::is_delivery_confirmed(['can_send_email' => true, 'score' => 30]) === false
);

efb_delivery_score_test(
	'the minimum itself still counts as delivery',
	\Emsfb\Email_Monitor::is_delivery_confirmed(['can_send_email' => true, 'score' => $min]) === true
);

efb_delivery_score_test(
	'a report without a score is judged on the service flag alone',
	\Emsfb\Email_Monitor::is_delivery_confirmed(['can_send_email' => true]) === true
		&& \Emsfb\Email_Monitor::is_delivery_confirmed(['can_send_email' => false]) === false
);

efb_delivery_score_test(
	'a low score on an undelivered probe is not reported as a low-score case',
	\Emsfb\Email_Monitor::is_delivery_score_too_low(['can_send_email' => false, 'score' => 10]) === false
		&& \Emsfb\Email_Monitor::is_delivery_score_too_low(['can_send_email' => true, 'score' => 10]) === true
);

efb_delivery_score_test(
	'the low-score sentence names the measured and the required score',
	strpos(\Emsfb\Email_Monitor::get_low_score_message(12), (string) number_format_i18n(12)) !== false
		&& strpos(\Emsfb\Email_Monitor::get_low_score_message(12), (string) number_format_i18n($min)) !== false
);

// The panel scores the same run in the browser, so it needs the same number.
$public_status = \Emsfb\Email_Monitor::get_public_status();
efb_delivery_score_test(
	'the panel is handed the same threshold',
	isset($public_status['min_delivery_score']) && (int) $public_status['min_delivery_score'] === (int) $min
);

// What the dashboard notice actually reads. The stored status is restored
// afterwards so running this test never changes what an administrator sees.
$option = 'emsfb_email_monitor_last_status';
$original_status = get_option($option, null);

$save_status = new ReflectionMethod('\Emsfb\Email_Monitor', 'save_status');
$save_status->setAccessible(true);

$save_status->invoke(null, 'success', 'delivered', 'weekly', ['can_send_email' => true, 'score' => 30]);
$stored_low = get_option($option, []);
efb_delivery_score_test(
	'a low score is stored as "cannot send", which is what raises the admin notice',
	isset($stored_low['can_send_email']) && $stored_low['can_send_email'] === false
);

$save_status->invoke(null, 'success', 'delivered', 'weekly', ['can_send_email' => true, 'score' => 75]);
$stored_ok = get_option($option, []);
efb_delivery_score_test(
	'a healthy score is still stored as "can send"',
	isset($stored_ok['can_send_email']) && $stored_ok['can_send_email'] === true
);

if ($original_status === null) {
	delete_option($option);
} else {
	update_option($option, $original_status, false);
}

// Installations from 4.1.2 and earlier: no monitor ever ran there, and the
// settings switch was stored as the number 1. Those sites must not be told
// their email is broken.
efb_delivery_score_test(
	'the legacy numeric switch silences the notice',
	\Emsfb\Email_Monitor::has_legacy_sending_confirmation((object) ['smtp' => 1]) === true
		&& \Emsfb\Email_Monitor::has_legacy_sending_confirmation((object) ['smtp' => '1']) === true
		&& \Emsfb\Email_Monitor::has_legacy_sending_confirmation(['smtp' => 1]) === true
);

efb_delivery_score_test(
	'a current boolean switch does not silence the notice',
	\Emsfb\Email_Monitor::has_legacy_sending_confirmation((object) ['smtp' => true]) === false
		&& \Emsfb\Email_Monitor::has_legacy_sending_confirmation((object) ['smtp' => 'true']) === false
);

efb_delivery_score_test(
	'a switch that is off never silences the notice',
	\Emsfb\Email_Monitor::has_legacy_sending_confirmation((object) ['smtp' => 0]) === false
		&& \Emsfb\Email_Monitor::has_legacy_sending_confirmation((object) ['smtp' => '0']) === false
		&& \Emsfb\Email_Monitor::has_legacy_sending_confirmation((object) ['smtp' => false]) === false
		&& \Emsfb\Email_Monitor::has_legacy_sending_confirmation((object) []) === false
);

echo "\nAll delivery score threshold checks passed.\n";
