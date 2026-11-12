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

	public static function return_url( $p ) {
		return add_query_arg( array( 'p' => $p['uid'], 's' => self::sign( $p['uid'], $p['gateway'] ) ), rest_url( 'Emsfb/v1/payments/return/' . $p['gateway'] ) );
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

	public static function message( $code, $extra = array() ) {
		switch ( $code ) {
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
		$gw = $field ? Registry::get( isset( $field['type'] ) ? (string) $field['type'] : '' ) : null;
		if ( ! $gw ) return self::fail( 'pay_unavailable' );
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
			'payer_email'  => isset( $a['payer_email'] ) ? substr( (string) $a['payer_email'], 0, 190 ) : '',
			'return_to'    => substr( $return_to, 0, 500 ),
			'locale'       => $locale,
			'ip'           => isset( $a['ip'] ) ? substr( (string) $a['ip'], 0, 45 ) : '',
		) );
		if ( ! $p ) {
			$prev = Store::find_by_attempt( $form_id, $attempt );
			return $prev ? self::replay( $prev ) : self::fail( 'gateway_error' );
		}
		Store::log_event( $p['id'], $slug, 'api', array( 'type' => 'create', 'to_status' => 'created', 'note' => 'quote ' . $quote['hash'] ) );

		$ctx = new Checkout_Context( array(
			'return_url'  => self::return_url( $p ),
			'cancel_url'  => add_query_arg( 'c', '1', self::return_url( $p ) ),
			'webhook_url' => self::webhook_url( $slug ),
			'locale'      => $locale,
			'method'      => isset( $a['method'] ) && is_string( $a['method'] ) && preg_match( '/^[a-z0-9_]{1,30}$/', $a['method'] ) ? $a['method'] : '',
			'description' => isset( $a['description'] ) ? substr( (string) $a['description'], 0, 120 ) : '',
			'payer_email' => $p['payer_email'],
			'metadata'    => array( 'efb_uid' => $p['uid'], 'form_id' => $form_id, 'track' => $p['track'] ),
		) );
		if ( function_exists( 'apply_filters' ) ) {
			$f = apply_filters( 'efb_payment_create_args', $ctx, $p, $quote );
			if ( $f instanceof Checkout_Context ) $ctx = $f;
		}
		try {
			$res = $gw->create( new Payment( $p ), $settings, $ctx );
		} catch ( \Throwable $e ) {
			$res = Create_Result::fail( 'exception', Redactor::redact_string( $e->getMessage() ) );
		}
		if ( ! $res instanceof Create_Result || ! $res->ok || $res->gateway_ref === '' ) {
			$err = $res instanceof Result ? $res->error . ' ' . $res->message : 'bad_result';
			Store::transition( $p['id'], 'failed' );
			Store::log_event( $p['id'], $slug, 'api', array( 'type' => 'create_failed', 'from_status' => 'created', 'to_status' => 'failed', 'note' => Redactor::redact_string( $err ) ) );
			return self::fail( 'gateway_error' );
		}
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
		if ( in_array( $to, array( 'paid', 'authorized' ), true ) ) {
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
		if ( in_array( $to, array( 'refunded', 'partially_refunded' ), true ) && $st->refunded_minor ) $fields['refunded_minor'] = (int) $st->refunded_minor;
		if ( $to !== $from ) self::move( $p, $to, $fields, $source );
		$now = Store::get( $p['id'] );
		if ( $now && $now['status'] === 'paid' && empty( $now['finalized_at'] ) ) self::finalize( $now );
		if ( $now && in_array( $now['status'], array( 'failed', 'expired', 'canceled' ), true ) && $now['status'] !== $from ) {
			if ( function_exists( 'do_action' ) ) do_action( 'efb_payment_failed', $now, $source );
			self::notify_failure( $now );
		}
		return $now ? $now['status'] : $from;
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
		if ( ! $st instanceof Remote_State || ! $st->ok ) {
			Store::log_event( $p['id'], $p['gateway'], $source, array( 'type' => 'fetch_failed', 'note' => $st instanceof Remote_State ? $st->error : 'bad_result' ) );
			return array( 'ok' => false, 'status' => $p['status'], 'error' => $st instanceof Remote_State ? $st->error : 'bad_result' );
		}
		$status = self::apply_remote_state( $p, $st, $source );
		if ( in_array( $status, array( 'created', 'pending', 'authorized' ), true ) ) {
			$ttl = (int) $gw->pending_ttl( new Payment( $p ) );
			$age = Store::time() - strtotime( $p['created_at'] . ' UTC' );
			if ( $ttl > 0 && $age > $ttl ) {
				try { $gw->cancel( new Payment( $p ), $s ); } catch ( \Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				self::apply_remote_state( Store::get( $p['id'] ), Remote_State::of( 'expired', null, '' ), $source );
				$status = Store::get( $p['id'] )['status'];
			}
		}
		return array( 'ok' => true, 'status' => $status, 'error' => '' );
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
		$rows = self::update_entry( $p, array( 'status' => 'paid', 'paymentIntent' => $p['gateway_ref'], 'method' => $p['method'], 'paid_at' => $p['paid_at'] ), true );
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
	public static function update_entry( array $p, array $fields, $mark_new = false ) {
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
			if ( is_array( $r ) && ( $r['type'] ?? '' ) === 'payment' && ( ! isset( $r['pay_uid'] ) || $r['pay_uid'] === $p['uid'] ) ) {
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
		if ( ! $p && isset( $ev->payload['efb_uid'] ) ) $p = Store::find_by_uid( (string) $ev->payload['efb_uid'] );
		if ( $p && $p['gateway'] !== $slug ) $p = null;
		if ( ! Store::claim_event( $slug, $key, array( 'payment_id' => $p ? $p['id'] : null, 'source' => 'webhook', 'type' => (string) $ev->type, 'verified' => $ev->verified ? 1 : 0, 'payload' => $ev->payload, 'http_code' => 200 ) ) ) {
			return self::rest( null, 200 ); // duplicate delivery
		}
		if ( ! $p ) return self::rest( null, 200 ); // not one of ours: no outbound call
		if ( $ev->needs_refetch || ! $ev->verified || ! $ev->state instanceof Remote_State ) {
			$res = self::refresh( $p, 'webhook' );
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
			$q = $r->get_query_params();
			$ref = $gw->parse_return( is_array( $q ) ? $q : array(), new Payment( $p ) );
			if ( is_string( $ref ) && $ref !== '' && $ref === $p['gateway_ref'] ) self::refresh( $p, 'return' );
		}
		$to = $p['return_to'] !== '' ? $p['return_to'] : home_url( '/' );
		$to = add_query_arg( array( 'efb_pay' => $p['uid'], 'efb_ps' => self::sign( $p['uid'], $p['gateway'] ) ), $to );
		return self::redirect( (string) wp_validate_redirect( $to, home_url( '/' ) ) );
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
		$out['message'] = $kind === 'paid' ? __( 'Payment received. Thank you!', 'easy-form-builder' )
			: ( $kind === 'failed' ? ( $state === 'expired' ? __( 'The payment time ran out. Please try again.', 'easy-form-builder' ) : __( 'The payment was not completed. You have not been charged. You can try again.', 'easy-form-builder' ) )
			: __( 'We are waiting for the payment confirmation. You can keep this page open; we will update it automatically.', 'easy-form-builder' ) );
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
			foreach ( Store::due_for_reconcile( $limit ) as $p ) {
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
		if ( Store::due_for_reconcile( 1 ) && function_exists( 'wp_schedule_single_event' ) && function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'emsfb_pmt_reconcile' ) ) {
			wp_schedule_single_event( Store::time() + 900, 'emsfb_pmt_reconcile' );
		}
		return $out;
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
		$left = (int) $p['amount_minor'] - (int) $p['refunded_minor'];
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
		Store::update_refund( $rid, array( 'status' => $res->status === 'pending' ? 'pending' : 'refunded', 'gateway_ref' => substr( (string) $res->gateway_ref, 0, 100 ) ) );
		$total = (int) $p['refunded_minor'] + $amount_minor;
		$to = $total >= (int) $p['amount_minor'] ? 'refunded' : 'partially_refunded';
		self::move( $p, $to, array( 'refunded_minor' => $total ), 'admin', 'refund ' . $amount_minor . ' by user ' . (int) $user_id );
		$after = Store::get( $p['id'] );
		self::update_entry( $after, array( 'status' => $to ) );
		if ( function_exists( 'do_action' ) ) do_action( 'efb_payment_refunded', $after, $amount_minor );
		return array( 'ok' => true, 'error' => '', 'message' => '' );
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
			: __( 'A payment was not completed', 'easy-form-builder' ) ) . ' [' . ( function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : '' ) . ']';
		$body = sprintf( "%s\n\n%s: %s\n%s: %s\n%s: %s %s\n%s: %s\n", $subject,
			__( 'Confirmation Code', 'easy-form-builder' ), $p['track'],
			__( 'Gateway', 'easy-form-builder' ), $p['gateway'] . ' ' . $p['gateway_ref'],
			__( 'Amount', 'easy-form-builder' ), Money::to_decimal_string( $p['amount_minor'], $p['currency'] ), $p['currency'],
			__( 'Status', 'easy-form-builder' ), $p['status'] . ( $note !== '' ? ' (' . $note . ')' : '' ) );
		wp_mail( $to, $subject, $body );
	}
}
