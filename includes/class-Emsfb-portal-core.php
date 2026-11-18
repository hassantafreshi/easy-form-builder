<?php
/**
 * F20-core (docs/feature-gaps/F20-customer-portal.fa.md §5, part one C1–C6; the contract an add-on builds on:
 * docs/portal/portal-core-contract.en.md). The customer portal itself is the Pro add-on AdnPRT; core only answers it,
 * and stays inert without it: every entry point refuses with efb_portal_unavailable until the add-on says it is on
 * (filter efb_portal_active) on a Pro site, and a form is in the portal only when the add-on's policy says so
 * (filter efb_portal_form_policy, default off).
 *
 *  - P7 sign-in codes without a form (emsfb_vrfy_ with fid 0, field_id 'portal', sid = the add-on's pre-session id):
 *    F02's limits, budget, locks, Human Shield, the site's CAPTCHA when asked (Spam::verify_captcha(…, 'portal_signin'),
 *    every provider); e-mail through core's mailer (template otpCode), SMS only through the WP SMS bridge
 *    (Verification::sms_provider()). A code is sent only to an identity that owns entries; the answer is the same either way.
 *  - proofs (signed, 10 minutes) → an identity [{k: e|p|u, h}]; only core makes it (P6 hashes, Owner_Index).
 *  - reads (P9, P12, P13): the identity's entries, one entry as text to show (no F08 hidden value, no password, no
 *    signature image, no payment reference, no IP), its thread, its payments and receipt data, a 10-minute file link.
 *  - writes (P10, P11, P14, P15, P16): reply, close / reopen, cancel / reschedule a booking (the Booking add-on's
 *    contract), the owner's edit (F14's edit with the submit's checks, F05 places, F06 dates), a new link to a draft;
 *    each one logged in F14's history with the actor "owner", each e-mail or SMS behind efb_shield_allow_side_effect.
 *  - refusals: WP_Error with a stable code (efb_portal_*), data status; anything not owned is efb_portal_not_found.
 *
 * @package Emsfb
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Portal_Core {

	const API = 1;
	const FEATURES = array('index', 'otp', 'read', 'thread', 'reply', 'ticket', 'payments', 'files', 'cancel', 'reschedule', 'edit', 'drafts', 'privacy');
	const ERRORS = array('efb_portal_not_found' => 404, 'efb_portal_forbidden' => 403, 'efb_portal_rate_limited' => 429, 'efb_portal_window_closed' => 403,
		'efb_portal_capacity' => 409, 'efb_portal_paid_locked' => 403, 'efb_portal_closed' => 403, 'efb_portal_unavailable' => 503, 'efb_portal_invalid' => 400,
		'efb_portal_conflict' => 409, 'addon_required' => 409);
	/** A form's portal policy (the add-on's settings, §6.3 forms.{id}); core's defaults keep everything off. */
	const POLICY = array('on' => false, 'fields' => 'all', 'match' => 'id_field', 'reply' => false, 'attach' => false, 'close' => false, 'reopen_days' => 14,
		'cancel' => false, 'cancel_hours' => 24, 'paid_cancel' => false, 'reschedule' => false, 'reschedule_hours' => 24, 'reschedule_max' => 1,
		'edit' => false, 'edit_hours' => 24, 'drafts' => false, 'from_date' => '');
	const PROOF_TTL = 600;
	/** Answer types never shown to the owner (secrets, raw data, the page link). */
	const HIDDEN_TYPES = array('password', 'pswRegister', 'pswLogin', 'w_link', 'payment', 'html', 'heading', 'link', 'step', 'captcha_v2');

	/* ================================================================ registry */

	public static function register() {
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 40, 2);
	}

	/** AutoPilot / SitePilot: capabilities.portal_core (the contract; ready only while the portal add-on is on). */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out)) return $out;
		$on = function_exists('apply_filters') && apply_filters('efb_portal_active', false) === true;
		$pro = self::plan_ok();
		$out['portal_core'] = array('version' => self::API, 'features' => self::FEATURES, 'addon' => 'AdnPRT', 'package' => 'pro', 'ready' => self::active(),
			'reason' => self::active() ? null : (!$pro ? 'plan' : (!$on ? 'addon' : 'index')), 'index' => Owner_Index::state()['state'],
			'never' => 'an assistant links to the portal page only; it never asks for or enters a sign-in code and never reads an owner\'s entries');
		return $out;
	}

	/* ================================================================ guard, policy, errors */

	/** Whether the portal add-on is on (it answers true on efb_portal_active) on a Pro site with the tables. */
	public static function active() {
		$on = function_exists('apply_filters') && apply_filters('efb_portal_active', false) === true;
		return $on && self::plan_ok() && Owner_Index::ready();
	}

	/** Pro (§9; filter efb_portal_plan_ok, as efb_verify_plan for F02's codes). */
	public static function plan_ok() {
		$ok = !function_exists('efb_plan_at_least_efb') || efb_plan_at_least_efb('pro');
		return (bool) (function_exists('apply_filters') ? apply_filters('efb_portal_plan_ok', $ok) : $ok);
	}

	public static function err($code, $msg = '', array $data = array()) {
		$status = isset(self::ERRORS[$code]) ? self::ERRORS[$code] : 400;
		return new \WP_Error($code, $msg !== '' ? $msg : self::t($code === 'efb_portal_not_found' ? 'prtNotFound' : ($code === 'efb_portal_unavailable' ? 'prtUnavailable' : 'error403')), array_merge(array('status' => $status), $data));
	}

	public static function t($key) {
		static $l = array();
		if (!isset($l[$key])) {
			$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
			$r = $fn && method_exists($fn, 'text_efb') ? $fn->text_efb(array($key)) : array();
			$l[$key] = isset($r[$key]) && is_string($r[$key]) ? html_entity_decode($r[$key], ENT_QUOTES, 'UTF-8') : $key;
		}
		return $l[$key];
	}

	public static function policy($form_id) {
		$p = function_exists('apply_filters') ? apply_filters('efb_portal_form_policy', array(), (int) $form_id) : array();
		$p = array_merge(self::POLICY, is_array($p) ? array_intersect_key($p, self::POLICY) : array());
		foreach (array('on', 'reply', 'attach', 'close', 'cancel', 'paid_cancel', 'reschedule', 'edit', 'drafts') as $k) $p[$k] = !empty($p[$k]) && $p[$k] !== '0';
		foreach (array('reopen_days', 'cancel_hours', 'reschedule_hours', 'reschedule_max', 'edit_hours') as $k) $p[$k] = max(0, min(87600, (int) $p[$k]));
		$p['match'] = $p['match'] === 'verified' ? 'verified' : 'id_field';
		$p['fields'] = is_array($p['fields']) ? array_values(array_map('strval', $p['fields'])) : 'all';
		return $p;
	}

	/** One counted hit of a portal limit (per identity); a refusal is efb_portal_rate_limited with retry_after. */
	private static function limit($scope, array $identity, $window, $max) {
		$key = self::identity_key($identity);
		if (Rate_Limit::hit($scope, $key, $window, (int) apply_filters('efb_portal_limit', $max, $scope))) return true;
		$wait = max(1, ((int) floor(Rate_Limit::time() / $window) + 1) * $window - Rate_Limit::time());
		return self::err('efb_portal_rate_limited', sprintf(self::t('prtTooMany'), Verification::duration($wait)), array('retry_after' => $wait));
	}

	/** A stable key of an identity (its sorted hashes), for limits only. */
	public static function identity_key(array $identity) {
		$h = array();
		foreach ($identity as $i) if (is_array($i) && isset($i['k'], $i['h'])) $h[] = $i['k'] . ':' . $i['h'];
		sort($h);
		return hash('sha256', implode('|', $h));
	}

	/* ================================================================ P7: sign-in code, proofs, identity */

	private static function psid($ctx) {
		$p = isset($ctx['psid']) && is_scalar($ctx['psid']) ? (string) $ctx['psid'] : '';
		return preg_match('/^[A-Za-z0-9_-]{16,64}$/', $p) ? $p : '';
	}

	/**
	 * P7: a sign-in code to an e-mail address ('email') or a mobile number ('sms'). ctx: psid (required: the add-on's
	 * pre-session id, 16–64 of A-Z a-z 0-9 _ -), lang, captcha (a token when the add-on asks for a CAPTCHA).
	 * @return array|\WP_Error ['challenge_id', 'masked', 'expires_in', 'resend_after', 'code_length', 'message'] — the
	 *   same answer whether or not the identity has entries (a code is sent only when it has).
	 */
	public static function send_code($channel, $dest, array $ctx) {
		if (!self::active() || !Verification::ready()) return self::err('efb_portal_unavailable');
		$psid = self::psid($ctx);
		if ($psid === '' || !in_array($channel, Verification::CHANNELS, true)) return self::err('efb_portal_invalid', self::t('pleaseEnterVaildValue'));
		if (array_key_exists('captcha', $ctx) && class_exists('\\Emsfb\\Spam')) {
			$c = Spam::verify_captcha(is_string($ctx['captcha']) ? $ctx['captcha'] : '', 'portal_signin');
			if (empty($c['ok'])) return new \WP_Error('efb_captcha_' . ($c['code'] !== '' ? $c['code'] : 'failed'), Spam::captcha_message($c['code'], array()), array('status' => 422));
		}
		$st = Verification::channel_state($channel);
		if (!$st['ready']) return new \WP_Error('efb_verify_unavailable', Verification::t('vrfUnavailable'), array('status' => 503));
		$s = Verification::settings();
		$cs = $s[$channel];
		$n = Verification::normalize_destination($channel, $dest);
		if ($n === null || ($channel === 'sms' && !Verification::country_allowed($n, $s['sms']['allowed_countries']))) return new \WP_Error('efb_verify_destination', Verification::t('vrfDestination'), array('status' => 422));
		$dh = Verification::dest_hash($channel, $n);
		$masked = Verification::mask($channel, $n);
		$ip = class_exists('\\Emsfb\\Client_Ip') ? (string) Client_Ip::limit_key(0) : '';
		$side = apply_filters('efb_shield_allow_side_effect', true, array('channel' => 'otp_' . $channel, 'event' => 'portal_signin', 'form_id' => 0, 'recipients' => array($masked), 'source' => 'portal_signin'));
		$bot = apply_filters('efb_submit_bot_decision', false, array('setting' => array(), 'form' => array(), 'ip' => class_exists('\\Emsfb\\Client_Ip') ? Client_Ip::get() : '', 'efbFunction' => null, 'context' => 'portal_signin', 'form_id' => 0));
		if (!$side || $bot === true) return new \WP_Error('efb_verify_blocked', Verification::t('vrfBlocked'), array('status' => 403));
		if (($wait = Verification::locked_out($channel, $dh, $ip, $s)) > 0) return new \WP_Error('efb_verify_locked', Verification::t('vrfLocked'), array('status' => 429, 'retry_after' => $wait));
		$hist = Verification::dest_history($dh);
		if (($wait = Verification::cooldown_left($hist, $cs['resend_cooldowns'])) > 0) return new \WP_Error('efb_verify_cooldown', str_replace('%s', Verification::duration($wait), Verification::t('vrfLimit')), array('status' => 429, 'retry_after' => $wait));
		$lim = Verification::send_limits($channel, 0, $psid, $dh, $n, $ip, $s, true);
		if ($lim) return $lim['code'] === 'budget' ? new \WP_Error('efb_verify_unavailable', Verification::t('vrfUnavailable'), array('status' => 503)) : new \WP_Error('efb_verify_limit', str_replace('%s', Verification::duration($lim['retry_after']), Verification::t('vrfLimit')), array('status' => 429, 'retry_after' => $lim['retry_after']));
		$k = $channel === 'sms' ? 'p' : 'e';
		$nres = count($cs['resend_cooldowns']);
		$out = array('challenge_id' => bin2hex(random_bytes(16)), 'masked' => $masked, 'expires_in' => (int) $cs['ttl'], 'resend_after' => (int) $cs['resend_cooldowns'][min($hist['count'], $nres - 1)],
			'code_length' => (int) $cs['code_length'], 'message' => sprintf(self::t('prtCodeSent'), $masked));
		if (!Owner_Index::has_entries($k, Owner_Index::hash($k, $n))) return $out; // nothing is sent; the answer is the same
		Verification::supersede(0, 'portal', $psid, $dh);
		$provider = $channel === 'sms' ? Verification::sms_provider($s) : null;
		$code = Verification::make_code($cs['code_length']);
		$row = Verification::create_challenge(array('fid' => 0, 'field_id' => 'portal', 'channel' => $channel, 'dest_hash' => $dh, 'dest_mask' => $masked, 'sid' => $psid,
			'ip_hash' => Verification::ip_hash($ip), 'code' => $code, 'provider' => $provider ? $provider->id() : 'wp_mail', 'max_attempts' => $cs['max_attempts'], 'ttl' => $cs['ttl']));
		if (!$row) return new \WP_Error('efb_verify_unavailable', Verification::t('vrfUnavailable'), array('status' => 503));
		set_transient('efb_prt_ch_' . $row['challenge_id'], Owner_Index::hash($k, $n), max(60, (int) $cs['ttl']) + 60);
		$lang = isset($ctx['lang']) ? preg_replace('/[^A-Za-z_]/', '', (string) $ctx['lang']) : '';
		if ($channel === 'email') {
			$f = array('email_otp_subject' => str_replace('%s', '[website_name]', self::t('prtMailSubject')), 'email_otp_body' => self::t('prtMailBody') . '@n#[otp_code]');
			$ok = Verification::send_email($n, $code, $f, $cs, $lang);
		} else {
			$ok = !empty(Verification::send_sms($provider, $n, $code, array_merge($cs, array('message' => str_replace(array('%1$s', '%2$s'), array('{code}', '{site}'), self::t('prtSmsSignin')))), $lang)['ok']);
		}
		if (!$ok) {
			Verification::set_status($row['challenge_id'], 'failed');
			Verification::alert('send_failed', $channel);
			return new \WP_Error('efb_verify_provider', Verification::t('vrfFailed'), array('status' => 502));
		}
		do_action('efb_verify_sent', array('fid' => 0, 'field_id' => 'portal', 'channel' => $channel, 'dest_mask' => $masked, 'provider' => (string) $row['provider']));
		Verification::after_send($channel);
		$out['challenge_id'] = $row['challenge_id'];
		return $out;
	}

	/**
	 * P7: checks a sign-in code (Persian / Arabic digits and pasted text accepted). ctx: psid (the one of send_code()).
	 * A form's code is never accepted here, nor a portal code on a form (fid 0, field_id 'portal').
	 * @return array|\WP_Error ['proof' => signed token (10 minutes), 'kind' => 'e'|'p']
	 */
	public static function check_code($cid, $code, array $ctx) {
		if (!self::active() || !Verification::ready()) return self::err('efb_portal_unavailable');
		$psid = self::psid($ctx);
		$code = Verification::digits_only((string) $code);
		$row = $psid !== '' ? Verification::find((string) $cid, 0, 'portal', $psid) : null;
		if (!$row) return new \WP_Error('efb_verify_expired', Verification::t('vrfExpired'), array('status' => 404));
		if (strlen($code) < 4 || strlen($code) > 10) return new \WP_Error('efb_verify_invalid', Verification::t('vrfWrong'), array('status' => 400));
		$ch = $row['channel'];
		if ($row['status'] !== 'pending' || strtotime($row['expires_at'] . ' UTC') < Verification::now()) {
			if ($row['status'] === 'pending') Verification::set_status($row['challenge_id'], 'expired', 'pending');
			return $row['status'] === 'locked' ? new \WP_Error('efb_verify_locked', Verification::t('vrfLocked'), array('status' => 429)) : new \WP_Error('efb_verify_expired', Verification::t('vrfExpired'), array('status' => 410));
		}
		$ip = class_exists('\\Emsfb\\Client_Ip') ? (string) Client_Ip::limit_key(0) : '';
		if (($wait = Verification::locked_out($ch, $row['dest_hash'], $ip)) > 0 || !Verification::attempt($row['challenge_id'])) {
			Verification::set_status($row['challenge_id'], 'locked', 'pending');
			return new \WP_Error('efb_verify_locked', Verification::t('vrfLocked'), array('status' => 429, 'retry_after' => (int) $wait));
		}
		$left = max(0, (int) $row['max_attempts'] - (int) $row['attempts'] - 1);
		if ($row['code_hash'] === '' || !hash_equals($row['code_hash'], Verification::hash_code($row['challenge_id'], $code))) {
			Verification::record_failure($ch, $row['dest_hash'], $ip);
			if ($left <= 0) { Verification::set_status($row['challenge_id'], 'locked', 'pending'); return new \WP_Error('efb_verify_locked', Verification::t('vrfLocked'), array('status' => 429)); }
			return new \WP_Error('efb_verify_wrong', str_replace('%s', (string) $left, Verification::t('vrfWrong')), array('status' => 422, 'attempts_left' => $left));
		}
		if (!Verification::mark_verified($row['challenge_id']) || Verification::consume(array(array('cid' => $row['challenge_id']))) !== 1) return new \WP_Error('efb_verify_expired', Verification::t('vrfExpired'), array('status' => 410));
		do_action('efb_verify_verified', array('fid' => 0, 'field_id' => 'portal', 'channel' => $ch, 'dest_mask' => $row['dest_mask'], 'provider' => $row['provider']));
		$k = $ch === 'sms' ? 'p' : 'e';
		// The challenge keeps the destination's F02 hash; the proof carries the owner hash, made from the same value
		// when the code was sent (stored nowhere else): looked up through the index by the destination hash.
		$h = self::owner_hash_of_challenge($row);
		if ($h === '') return new \WP_Error('efb_verify_expired', Verification::t('vrfExpired'), array('status' => 410));
		return array('proof' => self::proof_issue($k, $h), 'kind' => $k);
	}

	/** The owner hash a challenge was sent to (kept with the challenge's id for the proof, 15 minutes at most). */
	private static function owner_hash_of_challenge(array $row) {
		$v = get_transient('efb_prt_ch_' . $row['challenge_id']);
		delete_transient('efb_prt_ch_' . $row['challenge_id']);
		return is_string($v) && preg_match('/^[a-f0-9]{64}$/', $v) ? $v : '';
	}

	private static function b64u($s) {
		return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
	}

	public static function proof_issue($k, $h) {
		$body = self::b64u(wp_json_encode(array('k' => $k, 'h' => $h, 'exp' => time() + self::PROOF_TTL, 'n' => bin2hex(random_bytes(6)))));
		return $body . '.' . hash_hmac('sha256', $body, Verification::key('portal-proof'));
	}

	/**
	 * The identity of a session: verified proofs (check_code()) and, when $uid > 0, the WordPress user (k 'u') and —
	 * unless $wp_email is false — the hash of that user's e-mail address. Never from data the client sends.
	 */
	public static function identity_from_proof(array $proofs, $uid = 0, $wp_email = true) {
		$out = array();
		foreach (array_slice($proofs, 0, 4) as $p) {
			$parts = is_string($p) ? explode('.', $p) : array();
			if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], Verification::key('portal-proof')), $parts[1])) continue;
			$d = json_decode(base64_decode(strtr($parts[0], '-_', '+/'), true), true);
			if (!is_array($d) || !isset($d['k'], $d['h'], $d['exp']) || (int) $d['exp'] < time() || !in_array($d['k'], array('e', 'p'), true)) continue;
			$out[$d['k'] . $d['h']] = array('k' => $d['k'], 'h' => (string) $d['h']);
		}
		$uid = (int) $uid;
		if ($uid > 0 && get_userdata($uid)) {
			$out['u' . $uid] = array('k' => 'u', 'h' => hash_hmac('sha256', 'u|' . $uid, Verification::key('owner')), 'uid' => $uid);
			$u = get_userdata($uid);
			if ($wp_email && $u && !empty($u->user_email)) { $h = Owner_Index::hash('e', $u->user_email); if ($h !== '') $out['e' . $h] = array('k' => 'e', 'h' => $h); }
		}
		return array_values($out);
	}

	/** An identity as the add-on keeps it, checked again (a 'u' entry must carry its own hash). */
	public static function clean_identity($identity) {
		$out = array();
		foreach (is_array($identity) ? array_slice($identity, 0, 6) : array() as $i) {
			if (!is_array($i) || !isset($i['k'], $i['h']) || !is_string($i['h'])) continue;
			if ($i['k'] === 'u') { if (!empty($i['uid']) && hash_equals(hash_hmac('sha256', 'u|' . (int) $i['uid'], Verification::key('owner')), $i['h'])) $out[] = array('k' => 'u', 'h' => $i['h'], 'uid' => (int) $i['uid']); }
			elseif (in_array($i['k'], array('e', 'p'), true) && preg_match('/^[a-f0-9]{64}$/', $i['h'])) $out[] = array('k' => $i['k'], 'h' => $i['h']);
		}
		return $out;
	}

	/* ================================================================ ownership, entries */

	/** The forms in the portal (policy on) with their policies. */
	private static function portal_forms() {
		static $cache = null;
		if ($cache !== null) return $cache;
		global $wpdb;
		$cache = array();
		foreach ((array) $wpdb->get_col("SELECT form_id FROM `{$wpdb->prefix}emsfb_form`") as $fid) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$p = self::policy((int) $fid);
			if ($p['on'] && !(class_exists('\\Emsfb\\Privacy') && Privacy::nostore((int) $fid))) $cache[(int) $fid] = $p;
		}
		return $cache;
	}

	public static function reset_cache() {
		self::$entries_cache = array();
	}

	private static $entries_cache = array();

	/** The ids of the entries an identity owns (active, of a form in the portal, from the form's from_date on). */
	private static function owned_ids(array $identity) {
		$key = self::identity_key($identity);
		if (isset(self::$entries_cache[$key])) return self::$entries_cache[$key];
		$forms = self::portal_forms();
		$verified = array_keys(array_filter($forms, function ($p) { return $p['match'] === 'verified'; }));
		$ids = Owner_Index::lookup($identity, $verified);
		$out = array();
		if ($ids && $forms) {
			global $wpdb;
			foreach (array_chunk($ids, 500) as $chunk) {
				$in = implode(',', array_fill(0, count($chunk), '%d'));
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
				foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT msg_id, form_id, date FROM `{$wpdb->prefix}emsfb_msg_` WHERE msg_id IN ({$in})" . Entries::active_sql(), $chunk), ARRAY_A) as $r) {
					$p = isset($forms[(int) $r['form_id']]) ? $forms[(int) $r['form_id']] : null;
					if ($p && ($p['from_date'] === '' || strcmp((string) $r['date'], (string) $p['from_date']) >= 0)) $out[(int) $r['msg_id']] = (int) $r['form_id'];
				}
			}
		}
		krsort($out);
		return self::$entries_cache[$key] = $out;
	}

	/** One owned entry (ARRAY_A with 'rows', 'form', 'policy'), else efb_portal_not_found — the same for "not yours". */
	public static function owned_entry($msg_id, $identity) {
		if (!self::active()) return self::err('efb_portal_unavailable');
		$identity = self::clean_identity($identity);
		$ids = $identity ? self::owned_ids($identity) : array();
		$msg_id = (int) $msg_id;
		if (!isset($ids[$msg_id])) return self::err('efb_portal_not_found');
		$e = Entries::entry($msg_id);
		$form = Entries::form_rows($ids[$msg_id]);
		if (!$e || !is_array($form) || $e['entry_status'] !== 0) return self::err('efb_portal_not_found');
		$e['rows'] = Privacy::decode_content($e['content']);
		$e['rows'] = is_array($e['rows']) ? $e['rows'] : array();
		$e['form'] = $form;
		$e['policy'] = self::policy($e['form_id']);
		$e['owner_state'] = self::owner_state($msg_id);
		$e['identity'] = $identity;
		return $e;
	}

	private static function owner_state($msg_id) {
		global $wpdb;
		$r = $wpdb->get_row($wpdb->prepare("SELECT owner_state, owner_state_at FROM `{$wpdb->prefix}emsfb_msg_` WHERE msg_id = %d", (int) $msg_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array($r) ? array('state' => (int) $r['owner_state'], 'at' => (string) $r['owner_state_at']) : array('state' => 0, 'at' => '');
	}

	/** The booking time of an entry (Unix time of its first advanced date, with its time or the day's start), or null. */
	public static function booking_time(array $rows) {
		foreach ($rows as $r) {
			if (!is_array($r) || ($r['type'] ?? '') !== 'advdate') continue;
			if (!empty($r['utc'])) return strtotime((string) $r['utc'] . (preg_match('/Z|[+-]\d\d:?\d\d$/', (string) $r['utc']) ? '' : ' UTC'));
			$d = substr((string) ($r['iso'] ?? $r['value'] ?? ''), 0, 10);
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { try { return (new \DateTimeImmutable($d . ' 00:00:00', wp_timezone()))->getTimestamp(); } catch (\Exception $e) { return null; } }
		}
		return null;
	}

	/** The thread's last author: 'support' | 'owner' | '' and its date. */
	private static function last_reply($msg_id) {
		global $wpdb;
		$r = $wpdb->get_row($wpdb->prepare("SELECT rsp_by, date, content FROM `{$wpdb->prefix}emsfb_rsp_` WHERE msg_id = %d ORDER BY rsp_id DESC LIMIT 1", (int) $msg_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if (!$r) return array('by' => '', 'date' => '');
		return array('by' => ((int) $r['rsp_by'] > 0 || (int) $r['rsp_by'] === -1) && !self::by_owner($r) ? 'support' : 'owner', 'date' => (string) $r['date']);
	}

	/** A reply written in the portal carries by "owner" on its message row. */
	private static function by_owner(array $rsp) {
		return strpos((string) $rsp['content'], '"by":"owner"') !== false || strpos((string) $rsp['content'], '\\"by\\":\\"owner\\"') !== false;
	}

	/** The portal status of an entry (spec §4.4). */
	public static function status($read, $owner_state, $last_by, $booking = null) {
		if ((int) $owner_state === 1) return 'cancelled';
		if ((int) $read === 2) return 'pending_payment';
		if ((int) $read === 4) return 'closed';
		if ($last_by === 'support') return 'awaiting_you';
		if ((int) $read === 0 || (int) $read === 3) return 'awaiting_support';
		return 'received';
	}

	/**
	 * P9: the identity's entries. q: form (id), status, from / to (Y-m-d), search (tracking code or answer text, within
	 * these entries, on the server), page, per_page (10 or 20).
	 * @return array ['rows', 'total', 'page', 'per_page'] or WP_Error
	 */
	public static function entries($identity, array $q = array()) {
		if (!self::active()) return self::err('efb_portal_unavailable');
		$identity = self::clean_identity($identity);
		if (!$identity) return array('rows' => array(), 'total' => 0, 'page' => 1, 'per_page' => 10);
		$ok = self::limit('prr', $identity, 60, 120);
		if (is_wp_error($ok)) return $ok;
		$ids = self::owned_ids($identity);
		$per = isset($q['per_page']) && (int) $q['per_page'] === 20 ? 20 : 10;
		$page = max(1, isset($q['page']) ? (int) $q['page'] : 1);
		if (!empty($q['form'])) $ids = array_filter($ids, function ($f) use ($q) { return $f === (int) $q['form']; });
		$rows = array();
		if ($ids) {
			global $wpdb;
			$in = implode(',', array_fill(0, count($ids), '%d'));
			$args = array_keys($ids);
			$where = '';
			if (!empty($q['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $q['from'])) { $where .= ' AND m.date >= %s'; $args[] = $q['from'] . ' 00:00:00'; }
			if (!empty($q['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $q['to'])) { $where .= ' AND m.date <= %s'; $args[] = $q['to'] . ' 23:59:59'; }
			$search = isset($q['search']) && is_scalar($q['search']) ? trim(Verification::ascii_digits((string) $q['search'])) : '';
			if ($search !== '') { $where .= ' AND (m.track = %s OR m.content LIKE %s)'; $args[] = $search; $args[] = '%' . $wpdb->esc_like(str_replace('"', '@efb@dq#', $search)) . '%'; }
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tables from $wpdb->prefix, placeholders and clauses built above
			$list = (array) $wpdb->get_results($wpdb->prepare("SELECT m.msg_id, m.form_id, m.track, m.date, m.read_, m.content, m.owner_state, f.form_name, (SELECT MAX(r.date) FROM `{$wpdb->prefix}emsfb_rsp_` r WHERE r.msg_id = m.msg_id) AS last_at FROM `{$wpdb->prefix}emsfb_msg_` m LEFT JOIN `{$wpdb->prefix}emsfb_form` f ON f.form_id = m.form_id WHERE m.msg_id IN ({$in}){$where} ORDER BY m.msg_id DESC", $args), ARRAY_A);
			foreach ($list as $r) {
				$content = Privacy::decode_content($r['content']);
				$bt = self::booking_time(is_array($content) ? $content : array());
				$last = self::last_reply((int) $r['msg_id']);
				$st = self::status($r['read_'], $r['owner_state'], $last['by']);
				if (!empty($q['status']) && $q['status'] !== $st && !($bt !== null && $q['status'] === ($bt > time() ? 'upcoming' : 'past'))) continue;
				$rows[] = array('msg_id' => (int) $r['msg_id'], 'form_id' => (int) $r['form_id'], 'form' => (string) $r['form_name'], 'track' => (string) $r['track'],
					'date' => (string) $r['date'], 'date_text' => self::date_text($r['date']), 'status' => $st, 'new_reply' => $last['by'] === 'support' && (int) $r['read_'] !== 4,
					'last_activity' => (string) ($r['last_at'] ?: $r['date']), 'booking' => $bt === null ? null : ($st === 'cancelled' ? 'cancelled' : ($bt > time() ? 'upcoming' : 'past')));
			}
		}
		return array('rows' => array_slice($rows, ($page - 1) * $per, $per), 'total' => count($rows), 'page' => $page, 'per_page' => $per);
	}

	public static function date_text($mysql, $with_time = true) {
		$ts = strtotime((string) $mysql . ' ' . wp_timezone_string());
		return $ts ? Form_Gate::locale_digits(wp_date(get_option('date_format') . ($with_time ? ' ' . get_option('time_format') : ''), $ts)) : '';
	}

	/** P9: one answer row as text to show; null when the owner must not see it. */
	public static function display_row(array $r, array $fields, $policy, array $show_hidden) {
		$type = isset($r['type']) ? (string) $r['type'] : '';
		$id = isset($r['id_']) ? (string) $r['id_'] : '';
		if ($id === '' || in_array($type, self::HIDDEN_TYPES, true) || isset($r['ip'])) return null;
		if ($type === 'hiddenField' && !isset($show_hidden[$id])) return null;
		if (is_array($policy['fields']) && !in_array($id, $policy['fields'], true)) return null;
		if (strpos($type, 'pay') === 0 && $type !== 'payRadio' && $type !== 'payCheckbox' && $type !== 'paySelect' && $type !== 'payMultiselect') return null;
		$def = isset($fields[$id]) ? $fields[$id] : null;
		$out = array('id' => $id, 'type' => $type, 'label' => wp_strip_all_tags(str_replace('@efb@dq#', '"', $def && isset($def['name']) ? (string) $def['name'] : (string) ($r['name'] ?? ''))));
		foreach (array('rg', 'ri', 'grp', 'part') as $k) if (isset($r[$k]) && is_scalar($r[$k])) $out[$k] = (string) $r[$k];
		$l = array('prvRedacted' => '—', 'esign' => self::t('esign'));
		if ($type === 'esign') { $out['value'] = Privacy::readable_value(array_diff_key($r, array('value' => 1)), $l); return $out; }
		if ($type === 'advdate') { $out['value'] = isset($r['disp']) ? (string) $r['disp'] : (string) ($r['value'] ?? ''); $out['iso'] = (string) ($r['value'] ?? ''); return $out; }
		if (isset($r['url']) && is_string($r['url']) && $r['url'] !== '' && ($r['value'] ?? '') === '@file@') {
			$out['file'] = array('name' => isset($r['fnm']) && is_string($r['fnm']) && $r['fnm'] !== '' ? $r['fnm'] : basename((string) parse_url($r['url'], PHP_URL_PATH)), 'private' => class_exists('\\Emsfb\\Uploads') && Uploads::is_private_url($r['url']));
			if (!$out['file']['private']) $out['file']['url'] = esc_url_raw($r['url']);
			$out['value'] = $out['file']['name'];
			return $out;
		}
		$out['value'] = wp_strip_all_tags(Privacy::readable_value($r, $l));
		return function_exists('apply_filters') ? apply_filters('efb_portal_entry_row', $out, $r, $def) : $out;
	}

	/** The rows of an entry as text to show (P9). */
	private static function display_rows(array $e) {
		$fields = array(); $show = array();
		foreach ($e['form'] as $i => $f) {
			if ($i < 1 || !is_array($f) || !isset($f['id_']) || ($f['type'] ?? '') === 'option') continue;
			$fields[(string) $f['id_']] = $f;
			if (($f['type'] ?? '') === 'hiddenField' && in_array($f['hdn_visitor_mail'] ?? '', array(1, '1', true), true)) $show[(string) $f['id_']] = true;
		}
		$out = array();
		foreach ($e['rows'] as $r) {
			if (!is_array($r)) continue;
			$d = self::display_row($r, $fields, $e['policy'], $show);
			if ($d !== null) $out[] = $d;
		}
		return $out;
	}

	/** P9: one entry for its owner: header, answers as text, files, payments, booking, ticket state and what may be done. */
	public static function entry($msg_id, $identity) {
		$e = self::owned_entry($msg_id, $identity);
		if (is_wp_error($e)) return $e;
		$ok = self::limit('prr', $e['identity'], 60, 120);
		if (is_wp_error($ok)) return $ok;
		$p = $e['policy'];
		$last = self::last_reply($e['msg_id']);
		$bt = self::booking_time($e['rows']);
		$st = self::status($e['read_'], $e['owner_state']['state'], $last['by']);
		$paid = Privacy::is_payment_entry($e['rows'], $e['read_']);
		global $wpdb;
		$form_name = (string) $wpdb->get_var($wpdb->prepare("SELECT form_name FROM `{$wpdb->prefix}emsfb_form` WHERE form_id = %d", $e['form_id'])); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$can = array(
			'reply' => $p['reply'] && ($st !== 'closed' || self::reopen_open($e)) && $st !== 'cancelled',
			'attach' => $p['reply'] && $p['attach'] && $st !== 'cancelled',
			'close' => $p['close'] && !in_array($st, array('closed', 'cancelled'), true),
			'reopen' => $p['reply'] && $st === 'closed' && self::reopen_open($e),
			'cancel' => $p['cancel'] && $bt !== null && $st !== 'cancelled' && $bt - $p['cancel_hours'] * 3600 > time() && (!$paid || $p['paid_cancel']) && self::booking_api(),
			'reschedule' => $p['reschedule'] && $bt !== null && $st !== 'cancelled' && $bt - $p['reschedule_hours'] * 3600 > time() && self::reschedules($e['msg_id']) < $p['reschedule_max'] && self::booking_api(),
			'edit' => !empty(self::editable_ids($e)),
		);
		return array(
			'msg_id' => $e['msg_id'], 'form_id' => $e['form_id'], 'form' => $form_name, 'track' => $e['track'], 'date' => $e['date_iso'], 'date_text' => self::date_text($e['date_iso']),
			'edited' => $e['edited_at'] !== '', 'status' => $st, 'answers' => self::display_rows($e), 'payments' => self::payments_of($e),
			'booking' => $bt === null ? null : array('time' => $bt, 'state' => $st === 'cancelled' ? 'cancelled' : ($bt > time() ? 'upcoming' : 'past'),
				'cancel_until' => $p['cancel'] ? $bt - $p['cancel_hours'] * 3600 : null, 'reschedule_until' => $p['reschedule'] ? $bt - $p['reschedule_hours'] * 3600 : null),
			'can' => $can, 'edit_until' => $p['edit'] ? strtotime($e['date_iso'] . ' ' . wp_timezone_string()) + $p['edit_hours'] * 3600 : null,
		);
	}

	/** Whether a closed ticket may still be reopened by a reply (reopen_days after it was closed; 0 = never). */
	private static function reopen_open(array $e) {
		if ($e['policy']['reopen_days'] <= 0) return false;
		global $wpdb;
		$at = (string) $wpdb->get_var($wpdb->prepare("SELECT date FROM `{$wpdb->prefix}emsfb_rsp_` WHERE msg_id = %d AND content LIKE %s ORDER BY rsp_id DESC LIMIT 1", $e['msg_id'], '%closed%')); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ts = $at !== '' ? strtotime($at . ' ' . wp_timezone_string()) : 0;
		return $ts > 0 && $ts + $e['policy']['reopen_days'] * 86400 > time();
	}

	/* ================================================================ P12: payments, P13: files */

	private static function payments_of(array $e) {
		$out = array();
		if (class_exists('\\Emsfb\\Payments\\Store') && Payments\Store::ready()) {
			global $wpdb;
			$t = Payments\Store::table('emsfb_pmt_');
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$list = (array) $wpdb->get_results($wpdb->prepare("SELECT uid, gateway, status, amount_minor, currency, refunded_minor, method, paid_at, created_at, finalized_at FROM `{$t}` WHERE msg_id = %d ORDER BY id", $e['msg_id']), ARRAY_A);
			$final = false;
			foreach ($list as $p) $final = $final || !empty($p['finalized_at']);
			foreach ($list as $p) {
				$gw = class_exists('\\Emsfb\\Payments\\Registry') ? Payments\Registry::get($p['gateway']) : null;
				$retry = !$final && (int) $e['read_'] === 2 && in_array($p['status'], array('created', 'pending', 'failed', 'canceled', 'expired'), true) && count($list) < 10;
				$row = array('uid' => $p['uid'], 'status' => $p['status'], 'amount_minor' => (int) $p['amount_minor'], 'currency' => $p['currency'], 'refunded_minor' => (int) $p['refunded_minor'],
					'gateway' => $gw && method_exists($gw, 'label') && $gw->label() !== '' ? $gw->label() : ucfirst((string) $p['gateway']), 'method' => (string) $p['method'],
					'paid_at' => $p['paid_at'] ? (string) $p['paid_at'] : null, 'created_at' => (string) $p['created_at'], 'can_retry' => $retry,
					'receipt' => in_array($p['status'], array('paid', 'refunded', 'partially_refunded'), true));
				if ($retry && class_exists('\\Emsfb\\Payments\\Service')) $row['retry'] = array('uid' => $p['uid'], 'sig' => Payments\Service::sign($p['uid'], $p['gateway']));
				$out[] = $row;
			}
		}
		foreach ($e['rows'] as $r) {
			if (!is_array($r) || ($r['type'] ?? '') !== 'payment') continue; // a payment of the legacy gateways (inside the answers)
			$out[] = array('uid' => '', 'status' => (int) $e['read_'] === 2 ? 'pending' : 'paid', 'amount' => isset($r['total']) && is_scalar($r['total']) ? (string) $r['total'] : '',
				'currency' => isset($r['currency']) && is_scalar($r['currency']) ? strtoupper((string) $r['currency']) : '', 'gateway' => ucfirst((string) ($r['paymentGateway'] ?? '')),
				'method' => (string) ($r['paymentmethod'] ?? ''), 'paid_at' => isset($r['paymentCreated']) && is_scalar($r['paymentCreated']) ? (string) $r['paymentCreated'] : null, 'can_retry' => false, 'receipt' => (int) $e['read_'] !== 2, 'legacy' => true);
		}
		return $out;
	}

	/** P12: the payments of an owned entry (no gateway reference, no payer address); a receipt is the paid / refunded row. */
	public static function payments($msg_id, $identity) {
		$e = self::owned_entry($msg_id, $identity);
		return is_wp_error($e) ? $e : self::payments_of($e);
	}

	/** P13: a link to one file of an owned entry (its index in the answers' files), signed for 10 minutes when private. */
	public static function file_link($msg_id, $identity, $n) {
		$e = self::owned_entry($msg_id, $identity);
		if (is_wp_error($e)) return $e;
		$ok = self::limit('prf', $e['identity'], 3600, 60);
		if (is_wp_error($ok)) return $ok;
		$i = 0;
		foreach ($e['rows'] as $r) {
			if (!is_array($r) || ($r['value'] ?? '') !== '@file@' || empty($r['url']) || !is_string($r['url'])) continue;
			if (is_array($e['policy']['fields']) && !in_array((string) ($r['id_'] ?? ''), $e['policy']['fields'], true)) continue;
			if ($i++ !== (int) $n) continue;
			if (!class_exists('\\Emsfb\\Uploads') || !Uploads::is_private_url($r['url'])) return array('url' => esc_url_raw($r['url']), 'expires_in' => null);
			$u = Uploads::signed_url_ttl($r['url'], 600);
			return $u !== '' ? array('url' => $u, 'expires_in' => 600) : self::err('efb_portal_not_found');
		}
		return self::err('efb_portal_not_found');
	}

	/* ================================================================ P10: thread, reply; P11: ticket state */

	/** P10: the conversation of an owned entry, in order: {by: support|you|system, name, text, date, files[], kind}. */
	public static function thread($msg_id, $identity) {
		$e = self::owned_entry($msg_id, $identity);
		if (is_wp_error($e)) return $e;
		global $wpdb;
		$list = (array) $wpdb->get_results($wpdb->prepare("SELECT rsp_id, rsp_by, content, date FROM `{$wpdb->prefix}emsfb_rsp_` WHERE msg_id = %d ORDER BY rsp_id", $e['msg_id']), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out = array();
		foreach ($list as $r) {
			$rows = Privacy::decode_content($r['content']);
			$item = array('id' => (int) $r['rsp_id'], 'date' => (string) $r['date'], 'date_text' => self::date_text($r['date']), 'text' => '', 'files' => array(), 'kind' => 'message');
			$by = (int) $r['rsp_by'];
			foreach (is_array($rows) ? $rows : array() as $row) {
				if (!is_array($row)) continue;
				if (in_array($row['type'] ?? '', array('closed', 'opened'), true)) $item['kind'] = $row['type'];
				if (($row['id_'] ?? '') === 'message' && isset($row['value']) && is_string($row['value'])) $item['text'] = wp_strip_all_tags(str_replace(array('@efb@dq#', '@n#'), array('"', "\n"), $row['value']));
				elseif (($row['value'] ?? '') === '@file@' && !empty($row['url'])) $item['files'][] = array('name' => basename((string) parse_url((string) $row['url'], PHP_URL_PATH)), 'private' => class_exists('\\Emsfb\\Uploads') && Uploads::is_private_url($row['url']));
			}
			$owner = self::by_owner($r) || $by === 0 || ($by > 0 && in_array(array('k' => 'u', 'h' => hash_hmac('sha256', 'u|' . $by, Verification::key('owner')), 'uid' => $by), $e['identity'], false));
			if ($item['kind'] !== 'message') $item['by'] = 'system';
			elseif ($owner && !($by > 0 && user_can($by, 'manage_options'))) $item['by'] = 'you';
			else { $item['by'] = 'support'; $u = $by > 0 ? get_userdata($by) : null; $item['name'] = $u ? $u->display_name : self::t('spprt'); }
			$out[] = $item;
		}
		return $out;
	}

	/** The allow-list of a reply's rows (the rule of forms/response/add): the text, and attachments the reply reserved. */
	private static function reply_rows(array $rows, $setting) {
		$out = array();
		$up = function_exists('wp_upload_dir') ? wp_upload_dir() : array();
		$base = preg_replace('#^https?:#i', '', rtrim(isset($up['baseurl']) ? (string) $up['baseurl'] : '', '/'));
		foreach (array_slice($rows, 0, 20) as $f) {
			$f = is_array($f) ? $f : (is_object($f) ? get_object_vars($f) : null);
			if (!$f) return null;
			$s = function ($k) use ($f) { return isset($f[$k]) && is_scalar($f[$k]) ? (string) $f[$k] : ''; };
			if ($s('type') === 'allformat') {
				$u = $s('url');
				if (empty($setting->dsupfile) || $base === '' || stripos(preg_replace('#^https?:#i', '', $u), $base . '/') !== 0) return null;
				$out[] = (object) array('id_' => sanitize_text_field($s('id_')), 'name' => sanitize_text_field($s('name')), 'amount' => (int) $s('amount'), 'type' => 'allformat', 'value' => '@file@', 'url' => sanitize_url($u), 'session' => '');
			} elseif ($s('id_') === 'message' && isset($f['value']) && is_string($f['value'])) {
				$v = sanitize_text_field($f['value']);
				if ($v === '' || (function_exists('mb_strlen') ? mb_strlen($v) : strlen($v)) > 5000) return null;
				$out[] = (object) array('id_' => 'message', 'name' => 'message', 'type' => 'text', 'amount' => 0, 'value' => $v, 'by' => 'owner', 'session' => '');
			} else {
				return null;
			}
		}
		return $out ?: null;
	}

	/**
	 * P10: a reply. actor: ['kind' => 'owner', 'identity' => …] (the portal). The same storage, entry state and
	 * notifications as a visitor's reply on the tracking page (_Public::entry_reply_store_efb()). rows: the reply's rows
	 * ({id_: 'message', value} and {type: 'allformat', url} reserved with efb_portal_reply_upload_token()).
	 */
	public static function add_reply($msg_id, array $rows, array $actor) {
		if (($actor['kind'] ?? '') !== 'owner') return self::err('efb_portal_forbidden');
		$e = self::owned_entry($msg_id, isset($actor['identity']) ? $actor['identity'] : array());
		if (is_wp_error($e)) return $e;
		if (!$e['policy']['reply'] || $e['owner_state']['state'] === 1) return self::err('efb_portal_forbidden');
		if ((int) $e['read_'] === 4 && !self::reopen_open($e)) return self::err('efb_portal_closed', self::t('prtClosedNew'));
		$ok = self::limit('prp', $e['identity'], 3600, 10);
		if (is_wp_error($ok)) return $ok;
		$setting = get_setting_Emsfb('decoded');
		$message = self::reply_rows($rows, $setting);
		if ($message === null) return self::err('efb_portal_invalid', self::t('pleaseEnterVaildValue'));
		if (!$e['policy']['attach']) foreach ($message as $m) if ($m->type === 'allformat') return self::err('efb_portal_forbidden');
		$pub = class_exists('\\Emsfb\\_Public') ? _Public::$instance_efb : null;
		if (!$pub || !is_object($setting)) return self::err('efb_portal_unavailable');
		if (!apply_filters('efb_shield_allow_side_effect', true, array('channel' => 'email', 'event' => 'portal_reply', 'form_id' => $e['form_id'], 'tracking_code' => $e['track'], 'source' => 'portal_reply'))) return self::err('efb_portal_forbidden');
		foreach ($message as $row) foreach (get_object_vars($row) as $k => $v) if (is_string($v) && strpos($v, '"') !== false) $row->$k = str_replace('"', '@efb@dq#', $v);
		$m = str_replace('"', '\\"', wp_json_encode($message, JSON_UNESCAPED_UNICODE));
		$fn = get_efbFunction();
		$pub->lanText = $fn->text_efb(array('guest', 'spprt', 'messageSent', 'errorSomthingWrong', 'error405'));
		$uid = 0;
		foreach ($e['identity'] as $i) if ($i['k'] === 'u') $uid = (int) $i['uid'];
		$pub->efb_uid = $uid;
		$pub->setting = $setting;
		$last = end($e['rows']);
		$pro = in_array((int) get_option('emsfb_pro'), array(1, 3), true);
		$r = $pub->entry_reply_store_efb(array('admin_verified_rsp' => false, 'id' => $e['msg_id'], 'track' => $e['track'], 'message' => $message, 'm' => $m,
			'ip' => Privacy::store_ip(Privacy::client_ip(), $e['form_id']), 'data_POST' => array(), 'value' => array((object) array('content' => $e['content'], 'track' => $e['track'], 'form_id' => $e['form_id'])),
			'msg_obj' => $e['rows'], 'link_w' => is_array($last) && ($last['type'] ?? '') === 'w_link' ? $last['value'] : 'null', 'setting' => $setting,
			'email_actived' => function_exists('emsfb_is_email_sending_enabled_efb') && emsfb_is_email_sending_enabled_efb($setting), 'pro' => $pro, 'rsp_by' => $uid > 0 ? 'user' : 'guest',
			'table_name' => $GLOBALS['wpdb']->prefix . 'emsfb_rsp_'));
		if (empty($r['success'])) return self::err('efb_portal_invalid', wp_strip_all_tags((string) ($r['m'] ?? '')));
		global $wpdb;
		$rsp_id = (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(rsp_id) FROM `{$wpdb->prefix}emsfb_rsp_` WHERE msg_id = %d", $e['msg_id'])); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		Entries::log($e['msg_id'], 'owner_reply', '', null, null, 'owner');
		do_action('efb_entry_reply_added', $e['msg_id'], $rsp_id, 'owner');
		self::reset_cache();
		return array('rsp_id' => $rsp_id);
	}

	/** P10: a token to attach a file to a reply, for the existing upload route (forms/file/upload, the response box). */
	public static function reply_upload_token($msg_id, $identity) {
		$e = self::owned_entry($msg_id, $identity);
		if (is_wp_error($e)) return $e;
		if (!$e['policy']['reply'] || !$e['policy']['attach']) return self::err('efb_portal_forbidden');
		$ok = self::limit('pru', $e['identity'], 3600, 20);
		if (is_wp_error($ok)) return $ok;
		$token = wp_generate_password(48, false, false);
		$rec = array('message_id' => $e['msg_id'], 'track_hash' => hash('sha256', $e['track']), 'ip_hash' => hash('sha256', Privacy::client_ip()));
		return set_transient('emsfb_response_upload_' . hash('sha256', $token), $rec, 30 * MINUTE_IN_SECONDS) ? array('token' => $token, 'track' => $e['track']) : self::err('efb_portal_unavailable');
	}

	/**
	 * P11: the ticket state. state: 'closed' | 'open'; actor: ['kind' => 'owner', 'identity' => …] (an owner may
	 * close when the policy allows, reopen within reopen_days) or ['kind' => 'admin'] (manage the entries).
	 * Writes the system message of the thread (as the panel's Close / Reopen) and read_ (closed 4, reopened by the
	 * owner 3, by an administrator 1); F14's history; efb_entry_ticket_state.
	 */
	public static function set_ticket_state($msg_id, $state, array $actor) {
		$state = $state === 'closed' ? 'closed' : ($state === 'open' ? 'open' : '');
		if ($state === '') return self::err('efb_portal_invalid');
		$kind = isset($actor['kind']) ? (string) $actor['kind'] : '';
		if ($kind === 'owner') {
			$e = self::owned_entry($msg_id, isset($actor['identity']) ? $actor['identity'] : array());
			if (is_wp_error($e)) return $e;
			if ($state === 'closed' && !$e['policy']['close']) return self::err('efb_portal_forbidden');
			if ($state === 'open' && (!$e['policy']['reply'] || !self::reopen_open($e))) return self::err('efb_portal_window_closed', self::t('prtClosedNew'));
		} elseif ($kind === 'admin' && function_exists('current_user_can') && (current_user_can('manage_options') || current_user_can('Emsfb'))) {
			$e = Entries::entry((int) $msg_id);
			if (!$e) return self::err('efb_portal_not_found');
		} else {
			return self::err('efb_portal_forbidden');
		}
		if (($state === 'closed') === ((int) $e['read_'] === 4)) return true;
		global $wpdb;
		$row = array(array('id_' => 'message', 'name' => 'message', 'type' => $state === 'closed' ? 'closed' : 'opened', 'amount' => 0, 'value' => self::t($state === 'closed' ? 'clsdrspn' : 'clsdrspo'), 'by' => $kind === 'owner' ? 'owner' : 'admin', 'session' => ''));
		$wpdb->insert($wpdb->prefix . 'emsfb_rsp_', array('ip' => '', 'content' => str_replace('"', '\\"', wp_json_encode($row, JSON_UNESCAPED_UNICODE)), 'msg_id' => (int) $e['msg_id'], 'rsp_by' => $kind === 'owner' ? 0 : (int) get_current_user_id(), 'read_' => $kind === 'owner' ? 0 : 1, 'date' => wp_date('Y-m-d H:i:s')));
		$to = $state === 'closed' ? 4 : ($kind === 'owner' ? 3 : 1);
		$wpdb->update($wpdb->prefix . 'emsfb_msg_', array('read_' => $to), array('msg_id' => (int) $e['msg_id']));
		Entries::log((int) $e['msg_id'], 'ticket', '', (string) $e['read_'], (string) $to, $kind);
		do_action('efb_entry_ticket_state', (int) $e['msg_id'], $state, $kind);
		self::reset_cache();
		return true;
	}

	/* ================================================================ P14: cancel, reschedule */

	/** Whether the Booking add-on implements the portal's booking contract (efb_portal_booking_api = its version). */
	public static function booking_api() {
		return (int) apply_filters('efb_portal_booking_api', 0) >= 1;
	}

	private static function reschedules($msg_id) {
		global $wpdb;
		return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->prefix}emsfb_msg_log_` WHERE msg_id = %d AND action = %s", (int) $msg_id, 'owner_reschedule')); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * P14: the owner cancels a booking: owner_state 1 (once: a second cancel changes nothing), the F05 places and the
	 * F11 total place given back, efb_entry_cancelled($msg_id, $form_id, $rows) for the Booking add-on (its dates),
	 * F14's history (owner_cancel), an e-mail to the site and to the owner. Without the Booking add-on: addon_required.
	 */
	public static function owner_cancel($msg_id, $identity, $reason = '') {
		$e = self::owned_entry($msg_id, $identity);
		if (is_wp_error($e)) return $e;
		if (!self::booking_api()) return self::err('addon_required', self::t('prtAddonBooking'), array('addon' => 'AdnBEF'));
		$p = $e['policy'];
		$bt = self::booking_time($e['rows']);
		if (!$p['cancel'] || $bt === null) return self::err('efb_portal_forbidden');
		if ($e['owner_state']['state'] === 1) return self::err('efb_portal_conflict', self::t('prtCancelled'));
		if ($bt - $p['cancel_hours'] * 3600 <= time()) return self::err('efb_portal_window_closed', self::t('prtWindowClosed'));
		if (!$p['paid_cancel'] && Privacy::is_payment_entry($e['rows'], $e['read_'])) return self::err('efb_portal_paid_locked', self::t('prtPaidLocked'));
		$ok = self::limit('prc', $e['identity'], 86400, 5);
		if (is_wp_error($ok)) return $ok;
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		if ((int) $wpdb->query($wpdb->prepare("UPDATE `{$wpdb->prefix}emsfb_msg_` SET owner_state = 1, owner_state_at = %s WHERE msg_id = %d AND owner_state = 0", wp_date('Y-m-d H:i:s'), $e['msg_id'])) !== 1) return self::err('efb_portal_conflict', self::t('prtCancelled'));
		$released = self::release_places($e);
		Entries::log($e['msg_id'], 'owner_cancel', '', null, null, 'owner' . ($reason !== '' ? ': ' . sanitize_text_field($reason) : ''));
		do_action('efb_entry_cancelled', $e['msg_id'], $e['form_id'], $e['rows']);
		self::notify($e, 'prtAdminCancelled', 'prtCancelled');
		self::reset_cache();
		return array('state' => 'cancelled', 'released' => $released);
	}

	/** The F05 places (choices with a capacity, unless a paid hold gave them back already) and the F11 total place. */
	private static function release_places(array $e) {
		$n = 0;
		if (class_exists('\\Emsfb\\Stock') && Stock::ready()) {
			$h = Stock::hold_get($e['msg_id']);
			if (!$h || empty($h['r'])) $n += Stock::release($e['form_id'], $h ? $h['c'] : Stock::claims($e['form'], $e['rows']));
			if ($h) Stock::hold_delete($e['msg_id']);
		}
		if (class_exists('\\Emsfb\\Form_Gate') && Rate_Limit::ready()) {
			$b = Form_Gate::total_bucket($e['form_id']);
			if (Rate_Limit::peek_bucket($b) !== null && Rate_Limit::release_bucket($b)) $n++;
			Form_Gate::flush_cache();
		}
		return $n;
	}

	/** An e-mail to the site (admin key) and to the owner's addresses of the entry (owner key), behind the shield. */
	private static function notify(array $e, $admin_key, $owner_key) {
		$s = get_setting_Emsfb('decoded');
		if (!function_exists('emsfb_is_email_sending_enabled_efb') || !emsfb_is_email_sending_enabled_efb($s)) return;
		$site = wp_strip_all_tags(get_bloginfo('name'));
		$to = is_object($s) && !empty($s->emailSupporter) && is_email($s->emailSupporter) ? $s->emailSupporter : get_option('admin_email');
		$f0 = isset($e['form'][0]) && is_array($e['form'][0]) ? $e['form'][0] : array();
		$mine = array();
		foreach ($e['rows'] as $r) if (is_array($r) && ($r['type'] ?? '') === 'email' && is_string($r['value'] ?? null) && Privacy::entry_matches_email(array($r), $f0, $r['value'])) $mine[] = $r['value'];
		$mails = array(array($to, sprintf(self::t($admin_key), $e['track'])), array(implode(',', array_unique($mine)), self::t($owner_key) . ' ' . self::t('trackNo') . ': ' . $e['track']));
		foreach ($mails as $m) {
			if ($m[0] === '' || !apply_filters('efb_shield_allow_side_effect', true, array('channel' => 'email', 'event' => 'portal_' . $owner_key, 'form_id' => $e['form_id'], 'tracking_code' => $e['track'], 'source' => 'portal'))) continue;
			wp_mail($m[0], '[' . $site . '] ' . $m[1], $m[1]);
		}
	}

	/** One advanced-date change checked like a submit (F06 rules) and reserved through efb_date_before_commit. */
	private static function date_change(array $e, $field_id, $value) {
		$def = null; $old = null;
		foreach ($e['form'] as $i => $f) if ($i > 0 && is_array($f) && ($f['id_'] ?? '') === (string) $field_id && ($f['type'] ?? '') === 'advdate') $def = $f;
		foreach ($e['rows'] as $r) if (is_array($r) && ($r['id_'] ?? '') === (string) $field_id && ($r['type'] ?? '') === 'advdate') $old = $r;
		if (!$def || !$old || !function_exists('efb_date_validate_value')) return self::err('efb_portal_invalid');
		$res = efb_date_validate_value($def, is_scalar($value) ? (string) $value : '', array('form_id' => $e['form_id'], 'cal' => (string) ($old['cal'] ?? ''), 'visitor_tz' => (string) ($old['tz'] ?? '')));
		if (empty($res['ok']) || empty($res['row']['value'])) return self::err('efb_portal_invalid', !empty($res['errors']) ? $res['errors'][0]->get_error_message() : self::t('pleaseEnterVaildValue'), array('field_id' => (string) $field_id));
		$row = array_merge(array_intersect_key($old, array_flip(array('id_', 'name', 'amount'))), $res['row'], array('type' => 'advdate'));
		$ok = apply_filters('efb_date_before_commit', true, array($row), $e['form_id'], array('form_id' => $e['form_id'], 'msg_id' => $e['msg_id'], 'actor' => 'owner'));
		if (is_wp_error($ok)) return self::err('efb_portal_capacity', self::t('prtSlotTaken'), array('field_id' => (string) $field_id));
		return array('row' => $row, 'old' => $old);
	}

	/** Writes changed advanced-date rows into the entry (atomic on the content read), then the F06 after / released actions. */
	private static function date_commit($msg_id, array $changes, $log_action) {
		global $wpdb;
		$e = Entries::entry($msg_id);
		$rows = $e ? Privacy::decode_content($e['content']) : null;
		$newrows = array_map(function ($c) { return $c['row']; }, $changes);
		if (!is_array($rows)) { do_action('efb_date_commit_failed', $newrows, (int) ($e['form_id'] ?? 0), array('msg_id' => (int) $msg_id)); return self::err('efb_portal_conflict'); }
		foreach ($rows as $k => $r) foreach ($changes as $c) if (is_array($r) && ($r['id_'] ?? '') === $c['row']['id_'] && ($r['type'] ?? '') === 'advdate') $rows[$k] = $c['row'];
		$content = Privacy::encode_content($rows);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		if ((int) $wpdb->query($wpdb->prepare("UPDATE `{$wpdb->prefix}emsfb_msg_` SET content = %s, edited_at = %s WHERE msg_id = %d AND content = %s", $content, wp_date('Y-m-d H:i:s'), (int) $msg_id, (string) $e['content'])) !== 1) {
			do_action('efb_date_commit_failed', $newrows, (int) $e['form_id'], array('msg_id' => (int) $msg_id));
			return self::err('efb_portal_conflict');
		}
		do_action('efb_date_after_commit', $newrows, (int) $e['form_id'], (string) $e['track'], array('form_id' => (int) $e['form_id'], 'msg_id' => (int) $msg_id, 'actor' => 'owner'));
		do_action('efb_date_released', array_map(function ($c) { return $c['old']; }, $changes), (int) $e['form_id'], (string) $e['track'], array('form_id' => (int) $e['form_id'], 'msg_id' => (int) $msg_id, 'actor' => 'owner'));
		foreach ($changes as $c) Entries::log((int) $msg_id, $log_action, $c['row']['id_'], (string) ($c['old']['disp'] ?? $c['old']['value'] ?? ''), (string) ($c['row']['disp'] ?? $c['row']['value']), 'owner');
		do_action('efb_entry_updated', (int) $msg_id, Privacy::decode_content($e['content']), $rows);
		return $rows;
	}

	/**
	 * P14: the owner moves a booking to another date (an advdate field): checked by the field's rules, reserved through
	 * efb_date_before_commit (a taken date is refused and nothing changes), then the old date released
	 * (efb_date_released). Within reschedule_hours before the booking, at most reschedule_max times.
	 */
	public static function owner_reschedule($msg_id, $identity, $field_id, $value) {
		$e = self::owned_entry($msg_id, $identity);
		if (is_wp_error($e)) return $e;
		if (!self::booking_api()) return self::err('addon_required', self::t('prtAddonBooking'), array('addon' => 'AdnBEF'));
		$p = $e['policy'];
		$bt = self::booking_time($e['rows']);
		if (!$p['reschedule'] || $bt === null || $e['owner_state']['state'] === 1) return self::err('efb_portal_forbidden');
		if ($bt - $p['reschedule_hours'] * 3600 <= time() || self::reschedules($e['msg_id']) >= $p['reschedule_max']) return self::err('efb_portal_window_closed', self::t('prtWindowClosed'));
		if (!$p['paid_cancel'] && Privacy::is_payment_entry($e['rows'], $e['read_']) && !apply_filters('efb_portal_paid_reschedule', true, $e['msg_id'])) return self::err('efb_portal_paid_locked', self::t('prtPaidLocked'));
		$ok = self::limit('prc', $e['identity'], 86400, 5);
		if (is_wp_error($ok)) return $ok;
		$c = self::date_change($e, $field_id, $value);
		if (is_wp_error($c)) return $c;
		$rows = self::date_commit($e['msg_id'], array($c), 'owner_reschedule');
		if (is_wp_error($rows)) return $rows;
		self::notify($e, 'prtAdminRescheduled', 'prtRescheduledMail');
		self::reset_cache();
		return array('value' => $c['row']['value'], 'disp' => (string) ($c['row']['disp'] ?? ''));
	}

	/* ================================================================ P15: the owner's edit */

	/** The field ids the owner may change now (policy edit, the window, before support answered, never prices). */
	private static function editable_ids(array $e) {
		$p = $e['policy'];
		if (!$p['edit'] || $e['owner_state']['state'] === 1 || in_array((int) $e['read_'], array(2, 4), true)) return array();
		if (strtotime($e['date_iso'] . ' ' . wp_timezone_string()) + $p['edit_hours'] * 3600 <= time() || self::last_reply($e['msg_id'])['by'] === 'support') return array();
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		if ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->prefix}emsfb_rsp_` WHERE msg_id = %d AND rsp_by <> 0", $e['msg_id'])) > 0) return array();
		$targets = array();
		if (!empty($e['form'][0]['logic_rules']) && is_array($e['form'][0]['logic_rules'])) {
			array_walk_recursive($e['form'][0]['logic_rules'], function ($v, $k) use (&$targets) { if (in_array($k, array('target', 'field', 'id'), true) && is_scalar($v)) $targets[(string) $v] = true; });
		}
		$out = array();
		foreach (Entries::edit_fields($e['form_id'], $e['rows'], (int) $e['read_']) as $f) {
			$def = null;
			foreach ($e['form'] as $i => $r) if ($i > 0 && is_array($r) && ($r['id_'] ?? '') === $f['id']) $def = $r;
			if (!$def || !in_array($f['mode'], array('edit', 'advdate'), true) || strpos($f['type'], 'pay') === 0 || $f['type'] === 'prcfld' || isset($targets[$f['id']])) continue;
			if (!empty($def['disabled']) && $def['disabled'] !== '0' || !empty($def['hidden']) && $def['hidden'] !== '0' || !empty($def['readonly']) && $def['readonly'] !== '0') continue;
			if (is_array($p['fields']) && !in_array($f['id'], $p['fields'], true)) continue;
			$out[] = $f['id'];
		}
		foreach ($e['form'] as $i => $r) if ($i > 0 && is_array($r) && ($r['type'] ?? '') === 'advdate' && empty($r['disabled']) && self::booking_api() && (!is_array($p['fields']) || in_array((string) $r['id_'], $p['fields'], true))) $out[] = (string) $r['id_'];
		return array_values(array_unique($out));
	}

	/** P15: the fields the owner may edit now, with the entry's hash (for owner_update()). */
	public static function owner_editable($msg_id, $identity) {
		$e = self::owned_entry($msg_id, $identity);
		if (is_wp_error($e)) return array();
		$ids = self::editable_ids($e);
		return $ids ? array('fields' => $ids, 'hash' => md5((string) $e['content']), 'until' => strtotime($e['date_iso'] . ' ' . wp_timezone_string()) + $e['policy']['edit_hours'] * 3600) : array();
	}

	/**
	 * P15: the owner's edit: F14's edit (Entries::update) with the submit's checks (required, formats, F03 unique, F05
	 * places taken again, F04 calculations; prices and payments locked) and F06 dates reserved again; refused outside
	 * the window, after support answered, or on a field not editable. $changes = [{id_, value}].
	 */
	public static function owner_update($msg_id, $identity, $hash, array $changes) {
		$e = self::owned_entry($msg_id, $identity);
		if (is_wp_error($e)) return $e;
		$ids = self::editable_ids($e);
		if (!$ids) return self::err('efb_portal_window_closed', self::t('prtWindowClosed'));
		if (!is_string($hash) || !hash_equals(md5((string) $e['content']), $hash)) return self::err('efb_portal_conflict', self::t('entConflict'));
		$ok = self::limit('pre', $e['identity'], 3600, 10);
		if (is_wp_error($ok)) return $ok;
		$dates = array(); $rest = array();
		foreach (array_slice($changes, 0, 200) as $c) {
			$id = is_array($c) && isset($c['id_']) && is_scalar($c['id_']) ? (string) $c['id_'] : '';
			if ($id === '' || !in_array($id, $ids, true)) return self::err('efb_portal_forbidden', self::t('entReadOnly'), array('field_id' => $id));
			$is_date = false;
			foreach ($e['form'] as $i => $r) if ($i > 0 && is_array($r) && ($r['id_'] ?? '') === $id && ($r['type'] ?? '') === 'advdate') $is_date = true;
			if ($is_date) { $dc = self::date_change($e, $id, $c['value'] ?? ''); if (is_wp_error($dc)) { if ($dates) do_action('efb_date_commit_failed', array_map(function ($x) { return $x['row']; }, $dates), $e['form_id'], array()); return $dc; } $dates[] = $dc; }
			else $rest[] = array('id_' => $id, 'value' => $c['value'] ?? '');
		}
		if ($rest) {
			$r = Entries::update($e['msg_id'], $hash, $rest, '', array('actor' => 'owner'));
			if (empty($r['ok'])) {
				if ($dates) do_action('efb_date_commit_failed', array_map(function ($x) { return $x['row']; }, $dates), $e['form_id'], array());
				$map = array('efb_choice_full' => 'efb_portal_capacity', 'efb_entry_conflict' => 'efb_portal_conflict', 'efb_entry_readonly' => 'efb_portal_paid_locked');
				return self::err(isset($map[$r['code']]) ? $map[$r['code']] : 'efb_portal_invalid', wp_strip_all_tags((string) $r['m']), array('field_id' => (string) ($r['field_id'] ?? ''), 'reason' => (string) $r['code']));
			}
		}
		if ($dates) { $rows = self::date_commit($e['msg_id'], $dates, 'edit'); if (is_wp_error($rows)) return $rows; }
		self::reset_cache();
		return array('ok' => true, 'hash' => md5((string) Entries::entry($e['msg_id'])['content']));
	}

	/* ================================================================ P16: drafts, P19: privacy */

	/** P16: the identity's unfinished forms (F11 "save and continue later") of forms in the portal with drafts on. */
	public static function drafts($identity) {
		if (!self::active() || !class_exists('\\Emsfb\\Drafts') || !Drafts::ready()) return array();
		$identity = self::clean_identity($identity);
		$out = array();
		foreach (Drafts::of_identity($identity) as $d) {
			$p = self::policy((int) $d['fid']);
			if (!$p['on'] || !$p['drafts']) continue;
			$out[] = array('id' => (int) $d['id'], 'form_id' => (int) $d['fid'], 'progress' => (int) $d['progress'], 'updated' => (string) $d['updated_at'], 'expires' => (string) $d['expires_at'], 'expires_text' => Drafts::expiry_text($d['expires_at']));
		}
		return $out;
	}

	/** P16: a new link to continue a draft: the token turns (the earlier link stops working). $page = the form's page. */
	public static function draft_link($draft_id, $identity, $page = '') {
		if (!self::active() || !class_exists('\\Emsfb\\Drafts')) return self::err('efb_portal_unavailable');
		$identity = self::clean_identity($identity);
		$ok = $identity ? self::limit('prd', $identity, 3600, 20) : self::err('efb_portal_not_found');
		if (is_wp_error($ok)) return $ok;
		foreach (self::drafts($identity) as $d) if ($d['id'] === (int) $draft_id) {
			$u = Drafts::reissue_link((int) $draft_id, $page);
			return $u !== '' ? $u : self::err('efb_portal_not_found');
		}
		return self::err('efb_portal_not_found');
	}

	/**
	 * §4.10: a request about the owner's data. With an e-mail address the identity owns, WordPress' own request
	 * (wp_create_user_request + wp_send_user_request: confirmed by e-mail, then F13's exporters / erasers run); a
	 * mobile-only identity: a note on its entries and an e-mail to the site. $type: export | erase.
	 */
	public static function privacy_request($identity, $email, $type) {
		if (!self::active()) return self::err('efb_portal_unavailable');
		$identity = self::clean_identity($identity);
		$action = $type === 'erase' ? 'remove_personal_data' : 'export_personal_data';
		$h = is_string($email) && $email !== '' ? Owner_Index::hash('e', $email) : '';
		foreach ($identity as $i) {
			if ($i['k'] !== 'e' || $h === '' || !hash_equals($i['h'], $h)) continue;
			$ok = self::limit('prq', $identity, 86400, 3);
			if (is_wp_error($ok)) return $ok;
			if (!function_exists('wp_create_user_request')) require_once ABSPATH . 'wp-admin/includes/user.php';
			$rid = wp_create_user_request(Verification::normalize_destination('email', $email), $action);
			if (is_wp_error($rid) && $rid->get_error_code() !== 'duplicate_request') return self::err('efb_portal_invalid', $rid->get_error_message());
			if (!is_wp_error($rid)) wp_send_user_request($rid);
			return array('sent' => 'email');
		}
		$ids = array_keys(self::owned_ids($identity));
		if (!$ids || $h !== '' || (is_string($email) && trim($email) !== '')) return self::err('efb_portal_not_found'); // an address that is not this identity's
		$ok = self::limit('prq', $identity, 86400, 3);
		if (is_wp_error($ok)) return $ok;
		foreach (array_slice($ids, 0, 50) as $id) Entries::note_add($id, sprintf(self::t('prtPrivacyNote'), $type));
		$s = get_setting_Emsfb('decoded');
		$to = is_object($s) && !empty($s->emailSupporter) && is_email($s->emailSupporter) ? $s->emailSupporter : get_option('admin_email');
		if (apply_filters('efb_shield_allow_side_effect', true, array('channel' => 'email', 'event' => 'portal_privacy', 'form_id' => 0, 'source' => 'portal'))) wp_mail($to, '[' . wp_strip_all_tags(get_bloginfo('name')) . '] ' . sprintf(self::t('prtPrivacyNote'), $type), sprintf(self::t('prtPrivacyNote'), $type) . "\n" . self::t('trackNo') . ': ' . count($ids));
		return array('sent' => 'owner');
	}
}
