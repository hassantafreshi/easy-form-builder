<?php

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!file_exists($wp_load)) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit(0);
}

require_once $wp_load;

function efb_email_monitor_test($label, $condition) {
	if (!$condition) {
		fwrite(STDERR, "[FAIL] {$label}\n");
		exit(1);
	}
	echo "[PASS] {$label}\n";
}

efb_email_monitor_test(
	'email monitor service is loaded',
	class_exists('\Emsfb\Email_Monitor')
);

efb_email_monitor_test(
	'weekly reports default to enabled',
	\Emsfb\Email_Monitor::is_enabled() === true
);

efb_email_monitor_test(
	'Pro can manage weekly reports',
	\Emsfb\Email_Monitor::can_manage_setting(1) === true
);

efb_email_monitor_test(
	'Free Plus can manage weekly reports',
	\Emsfb\Email_Monitor::can_manage_setting(3) === true
);

efb_email_monitor_test(
	'Free cannot manage weekly reports',
	\Emsfb\Email_Monitor::can_manage_setting(2) === false
);

efb_email_monitor_test(
	'boolean normalization accepts enabled values',
	\Emsfb\Email_Monitor::normalize_bool('true') === true
		&& \Emsfb\Email_Monitor::normalize_bool('0') === false
);

$original_enabled = \Emsfb\Email_Monitor::is_enabled();
$blocked_update = \Emsfb\Email_Monitor::update_enabled(!$original_enabled, 2);
efb_email_monitor_test(
	'Free plan cannot change the stored report state',
	$blocked_update === false
		&& \Emsfb\Email_Monitor::is_enabled() === $original_enabled
);

$allowed_update = \Emsfb\Email_Monitor::update_enabled(!$original_enabled, 3);
efb_email_monitor_test(
	'Free Plus can change the stored report state',
	$allowed_update === true
		&& \Emsfb\Email_Monitor::is_enabled() === !$original_enabled
);
\Emsfb\Email_Monitor::update_enabled($original_enabled, 3);

$settings = get_setting_Emsfb('decoded');
efb_email_monitor_test(
	'decoded settings expose the weekly report state',
	is_object($settings)
		&& isset($settings->weeklyEmailReport)
		&& $settings->weeklyEmailReport === true
);

\Emsfb\Email_Monitor::sync_schedule();
efb_email_monitor_test(
	'weekly monitor event is scheduled',
	(bool) wp_next_scheduled(\Emsfb\Email_Monitor::WEEKLY_HOOK)
);

$status = \Emsfb\Email_Monitor::get_public_status();
efb_email_monitor_test(
	'public status contains user-facing scheduling data',
	is_array($status)
		&& isset($status['enabled'], $status['can_manage'], $status['state'], $status['next_run'])
);
