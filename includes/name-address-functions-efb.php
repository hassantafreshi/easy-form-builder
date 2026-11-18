<?php
/**
 * F17 / F20 P22: the display of a localized name and address as a core contract (version 1,
 * docs/name-address/display-contract.en.md). The JavaScript twin is window.EFBNameAddr (public/assets/js/name-address-efb.js,
 * same data: includes/data/name-address-efb.json). The customer portal add-on, e-mails, the CSV and the entry viewer
 * call these instead of joining parts themselves.
 *
 * Input shapes accepted by efb_address_display() / efb_name_display():
 *   - the rows of a stored answer (each part row carries grp, part, cmp, sch; the first one na_full): the first group of that kind;
 *   - one group from efb_name_address_groups();
 *   - a map part => value, with $args['scheme'] (address) or $args['format'] (name).
 * The result is plain text (escape it where it is printed). Empty parts and their separators are left out.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('efb_name_address_api')) {
	/** ['version' => 1, 'features' => [...], 'script' => handle, 'global' => 'EFBNameAddr', 'formats' => [...], 'schemes' => [...]]. */
	function efb_name_address_api() {
		$c = class_exists('\\Emsfb\\Address_Schemes');
		return array('version' => 1, 'features' => array('name', 'address', 'groups', 'multiline', 'labels', 'enqueue'),
			'script' => 'efb-name-address-js', 'global' => 'EFBNameAddr',
			'formats' => $c ? array_keys(\Emsfb\Address_Schemes::name_formats()) : array(), 'schemes' => $c ? array_keys(\Emsfb\Address_Schemes::schemes()) : array());
	}
}

if (!function_exists('efb_name_address_groups')) {
	/** The name and address groups of an answer's rows, in order: grp, kind, set, parts, labels, full, text. */
	function efb_name_address_groups(array $rows, $locale = '') {
		if (!class_exists('\\Emsfb\\Address_Schemes')) return array();
		$out = array();
		foreach (\Emsfb\Address_Schemes::groups($rows) as $g) { $g['text'] = \Emsfb\Address_Schemes::group_text($g, (string) $locale, false); $out[] = $g; }
		return $out;
	}
}

if (!function_exists('efb_name_address_pick_efb')) {
	/** @internal The group to show for efb_address_display() / efb_name_display(). */
	function efb_name_address_pick_efb(array $row, $kind, array $args) {
		if (isset($row['kind'], $row['parts']) && is_array($row['parts'])) return $row;
		$list = array_values($row);
		if ($list && (is_array($list[0]) || is_object($list[0]))) {
			foreach (\Emsfb\Address_Schemes::groups($row) as $g) if ($g['kind'] === $kind) return $g;
			return null;
		}
		$set = $kind === 'name' ? (isset($args['format']) ? (string) $args['format'] : '') : (isset($args['scheme']) ? (string) $args['scheme'] : 'intl');
		return array('grp' => '', 'kind' => $kind, 'set' => $set, 'parts' => $row, 'labels' => isset($args['labels']) && is_array($args['labels']) ? $args['labels'] : array(), 'full' => '');
	}
}

if (!function_exists('efb_address_display')) {
	/** An address as text in its format's order. $args: scheme (for a parts map), multiline (lines with "\n"), labels. */
	function efb_address_display(array $row, $locale = '', array $args = array()) {
		if (!class_exists('\\Emsfb\\Address_Schemes')) return '';
		$g = efb_name_address_pick_efb($row, 'address', $args);
		if (!$g) return '';
		if (isset($args['labels']) && is_array($args['labels'])) $g['labels'] = $args['labels'] + (isset($g['labels']) ? $g['labels'] : array());
		return \Emsfb\Address_Schemes::group_text($g, (string) $locale, !empty($args['multiline']));
	}
}

if (!function_exists('efb_name_display')) {
	/** A name as text in its logical order (title, given, middle / father / grandfather, family). $args: format (for a parts map). */
	function efb_name_display(array $row, $locale = '', array $args = array()) {
		if (!class_exists('\\Emsfb\\Address_Schemes')) return '';
		$g = efb_name_address_pick_efb($row, 'name', $args);
		return $g ? \Emsfb\Address_Schemes::name_text($g['parts'], $g['set']) : '';
	}
}

if (!function_exists('efb_name_address_enqueue')) {
	/** Enqueue EFBNameAddr with the display data of every format (a page that is not a form: the portal, an admin screen). Returns the handle. */
	function efb_name_address_enqueue() {
		$f = EMSFB_PLUGIN_DIRECTORY . 'public/assets/js/name-address-efb.js';
		wp_register_script('efb-name-address-js', EMSFB_PLUGIN_URL . 'public/assets/js/name-address-efb.js', array(), EMSFB_PLUGIN_VERSION . '.' . (is_readable($f) ? (string) filemtime($f) : '0'), true);
		wp_enqueue_script('efb-name-address-js');
		if (class_exists('\\Emsfb\\Address_Schemes')) wp_add_inline_script('efb-name-address-js', 'window.efb_na_data=' . wp_json_encode(\Emsfb\Address_Schemes::display_data()) . ';', 'before');
		return 'efb-name-address-js';
	}
}
