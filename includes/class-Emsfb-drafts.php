<?php
/**
 * F11-E/F: saved answers ("save and continue later", kind resume) and partial entries (kind
 * partial). Spec: docs/feature-gaps/F11-form-limits-resume.fa.md §5, §6, "مسیرهای REST/AJAX".
 *
 * What is never stored: passwords, files and recordings, signatures, payment rows and ids, the
 * captcha, computed prices; only the form's own fields, each cleaned like a submit row.
 * The resume token is 256 random bits; only its HMAC is stored, so a database leak gives no
 * usable link. A draft of a logged-in user opens only for that user. Partial answers are stored
 * only with the visitor's consent (checkbox, or the owner's notice) and, by default, only once an
 * e-mail or mobile number is filled in. Completed drafts are emptied at once; expired ones are
 * removed by the daily job; both kinds are in WordPress' export / erase tools.
 *
 * Plan: Pro (Form_Gate::PLAN drafts / partials). Routes need a live runtime token
 * (forms/session/prepare), the wp_rest nonce and the same origin; an old cached page without
 * a token can still submit, but cannot save a draft.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Drafts {

	const TABLE = 'emsfb_draft_';

	/** Size cap of the answers of one draft (JSON bytes). */
	const MAX_BYTES = 262144;

	/** Types never kept in a draft (spec §5 "آنچه ذخیره نمی‌شود"). */
	const NOT_SAVED = array(
		'password', 'file', 'dadfile', 'esign', 'audio_recorder', 'video_recorder', 'screen_recorder', 'image', 'document', 'media', 'allformat', 'zip',
		'stripe', 'paypal', 'persiaPay', 'persiapay', 'zarinPal', 'payment', 'captcha_v2', 'prcfld', 'ttlprc', 'html', 'heading', 'link', 'step', 'steps', 'w_link',
	);

	/** Test clock (unix seconds); null = time(). */
	public static $now = null;

	public static function now() {
		return self::$now === null ? time() : (int) self::$now;
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** CREATE TABLE in the dbDelta format of Install (spec "جدول پیش‌نویس"). */
	public static function ddl($prefix, $charset_collate = '') {
		return "CREATE TABLE {$prefix}" . self::TABLE . " (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kind` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fid` int(11) NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `owner_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uid` bigint(20) unsigned NOT NULL DEFAULT 0,
  `content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `step` smallint(5) unsigned NOT NULL DEFAULT 1,
  `progress` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `contact_mask` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `completed_msg_id` int(11) NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY token (token_hash),
  KEY owner (fid,kind,owner_hash),
  KEY listing (fid,kind,status,updated_at),
  KEY expires (expires_at)
) {$charset_collate};";
	}

	private static $ready = null;
	public static function ready() {
		if (self::$ready !== null) return self::$ready;
		global $wpdb;
		$t = self::table();
		self::$ready = is_object($wpdb) && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t;
		return self::$ready;
	}
	public static function reset_ready() { self::$ready = null; }

	public static function register() {
		if (!function_exists('add_action')) return;
		add_action('rest_api_init', array(__CLASS__, 'routes'), 20);
		add_action('emsfb_daily_maintenance', array(__CLASS__, 'cleanup'), 41, 0);
		add_action('wp_ajax_get_partial_entries_Emsfb', array(__CLASS__, 'ajax_list'));
		add_action('wp_ajax_delete_partial_entries_Emsfb', array(__CLASS__, 'ajax_delete'));
		add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'register_exporter'));
		add_filter('wp_privacy_personal_data_erasers', array(__CLASS__, 'register_eraser'));
		add_action('efb_form_deleted', array(__CLASS__, 'on_form_deleted'), 10, 1);
		add_action('template_redirect', array(__CLASS__, 'resume_page_headers'), 1);
	}

	/* ------------------------------------------------------------ routes */

	public static function routes() {
		$perm = class_exists('\\Emsfb\\_Public') && _Public::$instance_efb ? array(_Public::$instance_efb, 'check_nonce_permission_efb') : '__return_false';
		foreach (array('forms/draft/save' => 'rest_save', 'forms/draft/load' => 'rest_load', 'forms/draft/email' => 'rest_email', 'forms/draft/discard' => 'rest_discard', 'forms/partial/save' => 'rest_partial') as $route => $cb) {
			register_rest_route('Emsfb/v1', $route, array('methods' => 'POST', 'callback' => array(__CLASS__, $cb), 'permission_callback' => $perm));
		}
	}

	private static function err($code, $message, $status) {
		return new \WP_Error($code, $message, array('status' => (int) $status));
	}

	private static function texts(array $keys) {
		$fb = array(
			'draftInvalid' => 'This link has expired or is not valid.',
			'draftFailed'  => 'Your answers could not be saved. Please try again.',
			'tooManyRequests' => 'Too many requests. Please try again shortly.',
			'resumeLinkSub' => 'Continue your form: %s',
			'resumeLinkBody' => 'Use this link to continue the form. It works until %s. If you did not ask for it, ignore this email.',
			'resumeLinkBtn' => 'Continue the form',
			'invalidEmail' => 'Please enter a valid email address.',
		);
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$got = ($fn && method_exists($fn, 'text_efb')) ? (array) $fn->text_efb($keys) : array();
		$out = array();
		foreach ($keys as $k) $out[$k] = isset($got[$k]) && is_string($got[$k]) && $got[$k] !== '' ? $got[$k] : (isset($fb[$k]) ? $fb[$k] : $k);
		return $out;
	}

	/** The JSON body, the stored form, the runtime proof and the gate: the common start of every route. */
	private static function start($r, $kind) {
		if (function_exists('nocache_headers')) nocache_headers();
		if (!class_exists('\\Emsfb\\Request_Health') || !Request_Health::same_origin()) return self::err('efb_origin_denied', 'Same origin required.', 403);
		if (!self::ready() || !Rate_Limit::ready()) return self::err('efb_drafts_unavailable', self::texts(array('draftFailed'))['draftFailed'], 503);
		$j = $r->get_json_params();
		if (!is_array($j)) return self::err('efb_invalid_payload', 'Invalid request.', 400);
		$fid = isset($j['form_id']) && is_scalar($j['form_id']) ? (int) $j['form_id'] : 0;
		$rows = $fid > 0 && class_exists('\\Emsfb\\Payments\\Service') ? Payments\Service::form_rows($fid) : null;
		if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) return self::err('efb_form_missing', 'Form not found.', 404);
		$cfg = Form_Gate::cfg($rows[0]);
		$part = $kind === 'partial' ? 'partials' : 'drafts';
		if (($kind === 'partial' ? !$cfg['partial_on'] : !$cfg['draft_on']) || !Form_Gate::plan_ok($part)) return self::err('efb_feature_unavailable', 'This form does not save answers.', 403);
		$proof = class_exists('\\Emsfb\\Form_Runtime') ? Form_Runtime::verify(isset($j['runtime_token']) ? (string) $j['runtime_token'] : '', $fid) : false;
		if (is_wp_error($proof)) return $proof;
		if (!$proof) return self::err('efb_context_expired', __('This form has expired. Please refresh the page and send it again.', 'easy-form-builder'), 409);
		if (in_array($rows[0]['stateForm'] ?? false, array(true, 1, '1'), true) && !is_user_logged_in()) return self::err('efb_login_required', __('Please log in to use this form.', 'easy-form-builder'), 403);
		// Role and schedule apply; the caps do not (a saved draft takes no place).
		$err = Form_Gate::check(self::access_only($rows[0]), Form_Gate::context($fid, array('t0' => isset($proof['t0']) ? (int) $proof['t0'] : null)));
		if ($err) return $err;
		return array('j' => $j, 'fid' => $fid, 'rows' => $rows, 'cfg' => $cfg, 'proof' => $proof);
	}

	/** Row 0 without the caps: role and schedule only. */
	public static function access_only(array $s0) {
		foreach (array('lim_total', 'lim_user', 'lim_ip', 'lim_cookie', 'lim_email') as $k) unset($s0[$k]);
		return $s0;
	}

	private static function ip() {
		return class_exists('\\Emsfb\\Client_Ip') ? Client_Ip::get() : (isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '');
	}

	private static function utc($offset = 0) {
		return gmdate('Y-m-d H:i:s', self::now() + (int) $offset);
	}

	private static function owner_hash($proof) {
		return Rate_Limit::hash('draft-owner', isset($proof['owner']) ? (string) $proof['owner'] : '');
	}

	public static function token_hash($token) {
		return Rate_Limit::hash('draft', (string) $token);
	}

	/**
	 * The rows a draft may keep: only the form's own fields, not the types above, each cleaned like
	 * a submit row (the submit handler's normalize_submitted_row_efb() and filter_attributes_by_type_efb()
	 * through _Public::draft_clean_row_efb()); no field hidden by logic is sent by the browser.
	 */
	public static function clean_rows($rows, array $form) {
		if (!is_array($rows)) return array();
		$types = array();
		$hidden_client = array();
		foreach ($form as $i => $f) {
			if ($i > 0 && is_array($f) && isset($f['id_'], $f['type'])) $types[(string) $f['id_']] = (string) $f['type'];
			// F08: a hidden value is kept only when the visitor is its source (a campaign parameter, the referrer);
			// one the server decides (user, IP, date, a fixed text …) is rebuilt on submit and never stored here.
			if ($i > 0 && is_array($f) && ($f['type'] ?? '') === 'hiddenField' && isset($f['id_']) && class_exists('\\Emsfb\\Smart_Tags')
				&& \Emsfb\Smart_Tags::classify(isset($f['value']) ? $f['value'] : '') === 'client') $hidden_client[(string) $f['id_']] = true;
		}
		$gw = class_exists('\\Emsfb\\Payments\\Registry') ? Payments\Registry::slugs() : array();
		$out = array();
		$pub = class_exists('\\Emsfb\\_Public') ? _Public::$instance_efb : null;
		foreach (array_slice($rows, 0, 500) as $r) {
			if (!is_array($r) || !isset($r['id_']) || !is_scalar($r['id_'])) continue;
			$id = (string) $r['id_'];
			if (!isset($types[$id])) continue;
			$type = $types[$id];
			if (in_array($type, self::NOT_SAVED, true) || in_array($type, $gw, true) || strpos($type, 'pay') === 0 && in_array($type, array('payment'), true)) continue;
			if ($type === 'hiddenField' && !isset($hidden_client[$id])) continue;
			$r['type'] = $type;
			$r['form_id'] = isset($r['form_id']) ? (int) $r['form_id'] : 0;
			if ($pub && method_exists($pub, 'draft_clean_row_efb')) {
				$c = $pub->draft_clean_row_efb($r, $type);
				if (!is_array($c)) continue;
				$r = $c;
			} else {
				$keep = array();
				foreach (array('id_', 'name', 'id_ob', 'amount', 'type', 'value', 'form_id', 'qty') as $k) if (isset($r[$k])) $keep[$k] = $r[$k];
				$r = $keep;
			}
			foreach ($r as $k => $v) {
				if (is_array($v)) { $r[$k] = array_map(function ($x) { return is_scalar($x) ? sanitize_text_field((string) $x) : ''; }, $v); }
				elseif (is_scalar($v)) $r[$k] = sanitize_text_field((string) $v);
				else unset($r[$k]);
			}
			$out[] = $r;
		}
		return $out;
	}

	/** The page to come back to: the page that sent it, on this site, with ?efb_resume=<token>. */
	private static function resume_url($url, $token) {
		$url = is_string($url) ? esc_url_raw($url) : '';
		$url = $url !== '' ? (string) wp_validate_redirect($url, home_url('/')) : home_url('/');
		$url = remove_query_arg(array('efb_resume'), $url);
		return add_query_arg('efb_resume', $token, $url);
	}

	public static function expiry_text($utc) {
		return Form_Gate::fmt_date(strtotime($utc . ' UTC'));
	}

	/* ---- save */

	public static function rest_save($r) {
		$s = self::start($r, 'resume');
		if (is_wp_error($s)) return $s;
		$ip = self::ip();
		if (!Rate_Limit::hit('dsv', $ip, 3600, (int) apply_filters('efb_draft_save_per_hour', 30)) || !Rate_Limit::hit('dsf', (string) $s['fid'], 60, 120)) {
			return self::err('rest_too_many_requests', self::texts(array('tooManyRequests'))['tooManyRequests'], 429);
		}
		$rows = self::clean_rows(isset($s['j']['rows']) ? $s['j']['rows'] : array(), $s['rows']);
		$content = Privacy::encode_content($rows);
		if (strlen($content) > self::MAX_BYTES) return self::err('efb_draft_too_large', self::texts(array('draftFailed'))['draftFailed'], 413);
		$step = isset($s['j']['step']) && is_numeric($s['j']['step']) ? max(1, min(500, (int) $s['j']['step'])) : 1;
		global $wpdb;
		$t = self::table();
		$expires = self::utc($s['cfg']['draft_days'] * 86400);
		$uid = (int) get_current_user_id();
		$given = isset($s['j']['resume_token']) && is_string($s['j']['resume_token']) && preg_match('/^[a-f0-9]{64}$/', $s['j']['resume_token']) ? $s['j']['resume_token'] : '';
		$row = null;
		if ($given !== '') {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$t}` WHERE token_hash = %s AND kind = 'resume' AND fid = %d AND status = 'active' AND expires_at > %s", self::token_hash($given), $s['fid'], self::utc()), ARRAY_A);
			if ($row && (int) $row['uid'] !== $uid) $row = null;
		}
		$token = '';
		if ($row) {
			$wpdb->update($t, array('content' => $content, 'step' => $step, 'updated_at' => self::utc(), 'expires_at' => $expires), array('id' => (int) $row['id']));
			$token = $given;
		} else {
			$token = bin2hex(random_bytes(32));
			$ok = $wpdb->insert($t, array('kind' => 'resume', 'fid' => $s['fid'], 'token_hash' => self::token_hash($token), 'owner_hash' => self::owner_hash($s['proof']), 'uid' => $uid,
				'content' => $content, 'step' => $step, 'progress' => self::progress($rows, $s['rows']), 'contact_mask' => '', 'ip_hash' => self::ip_hash($ip, $s['fid']), 'status' => 'active',
				'created_at' => self::utc(), 'updated_at' => self::utc(), 'expires_at' => $expires));
			if ($ok === false) return self::err('efb_draft_failed', self::texts(array('draftFailed'))['draftFailed'], 500);
		}
		$out = array('success' => true, 'expires_at' => gmdate('c', strtotime($expires . ' UTC')), 'expires_text' => self::expiry_text($expires), 'url' => self::resume_url(isset($s['j']['url']) ? $s['j']['url'] : '', $token), 'saved' => count($rows));
		$out['resume_token'] = $token; // the browser keeps it for this tab (sessionStorage) to update the same draft
		return new \WP_REST_Response($out, 200);
	}

	/* ---- load */

	public static function rest_load($r) {
		if (function_exists('nocache_headers')) nocache_headers();
		if (!class_exists('\\Emsfb\\Request_Health') || !Request_Health::same_origin()) return self::err('efb_origin_denied', 'Same origin required.', 403);
		if (!self::ready() || !Rate_Limit::ready()) return self::err('efb_drafts_unavailable', self::texts(array('draftFailed'))['draftFailed'], 503);
		$ip = self::ip();
		$l = self::texts(array('draftInvalid', 'tooManyRequests'));
		// Wrong tokens: 10 per 15 minutes per address, then 429 (the guess limit of a 256-bit token is belt and braces).
		$fails = Rate_Limit::peek('dlf', $ip, (string) floor(Rate_Limit::time() / 900));
		if (!Rate_Limit::hit('dld', $ip, 900, 20) || ($fails !== null && $fails >= 10)) return self::err('rest_too_many_requests', $l['tooManyRequests'], 429);
		$j = $r->get_json_params();
		$fid = is_array($j) && isset($j['form_id']) && is_scalar($j['form_id']) ? (int) $j['form_id'] : 0;
		$token = is_array($j) && isset($j['resume_token']) && is_string($j['resume_token']) ? $j['resume_token'] : '';
		$fail = function () use ($ip, $l) { Rate_Limit::hit('dlf', $ip, 900, 1000000); return self::err('efb_draft_invalid', $l['draftInvalid'], 404); };
		if ($fid <= 0 || !preg_match('/^[a-f0-9]{64}$/', $token)) return $fail();
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$t}` WHERE token_hash = %s AND kind = 'resume' AND fid = %d AND status = 'active' AND expires_at > %s", self::token_hash($token), $fid, self::utc()), ARRAY_A);
		if (!$row || ((int) $row['uid'] > 0 && (int) $row['uid'] !== (int) get_current_user_id())) return $fail();
		$form = class_exists('\\Emsfb\\Payments\\Service') ? Payments\Service::form_rows($fid) : null;
		if (!is_array($form) || !isset($form[0])) return $fail();
		$cfg = Form_Gate::cfg($form[0]);
		if (!$cfg['draft_on'] || !Form_Gate::plan_ok('drafts')) return $fail();
		$err = Form_Gate::check(self::access_only($form[0]), Form_Gate::context($fid));
		if ($err) return $err;
		$rows = Privacy::decode_content($row['content']);
		return new \WP_REST_Response(array('success' => true, 'rows' => self::clean_rows(is_array($rows) ? $rows : array(), $form), 'step' => (int) $row['step'],
			'expires_at' => gmdate('c', strtotime($row['expires_at'] . ' UTC')), 'expires_text' => self::expiry_text($row['expires_at'])), 200);
	}

	/* ---- e-mail the link */

	public static function rest_email($r) {
		$s = self::start($r, 'resume');
		if (is_wp_error($s)) return $s;
		$l = self::texts(array('draftInvalid', 'tooManyRequests', 'resumeLinkSub', 'resumeLinkBody', 'resumeLinkBtn', 'invalidEmail'));
		$email = isset($s['j']['email']) && is_string($s['j']['email']) ? sanitize_email($s['j']['email']) : '';
		if ($email === '' || !is_email($email)) return self::err('efb_email_invalid', $l['invalidEmail'], 400);
		$token = isset($s['j']['resume_token']) && is_string($s['j']['resume_token']) ? $s['j']['resume_token'] : '';
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$row = preg_match('/^[a-f0-9]{64}$/', $token) ? $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$t}` WHERE token_hash = %s AND kind = 'resume' AND fid = %d AND status = 'active' AND expires_at > %s", self::token_hash($token), $s['fid'], self::utc()), ARRAY_A) : null;
		if (!$row || (int) $row['uid'] !== (int) get_current_user_id()) return self::err('efb_draft_invalid', $l['draftInvalid'], 404);
		// Human Shield guards only its fixed routes (playbook §5.1): ask it here.
		if (apply_filters('efb_submit_bot_decision', false, array('form_id' => $s['fid'], 'route' => 'forms/draft/email', 'type' => 'draft_email'))) return self::err('efb_bot_denied', self::texts(array('draftFailed'))['draftFailed'], 403);
		$ip = self::ip();
		if (!Rate_Limit::hit('dem', (string) $row['id'], 3600, 3) || !Rate_Limit::hit('dme', strtolower($email), 86400, 5) || !Rate_Limit::hit('dmi', $ip, 3600, 10)) {
			return self::err('rest_too_many_requests', $l['tooManyRequests'], 429);
		}
		$name = isset($s['rows'][0]['formName']) && is_string($s['rows'][0]['formName']) ? str_replace('@efb@dq#', '"', $s['rows'][0]['formName']) : '';
		$url = self::resume_url(isset($s['j']['url']) ? $s['j']['url'] : '', $token);
		$until = self::expiry_text($row['expires_at']);
		$body = self::email_html($name, $url, $until, $l);
		$sub = str_replace('%s', $name, $l['resumeLinkSub']);
		$fn = get_efbFunction();
		$sent = $fn && method_exists($fn, 'send_email_state_new') ? $fn->send_email_state_new($email, $sub, $body, $fn->is_efb_pro(1), 'resumeLink', $url, 'null') : false;
		return new \WP_REST_Response(array('success' => (bool) $sent && $sent !== 'n'), 200);
	}

	/** The e-mail: the form name, the link, its end; never a field value. */
	public static function email_html($form_name, $url, $until, array $l) {
		$u = esc_url($url);
		return '<h2>' . esc_html($form_name) . '</h2>'
			. '<p>' . esc_html(str_replace('%s', $until, $l['resumeLinkBody'])) . '</p>'
			. '<p style="text-align:center;margin:24px 0"><a href="' . $u . '" style="display:inline-block;padding:12px 28px;border-radius:8px;background:#3644d2;color:#ffffff;text-decoration:none;font-weight:600">' . esc_html($l['resumeLinkBtn']) . '</a></p>'
			. '<p style="font-size:12px;color:#6b7280;word-break:break-all">' . $u . '</p>';
	}

	/* ---- discard */

	public static function rest_discard($r) {
		if (function_exists('nocache_headers')) nocache_headers();
		if (!class_exists('\\Emsfb\\Request_Health') || !Request_Health::same_origin()) return self::err('efb_origin_denied', 'Same origin required.', 403);
		if (!self::ready()) return self::err('efb_drafts_unavailable', 'Unavailable.', 503);
		$j = $r->get_json_params();
		$fid = is_array($j) && isset($j['form_id']) ? (int) $j['form_id'] : 0;
		$token = is_array($j) && isset($j['resume_token']) && is_string($j['resume_token']) ? $j['resume_token'] : '';
		if ($fid <= 0 || !preg_match('/^[a-f0-9]{64}$/', $token)) return self::err('efb_draft_invalid', self::texts(array('draftInvalid'))['draftInvalid'], 404);
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$n = $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET content = '', status = 'expired', updated_at = %s WHERE token_hash = %s AND kind = 'resume' AND fid = %d AND status = 'active' AND (uid = 0 OR uid = %d)", self::utc(), self::token_hash($token), $fid, (int) get_current_user_id()));
		return new \WP_REST_Response(array('success' => (int) $n > 0), 200);
	}

	/* ---- partial */

	/** "j***@e***.com" / "+98 *** **12". */
	public static function contact_mask($rows, array $form) {
		$types = array();
		foreach ($form as $i => $f) if ($i > 0 && is_array($f) && isset($f['id_'], $f['type'])) $types[(string) $f['id_']] = (string) $f['type'];
		foreach ((array) $rows as $r) {
			if (!is_array($r) || !isset($r['id_'], $r['value']) || !is_string($r['value'])) continue;
			$t = isset($types[(string) $r['id_']]) ? $types[(string) $r['id_']] : '';
			$v = trim($r['value']);
			if ($t === 'email' && is_email($v)) {
				list($a, $d) = explode('@', $v, 2);
				$dot = strrpos($d, '.');
				return mb_substr($a, 0, 1) . '***@' . mb_substr($d, 0, 1) . '***' . ($dot !== false ? substr($d, $dot) : '');
			}
			if (in_array($t, array('mobile', 'tel'), true)) {
				$digits = preg_replace('/\D+/', '', strtr($v, array('۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9')));
				if (strlen($digits) >= 7) return (strpos($v, '+') === 0 ? '+' : '') . substr($digits, 0, 2) . ' *** **' . substr($digits, -2);
			}
		}
		return '';
	}

	/** Share of the answerable fields that hold an answer, 0-100. */
	public static function progress($rows, array $form) {
		$fields = 0;
		foreach ($form as $i => $f) {
			if ($i > 0 && is_array($f) && isset($f['type']) && !in_array($f['type'], array_merge(self::NOT_SAVED, array('option', 'r_matrix')), true)) $fields++;
		}
		if ($fields === 0) return 0;
		$done = array();
		foreach ((array) $rows as $r) if (is_array($r) && isset($r['id_']) && isset($r['value']) && $r['value'] !== '' && $r['value'] !== array()) $done[(string) $r['id_']] = true;
		return (int) min(100, round(count($done) * 100 / $fields));
	}

	private static function ip_hash($ip, $fid) {
		if ($ip === '' || (class_exists('\\Emsfb\\Privacy') && Privacy::ip_mode((int) $fid) === 'none')) return '';
		return Rate_Limit::hash('draft-ip', $ip);
	}

	public static function rest_partial($r) {
		$s = self::start($r, 'partial');
		if (is_wp_error($s)) return $s;
		$cfg = $s['cfg'];
		if ($cfg['partial_consent'] === 'checkbox' && !in_array($s['j']['consent'] ?? '', array(1, '1', true), true)) {
			return self::err('efb_consent_required', 'Consent is required.', 403);
		}
		$ip = self::ip();
		if (!Rate_Limit::hit('psv', $ip, 3600, (int) apply_filters('efb_partial_save_per_hour', 120)) || !Rate_Limit::hit('psf', (string) $s['fid'], 60, 600)) {
			return self::err('rest_too_many_requests', self::texts(array('tooManyRequests'))['tooManyRequests'], 429);
		}
		$rows = self::clean_rows(isset($s['j']['rows']) ? $s['j']['rows'] : array(), $s['rows']);
		$mask = self::contact_mask($rows, $s['rows']);
		if ($cfg['partial_need_contact'] && $mask === '') return new \WP_REST_Response(array('success' => false, 'code' => 'efb_partial_no_contact', 'saved' => false), 200);
		$content = Privacy::encode_content($rows);
		if (strlen($content) > self::MAX_BYTES) return self::err('efb_draft_too_large', 'Too large.', 413);
		global $wpdb;
		$t = self::table();
		$owner = self::owner_hash($s['proof']);
		$step = isset($s['j']['step']) && is_numeric($s['j']['step']) ? max(1, min(500, (int) $s['j']['step'])) : 1;
		$data = array('content' => $content, 'step' => $step, 'progress' => self::progress($rows, $s['rows']), 'contact_mask' => substr($mask, 0, 64), 'updated_at' => self::utc(), 'expires_at' => self::utc($cfg['partial_days'] * 86400));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM `{$t}` WHERE fid = %d AND kind = 'partial' AND owner_hash = %s AND status = 'active' ORDER BY id DESC LIMIT 1", $s['fid'], $owner));
		if ($id > 0) {
			$wpdb->update($t, $data, array('id' => $id));
		} else {
			$ok = $wpdb->insert($t, array_merge($data, array('kind' => 'partial', 'fid' => $s['fid'], 'token_hash' => '', 'owner_hash' => $owner, 'uid' => (int) get_current_user_id(), 'ip_hash' => self::ip_hash($ip, $s['fid']), 'status' => 'active', 'created_at' => self::utc())));
			if ($ok === false) return self::err('efb_draft_failed', 'Not saved.', 500);
		}
		return new \WP_REST_Response(array('success' => true, 'saved' => true), 200);
	}

	/* ------------------------------------------------------------ completion */

	/**
	 * A submission was stored (or sent with prv_nostore): its saved answers and its partial entry
	 * are emptied and marked completed, so the owner's list holds only abandoned answers.
	 */
	public static function complete($fid, $draft_token, $owner, $msg_id) {
		if (!self::ready()) return 0;
		global $wpdb;
		$t = self::table();
		$n = 0;
		if (is_string($draft_token) && preg_match('/^[a-f0-9]{64}$/', $draft_token)) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$n += (int) $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET content = '', status = 'completed', completed_msg_id = %d, updated_at = %s WHERE token_hash = %s AND kind = 'resume' AND fid = %d", (int) $msg_id, self::utc(), self::token_hash($draft_token), (int) $fid));
		}
		if (is_string($owner) && $owner !== '') {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$n += (int) $wpdb->query($wpdb->prepare("UPDATE `{$t}` SET content = '', status = 'completed', completed_msg_id = %d, updated_at = %s WHERE owner_hash = %s AND kind = 'partial' AND fid = %d AND status = 'active'", (int) $msg_id, self::utc(), self::owner_hash(array('owner' => $owner)), (int) $fid));
		}
		return $n;
	}

	/** The daily job: expired rows deleted, completed ones emptied (at most 10 × 500 a run). */
	public static function cleanup() {
		if (!self::ready()) return 0;
		global $wpdb;
		$t = self::table();
		$done = 0;
		for ($i = 0; $i < 10; $i++) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT id FROM `{$t}` WHERE expires_at < %s OR (status <> 'active' AND updated_at < %s) LIMIT 500", self::utc(), self::utc(-7 * 86400))));
			if (!$ids) break;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, ids are integers
			$done += (int) $wpdb->query("DELETE FROM `{$t}` WHERE id IN (" . implode(',', $ids) . ')');
			if (count($ids) < 500) break;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$wpdb->query("UPDATE `{$t}` SET content = '' WHERE status = 'completed' AND content <> ''");
		return $done;
	}

	public static function on_form_deleted($fid) {
		if (!self::ready() || (int) $fid <= 0) return;
		global $wpdb;
		$wpdb->delete(self::table(), array('fid' => (int) $fid));
	}

	/** A resume link is per visitor: never page-cached, never sent on in the Referer. */
	public static function resume_page_headers() {
		if (empty($_GET['efb_resume'])) return; // phpcs:ignore WordPress.Security.NonceVerification -- read-only flag
		if (class_exists('\\Emsfb\\Cache_Compat')) Cache_Compat::bypass();
		if (!headers_sent()) header('Referrer-Policy: no-referrer');
	}

	/* ------------------------------------------------------------ admin (partial entries) */

	private static function admin_guard() {
		$fn = get_efbFunction();
		if (!check_ajax_referer('wp_rest', 'nonce', false) || !$fn->user_permission_efb_admin_dashboard()) {
			$l = $fn->text_efb(array('error403'));
			wp_send_json_success(array('success' => false, 'm' => $l['error403']), 200);
		}
	}

	/** AJAX get_partial_entries_Emsfb: the abandoned answers of one form (newest first, 500 at most). */
	public static function ajax_list() {
		self::admin_guard();
		$fid = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
		wp_send_json_success(array('success' => true, 'rows' => self::list_partials($fid)), 200);
	}

	public static function list_partials($fid, $limit = 500) {
		if (!self::ready() || (int) $fid <= 0) return array();
		global $wpdb;
		$t = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$rows = (array) $wpdb->get_results($wpdb->prepare("SELECT id, step, progress, contact_mask, content, created_at, updated_at, expires_at FROM `{$t}` WHERE fid = %d AND kind = 'partial' AND status = 'active' AND expires_at > %s ORDER BY updated_at DESC LIMIT %d", (int) $fid, self::utc(), (int) $limit), ARRAY_A);
		foreach ($rows as $i => $r) {
			$rows[$i]['id'] = (int) $r['id'];
			$rows[$i]['step'] = (int) $r['step'];
			$rows[$i]['progress'] = (int) $r['progress'];
			$rows[$i]['rows'] = Privacy::decode_content($r['content']);
			$rows[$i]['date'] = function_exists('get_date_from_gmt') ? get_date_from_gmt($r['updated_at']) : $r['updated_at'];
			unset($rows[$i]['content']);
		}
		return $rows;
	}

	/** AJAX delete_partial_entries_Emsfb: ids (JSON list or comma list). */
	public static function ajax_delete() {
		self::admin_guard();
		$raw = isset($_POST['ids']) ? sanitize_text_field(wp_unslash($_POST['ids'])) : '';
		$ids = array_values(array_filter(array_map('intval', preg_split('/[^0-9]+/', $raw)), function ($i) { return $i > 0; }));
		$fid = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
		$n = 0;
		if ($ids && $fid > 0 && self::ready()) {
			global $wpdb;
			$t = self::table();
			$in = implode(',', array_fill(0, count($ids), '%d'));
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, placeholders built above
			$n = (int) $wpdb->query($wpdb->prepare("DELETE FROM `{$t}` WHERE kind = 'partial' AND fid = %d AND id IN ({$in})", array_merge(array($fid), $ids)));
		}
		wp_send_json_success(array('success' => true, 'deleted' => $n), 200);
	}

	/* ------------------------------------------------------------ WordPress export / erase */

	public static function register_exporter($exporters) {
		$exporters['easy-form-builder-drafts'] = array('exporter_friendly_name' => __('Easy Form Builder saved and partial answers', 'easy-form-builder'), 'callback' => array(__CLASS__, 'export'));
		return $exporters;
	}

	public static function register_eraser($erasers) {
		$erasers['easy-form-builder-drafts'] = array('eraser_friendly_name' => __('Easy Form Builder saved and partial answers', 'easy-form-builder'), 'callback' => array(__CLASS__, 'erase'));
		return $erasers;
	}

	/** Drafts of a person: the address in an e-mail field of the answers (as F13 decides), or the user. */
	public static function find($email, $limit = 200) {
		if (!self::ready()) return array();
		global $wpdb;
		$t = self::table();
		$email = trim((string) $email);
		if ($email === '') return array();
		$user = function_exists('get_user_by') ? get_user_by('email', $email) : false;
		$uid = $user && isset($user->ID) ? (int) $user->ID : 0;
		$like = '%' . $wpdb->esc_like($email) . '%';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$cand = (array) $wpdb->get_results($wpdb->prepare("SELECT id, kind, fid, uid, content, step, created_at, updated_at, expires_at, status FROM `{$t}` WHERE content LIKE %s OR (uid > 0 AND uid = %d) ORDER BY id ASC LIMIT %d", $like, $uid, (int) $limit), ARRAY_A);
		$out = array();
		foreach ($cand as $c) {
			$rows = Privacy::decode_content($c['content']);
			$form = class_exists('\\Emsfb\\Payments\\Service') ? Payments\Service::form_rows((int) $c['fid']) : null;
			$f0 = is_array($form) && isset($form[0]) && is_array($form[0]) ? $form[0] : array();
			if (($uid > 0 && (int) $c['uid'] === $uid) || (is_array($rows) && Privacy::entry_matches_email($rows, $f0, $email))) {
				$c['rows'] = is_array($rows) ? $rows : array();
				$out[] = $c;
			}
		}
		return $out;
	}

	public static function export($email, $page = 1) {
		$items = array();
		if ((int) $page <= 1) {
			foreach (self::find($email) as $d) {
				$data = array(
					array('name' => __('Form', 'easy-form-builder'), 'value' => (string) $d['fid']),
					array('name' => __('Kind', 'easy-form-builder'), 'value' => $d['kind'] === 'partial' ? __('Partial answers', 'easy-form-builder') : __('Saved to continue later', 'easy-form-builder')),
					array('name' => __('Last saved', 'easy-form-builder'), 'value' => (string) $d['updated_at'] . ' UTC'),
				);
				foreach ($d['rows'] as $r) {
					if (!is_array($r)) continue;
					$name = isset($r['name']) && $r['name'] !== '' ? str_replace('@efb@dq#', '"', (string) $r['name']) : (isset($r['id_']) ? (string) $r['id_'] : '');
					$val = isset($r['value']) ? (is_array($r['value']) ? implode(', ', array_map('strval', $r['value'])) : str_replace(array('@efb@dq#', '@n#', '@efb!'), array('"', "\n", ', '), (string) $r['value'])) : '';
					$data[] = array('name' => $name, 'value' => $val);
				}
				$items[] = array('group_id' => 'efb-drafts', 'group_label' => __('Form answers saved before sending', 'easy-form-builder'), 'item_id' => 'efb-draft-' . (int) $d['id'], 'data' => $data);
			}
		}
		return array('data' => $items, 'done' => true);
	}

	public static function erase($email, $page = 1) {
		$removed = false;
		if ((int) $page <= 1 && self::ready()) {
			global $wpdb;
			$ids = array_map(function ($d) { return (int) $d['id']; }, self::find($email));
			if ($ids) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, ids are integers
				$removed = (int) $wpdb->query('DELETE FROM `' . self::table() . '` WHERE id IN (' . implode(',', $ids) . ')') > 0;
			}
			// The per-e-mail counters of F11 (lim_email) are forgotten too: "ever" and the current periods.
			$removed = self::erase_email_counters($email) > 0 || $removed;
		}
		return array('items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => true);
	}

	/** Removes the lim_email counters of an address (every form that limits by e-mail). */
	public static function erase_email_counters($email) {
		global $wpdb;
		$e = Form_Gate::normalize_email($email);
		if ($e === '' || !Rate_Limit::ready()) return 0;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$forms = (array) $wpdb->get_results("SELECT form_id, form_structer FROM `{$wpdb->prefix}emsfb_form` WHERE form_structer LIKE '%lim_email%'", ARRAY_A);
		$buckets = array();
		$now = new \DateTimeImmutable('@' . Form_Gate::now());
		foreach ($forms as $f) {
			$rows = Privacy::decode_content($f['form_structer']);
			if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) continue;
			$cfg = Form_Gate::cfg($rows[0]);
			if ($cfg['email'] <= 0 || $cfg['email_field'] === '') continue;
			foreach (Form_Gate::PERIODS as $p) {
				$slot = Form_Gate::period_slot($p, $now);
				$buckets[] = Rate_Limit::bucket('le:' . (int) $f['form_id'], $cfg['email_field'] . '|' . $e, $slot[0]);
			}
		}
		return Rate_Limit::delete_buckets($buckets);
	}

	/* ------------------------------------------------------------ markup */

	/**
	 * The "Save and continue later" button under the form's buttons (twin of
	 * efb_draft_button_html_efb() in new-efb.js, the builder preview; tests/test-f11-parity.js).
	 * Starts with a block tag (wpautop); full width on phones (form-access-efb.css).
	 */
	public static function button_html($label, $form_id, $preview = false) {
		$fid = (int) $form_id;
		$e = function ($s) { return function_exists('esc_html') ? esc_html($s) : htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
		return '<div class="efb efb-draft-zone d-flex justify-content-center mt-2 mx-3" data-formid="' . $fid . '">'
			. '<button type="button" class="efb btn btn-outline-secondary btn-sm efb-draft-save" id="efb-draft-save-' . $fid . '" data-formid="' . $fid . '"' . ($preview ? ' disabled aria-disabled="true"' : '') . '>'
			. '<i class="efb bi-bookmark me-1" aria-hidden="true"></i>' . $e($label) . '</button></div>';
	}
}
