<?php
/**
 * F13 sub-task E: WordPress personal-data exporter and eraser, privacy policy text.
 *
 * Real Privacy code on an in-memory SQLite database (tests/privacy-test-db.php), fixtures stored in
 * the plugin's real escaped format (including the @efb@dq# quote token):
 *  1. an address in the identifying field → the export has the entry with every answer and its
 *     replies; files as URLs, signature as [Signature], maps as text, multi-values with ", ";
 *  2. the same address only inside a textarea → not found (someone else's entry);
 *  3. a logged-in sender (uid) is found without any email field; prv_id_field / email_to decide;
 *  4. 120 entries → three pages, done on the last one;
 *  5. erasure in "anon" mode anonymizes, a payment entry is kept and reported (items_retained);
 *     "delete" mode removes entries and replies; e-mail log rows are exported and erased;
 *  6. % and _ in an address are matched literally (esc_like);
 *  7. the policy text follows the settings (IP mode, prv_days, add-ons, reCAPTCHA).
 *
 * Run: php tests/test-privacy-exporter.php
 */
define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
$GLOBALS['opts'] = array();
$GLOBALS['settings'] = new stdClass();
$GLOBALS['users'] = array('member@example.test' => 42);
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v, $a = null) { $GLOBALS['opts'][$k] = $v; return true; }
function get_setting_Emsfb($m = 'decoded') { return $GLOBALS['settings']; }
function apply_filters($h, $v) { return $v; }
function do_action() {} function add_action() {} function add_filter() {}
function emsfb_json_normalize_efb($s) { return str_replace('\\"', '"', (string) $s); }
function wp_json_encode($v) { return json_encode($v); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function __($s) { return $s; }
function get_user_by($f, $v) {
	if ($f === 'email' && isset($GLOBALS['users'][strtolower($v)])) return (object) array('ID' => $GLOBALS['users'][strtolower($v)]);
	if ($f === 'id' && (int) $v === 9) return (object) array('ID' => 9, 'display_name' => 'Site Admin');
	return false;
}
class TextFn { public function text_efb($keys) { $o = array(); foreach ($keys as $k) $o[$k] = 'T[' . $k . ']'; $o['prvRetainedPay'] = '%s payment entries kept.'; $o['prvPolicyRetention'] = 'Deleted after %s days.'; $o['prvPolicyShare'] = 'Shared with: %s.'; return $o; } }
function get_efbFunction() { return new TextFn(); }
require __DIR__ . '/privacy-test-db.php';
require __DIR__ . '/../includes/class-Emsfb-upload-guard.php';
require __DIR__ . '/../includes/class-Emsfb-privacy.php';
use Emsfb\Privacy;

$tmp = sys_get_temp_dir() . '/efb-prv-exp-' . getmypid();
@mkdir($tmp . '/uploads', 0777, true);
register_shutdown_function(function () use ($tmp) { exec('rm -rf ' . escapeshellarg($tmp)); });
efb_privacy_test_wp_stubs($tmp . '/uploads');
$db = $GLOBALS['wpdb'] = new EfbPrivacyTestDb();
$now = date('Y-m-d H:i:s');

$f_auto = $db->add_form(array(array('type' => 'form'), array('id_' => 'em', 'type' => 'email'), array('id_' => 'msg', 'type' => 'textarea')));
$f_to = $db->add_form(array(array('type' => 'form', 'email_to' => 'em2'), array('id_' => 'em1', 'type' => 'email'), array('id_' => 'em2', 'type' => 'email')));
$f_pick = $db->add_form(array(array('type' => 'form', 'prv_id_field' => 'em1', 'email_to' => 'em2'), array('id_' => 'em1', 'type' => 'email'), array('id_' => 'em2', 'type' => 'email')));

$jane = $db->add_entry($f_auto, array(
	array('id_' => 'em', 'name' => 'Email', 'type' => 'email', 'value' => 'Jane@Example.test '),
	array('id_' => 'nm', 'name' => 'Say @efb@dq#hi@efb@dq#', 'type' => 'text', 'value' => 'line1@n#line2 @efb@dq#q@efb@dq#'),
	array('id_' => 'ck', 'name' => 'Colors', 'type' => 'multiselect', 'value' => 'Red@efb!Blue@efb!'),
	array('id_' => 'fl', 'name' => 'CV', 'type' => 'file', 'value' => '@file@', 'url' => 'http://example.test/wp-content/uploads/2026/11/efb-PLG-261112-JANE0001.pdf'),
	array('id_' => 'sg', 'name' => 'Sign', 'type' => 'esign', 'value' => 'data:image/png;base64,AAAA'),
	array('id_' => 'mp', 'name' => 'Where', 'type' => 'maps', 'value' => array(array('lat' => '35.7', 'lng' => '51.4', 'address' => 'Tehran'))),
	array('type' => 'w_link', 'id_' => 'w_link', 'value' => 'https://site.test/?x=1'),
), $now, array('form_title_x' => 'Contact us', 'track' => 'TRK-JANE', 'ip' => '203.0.113.0'));
$db->add_reply($jane, array(array('id_' => 'message', 'type' => 'text', 'value' => 'Thanks Jane')), $now, array('rsp_by' => 9));
$db->add_reply($jane, array(array('id_' => 'message', 'type' => 'text', 'value' => 'You are welcome'), array('id_' => 'closed', 'type' => 'closed', 'value' => 'x')), $now, array('rsp_by' => 0));
$other = $db->add_entry($f_auto, array(
	array('id_' => 'em', 'name' => 'Email', 'type' => 'email', 'value' => 'bob@example.test'),
	array('id_' => 'msg', 'name' => 'Message', 'type' => 'textarea', 'value' => 'please also email jane@example.test'),
), $now);

// 1 + 2
$exp = Privacy::export('jane@example.test', 1);
$entries = array_values(array_filter($exp['data'], function ($i) { return $i['group_id'] === 'efb-entries'; }));
t('export: the entry with the address in its email field is found (case and spaces ignored)', count($entries) === 1 && $entries[0]['item_id'] === 'efb-entry-' . $jane, true);
t('export: someone else\'s entry that only mentions the address in a textarea is not', strpos(json_encode($exp), 'bob@example.test') === false, true);
$d = array();
foreach ($entries[0]['data'] as $row) $d[$row['name']] = $row['value'];
t('export: form name, confirmation code, date, IP', array($d['T[form]'], $d['T[trackNo]'], $d['T[ddate]'], $d['T[ip]']), array('Contact us', 'TRK-JANE', $now, '203.0.113.0'));
t('export: tokens decoded in names and values (@efb@dq#, @n#)', isset($d['Say "hi"']) ? $d['Say "hi"'] : null, "line1\nline2 \"q\"");
t('export: multi-values joined with ", "', $d['Colors'], 'Red, Blue');
t('export: file as its URL', $d['CV'], 'http://example.test/wp-content/uploads/2026/11/efb-PLG-261112-JANE0001.pdf');
t('export: signature as [Signature]', $d['Sign'], '[T[esign]]');
t('export: map as text', $d['Where'], '35.7, 51.4, Tehran');
t('export: the page link is not exported', strpos(json_encode($entries), 'w_link') === false && strpos(json_encode($entries), 'site.test') === false, true);
t('export: replies with date, sender (display name, guest) and text; open/close markers left out', array($d['T[reply] 1'], $d['T[reply] 2']), array($now . ' — Site Admin: Thanks Jane', $now . ' — T[guest]: You are welcome'));
t('export: group label and done', array($entries[0]['group_label'], $exp['done']), array('T[prvGroupLabel]', true));

// 3. uid and id field choice
$by_uid = $db->add_entry($f_auto, array(array('id_' => 'nm', 'type' => 'text', 'value' => 'no email field')), $now, array('uid' => 42));
$e = Privacy::export('member@example.test', 1);
t('export: a logged-in sender is found by uid without any email field', array_column($e['data'], 'item_id'), array('efb-entry-' . $by_uid));
$to1 = $db->add_entry($f_to, array(array('id_' => 'em1', 'type' => 'email', 'value' => 'cc@example.test'), array('id_' => 'em2', 'type' => 'email', 'value' => 'owner@example.test')), $now);
t('email_to decides: the address in the other email field does not count', count(Privacy::export('cc@example.test', 1)['data']), 0);
t('email_to decides: the address in email_to counts', array_column(Privacy::export('owner@example.test', 1)['data'], 'item_id'), array('efb-entry-' . $to1));
$pk = $db->add_entry($f_pick, array(array('id_' => 'em1', 'type' => 'email', 'value' => 'picked@example.test'), array('id_' => 'em2', 'type' => 'email', 'value' => 'notify@example.test')), $now);
t('prv_id_field beats email_to', array(count(Privacy::export('picked@example.test', 1)['data']), count(Privacy::export('notify@example.test', 1)['data'])), array(1, 0));

// 6. LIKE wildcards
$db->add_entry($f_auto, array(array('id_' => 'em', 'type' => 'email', 'value' => 'aXb@example.test')), $now);
$pct = $db->add_entry($f_auto, array(array('id_' => 'em', 'type' => 'email', 'value' => 'a_b%c@example.test')), $now);
t('% and _ in the address are matched literally', array_column(Privacy::export('a_b%c@example.test', 1)['data'], 'item_id'), array('efb-entry-' . $pct));
t('...and do not act as wildcards', count(Privacy::export('a_b@example.test', 1)['data']), 0);

// 4. 120 entries → three pages
$db->pdo->beginTransaction();
for ($i = 0; $i < 120; $i++) $db->add_entry($f_auto, array(array('id_' => 'em', 'type' => 'email', 'value' => 'many@example.test')), $now);
$db->pdo->commit();
$pages = array();
for ($p = 1; $p <= 5; $p++) { $r = Privacy::export('many@example.test', $p); $pages[] = array(count($r['data']), $r['done']); if ($r['done']) break; }
t('120 entries: pages of 50, 50, 20; done only on the third', $pages, array(array(50, false), array(50, false), array(20, true)));
$again = Privacy::export('many@example.test', 1);
t('page 1 again restarts from the beginning', count($again['data']), 50);

// 5. erase
$GLOBALS['settings'] = (object) array('prv_erase_mode' => 'anon');
$f_pay = $db->add_form(array(array('type' => 'payment'), array('id_' => 'em', 'type' => 'email')), 'payment');
$paid = $db->add_entry($f_pay, array(array('id_' => 'em', 'type' => 'email', 'value' => 'jane@example.test'), array('id_' => 'payment', 'type' => 'payment', 'value' => '10 usd', 'paymentIntent' => 'pi_1')), $now);
$GLOBALS['opts']['efb_email_log'] = array(array('to' => 'jane@example.test', 'subject' => 'Your entry', 'date' => $now), array('to' => 'bob@example.test', 'subject' => 'x', 'date' => $now));
$exp2 = Privacy::export('jane@example.test', 1);
t('export: the e-mail log rows to the address', array_values(array_map(function ($i) { return $i['data'][1]['value']; }, array_filter($exp2['data'], function ($i) { return $i['group_id'] === 'efb-email-log'; }))), array('jane@example.test'));
$er = Privacy::erase('jane@example.test', 1);
$jrow = $db->row('wp_emsfb_msg_', "msg_id = $jane");
t('erase (anon): the entry stays, anonymized', array((int) $jrow['prv_state'], strpos($jrow['content'], 'Jane@Example') === false), array(1, true));
t('erase (anon): replies replaced', strpos($db->get_var("SELECT GROUP_CONCAT(content) FROM wp_emsfb_rsp_ WHERE msg_id = $jane"), 'Thanks Jane'), false);
t('erase: the payment entry is anonymized and reported as retained', array($er['items_removed'], $er['items_retained'], $er['messages']), array(true, true, array('1 payment entries kept.')));
t('erase: the other person\'s entry is untouched', strpos($db->row('wp_emsfb_msg_', "msg_id = $other")['content'], 'jane@example.test') !== false, true);
t('erase: the e-mail log row to the address is removed, the other kept', array_column($GLOBALS['opts']['efb_email_log'], 'to'), array('bob@example.test'));
t('erase: a second request finds nothing more', Privacy::erase('jane@example.test', 1)['items_removed'], false);
$GLOBALS['settings'] = (object) array('prv_erase_mode' => 'delete');
$pages = 0; do { $r = Privacy::erase('many@example.test', ++$pages); } while (!$r['done'] && $pages < 10);
t('erase (delete): all 120 entries removed over the pages', array($db->count('wp_emsfb_msg_', "content LIKE '%many@example.test%'"), $pages), array(0, 3));
t('registration: exporter and eraser under "easy-form-builder"', array(isset(Privacy::register_exporter(array())['easy-form-builder']['callback']), isset(Privacy::register_eraser(array())['easy-form-builder']['callback'])), array(true, true));

// 7. policy text
$h = Privacy::policy_html((object) array());
t('policy: default site (IP stored, kept until deleted)', strpos($h, 'T[prvPolicyIp]') !== false && strpos($h, 'T[prvPolicyForever]') !== false && strpos($h, 'T[prvPolicyCaptcha]') === false, true);
$h = Privacy::policy_html((object) array('prv_ip_mode' => 'none', 'prv_days' => '90', 'AdnTLG' => 1, 'AdnGoS' => '1', 'siteKey' => 'site-key-123'));
t('policy: follows prv_days, IP mode, add-ons and reCAPTCHA', strpos($h, 'Deleted after 90 days.') !== false && strpos($h, 'T[prvPolicyNoIp]') !== false && strpos($h, 'Shared with: Telegram, Google Sheets.') !== false && strpos($h, 'T[prvPolicyCaptcha]') !== false, true);
t('policy: no secret in the text', strpos($h, 'site-key-123'), false);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
