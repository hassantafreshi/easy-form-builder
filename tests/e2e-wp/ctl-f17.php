<?php
/**
 * F17 helpers for tests/e2e-wp/scenario-f17.js, run through `wp eval-file` (see README.md).
 *
 *   form <name> <groups json> [old]   a form with name / address groups, built from Address_Schemes' definitions the way the
 *                                     builder's chooser builds them (efbNaPatch), saved through the real sanitizer. groups:
 *                                     [{kind: name|address, format, strict?, lookup?, mode?, step?}]; "old" adds the
 *                                     composite of before F17 (firstName, lastName, country, state, city, address_line,
 *                                     postalcode without the keys). State lists of a fixed country get a few options of
 *                                     the test data (no CDN). The answer: form_id, groups [{grp, kind, format, parts: {part: id_}}], old.
 *   rows <form id>                    the stored rows of a form (decoded)
 *   entry <form id>                   the last stored answer of the form (rows decoded) and its e-mail body (P22 line)
 *   display <rows json> <locale>      efb_name_address_groups() of rows (PHP twin check)
 *   page <title> <form id>            a page with the form
 *   mu                                copy mu-plugins/efb-e2e-f17.php (the Nominatim stand-in) into the site
 *   geo [clear]                       the stand-in's calls; clear also empties the search cache and the lock
 *   adr <0|1> [mode] [url]            the Address & Postal add-on off / on (settings flag AdnADR) and its search settings
 *                                     (adr_lookup of the settings row, the F12b-L rule; the answer also shows the old option)
 *   adr-old <mode> [url]              a site from before the move: the settings in the old option emsfb_adr_lookup, none in
 *                                     the row, the move not done (emsfb_adr_moved removed)
 *   adr-row                           the settings row as stored (decoded; a test site only: it may hold keys)
 *   caps                              the AutoPilot capabilities (Builder_Api::capabilities(), as administrator)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}
$f17_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$f17_cmd  = isset( $f17_args[0] ) ? $f17_args[0] : '';
$f17_rest = array_slice( $f17_args, 1 );
function efb_f17_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}
function efb_f17_rows( $fid ) {
	global $wpdb;
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT form_structer FROM {$wpdb->prefix}emsfb_form WHERE form_id = %d", (int) $fid ) ); // phpcs:ignore
	return json_decode( emsfb_json_normalize_efb( (string) $raw ), true );
}
/** A few provinces of each fixed-country list (value as the builder stores it: native (English); s2 = the city file's code). */
function efb_f17_states( $cc ) {
	$d = array(
		'ir' => array( array( 'تهران', 'Tehran', 'tehran' ), array( 'اصفهان', 'Isfahan', 'isfahan' ), array( 'فارس', 'Fars', 'fars' ) ),
		'sa' => array( array( 'الرياض', 'Riyadh', 'riyadh' ), array( 'مكة المكرمة', 'Makkah', 'makkah' ) ),
		'ae' => array( array( 'دبي', 'Dubai', 'dubai' ), array( 'أبو ظبي', 'Abu Dhabi', 'abu_dhabi' ) ),
		'eg' => array( array( 'القاهرة', 'Cairo', 'cairo' ), array( 'الإسكندرية', 'Alexandria', 'alexandria' ) ),
		'us' => array( array( 'California', 'California', 'ca' ), array( 'New York', 'New York', 'ny' ) ),
		'ca' => array( array( 'Ontario', 'Ontario', 'on' ), array( 'Québec', 'Quebec', 'qc' ) ),
		'jp' => array( array( '東京都', 'Tokyo', 'tokyo' ), array( '大阪府', 'Osaka', 'osaka' ) ),
		'tr' => array( array( 'İstanbul', 'Istanbul', 'istanbul' ), array( 'Ankara', 'Ankara', 'ankara' ) ),
	);
	return isset( $d[ $cc ] ) ? $d[ $cc ] : array();
}

switch ( $f17_cmd ) {
	case 'form':
		wp_set_current_user( 1 );
		global $wpdb;
		$name   = isset( $f17_rest[0] ) && '' !== $f17_rest[0] ? $f17_rest[0] : 'F17 form';
		$groups = isset( $f17_rest[1] ) ? json_decode( $f17_rest[1], true ) : array();
		$old    = isset( $f17_rest[2] ) && 'old' === $f17_rest[2];
		$rid    = function () { return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz' ), 0, 9 ); };
		$steps  = 1;
		foreach ( (array) $groups as $g ) $steps = max( $steps, isset( $g['step'] ) ? (int) $g['step'] : 1 );
		$base = array( 'amount' => 0, 'size' => 100, 'required' => 0, 'label_text_size' => 'fs-6', 'label_position' => 'up', 'el_text_size' => 'fs-6', 'label_text_color' => 'text-labelEfb', 'el_border_color' => 'border-d', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'el_height' => 'h-d-efb', 'label_align' => 'txt-left', 'message_align' => 'justify-content-start', 'el_align' => 'justify-content-start', 'classes' => '', 'message' => '', 'placeholder' => '', 'value' => '', 'icon_input' => '', 'mobile_size' => '100' );
		$row0 = array( 'type' => 'form', 'steps' => $steps, 'formName' => $name, 'email' => 'owner@example.test', 'trackingCode' => '1', 'EfbVersion' => 2, 'button_single_text' => 'Send', 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'button_Next_icon' => 'bi-chevron-right', 'button_Previous_icon' => 'bi-chevron-left', 'button_color' => 'btn-darkb', 'icon' => 'bi-ui-checks-grid', 'button_state' => 'single', 'email_to' => '', 'show_icon' => '1', 'captcha' => '', 'sendEmail' => '1', 'stateForm' => 0, 'thank_you' => 'msg', 'thank_you_message' => array( 'icon' => 'bi-hand-thumbs-up', 'thankYou' => 'Thank you', 'done' => 'Done', 'trackingCode' => 'Confirmation Code', 'pleaseFillInRequiredFields' => 'Please fill in all required fields.' ), 'dShowBg' => '1', 'btns_align' => 'justify-content-center', 'label_text_color' => 'text-labelEfb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb', 'val_timing' => 'blur', 'err_summary' => '1', 'warn_unsaved' => '0' );
		$stepRow = function ( $n ) { return array( 'id_' => (string) $n, 'type' => 'step', 'dataId' => (string) $n, 'classes' => '', 'id' => (string) $n, 'name' => 'Step ' . $n, 'icon' => 'bi-ui-checks-grid', 'step' => $n, 'amount' => $n === 1 ? 1 : 1000 * $n, 'EfbVersion' => 2, 'message' => '', 'label_text_size' => 'fs-5', 'el_text_size' => 'fs-5', 'label_text_color' => 'text-darkb', 'el_text_color' => 'text-labelEfb', 'message_text_color' => 'text-muted', 'icon_color' => 'text-darkb', 'visible' => 1 ); };
		$rows = array( $row0 );
		for ( $s = 1; $s <= $steps; $s++ ) $rows[] = $stepRow( $s );
		$fn = get_efbFunction();
		$text = $fn->text_efb( 1 );
		$out = array();
		$amount = 2;
		$opt = function ( $parent, $value, $extra, $step, $amount ) use ( $rid ) { $id = $rid(); return array_merge( array( 'id_' => $id, 'dataId' => $id . '-id', 'parent' => $parent, 'type' => 'option', 'value' => $value, 'id_op' => $id, 'step' => $step, 'amount' => $amount ), $extra ); };
		$add = function ( $type, $el, $fields ) use ( &$rows, &$amount, $rid, $base ) {
			$id = $rid();
			$rows[] = array_merge( $base, array( 'id_' => $id, 'dataId' => $id . '-id', 'type' => $type, 'elementId' => $el, 'amount' => $amount++ ), $fields );
			return $id;
		};
		foreach ( (array) $groups as $g ) {
			$kind = $g['kind'] === 'name' ? 'name' : 'address';
			$fmt  = (string) $g['format'];
			$set  = $kind === 'name' ? \Emsfb\Address_Schemes::name_formats() : \Emsfb\Address_Schemes::schemes();
			if ( ! isset( $set[ $fmt ] ) ) efb_f17_out( array( 'error' => 'unknown format ' . $fmt ) );
			$step = isset( $g['step'] ) ? (int) $g['step'] : 1;
			$grp = ''; $parts = array();
			foreach ( $set[ $fmt ]['parts'] as $i => $p ) {
				$f = array( 'name' => isset( $text[ $p['lb'] ] ) ? $text[ $p['lb'] ] : $p['lb'], 'required' => $p['r'] ? 1 : 0, 'size' => (string) $p['sz'], 'step' => $step, 'cmp_kind' => $kind );
				if ( $kind === 'name' ) { $f['name_format'] = $fmt; $f['name_part'] = $p['p']; if ( $p['t'] === 'text' ) $f['name_strict'] = isset( $g['strict'] ) ? (string) $g['strict'] : '1'; }
				else {
					$f['addr_scheme'] = $fmt; $f['addr_part'] = $p['p'];
					if ( ! empty( $p['mx'] ) ) $f['mlen'] = (string) $p['mx'];
					if ( ! empty( $p['cas'] ) ) { $f['addr_cascade'] = '1'; $f['statePov'] = ''; $f['country'] = isset( $set[ $fmt ]['cc'] ) ? strtolower( $set[ $fmt ]['cc'] ) : ''; }
					if ( ! empty( $p['cc'] ) ) { $f['country'] = $p['cc']; }
					if ( ! empty( $p['mask'] ) && ! empty( $g['masks'] ) ) $f['mask_preset'] = $p['mask'];
					if ( $i === 0 && ! empty( $g['lookup'] ) ) $f['addr_lookup'] = '1';
					if ( $i === 0 && ! empty( $g['mode'] ) ) $f['addr_req_mode'] = 'sa_short_or_full';
				}
				$id = $add( $p['t'], $p['el'], $f );
				if ( $grp === '' ) $grp = $id;
				$parts[ $p['p'] ] = $id;
				$k = count( $rows ) - 1;
				$rows[ $k ]['cmp_grp'] = $grp;
				if ( $p['t'] === 'select' ) foreach ( array( 'Mr', 'Mrs', 'Dr' ) as $v ) $rows[] = $opt( $id, $v, array(), $step, $rows[ $k ]['amount'] );
				if ( $p['t'] === 'stateProvince' && ! empty( $p['cc'] ) ) foreach ( efb_f17_states( $p['cc'] ) as $s ) $rows[] = $opt( $id, $s[0] === $s[1] ? $s[1] : $s[0] . ' (' . $s[1] . ')', array( 's2' => $s[2], 'n' => $s[1], 'l' => $s[0] ), $step, $rows[ $k ]['amount'] );
				if ( $p['t'] === 'stateProvince' && empty( $p['cc'] ) ) { $rows[ $k ]['country'] = 'US'; foreach ( efb_f17_states( 'us' ) as $s ) $rows[] = $opt( $id, $s[1], array( 's2' => $s[2], 'n' => $s[1], 'l' => $s[0] ), $step, $rows[ $k ]['amount'] ); }
				if ( $p['t'] === 'conturyList' ) foreach ( array( array( 'United States', 'us' ), array( 'Germany', 'de' ), array( 'Iran', 'ir' ) ) as $c ) $rows[] = $opt( $id, $c[0], array( 'id_op' => $c[1] ), $step, $rows[ $k ]['amount'] );
				if ( $p['t'] === 'cityList' && empty( $p['cas'] ) ) foreach ( array( 'Los Angeles', 'San Diego' ) as $c ) $rows[] = $opt( $id, $c, array(), $step, $rows[ $k ]['amount'] );
			}
			$out[] = array( 'grp' => $grp, 'kind' => $kind, 'format' => $fmt, 'parts' => $parts );
		}
		$oldIds = array();
		if ( $old ) {
			foreach ( array( array( 'text', 'firstName', 'First name', '50' ), array( 'text', 'lastName', 'Last name', '50' ), array( 'address_line', 'address_line', 'Address', '100' ), array( 'postalcode', 'postalcode', 'Postal Code', '100' ) ) as $o ) {
				$oldIds[ $o[1] ] = $add( $o[0], $o[1], array( 'name' => $o[2], 'size' => $o[3], 'step' => 1, 'required' => 0 ) );
			}
		}
		$clean = $fn->sanitize_obj_msg_efb( $rows );
		if ( class_exists( '\Emsfb\Form_Gate' ) ) $clean = \Emsfb\Form_Gate::normalize_form_settings( $clean, null );
		$json = str_replace( '"', '\\"', wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) );
		$wpdb->insert( $wpdb->prefix . 'emsfb_form', array( 'form_name' => $name, 'form_structer' => $json, 'form_email' => '', 'form_type' => 'form', 'form_created_by' => '1', 'form_access_by' => '', 'form_create_date' => current_time( 'mysql' ), 'status' => 1 ) );
		$fid = (int) $wpdb->insert_id;
		do_action( 'efb_form_created', $fid, $clean, array( 'form_id' => $fid, 'source' => 'e2e', 'user_id' => 1 ) );
		efb_f17_out( array( 'form_id' => $fid, 'groups' => $out, 'old' => $oldIds ) );
		break;
	case 'rows':
		efb_f17_out( efb_f17_rows( (int) $f17_rest[0] ) );
		break;
	case 'entry':
		global $wpdb;
		$fid = (int) $f17_rest[0];
		$m = $wpdb->get_row( $wpdb->prepare( "SELECT msg_id, content, track FROM {$wpdb->prefix}emsfb_msg_ WHERE form_id = %d ORDER BY msg_id DESC LIMIT 1", $fid ), ARRAY_A ); // phpcs:ignore
		if ( ! $m ) efb_f17_out( array( 'entry' => null ) );
		$rows = json_decode( str_replace( '@efb@dq#', '\"', stripslashes( (string) $m['content'] ) ), true );
		$pub = new \Emsfb\_Public();
		$mail = $pub->email_get_content_efb( is_array( $rows ) ? $rows : array(), $m['track'] );
		efb_f17_out( array( 'entry' => array( 'msg_id' => (int) $m['msg_id'], 'track' => $m['track'], 'rows' => $rows, 'groups' => efb_name_address_groups( is_array( $rows ) ? $rows : array() ), 'mail' => $mail ) ) );
		break;
	case 'display':
		$rows = json_decode( $f17_rest[0], true );
		$loc = isset( $f17_rest[1] ) ? $f17_rest[1] : 'en_US';
		efb_f17_out( efb_name_address_groups( is_array( $rows ) ? $rows : array(), $loc ) );
		break;
	case 'page':
		$title = isset( $f17_rest[0] ) ? $f17_rest[0] : 'F17 page';
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => '<!-- wp:shortcode -->[EMS_Form_Builder id=' . (int) $f17_rest[1] . ']<!-- /wp:shortcode -->', 'meta_input' => array( '_efb_e2e' => 1 ) ) );
		efb_f17_out( array( 'id' => $id, 'url' => get_permalink( $id ) ) );
		break;
	case 'mu':
		$dst = WPMU_PLUGIN_DIR . '/efb-e2e-f17.php';
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) wp_mkdir_p( WPMU_PLUGIN_DIR );
		$src = dirname( __FILE__ ) . '/mu-plugins/efb-e2e-f17.php';
		$same = is_readable( $dst ) && md5_file( $dst ) === md5_file( $src );
		$ok = $same || copy( $src, $dst );
		if ( ! $same ) sleep( 3 );
		efb_f17_out( array( 'copied' => $ok ) );
		break;
	case 'geo':
		global $wpdb;
		if ( isset( $f17_rest[0] ) && 'clear' === $f17_rest[0] ) {
			delete_option( 'efb_e2e_f17_calls' );
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_efb\\_geo\\_%' OR option_name LIKE '\\_transient\\_timeout\\_efb\\_geo\\_%'" ); // phpcs:ignore
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_vrfy_rl_ WHERE scope = %s", 'adrgeo' ) ); // phpcs:ignore
		}
		efb_f17_out( array( 'calls' => array_values( (array) get_option( 'efb_e2e_f17_calls', array() ) ) ) );
		break;
	case 'adr':
		$on = isset( $f17_rest[0] ) && '1' === $f17_rest[0];
		$s = get_setting_Emsfb( 'decoded' );
		$s = is_object( $s ) ? $s : new stdClass();
		$s->AdnADR = $on ? 1 : 0;
		if ( isset( $f17_rest[1] ) ) $s->adr_lookup = array( 'mode' => $f17_rest[1], 'url' => isset( $f17_rest[2] ) ? $f17_rest[2] : '' );
		\efbFunction::set_setting_Emsfb( $s );
		$s = get_setting_Emsfb( 'decoded' );
		efb_f17_out( array( 'AdnADR' => $on, 'lookup' => is_object( $s ) && isset( $s->adr_lookup ) ? $s->adr_lookup : null, 'old' => get_option( 'emsfb_adr_lookup', null ), 'moved' => get_option( 'emsfb_adr_moved', null ) ) );
		break;
	case 'adr-row':
		global $wpdb;
		efb_f17_out( emsfb_json_decode_efb( (string) $wpdb->get_var( "SELECT setting FROM {$wpdb->prefix}emsfb_setting ORDER BY id DESC LIMIT 1" ), true ) ); // phpcs:ignore
		break;
	case 'caps':
		wp_set_current_user( 1 );
		efb_f17_out( \Emsfb\Builder_Api::capabilities() );
		break;
	case 'adr-old':
		// The row is written straight to the table (set_setting_Emsfb() keeps a key that is left out).
		global $wpdb;
		$row = $wpdb->get_row( "SELECT id, setting FROM {$wpdb->prefix}emsfb_setting ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore
		$escaped = $row && strpos( (string) $row['setting'], '\\"' ) !== false;
		$d = $row ? emsfb_json_decode_efb( (string) $row['setting'], true ) : null;
		if ( is_array( $d ) ) {
			unset( $d['adr_lookup'] );
			$json = wp_json_encode( $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$wpdb->update( $wpdb->prefix . 'emsfb_setting', array( 'setting' => $escaped ? str_replace( '"', '\\"', $json ) : $json ), array( 'id' => (int) $row['id'] ) );
		}
		update_option( 'emsfb_adr_lookup', array( 'mode' => isset( $f17_rest[0] ) ? $f17_rest[0] : 'proxy', 'url' => isset( $f17_rest[1] ) ? $f17_rest[1] : '' ), false );
		delete_option( 'emsfb_adr_moved' );
		delete_option( 'emsfb_settings' );
		delete_transient( 'emsfb_settings_transient' );
		if ( function_exists( 'wp_cache_flush' ) ) wp_cache_flush();
		get_setting_Emsfb( '_clear_cache' );
		$s = get_setting_Emsfb( 'decoded' );
		efb_f17_out( array( 'in_row' => is_object( $s ) && isset( $s->adr_lookup ), 'old' => get_option( 'emsfb_adr_lookup', null ), 'moved' => get_option( 'emsfb_adr_moved', null ) ) );
		break;
	default:
		efb_f17_out( array( 'error' => 'unknown command ' . $f17_cmd ) );
}
