<?php
/**
 * Future side-effect guard for EFB Human Shield.
 *
 * Core integration can call the efb_shield_allow_side_effect filter before
 * SMS, Telegram, email, webhooks, or any paid service.
 *
 * @package Easy_Form_Builder
 * @subpackage Human_Shield
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Emsfb_Human_Shield_Notification_Gate {
	/** @var Emsfb_Human_Shield */
	private $shield;

	public function __construct( Emsfb_Human_Shield $shield ) {
		$this->shield = $shield;
		add_filter( 'efb_shield_allow_side_effect', array( $this, 'allow_side_effect' ), 10, 2 );
	}

	public function allow_side_effect( $allowed, $context ) {
		if ( ! $allowed || ! $this->shield->is_enabled() ) {
			return $allowed;
		}

		$context  = is_array( $context ) ? $context : array();
		$channel  = isset( $context['channel'] ) ? sanitize_key( $context['channel'] ) : 'unknown';
		$form_id  = isset( $context['form_id'] ) ? absint( $context['form_id'] ) : 0;
		$route    = 'side_effect_' . $channel;
		$settings = $this->shield->get_settings();
		$monitor  = ( 'monitor' === $settings['mode'] );

		// 1. Score-based suppression: the REST guard scored this request earlier
		// in the same PHP request. Low-scoring entries are stored but must not
		// spend money on notifications.
		$assessment = $this->shield->get_request_assessment();
		if ( is_array( $assessment ) && ! empty( $assessment['suppress_paid'] ) ) {
			$this->log_suppression( $route, $form_id, $channel, $monitor, (int) $assessment['score'], array( 'paid_suppressed_low_score' ) );
			if ( ! $monitor ) {
				return false;
			}
		}

		// 2. Global daily stop-loss per channel: one shared bucket no matter
		// who the recipient is, so many different recipients cannot drain the
		// budget past the configured ceiling.
		$daily_limit = 0;
		if ( 'sms' === $channel ) {
			$daily_limit = (int) $settings['sms_daily_stop_loss'];
		} elseif ( 'telegram' === $channel ) {
			$daily_limit = (int) $settings['telegram_daily_stop_loss'];
		} else {
			// email, webhook, googlesheet and future channels share one bucket.
			$daily_limit = (int) $settings['webhook_daily_stop_loss'];
		}

		if ( $daily_limit > 0 ) {
			$rate = $this->shield->rate_limiter->consume( 'side_effect_daily_' . $channel, $route, 0, 'global', $daily_limit, DAY_IN_SECONDS );
			if ( empty( $rate['allowed'] ) ) {
				$this->log_suppression( $route, $form_id, $channel, $monitor, 0, array( 'side_effect_daily_stop_loss' ) );
				if ( ! $monitor ) {
					return false;
				}
			}
		}

		// 3. Per-recipient daily cap: a single phone number / chat id /
		// address cannot absorb the whole channel budget.
		$recipient_cap = (int) $settings['recipient_daily_cap'];
		if ( $recipient_cap > 0 ) {
			$rate = $this->shield->rate_limiter->consume( 'side_effect_recipient_' . $channel, $route, $form_id, $this->recipient_key( $context ), $recipient_cap, DAY_IN_SECONDS );
			if ( empty( $rate['allowed'] ) ) {
				$this->log_suppression( $route, $form_id, $channel, $monitor, 0, array( 'side_effect_recipient_cap' ) );
				if ( ! $monitor ) {
					return false;
				}
			}
		}

		return true;
	}

	private function log_suppression( $route, $form_id, $channel, $monitor, $score, $reasons ) {
		$event_context = array(
			'route'           => $route,
			'action'          => 'side_effect',
			'form_id'         => $form_id,
			'ip'              => $this->shield->get_client_ip(),
			'ua'              => $this->shield->current_user_agent(),
			'cost_channel'    => $channel,
			'cost_suppressed' => $monitor ? 0 : 1,
		);
		$this->shield->log_event( $event_context, $monitor ? 'monitor' : 'block', $score, $reasons );
	}

	private function recipient_key( $context ) {
		$parts = array();
		if ( ! empty( $context['recipients'] ) ) {
			$parts[] = $this->shield->json_encode( $context['recipients'] );
		}
		if ( ! empty( $context['tracking_code'] ) ) {
			$parts[] = (string) $context['tracking_code'];
		}
		if ( empty( $parts ) ) {
			$parts[] = $this->shield->get_client_ip();
		}

		return $this->shield->hash_value( implode( '|', $parts ), 'recipient' );
	}
}
