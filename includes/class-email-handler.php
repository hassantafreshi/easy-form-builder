<?php
/**
 * Email Handler for Easy Form Builder
 * Separates all email functionality from main efbFunction class for better performance and caching
 *
 * @package    Easy Form Builder
 * @subpackage Email Handler
 * @since      1.0
 */

defined('ABSPATH') || exit;

/**
 * EmsfbEmailHandler Class
 * Handles all email-related functionality with proper text translation integration
 */
class EmsfbEmailHandler {

    /**
     * efbFunction instance for accessing text translations and utilities
     * @var efbFunction
     */
    private $efb_instance;

    /**
     * Static efbFunction instance for shared access
     * @var efbFunction
     */
    private static $efb_function = null;

    /**
     * Cached text translations to avoid repeated lookups
     * @var array
     */
    private static $text_cache = [];

    /**
     * Constructor - accepts efbFunction instance for dependency injection
     *
     * @param efbFunction $efb_instance Instance of efbFunction class
     */
    public function __construct($efb_instance = null) {
        $this->efb_instance = $efb_instance;
    }

    /**
     * Get text translations using efbFunction instance
     * Caches results to avoid repeated database queries
     *
     * @param array $text_keys Array of translation keys
     * @return array Translated text array
     */
    private function get_text_efb($text_keys) {
        $cache_key = md5(serialize($text_keys));

        if (isset(self::$text_cache[$cache_key])) {
            return self::$text_cache[$cache_key];
        }

        // Get efbFunction instance efficiently
        if (self::$efb_function === null) {
            if (class_exists('Emsfb') && method_exists('Emsfb', 'get_efbFunction')) {
                self::$efb_function = Emsfb::get_efbFunction();
            } else {
                // Fallback للحصول على efbFunction
                global $efbFunction;
                if ($efbFunction instanceof efbFunction) {
                    self::$efb_function = $efbFunction;
                }
            }
        }

        if (self::$efb_function && method_exists(self::$efb_function, 'text_efb')) {
            $result = self::$efb_function->text_efb($text_keys);
            self::$text_cache[$cache_key] = $result;
            return $result;
        }

        // Fallback to WordPress translations
        $fallback = [];
        foreach ($text_keys as $key) {
            $fallback[$key] = $this->get_fallback_text($key);
        }

        return count($text_keys) === 1 ? $fallback[array_keys($fallback)[0]] : $fallback;
    }

    /**
     * Fallback text translations
     *
     * @param string $key
     * @return string
     */
    private function get_fallback_text($key) {
        $fallbacks = [
            'msgdml' => __('To explore the full functionality and settings of Easy Form Builder, including email configurations, form creation options, and other features, simply delve into our %1$s documentation %2$s .', 'easy-form-builder'),
            'mlntip' => __('If your emails are not being delivered, try using an SMTP plugin. For more information, %1$s click here %2$s or contact our %3$s support team %4$s.', 'easy-form-builder'),
            'msgnml' => __('To explore the full functionality and settings of Easy Form Builder, including email configurations, form creation options, and other features, simply delve into our %1$s documentation %2$s .', 'easy-form-builder'),
            'serverEmailAble' => __('Email Server Status', 'easy-form-builder'),
            'vmgs' => __('View Messages', 'easy-form-builder'),
            'getProVersion' => __('Activate Pro version', 'easy-form-builder'),
            'sentBy' => __('Sent by:', 'easy-form-builder'),
            'hiUser' => __('Hello!', 'easy-form-builder'),
            'trackingCode' => __('Confirmation Code', 'easy-form-builder'),
            'newMessage' => __('New Message', 'easy-form-builder'),
            'createdBy' => __('Created by', 'easy-form-builder'),
            'newMessageReceived' => __('New message received', 'easy-form-builder'),
            'goodJob' => __('Good Job', 'easy-form-builder'),
            'yFreeVEnPro' => __('You are using the free version. Upgrade to Pro for just %1$s%2$s%3$s/year and unlock advanced features to improve your experience and productivity.%4$sView Pro Features%5$s', 'easy-form-builder'),
            'WeRecivedUrM' => __('We received your message', 'easy-form-builder'),
        ];

        return $fallbacks[$key] ?? $key;
    }

    /**
     * Get visitor IP address
     *
     * @return string IP address
     */
    public function get_ip_address() {
        $ip = '1.1.1.1';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        $ip = strval($ip);
        $check = strpos($ip, ',');
        if ($check !== false) {
            $ip = substr($ip, 0, $check);
        }
        return $ip;
    }

    /**
     * Get visitor operating system
     *
     * @return string OS name
     */
    public function getVisitorOS() {
        $_HTTP_USER_AGENT = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : null;
        $ua = strtolower($_HTTP_USER_AGENT);
        $os = "Unknown";

        if ($ua) {
            if (strpos($ua, 'windows') !== false) {
                $os = "Windows";
            } elseif (strpos($ua, 'linux') !== false) {
                $os = "Linux";
            } elseif (strpos($ua, 'macintosh') !== false || strpos($ua, 'mac os x') !== false) {
                $os = "Mac";
            } elseif (strpos($ua, 'android') !== false) {
                $os = "Android";
            } elseif (strpos($ua, 'ios') !== false) {
                $os = "iOS";
            }
        }

        return $os;
    }

    /**
     * Get visitor browser
     *
     * @return string Browser name
     */
    public function getVisitorBrowser() {
        $_HTTP_USER_AGENT = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : null;
        $ua = strtolower($_HTTP_USER_AGENT);
        $b = "Unknown";

        if ($ua) {
            if (strpos($ua, 'firefox') !== false) {
                $b = "Mozilla Firefox";
            } elseif (strpos($ua, 'chrome') !== false) {
                if (strpos($ua, 'edg') !== false) {
                    $b = "Microsoft Edge";
                } elseif (strpos($ua, 'brave') !== false) {
                    $b = "Brave";
                } else {
                    $b = "Google Chrome";
                }
            } elseif (strpos($ua, 'safari') !== false) {
                $b = "Apple Safari";
            } elseif (strpos($ua, 'opera') !== false) {
                $b = "Opera";
            } elseif (strpos($ua, 'msie') !== false || strpos($ua, 'trident') !== false) {
                $b = "Internet Explorer";
            }
        }

        return $b;
    }

    /**
     * Send email with state and template
     *
     * @param string|array $to Recipient(s)
     * @param string|array $sub Subject(s)
     * @param string|array $cont Content
     * @param mixed $pro Pro version flag
     * @param string $state Email state
     * @param string $link Link for email
     * @param string $st Settings (optional)
     * @return mixed Email send result
     */
    public function send_email_state_new($to, $sub, $cont, $pro, $state, $link, $st = "null") {
        error_log('===== EmsfbEmailHandler::send_email_state_new START =====');
        error_log('EmailHandler - to: ' . json_encode($to));
        error_log('EmailHandler - sub: ' . json_encode($sub));
        error_log('EmailHandler - state: ' . json_encode($state));

        $email_content_type = isset($state[2]) ? $state[2] : 'traking_link';
        error_log('EmailHandler - email_content_type: ' . $email_content_type);
        error_log('EmailHandler - content: ' . json_encode($cont));

        // Set email content type to HTML
        add_filter('wp_mail_content_type', [$this, 'wpdocs_set_html_mail_content_type']);

        $mailResult = "n";
        $server_name = apply_filters('emsfb_get_server_host', 'yourdomain.com');
        $from = get_bloginfo('name') . " <no-reply@" . $server_name . ">";
        error_log('EmailHandler - default sender: ' . $from);

        // Handle $from setup based on $to type and email validation
        if (is_array($to) && isset($to[2]) && is_email($to[2])) {
            $fromEmail = is_array($to[2]) ? array_pop($to[2]) : $to[2];
            $from = get_bloginfo('name') . " <" . $fromEmail . ">";
            error_log('EmailHandler - sender updated from array: ' . $from);
            unset($to[2]);
        } elseif (is_object($to) && isset($to[2]) && is_email($to[2])) {
            $from = get_bloginfo('name') . " <" . $to[2] . ">";
            error_log('EmailHandler - sender updated from object: ' . $from);
            unset($to[2]);
        }
        error_log('EmailHandler - final sender: ' . $from);

        $headers = [
            'MIME-Version: 1.0\r\n',
            'From:' . $from,
        ];

        // Add wp_mail error logging
        add_action('wp_mail_failed', function($wp_error) {
            error_log('===== WP_MAIL FAILED =====');
            error_log('wp_mail error: ' . $wp_error->get_error_message());
        });

        // Internal function for sending emails
        $sendMail = function($to, $sub, $message, $headers) {
            error_log('======= EMAIL DETAILS =======');
            error_log('EmailHandler - Recipients: ' . json_encode($to));
            error_log('EmailHandler - Subject: ' . $sub);
            error_log('EmailHandler - Headers: ' . json_encode($headers));
            error_log('EmailHandler - Message length: ' . strlen($message) . ' chars');
            error_log('EmailHandler - Message preview: ' . substr(strip_tags($message), 0, 200) . '...');
            error_log('============================');

            if (is_string($to)) {
                $result = wp_mail($to, $sub, $message, $headers);
                error_log('EmailHandler - wp_mail result for ' . $to . ': ' . ($result ? 'SUCCESS' : 'FAILED'));
                if (!$result) {
                    error_log('EmailHandler - wp_mail FAILED for: ' . $to);
                    // Try alternative method
                    $alt_result = mail($to, $sub, $message, implode("\r\n", $headers));
                    error_log('EmailHandler - PHP mail() result: ' . json_encode($alt_result));
                }
                return $result;
            } else {
                $to = array_filter(array_unique($to));
                $success = true;
                foreach ($to as $email) {
                    if (is_email($email)) {
                        $result = wp_mail($email, $sub, $message, $headers);
                        error_log('EmailHandler - wp_mail result for ' . $email . ': ' . ($result ? 'SUCCESS' : 'FAILED'));
                        if (!$result) {
                            $success = false;
                            error_log('EmailHandler - wp_mail FAILED for: ' . $email);
                        }
                    } else {
                        error_log('EmailHandler - Invalid email address: ' . $email);
                    }
                }
                return $success;
            }
        };

        // Handle single email sending
        if (is_string($sub)) {
            error_log('EmailHandler - Single email mode');
            $message = $this->email_template_efb($pro, $state, $cont, $link, $email_content_type, $st);
            error_log('EmailHandler - Generated message for single email: ' . substr(strip_tags($message), 0, 300) . '...');
            if ($state != "reportProblem") {
                $mailResult = $sendMail($to, $sub, $message, $headers);
            }

            // Handle special support emails
            if (in_array($state, ["reportProblem", "testMailServer", "addonsDlProblem"])) {
                $message = $this->email_template_efb($pro, $state, $cont, $link, $email_content_type, $st);
                $mailResult = $sendMail($to, $sub, $message, $headers);
            }
        } else {
            // Handle multiple emails
            for ($i = 0; $i < 2; $i++) {
                if (!empty($to[$i]) && $to[$i] != "null") {
                    error_log('EmailHandler - Multiple email mode [' . $i . ']');
                    $message = $this->email_template_efb($pro, $state[$i], $cont[$i], $link[$i], $email_content_type, $st);
                    error_log('EmailHandler - Generated message for email [' . $i . ']: ' . substr(strip_tags($message), 0, 300) . '...');
                    if ($state != "reportProblem") {
                        $mailResult = $sendMail($to[$i], $sub[$i], $message, $headers);
                    }
                }
            }
        }

        // Remove email content type filter
        remove_filter('wp_mail_content_type', [$this, 'wpdocs_set_html_mail_content_type']);

        error_log('EmailHandler - Final result: ' . json_encode($mailResult));
        return $mailResult;
    }

    /**
     * Generate email template with proper translations
     *
     * @param mixed $pro Pro version flag
     * @param string $state Email state
     * @param mixed $m Message content
     * @param string $link Link for email
     * @param string $email_content_type Content type
     * @param string $st Settings
     * @return string Generated HTML email template
     */
    public function email_template_efb($pro, $state, $m, $link, $email_content_type, $st = "null") {
        error_log('===== EmsfbEmailHandler::email_template_efb START =====');
        error_log('EmailHandler Template - state: ' . json_encode($state));
        error_log('EmailHandler Template - message content: ' . json_encode($m));
        error_log('EmailHandler Template - link: ' . $link);
        error_log('EmailHandler Template - content type: ' . $email_content_type);

        // Website locale and URL setup
        $l = 'https://whitestudio.team';
        $wp_lan = get_locale();
        $locale_map = [
            'fa_IR' => 'https://easyformbuilder.ir',
            'ar' => 'https://ar.whitestudio.team',
            'arq' => 'https://ar.whitestudio.team',
            'de_DE' => 'https://de.whitestudio.team'
        ];
        $l = $locale_map[$wp_lan] ?? $l;

        // Get translations efficiently
        $text_keys = ['msgdml', 'mlntip', 'msgnml', 'serverEmailAble', 'vmgs', 'getProVersion', 'sentBy', 'hiUser', 'trackingCode', 'newMessage', 'createdBy', 'newMessageReceived', 'goodJob', 'yFreeVEnPro', 'WeRecivedUrM'];
        $lang = $this->get_text_efb($text_keys);

        // Email disclaimer
        $automatic_email_disclaimer = '📧 ' . __('This email was sent automatically. Please do not reply.', 'easy-form-builder');

        $footer = "<a class='efb subtle-link' target='_blank' href='" . home_url() . "'>" . $lang['sentBy'] . " " . get_bloginfo('name') . "</a>";
        $align = is_rtl() ? 'right' : 'left';
        $d = is_rtl() ? 'rtl' : 'ltr';

        // Get settings
        if ($st == 'null') {
            $st = $this->get_settings_efficiently();
        }
        if ($st == "null") return;

        // Pro version footer handling
        if ($pro == true || $pro == 1) {
            $is_pro = (int) get_option('Emsfb_pro', 2);
            if ($is_pro == 3) {
                $footer = "<div style='text-align:center;'>
                    " . $footer . "<br>
                    <p>" . sprintf(
                        __('Built with %1$sEasy Form Builder%2$s by %3$sWhiteStudio.team%4$s', 'easy-form-builder'),
                        "<a href='https://wordpress.org/plugins/easy-form-builder/' target='_blank' class='subtle-link' style='color:#888;text-decoration:none;'>",
                        "</a>",
                        "<a href='https://whitestudio.team' target='_blank' class='subtle-link' style='color:#888;text-decoration:none;'>",
                        "</a>"
                    ) . "</p>
                </div>";
            }
        }

        $temp = isset($st->emailTemp) && strlen($st->emailTemp) > 10 ? $st->emailTemp : "0";

        $title = $lang['newMessage'];
        $message = is_string($m) ? "<h3>$m</h3>" : "<h3>{$m[0]}</h3>";
        $blogName = get_bloginfo('name');
        $user = function_exists("get_user_by") ? get_user_by('id', 1) : false;
        $adminEmail = $user ? $user->user_email : '';
        $blogURL = home_url();

        $track_id = '';
        if (gettype($m) == 'string') {
            $track_id = $m;
        } else {
            $track_id = $m[0];
        }

        // Create responsive email button
        $button_style = "display: inline-block; padding: 16px 32px; background: transparent; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 18px; line-height: 1; text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif; border: none; cursor: pointer;";
        error_log('email content type: ' . $email_content_type);
        error_log('contet message: ' . json_encode($m));
        // message_link
        if($email_content_type == 'message_link'){

        }
        // For newUser/register states, no tracking section needed - the verification link is in the content
        $isRegistrationState = in_array($state, ['newUser', 'register']);
        $tracking_section = ($email_content_type == 'just_message' || $isRegistrationState) ? "" : "
            <div style='text-align:center; margin: 30px 0;'>
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' style='margin: 0 auto;'>
                    <tr>
                        <td style='background: linear-gradient(135deg, #202a8d 0%, #1e3a8a 100%); border-radius: 8px; text-align: center; box-shadow: 0 4px 15px rgba(32, 42, 141, 0.3);'>
                            <a href='" . $link . "' target='_blank' style='" . $button_style . "'>
                                " . $lang['vmgs'] . "
                            </a>
                        </td>
                    </tr>
                </table>
            </div>
        ";

        // Set appropriate title based on state
        if ($isRegistrationState) {
            $title = __('Welcome!', 'easy-form-builder');
        }

        // Handle different email states based on content type
        if ($state == "testMailServer") {
            $title = $lang['serverEmailAble'];
            $message = $this->generate_test_server_message($lang, $l, $wp_lan);
        } else {
            // Use email_content_type to determine message structure
            error_log('EmailHandler - Using email_content_type: ' . $email_content_type . ' for state: ' . $state);
            switch ($email_content_type) {
                case 'message_link':
                    // message_link: فرم پر شده + لینک رهگیری
                    error_log('EmailHandler - Generating message_link content');
                    $message = $this->generate_message_link_content($m, $lang, $link, $tracking_section, $state);
                    break;

                case 'just_message':
                    // just_message: فقط فرم پر شده (بدون لینک)
                    error_log('EmailHandler - Generating just_message content');
                    $message = $this->generate_just_message_content($m, $lang, $align);
                    break;

                case 'traking_link':
                default:
                    // traking_link: تأیید پیام + لینک (بدون جزئیات فرم)
                    error_log('EmailHandler - Generating traking_link content');
                    $message = $this->generate_tracking_link_content($m, $lang, $link, $tracking_section, $state);
                    break;
            }
        }

        // Generate final HTML email
        $html_email = $this->generate_html_email_template($title, $message, $footer, $automatic_email_disclaimer, $d, $align);

        // Handle custom templates
        if ($temp != "0") {
            $html_email = $this->apply_custom_template($temp, $message, $title, $blogName, $blogURL, $adminEmail, $footer, $automatic_email_disclaimer);
        }

        error_log('EmailHandler Template - Final output:\\n ' . $html_email);
        error_log('EmailHandler Template - Preview: ' . substr(strip_tags($html_email), 0, 200) . '...');
        error_log('===== EmsfbEmailHandler::email_template_efb END =====');

        return $html_email;
    }

    /**
     * Generate test server message
     */
    private function generate_test_server_message($lang, $l, $wp_lan) {
        $dt = $lang['msgnml'];
        $de = preg_replace('/^[^.]*\. /', '', $lang['mlntip']);
        $link = "$l/document/send-email-using-smtp-plugin/";
        if ($wp_lan == "fa_IR") $link = "$l/داکیومنت/ارسال-ایمیل-بوسیله-افزونه-smtp/";

        $de = strtr($de, [
            '%1$s' => "<a href='$link' target='_blank' style='color: #202a8d; text-decoration: none; font-weight: 600;'>",
            '%2$s' => "</a>",
            '%3$s' => "<a href='$l/support/' target='_blank' style='color: #202a8d; text-decoration: none; font-weight: 600;'>",
            '%4$s' => "</a>"
        ]);

        return "
            <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0; border-collapse: collapse;'>
                <tr>
                    <td style='background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 25px; border-left: 5px solid #0ea5e9; border-radius: 12px; text-align: center;'>
                        <h2 style='color: #0c4a6e; margin: 0 0 15px 0; font-size: 24px; font-weight: 700;'>
                            ✅ " . __('Congratulations! Email System Working', 'easy-form-builder') . "
                        </h2>
                        <p style='color: #075985; font-size: 16px; line-height: 1.6; margin: 0;'>
                            " . __('Your server has successfully sent this test email. The email delivery system is properly configured and functioning.', 'easy-form-builder') . "
                        </p>
                    </td>
                </tr>
            </table>
            <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0; border-collapse: collapse;'>
                <tr>
                    <td style='background-color: #fefce8; padding: 20px; border-left: 4px solid #eab308; border-radius: 10px;'>
                        <h3 style='color: #a16207; margin: 0 0 12px 0; font-size: 18px; font-weight: 600;'>
                            💡 " . __('Email Delivery Tips', 'easy-form-builder') . "
                        </h3>
                        <div style='color: #713f12; font-size: 14px; line-height: 1.6;'>
                            $de
                        </div>
                    </td>
                </tr>
            </table>";
    }

    /**
     * Generate new message content
     */
    private function generate_new_message_content($m, $lang, $link, $tracking_section) {
        if (gettype($m) == 'string') {
            if (strpos($m, '<h2>') !== false || strpos($m, '<div') !== false) {
                return $m;
            } else {
                $link = strpos($link, "?") == true ? $link . '&track=' . $m : $link . '?track=' . $m;
                return "<h2 style='text-align:center'>" . $lang["newMessageReceived"] . "</h2>
                <p style='text-align:center'>" . $lang["trackingCode"] . ": " . $m . " </p>" . $tracking_section;
            }
        } else {
            $link = strpos($link, "?") == true ? $link . '&track=' . $m[0] : $link . '?track=' . $m[0];
            return "<div style='text-align:center;color:#252526;font-size:14px;background: #f9f9f9;padding: 10px;margin: 20px 5px;'>" . $m[1] . " </div>" . $tracking_section;
        }
    }

    /**
     * Generate default message content
     */
    private function generate_default_message_content($m, $lang, $link, $tracking_section, $align) {
        if (is_string($m)) {
            if (strpos($m, '<h2>') !== false || strpos($m, '<div') !== false) {
                return $m;
            } else {
                $track_id = $m;
                return "
                    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                        <tr>
                            <td style='text-align: center; padding: 20px;'>
                                <h2>" . $lang["WeRecivedUrM"] . "</h2>
                                <p>" . $lang["trackingCode"] . ": " . $track_id . " </p>
                                " . $tracking_section . "
                            </td>
                        </tr>
                    </table>";
            }
        } elseif (is_array($m) && count($m) >= 2) {
            $track_id = $m[0];
            $content = $m[1];

            if (strpos($content, '<') !== false && strpos($content, '>') !== false) {
                return "
                    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                        <tr>
                            <td style='text-align: center; padding: 20px;'>
                                <h2>" . $lang["WeRecivedUrM"] . "</h2>
                                <div style='text-align:" . $align . ";color:#252526;font-size:14px;'>" . $content . " </div>
                                " . $tracking_section . "
                            </td>
                        </tr>
                    </table>";
            } else {
                return "
                    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                        <tr>
                            <td style='text-align: center; padding: 20px;'>
                                <h2>" . $lang["WeRecivedUrM"] . "</h2>
                                <div style='text-align:" . $align . ";color:#252526;font-size:14px;background:#f9f9f9;padding:10px;margin:20px 5px;border-radius:8px;'>" . $content . " </div>
                                " . $tracking_section . "
                            </td>
                        </tr>
                    </table>";
            }
        }

        return "";
    }

    /**
     * Generate message_link content: فرم پر شده + لینک رهگیری
     */
    private function generate_message_link_content($m, $lang, $link, $tracking_section, $state) {
        error_log('EmailHandler - generate_message_link_content called with m: ' . json_encode($m) . ', state: ' . $state);
        if (is_string($m)) {
            // فقط کد رهگیری داریم
            if (strpos($m, '<h2>') !== false || strpos($m, '<div') !== false) {
                return $m;
            } else {
                $track_id = $m;
                $title = ($state == "newMessage") ? $lang["newMessageReceived"] : $lang["WeRecivedUrM"];
                return "<h2 style='text-align:center'>" . $title . "</h2>
                <p style='text-align:center'>" . $lang["trackingCode"] . ": " . $track_id . " </p>" . $tracking_section;
            }
        } elseif (is_array($m) && count($m) >= 2) {
            // فرم پر شده + کد رهگیری داریم
            $track_id = $m[0];
            $form_content = $m[1];
            $title = ($state == "newMessage") ? $lang["newMessageReceived"] : $lang["WeRecivedUrM"];

            return "
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                    <tr>
                        <td style='text-align: center; padding: 20px;'>
                            <h2>" . $title . "</h2>
                            <p style='margin: 10px 0; color: #666;'>" . $lang["trackingCode"] . ": <strong>" . $track_id . "</strong></p>
                            <div style='text-align:left;color:#252526;font-size:14px;background:#f8f9fa;padding:15px;margin:20px 0;border-radius:8px;border:1px solid #e9ecef;'>" . $form_content . " </div>
                            " . $tracking_section . "
                        </td>
                    </tr>
                </table>";
        }

        return "";
    }

    /**
     * Generate just_message content: فقط فرم پر شده (بدون لینک)
     */
    private function generate_just_message_content($m, $lang, $align) {
        if (is_string($m)) {
            if (strpos($m, '<h2>') !== false || strpos($m, '<div') !== false) {
                return $m;
            } else {
                return "<h2 style='text-align:center'>" . $lang["WeRecivedUrM"] . "</h2>
                <p style='text-align:center;color:#666;'>" . __('Form submitted successfully without tracking.', 'easy-form-builder') . "</p>";
            }
        } elseif (is_array($m) && count($m) >= 2) {
            $form_content = $m[1];

            return "
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                    <tr>
                        <td style='text-align: center; padding: 20px;'>
                            <h2>" . $lang["WeRecivedUrM"] . "</h2>
                            <div style='text-align:" . $align . ";color:#252526;font-size:14px;background:#f8f9fa;padding:15px;margin:20px 0;border-radius:8px;border:1px solid #e9ecef;'>" . $form_content . "</div>
                        </td>
                    </tr>
                </table>";
        }

        return "";
    }

    /**
     * Generate traking_link content: تأیید پیام + لینک (بدون جزئیات فرم)
     */
    private function generate_tracking_link_content($m, $lang, $link, $tracking_section, $state) {
        // For newUser/register states, the message already contains the verification link
        // Just clean up &quot; entities and return the content without additional tracking section
        $isRegistrationState = in_array($state, ['newUser', 'register']);

        if (is_string($m)) {
            // Clean &quot; entities from content for all states
            $m = str_replace(['&quot;', '&amp;quot;'], '', $m);

            // If content already has formatted HTML structure, return it
            if (strpos($m, '<h2>') !== false || strpos($m, '<div') !== false || strpos($m, '<p>') !== false) {
                // For registration states, don't add tracking section (verification link is in content)
                return $isRegistrationState ? $m : ($m . $tracking_section);
            } else {
                // Plain text tracking code - format it properly
                $track_id = $m;
                $title = ($state == "newMessage") ? $lang["newMessageReceived"] : $lang["WeRecivedUrM"];
                return "<h2 style='text-align:center'>" . $title . "</h2>
                <p style='text-align:center'>" . $lang["trackingCode"] . ": " . $track_id . " </p>" . $tracking_section;
            }
        } elseif (is_array($m) && count($m) >= 2) {
            // Array format: [tracking_code, content]
            $track_id = $m[0];
            $content = str_replace(['&quot;', '&amp;quot;'], '', $m[1]);
            $title = ($state == "newMessage") ? $lang["newMessageReceived"] : $lang["WeRecivedUrM"];

            // For registration states with array content
            if ($isRegistrationState && (strpos($content, '<') !== false)) {
                return $content; // Return content as-is without tracking section
            }

            return "
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                    <tr>
                        <td style='text-align: center; padding: 20px;'>
                            <h2>" . $title . "</h2>
                            <p style='margin: 10px 0; color: #666;'>" . $lang["trackingCode"] . ": <strong>" . $track_id . "</strong></p>
                            <p style='color: #28a745; font-weight: 600;'>✅ " . __('Your message has been received and recorded successfully.', 'easy-form-builder') . "</p>
                            " . $tracking_section . "
                        </td>
                    </tr>
                </table>";
        }

        return "";
    }

    /**
     * Generate complete HTML email template
     */
    private function generate_html_email_template($title, $message, $footer, $disclaimer, $direction, $align) {
        return "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\" xmlns:v=\"urn:schemas-microsoft-com:vml\" xmlns:o=\"urn:schemas-microsoft-com:office:office\">
<head>
    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />
    <title>$title</title>
    <style type=\"text/css\">
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        table { border-collapse: collapse !important; }
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; margin: 0 !important; }
            .content-wrapper { padding: 15px !important; }
            .header-image { width: 80% !important; max-width: 200px !important; }
            .message-content { padding: 20px 15px !important; font-size: 16px !important; }
            .footer-content { padding: 20px 15px !important; }
        }
    </style>
</head>
<body style=\"margin: 0; padding: 0; width: 100%; background-color: #f8f9fa; direction: $direction; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif;\">
    <table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\" style=\"margin: 0; padding: 0; background-color: #f8f9fa;\">
        <tr>
            <td align=\"center\" style=\"padding: 20px 0;\">
                <table class=\"email-container\" role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"600\" style=\"margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;\">
                    <tr>
                        <td align=\"center\" style=\"padding: 40px 30px 30px 30px; background: linear-gradient(135deg, #667eea 0%, #202a8d 100%);\">
                            <table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\">
                                <tr>
                                    <td align=\"center\">
                                        <img src=\"" . (defined('EMSFB_PLUGIN_URL') ? EMSFB_PLUGIN_URL : '') . "public/assets/images/email_template1.png\" alt=\"Easy Form Builder\" class=\"header-image\" style=\"width: 120px; height: auto; display: block; margin: 0 auto 20px auto; border: none;\" />
                                    </td>
                                </tr>
                                <tr>
                                    <td align=\"center\">
                                        <h1 style=\"margin: 0; padding: 0; color: #ffffff; font-size: 28px; font-weight: 600; line-height: 1.3; text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif;\">$title</h1>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class=\"content-wrapper\" style=\"padding: 40px 30px;\">
                            <table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\">
                                <tr>
                                    <td class=\"message-content\" align=\"center\" style=\"color: #333333; font-size: 16px; line-height: 1.6; text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif;\">
                                        $message
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style=\"height: 20px; background-color: #ffffff;\"></td>
                    </tr>
                </table>
                <table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"600\" style=\"margin: 20px auto 0 auto;\">
                    <tr>
                        <td class=\"footer-content\" align=\"center\" style=\"padding: 30px; color: #6b7280; font-size: 14px; line-height: 1.5; text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif;\">
                            $footer
                        </td>
                    </tr>
                    <tr>
                        <td align=\"center\" style=\"padding-bottom: 20px;\">
                            <table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                                <tr>
                                    <td style=\"border-radius: 20px; background-color: #f1f5f9; padding: 15px 25px;\">
                                        <p style=\"margin: 0; color: #64748b; font-size: 12px; text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif;\">
                                            $disclaimer
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
    }

    /**
     * Apply custom email template
     *
     * Handles both legacy templates (old textarea input) and builder templates
     * (drag-drop email builder with efb-email-container class).
     */
    private function apply_custom_template($temp, $message, $title, $blogName, $blogURL, $adminEmail, $footer, $disclaimer) {
        $replacements = [
            'shortcode_message' => $message,
            'shortcode_title' => $title,
            'shortcode_website_name' => $blogName,
            'shortcode_website_url' => $blogURL,
            'shortcode_admin_email' => $adminEmail
        ];

        // Strip builder data comment before processing (not needed in sent emails)
        $temp = preg_replace('/\n?<!-- EFBDATA:.*? -->/', '', $temp);

        // Replace shortcodes with actual values
        $temp = strtr($temp, $replacements);

        // Decode @efb@ URL encoding — each @efb@ represents exactly one /
        // Protocol fix: wp_kses may normalize http:// to http:/ during save,
        // so the DB may store http:@efb@ (one token) instead of http:@efb@@efb@ (two).
        // (?:@efb@)+ matches one OR more complete @efb@ groups and restores ://
        // The old regex @efb@+ was wrong because + applied to just the last @,
        // eating into the next @efb@ token on two-token cases.
        $temp = preg_replace(['/http:(?:@efb@)+/', '/https:(?:@efb@)+/'], ['http://', 'https://'], $temp);
        $temp = str_replace('@efb@', '/', $temp);

        // Detect builder template (contains efb-email-container class from the drag-drop builder)
        $isBuilderTemplate = (strpos($temp, 'efb-email-container') !== false);

        if ($isBuilderTemplate) {
            // Builder templates are saved through wp_kses which strips the HTML document
            // envelope (<!DOCTYPE>, <html>, <head>, <style>, <body>) and HTML comments
            // (MSO conditionals). Reconstruct the document for correct email rendering.
            if (stripos($temp, '<!DOCTYPE') === false && stripos($temp, '<html') === false) {
                $temp = $this->wrap_builder_template_html($temp);
            }
            // Builder templates include their own footer blocks — no extra injection needed.
        } else {
            // Legacy template — inject footer + disclaimer before </body>
            $p = strripos($temp, '</body>');
            $custom_footer = "
        <table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"600\" style=\"margin: 20px auto 0 auto;\">
            <tr>
                <td align=\"center\" style=\"padding: 30px; color: #6b7280; font-size: 14px; line-height: 1.5; text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif; background-color: #f8f9fa; border-radius: 8px;\">
                    $footer
                </td>
            </tr>
            <tr>
                <td align=\"center\" style=\"padding: 15px 30px;\">
                    <table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                        <tr>
                            <td style=\"border-radius: 20px; background-color: #f1f5f9; padding: 12px 20px;\">
                                <p style=\"margin: 0; color: #64748b; font-size: 11px; text-align: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif;\">
                                    $disclaimer
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
            ";

            if ($p !== false) {
                $temp = substr_replace($temp, $custom_footer, $p, 0);
            }
        }

        return $temp;
    }

    /**
     * Wrap builder template content in a full HTML email document.
     *
     * The drag-drop email builder generates complete HTML documents, but wp_kses
     * strips document-level tags (DOCTYPE, html, head, style, body) and HTML
     * comments (MSO conditionals) during save sanitization. This method
     * reconstructs the document envelope with responsive CSS and MSO fallbacks.
     *
     * @param string $content The sanitized builder template content (bare tables)
     * @return string Complete HTML email document
     */
    private function wrap_builder_template_html($content) {
        // Direction from WordPress locale
        $direction = is_rtl() ? 'rtl' : 'ltr';

        // ── Clean up orphaned content from wp_kses stripping ──
        // Must happen BEFORE value extraction — orphaned CSS text contains selectors
        // like .efb-email-wrapper { max-width: 100% } that would confuse the regex
        // into extracting 100 instead of 600.

        // 1. Strip orphaned <meta> tags that were in <head> and now float in content
        $content = preg_replace('/<meta\s[^>]*\/?>/i', '', $content);

        // 2. Strip orphaned CSS text — when wp_kses strips <style> tags, the CSS
        //    rules inside become raw text before the first <table>. Remove everything
        //    before the first <table that isn't an HTML tag.
        $firstTable = strpos($content, '<table');
        if ($firstTable === false) {
            $firstTable = strpos($content, '<div');
        }
        if ($firstTable !== false && $firstTable > 0) {
            $content = substr($content, $firstTable);
        }

        // 3. Strip escaped MSO conditional comments — wp_kses encodes > and < inside
        //    HTML comments as &gt; and &lt;, making them broken:
        //    <!--[if mso]&gt;...&lt;![endif]-->
        $content = preg_replace('/<!--\[if\s+mso\]&gt;.*?&lt;!\[endif\]-->/is', '', $content);

        // 4. Clean trailing whitespace/newlines
        $content = trim($content);

        // ── Extract values from cleaned content ──

        // Background color from the outer table (page background)
        $bgColor = '#f8f9fa';
        if (preg_match("/background-color:\s*([^;'\"]+)/i", $content, $bgMatch)) {
            $bgColor = trim($bgMatch[1]);
        }

        // Content width from efb-email-wrapper max-width
        $contentWidth = 600;
        if (preg_match("/efb-email-wrapper[^>]*max-width:\s*(\d+)/i", $content, $wMatch)) {
            $contentWidth = intval($wMatch[1]);
        }

        // ── Regenerate MSO conditional comments ──
        $mso_width = intval($contentWidth);
        $content = preg_replace(
            '/(<div\s[^>]*efb-email-wrapper[^>]*>)/i',
            '<!--[if mso]><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="' . $mso_width . '" align="center"><tr><td><![endif]-->' . "\n$1",
            $content,
            1
        );
        // Close MSO after the wrapper: </table>(efb-email-container) </div>(efb-email-wrapper) </td>(outer)
        $content = preg_replace(
            '#(</table>\s*</div>)(\s*</td>)#i',
            "$1\n<!--[if mso]></td></tr></table><![endif]-->$2",
            $content,
            1
        );

        $safe_bg = esc_attr($bgColor);
        $safe_dir = esc_attr($direction);

        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
            <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <style type="text/css">
            body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
            table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
            img { -ms-interpolation-mode: bicubic; border: 0; }
            body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            @media only screen and (max-width: 600px) {
            .efb-email-wrapper { max-width: 100% !important; width: 100% !important; }
            .efb-email-container { width: 100% !important; }
            .efb-email-container td { padding-left: 15px !important; padding-right: 15px !important; }
            img { max-width: 100% !important; height: auto !important; }
            }
            </style>
            </head>
            <body style="margin: 0; padding: 0; width: 100%; background-color: ' . $safe_bg . '; direction: ' . $safe_dir . '; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, Arial, sans-serif;">
            ' . $content . '
            </body>
            </html>';
    }


    public function send_email_noti_sid_plugins_efb($status){
		$all_plugins = get_plugins();
		$msg = esc_html__('This is an alert message regarding a SID validation error. This issue may have occurred due to a plugin conflict or an unauthorized attempt to access the website.', 'easy-form-builder') . '<br>';
        $msg .= esc_html__('If you receive this email multiple times, it could indicate a recurring issue.', 'easy-form-builder') ;
		$msg .= '<a href="'.EMSFB_SERVER_URL.'/support" target="_blank">'.esc_html__('Please contact our support team for assistance.', 'easy-form-builder') . '</a><br>';
		$msg .= esc_html__('One or more of the plugins listed below—typically related to caching or security—might be triggering this issue. For troubleshooting, temporarily deactivate them and test your site.', 'easy-form-builder') . '<br>';


		$str =   '<!--efb-->';
		$str .= 'Error code:'.$status . '<br>';
		// 'this is a test message for sid validation error because of plugin conflict or user try to attack the website<br>';
        $str .=  $msg . '<br><hr>';
		$str .= 'IP:'.$this->get_ip_address() . '<br>';
		$str .= 'OS:'.$this->getVisitorOS() . '<br>';
		$str .= 'Browser:'.$this->getVisitorBrowser() . '<br>';
		$str .= 'User ID:'.get_current_user_id() . '<br>';
		$_http_user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
		$str .= 'User Agent:'.$_http_user_agent . '<br>';
		$_http_referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
		$str .= 'Referer:'.$_http_referer . '<br>';
		$_request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
		$str .= 'Request URI:'.$_request_uri . '<br>';
		$str .= 'Date:'. wp_date('Y-m-d H:i:s') . '<br>';
		$str .= '<hr>Value:'.$status . '<br>';
		$str .= 'State:'.$status . '<br>';
		$str .= 'PHP Version: ' . phpversion() . '<br>';
		$str .= 'WordPress Version: ' . get_bloginfo('version') . '<br>';
		$str .= 'Easy Form Builder Version' . EMSFB_PLUGIN_VERSION . '<br>';
		$str .= 'Website URL: ' . get_site_url() . '<br>';
		$div = '<div style="width: 100%; height: 1px; background-color: #ccc; margin: 5px 10;"></div>';
		foreach ($all_plugins as $plugin_file => $plugin_data) {
			$div.= 'Plugin Name: ' . $plugin_data['Name'] . '<br>';

			$div .= 'Plugin URI: ' . $plugin_data['PluginURI'] . '<br>';
			$div .= 'Version: ' . $plugin_data['Version'] . '<br>';
			$div .= 'Description: ' . $plugin_data['Description'] . '<br><hr>';
			$div .= '</div>';

		}
		error_log('EFB=>plugin_data[name] : ' . $div );
		$str .= $div;
		$subject = esc_html__('Easy Form Builder', 'easy-form-builder') . ':' . esc_html__('SID Validation Error', 'easy-form-builder') . ' - ' . get_bloginfo('name');
		$to = [];
		$to[] = get_option('admin_email');
		$settings = get_setting_Emsfb('decoded');
		if($settings->emailSupporter != null && $settings->emailSupporter != 'null' && $settings->emailSupporter != ''){
			$to[] = $settings->emailSupporter;
		}
		$to[]= 'no-reply@whitestudio.team';
		if(isset($settings->smtp) && (bool)$settings->smtp ) $this->send_email_state_new($to, $subject, $str, 0, "sid_noti_validation", 'null', 'null');



	}

    /**
     * Get settings efficiently
     */
    private function get_settings_efficiently() {
        if (class_exists('Emsfb') && method_exists('Emsfb', 'get_setting_Emsfb')) {
            return Emsfb::get_setting_Emsfb();
        }

        // Fallback to global function
        if (function_exists('get_setting_Emsfb')) {
            return get_setting_Emsfb();
        }

        return null;
    }

    /**
     * Set HTML content type for emails
     *
     * @return string
     */
    public function wpdocs_set_html_mail_content_type() {
        return 'text/html';
    }

    /**
     * Static factory method for quick access
     *
     * @return EmsfbEmailHandler
     */
    public static function getInstance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Quick send method with minimal parameters
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $message Message content
     * @param string $state Email state (optional)
     * @return bool Send result
     */
    public static function quickSend($to, $subject, $message, $state = 'newMessage') {
        $handler = self::getInstance();
        $link = home_url();
        return $handler->send_email_state_new($to, $subject, $message, false, $state, $link);
    }
}
