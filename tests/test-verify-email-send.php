<?php
/**
 * F02-E2: the code e-mail (spec §7.4–7.6) through the plugin's own template (EmsfbEmailHandler, state
 * otpCode), with wp_mail recorded:
 *  - the template's frame (title "Verification code", footer) around the message area; no button, no
 *    link, no href anywhere in the e-mail (the footer is plain text for this state too);
 *  - the code once, large, monospace, left to right; the lifetime in minutes, "ignore it", the site name;
 *    no value of the form and not even the address it goes to;
 *  - the subject: the default or the field's own with only [website_name] / [otp_code]; the code is in
 *    the subject only with code_in_subject; a custom text without [otp_code] falls back to the default;
 *    HTML in a custom text is shown as text;
 *  - send_email() is true only when wp_mail said yes ("n" / false = failure); the other states are
 *    unchanged (recovery keeps its button, newMessage its tracking button).
 *
 * Run: php tests/test-verify-email-send.php
 */
require __DIR__ . '/verify-test-lib.php';
use Emsfb\Verification as V;

if (!function_exists('is_rtl')) { function is_rtl() { return false; } }
if (!defined('EMSFB_PLUGIN_URL')) define('EMSFB_PLUGIN_URL', 'https://site.example/wp-content/plugins/easy-form-builder/');
require_once __DIR__ . '/../includes/class-email-handler.php';

vt_env();
$site = get_bloginfo('name');
$s = V::settings();
$field = vt_email('eml1', 'otp');
$html = V::email_html($field, '042917', $s['email']);
vt('message: the code once, as typed (leading zero kept)', substr_count($html, '042917'), 1);
vt('message: the code is monospace, spaced and left to right', (bool) preg_match('/<p dir="ltr" style="[^"]*monospace[^"]*letter-spacing:6px;direction:ltr[^"]*">042917<\/p>/', $html), true);
vt('message: lifetime in minutes (600 s → 10)', strpos($html, 'The code expires in 10 minutes.') !== false, true);
vt('message: "ignore it" and the site name', array(strpos($html, V::TEXTS['vrfEmailIgnore']) !== false, strpos($html, $site) !== false), array(true, true));
vt('message: no link at all', array(stripos($html, 'href'), stripos($html, '<a ')), array(false, false));

$h = new EmsfbEmailHandler();
$mail = (string) $h->email_template_efb(true, 'otpCode', $html, 'null', 'traking_link', (object) array('emailTemp' => ''));
vt('the whole e-mail (default template): the message inside, the code once', array(strpos($mail, '042917') !== false, substr_count($mail, '042917')), array(true, 1));
vt('the whole e-mail has no href (no button, no link, plain footer)', stripos($mail, 'href'), false);
vt('no tracking button ("View Messages") in a code e-mail', strpos($mail, 'View Messages'), false);
vt('titled "Verification code"', strpos($mail, 'Verification code') !== false, true);
$rec = (string) $h->email_template_efb(true, 'recovery', 'ali', 'https://site.example/reset', 'traking_link', (object) array('emailTemp' => ''));
vt('recovery is unchanged (keeps its button)', strpos($rec, 'Reset Password') !== false && stripos($rec, 'href') !== false, true);
$nm = (string) $h->email_template_efb(true, 'newMessage', 'TRK123', 'https://site.example/?track=1', 'traking_link', (object) array('emailTemp' => ''));
vt('a new-message e-mail still has its tracking button', strpos($nm, 'View Messages') !== false, true);

/* subject */
vt('subject: default with the site name, no code', V::email_subject($field, '042917', $s['email']), 'Your verification code for ' . $site);
vt('subject: code_in_subject puts the code first', V::email_subject($field, '042917', array_merge($s['email'], array('code_in_subject' => 1))), '042917 · Your verification code for ' . $site);
$own = vt_email('eml1', 'otp', array('email_otp_subject' => '[website_name] code [otp_code]'));
vt('subject: the field\'s own, [otp_code] empty unless code_in_subject', V::email_subject($own, '042917', $s['email']), $site . ' code');
/* custom body */
$cb = vt_email('eml1', 'otp', array('email_otp_body' => 'Hello from [website_name]@n#<b>Code:</b> [otp_code]@n#Thanks'));
$ch = V::email_html($cb, '555123', $s['email']);
vt('custom text: [website_name] filled, newlines kept, HTML shown as text, the code once in its own block', array(strpos($ch, 'Hello from ' . $site) !== false, strpos($ch, '&lt;b&gt;Code:&lt;/b&gt;') !== false, substr_count($ch, '555123'), strpos($ch, '<b>')), array(true, true, 1, false));
$nb = vt_email('eml1', 'otp', array('email_otp_body' => 'No code here'));
vt('custom text without [otp_code] → the default text (the code is never left out)', strpos(V::email_html($nb, '555123', $s['email']), V::TEXTS['vrfEmailBody']) !== false, true);
vt('plain-text part: the code and the lifetime', (bool) preg_match('/555123.*10 minutes/s', V::email_text($nb, '555123', $s['email'])), true);

/* send_email(): the result of wp_mail mapped to a bool; no value of the form, not the address in the body */
$GLOBALS['vt_mails'] = array();
vt('send_email: true when the mailer says true', V::send_email('ali@example.com', '042917', $field, $s['email']), true);
$m = end($GLOBALS['vt_mails']);
vt('… through send_email_state_new with state otpCode to that address only', array($m['state'], $m['to']), array('otpCode', 'ali@example.com'));
vt('… the address is not repeated in the body', strpos($m['cont'], 'ali@example.com'), false);
$GLOBALS['vt_mail_result'] = 'n';
vt('send_email: "n" (guard or wp_mail refused) → false', V::send_email('ali@example.com', '042917', $field, $s['email']), false);
$GLOBALS['vt_mail_result'] = false;
vt('send_email: false → false', V::send_email('ali@example.com', '042917', $field, $s['email']), false);
unset($GLOBALS['vt_mail_result']);

/* the handler's own Human Shield gate applies to otpCode (it is not an internal state) */
$src = file_get_contents(__DIR__ . '/../includes/class-email-handler.php');
vt('otpCode is not an internal state (efb_shield_allow_side_effect applies)', (bool) preg_match('/\$efb_shield_internal_states = array\([^)]*otpCode/', $src), false);

vt_done();
