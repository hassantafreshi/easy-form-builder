<?php
/**
 * Plugin Name: EFB e2e: fake Tap Payments (local test site only)
 * Description: Scenario "tappay" of tests/e2e-wp/run.js copies this file into wp-content/mu-plugins.
 *              While the option efb_e2e_tap_fake is set, every request to api.tap.company is
 *              answered by tests/tappay-fake-provider.php (pre_http_request, shapes of Tap's
 *              documented responses in tests/fixtures/payments/tap) and the fake hosted page is
 *              served at /wp-json/efb-e2e/v1/tap-checkout. Nothing leaves the machine and no real
 *              key is used.
 */

if ( get_option( 'efb_e2e_tap_fake' ) ) {
	$efb_e2e_tap_fake = WP_PLUGIN_DIR . '/easy-form-builder/tests/tappay-fake-provider.php';
	if ( is_readable( $efb_e2e_tap_fake ) ) {
		require_once $efb_e2e_tap_fake;
		Efb_Test_Tap_Fake::register();
	}
	unset( $efb_e2e_tap_fake );
}
