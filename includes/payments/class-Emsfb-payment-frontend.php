<?php
/**
 * PAY00: the payment field on the public page (markup, per-form CSS, assets) and the REST routes.
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Frontend {

	/**
	 * The default texts of the field, filled with the gateway's name (the field's own texts win).
	 * $gateway_texts: the gateway's own defaults (Gateway::field_defaults()['texts'], checked by
	 * Registry::field_defaults()), e.g. a button text for a QR payment.
	 */
	public static function default_texts( $gateway_label, array $gateway_texts = array() ) {
		$out = self::core_texts( $gateway_label );
		foreach ( $gateway_texts as $k => $v ) {
			if ( isset( $out[ $k ] ) && $k !== 'test' && is_string( $v ) && $v !== '' ) $out[ $k ] = str_replace( '%s', $gateway_label, $v );
		}
		return $out;
	}

	private static function core_texts( $gateway_label ) {
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
			'min'        => __( 'The amount must be at least %s.', 'easy-form-builder' ),
			'max'        => __( 'The amount must be at most %s.', 'easy-form-builder' ),
			/* translators: %1$s: payment method, %2$s: amount with currency */
			'methodMin'  => __( '%1$s accepts amounts from %2$s. Please choose another payment method.', 'easy-form-builder' ),
			/* translators: %1$s: payment method, %2$s: amount with currency */
			'methodMax'  => __( '%1$s accepts amounts up to %2$s. Please choose another payment method.', 'easy-form-builder' ),
			'newAttempt' => __( 'Preparing a new payment…', 'easy-form-builder' ),
			/* translators: %1$s: name of the form field (e.g. Mobile), %2$s: payment method */
			'payerMissingFor' => __( 'Please fill in "%1$s": it is needed to pay with %2$s.', 'easy-form-builder' ),
			/* translators: %s: name of the form field (e.g. Email) */
			'payerMissing' => __( 'Please fill in "%s": it is needed for the payment.', 'easy-form-builder' ),
			/* translators: %s: name of the form field (e.g. Email) */
			'payerEmail' => __( 'Please enter a valid e-mail address in "%s".', 'easy-form-builder' ),
			/* translators: %s: name of the form field (e.g. Mobile) */
			'payerPhone' => __( 'Please enter a valid phone number in "%s".', 'easy-form-builder' ),
			/* translators: %s: name of the form field (e.g. Mobile) */
			'payerPhoneIntl' => __( 'Please enter the phone number in "%s" with its country code, starting with + (for example +33 6 12 34 56 78).', 'easy-form-builder' ),
			/* translators: %s: name of the form field (e.g. Country) */
			'payerCountry' => __( 'Please choose a country in "%s".', 'easy-form-builder' ),
			/* translators: %s: name of the form field (e.g. Country) */
			'payerCountryNo' => __( 'Payments with this method are not available for the country in "%s". Please check it or choose another payment method.', 'easy-form-builder' ),
			'neededForPay' => __( 'Needed for the payment', 'easy-form-builder' ),
			'network' => __( 'We could not reach the site. Check your internet connection and try again. You have not been charged.', 'easy-form-builder' ),
			'returnedCancel' => __( 'You left the payment page before paying. You have not been charged. You can try again.', 'easy-form-builder' ),
			'rateLimited' => __( 'Too many payment attempts. Please wait a minute and try again.', 'easy-form-builder' ),
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
		$info = Registry::public_info( $type, array( 'currency' => (string) Field::get( $form0, 'currency' ) ) );
		$id = (string) Field::get( $row, 'id_' );
		$ok = $info && Service::plan_ok() && ! empty( $info['configured'][ $info['mode'] ] );
		if ( ! $ok ) {
			$msg = esc_html__( 'This form cannot take payments at the moment. Please contact the site owner.', 'easy-form-builder' );
			if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
				$msg .= ' <a href="' . esc_url( admin_url( 'admin.php?page=Emsfb_payments&tab=gateways' ) ) . '">' . esc_html__( 'Set up the payment gateway', 'easy-form-builder' ) . '</a>';
			}
			return '<div class="efb alert alert-warning efb-pf-unavailable" role="alert" data-pf="' . esc_attr( $id ) . '">' . $msg . '</div>';
		}
		$r = Field::resolve( $row, $form0, array( 'methods' => $info['methods'], 'logos' => $info['logos'], 'mobile' => self::mobile_ok(), 'defaults' => $info['defaults']['pf'] ) );
		$t = Field::texts( $row, self::locale(), self::default_texts( $info['label'], $info['defaults']['texts'] ) );
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
			$info = Registry::public_info( (string) Field::get( $r, 'type' ), array( 'currency' => (string) Field::get( $form0, 'currency' ) ) );
			$res = Field::resolve( $r, $form0, array( 'methods' => $info ? $info['methods'] : array(), 'logos' => $info ? $info['logos'] : array(), 'mobile' => self::mobile_ok(), 'defaults' => $info ? $info['defaults']['pf'] : array() ) );
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
			// Each method's own limits (Gateway::methods() extras), so the page can name the method that does not fit.
			$limits = array();
			foreach ( $info['methods'] as $m ) {
				if ( isset( $m[2] ) && ( isset( $m[2]['min_minor'] ) || isset( $m[2]['max_minor'] ) ) ) {
					$limits[ $m[0] ] = array( 'label' => $m[1], 'min_minor' => isset( $m[2]['min_minor'] ) ? $m[2]['min_minor'] : array(), 'max_minor' => isset( $m[2]['max_minor'] ) ? $m[2]['max_minor'] : array() );
				}
			}
			// The payer values the gateway requires (field options of type "field": required, or required_for
			// some methods): the page stops Pay at the empty field with this message.
			$requires = array();
			foreach ( $info['field_schema'] as $d ) {
				if ( $d['type'] === 'field' && ( $d['required'] || $d['required_for'] || $d['format'] !== '' ) ) $requires[] = array( 'key' => $d['key'], 'label' => $d['label'], 'required' => $d['required'] && ! $d['required_for'], 'required_for' => $d['required_for'], 'visitor_message' => $d['visitor_message'], 'format' => $d['format'], 'countries' => $d['countries'], 'invalid_message' => $d['invalid_message'] );
			}
			$messages = array();
			try { $own = $g->messages(); } catch ( \Throwable $e ) { $own = array(); }
			foreach ( is_array( $own ) ? $own : array() as $code => $text ) {
				if ( is_string( $code ) && preg_match( '/^[a-z0-9_]{1,40}$/', $code ) && is_string( $text ) && Field::text( $text, 300 ) !== '' ) $messages[ $code ] = Field::text( $text, 300 );
			}
			$gateways[ $slug ] = array( 'label' => $info['label'], 'mode' => $info['mode'], 'flows' => $info['supports']['flows'], 'min_minor' => $info['supports']['min_minor'], 'max_minor' => $info['supports']['max_minor'], 'currencies' => $info['currencies'], 'method_limits' => $limits, 'requires' => $requires, 'messages' => $messages );
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

	/** The labels of the builder's settings panel (payment-field-efb.js L()). */
	public static function builder_labels() {
		return array(
			'slot' => __( 'The payment page or card fields of the gateway open here.', 'easy-form-builder' ),
			'notConfigured' => __( 'This gateway has no keys yet. Visitors will see "cannot take payments" until it is set up.', 'easy-form-builder' ),
			'reset' => __( 'Default', 'easy-form-builder' ),
			'contrastOk' => /* translators: %s is a number or %amount% stays as written */ __( 'Contrast %s:1, readable.', 'easy-form-builder' ),
			'contrastLow' => /* translators: %s is a number or %amount% stays as written */ __( 'The button text may be hard to read: contrast %s:1, at least 4.5:1 is needed.', 'easy-form-builder' ),
			'elSummary' => __( 'Order summary', 'easy-form-builder' ),
			'elAmount' => __( 'Amount (donation)', 'easy-form-builder' ),
			'elMethods' => __( 'Payment methods', 'easy-form-builder' ),
			'elFields' => __( 'Card / QR area', 'easy-form-builder' ),
			'elButton' => __( 'Pay button', 'easy-form-builder' ),
			'elSecure' => __( 'Security note', 'easy-form-builder' ),
			'elLogos' => __( 'Logos', 'easy-form-builder' ),
			'order' => __( 'Order of the parts', 'easy-form-builder' ),
			'show' => __( 'Show', 'easy-form-builder' ),
			'up' => __( 'Move up', 'easy-form-builder' ),
			'down' => __( 'Move down', 'easy-form-builder' ),
			'tBtn' => /* translators: %s is a number or %amount% stays as written */ __( 'Button text (%amount% = amount)', 'easy-form-builder' ),
			'tSecure' => __( 'Security note', 'easy-form-builder' ),
			'tSummary' => __( 'Summary title', 'easy-form-builder' ),
			'tMethods' => __( 'Methods title', 'easy-form-builder' ),
			'tAmount' => __( 'Amount title', 'easy-form-builder' ),
			'tOther' => __( 'Other amount', 'easy-form-builder' ),
			'tPending' => __( 'Waiting message', 'easy-form-builder' ),
			'tSuccess' => __( 'Success message', 'easy-form-builder' ),
			'tFail' => __( 'Failure message', 'easy-form-builder' ),
			'language' => __( 'Language', 'easy-form-builder' ),
			'addLanguage' => __( 'Add a language (locale, e.g. de_DE)', 'easy-form-builder' ),
			'add' => __( 'Add', 'easy-form-builder' ),
			'resetTexts' => __( 'Back to the default translation', 'easy-form-builder' ),
			'stLive' => __( 'Live: connected', 'easy-form-builder' ),
			'stTest' => __( 'Test mode only (no real payments)', 'easy-form-builder' ),
			'stNoKeys' => __( 'No keys yet', 'easy-form-builder' ),
			'settings' => __( 'Gateway settings and keys', 'easy-form-builder' ),
			'label' => __( 'Label', 'easy-form-builder' ),
			'currency' => __( 'Currency', 'easy-form-builder' ),
			'fixed' => __( 'Fixed amount (added to the total)', 'easy-form-builder' ),
			'fixedLabel' => __( 'Name of the fixed amount', 'easy-form-builder' ),
			'donation' => __( 'Donation: the visitor chooses the amount', 'easy-form-builder' ),
			'presets' => __( 'Suggested amounts (comma separated, up to 8)', 'easy-form-builder' ),
			'other' => __( 'Allow another amount', 'easy-form-builder' ),
			'min' => __( 'Minimum', 'easy-form-builder' ),
			'max' => __( 'Maximum', 'easy-form-builder' ),
			'default' => __( 'Preselected amount', 'easy-form-builder' ),
			'amountHelp' => __( 'The total also counts the priced choices and price fields of the form. The server computes the amount; the browser only shows it.', 'easy-form-builder' ),
			'surchargeEu' => __( 'Charging a mandatory fee on consumer cards is not allowed in the EU. Use the optional "cover the fee" setting for donations instead.', 'easy-form-builder' ),
			'btnText' => /* translators: %s is a number or %amount% stays as written */ __( 'Button text (%amount% = amount)', 'easy-form-builder' ),
			'btnBg' => __( 'Button colour', 'easy-form-builder' ),
			'btnFg' => __( 'Text colour', 'easy-form-builder' ),
			'auto' => __( 'Automatic (readable)', 'easy-form-builder' ),
			'custom' => __( 'Custom', 'easy-form-builder' ),
			'btnHover' => __( 'Colour on hover', 'easy-form-builder' ),
			'radius' => __( 'Corner radius (px)', 'easy-form-builder' ),
			'size' => __( 'Size', 'easy-form-builder' ),
			'small' => __( 'Small', 'easy-form-builder' ),
			'medium' => __( 'Medium', 'easy-form-builder' ),
			'large' => __( 'Large', 'easy-form-builder' ),
			'width' => __( 'Width', 'easy-form-builder' ),
			'full' => __( 'Full width', 'easy-form-builder' ),
			'fit' => __( 'Fit the text', 'easy-form-builder' ),
			'icon' => __( 'Icon', 'easy-form-builder' ),
			'none' => __( 'None', 'easy-form-builder' ),
			'iconPos' => __( 'Icon position', 'easy-form-builder' ),
			'start' => __( 'Before the text', 'easy-form-builder' ),
			'end' => __( 'After the text', 'easy-form-builder' ),
			'layout' => __( 'Layout', 'easy-form-builder' ),
			'stack' => __( 'One column', 'easy-form-builder' ),
			'split' => __( 'Summary beside (wide screens)', 'easy-form-builder' ),
			'methodsStyle' => __( 'Methods', 'easy-form-builder' ),
			'list' => __( 'List', 'easy-form-builder' ),
			'grid' => __( 'Grid', 'easy-form-builder' ),
			'tabs' => __( 'Tabs', 'easy-form-builder' ),
			'select' => __( 'Drop-down', 'easy-form-builder' ),
			'summary' => __( 'Summary', 'easy-form-builder' ),
			'lines' => __( 'Every item', 'easy-form-builder' ),
			'totalOnly' => __( 'Total only', 'easy-form-builder' ),
			'labelPos' => __( 'Label', 'easy-form-builder' ),
			'top' => __( 'Above', 'easy-form-builder' ),
			'beside' => __( 'Beside', 'easy-form-builder' ),
			'hidden' => __( 'Hidden (screen readers only)', 'easy-form-builder' ),
			'cardBg' => __( 'Background', 'easy-form-builder' ),
			'cardBorder' => __( 'Border', 'easy-form-builder' ),
			'cardRadius' => __( 'Box corner radius (px)', 'easy-form-builder' ),
			'accent' => __( 'Accent and focus colour', 'easy-form-builder' ),
			'logoSize' => __( 'Logo size', 'easy-form-builder' ),
			'digits' => __( 'Digits', 'easy-form-builder' ),
			'localeDigits' => __( 'Digits of the language', 'easy-form-builder' ),
			'actAdmin' => __( 'E-mail to the owner', 'easy-form-builder' ),
			'actUser' => __( 'E-mail to the payer', 'easy-form-builder' ),
			'actSms' => __( 'SMS', 'easy-form-builder' ),
			'actWh' => __( 'Webhooks', 'easy-form-builder' ),
			'whenPaid' => __( 'After payment', 'easy-form-builder' ),
			'whenSubmit' => __( 'When the form is sent (before payment)', 'easy-form-builder' ),
			'whenBoth' => __( 'Both', 'easy-form-builder' ),
			'actHelp' => __( 'An unpaid entry stays "pending payment". Choose when each notification goes out.', 'easy-form-builder' ),
			'failNotify' => __( 'E-mail the owner when a payment fails or expires', 'easy-form-builder' ),
			'asDesktop' => __( 'As on wide screens', 'easy-form-builder' ),
			'mobileHide' => __( 'Hide on phones (comma separated: summary, secure, logos, methods, amount)', 'easy-form-builder' ),
			'mobilePro' => __( 'Phone layout of the field is part of Pro.', 'easy-form-builder' ),
			'secGateway' => __( 'Gateway', 'easy-form-builder' ),
			'secAmount' => __( 'Amount', 'easy-form-builder' ),
			'secButton' => __( 'Button', 'easy-form-builder' ),
			'secLayout' => __( 'Layout', 'easy-form-builder' ),
			'secTexts' => __( 'Texts and languages', 'easy-form-builder' ),
			'secNotify' => __( 'Notifications', 'easy-form-builder' ),
			'secMobile' => __( 'Phones', 'easy-form-builder' ),
			'secOptions' => __( 'Payer details and gateway options', 'easy-form-builder' ),
			'methodsPick' => __( 'Payment methods (order and choice)', 'easy-form-builder' ),
			'methodsHelp' => __( 'Unticked methods are not offered on this form. The gateway still checks every payment.', 'easy-form-builder' ),
			/* translators: %1$s: gateway name, %2$s: currency codes, %3$s: the form's currency */
			'wCurrency' => __( '%1$s only accepts %2$s. This form uses %3$s, so visitors cannot pay. Change the currency in the payment field.', 'easy-form-builder' ),
			/* translators: %1$s: gateway name, %2$s: what is needed (option name, e.g. Payer e-mail), %3$s: kind of field (e.g. an Email field) */
			'wRequired' => __( '%1$s requires "%2$s". Add %3$s to the form and select it in the payment field\'s "%2$s" option.', 'easy-form-builder' ),
			/* translators: %1$s: gateway name, %2$s: what is needed (option name, e.g. Payer e-mail), %3$s: payment methods, %4$s: kind of field (e.g. an Email field) */
			'wRequiredFor' => __( '%1$s requires "%2$s" to pay with %3$s. Add %4$s to the form and select it in the payment field\'s "%2$s" option.', 'easy-form-builder' ),
			'kindEmail' => __( 'an Email field', 'easy-form-builder' ),
			'kindPhone' => __( 'a phone number field', 'easy-form-builder' ),
			'kindAny' => __( 'a field for it', 'easy-form-builder' ),
			/* translators: %1$s: gateway name, %2$s: name of an option of the payment field */
			'wMissing' => __( '%1$s: the field chosen for "%2$s" is no longer in the form. Choose another one.', 'easy-form-builder' ),
			/* translators: %1$s: gateway name, %2$s: name of an option of the payment field */
			'wType' => __( '%1$s: the field chosen for "%2$s" is not the right kind of field.', 'easy-form-builder' ),
			/* translators: %1$s: name of a form field (e.g. Mobile), %2$s: gateway name */
			'wNotRequired' => __( '"%1$s" is needed by %2$s for the payment but is not marked Required. Visitors are asked for it when they press Pay; mark it Required so they see it earlier.', 'easy-form-builder' ),
			/* translators: %s: gateway name */
			'wNoKeysLive' => __( '%s has no keys for Live mode yet, so visitors cannot pay. Add them in Payments → Gateways.', 'easy-form-builder' ),
			/* translators: %s: gateway name */
			'wNoKeysTest' => __( '%s has no keys for Test mode yet, so the form cannot be tried. Add them in Payments → Gateways.', 'easy-form-builder' ),
			/* translators: %s: gateway name */
			'wTestMode' => __( '%s is in Test mode: no real payments are taken. Switch it to Live in Payments → Gateways when you are ready.', 'easy-form-builder' ),
			/* translators: %s: gateway name */
			'wLocal' => __( 'This site cannot be reached from the internet, so %s cannot send payment confirmations to it. Payments are confirmed when the visitor comes back and by the regular check.', 'easy-form-builder' ),
			/* translators: %1$s: gateway name, %2$s: the gateway's maximum, %3$s: the form's fixed amount */
			'wFixedMax' => __( '%1$s accepts payments up to %2$s. The fixed amount of this form is %3$s, so visitors cannot pay.', 'easy-form-builder' ),
			/* translators: %1$s: gateway name, %2$s: the gateway's minimum, %3$s: the form's fixed amount */
			'wFixedMin' => __( '%1$s accepts payments from %2$s. The fixed amount of this form is %3$s; visitors can only pay when other choices raise the total.', 'easy-form-builder' ),
			/* translators: %1$s: gateway name, %2$s: the gateway's maximum, %3$s: the donation maximum of the form */
			'wDonMax' => __( '%1$s accepts payments up to %2$s. The largest donation of this form (%3$s) is above it.', 'easy-form-builder' ),
			/* translators: %1$s: gateway name, %2$s: the gateway's minimum, %3$s: the donation minimum of the form */
			'wDonMin' => __( '%1$s accepts payments from %2$s. The smallest donation of this form (%3$s) is below it.', 'easy-form-builder' ),
			/* translators: %1$s: gateway name, %2$s: payment methods, %3$s: the form's currency */
			'wMethodCur' => __( '%1$s: %2$s cannot take %3$s, so it is not offered on this form. Choose other methods or change the currency.', 'easy-form-builder' ),
			'wMultiple' => __( 'This form has more than one payment field. A form can take one payment: remove the others.', 'easy-form-builder' ),
			/* translators: %s: the form's type (e.g. survey) */
			'wFormType' => __( 'A payment field only works in a payment form. This form is a "%s" form: create a payment form or remove the payment field.', 'easy-form-builder' ),
		);
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
			'labels'   => self::builder_labels(),
			'locale'   => self::locale(),
			'site_local' => Service::site_is_local(),
		);
	}

	/** efb_admin_localize_vars: efb_var.pay (only when a gateway is registered). */
	public static function localize_vars( $vars, $context = '' ) {
		if ( is_array( $vars ) && Registry::all() ) $vars['pay'] = self::builder_vars();
		return $vars;
	}
}

class Routes {

	/**
	 * The ?efb_pay_webhook=<gateway> request as a WP_REST_Request for Service::handle_webhook():
	 * the raw body, the headers, the query parameters (gateways that sign their own webhook address
	 * read them), the form-encoded fields, and the gateway. Same verification as the REST route.
	 */
	public static function fallback_request( $slug, array $get, array $server, $body ) {
		$r = new \WP_REST_Request( 'POST', '/Emsfb/v1/payments/webhook/' . $slug );
		$r->set_body( (string) $body );
		foreach ( $server as $k => $v ) {
			if ( ! is_string( $k ) || ! is_scalar( $v ) ) continue;
			if ( strpos( $k, 'HTTP_' ) === 0 ) $r->set_header( strtolower( substr( $k, 5 ) ), (string) $v );
			elseif ( in_array( $k, array( 'CONTENT_TYPE', 'CONTENT_LENGTH' ), true ) ) $r->set_header( strtolower( $k ), (string) $v );
		}
		$ct = isset( $server['CONTENT_TYPE'] ) ? strtolower( (string) $server['CONTENT_TYPE'] ) : '';
		if ( strpos( $ct, 'application/x-www-form-urlencoded' ) !== false && strlen( (string) $body ) <= 262144 ) {
			$f = array();
			parse_str( (string) $body, $f );
			foreach ( $f as $k => $v ) {
				if ( is_string( $k ) && is_scalar( $v ) ) $r->set_param( $k, (string) $v );
			}
		}
		foreach ( $get as $k => $v ) {
			if ( is_string( $k ) && $k !== 'efb_pay_webhook' && is_scalar( $v ) ) $r->set_param( $k, (string) $v );
		}
		$r->set_param( 'gateway', $slug );
		return $r;
	}

	/** parse_request: answers POST /?efb_pay_webhook=<gateway> with the webhook handler, then stops. */
	public static function fallback_webhook() {
		// phpcs:disable WordPress.Security.NonceVerification -- a provider's webhook; verified by the gateway
		if ( ! isset( $_GET['efb_pay_webhook'] ) || ! is_string( $_GET['efb_pay_webhook'] ) ) return;
		$slug = (string) wp_unslash( $_GET['efb_pay_webhook'] );
		if ( ! preg_match( '/^[a-z][a-z0-9]{1,19}$/', $slug ) || ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : '' ) !== 'POST' ) return;
		$body = file_get_contents( 'php://input', false, null, 0, 262145 );
		$res = Service::handle_webhook( self::fallback_request( $slug, wp_unslash( $_GET ), $_SERVER, is_string( $body ) ? $body : '' ) );
		// phpcs:enable
		$code = $res instanceof \WP_REST_Response ? (int) $res->get_status() : 200;
		if ( ! headers_sent() ) {
			status_header( $code );
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Cache-Control: no-store' );
		}
		echo wp_json_encode( $res instanceof \WP_REST_Response ? $res->get_data() : null );
		exit;
	}

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
		register_rest_route( 'Emsfb/v1', 'payments/retry', array(
			'methods'             => 'POST',
			'callback'            => array( Service::class, 'handle_retry' ),
			'permission_callback' => array( $public, 'check_nonce_permission_efb' ),
		) );
		register_rest_route( 'Emsfb/v1', 'payments/poll', array(
			'methods'             => 'POST',
			'callback'            => array( Service::class, 'handle_poll' ),
			'permission_callback' => array( $public, 'check_nonce_permission_efb' ),
		) );
	}
}
