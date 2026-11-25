<?php
/**
 * Test only (tests/upgrade/run-upgrade-431.sh copies it into wp-content/mu-plugins of a throw-away site): times Easy
 * Form Builder's schema upgrade (Emsfb::maybe_upgrade_schema_efb() runs on plugins_loaded at priority 11) and keeps
 * the site off the network (the add-on server, the language packs, the e-mail test), so the run is the same every
 * time. The result is the option efb_upgrade_test_timing: from / to version, seconds, queries, the slowest DDL.
 */
if (!defined('ABSPATH')) exit;
if (!defined('SAVEQUERIES')) define('SAVEQUERIES', true);

add_filter('pre_http_request', function ($pre, $args, $url) {
	$host = (string) parse_url((string) $url, PHP_URL_HOST);
	if (in_array($host, array('127.0.0.1', 'localhost'), true)) return $pre;
	return new WP_Error('efb_upgrade_test_offline', 'The upgrade test keeps this site off the network: ' . $host);
}, 1, 3);

add_action('plugins_loaded', function () {
	global $wpdb;
	$GLOBALS['efb_upg_t0'] = microtime(true);
	$GLOBALS['efb_upg_q0'] = is_array($wpdb->queries) ? count($wpdb->queries) : 0;
	$GLOBALS['efb_upg_v0'] = (string) get_option('Emsfb_db_version', '');
}, 10);

add_action('plugins_loaded', function () {
	global $wpdb;
	$v1 = (string) get_option('Emsfb_db_version', '');
	if (!isset($GLOBALS['efb_upg_v0']) || $GLOBALS['efb_upg_v0'] === $v1) return; // no upgrade in this request
	$secs = microtime(true) - $GLOBALS['efb_upg_t0'];
	$queries = is_array($wpdb->queries) ? array_slice($wpdb->queries, $GLOBALS['efb_upg_q0']) : array();
	$ddl = array();
	foreach ($queries as $q) {
		if (!preg_match('/^\s*(ALTER|CREATE|DROP|INSERT|UPDATE|DELETE)\b/i', (string) $q[0])) continue;
		$ddl[] = array('sql' => substr((string) preg_replace('/\s+/', ' ', (string) $q[0]), 0, 180), 'seconds' => round((float) $q[1], 3));
	}
	usort($ddl, function ($a, $b) { return $b['seconds'] <=> $a['seconds']; });
	update_option('efb_upgrade_test_timing', array(
		'from'    => $GLOBALS['efb_upg_v0'],
		'to'      => $v1,
		'seconds' => round($secs, 3),
		'queries' => count($queries),
		'writes'  => count($ddl),
		'slowest' => array_slice($ddl, 0, 15),
	), false);
}, 12);
