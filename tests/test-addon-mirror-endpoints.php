<?php
/**
 * Add-on mirror endpoint regression tests (phase 2).
 *
 * Context: a host-level IP-reputation filter in front of whitestudio.team has
 * repeatedly answered a bare 403 to ordinary plugin requests — sometimes for
 * an hour, sometimes for six days straight — leaving paying customers unable
 * to install add-ons they had already bought. EMSFB_MIRROR_SERVER_URL adds an
 * independent second origin so a blocked primary is no longer the end of it.
 *
 * These tests lock down the parts that are easy to get subtly wrong:
 *   - the endpoint list for each locale, and the mirror opt-out
 *   - a demoted endpoint moves to the back rather than disappearing
 *   - the primary can never be demoted (that would leave nowhere to ask)
 *   - the download allow-list now applies to every locale, not just fa_IR
 *
 * Run: php tests/test-addon-mirror-endpoints.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'EMSFB_PLUGIN_DIRECTORY', dirname( __DIR__ ) . '/' );
define( 'EMSFB_PLUGIN_VERSION', '4.1.3' );
define( 'EMSFB_SERVER_URL', 'https://whitestudio.team' );
define( 'EMSFB_MIRROR_SERVER_URL', 'https://blog.whitestudio.team' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$test_locale     = 'en_US';
$test_transients = array();

function get_locale() {
	global $test_locale;
	return $test_locale;
}

function get_transient( $key ) {
	global $test_transients;
	return array_key_exists( $key, $test_transients ) ? $test_transients[ $key ] : false;
}

function set_transient( $key, $value, $ttl = 0 ) {
	global $test_transients;
	$test_transients[ $key ] = $value;
	return true;
}

function delete_transient( $key ) {
	global $test_transients;
	unset( $test_transients[ $key ] );
	return true;
}

function untrailingslashit( $string ) {
	return rtrim( (string) $string, '/\\' );
}

function esc_url_raw( $url ) {
	return (string) $url;
}

function esc_html__( $text, $domain = null ) {
	return $text;
}

function wp_parse_url( $url, $component = -1 ) {
	return $component === -1 ? parse_url( $url ) : parse_url( $url, $component );
}

class WP_Error {
	public $code;
	public $message;

	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/*
 * functions.php is one large class with a great deal of WordPress surface, so
 * the three methods under test are lifted out of the shipped source instead of
 * requiring the file — the same approach test-addon-install-plan-gate.php uses,
 * and for the same reason.
 */
$source = file_get_contents( dirname( __DIR__ ) . '/includes/functions.php' );

function efb_mirror_extract_method( $source, $signature ) {
	$start = strpos( $source, $signature );
	if ( false === $start ) {
		fwrite( STDERR, "Could not find {$signature} in functions.php\n" );
		exit( 1 );
	}

	$brace_start = strpos( $source, '{', $start );
	$depth       = 0;
	$length      = strlen( $source );

	for ( $i = $brace_start; $i < $length; $i++ ) {
		if ( '{' === $source[ $i ] ) {
			$depth++;
		} elseif ( '}' === $source[ $i ] ) {
			$depth--;
			if ( 0 === $depth ) {
				return substr( $source, $start, $i - $start + 1 );
			}
		}
	}

	fwrite( STDERR, "Unbalanced braces while reading {$signature}\n" );
	exit( 1 );
}

$methods = '';
foreach (
	array(
		'public function addon_api_domains_efb()',
		'public function addon_download_allowed_hosts_efb()',
		'public function addon_api_down_key_efb( $domain )',
		'public function addon_api_mark_down_efb( $domain )',
		'public function normalize_addon_download_url_efb( $url )',
	) as $signature
) {
	$methods .= efb_mirror_extract_method( $source, $signature ) . "\n\n";
}

eval(
	'class Efb_Mirror_Under_Test {
		const EMSFB_ADDON_IR_DOMAIN = "https://easyformbuilder.ir";
		const EMSFB_ADDON_DOWN_TTL  = HOUR_IN_SECONDS;
	' . $methods . '}'
);

$passed = 0;
$failed = 0;

function efb_mirror_assert( $name, $expected, $actual ) {
	global $passed, $failed;
	if ( $expected === $actual ) {
		echo "  PASS  {$name}\n";
		$passed++;
		return;
	}
	echo "  FAIL  {$name}\n";
	echo '        expected: ' . var_export( $expected, true ) . "\n";
	echo '        actual:   ' . var_export( $actual, true ) . "\n";
	$failed++;
}

$subject = new Efb_Mirror_Under_Test();

echo "Endpoint list\n";

$test_locale = 'en_US';
efb_mirror_assert(
	'non-Persian sites get the primary then the mirror',
	array( 'https://whitestudio.team', 'https://blog.whitestudio.team' ),
	$subject->addon_api_domains_efb()['endpoints']
);

$test_locale = 'fa_IR';
efb_mirror_assert(
	'Persian sites keep the Iranian domain and gain the mirror',
	array( 'https://whitestudio.team', 'https://easyformbuilder.ir', 'https://blog.whitestudio.team' ),
	$subject->addon_api_domains_efb()['endpoints']
);

$test_locale = 'en_US';
efb_mirror_assert(
	'primary stays first for backwards-compatible callers',
	'https://whitestudio.team',
	$subject->addon_api_domains_efb()['primary']
);
efb_mirror_assert(
	'fallback key now points at the mirror',
	'https://blog.whitestudio.team',
	$subject->addon_api_domains_efb()['fallback']
);

echo "\nDemotion\n";

$test_transients = array();
$subject->addon_api_mark_down_efb( 'https://blog.whitestudio.team' );
efb_mirror_assert(
	'a demoted endpoint moves to the back, it is not dropped',
	array( 'https://whitestudio.team', 'https://blog.whitestudio.team' ),
	$subject->addon_api_domains_efb()['endpoints']
);
efb_mirror_assert(
	'demotion is reported so callers can log it',
	true,
	$subject->addon_api_domains_efb()['mirror_skipped']
);

$test_locale     = 'fa_IR';
$test_transients = array();
$subject->addon_api_mark_down_efb( 'https://easyformbuilder.ir' );
efb_mirror_assert(
	'a demoted middle endpoint falls behind the healthy mirror',
	array( 'https://whitestudio.team', 'https://blog.whitestudio.team', 'https://easyformbuilder.ir' ),
	$subject->addon_api_domains_efb()['endpoints']
);

$test_locale     = 'en_US';
$test_transients = array();
$subject->addon_api_mark_down_efb( 'https://whitestudio.team' );
efb_mirror_assert(
	'the primary is never demoted, or there would be nowhere left to ask',
	array(),
	$test_transients
);

echo "\nDownload allow-list (every locale, not just fa_IR)\n";

$test_locale     = 'en_US';
$test_transients = array();

efb_mirror_assert(
	'the mirror host is accepted for archives',
	'https://blog.whitestudio.team/wp-json/payefb-blog-mirror/v1/files/telegram.zip',
	$subject->normalize_addon_download_url_efb( 'https://blog.whitestudio.team/wp-json/payefb-blog-mirror/v1/files/telegram.zip' )
);

$rejected = $subject->normalize_addon_download_url_efb( 'https://evil.example.com/payload.zip' );
efb_mirror_assert(
	'an unknown host is refused on a non-Persian site too',
	'emsfb_addon_download_host_not_allowed',
	is_wp_error( $rejected ) ? $rejected->get_error_code() : 'accepted'
);

$suffix = $subject->normalize_addon_download_url_efb( 'https://whitestudio.team.evil.com/x.zip' );
efb_mirror_assert(
	'a look-alike suffix domain is refused',
	'emsfb_addon_download_host_not_allowed',
	is_wp_error( $suffix ) ? $suffix->get_error_code() : 'accepted'
);

$not_zip = $subject->normalize_addon_download_url_efb( 'https://blog.whitestudio.team/etc/passwd' );
efb_mirror_assert(
	'a non-archive path on an allowed host is still refused',
	'emsfb_addon_download_host_not_allowed',
	is_wp_error( $not_zip ) ? $not_zip->get_error_code() : 'accepted'
);

$empty = $subject->normalize_addon_download_url_efb( '' );
efb_mirror_assert(
	'a missing URL is reported as missing, not as a bad host',
	'emsfb_addon_download_url_missing',
	is_wp_error( $empty ) ? $empty->get_error_code() : 'accepted'
);

echo "\n========================================\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";
echo "========================================\n";

exit( $failed > 0 ? 1 : 0 );
