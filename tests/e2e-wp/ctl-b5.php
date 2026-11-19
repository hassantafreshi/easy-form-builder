<?php
/**
 * B5 helpers for tests/e2e-wp/scenario-b5.js, run through `wp eval-file` (see README.md).
 *
 *   form <name> <base64 JSON rows>   a form stored as the builder stores it (escaped JSON); prints its id
 *   page-finder                      a page with the confirmation code finder; prints id and URL
 *   delete <form id,…> [page id,…]   the forms (with their entries and counters) and the pages
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}
$b5_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$b5_cmd  = isset( $b5_args[0] ) ? $b5_args[0] : '';
$b5_rest = array_slice( $b5_args, 1 );
function efb_b5_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
	exit( 0 );
}
global $wpdb;
switch ( $b5_cmd ) {
	case 'form':
		$rows = json_decode( base64_decode( (string) ( $b5_rest[1] ?? '' ) ), true );
		if ( ! is_array( $rows ) ) efb_b5_out( array( 'error' => 'rows' ) );
		$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => (string) $b5_rest[0], 'form_structer' => str_replace( '"', '\\"', wp_json_encode( $rows, JSON_UNESCAPED_UNICODE ) ), 'form_email' => '', 'form_type' => 'form', 'form_created_by' => '1', 'form_access_by' => '' ) );
		if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
		efb_b5_out( array( 'form_id' => (int) $wpdb->insert_id ) );
		break;
	case 'page-finder':
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'EFB e2e code finder', 'post_content' => '<!-- wp:shortcode -->[Easy_Form_Builder_confirmation_code_finder]<!-- /wp:shortcode -->', 'meta_input' => array( '_efb_e2e' => 1 ) ) );
		efb_b5_out( array( 'id' => $id, 'url' => get_permalink( $id ) ) );
		break;
	case 'delete':
		foreach ( array_filter( array_map( 'intval', explode( ',', (string) ( $b5_rest[0] ?? '' ) ) ) ) as $fid ) {
			$wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $fid ) );
			$wpdb->delete( $wpdb->prefix . 'emsfb_msg_', array( 'form_id' => $fid ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_stats_ WHERE form_id = %d", $fid ) ); // phpcs:ignore
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_stts_ WHERE fid = %d", $fid ) ); // phpcs:ignore
		}
		foreach ( array_filter( array_map( 'intval', explode( ',', (string) ( $b5_rest[1] ?? '' ) ) ) ) as $pid ) wp_delete_post( $pid, true );
		if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
		efb_b5_out( array( 'ok' => true ) );
		break;
	default:
		efb_b5_out( array( 'error' => 'unknown command ' . $b5_cmd ) );
}
