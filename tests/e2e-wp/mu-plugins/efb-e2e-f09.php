<?php
/**
 * Plugin Name: EFB e2e F09 helpers (local test site only)
 * Description: For the e2e scenarios f09*, through the plugin's own filters only (what a site owner could do):
 *              efb_repeater_plan opens the Pro part of F09 (repeating rows) and efb_calc_plan the calc price while
 *              the option efb_e2e_f09_plan is "open"; on this site a Pro plan without a licence falls back to Free
 *              Plus. Copied by ctl.php f09-mu.
 */
add_filter(
	'efb_repeater_plan',
	static function ( $need, $part ) {
		return get_option( 'efb_e2e_f09_plan' ) === 'open' && $need === 'pro' ? 'free_plus' : $need;
	},
	10,
	2
);
add_filter(
	'efb_calc_plan',
	static function ( $need, $part ) {
		return get_option( 'efb_e2e_f09_plan' ) === 'open' && $need === 'pro' ? 'free_plus' : $need;
	},
	11,
	2
);
