<?php
/**
 * Regression test: the Telegram "Clear activity log" admin flow.
 *
 * ajax_clear_telegram_activity_efb() builds its statement with
 *   $wpdb->prepare( "DELETE FROM `%1s` WHERE 1=1", $table_name )
 * The "%1s" spelling is the legacy unquoted-identifier placeholder: wpdb::prepare renders it
 * as a bare name (DELETE FROM `wp_..._sent_list`), whereas a plain %s would quote it into
 * `'wp_..._sent_list'` and make MySQL reject the statement. That is easy to "tidy up" into a
 * break, so the rendering is pinned here alongside an end-to-end run of the handler.
 *
 * The test also covers the second half of the same admin surface: class-Emsfb-telegram.php and
 * telegram-new-efb.php are loaded under separate file_exists() guards, so a partial install can
 * register the admin AJAX actions while $GLOBALS['emsfb_telegram_sender'] is never created.
 * send_telegram_admin_efb() then calls a method on null.
 *
 * Every query runs against a throwaway table (the telegram prefix is rewritten to a temporary
 * one), no option is written, and no HTTP request is made.
 *
 * Run: php tests/test-telegram-activity-log.php
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
function efb_tg_log_assert( $name, $actual, $expected ) {
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
$tmp_prefix  = $wpdb->prefix . 'efblog' . getmypid() . '_telegram_';
$table       = $tmp_prefix . 'sent_list';

add_filter( 'query', function ( $query ) use ( $real_prefix, $tmp_prefix ) {
	return str_replace( $real_prefix, $tmp_prefix, $query );
} );

function efb_tg_log_drop() {
	global $wpdb, $tmp_prefix;
	$wpdb->query( 'DROP TABLE IF EXISTS `' . $tmp_prefix . 'sent_list`' );
}
register_shutdown_function( 'efb_tg_log_drop' );
efb_tg_log_drop();

// A table name is an identifier, not a value. This is what makes the handler's statement valid.
efb_tg_log_assert(
	'the table-name placeholder renders as a bare identifier',
	$wpdb->prepare( 'DELETE FROM `%1s` WHERE 1=1', 'wp_demo_tbl' ),
	'DELETE FROM `wp_demo_tbl` WHERE 1=1'
);

$charset_collate = $wpdb->get_charset_collate();
$wpdb->query(
	"CREATE TABLE `{$table}` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`chat_id` varchar(255) NOT NULL,
		`message` text NOT NULL,
		`status` varchar(20) NOT NULL DEFAULT 'sent',
		`date` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		`form_id` int(11) NOT NULL DEFAULT 0,
		`by` varchar(100) DEFAULT 'System',
		PRIMARY KEY (`id`)
	) {$charset_collate}"
);
for ( $i = 1; $i <= 3; $i++ ) {
	$wpdb->insert( $table, array(
		'chat_id' => '1234' . $i,
		'message' => 'row ' . $i,
		'status'  => 'sent',
		'form_id' => 0,
		'by'      => 'Test',
	) );
}
efb_tg_log_assert( 'fixture: three rows in the activity table', (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ), 3 );

$addon = EMSFB_PLUGIN_DIRECTORY . 'vendor/telegram/';
require_once $addon . 'class-Emsfb-telegram.php';

// An administrator is needed for the handler's permission check.
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( empty( $admins ) ) {
	echo "[SKIP] no administrator account on this site; handler assertions skipped\n";
} else {
	wp_set_current_user( (int) $admins[0] );
	$_REQUEST['nonce'] = wp_create_nonce( 'telegram_efb_actions' );
	$_POST['nonce']    = $_REQUEST['nonce'];

	// wp_send_json_*() ends in wp_die(). wp_doing_ajax() is false on the CLI, so the default
	// handler would print the response and exit before anything could be asserted; declare the
	// AJAX context and make both handlers throw instead.
	if ( ! defined( 'DOING_AJAX' ) ) {
		define( 'DOING_AJAX', true );
	}
	$thrower = function () {
		return function () { throw new Exception( 'wp_die' ); };
	};
	add_filter( 'wp_die_ajax_handler', $thrower );
	add_filter( 'wp_die_handler', $thrower );

	// Built without the constructor: no hooks registered, no options written, no tables created.
	$reflection = new ReflectionClass( '\\Emsfb\\telegramlistefb' );
	$list       = $reflection->newInstanceWithoutConstructor();

	ob_start();
	try {
		$list->ajax_clear_telegram_activity_efb();
	} catch ( Exception $e ) {
		// wp_die from wp_send_json_*
	}
	$json = ob_get_clean();

	$response = json_decode( $json, true );
	efb_tg_log_assert( 'clearing the activity log reports success', isset( $response['success'] ) ? $response['success'] : null, true );
	efb_tg_log_assert( 'clearing the activity log removes every row', (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ), 0 );
}

// A partial install (admin class present, sender file absent) must not fatal.
$child = tempnam( sys_get_temp_dir(), 'efbtg' ) . '.php';
file_put_contents( $child, '<?php
$wp_load = ' . var_export( $wp_load, true ) . ';
define( "DISABLE_WP_CRON", true );
require_once $wp_load;
add_filter( "pre_http_request", function () { return new WP_Error( "blocked", "no traffic" ); }, 1 );
require_once ' . var_export( $addon . 'class-Emsfb-telegram.php', true ) . ';
unset( $GLOBALS["emsfb_telegram_sender"] );
if ( ! defined( "DOING_AJAX" ) ) { define( "DOING_AJAX", true ); }
$thrower = function () { return function () { throw new Exception( "wp_die" ); }; };
add_filter( "wp_die_ajax_handler", $thrower );
add_filter( "wp_die_handler", $thrower );
$r = new ReflectionClass( "\\\\Emsfb\\\\telegramlistefb" );
$o = $r->newInstanceWithoutConstructor();
ob_start();
try {
	$o->send_telegram_admin_efb( array( "chat_id" => "12345", "msg" => "hello", "bot_token" => "123:abc" ) );
} catch ( Exception $e ) {
}
$out = ob_get_clean();
echo "CHILD_OK " . $out . "\n";
' );
$child_output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $child ) . ' 2>&1' );
@unlink( $child );
efb_tg_log_assert(
	'a missing sender is reported instead of fataling',
	(bool) ( $child_output && false !== strpos( $child_output, 'CHILD_OK' ) && false === stripos( $child_output, 'Fatal error' ) ),
	true
);

efb_tg_log_drop();
efb_tg_log_assert( 'temporary table removed', (string) $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ), '' );

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
