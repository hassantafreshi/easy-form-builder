<?php
/**
 * What does the delivery notice say, and when?
 *
 * Every case renders the real admin_notices callback and reads what came out,
 * so this is the behaviour an administrator actually gets.
 *
 * Four things decide it:
 *   1. the capability of the logged-in user,
 *   2. the automated monitor's last run (emsfb_email_monitor_last_status),
 *   3. the last check started from the panel or wizard (emsfb_email_status),
 *   4. the shape of settings->smtp, which tells a pre-4.1.2 installation
 *      (the number 1, written before this monitor existed) from a current one.
 *
 * The wording matters as much as the timing: a site whose mail arrives in the
 * spam folder must be told about spam, not told to run a check it already ran.
 *
 * Nothing is left behind: both options, the settings cache, the dismissal meta
 * and the current user are restored at the end.
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

const MONITOR_OPTION = 'emsfb_email_monitor_last_status';
const PANEL_OPTION   = 'emsfb_email_status';
const DISMISS_META   = 'emsfb_delivery_notice_dismissed';

// The sentence that must never appear once a check has actually run - telling
// an administrator to "run the email check" right after they ran one is the
// bug this whole file guards.
const NAG_SENTENCE = 'so you can be sure the messages your forms send';

$passed = 0;
$failed = 0;
$rows   = [];

/** Site-local mysql time, the format the monitor stores. */
function efb_notice_local_time($minutes_ago) {
	return wp_date('Y-m-d H:i:s', time() - ($minutes_ago * MINUTE_IN_SECONDS));
}

/** UTC mysql time, the format the panel result stores. */
function efb_notice_utc_time($minutes_ago) {
	return gmdate('Y-m-d H:i:s', time() - ($minutes_ago * MINUTE_IN_SECONDS));
}

/**
 * Swap the decoded settings for this request only.
 *
 * The object cache is loaded directly rather than writing a settings row: this
 * test must never leave a different "This site can send emails" value behind.
 *
 * @param mixed $smtp Value for settings->smtp, or the string '(absent)'.
 */
function efb_notice_set_smtp($smtp) {
	$settings = get_setting_Emsfb('decoded');
	$settings = is_object($settings) ? clone $settings : new stdClass();

	if ('(absent)' === $smtp) {
		unset($settings->smtp);
	} else {
		$settings->smtp = $smtp;
	}

	get_setting_Emsfb('_clear_cache');
	wp_cache_set('settings:decoded', $settings, 'emsfb', 3600);
}

function efb_notice_render() {
	ob_start();
	\Emsfb\Email_Monitor::render_delivery_failure_notice();
	return (string) ob_get_clean();
}

/**
 * @param string     $scenario What situation this represents.
 * @param array|null $monitor  Stored monitor run, or null for none.
 * @param array|null $panel    Stored panel check result, or null for none.
 * @param string     $expected 'silent', 'spam' or 'undelivered'.
 * @param mixed      $smtp     settings->smtp for the case.
 * @param callable   $prepare  Optional extra setup, run last.
 */
function efb_notice_case($scenario, $monitor, $panel, $expected, $smtp = false, $prepare = null) {
	global $passed, $failed, $rows;

	if (null === $monitor) { delete_option(MONITOR_OPTION); } else { update_option(MONITOR_OPTION, $monitor, false); }
	if (null === $panel)   { delete_option(PANEL_OPTION); }   else { update_option(PANEL_OPTION, $panel, false); }

	delete_user_meta(get_current_user_id(), DISMISS_META);
	efb_notice_set_smtp($smtp);

	if (is_callable($prepare)) {
		call_user_func($prepare);
	}

	$html = efb_notice_render();

	$shown = false !== strpos($html, 'efb-delivery-notice');
	if (!$shown) {
		$actual = 'silent';
	} elseif (false !== strpos($html, 'going to the spam folder')) {
		$actual = 'spam';
	} elseif (false !== strpos($html, 'not being delivered')) {
		$actual = 'undelivered';
	} else {
		$actual = 'shown, unknown copy';
	}

	$notes = '';
	$ok    = ($actual === $expected);

	if ($ok && 'silent' === $expected && '' !== trim($html)) {
		$ok = false;
		$notes = 'expected no output at all';
	}
	if ($ok && 'silent' !== $expected) {
		// A rendered notice must carry the panel button and the SMTP guide,
		// and must never fall back to the "go and run a check" wording.
		if (false === strpos($html, 'page=Emsfb') || false === strpos($html, \Emsfb\Email_Monitor::get_smtp_guide_url())) {
			$ok = false;
			$notes = 'missing action links';
		} elseif (false !== strpos($html, NAG_SENTENCE)) {
			$ok = false;
			$notes = 'still tells the admin to run a check';
		}
	}

	$rows[] = [
		'scenario' => $scenario,
		'smtp'     => is_bool($smtp) ? ($smtp ? 'true (bool)' : 'false (bool)') : var_export($smtp, true),
		'expected' => $expected,
		'actual'   => $actual,
		'result'   => $ok ? 'PASS' : ('FAIL' . ($notes !== '' ? ' - ' . $notes : '')),
	];

	if ($ok) { $passed++; } else { $failed++; }
}

// ---------------------------------------------------------------------------
// Snapshot everything this test touches.
// ---------------------------------------------------------------------------
$original_monitor = get_option(MONITOR_OPTION, null);
$original_panel   = get_option(PANEL_OPTION, null);
$original_user    = get_current_user_id();
$admin_id         = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);

if ($admin_id <= 0) {
	echo "[SKIP] No administrator account on this site to render the notice for.\n";
	exit(0);
}

wp_set_current_user($admin_id);
$original_dismissal = get_user_meta($admin_id, DISMISS_META, true);

// --- monitor records -------------------------------------------------------

// The exact record from a free-plan site whose activation test was refused by
// the quota before it could send anything.
$quota_failure = [
	'state' => 'failed',
	'message' => 'You have reached the free email test limit for this domain.',
	'context' => 'activation',
	'checked_at' => efb_notice_local_time(5),
	'can_send_email' => false,
	'delivered' => false,
	'score' => null,
	'reason' => 'service_start_error',
];
$wp_mail_failed = [
	'state' => 'failed', 'message' => 'WordPress could not send the automated email test.',
	'context' => 'weekly', 'checked_at' => efb_notice_local_time(5),
	'can_send_email' => false, 'delivered' => false, 'score' => null, 'reason' => 'wp_mail_failed',
];
$never_arrived = [
	'state' => 'failed', 'message' => 'The email delivery test timed out.',
	'context' => 'weekly', 'checked_at' => efb_notice_local_time(5),
	'can_send_email' => false, 'delivered' => false, 'score' => null, 'reason' => 'expired',
];
$analyzed = function ($score, $delivered = true, $minutes_ago = 5) {
	return [
		'state' => $delivered ? 'success' : 'failed',
		'message' => 'analysed',
		'context' => 'weekly',
		'checked_at' => efb_notice_local_time($minutes_ago),
		'can_send_email' => \Emsfb\Email_Monitor::is_delivery_confirmed(['can_send_email' => $delivered, 'score' => $score]),
		'delivered' => $delivered,
		'score' => $score,
		'reason' => 'analyzed',
	];
};
// Written by a version that did not record why a run ended.
$pre_update_record = [
	'state' => 'failed', 'message' => 'x', 'context' => 'weekly',
	'checked_at' => efb_notice_local_time(5), 'can_send_email' => false,
];
$still_pending = [
	'state' => 'pending', 'message' => 'x', 'context' => 'weekly',
	'checked_at' => efb_notice_local_time(1), 'can_send_email' => false, 'reason' => 'sent',
];

// --- panel records ---------------------------------------------------------

$panel_result = function ($id, $details, $minutes_ago = 2) {
	return [
		'status' => 'ok_set_smtp' === $id ? 'ok_set_smtp' : 'error',
		'message' => ['title' => 't', 'description' => 'd', 'id' => $id],
		'details' => array_merge(['test_timestamp' => efb_notice_utc_time($minutes_ago)], $details),
	];
};
$panel_spam    = $panel_result('email_test_low_score', ['delivered' => true, 'delivery_score' => 25, 'can_send_email' => false]);
$panel_healthy = $panel_result('email_settings_configured', ['delivered' => true, 'delivery_score' => 85, 'can_send_email' => true]);
$panel_failed  = $panel_result('email_test_failed', ['delivered' => false, 'delivery_score' => null, 'can_send_email' => false]);
$panel_pending = $panel_result('email_test_pending', ['can_send_email' => false]);
$panel_broken  = $panel_result('service_request_error', ['can_send_email' => false]);

// ---------------------------------------------------------------------------

echo "\n=== Nothing conclusive is known ===\n";
efb_notice_case('brand new install, nothing has run', null, null, 'silent');
efb_notice_case('monitor test still waiting for confirmation', $still_pending, null, 'silent');
efb_notice_case('panel check still waiting', null, $panel_pending, 'silent');
efb_notice_case('panel check could not reach the service', null, $panel_broken, 'silent');
efb_notice_case('record written before runs recorded a reason', $pre_update_record, null, 'silent');

echo "\n=== The reported bug: the monitor run never happened ===\n";
efb_notice_case('free test quota refused the run, nothing else known', $quota_failure, null, 'silent');
efb_notice_case('quota refused the run, admin check scored 25', $quota_failure, $panel_spam, 'spam');
efb_notice_case('quota refused the run, admin check scored 85', $quota_failure, $panel_healthy, 'silent');
efb_notice_case('quota refused the run, admin check found nothing arrived', $quota_failure, $panel_failed, 'undelivered');

echo "\n=== The monitor measured something ===\n";
efb_notice_case('WordPress itself could not send', $wp_mail_failed, null, 'undelivered');
efb_notice_case('the test email never arrived', $never_arrived, null, 'undelivered');
efb_notice_case('delivered, score 25 (spam range)', $analyzed(25), null, 'spam');
efb_notice_case('delivered, score 60 (spam range)', $analyzed(60), null, 'spam');
efb_notice_case('delivered, score 12 (below the sending threshold)', $analyzed(12), null, 'undelivered');
efb_notice_case('delivered, score 82 (healthy)', $analyzed(82), null, 'silent');
efb_notice_case('analysed, nothing arrived', $analyzed(null, false), null, 'undelivered');

echo "\n=== Two records: the most recent measurement wins ===\n";
efb_notice_case('panel check (2 min ago) newer than monitor failure (90 min ago)', $analyzed(null, false, 90), $panel_spam, 'spam');
efb_notice_case('monitor failure (2 min ago) newer than panel check (90 min ago)', $analyzed(null, false, 2), $panel_result('email_test_low_score', ['delivered' => true, 'delivery_score' => 60], 90), 'undelivered');
efb_notice_case('panel healthy check newer than a monitor spam score', $analyzed(25, true, 90), $panel_healthy, 'silent');

echo "\n=== Installations from 4.1.2 and earlier ===\n";
efb_notice_case('legacy site (smtp = 1), quota failure', $quota_failure, null, 'silent', 1);
efb_notice_case('legacy site (smtp = 1), nothing arrived', $wp_mail_failed, null, 'silent', 1);
efb_notice_case('legacy site (smtp = 1), score 25', $analyzed(25), null, 'silent', 1);
efb_notice_case('legacy site (smtp = "1"), score 25', $analyzed(25), null, 'silent', '1');
efb_notice_case('legacy site that never had sending on (smtp = 0)', $analyzed(25), null, 'spam', 0);

echo "\n=== Current installations, switch on or off ===\n";
efb_notice_case('switch on as a boolean, score 25', $analyzed(25), null, 'spam', true);
efb_notice_case('switch on as the panel string, nothing arrived', $wp_mail_failed, null, 'undelivered', 'true');
efb_notice_case('no smtp key at all, nothing arrived', $wp_mail_failed, null, 'undelivered', '(absent)');

echo "\n=== Dismissal and capability ===\n";
$spam_record = $analyzed(25);
$spam_fingerprint = md5($spam_record['checked_at'] . '|spam');
efb_notice_case('admin dismissed this exact verdict', $spam_record, null, 'silent', false, function () use ($spam_fingerprint) {
	update_user_meta(get_current_user_id(), DISMISS_META, $spam_fingerprint);
});
efb_notice_case('the problem changes after that dismissal', $wp_mail_failed, null, 'undelivered', false, function () use ($spam_fingerprint) {
	update_user_meta(get_current_user_id(), DISMISS_META, $spam_fingerprint);
});

wp_set_current_user(0);
efb_notice_case('visitor without manage_options', $wp_mail_failed, null, 'silent');
wp_set_current_user($admin_id);
efb_notice_case('administrator, same failed run', $wp_mail_failed, null, 'undelivered');

// ---------------------------------------------------------------------------
// Restore.
// ---------------------------------------------------------------------------
if (null === $original_monitor) { delete_option(MONITOR_OPTION); } else { update_option(MONITOR_OPTION, $original_monitor, false); }
if (null === $original_panel)   { delete_option(PANEL_OPTION); }   else { update_option(PANEL_OPTION, $original_panel, false); }

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
$widths = ['scenario' => 0, 'smtp' => 0, 'expected' => 0, 'actual' => 0];
foreach ($rows as $row) {
	foreach ($widths as $key => $width) {
		$widths[$key] = max($width, strlen($row[$key]));
	}
}

echo "\n";
printf("%-{$widths['scenario']}s  %-{$widths['smtp']}s  %-{$widths['expected']}s  %-{$widths['actual']}s  %s\n",
	'SCENARIO', 'settings->smtp', 'expected', 'actual', '');
echo str_repeat('-', array_sum($widths) + 12) . "\n";
foreach ($rows as $row) {
	printf("%-{$widths['scenario']}s  %-{$widths['smtp']}s  %-{$widths['expected']}s  %-{$widths['actual']}s  %s\n",
		$row['scenario'], $row['smtp'], $row['expected'], $row['actual'], $row['result']);
}

echo "\n" . str_repeat('=', 40) . "\n";
echo "RESULTS: {$passed} passed, {$failed} failed\n";

exit($failed > 0 ? 1 : 0);
