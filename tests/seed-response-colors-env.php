<?php
/**
 * Set up and tear down the environment for the Colors & Fonts browser test.
 *
 * The dialog saves through the settings endpoint, so proving that it saves
 * means letting it write the real settings row. This script takes the row and
 * the site locale aside first and puts both back afterwards, so a test run
 * leaves the dev site exactly as it found it - the palette an admin was in the
 * middle of choosing is not something a test gets to overwrite.
 *
 * Run: C:\xampp\php\php.exe tests/seed-response-colors-env.php setup
 *      C:\xampp\php\php.exe tests/seed-response-colors-env.php read
 *      C:\xampp\php\php.exe tests/seed-response-colors-env.php rtl
 *      C:\xampp\php\php.exe tests/seed-response-colors-env.php ltr
 *      C:\xampp\php\php.exe tests/seed-response-colors-env.php teardown
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo json_encode( array( 'ok' => false, 'error' => 'wp-load not found' ) );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_load;

global $wpdb;

$mode   = isset( $argv[1] ) ? $argv[1] : '';
$marker = 'efb_colors_browser_test_state';
$table  = $wpdb->prefix . 'emsfb_setting';

/** The one settings row the panel reads and writes. */
function efb_colors_test_row_efb() {
	global $wpdb;
	$table = $wpdb->prefix . 'emsfb_setting';
	/* The newest row, which is the one get_setting_Emsfb() reads and
	   set_setting_Emsfb() writes - this install has three of them. */
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name only.
	return $wpdb->get_row( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 1" );
}

function efb_colors_test_settings_efb() {
	$row = efb_colors_test_row_efb();
	if ( ! $row || empty( $row->setting ) ) {
		return array();
	}
	$decoded = json_decode( $row->setting, true );
	return is_array( $decoded ) ? $decoded : array();
}

switch ( $mode ) {
	case 'setup':
		// Only snapshot once: a re-run must not record the values a previous
		// run of the test itself left behind.
		if ( ! get_option( $marker ) ) {
			$row = efb_colors_test_row_efb();
			update_option(
				$marker,
				array(
					'setting_id' => $row ? (int) $row->id : 0,
					'setting'    => $row ? (string) $row->setting : '',
					'WPLANG'     => get_option( 'WPLANG', null ),
				),
				false
			);
		}
		if ( function_exists( 'get_setting_Emsfb' ) ) {
			get_setting_Emsfb( '_clear_cache' );
		}
		echo json_encode( array( 'ok' => true, 'settings' => efb_colors_test_settings_efb() ) );
		break;

	case 'baseline':
		/*
		 * Start the run from the plugin defaults.
		 *
		 * The assertions below are about what the dialog does to a palette, not
		 * about which palette this install happens to be wearing - and an admin
		 * who has chosen their own colours must not make the suite red. The
		 * snapshot is already taken by 'setup', so their palette comes back at
		 * teardown untouched.
		 */
		$row = efb_colors_test_row_efb();
		if ( $row ) {
			$settings = json_decode( $row->setting, true );
			$settings = is_array( $settings ) ? $settings : array();
			foreach ( array_keys( $settings ) as $key ) {
				if ( 0 === strpos( $key, 'resp' ) ) {
					unset( $settings[ $key ] );
				}
			}
			$wpdb->update(
				$table,
				array( 'setting' => wp_json_encode( $settings, JSON_UNESCAPED_UNICODE ) ),
				array( 'id' => (int) $row->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
		if ( function_exists( 'get_setting_Emsfb' ) ) {
			get_setting_Emsfb( '_clear_cache' );
		}
		delete_transient( 'emsfb_settings_transient' );
		delete_option( 'emsfb_settings' );
		echo json_encode( array( 'ok' => true, 'settings' => efb_colors_test_settings_efb() ) );
		break;

	case 'read':
		if ( function_exists( 'get_setting_Emsfb' ) ) {
			get_setting_Emsfb( '_clear_cache' );
		}
		echo json_encode( array( 'ok' => true, 'settings' => efb_colors_test_settings_efb() ) );
		break;

	case 'rtl':
	case 'ltr':
		update_option( 'WPLANG', 'rtl' === $mode ? 'fa_IR' : '' );
		echo json_encode( array( 'ok' => true, 'locale' => (string) get_option( 'WPLANG', '' ) ) );
		break;

	case 'teardown':
		$snapshot = get_option( $marker );
		if ( is_array( $snapshot ) ) {
			if ( ! empty( $snapshot['setting_id'] ) ) {
				$wpdb->update(
					$table,
					array( 'setting' => $snapshot['setting'] ),
					array( 'id' => (int) $snapshot['setting_id'] ),
					array( '%s' ),
					array( '%d' )
				);
			}
			if ( null === $snapshot['WPLANG'] ) {
				delete_option( 'WPLANG' );
			} else {
				update_option( 'WPLANG', $snapshot['WPLANG'] );
			}
			delete_option( $marker );
		}
		if ( function_exists( 'get_setting_Emsfb' ) ) {
			get_setting_Emsfb( '_clear_cache' );
		}
		echo json_encode( array( 'ok' => true, 'restored' => is_array( $snapshot ) ) );
		break;

	default:
		echo json_encode( array( 'ok' => false, 'error' => 'unknown mode' ) );
		exit( 1 );
}
