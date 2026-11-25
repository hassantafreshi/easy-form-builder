<?php
/**
 * Site control for the e2e scenario "pay-steps" (tests/e2e-wp/scenario-pay-steps.js, QA-3's payment findings):
 *
 *   wp eval-file tests/e2e-wp/ctl-pay-steps.php <command> [args]
 *
 *   form [currency] [name]    a two-step payment form of the test gateway (efbmock): step 1 an e-mail and a priced
 *                             choice (Adult 25 / Child 12.5), step 2 the payment field; saved like the builder saves
 *   mu                        copy mu-plugins/efb-e2e-pay-steps.php into the site (the switch below)
 *   no-gateways <0|1>         every payment gateway hidden from the framework (as if no gateway add-on were active)
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI\n" );
	exit( 1 );
}

global $wpdb;
$efb_ps_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$efb_ps_cmd  = isset( $efb_ps_args[0] ) ? $efb_ps_args[0] : '';
$efb_ps_rest = array_slice( $efb_ps_args, 1 );

function efb_ps_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
	exit( 0 );
}

switch ( $efb_ps_cmd ) {
	case 'form':
		$cur  = isset( $efb_ps_rest[0] ) && $efb_ps_rest[0] !== '' ? strtolower( $efb_ps_rest[0] ) : 'usd';
		$name = isset( $efb_ps_rest[1] ) ? $efb_ps_rest[1] : 'Two-step payment';
		$rid  = function () { return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz0123456789' ), 0, 9 ); };
		$em = $rid(); $rad = $rid(); $o1 = $rid(); $o2 = $rid(); $pf = $rid();
		$base = array( 'amount' => 0, 'size' => 100, 'required' => 0, 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6', 'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'classes' => '', 'message' => '', 'placeholder' => '', 'value' => '', 'icon_input' => '' );
		$step = function ( $n ) { return array( 'id_' => (string) $n, 'type' => 'step', 'dataId' => (string) $n, 'classes' => '', 'id' => (string) $n, 'name' => 'Step ' . $n, 'icon' => 'bi-ui-checks-grid', 'step' => $n, 'amount' => $n * 10 - 5, 'EfbVersion' => 2, 'message' => '', 'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5', 'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-darkb', 'visible' => 1 ); };
		$row0 = array( 'type' => 'payment', 'steps' => 2, 'formName' => $name, 'email' => 'owner@example.test', 'trackingCode' => '1', 'EfbVersion' => 2, 'button_single_text' => 'Send', 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_Next_icon' => 'bi-chevron-right', 'button_Previous_icon' => 'bi-chevron-left', 'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_state' => 'multi', 'email_to' => $em, 'show_icon' => '1', 'captcha' => '', 'sendEmail' => '0', 'stateForm' => 0, 'thank_you' => 'msg', 'thank_you_message' => array( 'icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thank you', 'done' => 'Done', 'trackingCode' => 'Confirmation Code', 'pleaseFillInRequiredFields' => 'Please fill in all required fields.' ), 'getway' => 'efbmock', 'currency' => $cur, 'paymentmethod' => 'charge', 'dShowBg' => '1', 'btns_align' => 'justify-content-center', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb' );
		$rows = array(
			$row0,
			$step( 1 ),
			array_merge( $base, array( 'id_' => $em, 'dataId' => $em . '-id', 'type' => 'email', 'elementId' => 'email', 'name' => 'Email', 'step' => 1, 'amount' => 2, 'required' => 1, 'noti' => 1 ) ),
			array_merge( $base, array( 'id_' => $rad, 'dataId' => $rad . '-id', 'type' => 'payRadio', 'elementId' => 'payRadio', 'name' => 'Ticket', 'step' => 1, 'amount' => 3, 'required' => 1 ) ),
			array( 'id_' => $o1, 'dataId' => $o1 . '-id', 'parent' => $rad, 'type' => 'option', 'value' => 'Adult', 'id_op' => $o1, 'step' => 1, 'amount' => 4, 'price' => '25' ),
			array( 'id_' => $o2, 'dataId' => $o2 . '-id', 'parent' => $rad, 'type' => 'option', 'value' => 'Child', 'id_op' => $o2, 'step' => 1, 'amount' => 4, 'price' => '12.5' ),
			$step( 2 ),
			array_merge( $base, array( 'id_' => $pf, 'dataId' => $pf . '-id', 'type' => 'efbmock', 'elementId' => 'efbmock', 'name' => 'Payment', 'step' => 2, 'amount' => 16 ) ),
		);
		$json = str_replace( '"', '\\"', wp_json_encode( $rows, JSON_UNESCAPED_UNICODE ) );
		$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => $name, 'form_structer' => $json, 'form_email' => '', 'form_created_by' => 1, 'form_type' => 'payment' ) ); // phpcs:ignore
		efb_ps_out( array( 'form_id' => (int) $wpdb->insert_id, 'email' => $em, 'radio' => $rad, 'o1' => $o1, 'o2' => $o2, 'pf' => $pf ) );
		break;

	case 'mu':
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) wp_mkdir_p( WPMU_PLUGIN_DIR );
		$ok = copy( __DIR__ . '/mu-plugins/efb-e2e-pay-steps.php', WPMU_PLUGIN_DIR . '/efb-e2e-pay-steps.php' );
		efb_ps_out( array( 'ok' => $ok ) );
		break;

	case 'no-gateways':
		update_option( 'efb_e2e_no_gateways', isset( $efb_ps_rest[0] ) && '1' === $efb_ps_rest[0] ? 1 : 0 );
		efb_ps_out( array( 'no_gateways' => (int) get_option( 'efb_e2e_no_gateways' ) ) );
		break;

	default:
		efb_ps_out( array( 'error' => 'unknown command ' . $efb_ps_cmd ) );
}
