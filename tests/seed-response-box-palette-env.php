<?php
/**
 * Set up and tear down the environment for the public response box palette test.
 *
 * The [Easy_Form_Builder_confirmation_code_finder] shortcode is the public face
 * of everything the Colors & Fonts dialog controls: the code finder card, the
 * conversation the code opens, and the reply editor under it. Proving the
 * palette reaches all three needs a published page carrying the shortcode, a
 * submission whose tracking code can be looked up, and a palette that could not
 * be mistaken for the defaults.
 *
 * Everything it touches is put back: the settings row, the page (deleted), and
 * the read flag of the message it looked at.
 *
 * Run: C:\xampp\php\php.exe tests/seed-response-box-palette-env.php setup
 *      C:\xampp\php\php.exe tests/seed-response-box-palette-env.php dark
 *      C:\xampp\php\php.exe tests/seed-response-box-palette-env.php teardown
 */

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	echo json_encode( array( 'ok' => false, 'error' => 'wp-load not found' ) );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_load;

global $wpdb;

$mode   = isset( $argv[1] ) ? $argv[1] : '';
$marker = 'efb_respbox_palette_test_state';
$table  = $wpdb->prefix . 'emsfb_setting';

/**
 * A palette no default could be confused with: every one of the thirteen
 * values is distinct, so a computed style can be traced back to exactly one
 * setting.
 */
function efb_palette_probe_efb() {
	return array(
		'respPrimary'     => '#7b8cff',
		'respPrimaryDark' => '#3f4bd8',
		'respAccent'      => '#ff9800',
		'respBtnText'     => '#101014',
		'respText'        => '#e9ebf7',
		'respTextMuted'   => '#9aa3c7',
		'respBgCard'      => '#1c2030',
		'respBgMeta'      => '#252a3d',
		'respBgResp'      => '#13161f',
		'respBgTrack'     => '#1c2030',
		'respBgEditor'    => '#2d3350',
		'respEditorText'  => '#e9ebf7',
		'respEditorPh'    => '#6b7394',
		'respFontSize'    => '1.05rem',
		'respFontFamily'  => 'inherit',
		'respPreset'      => 'dark',
		'respBrandColor'  => '#7b8cff',
	);
}

function efb_palette_row_efb() {
	global $wpdb;
	$table = $wpdb->prefix . 'emsfb_setting';
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name only.
	return $wpdb->get_row( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 1" );
}

/** The newest submission that carries a tracking code, whatever form it is on. */
function efb_palette_message_efb() {
	global $wpdb;
	$msg = $wpdb->prefix . 'emsfb_msg_';
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name only.
	return $wpdb->get_row( "SELECT msg_id, form_id, track, read_ FROM {$msg} WHERE track <> '' ORDER BY msg_id DESC LIMIT 1" );
}

switch ( $mode ) {
	case 'setup':
		$row = efb_palette_row_efb();
		$msg = efb_palette_message_efb();
		if ( ! $msg ) {
			echo json_encode( array( 'ok' => false, 'error' => 'no submission with a tracking code on this install' ) );
			exit( 1 );
		}

		if ( ! get_option( $marker ) ) {
			$page_id = wp_insert_post(
				array(
					'post_title'   => 'EFB code finder palette probe',
					'post_name'    => 'efb-code-finder-palette-probe',
					'post_content' => '[Easy_Form_Builder_confirmation_code_finder]',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
			update_option(
				$marker,
				array(
					'setting_id' => $row ? (int) $row->id : 0,
					'setting'    => $row ? (string) $row->setting : '',
					'page_id'    => (int) $page_id,
					'msg_id'     => (int) $msg->msg_id,
					'msg_read'   => $msg->read_,
				),
				false
			);
		}

		$state = get_option( $marker );
		echo json_encode(
			array(
				'ok'      => true,
				'url'     => get_permalink( $state['page_id'] ),
				'track'   => $msg->track,
				/* The panel opens a form's inbox by id, so the admin half of the
				   run does not have to hard-code one. */
				'form_id' => (int) $msg->form_id,
				'msg_id'  => (int) $msg->msg_id,
			)
		);
		break;

	case 'dark':
		$row = efb_palette_row_efb();
		if ( ! $row ) {
			echo json_encode( array( 'ok' => false, 'error' => 'no settings row' ) );
			exit( 1 );
		}
		$settings = json_decode( $row->setting, true );
		$settings = is_array( $settings ) ? $settings : array();
		$settings = array_merge( $settings, efb_palette_probe_efb() );
		$wpdb->update(
			$table,
			array( 'setting' => wp_json_encode( $settings, JSON_UNESCAPED_UNICODE ) ),
			array( 'id' => (int) $row->id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( function_exists( 'get_setting_Emsfb' ) ) {
			get_setting_Emsfb( '_clear_cache' );
		}
		delete_transient( 'emsfb_settings_transient' );
		echo json_encode( array( 'ok' => true, 'palette' => efb_palette_probe_efb() ) );
		break;

	case 'font':
	case 'customfont':
		$row = efb_palette_row_efb();
		if ( ! $row ) {
			echo json_encode( array( 'ok' => false, 'error' => 'no settings row' ) );
			exit( 1 );
		}
		$settings = json_decode( $row->setting, true );
		$settings = is_array( $settings ) ? $settings : array();
		if ( 'font' === $mode ) {
			// A family the plugin ships a Google Fonts URL for.
			$settings['respFontFamily'] = "'Inter', sans-serif";
			$settings['respCustomFont'] = '';
			$answer                     = array( 'family' => $settings['respFontFamily'] );
		} else {
			// The shape the dialog stores for a font an admin typed in themselves.
			$settings['respFontFamily'] = "'Vazirmatn', sans-serif";
			$settings['respCustomFont'] = wp_json_encode(
				array(
					'name' => 'Vazirmatn',
					'url'  => 'https://fonts.googleapis.com/css2?family=Vazirmatn&display=swap',
				)
			);
			$answer = array( 'name' => 'Vazirmatn' );
		}
		$wpdb->update(
			$table,
			array( 'setting' => wp_json_encode( $settings, JSON_UNESCAPED_UNICODE ) ),
			array( 'id' => (int) $row->id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( function_exists( 'get_setting_Emsfb' ) ) {
			get_setting_Emsfb( '_clear_cache' );
		}
		delete_transient( 'emsfb_settings_transient' );
		echo json_encode( array_merge( array( 'ok' => true ), $answer ) );
		break;

	case 'teardown':
		$snapshot = get_option( $marker );
		if ( is_array( $snapshot ) ) {
			if ( ! empty( $snapshot['setting_id'] ) ) {
				$wpdb->update(
					$table,
					array( 'setting' => $snapshot['setting'] ),
					array( 'id' => (int) $snapshot['setting_id'] ),
					array( '%s' ),
					array( '%d' )
				);
			}
			if ( ! empty( $snapshot['page_id'] ) ) {
				wp_delete_post( (int) $snapshot['page_id'], true );
			}
			// Looking a code up marks the submission read; put that back too.
			if ( ! empty( $snapshot['msg_id'] ) ) {
				$wpdb->update(
					$wpdb->prefix . 'emsfb_msg_',
					array( 'read_' => $snapshot['msg_read'] ),
					array( 'msg_id' => (int) $snapshot['msg_id'] ),
					array( '%s' ),
					array( '%d' )
				);
			}
			delete_option( $marker );
		}
		if ( function_exists( 'get_setting_Emsfb' ) ) {
			get_setting_Emsfb( '_clear_cache' );
		}
		delete_transient( 'emsfb_settings_transient' );
		echo json_encode( array( 'ok' => true, 'restored' => is_array( $snapshot ) ) );
		break;

	default:
		echo json_encode( array( 'ok' => false, 'error' => 'unknown mode' ) );
		exit( 1 );
}
