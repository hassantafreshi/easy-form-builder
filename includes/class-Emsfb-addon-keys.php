<?php
/**
 * SPLIT §4.2: the form keys each moved feature's add-on owns, and the save rule that keeps them while the add-on
 * is absent. Without it the first save of a form on a site without the add-on would have emptied formulas, the
 * number format, the typed-signature mode, postal presets and booking dates (SPLIT §20 item 2).
 *
 * The rule (every save path: builder, AutoPilot, revision restore, API): after the sanitizer, for every key whose
 * add-on is not active, the value of the stored version of the same row (matched by id_) comes back unchanged and
 * the new value of the request is ignored; a new row (no stored version) does not get the key. The save answer
 * gets warnings[] {code: 'addon_keys_kept', addon, field_id, keys}. A form package import (no stored version)
 * reports the keys it could not keep in dropped[] with reason addon_inactive.
 *
 * Filter efb_addon_keys adds the keys of another add-on (same shape as MANIFEST).
 *
 * @package Emsfb
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Addon_Keys {

	const VERSION = 1;

	/** Mask presets of the postal category (F01 catalogue), owned by AdnADR. */
	const POSTAL_PRESETS = array('ir.postal_code', 'sa.postal_code', 'sa.short_address', 'eg.postal_code', 'kw.postal_code', 'om.postal_code', 'jo.postal_code',
		'ma.postal_code', 'dz.postal_code', 'tn.postal_code', 'us.zip', 'uk.postcode', 'ca.postal_code', 'au.postcode', 'in.pin', 'de.plz', 'br.cep', 'nl.postcode');

	/**
	 * add-on code => contract, types (field types it owns: the whole row's add-on keys plus every other key it holds
	 * is kept), fields (type => keys, '*' = any type; a key ending in * is a prefix), options (keys on option rows),
	 * row0 (form settings keys), values (key => values that belong to the add-on: the key is kept only for these).
	 */
	const MANIFEST = array(
		'AdnCLC' => array(
			'contract' => 'calc',
			'types'    => array('calc'),
			'fields'   => array(
				'number' => array('num_step', 'num_decimals', 'num_format', 'num_dec_sep', 'num_grp_sep', 'num_digits', 'num_prefix', 'num_suffix', 'num_currency', 'num_max_zero', 'range_mode'),
				'range'  => array('num_step', 'num_decimals', 'num_format', 'num_dec_sep', 'num_grp_sep', 'num_digits', 'num_prefix', 'num_suffix', 'num_currency', 'num_max_zero', 'range_mode'),
				'prcfld' => array('num_digits', 'num_prefix', 'num_suffix'),
				'calc'   => array('num_step', 'num_decimals', 'num_format', 'num_dec_sep', 'num_grp_sep', 'num_digits', 'num_prefix', 'num_suffix', 'num_currency', 'num_max_zero', 'formula', 'formula_err', 'calc_show', 'calc_empty', 'calc_as_price'),
			),
			'options'  => array('calc'),
			'row0'     => array(),
			'values'   => array(),
		),
		'AdnTSG' => array(
			'contract' => 'typed_signature',
			'types'    => array(),
			'fields'   => array('esign' => array('esign_mode', 'esign_font')),
			'options'  => array(),
			'row0'     => array(),
			'values'   => array(),
		),
		'AdnADR' => array(
			'contract' => 'address',
			'types'    => array('maps'),
			'fields'   => array('*' => array('addr_lookup')),
			'options'  => array(),
			'row0'     => array(),
			'values'   => array('mask_preset' => self::POSTAL_PRESETS),
		),
		'AdnBEF' => array(
			'contract' => 'booking',
			'types'    => array(),
			'fields'   => array('advdate' => array('availability', 'bk_*')),
			'options'  => array('dateExp', 'registered_count'),
			'row0'     => array('booking'),
			'values'   => array(),
		),
		'AdnGEO' => array(
			'contract' => 'smart_tags_ext',
			'types'    => array(),
			'fields'   => array(),
			'options'  => array(),
			'row0'     => array('geo_on', 'geo_notice'),
			'values'   => array(),
		),
	);

	/** @var array<string, bool>|null test override: add-on code => active. */
	private static $override = null;

	/** The manifest with the filter applied. */
	public static function manifest() {
		$m = self::MANIFEST;
		if (function_exists('apply_filters')) {
			$f = apply_filters('efb_addon_keys', $m);
			if (is_array($f)) $m = $f;
		}
		$out = array();
		foreach ($m as $code => $e) {
			if (!is_string($code) || !preg_match('/^Adn[A-Za-z]{2,5}$/', $code) || !is_array($e)) continue;
			$out[$code] = array(
				'contract' => isset($e['contract']) && is_string($e['contract']) ? $e['contract'] : '',
				'types'    => self::strings(isset($e['types']) ? $e['types'] : array()),
				'fields'   => isset($e['fields']) && is_array($e['fields']) ? array_map(array(__CLASS__, 'strings'), $e['fields']) : array(),
				'options'  => self::strings(isset($e['options']) ? $e['options'] : array()),
				'row0'     => self::strings(isset($e['row0']) ? $e['row0'] : array()),
				'values'   => isset($e['values']) && is_array($e['values']) ? array_map(array(__CLASS__, 'strings'), $e['values']) : array(),
			);
		}
		return $out;
	}

	private static function strings($list) {
		return array_values(array_filter((array) $list, 'is_string'));
	}

	/** Tests: force the active state of add-ons (null = real state). */
	public static function set_override($map) {
		self::$override = is_array($map) ? $map : null;
	}

	/** Whether the add-on that owns these keys is usable now (its contract has an active, compatible implementation). */
	public static function active($code) {
		if (is_array(self::$override) && array_key_exists($code, self::$override)) return (bool) self::$override[$code];
		$m = self::manifest();
		$contract = isset($m[$code]) ? $m[$code]['contract'] : '';
		if ($contract === '' || !function_exists('efb_addon_contracts')) return true;
		$c = efb_addon_contracts();
		return !isset($c[$contract]) || !empty($c[$contract]['active']);
	}

	/** The absent add-ons (code => manifest entry). */
	public static function absent() {
		$out = array();
		foreach (self::manifest() as $code => $e) if (!self::active($code)) $out[$code] = $e;
		return $out;
	}

	private static function key_matches($k, array $list) {
		foreach ($list as $p) {
			if ($p === $k) return true;
			if (substr($p, -1) === '*' && strncmp($k, substr($p, 0, -1), strlen($p) - 1) === 0) return true;
		}
		return false;
	}

	/**
	 * Keys of one row that belong to the add-on $e. $row0 true for form settings.
	 * @return array<int, string>
	 */
	public static function owned_keys(array $row, array $e, $row0 = false) {
		$type = isset($row['type']) && is_string($row['type']) ? $row['type'] : '';
		$keys = array();
		foreach ($row as $k => $v) {
			if (!is_string($k)) continue;
			$own = false;
			if ($row0) {
				$own = self::key_matches($k, $e['row0']);
			} elseif ($type === 'option') {
				$own = self::key_matches($k, $e['options']);
			} else {
				if (isset($e['fields'][$type]) && self::key_matches($k, $e['fields'][$type])) $own = true;
				if (isset($e['fields']['*']) && self::key_matches($k, $e['fields']['*'])) $own = true;
				if (isset($e['values'][$k]) && is_scalar($v) && in_array((string) $v, $e['values'][$k], true)) $own = true;
			}
			if ($own) $keys[] = $k;
		}
		return $keys;
	}

	/** Whether a form uses an add-on (its types, keys or values). @return array<int, string> codes. */
	public static function used_addons(array $rows) {
		$used = array();
		foreach (self::manifest() as $code => $e) {
			foreach ($rows as $i => $r) {
				if (is_object($r)) $r = get_object_vars($r);
				if (!is_array($r)) continue;
				$type = isset($r['type']) && is_string($r['type']) ? $r['type'] : '';
				if (($i !== 0 && in_array($type, $e['types'], true)) || self::owned_keys($r, $e, $i === 0)) { $used[] = $code; break; }
			}
		}
		return $used;
	}

	private static function index(array $rows) {
		$out = array();
		foreach ($rows as $i => $r) {
			if (!is_array($r)) continue;
			if ($i === 0) { $out["\0row0"] = $r; continue; }
			if (isset($r['id_']) && is_scalar($r['id_'])) $out[(string) $r['id_']] = $r;
		}
		return $out;
	}

	/**
	 * The save rule. $rows = the sanitized form; $stored = the stored rows (null for a new form).
	 * @return array{0: array, 1: array} rows, warnings (addon_keys_kept).
	 */
	public static function keep(array $rows, $stored = null) {
		$absent = self::absent();
		if (!$absent) return array($rows, array());
		$old = is_array($stored) ? self::index($stored) : array();
		$warnings = array();
		foreach ($rows as $i => $r) {
			if (!is_array($r)) continue;
			$id = $i === 0 ? "\0row0" : (isset($r['id_']) && is_scalar($r['id_']) ? (string) $r['id_'] : '');
			$prev = $id !== '' && isset($old[$id]) ? $old[$id] : null;
			$type = isset($r['type']) && is_string($r['type']) ? $r['type'] : '';
			foreach ($absent as $code => $e) {
				$whole = $i !== 0 && in_array($type, $e['types'], true);
				$now = self::owned_keys($r, $e, $i === 0);
				$was = is_array($prev) ? self::owned_keys($prev, $e, $i === 0) : array();
				if ($whole && is_array($prev)) {
					// A field type of the absent add-on: every key the stored row had that the request changed or lost comes back.
					$was = array_unique(array_merge($was, array_diff(array_keys($prev), array('id_', 'dataId', 'type', 'step', 'amount', 'parent'))));
				}
				$touched = array();
				$dropped = array();
				foreach (array_unique(array_merge($now, $was)) as $k) {
					$has_old = is_array($prev) && array_key_exists($k, $prev);
					if ($has_old) {
						if (!array_key_exists($k, $r) || $r[$k] !== $prev[$k]) { $rows[$i][$k] = $prev[$k]; $touched[] = $k; }
						elseif (in_array($k, $now, true)) $touched[] = $k;
					} elseif (array_key_exists($k, $r)) {
						// A key the stored row did not have: not written while its add-on is absent.
						unset($rows[$i][$k]);
						$dropped[] = $k;
					}
				}
				if ($touched) {
					$warnings[] = array('code' => 'addon_keys_kept', 'addon' => $code, 'field_id' => $i === 0 ? '' : $id, 'keys' => array_values(array_unique($touched)),
						'm' => self::message($code));
				}
				if ($dropped) {
					$warnings[] = array('code' => 'addon_keys_dropped', 'addon' => $code, 'field_id' => $i === 0 ? '' : $id, 'keys' => array_values(array_unique($dropped)),
						'm' => self::message($code, true));
				}
			}
		}
		return array($rows, $warnings);
	}

	/**
	 * A form package import (a new form, nothing stored): the keys of absent add-ons are taken out and reported.
	 * @return array{0: array, 1: array} rows, dropped [{code: addon_inactive, path, key, reason: addon_inactive, addon}]
	 *         (code like every other dropped[] entry of Form_Package; reason kept for callers of contract 1.1).
	 */
	public static function drop_for_import(array $rows) {
		$absent = self::absent();
		$dropped = array();
		if (!$absent) return array($rows, $dropped);
		foreach ($rows as $i => $r) {
			if (!is_array($r)) continue;
			foreach ($absent as $code => $e) {
				foreach (self::owned_keys($r, $e, $i === 0) as $k) {
					unset($rows[$i][$k]);
					$dropped[] = array('code' => 'addon_inactive', 'path' => $i . '.' . $k, 'key' => $k, 'reason' => 'addon_inactive', 'addon' => $code);
				}
			}
		}
		return array($rows, $dropped);
	}

	/** "Settings of %s are kept until the add-on is installed." with the add-on's name. */
	public static function message($code, $dropped = false) {
		$name = class_exists('\\Emsfb\\Addon_Catalog') ? Addon_Catalog::name($code) : $code;
		if ($dropped) {
			/* translators: %s: name of an Easy Form Builder add-on (a product name) */
			$t = function_exists('__') ? __('Needs the %s add-on', 'easy-form-builder') : 'Needs the %s add-on';
			return sprintf($t, $name);
		}
		/* translators: %s: name of an Easy Form Builder add-on (a product name) */
		$t = function_exists('__') ? __('Settings of %s are kept until the add-on is installed.', 'easy-form-builder') : 'Settings of %s are kept until the add-on is installed.';
		return sprintf($t, $name);
	}
}
