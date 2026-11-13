<?php
/**
 * Plugin Name: EFB tutorial environment tweaks
 * Description: Dev/CI-only helper for the automated tutorial pipeline. NOT part of Easy Form Builder.
 *              Keeps the recording site deterministic: no update nags, no outgoing mail, and a strict
 *              outbound-HTTP policy (whitestudio.team is GET/HEAD only, never POST: no licence calls).
 */
defined( 'ABSPATH' ) || exit;

// 1. Outbound HTTP policy -------------------------------------------------------------------------
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$path   = (string) wp_parse_url( $url, PHP_URL_PATH );
	$method = strtoupper( isset( $args['method'] ) ? $args['method'] : 'GET' );

	// whitestudio.team hosts the licence server and the add-on archives.
	if ( $host === 'whitestudio.team' || substr( $host, -strlen( '.whitestudio.team' ) ) === '.whitestudio.team' ) {
		$allow_get = defined( 'EFB_ENV_ALLOW_WHITESTUDIO_GET' ) && EFB_ENV_ALLOW_WHITESTUDIO_GET;
		if ( ! $allow_get || ! in_array( $method, array( 'GET', 'HEAD' ), true ) || false !== strpos( $path, '/pro/key' ) ) {
			return new WP_Error( 'efb_env_blocked', 'Blocked by efb-tutorial-env mu-plugin: ' . $method . ' ' . $host . $path );
		}
		return $pre;
	}

	// Update checks / dashboard feeds only slow the admin down and make screenshots non-deterministic.
	if ( $host === 'api.wordpress.org' && preg_match( '#/(core/version-check|plugins/update-check|themes/update-check|core/browse-happy|core/serve-happy|events)/#', $path ) ) {
		return new WP_Error( 'efb_env_blocked', 'Blocked update check: ' . $path );
	}
	if ( $host === 'wordpress.org' && false !== strpos( $path, '/news/' ) ) {
		return new WP_Error( 'efb_env_blocked', 'Blocked news feed' );
	}
	return $pre;
}, 1, 3 );

// 2. Never send real e-mail from the recording site (form submissions would otherwise try to).
add_filter( 'pre_wp_mail', '__return_true' );

// 3. Quiet admin: no update nags, no admin-email verification prompt, no welcome panel.
add_filter( 'admin_email_check_interval', '__return_false' );
add_action( 'admin_init', function () {
	remove_action( 'welcome_panel', 'wp_welcome_panel' );
	remove_action( 'admin_notices', 'update_nag', 3 );
	remove_action( 'network_admin_notices', 'update_nag', 3 );
} );
add_filter( 'auto_update_core', '__return_false' );
add_filter( 'auto_update_plugin', '__return_false' );
add_filter( 'auto_update_theme', '__return_false' );
add_filter( 'auto_update_translation', '__return_false' );

// 4. Locale switching for front-end recordings: ?efb_locale=de_DE (wp-admin uses the per-user locale instead).
add_filter( 'locale', function ( $locale ) {
	if ( ! is_admin() && isset( $_GET['efb_locale'] ) && preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/', (string) $_GET['efb_locale'] ) ) {
		return (string) $_GET['efb_locale'];
	}
	return $locale;
}, 5 );
