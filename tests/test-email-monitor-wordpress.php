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
$next_run = wp_next_scheduled(\Emsfb\Email_Monitor::WEEKLY_HOOK);
efb_email_monitor_test(
	'weekly monitor event is scheduled',
	(bool) $next_run
);

// The report day is a deliberate choice, not an accident of when the plugin
// happened to be activated, so pin it. sync_schedule() rewrites any event that
// no longer lands on these constants, which is also how a changed report day
// reaches installations that were already scheduled on the previous one.
$scheduled_local = (new DateTimeImmutable('@' . (int) $next_run))->setTimezone(wp_timezone());
efb_email_monitor_test(
	sprintf(
		'weekly report runs on weekday %d at %02d:00 site time (got %s %s)',
		\Emsfb\Email_Monitor::WEEKLY_REPORT_WEEKDAY,
		\Emsfb\Email_Monitor::WEEKLY_REPORT_HOUR,
		$scheduled_local->format('D'),
		$scheduled_local->format('H:i')
	),
	(int) $scheduled_local->format('w') === \Emsfb\Email_Monitor::WEEKLY_REPORT_WEEKDAY
		&& (int) $scheduled_local->format('G') === \Emsfb\Email_Monitor::WEEKLY_REPORT_HOUR
		&& (int) $scheduled_local->format('i') === 0
);

efb_email_monitor_test(
	'the scheduled run is in the future',
	(int) $next_run > time()
);

$status = \Emsfb\Email_Monitor::get_public_status();
efb_email_monitor_test(
	'public status contains user-facing scheduling data',
	is_array($status)
		&& isset($status['enabled'], $status['can_manage'], $status['state'], $status['next_run'])
);
