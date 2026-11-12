<?php
/**
 * Plugin Name: EFB e2e: fake Xendit (local test site only)
 * Description: Scenario "xendit" of tests/e2e-wp/run.js copies this file into wp-content/mu-plugins
 *              (ctl-xendit.php install). While the option efb_e2e_xendit_fake is set, every request to
 *              api.xendit.co is answered by tests/xendit-fake-provider.php (pre_http_request, shapes of
 *              Xendit's documented responses in tests/fixtures/payments/xendit) and the fake hosted page
 *              is served at /wp-json/efb-e2e/v1/xendit-checkout. Nothing leaves the machine and no real
 *              key is used.
 */

if ( get_option( 'efb_e2e_xendit_fake' ) ) {
	$efb_e2e_xnd_fake = WP_PLUGIN_DIR . '/easy-form-builder/tests/xendit-fake-provider.php';
	if ( is_readable( $efb_e2e_xnd_fake ) ) {
		require_once $efb_e2e_xnd_fake;
		Efb_Test_Xendit_Fake::register();
	}
	unset( $efb_e2e_xnd_fake );
}
