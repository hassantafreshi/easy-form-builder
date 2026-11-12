<?php
/**
 * Plugin Name: EFB e2e: fake Omise (local test site only)
 * Description: Scenario "omise" of tests/e2e-wp/run.js copies this file into wp-content/mu-plugins.
 *              While the option efb_e2e_omise_fake is set, every request to api.omise.co is answered
 *              by tests/omise-fake-provider.php (pre_http_request, answers built from the documented
 *              responses in tests/fixtures/payments/omise). Nothing leaves the machine and no real
 *              key is used.
 */

if ( get_option( 'efb_e2e_omise_fake' ) ) {
	$efb_e2e_omise_fake = WP_PLUGIN_DIR . '/easy-form-builder/tests/omise-fake-provider.php';
	if ( is_readable( $efb_e2e_omise_fake ) ) {
		require_once $efb_e2e_omise_fake;
		Efb_Test_Omise_Fake::register();
	}
	unset( $efb_e2e_omise_fake );
}
