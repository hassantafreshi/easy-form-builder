<?php
/**
 * F21 D: the e-mail.
 *
 *  - one self-contained document: <html lang dir>, colour scheme (light dark), a hidden preheader, one column
 *    (max-width 560px, width 100%), inline styles only (no <style>, no class), a bulletproof button (a table cell with
 *    its colour and a border of the same colour), contrast of text and button ≥ 4.5:1;
 *  - nothing external: no <img>, no font, no tracking, no UTM; the only http(s) address is the renew link (once in
 *    the HTML; in the text version as well); the host is named in the text so a phishing copy can be spotted;
 *  - right to left for ar and he (and fa), left to right for en and de; text-align start; list padding on the start
 *    side; site name, date and host in <bdi>; every paragraph unicode-bidi: plaintext;
 *  - words: the spec's §11.2 / §11.3 copy, at most 120 words without the footer; updates_only words; the subject
 *    has no line break even with a hostile site name, which is escaped in the HTML;
 *  - the text version (AltBody) and the "paused" e-mail.
 *
 * Run: php tests/test-renewal-email-template.php
 */
require __DIR__ . '/renewal-test-lib.php';
use Emsfb\Renewal_Reminder as RR;

$T0 = $GLOBALS['efb_rt']['now'];
efb_rt_reset();
update_option('timezone_string', 'UTC'); update_option('date_format', 'F j, Y');
update_option('blogname', 'Example Site');
efb_rt_pro(7);
$st = RR::state('en_US');
$H = RR::email_html('t7', $st, 'en_US');
$X = RR::email_text('t7', $st, 'en_US');

$words = function ($html) {
	$body = preg_replace('#<div style="display:none.*?</div>#s', '', $html); // the preheader is not read
	$body = preg_replace('#<title>.*?</title>#s', '', $body);
	$body = preg_replace('#<tr><td dir="[a-z]+" style="padding:16px 24px 24px.*$#s', '', $body); // the footer row
	$text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8')));
	return count(preg_split('/\s+/u', $text));
};
$hex = function ($h) { $h = ltrim($h, '#'); return array(hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255); };
$lum = function ($h) use ($hex) { $l = 0; foreach (array_combine(array(0.2126, 0.7152, 0.0722), $hex($h)) as $w => $c) $l += $w * ($c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4)); return $l; };
$ratio = function ($a, $b) use ($lum) { $x = $lum($a); $y = $lum($b); return (max($x, $y) + 0.05) / (min($x, $y) + 0.05); };

echo "--- the document ---\n";
t('<html lang="en-US" dir="ltr">, charset, viewport, colour scheme light dark (both metas)', array(strpos($H, '<!DOCTYPE html><html lang="en-US" dir="ltr">') === 0, strpos($H, '<meta charset="utf-8">') !== false, strpos($H, '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">') !== false), array(true, true, true));
t('a hidden preheader with the spec\'s words', (bool) preg_match('~<div style="display:none;max-height:0;[^"]*overflow:hidden;[^"]*">Renew before then and your visitors won&#039;t notice a thing\.</div>~', $H), true);
t('one column: max-width 560px, width 100%', strpos($H, 'max-width:560px;width:100%') !== false, true);
t('inline styles only: no <style>, no class attribute, no <link>', array(stripos($H, '<style'), stripos($H, ' class='), stripos($H, '<link')), array(false, false, false));
t('bulletproof button: a cell with bgcolor, background and a border of the same colour, the link white', (bool) preg_match('#<td align="center" bgcolor="\#1d4ed8" style="background-color:\#1d4ed8;border:2px solid \#1d4ed8;[^"]*"><a href="https://whitestudio\.team/checkout\?renew=tok_e2e_short_lived" target="_blank" rel="noopener noreferrer" style="display:inline-block;[^"]*color:\#ffffff;[^"]*">Renew Pro</a></td>#', $H), true);
t('contrast ≥ 4.5:1: body text, the footer, the button', array($ratio('#1f2937', '#ffffff') >= 4.5, $ratio('#4b5563', '#ffffff') >= 4.5, $ratio('#374151', '#ffffff') >= 4.5, $ratio('#ffffff', '#1d4ed8') >= 4.5), array(true, true, true, true));

echo "--- nothing external ---\n";
preg_match_all('#https?://[^\s"<>]+#i', $H, $m);
t('the only address in the HTML is the renew link, once', $m[0], array('https://whitestudio.team/checkout?renew=tok_e2e_short_lived'));
t('no image, no font, no tracking, no UTM', array(stripos($H, '<img'), stripos($H, '@font-face'), stripos($H, 'fonts.googleapis'), stripos($H, 'utm_'), stripos($H, 'pixel')), array(false, false, false, false, false));
t('the host is named in the words (anti-phishing), in <bdi>', strpos($H, 'The button opens <bdi>whitestudio.team</bdi>. We&#039;ll never ask for your password by email.') !== false, true);
t('the activation code is nowhere (HTML, text)', array(strpos($H, 'E2EPLACEHOLDER0000'), strpos($X, 'E2EPLACEHOLDER0000')), array(false, false));

echo "--- words ---\n";
t('§11.2: the opening line with name and date in <strong><bdi>, "7 days from now"', strpos($H, 'Easy Form Builder Pro on <strong><bdi>Example Site</bdi></strong> ends on <strong><bdi>' . $st['date'] . '</bdi></strong> — 7 days from now.') !== false, true);
foreach (array('Hi,', 'Renew before then and nothing changes: your forms keep working exactly as they do today.', 'If it lapses:', 'Your forms, entries and settings stay safe. Nothing is deleted.', 'Pro fields on your live forms will show as unavailable to visitors.', 'Online payments through your forms will pause.', 'Pro add-ons stop getting updates until you renew.') as $w) {
	t('says: ' . $w, strpos($H, esc_html($w)) !== false, true);
}
t('the footer: why, once for this renewal, how to stop', strpos($H, 'You&#039;re getting this because you manage <bdi>Example Site</bdi>, which uses Easy Form Builder Pro. It was sent by your own website, once for this renewal. To stop these reminders, renew, or turn off &quot;Email me before my Pro subscription ends&quot; in Easy Form Builder › Settings.') !== false, true);
$n = $words($H);
t("at most 120 words without the footer ({$n})", $n <= 120 && $n >= 60, true);
t('no exclamation mark, no capitals shouting', array(strpos(strip_tags($H), '!'), (bool) preg_match('/\b[A-Z]{4,}\b/', strip_tags(preg_replace('#<title>.*?</title>#', '', $H)))), array(false, false));
$st1 = $st; $st1['days_left'] = 1;
t('one day: "1 day from now" (singular)', strpos(RR::email_html('t7', $st1, 'en_US'), '— 1 day from now.') !== false, true);
efb_rt_pro(5, array('lapse_effect' => 'updates_only'));
$su = RR::state('en_US');
$HU = RR::email_html('t7', $su, 'en_US');
t('updates_only: the one soft sentence instead of the list', array(strpos($HU, 'Your forms keep working either way. Renewing keeps updates, new add-ons and support coming.') !== false, strpos($HU, 'If it lapses:'), strpos($HU, '<ul')), array(true, false, false));

echo "--- the subject ---\n";
t('subject A: site and date', RR::subject('t7', $st), 'Easy Form Builder Pro on Example Site ends on ' . $st['date']);
update_option('blogname', "Evil\r\nBcc: x@evil.example <script>alert(1)</script> &amp; Co");
$sh = RR::subject('t7', $st);
$Hh = RR::email_html('t7', $st, 'en_US');
t('a hostile site name: no line break in the subject', array(strpos($sh, "\r"), strpos($sh, "\n")), array(false, false));
t('… escaped in the HTML (no <script>), entities decoded once (& Co)', array(strpos($Hh, '<script>'), strpos($Hh, '&lt;script&gt;alert(1)&lt;/script&gt; &amp; Co') !== false), array(false, true));
update_option('blogname', 'Example Site');

echo "--- right to left ---\n";
foreach (array('ar' => 'rtl', 'he_IL' => 'rtl', 'fa_IR' => 'rtl', 'en_US' => 'ltr', 'de_DE' => 'ltr') as $loc => $dir) {
	$h = RR::email_html('t7', $st, $loc);
	$start = $dir === 'rtl' ? 'right' : 'left';
	t("{$loc}: dir {$dir} on html, body and the cells; text-align {$start}; list padding on the {$start}", array(strpos($h, 'lang="' . str_replace('_', '-', $loc) . '" dir="' . $dir . '"') !== false, strpos($h, '<body dir="' . $dir . '"') !== false, substr_count($h, '<td dir="' . $dir . '"'), strpos($h, 'text-align:' . $start . ';') !== false, strpos($h, 'padding-' . $start . ':22px') !== false), array(true, true, 2, true, true));
}
t('every paragraph and list item runs unicode-bidi: plaintext', substr_count($H, 'unicode-bidi:plaintext') >= 9, true);
$GLOBALS['efb_rt']['locale'] = 'ar';
$sa = RR::state('ar');
t('ar: the date in Arabic-Indic digits, the day count too', array((bool) preg_match('/[٠-٩]/u', $sa['date']), strpos(RR::email_html('t7', $sa, 'ar'), '٥ days from now') !== false), array(true, true));
$GLOBALS['efb_rt']['locale'] = 'en_US';

echo "--- the text version ---\n";
$want = "Hi,\n\nEasy Form Builder Pro on Example Site ends on {$st['date']} — 7 days from now.\n\nRenew before then and nothing changes: your forms keep working exactly as they do today.\n\nIf it lapses:\n- Your forms, entries and settings stay safe. Nothing is deleted.\n- Pro fields on your live forms will show as unavailable to visitors.\n- Online payments through your forms will pause.\n- Pro add-ons stop getting updates until you renew.\n\nRenew Pro: https://whitestudio.team/checkout?renew=tok_e2e_short_lived\n(The button opens whitestudio.team. We'll never ask for your password by email.)\n\n--\nYou're getting this because you manage Example Site, which uses Easy Form Builder Pro. It was sent by your own website, once for this renewal. To stop these reminders, renew, or turn off \"Email me before my Pro subscription ends\" in Easy Form Builder › Settings.\n";
t('the spec\'s plain text, with the link written out', $X, $want);

echo "--- after the end (t0) ---\n";
efb_rt_pro(-1, array(), 'deactive');
$s0 = RR::state('en_US');
$H0 = RR::email_html('t0', $s0, 'en_US');
t('subject "Pro features are paused on Example Site"', RR::subject('t0', $s0), 'Pro features are paused on Example Site');
t('§11.3 words, the date in <bdi>, the button and the footer; no list, no "Hi,"', array(strpos($H0, 'Easy Form Builder Pro on <strong><bdi>Example Site</bdi></strong> ended on <strong><bdi>' . $s0['date'] . '</bdi></strong>, so Pro features are paused for now.') !== false, strpos($H0, 'nothing was deleted') !== false, strpos($H0, '>Renew Pro</a>') !== false, strpos($H0, 'once for this renewal') !== false, strpos($H0, '<ul'), strpos($H0, 'Hi,')), array(true, true, true, true, false, false));
$n0 = $words($H0);
t("the paused e-mail is short too ({$n0} words)", $n0 <= 120, true);

efb_rt_done();
