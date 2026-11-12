<?php
/**
 * Not a test. A fake api.xendit.co for the Xendit add-on tests (tests/test-addon-xendit-*.php,
 * through tests/addon-xendit-lib.php) and for the real-WordPress scenario "xendit" of
 * tests/e2e-wp/run.js (loaded by the mu-plugin efb-e2e-xendit.php while the option
 * efb_e2e_xendit_fake is set). Nothing leaves the machine and no real key is used: the fake keys
 * are assembled at run time from obviously fake parts.
 *
 *  - pre_http_request answers https://api.xendit.co/… with the shapes of Xendit's documented
 *    responses (tests/fixtures/payments/xendit): POST /sessions, GET /sessions/{id},
 *    POST /sessions/{id}/cancel, GET /v3/payment_requests/{id}, POST /v3/refunds; HTTP Basic with
 *    the fake key of a mode (a session belongs to the mode of the key that made it); the rules the
 *    reference states (required fields, currencies, IDR / VND whole amounts, expires_at ≥ 10
 *    minutes ahead, return URLs https, known channel codes, api-version on v3, one reference_id
 *    per refund, QRIS full refunds only); 429 with the Rate-Limit-* headers on demand;
 *  - state (sessions, refunds, request log without keys) lives in the option efb_e2e_xendit_state;
 *  - the hosted page (payment_link_url https://dev.xen.to/<token>) is served on the site at
 *    /wp-json/efb-e2e/v1/xendit-checkout?token=… with Pay, Cancel and Back buttons.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Efb_Test_Xendit_Fake {

	const OPTION = 'efb_e2e_xendit_state';
	const CURRENCIES = array( 'IDR' => 0, 'VND' => 0, 'PHP' => 2, 'THB' => 2, 'MYR' => 2, 'SGD' => 2, 'USD' => 2 );
	const CODES = array( 'QRIS' => array( 'IDR' ), 'DANA' => array( 'IDR' ), 'OVO' => array( 'IDR' ), 'GOPAY' => array( 'IDR' ), 'LINKAJA' => array( 'IDR' ),
		'SHOPEEPAY' => array( 'IDR', 'PHP', 'MYR', 'VND', 'THB' ), 'BCA_VIRTUAL_ACCOUNT' => array( 'IDR' ), 'BNI_VIRTUAL_ACCOUNT' => array( 'IDR' ),
		'BRI_VIRTUAL_ACCOUNT' => array( 'IDR' ), 'MANDIRI_VIRTUAL_ACCOUNT' => array( 'IDR' ), 'PERMATA_VIRTUAL_ACCOUNT' => array( 'IDR' ),
		'GCASH' => array( 'PHP' ), 'PAYMAYA' => array( 'PHP' ), 'GRABPAY' => array( 'PHP', 'MYR' ), 'QRPH' => array( 'PHP' ), 'PROMPTPAY' => array( 'THB' ),
		'TRUEMONEY' => array( 'THB' ), 'MOMO' => array( 'VND' ), 'ZALOPAY' => array( 'VND' ), 'TOUCHNGO' => array( 'MYR' ),
		'CARDS' => array( 'IDR', 'PHP', 'MYR', 'THB', 'VND', 'SGD', 'USD' ) );

	/** In-memory state for the unit tests (no option round trip needed, but kept the same). */
	public static $memory = null;

	/** Fake secret keys of the right shape, assembled here (never a real key). */
	public static function key( $mode ) {
		return 'xnd' . '_' . ( $mode === 'live' ? 'production' : 'development' ) . '_' . ( $mode === 'live' ? 'FakeLiveKeyForEfbTests' : 'FakeTestKeyForEfbTests' ) . str_repeat( $mode === 'live' ? '2' : '1', 8 );
	}

	/** Fake webhook verification tokens (dashboard tokens are plain random strings). */
	public static function token( $mode ) {
		return 'efbFakeCallbackToken' . ( $mode === 'live' ? 'Live' : 'Test' ) . '000000000000';
	}

	public static function register() {
		add_filter( 'pre_http_request', array( __CLASS__, 'http' ), 10, 3 );
		if ( function_exists( 'add_action' ) ) add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function fixture( $name ) {
		$f = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/payments/xendit/' . $name . '.json' ), true );
		return is_array( $f ) ? $f['response'] : array( 'code' => 500, 'headers' => array(), 'body' => array() );
	}

	public static function now() {
		return class_exists( '\Emsfb\Payments\Store' ) ? \Emsfb\Payments\Store::time() : time();
	}

	public static function state() {
		$s = self::$memory !== null ? self::$memory : get_option( self::OPTION, array() );
		return ( is_array( $s ) ? $s : array() ) + array( 'sessions' => array(), 'refunds' => array(), 'refs' => array(), 'log' => array(), 'n' => 0,
			'down' => false, 'limit_next' => 0, 'allow_http' => false, 'refund_status' => 'SUCCEEDED', 'create_amount' => null, 'create_status' => null );
	}

	public static function save( array $s ) {
		if ( self::$memory !== null ) { self::$memory = $s; return; }
		update_option( self::OPTION, $s, false );
	}

	public static function reset( $memory = false ) {
		self::$memory = $memory ? array() : null;
		if ( ! $memory && function_exists( 'delete_option' ) ) delete_option( self::OPTION );
	}

	/** Change one setting of the fake: down, limit_next, allow_http, refund_status, create_amount, create_status. */
	public static function set_opt( $k, $v ) {
		$s = self::state();
		$s[ $k ] = $v;
		self::save( $s );
	}

	private static function answer( $code, $body, $headers = array() ) {
		return array( 'headers' => array_merge( array( 'content-type' => 'application/json', 'rate-limit-limit' => '60', 'rate-limit-remaining' => '42', 'rate-limit-reset' => '30.5' ), $headers ),
			'body' => is_string( $body ) ? $body : json_encode( $body, JSON_UNESCAPED_SLASHES ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}

	private static function err( $code, $error_code, $message ) {
		return self::answer( $code, array( 'error_code' => $error_code, 'message' => $message ) );
	}

	private static function hex( $n ) {
		return substr( hash( 'sha256', uniqid( '', true ) . $n . mt_rand() ), 0, $n );
	}

	private static function uuid() {
		$h = self::hex( 32 );
		return substr( $h, 0, 8 ) . '-' . substr( $h, 8, 4 ) . '-' . substr( $h, 12, 4 ) . '-' . substr( $h, 16, 4 ) . '-' . substr( $h, 20, 12 );
	}

	/** The raw JSON number of "amount" in a request body (to check decimals exactly as sent). */
	public static function raw_amount( $raw ) {
		return preg_match( '/"amount":(-?[0-9.eE+]+)/', (string) $raw, $m ) ? $m[1] : '';
	}

	private static function number( $raw ) {
		return strpos( $raw, '.' ) !== false ? (float) $raw : (int) $raw;
	}

	public static function http( $pre, $args, $url ) {
		$p = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
		if ( ! is_array( $p ) || ( isset( $p['host'] ) ? $p['host'] : '' ) !== 'api.xendit.co' ) return $pre;
		$s = self::state();
		$method = isset( $args['method'] ) ? strtoupper( $args['method'] ) : 'GET';
		$path = isset( $p['path'] ) ? (string) $p['path'] : '';
		$raw = isset( $args['body'] ) && is_string( $args['body'] ) ? $args['body'] : '';
		$h = array();
		foreach ( (array) ( isset( $args['headers'] ) ? $args['headers'] : array() ) as $k => $v ) $h[ strtolower( $k ) ] = $v;
		$auth = isset( $h['authorization'] ) ? (string) $h['authorization'] : '';
		$user = strpos( $auth, 'Basic ' ) === 0 ? (string) base64_decode( substr( $auth, 6 ) ) : '';
		$mode = $user === self::key( 'live' ) . ':' ? 'live' : ( $user === self::key( 'test' ) . ':' ? 'test' : '' );
		$s['log'][] = array( 'method' => $method, 'path' => $path, 'key_mode' => $mode, 'api_version' => isset( $h['api-version'] ) ? $h['api-version'] : '', 'raw' => $raw, 'headers' => array_diff_key( $h, array( 'authorization' => 1 ) ) );
		$s['log'] = array_slice( $s['log'], -120 );
		if ( ! empty( $s['down'] ) ) { self::save( $s ); return new WP_Error( 'http_request_failed', 'xendit down' ); }
		if ( (int) $s['limit_next'] > 0 ) {
			$s['limit_next'] = (int) $s['limit_next'] - 1;
			self::save( $s );
			$f = self::fixture( 'rate-limit-429' );
			return self::answer( 429, $f['body'], $f['headers'] );
		}
		if ( $mode === '' ) { self::save( $s ); $f = self::fixture( 'error-401' ); return self::answer( 401, $f['body'] ); }
		$b = json_decode( $raw, true );
		$out = null;
		if ( $method === 'POST' && $path === '/sessions' ) {
			$out = self::create( $s, $b, $raw, $mode );
		} elseif ( $method === 'GET' && preg_match( '#^/sessions/([^/]+)$#', $path, $m ) ) {
			$out = isset( $s['sessions'][ $m[1] ] ) && $s['sessions'][ $m[1] ]['_mode'] === $mode ? self::answer( 200, self::public_session( $s['sessions'][ $m[1] ] ) ) : self::answer( 404, self::fixture( 'error-404-session' )['body'] );
		} elseif ( $method === 'POST' && preg_match( '#^/sessions/([^/]+)/cancel$#', $path, $m ) ) {
			if ( ! isset( $s['sessions'][ $m[1] ] ) || $s['sessions'][ $m[1] ]['_mode'] !== $mode ) $out = self::answer( 404, self::fixture( 'error-404-session' )['body'] );
			elseif ( $s['sessions'][ $m[1] ]['status'] !== 'ACTIVE' ) $out = self::err( 422, 'INVALID_STATUS', 'Only an ACTIVE session can be canceled' );
			else {
				$s['sessions'][ $m[1] ]['status'] = 'CANCELED';
				$s['sessions'][ $m[1] ]['updated'] = gmdate( 'Y-m-d\TH:i:s\Z', self::now() );
				$out = self::answer( 200, self::public_session( $s['sessions'][ $m[1] ] ) );
			}
		} elseif ( $method === 'GET' && preg_match( '#^/v3/payment_requests/(pr-[0-9a-f-]+)$#', $path, $m ) ) {
			if ( ( isset( $h['api-version'] ) ? $h['api-version'] : '' ) !== '2024-11-11' ) $out = self::err( 400, 'API_VALIDATION_ERROR', 'api-version header is required' );
			else {
				$out = self::answer( 404, array( 'error_code' => 'DATA_NOT_FOUND', 'message' => 'Payment request not found' ) );
				foreach ( $s['sessions'] as $x ) {
					if ( isset( $x['payment_request_id'] ) && $x['payment_request_id'] === $m[1] && $x['_mode'] === $mode ) {
						$pr = self::fixture( 'payment-request-get' )['body'];
						$pr = array_merge( $pr, array( 'payment_request_id' => $m[1], 'reference_id' => $x['reference_id'], 'currency' => $x['currency'], 'country' => $x['country'], 'request_amount' => $x['amount'], 'channel_code' => $x['_channel'] ) );
						$out = self::answer( 200, $pr );
					}
				}
			}
		} elseif ( $method === 'POST' && $path === '/v3/refunds' ) {
			$out = self::refund( $s, $b, $raw, $h, $mode );
		} else {
			$out = self::err( 404, 'NOT_FOUND', 'not found' );
		}
		self::save( $s );
		return $out;
	}

	private static function create( array &$s, $b, $raw, $mode ) {
		if ( ! is_array( $b ) ) return self::err( 400, 'API_VALIDATION_ERROR', 'body is not JSON' );
		foreach ( array( 'reference_id', 'currency', 'amount', 'country', 'session_type', 'mode' ) as $k ) {
			if ( ! isset( $b[ $k ] ) || $b[ $k ] === '' ) return self::err( 400, 'API_VALIDATION_ERROR', $k . ' is required' );
		}
		if ( ! isset( self::CURRENCIES[ $b['currency'] ] ) ) return self::err( 400, 'INVALID_CURRENCY', 'currency not supported' );
		if ( ! in_array( $b['country'], array( 'ID', 'PH', 'VN', 'TH', 'SG', 'MY' ), true ) ) return self::err( 400, 'INVALID_COUNTRY', 'country not supported' );
		if ( ! in_array( $b['session_type'], array( 'PAY', 'SAVE', 'SUBSCRIPTION' ), true ) || ! in_array( $b['mode'], array( 'PAYMENT_LINK', 'COMPONENTS' ), true ) ) return self::err( 400, 'API_VALIDATION_ERROR', 'session_type / mode' );
		$amt = self::raw_amount( $raw );
		$dec = self::CURRENCIES[ $b['currency'] ];
		if ( ! preg_match( $dec === 0 ? '/^[0-9]+$/' : '/^[0-9]+(\.[0-9]{1,2})?$/', $amt ) ) return self::err( 400, 'INVALID_AMOUNT', 'The amount must also respect the decimal places allowed for the given currency' );
		$exp = isset( $b['expires_at'] ) ? strtotime( $b['expires_at'] ) : self::now() + 1800;
		if ( ! $exp || $exp < self::now() + 600 ) return self::fixture_answer( 'error-400-expiry' );
		foreach ( array( 'success_return_url', 'cancel_return_url' ) as $k ) {
			if ( isset( $b[ $k ] ) && ! preg_match( empty( $s['allow_http'] ) ? '#^https://#' : '#^https?://#', (string) $b[ $k ] ) ) return self::err( 400, 'INVALID_URL', $k . ' must be HTTPS' );
		}
		if ( isset( $b['allowed_payment_channels'] ) ) {
			foreach ( (array) $b['allowed_payment_channels'] as $c ) {
				if ( ! isset( self::CODES[ $c ] ) || ! in_array( $b['currency'], self::CODES[ $c ], true ) ) return self::err( 400, 'INVALID_PAYMENT_CHANNEL', 'channel ' . $c . ' is not available for ' . $b['currency'] );
			}
		}
		if ( isset( $b['metadata'] ) && ( count( (array) $b['metadata'] ) > 20 || count( array_filter( (array) $b['metadata'], function ( $v ) { return strlen( (string) $v ) > 80; } ) ) ) ) return self::err( 400, 'INVALID_METADATA', 'metadata' );
		if ( isset( $b['customer'] ) && ( empty( $b['customer']['reference_id'] ) || empty( $b['customer']['individual_detail']['given_names'] ) || ( isset( $b['customer']['type'] ) ? $b['customer']['type'] : '' ) !== 'INDIVIDUAL' ) ) return self::err( 400, 'API_VALIDATION_ERROR', 'customer' );
		$s['n']++;
		$x = self::fixture( 'session-create-201' )['body'];
		$token = substr( hash( 'sha256', $b['reference_id'] . $s['n'] . uniqid( '', true ) ), 0, 8 );
		$now = gmdate( 'Y-m-d\TH:i:s\Z', self::now() );
		$x = array_merge( $x, array(
			'payment_session_id' => 'ps-' . self::hex( 24 ), 'created' => $now, 'updated' => $now, 'status' => $s['create_status'] ? $s['create_status'] : 'ACTIVE',
			'reference_id' => (string) $b['reference_id'], 'currency' => $b['currency'], 'amount' => $s['create_amount'] !== null ? $s['create_amount'] : self::number( $amt ),
			'country' => $b['country'], 'session_type' => $b['session_type'], 'mode' => $b['mode'], 'expires_at' => gmdate( 'Y-m-d\TH:i:s\Z', $exp ),
			'locale' => isset( $b['locale'] ) ? $b['locale'] : 'en', 'description' => isset( $b['description'] ) ? $b['description'] : null,
			'allowed_payment_channels' => isset( $b['allowed_payment_channels'] ) ? $b['allowed_payment_channels'] : array(),
			'metadata' => isset( $b['metadata'] ) ? $b['metadata'] : null, 'customer_id' => isset( $b['customer'] ) ? 'cust-' . self::uuid() : null,
			'success_return_url' => isset( $b['success_return_url'] ) ? $b['success_return_url'] : null, 'cancel_return_url' => isset( $b['cancel_return_url'] ) ? $b['cancel_return_url'] : null,
			'payment_link_url' => ( $mode === 'live' ? 'https://xen.to/' : 'https://dev.xen.to/' ) . $token,
		) );
		$x['_mode'] = $mode;
		$x['_token'] = $token;
		$x['_raw_amount'] = $amt;
		$x['_customer'] = isset( $b['customer'] ) ? $b['customer'] : null;
		$x['_channel'] = '';
		$s['sessions'][ $x['payment_session_id'] ] = $x;
		return self::answer( 201, self::public_session( $x ) );
	}

	private static function fixture_answer( $name ) {
		$f = self::fixture( $name );
		return self::answer( (int) $f['code'], $f['body'] );
	}

	private static function refund( array &$s, $b, $raw, array $h, $mode ) {
		if ( ( isset( $h['api-version'] ) ? $h['api-version'] : '' ) !== '2024-11-11' ) return self::err( 400, 'API_VALIDATION_ERROR', 'api-version header is required' );
		if ( ! is_array( $b ) || empty( $b['reference_id'] ) || empty( $b['payment_id'] ) || empty( $b['reason'] ) ) return self::err( 400, 'API_VALIDATION_ERROR', 'reference_id, payment_id and reason are required' );
		if ( ! in_array( $b['reason'], array( 'FRAUDULENT', 'DUPLICATE', 'REQUESTED_BY_CUSTOMER', 'CANCELLATION', 'OTHERS' ), true ) ) return self::err( 400, 'API_VALIDATION_ERROR', 'reason' );
		if ( isset( $s['refs'][ $b['reference_id'] ] ) ) return self::err( 409, 'DUPLICATE_ERROR', 'reference_id was used before' );
		$sess = null;
		foreach ( $s['sessions'] as $id => $x ) {
			if ( isset( $x['payment_id'] ) && $x['payment_id'] === $b['payment_id'] && $x['_mode'] === $mode ) $sess = $id;
		}
		if ( $sess === null ) return self::err( 404, 'DATA_NOT_FOUND', 'payment not found' );
		$x = $s['sessions'][ $sess ];
		$amt = self::raw_amount( $raw );
		$minor_full = (float) $x['amount'];
		$done = 0.0;
		foreach ( $s['refunds'] as $r ) { if ( $r['payment_id'] === $b['payment_id'] && $r['status'] !== 'FAILED' ) $done += (float) $r['amount']; }
		$want = $amt === '' ? $minor_full - $done : (float) $amt;
		if ( $want <= 0 || $want > $minor_full - $done + 0.0001 ) return self::err( 400, 'REFUND_AMOUNT_EXCEEDED', 'Refund amount exceeds the remaining amount' );
		if ( $x['_channel'] === 'QRIS' && abs( $want - $minor_full ) > 0.0001 ) { $f = self::fixture( 'refund-400-qris' ); return self::answer( 400, $f['body'] ); }
		$r = self::fixture( 'refund-200' )['body'];
		$r = array_merge( $r, array( 'refund_id' => 'rfd-' . self::uuid(), 'reference_id' => $b['reference_id'], 'payment_id' => $b['payment_id'], 'status' => $s['refund_status'],
			'amount' => $amt === '' ? $want : self::number( $amt ), 'currency' => $x['currency'], 'channel_code' => $x['_channel'], 'metadata' => isset( $b['metadata'] ) ? $b['metadata'] : null,
			'created' => gmdate( 'Y-m-d\TH:i:s\Z', self::now() ), 'updated' => gmdate( 'Y-m-d\TH:i:s\Z', self::now() ) ) );
		$r['_raw_amount'] = $amt;
		$s['refunds'][ $r['refund_id'] ] = $r;
		$s['refs'][ $b['reference_id'] ] = $r['refund_id'];
		$pub = $r;
		unset( $pub['_raw_amount'] );
		return self::answer( 200, $pub );
	}

	public static function public_session( array $x ) {
		foreach ( array_keys( $x ) as $k ) { if ( $k[0] === '_' ) unset( $x[ $k ] ); }
		return $x;
	}

	/** Xendit's side of a session: COMPLETED (with channel), EXPIRED, CANCELED, ACTIVE; optional amount / currency of the answer. */
	public static function set( $id, $status, $channel = 'QRIS', $amount = null, $currency = null ) {
		$s = self::state();
		if ( ! isset( $s['sessions'][ $id ] ) ) return false;
		$x =& $s['sessions'][ $id ];
		$x['status'] = $status;
		$x['updated'] = gmdate( 'Y-m-d\TH:i:s\Z', self::now() + mt_rand( 1, 50 ) );
		if ( $status === 'COMPLETED' ) {
			$x['_channel'] = $channel;
			$tail = substr( $x['payment_session_id'], 3 );
			$x['payment_token_id'] = 'pt-' . $tail;
			$x['payment_request_id'] = $x['latest_payment_request_id'] = 'pr-' . $tail;
			$x['payment_id'] = 'py-' . $tail;
		}
		if ( $amount !== null ) $x['amount'] = is_numeric( $amount ) ? self::number( (string) $amount ) : $amount;
		if ( $currency !== null ) $x['currency'] = $currency;
		unset( $x );
		self::save( $s );
		return true;
	}

	public static function session( $id ) {
		$s = self::state();
		return isset( $s['sessions'][ $id ] ) ? $s['sessions'][ $id ] : null;
	}

	public static function last( $method, $path_part ) {
		$log = self::state()['log'];
		for ( $i = count( $log ) - 1; $i >= 0; $i-- ) {
			if ( $log[ $i ]['method'] === $method && strpos( $log[ $i ]['path'], $path_part ) !== false ) return $log[ $i ];
		}
		return null;
	}

	/** The body Xendit would post for a session event (payment_session.completed / .expired). */
	public static function webhook_body( $id, $event ) {
		$x = self::public_session( self::session( $id ) );
		$keep = array( 'payment_session_id', 'created', 'updated', 'reference_id', 'customer_id', 'session_type', 'currency', 'amount', 'country', 'mode', 'expires_at', 'locale',
			'description', 'status', 'payment_link_url', 'payment_token_id', 'payment_request_id', 'latest_payment_request_id', 'latest_payment_token_id', 'payment_id', 'business_id' );
		$data = array_intersect_key( $x, array_flip( $keep ) );
		return json_encode( array( 'event' => $event, 'business_id' => '62440e322008e87fb29c1fd0', 'created' => gmdate( 'Y-m-d\TH:i:s\Z', self::now() ), 'data' => $data ), JSON_UNESCAPED_SLASHES );
	}

	/** The body Xendit would post for a refund event (refund.succeeded / refund.failed). */
	public static function refund_webhook_body( $rid, $event ) {
		$r = self::state()['refunds'][ $rid ];
		unset( $r['_raw_amount'], $r['refund_details'] );
		$r['status'] = $event === 'refund.succeeded' ? 'SUCCEEDED' : 'FAILED';
		if ( $event === 'refund.failed' ) $r['failure_code'] = 'INSUFFICIENT_BALANCE';
		return json_encode( array( 'event' => $event, 'business_id' => '62440e322008e87fb29c1fd0', 'created' => gmdate( 'Y-m-d\TH:i:s\Z', self::now() ), 'api_version' => 'v3', 'data' => $r ), JSON_UNESCAPED_SLASHES );
	}

	public static function by_token( $token ) {
		foreach ( self::state()['sessions'] as $x ) {
			if ( hash_equals( (string) $x['_token'], (string) $token ) ) return $x;
		}
		return null;
	}

	public static function routes() {
		register_rest_route( 'efb-e2e/v1', 'xendit-checkout', array(
			'methods'             => array( 'GET', 'POST' ),
			'callback'            => array( __CLASS__, 'checkout' ),
			'permission_callback' => '__return_true',
		) );
	}

	/** The fake hosted page: amount, currency, channels; Pay (COMPLETED → success URL), Cancel (cancel URL), Back. */
	public static function checkout( WP_REST_Request $r ) {
		$x = self::by_token( (string) $r->get_param( 'token' ) );
		if ( ! $x ) return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		if ( $r->get_method() === 'POST' ) {
			$do = (string) $r->get_param( 'do' );
			$ch = $x['allowed_payment_channels'] ? $x['allowed_payment_channels'][0] : 'QRIS';
			if ( $do === 'pay' ) self::set( $x['payment_session_id'], 'COMPLETED', $ch );
			$res = new WP_REST_Response( null, 303 );
			$res->header( 'Location', $do === 'cancel' ? $x['cancel_return_url'] : $x['success_return_url'] );
			return $res;
		}
		$action = esc_url( rest_url( 'efb-e2e/v1/xendit-checkout' ) );
		$hidden = '<input type="hidden" name="token" value="' . esc_attr( $x['_token'] ) . '">';
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Xendit (fake)</title></head><body>'
			. '<h1>Xendit checkout (fake, local test)</h1><p>Amount: <strong id="amount">' . esc_html( $x['_raw_amount'] . ' ' . $x['currency'] ) . '</strong></p>'
			. '<p>Channels: <code id="channels">' . esc_html( implode( ',', (array) $x['allowed_payment_channels'] ) ) . '</code></p>'
			. '<form method="post" action="' . $action . '">' . $hidden . '<button id="pay" name="do" value="pay">Pay</button> <button id="cancel" name="do" value="cancel">Cancel</button> <button id="back" name="do" value="back">Return to the site</button></form>'
			. '</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts
		exit;
	}
}
