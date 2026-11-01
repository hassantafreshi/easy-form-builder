<?php
/**
 * What Email_Monitor::get_delivery_state() says, record by record.
 *
 * Three answers, and the whole first-run flow depends on which one a site
 * gets: `unchecked` (ask the owner when a form gets an email field),
 * `confirmed` (the owner turned sending on without a check) and `tested`
 * (a check the owner ran reached a verdict). The background monitor's own
 * record must never count as the owner's.
 *
 * Also covered: the automated pass no longer overwrites a record the owner
 * produced, and the dashboard notice stays silent while nothing is checked.
 *
 * Run from inside a WordPress install (the plugin active), or with
 * EFB_WP_LOAD pointing at wp-load.php:
 *   php tests/test-email-delivery-state.php
 */

$wp_load = getenv('EFB_WP_LOAD') ?: dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit(0);
}
require_once $wp_load;

if (!class_exists('\Emsfb\Email_Monitor')) {
	fwrite(STDERR, "[FAIL] Email_Monitor is not loaded\n");
	exit(1);
}

const EFB_DS_MONITOR = 'emsfb_email_monitor_last_status';
const EFB_DS_PANEL   = 'emsfb_email_status';

$passed = 0;
$failed = 0;

function efb_ds_check($label, $condition, $detail = '') {
	global $passed, $failed;
	if ($condition) { $passed++; echo "[PASS] {$label}\n"; return; }
	$failed++;
	echo "[FAIL] {$label}" . ('' !== $detail ? " -- {$detail}" : '') . "\n";
}

/** Swap the decoded settings for this request only (no settings row is written). */
function efb_ds_set_smtp($smtp) {
	$settings = get_setting_Emsfb('decoded');
	$settings = is_object($settings) ? clone $settings : new stdClass();
	if ('(absent)' === $smtp) { unset($settings->smtp); } else { $settings->smtp = $smtp; }
	get_setting_Emsfb('_clear_cache');
	wp_cache_set('settings:decoded', $settings, 'emsfb', 3600);
}

function efb_ds_utc($minutes_ago) {
	return gmdate('Y-m-d H:i:s', time() - ($minutes_ago * MINUTE_IN_SECONDS));
}

function efb_ds_state($monitor, $panel, $smtp) {
	if (null === $monitor) { delete_option(EFB_DS_MONITOR); } else { update_option(EFB_DS_MONITOR, $monitor, false); }
	if (null === $panel)   { delete_option(EFB_DS_PANEL); }   else { update_option(EFB_DS_PANEL, $panel, false); }
	efb_ds_set_smtp($smtp);
	return \Emsfb\Email_Monitor::get_delivery_state();
}

$panel = function ($id, $details, $minutes_ago = 2, $status = 'error') {
	return [
		'status'  => $status,
		'message' => ['title' => 't', 'description' => 'd', 'id' => $id],
		'details' => array_merge(['test_timestamp' => efb_ds_utc($minutes_ago)], $details),
	];
};
$monitor_failed = [
	'state' => 'failed', 'message' => 'x', 'context' => 'weekly',
	'checked_at' => wp_date('Y-m-d H:i:s', time() - 300),
	'can_send_email' => false, 'delivered' => false, 'score' => null, 'reason' => 'expired',
];
$monitor_ok = [
	'state' => 'success', 'message' => 'x', 'context' => 'activation',
	'checked_at' => wp_date('Y-m-d H:i:s', time() - 300),
	'can_send_email' => true, 'delivered' => true, 'score' => 88, 'reason' => 'analyzed',
];

$original = [
	EFB_DS_MONITOR => get_option(EFB_DS_MONITOR, null),
	EFB_DS_PANEL   => get_option(EFB_DS_PANEL, null),
];
$original_user = get_current_user_id();
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!empty($admins)) { wp_set_current_user((int) $admins[0]); }

try {
	echo "\n=== unchecked ===\n";
	$s = efb_ds_state(null, null, false);
	efb_ds_check('U1 a fresh install is unchecked', 'unchecked' === $s['state'] && false === $s['notifications'], wp_json_encode($s));
	efb_ds_check('U2 the thresholds and the capability travel with it', 20 === $s['min_delivery_score'] && 70 === $s['healthy_score'] && is_bool($s['can_manage']));

	$s = efb_ds_state($monitor_ok, null, false);
	efb_ds_check('U3 a passing background probe does not count as the owner having checked', 'unchecked' === $s['state'] && 'healthy' === $s['monitor_outcome'], wp_json_encode($s));

	$s = efb_ds_state($monitor_failed, null, false);
	efb_ds_check('U4 a failing background probe is reported for wording only', 'unchecked' === $s['state'] && 'undelivered' === $s['monitor_outcome'], wp_json_encode($s));

	$s = efb_ds_state(null, $panel('automated_email_test_ok', ['stage' => 'automated', 'delivered' => true], 2, 'ok'), false);
	efb_ds_check('U5 the probe\'s own status record is not a verdict of the owner', 'unchecked' === $s['state'], wp_json_encode($s));

	$s = efb_ds_state(null, $panel('email_test_pending', ['can_send_email' => false], 3, 'warning'), false);
	efb_ds_check('U6 a check that never reported back is unchecked, and says since when', 'unchecked' === $s['state'] && '' !== $s['pending_since'], wp_json_encode($s));
	efb_ds_check('U6b ... in the site\'s own date format, for people', '' !== $s['pending_since_display'] && $s['pending_since_display'] !== $s['pending_since'], wp_json_encode($s));

	$s = efb_ds_state(null, $panel('email_test_pending', ['can_send_email' => false], 60, 'warning'), false);
	efb_ds_check('U7 an hour later that pending record is just absent', 'unchecked' === $s['state'] && '' === $s['pending_since'], wp_json_encode($s));

	$s = efb_ds_state(null, $panel('service_start_error', ['reason' => 'service_start_error'], 2), false);
	efb_ds_check('U8 a check that could not reach the service proves nothing', 'unchecked' === $s['state'], wp_json_encode($s));

	echo "\n=== confirmed ===\n";
	$owner = $panel('email_sending_confirmed_by_owner', ['source' => 'owner'], 2, 'ok_set_smtp');
	$s = efb_ds_state(null, $owner, true);
	efb_ds_check('C1 the owner\'s answer in the builder is confirmed, with its date', 'confirmed' === $s['state'] && true === $s['notifications'] && '' !== $s['confirmed_at'] && '' !== $s['confirmed_at_display'], wp_json_encode($s));

	$s = efb_ds_state(null, ['status' => 'ok_set_smtp', 'message' => ['title' => 'configured', 'description' => 'd', 'id' => 'email_settings_configured']], 'true');
	efb_ds_check('C2 the undated switch of an older version is confirmed without a date', 'confirmed' === $s['state'] && '' === $s['confirmed_at'], wp_json_encode($s));

	$s = efb_ds_state(null, null, 1);
	efb_ds_check('C3 a pre-4.1.2 site (smtp = 1) is confirmed, nothing else', 'confirmed' === $s['state'] && '' === $s['confirmed_at'], wp_json_encode($s));

	$s = efb_ds_state($monitor_failed, $owner, true);
	efb_ds_check('C4 a failing probe does not undo the owner\'s confirmation', 'confirmed' === $s['state'] && 'undelivered' === $s['monitor_outcome'], wp_json_encode($s));

	echo "\n=== tested ===\n";
	$s = efb_ds_state(null, $panel('email_settings_configured', ['delivered' => true, 'delivery_score' => 85, 'can_send_email' => true], 2, 'ok_set_smtp'), true);
	efb_ds_check('T1 a passed check is tested / healthy with its score and date', 'tested' === $s['state'] && 'healthy' === $s['outcome'] && 85.0 === $s['score'] && '' !== $s['checked_at_display'], wp_json_encode($s));

	$s = efb_ds_state(null, $panel('email_settings_configured', ['delivered' => true, 'delivery_score' => 55, 'can_send_email' => true], 2, 'ok_set_smtp'), true);
	efb_ds_check('T2 a passed check under the healthy line is tested / spam', 'tested' === $s['state'] && 'spam' === $s['outcome'], wp_json_encode($s));

	$s = efb_ds_state(null, $panel('email_test_low_score', ['delivered' => true, 'delivery_score' => 12, 'can_send_email' => false]), false);
	efb_ds_check('T3 a delivered probe scoring under the sending line is tested / undelivered', 'tested' === $s['state'] && 'undelivered' === $s['outcome'] && false === $s['notifications'], wp_json_encode($s));

	$s = efb_ds_state(null, $panel('email_test_failed', ['delivered' => false, 'delivery_score' => null, 'can_send_email' => false, 'send_stage' => 'handed_off']), false);
	efb_ds_check('T4 nothing arrived is tested / undelivered', 'tested' === $s['state'] && 'undelivered' === $s['outcome'], wp_json_encode($s));

	$s = efb_ds_state(null, $panel('mail_function_failed', ['stage' => 'send', 'send_stage' => 'wp_mail_failed']), false);
	efb_ds_check('T5 wp_mail() returning false is tested / undelivered', 'tested' === $s['state'] && 'undelivered' === $s['outcome'], wp_json_encode($s));

	$s = efb_ds_state($monitor_ok, $panel('email_test_failed', ['delivered' => false, 'delivery_score' => null, 'can_send_email' => false]), false);
	efb_ds_check('T6 a later passing probe does not turn the owner\'s failed check into a pass', 'tested' === $s['state'] && 'undelivered' === $s['outcome'], wp_json_encode($s));

	echo "\n=== the probe keeps its hands off the owner\'s record ===\n";
	$ref = new ReflectionMethod('\Emsfb\Email_Monitor', 'mark_email_ready');
	$ref->setAccessible(true);

	update_option(EFB_DS_PANEL, $panel('email_test_failed', ['delivered' => false, 'delivery_score' => null, 'can_send_email' => false]), false);
	$ref->invoke(null);
	$after = get_option(EFB_DS_PANEL);
	efb_ds_check('M1 a passing probe leaves the owner\'s "nothing arrived" record alone', 'email_test_failed' === $after['message']['id'], wp_json_encode($after));

	update_option(EFB_DS_PANEL, $panel('email_test_low_score', ['delivered' => true, 'delivery_score' => 12]), false);
	$ref->invoke(null);
	$after = get_option(EFB_DS_PANEL);
	efb_ds_check('M2 ... and the owner\'s low-score record', 'email_test_low_score' === $after['message']['id']);

	update_option(EFB_DS_PANEL, $owner, false);
	$ref->invoke(null);
	$after = get_option(EFB_DS_PANEL);
	efb_ds_check('M3 ... and the owner\'s confirmation', 'email_sending_confirmed_by_owner' === $after['message']['id']);

	delete_option(EFB_DS_PANEL);
	$ref->invoke(null);
	$after = get_option(EFB_DS_PANEL);
	efb_ds_check('M4 an empty slot is still filled by the probe', is_array($after) && 'automated_email_test_ok' === $after['message']['id']);

	update_option(EFB_DS_PANEL, $panel('email_test_pending', ['can_send_email' => false], 3, 'warning'), false);
	$ref->invoke(null);
	$after = get_option(EFB_DS_PANEL);
	efb_ds_check('M5 a check that never reported back gives way to the probe', 'automated_email_test_ok' === $after['message']['id']);

	update_option(EFB_DS_PANEL, $panel('service_start_error', ['reason' => 'service_start_error']), false);
	$ref->invoke(null);
	$after = get_option(EFB_DS_PANEL);
	efb_ds_check('M6 a check that could not reach the service gives way to the probe', 'automated_email_test_ok' === $after['message']['id']);

	echo "\n=== the activation probe steps aside for the owner ===\n";
	wp_clear_scheduled_hook(\Emsfb\Email_Monitor::LIFECYCLE_HOOK, ['activation']);
	wp_schedule_single_event(time() + 45, \Emsfb\Email_Monitor::LIFECYCLE_HOOK, ['activation']);
	efb_ds_check('L1 the probe is queued', false !== wp_next_scheduled(\Emsfb\Email_Monitor::LIFECYCLE_HOOK, ['activation']));
	wp_clear_scheduled_hook(\Emsfb\Email_Monitor::LIFECYCLE_HOOK, ['update']);
	wp_schedule_single_event(time() + 45, \Emsfb\Email_Monitor::LIFECYCLE_HOOK, ['update']);
	\Emsfb\Email_Monitor::cancel_pending_lifecycle_test();
	efb_ds_check('L2 the owner\'s own check unqueues it', false === wp_next_scheduled(\Emsfb\Email_Monitor::LIFECYCLE_HOOK, ['activation']));
	efb_ds_check('L3 ... and leaves the update-time probe alone', false !== wp_next_scheduled(\Emsfb\Email_Monitor::LIFECYCLE_HOOK, ['update']));
	wp_clear_scheduled_hook(\Emsfb\Email_Monitor::LIFECYCLE_HOOK, ['update']);
} catch (Throwable $e) {
	$failed++;
	echo '[FAIL] unexpected ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
} finally {
	foreach ($original as $name => $value) {
		null === $value ? delete_option($name) : update_option($name, $value, false);
	}
	get_setting_Emsfb('_clear_cache');
	wp_set_current_user($original_user);
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
