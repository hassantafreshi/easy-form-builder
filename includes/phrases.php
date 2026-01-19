<?php
/**
 * Easy Form Builder - Addon Phrases Handler
 *
 * This file manages translation phrases for plugin addons.
 * Each addon can register its own phrases through this system.
 *
 * @package Emsfb
 * @since 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
} // No direct access allowed

/**
 * Class EfbAddonPhrases
 *
 * Manages addon-specific translation phrases in a modular and extensible way.
 * Addons can register their phrases and they will be loaded only when needed.
 */
class EfbAddonPhrases {

    /**
     * Singleton instance
     * @var EfbAddonPhrases|null
     */
    private static $instance = null;

    /**
     * Registered addon phrase providers
     * @var array
     */
    private static $addon_providers = [];

    /**
     * Cache for loaded phrases
     * @var array
     */
    private static $phrase_cache = [];

    /**
     * Get singleton instance
     *
     * @return EfbAddonPhrases
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->register_default_addons();
    }

    /**
     * Register default addon phrase providers
     */
    private function register_default_addons() {
        // Register Telegram addon phrases
        self::register_addon('telegram', [__CLASS__, 'get_telegram_phrases']);

        /* // Register SMS addon phrases
        self::register_addon('sms', [__CLASS__, 'get_sms_phrases']);

        // Register Stripe addon phrases
        self::register_addon('stripe', [__CLASS__, 'get_stripe_phrases']);

        // Register PayPal addon phrases
        self::register_addon('paypal', [__CLASS__, 'get_paypal_phrases']);

        // Register Webhook addon phrases
        self::register_addon('webhook', [__CLASS__, 'get_webhook_phrases']); */
    }

    /**
     * Register an addon phrase provider
     *
     * @param string $addon_key Unique addon identifier
     * @param callable $callback Function that returns phrases array
     */
    public static function register_addon($addon_key, $callback) {
        self::$addon_providers[$addon_key] = $callback;
    }

    /**
     * Get phrases for a specific addon
     *
     * @param string $addon_key Addon identifier
     * @param object|null $ac Settings object with custom texts
     * @param bool $state Whether custom texts are available
     * @return array Phrases array
     */
    public static function get_addon_phrases($addon_key, $ac = null, $state = false) {
        // Check cache first
        $cache_key = $addon_key . '_' . ($state ? '1' : '0');
        if (isset(self::$phrase_cache[$cache_key])) {
            return self::$phrase_cache[$cache_key];
        }

        // Check if addon is registered
        if (!isset(self::$addon_providers[$addon_key])) {
            return [];
        }

        // Get phrases from provider
        $phrases = call_user_func(self::$addon_providers[$addon_key], $ac, $state);

        // Cache the result
        self::$phrase_cache[$cache_key] = $phrases;

        return $phrases;
    }

    /**
     * Get phrases for multiple addons
     *
     * @param array $addon_keys Array of addon identifiers
     * @param object|null $ac Settings object
     * @param bool $state Whether custom texts are available
     * @return array Combined phrases array
     */
    public static function get_multiple_addon_phrases($addon_keys, $ac = null, $state = false) {
        $phrases = [];
        foreach ($addon_keys as $key) {
            $phrases = array_merge($phrases, self::get_addon_phrases($key, $ac, $state));
        }
        return $phrases;
    }

    /**
     * Clear phrase cache
     */
    public static function clear_cache() {
        self::$phrase_cache = [];
    }

    /**
     * Get all registered addon keys
     *
     * @return array
     */
    public static function get_registered_addons() {
        return array_keys(self::$addon_providers);
    }

    // ========================================
    // TELEGRAM ADDON PHRASES
    // ========================================

    /**
     * Get Telegram addon phrases
     *
     * @param object|null $ac Settings object
     * @param bool $state Whether custom texts are available
     * @return array
     */
    public static function get_telegram_phrases($ac = null, $state = false) {
        return [
            // === Main Navigation & Headers ===
            /* translators: Settings = configuration tab title */


            /* translators: Test Message = test tab title */
            "test_message" => $state && isset($ac->text->test_message) ? $ac->text->test_message : esc_html__('Test Message', 'easy-form-builder'),

            /* translators: Activity Log = activity tab title */
            "activity" => $state && isset($ac->text->activity) ? $ac->text->activity : esc_html__('Activity Log', 'easy-form-builder'),

            /* translators: Telegram Settings = main settings header */
            "telegram_settings" => $state && isset($ac->text->telegram_settings) ? $ac->text->telegram_settings : esc_html__('Telegram Settings', 'easy-form-builder'),

            /* translators: Telegram Integration = header title */
            "telegram_title" => $state && isset($ac->text->telegram_title) ? $ac->text->telegram_title : esc_html__('Telegram Integration', 'easy-form-builder'),

            /* translators: Real-time Form Notifications = header subtitle */
            "telegram_subtitle" => $state && isset($ac->text->telegram_subtitle) ? $ac->text->telegram_subtitle : esc_html__('Real-time Form Notifications', 'easy-form-builder'),

            /* translators: Introduction text for Telegram settings */
            "telegram_intro" => $state && isset($ac->text->telegram_intro) ? $ac->text->telegram_intro : esc_html__('Configure Telegram notifications to receive instant alerts when forms are submitted. Keep your team informed in real-time.', 'easy-form-builder'),

            // === Status Messages ===
            /* translators: Enabled = status when feature is active */
            "enabled" => $state && isset($ac->text->enabled) ? $ac->text->enabled : esc_html__('Enabled', 'easy-form-builder'),


            /* translators: Status = current state label */
            "status" => $state && isset($ac->text->status) ? $ac->text->status : esc_html__('Status', 'easy-form-builder'),

            /* translators: Notifications are active = status description */
            "notifications_active" => $state && isset($ac->text->notifications_active) ? $ac->text->notifications_active : esc_html__('Notifications are active', 'easy-form-builder'),

            /* translators: Configure settings to enable = status description */
            "configure_to_enable" => $state && isset($ac->text->configure_to_enable) ? $ac->text->configure_to_enable : esc_html__('Configure settings to enable', 'easy-form-builder'),

            // === Bot Configuration ===
            /* translators: Enable Telegram Notifications = toggle label */
            "enable_telegram" => $state && isset($ac->text->enable_telegram) ? $ac->text->enable_telegram : esc_html__('Enable Telegram Notifications', 'easy-form-builder'),

            /* translators: Bot Token = authentication token field label */
            "bot_token" => $state && isset($ac->text->bot_token) ? $ac->text->bot_token : esc_html__('Bot Token', 'easy-form-builder'),

            /* translators: Enter your bot token = placeholder text */
            "enter_bot_token" => $state && isset($ac->text->enter_bot_token) ? $ac->text->enter_bot_token : esc_html__('Enter your bot token', 'easy-form-builder'),

            /* translators: Get your bot token from @BotFather on Telegram = help text */
            "bot_token_help" => $state && isset($ac->text->bot_token_help) ? $ac->text->bot_token_help : esc_html__('Get your bot token from @BotFather on Telegram', 'easy-form-builder'),

            /* translators: Chat ID = chat identifier field label */
            "chat_id" => $state && isset($ac->text->chat_id) ? $ac->text->chat_id : esc_html__('Chat ID', 'easy-form-builder'),

            /* translators: Enter chat ID = placeholder text */
            "enter_chat_id" => $state && isset($ac->text->enter_chat_id) ? $ac->text->enter_chat_id : esc_html__('Enter chat ID', 'easy-form-builder'),

            /* translators: Use @username for channels or numeric ID for private chats = help text */
            "chat_id_help" => $state && isset($ac->text->chat_id_help) ? $ac->text->chat_id_help : esc_html__('Use @username for channels or numeric ID for private chats', 'easy-form-builder'),

            /* translators: Test = button text */
            "test" => $state && isset($ac->text->test) ? $ac->text->test : esc_html__('Test', 'easy-form-builder'),

            // === Buttons & Actions ===
            /* translators: Save Settings = save button text */
            "save_settings" => $state && isset($ac->text->save_settings) ? $ac->text->save_settings : esc_html__('Save Settings', 'easy-form-builder'),


            /* translators: Refresh = refresh button text */
            "refresh" => $state && isset($ac->text->refresh) ? $ac->text->refresh : esc_html__('Refresh', 'easy-form-builder'),

            /* translators: Send Test Message = test button text */
            "send_test_message" => $state && isset($ac->text->send_test_message) ? $ac->text->send_test_message : esc_html__('Send Test Message', 'easy-form-builder'),

            // === Quick Tips ===
            /* translators: Quick Tips = tips section title */
            "quick_tips" => $state && isset($ac->text->quick_tips) ? $ac->text->quick_tips : esc_html__('Quick Tips', 'easy-form-builder'),

            /* translators: Tip 1 = first tip */
            "tip_1" => $state && isset($ac->text->tip_1) ? $ac->text->tip_1 : esc_html__('Create a bot using @BotFather', 'easy-form-builder'),

            /* translators: Tip 2 = second tip */
            "tip_2" => $state && isset($ac->text->tip_2) ? $ac->text->tip_2 : esc_html__('Add your bot to the target chat', 'easy-form-builder'),

            /* translators: Tip 3 = third tip */
            "tip_3" => $state && isset($ac->text->tip_3) ? $ac->text->tip_3 : esc_html__('Use the test feature to verify setup', 'easy-form-builder'),

            /* translators: Tip 4 = fourth tip */
            "tip_4" => $state && isset($ac->text->tip_4) ? $ac->text->tip_4 : esc_html__('Monitor activity for troubleshooting', 'easy-form-builder'),

            // === Test Message Tab ===
            /* translators: Enter your test message here = placeholder */
            "enter_test_message" => $state && isset($ac->text->enter_test_message) ? $ac->text->enter_test_message : esc_html__('Enter your test message here...', 'easy-form-builder'),

            /* translators: Default test message content */
            "default_test_message" => $state && isset($ac->text->default_test_message) ? $ac->text->default_test_message : esc_html__("🎯 Test Message\n\nThis is a test message from Easy Form Builder.\n\n✅ If you receive this message, your Telegram integration is working correctly!", 'easy-form-builder'),

            /* translators: Test Information = info section title */
            "test_info" => $state && isset($ac->text->test_info) ? $ac->text->test_info : esc_html__('Test Information', 'easy-form-builder'),

            /* translators: Test description text */
            "test_description" => $state && isset($ac->text->test_description) ? $ac->text->test_description : esc_html__('Use this tab to send test messages and verify your Telegram bot configuration.', 'easy-form-builder'),

            /* translators: Test warning message */
            "test_warning" => $state && isset($ac->text->test_warning) ? $ac->text->test_warning : esc_html__('Make sure to save your settings before testing!', 'easy-form-builder'),

            // === Activity Log Tab ===
            /* translators: Activity Log = tab title */
            "activity_log" => $state && isset($ac->text->activity_log) ? $ac->text->activity_log : esc_html__('Activity Log', 'easy-form-builder'),

            /* translators: Date = column header */
            "date" => $state && isset($ac->text->date) ? $ac->text->date : esc_html__('Date', 'easy-form-builder'),

            /* translators: No activity found = empty state message */
            "no_activity" => $state && isset($ac->text->no_activity) ? $ac->text->no_activity : esc_html__('No activity found', 'easy-form-builder'),

            // === Help Tab ===
            /* translators: Setting Up Your Bot = help section title */
            "setup_bot" => $state && isset($ac->text->setup_bot) ? $ac->text->setup_bot : esc_html__('Setting Up Your Bot', 'easy-form-builder'),

            /* translators: Step 1: Create a Bot */
            "step_1" => $state && isset($ac->text->step_1) ? $ac->text->step_1 : esc_html__('Create a Bot', 'easy-form-builder'),

            /* translators: Step 1 description */
            "step_1_desc" => $state && isset($ac->text->step_1_desc) ? $ac->text->step_1_desc : esc_html__('Message @BotFather on Telegram and use /newbot command', 'easy-form-builder'),

            /* translators: Step 2: Get Bot Token */
            "step_2" => $state && isset($ac->text->step_2) ? $ac->text->step_2 : esc_html__('Get Bot Token', 'easy-form-builder'),

            /* translators: Step 2 description */
            "step_2_desc" => $state && isset($ac->text->step_2_desc) ? $ac->text->step_2_desc : esc_html__('Copy the bot token provided by BotFather', 'easy-form-builder'),

            /* translators: Step 3: Get Chat ID */
            "step_3" => $state && isset($ac->text->step_3) ? $ac->text->step_3 : esc_html__('Get Chat ID', 'easy-form-builder'),

            /* translators: Step 3 description */
            "step_3_desc" => $state && isset($ac->text->step_3_desc) ? $ac->text->step_3_desc : esc_html__('For groups: Add bot and use @username. For private: Use numeric ID', 'easy-form-builder'),

            /* translators: Step 4: Test Connection */
            "step_4" => $state && isset($ac->text->step_4) ? $ac->text->step_4 : esc_html__('Test Connection', 'easy-form-builder'),

            /* translators: Step 4 description */
            "step_4_desc" => $state && isset($ac->text->step_4_desc) ? $ac->text->step_4_desc : esc_html__('Use the test feature to verify everything works', 'easy-form-builder'),

            // === Getting Chat ID Help ===
            /* translators: Getting Chat ID = help section title */
            "getting_chat_id" => $state && isset($ac->text->getting_chat_id) ? $ac->text->getting_chat_id : esc_html__('Getting Chat ID', 'easy-form-builder'),

            /* translators: For Groups/Channels = subsection title */
            "for_groups" => $state && isset($ac->text->for_groups) ? $ac->text->for_groups : esc_html__('For Groups/Channels:', 'easy-form-builder'),

            /* translators: Group step 1 */
            "group_step_1" => $state && isset($ac->text->group_step_1) ? $ac->text->group_step_1 : esc_html__('Add your bot to the group', 'easy-form-builder'),

            /* translators: Group step 2 */
            "group_step_2" => $state && isset($ac->text->group_step_2) ? $ac->text->group_step_2 : esc_html__('Use @your_bot_username', 'easy-form-builder'),

            /* translators: Group step 3 */
            "group_step_3" => $state && isset($ac->text->group_step_3) ? $ac->text->group_step_3 : esc_html__('For channels, use @channel_username', 'easy-form-builder'),

            /* translators: For Private Chats = subsection title */
            "for_private" => $state && isset($ac->text->for_private) ? $ac->text->for_private : esc_html__('For Private Chats:', 'easy-form-builder'),

            /* translators: Private step 1 */
            "private_step_1" => $state && isset($ac->text->private_step_1) ? $ac->text->private_step_1 : esc_html__('Message @userinfobot', 'easy-form-builder'),

            /* translators: Private step 2 */
            "private_step_2" => $state && isset($ac->text->private_step_2) ? $ac->text->private_step_2 : esc_html__('Copy your numeric user ID', 'easy-form-builder'),

            /* translators: Private step 3 */
            "private_step_3" => $state && isset($ac->text->private_step_3) ? $ac->text->private_step_3 : esc_html__('Use the numeric ID in settings', 'easy-form-builder'),

            /* translators: Important! = warning label */
            "important" => $state && isset($ac->text->important) ? $ac->text->important : esc_html__('Important!', 'easy-form-builder'),

            /* translators: Help note text */
            "help_note" => $state && isset($ac->text->help_note) ? $ac->text->help_note : esc_html__('Make sure your bot has permission to send messages to the target chat.', 'easy-form-builder'),

            // === Confirmation Dialogs ===
            /* translators: Confirm reset dialog */
            "confirm_reset" => $state && isset($ac->text->confirm_reset) ? $ac->text->confirm_reset : esc_html__('Are you sure you want to reset all settings?', 'easy-form-builder'),

            /* translators: Confirm clear logs dialog */
            "confirm_clear" => $state && isset($ac->text->confirm_clear) ? $ac->text->confirm_clear : esc_html__('Are you sure you want to clear all activity logs?', 'easy-form-builder'),

            // === Error & Success Messages ===
            /* translators: An error occurred = generic error */
            "error_occurred" => $state && isset($ac->text->error_occurred) ? $ac->text->error_occurred : esc_html__('An error occurred', 'easy-form-builder'),

            /* translators: Please fill in all required fields = validation error */
            "fill_required_fields" => $state && isset($ac->text->fill_required_fields) ? $ac->text->fill_required_fields : esc_html__('Please fill in all required fields', 'easy-form-builder'),

            /* translators: Connection test failed = test error */
            "connection_failed" => $state && isset($ac->text->connection_failed) ? $ac->text->connection_failed : esc_html__('Connection test failed', 'easy-form-builder'),

            /* translators: Please fill in all fields = validation error */
            "fill_all_fields" => $state && isset($ac->text->fill_all_fields) ? $ac->text->fill_all_fields : esc_html__('Please fill in all fields', 'easy-form-builder'),

            /* translators: Failed to send message = send error */
            "send_failed" => $state && isset($ac->text->send_failed) ? $ac->text->send_failed : esc_html__('Failed to send message', 'easy-form-builder'),

            /* translators: Failed to load activity = load error */
            "load_failed" => $state && isset($ac->text->load_failed) ? $ac->text->load_failed : esc_html__('Failed to load activity', 'easy-form-builder'),

            // === PHP Backend Error Messages (used in telegram-new-efb.php) ===
            /* translators: Bot token is empty = error when bot token is not provided */
            "botTokenEmpty" => $state && isset($ac->text->botTokenEmpty) ? $ac->text->botTokenEmpty : esc_html__('Bot token is empty', 'easy-form-builder'),

            /* translators: Invalid token = error for invalid bot token */
            "invalidToken" => $state && isset($ac->text->invalidToken) ? $ac->text->invalidToken : esc_html__('Invalid token', 'easy-form-builder'),

            /* translators: Unknown error = generic error message */
            "unknownError" => $state && isset($ac->text->unknownError) ? $ac->text->unknownError : esc_html__('Unknown error', 'easy-form-builder'),

            /* translators: Error connecting to Telegram server = connection error */
            "telegramConnectionError" => $state && isset($ac->text->telegramConnectionError) ? $ac->text->telegramConnectionError : esc_html__('Error connecting to Telegram server', 'easy-form-builder'),

            /* translators: Telegram configuration error = config error prefix */
            "telegramConfigError" => $state && isset($ac->text->telegramConfigError) ? $ac->text->telegramConfigError : esc_html__('Telegram configuration error', 'easy-form-builder'),

            /* translators: Message sent = success message */
            "messageSent" => $state && isset($ac->text->messageSent) ? $ac->text->messageSent : esc_html__('Message sent', 'easy-form-builder'),

            /* translators: Telegram bot is not configured = error when bot token is not set */
            "telegramBotNotConfigured" => $state && isset($ac->text->telegramBotNotConfigured) ? $ac->text->telegramBotNotConfigured : esc_html__('Telegram bot is not configured', 'easy-form-builder'),

            /* translators: Invalid response from Telegram server = API error */
            "invalidTelegramResponse" => $state && isset($ac->text->invalidTelegramResponse) ? $ac->text->invalidTelegramResponse : esc_html__('Invalid response from Telegram server', 'easy-form-builder'),

            /* translators: Telegram user not found for this phone number = user not found error */
            "telegramUserNotFound" => $state && isset($ac->text->telegramUserNotFound) ? $ac->text->telegramUserNotFound : esc_html__('Telegram user not found for this phone number', 'easy-form-builder'),

            /* translators: Telegram user account is not verified = user not verified error */
            "telegramUserNotVerified" => $state && isset($ac->text->telegramUserNotVerified) ? $ac->text->telegramUserNotVerified : esc_html__('Telegram user account is not verified', 'easy-form-builder'),

            /* translators: Error occurred while sending Telegram message = send error */
            "telegramSendError" => $state && isset($ac->text->telegramSendError) ? $ac->text->telegramSendError : esc_html__('Error occurred while sending Telegram message', 'easy-form-builder'),

            /* translators: Invalid security nonce = security error */
            "invalidSecurityNonce" => $state && isset($ac->text->invalidSecurityNonce) ? $ac->text->invalidSecurityNonce : esc_html__('Invalid security nonce', 'easy-form-builder'),

            /* translators: Invalid form ID = form ID error */
            "invalidFormId" => $state && isset($ac->text->invalidFormId) ? $ac->text->invalidFormId : esc_html__('Invalid form ID', 'easy-form-builder'),

            /* translators: Telegram notifications activated successfully = success message */
            "telegramActivationSuccess" => $state && isset($ac->text->telegramActivationSuccess) ? $ac->text->telegramActivationSuccess : esc_html__('Telegram notifications activated successfully', 'easy-form-builder'),
        ];
    }

    // ========================================
    // SMS ADDON PHRASES (Placeholder)
    // ========================================

    /**
     * Get SMS addon phrases
     *
     * @param object|null $ac Settings object
     * @param bool $state Whether custom texts are available
     * @return array
     */
    public static function get_sms_phrases($ac = null, $state = false) {
        return [
            // SMS phrases will be added here when needed
        ];
    }

    // ========================================
    // STRIPE ADDON PHRASES (Placeholder)
    // ========================================

    /**
     * Get Stripe addon phrases
     *
     * @param object|null $ac Settings object
     * @param bool $state Whether custom texts are available
     * @return array
     */
    public static function get_stripe_phrases($ac = null, $state = false) {
        return [
            // Stripe phrases will be added here when needed
        ];
    }

    // ========================================
    // PAYPAL ADDON PHRASES (Placeholder)
    // ========================================

    /**
     * Get PayPal addon phrases
     *
     * @param object|null $ac Settings object
     * @param bool $state Whether custom texts are available
     * @return array
     */
    public static function get_paypal_phrases($ac = null, $state = false) {
        return [
            // PayPal phrases will be added here when needed
        ];
    }

    // ========================================
    // WEBHOOK ADDON PHRASES (Placeholder)
    // ========================================

    /**
     * Get Webhook addon phrases
     *
     * @param object|null $ac Settings object
     * @param bool $state Whether custom texts are available
     * @return array
     */
    public static function get_webhook_phrases($ac = null, $state = false) {
        return [
            // Webhook phrases will be added here when needed
        ];
    }
}

/**
 * Helper function to get addon phrases
 *
 * @param string $addon_key Addon identifier
 * @param object|null $ac Settings object
 * @param bool $state Whether custom texts are available
 * @return array
 */
function efb_get_addon_phrases($addon_key, $ac = null, $state = false) {
    EfbAddonPhrases::get_instance();
    return EfbAddonPhrases::get_addon_phrases($addon_key, $ac, $state);
}

/**
 * Helper function to register custom addon phrases
 *
 * @param string $addon_key Addon identifier
 * @param callable $callback Function that returns phrases array
 */
function efb_register_addon_phrases($addon_key, $callback) {
    EfbAddonPhrases::get_instance();
    EfbAddonPhrases::register_addon($addon_key, $callback);
}
