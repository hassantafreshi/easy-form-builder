<?php
/**
 * PAY-OUT step 3 (owner approved 2026-11-18): the server pricing and the keys of Stripe, PayPal and Persia Pay left core
 * for their add-ons (legacy_gateway contract 2). These members of core's public class stay one more release as a
 * deprecated shim, so an add-on from before PAY-OUT is never fatal: each one says so with _doing_it_wrong() and either
 * hands over to the add-on (efb_legacy_server_price(), the filter efb_legacy_payment_key) or answers the visitor with the
 * clear "update the add-on" message and code pay_addon_update — nothing is charged. Remove in the next major version
 * (docs/feature-gaps/SPLIT-core-addons.fa.md §5, "legacy gateway").
 *
 * @package Emsfb
 */

namespace Emsfb\Payments;

if (!defined('ABSPATH')) {
	exit;
}

trait Legacy_Gateway_Shim {

	/** @deprecated PAY-OUT: the Stripe and PayPal add-ons price their own orders; use efb_legacy_server_price(). */
	public function fun_validation_pay_elements_efb($val_, $fs_) {
		self::legacy_shim_notice_efb(__FUNCTION__, 'efb_legacy_server_price()');
		return efb_legacy_server_price($val_, $fs_, array('form_id' => (int) $this->id));
	}

	/** @deprecated PAY-OUT: each add-on keeps its own keys (its settings screen); answered by the filter efb_legacy_payment_key. */
	public function resolve_payment_key_efb($setting, $key) {
		self::legacy_shim_notice_efb(__FUNCTION__, 'its own keys (filter efb_legacy_payment_key)');
		$v = function_exists('apply_filters') ? apply_filters('efb_legacy_payment_key', null, (string) $key) : null;
		return is_string($v) && $v !== '' ? $v : 'null';
	}

	/* The REST callbacks an add-on from before SPLIT registers on core's class: answered with "update the add-on". */
	public function pay_stripe_sub_Emsfb_api($r = null) { $this->legacy_route_update_efb('AdnSPF', __FUNCTION__); }
	public function pay_stripe_confirm_Emsfb_api($r = null) { $this->legacy_route_update_efb('AdnSPF', __FUNCTION__); }
	public function get_stripe_public_key_Emsfb_api($r = null) { $this->legacy_route_update_efb('AdnSPF', __FUNCTION__); }
	public function pay_paypal_sub_Emsfb_api($r = null) { $this->legacy_route_update_efb('AdnPAP', __FUNCTION__); }
	public function pay_paypal_capture_Emsfb_api($r = null) { $this->legacy_route_update_efb('AdnPAP', __FUNCTION__); }
	public function pay_paypal_subscription_activate_Emsfb_api($r = null) { $this->legacy_route_update_efb('AdnPAP', __FUNCTION__); }
	public function pay_persia_sub_Emsfb_api($r = null) { $this->legacy_route_update_efb('AdnPPF', __FUNCTION__); }

	private function legacy_route_update_efb($code, $fn) {
		self::legacy_shim_notice_efb($fn, 'its own REST routes');
		$name = class_exists('\\Emsfb\\Addon_Catalog') ? \Emsfb\Addon_Catalog::name($code) : $code;
		/* translators: %s: name of an Easy Form Builder add-on (a product name) */
		wp_send_json_success(array('success' => false, 'code' => 'pay_addon_update', 'm' => esc_html(sprintf(__('Update %s to keep using it with this version.', 'easy-form-builder'), $name))), 200);
	}

	public static function legacy_shim_notice_efb($fn, $instead) {
		if (function_exists('_doing_it_wrong')) {
			_doing_it_wrong('Emsfb\_Public::' . esc_html($fn), esc_html(sprintf('Part of the deprecated legacy_gateway contract, removed in the next major version of Easy Form Builder: the add-on uses %s. Update the add-on.', $instead)), '4.3.2');
		}
	}
}
