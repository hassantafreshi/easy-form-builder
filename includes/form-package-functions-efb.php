<?php
/**
 * F15-D: discovery of the form package API for other plugins (Easy Form Builder Data Tools, add-ons).
 *
 *   $api = function_exists('efb_form_package_api') ? efb_form_package_api() : null;
 *   if ($api && $api['version'] >= 1) { $pkg = \Emsfb\Form_Package::export($form_id); … }
 *
 * Contract: docs/form-package-contract.en.md.
 */
defined('ABSPATH') || exit;

if (!function_exists('efb_form_package_api')) {
	/** {version, schema, extras_version, class, functions, filters, actions, limits, plugin_version} or null. */
	function efb_form_package_api() {
		return class_exists('\\Emsfb\\Form_Package') ? \Emsfb\Form_Package::api() : null;
	}
}
