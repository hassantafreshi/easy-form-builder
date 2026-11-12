<?php
/**
 * Not a test. A fake api.tap.company for the real-WordPress scenario "tappay" of
 * tests/e2e-wp/run.js (loaded by the mu-plugin efb-e2e-tappay.php while the option
 * efb_e2e_tap_fake is set). Nothing leaves the machine and no real key is used.
 *
 *  - pre_http_request answers https://api.tap.company/v2/… (charges, charges/list, GET charge,
 *    refunds) with the shapes of Tap's documented responses (tests/fixtures/payments/tap);
 *  - charges carry the hosted page https://checkout.payments.tap.company?mode=page&token=…; the
 *    browser test routes that host to the page served here (REST efb-e2e/v1/tap-checkout), which
 *    has "Pay" (CAPTURED), "Decline" (DECLINED) and "Return to the site" buttons;
 *  - state (charges, refunds, request log without keys) lives in the option efb_e2e_tap_state;
 *  - sign() is an independent hashstring (number_format at the documented decimals).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Efb_Test_Tap_Fake {

	const OPTION = 'efb_e2e_tap_state';
	const DECIMALS = array( 'AED' => 2, 'BHD' => 3, 'KWD' => 3, 'OMR' => 3, 'QAR' => 2, 'SAR' => 2, 'USD' => 2, 'EUR' => 2, 'GBP' => 2, 'EGP' => 2, 'JOD' => 3 );

	/** Fake keys of the right shape, assembled here (never a real key). */
	public static function key( $mode ) {
		return 'sk' . '_' . ( $mode === 'live' ? 'live' : 'test' ) . '_' . ( $mode === 'live' ? 'E2eFakeLiveKey0002' : 'E2eFakeTestKey0001' );
	}

	public static function register() {
		add_filter( 'pre_http_request', array( __CLASS__, 'http' ), 10, 3 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function fixture( $name ) {
		$f = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/payments/tap/' . $name . '.json' ), true );
		return is_array( $f ) ? $f['response']['body'] : array();
	}

	public static function state() {
		$s = get_option( self::OPTION, array() );
		return ( is_array( $s ) ? $s : array() ) + array( 'charges' => array(), 'refunds' => array(), 'idem' => array(), 'log' => array(), 'n' => 0 );
	}

	public static function save( array $s ) {
		update_option( self::OPTION, $s, false );
	}

	public static function reset() {
		delete_option( self::OPTION );
	}

	private static function answer( $code, $body ) {
		return array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}

	public static function http( $pre, $args, $url ) {
		if ( wp_parse_url( $url, PHP_URL_HOST ) !== 'api.tap.company' ) return $pre;
		$s = self::state();
		$method = isset( $args['method'] ) ? strtoupper( $args['method'] ) : 'GET';
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$raw = isset( $args['body'] ) && is_string( $args['body'] ) ? $args['body'] : '';
		$b = json_decode( $raw, true );
		$auth = isset( $args['headers']['Authorization'] ) ? substr( (string) $args['headers']['Authorization'], 7 ) : '';
		$mode = $auth === self::key( 'live' ) ? 'live' : ( $auth === self::key( 'test' ) ? 'test' : '' );
		$s['log'][] = array( 'method' => $method, 'path' => $path, 'key_mode' => $mode, 'raw' => $raw );
		$s['log'] = array_slice( $s['log'], -80 );
		if ( $mode === '' ) { self::save( $s ); return self::answer( 401, self::fixture( 'error-401' ) ); }
		$out = null;
		if ( $method === 'POST' && $path === '/v2/charges/list' ) {
			$out = self::answer( 200, array( 'object' => 'list', 'charges' => array(), 'has_more' => false ) );
		} elseif ( $method === 'POST' && $path === '/v2/charges' ) {
			$idem = isset( $b['reference']['idempotent'] ) ? (string) $b['reference']['idempotent'] : '';
			if ( $idem !== '' && isset( $s['idem'][ $idem ] ) ) {
				$out = self::answer( 200, $s['charges'][ $s['idem'][ $idem ] ] );
			} elseif ( empty( $b['customer']['first_name'] ) || empty( $b['customer']['email'] ) || ! isset( $b['amount'], $b['currency'], $b['source']['id'], $b['redirect']['url'] ) ) {
				$out = self::answer( 400, array( 'errors' => array( array( 'code' => '1104', 'description' => 'amount, currency, customer (first_name, email), source and redirect are required' ) ) ) );
			} else {
				$s['n']++;
				$c = self::fixture( 'charge-create-200' );
				$c['id'] = 'chg_TS' . gmdate( 'His' ) . str_pad( (string) $s['n'], 4, '0', STR_PAD_LEFT ) . substr( md5( uniqid( '', true ) ), 0, 12 );
				$c['live_mode'] = $mode === 'live';
				$c['status'] = 'INITIATED';
				$c['amount'] = $b['amount'];
				$c['currency'] = $b['currency'];
				$token = substr( md5( $c['id'] ), 0, 24 );
				$c['transaction'] = array_merge( $c['transaction'], array( 'created' => (string) round( microtime( true ) * 1000 ), 'amount' => $b['amount'], 'currency' => $b['currency'], 'url' => 'https://checkout.payments.tap.company?mode=page&token=' . $token ) );
				$c['metadata'] = isset( $b['metadata'] ) ? $b['metadata'] : array();
				$c['reference'] = isset( $b['reference'] ) ? $b['reference'] : array();
				$c['source'] = array( 'object' => 'source', 'id' => $b['source']['id'] );
				$c['customer'] = $b['customer'];
				$c['redirect'] = array( 'status' => 'PENDING', 'url' => $b['redirect']['url'] );
				$c['post'] = array( 'status' => 'PENDING', 'url' => isset( $b['post']['url'] ) ? $b['post']['url'] : '' );
				$c['_token'] = $token;
				$c['_raw_amount'] = preg_match( '/"amount":([0-9.]+)/', $raw, $m ) ? $m[1] : '';
				$s['charges'][ $c['id'] ] = $c;
				if ( $idem !== '' ) $s['idem'][ $idem ] = $c['id'];
				$out = self::answer( 200, self::public_charge( $c ) );
			}
		} elseif ( $method === 'GET' && preg_match( '#^/v2/charges/(chg_\w+)$#', $path, $m ) ) {
			$out = isset( $s['charges'][ $m[1] ] ) ? self::answer( 200, self::public_charge( $s['charges'][ $m[1] ] ) ) : self::answer( 404, array( 'errors' => array( array( 'code' => '1130', 'description' => 'Charge not found' ) ) ) );
		} elseif ( $method === 'POST' && $path === '/v2/refunds' ) {
			$id = isset( $b['charge_id'] ) ? (string) $b['charge_id'] : '';
			if ( ! isset( $s['charges'][ $id ] ) || $s['charges'][ $id ]['status'] !== 'CAPTURED' ) {
				$out = self::answer( 400, array( 'errors' => array( array( 'code' => '1146', 'description' => 'Charge is not captured' ) ) ) );
			} else {
				$r = self::fixture( 'refund-200' );
				$r['id'] = 're_TS' . gmdate( 'His' ) . substr( md5( uniqid( '', true ) ), 0, 14 );
				$r['charge_id'] = $id;
				$r['amount'] = $b['amount'];
				$r['currency'] = $b['currency'];
				$r['status'] = 'REFUNDED';
				$r['reference'] = array_merge( $r['reference'], isset( $b['reference'] ) ? $b['reference'] : array() );
				$r['_raw_amount'] = preg_match( '/"amount":([0-9.]+)/', $raw, $m ) ? $m[1] : '';
				$s['refunds'][ $r['id'] ] = $r;
				$out = self::answer( 200, $r );
			}
		} else {
			$out = self::answer( 404, array( 'errors' => array( array( 'code' => '404', 'description' => 'not found' ) ) ) );
		}
		self::save( $s );
		return $out;
	}

	private static function public_charge( array $c ) {
		unset( $c['_token'], $c['_raw_amount'] );
		return $c;
	}

	/** Tap's side of a charge: CAPTURED, DECLINED … (amount optional). */
	public static function set( $id, $status, $amount = null ) {
		$s = self::state();
		if ( ! isset( $s['charges'][ $id ] ) ) return false;
		$s['charges'][ $id ]['status'] = $status;
		if ( $amount !== null ) $s['charges'][ $id ]['amount'] = (float) $amount;
		if ( $status === 'CAPTURED' ) {
			$s['charges'][ $id ]['reference']['gateway'] = '123456789012345';
			$s['charges'][ $id ]['reference']['payment'] = '2914230055068969225';
			$s['charges'][ $id ]['response'] = array( 'code' => '000', 'message' => 'Captured' );
			$s['charges'][ $id ]['source']['payment_method'] = 'KNET';
		}
		self::save( $s );
		return true;
	}

	public static function by_token( $token ) {
		foreach ( self::state()['charges'] as $c ) {
			if ( hash_equals( (string) $c['_token'], (string) $token ) ) return $c;
		}
		return null;
	}

	/** The webhook Tap would post for a charge: body + hashstring (independent of the add-on's code). */
	public static function webhook( $id, $secret ) {
		$s = self::state();
		$o = self::public_charge( $s['charges'][ $id ] );
		$cur = $o['currency'];
		$str = 'x_id' . $o['id'] . 'x_amount' . number_format( (float) $o['amount'], self::DECIMALS[ $cur ], '.', '' ) . 'x_currency' . $cur
			. 'x_gateway_reference' . ( isset( $o['reference']['gateway'] ) ? $o['reference']['gateway'] : '' )
			. 'x_payment_reference' . ( isset( $o['reference']['payment'] ) ? $o['reference']['payment'] : '' )
			. 'x_status' . $o['status'] . 'x_created' . $o['transaction']['created'];
		return array( 'body' => wp_json_encode( $o ), 'hashstring' => hash_hmac( 'sha256', $str, $secret ) );
	}

	public static function routes() {
		register_rest_route( 'efb-e2e/v1', 'tap-checkout', array(
			'methods'             => array( 'GET', 'POST' ),
			'callback'            => array( __CLASS__, 'checkout' ),
			'permission_callback' => '__return_true',
		) );
	}

	/** The fake hosted page: shows amount and source; Pay / Decline set Tap's state; Return goes back. */
	public static function checkout( WP_REST_Request $r ) {
		$c = self::by_token( (string) $r->get_param( 'token' ) );
		if ( ! $c ) return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		if ( $r->get_method() === 'POST' ) {
			$do = (string) $r->get_param( 'do' );
			if ( $do === 'pay' ) self::set( $c['id'], 'CAPTURED' );
			if ( $do === 'decline' ) self::set( $c['id'], 'DECLINED' );
			$res = new WP_REST_Response( null, 303 );
			$res->header( 'Location', add_query_arg( 'tap_id', $c['id'], $c['redirect']['url'] ) );
			return $res;
		}
		$action = esc_url( rest_url( 'efb-e2e/v1/tap-checkout' ) );
		$hidden = '<input type="hidden" name="token" value="' . esc_attr( $c['_token'] ) . '">';
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Tap (fake)</title></head><body>'
			. '<h1>Tap checkout (fake, local test)</h1><p>Amount: <strong id="amount">' . esc_html( $c['_raw_amount'] . ' ' . $c['currency'] ) . '</strong></p>'
			. '<p>Source: <code id="source">' . esc_html( $c['source']['id'] ) . '</code></p>'
			. '<form method="post" action="' . $action . '">' . $hidden . '<button id="pay" name="do" value="pay">Pay</button> <button id="decline" name="do" value="decline">Decline</button> <button id="back" name="do" value="back">Return to the site</button></form>'
			. '</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts
		exit;
	}
}
