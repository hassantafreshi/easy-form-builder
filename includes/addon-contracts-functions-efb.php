<?php
/**
 * SPLIT §4.3: the contracts core keeps for the features that left it, and how an add-on announces it implements
 * one. An add-on adds itself on the filter efb_addon_contracts:
 *
 *   add_filter('efb_addon_contracts', function ($c) { $c['calc']['AdnCLC'] = 1; return $c; });
 *
 * efb_addon_contracts() answers {contract_id: {version, min, provider_addon, providers, active, outdated, core}}.
 * A provider whose version is below min is "outdated": core treats the add-on as absent (SPLIT §4.1) and the
 * owner sees "Update the add-on". An add-on that needs a newer contract than core has does not register
 * (its routes-efb.php checks version before anything else, SPLIT §12 rule 1).
 *
 * Contract details: docs/autopilot-integration.md "SPLIT"; docs/feature-gaps/SPLIT-core-addons.fa.md §4–§10.
 *
 * @package Emsfb
 */
defined('ABSPATH') || exit;

if (!function_exists('efb_addon_contract_versions')) {
	/**
	 * The version of each contract this core speaks, and the oldest provider version it accepts.
	 * "core" = core implements it itself (no add-on); otherwise an add-on must provide it.
	 */
	function efb_addon_contract_versions() {
		$fp = class_exists('\\Emsfb\\Form_Package') ? \Emsfb\Form_Package::API_VERSION : 1;
		return array(
			'calc'            => array('version' => 1, 'min' => 1, 'core' => false),
			'typed_signature' => array('version' => 1, 'min' => 1, 'core' => false),
			'address'         => array('version' => 1, 'min' => 1, 'core' => false),
			'booking'         => array('version' => 1, 'min' => 1, 'core' => false),
			// PAY-OUT: 1.1 = the add-on draws its field's builder settings (window.efbFieldPanels); 1.2 = it keeps its keys
			// on its own screen (a pay_<type> key of the settings row; core's Settings › Payments only links to it).
			'legacy_gateway'  => array('version' => 1.2, 'min' => 1, 'core' => false),
			'smart_tags_ext'  => array('version' => 1, 'min' => 1, 'core' => false),
			'form_package'    => array('version' => $fp, 'min' => 1, 'core' => class_exists('\\Emsfb\\Form_Package')),
			'payments'        => array('version' => defined('EMSFB_PAYMENTS_API') ? (int) EMSFB_PAYMENTS_API : 1, 'min' => 1, 'core' => class_exists('\\Emsfb\\Payments\\Registry')),
			'captcha'         => array('version' => 1, 'min' => 1, 'core' => function_exists('efb_captcha_api')),
			// F12b: an add-on CAPTCHA provider (Emsfb\Captcha\Provider::API); core keeps Google itself.
			'captcha_provider' => array('version' => 2, 'min' => 2, 'core' => false),
			// F20-core: the customer portal (EMSFB_PORTAL_API, efb_portal_core_api()); the add-on AdnPRT builds on it.
			'portal'          => array('version' => defined('EMSFB_PORTAL_API') ? (int) EMSFB_PORTAL_API : 1, 'min' => 1, 'core' => function_exists('efb_portal_core_api')),
		);
	}
}

if (!function_exists('efb_addon_contracts')) {
	/** @return array<string, array> every contract, its providers and whether it is usable now. */
	function efb_addon_contracts() {
		$providers = function_exists('apply_filters') ? apply_filters('efb_addon_contracts', array()) : array();
		$providers = is_array($providers) ? $providers : array();
		$out = array();
		foreach (efb_addon_contract_versions() as $id => $v) {
			$list = array();
			if (isset($providers[$id]) && is_array($providers[$id])) {
				foreach ($providers[$id] as $code => $ver) {
					if (is_string($code) && preg_match('/^(Adn[A-Za-z]{2,5}|core)$/', $code) && is_numeric($ver)) $list[$code] = (float) $ver;
				}
			}
			$ok = array_filter($list, function ($ver) use ($v) { return $ver >= $v['min'] && floor($ver) <= floor($v['version']); });
			$out[$id] = array(
				'version'        => $v['version'],
				'min'            => $v['min'],
				'core'           => (bool) $v['core'],
				'providers'      => $list,
				'provider_addon' => $ok ? (string) array_keys($ok)[0] : ($list ? (string) array_keys($list)[0] : null),
				'active'         => (bool) $v['core'] || (bool) $ok,
				'outdated'       => !$v['core'] && $list && !$ok,
			);
		}
		return $out;
	}
}

if (!function_exists('efb_addon_screen_own_notices')) {
	/**
	 * A focused add-on settings screen shows only its own notices: call it on the screen's load-{$hook}. The site-wide
	 * notices of Easy Form Builder and of its other add-ons (SPLIT, payment readiness, renewal, e-mail delivery …) stay
	 * on the plugin's other screens. Theirs: a callback of the Emsfb namespace or defined in the plugin's folder (add-ons
	 * live in its vendor/); a callback defined in $keep_dir (the add-on's own folder) is kept. Other plugins' and
	 * WordPress' notices are left as on any screen.
	 *
	 * @param string $keep_dir The add-on's folder ('' keeps none of the plugin's own).
	 * @return int How many notices were taken off this screen.
	 */
	function efb_addon_screen_own_notices($keep_dir = '') {
		global $wp_filter;
		if (empty($wp_filter['admin_notices']) || !is_object($wp_filter['admin_notices'])) return 0;
		$root = defined('EMSFB_PLUGIN_DIRECTORY') ? wp_normalize_path(EMSFB_PLUGIN_DIRECTORY) : "\0";
		$keep = is_string($keep_dir) && $keep_dir !== '' ? rtrim(wp_normalize_path($keep_dir), '/') . '/' : "\0";
		$n = 0;
		foreach ($wp_filter['admin_notices']->callbacks as $prio => $cbs) {
			foreach ($cbs as $cb) {
				$f = $cb['function'];
				try {
					$r = is_array($f) ? new \ReflectionMethod($f[0], $f[1]) : (is_string($f) && strpos($f, '::') ? new \ReflectionMethod($f) : new \ReflectionFunction($f));
				} catch (\ReflectionException $e) { continue; }
				$file = wp_normalize_path((string) $r->getFileName());
				$ns = $r instanceof \ReflectionMethod ? $r->getDeclaringClass()->getName() : $r->getNamespaceName();
				if ((stripos($ns, 'Emsfb') === 0 || strpos($file, $root) === 0) && strpos($file, $keep) !== 0) {
					remove_action('admin_notices', $f, $prio);
					$n++;
				}
			}
		}
		return $n;
	}
}

if (!function_exists('efb_addon_contract_active')) {
	/** Whether a contract has a usable implementation now (core's own, or an add-on with a compatible version). */
	function efb_addon_contract_active($id) {
		$c = efb_addon_contracts();
		return isset($c[$id]) && !empty($c[$id]['active']);
	}
}

if (!function_exists('efb_calc_api')) {
	/**
	 * SPLIT §6.2 (calc contract v1): the calculation engine's API, or null when no add-on provides it.
	 * {version: 1, plan_ok: callable(part): bool, json_needs_runtime: callable(json): bool, ...}
	 */
	function efb_calc_api() {
		$api = function_exists('apply_filters') ? apply_filters('efb_calc_api', null) : null;
		if (!is_array($api) || !isset($api['version']) || (int) $api['version'] < 1) return null;
		return $api;
	}
}

if (!function_exists('efb_form_calc_price_fields')) {
	/**
	 * SPLIT §6.3 rule 1: the calc fields of a form used as the amount to pay (type calc, calc_as_price 1), read from
	 * the raw rows (arrays or objects) without the calculation add-on. @return array<int, string> their id_.
	 */
	function efb_form_calc_price_fields($form) {
		$ids = array();
		if (!is_array($form)) return $ids;
		foreach ($form as $i => $r) {
			if ($i === 0) continue;
			if (is_object($r)) $r = get_object_vars($r);
			if (!is_array($r) || !isset($r['type'], $r['id_']) || $r['type'] !== 'calc') continue;
			if (isset($r['calc_as_price']) && in_array($r['calc_as_price'], array(1, '1', true), true) && is_scalar($r['id_'])) $ids[] = (string) $r['id_'];
		}
		return $ids;
	}
}

if (!function_exists('efb_payment_computed_items')) {
	/**
	 * SPLIT §6.3: the amounts of the calc fields used as a price, from the add-on (filter efb_payment_computed_items).
	 * {ok, items: [{id, label, value}]} or {ok:false, error, field_id}. A form without such a field: ok, no items.
	 * A form with one and no add-on (absent, off, contract too old, plan below the add-on's): pay_calc_unavailable,
	 * so the whole payment is refused (never a lower amount: SPLIT §20 item 1).
	 */
	function efb_payment_computed_items($form, array $rows, array $hidden = array()) {
		$ids = efb_form_calc_price_fields($form);
		if (!$ids) return array('ok' => true, 'items' => array());
		$res = efb_calc_api() !== null && function_exists('apply_filters') ? apply_filters('efb_payment_computed_items', null, $form, $rows, $hidden) : null;
		if (!is_array($res) || !array_key_exists('ok', $res)) return array('ok' => false, 'error' => 'pay_calc_unavailable', 'field_id' => $ids[0]);
		if (empty($res['ok'])) return array('ok' => false, 'error' => isset($res['error']) && is_string($res['error']) ? $res['error'] : 'price_not_computable', 'field_id' => isset($res['field_id']) ? (string) $res['field_id'] : $ids[0]);
		$items = array();
		foreach (isset($res['items']) && is_array($res['items']) ? $res['items'] : array() as $it) {
			if (!is_array($it) || !isset($it['id'], $it['value']) || !is_numeric($it['value'])) return array('ok' => false, 'error' => 'price_not_computable', 'field_id' => $ids[0]);
			$items[] = array('id' => (string) $it['id'], 'label' => isset($it['label']) ? (string) $it['label'] : '', 'value' => (string) $it['value']);
		}
		return array('ok' => true, 'items' => $items);
	}
}

if (!function_exists('efb_addon_state')) {
	/** Why an add-on is not usable now (null = usable; 'addon', 'plan', 'update'). */
	function efb_addon_state($code) {
		return class_exists('\\Emsfb\\Addon_Catalog') ? \Emsfb\Addon_Catalog::state($code, function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null) : 'addon';
	}
}

/* ------------------------------------------------------------------ legacy_gateway v1 (SPLIT §5.2) */

if (!function_exists('efb_legacy_gateway_ready')) {
	/**
	 * Whether a Stripe / PayPal / PersiaPay field can take a payment now: its add-on is on, this PHP can run it and it
	 * announced the legacy_gateway contract (an add-on from before SPLIT does not, and is treated as absent).
	 */
	function efb_legacy_gateway_ready($type) {
		if (!class_exists('\\Emsfb\\Split') || !class_exists('\\Emsfb\\Addon_Catalog')) return false;
		$map = \Emsfb\Split::legacy_types();
		if (!isset($map[$type])) return false;
		$code = $map[$type];
		if (!\Emsfb\Addon_Catalog::flag_on($code, function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null)) return false;
		if (function_exists('emsfb_is_addon_compatible_efb') && !emsfb_is_addon_compatible_efb($code)) return false;
		$c = efb_addon_contracts();
		return isset($c['legacy_gateway']['providers'][$code]) && !empty($c['legacy_gateway']['active']);
	}
}

if (!function_exists('efb_gate_prepare')) {
	/** F11's form gate for an add-on's own payment endpoint (the same gate and places as the submit). */
	function efb_gate_prepare($form_id, $request = null) {
		$pub = class_exists('\\Emsfb\\_Public') ? \Emsfb\_Public::$instance_efb : null;
		if ($pub && method_exists($pub, 'gate_prepare_for_endpoint_efb')) return $pub->gate_prepare_for_endpoint_efb((int) $form_id, $request);
		return null;
	}
}

if (!function_exists('efb_legacy_server_price')) {
	/**
	 * The amount of a legacy-gateway order computed by the server from the stored form (price fields, priced choices,
	 * logic prices, calc amounts; a lowered price is refused): fun_validation_pay_elements_efb() of core.
	 * @return array {price_total, email, valobj} (refusals answer the request themselves, as before).
	 */
	function efb_legacy_server_price($rows, $form) {
		$pub = class_exists('\\Emsfb\\_Public') ? \Emsfb\_Public::$instance_efb : null;
		return $pub ? $pub->fun_validation_pay_elements_efb($rows, $form) : array('price_total' => 0, 'email' => '', 'valobj' => array());
	}
}
