<?php
/**
 * Site control for the AI builder core API tests (tests/test-ai-core-apis-http.php, scenario "ai-*" of run.js):
 *
 *   wp eval-file tests/e2e-wp/ctl-ai.php <command> [args]
 *
 *   mu                         copy mu-plugins/efb-e2e-ai.php into the site's mu-plugins; clear what it recorded
 *   saved [clear]              what efb_form_saved / efb_form_created carried (the mu-plugin's record)
 *   statuses [clear]           what efb_form_status_changed carried
 *   import <file> [k=v&…]      Form_Package::import() of the package in <file> as user 1 (args: name, name_policy, status)
 *   read <form_id> [user]      Form_Package::read() (as user 1, or the given user id)
 *   update <form_id> <file> <base_hash> [user]   Form_Package::update() with the patches in <file>
 *   status <form_id> [draft|published]           Form_Status::get(), or set (as user 1)
 *   publish <form_id>          Form_Package::publish() as user 1
 *   raw-status <form_id> [v]   the stored emsfb_form.status (or set it to v: an old row 0 / NULL)
 *   templates <palette_id>     field_templates_efb(): the code and data of a refusal, or the row count and types
 *   caps [key]                 capabilities_efb() (one top-level key)
 *   app-password <login>       a new application password for that user (name "efb-e2e-ai")
 *   versions                   the plugin header, EMSFB_PLUGIN_VERSION, the registry's and the form package's versions
 *   delete <id,id,…>           delete forms (and their caches)
 *   pay-form <slug> <name> [draft]   a payment form (an e-mail field and the gateway's field from field_templates_efb())
 *                              imported as user 1 (name_policy keep); "draft" makes it a draft
 *   esign-form <name>          a form with a text field and a typed signature (esign_mode type), saved by import
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */
if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI\n" );
	exit( 1 );
}
$ai_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$ai_cmd  = isset( $ai_args[0] ) ? $ai_args[0] : 'caps';
$ai_rest = array_slice( $ai_args, 1 );

function efb_ai_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}
function efb_ai_err( $e ) {
	return array( 'error' => $e->get_error_code(), 'm' => $e->get_error_message(), 'data' => $e->get_error_data() );
}
function efb_ai_kv( $s ) {
	$o = array();
	foreach ( explode( '&', (string) $s ) as $kv ) {
		if ( '' === $kv ) continue;
		list( $k, $v ) = array_pad( explode( '=', $kv, 2 ), 2, '' );
		$o[ $k ] = urldecode( $v );
	}
	return $o;
}

switch ( $ai_cmd ) {
	case 'mu':
		copy( __DIR__ . '/mu-plugins/efb-e2e-ai.php', WPMU_PLUGIN_DIR . '/efb-e2e-ai.php' );
		delete_option( 'efb_e2e_ai_saved' );
		delete_option( 'efb_e2e_ai_status' );
		efb_ai_out( array( 'ok' => true ) );
		break;

	case 'saved':
	case 'statuses':
		$opt = 'saved' === $ai_cmd ? 'efb_e2e_ai_saved' : 'efb_e2e_ai_status';
		if ( isset( $ai_rest[0] ) && 'clear' === $ai_rest[0] ) delete_option( $opt );
		efb_ai_out( array_values( (array) get_option( $opt, array() ) ) );
		break;

	case 'import':
		wp_set_current_user( 1 );
		$pkg = json_decode( (string) file_get_contents( $ai_rest[0] ), true );
		$r = \Emsfb\Form_Package::import( $pkg, isset( $ai_rest[1] ) ? efb_ai_kv( $ai_rest[1] ) : array() );
		efb_ai_out( is_wp_error( $r ) ? efb_ai_err( $r ) : $r );
		break;

	case 'read':
		wp_set_current_user( isset( $ai_rest[1] ) ? (int) $ai_rest[1] : 1 );
		$r = \Emsfb\Form_Package::read( (int) $ai_rest[0] );
		efb_ai_out( is_wp_error( $r ) ? efb_ai_err( $r ) : $r );
		break;

	case 'update':
		wp_set_current_user( isset( $ai_rest[3] ) ? (int) $ai_rest[3] : 1 );
		$p = json_decode( (string) file_get_contents( $ai_rest[1] ), true );
		$r = \Emsfb\Form_Package::update( (int) $ai_rest[0], $p, array( 'base_hash' => isset( $ai_rest[2] ) ? (string) $ai_rest[2] : '' ) );
		efb_ai_out( is_wp_error( $r ) ? efb_ai_err( $r ) : $r );
		break;

	case 'status':
		wp_set_current_user( 1 );
		$r = isset( $ai_rest[1] ) ? \Emsfb\Form_Status::set( (int) $ai_rest[0], $ai_rest[1] ) : true;
		efb_ai_out( is_wp_error( $r ) ? efb_ai_err( $r ) : array( 'status' => \Emsfb\Form_Status::get( (int) $ai_rest[0] ) ) );
		break;

	case 'publish':
		wp_set_current_user( 1 );
		$r = \Emsfb\Form_Package::publish( (int) $ai_rest[0] );
		efb_ai_out( is_wp_error( $r ) ? efb_ai_err( $r ) : array( 'ok' => true, 'status' => \Emsfb\Form_Status::get( (int) $ai_rest[0] ) ) );
		break;

	case 'raw-status':
		global $wpdb;
		$t = $wpdb->prefix . 'emsfb_form';
		if ( isset( $ai_rest[1] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( 'NULL' === $ai_rest[1] ? $wpdb->prepare( "UPDATE `{$t}` SET status = NULL WHERE form_id = %d", (int) $ai_rest[0] ) : $wpdb->prepare( "UPDATE `{$t}` SET status = %d WHERE form_id = %d", (int) $ai_rest[1], (int) $ai_rest[0] ) );
			\Emsfb\Form_Status::flush();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$v = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM `{$t}` WHERE form_id = %d", (int) $ai_rest[0] ) );
		efb_ai_out( array( 'raw' => null === $v ? null : (int) $v, 'status' => \Emsfb\Form_Status::get( (int) $ai_rest[0] ) ) );
		break;

	case 'templates':
		wp_set_current_user( 1 );
		$r = get_efbFunction()->field_templates_efb( $ai_rest[0] );
		if ( is_wp_error( $r ) ) efb_ai_out( efb_ai_err( $r ) );
		efb_ai_out( array( 'rows' => count( (array) ( $r['rows'] ?? array() ) ), 'types' => array_values( array_unique( array_column( (array) ( $r['rows'] ?? array() ), 'type' ) ) ) ) );
		break;

	case 'caps':
		wp_set_current_user( 1 );
		$c = get_efbFunction()->capabilities_efb();
		efb_ai_out( isset( $ai_rest[0] ) ? ( isset( $c[ $ai_rest[0] ] ) ? $c[ $ai_rest[0] ] : null ) : array_keys( $c ) );
		break;

	case 'app-password':
		$u = get_user_by( 'login', $ai_rest[0] );
		if ( ! $u ) efb_ai_out( array( 'error' => 'no user' ) );
		$r = WP_Application_Passwords::create_new_application_password( $u->ID, array( 'name' => 'efb-e2e-ai ' . wp_generate_password( 6, false ) ) );
		efb_ai_out( is_wp_error( $r ) ? efb_ai_err( $r ) : array( 'user' => $u->user_login, 'password' => $r[0] ) );
		break;

	case 'versions':
		$head = get_file_data( WP_PLUGIN_DIR . '/easy-form-builder/emsfb.php', array( 'Version' => 'Version' ) );
		$api = efb_form_package_api();
		$c = get_efbFunction()->capabilities_efb();
		$t = get_efbFunction()->field_templates_efb( 'text' );
		efb_ai_out( array( 'header' => $head['Version'], 'constant' => EMSFB_PLUGIN_VERSION, 'registry' => $c['efb_version'], 'package' => $api['plugin_version'], 'templates' => $t['efb_version'], 'stored' => get_option( 'emsfb_version' ) ) );
		break;

	case 'delete':
		global $wpdb;
		foreach ( array_filter( array_map( 'intval', explode( ',', (string) $ai_rest[0] ) ) ) as $id ) {
			$wpdb->delete( $wpdb->prefix . 'emsfb_form', array( 'form_id' => $id ) );
			do_action( 'efb_form_deleted', $id );
		}
		efb_ai_out( array( 'ok' => true ) );
		break;

	case 'pay-form':
	case 'esign-form':
		wp_set_current_user( 1 );
		$fn = get_efbFunction();
		$name = isset( $ai_rest[ 'pay-form' === $ai_cmd ? 1 : 0 ] ) ? $ai_rest[ 'pay-form' === $ai_cmd ? 1 : 0 ] : 'AI e2e';
		// Row 0 and step 1 as the builder writes them (the canvas reads their style keys), like ctl.php f03-form.
		$rows = array( array( 'type' => 'pay-form' === $ai_cmd ? 'payment' : 'form', 'steps' => 1, 'formName' => $name, 'email' => '', 'trackingCode' => '1', 'EfbVersion' => 2, 'button_single_text' => 'Send', 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_Next_icon' => 'bi-chevron-right', 'button_Previous_icon' => 'bi-chevron-left', 'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_state' => 'single', 'show_icon' => '1', 'show_pro_bar' => '1', 'font' => '1', 'captcha' => '', 'stateForm' => 0, 'thank_you' => 'msg', 'thank_you_message' => array( 'icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thank you', 'done' => 'Done', 'trackingCode' => 'Confirmation Code', 'pleaseFillInRequiredFields' => 'Please fill in all required fields.' ), 'dShowBg' => '1', 'btns_align' => 'justify-content-center', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb' ),
			array( 'id_' => '1', 'type' => 'step', 'dataId' => '1', 'classes' => '', 'id' => '1', 'name' => 'Step', 'icon' => 'bi-ui-checks-grid', 'step' => 1, 'amount' => 1, 'EfbVersion' => 2, 'message' => '', 'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5', 'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-darkb', 'visible' => 1 ) );
		$add = function ( $pid, $extra = array() ) use ( $fn, &$rows ) {
			$t = $fn->field_templates_efb( $pid, $extra );
			if ( is_wp_error( $t ) ) efb_ai_out( efb_ai_err( $t ) );
			foreach ( $t['rows'] as $r ) $rows[] = $r;
			foreach ( (array) $t['form_patch'] as $k => $v ) $rows[0][ $k ] = $v;
			return $t['rows'][0]['id_'];
		};
		$ids = array( 'email' => $add( 'email' ) );
		if ( 'pay-form' === $ai_cmd ) {
			$ids['pay'] = $add( $ai_rest[0] );
		} else {
			$ids['text'] = $add( 'text', array( 'label' => 'Full name' ) );
			$ids['esign'] = $add( 'esign', array( 'label' => 'Signature' ) );
			foreach ( $rows as $i => $r ) if ( isset( $r['type'] ) && 'esign' === $r['type'] ) { $rows[ $i ]['esign_mode'] = 'type'; $rows[ $i ]['esign_font'] = 'latin'; }
		}
		foreach ( $rows as $i => $r ) if ( $i > 0 ) { $rows[ $i ]['amount'] = $i; $rows[ $i ]['step'] = 1; }
		$r = \Emsfb\Form_Package::import( array( 'schema' => 1, 'form' => array( 'form_name' => $name, 'form_structer' => $rows ) ), array( 'name_policy' => 'keep', 'status' => in_array( 'draft', $ai_rest, true ) ? 'draft' : 'published' ) );
		efb_ai_out( is_wp_error( $r ) ? efb_ai_err( $r ) : array( 'form_id' => $r['form_id'], 'ids' => $ids, 'status' => $r['status'] ) );
		break;

	default:
		efb_ai_out( array( 'error' => 'unknown command ' . $ai_cmd ) );
}
