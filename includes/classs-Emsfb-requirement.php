<?php
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
                'title'       => 'Email system is working properly.',
                'description' => 'Your server is able to send emails using the default PHP mail system.',
                'id'         => 'mail_function_ok',
            ],
            'details' => [],
        ];
       
        $email_notifi = sprintf(
            esc_html__('%s notification', 'easy-form-builder'),
            esc_html__('Email', 'easy-form-builder')
        );
        $warning =' '. sprintf(
            esc_html__('Disabling this feature may affect the proper functionality of Easy Form Builder. If you plan to use the %s feature, please ensure it is enabled.', 'easy-form-builder'),
            $email_notifi
        );


        if (!function_exists('mail')) {
            return [
                'status' => 'error',
                'message' => [
                    'title'       => esc_html__('Email system is not available.', 'easy-form-builder'),
                    'description' => esc_html__('The PHP mail() function is missing. Your server cannot send emails.', 'easy-form-builder') . $warning,
                    'id'         => 'mail_function_missing',
                ]
            ];
        }

        $disabled = array_map('trim', explode(',', ini_get('disable_functions')));
        if (in_array('mail', $disabled)) {
            return [
                'status' => 'error',
                'message' => [
                    'title'       => esc_html__('Email sending is blocked by server settings.', 'easy-form-builder'),
                    'description' => esc_html__('The mail() function is disabled in your server’s PHP configuration (php.ini).', 'easy-form-builder') . $warning,
                    'id'         => 'mail_function_disabled',
                ]
            ];
        }

        if (!function_exists('wp_mail')) {
            return [
                'status' => 'error',
                'message' => [
                    'title'       => esc_html__('WordPress mail function not found.', 'easy-form-builder'),
                    'description' => esc_html__('The wp_mail() function is missing or not available. WordPress email features may be broken.', 'easy-form-builder') . $warning,
                    'id'         => 'wp_mail_function_missing',
                ]
            ];
        }

        $smtp = ini_get('SMTP');
        $sendmail = ini_get('sendmail_path');
        if (empty($smtp) && empty($sendmail)) {
            $results['status'] = 'warning';
            $results['message'] = [
                'title'       => esc_html__('No email handler configured.', 'easy-form-builder'),
                'description' => esc_html__('Your server has no SMTP host or sendmail path set. Emails may not be delivered.', 'easy-form-builder') . $warning,
                'id'         => 'smtp_sendmail_empty',
            ];
           
        }

        $to = get_option('admin_email');
        $subject = esc_html__('Test Email from Easy Form Builder', 'easy-form-builder');
        $body = esc_html__('This is a test email sent by your WordPress site to check if your server can send emails.', 'easy-form-builder');
        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        ob_start();
        $sent = wp_mail($to, $subject, $body, $headers);
        $debug = trim(ob_get_clean());

        if (!$sent) {
           $results= [
                'status' => 'error',
                'message' => [
                    'title'       => esc_html__('Test email could not be sent.', 'easy-form-builder'),
                    'description' => sprintf(
                        esc_html__('It seems that your WordPress site could not send a test email. To manually test your email system, go to Easy Form Builder > Settings > Email Settings tab and click the "%s" button.', 'easy-form-builder') .  $warning,
                        esc_html__('Check Email Server', 'easy-form-builder')
                    ),
                    'id'         => 'mail_function_failed',
                ],
                'details' => [
                    esc_html__('wp_mail() returned false.', 'easy-form-builder'),
                    sprintf(esc_html__('Attempted to send to: %s', 'easy-form-builder'), esc_html($to)),
                    esc_html($debug),
                ],
            ];
        }else{
    
            $results['message'] = [
                'title'       => esc_html__('Test email sent successfully.', 'easy-form-builder'),
                'description' => sprintf(
                    esc_html__('A test email was sent successfully to %s. Your server is able to send emails using the default PHP mail system.', 'easy-form-builder'),
                    esc_html($to)
                ),
                'id'         => 'mail_function_ok',
            ];
        }

       
        
        return $results;
    }
}
