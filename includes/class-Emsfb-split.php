<?php
/**
 * SPLIT: the glue core keeps for the features that became add-ons (docs/feature-gaps/SPLIT-core-addons.fa.md).
 *
 *  - the capability registry: every moved entry names its add-on (addon, available_now, reason addon | plan | update)
 *    and capabilities.split = {v, moved, contracts, addons} (§4.3, §15);
 *  - payment readiness: a form whose amount comes from a calculation and no calculation add-on → pay_calc_unavailable
 *    (§6.3 rule 5);
 *  - forms/{id}/schema: a field of an absent add-on says assistant none and why (§15.2);
 *  - the upgrade scan (§4.5): every form is read once (200 per run, WP-Cron); each add-on a form needs is switched on
 *    and its download queued through the existing add-on recovery when the plan allows; a site that used a feature
 *    before it became an add-on keeps it (open question 2, approved by owner 2026-11-14); the result is in the option
 *    emsfb_split_scan for the notice, the forms list badge and "Try again";
 *  - the admin notice and the forms list badge ("Needs the X add-on").
 *
 * @package Emsfb
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Split {

	const VERSION = 1;
	const OPTION = 'emsfb_split_scan';
	const EVENT = 'emsfb_split_scan_efb';
	const BATCH = 200;

	/** Add-ons the upgrade scan installs by itself (AdnGEO needs the owner's own API key: notice only). */
	const AUTO_INSTALL = array('AdnCLC', 'AdnTSG', 'AdnADR', 'AdnBEF');

	/** @var callable|null tests: replaces queue_addon_recovery_efb(). */
	public static $installer = null;

	public static function register() {
		if (!function_exists('add_filter')) return;
		add_filter('efb_addon_contracts', array(__CLASS__, 'core_contracts'), 1);
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 99, 2);
		add_filter('efb_payment_readiness', array(__CLASS__, 'payment_readiness'), 30, 3);
		add_filter('efb_form_schema_field', array(__CLASS__, 'schema_field'), 40, 3);
		add_action(self::EVENT, array(__CLASS__, 'run_scan_event'));
		add_action('admin_init', array(__CLASS__, 'maybe_schedule_scan'));
		add_action('admin_notices', array(__CLASS__, 'admin_notices'));
		add_action('wp_ajax_efb_split_retry', array(__CLASS__, 'ajax_retry'));
		add_action('wp_ajax_efb_split_dismiss', array(__CLASS__, 'ajax_dismiss'));
	}

	/* ------------------------------------------------------------------------- contracts core still has */

	/**
	 * The contracts whose feature is still part of this core (provider "core"). Each SPLIT subtask removes its
	 * line when the code moves to the add-on, which then announces itself with its own code.
	 */
	public static function core_contracts($c) {
		$c = is_array($c) ? $c : array();
		// The booking editor is still in core (until SPLIT-F).
		foreach (array('booking') as $id) if (!self::addon_provides($id)) $c[$id]['core'] = 1;
		return $c;
	}

	/** Whether an add-on (not core) already registered the contract (it then wins). */
	private static function addon_provides($contract) {
		static $busy = false;
		if ($busy || !function_exists('apply_filters')) return false;
		$busy = true;
		$all = apply_filters('efb_addon_contracts', array());
		$busy = false;
		foreach (isset($all[$contract]) && is_array($all[$contract]) ? $all[$contract] : array() as $code => $v) if ($code !== 'core') return true;
		return false;
	}

	/* --------------------------------------------------------------------------------------------- types */

	/** Legacy gateway field types and their add-on (contract legacy_gateway v1, filter efb_legacy_gateway_types). */
	public static function legacy_types() {
		$map = array('stripe' => 'AdnSPF', 'paypal' => 'AdnPAP', 'persiaPay' => 'AdnPPF', 'zarinPal' => 'AdnPPF');
		if (function_exists('apply_filters')) {
			$f = apply_filters('efb_legacy_gateway_types', $map);
			if (is_array($f)) {
				$map = array();
				foreach ($f as $t => $c) if (is_string($t) && is_string($c) && preg_match('/^Adn[A-Za-z]{2,5}$/', $c)) $map[$t] = $c;
			}
		}
		return $map;
	}

	/** The add-on codes a form needs (moved features it uses), in catalogue order. @return array<int, string> */
	public static function needed_addons($rows) {
		if (!is_array($rows)) return array();
		$codes = class_exists('\\Emsfb\\Addon_Keys') ? Addon_Keys::used_addons($rows) : array();
		$legacy = self::legacy_types();
		foreach ($rows as $i => $r) {
			if ($i === 0) continue;
			if (is_object($r)) $r = get_object_vars($r);
			if (is_array($r) && isset($r['type']) && is_string($r['type']) && isset($legacy[$r['type']])) $codes[] = $legacy[$r['type']];
		}
		$codes = array_values(array_unique($codes));
		$order = class_exists('\\Emsfb\\Addon_Catalog') ? Addon_Catalog::keys() : $codes;
		usort($codes, function ($a, $b) use ($order) { return (int) array_search($a, $order, true) - (int) array_search($b, $order, true); });
		return $codes;
	}

	/** The state of an add-on for this request: null (usable), 'addon', 'plan', 'update'. Core-provided contracts are usable. */
	public static function addon_state($code) {
		if (!class_exists('\\Emsfb\\Addon_Catalog')) return 'addon';
		$e = Addon_Catalog::get($code);
		// A gateway on the legacy bridge: on and loaded but without the contract = an add-on from before SPLIT.
		if ($e && $e['contract'] === 'legacy_gateway') {
			$settings = function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null;
			if (!Addon_Catalog::flag_on($code, $settings)) return 'addon';
			if (function_exists('emsfb_is_addon_compatible_efb') && !emsfb_is_addon_compatible_efb($code)) return 'addon';
			$c = function_exists('efb_addon_contracts') ? efb_addon_contracts() : array();
			return isset($c['legacy_gateway']['providers'][$code]) ? null : 'update';
		}
		if ($e && $e['contract'] && function_exists('efb_addon_contracts')) {
			$c = efb_addon_contracts();
			if (isset($c[$e['contract']]['providers']['core'])) return null; // still part of core on this version
			if ($e['loader'] === 'routes' && !empty($c[$e['contract']]['active']) && isset($c[$e['contract']]['providers'][$code])) {
				return Addon_Catalog::plan_allows($code) ? null : 'plan';
			}
		}
		return Addon_Catalog::state($code, function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null);
	}

	/* --------------------------------------------------------------------------------------- registry */

	/** efb_capabilities: the add-on of every moved entry, and capabilities.split. */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out)) return $out;
		$contracts = function_exists('efb_addon_contracts') ? efb_addon_contracts() : array();
		$moved = class_exists('\\Emsfb\\Addon_Catalog') ? Addon_Catalog::MOVED : array();
		$addons = array();
		foreach (array_unique(array_values($moved)) as $code) {
			$e = class_exists('\\Emsfb\\Addon_Catalog') ? Addon_Catalog::get($code) : null;
			if (!$e) continue;
			$state = self::addon_state($code);
			$in_core = $e['contract'] && isset($contracts[$e['contract']]['providers']['core']);
			$addons[$code] = array(
				'name'      => $e['name'],
				'package'   => Builder_Api::normalize_plan($e['package']),
				'contract'  => $e['contract'],
				'installed' => Addon_Catalog::flag_on($code, isset($args['settings']) ? $args['settings'] : (function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null)),
				'active'    => $state === null,
				'state'     => $state === null ? 'active' : $state,
				'in_core'   => $in_core,
				'can_install' => Addon_Catalog::plan_allows($code),
			);
		}
		if (isset($out['features']) && is_array($out['features'])) {
			foreach ($moved as $key => $code) {
				if (!isset($addons[$code])) continue;
				if (!isset($out['features'][$key]) || !is_array($out['features'][$key])) {
					$out['features'][$key] = array('kind' => strpos($key, 'field:') === 0 ? 'field' : 'setting', 'package' => $addons[$code]['package'], 'available_now' => true, 'reason' => null);
				}
				$f = $out['features'][$key];
				if ($addons[$code]['in_core'] && !in_array($code, array('AdnSPF', 'AdnPAP', 'AdnPPF'), true)) {
					// Still part of core on this version (before its add-on takes over).
					$f['addon'] = isset($f['addon']) ? $f['addon'] : null;
					$out['features'][$key] = $f;
					continue;
				}
				$f['addon'] = $code;
				$f['addon_package'] = $addons[$code]['package'];
				$f['addon_installed'] = $addons[$code]['installed'];
				$f['addon_active'] = $addons[$code]['active'];
				$st = $addons[$code]['state'];
				$plan_ok = !isset($f['reason']) || $f['reason'] !== 'plan';
				if ($st !== 'active') {
					$f['available_now'] = false;
					$f['reason'] = $plan_ok ? ($st === 'plan' ? 'plan' : ($st === 'update' ? 'update' : 'addon')) : 'plan';
				}
				$out['features'][$key] = $f;
			}
		}
		$out['split'] = array(
			'v'         => self::VERSION,
			'moved'     => $moved,
			'contracts' => $contracts,
			'addons'    => $addons,
			'codes'     => array('save' => array('addon_keys_kept', 'addon_keys_dropped'), 'builder' => array('addon_required'), 'payment' => array('pay_calc_unavailable', 'pay_not_ready', 'pay_addon_inactive'),
				'import' => array('addon_inactive'), 'logic' => array('logic_ref_addon_inactive')),
			'install'   => array('ajax' => 'add_addons_Emsfb', 'page' => function_exists('admin_url') ? admin_url('admin.php?page=Emsfb_addon') : 'admin.php?page=Emsfb_addon', 'human_only' => true),
			'data_tools' => array('slug' => 'easy-form-builder-data-tools', 'active' => function_exists('efbdt_core_api') || defined('EFBDT_VERSION'), 'replaces' => array('cf7_importer', 'form_export_import')),
		);
		return $out;
	}

	/** efb_payment_readiness: the amount comes from a calculation and no calculation add-on can compute it. */
	public static function payment_readiness($out, $form, $form_id = 0) {
		if (!is_array($out) || empty($out['has_payment']) || !is_array($form)) return $out;
		if (!function_exists('efb_form_calc_price_fields') || !function_exists('efb_calc_api')) return $out;
		$ids = efb_form_calc_price_fields($form);
		if (!$ids || efb_calc_api() !== null) return $out;
		$name = class_exists('\\Emsfb\\Addon_Catalog') ? Addon_Catalog::name('AdnCLC') : 'AdnCLC';
		foreach ($ids as $id) {
			$out['issues'][] = array('code' => 'pay_calc_unavailable', 'level' => 'error', 'field_id' => $id, 'gateway' => '', 'addon' => 'AdnCLC',
				'm' => function_exists('__') ? __('The amount of this form is calculated by an add-on that is not active. Payment is paused.', 'easy-form-builder') : 'The amount of this form is calculated by an add-on that is not active. Payment is paused.',
				'fix' => array('kind' => 'addon', 'addon' => 'AdnCLC', 'name' => $name, 'url' => function_exists('admin_url') ? admin_url('admin.php?page=Emsfb_addon') : '', 'label' => function_exists('__') ? __('Install', 'easy-form-builder') : 'Install'));
		}
		$out['ready'] = false;
		return $out;
	}

	/** efb_form_schema_field: a field an absent add-on draws cannot be filled by an assistant. */
	public static function schema_field($e, $row, $form_id = 0) {
		if (!is_array($e) || !is_array($row)) return $e;
		$code = self::field_addon($row);
		if ($code === '') return $e;
		$st = self::addon_state($code);
		if ($st === null) return $e;
		$e['assistant'] = 'none';
		$e['unavailable'] = array('addon' => $code, 'reason' => $st);
		return $e;
	}

	/** Fields drawn by an add-on that is not usable now are not required (their notice is in their place). */
	public static function unrequire_absent(array $rows) {
		$legacy = self::legacy_types();
		foreach ($rows as $i => $r) {
			if ($i === 0 || !is_array($r) || empty($r['required'])) continue;
			$type = isset($r['type']) && is_string($r['type']) ? $r['type'] : '';
			if ($type === '' || isset($legacy[$type])) continue; // a payment field keeps its own refusal (pay_not_ready)
			$code = self::field_addon($r);
			if ($code !== '' && self::addon_state($code) !== null) $rows[$i]['required'] = false;
		}
		return $rows;
	}

	/** The add-on that draws a whole field (calc, maps, the legacy gateways), or ''. */
	public static function field_addon(array $row) {
		$type = isset($row['type']) && is_string($row['type']) ? $row['type'] : '';
		if ($type === '') return '';
		$legacy = self::legacy_types();
		if (isset($legacy[$type])) return $legacy[$type];
		foreach (class_exists('\\Emsfb\\Addon_Keys') ? Addon_Keys::manifest() : array() as $code => $m) {
			if (in_array($type, $m['types'], true)) return $code;
		}
		return '';
	}

	/* ------------------------------------------------------------------------------------ upgrade scan */

	public static function scan_state() {
		$s = function_exists('get_option') ? get_option(self::OPTION, array()) : array();
		return is_array($s) ? $s : array();
	}

	/** The SPLIT add-ons whose feature has left this core (their contract has no "core" provider). */
	public static function moved_codes() {
		$c = function_exists('efb_addon_contracts') ? efb_addon_contracts() : array();
		$out = array();
		foreach (self::AUTO_INSTALL as $code) {
			$e = class_exists('\\Emsfb\\Addon_Catalog') ? Addon_Catalog::get($code) : null;
			if ($e && $e['contract'] && !isset($c[$e['contract']]['providers']['core'])) $out[] = $code;
		}
		return $out;
	}

	/** A fresh scan state; grandfathering is allowed for the add-ons whose feature left core since the last scan. */
	private static function fresh_state($old) {
		$moved = self::moved_codes();
		$prev = isset($old['moved']) && is_array($old['moved']) ? $old['moved'] : array();
		return array('v' => self::VERSION, 'at' => time(), 'done' => false, 'cursor' => 0, 'forms' => array(),
			'grandfather' => isset($old['grandfather']) && is_array($old['grandfather']) ? $old['grandfather'] : array(),
			'first' => !isset($old['v']), 'moved' => $moved, 'newly' => isset($old['v']) ? array_values(array_diff($moved, $prev)) : $moved,
			'queued' => array(), 'blocked' => array());
	}

	/** admin_init: start the scan once per site, again when another feature has left core, and after "Try again". */
	public static function maybe_schedule_scan() {
		$s = self::scan_state();
		$stale = !isset($s['v']) || (int) $s['v'] < self::VERSION || !isset($s['moved']) || $s['moved'] !== self::moved_codes();
		if (!$stale && !empty($s['done'])) return;
		if ($stale) {
			$s = self::fresh_state($s);
			update_option(self::OPTION, $s, false);
		}
		if (function_exists('wp_next_scheduled') && !wp_next_scheduled(self::EVENT) && function_exists('wp_schedule_single_event')) {
			wp_schedule_single_event(time() + 5, self::EVENT);
		}
	}

	/** Cron: one batch, then the next one is scheduled until every form is read. */
	public static function run_scan_event() {
		$r = self::scan_batch();
		if (empty($r['done']) && function_exists('wp_schedule_single_event')) wp_schedule_single_event(time() + 10, self::EVENT);
	}

	/** Reads every form at once (tests, WP-CLI, ctl.php): loops scan_batch() until done. */
	public static function scan_all() {
		$s = self::scan_state();
		if (!isset($s['v']) || !empty($s['done']) || !isset($s['moved']) || $s['moved'] !== self::moved_codes()) {
			$s = self::fresh_state($s);
			update_option(self::OPTION, $s, false);
		}
		$guard = 0;
		do { $r = self::scan_batch(); } while (empty($r['done']) && ++$guard < 10000);
		return self::scan_state();
	}

	/** One batch of BATCH forms after the cursor. @return array the state. */
	public static function scan_batch() {
		global $wpdb;
		$s = self::scan_state();
		if (!isset($s['v'])) $s = self::fresh_state(array());
		if (!empty($s['done']) || !is_object($wpdb)) return $s;
		$t = $wpdb->prefix . 'emsfb_form';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
		$list = $wpdb->get_results($wpdb->prepare("SELECT form_id, form_structer FROM `{$t}` WHERE form_id > %d ORDER BY form_id ASC LIMIT %d", (int) $s['cursor'], self::BATCH));
		foreach ((array) $list as $f) {
			$id = (int) $f->form_id;
			$s['cursor'] = max((int) $s['cursor'], $id);
			$raw = isset($f->form_structer) ? (string) $f->form_structer : '';
			if ($raw === '') continue;
			$json = function_exists('emsfb_json_normalize_efb') ? emsfb_json_normalize_efb($raw) : str_replace('\\"', '"', $raw);
			$rows = function_exists('emsfb_json_decode_efb') ? emsfb_json_decode_efb($json, true) : json_decode($json, true);
			foreach (self::needed_addons($rows) as $code) {
				if (!isset($s['forms'][$code])) $s['forms'][$code] = array();
				if (!in_array($id, $s['forms'][$code], true)) $s['forms'][$code][] = $id;
			}
		}
		if (count((array) $list) < self::BATCH) {
			$s['done'] = true;
			$s['at'] = time();
			$s = self::finalize($s);
		}
		update_option(self::OPTION, $s, false);
		return $s;
	}

	/** Every moved add-on a form needs is switched on and its download queued (existing add-on recovery). */
	public static function finalize(array $s) {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$settings = function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null;
		$turn_on = array();
		$moved = isset($s['moved']) && is_array($s['moved']) ? $s['moved'] : self::moved_codes();
		$newly = isset($s['newly']) && is_array($s['newly']) ? $s['newly'] : array();
		foreach (isset($s['forms']) && is_array($s['forms']) ? $s['forms'] : array() as $code => $ids) {
			// Only add-ons whose feature has left this core are installed (a feature still in core needs nothing).
			if (!$ids || !in_array($code, self::AUTO_INSTALL, true) || !in_array($code, $moved, true)) continue;
			if (class_exists('\\Emsfb\\Addon_Catalog') && Addon_Catalog::flag_on($code, $settings)) continue;
			$allowed = class_exists('\\Emsfb\\Addon_Catalog') ? Addon_Catalog::plan_allows($code) : false;
			if (!$allowed && (!empty($s['first']) || in_array($code, $newly, true))) {
				// The site used the feature on its plan before it became an add-on: it keeps it (open question 2).
				if (!in_array($code, $s['grandfather'], true)) $s['grandfather'][] = $code;
				update_option(self::OPTION, $s, false);
				$allowed = true;
			}
			if (!$allowed) { $s['blocked'][$code] = 'plan'; continue; }
			$turn_on[] = $code;
		}
		if ($turn_on) {
			if (is_object($settings)) {
				foreach ($turn_on as $code) $settings->$code = 1;
				if ($fn && method_exists($fn, 'set_setting_Emsfb')) $fn->set_setting_Emsfb($settings, isset($settings->emailSupporter) ? $settings->emailSupporter : '');
			}
			foreach ($turn_on as $code) if (function_exists('update_option')) update_option('emsfb_addon_' . $code, 1);
			$q = self::$installer ? call_user_func(self::$installer, array('source' => 'split_scan', 'addons' => $turn_on))
				: ($fn && method_exists($fn, 'queue_addon_recovery_efb') ? $fn->queue_addon_recovery_efb(array('source' => 'split_scan', 'addon' => implode(',', $turn_on))) : array('queued' => false, 'reason' => 'no_installer'));
			$s['queued'] = array_values(array_unique(array_merge(isset($s['queued']) ? $s['queued'] : array(), $turn_on)));
			$s['install'] = is_array($q) ? $q : array('queued' => (bool) $q);
		}
		$s['first'] = false;
		$s['newly'] = array();
		return $s;
	}

	/** "Try again": read the forms again and queue the downloads again. */
	public static function retry() {
		$s = self::scan_state();
		$s['done'] = false;
		$s['cursor'] = 0;
		$s['forms'] = array();
		$s['queued'] = array();
		$s['blocked'] = array();
		$s['first'] = false;
		$s['newly'] = array();
		$s['moved'] = self::moved_codes();
		unset($s['dismissed']);
		if (function_exists('update_option')) update_option(self::OPTION, $s, false);
		if (function_exists('delete_transient')) { delete_transient('emsfb_addon_recovery_lock'); delete_transient('emsfb_addons_dl_backoff'); }
		return self::scan_all();
	}

	public static function ajax_retry() {
		if (!function_exists('check_ajax_referer') || !check_ajax_referer('efb_split', 'nonce', false) || !current_user_can('manage_options')) {
			wp_send_json_error(array('m' => 'forbidden'), 403);
		}
		$s = self::retry();
		wp_send_json_success(array('forms' => self::forms_needing(), 'queued' => isset($s['queued']) ? $s['queued'] : array()));
	}

	public static function ajax_dismiss() {
		if (!function_exists('check_ajax_referer') || !check_ajax_referer('efb_split', 'nonce', false) || !current_user_can('manage_options')) {
			wp_send_json_error(array('m' => 'forbidden'), 403);
		}
		update_user_meta(get_current_user_id(), 'efb_split_notice_dismissed', md5(wp_json_encode(self::forms_needing())));
		wp_send_json_success(array());
	}

	/* ------------------------------------------------------------------------------ notice and badge */

	/**
	 * The forms that use an add-on that is not usable now: form_id => [{addon, name, state}].
	 * From the scan (no form is read here) and the live add-on state.
	 */
	public static function forms_needing() {
		$s = self::scan_state();
		$out = array();
		foreach (isset($s['forms']) && is_array($s['forms']) ? $s['forms'] : array() as $code => $ids) {
			$st = self::addon_state($code);
			if ($st === null) continue;
			foreach ((array) $ids as $id) {
				$out[(int) $id][] = array('addon' => $code, 'name' => class_exists('\\Emsfb\\Addon_Catalog') ? Addon_Catalog::name($code) : $code, 'state' => $st);
			}
		}
		ksort($out);
		return $out;
	}

	/** One line per add-on: name, forms, why, and the buttons (Install / Try again / plan). '' when nothing is missing. */
	public static function admin_notice_html(array $forms) {
		if (!$forms) return '';
		$by = array();
		foreach ($forms as $id => $list) foreach ($list as $a) {
			if (!isset($by[$a['addon']])) $by[$a['addon']] = array('name' => $a['name'], 'state' => $a['state'], 'forms' => array());
			$by[$a['addon']]['forms'][] = (int) $id;
		}
		$s = self::scan_state();
		$h = '<div class="efb notice notice-warning is-dismissible efb-split-notice" id="efbSplitNotice" data-nonce="' . esc_attr(wp_create_nonce('efb_split')) . '" role="region" aria-label="' . esc_attr__('Add-ons your forms need', 'easy-form-builder') . '">'
			. '<p style="unicode-bidi:plaintext"><strong>' . esc_html__('Some of your forms use features that are now add-ons.', 'easy-form-builder') . '</strong> '
			. esc_html__('The forms still work and their settings are kept; install the add-on to use the feature again.', 'easy-form-builder') . '</p><ul style="list-style:disc;padding-inline-start:20px">';
		foreach ($by as $code => $a) {
			$n = count($a['forms']);
			/* translators: %s: name of an Easy Form Builder add-on (a product name) */
			$need = $a['state'] === 'update' ? sprintf(__('Update %s to keep using it with this version.', 'easy-form-builder'), '%NAME%') : sprintf(__('Needs the %s add-on', 'easy-form-builder'), '%NAME%');
			$need = str_replace('%NAME%', '<bdi>' . esc_html($a['name']) . '</bdi>', esc_html($need));
			/* translators: %d: number of forms */
			$h .= '<li data-addon="' . esc_attr($code) . '" style="unicode-bidi:plaintext">' . $need . ' · ' . esc_html(sprintf(_n('%d form', '%d forms', $n, 'easy-form-builder'), $n));
			if ($a['state'] === 'plan') {
				$h .= ' · <a class="efb-split-plan" href="' . esc_url(admin_url('admin.php?page=Emsfb_addon')) . '">' . esc_html__('See the plans', 'easy-form-builder') . '</a>';
			} else {
				$h .= ' · <a class="button button-small efb-split-install" style="min-height:44px;line-height:42px" data-addon="' . esc_attr($code) . '" href="' . esc_url(admin_url('admin.php?page=Emsfb_addon&efb_install=' . rawurlencode($code))) . '">' . esc_html__('Install', 'easy-form-builder') . '</a>';
			}
			$h .= '</li>';
		}
		$h .= '</ul>';
		if (!empty($s['queued']) && isset($s['install']['queued']) && !$s['install']['queued']) {
			$h .= '<p style="unicode-bidi:plaintext">' . esc_html__('The add-ons could not be downloaded automatically (no connection to the add-on server).', 'easy-form-builder') . ' '
				. '<button type="button" class="button button-small efb-split-retry" style="min-height:44px">' . esc_html__('Try again', 'easy-form-builder') . '</button> '
				. '<a href="https://whitestudio.team/addons" target="_blank" rel="noopener">' . esc_html__('Download manually', 'easy-form-builder') . '</a></p>';
		}
		$h .= '</div>';
		$h .= '<script>(function(){var n=document.getElementById("efbSplitNotice");if(!n||!window.fetch)return;var post=function(a){var b=new FormData();b.append("action",a);b.append("nonce",n.getAttribute("data-nonce"));return fetch(window.ajaxurl||"' . esc_url(admin_url('admin-ajax.php')) . '",{method:"POST",credentials:"same-origin",body:b});};'
			. 'n.addEventListener("click",function(e){var t=e.target;if(t.classList.contains("efb-split-retry")){t.disabled=true;post("efb_split_retry").then(function(){location.reload();});}if(t.classList.contains("notice-dismiss")){post("efb_split_dismiss");}});})();</script>';
		return $h;
	}

	/** admin_notices on Easy Form Builder screens (manage_options only; dismissed until the list changes). */
	public static function admin_notices() {
		if (!function_exists('current_user_can') || !current_user_can('manage_options')) return;
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!$screen || stripos((string) $screen->id, 'emsfb') === false) return;
		// SPLIT §5.3: Stripe payments still pending while the Stripe add-on is absent are not checked meanwhile.
		$pending = self::stripe_pending_without_addon();
		if ($pending > 0) {
			/* translators: %d: number of payments */
			echo '<div class="notice notice-warning efb-split-stripe-pending"><p style="unicode-bidi:plaintext">' . esc_html(sprintf(_n('%d Stripe payment is waiting to be checked; install Stripe to finish it.', '%d Stripe payments are waiting to be checked; install Stripe to finish them.', $pending, 'easy-form-builder'), $pending)) . '</p></div>';
		}
		$forms = self::forms_needing();
		if (!$forms) return;
		if (get_user_meta(get_current_user_id(), 'efb_split_notice_dismissed', true) === md5(wp_json_encode($forms))) return;
		echo self::admin_notice_html($forms); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
	}

	/** Pending Stripe rows of emsfb_pay_ while AdnSPF cannot check them (0 when the add-on is ready or no table). */
	public static function stripe_pending_without_addon() {
		if (function_exists('efb_legacy_gateway_ready') && efb_legacy_gateway_ready('stripe')) return 0;
		global $wpdb;
		if (!is_object($wpdb)) return 0;
		$t = $wpdb->prefix . 'emsfb_pay_';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) !== $t) return 0;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
		return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$t}` WHERE gateway = 'stripe' AND status = 'pending'");
	}

	/** The forms list badge texts (ajax_object_efm.split_badge). */
	public static function badge_texts() {
		return array(
			/* translators: %s: name of an Easy Form Builder add-on (a product name) */
			'needs'  => __('Needs the %s add-on', 'easy-form-builder'),
			/* translators: %s: name of an Easy Form Builder add-on (a product name) */
			'update' => __('Update %s to keep using it with this version.', 'easy-form-builder'),
		);
	}
}
