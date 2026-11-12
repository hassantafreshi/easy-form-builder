<?php
/**
 * Not a test. A fake PayTabs for the real-WordPress scenario "paytabs" of tests/e2e-wp/run.js
 * (loaded by the mu-plugin efb-e2e-paytabs.php while the option efb_e2e_ptb_fake is set). Nothing
 * leaves the machine and no real key or profile is used.
 *
 *  - pre_http_request answers https://<the seven regional hosts>/payment/request (sale, refund) and
 *    /payment/query with the shapes of PayTabs' documented responses (tests/fixtures/payments/paytabs);
 *    a key works only on its own profile's region (Test: secure.paytabs.sa / 10001, Live:
 *    secure.paytabs.com / 10002), as at PayTabs;
 *  - redirect_url is https://secure.paytabs.sa/payment/page/<token>; the browser test routes that
 *    host to the page served here (REST efb-e2e/v1/paytabs-page) with "Pay" (A), "Decline" (D) and
 *    "Cancel" (C): each sends the payer back like PayTabs, a POST of signed fields to the return URL;
 *  - callback() builds the server-to-server callback (JSON body + Signature header) independently
 *    of the add-on (hash_hmac of the raw body);
 *  - state (transactions, request log without keys) lives in the option efb_e2e_ptb_state.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Efb_Test_Paytabs_Fake {

	const OPTION = 'efb_e2e_ptb_state';
	const HOSTS = array( 'sa' => 'secure.paytabs.sa', 'ae' => 'secure.paytabs.com', 'eg' => 'secure-egypt.paytabs.com', 'om' => 'secure-oman.paytabs.com', 'jo' => 'secure-jordan.paytabs.com', 'iq' => 'secure-iraq.paytabs.com', 'global' => 'secure-global.paytabs.com' );

	/** Fake server keys, assembled here (never a real key). */
	public static function key( $mode ) {
		return 'S' . 'E2EFAKE' . ( $mode === 'live' ? 'LV' : 'TS' ) . '-' . 'NOTAREALKEY' . '-' . ( $mode === 'live' ? 'LIVEPROF02' : 'TESTPROF01' );
	}
	public static function profile( $mode ) { return $mode === 'live' ? '10002' : '10001'; }
	public static function region( $mode ) { return $mode === 'live' ? 'ae' : 'sa'; }

	public static function register() {
		add_filter( 'pre_http_request', array( __CLASS__, 'http' ), 10, 3 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function fixture( $name ) {
		$f = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/payments/paytabs/' . $name . '.json' ), true );
		return is_array( $f ) ? $f['response']['body'] : array();
	}

	public static function state() {
		$s = get_option( self::OPTION, array() );
		return ( is_array( $s ) ? $s : array() ) + array( 'tx' => array(), 'log' => array(), 'n' => 0 );
	}

	public static function save( array $s ) {
		update_option( self::OPTION, $s, false );
	}

	public static function reset() {
		delete_option( self::OPTION );
	}

	private static function answer( $code, $body ) {
		return array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => wp_json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}

	public static function http( $pre, $args, $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		$region = array_search( $host, self::HOSTS, true );
		if ( $region === false ) return $pre;
		$s = self::state();
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$raw = isset( $args['body'] ) && is_string( $args['body'] ) ? $args['body'] : '';
		$b = json_decode( $raw, true );
		$auth = isset( $args['headers']['authorization'] ) ? (string) $args['headers']['authorization'] : '';
		$mode = $auth === self::key( 'live' ) ? 'live' : ( $auth === self::key( 'test' ) ? 'test' : '' );
		$s['log'][] = array( 'path' => $path, 'host' => $host, 'key_mode' => $mode, 'raw' => $raw );
		$s['log'] = array_slice( $s['log'], -80 );
		$out = null;
		if ( $mode === '' || self::region( $mode ) !== $region ) {
			$out = self::answer( 401, self::fixture( 'error-auth' ) );
		} elseif ( ! is_array( $b ) || (string) ( isset( $b['profile_id'] ) ? $b['profile_id'] : '' ) !== self::profile( $mode ) ) {
			$out = self::answer( 400, array( 'code' => 4, 'message' => 'Profile ID is invalid', 'trace' => 'PMNT0401.E2E' ) );
		} elseif ( $path === '/payment/query' ) {
			$ref = (string) ( isset( $b['tran_ref'] ) ? $b['tran_ref'] : '' );
			$out = isset( $s['tx'][ $ref ] ) && $s['tx'][ $ref ]['_status'] !== '' ? self::answer( 200, self::tx_body( $s['tx'][ $ref ] ) ) : self::answer( 400, self::fixture( 'error-not-found' ) );
		} elseif ( $path === '/payment/request' && isset( $b['tran_type'] ) && $b['tran_type'] === 'refund' ) {
			$orig = (string) ( isset( $b['tran_ref'] ) ? $b['tran_ref'] : '' );
			if ( ! isset( $s['tx'][ $orig ] ) || $s['tx'][ $orig ]['_status'] !== 'A' ) {
				$out = self::answer( 400, array( 'code' => 207, 'message' => 'Transaction is not refundable', 'trace' => 'PMNT0401.E2E' ) );
			} else {
				$s['n']++;
				$r = self::fixture( 'refund-A' );
				$r['tran_ref'] = ( $mode === 'live' ? 'SFT' : 'TST' ) . 'E2E' . gmdate( 'His' ) . str_pad( (string) $s['n'], 4, '0', STR_PAD_LEFT );
				$r['cart_id'] = $b['cart_id'];
				$r['cart_currency'] = $b['cart_currency'];
				$r['cart_amount'] = preg_match( '/"cart_amount":([0-9.]+)/', $raw, $m ) ? $m[1] : '';
				$r['cart_description'] = $b['cart_description'];
				$r['previous_tran_ref'] = $orig;
				$s['tx'][ $r['tran_ref'] ] = array_merge( $r, array( '_status' => 'A', '_mode' => $mode, '_refund' => true ) );
				$out = self::answer( 200, $r );
			}
		} elseif ( $path === '/payment/request' ) {
			$s['n']++;
			$ref = ( $mode === 'live' ? 'SFT' : 'TST' ) . 'E2E' . gmdate( 'His' ) . str_pad( (string) $s['n'], 4, '0', STR_PAD_LEFT );
			$token = strtoupper( substr( md5( $ref . wp_rand() ), 0, 24 ) );
			$amount = preg_match( '/"cart_amount":([0-9.]+)/', $raw, $m ) ? $m[1] : '';
			$r = self::fixture( 'request-200' );
			$r = array_merge( $r, array( 'tran_ref' => $ref, 'cart_id' => $b['cart_id'], 'cart_description' => $b['cart_description'], 'cart_currency' => $b['cart_currency'], 'cart_amount' => $amount,
				'tran_currency' => $b['cart_currency'], 'tran_total' => $amount, 'callback' => isset( $b['callback'] ) ? $b['callback'] : '', 'return' => isset( $b['return'] ) ? $b['return'] : '',
				'redirect_url' => 'https://' . $host . '/payment/page/' . $token . ( ! empty( $b['framed'] ) ? '?framed=1' : '' ), 'profileId' => (int) self::profile( $mode ) ) );
			$s['tx'][ $ref ] = array( '_status' => '', '_mode' => $mode, '_token' => $token, '_request' => $b, 'tran_ref' => $ref, 'tran_type' => 'Sale', 'cart_id' => $b['cart_id'],
				'cart_description' => $b['cart_description'], 'cart_currency' => $b['cart_currency'], 'cart_amount' => $amount );
			$out = self::answer( 200, $r );
		} else {
			$out = self::answer( 404, array( 'code' => 404, 'message' => 'not found', 'trace' => 'PMNT0401.E2E' ) );
		}
		self::save( $s );
		return $out;
	}

	/** A transaction as the query and the callback show it. */
	public static function tx_body( array $t ) {
		$b = self::fixture( 'query-A' );
		foreach ( array( 'tran_ref', 'tran_type', 'cart_id', 'cart_description', 'cart_currency', 'cart_amount' ) as $k ) $b[ $k ] = $t[ $k ];
		$b['profileId'] = (int) self::profile( $t['_mode'] );
		$b['payment_result']['response_status'] = $t['_status'];
		$b['payment_result']['response_message'] = array( 'A' => 'Authorised', 'D' => 'Declined', 'C' => 'Cancelled' )[ $t['_status'] ] ?? 'Error';
		$b['payment_info']['payment_method'] = isset( $t['_method'] ) ? $t['_method'] : 'mada';
		return $b;
	}

	/** PayTabs' side of a transaction: A, D, C … (amount optional). */
	public static function set( $ref, $status, $amount = null ) {
		$s = self::state();
		if ( ! isset( $s['tx'][ $ref ] ) ) return false;
		$s['tx'][ $ref ]['_status'] = $status;
		if ( $amount !== null ) $s['tx'][ $ref ]['cart_amount'] = (string) $amount;
		self::save( $s );
		return true;
	}

	/** The callback PayTabs would post: raw JSON body + Signature (hash_hmac of the raw body). */
	public static function callback( $ref, $key ) {
		$s = self::state();
		if ( ! isset( $s['tx'][ $ref ] ) ) return array( 'error' => 'unknown ' . $ref );
		$body = wp_json_encode( self::tx_body( $s['tx'][ $ref ] ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return array( 'body' => $body, 'signature' => hash_hmac( 'sha256', $body, $key ) );
	}

	/** The signed fields of the browser POST to the return URL (PayTabs' PHP sample: array_filter, ksort, http_build_query). */
	public static function return_fields( array $t, $key ) {
		$f = array( 'acquirerMessage' => '', 'acquirerRRN' => '', 'cartId' => $t['cart_id'], 'customerEmail' => isset( $t['_request']['customer_details']['email'] ) ? $t['_request']['customer_details']['email'] : '',
			'respCode' => $t['_status'] === 'A' ? 'G96376' : '325', 'respMessage' => $t['_status'] === 'A' ? 'Authorised' : 'Declined', 'respStatus' => $t['_status'], 'token' => '', 'tranRef' => $t['tran_ref'] );
		$s = array_filter( $f );
		ksort( $s );
		$f['signature'] = hash_hmac( 'sha256', http_build_query( $s ), $key );
		return $f;
	}

	public static function routes() {
		register_rest_route( 'efb-e2e/v1', 'paytabs-page', array(
			'methods'             => array( 'GET', 'POST' ),
			'callback'            => array( __CLASS__, 'page' ),
			'permission_callback' => '__return_true',
		) );
	}

	private static function by_token( $token ) {
		foreach ( self::state()['tx'] as $t ) {
			if ( isset( $t['_token'] ) && hash_equals( (string) $t['_token'], (string) $token ) ) return $t;
		}
		return null;
	}

	/** The fake hosted page: amount, method, language; Pay / Decline / Cancel set PayTabs' state and POST the payer back. */
	public static function page( WP_REST_Request $r ) {
		$t = self::by_token( (string) $r->get_param( 'token' ) );
		if ( ! $t ) return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		if ( $r->get_method() === 'POST' ) {
			$do = (string) $r->get_param( 'do' );
			$st = array( 'pay' => 'A', 'decline' => 'D', 'cancel' => 'C' );
			if ( isset( $st[ $do ] ) ) self::set( $t['tran_ref'], $st[ $do ] );
			$t = self::by_token( (string) $r->get_param( 'token' ) );
			$inputs = '';
			foreach ( self::return_fields( $t, self::key( $t['_mode'] ) ) as $k => $v ) $inputs .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
			echo '<!doctype html><html><body><form id="back" method="post" action="' . esc_url( $t['_request']['return'] ) . '">' . $inputs . '</form><script>document.getElementById("back").submit();</script></body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}
		$q = $t['_request'];
		echo '<!doctype html><html lang="' . esc_attr( $q['paypage_lang'] ) . '"><head><meta charset="utf-8"><title>PayTabs (fake)</title></head><body>'
			. '<h1>PayTabs payment page (fake, local test)</h1><p>Amount: <strong id="amount">' . esc_html( $t['cart_amount'] . ' ' . $t['cart_currency'] ) . '</strong></p>'
			. '<p>Methods: <code id="methods">' . esc_html( isset( $q['payment_methods'] ) ? implode( ',', $q['payment_methods'] ) : 'all' ) . '</code> Language: <code id="lang">' . esc_html( $q['paypage_lang'] ) . '</code> Framed: <code id="framed">' . ( ! empty( $q['framed'] ) ? '1' : '0' ) . '</code></p>'
			. '<form method="post" action="' . esc_url( rest_url( 'efb-e2e/v1/paytabs-page' ) ) . '"><input type="hidden" name="token" value="' . esc_attr( $t['_token'] ) . '">'
			. '<button id="pay" name="do" value="pay">Pay</button> <button id="decline" name="do" value="decline">Decline</button> <button id="cancel" name="do" value="cancel">Cancel</button></form>'
			. '</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts
		exit;
	}
}
