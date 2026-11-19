<?php
/**
 * Plugin Name: EFB e2e F20 mock portal add-on (local test site only, never shipped)
 * Description: A stand-in of the customer portal add-on (AdnPRT) that calls core's F20-core contract end to end, for
 * tests/test-portal-core-http.php and tests/e2e-wp/scenario-f20.js. Copied by `ctl-f20.php mu`. Active only while
 * the option efb_e2e_f20_on is set (other scenarios see nothing):
 *  - efb_portal_active true (the contract answers on every plan since 1.1: no plan filter), efb_portal_form_policy
 *    from the option efb_e2e_f20_policy ({form_id: policy});
 *  - a session as the real add-on keeps it (§4.3, simplified): an HttpOnly cookie efb_e2e_prt with a random token, only
 *    its hash stored (option efb_e2e_prt_<hash> = the identity core made); a pre-session cookie efb_e2e_pre (the psid);
 *    writes need the header X-EFB-Portal-CSRF (HMAC of the token) and the same origin;
 *  - one REST route POST Emsfb/v1/e2e-portal/<op> (no-store) mapping each op to a contract function, and a page
 *    shortcode [efb_e2e_portal] (sign in with an e-mail code, the list) for the browser scenario.
 */
// P4 is checked with the mock off too: every efb_entry_stored call is recorded while efb_e2e_f20_stored exists.
add_action( 'efb_entry_stored', static function ( $id, $fid, $track, $rows ) {
	$l = get_option( 'efb_e2e_f20_stored', null );
	if ( ! is_array( $l ) ) return;
	$l[] = array( (int) $id, (int) $fid, (string) $track, is_array( $rows ) ? count( $rows ) : -1 );
	update_option( 'efb_e2e_f20_stored', array_slice( $l, -50 ), false );
}, 10, 4 );
if ( ! get_option( 'efb_e2e_f20_on' ) ) {
	return;
}
// Lower limits for the tests (efb_portal_limit), from the option efb_e2e_f20_limits {scope: n}.
add_filter( 'efb_portal_limit', static function ( $n, $scope ) { $l = get_option( 'efb_e2e_f20_limits', array() ); return is_array( $l ) && isset( $l[ $scope ] ) ? (int) $l[ $scope ] : $n; }, 10, 2 );
add_filter( 'efb_portal_active', '__return_true' );
add_filter( 'efb_verify_plan', static function ( $need, $part ) { return 'sms' === $part ? 'free_plus' : $need; }, 10, 2 );
add_filter( 'efb_portal_form_policy', static function ( $p, $fid ) {
	$all = get_option( 'efb_e2e_f20_policy', array() );
	return is_array( $all ) && isset( $all[ $fid ] ) && is_array( $all[ $fid ] ) ? $all[ $fid ] : $p;
}, 10, 2 );
add_filter( 'emsfb_cache_shortcodes', static function ( $t ) { $t[] = 'efb_e2e_portal'; return $t; } );
add_filter( 'efb_entry_view_url', static function ( $url, $msg_id, $track, $ctx ) {
	$page = get_option( 'efb_e2e_f20_page' );
	return $page ? add_query_arg( 'efb_portal_track', rawurlencode( (string) $track ), $page ) : $url;
}, 10, 4 );

// With the F06 Booking stand-in (efb-e2e-f06.php, efb_e2e_f06_taken): the dates of a cancelled booking and the date a
// rescheduled one left are free again (what the Booking add-on does on these actions).
$efb_e2e_free = static function ( $rows ) {
	$taken = (array) get_option( 'efb_e2e_f06_taken', array() );
	foreach ( (array) $rows as $r ) if ( is_array( $r ) && 'advdate' === ( $r['type'] ?? '' ) ) $taken = array_diff( $taken, array_map( static function ( $p ) { return explode( 'T', $p )[0]; }, preg_split( '#[/,]#', (string) $r['value'] ) ) );
	update_option( 'efb_e2e_f06_taken', array_values( $taken ), false );
	$log = (array) get_option( 'efb_e2e_f20_events', array() );
	$log[] = array( current_action(), count( (array) $rows ) );
	update_option( 'efb_e2e_f20_events', array_slice( $log, -30 ), false );
};
add_action( 'efb_date_released', $efb_e2e_free, 10, 1 );
add_action( 'efb_entry_cancelled', static function ( $id, $fid, $rows ) use ( $efb_e2e_free ) { $efb_e2e_free( $rows ); }, 10, 3 );

function efb_e2e_prt_session( $make = null ) {
	$tok = isset( $_COOKIE['efb_e2e_prt'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) $_COOKIE['efb_e2e_prt'] ) : '';
	if ( null !== $make ) {
		if ( '' !== $tok ) delete_option( 'efb_e2e_prt_' . hash( 'sha256', $tok ) );
		$tok = bin2hex( random_bytes( 32 ) ); // turned on every sign-in (no fixation)
		update_option( 'efb_e2e_prt_' . hash( 'sha256', $tok ), $make, false );
		setcookie( 'efb_e2e_prt', $tok, array( 'expires' => time() + 1800, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'httponly' => true, 'samesite' => 'Lax' ) );
		return array( 'identity' => $make, 'csrf' => hash_hmac( 'sha256', $tok, wp_salt( 'nonce' ) ) );
	}
	$id = '' !== $tok ? get_option( 'efb_e2e_prt_' . hash( 'sha256', $tok ), null ) : null;
	return is_array( $id ) ? array( 'identity' => $id, 'csrf' => hash_hmac( 'sha256', $tok, wp_salt( 'nonce' ) ) ) : null;
}

function efb_e2e_prt_psid() {
	$p = isset( $_COOKIE['efb_e2e_pre'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $_COOKIE['efb_e2e_pre'] ) : '';
	if ( strlen( $p ) < 16 ) {
		$p = bin2hex( random_bytes( 16 ) );
		setcookie( 'efb_e2e_pre', $p, array( 'expires' => time() + 900, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'httponly' => true, 'samesite' => 'Lax' ) );
	}
	return $p;
}

add_action( 'rest_api_init', static function () {
	register_rest_route( 'Emsfb/v1', 'e2e-portal/(?P<op>[a-z_]+)', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => static function ( $r ) {
		nocache_headers();
		header( 'Cache-Control: private, no-store' );
		$op = $r['op'];
		$j = $r->get_json_params();
		$j = is_array( $j ) ? $j : array();
		$sess = efb_e2e_prt_session();
		$writes = array( 'reply', 'upload_token', 'state', 'cancel', 'reschedule', 'update', 'draft_link', 'privacy', 'signout' );
		$open = array( 'start', 'verify', 'wp', 'api' );
		if ( ! in_array( $op, $open, true ) && ! $sess ) return new WP_REST_Response( array( 'success' => false, 'code' => 'session' ), 401 );
		if ( in_array( $op, $writes, true ) && ( ! hash_equals( $sess['csrf'], (string) $r->get_header( 'X-EFB-Portal-CSRF' ) ) || ( class_exists( '\Emsfb\Request_Health' ) && ! \Emsfb\Request_Health::same_origin() ) ) ) {
			return new WP_REST_Response( array( 'success' => false, 'code' => 'csrf' ), 403 );
		}
		$id = $sess ? $sess['identity'] : array();
		$a = static function ( $k, $d = null ) use ( $j ) { return array_key_exists( $k, $j ) ? $j[ $k ] : $d; };
		switch ( $op ) {
			case 'api': $res = efb_portal_core_api(); break;
			case 'start':
				$ctx = array( 'psid' => efb_e2e_prt_psid(), 'lang' => (string) $a( 'lang', '' ) );
				if ( null !== $a( 'captcha' ) ) $ctx['captcha'] = (string) $a( 'captcha' );
				$res = efb_portal_send_code( (string) $a( 'channel', 'email' ), (string) $a( 'dest', '' ), $ctx );
				break;
			case 'verify':
				$res = efb_portal_check_code( (string) $a( 'cid', '' ), (string) $a( 'code', '' ), array( 'psid' => efb_e2e_prt_psid() ) );
				if ( ! is_wp_error( $res ) ) {
					// "Add another e-mail or mobile number": the new identity joins the session's (the token turns).
					$ident = array_values( array_unique( array_merge( $sess ? $sess['identity'] : array(), efb_portal_identity_from_proof( array( $res['proof'] ), 0 ) ), SORT_REGULAR ) );
					$new = efb_e2e_prt_session( $ident );
					$res = array( 'csrf' => $new['csrf'], 'identity_kinds' => wp_list_pluck( $ident, 'k' ) );
				}
				break;
			case 'wp':
				$res = is_user_logged_in() ? efb_e2e_prt_session( efb_portal_identity_from_proof( array(), get_current_user_id(), (bool) $a( 'wp_email', true ) ) ) : new WP_Error( 'session', 'login', array( 'status' => 401 ) );
				if ( ! is_wp_error( $res ) ) $res = array( 'csrf' => $res['csrf'] );
				break;
			case 'session': $res = array( 'csrf' => $sess['csrf'], 'kinds' => wp_list_pluck( $id, 'k' ) ); break;
			case 'identity': $res = $id; break; // tests only: what the add-on keeps (hashes)
			case 'entries': $res = efb_portal_entries( $id, (array) $a( 'q', array() ) ); break;
			case 'entry': $res = efb_portal_entry( (int) $a( 'id' ), $id ); break;
			case 'thread': $res = efb_portal_thread( (int) $a( 'id' ), $id ); break;
			case 'payments': $res = efb_portal_payments( (int) $a( 'id' ), $id ); break;
			case 'file': $res = efb_portal_file_link( (int) $a( 'id' ), $id, (int) $a( 'n', 0 ) ); break;
			case 'reply': $res = efb_entry_add_reply( (int) $a( 'id' ), (array) $a( 'rows', array() ), array( 'kind' => 'owner', 'identity' => $id ) ); break;
			case 'upload_token': $res = efb_portal_reply_upload_token( (int) $a( 'id' ), $id ); break;
			case 'state': $res = efb_entry_set_ticket_state( (int) $a( 'id' ), (string) $a( 'state' ), array( 'kind' => 'owner', 'identity' => $id ) ); break;
			case 'cancel': $res = efb_entry_owner_cancel( (int) $a( 'id' ), $id, (string) $a( 'reason', '' ) ); break;
			case 'reschedule': $res = efb_entry_owner_reschedule( (int) $a( 'id' ), $id, (string) $a( 'field' ), $a( 'value' ) ); break;
			case 'editable': $res = efb_entry_owner_editable( (int) $a( 'id' ), $id ); break;
			case 'update': $res = efb_entry_owner_update( (int) $a( 'id' ), $id, (string) $a( 'hash' ), (array) $a( 'changes', array() ) ); break;
			case 'drafts': $res = efb_portal_drafts( $id ); break;
			case 'draft_link': $res = efb_portal_draft_link( (int) $a( 'id' ), $id, (string) $a( 'page', home_url( '/' ) ) ); break;
			case 'privacy': $res = efb_portal_privacy_request( $id, (string) $a( 'email', '' ), (string) $a( 'type', 'export' ) ); break;
			case 'signout':
				delete_option( 'efb_e2e_prt_' . hash( 'sha256', (string) $_COOKIE['efb_e2e_prt'] ) );
				setcookie( 'efb_e2e_prt', '', array( 'expires' => time() - 3600, 'path' => COOKIEPATH ? COOKIEPATH : '/' ) );
				$res = true;
				break;
			default: $res = new WP_Error( 'op', 'unknown', array( 'status' => 404 ) );
		}
		if ( is_wp_error( $res ) ) {
			$d = $res->get_error_data();
			return new WP_REST_Response( array_merge( array( 'success' => false, 'code' => $res->get_error_code(), 'message' => $res->get_error_message() ), is_array( $d ) ? $d : array() ), 200 );
		}
		return new WP_REST_Response( array( 'success' => true, 'data' => $res ), 200 );
	} ) );
} );

// The portal page of the browser scenario: a sign-in by e-mail code and the list, drawn with textContent only.
add_shortcode( 'efb_e2e_portal', static function () {
	if ( class_exists( '\Emsfb\Cache_Compat' ) ) \Emsfb\Cache_Compat::bypass();
	$rest = esc_url_raw( rest_url( 'Emsfb/v1/e2e-portal/' ) );
	ob_start(); ?>
<main id="efbPrt" class="efb-prt" style="max-width:640px;margin:0 auto;padding:12px;box-sizing:border-box">
	<h2 id="efbPrtH" tabindex="-1">My submissions</h2>
	<form id="efbPrtSignin" novalidate><label for="efbPrtEmail">Email address</label><br><input id="efbPrtEmail" type="email" autocomplete="email" dir="ltr" style="width:100%;min-height:44px;box-sizing:border-box">
		<button type="submit" style="min-height:44px;width:100%">Send code</button></form>
	<form id="efbPrtCode" hidden novalidate><p id="efbPrtSent" aria-live="polite" style="unicode-bidi:plaintext"></p><label for="efbPrtCodeIn">Code</label><br>
		<input id="efbPrtCodeIn" inputmode="numeric" autocomplete="one-time-code" dir="ltr" style="width:100%;min-height:44px;box-sizing:border-box"><button type="submit" style="min-height:44px;width:100%">Verify</button></form>
	<p id="efbPrtLive" aria-live="polite"></p>
	<ul id="efbPrtList" role="list" style="list-style:none;padding:0"></ul>
</main>
<script>
(function () {
	var R = <?php echo wp_json_encode( $rest ); ?>, csrf = '', cid = '';
	var post = function (op, body) { return fetch(R + op, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-EFB-Portal-CSRF': csrf }, body: JSON.stringify(body || {}) }).then(function (r) { return r.json(); }); };
	var $ = function (i) { return document.getElementById(i); };
	$('efbPrtSignin').addEventListener('submit', function (e) { e.preventDefault(); post('start', { channel: 'email', dest: $('efbPrtEmail').value, lang: document.documentElement.lang.replace('-', '_') }).then(function (r) {
		if (!r.success) { $('efbPrtLive').textContent = r.message; return; }
		cid = r.data.challenge_id; var s = $('efbPrtSent'); s.textContent = ''; var t = r.data.message.split(r.data.masked); s.append(t[0]); var b = document.createElement('bdi'); b.dir = 'ltr'; b.textContent = r.data.masked; s.append(b, t[1] || '');
		$('efbPrtSignin').hidden = true; $('efbPrtCode').hidden = false; $('efbPrtCodeIn').focus(); }); });
	$('efbPrtCode').addEventListener('submit', function (e) { e.preventDefault(); post('verify', { cid: cid, code: $('efbPrtCodeIn').value }).then(function (r) {
		if (!r.success) { $('efbPrtLive').textContent = r.message; return; }
		csrf = r.data.csrf; $('efbPrtCode').hidden = true; $('efbPrtLive').textContent = 'You are signed in.'; $('efbPrtH').focus();
		return post('entries', {}).then(function (l) { (l.data.rows || []).forEach(function (row) { var li = document.createElement('li'); li.className = 'efb-prt-row'; li.style.cssText = 'border:1px solid #ccc;margin:8px 0;padding:8px;min-height:44px';
			var f = document.createElement('strong'); f.textContent = row.form; var c = document.createElement('bdi'); c.dir = 'ltr'; c.className = 'efb-prt-track'; c.textContent = ' ' + row.track + ' ';
			var st = document.createElement('span'); st.className = 'efb-prt-status'; st.textContent = row.status; var d = document.createElement('span'); d.textContent = ' ' + row.date_text;
			li.append(f, c, st, d); $('efbPrtList').appendChild(li); }); }); }); });
})();
</script>
	<?php
	return ob_get_clean();
} );
