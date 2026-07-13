<?php
/**
 * REST endpoints and REST guard for EFB Human Shield.
 *
 * @package Easy_Form_Builder
 * @subpackage Human_Shield
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Emsfb_Human_Shield_Rest {
	/** @var Emsfb_Human_Shield */
	private $shield;

	public function __construct( Emsfb_Human_Shield $shield ) {
		$this->shield = $shield;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_pre_dispatch', array( $this, 'guard_efb_rest' ), 5, 3 );
	}

	public function register_routes() {
		register_rest_route(
			'EmsfbShield/v1',
			'/challenge',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'challenge' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'EmsfbShield/v1',
			'/attest',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'attest' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function challenge( \WP_REST_Request $request ) {
		if ( ! $this->shield->is_enabled() ) {
			return $this->json_response( array( 'success' => false, 'code' => 'shield_disabled', 'message' => esc_html__( 'Form Security & Spam Protection is disabled.', 'easy-form-builder' ) ), 200 );
		}

		if ( ! $this->shield->requirements_ok() ) {
			// Details stay in the admin System tab; never leak PHP/server state
			// to unauthenticated visitors.
			return $this->json_response(
				array(
					'success' => false,
					'code'    => 'requirements_missing',
					'message' => esc_html__( 'The security service is not available right now. Your form still works.', 'easy-form-builder' ),
				),
				200
			);
		}

		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		$form_id = isset( $params['formId'] ) ? absint( $params['formId'] ) : 0;
		$route   = isset( $params['route'] ) ? sanitize_text_field( $params['route'] ) : '';
		$sid     = isset( $params['sid'] ) ? sanitize_text_field( $params['sid'] ) : '';
		$ip      = $this->shield->get_client_ip();
		$ua      = $this->shield->current_user_agent();
		$settings = $this->shield->get_settings();

		if ( 'block' === $this->shield->ip_list_decision( $ip ) ) {
			return $this->json_response( array( 'success' => false, 'code' => 'ip_blocked', 'message' => esc_html__( 'Too many requests. Please try again shortly.', 'easy-form-builder' ) ), 403 );
		}

		$rate = $this->shield->rate_limiter->evaluate_context(
			array(
				'route'   => '/EmsfbShield/v1/challenge',
				'action'  => 'shield_challenge',
				'form_id' => $form_id,
				'sid'     => $sid,
				'ip'      => $ip,
				'ua'      => $ua,
			)
		);
		if ( empty( $rate['allowed'] ) ) {
			return $this->json_response(
				array( 'success' => false, 'message' => esc_html__( 'Too many requests. Please try again shortly.', 'easy-form-builder' ), 'code' => 'rate_limited' ),
				429,
				array( 'Retry-After' => isset( $rate['retry_after'] ) ? (string) (int) $rate['retry_after'] : '60' )
			);
		}

		$challenge_id = $this->shield->random_string( 32 );
		$expires_at = time() + (int) $settings['challenge_ttl_seconds'];

		if ( $this->shield->tables_ready() ) {
			$inserted = $this->shield->db->insert(
				$this->shield->table_name( 'challenges' ),
				array(
					'challenge_id'   => $challenge_id,
					'form_id'        => $form_id,
					'route'          => $route,
					'ip_hash'        => $this->shield->hash_value( $ip, 'ip' ),
					'ip_prefix_hash' => $this->shield->hash_value( $this->shield->get_ip_prefix( $ip ), 'ip_prefix' ),
					'ua_hash'        => $this->shield->hash_value( $ua, 'ua' ),
					'sid_hash'       => $sid ? $this->shield->hash_value( $sid, 'sid' ) : '',
					'score'          => 0,
					'token_jti'      => '',
					'used'           => 0,
					'expires_at'     => $expires_at,
					'created_at'     => current_time( 'mysql' ),
					'meta_json'      => '',
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
			);
			if ( false === $inserted ) {
				return $this->json_response( array( 'success' => false, 'code' => 'storage_failed', 'message' => esc_html__( 'The security service is temporarily unavailable.', 'easy-form-builder' ) ), 503 );
			}
		}

		return $this->json_response(
			array(
				'success'     => true,
				'challengeId' => $challenge_id,
				'expiresAt'   => $expires_at,
			),
			200
		);
	}

	public function attest( \WP_REST_Request $request ) {
		if ( ! $this->shield->is_enabled() ) {
			return $this->json_response( array( 'success' => false, 'code' => 'shield_disabled', 'message' => esc_html__( 'Form Security & Spam Protection is disabled.', 'easy-form-builder' ) ), 200 );
		}

		if ( ! $this->shield->requirements_ok() ) {
			return $this->json_response( array( 'success' => false, 'code' => 'requirements_missing', 'message' => esc_html__( 'The security service is not available right now. Your form still works.', 'easy-form-builder' ) ), 200 );
		}

		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		$challenge_id = isset( $params['challengeId'] ) ? sanitize_text_field( $params['challengeId'] ) : '';
		$form_id      = isset( $params['formId'] ) ? absint( $params['formId'] ) : 0;
		$route        = isset( $params['route'] ) ? sanitize_text_field( $params['route'] ) : '';
		$sid          = isset( $params['sid'] ) ? sanitize_text_field( $params['sid'] ) : '';
		$metrics      = isset( $params['metrics'] ) && is_array( $params['metrics'] ) ? $params['metrics'] : array();

		if ( '' === $challenge_id ) {
			return $this->json_response( array( 'success' => false, 'code' => 'challenge_missing', 'message' => esc_html__( 'Missing challenge.', 'easy-form-builder' ) ), 200 );
		}

		$challenge = $this->get_challenge( $challenge_id );
		if ( ! $challenge || (int) $challenge['expires_at'] < time() ) {
			return $this->json_response( array( 'success' => false, 'code' => 'challenge_expired', 'message' => esc_html__( 'Expired challenge.', 'easy-form-builder' ) ), 200 );
		}

		if ( ! empty( $challenge['used'] ) ) {
			// A challenge whose token was already consumed can never mint a
			// working token again; tell the client to start a fresh challenge.
			return $this->json_response( array( 'success' => false, 'code' => 'challenge_used', 'message' => esc_html__( 'Challenge already used.', 'easy-form-builder' ) ), 200 );
		}

		if ( ! empty( $challenge['form_id'] ) && (int) $challenge['form_id'] !== (int) $form_id ) {
			return $this->json_response( array( 'success' => false, 'code' => 'form_mismatch', 'message' => esc_html__( 'Form mismatch.', 'easy-form-builder' ) ), 200 );
		}

		$context = array(
			'form_id' => $form_id,
			'route'   => $route,
			'sid'     => $sid,
			'ip'      => $this->shield->get_client_ip(),
			'ua'      => $this->shield->current_user_agent(),
		);

		if ( 'block' === $this->shield->ip_list_decision( $context['ip'] ) ) {
			return $this->json_response( array( 'success' => false, 'code' => 'ip_blocked', 'message' => esc_html__( 'Too many requests. Please try again shortly.', 'easy-form-builder' ) ), 403 );
		}

		$rate = $this->shield->rate_limiter->evaluate_context(
			array(
				'route'   => '/EmsfbShield/v1/attest',
				'action'  => 'shield_attest',
				'form_id' => $form_id,
				'sid'     => $sid,
				'ip'      => $context['ip'],
				'ua'      => $context['ua'],
			)
		);
		if ( empty( $rate['allowed'] ) ) {
			return $this->json_response(
				array( 'success' => false, 'message' => esc_html__( 'Too many requests. Please try again shortly.', 'easy-form-builder' ), 'code' => 'rate_limited' ),
				429,
				array( 'Retry-After' => isset( $rate['retry_after'] ) ? (string) (int) $rate['retry_after'] : '60' )
			);
		}

		$result = $this->shield->detector->score( $metrics, $context );
		$jti = $this->shield->random_string( 24 );
		$settings = $this->shield->get_settings();
		$expires_at = time() + (int) $settings['token_ttl_seconds'];

		$payload = array(
			'cid' => $challenge_id,
			'jti' => $jti,
			'fid' => $form_id,
			'route' => $route,
			'sid_hash' => $sid ? $this->shield->hash_value( $sid, 'sid' ) : '',
			'ip_prefix_hash' => $this->shield->hash_value( $this->shield->get_ip_prefix( $context['ip'] ), 'ip_prefix' ),
			'ua_hash' => $this->shield->hash_value( $context['ua'], 'ua' ),
			'score' => (int) $result['score'],
			'iat' => time(),
			'exp' => $expires_at,
		);

		$token = $this->sign_payload( $payload );
		if ( false === $token ) {
			return $this->json_response( array( 'success' => false, 'code' => 'sign_failed', 'message' => esc_html__( 'The security service is not available right now. Your form still works.', 'easy-form-builder' ) ), 200 );
		}

		if ( $this->shield->tables_ready() ) {
			$meta = ! empty( $settings['store_raw_metrics'] ) ? array( 'metrics' => $metrics, 'reasons' => $result['reasons'] ) : array( 'reasons' => $result['reasons'] );
			$updated = $this->shield->db->update(
				$this->shield->table_name( 'challenges' ),
				array(
					'form_id'   => $form_id,
					'route'     => $route,
					'sid_hash'  => $payload['sid_hash'],
					'score'     => (int) $result['score'],
					'token_jti' => $jti,
					'expires_at'=> $expires_at,
					'meta_json' => $this->shield->json_encode( $meta ),
				),
				array( 'challenge_id' => $challenge_id ),
				array( '%d', '%s', '%s', '%d', '%s', '%d', '%s' ),
				array( '%s' )
			);
			if ( false === $updated ) {
				return $this->json_response( array( 'success' => false, 'code' => 'storage_failed', 'message' => esc_html__( 'The security service is temporarily unavailable.', 'easy-form-builder' ) ), 503 );
			}
		}

		return $this->json_response(
			array(
				'success' => true,
				'token'   => $token,
				'score'   => (int) $result['score'],
				'reasons' => $result['reasons'],
				'expiresAt' => $expires_at,
			),
			200
		);
	}

	public function guard_efb_rest( $result, $server, $request ) {
		if ( ! $request instanceof \WP_REST_Request || ! $this->shield->is_enabled() ) {
			return $result;
		}

		$route = $request->get_route();
		$action = $this->protected_action_for_route( $route );
		if ( false === $action ) {
			return $result;
		}

		$settings = $this->shield->get_settings();

		// Manual allow/block lists are explicit admin decisions: the allowlist
		// bypasses every check, the blocklist blocks even in monitor mode and
		// even when PHP requirements are missing (no crypto needed to match).
		$list_decision = $this->shield->ip_list_decision( $this->shield->get_client_ip() );
		if ( 'allow' === $list_decision ) {
			$context = $this->build_context( $request, $route, $action );
			$this->shield->log_event( $context, 'allow', 100, array( 'ip_allowlisted' ) );
			return $result;
		}
		if ( 'block' === $list_decision ) {
			$context = $this->build_context( $request, $route, $action );
			$this->shield->log_event( $context, 'block', 0, array( 'ip_blocklisted' ) );
			return $this->blocked_response( esc_html__( 'Your request looked too fast or unusual. Please try again in a few minutes.', 'easy-form-builder' ), 403, 0, array( 'ip_blocklisted' ) );
		}

		if ( ! $this->shield->requirements_ok() ) {
			$context = $this->build_context( $request, $route, $action );
			$this->shield->log_event( $context, 'monitor', 0, array( 'requirements_missing' ) );
			if ( ! empty( $settings['fail_closed_on_missing_requirements'] ) ) {
				return $this->blocked_response( esc_html__( 'The security service is temporarily unavailable. Please try again later.', 'easy-form-builder' ), 503, 0, array( 'requirements_missing' ) );
			}
			return $result;
		}

		$context = $this->build_context( $request, $route, $action );
		$rate = $this->shield->rate_limiter->evaluate_context( $context );
		if ( empty( $rate['allowed'] ) ) {
			return $this->decide_block( $context, 0, array( $rate['reason'] ), 'block', isset( $rate['retry_after'] ) ? (int) $rate['retry_after'] : 60 );
		}

		$token = $request->get_header( 'x_efb_human_token' );
		if ( empty( $token ) ) {
			return $this->decide_block( $context, 0, array( 'token_missing' ), 'block', 0 );
		}

		$verification = $this->verify_token( $token, $context );
		if ( empty( $verification['valid'] ) ) {
			return $this->decide_block( $context, 0, array( $verification['reason'] ), 'block', 0 );
		}

		$score = (int) $verification['payload']['score'];
		$context['token_jti'] = sanitize_text_field( $verification['payload']['jti'] );

		if ( $score < (int) $settings['block_score_below'] ) {
			return $this->decide_block( $context, $score, array( 'score_below_block' ), 'block', 0 );
		}

		if ( $score < (int) $settings['quarantine_score_below'] ) {
			return $this->decide_block( $context, $score, array( 'score_below_quarantine' ), 'quarantine', 0 );
		}

		// Allowed entry. Borderline scores are stored normally but must not
		// trigger paid notifications (SMS/Telegram/webhook/email) later in
		// this same request — the notification gate reads this assessment.
		$reasons       = array( 'token_valid' );
		$suppress_paid = $score < (int) $settings['min_score_paid_notification'];
		if ( $suppress_paid ) {
			$reasons[] = 'paid_notifications_suppressed';
		}
		if ( $score < (int) $settings['min_score_submit'] ) {
			$reasons[] = 'below_min_score_submit';
		}
		$this->shield->set_request_assessment( $score, $suppress_paid, $reasons );

		if ( 'monitor' === $settings['mode'] ) {
			$this->shield->log_event( $context, 'monitor', $score, $reasons );
			return $result;
		}

		$this->shield->log_event( $context, 'allow', $score, $reasons );
		return $result;
	}

	private function protected_action_for_route( $route ) {
		$route = (string) $route;
		if ( '/Emsfb/v1/forms/message/add' === $route ) {
			return 'submit';
		}
		if ( '/Emsfb/v1/forms/response/get' === $route ) {
			$settings = $this->shield->get_settings();
			return ! empty( $settings['protect_response_lookup'] ) ? 'response_get' : false;
		}
		if ( '/Emsfb/v1/forms/response/add' === $route ) {
			return 'response_add';
		}
		if ( '/Emsfb/v1/forms/file/upload' === $route ) {
			return 'file_upload';
		}
		if ( 0 === strpos( $route, '/Emsfb/v1/forms/payment/' ) ) {
			return 'payment';
		}

		return false;
	}

	private function build_context( \WP_REST_Request $request, $route, $action ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$form_id = 0;
		foreach ( array( 'id', 'form_id', 'fid' ) as $key ) {
			if ( isset( $params[ $key ] ) ) {
				$form_id = absint( $params[ $key ] );
				break;
			}
		}
		if ( ! $form_id ) {
			// Core front-end sends the form id in a `form-id` header on every
			// EFB REST call; use it when the body has no usable id.
			$form_id = absint( (string) $request->get_header( 'form_id' ) );
		}

		$sid = isset( $params['sid'] ) ? sanitize_text_field( $params['sid'] ) : $request->get_header( 'sid' );
		$track = isset( $params['track'] ) ? sanitize_text_field( $params['track'] ) : '';

		return array(
			'route'        => sanitize_text_field( $route ),
			'action'       => sanitize_key( $action ),
			'form_id'      => $form_id,
			'sid'          => sanitize_text_field( (string) $sid ),
			'track'        => $track,
			'ip'           => $this->shield->get_client_ip(),
			'ua'           => $this->shield->current_user_agent(),
			'payload_hash' => $this->payload_hash( $params ),
		);
	}

	private function payload_hash( $params ) {
		unset( $params['valid'], $params['captcha'], $params['token'] );
		$json = $this->shield->json_encode( $params );
		return $this->shield->hash_value( false === $json ? '' : $json, 'payload' );
	}

	private function decide_block( $context, $score, $reasons, $decision, $retry_after ) {
		$settings = $this->shield->get_settings();
		if ( 'monitor' === $settings['mode'] ) {
			$this->shield->log_event( $context, 'monitor', $score, $reasons );
			return null;
		}

		if ( 'quarantine' === $decision && 'strict' !== $settings['mode'] ) {
			$this->shield->log_event( $context, 'quarantine', $score, $reasons );
			return $this->soft_fail_response( esc_html__( 'Your request looked unusual. Please wait a moment and try again.', 'easy-form-builder' ), 'efb_human_shield_quarantine' );
		}

		$this->shield->log_event( $context, 'block', $score, $reasons );
		$message = $this->user_message_for_reasons( $reasons );

		// Soft block: HTTP 200 with success=false so the standard EFB
		// front-end shows the real message instead of a generic network
		// error. Strict mode keeps hard HTTP statuses for WAF/CDN visibility.
		if ( 'strict' !== $settings['mode'] ) {
			return $this->soft_fail_response( $message, 'efb_human_shield_blocked', $retry_after );
		}

		return $this->blocked_response( $message, $retry_after ? 429 : 403, $score, $reasons, $retry_after );
	}

	/**
	 * Users never see which rule failed, only what they can do about it.
	 */
	private function user_message_for_reasons( $reasons ) {
		$reasons = (array) $reasons;

		foreach ( $reasons as $reason ) {
			if ( 0 === strpos( (string) $reason, 'rate_limited' ) ) {
				return esc_html__( 'Too many requests. Please try again shortly.', 'easy-form-builder' );
			}
		}

		$token_reasons = array( 'token_missing', 'token_expired', 'token_replayed', 'token_malformed', 'token_bad_signature', 'token_payload_invalid', 'token_base64_invalid', 'base64_missing' );
		foreach ( $reasons as $reason ) {
			if ( in_array( (string) $reason, $token_reasons, true ) ) {
				return esc_html__( 'The form was open for too long or could not be verified. Please refresh the page and submit again.', 'easy-form-builder' );
			}
		}

		return esc_html__( 'Your request looked too fast or unusual. Please try again in a few minutes.', 'easy-form-builder' );
	}

	private function soft_fail_response( $message, $code, $retry_after = 0 ) {
		return $this->json_response(
			array(
				'success' => false,
				'data'    => array(
					'success' => false,
					'm'       => $message,
					'code'    => $code,
				),
			),
			200,
			$retry_after > 0 ? array( 'Retry-After' => (string) (int) $retry_after ) : array()
		);
	}

	private function blocked_response( $message, $status, $score, $reasons, $retry_after = 0 ) {
		// Which rule failed stays in the admin log only; the visitor just gets
		// an actionable message.
		return $this->json_response(
			array(
				'success' => false,
				'data'    => array(
					'success' => false,
					'm'       => esc_html( $message ),
					'code'    => 'efb_human_shield_blocked',
				),
			),
			$status,
			$retry_after > 0 ? array( 'Retry-After' => (string) $retry_after ) : array()
		);
	}

	private function sign_payload( $payload ) {
		$json = $this->shield->json_encode( $payload );
		if ( false === $json || ! emsfb_is_php_function_available_efb( 'base64_encode' ) || ! emsfb_is_php_function_available_efb( 'hash_hmac' ) ) {
			return false;
		}

		$body = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' );
		$sig  = hash_hmac( 'sha256', $body, $this->shield->get_secret() );
		return $body . '.' . $sig;
	}

	private function verify_token( $token, $context ) {
		if ( ! is_string( $token ) || false === strpos( $token, '.' ) ) {
			return array( 'valid' => false, 'reason' => 'token_malformed' );
		}

		list( $body, $sig ) = explode( '.', $token, 2 );
		if ( ! emsfb_is_php_function_available_efb( 'hash_hmac' ) || ! $this->shield->hash_equals_safe( hash_hmac( 'sha256', $body, $this->shield->get_secret() ), $sig ) ) {
			return array( 'valid' => false, 'reason' => 'token_bad_signature' );
		}

		if ( ! emsfb_is_php_function_available_efb( 'base64_decode' ) ) {
			return array( 'valid' => false, 'reason' => 'base64_missing' );
		}

		$base64 = strtr( $body, '-_', '+/' );
		$base64 .= str_repeat( '=', ( 4 - strlen( $base64 ) % 4 ) % 4 );
		$decoded_body = base64_decode( $base64, true );
		if ( false === $decoded_body ) {
			return array( 'valid' => false, 'reason' => 'token_base64_invalid' );
		}
		$payload = $this->shield->json_decode_assoc( $decoded_body );
		if ( empty( $payload ) ) {
			return array( 'valid' => false, 'reason' => 'token_payload_invalid' );
		}

		if ( empty( $payload['exp'] ) || (int) $payload['exp'] < time() ) {
			return array( 'valid' => false, 'reason' => 'token_expired' );
		}

		if ( (int) $payload['fid'] !== (int) $context['form_id'] ) {
			return array( 'valid' => false, 'reason' => 'token_form_mismatch' );
		}

		if ( (string) $payload['route'] !== (string) $context['route'] ) {
			return array( 'valid' => false, 'reason' => 'token_route_mismatch' );
		}

		$sid_hash = ! empty( $context['sid'] ) ? $this->shield->hash_value( $context['sid'], 'sid' ) : '';
		if ( ! empty( $payload['sid_hash'] ) && ! $this->shield->hash_equals_safe( (string) $payload['sid_hash'], $sid_hash ) ) {
			return array( 'valid' => false, 'reason' => 'token_session_mismatch' );
		}

		$ip_prefix_hash = $this->shield->hash_value( $this->shield->get_ip_prefix( $context['ip'] ), 'ip_prefix' );
		if ( ! $this->shield->hash_equals_safe( (string) $payload['ip_prefix_hash'], $ip_prefix_hash ) ) {
			return array( 'valid' => false, 'reason' => 'token_ip_mismatch' );
		}

		if ( ! $this->mark_token_used( $payload ) ) {
			return array( 'valid' => false, 'reason' => 'token_replayed' );
		}

		return array( 'valid' => true, 'payload' => $payload );
	}

	private function get_challenge( $challenge_id ) {
		if ( ! $this->shield->tables_ready() ) {
			return false;
		}

		return $this->shield->db->get_row(
			$this->shield->db->prepare(
				'SELECT * FROM ' . $this->shield->table_name( 'challenges' ) . ' WHERE challenge_id = %s LIMIT 1',
				$challenge_id
			),
			ARRAY_A
		);
	}

	private function mark_token_used( $payload ) {
		if ( ! $this->shield->tables_ready() ) {
			return false;
		}

		$updated = $this->shield->db->query(
			$this->shield->db->prepare(
				'UPDATE ' . $this->shield->table_name( 'challenges' ) . ' SET used = 1 WHERE challenge_id = %s AND token_jti = %s AND used = 0 AND expires_at >= %d',
				$payload['cid'],
				$payload['jti'],
				time()
			)
		);

		return $updated > 0;
	}

	private function json_response( $data, $status = 200, $headers = array() ) {
		$response = new \WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow' );

		foreach ( $headers as $name => $value ) {
			$response->header( $name, $value );
		}

		return $response;
	}
}
