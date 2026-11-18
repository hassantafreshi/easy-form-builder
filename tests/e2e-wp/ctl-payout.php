<?php
/**
 * Site control for the PAY-OUT tests (tests/test-payout-*-http.php, e2e scenario "payout"): the legacy gateway
 * fields of Stripe, PayPal and Persia Pay on the e2e site, whichever core or add-on version is in place.
 *
 *   wp eval-file tests/e2e-wp/ctl-payout.php <command> [args]
 *
 *   mu                         copy mu-plugins/efb-e2e-payout.php (the Stripe SDK stand-in) into the site
 *   fake <0|1>                 the stand-in on / off; its state reset
 *   mode <status> [pi_id]      the status the stand-in reports (succeeded, requires_payment_method, processing, canceled),
 *                              for every intent or one
 *   stripe                     the stand-in's intents and request log
 *   legacy-form <type> [name] [k=v&…]   a payment form with a legacy gateway field (stripe | paypal | persiaPay), an
 *                              e-mail and a priced choice (Adult 25 / Child 12.5; for persiaPay 250000 / 125000),
 *                              saved through the builder's sanitizer; row-0 overrides as k=v&…
 *   keys <old|clear>           placeholder keys (built here, never a real key) in the settings row the way core's
 *                              Settings › Payments tab saved them (stripePKey, stripeSKey, paypalPKey, paypalSKey,
 *                              payToken); clear removes them there
 *   where                      where the keys are now: the old row keys (set or not), the add-ons' own keys
 *                              (pay_stripe, pay_paypal, pay_persiapay: public values, secrets as encrypted or not),
 *                              never a secret's value
 *   pay-rows [form_id]         rows of the emsfb_pay_ table (the Stripe / PayPal payments list); table: exists or not
 *   versions                   core version, the legacy_gateway contract (version, min, providers) and each legacy add-on's state
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI\n" );
	exit( 1 );
}

global $wpdb;
$efb_po_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$efb_po_cmd  = isset( $efb_po_args[0] ) ? $efb_po_args[0] : 'versions';
$efb_po_rest = array_slice( $efb_po_args, 1 );

function efb_po_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}

/** The settings row as an array (read from the table itself). */
function efb_po_row() {
	global $wpdb;
	$raw = (string) $wpdb->get_var( 'SELECT setting FROM `' . $wpdb->prefix . 'emsfb_setting` ORDER BY id DESC LIMIT 1' ); // phpcs:ignore
	$d = function_exists( 'emsfb_json_decode_efb' ) ? emsfb_json_decode_efb( $raw, true ) : json_decode( $raw, true );
	return is_array( $d ) ? $d : array();
}

function efb_po_write( array $patch ) {
	$fn = get_efbFunction();
	$s = get_setting_Emsfb( 'decoded' );
	return (bool) $fn->set_setting_Emsfb( (object) $patch, is_object( $s ) && isset( $s->emailSupporter ) ? (string) $s->emailSupporter : '' );
}

switch ( $efb_po_cmd ) {
	case 'mu':
		$dst = WPMU_PLUGIN_DIR . '/efb-e2e-payout.php';
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) wp_mkdir_p( WPMU_PLUGIN_DIR );
		$src = __DIR__ . '/mu-plugins/efb-e2e-payout.php';
		$same = is_readable( $dst ) && md5_file( $dst ) === md5_file( $src );
		efb_po_out( array( 'copied' => $same || copy( $src, $dst ), 'changed' => ! $same ) );
		break;

	case 'fake':
		update_option( 'efb_e2e_payout_fake', isset( $efb_po_rest[0] ) && '1' === $efb_po_rest[0] ? 1 : 0, false );
		update_option( 'efb_e2e_payout_stripe', array( 'intents' => array(), 'mode' => 'succeeded', 'log' => array() ), false );
		efb_po_out( array( 'ok' => true ) );
		break;

	case 'mode':
		$s = get_option( 'efb_e2e_payout_stripe', array() );
		$s = is_array( $s ) ? $s : array();
		if ( isset( $efb_po_rest[1] ) && isset( $s['intents'][ $efb_po_rest[1] ] ) ) $s['intents'][ $efb_po_rest[1] ]['mode'] = $efb_po_rest[0];
		else $s['mode'] = isset( $efb_po_rest[0] ) ? $efb_po_rest[0] : 'succeeded';
		update_option( 'efb_e2e_payout_stripe', $s, false );
		efb_po_out( array( 'ok' => true ) );
		break;

	case 'stripe':
		$s = get_option( 'efb_e2e_payout_stripe', array() );
		efb_po_out( is_array( $s ) ? $s : array() );
		break;

	case 'legacy-form':
		$type = isset( $efb_po_rest[0] ) && in_array( $efb_po_rest[0], array( 'stripe', 'paypal', 'persiaPay' ), true ) ? $efb_po_rest[0] : 'stripe';
		$name = isset( $efb_po_rest[1] ) && '' !== $efb_po_rest[1] ? $efb_po_rest[1] : 'Legacy ' . $type;
		$rid  = function () { return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz0123456789' ), 0, 9 ); };
		$em = $rid(); $pf = $rid(); $rad = $rid(); $o1 = $rid(); $o2 = $rid();
		$persia = 'persiaPay' === $type;
		$base = array( 'step' => 1, 'amount' => 0, 'size' => 100, 'required' => 0, 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6', 'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'classes' => '', 'message' => '', 'placeholder' => '', 'value' => '', 'icon_input' => '' );
		$row0 = array( 'type' => 'payment', 'steps' => 1, 'formName' => $name, 'email' => 'owner@example.test', 'trackingCode' => '1', 'EfbVersion' => 2, 'button_single_text' => 'Send', 'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_state' => 'single', 'email_to' => $em, 'show_icon' => '1', 'captcha' => '', 'sendEmail' => '0', 'stateForm' => 0, 'thank_you' => 'msg', 'thank_you_message' => array( 'icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thank you', 'done' => 'Done', 'trackingCode' => 'Confirmation Code', 'pleaseFillInRequiredFields' => 'Please fill in all required fields.' ), 'getway' => $type, 'currency' => $persia ? 'irr' : 'usd', 'paymentmethod' => 'charge', 'dShowBg' => '1', 'btns_align' => 'justify-content-center', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb' );
		if ( $persia ) $row0['persiaPay'] = 'zarinPal';
		$rows = array( $row0, array( 'id_' => '1', 'type' => 'step', 'dataId' => '1', 'classes' => '', 'id' => '1', 'name' => 'Step', 'icon' => 'bi-ui-checks-grid', 'step' => 1, 'amount' => 1, 'EfbVersion' => 2, 'message' => '', 'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5', 'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-darkb', 'visible' => 1 ) );
		$rows[] = array_merge( $base, array( 'id_' => $em, 'dataId' => $em . '-id', 'type' => 'email', 'elementId' => 'email', 'name' => 'Email', 'amount' => 2, 'required' => 1 ) );
		$rows[] = array_merge( $base, array( 'id_' => $rad, 'dataId' => $rad . '-id', 'type' => 'payRadio', 'elementId' => 'payRadio', 'name' => 'Ticket', 'amount' => 3, 'required' => 1 ) );
		$rows[] = array( 'id_' => $o1, 'dataId' => $o1 . '-id', 'parent' => $rad, 'type' => 'option', 'value' => 'Adult', 'id_op' => $o1, 'step' => 1, 'amount' => 4, 'price' => $persia ? '250000' : '25' );
		$rows[] = array( 'id_' => $o2, 'dataId' => $o2 . '-id', 'parent' => $rad, 'type' => 'option', 'value' => 'Child', 'id_op' => $o2, 'step' => 1, 'amount' => 4, 'price' => $persia ? '125000' : '12.5' );
		$rows[] = array_merge( $base, array( 'id_' => $pf, 'dataId' => $pf . '-id', 'type' => $type, 'elementId' => $type, 'name' => ucfirst( $type ), 'amount' => 5 ) );
		if ( isset( $efb_po_rest[2] ) ) {
			foreach ( explode( '&', $efb_po_rest[2] ) as $kv ) {
				list( $k, $v ) = array_pad( explode( '=', $kv, 2 ), 2, '' );
				$rows[0][ $k ] = urldecode( $v );
			}
		}
		$clean = get_efbFunction()->sanitize_obj_msg_efb( $rows );
		$json = str_replace( '"', '\\"', wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) );
		$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => $name, 'form_structer' => $json, 'form_email' => '', 'form_type' => 'payment', 'form_created_by' => '1', 'form_access_by' => '', 'form_create_date' => current_time( 'mysql' ), 'status' => 1 ) );
		efb_po_out( array( 'form_id' => (int) $wpdb->insert_id, 'email' => $em, 'pf' => $pf, 'radio' => $rad, 'o1' => $o1, 'o2' => $o2 ) );
		break;

	case 'keys':
		$which = isset( $efb_po_rest[0] ) ? $efb_po_rest[0] : 'old';
		// Shapes of the providers' keys, built here: never a real key.
		$k = array( 'stripePKey' => 'pk_test_' . str_repeat( 'EfbE2e', 4 ), 'stripeSKey' => 'sk_test_' . str_repeat( 'EfbE2e', 4 ), 'paypalPKey' => 'AE2e' . str_repeat( 'Efb', 8 ), 'paypalSKey' => 'EE2e' . str_repeat( 'Efb', 8 ), 'payToken' => str_repeat( 'a1b2', 9 ) );
		if ( 'clear' === $which ) $k = array_fill_keys( array_keys( $k ), null );
		efb_po_out( array( 'ok' => efb_po_write( $k ) ) );
		break;

	case 'where':
		$row = efb_po_row();
		$out = array( 'old' => array(), 'own' => array() );
		foreach ( array( 'stripePKey', 'stripeSKey', 'paypalPKey', 'paypalSKey', 'payToken' ) as $k ) $out['old'][ $k ] = isset( $row[ $k ] ) && is_string( $row[ $k ] ) && strlen( $row[ $k ] ) > 5 && 'null' !== $row[ $k ];
		foreach ( array( 'pay_stripe', 'pay_paypal', 'pay_persiapay' ) as $k ) {
			if ( ! isset( $row[ $k ] ) || ! is_array( $row[ $k ] ) ) { $out['own'][ $k ] = null; continue; }
			$sec = array();
			foreach ( (array) ( $row[ $k ]['secret'] ?? array() ) as $sk => $sv ) $sec[ $sk ] = is_array( $sv ) && isset( $sv['ct'] ) ? 'encrypted' : ( is_array( $sv ) ? 'array' : 'plain' );
			$out['own'][ $k ] = array( 'public' => (array) ( $row[ $k ]['public'] ?? array() ), 'secret' => $sec, 'v' => $row[ $k ]['v'] ?? null );
		}
		$out['moved'] = array( 'stripe' => get_option( 'emsfb_pay_stripe_moved', null ), 'paypal' => get_option( 'emsfb_pay_paypal_moved', null ), 'persiapay' => get_option( 'emsfb_pay_persiapay_moved', null ) );
		efb_po_out( $out );
		break;

	case 'pay-rows':
		$t = $wpdb->prefix . 'emsfb_pay_';
		$has = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
		$fid = isset( $efb_po_rest[0] ) ? (int) $efb_po_rest[0] : 0;
		$rows = ! $has ? array() : $wpdb->get_results( $fid ? $wpdb->prepare( "SELECT * FROM `$t` WHERE form_id = %d ORDER BY id", $fid ) : "SELECT * FROM `$t` ORDER BY id", ARRAY_A ); // phpcs:ignore
		efb_po_out( array( 'table' => $has, 'rows' => $rows ) );
		break;

	case 'builder':
		// What the form builder gets: efb_var.legacy_gw (core 1.1+) and the add-ons' builder scripts.
		wp_set_current_user( 1 );
		$vars = apply_filters( 'efb_admin_localize_vars', array() );
		do_action( 'efb_admin_enqueue_builder', 'create' );
		$js = array();
		foreach ( array( 'stripe' => 'efb-stripe-builder-js', 'paypal' => 'efb-paypal-builder-js', 'persiapay' => 'efb-persiapay-builder-js' ) as $a => $h ) $js[ $a ] = wp_script_is( $h, 'enqueued' );
		efb_po_out( array( 'legacy_gw' => $vars['legacy_gw'] ?? null, 'scripts' => $js ) );
		break;

	case 'versions':
		$c = function_exists( 'efb_addon_contracts' ) ? efb_addon_contracts() : array();
		$states = array();
		if ( class_exists( '\Emsfb\Split' ) ) foreach ( array( 'AdnSPF', 'AdnPAP', 'AdnPPF' ) as $code ) $states[ $code ] = \Emsfb\Split::addon_state( $code );
		efb_po_out( array( 'core' => defined( 'EMSFB_PLUGIN_VERSION' ) ? EMSFB_PLUGIN_VERSION : '', 'dir' => defined( 'EMSFB_PLUGIN_DIRECTORY' ) ? realpath( EMSFB_PLUGIN_DIRECTORY ) : '', 'legacy_gateway' => $c['legacy_gateway'] ?? null, 'states' => $states ) );
		break;

	default:
		efb_po_out( array( 'error' => 'unknown command ' . $efb_po_cmd ) );
}
