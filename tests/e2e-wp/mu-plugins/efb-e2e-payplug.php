<?php
/**
 * Plugin Name: EFB e2e: fake Payplug (local test site only)
 * Description: Scenario "payplug" of tests/e2e-wp/run.js copies this file into wp-content/mu-plugins
 *              (ctl-payplug.php fake 1). While the option efb_e2e_payplug_fake is set, every request
 *              to api.payplug.com is answered here (pre_http_request) with answers built from the
 *              documented responses in tests/fixtures/payments/payplug/, and the fake hosted payment
 *              page is served at /wp-json/efb-e2e/v1/payplug-pay. Nothing leaves the machine and no
 *              real key is used (the keys are made-up placeholders, see ctl-payplug.php).
 */

if ( ! function_exists( 'efb_e2e_payplug_fixture' ) ) {

	function efb_e2e_payplug_fixture( $name ) {
		$f = WP_PLUGIN_DIR . '/easy-form-builder/tests/fixtures/payments/payplug/' . $name . '.json';
		$d = is_readable( $f ) ? json_decode( file_get_contents( $f ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $d ) ? $d['response']['body'] : array();
	}

	/** The placeholder keys of the fake: JWT-shaped, the middle part says the mode. */
	function efb_e2e_payplug_key( $mode ) {
		return 'e2e-ppg-key.' . 'mode' . $mode . '0000.notasignature0';
	}

	function efb_e2e_payplug_all() {
		$v = get_option( 'efb_e2e_payplug_payments', array() );
		return is_array( $v ) ? $v : array();
	}

	/** Moves a payment to the state of a get-* fixture (state fields only). */
	function efb_e2e_payplug_set( $id, $fixture, $over = array() ) {
		$all = efb_e2e_payplug_all();
		if ( ! isset( $all[ $id ] ) ) return false;
		$f = efb_e2e_payplug_fixture( $fixture );
		foreach ( array( 'is_paid', 'paid_at', 'is_3ds', 'is_refunded', 'amount_refunded', 'failure', 'card' ) as $k ) {
			if ( array_key_exists( $k, $f ) ) $all[ $id ][ $k ] = $f[ $k ];
		}
		$all[ $id ]['hosted_payment']['paid_at'] = isset( $f['hosted_payment']['paid_at'] ) ? $f['hosted_payment']['paid_at'] : null;
		foreach ( $over as $k => $v ) $all[ $id ][ $k ] = $v;
		update_option( 'efb_e2e_payplug_payments', $all, false );
		return true;
	}

	function efb_e2e_payplug_answer( $code, $body ) {
		return array( 'headers' => array( 'content-type' => 'application/json' ), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}

	function efb_e2e_payplug_http( $pre, $args, $url ) {
		$u = wp_parse_url( (string) $url );
		if ( ! is_array( $u ) || ( isset( $u['host'] ) ? $u['host'] : '' ) !== 'api.payplug.com' ) return $pre;
		$method = strtoupper( isset( $args['method'] ) ? $args['method'] : 'GET' );
		$h = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$body = isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null;
		$log = get_option( 'efb_e2e_payplug_log', array() );
		$log = is_array( $log ) ? array_slice( $log, -50 ) : array();
		$log[] = array( 'method' => $method, 'path' => isset( $u['path'] ) ? $u['path'] : '', 'version' => isset( $h['PayPlug-Version'] ) ? $h['PayPlug-Version'] : '', 'time' => time() );
		update_option( 'efb_e2e_payplug_log', $log, false );
		$auth = isset( $h['Authorization'] ) ? (string) $h['Authorization'] : '';
		if ( ( isset( $h['PayPlug-Version'] ) ? $h['PayPlug-Version'] : '' ) !== '2019-08-06' ) return efb_e2e_payplug_answer( 400, array( 'object' => 'error', 'message' => 'missing PayPlug-Version' ) );
		if ( $auth === 'Bearer ' . efb_e2e_payplug_key( 'test' ) ) $live = false;
		elseif ( $auth === 'Bearer ' . efb_e2e_payplug_key( 'live' ) ) $live = true;
		else return efb_e2e_payplug_answer( 401, efb_e2e_payplug_fixture( 'error-401' ) );
		$path = isset( $u['path'] ) ? $u['path'] : '';
		$all = efb_e2e_payplug_all();
		if ( $method === 'GET' && $path === '/v1/payments' ) {
			$l = efb_e2e_payplug_fixture( 'list-payments' );
			$l['data'][0]['is_live'] = $live;
			return efb_e2e_payplug_answer( 200, $l );
		}
		if ( $method === 'POST' && $path === '/v1/payments' ) {
			// Oney is a deferred payment: its amount comes as authorized_amount (Payplug's Oney rules).
			$amount = isset( $body['amount'] ) ? (int) $body['amount'] : ( isset( $body['authorized_amount'] ) && isset( $body['payment_method'] ) && strpos( (string) $body['payment_method'], 'oney_' ) === 0 ? (int) $body['authorized_amount'] : 0 );
			if ( $amount < 30 || $amount > 2000000 || ( isset( $body['currency'] ) ? $body['currency'] : '' ) !== 'EUR' || ! isset( $body['billing'], $body['shipping'] ) ) {
				return efb_e2e_payplug_answer( 400, efb_e2e_payplug_fixture( 'error-400' ) );
			}
			$p = efb_e2e_payplug_fixture( 'create-201' );
			$id = 'pay_' . wp_generate_password( 22, false, false );
			$p['id'] = $id;
			$p['is_live'] = $live;
			$p['amount'] = $amount;
			$p['billing'] = $body['billing'];
			$p['shipping'] = $body['shipping'];
			$p['metadata'] = isset( $body['metadata'] ) ? $body['metadata'] : array();
			$p['description'] = isset( $body['description'] ) ? $body['description'] : null;
			$p['hosted_payment']['return_url'] = isset( $body['hosted_payment']['return_url'] ) ? $body['hosted_payment']['return_url'] : '';
			$p['hosted_payment']['cancel_url'] = isset( $body['hosted_payment']['cancel_url'] ) ? $body['hosted_payment']['cancel_url'] : '';
			$p['hosted_payment']['payment_url'] = add_query_arg( 'id', $id, rest_url( 'efb-e2e/v1/payplug-pay' ) );
			$p['notification']['url'] = isset( $body['notification_url'] ) ? $body['notification_url'] : '';
			$all[ $id ] = $p;
			update_option( 'efb_e2e_payplug_payments', $all, false );
			return efb_e2e_payplug_answer( 201, $p );
		}
		if ( preg_match( '#^/v1/payments/(pay_[A-Za-z0-9]+)(/refunds)?$#', $path, $m ) ) {
			$id = $m[1];
			if ( ! isset( $all[ $id ] ) || (bool) $all[ $id ]['is_live'] !== $live ) return efb_e2e_payplug_answer( 404, array( 'object' => 'error', 'message' => 'not found' ) );
			$p = $all[ $id ];
			if ( empty( $m[2] ) && $method === 'GET' ) return efb_e2e_payplug_answer( 200, $p );
			if ( empty( $m[2] ) && $method === 'PATCH' ) {
				if ( empty( $p['is_paid'] ) ) efb_e2e_payplug_set( $id, 'get-failure-aborted' );
				return efb_e2e_payplug_answer( 200, efb_e2e_payplug_all()[ $id ] );
			}
			$refunds = get_option( 'efb_e2e_payplug_refunds', array() );
			$refunds = is_array( $refunds ) ? $refunds : array();
			if ( ! empty( $m[2] ) && $method === 'GET' ) {
				$data = array_values( array_filter( $refunds, static function ( $r ) use ( $id ) { return $r['payment_id'] === $id; } ) );
				return efb_e2e_payplug_answer( 200, array( 'object' => 'list', 'page' => 0, 'per_page' => 100, 'has_more' => false, 'data' => $data ) );
			}
			if ( ! empty( $m[2] ) && $method === 'POST' ) {
				$amt = isset( $body['amount'] ) ? (int) $body['amount'] : $p['amount'] - $p['amount_refunded'];
				if ( empty( $p['is_paid'] ) || $amt < 10 || $amt > $p['amount'] - $p['amount_refunded'] ) return efb_e2e_payplug_answer( 400, array( 'object' => 'error', 'message' => 'not refundable' ) );
				$r = efb_e2e_payplug_fixture( 'refund-201' );
				$r['id'] = 're_' . wp_generate_password( 22, false, false );
				$r['payment_id'] = $id;
				$r['amount'] = $amt;
				$r['is_live'] = $live;
				$r['metadata'] = isset( $body['metadata'] ) ? $body['metadata'] : array();
				$refunds[] = $r;
				update_option( 'efb_e2e_payplug_refunds', $refunds, false );
				$all[ $id ]['amount_refunded'] += $amt;
				if ( $all[ $id ]['amount_refunded'] >= $p['amount'] ) $all[ $id ]['is_refunded'] = true;
				update_option( 'efb_e2e_payplug_payments', $all, false );
				return efb_e2e_payplug_answer( 201, $r );
			}
		}
		return efb_e2e_payplug_answer( 404, array( 'object' => 'error', 'message' => 'not found' ) );
	}

	function efb_e2e_payplug_token( $id ) {
		return substr( hash_hmac( 'sha256', 'payplug-pay|' . $id, wp_salt( 'nonce' ) ), 0, 24 );
	}

	/** The fake hosted payment page: Pay / Fail / Cancel, then back to return_url or cancel_url. */
	function efb_e2e_payplug_page( WP_REST_Request $r ) {
		$id = (string) $r->get_param( 'id' );
		$all = efb_e2e_payplug_all();
		if ( ! isset( $all[ $id ] ) ) return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		$p = $all[ $id ];
		if ( $r->get_method() === 'POST' ) {
			if ( ! hash_equals( efb_e2e_payplug_token( $id ), (string) $r->get_param( 't' ) ) ) return new WP_REST_Response( array( 'error' => 'bad_token' ), 403 );
			$o = (string) $r->get_param( 'outcome' );
			if ( $o === 'paid' ) efb_e2e_payplug_set( $id, 'get-paid' );
			if ( $o === 'failed' ) efb_e2e_payplug_set( $id, 'get-failure-declined' );
			// A cancel on Payplug's page: no notification and nothing changes until the payment times out.
			$res = new WP_REST_Response( null, 303 );
			$res->header( 'Location', $o === 'canceled' ? $p['hosted_payment']['cancel_url'] : $p['hosted_payment']['return_url'] );
			return $res;
		}
		$amount = esc_html( number_format( $p['amount'] / 100, 2, '.', '' ) . ' ' . $p['currency'] );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-store' );
		echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payplug (fake)</title></head><body>'
			. '<h1>Payplug test payment page (fake)</h1><p>' . ( $p['is_live'] ? '' : 'TEST. ' ) . 'Montant : <strong id="amount">' . $amount . '</strong></p>'
			. '<p id="billing">' . esc_html( trim( ( isset( $p['billing']['first_name'] ) ? $p['billing']['first_name'] : '' ) . ' ' . ( isset( $p['billing']['last_name'] ) ? $p['billing']['last_name'] : '' ) . ', ' . ( isset( $p['billing']['city'] ) ? $p['billing']['city'] : '' ) . ' ' . ( isset( $p['billing']['country'] ) ? $p['billing']['country'] : '' ) ) ) . '</p>'
			. '<form method="post" action="' . esc_url( rest_url( 'efb-e2e/v1/payplug-pay' ) ) . '"><input type="hidden" name="id" value="' . esc_attr( $id ) . '"><input type="hidden" name="t" value="' . esc_attr( efb_e2e_payplug_token( $id ) ) . '">'
			. '<button id="pay" name="outcome" value="paid">Payer</button> <button id="fail" name="outcome" value="failed">Refuser</button> <button id="cancel" name="outcome" value="canceled">Annuler</button></form></body></html>';
		exit;
	}

	if ( get_option( 'efb_e2e_payplug_fake' ) ) {
		add_filter( 'pre_http_request', 'efb_e2e_payplug_http', 10, 3 );
		add_action( 'rest_api_init', static function () {
			register_rest_route( 'efb-e2e/v1', 'payplug-pay', array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => 'efb_e2e_payplug_page',
				'permission_callback' => '__return_true',
			) );
		} );
	}
}
