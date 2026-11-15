<?php
/**
 * Plugin Name: EFB e2e SPLIT helpers (local test site only)
 * Description: For the SPLIT scenarios (tests/e2e-wp/scenario-split.js), through the plugin's and the add-ons' own
 *              test hooks only. Copied by ctl.php split-mu.
 *              - The upgrade scan never downloads anything here: Split::$installer records the request in the option
 *                efb_e2e_split_installs and answers "queued" (efb_e2e_split_mock = online) or "no connection".
 *              - Visitor Country (AdnGEO): while the option efb_e2e_geo_ip holds an address, the visitor's address is
 *                that one (efb_client_ip) and the provider is a fake one (Geo_Efb::$http): it answers the option
 *                efb_e2e_geo_answer, counts the requests (efb_e2e_geo_calls) and keeps the last address asked for,
 *                its key masked (efb_e2e_geo_last). No network.
 */

function efb_e2e_split_hooks() {
	if ( class_exists( '\\Emsfb\\Split' ) && null === \Emsfb\Split::$installer ) {
		\Emsfb\Split::$installer = static function ( $req ) {
			$log   = get_option( 'efb_e2e_split_installs', array() );
			$log   = is_array( $log ) ? $log : array();
			$log[] = array( 'source' => isset( $req['source'] ) ? $req['source'] : '', 'addons' => isset( $req['addons'] ) ? array_values( (array) $req['addons'] ) : array(), 'time' => time() );
			update_option( 'efb_e2e_split_installs', $log, false );
			return 'online' === get_option( 'efb_e2e_split_mock' ) ? array( 'queued' => true, 'mock' => true ) : array( 'queued' => false, 'reason' => 'offline', 'mock' => true );
		};
	}
	if ( get_option( 'efb_e2e_geo_ip' ) && class_exists( '\\Emsfb\\Geo_Efb' ) && null === \Emsfb\Geo_Efb::$http ) {
		\Emsfb\Geo_Efb::$http = static function ( $url, $args ) {
			update_option( 'efb_e2e_geo_calls', (int) get_option( 'efb_e2e_geo_calls', 0 ) + 1, false );
			update_option( 'efb_e2e_geo_last', preg_replace( '/(token|key)=[^&]+/', '$1=***', (string) $url ) . ' t=' . ( isset( $args['timeout'] ) ? $args['timeout'] : '' ), false );
			return array( 'headers' => array(), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => (string) get_option( 'efb_e2e_geo_answer', '{}' ), 'cookies' => array() );
		};
	}
}
add_action( 'init', 'efb_e2e_split_hooks', 0 );
add_action( 'wp_loaded', 'efb_e2e_split_hooks', 0 );
add_action( 'rest_api_init', 'efb_e2e_split_hooks', 0 );

add_filter(
	'efb_client_ip',
	static function ( $ip ) {
		$fake = get_option( 'efb_e2e_geo_ip' );
		return is_string( $fake ) && '' !== $fake ? $fake : $ip;
	},
	99
);
