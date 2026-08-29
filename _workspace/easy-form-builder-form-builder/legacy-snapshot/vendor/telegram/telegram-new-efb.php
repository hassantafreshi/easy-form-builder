<?php
/**
 * =====================================================================
 * Telegram Addon - Class telegramsendefb (Advanced Features)
 * =====================================================================
 *
 * کلاس پیشرفته مدیریت تلگرام
 * این کلاس ویژگی‌های پیشرفته تلگرام شامل onboarding، business notifications و مدیریت کاربران را ارائه می‌دهد
 *
 * Advanced Telegram management class
 * This class provides advanced Telegram features including onboarding, business notifications and user management
 *
 * @package    Easy_Form_Builder
 * @subpackage Telegram_Addon
 * @version    1.0.0
 * @author     EFB Team
 *
 * =====================================================================
 * فایل‌های مرتبط / Related Files:
 * - class-Emsfb-telegram.php: کلاس telegramlistefb برای UI ادمین
 * - assets/js/telegram-efb.js: رابط کاربری جاوااسکریپت
 * - onboarding.html: صفحه onboarding
 * =====================================================================
 *
 * جداول دیتابیس / Database Tables:
 * - {prefix}_emsfb_telegram_sent_list: لیست پیام‌های ارسالی
 * - {prefix}_emsfb_telegram_contact: تنظیمات تلگرام برای هر فرم
 * - {prefix}_emsfb_telegram_users: مدیریت Chat ID کاربران
 *
 * AJAX Actions:
 * - send_telegram_test_efb: ارسال پیام تست از پنل ادمین
 * - verify_telegram_bot_efb: تایید Bot Token
 * - telegram_activate_efb: فعال‌سازی onboarding
 * - send_business_telegram_efb: ارسال پیام بیزنیس به کاربر
 * - telegram_check_status_efb: بررسی وضعیت فعال‌سازی
 *
 * Hooks:
 * - efb_send_telegram_notification: اکشن ارسال اعلان تلگرام
 * - efb_handle_telegram_notification: فیلتر مدیریت اعلان
 * =====================================================================
 */

namespace Emsfb;

// جلوگیری از دسترسی مستقیم به فایل / Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// استفاده از تابع دیباگ مشترک / Use shared debug function
if ( ! function_exists( 'Emsfb\efb_telegram_debug_log' ) ) {
	function efb_telegram_debug_log( $message ) {
		$debug = defined( 'EFB_DEBUG' ) ? EFB_DEBUG : ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		if ( $debug ) {
			error_log( $message );
		}
	}
}

/**
 * Class telegramsendefb
 *
 * کلاس پیشرفته ارسال پیام تلگرام
 * Advanced Telegram message sending class
 *
 * @since 1.0.0
 */
class telegramsendefb {

    /**
     * Constructor - سازنده کلاس
     *
     * مقداردهی اولیه، ثبت اکشن‌ها و هوک‌ها
     * Initialize, register actions and hooks
     *
     * @since 1.0.0
     */
    public function __construct() {
        efb_telegram_debug_log('[EFB Telegram Advanced] telegramsendefb::__construct() - START');

        // ایجاد جداول دیتابیس (فقط در صورت نیاز) / Create database tables (only if needed)
        $this->maybe_create_telegram_tables_efb();

        // ثبت هوک برای اعلان‌های تلگرام / Register hook for telegram notifications
        // توجه: اکشن‌های AJAX ادمین به class-Emsfb-telegram.php منتقل شده‌اند
        // Note: Admin AJAX actions have been moved to class-Emsfb-telegram.php
        add_action('efb_send_telegram_notification', [$this, 'telegram_ready_for_send_efb'], 10, 4);

        // ثبت فیلتر برای مدیریت کامل فرآیند اعلان
        // Register filter for handling complete telegram notification process
        add_filter('efb_handle_telegram_notification', [$this, 'handle_telegram_notification_efb'], 10, 8);

        // ثبت هوک برای یکپارچه‌سازی سرویس‌های ثالث (فرم ارسال / پاسخ دریافتی)
        // Register hook for 3rd party integration (form submit / received reply)
        add_action('efb_3rd_party_telegram_notify', [$this, 'efb_handle_form_event_notification'], 10, 1);

        efb_telegram_debug_log('[EFB Telegram Advanced] telegramsendefb::__construct() - END');
    }

    /**
     * ایجاد جداول دیتابیس تلگرام
     * Create Telegram database tables
     *
     * سه جدول ایجاد می‌کند:
     * 1. emsfb_telegram_sent_list: لیست پیام‌های ارسالی
     * 2. emsfb_telegram_contact: تنظیمات تلگرام برای هر فرم
     * 3. emsfb_telegram_users: مدیریت Chat ID کاربران
     *
     * Creates three tables:
     * 1. emsfb_telegram_sent_list: Sent messages list
     * 2. emsfb_telegram_contact: Telegram settings for each form
     * 3. emsfb_telegram_users: User Chat ID management
     *
     * @since 1.0.0
     * @return void
     */
    /**
     * ایجاد جداول دیتابیس تلگرام (فقط در صورت نیاز - مانند PayPal)
     * Create Telegram database tables (only if needed - like PayPal pattern)
     *
     * @since 1.0.0
     * @return void
     */
    public function maybe_create_telegram_tables_efb() {
        // بررسی نسخه جدول / Check table version
        if ( get_option( 'emsfb_telegram_adv_table_version', '0' ) === '1.0' ) {
            return;
        }

        efb_telegram_debug_log('[EFB Telegram Advanced] Creating tables…');

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // متغیرهای نام جدول / Table name variables
        $table_name = $wpdb->prefix . 'emsfb_telegram_sent_list';
        $table_name_contact = $wpdb->prefix . 'emsfb_telegram_contact';
        $table_name_users = $wpdb->prefix . 'emsfb_telegram_users';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `chat_id` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `status` varchar(20) NOT NULL,
            `date` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            `form_id` int(11) NOT NULL,
            `message_id` varchar(50) DEFAULT NULL,
            `error_message` text DEFAULT NULL,
            `phone_number` varchar(20) DEFAULT NULL,
            `by` varchar(50) DEFAULT 'admin',
            PRIMARY KEY (`id`),
            KEY `form_id` (`form_id`),
            KEY `status` (`status`),
            KEY `phone_number` (`phone_number`)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // جدول ۲ / Table 2
        $sql = "CREATE TABLE IF NOT EXISTS $table_name_contact (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `admin_chat_ids` text NOT NULL,
            `form_id` int(11) NOT NULL,
            `bot_token` varchar(255) NOT NULL,
            `received_message_noti_user` text NOT NULL,
            `new_message_noti_user` text NOT NULL,
            `new_message_noti_admin` text NOT NULL,
            `new_response_noti` text NOT NULL,
            `onboarding_token` varchar(50) DEFAULT NULL,
            `is_active` tinyint(1) DEFAULT 1,
            `date` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (`id`)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // جدول ۳ / Table 3
        // Table 3: User Chat ID management
        // =====================================================
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] Creating table: ' . $table_name_users);
        $sql = "CREATE TABLE IF NOT EXISTS $table_name_users (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `chat_id` varchar(255) NOT NULL,
            `phone_number` varchar(20) DEFAULT NULL,
            `username` varchar(100) DEFAULT NULL,
            `first_name` varchar(100) DEFAULT NULL,
            `last_name` varchar(100) DEFAULT NULL,
            `verification_code` varchar(20) DEFAULT NULL,
            `is_verified` tinyint(1) DEFAULT 0,
            `is_active` tinyint(1) DEFAULT 1,
            `last_activity` datetime DEFAULT CURRENT_TIMESTAMP,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `chat_id` (`chat_id`)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // به‌روزرسانی وضعیت افزونه و نسخه جدول / Update addon status and table version
        update_option('emsfb_addon_AdnTLG', 2);
        update_option('emsfb_telegram_adv_table_version', '1.0');

        efb_telegram_debug_log('[EFB Telegram Advanced] All tables created/verified');
    }

    /**
     * دریافت لیست پیام‌های ارسالی تلگرام
     * Get list of sent Telegram messages
     *
     * @since 1.0.0
     * @param int $form_id شناسه فرم (0 برای همه) / Form ID (0 for all)
     * @return array لیست پیام‌ها / Messages list
     */
    public function get_telegram_sent_list_efb($form_id) {
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] get_telegram_sent_list_efb() - Form ID: ' . $form_id);

        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_telegram_sent_list';

        if ($form_id == 0) {
            // دریافت همه پیام‌ها / Get all messages
            $sql = "SELECT * FROM $table_name ORDER BY id DESC";
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] get_telegram_sent_list_efb() - Getting all messages');
        } else {
            // دریافت پیام‌های یک فرم خاص / Get messages for specific form
            $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE form_id = %d ORDER BY id DESC", $form_id);
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] get_telegram_sent_list_efb() - Getting messages for form: ' . $form_id);
        }

        $result = $wpdb->get_results($sql);
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] get_telegram_sent_list_efb() - Found ' . count($result) . ' messages');

        return $result;
    }

    /**
     * حذف یک پیام از لیست ارسالی
     * Delete a single message from sent list
     *
     * @since 1.0.0
     * @param int $id شناسه رکورد / Record ID
     * @return void
     */
    public function delete_telegram_efb($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_telegram_sent_list';
        $sql = $wpdb->prepare("DELETE FROM $table_name WHERE id = %d", $id);
        $wpdb->query($sql);
    }

    /**
     * ارسال پیام تلگرام
     * Send Telegram message
     *
     * این متد پیام را به یک یا چند Chat ID می‌فرستد و نتیجه را در دیتابیس ذخیره می‌کند
     * This method sends message to one or more Chat IDs and saves result in database
     *
     * @since 1.0.0
     * @param string|array $chat_ids شناسه‌های چت (کاما جدا یا آرایه) / Chat IDs (comma separated or array)
     * @param string $message متن پیام / Message text
     * @param int $form_id شناسه فرم / Form ID
     * @param string $bot_token توکن بات / Bot token
     * @return array نتیجه ارسال / Send result
     */
    public function send_telegram_efb($chat_ids, $message, $form_id, $bot_token) {
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] ========================================');
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - START');
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - Form ID: ' . $form_id);
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - Message length: ' . strlen($message));
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - Bot Token: ' . (!empty($bot_token) ? '***SET***' : '***EMPTY***'));

        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_telegram_sent_list';

        // تمیز کردن پیام - جایگزینی کاراکترهای خط جدید
        // Clean message - replace newline characters
        // "\\n" = literal \n from DB, "\\\\n" = double-escaped \\n, "@n#" = custom marker
        $message = str_replace(["\\n", "\\\\n", "@n#"], ["\n", "\n", "\n"], $message);
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - Message cleaned');

        // اگر form_id صفر باشد، از user_id منفی استفاده کن
        // If form_id is 0, use negative user_id
        if ($form_id == 0) {
            $form_id = get_current_user_id() * -1;
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - Using user-based form_id: ' . $form_id);
        }

        // تبدیل chat_ids به آرایه / Convert chat_ids to array
        if (is_string($chat_ids)) {
            $chat_ids = explode(',', $chat_ids);
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - Converted string to array');
        }
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - Total chat IDs: ' . count($chat_ids));

        $success_count = 0;
        $error_messages = [];

        // ارسال به هر Chat ID / Send to each Chat ID
        foreach ($chat_ids as $index => $chat_id) {
            $chat_id = trim($chat_id);
            if (empty($chat_id)) {
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - Skipping empty chat_id at index: ' . $index);
                continue;
            }

            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - Sending to chat_id: ' . $chat_id);

            // ارسال به API تلگرام / Send to Telegram API
            $result = $this->send_to_telegram_api($bot_token, $chat_id, $message);

            if ($result['success']) {
                $status = 'sent';
                $message_id = $result['message_id'];
                $error_msg = null;
                $success_count++;
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - SUCCESS for chat_id: ' . $chat_id . ' (message_id: ' . $message_id . ')');
            } else {
                $status = 'failed';
                $message_id = null;
                $error_msg = $result['error'];
                $error_messages[] = "Chat ID $chat_id: " . $result['error'];
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - FAILED for chat_id: ' . $chat_id . ' - Error: ' . $result['error']);
            }

            // ذخیره در دیتابیس / Save to database
            $sql = $wpdb->prepare(
                "INSERT INTO $table_name (chat_id, message, status, form_id, message_id, error_message) VALUES (%s, %s, %s, %d, %s, %s)",
                $chat_id,
                $message,
                $status,
                $form_id,
                $message_id,
                $error_msg
            );
            $wpdb->query($sql);
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - Logged to database');
        }

        $result_array = [
            'success' => $success_count > 0,
            'sent_count' => $success_count,
            'total_count' => count($chat_ids),
            'errors' => $error_messages
        ];

        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - Final result: ' . json_encode($result_array));
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_telegram_efb() - END');
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] ========================================');

        return $result_array;
    }

    /**
     * ارسال به API تلگرام
     * Send to Telegram API
     *
     * این متد درخواست HTTP به API تلگرام می‌فرستد
     * This method sends HTTP request to Telegram API
     *
     * @since 1.0.0
     * @access private
     * @param string $bot_token توکن بات / Bot token
     * @param string $chat_id شناسه چت / Chat ID
     * @param string $message متن پیام / Message text
     * @return array نتیجه API / API result
     */
    public function send_to_telegram_api( $bot_token, $chat_id, $message) {

        if (empty($bot_token) || empty($chat_id)) {
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_to_telegram_api() - Missing bot token or chat ID');
            return [
                'success' => false,
                'error' => 'Bot token or chat ID is missing'
            ];
        }
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_to_telegram_api() - START');
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_to_telegram_api() - Chat ID: ' . $chat_id);

        $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_to_telegram_api() - API URL constructed');

        // تنظیمات پیام / Message settings
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_to_telegram_api() - Request data prepared');

        // ارسال درخواست / Send request
        $response = wp_remote_post($url, [
            'body' => $data,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        // بررسی خطای WordPress / Check WordPress error
        if (is_wp_error($response)) {
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_to_telegram_api() - WP_Error: ' . $response->get_error_message());
            return [
                'success' => false,
                'error' => $response->get_error_message()
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_to_telegram_api() - API Response: ' . json_encode($result));

        // بررسی نتیجه API / Check API result
        if (isset($result['ok']) && $result['ok'] === true) {
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_to_telegram_api() - SUCCESS');
            return [
                'success' => true,
                'message_id' => $result['result']['message_id']
            ];
        } else {
            $error = isset($result['description']) ? $result['description'] : 'Unknown error';
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_to_telegram_api() - FAILED: ' . $error);
            return [
                'success' => false,
                'error' => $error
            ];
        }
    }

    /**
     * بررسی معتبر بودن Bot Token
     * Verify Bot Token validity
     *
     * این متد با فراخوانی getMe از API تلگرام اعتبار توکن را بررسی می‌کند
     * This method checks token validity by calling getMe from Telegram API
     *
     * @since 1.0.0
     * @param string $bot_token توکن بات / Bot token
     * @return array نتیجه بررسی / Verification result
     */
    public function verify_bot_token_efb($bot_token) {
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] verify_bot_token_efb() - START');
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] verify_bot_token_efb() - Bot Token: ' . (!empty($bot_token) ? '***SET***' : '***EMPTY***'));

        // دریافت عبارات زبانی / Get language phrases
        $efbFunction = get_efbFunction();
        $text = ["botTokenEmpty", "invalidToken", "unknownError", "telegramConnectionError"];
        $lang = $efbFunction->text_efb($text);

        // بررسی خالی بودن توکن / Check empty token
        if (empty($bot_token)) {
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] verify_bot_token_efb() - EMPTY TOKEN');
            return ['valid' => false, 'error' => $lang['botTokenEmpty']];
        }

        $url = "https://api.telegram.org/bot{$bot_token}/getMe";
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] verify_bot_token_efb() - Calling getMe API');

        $response = wp_remote_get($url, ['timeout' => 15]);

        // بررسی خطای اتصال / Check connection error
        if (is_wp_error($response)) {
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] verify_bot_token_efb() - CONNECTION ERROR: ' . $response->get_error_message());
            return [
                'valid' => false,
                'error' => $lang['telegramConnectionError']
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] verify_bot_token_efb() - API Response: ' . json_encode($result));

        // بررسی نتیجه / Check result
        if (isset($result['ok']) && $result['ok'] === true) {
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] verify_bot_token_efb() - VALID - Bot: ' . $result['result']['username']);
            return [
                'valid' => true,
                'bot_info' => $result['result']
            ];
        } else {
            $error = isset($result['description']) ? $result['description'] : 'Invalid token';
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] verify_bot_token_efb() - INVALID: ' . $error);
            return [
                'valid' => false,
                'error' => $error
            ];
        }
    }


    /**
     * دریافت تنظیمات تلگرام برای یک فرم خاص
     * Get Telegram contact settings for a specific form
     *
     * @since 1.0.0
     * @param int $form_id شناسه فرم / Form ID
     * @return object|null تنظیمات فرم / Form settings
     */
    public function get_telegram_contact_efb($form_id) {
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] get_telegram_contact_efb() - Form ID: ' . $form_id);

        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_telegram_contact';
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE form_id = %d", $form_id);
        $result = $wpdb->get_row($sql);

        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] get_telegram_contact_efb() - Found: ' . ($result ? 'YES' : 'NO'));

        // تنظیمات global از class-Emsfb-telegram.php (ذخیره شده در wp_options)
        // Global settings from class-Emsfb-telegram.php (saved in wp_options)
        $global_token   = get_option('emsfb_telegram_bot_token', '');
        $global_chat_id = get_option('emsfb_telegram_chat_id', '');

        if ($result) {
            // رکورد per-form وجود دارد - فقط فیلدهای خالی را از global پر کن
            // Per-form record exists - only fill empty fields from global
            if (empty($result->bot_token) && !empty($global_token)) {
                $result->bot_token = $global_token;
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] get_telegram_contact_efb() - bot_token filled from wp_options');
            }
            if (empty($result->admin_chat_ids) && !empty($global_chat_id)) {
                $result->admin_chat_ids = $global_chat_id;
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] get_telegram_contact_efb() - admin_chat_ids filled from wp_options');
            }
        } else if (!empty($global_token)) {
            // رکوردی نیست ولی تنظیمات global هست - شیء بساز با قالب‌های پیش‌فرض
            // No record but global settings exist - build object with default templates
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] get_telegram_contact_efb() - No DB record, using wp_options + default templates');

            // قالب‌های پیش‌فرض با newline واقعی / Default templates with real newlines
            $site_name = get_bloginfo('name');
            $default_new_msg     = "A New Message has been Received.\nConfirmation Code: [confirmation_code]\nURL: [link_response]";
            $default_received    = "We have received your message.\nConfirmation Code: [confirmation_code]\nURL: [link_response]";
            $default_response    = "New Response\nConfirmation Code: [confirmation_code]\nURL: [link_response]";

            $result = (object) array(
                'id'                         => 0,
                'form_id'                    => $form_id,
                'bot_token'                  => $global_token,
                'admin_chat_ids'             => $global_chat_id,
                'received_message_noti_user' => $default_received,
                'new_message_noti_user'      => $default_new_msg,
                'new_message_noti_admin'     => $default_new_msg,
                'new_response_noti'          => $default_response,
            );
        }

        return $result;
    }

    /**
     * اضافه یا آپدیت تنظیمات تلگرام برای یک فرم
     * Add or update Telegram contact settings for a form
     *
     * این متد ابتدا چک می‌کند که آیا رکورد برای این فرم وجود دارد یا خیر
     * This method first checks if a record exists for this form
     *
     * @since 1.0.0
     * @param int $form_id شناسه فرم / Form ID
     * @param string $admin_chat_ids لیست Chat ID های ادمین / Admin Chat IDs list
     * @param string $bot_token توکن بات / Bot token
     * @param string $received_message_noti_user پیام تایید دریافت / Received message notification
     * @param string $new_message_noti_user پیام جدید به کاربر / New message notification to user
     * @param string $new_message_noti_admin پیام جدید به ادمین / New message notification to admin
     * @param string $new_response_noti پیام پاسخ جدید / New response notification
     * @return int شناسه رکورد / Record ID
     */
    public function add_telegram_contact_efb($form_id, $admin_chat_ids, $bot_token, $received_message_noti_user, $new_message_noti_user, $new_message_noti_admin, $new_response_noti) {
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] add_telegram_contact_efb() - START');
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] add_telegram_contact_efb() - Form ID: ' . $form_id);
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] add_telegram_contact_efb() - Chat IDs: ' . $admin_chat_ids);
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] add_telegram_contact_efb() - Bot Token: ' . (!empty($bot_token) ? '***SET***' : '***EMPTY***'));

        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_telegram_contact';

        // بررسی وجود رکورد / Check if record exists
        $sql = $wpdb->prepare("SELECT * FROM $table_name WHERE form_id = %d", $form_id);
        $result = $wpdb->get_results($sql);

        if (count($result) == 0) {
            // اضافه کردن رکورد جدید / Add new record
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] add_telegram_contact_efb() - Inserting NEW record');
            $sql = $wpdb->prepare(
                "INSERT INTO $table_name (form_id, admin_chat_ids, bot_token, received_message_noti_user, new_message_noti_user, new_message_noti_admin, new_response_noti) VALUES (%d, %s, %s, %s, %s, %s, %s)",
                $form_id,
                $admin_chat_ids,
                $bot_token,
                $received_message_noti_user,
                $new_message_noti_user,
                $new_message_noti_admin,
                $new_response_noti
            );
            $wpdb->query($sql);
            $insert_id = $wpdb->insert_id;
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] add_telegram_contact_efb() - New record ID: ' . $insert_id);
            return $insert_id;
        } else {
            // آپدیت رکورد موجود / Update existing record
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] add_telegram_contact_efb() - Updating EXISTING record ID: ' . $result[0]->id);
            $sql = $wpdb->prepare(
                "UPDATE $table_name SET admin_chat_ids = %s, bot_token = %s, received_message_noti_user = %s, new_message_noti_user = %s, new_message_noti_admin = %s, new_response_noti = %s WHERE form_id = %d",
                $admin_chat_ids,
                $bot_token,
                $received_message_noti_user,
                $new_message_noti_user,
                $new_message_noti_admin,
                $new_response_noti,
                $form_id
            );
            $wpdb->query($sql);
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] add_telegram_contact_efb() - Record updated');
            return $result[0]->id;
        }
    }

    /**
     * حذف تنظیمات تلگرام برای یک فرم
     * Delete Telegram contact settings for a form
     *
     * @since 1.0.0
     * @param int $form_id شناسه فرم / Form ID
     * @return void
     */
    public function delete_telegram_contact_efb($form_id) {
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] delete_telegram_contact_efb() - Form ID: ' . $form_id);

        global $wpdb;
        $table_name = $wpdb->prefix . 'emsfb_telegram_contact';
        $sql = $wpdb->prepare("DELETE FROM $table_name WHERE form_id = %d", $form_id);
        $result = $wpdb->query($sql);

        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] delete_telegram_contact_efb() - Deleted rows: ' . $result);
    }

    /**
     * نرمال‌سازی newline در تمپلیت‌های پیام تلگرام
     * Normalize newlines in Telegram message templates
     *
     * مشکل: وقتی تمپلیت‌ها از طریق WordPress ذخیره می‌شن، wp_magic_quotes
     * بک‌اسلش‌ها رو حذف می‌کنه و \n تبدیل به فقط n می‌شه.
     * این تابع الگوهای مختلف نیولاین رو شناسایی و فیکس می‌کنه.
     *
     * Problem: When templates are saved through WordPress, wp_magic_quotes
     * strips backslashes, turning \n into just n.
     * This function detects and fixes various newline patterns.
     *
     * @since 1.1.0
     * @param string $template متن تمپلیت / Template text
     * @return string تمپلیت با نیولاین‌های صحیح / Template with correct newlines
     */
    private function normalize_template_newlines_efb($template) {
        if (empty($template)) {
            return $template;
        }

        // ۱. اگر قبلاً newline واقعی (0x0a) داره، فقط مارکرها رو فیکس کن
        // 1. If already has real newlines (0x0a), just fix markers
        if (strpos($template, "\n") !== false) {
            $template = str_replace(["\\n", "\\\\n", "@n#"], ["\n", "\n", "\n"], $template);
            return $template;
        }

        // ۲. بک‌اسلش+n لیترال → newline واقعی
        // 2. Literal backslash+n → real newline
        $template = str_replace(["\\n", "\\\\n", "@n#"], ["\n", "\n", "\n"], $template);
        if (strpos($template, "\n") !== false) {
            return $template;
        }

        // ۳. حالت خراب: بک‌اسلش حذف شده و فقط n مونده
        // الگوهای معمول: ".n" یا ":n" یا "enC" (Responsen → Response\n)
        // 3. Corrupted case: backslash stripped, only bare n remains
        // Common patterns: ".n" or ":n" or word-ending "en" before uppercase
        // الگو: بعد از علائم نقطه‌گذاری، یک n تنها قبل از حرف بزرگ یا [
        // Pattern: after punctuation or word-end, a bare n before uppercase/[
        $template = preg_replace('/([.!?:])n([A-Z\[])/u', "$1\n$2", $template);

        // ۴. الگوی خاص: "]n" — بعد از placeholder ها
        // 4. Special pattern: "]n" — after placeholders
        $template = preg_replace('/(\])n([A-Z\[])/u', "$1\n$2", $template);

        // ۵. الگوی خاص: "e/d/g/...nC/U/N" — حرف کوچک + n + حرف بزرگ
        // مثال: "ResponsenConfirmation" → "Response\nConfirmation"
        // 5. Special: lowercase + n + uppercase (e.g., "ResponsenConfirmation")
        $template = preg_replace('/([a-z])n([A-Z])/u', "$1\n$2", $template);

        return $template;
    }

    /**
     * آماده سازی و ارسال اطلاع‌رسانی تلگرام
     * Prepare and send Telegram notification
     *
     * این متد پیام‌های اطلاع‌رسانی تلگرام را بر اساس نوع رویداد آماده و ارسال می‌کند
     * This method prepares and sends Telegram notifications based on event type
     * (منتقل شده از functions.php / Moved from functions.php)
     *
     * @since 1.0.0
     * @param int $form_id شناسه فرم / Form ID
     * @param string $page_url آدرس صفحه / Page URL
     * @param string $state نوع رویداد (fform, resppa, respp, respadmin) / Event type
     * @param string $tracking_code کد رهگیری / Tracking code
     * @return bool موفقیت / Success
     */
    public function telegram_ready_for_send_efb($form_id, $page_url, $state, $tracking_code) {
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - START');
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Form ID: ' . $form_id);
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Page URL: ' . $page_url);
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - State: ' . $state);
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Tracking: ' . $tracking_code);

        // دریافت تنظیمات تلگرام برای فرم / Get telegram settings for form
        $telegram_content = $this->get_telegram_contact_efb($form_id);

        // بررسی وجود تنظیمات / Check settings exist
        // id می‌تواند 0 باشد (شیء مجازی از wp_options) ولی bot_token باید وجود داشته باشد
        // id can be 0 (virtual object from wp_options) but bot_token must exist
        if (empty($telegram_content) || empty($telegram_content->bot_token)) {
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - No config or bot token');
            return false;
        }
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Config loaded');

        // دریافت قالب‌های پیام / Get message templates
        $received_your_message = $telegram_content->received_message_noti_user;
        $new_message = $telegram_content->new_message_noti_user;
        $news_response = $telegram_content->new_response_noti;
        $admin_chat_ids = $telegram_content->admin_chat_ids;

        // بررسی وجود Chat ID / Check Chat ID exists
        if (empty($admin_chat_ids)) {
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - No admin chat IDs');
            return false;
        }
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Chat IDs: ' . $admin_chat_ids);

        // === نرمال‌سازی newline در تمپلیت‌ها (فیکس بک‌اسلش حذف‌شده از DB) ===
        // === Normalize newlines in templates (fix stripped backslashes from DB) ===
        $received_your_message = $this->normalize_template_newlines_efb($received_your_message);
        $new_message           = $this->normalize_template_newlines_efb($new_message);
        $news_response         = $this->normalize_template_newlines_efb($news_response);

        // متغیرهای جایگزین / Replacement variables
        $rp = [
            ['[confirmation_code]', '[link_page]', '[link_domain]', '[link_response]', '[website_name]'],
            [$tracking_code, $page_url, get_site_url(), $page_url . "?track=" . $tracking_code, get_bloginfo('name')]
        ];

        // اعمال جایگزینی در قالب‌ها / Apply replacements to templates
        $received_your_message = str_replace($rp[0], $rp[1], $received_your_message);
        $new_message = str_replace($rp[0], $rp[1], $new_message);
        $news_response = str_replace($rp[0], $rp[1], $news_response);

        // === اضافه کردن تاریخ و ساعت به ابتدای پیام ===
        // === Add date and time to the beginning of every message ===
        $date_header = date('Y-m-d H:i:s') . "\n";

        $received_your_message = $date_header . $received_your_message;
        $new_message           = $date_header . $new_message;
        $news_response         = $date_header . $news_response;

        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Templates processed + date added');

        $result_send_message = false;

        // ارسال بر اساس نوع رویداد / Send based on event type
        if ($state == "fform") {
            // پیام جدید دریافت شد (فرم ارسال شده) / New message received (form submitted)
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Processing: NEW FORM SUBMISSION');
            if (!empty($new_message)) {
                $admin_message = str_replace($page_url . "?track=" . $tracking_code, $page_url . "?track=" . $tracking_code . '&user=admin', $new_message);
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Sending admin notification…');
                $result = $this->send_telegram_efb(
                    $admin_chat_ids,
                    $admin_message,
                    $form_id,
                    $telegram_content->bot_token
                );
                $result_send_message = $result['success'];
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Admin notification: ' . ($result['success'] ? 'SENT' : 'FAILED'));
            }
        } else if ($state == "resppa") {
            // پاسخ ادمین به کاربر / Admin response to user
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Processing: ADMIN RESPONSE');
            if (!empty($news_response)) {
                $admin_message = str_replace($page_url, $page_url . "?track=" . $tracking_code . '&user=admin', $news_response);
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Sending response notification…');
                $result = $this->send_telegram_efb(
                    $admin_chat_ids,
                    $admin_message,
                    $form_id,
                    $telegram_content->bot_token
                );
                $result_send_message = $result['success'];
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Response notification: ' . ($result['success'] ? 'SENT' : 'FAILED'));
            }
        } else if ($state == "respp" || $state == "respadmin") {
            // پاسخ جدید (کاربر یا ادمین) / New response (user or admin)
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Processing: NEW RESPONSE (' . $state . ')');
            if (!empty($news_response)) {
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Sending new response notification…');
                $result = $this->send_telegram_efb(
                    $admin_chat_ids,
                    $news_response,
                    $form_id,
                    $telegram_content->bot_token
                );
                $result_send_message = $result['success'];
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - New response notification: ' . ($result['success'] ? 'SENT' : 'FAILED'));
            }
        } else {
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - Unknown state: ' . $state);
        }

        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] telegram_ready_for_send_efb() - END: ' . ($result_send_message ? 'SUCCESS' : 'FAILED/NO_ACTION'));
        return $result_send_message;
    }

    /**
     * Handler برای پردازش کامل اطلاع‌رسانی تلگرام
     * Handler for complete telegram notification process
     * (منتقل شده از functions.php / Moved from functions.php)
     *
     * @since 1.0.0
     * @param mixed $default_result نتیجه پیش‌فرض / Default result
     * @param string $form_type نوع فرم / Form type
     * @param string $track_id شناسه رهگیری / Track ID
     * @param int $form_id شناسه فرم / Form ID
     * @param array $form_obj آبجکت فرم / Form object
     * @param array $form_values مقادیر فرم / Form values
     * @param array $settings تنظیمات / Settings
     * @return bool موفقیت / Success
     */
    public function handle_telegram_notification_efb($default_result, $form_type, $track_id, $form_id, $form_obj, $form_values, $settings) {
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] handle_telegram_notification_efb() - START');
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] handle_telegram_notification_efb() - Form Type: ' . $form_type);
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] handle_telegram_notification_efb() - Track ID: ' . $track_id);
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] handle_telegram_notification_efb() - Form ID: ' . $form_id);

        try {
            // بررسی اینکه آیا تلگرام باید ارسال شود / Check if telegram should be sent
            if (!$this->should_send_telegram_efb($form_obj, $settings, $form_type)) {
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] handle_telegram_notification_efb() - Notification skipped - conditions not met');
                return false;
            }

            // ارسال اطلاع‌رسانی تلگرام / Send telegram notification
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] handle_telegram_notification_efb() - Conditions met, sending…');
            $telegram_result = $this->send_smart_telegram_efb($form_type, $track_id, $form_id, $form_obj, $settings);

            if ($telegram_result) {
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] handle_telegram_notification_efb() - SUCCESS for: ' . $form_type);
                return true;
            } else {
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] handle_telegram_notification_efb() - FAILED for: ' . $form_type);
                return false;
            }

        } catch (Exception $e) {
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] handle_telegram_notification_efb() - EXCEPTION: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * بررسی هوشمند برای ارسال اطلاع‌رسانی تلگرام
     * Smart check for Telegram notification
     * (منتقل شده از functions.php / Moved from functions.php)
     *
     * @since 1.0.0
     * @access private
     * @param array $form_obj آبجکت فرم / Form object
     * @param array $settings تنظیمات / Settings
     * @param string $form_type نوع فرم / Form type
     * @return bool آیا ارسال شود / Should send
     */
    private function should_send_telegram_efb($form_obj, $settings, $form_type) {
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] should_send_telegram_efb() - Checking conditions…');

        // بررسی فعال بودن افزونه تلگرام / Check if Telegram addon is active
        $addon_status = intval(get_option('emsfb_addon_AdnTLG', 0));
        if ($addon_status !== 2) {
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] should_send_telegram_efb() - Addon not active (status: ' . $addon_status . ')');
            return false;
        }
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] should_send_telegram_efb() - Addon is active');

        // بررسی‌های مرتبط با فرم / Form-specific checks
        if ($form_obj && is_array($form_obj) && isset($form_obj[0])) {
            $form_data = $form_obj[0];
            if (isset($form_data['telegramNoti']) && $form_data['telegramNoti'] === false) {
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] should_send_telegram_efb() - Telegram disabled for this form');
                return false;
            }
        }

        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] should_send_telegram_efb() - All conditions met for: ' . $form_type);
        return true;
    }

    /**
     * ارسال هوشمند تلگرام
     * Smart Telegram sender
     * (منتقل شده از functions.php / Moved from functions.php)
     *
     * @since 1.0.0
     * @access private
     * @param string $form_type نوع فرم / Form type
     * @param string $track_id شناسه رهگیری / Track ID
     * @param int $form_id شناسه فرم / Form ID
     * @param array $form_obj آبجکت فرم / Form object
     * @param array $settings تنظیمات / Settings
     * @return bool موفقیت / Success
     */
    private function send_smart_telegram_efb($form_type, $track_id, $form_id, $form_obj, $settings) {
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_smart_telegram_efb() - START');
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_smart_telegram_efb() - Form Type: ' . $form_type);

        try {
            // دریافت URL صفحه / Get page URL
            $page_url = isset($_SERVER['HTTP_REFERER']) ? sanitize_url($_SERVER['HTTP_REFERER']) : get_site_url();
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_smart_telegram_efb() - Page URL: ' . $page_url);

            // تبدیل نوع فرم به نوع تلگرام / Convert form type to telegram type
            $telegram_type = $this->get_telegram_type_by_form_type_efb($form_type);
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_smart_telegram_efb() - Telegram Type: ' . $telegram_type);

            // استفاده از تابع داخلی / Use internal function
            $result = $this->telegram_ready_for_send_efb($form_id, $page_url, $telegram_type, $track_id);

            if ($result) {
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_smart_telegram_efb() - Direct notification sent');
                return true;
            } else {
                efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_smart_telegram_efb() - Direct notification failed');
                return false;
            }

        } catch (Exception $e) {
            efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] send_smart_telegram_efb() - EXCEPTION: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * تبدیل نوع فرم به نوع اطلاع‌رسانی تلگرام
     * Convert form type to Telegram notification type
     * (منتقل شده از functions.php / Moved from functions.php)
     *
     * @since 1.0.0
     * @access private
     * @param string $form_type نوع فرم / Form type
     * @return string نوع تلگرام / Telegram type
     */
    private function get_telegram_type_by_form_type_efb($form_type) {
        // مپ نوع فرم به نوع تلگرام / Map form type to telegram type
        $telegram_types = [
            'form' => 'fform',
            'register' => 'register',
            'login' => 'login',
            'subscribe' => 'subscribe',
            'survey' => 'survey'
        ];

        $result = $telegram_types[$form_type] ?? 'fform';
        efb_telegram_debug_log('[EFB Telegram Advanced DEBUG] get_telegram_type_by_form_type_efb() - ' . $form_type . ' => ' . $result);

        return $result;
    }

    /**
     * مدیریت رویداد فرم برای اطلاع‌رسانی تلگرام (تابع مستقل)
     * Handle form event for Telegram notification (standalone function)
     *
     * این متد از طریق اکشن وردپرس efb_3rd_party_telegram_notify فراخوانی می‌شود
     * و تمام شرایط لازم (AdnTLG, telegram_enabled, telegramnoti) را بررسی کرده
     * و در صورت برقراری، پیام تلگرام مناسب را ارسال می‌کند
     *
     * This method is called via WordPress action efb_3rd_party_telegram_notify
     * It checks all required conditions and sends the appropriate Telegram message
     *
     * Supported event_type values:
     * - 'form_submit'     => New form submission (maps to 'fform' state)
     * - 'received_reply'  => Reply/response received (maps to 'respp' state)
     * - 'admin_reply'     => Admin reply to user (maps to 'resppa' state)
     *
     * @since 1.1.0
     * @param array $context {
     *     آرایه اطلاعات رویداد / Event context array
     *
     *     @type string $track_code       کد رهگیری / Tracking code
     *     @type int    $form_id          شناسه فرم / Form ID
     *     @type string $page_url         آدرس صفحه / Page URL
     *     @type string $event_type       نوع رویداد / Event type ('form_submit', 'received_reply', 'admin_reply')
     *     @type array  $submitted_values مقادیر ارسالی فرم / Submitted form values
     *     @type array  $form_fields      ساختار فرم / Form structure fields
     * }
     * @return bool موفقیت ارسال / Send success
     */
    public function efb_handle_form_event_notification($context) {
        efb_telegram_debug_log('[EFB Telegram 3rdParty] efb_handle_form_event_notification() - START');
        efb_telegram_debug_log('[EFB Telegram 3rdParty] Event: ' . ($context['event_type'] ?? 'unknown'));
        efb_telegram_debug_log('[EFB Telegram 3rdParty] Form ID: ' . ($context['form_id'] ?? 0));
        efb_telegram_debug_log('[EFB Telegram 3rdParty] Track: ' . ($context['track_code'] ?? ''));

        // === شرط ۱: بررسی فعال بودن افزونه تلگرام (AdnTLG) ===
        // === Condition 1: Check Telegram addon is active (AdnTLG) ===
        $addon_status = intval(get_option('emsfb_addon_AdnTLG', 0));
        if ($addon_status === 0) {
            efb_telegram_debug_log('[EFB Telegram 3rdParty] SKIP - Addon not installed (AdnTLG=' . $addon_status . ')');
            return false;
        }
        efb_telegram_debug_log('[EFB Telegram 3rdParty] AdnTLG=' . $addon_status . ' (active)');

        // === شرط ۲: بررسی فعال بودن تلگرام بصورت سراسری (telegram_enabled) ===
        // === Condition 2: Check Telegram is globally enabled ===
        $telegram_enabled = get_option('emsfb_telegram_enabled', '0');
        if ($telegram_enabled !== '1') {
            efb_telegram_debug_log('[EFB Telegram 3rdParty] SKIP - Not enabled globally (telegram_enabled=' . $telegram_enabled . ')');
            return false;
        }
        efb_telegram_debug_log('[EFB Telegram 3rdParty] telegram_enabled=1 (active)');

        // === شرط ۳: بررسی فعال بودن telegramnoti در ساختار فرم ===
        // === Condition 3: Check telegramnoti is enabled in form structure ===
        // اگر telegramnoti وجود نداشت (فرم‌های قدیمی)، فرض بر فعال بودن است
        // If telegramnoti not set (legacy forms), default to enabled
        // فقط وقتی SKIP می‌شود که صراحتاً 0 ست شده باشد
        // Only skip if explicitly set to 0
        $form_fields = $context['form_fields'] ?? [];
        $telegramnoti_val = $form_fields[0]['telegramnoti'] ?? null;
        if ($telegramnoti_val === null || intval($telegramnoti_val) === 0) {
            efb_telegram_debug_log('[EFB Telegram 3rdParty] SKIP - telegramnoti explicitly disabled (=0) for this form');
            return false;
        }
        efb_telegram_debug_log('[EFB Telegram 3rdParty] telegramnoti=' . ($telegramnoti_val ?? 'NOT SET (default=enabled)') . ' - proceeding');

        // === نگاشت نوع رویداد به وضعیت تلگرام ===
        // === Map event type to Telegram state ===
        $event_state_map = [
            'form_submit'    => 'fform',     // ارسال فرم جدید / New form submission
            'received_reply' => 'respp',     // دریافت پاسخ کاربر / User reply received
            'admin_reply'    => 'resppa',    // پاسخ ادمین / Admin reply to user
        ];

        $event_type = $context['event_type'] ?? 'form_submit';
        $telegram_state = $event_state_map[$event_type] ?? 'fform';
        efb_telegram_debug_log('[EFB Telegram 3rdParty] Mapped event "' . $event_type . '" => state "' . $telegram_state . '"');

        // === ارسال اطلاع‌رسانی با استفاده از تابع موجود ===
        // === Send notification using existing method ===
        $form_id    = intval($context['form_id'] ?? 0);
        $page_url   = $context['page_url'] ?? get_site_url();
        $track_code = $context['track_code'] ?? '';

        $result = $this->telegram_ready_for_send_efb($form_id, $page_url, $telegram_state, $track_code);

        efb_telegram_debug_log('[EFB Telegram 3rdParty] efb_handle_form_event_notification() - END, result: ' . ($result ? 'SUCCESS' : 'FAILED'));
        return $result;
    }

}

// جلوگیری از ساخت چندباره نمونه / Prevent multiple instantiation
if (!defined('EMSFB_TELEGRAM_SEND_LOADED')) {
    define('EMSFB_TELEGRAM_SEND_LOADED', true);
    $GLOBALS['emsfb_telegram_sender'] = new telegramsendefb();
}
