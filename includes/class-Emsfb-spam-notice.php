<?php
/**
 * F12b D2 (docs/feature-gaps/F12b-captcha-providers.fa.md §D2) and §5: notices of the anti-spam layer on the plugin's
 * own screens, for administrators (manage_options) only.
 *
 *  - "Turn on recommended protection": a site updated from 4.3.1 has none of spam_honeypot / spam_min_time /
 *    spam_action in its settings row, so every soft check is off (F12 decision 1: nothing changes on update). The
 *    notice offers the fresh-install defaults in one click (hidden field, 3 seconds, save as spam, 30 days) through
 *    AJAX efb_spam_notice (do enable | dismiss; nonce wp_rest). Not shown on a fresh install, while Human Shield
 *    runs (it has its own trap field), or to a user who dismissed it (user meta, per user).
 *  - "N forms use X, which is not available now": forms whose CAPTCHA add-on is gone, off, too old or above the
 *    plan, and what protects them now (Google, or the hidden field and the minimum time). Dismissed per user until
 *    the list changes.
 */

namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Spam_Notice {

	const META = 'emsfb_spam_notice_dismissed';
	const META_CP = 'emsfb_cp_notice_dismissed';

	public static function register() {
		if (!function_exists('add_action')) return;
		add_action('admin_notices', array(__CLASS__, 'render'));
		add_action('wp_ajax_efb_spam_notice', array(__CLASS__, 'ajax'));
	}

	private static function on_efb_screen() {
		$scr = function_exists('get_current_screen') ? get_current_screen() : null;
		return $scr && is_string($scr->id) && stripos($scr->id, 'emsfb') !== false;
	}

	/** The settings row has none of the spam keys (a site from before F12). */
	public static function needed($settings = null) {
		$s = Captcha::settings($settings);
		if (!$s) return false;
		foreach (array('spam_honeypot', 'spam_min_time', 'spam_action') as $k) if (array_key_exists($k, $s)) return false;
		return !(defined('EFB_HUMAN_SHIELD_RUNTIME') && EFB_HUMAN_SHIELD_RUNTIME);
	}

	/** Forms whose CAPTCHA service cannot be used now: [want => [form ids]] with what protects them. */
	public static function unavailable_forms() {
		global $wpdb;
		if (!is_object($wpdb) || !class_exists('\\Emsfb\\Captcha\\Registry')) return array();
		$out = array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from $wpdb->prefix
		$rows = $wpdb->get_results("SELECT form_id, form_structer FROM `{$wpdb->prefix}emsfb_form` WHERE form_structer LIKE '%captcha%' LIMIT 500", ARRAY_A);
		foreach ((array) $rows as $r) {
			$d = function_exists('emsfb_json_decode_efb') ? \emsfb_json_decode_efb(\emsfb_json_normalize_efb((string) $r['form_structer']), true) : json_decode((string) $r['form_structer'], true);
			if (!is_array($d) || !isset($d[0]) || !is_array($d[0]) || !Captcha::form_on($d[0])) continue;
			$e = Captcha\Registry::effective_for($d[0]);
			if (!$e['fallback'] || $e['why'] === 'keys') continue; // keys: Settings › Captchas says so already
			if (!isset($out[$e['want']])) $out[$e['want']] = array('ids' => array(), 'by' => $e['id'], 'why' => $e['why']);
			$out[$e['want']]['ids'][] = (int) $r['form_id'];
		}
		return $out;
	}

	private static function t($k, $fb) {
		$fn = function_exists('get_efbFunction') ? get_efbFunction() : null;
		$t = $fn && method_exists($fn, 'text_efb') ? $fn->text_efb(array($k)) : array();
		return is_array($t) && !empty($t[$k]) ? html_entity_decode((string) $t[$k], ENT_QUOTES, 'UTF-8') : $fb;
	}

	public static function render() {
		if (!current_user_can('manage_options') || !self::on_efb_screen()) return;
		$uid = get_current_user_id();
		$nonce = wp_create_nonce('wp_rest');
		$shown = false;
		if (self::needed() && !get_user_meta($uid, self::META, true)) {
			$shown = true;
			printf(
				'<div class="notice notice-info is-dismissible efb efb-spam-notice" id="efb-spam-notice" data-efb-notice="spam" dir="auto"><p style="unicode-bidi:plaintext">%1$s</p><p class="efb-spam-notice-actions"><button type="button" class="button button-primary" data-efb-spam-do="enable">%2$s</button> <a class="button" href="%3$s">%4$s</a></p><p class="efb-spam-notice-status" role="status" aria-live="polite" style="unicode-bidi:plaintext"></p></div>',
				esc_html(self::t('spamSuggestNotice', 'Easy Form Builder can now catch spam without a CAPTCHA: a hidden trap field and a minimum time to fill. Turn them on in Settings › Captchas.')),
				esc_html(self::t('spamTurnOnRecommended', 'Turn on recommended protection')),
				esc_url(admin_url('admin.php?page=Emsfb&state=setting&tab=captchas')),
				esc_html(self::t('captchas', 'Captchas'))
			);
		}
		$un = self::unavailable_forms();
		$sig = md5(wp_json_encode($un));
		if ($un && get_user_meta($uid, self::META_CP, true) !== $sig) {
			$shown = true;
			$lines = array();
			foreach ($un as $want => $e) {
				$code = array_search($want, Captcha\Admin::ADDONS, true);
				$name = $code !== false && class_exists('\\Emsfb\\Addon_Catalog') ? Addon_Catalog::name($code) : $want;
				$by = $e['by'] ? (Captcha\Registry::get($e['by']) ? Captcha\Registry::get($e['by'])->label() : $e['by']) : self::t('cpFallbackSoft', 'the hidden field and the minimum time');
				$txt = sprintf(self::t('cpUnavailableNotice', '%1$s forms use %2$s, which is not available now. They are protected by %3$s.'), '<bdi>' . count($e['ids']) . '</bdi>', '<bdi>' . esc_html($name) . '</bdi>', '<bdi>' . esc_html($by) . '</bdi>');
				$lines[] = '<li style="unicode-bidi:plaintext">' . wp_kses($txt, array('bdi' => array())) . ' <span class="efb-cp-ids">(' . esc_html(implode(', ', array_slice($e['ids'], 0, 10))) . ')</span></li>';
			}
			printf(
				'<div class="notice notice-warning is-dismissible efb efb-cp-notice" id="efb-cp-notice" data-efb-notice="cp" data-sig="%1$s" dir="auto"><ul>%2$s</ul><p><a class="button button-primary" href="%3$s">%4$s</a></p></div>',
				esc_attr($sig), implode('', $lines), esc_url(admin_url('admin.php?page=Emsfb_addon')), esc_html(self::t('cpInstallAddon', 'Install the add-on'))
			);
		}
		if (!$shown) return;
		$on = esc_js(self::t('spamTurnedOn', 'Spam protection is on: hidden field, 3 seconds, spam kept 30 days.'));
		$url = esc_url_raw(admin_url('admin-ajax.php'));
		echo "<script>(function(){function go(d,x){var f=new FormData();f.append('action','efb_spam_notice');f.append('do',d);f.append('nonce','" . esc_js($nonce) . "');if(x)f.append('sig',x);return fetch('" . esc_js($url) . "',{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json();});}"
			. "document.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('[data-efb-spam-do=\"enable\"]');if(b){b.disabled=true;go('enable').then(function(j){var n=document.getElementById('efb-spam-notice');if(!n)return;var s=n.querySelector('.efb-spam-notice-status');if(j&&j.success){s.textContent='" . $on . "';n.className='notice notice-success efb efb-spam-notice';var a=n.querySelector('.efb-spam-notice-actions');if(a)a.remove();}else{b.disabled=false;}});return;}"
			. "var x=e.target.closest&&e.target.closest('.notice-dismiss');if(!x)return;var n=x.closest('[data-efb-notice]');if(!n)return;go(n.getAttribute('data-efb-notice')==='cp'?'dismiss_cp':'dismiss',n.getAttribute('data-sig'));});})();</script>";
	}

	/** do: enable (the fresh-install defaults), dismiss (this user), dismiss_cp (this list of unavailable services). */
	public static function ajax() {
		if (!check_ajax_referer('wp_rest', 'nonce', false) || !current_user_can('manage_options')) wp_send_json_error(array('m' => 'forbidden'), 403);
		$do = isset($_POST['do']) ? sanitize_key(wp_unslash($_POST['do'])) : '';
		if ($do === 'dismiss') { update_user_meta(get_current_user_id(), self::META, time()); wp_send_json_success(array('done' => 'dismiss')); }
		if ($do === 'dismiss_cp') { update_user_meta(get_current_user_id(), self::META_CP, isset($_POST['sig']) ? sanitize_key(wp_unslash($_POST['sig'])) : ''); wp_send_json_success(array('done' => 'dismiss_cp')); }
		if ($do !== 'enable') wp_send_json_error(array('m' => 'unknown'), 400);
		$s = function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null;
		if (!is_object($s)) wp_send_json_error(array('m' => 'settings'), 500);
		foreach (array('spam_honeypot' => '1', 'spam_min_time' => '3', 'spam_action' => 'spam', 'spam_retention_days' => '30') as $k => $v) $s->$k = $v;
		$fn = get_efbFunction();
		$ok = $fn && method_exists($fn, 'set_setting_Emsfb') ? $fn->set_setting_Emsfb($s, isset($s->emailSupporter) ? $s->emailSupporter : '') : false;
		wp_send_json_success(array('done' => 'enable', 'saved' => $ok !== false));
	}
}
