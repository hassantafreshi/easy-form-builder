<?php
/**
 * F21 A: the `license` field of the licence answer (contract v1) and what is stored from it.
 *
 *  - parse_license(): an older server (no field), lifetime (expires_at null), auto_renew, bad dates (not UTC, not
 *    ISO, out of range, impossible days), unknown versions and types, renew links (http, a foreign host, credentials,
 *    a port, the activation code or its secret inside, a look-alike host), lapse_effect values;
 *  - store(): keeps the field with the server clock offset and the period; an older server or a garbled field drops
 *    it; an answer the plugin made itself (fa_IR offline, a probe that never reached the server) changes nothing;
 *    "not a verdict" keeps it; notExists drops it; never touches emsfb_pro;
 *  - the renew link: the server's token link, else the fallback that never holds the activation code.
 *
 * Run: php tests/test-renewal-license-parse.php
 */
require __DIR__ . '/renewal-test-lib.php';
use Emsfb\Renewal_Reminder as RR;

$now = $GLOBALS['efb_rt']['now'];
$iso = function ($ts) { return gmdate('Y-m-d\TH:i:s\Z', $ts); };
$base = function (array $over = array()) use ($now, $iso) {
	$l = array('v' => 1, 'expires_at' => $iso($now + 7 * 86400), 'server_time' => $iso($now), 'auto_renew' => false, 'renew_url' => 'https://whitestudio.team/checkout?renew=tok_abc', 'lapse_effect' => 'pause_pro');
	foreach ($over as $k => $v) { if ($v === '__unset__') unset($l[$k]); else $l[$k] = $v; }
	return json_decode(json_encode(array('r' => true, 'state' => 'active', 'license' => $l)));
};
$P = function (array $over = array(), $code = EFB_RT_CODE) use ($base) { return RR::parse_license($base($over), $code); };

echo "--- parse_license: what is accepted ---\n";
$p = $P();
t('a well-formed field: expiry, server time, auto_renew, link, effect', array($p['expires_at'], $p['server_time'], $p['auto_renew'], $p['renew_url'], $p['lapse_effect']), array($now + 7 * 86400, $now, false, 'https://whitestudio.team/checkout?renew=tok_abc', 'pause_pro'));
t('an older server: no field → null', RR::parse_license(json_decode('{"r":true,"state":"active","pakcage":1}'), EFB_RT_CODE), null);
t('lifetime: expires_at null → kept as 0 (never ends)', $P(array('expires_at' => null))['expires_at'], 0);
t('auto_renew true is kept', $P(array('auto_renew' => true))['auto_renew'], true);
t('auto_renew missing → false', $P(array('auto_renew' => '__unset__'))['auto_renew'], false);
t('lapse_effect missing → pause_pro (the global channel)', $P(array('lapse_effect' => '__unset__'))['lapse_effect'], 'pause_pro');
t('lapse_effect updates_only is kept', $P(array('lapse_effect' => 'updates_only'))['lapse_effect'], 'updates_only');
t('fractions of a second and +00:00 are UTC too', array($P(array('expires_at' => gmdate('Y-m-d\TH:i:s', $now + 86400) . '.250Z')) !== null, $P(array('server_time' => gmdate('Y-m-d\TH:i:s', $now) . '+00:00'))['server_time']), array(true, $now));
t('array answers (json_decode assoc) work too', RR::parse_license(json_decode(json_encode($base()), true), EFB_RT_CODE)['expires_at'], $now + 7 * 86400);
t('renew_url missing → empty (the fallback is used later)', $P(array('renew_url' => '__unset__'))['renew_url'], '');
t('a subdomain-less www. host is accepted', $P(array('renew_url' => 'https://www.whitestudio.team/renew?t=x'))['renew_url'], 'https://www.whitestudio.team/renew?t=x');
t('easyformbuilder.ir is an allowed host', $P(array('renew_url' => 'https://easyformbuilder.ir/renew?t=x'))['renew_url'], 'https://easyformbuilder.ir/renew?t=x');

echo "--- parse_license: garbled → null (nothing shown, nothing sent) ---\n";
$bad = array(
	'version 2' => array('v' => 2),
	'version "1" (a string)' => array('v' => '1'),
	'no version' => array('v' => '__unset__'),
	'no server_time' => array('server_time' => '__unset__'),
	'server_time not ISO' => array('server_time' => 'yesterday'),
	'expires_at missing' => array('expires_at' => '__unset__'),
	'expires_at a number' => array('expires_at' => $now + 86400),
	'expires_at local time (no Z)' => array('expires_at' => gmdate('Y-m-d\TH:i:s', $now + 86400)),
	'expires_at in another zone (+03:30)' => array('expires_at' => gmdate('Y-m-d\TH:i:s', $now + 86400) . '+03:30'),
	'expires_at Feb 30' => array('expires_at' => '2027-02-30T00:00:00Z'),
	'expires_at hour 24' => array('expires_at' => '2026-11-21T24:00:00Z'),
	'expires_at more than 5 years ahead' => array('expires_at' => $iso($now + 6 * 365 * 86400)),
	'expires_at more than 400 days ago' => array('expires_at' => $iso($now - 401 * 86400)),
	'expires_at year 1970' => array('expires_at' => '1970-01-01T00:00:00Z'),
	'expires_at with a newline after the Z' => array('expires_at' => $iso($now + 86400) . "\n"),
	'auto_renew "yes"' => array('auto_renew' => 'yes'),
	'auto_renew 1' => array('auto_renew' => 1),
	'lapse_effect unknown' => array('lapse_effect' => 'delete_everything'),
	'license a string' => null,
);
foreach ($bad as $why => $over) {
	if ($over === null) { t('garbled: ' . $why, RR::parse_license(json_decode('{"r":true,"state":"active","license":"2026-11-21"}'), EFB_RT_CODE), null); continue; }
	t('garbled: ' . $why, $P($over), null);
}
t('400 days ago exactly is still a date (a lapsed licence)', $P(array('expires_at' => $iso($now - 400 * 86400)))['expires_at'], $now - 400 * 86400);

echo "--- renew links: anything unsafe → '' ---\n";
$links = array(
	'http (not https)' => 'http://whitestudio.team/checkout?renew=tok',
	'a foreign host' => 'https://evil.example/checkout?renew=tok',
	'a look-alike host' => 'https://whitestudio.team.evil.example/x',
	'a host that ends like ours' => 'https://notwhitestudio.team/x',
	'credentials in the link' => 'https://user:pw@whitestudio.team/x',
	'another port' => 'https://whitestudio.team:8443/x',
	'javascript:' => 'javascript:alert(1)',
	'spaces / quotes' => 'https://whitestudio.team/x?a="b"',
	'the full activation code' => 'https://whitestudio.team/checkout?renew=' . rawurlencode(EFB_RT_CODE),
	'the code, URL-encoded twice' => 'https://whitestudio.team/checkout?renew=' . rawurlencode(rawurlencode(EFB_RT_CODE)),
	'the secret half of the code' => 'https://whitestudio.team/checkout?k=E2EPLACEHOLDER0000',
	'longer than 2048' => 'https://whitestudio.team/?' . str_repeat('a', 2050),
);
foreach ($links as $why => $u) t('renew_url: ' . $why, $P(array('renew_url' => $u))['renew_url'], '');
t('a link that is not a string', $P(array('renew_url' => array('https://whitestudio.team')))['renew_url'], '');

echo "--- store() ---\n";
efb_rt_reset();
update_option('emsfb_pro', 1);
update_option('emsfb_pro_activeCode', EFB_RT_CODE);
$i = RR::store(efb_rt_answer(7, array(), 'active', 120), EFB_RT_CODE);
t('stored: expiry, offset from the server clock (+120 s), when, period of code + expiry', array($i['expires_at'], $i['offset'], $i['checked_at'], $i['period'], get_option('emsfb_license_info')['period']), array($now + 120 + 7 * 86400, 120, $now, RR::period(EFB_RT_CODE, $now + 120 + 7 * 86400), $i['period']));
t('the period is 16 hex characters', (bool) preg_match('/^[a-f0-9]{16}$/', $i['period']), true);
t('emsfb_pro is not touched by store()', get_option('emsfb_pro'), 1);
RR::store((object) array('r' => true, 'state' => 'active', 'pakcage' => 1, 'local' => true), EFB_RT_CODE);
t('an answer the plugin made itself (local) keeps the field', is_array(get_option('emsfb_license_info')), true);
RR::store((object) array('r' => false, 'state' => 'maintenance'), EFB_RT_CODE);
t('not a verdict (unknown state) keeps the field', is_array(get_option('emsfb_license_info')), true);
RR::store(json_decode('{"r":true,"state":"active","pakcage":1}'), EFB_RT_CODE);
t('an older server (no field) drops it', get_option('emsfb_license_info', 'gone'), 'gone');
RR::store(efb_rt_answer(7), EFB_RT_CODE);
RR::store(efb_rt_answer(7, array('v' => 9)), EFB_RT_CODE);
t('a garbled field drops it', get_option('emsfb_license_info', 'gone'), 'gone');
RR::store(efb_rt_answer(7), EFB_RT_CODE);
RR::store((object) array('r' => false, 'state' => 'notExists'), EFB_RT_CODE);
t('notExists drops it', get_option('emsfb_license_info', 'gone'), 'gone');
$i = RR::store(efb_rt_answer(-1, array(), 'deactive'), EFB_RT_CODE);
t('deactive with a field: kept with last_state deactive (the "paused" words need the date)', array($i['last_state'], $i['expires_at']), array('deactive', $now - 86400));
$i = RR::store((object) array('r' => true, 'state' => 'new', 'key' => md5('site.example') . '@NEWPLACEHOLDER000', 'license' => efb_rt_answer(30)->license), EFB_RT_CODE);
t('state new: the period belongs to the new code', $i['period'], RR::period(md5('site.example') . '@NEWPLACEHOLDER000', $now + 30 * 86400));
RR::store(efb_rt_answer(7, array('renew_url' => 'https://whitestudio.team/checkout?renew=' . EFB_RT_CODE)), EFB_RT_CODE);
t('a link with the code is never stored', get_option('emsfb_license_info')['renew_url'], '');

echo "--- the renew link of the buttons ---\n";
t('the fallback holds the public half (md5 of the domain), never the code', array(RR::renew_url(), RR::holds_code(RR::renew_url(), EFB_RT_CODE)), array('https://whitestudio.team/checkout?renew=site&d=' . md5('site.example'), false));
RR::store(efb_rt_answer(7), EFB_RT_CODE);
t('with the server\'s token link: that link', RR::renew_url(), 'https://whitestudio.team/checkout?renew=tok_e2e_short_lived');
update_option('emsfb_pro_activeCode', 'not-a-code');
t('a code without the md5 half: the checkout without d=', RR::fallback_renew_url(), 'https://whitestudio.team/checkout?renew=site');
t('another brand (fa_IR e-mail) keeps its own host', RR::fallback_renew_url('https://easyformbuilder.ir/'), 'https://easyformbuilder.ir/checkout?renew=site');

echo "--- forget() ---\n";
update_option('emsfb_renew_reminder', array('expires_at' => 1));
update_option('emsfb_license_lapse_cause', 'expired');
RR::forget();
t('the code removed: field, sent marks and the cause are gone', array(get_option('emsfb_license_info', 0), get_option('emsfb_renew_reminder', 0), get_option('emsfb_license_lapse_cause', 0)), array(0, 0, 0));

efb_rt_done();
