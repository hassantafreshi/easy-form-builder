<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Real WordPress security/integration checks; restores diagnostic options. */
define('DOING_AJAX', true);
require_once dirname(__DIR__, 4) . '/wp-load.php';
require_once dirname(__DIR__) . '/includes/class-Emsfb-request-health.php';
use Emsfb\Request_Health;

class EfbHealthTestDie extends Exception {}
add_filter('wp_die_ajax_handler', static function () { return static function () { throw new EfbHealthTestDie(); }; });
$saved_server = $_SERVER;
$saved_request = $_REQUEST;
$saved_post = $_POST;
$saved_user = get_current_user_id();
$saved_option = get_option(Request_Health::OPTION, null);
$saved_lock = get_option(Request_Health::OPTION . '_lock', null);
$saved_global = array();
foreach (array('ajax','server') as $kind) {
    foreach (array(time()-60,time(),time()+60) as $time) {
        $key = 'efb_rh_global_' . $kind . '_' . gmdate('YmdHi',$time);
        $saved_global[$key] = get_transient($key);
    }
}
$passed = 0;
function health_check($ok, $label) {
    global $passed;
    if (!$ok) throw new Exception($label);
    $passed++;
}
function health_ajax($post) {
    $_POST = $_REQUEST = $post;
    ob_start();
    try { Request_Health::ajax(); } catch (EfbHealthTestDie $e) {}
    $body = ob_get_clean();
    return json_decode($body, true);
}
try {
    wp_set_current_user(0);
    $exclusions=apply_filters('rocket_delay_js_exclusions',array('existing.js'));
    health_check(in_array('existing.js',$exclusions,true) && in_array('request-health-efb.js',$exclusions,true),'optimizer exclusions preserve existing rules');
    health_check(strpos(apply_filters('autoptimize_filter_js_exclude','existing.js'),'request-health-efb.js')!==false,'Autoptimize exclusion hook registered');
    delete_option(Request_Health::OPTION);
    delete_option(Request_Health::OPTION . '_lock');
    $_SERVER['REMOTE_ADDR'] = '192.0.2.237';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_ORIGIN'] = home_url();
    $_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
    health_check(Request_Health::same_origin(), 'same origin accepted');
    $_SERVER['HTTP_ORIGIN'] = 'https://attacker.invalid';
    health_check(!Request_Health::same_origin(), 'foreign origin denied');
    health_check(health_ajax(array('phase'=>'token'))['success'] === false, 'cross-origin token denied');
    $_SERVER['HTTP_ORIGIN'] = home_url();
    $_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
    health_check(!Request_Health::same_origin(), 'cross-site browser denied');
    $_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
    $home = wp_parse_url(home_url());
    $_SERVER['HTTP_ORIGIN'] = $home['scheme'] . '://' . $home['host'] . ':9999';
    health_check(!Request_Health::same_origin(), 'different port denied');
    $_SERVER['HTTP_ORIGIN'] = home_url();
    $public = new \Emsfb\_Public();
    $_SERVER['HTTP_ORIGIN'] = 'https://attacker.invalid';
    $denied = $public->efb_nonce_refresh_api();
    health_check(is_wp_error($denied) && $denied->get_error_code() === 'efb_refresh_origin_denied', 'cross-origin form nonce refresh denied');
    $_SERVER['HTTP_ORIGIN'] = home_url();
    $fresh = $public->efb_nonce_refresh_api();
    health_check($fresh instanceof WP_REST_Response && wp_verify_nonce($fresh->get_data()['nonce'],'wp_rest') !== false, 'same-origin form nonce refresh works');
    $token_response = health_ajax(array('phase'=>'token'));
    $token = $token_response['data']['token'];
    health_check(wp_verify_nonce($token, Request_Health::ACTION) !== false, 'fresh reporting token');
    health_check(health_ajax(array('phase'=>'report','operation'=>'reply','status'=>403,'token'=>'invalid'))['success'] === false, 'invalid token denied');
    health_check(get_option(Request_Health::OPTION, array()) === array(), 'invalid report not stored');
    health_check(health_ajax(array('phase'=>'report','operation'=>'<script>','status'=>403,'token'=>$token))['success'] === false, 'operation injection denied');
    health_check(health_ajax(array('phase'=>'report','operation'=>'reply','status'=>200,'token'=>$token))['success'] === false, 'success is not a failure');
    $result = health_ajax(array('phase'=>'report','operation'=>'reply','status'=>403,'token'=>$token,'message'=>'PRIVATE','track'=>'SECRET'));
    health_check($result['success'] === true, 'valid report accepted');
    $rows = get_option(Request_Health::OPTION);
    health_check(isset($rows['browser:reply:403:unconfirmed']), 'browser report labelled unconfirmed');
    health_check(strpos(wp_json_encode($rows),'PRIVATE') === false && strpos(wp_json_encode($rows),'SECRET') === false, 'sensitive extras discarded');
    $request = new WP_REST_Request('POST', '/Emsfb/v1/forms/message/add');
    $response = new WP_REST_Response(array('code'=>'efb_nonce_expired'),403);
    health_check(Request_Health::observe($response,null,$request) === $response, 'server response preserved');
    $rows = get_option(Request_Health::OPTION);
    health_check(isset($rows['server:form:403:nonce']), 'server nonce cause recorded');
    $before = $rows;
    Request_Health::observe($response,null,new WP_REST_Request('POST','/wp/v2/users'));
    health_check(get_option(Request_Health::OPTION) === $before, 'other plugins/core routes untouched');
    ob_start(); Request_Health::notice(); $notice = ob_get_clean();
    health_check($notice === '', 'guest cannot read diagnostic details');
    $admins = get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID'));
    if ($admins) {
        $admin_id = (int) $admins[0];
        $saved_ack = get_user_option(Request_Health::ACK, $admin_id);
        delete_user_option($admin_id, Request_Health::ACK);
        wp_set_current_user($admin_id);
        $fresh = $public->efb_nonce_refresh_api();
        health_check($fresh instanceof WP_REST_Response && wp_verify_nonce($fresh->get_data()['nonce'],'wp_rest') !== false, 'logged-in refresh remains bound to logged-in identity');
        ob_start(); Request_Health::notice(); $notice = ob_get_clean();
        health_check($notice === '', 'browser reports and nonce refusals never raise the admin notice');
        Request_Health::record('prepare',429,'server','rate_limit');
        Request_Health::record('payment',409,'server','payment_record_mismatch');
        Request_Health::record('payment',400,'server','gateway_creation_failed');
        ob_start(); Request_Health::notice(); $notice = ob_get_clean();
        health_check($notice === '', 'rate limits and refusals a visitor can provoke stay diagnostics');
        health_check(isset(get_option(Request_Health::OPTION)['server:prepare:429:rate_limit']), 'diagnostics are still recorded');
        $row = static function ($source, $status, $reason) { return array('operation'=>'payment','status'=>$status,'source'=>$source,'reason'=>$reason,'last'=>time(),'count'=>1); };
        health_check(Request_Health::needs_attention($row('server',401,'gateway_creation_failed')) && Request_Health::needs_attention($row('server',503,'gateway_verification_unavailable')) && Request_Health::needs_attention($row('server',500,'payment_status_storage_failed')) && Request_Health::needs_attention($row('server',503,'ticket_token_storage_failed')) && Request_Health::needs_attention($row('server',500,'server_fatal')), 'server failures and gateway credential errors need attention');
        health_check(!Request_Health::needs_attention($row('browser',500,'unconfirmed')) && !Request_Health::needs_attention($row('server',402,'gateway_creation_failed')) && !Request_Health::needs_attention($row('server',409,'payment_not_verified')) && !Request_Health::needs_attention($row('server',403,'nonce')) && !Request_Health::needs_attention($row('server',403,'request_rejected')) && !Request_Health::needs_attention('not-a-row'), 'browser reports and refusals a visitor can provoke do not');
        $opt_in = static function ($attention, $candidate) { return $attention || $candidate['reason'] === 'payment_record_mismatch'; };
        add_filter('efb_request_health_needs_attention', $opt_in, 10, 2);
        health_check(Request_Health::needs_attention($row('server',409,'payment_record_mismatch')), 'sites can opt a row in through the filter');
        remove_filter('efb_request_health_needs_attention', $opt_in, 10);
        health_check(has_action('wp_ajax_' . Request_Health::DISMISS) !== false && has_action('wp_ajax_nopriv_' . Request_Health::DISMISS) === false, 'dismiss is registered for logged-in users only');
        Request_Health::record('form',500,'server','storage_failed');
        ob_start(); Request_Health::notice(); $notice = ob_get_clean();
        health_check(strpos($notice,'is-dismissible') !== false && strpos($notice,'page=Emsfb') !== false && strpos($notice,'unconfirmed') === false, 'server failure shows a short dismissible notice outside EFB screens');
        require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        WP_Screen::get('toplevel_page_Emsfb')->set_current_screen();
        ob_start(); Request_Health::notice(); $notice = ob_get_clean();
        health_check(strpos($notice,'unconfirmed') !== false && strpos($notice,'<strong>form | HTTP 500 | server | storage_failed') !== false, 'EFB screens show every row and mark the failure');
        $GLOBALS['current_screen'] = null;
        $newest = (int) get_option(Request_Health::OPTION)['server:form:500:storage_failed']['last'];
        $dismiss = static function ($post) {
            $_POST = $_REQUEST = $post;
            ob_start();
            try { Request_Health::dismiss(); } catch (EfbHealthTestDie $e) {}
            return json_decode(ob_get_clean(), true);
        };
        health_check($dismiss(array('nonce'=>'invalid','last'=>$newest))['success'] === false && !get_user_option(Request_Health::ACK, $admin_id), 'dismiss requires its nonce');
        wp_set_current_user(0);
        health_check($dismiss(array('nonce'=>wp_create_nonce(Request_Health::DISMISS),'last'=>$newest))['success'] === false, 'dismiss requires manage_options');
        wp_set_current_user($admin_id);
        health_check($dismiss(array('nonce'=>wp_create_nonce(Request_Health::DISMISS),'last'=>$newest + 999999))['success'] === true, 'administrator can dismiss');
        health_check((int) get_user_option(Request_Health::ACK, $admin_id) === $newest, 'acknowledgement never runs ahead of the failure shown');
        global $wpdb;
        health_check((int) get_user_meta($admin_id, $wpdb->get_blog_prefix() . Request_Health::ACK, true) === $newest, 'acknowledgement is stored per site');
        ob_start(); Request_Health::notice(); $notice = ob_get_clean();
        health_check($notice === '', 'dismissed notice stays hidden');
        $rows = get_option(Request_Health::OPTION);
        $rows['server:form:403:nonce']['last'] = $newest + 5;
        update_option(Request_Health::OPTION, $rows, false);
        ob_start(); Request_Health::notice(); $notice = ob_get_clean();
        health_check($notice === '', 'a newer diagnostic row does not bring it back');
        $rows['server:form:500:storage_failed']['last'] = $newest + 10;
        update_option(Request_Health::OPTION, $rows, false);
        ob_start(); Request_Health::notice(); $notice = ob_get_clean();
        health_check(strpos($notice,'data-last="' . ($newest + 10) . '"') !== false, 'a newer server failure shows it again');
        if ($saved_ack === false) delete_user_option($admin_id, Request_Health::ACK); else update_user_option($admin_id, Request_Health::ACK, $saved_ack);
        wp_set_current_user(0);
    }
    unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
    health_check(Request_Health::same_origin(), 'same-origin browser fetch works with no-referrer policy');
    unset($_SERVER['HTTP_SEC_FETCH_SITE']);
    health_check(!Request_Health::same_origin(), 'missing browser origin evidence denied');
    $_SERVER['HTTP_ORIGIN'] = home_url();
    $_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
    for ($i=0;$i<40;$i++) Request_Health::record('form',500+$i,'server','request_rejected');
    health_check(count(get_option(Request_Health::OPTION)) === 32, 'bounded storage');
    // Report throttling affects telemetry only and does not alter form auth.
    for ($i=0;$i<25;$i++) $last = health_ajax(array('phase'=>'token'));
    health_check($last['success'] === false, 'report requests rate limited');
    echo "PASS: {$passed} WordPress integration/security checks\n";
} finally {
    if ($saved_option === null) delete_option(Request_Health::OPTION); else update_option(Request_Health::OPTION,$saved_option,false);
    if ($saved_lock === null) delete_option(Request_Health::OPTION . '_lock'); else update_option(Request_Health::OPTION . '_lock',$saved_lock,false);
    // The fixed documentation-only test IP uses isolated per-IP counters.
    foreach (array('ajax','server') as $kind) {
        foreach (array(time()-60,time(),time()+60) as $time) delete_transient('efb_rh_' . md5($kind . '|192.0.2.237|' . gmdate('YmdHi',$time)));
    }
    delete_transient('efb_nrl_' . md5('192.0.2.237|' . gmdate('YmdHi')));
    foreach ($saved_global as $key => $value) {
        if ($value === false) delete_transient($key); else set_transient($key,$value,120);
    }
    $_SERVER = $saved_server; $_REQUEST = $saved_request; $_POST = $saved_post;
    wp_set_current_user($saved_user);
}
