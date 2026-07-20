<?php
/**
 * WordPress integration test for the public add-on settings payload.
 * Run: php tests/test-addon-settings-wordpress.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

require_once $wp_load;

get_setting_Emsfb( '_clear_cache' );
wp_cache_delete( 'settings:decoded', 'emsfb' );
wp_cache_delete( 'settings:pub', 'emsfb' );
wp_cache_delete( 'settings:raw', 'emsfb' );

$decoded = get_setting_Emsfb( 'decoded' );

get_setting_Emsfb( '_clear_cache' );
wp_cache_delete( 'settings:pub', 'emsfb' );
$public = get_setting_Emsfb( 'pub' );

$helper = get_efbFunction();
$admin_addons = $helper->fun_get_addons_list_efb( (object) [ 'AdnSMF' => 1 ] );
$public_addons = isset( $public[1]['addons'] ) && is_array( $public[1]['addons'] )
	? $public[1]['addons']
	: [];

$checks = [
	'decoded settings contain active AdnSMF' => isset( $decoded->AdnSMF ) && (int) $decoded->AdnSMF >= 1,
	'public payload contains AdnSMF' => isset( $public_addons['AdnSMF'] ),
	'public helper recognizes AdnSMF' => emsfb_is_addon_active_efb( $public[1] ?? [], 'AdnSMF' ),
	'public helper recognizes numeric state' => emsfb_is_addon_active_efb( [ 'AdnSMF' => 1 ], 'AdnSMF' ),
	'public helper respects inactive metadata' => ! emsfb_is_addon_active_efb(
		[ 'addons' => [ 'AdnSMF' => [ 'active' => false, 'version' => 1 ] ] ],
		'AdnSMF'
	),
	'admin helper works without AdnSPF' => isset( $admin_addons['AdnSMF'] ) && 1 === $admin_addons['AdnSMF'],
	'settings update hook clears all settings caches' => false !== has_action(
		'update_option_emsfb_settings',
		[ $helper, 'invalidate_settings_cache' ]
	),
];

$failed = 0;
foreach ( $checks as $name => $passed ) {
	echo ( $passed ? '[PASS] ' : '[FAIL] ' ) . $name . "\n";
	if ( ! $passed ) {
		$failed++;
	}
}

echo 'pub.addons=' . wp_json_encode( $public_addons ) . "\n";
exit( $failed > 0 ? 1 : 0 );
