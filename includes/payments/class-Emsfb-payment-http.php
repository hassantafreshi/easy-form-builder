<?php
/**
 * PAY00: the only way a gateway reaches the network (docs §5 "testability contract", §14).
 *
 * Http::request() wraps wp_remote_request() with a timeout, one retry for GET only, and a host
 * allow-list per gateway (SSRF: a gateway may only call its own API hosts). Tests answer through
 * the pre_http_request filter, so no test ever opens a connection.
 *
 * Redactor removes secrets and card data before anything is logged (event payloads, error_log).
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Http {

	/**
	 * @param string   $method        GET | POST | DELETE | PATCH
	 * @param string   $url           https URL
	 * @param array    $args          headers, body (array → JSON), timeout (s), query (array)
	 * @param string[] $allowed_hosts hosts the caller may reach (exact match, or ".example.com" for sub-domains)
	 * @return array{ok:bool,code:int,headers:array,body:string,json:mixed,error:string}
	 */
	public static function request( $method, $url, array $args = array(), array $allowed_hosts = array() ) {
		$out = array( 'ok' => false, 'code' => 0, 'headers' => array(), 'body' => '', 'json' => null, 'error' => '' );
		$method = strtoupper( (string) $method );
		if ( ! empty( $args['query'] ) && is_array( $args['query'] ) ) {
			$url = add_query_arg( array_map( 'rawurlencode', $args['query'] ), $url );
		}
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
		$host = is_array( $parts ) && isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' || $host === '' || ! self::host_allowed( $host, $allowed_hosts ) ) {
			$out['error'] = 'host_not_allowed';
			return $out;
		}
		$headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		$body = isset( $args['body'] ) ? $args['body'] : null;
		if ( is_array( $body ) ) {
			$body = json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( ! isset( $headers['Content-Type'] ) ) $headers['Content-Type'] = 'application/json';
		}
		$req = array(
			'method'      => $method,
			'timeout'     => isset( $args['timeout'] ) ? max( 1, (int) $args['timeout'] ) : 15,
			'headers'     => $headers,
			'redirection' => 0,
			'user-agent'  => 'EasyFormBuilder/' . ( defined( 'EMSFB_PLUGIN_VERSION' ) ? EMSFB_PLUGIN_VERSION : '0' ) . '; payments',
		);
		if ( $body !== null ) $req['body'] = $body;
		$tries = $method === 'GET' ? 2 : 1;
		$res = null;
		for ( $i = 0; $i < $tries; $i++ ) {
			$res = wp_remote_request( $url, $req );
			if ( ! is_wp_error( $res ) ) break;
		}
		if ( is_wp_error( $res ) ) {
			$out['error'] = 'network:' . $res->get_error_code();
			return $out;
		}
		$out['code'] = (int) wp_remote_retrieve_response_code( $res );
		$out['body'] = (string) wp_remote_retrieve_body( $res );
		$h = wp_remote_retrieve_headers( $res );
		$out['headers'] = is_object( $h ) && method_exists( $h, 'getAll' ) ? $h->getAll() : ( is_array( $h ) ? $h : array() );
		$out['json'] = json_decode( $out['body'], true );
		$out['ok'] = $out['code'] >= 200 && $out['code'] < 300;
		if ( ! $out['ok'] ) $out['error'] = 'http_' . $out['code'];
		return $out;
	}

	public static function host_allowed( $host, array $allowed ) {
		foreach ( $allowed as $a ) {
			$a = strtolower( (string) $a );
			if ( $a === '' ) continue;
			if ( $a[0] === '.' ) {
				if ( substr( $host, -strlen( $a ) ) === $a || $host === substr( $a, 1 ) ) return true;
			} elseif ( $host === $a ) {
				return true;
			}
		}
		return false;
	}
}

class Redactor {

	const SECRET_KEY_RE = '/(authori[sz]ation|secret|token|password|passwd|api_?key|apikey|client_secret|signature|cvv|cvc|card_?number|^pan$|^number$)/i';

	/** A copy of $data with secrets replaced by *** and card numbers cut to their last four digits. */
	public static function redact( $data, $depth = 0 ) {
		if ( $depth > 12 ) return '***';
		if ( is_object( $data ) ) $data = get_object_vars( $data );
		if ( is_array( $data ) ) {
			$out = array();
			foreach ( $data as $k => $v ) {
				if ( is_string( $k ) && preg_match( self::SECRET_KEY_RE, $k ) ) {
					$out[ $k ] = is_scalar( $v ) && preg_match( '/^\d{13,19}$/', (string) $v ) ? '****' . substr( (string) $v, -4 ) : '***';
					continue;
				}
				$out[ $k ] = self::redact( $v, $depth + 1 );
			}
			return $out;
		}
		if ( is_string( $data ) ) return self::redact_string( $data );
		return $data;
	}

	/** Card-like digit runs (13-19) and bearer tokens inside free text. */
	public static function redact_string( $s ) {
		$s = preg_replace_callback( '/\b\d{13,19}\b/', function ( $m ) { return '****' . substr( $m[0], -4 ); }, (string) $s );
		$s = preg_replace( '/(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]+/i', '$1 ***', $s );
		return preg_replace( '/\b(sk|pk|rk|live|test|whsec|skey|pkey)_[A-Za-z0-9_]{6,}/', '$1_***', $s );
	}
}
