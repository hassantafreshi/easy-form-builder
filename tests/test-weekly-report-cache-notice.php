<?php
/**
 * Regression test: the caching warning is never part of the weekly report.
 *
 * The cache exclusion notice travels only with the email delivery test report
 * (the first-run guide and the manual test in settings), which the tester
 * service writes. An administrator must not be told about their caching plugin
 * every week, so this file pins down:
 *
 *   A. build_weekly_report_html() - no notice, no guide link and no notice
 *      styling, even on a site that runs a page caching plugin, whatever
 *      sections that week asked for.
 *   B. Emsfb::cache_plugins_names() - the list sent with the delivery test
 *      names page caching plugins only; Jetpack and asset optimizers sit in the
 *      stored list for other reasons and must not trigger the warning.
 *
 * Every option and filter touched is restored, including on failure.
 *
 * Run: php tests/test-weekly-report-cache-notice.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

require_once $wp_load;

if ( ! class_exists( 'Emsfb' ) ) {
	echo "[SKIP] The Easy Form Builder main class could not be loaded.\n";
	exit( 0 );
}

if ( ! class_exists( 'Emsfb\\Email_Monitor' ) ) {
	require_once dirname( __DIR__ ) . '/includes/class-Emsfb-email-monitor.php';
}

if ( ! class_exists( 'Emsfb\\Email_Monitor' ) ) {
	echo "[SKIP] The Easy Form Builder email monitor class could not be loaded.\n";
	exit( 0 );
}

$passed = 0;
$failed = 0;

function efb_weekly_cache_check( $label, $condition, $extra = '' ) {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "[PASS] {$label}\n";
	} else {
		$failed++;
		echo "[FAIL] {$label}" . ( '' !== $extra ? " — {$extra}" : '' ) . "\n";
	}
}

// --- restore the world, whatever happens -------------------------------

$original_option = get_option( 'emsfb_cache_plugins', 0 );
$original_pro    = get_option( 'emsfb_pro', 2 );

function efb_weekly_cache_restore( $cache_value, $pro_value ) {
	remove_all_filters( 'emsfb_cache_plugins_slug' );
	remove_all_actions( 'emsfb_update_cache_plugins_list' );
	update_option( 'emsfb_cache_plugins', $cache_value );
	update_option( 'emsfb_pro', $pro_value );
}

register_shutdown_function( 'efb_weekly_cache_restore', $original_option, $original_pro );

/**
 * Store a known caching list and keep the refresh from overwriting it.
 *
 * @param array $plugins Rows of name/version/slug.
 * @return void
 */
function efb_weekly_cache_store( $plugins ) {
	remove_all_actions( 'emsfb_update_cache_plugins_list' );
	update_option( 'emsfb_cache_plugins', $plugins ? wp_json_encode( $plugins ) : 0 );
}

$page_cache = array( 'name' => 'LiteSpeed Cache', 'version' => '7.0', 'slug' => 'litespeed-cache' );
$jetpack    = array( 'name' => 'Jetpack', 'version' => '14.0', 'slug' => 'jetpack' );
$optimizer  = array( 'name' => 'Autoptimize', 'version' => '3.1', 'slug' => 'autoptimize' );

$monitor_reflection = new ReflectionClass( 'Emsfb\\Email_Monitor' );
$report_method      = $monitor_reflection->getMethod( 'build_weekly_report_html' );
$report_method->setAccessible( true );

efb_weekly_cache_check(
	'the weekly report has no cache notice builder any more',
	! $monitor_reflection->hasMethod( 'build_cache_notice_block' )
);

update_option( 'emsfb_pro', 2 );

$result = array(
	'score'           => 85,
	'grade'           => 'A',
	'can_send_email'  => true,
	'send_stage'      => 'handed_off',
	'recommendations' => array(),
);

// --- A. the weekly report never warns ------------------------------------

efb_weekly_cache_store( array( $page_cache ) );

$section_sets = array(
	'both sections'  => array( 'delivery' => true, 'activity' => true ),
	'delivery only'  => array( 'delivery' => true, 'activity' => false ),
	'activity only'  => array( 'delivery' => false, 'activity' => true ),
);

foreach ( $section_sets as $label => $sections ) {
	$report = $report_method->invoke( null, $sections, true, 'The test email arrived.', $result );
	efb_weekly_cache_check(
		"weekly report ({$label}) carries no cache notice with a page cache active",
		false === strpos( $report, 'Using a caching plugin' )
			&& false === strpos( $report, 'exclude-easy-form-builder-froms-cache' )
			&& false === strpos( $report, 'LiteSpeed Cache' )
			&& false === strpos( $report, '#fffbeb' )
	);
	efb_weekly_cache_check(
		"weekly report ({$label}) keeps its own content",
		false !== strpos( $report, '</div>' ) && strlen( $report ) > 500
	);
}

// --- B. the list sent with the delivery test -----------------------------

efb_weekly_cache_store( array( $page_cache, $jetpack, $optimizer ) );
efb_weekly_cache_check(
	'page caching plugins are named',
	'LiteSpeed Cache' === \Emsfb::cache_plugins_names(),
	var_export( \Emsfb::cache_plugins_names(), true )
);

efb_weekly_cache_store( array( $jetpack, $optimizer ) );
efb_weekly_cache_check(
	'Jetpack and asset optimizers alone send nothing',
	'' === \Emsfb::cache_plugins_names(),
	var_export( \Emsfb::cache_plugins_names(), true )
);

efb_weekly_cache_store( array() );
efb_weekly_cache_check( 'no caching plugin sends nothing', '' === \Emsfb::cache_plugins_names() );

// --- restore ------------------------------------------------------------

efb_weekly_cache_restore( $original_option, $original_pro );

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
