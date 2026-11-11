<?php
/**
 * Plan helpers for every server-side gate (README "shared decisions" 3, F18 §14, SitePilot
 * contract §4.4), all answered by the one registry in \Emsfb\Builder_Api::capabilities().
 * is_efb_pro(1) is true for Free Plus as well; use these for "Pro only".
 */
if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('efb_current_package_efb')) {
	/** 0|1|2|3 from emsfb_pro (-1 and 10 = 2); reads the option only. */
	function efb_current_package_efb() { return \Emsfb\Builder_Api::current_package(); }
}
if (!function_exists('efb_plan_chosen_efb')) {
	/** F18: false while no plan was picked yet (emsfb_pro -1, 10 or missing). Reads the option only. */
	function efb_plan_chosen_efb() {
		$v = function_exists('get_option') ? get_option('emsfb_pro', null) : null;
		return is_numeric($v) && in_array((int) $v, array(0, 1, 2, 3), true);
	}
}
if (!function_exists('efb_plan_efb')) {
	/** free | free_plus | pro | pro_pending */
	function efb_plan_efb() { return \Emsfb\Builder_Api::plan(); }
}
if (!function_exists('efb_plan_at_least_efb')) {
	/** $required: 'free_plus' | 'pro', or 3 | 1 as EFB numbers them. */
	function efb_plan_at_least_efb($required) { return \Emsfb\Builder_Api::plan_at_least($required); }
}
if (!function_exists('efb_feature_registry_efb')) {
	/**
	 * Every feature key with its plan (package free|free_plus|pro), add-on and label key; the
	 * features part of capabilities_efb(). $package: answer for that plan instead of this site's.
	 * Filter efb_feature_registry (F18 §14).
	 */
	function efb_feature_registry_efb($package = null) {
		$c = \Emsfb\Builder_Api::capabilities($package === null ? array() : array('package' => (int) $package));
		$r = isset($c['features']) ? $c['features'] : array();
		return function_exists('apply_filters') ? (array) apply_filters('efb_feature_registry', $r, $package) : $r;
	}
}
if (!function_exists('efb_feature_required_package_efb')) {
	/** 0 = every plan, 3 = Free Plus, 1 = Pro; an unknown key = 1 (F18 §14). */
	function efb_feature_required_package_efb($feature) {
		$r = efb_feature_registry_efb();
		$pkg = isset($r[$feature]['package']) ? $r[$feature]['package'] : 'pro';
		$n = $pkg === 'free' ? 0 : ($pkg === 'free_plus' ? 3 : 1);
		return function_exists('apply_filters') ? (int) apply_filters('efb_feature_required_package', $n, $feature) : $n;
	}
}
if (!function_exists('efb_feature_available_efb')) {
	/** Whether this site (or $package: 0|1|2|3) can use the feature now: plan and add-on. */
	function efb_feature_available_efb($feature, $package = null) {
		$r = efb_feature_registry_efb($package);
		if (!isset($r[$feature])) return false;
		$e = $r[$feature];
		$pkg = $package === null ? efb_current_package_efb() : (int) $package;
		if (!\Emsfb\Builder_Api::plan_at_least(isset($e['package']) ? $e['package'] : 'pro', $pkg)) return false;
		if (empty($e['addon'])) return true;
		$c = \Emsfb\Builder_Api::capabilities(array('package' => $pkg));
		return !empty($c['addons'][$e['addon']]);
	}
}
if (!function_exists('efb_can_change_plan_efb')) {
	/**
	 * F18 §8: may the current user change the site's plan (which can add the public Free Plus
	 * credit)? The plugin's own admin permission (manage_options or the Emsfb capability), filter
	 * efb_can_change_plan. Read by efb_save_plan_selection and shown to the dialog as
	 * efb_var.can_change_plan. Never true for cron or REST requests (no human click there).
	 */
	function efb_can_change_plan_efb() {
		if ((function_exists('wp_doing_cron') && wp_doing_cron()) || (defined('REST_REQUEST') && REST_REQUEST)) return false;
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$ok = $fn && method_exists($fn, 'user_permission_efb_admin_dashboard') ? (bool) $fn->user_permission_efb_admin_dashboard()
			: (function_exists('current_user_can') && current_user_can('manage_options'));
		return function_exists('apply_filters') ? (bool) apply_filters('efb_can_change_plan', $ok) : $ok;
	}
}
if (!function_exists('efb_logic_validate_rules')) {
	/**
	 * B6 (AutoPilot): check conditional-logic rules before writing them. Errors carry a stable
	 * code and the rule id; without the Conditional Logic add-on the result has logic_addon_missing.
	 * Contract: docs/logic-rules-schema.md.
	 */
	function efb_logic_validate_rules( array $form_items, array $args = array() ) {
		return \Emsfb\Builder_Api::logic_validate_rules($form_items, $args);
	}
}
