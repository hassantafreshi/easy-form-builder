<?php
/**
 * Plugin Name: EFB e2e F04 helpers (local test site only)
 * Description: For the e2e scenario "f04pay", through the plugin's own filter only (what a site owner could do):
 *              efb_calc_plan opens the Pro part of F04 (a calc result as the amount to pay) while the option
 *              efb_e2e_f04_plan is "open"; on this site a Pro plan without a licence falls back to Free Plus.
 *              Copied by ctl.php f04-mu.
 */
add_filter(
	'efb_calc_plan',
	static function ( $need, $part ) {
		return get_option( 'efb_e2e_f04_plan' ) === 'open' && $need === 'pro' ? 'free_plus' : $need;
	},
	10,
	2
);
