<?php
/**
 * Tests for the five-star invitation.
 *
 * Covers includes/class-Emsfb-review-request.php: who gets asked and when, the
 * state machine behind "later" / "never" / a low rating, the coupon claim, and
 * the two promises the wording makes - that the discount figure is never
 * baked into a sentence, and that the stylesheets carry no direction-specific
 * overrides.
 *
 * Every option this test touches is restored at the end, pass or fail. It never
 * leaves a plan, an install date or a review state behind.
 *
 * Run: C:\xampp\php\php.exe tests/test-review-request.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

// The whole feature lives behind is_admin(), which is nothing but the WP_ADMIN
// constant. Without this the class constructor returns before registering a
// single hook and half of what follows would be testing an inert object.
define( 'WP_ADMIN', true );
define( 'WP_USE_THEMES', false );
require_once $wp_load;

// set_current_screen() ships with the admin, which wp-load does not pull in.
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$pass     = 0;
$fail     = 0;
$failures = array();

/**
 * Assert one condition.
 *
 * @param string $name  Test name.
 * @param bool   $cond  Result.
 * @param string $extra Context printed when it fails.
 * @return bool
 */
function efb_t( $name, $cond, $extra = '' ) {
	global $pass, $fail, $failures;

	if ( $cond ) {
		$pass++;
		echo "  [PASS] {$name}\n";
		return true;
	}

	$fail++;
	$failures[] = $name . ( '' !== $extra ? ' -- ' . $extra : '' );
	echo "  [FAIL] {$name}" . ( '' !== $extra ? " -- {$extra}" : '' ) . "\n";
	return false;
}

$plugin_dir = dirname( __DIR__ );

require_once $plugin_dir . '/includes/class-Emsfb-review-request.php';

/* ---------------------------------------------------------------------
 * Save everything this file is about to change.
 * ------------------------------------------------------------------ */

$restore = array(
	'emsfb_pro'          => get_option( 'emsfb_pro', null ),
	'emsfb_install_date' => get_option( 'emsfb_install_date', null ),
	'emsfb_review_state' => get_option( 'emsfb_review_state', null ),
);

/**
 * Put every option back exactly as it was found.
 *
 * @return void
 */
function efb_restore_options() {
	global $restore;

	foreach ( $restore as $key => $value ) {
		if ( null === $value ) {
			delete_option( $key );
		} else {
			update_option( $key, $value, false );
		}
	}
}

register_shutdown_function( 'efb_restore_options' );

$request = new \Emsfb\Review_Request();

/* ---------------------------------------------------------------------
 * A. Who gets asked
 * ------------------------------------------------------------------ */

echo "\nA. Plan gate\n";

update_option( 'emsfb_pro', 1, false );
efb_t( 'A1 Pro (1) is never asked', false === $request->plan_is_eligible_efb() );

update_option( 'emsfb_pro', 0, false );
efb_t( 'A2 onboarding (0) is never asked', false === $request->plan_is_eligible_efb() );

update_option( 'emsfb_pro', 2, false );
efb_t( 'A3 Free (2) is eligible', true === $request->plan_is_eligible_efb() );

update_option( 'emsfb_pro', 3, false );
efb_t( 'A4 Free Plus (3) is eligible', true === $request->plan_is_eligible_efb() );

/* ---------------------------------------------------------------------
 * B. The two-week clock
 * ------------------------------------------------------------------ */

echo "\nB. Days in use\n";

update_option( 'emsfb_install_date', time() - ( 3 * DAY_IN_SECONDS ), false );
efb_t( 'B1 three days ago reads as 3', 3 === $request->days_in_use_efb(), 'got ' . $request->days_in_use_efb() );

update_option( 'emsfb_install_date', time() - ( 20 * DAY_IN_SECONDS ), false );
efb_t( 'B2 twenty days ago reads as 20', 20 === $request->days_in_use_efb(), 'got ' . $request->days_in_use_efb() );

// The option has been a datetime string on some installs, never a timestamp.
update_option( 'emsfb_install_date', gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) ), false );
efb_t( 'B3 a datetime string is read too', 30 === $request->days_in_use_efb(), 'got ' . $request->days_in_use_efb() );

update_option( 'emsfb_install_date', 'not a date', false );
efb_t( 'B4 an unreadable value reads as 0, not a crash', 0 === $request->days_in_use_efb() );

/* ---------------------------------------------------------------------
 * C. Seeding the install date
 * ------------------------------------------------------------------ */

echo "\nC. Install date seeding\n";

delete_option( 'emsfb_install_date' );
$request->seed_install_date_efb();
$seeded = get_option( 'emsfb_install_date' );
efb_t( 'C1 a missing install date is seeded', ! empty( $seeded ) && is_numeric( $seeded ) );

// Seeding must be idempotent: a second call cannot move the clock forward.
$first = (int) get_option( 'emsfb_install_date' );
update_option( 'emsfb_install_date', $first - ( 100 * DAY_IN_SECONDS ), false );
$request->seed_install_date_efb();
efb_t(
	'C2 seeding never overwrites a date already stored',
	(int) get_option( 'emsfb_install_date' ) === $first - ( 100 * DAY_IN_SECONDS )
);

// A site with forms was not installed today. Only meaningful where forms exist.
global $wpdb;
$forms_table = $wpdb->prefix . 'emsfb_form';
$has_forms   = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $forms_table ) ) === $forms_table )
	&& (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$forms_table}" ) > 0;

if ( $has_forms ) {
	delete_option( 'emsfb_install_date' );
	$request->seed_install_date_efb();
	$oldest_form = $wpdb->get_var( "SELECT MIN(form_create_date) FROM {$forms_table}" );
	efb_t(
		'C3 an existing site is backdated to its oldest form, not to today',
		(int) get_option( 'emsfb_install_date' ) <= time() - HOUR_IN_SECONDS,
		'oldest form: ' . $oldest_form . ', seeded: ' . gmdate( 'Y-m-d H:i:s', (int) get_option( 'emsfb_install_date' ) )
	);
} else {
	echo "  [SKIP] C3 backdating - this install has no forms to date from\n";
}

/* ---------------------------------------------------------------------
 * D. should_ask_efb()
 * ------------------------------------------------------------------ */

echo "\nD. The decision to ask\n";

// The gate needs a user who could act on the answer.
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! empty( $admins ) ) {
	wp_set_current_user( (int) $admins[0] );
}

update_option( 'emsfb_pro', 2, false );
delete_option( 'emsfb_review_state' );

update_option( 'emsfb_install_date', time() - ( 5 * DAY_IN_SECONDS ), false );
efb_t( 'D1 five days in, nobody is asked', false === $request->should_ask_efb() );

// The day before the gate and the day it opens, so a change to MIN_DAYS
// fails here rather than only in K4.
update_option( 'emsfb_install_date', time() - ( 6 * DAY_IN_SECONDS ), false );
efb_t( 'D2 six days in, still nobody is asked', false === $request->should_ask_efb() );

update_option( 'emsfb_install_date', time() - ( 7 * DAY_IN_SECONDS ), false );
efb_t( 'D3 at seven days a free site is asked', true === $request->should_ask_efb() );

update_option( 'emsfb_pro', 1, false );
efb_t( 'D4 a Pro site at seven days is still not asked', false === $request->should_ask_efb() );
update_option( 'emsfb_pro', 2, false );

// The filter is the documented escape hatch for a site that wants silence.
add_filter( 'emsfb_review_should_ask_efb', '__return_false' );
efb_t( 'D5 emsfb_review_should_ask_efb can veto the ask', false === $request->should_ask_efb() );
remove_filter( 'emsfb_review_should_ask_efb', '__return_false' );

/* ---------------------------------------------------------------------
 * E. The state machine
 * ------------------------------------------------------------------ */

echo "\nE. Answers are remembered\n";

update_option( 'emsfb_review_state', array( 'status' => 'dismissed' ), false );
efb_t( 'E1 "do not ask again" is permanent', false === $request->should_ask_efb() );

update_option( 'emsfb_review_state', array( 'status' => 'rated' ), false );
efb_t( 'E2 a site that already rated is not asked again', false === $request->should_ask_efb() );

update_option(
	'emsfb_review_state',
	array( 'status' => 'snoozed', 'snooze_until' => time() + ( 10 * DAY_IN_SECONDS ) ),
	false
);
efb_t( 'E3 a live snooze suppresses the ask', false === $request->should_ask_efb() );

update_option(
	'emsfb_review_state',
	array( 'status' => 'snoozed', 'snooze_until' => time() - HOUR_IN_SECONDS ),
	false
);
efb_t( 'E4 an expired snooze lets the ask through again', true === $request->should_ask_efb() );

$state = $request->state_efb();
efb_t(
	'E5 state_efb() fills in every key it promises',
	isset( $state['status'], $state['snooze_until'], $state['shown'], $state['rating'], $state['rated_at'], $state['username'], $state['outcome'] )
);

/* ---------------------------------------------------------------------
 * F. The discount is a variable, never a sentence
 * ------------------------------------------------------------------ */

echo "\nF. The %s discount\n";

efb_t( 'F1 the default offer is 64%', false !== strpos( $request->discount_label_efb(), '64' ), $request->discount_label_efb() );

add_filter( 'emsfb_review_discount_percent_efb', function () { return 40; } );
efb_t( 'F2 the filter changes the figure', false !== strpos( $request->discount_label_efb(), '40' ), 'got ' . $request->discount_label_efb() );
remove_all_filters( 'emsfb_review_discount_percent_efb' );

add_filter( 'emsfb_review_discount_percent_efb', function () { return 999; } );
efb_t( 'F3 an out-of-range figure is clamped to 100', false !== strpos( $request->discount_label_efb(), '100' ) );
remove_all_filters( 'emsfb_review_discount_percent_efb' );

// The point of the whole exercise: no translated string may carry the number.
$strings   = $request->strings_efb();
$must_have = array( 'ribbonPct', 'praiseLead', 'ocLowStarsLead' );
$missing   = array();
foreach ( $must_have as $key ) {
	if ( ! isset( $strings[ $key ] ) || false === strpos( $strings[ $key ], '%s' ) ) {
		$missing[] = $key;
	}
}
efb_t( 'F4 every discount sentence carries %s', empty( $missing ), 'without a placeholder: ' . implode( ', ', $missing ) );

$hardcoded = array();
foreach ( $strings as $key => $value ) {
	if ( is_string( $value ) && preg_match( '/(100\s*%|١٠٠|۱۰۰)/u', $value ) ) {
		$hardcoded[] = $key;
	}
}
efb_t( 'F5 no string hard-codes the figure', empty( $hardcoded ), 'hard-coded in: ' . implode( ', ', $hardcoded ) );

/* ---------------------------------------------------------------------
 * H. Direction. The stylesheets must work in both without an override.
 * ------------------------------------------------------------------ */

echo "\nH. LTR and RTL\n";

$sheets = array(
	'modal-system-efb.css'   => $plugin_dir . '/includes/admin/assets/css/modal-system-efb.css',
	'review-request-efb.css' => $plugin_dir . '/includes/admin/assets/css/review-request-efb.css',
);

/**
 * A stylesheet with its comments removed.
 *
 * These files explain in prose why they avoid [dir="rtl"] blocks and sibling
 * selectors, and a scanner that reads the prose finds the very strings the
 * rules were written to avoid. Only declarations are evidence.
 *
 * @param string $path Stylesheet path.
 * @return string
 */
function efb_css_rules( $path ) {
	$css = is_readable( $path ) ? (string) file_get_contents( $path ) : '';

	return (string) preg_replace( '#/\*.*?\*/#s', '', $css );
}

foreach ( $sheets as $label => $path ) {
	$css = efb_css_rules( $path );

	efb_t( "H1 {$label} exists", '' !== trim( $css ) );

	efb_t(
		"H2 {$label} has no [dir] or .rtl override block",
		! preg_match( '/\[dir\s*=|(^|[\s,])(html|body)?\.rtl[\s{,]/mi', $css ),
		'a direction-specific block means the base rules were not direction-neutral'
	);

	// The physical properties that actually break in RTL. padding/margin
	// shorthands are fine - they are symmetrical or already logical.
	$physical = array();
	foreach ( array( 'margin-left', 'margin-right', 'padding-left', 'padding-right', 'border-left', 'border-right', 'left:', 'right:' ) as $prop ) {
		if ( preg_match( '/(^|[\s;{])' . preg_quote( $prop, '/' ) . '/mi', $css ) ) {
			$physical[] = $prop;
		}
	}
	efb_t(
		"H3 {$label} uses logical properties only",
		empty( $physical ),
		'physical properties found: ' . implode( ', ', $physical )
	);
}

// The star row is the one thing a sibling selector would fill backwards.
$review_js = (string) file_get_contents( $plugin_dir . '/includes/admin/assets/js/review-request-efb.js' );
efb_t(
	'H4 stars are lit from the script, not by a CSS sibling selector',
	false !== strpos( $review_js, 'is-lit' )
		&& ! preg_match( '/star[^{]*~[^{]*star/i', efb_css_rules( $sheets['review-request-efb.css'] ) )
);

/* ---------------------------------------------------------------------
 * I. The markup the script drives
 * ------------------------------------------------------------------ */

echo "\nI. Markup contract\n";

update_option( 'emsfb_pro', 2, false );
update_option( 'emsfb_install_date', time() - ( 30 * DAY_IN_SECONDS ), false );
delete_option( 'emsfb_review_state' );

// render_modal_efb() screen-checks through get_current_screen(), which does not
// exist outside a real admin request, so the markup is exercised by calling the
// renderer with the screen check satisfied by a filter on the ask itself.
$markup = '';
if ( function_exists( 'set_current_screen' ) ) {
	set_current_screen( 'toplevel_page_Emsfb' );

	ob_start();
	$request->render_modal_efb();
	$markup = (string) ob_get_clean();
}

if ( '' !== $markup ) {
	$hooks = array(
		'data-efb-review-step="ask"',
		'data-efb-review-step="praise"',
		'data-efb-review-step="claim"',
		'data-efb-review-step="checking"',
		'data-efb-review-step="result"',
		'data-efb-review-step="feedback"',
		'data-efb-review-step="sent"',
		'data-efb-review-action="later"',
		'data-efb-review-action="never"',
		'data-efb-review-action="review"',
		'data-efb-review-action="toClaim"',
		'data-efb-review-action="getCode"',
		'data-efb-review-action="send"',
		'data-efb-review-rate="5"',
		'data-efb-review-username',
		'data-efb-review-email',
		'data-efb-review-comment',
	);

	$absent = array();
	foreach ( $hooks as $hook ) {
		if ( false === strpos( $markup, $hook ) ) {
			$absent[] = $hook;
		}
	}
	efb_t( 'I1 every hook the script binds to is in the markup', empty( $absent ), 'missing: ' . implode( ', ', $absent ) );

	efb_t( 'I2 the dialog uses the shared design system classes', false !== strpos( $markup, 'efb-dlg__shell' ) && false !== strpos( $markup, 'efb-dlg__foot' ) );
	efb_t( 'I3 the review link opens WordPress.org', false !== strpos( $markup, 'wordpress.org/support/plugin/easy-form-builder/reviews' ) );
	efb_t( 'I4 the reward line renders a figure, not a literal %s', false === strpos( $markup, '%s' ) );
	efb_t( 'I5 the dialog starts hidden', preg_match( '/id="efb-review-modal"[^>]*\shidden/', $markup ) === 1 );
	efb_t( 'I6 external links carry rel="noopener"', substr_count( $markup, 'rel="noopener noreferrer"' ) >= 2 );
} else {
	echo "  [SKIP] I1-I6 markup - set_current_screen() is unavailable in this context\n";
}

/* ---------------------------------------------------------------------
 * J. Showing it three times becomes a snooze
 * ------------------------------------------------------------------ */

echo "\nJ. The modal stops asking on its own\n";

if ( '' !== $markup ) {
	delete_option( 'emsfb_review_state' );

	ob_start();
	$request->render_modal_efb();
	ob_end_clean();

	$state = $request->state_efb();
	efb_t( 'J1 one sighting spends the ask', 'snoozed' === $state['status'], 'status: ' . $state['status'] );
	efb_t( 'J2 a seen site is not asked again on the next page load', false === $request->should_ask_efb() );

	// The whole politeness policy: two days between sightings, and only for
	// somebody who walked away without answering - rating it or picking "Do
	// not ask again" ends the asking outright, which E1 and E2 cover.
	$gap_days = (int) round( ( (int) $state['snooze_until'] - time() ) / DAY_IN_SECONDS );
	efb_t( 'J3 the next sighting is two days away', 2 === $gap_days, $gap_days . ' days' );

	// A preview must never spend a real site's turn.
	delete_option( 'emsfb_review_state' );
	$_GET[ \Emsfb\Review_Request::PREVIEW_ARG ] = '1';

	ob_start();
	$request->render_modal_efb();
	$preview_markup = (string) ob_get_clean();

	efb_t( 'J4 a preview renders the invitation', false !== strpos( $preview_markup, 'efb-review-modal' ) );
	efb_t( 'J5 a preview records nothing', 'pending' === $request->state_efb()['status'], 'status: ' . $request->state_efb()['status'] );
	efb_t( 'J6 a preview leaves the site still due to be asked', true === $request->should_ask_efb() );

	// Preview lifts the plan and the clock, but never the capability check.
	update_option( 'emsfb_pro', 1, false );
	update_option( 'emsfb_install_date', time() - HOUR_IN_SECONDS, false );
	efb_t( 'J7 a preview opens even on a Pro site that is one hour old', true === $request->should_ask_efb() );

	unset( $_GET[ \Emsfb\Review_Request::PREVIEW_ARG ] );
	efb_t( 'J8 without the argument that same site is not asked', false === $request->should_ask_efb() );

	update_option( 'emsfb_pro', 2, false );
	update_option( 'emsfb_install_date', time() - ( 30 * DAY_IN_SECONDS ), false );
} else {
	echo "  [SKIP] J1-J8 - markup could not be rendered in this context\n";
}

/* ---------------------------------------------------------------------
 * K. The AJAX door
 * ------------------------------------------------------------------ */

echo "\nK. AJAX\n";

efb_t( 'K1 the AJAX action is registered', has_action( 'wp_ajax_emsfb_review_request' ) !== false );

$reflect = new ReflectionClass( '\Emsfb\Review_Request' );
efb_t( 'K2 the nonce action and the AJAX action are the same name', 'emsfb_review_request' === $reflect->getConstant( 'ACTION' ) );
efb_t( 'K3 four stars and up take the review route', 4 === $reflect->getConstant( 'REWARD_THRESHOLD' ) );
efb_t( 'K4 the wait is seven days', 7 === $reflect->getConstant( 'MIN_DAYS' ) );
efb_t( 'K5 the gap between sightings is two days', 2 === $reflect->getConstant( 'SNOOZE_DAYS' ) );

// The claim path leans on the deactivation survey's signed pipeline. If that
// method stops being public, the coupon silently never issues.
efb_t(
	'K6 the signed-report pipeline is reachable from here',
	class_exists( '\Emsfb\Deactivation_Feedback' )
		? ( new ReflectionMethod( '\Emsfb\Deactivation_Feedback', 'send_report_efb' ) )->isPublic()
		: true
);

/* ---------------------------------------------------------------------
 * Done
 * ------------------------------------------------------------------ */

efb_restore_options();

echo "\n----------------------------------------\n";
echo "  Passed: {$pass}\n";
echo "  Failed: {$fail}\n";

if ( $fail > 0 ) {
	echo "\nFailures:\n";
	foreach ( $failures as $failure ) {
		echo "  - {$failure}\n";
	}
}

echo "----------------------------------------\n";

exit( $fail > 0 ? 1 : 0 );
