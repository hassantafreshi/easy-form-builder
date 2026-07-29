<?php

namespace Emsfb;

defined('ABSPATH') || exit;

class Email_Monitor {

    const OPTION_ENABLED = 'emsfb_weekly_email_report_enabled';
    const OPTION_EMAIL_STATS_ENABLED = 'emsfb_email_stats_enabled';
    const OPTION_PENDING = 'emsfb_email_monitor_pending_test';
    const OPTION_LAST_STATUS = 'emsfb_email_monitor_last_status';
    const OPTION_LAST_UPDATE_VERSION = 'emsfb_email_monitor_last_update_version';
    const OPTION_ACTIVATION_MARKER = 'emsfb_email_monitor_activation_marker';

    const TRANSIENT_WEEKLY_REPORT_LOCK = 'emsfb_weekly_admin_report_lock';

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
        if (self::is_weekly_run_enabled()) {
            if (!$scheduled) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'emsfb_weekly', self::WEEKLY_HOOK);
            }
        } elseif ($scheduled) {
            self::unschedule_hook(self::WEEKLY_HOOK);
        }
    }

    /**
     * Whether the weekly run should happen at all.
     *
     * The weekly email carries two independent sections and each toggle owns
     * one of them: emailStatsReport owns the email delivery status section and
     * weeklyEmailReport owns the form activity section. Either one on its own
     * is still worth a weekly delivery test, so the run is scheduled whenever
     * at least one is enabled, and skipped entirely when both are off.
     */
    public static function is_weekly_run_enabled() {
        return self::is_enabled() || self::is_email_stats_enabled();
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

    /**
     * Whether email delivery statistics are collected and reported.
     * Enabled by default; only full Pro (package 1) can turn it off.
     */
    public static function is_email_stats_enabled() {
        if (!self::can_manage_email_stats()) {
            return true;
        }
        if (get_option(self::OPTION_EMAIL_STATS_ENABLED, null) === null) {
            add_option(self::OPTION_EMAIL_STATS_ENABLED, 1, '', false);
        }
        return (bool) get_option(self::OPTION_EMAIL_STATS_ENABLED, 1);
    }

    public static function can_manage_email_stats($package_type = null) {
        if ($package_type === null) {
            $package_type = (int) get_option('emsfb_pro', 2);
        }
        return (int) $package_type === 1;
    }

    public static function update_email_stats_enabled($enabled, $package_type = null) {
        if (!self::can_manage_email_stats($package_type)) {
            return false;
        }

        update_option(self::OPTION_EMAIL_STATS_ENABLED, self::normalize_bool($enabled) ? 1 : 0, false);
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
        if (!self::is_weekly_run_enabled()) {
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
		}

        self::send_weekly_admin_report($pending['context'], $can_send, $message, $result);

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

        // Every key here must exist in the tester service's /start allow-list.
        // The service rejects the whole request when an unknown key is present
        // or when the field count exceeds that list, so nothing site-specific
        // (form counts, submission totals, trigger names) may travel with it.
        // admin_report=client tells the service to stay silent: this plugin
        // reads the finished report from /result and emails the administrator
        // itself, so the site owner gets one email from their own site.
        $start_payload = [
            'site_url' => home_url(),
            'sender_email' => $sender_email,
            'admin_email' => $admin_email,
            'plugin' => 'easy-form-builder',
            'plugin_version' => defined('EMSFB_PLUGIN_VERSION') ? EMSFB_PLUGIN_VERSION : '',
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'language' => get_locale(),
            'license_type' => self::get_license_type(),
            'license_key' => '',
            'admin_report' => 'client',
        ];

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
        // This message is delivered to the tester service mailbox, not to the
        // site owner, so it stays a bare delivery probe. Form activity totals
        // belong in the administrator email this plugin composes locally.
        $message = sprintf(
            '<p>Easy Form Builder automated email delivery test.</p><p>Site: %s</p><p>Trigger: %s</p><p>Test hash: %s</p>',
            esc_html(home_url()),
            esc_html($context),
            esc_html($test_hash)
        );
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
			self::send_weekly_admin_report($pending['context'], false, $final_message, ['status' => $state]);
			delete_option(self::OPTION_PENDING);
			return;
		}

        update_option(self::OPTION_PENDING, $pending, false);
        wp_schedule_single_event(time() + 60, self::POLL_HOOK, [$pending['test_hash']]);
    }

    private static function finish_without_test($context, $state, $message) {
        self::save_status('failed', $message, $context, ['status' => $state, 'can_send_email' => false]);
        self::send_weekly_admin_report($context, false, $message, ['status' => $state]);
    }

    /**
     * Weekly form activity totals for the administrator email.
     *
     * These never leave the site: they are read here and rendered straight into
     * the administrator's own email.
     */
    public static function get_form_activity_stats() {
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

        return [
            'forms_total' => $forms_total,
            'forms_active' => $forms_active,
            'forms_inactive' => max(0, $forms_total - $forms_active),
            'page_views' => $visits,
            'submissions' => $submissions,
        ];
    }

    /**
     * Which sections the weekly administrator email should carry.
     *
     * Each toggle owns exactly one section, so all four combinations are
     * meaningful: both on sends one email with both sections, one on sends
     * that section alone, and both off sends nothing at all.
     *
     * @return array{delivery:bool,activity:bool}
     */
    public static function get_weekly_report_sections() {
        return [
            'delivery' => self::is_email_stats_enabled(),
            'activity' => self::is_enabled(),
        ];
    }

    /**
     * Email the site administrator the weekly report this plugin composed itself.
     *
     * The tester service is started with admin_report=client precisely so it
     * stays silent, which lets both sections arrive together in one message
     * sent from the site's own address instead of two from two senders.
     *
     * @param string $context  Trigger context; only 'weekly' produces a report.
     * @param bool   $can_send Whether the delivery test confirmed sending works.
     * @param string $message  Human-readable delivery test outcome.
     * @param array  $result   Raw report payload from the tester service.
     * @return bool Whether an email was sent.
     */
    private static function send_weekly_admin_report($context, $can_send, $message, $result = []) {
        if (sanitize_key($context) !== 'weekly') {
            return false;
        }

        $sections = self::get_weekly_report_sections();
        if (!$sections['delivery'] && !$sections['activity']) {
            return false;
        }

        $admin_email = sanitize_email(get_option('admin_email', ''));
        if (!is_email($admin_email)) {
            return false;
        }

        // WP-Cron can run the same event twice when two requests spawn it at
        // once, and every terminal path of a run ends here. The service used to
        // absorb that with its own lock; now that this side sends the mail, a
        // duplicate would land in the administrator's inbox. A whole run
        // finishes within ~12 minutes and the next weekly run is a week away
        // (an hour away at worst, when the toggles are switched off and on), so
        // a short lock separates duplicates from a genuine next run.
        if (get_transient(self::TRANSIENT_WEEKLY_REPORT_LOCK)) {
            return false;
        }
        set_transient(self::TRANSIENT_WEEKLY_REPORT_LOCK, 1, 30 * MINUTE_IN_SECONDS);

        $body = self::build_weekly_report_html($sections, (bool) $can_send, (string) $message, is_array($result) ? $result : []);
        $subject = sprintf(
            /* translators: %s: site name. */
            __('Weekly Easy Form Builder report for %s', 'easy-form-builder'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
        );

        $sent = wp_mail($admin_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        if (!$sent) {
            // Nothing reached the administrator, so let the next attempt through.
            delete_transient(self::TRANSIENT_WEEKLY_REPORT_LOCK);
        }

        update_option('emsfb_email_monitor_last_remote_report', [
            'sent' => (bool) $sent,
            'sent_at' => current_time('mysql', true),
            'sections' => array_keys(array_filter($sections)),
            'can_send_email' => (bool) $can_send,
        ], false);

        return (bool) $sent;
    }

    /**
     * Render the weekly administrator email.
     *
     * @param array  $sections Which sections to include.
     * @param bool   $can_send Whether the delivery test confirmed sending works.
     * @param string $message  Human-readable delivery test outcome.
     * @param array  $result   Raw report payload from the tester service.
     * @return string
     */
    private static function build_weekly_report_html($sections, $can_send, $message, $result) {
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $html = '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;font-size:14px;line-height:22px;color:#1f2937;">';
        $html .= '<h1 style="font-size:18px;margin:0 0 4px 0;">' . esc_html__('Weekly Easy Form Builder report', 'easy-form-builder') . '</h1>';
        $html .= '<p style="margin:0 0 20px 0;color:#6b7280;">' . esc_html($site_name) . ' &middot; ' . esc_html(home_url()) . '</p>';

        if (!empty($sections['delivery'])) {
            $status_label = $can_send
                ? __('Working', 'easy-form-builder')
                : __('Needs attention', 'easy-form-builder');
            $status_color = $can_send ? '#047857' : '#b91c1c';

            $html .= '<h2 style="font-size:16px;margin:0 0 8px 0;">' . esc_html__('Email delivery status', 'easy-form-builder') . '</h2>';
            $html .= '<p style="margin:0 0 8px 0;"><strong style="color:' . esc_attr($status_color) . ';">' . esc_html($status_label) . '</strong></p>';
            if ($message !== '') {
                $html .= '<p style="margin:0 0 12px 0;">' . esc_html($message) . '</p>';
            }

            // $result is whatever the remote service returned, so each value is
            // checked for the shape it is about to be cast to rather than
            // assumed. A field arriving as an array must be skipped, not
            // stringified into "Array".
            $rows = [];
            if (isset($result['score']) && is_scalar($result['score'])) {
                $rows[__('Deliverability score', 'easy-form-builder')] = (string) (int) $result['score'];
            }
            if (!empty($result['grade']) && is_scalar($result['grade'])) {
                $rows[__('Grade', 'easy-form-builder')] = (string) $result['grade'];
            }
            $authentication = isset($result['authentication']) && is_array($result['authentication'])
                ? $result['authentication']
                : [];
            foreach (['spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC'] as $key => $label) {
                if (!empty($authentication[$key]) && is_scalar($authentication[$key])) {
                    $rows[$label] = (string) $authentication[$key];
                }
            }

            require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-email-handler.php';
            $email_stats = \EmsfbEmailHandler::get_email_stats('week');
            $rows[__('Emails sent this week', 'easy-form-builder')] = (string) (int) $email_stats['success'];
            $rows[__('Emails failed this week', 'easy-form-builder')] = (string) (int) $email_stats['failed'];

            $html .= self::build_report_table($rows);

            if (!empty($result['recommendations']) && is_array($result['recommendations'])) {
                $html .= '<p style="margin:12px 0 6px 0;"><strong>' . esc_html__('Recommendations', 'easy-form-builder') . '</strong></p><ul style="margin:0 0 16px 18px;padding:0;">';
                foreach (array_slice($result['recommendations'], 0, 5) as $recommendation) {
                    if (is_scalar($recommendation)) {
                        $html .= '<li style="margin:0 0 6px 0;">' . esc_html((string) $recommendation) . '</li>';
                    }
                }
                $html .= '</ul>';
            }
        }

        if (!empty($sections['activity'])) {
            $activity = self::get_form_activity_stats();
            $labels = [
                'forms_total' => __('Total forms', 'easy-form-builder'),
                'forms_active' => __('Active forms', 'easy-form-builder'),
                'forms_inactive' => __('Inactive forms', 'easy-form-builder'),
                'page_views' => __('Form views this week', 'easy-form-builder'),
                'submissions' => __('Submissions this week', 'easy-form-builder'),
            ];

            $rows = [];
            foreach ($labels as $key => $label) {
                $rows[$label] = (string) (int) $activity[$key];
            }

            $html .= '<h2 style="font-size:16px;margin:24px 0 8px 0;">' . esc_html__('Form activity', 'easy-form-builder') . '</h2>';
            $html .= self::build_report_table($rows);
        }

        $html .= '<p style="margin:24px 0 0 0;color:#6b7280;font-size:12px;">' . esc_html__('You can turn these reports off in Easy Form Builder settings.', 'easy-form-builder') . '</p>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string,string> $rows Label => value.
     * @return string
     */
    private static function build_report_table($rows) {
        if (empty($rows)) {
            return '';
        }

        $html = '<table style="border-collapse:collapse;width:100%;max-width:480px;">';
        foreach ($rows as $label => $value) {
            $html .= '<tr>'
                . '<td style="padding:6px 12px 6px 0;border-bottom:1px solid #e5e7eb;color:#4b5563;">' . esc_html($label) . '</td>'
                . '<td style="padding:6px 0;border-bottom:1px solid #e5e7eb;font-weight:600;">' . esc_html($value) . '</td>'
                . '</tr>';
        }

        return $html . '</table>';
    }

    /**
     * Record that the automated delivery test succeeded.
     *
     * This only stores the diagnostic status. The "This site can send emails"
     * switch (settings->smtp) is what actually enables notification emails, and
     * it stays under the admin's control: a background test running minutes
     * after activation used to flip it on by itself, so a brand-new site showed
     * the switch already enabled while nobody had verified real delivery.
     * Enabling it is now always an explicit admin action.
     */
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
		$base_url = 'https://whitestudio.team';
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
