<?php
/**
 * Plugin Name: EFB e2e helpers (local test site only)
 * Description: Lets tests/e2e-wp restrict plan changes through the documented efb_can_change_plan
 *              filter, the way a site owner would. Copied into wp-content/mu-plugins by the README setup.
 */

function efb_e2e_mu_loaded() {
	return true;
}

add_filter(
	'efb_can_change_plan',
	static function ( $ok ) {
		if ( get_option( 'efb_e2e_restrict_plan' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return $ok;
	}
);
