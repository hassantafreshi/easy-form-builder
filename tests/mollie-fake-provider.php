<?php
/**
 * Not a test. A fake api.mollie.com for the Mollie add-on's tests (PAY01), answering through the
 * pre_http_request filter, so nothing leaves the machine and no real key is ever used.
 *
 * Every answer is built from tests/fixtures/payments/mollie/*.json (the shapes of the examples in
 * Mollie's API reference, source_url in each file), filled with the state of the fake payment:
 *   POST   /v2/payments                 create-201 (Idempotency-Key: same key + same body = same answer,
 *                                       same key + other body = 422, key "in flight" = idempotency-409)
 *   GET    /v2/payments/{id}            get-<status> (amountRefunded / amountRemaining / amountChargedBack)
 *   POST   /v2/payments/{id}/refunds    refund-201 (status pending), 422 above the remaining amount
 *   DELETE /v2/payments/{id}            canceled while open
 *   GET    /v2/methods                  methods-eur, minus methods whose limits do not fit amount[value]
 *   401 (error-401) for a key that is not test_/live_ + 20 alphanumerics, or contains "REVOKED"
 * The mode of a payment is the mode of the key that created it, like at Mollie.
 *
 * State: the option efb_test_mollie_fake. Used by the PHP tests (tests/test-addon-mollie-*.php) and
 * by the e2e site (tests/e2e-wp/mu-plugins/efb-e2e-mollie.php), where it also serves a hosted
 * checkout page (REST efb-e2e/v1/mollie-checkout) with Paid / Failed / Cancel / Expire buttons.
 */

class Efb_Test_Mollie_Fake {

	const OPTION = 'efb_test_mollie_fake';
	const HOST = 'api.mollie.com';

	public static function register() {
		add_filter( 'pre_http_request', array( __CLASS__, 'http' ), 10, 3 );
	}

	public static function fixture( $name ) {
		$f = __DIR__ . '/fixtures/payments/mollie/' . $name . '.json';
		$d = json_decode( (string) file_get_contents( $f ), true );
		return $d['response'];
	}

	public static function state() {
		$s = get_option( self::OPTION, array() );
		$s = is_array( $s ) ? $s : array();
		return $s + array( 'payments' => array(), 'idem' => array(), 'down' => 0, 'inflight' => array(), 'checkout_base' => '', 'log' => array(), 'disabled' => array() );
	}

	public static function save( array $s ) {
		update_option( self::OPTION, $s, false );
	}

	public static function reset( array $extra = array() ) {
		self::save( array_merge( array( 'payments' => array(), 'idem' => array(), 'down' => 0, 'inflight' => array(), 'checkout_base' => '', 'log' => array(), 'disabled' => array() ), $extra ) );
	}

	public static function set( $key, $value ) {
		$s = self::state();
		$s[ $key ] = $value;
		self::save( $s );
	}

	/** Changes a fake payment (status, amount, refunded, chargedback, method, mode). */
	public static function update( $id, array $fields ) {
		$s = self::state();
		if ( ! isset( $s['payments'][ $id ] ) ) return false;
		foreach ( $fields as $k => $v ) $s['payments'][ $id ][ $k ] = $v;
		if ( isset( $fields['status'] ) && $fields['status'] === 'paid' && empty( $s['payments'][ $id ]['paidAt'] ) ) $s['payments'][ $id ]['paidAt'] = gmdate( 'c' );
		self::save( $s );
		return true;
	}

	public static function payment( $id ) {
		$s = self::state();
		return isset( $s['payments'][ $id ] ) ? $s['payments'][ $id ] : null;
	}

	public static function last_id() {
		$s = self::state();
		$ids = array_keys( $s['payments'] );
		return $ids ? end( $ids ) : '';
	}

	private static function answer( $code, $body ) {
		return array( 'headers' => array( 'content-type' => 'application/hal+json' ), 'body' => json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}

	private static function err( $name, $detail = null ) {
		$f = self::fixture( $name );
		$b = $f['body'];
		if ( $detail !== null ) $b['detail'] = $detail;
		return self::answer( $f['code'], $b );
	}

	private static function minor( $v ) {
		return (int) round( (float) $v * 100 );
	}

	private static function dec( $minor, $cur ) {
		return in_array( $cur, array( 'JPY', 'ISK' ), true ) ? (string) (int) $minor : number_format( $minor / 100, 2, '.', '' );
	}

	/** The GET answer of a stored payment, from the fixture of its status. */
	public static function body( array $p ) {
		$name = 'get-' . $p['status'];
		$file = __DIR__ . '/fixtures/payments/mollie/' . $name . '.json';
		$b = is_readable( $file ) ? self::fixture( $name )['body'] : self::fixture( 'get-open' )['body'];
		foreach ( array( 'id', 'mode', 'amount', 'description', 'redirectUrl', 'webhookUrl', 'metadata', 'status', 'createdAt', 'expiresAt', 'details' ) as $k ) {
			if ( array_key_exists( $k, $p ) ) $b[ $k ] = $p[ $k ];
		}
		if ( ! array_key_exists( 'webhookUrl', $p ) ) unset( $b['webhookUrl'] );
		unset( $b['method'] );
		if ( ! empty( $p['method'] ) ) $b['method'] = $p['method'];
		$cur = $p['amount']['currency'];
		$amt = self::minor_of( $p['amount'] );
		if ( $p['status'] === 'paid' ) {
			$b['paidAt'] = ! empty( $p['paidAt'] ) ? $p['paidAt'] : gmdate( 'c' );
			$ref = isset( $p['refunded'] ) ? (int) $p['refunded'] : 0;
			$b['amountRefunded'] = array( 'value' => self::dec( $ref, $cur ), 'currency' => $cur );
			$b['amountRemaining'] = array( 'value' => self::dec( $amt - $ref, $cur ), 'currency' => $cur );
			if ( ! empty( $p['chargedback'] ) ) $b['amountChargedBack'] = array( 'value' => self::dec( (int) $p['chargedback'], $cur ), 'currency' => $cur );
			else unset( $b['amountChargedBack'] );
		}
		$b['isCancelable'] = $p['status'] === 'open';
		$b['_links']['self']['href'] = 'https://api.mollie.com/v2/payments/' . $p['id'];
		$b['_links']['dashboard']['href'] = 'https://www.mollie.com/dashboard/org_12345678/payments/' . $p['id'];
		if ( $p['status'] === 'open' ) $b['_links']['checkout'] = array( 'href' => $p['checkout'], 'type' => 'text/html' );
		else unset( $b['_links']['checkout'] );
		return $b;
	}

	private static function minor_of( $amount ) {
		$cur = isset( $amount['currency'] ) ? $amount['currency'] : 'EUR';
		$v = isset( $amount['value'] ) ? (string) $amount['value'] : '0';
		return in_array( $cur, array( 'JPY', 'ISK' ), true ) ? (int) $v : self::minor( $v );
	}

	public static function http( $pre, $args, $url ) {
		$host = parse_url( (string) $url, PHP_URL_HOST );
		if ( $host !== self::HOST ) return $pre;
		$s = self::state();
		$method = isset( $args['method'] ) ? strtoupper( $args['method'] ) : 'GET';
		$path = (string) parse_url( (string) $url, PHP_URL_PATH );
		$h = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$auth = isset( $h['Authorization'] ) ? (string) $h['Authorization'] : '';
		$key = strpos( $auth, 'Bearer ' ) === 0 ? substr( $auth, 7 ) : '';
		$body = isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null;
		$s['log'][] = array( 'method' => $method, 'path' => $path, 'query' => (string) parse_url( (string) $url, PHP_URL_QUERY ), 'key_mode' => substr( $key, 0, 4 ), 'idem' => isset( $h['Idempotency-Key'] ) ? $h['Idempotency-Key'] : '', 'body' => $body );
		$s['log'] = array_slice( $s['log'], -60 );
		self::save( $s );
		if ( ! empty( $s['down'] ) ) return new WP_Error( 'http_request_failed', 'fake Mollie down' );
		if ( ! preg_match( '/^(test|live)_[A-Za-z0-9]{20,}$/', $key ) || strpos( $key, 'REVOKED' ) !== false ) return self::err( 'error-401' );
		$mode = substr( $key, 0, 4 ) === 'live' ? 'live' : 'test';

		if ( $method === 'GET' && $path === '/v2/methods' ) {
			parse_str( (string) parse_url( (string) $url, PHP_URL_QUERY ), $q );
			$f = self::fixture( 'methods-eur' );
			$cur = isset( $q['amount']['currency'] ) ? $q['amount']['currency'] : 'EUR';
			$val = isset( $q['amount']['value'] ) ? (float) $q['amount']['value'] : null;
			$out = array();
			foreach ( $f['body']['_embedded']['methods'] as $m ) {
				if ( in_array( $m['id'], (array) $s['disabled'], true ) ) continue;
				if ( $cur !== 'EUR' && ! in_array( $m['id'], array( 'creditcard', 'paypal' ), true ) ) continue;
				if ( $val !== null && ( $val < (float) $m['minimumAmount']['value'] || ( $m['maximumAmount'] && $val > (float) $m['maximumAmount']['value'] ) ) ) continue;
				$m['minimumAmount']['currency'] = $cur;
				if ( $m['maximumAmount'] ) $m['maximumAmount']['currency'] = $cur;
				$out[] = $m;
			}
			// Methods an account may also have activated (a test adds them, e.g. klarna, in3), in Mollie's shape.
			foreach ( isset( $s['extra_methods'] ) ? (array) $s['extra_methods'] : array() as $id ) {
				$out[] = array( 'resource' => 'method', 'id' => (string) $id, 'description' => ucfirst( (string) $id ), 'minimumAmount' => array( 'value' => '0.01', 'currency' => $cur ), 'maximumAmount' => null, 'status' => 'activated' );
			}
			$b = $f['body'];
			$b['_embedded']['methods'] = $out;
			$b['count'] = count( $out );
			return self::answer( 200, $b );
		}

		if ( $method === 'POST' && $path === '/v2/payments' ) {
			// A test can make the next create fail with a given answer (e.g. 422 about the method).
			if ( ! empty( $s['next_create_error'] ) ) {
				$e = $s['next_create_error'];
				$s['next_create_error'] = null;
				self::save( $s );
				return self::answer( $e[0], $e[1] );
			}
			$idem = isset( $h['Idempotency-Key'] ) ? (string) $h['Idempotency-Key'] : '';
			$hash = md5( (string) $args['body'] );
			if ( $idem !== '' && in_array( $idem, (array) $s['inflight'], true ) ) return self::err( 'idempotency-409' );
			if ( $idem !== '' && isset( $s['idem'][ $idem ] ) ) {
				if ( $s['idem'][ $idem ][0] !== $hash ) return self::err( 'error-422', 'The idempotency key was already used with another request body.' );
				$p = $s['payments'][ $s['idem'][ $idem ][1] ];
				$b = self::body( $p );
				return self::answer( 201, $b );
			}
			if ( ! is_array( $body ) || empty( $body['amount']['value'] ) || empty( $body['description'] ) ) return self::err( 'error-422' );
			$tpl = self::fixture( 'create-201' )['body'];
			$id = 'tr_' . substr( str_replace( array( '+', '/', '=' ), '', base64_encode( random_bytes( 16 ) ) ), 0, 10 );
			$base = $s['checkout_base'] !== '' ? $s['checkout_base'] : 'https://www.mollie.com/checkout/select-method/';
			$p = array(
				'id'          => $id,
				'mode'        => $mode,
				'amount'      => $body['amount'],
				'description' => (string) $body['description'],
				'redirectUrl' => isset( $body['redirectUrl'] ) ? $body['redirectUrl'] : '',
				'cancelUrl'   => isset( $body['cancelUrl'] ) ? $body['cancelUrl'] : '',
				'metadata'    => isset( $body['metadata'] ) ? $body['metadata'] : null,
				'status'      => 'open',
				'method'      => isset( $body['method'] ) && is_string( $body['method'] ) ? $body['method'] : null,
				'createdAt'   => gmdate( 'c' ),
				'expiresAt'   => gmdate( 'c', time() + 900 ),
				'checkout'    => $base . ( strpos( $base, '?' ) !== false ? '&id=' : '' ) . $id,
				'request'     => $body,
			);
			if ( isset( $body['webhookUrl'] ) ) $p['webhookUrl'] = $body['webhookUrl'];
			$s['payments'][ $id ] = $p;
			if ( $idem !== '' ) $s['idem'][ $idem ] = array( $hash, $id );
			self::save( $s );
			$b = array_merge( $tpl, self::body( $p ) );
			return self::answer( 201, $b );
		}

		if ( preg_match( '#^/v2/payments/(tr_[A-Za-z0-9]+)(/refunds)?$#', $path, $m ) ) {
			$id = $m[1];
			if ( ! isset( $s['payments'][ $id ] ) || $s['payments'][ $id ]['mode'] !== $mode ) return self::err( 'error-404', 'No payment exists with token ' . $id . '.' );
			$p = $s['payments'][ $id ];
			if ( $method === 'GET' && empty( $m[2] ) ) return self::answer( 200, self::body( $p ) );
			if ( $method === 'DELETE' && empty( $m[2] ) ) {
				if ( $p['status'] !== 'open' ) return self::err( 'error-422', 'The payment cannot be canceled.' );
				$s['payments'][ $id ]['status'] = 'canceled';
				self::save( $s );
				return self::answer( 200, self::body( $s['payments'][ $id ] ) );
			}
			if ( $method === 'POST' && ! empty( $m[2] ) ) {
				$amt = self::minor_of( $p['amount'] );
				$ref = isset( $p['refunded'] ) ? (int) $p['refunded'] : 0;
				$want = is_array( $body ) && isset( $body['amount'] ) ? self::minor_of( $body['amount'] ) : $amt - $ref;
				if ( $p['status'] !== 'paid' || $want <= 0 || $want > $amt - $ref ) return self::err( 'error-422', 'The refund amount is higher than the refundable amount.' );
				$idem = isset( $h['Idempotency-Key'] ) ? (string) $h['Idempotency-Key'] : '';
				if ( $idem !== '' && isset( $s['idem'][ $idem ] ) ) {
					$rb = self::fixture( 'refund-201' )['body'];
					$rb['id'] = $s['idem'][ $idem ][1];
					$rb['amount'] = $body['amount'];
					return self::answer( 201, $rb );
				}
				$rid = 're_' . substr( md5( $id . '|' . $ref . '|' . $want ), 0, 10 );
				$s['payments'][ $id ]['refunded'] = $ref + $want;
				if ( $idem !== '' ) $s['idem'][ $idem ] = array( 'refund', $rid );
				self::save( $s );
				$rb = self::fixture( 'refund-201' )['body'];
				$rb['id'] = $rid;
				$rb['mode'] = $mode;
				$rb['amount'] = $body['amount'];
				$rb['paymentId'] = $id;
				$rb['description'] = isset( $body['description'] ) ? $body['description'] : '';
				$rb['metadata'] = isset( $body['metadata'] ) ? $body['metadata'] : null;
				return self::answer( 201, $rb );
			}
		}
		return self::err( 'error-404', 'Unknown resource' );
	}

	/* ---- e2e only: the hosted checkout page of the fake ---- */

	public static function routes() {
		register_rest_route( 'efb-e2e/v1', 'mollie-checkout', array(
			'methods'             => array( 'GET', 'POST' ),
			'callback'            => array( __CLASS__, 'checkout' ),
			'permission_callback' => '__return_true',
		) );
	}

	public static function checkout( $r ) {
		$id = (string) $r->get_param( 'id' );
		$p = self::payment( $id );
		if ( ! $p ) return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		if ( $r->get_method() === 'POST' ) {
			$out = (string) $r->get_param( 'outcome' );
			if ( in_array( $out, array( 'paid', 'failed', 'canceled', 'expired' ), true ) && $p['status'] === 'open' ) self::update( $id, array( 'status' => $out, 'method' => $out === 'paid' ? 'ideal' : $p['method'] ) );
			$res = new WP_REST_Response( null, 303 );
			$res->header( 'Location', $out === 'canceled' && $p['cancelUrl'] !== '' ? $p['cancelUrl'] : $p['redirectUrl'] );
			return $res;
		}
		$amount = esc_html( $p['amount']['value'] . ' ' . $p['amount']['currency'] );
		$action = esc_url( rest_url( 'efb-e2e/v1/mollie-checkout' ) );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Fake Mollie checkout</title></head><body>'
			. '<h1>Fake Mollie checkout (' . esc_html( $p['mode'] ) . ')</h1><p>Amount: <strong id="amount">' . $amount . '</strong></p><p id="desc">' . esc_html( $p['description'] ) . '</p>'
			. '<p id="methods">' . esc_html( is_array( $p['request']['method'] ?? null ) ? implode( ',', $p['request']['method'] ) : (string) ( $p['request']['method'] ?? '' ) ) . '</p>'
			. '<form method="post" action="' . $action . '"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">'
			. '<button id="paid" name="outcome" value="paid">Paid</button><button id="failed" name="outcome" value="failed">Failed</button>'
			. '<button id="canceled" name="outcome" value="canceled">Canceled</button><button id="expired" name="outcome" value="expired">Expired</button></form></body></html>';
		exit;
	}
}
