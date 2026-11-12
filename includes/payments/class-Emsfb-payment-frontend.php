<?php
/**
 * PAY00: the payment field on the public page (markup, per-form CSS, assets) and the REST routes.
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Frontend {

	/** The default texts of the field, filled with the gateway's name (the field's own texts win). */
	public static function default_texts( $gateway_label ) {
		return array(
			'btn'      => str_replace( '%s', '%amount%', __( 'Pay %s', 'easy-form-builder' ) ),
			'secure'   => sprintf( __( 'Payments are processed securely by %s. Your card details never reach this website.', 'easy-form-builder' ), $gateway_label ),
			'summary'  => __( 'Order summary', 'easy-form-builder' ),
			'methods'  => __( 'Choose a payment method', 'easy-form-builder' ),
			'pending'  => __( 'We are waiting for the payment confirmation. You can keep this page open; we will update it automatically.', 'easy-form-builder' ),
			'success'  => __( 'Payment received. Thank you!', 'easy-form-builder' ),
			'fail'     => __( 'The payment was not completed. You have not been charged. You can try again.', 'easy-form-builder' ),
			'redirect' => __( 'Taking you to the secure payment page…', 'easy-form-builder' ),
			'test'     => __( 'Test mode: no real payment will be taken.', 'easy-form-builder' ),
			'amount'   => __( 'Amount', 'easy-form-builder' ),
			'other'    => __( 'Other amount', 'easy-form-builder' ),
			'total'    => __( 'Total', 'easy-form-builder' ),
		);
	}

	/** Texts the runtime needs besides the field's own. */
	public static function runtime_texts() {
		return array(
			'tryAgain'   => __( 'Try again', 'easy-form-builder' ),
			'expired'    => __( 'The payment time ran out. Please try again.', 'easy-form-builder' ),
			'checking'   => __( 'Checking the payment…', 'easy-form-builder' ),
			'invalidAmt' => __( 'Please choose a valid amount.', 'easy-form-builder' ),
			'titleOk'    => __( 'Payment successful', 'easy-form-builder' ),
			'titleFail'  => __( 'Payment not completed', 'easy-form-builder' ),
			'noScript'   => __( 'This form cannot take payments at the moment. Please contact the site owner.', 'easy-form-builder' ),
		);
	}

	public static function locale() {
		return function_exists( 'determine_locale' ) ? determine_locale() : ( function_exists( 'get_locale' ) ? get_locale() : 'en_US' );
	}

	/** Whether the plan opens the mobile overrides (Pro, setting:mobile_layout). */
	public static function mobile_ok() {
		return function_exists( 'efb_plan_at_least_efb' ) ? (bool) efb_plan_at_least_efb( 'pro' ) : false;
	}

	/**
	 * The field's markup for visitors, or a short notice when the form cannot take payments now
	 * (plan, gateway keys): the field is never shown half-working, and the server refuses anyway.
	 *
	 * @param object|array $row   The gateway field row.
	 * @param object|array $form0 Row 0.
	 */
	public static function field_markup( $row, $form0, $form_id ) {
		$type = (string) Field::get( $row, 'type' );
		$info = Registry::public_info( $type );
		$id = (string) Field::get( $row, 'id_' );
		$ok = $info && Service::plan_ok() && ! empty( $info['configured'][ $info['mode'] ] );
		if ( ! $ok ) {
			$msg = esc_html__( 'This form cannot take payments at the moment. Please contact the site owner.', 'easy-form-builder' );
			if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
				$msg .= ' <a href="' . esc_url( admin_url( 'admin.php?page=Emsfb_payments&tab=gateways' ) ) . '">' . esc_html__( 'Set up the payment gateway', 'easy-form-builder' ) . '</a>';
			}
			return '<div class="efb alert alert-warning efb-pf-unavailable" role="alert" data-pf="' . esc_attr( $id ) . '">' . $msg . '</div>';
		}
		$r = Field::resolve( $row, $form0, array( 'methods' => $info['methods'], 'logos' => $info['logos'], 'mobile' => self::mobile_ok() ) );
		$t = Field::texts( $row, self::locale(), self::default_texts( $info['label'] ) );
		return Field::html( $r, $t, array(
			'id'      => $id,
			'gateway' => $type,
			'form_id' => (string) $form_id,
			'label'   => (string) Field::get( $row, 'name' ),
			'test'    => $info['mode'] !== 'live',
		) );
	}

	/** The gateway field rows of a form (objects or arrays). */
	public static function gateway_rows( $rows ) {
		$out = array();
		foreach ( (array) $rows as $i => $r ) {
			if ( $i === 0 ) continue;
			$type = Field::get( $r, 'type' );
			if ( is_string( $type ) && Registry::is_field_type( $type ) ) $out[] = $r;
		}
		return $out;
	}

	/** '' for a form without a framework gateway field. */
	public static function form_css( $rows, $form_id ) {
		$css = '';
		$form0 = isset( $rows[0] ) ? $rows[0] : array();
		foreach ( self::gateway_rows( $rows ) as $r ) {
			$info = Registry::public_info( (string) Field::get( $r, 'type' ) );
			$res = Field::resolve( $r, $form0, array( 'methods' => $info ? $info['methods'] : array(), 'logos' => $info ? $info['logos'] : array(), 'mobile' => self::mobile_ok() ) );
			$css .= Field::css( $res, (string) Field::get( $r, 'id_' ) );
		}
		return $css;
	}

	/** Whether the raw form JSON names a registered gateway type. */
	public static function json_has_gateway( $json ) {
		if ( ! is_string( $json ) || $json === '' ) return false;
		foreach ( Registry::slugs() as $slug ) {
			if ( preg_match( '/type\\\\?"\s*:\s*\\\\?"' . preg_quote( $slug, '/' ) . '\\\\?"/', $json ) ) return true;
		}
		return false;
	}

	/** Styles and scripts of the field; the gateways' own adapters only for gateways on the page. */
	public static function enqueue( $json = '' ) {
		$ver = function ( $rel ) {
			$p = EMSFB_PLUGIN_DIRECTORY . $rel;
			return EMSFB_PLUGIN_VERSION . '.' . ( is_readable( $p ) ? (string) filemtime( $p ) : '0' );
		};
		wp_enqueue_style( 'efb-payment-field', EMSFB_PLUGIN_URL . 'public/assets/css/payment-field-efb.css', array(), $ver( 'public/assets/css/payment-field-efb.css' ) );
		wp_enqueue_script( 'efb-payment-core', EMSFB_PLUGIN_URL . 'public/assets/js/payment-core-efb.js', array(), $ver( 'public/assets/js/payment-core-efb.js' ), true );
		wp_enqueue_script( 'efb-payment-runtime', EMSFB_PLUGIN_URL . 'public/assets/js/payment-runtime-efb.js', array( 'efb-payment-core', 'Emsfb-core_js' ), $ver( 'public/assets/js/payment-runtime-efb.js' ), true );
		$gateways = array();
		foreach ( Registry::all() as $slug => $g ) {
			if ( $json !== '' && ! self::json_has_gateway_slug( $json, $slug ) ) continue;
			$info = Registry::public_info( $slug );
			$gateways[ $slug ] = array( 'label' => $info['label'], 'mode' => $info['mode'], 'flows' => $info['supports']['flows'], 'min_minor' => $info['supports']['min_minor'], 'max_minor' => $info['supports']['max_minor'], 'currencies' => $info['currencies'] );
			$assets = (array) $g->public_assets();
			foreach ( isset( $assets['js'] ) ? (array) $assets['js'] : array() as $i => $src ) {
				if ( is_string( $src ) && $src !== '' ) wp_enqueue_script( 'efb-pay-' . $slug . '-' . $i, $src, array( 'efb-payment-runtime' ), EMSFB_PLUGIN_VERSION, true );
			}
			foreach ( isset( $assets['css'] ) ? (array) $assets['css'] : array() as $i => $src ) {
				if ( is_string( $src ) && $src !== '' ) wp_enqueue_style( 'efb-pay-' . $slug . '-' . $i, $src, array( 'efb-payment-field' ), EMSFB_PLUGIN_VERSION );
			}
		}
		wp_localize_script( 'efb-payment-runtime', 'efb_pay_vars', array(
			'rest'     => esc_url_raw( rest_url( 'Emsfb/v1/payments/' ) ),
			'locale'   => self::locale(),
			'gateways' => $gateways,
			'text'     => array_merge( self::default_texts( '%s' ), self::runtime_texts() ),
		) );
	}

	private static function json_has_gateway_slug( $json, $slug ) {
		return (bool) preg_match( '/type\\\\?"\s*:\s*\\\\?"' . preg_quote( $slug, '/' ) . '\\\\?"/', $json );
	}

	/** efb_var.pay on the builder screens: every gateway, without any secret. */
	public static function builder_vars() {
		$g = array();
		foreach ( Registry::slugs() as $slug ) $g[ $slug ] = Registry::public_info( $slug );
		return array(
			'gateways' => $g,
			'plan_ok'  => Service::plan_ok(),
			'mobile'   => self::mobile_ok(),
			'settings' => admin_url( 'admin.php?page=Emsfb_payments&tab=gateways' ),
			'text'     => self::default_texts( '%s' ),
			'locale'   => self::locale(),
		);
	}
}

class Routes {

	/** Called from _Public's rest_api_init next to efb_register_payment_rest_routes. */
	public static function register( $public ) {
		if ( ! Registry::all() ) return;
		$slug = '(?P<gateway>[a-z][a-z0-9]{1,19})';
		register_rest_route( 'Emsfb/v1', 'payments/create', array(
			'methods'             => 'POST',
			'callback'            => array( $public, 'payment_create_api_efb' ),
			'permission_callback' => array( $public, 'check_nonce_permission_efb' ),
		) );
		register_rest_route( 'Emsfb/v1', 'payments/webhook/' . $slug, array(
			'methods'             => 'POST',
			'callback'            => array( Service::class, 'handle_webhook' ),
			'permission_callback' => '__return_true', // verified inside the gateway (signature, token or refetch)
		) );
		register_rest_route( 'Emsfb/v1', 'payments/return/' . $slug, array(
			'methods'             => array( 'GET', 'POST' ),
			'callback'            => array( Service::class, 'handle_return' ),
			'permission_callback' => '__return_true', // the HMAC signature s is checked in the handler
		) );
		register_rest_route( 'Emsfb/v1', 'payments/status', array(
			'methods'             => 'GET',
			'callback'            => array( Service::class, 'handle_status' ),
			'permission_callback' => '__return_true', // the HMAC signature s is checked in the handler
		) );
		register_rest_route( 'Emsfb/v1', 'payments/poll', array(
			'methods'             => 'POST',
			'callback'            => array( Service::class, 'handle_poll' ),
			'permission_callback' => array( $public, 'check_nonce_permission_efb' ),
		) );
	}
}
