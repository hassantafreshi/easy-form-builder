<?php

if (!defined('ABSPATH')) {
    exit;
}

class EfbAddonPhrases {

    private static $instance = null;

    private static $addon_providers = [];

    private static $phrase_cache = [];

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->register_default_addons();
    }

    private function register_default_addons() {

        self::register_addon('telegram', [__CLASS__, 'get_telegram_phrases']);

        self::register_addon('Autofill', [__CLASS__, 'get_autofill_phrases']);

        self::register_addon('payment', [__CLASS__, 'get_payment_phrases']);

        self::register_addon('sms', [__CLASS__, 'get_sms_phrases']);

        self::register_addon('googlesheet', [__CLASS__, 'get_googlesheet_phrases']);

    }

    public static function register_addon($addon_key, $callback) {
        self::$addon_providers[$addon_key] = $callback;
    }

    public static function get_addon_phrases($addon_key, $ac = null, $state = false) {

        $cache_key = $addon_key . '_' . ($state ? '1' : '0');
        if (isset(self::$phrase_cache[$cache_key])) {
            return self::$phrase_cache[$cache_key];
        }

        if (!isset(self::$addon_providers[$addon_key])) {
            return [];
        }

        $phrases = call_user_func(self::$addon_providers[$addon_key], $ac, $state);

        self::$phrase_cache[$cache_key] = $phrases;

        return $phrases;
    }

    public static function get_multiple_addon_phrases($addon_keys, $ac = null, $state = false) {
        $phrases = [];
        foreach ($addon_keys as $key) {
            $phrases = array_merge($phrases, self::get_addon_phrases($key, $ac, $state));
        }
        return $phrases;
    }

    public static function clear_cache() {
        self::$phrase_cache = [];
    }

    public static function get_registered_addons() {
        return array_keys(self::$addon_providers);
    }

    public static function get_telegram_phrases($ac = null, $state = false) {
        return [

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

            /* translators: Enabled = status when feature is active */
            "enabled" => $state && isset($ac->text->enabled) ? $ac->text->enabled : esc_html__('Enabled', 'easy-form-builder'),

            /* translators: Status = current state label */
            "status" => $state && isset($ac->text->status) ? $ac->text->status : esc_html__('Status', 'easy-form-builder'),

            /* translators: Notifications are active = status description */
            "notifications_active" => $state && isset($ac->text->notifications_active) ? $ac->text->notifications_active : esc_html__('Notifications are active', 'easy-form-builder'),

            /* translators: Configure settings to enable = status description */
            "configure_to_enable" => $state && isset($ac->text->configure_to_enable) ? $ac->text->configure_to_enable : esc_html__('Configure settings to enable', 'easy-form-builder'),

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

            /* translators: Save Settings = save button text */
            "save_settings" => $state && isset($ac->text->save_settings) ? $ac->text->save_settings : esc_html__('Save Settings', 'easy-form-builder'),

            /* translators: Refresh = refresh button text */
            "refresh" => $state && isset($ac->text->refresh) ? $ac->text->refresh : esc_html__('Refresh', 'easy-form-builder'),

            /* translators: Send Test Message = test button text */
            "send_test_message" => $state && isset($ac->text->send_test_message) ? $ac->text->send_test_message : esc_html__('Send Test Message', 'easy-form-builder'),

            /* translators: Quick Tips = tips section title */
            "quick_tips" => $state && isset($ac->text->quick_tips) ? $ac->text->quick_tips : esc_html__('Quick Tips', 'easy-form-builder'),

            /* translators: Tip 1 = first tip, contains %s placeholder for @BotFather link */
            "tip_1" => $state && isset($ac->text->tip_1) ? $ac->text->tip_1 : esc_html__('Create a bot using %s', 'easy-form-builder'),

            /* translators: Tip 2 = second tip */
            "tip_2" => $state && isset($ac->text->tip_2) ? $ac->text->tip_2 : esc_html__('Add your bot to the target chat', 'easy-form-builder'),

            /* translators: Tip 3 = third tip */
            "tip_3" => $state && isset($ac->text->tip_3) ? $ac->text->tip_3 : esc_html__('Use the test feature to verify setup', 'easy-form-builder'),

            /* translators: Tip 4 = fourth tip */
            "tip_4" => $state && isset($ac->text->tip_4) ? $ac->text->tip_4 : esc_html__('Monitor activity for troubleshooting', 'easy-form-builder'),

            /* translators: Tip 5 = fifth tip, contains %s placeholder for Chat ID finder link */
            "tip_5" => $state && isset($ac->text->tip_5) ? $ac->text->tip_5 : esc_html__('Find your Chat ID using %s', 'easy-form-builder'),

            /* translators: @BotFather = clickable link text (not translatable) */
            "botfather_link" => '@BotFather',

            /* translators: @WSYourIDBot = clickable link text (not translatable) */
            "chatid_finder_link" => '@WSYourIDBot',

            /* translators: Enter your test message here = placeholder */
            "enter_test_message" => $state && isset($ac->text->enter_test_message) ? $ac->text->enter_test_message : esc_html__('Enter your test message here...', 'easy-form-builder'),

            /* translators: Default test message content */
            "default_test_message" => $state && isset($ac->text->default_test_message) ? $ac->text->default_test_message : esc_html__("Test Message\n\nThis is a test message from Easy Form Builder.\n\nIf you receive this message, your Telegram integration is working correctly!", 'easy-form-builder'),

            /* translators: Test Information = info section title */
            "test_info" => $state && isset($ac->text->test_info) ? $ac->text->test_info : esc_html__('Test Information', 'easy-form-builder'),

            /* translators: Test description text */
            "test_description" => $state && isset($ac->text->test_description) ? $ac->text->test_description : esc_html__('Use this tab to send test messages and verify your Telegram bot configuration.', 'easy-form-builder'),

            /* translators: Test warning message */
            "test_warning" => $state && isset($ac->text->test_warning) ? $ac->text->test_warning : esc_html__('Make sure to save your settings before testing!', 'easy-form-builder'),

            /* translators: Activity Log = tab title */
            "activity_log" => $state && isset($ac->text->activity_log) ? $ac->text->activity_log : esc_html__('Activity Log', 'easy-form-builder'),

            /* translators: Date = column header */
            "date" => $state && isset($ac->text->date) ? $ac->text->date : esc_html__('Date', 'easy-form-builder'),

            /* translators: No activity found = empty state message */
            "no_activity" => $state && isset($ac->text->no_activity) ? $ac->text->no_activity : esc_html__('No activity found', 'easy-form-builder'),

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

            /* translators: Confirm reset dialog */
            "confirm_reset" => $state && isset($ac->text->confirm_reset) ? $ac->text->confirm_reset : esc_html__('Are you sure you want to reset all settings?', 'easy-form-builder'),

            /* translators: Confirm clear logs dialog */
            "confirm_clear" => $state && isset($ac->text->confirm_clear) ? $ac->text->confirm_clear : esc_html__('Are you sure you want to clear all activity logs?', 'easy-form-builder'),

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

    public static function get_sms_phrases($ac = null, $state = false) {
        return [

            /* translators: SMS Send & Manage Messages = subtitle in SMS header */
            "smsSendMessages" => $state && isset($ac->text->smsSendMessages) ? $ac->text->smsSendMessages : esc_html__('Send & Manage Messages', 'easy-form-builder'),

            /* translators: SMS intro description = description text under SMS header */
            "smsIntroDesc" => $state && isset($ac->text->smsIntroDesc) ? $ac->text->smsIntroDesc : esc_html__('Send SMS messages directly from your dashboard and track all sent messages.', 'easy-form-builder'),

            /* translators: Quick Send = feature badge label */
            "quickSend" => $state && isset($ac->text->quickSend) ? $ac->text->quickSend : esc_html__('Quick Send', 'easy-form-builder'),

            /* translators: Message History = tab title and feature badge */
            "messageHistory" => $state && isset($ac->text->messageHistory) ? $ac->text->messageHistory : esc_html__('Message History', 'easy-form-builder'),

            /* translators: Total Sent = label for total sent messages counter */
            "totalSent" => $state && isset($ac->text->totalSent) ? $ac->text->totalSent : esc_html__('Total Sent', 'easy-form-builder'),

            /* translators: Messages sent from your dashboard = hint under status card */
            "smsStatusHint" => $state && isset($ac->text->smsStatusHint) ? $ac->text->smsStatusHint : esc_html__('Messages sent from your dashboard', 'easy-form-builder'),

            /* translators: Send Message = tab title for composing an SMS */
            "sendMessage" => $state && isset($ac->text->sendMessage) ? $ac->text->sendMessage : esc_html__('Send Message', 'easy-form-builder'),

            /* translators: Compose and send an SMS message = subtitle on send card */
            "sendSmsDesc" => $state && isset($ac->text->sendSmsDesc) ? $ac->text->sendSmsDesc : esc_html__('Compose and send an SMS message', 'easy-form-builder'),

            /* translators: Type your message here... = textarea placeholder */
            "typeYourMessage" => $state && isset($ac->text->typeYourMessage) ? $ac->text->typeYourMessage : esc_html__('Type your message here...', 'easy-form-builder'),

            /* translators: Phone Number = label for phone input field */
            "phoneNumber" => $state && isset($ac->text->phoneNumber) ? $ac->text->phoneNumber : esc_html__('Phone Number', 'easy-form-builder'),

            /* translators: SMS message field hint text */
            "smsMessageHint" => $state && isset($ac->text->smsMessageHint) ? $ac->text->smsMessageHint : esc_html__('Write the message you want to send via SMS', 'easy-form-builder'),

            /* translators: SMS phone field hint text */
            "smsPhoneHint" => $state && isset($ac->text->smsPhoneHint) ? $ac->text->smsPhoneHint : esc_html__('Enter phone number with country code (e.g. +11234567890)', 'easy-form-builder'),

            /* translators: Quick Tips = title for tips sidebar */
            "quickTips" => $state && isset($ac->text->quickTips) ? $ac->text->quickTips : esc_html__('Quick Tips', 'easy-form-builder'),

            /* translators: SMS tip about international phone format */
            "smsTip1" => $state && isset($ac->text->smsTip1) ? $ac->text->smsTip1 : esc_html__('Use international format: +[country code][number]', 'easy-form-builder'),

            /* translators: SMS tip about provider configuration */
            "smsTip2" => $state && isset($ac->text->smsTip2) ? $ac->text->smsTip2 : esc_html__('Messages are sent via your configured SMS provider', 'easy-form-builder'),

            /* translators: SMS tip about message history */
            "smsTip3" => $state && isset($ac->text->smsTip3) ? $ac->text->smsTip3 : esc_html__('All sent messages are logged in the History tab', 'easy-form-builder'),

            /* translators: No messages sent yet = empty state message */
            "noRecordsFound" => $state && isset($ac->text->noRecordsFound) ? $ac->text->noRecordsFound : esc_html__('No messages sent yet', 'easy-form-builder'),

            /* translators: sent = status label for sent messages */
            "sent" => $state && isset($ac->text->sent) ? $ac->text->sent : esc_html__('sent', 'easy-form-builder'),

            /* translators: Learn how to use the SMS feature = help subtitle */
            "smsHelpDesc" => $state && isset($ac->text->smsHelpDesc) ? $ac->text->smsHelpDesc : esc_html__('Learn how to use the SMS feature', 'easy-form-builder'),

            /* translators: Quick Start Guide = help section title */
            "smsHelpQuickStart" => $state && isset($ac->text->smsHelpQuickStart) ? $ac->text->smsHelpQuickStart : esc_html__('Quick Start Guide', 'easy-form-builder'),

            /* translators: Step 1 of SMS quick start guide */
            "smsHelpStep1" => $state && isset($ac->text->smsHelpStep1) ? $ac->text->smsHelpStep1 : esc_html__('Go to EFB Settings and configure your SMS provider (e.g. WP SMS Pro)', 'easy-form-builder'),

            /* translators: Step 2 of SMS quick start guide */
            "smsHelpStep2" => $state && isset($ac->text->smsHelpStep2) ? $ac->text->smsHelpStep2 : esc_html__('Navigate to the "Send Message" tab', 'easy-form-builder'),

            /* translators: Step 3 of SMS quick start guide */
            "smsHelpStep3" => $state && isset($ac->text->smsHelpStep3) ? $ac->text->smsHelpStep3 : esc_html__('Type your message and enter the phone number with country code', 'easy-form-builder'),

            /* translators: Step 4 of SMS quick start guide */
            "smsHelpStep4" => $state && isset($ac->text->smsHelpStep4) ? $ac->text->smsHelpStep4 : esc_html__('Click "Send" — the message will appear in Message History', 'easy-form-builder'),

            /* translators: Important Notes = help section title */
            "smsHelpNotes" => $state && isset($ac->text->smsHelpNotes) ? $ac->text->smsHelpNotes : esc_html__('Important Notes', 'easy-form-builder'),

            /* translators: Note about country code requirement */
            "smsHelpNote1" => $state && isset($ac->text->smsHelpNote1) ? $ac->text->smsHelpNote1 : esc_html__('Phone numbers must include country code (e.g. +1 for US, +44 for UK)', 'easy-form-builder'),

            /* translators: Note about SMS provider requirement */
            "smsHelpNote2" => $state && isset($ac->text->smsHelpNote2) ? $ac->text->smsHelpNote2 : esc_html__('An SMS provider plugin must be installed and configured', 'easy-form-builder'),

            /* translators: Note about form notification settings */
            "smsHelpNote3" => $state && isset($ac->text->smsHelpNote3) ? $ac->text->smsHelpNote3 : esc_html__('SMS notifications for forms can be set up in each form\'s settings', 'easy-form-builder'),

            /* translators: Note about message history tab */
            "smsHelpNote4" => $state && isset($ac->text->smsHelpNote4) ? $ac->text->smsHelpNote4 : esc_html__('All sent messages are recorded in the Message History tab', 'easy-form-builder'),

            /* translators: Supported SMS Providers = help section title */
            "smsHelpProviders" => $state && isset($ac->text->smsHelpProviders) ? $ac->text->smsHelpProviders : esc_html__('Supported SMS Providers', 'easy-form-builder'),
        ];
    }

    public static function get_stripe_phrases($ac = null, $state = false) {
        return [

        ];
    }

    public static function get_autofill_phrases($ac = null, $state = false) {
        return [

            /* translators: Auto-Populate Dataset = menu title for dataset management */
            "autofill_dataset" => $state && isset($ac->text->autofill_dataset) ? $ac->text->autofill_dataset : esc_html__('Auto-Populate Dataset', 'easy-form-builder'),

            /* translators: Auto-Populate Integrations = menu title for API integrations */
            "autofill_integrations" => $state && isset($ac->text->autofill_integrations) ? $ac->text->autofill_integrations : esc_html__('Auto-Populate Integrations', 'easy-form-builder'),

            /* translators: Dataset page header subtitle */
            "datasetSubtitle" => $state && isset($ac->text->datasetSubtitle) ? $ac->text->datasetSubtitle : esc_html__('Manage & Upload Datasets', 'easy-form-builder'),

            /* translators: Feature badge: Upload */
            "uploadDataset" => $state && isset($ac->text->uploadDataset) ? $ac->text->uploadDataset : esc_html__('Upload CSV', 'easy-form-builder'),

            /* translators: Feature badge: Manage */
            "manageDatasets" => $state && isset($ac->text->manageDatasets) ? $ac->text->manageDatasets : esc_html__('Manage Datasets', 'easy-form-builder'),

            /* translators: Feature badge: Easy Edit */
            "easyEdit" => $state && isset($ac->text->easyEdit) ? $ac->text->easyEdit : esc_html__('Easy Edit', 'easy-form-builder'),

            /* translators: Total datasets status label */
            "totalDatasets" => $state && isset($ac->text->totalDatasets) ? $ac->text->totalDatasets : esc_html__('Total Datasets', 'easy-form-builder'),

            /* translators: Datasets count suffix */
            "datasets" => $state && isset($ac->text->datasets) ? $ac->text->datasets : esc_html__('Datasets', 'easy-form-builder'),

            /* translators: Dataset status hint */
            "datasetStatusHint" => $state && isset($ac->text->datasetStatusHint) ? $ac->text->datasetStatusHint : esc_html__('Datasets available for auto-populate', 'easy-form-builder'),

            /* translators: Tab: Datasets */
            "datasetsTab" => $state && isset($ac->text->datasetsTab) ? $ac->text->datasetsTab : esc_html__('Datasets', 'easy-form-builder'),

            /* translators: Tab: Help */
            "helpTab" => $state && isset($ac->text->helpTab) ? $ac->text->helpTab : esc_html__('Help', 'easy-form-builder'),

            /* translators: No datasets yet message */
            "noDatasetsYet" => $state && isset($ac->text->noDatasetsYet) ? $ac->text->noDatasetsYet : esc_html__('No datasets yet. Upload a CSV file to get started.', 'easy-form-builder'),

            /* translators: Dataset help title */
            "datasetHelpTitle" => $state && isset($ac->text->datasetHelpTitle) ? $ac->text->datasetHelpTitle : esc_html__('How to use Auto-Populate Datasets', 'easy-form-builder'),

            /* translators: Dataset help step 1 */
            "datasetHelpStep1" => $state && isset($ac->text->datasetHelpStep1) ? $ac->text->datasetHelpStep1 : esc_html__('Prepare a CSV file with column headers in the first row.', 'easy-form-builder'),

            /* translators: Dataset help step 1 title */
            "datasetHelpStep1Title" => $state && isset($ac->text->datasetHelpStep1Title) ? $ac->text->datasetHelpStep1Title : esc_html__('Prepare CSV', 'easy-form-builder'),

            /* translators: Dataset help step 2 */
            "datasetHelpStep2" => $state && isset($ac->text->datasetHelpStep2) ? $ac->text->datasetHelpStep2 : esc_html__('Upload the CSV file using the upload button.', 'easy-form-builder'),

            /* translators: Dataset help step 2 title */
            "datasetHelpStep2Title" => $state && isset($ac->text->datasetHelpStep2Title) ? $ac->text->datasetHelpStep2Title : esc_html__('Upload Dataset', 'easy-form-builder'),

            /* translators: Dataset help step 3 */
            "datasetHelpStep3" => $state && isset($ac->text->datasetHelpStep3) ? $ac->text->datasetHelpStep3 : esc_html__('In Form Builder, open Form Settings and enable "Auto-Populate". Choose your dataset, then map elements to dataset fields as search conditions.', 'easy-form-builder'),

            /* translators: Dataset help step 3 title */
            "datasetHelpStep3Title" => $state && isset($ac->text->datasetHelpStep3Title) ? $ac->text->datasetHelpStep3Title : esc_html__('Enable Auto-Populate', 'easy-form-builder'),

            /* translators: Dataset help step 4 */
            "datasetHelpStep4" => $state && isset($ac->text->datasetHelpStep4) ? $ac->text->datasetHelpStep4 : esc_html__('For each target field, enable "Auto-Populate to automatically populate this field" and select the dataset column to auto-populate.', 'easy-form-builder'),

            /* translators: Dataset help step 4 title */
            "datasetHelpStep4Title" => $state && isset($ac->text->datasetHelpStep4Title) ? $ac->text->datasetHelpStep4Title : esc_html__('Map Fields', 'easy-form-builder'),

            /* translators: Dataset help tip */
            "datasetHelpTip" => $state && isset($ac->text->datasetHelpTip) ? $ac->text->datasetHelpTip : esc_html__('You can edit dataset values inline by clicking on them.', 'easy-form-builder'),

            /* translators: Dataset help subtitle */
            "datasetHelpSubtitle" => $state && isset($ac->text->datasetHelpSubtitle) ? $ac->text->datasetHelpSubtitle : esc_html__('Follow these steps to set up auto-populate for your forms', 'easy-form-builder'),

            /* translators: Quick Start Guide title */
            "quickStartGuide" => $state && isset($ac->text->quickStartGuide) ? $ac->text->quickStartGuide : esc_html__('Quick Start Guide', 'easy-form-builder'),

            /* translators: Tips & Notes title */
            "tipsAndNotes" => $state && isset($ac->text->tipsAndNotes) ? $ac->text->tipsAndNotes : esc_html__('Tips & Notes', 'easy-form-builder'),

            /* translators: Editing Data section title */
            "editingData" => $state && isset($ac->text->editingData) ? $ac->text->editingData : esc_html__('Editing Data', 'easy-form-builder'),

            /* translators: Dataset tip about CSV format */
            "datasetTipCSV" => $state && isset($ac->text->datasetTipCSV) ? $ac->text->datasetTipCSV : esc_html__('Supported format: CSV with UTF-8 encoding.', 'easy-form-builder'),

            /* translators: Form Builder Tips section title */
            "formBuilderTips" => $state && isset($ac->text->formBuilderTips) ? $ac->text->formBuilderTips : esc_html__('Form Builder Tips', 'easy-form-builder'),

            /* translators: Dataset tip about conditions */
            "datasetTipCondition" => $state && isset($ac->text->datasetTipCondition) ? $ac->text->datasetTipCondition : esc_html__('Use conditions to filter dataset rows based on user input.', 'easy-form-builder'),

            /* translators: Dataset tip about multiple fields */
            "datasetTipMultiple" => $state && isset($ac->text->datasetTipMultiple) ? $ac->text->datasetTipMultiple : esc_html__('Multiple fields can auto-populate from the same dataset.', 'easy-form-builder'),

            /* translators: Dataset notice title */
            "datasetNoticeTitle" => $state && isset($ac->text->datasetNoticeTitle) ? $ac->text->datasetNoticeTitle : esc_html__('Good to know', 'easy-form-builder'),

            /* translators: Dataset notice text */
            "datasetNoticeText" => $state && isset($ac->text->datasetNoticeText) ? $ac->text->datasetNoticeText : esc_html__('Changes to the dataset take effect immediately for new form submissions.', 'easy-form-builder'),

            /* translators: API page subtitle */
            "apiSubtitle" => $state && isset($ac->text->apiSubtitle) ? $ac->text->apiSubtitle : esc_html__('Connect & Manage API Integrations', 'easy-form-builder'),

            /* translators: Feature badge: Real-time */
            "realTimeData" => $state && isset($ac->text->realTimeData) ? $ac->text->realTimeData : esc_html__('Real-time Data', 'easy-form-builder'),

            /* translators: Feature badge: Field Mapping */
            "fieldMappingFeature" => $state && isset($ac->text->fieldMappingFeature) ? $ac->text->fieldMappingFeature : esc_html__('Field Mapping', 'easy-form-builder'),

            /* translators: Feature badge: Smart Cache */
            "smartCache" => $state && isset($ac->text->smartCache) ? $ac->text->smartCache : esc_html__('Smart Cache', 'easy-form-builder'),

            /* translators: Total connections label */
            "totalConnections" => $state && isset($ac->text->totalConnections) ? $ac->text->totalConnections : esc_html__('Total Connections', 'easy-form-builder'),

            /* translators: Connections count suffix */
            "connections" => $state && isset($ac->text->connections) ? $ac->text->connections : esc_html__('Connections', 'easy-form-builder'),

            /* translators: API status hint */
            "apiStatusHint" => $state && isset($ac->text->apiStatusHint) ? $ac->text->apiStatusHint : esc_html__('API connections configured', 'easy-form-builder'),

            /* translators: Tab: Connections */
            "connectionsTab" => $state && isset($ac->text->connectionsTab) ? $ac->text->connectionsTab : esc_html__('Connections', 'easy-form-builder'),

            /* translators: Tab: Help */
            "apiHelpTab" => $state && isset($ac->text->apiHelpTab) ? $ac->text->apiHelpTab : esc_html__('Help', 'easy-form-builder'),

            /* translators: API help title */
            "apiHelpTitle" => $state && isset($ac->text->apiHelpTitle) ? $ac->text->apiHelpTitle : esc_html__('How to use API Integrations', 'easy-form-builder'),

            /* translators: API help subtitle */
            "apiHelpSubtitle" => $state && isset($ac->text->apiHelpSubtitle) ? $ac->text->apiHelpSubtitle : esc_html__('Follow these steps to connect your external API', 'easy-form-builder'),

            /* translators: API help step 1 */
            "apiHelpStep1" => $state && isset($ac->text->apiHelpStep1) ? $ac->text->apiHelpStep1 : esc_html__('Click "Add API Connection" to create a new connection and enter your API endpoint URL.', 'easy-form-builder'),

            /* translators: API help step 1 title */
            "apiHelpStep1Title" => $state && isset($ac->text->apiHelpStep1Title) ? $ac->text->apiHelpStep1Title : esc_html__('Add Connection', 'easy-form-builder'),

            /* translators: API help step 2 */
            "apiHelpStep2" => $state && isset($ac->text->apiHelpStep2) ? $ac->text->apiHelpStep2 : esc_html__('Configure authentication (API Key, Bearer Token, or Basic Auth) if your API requires it.', 'easy-form-builder'),

            /* translators: API help step 2 title */
            "apiHelpStep2Title" => $state && isset($ac->text->apiHelpStep2Title) ? $ac->text->apiHelpStep2Title : esc_html__('Authentication', 'easy-form-builder'),

            /* translators: API help step 3 */
            "apiHelpStep3" => $state && isset($ac->text->apiHelpStep3) ? $ac->text->apiHelpStep3 : esc_html__('Select a form and map API response fields to your form fields for auto-populate.', 'easy-form-builder'),

            /* translators: API help step 3 title */
            "apiHelpStep3Title" => $state && isset($ac->text->apiHelpStep3Title) ? $ac->text->apiHelpStep3Title : esc_html__('Map Fields', 'easy-form-builder'),

            /* translators: API help step 4 */
            "apiHelpStep4" => $state && isset($ac->text->apiHelpStep4) ? $ac->text->apiHelpStep4 : esc_html__('Use the Test button to verify the API returns expected data, then save your connection.', 'easy-form-builder'),

            /* translators: API help step 4 title */
            "apiHelpStep4Title" => $state && isset($ac->text->apiHelpStep4Title) ? $ac->text->apiHelpStep4Title : esc_html__('Test & Save', 'easy-form-builder'),

            /* translators: API help tip */
            "apiHelpTip" => $state && isset($ac->text->apiHelpTip) ? $ac->text->apiHelpTip : esc_html__('Use cache settings to reduce API calls and improve performance.', 'easy-form-builder'),

            /* translators: API tip - Performance section title */
            "apiTipPerformance" => $state && isset($ac->text->apiTipPerformance) ? $ac->text->apiTipPerformance : esc_html__('Performance', 'easy-form-builder'),

            /* translators: API tip - timeout */
            "apiTipTimeout" => $state && isset($ac->text->apiTipTimeout) ? $ac->text->apiTipTimeout : esc_html__('Set a reasonable timeout to prevent slow form loading.', 'easy-form-builder'),

            /* translators: API tip - Security section title */
            "apiTipSecurity" => $state && isset($ac->text->apiTipSecurity) ? $ac->text->apiTipSecurity : esc_html__('Security', 'easy-form-builder'),

            /* translators: API tip - HTTPS */
            "apiTipAuth" => $state && isset($ac->text->apiTipAuth) ? $ac->text->apiTipAuth : esc_html__('Always use HTTPS endpoints for secure data transfer.', 'easy-form-builder'),

            /* translators: API tip - keys */
            "apiTipKeys" => $state && isset($ac->text->apiTipKeys) ? $ac->text->apiTipKeys : esc_html__('Keep API keys confidential — they are stored securely in WordPress.', 'easy-form-builder'),

            /* translators: API notice title */
            "apiNoticeTitle" => $state && isset($ac->text->apiNoticeTitle) ? $ac->text->apiNoticeTitle : esc_html__('Good to know', 'easy-form-builder'),

            /* translators: API notice text */
            "apiNoticeText" => $state && isset($ac->text->apiNoticeText) ? $ac->text->apiNoticeText : esc_html__('API responses are fetched in real-time when the form loads. Enable caching for frequently accessed data.', 'easy-form-builder'),

            /* translators: External API Connections = section header */
            "external_api" => $state && isset($ac->text->external_api) ? $ac->text->external_api : esc_html__('External API Connections', 'easy-form-builder'),

            /* translators: Add New API Connection = button text */
            "add_new_api" => $state && isset($ac->text->add_new_api) ? $ac->text->add_new_api : esc_html__('Add New API Connection', 'easy-form-builder'),

            /* translators: Edit API Connection = modal title */
            "edit_api_connection" => $state && isset($ac->text->edit_api_connection) ? $ac->text->edit_api_connection : esc_html__('Edit API Connection', 'easy-form-builder'),

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

            /* translators: Custom Headers = section title */
            "custom_headers" => $state && isset($ac->text->custom_headers) ? $ac->text->custom_headers : esc_html__('Custom Headers', 'easy-form-builder'),

            /* translators: No custom headers message */
            "no_custom_headers" => $state && isset($ac->text->no_custom_headers) ? $ac->text->no_custom_headers : esc_html__('No custom headers', 'easy-form-builder'),

            /* translators: Query Parameters = section title */
            "query_params" => $state && isset($ac->text->query_params) ? $ac->text->query_params : esc_html__('Query Parameters', 'easy-form-builder'),

            /* translators: No query params message */
            "no_query_params" => $state && isset($ac->text->no_query_params) ? $ac->text->no_query_params : esc_html__('No query parameters', 'easy-form-builder'),

            /* translators: Request Body Template = field label */
            "request_body" => $state && isset($ac->text->request_body) ? $ac->text->request_body : esc_html__('Request Body Template (JSON)', 'easy-form-builder'),

            /* translators: Body template help */
            "body_template_help" => $state && isset($ac->text->body_template_help) ? $ac->text->body_template_help : esc_html__('JSON template for POST/PUT/PATCH requests. Use {{field_id}} for dynamic values.', 'easy-form-builder'),

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

            /* translators: Cache Settings = section title */
            "cache_settings" => $state && isset($ac->text->cache_settings) ? $ac->text->cache_settings : esc_html__('Cache Settings', 'easy-form-builder'),

            /* translators: Cache Duration = field label */
            "cache_duration" => $state && isset($ac->text->cache_duration) ? $ac->text->cache_duration : esc_html__('Cache Duration (minutes)', 'easy-form-builder'),

            /* translators: Cache duration help */
            "cache_duration_help" => $state && isset($ac->text->cache_duration_help) ? $ac->text->cache_duration_help : esc_html__('0 = no caching', 'easy-form-builder'),

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

            /* translators: No API connections message */
            "no_api_connections" => $state && isset($ac->text->no_api_connections) ? $ac->text->no_api_connections : esc_html__('No API connections yet. Click "Add New API Connection" to create one.', 'easy-form-builder'),

            /* translators: Confirm delete message */
            "confirm_delete" => $state && isset($ac->text->confirm_delete) ? $ac->text->confirm_delete : esc_html__('Are you sure you want to delete', 'easy-form-builder'),

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

            "apiIntroTitle" => $state && isset($ac->text->api_intro_title) ? $ac->text->api_intro_title : esc_html__('Connect Your Forms to External APIs', 'easy-form-builder'),
            "apiIntroDesc" => $state && isset($ac->text->api_intro_desc) ? $ac->text->api_intro_desc : esc_html__('Easily auto-populate your form fields with data from any API. Just add your API endpoint and map the fields!', 'easy-form-builder'),
            "clickToAdd" => $state && isset($ac->text->click_to_add) ? $ac->text->click_to_add : esc_html__('Click the button above to create your first API connection', 'easy-form-builder'),

            "step1Label" => $state && isset($ac->text->step1_label) ? $ac->text->step1_label : esc_html__('Basic Info', 'easy-form-builder'),
            "step2Label" => $state && isset($ac->text->step2_label) ? $ac->text->step2_label : esc_html__('Authentication', 'easy-form-builder'),
            "step3Label" => $state && isset($ac->text->step3_label) ? $ac->text->step3_label : esc_html__('Field Mapping', 'easy-form-builder'),
            "step4Label" => $state && isset($ac->text->step4_label) ? $ac->text->step4_label : esc_html__('Test & Save', 'easy-form-builder'),

            "basicInfoTitle" => $state && isset($ac->text->basic_info_title) ? $ac->text->basic_info_title : esc_html__('Basic API Information', 'easy-form-builder'),
            "connectionNameHelp" => $state && isset($ac->text->connection_name_help) ? $ac->text->connection_name_help : esc_html__('A friendly name to identify this API connection', 'easy-form-builder'),
            "getMethodDesc" => $state && isset($ac->text->get_method_desc) ? $ac->text->get_method_desc : esc_html__('Fetch data', 'easy-form-builder'),
            "postMethodDesc" => $state && isset($ac->text->post_method_desc) ? $ac->text->post_method_desc : esc_html__('Send data', 'easy-form-builder'),
            "requestBodyHelp" => $state && isset($ac->text->request_body_help) ? $ac->text->request_body_help : esc_html__('JSON body for POST requests. Use {{field_id}} for dynamic values.', 'easy-form-builder'),

            "authTitle" => $state && isset($ac->text->auth_title) ? $ac->text->auth_title : esc_html__('Authentication Settings', 'easy-form-builder'),
            "authInfo" => $state && isset($ac->text->auth_info) ? $ac->text->auth_info : esc_html__('If your API requires authentication, select the type below. Otherwise, leave it as "No Authentication".', 'easy-form-builder'),
            "noAuthDesc" => $state && isset($ac->text->no_auth_desc) ? $ac->text->no_auth_desc : esc_html__('API is public', 'easy-form-builder'),
            "bearerDesc" => $state && isset($ac->text->bearer_desc) ? $ac->text->bearer_desc : esc_html__('JWT or OAuth tokens', 'easy-form-builder'),
            "apiKeyDesc" => $state && isset($ac->text->api_key_desc) ? $ac->text->api_key_desc : esc_html__('X-API-Key header', 'easy-form-builder'),
            "basicDesc" => $state && isset($ac->text->basic_desc) ? $ac->text->basic_desc : esc_html__('Username:Password', 'easy-form-builder'),
            "addHeader" => $state && isset($ac->text->add_header) ? $ac->text->add_header : esc_html__('Add Header', 'easy-form-builder'),
            "noHeaders" => $state && isset($ac->text->no_headers) ? $ac->text->no_headers : esc_html__('No custom headers', 'easy-form-builder'),
            "optional" => $state && isset($ac->text->optional) ? $ac->text->optional : esc_html__('Optional', 'easy-form-builder'),

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
            "targetFieldsTitle" => $state && isset($ac->text->target_fields_title) ? $ac->text->target_fields_title : esc_html__('Target Fields (Auto-Populate)', 'easy-form-builder'),
            "targetFieldsInfo" => $state && isset($ac->text->target_fields_info) ? $ac->text->target_fields_info : esc_html__('Map API response to form fields', 'easy-form-builder'),
            "apiFieldName" => $state && isset($ac->text->api_field_name) ? $ac->text->api_field_name : esc_html__('API Response Field', 'easy-form-builder'),
            "formFieldSelect" => $state && isset($ac->text->form_field_select) ? $ac->text->form_field_select : esc_html__('Form Field to Fill', 'easy-form-builder'),
            "addMapping" => $state && isset($ac->text->add_mapping) ? $ac->text->add_mapping : esc_html__('Add Mapping', 'easy-form-builder'),
            "selectField" => $state && isset($ac->text->select_field) ? $ac->text->select_field : esc_html__('— Select Field —', 'easy-form-builder'),
            "fieldsSelected" => $state && isset($ac->text->fields_selected) ? $ac->text->fields_selected : esc_html__('selected', 'easy-form-builder'),

            "cacheHelp" => $state && isset($ac->text->cache_help) ? $ac->text->cache_help : esc_html__('Cache API responses to improve performance', 'easy-form-builder'),
            "noCache" => $state && isset($ac->text->no_cache) ? $ac->text->no_cache : esc_html__('No caching', 'easy-form-builder'),
            "minutes" => $state && isset($ac->text->minutes) ? $ac->text->minutes : esc_html__('minutes', 'easy-form-builder'),
            "hour" => $state && isset($ac->text->hour) ? $ac->text->hour : esc_html__('hour', 'easy-form-builder'),

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
            "save" => $state && isset($ac->text->save) ? $ac->text->save : esc_html__('Save', 'easy-form-builder'),
            "back" => $state && isset($ac->text->back) ? $ac->text->back : esc_html__('Back', 'easy-form-builder'),
            "edit" => $state && isset($ac->text->edit) ? $ac->text->edit : esc_html__('Edit', 'easy-form-builder'),
            "test" => $state && isset($ac->text->test) ? $ac->text->test : esc_html__('Test', 'easy-form-builder'),
            "enabled" => $state && isset($ac->text->enabled) ? $ac->text->enabled : esc_html__('Active', 'easy-form-builder'),
            "disabled" => $state && isset($ac->text->disabled) ? $ac->text->disabled : esc_html__('Inactive', 'easy-form-builder'),

            "nameRequired" => $state && isset($ac->text->name_required) ? $ac->text->name_required : esc_html__('Please enter a connection name', 'easy-form-builder'),
            "urlRequired" => $state && isset($ac->text->url_required) ? $ac->text->url_required : esc_html__('Please enter an API endpoint URL', 'easy-form-builder'),
            "invalidUrl" => $state && isset($ac->text->invalid_url) ? $ac->text->invalid_url : esc_html__('Please enter a valid URL starting with http:// or https://', 'easy-form-builder'),
            "fillRequired" => $state && isset($ac->text->fill_required) ? $ac->text->fill_required : esc_html__('Please fill all required fields', 'easy-form-builder'),
            "loading" => $state && isset($ac->text->loading) ? $ac->text->loading : esc_html__('Loading...', 'easy-form-builder'),

            "savedSuccess" => $state && isset($ac->text->saved_success) ? $ac->text->saved_success : esc_html__('Connection saved successfully!', 'easy-form-builder'),
            "saveFailed" => $state && isset($ac->text->save_failed) ? $ac->text->save_failed : esc_html__('Failed to save connection', 'easy-form-builder'),
            "errorOccurred" => $state && isset($ac->text->error_occurred) ? $ac->text->error_occurred : esc_html__('An error occurred', 'easy-form-builder'),
            "connectionSuccess" => $state && isset($ac->text->connection_success) ? $ac->text->connection_success : esc_html__('Connection successful!', 'easy-form-builder'),
            "connectionFailed" => $state && isset($ac->text->connection_failed) ? $ac->text->connection_failed : esc_html__('Connection failed', 'easy-form-builder'),
            "testFailed" => $state && isset($ac->text->test_failed) ? $ac->text->test_failed : esc_html__('Test failed. Please check the URL and try again.', 'easy-form-builder'),
            "deleteSuccess" => $state && isset($ac->text->delete_success) ? $ac->text->delete_success : esc_html__('Connection deleted', 'easy-form-builder'),
            "formNotFound" => $state && isset($ac->text->form_not_found) ? $ac->text->form_not_found : esc_html__('Form data not found. Please refresh the page.', 'easy-form-builder'),
            "noFieldsFound" => $state && isset($ac->text->no_fields_found) ? $ac->text->no_fields_found : esc_html__('No fillable fields found in this form', 'easy-form-builder'),

            "atfllApiActive" => $state && isset($ac->text->atfll_api_active) ? $ac->text->atfll_api_active : esc_html__('API Auto-Populate Integration is Active', 'easy-form-builder'),
            "atfllApiActiveDesc" => $state && isset($ac->text->atfll_api_active_desc) ? $ac->text->atfll_api_active_desc : esc_html__('This form uses External API Auto-Populate. To configure settings, go to', 'easy-form-builder'),
            "atfllApiLink" => $state && isset($ac->text->atfll_api_link) ? $ac->text->atfll_api_link : esc_html__('Auto-Populate Integrations', 'easy-form-builder'),
            "atfllApiFieldBadge" => $state && isset($ac->text->atfll_api_field_badge) ? $ac->text->atfll_api_field_badge : esc_html__('Auto-filled via External API', 'easy-form-builder'),

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

    public static function get_payment_phrases($ac = null, $state = false) {
        return [

            /* translators: Payments = admin page title */

            "paypals" => $state && isset($ac->text->paypals) ? $ac->text->paypals : esc_html__('PayPal %s', 'easy-form-builder'),

            "stripes" => $state && isset($ac->text->stripes) ? $ac->text->stripes : esc_html__('Stripe %s', 'easy-form-builder'),

            "pay_payments" => $state && isset($ac->text->pay_payments) ? $ac->text->pay_payments : esc_html__('Payments', 'easy-form-builder'),

            /* translators: Search = search input placeholder */
            "pay_search" => $state && isset($ac->text->pay_search) ? $ac->text->pay_search : esc_html__('Search', 'easy-form-builder'),

            /* translators: You do not have permission to access this page */
            "pay_no_permission" => $state && isset($ac->text->pay_no_permission) ? $ac->text->pay_no_permission : esc_html__('You do not have permission to access this page.', 'easy-form-builder'),

            /* translators: All Statuses = filter dropdown */
            "pay_allStatuses" => $state && isset($ac->text->pay_allStatuses) ? $ac->text->pay_allStatuses : esc_html__('All Statuses', 'easy-form-builder'),

            /* translators: All Forms = filter dropdown */
            "pay_allForms" => $state && isset($ac->text->pay_allForms) ? $ac->text->pay_allForms : esc_html__('All Forms', 'easy-form-builder'),

            /* translators: Active = payment status */
            "pay_active" => $state && isset($ac->text->pay_active) ? $ac->text->pay_active : esc_html__('Active', 'easy-form-builder'),

            /* translators: Completed = payment status */
            "pay_completed" => $state && isset($ac->text->pay_completed) ? $ac->text->pay_completed : esc_html__('Completed', 'easy-form-builder'),

            /* translators: Pending = payment status */
            "pay_pending" => $state && isset($ac->text->pay_pending) ? $ac->text->pay_pending : esc_html__('Pending', 'easy-form-builder'),

            /* translators: Cancelled = payment status */
            "pay_cancelled" => $state && isset($ac->text->pay_cancelled) ? $ac->text->pay_cancelled : esc_html__('Cancelled', 'easy-form-builder'),

            /* translators: Refunded = payment status */
            "pay_refunded" => $state && isset($ac->text->pay_refunded) ? $ac->text->pay_refunded : esc_html__('Refunded', 'easy-form-builder'),

            /* translators: Failed = payment status */
            "pay_failed" => $state && isset($ac->text->pay_failed) ? $ac->text->pay_failed : esc_html__('Failed', 'easy-form-builder'),

            /* translators: No payments found = empty table message */
            "pay_noPayments" => $state && isset($ac->text->pay_noPayments) ? $ac->text->pay_noPayments : esc_html__('No payments found.', 'easy-form-builder'),

            /* translators: Loading... = loading indicator */
            "pay_loading" => $state && isset($ac->text->pay_loading) ? $ac->text->pay_loading : esc_html__('Loading...', 'easy-form-builder'),

            /* translators: View Details = action button */
            "pay_viewDetails" => $state && isset($ac->text->pay_viewDetails) ? $ac->text->pay_viewDetails : esc_html__('View Details', 'easy-form-builder'),

            /* translators: Refund = action button */
            "pay_refund" => $state && isset($ac->text->pay_refund) ? $ac->text->pay_refund : esc_html__('Refund', 'easy-form-builder'),

            /* translators: Cancel Subscription = action button */
            "pay_cancelSub" => $state && isset($ac->text->pay_cancelSub) ? $ac->text->pay_cancelSub : esc_html__('Cancel Subscription', 'easy-form-builder'),

            /* translators: Are you sure you want to refund this payment? = confirm dialog */
            "pay_confirmRefund" => $state && isset($ac->text->pay_confirmRefund) ? $ac->text->pay_confirmRefund : esc_html__('Are you sure you want to refund this payment?', 'easy-form-builder'),

            /* translators: Are you sure you want to cancel this subscription? = confirm dialog */
            "pay_confirmCancel" => $state && isset($ac->text->pay_confirmCancel) ? $ac->text->pay_confirmCancel : esc_html__('Are you sure you want to cancel this subscription?', 'easy-form-builder'),

            /* translators: Success = success message */
            "pay_success" => $state && isset($ac->text->pay_success) ? $ac->text->pay_success : esc_html__('Success', 'easy-form-builder'),

            /* translators: Error = error message */
            "pay_error" => $state && isset($ac->text->pay_error) ? $ac->text->pay_error : esc_html__('Error', 'easy-form-builder'),

            /* translators: Close = close button */
            "pay_close" => $state && isset($ac->text->pay_close) ? $ac->text->pay_close : esc_html__('Close', 'easy-form-builder'),

            /* translators: Payment refunded successfully = success toast */
            "pay_refundSuccess" => $state && isset($ac->text->pay_refundSuccess) ? $ac->text->pay_refundSuccess : esc_html__('Payment refunded successfully.', 'easy-form-builder'),

            /* translators: Subscription cancelled successfully = success toast */
            "pay_cancelSuccess" => $state && isset($ac->text->pay_cancelSuccess) ? $ac->text->pay_cancelSuccess : esc_html__('Subscription cancelled successfully.', 'easy-form-builder'),

            /* translators: Copied = clipboard copy toast */
            "pay_copied" => $state && isset($ac->text->pay_copied) ? $ac->text->pay_copied : esc_html__('Copied', 'easy-form-builder'),

            /* translators: Transaction ID = field label */
            "pay_transactionId" => $state && isset($ac->text->pay_transactionId) ? $ac->text->pay_transactionId : esc_html__('Transaction ID', 'easy-form-builder'),

            /* translators: Subscription ID = field label */
            "pay_subscriptionId" => $state && isset($ac->text->pay_subscriptionId) ? $ac->text->pay_subscriptionId : esc_html__('Subscription ID', 'easy-form-builder'),

            /* translators: Plan ID = field label */
            "pay_planId" => $state && isset($ac->text->pay_planId) ? $ac->text->pay_planId : esc_html__('Plan ID', 'easy-form-builder'),

            /* translators: Capture ID = field label */
            "pay_captureId" => $state && isset($ac->text->pay_captureId) ? $ac->text->pay_captureId : esc_html__('Capture ID', 'easy-form-builder'),

            /* translators: Amount = field label */
            "pay_amount" => $state && isset($ac->text->pay_amount) ? $ac->text->pay_amount : esc_html__('Amount', 'easy-form-builder'),

            /* translators: Currency = field label */
            "pay_currency" => $state && isset($ac->text->pay_currency) ? $ac->text->pay_currency : esc_html__('Currency', 'easy-form-builder'),

            /* translators: Status = field label */
            "pay_status" => $state && isset($ac->text->pay_status) ? $ac->text->pay_status : esc_html__('Status', 'easy-form-builder'),

            /* translators: Payment Type = field label */
            "pay_paymentType" => $state && isset($ac->text->pay_paymentType) ? $ac->text->pay_paymentType : esc_html__('Payment Type', 'easy-form-builder'),

            /* translators: Interval = field label */
            "pay_interval" => $state && isset($ac->text->pay_interval) ? $ac->text->pay_interval : esc_html__('Interval', 'easy-form-builder'),

            /* translators: Payer Email = field label */
            "pay_payerEmail" => $state && isset($ac->text->pay_payerEmail) ? $ac->text->pay_payerEmail : esc_html__('Payer Email', 'easy-form-builder'),

            /* translators: Payer Name = field label */
            "pay_payerName" => $state && isset($ac->text->pay_payerName) ? $ac->text->pay_payerName : esc_html__('Payer Name', 'easy-form-builder'),

            /* translators: Form = column header */
            "pay_formName" => $state && isset($ac->text->pay_formName) ? $ac->text->pay_formName : esc_html__('Form', 'easy-form-builder'),

            /* translators: Date = column header */
            "pay_date" => $state && isset($ac->text->pay_date) ? $ac->text->pay_date : esc_html__('Date', 'easy-form-builder'),

            /* translators: Tracking Code = field label */
            "pay_trackCode" => $state && isset($ac->text->pay_trackCode) ? $ac->text->pay_trackCode : esc_html__('Tracking Code', 'easy-form-builder'),

            /* translators: User = column header */
            "pay_user" => $state && isset($ac->text->pay_user) ? $ac->text->pay_user : esc_html__('User', 'easy-form-builder'),

            /* translators: One-time = payment type label */
            "pay_oneTime" => $state && isset($ac->text->pay_oneTime) ? $ac->text->pay_oneTime : esc_html__('One-time', 'easy-form-builder'),

            /* translators: Subscription = payment type label */
            "pay_subscription" => $state && isset($ac->text->pay_subscription) ? $ac->text->pay_subscription : esc_html__('Subscription', 'easy-form-builder'),

            /* translators: Daily = interval label */
            "pay_daily" => $state && isset($ac->text->pay_daily) ? $ac->text->pay_daily : esc_html__('Daily', 'easy-form-builder'),

            /* translators: Weekly = interval label */
            "pay_weekly" => $state && isset($ac->text->pay_weekly) ? $ac->text->pay_weekly : esc_html__('Weekly', 'easy-form-builder'),

            /* translators: Monthly = interval label */
            "pay_monthly" => $state && isset($ac->text->pay_monthly) ? $ac->text->pay_monthly : esc_html__('Monthly', 'easy-form-builder'),

            /* translators: Yearly = interval label */
            "pay_yearly" => $state && isset($ac->text->pay_yearly) ? $ac->text->pay_yearly : esc_html__('Yearly', 'easy-form-builder'),

            /* translators: Payment Details = modal title */
            "pay_paymentDetails" => $state && isset($ac->text->pay_paymentDetails) ? $ac->text->pay_paymentDetails : esc_html__('Payment Details', 'easy-form-builder'),

            /* translators: Actions = column header */
            "pay_actions" => $state && isset($ac->text->pay_actions) ? $ac->text->pay_actions : esc_html__('Actions', 'easy-form-builder'),

            /* translators: Subscription Details = modal title */
            "pay_subscriptionDetail" => $state && isset($ac->text->pay_subscriptionDetail) ? $ac->text->pay_subscriptionDetail : esc_html__('Subscription Details', 'easy-form-builder'),

            /* translators: Next Billing Date = field label */
            "pay_nextBilling" => $state && isset($ac->text->pay_nextBilling) ? $ac->text->pay_nextBilling : esc_html__('Next Billing Date', 'easy-form-builder'),

            /* translators: Export = export button */
            "pay_export" => $state && isset($ac->text->pay_export) ? $ac->text->pay_export : esc_html__('Export', 'easy-form-builder'),

            /* translators: Total = total label */
            "pay_total" => $state && isset($ac->text->pay_total) ? $ac->text->pay_total : esc_html__('Total', 'easy-form-builder'),

            /* translators: of = pagination separator */
            "pay_of" => $state && isset($ac->text->pay_of) ? $ac->text->pay_of : esc_html__('of', 'easy-form-builder'),

            /* translators: Page = pagination label */
            "pay_page" => $state && isset($ac->text->pay_page) ? $ac->text->pay_page : esc_html__('Page', 'easy-form-builder'),

            /* translators: Previous = pagination button */
            "pay_previous" => $state && isset($ac->text->pay_previous) ? $ac->text->pay_previous : esc_html__('Previous', 'easy-form-builder'),

            /* translators: Next = pagination button */
            "pay_next" => $state && isset($ac->text->pay_next) ? $ac->text->pay_next : esc_html__('Next', 'easy-form-builder'),

            /* translators: Rows per page = pagination label */
            "pay_rowsPerPage" => $state && isset($ac->text->pay_rowsPerPage) ? $ac->text->pay_rowsPerPage : esc_html__('Rows per page', 'easy-form-builder'),

            /* translators: Suspended = status label */
            "pay_suspended" => $state && isset($ac->text->pay_suspended) ? $ac->text->pay_suspended : esc_html__('Suspended', 'easy-form-builder'),

            /* translators: Suspend Subscription = action button */
            "pay_suspendSub" => $state && isset($ac->text->pay_suspendSub) ? $ac->text->pay_suspendSub : esc_html__('Suspend Subscription', 'easy-form-builder'),

            /* translators: Reactivate Subscription = action button */
            "pay_reactivateSub" => $state && isset($ac->text->pay_reactivateSub) ? $ac->text->pay_reactivateSub : esc_html__('Reactivate Subscription', 'easy-form-builder'),

            /* translators: Are you sure you want to suspend this subscription? = confirm dialog */
            "pay_confirmSuspend" => $state && isset($ac->text->pay_confirmSuspend) ? $ac->text->pay_confirmSuspend : esc_html__('Are you sure you want to suspend this subscription?', 'easy-form-builder'),

            /* translators: Are you sure you want to reactivate this subscription? = confirm dialog */
            "pay_confirmReactivate" => $state && isset($ac->text->pay_confirmReactivate) ? $ac->text->pay_confirmReactivate : esc_html__('Are you sure you want to reactivate this subscription?', 'easy-form-builder'),

            /* translators: Subscription suspended successfully = success toast */
            "pay_suspendSuccess" => $state && isset($ac->text->pay_suspendSuccess) ? $ac->text->pay_suspendSuccess : esc_html__('Subscription suspended successfully.', 'easy-form-builder'),

            /* translators: Subscription reactivated successfully = success toast */
            "pay_reactivateSuccess" => $state && isset($ac->text->pay_reactivateSuccess) ? $ac->text->pay_reactivateSuccess : esc_html__('Subscription reactivated successfully.', 'easy-form-builder'),

            /* translators: Payment Management Dashboard = header subtitle */
            "pay_headerSubtitle" => $state && isset($ac->text->pay_headerSubtitle) ? $ac->text->pay_headerSubtitle : esc_html__('Payment Management Dashboard', 'easy-form-builder'),

            /* translators: %s = gateway name (PayPal/Stripe). Header description */
            "pay_headerDesc" => $state && isset($ac->text->pay_headerDesc) ? $ac->text->pay_headerDesc : esc_html__('Manage and track all %s transactions. View payment details, process refunds, and export records.', 'easy-form-builder'),

            /* translators: Secure = feature badge in header */
            "pay_secure" => $state && isset($ac->text->pay_secure) ? $ac->text->pay_secure : esc_html__('Secure', 'easy-form-builder'),

            /* translators: Download payment records = export hint in header */
            "pay_downloadRecords" => $state && isset($ac->text->pay_downloadRecords) ? $ac->text->pay_downloadRecords : esc_html__('Download payment records', 'easy-form-builder'),

            /* translators: Start = subscription start date label */
            "pay_start" => $state && isset($ac->text->pay_start) ? $ac->text->pay_start : esc_html__('Start', 'easy-form-builder'),

            /* translators: Last Payment = subscription last payment label */
            "pay_lastPayment" => $state && isset($ac->text->pay_lastPayment) ? $ac->text->pay_lastPayment : esc_html__('Last Payment', 'easy-form-builder'),

            /* translators: Copy = copy button tooltip */
            "pay_copy" => $state && isset($ac->text->pay_copy) ? $ac->text->pay_copy : esc_html__('Copy', 'easy-form-builder'),

            /* translators: Powered by = footer text */
            "pay_poweredBy" => $state && isset($ac->text->pay_poweredBy) ? $ac->text->pay_poweredBy : esc_html__('Powered by', 'easy-form-builder'),

            /* translators: and Bootstrap Icon. Created by = footer segment */
            "pay_createdBy" => $state && isset($ac->text->pay_createdBy) ? $ac->text->pay_createdBy : esc_html__('and Bootstrap Icon. Created by', 'easy-form-builder'),

            /* translators: Easy Form Builder = brand name */
            "pay_brandName" => $state && isset($ac->text->pay_brandName) ? $ac->text->pay_brandName : esc_html__('Easy Form Builder', 'easy-form-builder'),

            /* translators: Please Wait = loading overlay */
            "pay_pleaseWait" => $state && isset($ac->text->pay_pleaseWait) ? $ac->text->pay_pleaseWait : esc_html__('Please Wait', 'easy-form-builder'),
        ];
    }

    public static function get_webhook_phrases($ac = null, $state = false) {
        return [

        ];
    }

    /**
     * Google Sheet add-on phrases. Consumed by the Google Sheet admin pages
     * (settings + logs) and localized into the wizard JS as `efb_google_sheet.text`.
     * Every visible string in vendor/googlesheet lives here so it can be translated
     * from the central phrase editor. Strings with %s/%d are format templates the
     * JS fills in with String.replace().
     */
    public static function get_googlesheet_phrases($ac = null, $state = false) {
        return [

            /* ===== Header (settings page) ===== */
            /* translators: Google Sheet page main title (brand) */
            "gs_title" => $state && isset($ac->text->gs_title) ? $ac->text->gs_title : esc_html__('Google Sheet', 'easy-form-builder'),
            /* translators: Google Sheet page subtitle */
            "gs_subtitle" => $state && isset($ac->text->gs_subtitle) ? $ac->text->gs_subtitle : esc_html__('Sync Form Responses to Google Sheets', 'easy-form-builder'),
            /* translators: Google Sheet page intro description */
            "gs_headerDesc" => $state && isset($ac->text->gs_headerDesc) ? $ac->text->gs_headerDesc : esc_html__('Automatically send form submissions to your Google Sheets. Connect one or more service accounts, link a sheet to each form, and let data flow in real time — no webhook or browser login required.', 'easy-form-builder'),
            /* translators: Connection status badge — connected */
            "gs_connected" => $state && isset($ac->text->gs_connected) ? $ac->text->gs_connected : esc_html__('Connected', 'easy-form-builder'),
            /* translators: Connection status badge — not connected */
            "gs_notConnected" => $state && isset($ac->text->gs_notConnected) ? $ac->text->gs_notConnected : esc_html__('Not Connected', 'easy-form-builder'),
            /* translators: Feature badge — automatic sync */
            "gs_autoSync" => $state && isset($ac->text->gs_autoSync) ? $ac->text->gs_autoSync : esc_html__('Auto Sync', 'easy-form-builder'),
            /* translators: Feature badge — automatic header row */
            "gs_autoHeader" => $state && isset($ac->text->gs_autoHeader) ? $ac->text->gs_autoHeader : esc_html__('Auto Header', 'easy-form-builder'),
            /* translators: Navigation chip linking to the sync logs page */
            "gs_syncLogsChip" => $state && isset($ac->text->gs_syncLogsChip) ? $ac->text->gs_syncLogsChip : esc_html__('Sync Logs', 'easy-form-builder'),
            /* translators: Navigation chip linking back to the settings page */
            "gs_settingsChip" => $state && isset($ac->text->gs_settingsChip) ? $ac->text->gs_settingsChip : esc_html__('Google Sheet Settings', 'easy-form-builder'),
            /* translators: Header status card label */
            "gs_activeBindings" => $state && isset($ac->text->gs_activeBindings) ? $ac->text->gs_activeBindings : esc_html__('Active Bindings', 'easy-form-builder'),
            /* translators: Header status value — singular form count */
            "gs_formSingular" => $state && isset($ac->text->gs_formSingular) ? $ac->text->gs_formSingular : esc_html__('Form', 'easy-form-builder'),
            /* translators: Header status value — plural form count */
            "gs_formPlural" => $state && isset($ac->text->gs_formPlural) ? $ac->text->gs_formPlural : esc_html__('Forms', 'easy-form-builder'),
            /* translators: %d = number of connected service accounts (singular) */
            "gs_saConnected" => $state && isset($ac->text->gs_saConnected) ? $ac->text->gs_saConnected : esc_html__('%d service account connected', 'easy-form-builder'),
            /* translators: %d = number of connected service accounts (plural) */
            "gs_saConnectedPlural" => $state && isset($ac->text->gs_saConnectedPlural) ? $ac->text->gs_saConnectedPlural : esc_html__('%d service accounts connected', 'easy-form-builder'),
            /* translators: Header hint when no service account is connected */
            "gs_noSaYet" => $state && isset($ac->text->gs_noSaYet) ? $ac->text->gs_noSaYet : esc_html__('No service account connected yet', 'easy-form-builder'),

            /* ===== Tabs ===== */
            /* translators: Tab title — service account connections */
            "gs_tabConnections" => $state && isset($ac->text->gs_tabConnections) ? $ac->text->gs_tabConnections : esc_html__('Connections', 'easy-form-builder'),
            /* translators: Tab title — form-to-sheet bindings */
            "gs_tabBindings" => $state && isset($ac->text->gs_tabBindings) ? $ac->text->gs_tabBindings : esc_html__('Form Bindings', 'easy-form-builder'),
            /* translators: Tab title — help and guide */
            "gs_tabHelp" => $state && isset($ac->text->gs_tabHelp) ? $ac->text->gs_tabHelp : esc_html__('Help & Guide', 'easy-form-builder'),

            /* ===== Common actions / status ===== */
            /* translators: Generic error message */
            "gs_error" => $state && isset($ac->text->gs_error) ? $ac->text->gs_error : esc_html__('Something went wrong.', 'easy-form-builder'),
            /* translators: Saved success message */
            "gs_saved" => $state && isset($ac->text->gs_saved) ? $ac->text->gs_saved : esc_html__('Saved successfully.', 'easy-form-builder'),
            /* translators: Save button */
            "gs_save" => $state && isset($ac->text->gs_save) ? $ac->text->gs_save : esc_html__('Save', 'easy-form-builder'),
            /* translators: Saved short label */
            "gs_savedShort" => $state && isset($ac->text->gs_savedShort) ? $ac->text->gs_savedShort : esc_html__('Saved', 'easy-form-builder'),
            /* translators: Cancel button */
            "gs_cancel" => $state && isset($ac->text->gs_cancel) ? $ac->text->gs_cancel : esc_html__('Cancel', 'easy-form-builder'),
            /* translators: Edit button */
            "gs_edit" => $state && isset($ac->text->gs_edit) ? $ac->text->gs_edit : esc_html__('Edit', 'easy-form-builder'),
            /* translators: Delete button */
            "gs_delete" => $state && isset($ac->text->gs_delete) ? $ac->text->gs_delete : esc_html__('Delete', 'easy-form-builder'),
            /* translators: Remove button */
            "gs_remove" => $state && isset($ac->text->gs_remove) ? $ac->text->gs_remove : esc_html__('Remove', 'easy-form-builder'),
            /* translators: Back button */
            "gs_back" => $state && isset($ac->text->gs_back) ? $ac->text->gs_back : esc_html__('Back', 'easy-form-builder'),
            /* translators: Next button */
            "gs_next" => $state && isset($ac->text->gs_next) ? $ac->text->gs_next : esc_html__('Next', 'easy-form-builder'),
            /* translators: Open link label */
            "gs_open" => $state && isset($ac->text->gs_open) ? $ac->text->gs_open : esc_html__('Open', 'easy-form-builder'),
            /* translators: Open in Google Sheets link label */
            "gs_openInSheets" => $state && isset($ac->text->gs_openInSheets) ? $ac->text->gs_openInSheets : esc_html__('Open in Google Sheets', 'easy-form-builder'),
            /* translators: Test Connection button */
            "gs_testConnection" => $state && isset($ac->text->gs_testConnection) ? $ac->text->gs_testConnection : esc_html__('Test Connection', 'easy-form-builder'),

            /* ===== Connections tab ===== */
            /* translators: Section card title — service accounts */
            "gs_serviceAccounts" => $state && isset($ac->text->gs_serviceAccounts) ? $ac->text->gs_serviceAccounts : esc_html__('Service Accounts', 'easy-form-builder'),
            /* translators: Section card subtitle — service accounts */
            "gs_serviceAccountsSub" => $state && isset($ac->text->gs_serviceAccountsSub) ? $ac->text->gs_serviceAccountsSub : esc_html__('Connect one or more Google service accounts — no browser login required', 'easy-form-builder'),
            /* translators: OpenSSL missing banner — bold lead */
            "gs_opensslMissingLead" => $state && isset($ac->text->gs_opensslMissingLead) ? $ac->text->gs_opensslMissingLead : esc_html__('Your server is missing the PHP OpenSSL extension.', 'easy-form-builder'),
            /* translators: OpenSSL missing banner — body. %s = the literal "openssl" code tag */
            "gs_opensslMissingBody" => $state && isset($ac->text->gs_opensslMissingBody) ? $ac->text->gs_opensslMissingBody : esc_html__('Google Sheet sync cannot work until it is enabled. Please ask your host to enable the %s PHP extension, then reload this page.', 'easy-form-builder'),
            /* translators: Connection test status — not tested */
            "gs_notTested" => $state && isset($ac->text->gs_notTested) ? $ac->text->gs_notTested : esc_html__('Not tested', 'easy-form-builder'),
            /* translators: Connection test status — failed */
            "gs_failed" => $state && isset($ac->text->gs_failed) ? $ac->text->gs_failed : esc_html__('Failed', 'easy-form-builder'),
            /* translators: Default service account pill */
            "gs_default" => $state && isset($ac->text->gs_default) ? $ac->text->gs_default : esc_html__('Default', 'easy-form-builder'),
            /* translators: Copy-email button tooltip */
            "gs_copyThisEmail" => $state && isset($ac->text->gs_copyThisEmail) ? $ac->text->gs_copyThisEmail : esc_html__('Copy this email', 'easy-form-builder'),
            /* translators: Copy-email button tooltip (short) */
            "gs_copyEmail" => $state && isset($ac->text->gs_copyEmail) ? $ac->text->gs_copyEmail : esc_html__('Copy email', 'easy-form-builder'),
            /* translators: Per-connection sharing instructions (HTML allowed) */
            "gs_connShareHint" => $state && isset($ac->text->gs_connShareHint) ? $ac->text->gs_connShareHint : esc_html__('To sync an <strong>existing</strong> sheet: open it in Google Sheets &rarr; <strong>Share</strong> &rarr; paste the email above &rarr; set role to <strong>Editor</strong>. To create a <strong>new</strong> sheet, just use Form Bindings — no sharing needed.', 'easy-form-builder'),
            /* translators: Set default service account button */
            "gs_setDefault" => $state && isset($ac->text->gs_setDefault) ? $ac->text->gs_setDefault : esc_html__('Set Default', 'easy-form-builder'),
            /* translators: Retest connection button */
            "gs_retest" => $state && isset($ac->text->gs_retest) ? $ac->text->gs_retest : esc_html__('Retest', 'easy-form-builder'),
            /* translators: Onboarding title (first run) */
            "gs_onboardTitle" => $state && isset($ac->text->gs_onboardTitle) ? $ac->text->gs_onboardTitle : esc_html__('Let’s connect Google Sheets — about 3 minutes, one time', 'easy-form-builder'),
            /* translators: Onboarding description (first run) */
            "gs_onboardDesc" => $state && isset($ac->text->gs_onboardDesc) ? $ac->text->gs_onboardDesc : esc_html__('Follow the numbered steps on the right to download a free service account key from Google, then drop the file below. You only do this once. After that, creating a sheet for any form is a couple of clicks.', 'easy-form-builder'),
            /* translators: Your Google email field label */
            "gs_yourGoogleEmail" => $state && isset($ac->text->gs_yourGoogleEmail) ? $ac->text->gs_yourGoogleEmail : esc_html__('Your Google email', 'easy-form-builder'),
            /* translators: Optional field marker */
            "gs_recommended" => $state && isset($ac->text->gs_recommended) ? $ac->text->gs_recommended : esc_html__('(recommended)', 'easy-form-builder'),
            /* translators: Your Google email field hint (HTML allowed) */
            "gs_yourGoogleEmailHint" => $state && isset($ac->text->gs_yourGoogleEmailHint) ? $ac->text->gs_yourGoogleEmailHint : esc_html__('Any sheet the plugin <strong>creates</strong> is automatically shared to this address, so it appears in your own Google Drive under “Shared with me.” Without it, new sheets stay hidden inside the service account.', 'easy-form-builder'),
            /* translators: Enable integration toggle title */
            "gs_enableIntegration" => $state && isset($ac->text->gs_enableIntegration) ? $ac->text->gs_enableIntegration : esc_html__('Enable Google Sheet Integration', 'easy-form-builder'),
            /* translators: Enable integration toggle subtitle */
            "gs_enableIntegrationSub" => $state && isset($ac->text->gs_enableIntegrationSub) ? $ac->text->gs_enableIntegrationSub : esc_html__('Sync form submissions to your spreadsheets automatically', 'easy-form-builder'),
            /* translators: Dropzone title */
            "gs_dropzoneTitle" => $state && isset($ac->text->gs_dropzoneTitle) ? $ac->text->gs_dropzoneTitle : esc_html__('Drag & drop service account JSON file(s) here', 'easy-form-builder'),
            /* translators: Dropzone subtitle */
            "gs_dropzoneSub" => $state && isset($ac->text->gs_dropzoneSub) ? $ac->text->gs_dropzoneSub : esc_html__('or click to browse — you can add multiple service accounts at once', 'easy-form-builder'),
            /* translators: Connected accounts list title */
            "gs_connectedAccounts" => $state && isset($ac->text->gs_connectedAccounts) ? $ac->text->gs_connectedAccounts : esc_html__('Connected service accounts', 'easy-form-builder'),
            /* translators: Tips card title */
            "gs_setupTitle" => $state && isset($ac->text->gs_setupTitle) ? $ac->text->gs_setupTitle : esc_html__('One-time setup (≈3 min)', 'easy-form-builder'),
            /* translators: Setup tip 1 (HTML allowed) */
            "gs_tip1" => $state && isset($ac->text->gs_tip1) ? $ac->text->gs_tip1 : esc_html__('Go to <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">console.cloud.google.com</a> &rarr; Project dropdown (top-left) &rarr; New Project &rarr; name it &rarr; Create', 'easy-form-builder'),
            /* translators: Setup tip 2 (HTML allowed) */
            "gs_tip2" => $state && isset($ac->text->gs_tip2) ? $ac->text->gs_tip2 : esc_html__('APIs &amp; Services &rarr; Library &rarr; enable <strong>Google Sheets API</strong> <em>and</em> <strong>Google Drive API</strong>', 'easy-form-builder'),
            /* translators: Setup tip 3 (HTML allowed) */
            "gs_tip3" => $state && isset($ac->text->gs_tip3) ? $ac->text->gs_tip3 : esc_html__('APIs &amp; Services &rarr; Credentials &rarr; Create Credentials &rarr; Service Account &rarr; name it &rarr; Done', 'easy-form-builder'),
            /* translators: Setup tip 4 (HTML allowed) */
            "gs_tip4" => $state && isset($ac->text->gs_tip4) ? $ac->text->gs_tip4 : esc_html__('Open the service account &rarr; Keys tab &rarr; Add Key &rarr; Create New Key &rarr; JSON (downloads automatically)', 'easy-form-builder'),
            /* translators: Setup tip 5 */
            "gs_tip5" => $state && isset($ac->text->gs_tip5) ? $ac->text->gs_tip5 : esc_html__('Drag that downloaded file into the box on the left — it is tested automatically', 'easy-form-builder'),
            /* translators: Setup tip 6 (HTML allowed) */
            "gs_tip6" => $state && isset($ac->text->gs_tip6) ? $ac->text->gs_tip6 : esc_html__('Add <strong>Your Google email</strong> above so created sheets land in your Drive', 'easy-form-builder'),
            /* translators: Invalid JSON file alert */
            "gs_chooseValidJson" => $state && isset($ac->text->gs_chooseValidJson) ? $ac->text->gs_chooseValidJson : esc_html__('Please choose a valid .json key file.', 'easy-form-builder'),
            /* translators: File read error alert. %s = file name */
            "gs_couldNotReadFile" => $state && isset($ac->text->gs_couldNotReadFile) ? $ac->text->gs_couldNotReadFile : esc_html__('Could not read file: %s', 'easy-form-builder'),
            /* translators: Connecting service account status */
            "gs_connectingAccount" => $state && isset($ac->text->gs_connectingAccount) ? $ac->text->gs_connectingAccount : esc_html__('Connecting service account…', 'easy-form-builder'),
            /* translators: Service account added fallback message */
            "gs_accountAdded" => $state && isset($ac->text->gs_accountAdded) ? $ac->text->gs_accountAdded : esc_html__('Service account added.', 'easy-form-builder'),
            /* translators: My-email saved message. %s = email address */
            "gs_emailSaved" => $state && isset($ac->text->gs_emailSaved) ? $ac->text->gs_emailSaved : esc_html__('Saved. New sheets created by the plugin will be shared to %s automatically.', 'easy-form-builder'),
            /* translators: My-email cleared message */
            "gs_emailCleared" => $state && isset($ac->text->gs_emailCleared) ? $ac->text->gs_emailCleared : esc_html__('Email cleared. New sheets will stay inside the service account until you share them.', 'easy-form-builder'),
            /* translators: Confirm remove service account */
            "gs_confirmRemoveAccount" => $state && isset($ac->text->gs_confirmRemoveAccount) ? $ac->text->gs_confirmRemoveAccount : esc_html__('Remove this service account?', 'easy-form-builder'),
            /* translators: Testing connection status */
            "gs_testingConnection" => $state && isset($ac->text->gs_testingConnection) ? $ac->text->gs_testingConnection : esc_html__('Testing connection…', 'easy-form-builder'),

            /* ===== Bindings tab ===== */
            /* translators: Section card subtitle — bindings */
            "gs_bindingsSub" => $state && isset($ac->text->gs_bindingsSub) ? $ac->text->gs_bindingsSub : esc_html__('Connect each form to a specific Google Sheet tab', 'easy-form-builder'),
            /* translators: Section card subtitle — bindings (with steps) */
            "gs_bindingsSubSteps" => $state && isset($ac->text->gs_bindingsSubSteps) ? $ac->text->gs_bindingsSubSteps : esc_html__('Connect each form to a specific Google Sheet tab — step by step', 'easy-form-builder'),
            /* translators: No service account warning — bold lead */
            "gs_noAccountLead" => $state && isset($ac->text->gs_noAccountLead) ? $ac->text->gs_noAccountLead : esc_html__('No service account connected.', 'easy-form-builder'),
            /* translators: No service account warning — body */
            "gs_noAccountBody" => $state && isset($ac->text->gs_noAccountBody) ? $ac->text->gs_noAccountBody : esc_html__('Go to the Connections tab and drop a service account JSON key first.', 'easy-form-builder'),
            /* translators: Binding status — active */
            "gs_active" => $state && isset($ac->text->gs_active) ? $ac->text->gs_active : esc_html__('Active', 'easy-form-builder'),
            /* translators: Binding status — paused */
            "gs_paused" => $state && isset($ac->text->gs_paused) ? $ac->text->gs_paused : esc_html__('Paused', 'easy-form-builder'),
            /* translators: Last sync succeeded badge */
            "gs_lastSyncOk" => $state && isset($ac->text->gs_lastSyncOk) ? $ac->text->gs_lastSyncOk : esc_html__('Last sync OK', 'easy-form-builder'),
            /* translators: Last sync failed badge */
            "gs_lastSyncFailed" => $state && isset($ac->text->gs_lastSyncFailed) ? $ac->text->gs_lastSyncFailed : esc_html__('Last sync failed', 'easy-form-builder'),
            /* translators: Inline delete confirm text */
            "gs_removeSyncSafe" => $state && isset($ac->text->gs_removeSyncSafe) ? $ac->text->gs_removeSyncSafe : esc_html__('Remove sync? The sheet stays safe.', 'easy-form-builder'),
            /* translators: Inline delete confirm — yes */
            "gs_yesRemove" => $state && isset($ac->text->gs_yesRemove) ? $ac->text->gs_yesRemove : esc_html__('Yes, remove', 'easy-form-builder'),
            /* translators: Delete-binding row button tooltip */
            "gs_deleteRowTitle" => $state && isset($ac->text->gs_deleteRowTitle) ? $ac->text->gs_deleteRowTitle : esc_html__('Remove this sync (your Google Sheet is not deleted)', 'easy-form-builder'),
            /* translators: Existing bindings list title */
            "gs_existingBindings" => $state && isset($ac->text->gs_existingBindings) ? $ac->text->gs_existingBindings : esc_html__('Existing Bindings', 'easy-form-builder'),
            /* translators: Delete all bindings button */
            "gs_deleteAll" => $state && isset($ac->text->gs_deleteAll) ? $ac->text->gs_deleteAll : esc_html__('Delete All', 'easy-form-builder'),
            /* translators: New binding button */
            "gs_newBinding" => $state && isset($ac->text->gs_newBinding) ? $ac->text->gs_newBinding : esc_html__('New Binding', 'easy-form-builder'),
            /* translators: Bindings table column — form */
            "gs_colForm" => $state && isset($ac->text->gs_colForm) ? $ac->text->gs_colForm : esc_html__('Form', 'easy-form-builder'),
            /* translators: Bindings table column — spreadsheet id */
            "gs_colSpreadsheetId" => $state && isset($ac->text->gs_colSpreadsheetId) ? $ac->text->gs_colSpreadsheetId : esc_html__('Spreadsheet ID', 'easy-form-builder'),
            /* translators: Bindings table column — tab */
            "gs_colTab" => $state && isset($ac->text->gs_colTab) ? $ac->text->gs_colTab : esc_html__('Tab', 'easy-form-builder'),
            /* translators: Bindings table column — status */
            "gs_colStatus" => $state && isset($ac->text->gs_colStatus) ? $ac->text->gs_colStatus : esc_html__('Status', 'easy-form-builder'),

            /* ===== Wizard stepper ===== */
            /* translators: Wizard step 1 label */
            "gs_stepSelectForm" => $state && isset($ac->text->gs_stepSelectForm) ? $ac->text->gs_stepSelectForm : esc_html__('Select Form', 'easy-form-builder'),
            /* translators: Wizard step 2 label */
            "gs_stepChooseSheet" => $state && isset($ac->text->gs_stepChooseSheet) ? $ac->text->gs_stepChooseSheet : esc_html__('Choose Sheet', 'easy-form-builder'),
            /* translators: Wizard step 3 label */
            "gs_stepColumns" => $state && isset($ac->text->gs_stepColumns) ? $ac->text->gs_stepColumns : esc_html__('Columns & Fields', 'easy-form-builder'),
            /* translators: Wizard step 4 label */
            "gs_stepStyle" => $state && isset($ac->text->gs_stepStyle) ? $ac->text->gs_stepStyle : esc_html__('Style & Save', 'easy-form-builder'),

            /* ===== Wizard step 1 (form) ===== */
            /* translators: Form select placeholder */
            "gs_selectAForm" => $state && isset($ac->text->gs_selectAForm) ? $ac->text->gs_selectAForm : esc_html__('— Select a Form —', 'easy-form-builder'),
            /* translators: Form select question label */
            "gs_whichForm" => $state && isset($ac->text->gs_whichForm) ? $ac->text->gs_whichForm : esc_html__('Which form should send data to Google Sheets?', 'easy-form-builder'),
            /* translators: Alert — select a form first */
            "gs_selectFormFirst" => $state && isset($ac->text->gs_selectFormFirst) ? $ac->text->gs_selectFormFirst : esc_html__('Please select a form first.', 'easy-form-builder'),

            /* ===== Wizard step 2 (sheet) ===== */
            /* translators: Service account select label */
            "gs_serviceAccount" => $state && isset($ac->text->gs_serviceAccount) ? $ac->text->gs_serviceAccount : esc_html__('Service Account', 'easy-form-builder'),
            /* translators: Loading spreadsheets status */
            "gs_loadingSheets" => $state && isset($ac->text->gs_loadingSheets) ? $ac->text->gs_loadingSheets : esc_html__('Loading your spreadsheets…', 'easy-form-builder'),
            /* translators: Browse/create hint (HTML allowed) */
            "gs_browseOrCreateHint" => $state && isset($ac->text->gs_browseOrCreateHint) ? $ac->text->gs_browseOrCreateHint : esc_html__('Click <strong>“Browse My Sheets”</strong> to list spreadsheets this service account can already access, or <strong>“Create New Sheet”</strong> to make a fresh one.', 'easy-form-builder'),
            /* translators: No accessible sheets message — lead (HTML allowed) */
            "gs_noSheetsLead" => $state && isset($ac->text->gs_noSheetsLead) ? $ac->text->gs_noSheetsLead : esc_html__('<strong>No spreadsheets are accessible to this service account yet.</strong> You have two options:', 'easy-form-builder'),
            /* translators: No accessible sheets — easiest option (HTML allowed) */
            "gs_noSheetsEasiest" => $state && isset($ac->text->gs_noSheetsEasiest) ? $ac->text->gs_noSheetsEasiest : esc_html__('<strong>Easiest:</strong> click <em>“Create New Sheet”</em> above — it is made for you and shared to your email automatically.', 'easy-form-builder'),
            /* translators: No accessible sheets — existing option prefix (HTML allowed) */
            "gs_noSheetsExisting" => $state && isset($ac->text->gs_noSheetsExisting) ? $ac->text->gs_noSheetsExisting : esc_html__('<strong>Use an existing sheet:</strong> open it in Google Sheets &rarr; Share &rarr; paste ', 'easy-form-builder'),
            /* translators: No accessible sheets — existing option suffix (HTML allowed) */
            "gs_noSheetsExistingSuffix" => $state && isset($ac->text->gs_noSheetsExistingSuffix) ? $ac->text->gs_noSheetsExistingSuffix : esc_html__(' &rarr; Editor, then click <em>Browse My Sheets</em> again.', 'easy-form-builder'),
            /* translators: Fallback when service account email is unknown */
            "gs_theServiceAccountEmail" => $state && isset($ac->text->gs_theServiceAccountEmail) ? $ac->text->gs_theServiceAccountEmail : esc_html__('the service account email', 'easy-form-builder'),
            /* translators: Create new spreadsheet field title */
            "gs_createNewSpreadsheet" => $state && isset($ac->text->gs_createNewSpreadsheet) ? $ac->text->gs_createNewSpreadsheet : esc_html__('Create a new spreadsheet', 'easy-form-builder'),
            /* translators: New sheet title placeholder */
            "gs_newSheetTitlePh" => $state && isset($ac->text->gs_newSheetTitlePh) ? $ac->text->gs_newSheetTitlePh : esc_html__('New spreadsheet title (e.g. Contact form responses)', 'easy-form-builder'),
            /* translators: New sheet email placeholder */
            "gs_newSheetEmailPh" => $state && isset($ac->text->gs_newSheetEmailPh) ? $ac->text->gs_newSheetEmailPh : esc_html__('Your Google email — the sheet is shared here so you can open it', 'easy-form-builder'),
            /* translators: New sheet email hint — prefix when prefilled */
            "gs_prefilledEmail" => $state && isset($ac->text->gs_prefilledEmail) ? $ac->text->gs_prefilledEmail : esc_html__('Pre-filled from your saved email. ', 'easy-form-builder'),
            /* translators: New sheet email hint */
            "gs_leaveEmptyHint" => $state && isset($ac->text->gs_leaveEmptyHint) ? $ac->text->gs_leaveEmptyHint : esc_html__('Leave empty only if you don’t need to open the sheet yourself.', 'easy-form-builder'),
            /* translators: Create sheet button */
            "gs_createSheet" => $state && isset($ac->text->gs_createSheet) ? $ac->text->gs_createSheet : esc_html__('Create Sheet', 'easy-form-builder'),
            /* translators: Creating sheet status */
            "gs_creating" => $state && isset($ac->text->gs_creating) ? $ac->text->gs_creating : esc_html__('Creating…', 'easy-form-builder'),
            /* translators: Creating spreadsheet status */
            "gs_creatingSpreadsheet" => $state && isset($ac->text->gs_creatingSpreadsheet) ? $ac->text->gs_creatingSpreadsheet : esc_html__('Creating spreadsheet...', 'easy-form-builder'),
            /* translators: Sheet created + shared message. %s = email */
            "gs_sheetCreatedShared" => $state && isset($ac->text->gs_sheetCreatedShared) ? $ac->text->gs_sheetCreatedShared : esc_html__('Sheet created and shared with %s. It’s selected below — click Next to finish.', 'easy-form-builder'),
            /* translators: Fallback for "your email" in the shared message */
            "gs_yourEmail" => $state && isset($ac->text->gs_yourEmail) ? $ac->text->gs_yourEmail : esc_html__('your email', 'easy-form-builder'),
            /* translators: Sheet created (not shared) message */
            "gs_sheetCreatedNotShared" => $state && isset($ac->text->gs_sheetCreatedNotShared) ? $ac->text->gs_sheetCreatedNotShared : esc_html__('Sheet created. Tip: add “Your Google email” on the Connections tab so new sheets are shared to you automatically.', 'easy-form-builder'),
            /* translators: Open the new sheet link label */
            "gs_openNewSheet" => $state && isset($ac->text->gs_openNewSheet) ? $ac->text->gs_openNewSheet : esc_html__('Open the new sheet', 'easy-form-builder'),
            /* translators: Selected sheet banner prefix */
            "gs_selected" => $state && isset($ac->text->gs_selected) ? $ac->text->gs_selected : esc_html__('Selected:', 'easy-form-builder'),
            /* translators: Loading worksheet tabs status */
            "gs_loadingTabs" => $state && isset($ac->text->gs_loadingTabs) ? $ac->text->gs_loadingTabs : esc_html__('Loading worksheet tabs…', 'easy-form-builder'),
            /* translators: Worksheet tab field label */
            "gs_worksheetTab" => $state && isset($ac->text->gs_worksheetTab) ? $ac->text->gs_worksheetTab : esc_html__('Worksheet Tab', 'easy-form-builder'),
            /* translators: New tab name placeholder */
            "gs_newTabPh" => $state && isset($ac->text->gs_newTabPh) ? $ac->text->gs_newTabPh : esc_html__('Or type a new tab name (created automatically)', 'easy-form-builder'),
            /* translators: Browse my sheets button */
            "gs_browseMySheets" => $state && isset($ac->text->gs_browseMySheets) ? $ac->text->gs_browseMySheets : esc_html__('Browse My Sheets', 'easy-form-builder'),
            /* translators: Loading short status */
            "gs_loadingShort" => $state && isset($ac->text->gs_loadingShort) ? $ac->text->gs_loadingShort : esc_html__('Loading…', 'easy-form-builder'),
            /* translators: Create new sheet button */
            "gs_createNewSheet" => $state && isset($ac->text->gs_createNewSheet) ? $ac->text->gs_createNewSheet : esc_html__('Create New Sheet', 'easy-form-builder'),
            /* translators: Next button disabled tooltip */
            "gs_chooseSheetFirst" => $state && isset($ac->text->gs_chooseSheetFirst) ? $ac->text->gs_chooseSheetFirst : esc_html__('Choose or create a spreadsheet first', 'easy-form-builder'),
            /* translators: Alert — select a service account */
            "gs_selectAccount" => $state && isset($ac->text->gs_selectAccount) ? $ac->text->gs_selectAccount : esc_html__('Please select a service account.', 'easy-form-builder'),
            /* translators: Alert — choose or create a spreadsheet first */
            "gs_chooseCreateFirst" => $state && isset($ac->text->gs_chooseCreateFirst) ? $ac->text->gs_chooseCreateFirst : esc_html__('Please choose or create a spreadsheet first.', 'easy-form-builder'),

            /* ===== Wizard step 3 (columns) ===== */
            /* translators: Enable sync toggle title (per form) */
            "gs_enableSyncForm" => $state && isset($ac->text->gs_enableSyncForm) ? $ac->text->gs_enableSyncForm : esc_html__('Enable Sync for this Form', 'easy-form-builder'),
            /* translators: Enable sync toggle subtitle */
            "gs_enableSyncFormSub" => $state && isset($ac->text->gs_enableSyncFormSub) ? $ac->text->gs_enableSyncFormSub : esc_html__('Disable to pause without deleting the binding', 'easy-form-builder'),
            /* translators: Reading form fields status */
            "gs_readingFields" => $state && isset($ac->text->gs_readingFields) ? $ac->text->gs_readingFields : esc_html__('Reading this form’s fields…', 'easy-form-builder'),
            /* translators: Fields unreadable message (HTML allowed) */
            "gs_fieldsUnreadable" => $state && isset($ac->text->gs_fieldsUnreadable) ? $ac->text->gs_fieldsUnreadable : esc_html__('We couldn’t read this form’s fields automatically, so column mapping isn’t available. Don’t worry — <strong>every submitted field will still be synced automatically</strong>, each in its own column, in submission order. The header row is created and kept up to date for you.', 'easy-form-builder'),
            /* translators: Try again button */
            "gs_tryAgain" => $state && isset($ac->text->gs_tryAgain) ? $ac->text->gs_tryAgain : esc_html__('Try again', 'easy-form-builder'),
            /* translators: Column mapping intro (HTML allowed) */
            "gs_mapIntro" => $state && isset($ac->text->gs_mapIntro) ? $ac->text->gs_mapIntro : esc_html__('Drag rows (or use ▲▼) to set the <strong>column order</strong>. Rename any <strong>header</strong>. Turn a field off to skip its column. The letter (A, B, C…) shows where each field lands.', 'easy-form-builder'),
            /* translators: Column map header — column letter */
            "gs_mapColHead" => $state && isset($ac->text->gs_mapColHead) ? $ac->text->gs_mapColHead : esc_html__('Col', 'easy-form-builder'),
            /* translators: Column map header — form field */
            "gs_mapFieldHead" => $state && isset($ac->text->gs_mapFieldHead) ? $ac->text->gs_mapFieldHead : esc_html__('Form field', 'easy-form-builder'),
            /* translators: Column map header — spreadsheet header */
            "gs_mapHeaderHead" => $state && isset($ac->text->gs_mapHeaderHead) ? $ac->text->gs_mapHeaderHead : esc_html__('Spreadsheet header', 'easy-form-builder'),
            /* translators: Column map header — order/on */
            "gs_mapActionsHead" => $state && isset($ac->text->gs_mapActionsHead) ? $ac->text->gs_mapActionsHead : esc_html__('Order / On', 'easy-form-builder'),
            /* translators: Refresh fields button */
            "gs_refreshFields" => $state && isset($ac->text->gs_refreshFields) ? $ac->text->gs_refreshFields : esc_html__('Refresh fields from form', 'easy-form-builder'),
            /* translators: Columns & field mapping card title */
            "gs_columnsMapping" => $state && isset($ac->text->gs_columnsMapping) ? $ac->text->gs_columnsMapping : esc_html__('Columns & Field Mapping', 'easy-form-builder'),
            /* translators: Built-in timestamp field name */
            "gs_submittedTime" => $state && isset($ac->text->gs_submittedTime) ? $ac->text->gs_submittedTime : esc_html__('Submitted time', 'easy-form-builder'),
            /* translators: Built-in field marker */
            "gs_builtin" => $state && isset($ac->text->gs_builtin) ? $ac->text->gs_builtin : esc_html__('built-in', 'easy-form-builder'),
            /* translators: Default timestamp header value */
            "gs_submittedAt" => $state && isset($ac->text->gs_submittedAt) ? $ac->text->gs_submittedAt : esc_html__('Submitted At', 'easy-form-builder'),
            /* translators: Column header input placeholder */
            "gs_columnHeaderPh" => $state && isset($ac->text->gs_columnHeaderPh) ? $ac->text->gs_columnHeaderPh : esc_html__('Column header', 'easy-form-builder'),
            /* translators: Drag-to-reorder tooltip */
            "gs_dragReorder" => $state && isset($ac->text->gs_dragReorder) ? $ac->text->gs_dragReorder : esc_html__('Drag to reorder', 'easy-form-builder'),
            /* translators: Move up tooltip */
            "gs_moveUp" => $state && isset($ac->text->gs_moveUp) ? $ac->text->gs_moveUp : esc_html__('Move up', 'easy-form-builder'),
            /* translators: Move down tooltip */
            "gs_moveDown" => $state && isset($ac->text->gs_moveDown) ? $ac->text->gs_moveDown : esc_html__('Move down', 'easy-form-builder'),
            /* translators: Include this field tooltip */
            "gs_includeField" => $state && isset($ac->text->gs_includeField) ? $ac->text->gs_includeField : esc_html__('Include this field', 'easy-form-builder'),
            /* translators: Columns count — singular. %d = count */
            "gs_columnsWritten" => $state && isset($ac->text->gs_columnsWritten) ? $ac->text->gs_columnsWritten : esc_html__('%d column will be written to the sheet.', 'easy-form-builder'),
            /* translators: Columns count — plural. %d = count */
            "gs_columnsWrittenPlural" => $state && isset($ac->text->gs_columnsWrittenPlural) ? $ac->text->gs_columnsWrittenPlural : esc_html__('%d columns will be written to the sheet.', 'easy-form-builder'),

            /* ===== Wizard step 4 (style) ===== */
            /* translators: Sheet style field label */
            "gs_sheetStyle" => $state && isset($ac->text->gs_sheetStyle) ? $ac->text->gs_sheetStyle : esc_html__('Sheet Style', 'easy-form-builder'),
            /* translators: Sheet style — google theme marker */
            "gs_googleTheme" => $state && isset($ac->text->gs_googleTheme) ? $ac->text->gs_googleTheme : esc_html__('(Google theme)', 'easy-form-builder'),
            /* translators: Sheet style hint */
            "gs_styleHint" => $state && isset($ac->text->gs_styleHint) ? $ac->text->gs_styleHint : esc_html__('Pick a look for the sheet — colored header, frozen top row and alternating row colors (Google Sheets’ own styling). It’s applied automatically on the first sync, or apply it now.', 'easy-form-builder'),
            /* translators: Apply style now button */
            "gs_applyStyleNow" => $state && isset($ac->text->gs_applyStyleNow) ? $ac->text->gs_applyStyleNow : esc_html__('Apply style now', 'easy-form-builder'),
            /* translators: Applying style status */
            "gs_applying" => $state && isset($ac->text->gs_applying) ? $ac->text->gs_applying : esc_html__('Applying…', 'easy-form-builder'),
            /* translators: Apply style optional note */
            "gs_applyStyleOptional" => $state && isset($ac->text->gs_applyStyleOptional) ? $ac->text->gs_applyStyleOptional : esc_html__('Optional — it also applies on the first submission.', 'easy-form-builder'),
            /* translators: Summary — spreadsheet */
            "gs_summarySpreadsheet" => $state && isset($ac->text->gs_summarySpreadsheet) ? $ac->text->gs_summarySpreadsheet : esc_html__('Spreadsheet:', 'easy-form-builder'),
            /* translators: Summary — tab */
            "gs_summaryTab" => $state && isset($ac->text->gs_summaryTab) ? $ac->text->gs_summaryTab : esc_html__('Tab:', 'easy-form-builder'),
            /* translators: Summary — columns */
            "gs_summaryColumns" => $state && isset($ac->text->gs_summaryColumns) ? $ac->text->gs_summaryColumns : esc_html__('Columns:', 'easy-form-builder'),
            /* translators: Summary — columns mapped suffix. %d = count */
            "gs_summaryMapped" => $state && isset($ac->text->gs_summaryMapped) ? $ac->text->gs_summaryMapped : esc_html__('%d mapped', 'easy-form-builder'),
            /* translators: Summary — all submitted fields (auto) */
            "gs_summaryAllFields" => $state && isset($ac->text->gs_summaryAllFields) ? $ac->text->gs_summaryAllFields : esc_html__('all submitted fields (auto)', 'easy-form-builder'),
            /* translators: Summary — style */
            "gs_summaryStyle" => $state && isset($ac->text->gs_summaryStyle) ? $ac->text->gs_summaryStyle : esc_html__('Style:', 'easy-form-builder'),
            /* translators: Summary hint */
            "gs_summaryHint" => $state && isset($ac->text->gs_summaryHint) ? $ac->text->gs_summaryHint : esc_html__('After saving, submit the form once to create the header row and confirm the first sync.', 'easy-form-builder'),
            /* translators: Save binding button */
            "gs_saveBinding" => $state && isset($ac->text->gs_saveBinding) ? $ac->text->gs_saveBinding : esc_html__('Save Binding', 'easy-form-builder'),
            /* translators: Style name — none */
            "gs_styleNone" => $state && isset($ac->text->gs_styleNone) ? $ac->text->gs_styleNone : esc_html__('None', 'easy-form-builder'),
            /* translators: Alert — choose a spreadsheet first */
            "gs_chooseSpreadsheetFirst" => $state && isset($ac->text->gs_chooseSpreadsheetFirst) ? $ac->text->gs_chooseSpreadsheetFirst : esc_html__('Please choose a spreadsheet first.', 'easy-form-builder'),
            /* translators: Alert — pick a style first */
            "gs_pickStyleFirst" => $state && isset($ac->text->gs_pickStyleFirst) ? $ac->text->gs_pickStyleFirst : esc_html__('Pick a style first — “None” leaves the sheet unstyled.', 'easy-form-builder'),
            /* translators: Style applied success */
            "gs_styleApplied" => $state && isset($ac->text->gs_styleApplied) ? $ac->text->gs_styleApplied : esc_html__('Style applied.', 'easy-form-builder'),

            /* ===== Save / delete binding ===== */
            /* translators: Alert — select a form */
            "gs_selectForm" => $state && isset($ac->text->gs_selectForm) ? $ac->text->gs_selectForm : esc_html__('Please select a form.', 'easy-form-builder'),
            /* translators: Alert — choose a spreadsheet */
            "gs_chooseSpreadsheet" => $state && isset($ac->text->gs_chooseSpreadsheet) ? $ac->text->gs_chooseSpreadsheet : esc_html__('Please choose a spreadsheet.', 'easy-form-builder'),
            /* translators: Binding saved message */
            "gs_bindingSaved" => $state && isset($ac->text->gs_bindingSaved) ? $ac->text->gs_bindingSaved : esc_html__('Binding saved.', 'easy-form-builder'),
            /* translators: Confirm remove single binding */
            "gs_confirmRemoveBinding" => $state && isset($ac->text->gs_confirmRemoveBinding) ? $ac->text->gs_confirmRemoveBinding : esc_html__('Remove this binding? Syncing stops for this form. Your Google Sheet and its data are not deleted.', 'easy-form-builder'),
            /* translators: Binding removed message */
            "gs_syncRemoved" => $state && isset($ac->text->gs_syncRemoved) ? $ac->text->gs_syncRemoved : esc_html__('Sync removed — your Google Sheet and its data are untouched.', 'easy-form-builder'),
            /* translators: Confirm remove all bindings — singular. %d = count */
            "gs_confirmRemoveAll" => $state && isset($ac->text->gs_confirmRemoveAll) ? $ac->text->gs_confirmRemoveAll : esc_html__('Remove ALL %d binding? Syncing stops for every form. Your Google Sheets are not deleted.', 'easy-form-builder'),
            /* translators: Confirm remove all bindings — plural. %d = count */
            "gs_confirmRemoveAllPlural" => $state && isset($ac->text->gs_confirmRemoveAllPlural) ? $ac->text->gs_confirmRemoveAllPlural : esc_html__('Remove ALL %d bindings? Syncing stops for every form. Your Google Sheets are not deleted.', 'easy-form-builder'),
            /* translators: All bindings removed message */
            "gs_allBindingsRemoved" => $state && isset($ac->text->gs_allBindingsRemoved) ? $ac->text->gs_allBindingsRemoved : esc_html__('All bindings removed. Your Google Sheets are untouched.', 'easy-form-builder'),
            /* translators: Connection successful message */
            "gs_connectionSuccessful" => $state && isset($ac->text->gs_connectionSuccessful) ? $ac->text->gs_connectionSuccessful : esc_html__('Connection successful!', 'easy-form-builder'),

            /* ===== Help tab ===== */
            /* translators: Help section card title */
            "gs_helpTitle" => $state && isset($ac->text->gs_helpTitle) ? $ac->text->gs_helpTitle : esc_html__('Help & Setup Guide', 'easy-form-builder'),
            /* translators: Help section card subtitle */
            "gs_helpSubtitle" => $state && isset($ac->text->gs_helpSubtitle) ? $ac->text->gs_helpSubtitle : esc_html__('Everything you need to connect forms to Google Sheets', 'easy-form-builder'),
            /* translators: Help — step-by-step setup title */
            "gs_stepByStep" => $state && isset($ac->text->gs_stepByStep) ? $ac->text->gs_stepByStep : esc_html__('Step-by-Step Setup', 'easy-form-builder'),
            /* translators: Guide step 1 title */
            "gs_guide1Title" => $state && isset($ac->text->gs_guide1Title) ? $ac->text->gs_guide1Title : esc_html__('Create or Select a Project', 'easy-form-builder'),
            /* translators: Guide step 1 description (HTML allowed) */
            "gs_guide1Desc" => $state && isset($ac->text->gs_guide1Desc) ? $ac->text->gs_guide1Desc : esc_html__('Open console.cloud.google.com and log in &rarr; click the Project dropdown (top-left) &rarr; New Project (or pick an existing one) &rarr; enter a name &rarr; Create', 'easy-form-builder'),
            /* translators: Guide step 2 title */
            "gs_guide2Title" => $state && isset($ac->text->gs_guide2Title) ? $ac->text->gs_guide2Title : esc_html__('Enable both Google APIs', 'easy-form-builder'),
            /* translators: Guide step 2 description (HTML allowed) */
            "gs_guide2Desc" => $state && isset($ac->text->gs_guide2Desc) ? $ac->text->gs_guide2Desc : esc_html__('APIs &amp; Services &rarr; Library &rarr; enable <strong>Google Sheets API</strong> and <strong>Google Drive API</strong> (both are required — Drive lists and creates the files)', 'easy-form-builder'),
            /* translators: Guide step 3 title */
            "gs_guide3Title" => $state && isset($ac->text->gs_guide3Title) ? $ac->text->gs_guide3Title : esc_html__('Create a Service Account', 'easy-form-builder'),
            /* translators: Guide step 3 description (HTML allowed) */
            "gs_guide3Desc" => $state && isset($ac->text->gs_guide3Desc) ? $ac->text->gs_guide3Desc : esc_html__('APIs &amp; Services &rarr; Credentials &rarr; Create Credentials &rarr; Service Account &rarr; enter a name &rarr; Done', 'easy-form-builder'),
            /* translators: Guide step 4 title */
            "gs_guide4Title" => $state && isset($ac->text->gs_guide4Title) ? $ac->text->gs_guide4Title : esc_html__('Create a JSON Key', 'easy-form-builder'),
            /* translators: Guide step 4 description (HTML allowed) */
            "gs_guide4Desc" => $state && isset($ac->text->gs_guide4Desc) ? $ac->text->gs_guide4Desc : esc_html__('Open the service account &rarr; Keys tab &rarr; Add Key &rarr; Create New Key &rarr; choose JSON (file downloads automatically)', 'easy-form-builder'),
            /* translators: Guide step 5 title */
            "gs_guide5Title" => $state && isset($ac->text->gs_guide5Title) ? $ac->text->gs_guide5Title : esc_html__('Drop the Key File', 'easy-form-builder'),
            /* translators: Guide step 5 description */
            "gs_guide5Desc" => $state && isset($ac->text->gs_guide5Desc) ? $ac->text->gs_guide5Desc : esc_html__('Drag the downloaded file into the Connections tab — it is verified automatically and you’ll see a green “Connected” badge', 'easy-form-builder'),
            /* translators: Guide step 6 title */
            "gs_guide6Title" => $state && isset($ac->text->gs_guide6Title) ? $ac->text->gs_guide6Title : esc_html__('Add Your Google Email', 'easy-form-builder'),
            /* translators: Guide step 6 description */
            "gs_guide6Desc" => $state && isset($ac->text->gs_guide6Desc) ? $ac->text->gs_guide6Desc : esc_html__('On the Connections tab, fill in “Your Google email” and Save — sheets the plugin creates are then shared to you so they appear in your own Drive', 'easy-form-builder'),
            /* translators: Guide step 7 title */
            "gs_guide7Title" => $state && isset($ac->text->gs_guide7Title) ? $ac->text->gs_guide7Title : esc_html__('Bind a Form (create a sheet)', 'easy-form-builder'),
            /* translators: Guide step 7 description (HTML allowed) */
            "gs_guide7Desc" => $state && isset($ac->text->gs_guide7Desc) ? $ac->text->gs_guide7Desc : esc_html__('Form Bindings &rarr; pick a form &rarr; <strong>Create New Sheet</strong> &rarr; Save. No manual sharing needed — this is the easiest path', 'easy-form-builder'),
            /* translators: Guide step 8 title */
            "gs_guide8Title" => $state && isset($ac->text->gs_guide8Title) ? $ac->text->gs_guide8Title : esc_html__('…or use an existing sheet', 'easy-form-builder'),
            /* translators: Guide step 8 description */
            "gs_guide8Desc" => $state && isset($ac->text->gs_guide8Desc) ? $ac->text->gs_guide8Desc : esc_html__('Prefer your own sheet? Open it &rarr; Share &rarr; paste the service account email (copy button on its card) &rarr; Editor &rarr; then Browse My Sheets in the wizard', 'easy-form-builder'),
            /* translators: FAQ section title */
            "gs_faq" => $state && isset($ac->text->gs_faq) ? $ac->text->gs_faq : esc_html__('FAQ', 'easy-form-builder'),
            /* translators: FAQ 1 question */
            "gs_faq1Q" => $state && isset($ac->text->gs_faq1Q) ? $ac->text->gs_faq1Q : esc_html__('I created a sheet — where did it go?', 'easy-form-builder'),
            /* translators: FAQ 1 answer */
            "gs_faq1A" => $state && isset($ac->text->gs_faq1A) ? $ac->text->gs_faq1A : esc_html__('Service accounts create files in their own hidden Drive. Add “Your Google email” on the Connections tab and the plugin shares every new sheet to you automatically — it then appears under “Shared with me” in your Google Drive. Each sheet also has an “Open” link right in this dashboard.', 'easy-form-builder'),
            /* translators: FAQ 2 question */
            "gs_faq2Q" => $state && isset($ac->text->gs_faq2Q) ? $ac->text->gs_faq2Q : esc_html__('Why don’t I see my existing sheet in the picker?', 'easy-form-builder'),
            /* translators: FAQ 2 answer */
            "gs_faq2A" => $state && isset($ac->text->gs_faq2A) ? $ac->text->gs_faq2A : esc_html__('It must be shared with the service account’s email first: open the sheet &rarr; Share &rarr; paste the email (use the copy button on the connection card) &rarr; set Editor &rarr; Share. Then click “Browse My Sheets” again.', 'easy-form-builder'),
            /* translators: FAQ 3 question */
            "gs_faq3Q" => $state && isset($ac->text->gs_faq3Q) ? $ac->text->gs_faq3Q : esc_html__('The test says a Google API is not enabled.', 'easy-form-builder'),
            /* translators: FAQ 3 answer */
            "gs_faq3A" => $state && isset($ac->text->gs_faq3A) ? $ac->text->gs_faq3A : esc_html__('Open Google Cloud Console for the same project &rarr; APIs &amp; Services &rarr; Library, and enable BOTH “Google Sheets API” and “Google Drive API”. Wait a minute, then Retest.', 'easy-form-builder'),
            /* translators: FAQ 4 question */
            "gs_faq4Q" => $state && isset($ac->text->gs_faq4Q) ? $ac->text->gs_faq4Q : esc_html__('The test says access denied / permission.', 'easy-form-builder'),
            /* translators: FAQ 4 answer */
            "gs_faq4A" => $state && isset($ac->text->gs_faq4A) ? $ac->text->gs_faq4A : esc_html__('The service account can authenticate but can’t reach that specific spreadsheet. Re-share the sheet with the service account email as Editor, or create a new sheet instead.', 'easy-form-builder'),
            /* translators: FAQ 5 question */
            "gs_faq5Q" => $state && isset($ac->text->gs_faq5Q) ? $ac->text->gs_faq5Q : esc_html__('It says OpenSSL is required.', 'easy-form-builder'),
            /* translators: FAQ 5 answer */
            "gs_faq5A" => $state && isset($ac->text->gs_faq5A) ? $ac->text->gs_faq5A : esc_html__('The server is missing the PHP OpenSSL extension used to sign the secure Google request. Ask your host to enable it — no plugin setting can replace it.', 'easy-form-builder'),
            /* translators: FAQ 6 question */
            "gs_faq6Q" => $state && isset($ac->text->gs_faq6Q) ? $ac->text->gs_faq6Q : esc_html__('Can I connect more than one service account?', 'easy-form-builder'),
            /* translators: FAQ 6 answer */
            "gs_faq6A" => $state && isset($ac->text->gs_faq6A) ? $ac->text->gs_faq6A : esc_html__('Yes — drop as many JSON keys as you like in the Connections tab and pick which one each form binding should use.', 'easy-form-builder'),
            /* translators: FAQ 7 question */
            "gs_faq7Q" => $state && isset($ac->text->gs_faq7Q) ? $ac->text->gs_faq7Q : esc_html__('Does the connection expire?', 'easy-form-builder'),
            /* translators: FAQ 7 answer */
            "gs_faq7A" => $state && isset($ac->text->gs_faq7A) ? $ac->text->gs_faq7A : esc_html__('No. The integration runs entirely on the server using your JSON key — there’s no browser session or login to expire.', 'easy-form-builder'),
            /* translators: FAQ 8 question */
            "gs_faq8Q" => $state && isset($ac->text->gs_faq8Q) ? $ac->text->gs_faq8Q : esc_html__('Can I choose which field goes to which column?', 'easy-form-builder'),
            /* translators: FAQ 8 answer (HTML allowed) */
            "gs_faq8A" => $state && isset($ac->text->gs_faq8A) ? $ac->text->gs_faq8A : esc_html__('Yes. In the binding wizard’s <strong>Columns &amp; Fields</strong> step, drag rows (or use ▲▼) to set the order, rename each column header, and toggle any field off to skip it. The A/B/C badge shows exactly where each field lands.', 'easy-form-builder'),
            /* translators: FAQ 9 question */
            "gs_faq9Q" => $state && isset($ac->text->gs_faq9Q) ? $ac->text->gs_faq9Q : esc_html__('Can I style the sheet automatically?', 'easy-form-builder'),
            /* translators: FAQ 9 answer (HTML allowed) */
            "gs_faq9A" => $state && isset($ac->text->gs_faq9A) ? $ac->text->gs_faq9A : esc_html__('Yes. The <strong>Style &amp; Save</strong> step offers Google-Sheets themes (colored header, frozen top row, alternating row colors). Pick one and it’s applied on the first sync, or click “Apply style now”.', 'easy-form-builder'),
            /* translators: FAQ 10 question */
            "gs_faq10Q" => $state && isset($ac->text->gs_faq10Q) ? $ac->text->gs_faq10Q : esc_html__('What if my form fields change later?', 'easy-form-builder'),
            /* translators: FAQ 10 answer */
            "gs_faq10A" => $state && isset($ac->text->gs_faq10A) ? $ac->text->gs_faq10A : esc_html__('New fields are added automatically. In mapped mode, open the Columns step and click “Refresh fields from form” to pull them into the mapping; in “All Form Fields” mode they appear as new columns on the next submission.', 'easy-form-builder'),
            /* translators: FAQ 11 question */
            "gs_faq11Q" => $state && isset($ac->text->gs_faq11Q) ? $ac->text->gs_faq11Q : esc_html__('What if the tab doesn’t exist yet?', 'easy-form-builder'),
            /* translators: FAQ 11 answer */
            "gs_faq11A" => $state && isset($ac->text->gs_faq11A) ? $ac->text->gs_faq11A : esc_html__('It is created automatically the first time a submission is synced.', 'easy-form-builder'),

            /* ===== Logs page ===== */
            /* translators: Logs page title */
            "gs_logsTitle" => $state && isset($ac->text->gs_logsTitle) ? $ac->text->gs_logsTitle : esc_html__('Google Sheet Sync Logs', 'easy-form-builder'),
            /* translators: Logs page subtitle */
            "gs_logsSubtitle" => $state && isset($ac->text->gs_logsSubtitle) ? $ac->text->gs_logsSubtitle : esc_html__('Latest submissions sent to Google Sheets, success or failure', 'easy-form-builder'),
            /* translators: Logs — cleared message */
            "gs_logCleared" => $state && isset($ac->text->gs_logCleared) ? $ac->text->gs_logCleared : esc_html__('Log cleared.', 'easy-form-builder'),
            /* translators: Logs — clear button */
            "gs_clearLog" => $state && isset($ac->text->gs_clearLog) ? $ac->text->gs_clearLog : esc_html__('Clear Log', 'easy-form-builder'),
            /* translators: Logs — clear confirm */
            "gs_clearAllLogs" => $state && isset($ac->text->gs_clearAllLogs) ? $ac->text->gs_clearAllLogs : esc_html__('Clear all logs?', 'easy-form-builder'),
            /* translators: Logs — empty state */
            "gs_noSyncYet" => $state && isset($ac->text->gs_noSyncYet) ? $ac->text->gs_noSyncYet : esc_html__('No sync activity yet.', 'easy-form-builder'),
            /* translators: Logs table — time column */
            "gs_logTime" => $state && isset($ac->text->gs_logTime) ? $ac->text->gs_logTime : esc_html__('Time', 'easy-form-builder'),
            /* translators: Logs table — event column */
            "gs_logEvent" => $state && isset($ac->text->gs_logEvent) ? $ac->text->gs_logEvent : esc_html__('Event', 'easy-form-builder'),
            /* translators: Logs table — spreadsheet column */
            "gs_logSpreadsheet" => $state && isset($ac->text->gs_logSpreadsheet) ? $ac->text->gs_logSpreadsheet : esc_html__('Spreadsheet', 'easy-form-builder'),
            /* translators: Logs table — detail column */
            "gs_logDetail" => $state && isset($ac->text->gs_logDetail) ? $ac->text->gs_logDetail : esc_html__('Detail', 'easy-form-builder'),
            /* translators: Logs status — synced */
            "gs_synced" => $state && isset($ac->text->gs_synced) ? $ac->text->gs_synced : esc_html__('Synced', 'easy-form-builder'),
            /* translators: Access denied (page + ajax guard) */
            "gs_accessDenied" => $state && isset($ac->text->gs_accessDenied) ? $ac->text->gs_accessDenied : esc_html__('Access denied', 'easy-form-builder'),
        ];
    }
}

function efb_get_addon_phrases($addon_key, $ac = null, $state = false) {
    EfbAddonPhrases::get_instance();
    return EfbAddonPhrases::get_addon_phrases($addon_key, $ac, $state);
}

function efb_register_addon_phrases($addon_key, $callback) {
    EfbAddonPhrases::get_instance();
    EfbAddonPhrases::register_addon($addon_key, $callback);
}
