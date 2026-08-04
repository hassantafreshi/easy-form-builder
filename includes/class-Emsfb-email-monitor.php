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

    // WordPress numbers Sunday as 0, so Friday is 5. The timestamp is built
    // in the site's configured timezone, not the server's timezone.
    const WEEKLY_REPORT_WEEKDAY = 5;
    const WEEKLY_REPORT_HOUR = 11;

    public static function register() {
        add_filter('cron_schedules', [__CLASS__, 'add_weekly_schedule']);
        add_action(self::WEEKLY_HOOK, [__CLASS__, 'run_weekly_test']);
        add_action(self::LIFECYCLE_HOOK, [__CLASS__, 'run_lifecycle_test'], 10, 1);
        add_action(self::POLL_HOOK, [__CLASS__, 'poll_test'], 10, 1);
        add_action('init', [__CLASS__, 'ensure_schedule']);
        add_action('admin_notices', [__CLASS__, 'render_delivery_failure_notice']);
        add_action('wp_ajax_emsfb_dismiss_delivery_notice', [__CLASS__, 'ajax_dismiss_delivery_notice']);
    }

    /**
     * Warn in wp-admin when the last delivery test could not get an email
     * through.
     *
     * A failing weekly report is the one message that cannot report itself: if
     * sending is broken, the email explaining that sending is broken does not
     * arrive either. So the same guidance is surfaced where the administrator
     * will actually see it.
     *
     * @return void
     */
    public static function render_delivery_failure_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $status = get_option(self::OPTION_LAST_STATUS, []);
        if (!is_array($status) || empty($status)) {
            return;
        }

        // Only speak up once a test has actually concluded that nothing got
        // through. A pending or in-progress run says nothing yet.
        $state = isset($status['state']) ? (string) $status['state'] : '';
        if (in_array($state, ['pending', 'running', 'queued', 'delayed'], true)) {
            return;
        }
        if (!empty($status['can_send_email'])) {
            return;
        }

        // Dismissal is tied to the run it was dismissed for, so a later failure
        // speaks up again instead of staying silent forever.
        $fingerprint = md5((string) ($status['checked_at'] ?? '') . '|' . $state);
        if (get_user_meta(get_current_user_id(), 'emsfb_delivery_notice_dismissed', true) === $fingerprint) {
            return;
        }

        $panel_url = admin_url('admin.php?page=Emsfb&state=setting&tab=email');
        $guide_url = self::get_smtp_guide_url();
        $logo_url  = EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo.png';
        ?>
        <div class="efb notice notice-error is-dismissible efb-delivery-notice" data-efb-fingerprint="<?php echo esc_attr($fingerprint); ?>" style="display:flex;align-items:flex-start;gap:14px;padding:14px 18px;">
            <img src="<?php echo esc_url($logo_url); ?>" alt="" style="width:42px;height:auto;margin-top:2px;flex-shrink:0;" />
            <div style="flex:1;min-width:0;">
                <p style="margin:0 0 6px;font-size:14px;">
                    <strong><?php esc_html_e('Easy Form Builder', 'easy-form-builder'); ?></strong>
                    &mdash;
                    <?php esc_html_e('Your form emails are not being delivered', 'easy-form-builder'); ?>
                </p>
                <p style="margin:0 0 10px;color:#555;max-width:820px;">
                    <?php echo esc_html(self::get_delivery_check_message()); ?>
                </p>
                <p style="margin:0;">
                    <a href="<?php echo esc_url($panel_url); ?>" class="button button-primary"><?php esc_html_e('Run the email check', 'easy-form-builder'); ?></a>
                    <a href="<?php echo esc_url($guide_url); ?>" target="_blank" rel="noopener" style="margin-inline-start:10px;"><?php echo esc_html(self::get_smtp_guide_label()); ?></a>
                </p>
            </div>
        </div>
        <script>
        (function(){
            var n = document.querySelector('.efb-delivery-notice');
            if (!n) { return; }
            n.addEventListener('click', function(e){
                if (!e.target.classList.contains('notice-dismiss')) { return; }
                var body = new URLSearchParams({
                    action: 'emsfb_dismiss_delivery_notice',
                    fingerprint: n.getAttribute('data-efb-fingerprint'),
                    _wpnonce: '<?php echo esc_js(wp_create_nonce('emsfb_dismiss_delivery_notice')); ?>'
                });
                fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST', credentials: 'same-origin', body: body
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * Remember that this administrator dismissed the notice for this run.
     *
     * @return void
     */
    public static function ajax_dismiss_delivery_notice() {
        check_ajax_referer('emsfb_dismiss_delivery_notice');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('', 403);
        }

        $fingerprint = isset($_POST['fingerprint']) ? sanitize_text_field(wp_unslash($_POST['fingerprint'])) : '';
        if ($fingerprint !== '') {
            update_user_meta(get_current_user_id(), 'emsfb_delivery_notice_dismissed', $fingerprint);
        }

        wp_send_json_success();
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
            // Move installations that were scheduled under the old
            // "one hour from now" behaviour onto Friday as well. Only this
            // plugin's hook is replaced; no other cron event is touched.
            if (!$scheduled || !self::is_weekly_report_schedule($scheduled)) {
                if ($scheduled) {
                    self::unschedule_hook(self::WEEKLY_HOOK);
                }
                wp_schedule_event(self::get_next_weekly_report_timestamp(), 'emsfb_weekly', self::WEEKLY_HOOK);
            }
        } elseif ($scheduled) {
            self::unschedule_hook(self::WEEKLY_HOOK);
        }
    }

    /**
     * Next Friday at 09:00 in the WordPress site timezone.
     *
     * @return int Unix timestamp, as required by WP-Cron.
     */
    private static function get_next_weekly_report_timestamp() {
        $now = new \DateTimeImmutable('now', wp_timezone());
        $next = $now->setTime(self::WEEKLY_REPORT_HOUR, 0, 0);
        $days_until_friday = (self::WEEKLY_REPORT_WEEKDAY - (int) $next->format('w') + 7) % 7;

        if ($days_until_friday === 0 && $next <= $now) {
            $days_until_friday = 7;
        }

        return $next->modify('+' . $days_until_friday . ' days')->getTimestamp();
    }

    /**
     * Whether an existing timestamp already matches Friday at 09:00 locally.
     */
    private static function is_weekly_report_schedule($timestamp) {
        if (!is_numeric($timestamp) || (int) $timestamp <= 0) {
            return false;
        }

        $scheduled = (new \DateTimeImmutable('@' . (int) $timestamp))->setTimezone(wp_timezone());
        return (int) $scheduled->format('w') === self::WEEKLY_REPORT_WEEKDAY
            && (int) $scheduled->format('G') === self::WEEKLY_REPORT_HOUR
            && (int) $scheduled->format('i') === 0;
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

        $report_content = self::build_weekly_report_html($sections, (bool) $can_send, (string) $message, is_array($result) ? $result : []);
        require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-email-handler.php';

        // Use the same template selected under Easy Form Builder settings for
        // every other plugin email. The report stays a self-contained HTML
        // fragment, so it also works in custom templates that place
        // shortcode_message inside a styled message block.
        $email_handler = new \EmsfbEmailHandler();
        $body = $email_handler->email_template_efb(
            (int) get_option('emsfb_pro', 2) === 1,
            'weeklyAdminReport',
            $report_content,
            home_url(),
            'just_message'
        );
        if ($body === '' || strpos($body, $report_content) === false) {
            // A malformed saved template (for example, one from an older
            // version without shortcode_message) must never hide a delivery
            // warning from the administrator.
            $body = $report_content;
        }
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
     * Render the content placed inside the weekly administrator email template.
     *
     * @param array  $sections Which sections to include.
     * @param bool   $can_send Whether the delivery test confirmed sending works.
     * @param string $message  Human-readable delivery test outcome.
     * @param array  $result   Raw report payload from the tester service.
     * @return string
     */
    private static function build_weekly_report_html($sections, $can_send, $message, $result) {
        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $is_rtl = is_rtl();
        $direction = $is_rtl ? 'rtl' : 'ltr';
        $align = $is_rtl ? 'right' : 'left';
        $row_label_padding = $is_rtl ? '0 0 0 12px' : '0 12px 0 0';
        $generated_at = wp_date(get_option('date_format', 'F j, Y') . ' ' . get_option('time_format', 'g:i a'));
        $intro = sprintf(
            /* translators: %s: site name. */
            __('Here is your weekly snapshot for %s.', 'easy-form-builder'),
            $site_name
        );

        // Accent colour and font follow whatever the administrator configured for
        // their emails; only the structure below is fixed. See get_body_theme().
        $theme = self::get_body_theme();

        // Keep the report itself table-based, with direction and alignment set
        // on every important container. That prevents a saved LTR template
        // from reversing Persian or Arabic report content in Outlook/Gmail.
        // The font is the administrator's own (sanitize_css_font() guarantees a
        // family Outlook can resolve at the end of it).
        $html = '<div dir="' . esc_attr($direction) . '" style="direction:' . esc_attr($direction) . ';text-align:' . esc_attr($align) . ';font-family:' . esc_attr($theme['font']) . ';font-size:14px;line-height:1.6;color:#1f2937;">';
        $html .= '<p style="margin:0 0 4px 0;font-size:16px;line-height:24px;font-weight:700;text-align:' . esc_attr($align) . ';">' . esc_html($intro) . '</p>';
        $html .= '<p style="margin:0 0 22px 0;font-size:12px;line-height:18px;color:#6b7280;text-align:' . esc_attr($align) . ';">' . sprintf(
            /* translators: %s: report generation date and time. */
            esc_html__('Report generated %s', 'easy-form-builder'),
            esc_html($generated_at)
        ) . '</p>';

        if (!empty($sections['delivery'])) {
            $has_score = isset($result['score']) && is_scalar($result['score']);
            $score = $has_score ? (int) $result['score'] : null;
            $grade = (!empty($result['grade']) && is_scalar($result['grade'])) ? (string) $result['grade'] : '';

            $html .= self::build_score_hero($score, $grade, $can_send, $message, $result, $direction, $align);

            // Three outcomes need guidance, and they need the same guidance:
            // no score at all (the check never ran or returned nothing), a score
            // below the healthy threshold, and a delivery attempt that failed
            // outright. Anything else is working and gets no lecture.
            if (!$has_score) {
                $html .= self::build_delivery_guidance_block($direction, $align, 'unknown');
            } elseif ($score < self::HEALTHY_SCORE || !$can_send) {
                $html .= self::build_delivery_guidance_block($direction, $align, 'low');
            }

            if (!empty($result['recommendations']) && is_array($result['recommendations'])) {
                $recommendation_rows = '';
                foreach (array_slice($result['recommendations'], 0, 5) as $recommendation) {
                    if (is_scalar($recommendation)) {
                        $recommendation_rows .= '<tr><td width="16" valign="top" style="width:16px;padding:0 ' . ($is_rtl ? '0 0 7px' : '7px 0 0') . ';color:' . esc_attr($theme['accent']) . ';font-size:15px;line-height:20px;">&bull;</td>'
                            . '<td dir="auto" align="' . esc_attr($align) . '" style="padding:0 0 6px 0;color:#475569;font-size:12.5px;line-height:20px;text-align:' . esc_attr($align) . ';">' . esc_html((string) $recommendation) . '</td></tr>';
                    }
                }
                if ($recommendation_rows !== '') {
                    $html .= '<table role="presentation" dir="' . esc_attr($direction) . '" cellspacing="0" cellpadding="0" border="0" width="100%" style="' . self::TABLE_RESET . 'width:100%;margin:14px 0 0 0;">'
                        . '<tr><td align="' . esc_attr($align) . '" style="padding:14px 15px;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;text-align:' . esc_attr($align) . ';">'
                        . '<p style="margin:0 0 8px 0;font-size:13px;line-height:20px;font-weight:700;color:#334155;text-align:' . esc_attr($align) . ';">' . esc_html__('Detailed analysis', 'easy-form-builder') . '</p>'
                        . '<table role="presentation" dir="' . esc_attr($direction) . '" cellspacing="0" cellpadding="0" border="0" width="100%" style="' . self::TABLE_RESET . 'width:100%;">' . $recommendation_rows . '</table>'
                        . '</td></tr></table>';
                }
            }

            require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-email-handler.php';
            $email_stats = \EmsfbEmailHandler::get_email_stats('week');
            $failed = (int) $email_stats['failed'];

            $html .= self::build_section_heading(__('This week', 'easy-form-builder'), $align, 22);

            $activity = !empty($sections['activity']) ? self::get_form_activity_stats() : null;
            $tiles = [];
            if ($activity) {
                $tiles[] = ['value' => (int) $activity['page_views'], 'label' => __('Form views', 'easy-form-builder'), 'tone' => 'accent'];
                $tiles[] = ['value' => (int) $activity['submissions'], 'label' => __('Submissions', 'easy-form-builder'), 'tone' => 'accent'];
            }
            $tiles[] = ['value' => (int) $email_stats['success'], 'label' => __('Emails sent', 'easy-form-builder'), 'tone' => 'good'];
            $tiles[] = ['value' => $failed, 'label' => __('Emails failed', 'easy-form-builder'), 'tone' => $failed > 0 ? 'bad' : 'muted'];

            $html .= self::build_stat_tiles($tiles, $direction, $align, $theme);

            if ($activity) {
                $html .= self::build_forms_summary_line($activity, $direction, $align);
            }
        } elseif (!empty($sections['activity'])) {
            // Activity-only delivery: no score section was requested, so the
            // tiles carry the form numbers on their own.
            $activity = self::get_form_activity_stats();
            $html .= self::build_section_heading(__('This week', 'easy-form-builder'), $align, 0);
            $html .= self::build_stat_tiles([
                ['value' => (int) $activity['page_views'], 'label' => __('Form views', 'easy-form-builder'), 'tone' => 'accent'],
                ['value' => (int) $activity['submissions'], 'label' => __('Submissions', 'easy-form-builder'), 'tone' => 'accent'],
            ], $direction, $align, $theme);
            $html .= self::build_forms_summary_line($activity, $direction, $align);
        }

        if ((int) get_option('emsfb_pro', 2) !== 1) {
            $html .= self::build_pro_upgrade_callout($direction, $align);
        }

        $html .= '<p style="margin:24px 0 0 0;color:#6b7280;font-size:12px;line-height:18px;text-align:' . esc_attr($align) . ';">' . esc_html__('You can turn these reports off in Easy Form Builder settings.', 'easy-form-builder') . '</p>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string,string> $rows Label => value.
     * @return string
     */
    /**
     * Score at or above which delivery is considered healthy.
     *
     * Matches what the panel already tells the administrator ("If the score is
     * above 70 - enable the This site can send emails switch and save"), so the
     * email and the panel never disagree.
     */
    const HEALTHY_SCORE = 70;

    /**
     * Table declarations every email client needs.
     *
     * Outlook inserts its own spacing around tables unless mso-table-lspace and
     * mso-table-rspace are zeroed, and leaves hairline gaps between cells
     * without border-collapse. Both are cheap and stop the report drifting
     * apart in Outlook while looking correct everywhere else.
     */
    const TABLE_RESET = 'border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt;';

    /**
     * Fonts Outlook's Word engine is guaranteed to resolve.
     *
     * A stack it cannot parse - one made only of -apple-system and
     * BlinkMacSystemFont, or a single custom family from the template settings -
     * makes Word fall back to Times New Roman, so the whole report arrives in a
     * serif face. Appending these guarantees a sans-serif last resort.
     */
    const FONT_FALLBACK = "Segoe UI,Tahoma,Arial,Helvetica,sans-serif";

    /**
     * Documentation URL for setting up SMTP, in the reader's language.
     *
     * Mirrors the mapping EmsfbEmailHandler already uses for the test email so
     * a Persian or Arabic administrator is not sent to an English article.
     *
     * @return string
     */
    public static function get_smtp_guide_url() {
        $locale = get_locale();

        if ($locale === 'fa_IR') {
            return 'https://easyformbuilder.ir/Ø¯Ø§Ú©ÛŒÙˆÙ…Ù†Øª/Ø§Ø±Ø³Ø§Ù„-Ø§ÛŒÙ…ÛŒÙ„-Ø¨ÙˆØ³ÛŒÙ„Ù‡-Ø§ÙØ²ÙˆÙ†Ù‡-smtp/';
        }
        if (strpos($locale, 'ar') === 0) {
            return 'https://ar.whitestudio.team/document/send-email-using-smtp-plugin/';
        }
        if (strpos($locale, 'de') === 0) {
            return 'https://de.whitestudio.team/document/send-email-using-smtp-plugin/';
        }

        return 'https://whitestudio.team/document/send-email-using-smtp-plugin/';
    }

    /**
     * The single sentence shown wherever delivery could not be confirmed - the
     * weekly email and the dashboard notice both use it, so the administrator
     * reads the same advice in both places.
     *
     * @return string
     */
    public static function get_delivery_check_message() {
        return __('Run the email check so you can be sure the messages your forms send are actually reaching people. The check sends a real message through the new WhiteStudio delivery service and reports back what arrived.', 'easy-form-builder');
    }

    /**
     * @return string Anchor text for the SMTP guide.
     */
    public static function get_smtp_guide_label() {
        return __('Read the guide to sending email through SMTP', 'easy-form-builder');
    }

    /**
     * Colour and font the report body borrows from the administrator's own
     * email settings.
     *
     * The report header is deliberately left alone - it belongs to the email
     * template and stays exactly as it is. Only the body picks up the
     * customisation, so a site that has themed its emails gets a report that
     * matches, without the report being able to restyle the header.
     *
     * @return array{accent:string, accent_text:string, font:string}
     */
    private static function get_body_theme() {
        $accent = '#202a8d';
        $accent_text = '#ffffff';
        $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Tahoma,Arial,sans-serif";

        $settings = function_exists('get_setting_Emsfb') ? get_setting_Emsfb('decoded') : null;
        if (is_object($settings)) {
            if (!empty($settings->emailBtnBgColor) && is_scalar($settings->emailBtnBgColor)) {
                $accent = (string) $settings->emailBtnBgColor;
            }
            if (!empty($settings->emailBtnTextColor) && is_scalar($settings->emailBtnTextColor)) {
                $accent_text = (string) $settings->emailBtnTextColor;
            }
        }

        // A saved template's own global settings win over the plain colour
        // pickers, matching the precedence EmsfbEmailHandler applies.
        $template = is_object($settings) && !empty($settings->emailTemp) && is_string($settings->emailTemp)
            ? $settings->emailTemp
            : '';
        if ($template !== '' && preg_match('/<!-- EFBDATA:([\S]+) -->/', $template, $match)) {
            $data = json_decode(urldecode($match[1]), true);
            $global = (is_array($data) && isset($data['globalSettings']) && is_array($data['globalSettings']))
                ? $data['globalSettings']
                : [];
            if (!empty($global['btnBgColor']) && is_scalar($global['btnBgColor'])) {
                $accent = (string) $global['btnBgColor'];
            }
            if (!empty($global['btnTextColor']) && is_scalar($global['btnTextColor'])) {
                $accent_text = (string) $global['btnTextColor'];
            }
            if (!empty($global['fontFamily']) && is_scalar($global['fontFamily'])) {
                $font = (string) $global['fontFamily'];
            }
        }

        return [
            'accent'      => self::sanitize_css_colour($accent, '#202a8d'),
            'accent_text' => self::sanitize_css_colour($accent_text, '#ffffff'),
            'font'        => self::sanitize_css_font($font),
        ];
    }

    /**
     * Only accept a colour we are willing to drop into a style attribute.
     *
     * @param string $value    Candidate colour.
     * @param string $fallback Used when the candidate is not a plain colour.
     * @return string
     */
    private static function sanitize_css_colour($value, $fallback) {
        $value = trim((string) $value);

        if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return $value;
        }
        if (preg_match('/^rgba?\(\s*[0-9.]+\s*,\s*[0-9.]+\s*,\s*[0-9.]+\s*(?:,\s*[0-9.]+\s*)?\)$/', $value)) {
            return $value;
        }
        if (preg_match('/^[a-zA-Z]{3,20}$/', $value)) {
            return $value;
        }

        return $fallback;
    }

    /**
     * @param string $value Candidate font stack.
     * @return string
     */
    private static function sanitize_css_font($value) {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/[<>{};:]/', $value)) {
            return self::FONT_FALLBACK;
        }

        // Guarantee a family Outlook can resolve at the end of whatever the
        // administrator configured, so Word never drops back to Times New Roman.
        if (false === stripos($value, 'sans-serif') && false === stripos($value, 'serif')) {
            $value .= ',' . self::FONT_FALLBACK;
        }

        return $value;
    }

    /**
     * The banner at the top of the report body: the deliverability score, or a
     * clear neutral state when there is no score to show.
     *
     * @param int|null $score
     * @param string   $grade
     * @param bool     $can_send
     * @param string   $message
     * @param array    $result
     * @param string   $direction
     * @param string   $align
     * @return string
     */
    private static function build_score_hero($score, $grade, $can_send, $message, $result, $direction, $align) {
        if (null === $score) {
            // Nothing was measured. Do not imply either success or failure.
            $bg = '#f6f7fb';
            $border = '#e0e3ee';
            $badge_bg = '#8b93a7';
            $badge_text = '&#63;';
            $badge_sub = '';
            $title = $can_send
                ? __('Delivery has not been scored yet', 'easy-form-builder')
                : __('We could not confirm your emails are being delivered', 'easy-form-builder');
            $title_colour = '#3f4657';
            $subtitle = '';
        } else {
            $healthy = $score >= self::HEALTHY_SCORE && $can_send;
            $bg = $healthy ? '#f2fbf6' : '#fdf5f3';
            $border = $healthy ? '#d3ecdf' : '#f4d6cf';
            $badge_bg = $healthy ? '#0f9d58' : '#d9483b';
            $badge_text = number_format_i18n($score);
            $badge_sub = __('out of 100', 'easy-form-builder');
            $title_colour = $healthy ? '#0b6b3d' : '#9c2f24';
            $title = $healthy
                ? __('Your email delivery is healthy', 'easy-form-builder')
                : __('Your emails are likely going to spam', 'easy-form-builder');
            if ($grade !== '') {
                $title .= ' &mdash; ' . sprintf(
                    /* translators: %s: deliverability grade such as A or D. */
                    esc_html__('grade %s', 'easy-form-builder'),
                    esc_html($grade)
                );
            }
            $subtitle = $message !== '' ? $message : '';
        }

        $html = '<table role="presentation" dir="' . esc_attr($direction) . '" cellspacing="0" cellpadding="0" border="0" width="100%" bgcolor="' . esc_attr($bg) . '" style="' . self::TABLE_RESET . 'width:100%;background-color:' . esc_attr($bg) . ';border:1px solid ' . esc_attr($border) . ';border-radius:12px;">'
            . '<tr><td align="center" style="padding:22px 20px;text-align:center;">';

        // Outlook 2007-2019 renders through Word: it drops border-radius (the
        // circle becomes a square) and ignores display:block on a span, so both
        // lines would collapse onto one. It does understand VML, so Outlook gets
        // a filled oval and every other client gets the CSS version.
        $html .= '<!--[if mso]>'
            . '<v:oval xmlns:v="urn:schemas-microsoft-com:vml" fill="t" stroke="f" style="width:92px;height:92px;v-text-anchor:middle;">'
            . '<v:fill color="' . esc_attr($badge_bg) . '" />'
            . '<v:textbox inset="0,0,0,0"><center style="color:#ffffff;font-family:Arial,sans-serif;font-size:30px;font-weight:bold;">' . $badge_text . '</center></v:textbox>'
            . '</v:oval>'
            . '<![endif]-->';

        $html .= '<!--[if !mso]><!-->'
            . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="' . self::TABLE_RESET . 'margin:0 auto;"><tr>'
            . '<td align="center" bgcolor="' . esc_attr($badge_bg) . '" width="92" height="92" style="width:92px;height:92px;background-color:' . esc_attr($badge_bg) . ';border-radius:46px;text-align:center;vertical-align:middle;color:#ffffff;">'
            . '<div style="font-size:32px;line-height:34px;font-weight:700;color:#ffffff;">' . $badge_text . '</div>';
        if ($badge_sub !== '') {
            $html .= '<div style="font-size:11px;line-height:16px;color:#eef7f1;">' . esc_html($badge_sub) . '</div>';
        }
        $html .= '</td></tr></table>'
            . '<!--<![endif]-->';

        $html .= '<p style="margin:12px 0 3px 0;color:' . esc_attr($title_colour) . ';font-size:17px;line-height:25px;font-weight:700;text-align:center;">' . wp_kses($title, ['br' => []]) . '</p>';
        if ($subtitle !== '') {
            $html .= '<p style="margin:0;color:#5b6474;font-size:13px;line-height:21px;text-align:center;">' . esc_html($subtitle) . '</p>';
        }

        $html .= self::build_auth_chips($result);
        $html .= '</td></tr></table>';

        return $html;
    }

    /**
     * SPF / DKIM / DMARC shown as pass-fail chips instead of table rows.
     *
     * @param array $result
     * @return string
     */
    private static function build_auth_chips($result) {
        $authentication = isset($result['authentication']) && is_array($result['authentication'])
            ? $result['authentication']
            : [];
        if (empty($authentication)) {
            return '';
        }

        $cells = '';
        foreach (['spf' => 'SPF', 'dkim' => 'DKIM', 'dmarc' => 'DMARC'] as $key => $label) {
            if (empty($authentication[$key]) || !is_scalar($authentication[$key])) {
                continue;
            }
            $value = strtolower(trim((string) $authentication[$key]));
            $passed = in_array($value, ['pass', 'passed', 'ok', 'valid', 'true', '1', 'yes'], true);
            $chip_colour = $passed ? '#0b6b3d' : '#9c2f24';
            $chip_border = $passed ? '#b7e2c9' : '#f0c3bc';
            $mark = $passed ? '&#10003;' : '&#10007;';

            $cells .= '<td style="padding:0 3px;"><table role="presentation" cellspacing="0" cellpadding="0" border="0" style="' . self::TABLE_RESET . '"><tr>'
                . '<td bgcolor="#ffffff" style="background:#ffffff;border:1px solid ' . $chip_border . ';border-radius:20px;padding:6px 13px;color:' . $chip_colour . ';font-size:12px;font-weight:700;white-space:nowrap;">'
                . $mark . ' ' . esc_html($label) . '</td></tr></table></td>';
        }

        if ($cells === '') {
            return '';
        }

        return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="' . self::TABLE_RESET . 'margin:14px auto 0;"><tr>' . $cells . '</tr></table>';
    }

    /**
     * The "what should I do" block. Shown when delivery is unproven or poor -
     * never when everything is working.
     *
     * @param string $direction
     * @param string $align
     * @param string $mode 'unknown' when nothing was measured, 'low' otherwise.
     * @return string
     */
    private static function build_delivery_guidance_block($direction, $align, $mode) {
        $is_rtl = ('rtl' === $direction);
        $url = self::get_smtp_guide_url();

        $html = '<table role="presentation" dir="' . esc_attr($direction) . '" cellspacing="0" cellpadding="0" border="0" width="100%" bgcolor="#fffbeb" style="' . self::TABLE_RESET . 'width:100%;margin:16px 0 0 0;background-color:#fffbeb;border:1px solid #fde68a;border-radius:12px;">'
            . '<tr><td align="' . esc_attr($align) . '" style="padding:19px 18px;text-align:' . esc_attr($align) . ';border-' . ($is_rtl ? 'right' : 'left') . ':4px solid #f59e0b;">';

        $html .= '<p style="margin:0 0 9px 0;color:#7c4a03;font-size:16px;line-height:24px;font-weight:700;text-align:' . esc_attr($align) . ';">'
            . esc_html__('What you should do', 'easy-form-builder') . '</p>';

        if ('unknown' === $mode) {
            $html .= '<p style="margin:0 0 13px 0;color:#5f4a2a;font-size:13.5px;line-height:23px;text-align:' . esc_attr($align) . ';">'
                . esc_html(self::get_delivery_check_message()) . '</p>';
        } else {
            $html .= '<p style="margin:0 0 12px 0;color:#5f4a2a;font-size:13.5px;line-height:23px;text-align:' . esc_attr($align) . ';">'
                . esc_html__('A hosting server usually sends mail without a trusted signature, which is what pushes your form emails into the spam folder or stops them arriving at all. The standard fix is to send through an SMTP service.', 'easy-form-builder')
                . '</p>';

            $steps = [
                __('Install an SMTP plugin and enter the details of your email service.', 'easy-form-builder'),
                __('Add the SPF and DKIM records to your domain settings.', 'easy-form-builder'),
                sprintf(
                    /* translators: %s: the healthy score threshold, such as 70. */
                    __('Run the email check again until the score is above %s.', 'easy-form-builder'),
                    number_format_i18n(self::HEALTHY_SCORE)
                ),
            ];
            $html .= '<table role="presentation" dir="' . esc_attr($direction) . '" cellspacing="0" cellpadding="0" border="0" width="100%" style="' . self::TABLE_RESET . 'width:100%;margin:0 0 14px 0;">';
            $index = 1;
            foreach ($steps as $step) {
                $html .= '<tr><td width="20" valign="top" style="width:20px;padding:0 ' . ($is_rtl ? '0 6px 8px' : '8px 6px 0') . ';color:#b45309;font-size:13px;line-height:21px;text-align:' . esc_attr($align) . ';">'
                    . esc_html(number_format_i18n($index)) . '.</td>'
                    . '<td align="' . esc_attr($align) . '" style="padding:0 0 6px 0;color:#5f4a2a;font-size:13px;line-height:21px;text-align:' . esc_attr($align) . ';">' . esc_html($step) . '</td></tr>';
                $index++;
            }
            $html .= '</table>';
        }

        // The padding sits on the cell, not on the link. Outlook's Word engine
        // ignores display:inline-block and padding on an inline <a>, which
        // collapsed this into bare text on a coloured strip; padding on a <td>
        // is one of the few things it does honour everywhere.
        $html .= '<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="' . esc_attr($align) . '" style="' . self::TABLE_RESET . '"><tr>'
            . '<td align="center" bgcolor="#b45309" style="padding:12px 22px;border-radius:6px;background-color:#b45309;">'
            . '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="color:#ffffff;font-size:13.5px;font-weight:700;line-height:18px;text-align:center;text-decoration:none;">'
            . esc_html(self::get_smtp_guide_label()) . '</a>'
            . '</td></tr></table>';

        return $html . '</td></tr></table>';
    }

    /**
     * Two-per-row number tiles. Built as a table so Outlook keeps the grid.
     *
     * @param array  $tiles
     * @param string $direction
     * @param string $align
     * @param array  $theme
     * @return string
     */
    private static function build_stat_tiles($tiles, $direction, $align, $theme) {
        if (empty($tiles)) {
            return '';
        }

        $palette = [
            'accent' => ['bg' => '#f7f8fc', 'border' => '#e4e7f2', 'value' => $theme['accent'], 'label' => '#6b7280'],
            'good'   => ['bg' => '#f2fbf6', 'border' => '#cfe9dc', 'value' => '#0f9d58', 'label' => '#6b7280'],
            'bad'    => ['bg' => '#fdf5f3', 'border' => '#f0c3bc', 'value' => '#d9483b', 'label' => '#9c2f24'],
            'muted'  => ['bg' => '#f7f8fc', 'border' => '#e4e7f2', 'value' => '#9aa1af', 'label' => '#6b7280'],
        ];

        // Fluid-hybrid layout. This fragment is injected into whatever email
        // template the site has saved, so it cannot rely on a <style> block or a
        // media query - there is nowhere dependable to put one, and Gmail strips
        // <style> in several contexts. Instead each tile is an inline-block with
        // a max-width: two sit side by side while the container is wide enough
        // and they stack by themselves on a phone, with no CSS at all.
        //
        // Outlook renders through Word, which supports neither inline-block nor
        // max-width, so it is handed a fixed two-column ghost table inside
        // conditional comments and never sees the divs' layout.
        $html = '<div dir="' . esc_attr($direction) . '" style="font-size:0;text-align:' . esc_attr($align) . ';">';

        $chunks = array_chunk($tiles, 2);
        foreach ($chunks as $row) {
            $html .= '<!--[if mso]><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="' . self::TABLE_RESET . 'width:100%;"><tr><![endif]-->';

            foreach ($row as $position => $tile) {
                $tone = isset($palette[$tile['tone']]) ? $palette[$tile['tone']] : $palette['accent'];

                $html .= '<!--[if mso]><td width="50%" valign="top" style="width:50%;padding:0 5px 10px 5px;"><![endif]-->';

                // width:100% with a max-width is what produces the stacking:
                // below ~2x the max-width the second tile no longer fits beside
                // the first and wraps to its own line.
                $html .= '<div style="display:inline-block;width:100%;max-width:262px;vertical-align:top;padding:0 4px 10px 4px;box-sizing:border-box;">'
                    . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="' . self::TABLE_RESET . 'width:100%;background:' . esc_attr($tone['bg']) . ';border:1px solid ' . esc_attr($tone['border']) . ';border-radius:10px;">'
                    . '<tr><td align="' . esc_attr($align) . '" style="padding:14px 15px;text-align:' . esc_attr($align) . ';">'
                    . '<p style="margin:0 0 2px 0;color:' . esc_attr($tone['value']) . ';font-size:25px;line-height:31px;font-weight:700;text-align:' . esc_attr($align) . ';">' . esc_html(number_format_i18n((int) $tile['value'])) . '</p>'
                    . '<p style="margin:0;color:' . esc_attr($tone['label']) . ';font-size:12px;line-height:18px;text-align:' . esc_attr($align) . ';">' . esc_html($tile['label']) . '</p>'
                    . '</td></tr></table>'
                    . '</div>';

                $html .= '<!--[if mso]></td><![endif]-->';
            }

            if (1 === count($row)) {
                $html .= '<!--[if mso]><td width="50%" style="width:50%;">&nbsp;</td><![endif]-->';
            }

            $html .= '<!--[if mso]></tr></table><![endif]-->';
        }

        return $html . '</div>';
    }

    /**
     * Form counts as one readable sentence rather than three table rows.
     *
     * @param array  $activity
     * @param string $direction
     * @param string $align
     * @return string
     */
    private static function build_forms_summary_line($activity, $direction, $align) {
        $sentence = sprintf(
            /* translators: 1: total forms, 2: active forms, 3: inactive forms. */
            esc_html__('You have %1$s forms: %2$s active and %3$s inactive.', 'easy-form-builder'),
            '<span style="color:#111827;font-weight:700;">' . esc_html(number_format_i18n((int) $activity['forms_total'])) . '</span>',
            '<span style="color:#0f9d58;font-weight:700;">' . esc_html(number_format_i18n((int) $activity['forms_active'])) . '</span>',
            '<span style="color:#9aa1af;font-weight:700;">' . esc_html(number_format_i18n((int) $activity['forms_inactive'])) . '</span>'
        );

        return '<table role="presentation" dir="' . esc_attr($direction) . '" cellspacing="0" cellpadding="0" border="0" width="100%" style="' . self::TABLE_RESET . 'width:100%;margin:6px 0 0 0;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;">'
            . '<tr><td align="' . esc_attr($align) . '" style="padding:12px 15px;color:#4b5563;font-size:13px;line-height:20px;text-align:' . esc_attr($align) . ';">'
            . wp_kses($sentence, ['span' => ['style' => []]])
            . '</td></tr></table>';
    }

    private static function build_report_table($rows, $direction, $align, $label_padding) {
        if (empty($rows)) {
            return '';
        }

        $html = '<table role="presentation" dir="' . esc_attr($direction) . '" cellspacing="0" cellpadding="0" border="0" width="100%" style="width:100%;margin:12px 0 0 0;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;">';
        foreach ($rows as $label => $value) {
            $html .= '<tr>'
                . '<td width="70%" align="' . esc_attr($align) . '" style="width:70%;padding:9px ' . esc_attr($label_padding) . ' 9px 12px;border-bottom:1px solid #e5e7eb;color:#4b5563;font-size:13px;line-height:20px;text-align:' . esc_attr($align) . ';">' . esc_html($label) . '</td>'
                . '<td width="30%" dir="auto" align="' . esc_attr($align) . '" style="width:30%;padding:9px 12px;border-bottom:1px solid #e5e7eb;color:#111827;font-size:14px;line-height:20px;font-weight:700;text-align:' . esc_attr($align) . ';white-space:nowrap;">' . esc_html($value) . '</td>'
                . '</tr>';
        }

        return $html . '</table>';
    }

    /**
     * A heading kept outside of the data tables so it remains readable in
     * Outlook versions that ignore table-cell margins.
     */
    private static function build_section_heading($title, $align, $top_margin) {
        return '<p style="margin:' . (int) $top_margin . 'px 0 8px 0;font-size:16px;line-height:24px;font-weight:700;color:#111827;text-align:' . esc_attr($align) . ';">' . esc_html($title) . '</p>';
    }

    /**
     * Purchase invitation shown only to Free, Free Plus and expired plans.
     */
    private static function build_pro_upgrade_callout($direction, $align) {
        $package_type = (int) get_option('emsfb_pro', 2);
        $locale = get_locale();
        $brand = defined('EMSFB_SERVER_URL') ? untrailingslashit(EMSFB_SERVER_URL) : 'https://whitestudio.team';
        if ($locale === 'fa_IR') {
            $brand = 'https://easyformbuilder.ir';
        } elseif (strpos($locale, 'ar') === 0) {
            $brand = 'https://ar.whitestudio.team';
        }

        $upgrade_url = $brand . '/register-costumer';
        $active_code = trim((string) get_option('emsfb_pro_activeCode', ''));
        $is_expired = $package_type === 0 && $active_code !== '';
        if ($is_expired) {
            $upgrade_url = add_query_arg('renew', $active_code, $upgrade_url);
        }

        $title = $is_expired
            ? __('Renew Easy Form Builder Pro', 'easy-form-builder')
            : __('Get more from Easy Form Builder Pro', 'easy-form-builder');
        $description = $is_expired
            ? __('Renew your subscription to restore all Pro features and add-ons.', 'easy-form-builder')
            : __('Unlock premium add-ons, unlimited features and priority support for your forms.', 'easy-form-builder');
        $button_label = $is_expired
            ? __('Renew Pro', 'easy-form-builder')
            : __('Upgrade to Pro', 'easy-form-builder');

        return '<table role="presentation" dir="' . esc_attr($direction) . '" cellspacing="0" cellpadding="0" border="0" width="100%" style="mso-table-lspace:0pt;mso-table-rspace:0pt;width:100%;margin:24px 0 0 0;border-collapse:separate;background-color:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;">'
            . '<tr><td align="' . esc_attr($align) . '" style="padding:18px 16px;text-align:' . esc_attr($align) . ';">'
            . '<p style="margin:0 0 5px 0;color:#1e1b4b;font-size:16px;line-height:23px;font-weight:700;text-align:' . esc_attr($align) . ';">' . esc_html($title) . '</p>'
            . '<p style="margin:0 0 14px 0;color:#3730a3;font-size:13px;line-height:20px;text-align:' . esc_attr($align) . ';">' . esc_html($description) . '</p>'
            . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="' . esc_attr($align) . '" style="border-collapse:separate;"><tr><td align="center" bgcolor="#202a8d" style="border-radius:6px;background-color:#202a8d;">'
            . '<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . esc_url($upgrade_url) . '" style="height:44px;v-text-anchor:middle;width:170px;" arcsize="14%" strokecolor="#202a8d" fillcolor="#202a8d"><w:anchorlock/><center style="color:#ffffff;font-family:Arial,sans-serif;font-size:14px;font-weight:700;">' . esc_html($button_label) . '</center></v:roundrect><![endif]-->'
            . '<!--[if !mso]><!--><a href="' . esc_url($upgrade_url) . '" target="_blank" style="display:inline-block;padding:13px 20px;color:#ffffff !important;font-family:Arial,sans-serif;font-size:14px;font-weight:700;line-height:18px;text-align:center;text-decoration:none;mso-hide:all;">' . esc_html($button_label) . '</a><!--<![endif]-->'
            . '</td></tr></table>'
            . '</td></tr></table>';
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
