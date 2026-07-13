<?php
namespace Emsfb;

defined('ABSPATH') || exit;

/**
 * Dashboard Widget for Easy Form Builder
 * Shows visit count, form submissions, email success/failure stats with charts.
 * Uses WordPress native dashboard widget API.
 */
class Dashboard_Widget {

    public function __construct() {
        add_action('wp_dashboard_setup', [$this, 'register_widget']);
        add_action('wp_dashboard_setup', [$this, 'force_widget_top'], 999);
        add_action('wp_ajax_efb_dashboard_stats', [$this, 'ajax_get_stats']);
        add_action('wp_ajax_efb_dashboard_email_errors', [$this, 'ajax_get_email_errors']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Move widget to the top of the dashboard.
     */
    public function force_widget_top() {
        global $wp_meta_boxes;
        if (empty($wp_meta_boxes['dashboard']['normal']['core']['efb_stats_dashboard_widget'])) {
            return;
        }
        $widget = $wp_meta_boxes['dashboard']['normal']['core']['efb_stats_dashboard_widget'];
        unset($wp_meta_boxes['dashboard']['normal']['core']['efb_stats_dashboard_widget']);
        $wp_meta_boxes['dashboard']['normal']['core'] = array_merge(
            ['efb_stats_dashboard_widget' => $widget],
            $wp_meta_boxes['dashboard']['normal']['core']
        );
    }

    /**
     * Enqueue widget assets only on the main dashboard page.
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'index.php') { return; }
        if (!current_user_can('manage_options')) { return; }

        wp_enqueue_style(
            'efb-dashboard-widget',
            EMSFB_PLUGIN_URL . 'includes/admin/assets/css/dashboard-widget-efb.css',
            [],
            EMSFB_PLUGIN_VERSION
        );
        wp_enqueue_script(
            'efb-dashboard-widget',
            EMSFB_PLUGIN_URL . 'includes/admin/assets/js/dashboard-widget-efb.js',
            ['jquery'],
            EMSFB_PLUGIN_VERSION,
            true
        );

        $efbFunction = get_efbFunction();
        $text_keys = [
            'easyFormBuilder', 'email', 'error', 'page',
            'dayly', 'weekly', 'monthly',
            'close', 'subject', 'loading', 'ddate', 'total',
            'dwVisits', 'dwSubmissions', 'dwEmailsSent', 'dwEmailsFailed',
            'dwEmailErrors', 'dwRecipient', 'dwErrorDetail', 'dwNoData',
        ];
        $lang = $efbFunction->text_efb($text_keys);

        wp_localize_script('efb-dashboard-widget', 'efb_dw', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('efb_dashboard_stats'),
            'text'     => $lang,
            'rtl'      => is_rtl() ? 1 : 0,
        ]);
    }

    /**
     * Register the dashboard widget.
     */
    public function register_widget() {
        if (!current_user_can('manage_options')) { return; }

        wp_add_dashboard_widget(
            'efb_stats_dashboard_widget',
            '<span class="efb-dw-title"><img src="' . esc_url(EMSFB_PLUGIN_URL . 'includes/admin/assets/image/logo-gray.png') . '" alt="" style="width:18px;height:18px;vertical-align:middle;margin-inline-end:6px;">' . esc_html__('Easy Form Builder', 'easy-form-builder') . '</span>',
            [$this, 'render_widget']
        );
    }

    /**
     * Render the widget HTML shell — data is loaded via AJAX.
     */
    public function render_widget() {
        ?>
        <div id="efb-dw-root" class="efb-dw">
            <div class="efb-dw-period-tabs">
                <button class="efb-dw-tab" data-period="day"></button>
                <button class="efb-dw-tab active" data-period="week"></button>
                <button class="efb-dw-tab" data-period="month"></button>
            </div>
            <div class="efb-dw-cards">
                <div class="efb-dw-card efb-dw-card--visits">
                    <div class="efb-dw-card-top">
                        <div class="efb-dw-card-icon"><span class="dashicons dashicons-visibility"></span></div>
                        <span class="efb-dw-card-value" id="efb-dw-visits">—</span>
                    </div>
                    <span class="efb-dw-card-label" id="efb-dw-visits-label"></span>
                </div>
                <div class="efb-dw-card efb-dw-card--submissions">
                    <div class="efb-dw-card-top">
                        <div class="efb-dw-card-icon"><span class="dashicons dashicons-forms"></span></div>
                        <span class="efb-dw-card-value" id="efb-dw-submissions">—</span>
                    </div>
                    <span class="efb-dw-card-label" id="efb-dw-submissions-label"></span>
                </div>
                <div class="efb-dw-card efb-dw-card--email-ok">
                    <div class="efb-dw-card-top">
                        <div class="efb-dw-card-icon"><span class="dashicons dashicons-email"></span></div>
                        <span class="efb-dw-card-value" id="efb-dw-email-ok">—</span>
                    </div>
                    <span class="efb-dw-card-label" id="efb-dw-email-ok-label"></span>
                </div>
                <div class="efb-dw-card efb-dw-card--email-fail" id="efb-dw-email-fail-card" role="button" tabindex="0" title="">
                    <div class="efb-dw-card-top">
                        <div class="efb-dw-card-icon"><span class="dashicons dashicons-warning"></span></div>
                        <span class="efb-dw-card-value" id="efb-dw-email-fail">—</span>
                    </div>
                    <span class="efb-dw-card-label" id="efb-dw-email-fail-label"></span>
                </div>
            </div>
            <div class="efb-dw-chart-wrap">
                <canvas id="efb-dw-chart" height="200"></canvas>
            </div>
            <div id="efb-dw-email-errors-panel" class="efb-dw-errors-panel" style="display:none;">
                <div class="efb-dw-errors-header">
                    <strong><span class="dashicons dashicons-warning"></span> <span id="efb-dw-errors-title"></span></strong>
                    <button type="button" id="efb-dw-errors-close" class="efb-dw-errors-close">&times;</button>
                </div>
                <div id="efb-dw-errors-list" class="efb-dw-errors-list"></div>
            </div>
            <div id="efb-dw-loading" class="efb-dw-loading"><span class="spinner is-active"></span></div>
        </div>
        <?php
    }

    /**
     * AJAX: Return aggregated stats for the selected period.
     */
    public function ajax_get_stats() {
        check_ajax_referer('efb_dashboard_stats', 'nonce');
        if (!current_user_can('manage_options')) { wp_send_json_error([], 403); }

        $period = isset($_POST['period']) ? sanitize_text_field(wp_unslash($_POST['period'])) : 'week';
        if (!in_array($period, ['day', 'week', 'month'], true)) { $period = 'week'; }

        global $wpdb;
        $table = $wpdb->prefix . 'emsfb_stts_';

        // Determine date boundaries
        $now = wp_date('Y-m-d H:i:s');
        switch ($period) {
            case 'day':
                $since  = wp_date('Y-m-d 00:00:00');
                $points = 24;
                $group  = 'HOUR(`date`)';
                $date_format = '%H:00';
                break;
            case 'week':
                $since  = wp_date('Y-m-d 00:00:00', strtotime('-6 days'));
                $points = 7;
                $group  = 'DATE(`date`)';
                $date_format = '%m-%d';
                break;
            case 'month':
                $since  = wp_date('Y-m-d 00:00:00', strtotime('-29 days'));
                $points = 30;
                $group  = 'DATE(`date`)';
                $date_format = '%m-%d';
                break;
        }

        // Total visits (status = 'visit')
        $visits = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE `status` = 'visit' AND `date` >= %s",
            $since
        ));

        // Total form submissions (send, poll, regis, login, logou, recov, pay etc — exclude visit, inact, admin)
        $submissions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE `status` NOT IN ('visit','inact','admin') AND `date` >= %s",
            $since
        ));

        // Get chart data — visits per time bucket
        $visit_chart = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(`date`, %s) AS label, COUNT(*) AS cnt
             FROM `{$table}`
             WHERE `status` = 'visit' AND `date` >= %s
             GROUP BY label ORDER BY MIN(`date`) ASC",
            $date_format, $since
        ), ARRAY_A);

        // Get chart data — submissions per time bucket
        $send_chart = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(`date`, %s) AS label, COUNT(*) AS cnt
             FROM `{$table}`
             WHERE `status` NOT IN ('visit','inact','admin') AND `date` >= %s
             GROUP BY label ORDER BY MIN(`date`) ASC",
            $date_format, $since
        ), ARRAY_A);

        // Map chart data to arrays
        $visit_map = [];
        foreach ($visit_chart as $row) { $visit_map[$row['label']] = (int) $row['cnt']; }
        $send_map = [];
        foreach ($send_chart as $row) { $send_map[$row['label']] = (int) $row['cnt']; }

        // Build labels array
        $labels = [];
        $visit_data = [];
        $send_data = [];

        switch ($period) {
            case 'day':
                for ($h = 0; $h < 24; $h++) {
                    $lbl = sprintf('%02d:00', $h);
                    $labels[] = $lbl;
                    $visit_data[] = $visit_map[$lbl] ?? 0;
                    $send_data[]  = $send_map[$lbl] ?? 0;
                }
                break;
            case 'week':
                for ($d = 6; $d >= 0; $d--) {
                    $lbl = wp_date('m-d', strtotime("-{$d} days"));
                    $labels[] = $lbl;
                    $visit_data[] = $visit_map[$lbl] ?? 0;
                    $send_data[]  = $send_map[$lbl] ?? 0;
                }
                break;
            case 'month':
                for ($d = 29; $d >= 0; $d--) {
                    $lbl = wp_date('m-d', strtotime("-{$d} days"));
                    $labels[] = $lbl;
                    $visit_data[] = $visit_map[$lbl] ?? 0;
                    $send_data[]  = $send_map[$lbl] ?? 0;
                }
                break;
        }

        // Email stats from our log
        require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-email-handler.php';
        $email_stats = \EmsfbEmailHandler::get_email_stats($period);

        wp_send_json_success([
            'visits'      => $visits,
            'submissions' => $submissions,
            'email_ok'    => $email_stats['success'],
            'email_fail'  => $email_stats['failed'],
            'chart'       => [
                'labels'      => $labels,
                'visits'      => $visit_data,
                'submissions' => $send_data,
            ],
        ]);
    }

    /**
     * AJAX: Return failed email details.
     */
    public function ajax_get_email_errors() {
        check_ajax_referer('efb_dashboard_stats', 'nonce');
        if (!current_user_can('manage_options')) { wp_send_json_error([], 403); }

        $period = isset($_POST['period']) ? sanitize_text_field(wp_unslash($_POST['period'])) : 'week';
        if (!in_array($period, ['day', 'week', 'month'], true)) { $period = 'week'; }

        require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-email-handler.php';
        $email_stats = \EmsfbEmailHandler::get_email_stats($period);

        // Sanitize output
        $errors = [];
        foreach ($email_stats['failed_logs'] as $log) {
            $errors[] = [
                'to'      => sanitize_email($log['to']),
                'subject' => esc_html($log['subject']),
                'error'   => esc_html($log['error']),
                'date'    => esc_html($log['date']),
            ];
        }

        wp_send_json_success(['errors' => $errors]);
    }
}
