<?php
/**
 * PAY00 §13: gateway settings and their secrets.
 *
 * Stored in the option emsfb_pgw_<slug> (autoload off), never in the form JSON (which reaches
 * every visitor) and never in emsfb_settings:
 *   { v:1, mode:'test'|'live', public:{key:value, test:{…}, live:{…}}, secret:{test:{key:'enc:v1:…'}, live:{…}}, updated_at }
 *
 * Encryption: sodium_crypto_secretbox (WordPress ships sodium_compat when the extension is
 * missing), key = BLAKE2b('efb-payments-v1' . (EFB_PAYMENTS_KEY or wp_salt('secure_auth'))).
 * When the salts change, decrypt() fails and the admin is asked to enter the keys again; a
 * payment is never created with an unreadable key. A secret is never sent back to a browser:
 * the settings screen shows "•••• abcd" only.
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Secrets {

	const PREFIX = 'enc:v1:';

	private static function key() {
		$material = 'efb-payments-v1' . ( defined( 'EFB_PAYMENTS_KEY' ) && EFB_PAYMENTS_KEY ? EFB_PAYMENTS_KEY : ( function_exists( 'wp_salt' ) ? wp_salt( 'secure_auth' ) : '' ) );
		if ( function_exists( 'sodium_crypto_generichash' ) ) {
			return sodium_crypto_generichash( $material, '', 32 );
		}
		return hash( 'sha256', $material, true );
	}

	public static function available() {
		return function_exists( 'sodium_crypto_secretbox' ) || function_exists( 'openssl_encrypt' );
	}

	/** Plain text → 'enc:v1:…' (or '' for an empty value). */
	public static function encrypt( $plain ) {
		$plain = (string) $plain;
		if ( $plain === '' ) return '';
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( 24 );
			return self::PREFIX . 's' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key() ) );
		}
		$iv = random_bytes( 12 );
		$tag = '';
		$c = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		return self::PREFIX . 'g' . base64_encode( $iv . $tag . $c );
	}

	/** 'enc:v1:…' → plain text, or null when it cannot be read (other salts, tampered, not ours). */
	public static function decrypt( $enc ) {
		if ( ! is_string( $enc ) || strpos( $enc, self::PREFIX ) !== 0 ) return null;
		$kind = substr( $enc, strlen( self::PREFIX ), 1 );
		$bin = base64_decode( substr( $enc, strlen( self::PREFIX ) + 1 ), true );
		if ( $bin === false ) return null;
		if ( $kind === 's' && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			if ( strlen( $bin ) < 24 + 16 ) return null;
			try {
				$p = sodium_crypto_secretbox_open( substr( $bin, 24 ), substr( $bin, 0, 24 ), self::key() );
			} catch ( \Throwable $e ) {
				return null;
			}
			return $p === false ? null : $p;
		}
		if ( $kind === 'g' && function_exists( 'openssl_decrypt' ) ) {
			if ( strlen( $bin ) < 28 ) return null;
			$p = openssl_decrypt( substr( $bin, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $bin, 0, 12 ), substr( $bin, 12, 16 ) );
			return $p === false ? null : $p;
		}
		return null;
	}

	/** "•••• abcd" (last four characters) for the settings screen; '' when empty. */
	public static function mask( $plain ) {
		$plain = (string) $plain;
		if ( $plain === '' ) return '';
		return '•••• ' . ( strlen( $plain ) > 8 ? substr( $plain, -4 ) : '' );
	}
}

class Settings {

	public $slug = '';
	private $data = array();

	public static function option_name( $slug ) {
		return 'emsfb_pgw_' . preg_replace( '/[^a-z0-9]/', '', (string) $slug );
	}

	public static function load( $slug ) {
		$s = new self();
		$s->slug = (string) $slug;
		$raw = function_exists( 'get_option' ) ? get_option( self::option_name( $slug ), array() ) : array();
		if ( is_string( $raw ) ) $raw = json_decode( $raw, true );
		$s->data = is_array( $raw ) ? $raw : array();
		return $s;
	}

	public function mode() {
		return isset( $this->data['mode'] ) && $this->data['mode'] === 'live' ? 'live' : 'test';
	}

	/** A public (not secret) value; per-mode values under public[mode][key]. */
	public function get( $key, $mode = null ) {
		$mode = $mode === null ? $this->mode() : $mode;
		$p = isset( $this->data['public'] ) && is_array( $this->data['public'] ) ? $this->data['public'] : array();
		if ( isset( $p[ $mode ] ) && is_array( $p[ $mode ] ) && array_key_exists( $key, $p[ $mode ] ) ) return $p[ $mode ][ $key ];
		return array_key_exists( $key, $p ) && ! is_array( $p[ $key ] ) ? $p[ $key ] : null;
	}

	/** A decrypted secret of $mode, '' when not set, null when it cannot be read. */
	public function secret( $key, $mode = null ) {
		$mode = $mode === null ? $this->mode() : $mode;
		$enc = isset( $this->data['secret'][ $mode ][ $key ] ) ? $this->data['secret'][ $mode ][ $key ] : '';
		if ( $enc === '' ) return '';
		return Secrets::decrypt( $enc );
	}

	/** 'ok' | 'missing' | 'unreadable' for the secret. */
	public function secret_state( $key, $mode ) {
		$enc = isset( $this->data['secret'][ $mode ][ $key ] ) ? $this->data['secret'][ $mode ][ $key ] : '';
		if ( $enc === '' ) return 'missing';
		return Secrets::decrypt( $enc ) === null ? 'unreadable' : 'ok';
	}

	public function raw() {
		return $this->data;
	}

	/** Cached provider data (e.g. the account's methods) for $ttl seconds. */
	public function cache( $key, $value = null ) {
		if ( $value === null ) return isset( $this->data['cache'][ $key ] ) ? $this->data['cache'][ $key ] : null;
		$this->data['cache'][ $key ] = $value;
		$this->persist();
		return $value;
	}

	/**
	 * Applies a settings form post ($in: key => value, '<key>__<mode>' for per-mode fields).
	 * A secret left empty keeps the stored one; '__clear' as the value removes it. Values are
	 * checked against the schema (type, pattern, options) and anything not in it is dropped.
	 *
	 * @return array list of field keys that were refused (pattern).
	 */
	public function apply( array $schema, array $in, $mode = null ) {
		$refused = array();
		if ( $mode !== null ) $this->data['mode'] = $mode === 'live' ? 'live' : 'test';
		foreach ( $schema as $f ) {
			if ( ! is_array( $f ) || empty( $f['key'] ) || ! preg_match( '/^[a-z0-9_]{1,40}$/', $f['key'] ) ) continue;
			$modes = ! empty( $f['per_mode'] ) ? array( 'test', 'live' ) : array( '' );
			foreach ( $modes as $m ) {
				$name = $m === '' ? $f['key'] : $f['key'] . '__' . $m;
				if ( ! array_key_exists( $name, $in ) ) continue;
				$type = isset( $f['type'] ) ? $f['type'] : 'text';
				$v = is_scalar( $in[ $name ] ) ? trim( (string) $in[ $name ] ) : '';
				if ( $type === 'secret' ) {
					$sm = $m === '' ? 'test' : $m;
					$targets = $m === '' ? array( 'test', 'live' ) : array( $sm );
					if ( $v === '' ) continue;
					if ( $v === '__clear' ) { foreach ( $targets as $tm ) unset( $this->data['secret'][ $tm ][ $f['key'] ] ); continue; }
					if ( ! empty( $f['pattern'] ) && ! @preg_match( '/' . str_replace( '/', '\/', $f['pattern'] ) . '/', $v ) ) { $refused[] = $name; continue; }
					foreach ( $targets as $tm ) $this->data['secret'][ $tm ][ $f['key'] ] = Secrets::encrypt( $v );
					continue;
				}
				if ( $type === 'checkbox' ) $v = in_array( $v, array( '1', 'on', 'true' ), true ) ? '1' : '';
				elseif ( $type === 'select' ) $v = isset( $f['options'] ) && is_array( $f['options'] ) && array_key_exists( $v, $f['options'] ) ? $v : '';
				else $v = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $v ) : strip_tags( $v );
				if ( $v !== '' && ! empty( $f['pattern'] ) && ! @preg_match( '/' . str_replace( '/', '\/', $f['pattern'] ) . '/', $v ) ) { $refused[] = $name; continue; }
				if ( $m === '' ) $this->data['public'][ $f['key'] ] = $v; else $this->data['public'][ $m ][ $f['key'] ] = $v;
			}
		}
		$this->persist();
		return $refused;
	}

	public function set_mode( $mode ) {
		$this->data['mode'] = $mode === 'live' ? 'live' : 'test';
		$this->persist();
	}

	public function persist() {
		$this->data['v'] = 1;
		$this->data['updated_at'] = gmdate( 'c' );
		if ( function_exists( 'update_option' ) ) update_option( self::option_name( $this->slug ), $this->data, false );
	}
}
