<?php

defined('ABSPATH') || exit;

class EmsfbEmailHandler {

    private $efb_instance;

    private static $efb_function = null;

    private static $text_cache = [];

    public function __construct($efb_instance = null) {
        $this->efb_instance = $efb_instance;
    }

    private function get_text_efb($text_keys) {
        $cache_key = md5(serialize($text_keys));

        if (isset(self::$text_cache[$cache_key])) {
            return self::$text_cache[$cache_key];
        }

        if (self::$efb_function === null) {
            if (class_exists('Emsfb') && method_exists('Emsfb', 'get_efbFunction')) {
                self::$efb_function = Emsfb::get_efbFunction();
            } else {

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

        $fallback = [];
        foreach ($text_keys as $key) {
            $fallback[$key] = $this->get_fallback_text($key);
        }

        return count($text_keys) === 1 ? $fallback[array_keys($fallback)[0]] : $fallback;
    }

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

    public function send_email_state_new($to, $sub, $cont, $pro, $state, $link, $st = "null") {

        $email_content_type = isset($state[2]) ? $state[2] : 'traking_link';

        add_filter('wp_mail_content_type', [$this, 'wpdocs_set_html_mail_content_type']);

        $mailResult = "n";
        $server_name = apply_filters('emsfb_get_server_host', 'yourdomain.com');
        $from = get_bloginfo('name') . " <no-reply@" . $server_name . ">";

        if (is_array($to) && isset($to[2]) && is_email($to[2])) {
            $fromEmail = is_array($to[2]) ? array_pop($to[2]) : $to[2];
            $from = get_bloginfo('name') . " <" . $fromEmail . ">";
            unset($to[2]);
        } elseif (is_object($to) && isset($to[2]) && is_email($to[2])) {
            $from = get_bloginfo('name') . " <" . $to[2] . ">";
            unset($to[2]);
        }

        $headers = [
            "MIME-Version: 1.0",
            'From:' . $from,
        ];

        add_action('wp_mail_failed', function($wp_error) {
        });

        $sendMail = function($to, $sub, $message, $headers) {

            if (is_string($to)) {
                $result = wp_mail($to, $sub, $message, $headers);
                if (!$result) {

                    $alt_result = mail($to, $sub, $message, implode("\r\n", $headers));
                }
                return $result;
            } else {
                $to = array_filter(array_unique($to));
                $success = true;
                foreach ($to as $email) {
                    if (is_email($email)) {
                        $result = wp_mail($email, $sub, $message, $headers);
                        if (!$result) {
                            $success = false;
                        }
                    }
                }
                return $success;
            }
        };

        if (is_string($sub)) {
            $message = $this->email_template_efb($pro, $state, $cont, $link, $email_content_type, $st);

            // DEBUG LOG: Email content for all states
            // $this->log_email_debug($state, $to, $sub, $message, $link, $email_content_type);

            if (in_array($state, ["reportProblem", "testMailServer", "addonsDlProblem"])) {

                $message = $this->email_template_efb($pro, $state, $cont, $link, $email_content_type, $st);
                $mailResult = $sendMail($to, $sub, $message, $headers);
            }else  {
                $mailResult = $sendMail($to, $sub, $message, $headers);
            }
        } else {

            for ($i = 0; $i < 2; $i++) {
                if (!empty($to[$i]) && $to[$i] != "null") {
                    $message = $this->email_template_efb($pro, $state[$i], $cont[$i], $link[$i], $email_content_type, $st);

                    // DEBUG LOG: Email content for array states
                    // $this->log_email_debug($state[$i], $to[$i], $sub[$i], $message, $link[$i], $email_content_type);

                    if ($state != "reportProblem") {
                        $mailResult = $sendMail($to[$i], $sub[$i], $message, $headers);
                    }
                }
            }
        }

        remove_filter('wp_mail_content_type', [$this, 'wpdocs_set_html_mail_content_type']);

        return $mailResult;
    }

    public function email_template_efb($pro, $state, $m, $link, $email_content_type, $st = "null") {

        $l = 'https://whitestudio.team';
        $wp_lan = get_locale();
        $locale_map = [
            'fa_IR' => 'https://easyformbuilder.ir',
            'ar' => 'https://ar.whitestudio.team',
            'arq' => 'https://ar.whitestudio.team',
            'de_DE' => 'https://de.whitestudio.team'
        ];
        $l = $locale_map[$wp_lan] ?? $l;

        $text_keys = ['msgdml', 'mlntip', 'msgnml', 'serverEmailAble', 'vmgs', 'getProVersion', 'sentBy', 'hiUser', 'trackingCode', 'newMessage', 'createdBy', 'newMessageReceived', 'goodJob', 'yFreeVEnPro', 'WeRecivedUrM'];
        $lang = $this->get_text_efb($text_keys);

        $automatic_email_disclaimer = '📧 ' . __('This email was sent automatically. Please do not reply.', 'easy-form-builder');

        $footer = "<a class='efb subtle-link' target='_blank' href='" . esc_url(home_url()) . "'>" . $lang['sentBy'] . " " . esc_html(get_bloginfo('name')) . "</a>";
        $align = is_rtl() ? 'right' : 'left';
        $d = is_rtl() ? 'rtl' : 'ltr';

        if ($st == 'null') {
            $st = $this->get_settings_efficiently();
        }
        if ($st == "null") return '';

        if (is_array($st)) {
            $st = json_decode(json_encode($st), false);
        }

        if ($pro == true || $pro == 1) {
            $is_pro = (int) get_option('emsfb_pro', 2);
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

        $btnBgColor = isset($st->emailBtnBgColor) && !empty($st->emailBtnBgColor) ? esc_attr($st->emailBtnBgColor) : '#202a8d';
        $btnTextColor = isset($st->emailBtnTextColor) && !empty($st->emailBtnTextColor) ? esc_attr($st->emailBtnTextColor) : '#ffffff';

        $templateConfig = ['headerBgColor' => $btnBgColor];
        $msgStyles = null;
        if ($temp != "0") {
            $tplGs = $this->extract_template_global_settings($temp);
            if ($tplGs) {
                if (!empty($tplGs['btnBgColor'])) $btnBgColor = esc_attr($tplGs['btnBgColor']);
                if (!empty($tplGs['btnTextColor'])) $btnTextColor = esc_attr($tplGs['btnTextColor']);
                if (!empty($tplGs['bgColor'])) $templateConfig['bgColor'] = $tplGs['bgColor'];
                if (!empty($tplGs['contentBgColor'])) $templateConfig['contentBgColor'] = $tplGs['contentBgColor'];
                if (!empty($tplGs['contentWidth'])) $templateConfig['contentWidth'] = intval($tplGs['contentWidth']);
                if (!empty($tplGs['borderRadius'])) $templateConfig['borderRadius'] = intval($tplGs['borderRadius']);
                if (!empty($tplGs['fontFamily'])) $templateConfig['fontFamily'] = $tplGs['fontFamily'];
                $templateConfig['headerBgColor'] = $btnBgColor;
            }
            $msgStyles = $this->extract_message_block_styles($temp);
        }

        $btnFontFamily = !empty($templateConfig['fontFamily'])
            ? $this->safe_css_value($templateConfig['fontFamily'])
            : "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif";

        if($email_content_type == 'message_link'){

        }

        $isRegistrationState = in_array($state, ['newUser', 'register']);
        $isRecoveryState = $state === 'recovery';

        $tracking_section = "";
        if ($email_content_type != 'just_message' && !$isRegistrationState && !$isRecoveryState) {
            $safe_link = esc_url($link);
            $tracking_section = "
            <div style='text-align:center; margin: 30px 0;'>
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' style='margin: 0 auto;'>
                    <tr>
                        <td align='center' style='background-color: " . $btnBgColor . "; border-radius: 8px; text-align: center; padding: 13px;'>
                            <!--[if mso]>
                            <v:roundrect xmlns:v='urn:schemas-microsoft-com:vml' xmlns:w='urn:schemas-microsoft-com:office:word' href='" . $safe_link . "' style='height:auto;v-text-anchor:middle;' arcsize='20%' strokecolor='" . $btnBgColor . "' fillcolor='" . $btnBgColor . "'>
                                <w:anchorlock/>
                                <center style='color:" . $btnTextColor . ";font-family:" . $btnFontFamily . ";font-size:18px;font-weight:700;padding:16px 32px;'>" . $lang['vmgs'] . "</center>
                            </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-->
                            <a href='" . $safe_link . "' target='_blank' style='display: block; color: " . $btnTextColor . "; text-decoration: none !important; font-weight: 700; font-size: 18px; line-height: 1; text-align: center; font-family: " . $btnFontFamily . "; border: none;'>
                                <span style='color: " . $btnTextColor . "; text-decoration: none;'>" . $lang['vmgs'] . "</span>
                            </a>
                            <!--<![endif]-->
                        </td>
                    </tr>
                </table>
            </div>
        ";
        }

        if ($isRegistrationState) {
            $title = __('Welcome!', 'easy-form-builder');
        }

        if ($isRecoveryState) {
            $title = __('Password Reset', 'easy-form-builder');
        }

        if ($state == "testMailServer") {
            $title = $lang['serverEmailAble'];
            $message = $this->generate_test_server_message($lang, $l, $wp_lan);
        } else if ($isRecoveryState) {
            // Recovery email - m contains username, link contains the full recovery URL
            $message = $this->generate_recovery_content($m, $lang, $link, $btnBgColor, $btnTextColor, $btnFontFamily);
        } else if ($isRegistrationState) {
            // Registration email to USER - m contains username, link contains the full verification URL
            $message = $this->generate_register_content($m, $lang, $link, $btnBgColor, $btnTextColor, $btnFontFamily);
        }else {

            switch ($email_content_type) {
                case 'message_link':

                    $message = $this->generate_message_link_content($m, $lang, $link, $tracking_section, $state, $msgStyles);
                    break;

                case 'just_message':

                    $message = $this->generate_just_message_content($m, $lang, $align, $msgStyles);
                    break;

                case 'traking_link':
                default:

                    $message = $this->generate_tracking_link_content($m, $lang, $link, $tracking_section, $state, $msgStyles);
                    break;
            }
        }

        $html_email = $this->generate_html_email_template($title, $message, $footer, $automatic_email_disclaimer, $d, $align, $templateConfig);

        if ($temp != "0") {
            $html_email = $this->apply_custom_template($temp, $message, $title, $blogName, $blogURL, $adminEmail, $footer, $automatic_email_disclaimer);
        }

        return $html_email;
    }

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

    private function build_content_div_style($msgStyles, $fallbackAlign = 'left') {
        if ($msgStyles) {
            $align    = esc_attr($msgStyles['align'] ?? $fallbackAlign);
            $color    = esc_attr($msgStyles['color'] ?? '#333333');
            $fontSize = intval($msgStyles['fontSize'] ?? 16);
            $fontFam  = $this->safe_css_value($msgStyles['fontFamily'] ?? '');
            $fontStr  = $fontFam ? "font-family:{$fontFam};" : '';
            return "text-align:{$align};color:{$color};font-size:{$fontSize}px;{$fontStr}margin:20px 0;";
        }
        return "text-align:{$fallbackAlign};color:#252526;font-size:14px;background:#f8f9fa;padding:15px;margin:20px 0;border-radius:8px;border:1px solid #e9ecef;";
    }

    private function generate_new_message_content($m, $lang, $link, $tracking_section, $msgStyles = null) {
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
            $divStyle = $this->build_content_div_style($msgStyles, 'center');
            return "<div style='" . $divStyle . "'>" . $m[1] . " </div>" . $tracking_section;
        }
    }

    private function generate_default_message_content($m, $lang, $link, $tracking_section, $align, $msgStyles = null) {
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
            $divStyle = $this->build_content_div_style($msgStyles, $align);

            return "
                    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                        <tr>
                            <td style='text-align: center; padding: 20px;'>
                                <h2>" . $lang["WeRecivedUrM"] . "</h2>
                                <div style='" . $divStyle . "'>" . $content . " </div>
                                " . $tracking_section . "
                            </td>
                        </tr>
                    </table>";
        }

        return "";
    }

    private function generate_message_link_content($m, $lang, $link, $tracking_section, $state, $msgStyles = null) {
        if (is_string($m)) {

            if (strpos($m, '<h2>') !== false || strpos($m, '<div') !== false) {
                return $m . $tracking_section;
            } else {
                $track_id = $m;
                $title = ($state == "newMessage") ? $lang["newMessageReceived"] : $lang["WeRecivedUrM"];
                return "<h2 style='text-align:center'>" . $title . "</h2>
                <p style='text-align:center'>" . $lang["trackingCode"] . ": " . $track_id . " </p>" . $tracking_section;
            }
        } elseif (is_array($m) && count($m) >= 2) {

            $track_id = $m[0];
            $form_content = $m[1];
            $title = ($state == "newMessage") ? $lang["newMessageReceived"] : $lang["WeRecivedUrM"];
            $divStyle = $this->build_content_div_style($msgStyles);

            return "
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                    <tr>
                        <td style='text-align: center; padding: 20px;'>
                            <h2>" . $title . "</h2>
                            <div style='" . $divStyle . "'>
                              <p style='text-align:center'>" . $lang["trackingCode"] . ": <strong>" . $track_id . "</strong></p>"
                             . $form_content .
                              " </div>
                            " . $tracking_section . "
                        </td>
                    </tr>
                </table>";
        }

        return "";
    }

    private function generate_just_message_content($m, $lang, $align, $msgStyles = null) {
        if (is_string($m)) {
            if (strpos($m, '<h2>') !== false || strpos($m, '<div') !== false) {
                return $m;
            } else {
                return "<h2 style='text-align:center'>" . $lang["WeRecivedUrM"] . "</h2>
                <p style='text-align:center;color:#666;'>" . __('Form submitted successfully without tracking.', 'easy-form-builder') . "</p>";
            }
        } elseif (is_array($m) && count($m) >= 2) {
            $form_content = $m[1];
            $divStyle = $this->build_content_div_style($msgStyles, $align);

            return "
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                    <tr>
                        <td style='text-align: center; padding: 20px;'>
                            <h2>" . $lang["WeRecivedUrM"] . "</h2>
                            <div style='" . $divStyle . "'>" . $form_content . "</div>
                        </td>
                    </tr>
                </table>";
        }

        return "";
    }

    private function generate_tracking_link_content($m, $lang, $link, $tracking_section, $state, $msgStyles = null) {

        $isRegistrationState = in_array($state, ['newUser', 'register']);

        if (is_string($m)) {

            $m = str_replace(['&quot;', '&amp;quot;'], '', $m);

            if (strpos($m, '<h2>') !== false || strpos($m, '<div') !== false || strpos($m, '<p>') !== false) {

                return $isRegistrationState ? $m : ($m . $tracking_section);
            } else {

                $track_id = $m;
                $title = ($state == "newMessage") ? $lang["newMessageReceived"] : $lang["WeRecivedUrM"];
                return "<h2 style='text-align:center'>" . $title . "</h2>
                <p style='text-align:center'>" . $lang["trackingCode"] . ": " . $track_id . " </p>" . $tracking_section;
            }
        } elseif (is_array($m) && count($m) >= 2) {

            $track_id = $m[0];
            $content = str_replace(['&quot;', '&amp;quot;'], '', $m[1]);
            $title = ($state == "newMessage") ? $lang["newMessageReceived"] : $lang["WeRecivedUrM"];

            if ($isRegistrationState && (strpos($content, '<') !== false)) {
                return $content;
            }

            return "
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='margin: 20px 0;'>
                    <tr>
                        <td style='text-align: center; padding: 20px;'>
                            <h2>" . $title . "</h2>
                            <p style='text-align:center'>" . $lang["trackingCode"] . ": <strong>" . $track_id . "</strong></p>
                            <p style='text-align:center'> " . __('Your message has been received and recorded successfully.', 'easy-form-builder') . "</p>
                            " . $tracking_section . "
                        </td>
                    </tr>
                </table>";
        }

        return "";
    }

    /**
     * Generate recovery email content with proper styling
     *
     * @param string|array $m The message content (username or pre-generated HTML)
     * @param array $lang Language strings
     * @param string $link The recovery link URL (with all parameters)
     * @param string $btnBgColor Button background color
     * @param string $btnTextColor Button text color
     * @return string The formatted recovery email content
     */
    private function generate_recovery_content($m, $lang, $link, $btnBgColor = '#667eea', $btnTextColor = '#ffffff', $btnFontFamily = "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif") {
        // If already pre-generated HTML content, return it directly
        if (is_string($m) && (strpos($m, '<h2>') !== false || strpos($m, '<div') !== false || strpos($m, '<p>') !== false || strpos($m, '<table') !== false)) {
            return $m;
        }

        // $m is the username
        $username = is_string($m) ? esc_html($m) : '';

        // Generate recovery button
        $safe_link = esc_url($link);
        $button_text = __('Reset Password', 'easy-form-builder');

        $recovery_button = "
            <table role='presentation' cellspacing='0' cellpadding='0' border='0' style='margin: 25px auto;'>
                <tr>
                    <td style='border-radius: 8px; background-color: " . esc_attr($btnBgColor) . "; text-align: center;'>
                        <!--[if mso]>
                        <v:roundrect xmlns:v='urn:schemas-microsoft-com:vml' xmlns:w='urn:schemas-microsoft-com:office:word' href='" . $safe_link . "' style='height:auto;v-text-anchor:middle;' arcsize='20%' strokecolor='" . esc_attr($btnBgColor) . "' fillcolor='" . esc_attr($btnBgColor) . "'>
                            <w:anchorlock/>
                            <center style='color:" . esc_attr($btnTextColor) . ";font-family:" . $btnFontFamily . ";font-size:16px;font-weight:600;padding:14px 35px;'>" . $button_text . "</center>
                        </v:roundrect>
                        <![endif]-->
                        <!--[if !mso]><!-->
                        <a href='" . $safe_link . "' target='_blank' style='display: inline-block; padding: 14px 35px; font-size: 16px; font-weight: 600; color: " . esc_attr($btnTextColor) . "; text-decoration: none; border-radius: 8px; background-color: " . esc_attr($btnBgColor) . "; mso-hide: all; font-family: " . $btnFontFamily . ";'>" . $button_text . "</a>
                        <!--<![endif]-->
                    </td>
                </tr>
            </table>";

        $link_text = sprintf(
            '<p style="margin: 20px 0 0 0; font-size: 13px; color: #6b7280; word-break: break-all; text-align: center;">%s<br><a href="%s" style="color: ' . esc_attr($btnBgColor) . '; text-decoration: none;">%s</a></p>',
            esc_html__('Or copy and paste this link:', 'easy-form-builder'),
            $safe_link,
            $safe_link
        );

        // Use theme color for warning section
        $warningBgColor = $this->adjust_color_brightness($btnBgColor, 0.92);
        $warning_text = '<p style="margin: 20px 0 0 0; padding: 15px; background-color: ' . esc_attr($warningBgColor) . '; border-radius: 6px; font-size: 13px; color: ' . esc_attr($btnBgColor) . '; text-align: center;">⚠️ ' . esc_html__('If you did not request this, you can safely ignore it.', 'easy-form-builder') . '</p>';

        // Greeting with username if available
        $greeting = '';
        if (!empty($username)) {
            $greeting = sprintf(
                '<p style="margin: 0 0 20px 0; font-size: 18px; text-align: center;">%s <strong>%s</strong>,</p>',
                esc_html__('Hi', 'easy-form-builder'),
                $username
            );
        }

        $main_text = '<p style="margin: 0 0 10px 0; text-align: center;">' . esc_html__('You have requested to reset your password.', 'easy-form-builder') . '</p>';
        $main_text .= '<p style="margin: 0; text-align: center;">' . esc_html__('Click the button below to set a new password. This link will be valid for 24 hours.', 'easy-form-builder') . '</p>';

        return $greeting . $main_text . $recovery_button . $link_text . $warning_text;
    }

    /**
     * Generate registration verification email content
     *
     * @param string|array $m The message content (username or pre-generated HTML)
     * @param array $lang Language strings
     * @param string $link The verification link URL (with all parameters)
     * @param string $btnBgColor Button background color
     * @param string $btnTextColor Button text color
     * @return string The formatted registration email content
     */
    private function generate_register_content($m, $lang, $link, $btnBgColor = '#22c55e', $btnTextColor = '#ffffff', $btnFontFamily = "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif") {
        // If already pre-generated HTML content, return it directly
        if (is_string($m) && (strpos($m, '<h2>') !== false || strpos($m, '<div') !== false || strpos($m, '<p>') !== false || strpos($m, '<table') !== false)) {
            return $m;
        }

        // $m is the username
        $username = is_string($m) ? esc_html($m) : '';

        $safe_link = esc_url($link);
        $button_text = __('Verify Email', 'easy-form-builder');

        $verify_button = "
            <table role='presentation' cellspacing='0' cellpadding='0' border='0' style='margin: 25px auto;'>
                <tr>
                    <td style='border-radius: 8px; background-color: " . esc_attr($btnBgColor) . "; text-align: center;'>
                        <!--[if mso]>
                        <v:roundrect xmlns:v='urn:schemas-microsoft-com:vml' xmlns:w='urn:schemas-microsoft-com:office:word' href='" . $safe_link . "' style='height:auto;v-text-anchor:middle;' arcsize='20%' strokecolor='" . esc_attr($btnBgColor) . "' fillcolor='" . esc_attr($btnBgColor) . "'>
                            <w:anchorlock/>
                            <center style='color:" . esc_attr($btnTextColor) . ";font-family:" . $btnFontFamily . ";font-size:16px;font-weight:600;padding:14px 35px;'>" . $button_text . "</center>
                        </v:roundrect>
                        <![endif]-->
                        <!--[if !mso]><!-->
                        <a href='" . $safe_link . "' target='_blank' style='display: inline-block; padding: 14px 35px; font-size: 16px; font-weight: 600; color: " . esc_attr($btnTextColor) . "; text-decoration: none; border-radius: 8px; background-color: " . esc_attr($btnBgColor) . "; mso-hide: all; font-family: " . $btnFontFamily . ";'>" . $button_text . "</a>
                        <!--<![endif]-->
                    </td>
                </tr>
            </table>";

        $link_text = sprintf(
            '<p style="margin: 20px 0 0 0; font-size: 13px; color: #6b7280; word-break: break-all; text-align: center;">%s<br><a href="%s" style="color: ' . esc_attr($btnBgColor) . '; text-decoration: none;">%s</a></p>',
            esc_html__('Or copy and paste this link:', 'easy-form-builder'),
            $safe_link,
            $safe_link
        );

        // Use theme color for warning section
        $warningBgColor = $this->adjust_color_brightness($btnBgColor, 0.92);
        $warning_text = '<p style="margin: 20px 0 0 0; padding: 15px; background-color: ' . esc_attr($warningBgColor) . '; border-radius: 6px; font-size: 13px; color: ' . esc_attr($btnBgColor) . '; text-align: center;">⚠️ ' . esc_html__('If you did not request this, you can safely ignore it.', 'easy-form-builder') . '</p>';

        // Greeting with username if available
        $greeting = '';
        if (!empty($username)) {
            $greeting = sprintf(
                '<p style="margin: 0 0 20px 0; font-size: 18px; text-align: center;">%s <strong>%s</strong>,</p>',
                esc_html__('Hi', 'easy-form-builder'),
                $username
            );
        }

        $main_text = '<p style="margin: 0 0 10px 0; text-align: center;">' . esc_html__('Your account has been successfully created!', 'easy-form-builder') . '</p>';
        $main_text .= '<p style="margin: 0; text-align: center;">' . esc_html__('Please verify your email address by clicking the button below. This activation link will be valid for 24 hours.', 'easy-form-builder') . '</p>';

        return $greeting . $main_text . $verify_button . $link_text . $warning_text;
    }

    /**
     * Generate admin notification email for new user registration
     *
     * @param string $m The username of the new user
     * @param array $lang Language strings
     * @param string $link The admin dashboard or user profile link
     * @param string $btnBgColor Button/accent background color from template settings
     * @param string $btnTextColor Button/accent text color from template settings
     * @return string The formatted admin notification email content
     */
    private function generate_admin_new_user_content($m, $lang, $link, $btnBgColor = '#202a8d', $btnTextColor = '#ffffff') {
        // If already pre-generated HTML content, return it directly
        if (is_string($m) && (strpos($m, '<h2>') !== false || strpos($m, '<div') !== false || strpos($m, '<p>') !== false || strpos($m, '<table') !== false)) {
            return $m;
        }

        $username = is_string($m) ? esc_html($m) : '';
        $registration_time = wp_date(get_option('date_format') . ' ' . get_option('time_format'));

        // Calculate lighter shade of btnBgColor for background
        $headerBgColor = $this->adjust_color_brightness($btnBgColor, 0.9);
        $infoBgColor = $this->adjust_color_brightness($btnBgColor, 0.95);

        $content = '<div style="text-align: center;">';
        $content .= '<p style="margin: 0 0 20px 0; font-size: 16px;">' . esc_html__('A new user has registered on your website.', 'easy-form-builder') . '</p>';

        // User info box with theme colors
        $content .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 20px auto; background-color: ' . esc_attr($infoBgColor) . '; border-radius: 8px; border: 1px solid ' . esc_attr($this->adjust_color_brightness($btnBgColor, 0.8)) . ';">';
        $content .= '<tr><td style="padding: 20px;">';
        $content .= '<table role="presentation" cellspacing="0" cellpadding="5" border="0" width="100%">';

        // Username row
        $content .= '<tr>';
        $content .= '<td style="text-align: right; padding: 8px 15px; color: #6b7280; font-size: 14px;"><strong>' . esc_html__('Username', 'easy-form-builder') . ':</strong></td>';
        $content .= '<td style="text-align: left; padding: 8px 15px; color: #1f2937; font-size: 14px;">' . $username . '</td>';
        $content .= '</tr>';

        // Registration time row
        $content .= '<tr>';
        $content .= '<td style="text-align: right; padding: 8px 15px; color: #6b7280; font-size: 14px;"><strong>' . esc_html__('Registered', 'easy-form-builder') . ':</strong></td>';
        $content .= '<td style="text-align: left; padding: 8px 15px; color: #1f2937; font-size: 14px;">' . esc_html($registration_time) . '</td>';
        $content .= '</tr>';

        $content .= '</table>';
        $content .= '</td></tr></table>';

        // Note for admin with theme colors
        $content .= '<p style="margin: 20px 0 0 0; padding: 15px; background-color: ' . esc_attr($headerBgColor) . '; border-radius: 6px; font-size: 13px; color: ' . esc_attr($btnBgColor) . '; text-align: center;">ℹ️ ' . esc_html__('The user has been sent a verification email to confirm their account.', 'easy-form-builder') . '</p>';

        $content .= '</div>';

        return $content;
    }

    /**
     * Adjust color brightness
     *
     * @param string $hex Hex color code
     * @param float $factor Factor to adjust (0-1 for darker, >1 for lighter, 0.9 = 90% lightness mix with white)
     * @return string Adjusted hex color
     */
    private function adjust_color_brightness($hex, $factor) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return '#f8f9fa'; // Return default light gray if invalid
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Mix with white based on factor
        $r = round($r + (255 - $r) * $factor);
        $g = round($g + (255 - $g) * $factor);
        $b = round($b + (255 - $b) * $factor);

        $r = min(255, max(0, $r));
        $g = min(255, max(0, $g));
        $b = min(255, max(0, $b));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * DEBUG: Log email content for debugging purposes
     * Check wp-content/debug.log to see the output
     *
     * @param string $state Email state (register, recovery, newUser, etc.)
     * @param string|array $to Recipient email(s)
     * @param string $subject Email subject
     * @param string $message Email HTML content
     * @param string $link Link included in email
     * @param string $email_content_type Content type
     */
    private function log_email_debug($state, $to, $subject, $message, $link, $email_content_type) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return; // Only log when WP_DEBUG is enabled
        }

        $log_file = WP_CONTENT_DIR . '/efb-email-debug.log';
        $timestamp = wp_date('Y-m-d H:i:s');

        $log_content = "\n";
        $log_content .= "╔══════════════════════════════════════════════════════════════════════════════╗\n";
        $log_content .= "║  EMAIL DEBUG LOG - " . str_pad($timestamp, 58) . "║\n";
        $log_content .= "╠══════════════════════════════════════════════════════════════════════════════╣\n";
        $log_content .= "║  State: " . str_pad($state, 69) . "║\n";
        $log_content .= "║  Content Type: " . str_pad($email_content_type, 62) . "║\n";
        $log_content .= "║  To: " . str_pad(is_array($to) ? implode(', ', $to) : $to, 72) . "║\n";
        $log_content .= "║  Subject: " . str_pad(mb_substr($subject, 0, 65), 67) . "║\n";
        $log_content .= "║  Link: " . str_pad(mb_substr($link, 0, 68), 70) . "║\n";
        $log_content .= "╚══════════════════════════════════════════════════════════════════════════════╝\n";
        $log_content .= "\n───────────────────────────────────────────────────────────────────────────────\n";
        $log_content .= "EMAIL HTML CONTENT:\n";
        $log_content .= "───────────────────────────────────────────────────────────────────────────────\n";
        $log_content .= $message;
        $log_content .= "\n───────────────────────────────────────────────────────────────────────────────\n";
        $log_content .= "END OF EMAIL\n";
        $log_content .= "═══════════════════════════════════════════════════════════════════════════════\n\n";

        // Write to custom log file
        file_put_contents($log_file, $log_content, FILE_APPEND | LOCK_EX);

        // Also log summary to WordPress debug.log
        error_log("[EFB Email Debug] State: {$state} | To: " . (is_array($to) ? implode(', ', $to) : $to) . " | Subject: {$subject} | See full HTML in: {$log_file}");
    }

    private function generate_html_email_template($title, $message, $footer, $disclaimer, $direction, $align, $config = []) {
        $bgColor = esc_attr($config['bgColor'] ?? '#f8f9fa');
        $contentBgColor = esc_attr($config['contentBgColor'] ?? '#ffffff');
        $contentWidth = intval($config['contentWidth'] ?? 600);
        $borderRadius = intval($config['borderRadius'] ?? 8);
        $fontFamily = $this->safe_css_value($config['fontFamily'] ?? "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif");
        $headerBgColor = esc_attr($config['headerBgColor'] ?? '#202a8d');
        $headerGradientStart = $this->adjust_color_brightness($headerBgColor, 0.4);

        return "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\" xmlns:v=\"urn:schemas-microsoft-com:vml\" xmlns:o=\"urn:schemas-microsoft-com:office:office\">
<head>
    <meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />
    <title>" . esc_html($title) . "</title>
    <!--[if gte mso 9]><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
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
<body style=\"margin: 0; padding: 0; width: 100%; background-color: $bgColor; direction: $direction; font-family: $fontFamily;\">
    <table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\" style=\"margin: 0; padding: 0; background-color: $bgColor;\">
        <tr>
            <td align=\"center\" style=\"padding: 20px 0;\">
                <table class=\"email-container\" role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"$contentWidth\" style=\"margin: 0 auto; background-color: $contentBgColor; border-radius: {$borderRadius}px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;\">
                    <tr>
                        <td align=\"center\" style=\"padding: 40px 30px 30px 30px; background: linear-gradient(135deg, $headerGradientStart 0%, $headerBgColor 100%);\">
                            <table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\">
                                <tr>
                                    <td align=\"center\">
                                        <img src=\"" . (defined('EMSFB_PLUGIN_URL') ? EMSFB_PLUGIN_URL : '') . "public/assets/images/email_template1.png\" alt=\"Easy Form Builder\" class=\"header-image\" style=\"width: 120px; height: auto; display: block; margin: 0 auto 20px auto; border: none;\" />
                                    </td>
                                </tr>
                                <tr>
                                    <td align=\"center\">
                                        <h1 style=\"margin: 0; padding: 0; color: #ffffff; font-size: 28px; font-weight: 600; line-height: 1.3; text-align: center; font-family: $fontFamily;\">$title</h1>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class=\"content-wrapper\" style=\"padding: 40px 30px;\">
                            <table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"100%\">
                                <tr>
                                    <td class=\"message-content\" align=\"center\" style=\"color: #333333; font-size: 16px; line-height: 1.6; text-align: center; font-family: $fontFamily;\">
                                        $message
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style=\"height: 20px; background-color: $contentBgColor;\"></td>
                    </tr>
                </table>
                <table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"$contentWidth\" style=\"margin: 20px auto 0 auto;\">
                    <tr>
                        <td class=\"footer-content\" align=\"center\" style=\"padding: 30px; color: #6b7280; font-size: 14px; line-height: 1.5; text-align: center; font-family: $fontFamily;\">
                            $footer
                        </td>
                    </tr>
                    <tr>
                        <td align=\"center\" style=\"padding-bottom: 20px;\">
                            <table role=\"presentation\" cellspacing=\"0\" cellpadding=\"0\" border=\"0\">
                                <tr>
                                    <td style=\"border-radius: 20px; background-color: #f1f5f9; padding: 15px 25px;\">
                                        <p style=\"margin: 0; color: #64748b; font-size: 12px; text-align: center; font-family: $fontFamily;\">
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

    private function extract_template_global_settings($temp) {
        if (preg_match('/<!-- EFBDATA:([\S]+) -->/', $temp, $match)) {
            $data = json_decode(urldecode($match[1]), true);
            if ($data && isset($data['globalSettings'])) {
                return $data['globalSettings'];
            }
        }
        return null;
    }

    private function extract_message_block_styles($temp) {
        if (preg_match('/<!-- EFBDATA:([\S]+) -->/', $temp, $match)) {
            $data = json_decode(urldecode($match[1]), true);
            if ($data && isset($data['blocks']) && is_array($data['blocks'])) {
                foreach ($data['blocks'] as $block) {
                    if (isset($block['type']) && $block['type'] === 'message') {
                        $d = $block['data'] ?? [];
                        $gs = $data['globalSettings'] ?? [];
                        return [
                            'bgColor'    => $d['bgColor'] ?? '#ffffff',
                            'color'      => $d['color'] ?? '#333333',
                            'fontSize'   => intval($d['fontSize'] ?? 16),
                            'fontFamily' => !empty($d['fontFamily']) ? $d['fontFamily'] : ($gs['fontFamily'] ?? ''),
                            'align'      => $d['align'] ?? (is_rtl() ? 'right' : 'left'),
                            'padding'    => $d['padding'] ?? '40px 30px',
                        ];
                    }
                }
            }
        }
        return null;
    }

    private function apply_custom_template($temp, $message, $title, $blogName, $blogURL, $adminEmail, $footer, $disclaimer) {
        $replacements = [
            'shortcode_message' => $message,
            'shortcode_title' => $title,
            'shortcode_website_name' => $blogName,
            'shortcode_website_url' => $blogURL,
            'shortcode_admin_email' => $adminEmail
        ];

        $efbdata_json = null;
        if (preg_match('/<!-- EFBDATA:([\S]+) -->/', $temp, $efb_match)) {
            $efbdata_json = $efb_match[1];
        }

        if ($efbdata_json) {
            $rebuilt = $this->generate_from_efbdata($efbdata_json, $replacements);
            if ($rebuilt !== false) {
                return $rebuilt;
            }
        }

        $temp = preg_replace('/\n?<!-- EFBDATA:.*? -->/', '', $temp);

        $temp = strtr($temp, $replacements);

        $temp = preg_replace(['/http:(?:@efb@)+/', '/https:(?:@efb@)+/'], ['http://', 'https://'], $temp);
        $temp = str_replace('@efb@', '/', $temp);

        $isBuilderTemplate = (strpos($temp, 'efb-email-container') !== false);

        if ($isBuilderTemplate) {
            if (stripos($temp, '<!DOCTYPE') === false && stripos($temp, '<html') === false) {
                $temp = $this->wrap_builder_template_html($temp);
            }
        } else {

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

    private function safe_css_value($value) {

        $value = str_replace(['"', '<', '>', '\\'], '', $value);

        $value = preg_replace('/expression\s*\(/i', '', $value);
        $value = preg_replace('/javascript\s*:/i', '', $value);
        $value = preg_replace('/\burl\s*\(/i', '', $value);
        $value = preg_replace('/-moz-binding\s*:/i', '', $value);
        $value = preg_replace('/behavior\s*:/i', '', $value);
        $value = preg_replace('/@import/i', '', $value);
        return trim($value);
    }

    private function generate_from_efbdata($efbdata_encoded, $replacements) {
        $data = json_decode(urldecode($efbdata_encoded), true);
        if (!$data || !isset($data['blocks']) || !is_array($data['blocks'])) {
            return false;
        }

        $blocks = $data['blocks'];
        $gs = $data['globalSettings'] ?? [];

        $bgColor        = $gs['bgColor']        ?? '#f8f9fa';
        $contentBgColor = $gs['contentBgColor']  ?? '#ffffff';
        $contentWidth   = intval($gs['contentWidth'] ?? 600);
        $borderRadius   = intval($gs['borderRadius'] ?? 8);
        $fontFamily     = $gs['fontFamily']      ?? "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif";
        $direction      = $gs['direction']       ?? (is_rtl() ? 'rtl' : 'ltr');

        $rows_html = '';
        foreach ($blocks as $block) {
            $rows_html .= $this->render_efb_block($block, $gs, $replacements);
        }

        $mso_open  = '<!--[if mso]><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="' . $contentWidth . '" align="center"><tr><td><![endif]-->';
        $mso_close = '<!--[if mso]></td></tr></table><![endif]-->';

        $safe_bg       = esc_attr($bgColor);
        $safe_cbg      = esc_attr($contentBgColor);
        $safe_dir      = esc_attr($direction);
        $safe_ff       = $this->safe_css_value($fontFamily);
        $safe_br       = esc_attr($borderRadius);
        $safe_cw       = esc_attr($contentWidth);

        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<!--[if gte mso 9]><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
<style type="text/css">
body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { -ms-interpolation-mode: bicubic; border: 0; }
body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
table { border-collapse: collapse !important; }
@media only screen and (max-width: 600px) {
  .efb-email-wrapper { max-width: 100% !important; width: 100% !important; }
  .efb-email-container { width: 100% !important; }
  .efb-email-container td { padding-left: 15px !important; padding-right: 15px !important; }
  img { max-width: 100% !important; height: auto !important; }
}
</style>
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: ' . $safe_bg . '; direction: ' . $safe_dir . '; font-family: ' . $safe_ff . ';">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: ' . $safe_bg . ';">
<tr><td align="center" style="padding: 20px 0;">
' . $mso_open . '
<div class="efb-email-wrapper" style="max-width: ' . $safe_cw . 'px; margin: 0 auto; border-radius: ' . $safe_br . 'px; overflow: hidden; background-color: ' . $safe_cbg . ';">
<table class="efb-email-container" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: ' . $safe_cbg . ';">
' . $rows_html . '
</table>
</div>
' . $mso_close . '
</td></tr>
</table>
</body>
</html>';
    }

    private function render_efb_block($block, $gs, $replacements) {
        $type = $block['type'] ?? '';
        $d    = $block['data'] ?? [];
        $contentBgColor = $gs['contentBgColor'] ?? '#ffffff';
        $globalFont     = $gs['fontFamily'] ?? "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif";

        $ff = function($blockFont = '') use ($globalFont) {
            return (!empty($blockFont) && $blockFont !== '') ? $blockFont : $globalFont;
        };

        $sc = function($text) use ($replacements) {
            return strtr($text, $replacements);
        };

        switch ($type) {
            case 'header':
                return $this->render_header_block($d, $block['children'] ?? [], $gs, $replacements);

            case 'logo':
                return $this->render_logo_block($d);

            case 'title':
                return $this->render_title_block($d, $gs, $replacements);

            case 'text':
                return $this->render_text_block($d, $gs, $replacements);

            case 'message':
                return $this->render_message_block($d, $gs, $replacements);

            case 'button':
                return $this->render_button_block($d, $gs, $replacements);

            case 'divider':
                return $this->render_divider_block($d, $gs);

            case 'spacer':
                return $this->render_spacer_block($d);

            case 'image':
                return $this->render_image_block($d, $gs, $replacements);

            case 'columns':
                return $this->render_columns_block($d, $gs, $replacements);

            case 'social':
                return $this->render_social_block($d, $gs, $replacements);

            case 'footer':
                return $this->render_footer_block($d, $gs, $replacements);

            case 'htmlBlock':
                return $this->render_html_block($d, $replacements);

            default:
                return '';
        }
    }

    private function render_header_block($d, $children, $gs, $replacements) {
        $align   = esc_attr($d['align'] ?? 'center');
        $padding = esc_attr($d['padding'] ?? '40px 30px 30px 30px');
        $bg      = !empty($d['bgGradient']) ? $d['bgGradient'] : ($d['bgColor'] ?? '#202a8d');
        $isGradient = (strpos($bg, 'gradient') !== false);
        $borderRadius = isset($gs['borderRadius']) ? intval($gs['borderRadius']) : 8;

        $solidFallback = $d['bgColor'] ?? '#202a8d';
        if ($isGradient && preg_match('/#[0-9a-fA-F]{3,8}/', $bg, $cMatch)) {
            $solidFallback = $cMatch[0];
        }

        $inner = '';
        foreach ($children as $child) {
            $childType = $child['type'] ?? '';
            $childData = $child['data'] ?? [];
            if ($childType === 'logo') {
                $inner .= $this->render_logo_block($childData, $align);
            } elseif ($childType === 'title') {
                $inner .= $this->render_title_block($childData, $gs, $replacements, $align);
            }
        }

        $bgStyle = $isGradient
            ? 'background-color: ' . esc_attr($solidFallback) . '; background: ' . esc_attr($bg) . ';'
            : 'background-color: ' . esc_attr($bg) . ';';

        return '<tr><td align="' . $align . '" style="padding: ' . $padding . '; ' . $bgStyle . ' text-align: ' . $align . ';">
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            ' . $inner . '
          </table>
        </td></tr>';
    }

    private function render_logo_block($d, $parentAlign = null) {
        $src   = esc_url($d['src'] ?? '');
        $alt   = esc_attr($d['alt'] ?? 'Logo');
        $width = intval($d['width'] ?? 120);
        $align = esc_attr($parentAlign ?? $d['align'] ?? 'center');

        $margin = '0 auto 20px auto';
        if ($align === 'left')  $margin = '0 auto 20px 0';
        if ($align === 'right') $margin = '0 0 20px auto';

        return '<tr><td align="' . $align . '">
          <img src="' . $src . '" alt="' . $alt . '" style="width: ' . $width . 'px; height: auto; display: block; margin: ' . $margin . '; border: none;" />
        </td></tr>';
    }

    private function render_title_block($d, $gs, $replacements, $parentAlign = null) {
        $text       = strtr(($d['text'] ?? ''), $replacements);
        $color      = esc_attr($d['color'] ?? '#ffffff');
        $fontSize   = intval($d['fontSize'] ?? 28);
        $fontWeight = esc_attr($d['fontWeight'] ?? '600');
        $align      = esc_attr($parentAlign ?? $d['align'] ?? 'center');
        $globalFont = $gs['fontFamily'] ?? "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif";
        $fontFam    = $this->safe_css_value(!empty($d['fontFamily']) ? $d['fontFamily'] : $globalFont);

        return '<tr><td align="' . $align . '">
          <h1 style="margin: 0; padding: 0; color: ' . $color . '; font-size: ' . $fontSize . 'px; font-weight: ' . $fontWeight . '; line-height: 1.3; text-align: ' . $align . '; font-family: ' . $fontFam . ';">' . $text . '</h1>
        </td></tr>';
    }

    private function render_text_block($d, $gs, $replacements) {
        $text       = strtr(($d['text'] ?? ''), $replacements);
        $color      = esc_attr($d['color'] ?? '#333333');
        $fontSize   = intval($d['fontSize'] ?? 16);
        $lineHeight = esc_attr($d['lineHeight'] ?? '1.6');
        $align      = esc_attr($d['align'] ?? 'center');
        $padding    = esc_attr($d['padding'] ?? '10px 30px');
        $globalFont = $gs['fontFamily'] ?? "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif";
        $fontFam    = $this->safe_css_value(!empty($d['fontFamily']) ? $d['fontFamily'] : $globalFont);
        $contentBg  = esc_attr($gs['contentBgColor'] ?? '#ffffff');

        return '<tr><td style="padding: ' . $padding . '; background-color: ' . $contentBg . ';">
          <p style="margin: 0; color: ' . $color . '; font-size: ' . $fontSize . 'px; line-height: ' . $lineHeight . '; text-align: ' . $align . '; font-family: ' . $fontFam . ';">' . $text . '</p>
        </td></tr>';
    }

    private function render_message_block($d, $gs, $replacements) {
        $padding    = esc_attr($d['padding'] ?? '40px 30px');
        $bgColor    = esc_attr($d['bgColor'] ?? '#ffffff');
        $color      = esc_attr($d['color'] ?? '#333333');
        $fontSize   = intval($d['fontSize'] ?? 16);
        $align      = esc_attr($d['align'] ?? 'center');
        $globalFont = $gs['fontFamily'] ?? "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif";
        $fontFam    = $this->safe_css_value(!empty($d['fontFamily']) ? $d['fontFamily'] : $globalFont);

        $content = $replacements['shortcode_message'] ?? 'shortcode_message';

        return '<tr><td style="padding: ' . $padding . '; background-color: ' . $bgColor . ';">
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr><td align="' . $align . '" style="color: ' . $color . '; font-size: ' . $fontSize . 'px; line-height: 1.6; text-align: ' . $align . '; font-family: ' . $fontFam . ';">
              ' . $content . '
            </td></tr>
          </table>
        </td></tr>';
    }

    private function render_button_block($d, $gs, $replacements) {
        $text        = strtr(($d['text'] ?? 'Click Here'), $replacements);
        $url         = esc_url(strtr(($d['url'] ?? '#'), $replacements));
        $bgColor     = esc_attr($d['bgColor'] ?? '#202a8d');
        $textColor   = esc_attr($d['textColor'] ?? '#ffffff');
        $borderRad   = intval($d['borderRadius'] ?? 8);
        $padding     = esc_attr($d['padding'] ?? '14px 32px');
        $fontSize    = intval($d['fontSize'] ?? 16);
        $align       = esc_attr($d['align'] ?? 'center');
        $cPadding    = esc_attr($d['containerPadding'] ?? '20px 30px');
        $globalFont  = $gs['fontFamily'] ?? "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif";
        $fontFam     = $this->safe_css_value(!empty($d['fontFamily']) ? $d['fontFamily'] : $globalFont);
        $contentBg   = esc_attr($gs['contentBgColor'] ?? '#ffffff');

        $margin = '0 auto';
        if ($align === 'left')  $margin = '0 auto 0 0';
        if ($align === 'right') $margin = '0 0 0 auto';

        $padParts = preg_split('/\s+/', trim($padding));
        $padTop = intval($padParts[0] ?? 14);
        $padRight = intval($padParts[1] ?? $padParts[0] ?? 32);
        $padBottom = intval($padParts[2] ?? $padParts[0] ?? 14);
        $padLeft = intval($padParts[3] ?? $padParts[1] ?? $padParts[0] ?? 32);
        $btnWidth = 0;

        $vml_btn = '<!--[if mso]>
          <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $url . '" style="height:auto;v-text-anchor:middle;" arcsize="' . ($borderRad > 0 ? intval($borderRad * 100 / 40) : 0) . '%" strokecolor="' . $bgColor . '" fillcolor="' . $bgColor . '">
            <w:anchorlock/>
            <center style="color:' . $textColor . ';font-family:' . $fontFam . ';font-size:' . $fontSize . 'px;font-weight:600;padding:' . $padTop . 'px ' . $padRight . 'px ' . $padBottom . 'px ' . $padLeft . 'px;">' . $text . '</center>
          </v:roundrect>
        <![endif]-->';

        return '<tr><td align="' . $align . '" style="background-color: ' . $contentBg . '; padding: ' . $cPadding . ';">
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="' . $align . '" style="margin: ' . $margin . ';">
            <tr>
              <td style="background-color: ' . $bgColor . '; border-radius: ' . $borderRad . 'px; text-align: center;">
                ' . $vml_btn . '
                <a href="' . $url . '" target="_blank" style="display: inline-block; padding: ' . $padding . '; color: ' . $textColor . '; text-decoration: none; font-family: ' . $fontFam . '; font-size: ' . $fontSize . 'px; font-weight: 600; line-height: 1; mso-hide: all;">' . $text . '</a>
              </td>
            </tr>
          </table>
        </td></tr>';
    }

    private function render_divider_block($d, $gs) {
        $color     = esc_attr($d['color'] ?? '#e5e7eb');
        $thickness = intval($d['thickness'] ?? 1);
        $width     = intval($d['width'] ?? 100);
        $padding   = esc_attr($d['padding'] ?? '15px 30px');
        $contentBg = esc_attr($gs['contentBgColor'] ?? '#ffffff');

        return '<tr><td style="background-color: ' . $contentBg . '; padding: ' . $padding . ';">
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="' . $width . '%" align="center" style="margin: 0 auto;">
            <tr>
              <td style="border-top: ' . $thickness . 'px solid ' . $color . '; font-size: 1px; line-height: 1px;">&nbsp;</td>
            </tr>
          </table>
        </td></tr>';
    }

    private function render_spacer_block($d) {
        $height  = intval($d['height'] ?? 20);
        $bgColor = esc_attr($d['bgColor'] ?? 'transparent');

        return '<tr><td style="height: ' . $height . 'px; background-color: ' . $bgColor . ';">&nbsp;</td></tr>';
    }

    private function render_image_block($d, $gs, $replacements) {
        $src       = esc_url($d['src'] ?? '');
        $alt       = esc_attr($d['alt'] ?? '');
        $width     = $d['width'] ?? '100';
        $widthUnit = $d['widthUnit'] ?? '%';
        $align     = esc_attr($d['align'] ?? 'center');
        $padding   = esc_attr($d['padding'] ?? '10px 30px');
        $link      = !empty($d['link']) ? esc_url(strtr($d['link'], $replacements)) : '';
        $contentBg = esc_attr($gs['contentBgColor'] ?? '#ffffff');

        $w = esc_attr($width . $widthUnit);
        $img = '<img src="' . $src . '" alt="' . $alt . '" style="width: ' . $w . '; max-width: 100%; height: auto; display: block; border: none;" />';

        if ($link) {
            $img = '<a href="' . $link . '" target="_blank">' . $img . '</a>';
        }

        return '<tr><td align="' . $align . '" style="background-color: ' . $contentBg . '; padding: ' . $padding . ';">
          ' . $img . '
        </td></tr>';
    }

    private function render_columns_block($d, $gs, $replacements) {
        $padding    = esc_attr($d['padding'] ?? '20px 30px');
        $gap        = intval($d['gap'] ?? 20);
        $leftColor  = esc_attr($d['leftColor'] ?? '#333333');
        $rightColor = esc_attr($d['rightColor'] ?? '#333333');
        $fontSize   = intval($d['fontSize'] ?? 14);
        $bgColor    = esc_attr($d['bgColor'] ?? ($gs['contentBgColor'] ?? '#ffffff'));
        $globalFont = $gs['fontFamily'] ?? "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif";
        $fontFam    = $this->safe_css_value(!empty($d['fontFamily']) ? $d['fontFamily'] : $globalFont);

        $leftContent  = strtr(($d['leftContent'] ?? ''), $replacements);
        $rightContent = strtr(($d['rightContent'] ?? ''), $replacements);
        $halfGap = intval($gap / 2);

        return '<tr><td style="padding: ' . $padding . '; background-color: ' . $bgColor . ';">
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
              <td width="48%" valign="top" style="padding-right: ' . $halfGap . 'px; color: ' . $leftColor . '; font-size: ' . $fontSize . 'px; line-height: 1.6; font-family: ' . $fontFam . ';">' . $leftContent . '</td>
              <td width="4%"></td>
              <td width="48%" valign="top" style="padding-left: ' . $halfGap . 'px; color: ' . $rightColor . '; font-size: ' . $fontSize . 'px; line-height: 1.6; font-family: ' . $fontFam . ';">' . $rightContent . '</td>
            </tr>
          </table>
        </td></tr>';
    }

    private function render_social_block($d, $gs, $replacements) {
        $align     = esc_attr($d['align'] ?? 'center');
        $padding   = esc_attr($d['padding'] ?? '20px 30px');
        $iconColor = $d['color'] ?? '#333333';
        $iconSize  = intval($d['iconSize'] ?? 24);
        $links     = $d['links'] ?? [];
        $contentBg = esc_attr($gs['contentBgColor'] ?? '#ffffff');

        $linksHtml = '';
        foreach ($links as $link) {
            $url   = esc_url(strtr(($link['url'] ?? '#'), $replacements));
            $name  = $link['name'] ?? '';
            $icon  = $link['icon'] ?? '';
            $label = esc_attr($name ?: ucfirst($icon));

            $icon_html = '';

            // 1. Try existing colored PNG from plugin assets
            $png_url = $this->get_colored_icon_url($icon, $iconColor);
            if ($png_url) {
                $icon_html = '<img src="' . esc_url($png_url) . '" alt="' . $label . '" width="' . $iconSize . '" height="' . $iconSize . '" style="display:inline-block;vertical-align:middle;border:0;" />';
            }

            // 2. Generate icon image file (PNG via Imagick, GD circle, or SVG file)
            if (!$icon_html) {
                $gen_url = $this->get_social_icon_file_url($icon, $iconColor, max($iconSize * 2, 48));
                if ($gen_url) {
                    $icon_html = '<img src="' . esc_url($gen_url) . '" alt="' . $label . '" width="' . $iconSize . '" height="' . $iconSize . '" style="display:inline-block;vertical-align:middle;border:0;" />';
                }
            }

            // 3. Text fallback with emoji (works in all email clients)
            if (!$icon_html) {
                $emoji = $this->get_social_emoji($icon);
                $safe_color = esc_attr($iconColor);
                $fs = max(12, intval($iconSize * 0.6));
                $icon_html = '<span style="display:inline-block;width:' . $iconSize . 'px;height:' . $iconSize . 'px;line-height:' . $iconSize . 'px;text-align:center;font-size:' . $fs . 'px;vertical-align:middle;">' . $emoji . '</span>';
            }

            $linksHtml .= '<a href="' . $url . '" target="_blank" style="display:inline-block;margin:0 6px;text-decoration:none;vertical-align:middle;line-height:1;">' . $icon_html . '</a>';
        }

        return '<tr><td align="' . $align . '" style="background-color: ' . $contentBg . '; padding: ' . $padding . ';">
          ' . $linksHtml . '
        </td></tr>';
    }

    private function get_colored_icon_url($icon, $color) {
        if (!defined('EMSFB_PLUGIN_URL') || !defined('EMSFB_PLUGIN_DIRECTORY')) {
            return '';
        }

        $safe_icon = sanitize_file_name($icon);
        $base_rel  = 'public/assets/images/social/' . $safe_icon . '.png';
        $base_path = EMSFB_PLUGIN_DIRECTORY . $base_rel;

        if (!file_exists($base_path)) {
            return '';
        }

        $hex = ltrim(sanitize_hex_color($color) ?: '#333333', '#');

        if (!function_exists('imagecreatefrompng')) {
            return EMSFB_PLUGIN_URL . $base_rel;
        }

        $upload_dir = wp_upload_dir();
        $cache_dir  = $upload_dir['basedir'] . '/efb-icons/' . $hex;
        $cache_file = $cache_dir . '/' . $safe_icon . '.png';
        $cache_url  = $upload_dir['baseurl'] . '/efb-icons/' . $hex . '/' . $safe_icon . '.png';

        if (file_exists($cache_file)) {
            return $cache_url;
        }

        if (!is_dir($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $img = @imagecreatefrompng($base_path);
        if (!$img) {
            return EMSFB_PLUGIN_URL . $base_rel;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        imagealphablending($img, false);
        imagesavealpha($img, true);

        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgba  = imagecolorat($img, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha < 127) {
                    $new_color = imagecolorallocatealpha($img, $r, $g, $b, $alpha);
                    imagesetpixel($img, $x, $y, $new_color);
                }
            }
        }

        imagepng($img, $cache_file, 9);
        imagedestroy($img);

        return file_exists($cache_file) ? $cache_url : EMSFB_PLUGIN_URL . $base_rel;
    }

    private function render_footer_block($d, $gs, $replacements) {
        $text       = strtr(($d['text'] ?? ''), $replacements);
        $color      = esc_attr($d['color'] ?? '#666666');
        $fontSize   = intval($d['fontSize'] ?? 14);
        $align      = esc_attr($d['align'] ?? 'center');
        $bgColor    = esc_attr($d['bgColor'] ?? ($gs['contentBgColor'] ?? '#ffffff'));
        $padding    = esc_attr($d['padding'] ?? '25px 30px');
        $borderRad  = esc_attr($d['borderRadius'] ?? '0');
        $globalFont = $gs['fontFamily'] ?? "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif";
        $fontFam    = $this->safe_css_value(!empty($d['fontFamily']) ? $d['fontFamily'] : $globalFont);

        return '<tr><td style="padding: ' . $padding . '; background-color: ' . $bgColor . '; border-radius: ' . $borderRad . ';">
          <p style="margin: 0; color: ' . $color . '; font-size: ' . $fontSize . 'px; line-height: 1.5; text-align: ' . $align . '; font-family: ' . $fontFam . ';">' . $text . '</p>
        </td></tr>';
    }

    private function render_html_block($d, $replacements) {
        $html = strtr(($d['html'] ?? ''), $replacements);
        return '<tr><td>' . $html . '</td></tr>';
    }

    private function get_social_emoji($icon) {
        $map = [
            'facebook'  => '&#x1F1EB;',
            'x'         => '&#x2717;',
            'instagram' => '&#x1F4F7;',
            'linkedin'  => '&#x1F517;',
            'youtube'   => '&#x25B6;',
            'tiktok'    => '&#x266B;',
            'whatsapp'  => '&#x1F4AC;',
            'telegram'  => '&#x2708;',
            'pinterest' => '&#x1F4CC;',
            'github'    => '&#x2699;',
            'website'   => '&#x1F310;',
            'email'     => '&#x2709;',
        ];
        return $map[$icon] ?? '&#x1F517;';
    }

    private function get_social_icon_svg($icon, $color = '#333333', $size = 24) {
        $esc_color = esc_attr($color);
        $paths = [
            'facebook'  => 'M24 12.073c0-6.627-5.373-12-12-12S0 5.446 0 12.073c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
            'x'         => 'M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932zM17.61 20.644h2.039L6.486 3.24H4.298z',
            'instagram' => 'M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405a1.441 1.441 0 11-2.882 0 1.441 1.441 0 012.882 0z',
            'linkedin'  => 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
            'youtube'   => 'M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z',
            'tiktok'    => 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
            'whatsapp'  => 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z',
            'telegram'  => 'M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0a12 12 0 00-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z',
            'pinterest' => 'M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12.017 24c6.624 0 11.99-5.367 11.99-11.988C24.007 5.367 18.641.001 12.017.001z',
            'snapchat'  => 'M12.206.793c.99 0 4.347.276 5.93 3.821.529 1.193.403 3.219.299 4.847l-.003.06c-.012.18-.022.345-.03.51.075.045.203.09.401.09.3-.016.659-.12 1.033-.301a.32.32 0 01.139-.029c.108 0 .234.029.365.104.21.12.3.27.3.42v.012c-.06.45-.539.63-.959.719-.03.011-.06.016-.09.026-.21.059-.39.105-.45.359l-.009.031c-.12.48.12.9.33 1.32.36.72.87 1.38 1.47 1.89.33.27.6.51.96.63.12.06.27.12.27.36-.06.27-.33.42-.56.481-.27.075-.56.12-.84.18-.27.045-.53.089-.78.149a.37.37 0 00-.27.27c-.03.105 0 .225.06.36.12.21.18.45.21.66.032.24-.068.48-.208.62-.18.18-.42.24-.66.24-.27.014-.54-.06-.81-.18-.27-.12-.51-.18-.78-.24-.15-.03-.3-.049-.45-.049-.54 0-.96.33-1.29.57-.66.45-1.17.81-2.16.87a4.98 4.98 0 01-.36.01c-.21 0-.51-.03-.78-.06-1.2-.15-2.01-.57-2.73-1.07-.45-.3-.87-.51-1.35-.51-.15 0-.3.015-.45.045-.27.06-.51.12-.78.24-.27.12-.54.196-.81.18a.982.982 0 01-.66-.24c-.14-.14-.24-.38-.21-.62.03-.21.09-.45.21-.66.06-.135.09-.255.06-.36a.37.37 0 00-.27-.27c-.24-.06-.51-.105-.78-.15-.3-.06-.57-.104-.84-.18-.24-.06-.51-.21-.57-.48l.003-.06c.03-.18.06-.33.27-.345.36-.12.63-.36.96-.63.6-.51 1.11-1.17 1.47-1.89.21-.42.45-.84.33-1.32l-.009-.03c-.06-.255-.24-.3-.45-.36-.03-.009-.06-.015-.09-.024-.42-.09-.9-.27-.96-.72v-.015c0-.15.09-.3.3-.42.12-.075.255-.105.365-.105a.35.35 0 01.135.03c.375.18.735.285 1.035.3.3 0 .435-.074.465-.09l-.003-.06a30.9 30.9 0 00-.033-.51c-.104-1.628-.23-3.654.3-4.847C7.86 1.07 11.216.793 12.206.793z',
            'github'    => 'M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12',
            'dribbble'  => 'M12 24C5.385 24 0 18.615 0 12S5.385 0 12 0s12 5.385 12 12-5.385 12-12 12zm10.12-10.358c-.35-.11-3.17-.953-6.384-.438 1.34 3.684 1.887 6.684 1.992 7.308a10.28 10.28 0 004.395-6.87zm-6.115 7.808c-.153-.9-.75-4.032-2.19-7.77l-.066.02c-5.79 2.015-7.86 6.025-8.04 6.4a10.161 10.161 0 006.29 2.166c1.42 0 2.77-.29 4.006-.816zM4.855 18.546c.24-.395 3.004-4.936 8.348-6.613.135-.045.27-.084.405-.12-.26-.585-.54-1.167-.832-1.74C7.17 11.775 1.65 11.7 1.2 11.685v.315c0 2.633.998 5.037 2.655 6.845zm-1.56-8.735c.46.008 5.225.03 10.44-1.415A76.27 76.27 0 0010.2 3.216 10.232 10.232 0 002.295 9.81zm9.56-7.38c.79 1.207 1.558 2.497 2.288 3.855 3.36-1.26 4.785-3.164 4.952-3.394A10.174 10.174 0 0012.856 3.43zm8.478 1.816c-.21.264-1.8 2.293-5.31 3.704.249.515.489 1.035.717 1.56.08.186.16.37.236.555 3.396-.428 6.77.265 7.104.335-.02-2.235-.794-4.29-2.146-5.88z',
            'reddit'    => 'M12 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 01-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 01.042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 014.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 01.14-.197.35.35 0 01.238-.042l2.906.617a1.214 1.214 0 011.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 00-.231.094.33.33 0 000 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 00.029-.463.33.33 0 00-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 00-.232-.095z',
            'discord'   => 'M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189z',
            'twitch'    => 'M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714z',
            'medium'    => 'M13.54 12a6.8 6.8 0 01-6.77 6.82A6.8 6.8 0 010 12a6.8 6.8 0 016.77-6.82A6.8 6.8 0 0113.54 12zM20.96 12c0 3.54-1.51 6.42-3.38 6.42-1.86 0-3.38-2.88-3.38-6.42s1.52-6.42 3.38-6.42 3.38 2.88 3.38 6.42M24 12c0 3.17-.53 5.75-1.19 5.75-.66 0-1.19-2.58-1.19-5.75s.53-5.75 1.19-5.75C23.47 6.25 24 8.83 24 12z',
            'spotify'   => 'M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z',
            'behance'   => 'M6.938 4.503c.702 0 1.34.06 1.92.188.577.13 1.07.33 1.485.61.41.28.733.65.96 1.12.225.47.34 1.05.34 1.73 0 .74-.17 1.36-.507 1.86-.338.5-.837.9-1.502 1.22.906.26 1.576.72 2.022 1.37.448.66.665 1.45.665 2.36 0 .75-.13 1.39-.41 1.93-.28.55-.67 1-1.16 1.35-.48.348-1.05.6-1.67.767-.63.165-1.27.25-1.95.25H0V4.51h6.938v-.007zM16.94 16.665c.44.428 1.073.643 1.894.643.59 0 1.1-.148 1.53-.447.424-.29.68-.61.78-.94h2.588c-.403 1.28-1.048 2.2-1.9 2.75-.85.56-1.884.83-3.08.83-.837 0-1.584-.13-2.272-.4a4.948 4.948 0 01-1.72-1.14 5.1 5.1 0 01-1.077-1.77c-.253-.69-.373-1.45-.373-2.27 0-.803.135-1.54.403-2.23.27-.7.644-1.28 1.12-1.79.495-.51 1.063-.895 1.736-1.194s1.4-.433 2.22-.433c.91 0 1.69.164 2.38.523.67.34 1.22.82 1.66 1.4.44.586.75 1.26.94 2.02.19.75.25 1.54.21 2.38h-7.69c.055 1.023.47 1.84.91 2.267zM3.577 8.377c0-.41-.086-.74-.258-1.01-.172-.27-.41-.47-.68-.61-.283-.13-.586-.21-.94-.24a8.018 8.018 0 00-1.008-.06H3.59v3.93H.93c-.372 0-.74-.026-1.087-.087-.36-.06-.67-.17-.94-.33a1.697 1.697 0 01-.638-.62c-.16-.27-.24-.62-.24-1.04 0-.06.006-.117.013-.173.007-.057.017-.107.027-.157zM9.58 5.89c0-.316-.06-.585-.17-.82a1.45 1.45 0 00-.46-.57 1.86 1.86 0 00-.69-.33c-.26-.06-.55-.1-.85-.1H3.59v3.58h3.77c.34 0 .65-.03.96-.1s.55-.18.78-.34c.22-.16.393-.37.52-.64.12-.27.18-.6.18-1z',
            'vimeo'     => 'M23.977 6.416c-.105 2.338-1.739 5.543-4.894 9.609-3.268 4.247-6.026 6.37-8.29 6.37-1.409 0-2.578-1.294-3.553-3.881L5.322 11.4C4.603 8.816 3.834 7.522 3.01 7.522c-.179 0-.806.378-1.881 1.132L0 7.197c1.185-1.044 2.351-2.084 3.501-3.128C5.08 2.701 6.266 1.984 7.055 1.91c1.867-.18 3.016 1.1 3.447 3.838.465 2.953.789 4.789.971 5.507.539 2.45 1.131 3.674 1.776 3.674.502 0 1.256-.796 2.265-2.385 1.004-1.589 1.54-2.797 1.612-3.628.144-1.371-.395-2.061-1.614-2.061-.574 0-1.167.121-1.777.391 1.186-3.868 3.434-5.757 6.762-5.637 2.473.06 3.628 1.664 3.493 4.797l-.013.01z',
            'website'   => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z',
            'email'     => 'M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636H1.636C.732 21.002 0 20.27 0 19.366V5.457c0-.904.732-1.636 1.636-1.636h20.727c.904 0 1.636.732 1.636 1.636zm-2.07.195L12 13.423 2.07 5.652A.364.364 0 001.636 6v12.727c0 .2.164.364.364.364h20c.2 0 .364-.164.364-.364V6a.364.364 0 00-.434-.348z',
        ];

        if (!isset($paths[$icon])) {
            return '';
        }

        return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="' . $esc_color . '"><path d="' . $paths[$icon] . '"/></svg>';
    }

    private function get_social_icon_file_url($icon, $color, $size = 48) {
        $esc_color = sanitize_hex_color($color) ?: '#333333';
        $hex       = ltrim($esc_color, '#');
        $safe_icon = sanitize_file_name($icon);

        $upload_dir = wp_upload_dir();
        $cache_dir  = $upload_dir['basedir'] . '/efb-icons/' . $hex;

        // 1. Check cached PNG (best for all email clients)
        $png_file = $cache_dir . '/' . $safe_icon . '.png';
        $png_url  = $upload_dir['baseurl'] . '/efb-icons/' . $hex . '/' . $safe_icon . '.png';
        if (file_exists($png_file)) {
            return $png_url;
        }

        $svg = $this->get_social_icon_svg($icon, $esc_color, $size);
        if (!$svg) {
            return '';
        }

        if (!is_dir($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }

        $svg_xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                 . str_replace('<svg ', '<svg xmlns="http://www.w3.org/2000/svg" ', $svg);

        // 2. Try Imagick SVG→PNG (exact icon shape, transparent background)
        if (class_exists('Imagick')) {
            try {
                $im = new \Imagick();
                $im->setResolution(150, 150);
                $im->setBackgroundColor(new \ImagickPixel('transparent'));
                $im->readImageBlob($svg_xml);
                $im->setImageFormat('png32');
                $im->resizeImage($size, $size, \Imagick::FILTER_LANCZOS, 1);
                $im->writeImage($png_file);
                $im->destroy();
                if (file_exists($png_file)) {
                    return $png_url;
                }
            } catch (\Exception $e) {
                // Imagick failed
            }
        }

        // 3. Save as SVG file — email clients fetch external <img src="url.svg">
        //    via their image proxy (Gmail, Outlook.com, Yahoo all proxy external images)
        $svg_file = $cache_dir . '/' . $safe_icon . '.svg';
        $svg_url  = $upload_dir['baseurl'] . '/efb-icons/' . $hex . '/' . $safe_icon . '.svg';
        if (!file_exists($svg_file)) {
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ($wp_filesystem) {
                $wp_filesystem->put_contents($svg_file, $svg_xml, FS_CHMOD_FILE);
            }
        }
        if (file_exists($svg_file)) {
            return $svg_url;
        }

        return '';
    }

    private function wrap_builder_template_html($content) {

        $direction = is_rtl() ? 'rtl' : 'ltr';

        $content = preg_replace('/<meta\s[^>]*\/?>/i', '', $content);

        $firstElement = strpos($content, '<table');
        if ($firstElement === false) {
            $firstElement = strpos($content, '<div');
        }
        if ($firstElement !== false && $firstElement > 0) {

            $beforeText = trim(substr($content, 0, $firstElement));
            if (!preg_match('/^<[a-z]/i', $beforeText)) {
                $content = substr($content, $firstElement);
            }
        }

        $content = preg_replace('/<!--\[if\s+mso\]&gt;.*?&lt;!\[endif\]-->/is', '', $content);

        $content = trim($content);

        $bgColor = '#f8f9fa';
        if (preg_match("/background-color:\s*([^;'\"]+)/i", $content, $bgMatch)) {
            $bgColor = trim($bgMatch[1]);
        }

        $contentWidth = 600;
        if (preg_match("/efb-email-wrapper[^>]*max-width:\s*(\d+)/i", $content, $wMatch)) {
            $contentWidth = intval($wMatch[1]);
        }

        $mso_width = intval($contentWidth);
        $content = preg_replace(
            '/(<div\s[^>]*efb-email-wrapper[^>]*>)/i',
            '<!--[if mso]><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="' . $mso_width . '" align="center"><tr><td><![endif]-->' . "\n$1",
            $content,
            1
        );

        $content = preg_replace(
            '#(</table>\s*</div>)(\s*</td>)#i',
            "$1\n<!--[if mso]></td></tr></table><![endif]-->$2",
            $content,
            1
        );

        $safe_bg = esc_attr($bgColor);
        $safe_dir = esc_attr($direction);

        return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
            <html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
            <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <!--[if gte mso 9]><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
            <style type="text/css">
            body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
            table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
            img { -ms-interpolation-mode: bicubic; border: 0; }
            body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            table { border-collapse: collapse !important; }
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

    private function get_settings_efficiently() {
        if (class_exists('Emsfb') && method_exists('Emsfb', 'get_setting_Emsfb')) {
            return Emsfb::get_setting_Emsfb();
        }

        if (function_exists('get_setting_Emsfb')) {
            return get_setting_Emsfb();
        }

        return null;
    }

    public function wpdocs_set_html_mail_content_type() {
        return 'text/html';
    }

    public static function getInstance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    public static function quickSend($to, $subject, $message, $state = 'newMessage') {
        $handler = self::getInstance();
        $link = home_url();
        return $handler->send_email_state_new($to, $subject, $message, false, $state, $link);
    }
}
