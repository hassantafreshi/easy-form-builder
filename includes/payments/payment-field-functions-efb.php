<?php
/**
 * PAY00 §10: the customizable payment field of a framework gateway.
 *
 * The data is a set of flat scalar keys (pf_*) on the gateway field row, never CSS or HTML.
 * Field::sanitize_key / resolve / texts / css / html are the PHP twin of efbPayCoreEfb in
 * public/assets/js/payment-core-efb.js: change one, change the other. The vectors in
 * tests/fixtures/pay-field-vectors.json hold both to the same output byte for byte
 * (node tests/test-pay-field-parity.js).
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Field {

	const ELEMENTS = array( 'summary', 'amount', 'coupon', 'methods', 'fields', 'button', 'secure', 'logos' );
	const HIDEABLE = array( 'summary', 'amount', 'coupon', 'methods', 'secure', 'logos' );
	const ICONS = array( 'none', 'bi-lock', 'bi-lock-fill', 'bi-credit-card', 'bi-wallet2', 'bi-qr-code', 'bi-bag-check', 'bi-heart' );
	const TEXT_KEYS = array( 'btn', 'secure', 'summary', 'methods', 'pending', 'success', 'fail', 'redirect', 'test', 'amount', 'other', 'total' );

	/** Style keys a gateway may give a default for (Gateway::field_defaults()['pf']); the field's own value wins. */
	const DEFAULTABLE = array( 'pf_btn_bg', 'pf_btn_fg', 'pf_btn_bg_h', 'pf_btn_fg_h', 'pf_btn_radius', 'pf_btn_size', 'pf_btn_width', 'pf_btn_icon',
		'pf_btn_icon_pos', 'pf_order', 'pf_hide', 'pf_layout', 'pf_methods_style', 'pf_summary', 'pf_label_pos', 'pf_card_bg', 'pf_card_border',
		'pf_card_radius', 'pf_accent', 'pf_logo_size' );

	/** The standard payer options of a gateway field (Gateway::field_schema()): key → Checkout_Context::$payer key. */
	const PAYER_KEYS = array( 'pgw_map_name' => 'name', 'pgw_map_first' => 'first_name', 'pgw_map_last' => 'last_name', 'pgw_map_email' => 'email',
		'pgw_map_phone' => 'phone', 'pgw_map_address' => 'address', 'pgw_map_postcode' => 'postcode', 'pgw_map_city' => 'city',
		'pgw_map_state' => 'state', 'pgw_map_country' => 'country' );

	/** Types of a field_schema() entry. */
	const OPTION_TYPES = array( 'field', 'select', 'text', 'code', 'checkbox' );

	/** Keys on the gateway field row. */
	public static function keys() {
		return array(
			'pf_btn_text', 'pf_btn_bg', 'pf_btn_fg', 'pf_btn_bg_h', 'pf_btn_fg_h', 'pf_btn_radius', 'pf_btn_size', 'pf_btn_width',
			'pf_btn_icon', 'pf_btn_icon_pos', 'pf_order', 'pf_hide', 'pf_layout', 'pf_methods_style', 'pf_methods', 'pf_summary',
			'pf_label_pos', 'pf_card_bg', 'pf_card_border', 'pf_card_radius', 'pf_accent', 'pf_logo_size', 'pf_digits', 'pf_i18n_b64',
			'pf_secure_text', 'pf_t_summary', 'pf_t_methods', 'pf_t_pending', 'pf_t_success', 'pf_t_fail', 'pf_t_amount',
			'pf_fixed', 'pf_fixed_label', 'pf_amount', 'pf_presets', 'pf_other', 'pf_default', 'pf_min', 'pf_max',
			'mobile_pf_order', 'mobile_pf_hide', 'mobile_pf_btn_size', 'mobile_pf_btn_width', 'mobile_pf_methods_style',
		);
	}

	/** Row-0 keys: when each notification channel runs for a payment form (§7.5). */
	public static function form_keys() {
		return array( 'pay_act_admin_mail', 'pay_act_user_mail', 'pay_act_sms', 'pay_act_tlg', 'pay_act_gs', 'pay_act_wh', 'pay_fail_notify' );
	}

	public static function is_key( $k ) {
		return in_array( $k, self::keys(), true ) || in_array( $k, self::form_keys(), true );
	}

	/* ------------------------------------------------------------------ sanitize */

	public static function hex( $v ) {
		if ( ! is_string( $v ) ) return '';
		$v = trim( $v );
		if ( ! preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v, $m ) ) return '';
		$h = strtolower( $m[1] );
		if ( strlen( $h ) === 3 ) $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		return '#' . $h;
	}

	public static function int( $v, $min, $max ) {
		if ( is_bool( $v ) ) return '';
		if ( is_int( $v ) || is_float( $v ) ) {
			$n = (float) $v;
		} elseif ( is_string( $v ) && preg_match( '/^-?\d{1,6}(\.\d{1,6})?$/', trim( $v ) ) ) {
			$n = (float) trim( $v );
		} else {
			return '';
		}
		$n = (int) floor( $n + 0.5 );
		return (string) max( $min, min( $max, $n ) );
	}

	/** Plain text: no tags, no control characters, single spaces, at most $max code points. */
	public static function text( $v, $max ) {
		if ( ! is_string( $v ) || ! preg_match( '//u', $v ) ) return '';
		$v = preg_replace( '/[\x{0000}-\x{001F}\x{007F}\x{2028}\x{2029}<>]/u', ' ', $v );
		$v = trim( preg_replace( '/ +/', ' ', $v ), ' ' );
		if ( preg_match_all( '/./us', $v, $m ) > $max ) $v = implode( '', array_slice( $m[0], 0, $max ) );
		return trim( $v, ' ' );
	}

	/** A non-negative decimal amount in major units ("25", "12.5"); Persian/Arabic digits accepted. */
	public static function decimal( $v ) {
		if ( is_int( $v ) ) $v = (string) $v;
		if ( ! is_string( $v ) ) return '';
		$v = Money::latin_digits( trim( $v ) );
		return preg_match( '/^\d{1,9}(\.\d{1,4})?$/', $v ) ? $v : '';
	}

	/** Comma list from an allow-list, each id once, given order kept. */
	public static function id_list( $v, array $allowed ) {
		if ( ! is_string( $v ) ) return '';
		$out = array();
		foreach ( explode( ',', $v ) as $p ) {
			$p = trim( $p );
			if ( in_array( $p, $allowed, true ) && ! in_array( $p, $out, true ) ) $out[] = $p;
		}
		return implode( ',', $out );
	}

	public static function presets( $v ) {
		if ( ! is_string( $v ) || $v === '' ) return array();
		$out = array();
		foreach ( explode( ',', $v ) as $p ) {
			$d = self::decimal( $p );
			if ( $d !== '' && (float) $d > 0 && ! in_array( $d, $out, true ) ) $out[] = $d;
			if ( count( $out ) >= 8 ) break;
		}
		return $out;
	}

	public static function b64url_encode( $s ) {
		return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' );
	}

	public static function b64url_decode( $s ) {
		if ( ! is_string( $s ) || $s === '' || strlen( $s ) > 40000 || ! preg_match( '/^[A-Za-z0-9_-]+$/', $s ) ) return null;
		$r = base64_decode( strtr( $s, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $s ) % 4 ) % 4 ), true );
		return $r === false ? null : $r;
	}

	/** pf_i18n_b64 → array locale => [key => text] (clean), or array(). */
	public static function i18n_decode( $v ) {
		$json = self::b64url_decode( $v );
		if ( $json === null ) return array();
		$d = json_decode( $json, true );
		if ( ! is_array( $d ) ) return array();
		$out = array();
		foreach ( $d as $loc => $texts ) {
			if ( count( $out ) >= 30 ) break;
			if ( ! is_string( $loc ) || ! preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/', $loc ) || ! is_array( $texts ) ) continue;
			$clean = array();
			foreach ( self::TEXT_KEYS as $k ) {
				if ( isset( $texts[ $k ] ) ) {
					$t = self::text( $texts[ $k ], 200 );
					if ( $t !== '' ) $clean[ $k ] = $t;
				}
			}
			if ( $clean ) $out[ $loc ] = $clean;
		}
		return $out;
	}

	public static function i18n_encode( array $d ) {
		if ( empty( $d ) ) return '';
		$j = json_encode( $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return is_string( $j ) ? self::b64url_encode( $j ) : '';
	}

	/** The save-side sanitizer of one key (sanitize_obj_msg_efb() routes pf_* / pay_act_* here). '' = not set. */
	public static function sanitize_key( $k, $v ) {
		$base = strpos( $k, 'mobile_' ) === 0 ? substr( $k, 7 ) : $k;
		switch ( $base ) {
			case 'pf_btn_text':
			case 'pf_fixed_label':
				return self::text( $v, 60 );
			case 'pf_secure_text':
			case 'pf_t_summary':
			case 'pf_t_methods':
			case 'pf_t_pending':
			case 'pf_t_success':
			case 'pf_t_fail':
			case 'pf_t_amount':
				return self::text( $v, 200 );
			case 'pf_btn_fg':
				if ( $v === 'auto' ) return 'auto';
				return self::hex( $v );
			case 'pf_btn_bg':
			case 'pf_btn_bg_h':
			case 'pf_btn_fg_h':
			case 'pf_card_bg':
			case 'pf_card_border':
			case 'pf_accent':
				return self::hex( $v );
			case 'pf_btn_radius':
				return self::int( $v, 0, 40 );
			case 'pf_card_radius':
				return self::int( $v, 0, 24 );
			case 'pf_btn_size':
				return is_string( $v ) && in_array( $v, array( 'sm', 'md', 'lg' ), true ) ? $v : '';
			case 'pf_btn_width':
				return is_string( $v ) && in_array( $v, array( 'auto', 'full' ), true ) ? $v : '';
			case 'pf_btn_icon':
				return is_string( $v ) && in_array( $v, self::ICONS, true ) ? $v : '';
			case 'pf_btn_icon_pos':
				return is_string( $v ) && in_array( $v, array( 'start', 'end' ), true ) ? $v : '';
			case 'pf_order':
				return self::id_list( $v, self::ELEMENTS );
			case 'pf_hide':
				return self::id_list( $v, self::HIDEABLE );
			case 'pf_layout':
				return is_string( $v ) && in_array( $v, array( 'stack', 'split' ), true ) ? $v : '';
			case 'pf_methods_style':
				return is_string( $v ) && in_array( $v, array( 'list', 'grid', 'tabs', 'select' ), true ) ? $v : '';
			case 'pf_methods':
				if ( ! is_string( $v ) ) return '';
				$out = array();
				foreach ( explode( ',', $v ) as $p ) {
					$p = trim( $p );
					if ( preg_match( '/^[a-z0-9_]{1,30}$/', $p ) && ! in_array( $p, $out, true ) ) $out[] = $p;
					if ( count( $out ) >= 40 ) break;
				}
				return implode( ',', $out );
			case 'pf_summary':
				return is_string( $v ) && in_array( $v, array( 'total', 'lines' ), true ) ? $v : '';
			case 'pf_label_pos':
				return is_string( $v ) && in_array( $v, array( 'top', 'start', 'hidden' ), true ) ? $v : '';
			case 'pf_logo_size':
				return is_string( $v ) && in_array( $v, array( 'sm', 'md' ), true ) ? $v : '';
			case 'pf_digits':
				return is_string( $v ) && in_array( $v, array( 'latn', 'locale' ), true ) ? $v : '';
			case 'pf_i18n_b64':
				return self::i18n_encode( self::i18n_decode( $v ) );
			case 'pf_fixed':
			case 'pf_default':
			case 'pf_min':
			case 'pf_max':
				return self::decimal( $v );
			case 'pf_amount':
				return $v === 'donation' ? 'donation' : '';
			case 'pf_presets':
				return implode( ',', self::presets( $v ) );
			case 'pf_other':
				return in_array( $v, array( 1, '1', true ), true ) ? '1' : '';
			case 'pay_act_admin_mail':
			case 'pay_act_user_mail':
			case 'pay_act_sms':
			case 'pay_act_tlg':
			case 'pay_act_gs':
			case 'pay_act_wh':
				return is_string( $v ) && in_array( $v, array( 'paid', 'submit', 'both' ), true ) ? $v : '';
			case 'pay_fail_notify':
				return $v === 'admin' ? 'admin' : '';
		}
		return '';
	}

	/* ------------------------------------------------------------------ gateway options (pgw_*) */

	/** A field id of the same form ('' when it does not look like one). */
	public static function field_id( $v ) {
		if ( is_int( $v ) ) $v = (string) $v;
		return is_string( $v ) && preg_match( '/^[A-Za-z0-9_-]{1,40}$/', $v ) ? $v : '';
	}

	/**
	 * A gateway's field_schema() in the one shape core uses: bad entries dropped, every value typed.
	 * Twin: efbPayCoreEfb.cleanSchema().
	 *
	 * @return array list of array( key, type, label, help, required, options (list of [value, label]), max, field_types, default )
	 */
	public static function clean_schema( $schema ) {
		$out = array();
		$seen = array();
		if ( ! is_array( $schema ) ) return $out;
		foreach ( $schema as $d ) {
			if ( count( $out ) >= 40 ) break;
			if ( ! is_array( $d ) || ! isset( $d['key'], $d['type'] ) || ! is_string( $d['key'] ) || ! preg_match( '/^pgw_[a-z0-9_]{1,40}$/', $d['key'] ) ) continue;
			if ( ! is_string( $d['type'] ) || ! in_array( $d['type'], self::OPTION_TYPES, true ) || isset( $seen[ $d['key'] ] ) ) continue;
			$seen[ $d['key'] ] = true;
			$e = array(
				'key'         => $d['key'],
				'type'        => $d['type'],
				'label'       => self::text( isset( $d['label'] ) && is_string( $d['label'] ) ? $d['label'] : $d['key'], 120 ),
				'help'        => self::text( isset( $d['help'] ) && is_string( $d['help'] ) ? $d['help'] : '', 300 ),
				'required'    => ! empty( $d['required'] ),
				'options'     => array(),
				'max'         => 0,
				'field_types' => array(),
				'default'     => '',
			);
			if ( $d['type'] === 'select' && isset( $d['options'] ) && is_array( $d['options'] ) ) {
				foreach ( $d['options'] as $ov => $ol ) {
					if ( count( $e['options'] ) >= 50 ) break;
					// A clean schema again: [value, label] pairs.
					if ( is_array( $ol ) && count( $ol ) === 2 && isset( $ol[0], $ol[1] ) && is_scalar( $ol[0] ) ) { $ov = $ol[0]; $ol = $ol[1]; }
					$ov = (string) $ov;
					if ( preg_match( '/^[A-Za-z0-9_.-]{0,40}$/', $ov ) ) $e['options'][] = array( $ov, self::text( is_scalar( $ol ) ? (string) $ol : $ov, 120 ) );
				}
			}
			if ( $d['type'] === 'text' || $d['type'] === 'code' ) {
				$cap = $d['type'] === 'text' ? 200 : 60;
				$m = isset( $d['max'] ) && is_int( $d['max'] ) ? $d['max'] : $cap;
				$e['max'] = max( 1, min( $cap, $m ) );
			}
			if ( $d['type'] === 'field' && isset( $d['field_types'] ) && is_array( $d['field_types'] ) ) {
				foreach ( $d['field_types'] as $ft ) {
					if ( is_string( $ft ) && preg_match( '/^[A-Za-z]{1,30}$/', $ft ) && ! in_array( $ft, $e['field_types'], true ) ) $e['field_types'][] = $ft;
				}
			}
			if ( isset( $d['default'] ) && $d['type'] !== 'field' ) $e['default'] = self::option_value( $e, $d['default'] );
			$out[] = $e;
		}
		return $out;
	}

	/** One value of a clean schema entry ('' = not set). */
	public static function option_value( array $d, $v ) {
		switch ( $d['type'] ) {
			case 'field':
				return self::field_id( $v );
			case 'select':
				if ( ! is_string( $v ) ) return '';
				foreach ( $d['options'] as $o ) { if ( $o[0] === $v ) return $v; }
				return '';
			case 'text':
				return self::text( $v, $d['max'] );
			case 'code':
				if ( ! is_string( $v ) ) return '';
				$v = trim( $v );
				return preg_match( '/^[A-Za-z0-9_.-]+$/', $v ) && strlen( $v ) <= $d['max'] ? $v : '';
			case 'checkbox':
				return in_array( $v, array( 1, '1', true ), true ) ? '1' : '';
		}
		return '';
	}

	/**
	 * Save-side sanitizer of a gateway option (pgw_*), from the gateway's field_schema(). A key the
	 * schema does not know (the add-on is not active, or an older schema) is kept but cleaned
	 * conservatively: pgw_map_* as a field id, anything else as plain text of at most 200 characters.
	 * Twin: efbPayCoreEfb.sanitizeGatewayKey().
	 */
	public static function sanitize_gateway_key( $k, $v, $schema = null ) {
		if ( ! is_string( $k ) || ! preg_match( '/^pgw_[a-z0-9_]{1,40}$/', $k ) ) return '';
		$d = null;
		foreach ( self::clean_schema( $schema ) as $e ) { if ( $e['key'] === $k ) { $d = $e; break; } }
		if ( $d === null ) {
			if ( strpos( $k, 'pgw_map_' ) === 0 ) return self::field_id( $v );
			return is_string( $v ) || is_int( $v ) ? self::text( (string) $v, 200 ) : '';
		}
		return self::option_value( $d, $v );
	}

	/** The gateway field's options for Gateway::create(): pf_methods and every schema key (its default when empty). */
	public static function gateway_options( $row, $schema ) {
		$out = array( 'pf_methods' => self::sanitize_key( 'pf_methods', self::get( $row, 'pf_methods' ) ) );
		foreach ( self::clean_schema( $schema ) as $d ) {
			$v = self::option_value( $d, self::get( $row, $d['key'] ) );
			$out[ $d['key'] ] = $v !== '' ? $v : $d['default'];
		}
		return $out;
	}

	/**
	 * Checkout_Context::$payer from the mapped fields of the submitted rows. The e-mail falls back
	 * to $email (the form's confirmation e-mail field); the name is joined or split when only one
	 * side is mapped.
	 */
	public static function payer( array $rows, array $options, $email = '' ) {
		$by_id = array();
		foreach ( $rows as $r ) {
			if ( is_array( $r ) && isset( $r['id_'] ) && is_scalar( $r['id_'] ) && isset( $r['value'] ) && is_scalar( $r['value'] ) && ! isset( $by_id[ (string) $r['id_'] ] ) ) {
				$by_id[ (string) $r['id_'] ] = str_replace( '@efb@dq#', '"', (string) $r['value'] );
			}
		}
		$p = array( 'name' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'address' => '', 'postcode' => '', 'city' => '', 'state' => '', 'country' => '' );
		foreach ( self::PAYER_KEYS as $k => $to ) {
			$fid = isset( $options[ $k ] ) ? self::field_id( $options[ $k ] ) : '';
			if ( $fid !== '' && isset( $by_id[ $fid ] ) ) $p[ $to ] = self::text( $by_id[ $fid ], $to === 'phone' ? 40 : 190 );
		}
		if ( $p['email'] !== '' && ! filter_var( $p['email'], FILTER_VALIDATE_EMAIL ) ) $p['email'] = '';
		if ( $p['email'] === '' && is_string( $email ) && filter_var( $email, FILTER_VALIDATE_EMAIL ) ) $p['email'] = $email;
		if ( $p['name'] === '' && ( $p['first_name'] !== '' || $p['last_name'] !== '' ) ) $p['name'] = trim( $p['first_name'] . ' ' . $p['last_name'] );
		if ( $p['name'] !== '' && $p['first_name'] === '' && $p['last_name'] === '' ) {
			$parts = preg_split( '/\s+/u', $p['name'], 2 );
			$p['first_name'] = $parts[0];
			$p['last_name'] = isset( $parts[1] ) ? $parts[1] : '';
		}
		return $p;
	}

	/**
	 * Save-time checks of the gateway fields of a form, from what the gateways declare (no gateway
	 * code in core). $infos: slug => array( currencies, field_schema ) (Registry::public_info()).
	 * Twin: efbPayCoreEfb.warnings(). Returns codes with their data; the caller words them.
	 *
	 * @return array list of array( code, field_id, gateway, … )
	 */
	public static function warnings( $form, array $infos ) {
		$out = array();
		if ( ! is_array( $form ) || ! isset( $form[0] ) ) return $out;
		$types = array();
		foreach ( $form as $i => $r ) {
			if ( $i > 0 && is_array( $r ) && isset( $r['id_'] ) && is_scalar( $r['id_'] ) ) $types[ (string) $r['id_'] ] = isset( $r['type'] ) && is_string( $r['type'] ) ? $r['type'] : '';
		}
		$cur = self::get( $form[0], 'currency' );
		$cur = is_string( $cur ) ? strtoupper( trim( $cur ) ) : '';
		foreach ( $form as $i => $r ) {
			if ( $i === 0 || ! is_array( $r ) || ! isset( $r['type'], $r['id_'] ) || ! is_string( $r['type'] ) || ! is_scalar( $r['id_'] ) || ! isset( $infos[ $r['type'] ] ) ) continue;
			$g = $infos[ $r['type'] ];
			$base = array( 'field_id' => (string) $r['id_'], 'gateway' => $r['type'] );
			$allowed = isset( $g['currencies'] ) && is_array( $g['currencies'] ) ? array_values( array_map( 'strval', $g['currencies'] ) ) : array();
			if ( $allowed && ! in_array( $cur, $allowed, true ) ) $out[] = $base + array( 'code' => 'pay_currency', 'currency' => $cur, 'allowed' => $allowed );
			foreach ( self::clean_schema( isset( $g['field_schema'] ) ? $g['field_schema'] : array() ) as $d ) {
				$v = self::option_value( $d, isset( $r[ $d['key'] ] ) ? $r[ $d['key'] ] : null );
				if ( $v === '' ) {
					if ( $d['required'] && $d['default'] === '' ) $out[] = $base + array( 'code' => 'pay_option_required', 'key' => $d['key'], 'label' => $d['label'] );
					continue;
				}
				if ( $d['type'] !== 'field' ) continue;
				if ( ! isset( $types[ $v ] ) ) $out[] = $base + array( 'code' => 'pay_option_field_missing', 'key' => $d['key'], 'label' => $d['label'] );
				elseif ( $d['field_types'] && ! in_array( $types[ $v ], $d['field_types'], true ) ) $out[] = $base + array( 'code' => 'pay_option_field_type', 'key' => $d['key'], 'label' => $d['label'] );
			}
		}
		return $out;
	}

	/** A method entry from a gateway (id, label, extras) → [id, label] or [id, label, logo URL]. */
	public static function method_entry( $m ) {
		if ( ! is_array( $m ) || ! isset( $m[0], $m[1] ) || ! is_scalar( $m[0] ) || ! is_scalar( $m[1] ) ) return null;
		$e = array( (string) $m[0], (string) $m[1] );
		$x = isset( $m[2] ) && is_array( $m[2] ) ? $m[2] : array();
		if ( isset( $x['logo'] ) && is_string( $x['logo'] ) && self::logo_url( $x['logo'] ) ) $e[] = $x['logo'];
		return $e;
	}

	/** An image address a method may show: http(s), protocol-relative or site-relative; no quotes or spaces. */
	public static function logo_url( $u ) {
		return is_string( $u ) && strlen( $u ) <= 500 && (bool) preg_match( '#^(?:https?:)?//[^\x00-\x20"\'<>()]+$|^/[^/\x00-\x20"\'<>()][^\x00-\x20"\'<>()]*$#i', $u );
	}

	/** When a channel runs: 'paid' (default), 'submit' or 'both'. */
	public static function action_timing( $form0, $key ) {
		$v = self::sanitize_key( $key, self::get( $form0, $key ) );
		return $v === '' ? 'paid' : $v;
	}

	public static function get( $row, $k ) {
		if ( is_array( $row ) ) return array_key_exists( $k, $row ) ? $row[ $k ] : null;
		if ( is_object( $row ) ) return property_exists( $row, $k ) ? $row->$k : null;
		return null;
	}

	/* ------------------------------------------------------------------ colour */

	public static function rgb( $hex ) {
		return array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) );
	}

	public static function luminance( $hex ) {
		$l = array();
		foreach ( self::rgb( $hex ) as $c ) {
			$c = $c / 255;
			$l[] = $c <= 0.04045 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
	}

	/** WCAG contrast ratio, rounded to two decimals. */
	public static function contrast( $a, $b ) {
		return round( self::ratio( $a, $b ), 2 );
	}

	public static function ratio( $a, $b ) {
		$x = self::luminance( $a ) + 0.05;
		$y = self::luminance( $b ) + 0.05;
		return max( $x, $y ) / min( $x, $y );
	}

	/** Black or white, whichever reads better on $bg. */
	public static function auto_text( $bg ) {
		return self::ratio( '#000000', $bg ) >= self::ratio( '#ffffff', $bg ) ? '#000000' : '#ffffff';
	}

	public static function darken( $hex, $pct = 8 ) {
		$out = '#';
		foreach ( self::rgb( $hex ) as $c ) {
			$out .= str_pad( dechex( (int) floor( $c * ( 100 - $pct ) / 100 + 0.5 ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}

	const NAMED = array( 'primary' => '#0d6efd', 'success' => '#198754', 'secondary' => '#6c757d', 'danger' => '#ff455f', 'warning' => '#e9c31a',
		'info' => '#31d2f2', 'light' => '#fbfbfb', 'darkb' => '#202a8d', 'pinkEfb' => '#ff4b93', 'dark' => '#212529', 'white' => '#ffffff' );

	/** The form's button colour (button_color: a hex, or a class such as btn-primary / btn-darkb). */
	public static function form_button_hex( $form0 ) {
		$v = self::get( $form0, 'button_color' );
		if ( is_string( $v ) ) {
			if ( preg_match( '/#[0-9a-fA-F]{6}\b/', $v, $m ) ) return strtolower( $m[0] );
			if ( preg_match( '/(?:^|\s)btn-([A-Za-z]+)/', $v, $m ) && isset( self::NAMED[ $m[1] ] ) ) return self::NAMED[ $m[1] ];
		}
		return '#202a8d';
	}

	/* ------------------------------------------------------------------ resolve */

	/**
	 * Everything the renderers need, from the row and the form defaults.
	 *
	 * @param array $opts methods: [[id,label],…] the gateway offers; logos: [[src,alt],…];
	 *                    mobile: the plan allows the mobile overrides.
	 */
	public static function resolve( $row, $form0, array $opts = array() ) {
		$defaults = isset( $opts['defaults'] ) && is_array( $opts['defaults'] ) ? $opts['defaults'] : array();
		$s = function ( $k ) use ( $row, $defaults ) {
			$v = Field::sanitize_key( $k, Field::get( $row, $k ) );
			if ( $v === '' && array_key_exists( $k, $defaults ) && in_array( $k, Field::DEFAULTABLE, true ) ) $v = Field::sanitize_key( $k, $defaults[ $k ] );
			return $v;
		};
		$bg = $s( 'pf_btn_bg' );
		if ( $bg === '' ) $bg = self::form_button_hex( $form0 );
		$fg = $s( 'pf_btn_fg' );
		if ( $fg === '' || $fg === 'auto' ) $fg = self::auto_text( $bg );
		$bg_h = $s( 'pf_btn_bg_h' );
		if ( $bg_h === '' ) $bg_h = self::darken( $bg, 8 );
		$fg_h = $s( 'pf_btn_fg_h' );
		if ( $fg_h === '' ) $fg_h = $fg;
		$accent = $s( 'pf_accent' );
		if ( $accent === '' ) $accent = $bg;
		$radius = $s( 'pf_btn_radius' );
		$card_radius = $s( 'pf_card_radius' );

		$methods = array();
		$allowed = $s( 'pf_methods' );
		$avail = isset( $opts['methods'] ) && is_array( $opts['methods'] ) ? $opts['methods'] : array();
		if ( $allowed !== '' ) {
			foreach ( explode( ',', $allowed ) as $mid ) {
				foreach ( $avail as $m ) { $me = self::method_entry( $m ); if ( $me && $me[0] === $mid ) $methods[] = $me; }
			}
		} else {
			foreach ( $avail as $m ) { $me = self::method_entry( $m ); if ( $me ) $methods[] = $me; }
		}
		$logos = isset( $opts['logos'] ) && is_array( $opts['logos'] ) ? $opts['logos'] : array();
		$amount_mode = $s( 'pf_amount' );

		$order_of = function ( $order_s, $hide_s ) use ( $methods, $logos, $amount_mode ) {
			$order = $order_s === '' ? array() : explode( ',', $order_s );
			foreach ( Field::ELEMENTS as $e ) { if ( ! in_array( $e, $order, true ) ) $order[] = $e; }
			$hide = $hide_s === '' ? array() : explode( ',', $hide_s );
			$out = array();
			foreach ( $order as $e ) {
				if ( in_array( $e, $hide, true ) ) continue;
				if ( $e === 'coupon' ) continue; // phase 2
				if ( $e === 'amount' && $amount_mode !== 'donation' ) continue;
				if ( $e === 'methods' && count( $methods ) < 2 ) continue;
				if ( $e === 'logos' && empty( $logos ) ) continue;
				$out[] = $e;
			}
			return $out;
		};

		$r = array(
			'btn'           => array(
				'bg'       => $bg,
				'fg'       => $fg,
				'bg_h'     => $bg_h,
				'fg_h'     => $fg_h,
				'radius'   => $radius === '' ? 8 : (int) $radius,
				'size'     => $s( 'pf_btn_size' ) ?: 'md',
				'width'    => $s( 'pf_btn_width' ) ?: 'full',
				'icon'     => $s( 'pf_btn_icon' ) ?: 'bi-lock',
				'icon_pos' => $s( 'pf_btn_icon_pos' ) ?: 'start',
			),
			'order'         => $order_of( $s( 'pf_order' ), $s( 'pf_hide' ) ),
			'layout'        => $s( 'pf_layout' ) ?: 'stack',
			'methods_style' => $s( 'pf_methods_style' ) ?: 'list',
			'methods'       => $methods,
			'logos'         => $logos,
			'summary'       => $s( 'pf_summary' ) ?: 'lines',
			'label_pos'     => $s( 'pf_label_pos' ) ?: 'top',
			'card_bg'       => $s( 'pf_card_bg' ),
			'card_border'   => $s( 'pf_card_border' ),
			'card_radius'   => $card_radius === '' ? 12 : (int) $card_radius,
			'accent'        => $accent,
			'logo_size'     => $s( 'pf_logo_size' ) ?: 'sm',
			'digits'        => $s( 'pf_digits' ) ?: 'latn',
			'amount'        => array(
				'mode'    => $amount_mode,
				'presets' => self::presets( is_string( self::get( $row, 'pf_presets' ) ) ? self::get( $row, 'pf_presets' ) : '' ),
				'other'   => $s( 'pf_other' ) === '1',
				'default' => $s( 'pf_default' ),
				'min'     => $s( 'pf_min' ),
				'max'     => $s( 'pf_max' ),
				'fixed'   => $s( 'pf_fixed' ),
			),
			'mobile'        => null,
		);
		if ( ! empty( $opts['mobile'] ) ) {
			$mo = $s( 'mobile_pf_order' );
			$mh = $s( 'mobile_pf_hide' );
			$m = array(
				'order'         => ( $mo !== '' || $mh !== '' ) ? $order_of( $mo !== '' ? $mo : $s( 'pf_order' ), $mh !== '' ? $mh : $s( 'pf_hide' ) ) : null,
				'size'          => $s( 'mobile_pf_btn_size' ),
				'width'         => $s( 'mobile_pf_btn_width' ),
				'methods_style' => $s( 'mobile_pf_methods_style' ),
			);
			if ( $m['order'] !== null || $m['size'] !== '' || $m['width'] !== '' || $m['methods_style'] !== '' ) $r['mobile'] = $m;
		}
		return $r;
	}

	/**
	 * The texts, by priority: pf_i18n_b64[locale] → pf_i18n_b64[language] → the field's own texts →
	 * $defaults (the translated phrases, already filled with the gateway name).
	 */
	public static function texts( $row, $locale, array $defaults ) {
		$i18n = self::i18n_decode( is_string( self::get( $row, 'pf_i18n_b64' ) ) ? self::get( $row, 'pf_i18n_b64' ) : '' );
		$lang = strpos( (string) $locale, '_' ) !== false ? substr( (string) $locale, 0, strpos( (string) $locale, '_' ) ) : (string) $locale;
		$own = array(
			'btn' => 'pf_btn_text', 'secure' => 'pf_secure_text', 'summary' => 'pf_t_summary', 'methods' => 'pf_t_methods',
			'pending' => 'pf_t_pending', 'success' => 'pf_t_success', 'fail' => 'pf_t_fail', 'amount' => 'pf_t_amount',
		);
		$out = array();
		foreach ( self::TEXT_KEYS as $k ) {
			$v = '';
			if ( isset( $i18n[ $locale ][ $k ] ) ) $v = $i18n[ $locale ][ $k ];
			elseif ( isset( $i18n[ $lang ][ $k ] ) ) $v = $i18n[ $lang ][ $k ];
			elseif ( isset( $own[ $k ] ) ) $v = self::sanitize_key( $own[ $k ], self::get( $row, $own[ $k ] ) );
			if ( $v === '' ) $v = isset( $defaults[ $k ] ) ? self::text( (string) $defaults[ $k ], 200 ) : '';
			$out[ $k ] = $v;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ css */

	const SIZES = array( 'sm' => array( '.4rem .85rem', '.875rem' ), 'md' => array( '.65rem 1.1rem', '1rem' ), 'lg' => array( '.85rem 1.4rem', '1.15rem' ) );

	public static function css( array $r, $id ) {
		$sel = '.efb-pf[data-pf="' . $id . '"]';
		$v = array(
			'--efb-pf-btn-bg:' . $r['btn']['bg'],
			'--efb-pf-btn-fg:' . $r['btn']['fg'],
			'--efb-pf-btn-bg-h:' . $r['btn']['bg_h'],
			'--efb-pf-btn-fg-h:' . $r['btn']['fg_h'],
			'--efb-pf-btn-radius:' . $r['btn']['radius'] . 'px',
			'--efb-pf-btn-pad:' . self::SIZES[ $r['btn']['size'] ][0],
			'--efb-pf-btn-fs:' . self::SIZES[ $r['btn']['size'] ][1],
			'--efb-pf-btn-w:' . ( $r['btn']['width'] === 'full' ? '100%' : 'auto' ),
			'--efb-pf-accent:' . $r['accent'],
			'--efb-pf-card-radius:' . $r['card_radius'] . 'px',
		);
		if ( $r['card_bg'] !== '' ) $v[] = '--efb-pf-card-bg:' . $r['card_bg'];
		if ( $r['card_border'] !== '' ) $v[] = '--efb-pf-card-border:' . $r['card_border'];
		$css = $sel . '{' . implode( ';', $v ) . '}';
		$m = $r['mobile'];
		if ( $m ) {
			$inner = array();
			$mv = array();
			if ( $m['size'] !== '' ) { $mv[] = '--efb-pf-btn-pad:' . self::SIZES[ $m['size'] ][0]; $mv[] = '--efb-pf-btn-fs:' . self::SIZES[ $m['size'] ][1]; }
			if ( $m['width'] !== '' ) $mv[] = '--efb-pf-btn-w:' . ( $m['width'] === 'full' ? '100%' : 'auto' );
			if ( $mv ) $inner[] = $sel . '{' . implode( ';', $mv ) . '}';
			if ( $m['order'] !== null ) {
				foreach ( self::ELEMENTS as $e ) {
					$pos = array_search( $e, $m['order'], true );
					$inner[] = $sel . ' [data-pf-el="' . $e . '"]{' . ( $pos === false ? 'display:none' : 'order:' . ( $pos + 1 ) ) . '}';
				}
			}
			if ( $inner ) $css .= '@media (max-width:767.98px){' . implode( '', $inner ) . '}';
		}
		return $css;
	}

	/* ------------------------------------------------------------------ html */

	public static function esc( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * The markup of the field (without the builder's wrappers).
	 *
	 * @param array $t   texts() output.
	 * @param array $ctx id, gateway, form_id, label, test (bool), preview (bool), placeholder (text of the preview slot)
	 */
	public static function html( array $r, array $t, array $ctx ) {
		$id = self::esc( $ctx['id'] );
		$e = array( __CLASS__, 'esc' );
		$o = '<div class="efb efb-pf" data-pf="' . $id . '" data-gw="' . self::esc( $ctx['gateway'] ) . '" data-formid="' . self::esc( $ctx['form_id'] ) . '" data-layout="' . $r['layout'] . '" data-label-pos="' . $r['label_pos'] . '" data-digits="' . $r['digits'] . '" role="group" aria-labelledby="' . $id . '_pflab">';
		$o .= '<div class="efb efb-pf-label' . ( $r['label_pos'] === 'hidden' ? ' efb-pf-sr' : '' ) . '" id="' . $id . '_pflab">' . self::esc( $ctx['label'] ) . '</div>';
		$o .= '<div class="efb efb-pf-test" data-pf-el="test" role="note"' . ( ! empty( $ctx['test'] ) ? '' : ' hidden' ) . '><i class="efb bi-cone-striped" aria-hidden="true"></i> ' . self::esc( $t['test'] ) . '</div>';
		$o .= '<div class="efb efb-pf-body">';
		foreach ( $r['order'] as $el ) {
			switch ( $el ) {
				case 'summary':
					$o .= '<div class="efb efb-pf-el efb-pf-summary" data-pf-el="summary" data-pf-mode="' . $r['summary'] . '"><div class="efb efb-pf-sum-title">' . self::esc( $t['summary'] ) . '</div>'
						. ( $r['summary'] === 'lines' ? '<ul class="efb efb-pf-lines" data-pf-lines></ul>' : '' )
						. '<div class="efb efb-pf-total"><span>' . self::esc( $t['total'] ) . '</span> <bdi data-pf-total></bdi></div></div>';
					break;
				case 'amount':
					$o .= '<div class="efb efb-pf-el efb-pf-amount" data-pf-el="amount" role="radiogroup" aria-label="' . self::esc( $t['amount'] ) . '"'
						. ( $r['amount']['default'] !== '' ? ' data-pf-default="' . $r['amount']['default'] . '"' : '' )
						. ( $r['amount']['min'] !== '' ? ' data-pf-min="' . $r['amount']['min'] . '"' : '' )
						. ( $r['amount']['max'] !== '' ? ' data-pf-max="' . $r['amount']['max'] . '"' : '' ) . '>';
					foreach ( $r['amount']['presets'] as $p ) {
						$on = $p === $r['amount']['default'];
						$o .= '<button type="button" class="efb efb-pf-chip" role="radio" aria-checked="' . ( $on ? 'true' : 'false' ) . '" data-pf-preset="' . $p . '"><bdi>' . $p . '</bdi></button>';
					}
					if ( $r['amount']['other'] || empty( $r['amount']['presets'] ) ) {
						$o .= '<label class="efb efb-pf-other"><span class="efb efb-pf-other-t">' . self::esc( $t['other'] ) . '</span><input type="text" inputmode="decimal" autocomplete="off" dir="ltr" class="efb efb-pf-other-in" data-pf-other></label>';
					}
					$o .= '</div>';
					break;
				case 'methods':
					$o .= '<div class="efb efb-pf-el efb-pf-methods" data-pf-el="methods" data-style="' . $r['methods_style'] . '">';
					$o .= '<div class="efb efb-pf-sum-title" id="' . $id . '_pfmet">' . self::esc( $t['methods'] ) . '</div>';
					if ( $r['methods_style'] === 'select' ) {
						$o .= '<select class="efb efb-pf-method-select" data-pf-method-select aria-labelledby="' . $id . '_pfmet">';
						foreach ( $r['methods'] as $i => $m ) $o .= '<option value="' . self::esc( $m[0] ) . '"' . ( $i === 0 ? ' selected' : '' ) . '>' . self::esc( $m[1] ) . '</option>';
						$o .= '</select>';
					} else {
						$o .= '<div class="efb efb-pf-method-list" role="radiogroup" aria-labelledby="' . $id . '_pfmet">';
						foreach ( $r['methods'] as $i => $m ) {
							$o .= '<button type="button" class="efb efb-pf-method" role="radio" aria-checked="' . ( $i === 0 ? 'true' : 'false' ) . '" tabindex="' . ( $i === 0 ? '0' : '-1' ) . '" data-pf-method="' . self::esc( $m[0] ) . '">'
								. ( isset( $m[2] ) ? '<img class="efb efb-pf-method-logo" src="' . self::esc( $m[2] ) . '" alt="" aria-hidden="true" loading="lazy" decoding="async">' : '' ) . self::esc( $m[1] ) . '</button>';
						}
						$o .= '</div>';
					}
					$o .= '</div>';
					break;
				case 'fields':
					$o .= '<div class="efb efb-pf-el efb-pf-fields" data-pf-el="fields" data-pf-slot="fields">' . ( ! empty( $ctx['preview'] ) ? '<div class="efb efb-pf-placeholder" data-pf-preview="1">' . self::esc( isset( $ctx['placeholder'] ) ? $ctx['placeholder'] : '' ) . '</div>' : '' ) . '</div>';
					break;
				case 'button':
					$icon = $r['btn']['icon'] === 'none' ? '' : '<i class="efb ' . $r['btn']['icon'] . '" aria-hidden="true"></i>';
					$label = str_replace( '%amount%', '<bdi data-pf-amount></bdi>', self::esc( $t['btn'] ) );
					$o .= '<div class="efb efb-pf-el efb-pf-btn-wrap" data-pf-el="button"><button type="button" class="efb efb-pf-btn" data-pf-pay data-icon-pos="' . $r['btn']['icon_pos'] . '"'
						. ( in_array( 'secure', $r['order'], true ) ? ' aria-describedby="' . $id . '_pfsec"' : '' ) . '>'
						. ( $r['btn']['icon_pos'] === 'start' ? $icon : '' ) . '<span class="efb efb-pf-btn-t">' . $label . '</span>' . ( $r['btn']['icon_pos'] === 'end' ? $icon : '' ) . '</button></div>';
					break;
				case 'secure':
					$o .= '<div class="efb efb-pf-el efb-pf-secure" data-pf-el="secure" id="' . $id . '_pfsec"><i class="efb bi-shield-lock" aria-hidden="true"></i> ' . self::esc( $t['secure'] ) . '</div>';
					break;
				case 'logos':
					$o .= '<div class="efb efb-pf-el efb-pf-logos" data-pf-el="logos" data-size="' . $r['logo_size'] . '">';
					foreach ( $r['logos'] as $l ) $o .= '<img class="efb efb-pf-logo" src="' . self::esc( $l[0] ) . '" alt="' . self::esc( $l[1] ) . '" loading="lazy" decoding="async">';
					$o .= '</div>';
					break;
			}
		}
		$o .= '</div><div class="efb efb-pf-status" role="status" aria-live="polite" data-pf-status tabindex="-1"></div></div>';
		return $o;
	}

	/* ------------------------------------------------------------------ submit */

	/** The donation row of the field as submitted: [id_, name, type, value] or null (no amount). */
	public static function submitted_row( array $item, array $f ) {
		$v = isset( $item['value'] ) && is_scalar( $item['value'] ) ? self::decimal( (string) $item['value'] ) : '';
		if ( $v === '' ) return null;
		return array( 'id_' => (string) $f['id_'], 'name' => isset( $f['name'] ) ? (string) $f['name'] : '', 'type' => (string) $f['type'], 'value' => $v, 'amount' => isset( $f['amount'] ) ? $f['amount'] : '' );
	}
}
