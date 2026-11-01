<?php
/**
 * The owner's "my host can send email" answer: wp_ajax_efb_confirm_email_sending.
 *
 *   A. What it writes: the switch as a real boolean (never the number 1 that
 *      marks a pre-4.1.2 site), a dated record of the owner's own kind, the
 *      address when one was given - and nothing in the first-run options.
 *   B. Who may call it: administrators only.
 *
 * Run from inside a WordPress install, or with EFB_WP_LOAD set:
 *   php tests/test-email-confirm-ajax.php
 */

$wp_load = getenv('EFB_WP_LOAD') ?: dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit(0);
}
if (!defined('DOING_AJAX')) {
	define('DOING_AJAX', true);
}
require_once $wp_load;

if (!defined('EMSFB_PLUGIN_DIRECTORY') || !class_exists('\Emsfb\Email_Monitor')) {
	echo "[SKIP] Easy Form Builder is not active in this WordPress installation.\n";
	exit(0);
}
if (!class_exists('\Emsfb\Admin')) {
	require_once EMSFB_PLUGIN_DIRECTORY . 'includes/admin/class-Emsfb-admin.php';
}
if (!function_exists('wp_delete_user')) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
}

$passed = 0;
$failed = 0;

function efb_confirm_check($label, $condition, $detail = '') {
	global $passed, $failed;
	if ($condition) { $passed++; echo "[PASS] {$label}\n"; return; }
	$failed++;
	echo "[FAIL] {$label}" . ('' !== $detail ? " -- {$detail}" : '') . "\n";
}

function efb_confirm_call($email = '', $nonce = null) {
	$_POST = [
		'action' => 'efb_confirm_email_sending',
		'nonce'  => null === $nonce ? wp_create_nonce('wp_rest') : $nonce,
		'email'  => $email,
	];
	$_REQUEST = $_POST;
	$reflection = new ReflectionClass('\Emsfb\Admin');
	$admin = $reflection->newInstanceWithoutConstructor();
	ob_start();
	try {
		$admin->efb_confirm_email_sending();
	} catch (Exception $e) {
		// wp_die() from wp_send_json_*().
	}
	$decoded = json_decode(ob_get_clean(), true);
	return is_array($decoded) ? $decoded : [];
}

$restore = [];
foreach (['emsfb_email_status', 'emsfb_onboarding_pending', 'emsfb_onboarding_initial_install', 'emsfb_onboarding_completed_at', 'emsfb_onboarding_dismissed_at'] as $name) {
	$restore[$name] = get_option($name, null);
}
$original_settings = get_setting_Emsfb('decoded');
$original_settings = is_object($original_settings) ? clone $original_settings : null;
$original_user = get_current_user_id();

$admins  = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (empty($admins)) {
	echo "[SKIP] No administrator account to run the handler as.\n";
	exit(0);
}

$thrower = function () { return function () { throw new Exception('wp_die'); }; };
add_filter('wp_die_ajax_handler', $thrower);
add_filter('wp_die_handler', $thrower);

try {
	wp_set_current_user((int) $admins[0]);

	// Start from a site that was never checked, with the guide dismissed.
	$settings = get_setting_Emsfb('decoded');
	$settings = is_object($settings) ? $settings : new stdClass();
	$settings->smtp = false;
	get_efbFunction()->set_setting_Emsfb($settings);
	delete_option('emsfb_email_status');
	update_option('emsfb_onboarding_initial_install', 1, false);
	update_option('emsfb_onboarding_pending', 0, false);
	update_option('emsfb_onboarding_dismissed_at', '2026-11-01 10:00:00', false);
	delete_option('emsfb_onboarding_completed_at');

	efb_confirm_check('A0 the site starts unchecked', 'unchecked' === \Emsfb\Email_Monitor::get_delivery_state()['state']);

	$r = efb_confirm_call('owner@example.test');
	efb_confirm_check('A1 the answer is accepted', !empty($r['success']) && !empty($r['data']['confirmed']), wp_json_encode($r));

	get_setting_Emsfb('_clear_cache');
	$saved = get_setting_Emsfb('decoded');
	efb_confirm_check('A2 the switch is on, as a real boolean', is_object($saved) && true === $saved->smtp, var_export(is_object($saved) ? $saved->smtp : null, true));
	efb_confirm_check('A3 ... which is not the legacy numeric shape', false === \Emsfb\Email_Monitor::has_legacy_sending_confirmation($saved));
	efb_confirm_check('A4 sending is enabled for every notification', true === emsfb_is_email_sending_enabled_efb($saved));
	efb_confirm_check('A5 the address goes where notifications and reports are sent', is_object($saved) && 'owner@example.test' === $saved->emailSupporter);

	$status = get_option('emsfb_email_status');
	efb_confirm_check('A6 the record is the owner\'s own kind, dated',
		is_array($status) && 'email_sending_confirmed_by_owner' === $status['message']['id'] && 'ok_set_smtp' === $status['status'] && !empty($status['details']['test_timestamp']) && 'owner' === $status['details']['source'],
		wp_json_encode($status));
	efb_confirm_check('A7 ... and it carries no measurement', !isset($status['details']['delivered']) && !isset($status['details']['delivery_score']));

	$state = \Emsfb\Email_Monitor::get_delivery_state();
	efb_confirm_check('A8 the site is now confirmed, with the date', 'confirmed' === $state['state'] && '' !== $state['confirmed_at_display'], wp_json_encode($state));
	efb_confirm_check('A9 the reply carries that state for the page to adopt', isset($r['data']['delivery']['state']) && 'confirmed' === $r['data']['delivery']['state']);

	efb_confirm_check('A10 the first-run options are untouched',
		0 === (int) get_option('emsfb_onboarding_pending') && '2026-11-01 10:00:00' === get_option('emsfb_onboarding_dismissed_at') && null === get_option('emsfb_onboarding_completed_at', null));

	$r = efb_confirm_call('not-an-address');
	get_setting_Emsfb('_clear_cache');
	$saved = get_setting_Emsfb('decoded');
	efb_confirm_check('A11 an invalid address is ignored, the answer still counts', !empty($r['success']) && 'owner@example.test' === $saved->emailSupporter && true === $saved->smtp);

	// B. Who may call it
	$settings = get_setting_Emsfb('decoded');
	$settings->smtp = false;
	get_efbFunction()->set_setting_Emsfb($settings);
	delete_option('emsfb_email_status');

	$r = efb_confirm_call('owner@example.test', 'not-a-valid-nonce');
	get_setting_Emsfb('_clear_cache');
	efb_confirm_check('B1 a bad nonce is refused', empty($r['success']) && false === get_setting_Emsfb('decoded')->smtp, wp_json_encode($r));

	$editor_id = wp_insert_user(['user_login' => 'efb-confirm-editor-' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(20), 'role' => 'editor']);
	if (!is_wp_error($editor_id)) {
		$editor = get_user_by('id', $editor_id);
		$editor->add_cap('Emsfb');
		wp_set_current_user($editor_id);
		$r = efb_confirm_call('owner@example.test');
		get_setting_Emsfb('_clear_cache');
		efb_confirm_check('B2 a form editor with the Emsfb capability may not switch the site\'s sending on', empty($r['success']) && false === get_setting_Emsfb('decoded')->smtp && false === get_option('emsfb_email_status', false), wp_json_encode($r));
		wp_set_current_user((int) $admins[0]);
		wp_delete_user($editor_id);
	} else {
		echo "[SKIP] could not create an editor to test the capability gate\n";
	}

	wp_set_current_user(0);
	$r = efb_confirm_call('owner@example.test');
	efb_confirm_check('B3 a visitor is refused', empty($r['success']), wp_json_encode($r));
	wp_set_current_user((int) $admins[0]);

	// C. Wiring
	$admin_src = (string) file_get_contents(EMSFB_PLUGIN_DIRECTORY . 'includes/admin/class-Emsfb-admin.php');
	efb_confirm_check('C1 the AJAX action is registered', false !== strpos($admin_src, "add_action('wp_ajax_efb_confirm_email_sending', [\$this, 'efb_confirm_email_sending'])"));
	$handler = preg_match('/public function efb_confirm_email_sending\(\) \{(.*?)\n    \}/s', $admin_src, $m) ? $m[1] : '';
	efb_confirm_check('C2 it does not end the first-run guide', false === strpos($handler, 'emsfb_complete_onboarding_efb'));
	efb_confirm_check('C3 it stores a boolean, not the legacy number', false !== strpos($handler, '$ac->smtp = true;') && false === strpos($handler, '$ac->smtp = 1;'));
} catch (Throwable $e) {
	$failed++;
	echo '[FAIL] unexpected ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
} finally {
	remove_filter('wp_die_ajax_handler', $thrower);
	remove_filter('wp_die_handler', $thrower);
	foreach ($restore as $name => $value) {
		null === $value ? delete_option($name) : update_option($name, $value, false);
	}
	if (null !== $original_settings) {
		get_efbFunction()->set_setting_Emsfb($original_settings);
	}
	get_setting_Emsfb('_clear_cache');
	wp_set_current_user($original_user);
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
