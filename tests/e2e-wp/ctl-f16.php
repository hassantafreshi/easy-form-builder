<?php
/**
 * F16 helpers for tests/e2e-wp/scenario-f16.js, run through `wp eval-file` (see README.md).
 *
 *   page <title> <form id> [<form id> …]   a page holding several forms (one shortcode each)
 *   revisions <form id> <locale>             the versions of a form as the F15 dialog lists them, in that locale
 *   record <form id>                         record a version of the stored form (F15), as a builder Save does
 *   rows <form id>                           the stored rows of a form (decoded)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}
$f16_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$f16_cmd  = isset( $f16_args[0] ) ? $f16_args[0] : '';
$f16_rest = array_slice( $f16_args, 1 );
function efb_f16_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}
function efb_f16_rows( $fid ) {
	global $wpdb;
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", (int) $fid ) ); // phpcs:ignore
	return json_decode( emsfb_json_normalize_efb( (string) $raw ), true );
}
switch ( $f16_cmd ) {
	case 'page':
		$title = isset( $f16_rest[0] ) ? $f16_rest[0] : 'F16 page';
		$body  = '';
		foreach ( array_slice( $f16_rest, 1 ) as $fid ) $body .= '<!-- wp:shortcode -->[EMS_Form_Builder id=' . (int) $fid . ']<!-- /wp:shortcode -->' . "\n";
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $body, 'meta_input' => array( '_efb_e2e' => 1 ) ) );
		efb_f16_out( array( 'id' => $id, 'url' => get_permalink( $id ) ) );
		break;
	case 'record':
		wp_set_current_user( 1 );
		$fid = (int) $f16_rest[0];
		efb_f16_out( array( 'rev' => \Emsfb\Revisions::record( $fid, efb_f16_rows( $fid ), array( 'source' => 'builder' ) ) ) );
		break;
	case 'revisions':
		$fid = (int) $f16_rest[0];
		$loc = isset( $f16_rest[1] ) ? $f16_rest[1] : 'en_US';
		switch_to_locale( $loc );
		$out = array_map( function ( $r ) { return array( 'date' => $r['date'], 'iso' => $r['iso'] ); }, \Emsfb\Revisions::list_for_form( $fid ) );
		restore_previous_locale();
		efb_f16_out( $out );
		break;
	case 'rows':
		efb_f16_out( efb_f16_rows( (int) $f16_rest[0] ) );
		break;
	default:
		efb_f16_out( array( 'error' => 'unknown command ' . $f16_cmd ) );
}
