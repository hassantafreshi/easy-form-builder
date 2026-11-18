<?php
/**
 * AI-4 (docs/autopilot-integration.md "AI builder core APIs"): a form can be a draft — made (by an assistant, an
 * import) but not shown to visitors yet.
 *
 * Stored in the existing column emsfb_form.status (every site has it: Install::upgrade_schema() adds it since 3.9.x):
 * 2 = draft; every other value (1, NULL, the 0 some old versions wrote) = published, so no form changes when this
 * ships and no migration is needed. A draft's shortcode, block and widgets give visitors nothing (an empty string, no
 * scripts), its session prepare answers "Form not found." to them; the plugin's admins see the form with a notice
 * above it ("Draft: visitors do not see this form yet") so they can try it. The forms list marks it and has Publish.
 *
 *   Form_Status::get($id) → 'draft' | 'published' | null    Form_Status::set($id, 'draft'|'published') → true | WP_Error
 *   Form_Package::import($pkg, ['status' => 'draft']), Form_Package::publish($id)
 *   REST POST Emsfb/v1/admin/forms/{id}/publish (and /status {status}), admin-ajax efb_form_publish (the list's button)
 *   action efb_form_status_changed ($form_id, $new, $old)
 */
namespace Emsfb;

defined('ABSPATH') || exit;

class Form_Status {

	const VERSION = 1;
	const DRAFT = 2;
	const CACHE = 'efb_draft_forms';

	private static $drafts = null;
	private static $column = null;

	public static function register() {
		if (!function_exists('add_action')) return;
		add_action('rest_api_init', array(__CLASS__, 'routes'));
		add_action('wp_ajax_efb_form_publish', array(__CLASS__, 'ajax_publish'));
		add_action('efb_form_deleted', array(__CLASS__, 'flush'));
		add_filter('efb_capabilities', array(__CLASS__, 'capabilities'), 35, 2);
		add_filter('efb_admin_localize_vars', array(__CLASS__, 'builder_vars'));
	}

	private static function err($code, $msg, $status) {
		return class_exists('\\WP_Error') ? new \WP_Error($code, $msg, array('status' => $status)) : array('error' => $code, 'message' => $msg, 'status' => $status);
	}

	/** Whether this site's form table has the status column (it does since 3.9.x; checked once per request). */
	public static function column_ready() {
		if (self::$column !== null) return self::$column;
		global $wpdb;
		$t = $wpdb->prefix . 'emsfb_form';
		self::$column = (bool) $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$t}` LIKE %s", 'status')); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		return self::$column;
	}

	public static function flush() {
		self::$drafts = null;
		if (function_exists('wp_cache_delete')) wp_cache_delete(self::CACHE, 'emsfb');
	}

	/** The ids of the draft forms (one small query per request, kept in the object cache). @return array<int, int> */
	public static function draft_ids() {
		if (self::$drafts !== null) return self::$drafts;
		$ids = function_exists('wp_cache_get') ? wp_cache_get(self::CACHE, 'emsfb') : false;
		if (!is_array($ids)) {
			$ids = array();
			if (self::column_ready()) {
				global $wpdb;
				$ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare("SELECT form_id FROM `{$wpdb->prefix}emsfb_form` WHERE status = %d", self::DRAFT))); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			if (function_exists('wp_cache_set')) wp_cache_set(self::CACHE, $ids, 'emsfb', 3600);
		}
		self::$drafts = $ids;
		return $ids;
	}

	public static function is_draft($form_id) {
		return (int) $form_id > 0 && in_array((int) $form_id, self::draft_ids(), true);
	}

	/** 'draft' | 'published', or null when there is no such form. */
	public static function get($form_id) {
		global $wpdb;
		$n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->prefix}emsfb_form` WHERE form_id = %d", (int) $form_id)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ($n < 1) return null;
		return self::is_draft($form_id) ? 'draft' : 'published';
	}

	/** The plugin's admins (who build forms) see a draft; nobody else does. */
	public static function viewer_is_admin() {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		if ($fn && method_exists($fn, 'user_permission_efb_admin_dashboard')) return (bool) $fn->user_permission_efb_admin_dashboard();
		return function_exists('current_user_can') && current_user_can('manage_options');
	}

	/** A draft is hidden from this viewer (the shortcode, the block, the session prepare). */
	public static function hidden_for_viewer($form_id) {
		return self::is_draft($form_id) && !self::viewer_is_admin();
	}

	/**
	 * The shortcode's gate: null (not a draft: render as always), '' (a visitor: nothing at all) or the admin's notice
	 * (shown above the form).
	 */
	public static function render_note($form_id) {
		if (!self::is_draft($form_id)) return null;
		if (!self::viewer_is_admin()) return '';
		$url = function_exists('admin_url') ? admin_url('admin.php?page=Emsfb') : 'admin.php?page=Emsfb';
		/* translators: shown above a draft form to the site's administrators only; "Easy Form Builder › Forms" is the admin menu */
		$t = __('Draft: visitors do not see this form yet. Publish it in Easy Form Builder › Forms when it is ready.', 'easy-form-builder');
		return '<div class="efb efb-draft-note" role="note" dir="auto" style="margin:0 0 12px;padding:10px 14px;border:1px dashed #c7a600;border-radius:8px;background:#fffbe6;color:#5c4a00;font-size:14px;line-height:1.5">'
			. esc_html($t) . ' <a href="' . esc_url($url) . '">' . esc_html__('Open the forms list', 'easy-form-builder') . '</a></div>';
	}

	/**
	 * Draft or publish a form (the plugin's admins). Fires efb_form_status_changed. @return true|\WP_Error
	 */
	public static function set($form_id, $status) {
		$form_id = (int) $form_id;
		if (!self::viewer_is_admin()) return self::err('package_forbidden', 'You are not allowed to do this.', 403);
		if (!in_array($status, array('draft', 'published'), true)) return self::err('status_invalid', 'The status is draft or published.', 400);
		$old = self::get($form_id);
		if ($old === null) return self::err('package_form_missing', 'The form was not found.', 404);
		if (!self::column_ready()) return self::err('status_unavailable', 'This site cannot keep a form as a draft (its form table has no status column).', 503);
		if ($old === $status) return true;
		global $wpdb;
		$r = $wpdb->update($wpdb->prefix . 'emsfb_form', array('status' => $status === 'draft' ? self::DRAFT : 1), array('form_id' => $form_id), array('%d'), array('%d'));
		if ($r === false) return self::err('package_not_saved', 'The status could not be saved.', 500);
		self::flush();
		if (function_exists('do_action')) do_action('efb_form_status_changed', $form_id, $status, $old);
		return true;
	}

	public static function publish($form_id) {
		return self::set($form_id, 'published');
	}

	/** The forms list: efb_list_form() rows get draft = 1 for drafts (the old status column is not exposed). */
	public static function mark_list($rows) {
		if (!is_array($rows)) return $rows;
		$d = array_flip(self::draft_ids());
		foreach ($rows as $r) if (is_object($r) && isset($r->form_id) && isset($d[(int) $r->form_id])) $r->draft = 1;
		return $rows;
	}

	/** The list's Publish button (admin-ajax, nonce wp_rest). */
	public static function ajax_publish() {
		if (!check_ajax_referer('wp_rest', 'nonce', false) || !self::viewer_is_admin()) wp_send_json_success(array('success' => false, 'code' => 'package_forbidden', 'm' => __('You are not allowed to do this.', 'easy-form-builder')), 200);
		$id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
		$r = self::publish($id);
		if (is_wp_error($r)) wp_send_json_success(array('success' => false, 'code' => $r->get_error_code(), 'm' => $r->get_error_message()), 200);
		wp_send_json_success(array('success' => true, 'id' => $id, 'status' => 'published', 'm' => __('Published: visitors see this form now.', 'easy-form-builder')), 200);
	}

	public static function routes() {
		if (!function_exists('register_rest_route')) return;
		$perm = function () { return function_exists('get_current_user_id') && get_current_user_id() > 0 && self::viewer_is_admin(); };
		$answer = function ($id, $r) {
			if (is_wp_error($r)) return $r;
			return rest_ensure_response(array('form_id' => (int) $id, 'status' => self::get($id)));
		};
		register_rest_route('Emsfb/v1', '/admin/forms/(?P<id>\d+)/publish', array('methods' => 'POST', 'permission_callback' => $perm,
			'callback' => function ($req) use ($answer) { return $answer((int) $req['id'], self::publish((int) $req['id'])); }));
		register_rest_route('Emsfb/v1', '/admin/forms/(?P<id>\d+)/status', array('methods' => 'POST', 'permission_callback' => $perm,
			'callback' => function ($req) use ($answer) { return $answer((int) $req['id'], self::set((int) $req['id'], (string) $req->get_param('status'))); }));
	}

	/** The forms list's phrases (efb_var.form_status). */
	public static function builder_vars($vars) {
		if (!is_array($vars)) return $vars;
		$vars['form_status'] = array('v' => self::VERSION,
			/* translators: the badge of a form that is not shown to visitors yet (the forms list) */
			'draft' => __('Draft', 'easy-form-builder'),
			/* translators: the button that shows a draft form to visitors (the forms list) */
			'publish' => __('Publish', 'easy-form-builder'),
			'draftTip' => __('Visitors do not see a draft. Publish it when it is ready.', 'easy-form-builder'));
		return $vars;
	}

	public static function capabilities($out, $args = array()) {
		if (!is_array($out)) return $out;
		$rest = function ($p) { return function_exists('rest_url') ? rest_url('Emsfb/v1/admin/forms/{id}/' . $p) : '/wp-json/Emsfb/v1/admin/forms/{id}/' . $p; };
		$out['form_status'] = array('v' => self::VERSION, 'spec' => 'AI-4', 'values' => array('published', 'draft'), 'ready' => self::column_ready(),
			'import' => 'Form_Package::import($package, {status: "draft"})', 'publish' => array('php' => 'Form_Package::publish($form_id)', 'rest' => array('POST', $rest('publish')), 'ui' => 'the forms list (Publish)'),
			'set' => array('php' => 'Form_Status::set($form_id, "draft"|"published")', 'rest' => array('POST', $rest('status'))),
			'visitor' => 'nothing (shortcode, block, widgets); the session prepare answers efb_form_missing', 'admin' => 'the form with a draft notice',
			'action' => 'efb_form_status_changed', 'stored' => 'emsfb_form.status = 2 (other values: published)');
		return $out;
	}
}
