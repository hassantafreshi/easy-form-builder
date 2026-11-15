<?php
/**
 * Plugin Name: EFB e2e F12b helpers (local test site only)
 * Description: For the F12b tests (CAPTCHA providers). While the option efb_e2e_f12b_mock is set, the siteverify
 *              endpoints of every CAPTCHA service (Google on google.com and recaptcha.net, hCaptcha, Cloudflare
 *              Turnstile, Friendly Captcha) answer here (pre_http_request, before efb-e2e-f12.php), never on the
 *              network, for tokens the test built itself:
 *                f12b.<base64url JSON>.<random>   → that JSON is the service's answer (success, score, action,
 *                                                    hostname, error-codes …; "hostname":"@home" = this site);
 *                f12b-slow.<random>               → a timeout (WP_Error, what wp_remote_post() gives after 8 s);
 *                f12b-bad.<random>                → an answer that is not JSON (502).
 *              Every call is recorded in efb_e2e_f12b_calls: URL, the body's keys, the header names, the sitekey and
 *              idempotency_key — never a secret's value. Copied by ctl-f12b.php mu.
 */

if ( get_option( 'efb_e2e_f12b_mock' ) ) {
	add_filter(
		'pre_http_request',
		static function ( $pre, $args, $url ) {
			$hosts = array( 'www.google.com/recaptcha/api/siteverify', 'www.recaptcha.net/recaptcha/api/siteverify', 'api.hcaptcha.com/siteverify', 'challenges.cloudflare.com/turnstile/v0/siteverify', 'frcapi.com/api/v2/captcha/siteverify' );
			$hit = '';
			foreach ( $hosts as $h ) if ( false !== strpos( (string) $url, $h ) ) $hit = $h;
			if ( '' === $hit ) return $pre;
			$body = isset( $args['body'] ) ? $args['body'] : array();
			if ( is_string( $body ) ) {
				$j = json_decode( $body, true );
				if ( is_array( $j ) ) $body = $j; else parse_str( $body, $body );
			}
			$token = isset( $body['response'] ) ? (string) $body['response'] : '';
			if ( 0 !== strpos( $token, 'f12b' ) ) return $pre;
			$calls = get_option( 'efb_e2e_f12b_calls', array() );
			$calls = is_array( $calls ) ? $calls : array();
			$rec = array( 'url' => preg_replace( '/\?.*$/', '', (string) $url ), 'keys' => array_keys( (array) $body ), 'headers' => array_keys( isset( $args['headers'] ) ? (array) $args['headers'] : array() ), 'timeout' => $args['timeout'] ?? null, 'redirection' => $args['redirection'] ?? null );
			foreach ( array( 'sitekey', 'idempotency_key' ) as $k ) if ( isset( $body[ $k ] ) ) $rec[ $k ] = (string) $body[ $k ];
			$calls[] = $rec;
			update_option( 'efb_e2e_f12b_calls', array_slice( $calls, -40 ), false );
			if ( 0 === strpos( $token, 'f12b-slow.' ) ) return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 8000 milliseconds' );
			if ( 0 === strpos( $token, 'f12b-bad.' ) ) return array( 'headers' => array(), 'body' => '<html>502</html>', 'response' => array( 'code' => 502, 'message' => 'Bad Gateway' ), 'cookies' => array(), 'filename' => null );
			$parts = explode( '.', $token );
			$ans = isset( $parts[1] ) ? json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true ) : null;
			if ( ! is_array( $ans ) ) $ans = array( 'success' => false, 'error-codes' => array( 'invalid-input-response' ) );
			if ( isset( $ans['hostname'] ) && '@home' === $ans['hostname'] ) $ans['hostname'] = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			if ( isset( $ans['challenge_ts'] ) && '@now' === $ans['challenge_ts'] ) $ans['challenge_ts'] = gmdate( 'c' );
			return array( 'headers' => array(), 'body' => wp_json_encode( $ans ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
		},
		5,
		3
	);
}
