<?php
/**
 * F15: builder productivity, the parts on the server that are not revisions or export / import
 * (docs/feature-gaps/F15-builder-productivity.fa.md §4.4, §5.3, §8).
 *
 *  - the builder preferences of the signed-in user (user meta), saved by the admin AJAX action
 *    efb_set_builder_pref: today only efb_builder_single_key_shortcuts ('1' / '0', default '1'),
 *    the switch for the one-key shortcuts "/" and "?" (WCAG 2.1.4);
 *  - efb_var.builder_prefs and efb_var.f15 for the builder (efb_admin_localize_vars);
 *  - the F15 part of the capability registry (efb_capabilities): the undo / redo hooks of
 *    window.efbBuilderApi and the keyboard shortcuts.
 *
 * Revisions: class-Emsfb-revisions.php. Export / import: class-Emsfb-form-transfer.php.
 */
namespace Emsfb;

defined('ABSPATH') || exit;

class Builder_Tools {

	const API_VERSION = 1;
	const META_SINGLE_KEYS = 'efb_builder_single_key_shortcuts';
	/** Preference => user meta key. */
	const PREFS = array('single_key_shortcuts' => self::META_SINGLE_KEYS);
	/** Undo limits of the builder (spec §4.1): entries and characters of snapshots kept in memory. */
	const UNDO_LIMIT = 50;
	const UNDO_MAX_CHARS = 20000000;
	/** The shortcuts (spec §4.4): action => keys (KeyboardEvent.code, not the typed letter). */
	const SHORTCUTS = array(
		'save'      => array('Ctrl/Cmd+KeyS'),
		'undo'      => array('Ctrl/Cmd+KeyZ'),
		'redo'      => array('Ctrl/Cmd+Shift+KeyZ', 'Ctrl+KeyY'),
		'search'    => array('Slash'),
		'delete'    => array('Delete', 'Backspace'),
		'duplicate' => array('Ctrl/Cmd+KeyD'),
		'moveUp'    => array('Alt+ArrowUp'),
		'moveDown'  => array('Alt+ArrowDown'),
		'close'     => array('Escape'),
		'preview'   => array('Alt+KeyP'),
		'help'      => array('Shift+Slash'),
	);

	public static function register() {
		if (!function_exists('add_action')) return;
		if (function_exists('is_admin') && is_admin()) {
			add_action('wp_ajax_efb_set_builder_pref', array(__CLASS__, 'ajax_set_pref'));
		}
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 31, 2);
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 31, 2);
	}

	/** A preference of a user (string '1' / '0'). */
	public static function pref($name, $user_id = 0) {
		if (!isset(self::PREFS[$name])) return '';
		$uid = $user_id ? (int) $user_id : (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
		$v = ($uid && function_exists('get_user_meta')) ? get_user_meta($uid, self::PREFS[$name], true) : '';
		return $v === '0' ? '0' : '1';
	}

	/** The stored form of a preference value, or null when it is not one. */
	public static function sanitize_pref($name, $value) {
		if (!isset(self::PREFS[$name]) || !is_scalar($value)) return null;
		$v = (string) $value;
		if (in_array($v, array('1', 'true', 'on'), true)) return '1';
		if (in_array($v, array('0', 'false', 'off', ''), true)) return '0';
		return null;
	}

	/** wp_ajax_efb_set_builder_pref: {pref, value, nonce} → {success, pref, value}. */
	public static function ajax_set_pref() {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$lang = $fn ? $fn->text_efb(array('error403', 'somethingWentWrongPleaseRefresh')) : array('error403' => 'Forbidden', 'somethingWentWrongPleaseRefresh' => 'Error');
		$can = $fn ? $fn->user_permission_efb_admin_dashboard() : false;
		if (!check_ajax_referer('wp_rest', 'nonce', false) || !$can) {
			wp_send_json_success(array('success' => false, 'm' => $lang['error403']), 200);
		}
		$name = isset($_POST['pref']) ? sanitize_key(wp_unslash($_POST['pref'])) : '';
		$value = isset($_POST['value']) ? self::sanitize_pref($name, sanitize_text_field(wp_unslash($_POST['value']))) : null;
		if ($value === null) {
			wp_send_json_success(array('success' => false, 'm' => $lang['somethingWentWrongPleaseRefresh'], 'code' => 'invalid_pref'), 200);
		}
		update_user_meta(get_current_user_id(), self::PREFS[$name], $value);
		wp_send_json_success(array('success' => true, 'pref' => $name, 'value' => $value), 200);
	}

	/** efb_admin_localize_vars: efb_var.builder_prefs (this user) and efb_var.f15 (limits). */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		$vars['builder_prefs'] = array('single_key_shortcuts' => self::pref('single_key_shortcuts'));
		$vars['f15'] = array(
			'v'           => self::API_VERSION,
			'undo_limit'  => self::UNDO_LIMIT,
			'undo_chars'  => self::UNDO_MAX_CHARS,
			'merge_ms'    => 1000,
			'revisions'   => class_exists('\\Emsfb\\Revisions') ? Revisions::client_config() : null,
			'package'     => class_exists('\Emsfb\Form_Package') ? array('version' => Form_Package::API_VERSION, 'schema' => Form_Package::SCHEMA) : null,
		);
		return $vars;
	}

	/** efb_capabilities: the builder part of F15 (undo / redo through efbBuilderApi, shortcuts). */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out)) return $out;
		$out['builder_history'] = array(
			'v'        => self::API_VERSION,
			'spec'     => 'F15',
			'builder'  => array('efbBuilderApi.history.undo', 'efbBuilderApi.history.redo', 'efbBuilderApi.history.state', 'efbBuilderApi.history.batch'),
			'rule'     => 'every efbBuilderApi call that changes the form is one undo step; history.batch(label, fn) makes several calls one step',
			'event'    => 'efb:history-change',
			'limit'    => self::UNDO_LIMIT,
			'max_chars' => self::UNDO_MAX_CHARS,
			'scope'    => 'the form open in the builder; cleared when another form, a template or a draft is opened; not kept after a reload',
			'shortcuts' => self::SHORTCUTS,
			'plan'     => 'free',
		);
		if (isset($out['features']) && is_array($out['features'])) {
			$e = array('kind' => 'builder', 'scope' => 'site', 'package' => 'free', 'addon' => null, 'available_now' => true, 'reason' => null, 'prompt_state' => 0, 'message_key' => null, 'form_keys' => array());
			$out['features']['builder:undo'] = array_merge($e, array('label_key' => 'undo'));
			$out['features']['builder:shortcuts'] = array_merge($e, array('label_key' => 'shortcuts'));
			$out['features']['builder:palette_search'] = array_merge($e, array('label_key' => 'searchFields'));
		}
		return $out;
	}
}
