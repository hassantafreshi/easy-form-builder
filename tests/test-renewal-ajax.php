<?php
/**
 * F21 C: the two AJAX actions of the notice.
 *
 *  - efb_renew_dismiss: nonce (efb_renew_notice) and manage_options, else 403; period must be 16 hex characters and
 *    stage t7 | t1 | t0, else 400 and nothing stored; a good request stores "{period}:{stage}" for this user only;
 *  - efb_license_recheck: nonce and manage_options; nothing to check without a reminder on screen; asks the server
 *    once (the licence check), at most once per 5 minutes per site (a second click gets the "try again" words, and
 *    the server is not asked); renewed → renewed true and "Thanks — your renewal is confirmed."; not renewed → the
 *    "No renewal found yet" words; the server just said deactive → t0 and its one e-mail; the client cannot pass
 *    anything that changes what is asked.
 *
 * Run: php tests/test-renewal-ajax.php
 */
require __DIR__ . '/renewal-test-lib.php';
use Emsfb\Renewal_Reminder as RR;

$T0 = $GLOBALS['efb_rt']['now'];
$at = function ($ts) { $GLOBALS['efb_rt']['now'] = $ts; };
$D = array('\\Emsfb\\Renewal_Reminder', 'ajax_dismiss');
$C = array('\\Emsfb\\Renewal_Reminder', 'ajax_recheck');
$N = 'nonce-efb_renew_notice';
$setup = function ($days, array $over = array(), $state = 'active') use ($at, $T0) {
	efb_rt_reset(); $at($T0);
	update_option('admin_email', 'owner@site.example');
	$GLOBALS['efb_rt']['caps'] = array('manage_options' => true);
	$GLOBALS['efb_rt']['uid'] = 1;
	return efb_rt_pro($days, $over, $state);
};

echo "--- efb_renew_dismiss ---\n";
$setup(5);
$p = RR::state()['period'];
t('a bad nonce: 403, nothing stored', array(efb_rt_ajax($D, array('nonce' => 'nonce-wp_rest', 'period' => $p, 'stage' => 't7'))[1], get_user_meta(1, 'efb_renew_notice')), array(403, ''));
$GLOBALS['efb_rt']['caps'] = array('Emsfb' => true, 'edit_posts' => true);
t('an editor with the plugin\'s capability: 403', efb_rt_ajax($D, array('nonce' => $N, 'period' => $p, 'stage' => 't7'))[1], 403);
$GLOBALS['efb_rt']['caps'] = array('manage_options' => true);
foreach (array(
	'a period that is not 16 hex characters' => array($p . 'x', 't7'),
	'an upper-case period' => array(strtoupper($p), 't7'),
	'a period with a quote' => array(substr($p, 0, 15) . '"', 't7'),
	'an unknown stage' => array($p, 't9'),
	'a stage with more after it' => array($p, "t7\n"),
	'no period' => array('', 't7'),
) as $why => $v) {
	$r = efb_rt_ajax($D, array('nonce' => $N, 'period' => $v[0], 'stage' => $v[1]));
	t($why . ': 400, nothing stored', array($r[1], get_user_meta(1, 'efb_renew_notice')), array(400, ''));
}
$r = efb_rt_ajax($D, array('nonce' => $N, 'period' => $p, 'stage' => 't7'));
t('a good request: stored for this user', array($r[0], get_user_meta(1, 'efb_renew_notice'), get_user_meta(2, 'efb_renew_notice')), array(true, $p . ':t7', ''));

echo "--- efb_license_recheck ---\n";
$setup(5);
t('a bad nonce: 403', efb_rt_ajax($C, array('nonce' => 'x'))[1], 403);
$GLOBALS['efb_rt']['caps'] = array('Emsfb' => true);
t('an editor: 403', efb_rt_ajax($C, array('nonce' => $N))[1], 403);
$GLOBALS['efb_rt']['caps'] = array('manage_options' => true);
$setup(30);
$r = efb_rt_ajax($C, array('nonce' => $N));
t('no reminder on screen: nothing asked', array($r[0], $r[2]['renewed'], get_transient('emsfb_license_recheck')), array(true, false, false));

$setup(5);
$GLOBALS['efb_rt']['json'] = efb_rt_answer(5); // the server: still the same date
$r = efb_rt_ajax($C, array('nonce' => $N));
t('not renewed yet: renewed false, the "try again" words, still t7', array($r[2]['renewed'], $r[2]['m'], $r[2]['stage']), array(false, 'No renewal found yet. If you just renewed, try again in a few minutes.', 't7'));
$GLOBALS['efb_rt']['json'] = efb_rt_answer(370);
$r = efb_rt_ajax($C, array('nonce' => $N));
t('a second click within 5 minutes: throttled, the server not asked (the new date not seen)', array($r[2]['renewed'], !empty($r[2]['throttled']), RR::state()['stage']), array(false, true, 't7'));
$at($T0 + 301);
$r = efb_rt_ajax($C, array('nonce' => $N, 'expires_at' => '2099-01-01T00:00:00Z', 'renewed' => '1'));
t('5 minutes later, renewed on the server: renewed, "Thanks", no stage (the client\'s own values ignored)', array($r[2]['renewed'], $r[2]['m'], $r[2]['stage'], RR::state()['days_left']), array(true, 'Thanks — your renewal is confirmed.', null, 370));

$setup(5, array('auto_renew' => false));
$GLOBALS['efb_rt']['json'] = efb_rt_answer(5, array('auto_renew' => true));
t('switched to automatic renewal: renewed (nothing more to remind)', efb_rt_ajax($C, array('nonce' => $N))[2]['renewed'], true);

$setup(0.5); // the last day: t1 on screen
$GLOBALS['efb_rt']['json'] = efb_rt_answer(-0.5, array(), 'deactive'); // the server: it ended (the fake check sets emsfb_pro 0)
$r = efb_rt_ajax($C, array('nonce' => $N));
t('the server says deactive: t0, not renewed, and the one "paused" e-mail (one per address)', array($r[2]['renewed'], $r[2]['stage'], count($GLOBALS['efb_rt']['mails']), end($GLOBALS['efb_rt']['mails'])['state']), array(false, 't0', 2, 'renewPaused'));

$setup(5);
$GLOBALS['efb_rt']['locale'] = 'fa_IR';
$r = efb_rt_ajax($C, array('nonce' => $N));
t('fa_IR: nothing asked', array($r[2]['renewed'], $r[2]['stage'], get_transient('emsfb_license_recheck')), array(false, null, false));
$GLOBALS['efb_rt']['locale'] = 'en_US';

efb_rt_done();
