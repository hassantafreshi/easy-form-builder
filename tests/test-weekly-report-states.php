<?php
/**
 * Weekly administrator report: every state of the delivery section.
 *
 * Renders the REAL builder (Email_Monitor::build_weekly_report_html) through
 * reflection for each outcome the monitor can produce, and asserts what the
 * administrator ends up reading:
 *
 *   healthy   score >= 70 and sending confirmed -> no lecture, no SMTP button
 *   low       score <  70                        -> SMTP guidance + guide link
 *   no score  nothing was measured               -> "run the email check" + link
 *   failed    sending itself failed              -> guidance, never "healthy"
 *
 * Also covers the dashboard notice gating and the body-theme customisation.
 *
 * Run: php tests/test-weekly-report-states.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}
define( 'WP_USE_THEMES', false );
require_once $wp_load;

if ( ! class_exists( '\Emsfb\Email_Monitor' ) ) {
	echo "[SKIP] Email_Monitor is not loaded\n";
	exit( 0 );
}

$pass = 0;
$fail = 0;
function t( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) { $pass++; echo "[PASS] {$label}\n"; }
	else { $fail++; echo "[FAIL] {$label}\n"; }
	if ( '' !== $detail ) { echo "       {$detail}\n"; }
}

$ref = new ReflectionMethod( '\Emsfb\Email_Monitor', 'build_weekly_report_html' );
$ref->setAccessible( true );
$render = function ( $can_send, $message, $result ) use ( $ref ) {
	return (string) $ref->invoke( null, [ 'delivery' => true, 'activity' => true ], $can_send, $message, $result );
};

$guide_url = \Emsfb\Email_Monitor::get_smtp_guide_url();
echo "SMTP guide URL for locale '" . get_locale() . "': {$guide_url}\n\n";

// ---------------------------------------------------------------- healthy
echo "--- STATE: healthy (score 96) ---\n";
$healthy = $render( true, 'The weekly email delivery test completed successfully.', [
	'score' => 96, 'grade' => 'A',
	'authentication' => [ 'spf' => 'pass', 'dkim' => 'pass', 'dmarc' => 'pass' ],
] );
t( 'H1 shows the score', false !== strpos( $healthy, '96' ) );
t( 'H2 reads as healthy', false !== strpos( $healthy, 'healthy' ) || false !== strpos( $healthy, 'سالم' ) );
t( 'H3 does NOT nag about SMTP', false === strpos( $healthy, $guide_url ),
	'a working site must not be told to reconfigure its email' );
t( 'H4 renders pass chips', substr_count( $healthy, '&#10003;' ) >= 3, 'tick marks: ' . substr_count( $healthy, '&#10003;' ) );

// ---------------------------------------------------------------- low score
echo "\n--- STATE: low score (48) ---\n";
$low = $render( true, 'Delivery problems were detected.', [
	'score' => 48, 'grade' => 'D',
	'authentication' => [ 'spf' => 'pass', 'dkim' => 'fail', 'dmarc' => 'none' ],
	'recommendations' => [ 'No DKIM record was found for your domain.', 'SpamAssassin score: 5.2' ],
] );
t( 'L1 shows the score', false !== strpos( $low, '48' ) );
t( 'L2 warns about spam', false !== strpos( $low, 'spam' ) || false !== strpos( $low, 'اسپم' ) );
t( 'L3 includes the SMTP guide link', false !== strpos( $low, $guide_url ), $guide_url );
t( 'L4 includes the numbered steps', false !== strpos( $low, 'SPF' ) && false !== strpos( $low, 'DKIM' ) );
t( 'L5 shows a failing chip', false !== strpos( $low, '&#10007;' ) );
t( 'L6 lists the service recommendations', false !== strpos( $low, 'No DKIM record was found' ) );

// ---------------------------------------------------------------- no score
echo "\n--- STATE: no score at all ---\n";
$none = $render( true, '', [] );
t( 'N1 does not invent a score', false === strpos( $none, 'out of 100' ) );
t( 'N2 asks the admin to run the check',
	false !== strpos( $none, 'Run the email check' ) || false !== strpos( $none, 'reaching people' ),
	'must tell the user what to do next' );
t( 'N3 includes the guide link', false !== strpos( $none, $guide_url ) );
t( 'N4 never claims delivery is healthy', false === strpos( $none, 'is healthy' ) );
t( 'N5 never claims delivery is broken', false === strpos( $none, 'going to spam' ) );

// ---------------------------------------------------------------- send failed
echo "\n--- STATE: sending failed outright ---\n";
$failed = $render( false, 'The weekly email delivery test found an email delivery problem.', [] );
t( 'F1 does not claim success', false === strpos( $failed, 'is healthy' ) );
t( 'F2 gives guidance + link', false !== strpos( $failed, $guide_url ) );

// A high score must not whitewash a failed send.
$conflict = $render( false, 'Delivery failed.', [ 'score' => 95, 'grade' => 'A' ] );
t( 'F3 a failed send outweighs a high score',
	false === strpos( $conflict, 'is healthy' ) && false !== strpos( $conflict, $guide_url ),
	'can_send=false must still warn even when the score looks good' );

// ---------------------------------------------------------------- theme
echo "\n--- Body theme follows the admin's email colours ---\n";
$theme_ref = new ReflectionMethod( '\Emsfb\Email_Monitor', 'get_body_theme' );
$theme_ref->setAccessible( true );
$theme = $theme_ref->invoke( null );
t( 'T1 returns a usable accent colour', ! empty( $theme['accent'] ) && $theme['accent'][0] === '#', wp_json_encode( $theme ) );

$colour_ref = new ReflectionMethod( '\Emsfb\Email_Monitor', 'sanitize_css_colour' );
$colour_ref->setAccessible( true );
t( 'T2 accepts a valid hex', '#ff8800' === $colour_ref->invoke( null, '#ff8800', '#000000' ) );
t( 'T3 rejects a style-breaking value',
	'#000000' === $colour_ref->invoke( null, '#fff;}</style><script>alert(1)</script>', '#000000' ),
	'returned: ' . $colour_ref->invoke( null, '#fff;}</style><script>alert(1)</script>', '#000000' ) );

$font_ref = new ReflectionMethod( '\Emsfb\Email_Monitor', 'sanitize_css_font' );
$font_ref->setAccessible( true );
t( 'T4 rejects a font stack containing CSS punctuation',
	false === strpos( $font_ref->invoke( null, 'Tahoma;}body{display:none' ), 'display:none' ) );

// The report must never emit an unescaped brace-and-tag payload.
$evil = $render( true, '<script>alert(1)</script>', [ 'score' => 40, 'grade' => '<b>X</b>',
	'recommendations' => [ '<img src=x onerror=alert(1)>' ] ] );
t( 'T5 escapes hostile service output', false === strpos( $evil, '<script>alert(1)</script>' )
	&& false === strpos( $evil, '<img src=x onerror' ) );

// ---------------------------------------------------------------- notice
// ---------------------------------------------------------------- clients
echo "\n--- Gmail / Outlook compatibility and responsiveness ---\n";
$sample = $low; // the richest state: hero, chips, guidance button, tiles

// This fragment is injected into whatever template the site saved, so there is
// nowhere dependable to put a <style> block - Gmail strips it in several
// contexts anyway. Any class-based responsiveness would therefore be dead.
t( 'C1 no styling depends on a CSS class', false === strpos( $sample, 'class="' ),
	'a class in an injected fragment has no stylesheet backing it' );
t( 'C2 no media query is relied on', false === strpos( $sample, '@media' ) );

// Stacking without CSS: inline-block + max-width, with an Outlook ghost table.
t( 'C3 tiles use the fluid-hybrid pattern',
	false !== strpos( $sample, 'display:inline-block' ) && false !== strpos( $sample, 'max-width:262px' ) );
t( 'C4 Outlook gets a ghost table for the tiles',
	false !== strpos( $sample, '<!--[if mso]><table' ) && false !== strpos( $sample, '<!--[if mso]><td width="50%"' ) );
t( 'C5 conditional comments are balanced',
	substr_count( $sample, '<!--[if mso]>' ) === substr_count( $sample, '<![endif]-->' ) - substr_count( $sample, '<!--[if !mso]><!-->' ),
	'mso: ' . substr_count( $sample, '<!--[if mso]>' )
	. ', endif: ' . substr_count( $sample, '<![endif]-->' )
	. ', !mso: ' . substr_count( $sample, '<!--[if !mso]><!-->' ) );

// Outlook's Word engine ignores padding on an inline <a>; it must sit on the td.
t( 'C6 the guide button pads the cell, not the link',
	false !== strpos( $sample, 'bgcolor="#b45309" style="padding:12px 22px;' )
	&& false === strpos( $sample, 'style="display:inline-block;padding:12px 22px;color:#ffffff' ),
	'padding on an inline <a> is dropped by Outlook' );

// Word drops border-radius, so the score circle needs a VML twin.
t( 'C7 the score badge has a VML fallback for Outlook',
	false !== strpos( $sample, '<v:oval' ) && false !== strpos( $sample, 'urn:schemas-microsoft-com:vml' ) );
t( 'C8 the badge does not rely on display:block spans',
	false === strpos( $sample, '<span style="display:block' ),
	'Outlook ignores display:block on a span, collapsing both lines onto one' );

// Outlook adds its own spacing around tables unless these are zeroed.
$tables = substr_count( $sample, '<table' );
$resets = substr_count( $sample, 'mso-table-lspace:0pt' );
t( 'C9 every table carries the Outlook spacing reset', $resets >= $tables - 1,
	"{$resets} reset(s) for {$tables} table(s)" );

// A stack Word cannot parse makes it fall back to Times New Roman.
$font_ref2 = new ReflectionMethod( '\Emsfb\Email_Monitor', 'sanitize_css_font' );
$font_ref2->setAccessible( true );
t( 'C10 a custom font always ends in a family Outlook resolves',
	false !== stripos( $font_ref2->invoke( null, 'Vazirmatn' ), 'sans-serif' ),
	'got: ' . $font_ref2->invoke( null, 'Vazirmatn' ) );
t( 'C11 an existing generic family is left alone',
	'Georgia, serif' === $font_ref2->invoke( null, 'Georgia, serif' ),
	'got: ' . $font_ref2->invoke( null, 'Georgia, serif' ) );

// Gmail clips a message past ~102KB; the whole report must stay far below it.
t( 'C12 the report is nowhere near Gmail\'s clipping threshold', strlen( $sample ) < 60000,
	number_format( strlen( $sample ) ) . ' bytes' );

// RTL must survive an LTR template.
$rtl_probe = $sample;
t( 'C13 direction is set on the containers', false !== strpos( $rtl_probe, 'dir="' ) );

echo "\n--- Dashboard notice gating ---\n";
// __CLASS__ has no leading backslash, and WordPress builds the callback id from
// the class-name string verbatim - so the hook must be looked up in that form.
t( 'D1 notice callback is registered',
	false !== has_action( 'admin_notices', [ 'Emsfb\Email_Monitor', 'render_delivery_failure_notice' ] ),
	'priority: ' . var_export( has_action( 'admin_notices', [ 'Emsfb\Email_Monitor', 'render_delivery_failure_notice' ] ), true ) );

$snapshot = get_option( 'emsfb_email_monitor_last_status', null );

$capture = function () {
	ob_start();
	\Emsfb\Email_Monitor::render_delivery_failure_notice();
	return (string) ob_get_clean();
};

wp_set_current_user( (int) ( get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] )[0] ?? 0 ) );

update_option( 'emsfb_email_monitor_last_status', [
	'state' => 'failed', 'message' => 'x', 'context' => 'weekly',
	'checked_at' => current_time( 'mysql' ), 'can_send_email' => false,
], false );
delete_user_meta( get_current_user_id(), 'emsfb_delivery_notice_dismissed' );
$shown = $capture();
t( 'D2 shows when delivery failed', false !== strpos( $shown, 'not being delivered' ) );
t( 'D3 the notice carries the guide link', false !== strpos( $shown, $guide_url ) );

update_option( 'emsfb_email_monitor_last_status', [
	'state' => 'ok', 'message' => 'x', 'context' => 'weekly',
	'checked_at' => current_time( 'mysql' ), 'can_send_email' => true,
], false );
t( 'D4 stays silent when delivery works', '' === trim( $capture() ) );

update_option( 'emsfb_email_monitor_last_status', [
	'state' => 'pending', 'message' => 'x', 'context' => 'weekly',
	'checked_at' => current_time( 'mysql' ), 'can_send_email' => false,
], false );
t( 'D5 stays silent while a test is still running', '' === trim( $capture() ) );

if ( null === $snapshot ) { delete_option( 'emsfb_email_monitor_last_status' ); }
else { update_option( 'emsfb_email_monitor_last_status', $snapshot, false ); }
delete_user_meta( get_current_user_id(), 'emsfb_delivery_notice_dismissed' );

echo "\n========================================\n";
echo "RESULTS: {$pass} passed, {$fail} failed\n";
exit( $fail > 0 ? 1 : 0 );
