<?php
/**
 * Set up and tear down the environment for the deactivation feedback browser test.
 *
 * The browser test needs the White Studio feedback service reachable on this
 * install so the coupon can actually be issued. This script switches it on,
 * remembers everything it changed, and puts all of it back afterwards - the
 * plugin's active state, both client options, and every row the run created.
 *
 * Run: C:\xampp\php\php.exe tests/seed-deactivation-feedback-env.php setup
 *      C:\xampp\php\php.exe tests/seed-deactivation-feedback-env.php teardown
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo wp_json_encode( array( 'ok' => false, 'error' => 'wp-load not found' ) );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_load;
require_once ABSPATH . 'wp-admin/includes/plugin.php';

global $wpdb;

$mode     = isset( $argv[1] ) ? $argv[1] : '';
$service  = 'ws-efb-feedback/ws-efb-feedback.php';
$marker   = 'efb_feedback_browser_test_state';
$identity = \Emsfb\Deactivation_Feedback::OPTION_IDENTITY;
$state    = \Emsfb\Deactivation_Feedback::OPTION_STATE;

/*
 * The browser runs as a normal visitor, so the client has to be pointed at the
 * local service the same way a real install points at whitestudio.team. A
 * throwaway mu-plugin defines the override constant early enough to matter and
 * is deleted again in the teardown.
 */
$mu_dir  = WPMU_PLUGIN_DIR;
$mu_file = $mu_dir . '/efb-feedback-test-endpoint.php';

/**
 * Table name helper that works whether or not the service plugin is loaded.
 *
 * @param string $name Short table name.
 * @return string
 */
function efb_seed_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'ws_efb_' . $name;
}

if ( 'setup' === $mode ) {
	$was_active = is_plugin_active( $service );

	if ( ! $was_active ) {
		$activated = activate_plugin( $service );
		if ( is_wp_error( $activated ) ) {
			echo wp_json_encode( array( 'ok' => false, 'error' => $activated->get_error_message() ) );
			exit( 1 );
		}
	}

	if ( file_exists( $mu_file ) ) {
		echo wp_json_encode( array( 'ok' => false, 'error' => 'a file already exists at ' . $mu_file ) );
		exit( 1 );
	}

	if ( ! is_dir( $mu_dir ) ) {
		wp_mkdir_p( $mu_dir );
	}

	$mu_source = "<?php\n"
		. "/**\n"
		. " * Plugin Name: EFB feedback test endpoint\n"
		. " * Description: Temporary. Points the deactivation feedback client at this site while tests/test-deactivation-feedback-browser.js runs.\n"
		. " */\n"
		. "if ( ! defined( 'EMSFB_FEEDBACK_SERVER_URL' ) ) {\n"
		. "\tdefine( 'EMSFB_FEEDBACK_SERVER_URL', '" . esc_url_raw( untrailingslashit( home_url() ) ) . "' );\n"
		. "}\n";

	if ( false === file_put_contents( $mu_file, $mu_source ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		echo wp_json_encode( array( 'ok' => false, 'error' => 'could not write ' . $mu_file ) );
		exit( 1 );
	}

	$reports = efb_seed_table( 'reports' );
	$sites   = efb_seed_table( 'sites' );
	$coupons = efb_seed_table( 'coupons' );
	$events  = efb_seed_table( 'events' );

	update_option(
		$marker,
		array(
			'ws_active'   => $was_active,
			'identity'    => get_option( $identity ),
			'state'       => get_option( $state ),
			'base_report' => (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$reports}" ),
			'base_site'   => (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$sites}" ),
			'base_coupon' => (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$coupons}" ),
			'base_event'  => (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$events}" ),
		),
		false
	);

	echo wp_json_encode(
		array(
			'ok'             => true,
			'service_active' => is_plugin_active( $service ),
			'was_active'     => $was_active,
			'endpoint'       => untrailingslashit( home_url() ),
		)
	);
	exit( 0 );
}

if ( 'teardown' === $mode ) {
	$saved = get_option( $marker );
	if ( ! is_array( $saved ) ) {
		echo wp_json_encode( array( 'ok' => false, 'error' => 'no saved state' ) );
		exit( 1 );
	}

	$reports = efb_seed_table( 'reports' );
	$sites   = efb_seed_table( 'sites' );
	$coupons = efb_seed_table( 'coupons' );
	$events  = efb_seed_table( 'events' );
	$nonces  = efb_seed_table( 'nonces' );
	$limits  = efb_seed_table( 'limits' );

	// The site rows created during the run are the key to everything else.
	$new_sites = $wpdb->get_col( $wpdb->prepare( "SELECT site_id FROM {$sites} WHERE id > %d", (int) $saved['base_site'] ) );

	$wpdb->query( $wpdb->prepare( "DELETE FROM {$reports} WHERE id > %d", (int) $saved['base_report'] ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$coupons} WHERE id > %d", (int) $saved['base_coupon'] ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$events} WHERE id > %d", (int) $saved['base_event'] ) );

	foreach ( (array) $new_sites as $site_id ) {
		$wpdb->delete( $nonces, array( 'site_id' => $site_id ), array( '%s' ) );
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$sites} WHERE id > %d", (int) $saved['base_site'] ) );

	// Rate-limit buckets are keyed by an opaque hash; the run's own buckets
	// expire on their own, so only the obviously stale ones are swept here.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$limits} WHERE window_start < %d", time() - DAY_IN_SECONDS ) );

	if ( false === $saved['identity'] ) {
		delete_option( $identity );
	} else {
		update_option( $identity, $saved['identity'], false );
	}

	if ( false === $saved['state'] ) {
		delete_option( $state );
	} else {
		update_option( $state, $saved['state'], false );
	}

	delete_transient( 'emsfb_feedback_register_backoff' );

	if ( empty( $saved['ws_active'] ) ) {
		deactivate_plugins( $service, true );
	}

	delete_option( $marker );

	if ( file_exists( $mu_file ) ) {
		unlink( $mu_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	echo wp_json_encode(
		array(
			'ok'             => true,
			'service_active' => is_plugin_active( $service ),
			'reports_left'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reports} WHERE id > %d", (int) $saved['base_report'] ) ),
			'mu_removed'     => ! file_exists( $mu_file ),
		)
	);
	exit( 0 );
}

echo wp_json_encode( array( 'ok' => false, 'error' => 'usage: setup|teardown' ) );
exit( 1 );
