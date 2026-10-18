<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** In-process REST checks for the prepare/status quotas. Removes its session rows and counters. */
require_once dirname(__DIR__, 4) . '/wp-load.php';
global $wpdb;
$passed = 0;
$ip = '192.0.2.238';
function prepare_check($ok, $label) { global $passed; if (!$ok) throw new Exception($label); $passed++; }
function prepare_call($method, $route, $body = null) {
    $request = new WP_REST_Request($method, '/Emsfb/v1/' . $route);
    if ($body !== null) { $request->set_header('Content-Type', 'application/json'); $request->set_body(wp_json_encode($body)); }
    $response = rest_do_request($request);
    return array($response->get_status(), $response->get_data());
}
function prepare_clear_counters($ip) {
    foreach (array(time() - 60, time(), time() + 60) as $t) {
        foreach (array('efb_nrl_', 'efb_spl_') as $prefix) delete_transient($prefix . md5($ip . '|' . gmdate('YmdHi', $t)));
    }
}
$saved_server = $_SERVER;
$saved_user = get_current_user_id();
$limit = static function () { return 5; };
try {
    if ((int) gmdate('s') > 45) sleep(61 - (int) gmdate('s')); // Keep every count inside one window.
    rest_get_server();
    wp_set_current_user(0);
    $_SERVER['REMOTE_ADDR'] = $ip;
    unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    $_SERVER['HTTP_ORIGIN'] = home_url();
    $_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
    prepare_clear_counters($ip);
    add_filter('efb_session_prepare_per_minute', $limit);

    for ($i = 0; $i < 30; $i++) { [$code] = prepare_call('GET', 'nonce/refresh'); prepare_check($code === 200, 'refresh ' . $i . ' allowed'); }
    [$code] = prepare_call('GET', 'nonce/refresh');
    prepare_check($code === 429, 'nonce/refresh keeps its 30/min limit');

    $contexts = array();
    for ($i = 0; $i < 5; $i++) {
        [$code, $context] = prepare_call('POST', 'forms/session/prepare', array('form_id' => 0));
        prepare_check($code === 200 && !empty($context['runtime_token']), 'prepare ' . $i . ' does not spend the refresh quota');
        $contexts[] = $context;
    }
    [$code, $denied] = prepare_call('POST', 'forms/session/prepare', array('form_id' => 0));
    prepare_check($code === 429 && $denied['code'] === 'rest_too_many_requests', 'new sessions are bounded by efb_session_prepare_per_minute');

    [$code, $renewed] = prepare_call('POST', 'forms/session/prepare', array('form_id' => 0, 'runtime_token' => $contexts[0]['runtime_token']));
    prepare_check($code === 200 && $renewed['sid'] === $contexts[0]['sid'], 'refreshing a verified context is never counted');
    [$code, $status] = prepare_call('POST', 'forms/operation/status', array('form_id' => 0, 'kind' => 'form', 'request_id' => wp_generate_uuid4(), 'runtime_token' => $contexts[0]['runtime_token']));
    prepare_check($code === 200 && $status['state'] === 'not_found', 'status recovery uses no quota');

    $_SERVER['HTTP_ORIGIN'] = 'https://attacker.invalid';
    [$code, $foreign] = prepare_call('POST', 'forms/session/prepare', array('form_id' => 0));
    prepare_check($code === 403 && $foreign['code'] === 'efb_refresh_origin_denied', 'cross-origin prepare still denied');
    $_SERVER['HTTP_ORIGIN'] = home_url();

    remove_filter('efb_session_prepare_per_minute', $limit);
    add_filter('efb_session_prepare_per_minute', '__return_zero');
    [$code] = prepare_call('POST', 'forms/session/prepare', array('form_id' => 0));
    prepare_check($code === 200, 'a limit of 0 disables the session quota');
    remove_filter('efb_session_prepare_per_minute', '__return_zero');

    $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
    if ($admins) {
        add_filter('efb_session_prepare_per_minute', $limit);
        wp_set_current_user((int) $admins[0]);
        [$code] = prepare_call('POST', 'forms/session/prepare', array('form_id' => 0));
        prepare_check($code === 200, 'logged-in users are not throttled');
        wp_set_current_user(0);
    }
    echo "PASS: {$passed} prepare/status quota checks\n";
} finally {
    remove_filter('efb_session_prepare_per_minute', $limit);
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}emsfb_stts_ WHERE ip = %s", $ip));
    prepare_clear_counters($ip);
    $_SERVER = $saved_server;
    wp_set_current_user($saved_user);
}
