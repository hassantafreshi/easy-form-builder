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
			$s = Settings::load( $slug );
			try { $schema = $g->field_schema(); } catch ( \Throwable $e ) { $schema = array(); }
			try { $methods = self::normalize_methods( $g->methods( $s ) ); } catch ( \Throwable $e ) { $methods = array(); }
			try { $ready = (bool) $g->is_configured( $s, $s->mode() ); } catch ( \Throwable $e ) { $ready = false; }
			$infos[ $slug ] = array( 'currencies' => array_values( (array) $sup['currencies'] ), 'field_schema' => $schema, 'label' => (string) $g->label(), 'methods' => $methods,
				'ready' => $ready, 'mode' => $s->mode(), 'min_minor' => (array) $sup['min_minor'], 'max_minor' => (array) $sup['max_minor'] );
		}
		foreach ( Field::warnings( $form, $infos, array( 'webhook_local' => Service::site_is_local() ) ) as $w ) {
			$out[] = array( 'code' => $w['code'], 'm' => self::warning_text( $w, $infos[ $w['gateway'] ], $form ), 'level' => $w['level'], 'field_id' => $w['field_id'], 'gateway' => $w['gateway'] );
		}
		foreach ( $form as $i => $r ) {
			if ( $i === 0 || ! is_array( $r ) || ! isset( $r['type'], $r['id_'] ) || ! is_string( $r['type'] ) || ! isset( $infos[ $r['type'] ] ) ) continue;
			$g = self::get( $r['type'] );
			try { $list = $g->form_warnings( $form, $r, Settings::load( $r['type'] ) ); } catch ( \Throwable $e ) { $list = array(); }
			foreach ( (array) $list as $w ) {
				if ( ! is_array( $w ) || ! isset( $w['code'], $w['m'] ) || ! is_string( $w['code'] ) || ! preg_match( '/^[a-z0-9_]{1,40}$/', $w['code'] ) || ! is_string( $w['m'] ) ) continue;
				$m = Field::text( $w['m'], 300 );
				$lv = isset( $w['level'] ) && in_array( $w['level'], array( 'error', 'warning', 'info' ), true ) ? $w['level'] : 'warning';
				if ( $m !== '' ) $out[] = array( 'code' => 'pay_gw_' . $w['code'], 'm' => $m, 'level' => $lv, 'field_id' => (string) $r['id_'], 'gateway' => $r['type'] );
				if ( count( $out ) >= 30 ) break 2;
			}
		}
		return $out;
	}

	/** The owner's words for one of Field::warnings() (twin: payment-field-efb.js warningsHtml()). */
	public static function warning_text( array $w, array $info, $form = array() ) {
		$label = $info['label'];
		switch ( $w['code'] ) {
			case 'pay_currency':
				/* translators: %1$s: gateway name, %2$s: currency codes, %3$s: the form's currency */
				return sprintf( __( '%1$s only accepts %2$s. This form uses %3$s, so visitors cannot pay. Change the currency in the payment field.', 'easy-form-builder' ), $label, implode( ', ', $w['allowed'] ), $w['currency'] !== '' ? $w['currency'] : '-' );
			case 'pay_option_required':
				return self::required_text( $label, $w, $info['methods'] );
			case 'pay_option_field_missing':
				/* translators: %1$s: gateway name, %2$s: name of an option of the payment field */
				return sprintf( __( '%1$s: the field chosen for "%2$s" is no longer in the form. Choose another one.', 'easy-form-builder' ), $label, $w['label'] );
			case 'pay_option_field_type':
				/* translators: %1$s: gateway name, %2$s: name of an option of the payment field */
				return sprintf( __( '%1$s: the field chosen for "%2$s" is not the right kind of field.', 'easy-form-builder' ), $label, $w['label'] );
			case 'pay_option_not_required':
				$name = $w['label'];
				foreach ( is_array( $form ) ? $form : array() as $i => $f ) {
					if ( $i > 0 && is_array( $f ) && isset( $f['id_'], $f['name'] ) && (string) $f['id_'] === $w['target'] && is_string( $f['name'] ) && $f['name'] !== '' ) $name = Field::text( str_replace( '@efb@dq#', '"', $f['name'] ), 80 );
				}
				/* translators: %1$s: name of a form field (e.g. Mobile), %2$s: gateway name */
				return sprintf( __( '"%1$s" is needed by %2$s for the payment but is not marked Required. Visitors are asked for it when they press Pay; mark it Required so they see it earlier.', 'easy-form-builder' ), $name, $label );
			case 'pay_not_configured':
				return $w['mode'] === 'live'
					/* translators: %s: gateway name */
					? sprintf( __( '%s has no keys for Live mode yet, so visitors cannot pay. Add them in Payments → Gateways.', 'easy-form-builder' ), $label )
					/* translators: %s: gateway name */
					: sprintf( __( '%s has no keys for Test mode yet, so the form cannot be tried. Add them in Payments → Gateways.', 'easy-form-builder' ), $label );
			case 'pay_test_mode':
				/* translators: %s: gateway name */
				return sprintf( __( '%s is in Test mode: no real payments are taken. Switch it to Live in Payments → Gateways when you are ready.', 'easy-form-builder' ), $label );
			case 'pay_webhook_local':
				/* translators: %s: gateway name */
				return sprintf( __( 'This site cannot be reached from the internet, so %s cannot send payment confirmations to it. Payments are confirmed when the visitor comes back and by the regular check.', 'easy-form-builder' ), $label );
			case 'pay_amount_limit':
				$lim = Money::format( $w['limit_minor'], $w['currency'] );
				$amt = Money::format( $w['amount_minor'], $w['currency'] );
				if ( $w['what'] === 'fixed' ) {
					return $w['limit'] === 'max'
						/* translators: %1$s: gateway name, %2$s: the gateway's maximum, %3$s: the form's fixed amount */
						? sprintf( __( '%1$s accepts payments up to %2$s. The fixed amount of this form is %3$s, so visitors cannot pay.', 'easy-form-builder' ), $label, $lim, $amt )
						/* translators: %1$s: gateway name, %2$s: the gateway's minimum, %3$s: the form's fixed amount */
						: sprintf( __( '%1$s accepts payments from %2$s. The fixed amount of this form is %3$s; visitors can only pay when other choices raise the total.', 'easy-form-builder' ), $label, $lim, $amt );
				}
				return $w['limit'] === 'max'
					/* translators: %1$s: gateway name, %2$s: the gateway's maximum, %3$s: the donation maximum of the form */
					? sprintf( __( '%1$s accepts payments up to %2$s. The largest donation of this form (%3$s) is above it.', 'easy-form-builder' ), $label, $lim, $amt )
					/* translators: %1$s: gateway name, %2$s: the gateway's minimum, %3$s: the donation minimum of the form */
					: sprintf( __( '%1$s accepts payments from %2$s. The smallest donation of this form (%3$s) is below it.', 'easy-form-builder' ), $label, $lim, $amt );
			case 'pay_method_currency':
				$names = array();
				foreach ( $w['methods'] as $id ) {
					$n = $id;
					foreach ( $info['methods'] as $m ) { if ( (string) $m[0] === $id ) $n = (string) $m[1]; }
					$names[] = $n;
				}
				/* translators: %1$s: gateway name, %2$s: payment methods, %3$s: the form's currency */
				return sprintf( __( '%1$s: %2$s cannot take %3$s, so it is not offered on this form. Choose other methods or change the currency.', 'easy-form-builder' ), $label, implode( ', ', $names ), $w['currency'] );
			case 'pay_multiple_fields':
				return __( 'This form has more than one payment field. A form can take one payment: remove the others.', 'easy-form-builder' );
			case 'pay_form_type':
				/* translators: %s: the form's type (e.g. survey) */
				return sprintf( __( 'A payment field only works in a payment form. This form is a "%s" form: create a payment form or remove the payment field.', 'easy-form-builder' ), $w['form_type'] !== '' ? $w['form_type'] : '-' );
		}
		return '';
	}

	/**
	 * The owner's message for a required payer option that is not chosen: the gateway's own
	 * (field_schema owner_message) or core's actionable one, naming the kind of field to add, the
	 * option to choose it in, and the methods that need it.
	 */
	public static function required_text( $gateway_label, array $w, array $methods = array() ) {
		if ( isset( $w['owner_message'] ) && $w['owner_message'] !== '' ) return $w['owner_message'];
		$kind = isset( $w['field_types'] ) && $w['field_types'] === array( 'email' ) ? __( 'an Email field', 'easy-form-builder' )
			: ( isset( $w['field_types'] ) && array_intersect( (array) $w['field_types'], array( 'mobile', 'tel' ) ) && ! array_diff( (array) $w['field_types'], array( 'mobile', 'tel', 'text', 'number' ) ) ? __( 'a phone number field', 'easy-form-builder' ) : __( 'a field for it', 'easy-form-builder' ) );
		$names = array();
		foreach ( isset( $w['methods'] ) ? (array) $w['methods'] : array() as $id ) {
			$n = $id;
			foreach ( $methods as $m ) { if ( (string) $m[0] === $id ) $n = (string) $m[1]; }
			$names[] = $n;
		}
		if ( $names ) {
			/* translators: %1$s: gateway name, %2$s: what is needed (option name, e.g. Payer e-mail), %3$s: payment methods, %4$s: kind of field (e.g. an Email field) */
			return sprintf( __( '%1$s requires "%2$s" to pay with %3$s. Add %4$s to the form and select it in the payment field\'s "%2$s" option.', 'easy-form-builder' ), $gateway_label, $w['label'], implode( ', ', $names ), $kind );
		}
		/* translators: %1$s: gateway name, %2$s: what is needed (option name, e.g. Payer e-mail), %3$s: kind of field (e.g. an Email field) */
		return sprintf( __( '%1$s requires "%2$s". Add %3$s to the form and select it in the payment field\'s "%2$s" option.', 'easy-form-builder' ), $gateway_label, $w['label'], $kind );
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
		try { $ready = (bool) $g->is_configured( $s, $s->mode() ); } catch ( \Throwable $e ) { $ready = false; }
		return array(
			'slug'         => $slug,
			'ready'        => $ready,
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

/**
 * PAY00: payment readiness of a form (owner request "a standard solution for a form that can never
 * take payment"; docs/payments/PAY00-payment-framework.fa.md, appendix "Payment readiness").
 *
 * The common pattern of form builders and shops (Gravity Forms, WPForms, Formidable, Jotform,
 * Shopify): saving is never blocked; the form has a computed state "ready / not ready + the blocking
 * issues", recomputed from the current form and the gateway settings on every read (nothing is
 * stored, so adding the keys later makes the form ready without saving it again, and removing them
 * or deactivating the add-on makes it not ready); visitors of a form that is not ready never reach
 * the payment and nothing is stored; the people who can fix it are told what and where.
 *
 * Issues are the save warnings of level "error" (Registry::form_warnings(): no keys for the mode,
 * currency, fixed amount above the maximum, no method left, a required payer field not chosen, a
 * mapped field gone, several payment fields, a form type that cannot pay, a gateway's own error) plus
 * what only readiness sees: a payment field whose gateway add-on is not active (pay_addon_inactive;
 * the catalogue folder is the field type) and a plan without payments (pay_plan).
 */
class Readiness {

	/** What a visitor of a form that is not ready reads (no detail, no jargon). */
	public static function visitor_text() {
		return __( 'Online payment for this form is not available right now. Please try again later or contact the site.', 'easy-form-builder' );
	}

	/** Whether the current user may see the owner's notice on the public page (builder or payment settings). */
	public static function viewer_can_fix() {
		if ( ! function_exists( 'current_user_can' ) ) return false;
		return current_user_can( 'manage_options' ) || current_user_can( 'Emsfb_create' ) || current_user_can( 'Emsfb_payments' );
	}

	/** Field types of the gateway add-ons of the catalogue (the folder is the field type), registered or not. */
	public static function catalogue_types() {
		return array_values( array_unique( array_map( 'strval', Registry::addons() ) ) );
	}

	/** Whether a field type is a payment field of the framework: a registered gateway, or one of the catalogue whose add-on is off. */
	public static function is_payment_type( $type ) {
		if ( ! is_string( $type ) || ! preg_match( '/^[a-z][a-z0-9]{1,19}$/', $type ) ) return false;
		return Registry::has( $type ) || in_array( $type, self::catalogue_types(), true );
	}

	/** The add-on key of a catalogue field type ('' when unknown). */
	public static function addon_of( $type ) {
		$k = array_search( (string) $type, Registry::addons(), true );
		return is_string( $k ) ? $k : '';
	}

	/** Whether a form (rows) has a payment field of the framework. */
	public static function has_payment( $form ) {
		if ( ! is_array( $form ) ) return false;
		foreach ( $form as $i => $r ) {
			$t = Field::get( $r, 'type' );
			if ( $i !== 0 && self::is_payment_type( $t ) ) return true;
		}
		return false;
	}

	/** The add-on's name for a message (the compatibility catalogue), else the field type. */
	private static function addon_name( $type ) {
		$key = self::addon_of( $type );
		if ( $key !== '' && class_exists( '\Emsfb_Addon_Compatibility' ) && method_exists( '\Emsfb_Addon_Compatibility', 'addon_requirements' ) ) {
			$req = \Emsfb_Addon_Compatibility::addon_requirements();
			if ( isset( $req[ $key ]['name'] ) && is_string( $req[ $key ]['name'] ) ) return $req[ $key ]['name'];
		}
		return ucfirst( (string) $type );
	}

	/**
	 * The readiness of a form: array( has_payment, ready, issues ). Each issue: code, level ('error'),
	 * m (the owner's words), field_id, gateway, fix = array( kind: settings | field | addons | plan,
	 * url, label ). $form_id (when known) makes the field links open the form in the builder.
	 * Filter efb_payment_readiness( $out, $form, $form_id ).
	 */
	public static function of_form( $form, $form_id = 0 ) {
		$out = array( 'has_payment' => false, 'ready' => true, 'issues' => array() );
		if ( ! is_array( $form ) || ! isset( $form[0] ) ) return $out;
		$form = json_decode( (string) json_encode( $form ), true );
		if ( ! is_array( $form ) ) return $out;
		$issues = array();
		$registered = false;
		foreach ( $form as $i => $r ) {
			if ( $i === 0 || ! is_array( $r ) || ! isset( $r['type'] ) || ! is_string( $r['type'] ) ) continue;
			if ( Registry::has( $r['type'] ) ) {
				$out['has_payment'] = true;
				$registered = true;
			} elseif ( in_array( $r['type'], self::catalogue_types(), true ) ) {
				$out['has_payment'] = true;
				$issues[] = array( 'code' => 'pay_addon_inactive', 'level' => 'error', 'field_id' => isset( $r['id_'] ) && is_scalar( $r['id_'] ) ? (string) $r['id_'] : '', 'gateway' => $r['type'], 'addon' => self::addon_of( $r['type'] ),
					/* translators: %s: name of a payment add-on (the gateway's name) */
					'm' => sprintf( __( 'The %s add-on of this payment field is not active, so visitors cannot pay. Activate it in Easy Form Builder → Add-ons, or remove the payment field.', 'easy-form-builder' ), self::addon_name( $r['type'] ) ) );
			}
		}
		if ( ! $out['has_payment'] ) return $out;
		if ( $registered && ! Service::plan_ok() ) {
			$issues[] = array( 'code' => 'pay_plan', 'level' => 'error', 'field_id' => '', 'gateway' => '',
				'm' => __( 'Online payments are not part of the plan of this site, so visitors cannot pay. Choose a plan that includes payments.', 'easy-form-builder' ) );
		}
		if ( $registered ) {
			foreach ( Registry::form_warnings( $form ) as $w ) {
				if ( isset( $w['level'] ) && $w['level'] === 'error' ) $issues[] = array( 'code' => $w['code'], 'level' => 'error', 'field_id' => (string) $w['field_id'], 'gateway' => (string) $w['gateway'], 'm' => (string) $w['m'] );
			}
		}
		foreach ( $issues as $k => $is ) $issues[ $k ]['fix'] = self::fix_of( $is, (int) $form_id );
		$out['issues'] = $issues;
		$out['ready'] = ! $issues;
		if ( function_exists( 'apply_filters' ) ) {
			$f = apply_filters( 'efb_payment_readiness', $out, $form, (int) $form_id );
			if ( is_array( $f ) && isset( $f['ready'], $f['issues'] ) && is_array( $f['issues'] ) ) $out = array( 'has_payment' => ! empty( $f['has_payment'] ), 'ready' => (bool) $f['ready'] && ! $f['issues'], 'issues' => array_values( $f['issues'] ) );
		}
		return $out;
	}

	/** Where the owner fixes an issue: the gateway settings, the add-ons screen, or the payment field in the builder. */
	public static function fix_of( array $issue, $form_id = 0 ) {
		$adm = function ( $p ) { return function_exists( 'admin_url' ) ? admin_url( $p ) : $p; };
		switch ( $issue['code'] ) {
			case 'pay_not_configured':
				return array( 'kind' => 'settings', 'url' => $adm( 'admin.php?page=Emsfb_payments&tab=gateways' ), 'label' => __( 'Open the gateway settings', 'easy-form-builder' ) );
			case 'pay_addon_inactive':
				return array( 'kind' => 'addons', 'url' => $adm( 'admin.php?page=Emsfb_addon' ), 'label' => __( 'Open Add-ons', 'easy-form-builder' ) );
			case 'pay_plan':
				return array( 'kind' => 'plan', 'url' => $adm( 'admin.php?page=Emsfb_addon' ), 'label' => __( 'See the plans', 'easy-form-builder' ) );
		}
		return array( 'kind' => 'field', 'url' => $form_id > 0 ? $adm( 'admin.php?page=Emsfb&state=edit-form&id=' . (int) $form_id ) : '', 'field_id' => isset( $issue['field_id'] ) ? (string) $issue['field_id'] : '', 'label' => __( 'Fix it in the payment field', 'easy-form-builder' ) );
	}

	/**
	 * The save answer of a form with a payment field gets payment_ready (bool) and payment_issues
	 * (the blocking issues with code, level, m, field_id, gateway, fix); the issues the save warnings
	 * do not already carry (pay_addon_inactive, pay_plan) are added to warnings too. Never refuses.
	 */
	public static function save_answer( array $response, $form, $form_id ) {
		$r = self::of_form( $form, (int) $form_id );
		if ( ! $r['has_payment'] ) return $response;
		$response['payment_ready'] = $r['ready'];
		$response['payment_issues'] = $r['issues'];
		if ( ! $r['ready'] ) $response['payment_title'] = __( 'Saved, but visitors cannot pay yet:', 'easy-form-builder' );
		$have = array();
		foreach ( isset( $response['warnings'] ) && is_array( $response['warnings'] ) ? $response['warnings'] : array() as $w ) {
			if ( is_array( $w ) && isset( $w['code'] ) ) $have[ $w['code'] . '|' . ( isset( $w['field_id'] ) ? $w['field_id'] : '' ) ] = true;
		}
		foreach ( $r['issues'] as $is ) {
			if ( isset( $have[ $is['code'] . '|' . $is['field_id'] ] ) ) continue;
			$response['warnings'][] = array( 'code' => $is['code'], 'm' => $is['m'], 'level' => 'error', 'field_id' => $is['field_id'], 'gateway' => $is['gateway'] );
		}
		return $response;
	}

	/** Who may read readiness over REST: the people who can edit forms or the payment settings. */
	public static function rest_can() {
		return self::viewer_can_fix();
	}

	/**
	 * GET  Emsfb/v1/payments/readiness?form_id=N  → the readiness of a stored form;
	 * POST Emsfb/v1/payments/readiness {form: rows} → the readiness of a draft (nothing is stored).
	 * Answer: {form_id, has_payment, ready, issues}; 404 for an unknown form, 400 without either.
	 */
	public static function rest( $r ) {
		$id = (int) $r->get_param( 'form_id' );
		$form = $r->get_method() === 'POST' ? $r->get_param( 'form' ) : null;
		if ( is_string( $form ) ) $form = json_decode( $form, true );
		if ( is_array( $form ) && isset( $form[0] ) ) {
			$out = self::of_form( array_slice( $form, 0, 2000 ), $id );
		} elseif ( $id > 0 ) {
			$out = self::for_form_id( $id );
			if ( $out === null ) return new \WP_REST_Response( array( 'code' => 'form_not_found', 'message' => 'Form not found.' ), 404 );
		} else {
			return new \WP_REST_Response( array( 'code' => 'bad_request', 'message' => 'Give form_id or form.' ), 400 );
		}
		return new \WP_REST_Response( array( 'form_id' => $id ) + $out, 200 );
	}

	/** The readiness of a stored form (null when there is no such form). */
	public static function for_form_id( $form_id ) {
		$rows = Service::form_rows( (int) $form_id );
		return is_array( $rows ) ? self::of_form( $rows, (int) $form_id ) : null;
	}

	/**
	 * The stored forms with a payment field that are not ready: form_id => array( name, issues ).
	 * Read live (a form is listed while it is not ready, and drops out once its keys are added).
	 */
	public static function not_ready_forms( $limit = 300 ) {
		global $wpdb;
		$out = array();
		if ( ! is_object( $wpdb ) ) return $out;
		$types = array_unique( array_merge( Registry::slugs(), self::catalogue_types() ) );
		if ( ! $types ) return $out;
		$t = $wpdb->prefix . 'emsfb_form';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$list = $wpdb->get_results( $wpdb->prepare( "SELECT form_id, form_name, form_structer FROM `{$t}` ORDER BY form_id DESC LIMIT %d", (int) $limit ) );
		$re = '/type\\\\?"\s*:\s*\\\\?"(' . implode( '|', array_map( function ( $s ) { return preg_quote( $s, '/' ); }, $types ) ) . ')\\\\?"/';
		foreach ( (array) $list as $f ) {
			$raw = is_object( $f ) && isset( $f->form_structer ) ? (string) $f->form_structer : '';
			if ( $raw === '' || ! preg_match( $re, $raw ) ) continue;
			$json = function_exists( 'emsfb_json_normalize_efb' ) ? emsfb_json_normalize_efb( $raw ) : str_replace( '\\"', '"', $raw );
			$rows = function_exists( 'emsfb_json_decode_efb' ) ? emsfb_json_decode_efb( $json, true ) : json_decode( $json, true );
			if ( ! is_array( $rows ) ) continue;
			$r = self::of_form( $rows, (int) $f->form_id );
			if ( $r['has_payment'] && ! $r['ready'] ) $out[ (int) $f->form_id ] = array( 'name' => Field::text( str_replace( '@efb@dq#', '"', (string) $f->form_name ), 120 ), 'issues' => $r['issues'] );
		}
		return $out;
	}

	/**
	 * The admin notice on Easy Form Builder screens (the Payments screen too) listing the forms
	 * visitors cannot pay with now, each with its first issue and a link to the form. '' when none.
	 */
	public static function admin_notice_html( array $forms ) {
		if ( ! $forms ) return '';
		$h = '<div class="notice notice-error efb-pay-notready-notice" id="efbPayNotReadyNotice"><p><strong>' . esc_html__( 'Payment setup incomplete: visitors cannot pay with these forms yet.', 'easy-form-builder' ) . '</strong></p><ul style="list-style:disc;padding-inline-start:20px">';
		$n = 0;
		foreach ( $forms as $id => $f ) {
			if ( ++$n > 5 ) break;
			$first = isset( $f['issues'][0]['m'] ) ? (string) $f['issues'][0]['m'] : '';
			$url = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=Emsfb&state=edit-form&id=' . (int) $id ) : '';
			$h .= '<li data-form="' . (int) $id . '"><a href="' . esc_url( $url ) . '">' . esc_html( $f['name'] !== '' ? $f['name'] : '#' . (int) $id ) . '</a>: ' . esc_html( $first );
			if ( count( $f['issues'] ) > 1 ) {
				/* translators: %d: number of further problems */
				$h .= ' ' . esc_html( sprintf( _n( '(and %d more problem)', '(and %d more problems)', count( $f['issues'] ) - 1, 'easy-form-builder' ), count( $f['issues'] ) - 1 ) );
			}
			$h .= '</li>';
		}
		$h .= '</ul>';
		if ( count( $forms ) > 5 ) {
			/* translators: %d: number of further forms */
			$h .= '<p>' . esc_html( sprintf( _n( 'And %d more form.', 'And %d more forms.', count( $forms ) - 5, 'easy-form-builder' ), count( $forms ) - 5 ) ) . '</p>';
		}
		return $h . '</div>';
	}

	/**
	 * The public notice shown in place of the payment field while the form is not ready: the
	 * visitor's sentence for everybody, and for a user who can fix it the issues with their links.
	 * data-efb-pay-blocked tells the page to keep the form's Send / Pay from sending.
	 */
	public static function public_notice( array $readiness, $field_id, $form_id ) {
		$h = '<div class="efb alert alert-warning efb-pf-unavailable efb-pay-blocked" role="alert" data-efb-pay-blocked="1" data-pf="' . esc_attr( (string) $field_id ) . '" data-formid="' . esc_attr( (string) $form_id ) . '">'
			. '<p class="efb mb-0 efb-pay-blocked__visitor" style="unicode-bidi:plaintext">' . esc_html( self::visitor_text() ) . '</p>';
		if ( self::viewer_can_fix() && ! empty( $readiness['issues'] ) ) {
			$h .= '<div class="efb efb-pay-blocked__admin mt-2 pt-2" data-efb-pay-admin="1" style="border-top:1px solid rgba(0,0,0,.15)"><p class="efb mb-1 fw-bold" style="unicode-bidi:plaintext">' . esc_html__( 'Only you see this (you can edit this form): visitors cannot pay yet because', 'easy-form-builder' ) . '</p><ul class="efb mb-1 efb-pay-blocked__list" style="padding-inline-start:1.25rem;padding-inline-end:0;margin-inline-start:0">';
			foreach ( $readiness['issues'] as $is ) {
				$fix = isset( $is['fix'] ) && is_array( $is['fix'] ) ? $is['fix'] : array();
				$h .= '<li data-code="' . esc_attr( (string) $is['code'] ) . '" style="unicode-bidi:plaintext">' . esc_html( (string) $is['m'] );
				if ( ! empty( $fix['url'] ) ) $h .= ' <a href="' . esc_url( (string) $fix['url'] ) . '">' . esc_html( (string) $fix['label'] ) . '</a>';
				$h .= '</li>';
			}
			$h .= '</ul></div>';
		}
		return $h . '</div>';
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
