<?php
namespace Emsfb;

defined('ABSPATH') || exit;

/** Bounded diagnostics. Browser observations are never proof of a server failure. */
class Request_Health {
    private static $active = '';
    private static $saved = false;
    private static $reserve = '';
    const OPTION = 'emsfb_request_health_v1';
    const ACTION = 'efb_request_health';
    const ROUTES = array(
        '/Emsfb/v1/forms/message/add' => 'form',
        '/Emsfb/v1/forms/response/get' => 'tracker',
        '/Emsfb/v1/forms/response/add' => 'reply',
        '/Emsfb/v1/forms/file/upload' => 'upload',
        '/Emsfb/v1/nonce/refresh' => 'refresh',
        '/Emsfb/v1/forms/session/prepare' => 'prepare',
        '/Emsfb/v1/forms/operation/status' => 'status',
        '/Emsfb/v1/forms/response/session' => 'ticket_session',
        '/EmsfbShield/v1/challenge' => 'shield',
        '/EmsfbShield/v1/attest' => 'shield',
        '/Emsfb/v1/forms/payment/stripe/card/add' => 'payment',
        '/Emsfb/v1/forms/payment/stripe/confirm' => 'payment',
        '/Emsfb/v1/forms/payment/persia/add' => 'payment',
    );

    public static function register() {
        foreach (array('rocket_delay_js_exclusions','rocket_exclude_defer_js','rocket_exclude_js','litespeed_optimize_js_excludes','litespeed_optm_js_defer_exc','litespeed_optm_gm_js_exc') as $filter) {
            add_filter($filter,array(__CLASS__,'exclude_scripts'));
        }
        add_filter('autoptimize_filter_js_exclude',static function ($list) {
            return implode(',',array_unique(array_merge(explode(',',(string)$list),self::script_patterns())));
        });
        add_filter('breeze_filter_js_exclude',static function ($list) {
            return implode(',',array_unique(array_merge(explode(',',(string)$list),self::script_patterns())));
        });
        add_filter('rest_pre_dispatch', array(__CLASS__,'track_request'), 1, 3);
        register_shutdown_function(array(__CLASS__,'fatal'));
        foreach (array('set_replyMessage_id_Emsfb'=>'admin_reply','get_all_response_id_Emsfb'=>'admin_tracker') as $action=>$operation) {
            add_action('wp_ajax_'.$action,static function () use ($operation) { self::$active=$operation; self::$saved=false; self::$reserve=str_repeat('x',65536); },0);
        }
        add_action('wp_ajax_' . self::ACTION, array(__CLASS__, 'ajax'));
        add_action('wp_ajax_nopriv_' . self::ACTION, array(__CLASS__, 'ajax'));
        add_filter('rest_post_dispatch', array(__CLASS__, 'observe'), 20, 3);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 100);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'), 100);
        add_action('wp_footer', array(__CLASS__, 'enqueue'), 1);
        add_action('admin_print_footer_scripts', array(__CLASS__, 'enqueue'), 1);
        add_action('check_ajax_referer', array(__CLASS__, 'observe_ajax_nonce'), 10, 2);
        add_action('admin_notices', array(__CLASS__, 'notice'));
    }

    private static function script_patterns() {
        $patterns=array('request-health-efb.js','core-efb.js','new-efb.js','jquery.min-efb.js','human-shield-public-efb.js','steps-progress-runtime-efb.js','response-viewer-efb.js','recorder-efb.js','stripe_pay-efb.js','js.stripe.com','bootstrap.bundle.min-efb.js','bootstrap.min-efb.js','efb_request_health','var efb_var','var ajax_object_efm','var EFBHumanShield');
        if (wp_script_is('efb-main-js','enqueued') || wp_script_is('efb-main-js','done')) $patterns[]='/wp-includes/js/jquery/jquery';
        return $patterns;
    }

    public static function exclude_scripts($list) {
        return array_values(array_unique(array_merge((array)$list,self::script_patterns())));
    }

    public static function track_request($result,$server,$request) {
        if (isset(self::ROUTES[$request->get_route()])) {
            self::$active=self::ROUTES[$request->get_route()]; self::$saved=false; self::$reserve=str_repeat('x',65536);
        }
        return $result;
    }

    public static function mark_saved() { self::$saved=true; }

    public static function fatal() {
        $error=error_get_last(); self::$reserve='';
        if (!self::$active || !$error || !in_array($error['type'],array(E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR,E_RECOVERABLE_ERROR),true)) return;
        self::record(self::$active,500,'server',self::$saved ? 'saved_then_error' : 'server_fatal');
    }

    public static function enqueue() {
        if (!wp_script_is('efb-main-js', 'enqueued') && !wp_script_is('Emsfb-core-js', 'enqueued')) return;
        if (wp_script_is('efb-request-health', 'enqueued')) return;
        // Public forms can enqueue their assets during shortcode rendering, after this hook.
        wp_enqueue_script('efb-request-health', EMSFB_PLUGIN_URL . 'public/assets/js/request-health-efb.js', array(), EMSFB_PLUGIN_VERSION, true);
        wp_localize_script('efb-request-health', 'efb_request_health', array('url' => admin_url('admin-ajax.php'), 'rest_url'=>rest_url('Emsfb/v1/'),'unknown_message'=>__('We could not confirm whether your request was saved. Your input has been kept. Avoid sending it again with different details until its status is checked.', 'easy-form-builder'),'payment_pending_message'=>__('Your card may have been charged, but the payment status could not be confirmed. Do not pay again. Keep this page open and contact the site administrator.', 'easy-form-builder')));
    }

    private static function origin($url) {
        $p = wp_parse_url($url);
        if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) return '';
        $scheme = strtolower($p['scheme']);
        $port = isset($p['port']) ? (int) $p['port'] : ($scheme === 'https' ? 443 : 80);
        return $scheme . '://' . strtolower($p['host']) . ':' . $port;
    }

    public static function same_origin() {
        if (isset($_SERVER['HTTP_SEC_FETCH_SITE']) && $_SERVER['HTTP_SEC_FETCH_SITE'] === 'cross-site') return false;
        $url = isset($_SERVER['HTTP_ORIGIN']) ? wp_unslash($_SERVER['HTTP_ORIGIN']) : (isset($_SERVER['HTTP_REFERER']) ? wp_unslash($_SERVER['HTTP_REFERER']) : '');
        if (!is_string($url)) return false;
        // A no-referrer policy can suppress Referer on same-origin GET refreshes.
        // Sec-Fetch-Site is browser-controlled; an explicit conflicting Origin still wins.
        if ($url === '') return ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'same-origin';
        $origin = self::origin($url);
        return $origin !== '' && in_array($origin, array(self::origin(home_url()), self::origin(site_url())), true);
    }

    public static function ajax() {
        nocache_headers();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !self::same_origin()) wp_send_json_error(array(), 403);
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) wp_send_json_error(array(), 413);
        if (!self::allow('ajax', 20)) wp_send_json_error(array(), 429);
        $phase = isset($_POST['phase']) && is_string($_POST['phase']) ? wp_unslash($_POST['phase']) : '';
        if ($phase === 'token') {
            wp_send_json_success(array('token' => wp_create_nonce(self::ACTION)));
        }
        if ($phase !== 'report') wp_send_json_error(array(), 400);
        // Fresh AJAX token, not the nonce embedded in cached form markup.
        if (!isset($_POST['token']) || !is_string($_POST['token']) || strlen($_POST['token']) > 128 || !check_ajax_referer(self::ACTION, 'token', false)) wp_send_json_error(array(), 403);
        $operation = isset($_POST['operation']) && is_string($_POST['operation']) ? wp_unslash($_POST['operation']) : '';
        $status = isset($_POST['status']) && is_scalar($_POST['status']) ? (int) $_POST['status'] : -1;
        $operations = array_merge(array_values(self::ROUTES), array('admin_reply', 'admin_tracker'));
        if (!in_array($operation, $operations, true) || ($status !== 0 && ($status < 400 || $status > 599))) wp_send_json_error(array(), 400);
        self::record($operation, $status, 'browser', 'unconfirmed');
        wp_send_json_success(array());
    }

    private static function allow($kind, $limit) {
        // Deliberately ignore spoofable forwarded headers. This limits reports, never submissions.
        $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $key = 'efb_rh_' . md5($kind . '|' . $ip . '|' . gmdate('YmdHi'));
        $count = (int) get_transient($key);
        $global_key = 'efb_rh_global_' . $kind . '_' . gmdate('YmdHi');
        $global_count = (int) get_transient($global_key);
        if ($count >= $limit || $global_count >= 120) return false;
        set_transient($key, $count + 1, 120);
        set_transient($global_key, $global_count + 1, 120);
        return true;
    }

    public static function observe($response, $server, $request) {
        $route = $request->get_route();
        if (!isset(self::ROUTES[$route])) return $response;
        $status = $response->get_status();
        if ($status < 400 || !self::allow('server', 12)) return $response;
        $data = $response->get_data();
        $code = is_array($data) && isset($data['code']) && is_string($data['code']) ? $data['code'] : '';
        $reason = in_array($code, array('rest_cookie_invalid_nonce', 'efb_nonce_expired', 'efb_nonce_missing'), true) ? 'nonce' : ($status === 429 ? 'rate_limit' : 'request_rejected');
        self::record(self::ROUTES[$route], $status, 'server', $reason);
        return $response;
    }

    public static function observe_ajax_nonce($action, $result) {
        if ($result !== false || $action !== 'wp_rest') return;
        $ajax = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? $_REQUEST['action'] : '';
        $operations = array('set_replyMessage_id_Emsfb' => 'admin_reply', 'get_all_response_id_Emsfb' => 'admin_tracker');
        if (isset($operations[$ajax]) && self::allow('server', 12)) self::record($operations[$ajax], 403, 'server', 'nonce');
    }

    public static function record($operation, $status, $source, $reason) {
        try { self::persist($operation,$status,$source,$reason); } catch (\Throwable $error) {}
    }

    private static function persist($operation, $status, $source, $reason) {
        // Best effort: diagnostics must never block or break a form operation.
        $lock = self::OPTION . '_lock';
        $now = time();
        $owner = $now . ':' . wp_generate_uuid4();
        global $wpdb;
        $acquired=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,%s)",$lock,$owner,'off'));
        wp_cache_delete($lock,'options'); wp_cache_delete('notoptions','options');
        if ($acquired!==1) {
            $old_owner = get_option($lock);
            if ((int) $old_owner < $now - 30) self::unlock($old_owner);
            return;
        }
        try {
            $rows = get_option(self::OPTION, array());
            if (!is_array($rows)) $rows = array();
            $rows = array_filter($rows, static function ($row) use ($now) { return is_array($row) && ($row['last'] ?? 0) > $now - 7 * DAY_IN_SECONDS; });
            $key = $source . ':' . $operation . ':' . $status . ':' . $reason;
            $old = isset($rows[$key]) ? $rows[$key] : array();
            $rows[$key] = array('operation' => $operation, 'status' => (int) $status, 'source' => $source, 'reason' => $reason, 'first' => $old['first'] ?? $now, 'last' => $now, 'count' => min(99999, ($old['count'] ?? 0) + 1));
            uasort($rows, static function ($a, $b) { return $b['last'] <=> $a['last']; });
            update_option(self::OPTION, array_slice($rows, 0, 32, true), false);
        } catch (\Throwable $error) {
            // Logging must not replace the operation's actual response.
        } finally {
            self::unlock($owner);
        }
    }

    private static function unlock($owner) {
        global $wpdb;
        $lock = self::OPTION . '_lock';
        // A delayed writer must never release a newer writer's lock.
        $wpdb->delete($wpdb->options, array('option_name' => $lock, 'option_value' => (string) $owner), array('%s', '%s'));
        wp_cache_delete($lock, 'options');
    }

    public static function notice() {
        if (!current_user_can('manage_options')) return;
        $rows = get_option(self::OPTION, array());
        if (!is_array($rows)) return;
        $rows = array_filter($rows, static function ($row) { return is_array($row) && ($row['last'] ?? 0) > time() - 7 * DAY_IN_SECONDS; });
        if (!$rows) return;
        echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Easy Form Builder: request failures detected', 'easy-form-builder') . '</strong></p><p>';
        echo esc_html__('A form, response box or upload request encountered an error. Some requests may have recovered automatically; counts are not lost submissions. Browser reports are unverified; a 403 alone does not prove a cache or firewall problem. Submission status may be unknown.', 'easy-form-builder');
        echo '</p><details><summary>' . esc_html__('View diagnostic details (last 7 days)', 'easy-form-builder') . '</summary><ul>';
        foreach ($rows as $row) {
            echo '<li>' . esc_html(sprintf('%s | HTTP %d | %s | %s | %d | %s', $row['operation'], $row['status'], $row['source'], $row['reason'], $row['count'], wp_date('Y-m-d H:i:s', $row['last']))) . '</li>';
        }
        echo '</ul><p>' . esc_html__('For nonce errors, check whether token refresh is reachable. For unconfirmed 403 responses, correlate the timestamp with security/CDN logs. Do not disable security protections based only on a browser report.', 'easy-form-builder') . '</p></details></div>';
    }
}
