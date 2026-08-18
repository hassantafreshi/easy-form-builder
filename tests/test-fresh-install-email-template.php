<?php
/**
 * Regression test for the default email template created on a fresh install.
 *
 * The installer writes plugin tables directly, so this uses a random, temporary
 * table prefix instead of touching the site's real Easy Form Builder data. All
 * options, the settings transient, scheduled events, caches, and temporary
 * tables are restored/removed even when an assertion fails.
 *
 * Run: php tests/test-fresh-install-email-template.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

require_once $wp_load;

if ( ! class_exists( 'Emsfb' ) || ! class_exists( '\\Emsfb\\Install' ) ) {
	echo "[SKIP] Easy Form Builder is not active in this WordPress installation.\n";
	exit( 0 );
}

$passed = 0;
$failed = 0;

function efb_fresh_install_check( $label, $condition ) {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "[PASS] {$label}\n";
		return;
	}

	$failed++;
	echo "[FAIL] {$label}\n";
}

/**
 * The default is both the server-side fallback and the source of the first
 * stored emailTemp value. Keep the expectations structural rather than tying
 * the test to incidental presentation markup.
 *
 * @param string $template Template HTML plus optional EFBDATA comment.
 * @return array{has_message:bool,has_builder_data:bool,builder_has_message_block:bool}
 */
function efb_fresh_install_template_shape( $template ) {
	$result = array(
		'has_message'               => is_string( $template ) && false !== strpos( $template, 'shortcode_message' ),
		'has_builder_data'          => false,
		'builder_has_message_block' => false,
	);

	if ( ! is_string( $template ) || ! preg_match( '/<!--\\s*EFBDATA:([^\\s]+)\\s*-->/', $template, $match ) ) {
		return $result;
	}

	$result['has_builder_data'] = true;
	$builder = json_decode( rawurldecode( $match[1] ), true );
	if ( ! is_array( $builder ) || empty( $builder['blocks'] ) || ! is_array( $builder['blocks'] ) ) {
		return $result;
	}

	foreach ( $builder['blocks'] as $block ) {
		if ( is_array( $block ) && isset( $block['type'] ) && 'message' === $block['type'] ) {
			$result['builder_has_message_block'] = true;
			break;
		}
	}

	return $result;
}

global $wpdb;

// A random, alphanumeric-only prefix makes every DROP target below explicit
// and keeps this integration test isolated from real plugin tables.
$token = function_exists( 'wp_generate_uuid4' )
	? str_replace( '-', '', wp_generate_uuid4() )
	: str_replace( '.', '', uniqid( 'efb', true ) );
$test_prefix      = strtolower( $wpdb->prefix . 'efb_fresh_tpl_' . substr( $token, 0, 12 ) . '_' );
$original_prefix  = $wpdb->prefix;
$settings_table   = $test_prefix . 'emsfb_setting';
$temporary_tables = array(
	$settings_table,
	$test_prefix . 'emsfb_form',
	$test_prefix . 'emsfb_msg_',
	$test_prefix . 'emsfb_rsp_',
	$test_prefix . 'emsfb_stts_',
);

if ( ! preg_match( '/^[a-z0-9_]+$/', $test_prefix ) ) {
	echo "[SKIP] Could not create a safe temporary table prefix.\n";
	exit( 0 );
}

// Install::install() and Email_Monitor::activate() touch only these options
// plus the cron option. Snapshot them before invoking the real installer.
$option_keys = array(
	'emsfb_settings',
	'Emsfb_db_version',
	'emsfb_onboarding_pending',
	'emsfb_onboarding_initial_install',
	'emsfb_onboarding_completed_at',
	'emsfb_weekly_email_report_enabled',
	'emsfb_email_stats_enabled',
	'emsfb_email_monitor_activation_marker',
	'emsfb_email_monitor_pending_test',
	'emsfb_email_monitor_last_update_version',
	'cron',
);
$missing_option = '__efb_fresh_install_missing_' . $token;
$options_before = array();
foreach ( $option_keys as $option_key ) {
	$value = get_option( $option_key, $missing_option );
	$options_before[ $option_key ] = array(
		'exists' => $value !== $missing_option,
		'value'  => $value,
	);
}
$transient_before = get_transient( 'emsfb_settings_transient' );

$restored = false;
$restore = function () use ( &$restored, $temporary_tables, $test_prefix, $original_prefix, $options_before, $transient_before, $wpdb ) {
	if ( $restored ) {
		return;
	}
	$restored = true;

	// Every table name was constructed from the random checked prefix above;
	// refuse to run a destructive query if that invariant ever changes.
	foreach ( $temporary_tables as $table ) {
		if ( 0 !== strpos( $table, $test_prefix ) || ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			continue;
		}
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- checked random test table name.
	}

	$wpdb->prefix = $original_prefix;
	foreach ( $options_before as $option_key => $snapshot ) {
		if ( $snapshot['exists'] ) {
			update_option( $option_key, $snapshot['value'], false );
		} else {
			delete_option( $option_key );
		}
	}

	if ( false === $transient_before ) {
		delete_transient( 'emsfb_settings_transient' );
	} else {
		set_transient( 'emsfb_settings_transient', $transient_before, 1800 );
	}

	Emsfb::get_setting_Emsfb( '_clear_cache' );
	foreach ( array( 'decoded', 'pub', 'raw', 'emsfb_settings' ) as $cache_key ) {
		wp_cache_delete( 'emsfb_settings' === $cache_key ? $cache_key : 'settings:' . $cache_key, 'emsfb' );
	}
};
register_shutdown_function( $restore );

try {
	// Do not let a value cached from the real settings table satisfy this test.
	Emsfb::get_setting_Emsfb( '_clear_cache' );
	foreach ( array( 'decoded', 'pub', 'raw', 'emsfb_settings' ) as $cache_key ) {
		wp_cache_delete( 'emsfb_settings' === $cache_key ? $cache_key : 'settings:' . $cache_key, 'emsfb' );
	}

	// Only the public prefix property changes: WordPress's users/options tables
	// stay pointed at the site, while every EFB table that Install creates uses
	// this disposable prefix.
	$wpdb->prefix = $test_prefix;

	$defaults = Emsfb::get_default_settings_efb();
	$default_template = is_object( $defaults ) && isset( $defaults->emailTemp ) ? $defaults->emailTemp : '';
	$default_shape = efb_fresh_install_template_shape( $default_template );
	efb_fresh_install_check(
		'the server-side default fallback contains shortcode_message',
		$default_shape['has_message']
	);
	efb_fresh_install_check(
		'the server-side default fallback preserves email-builder EFBDATA',
		$default_shape['has_builder_data'] && $default_shape['builder_has_message_block']
	);

	if ( ! class_exists( 'EmsfbEmailHandler' ) ) {
		require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-email-handler.php';
	}
	$handler = new EmsfbEmailHandler();
	$render_method = new ReflectionMethod( $handler, 'apply_custom_template' );
	$render_method->setAccessible( true );
	$rendered_template = $render_method->invoke(
		$handler,
		$default_template,
		'<strong>Sample message</strong>',
		'Sample title',
		'Sample site',
		'https://example.test',
		'admin@example.test',
		'',
		''
	);
	efb_fresh_install_check(
		'the default EFBDATA template renders its message through the email handler',
		is_string( $rendered_template )
			&& false !== strpos( $rendered_template, '<strong>Sample message</strong>' )
			&& false === strpos( $rendered_template, 'shortcode_message' )
	);
	efb_fresh_install_check(
		'the default EFBDATA template renders its dynamic title through the email handler',
		is_string( $rendered_template )
			&& false !== strpos( $rendered_template, 'Sample title' )
			&& false === strpos( $rendered_template, 'shortcode_title' )
	);

	\Emsfb\Install::install();

	$row = $wpdb->get_row( "SELECT id, setting FROM `{$settings_table}` ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- checked temporary table name.
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$settings_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- checked temporary table name.
	$raw = is_object( $row ) && isset( $row->setting ) ? (string) $row->setting : '';
	$stored = json_decode( $raw );
	$stored_template = is_object( $stored ) && isset( $stored->emailTemp ) ? $stored->emailTemp : '';
	$stored_shape = efb_fresh_install_template_shape( $stored_template );

	efb_fresh_install_check( 'a fresh install creates exactly one settings row', 1 === $count );
	efb_fresh_install_check(
		'the fresh settings row stores the default sample email template',
		is_string( $stored_template ) && $stored_template === $default_template
	);
	efb_fresh_install_check(
		'the stored template contains shortcode_message and a builder message block',
		$stored_shape['has_message'] && $stored_shape['has_builder_data'] && $stored_shape['builder_has_message_block']
	);

	// These two stores are read before a later database lookup in normal admin
	// and public requests, so both must receive the exact first-row JSON.
	efb_fresh_install_check(
		'fresh install seeds emsfb_settings with the settings-row JSON',
		get_option( 'emsfb_settings', null ) === $raw
	);
	efb_fresh_install_check(
		'fresh install seeds emsfb_settings_transient with the settings-row JSON',
		get_transient( 'emsfb_settings_transient' ) === $raw
	);

	// Exercise the database fallback deliberately: remove the transient and all
	// request/object-cache copies, then make the real reader rebuild them from
	// the new row.
	delete_transient( 'emsfb_settings_transient' );
	Emsfb::get_setting_Emsfb( '_clear_cache' );
	foreach ( array( 'decoded', 'pub', 'raw', 'emsfb_settings' ) as $cache_key ) {
		wp_cache_delete( 'emsfb_settings' === $cache_key ? $cache_key : 'settings:' . $cache_key, 'emsfb' );
	}
	$from_database = Emsfb::get_setting_Emsfb( 'decoded' );
	efb_fresh_install_check(
		'the settings reader falls back to the fresh database row with the template intact',
		is_object( $from_database )
			&& isset( $from_database->emailTemp )
			&& $from_database->emailTemp === $stored_template
	);
	efb_fresh_install_check(
		'the database fallback rehydrates the settings transient with the template intact',
		(function () use ( $stored_template ) {
			$rehydrated = json_decode( (string) get_transient( 'emsfb_settings_transient' ) );
			return is_object( $rehydrated )
				&& isset( $rehydrated->emailTemp )
				&& $rehydrated->emailTemp === $stored_template;
		})()
	);

	// Activation can run again on staging restores or updates. A row already
	// present must be left untouched and must not be duplicated.
	\Emsfb\Install::install();
	$raw_after_reactivation = (string) $wpdb->get_var( "SELECT setting FROM `{$settings_table}` ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- checked temporary table name.
	$count_after_reactivation = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$settings_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- checked temporary table name.
	efb_fresh_install_check(
		'reactivation keeps the first template row unchanged',
		1 === $count_after_reactivation && $raw_after_reactivation === $raw
	);
} finally {
	$restore();
}

echo "\n========================================\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";
echo "========================================\n";

exit( $failed > 0 ? 1 : 0 );
