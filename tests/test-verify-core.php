<?php
/**
 * F02-A: the core of Emsfb\Verification (spec §5, §8): codes, hashes, keys, tokens, destinations,
 * countries, the field keys' save rule and the settings' ranges.
 *
 * Run: php tests/test-verify-core.php
 */
require __DIR__ . '/verify-test-lib.php';
use Emsfb\Verification as V;

vt_env();

/* ---------------------------------------------------------------- codes */
$codes = array();
for ($i = 0; $i < 3000; $i++) $codes[] = V::make_code(6);
vt('make_code: 3000 codes are all exactly 6 ASCII digits', count(array_filter($codes, function ($c) { return is_string($c) && preg_match('/^[0-9]{6}$/', $c); })), 3000);
vt('make_code: leading zeros are kept (some codes start with 0)', count(array_filter($codes, function ($c) { return $c[0] === '0'; })) > 0, true);
vt('make_code: not constant (CSPRNG)', count(array_unique($codes)) > 2900, true);
vt('make_code(8) has 8 digits', strlen(V::make_code(8)), 8);
vt('make_code clamps silly lengths (2 → 4)', strlen(V::make_code(2)), 4);
vt('hash_code is an HMAC bound to the challenge (other challenge → other hash)', V::hash_code('a', '123456') !== V::hash_code('b', '123456'), true);
vt('hash_code is deterministic', V::hash_code('a', '123456'), V::hash_code('a', '123456'));
vt('hash_code never contains the code', strpos(V::hash_code('a', '123456'), '123456'), false);
vt('keys differ per purpose', V::key('code') !== V::key('token') && V::key('token') !== V::key('dest'), true);
vt('the token key is not the nonce salt (no swap with Form_Runtime tokens)', V::key('token') !== wp_salt('nonce'), true);

/* ---------------------------------------------------------------- digits */
vt('Persian digits → ASCII', V::digits_only('۱۲۳۴۵۶'), '123456');
vt('Arabic-Indic digits → ASCII', V::digits_only('٦٥٤٣٢١'), '654321');
vt('a pasted sentence keeps only the digits', V::digits_only('کد شما: ۱۲۳ ۴۵۶'), '123456');

/* ---------------------------------------------------------------- destinations */
vt('phone: E.164 with spaces', V::normalize_destination('sms', '+98 912 123 4567'), '+989121234567');
vt('phone: Persian digits and direction marks', V::normalize_destination('sms', "\u{200E}+۹۸۹۱۲۱۲۳۴۵۶۷\u{200F}"), '+989121234567');
vt('phone: 00 prefix → +', V::normalize_destination('sms', '00989121234567'), '+989121234567');
vt('phone: dashes and brackets', V::normalize_destination('sms', '+1 (415) 555-0123'), '+14155550123');
vt('phone: a national number without + is refused', V::normalize_destination('sms', '09121234567'), null);
vt('phone: too short', V::normalize_destination('sms', '+98912'), null);
vt('phone: too long', V::normalize_destination('sms', '+9891212345678901'), null);
vt('phone: leading 0 country code refused', V::normalize_destination('sms', '+0912345678'), null);
vt('phone: arrays are refused', V::normalize_destination('sms', array('+989121234567')), null);
vt('email: lower case, trimmed', V::normalize_destination('email', '  Ali.Reza@Example.COM '), 'ali.reza@example.com');
vt('email: fullwidth @ and Persian digits', V::normalize_destination('email', "user۱\u{FF20}example.com"), 'user1@example.com');
vt('email: +tag is kept (another address)', V::normalize_destination('email', 'a+news@example.com'), 'a+news@example.com');
vt('email: not an address', V::normalize_destination('email', 'nope'), null);
vt('dest_hash: same normalized value, same hash', V::dest_hash('email', 'a@example.com'), V::dest_hash('email', V::normalize_destination('email', 'A@Example.com')));
vt('dest_hash: the channel is part of it', V::dest_hash('email', 'x') !== V::dest_hash('sms', 'x'), true);
vt('dest_hash never contains the destination', strpos(V::dest_hash('sms', '+989121234567'), '9121234567'), false);
vt('mask of a mobile', V::mask('sms', '+989121234567'), '+98 912 ***-4567');
vt('mask of an e-mail', V::mask('email', 'mahdi@example.com'), 'ma***@example.com');
vt('mask of a short local part', V::mask('email', 'ab@example.com'), 'a***@example.com');

/* ---------------------------------------------------------------- countries */
vt('dial_of +98…', V::dial_of('+989121234567'), '98');
vt('dial_of +971… (longest match, not +97)', V::dial_of('+971501234567'), '971');
vt('dial_of +1…', V::dial_of('+14155550123'), '1');
vt('Iran allowed for [ir]', V::country_allowed('+989121234567', array('ir')), true);
vt('Iran refused for [de]', V::country_allowed('+989121234567', array('de')), false);
vt('an empty list sends to nobody (fail-closed)', V::country_allowed('+989121234567', array()), false);
vt('US mainland allowed for [us]', V::country_allowed('+14155550123', array('us')), true);
vt('Jamaica (+1 876) refused even for [us] (Caribbean toll fraud)', V::country_allowed('+18765550123', array('us')), false);
vt('Dominican Republic +1 809 refused', V::country_allowed('+18095550123', array('us', 'ca')), false);
add_filter('efb_verify_nanp_blocked_area_codes', function ($c) { return array_values(array_diff($c, array('876'))); });
vt('the NANP list is filterable', V::country_allowed('+18765550123', array('us')), true);
$GLOBALS['efb_filters'] = array();
$s = V::clamp(array('sms' => array('allowed_countries' => array('ir', 'ae'))));
vt('field list ∩ site list (never wider)', V::field_countries(array('otp_countries' => array('ir', 'de')), $s), array('ir'));
vt('no field list: the display list c_c ∩ site', V::field_countries(array('c_c' => array('ae', 'fr')), $s), array('ae'));
vt('nothing on the field: the site list', V::field_countries(array(), $s), array('ir', 'ae'));
$GLOBALS['efb_locale'] = 'fa_IR';
vt('default countries follow the site language (fa_IR → ir)', V::default_countries(), array('ir'));
$GLOBALS['efb_locale'] = 'en_US';
vt('en_US → us', V::default_countries(), array('us'));

/* ---------------------------------------------------------------- tokens */
$tok = V::token_issue(array('cid' => str_repeat('a', 32), 'fid' => 5, 'fld' => 'mob1', 'ch' => 'sms', 'dh' => str_repeat('b', 64), 'sid' => 'sid-a', 'ttl' => 600));
$p = V::token_verify($tok);
vt('token: verifies and carries form, field, channel, destination hash, session', array($p['fid'], $p['fld'], $p['ch'], $p['sid']), array(5, 'mob1', 'sms', 'sid-a'));
vt('token: holds no raw destination', strpos(base64_decode(strtr(explode('.', $tok)[0], '-_', '+/')), '+98'), false);
$body = explode('.', $tok)[0];
$bad = $body . 'x.' . explode('.', $tok)[1];
vt('token: one more byte → refused', V::token_verify($bad), false);
$forged = rtrim(strtr(base64_encode(json_encode(array_merge($p, array('fid' => 6)))), '+/', '-_'), '=') . '.' . explode('.', $tok)[1];
vt('token: another form id with the old signature → refused', V::token_verify($forged), false);
vt('token: another signature → refused', V::token_verify($body . '.' . str_repeat('0', 64)), false);
V::$now += 601;
vt('token: expired → refused', V::token_verify($tok), false);
V::$now -= 601;
vt('token: garbage → refused', V::token_verify('nonsense'), false);
vt('token: an array → refused', V::token_verify(array('x')), false);

/* ---------------------------------------------------------------- field keys */
vt('field_spec: mobile with otp_enabled', V::field_spec(vt_mobile()), array('channel' => 'sms', 'otp' => true, 'retype' => false, 'when' => 'filled'));
vt('field_spec: mobile without the key = old behaviour', V::field_spec(array('id_' => 'm', 'type' => 'mobile')), null);
vt('field_spec: e-mail retype only', V::field_spec(vt_email('e', 'retype')), array('channel' => 'email', 'otp' => false, 'retype' => true, 'when' => 'filled'));
vt('field_spec: e-mail retype_otp, always', V::field_spec(vt_email('e', 'retype_otp', array('email_otp_required_when' => 'always'))), array('channel' => 'email', 'otp' => true, 'retype' => true, 'when' => 'always'));
vt('field_spec: unknown mode = none', V::field_spec(vt_email('e', 'magic')), null);
vt('field_spec: a text field never', V::field_spec(array('id_' => 't', 'type' => 'text', 'otp_enabled' => '1')), null);
vt('sanitize_key otp_enabled true → "1"', V::sanitize_key('otp_enabled', true), '1');
vt('sanitize_key otp_enabled "yes" → "0"', V::sanitize_key('otp_enabled', 'yes'), '0');
vt('sanitize_key otp_channel is always sms', V::sanitize_key('otp_channel', 'call'), 'sms');
vt('sanitize_key email_confirm_mode unknown → none', V::sanitize_key('email_confirm_mode', '<b>otp</b>'), 'none');
vt('sanitize_key otp_countries keeps known ISO codes only', V::sanitize_key('otp_countries', array('IR', 'xx', 'de', 'ir')), array('ir', 'de'));
vt('sanitize_key otp_countries from a comma string', V::sanitize_key('otp_countries', 'ir, ae'), array('ir', 'ae'));
vt('sanitize_key subject: tags and quotes removed, ≤ 150', V::sanitize_key('email_otp_subject', '<i>Code</i> "for" [website_name]' . str_repeat('x', 200)) === mb_substr('Code for [website_name]' . str_repeat('x', 200), 0, 150), true);
vt('sanitize_key body: newlines kept as @n#, no HTML', V::sanitize_key('email_otp_body', "Hi\n<b>Your code:</b> [otp_code]"), 'Hi@n#Your code: [otp_code]');

/* ---------------------------------------------------------------- settings */
$c = V::clamp(array('sms' => array('code_length' => 3, 'ttl' => 9999, 'max_attempts' => 0, 'daily_budget' => '۵۰'), 'email' => array('ttl' => 10), 'token_ttl' => 99999, 'junk' => 1));
vt('clamp: code length ≥ 6', $c['sms']['code_length'], 6);
vt('clamp: SMS lifetime ≤ 600 (NIST)', $c['sms']['ttl'], 600);
vt('clamp: e-mail lifetime ≥ 120', $c['email']['ttl'], 120);
vt('clamp: attempts ≥ 1', $c['sms']['max_attempts'], 1);
vt('clamp: Persian digits in a number', $c['sms']['daily_budget'], 50);
vt('clamp: token lifetime ≤ 7200', $c['token_ttl'], 7200);
vt('clamp: unknown keys dropped', isset($c['junk']), false);
vt('defaults: SMS 300 s, e-mail 600 s, 5 attempts, cooldowns 60/120/300/600', array($c['sms']['resend_cooldowns'], V::defaults()['sms']['ttl'], V::defaults()['email']['ttl'], V::defaults()['sms']['max_attempts']), array(array(60, 120, 300, 600), 300, 600, 5));
add_filter('efb_verify_limit', function ($v, $name) { return $name === 'sid_sends' ? 2 : $v; });
vt('efb_verify_limit overrides one limit', V::limit('sid_sends', 6), 2);
$GLOBALS['efb_filters'] = array();
vt('json_has_verification: a form with otp_enabled "1"', V::json_has_verification('[{\"type\":\"mobile\",\"otp_enabled\":\"1\"}]'), true);
vt('json_has_verification: email_confirm_mode none', V::json_has_verification('[{"type":"email","email_confirm_mode":"none"}]'), false);
vt('json_has_verification: otp_enabled "0"', V::json_has_verification('[{"otp_enabled":"0"}]'), false);
vt('duration 59 → 0:59', V::duration(59), '0:59');
vt('duration 3725 → 1:02:05', V::duration(3725), '1:02:05');

vt_done();
