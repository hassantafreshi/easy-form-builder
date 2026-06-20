<?php

namespace Emsfb;

defined('ABSPATH') || exit;

class Email_Monitor {

    const OPTION_ENABLED = 'emsfb_weekly_email_report_enabled';
    const OPTION_PENDING = 'emsfb_email_monitor_pending_test';
    const OPTION_LAST_STATUS = 'emsfb_email_monitor_last_status';
    const OPTION_LAST_UPDATE_VERSION = 'emsfb_email_monitor_last_update_version';
    const OPTION_ACTIVATION_MARKER = 'emsfb_email_monitor_activation_marker';

    const WEEKLY_HOOK = 'emsfb_email_monitor_weekly';
    const LIFECYCLE_HOOK = 'emsfb_email_monitor_lifecycle';
    const POLL_HOOK = 'emsfb_email_monitor_poll';

    public static function register() {
        add_filter('cron_schedules', [__CLASS__, 'add_weekly_schedule']);
        add_action(self::WEEKLY_HOOK, [__CLASS__, 'run_weekly_test']);
        add_action(self::LIFECYCLE_HOOK, [__CLASS__, 'run_lifecycle_test'], 10, 1);
        add_action(self::POLL_HOOK, [__CLASS__, 'poll_test'], 10, 1);
        add_action('init', [__CLASS__, 'ensure_schedule']);
    }

    public static function add_weekly_schedule($schedules) {
        if (!isset($schedules['emsfb_weekly'])) {
            $schedules['emsfb_weekly'] = [
                'interval' => WEEK_IN_SECONDS,
                'display' => __('Once Weekly (Easy Form Builder)', 'easy-form-builder'),
            ];
        }
        return $schedules;
    }

    public static function activate() {
        self::ensure_default_option();
        update_option(self::OPTION_ACTIVATION_MARKER, [
            'version' => defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '',
            'time' => time(),
        ], false);
        self::sync_schedule();
        self::schedule_lifecycle_test('activation');
    }

    public static function plugin_updated() {
        self::ensure_default_option();
        self::sync_schedule();

        $version = defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '';
        $activation = get_option(self::OPTION_ACTIVATION_MARKER, []);
        if (
            is_array($activation)
            && isset($activation['version'], $activation['time'])
            && (string) $activation['version'] === $version
            && (time() - (int) $activation['time']) < 10 * MINUTE_IN_SECONDS
        ) {
            return;
        }

        if ((string) get_option(self::OPTION_LAST_UPDATE_VERSION, '') === $version) {
            return;
        }

        if (self::schedule_lifecycle_test('update')) {
            update_option(self::OPTION_LAST_UPDATE_VERSION, $version, false);
        }
    }

    public static function deactivate() {
        self::unschedule_hook(self::WEEKLY_HOOK);
        self::unschedule_hook(self::LIFECYCLE_HOOK);
        self::unschedule_hook(self::POLL_HOOK);
        delete_option(self::OPTION_PENDING);
    }

    public static function ensure_schedule() {
        self::ensure_default_option();
        self::sync_schedule();
    }

    public static function sync_schedule() {
        $scheduled = wp_next_scheduled(self::WEEKLY_HOOK);
        if (self::is_enabled()) {
            if (!$scheduled) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'emsfb_weekly', self::WEEKLY_HOOK);
            }
        } elseif ($scheduled) {
            self::unschedule_hook(self::WEEKLY_HOOK);
        }
    }

    public static function is_enabled() {
        self::ensure_default_option();
        return (bool) get_option(self::OPTION_ENABLED, 1);
    }

    public static function can_manage_setting($package_type = null) {
        if ($package_type === null) {
            $package_type = (int) get_option('emsfb_pro', 2);
        }
        return in_array((int) $package_type, [1, 3], true);
    }

    public static function update_enabled($enabled, $package_type = null) {
        if (!self::can_manage_setting($package_type)) {
            return false;
        }

        update_option(self::OPTION_ENABLED, self::normalize_bool($enabled) ? 1 : 0, false);
        self::sync_schedule();
        return true;
    }

    public static function normalize_bool($value) {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    public static function get_public_status() {
        $status = get_option(self::OPTION_LAST_STATUS, []);
        if (!is_array($status)) {
            $status = [];
        }

        return [
            'enabled' => self::is_enabled(),
            'can_manage' => self::can_manage_setting(),
            'state' => isset($status['state']) ? sanitize_key($status['state']) : 'not_run',
            'message' => isset($status['message']) ? sanitize_text_field($status['message']) : '',
            'checked_at' => isset($status['checked_at']) ? sanitize_text_field($status['checked_at']) : '',
            'next_run' => wp_next_scheduled(self::WEEKLY_HOOK) ?: 0,
        ];
    }

    public static function run_weekly_test() {
        if (!self::is_enabled()) {
            self::sync_schedule();
            return;
        }
        self::start_test('weekly');
    }

    public static function run_lifecycle_test($context = 'activation') {
        $context = in_array($context, ['activation', 'update'], true) ? $context : 'activation';
        self::start_test($context);
    }

    public static function poll_test($test_hash) {
        $pending = get_option(self::OPTION_PENDING, []);
        if (
            !is_array($pending)
            || empty($pending['test_hash'])
            || !hash_equals((string) $pending['test_hash'], (string) $test_hash)
        ) {
            return;
        }

        $response = self::remote_request('GET', '/result/' . rawurlencode($test_hash), [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            self::retry_or_finish($pending, 'service_request_error', $response->get_error_message());
            return;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($result)) {
            self::retry_or_finish($pending, 'invalid_service_response', __('The email tester service returned an invalid response.', 'easy-form-builder'));
            return;
        }

		$status = isset($result['status']) ? sanitize_key($result['status']) : '';
		$stage = isset($result['analysis_stage']) ? sanitize_key($result['analysis_stage']) : '';
		$terminal = in_array($status, ['delayed', 'expired', 'failed'], true)
			|| ($status === 'analyzed' && $stage === 'full')
			|| !empty($result['can_send_email']);

		if (!$terminal) {
			self::retry_or_finish($pending, $status ?: 'pending', isset($result['message']) ? $result['message'] : '');
			return;
		}

        $can_send = !empty($result['can_send_email']) || !empty($result['success']);
        $message = isset($result['message']) ? sanitize_text_field($result['message']) : '';
        if ($message === '') {
            $message = $can_send
                ? __('The weekly email delivery test completed successfully.', 'easy-form-builder')
                : __('The weekly email delivery test found an email delivery problem.', 'easy-form-builder');
        }

        self::save_status($can_send ? 'success' : 'failed', $message, $pending['context'], $result);
		if ($can_send) {
			self::mark_email_ready();
		} else {
			self::request_remote_email_report($test_hash, $status, $pending['admin_email']);
		}

        delete_option(self::OPTION_PENDING);
    }

    private static function start_test($context) {
        $pending = get_option(self::OPTION_PENDING, []);
        if (is_array($pending) && !empty($pending['started_at']) && (time() - (int) $pending['started_at']) < 15 * MINUTE_IN_SECONDS) {
            return;
        }

        $admin_email = sanitize_email(get_option('admin_email', ''));
        if (!is_email($admin_email)) {
            self::finish_without_test($context, 'invalid_admin_email', __('The main WordPress administrator email address is not valid.', 'easy-form-builder'));
            return;
        }

        $settings = function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null;
        $sender_email = self::get_sender_email($settings);
        $activity_report = [];
        $start_payload = [
            'site_url' => home_url(),
            'site_name' => get_bloginfo('name'),
            'sender_email' => $sender_email,
            'admin_email' => $admin_email,
            'plugin' => 'easy-form-builder',
            'plugin_version' => defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '',
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'language' => get_locale(),
            'license_type' => self::get_license_type(),
            'license_key' => '',
            'trigger' => $context,
            'generated_at' => current_time('mysql', true),
        ];
        if ($context === 'weekly' && self::is_enabled()) {
            $activity_report = self::get_weekly_stats();
            $start_payload['report_frequency'] = 'weekly';
            $start_payload['activity_report'] = $activity_report;
        }

        $start = self::remote_request('POST', '/start', [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode($start_payload),
        ]);

        if (is_wp_error($start)) {
            self::finish_without_test($context, 'service_start_error', $start->get_error_message());
            return;
        }

        $code = (int) wp_remote_retrieve_response_code($start);
        $test = json_decode(wp_remote_retrieve_body($start), true);
        if ($code < 200 || $code >= 300 || !is_array($test) || empty($test['success'])) {
            $message = is_array($test) && !empty($test['message'])
                ? sanitize_text_field($test['message'])
                : __('Could not start the email delivery test.', 'easy-form-builder');
            self::finish_without_test($context, 'service_start_error', $message);
            return;
        }

        $recipient = isset($test['recipient_email']) ? sanitize_email($test['recipient_email']) : '';
        $subject = isset($test['email_subject']) ? str_replace(["\r", "\n"], '', (string) $test['email_subject']) : '';
        $test_hash = isset($test['test_hash']) ? sanitize_text_field($test['test_hash']) : '';
        if (!is_email($recipient) || $subject === '' || !self::is_valid_hash($test_hash)) {
            self::finish_without_test($context, 'invalid_service_response', __('The email tester service returned an invalid response.', 'easy-form-builder'));
            return;
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $sender_email,
            'X-EFB-Test-Hash: ' . $test_hash,
        ];
        if ($context === 'weekly') {
            $headers[] = 'X-EFB-Report-Type: weekly';
        }
        $message = sprintf(
            '<p>Easy Form Builder automated email delivery test.</p><p>Site: %s</p><p>Trigger: %s</p><p>Test hash: %s</p>',
            esc_html(home_url()),
            esc_html($context),
            esc_html($test_hash)
        );
        if ($context === 'weekly' && !empty($activity_report)) {
            $message .= '<h2>Weekly form activity totals</h2><ul>';
            foreach ($activity_report as $label => $value) {
                $message .= sprintf(
                    '<li><strong>%s:</strong> %d</li>',
                    esc_html(str_replace('_', ' ', ucwords($label, '_'))),
                    (int) $value
                );
            }
            $message .= '</ul>';
        }
        $sent = wp_mail($recipient, $subject, $message, $headers);

        require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-email-handler.php';
        if ($sent) {
            \EmsfbEmailHandler::log_email_success($recipient, $subject);
        } else {
            \EmsfbEmailHandler::log_email_failure($recipient, $subject);
        }

        update_option(self::OPTION_PENDING, [
            'test_hash' => $test_hash,
            'context' => $context,
            'admin_email' => $admin_email,
            'started_at' => time(),
            'attempts' => 0,
        ], false);
        if ($context === 'weekly') {
            update_option('emsfb_email_monitor_last_remote_report', [
                'accepted_at' => current_time('mysql', true),
                'success' => true,
                'http_code' => $code,
            ], false);
        }

        self::save_status(
            $sent ? 'pending' : 'failed',
            $sent
                ? __('The automated email test was sent and is waiting for delivery confirmation.', 'easy-form-builder')
                : __('WordPress could not send the automated email test.', 'easy-form-builder'),
            $context
        );

        wp_schedule_single_event(time() + 30, self::POLL_HOOK, [$test_hash]);
    }

    private static function retry_or_finish($pending, $state, $message) {
        $pending['attempts'] = isset($pending['attempts']) ? (int) $pending['attempts'] + 1 : 1;
        if ($pending['attempts'] >= 10 || (time() - (int) $pending['started_at']) >= 12 * MINUTE_IN_SECONDS) {
			$final_message = $message !== ''
				? sanitize_text_field($message)
				: __('The email delivery test timed out before confirmation was received.', 'easy-form-builder');
			self::save_status('failed', $final_message, $pending['context']);
			if (in_array($state, ['delayed', 'expired'], true)) {
				self::request_remote_email_report($pending['test_hash'], $state, $pending['admin_email']);
			}
			delete_option(self::OPTION_PENDING);
			return;
		}

        update_option(self::OPTION_PENDING, $pending, false);
        wp_schedule_single_event(time() + 60, self::POLL_HOOK, [$pending['test_hash']]);
    }

    private static function finish_without_test($context, $state, $message) {
        self::save_status('failed', $message, $context, ['status' => $state, 'can_send_email' => false]);
    }

    private static function get_weekly_stats() {
        global $wpdb;

        $forms_table = $wpdb->prefix . 'emsfb_form';
        $stats_table = $wpdb->prefix . 'emsfb_stts_';
        $since = wp_date('Y-m-d H:i:s', time() - WEEK_IN_SECONDS);

        $forms_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($forms_table))) === $forms_table;
        $stats_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($stats_table))) === $stats_table;

        $forms_total = $forms_exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$forms_table}`") : 0;
        $forms_active = $forms_exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$forms_table}` WHERE `status` = 1") : 0;
        $submissions = $stats_exists ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$stats_table}` WHERE `status` NOT IN ('visit','inact','admin') AND `date` >= %s",
            $since
        )) : 0;
        $visits = $stats_exists ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$stats_table}` WHERE `status` = 'visit' AND `date` >= %s",
            $since
        )) : 0;

        require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-email-handler.php';
        $email_stats = \EmsfbEmailHandler::get_email_stats('week');

        return [
            'forms_total' => $forms_total,
            'forms_active' => $forms_active,
            'forms_inactive' => max(0, $forms_total - $forms_active),
            'page_views' => $visits,
            'submissions' => $submissions,
            'emails_sent' => (int) $email_stats['success'],
            'emails_failed' => (int) $email_stats['failed'],
        ];
    }

	private static function request_remote_email_report($test_hash, $status, $admin_email) {
		if (!self::is_valid_hash($test_hash)) {
			return;
		}

		$status = sanitize_key($status);
		if (!in_array($status, ['delayed', 'expired'], true)) {
			return;
		}

		self::remote_request('POST', '/result/' . rawurlencode($test_hash) . '/email-report', [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode([
                'trigger_status' => sanitize_key($status),
                'language' => get_locale(),
                'reason' => 'automated_email_monitor',
                'admin_email' => sanitize_email($admin_email),
            ]),
        ]);
    }

    private static function mark_email_ready() {
        update_option('emsfb_email_status', [
            'status' => 'ok',
            'message' => [
                'title' => __('Email delivery is working', 'easy-form-builder'),
                'description' => __('The automated Easy Form Builder email delivery test completed successfully.', 'easy-form-builder'),
                'id' => 'automated_email_test_ok',
            ],
            'details' => [
                'stage' => 'automated',
                'test_timestamp' => current_time('mysql', true),
            ],
        ], false);

        if (!function_exists('get_setting_Emsfb') || !function_exists('get_efbFunction')) {
            return;
        }
        $settings = get_setting_Emsfb('decoded');
        if (!is_object($settings) || !empty($settings->smtp)) {
            return;
        }
        $settings->smtp = true;
        $email = isset($settings->emailSupporter) ? sanitize_email($settings->emailSupporter) : '';
        get_efbFunction()->set_setting_Emsfb($settings, $email);
    }

    private static function save_status($state, $message, $context, $result = []) {
        update_option(self::OPTION_LAST_STATUS, [
            'state' => sanitize_key($state),
            'message' => sanitize_text_field($message),
            'context' => sanitize_key($context),
            'checked_at' => current_time('mysql'),
            'can_send_email' => !empty($result['can_send_email']) || !empty($result['success']),
        ], false);
    }

    private static function get_sender_email($settings) {
        if (is_object($settings) && isset($settings->femail) && is_email($settings->femail)) {
            return sanitize_email($settings->femail);
        }

        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $host = $host ? strtolower(preg_replace('/:\d+$/', '', str_replace('www.', '', $host))) : 'yourdomain.com';
        return sanitize_email('no-reply@' . $host);
    }

    private static function get_license_type() {
        switch ((int) get_option('emsfb_pro', 2)) {
            case 1:
                return 'pro';
            case 3:
                return 'free_plus';
            case 0:
                return 'pro_pending';
            default:
                return 'free';
        }
    }

    private static function schedule_lifecycle_test($context) {
        $args = [$context];
        if (wp_next_scheduled(self::LIFECYCLE_HOOK, $args)) {
            return true;
        }
        return wp_schedule_single_event(time() + 45, self::LIFECYCLE_HOOK, $args) !== false;
    }

    private static function ensure_default_option() {
        if (get_option(self::OPTION_ENABLED, null) === null) {
            add_option(self::OPTION_ENABLED, 1, '', false);
        }
    }

	private static function remote_request($method, $path, $args) {
		$base_url = defined('EMSFB_EMAIL_TESTER_URL') && EMSFB_EMAIL_TESTER_URL
			? untrailingslashit((string) EMSFB_EMAIL_TESTER_URL)
			: (defined('EMSFB_SERVER_URL') ? untrailingslashit(EMSFB_SERVER_URL) : 'https://whitestudio.team');
		$endpoint = $base_url . '/wp-json/ws-email-tester/v1' . $path;
		$response = strtoupper($method) === 'POST'
			? wp_remote_post($endpoint, $args)
			: wp_remote_get($endpoint, $args);

		if (!is_wp_error($response) || strpos($base_url, '://www.') !== false || !preg_match('#://whitestudio\.team/?$#', $base_url)) {
			return $response;
		}

        $fallback = preg_replace('#://#', '://www.', $base_url, 1) . '/wp-json/ws-email-tester/v1' . $path;
        return strtoupper($method) === 'POST'
            ? wp_remote_post($fallback, $args)
            : wp_remote_get($fallback, $args);
    }

    private static function is_valid_hash($test_hash) {
        return is_string($test_hash) && (bool) preg_match('/^[a-f0-9]{64}$/i', $test_hash);
    }

    private static function unschedule_hook($hook) {
        if (function_exists('wp_unschedule_hook')) {
            wp_unschedule_hook($hook);
            return;
        }

        while ($timestamp = wp_next_scheduled($hook)) {
            wp_unschedule_event($timestamp, $hook);
        }
    }
}

Email_Monitor::register();
