<?php
/**
 * B4 (admin UX): the phrases of the admin screens' shared parts that admin-efb.js draws (efb_var.text): the builder's
 * field settings panel (BLD-01).
 *
 * Loaded by Addon_Catalog::register() (always loaded), so it does not depend on the order of class-Emsfb.php.
 *
 * @package Emsfb
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Notices {

	const VERSION = 1;

	private static $registered = false;

	public static function register() {
		if (self::$registered || !function_exists('add_filter')) return;
		self::$registered = true;
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'localize_vars'), 40, 2);
	}

	/** The phrases (efb_var.text keys) admin-efb.js needs for these parts; a key already given is kept. */
	public static function texts() {
		return array(
			/* translators: BLD-01: the description of the "Done" button of the form builder's field settings panel (read by screen readers and shown as a tooltip) */
			'sbDoneTip' => __('Closes the settings. Your changes are already in the form; Save in the toolbar stores it.', 'easy-form-builder'),
		);
	}

	/** efb_admin_localize_vars: efb_var.text gets the phrases above (escaped like the other phrases of efb_var.text). */
	public static function localize_vars($vars, $context = '') {
		if (!is_array($vars)) return $vars;
		if (isset($vars['text']) && is_object($vars['text'])) {
			foreach (self::texts() as $k => $v) if (!isset($vars['text']->$k)) $vars['text']->$k = esc_html($v);
			return $vars;
		}
		$text = isset($vars['text']) && is_array($vars['text']) ? $vars['text'] : array();
		foreach (self::texts() as $k => $v) if (!isset($text[$k])) $text[$k] = esc_html($v);
		$vars['text'] = $text;
		return $vars;
	}
}
