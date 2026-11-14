<?php
/**
 * F09: the repeater group (spec docs/feature-gaps/F09-repeater.fa.md).
 *
 * Data model: a row `type: "repeater"` with its settings (min_rows, max_rows, default_rows, rows_from,
 * row_label, collapsible, collapse_prev, rpt_layout, btn_add_text, rmv_confirm) and ordinary field rows
 * that carry `group: <id_ of the repeater>`, right after it in the array, in its step. Options of a child
 * keep `parent: <child id_>` and have no `group`. A child is only a child when `group` names an existing
 * repeater row and its type is allowed (child_types()); anything else is an ordinary field (an orphan).
 *
 * At run time (page and server) row n of a group is a copy of every child with the virtual id
 * `{child}_r{n}` (and its options `{option}_r{n}`). The browser sends the base ids with rg (group) and
 * ri (row from 0); the server expands the answers into the same virtual ids (expand()), checks them with
 * the field loop it already has, and folds them back (fold()) before anything is stored.
 *
 * Twin of public/assets/js/repeater-efb.js (pure part: children map, keys, expansion, wire, fold).
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

final class Repeater {

	/** Field types a row may hold (spec §5.4; filter efb_repeater_child_types). */
	const CHILD_TYPES = array('text', 'firstName', 'lastName', 'email', 'number', 'tel', 'url', 'textarea', 'date', 'advdate', 'range', 'color',
		'select', 'radio', 'checkbox', 'multiselect', 'switch', 'yesNo', 'rating', 'pointr5', 'pointr10', 'heading', 'html', 'file', 'mobile',
		'calc', 'hiddenField', 'ranking');

	/** Field types whose value can give the number of rows (rows_from). */
	const COUNT_SOURCE_TYPES = array('number', 'range', 'select', 'radio', 'calc');

	/** The settings of a repeater row, all scalar (spec §5.1). */
	const KEYS = array('min_rows', 'max_rows', 'default_rows', 'rows_from', 'row_label', 'collapsible', 'collapse_prev', 'rpt_layout', 'btn_add_text', 'rmv_confirm');
	/** Run-time keys of a row copy: never saved, never accepted from a save or an API. */
	const RUNTIME_KEYS = array('rpt_base', 'rpt_n', 'rpt_group', 'rpt_ri');

	const DEFAULTS = array('min_rows' => '1', 'max_rows' => '10', 'default_rows' => '1', 'rows_from' => '', 'row_label' => '', 'collapsible' => '0',
		'collapse_prev' => '1', 'rpt_layout' => 'blocks', 'btn_add_text' => '', 'rmv_confirm' => '1');

	/** Hard caps (spec §14), each with its filter. */
	const CAPS = array('rows' => 50, 'children' => 25, 'groups' => 10, 'submission_rows' => 1500, 'email_rows' => 20);
	const CAP_FILTERS = array('rows' => 'efb_repeater_rows_cap', 'children' => 'efb_repeater_children_cap', 'groups' => 'efb_repeater_groups_cap',
		'submission_rows' => 'efb_submission_rows_cap', 'email_rows' => 'efb_email_repeater_rows');

	/**
	 * Plan of each part (spec §16, open question 1): a group of fields shown once (max_rows 1) is Free Plus;
	 * repeating rows (max_rows > 1), the number of rows from another field, collapsible rows and the
	 * side-by-side layout are Pro. The owner may change it with the filter efb_repeater_plan.
	 */
	const PLAN = array('group' => 'free_plus', 'repeat' => 'pro');

	/** Stable codes of the submit (SitePilot contract). */
	const CODES = array('min' => 'efb_rpt_min', 'max' => 'efb_rpt_max', 'count' => 'efb_rpt_count', 'required' => 'efb_rpt_required', 'payload' => 'efb_invalid_payload');

	const TEXT_DEFAULTS = array(
		'repeater' => 'Repeater group', 'rowN' => 'Row %s', 'addRow' => 'Add another', 'removeRow' => 'Remove row %s', 'removeRowConfirm' => 'Remove this row?',
		'rowAdded' => 'Row %s added', 'rowRemoved' => 'Row removed', 'expandRow' => 'Show row %s', 'collapseRow' => 'Hide row %s',
		'rptMaxReached' => 'You have reached the maximum of %s rows.', 'rptMinRowsMsg' => '%1$s needs at least %2$s rows.',
		'rptMaxRowsMsg' => '%1$s allows at most %2$s rows.', 'rptCountMismatch' => 'The number of rows in %s does not match the number you selected.',
		'rptRowRequired' => '%1$s in row %2$s is required.', 'rptCount' => '%s (count)', 'rptRowsCount' => '%s rows', 'yes' => 'Yes', 'no' => 'No',
	);

	/** WordPress hooks (builder variables, the capability registry, forms/{id}/schema). */
	public static function register() {
		if (!function_exists('add_filter')) return;
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 28, 2);
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 27, 2);
	}

	/** efb_admin_localize_vars: efb_var.repeater = {plan, caps, child_types}. */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$vars['repeater'] = array(
			'plan'        => array('group' => self::plan_ok('group'), 'repeat' => self::plan_ok('repeat')),
			'caps'        => self::caps(),
			'child_types' => self::child_types(),
		);
		return $vars;
	}

	/** efb_capabilities: field:repeater (Free Plus), setting:repeater_rows (Pro) and capabilities.repeater (the contract). */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$package = isset($out['plan']['package_type']) ? (int) $out['plan']['package_type'] : null;
		$entry = function ($part, $kind, $keys, $label) use ($package) {
			$need = isset(self::PLAN[$part]) ? self::PLAN[$part] : 'pro';
			if (function_exists('apply_filters')) $need = apply_filters('efb_repeater_plan', $need, $part);
			$ok = $need === 'free' ? true : (class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($need, $package) : false);
			return array('kind' => $kind, 'scope' => 'field', 'package' => $need, 'addon' => null, 'available_now' => $ok, 'reason' => $ok ? null : 'plan',
				'prompt_state' => $need === 'free_plus' ? 3 : ($need === 'pro' ? 1 : 0),
				'message_key' => $need === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : ($need === 'pro' ? 'proUnlockMsg' : null),
				'form_keys' => $keys, 'label_key' => $label);
		};
		$out['features']['field:repeater'] = array_merge(isset($out['features']['field:repeater']) && is_array($out['features']['field:repeater']) ? $out['features']['field:repeater'] : array(),
			$entry('group', 'field', array('min_rows', 'max_rows', 'default_rows', 'row_label', 'btn_add_text', 'rmv_confirm', 'group'), 'repeater'), array('pii_default' => null));
		$out['features']['setting:repeater_rows'] = $entry('repeat', 'setting', array('max_rows', 'rows_from', 'collapsible', 'collapse_prev', 'rpt_layout'), 'repeater');
		$out['repeater'] = array(
			'type'        => 'repeater',
			'keys'        => self::KEYS,
			'child_key'   => 'group',
			'child_types' => self::child_types(),
			'count_from'  => self::COUNT_SOURCE_TYPES,
			'enums'       => array('rpt_layout' => array('blocks', 'inline'), 'collapsible' => array('0', '1'), 'collapse_prev' => array('0', '1'), 'rmv_confirm' => array('0', '1')),
			'defaults'    => self::DEFAULTS,
			'caps'        => self::caps(),
			'runtime'     => array('virtual_id' => '{child}_r{n}', 'never_saved' => self::RUNTIME_KEYS),
			'answer'      => array('summary' => '{id_: group, type: "repeater", value: "<rows>"}', 'row' => 'the child id_ with rg (group, set by the server) and ri (row from 0)'),
			'rules'       => array('children right after the group, in its step', 'no group inside a group', 'a child type outside child_types is an ordinary field', 'rows_from: a number or choice field before the group, outside it'),
			'codes'       => array_values(self::CODES),
			'builder'     => array('efbBuilderApi.insertItems', 'efbBuilderApi.setForm', 'efbBuilderApi.addGroup', 'efbBuilderApi.moveIntoGroup'),
			'plan'        => array('group' => self::PLAN['group'], 'repeat' => self::PLAN['repeat']),
			'spec'        => 'F09',
		);
		return $out;
	}

	/* ------------------------------------------------------------------ the model */
	/* ------------------------------------------------------------------ the model */

	public static function child_types() {
		$t = self::CHILD_TYPES;
		if (function_exists('apply_filters')) $t = apply_filters('efb_repeater_child_types', $t);
		return array_values(array_diff(array_map('strval', (array) $t), array('repeater')));
	}

	public static function caps() {
		$out = array();
		foreach (self::CAPS as $k => $v) {
			$n = function_exists('apply_filters') ? (int) apply_filters(self::CAP_FILTERS[$k], $v) : $v;
			$out[$k] = $n > 0 ? $n : $v;
		}
		return $out;
	}

	/** A row of the structure as an array (the render path holds stdClass objects). */
	private static function arr($r) {
		if (is_array($r)) return $r;
		if (is_object($r)) return get_object_vars($r);
		return array();
	}

	private static function s($r, $k, $d = '') {
		$r = self::arr($r);
		return isset($r[$k]) && is_scalar($r[$k]) ? (string) $r[$k] : $d;
	}

	public static function is_group($r) {
		return self::s($r, 'type') === 'repeater' && self::s($r, 'id_') !== '';
	}

	/**
	 * Group id => indexes of its children, in array order. A child is a non-option field row whose `group`
	 * names a repeater row of this form, whose type is allowed and which is not itself a repeater (no
	 * nesting in version 1); children above the cap are ignored (they stay ordinary fields). Groups above
	 * the groups cap still count (the builder refuses them; a form saved with more keeps working).
	 */
	public static function children_map($structure) {
		$groups = array();
		if (!is_array($structure)) return $groups;
		foreach ($structure as $i => $r) {
			if ($i < 1) continue;
			if (self::is_group($r)) $groups[self::s($r, 'id_')] = array();
		}
		if (!$groups) return $groups;
		$allowed = array_flip(self::child_types());
		$cap = self::caps()['children'];
		foreach ($structure as $i => $r) {
			if ($i < 1) continue;
			$g = self::s($r, 'group');
			$t = self::s($r, 'type');
			if ($g === '' || !isset($groups[$g]) || $t === 'option' || $t === 'repeater' || $t === 'step' || !isset($allowed[$t]) || self::s($r, 'id_') === '') continue;
			if (count($groups[$g]) >= $cap) continue;
			$groups[$g][] = $i;
		}
		return $groups;
	}

	/** child id => group id (only real children). */
	public static function child_groups($structure) {
		$out = array();
		foreach (self::children_map($structure) as $g => $idx) {
			foreach ($idx as $i) $out[self::s($structure[$i], 'id_')] = $g;
		}
		return $out;
	}

	public static function has_groups($structure) {
		if (!is_array($structure)) return false;
		foreach ($structure as $i => $r) if ($i > 0 && self::is_group($r)) return true;
		return false;
	}

	/** Whether this site's plan has a part (group / repeat). */
	public static function plan_ok($part, $package = null) {
		$need = isset(self::PLAN[$part]) ? self::PLAN[$part] : 'pro';
		if (function_exists('apply_filters')) $need = apply_filters('efb_repeater_plan', $need, $part);
		if ($need === 'free') return true;
		if ($package === null && function_exists('efb_plan_at_least_efb')) return (bool) efb_plan_at_least_efb($need);
		return class_exists('\\Emsfb\\Builder_Api') ? (bool) Builder_Api::plan_at_least($need, $package) : false;
	}

	private static function int_of($v, $d) {
		$s = is_scalar($v) ? trim(self::ascii_digits((string) $v)) : '';
		return preg_match('/^\d{1,6}$/D', $s) ? (int) $s : $d;
	}

	public static function ascii_digits($s) {
		$map = array();
		for ($i = 0; $i < 10; $i++) {
			$map[html_entity_decode('&#' . (0x06F0 + $i) . ';', ENT_QUOTES, 'UTF-8')] = (string) $i;
			$map[html_entity_decode('&#' . (0x0660 + $i) . ';', ENT_QUOTES, 'UTF-8')] = (string) $i;
		}
		return strtr((string) $s, $map);
	}

	/**
	 * The limits of a group as the page and the server apply them: [min, max, default, required-floor min].
	 * max in [1, rows cap]; min in [0, max] (1 when the group is required); default in [min, max].
	 * $repeat_ok false (plan without repeating rows): one row exactly.
	 */
	public static function limits($g, $repeat_ok = true) {
		$cap = self::caps()['rows'];
		$max = self::int_of(self::s($g, 'max_rows', self::DEFAULTS['max_rows']), 10);
		$max = max(1, min($cap, $max));
		$min = self::int_of(self::s($g, 'min_rows', self::DEFAULTS['min_rows']), 1);
		$min = max(0, min($max, $min));
		$req = in_array(self::s($g, 'required'), array('1', 'true'), true);
		if ($req && $min < 1) $min = 1;
		$def = self::int_of(self::s($g, 'default_rows', self::DEFAULTS['default_rows']), 1);
		$def = max($min, min($max, $def));
		if (!$repeat_ok) { $max = 1; $min = min($min, 1); $def = max($min, min(1, $def)); }
		return array('min' => $min, 'max' => $max, 'default' => $def);
	}

	/* ------------------------------------------------------------------ the save (sanitize_obj_msg_efb) */

	public static function is_key($k) {
		return in_array($k, self::KEYS, true) || $k === 'group' || in_array($k, self::RUNTIME_KEYS, true);
	}

	/** One key as it may be stored ('' = drop it). Twin: EFBRepeaterCore.sanitizeKey(). */
	public static function sanitize_key($k, $v) {
		if (in_array($k, self::RUNTIME_KEYS, true)) return '';
		if (!is_scalar($v) || is_bool($v)) $v = is_bool($v) ? ($v ? '1' : '0') : '';
		$v = (string) $v;
		switch ($k) {
			case 'min_rows':
			case 'max_rows':
			case 'default_rows':
				$s = trim(self::ascii_digits($v));
				if (!preg_match('/^\d{1,4}$/D', $s)) return '';
				return (string) min((int) $s, self::caps()['rows']);
			case 'rows_from':
			case 'group':
				return preg_match('/^[A-Za-z0-9_-]{1,40}$/D', $v) ? $v : '';
			case 'collapsible':
			case 'collapse_prev':
			case 'rmv_confirm':
				return in_array($v, array('1', 'true', 'on'), true) ? '1' : '0';
			case 'rpt_layout':
				return in_array($v, array('blocks', 'inline'), true) ? $v : 'blocks';
			case 'row_label':
			case 'btn_add_text':
				$t = function_exists('sanitize_text_field') ? sanitize_text_field($v) : trim(strip_tags($v));
				return function_exists('mb_substr') ? mb_substr($t, 0, $k === 'row_label' ? 200 : 100) : substr($t, 0, 200);
		}
		return '';
	}

	/**
	 * After the key-by-key pass: limits in order, a child's step = its group's step, `group` dropped where it
	 * does not make a child (missing or not a repeater, a repeater, a type not allowed), rows_from kept only on
	 * a number / choice field before the group and outside it, and every child (with its options) moved right
	 * after its group. A form without a repeater comes back unchanged.
	 */
	public static function normalize_rows($rows) {
		if (!is_array($rows)) return $rows;
		$has_group_key = false;
		foreach ($rows as $i => $r) {
			if (!is_array($r)) continue;
			foreach (self::RUNTIME_KEYS as $rk) if (array_key_exists($rk, $r)) unset($rows[$i][$rk]);
			if ($i > 0 && isset($r['group']) && ($r['type'] ?? '') !== 'option') $has_group_key = true;
		}
		if (!self::has_groups($rows) && !$has_group_key) return $rows;
		$map = self::children_map($rows);
		$is_child = array();
		foreach ($map as $g => $idx) foreach ($idx as $i) $is_child[$i] = $g;
		// `group` that makes no child is dropped (orphans are ordinary fields).
		foreach ($rows as $i => $r) {
			if ($i < 1 || !is_array($r) || ($r['type'] ?? '') === 'option' || !array_key_exists('group', $r)) continue;
			if (!isset($is_child[$i])) unset($rows[$i]['group']);
		}
		$index_of = array();
		foreach ($rows as $i => $r) if ($i > 0 && is_array($r) && isset($r['id_']) && is_scalar($r['id_'])) $index_of[(string) $r['id_']] = $i;
		foreach ($rows as $i => $r) {
			if ($i < 1 || !self::is_group($r)) continue;
			$g = (string) $r['id_'];
			$l = self::limits($r);
			$rows[$i]['max_rows'] = (string) $l['max'];
			$rows[$i]['min_rows'] = (string) (isset($r['min_rows']) && $r['min_rows'] !== '' ? max(0, min($l['max'], self::int_of($r['min_rows'], 1))) : 1);
			$rows[$i]['default_rows'] = (string) $l['default'];
			foreach (array('rpt_layout', 'collapsible', 'collapse_prev', 'rmv_confirm') as $k) if (!isset($rows[$i][$k]) || $rows[$i][$k] === '') $rows[$i][$k] = self::DEFAULTS[$k];
			$from = isset($r['rows_from']) && is_scalar($r['rows_from']) ? (string) $r['rows_from'] : '';
			if ($from !== '') {
				$fi = isset($index_of[$from]) ? $index_of[$from] : -1;
				$ft = $fi > 0 ? (string) ($rows[$fi]['type'] ?? '') : '';
				if ($fi < 1 || $fi > $i || isset($is_child[$fi]) || !in_array($ft, self::COUNT_SOURCE_TYPES, true)) $rows[$i]['rows_from'] = '';
			}
			foreach ($map[$g] as $ci) {
				if (isset($r['step'])) $rows[$ci]['step'] = $r['step'];
			}
		}
		// Children (and their options) right after their group, in their order.
		$children_of = array();
		foreach ($map as $g => $idx) $children_of[$g] = $idx;
		$options_of = array();
		foreach ($rows as $i => $r) {
			if ($i > 0 && is_array($r) && ($r['type'] ?? '') === 'option' && isset($r['parent']) && is_scalar($r['parent'])) $options_of[(string) $r['parent']][] = $i;
		}
		$placed = array();
		$out = array();
		foreach ($rows as $i => $r) {
			if (isset($placed[$i])) continue;
			if (isset($is_child[$i])) continue; // emitted with its group
			$pid = is_array($r) && ($r['type'] ?? '') === 'option' && isset($r['parent']) && is_scalar($r['parent']) ? (string) $r['parent'] : null;
			if ($pid !== null && isset($index_of[$pid]) && isset($is_child[$index_of[$pid]])) continue; // an option of a child
			$out[] = $r;
			$placed[$i] = true;
			if ($i > 0 && self::is_group($r) && isset($children_of[(string) $r['id_']])) {
				foreach ($children_of[(string) $r['id_']] as $ci) {
					$out[] = $rows[$ci];
					$placed[$ci] = true;
					$cid = (string) $rows[$ci]['id_'];
					if (isset($options_of[$cid])) foreach ($options_of[$cid] as $oi) { $out[] = $rows[$oi]; $placed[$oi] = true; }
				}
			}
		}
		return array_keys($rows) === range(0, count($rows) - 1) ? $out : $rows;
	}

	/* ------------------------------------------------------------------ texts */

	public static function t($key, $lanText = array()) {
		if (is_array($lanText) && isset($lanText[$key]) && is_string($lanText[$key]) && $lanText[$key] !== '') return $lanText[$key];
		static $cache = array();
		if (!isset($cache[$key]) && function_exists('get_efbFunction')) {
			$fn = get_efbFunction();
			if ($fn && method_exists($fn, 'text_efb')) {
				$tx = $fn->text_efb(array($key));
				if (is_array($tx) && isset($tx[$key]) && is_string($tx[$key]) && $tx[$key] !== '') $cache[$key] = $tx[$key];
			}
		}
		if (isset($cache[$key])) return $cache[$key];
		return isset(self::TEXT_DEFAULTS[$key]) ? self::TEXT_DEFAULTS[$key] : $key;
	}
}
