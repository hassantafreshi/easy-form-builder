<?php
/**
 * LIFE-01 (owner decision 5): what deactivating and deleting the plugin remove. Deactivation: every scheduled event
 * (clear_cron()). Deletion (uninstall.php), per site: always the events, transients and preview drafts; with Settings ›
 * Privacy › "Delete all data when the plugin is deleted" (SETTING) also the tables, options, private folder, Media
 * Library files of entries, post meta, capabilities and roles, and the user meta once no site keeps its data.
 * Self-contained: uninstall.php runs while the plugin is not loaded.
 *
 * @package EasyFormBuilder
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Lifecycle {

	/** The site setting (emsfb_setting row, Settings › Privacy). '1' = delete everything on deletion. */
	const SETTING = 'prv_delete_on_uninstall';

	/** Every WP-Cron hook the plugin and its add-ons schedule, and the names older versions used. Any other hook whose name starts with emsfb_ is swept as well (clear_cron()). tests/test-lifecycle-uninstall-lists.php fails when the code schedules a hook that is not covered. */
	const CRON_HOOKS = array(
		'emsfb_daily_maintenance',
		'emsfb_revalidate_license',
		'emsfb_refresh_ir_cdn_status',
		'emsfb_addon_recovery_event',
		'emsfb_check_file_access_after_activation',
		'emsfb_cache_scan_v1',
		'emsfb_cache_scan_v2',
		'emsfb_cleanup_orphan_uploads',
		'emsfb_operation_cleanup',
		'emsfb_privacy_retention_continue',
		'emsfb_email_monitor_weekly',
		'emsfb_email_monitor_lifecycle',
		'emsfb_email_monitor_poll',
		'emsfb_split_scan_efb',
		'emsfb_pmt_reconcile',
		'emsfb_stripe_reconcile_efb',
		'emsfb_language_packs_daily',
		'emsfb_language_packs_sync',
		'emsfb_password_scrub',
		'efb_entries_stats_backfill',
		'efb_owner_index_backfill',
		'efb_unique_backfill_event',
		'efb_pay_legacy_migrate',
		'delete_preview_page_efb',
		// Older versions.
		'emsfb_download_addons_cron',
		'download_all_addons_efb',
		'email_recived_new_message_hook_efb',
		'efb_process_background_cron',
	);

	/** Option name prefixes of the plugin besides emsfb_ / Emsfb_ (efb_ alone is too common to sweep). */
	const OPTION_PREFIXES = array('efb_op_', 'efb_stkh_', 'efb_uqh_', 'efb_gh_');

	/** Option names of the plugin besides the prefixes. */
	const OPTIONS = array('efb_dyn_keys', 'efb_email_log', 'efb_form_package_imports', 'efb_draft_forms', 'efb_portal_form_pages');

	/** Transient prefixes: a transient is a cache, so the wider efb_ is safe here. */
	const TRANSIENT_PREFIXES = array('emsfb_', 'efb_');

	/** Transients with a fixed name, deleted one by one when transients live in a persistent object cache. */
	const TRANSIENTS = array(
		'emsfb_settings_transient', 'emsfb_tables_ok', 'emsfb_schema_lock', 'emsfb_ir_cdn_status', 'emsfb_theme_has_bootstrap',
		'emsfb_addons_dl_backoff', 'emsfb_addons_renew_backoff', 'emsfb_addon_recovery_lock', 'emsfb_addon_recovery_running',
		'emsfb_addon_blocked_notice_sent', 'emsfb_addon_i18n_refetch_backoff', 'emsfb_addons_report_sent', 'emsfb_outgoing_ip_hint',
		'emsfb_human_shield_cleanup_lock', 'emsfb_pmt_reconcile_lock', 'emsfb_prv_retention_lock', 'emsfb_weekly_admin_report_lock',
		'efb_pay_legacy_mig_seen', 'efb_pay_legacy_mig_lock', 'efb_portal_form_pages', 'efb_draft_forms',
	);

	/** User meta of the plugin (dismissed notices, screen preferences, the e-mail check of register forms). */
	const USER_META = array(
		'efb_pending_verification', 'efb_pp', 'efb_dt_card_dismissed', 'efb_split_notice_dismissed', 'efb_renew_notice',
		'efb_builder_single_key_shortcuts', 'efb_default_name_format', 'efb_default_addr_scheme',
	);

	/** User meta prefixes: emsfb_ (notices) and efb_cols_<form> (the entries table's columns). */
	const USER_META_PREFIXES = array('emsfb_', 'efb_cols_');

	/** Post meta of the plugin: the cache-bypass mark of pages with a form; the Media Library files' links to entries. */
	const POST_META = array('_emsfb_dynamic_page_v1', '_efb_form_id', '_efb_field_id', '_efb_track');

	/** Most attachments or preview drafts handled per query (each round deletes what it read). */
	const BATCH = 200;

	/* ------------------------------------------------------------------ deactivation */

	/** Remove every scheduled event of the plugin on this site. */
	public static function clear_cron() {
		if (!function_exists('wp_unschedule_hook')) {
			return 0;
		}
		$hooks = self::CRON_HOOKS;
		if (function_exists('_get_cron_array')) {
			foreach ((array) _get_cron_array() as $events) {
				foreach (array_keys((array) $events) as $hook) {
					if (is_string($hook) && stripos($hook, 'emsfb_') === 0) {
						$hooks[] = $hook;
					}
				}
			}
		}
		$removed = 0;
		foreach (array_unique($hooks) as $hook) {
			$n = wp_unschedule_hook($hook);
			if (is_int($n)) {
				$removed += $n;
			}
		}
		return $removed;
	}

	/** Deactivation: the events of this site, or of every site when the plugin is deactivated for the whole network. */
	public static function deactivate($network_wide = false) {
		if (!$network_wide || !function_exists('is_multisite') || !is_multisite()) {
			return self::clear_cron();
		}
		$removed = 0;
		foreach (self::site_ids() as $site_id) {
			switch_to_blog($site_id);
			$removed += self::clear_cron();
			restore_current_blog();
		}
		return $removed;
	}

	/* ------------------------------------------------------------------ uninstall */

	/** Deletion of the plugin (uninstall.php). Each site decides for its own data. */
	public static function uninstall() {
		$sum = array('sites' => 0, 'removed' => 0, 'kept' => 0);
		if (function_exists('is_multisite') && is_multisite()) {
			foreach (self::site_ids() as $site_id) {
				switch_to_blog($site_id);
				$all = self::uninstall_site();
				restore_current_blog();
				$sum['sites']++;
				$sum[$all ? 'removed' : 'kept']++;
			}
		} else {
			$all = self::uninstall_site();
			$sum['sites'] = 1;
			$sum[$all ? 'removed' : 'kept'] = 1;
		}
		self::delete_site_transients();
		// User meta belongs to the whole network: it goes once no site keeps its data.
		if ($sum['kept'] === 0) {
			self::delete_user_meta();
		}
		return $sum;
	}

	/** One site: events, transients and preview drafts always; everything else when the site asked for it. */
	public static function uninstall_site() {
		self::clear_cron();
		self::delete_transients();
		self::delete_preview_pages();
		if (!self::wants_data_removal()) {
			return false;
		}
		self::delete_data();
		return true;
	}

	/** Whether this site's settings ask for its data to be removed when the plugin is deleted (off by default). */
	public static function wants_data_removal() {
		global $wpdb;
		if (!is_object($wpdb)) {
			return false;
		}
		$table    = $wpdb->prefix . 'emsfb_setting';
		$suppress = $wpdb->suppress_errors(true);
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
			$wpdb->suppress_errors($suppress);
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- table name from $wpdb->prefix; uninstall reads the row once
		$raw = $wpdb->get_var("SELECT setting FROM `{$table}` ORDER BY id DESC LIMIT 1");
		$wpdb->suppress_errors($suppress);
		return self::setting_on($raw);
	}

	/** Read the switch from the stored settings JSON (also an older double-escaped copy). */
	public static function setting_on($raw) {
		if (!is_string($raw) || $raw === '') {
			return false;
		}
		$d = json_decode($raw, true);
		if (!is_array($d)) {
			$d = json_decode(stripslashes($raw), true);
		}
		if (is_array($d)) {
			return isset($d[self::SETTING]) && in_array($d[self::SETTING], array('1', 1, true, 'true'), true);
		}
		return (bool) preg_match('/"' . self::SETTING . '\\\\*"\s*:\s*\\\\*"?(1|true)\b/', $raw);
	}

	/** Everything the plugin stored for this site. */
	public static function delete_data() {
		self::delete_media();
		self::delete_private_dir();
		self::drop_tables();
		self::delete_options();
		self::delete_post_meta();
		self::remove_capabilities();
		self::flush_cache_groups();
	}

	/** The plugin's tables of this site ({prefix}emsfb…; a network's other sites have their own prefix). */
	public static function drop_tables() {
		global $wpdb;
		$tables = (array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'emsfb') . '%'));
		$n      = 0;
		foreach ($tables as $table) {
			if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
				continue;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall: the plugin's own tables, names checked above
			if (false !== $wpdb->query("DROP TABLE IF EXISTS `{$table}`")) {
				$n++;
			}
		}
		return $n;
	}

	/** The plugin's options of this site. */
	public static function delete_options() {
		global $wpdb;
		$like = array('emsfb\_%', 'Emsfb\_%');
		foreach (self::OPTION_PREFIXES as $p) {
			$like[] = $wpdb->esc_like($p) . '%';
		}
		$where = implode(' OR ', array_fill(0, count($like), 'option_name LIKE %s'));
		$in    = implode(',', array_fill(0, count(self::OPTIONS), '%s'));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- placeholders built above
		$names = (array) $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE {$where} OR option_name IN ({$in})", array_merge($like, self::OPTIONS)));
		$n     = 0;
		foreach (array_unique($names) as $name) {
			if (delete_option($name)) {
				$n++;
			}
		}
		return $n;
	}

	/** Transients of the plugin on this site: their values and timeouts in the options table (a single site's site transients too) and, with a persistent object cache, the ones with a fixed name. */
	public static function delete_transients() {
		global $wpdb;
		$like = array();
		foreach (self::TRANSIENT_PREFIXES as $p) {
			foreach (array('_transient_', '_transient_timeout_', '_site_transient_', '_site_transient_timeout_') as $kind) {
				$like[] = $wpdb->esc_like($kind . $p) . '%';
			}
		}
		$where = implode(' OR ', array_fill(0, count($like), 'option_name LIKE %s'));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- placeholders built above
		$names = (array) $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE {$where}", $like));
		$n     = 0;
		foreach (array_unique($names) as $name) {
			if (delete_option($name)) {
				$n++;
			}
		}
		if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
			foreach (self::TRANSIENTS as $t) {
				delete_transient($t);
				delete_site_transient($t);
			}
		}
		return $n;
	}

	/** A network's site transients (sitemeta); a single site keeps them in its options (delete_transients()). */
	public static function delete_site_transients() {
		global $wpdb;
		if (!function_exists('is_multisite') || !is_multisite() || empty($wpdb->sitemeta)) {
			return 0;
		}
		$like = array();
		foreach (self::TRANSIENT_PREFIXES as $p) {
			foreach (array('_site_transient_', '_site_transient_timeout_') as $kind) {
				$like[] = $wpdb->esc_like($kind . $p) . '%';
			}
		}
		$where = implode(' OR ', array_fill(0, count($like), 'meta_key LIKE %s'));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- placeholders built above
		$keys = (array) $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_key FROM {$wpdb->sitemeta} WHERE {$where}", $like));
		$n    = 0;
		foreach ($keys as $key) {
			if (delete_site_option($key)) {
				$n++;
			}
		}
		return $n;
	}

	/** The "Form Preview" drafts (page easy-form-builder-preview, option preview@…) whose clean-up event is gone. */
	public static function delete_preview_pages() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall: the preview pointers
		$rows = (array) $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('preview@') . '%'), ARRAY_A);
		$n    = 0;
		foreach ($rows as $row) {
			$post = get_post((int) $row['option_value']);
			if (!$post || $post->post_type !== 'page' || strpos((string) $post->post_name, 'easy-form-builder-preview') !== 0) {
				continue; // Another plugin's option that happens to start with "preview@".
			}
			if ($post->post_status !== 'publish' && wp_delete_post($post->ID, true)) {
				$n++;
			}
			delete_option($row['option_name']);
		}
		return $n;
	}

	/** Files of entries that were registered in the Media Library (private attachments with _efb_form_id). */
	public static function delete_media() {
		$n = 0;
		for ($round = 0; $round < 500; $round++) {
			$ids = get_posts(array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'posts_per_page'   => self::BATCH,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- uninstall, once
				'meta_key'         => '_efb_form_id',
			));
			if (!$ids) {
				break;
			}
			$done = 0;
			foreach ($ids as $id) {
				if (wp_delete_attachment((int) $id, true)) {
					$done++;
				} else {
					delete_post_meta((int) $id, '_efb_form_id'); // Never read it again in this loop.
				}
			}
			$n += $done;
		}
		return $n;
	}

	/** The private upload folder (uploads/efb-private, or the folder the emsfb_private_upload_dir filter names). */
	public static function delete_private_dir() {
		$dir = self::private_dir();
		if ($dir === '' || !is_dir($dir) || is_link(rtrim($dir, '/'))) {
			return false;
		}
		self::rrmdir(rtrim($dir, '/'));
		return !is_dir($dir);
	}

	/** The private folder of this site when it is safe to delete: it exists, its name says it is the plugin's, and it is none of WordPress's own folders (nor one of their parents). */
	public static function private_dir() {
		$uploads = wp_upload_dir(null, false);
		if (!is_array($uploads) || !empty($uploads['error']) || empty($uploads['basedir'])) {
			return '';
		}
		$dir  = trailingslashit($uploads['basedir']) . 'efb-private';
		$dir  = (string) apply_filters('emsfb_private_upload_dir', $dir);
		$real = $dir !== '' ? realpath($dir) : false;
		if ($real === false || stripos(basename($real), 'efb') === false) {
			return '';
		}
		$real = wp_normalize_path($real);
		foreach (array(ABSPATH, WP_CONTENT_DIR, $uploads['basedir'], dirname(ABSPATH)) as $protected) {
			$p = realpath($protected);
			if ($p === false) {
				continue;
			}
			$p = trailingslashit(wp_normalize_path($p));
			if (strpos($p, trailingslashit($real)) === 0) {
				return ''; // The folder is WordPress's own or holds it.
			}
		}
		return trailingslashit($real);
	}

	/** Delete a folder and what it holds; a link is removed, never followed. */
	private static function rrmdir($dir) {
		$items = scandir($dir);
		foreach ((array) $items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$path = $dir . '/' . $item;
			if (is_dir($path) && !is_link($path)) {
				self::rrmdir($path);
			} else {
				wp_delete_file($path);
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- uninstall, WP_Filesystem may need credentials here
		@rmdir($dir);
	}

	/** The plugin's post meta (marks on pages with a form, links of Media Library files to entries). */
	public static function delete_post_meta() {
		foreach (self::POST_META as $key) {
			delete_post_meta_by_key($key);
		}
	}

	/** The plugin's capabilities (Emsfb…) from every role of this site, and any role it added. */
	public static function remove_capabilities() {
		if (!function_exists('wp_roles')) {
			return 0;
		}
		$roles = wp_roles();
		$n     = 0;
		foreach ((array) $roles->roles as $name => $role) {
			if (stripos((string) $name, 'emsfb') === 0) {
				remove_role($name);
				continue;
			}
			$obj = get_role($name);
			if (!$obj) {
				continue;
			}
			foreach (array_keys((array) $obj->capabilities) as $cap) {
				if (stripos((string) $cap, 'emsfb') === 0) {
					$obj->remove_cap($cap);
					$n++;
				}
			}
		}
		return $n;
	}

	/** The plugin's user meta (shared by all sites of a network). */
	public static function delete_user_meta() {
		global $wpdb;
		$keys = self::USER_META;
		$like = array();
		foreach (self::USER_META_PREFIXES as $p) {
			$like[] = $wpdb->esc_like($p) . '%';
		}
		$where = implode(' OR ', array_fill(0, count($like), 'meta_key LIKE %s'));
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- placeholders built above
		$keys = array_merge($keys, (array) $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE {$where}", $like)));
		$n    = 0;
		foreach (array_unique($keys) as $key) {
			if (delete_metadata('user', 0, $key, '', true)) {
				$n++;
			}
		}
		return $n;
	}

	/** The plugin's object cache groups, where the cache can flush one group. */
	public static function flush_cache_groups() {
		if (function_exists('wp_cache_supports') && wp_cache_supports('flush_group')) {
			wp_cache_flush_group('emsfb');
			wp_cache_flush_group('efb');
		}
	}

	/** The ids of a network's sites. */
	private static function site_ids() {
		if (!function_exists('get_sites')) {
			return array(get_current_blog_id());
		}
		return array_map('intval', (array) get_sites(array('fields' => 'ids', 'number' => 0)));
	}
}
