<?php
/**
 * PAY00 §21: the reference / test gateway "efbmock". Not part of the shipped plugin: it lives in
 * tests/ and is registered by the unit tests (tests/pay-test-db.php) and by the e2e site's
 * mu-plugin (tests/e2e-wp/mu-plugins/efb-e2e.php, through tests/payments-mock/mock-efb.php) with
 * the normal efb_register_payment_gateways hook, exactly like a gateway add-on.
 *
 * It is written exactly like a gateway add-on (it only uses the public contract: Gateway,
 * Settings, $this->request()), so it proves an add-on can plug in through the registry. Its
 * "provider" is Mock_Provider: a pre_http_request handler for https://mock.efb.invalid that keeps
 * payments in an option, plus a hosted checkout page at Emsfb/v1/payments/mock/checkout with
 * "Pay" and "Fail" buttons. No request ever leaves the machine.
 *
 * Webhooks: JSON {id, type, data:{id}} signed with X-Mock-Signature = hex(HMAC-SHA256(body,
 * webhook_secret)). The gateway verifies the signature and then asks the provider (refetch).
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mock_Gateway extends Gateway {

	const HOST = 'mock.efb.invalid';

	/** The last Checkout_Context create() received (tests read it; nothing is written anywhere). */
	public static $last_ctx = null;

	public function slug() { return 'efbmock'; }
	public function addon_key() { return ''; }
	public function label() { return __( 'Test gateway', 'easy-form-builder' ); }

	public function supports() {
		return array(
			'flows'          => array( 'redirect' ),
			'refund'         => true,
			'partial_refund' => true,
			'recurring'      => false,
			'authorize'      => false,
			'currencies'     => array(),
			'countries'      => array(),
			'min_minor'      => array( 'EUR' => 30, 'USD' => 50 ),
			'max_minor'      => array( 'EUR' => 2000000 ),
			'webhook_auth'   => 'signature',
		);
	}

	public function settings_schema() {
		return array(
			array( 'key' => 'api_key', 'type' => 'secret', 'per_mode' => true, 'required' => true, 'label' => __( 'API key', 'easy-form-builder' ), 'pattern' => '^mock_(test|live)_[A-Za-z0-9]{6,}$', 'help' => 'mock_test_… / mock_live_…' ),
			array( 'key' => 'webhook_secret', 'type' => 'secret', 'per_mode' => true, 'required' => true, 'label' => __( 'Webhook signing secret', 'easy-form-builder' ) ),
		);
	}

	public function api_hosts() { return array( self::HOST ); }

	public function methods( Settings $s ) {
		return array( array( 'card', __( 'Card', 'easy-form-builder' ) ), array( 'ideal', 'iDEAL' ) );
	}

	private function auth( Settings $s, $mode = null ) {
		return array( 'Authorization' => 'Bearer ' . (string) $s->secret( 'api_key', $mode ) );
	}

	public function test_connection( Settings $s, $mode ) {
		$key = (string) $s->secret( 'api_key', $mode );
		if ( $key !== '' && strpos( $key, 'mock_' . $mode . '_' ) !== 0 ) {
			return Result::fail( 'wrong_mode', sprintf( __( 'This key is not a %s key.', 'easy-form-builder' ), $mode === 'live' ? 'Live' : 'Test' ) );
		}
		$r = $this->request( 'GET', 'https://' . self::HOST . '/v1/ping', array( 'headers' => $this->auth( $s, $mode ), 'timeout' => 8 ) );
		return $r['ok'] ? Result::ok( 'The mock provider answered (' . $mode . ').' ) : Result::fail( $r['error'] );
	}

	/** Notices and an extra button on the settings card (PAY00 optional hooks). */
	public function settings_notices( Settings $s ) {
		return array( array( 'level' => 'info', 'm' => 'Test gateway: no real money moves.' ) );
	}

	public function settings_actions( Settings $s ) {
		return array( array( 'id' => 'ping', 'label' => 'Ping the mock provider' ) );
	}

	public function run_settings_action( $action, Settings $s, $mode ) {
		return $action === 'ping' ? $this->test_connection( $s, $mode ) : Result::unsupported();
	}

	public function cancel( Payment $p, Settings $s ) {
		if ( $p->gateway_ref === '' ) return Result::fail( 'no_ref' );
		$all = Mock_Provider::all();
		if ( isset( $all[ $p->gateway_ref ] ) && $all[ $p->gateway_ref ]['status'] === 'open' ) Mock_Provider::set_status( $p->gateway_ref, 'canceled' );
		return Result::ok();
	}

	public function create( Payment $p, Settings $s, Checkout_Context $ctx ) {
		self::$last_ctx = $ctx;
		if ( $ctx->client_token === 'tok_declined' ) return Create_Result::fail( 'card_declined', 'mock: declined token' )->for_visitor( 'The test card was declined.' );
		$r = $this->request( 'POST', 'https://' . self::HOST . '/v1/payments', array(
			'headers' => array_merge( $this->auth( $s ), array( 'Idempotency-Key' => $p->idem_key ) ),
			'body'    => array(
				'cardToken'   => $ctx->client_token,
				'payer'       => array( 'email' => isset( $ctx->payer['email'] ) ? $ctx->payer['email'] : '', 'name' => isset( $ctx->payer['name'] ) ? $ctx->payer['name'] : '' ),
				'amount'      => array( 'value' => $this->format_amount( $p->amount_minor, $p->currency ), 'currency' => $p->currency ),
				'description' => $ctx->description,
				'redirectUrl' => $ctx->return_url,
				'webhookUrl'  => $ctx->webhook_url,
				'method'      => $ctx->method,
				'metadata'    => $ctx->metadata,
			),
		) );
		if ( ! $r['ok'] || ! is_array( $r['json'] ) || empty( $r['json']['id'] ) ) return Create_Result::fail( $r['error'] ?: 'bad_response' );
		$res = Create_Result::redirect( $r['json']['id'], (string) $r['json']['checkoutUrl'] );
		$res->status = 'pending';
		return $res;
	}

	public function fetch( Payment $p, Settings $s ) {
		$r = $this->request( 'GET', 'https://' . self::HOST . '/v1/payments/' . rawurlencode( $p->gateway_ref ), array( 'headers' => $this->auth( $s ), 'timeout' => 8 ) );
		if ( ! $r['ok'] || ! is_array( $r['json'] ) ) return Remote_State::error( $r['error'] ?: 'bad_response' );
		$j = $r['json'];
		// The answer must be this payment's: another payment's or another mode's is never applied.
		if ( isset( $j['metadata']['efb_uid'] ) && (string) $j['metadata']['efb_uid'] !== $p->uid ) return Remote_State::mismatch( 'mismatch_uid' );
		if ( isset( $j['mode'] ) && $j['mode'] !== $p->mode ) return Remote_State::mismatch( 'mismatch_mode' );
		$map = array( 'open' => 'pending', 'pending' => 'pending', 'authorized' => 'authorized', 'paid' => 'paid', 'failed' => 'failed', 'canceled' => 'canceled', 'expired' => 'expired', 'refunded' => 'refunded' );
		$status = isset( $j['status'], $map[ $j['status'] ] ) ? $map[ $j['status'] ] : 'pending';
		$cur = isset( $j['amount']['currency'] ) ? (string) $j['amount']['currency'] : '';
		$amount = isset( $j['amount']['value'] ) ? Money::from_decimal( (string) $j['amount']['value'], $cur ) : null;
		$refunds = array();
		$refunded = 0;
		foreach ( isset( $j['refunds'] ) && is_array( $j['refunds'] ) ? $j['refunds'] : array() as $rf ) {
			$refunds[] = array( 'ref' => (string) $rf['id'], 'status' => (string) $rf['status'], 'amount_minor' => (int) $rf['amount_minor'] );
			if ( $rf['status'] === 'refunded' ) $refunded += (int) $rf['amount_minor'];
		}
		if ( $status === 'paid' && $refunded > 0 && $amount ) $status = $refunded >= $amount ? 'refunded' : 'partially_refunded';
		return Remote_State::of( $status, $amount, $cur, array( 'paid_at' => isset( $j['paidAt'] ) ? $j['paidAt'] : null, 'method' => isset( $j['method'] ) ? (string) $j['method'] : '', 'refunded_minor' => $refunded, 'refunds' => $refunds, 'raw' => Redactor::redact( $j ) ) );
	}

	public function parse_webhook( \WP_REST_Request $r, Settings $s ) {
		$body = (string) $r->get_body();
		$j = json_decode( $body, true );
		if ( ! is_array( $j ) || empty( $j['data']['id'] ) ) return null;
		$sig = (string) $r->get_header( 'x_mock_signature' );
		$ok = false;
		foreach ( array( 'test', 'live' ) as $mode ) {
			$secret = (string) $s->secret( 'webhook_secret', $mode );
			if ( $secret !== '' && $sig !== '' && hash_equals( hash_hmac( 'sha256', $body, $secret ), $sig ) ) { $ok = true; break; }
		}
		if ( ! $ok ) return Webhook_Event::rejected( array( 'id' => isset( $j['id'] ) ? (string) $j['id'] : '' ) );
		return new Webhook_Event( array(
			'verified'      => true,
			'gateway_ref'   => (string) $j['data']['id'],
			'event_id'      => isset( $j['id'] ) ? (string) $j['id'] : '',
			'type'          => isset( $j['type'] ) ? (string) $j['type'] : '',
			'needs_refetch' => true,
			'payload'       => Redactor::redact( $j ),
		) );
	}

	public function refund( Payment $p, $amount_minor, $reason, Settings $s ) {
		$r = $this->request( 'POST', 'https://' . self::HOST . '/v1/refunds', array(
			'headers' => array_merge( $this->auth( $s ), array( 'Idempotency-Key' => hash( 'sha256', $p->uid . '|' . $amount_minor . '|' . $p->refunded_minor ) ) ),
			'body'    => array( 'payment' => $p->gateway_ref, 'amount' => array( 'value' => $this->format_amount( $amount_minor, $p->currency ), 'currency' => $p->currency ) ),
		) );
		if ( ! $r['ok'] || ! is_array( $r['json'] ) ) return Refund_Result::fail( $r['error'] ?: 'bad_response' );
		$res = Refund_Result::ok();
		$res->status = (string) $r['json']['status'] === 'pending' ? 'pending' : 'refunded';
		$res->gateway_ref = (string) $r['json']['id'];
		return $res;
	}

	public function dashboard_url( Payment $p ) {
		return '';
	}
}

/**
 * The fake provider behind https://mock.efb.invalid (pre_http_request). Payments live in the
 * option efb_mock_provider. The option efb_mock_provider_down = 1 answers every call with a
 * network error (tests of 503 / retry).
 */
class Mock_Provider {

	const OPTION = 'efb_mock_provider';

	public static function register() {
		add_filter( 'pre_http_request', array( __CLASS__, 'http' ), 10, 3 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function all() {
		$v = get_option( self::OPTION, array() );
		return is_array( $v ) ? $v : array();
	}

	public static function save( array $all ) {
		update_option( self::OPTION, $all, false );
	}

	/** Sets the status of a refund at the provider (tests: a pending refund confirmed or declined later). */
	public static function set_refund( $payment_id, $refund_id, $status ) {
		$all = self::all();
		if ( ! isset( $all[ $payment_id ]['refunds'] ) ) return false;
		foreach ( $all[ $payment_id ]['refunds'] as $i => $rf ) {
			if ( $rf['id'] === $refund_id ) $all[ $payment_id ]['refunds'][ $i ]['status'] = $status;
		}
		self::save( $all );
		return true;
	}

	/** Sets the provider-side state (the checkout page and the tests use it). */
	public static function set_status( $id, $status, $amount = null ) {
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) return false;
		$all[ $id ]['status'] = $status;
		if ( $status === 'paid' ) $all[ $id ]['paidAt'] = gmdate( 'c' );
		if ( $amount !== null ) $all[ $id ]['amount']['value'] = (string) $amount;
		self::save( $all );
		return true;
	}

	private static function answer( $code, $body ) {
		return array( 'headers' => array(), 'body' => json_encode( $body ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}

	public static function http( $pre, $args, $url ) {
		$host = parse_url( (string) $url, PHP_URL_HOST );
		if ( $host !== Mock_Gateway::HOST ) return $pre;
		if ( get_option( 'efb_mock_provider_down' ) ) return new \WP_Error( 'http_request_failed', 'mock provider down' );
		$path = (string) parse_url( (string) $url, PHP_URL_PATH );
		$method = isset( $args['method'] ) ? strtoupper( $args['method'] ) : 'GET';
		$h = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$key = isset( $h['Authorization'] ) ? substr( (string) $h['Authorization'], 7 ) : '';
		if ( ! preg_match( '/^mock_(test|live)_[A-Za-z0-9]{6,}$/', $key ) ) return self::answer( 401, array( 'error' => 'unauthorized' ) );
		$mode = strpos( $key, 'mock_live_' ) === 0 ? 'live' : 'test';
		$body = isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : array();
		$all = self::all();
		if ( $path === '/v1/ping' ) return self::answer( 200, array( 'ok' => true, 'mode' => $mode ) );
		if ( $method === 'POST' && $path === '/v1/payments' ) {
			$idem = isset( $h['Idempotency-Key'] ) ? (string) $h['Idempotency-Key'] : '';
			foreach ( $all as $p ) {
				if ( $idem !== '' && $p['idem'] === $idem ) return self::answer( 201, $p );
			}
			$id = 'mck_' . substr( bin2hex( random_bytes( 8 ) ), 0, 12 );
			$p = array(
				'id'          => $id,
				'status'      => 'open',
				'mode'        => $mode,
				'amount'      => isset( $body['amount'] ) ? $body['amount'] : array(),
				'description' => isset( $body['description'] ) ? (string) $body['description'] : '',
				'redirectUrl' => isset( $body['redirectUrl'] ) ? (string) $body['redirectUrl'] : '',
				'webhookUrl'  => isset( $body['webhookUrl'] ) ? (string) $body['webhookUrl'] : '',
				'method'      => isset( $body['method'] ) ? (string) $body['method'] : '',
				'metadata'    => isset( $body['metadata'] ) ? $body['metadata'] : array(),
				// Like a real provider: the card token is used, never kept; only proof that it arrived.
				'token_hash'  => isset( $body['cardToken'] ) && $body['cardToken'] !== '' ? hash( 'sha256', (string) $body['cardToken'] ) : '',
				'payer'       => isset( $body['payer'] ) ? $body['payer'] : array(),
				'refunds'     => array(),
				'idem'        => $idem,
				'checkoutUrl' => add_query_arg( 'id', $id, rest_url( 'Emsfb/v1/payments/mock/checkout' ) ),
			);
			$all[ $id ] = $p;
			self::save( $all );
			return self::answer( 201, $p );
		}
		if ( $method === 'GET' && preg_match( '#^/v1/payments/(mck_[a-f0-9]+)$#', $path, $m ) ) {
			return isset( $all[ $m[1] ] ) ? self::answer( 200, $all[ $m[1] ] ) : self::answer( 404, array( 'error' => 'not_found' ) );
		}
		if ( $method === 'POST' && $path === '/v1/refunds' ) {
			$pid = isset( $body['payment'] ) ? (string) $body['payment'] : '';
			if ( ! isset( $all[ $pid ] ) || $all[ $pid ]['status'] !== 'paid' && $all[ $pid ]['status'] !== 'refunded' ) return self::answer( 422, array( 'error' => 'not_refundable' ) );
			// Option efb_mock_refund_status = pending: the provider accepts the refund and settles it later.
			$st = get_option( 'efb_mock_refund_status' ) === 'pending' ? 'pending' : 'refunded';
			$rf = array( 'id' => 're_' . substr( bin2hex( random_bytes( 6 ) ), 0, 10 ), 'status' => $st, 'amount_minor' => isset( $body['amount']['value'] ) ? (int) Money::from_decimal( (string) $body['amount']['value'], (string) $body['amount']['currency'] ) : 0 );
			$all[ $pid ]['refunds'][] = $rf;
			self::save( $all );
			return self::answer( 201, $rf );
		}
		return self::answer( 404, array( 'error' => 'not_found' ) );
	}

	public static function routes() {
		register_rest_route( 'Emsfb/v1', 'payments/mock/checkout', array(
			'methods'             => array( 'GET', 'POST' ),
			'callback'            => array( __CLASS__, 'checkout' ),
			'permission_callback' => '__return_true',
		) );
	}

	private static function token( $id ) {
		return substr( hash_hmac( 'sha256', 'mock-checkout|' . $id, wp_salt( 'nonce' ) ), 0, 24 );
	}

	/** The hosted payment page of the fake provider. */
	public static function checkout( \WP_REST_Request $r ) {
		$id = (string) $r->get_param( 'id' );
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) return new \WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		$p = $all[ $id ];
		if ( $r->get_method() === 'POST' ) {
			if ( ! hash_equals( self::token( $id ), (string) $r->get_param( 't' ) ) ) return new \WP_REST_Response( array( 'error' => 'bad_token' ), 403 );
			$outcome = (string) $r->get_param( 'outcome' );
			if ( in_array( $outcome, array( 'paid', 'failed', 'canceled' ), true ) ) self::set_status( $id, $outcome );
			$res = new \WP_REST_Response( null, 303 );
			$res->header( 'Location', $outcome === 'canceled' ? add_query_arg( 'c', '1', $p['redirectUrl'] ) : $p['redirectUrl'] );
			return $res;
		}
		$amount = esc_html( ( isset( $p['amount']['value'] ) ? $p['amount']['value'] : '' ) . ' ' . ( isset( $p['amount']['currency'] ) ? $p['amount']['currency'] : '' ) );
		$action = esc_url( rest_url( 'Emsfb/v1/payments/mock/checkout' ) );
		$t = esc_attr( self::token( $id ) );
		$html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mock checkout</title>'
			. '<style>body{font:16px system-ui,sans-serif;max-width:28rem;margin:3rem auto;padding:0 1rem}button{font:inherit;padding:.7rem 1.2rem;margin:.3rem .3rem 0 0;border-radius:.5rem;border:1px solid #555;cursor:pointer}#pay{background:#14532d;color:#fff}</style></head><body>'
			. '<h1>Mock provider checkout</h1><p>' . ( $p['mode'] === 'test' ? 'TEST MODE. ' : '' ) . 'Amount: <strong id="amount">' . $amount . '</strong></p>'
			. '<p id="desc">' . esc_html( $p['description'] ) . '</p>'
			. '<form method="post" action="' . $action . '"><input type="hidden" name="id" value="' . esc_attr( $id ) . '"><input type="hidden" name="t" value="' . $t . '">'
			. '<button id="pay" name="outcome" value="paid">Pay (test)</button><button id="fail" name="outcome" value="failed">Fail (test)</button><button id="cancel" name="outcome" value="canceled">Cancel</button></form></body></html>';
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above
		exit;
	}
}
