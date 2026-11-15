<?php
/**
 * F21 G: what AutoPilot / SitePilot AI can read about the licence (capabilities().plan.renewal), read-only and
 * without secrets: known, ends_on (site date), expires_at (UTC), days_left, auto_renew, lifetime, stage, which
 * reminder e-mails went out and when, whether the reminder e-mail is on. Never the renew link (it can hold a token),
 * never the activation code; known false on fa_IR, without the server's field, with a stale answer or another code.
 * Nothing in it can be written: the plugin offers no route that changes the reminder for an assistant.
 *
 * Run: php tests/test-renewal-capability.php
 */
require __DIR__ . '/renewal-test-lib.php';
use Emsfb\Renewal_Reminder as RR;

$T0 = $GLOBALS['efb_rt']['now'];
$setup = function ($days, array $over = array(), $state = 'active') use ($T0) {
	efb_rt_reset(); $GLOBALS['efb_rt']['now'] = $T0;
	update_option('timezone_string', 'UTC'); update_option('admin_email', 'owner@site.example');
	return efb_rt_pro($days, $over, $state);
};
$empty = array('known' => false, 'ends_on' => null, 'expires_at' => null, 'days_left' => null, 'auto_renew' => false, 'lifetime' => false, 'stage' => null, 'reminder_sent' => array('t7' => null, 't0' => null), 'reminder_email' => true);

efb_rt_reset();
t('nothing from the server: known false, every value null', RR::capability(), $empty);
$setup(30);
$c = RR::capability();
t('30 days: known, the end date (site date and UTC), 30 days, no stage', array($c['known'], $c['ends_on'], $c['expires_at'], $c['days_left'], $c['stage']), array(true, gmdate('Y-m-d', $T0 + 30 * 86400 - 1), gmdate('Y-m-d\TH:i:s\Z', $T0 + 30 * 86400), 30, null));
$setup(6);
RR::tick();
$c = RR::capability();
t('6 days, the reminder sent: stage t7, reminder_sent.t7 is when', array($c['stage'], $c['days_left'], $c['reminder_sent']['t7'], $c['reminder_sent']['t0']), array('t7', 6, gmdate('Y-m-d\TH:i:s\Z', $T0), null));
$json = json_encode($c);
t('no renew link, no token, no code, no e-mail address in it', array(strpos($json, 'whitestudio'), strpos($json, 'tok_'), strpos($json, 'E2EPLACEHOLDER'), strpos($json, md5('site.example')), strpos($json, '@')), array(false, false, false, false, false));
$setup(-1, array(), 'deactive');
t('after the end (deactive): stage t0, 0 days', array(RR::capability()['stage'], RR::capability()['days_left']), array('t0', 0));
$setup(5, array('auto_renew' => true));
t('automatic renewal: known, auto_renew, no stage', array(RR::capability()['known'], RR::capability()['auto_renew'], RR::capability()['stage']), array(true, true, null));
$setup(5, array('expires_at' => null));
t('lifetime: known, lifetime, no date', array(RR::capability()['known'], RR::capability()['lifetime'], RR::capability()['ends_on']), array(true, true, null));
$setup(5);
$GLOBALS['efb_rt']['now'] = $T0 + 8 * 86400;
t('a stale answer: known false', RR::capability()['known'], false);
$setup(5); $GLOBALS['efb_rt']['locale'] = 'fa_IR';
t('fa_IR: known false', RR::capability(), $empty);
$GLOBALS['efb_rt']['locale'] = 'en_US';
$setup(5); update_option('emsfb_settings_obj', (object) array('renewMail' => '0'));
t('the reminder e-mail switched off: reminder_email false', RR::capability()['reminder_email'], false);

$api = file_get_contents(dirname(__DIR__) . '/includes/class-Emsfb-builder-api.php');
t('Builder_Api::capabilities() puts it at plan.renewal (the site\'s own plan only)', (bool) preg_match("/if \\(class_exists\\('\\\\\\\\Emsfb\\\\\\\\Renewal_Reminder'\\) && !isset\\(\\\$args\\['package'\\]\\)\\) \\{\\s*\\\$out\\['plan'\\]\\['renewal'\\] = Renewal_Reminder::capability\\(\\);/", $api), true);
$src = file_get_contents(dirname(__DIR__) . '/includes/class-Emsfb-renewal-reminder.php');
t('no REST route and no AJAX action that an assistant could use to change the reminder (only the two notice actions, manage_options + nonce)', array(substr_count($src, 'register_rest_route'), preg_match_all("/add_action\\('wp_ajax_/", $src)), array(0, 2));

efb_rt_done();
