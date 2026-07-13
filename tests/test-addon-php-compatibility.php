<?php
/**
 * Unit-style coverage for add-on PHP capability gating.
 *
 * Run: php tests/test-addon-php-compatibility.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

$efb_test_disabled_functions = array();

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		global $efb_test_disabled_functions;
		if ( 'emsfb_disabled_php_functions' === $hook ) {
			return $efb_test_disabled_functions;
		}
		return $value;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-Emsfb-addon-compatibility.php';

$passed = 0;
$failed = 0;

function efb_php_compat_check( $label, $result ) {
	global $passed, $failed;
	if ( $result ) {
		$passed++;
		echo "[PASS] {$label}\n";
		return;
	}

	$failed++;
	echo "[FAIL] {$label}\n";
}

$efb_test_disabled_functions = array( 'curl_exec', 'curl_init', 'openssl_sign', 'hash_hmac' );
emsfb_reset_php_compatibility_cache_efb();

efb_php_compat_check(
	'Stripe is blocked when cURL is restricted',
	! emsfb_is_addon_compatible_efb( 'AdnSPF' )
	&& in_array( 'curl_exec', emsfb_get_missing_addon_functions_efb( 'AdnSPF' ), true )
);
efb_php_compat_check(
	'Persia Payment is blocked when cURL is restricted',
	! emsfb_is_addon_compatible_efb( 'AdnPPF' )
	&& in_array( 'curl_init', emsfb_get_missing_addon_functions_efb( 'AdnPPF' ), true )
);
efb_php_compat_check(
	'Google Sheets is blocked when OpenSSL signing is restricted',
	! emsfb_is_addon_compatible_efb( 'AdnGoS' )
	&& in_array( 'openssl_sign', emsfb_get_missing_addon_functions_efb( 'AdnGoS' ), true )
);
efb_php_compat_check(
	'Human Shield is blocked when a required hashing function is restricted',
	! emsfb_is_addon_compatible_efb( 'AdnHSH' )
	&& in_array( 'hash_hmac', emsfb_get_missing_addon_functions_efb( 'AdnHSH' ), true )
);

$issues = emsfb_get_incompatible_addons_efb( (object) array( 'AdnSPF' => 1, 'AdnPPF' => 0 ) );
efb_php_compat_check(
	'Compatibility report preserves the add-on enabled state',
	isset( $issues['AdnSPF'], $issues['AdnPPF'] )
	&& true === $issues['AdnSPF']['enabled']
	&& false === $issues['AdnPPF']['enabled']
	&& in_array( 'curl_exec', $issues['AdnSPF']['disabled_functions'], true )
);

if ( ! function_exists( 'ini_get' ) ) {
	efb_php_compat_check(
		'A disabled ini_get is reported as a php.ini restriction',
		emsfb_is_php_function_disabled_efb( 'ini_get' )
	);
}

$efb_test_disabled_functions = array();
emsfb_reset_php_compatibility_cache_efb();
$token = emsfb_generate_token_efb( 19 );
efb_php_compat_check( 'Safe token fallback returns the requested length', 19 === strlen( $token ) );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
