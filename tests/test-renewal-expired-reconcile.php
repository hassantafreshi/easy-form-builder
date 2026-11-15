<?php
/**
 * F21 E: the card of a site whose Pro is off, and the spec's bugs 1 and 2.
 *
 *  - Bug 1: the words follow why Pro is off: the server said the subscription ended → "Pro features are paused on this
 *    site" with the date in <bdi> and "Check again" (t0); ended but no date from the server → the same words without a
 *    date; a code of another domain or one the server does not know → "This activation code doesn't match this site"
 *    and a link to Settings (no "expired", no renew); a long outage → "Open Settings and press Save"; never "expired"
 *    for a valid code;
 *  - Bug 2: no link anywhere in core carries the activation code; the card's link is escaped, opens a new tab with
 *    rel="noopener noreferrer" and says so to screen readers;
 *  - the card goes into the page as a JSON string (an apostrophe or "</script>" in a translation cannot break it);
 *  - the plugin records the cause at each verdict (functions.php wiring).
 *
 * Run: php tests/test-renewal-expired-reconcile.php
 */
require __DIR__ . '/renewal-test-lib.php';
use Emsfb\Renewal_Reminder as RR;

$T0 = $GLOBALS['efb_rt']['now'];
$ROOT = dirname(__DIR__);
$card = function () { return RR::expired_card_html(); };
$setup = function () use ($T0) { efb_rt_reset(); $GLOBALS['efb_rt']['now'] = $T0; update_option('timezone_string', 'UTC'); update_option('date_format', 'F j, Y'); update_option('emsfb_pro', 0); update_option('emsfb_pro_activeCode', EFB_RT_CODE); };

echo "--- the words follow the cause (Bug 1) ---\n";
$setup();
efb_rt_pro(-2, array(), 'deactive');
RR::set_cause('expired');
$h = $card();
$date = RR::state()['date'];
t('the server said it ended (t0): paused, the date in <bdi>, Renew Pro and Check again', array(strpos($h, '>Pro features are paused on this site</strong>') !== false, strpos($h, 'ended on <bdi>' . $date . '</bdi>. Your forms, entries and settings are all still here.') !== false, strpos($h, 'data-efb-renew="check"') !== false, strpos($h, '>Renew Pro<span class="screen-reader-text"> (opens in a new tab)</span></a>') !== false), array(true, true, true, true));
t('… the server\'s renew link, new tab, rel noopener noreferrer, a live region', array(strpos($h, 'href="https://whitestudio.team/checkout?renew=tok_e2e_short_lived" target="_blank" rel="noopener noreferrer"') !== false, strpos($h, 'role="status" aria-live="polite"') !== false), array(true, true));
t('… no "expired", no "activation code"', array(stripos(strip_tags($h), 'expired'), stripos(strip_tags($h), 'activation code')), array(false, false));
$setup();
RR::set_cause('expired');
$h = $card();
t('ended, but no date from the server (an older server): the same words without a date, no Check again', array(strpos($h, 'Your Easy Form Builder Pro subscription has ended.') !== false, strpos($h, '<bdi>'), strpos($h, 'data-efb-renew'), strpos($h, 'renew=site&#038;d=' . md5('site.example')) !== false), array(true, false, false, true));
foreach (array('domain' => 'a code made for another domain', 'not_found' => 'a code the server does not know') as $c => $why) {
	$setup(); RR::set_cause($c);
	$h = $card();
	t("{$why}: \"doesn't match this site\", a link to Settings in the same tab, no renew", array(strpos($h, 'This activation code doesn&#039;t match this site') !== false, strpos($h, 'href="https://site.example/wp-admin/admin.php?page=Emsfb&#038;state=setting"') !== false, strpos($h, 'target="_blank"'), stripos(strip_tags($h), 'renew'), stripos(strip_tags($h), 'expired')), array(true, true, false, false, false));
}
$setup(); RR::set_cause('outage');
$h = $card();
t('a long outage: paused, "press Save to check again", Settings', array(strpos($h, 'could not confirm your Pro licence for a while') !== false, strpos($h, '>Open Settings</a>') !== false, stripos(strip_tags($h), 'expired')), array(true, true, false));
$setup();
$h = $card();
t('no cause recorded (a site from before): paused, never "expired"', array(strpos($h, 'Pro features are paused on this site') !== false, stripos(strip_tags($h), 'expired')), array(true, false));
$setup(); RR::set_cause('bogus');
t('an unknown cause value is ignored', RR::lapse_cause(), '');
t('the card carries the efb marks (the notice cleaner keeps it) and data-efb-lapse', (bool) preg_match('/^<div class="efb [^"]*efb-renew-card" data-efb-lapse="paused" role="region" aria-label="Pro features are paused on this site">/', $card()), true);

echo "--- the card in a JS string ---\n";
$setup(); RR::set_cause('domain');
$js = 'const noti_exp_efb=' . json_encode($card(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';';
t('JSON-encoded: no raw apostrophe, quote or less-than sign left', array(strpos($js, "'"), substr_count($js, '"'), strpos($js, '<')), array(false, 2, false));
$tmp = tempnam(sys_get_temp_dir(), 'efbjs');
file_put_contents($tmp, $js . 'process.stdout.write(noti_exp_efb.indexOf("doesn&#039;t match")>0?"ok":"bad");');
t('node runs it and gets the HTML back', trim((string) shell_exec('node ' . escapeshellarg($tmp) . ' 2>&1')), 'ok');
@unlink($tmp);
foreach (array('class-Emsfb-panel.php', 'class-Emsfb-create.php', 'class-Emsfb-addon.php') as $f) {
	$src = file_get_contents($ROOT . '/includes/admin/' . $f);
	t("{$f}: the card is printed with wp_json_encode (JSON_HEX_TAG | JSON_HEX_APOS)", (bool) preg_match("/const noti_exp_efb=' \\. wp_json_encode\\(\\\$efbFunction->noti_expire_efb\\(\\), JSON_HEX_TAG \\| JSON_HEX_AMP \\| JSON_HEX_APOS \\| JSON_HEX_QUOT\\)/", $src), true);
}

echo "--- no link carries the activation code (Bug 2) ---\n";
$hits = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT . '/includes', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
	if (!preg_match('/\.(php|js)$/', $f->getFilename()) || preg_match('/\.min\.js$/', $f->getFilename())) continue;
	foreach (file($f->getPathname()) as $n => $line) {
		if (preg_match('/renew=/', $line) && preg_match('/activeCode|\$code\b|\$ac\b/', $line)) $hits[] = substr($f->getPathname(), strlen($ROOT) + 1) . ':' . ($n + 1);
	}
}
t('no "renew=" built from the activation code anywhere in includes/', $hits, array());
$fn = file_get_contents($ROOT . '/includes/functions.php');
t('the licence-suspended e-mail and the add-on download use the fallback link', array(substr_count($fn, 'Renewal_Reminder::fallback_renew_url('), strpos(file_get_contents($ROOT . '/includes/admin/class-Emsfb-admin.php'), 'Renewal_Reminder::fallback_renew_url($domain)') !== false), array(2, true));

echo "--- the cause is recorded at each verdict (functions.php) ---\n";
$body = function ($name) use ($fn) { $i = strpos($fn, 'function ' . $name . '('); return substr($fn, $i, 6000); };
$u = $body('update_pro_status_efb');
t('update_pro_status_efb: new/active → cleared, deactive → expired, notExists → not_found', array((bool) preg_match('/\$state=="new".*?license_lapse_cause_efb\(\'\'\).*?\$state=="active".*?license_lapse_cause_efb\(\'\'\).*?\$state=="deactive".*?license_lapse_cause_efb\(\'expired\'\).*?invalid_key.*?license_lapse_cause_efb\(\'not_found\'\)/s', $u)), array(true));
t('handle_license_transport_failure_efb: past the grace → outage', (bool) preg_match('/notify_license_suspended_efb\(.*?update_option\(\'emsfb_pro\', 0\);\s*\$this->license_lapse_cause_efb\(\'outage\'\);/s', $body('handle_license_transport_failure_efb')), true);
t('is_efb_pro: a code of another domain → domain', (bool) preg_match('/Local code does not match this domain.*?update_option\(\'emsfb_pro\', 0\);\s*\$this->license_lapse_cause_efb\(\'domain\'\);/s', $body('is_efb_pro')), true);
t('noti_expire_efb() hands over to Renewal_Reminder::expired_card_html()', strpos($body('noti_expire_efb'), 'return \\Emsfb\\Renewal_Reminder::expired_card_html();') !== false, true);
t('render_pro_gate_efb(): the same words, no "has expired!" title left in core', array(strpos($body('render_pro_gate_efb'), 'Renewal_Reminder::lapse_texts(') !== false, (bool) preg_match("/__\(\s*'Your activation code has expired!'/", $fn)), array(true, false));

efb_rt_done();
