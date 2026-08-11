<?php
/**
 * When does the "your form emails are not being delivered" notice appear?
 *
 * Every case below renders the real admin_notices callback and looks at what
 * came out, so this is the behaviour an administrator actually gets - not a
 * restatement of the conditions in the source.
 *
 * Three things decide it:
 *   1. the capability of the logged-in user,
 *   2. the stored monitor result (emsfb_email_monitor_last_status),
 *   3. the shape of settings->smtp, which tells a pre-4.1.2 installation
 *      (the number 1, written before this monitor existed) apart from a
 *      current one (a real boolean).
 *
 * Nothing is left behind: the settings cache, the status option, the dismissal
 * meta and the current user are all restored at the end.
 *
 * Run: php tests/test-delivery-notice-scenarios.php
 */

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!file_exists($wp_load)) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit(0);
}

require_once $wp_load;

if (!class_exists('\Emsfb\Email_Monitor')) {
	fwrite(STDERR, "[FAIL] Email_Monitor is not loaded\n");
	exit(1);
}

const STATUS_OPTION = 'emsfb_email_monitor_last_status';
const DISMISS_META  = 'emsfb_delivery_notice_dismissed';

$passed = 0;
$failed = 0;
$rows   = [];

/**
 * Swap the decoded settings for this request only.
 *
 * The object cache is loaded directly instead of writing a settings row: the
 * test must never leave a different "This site can send emails" value behind on
 * the site it runs against.
 *
 * @param mixed $smtp Value for settings->smtp, or the string '(absent)'.
 */
function efb_notice_test_set_smtp($smtp) {
	$settings = get_setting_Emsfb('decoded');
	$settings = is_object($settings) ? clone $settings : new stdClass();

	if ('(absent)' === $smtp) {
		unset($settings->smtp);
	} else {
		$settings->smtp = $smtp;
	}

	get_setting_Emsfb('_clear_cache');
	wp_cache_set('settings:decoded', $settings, 'emsfb', 3600);

	return $settings;
}

function efb_notice_test_render() {
	ob_start();
	\Emsfb\Email_Monitor::render_delivery_failure_notice();
	return (string) ob_get_clean();
}

/**
 * @param string $scenario  What situation this represents.
 * @param mixed  $smtp      settings->smtp for the case, or '(absent)'.
 * @param array|null $status Stored monitor result, or null to remove it.
 * @param bool   $expected  Whether the notice should be rendered.
 */
function efb_notice_test_case($scenario, $smtp, $status, $expected, $prepare = null) {
	global $passed, $failed, $rows;

	if (null === $status) {
		delete_option(STATUS_OPTION);
	} else {
		update_option(STATUS_OPTION, $status, false);
	}
	delete_user_meta(get_current_user_id(), DISMISS_META);
	efb_notice_test_set_smtp($smtp);

	if (is_callable($prepare)) {
		call_user_func($prepare);
		// A preparer may write the status itself (the score cases go through
		// the real save path), so report what is actually stored.
		$stored = get_option(STATUS_OPTION, null);
		$status = is_array($stored) ? $stored : $status;
	}

	$html  = efb_notice_test_render();
	$shown = false !== strpos($html, 'efb-delivery-notice');
	$ok    = ($shown === $expected);

	// A rendered notice must carry the two things it exists for: the panel
	// button and the SMTP guide. A silent case must render nothing at all.
	if ($ok && $shown) {
		$ok = false !== strpos($html, 'page=Emsfb')
			&& false !== strpos($html, \Emsfb\Email_Monitor::get_smtp_guide_url());
	}
	if ($ok && !$shown) {
		$ok = '' === trim($html);
	}

	$rows[] = [
		'scenario' => $scenario,
		'smtp'     => is_bool($smtp) ? ($smtp ? 'true (bool)' : 'false (bool)') : var_export($smtp, true),
		'status'   => null === $status
			? '(never run)'
			: (empty($status) ? '(empty)' : (string) ($status['state'] ?? '?') . ', can_send=' . var_export(!empty($status['can_send_email']), true)),
		'expected' => $expected ? 'SHOWN' : 'silent',
		'result'   => $ok ? 'PASS' : 'FAIL' . ($shown ? ' (was shown)' : ' (was silent)'),
	];

	if ($ok) {
		$passed++;
	} else {
		$failed++;
	}
}

// ---------------------------------------------------------------------------
// Snapshot everything this test touches.
// ---------------------------------------------------------------------------
$original_status   = get_option(STATUS_OPTION, null);
$original_user     = get_current_user_id();
$admin_id          = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);

if ($admin_id <= 0) {
	echo "[SKIP] No administrator account on this site to render the notice for.\n";
	exit(0);
}

wp_set_current_user($admin_id);
$original_dismissal = get_user_meta($admin_id, DISMISS_META, true);

$failed_status = [
	'state' => 'failed', 'message' => 'x', 'context' => 'weekly',
	'checked_at' => '2026-08-10 15:00:00', 'can_send_email' => false,
];
$success_status = [
	'state' => 'success', 'message' => 'x', 'context' => 'weekly',
	'checked_at' => '2026-08-10 15:00:00', 'can_send_email' => true,
];
// The exact payload from a free-plan site whose update-triggered test never got
// to send anything because the daily quota was already used.
$free_limit_status = [
	'state' => 'failed',
	'message' => 'You have reached the free email test limit for this domain.',
	'context' => 'activation',
	'checked_at' => '2026-08-10 15:35:15',
	'can_send_email' => false,
];

echo "\n=== Fresh install of the current version ===\n";

efb_notice_test_case('brand new install, no test has run yet', false, null, false);
efb_notice_test_case('install seeded settings, status option empty', false, [], false);
efb_notice_test_case('test sent, waiting for confirmation', false, ['state' => 'pending', 'can_send_email' => false, 'checked_at' => '2026-08-10 15:00:00'], false);
efb_notice_test_case('test still running', false, ['state' => 'running', 'can_send_email' => false, 'checked_at' => '2026-08-10 15:00:00'], false);
efb_notice_test_case('service reported a delayed delivery', false, ['state' => 'delayed', 'can_send_email' => false, 'checked_at' => '2026-08-10 15:00:00'], false);
efb_notice_test_case('test concluded: nothing was delivered', false, $failed_status, true);
efb_notice_test_case('test concluded: delivery works', false, $success_status, false);

echo "\n=== Updating an installation from 4.1.2 or earlier ===\n";

efb_notice_test_case('legacy site (smtp = 1), update test failed', 1, $failed_status, false);
efb_notice_test_case('legacy site (smtp = "1"), update test failed', '1', $failed_status, false);
efb_notice_test_case('legacy site (smtp = 1), free test limit reached', 1, $free_limit_status, false);
efb_notice_test_case('legacy site that never had sending on (smtp = 0)', 0, $failed_status, true);
efb_notice_test_case('legacy site that never had sending on (smtp = "0")', '0', $failed_status, true);
efb_notice_test_case('legacy site, delivery confirmed by the new test', 1, $success_status, false);

echo "\n=== Current version, admin has the sending switch on ===\n";

efb_notice_test_case('switch on as a boolean, later test failed', true, $failed_status, true);
efb_notice_test_case('switch on as the panel string, later test failed', 'true', $failed_status, true);
efb_notice_test_case('switch off, test failed', false, $failed_status, true);
efb_notice_test_case('settings row has no smtp key at all', '(absent)', $failed_status, true);

echo "\n=== Delivered, but scored too low to rely on ===\n";

$save_status = new ReflectionMethod('\Emsfb\Email_Monitor', 'save_status');
$save_status->setAccessible(true);

// Written through the real save path, so this proves the whole chain: the
// service said can_send_email, the score overrode it, the notice speaks up.
$low_score_writer = function () use ($save_status) {
	$save_status->invoke(null, 'success', 'delivered', 'weekly', ['can_send_email' => true, 'score' => 30]);
};
$healthy_writer = function () use ($save_status) {
	$save_status->invoke(null, 'success', 'delivered', 'weekly', ['can_send_email' => true, 'score' => 82]);
};

efb_notice_test_case('current site, delivered with score 30', false, $failed_status, true, $low_score_writer);
efb_notice_test_case('current site, delivered with score 82', false, $failed_status, false, $healthy_writer);
efb_notice_test_case('legacy site, delivered with score 30', 1, $failed_status, false, $low_score_writer);

echo "\n=== Dismissal ===\n";

// Dismissal is per run: the same failure stays hidden, a later one speaks up.
$fingerprint = md5($failed_status['checked_at'] . '|failed');
efb_notice_test_case('admin dismissed this exact run', false, $failed_status, false, function () use ($fingerprint) {
	update_user_meta(get_current_user_id(), DISMISS_META, $fingerprint);
});

$later_failure = $failed_status;
$later_failure['checked_at'] = '2026-08-17 15:00:00';
efb_notice_test_case('a later run fails after that dismissal', false, $later_failure, true, function () use ($fingerprint) {
	update_user_meta(get_current_user_id(), DISMISS_META, $fingerprint);
});

echo "\n=== Who sees it ===\n";

wp_set_current_user(0);
efb_notice_test_case('visitor without manage_options', false, $failed_status, false);
wp_set_current_user($admin_id);
efb_notice_test_case('administrator, same failed run', false, $failed_status, true);

// ---------------------------------------------------------------------------
// Restore.
// ---------------------------------------------------------------------------
if (null === $original_status) {
	delete_option(STATUS_OPTION);
} else {
	update_option(STATUS_OPTION, $original_status, false);
}

if ('' === $original_dismissal) {
	delete_user_meta($admin_id, DISMISS_META);
} else {
	update_user_meta($admin_id, DISMISS_META, $original_dismissal);
}

get_setting_Emsfb('_clear_cache');
wp_cache_delete('settings:decoded', 'emsfb');
wp_set_current_user($original_user);

// ---------------------------------------------------------------------------
// Report.
// ---------------------------------------------------------------------------
$widths = ['scenario' => 0, 'smtp' => 0, 'status' => 0, 'expected' => 0];
foreach ($rows as $row) {
	foreach ($widths as $key => $width) {
		$widths[$key] = max($width, strlen($row[$key]));
	}
}

echo "\n";
printf(
	"%-{$widths['scenario']}s  %-{$widths['smtp']}s  %-{$widths['status']}s  %-{$widths['expected']}s  %s\n",
	'SCENARIO', 'settings->smtp', 'stored result', 'notice', ''
);
echo str_repeat('-', array_sum($widths) + 14) . "\n";
foreach ($rows as $row) {
	printf(
		"%-{$widths['scenario']}s  %-{$widths['smtp']}s  %-{$widths['status']}s  %-{$widths['expected']}s  %s\n",
		$row['scenario'], $row['smtp'], $row['status'], $row['expected'], $row['result']
	);
}

echo "\n" . str_repeat('=', 40) . "\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";

exit($failed > 0 ? 1 : 0);
