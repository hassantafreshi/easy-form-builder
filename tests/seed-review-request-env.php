<?php
/**
 * Set up and tear down the environment for the review invitation browser test.
 *
 * The modal only appears for a Free or Free Plus site that has been using the
 * plugin for a week and has not answered yet, so the browser test cannot
 * see it without arranging exactly that. This script arranges it, remembers
 * every option it changed - including the site locale, which the RTL pass
 * switches - and puts all of it back afterwards.
 *
 * Run: C:\xampp\php\php.exe tests/seed-review-request-env.php setup
 *      C:\xampp\php\php.exe tests/seed-review-request-env.php rtl
 *      C:\xampp\php\php.exe tests/seed-review-request-env.php ltr
 *      C:\xampp\php\php.exe tests/seed-review-request-env.php reset
 *      C:\xampp\php\php.exe tests/seed-review-request-env.php teardown
 */

require_once __DIR__ . '/wp-env.php';
$wp_load = efb_test_wp_load();
if ( ! is_readable( $wp_load ) ) {
	echo wp_json_encode( array( 'ok' => false, 'error' => 'wp-load not found' ) );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_load;

$mode   = isset( $argv[1] ) ? $argv[1] : '';
$marker = 'efb_review_browser_test_state';

$watched = array( 'emsfb_pro', 'emsfb_install_date', 'emsfb_review_state', 'WPLANG', 'emsfb_updated_at', 'emsfb_addons_reinstall_required', 'emsfb_addon_recovery_result' );
// QA4-009: a site past its first visit to the plugin's screens (the invitation never opens on that one).
$seen = array( 'first_seen' => time() - DAY_IN_SECONDS );

switch ( $mode ) {
	case 'setup':
		// Remember the real values once. A re-run must not overwrite the
		// snapshot with the values this script itself installed.
		if ( ! get_option( $marker ) ) {
			$snapshot = array();
			foreach ( $watched as $key ) {
				$snapshot[ $key ] = get_option( $key, null );
			}
			update_option( $marker, $snapshot, false );
		}

		update_option( 'emsfb_pro', 2, false );
		update_option( 'emsfb_install_date', time() - ( 30 * DAY_IN_SECONDS ), false );
		update_option( 'emsfb_review_state', $seen, false );
		// QA4-009: no update in progress (the invitation keeps quiet then).
		delete_option( 'emsfb_updated_at' );
		delete_option( 'emsfb_addons_reinstall_required' );
		delete_option( 'emsfb_addon_recovery_result' );

		echo wp_json_encode( array( 'ok' => true, 'mode' => 'setup' ) );
		break;

	case 'reset':
		// Put the conversation back to "never answered" between passes, without
		// touching the snapshot.
		update_option( 'emsfb_review_state', $seen, false );
		echo wp_json_encode( array( 'ok' => true, 'mode' => 'reset' ) );
		break;

	case 'rtl':
		update_option( 'WPLANG', 'fa_IR', false );
		update_option( 'emsfb_review_state', $seen, false );
		echo wp_json_encode( array( 'ok' => true, 'mode' => 'rtl' ) );
		break;

	case 'ltr':
		update_option( 'WPLANG', '', false );
		update_option( 'emsfb_review_state', $seen, false );
		echo wp_json_encode( array( 'ok' => true, 'mode' => 'ltr' ) );
		break;

	case 'teardown':
		$snapshot = get_option( $marker );

		if ( is_array( $snapshot ) ) {
			foreach ( $watched as $key ) {
				$value = array_key_exists( $key, $snapshot ) ? $snapshot[ $key ] : null;

				if ( null === $value ) {
					delete_option( $key );
				} else {
					update_option( $key, $value, false );
				}
			}
		}

		delete_option( $marker );

		echo wp_json_encode( array( 'ok' => true, 'mode' => 'teardown', 'restored' => is_array( $snapshot ) ) );
		break;

	default:
		echo wp_json_encode( array( 'ok' => false, 'error' => 'unknown mode' ) );
		exit( 1 );
}

echo "\n";
