<?php
/**
 * Environment of tests/test-human-shield-live.js on any WordPress (tests/wp-env.php):
 *
 *   setup     the Human Shield add-on installed and switched off (EFB's add-on switch AdnHSH, through EFB's settings
 *             function; the test turns it on through the add-on's screen and puts it back), and a published page with a
 *             four-field guest form. Prints the form id and the page address.
 *             The site is put on Pro (Human Shield is a Pro add-on: on another plan its screen is core's Pro card).
 *   teardown  the add-on switch, the plan and the Human Shield settings as they were; the form and the page removed.
 *
 * On the owner's site these were form 134 on post 209 and an add-on already installed and switched off.
 * Run: php tests/seed-human-shield-live-env.php setup|teardown
 */

require_once __DIR__ . '/wp-env.php';
$wp_load = efb_test_wp_load();
if ( ! is_readable( $wp_load ) ) {
	echo json_encode( array( 'ok' => false, 'error' => 'wp-load not found: ' . $wp_load ) );
	exit( 1 );
}
define( 'WP_USE_THEMES', false );
require_once $wp_load;

global $wpdb;
$mode   = isset( $argv[1] ) ? $argv[1] : '';
$marker = 'efb_test_human_shield_live_env';
$table  = $wpdb->prefix . 'emsfb_form';
$option = 'emsfb_human_shield_settings';

$write_flag = static function ( $on ) {
	get_setting_Emsfb( '_clear_cache' );
	return false !== \efbFunction::set_setting_Emsfb( (object) array( 'AdnHSH' => $on ? 1 : 0 ) );
};

// The plan as ctl.php plan sets it: the settings row's package_type too (a settings write syncs emsfb_pro from it).
$set_plan = static function ( $pkg ) {
	get_setting_Emsfb( '_clear_cache' );
	$ok = false !== \efbFunction::set_setting_Emsfb( (object) array( 'package_type' => (int) $pkg ) );
	update_option( 'emsfb_pro', (int) $pkg );
	return $ok;
};

if ( 'setup' === $mode ) {
	if ( ! is_dir( dirname( __DIR__ ) . '/vendor/human-shield' ) ) {
		echo json_encode( array( 'ok' => false, 'skip' => true, 'error' => 'the Human Shield add-on is not installed in vendor/human-shield' ) );
		exit( 0 );
	}
	$state = get_option( $marker );
	if ( ! is_array( $state ) ) {
		$s     = get_setting_Emsfb( 'decoded' );
		$state = array(
			'flag'           => is_object( $s ) && ! empty( $s->AdnHSH ) ? 1 : 0,
			'had_settings'   => false !== get_option( $option, false ),
			'settings'       => get_option( $option, false ),
			'pro'            => get_option( 'emsfb_pro', null ),
		);
		$field = static function ( $id, $name, $amount ) {
			return array( 'id_' => $id, 'dataId' => $id . '-id', 'type' => 'text', 'elementId' => 'text', 'placeholder' => $name,
				'value' => '', 'size' => 100, 'message' => '', 'id' => '', 'classes' => '', 'name' => $name, 'required' => '0',
				'amount' => $amount, 'step' => '1', 'label_position' => 'up' );
		};
		$structure = array(
			array( 'type' => 'form', 'steps' => '1', 'formName' => 'EFB Human Shield live', 'email' => '', 'sendEmail' => '',
				'trackingCode' => '1', 'EfbVersion' => '2', 'button_single_text' => 'Submit', 'button_state' => 'single',
				'captcha' => '', 'thank_you' => 'msg', 'thank_you_message' => array( 'thankYou' => 'Thank you.', 'done' => 'Done' ) ),
			array( 'id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1', 'name' => 'Contact', 'step' => '1', 'amount' => 1, 'visible' => '1' ),
			$field( 'hsl1', 'Name', 2 ), $field( 'hsl2', 'City', 3 ), $field( 'hsl3', 'Company', 4 ), $field( 'hsl4', 'Note', 5 ),
		);
		$wpdb->insert( $table, array( 'form_name' => 'EFB Human Shield live', 'form_structer' => wp_json_encode( $structure ), 'form_email' => '',
			'form_type' => 'form', 'form_created_by' => '1', 'form_access_by' => 'all', 'status' => 1 ) );
		$state['form_id'] = (int) $wpdb->insert_id;
		$state['page_id'] = (int) wp_insert_post( array( 'post_title' => 'EFB Human Shield live', 'post_name' => 'efb-human-shield-live',
			'post_content' => '[EMS_Form_Builder id="' . $state['form_id'] . '"]', 'post_status' => 'publish', 'post_type' => 'page' ) );
		update_option( $marker, $state, false );
	}
	// Installed and switched off: the state the test starts from (as on the owner's site). The screen's Enabled switch
	// is EFB's add-on switch AdnHSH (the add-on's screen is registered whenever its files are there); the test turns it
	// on through that screen and puts it back. The site's own switch and screen values come back at teardown.
	// Human Shield is a Pro add-on: on another plan its screen is core's Pro card (vendores-efb 208a437, plan gating on
	// menus). The test runs on Pro; teardown puts the site's plan back.
	$ok = $state['form_id'] > 0 && $state['page_id'] > 0 && $write_flag( false ) && $set_plan( 1 );
	echo json_encode( array( 'ok' => $ok, 'form_id' => $state['form_id'], 'url' => get_permalink( $state['page_id'] ) ) );
	exit( $ok ? 0 : 1 );
}

if ( 'teardown' === $mode ) {
	$state = get_option( $marker );
	$ok    = true;
	if ( is_array( $state ) ) {
		$ok = $write_flag( ! empty( $state['flag'] ) );
		if ( array_key_exists( 'pro', $state ) && null !== $state['pro'] ) {
			$ok = $set_plan( (int) $state['pro'] ) && $ok;
		}
		if ( $state['had_settings'] ) {
			update_option( $option, $state['settings'], false );
		} else {
			delete_option( $option );
		}
		if ( ! empty( $state['form_id'] ) ) {
			$wpdb->delete( $wpdb->prefix . 'emsfb_msg_', array( 'form_id' => (int) $state['form_id'] ) );
			$wpdb->delete( $table, array( 'form_id' => (int) $state['form_id'] ) );
		}
		if ( ! empty( $state['page_id'] ) ) {
			wp_delete_post( (int) $state['page_id'], true );
		}
		delete_option( $marker );
	}
	echo json_encode( array( 'ok' => $ok, 'restored' => is_array( $state ) ) );
	exit( $ok ? 0 : 1 );
}

echo json_encode( array( 'ok' => false, 'error' => 'unknown mode' ) );
exit( 1 );
