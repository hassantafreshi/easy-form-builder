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

        // Register Autofill addon phrases
        self::register_addon('Autofill', [__CLASS__, 'get_autofill_phrases']);

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
            "default_test_message" => $state && isset($ac->text->default_test_message) ? $ac->text->default_test_message : esc_html__("ðŸŽ¯ Test Message\n\nThis is a test message from Easy Form Builder.\n\nâœ… If you receive this message, your Telegram integration is working correctly!", 'easy-form-builder'),

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
    // AUTOFILL ADDON PHRASES
    // ========================================

    /**
     * Get Autofill addon phrases
     *
     * @param object|null $ac Settings object
     * @param bool $state Whether custom texts are available
     * @return array
     */
    public static function get_autofill_phrases($ac = null, $state = false) {
        return [
            // === Menu & Navigation ===
            /* translators: Autofill Dataset = menu title for dataset management */
            "autofill_dataset" => $state && isset($ac->text->autofill_dataset) ? $ac->text->autofill_dataset : esc_html__('Autofill Dataset', 'easy-form-builder'),

            /* translators: Autofill Integrations = menu title for API integrations */
            "autofill_integrations" => $state && isset($ac->text->autofill_integrations) ? $ac->text->autofill_integrations : esc_html__('Autofill Integrations', 'easy-form-builder'),

            // === External API Section ===
            /* translators: External API Connections = section header */
            "external_api" => $state && isset($ac->text->external_api) ? $ac->text->external_api : esc_html__('External API Connections', 'easy-form-builder'),

            /* translators: Add New API Connection = button text */
            "add_new_api" => $state && isset($ac->text->add_new_api) ? $ac->text->add_new_api : esc_html__('Add New API Connection', 'easy-form-builder'),

            /* translators: Edit API Connection = modal title */
            "edit_api_connection" => $state && isset($ac->text->edit_api_connection) ? $ac->text->edit_api_connection : esc_html__('Edit API Connection', 'easy-form-builder'),

            // === Connection Settings ===
            /* translators: Connection Name = field label */
            "connection_name" => $state && isset($ac->text->connection_name) ? $ac->text->connection_name : esc_html__('Connection Name', 'easy-form-builder'),

            /* translators: Connection Name placeholder */
            "connection_name_placeholder" => $state && isset($ac->text->connection_name_placeholder) ? $ac->text->connection_name_placeholder : esc_html__('e.g., Customer Lookup API', 'easy-form-builder'),

            /* translators: HTTP Method = field label */
            "http_method" => $state && isset($ac->text->http_method) ? $ac->text->http_method : esc_html__('HTTP Method', 'easy-form-builder'),

            /* translators: API Endpoint URL = field label */
            "endpoint_url" => $state && isset($ac->text->endpoint_url) ? $ac->text->endpoint_url : esc_html__('API Endpoint URL', 'easy-form-builder'),

            /* translators: Endpoint URL help text */
            "endpoint_url_help" => $state && isset($ac->text->endpoint_url_help) ? $ac->text->endpoint_url_help : esc_html__('Use {{field_id}} placeholders for dynamic values from form fields', 'easy-form-builder'),

            // === Authentication ===
            /* translators: Authentication = section title */
            "authentication" => $state && isset($ac->text->authentication) ? $ac->text->authentication : esc_html__('Authentication', 'easy-form-builder'),

            /* translators: Authentication Type = field label */
            "auth_type" => $state && isset($ac->text->auth_type) ? $ac->text->auth_type : esc_html__('Authentication Type', 'easy-form-builder'),

            /* translators: No Authentication = auth option */
            "no_auth" => $state && isset($ac->text->no_auth) ? $ac->text->no_auth : esc_html__('No Authentication', 'easy-form-builder'),

            /* translators: Custom Header = auth option */
            "custom_header" => $state && isset($ac->text->custom_header) ? $ac->text->custom_header : esc_html__('Custom Header', 'easy-form-builder'),

            /* translators: Authentication Value = field label */
            "auth_value" => $state && isset($ac->text->auth_value) ? $ac->text->auth_value : esc_html__('Authentication Value', 'easy-form-builder'),

            /* translators: Auth Value placeholder */
            "auth_value_placeholder" => $state && isset($ac->text->auth_value_placeholder) ? $ac->text->auth_value_placeholder : esc_html__('Enter token or credentials', 'easy-form-builder'),

            /* translators: Bearer token help */
            "bearer_help" => $state && isset($ac->text->bearer_help) ? $ac->text->bearer_help : esc_html__('Enter your Bearer token without "Bearer " prefix', 'easy-form-builder'),

            /* translators: Basic auth help */
            "basic_help" => $state && isset($ac->text->basic_help) ? $ac->text->basic_help : esc_html__('Enter as username:password', 'easy-form-builder'),

            /* translators: API key help */
            "api_key_help" => $state && isset($ac->text->api_key_help) ? $ac->text->api_key_help : esc_html__('Enter your API key', 'easy-form-builder'),

            /* translators: Custom auth help */
            "custom_auth_help" => $state && isset($ac->text->custom_auth_help) ? $ac->text->custom_auth_help : esc_html__('Enter as Header-Name: value', 'easy-form-builder'),

            // === Headers & Parameters ===
            /* translators: Custom Headers = section title */
            "custom_headers" => $state && isset($ac->text->custom_headers) ? $ac->text->custom_headers : esc_html__('Custom Headers', 'easy-form-builder'),

            /* translators: No custom headers message */
            "no_custom_headers" => $state && isset($ac->text->no_custom_headers) ? $ac->text->no_custom_headers : esc_html__('No custom headers', 'easy-form-builder'),

            /* translators: Query Parameters = section title */
            "query_params" => $state && isset($ac->text->query_params) ? $ac->text->query_params : esc_html__('Query Parameters', 'easy-form-builder'),

            /* translators: No query params message */
            "no_query_params" => $state && isset($ac->text->no_query_params) ? $ac->text->no_query_params : esc_html__('No query parameters', 'easy-form-builder'),

            // === Request Body ===
            /* translators: Request Body Template = field label */
            "request_body" => $state && isset($ac->text->request_body) ? $ac->text->request_body : esc_html__('Request Body Template (JSON)', 'easy-form-builder'),

            /* translators: Body template help */
            "body_template_help" => $state && isset($ac->text->body_template_help) ? $ac->text->body_template_help : esc_html__('JSON template for POST/PUT/PATCH requests. Use {{field_id}} for dynamic values.', 'easy-form-builder'),

            // === Response Mapping ===
            /* translators: Response Mapping = section title */
            "response_mapping" => $state && isset($ac->text->response_mapping) ? $ac->text->response_mapping : esc_html__('Response Mapping', 'easy-form-builder'),

            /* translators: Response Data Path = field label */
            "response_path" => $state && isset($ac->text->response_path) ? $ac->text->response_path : esc_html__('Response Data Path', 'easy-form-builder'),

            /* translators: Response path help */
            "response_path_help" => $state && isset($ac->text->response_path_help) ? $ac->text->response_path_help : esc_html__('Dot notation path to the data array in response. e.g., data.results or items', 'easy-form-builder'),

            /* translators: Field Mappings = section title */
            "field_mappings" => $state && isset($ac->text->field_mappings) ? $ac->text->field_mappings : esc_html__('Field Mappings', 'easy-form-builder'),

            /* translators: No field mappings message */
            "no_field_mappings" => $state && isset($ac->text->no_field_mappings) ? $ac->text->no_field_mappings : esc_html__('No field mappings', 'easy-form-builder'),

            /* translators: Field mappings help */
            "field_mappings_help" => $state && isset($ac->text->field_mappings_help) ? $ac->text->field_mappings_help : esc_html__('Map API response fields to form field IDs', 'easy-form-builder'),

            // === Cache Settings ===
            /* translators: Cache Settings = section title */
            "cache_settings" => $state && isset($ac->text->cache_settings) ? $ac->text->cache_settings : esc_html__('Cache Settings', 'easy-form-builder'),

            /* translators: Cache Duration = field label */
            "cache_duration" => $state && isset($ac->text->cache_duration) ? $ac->text->cache_duration : esc_html__('Cache Duration (minutes)', 'easy-form-builder'),

            /* translators: Cache duration help */
            "cache_duration_help" => $state && isset($ac->text->cache_duration_help) ? $ac->text->cache_duration_help : esc_html__('0 = no caching', 'easy-form-builder'),

            // === Testing & Actions ===
            /* translators: Basic Information = section title */
            "basic_info" => $state && isset($ac->text->basic_info) ? $ac->text->basic_info : esc_html__('Basic Information', 'easy-form-builder'),

            /* translators: Test Connection = button text */
            "test_connection" => $state && isset($ac->text->test_connection) ? $ac->text->test_connection : esc_html__('Test Connection', 'easy-form-builder'),

            /* translators: Test Result = section title */
            "test_result" => $state && isset($ac->text->test_result) ? $ac->text->test_result : esc_html__('Test Result', 'easy-form-builder'),

            /* translators: Testing connection message */
            "testing" => $state && isset($ac->text->testing) ? $ac->text->testing : esc_html__('Testing connection...', 'easy-form-builder'),

            /* translators: Endpoint = column header */
            "endpoint" => $state && isset($ac->text->endpoint) ? $ac->text->endpoint : esc_html__('Endpoint', 'easy-form-builder'),

            /* translators: Actions = column header */
            "actions" => $state && isset($ac->text->actions) ? $ac->text->actions : esc_html__('Actions', 'easy-form-builder'),

            /* translators: Content = column header for message content preview */
            "content" => $state && isset($ac->text->content) ? $ac->text->content : esc_html__('Content', 'easy-form-builder'),

            /* translators: Description shown when there are no responses yet */
            "noResponseDesc" => $state && isset($ac->text->noResponseDesc) ? $ac->text->noResponseDesc : esc_html__('Submitted responses will appear here.', 'easy-form-builder'),

            /* translators: Saving message */
            "saving" => $state && isset($ac->text->saving) ? $ac->text->saving : esc_html__('Saving...', 'easy-form-builder'),

            // === Empty States & Confirmations ===
            /* translators: No API connections message */
            "no_api_connections" => $state && isset($ac->text->no_api_connections) ? $ac->text->no_api_connections : esc_html__('No API connections yet. Click "Add New API Connection" to create one.', 'easy-form-builder'),

            /* translators: Confirm delete message */
            "confirm_delete" => $state && isset($ac->text->confirm_delete) ? $ac->text->confirm_delete : esc_html__('Are you sure you want to delete', 'easy-form-builder'),

            // === Error Messages ===
            /* translators: Security check failed = error message */
            "security_check_failed" => $state && isset($ac->text->security_check_failed) ? $ac->text->security_check_failed : esc_html__('Security check failed', 'easy-form-builder'),

            /* translators: Permission denied = error message */
            "permission_denied" => $state && isset($ac->text->permission_denied) ? $ac->text->permission_denied : esc_html__('Permission denied', 'easy-form-builder'),

            /* translators: Connection name required = error message */
            "connection_name_required" => $state && isset($ac->text->connection_name_required) ? $ac->text->connection_name_required : esc_html__('Connection name is required', 'easy-form-builder'),

            /* translators: API endpoint required = error message */
            "api_endpoint_required" => $state && isset($ac->text->api_endpoint_required) ? $ac->text->api_endpoint_required : esc_html__('API endpoint URL is required', 'easy-form-builder'),

            /* translators: Connection ID required = error message */
            "connection_id_required" => $state && isset($ac->text->connection_id_required) ? $ac->text->connection_id_required : esc_html__('Connection ID is required', 'easy-form-builder'),

            /* translators: Connection not found = error message */
            "connection_not_found" => $state && isset($ac->text->connection_not_found) ? $ac->text->connection_not_found : esc_html__('Connection not found', 'easy-form-builder'),

            /* translators: API connection not found = error message */
            "api_connection_not_found" => $state && isset($ac->text->api_connection_not_found) ? $ac->text->api_connection_not_found : esc_html__('API connection not found or disabled', 'easy-form-builder'),

            /* translators: Rate limit exceeded = error message */
            "rate_limit_exceeded" => $state && isset($ac->text->rate_limit_exceeded) ? $ac->text->rate_limit_exceeded : esc_html__('Rate limit exceeded. Please try again later.', 'easy-form-builder'),

            /* translators: Invalid request format = error message */
            "invalid_request_format" => $state && isset($ac->text->invalid_request_format) ? $ac->text->invalid_request_format : esc_html__('Invalid request format', 'easy-form-builder'),

            /* translators: Failed to parse API response = error message */
            "parse_error" => $state && isset($ac->text->parse_error) ? $ac->text->parse_error : esc_html__('Failed to parse API response', 'easy-form-builder'),

            // === Success Messages ===
            /* translators: API connection saved = success message */
            "api_connection_saved" => $state && isset($ac->text->api_connection_saved) ? $ac->text->api_connection_saved : esc_html__('API connection saved successfully', 'easy-form-builder'),

            /* translators: Connection test successful = success message */
            "connection_test_successful" => $state && isset($ac->text->connection_test_successful) ? $ac->text->connection_test_successful : esc_html__('Connection test successful', 'easy-form-builder'),

            /* translators: API connection deleted = success message */
            "api_connection_deleted" => $state && isset($ac->text->api_connection_deleted) ? $ac->text->api_connection_deleted : esc_html__('API connection deleted successfully', 'easy-form-builder'),

            /* translators: Connection enabled = status message */
            "connection_enabled" => $state && isset($ac->text->connection_enabled) ? $ac->text->connection_enabled : esc_html__('Connection enabled', 'easy-form-builder'),

            /* translators: Connection disabled = status message */
            "connection_disabled" => $state && isset($ac->text->connection_disabled) ? $ac->text->connection_disabled : esc_html__('Connection disabled', 'easy-form-builder'),

            // === Form & API Messages ===
            /* translators: API connection ID required = error message */
            "api_connection_id_required" => $state && isset($ac->text->api_connection_id_required) ? $ac->text->api_connection_id_required : esc_html__('API connection ID is required', 'easy-form-builder'),

            /* translators: Form ID required = error message */
            "form_id_required" => $state && isset($ac->text->form_id_required) ? $ac->text->form_id_required : esc_html__('Form ID is required', 'easy-form-builder'),

            /* translators: Form not found = error message */
            "form_not_found" => $state && isset($ac->text->form_not_found) ? $ac->text->form_not_found : esc_html__('Form not found', 'easy-form-builder'),

            /* translators: Invalid form structure = error message */
            "invalid_form_structure" => $state && isset($ac->text->invalid_form_structure) ? $ac->text->invalid_form_structure : esc_html__('Invalid form structure', 'easy-form-builder'),

            /* translators: Invalid form ID = error message */
            "invalid_form_id" => $state && isset($ac->text->invalid_form_id) ? $ac->text->invalid_form_id : esc_html__('Invalid form ID', 'easy-form-builder'),

            /* translators: No matching data found = message */
            "no_matching_data" => $state && isset($ac->text->no_matching_data) ? $ac->text->no_matching_data : esc_html__('No matching data found', 'easy-form-builder'),

            /* translators: API returned error = error message with status code */
            "api_returned_error" => $state && isset($ac->text->api_returned_error) ? $ac->text->api_returned_error : esc_html__('API returned error: %d', 'easy-form-builder'),

            /* translators: Failed to update form = error message */
            "failed_to_update_form" => $state && isset($ac->text->failed_to_update_form) ? $ac->text->failed_to_update_form : esc_html__('Failed to update form', 'easy-form-builder'),

            /* translators: Form updated successfully = success message */
            "form_updated_successfully" => $state && isset($ac->text->form_updated_successfully) ? $ac->text->form_updated_successfully : esc_html__('Form updated successfully', 'easy-form-builder'),

            /* translators: Test = button text */
            "test" => $state && isset($ac->text->test) ? $ac->text->test : esc_html__('Test', 'easy-form-builder'),

            // === JavaScript Aliases (camelCase for frontend) ===
            // These are aliases for the snake_case keys to maintain JS compatibility
            "externalApi" => $state && isset($ac->text->external_api) ? $ac->text->external_api : esc_html__('External API Connections', 'easy-form-builder'),
            "addNewApi" => $state && isset($ac->text->add_new_api) ? $ac->text->add_new_api : esc_html__('Add New API Connection', 'easy-form-builder'),
            "editApiConnection" => $state && isset($ac->text->edit_api_connection) ? $ac->text->edit_api_connection : esc_html__('Edit API Connection', 'easy-form-builder'),
            "connectionName" => $state && isset($ac->text->connection_name) ? $ac->text->connection_name : esc_html__('Connection Name', 'easy-form-builder'),
            "connectionNamePlaceholder" => $state && isset($ac->text->connection_name_placeholder) ? $ac->text->connection_name_placeholder : esc_html__('e.g., Customer Lookup API', 'easy-form-builder'),
            "httpMethod" => $state && isset($ac->text->http_method) ? $ac->text->http_method : esc_html__('HTTP Method', 'easy-form-builder'),
            "endpointUrl" => $state && isset($ac->text->endpoint_url) ? $ac->text->endpoint_url : esc_html__('API Endpoint URL', 'easy-form-builder'),
            "endpointUrlHelp" => $state && isset($ac->text->endpoint_url_help) ? $ac->text->endpoint_url_help : esc_html__('Use {{field_id}} placeholders for dynamic values from form fields', 'easy-form-builder'),
            "authType" => $state && isset($ac->text->auth_type) ? $ac->text->auth_type : esc_html__('Authentication Type', 'easy-form-builder'),
            "noAuth" => $state && isset($ac->text->no_auth) ? $ac->text->no_auth : esc_html__('No Authentication', 'easy-form-builder'),
            "customHeader" => $state && isset($ac->text->custom_header) ? $ac->text->custom_header : esc_html__('Custom Header', 'easy-form-builder'),
            "authValue" => $state && isset($ac->text->auth_value) ? $ac->text->auth_value : esc_html__('Authentication Value', 'easy-form-builder'),
            "authValuePlaceholder" => $state && isset($ac->text->auth_value_placeholder) ? $ac->text->auth_value_placeholder : esc_html__('Enter token or credentials', 'easy-form-builder'),
            "bearerHelp" => $state && isset($ac->text->bearer_help) ? $ac->text->bearer_help : esc_html__('Enter your Bearer token without "Bearer " prefix', 'easy-form-builder'),
            "basicHelp" => $state && isset($ac->text->basic_help) ? $ac->text->basic_help : esc_html__('Enter as username:password', 'easy-form-builder'),
            "apiKeyHelp" => $state && isset($ac->text->api_key_help) ? $ac->text->api_key_help : esc_html__('Enter your API key', 'easy-form-builder'),
            "customAuthHelp" => $state && isset($ac->text->custom_auth_help) ? $ac->text->custom_auth_help : esc_html__('Enter as Header-Name: value', 'easy-form-builder'),
            "customHeaders" => $state && isset($ac->text->custom_headers) ? $ac->text->custom_headers : esc_html__('Custom Headers', 'easy-form-builder'),
            "noCustomHeaders" => $state && isset($ac->text->no_custom_headers) ? $ac->text->no_custom_headers : esc_html__('No custom headers', 'easy-form-builder'),
            "queryParams" => $state && isset($ac->text->query_params) ? $ac->text->query_params : esc_html__('Query Parameters', 'easy-form-builder'),
            "noQueryParams" => $state && isset($ac->text->no_query_params) ? $ac->text->no_query_params : esc_html__('No query parameters', 'easy-form-builder'),
            "requestBody" => $state && isset($ac->text->request_body) ? $ac->text->request_body : esc_html__('Request Body Template (JSON)', 'easy-form-builder'),
            "bodyTemplateHelp" => $state && isset($ac->text->body_template_help) ? $ac->text->body_template_help : esc_html__('JSON template for POST/PUT/PATCH requests. Use {{field_id}} for dynamic values.', 'easy-form-builder'),
            "responseMapping" => $state && isset($ac->text->response_mapping) ? $ac->text->response_mapping : esc_html__('Response Mapping', 'easy-form-builder'),
            "responsePath" => $state && isset($ac->text->response_path) ? $ac->text->response_path : esc_html__('Response Data Path', 'easy-form-builder'),
            "responsePathHelp" => $state && isset($ac->text->response_path_help) ? $ac->text->response_path_help : esc_html__('Dot notation path to the data array in response. e.g., data.results or items', 'easy-form-builder'),
            "fieldMappings" => $state && isset($ac->text->field_mappings) ? $ac->text->field_mappings : esc_html__('Field Mappings', 'easy-form-builder'),
            "noFieldMappings" => $state && isset($ac->text->no_field_mappings) ? $ac->text->no_field_mappings : esc_html__('No field mappings', 'easy-form-builder'),
            "fieldMappingsHelp" => $state && isset($ac->text->field_mappings_help) ? $ac->text->field_mappings_help : esc_html__('Map API response fields to form field IDs', 'easy-form-builder'),
            "cacheSettings" => $state && isset($ac->text->cache_settings) ? $ac->text->cache_settings : esc_html__('Cache Settings', 'easy-form-builder'),
            "cacheDuration" => $state && isset($ac->text->cache_duration) ? $ac->text->cache_duration : esc_html__('Cache Duration (minutes)', 'easy-form-builder'),
            "cacheDurationHelp" => $state && isset($ac->text->cache_duration_help) ? $ac->text->cache_duration_help : esc_html__('0 = no caching', 'easy-form-builder'),
            "basicInfo" => $state && isset($ac->text->basic_info) ? $ac->text->basic_info : esc_html__('Basic Information', 'easy-form-builder'),
            "testConnection" => $state && isset($ac->text->test_connection) ? $ac->text->test_connection : esc_html__('Test Connection', 'easy-form-builder'),
            "testResult" => $state && isset($ac->text->test_result) ? $ac->text->test_result : esc_html__('Test Result', 'easy-form-builder'),
            "testing" => $state && isset($ac->text->testing) ? $ac->text->testing : esc_html__('Testing connection...', 'easy-form-builder'),
            "noApiConnections" => $state && isset($ac->text->no_api_connections) ? $ac->text->no_api_connections : esc_html__('No API connections yet. Click "Add New API Connection" to create one.', 'easy-form-builder'),
            "confirmDelete" => $state && isset($ac->text->confirm_delete) ? $ac->text->confirm_delete : esc_html__('Are you sure you want to delete', 'easy-form-builder'),
            "saving" => $state && isset($ac->text->saving) ? $ac->text->saving : esc_html__('Saving...', 'easy-form-builder'),

            // === Additional JS Dashboard Phrases ===
            // Intro Section
            "apiIntroTitle" => $state && isset($ac->text->api_intro_title) ? $ac->text->api_intro_title : esc_html__('Connect Your Forms to External APIs', 'easy-form-builder'),
            "apiIntroDesc" => $state && isset($ac->text->api_intro_desc) ? $ac->text->api_intro_desc : esc_html__('Easily autofill your form fields with data from any API. Just add your API endpoint and map the fields!', 'easy-form-builder'),
            "clickToAdd" => $state && isset($ac->text->click_to_add) ? $ac->text->click_to_add : esc_html__('Click the button above to create your first API connection', 'easy-form-builder'),

            // Wizard Steps
            "step1Label" => $state && isset($ac->text->step1_label) ? $ac->text->step1_label : esc_html__('Basic Info', 'easy-form-builder'),
            "step2Label" => $state && isset($ac->text->step2_label) ? $ac->text->step2_label : esc_html__('Authentication', 'easy-form-builder'),
            "step3Label" => $state && isset($ac->text->step3_label) ? $ac->text->step3_label : esc_html__('Field Mapping', 'easy-form-builder'),
            "step4Label" => $state && isset($ac->text->step4_label) ? $ac->text->step4_label : esc_html__('Test & Save', 'easy-form-builder'),

            // Basic Info Section
            "basicInfoTitle" => $state && isset($ac->text->basic_info_title) ? $ac->text->basic_info_title : esc_html__('Basic API Information', 'easy-form-builder'),
            "connectionNameHelp" => $state && isset($ac->text->connection_name_help) ? $ac->text->connection_name_help : esc_html__('A friendly name to identify this API connection', 'easy-form-builder'),
            "getMethodDesc" => $state && isset($ac->text->get_method_desc) ? $ac->text->get_method_desc : esc_html__('Fetch data', 'easy-form-builder'),
            "postMethodDesc" => $state && isset($ac->text->post_method_desc) ? $ac->text->post_method_desc : esc_html__('Send data', 'easy-form-builder'),
            "requestBodyHelp" => $state && isset($ac->text->request_body_help) ? $ac->text->request_body_help : esc_html__('JSON body for POST requests. Use {{field_id}} for dynamic values.', 'easy-form-builder'),

            // Authentication Section
            "authTitle" => $state && isset($ac->text->auth_title) ? $ac->text->auth_title : esc_html__('Authentication Settings', 'easy-form-builder'),
            "authInfo" => $state && isset($ac->text->auth_info) ? $ac->text->auth_info : esc_html__('If your API requires authentication, select the type below. Otherwise, leave it as "No Authentication".', 'easy-form-builder'),
            "noAuthDesc" => $state && isset($ac->text->no_auth_desc) ? $ac->text->no_auth_desc : esc_html__('API is public', 'easy-form-builder'),
            "bearerDesc" => $state && isset($ac->text->bearer_desc) ? $ac->text->bearer_desc : esc_html__('JWT or OAuth tokens', 'easy-form-builder'),
            "apiKeyDesc" => $state && isset($ac->text->api_key_desc) ? $ac->text->api_key_desc : esc_html__('X-API-Key header', 'easy-form-builder'),
            "basicDesc" => $state && isset($ac->text->basic_desc) ? $ac->text->basic_desc : esc_html__('Username:Password', 'easy-form-builder'),
            "addHeader" => $state && isset($ac->text->add_header) ? $ac->text->add_header : esc_html__('Add Header', 'easy-form-builder'),
            "noHeaders" => $state && isset($ac->text->no_headers) ? $ac->text->no_headers : esc_html__('No custom headers', 'easy-form-builder'),
            "optional" => $state && isset($ac->text->optional) ? $ac->text->optional : esc_html__('Optional', 'easy-form-builder'),

            // Field Mapping Section
            "fieldMappingTitle" => $state && isset($ac->text->field_mapping_title) ? $ac->text->field_mapping_title : esc_html__('Field Mapping', 'easy-form-builder'),
            "selectFormTitle" => $state && isset($ac->text->select_form_title) ? $ac->text->select_form_title : esc_html__('Select Form', 'easy-form-builder'),
            "targetFormHelp" => $state && isset($ac->text->target_form_help) ? $ac->text->target_form_help : esc_html__('Select the form that will receive data from the API', 'easy-form-builder'),
            "targetForm" => $state && isset($ac->text->target_form) ? $ac->text->target_form : esc_html__('Target Form', 'easy-form-builder'),
            "selectForm" => $state && isset($ac->text->select_form) ? $ac->text->select_form : esc_html__('— Select a Form —', 'easy-form-builder'),
            "searchFieldsTitle" => $state && isset($ac->text->search_fields_title) ? $ac->text->search_fields_title : esc_html__('Search Fields (Trigger Fields)', 'easy-form-builder'),
            "searchConfigDesc" => $state && isset($ac->text->search_config_desc) ? $ac->text->search_config_desc : esc_html__('Select fields that trigger the API search', 'easy-form-builder'),
            "formField" => $state && isset($ac->text->form_field) ? $ac->text->form_field : esc_html__('Form Field', 'easy-form-builder'),
            "apiParamName" => $state && isset($ac->text->api_param_name) ? $ac->text->api_param_name : esc_html__('API Parameter', 'easy-form-builder'),
            "fieldType" => $state && isset($ac->text->field_type) ? $ac->text->field_type : esc_html__('Type', 'easy-form-builder'),
            "selectFormFirst" => $state && isset($ac->text->select_form_first) ? $ac->text->select_form_first : esc_html__('Please select a form first', 'easy-form-builder'),
            "searchParamHelp" => $state && isset($ac->text->search_param_help) ? $ac->text->search_param_help : esc_html__('API Parameter is what will be sent to the API (e.g., "q" for ?q=value)', 'easy-form-builder'),
            "targetFieldsTitle" => $state && isset($ac->text->target_fields_title) ? $ac->text->target_fields_title : esc_html__('Target Fields (Auto-fill)', 'easy-form-builder'),
            "targetFieldsInfo" => $state && isset($ac->text->target_fields_info) ? $ac->text->target_fields_info : esc_html__('Map API response to form fields', 'easy-form-builder'),
            "apiFieldName" => $state && isset($ac->text->api_field_name) ? $ac->text->api_field_name : esc_html__('API Response Field', 'easy-form-builder'),
            "formFieldSelect" => $state && isset($ac->text->form_field_select) ? $ac->text->form_field_select : esc_html__('Form Field to Fill', 'easy-form-builder'),
            "addMapping" => $state && isset($ac->text->add_mapping) ? $ac->text->add_mapping : esc_html__('Add Mapping', 'easy-form-builder'),
            "selectField" => $state && isset($ac->text->select_field) ? $ac->text->select_field : esc_html__('— Select Field —', 'easy-form-builder'),
            "fieldsSelected" => $state && isset($ac->text->fields_selected) ? $ac->text->fields_selected : esc_html__('selected', 'easy-form-builder'),

            // Cache Settings
            "cacheHelp" => $state && isset($ac->text->cache_help) ? $ac->text->cache_help : esc_html__('Cache API responses to improve performance', 'easy-form-builder'),
            "noCache" => $state && isset($ac->text->no_cache) ? $ac->text->no_cache : esc_html__('No caching', 'easy-form-builder'),
            "minutes" => $state && isset($ac->text->minutes) ? $ac->text->minutes : esc_html__('minutes', 'easy-form-builder'),
            "hour" => $state && isset($ac->text->hour) ? $ac->text->hour : esc_html__('hour', 'easy-form-builder'),

            // Test & Save Section
            "testSaveTitle" => $state && isset($ac->text->test_save_title) ? $ac->text->test_save_title : esc_html__('Test Connection & Save', 'easy-form-builder'),
            "connectionSummary" => $state && isset($ac->text->connection_summary) ? $ac->text->connection_summary : esc_html__('Connection Summary', 'easy-form-builder'),
            "name" => $state && isset($ac->text->name) ? $ac->text->name : esc_html__('Name', 'easy-form-builder'),
            "method" => $state && isset($ac->text->method) ? $ac->text->method : esc_html__('Method', 'easy-form-builder'),
            "endpoint" => $state && isset($ac->text->endpoint) ? $ac->text->endpoint : esc_html__('Endpoint', 'easy-form-builder'),
            "auth" => $state && isset($ac->text->auth) ? $ac->text->auth : esc_html__('Authentication', 'easy-form-builder'),
            "runTest" => $state && isset($ac->text->run_test) ? $ac->text->run_test : esc_html__('Run Test', 'easy-form-builder'),
            "clickTestBtn" => $state && isset($ac->text->click_test_btn) ? $ac->text->click_test_btn : esc_html__('Click "Run Test" to test your API connection', 'easy-form-builder'),
            "enableConnection" => $state && isset($ac->text->enable_connection) ? $ac->text->enable_connection : esc_html__('Enable this connection', 'easy-form-builder'),
            "previous" => $state && isset($ac->text->previous) ? $ac->text->previous : esc_html__('Previous', 'easy-form-builder'),
            "next" => $state && isset($ac->text->next) ? $ac->text->next : esc_html__('Next', 'easy-form-builder'),
            "save" => $state && isset($ac->text->save) ? $ac->text->save : esc_html__('Save Connection', 'easy-form-builder'),
            "back" => $state && isset($ac->text->back) ? $ac->text->back : esc_html__('Back', 'easy-form-builder'),
            "edit" => $state && isset($ac->text->edit) ? $ac->text->edit : esc_html__('Edit', 'easy-form-builder'),
            "test" => $state && isset($ac->text->test) ? $ac->text->test : esc_html__('Test', 'easy-form-builder'),
            "enabled" => $state && isset($ac->text->enabled) ? $ac->text->enabled : esc_html__('Active', 'easy-form-builder'),
            "disabled" => $state && isset($ac->text->disabled) ? $ac->text->disabled : esc_html__('Inactive', 'easy-form-builder'),

            // Validation Messages
            "nameRequired" => $state && isset($ac->text->name_required) ? $ac->text->name_required : esc_html__('Please enter a connection name', 'easy-form-builder'),
            "urlRequired" => $state && isset($ac->text->url_required) ? $ac->text->url_required : esc_html__('Please enter an API endpoint URL', 'easy-form-builder'),
            "invalidUrl" => $state && isset($ac->text->invalid_url) ? $ac->text->invalid_url : esc_html__('Please enter a valid URL starting with http:// or https://', 'easy-form-builder'),
            "fillRequired" => $state && isset($ac->text->fill_required) ? $ac->text->fill_required : esc_html__('Please fill all required fields', 'easy-form-builder'),
            "loading" => $state && isset($ac->text->loading) ? $ac->text->loading : esc_html__('Loading...', 'easy-form-builder'),

            // Success/Error Messages
            "savedSuccess" => $state && isset($ac->text->saved_success) ? $ac->text->saved_success : esc_html__('Connection saved successfully!', 'easy-form-builder'),
            "saveFailed" => $state && isset($ac->text->save_failed) ? $ac->text->save_failed : esc_html__('Failed to save connection', 'easy-form-builder'),
            "errorOccurred" => $state && isset($ac->text->error_occurred) ? $ac->text->error_occurred : esc_html__('An error occurred', 'easy-form-builder'),
            "connectionSuccess" => $state && isset($ac->text->connection_success) ? $ac->text->connection_success : esc_html__('Connection successful!', 'easy-form-builder'),
            "connectionFailed" => $state && isset($ac->text->connection_failed) ? $ac->text->connection_failed : esc_html__('Connection failed', 'easy-form-builder'),
            "testFailed" => $state && isset($ac->text->test_failed) ? $ac->text->test_failed : esc_html__('Test failed. Please check the URL and try again.', 'easy-form-builder'),
            "deleteSuccess" => $state && isset($ac->text->delete_success) ? $ac->text->delete_success : esc_html__('Connection deleted', 'easy-form-builder'),
            "formNotFound" => $state && isset($ac->text->form_not_found) ? $ac->text->form_not_found : esc_html__('Form data not found. Please refresh the page.', 'easy-form-builder'),
            "noFieldsFound" => $state && isset($ac->text->no_fields_found) ? $ac->text->no_fields_found : esc_html__('No fillable fields found in this form', 'easy-form-builder'),

            // API AutoFill Info Box (for autofill-efb.js)
            "atfllApiActive" => $state && isset($ac->text->atfll_api_active) ? $ac->text->atfll_api_active : esc_html__('API AutoFill Integration is Active', 'easy-form-builder'),
            "atfllApiActiveDesc" => $state && isset($ac->text->atfll_api_active_desc) ? $ac->text->atfll_api_active_desc : esc_html__('This form uses External API AutoFill. To configure settings, go to', 'easy-form-builder'),
            "atfllApiLink" => $state && isset($ac->text->atfll_api_link) ? $ac->text->atfll_api_link : esc_html__('Autofill Integrations', 'easy-form-builder'),

            // Additional API Page Keys (for autofill-api-efb.js)
            "status" => $state && isset($ac->text->status) ? $ac->text->status : esc_html__('Status', 'easy-form-builder'),
            "actions" => $state && isset($ac->text->actions) ? $ac->text->actions : esc_html__('Actions', 'easy-form-builder'),
            "enable" => $state && isset($ac->text->enable) ? $ac->text->enable : esc_html__('Enable', 'easy-form-builder'),
            "disable" => $state && isset($ac->text->disable) ? $ac->text->disable : esc_html__('Disable', 'easy-form-builder'),
            "delete" => $state && isset($ac->text->delete) ? $ac->text->delete : esc_html__('Delete', 'easy-form-builder'),
            "cancel" => $state && isset($ac->text->cancel) ? $ac->text->cancel : esc_html__('Cancel', 'easy-form-builder'),
            "authentication" => $state && isset($ac->text->authentication) ? $ac->text->authentication : esc_html__('Authentication', 'easy-form-builder'),
            "endpointRequired" => $state && isset($ac->text->endpoint_required) ? $ac->text->endpoint_required : esc_html__('API endpoint URL is required', 'easy-form-builder'),
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
