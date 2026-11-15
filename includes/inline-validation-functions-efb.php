<?php
/**
 * F16 / F20 P21: inline validation and the error summary as a core contract (version 1,
 * docs/a11y/inline-validation-contract.en.md). The page part is window.EFBInline (public/assets/js/inline-efb.js);
 * these are its server twins: which version is here, the script and its phrases for a page that is not a form
 * (the customer portal's sign-in and edit screens), and the shape of a refusal that names a field.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('efb_inline_api')) {
	/** ['version' => 1, 'features' => [...], 'script' => handle, 'global' => 'EFBInline']. */
	function efb_inline_api() {
		return array('version' => 1, 'features' => array('field_error', 'live_region', 'summary', 'focus', 'server_errors', 'timing', 'unsaved', 'digits', 'list'), 'script' => 'efb-inline-js', 'global' => 'EFBInline');
	}
}

if (!function_exists('efb_inline_texts')) {
	/** The phrases EFBInline reads (window.efb_inline_text on a page without a form). */
	function efb_inline_texts() {
		$keys = array('a11yErrPrefix', 'a11yErrSumOne', 'a11yErrSumN', 'a11yStepNow', 'a11yStay', 'enterTheValueThisField');
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		return $fn ? (array) $fn->text_efb($keys) : array();
	}
}

if (!function_exists('efb_inline_enqueue')) {
	/** Enqueue the module (and its phrases unless the page has a form, whose own text object it reads). Returns the handle. */
	function efb_inline_enqueue($with_texts = true) {
		$f = EMSFB_PLUGIN_DIRECTORY . 'public/assets/js/inline-efb.js';
		wp_register_script('efb-inline-js', EMSFB_PLUGIN_URL . 'public/assets/js/inline-efb.js', array(), is_readable($f) ? (string) filemtime($f) : EMSFB_PLUGIN_VERSION, true);
		wp_enqueue_script('efb-inline-js');
		if ($with_texts) wp_add_inline_script('efb-inline-js', 'window.efb_inline_text=' . wp_json_encode(efb_inline_texts()) . ';', 'before');
		return 'efb-inline-js';
	}
}

if (!function_exists('efb_inline_error')) {
	/**
	 * A refusal that names one field, in the shape the form's submit route answers (EFBInline.applyServer() shows it):
	 * ['success' => false, 'm' => message, 'field_id' => id, 'code' => code] plus $extra (row_index, option_id …).
	 */
	function efb_inline_error($field_id, $message, $code = '', $extra = array()) {
		return array_merge(is_array($extra) ? $extra : array(), array('success' => false, 'm' => (string) $message, 'field_id' => (string) $field_id, 'code' => (string) $code));
	}
}

if (!function_exists('efb_inline_errors')) {
	/** Several fields at once: $errors = [[field_id, message, code?, label?], …] → ['success' => false, 'm' => first message, 'errors' => […]]. */
	function efb_inline_errors(array $errors, $code = '') {
		$out = array();
		foreach ($errors as $e) {
			if (!is_array($e) || !isset($e[0], $e[1])) continue;
			$out[] = array('field_id' => (string) $e[0], 'm' => (string) $e[1], 'code' => isset($e[2]) && $e[2] !== '' ? (string) $e[2] : (string) $code, 'label' => isset($e[3]) ? (string) $e[3] : '');
		}
		return array('success' => false, 'm' => $out ? $out[0]['m'] : '', 'code' => (string) $code, 'errors' => $out);
	}
}

if (!function_exists('efb_form_defaults_efb')) {
	/** The F16 keys of a new form (builder, templates, importers, the API): val_timing blur, err_summary 1, warn_unsaved for several steps. */
	function efb_form_defaults_efb($steps = 1) {
		return class_exists('\\Emsfb\\A11y') ? \Emsfb\A11y::defaults($steps) : array();
	}
}
