<?php
/**
 * The 4.3.1 update turns form emails on once for a site that has them off.
 *
 * Before 4.3.1 new installs started with "This site can send emails" off and a
 * failed check switched it off, so most owners never received a submission by
 * email. The update switches it on once; an owner who turns it off afterwards
 * keeps it off, and the emsfb_upgrade_enable_email_sending filter opts out.
 *
 * Restores the settings row and the option it touches. Run inside WordPress
 * (plugin active) or with EFB_WP_LOAD pointing at wp-load.php:
 *   php tests/test-upgrade-enable-sending.php
 */

$wp_load = getenv('EFB_WP_LOAD') ?: dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit(0);
}
require_once $wp_load;

$passed = 0;
$failed = 0;
function efb_ues_check($label, $condition) {
	global $passed, $failed;
	if ($condition) { $passed++; echo "[PASS] {$label}\n"; return; }
	$failed++;
	echo "[FAIL] {$label}\n";
}

global $wpdb;
$table      = $wpdb->prefix . 'emsfb_setting';
$saved_row  = $wpdb->get_row("SELECT id, setting, email FROM `{$table}` ORDER BY id DESC LIMIT 1", ARRAY_A);
$saved_opt  = get_option('emsfb_sending_default_431', null);
$saved_json = get_option('emsfb_settings', null);

$set_sending = function ($on) {
	\Emsfb::get_setting_Emsfb('_clear_cache');
	$s = \Emsfb::get_setting_Emsfb('decoded');
	$s->smtp = (bool) $on;
	\Emsfb::get_efbFunction()->set_setting_Emsfb($s, '');
	delete_transient('emsfb_settings_transient');
	\Emsfb::get_setting_Emsfb('_clear_cache');
};
$sending_on = function () {
	\Emsfb::get_setting_Emsfb('_clear_cache');
	delete_transient('emsfb_settings_transient');
	return \Emsfb::is_email_sending_enabled_efb(\Emsfb::get_setting_Emsfb('decoded'));
};

$emsfb  = new ReflectionClass('Emsfb');
$method = $emsfb->getMethod('enable_email_sending_after_upgrade_efb');
$method->setAccessible(true);
$instance = $emsfb->newInstanceWithoutConstructor();

try {
	// 1. Off before the update -> on after it.
	delete_option('emsfb_sending_default_431');
	$set_sending(false);
	efb_ues_check('setup: sending is off', false === $sending_on());
	$method->invoke($instance);
	efb_ues_check('the 4.3.1 update turns sending on', true === $sending_on());
	efb_ues_check('the update records that it ran', '' !== (string) get_option('emsfb_sending_default_431', ''));

	// 2. The owner turns it off again -> a later run leaves it off.
	$set_sending(false);
	$method->invoke($instance);
	efb_ues_check('it runs once: an owner who turns sending off keeps it off', false === $sending_on());

	// 3. The filter opts out.
	delete_option('emsfb_sending_default_431');
	add_filter('emsfb_upgrade_enable_email_sending', '__return_false');
	$method->invoke($instance);
	remove_filter('emsfb_upgrade_enable_email_sending', '__return_false');
	efb_ues_check('the filter keeps sending off', false === $sending_on());

	// 4. Already on -> stays on, nothing else changes.
	delete_option('emsfb_sending_default_431');
	$set_sending(true);
	$method->invoke($instance);
	efb_ues_check('a site with sending on stays on', true === $sending_on());

	// 5. Wired into the upgrade path for versions below 4.3.1 only.
	$src = file_get_contents(dirname(__DIR__) . '/includes/class-Emsfb.php');
	efb_ues_check('run_upgrade_tasks_efb calls it for versions below 4.3.1',
		(bool) preg_match("/version_compare\\(\\(string\\) \\\$old_version, '4\\.3\\.1', '<'\\)\\)\\s*\\{\\s*\\\$this->enable_email_sending_after_upgrade_efb\\(\\);/", $src));
} finally {
	if (is_array($saved_row)) {
		$wpdb->update($table, ['setting' => $saved_row['setting'], 'email' => $saved_row['email']], ['id' => $saved_row['id']]);
	}
	if (null === $saved_json) { delete_option('emsfb_settings'); } else { update_option('emsfb_settings', $saved_json); }
	if (null === $saved_opt) { delete_option('emsfb_sending_default_431'); } else { update_option('emsfb_sending_default_431', $saved_opt, false); }
	delete_transient('emsfb_settings_transient');
	\Emsfb::get_setting_Emsfb('_clear_cache');
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
