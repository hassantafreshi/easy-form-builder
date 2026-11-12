<?php
/**
 * PAY00 §5: the gateway registry.
 *
 * Gateways register on the action efb_register_payment_gateways (fired once, the first time the
 * registry is read after plugins_loaded). register() refuses a slug that is malformed, already
 * taken or one of EFB's own field types, and a gateway written for another framework API version
 * (the admin then sees "update the add-on"). The filter efb_payment_gateways gives the final list.
 *
 * Gateway add-ons (vendor/<dir>/routes-efb.php) are loaded by load_addons() when their add-on
 * flag is on, like the Stripe / PayPal route files.
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registry {

	/** Framework API version of this core (EMSFB_PAYMENTS_API). */
	const API = 1;

	/** The add-on catalogue: key → folder under vendor/ (docs/payments PAY01-PAY07; owner to confirm). Core ships no gateway. */
	const ADDONS = array(
		'AdnMOL' => 'mollie',
		'AdnPPG' => 'payplug',
		'AdnTAP' => 'tappay',
		'AdnOMS' => 'omise',
		'AdnPTB' => 'paytabs',
		'AdnPMB' => 'paymob',
		'AdnXND' => 'xendit',
	);

	/** Types a gateway slug may not take (EFB's own field and row types). */
	const RESERVED = array( 'stripe', 'paypal', 'persiapay', 'zarinpal', 'payment', 'option', 'step', 'form', 'text', 'email', 'number', 'select',
		'radio', 'checkbox', 'file', 'html', 'prcfld', 'ttlprc', 'paycheckbox', 'payradio', 'payselect', 'paymultiselect', 'date', 'url', 'tel',
		'mobile', 'textarea', 'password', 'range', 'color', 'maps', 'esign', 'rating', 'switch', 'heading', 'link', 'yesno', 'steps', 'coupon', 'w_link' );

	private static $gateways = array();
	private static $errors = array();
	private static $booted = false;
	private static $instance = null;

	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	/** Fires efb_register_payment_gateways once. */
	public static function boot() {
		if ( self::$booted ) return;
		self::$booted = true;
		if ( function_exists( 'do_action' ) ) do_action( 'efb_register_payment_gateways', self::instance() );
	}

	/** Test helper: forget everything. */
	public static function reset() {
		self::$gateways = array();
		self::$errors = array();
		self::$booted = false;
	}

	/**
	 * @return bool true when registered.
	 */
	public function register( $g ) {
		if ( ! $g instanceof Gateway ) {
			self::$errors[] = array( 'slug' => '', 'code' => 'not_a_gateway' );
			return false;
		}
		$slug = (string) $g->slug();
		$api = (int) constant( get_class( $g ) . '::API' );
		if ( ! preg_match( '/^[a-z][a-z0-9]{1,19}$/', $slug ) || in_array( $slug, self::RESERVED, true ) ) {
			self::$errors[] = array( 'slug' => $slug, 'code' => 'bad_slug', 'class' => get_class( $g ) );
			return false;
		}
		if ( $api < 1 || $api > self::API ) {
			self::$errors[] = array( 'slug' => $slug, 'code' => 'api_version', 'api' => $api, 'core_api' => self::API, 'addon' => (string) $g->addon_key() );
			return false;
		}
		if ( isset( self::$gateways[ $slug ] ) && self::$gateways[ $slug ] !== $g ) {
			self::$errors[] = array( 'slug' => $slug, 'code' => 'duplicate', 'class' => get_class( $g ) );
			return false;
		}
		self::$gateways[ $slug ] = $g;
		return true;
	}

	/** Problems seen by register() (shown as an admin notice). */
	public static function errors() {
		self::boot();
		return self::$errors;
	}

	/** @return Gateway[] slug => gateway */
	public static function all() {
		self::boot();
		$all = self::$gateways;
		if ( function_exists( 'apply_filters' ) ) {
			$f = apply_filters( 'efb_payment_gateways', $all );
			if ( is_array( $f ) ) {
				$all = array();
				foreach ( $f as $slug => $g ) {
					if ( $g instanceof Gateway && $g->slug() === $slug ) $all[ $slug ] = $g;
				}
			}
		}
		return $all;
	}

	/** @return Gateway|null */
	public static function get( $slug ) {
		if ( ! is_string( $slug ) || $slug === '' ) return null;
		$all = self::all();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	public static function has( $slug ) {
		return self::get( $slug ) !== null;
	}

	/** Whether a field type is a framework gateway field. */
	public static function is_field_type( $type ) {
		return is_string( $type ) && preg_match( '/^[a-z][a-z0-9]{1,19}$/', $type ) && self::has( $type );
	}

	public static function slugs() {
		return array_keys( self::all() );
	}

	/** The add-on folder of each catalogue key (filter efb_payment_gateway_addons). */
	public static function addons() {
		$map = self::ADDONS;
		if ( function_exists( 'apply_filters' ) ) {
			$f = apply_filters( 'efb_payment_gateway_addons', $map );
			if ( is_array( $f ) ) {
				$map = array();
				foreach ( $f as $k => $d ) {
					if ( preg_match( '/^Adn[A-Za-z]{2,5}$/', (string) $k ) && preg_match( '/^[a-z0-9_-]{2,30}$/', (string) $d ) ) $map[ $k ] = $d;
				}
			}
		}
		return $map;
	}

	/**
	 * Loads vendor/<dir>/routes-efb.php of every gateway add-on whose flag is on and that the
	 * compatibility check accepts. The file registers its gateway on efb_register_payment_gateways.
	 */
	public static function load_addons( $settings, $plugin_dir ) {
		if ( ! is_object( $settings ) ) return;
		foreach ( self::addons() as $key => $dir ) {
			if ( empty( $settings->$key ) ) continue;
			if ( function_exists( 'emsfb_is_addon_compatible_efb' ) && ! emsfb_is_addon_compatible_efb( $key ) ) continue;
			$f = rtrim( $plugin_dir, '/' ) . '/vendor/' . $dir . '/routes-efb.php';
			if ( is_readable( $f ) ) require_once $f;
		}
	}

	/**
	 * A gateway's method list in one shape: array( id, label ) or array( id, label, extras ) where
	 * extras holds only logo (safe URL), currencies (ISO codes), min_minor / max_minor (CUR => int).
	 * With $currency, a method whose currencies do not include it is dropped.
	 */
	public static function normalize_methods( $list, $currency = '' ) {
		$out = array();
		$currency = strtoupper( (string) $currency );
		foreach ( (array) $list as $m ) {
			if ( ! is_array( $m ) || ! isset( $m[0], $m[1] ) || ! is_scalar( $m[0] ) || ! is_scalar( $m[1] ) ) continue;
			$id = (string) $m[0];
			if ( ! preg_match( '/^[a-z0-9_]{1,30}$/', $id ) ) continue;
			$e = array( $id, (string) $m[1] );
			$x = isset( $m[2] ) && is_array( $m[2] ) ? $m[2] : array();
			$extra = array();
			if ( isset( $x['logo'] ) && Field::logo_url( $x['logo'] ) ) $extra['logo'] = (string) $x['logo'];
			if ( isset( $x['currencies'] ) && is_array( $x['currencies'] ) ) {
				$extra['currencies'] = array_values( array_filter( array_map( function ( $c ) { return is_string( $c ) && preg_match( '/^[A-Za-z]{3}$/', $c ) ? strtoupper( $c ) : ''; }, $x['currencies'] ) ) );
			}
			foreach ( array( 'min_minor', 'max_minor' ) as $lk ) {
				if ( ! isset( $x[ $lk ] ) || ! is_array( $x[ $lk ] ) ) continue;
				foreach ( $x[ $lk ] as $c => $n ) {
					if ( is_string( $c ) && preg_match( '/^[A-Za-z]{3}$/', $c ) && is_numeric( $n ) && (int) $n >= 0 ) $extra[ $lk ][ strtoupper( $c ) ] = (int) $n;
				}
			}
			if ( $currency !== '' && ! empty( $extra['currencies'] ) && ! in_array( $currency, $extra['currencies'], true ) ) continue;
			if ( $extra ) $e[] = $extra;
			$out[] = $e;
			if ( count( $out ) >= 40 ) break;
		}
		return $out;
	}

	/** Gateway::field_defaults() checked: texts (plain text, known keys) and style keys (Field::DEFAULTABLE, valid values). */
	public static function field_defaults( Gateway $g ) {
		$d = array();
		try { $d = $g->field_defaults(); } catch ( \Throwable $e ) { $d = array(); }
		$out = array( 'texts' => array(), 'pf' => array() );
		if ( ! is_array( $d ) ) return $out;
		foreach ( isset( $d['texts'] ) && is_array( $d['texts'] ) ? $d['texts'] : array() as $k => $v ) {
			if ( in_array( $k, Field::TEXT_KEYS, true ) && $k !== 'test' && is_string( $v ) ) {
				$t = Field::text( $v, $k === 'btn' ? 60 : 200 );
				if ( $t !== '' ) $out['texts'][ $k ] = $t;
			}
		}
		foreach ( isset( $d['pf'] ) && is_array( $d['pf'] ) ? $d['pf'] : array() as $k => $v ) {
			if ( ! in_array( $k, Field::DEFAULTABLE, true ) ) continue;
			$c = Field::sanitize_key( $k, $v );
			if ( $c !== '' ) $out['pf'][ $k ] = $c;
		}
		return $out;
	}

	/** A gateway's field_schema(), cleaned (Field::clean_schema()). */
	public static function field_schema( $slug ) {
		$g = self::get( $slug );
		if ( ! $g ) return null;
		try { return Field::clean_schema( $g->field_schema() ); } catch ( \Throwable $e ) { return array(); }
	}

	/**
	 * Warnings for a form about to be saved (builder Save, AutoPilot): core's checks from what each
	 * gateway declares (currency, required options, mapped fields) plus Gateway::form_warnings().
	 * Never refuses the save. @return array list of array( code, m, field_id, gateway )
	 */
	public static function form_warnings( $form ) {
		$out = array();
		if ( ! is_array( $form ) || ! self::all() ) return $out;
		$infos = array();
		foreach ( self::all() as $slug => $g ) {
			$sup = array_merge( ( new Gateway_Defaults() )->supports(), (array) $g->supports() );
			try { $schema = $g->field_schema(); } catch ( \Throwable $e ) { $schema = array(); }
			$infos[ $slug ] = array( 'currencies' => array_values( (array) $sup['currencies'] ), 'field_schema' => $schema, 'label' => (string) $g->label() );
		}
		foreach ( Field::warnings( $form, $infos ) as $w ) {
			$label = $infos[ $w['gateway'] ]['label'];
			switch ( $w['code'] ) {
				case 'pay_currency':
					/* translators: %1$s: gateway name, %2$s: currency codes, %3$s: the form's currency */
					$m = sprintf( __( '%1$s only accepts %2$s. This form uses %3$s, so visitors cannot pay. Change the currency in the payment field.', 'easy-form-builder' ), $label, implode( ', ', $w['allowed'] ), $w['currency'] !== '' ? $w['currency'] : '-' );
					break;
				case 'pay_option_required':
					/* translators: %1$s: gateway name, %2$s: name of an option of the payment field */
					$m = sprintf( __( '%1$s needs "%2$s". Choose it in the payment field\'s settings.', 'easy-form-builder' ), $label, $w['label'] );
					break;
				case 'pay_option_field_missing':
					/* translators: %1$s: gateway name, %2$s: name of an option of the payment field */
					$m = sprintf( __( '%1$s: the field chosen for "%2$s" is no longer in the form. Choose another one.', 'easy-form-builder' ), $label, $w['label'] );
					break;
				default:
					/* translators: %1$s: gateway name, %2$s: name of an option of the payment field */
					$m = sprintf( __( '%1$s: the field chosen for "%2$s" is not the right kind of field.', 'easy-form-builder' ), $label, $w['label'] );
			}
			$out[] = array( 'code' => $w['code'], 'm' => $m, 'field_id' => $w['field_id'], 'gateway' => $w['gateway'] );
		}
		foreach ( $form as $i => $r ) {
			if ( $i === 0 || ! is_array( $r ) || ! isset( $r['type'], $r['id_'] ) || ! is_string( $r['type'] ) || ! isset( $infos[ $r['type'] ] ) ) continue;
			$g = self::get( $r['type'] );
			try { $list = $g->form_warnings( $form, $r, Settings::load( $r['type'] ) ); } catch ( \Throwable $e ) { $list = array(); }
			foreach ( (array) $list as $w ) {
				if ( ! is_array( $w ) || ! isset( $w['code'], $w['m'] ) || ! is_string( $w['code'] ) || ! preg_match( '/^[a-z0-9_]{1,40}$/', $w['code'] ) || ! is_string( $w['m'] ) ) continue;
				$m = Field::text( $w['m'], 300 );
				if ( $m !== '' ) $out[] = array( 'code' => 'pay_gw_' . $w['code'], 'm' => $m, 'field_id' => (string) $r['id_'], 'gateway' => $r['type'] );
				if ( count( $out ) >= 30 ) break 2;
			}
		}
		return $out;
	}

	/**
	 * What the public renderer and the builder need about each gateway, without any secret:
	 * label, mode, configured per mode, currencies, methods, supports, logos, the field options
	 * (field_schema) and the field defaults. $ctx (currency, amount_minor) asks for the methods of
	 * one form (Gateway::methods_for()).
	 */
	public static function public_info( $slug, array $ctx = array() ) {
		$g = self::get( $slug );
		if ( ! $g ) return null;
		$s = Settings::load( $slug );
		$cur = isset( $ctx['currency'] ) && is_string( $ctx['currency'] ) && preg_match( '/^[A-Za-z]{3}$/', $ctx['currency'] ) ? strtoupper( $ctx['currency'] ) : '';
		$methods = array();
		try {
			$methods = $cur !== '' ? (array) $g->methods_for( $s, array( 'currency' => $cur, 'amount_minor' => isset( $ctx['amount_minor'] ) ? (int) $ctx['amount_minor'] : null ) ) : (array) $g->methods( $s );
		} catch ( \Throwable $e ) {
			$methods = array();
		}
		$sup = array_merge( ( new Gateway_Defaults() )->supports(), (array) $g->supports() );
		try { $schema = Field::clean_schema( $g->field_schema() ); } catch ( \Throwable $e ) { $schema = array(); }
		return array(
			'slug'         => $slug,
			'addon'        => (string) $g->addon_key(),
			'label'        => (string) $g->label(),
			'mode'         => $s->mode(),
			'configured'   => array( 'test' => (bool) $g->is_configured( $s, 'test' ), 'live' => (bool) $g->is_configured( $s, 'live' ) ),
			'currencies'   => array_values( (array) $sup['currencies'] ),
			'countries'    => array_values( (array) $sup['countries'] ),
			'methods'      => self::normalize_methods( $methods, $cur ),
			'logos'        => array_values( array_map( function ( $l ) { return array( (string) $l[0], (string) $l[1] ); }, array_filter( (array) $g->logos(), 'is_array' ) ) ),
			'supports'     => $sup,
			'field_schema' => $schema,
			'defaults'     => self::field_defaults( $g ),
		);
	}
}

/** Defaults of Gateway::supports() for a gateway that returns only some keys. */
class Gateway_Defaults extends Gateway {
	public function slug() { return 'x'; }
	public function addon_key() { return ''; }
	public function label() { return ''; }
	public function settings_schema() { return array(); }
	public function test_connection( Settings $s, $mode ) { return Result::unsupported(); }
	public function create( Payment $p, Settings $s, Checkout_Context $ctx ) { return new Create_Result(); }
	public function fetch( Payment $p, Settings $s ) { return Remote_State::error( 'unsupported' ); }
	public function parse_webhook( \WP_REST_Request $r, Settings $s ) { return null; }
}
