<?php
/**
 * Plugin Name: EFB e2e F10 helpers (local test site only)
 * Description: For the F10 tests, through the plugin's own filters only (what a site owner could do): efb_layout_plan
 *              opens the Pro part of F10 (Show this field to) while the option efb_e2e_f10_plan is "open" (on this
 *              site a Pro plan without a licence falls back to Free Plus); emsfb_cache_bypass keeps every page
 *              cacheable (a site that caches all pages, the visibility context "vis" included) while the option
 *              efb_e2e_f10_nobypass is "1", so a page holds the view every visitor shares. Copied by ctl.php f10-mu.
 */
add_filter(
	'efb_layout_plan',
	static function ( $need, $part = '' ) {
		return get_option( 'efb_e2e_f10_plan' ) === 'open' && $need === 'pro' ? 'free_plus' : $need;
	},
	10,
	2
);
add_filter(
	'emsfb_cache_bypass',
	static function ( $bypass, $context = '', $id = 0 ) {
		return get_option( 'efb_e2e_f10_nobypass' ) === '1' ? false : $bypass;
	},
	10,
	3
);
