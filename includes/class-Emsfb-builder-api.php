<?php
/**
 * Builder API for integrations (AI Site Assistant AutoPilot and others).
 *
 * One place for what an integration needs to know about the form builder without
 * copying EFB's own knowledge: which notification add-ons can run on save, the
 * capability (plan and add-on) registry, the field templates and the
 * conditional-logic rule check. See docs/autopilot-integration.md.
 *
 * Every public answer here is free of secrets: add-on and gateway keys are only
 * ever reported as "configured: true/false".
 */
namespace Emsfb;

if (!defined('ABSPATH')) {
	exit;
}

class Builder_Api {

	/**
	 * Which notification add-ons a saved form can actually use on this site.
	 *
	 * @param object|array|null $settings Decoded EFB settings (get_setting_Emsfb('decoded')).
	 * @return array{sms:bool,telegram:bool}
	 */
	public static function notifier_state($settings) {
		$s = is_array($settings) ? (object) $settings : (is_object($settings) ? $settings : new \stdClass());
		$dir = defined('EMSFB_PLUGIN_DIRECTORY') ? EMSFB_PLUGIN_DIRECTORY : dirname(__DIR__) . '/';
		$sms_on = (isset($s->AdnSS) && intval($s->AdnSS) === 1)
			|| (function_exists('get_option') && intval(get_option('emsfb_addon_AdnSS', 0)) === 1);
		$tlg_on = (isset($s->AdnTLG) && intval($s->AdnTLG) === 1)
			|| (function_exists('get_option') && intval(get_option('emsfb_addon_AdnTLG', 0)) === 1);
		return array(
			'sms'      => $sms_on && file_exists($dir . '/vendor/smssended/smsefb.php'),
			'telegram' => $tlg_on && file_exists($dir . '/vendor/telegram/telegram-new-efb.php'),
		);
	}

	/**
	 * A5: a form that asks for SMS or Telegram notifications on a site where that
	 * add-on is not active is still saved. The notification is switched off (row 0
	 * and every field) and a warning tells the admin why, instead of refusing the
	 * whole save and losing every other change.
	 *
	 * Secrets that only the add-on may store (Telegram bot token and chat ids, the
	 * admins' phone numbers) are removed from the form JSON, which visitors can read.
	 *
	 * @param array             $rows     Decoded form (list of associative rows).
	 * @param object|array|null $settings Decoded EFB settings.
	 * @param array             $lang     text_efb() subset with smsOffAddonMissing / telegramOffAddonMissing.
	 * @return array{0:array,1:array} [rows, warnings]; a warning is ['code'=>..,'m'=>..].
	 */
	public static function disable_unavailable_notifiers(array $rows, $settings, array $lang = array()) {
		$warnings = array();
		if (!isset($rows[0]) || !is_array($rows[0])) return array($rows, $warnings);
		$state = self::notifier_state($settings);

		if (isset($rows[0]['smsnoti']) && intval($rows[0]['smsnoti']) === 1 && !$state['sms']) {
			$rows[0]['smsnoti'] = 0;
			unset($rows[0]['sms_admins_phone_no']);
			foreach ($rows as $i => $row) {
				if ($i > 0 && is_array($row) && isset($row['smsnoti'])) $rows[$i]['smsnoti'] = 0;
			}
			$warnings[] = array(
				'code' => 'sms_disabled_addon_missing',
				'm'    => isset($lang['smsOffAddonMissing']) ? $lang['smsOffAddonMissing'] : 'SMS notifications were turned off because the SMS add-on is not active. Everything else was saved.',
			);
		}
		if (isset($rows[0]['telegramnoti']) && intval($rows[0]['telegramnoti']) === 1 && !$state['telegram']) {
			$rows[0]['telegramnoti'] = 0;
			unset($rows[0]['telegram_bot_token'], $rows[0]['telegram_admin_chat_ids']);
			$warnings[] = array(
				'code' => 'telegram_disabled_addon_missing',
				'm'    => isset($lang['telegramOffAddonMissing']) ? $lang['telegramOffAddonMissing'] : 'Telegram notifications were turned off because the Telegram add-on is not active. Everything else was saved.',
			);
		}
		return array($rows, $warnings);
	}

	/**
	 * A7: "NotFoundEl" is what the settings screen reports for a control it could not
	 * read. It must never be stored as a value (it replaced a working Stripe secret on
	 * an old build). For every key that arrives as the marker the stored value is
	 * kept; a key with no usable stored value is left out. Only the key name is logged.
	 *
	 * @param array             $m      Settings as posted by the screen.
	 * @param object|array|null $stored Settings currently saved.
	 * @return array
	 */
	public static function drop_unread_setting_markers(array $m, $stored) {
		$stored = is_array($stored) ? (object) $stored : (is_object($stored) ? $stored : null);
		foreach ($m as $key => $value) {
			if ($value !== 'NotFoundEl') continue;
			if ($stored !== null && isset($stored->$key) && $stored->$key !== 'NotFoundEl') {
				$m[$key] = $stored->$key;
			} else {
				unset($m[$key]);
			}
			error_log('[EFB] settings: "' . preg_replace('/[^A-Za-z0-9_]/', '', (string) $key) . '" arrived unread (NotFoundEl); the stored value was kept.');
		}
		return $m;
	}
}
