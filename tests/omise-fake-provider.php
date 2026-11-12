<?php
/**
 * Not a test. A fake api.omise.co for the real-WordPress e2e scenario "omise" (PAY04): loaded by the
 * mu-plugin tests/e2e-wp/mu-plugins/efb-e2e-omise.php while the option efb_e2e_omise_fake is set.
 * Every request the Omise add-on makes goes through core's Http::request() → wp_remote_request() →
 * pre_http_request, and is answered here from the shapes of the official examples
 * (tests/fixtures/payments/omise/*.json). Charges live in the option efb_e2e_omise_charges.
 * Keys are fake and assembled at run time. Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Efb_Test_Omise_Fake {

	const OPTION = 'efb_e2e_omise_charges';

	/** Fake keys (no key-shaped literal in the repository). */
	public static function keys() {
		$t = 'test' . '_';
		return array(
			'skey_test'  => 'skey_' . $t . 'efbe2efixture001',
			'skey_live'  => 'skey_' . 'efbe2efixturelive01',
			'pkey_test'  => 'pkey_' . $t . 'efbe2efixture001',
			'pkey_live'  => 'pkey_' . 'efbe2efixturelive01',
			'whsec_test' => base64_encode( 'efb-e2e-omise-webhook-secret-test' ),
			'whsec_live' => base64_encode( 'efb-e2e-omise-webhook-secret-live' ),
		);
	}

	public static function register() {
		add_filter( 'pre_http_request', array( __CLASS__, 'http' ), 10, 3 );
	}

	private static function fixture( $name ) {
		$f = dirname( __FILE__ ) . '/fixtures/payments/omise/' . $name;
		if ( substr( $name, -4 ) === '.svg' ) return (string) file_get_contents( $f );
		$d = json_decode( (string) file_get_contents( $f ), true );
		return $d['response']['body'];
	}

	public static function all() {
		$v = get_option( self::OPTION, array() );
		return is_array( $v ) ? $v : array();
	}

	/** Omise-side change, like the Test dashboard's "Mark as successful / failed". */
	public static function set_status( $id, $status, $amount = null ) {
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) return false;
		$c = &$all[ $id ];
		$c['status'] = $status;
		$c['paid'] = $status === 'successful';
		$c['paid_at'] = $status === 'successful' ? gmdate( 'Y-m-d\TH:i:s\Z' ) : null;
		$c['failure_code'] = $status === 'failed' ? 'insufficient_balance' : null;
		if ( $amount !== null && $amount !== '' ) $c['amount'] = (int) $amount;
		update_option( self::OPTION, $all, false );
		return true;
	}

	private static function answer( $code, $body, $type = 'application/json' ) {
		return array( 'headers' => array( 'content-type' => $type ), 'body' => is_string( $body ) ? $body : wp_json_encode( $body ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
	}

	public static function http( $pre, $args, $url ) {
		if ( wp_parse_url( (string) $url, PHP_URL_HOST ) !== 'api.omise.co' ) return $pre;
		$method = isset( $args['method'] ) ? strtoupper( $args['method'] ) : 'GET';
		$auth = isset( $args['headers']['Authorization'] ) ? (string) $args['headers']['Authorization'] : '';
		$key = strpos( $auth, 'Basic ' ) === 0 ? rtrim( (string) base64_decode( substr( $auth, 6 ) ), ':' ) : '';
		$k = self::keys();
		$live = in_array( $key, array( $k['skey_live'], $k['pkey_live'] ), true );
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		if ( $path === '/capability' ) return in_array( $key, array( $k['pkey_test'], $k['pkey_live'] ), true ) ? self::answer( 200, self::fixture( 'capability.json' ) ) : self::answer( 401, self::fixture( 'error-authentication.json' ) );
		if ( ! in_array( $key, array( $k['skey_test'], $k['skey_live'] ), true ) ) return self::answer( 401, self::fixture( 'error-authentication.json' ) );
		if ( $path === '/account' ) {
			$a = self::fixture( 'account.json' );
			$a['livemode'] = $live;
			return self::answer( 200, $a );
		}
		$all = self::all();
		if ( $method === 'POST' && $path === '/charges' ) {
			$q = array();
			foreach ( explode( '&', (string) $args['body'] ) as $kv ) {
				$p = array_pad( explode( '=', $kv, 2 ), 2, '' );
				$q[ rawurldecode( $p[0] ) ] = rawurldecode( $p[1] );
			}
			foreach ( $all as $c ) {
				if ( isset( $q['ref_id'] ) && $c['ref_id'] === $q['ref_id'] ) return self::answer( 400, array( 'object' => 'error', 'code' => 'bad_request', 'message' => 'ref_id has already been used' ) );
			}
			$c = self::fixture( 'charge-promptpay-pending.json' );
			$id = 'chrg_' . ( $live ? '' : 'test_' ) . 'e2e' . strtolower( wp_generate_password( 14, false, false ) );
			$c['id'] = $id;
			$c['livemode'] = $live;
			$c['amount'] = (int) $q['amount'];
			$c['currency'] = (string) $q['currency'];
			$c['expires_at'] = (string) $q['expires_at'];
			$c['ref_id'] = (string) $q['ref_id'];
			$c['description'] = (string) ( $q['description'] ?? '' );
			$c['metadata'] = array();
			foreach ( $q as $mk => $mv ) {
				if ( preg_match( '/^metadata\[(\w+)\]$/', $mk, $m ) ) $c['metadata'][ $m[1] ] = $mv;
			}
			$c['source']['scannable_code']['image']['download_uri'] = 'https://api.omise.co/charges/' . $id . '/documents/docu_test_e2e/downloads/47B72F81B5294A7A';
			$all[ $id ] = $c;
			update_option( self::OPTION, $all, false );
			return self::answer( 200, $c );
		}
		if ( $method === 'GET' && preg_match( '#^/charges/(chrg_[a-z0-9_]+)/documents/[a-z0-9_]+/downloads/[A-F0-9]+$#', $path, $m ) ) {
			return isset( $all[ $m[1] ] ) ? self::answer( 200, self::fixture( 'qrcode.svg' ), 'image/svg+xml' ) : self::answer( 404, array( 'object' => 'error', 'code' => 'not_found' ) );
		}
		if ( $method === 'GET' && preg_match( '#^/charges/(chrg_[a-z0-9_]+)$#', $path, $m ) ) {
			return isset( $all[ $m[1] ] ) ? self::answer( 200, $all[ $m[1] ] ) : self::answer( 404, array( 'object' => 'error', 'code' => 'not_found', 'message' => 'charge was not found' ) );
		}
		return self::answer( 404, array( 'object' => 'error', 'code' => 'not_found' ) );
	}
}
