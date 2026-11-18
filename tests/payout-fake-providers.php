<?php
/**
 * Not a test. PAY-OUT step 5: fake api.stripe.com (Checkout Sessions), api-m(.sandbox).paypal.com (Orders v2) and
 * (sandbox|payment).zarinpal.com (Payment Gateway v4) for the framework gateways of the Stripe, PayPal and Persia Pay
 * add-ons (stripecheckout, paypalcheckout, zarinpalgate), answering through pre_http_request: nothing leaves the
 * machine and no real key is used (the keys the tests use are assembled from obviously fake parts and only their shape
 * is checked). The answers follow the shapes of each provider's API reference:
 *   Stripe   POST /v1/checkout/sessions (Idempotency-Key: the same key = the same session), GET /v1/checkout/sessions/{id}
 *            (expand payment_intent.latest_charge), GET /v1/balance (401 for a key that is not sk_/rk_ + test|live),
 *            POST /v1/refunds (amount ≤ what is left)            https://docs.stripe.com/api
 *   PayPal   POST /v1/oauth2/token (Basic), POST /v2/checkout/orders (PayPal-Request-Id), GET /v2/checkout/orders/{id},
 *            POST …/capture (APPROVED → COMPLETED once; "decline" → 422 INSTRUMENT_DECLINED), POST
 *            /v2/payments/captures/{id}/refund                    https://developer.paypal.com/docs/api/orders/v2/
 *   ZarinPal POST /pg/v4/payment/request.json, inquiry.json (IN_BANK, PAID, VERIFIED, FAILED, REVERSED), verify.json
 *            (100, then 101; -50 another amount; -51 not paid)  https://www.zarinpal.com/docs/paymentGateway/
 * A "down" switch answers 503 for one provider.
 *
 * State: the option efb_test_payout_fake (unit tests: the test database's options; the e2e site: wp_options), and on the
 * e2e site a hosted page per provider (REST efb-e2e/v1/payout-checkout?gw=&id=) with Pay / Fail / Cancel buttons that
 * set the fake's state and send the visitor back as the provider does.
 */

class Efb_Test_Payout_Fake {

	const OPTION = 'efb_test_payout_fake';

	public static function register() {
		add_filter( 'pre_http_request', array( __CLASS__, 'http' ), 10, 3 );
	}

	public static function state() {
		$s = get_option( self::OPTION, array() );
		$s = is_array( $s ) ? $s : array();
		return $s + array( 'stripe' => array(), 'paypal' => array(), 'zarinpal' => array(), 'idem' => array(), 'down' => array(), 'log' => array(), 'base' => '' );
	}

	public static function save( array $s ) { update_option( self::OPTION, $s, false ); }

	public static function reset( array $extra = array() ) { self::save( array_merge( array( 'stripe' => array(), 'paypal' => array(), 'zarinpal' => array(), 'idem' => array(), 'down' => array(), 'log' => array(), 'base' => '' ), $extra ) ); }

	/** Keys of the right shape, assembled here (never a real key). */
	public static function stripe_key( $mode = 'test' ) { return 'sk_' . ( $mode === 'live' ? 'live' : 'test' ) . '_' . str_repeat( 'EfbFake', 4 ); }
	public static function paypal_id() { return 'AEfbFake' . str_repeat( 'Client', 3 ); }
	public static function paypal_secret() { return 'EEfbFake' . str_repeat( 'Secret', 3 ); }
	public static function zarinpal_merchant() { return '00000000-efbf-4ake-8000-' . str_repeat( '0', 12 ); }

	/** Sets a fake payment's state: stripe {status, payment_status, pi_status}, paypal {status, capture, decline}, zarinpal {status, paid_amount}. */
	public static function update( $gw, $id, array $fields ) {
		$s = self::state();
		if ( ! isset( $s[ $gw ][ $id ] ) ) return false;
		foreach ( $fields as $k => $v ) $s[ $gw ][ $id ][ $k ] = $v;
		self::save( $s );
		return true;
	}

	public static function get( $gw, $id ) { $s = self::state(); return isset( $s[ $gw ][ $id ] ) ? $s[ $gw ][ $id ] : null; }

	public static function last_id( $gw ) { $s = self::state(); $k = array_keys( $s[ $gw ] ); return $k ? end( $k ) : ''; }

	public static function count( $gw, $method, $part ) {
		return count( array_filter( self::state()['log'], function ( $l ) use ( $gw, $method, $part ) { return $l['gw'] === $gw && $l['method'] === $method && strpos( $l['path'], $part ) !== false; } ) );
	}

	private static function answer( $code, $body ) {
		return array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => is_string( $body ) ? $body : json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}

	private static function id( $prefix, $n, $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' ) {
		$o = $prefix;
		for ( $i = 0; $i < $n; $i++ ) $o .= $chars[ mt_rand( 0, strlen( $chars ) - 1 ) ];
		return $o;
	}

	private static function page( $gw, $id ) {
		$base = self::state()['base'];
		if ( $base === '' ) return array( 'stripe' => 'https://checkout.stripe.com/c/pay/' . $id, 'paypal' => 'https://www.sandbox.paypal.com/checkoutnow?token=' . $id, 'zarinpal' => 'https://sandbox.zarinpal.com/pg/StartPay/' . $id )[ $gw ];
		return $base . ( strpos( $base, '?' ) === false ? '?' : '&' ) . 'gw=' . $gw . '&id=' . rawurlencode( $id );
	}

	public static function http( $pre, $args, $url ) {
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		$gw = $host === 'api.stripe.com' ? 'stripe' : ( in_array( $host, array( 'api-m.paypal.com', 'api-m.sandbox.paypal.com' ), true ) ? 'paypal' : ( in_array( $host, array( 'payment.zarinpal.com', 'sandbox.zarinpal.com' ), true ) ? 'zarinpal' : '' ) );
		if ( $gw === '' ) return $pre;
		$s = self::state();
		$method = strtoupper( isset( $args['method'] ) ? $args['method'] : 'GET' );
		$path = (string) parse_url( $url, PHP_URL_PATH );
		$h = array_change_key_case( isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array(), CASE_LOWER );
		$raw = isset( $args['body'] ) && is_string( $args['body'] ) ? $args['body'] : '';
		$s['log'][] = array( 'gw' => $gw, 'method' => $method, 'path' => $path, 'host' => $host, 'idem' => (string) ( $h['idempotency-key'] ?? $h['paypal-request-id'] ?? '' ), 'raw' => $raw, 'query' => (string) parse_url( $url, PHP_URL_QUERY ) );
		$s['log'] = array_slice( $s['log'], -80 );
		self::save( $s );
		if ( ! empty( $s['down'][ $gw ] ) ) return self::answer( 503, array( 'error' => array( 'message' => 'Service unavailable (fake)' ) ) );
		$fn = 'http_' . $gw;
		return self::$fn( $method, $path, $h, $raw, $url );
	}

	/* ------------------------------------------------------------------ Stripe */

	private static function http_stripe( $method, $path, $h, $raw, $url ) {
		$auth = preg_replace( '/^Bearer\s+/', '', (string) ( $h['authorization'] ?? '' ) );
		if ( ! preg_match( '/^(sk|rk)_(test|live)_[A-Za-z0-9]{10,}$/', $auth, $km ) || strpos( $auth, 'REVOKED' ) !== false ) return self::answer( 401, array( 'error' => array( 'type' => 'invalid_request_error', 'message' => 'Invalid API Key provided: ' . substr( $auth, 0, 8 ) . '****' ) ) );
		$live = $km[2] === 'live';
		$s = self::state();
		if ( $method === 'GET' && $path === '/v1/balance' ) return self::answer( 200, array( 'object' => 'balance', 'livemode' => $live, 'available' => array() ) );
		if ( $method === 'POST' && $path === '/v1/checkout/sessions' ) {
			parse_str( $raw, $p );
			$ik = (string) ( $h['idempotency-key'] ?? '' );
			if ( $ik !== '' && isset( $s['idem'][ 'stripe|' . $ik ] ) ) return self::answer( 200, self::stripe_session( $s['stripe'][ $s['idem'][ 'stripe|' . $ik ] ] ) );
			$li = $p['line_items'][0] ?? array();
			$id = self::id( 'cs_' . ( $live ? 'live' : 'test' ) . '_', 24 );
			$s['stripe'][ $id ] = array( 'id' => $id, 'livemode' => $live, 'amount' => (int) ( $li['price_data']['unit_amount'] ?? 0 ), 'currency' => (string) ( $li['price_data']['currency'] ?? '' ), 'name' => (string) ( $li['price_data']['product_data']['name'] ?? '' ),
				'metadata' => (array) ( $p['metadata'] ?? array() ), 'status' => 'open', 'payment_status' => 'unpaid', 'pi' => self::id( 'pi_', 24 ), 'pi_status' => 'requires_payment_method', 'refunded' => 0,
				'success_url' => (string) ( $p['success_url'] ?? '' ), 'cancel_url' => (string) ( $p['cancel_url'] ?? '' ), 'email' => (string) ( $p['customer_email'] ?? '' ), 'request' => $p );
			if ( $ik !== '' ) $s['idem'][ 'stripe|' . $ik ] = $id;
			self::save( $s );
			return self::answer( 200, self::stripe_session( $s['stripe'][ $id ] ) );
		}
		if ( $method === 'GET' && preg_match( '#^/v1/checkout/sessions/(cs_[A-Za-z0-9_]+)$#', $path, $m ) ) {
			if ( ! isset( $s['stripe'][ $m[1] ] ) ) return self::answer( 404, array( 'error' => array( 'type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'No such checkout.session' ) ) );
			$expand = strpos( rawurldecode( (string) parse_url( $url, PHP_URL_QUERY ) ), 'payment_intent.latest_charge' ) !== false;
			return self::answer( 200, self::stripe_session( $s['stripe'][ $m[1] ], $expand ) );
		}
		if ( $method === 'POST' && $path === '/v1/refunds' ) {
			parse_str( $raw, $p );
			foreach ( $s['stripe'] as $id => $c ) {
				if ( $c['pi'] !== ( $p['payment_intent'] ?? '' ) ) continue;
				$amt = (int) ( $p['amount'] ?? ( $c['amount'] - $c['refunded'] ) );
				if ( $c['payment_status'] !== 'paid' || $amt <= 0 || $amt > $c['amount'] - $c['refunded'] ) return self::answer( 400, array( 'error' => array( 'type' => 'invalid_request_error', 'code' => 'charge_already_refunded', 'message' => 'Refund amount is greater than the unrefunded amount' ) ) );
				$s['stripe'][ $id ]['refunded'] += $amt;
				self::save( $s );
				return self::answer( 200, array( 'id' => self::id( 're_', 24 ), 'object' => 'refund', 'amount' => $amt, 'payment_intent' => $c['pi'], 'status' => 'succeeded' ) );
			}
			return self::answer( 404, array( 'error' => array( 'type' => 'invalid_request_error', 'message' => 'No such payment_intent' ) ) );
		}
		return self::answer( 404, array( 'error' => array( 'message' => 'Unrecognized request URL (fake)' ) ) );
	}

	private static function stripe_session( array $c, $expand = false ) {
		$pi = array( 'id' => $c['pi'], 'object' => 'payment_intent', 'status' => $c['pi_status'], 'amount' => $c['amount'],
			'latest_charge' => $c['payment_status'] === 'paid' ? array( 'id' => 'ch_' . substr( $c['pi'], 3 ), 'object' => 'charge', 'amount' => $c['amount'], 'amount_refunded' => $c['refunded'], 'created' => 1795000000 ) : null );
		if ( $c['pi_status'] === 'requires_payment_method' && $c['payment_status'] === 'unpaid' && $c['status'] === 'complete' ) $pi['last_payment_error'] = array( 'code' => 'card_declined', 'message' => 'Your card was declined.' );
		return array( 'id' => $c['id'], 'object' => 'checkout.session', 'livemode' => $c['livemode'], 'mode' => 'payment', 'amount_total' => $c['amount'], 'currency' => $c['currency'], 'status' => $c['status'], 'payment_status' => $c['payment_status'],
			'metadata' => $c['metadata'], 'client_reference_id' => $c['metadata']['efb_uid'] ?? null, 'url' => $c['status'] === 'open' ? self::page( 'stripe', $c['id'] ) : null, 'payment_method_types' => array( 'card' ),
			'payment_intent' => $expand ? $pi : $c['pi'] );
	}

	/* ------------------------------------------------------------------ PayPal */

	private static function http_paypal( $method, $path, $h, $raw, $url ) {
		$s = self::state();
		if ( $method === 'POST' && $path === '/v1/oauth2/token' ) {
			$basic = base64_decode( preg_replace( '/^Basic\s+/', '', (string) ( $h['authorization'] ?? '' ) ) );
			if ( $basic !== self::paypal_id() . ':' . self::paypal_secret() ) return self::answer( 401, array( 'error' => 'invalid_client', 'error_description' => 'Client Authentication failed' ) );
			return self::answer( 200, array( 'access_token' => 'A21EfbFakeToken' . md5( $basic ), 'token_type' => 'Bearer', 'expires_in' => 32400 ) );
		}
		if ( strpos( (string) ( $h['authorization'] ?? '' ), 'Bearer A21EfbFakeToken' ) !== 0 ) return self::answer( 401, array( 'name' => 'AUTHENTICATION_FAILURE', 'message' => 'Authentication failed due to invalid authentication credentials or a missing Authorization header.' ) );
		if ( $method === 'POST' && $path === '/v2/checkout/orders' ) {
			$b = json_decode( $raw, true );
			$rid = (string) ( $h['paypal-request-id'] ?? '' );
			if ( $rid !== '' && isset( $s['idem'][ 'paypal|' . $rid ] ) ) return self::answer( 200, self::paypal_order( $s['paypal'][ $s['idem'][ 'paypal|' . $rid ] ] ) );
			$pu = $b['purchase_units'][0] ?? array();
			$id = self::id( '', 17, 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789' );
			$s['paypal'][ $id ] = array( 'id' => $id, 'status' => 'PAYER_ACTION_REQUIRED', 'custom_id' => (string) ( $pu['custom_id'] ?? '' ), 'amount' => (array) ( $pu['amount'] ?? array() ), 'capture' => '', 'capture_id' => '', 'refunds' => array(), 'decline' => 0,
				'return_url' => (string) ( $b['payment_source']['paypal']['experience_context']['return_url'] ?? '' ), 'cancel_url' => (string) ( $b['payment_source']['paypal']['experience_context']['cancel_url'] ?? '' ), 'request' => $b, 'live' => strpos( $url, 'sandbox' ) === false );
			if ( $rid !== '' ) $s['idem'][ 'paypal|' . $rid ] = $id;
			self::save( $s );
			return self::answer( 200, self::paypal_order( $s['paypal'][ $id ] ) );
		}
		if ( preg_match( '#^/v2/checkout/orders/([A-Z0-9]+)(/capture)?$#', $path, $m ) ) {
			if ( ! isset( $s['paypal'][ $m[1] ] ) ) return self::answer( 404, array( 'name' => 'RESOURCE_NOT_FOUND', 'details' => array( array( 'issue' => 'INVALID_RESOURCE_ID' ) ) ) );
			$o = $s['paypal'][ $m[1] ];
			if ( $method === 'POST' && ! empty( $m[2] ) ) {
				if ( $o['status'] === 'COMPLETED' ) return self::answer( 422, array( 'name' => 'UNPROCESSABLE_ENTITY', 'details' => array( array( 'issue' => 'ORDER_ALREADY_CAPTURED' ) ) ) );
				if ( $o['status'] !== 'APPROVED' ) return self::answer( 422, array( 'name' => 'UNPROCESSABLE_ENTITY', 'details' => array( array( 'issue' => 'ORDER_NOT_APPROVED' ) ) ) );
				if ( ! empty( $o['decline'] ) ) return self::answer( 422, array( 'name' => 'UNPROCESSABLE_ENTITY', 'details' => array( array( 'issue' => 'INSTRUMENT_DECLINED', 'description' => 'The instrument presented was declined.' ) ) ) );
				$o['status'] = 'COMPLETED'; $o['capture'] = 'COMPLETED'; $o['capture_id'] = self::id( '', 17, 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789' );
				$s['paypal'][ $m[1] ] = $o;
				self::save( $s );
				return self::answer( 201, self::paypal_order( $o ) );
			}
			return self::answer( 200, self::paypal_order( $o ) );
		}
		if ( $method === 'POST' && preg_match( '#^/v2/payments/captures/([A-Z0-9]+)/refund$#', $path, $m ) ) {
			foreach ( $s['paypal'] as $id => $o ) {
				if ( $o['capture_id'] !== $m[1] ) continue;
				$b = json_decode( $raw, true );
				$v = (string) ( $b['amount']['value'] ?? $o['amount']['value'] );
				$done = array_sum( array_map( function ( $x ) { return (float) $x['amount']['value']; }, $o['refunds'] ) );
				if ( (float) $v + $done > (float) $o['amount']['value'] + 0.0001 ) return self::answer( 422, array( 'name' => 'UNPROCESSABLE_ENTITY', 'details' => array( array( 'issue' => 'REFUND_AMOUNT_EXCEEDED' ) ) ) );
				$rf = array( 'id' => self::id( '', 17, 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789' ), 'status' => 'COMPLETED', 'amount' => array( 'value' => $v, 'currency_code' => $o['amount']['currency_code'] ) );
				$s['paypal'][ $id ]['refunds'][] = $rf;
				$s['paypal'][ $id ]['capture'] = (float) $v + $done >= (float) $o['amount']['value'] - 0.0001 ? 'REFUNDED' : 'PARTIALLY_REFUNDED';
				self::save( $s );
				return self::answer( 201, $rf );
			}
			return self::answer( 404, array( 'name' => 'RESOURCE_NOT_FOUND' ) );
		}
		return self::answer( 404, array( 'name' => 'NOT_FOUND' ) );
	}

	private static function paypal_order( array $o ) {
		$pu = array( 'reference_id' => $o['custom_id'], 'custom_id' => $o['custom_id'], 'amount' => $o['amount'] );
		if ( $o['capture_id'] !== '' ) $pu['payments'] = array( 'captures' => array( array( 'id' => $o['capture_id'], 'status' => $o['capture'], 'amount' => $o['amount'], 'create_time' => '2026-11-18T10:00:00Z' ) ), 'refunds' => $o['refunds'] );
		return array( 'id' => $o['id'], 'status' => $o['status'], 'intent' => 'CAPTURE', 'purchase_units' => array( $pu ),
			'links' => array( array( 'href' => 'https://api-m.sandbox.paypal.com/v2/checkout/orders/' . $o['id'], 'rel' => 'self', 'method' => 'GET' ), array( 'href' => self::page( 'paypal', $o['id'] ), 'rel' => 'payer-action', 'method' => 'GET' ) ) );
	}

	/* ------------------------------------------------------------------ ZarinPal */

	private static function http_zarinpal( $method, $path, $h, $raw, $url ) {
		$b = json_decode( $raw, true );
		$b = is_array( $b ) ? $b : array();
		$s = self::state();
		if ( (string) ( $b['merchant_id'] ?? '' ) !== self::zarinpal_merchant() ) return self::answer( 200, array( 'data' => array(), 'errors' => array( 'code' => -11, 'message' => 'Terminal is not valid, please check merchant_id or ip address.', 'validations' => array() ) ) );
		if ( $path === '/pg/v4/payment/request.json' ) {
			if ( (int) ( $b['amount'] ?? 0 ) < 1000 ) return self::answer( 200, array( 'data' => array(), 'errors' => array( 'code' => -9, 'message' => 'The input params invalid, validation error.', 'validations' => array( array( 'amount' => 'The amount must be at least 1000.' ) ) ) ) );
			$id = self::id( 'A', 35, '0123456789' );
			$s['zarinpal'][ $id ] = array( 'id' => $id, 'amount' => (int) $b['amount'], 'status' => 'IN_BANK', 'verified' => 0, 'callback' => (string) ( $b['callback_url'] ?? '' ), 'request' => $b, 'live' => strpos( $url, 'sandbox' ) === false );
			self::save( $s );
			return self::answer( 200, array( 'data' => array( 'code' => 100, 'message' => 'Success', 'authority' => $id, 'fee_type' => 'Merchant', 'fee' => 100 ), 'errors' => array() ) );
		}
		$a = (string) ( $b['authority'] ?? '' );
		if ( ! isset( $s['zarinpal'][ $a ] ) ) return self::answer( 200, array( 'data' => array(), 'errors' => array( 'code' => -54, 'message' => 'Invalid authority.', 'validations' => array() ) ) );
		$z = $s['zarinpal'][ $a ];
		if ( $path === '/pg/v4/payment/inquiry.json' ) return self::answer( 200, array( 'data' => array( 'status' => $z['verified'] && $z['status'] === 'PAID' ? 'VERIFIED' : $z['status'], 'code' => 100, 'message' => 'Success' ), 'errors' => array() ) );
		if ( $path === '/pg/v4/payment/verify.json' ) {
			if ( ! in_array( $z['status'], array( 'PAID' ), true ) ) return self::answer( 200, array( 'data' => array(), 'errors' => array( 'code' => -51, 'message' => 'Session is not valid, session is not active paid try.', 'validations' => array() ) ) );
			if ( (int) ( $b['amount'] ?? 0 ) !== $z['amount'] ) return self::answer( 200, array( 'data' => array(), 'errors' => array( 'code' => -50, 'message' => 'Session is not valid, amounts values is not the same.', 'validations' => array() ) ) );
			$code = $z['verified'] ? 101 : 100;
			$s['zarinpal'][ $a ]['verified'] = 1;
			self::save( $s );
			return self::answer( 200, array( 'data' => array( 'code' => $code, 'message' => $code === 100 ? 'Paid' : 'Verified', 'card_hash' => md5( $a ), 'card_pan' => '502229******5995', 'ref_id' => 201 + crc32( $a ) % 100000, 'fee_type' => 'Merchant', 'fee' => 100 ), 'errors' => array() ) );
		}
		return self::answer( 404, array( 'errors' => array( 'code' => -1, 'message' => 'not found (fake)' ) ) );
	}

	/* ------------------------------------------------------------------ e2e: the hosted pages */

	public static function routes() {
		register_rest_route( 'efb-e2e/v1', 'payout-checkout', array( 'methods' => array( 'GET', 'POST' ), 'callback' => array( __CLASS__, 'checkout' ), 'permission_callback' => '__return_true' ) );
	}

	/** Pay / Fail / Cancel, then back to the site the way each provider sends the visitor back. */
	public static function checkout( $r ) {
		$gw = (string) $r->get_param( 'gw' );
		$id = (string) $r->get_param( 'id' );
		$c = in_array( $gw, array( 'stripe', 'paypal', 'zarinpal' ), true ) ? self::get( $gw, $id ) : null;
		if ( ! $c ) return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		if ( $r->get_method() === 'POST' ) {
			$out = (string) $r->get_param( 'outcome' );
			$to = '';
			if ( $gw === 'stripe' ) {
				if ( $out === 'pay' ) self::update( 'stripe', $id, array( 'status' => 'complete', 'payment_status' => 'paid', 'pi_status' => 'succeeded' ) );
				// A declined payment ends the session unpaid (as an async method that failed: checkout.session.async_payment_failed).
				if ( $out === 'fail' ) self::update( 'stripe', $id, array( 'status' => 'complete', 'payment_status' => 'unpaid', 'pi_status' => 'requires_payment_method', 'last_error' => 'card_declined' ) );
				$to = $out === 'cancel' ? $c['cancel_url'] : str_replace( '{CHECKOUT_SESSION_ID}', $id, $c['success_url'] );
			} elseif ( $gw === 'paypal' ) {
				if ( $out === 'pay' ) self::update( 'paypal', $id, array( 'status' => 'APPROVED', 'decline' => 0 ) );
				if ( $out === 'fail' ) self::update( 'paypal', $id, array( 'status' => 'APPROVED', 'decline' => 1 ) );
				$to = $out === 'cancel' ? $c['cancel_url'] : add_query_arg( array( 'token' => $id, 'PayerID' => 'EFBFAKEPAYER' ), $c['return_url'] );
			} else {
				if ( $out === 'pay' ) self::update( 'zarinpal', $id, array( 'status' => 'PAID' ) );
				if ( $out === 'fail' || $out === 'cancel' ) self::update( 'zarinpal', $id, array( 'status' => 'FAILED' ) );
				$to = add_query_arg( array( 'Authority' => $id, 'Status' => $out === 'pay' ? 'OK' : 'NOK' ), $c['callback'] );
			}
			$res = new WP_REST_Response( null, 303 );
			$res->header( 'Location', $to );
			return $res;
		}
		$amount = $gw === 'stripe' ? ( $c['amount'] . ' ' . strtoupper( $c['currency'] ) . ' (minor)' ) : ( $gw === 'paypal' ? $c['amount']['value'] . ' ' . $c['amount']['currency_code'] : $c['amount'] . ' IRR' );
		$names = array( 'stripe' => 'Stripe Checkout', 'paypal' => 'PayPal', 'zarinpal' => 'ZarinPal' );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Fake ' . esc_html( $names[ $gw ] ) . '</title></head><body>'
			. '<h1>Fake ' . esc_html( $names[ $gw ] ) . '</h1><p>Amount: <strong id="amount">' . esc_html( $amount ) . '</strong></p>'
			. '<form method="post" action="' . esc_url( rest_url( 'efb-e2e/v1/payout-checkout' ) ) . '"><input type="hidden" name="gw" value="' . esc_attr( $gw ) . '"><input type="hidden" name="id" value="' . esc_attr( $id ) . '">'
			. '<button id="pay" name="outcome" value="pay">Pay</button> <button id="fail" name="outcome" value="fail">Fail</button> <button id="cancel" name="outcome" value="cancel">Cancel</button></form></body></html>';
		exit;
	}
}
