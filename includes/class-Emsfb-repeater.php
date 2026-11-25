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
		add_filter('efb_form_schema', array(__CLASS__, 'schema'), 14, 3);
	}

	/**
	 * forms/{id}/schema (F06 Form_Schema) for an assistant in the page: a group's entry gets `repeater` (its
	 * fields, the limits this site's plan allows, rows_from, the row title, how to add / reach a row); a field of
	 * a group gets `group` and how to reach its input in row k. Nothing a visitor does not see (no formulas,
	 * no settings of the form). $rows: the form's rows when the caller has them (tests).
	 */
	public static function schema($out, $form_id = 0, $context = array(), $rows = null) {
		if (!is_array($out) || empty($out['fields'])) return $out;
		if ($rows === null) {
			if (!class_exists('\\Emsfb\\Calendar_Rest')) return $out;
			$rows = Calendar_Rest::form_rows((int) $form_id);
		}
		if (!is_array($rows) || !self::has_groups($rows)) return $out;
		$map = self::children_map($rows);
		$repeat = self::plan_ok('repeat');
		$kids = array();
		foreach ($map as $g => $idx) foreach ($idx as $ci) $kids[(string) $rows[$ci]['id_']] = $g;
		$by_id = array();
		foreach ($rows as $i => $r) if ($i > 0 && is_array($r) && isset($r['id_']) && is_scalar($r['id_'])) $by_id[(string) $r['id_']] = $r;
		foreach ($out['fields'] as $k => $e) {
			if (!is_array($e) || !isset($e['id'])) continue;
			$id = (string) $e['id'];
			if (isset($map[$id]) && isset($by_id[$id])) {
				$r = $by_id[$id];
				$l = self::limits($r, $repeat);
				$children = array();
				foreach ($map[$id] as $ci) $children[] = (string) $rows[$ci]['id_'];
				$from = $repeat && isset($r['rows_from']) && is_scalar($r['rows_from']) ? (string) $r['rows_from'] : '';
				$out['fields'][$k]['repeater'] = array(
					'children' => $children, 'min_rows' => $l['min'], 'max_rows' => $l['max'], 'default_rows' => $l['default'],
					'rows_from' => $from !== '' ? $from : null, 'repeat' => $repeat,
					'row_label' => isset($r['row_label']) && is_scalar($r['row_label']) ? (string) $r['row_label'] : '',
					'answer' => 'the number of rows (the server counts them)',
				);
				$out['fields'][$k]['fill'] = array('api' => 'EFBRepeater', 'add' => 'EFBRepeater.addRow(form_id, "' . $id . '")', 'remove' => 'EFBRepeater.removeRow(form_id, "' . $id . '", row_element)',
					'rows' => 'EFBRepeater.rowsOf(form_id, "' . $id . '") gives {n, ri} per row', 'note' => $from !== '' ? 'the number of rows follows the field ' . $from : 'add rows up to max_rows, then fill each row\'s fields');
				$out['fields'][$k]['assistant'] = 'rows';
				continue;
			}
			if (isset($kids[$id])) {
				$out['fields'][$k]['group'] = $kids[$id];
				$fill = array('api' => 'dom', 'per_row' => true, 'input' => $id . '_r{n}_', 'resolve' => 'EFBRepeater.fieldAt(form_id, "' . $id . '", row_index) gives the copy id {id}_r{n}; its input is that id + "_"');
				if (!empty($e['options'])) $fill['options'] = 'option id + "_r{n}"';
				$out['fields'][$k]['fill'] = $fill;
			}
		}
		$out['repeater'] = array('page_api' => 'window.EFBRepeater', 'wire' => 'answers are sent under the field\'s own id with rg (group) and ri (row from 0)', 'caps' => self::caps());
		return $out;
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
			'builder'     => array('efbBuilderApi.insertItems', 'efbBuilderApi.setForm', 'efbBuilderApi.addGroup', 'efbBuilderApi.moveIntoGroup', 'efbBuilderApi.moveOutOfGroup', 'efbBuilderApi.setGroup', 'efbBuilderApi.removeItems'),
			'builder_codes' => array('repeater_invalid', 'repeater_group', 'repeater_child_type', 'repeater_nested', 'repeater_cap', 'not_in_group'),
			'page_api'    => 'window.EFBRepeater (addRow, removeRow, rowsOf, fieldAt)',
			'schema'      => 'forms/{id}/schema: fields[].repeater on a group, fields[].group and fill.input "{id}_r{n}_" on its fields',
			'plan'        => array('group' => self::PLAN['group'], 'repeat' => self::PLAN['repeat']),
			'spec'        => 'F09',
		);
		return $out;
	}

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

	/** A number in the page language's digits (Persian, Arabic-Indic); the stored ri is always Latin. Twin: EFBRepeaterCore.digits(). */
	public static function digits($s, $locale = null) {
		return class_exists('\\Emsfb\\Form_Gate') && method_exists('\\Emsfb\\Form_Gate', 'locale_digits') ? Form_Gate::locale_digits((string) $s, $locale) : (string) $s;
	}

	/**
	 * The formula of a calc child in a row: {sibling} (and {field:sibling}, {sibling.part}) read the same row,
	 * i.e. the copy {sibling}{suffix}; other references are unchanged (F04 §6.4). Twin: EFBRepeaterCore.rewriteFormula().
	 */
	public static function rewrite_formula($formula, array $siblings, $suffix) {
		if (!is_string($formula) || $formula === '' || !$siblings) return (string) $formula;
		$set = array_flip(array_map('strval', $siblings));
		return preg_replace_callback('/\{(field:)?([A-Za-z0-9_-]{1,40})(\.[A-Za-z0-9_-]{1,40})?\}/', function ($m) use ($set, $suffix) {
			if (!isset($set[$m[2]])) return $m[0];
			return '{' . $m[1] . $m[2] . $suffix . (isset($m[3]) ? $m[3] : '') . '}';
		}, $formula);
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
				$t = function_exists('sanitize_text_field') ? sanitize_text_field($v) : trim(preg_replace('/<[^>]*>/', '', $v));
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

	/* ------------------------------------------------------------------ the submission (spec §9.2) */

	/** Keys of a child that do not apply in a row: one-time codes (F02) are not offered inside rows. */
	const ROW_DROP_KEYS = array('otp_enabled', 'otp_channel', 'otp_required_when', 'email_confirm_mode', 'email_otp_required_when');

	private static function msg($key, $lanText, $name, $n = null) {
		$t = self::t($key, $lanText);
		$esc = function ($v) { return function_exists('esc_html') ? esc_html($v) : htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
		$nm = '<b><bdi>' . $esc((string) $name) . '</bdi></b>';
		$num = '<b><bdi>' . $esc(self::digits((string) $n)) . '</bdi></b>';
		return strtr($t, array('%1$s' => $nm, '%2$s' => $num, '%s' => $nm));
	}

	/** The number a rows_from source asks for: a number / slider value, a choice's numeric stored value or its leading digits. */
	private static function count_from($fields, $rows, $src) {
		$f = null; $opts = array();
		foreach ($fields as $i => $r) {
			if ($i < 1 || !is_array($r)) continue;
			if (isset($r['id_']) && (string) $r['id_'] === $src) $f = $r;
			if (($r['type'] ?? '') === 'option' && isset($r['parent']) && (string) $r['parent'] === $src) $opts[] = $r;
		}
		if (!$f) return null;
		foreach ($rows as $row) {
			if (!is_array($row) || !isset($row['id_']) || (string) $row['id_'] !== $src || isset($row['ri'])) continue;
			$v = isset($row['value']) && is_scalar($row['value']) ? (string) $row['value'] : '';
			$ob = isset($row['id_ob']) && is_scalar($row['id_ob']) ? (string) $row['id_ob'] : '';
			if (in_array($f['type'] ?? '', array('select', 'radio'), true)) {
				foreach ($opts as $o) {
					if ((isset($o['id_']) && (string) $o['id_'] === $ob) || (isset($o['value']) && (string) $o['value'] === $v)) {
						$ov = isset($o['opt_value']) && is_scalar($o['opt_value']) ? trim(self::ascii_digits((string) $o['opt_value'])) : '';
						if (preg_match('/^\d{1,6}$/D', $ov)) return (int) $ov;
						$v = (string) ($o['value'] ?? $v);
						break;
					}
				}
			}
			return preg_match('/^\s*(\d{1,6})/', self::ascii_digits($v), $m) ? (int) $m[1] : 0;
		}
		return 0;
	}

	/**
	 * The answers of a form with repeater groups, made ready for the field loop (spec §9.2):
	 *  - each group's own row gives its number of rows, checked against [min, max] (the plan: one row without
	 *    repeating rows) and, with rows_from, against the source field's answer;
	 *  - a row of a child must carry ri (0 ≤ ri < rows); rg is the server's (the form says which group a field
	 *    belongs to); a row of a field that is not a child loses ri / rg and only its first row is kept; a row of a
	 *    child without ri, out of range, or of a group hidden by Conditional Logic is dropped;
	 *  - every child becomes, for each row, a copy {child}_r{ri} (options {option}_r{ri}) in the fields and in the
	 *    answers, so the field loop checks each row with the field's own rules.
	 * $opts: ignored (field ids hidden by Conditional Logic), ignored_rows ({child: [ri…]}), repeat_ok, group_ok, lanText.
	 * Returns ['ok', 'fields', 'rows', 'virtual' => {vid: [base, group, ri]}, 'ignored' => [vid…], 'counts' => {g: n},
	 * 'error' => ['m', 'field_id', 'row_index', 'code'] when not ok].
	 */
	public static function expand(array $fields, array $rows, array $opts = array()) {
		$ignored = isset($opts['ignored']) && is_array($opts['ignored']) ? $opts['ignored'] : array();
		$ignored_rows = isset($opts['ignored_rows']) && is_array($opts['ignored_rows']) ? $opts['ignored_rows'] : array();
		$repeat_ok = !isset($opts['repeat_ok']) || $opts['repeat_ok'];
		$group_ok = !isset($opts['group_ok']) || $opts['group_ok'];
		$lanText = isset($opts['lanText']) && is_array($opts['lanText']) ? $opts['lanText'] : array();
		$out = array('ok' => true, 'fields' => $fields, 'rows' => $rows, 'virtual' => array(), 'ignored' => array(), 'counts' => array(), 'error' => null);
		$map = self::children_map($fields);
		$child_of = array(); $opt_of = array(); $groups = array();
		foreach ($map as $g => $idx) foreach ($idx as $i) $child_of[(string) $fields[$i]['id_']] = $g;
		foreach ($fields as $i => $r) {
			if ($i < 1 || !is_array($r)) continue;
			if (self::is_group($r)) $groups[(string) $r['id_']] = $r;
			if (($r['type'] ?? '') === 'option' && isset($r['parent']) && isset($child_of[(string) $r['parent']])) $opt_of[(string) $r['parent']][] = $r;
		}
		// Rows of fields that are not children: no ri / rg, first row only.
		$kept = array(); $seen = array(); $child_rows = array(); $summary = array();
		foreach ($rows as $row) {
			if (!is_array($row)) { $kept[] = $row; continue; }
			$id = isset($row['id_']) && is_scalar($row['id_']) ? (string) $row['id_'] : '';
			if ($id !== '' && isset($groups[$id])) { if (!isset($summary[$id])) $summary[$id] = $row; continue; }
			if ($id !== '' && isset($child_of[$id])) { $child_rows[] = $row; continue; }
			if (array_key_exists('ri', $row) || array_key_exists('rg', $row)) {
				$k = $id . '|' . (isset($row['id_ob']) && is_scalar($row['id_ob']) ? (string) $row['id_ob'] : '');
				if (isset($seen[$k])) continue;
				$seen[$k] = true;
				unset($row['ri'], $row['rg']);
			}
			$kept[] = $row;
		}
		if (!$groups) { $out['rows'] = $kept; return $out; }
		$counts = array();
		foreach ($groups as $g => $gr) {
			if (!$group_ok || isset($ignored[$g])) { $counts[$g] = 0; continue; }
			$l = self::limits($gr, $repeat_ok);
			$max_ri = -1;
			foreach ($child_rows as $row) if (($child_of[(string) $row['id_']] ?? '') === $g && isset($row['ri']) && is_scalar($row['ri']) && preg_match('/^\d{1,4}$/D', (string) $row['ri'])) $max_ri = max($max_ri, (int) $row['ri']);
			if (isset($summary[$g])) {
				$v = isset($summary[$g]['value']) && is_scalar($summary[$g]['value']) ? trim((string) $summary[$g]['value']) : '';
				if (!preg_match('/^\d{1,4}$/D', $v)) { $out['ok'] = false; $out['error'] = array('m' => self::msg('rptMinRowsMsg', $lanText, $gr['name'] ?? '', $l['min']), 'field_id' => $g, 'row_index' => null, 'code' => self::CODES['min']); return $out; }
				$n = (int) $v;
			} else {
				$n = $max_ri + 1; // an API client that sent rows without the group's own row
			}
			if ($n < $l['min']) { $out['ok'] = false; $out['error'] = array('m' => self::msg('rptMinRowsMsg', $lanText, $gr['name'] ?? '', $l['min']), 'field_id' => $g, 'row_index' => null, 'code' => self::CODES['min']); return $out; }
			if ($n > $l['max']) { $out['ok'] = false; $out['error'] = array('m' => self::msg('rptMaxRowsMsg', $lanText, $gr['name'] ?? '', $l['max']), 'field_id' => $g, 'row_index' => null, 'code' => self::CODES['max']); return $out; }
			$from = $repeat_ok && isset($gr['rows_from']) && is_scalar($gr['rows_from']) ? (string) $gr['rows_from'] : '';
			if ($from !== '' && !isset($ignored[$from])) {
				$want = self::count_from($fields, $kept, $from);
				if ($want !== null) {
					$want = max($l['min'], min($l['max'], $want));
					if ($want !== $n) { $out['ok'] = false; $out['error'] = array('m' => self::msg('rptCountMismatch', $lanText, $gr['name'] ?? ''), 'field_id' => $g, 'row_index' => null, 'code' => self::CODES['count']); return $out; }
				}
			}
			$counts[$g] = $n;
		}
		// The rows of the children, under the copies' ids.
		$rewritten = array();
		foreach ($child_rows as $row) {
			$base = (string) $row['id_'];
			$g = $child_of[$base];
			if (!isset($row['ri']) || !is_scalar($row['ri']) || !preg_match('/^\d{1,4}$/D', (string) $row['ri'])) continue;
			$ri = (int) $row['ri'];
			if ($ri >= $counts[$g]) continue;
			$sfx = '_r' . $ri;
			$row['id_'] = $base . $sfx;
			if (isset($row['id_ob']) && is_scalar($row['id_ob'])) {
				$ob = (string) $row['id_ob'];
				$hit = false;
				foreach (isset($opt_of[$base]) ? $opt_of[$base] : array() as $o) if ((string) $o['id_'] === $ob) { $row['id_ob'] = $ob . $sfx; $hit = true; break; }
				if (!$hit && strpos($ob, $base) === 0) $row['id_ob'] = $base . $sfx . substr($ob, strlen($base));
			}
			if (isset($row['ids']) && is_string($row['ids']) && $row['ids'] !== '') {
				$row['ids'] = implode(',', array_map(function ($x) use ($sfx) { return $x === '' ? '' : $x . $sfx; }, explode(',', $row['ids'])));
			}
			unset($row['ri'], $row['rg']);
			$rewritten[] = $row;
		}
		// The fields, with the copies in place of the children.
		$new = array();
		$skip = array();
		foreach ($child_of as $cid => $g) $skip[$cid] = true;
		foreach ($fields as $i => $r) {
			if ($i > 0 && is_array($r)) {
				$id = isset($r['id_']) && is_scalar($r['id_']) ? (string) $r['id_'] : '';
				if (isset($skip[$id])) continue;
				if (($r['type'] ?? '') === 'option' && isset($r['parent']) && isset($skip[(string) $r['parent']])) continue;
			}
			$new[] = $r;
			if ($i < 1 || !self::is_group($r)) continue;
			$g = (string) $r['id_'];
			$kids = array();
			foreach ($map[$g] as $ci) $kids[] = (string) $fields[$ci]['id_'];
			for ($ri = 0; $ri < $counts[$g]; $ri++) {
				$sfx = '_r' . $ri;
				foreach ($map[$g] as $ci) {
					$c = $fields[$ci];
					$base = (string) $c['id_'];
					$c['id_'] = $base . $sfx;
					$c['dataId'] = $c['id_'] . '-id';
					$c['rpt_base'] = $base; $c['rpt_ri'] = $ri; $c['rpt_group'] = $g;
					unset($c['group']);
					foreach (self::ROW_DROP_KEYS as $dk) unset($c[$dk]);
					if (($c['type'] ?? '') === 'file') $c['max_files'] = '1';
					if (isset($c['formula']) && is_string($c['formula'])) $c['formula'] = self::rewrite_formula($c['formula'], $kids, $sfx);
					$hidden_row = isset($ignored[$base]) || (isset($ignored_rows[$base]) && is_array($ignored_rows[$base]) && in_array($ri, array_map('intval', $ignored_rows[$base]), true));
					if ($hidden_row) { $c['required'] = false; $out['ignored'][] = $c['id_']; }
					$new[] = $c;
					$out['virtual'][$c['id_']] = array($base, $g, $ri);
					foreach (isset($opt_of[$base]) ? $opt_of[$base] : array() as $o) {
						$oc = $o;
						$oc['id_'] = (string) $o['id_'] . $sfx;
						if (isset($oc['id_op'])) $oc['id_op'] = $oc['id_'];
						$oc['dataId'] = $oc['id_'] . '-id';
						$oc['parent'] = $c['id_'];
						$oc['rpt_base'] = (string) $o['id_']; $oc['rpt_ri'] = $ri; $oc['rpt_group'] = $g;
						$new[] = $oc;
						$out['virtual'][$oc['id_']] = array((string) $o['id_'], $g, $ri);
					}
				}
			}
		}
		// The groups' own rows, with the server's count.
		foreach ($groups as $g => $gr) {
			if (!$group_ok || isset($ignored[$g])) continue;
			$row = isset($summary[$g]) ? $summary[$g] : array('id_' => $g, 'name' => $gr['name'] ?? '', 'id_ob' => $g . '-f', 'type' => 'repeater');
			$row['value'] = (string) $counts[$g];
			unset($row['ri'], $row['rg']);
			$kept[] = $row;
		}
		// A row that names a copy itself ({child}_r{n} as id_ or id_ob) did not come through ri: it cannot smuggle an answer in.
		$kept = array_values(array_filter($kept, function ($row) use ($out) {
			if (!is_array($row)) return true;
			foreach (array('id_', 'id_ob') as $k) if (isset($row[$k]) && is_scalar($row[$k]) && isset($out['virtual'][(string) $row[$k]])) return false;
			return true;
		}));
		$out['fields'] = $new;
		$out['rows'] = array_merge($kept, $rewritten);
		$out['counts'] = $counts;
		return $out;
	}

	/** Base id and row of a copy's id (an error's field), or null. */
	public static function locate($vid, array $virtual) {
		$vid = (string) $vid;
		if (isset($virtual[$vid])) return array('field_id' => $virtual[$vid][0], 'row_index' => $virtual[$vid][2], 'group' => $virtual[$vid][1]);
		return null;
	}

	/** The checked answers back under the fields' own ids, with rg (group) and ri (row), in row order. Twin: EFBRepeaterCore.fold(). */
	public static function fold(array $items, array $virtual) {
		if (!$virtual) return $items;
		$prefix = function ($s) use ($virtual) {
			$s = (string) $s;
			if (isset($virtual[$s])) return $virtual[$s][0];
			if (preg_match('/^(.+?_r\d{1,4})(.*)$/D', $s, $m) && isset($virtual[$m[1]])) return $virtual[$m[1]][0] . $m[2];
			return $s;
		};
		foreach ($items as $k => $it) {
			if (!is_array($it) || !isset($it['id_']) || !is_scalar($it['id_']) || !isset($virtual[(string) $it['id_']])) continue;
			$v = $virtual[(string) $it['id_']];
			$it['id_'] = $v[0];
			if (isset($it['id_ob']) && is_scalar($it['id_ob'])) $it['id_ob'] = $prefix($it['id_ob']);
			if (isset($it['ids']) && is_string($it['ids']) && $it['ids'] !== '') $it['ids'] = implode(',', array_map($prefix, explode(',', $it['ids'])));
			$it['rg'] = $v[1];
			$it['ri'] = (string) $v[2];
			$items[$k] = $it;
		}
		return $items;
	}

	/** {group: {ri: {child: value}}} of stored rows (webhooks, assistants: Repeater::nest_rows). */
	public static function nest_rows(array $rows) {
		$out = array();
		foreach ($rows as $r) {
			if (!is_array($r) || !isset($r['rg'], $r['ri'], $r['id_'])) continue;
			$g = (string) $r['rg']; $ri = (int) $r['ri']; $c = (string) $r['id_'];
			$v = isset($r['value']) && is_scalar($r['value']) ? (string) $r['value'] : '';
			if (isset($out[$g][$ri][$c]) && $out[$g][$ri][$c] !== '') $out[$g][$ri][$c] .= '@efb!' . $v; else $out[$g][$ri][$c] = $v;
		}
		foreach ($out as $g => $rows_g) { ksort($rows_g); $out[$g] = array_values($rows_g); }
		return $out;
	}

	/* ------------------------------------------------------------------ Conditional Logic (spec §10) */

	const LOGIC_CHECK_TYPES = array('checkbox', 'paycheckbox', 'chlcheckbox', 'option');
	const LOGIC_RADIO_TYPES = array('radio', 'payradio', 'chlradio', 'imgradio', 'yesno');

	/** Whether a field is a repeater child (its value in a condition is the list of every row's value). */
	public static function is_list_field($structure, $field_id) {
		$cg = self::child_groups($structure);
		return isset($cg[(string) $field_id]);
	}

	/**
	 * The values a condition reads (spec §10.1), on top of a values map built from the answers: a group is its
	 * number of rows ("2"); a child read from outside its group is the list of every row's value (a condition is
	 * true when it holds for at least one row: "any"). Answers without rg / ri leave the map as it is.
	 */
	public static function logic_values($structure, $rows, $values) {
		if (!is_array($values) || !is_array($rows) || !self::has_groups($structure)) return $values;
		$cg = self::child_groups($structure);
		$types = array();
		foreach ((array) $structure as $i => $r) if ($i > 0 && is_array($r) && isset($r['id_'])) $types[(string) $r['id_']] = strtolower((string) ($r['type'] ?? ''));
		$lists = array();
		foreach ($rows as $row) {
			if (!is_array($row) || !isset($row['id_']) || !is_scalar($row['id_'])) continue;
			$id = (string) $row['id_'];
			if (isset($types[$id]) && $types[$id] === 'repeater') { $values[$id] = (string) (int) ($row['value'] ?? 0); continue; }
			if (!isset($cg[$id])) continue;
			$ri = isset($row['ri']) && is_scalar($row['ri']) ? (int) $row['ri'] : 0;
			$t = $types[$id] ?? strtolower((string) ($row['type'] ?? ''));
			$v = isset($row['value']) && is_scalar($row['value']) ? (string) $row['value'] : '';
			$ob = isset($row['id_ob']) && is_scalar($row['id_ob']) ? (string) $row['id_ob'] : '';
			if (in_array($t, self::LOGIC_CHECK_TYPES, true) || in_array($t, self::LOGIC_RADIO_TYPES, true)) $add = array($ob !== '' && $ob !== $id . '_' ? $ob : $v);
			elseif (strpos($t, 'multiselect') !== false) $add = array_values(array_filter(array_map('trim', explode('@efb!', $v)), 'strlen'));
			else $add = array($v);
			foreach ($add as $a) if ($a !== '') $lists[$id][$ri][] = $a;
		}
		foreach ($cg as $c => $g) {
			if (!isset($lists[$c])) { if (array_key_exists($c, $values) && !is_array($values[$c]) && self::rows_have_ri($rows)) $values[$c] = array(); continue; }
			ksort($lists[$c]);
			$flat = array();
			foreach ($lists[$c] as $vs) foreach ($vs as $v) $flat[] = $v;
			$values[$c] = $flat;
		}
		return $values;
	}

	private static function rows_have_ri($rows) {
		foreach ((array) $rows as $r) if (is_array($r) && isset($r['ri'])) return true;
		return false;
	}

	/**
	 * Children that a rule changes from a condition on another child of the same group ("in the same row").
	 * Until the Conditional Logic add-on says it evaluates rules per row (filter efb_logic_supports_repeater_rows),
	 * the server does not enforce "required" on these children (fail open, spec §10.3); their format is still checked.
	 */
	public static function row_scoped_targets($structure) {
		$out = array();
		if (!is_array($structure) || !isset($structure[0]['logic_rules']) || !is_array($structure[0]['logic_rules'])) return $out;
		$cg = self::child_groups($structure);
		if (!$cg) return $out;
		$conds = function ($group) use (&$conds) {
			$ids = array();
			foreach (isset($group['items']) && is_array($group['items']) ? $group['items'] : array() as $it) {
				if (!is_array($it)) continue;
				if (isset($it['items'])) $ids = array_merge($ids, $conds($it));
				elseif (isset($it['field_id']) && is_scalar($it['field_id'])) $ids[] = (string) $it['field_id'];
			}
			return $ids;
		};
		foreach ($structure[0]['logic_rules'] as $rule) {
			if (!is_array($rule) || empty($rule['enabled'])) continue;
			$cgroups = array();
			foreach ($conds(isset($rule['conditions']) && is_array($rule['conditions']) ? $rule['conditions'] : array()) as $fid) if (isset($cg[$fid])) $cgroups[$cg[$fid]] = true;
			if (!$cgroups) continue;
			foreach (isset($rule['actions']) && is_array($rule['actions']) ? $rule['actions'] : array() as $a) {
				$tg = is_array($a) && isset($a['target']) && is_scalar($a['target']) ? (string) $a['target'] : '';
				if ($tg !== '' && isset($cg[$tg]) && isset($cgroups[$cg[$tg]])) $out[$tg] = true;
			}
		}
		return array_keys($out);
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
