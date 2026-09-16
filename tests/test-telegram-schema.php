<?php
/**
 * Regression test: the Telegram activity table must have the columns the add-on writes.
 *
 * wp_emsfb_telegram_sent_list is created by two classes. In wp-admin, class-Emsfb-telegram.php
 * (telegramlistefb) runs first with a narrow schema, and telegram-new-efb.php's wider schema
 * cannot arrive afterwards, because dbDelta never alters a table created with
 * "CREATE TABLE IF NOT EXISTS". Every notification INSERT then failed with
 * "Unknown column 'message_id' in 'field list'" and nothing was logged.
 *
 * The test runs both load orders against throwaway tables (every telegram query is rewritten to
 * a temporary prefix) and drops them again. No option is written and no HTTP request is made.
 *
 * Run: php tests/test-telegram-schema.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}
require_once $wp_load;
add_filter( 'pre_http_request', function () { return new WP_Error( 'blocked', 'no Telegram traffic in tests' ); }, 1 );

$passed = 0;
$failed = 0;
function efb_tg_schema_assert( $name, $actual, $expected ) {
	global $passed, $failed;
	if ( $actual === $expected ) {
		echo "[PASS] {$name}\n";
		$passed++;
		return;
	}
	echo "[FAIL] {$name}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
	$failed++;
}

global $wpdb;
$real_prefix = $wpdb->prefix . 'emsfb_telegram_';
$tmp_prefix  = $wpdb->prefix . 'efbtest' . getmypid() . '_telegram_';
$table       = $tmp_prefix . 'sent_list';

add_filter( 'query', function ( $query ) use ( $real_prefix, $tmp_prefix ) {
	return str_replace( $real_prefix, $tmp_prefix, $query );
} );
foreach ( array( 'emsfb_telegram_table_version', 'emsfb_telegram_adv_table_version' ) as $option ) {
	add_filter( "pre_option_{$option}", function () { return '0'; } );
}
add_filter( 'pre_option_emsfb_telegram_cap_added', function () { return 1; } );
add_filter( 'pre_option_Emsfb_telegram_efb', function () { return array( 'rest_api_status' => 1 ); } );
foreach ( array( 'emsfb_telegram_table_version', 'emsfb_telegram_adv_table_version', 'emsfb_addon_AdnTLG', 'Emsfb_telegram_efb' ) as $option ) {
	add_filter( "pre_update_option_{$option}", function ( $value, $old ) { return $old; }, 10, 2 );
	add_filter( "pre_add_option_{$option}", function () { return true; } );
}

function efb_tg_schema_drop() {
	global $wpdb, $tmp_prefix;
	foreach ( array( 'sent_list', 'contact', 'users' ) as $name ) {
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $tmp_prefix . $name . '`' );
	}
}
register_shutdown_function( 'efb_tg_schema_drop' );
efb_tg_schema_drop();

$addon = EMSFB_PLUGIN_DIRECTORY . 'vendor/telegram/';
// wp-admin order: class-Emsfb.php requires the admin class first (class-Emsfb.php ~411), then the sender.
require_once $addon . 'class-Emsfb-telegram.php';
new \Emsfb\telegramlistefb();
require_once $addon . 'telegram-new-efb.php';

$columns = array();
foreach ( (array) $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" ) as $column ) {
	$columns[] = $column->Field;
}
foreach ( array( 'message_id', 'error_message', 'phone_number' ) as $needed ) {
	efb_tg_schema_assert( "admin-first install has the {$needed} column", in_array( $needed, $columns, true ), true );
}

// Exactly what telegramsendefb::send_telegram_efb() writes for every notification.
$wpdb->suppress_errors( true );
$inserted = $wpdb->query( $wpdb->prepare(
	"INSERT INTO `{$table}` (chat_id, message, status, form_id, message_id, error_message) VALUES (%s, %s, %s, %d, %s, %s)",
	'12345', 'regression test', 'sent', 7, '99', null
) );
$insert_error = $wpdb->last_error;
$wpdb->suppress_errors( false );
efb_tg_schema_assert( 'a notification row can be logged', false !== $inserted, true );
efb_tg_schema_assert( 'no SQL error', $insert_error, '' );
efb_tg_schema_assert( 'the row is in the activity table', (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ), 1 );

// The business-notification path writes phone_number as well.
$wpdb->suppress_errors( true );
$inserted_phone = $wpdb->insert( $table, array(
	'chat_id'      => '12345',
	'message'      => 'business',
	'status'       => 'sent',
	'form_id'      => 7,
	'phone_number' => '+15551234567',
	'by'           => 'business_notification',
) );
$phone_error = $wpdb->last_error;
$wpdb->suppress_errors( false );
efb_tg_schema_assert( 'a business notification row can be logged', false !== $inserted_phone, true );
efb_tg_schema_assert( 'no SQL error for phone_number', $phone_error, '' );

// An install created before the fix must be repaired, so the version gate has to move on.
$sender_source = file_get_contents( $addon . 'telegram-new-efb.php' );
efb_tg_schema_assert(
	'the table version gate lets an existing install run the repair once',
	(bool) preg_match( "/get_option\(\s*'emsfb_telegram_adv_table_version',\s*'0'\s*\)\s*===\s*'1\.1'/", $sender_source ),
	true
);
efb_tg_schema_assert(
	'the repair adds the missing columns instead of relying on dbDelta',
	substr_count( $sender_source, 'ADD COLUMN' ),
	3
);

efb_tg_schema_drop();
efb_tg_schema_assert( 'temporary tables removed', (string) $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ), '' );

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
