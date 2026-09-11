<?php
/**
 * Regression test for when first-run setup stops being pending.
 *
 * The guide exists to get one thing done: check that this site can deliver an
 * email. So the delivery test is what ends it. It used to end only on a
 * "Finish setup" click, which meant an admin who read the report and moved on -
 * or whose tab closed while the result was still being polled - was shown the
 * whole wizard again on their next admin page, with the work already done.
 *
 * Two halves:
 *   A. emsfb_complete_onboarding_efb() - the one writer, and its idempotence.
 *      A site that already finished keeps its original completion date rather
 *      than being restamped by every later test from General Settings.
 *   B. where start_email_tester_efb() calls it: after wp_mail() has actually
 *      been attempted, and before either of the two returns that follow. The
 *      earlier bail-outs mean the test never ran, so they must not end the
 *      guide. Checked against the source because reaching that code for real
 *      needs the remote tester service.
 *
 * Every option touched is restored, including on failure.
 *
 * Run: php tests/test-onboarding-completion.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

require_once $wp_load;

if ( ! function_exists( 'emsfb_onboarding_pending_efb' ) ) {
	echo "[SKIP] Easy Form Builder is not active in this WordPress installation.\n";
	exit( 0 );
}

$passed = 0;
$failed = 0;

function efb_onboarding_check( $label, $condition ) {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "[PASS] {$label}\n";
		return;
	}

	$failed++;
	echo "[FAIL] {$label}\n";
}

$restore = array();
foreach ( array( 'emsfb_onboarding_pending', 'emsfb_onboarding_initial_install', 'emsfb_onboarding_completed_at' ) as $option ) {
	$restore[ $option ] = get_option( $option, null );
}

try {
	// ------------------------------------------------------------------
	// A. The writer
	// ------------------------------------------------------------------
	update_option( 'emsfb_onboarding_initial_install', 1, false );
	update_option( 'emsfb_onboarding_pending', 1, false );
	delete_option( 'emsfb_onboarding_completed_at' );

	efb_onboarding_check(
		'A1 a fresh install starts with the guide pending',
		true === emsfb_onboarding_pending_efb()
	);

	$ended = emsfb_complete_onboarding_efb();
	$first_completed_at = get_option( 'emsfb_onboarding_completed_at', '' );

	efb_onboarding_check( 'A2 completing it reports that this call is what ended it', true === $ended );
	efb_onboarding_check( 'A3 the guide is no longer pending', false === emsfb_onboarding_pending_efb() );
	efb_onboarding_check( 'A4 the completion date is recorded', '' !== $first_completed_at );

	$ended_again = emsfb_complete_onboarding_efb();
	efb_onboarding_check( 'A5 a second call reports that it changed nothing', false === $ended_again );
	efb_onboarding_check(
		'A6 and leaves the original completion date alone',
		get_option( 'emsfb_onboarding_completed_at', '' ) === $first_completed_at
	);

	// An existing site that upgrades into this version has no install marker.
	// Nothing here may hand it one.
	update_option( 'emsfb_onboarding_pending', 1, false );
	delete_option( 'emsfb_onboarding_initial_install' );
	emsfb_complete_onboarding_efb();
	efb_onboarding_check(
		'A7 completing never marks a site as a first install',
		'' === (string) get_option( 'emsfb_onboarding_initial_install', '' )
	);

	// ------------------------------------------------------------------
	// B. Where the email tester calls it
	// ------------------------------------------------------------------
	$admin_source = file_get_contents( __DIR__ . '/../includes/admin/class-Emsfb-admin.php' );
	$start = strpos( $admin_source, 'private function start_email_tester_efb(' );
	$end = strpos( $admin_source, 'private function request_email_tester_start_efb(', $start );
	$body = false === $start || false === $end ? '' : substr( $admin_source, $start, $end - $start );

	efb_onboarding_check( 'B1 start_email_tester_efb() was found', '' !== $body );

	$completion = strpos( $body, 'emsfb_complete_onboarding_efb()' );
	$wp_mail = strpos( $body, '$sent = wp_mail(' );
	$mail_failed_return = strpos( $body, "'send_stage' => 'wp_mail_failed'" );
	$sent_return = strpos( $body, "'send_stage' => 'handed_off'" );

	efb_onboarding_check( 'B2 it ends the guide exactly once', 1 === substr_count( $body, 'emsfb_complete_onboarding_efb()' ) );
	efb_onboarding_check(
		'B3 only after wp_mail() has actually been attempted',
		false !== $completion && false !== $wp_mail && $completion > $wp_mail
	);
	efb_onboarding_check(
		'B4 a send WordPress refused still ends the guide',
		false !== $mail_failed_return && $completion < $mail_failed_return
	);
	efb_onboarding_check(
		'B5 and so does a send it accepted',
		false !== $sent_return && $completion < $sent_return
	);

	// The bail-outs above wp_mail() mean no test ran - an invalid address, a
	// tester service that would not start, a malformed reply.
	$before_mail = substr( $body, 0, $wp_mail );
	efb_onboarding_check(
		'B6 nothing that returns before the send ends the guide',
		false === strpos( $before_mail, 'emsfb_complete_onboarding_efb()' )
	);

	// The AJAX action behind the "Finish setup" button shares the same writer,
	// so the two paths cannot drift into two different definitions of done.
	efb_onboarding_check(
		'B7 the finish action uses the same writer',
		1 === preg_match(
			'/public function efb_complete_onboarding\(\).*?emsfb_complete_onboarding_efb\(\)/s',
			$admin_source
		)
	);
	efb_onboarding_check(
		'B8 and nothing else writes the pending flag by hand',
		0 === substr_count( $admin_source, "update_option('emsfb_onboarding_completed_at'" )
	);
} finally {
	foreach ( $restore as $option => $value ) {
		if ( null === $value ) {
			delete_option( $option );
			continue;
		}
		update_option( $option, $value, false );
	}
}

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed === 0 ? 0 : 1 );
