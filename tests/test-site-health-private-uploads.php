<?php
/**
 * QA4-004 (owner decision 13): Tools › Site Health has a direct test "Easy Form Builder private uploads". It writes a
 * canary file into uploads/efb-private, fetches it over HTTP and deletes it:
 *   - served (HTTP 200 with the canary's content, nginx without the rule) → critical, and the description carries
 *     Uploads::nginx_rule();
 *   - refused (403) → good;
 *   - no answer (a server that cannot request itself) → recommended, with the rule.
 * The web server's answer is played by pre_http_request; the probe's cached result is put back afterwards. Also:
 * the upload token is required by default (Upload_Guard::require_token()).
 *
 * Run: php tests/test-site-health-private-uploads.php        (needs WordPress: tests/wp-env.php)
 */

require_once __DIR__ . '/wp-env.php';
$wp_load = efb_test_wp_load();
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}
require_once $wp_load;
if ( ! class_exists( '\\Emsfb\\Uploads' ) || ! class_exists( '\\Emsfb\\Upload_Guard' ) ) {
	echo "[SKIP] Easy Form Builder is not active in this WordPress installation.\n";
	exit( 0 );
}

$passed = 0;
$failed = 0;
function efb_shp_check( $label, $ok, $detail = '' ) {
	global $passed, $failed;
	if ( $ok ) { $passed++; echo "[PASS] {$label}\n"; return; }
	$failed++;
	echo "[FAIL] {$label}" . ( $detail !== '' ? " -- {$detail}" : '' ) . "\n";
}

$probe_before = get_option( \Emsfb\Uploads::PROBE_OPTION, null );
$soft_before  = isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : null;
$_SERVER['SERVER_SOFTWARE'] = 'Apache';
$answer   = null;
$requests = array();
$fake = function ( $pre, $args, $url ) use ( &$answer, &$requests ) {
	if ( strpos( $url, '/efb-private/' ) === false ) return $pre;
	$requests[] = $url;
	return is_callable( $answer ) ? call_user_func( $answer, $url ) : $answer;
};
add_filter( 'pre_http_request', $fake, 1, 3 );

try {
	$tests = apply_filters( 'site_status_tests', array( 'direct' => array(), 'async' => array() ) );
	efb_shp_check( 'T1 the test is registered as a direct Site Health test', isset( $tests['direct']['efb_private_uploads']['test'] ) && is_callable( $tests['direct']['efb_private_uploads']['test'] ) );
	$run = $tests['direct']['efb_private_uploads']['test'];

	// Served: the body is what the probe wrote (read from the disk at request time, the way a server would).
	$answer = function ( $url ) {
		$path = trailingslashit( \Emsfb\Uploads::private_dir() ) . wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$body = is_file( $path ) ? (string) file_get_contents( $path ) : '';
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => $body, 'headers' => array(), 'cookies' => array() );
	};
	$r = call_user_func( $run );
	efb_shp_check( 'T2 served over HTTP (nginx without the rule) → critical', isset( $r['status'] ) && $r['status'] === 'critical', wp_json_encode( $r ) );
	efb_shp_check( 'T3 ... and the description gives Uploads::nginx_rule()', isset( $r['description'] ) && strpos( $r['description'], esc_html( \Emsfb\Uploads::nginx_rule() ) ) !== false );
	efb_shp_check( 'T4 ... with the result fields WordPress needs (label, badge, test)', ! empty( $r['label'] ) && isset( $r['badge']['label'], $r['badge']['color'] ) && $r['test'] === 'efb_private_uploads' );
	efb_shp_check( 'T5 the canary was fetched from the private folder and deleted afterwards', count( $requests ) === 1 && ! is_file( trailingslashit( \Emsfb\Uploads::private_dir() ) . wp_basename( (string) wp_parse_url( $requests[0], PHP_URL_PATH ) ) ) );

	$answer = array( 'response' => array( 'code' => 403, 'message' => 'Forbidden' ), 'body' => 'Forbidden', 'headers' => array(), 'cookies' => array() );
	$r = call_user_func( $run );
	efb_shp_check( 'T6 refused (403) → good', isset( $r['status'] ) && $r['status'] === 'good', wp_json_encode( $r ) );

	$answer = new WP_Error( 'http_request_failed', 'cURL error 7' );
	$r = call_user_func( $run );
	efb_shp_check( 'T7 no answer → recommended, with the nginx rule', isset( $r['status'] ) && $r['status'] === 'recommended' && strpos( (string) $r['description'], esc_html( \Emsfb\Uploads::nginx_rule() ) ) !== false, wp_json_encode( $r ) );

	global $wp_filter;
	$saved_filter = isset( $wp_filter['emsfb_upload_require_token'] ) ? $wp_filter['emsfb_upload_require_token'] : null;
	unset( $wp_filter['emsfb_upload_require_token'] ); // a test site's own mu-plugin may set it
	$required = \Emsfb\Upload_Guard::require_token();
	if ( $saved_filter !== null ) $wp_filter['emsfb_upload_require_token'] = $saved_filter;
	efb_shp_check( 'T8 the upload token is required by default (no emsfb_upload_require_token filter)', $required === true );
} catch ( Throwable $e ) {
	$failed++;
	echo '[FAIL] unexpected ' . get_class( $e ) . ': ' . $e->getMessage() . "\n";
} finally {
	remove_filter( 'pre_http_request', $fake, 1 );
	if ( $probe_before === null ) delete_option( \Emsfb\Uploads::PROBE_OPTION ); else update_option( \Emsfb\Uploads::PROBE_OPTION, $probe_before, false );
	if ( $soft_before === null ) unset( $_SERVER['SERVER_SOFTWARE'] ); else $_SERVER['SERVER_SOFTWARE'] = $soft_before;
}

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
