<?php
/**
 * F11: who may send a form and when (docs/feature-gaps/F11-form-limits-resume.fa.md).
 *
 * One place decides, for every endpoint: the role of a private form (D), the open /
 * close schedule in site time (B), the total cap (B) and the per user / IP / cookie /
 * e-mail limits with a calendar period (C). The decision is made on the server at
 * the moment of the request: the page render, forms/session/prepare and every
 * submit (normal, payments/create, offline restore, AutoPilot); the lock card in
 * the HTML is only a convenience, because a form in a widget or popup may come
 * from a page cache.
 *
 * Counters live in Rate_Limit (atomic reserve / release). The total cap starts from
 * the real number of entries of the form (an entry of a failed, cancelled or
 * expired payment does not count) and is rebuilt from it after entries are deleted.
 *
 * Payment entries (a pending payment holds its place): the place is taken when the
 * entry is stored (payments/create, before the visitor goes to the provider), given
 * back when the payment fails, is cancelled or expires (and no other attempt of the
 * same entry is still open), taken again by "Try again" (refused when the form filled
 * up meanwhile) and kept when the payment is paid. A payment paid after its place was
 * given back is counted even when that goes over the cap: the money was taken.
 *
 * Every key is optional; a form without them behaves exactly as before.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Form_Gate {

	/** valj_efb[0] keys of F11 (all scalar). */
	const KEYS = array(
		'lim_total', 'lim_user', 'lim_ip', 'lim_cookie', 'lim_email', 'lim_period', 'lim_email_field', 'lim_show_left',
		'lim_full_msg', 'lim_user_msg', 'sched_open', 'sched_close', 'sched_grace', 'sched_pending_msg', 'sched_closed_msg',
		'allowed_roles', 'custom_css', 'draft_on', 'draft_days', 'partial_on', 'partial_days', 'partial_consent', 'partial_need_contact',
	);

	const PERIODS = array('ever', 'day', 'week', 'month', 'year');

	/** Stable error codes (SitePilot contract §5.7). */
	const CODES = array(
		'pending' => 'efb_form_pending',
		'closed'  => 'efb_form_closed',
		'full'    => 'efb_form_full',
		'limited' => 'efb_limit_reached',
		'role'    => 'efb_role_denied',
	);

	/** Plan of each part (spec "سطح نسخه"; the owner may change it with the filter efb_f11_plan). */
	const PLAN = array(
		'total'    => 'free',
		'schedule' => 'free',
		'roles'    => 'free',
		'css'      => 'free',
		'person'   => 'free_plus',
		'popup'    => 'free_plus',
		'drafts'   => 'pro',
		'partials' => 'pro',
	);

	/** Payment statuses that keep an entry's place. */
	const LIVE_PAYMENT = array('created', 'pending', 'authorized', 'paid', 'partially_refunded', 'refunded');

	const HOLD_OPTION = 'efb_gh_';

	/** Test clock (unix seconds); null = time(). */
	public static $now = null;

	private static $count_cache = array();

	public static function now() {
		return self::$now === null ? time() : (int) self::$now;
	}

	public static function register() {
		if (!function_exists('add_action')) return;
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 25, 2);
		add_action('efb_entries_purged', array(__CLASS__, 'on_entries_purged'), 10, 4);
		add_action('efb_form_deleted', array(__CLASS__, 'on_form_deleted'), 10, 1);
		add_action('efb_payment_status_changed', array(__CLASS__, 'on_payment_status'), 10, 4);
		add_filter('efb_payment_retry_gate', array(__CLASS__, 'retry_gate'), 10, 2);
		add_action('emsfb_daily_maintenance', array(__CLASS__, 'daily'), 40, 0);
		add_action('wp_ajax_efb_limits_reset_Emsfb', array(__CLASS__, 'ajax_reset'));
		add_action('rest_api_init', array(__CLASS__, 'rest_routes'));
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 20, 2);
	}

	/* ------------------------------------------------------------ settings */

	private static function digits($v) {
		$v = is_scalar($v) ? (string) $v : '';
		$v = strtr($v, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		));
		return preg_replace('/[^0-9]/', '', $v);
	}

	/** A whole number in range: Persian / Arabic digits, spaces and thousands separators allowed, nothing else ('-5', '1e3' are not numbers). */
	private static function int_in($v, $min, $max) {
		if (!is_scalar($v) || is_bool($v)) return null;
		$s = preg_replace('/[\s,\x{066C}\x{060C}]+/u', '', (string) $v);
		$s = strtr((string) $s, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		));
		if (!preg_match('/^\d{1,9}$/', $s)) return null;
		$d = $s;
		$n = (int) $d;
		return ($n < $min || $n > $max) ? null : $n;
	}

	private static function on($v) {
		return in_array($v, array(true, 1, '1', 'true', 'on'), true);
	}

	/** Row 0 as an array (stdClass from the renderer, array elsewhere). */
	public static function row0($s0) {
		if (is_object($s0)) $s0 = get_object_vars($s0);
		return is_array($s0) ? $s0 : array();
	}

	/**
	 * Server rules of one F11 key (sanitize_obj_msg_efb(), row 0). '' = not set.
	 * Unknown keys return null (not ours).
	 */
	public static function sanitize_key($k, $v) {
		switch ($k) {
			case 'lim_total':
				$n = self::int_in($v, 1, 10000000);
				return $n === null ? '' : (string) $n;
			case 'lim_user':
			case 'lim_ip':
			case 'lim_cookie':
			case 'lim_email':
				$n = self::int_in($v, 1, 100000);
				return $n === null ? '' : (string) $n;
			case 'lim_period':
				return is_string($v) && in_array($v, self::PERIODS, true) ? $v : '';
			case 'lim_email_field':
				return is_string($v) && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $v) ? $v : '';
			case 'lim_show_left':
			case 'draft_on':
			case 'partial_on':
				return self::on($v) ? '1' : '';
			case 'partial_need_contact':
				return self::on($v) ? '1' : '0';
			case 'lim_full_msg':
			case 'lim_user_msg':
			case 'sched_pending_msg':
			case 'sched_closed_msg':
				$t = is_scalar($v) ? (function_exists('sanitize_text_field') ? sanitize_text_field((string) $v) : trim(strip_tags((string) $v))) : '';
				return function_exists('mb_substr') ? mb_substr($t, 0, 300) : substr($t, 0, 300);
			case 'sched_open':
			case 'sched_close':
				$v = is_string($v) ? strtr(trim($v), array(' ' => 'T')) : '';
				$v = preg_replace_callback('/[۰-۹٠-٩]/u', function ($m) { return self::digits($m[0]); }, $v);
				if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v)) return '';
				$d = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $v, new \DateTimeZone('UTC'));
				return ($d && $d->format('Y-m-d\TH:i') === $v) ? $v : '';
			case 'sched_grace':
				$n = self::int_in($v, 0, 60);
				return $n === null ? '' : (string) $n;
			case 'draft_days':
				$n = self::int_in($v, 1, 90);
				return $n === null ? '' : (string) $n;
			case 'partial_days':
				$n = self::int_in($v, 1, 365);
				return $n === null ? '' : (string) $n;
			case 'partial_consent':
				return in_array($v, array('checkbox', 'notice'), true) ? $v : 'checkbox';
			case 'allowed_roles':
				return self::sanitize_roles($v);
			case 'custom_css':
				return Form_Css::sanitize_stored($v);
		}
		return null;
	}

	/** Comma list of existing role slugs. */
	public static function sanitize_roles($v) {
		if (!is_string($v) || $v === '') return '';
		$known = self::role_names();
		$out = array();
		foreach (explode(',', $v) as $r) {
			$r = function_exists('sanitize_key') ? sanitize_key(trim($r)) : strtolower(preg_replace('/[^a-z0-9_\-]/i', '', trim($r)));
			if ($r !== '' && isset($known[$r]) && !in_array($r, $out, true)) $out[] = $r;
		}
		return implode(',', $out);
	}

	/** slug => name of the site's roles. */
	public static function role_names() {
		if (function_exists('wp_roles')) {
			$r = wp_roles();
			if ($r && method_exists($r, 'get_names')) return (array) $r->get_names();
		}
		return isset($GLOBALS['efb_test_roles']) ? $GLOBALS['efb_test_roles'] : array();
	}

	/** What the form asks for, normalized; absent keys = today's behaviour. */
	public static function cfg($s0) {
		$s = self::row0($s0);
		$g = function ($k) use ($s) { return isset($s[$k]) && is_scalar($s[$k]) ? (string) $s[$k] : ''; };
		$num = function ($k, $min, $max) use ($g) { $n = self::int_in($g($k), $min, $max); return $n === null ? 0 : $n; };
		$period = $g('lim_period');
		$roles = self::sanitize_roles_lenient($g('allowed_roles'));
		return array(
			'members_only' => self::on($s['stateForm'] ?? false),
			'type'       => $g('type'),
			'total'      => $num('lim_total', 1, 10000000),
			'user'       => $num('lim_user', 1, 100000),
			'ip'         => $num('lim_ip', 1, 100000),
			'cookie'     => $num('lim_cookie', 1, 100000),
			'email'      => $num('lim_email', 1, 100000),
			'period'     => in_array($period, self::PERIODS, true) ? $period : 'ever',
			'email_field'=> $g('lim_email_field'),
			'show_left'  => self::on($g('lim_show_left')),
			'full_msg'   => $g('lim_full_msg'),
			'user_msg'   => $g('lim_user_msg'),
			'pending_msg'=> $g('sched_pending_msg'),
			'closed_msg' => $g('sched_closed_msg'),
			'open'       => self::parse_local($g('sched_open')),
			'close'      => self::parse_local($g('sched_close')),
			'grace'      => $num('sched_grace', 0, 60),
			'roles'      => $roles,
			'draft_on'   => self::on($g('draft_on')),
			'draft_days' => $num('draft_days', 1, 90) ?: 30,
			'partial_on' => self::on($g('partial_on')),
			'partial_days' => $num('partial_days', 1, 365) ?: 30,
			'partial_consent' => $g('partial_consent') === 'notice' ? 'notice' : 'checkbox',
			'partial_need_contact' => $g('partial_need_contact') !== '0',
		);
	}

	private static function sanitize_roles_lenient($v) {
		$out = array();
		foreach (explode(',', (string) $v) as $r) {
			$r = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', trim($r)));
			if ($r !== '') $out[] = $r;
		}
		return array_values(array_unique($out));
	}

	/** Whether the plan of this site includes a part (self::PLAN). */
	public static function plan_ok($part) {
		$need = isset(self::PLAN[$part]) ? self::PLAN[$part] : 'pro';
		if (function_exists('apply_filters')) $need = apply_filters('efb_f11_plan', $need, $part);
		if ($need === 'free') return true;
		if (function_exists('efb_plan_at_least_efb')) return (bool) efb_plan_at_least_efb($need);
		if (class_exists('\\Emsfb\\Builder_Api')) return Builder_Api::plan_at_least($need);
		return false;
	}

	/** The dimensions that count per person on this site (plan): user / ip / cookie / email. */
	public static function person_dims(array $cfg) {
		if (!self::plan_ok('person')) return array();
		$out = array();
		foreach (array('user', 'ip', 'cookie', 'email') as $d) if ($cfg[$d] > 0) $out[] = $d;
		if (in_array('email', $out, true) && $cfg['email_field'] === '') $out = array_values(array_diff($out, array('email')));
		return $out;
	}

	/** Whether row 0 holds anything this class enforces. */
	public static function has_rules(array $cfg) {
		return $cfg['total'] > 0 || $cfg['open'] !== null || $cfg['close'] !== null || ($cfg['members_only'] && $cfg['roles']) || self::person_dims($cfg);
	}

	/* ------------------------------------------------------------ time */

	public static function tz() {
		if (function_exists('wp_timezone')) return wp_timezone();
		return new \DateTimeZone(isset($GLOBALS['efb_test_tz']) ? $GLOBALS['efb_test_tz'] : 'UTC');
	}

	/** 'YYYY-MM-DDTHH:MM' in site time → unix seconds, or null. */
	public static function parse_local($v) {
		if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v)) return null;
		$d = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $v, self::tz());
		return $d ? $d->getTimestamp() : null;
	}

	/**
	 * The calendar period that contains $now, in site time (spec §3): slot id, end (UTC
	 * 'Y-m-d H:i:s' + 60 s, the counter's window_end) and the unix time it resets.
	 * week starts on get_option('start_of_week').
	 *
	 * @return array{0:string,1:string,2:int|null}
	 */
	public static function period_slot($period, \DateTimeImmutable $now) {
		if ($period === 'ever' || !in_array($period, self::PERIODS, true)) return array('0', Rate_Limit::NEVER, null);
		$local = $now->setTimezone(self::tz());
		switch ($period) {
			case 'day':
				$start = $local->setTime(0, 0);
				$end = $start->modify('+1 day');
				$slot = 'd' . $start->format('Ymd');
				break;
			case 'week':
				$sow = function_exists('get_option') ? (int) get_option('start_of_week', 1) : (isset($GLOBALS['efb_test_sow']) ? (int) $GLOBALS['efb_test_sow'] : 1);
				$back = ((int) $local->format('w') - $sow + 7) % 7;
				$start = $local->setTime(0, 0)->modify('-' . $back . ' days');
				$end = $start->modify('+7 days');
				$slot = 'w' . $start->format('Ymd');
				break;
			case 'month':
				$start = $local->setDate((int) $local->format('Y'), (int) $local->format('n'), 1)->setTime(0, 0);
				$end = $start->modify('+1 month');
				$slot = 'm' . $start->format('Ym');
				break;
			default:
				$start = $local->setDate((int) $local->format('Y'), 1, 1)->setTime(0, 0);
				$end = $start->modify('+1 year');
				$slot = 'y' . $start->format('Y');
		}
		$ts = $end->getTimestamp();
		return array($slot, gmdate('Y-m-d H:i:s', $ts + 60), $ts);
	}

	/** Date and time in the site's format and language, with the language's digits. */
	public static function fmt_date($ts) {
		if ($ts === null) return '';
		if (function_exists('wp_date') && function_exists('get_option')) {
			$s = wp_date(trim(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i')), (int) $ts);
		} else {
			$s = (new \DateTimeImmutable('@' . (int) $ts))->setTimezone(self::tz())->format('Y-m-d H:i');
		}
		return self::locale_digits((string) $s);
	}

	/** Persian or Arabic-Indic digits for those languages (spec: a11y, RTL, digits). */
	public static function locale_digits($s, $locale = null) {
		$locale = $locale !== null ? (string) $locale : (function_exists('determine_locale') ? determine_locale() : (function_exists('get_locale') ? get_locale() : 'en_US'));
		$lang = strtolower(strtok($locale, '_'));
		$base = null;
		if (in_array($lang, array('fa', 'ps', 'azb', 'haz'), true)) $base = 0x06F0;
		elseif (in_array($lang, array('ar', 'ary', 'arq', 'ckb', 'ur'), true)) $base = 0x0660;
		if ($base === null) return (string) $s;
		return preg_replace_callback('/[0-9]/', function ($m) use ($base) { return html_entity_decode('&#' . ($base + (int) $m[0]) . ';', ENT_QUOTES, 'UTF-8'); }, (string) $s);
	}

	public static function fmt_number($n) {
		$s = function_exists('number_format_i18n') ? number_format_i18n((int) $n) : (string) (int) $n;
		return self::locale_digits($s);
	}

	/* ------------------------------------------------------------ counts */

	private static function payments_ready() {
		global $wpdb;
		static $ready = null;
		if ($ready !== null) return $ready;
		$t = $wpdb->prefix . 'emsfb_pmt_';
		$ready = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t;
		return $ready;
	}

	/**
	 * Real number of entries of a form, the start of its total counter. An entry whose payment
	 * failed, was cancelled or expired (and has no live attempt) does not count.
	 */
	public static function entry_count($fid) {
		global $wpdb;
		$fid = (int) $fid;
		$m = $wpdb->prefix . 'emsfb_msg_';
		if (self::payments_ready()) {
			$p = $wpdb->prefix . 'emsfb_pmt_';
			$live = "'" . implode("','", self::LIVE_PAYMENT) . "'";
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tables from $wpdb->prefix, literal status list
			$sql = $wpdb->prepare("SELECT COUNT(*) FROM `{$m}` m WHERE m.form_id = %d AND NOT (EXISTS (SELECT 1 FROM `{$p}` p WHERE p.msg_id = m.msg_id) AND NOT EXISTS (SELECT 1 FROM `{$p}` q WHERE q.msg_id = m.msg_id AND q.status IN ({$live})))", $fid);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
			$sql = $wpdb->prepare("SELECT COUNT(*) FROM `{$m}` WHERE form_id = %d", $fid);
		}
		return (int) $wpdb->get_var($sql);
	}

	public static function total_bucket($fid) {
		return Rate_Limit::bucket('lt:' . (int) $fid, '', '0');
	}

	/** Places used: the counter, or the entries when there is no counter yet. */
	public static function total_used($fid) {
		$fid = (int) $fid;
		$v = Rate_Limit::peek_bucket(self::total_bucket($fid));
		if ($v !== null) return $v;
		if (!array_key_exists($fid, self::$count_cache)) self::$count_cache[$fid] = self::entry_count($fid);
		return self::$count_cache[$fid];
	}

	public static function flush_cache() {
		self::$count_cache = array();
	}

	/* ------------------------------------------------------------ context */

	/** The visitor of this request. $extra overrides (t0, cookie, tests). */
	public static function context($fid, array $extra = array()) {
		$uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
		$roles = array();
		if ($uid > 0 && function_exists('wp_get_current_user')) {
			$u = wp_get_current_user();
			$roles = $u && isset($u->roles) ? (array) $u->roles : array();
		}
		$cookie_name = 'efb_lim_' . (int) $fid;
		$cookie = isset($_COOKIE[$cookie_name]) && is_string($_COOKIE[$cookie_name]) && preg_match('/^[a-f0-9]{32}$/', $_COOKIE[$cookie_name]) ? $_COOKIE[$cookie_name] : '';
		$ctx = array(
			'fid'       => (int) $fid,
			'now'       => self::now(),
			'uid'       => $uid,
			'roles'     => $roles,
			'logged_in' => $uid > 0,
			'manager'   => $uid > 0 && function_exists('current_user_can') && (current_user_can('manage_options') || (function_exists('is_super_admin') && is_super_admin())),
			'ip_key'    => null, // lazily: Client_Ip::limit_key()
			'cookie'    => $cookie,
			't0'        => null,
		);
		return array_merge($ctx, $extra);
	}

	private static function ip_key(array &$ctx) {
		if ($ctx['ip_key'] === null) $ctx['ip_key'] = Client_Ip::limit_key($ctx['fid']);
		return (string) $ctx['ip_key'];
	}

	/** The key of one per-person dimension, '' when it cannot apply to this visitor. */
	private static function dim_key($dim, array &$ctx, array $cfg, $items = null) {
		switch ($dim) {
			case 'user': return $ctx['uid'] > 0 ? (string) $ctx['uid'] : '';
			case 'ip': return self::ip_key($ctx);
			case 'cookie': return (string) $ctx['cookie'];
			case 'email':
				if ($items === null) return '';
				$e = self::email_of($items, $cfg['email_field']);
				return $e === '' ? '' : $cfg['email_field'] . '|' . $e;
		}
		return '';
	}

	/** The submitted (normalized) e-mail of a field, lower case; '' when absent (hidden by logic, empty). */
	public static function email_of($items, $field_id) {
		if (!is_array($items) || $field_id === '') return '';
		foreach ($items as $it) {
			if (!is_array($it) || !isset($it['id_'], $it['value']) || (string) $it['id_'] !== (string) $field_id || !is_string($it['value'])) continue;
			return self::normalize_email($it['value']);
		}
		return '';
	}

	/** Spaces and invisible characters out, lower case. Dots and +tags are kept (open question 3). */
	public static function normalize_email($v) {
		$v = preg_replace('/[\s\x{200B}-\x{200D}\x{2060}\x{FEFF}]+/u', '', (string) $v);
		$v = function_exists('mb_strtolower') ? mb_strtolower($v, 'UTF-8') : strtolower($v);
		return (strpos($v, '@') === false || strlen($v) > 254) ? '' : $v;
	}

	/* ------------------------------------------------------------ decisions */

	/**
	 * The state of a form for a visitor: open | pending | closed | full | limited | role.
	 * Order: role, schedule (with sched_grace for a session prepared before the close),
	 * total cap, per user / IP / cookie (e-mail is unknown before the submit).
	 *
	 * @return array{state:string,code:string,next:?int,left:?int,closes_at:?int,opens_at:?int,until:?int,used:?int}
	 */
	public static function state($s0, array $ctx) {
		$cfg = self::cfg($s0);
		$now = isset($ctx['now']) ? (int) $ctx['now'] : self::now();
		$out = array('state' => 'open', 'code' => '', 'next' => null, 'left' => null, 'closes_at' => $cfg['close'], 'opens_at' => $cfg['open'], 'until' => null, 'used' => null);
		if ($cfg['members_only'] && $cfg['roles'] && !empty($ctx['logged_in']) && empty($ctx['manager']) && !array_intersect($cfg['roles'], (array) $ctx['roles'])) {
			return self::with_state($out, 'role');
		}
		if ($cfg['open'] !== null && $now < $cfg['open']) {
			$out['next'] = $cfg['open'];
			return self::with_state($out, 'pending');
		}
		if ($cfg['close'] !== null && $now >= $cfg['close']) {
			$t0 = isset($ctx['t0']) ? $ctx['t0'] : null;
			$in_grace = $cfg['grace'] > 0 && $t0 !== null && (int) $t0 < $cfg['close'] && $now < $cfg['close'] + $cfg['grace'] * 60;
			if (!$in_grace) return self::with_state($out, 'closed');
		}
		if ($cfg['close'] !== null) $out['next'] = $cfg['close'];
		if ($cfg['total'] > 0) {
			$used = self::total_used($ctx['fid']);
			$out['used'] = $used;
			$out['left'] = max(0, $cfg['total'] - $used);
			if ($used >= $cfg['total']) return self::with_state($out, 'full');
		}
		$slot = self::period_slot($cfg['period'], (new \DateTimeImmutable('@' . $now)));
		foreach (self::person_dims($cfg) as $dim) {
			if ($dim === 'email') continue;
			$key = self::dim_key($dim, $ctx, $cfg);
			if ($key === '') continue;
			$hits = Rate_Limit::peek(self::scope($dim, $ctx['fid']), $key, $slot[0]);
			if ($hits !== null && $hits >= $cfg[$dim]) {
				$out['until'] = $slot[2];
				$out['next'] = $slot[2];
				return self::with_state($out, 'limited');
			}
		}
		return $out;
	}

	private static function with_state(array $out, $state) {
		$out['state'] = $state;
		$out['code'] = self::CODES[$state];
		return $out;
	}

	public static function scope($dim, $fid) {
		$p = array('total' => 'lt', 'user' => 'lu', 'ip' => 'li', 'cookie' => 'lc', 'email' => 'le');
		return $p[$dim] . ':' . (int) $fid;
	}

	/** null when the visitor may go on, else the refusal (403, with reopen_at when known). */
	public static function check($s0, array $ctx) {
		$st = self::state($s0, $ctx);
		if ($st['state'] === 'open') return null;
		return self::error($st, self::cfg($s0));
	}

	public static function error(array $st, array $cfg) {
		$data = array('status' => 403, 'state' => $st['state']);
		$re = $st['state'] === 'pending' ? $st['opens_at'] : ($st['state'] === 'limited' ? $st['until'] : null);
		if ($re) $data['reopen_at'] = (int) $re;
		return new \WP_Error($st['code'], self::message($st, $cfg), $data);
	}

	/** Phrases with English fallbacks (the owner's custom texts included). */
	public static function texts(array $keys) {
		$defaults = array(
			'limFullM'         => 'This form has reached its limit and no longer accepts answers.',
			'limLeft'          => '%s places left',
			'limReachedM'      => 'You have already sent this form.',
			'limReachedUntilM' => 'You can send this form again after %s.',
			'schedPendingM'    => 'This form opens on %s.',
			'schedClosedM'     => 'This form closed on %s and no longer accepts answers.',
			'roleDeniedM'      => 'Your account does not have access to this form.',
		);
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$got = ($fn && method_exists($fn, 'text_efb')) ? (array) $fn->text_efb($keys) : array();
		$out = array();
		foreach ($keys as $k) $out[$k] = isset($got[$k]) && is_string($got[$k]) && $got[$k] !== '' ? $got[$k] : (isset($defaults[$k]) ? $defaults[$k] : $k);
		return $out;
	}

	/** The visitor's sentence for a state (the form's own text first). Plain text, not escaped. */
	public static function message(array $st, array $cfg) {
		$l = self::texts(array('limFullM', 'limReachedM', 'limReachedUntilM', 'schedPendingM', 'schedClosedM', 'roleDeniedM'));
		// %s = the date; any other % in the owner's own text stays as typed.
		$fill = function ($text, $date) { return str_replace('%s', $date, (string) $text); };
		switch ($st['state']) {
			case 'pending':
				return $fill($cfg['pending_msg'] !== '' ? $cfg['pending_msg'] : $l['schedPendingM'], self::fmt_date($st['opens_at']));
			case 'closed':
				return $fill($cfg['closed_msg'] !== '' ? $cfg['closed_msg'] : $l['schedClosedM'], self::fmt_date($cfg['close']));
			case 'full':
				return $cfg['full_msg'] !== '' ? $cfg['full_msg'] : $l['limFullM'];
			case 'limited':
				if ($cfg['user_msg'] !== '') return $fill($cfg['user_msg'], self::fmt_date($st['until']));
				return $st['until'] ? $fill($l['limReachedUntilM'], self::fmt_date($st['until'])) : $l['limReachedM'];
			case 'role':
				return $l['roleDeniedM'];
		}
		return '';
	}

	/** The gate block of forms/session/prepare: {state, closes_at, left?}. */
	public static function public_status($s0, array $ctx) {
		$cfg = self::cfg($s0);
		$st = self::state($s0, $ctx);
		$g = array('state' => $st['state'], 'closes_at' => $st['closes_at']);
		if ($cfg['show_left'] && $cfg['total'] > 0 && $st['left'] !== null) $g['left'] = (int) $st['left'];
		return $g;
	}

	/* ------------------------------------------------------------ submit */

	/** Days after which a per-person counter is forgotten: the form's (or the site's) retention, 0 = never. */
	public static function retention_days($fid) {
		if (!class_exists('\\Emsfb\\Privacy')) return 0;
		$e = Privacy::form_entry((int) $fid);
		if (is_array($e) && isset($e['days']) && $e['days'] !== '') return (int) $e['days'];
		return (int) Privacy::setting('prv_days');
	}

	/**
	 * Takes the places this submission needs, atomically: the total, then each per-person
	 * dimension. All or nothing. Returns ['claims' => list, 'cookie' => new cookie id or ''],
	 * or a WP_Error (efb_form_full / efb_limit_reached) with nothing taken.
	 */
	public static function reserve_for_submit($s0, $items, $fid, array $ctx) {
		$cfg = self::cfg($s0);
		$fid = (int) $fid;
		$out = array('claims' => array(), 'cookie' => '');
		$dims = self::person_dims($cfg);
		if ($cfg['total'] <= 0 && !$dims) return $out;
		if (!Rate_Limit::ready()) {
			// Fail closed: a cap that cannot be counted is not an open door.
			return new \WP_Error('efb_form_full', self::message(array('state' => 'full'), $cfg), array('status' => 503));
		}
		$claims = array();
		if ($cfg['total'] > 0) {
			$b = self::total_bucket($fid);
			$seed = Rate_Limit::peek_bucket($b) === null ? self::entry_count($fid) : 0;
			if (!Rate_Limit::reserve_bucket($b, 'lt:' . $fid, Rate_Limit::NEVER, $cfg['total'], $seed)) {
				return new \WP_Error('efb_form_full', self::message(array('state' => 'full'), $cfg), array('status' => 403, 'state' => 'full'));
			}
			$claims[] = array('b' => $b, 's' => 'lt:' . $fid, 'e' => Rate_Limit::NEVER, 'l' => $cfg['total']);
			self::flush_cache();
		}
		$now = isset($ctx['now']) ? (int) $ctx['now'] : self::now();
		$slot = self::period_slot($cfg['period'], new \DateTimeImmutable('@' . $now));
		$end = $slot[1];
		$keep = self::retention_days($fid);
		if ($keep > 0) {
			$cap = gmdate('Y-m-d H:i:s', $now + $keep * 86400);
			if (strcmp($cap, $end) < 0) $end = $cap;
		}
		foreach ($dims as $dim) {
			if ($dim === 'cookie' && $ctx['cookie'] === '') {
				$ctx['cookie'] = bin2hex(random_bytes(16));
				$out['cookie'] = $ctx['cookie'];
			}
			$key = self::dim_key($dim, $ctx, $cfg, $items);
			if ($key === '') continue;
			$scope = self::scope($dim, $fid);
			$b = Rate_Limit::bucket($scope, $key, $slot[0]);
			if (!Rate_Limit::reserve_bucket($b, $scope, $end, $cfg[$dim], 0)) {
				self::release($claims);
				$st = array('state' => 'limited', 'until' => $slot[2]);
				return new \WP_Error('efb_limit_reached', self::message($st, $cfg), array('status' => 403, 'state' => 'limited', 'reopen_at' => $slot[2], 'dim' => $dim));
			}
			$claims[] = array('b' => $b, 's' => $scope, 'e' => $end, 'l' => $cfg[$dim]);
		}
		$out['claims'] = $claims;
		return $out;
	}

	/** Gives the places of a refused or failed submission back. */
	public static function release(array $claims) {
		foreach ($claims as $c) {
			if (is_array($c) && isset($c['b'])) Rate_Limit::release_bucket($c['b']);
		}
		self::flush_cache();
	}

	/** The functional cookie of a cookie limit (no personal data: a random id). */
	public static function set_cookie($fid, $id, $period = 'ever') {
		if ($id === '' || headers_sent() || !function_exists('setcookie')) return false;
		$days = $period === 'ever' ? 365 : ($period === 'year' ? 366 : 40);
		return setcookie('efb_lim_' . (int) $fid, $id, array(
			'expires'  => time() + $days * 86400,
			'path'     => defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => function_exists('is_ssl') ? is_ssl() : false,
			'httponly' => true,
			'samesite' => 'Lax',
		));
	}

	/* ------------------------------------------------------------ payments */

	/** A pending-payment entry keeps its claims until the payment ends (see the class comment). */
	public static function hold_save($msg_id, $fid, array $claims) {
		if ((int) $msg_id <= 0 || !$claims || !function_exists('update_option')) return;
		update_option(self::HOLD_OPTION . (int) $msg_id, array('f' => (int) $fid, 'c' => array_values($claims), 'r' => 0, 't' => self::now()), false);
	}

	public static function hold_get($msg_id) {
		$h = function_exists('get_option') ? get_option(self::HOLD_OPTION . (int) $msg_id, null) : null;
		return is_array($h) && isset($h['c']) && is_array($h['c']) ? $h : null;
	}

	private static function hold_put($msg_id, array $h) {
		update_option(self::HOLD_OPTION . (int) $msg_id, $h, false);
	}

	public static function hold_delete($msg_id) {
		if (function_exists('delete_option')) delete_option(self::HOLD_OPTION . (int) $msg_id);
	}

	/** Attempts of the entry other than $except that still hold the place. */
	public static function live_attempts($msg_id, $except = 0) {
		global $wpdb;
		if (!self::payments_ready()) return 0;
		$t = $wpdb->prefix . 'emsfb_pmt_';
		$live = "'" . implode("','", self::LIVE_PAYMENT) . "'";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix, literal status list
		return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$t}` WHERE msg_id = %d AND id <> %d AND status IN ({$live})", (int) $msg_id, (int) $except));
	}

	/** efb_payment_status_changed: give the place back on failure, take it again on a late payment. */
	public static function on_payment_status($p, $from = '', $to = '', $source = '') {
		if (!is_array($p) || empty($p['msg_id'])) return;
		$msg = (int) $p['msg_id'];
		$h = self::hold_get($msg);
		$fid = isset($p['form_id']) ? (int) $p['form_id'] : 0;
		if (in_array($to, array('failed', 'canceled', 'expired'), true)) {
			if (self::live_attempts($msg, (int) $p['id']) > 0) return;
			if ($h && empty($h['r'])) {
				self::release($h['c']);
				$h['r'] = 1;
				self::hold_put($msg, $h);
			} elseif (!$h && $fid > 0) {
				// No record of the claims (an entry stored before F11): rebuild the total from the entries.
				Rate_Limit::reset_scopes(array('lt:' . $fid));
			}
			return;
		}
		if (in_array($to, array('paid', 'authorized'), true)) {
			if ($h && !empty($h['r'])) {
				foreach ($h['c'] as $c) Rate_Limit::force_bucket($c['b'], $c['s'], $c['e']);
				$h['r'] = 0;
			}
			if ($to === 'paid') self::hold_delete($msg); elseif ($h) self::hold_put($msg, $h);
		}
	}

	/**
	 * efb_payment_retry_gate: "Try again" on an entry whose place was given back takes it again;
	 * refused when the form filled up or closed meanwhile. null = go on.
	 */
	public static function retry_gate($r, $prev) {
		if ($r !== null || !is_array($prev) || empty($prev['msg_id'])) return $r;
		$fid = (int) $prev['form_id'];
		$rows = class_exists('\\Emsfb\\Payments\\Service') ? \Emsfb\Payments\Service::form_rows($fid) : null;
		if (!is_array($rows) || !isset($rows[0])) return $r;
		$cfg = self::cfg($rows[0]);
		$created = isset($prev['created_at']) ? strtotime($prev['created_at'] . ' UTC') : null;
		$st = self::state($rows[0], self::context($fid, array('t0' => $created, 'uid' => 0, 'logged_in' => false, 'cookie' => '', 'ip_key' => '')));
		if (in_array($st['state'], array('closed', 'pending'), true)) return array('code' => $st['code'], 'm' => self::message($st, $cfg));
		$h = self::hold_get((int) $prev['msg_id']);
		if (!$h || empty($h['r'])) return $r;
		$taken = array();
		foreach ($h['c'] as $c) {
			if (!Rate_Limit::reserve_bucket($c['b'], $c['s'], $c['e'], (int) $c['l'], 0)) {
				self::release($taken);
				$state = strpos($c['s'], 'lt:') === 0 ? 'full' : 'limited';
				return array('code' => self::CODES[$state], 'm' => self::message(array('state' => $state, 'until' => null), $cfg));
			}
			$taken[] = $c;
		}
		$h['r'] = 0;
		self::hold_put((int) $prev['msg_id'], $h);
		return $r;
	}

	/* ------------------------------------------------------------ admin */

	/** efb_entries_purged: a deleted entry frees its place (the total is rebuilt from the entries). */
	public static function on_entries_purged($ids, $mode = 'delete', $out = array(), $form_ids = array()) {
		$scopes = array();
		foreach ((array) $form_ids as $f) if ((int) $f > 0) $scopes[] = 'lt:' . (int) $f;
		if ($scopes) Rate_Limit::reset_scopes($scopes);
		foreach ((array) $ids as $id) self::hold_delete((int) $id);
		self::flush_cache();
	}

	public static function on_form_deleted($fid) {
		$fid = (int) $fid;
		if ($fid <= 0) return;
		Rate_Limit::reset_scopes(array('lt:' . $fid, 'lu:' . $fid, 'li:' . $fid, 'lc:' . $fid, 'le:' . $fid));
	}

	/** The daily job: counters whose period ended. */
	public static function daily() {
		return Rate_Limit::cleanup();
	}

	/** AJAX efb_limits_reset_Emsfb: the per-person counters of one form back to zero (builder button). */
	public static function ajax_reset() {
		$fn = get_efbFunction();
		$lang = $fn->text_efb(array('error403', 'limResetDone', 'somethingWentWrongPleaseRefresh'));
		if (!check_ajax_referer('wp_rest', 'nonce', false) || !$fn->user_permission_efb_admin_dashboard()) {
			wp_send_json_success(array('success' => false, 'm' => $lang['error403']), 200);
		}
		$fid = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
		if ($fid <= 0) wp_send_json_success(array('success' => false, 'm' => $lang['somethingWentWrongPleaseRefresh']), 200);
		$n = Rate_Limit::reset_scopes(array('lu:' . $fid, 'li:' . $fid, 'lc:' . $fid, 'le:' . $fid));
		wp_send_json_success(array('success' => true, 'removed' => (int) $n, 'm' => isset($lang['limResetDone']) ? $lang['limResetDone'] : 'Done'), 200);
	}

	/** GET Emsfb/v1/forms/gate?form_id=N: read-only status for the builder and AutoPilot. */
	public static function rest_routes() {
		register_rest_route('Emsfb/v1', 'forms/gate', array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'rest_status'),
			'permission_callback' => array(__CLASS__, 'rest_can'),
		));
	}

	public static function rest_can() {
		return function_exists('current_user_can') && (current_user_can('manage_options') || current_user_can('Emsfb') || current_user_can('Emsfb_create'));
	}

	public static function rest_status($r) {
		$fid = (int) $r->get_param('form_id');
		$rows = $fid > 0 && class_exists('\\Emsfb\\Payments\\Service') ? \Emsfb\Payments\Service::form_rows($fid) : null;
		if (!is_array($rows) || !isset($rows[0])) return new \WP_REST_Response(array('code' => 'form_not_found', 'message' => 'Form not found.'), 404);
		return new \WP_REST_Response(self::status($rows[0], $fid), 200);
	}

	/**
	 * The form's gate as AutoPilot reads it (never a visitor's own counters):
	 * {form_id, state, code, message, opens_at, closes_at, total, person, roles, inactive}.
	 */
	public static function status($s0, $fid) {
		$cfg = self::cfg($s0);
		$ctx = self::context((int) $fid, array('uid' => 0, 'roles' => array(), 'logged_in' => false, 'manager' => false, 'ip_key' => '', 'cookie' => ''));
		$st = self::state($s0, $ctx);
		$inactive = array();
		$wanted = array_filter(array('user', 'ip', 'cookie', 'email'), function ($d) use ($cfg) { return $cfg[$d] > 0; });
		if ($wanted && !self::plan_ok('person')) $inactive[] = 'person_limits_plan';
		if ($cfg['ip'] > 0 && class_exists('\\Emsfb\\Privacy') && Privacy::ip_mode((int) $fid) === 'none') $inactive[] = 'ip_limit_no_ip';
		if ($cfg['email'] > 0 && $cfg['email_field'] === '') $inactive[] = 'email_limit_no_field';
		if ($cfg['roles'] && !$cfg['members_only']) $inactive[] = 'roles_need_private';
		if (($cfg['draft_on'] || $cfg['partial_on']) && !self::plan_ok('drafts')) $inactive[] = 'drafts_plan';
		$fmt = function ($ts) { return $ts === null ? null : gmdate('c', (int) $ts); };
		return array(
			'form_id'   => (int) $fid,
			'state'     => $st['state'],
			'code'      => $st['code'],
			'message'   => $st['state'] === 'open' ? '' : self::message($st, $cfg),
			'opens_at'  => $fmt($cfg['open']),
			'closes_at' => $fmt($cfg['close']),
			'timezone'  => self::tz()->getName(),
			'total'     => $cfg['total'] > 0 ? array('limit' => $cfg['total'], 'used' => $st['used'] !== null ? (int) $st['used'] : (int) self::total_used($fid), 'left' => $st['left']) : null,
			'person'    => array('user' => $cfg['user'], 'ip' => $cfg['ip'], 'cookie' => $cfg['cookie'], 'email' => $cfg['email'], 'email_field' => $cfg['email_field'], 'period' => $cfg['period']),
			'roles'     => $cfg['roles'],
			'drafts'    => array('draft_on' => $cfg['draft_on'], 'partial_on' => $cfg['partial_on']),
			'inactive'  => $inactive,
		);
	}

	/**
	 * Save checks of update_form_id_Emsfb(): ['errors' => [...], 'warnings' => [...]].
	 * An error refuses the save (closing before opening); warnings never do.
	 */
	public static function save_checks(array $rows, $fid = 0) {
		$errors = array();
		$warnings = array();
		$s0 = isset($rows[0]) && is_array($rows[0]) ? $rows[0] : array();
		$cfg = self::cfg($s0);
		$l = self::texts(array('schedOrderErr', 'limTotalReachedWarn', 'limSharedIpWarn', 'limPlanWarn', 'limIpOffWarn', 'limEmailFieldWarn'));
		$fb = array(
			'schedOrderErr'       => 'The form must close after it opens.',
			'limTotalReachedWarn' => 'This form already has %s entries, so it is full now.',
			'limPlanWarn'         => 'Limits per person need Free Plus or Pro; they are not applied on this site now.',
			'limIpOffWarn'        => 'This form stores no IP addresses, so the limit per IP address is not applied.',
			'limEmailFieldWarn'   => 'Choose the email field for the limit per email address.',
		);
		foreach ($fb as $k => $v) if (!isset($l[$k]) || $l[$k] === $k) $l[$k] = $v;
		if ($cfg['open'] !== null && $cfg['close'] !== null && $cfg['close'] <= $cfg['open']) {
			$errors[] = array('code' => 'sched_order', 'm' => $l['schedOrderErr']);
		}
		if ($cfg['total'] > 0 && (int) $fid > 0) {
			$n = self::entry_count((int) $fid);
			if ($n >= $cfg['total']) $warnings[] = array('code' => 'lim_total_reached', 'm' => sprintf($l['limTotalReachedWarn'], self::fmt_number($n)), 'entries' => $n);
		}
		$wanted = array_filter(array('user', 'ip', 'cookie', 'email'), function ($d) use ($cfg) { return $cfg[$d] > 0; });
		if ($wanted && !self::plan_ok('person')) $warnings[] = array('code' => 'lim_plan', 'm' => $l['limPlanWarn']);
		if ($cfg['ip'] > 0 && (int) $fid > 0 && class_exists('\\Emsfb\\Privacy') && Privacy::ip_mode((int) $fid) === 'none') $warnings[] = array('code' => 'lim_ip_no_ip', 'm' => $l['limIpOffWarn']);
		if ($cfg['email'] > 0 && $cfg['email_field'] === '') $warnings[] = array('code' => 'lim_email_field', 'm' => $l['limEmailFieldWarn']);
		return array('errors' => $errors, 'warnings' => $warnings);
	}

	/**
	 * After sanitize_obj_msg_efb(): lim_email_field must name an email field of the form, and
	 * custom_css changes only for users who may write unfiltered HTML (their previous value stays).
	 */
	public static function normalize_form_settings(array $rows, $old_rows = null) {
		if (!isset($rows[0]) || !is_array($rows[0])) return $rows;
		if (isset($rows[0]['lim_email_field']) && $rows[0]['lim_email_field'] !== '') {
			$ok = false;
			foreach ($rows as $i => $r) {
				if ($i > 0 && is_array($r) && isset($r['id_'], $r['type']) && $r['type'] === 'email' && (string) $r['id_'] === (string) $rows[0]['lim_email_field']) { $ok = true; break; }
			}
			if (!$ok) $rows[0]['lim_email_field'] = '';
		}
		$can = function_exists('current_user_can') ? current_user_can('unfiltered_html') : true;
		if (!$can) {
			$old = is_array($old_rows) && isset($old_rows[0]) ? self::row0($old_rows[0]) : array();
			if (isset($old['custom_css']) && is_string($old['custom_css']) && $old['custom_css'] !== '') $rows[0]['custom_css'] = $old['custom_css'];
			else unset($rows[0]['custom_css']);
		}
		return $rows;
	}

	/** efb_admin_localize_vars: what the builder's "Access and limits" section needs. */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$now = (new \DateTimeImmutable('@' . self::now()))->setTimezone(self::tz());
		$vars['site_tz'] = self::tz()->getName();
		$vars['site_now'] = $now->format('Y-m-d\TH:i');
		$vars['wp_roles'] = self::role_names();
		$vars['can_unfiltered_html'] = function_exists('current_user_can') && current_user_can('unfiltered_html');
		$vars['f11'] = array(
			'plan_person'  => self::plan_ok('person'),
			'plan_drafts'  => self::plan_ok('drafts'),
			'plan_popup'   => self::plan_ok('popup'),
			'ip_mode'      => class_exists('\\Emsfb\\Privacy') ? Privacy::setting('prv_ip_mode') : 'store',
			'gate_rest'    => function_exists('rest_url') ? rest_url('Emsfb/v1/forms/gate') : '/wp-json/Emsfb/v1/forms/gate',
		);
		return $vars;
	}

	/** efb_capabilities: registry entries of F11 and the read-only gate status. */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$package = isset($out['plan']['package_type']) ? (int) $out['plan']['package_type'] : null;
		$entries = array(
			'setting:form_total_limit'   => array('total', array('lim_total', 'lim_full_msg', 'lim_show_left'), 'limTotal'),
			'setting:form_schedule'      => array('schedule', array('sched_open', 'sched_close', 'sched_grace', 'sched_pending_msg', 'sched_closed_msg'), 'schedOpen'),
			'setting:form_roles'         => array('roles', array('stateForm', 'allowed_roles'), 'allowedRoles'),
			'setting:form_person_limits' => array('person', array('lim_user', 'lim_ip', 'lim_cookie', 'lim_email', 'lim_email_field', 'lim_period', 'lim_user_msg'), 'limPerUser'),
			'setting:form_custom_css'    => array('css', array('custom_css'), 'customCss'),
			'setting:form_popup'         => array('popup', array(), 'popupMode'),
			'setting:form_drafts'        => array('drafts', array('draft_on', 'draft_days'), 'draftOn'),
			'setting:form_partials'      => array('partials', array('partial_on', 'partial_days', 'partial_consent', 'partial_need_contact'), 'partialOn'),
		);
		foreach ($entries as $key => $e) {
			$need = isset(self::PLAN[$e[0]]) ? self::PLAN[$e[0]] : 'pro';
			if (function_exists('apply_filters')) $need = apply_filters('efb_f11_plan', $need, $e[0]);
			$ok = class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($need, $package) : true;
			$extra = array();
			if ($e[0] === 'css') $extra['needs_cap'] = 'unfiltered_html';
			if ($e[0] === 'roles') $extra['needs'] = 'stateForm=1';
			if ($e[0] === 'popup') $extra = array('scope' => 'embed', 'shortcode_attrs' => array('popup', 'popup_label', 'popup_style', 'popup_delay'), 'block_attrs' => array('displayMode', 'popupLabel', 'popupStyle'));
			$out['features'][$key] = array_merge(array(
				'kind' => 'setting', 'package' => $need, 'addon' => null, 'available_now' => $ok, 'reason' => $ok ? null : 'plan',
				'prompt_state' => $need === 'free_plus' ? 3 : ($need === 'pro' ? 1 : 0),
				'message_key' => $need === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : ($need === 'pro' ? 'proUnlockMsg' : null),
				'form_keys' => $e[1], 'label_key' => $e[2],
			), $extra);
		}
		$out['form_gate'] = array(
			'rest'    => function_exists('rest_url') ? rest_url('Emsfb/v1/forms/gate') : '/wp-json/Emsfb/v1/forms/gate',
			'methods' => array('GET'),
			'builder' => 'efbBuilderApi.formGate',
			'codes'   => array_values(self::CODES),
			'states'  => array('open', 'pending', 'closed', 'full', 'limited', 'role'),
			'keys'    => self::KEYS,
			'periods' => self::PERIODS,
			'spec'    => 'F11',
		);
		return $out;
	}

	/* ------------------------------------------------------------ render */

	/** Icon of each state (Bootstrap Icons, decorative). */
	const ICONS = array('open' => 'bi-check2-circle', 'pending' => 'bi-hourglass-split', 'closed' => 'bi-calendar-x', 'full' => 'bi-people-fill', 'limited' => 'bi-person-check', 'role' => 'bi-shield-lock-fill');

	/**
	 * The lock card (twin of efb_gate_card_html_efb() in core-efb.js; tests/test-f11-parity.js).
	 * role="status": the sentence is announced; the icon is aria-hidden.
	 */
	public static function card_html($state, $message, $fid, $next = null, $mode = 'card') {
		$state = isset(self::ICONS[$state]) ? $state : 'closed';
		$hidden = $state === 'open' ? ' d-none' : '';
		return '<div class="efb efb-gate-card text-center my-4 p-4' . $hidden . '" id="efb-gate-' . (int) $fid . '" role="status" data-efb-gate="' . $state . '" data-efb-gate-next="' . ($next ? (int) $next : '') . '" data-efb-gate-mode="' . ($mode === 'swap' ? 'swap' : 'card') . '" data-formid="' . (int) $fid . '">'
			. '<div class="efb efb-gate-icon ' . self::ICONS[$state] . ' display-4 mb-2" aria-hidden="true"></div>'
			. '<p class="efb efb-gate-msg fs-5 mb-0">' . (function_exists('esc_html') ? esc_html($message) : htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8')) . '</p>'
			. '</div>';
	}

	/** "N places left" above the form (twin of efb_gate_left_html_efb()). */
	public static function left_html($left, $fid) {
		$l = self::texts(array('limLeft'));
		$t = str_replace('%s', self::fmt_number((int) $left), $l['limLeft']);
		return '<p class="efb efb-gate-left text-center fs-6 my-2" id="efb-gate-left-' . (int) $fid . '" aria-live="polite" data-left="' . (int) $left . '">' . (function_exists('esc_html') ? esc_html($t) : $t) . '</p>';
	}

	/**
	 * What the renderer prints for the gate: null (no rules), or
	 * ['card_only' => bool, 'html' => string, 'hide_form' => bool].
	 * A page that cannot be cached gets the card instead of the form; a cacheable page (widget,
	 * popup, footer) gets both, and core-efb.js asks forms/session/prepare which one to show.
	 */
	public static function render_gate($s0, $fid, $cacheable = null) {
		$cfg = self::cfg($s0);
		if (!self::has_rules($cfg)) return null;
		if ($cacheable === null) $cacheable = class_exists('\\Emsfb\\Cache_Compat') ? Cache_Compat::can_cache(true) : false;
		$st = self::state($s0, self::context((int) $fid));
		$msg = $st['state'] === 'open' ? '' : self::message($st, $cfg);
		if (!$cacheable) {
			if ($st['state'] !== 'open') return array('card_only' => true, 'hide_form' => true, 'html' => self::card_html($st['state'], $msg, $fid, $st['next'], 'card'));
			$html = '';
			if ($cfg['show_left'] && $cfg['total'] > 0 && $st['left'] !== null) $html .= self::left_html($st['left'], $fid);
			if ($st['next'] && $st['next'] - self::now() < DAY_IN_SECONDS_EFB) $html .= self::card_html('open', '', $fid, $st['next'], 'card');
			return array('card_only' => false, 'hide_form' => false, 'html' => $html);
		}
		return array('card_only' => false, 'hide_form' => $st['state'] !== 'open', 'html' => self::card_html($st['state'], $msg, $fid, $st['next'], 'swap'));
	}
}

if (!defined('DAY_IN_SECONDS_EFB')) define('DAY_IN_SECONDS_EFB', 86400);
