<?php

namespace Emsfb;

/**
 * Class _Public
 * @package Emsfb
 */

class persiapayEFB{

    public function __construct() {
        //error_log('persianDatePicker');

        add_action('efb_enqueue_persia', [$this, 'include_persia_efb']);


    }

    public function include_persia_efb(){

		wp_register_script('persia_pay-efb.js',  EMSFB_PLUGIN_URL .'/vendor/persiapay/persia_pay-efb.js', array('jquery', 'efb-main-js'),EMSFB_PLUGIN_VERSION,true);
		wp_enqueue_script('persia_pay-efb.js');
	}

}
