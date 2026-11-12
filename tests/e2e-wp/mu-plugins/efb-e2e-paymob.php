<?php
/**
 * Plugin Name: EFB e2e: fake Paymob (local test site only)
 * Description: Scenario "paymob" of tests/e2e-wp/run.js copies this file into wp-content/mu-plugins.
 *              While the option efb_e2e_pmb_fake is set, every request to the five regional Paymob
 *              hosts (accept / ksa / uae / oman / pakistan .paymob.com) is answered by
 *              tests/paymob-fake-provider.php (pre_http_request, shapes of Paymob's documented
 *              responses in tests/fixtures/payments/paymob) and the fake Unified Checkout page is
 *              served at /wp-json/efb-e2e/v1/paymob-checkout. Nothing leaves the machine and no real
 *              key is used.
 */

if ( get_option( 'efb_e2e_pmb_fake' ) ) {
	$efb_e2e_pmb_fake = WP_PLUGIN_DIR . '/easy-form-builder/tests/paymob-fake-provider.php';
	if ( is_readable( $efb_e2e_pmb_fake ) ) {
		require_once $efb_e2e_pmb_fake;
		Efb_Test_Paymob_Fake::register();
	}
	unset( $efb_e2e_pmb_fake );
}
