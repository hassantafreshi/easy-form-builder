<?php
/**
 * F21 B: exactly one e-mail per expiry date.
 *
 *  - two cron runs → one reminder (to admin_email and the support address, once each); a run that finds the lock of
 *    a parallel run sends nothing; a failed send is retried on the next run;
 *  - a late cron (3 days left, or the last day) still sends the reminder with its own number; after the end only the
 *    one "paused" e-mail, never the reminder; reminder then "paused": one of each;
 *  - early renewal: before the e-mail → none; after → none for the old date, one for the new date next year;
 *    a plan change (a new code, the same date) → no second e-mail;
 *  - multisite: two sites, the same administrator → one e-mail each, each with its own site name;
 *  - renewMail off, sending off, no recipient → nothing and no mark (owed when switched on); auto_renew, a stale
 *    answer, fa_IR → nothing;
 *  - the text version: set for this message only (the phpmailer_init hook is gone afterwards), in the site's language
 *    even when an Arabic administrator triggered it.
 *
 * Run: php tests/test-renewal-reminder-idempotent.php
 */
require __DIR__ . '/renewal-test-lib.php';
use Emsfb\Renewal_Reminder as RR;

$T0 = $GLOBALS['efb_rt']['now'];
$at = function ($ts) { $GLOBALS['efb_rt']['now'] = $ts; };
$mails = function () { return $GLOBALS['efb_rt']['mails']; };
$subs = function () { return array_map(function ($m) { return $m['to'] . ' | ' . $m['subject']; }, $GLOBALS['efb_rt']['mails']); };
$setup = function ($days, array $over = array(), $state = 'active') use ($at, $T0) {
	efb_rt_reset(); $at($T0);
	update_option('admin_email', 'owner@site.example');
	update_option('blogname', 'Example Site');
	update_option('timezone_string', 'UTC');
	update_option('date_format', 'F j, Y');
	return efb_rt_pro($days, $over, $state);
};

echo "--- two runs, one e-mail ---\n";
$setup(7);
t('first run: sent', RR::tick(), 'sent');
t('second run: already', RR::tick(), 'already');
$at($T0 + 86400);
t('next day: already', RR::tick(), 'already');
$date = RR::state('en_US')['date'];
t('one message to each of the two addresses, subject A', $subs(), array('owner@site.example | Easy Form Builder Pro on Example Site ends on ' . $date, 'support@site.example | Easy Form Builder Pro on Example Site ends on ' . $date));
t('the plugin mailer got state renewReminder (no Human Shield veto, no "View message" button)', end($GLOBALS['efb_rt_fn']->calls)['state'], 'renewReminder');
$m = $mails()[0];
t('the text version is set for it and says the same', array(strpos($m['alt'], 'Hi,') === 0, strpos($m['alt'], '7 days from now') !== false, strpos($m['alt'], 'Renew Pro: https://whitestudio.team/checkout?renew=tok_e2e_short_lived') !== false), array(true, true, true));
t('the phpmailer_init hook is gone after the message', efb_rt_hook_count('phpmailer_init'), 0);
$GLOBALS['efb_rt_fn']->send_email_state_new('x@site.example', 'Another e-mail', '<p>x</p>', 0, 'newMessage', 'null');
t('… so the next e-mail of the site has no text version from F21', end($GLOBALS['efb_rt']['mails'])['alt'], '');
t('the sent mark: the date and when', array(RR::sent()['expires_at'], RR::sent()['t7_sent']), array($T0 + 7 * 86400, $T0));

echo "--- a parallel run holds the lock ---\n";
$setup(6);
add_option('emsfb_renew_lock_' . ($T0 + 6 * 86400) . '_t7', $T0 - 5);
t('the lock of another run: nothing sent', array(RR::tick(), count($mails())), array('locked', 0));

echo "--- a failed send is retried ---\n";
$setup(6);
$GLOBALS['efb_rt']['mail_ok'] = false;
t('the mailer fails: failed, no mark, the lock released', array(RR::tick(), RR::sent($T0 + 6 * 86400)['t7_sent'], get_option('emsfb_renew_lock_' . ($T0 + 6 * 86400) . '_t7', 'none')), array('failed', 0, 'none'));
$GLOBALS['efb_rt']['mail_ok'] = true;
t('next run: sent', array(RR::tick(), count($mails())), array('sent', 2));

echo "--- a late cron ---\n";
$setup(3);
RR::tick();
t('first run with 3 days left: the reminder says 3 days', strpos($mails()[0]['alt'], '3 days from now') !== false, true);
$setup(0.5);
RR::tick();
t('first run on the last day: the reminder, "1 day from now"', array(count($mails()), strpos($mails()[0]['alt'], '1 day from now') !== false), array(2, true));
$setup(-1, array(), 'deactive');
t('first run after the end (deactive): only the "paused" e-mail', array(RR::tick(), RR::tick(), end($GLOBALS['efb_rt_fn']->calls)['state'], $mails()[0]['subject']), array('sent', 'already', 'renewPaused', 'Pro features are paused on Example Site'));
$setup(-1);
t('after the end while the server still says active: nothing (its grace)', array(RR::tick(), count($mails())), array('none', 0));

echo "--- reminder, then the end: one of each ---\n";
$setup(2);
RR::tick();
$at($T0 + 3 * 86400);
$GLOBALS['efb_rt']['json'] = efb_rt_answer(-1, array('expires_at' => gmdate('Y-m-d\TH:i:s\Z', $T0 + 2 * 86400)), 'deactive');
$GLOBALS['efb_rt_fn']->update_pro_status_efb(EFB_RT_CODE);
t('the end confirmed: t0, the paused e-mail once', array(RR::state()['stage'], RR::tick(), RR::tick()), array('t0', 'sent', 'already'));
t('two messages per address in all (reminder + paused)', count($mails()), 4);

echo "--- early renewal ---\n";
$setup(10);
$at($T0 + 2 * 86400);
efb_rt_pro(365);
t('renewed before the window: nothing ever for the old date', array(RR::tick(), count($mails())), array('none', 0));
$setup(6);
RR::tick();
efb_rt_pro(371);
t('renewed after the reminder: no more for that date', array(RR::state()['stage'], RR::tick(), count($mails())), array(null, 'none', 2));
$at($T0 + 365 * 86400);
$GLOBALS['efb_rt']['json'] = efb_rt_answer(6);
efb_rt_pro(6);
t('a year later, 6 days before the new date: one new reminder', array(RR::tick(), RR::tick(), count($mails())), array('sent', 'already', 4));

echo "--- a plan change with the same date ---\n";
$setup(5);
RR::tick();
update_option('emsfb_pro_activeCode', md5('site.example') . '@CHANGEDPLACEHOLD0');
RR::store(efb_rt_answer(5), get_option('emsfb_pro_activeCode'));
t('a new code, the same end date: a new period but no second e-mail', array(RR::state()['stage'], RR::tick(), count($mails())), array('t7', 'already', 2));
RR::forget();
RR::store(efb_rt_answer(5), get_option('emsfb_pro_activeCode'));
t('even after the marks were forgotten (code removed and entered again): the lock stops it', array(RR::tick(), count($mails())), array('locked', 2));

echo "--- multisite ---\n";
efb_rt_site(1); $setup(7); update_option('blogname', 'Site One');
efb_rt_site(2); $setup(7); update_option('blogname', 'Site Two');
$GLOBALS['efb_rt']['mails'] = array();
efb_rt_site(1); RR::tick(); RR::tick();
efb_rt_site(2); RR::tick(); RR::tick();
efb_rt_site(1); RR::tick();
$owner = array_values(array_filter($mails(), function ($m) { return $m['to'] === 'owner@site.example'; }));
t('the same administrator, two sites, five runs: one e-mail per site, each with its own name', array(count($owner), strpos($owner[0]['subject'], 'Site One') !== false, strpos($owner[1]['subject'], 'Site Two') !== false, $owner[0]['site'], $owner[1]['site']), array(2, true, true, 1, 2));
efb_rt_site(1);

echo "--- nothing, and nothing marked ---\n";
$setup(5);
update_option('emsfb_settings_obj', (object) array('emailSupporter' => 'support@site.example', 'renewMail' => '0'));
t('renewMail off: nothing, no mark, no lock', array(RR::tick(), count($mails()), RR::sent($T0 + 5 * 86400)['t7_sent'], get_option('emsfb_renew_lock_' . ($T0 + 5 * 86400) . '_t7', 'none')), array('off', 0, 0, 'none'));
update_option('emsfb_settings_obj', (object) array('emailSupporter' => 'support@site.example', 'renewMail' => '1'));
t('… switched on again: sent', RR::tick(), 'sent');
$setup(5);
update_option('efb_rt_no_mail', 1);
t('the site cannot send e-mail: nothing, no mark', array(RR::tick(), count($mails())), array('cannot_send', 0));
delete_option('efb_rt_no_mail');
$setup(5);
update_option('admin_email', 'not an address');
update_option('emsfb_settings_obj', (object) array('emailSupporter' => ''));
t('no address: nothing', RR::tick(), 'no_recipient');
$setup(5);
update_option('emsfb_settings_obj', (object) array('emailSupporter' => 'OWNER@site.example'));
RR::tick();
t('the support address is the administrator\'s: one message', count($mails()), 1);
$setup(5, array('auto_renew' => true));
t('auto_renew: nothing', RR::tick(), 'none');
$setup(12);
$at($T0 + 8 * 86400);
t('an answer 8 days old (4 days left): nothing', RR::tick(), 'none');
$setup(5);
$GLOBALS['efb_rt']['locale'] = 'fa_IR';
t('fa_IR: nothing', array(RR::tick(), count($mails())), array('none', 0));
$GLOBALS['efb_rt']['locale'] = 'en_US';

echo "--- the site's language ---\n";
$setup(5);
$GLOBALS['efb_rt']['user_locale'] = 'ar';
RR::tick();
$GLOBALS['efb_rt']['user_locale'] = null;
t('an Arabic administrator triggered it: written in the site\'s language (en_US, Latin digits, ltr)', array(strpos($mails()[0]['html'], '<html lang="en-US" dir="ltr">') !== false, strpos($mails()[0]['alt'], '5 days from now') !== false, $GLOBALS['efb_rt']['switched']), array(true, true, array()));

efb_rt_done();
