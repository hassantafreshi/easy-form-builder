<?php

namespace Emsfb;

/**
 * Class _Public
 * @package Emsfb
 */

class arabicDatePickerEfb{

    public function __construct() {
        wp_register_style('hijir-datetimepicker-css-efb', EMSFB_PLUGIN_URL . 'vendor/arabicdatepicker/assets/css/bootstrap-datetimepicker.css', true,'3.5.10' );
        wp_enqueue_style('hijir-datetimepicker-css-efb');

        wp_enqueue_script('hijri-query3-4-1-jq-efb', EMSFB_PLUGIN_URL . 'vendor/arabicdatepicker/assets/js/jquery341.min.js',array('jquery'), true,'3.5.10');
        wp_enqueue_script('hijri-query3-4-1-jq-efb');

        wp_enqueue_script('hijri-moment-js-efb', EMSFB_PLUGIN_URL . 'vendor/arabicdatepicker/assets/js/moment.min.js',array('jquery'), true,'3.5.10');
        wp_enqueue_script('hijri-moment-js-efb');

        wp_enqueue_script('hijri-datetimepicker-js-efb', EMSFB_PLUGIN_URL . 'vendor/arabicdatepicker/assets/js/bootstrap-hijri-datetimepicker.js',array('jquery'), true,'3.5.10');
        wp_enqueue_script('hijri-datetimepicker-js-efb');





    }

}
