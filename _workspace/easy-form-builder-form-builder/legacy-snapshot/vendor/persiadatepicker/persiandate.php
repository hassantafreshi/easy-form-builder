<?php 

namespace Emsfb;

/**
 * Class _Public
 * @package Emsfb
 */

class persianDatePickerEFB{

    public function __construct() { 
        //error_log('persianDatePicker');
        wp_register_style('persiandatapicker-css-efb', EMSFB_PLUGIN_URL . 'vendor/persiadatepicker/assets/css/persiandatapicker.css', true,'3.5.9' );
        wp_enqueue_style('persiandatapicker-css-efb');

        wp_enqueue_script('persiandatapicker-jq-efb', EMSFB_PLUGIN_URL . 'vendor/persiadatepicker/assets/js/jquery-1.10.1.min.js',array('jquery'), true,'3.5.9');
        wp_enqueue_script('persiandatapicker-jq-efb'); 
        
        wp_enqueue_script('persiandatapicker-js-efb', EMSFB_PLUGIN_URL . 'vendor/persiadatepicker/assets/js/persiandatepicker.js',array('jquery'), true,'3.5.9');
        wp_enqueue_script('persiandatapicker-js-efb'); 
        
    }

}
