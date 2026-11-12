<?php
/**
 * tests/e2e-wp: the Payplug scenario's window into the site (wp eval-file tests/e2e-wp/ctl-payplug.php <command>).
 *
 *   fake <0|1>               copy mu-plugins/efb-e2e-payplug.php into the site's mu-plugins and switch the
 *                            fake api.payplug.com on (1) or off (0); the AdnPPG add-on flag follows
 *   keys <test|live> [oney]  store the placeholder keys of the fake (not real keys) and the mode (oney: Oney 3x on)
 *   form [name] [oney]       a Payplug form (oney: also mobile, street, postcode, country mapped, card + Oney 3x) (e-mail, first / last name, city mapped for billing, a priced
 *                            radio 25 / 150, fixed fee 8) saved through the core sanitizer
 *   provider <id> <fixture>  move the fake's payment to a get-* fixture state (get-paid, …)
 *   notify-url <id>          the notification_url the fake was given for a payment
 *   log                      the fake's request log (method, path, PayPlug-Version)
 *
 * Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI: wp eval-file tests/e2e-wp/ctl-payplug.php <command>\n" );
	exit( 1 );
}

$efb_pp_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$efb_pp_cmd  = isset( $efb_pp_args[0] ) ? $efb_pp_args[0] : '';
$efb_pp_rest = array_slice( $efb_pp_args, 1 );

function efb_pp_out( $data ) {
	echo "\n" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
	exit( 0 );
}

$efb_pp_mu = __DIR__ . '/mu-plugins/efb-e2e-payplug.php';
if ( is_readable( $efb_pp_mu ) && ! function_exists( 'efb_e2e_payplug_key' ) ) require_once $efb_pp_mu;

global $wpdb;

switch ( $efb_pp_cmd ) {
	case 'fake':
		$on = isset( $efb_pp_rest[0] ) && '1' === $efb_pp_rest[0];
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) wp_mkdir_p( WPMU_PLUGIN_DIR );
		if ( $on ) copy( $efb_pp_mu, WPMU_PLUGIN_DIR . '/efb-e2e-payplug.php' );
		update_option( 'efb_e2e_payplug_fake', $on ? 1 : 0 );
		$s = get_setting_Emsfb( 'decoded' );
		if ( is_object( $s ) ) {
			$s->AdnPPG = $on ? 1 : 0;
			\efbFunction::set_setting_Emsfb( $s );
		}
		efb_pp_out( array( 'ok' => true, 'fake' => $on, 'mu' => is_readable( WPMU_PLUGIN_DIR . '/efb-e2e-payplug.php' ), 'addon' => is_readable( WP_PLUGIN_DIR . '/easy-form-builder/vendor/payplug/routes-efb.php' ) ) );
		break;

	case 'keys':
		require_once WP_PLUGIN_DIR . '/easy-form-builder/vendor/payplug/payplugefb.php';
		$gw = new \Emsfb\Payments\Payplug_Gateway();
		$st = \Emsfb\Payments\Settings::load( 'payplug' );
		$mode = isset( $efb_pp_rest[0] ) && 'live' === $efb_pp_rest[0] ? 'live' : 'test';
		$st->apply( $gw->settings_schema(), array( 'api_key__test' => efb_e2e_payplug_key( 'test' ), 'api_key__live' => efb_e2e_payplug_key( 'live' ), 'card_mode' => 'hosted', 'default_country' => 'FR', 'oney' => isset( $efb_pp_rest[1] ) && 'oney' === $efb_pp_rest[1] ? 'x3_with_fees' : '' ), $mode );
		efb_pp_out( array( 'ok' => true, 'mode' => \Emsfb\Payments\Settings::load( 'payplug' )->mode() ) );
		break;

	case 'form':
		$name = isset( $efb_pp_rest[0] ) ? $efb_pp_rest[0] : 'Payplug e2e';
		$rid  = static function () { return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz0123456789' ), 0, 9 ); };
		$em = $rid(); $fn = $rid(); $ln = $rid(); $ci = $rid(); $pf = $rid(); $rad = $rid(); $o1 = $rid(); $o2 = $rid();
		$base = array( 'step' => 1, 'amount' => 0, 'size' => 100, 'required' => 0, 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6', 'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'classes' => '', 'message' => '', 'placeholder' => '', 'value' => '', 'icon_input' => '' );
		$row0 = array( 'type' => 'payment', 'steps' => 1, 'formName' => $name, 'email' => 'owner@example.test', 'trackingCode' => '1', 'EfbVersion' => 2, 'button_single_text' => 'Send', 'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_state' => 'single', 'email_to' => $em, 'show_icon' => '1', 'captcha' => '', 'sendEmail' => '1', 'stateForm' => 0, 'thank_you' => 'msg', 'thank_you_message' => array( 'icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thank you', 'done' => 'Done', 'trackingCode' => 'Confirmation Code', 'pleaseFillInRequiredFields' => 'Please fill in all required fields.' ), 'getway' => 'payplug', 'currency' => 'EUR', 'paymentmethod' => 'charge', 'dShowBg' => '1', 'btns_align' => 'justify-content-center', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb' );
		$rows = array( $row0, array( 'id_' => '1', 'type' => 'step', 'dataId' => '1', 'classes' => '', 'id' => '1', 'name' => 'Step', 'icon' => 'bi-ui-checks-grid', 'step' => 1, 'amount' => 1, 'EfbVersion' => 2, 'message' => '', 'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5', 'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-darkb', 'visible' => 1 ) );
		$rows[] = array_merge( $base, array( 'id_' => $em, 'dataId' => $em . '-id', 'type' => 'email', 'elementId' => 'email', 'name' => 'Email', 'amount' => 2, 'required' => 1, 'noti' => 1 ) );
		$rows[] = array_merge( $base, array( 'id_' => $fn, 'dataId' => $fn . '-id', 'type' => 'text', 'elementId' => 'text', 'name' => 'Prénom', 'amount' => 3 ) );
		$rows[] = array_merge( $base, array( 'id_' => $ln, 'dataId' => $ln . '-id', 'type' => 'text', 'elementId' => 'text', 'name' => 'Nom', 'amount' => 4 ) );
		$rows[] = array_merge( $base, array( 'id_' => $ci, 'dataId' => $ci . '-id', 'type' => 'text', 'elementId' => 'text', 'name' => 'Ville', 'amount' => 5 ) );
		$rows[] = array_merge( $base, array( 'id_' => $rad, 'dataId' => $rad . '-id', 'type' => 'payRadio', 'elementId' => 'payRadio', 'name' => 'Atelier', 'amount' => 6, 'required' => 1 ) );
		$rows[] = array( 'id_' => $o1, 'dataId' => $o1 . '-id', 'parent' => $rad, 'type' => 'option', 'value' => 'Atelier', 'id_op' => $o1, 'step' => 1, 'amount' => 7, 'price' => '25' );
		$rows[] = array( 'id_' => $o2, 'dataId' => $o2 . '-id', 'parent' => $rad, 'type' => 'option', 'value' => 'Grand atelier', 'id_op' => $o2, 'step' => 1, 'amount' => 7, 'price' => '150' );
		$oney = isset( $efb_pp_rest[1] ) && 'oney' === $efb_pp_rest[1];
		$extra_map = array();
		$mo = $ad = $pc = $co = '';
		if ( $oney ) {
			// Oney needs the mobile number, the street, the postcode and the country too (PAY02 error scenarios).
			$mo = $rid(); $ad = $rid(); $pc = $rid(); $co = $rid();
			foreach ( array( array( $mo, 'Mobile' ), array( $ad, 'Adresse' ), array( $pc, 'Code postal' ), array( $co, 'Pays' ) ) as $i => $f ) {
				$rows[] = array_merge( $base, array( 'id_' => $f[0], 'dataId' => $f[0] . '-id', 'type' => 'text', 'elementId' => 'text', 'name' => $f[1], 'amount' => 9 + $i ) );
			}
			$extra_map = array( 'pgw_map_phone' => $mo, 'pgw_map_address' => $ad, 'pgw_map_postcode' => $pc, 'pgw_map_country' => $co, 'pf_methods' => 'card,oney_x3_with_fees' );
		}
		$rows[] = array_merge( $base, array( 'id_' => $pf, 'dataId' => $pf . '-id', 'type' => 'payplug', 'elementId' => 'payplug', 'name' => 'Paiement', 'amount' => 8,
			'pf_fixed' => '8', 'pf_fixed_label' => 'Frais de dossier', 'pgw_map_first' => $fn, 'pgw_map_last' => $ln, 'pgw_map_city' => $ci ), $extra_map );
		$clean = get_efbFunction()->sanitize_obj_msg_efb( $rows );
		$json = str_replace( '"', '\\"', wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) );
		$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => $name, 'form_structer' => $json, 'form_email' => '', 'form_type' => 'payment', 'form_created_by' => '1', 'form_access_by' => '', 'form_create_date' => current_time( 'mysql' ), 'status' => 1 ) );
		$fid = (int) $wpdb->insert_id;
		do_action( 'efb_form_created', $fid, $clean, array( 'form_id' => $fid, 'source' => 'e2e', 'user_id' => 1 ) );
		efb_pp_out( array( 'form_id' => $fid, 'email' => $em, 'first' => $fn, 'last' => $ln, 'city' => $ci, 'pf' => $pf, 'radio' => $rad, 'o1' => $o1, 'o2' => $o2, 'mobile' => $mo, 'address' => $ad, 'postcode' => $pc, 'country' => $co ) );
		break;

	case 'provider':
		efb_pp_out( array( 'ok' => efb_e2e_payplug_set( (string) $efb_pp_rest[0], isset( $efb_pp_rest[1] ) ? preg_replace( '/[^a-z-]/', '', $efb_pp_rest[1] ) : 'get-paid' ) ) );
		break;

	case 'notify-url':
		$all = efb_e2e_payplug_all();
		$id = isset( $efb_pp_rest[0] ) ? (string) $efb_pp_rest[0] : '';
		efb_pp_out( array( 'url' => isset( $all[ $id ]['notification']['url'] ) ? $all[ $id ]['notification']['url'] : '', 'billing' => isset( $all[ $id ]['billing'] ) ? $all[ $id ]['billing'] : null, 'amount' => isset( $all[ $id ]['amount'] ) ? $all[ $id ]['amount'] : null ) );
		break;

	case 'log':
		efb_pp_out( get_option( 'efb_e2e_payplug_log', array() ) );
		break;

	default:
		efb_pp_out( array( 'error' => 'unknown command ' . $efb_pp_cmd ) );
}
