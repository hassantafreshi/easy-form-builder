<?php
namespace Emsfb;

if (!defined('ABSPATH')) exit;

class CheckRequirementEmsfb {

    const OPTION_KEY = 'emsfb_email_status';

    public static function run_and_save_efb() {
        $result = self::check_email_capability_efb();
        update_option(self::OPTION_KEY, $result, false);
    }

    public static function get_result() {
        return get_option(self::OPTION_KEY);
    }

    public static function check_email_capability_efb() {

        $results = [
            'status' => 'ok',
            'message' => [
                'id' => 'mail_function_ok',
            ],
            'details' => [],
        ];

        if (!function_exists('mail')) {
            return [
                'status' => 'error',
                'message' => [
                    'id' => 'mail_function_missing',
                ]
            ];
        }

        $disabled = array_map('trim', explode(',', ini_get('disable_functions')));
        if (in_array('mail', $disabled)) {
            return [
                'status' => 'error',
                'message' => [
                    'id' => 'mail_function_disabled',
                ]
            ];
        }

        if (!function_exists('wp_mail')) {
            return [
                'status' => 'error',
                'message' => [
                    'id' => 'wp_mail_function_missing',
                ]
            ];
        }

        $smtp = ini_get('SMTP');
        $sendmail = ini_get('sendmail_path');
        if (empty($smtp) && empty($sendmail)) {
            $results['status'] = 'warning';
            $results['message'] = [
                'id' => 'smtp_sendmail_empty',
            ];

        }

        $to = get_option('admin_email');
        $subject ='Test Email from Easy Form Builder';
        $body = 'This is a test email sent by your WordPress site to check if your server can send emails.';
        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        ob_start();
        $sent = wp_mail($to, $subject, $body, $headers);
        $debug = trim(ob_get_clean());

        if (!$sent) {
           $results= [
                'status' => 'error',
                'message' => [
                    'id' => 'mail_function_failed',
                ]
            ];
        }else{

            $results['message'] = [
                'id' => 'mail_function_ok',
            ];
        }

        return $results;
    }
}
