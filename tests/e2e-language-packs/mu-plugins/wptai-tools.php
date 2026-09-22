<?php
/**
 * Plugin Name: wp-test-ai tools
 * Description: Test site only. Records every wp_mail() call instead of sending it, and every outgoing HTTP request with what came back, so the language-pack end-to-end run can show what the site did.
 */

defined( 'ABSPATH' ) || exit;

/**
 * wp_mail(): keep the whole message, send nothing.
 */
add_filter(
	'pre_wp_mail',
	function ( $null, $atts ) {
		$to  = isset( $atts['to'] ) ? $atts['to'] : '';
		$log = get_option( 'wptai_mail_log', array() );
		$log = is_array( $log ) ? $log : array();

		$log[] = array(
			'at'      => time(),
			'to'      => is_array( $to ) ? implode( ',', $to ) : (string) $to,
			'subject' => isset( $atts['subject'] ) ? (string) $atts['subject'] : '',
			'body'    => isset( $atts['message'] ) ? (string) $atts['message'] : '',
			'request' => isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : 'cli',
		);
		update_option( 'wptai_mail_log', $log, false );

		return true;
	},
	10,
	2
);

/**
 * Outgoing HTTP: what was asked for, and what came back. Only the language-pack traffic is kept:
 * the language servers and wordpress.org.
 */
function wptai_http_log_add( $url, array $fields ) {
	$host = (string) parse_url( $url, PHP_URL_HOST );
	$path = (string) parse_url( $url, PHP_URL_PATH );
	if ( false === strpos( $url, '/translations' ) && false === strpos( $url, 'downloads.wordpress.org/translation' ) && false === strpos( $path, '/wl/v1/' ) ) {
		return;
	}

	$log = get_option( 'wptai_http_log', array() );
	$log = is_array( $log ) ? $log : array();

	$key = md5( $url . '|' . ( isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? $_SERVER['REQUEST_TIME_FLOAT'] : '' ) );
	$row = isset( $log[ $key ] ) ? $log[ $key ] : array(
		'at'     => time(),
		'url'    => preg_replace( '/([?&]fp=)[a-f0-9]+/', '$1…', $url ),
		'host'   => $host,
		'status' => 'no response (blocked or failed before it was sent)',
	);
	$log[ $key ] = array_merge( $row, $fields );
	update_option( 'wptai_http_log', array_slice( $log, -80, null, true ), false );
}

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		wptai_http_log_add( $url, array() );
		return $pre;
	},
	1,
	3
);

add_action(
	'http_api_debug',
	function ( $response, $context, $class, $args, $url ) {
		if ( 'response' !== $context ) {
			return;
		}
		if ( is_wp_error( $response ) ) {
			wptai_http_log_add( $url, array( 'status' => 'error: ' . $response->get_error_message() ) );
			return;
		}
		$header = wp_remote_retrieve_header( $response, 'x-ws-language-handler' );
		wptai_http_log_add(
			$url,
			array(
				'status'         => 'HTTP ' . (int) wp_remote_retrieve_response_code( $response ),
				'ws_header'      => '' !== (string) $header ? (string) $header : 'absent',
				'content_type'   => (string) wp_remote_retrieve_header( $response, 'content-type' ),
			)
		);
	},
	10,
	5
);
