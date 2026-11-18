<?php
/**
 * Not a test. PAY-OUT step 3: the server pricing of a legacy gateway order (Stripe, PayPal) left core's
 * _Public::fun_validation_pay_elements_efb() for the add-ons (vendores-efb/<stripe|paypal>/legacy-price-efb.php, class
 * Emsfb\Legacy_Server_Price_V1). The tests that held core's method to its rules (B7, F04-G, SPLIT §6.3, input types,
 * tampering) now load the add-on's class through this fixture, unchanged: EfbLegacyPriceFixture keeps the method name
 * and the members (id, ip, get_ip_address()) the code reads. EFB_ADDONS_REPO: the add-ons repository (default: where the
 * core's vendor/stripe links to).
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
$efb_po_addons = getenv('EFB_ADDONS_REPO') ?: dirname((string) @readlink(dirname(__DIR__) . '/vendor/stripe'));
$efb_po_price = $efb_po_addons . '/stripe/legacy-price-efb.php';
if (!is_readable($efb_po_price)) { fwrite(STDERR, "the Stripe add-on's legacy-price-efb.php is not found (EFB_ADDONS_REPO)\n"); exit(1); }
require_once $efb_po_price;
unset($efb_po_addons, $efb_po_price);

class EfbLegacyPriceFixture {
	public $id = 0;
	public $ip = '';
	public function get_ip_address() { return '127.0.0.1'; }
	/** The name core's method had; the add-on's code. */
	public function fun_validation_pay_elements_efb($val_, $fs_) {
		return \Emsfb\Legacy_Server_Price_V1::price($val_, $fs_, $this, array('form_id' => (int) $this->id));
	}
}
