<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * QA2-019: a visitor's page does not read the weekly e-mail report options (two queries on every page, they are not
 * autoloaded) and a plain form page does not load the response viewer's 43 KB stylesheet; the dashboard / WP-Cron keep
 * the schedule, the code finder and the login / register forms keep the stylesheet.
 *
 * Needs the e2e site (EFB_E2E_SITE_DIR or EFB_WP_LOAD); [SKIP] otherwise. Run: php tests/test-qa2-visitor-page-wp.php
 */
$wp_load = getenv('EFB_WP_LOAD') ?: (getenv('EFB_E2E_SITE_DIR') ? rtrim(getenv('EFB_E2E_SITE_DIR'), '/') . '/wp-load.php' : '');
if ($wp_load === '' || !is_readable($wp_load)) { echo "[SKIP] WordPress bootstrap was not found (EFB_WP_LOAD / EFB_E2E_SITE_DIR)\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
require $wp_load;
$passed = 0; $failed = 0;
$t = function ($label, $ok, $info = '') use (&$passed, &$failed) { if ($ok) { $passed++; echo "[PASS] $label\n"; return; } $failed++; echo "[FAIL] $label" . ($info !== '' ? ': ' . $info : '') . "\n"; };
$reads = array();
foreach (array('emsfb_weekly_email_report_enabled', 'emsfb_email_stats_enabled') as $o) add_filter('pre_option_' . $o, function ($v) use (&$reads, $o) { $reads[] = $o; return $v; });
do_action('init');
$t('QA2-019: a visitor\'s request (init, not admin, not cron) reads neither weekly report option', $reads === array(), implode(',', $reads));
$reads = array();
add_filter('wp_doing_cron', '__return_true');
\Emsfb\Email_Monitor::maybe_ensure_schedule();
remove_filter('wp_doing_cron', '__return_true');
$t('… WP-Cron still keeps the schedule (reads them)', in_array('emsfb_weekly_email_report_enabled', $reads, true), implode(',', $reads));

$pub = \Emsfb\_Public::$instance_efb ?: new \Emsfb\_Public();
$css = function ($state, $json) use ($pub) { wp_dequeue_style('Emsfb-response-viewer-css'); wp_deregister_style('Emsfb-response-viewer-css'); $pub->public_scripts_and_css_head($state, $json); return wp_style_is('Emsfb-response-viewer-css', 'enqueued'); };
$plain = str_replace('"', '\\"', wp_json_encode(array(array('type' => 'form', 'steps' => 1), array('type' => 'text', 'id_' => 'a'))));
$login = str_replace('"', '\\"', wp_json_encode(array(array('type' => 'login', 'steps' => 1))));
$t('QA2-019: a plain form page does not load response-viewer-efb.css; a login form and the code finder (track) do', array($css('', $plain), $css('', $login), $css('track', $plain)) === array(false, true, true));
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
