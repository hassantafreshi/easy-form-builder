<?php
/**
 * Plugin Name: EFB e2e F12 helpers (local test site only)
 * Description: For the F12 tests (anti-spam), through public hooks only, each one only while its option is set:
 *              (1) efb_e2e_f12_verdict = spam | reject: the efb_spam_verdict filter decides every submission
 *              (what a third-party plugin may do); (2) efb_e2e_f12_mock = 1: the CAPTCHA services' siteverify
 *              endpoints (Google, hCaptcha, Turnstile) and Akismet are answered here, never on the network
 *              (pre_http_request), from the token's prefix: pass- ok, fail- refused, dup- timeout-or-duplicate,
 *              host- another hostname, act- another action, low- v3 score 0.3, net- a network error, bad- not
 *              JSON; Akismet says spam when the author is akismet-guaranteed-spam. Every call is recorded
 *              (efb_e2e_f12_calls: URL and the body's keys, never a secret's value); (3) efb_e2e_f12_akismet =
 *              real: Akismet's own class is loaded (the plugin stays inactive) with a placeholder key, fake: a
 *              stand-in class with the same three methods; (4) efb_e2e_f12_hs_off = 1: efb_core_spam_checks
 *              switches the core honeypot off (what Human Shield may do); (5) efb_e2e_f12_http_log = 1: requests to
 *              *.example.test (a test form's webhook) recorded and answered here; (6) efb_e2e_f12_akismet_down = 1:
 *              Akismet unreachable; (7) efb_e2e_f12_shield = bot | human: Shield Security's shield_test_ip_is_bot().
 *              Copied by ctl.php f12-mu.
 */

if ( get_option( 'efb_e2e_f12_verdict' ) ) {
	add_filter(
		'efb_spam_verdict',
		static function ( $v ) {
			$w = get_option( 'efb_e2e_f12_verdict' );
			return in_array( $w, array( 'spam', 'reject' ), true ) ? array( 'verdict' => $w, 'reasons' => array( 'filter' ) ) : $v;
		},
		10,
		2
	);
}

if ( get_option( 'efb_e2e_f12_hs_off' ) ) {
	add_filter(
		'efb_core_spam_checks',
		static function ( $c ) {
			$c['honeypot'] = false;
			return $c;
		}
	);
}

$efb_e2e_f12_ak = get_option( 'efb_e2e_f12_akismet' );
if ( 'real' === $efb_e2e_f12_ak && ! class_exists( 'Akismet' ) && is_readable( WP_PLUGIN_DIR . '/akismet/class.akismet.php' ) ) {
	if ( ! defined( 'AKISMET_VERSION' ) ) define( 'AKISMET_VERSION', '5.7.2' );
	if ( ! defined( 'AKISMET__PLUGIN_DIR' ) ) define( 'AKISMET__PLUGIN_DIR', WP_PLUGIN_DIR . '/akismet/' );
	require_once WP_PLUGIN_DIR . '/akismet/class.akismet.php';
	add_filter( 'akismet_get_api_key', static function () { return 'e2e0placeholder'; } );
} elseif ( 'fake' === $efb_e2e_f12_ak && ! class_exists( 'Akismet' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	class Akismet {
		public static function get_api_key() { return 'e2e0placeholder'; }
		public static function build_query( $args ) { return http_build_query( $args ); }
		public static function http_post( $request, $path ) {
			$r = wp_remote_post( 'https://rest.akismet.com/1.1/' . $path, array( 'body' => $request, 'timeout' => 15 ) );
			if ( is_wp_error( $r ) ) return array( array(), '' );
			return array( wp_remote_retrieve_headers( $r ), wp_remote_retrieve_body( $r ) );
		}
	}
}

/* (5) efb_e2e_f12_http_log = 1: every request to an example.test host (a webhook of a test form) is recorded in
 * efb_e2e_f12_http and answered 200 here (never sent). */
if ( get_option( 'efb_e2e_f12_http_log' ) ) {
	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) {
			$host = (string) wp_parse_url( (string) $url, PHP_URL_HOST );
			if ( ( '' === $host || substr( $host, -13 ) !== '.example.test' ) && false === strpos( (string) $url, 'efb_e2e_hook' ) ) return $pre;
			$log = get_option( 'efb_e2e_f12_http', array() );
			$log = is_array( $log ) ? $log : array();
			$log[] = array( 'host' => $host, 'time' => time() );
			update_option( 'efb_e2e_f12_http', array_slice( $log, -30 ), false );
			return array( 'headers' => array(), 'body' => '{"ok":true}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
		},
		1,
		3
	);
}

/* (7) efb_e2e_f12_shield = bot | human: a stand-in for Shield Security's public function (silentCAPTCHA). */
if ( get_option( 'efb_e2e_f12_shield' ) && ! function_exists( 'shield_test_ip_is_bot' ) ) {
	function shield_test_ip_is_bot( $ip ) {
		return get_option( 'efb_e2e_f12_shield' ) === 'bot';
	}
}

/* (6) efb_e2e_f12_akismet_down = 1: Akismet does not answer (a network error), never the real service. */
if ( get_option( 'efb_e2e_f12_akismet_down' ) ) {
	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) {
			return false !== strpos( (string) $url, 'rest.akismet.com' ) ? new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' ) : $pre;
		},
		2,
		3
	);
}

if ( get_option( 'efb_e2e_f12_mock' ) ) {
	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) {
			$hosts = array( 'www.google.com/recaptcha/api/siteverify', 'api.hcaptcha.com/siteverify', 'challenges.cloudflare.com/turnstile/v0/siteverify', 'rest.akismet.com/1.1/' );
			$hit = '';
			foreach ( $hosts as $h ) if ( false !== strpos( (string) $url, $h ) ) $hit = $h;
			if ( '' === $hit ) return $pre;
			$body = isset( $args['body'] ) ? $args['body'] : array();
			if ( is_string( $body ) ) parse_str( $body, $body );
			$calls = get_option( 'efb_e2e_f12_calls', array() );
			$calls = is_array( $calls ) ? $calls : array();
			$rec = array( 'url' => preg_replace( '/\?.*$/', '', (string) $url ), 'keys' => array_keys( (array) $body ) );
			foreach ( array( 'idempotency_key', 'sitekey', 'comment_type', 'comment_author', 'comment_author_email', 'comment_content', 'user_ip', 'user_agent', 'is_test', 'honeypot_field_name', 'blog_lang' ) as $k ) {
				if ( isset( $body[ $k ] ) ) $rec[ $k ] = (string) $body[ $k ];
			}
			$calls[] = $rec;
			update_option( 'efb_e2e_f12_calls', array_slice( $calls, -30 ), false );
			$json = static function ( $a ) { return array( 'headers' => array(), 'body' => wp_json_encode( $a ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null ); };
			if ( false !== strpos( $hit, 'akismet' ) ) {
				$spam = isset( $body['comment_author'] ) && false !== strpos( (string) $body['comment_author'], 'akismet-guaranteed-spam' );
				$discard = $spam && isset( $body['comment_content'] ) && false !== strpos( (string) $body['comment_content'], 'discard-me' );
				return array( 'headers' => $discard ? array( 'x-akismet-pro-tip' => 'discard' ) : array(), 'body' => $spam ? 'true' : 'false', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
			}
			$token = isset( $body['response'] ) ? (string) $body['response'] : '';
			$home = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			$base = array( 'success' => true, 'hostname' => $home, 'challenge_ts' => gmdate( 'c' ) );
			if ( false !== strpos( $hit, 'google' ) ) {
				$base['action'] = 'efb_submit';
				$base['score'] = 0.9;
			}
			if ( false !== strpos( $hit, 'cloudflare' ) ) $base['action'] = 'efb_submit';
			if ( 0 === strpos( $token, 'net-' ) ) return new WP_Error( 'http_request_failed', 'cURL error 28: timed out' );
			if ( 0 === strpos( $token, 'bad-' ) ) return array( 'headers' => array(), 'body' => '<html>gateway</html>', 'response' => array( 'code' => 502, 'message' => 'Bad Gateway' ), 'cookies' => array(), 'filename' => null );
			if ( 0 === strpos( $token, 'fail-' ) ) return $json( array( 'success' => false, 'error-codes' => array( 'invalid-input-response' ) ) );
			if ( 0 === strpos( $token, 'dup-' ) ) return $json( array( 'success' => false, 'error-codes' => array( 'timeout-or-duplicate' ) ) );
			if ( 0 === strpos( $token, 'host-' ) ) $base['hostname'] = 'evil.example';
			if ( 0 === strpos( $token, 'act-' ) ) $base['action'] = 'login';
			if ( 0 === strpos( $token, 'low-' ) ) $base['score'] = 0.3;
			if ( 0 === strpos( $token, 'pass-' ) || 0 === strpos( $token, 'host-' ) || 0 === strpos( $token, 'act-' ) || 0 === strpos( $token, 'low-' ) ) return $json( $base );
			return $json( array( 'success' => false, 'error-codes' => array( 'invalid-input-response' ) ) );
		},
		10,
		3
	);
}
