<?php
/**
 * Plugin Name: EFB e2e: fake Mollie (local test site only)
 * Description: Scenario "mollie" of tests/e2e-wp/run.js copies this file into wp-content/mu-plugins.
 *              While the option efb_e2e_mollie_fake is set, every request to api.mollie.com is
 *              answered by tests/mollie-fake-provider.php (pre_http_request, answers built from the
 *              documented responses in tests/fixtures/payments/mollie) and the fake's hosted
 *              checkout page is served at /wp-json/efb-e2e/v1/mollie-checkout. Nothing leaves the
 *              machine and no real key is used.
 */

if ( get_option( 'efb_e2e_mollie_fake' ) ) {
	$efb_e2e_mollie_fake = WP_PLUGIN_DIR . '/easy-form-builder/tests/mollie-fake-provider.php';
	if ( is_readable( $efb_e2e_mollie_fake ) ) {
		require_once $efb_e2e_mollie_fake;
		Efb_Test_Mollie_Fake::register();
		add_action( 'rest_api_init', array( 'Efb_Test_Mollie_Fake', 'routes' ) );
	}
	unset( $efb_e2e_mollie_fake );
}
