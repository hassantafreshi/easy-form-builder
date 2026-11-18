<?php
/**
 * F12b-L (approved by owner 2026-11-15): the settings screen of one CAPTCHA add-on,
 * admin.php?page=Emsfb_captcha&provider=<id> — a standard WordPress admin page (back link, title, notices, Save)
 * drawn by core from the add-on's settings_schema(), so an add-on ships no screen of its own. Opened from the add-on's
 * row in Settings › Captchas; an add-on that is not installed and active gets a "not found" page (HTTP 404). Saved
 * with a plain form (admin-post.php, nonce, manage_options) through Admin::save(), i.e. into the add-on's own key of
 * the settings row with set_setting_Emsfb(). Secrets are write-only: an empty password box keeps the saved key, the
 * box never holds it, "Remove the saved key" clears it. Works without JavaScript; keyboard, screen readers and right
 * to left come with WordPress' own admin markup.
 */

namespace Emsfb\Captcha;

if (!defined('ABSPATH')) {
	exit;
}

class Screen {

	const SLUG = 'Emsfb_captcha';

	public static function register() {
		add_action('admin_menu', array(__CLASS__, 'menu'), 20);
		add_action('admin_post_efb_captcha_settings', array(__CLASS__, 'save'));
		add_action('wp_ajax_efb_captcha_provider_save', array(__NAMESPACE__ . '\\Admin', 'ajax_save'));
	}

	public static function can() {
		return current_user_can('manage_options');
	}

	/** A page without a menu entry of its own (the add-on's row opens it); Easy Form Builder's menu stays open. */
	public static function menu() {
		$hook = add_submenu_page('', self::t('cpAddonProviders', 'CAPTCHA add-ons'), '', 'manage_options', self::SLUG, array(__CLASS__, 'page'));
		if ($hook) add_action('load-' . $hook, array(__CLASS__, 'load'));
		add_filter('parent_file', array(__CLASS__, 'parent_file'));
	}

	public static function parent_file($f) {
		return self::on_screen() ? 'Emsfb' : $f;
	}

	private static function on_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which screen, read only
		return is_admin() && isset($_GET['page']) && $_GET['page'] === self::SLUG;
	}

	/** The add-on the page is for: installed and active (registered, not switched off), never a Google mode; else null. */
	public static function provider() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen parameter
		$id = isset($_GET['provider']) ? sanitize_key(wp_unslash($_GET['provider'])) : '';
		$p = $id !== '' && !Registry::is_google($id) ? Registry::get($id) : null;
		return $p && $p->addon_key() !== null && Registry::state($p) !== 'addon' ? $p : null;
	}

	/** Before any output: the title in <title>, HTTP 404 for an add-on that is not here. */
	public static function load() {
		$p = self::provider();
		$GLOBALS['title'] = $p ? sprintf(self::t('cpSettingsOf', '%s settings'), $p->label()) : self::t('notFound', 'Not Found');
		if (!$p) status_header(404);
		self::own_notices_only($p);
	}

	/**
	 * This focused screen shows only its own notices (printed in page()): the site-wide notices of Easy Form Builder and
	 * of its other add-ons (SPLIT, payment readiness, renewal, e-mail delivery …) stay on the plugin's other screens.
	 * Theirs: a callback of the Emsfb namespace or defined in the plugin's folder (add-ons live in its vendor/); the
	 * folder of this provider's own add-on is kept. Other plugins' and WordPress' notices are left as on any screen.
	 */
	private static function own_notices_only($p) {
		global $wp_filter;
		if (empty($wp_filter['admin_notices'])) return;
		$root = defined('EMSFB_PLUGIN_DIRECTORY') ? wp_normalize_path(EMSFB_PLUGIN_DIRECTORY) : "\0";
		$keep = $p ? wp_normalize_path(dirname((new \ReflectionClass($p))->getFileName())) . '/' : "\0";
		foreach ($wp_filter['admin_notices']->callbacks as $prio => $cbs) {
			foreach ($cbs as $cb) {
				$f = $cb['function'];
				try {
					$r = is_array($f) ? new \ReflectionMethod($f[0], $f[1]) : (is_string($f) && strpos($f, '::') ? new \ReflectionMethod($f) : new \ReflectionFunction($f));
				} catch (\ReflectionException $e) { continue; }
				$file = wp_normalize_path((string) $r->getFileName());
				$ns = $r instanceof \ReflectionMethod ? $r->getDeclaringClass()->getName() : $r->getNamespaceName();
				if ((stripos($ns, 'Emsfb') === 0 || strpos($file, $root) === 0) && strpos($file, $keep) !== 0) remove_action('admin_notices', $f, $prio);
			}
		}
	}

	/** A phrase of core or of an add-on (text_efb: an owner's Localization text first), plain text. */
	public static function t($key, $fallback) {
		static $cache = array();
		if (!array_key_exists($key, $cache)) {
			$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
			$r = $fn && method_exists($fn, 'text_efb') ? $fn->text_efb(array($key)) : array();
			$cache[$key] = is_array($r) && isset($r[$key]) && is_string($r[$key]) && $r[$key] !== '' ? html_entity_decode($r[$key], ENT_QUOTES, 'UTF-8') : null;
		}
		return $cache[$key] !== null ? $cache[$key] : $fallback;
	}

	/** Ready / needs keys / off for your plan / turned off, as the main window's list and AutoPilot say it. */
	public static function status($p) {
		$st = Registry::state($p);
		if ($st === 'plan') return array('plan', self::t('cpStatusPlan', 'Off for your plan'));
		if ($st !== null) return array('off', self::t('cpUpdateAddonShort', 'Update or turn on the add-on'));
		return $p->ready() ? array('ready', self::t('cpStatusReady', 'Ready')) : array('keys', self::t('cpStatusKeys', 'Needs keys'));
	}

	public static function back_url() {
		return admin_url('admin.php?page=Emsfb&state=setting&tab=captchas');
	}

	public static function page() {
		if (!self::can()) wp_die(esc_html__('Sorry, you are not allowed to access this page.', 'easy-form-builder'), 403);
		$p = self::provider();
		$back = '<p class="efb-cps-back"><a href="' . esc_url(self::back_url()) . '">' . esc_html(self::t('cpBackToSettings', 'Back to Settings')) . '</a></p>';
		echo '<div class="wrap efb-cps" id="efbCaptchaScreen">';
		self::style();
		if (!$p) {
			echo $back; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
			echo '<h1>' . esc_html(self::t('notFound', 'Not Found')) . '</h1>';
			echo '<div class="notice notice-error inline efb-notice efb-cps-missing" role="alert"><p>' . esc_html(self::t('cpScreenMissing', 'This CAPTCHA add-on is not installed or not active.')) . '</p></div>';
			echo '<p><a href="' . esc_url(admin_url('admin.php?page=Emsfb_addon')) . '">' . esc_html(self::t('cpInstallAddon', 'Install the add-on')) . '</a></p></div>';
			return;
		}
		$id = $p->id();
		echo $back; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		echo '<h1>' . sprintf(esc_html(self::t('cpSettingsOf', '%s settings')), '<bdi>' . esc_html($p->label()) . '</bdi>') . '</h1>';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the message after the redirect of a save
		$msg = isset($_GET['efb_msg']) ? sanitize_key(wp_unslash($_GET['efb_msg'])) : '';
		$ref = isset($_GET['refused']) ? array_filter(array_map('sanitize_key', explode(',', sanitize_text_field(wp_unslash($_GET['refused']))))) : array();
		// phpcs:enable
		if ($msg === 'saved') echo '<div class="notice notice-success inline efb-notice" role="status"><p>' . esc_html(self::t('cpSettingsSaved', 'Settings saved.')) . '</p></div>';
		if ($msg === 'error') echo '<div class="notice notice-error inline efb-notice" role="alert"><p>' . esc_html(self::t('somethingWentWrongPleaseRefresh', 'Something went wrong. Please refresh the page and try again.')) . '</p></div>';
		if ($ref) {
			$names = array();
			foreach ($p->settings_schema() as $f) if (in_array(sanitize_key($f['key']), $ref, true)) $names[] = self::t($f['label_key'] ?? '', $f['label'] ?? $f['key']);
			if ($names) echo '<div class="notice notice-error inline efb-notice" role="alert"><p>' . esc_html(sprintf(self::t('cpvRefused', 'Not saved: %s'), implode(', ', $names))) . '</p></div>';
		}
		list($code, $label) = self::status($p);
		echo '<p class="efb-cps-status">' . esc_html(self::t('cpStatus', 'Status:')) . ' <strong class="efb-cps-st efb-cps-st-' . esc_attr($code) . '" data-status="' . esc_attr($code) . '">' . esc_html($label) . '</strong></p>';
		if ($code === 'plan') echo '<div class="notice notice-warning inline efb-notice"><p>' . esc_html(self::t('cpPlanNeeded', 'Available on Free Plus and Pro')) . '</p></div>';
		if ($code === 'keys') echo '<div class="notice notice-warning inline efb-notice"><p>' . esc_html(self::t('captchaKeyMissing', 'Keys are not set for this service.')) . '</p></div>';
		$pv = $p->privacy();
		if (!empty($pv['where'])) echo '<p class="description efb-cps-help">' . esc_html(sprintf(self::t('cpPrivacyWhere', 'Data goes to: %s'), $pv['where'])) . '</p>';
		echo '<p class="description efb-cps-help">' . esc_html(self::t('cpSecretsNote', 'Keys are stored encrypted and never shown again. Leave a key empty to keep the saved one.')) . '</p>';

		$cat = array_values(array_filter(Admin::catalog(), function ($e) use ($id) { return $e['id'] === $id; }));
		$fields = $cat ? $cat[0]['settings'] : array();
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="efb-cps-form" novalidate>';
		wp_nonce_field('efb_captcha_settings_' . $id);
		echo '<input type="hidden" name="action" value="efb_captcha_settings"><input type="hidden" name="provider" value="' . esc_attr($id) . '">';
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ($fields as $f) echo self::row($id, $f); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in row()
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary" id="efbCpsSave">' . esc_html(self::t('save', 'Save')) . '</button> ';
		echo '<a class="button" href="' . esc_url(self::back_url()) . '">' . esc_html(self::t('cpBackToSettings', 'Back to Settings')) . '</a></p></form></div>';
	}

	/** One schema field: label, control, help (described by), the saved secret's mask and "Remove the saved key". */
	private static function row($id, array $f) {
		$k = sanitize_key($f['key']);
		$type = $f['type'] ?? 'text';
		$fid = 'efbcps_' . $id . '_' . $k;
		$label = self::t($f['label_key'] ?? '', $f['label'] ?? $k);
		$help = !empty($f['help_key']) || !empty($f['help']) ? self::t($f['help_key'] ?? '', $f['help'] ?? '') : '';
		$desc = array();
		$after = '';
		if ($help !== '') { $desc[] = $fid . '-help'; $after .= '<p class="description efb-cps-help" id="' . esc_attr($fid . '-help') . '">' . esc_html($help) . '</p>'; }
		$req = !empty($f['required']) ? ' aria-required="true"' : '';
		$v = isset($f['value']) && is_scalar($f['value']) ? (string) $f['value'] : '';
		$h = '<tr><th scope="row"><label for="' . esc_attr($fid) . '">' . esc_html($label) . '</label></th><td>';
		if ($type === 'secret') {
			$saved = $f['saved'] ?? '';
			$mask = is_array($saved) ? (isset($saved['last4']) ? '•••• ' . $saved['last4'] : '••••') : '';
			if ($mask !== '') {
				$desc[] = $fid . '-keep';
				$after = '<p class="description efb-cps-help" id="' . esc_attr($fid . '-keep') . '">' . esc_html(sprintf(self::t('secretSavedMask', 'Saved (%s)'), $mask) . ' ' . self::t('secretKeep', 'Leave empty to keep the saved key.')) . '</p>'
					. '<p><label><input type="checkbox" name="clear[' . esc_attr($k) . ']" value="1" id="' . esc_attr($fid . '-clear') . '"> ' . esc_html(self::t('cpRemoveSaved', 'Remove the saved key')) . '</label></p>' . $after;
			}
			$h .= '<input type="password" class="regular-text code" dir="ltr" autocomplete="new-password" spellcheck="false" id="' . esc_attr($fid) . '" name="sec[' . esc_attr($k) . ']" value=""'
				. ($mask !== '' ? ' placeholder="' . esc_attr(sprintf(self::t('secretSavedMask', 'Saved (%s)'), $mask)) . '"' : $req) . self::described($desc) . ' data-secret="1" data-saved="' . ($mask !== '' ? '1' : '') . '">';
		} elseif ($type === 'select' || $type === 'enum') {
			$h .= '<select id="' . esc_attr($fid) . '" name="pub[' . esc_attr($k) . ']"' . self::described($desc) . '>';
			foreach ((array) ($f['values'] ?? array()) as $o) $h .= '<option value="' . esc_attr($o) . '"' . selected($v === '' ? (string) ($f['default'] ?? '') : $v, (string) $o, false) . '>' . esc_html(self::t('cpv_' . $o, (string) $o)) . '</option>';
			$h .= '</select>';
		} elseif ($type === 'toggle') {
			$h = '<tr><th scope="row">' . esc_html($label) . '</th><td><input type="hidden" name="pub[' . esc_attr($k) . ']" value=""><label for="' . esc_attr($fid) . '"><input type="checkbox" id="' . esc_attr($fid) . '" name="pub[' . esc_attr($k) . ']" value="1"' . checked($v, '1', false) . self::described($desc) . '> ' . esc_html($label) . '</label>';
		} elseif ($type === 'textarea') {
			$h .= '<textarea class="large-text" rows="8" dir="auto" id="' . esc_attr($fid) . '" name="pub[' . esc_attr($k) . ']"' . self::described($desc) . '>' . esc_textarea($v) . '</textarea>';
		} else {
			$ltr = $type === 'number' || !empty($f['public']) || !empty($f['pattern']);
			$h .= '<input type="text" class="regular-text' . ($ltr ? ' code' : '') . '"' . ($ltr ? ' dir="ltr"' : ' dir="auto"') . ($type === 'number' ? ' inputmode="numeric"' : '') . ' autocomplete="off" spellcheck="false" id="' . esc_attr($fid) . '" name="pub[' . esc_attr($k) . ']" value="' . esc_attr($v) . '"' . $req . self::described($desc) . '>';
		}
		return $h . $after . '</td></tr>';
	}

	private static function described(array $ids) {
		return $ids ? ' aria-describedby="' . esc_attr(implode(' ', $ids)) . '"' : '';
	}

	/** admin-post.php?action=efb_captcha_settings: nonce, manage_options, Admin::save(), back to the screen. */
	public static function save() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked by check_admin_referer() below
		$id = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : '';
		check_admin_referer('efb_captcha_settings_' . $id);
		if (!self::can()) wp_die(esc_html__('Sorry, you are not allowed to access this page.', 'easy-form-builder'), 403);
		$p = $id !== '' && !Registry::is_google($id) ? Registry::get($id) : null;
		$url = admin_url('admin.php?page=' . self::SLUG . '&provider=' . rawurlencode($id));
		if (!$p || $p->addon_key() === null) { wp_safe_redirect($url); exit; }
		$pub = array(); $sec = array();
		$in = function ($k) { return isset($_POST[$k]) && is_array($_POST[$k]) ? wp_unslash($_POST[$k]) : array(); }; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- every value goes through the add-on's schema in Admin::save()
		$posted_pub = $in('pub'); $posted_sec = $in('sec'); $clear = $in('clear');
		// phpcs:enable
		foreach ($p->settings_schema() as $f) {
			$k = $f['key'];
			if (($f['type'] ?? '') === 'secret') {
				if (!empty($clear[$k])) $sec[$k] = '__clear__';
				elseif (isset($posted_sec[$k]) && is_string($posted_sec[$k])) $sec[$k] = $posted_sec[$k];
			} elseif (array_key_exists($k, $posted_pub) && is_scalar($posted_pub[$k])) {
				$pub[$k] = (string) $posted_pub[$k];
			}
		}
		$r = Admin::save($id, $pub, $sec);
		$q = array('efb_msg' => $r['ok'] ? 'saved' : 'error');
		if ($r['refused']) $q['refused'] = implode(',', array_map('sanitize_key', $r['refused']));
		wp_safe_redirect(add_query_arg($q, $url));
		exit;
	}

	private static function style() {
		// A title or line still in English on a right-to-left site keeps its own word order (plaintext), aligned right.
		echo '<style>#efbCaptchaScreen .efb-cps-help,#efbCaptchaScreen h1,#efbCaptchaScreen .efb-cps-status{unicode-bidi:plaintext;text-align:start}.rtl #efbCaptchaScreen h1,.rtl #efbCaptchaScreen .efb-cps-status,.rtl #efbCaptchaScreen .efb-cps-help{text-align:right}#efbCaptchaScreen .efb-cps-st-ready{color:#00733b}#efbCaptchaScreen .efb-cps-st-keys,#efbCaptchaScreen .efb-cps-st-plan,#efbCaptchaScreen .efb-cps-st-off{color:#a15c00}'
			. '#efbCaptchaScreen input.code,#efbCaptchaScreen textarea{max-width:100%;box-sizing:border-box}#efbCaptchaScreen .efb-cps-back a,#efbCaptchaScreen .submit .button{min-height:44px;display:inline-flex;align-items:center}'
			. '#efbCaptchaScreen input[type=checkbox]{min-width:1.25rem;min-height:1.25rem}#efbCaptchaScreen .submit{display:flex;flex-wrap:wrap;gap:8px}</style>';
	}
}
