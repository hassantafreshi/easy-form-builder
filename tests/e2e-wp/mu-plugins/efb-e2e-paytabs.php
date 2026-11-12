<?php
/**
 * Plugin Name: EFB e2e: fake PayTabs (local test site only)
 * Description: Scenario "paytabs" of tests/e2e-wp/run.js copies this file into wp-content/mu-plugins.
 *              While the option efb_e2e_ptb_fake is set, every request to the seven PayTabs regional
 *              hosts is answered by tests/paytabs-fake-provider.php (pre_http_request, shapes of
 *              PayTabs' documented responses in tests/fixtures/payments/paytabs) and the fake
 *              payment page is served at /wp-json/efb-e2e/v1/paytabs-page. Nothing leaves the
 *              machine and no real key is used.
 */

if ( get_option( 'efb_e2e_ptb_fake' ) ) {
	$efb_e2e_ptb_fake = WP_PLUGIN_DIR . '/easy-form-builder/tests/paytabs-fake-provider.php';
	if ( is_readable( $efb_e2e_ptb_fake ) ) {
		require_once $efb_e2e_ptb_fake;
		Efb_Test_Paytabs_Fake::register();
	}
	unset( $efb_e2e_ptb_fake );
}
