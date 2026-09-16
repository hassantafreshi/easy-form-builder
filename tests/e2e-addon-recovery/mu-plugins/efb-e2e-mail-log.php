<?php
/**
 * Plugin Name: EFB e2e mail log
 * Description: Records every wp_mail() call in the efb_e2e_mail_log option instead of sending it. Test site only.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	$message = isset( $atts['message'] ) ? (string) $atts['message'] : '';
	$to      = isset( $atts['to'] ) ? $atts['to'] : '';

	$log   = get_option( 'efb_e2e_mail_log', array() );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		'at'             => time(),
		'to'             => is_array( $to ) ? implode( ',', $to ) : (string) $to,
		'subject'        => isset( $atts['subject'] ) ? (string) $atts['subject'] : '',
		'addon_report'   => false !== strpos( $message, 'could not reinstall its add-on files' ),
		'request'        => isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : 'cli',
	);
	update_option( 'efb_e2e_mail_log', $log, false );

	return true;
}, 10, 2 );
