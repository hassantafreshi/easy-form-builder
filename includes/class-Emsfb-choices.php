<?php
/**
 * F05: choice fields (spec docs/feature-gaps/F05-choices.fa.md).
 *
 *  - the F05 keys and their save rules (sanitize_key, normalize_rows; browser twin EfbChoices.rules
 *    in public/assets/js/choices-efb.js, tests/test-choices-parity.js);
 *  - ready-made lists of "Add in bulk" (includes/data/choice-presets.php, admin AJAX efb_choice_presets_Emsfb);
 *  - the submit rules: the stored value of a choice (ov, set by the server only), the "Other" answer
 *    (plain text, length-limited, never priced), the minimum / maximum of a checkbox or multiselect,
 *    a ranking that must be an order of the saved choices;
 *  - the markup pieces of the page renderer (Other, Select all, sold-out and "N left" marks);
 *  - choices built from the site (posts, terms, users, a CSV attachment): only public content,
 *    expanded on the server for the page and again on submit (stable ids), cached in a transient;
 *  - forms/{id}/schema and the capability registry.
 * Per-choice capacity (atomic, emsfb_stock_) is \Emsfb\Stock.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Choices {

	const OPTION_KEYS = array('opt_value', 'op_lock', 'inv_cap');
	const PARENT_KEYS = array('op_random', 'op_other', 'op_other_label', 'op_other_max', 'op_select_all', 'minSelect', 'maxSelect',
		'inv_on', 'inv_show', 'inv_left', 'inv_release', 'dyn_src', 'dyn_post_type', 'dyn_tax', 'dyn_term', 'dyn_role',
		'dyn_orderby', 'dyn_order', 'dyn_limit', 'dyn_csv', 'rank_top', 'rank_touch');
	const TYPES = array(
		'random'     => array('radio', 'checkbox', 'select', 'multiselect', 'imgRadio', 'payRadio', 'payCheckbox', 'paySelect', 'payMultiselect', 'chlRadio', 'chlCheckBox', 'ranking'),
		'other'      => array('radio', 'checkbox', 'payRadio', 'payCheckbox', 'chlRadio', 'chlCheckBox'),
		'select_all' => array('checkbox', 'payCheckbox'),
		'minmax'     => array('checkbox', 'payCheckbox', 'chlCheckBox', 'multiselect', 'payMultiselect'),
		'inventory'  => array('radio', 'checkbox', 'select', 'multiselect', 'imgRadio', 'payRadio', 'payCheckbox', 'paySelect', 'payMultiselect'),
		'dynamic'    => array('radio', 'checkbox', 'select', 'multiselect'),
		'ranking'    => array('ranking'),
	);
	const FLAGS = array('op_lock', 'op_random', 'op_other', 'op_select_all', 'inv_on', 'inv_left', 'inv_release');
	const ENUMS = array(
		'inv_show'    => array('disable', 'hide', 'label'),
		'dyn_src'     => array('posts', 'terms', 'users', 'csv'),
		'dyn_orderby' => array('title', 'date', 'menu_order', 'name'),
		'dyn_order'   => array('ASC', 'DESC'),
	);
	const LIMITS = array('op_other_max' => array(1, 1000), 'minSelect' => array(0, 500), 'maxSelect' => array(0, 500), 'inv_cap' => array(0, 1000000), 'dyn_limit' => array(1, 500), 'rank_top' => array(0, 500), 'dyn_csv' => array(1, 2147483647));

	/** Plan of each part (spec §14; the owner may change it with the filter efb_choices_plan). */
	const PLAN = array('bulk' => 'free', 'value' => 'free', 'random' => 'free', 'other' => 'free', 'select_all' => 'free', 'minmax' => 'free',
		'inventory' => 'pro', 'dynamic' => 'pro', 'ranking' => 'free_plus');

	/** Stable error codes of the submit (SitePilot contract). */
	const CODES = array('other' => 'efb_choice_other', 'min' => 'efb_choice_min', 'max' => 'efb_choice_max', 'invalid' => 'efb_choice_invalid',
		'full' => 'efb_choice_full', 'expired' => 'efb_choice_expired', 'ranking' => 'efb_choice_ranking', 'stock' => 'efb_stock_unavailable');

	const DEFAULTS = array(
		'otherOpt' => 'Other', 'otherReq' => 'Please describe your “Other” answer for %s.', 'selectAll' => 'Select all',
		'minSelM_' => 'Choose at least %2$s options in %1$s.', 'maxSelM_' => 'Choose at most %2$s options in %1$s.',
		'soldOut' => 'Fully booked', 'invLeftN' => '%s left', 'rnkReq' => 'Please put the items in %s in order.',
		'bkXpM_' => 'We are sorry, the booking time for the %s option has expired. Please choose from the other available options.',
		'bkFlM_' => 'We are sorry, the %s option is currently at full capacity. Please choose from the other available options.',
		'mnvvXXX_' => 'Please enter valid value for the %s field.',
		'rnkUp' => 'Move %s up', 'rnkDown' => 'Move %s down', 'rnkConfirm' => 'Keep this order', 'rnkMoved' => '%1$s, position %2$s of %3$s',
	);

	const DYN_TTL = 600;
	const CSV_MAX_ROWS = 2000;
	const CSV_MAX_BYTES = 1048576;

	public static function register() {
		if (!function_exists('add_action')) return;
		add_action('wp_ajax_efb_choice_presets_Emsfb', array(__CLASS__, 'ajax_presets'));
		add_action('wp_ajax_efb_dyn_preview_Emsfb', array(__CLASS__, 'ajax_dyn_preview'));
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 26, 2);
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 25, 2);
		add_filter('efb_form_schema_field', array(__CLASS__, 'schema_field'), 12, 3);
		add_action('efb_form_saved', array(__CLASS__, 'flush_dynamic'), 10, 1);
		add_action('efb_form_created', array(__CLASS__, 'flush_dynamic'), 10, 1);
		add_action('efb_form_deleted', array(__CLASS__, 'flush_dynamic'), 10, 1);
		// A post, term or user that changes empties every cached list (the page shows the change at once).
		foreach (array('save_post', 'deleted_post', 'trashed_post', 'untrashed_post', 'created_term', 'edited_term', 'delete_term', 'profile_update', 'user_register', 'deleted_user', 'add_attachment', 'edit_attachment', 'delete_attachment') as $h) {
			add_action($h, array(__CLASS__, 'flush_all_dynamic'), 10, 0);
		}
	}

	/* ------------------------------------------------------------------ keys */

	public static function is_key($k) {
		return in_array($k, self::OPTION_KEYS, true) || in_array($k, self::PARENT_KEYS, true);
	}

	private static function plain($v, $max) {
		$s = function_exists('sanitize_text_field') ? sanitize_text_field((string) $v) : trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v)));
		$s = str_replace(array('"', '<', '>'), '', $s);
		if (function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') > $max) $s = trim(mb_substr($s, 0, $max, 'UTF-8'));
		return $s;
	}

	private static function ascii_digits($s) {
		return strtr((string) $s, array('۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'));
	}

	/** One F05 key as stored ('' = not set). Twin: EfbChoices.rules.sanitizeKey(). */
	public static function sanitize_key($k, $v) {
		if ($v === null || is_array($v) || is_object($v)) return '';
		if (in_array($k, self::FLAGS, true)) return in_array($v, array(true, 1, '1', 'true', 'on'), true) ? '1' : '';
		if ($k === 'rank_touch') {
			if (in_array($v, array(false, 0, '0', 'false'), true)) return '0';
			return $v === '' ? '' : '1';
		}
		if (isset(self::ENUMS[$k])) {
			$s = trim((string) $v);
			foreach (self::ENUMS[$k] as $e) if (strcasecmp($e, $s) === 0) return $e;
			return '';
		}
		if (isset(self::LIMITS[$k])) {
			$s = trim(self::ascii_digits((string) $v));
			if (!preg_match('/^\d{1,10}$/D', $s)) return '';
			return (string) min(self::LIMITS[$k][1], max(self::LIMITS[$k][0], (int) $s));
		}
		switch ($k) {
			case 'opt_value': return self::plain($v, 200);
			case 'op_other_label': return self::plain($v, 80);
			case 'dyn_post_type':
			case 'dyn_tax': return substr(preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $v)), 0, 40);
			case 'dyn_term':
			case 'dyn_role':
				$parts = array_filter(array_map(function ($x) { return preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($x))); }, explode(',', (string) $v)), 'strlen');
				return implode(',', array_slice(array_values($parts), 0, $k === 'dyn_term' ? 50 : 20));
		}
		return self::plain($v, 200);
	}

	/**
	 * After the field loop of the save: a stored value used twice inside one field is kept on the first
	 * choice only (the builder refuses it too, but the save does not trust the builder).
	 */
	public static function normalize_rows($rows) {
		if (!is_array($rows)) return $rows;
		$seen = array();
		foreach ($rows as $i => $r) {
			if (!is_array($r) || ($r['type'] ?? '') !== 'option' || !isset($r['opt_value']) || !is_string($r['opt_value']) || $r['opt_value'] === '') continue;
			$p = isset($r['parent']) && is_scalar($r['parent']) ? (string) $r['parent'] : '';
			$k = $p . "\x00" . $r['opt_value'];
			if (isset($seen[$k])) $rows[$i]['opt_value'] = '';
			else $seen[$k] = true;
		}
		return $rows;
	}

	public static function plan_ok($part) {
		$need = isset(self::PLAN[$part]) ? self::PLAN[$part] : 'free';
		if (function_exists('apply_filters')) $need = apply_filters('efb_choices_plan', $need, $part);
		if ($need === 'free') return true;
		if (function_exists('efb_plan_at_least_efb')) return (bool) efb_plan_at_least_efb($need);
		if (class_exists('\\Emsfb\\Builder_Api')) return (bool) Builder_Api::plan_at_least($need);
		return false;
	}

	public static function on($f, $k) {
		return is_array($f) && isset($f[$k]) && in_array($f[$k], array('1', 1, true, 'true'), true);
	}

	public static function int($f, $k) {
		if (!is_array($f) || !isset($f[$k]) || !is_scalar($f[$k])) return 0;
		$s = trim(self::ascii_digits((string) $f[$k]));
		return preg_match('/^\d{1,10}$/D', $s) ? (int) $s : 0;
	}

	public static function type_of($f) {
		return is_array($f) && isset($f['type']) && is_string($f['type']) ? $f['type'] : '';
	}

	public static function has($f, $part) {
		return isset(self::TYPES[$part]) && in_array(self::type_of($f), self::TYPES[$part], true);
	}

	/** The options of one field, in form order. */
	public static function options_of(array $rows, $parent) {
		$out = array();
		foreach ($rows as $i => $r) {
			if ($i > 0 && is_array($r) && ($r['type'] ?? '') === 'option' && isset($r['parent']) && (string) $r['parent'] === (string) $parent) $out[] = $r;
		}
		return $out;
	}

	/** The stored value of a choice: opt_value, or its label when none is set. */
	public static function stored_value($opt) {
		if (is_array($opt) && isset($opt['opt_value']) && is_scalar($opt['opt_value']) && (string) $opt['opt_value'] !== '') return (string) $opt['opt_value'];
		return is_array($opt) && isset($opt['value']) && is_scalar($opt['value']) ? (string) $opt['value'] : '';
	}

	/* ------------------------------------------------------------------ texts */

	public static function t($key, $lanText = array()) {
		if (is_array($lanText) && isset($lanText[$key]) && is_string($lanText[$key]) && $lanText[$key] !== '') return $lanText[$key];
		static $cache = array();
		if (!isset($cache[$key]) && function_exists('get_efbFunction')) {
			$fn = get_efbFunction();
			if ($fn && method_exists($fn, 'text_efb')) {
				$t = (array) $fn->text_efb(array($key));
				if (isset($t[$key]) && is_string($t[$key]) && $t[$key] !== '') $cache[$key] = $t[$key];
			}
		}
		return isset($cache[$key]) ? $cache[$key] : (isset(self::DEFAULTS[$key]) ? self::DEFAULTS[$key] : $key);
	}

	private static function esc($s) {
		return function_exists('esc_html') ? esc_html((string) $s) : htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}

	private static function attr($s) {
		return function_exists('esc_attr') ? esc_attr((string) $s) : htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	}

	/** The language's digits (Form_Gate's rule: Persian, Arabic-Indic, else Latin). */
	public static function digits($s) {
		return class_exists('\\Emsfb\\Form_Gate') && method_exists('\\Emsfb\\Form_Gate', 'locale_digits') ? Form_Gate::locale_digits((string) $s) : (string) $s;
	}

	/** A server sentence: the field label in bold (escaped, isolated with <bdi>), a number in the language's digits. */
	public static function message($key, $f, $lanText = array(), $n = null) {
		$t = self::t($key, $lanText);
		$name = '<b><bdi>' . self::esc(is_array($f) && isset($f['name']) ? $f['name'] : '') . '</bdi></b>';
		return strtr($t, array('%1$s' => $name, '%2$s' => '<b><bdi>' . self::esc(self::digits((string) $n)) . '</bdi></b>', '%s' => $name));
	}

	/* ------------------------------------------------------------------ ready-made lists (A) */

	public static function presets() {
		static $p = null;
		if ($p !== null) return $p;
		$f = __DIR__ . '/data/choice-presets.php';
		$p = is_readable($f) ? (array) include $f : array();
		return $p;
	}

	/** id => {lang, name_key, name, count}: the list of lists the builder shows (contents come by AJAX). */
	public static function preset_index() {
		$out = array();
		foreach (self::presets() as $id => $l) {
			$out[$id] = array('lang' => $l['lang'], 'name_key' => $l['name_key'], 'name' => $l['name'], 'count' => count($l['items']));
		}
		return $out;
	}

	/** The lines of one list in the "Add in bulk" syntax ("Label|value", a "|" in a text written "\|"). */
	public static function preset_lines($id) {
		$p = self::presets();
		if (!isset($p[$id])) return null;
		return implode("\n", array_map(function ($it) {
			$e = function ($s) { return str_replace('|', '\\|', (string) $s); };
			return $e($it[0]) . ($it[1] !== '' && $it[1] !== $it[0] ? '|' . $e($it[1]) : '');
		}, $p[$id]['items']));
	}

	/** efb_admin_localize_vars: efb_var.choices = {presets: index, ajax actions}. */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$vars['choices'] = array(
			'presets' => self::preset_index(),
			'plan'    => array('inventory' => self::plan_ok('inventory'), 'dynamic' => self::plan_ok('dynamic'), 'ranking' => self::plan_ok('ranking')),
			'calc'    => (bool) (function_exists('apply_filters') ? apply_filters('efb_choices_calc_column', false) : false),
		);
		return $vars;
	}

	private static function ajax_guard() {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		if (!check_ajax_referer('wp_rest', 'nonce', false) || !$fn || !$fn->user_permission_efb_admin_dashboard()) {
			wp_send_json_error(array('m' => 'Forbidden'), 403);
		}
	}

	/** AJAX efb_choice_presets_Emsfb (builder only): {id, lines, count, lang}. */
	public static function ajax_presets() {
		self::ajax_guard();
		$id = isset($_POST['preset']) ? sanitize_key(wp_unslash($_POST['preset'])) : '';
		$lines = self::preset_lines($id);
		if ($lines === null) wp_send_json_error(array('m' => 'Unknown list'), 404);
		$p = self::presets();
		wp_send_json_success(array('id' => $id, 'lines' => $lines, 'count' => count($p[$id]['items']), 'lang' => $p[$id]['lang']));
	}

	/* ------------------------------------------------------------------ submit (B, C) */

	/** "Other" chosen in a radio / checkbox field: id_ob is {field}_oth. */
	public static function is_other_row($f, $item) {
		return is_array($f) && isset($f['id_']) && is_array($item) && isset($item['id_ob']) && is_scalar($item['id_ob']) && (string) $item['id_ob'] === (string) $f['id_'] . '_oth';
	}

	/**
	 * The "Other" row of a field (spec §4.4, §9): only when the field allows it; plain text, at most
	 * op_other_max characters (200), no price, other = '1'. An empty text is refused (otherReq).
	 * @return array{ok:bool,row:array|null,m:string,code:string}
	 */
	public static function other_row($f, $item, $lanText = array()) {
		$out = array('ok' => false, 'row' => null, 'm' => '', 'code' => self::CODES['other']);
		if (!self::has($f, 'other') || !self::on($f, 'op_other')) {
			$out['m'] = self::message('mnvvXXX_', $f, $lanText);
			$out['code'] = self::CODES['invalid'];
			return $out;
		}
		$max = self::int($f, 'op_other_max');
		if ($max <= 0) $max = 200;
		$v = isset($item['value']) && is_scalar($item['value']) ? (string) $item['value'] : '';
		$v = function_exists('sanitize_text_field') ? sanitize_text_field($v) : trim(strip_tags($v));
		if (function_exists('mb_substr')) $v = trim(mb_substr($v, 0, $max, 'UTF-8'));
		if ($v === '') {
			$out['m'] = self::message('otherReq', $f, $lanText);
			return $out;
		}
		$row = array();
		foreach (array('id_', 'name', 'amount', 'session', 'form_id') as $k) if (isset($item[$k]) && is_scalar($item[$k])) $row[$k] = $item[$k];
		$row['id_'] = (string) $f['id_'];
		$row['id_ob'] = (string) $f['id_'] . '_oth';
		$row['type'] = (string) $f['type'];
		$row['value'] = $v;
		$row['other'] = '1';
		$out['ok'] = true;
		$out['row'] = $row;
		return $out;
	}

	/** Rows the server writes: a browser's ov / other / ids never reach storage as sent. */
	public static function strip_client_keys($item) {
		if (is_array($item)) unset($item['ov'], $item['other']);
		return $item;
	}

	/**
	 * ov = the stored value of the chosen choice(s), from the saved form only, and only when a choice of
	 * the field has its own opt_value (older forms keep rows exactly as before: value = label).
	 */
	public static function add_ov(array $items, array $rows) {
		$fields = array();
		$opts = array();
		foreach ($rows as $i => $r) {
			if ($i < 1 || !is_array($r) || !isset($r['id_'])) continue;
			if (($r['type'] ?? '') === 'option') { $opts[(string) ($r['parent'] ?? '')][(string) $r['id_']] = $r; continue; }
			$fields[(string) $r['id_']] = $r;
		}
		$has = function ($pid) use ($opts) {
			if (empty($opts[$pid])) return false;
			foreach ($opts[$pid] as $o) if (isset($o['opt_value']) && is_scalar($o['opt_value']) && (string) $o['opt_value'] !== '') return true;
			return false;
		};
		$by_text = function ($pid, $text) use ($opts) {
			if (empty($opts[$pid])) return null;
			$t = html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
			foreach ($opts[$pid] as $o) if (isset($o['value']) && is_scalar($o['value']) && html_entity_decode((string) $o['value'], ENT_QUOTES, 'UTF-8') === $t) return $o;
			return null;
		};
		foreach ($items as $k => $it) {
			if (!is_array($it)) continue;
			$pid = isset($it['id_']) && is_scalar($it['id_']) ? (string) $it['id_'] : '';
			$type = $pid !== '' && isset($fields[$pid]) ? (string) ($fields[$pid]['type'] ?? '') : '';
			if ($type === 'ranking') continue; // its ov is written by ranking_row()
			unset($items[$k]['ov']);
			if ($pid === '' || !isset($fields[$pid]) || !empty($it['other'])) continue;
			if (!$has($pid)) continue;
			$ob = isset($it['id_ob']) && is_scalar($it['id_ob']) ? (string) $it['id_ob'] : '';
			if ($ob !== '' && isset($opts[$pid][$ob])) { $items[$k]['ov'] = self::stored_value($opts[$pid][$ob]); continue; }
			if (in_array($type, array('multiselect', 'payMultiselect'), true)) {
				$parts = array_values(array_filter(explode('@efb!', (string) ($it['value'] ?? '')), 'strlen'));
				$vals = array();
				foreach ($parts as $p) { $o = $by_text($pid, $p); $vals[] = $o ? self::stored_value($o) : $p; }
				if ($vals) $items[$k]['ov'] = implode('@efb!', $vals) . '@efb!';
				continue;
			}
			$o = $by_text($pid, isset($it['value']) ? $it['value'] : '');
			if ($o) $items[$k]['ov'] = self::stored_value($o);
		}
		return $items;
	}

	/**
	 * Minimum / maximum of checkbox fields after the field loop (multiselect is checked in its branch):
	 * null, or ['m' => sentence, 'field_id' => id, 'code' => …]. A field Conditional Logic hid is not checked.
	 */
	public static function count_check(array $rows, array $items, array $ignored = array(), $lanText = array()) {
		$count = array();
		foreach ($items as $it) {
			if (!is_array($it) || !isset($it['id_'], $it['id_ob']) || !is_scalar($it['id_'])) continue;
			$count[(string) $it['id_']] = (isset($count[(string) $it['id_']]) ? $count[(string) $it['id_']] : 0) + 1;
		}
		foreach ($rows as $i => $f) {
			if ($i < 1 || !is_array($f) || !isset($f['id_']) || !in_array(self::type_of($f), array('checkbox', 'payCheckbox', 'chlCheckBox'), true)) continue;
			$id = (string) $f['id_'];
			if (isset($ignored[$id])) continue;
			$n = isset($count[$id]) ? $count[$id] : 0;
			$r = self::limit_check($f, $n, $lanText);
			if ($r) return $r;
		}
		return null;
	}

	/** One field's count against minSelect / maxSelect (0 or missing = no limit; the minimum only when something was chosen). */
	public static function limit_check($f, $n, $lanText = array()) {
		$min = self::int($f, 'minSelect');
		$max = self::int($f, 'maxSelect');
		if ($max > 0 && $n > $max) return array('m' => self::message('maxSelM_', $f, $lanText, $max), 'field_id' => (string) $f['id_'], 'code' => self::CODES['max']);
		if ($min > 0 && $n > 0 && $n < $min) return array('m' => self::message('minSelM_', $f, $lanText, $min), 'field_id' => (string) $f['id_'], 'code' => self::CODES['min']);
		return null;
	}

	/**
	 * A ranking answer (spec §9 / G): ids must be distinct choices of the saved field, all of them in some
	 * order (or exactly the first rank_top). value and ov are rebuilt from the saved form.
	 * @return array{ok:bool,row:array|null,m:string,code:string}
	 */
	public static function ranking_row($f, $item, array $rows, $lanText = array()) {
		$bad = array('ok' => false, 'row' => null, 'm' => self::message('rnkReq', $f, $lanText), 'code' => self::CODES['ranking']);
		$opts = array();
		foreach (self::options_of($rows, $f['id_']) as $o) $opts[(string) $o['id_']] = $o;
		$ids = isset($item['ids']) && is_scalar($item['ids']) ? array_values(array_filter(array_map('trim', explode(',', (string) $item['ids'])), 'strlen')) : array();
		if (!$ids || count($ids) !== count(array_unique($ids))) return $bad;
		foreach ($ids as $id) if (!isset($opts[$id])) return $bad;
		$top = self::int($f, 'rank_top');
		$need = $top > 0 ? min($top, count($opts)) : count($opts);
		if (count($ids) !== $need) return $bad;
		$row = array();
		foreach (array('name', 'amount', 'session', 'form_id') as $k) if (isset($item[$k]) && is_scalar($item[$k])) $row[$k] = $item[$k];
		$row['id_'] = (string) $f['id_'];
		$row['id_ob'] = (string) $f['id_'] . '_';
		$row['type'] = 'ranking';
		$labels = array();
		$vals = array();
		foreach ($ids as $id) { $labels[] = (string) $opts[$id]['value']; $vals[] = self::stored_value($opts[$id]); }
		$row['value'] = implode('@efb!', $labels) . '@efb!';
		$row['ov'] = implode('@efb!', $vals) . '@efb!';
		$row['ids'] = implode(',', $ids);
		return array('ok' => true, 'row' => $row, 'm' => '', 'code' => '');
	}

	/* ------------------------------------------------------------------ page markup (C, E) */

	/**
	 * What the PHP renderer adds to a radio / checkbox family field: ['wrap' => attributes of {id}_options,
	 * 'before' => Select all, 'after' => Other, 'opt' => [option id => ['attr' => …, 'class' => …, 'disabled' => bool,
	 * 'hidden' => bool, 'suffix' => html]]].
	 */
	public static function render_parts($f, array $options, $form_id, $stock_left = array()) {
		$id = (string) $f['id_'];
		$type = self::type_of($f);
		$radio = strpos(strtolower($type), 'radio') !== false;
		$parts = array('wrap' => ' role="' . ($radio ? 'radiogroup' : 'group') . '" aria-labelledby="' . self::attr($id) . '_labG"', 'before' => '', 'after' => '', 'opt' => array());
		if (self::on($f, 'op_random') && self::has($f, 'random')) $parts['wrap'] .= ' data-random="1"';
		$max = self::int($f, 'maxSelect');
		if (self::has($f, 'minmax') && $max > 0) $parts['wrap'] .= ' data-max="' . $max . '"';
		if (self::has($f, 'minmax') && self::int($f, 'minSelect') > 0) $parts['wrap'] .= ' data-min="' . self::int($f, 'minSelect') . '"';
		$inv = Stock::tracked($f);
		$mode = isset($f['inv_show']) && in_array($f['inv_show'], self::ENUMS['inv_show'], true) ? $f['inv_show'] : 'disable';
		foreach ($options as $o) {
			$oid = (string) $o['id_'];
			$p = array('attr' => '', 'class' => '', 'disabled' => false, 'hidden' => false, 'suffix' => '');
			if (self::on($o, 'op_lock')) $p['attr'] .= ' data-lock="1"';
			if ($inv && array_key_exists($oid, $stock_left)) {
				$left = (int) $stock_left[$oid];
				$p['attr'] .= ' data-left="' . $left . '"';
				if ($left <= 0) {
					$p['class'] = ' efb-soldout';
					$p['disabled'] = true;
					$p['hidden'] = $mode === 'hide';
					$p['suffix'] = '<span class="efb efb-ch-left fs-7 text-muted mx-1" dir="auto">' . self::esc(self::t('soldOut')) . '</span>';
				} elseif (self::on($f, 'inv_left')) {
					$p['suffix'] = '<span class="efb efb-ch-left fs-7 text-muted mx-1" dir="auto">' . self::esc(str_replace('%s', self::digits((string) $left), self::t('invLeftN'))) . '</span>';
				}
			}
			$parts['opt'][$oid] = $p;
		}
		$col = isset($f['op_style']) && (int) $f['op_style'] > 1 && (int) $f['op_style'] <= 3 ? 'col-md-' . (12 / (int) $f['op_style']) : '';
		$disabled = !empty($f['disabled']) && $f['disabled'] != '0' ? ' disabled' : '';
		// The same look as the field's own choices (text colour, height, size).
		$lab_cls = '';
		foreach (array('el_text_color', 'el_height', 'label_text_size') as $k) if (isset($f[$k]) && is_string($f[$k]) && preg_match('/^[A-Za-z0-9_ -]{1,60}$/D', $f[$k])) $lab_cls .= ' ' . $f[$k];
		$in_cls = isset($f['el_text_size']) && is_string($f['el_text_size']) && preg_match('/^[A-Za-z0-9_-]{1,30}$/D', $f['el_text_size']) ? ' ' . $f['el_text_size'] : '';
		if (self::has($f, 'select_all') && self::on($f, 'op_select_all') && !($max > 0 && $max < count($options))) {
			$ctl = implode(' ', array_map(function ($o) { return (string) $o['id_']; }, $options));
			$parts['before'] = '<div class="efb form-check efb-selall ' . $col . ' mt-1" id="' . self::attr($id) . '_all-v">'
				. '<input class="efb form-check-input efb-selall-in' . $in_cls . '" type="checkbox" id="' . self::attr($id) . '_all" data-formid="' . (int) $form_id . '" aria-controls="' . self::attr($ctl) . '" aria-checked="false"' . $disabled . '>'
				. '<label class="efb form-check-label hStyleOpEfb' . $lab_cls . '" for="' . self::attr($id) . '_all">' . self::esc(self::t('selectAll')) . '</label></div>';
		}
		if (self::has($f, 'other') && self::on($f, 'op_other')) {
			$label = isset($f['op_other_label']) && is_string($f['op_other_label']) && trim($f['op_other_label']) !== '' ? $f['op_other_label'] : self::t('otherOpt');
			$max_len = self::int($f, 'op_other_max');
			if ($max_len <= 0) $max_len = 200;
			$itype = $radio ? 'radio' : 'checkbox';
			$parts['after'] = '<div class="efb form-check efb-other ' . $col . ' mt-1" data-parent="' . self::attr($id) . '" data-id="' . self::attr($id) . '_oth" data-lock="1" data-formid="' . (int) $form_id . '" id="' . self::attr($id) . '_oth-v">'
				. '<input class="efb form-check-input emsFormBuilder_v' . $in_cls . '" data-tag="' . self::attr($type) . '" data-type="' . self::attr($itype) . '" data-vid="' . self::attr($id) . '" type="' . $itype . '" name="' . self::attr($id) . '" value="" id="' . self::attr($id) . '_oth" data-id="' . self::attr($id) . '_oth-id" data-formid="' . (int) $form_id . '" data-op="' . self::attr($id) . '_oth"' . $disabled . '>'
				. '<label class="efb form-check-label hStyleOpEfb' . $lab_cls . '" id="' . self::attr($id) . '_oth_lab" for="' . self::attr($id) . '_oth">' . self::esc($label) . '</label>'
				. '<input type="text" class="efb form-control efb-other-t border-d rounded-3 mt-1" id="' . self::attr($id) . '_oth_t" data-formid="' . (int) $form_id . '" dir="auto" maxlength="' . $max_len . '" aria-labelledby="' . self::attr($id) . '_oth_lab" disabled aria-disabled="true" autocomplete="off">'
				. '</div>';
		}
		return $parts;
	}

	/* ------------------------------------------------------------------ dynamic choices (F) */

	public static function dyn_active($f) {
		return self::has($f, 'dynamic') && isset($f['dyn_src']) && in_array($f['dyn_src'], self::ENUMS['dyn_src'], true) && self::plan_ok('dynamic');
	}

	/**
	 * Adds the choices of every field with dyn_src (rows of type option, marked dyn = '1'); a field's typed
	 * choices are left out while it has a source. Same ids on every expansion (p{ID}, t{term_id}, u{ID},
	 * c{md5(value)[0..8]}), so the page and the submit agree.
	 */
	public static function expand_dynamic(array $rows, $form_id = 0, $fresh = false) {
		$has = false;
		foreach ($rows as $i => $r) if ($i > 0 && is_array($r) && self::dyn_active($r)) { $has = true; break; }
		if (!$has) return $rows;
		$out = array();
		$dyn_parents = array();
		foreach ($rows as $i => $r) {
			if ($i > 0 && is_array($r) && self::dyn_active($r)) $dyn_parents[(string) $r['id_']] = $r;
		}
		foreach ($rows as $i => $r) {
			if ($i > 0 && is_array($r) && ($r['type'] ?? '') === 'option' && isset($r['parent']) && isset($dyn_parents[(string) $r['parent']])) continue;
			$out[] = $r;
			if ($i > 0 && is_array($r) && isset($r['id_']) && isset($dyn_parents[(string) $r['id_']])) {
				foreach (self::dynamic_items($r, $form_id, $fresh) as $it) {
					$out[] = array('id_' => $it['id'], 'dataId' => $it['id'] . '-id', 'parent' => (string) $r['id_'], 'type' => 'option', 'value' => $it['label'],
						'opt_value' => $it['value'], 'id_op' => $it['id'], 'step' => isset($r['step']) ? $r['step'] : 1, 'amount' => isset($r['amount']) ? $r['amount'] : 0, 'dyn' => '1');
				}
			}
		}
		return $out;
	}

	/** Cache key of one field's list (the arguments that decide it, and the form). */
	public static function dyn_key($f, $form_id) {
		$args = array();
		foreach (array('dyn_src', 'dyn_post_type', 'dyn_tax', 'dyn_term', 'dyn_role', 'dyn_orderby', 'dyn_order', 'dyn_limit', 'dyn_csv') as $k) $args[$k] = isset($f[$k]) && is_scalar($f[$k]) ? (string) $f[$k] : '';
		return 'efb_dyn_' . (int) $form_id . '_' . substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $f['id_']), 0, 40) . '_' . substr(md5(json_encode($args)), 0, 12);
	}

	/** [{id, label, value}] of one dynamic field (cached ten minutes; filter efb_dynamic_choices). */
	public static function dynamic_items($f, $form_id = 0, $nocache = false) {
		$key = self::dyn_key($f, $form_id);
		if (!$nocache && function_exists('get_transient')) {
			$c = get_transient($key);
			if (is_array($c)) return $c;
		}
		$items = self::dynamic_build($f);
		if (function_exists('apply_filters')) {
			$items = apply_filters('efb_dynamic_choices', $items, $f, (int) $form_id);
			$clean = array();
			foreach ((array) $items as $it) {
				if (!is_array($it) || !isset($it['label'])) continue;
				$label = self::plain($it['label'], 200);
				if ($label === '') continue;
				$value = isset($it['value']) ? self::plain($it['value'], 200) : $label;
				$id = isset($it['id']) && preg_match('/^[A-Za-z0-9_-]{1,60}$/D', (string) $it['id']) ? (string) $it['id'] : (string) $f['id_'] . '_c' . substr(md5($value), 0, 8);
				$clean[] = array('id' => $id, 'label' => $label, 'value' => $value);
			}
			$items = $clean;
		}
		if (function_exists('set_transient')) {
			set_transient($key, $items, self::DYN_TTL);
			$index = get_option('efb_dyn_keys', array());
			if (!is_array($index)) $index = array();
			$index[(int) $form_id][$key] = 1;
			update_option('efb_dyn_keys', $index, false);
		}
		return $items;
	}

	/** The source itself (no cache): only public, published content; users by display name only. */
	public static function dynamic_build($f) {
		$src = isset($f['dyn_src']) ? (string) $f['dyn_src'] : '';
		$fid = (string) $f['id_'];
		$limit = self::int($f, 'dyn_limit');
		if ($limit <= 0) $limit = 100;
		$limit = min(500, $limit);
		$order = isset($f['dyn_order']) && $f['dyn_order'] === 'DESC' ? 'DESC' : 'ASC';
		$orderby = isset($f['dyn_orderby']) && in_array($f['dyn_orderby'], self::ENUMS['dyn_orderby'], true) ? $f['dyn_orderby'] : 'title';
		$out = array();
		$seen = array();
		$add = function ($id, $label, $value) use (&$out, &$seen) {
			$label = self::plain(function_exists('wp_specialchars_decode') ? wp_specialchars_decode((string) $label, ENT_QUOTES) : (string) $label, 200);
			$value = self::plain((string) $value, 200);
			if ($label === '' || $value === '' || isset($seen[$id])) return;
			$seen[$id] = true;
			$out[] = array('id' => $id, 'label' => $label, 'value' => $value);
		};
		switch ($src) {
			case 'posts':
				if (!function_exists('get_posts')) return array();
				$pt = isset($f['dyn_post_type']) && $f['dyn_post_type'] !== '' ? (string) $f['dyn_post_type'] : 'post';
				$public = function_exists('get_post_types') ? (array) get_post_types(array('public' => true)) : array('post' => 'post', 'page' => 'page');
				if (!isset($public[$pt]) || $pt === 'attachment') return array();
				$q = array('post_type' => $pt, 'post_status' => 'publish', 'has_password' => false, 'numberposts' => $limit, 'orderby' => $orderby === 'name' ? 'name' : $orderby, 'order' => $order, 'suppress_filters' => false, 'no_found_rows' => true);
				$tax = isset($f['dyn_tax']) ? (string) $f['dyn_tax'] : '';
				$terms = isset($f['dyn_term']) && $f['dyn_term'] !== '' ? explode(',', (string) $f['dyn_term']) : array();
				if ($tax !== '' && $terms && self::public_taxonomy($tax)) {
					$numeric = array_filter($terms, 'ctype_digit');
					$q['tax_query'] = array(array('taxonomy' => $tax, 'field' => count($numeric) === count($terms) ? 'term_id' : 'slug', 'terms' => count($numeric) === count($terms) ? array_map('intval', $terms) : $terms));
				}
				foreach ((array) get_posts($q) as $p) {
					if (!is_object($p) || ($p->post_status ?? '') !== 'publish' || !empty($p->post_password)) continue;
					$add($fid . '_p' . (int) $p->ID, function_exists('get_the_title') ? get_the_title($p) : $p->post_title, (string) (int) $p->ID);
				}
				break;
			case 'terms':
				if (!function_exists('get_terms')) return array();
				$tax = isset($f['dyn_tax']) && $f['dyn_tax'] !== '' ? (string) $f['dyn_tax'] : 'category';
				if (!self::public_taxonomy($tax)) return array();
				$terms = get_terms(array('taxonomy' => $tax, 'hide_empty' => true, 'number' => $limit, 'orderby' => $orderby === 'title' ? 'name' : ($orderby === 'date' ? 'term_id' : 'name'), 'order' => $order));
				if (is_wp_error($terms)) return array();
				foreach ((array) $terms as $t) if (is_object($t)) $add($fid . '_t' . (int) $t->term_id, $t->name, $t->slug);
				break;
			case 'users':
				if (!function_exists('get_users')) return array();
				$q = array('number' => $limit, 'fields' => array('ID', 'display_name'), 'orderby' => 'display_name', 'order' => $order);
				$roles = isset($f['dyn_role']) && $f['dyn_role'] !== '' ? explode(',', (string) $f['dyn_role']) : array();
				if ($roles) $q['role__in'] = $roles;
				foreach ((array) get_users($q) as $u) if (is_object($u)) $add($fid . '_u' . (int) $u->ID, $u->display_name, (string) (int) $u->ID);
				break;
			case 'csv':
				foreach (self::csv_rows(self::int($f, 'dyn_csv')) as $r) $add($fid . '_c' . substr(md5($r[1]), 0, 8), $r[0], $r[1]);
				break;
		}
		return array_slice($out, 0, $src === 'csv' ? self::CSV_MAX_ROWS : $limit);
	}

	private static function public_taxonomy($tax) {
		if (!function_exists('get_taxonomy')) return false;
		$t = get_taxonomy($tax);
		return is_object($t) && !empty($t->public);
	}

	/**
	 * [[label, value], …] of a CSV attachment the owner uploaded (never a URL): text/csv, at most 1 MB and
	 * 2000 rows, UTF-8 (a BOM removed), columns label,value[,calc][,group]; a value in Persian or Arabic-Indic
	 * digits is read as the same digits in Latin. A header row "label,value" is skipped.
	 */
	public static function csv_rows($attachment_id) {
		if ($attachment_id <= 0 || !function_exists('get_attached_file')) return array();
		if (function_exists('get_post_mime_type') && !in_array((string) get_post_mime_type($attachment_id), array('text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'), true)) return array();
		$path = get_attached_file($attachment_id);
		if (!is_string($path) || !is_readable($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'csv' || filesize($path) > self::CSV_MAX_BYTES) return array();
		$raw = (string) file_get_contents($path);
		return self::parse_csv($raw);
	}

	public static function parse_csv($raw) {
		$raw = preg_replace('/^\xEF\xBB\xBF/', '', (string) $raw);
		if (function_exists('mb_check_encoding') && !mb_check_encoding($raw, 'UTF-8')) return array();
		$out = array();
		$lines = preg_split('/\r\n|\r|\n/', $raw);
		foreach ($lines as $n => $line) {
			if (trim($line) === '') continue;
			$cols = str_getcsv($line, ',', '"', '\\');
			$label = isset($cols[0]) ? trim((string) $cols[0]) : '';
			$value = isset($cols[1]) && trim((string) $cols[1]) !== '' ? trim(self::ascii_digits((string) $cols[1])) : $label;
			if ($n === 0 && strtolower($label) === 'label') continue;
			if ($label === '') continue;
			$out[] = array($label, $value);
			if (count($out) >= self::CSV_MAX_ROWS) break;
		}
		return $out;
	}

	/** efb_form_saved / created / deleted: the cached lists of the form are dropped (also for saves through the API). */
	public static function flush_dynamic($form_id) {
		if (!function_exists('get_option')) return;
		$index = get_option('efb_dyn_keys', array());
		if (!is_array($index) || empty($index[(int) $form_id])) return;
		foreach (array_keys($index[(int) $form_id]) as $k) delete_transient($k);
		unset($index[(int) $form_id]);
		update_option('efb_dyn_keys', $index, false);
	}

	/** Every cached list of every form (a post, term or user changed). */
	public static function flush_all_dynamic() {
		if (!function_exists('get_option')) return;
		$index = get_option('efb_dyn_keys', array());
		if (!is_array($index) || !$index) return;
		foreach ($index as $keys) foreach (array_keys((array) $keys) as $k) delete_transient($k);
		update_option('efb_dyn_keys', array(), false);
	}

	/** AJAX efb_dyn_preview_Emsfb (builder): the first 50 choices of a source, plus the public post types and taxonomies. */
	public static function ajax_dyn_preview() {
		self::ajax_guard();
		$f = array('id_' => 'preview', 'type' => 'select');
		foreach (array('dyn_src', 'dyn_post_type', 'dyn_tax', 'dyn_term', 'dyn_role', 'dyn_orderby', 'dyn_order', 'dyn_limit', 'dyn_csv') as $k) {
			if (isset($_POST[$k]) && is_scalar($_POST[$k])) $f[$k] = self::sanitize_key($k, wp_unslash($_POST[$k]));
		}
		$items = isset($f['dyn_src']) && $f['dyn_src'] !== '' ? array_slice(self::dynamic_build($f), 0, 50) : array();
		$types = array();
		foreach ((array) get_post_types(array('public' => true), 'objects') as $k => $o) if ($k !== 'attachment') $types[] = array('id' => $k, 'label' => isset($o->labels->singular_name) ? $o->labels->singular_name : $k);
		$taxes = array();
		foreach ((array) get_taxonomies(array('public' => true), 'objects') as $k => $o) $taxes[] = array('id' => $k, 'label' => isset($o->labels->singular_name) ? $o->labels->singular_name : $k);
		$roles = array();
		if (function_exists('wp_roles')) foreach ((array) wp_roles()->get_names() as $k => $n) $roles[] = array('id' => $k, 'label' => translate_user_role($n));
		wp_send_json_success(array('items' => $items, 'count' => count($items), 'post_types' => $types, 'taxonomies' => $taxes, 'roles' => $roles, 'plan' => self::plan_ok('dynamic')));
	}

	/* ------------------------------------------------------------------ schema and registry */

	/** forms/{id}/schema: choices with id, label and stored value; limits; remaining stock only when the owner shows it. */
	public static function schema_field($e, $r, $form_id) {
		if (!is_array($e) || !is_array($r) || !isset($r['type'])) return $e;
		$type = (string) $r['type'];
		if (!in_array($type, array_unique(array_merge(self::TYPES['inventory'], self::TYPES['other'], self::TYPES['ranking'], array('chlRadio', 'chlCheckBox'))), true)) return $e;
		$rows = self::schema_rows((int) $form_id);
		$left = Stock::tracked($r) && self::on($r, 'inv_left') ? Stock::left((int) $form_id, $rows) : array();
		$opts = array();
		foreach (self::options_of($rows, $r['id_']) as $o) {
			$x = array('id' => (string) $o['id_'], 'label' => (string) ($o['value'] ?? ''), 'value' => self::stored_value($o));
			if (array_key_exists((string) $o['id_'], $left)) { $x['left'] = (int) $left[(string) $o['id_']]; $x['available'] = $x['left'] > 0; }
			$opts[] = $x;
		}
		$e['options'] = $opts;
		$c = array();
		if (self::on($r, 'op_random') && self::has($r, 'random')) $c['random'] = true;
		if (self::on($r, 'op_other') && self::has($r, 'other')) $c['other'] = array('id' => (string) $r['id_'] . '_oth', 'max' => self::int($r, 'op_other_max') ?: 200, 'price' => false);
		if (self::has($r, 'minmax')) {
			if (self::int($r, 'minSelect') > 0) $c['min'] = self::int($r, 'minSelect');
			if (self::int($r, 'maxSelect') > 0) $c['max'] = self::int($r, 'maxSelect');
		}
		if (Stock::tracked($r)) $c['inventory'] = array('show_left' => self::on($r, 'inv_left'), 'when_full' => isset($r['inv_show']) && $r['inv_show'] !== '' ? $r['inv_show'] : 'disable');
		if (self::dyn_active($r)) $c['dynamic'] = array('source' => (string) $r['dyn_src'], 'note' => 'the list comes from the site; the server checks the choice again on submit');
		if ($type === 'ranking') $c['ranking'] = array('top' => self::int($r, 'rank_top'), 'answer' => 'ids: the choice ids in order, joined with ","');
		if ($c) $e['choices'] = $c;
		$e['set_value'] = 'a choice id, its label or its stored value';
		return $e;
	}

	private static function schema_rows($form_id) {
		static $cache = array();
		if (isset($cache[$form_id])) return $cache[$form_id];
		$rows = class_exists('\\Emsfb\\Payments\\Service') ? Payments\Service::form_rows($form_id) : null;
		if (!is_array($rows)) $rows = array();
		return $cache[$form_id] = self::expand_dynamic($rows, $form_id);
	}

	/** efb_capabilities: the F05 entries (proposed plans of spec §14) and capabilities.choices. */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out) || !isset($out['features']) || !is_array($out['features'])) return $out;
		$package = isset($out['plan']['package_type']) ? (int) $out['plan']['package_type'] : null;
		$entries = array(
			'setting:choice_bulk'       => array('bulk', array('options[]'), 'bulkAdd'),
			'setting:choice_value'      => array('value', array('option.opt_value'), 'optValue'),
			'setting:choice_random'     => array('random', array('op_random', 'option.op_lock'), 'opRandom'),
			'setting:choice_other'      => array('other', array('op_other', 'op_other_label', 'op_other_max'), 'otherAllow'),
			'setting:choice_select_all' => array('select_all', array('op_select_all'), 'selectAll'),
			'setting:choice_limits'     => array('minmax', array('minSelect', 'maxSelect'), 'maxSelect'),
			'setting:choice_inventory'  => array('inventory', array('inv_on', 'inv_show', 'inv_left', 'inv_release', 'option.inv_cap'), 'invTrack'),
			'setting:choice_dynamic'    => array('dynamic', array('dyn_src', 'dyn_post_type', 'dyn_tax', 'dyn_term', 'dyn_role', 'dyn_orderby', 'dyn_order', 'dyn_limit', 'dyn_csv'), 'dynSource'),
		);
		foreach ($entries as $key => $e) {
			$need = isset(self::PLAN[$e[0]]) ? self::PLAN[$e[0]] : 'free';
			if (function_exists('apply_filters')) $need = apply_filters('efb_choices_plan', $need, $e[0]);
			$ok = class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($need, $package) : true;
			$out['features'][$key] = array(
				'kind' => 'setting', 'scope' => 'field', 'package' => $need, 'addon' => null, 'available_now' => $ok, 'reason' => $ok ? null : 'plan',
				'prompt_state' => $need === 'free_plus' ? 3 : ($need === 'pro' ? 1 : 0),
				'message_key' => $need === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : ($need === 'pro' ? 'proUnlockMsg' : null),
				'form_keys' => $e[1], 'label_key' => $e[2],
			);
		}
		$need = isset(self::PLAN['ranking']) ? self::PLAN['ranking'] : 'free_plus';
		if (function_exists('apply_filters')) $need = apply_filters('efb_choices_plan', $need, 'ranking');
		$ok = class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::plan_at_least($need, $package) : true;
		$out['features']['field:ranking'] = array_merge(isset($out['features']['field:ranking']) && is_array($out['features']['field:ranking']) ? $out['features']['field:ranking'] : array(), array(
			'kind' => 'field', 'package' => $need, 'addon' => null, 'available_now' => $ok, 'reason' => $ok ? null : 'plan',
			'prompt_state' => $need === 'free_plus' ? 3 : 1, 'message_key' => $need === 'free_plus' ? 'thisFeatureAvailableFreePlusPro' : 'proUnlockMsg',
			'label_key' => 'ranking', 'pii_default' => false, 'form_keys' => array('rank_top', 'rank_touch', 'op_random'),
		));
		$out['choices'] = array(
			'option_keys' => self::OPTION_KEYS,
			'field_keys'  => self::PARENT_KEYS,
			'types'       => self::TYPES,
			'limits'      => array('opt_value' => 200, 'op_other_label' => 80, 'op_other_max' => self::LIMITS['op_other_max'], 'dyn_limit' => self::LIMITS['dyn_limit'], 'bulk_lines' => 500, 'csv_rows' => self::CSV_MAX_ROWS),
			'enums'       => self::ENUMS,
			'presets'     => array_keys(self::presets()),
			'answer'      => array('ov' => 'written by the server (the stored value of the chosen choice); never sent', 'other' => '{field}_oth, other = "1", never priced', 'ranking' => 'value = labels joined with @efb!, ov = stored values, ids = choice ids joined with ","'),
			'codes'       => array_values(self::CODES),
			'builder'     => array('efbBuilderApi.insertItems', 'efbBuilderApi.setForm', 'efbBuilderApi.setOptions', 'efbBuilderApi.setChoices'),
			'rest'        => array('schema' => 'forms/{id}/schema (options with id, label, value; left when shown)', 'stock' => 'forms/session/prepare answers stock {option id: left} for a form with capacities'),
			'risk_flags'  => array('pii_capture' => 'dyn_src users publishes display names', 'public_values' => 'opt_value is visible in the page source'),
			'assistant'   => array('dyn_users' => 'only with allow.dyn_users'),
			'spec'        => 'F05',
		);
		return $out;
	}
}
