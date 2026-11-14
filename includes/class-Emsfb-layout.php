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
	const PLAN = array('review' => 'free', 'section' => 'free');

	/** Row-0 keys of the review page. */
	const FORM_KEYS = array('review_before_submit', 'review_hide_empty', 'review_title', 'section_accordion');

	/** Field keys. collapsible is shared with F09's repeater group (Repeater::sanitize_key keeps '1' / '0'). */
	const FIELD_KEYS = array('collapsed');

	/** Keys of the page only: never saved. */
	const RUNTIME_KEYS = array('vis_view');

	const LIMITS = array('review_title' => 120);

	/** The defaults when a key is missing (old forms). */
	const DEFAULTS = array('review_before_submit' => '0', 'review_hide_empty' => '1', 'review_title' => '', 'section_accordion' => '0', 'collapsible' => '1', 'collapsed' => '0');

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
				return self::on($v) ? '1' : '0';
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
		return (bool) preg_match('/\\\\?"review_before_submit\\\\?"\s*:\s*\\\\?"?(?:1|true)|type\\\\?"\s*:\s*\\\\?"section\\\\?"/', $json);
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
			'section'   => array('type' => 'section', 'keys' => array('collapsible', 'collapsed'), 'form_key' => 'section_accordion', 'members' => 'the fields after it in its step, up to the next section or step (never stored)',
				'answer' => 'none (no row is sent)', 'not_in' => 'a repeater group (a group may sit inside a section)', 'assistant' => 'none'),
			'plan'      => array_map(array(__CLASS__, 'need'), array_combine(array_keys(self::PLAN), array_keys(self::PLAN))),
			'spec'      => 'F10',
		);
		return $out;
	}
}
