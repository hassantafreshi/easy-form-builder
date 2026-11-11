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
