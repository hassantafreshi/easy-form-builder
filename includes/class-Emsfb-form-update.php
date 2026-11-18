<?php
/**
 * AI-2 (docs/autopilot-integration.md "AI builder core APIs"): form.update — change a SAVED form from the server.
 *
 *   Form_Package::read($form_id)                      → {form_id, form_name, form_type, status, base_hash, rows}
 *   Form_Package::update($form_id, $patches, $args)   → {form_id, form_name, base_hash, changed, warnings, payment?} | WP_Error
 *   REST (the plugin's admins; a logged-in cookie with X-WP-Nonce, or an application password):
 *     GET  Emsfb/v1/admin/forms/{id}/package   POST Emsfb/v1/admin/forms/{id}/update  {base_hash, patches, name?}
 *
 * Optimistic concurrency: base_hash is the hash read() gave; a form saved in between (the builder, another call) is
 * refused with stale_hash and nothing is written (the write itself is conditional on the stored text, so two calls
 * that read the same version cannot both win). The patches are applied to a copy, then:
 *  - the gate: a field type, a setting value or an add-on key the change brings in must be usable on this site now
 *    (the capability registry): addon_required (data: addon, state, feature) or plan_required (data: package,
 *    feature); what the form already had is not judged (as the builder's Save);
 *  - the save path of the builder's Save (Admin::save_existing_form_efb(): the checks, sanitize_obj_msg_efb, the F03 …
 *    F17 normalizers, Addon_Keys, the add-ons' tables, efb_form_saved with source "autopilot" and a version).
 *
 * Patches (applied in order; ids are field id_):
 *   {op:"form", set:{key:value}, unset:[key]}           row 0 (form settings; "steps" is counted, never set)
 *   {op:"field", id, set:{…}, unset:[…]}                one field or step row (id_, dataId, type, parent stay)
 *   {op:"insert", rows:[…], after|before:id | step:n}   fields with their option rows (efbBuilderApi.insertItems rules)
 *   {op:"remove", ids:[…]}                              fields with their option rows (a repeater with its fields)
 *   {op:"options", id, options:["Label" | {value, price?, opt_value?, id_?}]}   replaces a choice field's option rows
 *                                                       (a choice with its id_ keeps it)
 *   {op:"name", value}                                  the form's name
 * Refusals: package_forbidden 403, package_form_missing 404, base_hash_missing 400, stale_hash 409, invalid_patch 400,
 * unknown_field 404, duplicate_id 409, orphan_option 400, unknown_target 404, addon_required 409, plan_required 403,
 * html_not_allowed 400, and the save path's own codes (pattern_invalid, auth_fields_missing, date / limit codes).
 */
namespace Emsfb;

defined('ABSPATH') || exit;

class Form_Update {

	const VERSION = 1;
	const MAX_PATCHES = 200;
	const FIXED_KEYS = array('id_', 'dataId', 'type', 'parent');
	/** Registry entries that list where tags are read (labels, texts), not keys they gate. */
	const CONTENT_FEATURES = array('setting:smart_tags', 'setting:answer_piping');

	/** @var array|null tests: the capability registry to judge against (null = this site's). */
	public static $registry = null;

	public static function register() {
		if (!function_exists('add_action')) return;
		add_action('rest_api_init', array(__CLASS__, 'routes'));
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 34, 2);
	}

	private static function err($code, $msg, $status, $extra = array()) {
		return class_exists('\\WP_Error') ? new \WP_Error($code, $msg, array('status' => $status) + $extra) : array('error' => $code, 'message' => $msg, 'status' => $status) + $extra;
	}

	/** Who may read or change a saved form: a user of the plugin's dashboard (as the builder's Save). */
	public static function can() {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		if ($fn && method_exists($fn, 'user_permission_efb_admin_dashboard')) return (bool) $fn->user_permission_efb_admin_dashboard();
		return function_exists('current_user_can') && current_user_can('manage_options');
	}

	/** The concurrency token of a form: its decoded rows and its name (a stored text that only differs in escaping is the same form). */
	public static function hash_of($rows, $name) {
		$j = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return hash('sha256', (string) $j . "\n" . (string) $name);
	}

	/** The stored form: {form_id, form_name, form_type, raw, rows} or null. */
	public static function stored($form_id) {
		global $wpdb;
		$r = $wpdb->get_row($wpdb->prepare("SELECT form_id, form_name, form_type, form_structer FROM `{$wpdb->prefix}emsfb_form` WHERE form_id = %d", (int) $form_id), ARRAY_A);
		if (!$r) return null;
		$json = function_exists('emsfb_json_normalize_efb') ? \emsfb_json_normalize_efb($r['form_structer']) : str_replace('\\"', '"', $r['form_structer']);
		$rows = json_decode($json, true);
		if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) return null;
		return array('form_id' => (int) $r['form_id'], 'form_name' => (string) $r['form_name'], 'form_type' => (string) $r['form_type'], 'raw' => (string) $r['form_structer'], 'rows' => $rows);
	}

	/** Form_Package::read(): what an assistant edits and the base_hash of that version. */
	public static function read($form_id) {
		if (!self::can()) return self::err('package_forbidden', 'You are not allowed to do this.', 403);
		$f = self::stored($form_id);
		if (!$f) return self::err('package_form_missing', 'The form was not found.', 404);
		return array('form_id' => $f['form_id'], 'form_name' => $f['form_name'], 'form_type' => $f['form_type'],
			'status' => class_exists('\\Emsfb\\Form_Status') ? Form_Status::get($f['form_id']) : 'published',
			'base_hash' => self::hash_of($f['rows'], $f['form_name']), 'rows' => $f['rows']);
	}

	/* ------------------------------------------------------------------ patches */

	private static function is_option($r) {
		return is_array($r) && isset($r['type']) && in_array($r['type'], array('option', 'r_matrix'), true);
	}

	private static function index_of(array $rows, $id) {
		foreach ($rows as $i => $r) if ($i > 0 && is_array($r) && !self::is_option($r) && isset($r['id_']) && (string) $r['id_'] === (string) $id) return $i;
		return -1;
	}

	/** Last index that belongs to a field: its row and every row whose parent it is (builder-api-efb.js fieldEnd). */
	private static function end_of(array $rows, $i) {
		$id = (string) $rows[$i]['id_'];
		$end = $i;
		for ($j = $i + 1; $j < count($rows); $j++) if (is_array($rows[$j]) && isset($rows[$j]['parent']) && (string) $rows[$j]['parent'] === $id) $end = $j;
		return $end;
	}

	/** Fields and steps numbered one after another; options carry their parent's amount and step (A2, renumber()). */
	public static function renumber(array $rows) {
		$n = 0; $step = 1; $by = array();
		foreach ($rows as $i => $r) {
			if ($i === 0 || !is_array($r) || self::is_option($r)) continue;
			$n++;
			$rows[$i]['amount'] = $n;
			if ($r['type'] === 'step') $step = (int) (isset($r['step']) ? $r['step'] : $step) ?: $step;
			else $rows[$i]['step'] = $step;
			if (isset($r['id_'])) $by[(string) $r['id_']] = $i;
		}
		foreach ($rows as $i => $r) {
			if ($i > 0 && self::is_option($r) && isset($r['parent']) && isset($by[(string) $r['parent']])) {
				$rows[$i]['amount'] = $rows[$by[(string) $r['parent']]]['amount'];
				$rows[$i]['step'] = $rows[$by[(string) $r['parent']]]['step'];
			}
		}
		return $rows;
	}

	private static function set_keys(array $row, $p, $row0) {
		if (isset($p['set'])) {
			if (!is_array($p['set'])) return 'set must be an object';
			foreach ($p['set'] as $k => $v) {
				if (!is_string($k) || $k === '' || in_array($k, self::FIXED_KEYS, true) || ($row0 && $k === 'steps')) return 'the key ' . (is_string($k) ? $k : '?') . ' cannot be set';
				if ($row0 && $k === 'type' && !in_array($v, Form_Package::ROW0_TYPES, true)) return 'unknown form type';
				$row[$k] = $v;
			}
		}
		if (isset($p['unset'])) {
			if (!is_array($p['unset'])) return 'unset must be a list';
			foreach ($p['unset'] as $k) {
				if (!is_string($k) || in_array($k, self::FIXED_KEYS, true) || $k === 'type' || $k === 'steps') return 'the key ' . (is_string($k) ? $k : '?') . ' cannot be removed';
				unset($row[$k]);
			}
		}
		return $row;
	}

	/**
	 * The rows after the patches, or a WP_Error. $name is changed by a "name" patch.
	 * @return array|\WP_Error array(rows, name, changed field ids, whether row 0 changed)
	 */
	public static function apply(array $rows, $patches, $name) {
		if (!is_array($patches) || !$patches || array_values($patches) !== $patches) return self::err('invalid_patch', 'patches must be a non-empty list.', 400);
		if (count($patches) > self::MAX_PATCHES) return self::err('invalid_patch', 'At most ' . self::MAX_PATCHES . ' patches in one call.', 400);
		$changed = array();
		$form_changed = false;
		foreach ($patches as $n => $p) {
			$op = is_array($p) && isset($p['op']) && is_string($p['op']) ? $p['op'] : '';
			$bad = function ($m, $code = 'invalid_patch', $status = 400, $extra = array()) use ($n) { return self::err($code, 'Patch ' . $n . ': ' . $m, $status, array('patch' => $n) + $extra); };
			if ($op === 'name') {
				if (!isset($p['value']) || !is_scalar($p['value']) || trim((string) $p['value']) === '') return $bad('value must be a name');
				$name = (string) $p['value'];
			} elseif ($op === 'form') {
				$r = self::set_keys($rows[0], $p, true);
				if (is_string($r)) return $bad($r);
				$rows[0] = $r;
				$form_changed = true;
			} elseif ($op === 'field') {
				$id = isset($p['id']) && is_scalar($p['id']) ? (string) $p['id'] : '';
				$i = self::index_of($rows, $id);
				if ($i < 0) return $bad('no field with id ' . $id, 'unknown_field', 404, array('id' => $id));
				$r = self::set_keys($rows[$i], $p, false);
				if (is_string($r)) return $bad($r);
				$rows[$i] = $r;
				$changed[] = $id;
			} elseif ($op === 'remove') {
				$ids = isset($p['ids']) && is_array($p['ids']) ? $p['ids'] : (isset($p['id']) ? array($p['id']) : array());
				if (!$ids) return $bad('ids must list fields');
				$drop = array();
				foreach ($ids as $id) {
					$i = is_scalar($id) ? self::index_of($rows, (string) $id) : -1;
					if ($i < 0) return $bad('no field with id ' . (is_scalar($id) ? $id : '?'), 'unknown_field', 404, array('id' => is_scalar($id) ? (string) $id : ''));
					if ($rows[$i]['type'] === 'step') return $bad('steps are removed in the builder', 'invalid_patch');
					$drop[(string) $id] = 1;
					if ($rows[$i]['type'] === 'repeater') foreach ($rows as $k) if (is_array($k) && isset($k['group']) && (string) $k['group'] === (string) $id && !self::is_option($k) && isset($k['id_'])) $drop[(string) $k['id_']] = 1;
				}
				$keep = array();
				foreach ($rows as $i => $r) {
					if ($i > 0 && is_array($r) && ((isset($r['id_']) && isset($drop[(string) $r['id_']]) && !self::is_option($r)) || (isset($r['parent']) && isset($drop[(string) $r['parent']])))) continue;
					$keep[] = $r;
				}
				$rows = $keep;
				$changed = array_merge($changed, array_keys($drop));
			} elseif ($op === 'insert') {
				$add = isset($p['rows']) && is_array($p['rows']) ? array_values($p['rows']) : array();
				if (!$add) return $bad('rows must be a non-empty list');
				$seen = array(); $mine = array();
				foreach ($rows as $i => $r) if ($i > 0 && is_array($r) && isset($r['id_']) && is_scalar($r['id_'])) $seen[(string) $r['id_']] = 1;
				foreach ($add as $r) {
					if (!is_array($r) || array_values($r) === $r) return $bad('every row must be an object');
					if (!isset($r['id_']) || !is_string($r['id_']) || !preg_match('/^[A-Za-z0-9_-]{1,40}$/D', $r['id_'])) return $bad('every row needs an id_ of letters, digits, - or _');
					if (!isset($r['type']) || !is_string($r['type']) || $r['type'] === '' || in_array($r['type'], Form_Package::ROW0_TYPES, true)) return $bad('row ' . $r['id_'] . ' has no valid type');
					if (isset($seen[$r['id_']]) || isset($mine[$r['id_']])) return $bad('id_ ' . $r['id_'] . ' is already used', 'duplicate_id', 409, array('id' => $r['id_']));
					$mine[$r['id_']] = $r['type'];
				}
				foreach ($add as $k => $r) {
					if (self::is_option($r)) {
						$par = isset($r['parent']) && is_scalar($r['parent']) ? (string) $r['parent'] : '';
						if (!(isset($mine[$par]) && !in_array($mine[$par], array('option', 'r_matrix'), true)) && self::index_of($rows, $par) < 0) return $bad('option ' . $r['id_'] . ' has no parent field ' . $par, 'orphan_option', 400, array('id' => $r['id_']));
					} elseif ($r['type'] !== 'step' && empty($r['dataId'])) {
						$add[$k]['dataId'] = $r['id_'] . '-id';
					}
				}
				$has_step = false;
				foreach ($add as $r) if ($r['type'] === 'step') $has_step = true;
				if ($has_step) {
					if (count($add) !== 1 || isset($p['after']) || isset($p['before'])) return $bad('a step is inserted alone, at the end of the form', 'invalid_patch');
					$next = 1;
					foreach ($rows as $i => $r) if ($i > 0 && is_array($r) && $r['type'] === 'step') $next++;
					$add[0]['id_'] = $add[0]['dataId'] = $add[0]['id'] = (string) $next;
					$add[0]['step'] = $next;
					$at = count($rows);
				} elseif (isset($p['after'])) {
					$a = self::index_of($rows, (string) $p['after']);
					if ($a < 0) return $bad('no field with id ' . $p['after'], 'unknown_target', 404, array('id' => (string) $p['after']));
					$at = self::end_of($rows, $a) + 1;
				} elseif (isset($p['before'])) {
					$b = self::index_of($rows, (string) $p['before']);
					if ($b < 0) return $bad('no field with id ' . $p['before'], 'unknown_target', 404, array('id' => (string) $p['before']));
					if ($rows[$b]['type'] === 'step' && (int) ($rows[$b]['step'] ?? 1) === 1) return $bad('nothing goes before step 1', 'invalid_patch');
					$at = $b;
				} elseif (isset($p['step'])) {
					$s = -1; $at = count($rows);
					foreach ($rows as $i => $r) {
						if ($i === 0 || !is_array($r) || $r['type'] !== 'step') continue;
						if ((int) ($r['step'] ?? 0) === (int) $p['step']) $s = $i;
						elseif ($s >= 0 && (int) ($r['step'] ?? 0) > (int) $p['step']) { $at = $i; break; }
					}
					if ($s < 0) return $bad('no step ' . (int) $p['step'], 'unknown_target', 404);
				} else {
					$at = count($rows);
				}
				array_splice($rows, $at, 0, $add);
				if (isset($p['form_patch']) && is_array($p['form_patch'])) {
					$r = self::set_keys($rows[0], array('set' => $p['form_patch']), true);
					if (is_string($r)) return $bad($r);
					$rows[0] = $r;
					$form_changed = true;
				}
				foreach ($add as $r) if (!self::is_option($r)) $changed[] = $r['id_'];
			} elseif ($op === 'options') {
				$id = isset($p['id']) && is_scalar($p['id']) ? (string) $p['id'] : '';
				$i = self::index_of($rows, $id);
				if ($i < 0) return $bad('no field with id ' . $id, 'unknown_field', 404, array('id' => $id));
				$list = isset($p['options']) && is_array($p['options']) ? array_values($p['options']) : array();
				if (!$list || count($list) > 500) return $bad('options must list 1 to 500 choices');
				$first = null;
				foreach ($rows as $r) if (is_array($r) && ($r['type'] ?? '') === 'option' && isset($r['parent']) && (string) $r['parent'] === $id) { $first = $r; break; }
				if ($first === null && !in_array($rows[$i]['type'], array('radio', 'checkbox', 'select', 'multiselect', 'payRadio', 'payCheckbox', 'paySelect', 'payMultiselect', 'chlRadio', 'chlCheckBox', 'imgRadio', 'ranking'), true)) return $bad('field ' . $id . ' has no choices', 'invalid_patch');
				$rows = array_values(array_filter($rows, function ($r) use ($id) { return !(is_array($r) && ($r['type'] ?? '') === 'option' && isset($r['parent']) && (string) $r['parent'] === $id); }));
				$i = self::index_of($rows, $id);
				$used = array();
				foreach ($rows as $r) if (is_array($r) && isset($r['id_'])) $used[(string) $r['id_']] = 1;
				$new = array();
				foreach ($list as $o) {
					$label = is_scalar($o) ? (string) $o : (is_array($o) && isset($o['value']) && is_scalar($o['value']) ? (string) $o['value'] : '');
					if (trim($label) === '') return $bad('every choice needs a label', 'invalid_patch');
					// A choice that names its id_ keeps it (references to it stay: logic, calculations, stock); new ones get one.
					$oid = is_array($o) && isset($o['id_']) && is_string($o['id_']) && preg_match('/^[A-Za-z0-9_-]{1,40}$/D', $o['id_']) && !isset($used[$o['id_']]) ? $o['id_'] : '';
					while ($oid === '' || isset($used[$oid])) $oid = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 9);
					$used[$oid] = 1;
					$row = array('id_' => $oid, 'dataId' => $oid . '-id', 'parent' => $id, 'type' => 'option', 'value' => $label, 'id_op' => $oid, 'step' => $rows[$i]['step'] ?? 1, 'amount' => $rows[$i]['amount'] ?? 1);
					if (strpos((string) $rows[$i]['type'], 'pay') === 0) $row['price'] = is_array($o) && isset($o['price']) && is_numeric($o['price']) ? (string) $o['price'] : '0';
					if (is_array($o) && isset($o['opt_value']) && is_scalar($o['opt_value'])) $row['opt_value'] = (string) $o['opt_value'];
					$new[] = $row;
				}
				array_splice($rows, self::end_of($rows, $i) + 1, 0, $new);
				$changed[] = $id;
			} else {
				return $bad('unknown op "' . (is_string($op) ? substr($op, 0, 20) : '') . '" (form, field, insert, remove, options, name)');
			}
		}
		$steps = 0;
		foreach ($rows as $i => $r) if ($i > 0 && is_array($r) && ($r['type'] ?? '') === 'step') $steps++;
		$rows[0]['steps'] = max(1, $steps);
		if (class_exists('\\Emsfb\\Repeater')) $rows = Repeater::normalize_rows($rows);
		return array(self::renumber(array_values($rows)), $name, array_values(array_unique($changed)), $form_changed);
	}

	private static function has_script($v) {
		if (is_array($v)) { foreach ($v as $x) if (self::has_script($x)) return true; return false; }
		return is_string($v) && preg_match('/<\s*script\b/i', $v) === 1;
	}

	/* ------------------------------------------------------------------ the gate */

	private static function on($v) {
		return !($v === null || $v === false || $v === '' || $v === 0 || $v === '0' || $v === 'false' || $v === array());
	}

	private static function registry() {
		if (is_array(self::$registry)) return self::$registry;
		return class_exists('\\Emsfb\\Builder_Api') ? Builder_Api::capabilities() : array();
	}

	private static function refusal($feature, array $e, $what) {
		$reason = isset($e['reason']) ? (string) $e['reason'] : 'plan';
		if ($reason === 'plan') {
			$pkg = isset($e['package']) ? (string) $e['package'] : 'pro';
			return self::err('plan_required', $what . ' needs the ' . ($pkg === 'pro' ? 'Pro' : 'Free Plus') . ' plan.', 403, array('feature' => $feature, 'package' => $pkg));
		}
		$code = isset($e['addon']) ? (string) $e['addon'] : '';
		$name = class_exists('\\Emsfb\\Addon_Catalog') && $code !== '' ? Addon_Catalog::name($code) : $code;
		return self::err('addon_required', $what . ' needs the ' . $name . ' add-on (' . $code . ').', 409, array('feature' => $feature, 'addon' => $code, 'addon_name' => $name, 'state' => $reason === 'update' ? 'update' : 'addon', 'install' => 'admin.php?page=Emsfb_addon'));
	}

	/**
	 * What the change brings in must be usable on this site now: a new field's type, a setting value turned on, a key of
	 * an add-on that is not active, a step over the plan's limit. What the form already had is not judged.
	 * @return null|\WP_Error
	 */
	public static function gate(array $old, array $new) {
		$caps = self::registry();
		$f = isset($caps['features']) && is_array($caps['features']) ? $caps['features'] : array();
		$by_id = array();
		foreach ($old as $i => $r) if ($i > 0 && is_array($r) && isset($r['id_'])) $by_id[(string) $r['id_']] = $r;
		// Settings by form key ("key=value" entries and plain keys). Entries that list where smart tags / answer piping are
		// read (name, message, value, …) describe content, not a gate, and are left out.
		$plain = array(); $valued = array();
		foreach ($f as $key => $e) {
			if (!is_array($e) || ($e['kind'] ?? '') !== 'setting' || empty($e['form_keys']) || (isset($e['scope']) && $e['scope'] === 'site') || in_array($key, self::CONTENT_FEATURES, true)) continue;
			foreach ((array) $e['form_keys'] as $fk) {
				if (!is_string($fk) || strpos($fk, '[') !== false) continue;
				if (strpos($fk, '=') !== false) { list($k, $v) = explode('=', $fk, 2); $valued[$k][$v][] = $key; }
				else $plain[$fk][] = $key;
			}
		}
		$setting_of = function ($k, $v) use ($plain, $valued) {
			$keys = is_scalar($v) && isset($valued[$k][(string) $v]) ? $valued[$k][(string) $v] : array();
			foreach ($plain as $p => $list) if ($p === $k || (substr($p, -1) === '*' && strncmp($k, substr($p, 0, -1), strlen($p) - 1) === 0)) $keys = array_merge($keys, $list);
			return array_values(array_unique($keys));
		};
		// A key some entries name: refused only when none of them is usable now and they agree on the add-on (a currency
		// is every gateway's: refused only if it were no gateway's).
		$judge = function ($keys) use ($f) {
			$off = array(); $addons = array();
			foreach ($keys as $key) {
				if (!empty($f[$key]['available_now'])) return null;
				$off[] = $key;
				$addons[isset($f[$key]['addon']) ? (string) $f[$key]['addon'] : ''] = 1;
			}
			return $off && count($addons) === 1 ? $off[0] : null;
		};
		$absent = class_exists('\\Emsfb\\Addon_Keys') ? Addon_Keys::absent() : array();
		foreach ($new as $i => $r) {
			if (!is_array($r)) continue;
			$prev = $i === 0 ? $old[0] : (isset($r['id_']) && isset($by_id[(string) $r['id_']]) ? $by_id[(string) $r['id_']] : null);
			$type = isset($r['type']) && is_string($r['type']) ? $r['type'] : '';
			if ($i > 0 && $prev === null && !self::is_option($r) && $type !== 'step') {
				$e = isset($f['field:' . $type]) ? $f['field:' . $type] : null;
				if (is_array($e) && empty($e['available_now'])) return self::refusal('field:' . $type, $e, 'The field type ' . $type);
				foreach ($absent as $code => $m) if (in_array($type, $m['types'], true)) return self::refusal('field:' . $type, array('reason' => 'addon', 'addon' => $code), 'The field type ' . $type);
				// A gateway field of the catalogue whose add-on did not register it here (off, not installed, too old).
				if (!is_array($e) && class_exists('\\Emsfb\\Payments\\Readiness') && in_array($type, Payments\Readiness::catalogue_types(), true) && !Payments\Registry::has($type)) {
					$code = Payments\Readiness::addon_of($type);
					return self::refusal('field:' . $type, array('reason' => class_exists('\\Emsfb\\Split') && Split::addon_state($code) === 'update' ? 'update' : 'addon', 'addon' => $code), 'The field type ' . $type);
				}
			}
			$keys = array();
			foreach ($r as $k => $v) if (is_string($k) && (!is_array($prev) || !array_key_exists($k, $prev) || $prev[$k] !== $v)) $keys[$k] = $v;
			if (!$keys) continue;
			foreach ($keys as $k => $v) {
				if (!self::on($v)) continue;
				// A phone twin that only repeats the desktop value (mobile_size = size, as a new row carries it) is no layout.
				if (strncmp($k, 'mobile_', 7) === 0 && is_scalar($v) && isset($r[substr($k, 7)]) && is_scalar($r[substr($k, 7)]) && (string) $r[substr($k, 7)] === (string) $v) continue;
				$key = $judge($setting_of($k, $v));
				if ($key !== null) return self::refusal($key, $f[$key], 'The setting ' . $k);
			}
			foreach ($absent as $code => $m) {
				$own = Addon_Keys::owned_keys($keys + array('type' => $type), $m, $i === 0);
				$own = array_values(array_diff($own, array('type')));
				foreach ($own as $k) if (self::on($keys[$k])) return self::refusal('addon:' . $code, array('reason' => 'addon', 'addon' => $code), 'The setting ' . $k);
			}
		}
		// Steps over the plan's limit.
		$lim = isset($f['limit:steps']['limits']) && is_array($f['limit:steps']['limits']) ? $f['limit:steps']['limits'] : null;
		$plan = isset($caps['plan']['name']) ? (string) $caps['plan']['name'] : '';
		$plan = $plan === 'pro_pending' || $plan === '' ? 'free' : $plan;
		if ($lim && isset($lim[$plan]) && is_int($lim[$plan])) {
			$count = function ($rows) { $n = 0; foreach ($rows as $i => $r) if ($i > 0 && is_array($r) && ($r['type'] ?? '') === 'step') $n++; return $n; };
			if ($count($new) > $lim[$plan] && $count($new) > $count($old)) return self::refusal('limit:steps', array('reason' => 'plan', 'package' => 'free_plus'), 'More than ' . $lim[$plan] . ' steps');
		}
		return null;
	}

	/* ------------------------------------------------------------------ update */

	/** Form_Package::update(). args: base_hash (required), name, source (efb_form_saved source, default "autopilot"), note. */
	public static function update($form_id, $patches, array $args = array()) {
		if (!self::can()) return self::err('package_forbidden', 'You are not allowed to do this.', 403);
		$f = self::stored($form_id);
		if (!$f) return self::err('package_form_missing', 'The form was not found.', 404);
		$base = isset($args['base_hash']) && is_string($args['base_hash']) ? $args['base_hash'] : '';
		if ($base === '') return self::err('base_hash_missing', 'base_hash is required: read the form first (Form_Package::read()).', 400);
		$now = self::hash_of($f['rows'], $f['form_name']);
		if (!hash_equals($now, $base)) return self::err('stale_hash', 'The form changed since it was read. Read it again and repeat the change.', 409, array('current_hash' => $now));
		$name = $f['form_name'];
		if (isset($args['name']) && is_scalar($args['name']) && trim((string) $args['name']) !== '') $patches = array_merge(is_array($patches) ? $patches : array(), array(array('op' => 'name', 'value' => (string) $args['name'])));
		$a = self::apply($f['rows'], $patches, $name);
		if (!is_array($a) || isset($a['error'])) return $a;
		list($rows, $name, $changed, $form_changed) = $a;
		$name = function_exists('sanitize_text_field') ? sanitize_text_field($name) : trim(strip_tags($name));
		$name = function_exists('mb_substr') ? mb_substr($name, 0, 250) : substr($name, 0, 250);
		$g = self::gate($f['rows'], $rows);
		if ($g) return $g;
		if (!class_exists('\\Emsfb\\Admin') && defined('EMSFB_PLUGIN_DIRECTORY')) require_once EMSFB_PLUGIN_DIRECTORY . 'includes/admin/class-Emsfb-admin.php';
		if (!class_exists('\\Emsfb\\Admin') || !method_exists('\\Emsfb\\Admin', 'save_existing_form_efb')) return self::err('package_unavailable', 'The form cannot be saved right now.', 503);
		$adm = (new \ReflectionClass('\\Emsfb\\Admin'))->newInstanceWithoutConstructor();
		// The builder refuses a script (Admin::isScript()); here any <script in a value of the change, before the sanitizer.
		if (self::has_script($patches)) return self::err('html_not_allowed', 'Scripts are not allowed in a form.', 400);
		$source = isset($args['source']) && is_string($args['source']) && preg_match('/^[a-z_]{2,20}$/', $args['source']) ? $args['source'] : 'autopilot';
		$note = isset($args['note']) && is_scalar($args['note']) ? (function_exists('sanitize_text_field') ? sanitize_text_field((string) $args['note']) : (string) $args['note']) : '';
		$res = $adm->save_existing_form_efb($f['form_id'], $name, $rows, array('source' => $source, 'expected_raw' => $f['raw'],
			'rev' => array('rev_source' => 'autopilot', 'rev_note' => function_exists('mb_substr') ? mb_substr($note, 0, 190) : substr($note, 0, 190), 'rev_extras' => 0)));
		if (!is_array($res) || empty($res['success'])) {
			$code = is_array($res) && !empty($res['code']) ? (string) $res['code'] : 'package_not_saved';
			$m = is_array($res) && isset($res['m']) ? wp_strip_all_tags((string) $res['m']) : 'The form could not be saved.';
			$extra = array();
			foreach (array('path', 'missing', 'errors') as $k) if (is_array($res) && isset($res[$k])) $extra[$k] = $res[$k];
			return self::err($code, $m !== '' ? $m : 'The form could not be saved.', $code === 'stale_hash' ? 409 : ($code === 'package_not_saved' ? 500 : 400), $extra);
		}
		$after = self::stored($f['form_id']);
		$out = array('form_id' => $f['form_id'], 'form_name' => $name, 'form_type' => $after ? $after['form_type'] : $f['form_type'],
			'status' => class_exists('\\Emsfb\\Form_Status') ? Form_Status::get($f['form_id']) : 'published',
			'base_hash' => $after ? self::hash_of($after['rows'], $after['form_name']) : '', 'previous_hash' => $now, 'changed' => $changed, 'form_changed' => $form_changed,
			'warnings' => isset($res['warnings']) && is_array($res['warnings']) ? $res['warnings'] : array(), 'shortcode' => '[EMS_Form_Builder id=' . $f['form_id'] . ']');
		if (isset($res['payment_ready'])) $out['payment'] = array('ready' => (bool) $res['payment_ready'], 'issues' => isset($res['payment_issues']) ? $res['payment_issues'] : array());
		return $out;
	}

	/* ------------------------------------------------------------------ REST and registry */

	public static function routes() {
		if (!function_exists('register_rest_route')) return;
		$perm = function () { return function_exists('get_current_user_id') && get_current_user_id() > 0 && self::can(); };
		$answer = function ($r) {
			if (is_array($r) && isset($r['error'])) $r = new \WP_Error($r['error'], $r['message'], array('status' => $r['status']));
			return is_wp_error($r) ? $r : rest_ensure_response($r);
		};
		register_rest_route('Emsfb/v1', '/admin/forms/(?P<id>\d+)/package', array('methods' => 'GET', 'permission_callback' => $perm,
			'callback' => function ($req) use ($answer) { return $answer(self::read((int) $req['id'])); }));
		register_rest_route('Emsfb/v1', '/admin/forms/(?P<id>\d+)/update', array('methods' => 'POST', 'permission_callback' => $perm,
			'callback' => function ($req) use ($answer) {
				$args = array('base_hash' => (string) $req->get_param('base_hash'));
				foreach (array('name', 'note') as $k) if ($req->get_param($k) !== null) $args[$k] = $req->get_param($k);
				return $answer(self::update((int) $req['id'], $req->get_param('patches'), $args));
			}));
	}

	public static function capabilities($out, $args = array()) {
		if (!is_array($out)) return $out;
		$rest = function ($p) { return function_exists('rest_url') ? rest_url('Emsfb/v1/admin/forms/{id}/' . $p) : '/wp-json/Emsfb/v1/admin/forms/{id}/' . $p; };
		$out['form_update'] = array('v' => self::VERSION, 'spec' => 'AI-2',
			'php' => 'Form_Package::read($form_id) → base_hash; Form_Package::update($form_id, $patches, {base_hash, name?, note?})',
			'rest' => array('read' => array('GET', $rest('package')), 'update' => array('POST', $rest('update'))), 'auth' => 'the plugin\'s admins: cookie + X-WP-Nonce, or an application password',
			'ops' => array('form', 'field', 'insert', 'remove', 'options', 'name'), 'max_patches' => self::MAX_PATCHES,
			'codes' => array('stale_hash', 'base_hash_missing', 'unknown_field', 'unknown_target', 'duplicate_id', 'orphan_option', 'invalid_patch', 'addon_required', 'plan_required', 'html_not_allowed', 'package_forbidden', 'package_form_missing'),
			'rules' => array('the builder\'s save path (sanitizer, normalizers, add-on keys kept, hooks, a version "autopilot")', 'a form saved in between is refused (stale_hash), nothing written', 'what the change brings in is gated by this registry; what the form had is kept'));
		return $out;
	}
}
