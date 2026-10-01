<?php
/**
 * Environment for tests/test-response-box-xss-browser.js.
 *
 * setup   - creates a form, posts one hostile submission through the real
 *           REST route (what the server stores after the 2026-09-23 fix),
 *           inserts one *legacy* row straight into the table with raw markup
 *           in every slot (what a site patched today already has in its
 *           database), and publishes a page carrying the code-finder shortcode.
 * teardown - removes all of it.
 *
 * Run: C:\xampp\php\php.exe tests/seed-response-box-xss-env.php setup|teardown
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) { echo json_encode( array( 'ok' => false, 'error' => 'wp-load not found' ) ); exit( 1 ); }
define( 'WP_USE_THEMES', false );
require_once $wp_load;

global $wpdb;
$mode   = isset( $argv[1] ) ? $argv[1] : '';
$marker = 'efb_respbox_xss_test_state';
$form_table = $wpdb->prefix . 'emsfb_form';
$msg_table  = $wpdb->prefix . 'emsfb_msg_';
$rsp_table  = $wpdb->prefix . 'emsfb_rsp_';
$stts_table = $wpdb->prefix . 'emsfb_stts_';
$IMG = '<img src=x onerror=alert(document.domain)>';

function xs_field( $id, $type, $extra = array() ) {
	return array_merge( array( 'id_' => $id, 'dataId' => $id . '-id', 'type' => $type, 'id' => '',
		'name' => 'Field ' . $id, 'required' => false, 'amount' => 2, 'step' => '1' ), $extra );
}

switch ( $mode ) {
	case 'setup':
		if ( get_option( $marker ) ) { echo json_encode( array_merge( array( 'ok' => true ), get_option( $marker ) ) ); break; }
		$fn = get_efbFunction();
		$wpdb->insert( $form_table, array(
			'form_name'        => 'efb-respbox-xss-probe',
			'form_structer'    => wp_json_encode( array(
				array( 'type' => 'form', 'steps' => 1, 'formName' => 'XSS probe', 'captcha' => false, 'thank_you' => 'msg',
					'thank_you_message' => array( 'thankYou' => 'ok', 'done' => 'ok' ), 'email' => get_option( 'admin_email' ),
					'sendEmail' => false, 'trackingCode' => true ),
				array( 'id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1', 'name' => 'Step', 'step' => '1', 'amount' => 1, 'visible' => 1 ),
				xs_field( 't1', 'text', array( 'name' => 'Full name' ) ),
				xs_field( 'ta1', 'textarea', array( 'name' => 'Notes' ) ),
				xs_field( 'map1', 'maps', array( 'name' => 'Location', 'mark' => 1, 'lat' => 35.7, 'lng' => 51.4, 'zoom' => 10 ) ),
				xs_field( 'c1', 'color', array( 'name' => 'Colour' ) ),
			), JSON_UNESCAPED_UNICODE ),
			'form_email'       => get_option( 'admin_email' ),
			'form_created_by'  => 1,
			'form_type'        => 'form',
			'form_create_date' => current_time( 'mysql' ),
		) );
		$fid = (int) $wpdb->insert_id;
		wp_cache_delete( 'efb_form_' . md5( 'form_' . $fid ), 'emsfb' );
		wp_cache_delete( 'efb_form_' . $fid, 'emsfb' );

		// A. hostile submission through the real route (post-fix storage)
		$sid = $fn->efb_code_validate_create( $fid, 0, 'visit', 0 );
		$rows = array(
			array( 'id_' => 't1', 'name' => '<b>Full name</b>', 'type' => 'r_matrix', 'value' => $IMG . ' after' ),
			/* The bundled client never sends a raw double quote (it tokenises them),
			   and the route's backslash strip would break the JSON if we did. */
			array( 'id_' => 'ta1', 'name' => 'Notes', 'type' => 'textarea', 'value' => '=1+2@efb@nq#second line' ),
			array( 'id_' => 'map1', 'name' => 'Location', 'type' => 'maps', 'value' => array( array( 'lat' => 35.7, 'lng' => 51.4, 'address' => $IMG . ' Tehran' ) ) ),
			array( 'id_' => 'c1', 'name' => 'Colour', 'type' => 'color', 'value' => '#FF0000' ),
		);
		$res = wp_remote_post( get_rest_url( null ) . 'Emsfb/v1/forms/message/add', array(
			'timeout' => 30, 'sslverify' => false,
			'headers' => array( 'Content-Type' => 'application/json', 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ), 'sid' => $sid, 'form-id' => (string) $fid ),
			'body' => wp_json_encode( array( 'id' => (string) $fid, 'sid' => $sid, 'page_id' => '0', 'url' => home_url( '/' ),
				'value' => wp_json_encode( $rows ), 'type' => 'form', 'name' => 'probe' ) ),
		) );
		$body = is_wp_error( $res ) ? array() : json_decode( wp_remote_retrieve_body( $res ), true );
		$track_a = isset( $body['data']['track'] ) ? $body['data']['track'] : '';
		$msg_a = $track_a ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT msg_id FROM `$msg_table` WHERE track = %s", $track_a ) ) : 0;

		// B. legacy poisoned row, exactly as a pre-fix server stored it
		$track_b = '2609' . strtoupper( wp_generate_password( 7, false, false ) );
		$legacy = array(
			array( 'id_' => 't1', 'name' => 'Full name', 'type' => 'text', 'value' => $IMG . ' legacy' ),
			array( 'id_' => 't1', 'name' => '<img src=x onerror=alert(1)>', 'type' => 'r_matrix', 'value' => '<img src=x onerror=alert(2)>', 'label' => '<img src=x onerror=alert(3)>' ),
			array( 'id_' => 'ta1', 'name' => 'Notes', 'type' => 'textarea', 'value' => '=1+1' ),
			array( 'id_' => 'map1', 'name' => 'Location', 'type' => 'maps', 'value' => array( array( 'lat' => 35.7, 'lng' => 51.4, 'address' => '<img src=x onerror=alert(4)> legacy address' ) ) ),
			array( 'id_' => 'c1', 'name' => 'Colour', 'type' => 'color', 'value' => 'red;background:url(x)' ),
			array( 'id_' => 'x1', 'name' => 'Pic', 'type' => 'esign', 'value' => 'javascript:alert(5)' ),
			array( 'type' => 'w_link', 'value' => home_url( '/' ), 'amount' => -1 ),
		);
		$wpdb->insert( $msg_table, array(
			'form_id' => $fid, 'track' => $track_b, 'ip' => '127.0.0.1', 'form_title_x' => 'XSS probe',
			'content' => str_replace( '"', '\\"', wp_json_encode( $legacy, JSON_UNESCAPED_UNICODE ) ),
			'date' => current_time( 'mysql' ), 'read_' => 0, 'read_by' => 0,
		) );
		$msg_b = (int) $wpdb->insert_id;

		$page_id = wp_insert_post( array( 'post_title' => 'EFB code finder xss probe', 'post_name' => 'efb-code-finder-xss-probe',
			'post_content' => '[Easy_Form_Builder_confirmation_code_finder]', 'post_status' => 'publish', 'post_type' => 'page' ) );

		$state = array( 'form_id' => $fid, 'page_id' => (int) $page_id, 'url' => get_permalink( $page_id ), 'sid' => $sid,
			'track_a' => $track_a, 'msg_a' => $msg_a, 'track_b' => $track_b, 'msg_b' => $msg_b );
		update_option( $marker, $state, false );
		echo json_encode( array_merge( array( 'ok' => $msg_a > 0 && $msg_b > 0, 'resp' => is_wp_error( $res ) ? $res->get_error_message() : substr( wp_remote_retrieve_body( $res ), 0, 300 ) ), $state ) );
		break;

	case 'replies':
		$s = get_option( $marker );
		$rows = $s ? $wpdb->get_results( $wpdb->prepare( "SELECT content, rsp_by FROM `$rsp_table` WHERE msg_id = %d ORDER BY rsp_id ASC", (int) $s['msg_b'] ), ARRAY_A ) : array();
		echo json_encode( array( 'ok' => true, 'replies' => $rows ) );
		break;

	case 'teardown':
		$s = get_option( $marker );
		if ( is_array( $s ) ) {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT msg_id FROM `$msg_table` WHERE form_id = %d", (int) $s['form_id'] ) );
			foreach ( $ids as $mid ) { $wpdb->delete( $rsp_table, array( 'msg_id' => (int) $mid ) ); }
			$wpdb->delete( $msg_table, array( 'form_id' => (int) $s['form_id'] ) );
			$wpdb->delete( $form_table, array( 'form_id' => (int) $s['form_id'] ) );
			if ( ! empty( $s['sid'] ) ) { $wpdb->delete( $stts_table, array( 'sid' => $s['sid'] ) ); }
			if ( ! empty( $s['page_id'] ) ) { wp_delete_post( (int) $s['page_id'], true ); }
			delete_option( $marker );
		}
		echo json_encode( array( 'ok' => true, 'restored' => is_array( $s ) ) );
		break;

	default:
		echo json_encode( array( 'ok' => false, 'error' => 'unknown mode' ) );
		exit( 1 );
}
