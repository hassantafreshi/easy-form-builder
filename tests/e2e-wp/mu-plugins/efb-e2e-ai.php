<?php
/**
 * Test site only (tests/e2e-wp, the AI builder core APIs AI-1 … AI-8): records what the save hooks carry and lets
 * application passwords work on the plain-http test site. Copied into mu-plugins by `ctl-ai.php mu`.
 *
 *  - efb_form_saved / efb_form_created: the context's warnings (codes and messages) of the last saves, in the option
 *    efb_e2e_ai_saved (AI-1, the server twin of efb:form-saved);
 *  - efb_form_status_changed: the last changes (AI-4);
 *  - wp_is_application_passwords_available: true (WordPress allows them on https or a "local" site only).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'wp_is_application_passwords_available', '__return_true' );

$efb_e2e_ai_rec = function ( $hook ) {
	return function ( $form_id, $rows, $ctx = array() ) use ( $hook ) {
		$all = get_option( 'efb_e2e_ai_saved', array() );
		$all = is_array( $all ) ? $all : array();
		$all[] = array( 'hook' => $hook, 'form_id' => (int) $form_id, 'source' => is_array( $ctx ) && isset( $ctx['source'] ) ? $ctx['source'] : null,
			'has_warnings_key' => is_array( $ctx ) && array_key_exists( 'warnings', $ctx ), 'warnings' => is_array( $ctx ) && isset( $ctx['warnings'] ) ? $ctx['warnings'] : null );
		update_option( 'efb_e2e_ai_saved', array_slice( $all, -20 ), false );
	};
};
add_action( 'efb_form_saved', $efb_e2e_ai_rec( 'efb_form_saved' ), 99, 3 );
add_action( 'efb_form_created', $efb_e2e_ai_rec( 'efb_form_created' ), 99, 3 );
add_action( 'efb_form_status_changed', function ( $form_id, $new, $old ) {
	$all = get_option( 'efb_e2e_ai_status', array() );
	$all = is_array( $all ) ? $all : array();
	$all[] = array( 'form_id' => (int) $form_id, 'new' => $new, 'old' => $old );
	update_option( 'efb_e2e_ai_status', array_slice( $all, -20 ), false );
}, 10, 3 );
