<?php 

namespace Emsfb;

/**
 * Class _Public
 * @package Emsfb
 */

class paypalefb{

    public function __construct() { 
        //error_log('persianDatePicker');
        wp_enqueue_script('paypalefb-js', EMSFB_PLUGIN_URL . 'vendor/paypal/assets/js/paypal_efb.js',array('jquery', 'efb-main-js'), true,'3.8.6');
       
    }

}
