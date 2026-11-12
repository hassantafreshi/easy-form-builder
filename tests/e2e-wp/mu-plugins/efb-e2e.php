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

/*
 * PAY00 (scenario 9): the test gateway "efbmock" and its fake provider are not part of the plugin;
 * they live in tests/payments-mock and are registered here, like a gateway add-on, only when the
 * suite switches them on (option efb_e2e_payments_mock). E-mails are recorded instead of sent
 * while efb_e2e_capture_mail is set, so the scenario can see when a notification goes out.
 */
if ( get_option( 'efb_e2e_payments_mock' ) ) {
	add_action(
		'plugins_loaded',
		static function () {
			$f = WP_PLUGIN_DIR . '/easy-form-builder/tests/payments-mock/mock-efb.php';
			if ( is_readable( $f ) ) {
				require_once $f;
			}
		},
		1
	);
}
add_filter(
	'pre_wp_mail',
	static function ( $pre, $atts ) {
		if ( ! get_option( 'efb_e2e_capture_mail' ) ) {
			return $pre;
		}
		$log   = get_option( 'efb_e2e_mails', array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array(
			'to'      => $atts['to'],
			'subject' => (string) $atts['subject'],
			'time'    => time(),
		);
		update_option( 'efb_e2e_mails', $log, false );
		return true;
	},
	10,
	2
);
