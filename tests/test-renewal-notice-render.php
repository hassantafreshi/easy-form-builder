<?php
/**
 * F21 C: the notice.
 *
 *  - who and where: administrators (manage_options) only; the plugin's screens and the WordPress dashboard only (not
 *    Posts); nothing on fa_IR, Free / Free Plus, without the server's field, with auto_renew;
 *  - what: t7 (notice-info, "ends on <bdi>date</bdi>"), t1 (notice-warning, "ends tomorrow" / "ends today", shown
 *    again after t7 was dismissed), t0 (dashboard only; the plugin's screens keep their card and get the script);
 *    updates_only words; region + aria-labelledby, the Renew link opens a new tab (rel noopener noreferrer, said to
 *    screen readers), "Remind me later", "Check again", a live region; the marks the EFB notice cleaner keeps;
 *  - safety: a hostile date format is escaped, the link is the server's safe link or the fallback, never the code;
 *  - dismissed per user and per stage; a new period (renewal) is a new notice; the cron backup is asked for when the
 *    last answer is older than 36 hours.
 *
 * Run: php tests/test-renewal-notice-render.php
 */
require __DIR__ . '/renewal-test-lib.php';
use Emsfb\Renewal_Reminder as RR;

$T0 = $GLOBALS['efb_rt']['now'];
$at = function ($ts) { $GLOBALS['efb_rt']['now'] = $ts; };
$screen = function ($id) { $GLOBALS['efb_rt']['screen'] = $id === null ? null : (object) array('id' => $id); };
$out = function () { return efb_rt_render(array('\\Emsfb\\Renewal_Reminder', 'render_notice')); };
$has = function ($html) { return strpos($html, 'id="efb-renew-notice"') !== false; };
$setup = function ($days, array $over = array(), $state = 'active') use ($at, $T0, $screen) {
	efb_rt_reset(); $at($T0);
	update_option('timezone_string', 'UTC'); update_option('date_format', 'F j, Y');
	$GLOBALS['efb_rt']['caps'] = array('manage_options' => true);
	$screen('toplevel_page_Emsfb');
	return efb_rt_pro($days, $over, $state);
};

echo "--- who and where ---\n";
$setup(7);
$h = $out();
t('administrator, the plugin\'s screen, 7 days: the notice', $has($h), true);
$screen('dashboard');
t('… the WordPress dashboard: the notice', $has($out()), true);
$screen('easy-form-builder_page_Emsfb_create');
t('… the form builder screen: the notice', $has($out()), true);
$screen('edit-post');
t('… Posts: nothing', $out(), '');
$screen(null);
t('… no screen (AJAX, cron): nothing', $out(), '');
$screen('dashboard');
$GLOBALS['efb_rt']['caps'] = array('edit_posts' => true, 'Emsfb' => true);
t('an editor (the plugin\'s own capability, no manage_options): nothing', $out(), '');
$GLOBALS['efb_rt']['caps'] = array('manage_options' => true);
foreach (array(array('8 days', 8, array()), array('auto_renew', 5, array('auto_renew' => true)), array('lifetime', 5, array('expires_at' => null))) as $c) {
	$setup($c[1], $c[2]);
	t($c[0] . ': nothing', $out(), '');
}
$setup(5); update_option('emsfb_pro', 3);
t('Free Plus: nothing', $out(), '');
$setup(5); delete_option('emsfb_license_info');
t('no field from the server: nothing', $out(), '');
$setup(5); $GLOBALS['efb_rt']['locale'] = 'fa_IR';
t('fa_IR: nothing', $out(), '');
$GLOBALS['efb_rt']['locale'] = 'en_US';

echo "--- t7 ---\n";
$setup(7);
$h = $out();
t('notice-info, dismissible, the marks the EFB notice cleaner keeps', array(strpos($h, 'class="notice notice-info is-dismissible efb efb-renew-notice"') !== false, strpos($h, 'data-efb-notice="renew"') !== false), array(true, true));
t('a region named by its title', array(strpos($h, 'role="region" aria-labelledby="efb-renew-title"') !== false, strpos($h, '<p id="efb-renew-title" class="efb-renew-title"><strong>Easy Form Builder Pro ends on <bdi>' . RR::state()['date'] . '</bdi></strong></p>') !== false), array(true, true));
t('the body says what stays and what pauses', strpos($h, 'Renew before then and everything keeps running exactly as it does today. If it lapses, your forms and entries stay safe, but Pro fields will show as unavailable to visitors and online payments in your forms will pause.') !== false, true);
t('Renew Pro: a new tab, noopener noreferrer, said to screen readers, the server\'s link', (bool) preg_match('#<a class="button button-primary efb-renew-go" href="https://whitestudio\.team/checkout\?renew=tok_e2e_short_lived" target="_blank" rel="noopener noreferrer">Renew Pro<span class="screen-reader-text"> \(opens in a new tab\)</span></a>#', $h), true);
t('Remind me later and Check again are buttons; a polite live region', array(strpos($h, '<button type="button" class="button-link efb-renew-later" data-efb-renew="later">Remind me later</button>') !== false, strpos($h, '<button type="button" class="button-link efb-renew-check" data-efb-renew="check">Already renewed? Check again</button>') !== false, strpos($h, 'role="status" aria-live="polite"') !== false), array(true, true, true));
t('period and stage on the notice (for the dismiss)', (bool) preg_match('/data-period="[a-f0-9]{16}" data-stage="t7"/', $h), true);
t('375 px: the buttons stack, full width, 44 px; text runs plaintext; logical margins', array(strpos($h, '@media (max-width:782px)') !== false, strpos($h, 'min-height:44px') !== false, strpos($h, 'width:100%') !== false, strpos($h, '.efb-renew-notice p{unicode-bidi:plaintext}') !== false, strpos($h, 'margin-left') === false && strpos($h, 'margin-right') === false), array(true, true, true, true, true));
t('the script and its nonce once', array(substr_count($h, 'id="efb-renew-js"'), strpos($h, 'nonce-efb_renew_notice') !== false), array(1, true));
$setup(5, array('lapse_effect' => 'updates_only'));
t('updates_only: the soft words', strpos($out(), 'Renew before then to keep getting updates, new add-ons and support. Your forms keep working either way.') !== false, true);

echo "--- t1, t0 ---\n";
$setup(1);
$at(gmmktime(4, 0, 0, 11, 21, 2025)); // 04:00 UTC, the end at midnight: 20 hours left
efb_rt_pro(20 / 24);
$h = $out();
t('the last day: notice-warning, "ends today" (UTC, 20 h left)', array(strpos($h, 'notice notice-warning') !== false, strpos($h, 'Easy Form Builder Pro ends today') !== false), array(true, true));
update_option('timezone_string', 'Pacific/Auckland');
t('… in Auckland the last day is tomorrow: "ends tomorrow"', strpos($out(), 'Easy Form Builder Pro ends tomorrow') !== false, true);
$setup(-1, array(), 'deactive');
$screen('dashboard');
$h = $out();
t('t0 on the dashboard: "Pro features are paused on this site", the date in <bdi>, Renew + Check again, no "later"', array(strpos($h, 'Pro features are paused on this site') !== false, (bool) preg_match('#ended on <bdi>[^<]+</bdi>\. Your forms, entries and settings are all still here#', $h), strpos($h, 'data-efb-renew="check"') !== false, strpos($h, 'data-efb-renew="later"') === false), array(true, true, true, true));
$screen('toplevel_page_Emsfb');
$h = $out();
t('t0 on the plugin\'s screen: no second notice (the card says it), but the script for the card\'s Check again', array($has($h), strpos($h, 'id="efb-renew-js"') !== false), array(false, true));

echo "--- dismiss: per user, per stage ---\n";
$setup(5);
$st = RR::state();
update_user_meta(1, 'efb_renew_notice', $st['period'] . ':t7');
t('dismissed t7: not shown to this administrator', $out(), '');
$GLOBALS['efb_rt']['uid'] = 2;
t('… another administrator still sees it', $has($out()), true);
$GLOBALS['efb_rt']['uid'] = 1;
$at($T0 + 4.5 * 86400);
t('the last day (t1): shown again even though t7 was dismissed', $has($out()), true);
$at($T0);
efb_rt_pro(370);
efb_rt_pro(6);
t('a stored dismiss of an older period does not hide the new one', $has($out()), true);

echo "--- safety ---\n";
$setup(5);
update_option('date_format', '\\<\\s\\c\\r\\i\\p\\t\\>Y');
$h = $out();
t('a hostile date format is escaped', array(strpos($h, '<script>2') === false, strpos($h, '&lt;script&gt;2') !== false), array(true, true));
update_option('date_format', 'F j, Y');
$setup(5, array('renew_url' => 'https://evil.example/pay'));
$h = $out();
t('a foreign renew link from the server: the fallback is shown, never the foreign host', array(strpos($h, 'evil.example') === false, strpos($h, 'href="https://whitestudio.team/checkout?renew=site&#038;d=' . md5('site.example') . '"') !== false), array(true, true));
t('the activation code is never in the notice', array(strpos($h, EFB_RT_CODE) === false, strpos($h, 'E2EPLACEHOLDER0000') === false), array(true, true));

echo "--- the cron backup ---\n";
$setup(5);
$GLOBALS['efb_rt']['scheduled'] = array();
$out();
t('a fresh answer: nothing scheduled', isset($GLOBALS['efb_rt']['scheduled']['emsfb_revalidate_license']), false);
$at($T0 + 37 * 3600);
$out();
t('the answer is 37 hours old: a check is asked for within a minute', isset($GLOBALS['efb_rt']['scheduled']['emsfb_revalidate_license']), true);

efb_rt_done();
