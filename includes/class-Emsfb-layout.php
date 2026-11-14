<?php
/**
 * F10: layout and special fields (docs/feature-gaps/F10-layout-special-fields.fa.md).
 *
 *  A  the review page before sending (form keys review_before_submit, review_hide_empty, review_title);
 *     the page draws it (public/assets/js/layout-efb.js), the server checks every answer as before. A
 *     payment-framework form asks the server for the amount (payments/quote) instead of computing it.
 *  B  the section (type section: collapsible, collapsed; form key section_accordion): a heading that shows /
 *     hides the fields after it in its step. Membership is never stored: it is the array order (the page
 *     uses the DOM order, the same). A section hidden by Conditional Logic hides its fields on the server
 *     too (expand_hidden_sections()).
 *  C  the visitor's rich text on a textarea (rich_text, rich_toolbar; Free Plus): the answer is HTML cleaned with
 *     wp_kses and a strict allow-list (rich_allowed_html()), its length counted on the text (rich_check()), and
 *     shown as safe HTML or as plain text (rich_plain()) everywhere else.
 *
 * The save rules of every F10 key are here (sanitize_key(), called from sanitize_obj_msg_efb()); the
 * browser's twin is EFBLayoutCore.sanitizeKey() in layout-efb.js (tests/test-layout-parity.js).
 */
namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Layout {

	/**
	 * The plan of each part (spec §14). The owner may change it with the filter efb_layout_plan
	 * ($need, $part). The review page and sections are free.
	 */
	const PLAN = array('review' => 'free', 'section' => 'free', 'rich_text' => 'free_plus');

	/** Row-0 keys of the review page. */
	const FORM_KEYS = array('review_before_submit', 'review_hide_empty', 'review_title', 'section_accordion');

	/** Field keys. collapsible is shared with F09's repeater group (Repeater::sanitize_key keeps '1' / '0'). */
	const FIELD_KEYS = array('collapsed', 'rich_text', 'rich_toolbar');

	/** Keys of the page only: never saved. */
	const RUNTIME_KEYS = array('vis_view');

	const LIMITS = array('review_title' => 120, 'rich_raw_max' => 65535, 'rich_raw_min' => 4096);

	/** The defaults when a key is missing (old forms). */
	const DEFAULTS = array('review_before_submit' => '0', 'review_hide_empty' => '1', 'review_title' => '', 'section_accordion' => '0', 'collapsible' => '1', 'collapsed' => '0', 'rich_text' => '0', 'rich_toolbar' => 'basic');

	/** The tags a visitor's rich text may hold (no attribute but a link's href). */
	const RICH_TAGS = array('p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'h3', 'h4', 'a');
	const RICH_TOOLBARS = array('basic', 'full');

	/** Types of the legacy payment forms: the visitor pays inside the form, before Send (no review page there). */
	const LEGACY_PAY_TYPES = array('stripe', 'paypal', 'persiaPay', 'persiapay', 'zarinPal');

	/** WordPress hooks (the capability registry). */
	public static function register() {
		if (!function_exists('add_filter')) return;
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 29, 2);
	}

	/* ------------------------------------------------------------------ plan */

	public static function need($part) {
		$need = isset(self::PLAN[$part]) ? self::PLAN[$part] : 'pro';
		if (function_exists('apply_filters')) $need = apply_filters('efb_layout_plan', $need, $part);
		return in_array($need, array('free', 'free_plus', 'pro'), true) ? $need : 'pro';
	}

	public static function plan_ok($part, $package = null) {
		$need = self::need($part);
		if ($need === 'free') return true;
		if ($package === null && function_exists('efb_plan_at_least_efb')) return (bool) efb_plan_at_least_efb($need);
		return class_exists('\\Emsfb\\Builder_Api') ? (bool) Builder_Api::plan_at_least($need, $package) : false;
	}

	/* ------------------------------------------------------------------ helpers */

	public static function on($v) {
		return in_array($v, array(true, 1, '1', 'true', 'on'), true);
	}

	private static function arr($r) {
		if (is_array($r)) return $r;
		if (is_object($r)) return get_object_vars($r);
		return array();
	}

	private static function s($r, $k, $d = '') {
		$r = self::arr($r);
		return isset($r[$k]) && is_scalar($r[$k]) ? (string) $r[$k] : $d;
	}

	private static function text($v, $max) {
		$t = function_exists('sanitize_text_field') ? sanitize_text_field($v) : trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags($v)));
		return function_exists('mb_substr') ? mb_substr($t, 0, $max, 'UTF-8') : substr($t, 0, $max);
	}

	/* ------------------------------------------------------------------ save rules */

	/** Whether $k is a key this class keeps on save. */
	public static function is_key($k) {
		return in_array($k, self::FORM_KEYS, true) || in_array($k, self::FIELD_KEYS, true) || in_array($k, self::RUNTIME_KEYS, true);
	}

	/**
	 * The stored value of an F10 key ('' = not set; the caller drops it). Flags are '1' / '0'. The twin of
	 * EFBLayoutCore.sanitizeKey() (layout-efb.js).
	 */
	public static function sanitize_key($k, $v, $type = '') {
		if (in_array($k, self::RUNTIME_KEYS, true)) return '';
		if (is_bool($v)) $v = $v ? '1' : '0';
		if (!is_scalar($v)) return '';
		$v = (string) $v;
		switch ($k) {
			case 'review_before_submit':
			case 'review_hide_empty':
			case 'section_accordion':
			case 'collapsed':
			case 'rich_text':
				return self::on($v) ? '1' : '0';
			case 'rich_toolbar':
				return in_array($v, self::RICH_TOOLBARS, true) ? $v : 'basic';
			case 'review_title':
				return self::text($v, self::LIMITS['review_title']);
		}
		return '';
	}

	/* ------------------------------------------------------------------ A: review */

	/** Whether a form (rows, row 0 first) holds a legacy gateway field (paid inside the form, before Send). */
	public static function legacy_payment($rows) {
		foreach ((array) $rows as $i => $r) {
			if ($i > 0 && in_array(self::s($r, 'type'), self::LEGACY_PAY_TYPES, true)) return true;
		}
		return false;
	}

	/** Whether the page shows the review page before sending (row 0 flag, not a legacy payment form). */
	public static function review_on($rows) {
		$rows = (array) $rows;
		if (!isset($rows[0]) || !self::on(self::s($rows[0], 'review_before_submit'))) return false;
		if (!self::plan_ok('review')) return false;
		return !self::legacy_payment($rows);
	}

	/**
	 * Whether the stored form JSON (the raw column, quotes escaped or not) needs layout-efb.js on the page.
	 * Later parts add their own keys.
	 */
	public static function json_needs_runtime($json) {
		if (!is_string($json) || $json === '') return false;
		return (bool) preg_match('/\\\\?"review_before_submit\\\\?"\s*:\s*\\\\?"?(?:1|true)|type\\\\?"\s*:\s*\\\\?"section\\\\?"/', $json) || self::json_has_rich($json);
	}

	/** Whether the stored form JSON has a rich textarea (rich-text-efb.js on the page). */
	public static function json_has_rich($json) {
		return is_string($json) && (bool) preg_match('/\\\\?"rich_text\\\\?"\s*:\s*\\\\?"?(?:1|true)/', $json);
	}

	/* ------------------------------------------------------------------ C: rich text */

	/** Whether a field takes rich text now: a textarea with rich_text, on a plan that has it (else a plain textarea). */
	public static function rich_on($f) {
		$t = self::s($f, 'type');
		return $t === 'textarea' && self::on(self::s($f, 'rich_text')) && self::plan_ok('rich_text');
	}

	/** wp_kses() allow-list of a visitor's rich text: the tags without attributes, a with href only. */
	public static function rich_allowed_html() {
		$out = array();
		foreach (self::RICH_TAGS as $t) $out[$t] = array();
		$out['a'] = array('href' => true);
		return $out;
	}

	/** The largest HTML kept for a field (bytes): min(65535, max(4 × mlen, 4096)); 65535 without a maximum. */
	public static function rich_raw_cap($f) {
		$mlen = (int) self::s($f, 'mlen', '0');
		return $mlen > 0 ? min(self::LIMITS['rich_raw_max'], max(4 * $mlen, self::LIMITS['rich_raw_min'])) : self::LIMITS['rich_raw_max'];
	}

	/**
	 * A visitor's HTML as it may be stored or shown: the allow-list (wp_kses with http, https and mailto only),
	 * the browser's <div> paragraphs as <p>, the content of script / style / … dropped, balanced tags, links with
	 * rel="nofollow ugc noopener" and target="_blank" written here (a link without a usable address loses its tag).
	 */
	public static function rich_sanitize($html) {
		$html = is_scalar($html) ? (string) $html : '';
		if ($html === '') return '';
		$html = preg_replace('#<(script|style|xml|head|title|template|object|iframe|noscript|svg|math|select|textarea|button|embed)\b[^>]*>.*?</\1\s*>#is', '', $html);
		$html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*/?>#i', '', $html);
		$html = preg_replace('#<!--.*?(-->|$)#s', '', $html);
		$html = preg_replace(array('#<div\b[^>]*>#i', '#</div\s*>#i', '#<h[12]\b[^>]*>#i', '#</h[12]\s*>#i', '#<h[56]\b[^>]*>#i', '#</h[56]\s*>#i'), array('<p>', '</p>', '<h3>', '</h3>', '<h4>', '</h4>'), $html);
		if (function_exists('wp_kses')) {
			$html = wp_kses($html, self::rich_allowed_html(), array('http', 'https', 'mailto'));
		} else {
			$html = strip_tags($html, '<' . implode('><', self::RICH_TAGS) . '>');
			$html = preg_replace('#<(?!a\b)([a-z0-9]+)\b[^>]*>#i', '<$1>', $html);
		}
		if (function_exists('force_balance_tags')) $html = force_balance_tags($html);
		if (function_exists('wp_kses_normalize_entities')) $html = wp_kses_normalize_entities($html);
		$html = preg_replace_callback('#<a\b([^>]*)>(.*?)</a>#is', function ($m) {
			$href = '';
			if (preg_match('#\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))#i', $m[1], $h)) $href = html_entity_decode($h[1] !== '' ? $h[1] : (isset($h[2]) && $h[2] !== '' ? $h[2] : (isset($h[3]) ? $h[3] : '')), ENT_QUOTES, 'UTF-8');
			$href = trim($href);
			if (!preg_match('#^(https?://[^\s"<>]+|mailto:[^\s"<>]+)$#i', $href)) return $m[2];
			$attr = function_exists('esc_url') ? esc_url($href, array('http', 'https', 'mailto')) : htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
			return $attr === '' ? $m[2] : '<a href="' . $attr . '" rel="nofollow ugc noopener" target="_blank">' . $m[2] . '</a>';
		}, $html);
		// An <a> the pass above did not rebuild (no closing tag) goes; its text stays.
		$html = preg_replace('#<a\b(?![^>]*\brel="nofollow ugc noopener")[^>]*>#i', '', $html);
		$html = preg_replace('#<(p|h3|h4|li|blockquote|strong|b|em|i|u)>\s*</\1>#i', '', $html);
		return trim($html);
	}

	/** The text of rich HTML (CSV, SMS, Telegram, Google Sheets, logic, smart tags): block ends and <br> as new lines. */
	public static function rich_plain($html) {
		$s = is_scalar($html) ? (string) $html : '';
		$s = preg_replace('#<\s*br\s*/?>#i', "\n", $s);
		$s = preg_replace('#</?\s*(p|ul|ol|li|h3|h4|blockquote|div)\b[^>]*>#i', "\n", $s);
		$s = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($s, false) : strip_tags($s);
		$s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$s = str_replace("\xC2\xA0", ' ', $s);
		$s = preg_replace("/[ \t]+\n/", "\n", $s);
		$s = preg_replace("/\n[ \t]*/", "\n", $s);
		$s = preg_replace("/\n{2,}/", "\n", $s);
		return trim($s);
	}

	/** The plain text of a stored answer row: rich (fmt html) as text, any other value as it is. */
	public static function row_text($row) {
		$v = is_array($row) && isset($row['value']) && is_scalar($row['value']) ? (string) $row['value'] : '';
		if (is_array($row) && isset($row['fmt']) && $row['fmt'] === 'html') return self::rich_plain(str_replace('@efb@dq#', '"', $v));
		return $v;
	}

	/**
	 * The submit's check of a rich answer: [ok, html, plain, code]. code: 'raw' (more HTML than rich_raw_cap()),
	 * 'min' / 'max' (milen / mlen on the text, in code points like the browser).
	 */
	public static function rich_check($raw, $f) {
		$raw = is_scalar($raw) ? (string) $raw : '';
		if (strlen($raw) > self::rich_raw_cap($f)) return array(false, '', '', 'raw');
		$html = self::rich_sanitize($raw);
		$plain = self::rich_plain($html);
		$len = function_exists('mb_strlen') ? mb_strlen($plain, 'UTF-8') : strlen($plain);
		if ($len === 0) return array(true, '', '', '');
		$min = (int) self::s($f, 'milen', '0');
		$max = (int) self::s($f, 'mlen', '0');
		if ($min > 0 && $len < $min) return array(false, $html, $plain, 'min');
		if ($max > 0 && $len > $max) return array(false, $html, $plain, 'max');
		return array(true, $html, $plain, '');
	}

	/* ------------------------------------------------------------------ B: sections */

	/**
	 * The fields of each section: section id_ => [field id_, …] (the rows after it in its step, up to the next
	 * section or step, in array order; a repeater group's fields with their group; options and matrix rows are
	 * not fields). Works on associative rows and on stdClass rows.
	 */
	public static function section_members($rows) {
		$out = array();
		$cur = null;
		foreach ((array) $rows as $i => $r) {
			if ($i === 0) continue;
			$t = self::s($r, 'type');
			if ($t === 'step') { $cur = null; continue; }
			if ($t === 'section') { $cur = self::s($r, 'id_'); if ($cur !== '') $out[$cur] = array(); continue; }
			if ($cur === null || $t === 'option' || $t === 'r_matrix') continue;
			$id = self::s($r, 'id_');
			if ($id !== '') $out[$cur][] = $id;
		}
		return $out;
	}

	/**
	 * A section hidden by Conditional Logic hides its fields (spec §11): they join the ignored set and are not
	 * required. $rows: the form as the submit holds it (associative); $ignored: id => true. [rows, ignored].
	 */
	public static function expand_hidden_sections(array $rows, array $ignored) {
		if (!$ignored) return array($rows, $ignored);
		$add = array();
		foreach (self::section_members($rows) as $sid => $ids) {
			if (!isset($ignored[$sid])) continue;
			foreach ($ids as $id) $add[$id] = true;
		}
		if (!$add) return array($rows, $ignored);
		foreach ($rows as $i => $r) {
			if ($i > 0 && is_array($r) && isset($r['id_']) && isset($add[(string) $r['id_']])) $rows[$i]['required'] = false;
		}
		return array($rows, $ignored + $add);
	}

	/* ------------------------------------------------------------------ capabilities */

	/** efb_capabilities: setting:review_before_submit and capabilities.layout (SitePilot manifest features.layout). */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$package = isset($out['plan']['package_type']) ? (int) $out['plan']['package_type'] : null;
		$entry = function ($part, $kind, $keys, $label, $extra = array()) use ($package) {
			$need = self::need($part);
			$ok = $need === 'free' ? true : (class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($need, $package) : false);
			return array_merge(array('kind' => $kind, 'scope' => $kind === 'field' ? 'field' : 'form', 'package' => $need, 'addon' => null, 'available_now' => $ok, 'reason' => $ok ? null : 'plan',
				'prompt_state' => $need === 'free_plus' ? 3 : ($need === 'pro' ? 1 : 0),
				'message_key' => $need === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : ($need === 'pro' ? 'proUnlockMsg' : null),
				'form_keys' => $keys, 'label_key' => $label), $extra);
		};
		$out['features']['setting:review_before_submit'] = $entry('review', 'setting', array('review_before_submit', 'review_hide_empty', 'review_title'), 'rvwBfrSbmt', array('not_on' => 'legacy payment forms (stripe, paypal, persiaPay fields)'));
		$out['features']['setting:rich_text'] = $entry('rich_text', 'setting', array('rich_text', 'rich_toolbar'), 'richText', array('types' => array('textarea')));
		$out['features']['field:section'] = array_merge(isset($out['features']['field:section']) && is_array($out['features']['field:section']) ? $out['features']['field:section'] : array(),
			$entry('section', 'field', array('name', 'message', 'collapsible', 'collapsed', 'icon', 'el_text_color', 'el_text_size', 'section_accordion'), 'section'), array('pii_default' => null));
		$out['layout'] = array(
			'v'         => 1,
			'form_keys' => self::FORM_KEYS,
			'field_keys' => self::FIELD_KEYS,
			'defaults'  => self::DEFAULTS,
			'limits'    => self::LIMITS,
			'review'    => array('page' => 'after the last step, before Send; the visitor confirms', 'not_on' => 'forms with a stripe / paypal / persiaPay field',
				'amount' => 'payment-framework forms: the server\'s amount (POST payments/quote), never the page\'s', 'assistant' => 'an assistant never presses Confirm for the visitor'),
			'rich_text' => array('keys' => array('rich_text', 'rich_toolbar'), 'toolbars' => self::RICH_TOOLBARS, 'tags' => self::RICH_TAGS, 'link_protocols' => array('http', 'https', 'mailto'),
				'answer' => 'value = cleaned HTML, fmt = "html" (written by the server); length limits count the text', 'assistant' => 'type plain text; the server keeps only the allowed tags'),
			'section'   => array('type' => 'section', 'keys' => array('collapsible', 'collapsed'), 'form_key' => 'section_accordion', 'members' => 'the fields after it in its step, up to the next section or step (never stored)',
				'answer' => 'none (no row is sent)', 'not_in' => 'a repeater group (a group may sit inside a section)', 'assistant' => 'none'),
			'plan'      => array_map(array(__CLASS__, 'need'), array_combine(array_keys(self::PLAN), array_keys(self::PLAN))),
			'spec'      => 'F10',
		);
		return $out;
	}
}
