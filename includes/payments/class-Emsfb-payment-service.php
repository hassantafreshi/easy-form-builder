<?php
/**
 * PAY00 §7, §9: the coordinator. Creates payments from a validated form submission, applies the
 * provider's truth (webhook, return, poll, cron, admin), finalizes exactly once, refunds.
 *
 * The rule that holds everything together: a payment becomes "paid" only from Gateway::fetch()
 * (the provider's API) or from a webhook the gateway verified, with an amount and currency that
 * match the stored row; then finalize() runs behind Store::claim_finalize(), so webhook, return
 * URL, poll and cron can all arrive at once and the entry is completed, and its notifications
 * sent, one time.
 */
namespace Emsfb\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Service {

	const SITE_SECRET_OPTION = 'emsfb_pmt_site_secret';

	/* ------------------------------------------------------------------ helpers */

	/** The plan the framework needs (proposed default: Free Plus; filter efb_payments_required_plan). */
	public static function plan_ok() {
		$need = function_exists( 'apply_filters' ) ? apply_filters( 'efb_payments_required_plan', 'free_plus' ) : 'free_plus';
		if ( function_exists( 'efb_plan_at_least_efb' ) ) return (bool) efb_plan_at_least_efb( $need );
		if ( class_exists( '\Emsfb\Builder_Api' ) ) return \Emsfb\Builder_Api::plan_at_least( $need );
		return true;
	}

	private static function site_secret() {
		$s = function_exists( 'get_option' ) ? get_option( self::SITE_SECRET_OPTION, '' ) : '';
		if ( ! is_string( $s ) || strlen( $s ) < 32 ) {
			$s = bin2hex( random_bytes( 32 ) );
			if ( function_exists( 'add_option' ) && ! add_option( self::SITE_SECRET_OPTION, $s, '', 'no' ) ) {
				$s = (string) get_option( self::SITE_SECRET_OPTION, $s );
			}
		}
		return $s;
	}

	/** The signature in return / status URLs: HMAC(uid|gateway), 32 hex characters. */
	public static function sign( $uid, $gateway ) {
		return substr( hash_hmac( 'sha256', $uid . '|' . $gateway, self::site_secret() ), 0, 32 );
	}

	public static function verify_sig( $uid, $gateway, $sig ) {
		return is_string( $sig ) && strlen( $sig ) === 32 && hash_equals( self::sign( $uid, $gateway ), $sig );
	}

	public static function webhook_url( $slug ) {
		return rest_url( 'Emsfb/v1/payments/webhook/' . $slug );
	}

	/**
	 * The same webhook through the site's front door, for hosts that block the REST API for
	 * visitors: home_url('/?efb_pay_webhook=<gateway>') → Routes::fallback_webhook() → handle_webhook().
	 */
	public static function webhook_fallback_url( $slug ) {
		return add_query_arg( 'efb_pay_webhook', $slug, home_url( '/' ) );
	}

	public static function return_url( $p ) {
		return add_query_arg( array( 'p' => $p['uid'], 's' => self::sign( $p['uid'], $p['gateway'] ) ), rest_url( 'Emsfb/v1/payments/return/' . $p['gateway'] ) );
	}

	/**
	 * Whether this site cannot be reached from the internet (a provider's webhook cannot arrive):
	 * localhost, *.local / *.test / *.localhost / *.invalid, a loopback or private address.
	 * Filter efb_payment_site_is_local.
	 */
	public static function site_is_local( $url = null ) {
		$u = $url === null ? ( function_exists( 'home_url' ) ? home_url( '/' ) : '' ) : (string) $url;
		$h = strtolower( (string) parse_url( $u, PHP_URL_HOST ) );
		$local = $h === '' || $h === 'localhost' || (bool) preg_match( '/\.(local|test|localhost|invalid|example|internal|lan)$/', $h );
		if ( ! $local && filter_var( $h, FILTER_VALIDATE_IP ) ) $local = ! filter_var( $h, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		if ( ! $local && strpos( $h, '.' ) === false ) $local = true;
		return function_exists( 'apply_filters' ) ? (bool) apply_filters( 'efb_payment_site_is_local', $local, $u ) : $local;
	}

	/** Per-IP, per-minute counter. */
	public static function rate_ok( $bucket, $limit ) {
		$ip = class_exists( '\Emsfb\Privacy' ) ? \Emsfb\Privacy::client_ip() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '' );
		$key = 'efb_pmt_rl_' . $bucket . '_' . md5( $ip . '|' . gmdate( 'YmdHi', Store::time() ) );
		$n = (int) get_transient( $key );
		if ( $n >= (int) $limit ) return false;
		set_transient( $key, $n + 1, 70 );
		return true;
	}

	/**
	 * The visitor's message for an error code: the gateway's own (Gateway::messages()), else core's;
	 * then the filter efb_payment_message( $text, $code, $extra, $gateway_slug ). $extra may name the
	 * gateway ('gateway' => slug) and carry the values a text uses (amount, method, label).
	 */
	/** The gateway of the create / retry this request serves (for its own messages). */
	private static $gw_slug = '';

	public static function message( $code, $extra = array() ) {
		$slug = isset( $extra['gateway'] ) && is_string( $extra['gateway'] ) ? $extra['gateway'] : self::$gw_slug;
		$own = '';
		$g = $slug !== '' ? Registry::get( $slug ) : null;
		if ( $g ) {
			try { $map = $g->messages(); } catch ( \Throwable $e ) { $map = array(); }
			if ( is_array( $map ) && isset( $map[ $code ] ) && is_string( $map[ $code ] ) ) {
				$own = Field::text( strtr( $map[ $code ], array( '%amount%' => isset( $extra['amount'] ) ? (string) $extra['amount'] : '', '%method%' => isset( $extra['method'] ) ? (string) $extra['method'] : '', '%field%' => isset( $extra['label'] ) ? (string) $extra['label'] : '' ) ), 300 );
			}
		}
		$m = $own !== '' ? $own : self::core_message( $code, $extra );
		return function_exists( 'apply_filters' ) ? (string) apply_filters( 'efb_payment_message', $m, $code, $extra, $slug ) : $m;
	}

	private static function core_message( $code, $extra ) {
		switch ( $code ) {
			case 'payer_missing':
				if ( isset( $extra['visitor'] ) && $extra['visitor'] !== '' ) return $extra['visitor'];
				return ! empty( $extra['method'] )
					/* translators: %1$s: name of the form field (e.g. Mobile), %2$s: payment method */
					? sprintf( __( 'Please fill in "%1$s": it is needed to pay with %2$s.', 'easy-form-builder' ), isset( $extra['label'] ) ? $extra['label'] : '', $extra['method'] )
					/* translators: %s: name of the form field (e.g. Email) */
					: sprintf( __( 'Please fill in "%s": it is needed for the payment.', 'easy-form-builder' ), isset( $extra['label'] ) ? $extra['label'] : '' );
			case 'payer_invalid':
				if ( isset( $extra['visitor'] ) && $extra['visitor'] !== '' ) return $extra['visitor'];
				$lab = isset( $extra['label'] ) ? $extra['label'] : '';
				switch ( isset( $extra['format'] ) ? $extra['format'] : '' ) {
					case 'email':
						/* translators: %s: name of the form field (e.g. Email) */
						return sprintf( __( 'Please enter a valid e-mail address in "%s".', 'easy-form-builder' ), $lab );
					case 'phone':
						/* translators: %s: name of the form field (e.g. Mobile) */
						return sprintf( __( 'Please enter a valid phone number in "%s".', 'easy-form-builder' ), $lab );
					case 'phone_e164':
						/* translators: %s: name of the form field (e.g. Mobile) */
						return sprintf( __( 'Please enter the phone number in "%s" with its country code, starting with + (for example +33 6 12 34 56 78).', 'easy-form-builder' ), $lab );
					case 'country':
						/* translators: %s: name of the form field (e.g. Country) */
						return sprintf( __( 'Please choose a country in "%s".', 'easy-form-builder' ), $lab );
					case 'country_not_allowed':
						/* translators: %s: name of the form field (e.g. Country) */
						return sprintf( __( 'Payments with this method are not available for the country in "%s". Please check it or choose another payment method.', 'easy-form-builder' ), $lab );
				}
				/* translators: %s: name of the form field */
				return sprintf( __( 'The payment provider did not accept the value of "%s". Please check it and press Pay again.', 'easy-form-builder' ), $lab );
			case 'declined':
				return __( 'Your payment was declined. You have not been charged. Please try another card or payment method.', 'easy-form-builder' );
			case 'insufficient_funds':
				return __( 'The payment was declined because there were not enough funds. You have not been charged. Please use another card or payment method.', 'easy-form-builder' );
			case 'card_invalid':
				return __( 'The card details were not accepted. You have not been charged. Please check the card number, expiry date and security code, or use another card.', 'easy-form-builder' );
			case 'auth_failed':
				return __( 'The card security check (3-D Secure) was not completed. You have not been charged. Please try again or use another card.', 'easy-form-builder' );
			case 'fraud_blocked':
				return __( 'The payment provider could not accept this payment. You have not been charged. Please use another payment method or contact the site.', 'easy-form-builder' );
			case 'limit_exceeded':
				return __( 'The payment is above the limit of your card or account. You have not been charged. Please use another card or payment method.', 'easy-form-builder' );
			case 'canceled':
				return __( 'The payment was cancelled. You have not been charged. You can try again when you are ready.', 'easy-form-builder' );
			case 'expired':
				return __( 'The payment time ran out. You have not been charged. Please try again.', 'easy-form-builder' );
			case 'returned_cancel':
				return __( 'You left the payment page before paying. You have not been charged. You can try again.', 'easy-form-builder' );
			case 'provider_unavailable':
				return __( 'The payment service is not responding right now. You have not been charged. Please try again in a few minutes.', 'easy-form-builder' );
			case 'rate_limited':
			case 'efb_rate_limited':
				return __( 'Too many payment attempts. Please wait a minute and try again.', 'easy-form-builder' );
			case 'method_unavailable':
				return __( 'This payment method is not available for this payment right now. Please choose another payment method.', 'easy-form-builder' );
			case 'quote_changed':
				return sprintf( __( 'The amount was updated to %s. Please check and press Pay again.', 'easy-form-builder' ), isset( $extra['amount'] ) ? $extra['amount'] : '' );
			case 'below_min':
				return sprintf( __( 'The amount must be at least %s.', 'easy-form-builder' ), isset( $extra['amount'] ) ? $extra['amount'] : '' );
			case 'above_max':
				return sprintf( __( 'The amount must be at most %s.', 'easy-form-builder' ), isset( $extra['amount'] ) ? $extra['amount'] : '' );
			case 'pwyw_out_of_range':
				return __( 'Please choose a valid amount.', 'easy-form-builder' );
			case 'no_amount':
				return __( 'There is nothing to pay yet. Please choose an amount.', 'easy-form-builder' );
			case 'invalid_selection':
			case 'price_not_computable':
				return __( 'Invalid payment selection. Please review your choices and try again. If the problem persists, contact the site administrator.', 'easy-form-builder' );
			case 'gateway_error':
				return __( 'The payment could not be started. You have not been charged. Please try again in a moment.', 'easy-form-builder' );
			case 'attempt_used':
				return __( 'This payment attempt has already been used. Please reload the page and try again.', 'easy-form-builder' );
			case 'in_progress':
				return __( 'Your payment is already being prepared. Please wait a moment.', 'easy-form-builder' );
			case 'method_below_min':
				/* translators: %1$s: payment method, %2$s: amount with currency */
				return sprintf( __( '%1$s accepts amounts from %2$s. Please choose another payment method.', 'easy-form-builder' ), isset( $extra['method'] ) ? $extra['method'] : '', isset( $extra['amount'] ) ? $extra['amount'] : '' );
			case 'method_above_max':
				/* translators: %1$s: payment method, %2$s: amount with currency */
				return sprintf( __( '%1$s accepts amounts up to %2$s. Please choose another payment method.', 'easy-form-builder' ), isset( $extra['method'] ) ? $extra['method'] : '', isset( $extra['amount'] ) ? $extra['amount'] : '' );
			case 'gateway_declined':
				return isset( $extra['visitor'] ) && $extra['visitor'] !== '' ? $extra['visitor'] : __( 'The payment could not be started. You have not been charged. Please try again in a moment.', 'easy-form-builder' );
			case 'retry_unavailable':
				return __( 'This payment can no longer be started again here. Please reload the page and send the form again.', 'easy-form-builder' );
			case 'pay_not_ready':
				return Readiness::visitor_text();
			case 'currency_unsupported':
			case 'pay_unavailable':
			default:
				return __( 'This form cannot take payments at the moment. Please contact the site owner.', 'easy-form-builder' );
		}
	}

	private static function fail( $code, $extra = array() ) {
		return array_merge( array( 'success' => false, 'code' => $code, 'm' => self::message( $code, $extra ) ), isset( $extra['field_id'] ) ? array( 'field_id' => $extra['field_id'] ) : array() );
	}

	/** The stored form (associative rows) or null. */
	public static function form_rows( $form_id ) {
		global $wpdb;
		$t = $wpdb->prefix . 'emsfb_form';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM `{$t}` WHERE form_id = %d", (int) $form_id ) );
		if ( ! is_string( $raw ) || $raw === '' ) return null;
		$json = function_exists( 'emsfb_json_normalize_efb' ) ? emsfb_json_normalize_efb( $raw ) : str_replace( '\\"', '"', $raw );
		$rows = function_exists( 'emsfb_json_decode_efb' ) ? emsfb_json_decode_efb( $json, true ) : json_decode( $json, true );
		return is_array( $rows ) ? $rows : null;
	}

	/** Public part of a create answer (never anything secret: Gateway::create() puts only public data in next). */
	private static function public_next( $next ) {
		$out = array( 'type' => 'none' );
		if ( ! is_array( $next ) ) return $out;
		if ( isset( $next['type'] ) && in_array( $next['type'], array( 'redirect', 'embedded', 'qr', 'none' ), true ) ) $out['type'] = $next['type'];
		if ( isset( $next['url'] ) && is_string( $next['url'] ) && preg_match( '#^https://#i', $next['url'] ) ) $out['url'] = $next['url'];
		if ( isset( $next['url'] ) && is_string( $next['url'] ) && strpos( $next['url'], rest_url() ) === 0 ) $out['url'] = $next['url'];
		if ( isset( $next['client'] ) && is_array( $next['client'] ) ) $out['client'] = $next['client'];
		if ( isset( $next['expires_at'] ) && is_scalar( $next['expires_at'] ) ) $out['expires_at'] = (string) $next['expires_at'];
		return $out;
	}

	private static function pay_answer( array $p, array $next ) {
		$test = $p['mode'] !== 'live';
		return array(
			'success' => true,
			'pay'     => array(
				'uid'         => $p['uid'],
				'sig'         => self::sign( $p['uid'], $p['gateway'] ),
				'gateway'     => $p['gateway'],
				'status'      => $p['status'],
				'next'        => self::public_next( $next ),
				'test_mode'   => $test,
				'total_minor' => (int) $p['amount_minor'],
				'currency'    => $p['currency'],
				'amount_text' => Money::format( (int) $p['amount_minor'], $p['currency'], $p['locale'] ),
				'poll_until'  => self::poll_until( $p ),
			),
		);
	}

	/* ------------------------------------------------------------------ create */

	/**
	 * Called by the form submit handler once every row passed the normal validation.
	 *
	 * @param array $a form_id, form (stored rows), rows (validated rows), field_id, attempt_key, method,
	 *                 expected_minor (what the visitor saw, or null), page_url, locale, ip (as stored),
	 *                 payer_email, store_entry (callable(array $rows): array{track,msg_id}|null),
	 *                 description
	 * @return array Answer for the browser.
	 */
	public static function create( array $a ) {
		$form = $a['form'];
		$fid = (string) $a['field_id'];
		$field = null;
		foreach ( $form as $i => $r ) {
			if ( $i > 0 && is_array( $r ) && isset( $r['id_'] ) && (string) $r['id_'] === $fid ) { $field = $r; break; }
		}
		// PAY00 readiness: a form that can never take this payment as it is now (keys, add-on, plan,
		// currency, payer fields, …) is refused before anything is stored, whatever the browser sent.
		$ready = Readiness::of_form( $form, isset( $a['form_id'] ) ? (int) $a['form_id'] : 0 );
		if ( $ready['has_payment'] && ! $ready['ready'] ) return self::fail( 'pay_not_ready', array( 'issues' => array_column( $ready['issues'], 'code' ) ) );
		$gw = $field ? Registry::get( isset( $field['type'] ) ? (string) $field['type'] : '' ) : null;
		if ( ! $gw ) return self::fail( 'pay_unavailable' );
		self::$gw_slug = $gw->slug();
		if ( ! self::plan_ok() ) return self::fail( 'pay_unavailable' );
		if ( ! Store::ready() ) return self::fail( 'pay_unavailable' );
		$slug = $gw->slug();
		$settings = Settings::load( $slug );
		$mode = $settings->mode();
		if ( ! $gw->is_configured( $settings, $mode ) ) return self::fail( 'pay_unavailable' );
		$form_id = (int) $a['form_id'];
		$attempt = isset( $a['attempt_key'] ) ? (string) $a['attempt_key'] : '';
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $attempt ) ) return self::fail( 'attempt_used' );

		$prev = Store::find_by_attempt( $form_id, $attempt );
		if ( $prev ) return self::replay( $prev );

		$sup = array_merge( ( new Gateway_Defaults() )->supports(), (array) $gw->supports() );
		$quote = Pricing::quote( $form, $a['rows'], array( 'field_id' => $fid, 'gateway' => $slug, 'supports' => $sup ) );
		if ( empty( $quote['ok'] ) ) {
			$extra = isset( $quote['field_id'] ) ? array( 'field_id' => $quote['field_id'] ) : array();
			if ( isset( $quote['limit_minor'] ) ) $extra['amount'] = Money::format( $quote['limit_minor'], $quote['currency'], isset( $a['locale'] ) ? $a['locale'] : '' );
			return self::fail( $quote['error'], $extra );
		}
		$locale = isset( $a['locale'] ) && is_string( $a['locale'] ) && preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $a['locale'] ) ? $a['locale'] : ( function_exists( 'determine_locale' ) ? determine_locale() : '' );
		$method = isset( $a['method'] ) && is_string( $a['method'] ) && preg_match( '/^[a-z0-9_]{1,30}$/', $a['method'] ) ? $a['method'] : '';
		$limit = self::method_limit( $gw, $settings, $method, (int) $quote['total_minor'], $quote['currency'], $locale );
		if ( $limit ) return $limit;
		if ( isset( $a['expected_minor'] ) && $a['expected_minor'] !== null && $a['expected_minor'] !== '' && (int) $a['expected_minor'] !== (int) $quote['total_minor'] ) {
			$r = self::fail( 'quote_changed', array( 'amount' => Money::format( $quote['total_minor'], $quote['currency'], $locale ) ) );
			$r['total_minor'] = $quote['total_minor'];
			$r['currency'] = $quote['currency'];
			return $r;
		}

		$uid = Store::new_uid();
		$form0 = is_array( $form[0] ) ? $form[0] : array();
		$pay_row = array(
			'id_'             => 'payment',
			'name'            => 'payment',
			'amount'          => 0,
			'type'            => 'payment',
			'paymentGateway'  => $slug,
			'paymentIntent'   => '',
			'paymentAmount'   => Money::to_decimal_string( $quote['total_minor'], $quote['currency'] ),
			'paymentcurrency' => $quote['currency'],
			'paymentmethod'   => isset( $form0['paymentmethod'] ) && is_string( $form0['paymentmethod'] ) ? $form0['paymentmethod'] : 'charge',
			'paymentCreated'  => function_exists( 'wp_date' ) ? wp_date( 'Y/m/d H:i' ) : gmdate( 'Y/m/d H:i' ),
			'status'          => 'pending',
			'mode'            => $mode,
			'pay_uid'         => $uid,
		);
		// The payer values the gateway needs (empty or in the wrong format): a field-level answer
		// before anything is stored, so a visitor who corrects the field leaves no half entry behind.
		$plain_rows = array();
		foreach ( $a['rows'] as $r ) {
			if ( is_array( $r ) && ! in_array( $r['type'] ?? '', array( 'w_link', 'payment' ), true ) ) $plain_rows[] = $r;
		}
		try { $schema = $gw->field_schema(); } catch ( \Throwable $e ) { $schema = array(); }
		$missing = self::payer_check( $gw, $settings, $form, $field, $schema, $plain_rows, $method, isset( $a['payer_email'] ) ? (string) $a['payer_email'] : '' );
		if ( $missing ) return $missing;

		$rows = array();
		foreach ( $a['rows'] as $r ) {
			if ( is_array( $r ) && ( $r['type'] ?? '' ) === 'w_link' ) continue;
			$rows[] = $r;
		}
		$rows[] = $pay_row;
		foreach ( $a['rows'] as $r ) {
			if ( is_array( $r ) && ( $r['type'] ?? '' ) === 'w_link' ) $rows[] = $r;
		}
		$entry = call_user_func( $a['store_entry'], $rows );
		if ( ! is_array( $entry ) || empty( $entry['track'] ) ) return self::fail( 'gateway_error' );

		// The checkout context core builds for the gateway: the field's options, the payer from the
		// mapped fields, the submitted rows (read only), the browser adapter's token (never stored).
		$options = Field::gateway_options( $field, $schema );
		$payer = Field::payer( $plain_rows, $options, isset( $a['payer_email'] ) ? (string) $a['payer_email'] : '', $form );

		$return_to = '';
		if ( ! empty( $a['page_url'] ) && is_string( $a['page_url'] ) ) {
			$u = remove_query_arg( array( 'efb_pay', 'efb_ps' ), $a['page_url'] );
			$return_to = (string) wp_validate_redirect( $u, '' );
		}
		$p = Store::insert( array(
			'uid'          => $uid,
			'form_id'      => $form_id,
			'field_id'     => $fid,
			'msg_id'       => isset( $entry['msg_id'] ) ? (int) $entry['msg_id'] : 0,
			'track'        => (string) $entry['track'],
			'gateway'      => $slug,
			'mode'         => $mode,
			'amount_minor' => (int) $quote['total_minor'],
			'currency'     => $quote['currency'],
			'quote'        => wp_json_encode_efb_pay( $quote ),
			'attempt_key'  => $attempt,
			'payer_email'  => substr( $payer['email'], 0, 190 ),
			'payer_name'   => substr( $payer['name'], 0, 190 ),
			'return_to'    => substr( $return_to, 0, 500 ),
			'locale'       => $locale,
			'ip'           => isset( $a['ip'] ) ? substr( (string) $a['ip'], 0, 45 ) : '',
		) );
		if ( ! $p ) {
			$prev = Store::find_by_attempt( $form_id, $attempt );
			return $prev ? self::replay( $prev ) : self::fail( 'gateway_error' );
		}
		Store::log_event( $p['id'], $slug, 'api', array( 'type' => 'create', 'to_status' => 'created', 'note' => 'quote ' . $quote['hash'] ) );

		// The description is the stored form's name (server truth); the browser's copy only when it has none.
		$desc = isset( $form0['formName'] ) && is_string( $form0['formName'] ) && trim( $form0['formName'] ) !== '' ? $form0['formName'] : ( isset( $a['description'] ) ? (string) $a['description'] : '' );
		$ctx = new Checkout_Context( array(
			'description'  => substr( Field::text( str_replace( '@efb@dq#', '"', $desc ), 120 ), 0, 360 ),
			'rows'         => $plain_rows,
			'field'        => $options,
			'payer'        => $payer,
		) );
		return self::start( $gw, $settings, $p, $ctx, $method, $locale, $a, $quote );
	}

	/**
	 * Hands a stored payment to Gateway::create() and records the answer (create and retry).
	 * $ctx already holds rows, field and payer; the addresses, method and token are added here.
	 */
	private static function start( Gateway $gw, Settings $settings, array $p, Checkout_Context $ctx, $method, $locale, array $a, array $quote ) {
		$slug = $gw->slug();
		$ctx->return_url = self::return_url( $p );
		$ctx->cancel_url = add_query_arg( 'c', '1', self::return_url( $p ) );
		$ctx->webhook_url = self::webhook_url( $slug );
		$ctx->locale = $locale;
		$ctx->method = $method;
		$ctx->payer_email = $p['payer_email'];
		$ctx->metadata = array( 'efb_uid' => $p['uid'], 'form_id' => (int) $p['form_id'], 'track' => $p['track'] );
		$ctx->client_token = self::client_token( isset( $a['client_token'] ) ? $a['client_token'] : '' );
		$ctx->client_data = self::client_data( isset( $a['client_data'] ) ? $a['client_data'] : array() );
		if ( function_exists( 'apply_filters' ) ) {
			$f = apply_filters( 'efb_payment_create_args', $ctx, $p, $quote );
			if ( $f instanceof Checkout_Context ) $ctx = $f;
		}
		try {
			$res = $gw->create( new Payment( $p ), $settings, $ctx );
		} catch ( \Throwable $e ) {
			$res = Create_Result::fail( 'exception', Redactor::redact_string( $e->getMessage() ) );
		}
		$ctx->client_token = ''; // never kept past the call
		if ( ! $res instanceof Create_Result || ! $res->ok || $res->gateway_ref === '' ) {
			$err = $res instanceof Result ? $res->error . ' ' . $res->message : 'bad_result';
			Store::transition( $p['id'], 'failed' );
			Store::log_event( $p['id'], $slug, 'api', array( 'type' => 'create_failed', 'from_status' => 'created', 'to_status' => 'failed', 'note' => Redactor::redact_string( $err ) ) );
			return self::create_failure( $gw, $settings, $p, $res, $ctx );
		}
		self::clear_owner_alert( $slug, $p['mode'] );
		$next = self::public_next( $res->next );
		Store::transition( $p['id'], 'pending', array(
			'gateway_ref' => substr( $res->gateway_ref, 0, 100 ),
			'method'      => substr( (string) ( $res->method !== '' ? $res->method : $ctx->method ), 0, 40 ),
			'expires_at'  => $res->expires_at ? gmdate( 'Y-m-d H:i:s', is_numeric( $res->expires_at ) ? (int) $res->expires_at : strtotime( $res->expires_at ) ) : null,
			'next_json'   => wp_json_encode_efb_pay( $next ),
		) );
		Store::log_event( $p['id'], $slug, 'api', array( 'type' => 'created', 'from_status' => 'created', 'to_status' => 'pending', 'note' => 'ref ' . $res->gateway_ref ) );
		$p = Store::get( $p['id'] );
		// A provider that says "paid" already is checked against its API, never taken from create().
		if ( in_array( $res->status, array( 'paid', 'authorized' ), true ) ) {
			self::refresh( $p, 'api' );
			$p = Store::get( $p['id'] );
		}
		if ( function_exists( 'wp_schedule_single_event' ) ) wp_schedule_single_event( Store::time() + 600, 'emsfb_pmt_reconcile' );
		$out = self::pay_answer( $p, $next );
		$out['track'] = $p['track'];
		$out['msg_id'] = (int) $p['msg_id'];
		return $out;
	}

	/**
	 * The visitor's answer for a create() that did not start a payment: the gateway's own visitor
	 * text, else the reason (Result::because(), or Reasons::of_error() of its error code) as a safe
	 * message; a provider that refused a payer value names its option → a field-level payer_invalid.
	 * A refusal of the keys / mode / account setup (reason config) also alerts the owner.
	 */
	private static function create_failure( Gateway $gw, Settings $settings, array $p, $res, Checkout_Context $ctx ) {
		$err = $res instanceof Result ? (string) $res->error : 'bad_result';
		$reason = $res instanceof Result ? Reasons::clean( $res->reason ) : '';
		if ( $reason === '' ) $reason = Reasons::of_error( $err );
		if ( $reason === 'config' ) self::owner_alert( $gw, $p['mode'], $err, $res instanceof Result ? Field::text( (string) $res->message, 300 ) : '' );
		$visitor = $res instanceof Result ? Field::text( (string) $res->visitor_message, 200 ) : '';
		if ( $reason === 'payer_invalid' && $res instanceof Result && preg_match( '/^pgw_[a-z0-9_]{1,40}$/', (string) $res->payer_key ) ) {
			$fid = Field::field_id( isset( $ctx->field[ $res->payer_key ] ) ? $ctx->field[ $res->payer_key ] : '' );
			$form = self::form_rows( $p['form_id'] );
			if ( $fid === '' && $res->payer_key === 'pgw_map_email' && is_array( $form ) ) $fid = Field::confirmation_email_field( $form );
			$d = null;
			try { foreach ( Field::clean_schema( $gw->field_schema() ) as $e ) { if ( $e['key'] === $res->payer_key ) $d = $e; } } catch ( \Throwable $e ) { $d = null; }
			if ( $fid !== '' ) {
				$label = self::field_name( is_array( $form ) ? $form : array(), $fid, $d ? $d['label'] : '' );
				return self::fail( 'payer_invalid', array( 'gateway' => $gw->slug(), 'field_id' => $fid, 'label' => $label, 'format' => $d && $d['format'] !== '' ? $d['format'] : 'value', 'visitor' => $visitor !== '' ? $visitor : ( $d ? $d['invalid_message'] : '' ) ) );
			}
		}
		if ( $visitor !== '' ) return self::fail( 'gateway_declined', array( 'visitor' => $visitor, 'reason' => $reason ) );
		if ( $reason === 'config' || $reason === 'payer_invalid' ) return self::fail( 'pay_unavailable' );
		if ( $reason !== '' ) return self::fail( $reason );
		return self::fail( 'gateway_error' );
	}

	/**
	 * The owner is told that a visitor could not pay because of the gateway's setup (keys refused,
	 * wrong mode, an account that does not accept the currency …): a notice on the gateway's settings
	 * card and, at most once a day per gateway, an e-mail. Cleared by the next payment that starts.
	 */
	public static function owner_alert( Gateway $gw, $mode, $error, $message ) {
		$slug = $gw->slug();
		$a = array( 'at' => Store::time(), 'mode' => $mode === 'live' ? 'live' : 'test', 'error' => substr( preg_replace( '/[^a-z0-9_.-]/i', '', (string) $error ), 0, 60 ), 'm' => Field::text( Redactor::redact_string( (string) $message ), 300 ) );
		update_option( 'emsfb_pmt_alert_' . $slug, $a, false );
		Store::log_event( null, $slug, 'api', array( 'type' => 'config_failed', 'note' => $a['mode'] . ' ' . $a['error'] ) );
		if ( function_exists( 'wp_mail' ) && ! get_transient( 'emsfb_pmt_alert_mail_' . $slug ) ) {
			set_transient( 'emsfb_pmt_alert_mail_' . $slug, 1, DAY_IN_SECONDS );
			$site = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : '';
			/* translators: %s: gateway name */
			$subject = sprintf( __( 'Visitors cannot pay with %s', 'easy-form-builder' ), $gw->label() ) . ' [' . $site . ']';
			wp_mail( get_option( 'admin_email' ), $subject, self::owner_alert_text( $gw, $a ) . "\n\n" . ( function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=Emsfb_payments&tab=gateways' ) : '' ) . "\n" );
		}
	}

	/** The owner's words for a stored alert (settings card, e-mail). */
	public static function owner_alert_text( Gateway $gw, array $a ) {
		$when = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', (int) $a['at'] ) : gmdate( 'Y-m-d H:i', (int) $a['at'] );
		/* translators: %1$s: date and time, %2$s: gateway name, %3$s: Live or Test, %4$s: error code */
		$t = sprintf( __( 'On %1$s a visitor could not pay because %2$s refused the payment set-up of %3$s mode (%4$s). Check the keys and the account (currencies, payment methods), then press Test connection.', 'easy-form-builder' ), $when, $gw->label(), $a['mode'] === 'live' ? 'Live' : 'Test', $a['error'] !== '' ? $a['error'] : '-' );
		return $a['m'] !== '' ? $t . ' ' . $a['m'] : $t;
	}

	public static function clear_owner_alert( $slug, $mode ) {
		$a = function_exists( 'get_option' ) ? get_option( 'emsfb_pmt_alert_' . $slug ) : null;
		if ( is_array( $a ) && isset( $a['mode'] ) && $a['mode'] === ( $mode === 'live' ? 'live' : 'test' ) ) delete_option( 'emsfb_pmt_alert_' . $slug );
	}

	/**
	 * The payer values the gateway needs (field_schema: required / required_for the chosen method,
	 * with their format and countries): an empty one → payer_missing, one in the wrong format →
	 * payer_invalid, each with the
	 * field_id so the page shows it at the field, instead of the provider's generic refusal. null when
	 * everything fits. An option that is not mapped to a field is not checked (the owner is warned at
	 * Save); the payer e-mail falls back to the form's confirmation e-mail field like Field::payer().
	 */
	private static function payer_check( Gateway $gw, Settings $s, array $form, array $field, $schema, array $rows, $method, $conf_email = '' ) {
		try { $list = Registry::normalize_methods( $gw->methods( $s ) ); } catch ( \Throwable $e ) { $list = array(); }
		$offered = Field::offered_methods( $field, array( 'methods' => $list ) );
		$chosen = $method !== '' ? array( $method ) : ( is_array( $offered ) && count( $offered ) === 1 ? $offered : array() );
		$labels = array();
		foreach ( $list as $m ) $labels[ $m[0] ] = $m[1];
		$conf = Field::confirmation_email_field( $form );
		foreach ( Field::clean_schema( $schema ) as $d ) {
			if ( $d['type'] !== 'field' ) continue;
			$need = $d['required'] && ! $d['required_for'];
			$for = '';
			if ( ! $need && $d['required_for'] && $chosen && array_intersect( $chosen, $d['required_for'] ) ) { $need = true; $for = isset( $labels[ $chosen[0] ] ) ? $labels[ $chosen[0] ] : $chosen[0]; }
			$fid = Field::field_id( isset( $field[ $d['key'] ] ) ? $field[ $d['key'] ] : '' );
			if ( $fid === '' && $d['key'] === 'pgw_map_email' ) $fid = $conf;
			// Only what this payment needs is checked (a value the chosen method does not need is passed on as typed).
			if ( $fid === '' || ! $need ) continue;
			$val = '';
			foreach ( $rows as $r ) {
				if ( is_array( $r ) && isset( $r['id_'] ) && (string) $r['id_'] === $fid && isset( $r['value'] ) && is_scalar( $r['value'] ) ) { $val = trim( str_replace( '@efb@dq#', '"', (string) $r['value'] ) ); break; }
			}
			$name = self::field_name( $form, $fid, $d['label'] );
			if ( $val === '' ) return self::fail( 'payer_missing', array( 'gateway' => $gw->slug(), 'field_id' => $fid, 'label' => $name, 'method' => $for, 'visitor' => $d['visitor_message'] ) );
			$problem = Field::payer_problem( $d, $val, $d['format'] === 'country' ? Field::country_iso( $form, $fid, $val ) : '' );
			if ( $problem !== '' ) return self::fail( 'payer_invalid', array( 'gateway' => $gw->slug(), 'field_id' => $fid, 'label' => $name, 'format' => $problem === 'country' ? 'country_not_allowed' : $d['format'], 'method' => $for, 'visitor' => $d['invalid_message'] ) );
		}
		return null;
	}

	/** The form's own name of a field ($fallback when it has none). */
	private static function field_name( array $form, $fid, $fallback ) {
		foreach ( $form as $i => $f ) {
			if ( $i > 0 && is_array( $f ) && isset( $f['id_'], $f['name'] ) && (string) $f['id_'] === (string) $fid && is_string( $f['name'] ) && $f['name'] !== '' ) return Field::text( str_replace( '@efb@dq#', '"', $f['name'] ), 80 );
		}
		return $fallback;
	}

	/** The browser adapter's opaque token: printable ASCII, at most 4096 characters, else ''. */
	public static function client_token( $t ) {
		return is_string( $t ) && strlen( $t ) <= 4096 && preg_match( '/^[\x21-\x7E]{1,4096}$/', $t ) ? $t : '';
	}

	/** The adapter's small public data: flat, keys [a-z0-9_], scalar values of at most 200 characters, 2 KB in all. */
	public static function client_data( $d ) {
		$out = array();
		if ( ! is_array( $d ) ) return $out;
		foreach ( $d as $k => $v ) {
			if ( count( $out ) >= 20 || ! is_string( $k ) || ! preg_match( '/^[a-z0-9_]{1,40}$/', $k ) ) continue;
			if ( is_bool( $v ) || is_int( $v ) ) $out[ $k ] = $v;
			elseif ( is_string( $v ) ) $out[ $k ] = Field::text( $v, 200 );
		}
		$j = wp_json_encode_efb_pay( $out );
		return strlen( $j ) <= 2048 ? $out : array();
	}

	/**
	 * The chosen method's own limits (Gateway::methods() extras min_minor / max_minor): a specific
	 * answer instead of the provider's generic refusal. null when the amount fits or there is no limit.
	 */
	private static function method_limit( Gateway $gw, Settings $s, $method, $total, $currency, $locale ) {
		if ( $method === '' ) return null;
		try { $list = Registry::normalize_methods( $gw->methods_for( $s, array( 'currency' => $currency, 'amount_minor' => $total ) ), '' ); } catch ( \Throwable $e ) { return null; }
		foreach ( $list as $m ) {
			if ( $m[0] !== $method || ! isset( $m[2] ) ) continue;
			$min = isset( $m[2]['min_minor'][ $currency ] ) ? (int) $m[2]['min_minor'][ $currency ] : null;
			$max = isset( $m[2]['max_minor'][ $currency ] ) ? (int) $m[2]['max_minor'][ $currency ] : null;
			if ( $min !== null && $total < $min ) return self::fail( 'method_below_min', array( 'method' => Field::text( $m[1], 60 ), 'amount' => Money::format( $min, $currency, $locale ) ) );
			if ( $max !== null && $total > $max ) return self::fail( 'method_above_max', array( 'method' => Field::text( $m[1], 60 ), 'amount' => Money::format( $max, $currency, $locale ) ) );
		}
		return null;
	}

	/* ------------------------------------------------------------------ retry */

	/**
	 * "Try again" / "Get a new QR code": a new attempt for the SAME pending entry, without sending
	 * the form again. The amount, currency and quote are the stored ones (the server's), the rows
	 * come from the stored entry; a new payment row with its own attempt key (a repeated key gives
	 * the first answer again), the entry's payment row points to it, and an older attempt that is
	 * still pending is canceled at the provider when it can be (if it is paid after all, finalize
	 * takes the entry back; two paid attempts are reported to the owner, never finalized twice).
	 *
	 * @param array $a uid, sig (of the previous attempt), attempt_key, method, client_token, client_data, locale
	 */
	public static function retry( array $a ) {
		$uid = isset( $a['uid'] ) ? (string) $a['uid'] : '';
		$prev = Store::ready() ? Store::find_by_uid( $uid ) : null;
		if ( ! $prev || ! self::verify_sig( $uid, $prev['gateway'], isset( $a['sig'] ) ? (string) $a['sig'] : '' ) ) return self::fail( 'retry_unavailable' );
		$attempt = isset( $a['attempt_key'] ) ? (string) $a['attempt_key'] : '';
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $attempt ) ) return self::fail( 'attempt_used' );
		$again = Store::find_by_attempt( (int) $prev['form_id'], $attempt );
		if ( $again ) return self::replay( $again );
		if ( ! in_array( $prev['status'], array( 'created', 'pending', 'failed', 'canceled', 'expired' ), true ) || ! empty( $prev['finalized_at'] ) || empty( $prev['msg_id'] ) ) return self::fail( 'retry_unavailable' );
		if ( Store::finalized_sibling( (int) $prev['msg_id'], 0 ) || Store::count_by_msg( (int) $prev['msg_id'] ) >= 10 ) return self::fail( 'retry_unavailable' );
		$gw = Registry::get( $prev['gateway'] );
		if ( ! $gw || ! self::plan_ok() ) return self::fail( 'pay_unavailable' );
		self::$gw_slug = $gw->slug();
		$settings = Settings::load( $prev['gateway'] );
		if ( ! $gw->is_configured( $settings, $settings->mode() ) ) return self::fail( 'pay_unavailable' );
		$form = self::form_rows( $prev['form_id'] );
		$field = null;
		foreach ( is_array( $form ) ? $form : array() as $i => $r ) {
			if ( $i > 0 && is_array( $r ) && isset( $r['id_'] ) && (string) $r['id_'] === (string) $prev['field_id'] && ( $r['type'] ?? '' ) === $prev['gateway'] ) { $field = $r; break; }
		}
		$entry = self::entry_of( $prev );
		if ( ! $field || ! $entry || (string) $entry['read_'] !== '2' ) return self::fail( 'retry_unavailable' );
		$locale = isset( $a['locale'] ) && is_string( $a['locale'] ) && preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $a['locale'] ) ? $a['locale'] : (string) $prev['locale'];
		$method = isset( $a['method'] ) && is_string( $a['method'] ) && preg_match( '/^[a-z0-9_]{1,30}$/', $a['method'] ) ? $a['method'] : '';
		$limit = self::method_limit( $gw, $settings, $method, (int) $prev['amount_minor'], $prev['currency'], $locale );
		if ( $limit ) return $limit;

		$p = Store::insert( array(
			'uid'          => Store::new_uid(),
			'form_id'      => (int) $prev['form_id'],
			'field_id'     => (string) $prev['field_id'],
			'msg_id'       => (int) $prev['msg_id'],
			'track'        => (string) $prev['track'],
			'gateway'      => (string) $prev['gateway'],
			'mode'         => $settings->mode(),
			'amount_minor' => (int) $prev['amount_minor'],
			'currency'     => (string) $prev['currency'],
			'quote'        => (string) $prev['quote'],
			'attempt_key'  => $attempt,
			'payer_email'  => (string) $prev['payer_email'],
			'payer_name'   => (string) $prev['payer_name'],
			'return_to'    => (string) $prev['return_to'],
			'locale'       => $locale,
			'ip'           => (string) $prev['ip'],
		) );
		if ( ! $p ) {
			$again = Store::find_by_attempt( (int) $prev['form_id'], $attempt );
			return $again ? self::replay( $again ) : self::fail( 'gateway_error' );
		}
		Store::log_event( $p['id'], $p['gateway'], 'api', array( 'type' => 'create', 'to_status' => 'created', 'note' => 'retry of ' . $prev['uid'] ) );
		if ( in_array( $prev['status'], array( 'created', 'pending' ), true ) ) {
			try { $gw->cancel( new Payment( $prev ), $settings ); } catch ( \Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement -- best effort; a late payment is still taken
			self::move( $prev, 'canceled', array(), 'api', 'replaced by ' . $p['uid'] );
		}
		self::update_entry( $p, array( 'pay_uid' => $p['uid'], 'status' => 'pending', 'mode' => $p['mode'] ), false, true );
		$rows = array();
		foreach ( self::decode_content( $entry['content'] ) ?: array() as $r ) {
			if ( is_array( $r ) && ! in_array( $r['type'] ?? '', array( 'w_link', 'payment' ), true ) ) $rows[] = $r;
		}
		try { $schema = $gw->field_schema(); } catch ( \Throwable $e ) { $schema = array(); }
		$options = Field::gateway_options( $field, $schema );
		$ctx = new Checkout_Context( array(
			'description' => isset( $form[0]['formName'] ) && is_string( $form[0]['formName'] ) ? Field::text( str_replace( '@efb@dq#', '"', $form[0]['formName'] ), 120 ) : '',
			'rows'        => $rows,
			'field'       => $options,
			'payer'       => Field::payer( $rows, $options, (string) $prev['payer_email'] ),
		) );
		$quote = json_decode( (string) $prev['quote'], true );
		$out = self::start( $gw, $settings, $p, $ctx, $method, $locale, $a, is_array( $quote ) ? $quote : array() );
		unset( $out['track'], $out['msg_id'] );
		return $out;
	}

	/** The entry row (content, read_) of a payment, or null. */
	private static function entry_of( array $p ) {
		global $wpdb;
		if ( empty( $p['msg_id'] ) ) return null;
		$t = $wpdb->prefix . 'emsfb_msg_';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$e = $wpdb->get_row( $wpdb->prepare( "SELECT content, read_ FROM `{$t}` WHERE msg_id = %d", (int) $p['msg_id'] ), ARRAY_A );
		return $e ? $e : null;
	}

	/** Per-form, per-minute counter (with rate_ok() per IP: the card-testing guard of create and retry). */
	public static function form_rate_ok( $form_id, $limit ) {
		$key = 'efb_pmt_rl_pcf' . (int) $form_id . '_' . gmdate( 'YmdHi', Store::time() );
		$n = (int) get_transient( $key );
		if ( $n >= (int) $limit ) return false;
		set_transient( $key, $n + 1, 70 );
		return true;
	}

	/** POST payments/retry {uid, sig, attempt_key, method, client_token, client_data, locale} (nonce; same limits as create). */
	public static function handle_retry( \WP_REST_Request $r ) {
		$j = $r->get_json_params();
		$j = is_array( $j ) ? $j : array();
		$sc = function ( $k ) use ( $j ) { return isset( $j[ $k ] ) && is_scalar( $j[ $k ] ) ? (string) $j[ $k ] : ''; };
		$prev = Store::ready() ? Store::find_by_uid( $sc( 'uid' ) ) : null;
		$per_ip = function_exists( 'apply_filters' ) ? (int) apply_filters( 'efb_payment_create_per_minute', 10 ) : 10;
		if ( ! self::rate_ok( 'pc', $per_ip ) || ! self::form_rate_ok( $prev ? (int) $prev['form_id'] : 0, 30 ) ) {
			return self::rest( array( 'success' => true, 'data' => array( 'success' => false, 'code' => 'efb_rate_limited', 'm' => self::message( 'rate_limited' ) ) ), 429, true );
		}
		$out = self::retry( array(
			'uid'          => $sc( 'uid' ),
			'sig'          => $sc( 'sig' ),
			'attempt_key'  => strtolower( $sc( 'attempt_key' ) ),
			'method'       => strtolower( $sc( 'method' ) ),
			'locale'       => $sc( 'locale' ),
			'client_token' => $sc( 'client_token' ),
			'client_data'  => isset( $j['client_data'] ) && is_array( $j['client_data'] ) ? $j['client_data'] : array(),
		) );
		return self::rest( array( 'success' => true, 'data' => $out ), 200, true );
	}

	/** A repeated attempt (double click, lost answer): the first answer again. */
	private static function replay( array $p ) {
		if ( in_array( $p['status'], array( 'pending', 'authorized', 'paid' ), true ) ) {
			$next = is_string( $p['next_json'] ) ? json_decode( $p['next_json'], true ) : array();
			$r = self::pay_answer( $p, is_array( $next ) ? $next : array() );
			$r['replay'] = true;
			return $r;
		}
		return self::fail( $p['status'] === 'created' ? 'in_progress' : 'attempt_used' );
	}

	/* ------------------------------------------------------------------ state */

	/**
	 * Applies the provider's state to a payment (one conditional UPDATE per step) and finalizes it
	 * when it became paid. Never lowers paid; checks amount and currency before paid.
	 *
	 * @return string The status after the call.
	 */
	public static function apply_remote_state( array $p, $st, $source ) {
		if ( ! $st instanceof Remote_State || ! $st->ok || ! in_array( $st->status, Store::STATUSES, true ) ) return $p['status'];
		$to = $st->status;
		$from = $p['status'];
		$open = in_array( $from, array( 'created', 'pending', 'authorized' ), true );
		// The provider refunded part of a payment EFB never saw paid: it was paid first (finalize
		// runs once, the entry is completed), then the partial refund is applied.
		if ( $to === 'partially_refunded' && $open ) {
			$paid = Remote_State::of( 'paid', $st->amount_minor, $st->currency, array( 'paid_at' => $st->paid_at, 'method' => $st->method ) );
			self::apply_remote_state( $p, $paid, $source );
			$p = Store::get( $p['id'] );
			$from = $p['status'];
			$open = false;
		}
		if ( in_array( $to, array( 'paid', 'authorized' ), true ) || ( $to === 'refunded' && $open ) ) {
			$cur_ok = $st->currency === '' || strtoupper( $st->currency ) === strtoupper( $p['currency'] );
			$amt_ok = $st->amount_minor === null || (int) $st->amount_minor >= (int) $p['amount_minor'];
			if ( ! $cur_ok || ! $amt_ok ) {
				$note = 'amount_mismatch: provider ' . ( $st->amount_minor === null ? '?' : (int) $st->amount_minor ) . ' ' . $st->currency . ', expected ' . (int) $p['amount_minor'] . ' ' . $p['currency'];
				self::move( $p, 'failed', array(), $source, $note );
				self::mail_admin( 'mismatch', Store::get( $p['id'] ), $note );
				return Store::get( $p['id'] )['status'];
			}
		}
		$fields = array();
		if ( $to === 'paid' ) {
			$fields['paid_at'] = $st->paid_at ? gmdate( 'Y-m-d H:i:s', is_numeric( $st->paid_at ) ? (int) $st->paid_at : strtotime( (string) $st->paid_at ) ) : Store::now();
		}
		if ( $st->method !== '' ) $fields['method'] = substr( (string) $st->method, 0, 40 );
		if ( in_array( $to, array( 'refunded', 'partially_refunded' ), true ) ) {
			$fields['refunded_minor'] = $st->refunded_minor ? min( (int) $p['amount_minor'], (int) $st->refunded_minor ) : ( $to === 'refunded' ? (int) $p['amount_minor'] : (int) $p['refunded_minor'] );
		}
		if ( $to !== $from ) {
			$moved = self::move( $p, $to, $fields, $source, ( $to === 'refunded' && $open ) ? 'refunded at the provider before EFB saw it paid' : '' );
			if ( $moved && $to === 'refunded' && $open ) {
				// Completed (no longer "pending payment") but never finalized: the payment was taken back.
				self::update_entry( Store::get( $p['id'] ), array( 'status' => 'refunded', 'paymentIntent' => $p['gateway_ref'], 'method' => isset( $fields['method'] ) ? $fields['method'] : $p['method'] ), true );
				if ( function_exists( 'do_action' ) ) do_action( 'efb_payment_refunded', Store::get( $p['id'] ), (int) $fields['refunded_minor'] );
			} elseif ( $moved && in_array( $to, array( 'refunded', 'partially_refunded' ), true ) ) {
				self::update_entry( Store::get( $p['id'] ), array( 'status' => $to ) );
			}
		} else {
			// Same status: still keep what the provider adds (the method of a pending payment, a larger refunded amount).
			$keep = array();
			if ( isset( $fields['method'] ) && $fields['method'] !== (string) $p['method'] ) $keep['method'] = $fields['method'];
			if ( isset( $fields['refunded_minor'] ) && $fields['refunded_minor'] > (int) $p['refunded_minor'] ) $keep['refunded_minor'] = $fields['refunded_minor'];
			if ( $keep ) {
				Store::update( $p['id'], $keep );
				Store::log_event( $p['id'], $p['gateway'], $source, array( 'type' => 'updated', 'verified' => 1, 'note' => implode( ', ', array_map( function ( $k, $v ) { return $k . ' ' . $v; }, array_keys( $keep ), $keep ) ) ) );
			}
		}
		self::settle_refunds_from( Store::get( $p['id'] ), $st, $source );
		self::keep_status_text( Store::get( $p['id'] ), $st, $source );
		$now = Store::get( $p['id'] );
		if ( $now && $now['status'] === 'paid' && empty( $now['finalized_at'] ) ) self::finalize( $now );
		if ( $now && in_array( $now['status'], array( 'failed', 'expired', 'canceled' ), true ) && $now['status'] !== $from ) {
			if ( function_exists( 'do_action' ) ) do_action( 'efb_payment_failed', $now, $source );
			self::notify_failure( $now );
		}
		return $now ? $now['status'] : $from;
	}

	/**
	 * Remote_State::$visitor_message (shown on the status page while pending) and $note (event log,
	 * e-mail to the owner when asked), kept in the payment's next_json under "status_text".
	 */
	private static function keep_status_text( $p, Remote_State $st, $source ) {
		if ( ! $p ) return;
		$msg = Field::text( (string) $st->visitor_message, 300 );
		$next = is_string( $p['next_json'] ) && $p['next_json'] !== '' ? json_decode( $p['next_json'], true ) : array();
		$next = is_array( $next ) ? $next : array();
		$old = isset( $next['status_text'] ) ? (string) $next['status_text'] : '';
		if ( $msg !== $old ) {
			if ( $msg === '' ) unset( $next['status_text'] ); else $next['status_text'] = $msg;
			Store::update( $p['id'], array( 'next_json' => wp_json_encode_efb_pay( $next ) ) );
		}
		// Why a payment ended (Remote_State::$reason): the status page words it (Reasons::CODES).
		$reason = Reasons::clean( $st->reason );
		if ( $reason !== '' && in_array( $p['status'], array( 'failed', 'canceled', 'expired' ), true ) ) {
			$now = Store::get( $p['id'] );
			$next = $now && is_string( $now['next_json'] ) && $now['next_json'] !== '' ? json_decode( $now['next_json'], true ) : array();
			$next = is_array( $next ) ? $next : array();
			if ( ! isset( $next['fail_reason'] ) || $next['fail_reason'] !== $reason ) {
				$next['fail_reason'] = $reason;
				Store::update( $p['id'], array( 'next_json' => wp_json_encode_efb_pay( $next ) ) );
				Store::log_event( $p['id'], $p['gateway'], $source, array( 'type' => 'note', 'note' => 'reason ' . $reason ) );
			}
		}
		$note = Field::text( (string) $st->note, 200 );
		if ( $note !== '' && ! self::has_note( $p['id'], $note ) ) {
			Store::log_event( $p['id'], $p['gateway'], $source, array( 'type' => 'note', 'note' => $note ) );
			if ( $st->notify_owner ) self::mail_admin( 'note', $p, $note );
		}
	}

	private static function has_note( $payment_id, $note ) {
		foreach ( Store::events( $payment_id ) as $e ) {
			if ( $e['type'] === 'note' && $e['note'] === $note ) return true;
		}
		return false;
	}

	/**
	 * Settles EFB's pending refunds from what the provider reports: by reference
	 * (Remote_State::$refunds), else oldest first while the provider's refunded amount covers them.
	 */
	private static function settle_refunds_from( $p, Remote_State $st, $source ) {
		if ( ! $p ) return;
		$pending = array_values( array_filter( Store::refunds( $p['id'] ), function ( $r ) { return $r['status'] === 'pending'; } ) );
		if ( ! $pending ) return;
		$by_ref = array();
		foreach ( (array) $st->refunds as $r ) {
			if ( is_array( $r ) && isset( $r['ref'], $r['status'] ) && is_string( $r['ref'] ) && $r['ref'] !== '' && in_array( $r['status'], array( 'refunded', 'failed', 'canceled' ), true ) ) $by_ref[ $r['ref'] ] = $r['status'];
		}
		$covered = (int) $st->refunded_minor - Store::refunded_total( $p['id'] );
		// The provider's refunded amount was already written by apply_remote_state() when it reports a refunded status.
		$counted = in_array( $st->status, array( 'refunded', 'partially_refunded' ), true ) && (int) $st->refunded_minor > 0;
		foreach ( $pending as $r ) {
			if ( $r['gateway_ref'] !== '' && isset( $by_ref[ $r['gateway_ref'] ] ) ) {
				self::settle_refund( Store::get( $p['id'] ), (int) $r['id'], $by_ref[ $r['gateway_ref'] ], $source, 0, $counted );
			} elseif ( ! $by_ref && $covered >= (int) $r['amount_minor'] && in_array( $st->status, array( 'refunded', 'partially_refunded' ), true ) ) {
				$covered -= (int) $r['amount_minor'];
				self::settle_refund( Store::get( $p['id'] ), (int) $r['id'], 'refunded', $source, 0, true );
			}
		}
	}

	/**
	 * A pending refund is confirmed (refunded) or turned down (failed / canceled), by the provider
	 * (webhook, fetch) or by the owner. Only a confirmed refund counts in refunded_minor.
	 *
	 * @param bool $counted true when the provider's refunded amount was already written (fetch).
	 * @return bool
	 */
	public static function settle_refund( $p, $refund_id, $to, $source, $user_id = 0, $counted = false ) {
		if ( ! is_array( $p ) ) return false;
		$r = Store::get_refund( $refund_id );
		if ( ! $r || (int) $r['payment_id'] !== (int) $p['id'] || ! Store::settle_refund( $refund_id, $to ) ) return false;
		Store::log_event( $p['id'], $p['gateway'], $source, array( 'type' => $to === 'refunded' ? 'refund_confirmed' : 'refund_declined', 'note' => 'refund #' . (int) $refund_id . ' ' . Money::to_decimal_string( (int) $r['amount_minor'], $p['currency'] ) . ( $user_id ? ' by user ' . (int) $user_id : '' ) ) );
		if ( $to !== 'refunded' ) return true;
		$total = $counted ? max( (int) $p['refunded_minor'], Store::refunded_total( $p['id'] ) ) : min( (int) $p['amount_minor'], (int) $p['refunded_minor'] + (int) $r['amount_minor'] );
		$status = $total >= (int) $p['amount_minor'] ? 'refunded' : 'partially_refunded';
		if ( $status !== $p['status'] ) self::move( $p, $status, array( 'refunded_minor' => $total ), $source, 'refund #' . (int) $refund_id );
		elseif ( $total !== (int) $p['refunded_minor'] ) Store::update( $p['id'], array( 'refunded_minor' => $total ) );
		$after = Store::get( $p['id'] );
		self::update_entry( $after, array( 'status' => $after['status'] ) );
		if ( function_exists( 'do_action' ) ) do_action( 'efb_payment_refunded', $after, (int) $r['amount_minor'] );
		return true;
	}

	/** One or two transitions (created → pending → target). */
	private static function move( array $p, $to, array $fields, $source, $note = '' ) {
		$from = $p['status'];
		if ( $from === 'created' && $to !== 'pending' && $to !== 'failed' ) {
			if ( Store::transition( $p['id'], 'pending' ) ) {
				Store::log_event( $p['id'], $p['gateway'], $source, array( 'type' => 'transition', 'from_status' => 'created', 'to_status' => 'pending' ) );
				$from = 'pending';
			}
		}
		$ok = Store::transition( $p['id'], $to, $fields );
		Store::log_event( $p['id'], $p['gateway'], $source, array( 'type' => $ok ? 'transition' : 'transition_skipped', 'from_status' => $from, 'to_status' => $to, 'verified' => 1, 'note' => $note ) );
		if ( $ok && function_exists( 'do_action' ) ) do_action( 'efb_payment_status_changed', Store::get( $p['id'] ), $from, $to, $source );
		return $ok;
	}

	/**
	 * Asks the provider (Gateway::fetch()) and applies the answer. An unpaid payment past its
	 * pending_ttl() becomes expired (and is canceled at the provider when it can be).
	 *
	 * @return array{ok:bool,status:string,error:string}
	 */
	public static function refresh( array $p, $source ) {
		$gw = Registry::get( $p['gateway'] );
		if ( ! $gw ) return array( 'ok' => false, 'status' => $p['status'], 'error' => 'no_gateway' );
		$s = Settings::load( $p['gateway'] );
		try {
			$st = $gw->fetch( new Payment( $p ), $s );
		} catch ( \Throwable $e ) {
			$st = Remote_State::error( 'exception' );
		}
		Store::touch_checked( $p['id'] );
		if ( $st instanceof Remote_State && ! $st->ok && $st->mismatch ) {
			// The provider's answer is about another payment, site or mode: logged, never applied.
			Store::log_event( $p['id'], $p['gateway'], $source, array( 'type' => 'mismatch', 'note' => Redactor::redact_string( (string) $st->error ) ) );
			$status = $p['status'];
			if ( in_array( $status, array( 'created', 'pending', 'authorized' ), true ) && self::past_ttl( $gw, $p ) ) {
				self::apply_remote_state( $p, Remote_State::of( 'expired', null, '' ), $source );
				$status = Store::get( $p['id'] )['status'];
			}
			return array( 'ok' => false, 'status' => $status, 'error' => (string) $st->error, 'mismatch' => true );
		}
		if ( ! $st instanceof Remote_State || ! $st->ok ) {
			Store::log_event( $p['id'], $p['gateway'], $source, array( 'type' => 'fetch_failed', 'note' => $st instanceof Remote_State ? $st->error : 'bad_result' ) );
			return array( 'ok' => false, 'status' => $p['status'], 'error' => $st instanceof Remote_State ? $st->error : 'bad_result', 'mismatch' => false );
		}
		$status = self::apply_remote_state( $p, $st, $source );
		if ( in_array( $status, array( 'created', 'pending', 'authorized' ), true ) ) {
			if ( self::past_ttl( $gw, Store::get( $p['id'] ) ) ) {
				try { $gw->cancel( new Payment( $p ), $s ); } catch ( \Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				self::apply_remote_state( Store::get( $p['id'] ), Remote_State::of( 'expired', null, '' ), $source );
				$status = Store::get( $p['id'] )['status'];
			}
		}
		return array( 'ok' => true, 'status' => $status, 'error' => '', 'mismatch' => false );
	}

	/** Seconds after creation when the gateway counts an unpaid payment as expired (Gateway::pending_ttl()). */
	public static function ttl_of( $gw, array $p ) {
		if ( ! $gw instanceof Gateway ) return DAY_IN_SECONDS;
		try { return (int) $gw->pending_ttl( new Payment( $p ) ); } catch ( \Throwable $e ) { return DAY_IN_SECONDS; }
	}

	private static function past_ttl( $gw, $p ) {
		if ( ! is_array( $p ) ) return false;
		$ttl = self::ttl_of( $gw, $p );
		return $ttl > 0 && Store::time() - strtotime( $p['created_at'] . ' UTC' ) > $ttl;
	}

	/**
	 * Until when the page keeps asking (unix seconds): the payment's pending_ttl() from its creation,
	 * at least 15 minutes from now and at most 24 hours from now.
	 */
	public static function poll_until( array $p ) {
		$end = strtotime( $p['created_at'] . ' UTC' ) + self::ttl_of( Registry::get( $p['gateway'] ), $p );
		return (int) max( Store::time() + 15 * 60, min( Store::time() + DAY_IN_SECONDS, $end ) );
	}

	/* ------------------------------------------------------------------ finalize */

	/**
	 * Completes the entry of a paid payment exactly once: the entry becomes a normal one (read_ 0,
	 * its payment row "paid" with the provider's reference), then the channels set to run on
	 * payment (pay_act_*) run through efb_payment_run_actions, then efb_payment_paid fires.
	 */
	public static function finalize( array $p ) {
		if ( ! Store::claim_finalize( $p['id'] ) ) return false;
		$p = Store::get( $p['id'] );
		$twin = Store::finalized_sibling( (int) $p['msg_id'], (int) $p['id'] );
		if ( $twin ) {
			// Another attempt of the same entry (Try again / a new QR) was paid and finalized first:
			// the entry and its notifications stay as they are; the owner is told (refund one of them).
			$note = 'second payment for the entry of ' . $twin['uid'] . ' (' . $twin['gateway_ref'] . ')';
			Store::log_event( $p['id'], $p['gateway'], 'api', array( 'type' => 'duplicate_payment', 'to_status' => 'paid', 'note' => $note ) );
			self::mail_admin( 'duplicate', $p, $note );
			return true;
		}
		$rows = self::update_entry( $p, array( 'status' => 'paid', 'paymentIntent' => $p['gateway_ref'], 'method' => $p['method'], 'paid_at' => $p['paid_at'], 'pay_uid' => $p['uid'] ), true );
		Store::log_event( $p['id'], $p['gateway'], 'api', array( 'type' => 'finalized', 'to_status' => 'paid' ) );
		$form = self::form_rows( $p['form_id'] );
		if ( function_exists( 'do_action' ) ) {
			do_action( 'efb_payment_run_actions', $p, 'paid', is_array( $rows ) ? $rows : array(), is_array( $form ) ? $form : array() );
			do_action( 'efb_payment_paid', $p, array( 'rows' => $rows, 'form' => $form ) );
		}
		return true;
	}

	public static function finalize_by_id( $id ) {
		$p = Store::get( (int) $id );
		if ( $p && $p['status'] === 'paid' ) self::finalize( $p );
	}

	/**
	 * Updates the payment row inside the entry (emsfb_msg_.content) and, when $mark_new, turns the
	 * pending entry (read_ 2) into a new one (read_ 0).
	 *
	 * @return array|null The entry's rows after the change.
	 */
	public static function update_entry( array $p, array $fields, $mark_new = false, $takeover = false ) {
		global $wpdb;
		if ( empty( $p['msg_id'] ) ) return null;
		$t = $wpdb->prefix . 'emsfb_msg_';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$e = $wpdb->get_row( $wpdb->prepare( "SELECT content, read_ FROM `{$t}` WHERE msg_id = %d", (int) $p['msg_id'] ), ARRAY_A );
		if ( ! $e ) return null;
		$rows = self::decode_content( $e['content'] );
		if ( ! is_array( $rows ) ) return null;
		$found = false;
		foreach ( $rows as $i => $r ) {
			// An entry holds one payment row. Finalize ($mark_new) may take it over from a newer attempt
			// of the same entry (an older QR or page that was paid after all).
			if ( is_array( $r ) && ( $r['type'] ?? '' ) === 'payment' && ( ! isset( $r['pay_uid'] ) || $r['pay_uid'] === $p['uid'] || $mark_new || $takeover ) ) {
				foreach ( $fields as $k => $v ) {
					if ( $v === null || $v === '' ) continue;
					$rows[ $i ][ $k ] = is_string( $v ) ? str_replace( '"', '@efb@dq#', $v ) : $v;
				}
				$found = true;
			}
		}
		if ( ! $found ) return $rows;
		$data = array( 'content' => self::encode_content( $rows ) );
		if ( $mark_new && (string) $e['read_'] === '2' ) {
			$data['read_'] = 0;
			$data['read_date'] = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s' );
		}
		$wpdb->update( $t, $data, array( 'msg_id' => (int) $p['msg_id'] ) );
		return $rows;
	}

	public static function decode_content( $raw ) {
		if ( class_exists( '\Emsfb\Privacy' ) ) return \Emsfb\Privacy::decode_content( $raw );
		$rows = json_decode( str_replace( '\\"', '"', (string) $raw ), true );
		return is_array( $rows ) ? $rows : null;
	}

	/** Same storage format as _Public::efb_encode_rows_for_storage_efb() (quotes in values are @efb@dq#). */
	public static function encode_content( array $rows ) {
		$json = json_encode( array_values( $rows ), JSON_UNESCAPED_UNICODE );
		return str_replace( '"', '\\"', is_string( $json ) ? $json : '[]' );
	}

	/* ------------------------------------------------------------------ webhook / return / status */

	public static function handle_webhook( \WP_REST_Request $r ) {
		$slug = (string) $r['gateway'];
		$gw = Registry::get( $slug );
		// Unknown gateway: 200 and nothing else (no information, no outbound call).
		if ( ! $gw || ! Store::ready() ) return self::rest( null, 200 );
		if ( ! self::rate_ok( 'wh', 120 ) ) return self::rest( null, 429 );
		$body = (string) $r->get_body();
		if ( strlen( $body ) > 262144 ) return self::rest( null, 413 );
		$s = Settings::load( $slug );
		try {
			$ev = $gw->parse_webhook( $r, $s );
		} catch ( \Throwable $e ) {
			$ev = null;
		}
		if ( ! $ev instanceof Webhook_Event ) return self::rest( null, 200 );
		if ( $ev->auth_failed ) {
			Store::log_event( null, $slug, 'webhook', array( 'type' => 'auth_failed', 'http_code' => 401, 'payload' => $body === '' ? '' : Redactor::redact_string( substr( $body, 0, 2000 ) ) ) );
			return self::rest( null, 401 );
		}
		$key = hash( 'sha256', $slug . '|' . ( $ev->event_id !== '' ? 'id:' . $ev->event_id : 'body:' . $body ) );
		$p = $ev->gateway_ref !== '' ? Store::find_by_ref( $slug, $ev->gateway_ref ) : null;
		if ( ! $p && isset( $ev->payload['efb_uid'] ) ) {
			// Fallback for a payment whose provider reference was never stored (lost create answer).
			// A payment that already has a different reference is not this event's payment: its
			// state must not be applied to it, whatever metadata the event carries.
			$p = Store::find_by_uid( (string) $ev->payload['efb_uid'] );
			if ( $p && (string) $p['gateway_ref'] !== '' && $ev->gateway_ref !== '' && ! hash_equals( (string) $p['gateway_ref'], (string) $ev->gateway_ref ) ) {
				$p = null;
			}
		}
		if ( $p && $p['gateway'] !== $slug ) $p = null;
		if ( ! Store::claim_event( $slug, $key, array( 'payment_id' => $p ? $p['id'] : null, 'source' => 'webhook', 'type' => (string) $ev->type, 'verified' => $ev->verified ? 1 : 0, 'payload' => $ev->payload, 'http_code' => 200 ) ) ) {
			return self::rest( null, 200 ); // duplicate delivery
		}
		if ( ! $p ) return self::rest( null, 200 ); // not one of ours: no outbound call
		if ( $ev->needs_refetch || ! $ev->verified || ! $ev->state instanceof Remote_State ) {
			$res = self::refresh( $p, 'webhook' );
			if ( ! $res['ok'] && ! empty( $res['mismatch'] ) ) {
				// Proven to be about another payment or mode: logged by refresh(), answered 200 so the
				// provider stops sending it again; nothing is applied.
				return self::rest( null, 200 );
			}
			if ( ! $res['ok'] ) {
				// The provider was not reachable: let it deliver again (and the cron checks too).
				Store::release_event( $slug, $key );
				return self::rest( null, 503 );
			}
		} else {
			self::apply_remote_state( $p, $ev->state, 'webhook' );
		}
		return self::rest( null, 200 );
	}

	public static function handle_return( \WP_REST_Request $r ) {
		$uid = (string) $r->get_param( 'p' );
		$sig = (string) $r->get_param( 's' );
		$slug = (string) $r['gateway'];
		$p = Store::ready() ? Store::find_by_uid( $uid ) : null;
		if ( ! $p || $p['gateway'] !== $slug || ! self::verify_sig( $uid, $slug, $sig ) ) {
			return self::redirect( home_url( '/' ) );
		}
		$gw = Registry::get( $slug );
		if ( $gw && in_array( $p['status'], array( 'created', 'pending', 'authorized' ), true ) ) {
			// The query and, for a provider that comes back with a POST (signed fields), the body fields.
			$q = $r->get_query_params();
			$q = is_array( $q ) ? $q : array();
			if ( method_exists( $r, 'get_body_params' ) && is_array( $r->get_body_params() ) ) {
				foreach ( $r->get_body_params() as $k => $v ) {
					if ( is_string( $k ) && ! isset( $q[ $k ] ) && ( is_scalar( $v ) || is_array( $v ) ) ) $q[ $k ] = $v;
				}
			}
			$ref = $gw->parse_return( $q, new Payment( $p ) );
			if ( is_string( $ref ) && $ref !== '' && $ref === $p['gateway_ref'] ) self::refresh( $p, 'return' );
		}
		$to = $p['return_to'] !== '' ? $p['return_to'] : home_url( '/' );
		$back = array( 'efb_pay' => $p['uid'], 'efb_ps' => self::sign( $p['uid'], $p['gateway'] ) );
		// Back through the provider's cancel link while the provider still says open: the page says
		// "you left before paying" and offers Try again (wording only; the state is the server's).
		$now = Store::get( $p['id'] );
		if ( (string) $r->get_param( 'c' ) === '1' && $now && in_array( $now['status'], array( 'created', 'pending', 'authorized' ), true ) ) $back['efb_pc'] = '1';
		$to = add_query_arg( $back, $to );
		$to = (string) wp_validate_redirect( $to, home_url( '/' ) );
		// The gateway's own small return page (e.g. a frame that tells its parent page to continue).
		if ( $gw ) {
			$q = $r->get_query_params();
			try { $html = $gw->return_page( new Payment( Store::get( $p['id'] ) ), is_array( $q ) ? $q : array(), $to ); } catch ( \Throwable $e ) { $html = ''; }
			if ( is_string( $html ) && $html !== '' ) return self::html_page( $html );
		}
		return self::redirect( $to );
	}

	/** GET payments/status?p&s (no nonce; the signature and a rate limit protect it). */
	public static function handle_status( \WP_REST_Request $r ) {
		if ( ! self::rate_ok( 'st', 60 ) ) return self::rest( array( 'state' => 'unknown', 'retry_after' => 30 ), 429, true );
		$uid = (string) $r->get_param( 'p' );
		$p = Store::ready() ? Store::find_by_uid( $uid ) : null;
		if ( ! $p || ! self::verify_sig( $uid, $p['gateway'], (string) $r->get_param( 's' ) ) ) return self::rest( array( 'state' => 'unknown' ), 404, true );
		return self::rest( self::status_answer( $p, 'status' ), 200, true );
	}

	/** POST payments/poll {uid, attempt_key} (nonce; from the page that created the payment). */
	public static function handle_poll( \WP_REST_Request $r ) {
		if ( ! self::rate_ok( 'po', 60 ) ) return self::rest( array( 'state' => 'unknown', 'retry_after' => 30 ), 429, true );
		$p = Store::ready() ? Store::find_by_uid( (string) $r->get_param( 'uid' ) ) : null;
		if ( ! $p || ! hash_equals( (string) $p['attempt_key'], (string) $r->get_param( 'attempt_key' ) ) ) return self::rest( array( 'state' => 'unknown' ), 404, true );
		return self::rest( self::status_answer( $p, 'poll' ), 200, true );
	}

	/** What the page shows. At most one provider check per 10 seconds per payment. */
	public static function status_answer( array $p, $source ) {
		if ( in_array( $p['status'], array( 'created', 'pending', 'authorized' ), true ) ) {
			$last = $p['last_checked_at'] ? strtotime( $p['last_checked_at'] . ' UTC' ) : 0;
			if ( Store::time() - $last >= 10 ) {
				self::refresh( $p, $source );
				$p = Store::get( $p['id'] );
			}
		}
		$state = $p['status'];
		$kind = in_array( $state, array( 'paid', 'partially_refunded', 'refunded' ), true ) ? 'paid'
			: ( in_array( $state, array( 'failed', 'canceled', 'expired' ), true ) ? 'failed' : 'pending' );
		$out = array( 'state' => $kind, 'status' => $state, 'form_id' => (int) $p['form_id'], 'test_mode' => $p['mode'] !== 'live', 'can_retry' => $kind === 'failed' );
		if ( $kind === 'pending' ) $out['poll_until'] = self::poll_until( $p );
		$next = is_string( $p['next_json'] ) && $p['next_json'] !== '' ? json_decode( $p['next_json'], true ) : null;
		$own = $kind === 'pending' && is_array( $next ) && isset( $next['status_text'] ) ? Field::text( (string) $next['status_text'], 300 ) : '';
		if ( $kind === 'failed' ) {
			// Why it ended, in safe words (the gateway's Remote_State reason, else the status); "Try again" follows.
			$reason = is_array( $next ) && isset( $next['fail_reason'] ) ? Reasons::clean( $next['fail_reason'] ) : '';
			if ( $reason === '' && in_array( $state, array( 'canceled', 'expired' ), true ) ) $reason = $state;
			if ( in_array( $reason, array( 'config', 'payer_invalid', 'rate_limited' ), true ) ) $reason = '';
			if ( $reason !== '' ) $out['reason'] = $reason;
			$own = $reason !== '' ? self::message( $reason, array( 'gateway' => $p['gateway'] ) ) : '';
		}
		$out['message'] = $own !== '' ? $own : ( $kind === 'paid' ? __( 'Payment received. Thank you!', 'easy-form-builder' )
			: ( $kind === 'failed' ? ( $state === 'expired' ? __( 'The payment time ran out. Please try again.', 'easy-form-builder' ) : __( 'The payment was not completed. You have not been charged. You can try again.', 'easy-form-builder' ) )
			: __( 'We are waiting for the payment confirmation. You can keep this page open; we will update it automatically.', 'easy-form-builder' ) ) );
		if ( $kind === 'paid' ) {
			$form = self::form_rows( $p['form_id'] );
			$f0 = is_array( $form ) && isset( $form[0] ) ? $form[0] : array();
			if ( isset( $f0['trackingCode'] ) && in_array( $f0['trackingCode'], array( true, 1, '1', 'true' ), true ) ) $out['track'] = $p['track'];
			if ( isset( $f0['thank_you'], $f0['rePage'] ) && $f0['thank_you'] === 'rdrct' && is_string( $f0['rePage'] ) && $f0['rePage'] !== '' ) {
				$url = str_replace( '@efb@', '/', $f0['rePage'] );
				$url = function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
				if ( $url !== '' ) $out['redirect'] = $url;
			}
		}
		return $out;
	}

	private static function rest( $data, $code, $no_store = false ) {
		$res = new \WP_REST_Response( $data, $code );
		if ( $no_store ) $res->header( 'Cache-Control', 'no-store, max-age=0' );
		return $res;
	}

	/** How a gateway's own return page is sent (tests replace it). */
	public static $emit_html = null;

	private static function html_page( $html ) {
		if ( is_callable( self::$emit_html ) ) return call_user_func( self::$emit_html, $html );
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'Cache-Control: no-store, max-age=0' );
			header( 'X-Content-Type-Options: nosniff' );
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the gateway add-on's own page (it escapes its values)
		exit;
	}

	private static function redirect( $url ) {
		$res = new \WP_REST_Response( null, 303 );
		$res->header( 'Location', $url );
		$res->header( 'Cache-Control', 'no-store, max-age=0' );
		return $res;
	}

	/* ------------------------------------------------------------------ reconcile */

	/**
	 * emsfb_pmt_reconcile (WP-Cron, also from the daily job and `wp efb payments reconcile`): checks
	 * open payments with back-off; at most 25 per run and 60 seconds; stops calling a gateway after
	 * its first network / auth / rate-limit error; one run at a time.
	 *
	 * @return array{checked:int,stopped:array}
	 */
	public static function reconcile( $limit = 25, $budget = 60 ) {
		$out = array( 'checked' => 0, 'stopped' => array() );
		if ( ! Store::ready() ) return $out;
		if ( get_transient( 'emsfb_pmt_reconcile_lock' ) ) return $out;
		set_transient( 'emsfb_pmt_reconcile_lock', 1, 120 );
		$start = microtime( true );
		try {
			foreach ( Store::due_for_reconcile( $limit, self::reconcile_days() ) as $p ) {
				if ( microtime( true ) - $start > $budget ) break;
				if ( isset( $out['stopped'][ $p['gateway'] ] ) ) continue;
				$res = self::refresh( $p, 'cron' );
				$out['checked']++;
				if ( ! $res['ok'] && ( strpos( $res['error'], 'network' ) === 0 || in_array( $res['error'], array( 'http_401', 'http_403', 'http_429', 'exception' ), true ) ) ) {
					$out['stopped'][ $p['gateway'] ] = $res['error'];
				}
			}
		} finally {
			delete_transient( 'emsfb_pmt_reconcile_lock' );
		}
		update_option( 'emsfb_pmt_reconcile_last', Store::time(), false );
		if ( Store::due_for_reconcile( 1, self::reconcile_days() ) && function_exists( 'wp_schedule_single_event' ) && function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'emsfb_pmt_reconcile' ) ) {
			wp_schedule_single_event( Store::time() + 900, 'emsfb_pmt_reconcile' );
		}
		return $out;
	}

	/**
	 * How far back the reconcile job looks (days): 31 by default, so a payment whose gateway keeps it
	 * open for weeks (pending_ttl(), e.g. a bank transfer) is still checked and then expired by its
	 * own ttl. Filter efb_payment_reconcile_days (1 … 120).
	 */
	public static function reconcile_days() {
		$d = function_exists( 'apply_filters' ) ? (int) apply_filters( 'efb_payment_reconcile_days', 31 ) : 31;
		return max( 1, min( 120, $d ) );
	}

	/* ------------------------------------------------------------------ refund */

	/**
	 * Full or partial refund through the gateway, once per (payment, amount, n-th refund): the
	 * idem key is UNIQUE in emsfb_pmt_rfd_, so a double click refunds once.
	 *
	 * @return array{ok:bool,error:string,message:string}
	 */
	public static function refund( array $p, $amount_minor, $reason, $user_id ) {
		$gw = Registry::get( $p['gateway'] );
		$amount_minor = (int) $amount_minor;
		$left = (int) $p['amount_minor'] - (int) $p['refunded_minor'] - self::pending_refunds( $p['id'] );
		if ( ! $gw ) return array( 'ok' => false, 'error' => 'no_gateway', 'message' => '' );
		$sup = array_merge( ( new Gateway_Defaults() )->supports(), (array) $gw->supports() );
		if ( empty( $sup['refund'] ) ) return array( 'ok' => false, 'error' => 'unsupported', 'message' => '' );
		if ( ! in_array( $p['status'], array( 'paid', 'partially_refunded' ), true ) || $amount_minor <= 0 || $amount_minor > $left ) return array( 'ok' => false, 'error' => 'bad_amount', 'message' => '' );
		if ( $amount_minor < $left && empty( $sup['partial_refund'] ) ) return array( 'ok' => false, 'error' => 'unsupported_partial', 'message' => '' );
		$n = count( Store::refunds( $p['id'] ) );
		$idem = hash( 'sha256', $p['uid'] . '|refund|' . $n . '|' . $amount_minor );
		$rid = Store::insert_refund( array( 'payment_id' => $p['id'], 'amount_minor' => $amount_minor, 'currency' => $p['currency'], 'reason' => substr( (string) $reason, 0, 255 ), 'idem_key' => $idem, 'created_by' => (int) $user_id ) );
		if ( ! $rid ) return array( 'ok' => false, 'error' => 'duplicate', 'message' => '' );
		try {
			$res = $gw->refund( new Payment( $p ), $amount_minor, (string) $reason, Settings::load( $p['gateway'] ) );
		} catch ( \Throwable $e ) {
			$res = Refund_Result::fail( 'exception', Redactor::redact_string( $e->getMessage() ) );
		}
		if ( ! $res instanceof Refund_Result || ! $res->ok ) {
			Store::update_refund( $rid, array( 'status' => 'failed' ) );
			Store::log_event( $p['id'], $p['gateway'], 'admin', array( 'type' => 'refund_failed', 'note' => $res instanceof Result ? $res->error . ' ' . $res->message : '' ) );
			return array( 'ok' => false, 'error' => $res instanceof Result ? $res->error : 'bad_result', 'message' => $res instanceof Result ? $res->message : '' );
		}
		if ( $res->status === 'pending' ) {
			// Asked, not yet done: it counts once the provider (webhook, fetch) or the owner confirms it.
			Store::update_refund( $rid, array( 'gateway_ref' => substr( (string) $res->gateway_ref, 0, 100 ) ) );
			Store::log_event( $p['id'], $p['gateway'], 'admin', array( 'type' => 'refund_pending', 'note' => 'refund #' . $rid . ' ' . $amount_minor . ' by user ' . (int) $user_id ) );
			return array( 'ok' => true, 'error' => '', 'message' => '', 'pending' => true );
		}
		Store::update_refund( $rid, array( 'status' => 'refunded', 'gateway_ref' => substr( (string) $res->gateway_ref, 0, 100 ) ) );
		$total = (int) $p['refunded_minor'] + $amount_minor;
		$to = $total >= (int) $p['amount_minor'] ? 'refunded' : 'partially_refunded';
		self::move( $p, $to, array( 'refunded_minor' => $total ), 'admin', 'refund ' . $amount_minor . ' by user ' . (int) $user_id );
		$after = Store::get( $p['id'] );
		self::update_entry( $after, array( 'status' => $to ) );
		if ( function_exists( 'do_action' ) ) do_action( 'efb_payment_refunded', $after, $amount_minor );
		return array( 'ok' => true, 'error' => '', 'message' => '', 'pending' => false );
	}

	/**
	 * The owner's words for a refund answer (refund() / record_external_refund()): what is wrong and
	 * what to do, with the gateway's own reason when it gave one (admin only; never a secret: the
	 * add-ons redact their texts).
	 */
	public static function refund_message( array $r, array $p ) {
		$left = max( 0, (int) $p['amount_minor'] - (int) $p['refunded_minor'] - self::pending_refunds( $p['id'] ) );
		$left_t = Money::format( $left, $p['currency'] );
		$why = isset( $r['message'] ) ? Field::text( (string) $r['message'], 300 ) : '';
		switch ( isset( $r['error'] ) ? $r['error'] : '' ) {
			case 'unsupported':
				return __( 'This gateway cannot refund through Easy Form Builder. Refund it in the gateway\'s dashboard, then use "Mark as refunded outside EFB".', 'easy-form-builder' );
			case 'unsupported_partial':
				/* translators: %s: the amount left to refund */
				return sprintf( __( 'This gateway can only refund the full remaining amount (%s).', 'easy-form-builder' ), $left_t );
			case 'bad_amount':
				if ( ! in_array( $p['status'], array( 'paid', 'partially_refunded' ), true ) ) return __( 'Only a paid payment can be refunded.', 'easy-form-builder' );
				/* translators: %s: the amount left to refund */
				return sprintf( __( 'The refund amount must be more than 0 and at most %s (what is left after earlier and pending refunds).', 'easy-form-builder' ), $left_t );
			case 'duplicate':
				return __( 'This refund was already requested.', 'easy-form-builder' );
			case 'no_gateway':
				return __( 'The add-on of this gateway is not active, so it cannot refund. Activate it, or refund in the gateway\'s dashboard and use "Mark as refunded outside EFB".', 'easy-form-builder' );
			case 'reason_required':
				return __( 'Please write why (for example: refunded in the gateway dashboard).', 'easy-form-builder' );
		}
		return $why !== ''
			/* translators: %s: the gateway's reason */
			? sprintf( __( 'The gateway refused the refund: %s Nothing was refunded.', 'easy-form-builder' ), rtrim( $why, '.' ) . '.' )
			/* translators: %s: error code */
			: sprintf( __( 'The gateway refused the refund (%s). Nothing was refunded. Try again later or refund it in the gateway\'s dashboard.', 'easy-form-builder' ), isset( $r['error'] ) ? $r['error'] : '' );
	}

	/** The amount of a payment's refunds still waiting for the provider (they are not counted, but not refundable again). */
	public static function pending_refunds( $payment_id ) {
		$n = 0;
		foreach ( Store::refunds( $payment_id ) as $r ) {
			if ( $r['status'] === 'pending' ) $n += (int) $r['amount_minor'];
		}
		return $n;
	}

	/**
	 * "Mark as refunded outside EFB": the owner gave the money back from the provider's dashboard
	 * or the bank (a method without an API refund, or a refund made elsewhere). No call to the
	 * provider; a refund row (refunded, by the user, with the reason) and an audit event.
	 *
	 * @return array{ok:bool,error:string}
	 */
	public static function record_external_refund( array $p, $amount_minor, $reason, $user_id ) {
		$amount_minor = (int) $amount_minor;
		$left = (int) $p['amount_minor'] - (int) $p['refunded_minor'] - self::pending_refunds( $p['id'] );
		$reason = Field::text( (string) $reason, 200 );
		if ( ! in_array( $p['status'], array( 'paid', 'partially_refunded' ), true ) || $amount_minor <= 0 || $amount_minor > $left ) return array( 'ok' => false, 'error' => 'bad_amount' );
		if ( $reason === '' ) return array( 'ok' => false, 'error' => 'reason_required' );
		$n = count( Store::refunds( $p['id'] ) );
		$rid = Store::insert_refund( array( 'payment_id' => $p['id'], 'amount_minor' => $amount_minor, 'currency' => $p['currency'], 'status' => 'refunded',
			'reason' => substr( 'outside EFB: ' . $reason, 0, 255 ), 'idem_key' => hash( 'sha256', $p['uid'] . '|external|' . $n . '|' . $amount_minor ), 'created_by' => (int) $user_id ) );
		if ( ! $rid ) return array( 'ok' => false, 'error' => 'duplicate' );
		$total = (int) $p['refunded_minor'] + $amount_minor;
		$to = $total >= (int) $p['amount_minor'] ? 'refunded' : 'partially_refunded';
		self::move( $p, $to, array( 'refunded_minor' => $total ), 'admin', 'refunded outside EFB by user ' . (int) $user_id );
		Store::log_event( $p['id'], $p['gateway'], 'admin', array( 'type' => 'refund_external', 'note' => 'refund #' . $rid . ' ' . Money::to_decimal_string( $amount_minor, $p['currency'] ) . ' ' . $p['currency'] . ' by user ' . (int) $user_id . ': ' . $reason ) );
		$after = Store::get( $p['id'] );
		self::update_entry( $after, array( 'status' => $to ) );
		if ( function_exists( 'do_action' ) ) do_action( 'efb_payment_refunded', $after, $amount_minor );
		return array( 'ok' => true, 'error' => '' );
	}

	/* ------------------------------------------------------------------ mail */

	private static function notify_failure( array $p ) {
		$form = self::form_rows( $p['form_id'] );
		$f0 = is_array( $form ) && isset( $form[0] ) ? $form[0] : array();
		if ( Field::sanitize_key( 'pay_fail_notify', isset( $f0['pay_fail_notify'] ) ? $f0['pay_fail_notify'] : '' ) === 'admin' ) {
			self::mail_admin( 'failed', $p, $p['status'] );
		}
	}

	private static function mail_admin( $kind, $p, $note ) {
		if ( ! function_exists( 'wp_mail' ) || ! $p ) return;
		$to = get_option( 'admin_email' );
		$prefix = $p['mode'] !== 'live' ? '[TEST] ' : '';
		$subject = $prefix . ( $kind === 'mismatch'
			? __( 'Payment amount does not match', 'easy-form-builder' )
			: ( $kind === 'duplicate' ? __( 'An entry was paid twice', 'easy-form-builder' ) : ( $kind === 'note' ? __( 'A payment needs your attention', 'easy-form-builder' ) : __( 'A payment was not completed', 'easy-form-builder' ) ) ) ) . ' [' . ( function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : '' ) . ']';
		$body = sprintf( "%s\n\n%s: %s\n%s: %s\n%s: %s %s\n%s: %s\n", $subject,
			__( 'Confirmation Code', 'easy-form-builder' ), $p['track'],
			__( 'Gateway', 'easy-form-builder' ), $p['gateway'] . ' ' . $p['gateway_ref'],
			__( 'Amount', 'easy-form-builder' ), Money::to_decimal_string( $p['amount_minor'], $p['currency'] ), $p['currency'],
			__( 'Status', 'easy-form-builder' ), $p['status'] . ( $note !== '' ? ' (' . $note . ')' : '' ) );
		wp_mail( $to, $subject, $body );
	}
}
