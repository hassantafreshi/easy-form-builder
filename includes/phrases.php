<?php

if (!defined('ABSPATH')) {
    exit;
}

class EfbAddonPhrases {

    private static $instance = null;

    private static $addon_providers = [];

    private static $phrase_cache = [];

    /**
     * Add-ons that ship their own dashboard phrases, keyed by vendor folder:
     * [ 'group' => phrase group or null, 'slug', 'file' => relative provider path, 'class' ].
     */
    private static $addon_file_providers = [];

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

        self::register_addon('googlesheet', [__CLASS__, 'get_googlesheet_phrases']);

        self::register_addon('humanshield', [__CLASS__, 'get_humanshield_phrases']);

        // Add-ons that ship their dashboard phrases in their own text domain. Keep in
        // step with efbFunction::get_addon_i18n_required_files_efb().
        self::register_addon_provider_efb('telegram', 'telegram', 'vendor/telegram/telegram-phrases-efb.php', 'Emsfb_Addon_Phrases_Telegram');
        self::register_addon_provider_efb('googlesheet', 'googlesheet', 'vendor/googlesheet/googlesheet-phrases-efb.php', 'Emsfb_Addon_Phrases_Googlesheet');
        self::register_addon_provider_efb('Autofill', 'autofill', 'vendor/autofill/autofill-phrases-efb.php', 'Emsfb_Addon_Phrases_Autofill');
        self::register_addon_provider_efb('humanshield', 'human-shield', 'vendor/human-shield/human-shield-phrases-efb.php', 'Emsfb_Addon_Phrases_HumanShield');
        // 'sms' has no core group function left: the SMS add-on is the only reader of
        // that group, so its 25 keys ship in efb-smssended and get_addon_phrases('sms')
        // returns them from the provider alone. base() adds the three keys that lived
        // in the core $lang array and that nothing outside vendor/smssended reads.
        self::register_addon_provider_efb('sms', 'smssended', 'vendor/smssended/smssended-phrases-efb.php', 'Emsfb_Addon_Phrases_Smssended');
        // 'payment' is shared by the PayPal and Stripe add-ons and has no core group
        // function left: both providers ship the 65 keys their dashboards both read,
        // with the same English source, and get_addon_phrases('payment') merges them.
        self::register_addon_provider_efb('payment', 'paypal', 'vendor/paypal/paypal-phrases-efb.php', 'Emsfb_Addon_Phrases_Paypal');
        self::register_addon_provider_efb('payment', 'stripe', 'vendor/stripe/stripe-phrases-efb.php', 'Emsfb_Addon_Phrases_Stripe');
        // Conditional Logic has no phrase group: its keys lived in the base $lang
        // array, so only base() is populated and the group argument is null.
        self::register_addon_provider_efb(null, 'logic', 'vendor/logic/logic-phrases-efb.php', 'Emsfb_Addon_Phrases_Logic');

    }

    public static function register_addon($addon_key, $callback) {
        self::$addon_providers[$addon_key] = $callback;
    }

    /**
     * Register an add-on provider file (vendor/<slug>/<slug>-phrases-efb.php).
     *
     * @param string|null $group         Phrase group its group() extends, or null.
     * @param string      $slug          vendor/<slug> folder name.
     * @param string      $relative_file Provider file relative to the plugin root.
     * @param string      $class         Class with static group() and base().
     */
    public static function register_addon_provider_efb($group, $slug, $relative_file, $class) {
        self::$addon_file_providers[(string) $slug] = [
            'group' => null === $group ? null : (string) $group,
            'slug'  => (string) $slug,
            'file'  => ltrim(str_replace('\\', '/', (string) $relative_file), '/'),
            'class' => (string) $class,
        ];
        self::$phrase_cache = [];
    }

    public static function get_addon_providers_efb() {
        self::get_instance();
        return self::$addon_file_providers;
    }

    /**
     * Load a provider class from its file. Guarded on the file itself, so a
     * partial install (folder restored, file missing) never reaches the require.
     *
     * @param array $provider Registered provider.
     * @return bool
     */
    public static function load_addon_provider_efb($provider) {
        if (class_exists($provider['class'], false)) {
            return true;
        }
        if (!defined('EMSFB_PLUGIN_DIRECTORY')) {
            return false;
        }
        $provider_file = EMSFB_PLUGIN_DIRECTORY . $provider['file'];
        if (!file_exists($provider_file)) {
            return false;
        }
        require_once $provider_file;
        return class_exists($provider['class'], false);
    }

    /**
     * base() of every registered add-on whose provider file exists: keys that
     * add-ons moved out of the base $lang array in includes/functions.php.
     */
    public static function get_addon_base_phrases_efb($ac = null, $state = false) {
        self::get_instance();
        $phrases = [];
        foreach (self::$addon_file_providers as $provider) {
            if (!self::load_addon_provider_efb($provider) || !is_callable([$provider['class'], 'base'])) {
                continue;
            }
            $base = call_user_func([$provider['class'], 'base'], $ac, $state);
            if (is_array($base)) {
                $phrases = array_merge($phrases, $base);
            }
        }
        return $phrases;
    }

    public static function get_addon_phrases($addon_key, $ac = null, $state = false) {

        // Per locale: add-on phrases are translated when built, and a switch_to_locale()
        // or an admin with another profile language must not get the first copy.
        $locale = function_exists('determine_locale') ? determine_locale() : (function_exists('get_locale') ? get_locale() : '');
        $cache_key = $addon_key . '_' . ($state ? '1' : '0') . '_' . $locale;
        if (isset(self::$phrase_cache[$cache_key])) {
            return self::$phrase_cache[$cache_key];
        }

        $file_providers = [];
        foreach (self::$addon_file_providers as $provider) {
            if ($provider['group'] === $addon_key) {
                $file_providers[] = $provider;
            }
        }

        if (!isset(self::$addon_providers[$addon_key]) && empty($file_providers)) {
            return [];
        }

        $phrases = isset(self::$addon_providers[$addon_key]) ? call_user_func(self::$addon_providers[$addon_key], $ac, $state) : [];

        // Keys an add-on moved into its own provider file (missing file: English JS fallbacks).
        foreach ($file_providers as $provider) {
            if (!self::load_addon_provider_efb($provider) || !is_callable([$provider['class'], 'group'])) {
                continue;
            }
            $group = call_user_func([$provider['class'], 'group'], $ac, $state);
            if (is_array($group)) {
                $phrases = array_merge(is_array($phrases) ? $phrases : [], $group);
            }
        }

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

            /* translators: Enabled = status when feature is active */
            "enabled" => $state && isset($ac->text->enabled) ? $ac->text->enabled : esc_html__('Enabled', 'easy-form-builder'),

            /* translators: @BotFather = clickable link text (not translatable) */
            "botfather_link" => '@BotFather',

            /* translators: @WSYourIDBot = clickable link text (not translatable) */
            "chatid_finder_link" => '@WSYourIDBot',

            /* translators: Date = column header */
            "date" => $state && isset($ac->text->date) ? $ac->text->date : esc_html__('Date', 'easy-form-builder'),

            /* translators: Invalid token = error for invalid bot token */
            "invalidToken" => $state && isset($ac->text->invalidToken) ? $ac->text->invalidToken : esc_html__('Invalid token', 'easy-form-builder'),

            /* translators: Unknown error = generic error message */
            "unknownError" => $state && isset($ac->text->unknownError) ? $ac->text->unknownError : esc_html__('Unknown error', 'easy-form-builder'),

            /* translators: Message sent = success message */
            "messageSent" => $state && isset($ac->text->messageSent) ? $ac->text->messageSent : esc_html__('Message sent', 'easy-form-builder'),
        ];
    }

    public static function get_stripe_phrases($ac = null, $state = false) {
        return [

        ];
    }

    /**
     * Messages the Form Security & Spam Protection add-on (vendor/human-shield,
     * setting AdnHSH) returns to site visitors: the REST responses of
     * EmsfbShield/v1 and of its guard on the public form routes, read by raw
     * hs* key through Emsfb_Human_Shield::phrase(). They stay in core, in the
     * easy-form-builder text domain, because visitors see them.
     *
     * The add-on's admin panel strings are not here. They ship with the add-on
     * in vendor/human-shield/human-shield-phrases-efb.php (text domain
     * efb-human-shield, registered in register_default_addons()), and
     * get_addon_phrases('humanshield') merges them into this group.
     *
     * Values are used as plain text and escaped by the consumer, so keep them
     * unescaped (__ not esc_html__). A $ac->text override with the same key wins.
     */
    public static function get_humanshield_phrases($ac = null, $state = false) {
        return [

            /* translators: Shown when the security add-on is switched off */
            "hsDisabled" => $state && isset($ac->text->hsDisabled) ? $ac->text->hsDisabled : __('Form Security & Spam Protection is disabled.', 'easy-form-builder'),

            /* translators: Shown when the server cannot run the security checks but the form still works */
            "hsServiceNotAvailable" => $state && isset($ac->text->hsServiceNotAvailable) ? $ac->text->hsServiceNotAvailable : __('The security service is not available right now. Your form still works.', 'easy-form-builder'),

            /* translators: Ask the visitor to reload the form and submit again */
            "hsRefreshForm" => $state && isset($ac->text->hsRefreshForm) ? $ac->text->hsRefreshForm : __('Please refresh the form and try again.', 'easy-form-builder'),

            /* translators: Rate-limit message shown when too many requests arrive */
            "hsTooManyRequests" => $state && isset($ac->text->hsTooManyRequests) ? $ac->text->hsTooManyRequests : __('Too many requests. Please try again shortly.', 'easy-form-builder'),

            /* translators: The browser did not send a security challenge id */
            "hsChallengeMissing" => $state && isset($ac->text->hsChallengeMissing) ? $ac->text->hsChallengeMissing : __('Missing challenge.', 'easy-form-builder'),

            /* translators: The security challenge is no longer valid */
            "hsChallengeExpired" => $state && isset($ac->text->hsChallengeExpired) ? $ac->text->hsChallengeExpired : __('Expired challenge.', 'easy-form-builder'),

            /* translators: The security challenge was already used once */
            "hsChallengeUsed" => $state && isset($ac->text->hsChallengeUsed) ? $ac->text->hsChallengeUsed : __('Challenge already used.', 'easy-form-builder'),

            /* translators: The challenge does not belong to the submitted form */
            "hsFormMismatch" => $state && isset($ac->text->hsFormMismatch) ? $ac->text->hsFormMismatch : __('Form mismatch.', 'easy-form-builder'),

            /* translators: Temporary storage/service failure inside the security add-on */
            "hsServiceTempUnavailable" => $state && isset($ac->text->hsServiceTempUnavailable) ? $ac->text->hsServiceTempUnavailable : __('The security service is temporarily unavailable.', 'easy-form-builder'),

            /* translators: The signed security token expired or could not be verified */
            "hsFormExpired" => $state && isset($ac->text->hsFormExpired) ? $ac->text->hsFormExpired : __('The form was open for too long or could not be verified. Please refresh the page and submit again.', 'easy-form-builder'),

            /* translators: Generic block message when a request looks automated */
            "hsLooksUnusual" => $state && isset($ac->text->hsLooksUnusual) ? $ac->text->hsLooksUnusual : __('Your request looked too fast or unusual. Please try again in a few minutes.', 'easy-form-builder'),

            /* translators: Softer quarantine message asking the visitor to wait and retry */
            "hsQuarantine" => $state && isset($ac->text->hsQuarantine) ? $ac->text->hsQuarantine : __('Your request looked unusual. Please wait a moment and try again.', 'easy-form-builder'),

            /* translators: Shown when the security service is temporarily unavailable (fail-closed) */
            "hsServiceUnavailable" => $state && isset($ac->text->hsServiceUnavailable) ? $ac->text->hsServiceUnavailable : __('The security service is temporarily unavailable. Please try again later.', 'easy-form-builder'),

            /* ===== Admin panel (Security & Spam Protection settings page) =====
             * Its strings, rendered by human-shield-admin-efb.js, live in the
             * add-on's human-shield-phrases-efb.php (see the docblock above). */

        ];
    }

    public static function get_autofill_phrases($ac = null, $state = false) {
        return [










            /* translators: Tab: Datasets */
            "datasetsTab" => $state && isset($ac->text->datasetsTab) ? $ac->text->datasetsTab : esc_html__('Datasets', 'easy-form-builder'),


















































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
            "testing" => $state && isset($ac->text->testing) ? $ac->text->testing : esc_html__('Testing connection&hellip;', 'easy-form-builder'),


            /* translators: Actions = column header */
            "actions" => $state && isset($ac->text->actions) ? $ac->text->actions : esc_html__('Actions', 'easy-form-builder'),

            /* translators: Content = column header for message content preview */
            "content" => $state && isset($ac->text->content) ? $ac->text->content : esc_html__('Content', 'easy-form-builder'),

            /* translators: Saving message */
            "saving" => $state && isset($ac->text->saving) ? $ac->text->saving : esc_html__('Saving&hellip;', 'easy-form-builder'),

            /* translators: No API connections message */
            "no_api_connections" => $state && isset($ac->text->no_api_connections) ? $ac->text->no_api_connections : esc_html__('No API connections yet. Click "Add New API Connection" to create one.', 'easy-form-builder'),

            /* translators: Confirm delete message */
            "confirm_delete" => $state && isset($ac->text->confirm_delete) ? $ac->text->confirm_delete : esc_html__('Are you sure you want to delete', 'easy-form-builder'),







            /* translators: API connection not found = error message */
            "api_connection_not_found" => $state && isset($ac->text->api_connection_not_found) ? $ac->text->api_connection_not_found : esc_html__('API connection not found or disabled', 'easy-form-builder'),

            /* translators: Rate limit exceeded = error message */
            "rate_limit_exceeded" => $state && isset($ac->text->rate_limit_exceeded) ? $ac->text->rate_limit_exceeded : esc_html__('Rate limit exceeded. Please try again later.', 'easy-form-builder'),

            /* translators: Invalid request format = error message */
            "invalid_request_format" => $state && isset($ac->text->invalid_request_format) ? $ac->text->invalid_request_format : esc_html__('Invalid request format', 'easy-form-builder'),

            /* translators: Failed to parse API response = error message */
            "parse_error" => $state && isset($ac->text->parse_error) ? $ac->text->parse_error : esc_html__('Failed to parse API response', 'easy-form-builder'),






            /* translators: API connection ID required = error message */
            "api_connection_id_required" => $state && isset($ac->text->api_connection_id_required) ? $ac->text->api_connection_id_required : esc_html__('API connection ID is required', 'easy-form-builder'),

            /* translators: Form ID required = error message */
            "form_id_required" => $state && isset($ac->text->form_id_required) ? $ac->text->form_id_required : esc_html__('Form ID is required', 'easy-form-builder'),

            /* translators: Form not found = error message */
            "form_not_found" => $state && isset($ac->text->form_not_found) ? $ac->text->form_not_found : esc_html__('Form not found', 'easy-form-builder'),

            /* translators: Invalid form structure = error message */
            "invalid_form_structure" => $state && isset($ac->text->invalid_form_structure) ? $ac->text->invalid_form_structure : esc_html__('Invalid form structure', 'easy-form-builder'),


            /* translators: No matching data found = message */
            "no_matching_data" => $state && isset($ac->text->no_matching_data) ? $ac->text->no_matching_data : esc_html__('No matching data found', 'easy-form-builder'),

            /* translators: API returned error = error message with status code */
            "api_returned_error" => $state && isset($ac->text->api_returned_error) ? $ac->text->api_returned_error : esc_html__('API returned error: %d', 'easy-form-builder'),




            /* translators: camelCase alias of external_api, used by the API connections admin script */
            "externalApi" => $state && isset($ac->text->external_api) ? $ac->text->external_api : esc_html__('External API Connections', 'easy-form-builder'),
            /* translators: camelCase alias of add_new_api */
            "addNewApi" => $state && isset($ac->text->add_new_api) ? $ac->text->add_new_api : esc_html__('Add New API Connection', 'easy-form-builder'),
            /* translators: camelCase alias of custom_header */
            "customHeader" => $state && isset($ac->text->custom_header) ? $ac->text->custom_header : esc_html__('Custom Header', 'easy-form-builder'),
            /* translators: camelCase alias of auth_value_placeholder */
            "authValuePlaceholder" => $state && isset($ac->text->auth_value_placeholder) ? $ac->text->auth_value_placeholder : esc_html__('Enter token or credentials', 'easy-form-builder'),
            /* translators: camelCase alias of no_custom_headers */
            "noCustomHeaders" => $state && isset($ac->text->no_custom_headers) ? $ac->text->no_custom_headers : esc_html__('No custom headers', 'easy-form-builder'),
            /* translators: camelCase alias of query_params */
            "queryParams" => $state && isset($ac->text->query_params) ? $ac->text->query_params : esc_html__('Query Parameters', 'easy-form-builder'),
            /* translators: camelCase alias of no_query_params */
            "noQueryParams" => $state && isset($ac->text->no_query_params) ? $ac->text->no_query_params : esc_html__('No query parameters', 'easy-form-builder'),
            /* translators: camelCase alias of body_template_help */
            "bodyTemplateHelp" => $state && isset($ac->text->body_template_help) ? $ac->text->body_template_help : esc_html__('JSON template for POST/PUT/PATCH requests. Use {{field_id}} for dynamic values.', 'easy-form-builder'),
            /* translators: camelCase alias of response_mapping */
            "responseMapping" => $state && isset($ac->text->response_mapping) ? $ac->text->response_mapping : esc_html__('Response Mapping', 'easy-form-builder'),
            /* translators: camelCase alias of field_mappings */
            "fieldMappings" => $state && isset($ac->text->field_mappings) ? $ac->text->field_mappings : esc_html__('Field Mappings', 'easy-form-builder'),
            /* translators: camelCase alias of no_field_mappings */
            "noFieldMappings" => $state && isset($ac->text->no_field_mappings) ? $ac->text->no_field_mappings : esc_html__('No field mappings', 'easy-form-builder'),
            /* translators: camelCase alias of field_mappings_help */
            "fieldMappingsHelp" => $state && isset($ac->text->field_mappings_help) ? $ac->text->field_mappings_help : esc_html__('Map API response fields to form field IDs', 'easy-form-builder'),
            /* translators: camelCase alias of cache_settings */
            "cacheSettings" => $state && isset($ac->text->cache_settings) ? $ac->text->cache_settings : esc_html__('Cache Settings', 'easy-form-builder'),
            /* translators: camelCase alias of cache_duration */
            "cacheDuration" => $state && isset($ac->text->cache_duration) ? $ac->text->cache_duration : esc_html__('Cache Duration (minutes)', 'easy-form-builder'),
            /* translators: camelCase alias of cache_duration_help */
            "cacheDurationHelp" => $state && isset($ac->text->cache_duration_help) ? $ac->text->cache_duration_help : esc_html__('0 = no caching', 'easy-form-builder'),
            /* translators: camelCase alias of basic_info */
            "basicInfo" => $state && isset($ac->text->basic_info) ? $ac->text->basic_info : esc_html__('Basic Information', 'easy-form-builder'),
            /* translators: camelCase alias of test_result */
            "testResult" => $state && isset($ac->text->test_result) ? $ac->text->test_result : esc_html__('Test Result', 'easy-form-builder'),
            /* translators: camelCase alias of testing */
            "testing" => $state && isset($ac->text->testing) ? $ac->text->testing : esc_html__('Testing connection&hellip;', 'easy-form-builder'),
            /* translators: camelCase alias of saving */
            "saving" => $state && isset($ac->text->saving) ? $ac->text->saving : esc_html__('Saving&hellip;', 'easy-form-builder'),




            /* translators: Optional = marks a field as not required */
            "optional" => $state && isset($ac->text->optional) ? $ac->text->optional : esc_html__('Optional', 'easy-form-builder'),

            /* translators: Form selector section title */
            "selectFormTitle" => $state && isset($ac->text->select_form_title) ? $ac->text->select_form_title : esc_html__('Select Form', 'easy-form-builder'),
            /* translators: Help text under the target form selector */
            "targetFormHelp" => $state && isset($ac->text->target_form_help) ? $ac->text->target_form_help : esc_html__('Select the form that will receive data from the API', 'easy-form-builder'),
            /* translators: Target Form = field label */
            "targetForm" => $state && isset($ac->text->target_form) ? $ac->text->target_form : esc_html__('Target Form', 'easy-form-builder'),
            /* translators: Form select placeholder */
            "selectForm" => $state && isset($ac->text->select_form) ? $ac->text->select_form : esc_html__('— Select a Form —', 'easy-form-builder'),
            /* translators: Section title for fields that trigger the API search */
            "searchFieldsTitle" => $state && isset($ac->text->search_fields_title) ? $ac->text->search_fields_title : esc_html__('Search Fields (Trigger Fields)', 'easy-form-builder'),
            /* translators: Validation message shown before a form is chosen */
            "selectFormFirst" => $state && isset($ac->text->select_form_first) ? $ac->text->select_form_first : esc_html__('Please select a form first', 'easy-form-builder'),
            /* translators: Section title for fields that get auto-populated from the API response */
            "targetFieldsTitle" => $state && isset($ac->text->target_fields_title) ? $ac->text->target_fields_title : esc_html__('Target Fields (Auto-Populate)', 'easy-form-builder'),
            /* translators: Description of the target-fields mapping */
            "targetFieldsInfo" => $state && isset($ac->text->target_fields_info) ? $ac->text->target_fields_info : esc_html__('Map API response to form fields', 'easy-form-builder'),
            /* translators: API Response Field = column header */
            "apiFieldName" => $state && isset($ac->text->api_field_name) ? $ac->text->api_field_name : esc_html__('API Response Field', 'easy-form-builder'),
            /* translators: Form Field to Fill = column header */
            "formFieldSelect" => $state && isset($ac->text->form_field_select) ? $ac->text->form_field_select : esc_html__('Form Field to Fill', 'easy-form-builder'),
            /* translators: Field select placeholder */
            "selectField" => $state && isset($ac->text->select_field) ? $ac->text->select_field : esc_html__('— Select Field —', 'easy-form-builder'),

            /* translators: Help text above the cache duration field */
            "cacheHelp" => $state && isset($ac->text->cache_help) ? $ac->text->cache_help : esc_html__('Cache API responses to improve performance', 'easy-form-builder'),

            /* translators: Name = generic column/summary label */
            "name" => $state && isset($ac->text->name) ? $ac->text->name : esc_html__('Name', 'easy-form-builder'),
            /* translators: Method = HTTP method summary label */
            "method" => $state && isset($ac->text->method) ? $ac->text->method : esc_html__('Method', 'easy-form-builder'),
            /* translators: Run Test = button that calls the API once to verify it works */
            "runTest" => $state && isset($ac->text->run_test) ? $ac->text->run_test : esc_html__('Run Test', 'easy-form-builder'),
            /* translators: Previous = wizard navigation button */
            "previous" => $state && isset($ac->text->previous) ? $ac->text->previous : esc_html__('Previous', 'easy-form-builder'),
            /* translators: Next = wizard navigation button */
            "next" => $state && isset($ac->text->next) ? $ac->text->next : esc_html__('Next', 'easy-form-builder'),
            /* translators: Save = generic save button */
            "save" => $state && isset($ac->text->save) ? $ac->text->save : esc_html__('Save', 'easy-form-builder'),
            /* translators: Edit = generic edit button */
            "edit" => $state && isset($ac->text->edit) ? $ac->text->edit : esc_html__('Edit', 'easy-form-builder'),
            /* translators: Active = status shown for an enabled API connection */
            "enabled" => $state && isset($ac->text->enabled) ? $ac->text->enabled : esc_html__('Active', 'easy-form-builder'),
            /* translators: Inactive = status shown for a disabled API connection */
            "disabled" => $state && isset($ac->text->disabled) ? $ac->text->disabled : esc_html__('Inactive', 'easy-form-builder'),

            /* translators: Generic loading status */
            "loading" => $state && isset($ac->text->loading) ? $ac->text->loading : esc_html__('Loading&hellip;', 'easy-form-builder'),

            /* translators: Generic error message */
            "errorOccurred" => $state && isset($ac->text->error_occurred) ? $ac->text->error_occurred : esc_html__('An error occurred', 'easy-form-builder'),
            /* translators: Error shown when the form's data could not be loaded */
            "formNotFound" => $state && isset($ac->text->form_not_found) ? $ac->text->form_not_found : esc_html__('Form data not found. Please refresh the page.', 'easy-form-builder'),
            /* translators: Empty state when a form has no fields that can be auto-filled */
            "noFieldsFound" => $state && isset($ac->text->no_fields_found) ? $ac->text->no_fields_found : esc_html__('No fillable fields found in this form', 'easy-form-builder'),

            /* translators: Banner title shown on a form using the API auto-populate feature */
            "atfllApiActive" => $state && isset($ac->text->atfll_api_active) ? $ac->text->atfll_api_active : esc_html__('API Auto-Populate Integration is Active', 'easy-form-builder'),
            /* translators: Banner description pointing the admin to where the integration is configured */
            "atfllApiActiveDesc" => $state && isset($ac->text->atfll_api_active_desc) ? $ac->text->atfll_api_active_desc : esc_html__('This form uses External API Auto-Populate. To configure settings, go to', 'easy-form-builder'),
            /* translators: Link text pointing to the Auto-Populate Integrations page */
            "atfllApiLink" => $state && isset($ac->text->atfll_api_link) ? $ac->text->atfll_api_link : esc_html__('Auto-Populate Integrations', 'easy-form-builder'),

            /* translators: Status = generic column header */
            "status" => $state && isset($ac->text->status) ? $ac->text->status : esc_html__('Status', 'easy-form-builder'),
            /* translators: Actions = generic column header */
            "actions" => $state && isset($ac->text->actions) ? $ac->text->actions : esc_html__('Actions', 'easy-form-builder'),
            /* translators: Enable = generic toggle action */
            "enable" => $state && isset($ac->text->enable) ? $ac->text->enable : esc_html__('Enable', 'easy-form-builder'),
            /* translators: Disable = generic toggle action */
            "disable" => $state && isset($ac->text->disable) ? $ac->text->disable : esc_html__('Disable', 'easy-form-builder'),
            /* translators: Delete = generic delete action */
            "delete" => $state && isset($ac->text->delete) ? $ac->text->delete : esc_html__('Delete', 'easy-form-builder'),
            /* translators: Cancel = generic cancel action */
            "cancel" => $state && isset($ac->text->cancel) ? $ac->text->cancel : esc_html__('Cancel', 'easy-form-builder'),
            /* translators: Authentication = generic column header */
            "authentication" => $state && isset($ac->text->authentication) ? $ac->text->authentication : esc_html__('Authentication', 'easy-form-builder'),
            /* translators: Server-side validation error when the endpoint URL is missing */
            "endpointRequired" => $state && isset($ac->text->endpoint_required) ? $ac->text->endpoint_required : esc_html__('API endpoint URL is required', 'easy-form-builder'),
        ];
    }

    public static function get_webhook_phrases($ac = null, $state = false) {
        return [

        ];
    }

    public static function get_googlesheet_phrases($ac = null, $state = false) {
        return [

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
