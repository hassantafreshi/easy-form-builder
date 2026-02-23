<?php

if (!defined('ABSPATH')) {
	die("Direct access of plugin files is not allowed.");
}

class Emsfb_Shield_SilentCaptcha_Integration {
	public const SETTING_KEY = 'shield_silent_captcha';
	public const FORM_KEY = 'shield_silent_captcha';

	public function __construct() {
		add_filter('efb_admin_localize_vars', [$this, 'filter_admin_localize_vars'], 10, 2);
		add_filter('efb_submit_bot_decision', [$this, 'filter_submit_bot_decision'], 10, 2);
	}

	public function filter_admin_localize_vars($vars, $context = '') {
		if (!is_array($vars)) {
			return $vars;
		}
		$vars['shield_available'] = $this->is_shield_available_efb();
		return $vars;
	}

	public function filter_submit_bot_decision($should_block, $context = array()) {
		if ($should_block === true) {
			return true;
		}
		if (!is_array($context)) {
			return false;
		}

		$setting = isset($context['setting']) && is_array($context['setting']) ? $context['setting'] : [];
		$form = isset($context['form']) && is_array($context['form']) ? $context['form'] : [];
		$shield_enabled = isset($setting[self::SETTING_KEY]) && $this->is_true_state_efb($setting[self::SETTING_KEY]);
		if (array_key_exists(self::FORM_KEY, $form)) {
			$shield_enabled = $this->is_true_state_efb($form[self::FORM_KEY]);
		}
		if (!$shield_enabled) {
			return false;
		}

		$ip = isset($context['ip']) ? sanitize_text_field((string) $context['ip']) : '';
		if ($ip === '') {
			return false;
		}

		return $this->get_shield_bot_verdict_efb($ip) === true;
	}

	private function is_true_state_efb($value): bool {
		return in_array($value, [true, 1, '1', 'true'], true);
	}

	private function is_shield_available_efb(): bool {
		return is_callable('\FernleafSystems\Wordpress\Plugin\Shield\Functions\test_ip_is_bot')
			|| is_callable('shield_test_ip_is_bot');
	}

	private function get_shield_bot_verdict_efb(string $ip): ?bool {
		try {
			if (is_callable('\FernleafSystems\Wordpress\Plugin\Shield\Functions\test_ip_is_bot')) {
				$result = \FernleafSystems\Wordpress\Plugin\Shield\Functions\test_ip_is_bot($ip);
			} elseif (is_callable('shield_test_ip_is_bot')) {
				$result = shield_test_ip_is_bot($ip);
			} else {
				return null;
			}
		} catch (\Throwable $e) {
			return null;
		}

		if ($result === true) {
			return true;
		}
		if ($result === false) {
			return false;
		}
		return null;
	}
}
