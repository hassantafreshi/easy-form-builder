<?php
/**
 * Human-behavior detector for EFB Human Shield.
 *
 * @package Easy_Form_Builder
 * @subpackage Human_Shield
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Emsfb_Human_Shield_Detector {
	/** @var Emsfb_Human_Shield */
	private $shield;

	public function __construct( Emsfb_Human_Shield $shield ) {
		$this->shield = $shield;
	}

	public function score( $metrics, $context = array() ) {
		$settings = $this->shield->get_settings();
		$metrics  = is_array( $metrics ) ? $metrics : array();
		$score    = 0;
		$reasons  = array();
		$hard_fail = false;

		$duration_ms       = $this->int_metric( $metrics, 'durationMs' );
		$first_delay_ms    = $this->int_metric( $metrics, 'firstInteractionDelayMs' );
		$focus_count       = $this->int_metric( $metrics, 'focusCount' );
		$input_count       = $this->int_metric( $metrics, 'inputCount' );
		$key_count         = $this->int_metric( $metrics, 'keyCount' );
		$pointer_count     = $this->int_metric( $metrics, 'pointerMoveCount' );
		$pointer_distance  = $this->int_metric( $metrics, 'pointerDistance' );
		$touch_count       = $this->int_metric( $metrics, 'touchCount' );
		$click_count       = $this->int_metric( $metrics, 'clickCount' );
		$scroll_count      = $this->int_metric( $metrics, 'scrollCount' );
		$paste_count       = $this->int_metric( $metrics, 'pasteCount' );
		$fields_touched    = $this->int_metric( $metrics, 'fieldsTouched' );
		$field_count       = max( 1, $this->int_metric( $metrics, 'fieldCount' ) );
		$visibility_changes = $this->int_metric( $metrics, 'visibilityChanges' );

		$is_mobile = ! empty( $metrics['touchCapable'] );

		if ( ! empty( $metrics['honeypotFilled'] ) ) {
			$hard_fail = true;
			$score -= 50;
			$reasons[] = 'honeypot_filled';
		}

		if ( ! empty( $metrics['webdriver'] ) ) {
			$score -= 25;
			$reasons[] = 'webdriver_signal';
		}

		// Dynamic fill-time floor: bigger forms need more time. The client
		// reports the field count of the specific form; a lying bot only
		// lowers the floor back to the configured static minimum, and the
		// hard limits (rate limit, token, honeypot) do not depend on it.
		$form_field_count = $this->int_metric( $metrics, 'formFieldCount' );
		$min_fill_seconds = max( 1, (int) $settings['min_fill_time_seconds'] );
		if ( $form_field_count > 0 && $form_field_count <= 60 ) {
			$min_fill_seconds = max( $min_fill_seconds, min( 45, (int) ceil( $form_field_count * 1.2 ) ) );
		}

		$min_fill_ms = max( 1000, $min_fill_seconds * 1000 );
		if ( $duration_ms >= $min_fill_ms ) {
			$score += 20;
			$reasons[] = 'duration_ok';
		} else {
			$score -= 25;
			$reasons[] = 'too_fast';
		}

		if ( $first_delay_ms > 150 && $first_delay_ms < 120000 ) {
			$score += 8;
			$reasons[] = 'first_interaction_ok';
		}

		if ( $focus_count >= 1 && $input_count >= 1 ) {
			$score += 12;
			$reasons[] = 'input_focus_ok';
		} else {
			$score -= 15;
			$reasons[] = 'no_input_focus';
		}

		if ( $fields_touched > 0 ) {
			$coverage = min( 1, $fields_touched / max( 1, min( $field_count, 8 ) ) );
			$score += (int) round( $coverage * 12 );
			$reasons[] = 'field_coverage_' . (int) round( $coverage * 100 );
		}

		if ( $key_count >= 3 ) {
			$score += 12;
			$reasons[] = 'keyboard_cadence_present';
		} elseif ( $input_count > 0 && $is_mobile ) {
			$score += 7;
			$reasons[] = 'mobile_input_present';
		}

		if ( $paste_count > 0 && $input_count > 0 && $paste_count >= $input_count ) {
			$score -= 10;
			$reasons[] = 'paste_heavy';
		}

		if ( $is_mobile ) {
			if ( $touch_count >= 1 || $click_count >= 1 ) {
				$score += 15;
				$reasons[] = 'touch_activity_ok';
			} else {
				$score -= 10;
				$reasons[] = 'no_touch_activity';
			}
		} else {
			if ( $pointer_count >= 3 && $pointer_distance > 80 ) {
				$score += 15;
				$reasons[] = 'pointer_entropy_ok';
			} elseif ( $click_count >= 1 ) {
				$score += 8;
				$reasons[] = 'click_activity_ok';
			} else {
				$score -= 10;
				$reasons[] = 'no_pointer_activity';
			}
		}

		if ( $scroll_count > 0 ) {
			$score += 5;
			$reasons[] = 'scroll_seen';
		}

		if ( $visibility_changes > 8 ) {
			$score -= 5;
			$reasons[] = 'visibility_noisy';
		}

		if ( ! empty( $context['sid'] ) && ! empty( $context['form_id'] ) ) {
			$score += 8;
			$reasons[] = 'session_context_present';
		}

		if ( ! empty( $context['route'] ) ) {
			$score += 5;
			$reasons[] = 'route_context_present';
		}

		$score = max( 0, min( 100, $score ) );

		return array(
			'score'     => $score,
			'reasons'   => array_values( array_unique( $reasons ) ),
			'hard_fail' => $hard_fail,
		);
	}

	private function int_metric( $metrics, $key ) {
		if ( ! isset( $metrics[ $key ] ) ) {
			return 0;
		}

		return max( 0, absint( $metrics[ $key ] ) );
	}
}
