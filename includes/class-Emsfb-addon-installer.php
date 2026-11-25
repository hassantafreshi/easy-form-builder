<?php
/**
 * QA3-002: an add-on's package in vendor/: unzipped into a staging folder, then moved into place in one rename (an
 * outdated folder is moved aside, and put back when the new one cannot take its place); the opcode cache is told
 * (QA-4's fatal from stale cached files). remove_folder() serves Remove. A linked folder (a development checkout) is
 * never replaced or deleted.
 *
 * @package EasyFormBuilder
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Addon_Installer {

	/**
	 * Unzip $zip (one top folder named $folder) into $vendor_dir, replacing the folder already there in one step.
	 *
	 * @param string $zip        The downloaded package.
	 * @param string $vendor_dir The plugin's vendor/ folder.
	 * @param string $folder     The add-on's folder (stripe, paypal, …).
	 * @param string $temp_dir   A working folder on the same disk (the plugin's temp/).
	 * @return true|\WP_Error
	 */
	public static function install_zip($zip, $vendor_dir, $folder, $temp_dir) {
		$folder = (string) $folder;
		if (!preg_match('/^[a-z0-9_-]{1,40}$/', $folder)) {
			return new \WP_Error('addon_folder_invalid', esc_html__('Cannot install the add-on: its package name is not valid.', 'easy-form-builder'));
		}
		$vendor_dir = rtrim(wp_normalize_path((string) $vendor_dir), '/') . '/';
		$temp_dir   = rtrim(wp_normalize_path((string) $temp_dir), '/') . '/';
		$target     = $vendor_dir . $folder;
		if (is_link($target)) {
			return true; // A development checkout: never replaced (see the class comment).
		}
		if (!is_dir($temp_dir)) {
			wp_mkdir_p($temp_dir);
		}
		$stage = $temp_dir . 'stage-' . $folder . '-' . wp_generate_password(8, false, false) . '/';
		if (!wp_mkdir_p($stage)) {
			return new \WP_Error('addon_stage_failed', esc_html__('Cannot install the add-on: the plugin cannot write to its own temp folder.', 'easy-form-builder'));
		}
		$unzipped = unzip_file($zip, $stage);
		if (is_wp_error($unzipped)) {
			self::rrmdir(rtrim($stage, '/'));
			return new \WP_Error('unzip_failed', esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to unzip files', 'easy-form-builder') . ' (' . $unzipped->get_error_message() . ')');
		}
		$new = $stage . $folder;
		if (!is_dir($new) || is_link($new)) {
			self::rrmdir(rtrim($stage, '/'));
			/* translators: %s: the folder name an add-on package must hold (for example stripe) */
			return new \WP_Error('addon_package_invalid', sprintf(esc_html__('Cannot install the add-on: the downloaded package does not hold its folder "%s".', 'easy-form-builder'), $folder));
		}
		$old = '';
		if (file_exists($target)) {
			$old = $temp_dir . 'old-' . $folder . '-' . wp_generate_password(8, false, false);
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- one atomic move on the same disk; WP_Filesystem may need FTP details here
			if (!@rename($target, $old)) {
				self::rrmdir(rtrim($stage, '/'));
				return new \WP_Error('addon_replace_failed', esc_html__('Cannot update the add-on: its current folder cannot be moved. Check that the web server may write to the plugin\'s vendor folder.', 'easy-form-builder'));
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- see above
		if (!@rename($new, $target)) {
			if ($old !== '') {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- put the add-on back as it was
				@rename($old, $target);
			}
			self::rrmdir(rtrim($stage, '/'));
			return new \WP_Error('addon_replace_failed', esc_html__('Cannot install the add-on: its new folder cannot be moved into place. Check that the web server may write to the plugin\'s vendor folder.', 'easy-form-builder'));
		}
		if ($old !== '') {
			self::rrmdir($old);
		}
		self::rrmdir(rtrim($stage, '/'));
		self::invalidate_opcache($target);
		return true;
	}

	/**
	 * Download an add-on package from $url (its folder is the file's name: …/stripe.zip → stripe) and install it with
	 * install_zip(). The one download path of the Add-ons page and the recovery after an update.
	 *
	 * @return true|\WP_Error
	 */
	public static function download_and_install($url) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$folder = substr((string) $url, strrpos((string) $url, '/') + 1, -4);
		$tmp = download_url($url);
		if (is_wp_error($tmp)) {
			$tmp = download_url($url, 300, true);
		}
		if (is_wp_error($tmp)) {
			return new \WP_Error('download_failed', esc_html__('Cannot install add-ons of Easy Form Builder because the plugin is not able to download files', 'easy-form-builder') . ' (' . $tmp->get_error_message() . ')');
		}
		WP_Filesystem();
		$dir = EMSFB_PLUGIN_DIRECTORY;
		$r = self::install_zip($tmp, $dir . 'vendor/', $folder, $dir . 'temp/');
		wp_delete_file($tmp);
		if (is_wp_error($r)) {
			return $r;
		}
		// Keep only the add-on language files of locales this site uses (never throws, never changes the result).
		if (!function_exists('emsfb_prune_addon_languages_efb') && function_exists('get_efbFunction')) {
			get_efbFunction();
		}
		if (function_exists('emsfb_prune_addon_languages_efb')) {
			emsfb_prune_addon_languages_efb();
		}
		return true;
	}

	/**
	 * Delete an add-on's folder (Remove on the Add-ons page). A link is left alone (see the class comment).
	 *
	 * @return bool Whether the folder is gone (true when there was none).
	 */
	public static function remove_folder($vendor_dir, $folder) {
		$folder = (string) $folder;
		if (!preg_match('/^[a-z0-9_-]{1,40}$/', $folder)) {
			return false;
		}
		$target = rtrim(wp_normalize_path((string) $vendor_dir), '/') . '/' . $folder;
		if (is_link($target)) {
			return false;
		}
		if (!file_exists($target)) {
			return true;
		}
		self::invalidate_opcache($target);
		self::rrmdir($target);
		clearstatcache();
		return !file_exists($target);
	}

	/** Tell PHP's opcode cache that the files under $dir changed (wp_opcache_invalidate_directory(), WordPress 5.5+). */
	public static function invalidate_opcache($dir) {
		clearstatcache();
		if (function_exists('wp_opcache_invalidate_directory')) {
			wp_opcache_invalidate_directory((string) $dir);
			return;
		}
		if (!function_exists('opcache_invalidate') || !is_dir($dir)) {
			return;
		}
		$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
		foreach ($it as $f) {
			if ($f->isFile() && substr($f->getFilename(), -4) === '.php') {
				@opcache_invalidate($f->getPathname(), true);
			}
		}
	}

	/** Delete a folder and what it holds; a link is removed, never followed. */
	private static function rrmdir($dir) {
		if (is_link($dir) || is_file($dir)) {
			wp_delete_file($dir);
			return;
		}
		if (!is_dir($dir)) {
			return;
		}
		foreach ((array) scandir($dir) as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$path = $dir . '/' . $item;
			if (is_dir($path) && !is_link($path)) {
				self::rrmdir($path);
			} elseif (is_link($path)) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- a link inside a package: remove the link itself
				@unlink($path);
			} else {
				wp_delete_file($path);
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- WP_Filesystem may need FTP details here
		@rmdir($dir);
	}
}
