<?php
/**
 * Site control for the F12b tests (CAPTCHA providers; tests/test-*-captcha*.php, tests/test-secret-redaction.php,
 * tests/test-reply-captcha-http.php, tests/e2e-wp/scenario-f12b.js):
 *
 *   wp eval-file tests/e2e-wp/ctl-f12b.php <command> [args]
 *
 *   mu                         copy mu-plugins/efb-e2e-f12.php and efb-e2e-f12b.php into the site's mu-plugins
 *   mock <0|1>                 the F12b siteverify stand-in (efb_e2e_f12b_mock) and its call log cleared
 *   calls                      the recorded siteverify calls (URL, body keys, header names; never a secret)
 *   auth-form <login|register> [row-0 k=v&…]   a login or registration form (fields emaillogin / passwordlogin or
 *                              usernameRegisterEFB / emailRegisterEFB / passwordRegisterEFB), saved through the sanitizer
 *   user <login>               a subscriber with password <login>-pass-123 (made or reset); user-del <login>; user-exists <login>
 *   cpv <id> <public JSON> <secret JSON>   an add-on provider's settings through Admin::save() (the screen's path)
 *   cpv-raw <id>               what the option holds: public values, which secrets are set, whether they are encrypted
 *   rl-reset [scope …]         drop the counters of these Rate_Limit scopes (default rply rplt cptk cpch)
 *   providers                  the registry (id, addon, state, ready) and its registration errors
 *   notice-reset               remove every user's "spam notice dismissed" mark (D2)
 *   msg-uid <msg_id> <login>   the signed-in sender of an entry; cap <login> <cap> [role]; rpcp  seconds left of the reply passes
 *   effective <form_id>        Registry::effective_for() of a stored form
 *   settings-redacted          the settings row as an EFB screen gets it; secret <key> [expected] → {set, equals}
 *
 * Every command prints one JSON document on its last line. Local test site only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI\n" );
	exit( 1 );
}

$efb_b_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$efb_b_cmd  = isset( $efb_b_args[0] ) ? $efb_b_args[0] : 'providers';
$efb_b_rest = array_slice( $efb_b_args, 1 );

function efb_b_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}

global $wpdb;

switch ( $efb_b_cmd ) {
	case 'mu':
		$dir = WPMU_PLUGIN_DIR;
		if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );
		$ok = true;
		foreach ( array( 'efb-e2e-f12.php', 'efb-e2e-f12b.php' ) as $f ) $ok = copy( __DIR__ . '/mu-plugins/' . $f, $dir . '/' . $f ) && $ok;
		efb_b_out( array( 'ok' => $ok ) );
		break;

	case 'mock':
		$on = isset( $efb_b_rest[0] ) && '1' === $efb_b_rest[0];
		if ( $on ) update_option( 'efb_e2e_f12b_mock', 1, false ); else delete_option( 'efb_e2e_f12b_mock' );
		delete_option( 'efb_e2e_f12b_calls' );
		efb_b_out( array( 'ok' => true, 'mock' => $on ) );
		break;

	case 'calls':
		efb_b_out( get_option( 'efb_e2e_f12b_calls', array() ) );
		break;

	case 'auth-form':
		wp_set_current_user( 1 );
		$type = isset( $efb_b_rest[0] ) && 'register' === $efb_b_rest[0] ? 'register' : 'login';
		$f = function ( $id, $t ) { return array( 'id_' => $id, 'dataId' => $id . '-id', 'type' => $t, 'id' => '', 'name' => $id, 'required' => 1, 'amount' => 2, 'step' => 1, 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6', 'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'classes' => '', 'message' => '', 'placeholder' => '', 'value' => '', 'icon_input' => '', 'size' => 100 ); };
		$row0 = array( 'type' => $type, 'steps' => 1, 'formName' => 'F12b ' . $type, 'email' => 'owner@example.test', 'EfbVersion' => 2, 'button_single_text' => ucfirst( $type ), 'button_state' => 'single', 'captcha' => '1', 'thank_you' => 'msg', 'thank_you_message' => array( 'thankYou' => 'ok', 'done' => 'ok' ), 'show_icon' => '1', 'icon' => 'bi-person', 'button_color' => 'btn-darkb', 'dShowBg' => '1', 'btns_align' => 'justify-content-center' );
		if ( isset( $efb_b_rest[1] ) && '' !== $efb_b_rest[1] ) {
			foreach ( explode( '&', $efb_b_rest[1] ) as $kv ) { list( $k, $v ) = array_pad( explode( '=', $kv, 2 ), 2, '' ); $row0[ $k ] = urldecode( $v ); }
		}
		// label_align (row-0 key here) is the fields' own, as the builder writes it (txt-right on a right-to-left site).
		$align = isset( $row0['label_align'] ) && in_array( $row0['label_align'], array( 'txt-left', 'txt-right', 'txt-center' ), true ) ? $row0['label_align'] : 'txt-left';
		unset( $row0['label_align'] );
		$rows = array( $row0, array( 'id_' => '1', 'type' => 'step', 'dataId' => '1', 'id' => '1', 'name' => 'Step 1', 'step' => 1, 'amount' => 1, 'visible' => 1, 'icon' => 'bi-person', 'EfbVersion' => 2 ) );
		if ( 'login' === $type ) { $rows[] = $f( 'emaillogin', 'text' ); $rows[] = $f( 'passwordlogin', 'password' ); }
		else { $rows[] = $f( 'usernameRegisterEFB', 'text' ); $rows[] = $f( 'emailRegisterEFB', 'email' ); $rows[] = $f( 'passwordRegisterEFB', 'password' ); }
		foreach ( $rows as $i => $r ) if ( $i > 1 ) $rows[ $i ]['label_align'] = $align;
		$clean = get_efbFunction()->sanitize_obj_msg_efb( $rows );
		$json = str_replace( '"', '\\"', wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) );
		$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => 'F12b ' . $type, 'form_structer' => $json, 'form_email' => '', 'form_type' => $type, 'form_created_by' => '1', 'form_access_by' => '', 'form_create_date' => current_time( 'mysql' ), 'status' => 1 ) );
		efb_b_out( array( 'form_id' => (int) $wpdb->insert_id, 'rows' => $clean ) );
		break;

	case 'user':
		$login = sanitize_user( isset( $efb_b_rest[0] ) ? $efb_b_rest[0] : 'efb_f12b_user' );
		$uid = username_exists( $login );
		if ( ! $uid ) $uid = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $login . '-pass-123', 'user_email' => $login . '@example.test', 'role' => 'subscriber' ) );
		else wp_set_password( $login . '-pass-123', $uid );
		efb_b_out( array( 'id' => (int) $uid, 'login' => $login, 'password' => $login . '-pass-123' ) );
		break;

	case 'user-exists':
		efb_b_out( (bool) username_exists( isset( $efb_b_rest[0] ) ? $efb_b_rest[0] : '' ) );
		break;

	case 'user-del':
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $efb_b_rest as $login ) { $u = get_user_by( 'login', $login ); if ( $u ) wp_delete_user( $u->ID ); }
		efb_b_out( array( 'ok' => true ) );
		break;

	case 'cpv':
		$id = isset( $efb_b_rest[0] ) ? sanitize_key( $efb_b_rest[0] ) : '';
		$pub = isset( $efb_b_rest[1] ) ? json_decode( $efb_b_rest[1], true ) : array();
		$sec = isset( $efb_b_rest[2] ) ? json_decode( $efb_b_rest[2], true ) : array();
		efb_b_out( \Emsfb\Captcha\Admin::save( $id, is_array( $pub ) ? $pub : array(), is_array( $sec ) ? $sec : array() ) );
		break;

	case 'cpv-raw':
		$raw = get_option( \Emsfb\Captcha\Registry::option( isset( $efb_b_rest[0] ) ? $efb_b_rest[0] : '' ), null );
		$out = array( 'exists' => is_array( $raw ), 'public' => is_array( $raw ) ? ( $raw['public'] ?? array() ) : null, 'secret' => array() );
		if ( is_array( $raw ) ) foreach ( (array) ( $raw['secret'] ?? array() ) as $k => $v ) $out['secret'][ $k ] = array( 'set' => '' !== $v, 'encrypted' => is_string( $v ) && 0 === strpos( $v, 'enc:v1:' ) );
		$out['autoload_public'] = get_option( 'emsfb_cpv_public', null );
		efb_b_out( $out );
		break;

	case 'rl-reset':
		$scopes = $efb_b_rest ? $efb_b_rest : array( 'rply', 'rplt', 'cptk', 'cpch' );
		if ( class_exists( '\Emsfb\Rate_Limit' ) ) \Emsfb\Rate_Limit::reset_scopes( $scopes );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_efb\\_cptk\\_%' OR option_name LIKE '\\_transient\\_timeout\\_efb\\_cptk\\_%' OR option_name LIKE '\\_transient\\_efb\\_rpcp\\_%' OR option_name LIKE '\\_transient\\_timeout\\_efb\\_rpcp\\_%'" ); // phpcs:ignore
		efb_b_out( array( 'ok' => true, 'scopes' => $scopes ) );
		break;

	case 'providers':
		$out = array();
		foreach ( \Emsfb\Captcha\Registry::all() as $id => $p ) $out[ $id ] = array( 'addon' => $p->addon_key(), 'state' => \Emsfb\Captcha\Registry::state( $p ), 'ready' => $p->ready(), 'kind' => $p->kind() );
		efb_b_out( array( 'providers' => $out, 'errors' => \Emsfb\Captcha\Registry::errors() ) );
		break;

	case 'msg-uid':
		// The signed-in sender of an entry: <msg_id> <user login> (emsfb_msg_.uid).
		$u = get_user_by( 'login', isset( $efb_b_rest[1] ) ? $efb_b_rest[1] : '' );
		$wpdb->update( $wpdb->prefix . 'emsfb_msg_', array( 'uid' => $u ? (int) $u->ID : 0 ), array( 'msg_id' => (int) ( $efb_b_rest[0] ?? 0 ) ) );
		efb_b_out( array( 'ok' => (bool) $u, 'uid' => $u ? (int) $u->ID : 0 ) );
		break;

	case 'cap':
		// A capability for a user: <login> <cap> [role] (e.g. an editor who answers entries: Emsfb).
		$u = get_user_by( 'login', isset( $efb_b_rest[0] ) ? $efb_b_rest[0] : '' );
		if ( $u && ! empty( $efb_b_rest[2] ) ) $u->set_role( sanitize_key( $efb_b_rest[2] ) );
		if ( $u && ! empty( $efb_b_rest[1] ) ) $u->add_cap( (string) $efb_b_rest[1] );
		efb_b_out( array( 'ok' => (bool) $u ) );
		break;

	case 'rpcp':
		// The reply passes (D3): seconds left of each (never the session or the code).
		$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_timeout\\_efb\\_rpcp\\_%'", ARRAY_A ); // phpcs:ignore
		efb_b_out( array_map( static function ( $r ) { return (int) $r['option_value'] - time(); }, (array) $rows ) );
		break;

	case 'notice-reset':
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", 'emsfb_spam_notice_dismissed' ) ); // phpcs:ignore
		efb_b_out( array( 'ok' => true ) );
		break;

	case 'settings-redacted':
		// What an EFB screen receives of the settings row (Admin::redact_settings()).
		efb_b_out( \Emsfb\Captcha\Admin::redact_settings( get_setting_Emsfb( 'decoded' ) ) );
		break;

	case 'secret':
		// The stored value of a secret key, for the save tests only: <key> → {set, value_is <expected>} (never printed).
		$s = get_setting_Emsfb( 'decoded' );
		$k = isset( $efb_b_rest[0] ) ? $efb_b_rest[0] : '';
		$v = is_object( $s ) && isset( $s->$k ) && is_scalar( $s->$k ) ? (string) $s->$k : '';
		efb_b_out( array( 'set' => '' !== $v, 'equals' => isset( $efb_b_rest[1] ) ? hash_equals( $v, (string) $efb_b_rest[1] ) : null ) );
		break;

	case 'effective':
		$rows = \Emsfb\Spam::form_rows( isset( $efb_b_rest[0] ) ? (int) $efb_b_rest[0] : 0 );
		$e = $rows ? \Emsfb\Captcha\Registry::effective_for( $rows[0] ) : null;
		efb_b_out( $e ? array( 'want' => $e['want'], 'id' => $e['id'], 'fallback' => $e['fallback'], 'why' => $e['why'], 'cfg' => \Emsfb\Spam::cfg( $rows[0] ) ) : null );
		break;

	default:
		efb_b_out( array( 'error' => 'unknown command ' . $efb_b_cmd ) );
}
