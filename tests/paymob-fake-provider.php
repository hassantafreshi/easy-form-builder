<?php
/**
 * Not a test. A fake Paymob (accept / ksa / uae / oman / pakistan .paymob.com) for the Paymob add-on
 * tests: tests/addon-paymob-lib.php (unit) and the real-WordPress scenario "paymob" of
 * tests/e2e-wp (mu-plugin efb-e2e-paymob.php while the option efb_e2e_pmb_fake is set). Nothing
 * leaves the machine and no real key is used: every key is assembled here at run time.
 *
 *  - pre_http_request answers v1/intention/, api/auth/tokens, api/ecommerce/orders/transaction_inquiry,
 *    api/acceptance/transactions/{id} and api/acceptance/void_refund/refund in the shapes of
 *    Paymob's documented responses (tests/fixtures/payments/paymob);
 *  - the visitor's payment on Unified Checkout is pay(): a transaction on the intention's order;
 *  - callback() is the Transaction Processed callback Paymob would post, signed by an independent
 *    HMAC (field by field, like Paymob's own WooCommerce plugin), and redirect_query() the GET of
 *    the redirection;
 *  - state (intentions, orders, transactions, request log without keys) lives in the option
 *    efb_e2e_pmb_state (get_option / update_option, so the unit tests' option array works too).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Efb_Test_Paymob_Fake {

	const OPTION = 'efb_e2e_pmb_state';

	const HOSTS = array( 'accept.paymob.com' => 'egy', 'ksa.paymob.com' => 'ksa', 'uae.paymob.com' => 'are', 'oman.paymob.com' => 'omn', 'pakistan.paymob.com' => 'pak' );
	const CURRENCY = array( 'egy' => 'EGP', 'ksa' => 'SAR', 'are' => 'AED', 'omn' => 'OMR', 'pak' => 'PKR' );

	/** Integration IDs of the fake accounts: prefix → mode → method → id. */
	const INTEGRATIONS = array(
		'egy' => array( 'test' => array( 'card' => 1110001, 'wallet' => 1110002, 'kiosk' => 1110003, 'valu' => 1110004, 'meeza' => 1110005 ), 'live' => array( 'card' => 2220001, 'wallet' => 2220002, 'kiosk' => 2220003 ) ),
		'ksa' => array( 'test' => array( 'card' => 3330001, 'mada' => 3330002, 'stcpay' => 3330003 ), 'live' => array( 'card' => 3330101 ) ),
		'are' => array( 'test' => array( 'card' => 6660001, 'tabby' => 6660002 ), 'live' => array( 'card' => 6660101 ) ),
		'omn' => array( 'test' => array( 'card' => 4440001, 'omannet' => 4440002 ), 'live' => array( 'card' => 4440101 ) ),
		'pak' => array( 'test' => array( 'card' => 5550001, 'easypaisa' => 5550002, 'jazzcash' => 5550003 ), 'live' => array( 'card' => 5550101 ) ),
	);

	/** Fake keys of the right shape, assembled here (never a real key). $kind: sk | pk. */
	public static function key( $kind, $mode, $prefix = 'egy' ) {
		return implode( '_', array( $prefix, $kind, $mode === 'live' ? 'live' : 'test', 'FakeUnit' . ucfirst( $kind ) . ( $mode === 'live' ? 'Live' : 'Test' ) . '0001' ) );
	}

	public static function api_key() {
		return 'FakePaymobApiKey' . str_repeat( 'Q', 8 ) . 'Unit0001';
	}

	public static function hmac_secret() {
		return 'FakePaymobHmac' . 'Secret0001';
	}

	/** The settings form post for a region (eg, sa, ae, om, pk) with both modes. */
	public static function settings( $region = 'eg' ) {
		$prefix = array( 'eg' => 'egy', 'sa' => 'ksa', 'ae' => 'are', 'om' => 'omn', 'pk' => 'pak' )[ $region ];
		$map = function ( $mode ) use ( $prefix ) {
			$out = array();
			foreach ( self::INTEGRATIONS[ $prefix ][ $mode ] as $m => $id ) $out[] = $m . '=' . $id;
			return implode( ', ', $out );
		};
		return array(
			'region'             => $region,
			'secret_key__test'   => self::key( 'sk', 'test', $prefix ),
			'secret_key__live'   => self::key( 'sk', 'live', $prefix ),
			'public_key__test'   => self::key( 'pk', 'test', $prefix ),
			'public_key__live'   => self::key( 'pk', 'live', $prefix ),
			'api_key'            => self::api_key(),
			'hmac_secret'        => self::hmac_secret(),
			'integrations__test' => $map( 'test' ),
			'integrations__live' => $map( 'live' ),
		);
	}

	public static function register() {
		add_filter( 'pre_http_request', array( __CLASS__, 'http' ), 10, 3 );
		if ( function_exists( 'add_action' ) ) add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function fixture( $name ) {
		$f = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/payments/paymob/' . $name . '.json' ), true );
		return is_array( $f ) && isset( $f['response']['body'] ) ? $f['response']['body'] : ( is_array( $f ) ? $f : array() );
	}

	public static function state() {
		$s = get_option( self::OPTION, array() );
		return ( is_array( $s ) ? $s : array() ) + array( 'intentions' => array(), 'orders' => array(), 'txns' => array(), 'tokens' => array(), 'refs' => array(), 'log' => array(), 'n' => 0, 'down' => false, 'inquiry_404' => false, 'refund_declined' => false );
	}

	public static function save( array $s ) {
		update_option( self::OPTION, $s, false );
	}

	public static function reset() {
		if ( function_exists( 'delete_option' ) ) delete_option( self::OPTION );
		else unset( $GLOBALS['opts'][ self::OPTION ] );
	}

	public static function set_flag( $k, $v ) {
		$s = self::state();
		$s[ $k ] = $v;
		self::save( $s );
	}

	private static function answer( $code, $body ) {
		return array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}

	/** Which fake key an Authorization header holds: array( prefix, mode ) or null. */
	private static function who( $auth ) {
		if ( ! preg_match( '/^Token (.+)$/', (string) $auth, $m ) ) return null;
		foreach ( self::INTEGRATIONS as $prefix => $x ) {
			foreach ( array( 'test', 'live' ) as $mode ) {
				if ( hash_equals( self::key( 'sk', $mode, $prefix ), $m[1] ) ) return array( $prefix, $mode );
			}
		}
		return null;
	}

	public static function http( $pre, $args, $url ) {
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		if ( ! isset( self::HOSTS[ $host ] ) ) return $pre;
		$region = self::HOSTS[ $host ];
		$s = self::state();
		$method = isset( $args['method'] ) ? strtoupper( $args['method'] ) : 'GET';
		$path = (string) parse_url( $url, PHP_URL_PATH );
		$raw = isset( $args['body'] ) && is_string( $args['body'] ) ? $args['body'] : '';
		$b = json_decode( $raw, true );
		$b = is_array( $b ) ? $b : array();
		$h = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$auth = isset( $h['Authorization'] ) ? (string) $h['Authorization'] : '';
		$who = self::who( $auth );
		$s['log'][] = array( 'method' => $method, 'host' => $host, 'path' => $path, 'key_mode' => $who ? $who[1] : '', 'key_region' => $who ? $who[0] : '', 'bearer' => strpos( $auth, 'Bearer ' ) === 0, 'body' => self::log_body( $b ) );
		$s['log'] = array_slice( $s['log'], -120 );
		if ( ! empty( $s['down'] ) ) { self::save( $s ); return new WP_Error( 'http_request_failed', 'paymob down' ); }
		$out = self::route( $s, $region, $method, $path, $b, $who, $auth );
		self::save( $s );
		return $out;
	}

	/** The body as logged: no api_key / auth_token. */
	private static function log_body( array $b ) {
		foreach ( array( 'api_key', 'auth_token' ) as $k ) if ( isset( $b[ $k ] ) ) $b[ $k ] = '***';
		return $b;
	}

	private static function route( array &$s, $region, $method, $path, array $b, $who, $auth ) {
		if ( $method === 'POST' && $path === '/api/auth/tokens' ) {
			if ( ! isset( $b['api_key'] ) || ! hash_equals( self::api_key(), (string) $b['api_key'] ) ) return self::answer( 403, array( 'detail' => 'Incorrect credentials' ) );
			$s['n']++;
			$token = 'FakeAuthToken' . $region . $s['n'] . substr( md5( uniqid( '', true ) ), 0, 12 );
			$s['tokens'][ $token ] = $region;
			$a = self::fixture( 'auth-token-201' );
			$a['token'] = $token;
			return self::answer( 201, $a );
		}
		if ( $method === 'POST' && $path === '/v1/intention/' ) {
			if ( ! $who ) return self::answer( 401, array( 'detail' => 'Authentication credentials were not provided.' ) );
			if ( $who[0] !== $region ) return self::answer( 401, array( 'detail' => 'Invalid key for this region.' ) );
			return self::intention( $s, $region, $who[1], $b );
		}
		if ( $method === 'POST' && $path === '/api/ecommerce/orders/transaction_inquiry' ) {
			if ( empty( $b['auth_token'] ) || ! isset( $s['tokens'][ $b['auth_token'] ] ) ) return self::answer( 401, array( 'detail' => 'Invalid token.' ) );
			$oid = isset( $b['order_id'] ) ? (string) $b['order_id'] : '';
			if ( $oid === '' && ! empty( $b['merchant_order_id'] ) ) {
				foreach ( $s['orders'] as $id => $o ) if ( $o['merchant_order_id'] === $b['merchant_order_id'] ) $oid = (string) $id;
			}
			if ( ! empty( $s['inquiry_404'] ) || ! isset( $s['orders'][ $oid ] ) || empty( $s['orders'][ $oid ]['txns'] ) ) return self::answer( 404, array( 'detail' => 'Not found.' ) );
			$last = end( $s['orders'][ $oid ]['txns'] );
			return self::answer( 200, $s['txns'][ $last ] );
		}
		if ( $method === 'GET' && preg_match( '#^/api/acceptance/transactions/(\d+)$#', $path, $m ) ) {
			$token = substr( $auth, 0, 7 ) === 'Bearer ' ? substr( $auth, 7 ) : '';
			if ( $token === '' || ! isset( $s['tokens'][ $token ] ) ) return self::answer( 401, array( 'detail' => 'Invalid token.' ) );
			return isset( $s['txns'][ $m[1] ] ) ? self::answer( 200, $s['txns'][ $m[1] ] ) : self::answer( 404, array( 'detail' => 'Not found.' ) );
		}
		if ( $method === 'POST' && $path === '/api/acceptance/void_refund/refund' ) {
			if ( ! $who ) return self::answer( 401, array( 'detail' => 'Authentication credentials were not provided.' ) );
			$pid = isset( $b['transaction_id'] ) ? (string) $b['transaction_id'] : '';
			$amt = isset( $b['amount_cents'] ) ? (int) $b['amount_cents'] : 0;
			if ( ! isset( $s['txns'][ $pid ] ) ) return self::answer( 404, array( 'detail' => 'Not found.' ) );
			$p = $s['txns'][ $pid ];
			if ( empty( $p['success'] ) || ! empty( $p['has_parent_transaction'] ) || $p['source_data']['type'] === 'aggregator' ) return self::answer( 400, array( 'detail' => 'Transaction can not be refunded.' ) );
			if ( $amt <= 0 || $amt > $p['amount_cents'] - $p['refunded_amount_cents'] ) return self::answer( 400, array( 'amount_cents' => array( 'Refund amount exceeds the remaining amount.' ) ) );
			$c = self::txn( $s, $p['order']['id'], $p['integration_id'], $p['currency'], $amt, $who[1], 'card' );
			$c['has_parent_transaction'] = true;
			$c['parent_transaction'] = (int) $pid;
			$c['is_refund'] = true;
			$c['success'] = empty( $s['refund_declined'] );
			$c['data'] = array( 'message' => $c['success'] ? 'Approved' : 'Declined', 'txn_response_code' => $c['success'] ? 'APPROVED' : 'DECLINED' );
			$s['txns'][ (string) $c['id'] ] = $c;
			$s['orders'][ (string) $p['order']['id'] ]['txns'][] = (string) $c['id'];
			if ( $c['success'] ) {
				$s['txns'][ $pid ]['refunded_amount_cents'] += $amt;
				$s['txns'][ $pid ]['is_refunded'] = true;
			}
			// The documented answer is the child transaction; the Pakistan page shows it as {type, obj}.
			return self::answer( 200, $region === 'pak' ? array( 'type' => 'TRANSACTION', 'obj' => $c ) : $c );
		}
		return self::answer( 404, array( 'detail' => 'Not found.' ) );
	}

	private static function intention( array &$s, $region, $mode, array $b ) {
		foreach ( array( 'amount', 'currency', 'payment_methods' ) as $k ) {
			if ( ! isset( $b[ $k ] ) || $b[ $k ] === array() ) return self::answer( 400, array( $k => array( 'This field is required.' ) ) );
		}
		foreach ( array( 'first_name', 'last_name', 'email', 'phone_number' ) as $k ) {
			if ( empty( $b['billing_data'][ $k ] ) ) return self::answer( 400, array( 'billing_data' => array( $k => array( 'This field is required.' ) ) ) );
		}
		if ( ! is_int( $b['amount'] ) || $b['amount'] <= 0 ) return self::answer( 400, array( 'amount' => array( 'A valid integer is required.' ) ) );
		if ( isset( $b['items'] ) ) {
			$sum = 0;
			foreach ( $b['items'] as $it ) {
				if ( ! isset( $it['name'] ) || $it['name'] === '' ) return self::answer( 400, array( 'items' => array( 'name' => array( 'This field is required.' ) ) ) );
				if ( ! isset( $it['amount'] ) ) return self::answer( 400, array( 'items' => array( 'amount' => array( 'This field is required.' ) ) ) );
				if ( function_exists( 'mb_strlen' ) && mb_strlen( $it['name'] ) > 50 ) return self::answer( 400, array( 'items' => array( 'name' => array( 'Ensure this field has no more than 50 characters.' ) ) ) );
				$sum += (int) $it['amount'] * ( isset( $it['quantity'] ) ? (int) $it['quantity'] : 1 );
			}
			if ( $sum !== $b['amount'] ) return self::answer( 400, array( 'items' => array( 'Sum of items amounts should equal the amount.' ) ) );
		}
		$ids = self::INTEGRATIONS[ $region ][ $mode ];
		foreach ( $b['payment_methods'] as $pm ) {
			if ( ! in_array( $pm, $ids, true ) || strtoupper( (string) $b['currency'] ) !== self::CURRENCY[ $region ] ) {
				return self::answer( 404, array( 'detail' => 'Integration ID/Name does not exist in our system . You can find the list of Integration ID’/Names from Merchant Dashboard under Developers → Payment Integrations Tab' ) );
			}
		}
		$ref = isset( $b['special_reference'] ) ? (string) $b['special_reference'] : '';
		if ( $ref !== '' && isset( $s['refs'][ $ref ] ) ) return self::answer( 400, array( 'special_reference' => array( 'Special reference must be unique.' ) ) );
		$s['n']++;
		$order = 300000000 + $s['n'];
		$i = self::fixture( 'intention-201' );
		$i['id'] = implode( '_', array( 'pi', $mode, substr( md5( 'i' . $order . uniqid( '', true ) ), 0, 32 ) ) );
		$i['client_secret'] = implode( '_', array( $region, 'csk', $mode, substr( md5( 'c' . $order . uniqid( '', true ) ), 0, 32 ) ) );
		$i['intention_order_id'] = $order;
		$i['special_reference'] = $ref;
		$i['intention_detail'] = array( 'amount' => $b['amount'], 'items' => isset( $b['items'] ) ? $b['items'] : array(), 'currency' => strtoupper( (string) $b['currency'] ), 'billing_data' => $b['billing_data'] );
		$i['payment_keys'] = array();
		$i['payment_methods'] = array();
		foreach ( $b['payment_methods'] as $pm ) {
			$i['payment_keys'][] = array( 'integration' => $pm, 'key' => '<PAYMENT_KEY>', 'gateway_type' => 'MIGS', 'iframe_id' => null, 'order_id' => $order );
			$i['payment_methods'][] = array( 'integration_id' => $pm, 'alias' => null, 'name' => (string) array_search( $pm, $ids, true ), 'method_type' => 'online', 'currency' => self::CURRENCY[ $region ], 'live' => $mode === 'live', 'use_cvc_with_moto' => false );
		}
		$i['extras'] = array( 'creation_extras' => isset( $b['extras'] ) ? $b['extras'] : array(), 'confirmation_extras' => null );
		$i['created'] = gmdate( 'Y-m-d\TH:i:s.u' );
		$s['intentions'][ $i['id'] ] = array( 'body' => $i, 'region' => $region, 'mode' => $mode, 'notification_url' => isset( $b['notification_url'] ) ? $b['notification_url'] : '', 'redirection_url' => isset( $b['redirection_url'] ) ? $b['redirection_url'] : '', 'raw' => $b );
		$s['orders'][ (string) $order ] = array( 'intention' => $i['id'], 'merchant_order_id' => $ref, 'amount' => $b['amount'], 'currency' => strtoupper( (string) $b['currency'] ), 'mode' => $mode, 'methods' => $b['payment_methods'], 'txns' => array() );
		if ( $ref !== '' ) $s['refs'][ $ref ] = $order;
		return self::answer( 201, $i );
	}

	/** A transaction object in the documented shape (transaction-inquiry sample). */
	private static function txn( array &$s, $order, $integration, $currency, $amount, $mode, $type ) {
		$s['n']++;
		$t = self::fixture( 'inquiry-200' );
		$t['id'] = 400000000 + $s['n'];
		$t['amount_cents'] = (int) $amount;
		$t['amount_cents_int'] = (int) $amount;
		$t['original_amount'] = (int) $amount;
		$t['currency'] = $currency;
		$t['integration_id'] = (int) $integration;
		$t['is_live'] = $mode === 'live';
		$t['created_at'] = gmdate( 'Y-m-d\TH:i:s' ) . '.' . str_pad( (string) ( $s['n'] * 7919 % 1000000 ), 6, '0', STR_PAD_LEFT );
		$t['paid_at'] = $t['created_at'];
		$t['owner'] = 302852;
		$t['pending'] = false;
		$t['success'] = false;
		$t['is_refunded'] = false;
		$t['is_voided'] = false;
		$t['is_refund'] = false;
		$t['is_void'] = false;
		$t['refunded_amount_cents'] = 0;
		$t['has_parent_transaction'] = false;
		$t['parent_transaction'] = null;
		$t['order']['id'] = (int) $order;
		$t['order']['amount_cents'] = (int) $amount;
		$t['order']['currency'] = $currency;
		$t['order']['merchant_order_id'] = isset( $s['orders'][ (string) $order ] ) ? $s['orders'][ (string) $order ]['merchant_order_id'] : null;
		$t['source_data'] = $type === 'wallet' ? array( 'pan' => '01010101010', 'type' => 'wallet', 'sub_type' => 'wallet' )
			: ( $type === 'aggregator' ? array( 'pan' => '', 'type' => 'aggregator', 'sub_type' => 'AGGREGATOR' ) : array( 'pan' => '2346', 'type' => 'card', 'tenure' => null, 'sub_type' => 'MasterCard' ) );
		$t['billing_data'] = array( 'email' => 'payer@example.test' );
		$t['data'] = array( 'message' => 'Approved', 'txn_response_code' => 'APPROVED' );
		return $t;
	}

	/**
	 * The visitor on Unified Checkout: a transaction on the order. $kind: paid | failed | kiosk
	 * (pending with a bill reference) | voided | authorized; $over = fields to change (amount_cents …).
	 *
	 * @return array the transaction
	 */
	public static function pay( $order, $kind = 'paid', array $over = array(), $method = 'card' ) {
		$s = self::state();
		$o = $s['orders'][ (string) $order ];
		$ids = self::INTEGRATIONS[ self::prefix_of_intention( $s, $o['intention'] ) ][ $o['mode'] ];
		$integration = isset( $ids[ $method ] ) && in_array( $ids[ $method ], $o['methods'], true ) ? $ids[ $method ] : $o['methods'][0];
		$type = $kind === 'kiosk' || $method === 'kiosk' ? 'aggregator' : ( $method === 'wallet' ? 'wallet' : 'card' );
		$t = self::txn( $s, $order, $integration, $o['currency'], $o['amount'], $o['mode'], $type );
		if ( $kind === 'paid' ) $t['success'] = true;
		if ( $kind === 'authorized' ) { $t['success'] = true; $t['is_auth'] = true; }
		if ( $kind === 'voided' ) { $t['success'] = true; $t['is_voided'] = true; }
		if ( $kind === 'failed' ) { $t['data'] = array( 'message' => 'Do not honour', 'txn_response_code' => 'DECLINED' ); $t['error_occured'] = false; }
		if ( $kind === 'kiosk' ) { $t['pending'] = true; $t['data'] = array( 'message' => 'Pending', 'bill_reference' => 7700000 + $s['n'] ); }
		foreach ( $over as $k => $v ) $t[ $k ] = $v;
		$s['txns'][ (string) $t['id'] ] = $t;
		$s['orders'][ (string) $order ]['txns'][] = (string) $t['id'];
		self::save( $s );
		return $t;
	}

	/** Paymob's side of an existing transaction changes (kiosk paid at the outlet …). */
	public static function update( $txn_id, array $fields ) {
		$s = self::state();
		foreach ( $fields as $k => $v ) $s['txns'][ (string) $txn_id ][ $k ] = $v;
		self::save( $s );
		return $s['txns'][ (string) $txn_id ];
	}

	public static function get_txn( $id ) {
		$s = self::state();
		return isset( $s['txns'][ (string) $id ] ) ? $s['txns'][ (string) $id ] : null;
	}

	private static function prefix_of_intention( array $s, $iid ) {
		return $s['intentions'][ $iid ]['region'];
	}

	/** The independent HMAC string of a POST callback (explicit, like Paymob's WooCommerce plugin). */
	public static function hmac_post_string( array $o ) {
		$b = function ( $v ) { return $v === true ? 'true' : ( $v === false ? 'false' : (string) $v ); };
		return $o['amount_cents'] . $o['created_at'] . $o['currency'] . $b( $o['error_occured'] ) . $b( $o['has_parent_transaction'] )
			. $o['id'] . $o['integration_id'] . $b( $o['is_3d_secure'] ) . $b( $o['is_auth'] ) . $b( $o['is_capture'] ) . $b( $o['is_refunded'] )
			. $b( $o['is_standalone_payment'] ) . $b( $o['is_voided'] ) . ( is_array( $o['order'] ) ? $o['order']['id'] : $o['order'] ) . $o['owner'] . $b( $o['pending'] )
			. $o['source_data']['pan'] . $o['source_data']['sub_type'] . $o['source_data']['type'] . $b( $o['success'] );
	}

	/** The Transaction Processed callback for a transaction: JSON body and the hmac query value. */
	public static function callback( $txn, $secret = null ) {
		$t = is_array( $txn ) ? $txn : self::get_txn( $txn );
		$body = array( 'type' => 'TRANSACTION', 'obj' => $t, 'issuer_bank' => null, 'transaction_processed_callback_responses' => '' );
		return array( 'body' => json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 'hmac' => hash_hmac( 'sha512', self::hmac_post_string( $t ), $secret === null ? self::hmac_secret() : $secret ) );
	}

	/** The redirection Paymob makes after the payment: the query (PHP names, like $_GET) with its hmac. */
	public static function redirect_query( $txn, $secret = null ) {
		$t = is_array( $txn ) ? $txn : self::get_txn( $txn );
		$b = function ( $v ) { return $v === true ? 'true' : ( $v === false ? 'false' : (string) $v ); };
		$q = array( 'id' => (string) $t['id'], 'pending' => $b( $t['pending'] ), 'amount_cents' => (string) $t['amount_cents'], 'success' => $b( $t['success'] ),
			'is_auth' => $b( $t['is_auth'] ), 'is_capture' => $b( $t['is_capture'] ), 'is_standalone_payment' => $b( $t['is_standalone_payment'] ), 'is_voided' => $b( $t['is_voided'] ),
			'is_refunded' => $b( $t['is_refunded'] ), 'is_3d_secure' => $b( $t['is_3d_secure'] ), 'integration_id' => (string) $t['integration_id'], 'profile_id' => '106',
			'has_parent_transaction' => $b( $t['has_parent_transaction'] ), 'order' => (string) $t['order']['id'], 'created_at' => $t['created_at'], 'currency' => $t['currency'],
			'error_occured' => $b( $t['error_occured'] ), 'owner' => (string) $t['owner'], 'data_message' => (string) $t['data']['message'],
			'source_data_type' => $t['source_data']['type'], 'source_data_pan' => $t['source_data']['pan'], 'source_data_sub_type' => $t['source_data']['sub_type'],
			'txn_response_code' => isset( $t['data']['txn_response_code'] ) ? (string) $t['data']['txn_response_code'] : '' );
		$str = $q['amount_cents'] . $q['created_at'] . $q['currency'] . $q['error_occured'] . $q['has_parent_transaction'] . $q['id'] . $q['integration_id']
			. $q['is_3d_secure'] . $q['is_auth'] . $q['is_capture'] . $q['is_refunded'] . $q['is_standalone_payment'] . $q['is_voided'] . $q['order']
			. $q['owner'] . $q['pending'] . $q['source_data_pan'] . $q['source_data_sub_type'] . $q['source_data_type'] . $q['success'];
		$q['hmac'] = hash_hmac( 'sha512', $str, $secret === null ? self::hmac_secret() : $secret );
		return $q;
	}

	/** The order a client secret belongs to (the fake Unified Checkout page). */
	public static function by_client_secret( $cs ) {
		foreach ( self::state()['intentions'] as $i ) {
			if ( hash_equals( (string) $i['body']['client_secret'], (string) $cs ) ) return $i;
		}
		return null;
	}

	public static function routes() {
		register_rest_route( 'efb-e2e/v1', 'paymob-checkout', array(
			'methods'             => array( 'GET', 'POST' ),
			'callback'            => array( __CLASS__, 'checkout' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * The fake Unified Checkout page (e2e): amount, methods; Pay / Decline / Kiosk create the
	 * transaction; then the visitor goes back to redirection_url with the signed query (Kiosk: no
	 * redirection, Paymob shows the reference). The callback is posted by the test itself.
	 */
	public static function checkout( WP_REST_Request $r ) {
		$i = self::by_client_secret( (string) $r->get_param( 'clientSecret' ) );
		if ( ! $i ) return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		$order = $i['body']['intention_order_id'];
		if ( $r->get_method() === 'POST' ) {
			$do = (string) $r->get_param( 'do' );
			$t = null;
			if ( $do === 'pay' ) $t = self::pay( $order, 'paid', array(), (string) $r->get_param( 'm' ) ?: 'card' );
			if ( $do === 'decline' ) $t = self::pay( $order, 'failed' );
			if ( $do === 'kiosk' ) $t = self::pay( $order, 'kiosk', array(), 'kiosk' );
			$to = $i['redirection_url'];
			if ( $t && $do !== 'kiosk' ) $to .= ( strpos( $to, '?' ) === false ? '?' : '&' ) . http_build_query( self::redirect_query( $t ) );
			$res = new WP_REST_Response( null, 303 );
			$res->header( 'Location', $to );
			return $res;
		}
		$action = esc_url( rest_url( 'efb-e2e/v1/paymob-checkout' ) );
		$hidden = '<input type="hidden" name="clientSecret" value="' . esc_attr( $i['body']['client_secret'] ) . '">';
		$names = array();
		foreach ( $i['body']['payment_methods'] as $pm ) $names[] = $pm['name'] . ':' . $pm['integration_id'];
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Paymob (fake)</title></head><body>'
			. '<h1>Paymob Unified Checkout (fake, local test)</h1><p>Amount: <strong id="amount">' . esc_html( $i['body']['intention_detail']['amount'] . ' ' . $i['body']['intention_detail']['currency'] ) . '</strong></p>'
			. '<p>Methods: <code id="methods">' . esc_html( implode( ',', $names ) ) . '</code></p>'
			. '<p>Public key: <code id="pk">' . esc_html( (string) $r->get_param( 'publicKey' ) ) . '</code></p>'
			. '<form method="post" action="' . $action . '">' . $hidden . '<button id="pay" name="do" value="pay">Pay</button> <button id="decline" name="do" value="decline">Decline</button> <button id="kiosk" name="do" value="kiosk">Kiosk</button> <button id="back" name="do" value="back">Return to the site</button></form>'
			. '</body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts
		exit;
	}
}
