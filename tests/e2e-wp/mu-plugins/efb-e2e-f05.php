<?php
/**
 * Plugin Name: EFB e2e F05 helpers (local test site only)
 * Description: For tests/test-choices-*-http.php, tests/test-stock-concurrency-wp.php and the e2e scenarios
 *              "f05*", through the plugin's own filter only (what a site owner could do): efb_choices_plan
 *              opens the Pro parts of F05 (per-choice capacity, dynamic choices) while the option
 *              efb_e2e_f05_plan is "open"; on this site a Pro plan without a licence falls back to Free Plus.
 *              With "free" every F05 part needs Free (the Free Plus ranking too), with "locked" the Pro parts
 *              stay locked (the default rule). Copied by ctl.php f05-mu.
 */
add_filter(
	'efb_choices_plan',
	static function ( $need, $part ) {
		$o = get_option( 'efb_e2e_f05_plan' );
		if ( $o === 'open' && $need === 'pro' ) return 'free_plus';
		if ( $o === 'free' ) return 'free';
		return $need;
	},
	10,
	2
);
