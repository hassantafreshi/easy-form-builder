<?php
/**
 * F15-C: form versions (revisions) - docs/feature-gaps/F15-builder-productivity.fa.md §4.2, §5.4, §8, §14.
 *
 * Every successful save of a form keeps the saved state as a version in {prefix}emsfb_form_rev_ (the newest is
 * the form as it is now, "Current"). The first save of a form that has no version yet (a form older than DB
 * 1.11) first keeps the state it replaces, marked "initial". A save that changes nothing (same md5 of the JSON)
 * keeps nothing. Who saved (user_id), how (source: builder | autopilot | restore | created | initial | api) and a
 * note are kept with it. The versions are written by the save hooks efb_form_saved / efb_form_created, so every
 * save path (the builder, AutoPilot through the builder, an importer through Form_Package, a future API) keeps them; an error
 * here never stops a save (error_log only).
 *
 * How many: the site setting form_rev_keep (earlier versions besides the current one; default 10, 0 = off)
 * capped by the plan - Free 3, Free Plus 10, Pro 50 - and the filter efb_form_revisions_keep. A form bigger than
 * 2 MB keeps at most 5. Older ones are pruned at each save. Deleting a form deletes its versions.
 *
 * Restoring is done in the builder: the version is loaded (unsaved, one undo step) and the owner saves it, so
 * every guarantee of the save path applies again (sanitizer, add-ons, caches). Stored entries are never
 * touched. Admin AJAX: efb_list_revisions, efb_get_revision (nonce wp_rest + the plugin's capability).
 *
 * Personal data: a version holds the form, never an entry; only the editor's user id. WordPress' eraser
 * ("Easy Form Builder form versions") and the deletion of a user set that id to 0. The table stays after the
 * plugin is removed, like every table of the plugin (no uninstall routine drops tables).
 */
namespace Emsfb;

defined('ABSPATH') || exit;

class Revisions {

	const API_VERSION = 1;
	const SOURCES = array('builder', 'autopilot', 'restore', 'created', 'initial', 'api', 'import', 'duplicate');
	/** Earlier versions kept by plan (spec §14, decision: Free 3, Free Plus 10, Pro 50). */
	const PLAN_CAP = array('free' => 3, 'free_plus' => 10, 'pro' => 50);
	const DEFAULT_KEEP = 10;
	const BIG_FORM = 2097152;
	const BIG_FORM_KEEP = 5;
	const LIST_MAX = 51;

	public static function register() {
		if (!function_exists('add_action')) return;
		add_action('efb_form_saved', array(__CLASS__, 'on_saved'), 30, 3);
		add_action('efb_form_created', array(__CLASS__, 'on_created'), 30, 3);
		add_action('efb_form_deleted', array(__CLASS__, 'delete_for_form'), 10, 1);
		add_action('deleted_user', array(__CLASS__, 'forget_user'), 10, 1);
		add_filter('wp_privacy_personal_data_erasers', array(__CLASS__, 'register_eraser'));
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 32, 2);
		if (function_exists('is_admin') && is_admin()) {
			add_action('wp_ajax_efb_list_revisions', array(__CLASS__, 'ajax_list'));
			add_action('wp_ajax_efb_get_revision', array(__CLASS__, 'ajax_get'));
		}
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'emsfb_form_rev_';
	}

	/** CREATE TABLE of emsfb_form_rev_ (dbDelta format). */
	public static function ddl($prefix, $charset_collate = '') {
		return array('emsfb_form_rev_' => "CREATE TABLE {$prefix}emsfb_form_rev_ (
  rev_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  form_id int(11) NOT NULL,
  form_name varchar(250) NOT NULL DEFAULT '',
  form_type varchar(15) NULL DEFAULT 'form',
  form_structer mediumtext NOT NULL,
  struct_hash char(32) NOT NULL DEFAULT '',
  field_count smallint(5) unsigned NOT NULL DEFAULT 0,
  step_count smallint(5) unsigned NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  source varchar(20) NOT NULL DEFAULT 'builder',
  note varchar(190) NOT NULL DEFAULT '',
  extras mediumtext NULL,
  created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
  PRIMARY KEY  (rev_id),
  KEY form_rev (form_id, rev_id),
  KEY user_id (user_id)
) {$charset_collate};");
	}

	private static $ready = null;
	public static function ready() {
		if (self::$ready !== null) return self::$ready;
		global $wpdb;
		if (!isset($wpdb) || !is_object($wpdb)) return self::$ready = false;
		$t = self::table();
		return self::$ready = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $t)) === $t);
	}
	public static function reset_ready() { self::$ready = null; }

	/* ------------------------------------------------------------------ settings and plan */

	public static function is_setting_key($key) { return $key === 'form_rev_keep'; }

	/** 0 … 50 (digits in any script); the plan cap is applied when the value is read, not here. */
	public static function sanitize_setting($key, $value) {
		if ($key !== 'form_rev_keep') return null;
		$s = is_scalar($value) ? strtr((string) $value, array('۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9')) : '';
		$d = preg_replace('/[^0-9]/', '', $s);
		return $d === '' ? (string) self::DEFAULT_KEEP : (string) min(50, (int) $d);
	}

	/** The plan's cap of earlier versions (free | free_plus | pro). */
	public static function plan_cap($package = null) {
		$plan = 'free';
		if (class_exists('\\Emsfb\\Builder_Api')) {
			if (Builder_Api::plan_at_least('pro', $package)) $plan = 'pro';
			elseif (Builder_Api::plan_at_least('free_plus', $package)) $plan = 'free_plus';
		}
		return self::PLAN_CAP[$plan];
	}

	/** Earlier versions kept per form (besides the current one); 0 = no versions. */
	public static function keep($settings = null, $package = null) {
		if ($settings === null && function_exists('get_setting_Emsfb')) $settings = get_setting_Emsfb('decoded');
		if (is_array($settings)) $settings = (object) $settings;
		$want = (is_object($settings) && isset($settings->form_rev_keep)) ? (int) self::sanitize_setting('form_rev_keep', $settings->form_rev_keep) : self::DEFAULT_KEEP;
		$n = max(0, min($want, self::plan_cap($package)));
		if (function_exists('apply_filters')) $n = (int) apply_filters('efb_form_revisions_keep', $n, $want);
		return max(0, min(50, $n));
	}

	/* ------------------------------------------------------------------ storage */

	/** The JSON text that is hashed and stored (the save path's own encoding, unescaped). */
	public static function encode(array $rows) {
		return function_exists('wp_json_encode') ? wp_json_encode($rows, JSON_UNESCAPED_UNICODE) : json_encode($rows, JSON_UNESCAPED_UNICODE);
	}

	public static function counts(array $rows) {
		$f = 0; $s = 0;
		foreach ($rows as $i => $r) {
			if ($i === 0 || !is_array($r) || !isset($r['type'])) continue;
			if ($r['type'] === 'step') $s++;
			elseif ($r['type'] !== 'option' && $r['type'] !== 'r_matrix') $f++;
		}
		return array(min(65535, $f), min(65535, $s));
	}

	/**
	 * Keeps $rows as a version of the form. args: source, note, user_id, name, type, old_rows (the state the
	 * save replaced: kept first, as "initial", when the form has no version yet).
	 *
	 * @return int The new rev_id, 0 when nothing was kept (no change, versions off, no table).
	 */
	public static function record($form_id, array $rows, array $args = array()) {
		$form_id = (int) $form_id;
		if ($form_id < 1 || empty($rows) || !self::ready()) return 0;
		$keep = self::keep();
		if ($keep < 1) return 0;
		global $wpdb;
		$t = self::table();
		$json = self::encode($rows);
		if (!is_string($json) || $json === '') return 0;
		$hash = md5($json);
		$last = $wpdb->get_row($wpdb->prepare("SELECT rev_id, struct_hash FROM `{$t}` WHERE form_id = %d ORDER BY rev_id DESC LIMIT 1", $form_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ($last && $last['struct_hash'] === $hash) return 0;
		$name = isset($args['name']) && is_scalar($args['name']) ? (string) $args['name'] : '';
		if ($name === '' && isset($rows[0]['formName']) && is_scalar($rows[0]['formName'])) $name = (string) $rows[0]['formName'];
		if (!$last && !empty($args['old_rows']) && is_array($args['old_rows'])) {
			$old_json = self::encode($args['old_rows']);
			if (is_string($old_json) && md5($old_json) !== $hash) {
				self::insert($form_id, $args['old_rows'], $old_json, array('source' => 'initial', 'user_id' => 0, 'name' => $name, 'note' => ''));
			}
		}
		if (!isset($args['extras']) && class_exists('\Emsfb\Form_Package')) $args['extras'] = Form_Package::export_extras($form_id, array('context' => 'revision'));
		$id = self::insert($form_id, $rows, $json, $args + array('name' => $name));
		if ($id) self::prune($form_id, strlen($json) > self::BIG_FORM ? min($keep, self::BIG_FORM_KEEP) : $keep);
		return $id;
	}

	private static function insert($form_id, array $rows, $json, array $args) {
		global $wpdb;
		list($fields, $steps) = self::counts($rows);
		$source = isset($args['source']) && in_array($args['source'], self::SOURCES, true) ? $args['source'] : 'builder';
		$note = isset($args['note']) && is_scalar($args['note']) ? (string) $args['note'] : '';
		$note = function_exists('sanitize_text_field') ? sanitize_text_field($note) : trim(strip_tags($note));
		$note = function_exists('mb_substr') ? mb_substr($note, 0, 190, 'UTF-8') : substr($note, 0, 190);
		$type = isset($rows[0]['type']) && is_scalar($rows[0]['type']) ? substr((string) $rows[0]['type'], 0, 15) : 'form';
		$uid = isset($args['user_id']) ? (int) $args['user_id'] : (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
		$row = array(
			'form_id' => (int) $form_id, 'form_name' => substr((string) $args['name'], 0, 250), 'form_type' => $type,
			// Stored like emsfb_form.form_structer (quotes escaped); read with emsfb_json_normalize_efb().
			'form_structer' => str_replace('"', '\\"', $json), 'struct_hash' => md5($json),
			'field_count' => $fields, 'step_count' => $steps, 'user_id' => max(0, $uid), 'source' => $source, 'note' => $note,
			// F15-D / P23: the add-ons' blocks of the form at this save (secrets removed), applied again when a restored version is saved.
			'extras' => isset($args['extras']) && is_array($args['extras']) && $args['extras'] ? (function_exists('wp_json_encode') ? wp_json_encode($args['extras'], JSON_UNESCAPED_UNICODE) : json_encode($args['extras'], JSON_UNESCAPED_UNICODE)) : null,
			'created' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
		);
		$ok = $wpdb->insert(self::table(), $row);
		// A table made before the extras column (an early build of DB 1.11, until its upgrade step ran): the version is still kept.
		if ($ok === false && array_key_exists('extras', $row)) { unset($row['extras']); $ok = $wpdb->insert(self::table(), $row); }
		if ($ok === false) {
			if (function_exists('error_log')) error_log('Easy Form Builder: a version of form ' . (int) $form_id . ' could not be kept: ' . $wpdb->last_error); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	/** Keeps the current version and $keep earlier ones. Portable: no subquery with LIMIT (MySQL, SQLite). */
	public static function prune($form_id, $keep) {
		global $wpdb;
		$t = self::table();
		$ids = $wpdb->get_col($wpdb->prepare("SELECT rev_id FROM `{$t}` WHERE form_id = %d ORDER BY rev_id DESC", (int) $form_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$drop = array_map('intval', array_slice((array) $ids, max(1, (int) $keep + 1)));
		if (!$drop) return 0;
		return (int) $wpdb->query("DELETE FROM `{$t}` WHERE rev_id IN (" . implode(',', $drop) . ')'); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers only
	}

	public static function delete_for_form($form_id) {
		if (!self::ready()) return 0;
		global $wpdb;
		return (int) $wpdb->delete(self::table(), array('form_id' => (int) $form_id), array('%d'));
	}

	/* ------------------------------------------------------------------ hooks */

	/** efb_form_created: a new form's first version ("created" when the builder made it). */
	public static function on_created($form_id, $rows, $ctx = array()) {
		$ctx = is_array($ctx) ? $ctx : array();
		if ((!isset($ctx['source']) || $ctx['source'] === 'builder') && empty($ctx['rev_source'])) $ctx['source'] = 'created';
		self::on_saved($form_id, $rows, $ctx);
	}

	/** efb_form_saved: ($form_id, $rows, $ctx = {source, user_id, rev_source, rev_note, old_rows}). */
	public static function on_saved($form_id, $rows, $ctx = array()) {
		try {
			if (!is_array($rows)) return;
			$ctx = is_array($ctx) ? $ctx : array();
			$source = isset($ctx['rev_source']) && in_array($ctx['rev_source'], self::SOURCES, true) ? $ctx['rev_source'] : '';
			if ($source === '') {
				$s = isset($ctx['source']) ? (string) $ctx['source'] : 'builder';
				$source = in_array($s, self::SOURCES, true) ? $s : ($s === 'cf7' ? 'import' : 'api');
			}
			self::record((int) $form_id, $rows, array(
				'source' => $source, 'note' => isset($ctx['rev_note']) ? $ctx['rev_note'] : '',
				'user_id' => isset($ctx['user_id']) ? (int) $ctx['user_id'] : null,
				'old_rows' => isset($ctx['old_rows']) && is_array($ctx['old_rows']) ? $ctx['old_rows'] : null,
			));
		} catch (\Throwable $e) {
			if (function_exists('error_log')) error_log('Easy Form Builder: form version not kept: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	/** rev_source / rev_note of a save request (the builder sends them; anything else is ignored). */
	public static function request_meta() {
		$src = isset($_POST['rev_source']) ? sanitize_key(wp_unslash($_POST['rev_source'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- the caller checked the nonce
		$rex = isset($_POST['rev_extras']) ? absint(wp_unslash($_POST['rev_extras'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$note = isset($_POST['rev_note']) ? sanitize_text_field(wp_unslash($_POST['rev_note'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return array('rev_source' => in_array($src, array('builder', 'autopilot', 'restore'), true) ? $src : '', 'rev_note' => function_exists('mb_substr') ? mb_substr($note, 0, 190, 'UTF-8') : substr($note, 0, 190), 'rev_extras' => $src === 'restore' ? $rex : 0);
	}

	/**
	 * A restored version is being saved: the add-ons' blocks it kept are applied again to the form (context revision:
	 * this site's credentials stay). Called by the save before efb_form_saved, so the new version has them.
	 * @return array|null the import report, or null.
	 */
	public static function apply_extras($form_id, $rev_id) {
		if ((int) $rev_id < 1 || !class_exists('\Emsfb\Form_Package')) return null;
		$r = self::get((int) $rev_id);
		if (!$r || (int) $r['form_id'] !== (int) $form_id || empty($r['extras'])) return null;
		return Form_Package::import_extras((int) $form_id, $r['extras'], array('context' => 'revision'));
	}

	public static function forget_user($user_id) {
		if (!self::ready()) return 0;
		global $wpdb;
		return (int) $wpdb->update(self::table(), array('user_id' => 0), array('user_id' => (int) $user_id));
	}

	public static function register_eraser($erasers) {
		$erasers['easy-form-builder-versions'] = array('eraser_friendly_name' => __('Easy Form Builder form versions', 'easy-form-builder'), 'callback' => array(__CLASS__, 'erase'));
		return $erasers;
	}

	/** WordPress eraser: the editor's user id on form versions becomes 0 (the forms themselves are the site's). */
	public static function erase($email, $page = 1) {
		$user = function_exists('get_user_by') ? get_user_by('email', (string) $email) : false;
		$n = $user ? self::forget_user($user->ID) : 0;
		return array('items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true);
	}

	/* ------------------------------------------------------------------ reading */

	/** The versions of a form, newest first, without the form JSON. */
	public static function list_for_form($form_id, $limit = self::LIST_MAX) {
		if (!self::ready()) return array();
		global $wpdb;
		$t = self::table();
		$rows = (array) $wpdb->get_results($wpdb->prepare("SELECT rev_id, form_id, form_name, form_type, struct_hash, field_count, step_count, user_id, source, note, created FROM `{$t}` WHERE form_id = %d ORDER BY rev_id DESC LIMIT %d", (int) $form_id, max(1, (int) $limit)), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$names = array();
		foreach ($rows as $i => $r) {
			$uid = (int) $r['user_id'];
			if (!isset($names[$uid])) {
				$u = ($uid && function_exists('get_userdata')) ? get_userdata($uid) : false;
				$names[$uid] = $u ? (string) $u->display_name : '';
			}
			$ts = strtotime($r['created'] . ' ' . (function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC'));
			$rows[$i] = array(
				'rev_id' => (int) $r['rev_id'], 'form_id' => (int) $r['form_id'], 'form_name' => (string) $r['form_name'], 'form_type' => (string) $r['form_type'],
				'hash' => (string) $r['struct_hash'], 'fields' => (int) $r['field_count'], 'steps' => (int) $r['step_count'],
				'user' => $names[$uid], 'source' => (string) $r['source'], 'note' => (string) $r['note'],
				'created' => (string) $r['created'], 'iso' => $ts ? gmdate('c', $ts) : '',
				'date' => ($ts && function_exists('wp_date')) ? wp_date(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'), $ts) : (string) $r['created'],
				'current' => $i === 0,
			);
		}
		return $rows;
	}

	/** One version with its form (rows, decoded), or null. */
	public static function get($rev_id) {
		if (!self::ready()) return null;
		global $wpdb;
		$t = self::table();
		$r = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$t}` WHERE rev_id = %d", (int) $rev_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if (!$r) return null;
		$json = function_exists('emsfb_json_normalize_efb') ? \emsfb_json_normalize_efb($r['form_structer']) : str_replace('\\"', '"', $r['form_structer']);
		$rows = json_decode($json, true);
		if (!is_array($rows)) return null;
		$r['rows'] = $rows;
		$r['extras'] = isset($r['extras']) && is_string($r['extras']) && $r['extras'] !== '' ? json_decode($r['extras'], true) : array();
		if (!is_array($r['extras'])) $r['extras'] = array();
		unset($r['form_structer']);
		return $r;
	}

	/* ------------------------------------------------------------------ admin AJAX */

	private static function guard() {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$lang = $fn ? $fn->text_efb(array('error403', 'somethingWentWrongPleaseRefresh')) : array('error403' => 'Forbidden', 'somethingWentWrongPleaseRefresh' => 'Error');
		$can = $fn ? $fn->user_permission_efb_admin_dashboard() : false;
		if (!check_ajax_referer('wp_rest', 'nonce', false) || !$can) {
			wp_send_json_success(array('success' => false, 'm' => $lang['error403'], 'code' => 'forbidden'), 200);
		}
		return $lang;
	}

	private static function form_exists($form_id) {
		global $wpdb;
		return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->prefix}emsfb_form` WHERE form_id = %d", (int) $form_id)) > 0;
	}

	/** wp_ajax_efb_list_revisions: {form_id} → {success, form_id, keep, cap, revisions:[…]}. */
	public static function ajax_list() {
		$lang = self::guard();
		$fid = isset($_POST['form_id']) ? absint(wp_unslash($_POST['form_id'])) : 0;
		if ($fid < 1 || !self::form_exists($fid)) wp_send_json_success(array('success' => false, 'm' => $lang['somethingWentWrongPleaseRefresh'], 'code' => 'no_form'), 200);
		wp_send_json_success(array('success' => true, 'form_id' => $fid, 'keep' => self::keep(), 'cap' => self::plan_cap(), 'revisions' => self::list_for_form($fid)), 200);
	}

	/** wp_ajax_efb_get_revision: {rev_id} → {success, revision:{rev_id, form_id, …, rows}}. */
	public static function ajax_get() {
		$lang = self::guard();
		$rid = isset($_POST['rev_id']) ? absint(wp_unslash($_POST['rev_id'])) : 0;
		$r = $rid ? self::get($rid) : null;
		if (!$r || !self::form_exists((int) $r['form_id'])) wp_send_json_success(array('success' => false, 'm' => $lang['somethingWentWrongPleaseRefresh'], 'code' => 'no_revision'), 200);
		wp_send_json_success(array('success' => true, 'revision' => array(
			'rev_id' => (int) $r['rev_id'], 'form_id' => (int) $r['form_id'], 'form_name' => (string) $r['form_name'], 'form_type' => (string) $r['form_type'],
			'source' => (string) $r['source'], 'note' => (string) $r['note'], 'created' => (string) $r['created'], 'hash' => (string) $r['struct_hash'], 'rows' => $r['rows'],
			'extras' => array_keys((array) $r['extras']),
		)), 200);
	}

	/* ------------------------------------------------------------------ registry and the builder */

	public static function client_config() {
		return array('v' => self::API_VERSION, 'keep' => self::keep(), 'cap' => self::plan_cap(), 'caps' => self::PLAN_CAP, 'on' => self::keep() > 0);
	}

	/** efb_capabilities: the versions of a form (read only for assistants; a restore is a builder change and a save). */
	public static function capabilities($out, $args = array()) {
		if (!is_array($out)) return $out;
		$pkg = isset($args['package']) ? $args['package'] : null;
		$out['revisions'] = array(
			'v' => self::API_VERSION, 'spec' => 'F15',
			'ajax' => array('list' => 'efb_list_revisions', 'get' => 'efb_get_revision'),
			'kept_on' => array('efb_form_saved', 'efb_form_created'), 'sources' => self::SOURCES,
			'save_params' => array('rev_source' => array('builder', 'autopilot', 'restore'), 'rev_note' => 'text, 190 characters'),
			'setting' => 'form_rev_keep', 'limits' => self::PLAN_CAP, 'cap_now' => self::plan_cap($pkg),
			'restore' => 'efbBuilderApi.revisions.restore(rev_id): the version in the builder as one undo step; saving it is the owner\'s click on Save',
			'builder' => array('efbBuilderApi.revisions.list', 'efbBuilderApi.revisions.get', 'efbBuilderApi.revisions.restore'),
		);
		if (isset($out['features']) && is_array($out['features'])) {
			$out['features']['setting:revisions'] = array('kind' => 'setting', 'scope' => 'site', 'package' => 'free', 'addon' => null, 'available_now' => true, 'reason' => null,
				'prompt_state' => 0, 'message_key' => null, 'form_keys' => array(), 'settings_keys' => array('form_rev_keep'), 'label_key' => 'revisions',
				'limits' => array('free' => self::PLAN_CAP['free'], 'free_plus' => self::PLAN_CAP['free_plus'], 'pro' => self::PLAN_CAP['pro']));
		}
		return $out;
	}
}
