<?php
/**
 * Plugin Name: EFB e2e F06 helpers (local test site only)
 * Description: For tests/test-advdate-submission-http.php and the e2e scenario "f06": (1) the plan the
 *              calendar rules follow (efb_calendar_plan_rank) from the option efb_e2e_f06_plan, because a
 *              Pro plan without a licence falls back to Free Plus on this site; (2) a stand-in of the
 *              future Booking add-on on the documented hooks: efb_date_availability says "full" for the
 *              dates in efb_e2e_f06_full, efb_date_before_commit takes one place per date (dates in
 *              efb_e2e_f06_taken are refused), efb_date_after_commit / efb_date_commit_failed are
 *              recorded in efb_e2e_f06_log. Copied by `ctl.php f06-mu`.
 */
add_filter(
	'efb_calendar_plan_rank',
	static function ( $rank ) {
		$p = get_option( 'efb_e2e_f06_plan', '' );
		return '' === $p ? $rank : (int) $p;
	}
);
add_filter(
	'efb_date_availability',
	static function ( $status, $iso ) {
		$full = (array) get_option( 'efb_e2e_f06_full', array() );
		return in_array( $iso, $full, true ) ? array( 'available' => false, 'code' => 'full' ) : $status;
	},
	10,
	2
);
add_filter(
	'efb_date_before_commit',
	static function ( $ok, $rows, $form_id ) {
		if ( ! get_option( 'efb_e2e_f06_booking' ) ) {
			return $ok;
		}
		$taken = (array) get_option( 'efb_e2e_f06_taken', array() );
		foreach ( $rows as $r ) {
			$d = explode( 'T', (string) $r['value'] )[0];
			if ( in_array( $d, $taken, true ) ) {
				return new WP_Error( 'full', 'This date was just booked by someone else.', array( 'field_id' => $r['id_'] ) );
			}
		}
		foreach ( $rows as $r ) {
			$taken[] = explode( 'T', (string) $r['value'] )[0];
		}
		update_option( 'efb_e2e_f06_taken', array_values( array_unique( $taken ) ), false );
		return $ok;
	},
	10,
	3
);
add_action(
	'efb_date_after_commit',
	static function ( $rows, $form_id, $track ) {
		$log   = (array) get_option( 'efb_e2e_f06_log', array() );
		$log[] = array( 'after', (int) $form_id, (string) $track, count( $rows ) );
		update_option( 'efb_e2e_f06_log', array_slice( $log, -20 ), false );
	},
	10,
	3
);
add_action(
	'efb_date_commit_failed',
	static function ( $rows, $form_id ) {
		$log   = (array) get_option( 'efb_e2e_f06_log', array() );
		$log[] = array( 'failed', (int) $form_id, '', count( $rows ) );
		update_option( 'efb_e2e_f06_log', array_slice( $log, -20 ), false );
	},
	10,
	2
);
