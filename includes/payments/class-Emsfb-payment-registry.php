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

	/** Proposed add-on keys and folders (docs/payments PAY01-PAY07; owner to confirm). */
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

	/** The add-on folder for a key, e.g. AdnMOL → mollie (filter efb_payment_gateway_addons). */
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
	 * What the public renderer and the builder need about each gateway, without any secret:
	 * label, mode, configured per mode, currencies, methods, supports, logos.
	 */
	public static function public_info( $slug ) {
		$g = self::get( $slug );
		if ( ! $g ) return null;
		$s = Settings::load( $slug );
		$methods = array();
		try { $methods = (array) $g->methods( $s ); } catch ( \Throwable $e ) { $methods = array(); }
		$sup = array_merge( ( new Gateway_Defaults() )->supports(), (array) $g->supports() );
		return array(
			'slug'       => $slug,
			'addon'      => (string) $g->addon_key(),
			'label'      => (string) $g->label(),
			'mode'       => $s->mode(),
			'configured' => array( 'test' => (bool) $g->is_configured( $s, 'test' ), 'live' => (bool) $g->is_configured( $s, 'live' ) ),
			'currencies' => array_values( (array) $sup['currencies'] ),
			'countries'  => array_values( (array) $sup['countries'] ),
			'methods'    => array_values( array_map( function ( $m ) { return array( (string) $m[0], (string) $m[1] ); }, array_filter( $methods, 'is_array' ) ) ),
			'logos'      => array_values( array_map( function ( $l ) { return array( (string) $l[0], (string) $l[1] ); }, array_filter( (array) $g->logos(), 'is_array' ) ) ),
			'supports'   => $sup,
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
