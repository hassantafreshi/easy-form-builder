<?php
/**
 * Integration/regression test for the Telegram cost guard.
 *
 * It does not contact Telegram. The test wires the real Human Shield filter,
 * marks the current request as low-score, and proves that the Telegram action
 * dispatch is structurally behind that filter.
 * Run: php tests/test-human-shield-telegram-gate.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

require_once $wp_load;
require_once dirname( __DIR__ ) . '/vendor/human-shield/human-shield-efb.php';

$passed = 0;
$failed = 0;

function efb_hs_telegram_check( $name, $actual, $expected = true ) {
	global $passed, $failed;
	if ( $actual === $expected ) {
		echo "[PASS] {$name}\n";
		$passed++;
		return;
	}

	echo "[FAIL] {$name}\n";
	$failed++;
}

$shield   = \Emsfb\Emsfb_Human_Shield::instance();
$original = get_option( \Emsfb\Emsfb_Human_Shield::OPTION_SETTINGS, false );

try {
	$settings = $shield->defaults();
	$settings['enabled'] = 1;
	$settings['mode']    = 'soft_block';
	$shield->update_settings( $settings );
	$shield->set_request_assessment( 24, true, array( 'test_low_score' ) );

	new \Emsfb\Emsfb_Human_Shield_Notification_Gate( $shield );
	$allowed = apply_filters(
		'efb_shield_allow_side_effect',
		true,
		array(
			'channel'       => 'telegram',
			'form_id'       => 134,
			'tracking_code' => 'human-shield-test',
		)
	);
	efb_hs_telegram_check( 'Low-score Telegram notification is vetoed by the real filter', $allowed, false );

	$public_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-Emsfb-public.php' );
	$telegram_guard_pattern = "/apply_filters\\(\\s*'efb_shield_allow_side_effect'.*?'channel'\\s*=>\\s*'telegram'.*?\\)\\s*\\)\\s*\\{\\s*do_action\\(\\s*'efb_3rd_party_telegram_notify'/s";
	efb_hs_telegram_check( 'Telegram dispatch is behind the shared side-effect gate', 1 === preg_match( $telegram_guard_pattern, $public_source ) );
} finally {
	if ( false === $original ) {
		delete_option( \Emsfb\Emsfb_Human_Shield::OPTION_SETTINGS );
	} else {
		update_option( \Emsfb\Emsfb_Human_Shield::OPTION_SETTINGS, $original, false );
	}
}

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
