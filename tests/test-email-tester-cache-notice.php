<?php
/**
 * Regression test for the caching plugin list sent to the email tester service.
 *
 * The administrator report only carries the cache exclusion notice when the
 * calling site reports a caching plugin, so this file pins down the two halves
 * of that contract on the client side:
 *
 *   A. active_cache_plugins_names_efb() - the list that goes on the wire. It
 *      refreshes the stored list first, because that list is otherwise only
 *      rebuilt on plugin install/activation/update, and an empty site must
 *      send no field at all rather than an empty one.
 *   B. request_email_tester_start_efb() - the field travels in /start, and a
 *      service that predates it rejects the whole request. Losing the notice
 *      is acceptable, losing the test is not, so the request is repeated once
 *      without the field and only when the refusal is the guard's unknown
 *      argument response.
 *
 * Every option and filter touched is restored, including on failure.
 *
 * Run: php tests/test-email-tester-cache-notice.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

require_once $wp_load;

// The admin class is only loaded into admin requests, so a CLI bootstrap that
// reaches the test code has to pull it in itself.
if ( ! class_exists( 'Emsfb\\Admin' ) ) {
	require_once dirname( __DIR__ ) . '/includes/admin/class-Emsfb-admin.php';
}

if ( ! class_exists( 'Emsfb\\Admin' ) ) {
	echo "[SKIP] The Easy Form Builder admin class could not be loaded.\n";
	exit( 0 );
}

$passed = 0;
$failed = 0;

function efb_cache_notice_check( $label, $condition, $extra = '' ) {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "[PASS] {$label}\n";
	} else {
		$failed++;
		echo "[FAIL] {$label}" . ( '' !== $extra ? " — {$extra}" : '' ) . "\n";
	}
}

// --- restore the world, whatever happens -------------------------------

$original_option = get_option( 'emsfb_cache_plugins', 0 );

function efb_cache_notice_restore_option( $value ) {
	remove_all_filters( 'emsfb_cache_plugins_slug' );
	update_option( 'emsfb_cache_plugins', $value );
}

register_shutdown_function( 'efb_cache_notice_restore_option', $original_option );

/**
 * Force the refreshed list to a known state.
 *
 * The stored list is rebuilt from the plugins actually installed, so the only
 * deterministic way to control it is through the filter the rebuild reads.
 *
 * @param callable|null $slug_filter Filter that returns the slugs to look for.
 * @return void
 */
function efb_cache_notice_prime_list( $slug_filter ) {
	remove_all_filters( 'emsfb_cache_plugins_slug' );
	if ( null !== $slug_filter ) {
		add_filter( 'emsfb_cache_plugins_slug', $slug_filter, 1000 );
	}
	if ( has_action( 'emsfb_update_cache_plugins_list' ) ) {
		do_action( 'emsfb_update_cache_plugins_list' );
	}
}

$reflection  = new ReflectionClass( 'Emsfb\\Admin' );
$admin       = $reflection->newInstanceWithoutConstructor();
$names_method = $reflection->getMethod( 'active_cache_plugins_names_efb' );
$names_method->setAccessible( true );
$start_method = $reflection->getMethod( 'request_email_tester_start_efb' );
$start_method->setAccessible( true );

$efb_function = new class {
	public function is_efb_pro( $x = 0 ) {
		return 0;
	}
};

// --- A. the list itself -------------------------------------------------

$active_plugins = (array) get_option( 'active_plugins', array() );
$probe_file     = $active_plugins ? reset( $active_plugins ) : '';
$probe_slug     = $probe_file ? explode( '/', $probe_file )[0] : '';

if ( '' === $probe_slug ) {
	echo "[SKIP] No active plugin is available to probe the cache list with.\n";
	efb_cache_notice_restore_option( $original_option );
	exit( 0 );
}

$force_empty = function () {
	return array();
};
$probe_slug_filter = function ( $slugs ) use ( $probe_slug ) {
	$slugs[] = $probe_slug;
	return array_values( array_unique( $slugs ) );
};

efb_cache_notice_prime_list( $probe_slug_filter );
$names_with = $names_method->invoke( $admin );

efb_cache_notice_check( 'cache list is not empty when a cache plugin is active', '' !== $names_with, $names_with );

$stored = json_decode( (string) get_option( 'emsfb_cache_plugins', 0 ), true );
efb_cache_notice_check(
	'cache list reports the active plugin name',
	is_array( $stored ) && ! empty( $stored ) && isset( $stored[0]['name'] ) && false !== strpos( $names_with, $stored[0]['name'] ),
	$names_with
);
efb_cache_notice_check( 'cache list stays within the column it is stored in', strlen( $names_with ) <= 500, (string) strlen( $names_with ) );

efb_cache_notice_prime_list( $force_empty );
$names_without = $names_method->invoke( $admin );
efb_cache_notice_check( 'cache list is empty without a cache plugin', '' === $names_without, var_export( $names_without, true ) );

// --- B. what goes over the wire ----------------------------------------

$http_calls = array();
$reject_first = false;
$reject_code  = 'api_guard_unknown_body_arg';

add_filter(
	'pre_http_request',
	function ( $preempt, $args, $url ) use ( &$http_calls, &$reject_first, &$reject_code ) {
		$body = isset( $args['body'] ) ? json_decode( (string) $args['body'], true ) : null;
		$http_calls[] = array(
			'url'  => $url,
			'body' => is_array( $body ) ? $body : array(),
		);

		$reject = $reject_first && 1 === count( $http_calls );
		$response = $reject
			? array(
				'success' => false,
				'code'    => $reject_code,
				'message' => 'Rejected.',
			)
			: array(
				'success'   => true,
				'message'   => 'ok',
				'test_hash' => str_repeat( 'a', 64 ),
			);

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $response ),
			'response' => array(
				'code'    => $reject ? 400 : 200,
				'message' => $reject ? 'Bad Request' : 'OK',
			),
			'cookies'  => array(),
		);
	},
	10,
	3
);

// B1. a site with a cache plugin sends the field.
efb_cache_notice_prime_list( $probe_slug_filter );
$http_calls = array();
$result     = $start_method->invoke( $admin, 'no-reply@example.org', 'admin@example.org', $efb_function );

efb_cache_notice_check( 'start succeeds with the field', ! empty( $result['success'] ), wp_json_encode( $result ) );
efb_cache_notice_check( 'one request was made', 1 === count( $http_calls ), (string) count( $http_calls ) );
$sent = isset( $http_calls[0]['body']['caching_plugins'] ) ? $http_calls[0]['body']['caching_plugins'] : null;
efb_cache_notice_check( 'start sends caching_plugins', is_string( $sent ) && '' !== $sent, var_export( $sent, true ) );
efb_cache_notice_check( 'start sends the refreshed list', is_string( $sent ) && $sent === $names_with, var_export( $sent, true ) );

// B2. a site without a cache plugin sends no field at all.
efb_cache_notice_prime_list( $force_empty );
$http_calls = array();
$result     = $start_method->invoke( $admin, 'no-reply@example.org', 'admin@example.org', $efb_function );

efb_cache_notice_check( 'start succeeds without the field', ! empty( $result['success'] ), wp_json_encode( $result ) );
efb_cache_notice_check( 'one request was made', 1 === count( $http_calls ), (string) count( $http_calls ) );
efb_cache_notice_check(
	'caching_plugins is omitted when nothing was detected',
	isset( $http_calls[0]['body'] ) && ! array_key_exists( 'caching_plugins', $http_calls[0]['body'] ),
	wp_json_encode( $http_calls[0]['body'] ?? array() )
);

// B3. an older service rejects the unknown field: retry once, without it.
efb_cache_notice_prime_list( $probe_slug_filter );
$http_calls  = array();
$reject_first = true;
$result      = $start_method->invoke( $admin, 'no-reply@example.org', 'admin@example.org', $efb_function );
$reject_first = false;

efb_cache_notice_check( 'test still succeeds when the service rejects the field', ! empty( $result['success'] ), wp_json_encode( $result ) );
efb_cache_notice_check( 'the rejection was retried once', 2 === count( $http_calls ), (string) count( $http_calls ) );
efb_cache_notice_check(
	'the first request carried the field',
	isset( $http_calls[0]['body']['caching_plugins'] ) && '' !== $http_calls[0]['body']['caching_plugins'],
	wp_json_encode( $http_calls[0]['body'] ?? array() )
);
efb_cache_notice_check(
	'the retry dropped the field',
	isset( $http_calls[1]['body'] ) && ! array_key_exists( 'caching_plugins', $http_calls[1]['body'] ),
	wp_json_encode( $http_calls[1]['body'] ?? array() )
);
efb_cache_notice_check( 'both requests hit the start endpoint', 2 === count( array_filter( $http_calls, function ( $call ) {
	return false !== strpos( $call['url'], '/start' );
} ) ) );

// B4. a refusal that is not about this field must not be papered over by a
// second request that would fail the same way.
efb_cache_notice_prime_list( $probe_slug_filter );
$http_calls   = array();
$reject_code  = 'api_guard_invalid_content_type';
$reject_first = true;
$result       = $start_method->invoke( $admin, 'no-reply@example.org', 'admin@example.org', $efb_function );
$reject_first = false;
$reject_code  = 'api_guard_unknown_body_arg';

efb_cache_notice_check( 'an unrelated refusal is not retried', 1 === count( $http_calls ), (string) count( $http_calls ) );
efb_cache_notice_check( 'an unrelated refusal still fails', empty( $result['success'] ), wp_json_encode( $result ) );
efb_cache_notice_check( 'the guard code reaches the caller', isset( $result['code'] ) && 400 === (int) $result['code'], wp_json_encode( $result ) );

// B5. with no field to blame there is nothing to retry either.
efb_cache_notice_prime_list( $force_empty );
$http_calls   = array();
$reject_first = true;
$result       = $start_method->invoke( $admin, 'no-reply@example.org', 'admin@example.org', $efb_function );
$reject_first = false;

efb_cache_notice_check( 'no retry without the field', 1 === count( $http_calls ), (string) count( $http_calls ) );
efb_cache_notice_check( 'the original failure is reported', empty( $result['success'] ), wp_json_encode( $result ) );

// --- restore ------------------------------------------------------------

efb_cache_notice_restore_option( $original_option );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
