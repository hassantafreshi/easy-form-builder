<?php
/**
 * WordPress smoke test for conditional-logic asset loading.
 * Run: php tests/test-addon-render-wordpress.php
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit( 0 );
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once $wp_load;

global $wpdb;
$table = $wpdb->prefix . 'emsfb_form';
$rows = $wpdb->get_results(
	"SELECT form_id, form_structer FROM {$table} ORDER BY form_id DESC LIMIT 200",
	ARRAY_A
);

$logic_form_id = 0;
$plain_form_id = 0;

foreach ( $rows as $row ) {
	$structure = (string) $row['form_structer'];
	$has_logic = false !== strpos( $structure, 'logic_rules' )
		|| false !== strpos( $structure, '\"logic\":\"1\"' )
		|| false !== strpos( $structure, '"logic":"1"' );

	if ( $has_logic && ! $logic_form_id ) {
		$logic_form_id = (int) $row['form_id'];
	}
	if ( ! $has_logic && ! $plain_form_id ) {
		$plain_form_id = (int) $row['form_id'];
	}
}

$failed = 0;

function efb_render_test_result( $name, $passed ) {
	global $failed;
	echo ( $passed ? '[PASS] ' : '[FAIL] ' ) . $name . "\n";
	if ( ! $passed ) {
		$failed++;
	}
}

if ( $plain_form_id ) {
	$html = do_shortcode( '[EMS_Form_Builder id=' . $plain_form_id . ']' );
	efb_render_test_result( 'plain form renders without PHP errors', ! preg_match( '/Fatal error|Parse error|Warning:/i', $html ) );
	efb_render_test_result( 'plain form does not enqueue conditional runtime', ! wp_script_is( 'efb-conditional-logic-public', 'enqueued' ) );
} else {
	echo "[SKIP] No plain form was found\n";
}

if ( $logic_form_id ) {
	wp_dequeue_script( 'efb-conditional-logic-public' );
	$html = do_shortcode( '[EMS_Form_Builder id=' . $logic_form_id . ']' );
	efb_render_test_result( 'logic form renders without PHP errors', ! preg_match( '/Fatal error|Parse error|Warning:/i', $html ) );
	efb_render_test_result( 'logic form enqueues conditional runtime', wp_script_is( 'efb-conditional-logic-public', 'enqueued' ) );
} else {
	echo "[SKIP] No conditional-logic form was found\n";
}

echo 'plain_form_id=' . $plain_form_id . "\n";
echo 'logic_form_id=' . $logic_form_id . "\n";

exit( $failed > 0 ? 1 : 0 );
