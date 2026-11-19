<?php
/**
 * F20-core helpers for tests/test-portal-*.php and tests/e2e-wp/scenario-f20.js, run through `wp eval-file`:
 *
 *   mu                         copy mu-plugins/efb-e2e-f20.php (the mock portal add-on) into the site's mu-plugins
 *   on <0|1>                   the mock portal on / off (efb_e2e_f20_on); off also removes its sessions and policies
 *   policy <form id> <json>    a form's portal policy (the add-on's settings; {} removes it)
 *   page <title>               a page with [efb_e2e_portal]; its URL becomes the link of e-mails (efb_entry_view_url)
 *   index [restart]            efb_portal_reindex() and the whole backfill now (what the cron does in batches)
 *   owners <msg id>            the owner index rows of an entry (kind, verified; hashes shortened)
 *   msg <msg id>               read_, owner_state, entry_status, content (decoded) of an entry
 *   reset                      the portal's and F02's counters, locks and challenges (a clean slate for a test)
 *   bef <0|1>                  the Booking add-on (AdnBEF) on / off; on also keeps it on this Free Plus site (SPLIT
 *                              grandfathering, as a site that used booking forms before SPLIT)
 *   stock <form id>            the used places of the form's choices (emsfb_stock_)
 *   hold-date <iso>            a date the Booking stand-in refuses (efb_date_before_commit), '' = none
 *   log <msg id>               F14's history rows of an entry
 *   seed-bulk <form id> <n> <email>   n entries of the form at once (n-1 of other addresses, the last of <email>)
 *   time-lookup <email>        milliseconds of one owner lookup (P5) and the number found
 *   call <function> <json args>   a contract function called directly (WP_Error → {error, data})
 *   pmt <msg id> <status> [legacy]  a payment row of an entry (emsfb_pmt_, the test gateway, a reference and a
 *                              payer address that must never come out); "legacy" adds a payment row in the answers
 *   private-file <name>        a file in the private uploads folder (F07); its address
 *   draft <form id> <email> [page url]  an active "continue later" draft of the form holding the address (an older
 *                              draft: no page_url, unless one is given); its token
 *   draft-load <form id> <token>  whether a draft token still opens
 *   stored [clear]             the efb_entry_stored calls the mock recorded
 *   chal <challenge id>        a sign-in challenge (fid, field_id, sid length, status) and whether a form's lookup of the
 *                              same id (fid of the first form, any field) finds it
 *   expire <challenge id>      the challenge's time is over
 *   bulk-own <n>|clear         synthetic owner rows (form -1) for a lookup on a large index
 *   bf-batch [size]            one backfill batch now (the cron event's work) and its time
 *   purge <msg id> [anon]      F13's delete (or anonymize) of one entry
 *   tpl clear|restore          the site's own e-mail template put aside / back (core's default template meanwhile)
 *   limits <json>              lower portal limits for a test ({scope: n}; '' = none)
 *   caps                       the registry's capabilities.portal_core, and whether AdnPRT is anywhere in the add-on registry
 *   place <form id> <kind> [status]  a post showing the form: shortcode | block | wpbakery | elementor | post (a blog post
 *                              with the shortcode) | draft (an unpublished page); its id and URL
 *   unplace <post id>          deletes that post
 *   draft-row <draft id>       a draft's fid, status and page_url
 *   raw-content <msg id> <json rows>  an entry's answers replaced (stored as the plugin stores them; no hook)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}
$f20_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$f20_cmd  = isset( $f20_args[0] ) ? $f20_args[0] : '';
$f20_rest = array_slice( $f20_args, 1 );
function efb_f20_out( $d ) {
	echo "\n" . wp_json_encode( $d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ) . "\n";
	exit( 0 );
}
global $wpdb;
switch ( $f20_cmd ) {
	case 'mu':
		$dst = WPMU_PLUGIN_DIR . '/efb-e2e-f20.php';
		if ( ! is_dir( WPMU_PLUGIN_DIR ) ) wp_mkdir_p( WPMU_PLUGIN_DIR );
		$src = __DIR__ . '/mu-plugins/efb-e2e-f20.php';
		$same = is_readable( $dst ) && md5_file( $dst ) === md5_file( $src );
		efb_f20_out( array( 'copied' => $same || copy( $src, $dst ) ) );
		break;
	case 'on':
		if ( isset( $f20_rest[0] ) && '1' === $f20_rest[0] ) {
			update_option( 'efb_e2e_f20_on', 1, false );
		} else {
			delete_option( 'efb_e2e_f20_on' );
			delete_option( 'efb_e2e_f20_policy' );
			delete_option( 'efb_e2e_f20_page' );
			delete_option( 'efb_e2e_f20_hold' );
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'efb\\_e2e\\_prt\\_%'" ); // phpcs:ignore
		}
		efb_f20_out( array( 'on' => (bool) get_option( 'efb_e2e_f20_on' ) ) );
		break;
	case 'policy':
		$all = get_option( 'efb_e2e_f20_policy', array() );
		$all = is_array( $all ) ? $all : array();
		$p = json_decode( isset( $f20_rest[1] ) ? $f20_rest[1] : '{}', true );
		if ( $p ) $all[ (int) $f20_rest[0] ] = $p; else unset( $all[ (int) $f20_rest[0] ] );
		update_option( 'efb_e2e_f20_policy', $all, false );
		efb_f20_out( \Emsfb\Portal_Core::policy( (int) $f20_rest[0] ) );
		break;
	case 'page':
		$id = wp_insert_post( array( 'post_title' => isset( $f20_rest[0] ) ? $f20_rest[0] : 'Portal', 'post_content' => '[efb_e2e_portal]', 'post_status' => 'publish', 'post_type' => 'page' ) );
		update_option( 'efb_e2e_f20_page', get_permalink( $id ), false );
		efb_f20_out( array( 'id' => $id, 'url' => get_permalink( $id ) ) );
		break;
	case 'index':
		efb_portal_reindex( isset( $f20_rest[0] ) && 'restart' === $f20_rest[0] );
		$st = null;
		for ( $i = 0; $i < 1000; $i++ ) { $st = \Emsfb\Owner_Index::run_backfill( 500, 5 ); if ( ! is_array( $st ) || ! empty( $st['done'] ) ) break; }
		wp_clear_scheduled_hook( \Emsfb\Owner_Index::BF_HOOK );
		efb_f20_out( array( 'bf' => $st, 'state' => \Emsfb\Owner_Index::state(), 'rows' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_msg_own_" ) ) ); // phpcs:ignore
		break;
	case 'owners':
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT kind, verified, form_id, LEFT(id_hash, 12) AS h FROM {$wpdb->prefix}emsfb_msg_own_ WHERE msg_id = %d ORDER BY id", (int) $f20_rest[0] ), ARRAY_A ); // phpcs:ignore
		efb_f20_out( $rows );
		break;
	case 'msg':
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT msg_id, form_id, track, read_, owner_state, owner_state_at, entry_status, content, edited_at FROM {$wpdb->prefix}emsfb_msg_ WHERE msg_id = %d", (int) $f20_rest[0] ), ARRAY_A ); // phpcs:ignore
		if ( $r ) $r['rows'] = \Emsfb\Privacy::decode_content( $r['content'] );
		efb_f20_out( $r );
		break;
	case 'reset':
		$wpdb->query( "DELETE FROM {$wpdb->prefix}emsfb_vrfy_rl_ WHERE scope LIKE 'v%' OR scope LIKE 'pr%'" ); // phpcs:ignore
		$wpdb->query( "DELETE FROM {$wpdb->prefix}emsfb_vrfy_ WHERE fid = 0" ); // phpcs:ignore
		efb_f20_out( array( 'ok' => true ) );
		break;
	case 'bef':
		$on = isset( $f20_rest[0] ) && '1' === $f20_rest[0];
		$s = get_setting_Emsfb( 'decoded' );
		$s = is_object( $s ) ? $s : new stdClass();
		$s->AdnBEF = $on ? 1 : 0;
		\efbFunction::set_setting_Emsfb( (object) array( 'AdnBEF' => $s->AdnBEF ) );
		$scan = get_option( 'emsfb_split_scan', array() );
		$scan = is_array( $scan ) ? $scan : array();
		$g = isset( $scan['grandfather'] ) && is_array( $scan['grandfather'] ) ? $scan['grandfather'] : array();
		$g = $on ? array_values( array_unique( array_merge( $g, array( 'AdnBEF' ) ) ) ) : array_values( array_diff( $g, array( 'AdnBEF' ) ) );
		$scan['grandfather'] = $g;
		update_option( 'emsfb_split_scan', $scan, false );
		efb_f20_out( array( 'AdnBEF' => $on, 'state' => \Emsfb\Addon_Catalog::state( 'AdnBEF' ) ) );
		break;
	case 'stock':
		efb_f20_out( $wpdb->get_results( $wpdb->prepare( "SELECT field_id, option_id, used FROM {$wpdb->prefix}emsfb_stock_ WHERE form_id = %d ORDER BY option_id", (int) $f20_rest[0] ), ARRAY_A ) ); // phpcs:ignore
		break;
	case 'hold-date':
		update_option( 'efb_e2e_f20_hold', isset( $f20_rest[0] ) ? (string) $f20_rest[0] : '', false );
		efb_f20_out( array( 'hold' => get_option( 'efb_e2e_f20_hold' ) ) );
		break;
	case 'log':
		efb_f20_out( $wpdb->get_results( $wpdb->prepare( "SELECT action, field_id, old_value, new_value, meta, user_id FROM {$wpdb->prefix}emsfb_msg_log_ WHERE msg_id = %d ORDER BY log_id", (int) $f20_rest[0] ), ARRAY_A ) ); // phpcs:ignore
		break;
	case 'seed-bulk':
		$fid = (int) $f20_rest[0];
		$n = max( 1, (int) $f20_rest[1] );
		$form = \Emsfb\Payments\Service::form_rows( $fid );
		$em = '';
		foreach ( (array) $form as $i => $f ) if ( $i > 0 && is_array( $f ) && 'email' === ( $f['type'] ?? '' ) ) { $em = $f['id_']; break; }
		$t0 = microtime( true );
		for ( $k = 0; $k < $n; $k += 500 ) {
			$vals = array();
			for ( $q = $k; $q < min( $n, $k + 500 ); $q++ ) {
				$mail = $q === $n - 1 ? (string) $f20_rest[2] : 'bulk' . $q . '@example.test';
				$c = str_replace( '"', '\\"', wp_json_encode( array( array( 'id_' => $em, 'name' => 'Email', 'type' => 'email', 'value' => $mail ) ) ) );
				$vals[] = $wpdb->prepare( '(%d, %s, %s, %s, %s, 0)', $fid, 'BLK' . $q . wp_generate_password( 6, false, false ), $c, current_time( 'mysql' ), 'bulk' );
			}
			$wpdb->query( "INSERT INTO {$wpdb->prefix}emsfb_msg_ (form_id, track, content, date, form_title_x, read_) VALUES " . implode( ',', $vals ) ); // phpcs:ignore
		}
		efb_f20_out( array( 'seeded' => $n, 'seconds' => round( microtime( true ) - $t0, 2 ), 'max' => (int) $wpdb->get_var( "SELECT MAX(msg_id) FROM {$wpdb->prefix}emsfb_msg_" ) ) ); // phpcs:ignore
		break;
	case 'time-lookup':
		$h = \Emsfb\Owner_Index::hash( 'e', (string) $f20_rest[0] );
		$t0 = microtime( true );
		$ids = \Emsfb\Owner_Index::lookup( array( array( 'k' => 'e', 'h' => $h ) ) );
		efb_f20_out( array( 'ms' => round( 1000 * ( microtime( true ) - $t0 ), 2 ), 'found' => count( $ids ) ) );
		break;
	case 'call':
		$fn = (string) $f20_rest[0];
		if ( 0 !== strpos( $fn, 'efb_portal_' ) && 0 !== strpos( $fn, 'efb_entry_' ) ) efb_f20_out( array( 'error' => 'not a contract function' ) );
		$r = call_user_func_array( $fn, (array) json_decode( isset( $f20_rest[1] ) ? $f20_rest[1] : '[]', true ) );
		efb_f20_out( is_wp_error( $r ) ? array( 'error' => $r->get_error_code(), 'data' => $r->get_error_data() ) : $r );
		break;
	case 'pmt':
		$e = $wpdb->get_row( $wpdb->prepare( "SELECT form_id, track, content FROM {$wpdb->prefix}emsfb_msg_ WHERE msg_id = %d", (int) $f20_rest[0] ), ARRAY_A ); // phpcs:ignore
		$now = gmdate( 'Y-m-d H:i:s' );
		$st = (string) $f20_rest[1];
		$wpdb->insert( $wpdb->prefix . 'emsfb_pmt_', array( 'uid' => substr( bin2hex( random_bytes( 11 ) ), 0, 22 ), 'form_id' => (int) $e['form_id'], 'msg_id' => (int) $f20_rest[0], 'track' => $e['track'], 'gateway' => 'efbmock', 'status' => $st,
			'amount_minor' => 1250, 'currency' => 'EUR', 'refunded_minor' => 'refunded' === $st ? 1250 : 0, 'attempt_key' => wp_generate_uuid4(), 'idem_key' => hash( 'sha256', wp_generate_uuid4() ), 'gateway_ref' => 'tr_SECRETREF123', 'method' => 'card',
			'payer_email' => 'payer-secret@example.test', 'paid_at' => 'paid' === $st || 'refunded' === $st ? $now : null, 'finalized_at' => 'paid' === $st ? $now : null, 'created_at' => $now, 'updated_at' => $now ) );
		$pid = (int) $wpdb->insert_id;
		if ( isset( $f20_rest[2] ) && 'legacy' === $f20_rest[2] ) {
			$rows = \Emsfb\Privacy::decode_content( $e['content'] );
			$rows[] = array( 'id_' => 'payment', 'type' => 'payment', 'paymentGateway' => 'stripe', 'paymentIntent' => 'pi_SECRETINTENT', 'total' => '30', 'currency' => 'usd', 'paymentmethod' => 'card', 'paymentCreated' => '2026-11-01', 'amount' => -2 );
			$wpdb->update( $wpdb->prefix . 'emsfb_msg_', array( 'content' => \Emsfb\Privacy::encode_content( $rows ) ), array( 'msg_id' => (int) $f20_rest[0] ) );
		}
		efb_f20_out( array( 'id' => $pid ) );
		break;
	case 'private-file':
		$sub = gmdate( 'Y' ) . '/' . gmdate( 'm' );
		$dir = \Emsfb\Uploads::ensure_private_dir( $sub );
		$name = 'efb-PLG-' . gmdate( 'ymd' ) . '-' . preg_replace( '/[^A-Za-z0-9]/', '', (string) $f20_rest[0] ) . '.pdf';
		file_put_contents( $dir . $name, 'portal private file ' . $name );
		efb_f20_out( array( 'url' => \Emsfb\Uploads::private_base_url() . $sub . '/' . $name, 'private' => \Emsfb\Uploads::is_private_url( \Emsfb\Uploads::private_base_url() . $sub . '/' . $name ) ) );
		break;
	case 'draft':
		$tok = bin2hex( random_bytes( 32 ) );
		$form = \Emsfb\Payments\Service::form_rows( (int) $f20_rest[0] );
		$em = '';
		foreach ( (array) $form as $i => $f ) if ( $i > 0 && is_array( $f ) && 'email' === ( $f['type'] ?? '' ) ) { $em = $f['id_']; break; }
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->insert( \Emsfb\Drafts::table(), array( 'kind' => 'resume', 'fid' => (int) $f20_rest[0], 'token_hash' => \Emsfb\Drafts::token_hash( $tok ), 'owner_hash' => str_repeat( 'a', 64 ), 'uid' => 0,
			'content' => \Emsfb\Privacy::encode_content( array( array( 'id_' => $em, 'name' => 'Email', 'type' => 'email', 'value' => (string) $f20_rest[1] ) ) ), 'step' => 1, 'progress' => 40, 'status' => 'active',
			'created_at' => $now, 'updated_at' => $now, 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 7 * 86400 ) ) + ( isset( $f20_rest[2] ) ? array( 'page_url' => (string) $f20_rest[2] ) : array() ) );
		efb_f20_out( array( 'id' => (int) $wpdb->insert_id, 'token' => $tok ) );
		break;
	case 'draft-load':
		$r = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM `' . \Emsfb\Drafts::table() . "` WHERE token_hash = %s AND fid = %d AND status = 'active'", \Emsfb\Drafts::token_hash( (string) $f20_rest[1] ), (int) $f20_rest[0] ) ); // phpcs:ignore
		efb_f20_out( array( 'opens' => (bool) $r ) );
		break;
	case 'chal':
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT fid, field_id, sid, status, channel FROM {$wpdb->prefix}emsfb_vrfy_ WHERE challenge_id = %s", (string) $f20_rest[0] ), ARRAY_A ); // phpcs:ignore
		$ffid = (int) $wpdb->get_var( "SELECT MIN(form_id) FROM {$wpdb->prefix}emsfb_form" ); // phpcs:ignore
		efb_f20_out( array( 'row' => $r ? array( 'fid' => (int) $r['fid'], 'field_id' => $r['field_id'], 'sid' => strlen( $r['sid'] ), 'status' => $r['status'], 'channel' => $r['channel'] ) : null,
			'form_lookup' => $r ? null !== \Emsfb\Verification::find( (string) $f20_rest[0], $ffid, $r['field_id'], $r['sid'] ) : null ) );
		break;
	case 'expire':
		$wpdb->update( $wpdb->prefix . 'emsfb_vrfy_', array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 5 ) ), array( 'challenge_id' => (string) $f20_rest[0] ) );
		efb_f20_out( array( 'ok' => true ) );
		break;
	case 'raw-content':
		efb_f20_out( array( 'updated' => $wpdb->update( $wpdb->prefix . 'emsfb_msg_', array( 'content' => \Emsfb\Privacy::encode_content( (array) json_decode( (string) $f20_rest[1], true ) ) ), array( 'msg_id' => (int) $f20_rest[0] ) ) ) );
		break;
	case 'caps':
		$all = \Emsfb\Builder_Api::capabilities();
		efb_f20_out( array( 'portal_core' => isset( $all['portal_core'] ) ? $all['portal_core'] : null, 'addon_feature' => isset( $all['features']['addon:AdnPRT'] ), 'catalog' => null !== \Emsfb\Addon_Catalog::get( 'AdnPRT' ),
			'split_addon' => isset( $all['split']['addons']['AdnPRT'] ), 'plan' => \Emsfb\Builder_Api::plan() ) );
		break;
	case 'place':
		$fid  = (int) $f20_rest[0];
		$kind = isset( $f20_rest[1] ) ? (string) $f20_rest[1] : 'shortcode';
		$content = array( 'shortcode' => '[EMS_Form_Builder id=' . $fid . ']', 'post' => '[EMS_Form_Builder id="' . $fid . '"]', 'draft' => '[EMS_Form_Builder id=' . $fid . ']',
			'block' => '<!-- wp:easy-form-builder/form {"formId":"' . $fid . '","formName":"x"} /-->', 'wpbakery' => '[vc_row][efb_wpbakery_form form_id="' . $fid . '"][/vc_row]', 'elementor' => '' );
		$id = wp_insert_post( array( 'post_type' => 'post' === $kind ? 'post' : 'page', 'post_status' => 'draft' === $kind ? 'draft' : 'publish', 'post_title' => 'Portal place ' . $kind . ' ' . $fid,
			'post_content' => isset( $content[ $kind ] ) ? $content[ $kind ] : '', 'meta_input' => array( '_efb_e2e' => 1 ) ) );
		if ( 'elementor' === $kind ) update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array( array( 'elType' => 'section', 'elements' => array( array( 'elType' => 'widget', 'widgetType' => 'emsfb_form', 'settings' => array( 'form_id' => (string) $fid ) ) ) ) ) ) ) );
		efb_f20_out( array( 'id' => (int) $id, 'url' => get_permalink( $id ) ) );
		break;
	case 'draft-row':
		efb_f20_out( $wpdb->get_row( $wpdb->prepare( 'SELECT fid, status, page_url FROM `' . \Emsfb\Drafts::table() . '` WHERE id = %d', (int) $f20_rest[0] ), ARRAY_A ) ); // phpcs:ignore
		break;
	case 'unplace':
		efb_f20_out( array( 'deleted' => (bool) wp_delete_post( (int) $f20_rest[0], true ) ) );
		break;
	case 'limits':
		$l = json_decode( isset( $f20_rest[0] ) ? (string) $f20_rest[0] : '', true );
		if ( is_array( $l ) && $l ) update_option( 'efb_e2e_f20_limits', $l, false ); else delete_option( 'efb_e2e_f20_limits' );
		efb_f20_out( array( 'limits' => get_option( 'efb_e2e_f20_limits', null ) ) );
		break;
	case 'purge':
		efb_f20_out( \Emsfb\Privacy::purge_entries( array( (int) $f20_rest[0] ), isset( $f20_rest[1] ) && 'anon' === $f20_rest[1] ? 'anon' : 'delete' ) );
		break;
	case 'bulk-own':
		// <n> synthetic owner rows (form_id -1, msg ids from 900000000 on) to measure a lookup on a large index; "clear" removes them.
		if ( isset( $f20_rest[0] ) && 'clear' === $f20_rest[0] ) efb_f20_out( array( 'deleted' => $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}emsfb_msg_own_ WHERE form_id IN (-1, %d)", isset( $f20_rest[1] ) ? (int) $f20_rest[1] : -1 ) ) ) ); // phpcs:ignore
		$n = (int) $f20_rest[0];
		$t0 = microtime( true );
		for ( $k = 0; $k < $n; $k += 1000 ) {
			$vals = array();
			for ( $q = $k; $q < min( $n, $k + 1000 ); $q++ ) $vals[] = $wpdb->prepare( '(%d, -1, %s, %s, %s)', 900000000 + $q, $q % 3 ? 'e' : 'p', hash( 'sha256', 'bulk' . $q ), '' );
			$wpdb->query( "INSERT INTO {$wpdb->prefix}emsfb_msg_own_ (msg_id, form_id, kind, id_hash, verified) VALUES " . implode( ',', $vals ) ); // phpcs:ignore
		}
		efb_f20_out( array( 'rows' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}emsfb_msg_own_" ), 'seconds' => round( microtime( true ) - $t0, 2 ) ) ); // phpcs:ignore
		break;
	case 'bf-batch':
		// One backfill batch now (what the cron event runs), with its time.
		$t0 = microtime( true );
		$st = \Emsfb\Owner_Index::run_backfill( isset( $f20_rest[0] ) ? (int) $f20_rest[0] : 500, 20 );
		efb_f20_out( array( 'st' => $st, 'seconds' => round( microtime( true ) - $t0, 2 ), 'next' => (bool) wp_next_scheduled( \Emsfb\Owner_Index::BF_HOOK ) ) );
		break;
	case 'tpl':
		// clear | restore: the site's own e-mail template (emailTemp) put aside, so a mail is drawn with core's default
		// template (its direction from the language), and put back exactly as it was.
		$s = get_setting_Emsfb( 'decoded' );
		if ( isset( $f20_rest[0] ) && 'clear' === $f20_rest[0] ) {
			if ( null === get_option( 'efb_e2e_f20_tpl', null ) ) update_option( 'efb_e2e_f20_tpl', array( 'v' => is_object( $s ) && isset( $s->emailTemp ) ? $s->emailTemp : null ), false );
			\efbFunction::set_setting_Emsfb( (object) array( 'emailTemp' => '' ) );
		} else {
			$o = get_option( 'efb_e2e_f20_tpl', null );
			if ( is_array( $o ) ) { \efbFunction::set_setting_Emsfb( (object) array( 'emailTemp' => null === $o['v'] ? '' : $o['v'] ) ); delete_option( 'efb_e2e_f20_tpl' ); }
		}
		$s = get_setting_Emsfb( 'decoded' );
		efb_f20_out( array( 'len' => is_object( $s ) && isset( $s->emailTemp ) ? strlen( (string) $s->emailTemp ) : 0 ) );
		break;
	case 'stored':
		if ( isset( $f20_rest[0] ) && 'clear' === $f20_rest[0] ) update_option( 'efb_e2e_f20_stored', array(), false );
		efb_f20_out( array_values( (array) get_option( 'efb_e2e_f20_stored', array() ) ) );
		break;
	default:
		efb_f20_out( array( 'error' => 'unknown command ' . $f20_cmd ) );
}
