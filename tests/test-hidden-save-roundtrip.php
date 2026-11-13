<?php
/**
 * F08-B: the hidden field and smart-tag keys survive a real save round trip (as the builder or AutoPilot
 * sends them): JSON -> sanitize_obj_msg_efb (explicit cases -> Smart_Tags::sanitize_key, then
 * Smart_Tags::normalize_rows) -> json_encode -> stored -> json_decode, with an array-faithful stub.
 *   value templates keep { } : | (unknown namespaces removed, 500 characters), hdn_visitor_mail, prefill_key /
 *   prefill_lock (allow-list of types, once per form), st_uses computed (a posted one ignored), labels /
 *   placeholders / thank-you texts / e-mail subject keep their tags, a redirect address and a confirmation
 *   rule's address keep their tags (masked around sanitize_url), an old form comes back unchanged.
 *
 * Run: php tests/test-hidden-save-roundtrip.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
$EFB = __DIR__ . '/..';

function sanitize_text_field($v) {
	if (is_array($v) || is_object($v)) return '';
	$s = strip_tags((string) $v);
	$s = preg_replace('/%[a-f0-9]{2}/i', '', $s);
	$s = preg_replace('/[\r\n\t ]+/', ' ', $s);
	return trim($s);
}
function wp_check_invalid_utf8($s) { return preg_match('//u', (string) $s) ? (string) $s : ''; }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
/* The character filter of WordPress' esc_url() (what sanitize_url keeps). */
function sanitize_url($v) { return preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\x80-\xff]|i', '', (string) $v); }
function sanitize_file_name($v) { $v = preg_replace('/[^\p{L}\p{N}._ -]/u', '', (string) $v); return preg_replace('/\s+/', '-', trim($v)); }
function is_email($v) { return filter_var((string) $v, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url_raw($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function wp_kses_post($v) { return strip_tags((string) $v, '<b><strong><em><i><br><p><a>'); }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function __($s, $d = null) { return $s; }
$GLOBALS['filters'] = array();
function apply_filters($h, $v) { $a = func_get_args(); array_shift($a); foreach ($GLOBALS['filters'][$h] ?? array() as $cb) $a[0] = call_user_func_array($cb, $a); return $a[0]; }
function add_filter($h, $cb = null) { if ($cb) $GLOBALS['filters'][$h][] = $cb; return true; }
function add_action() { return true; }
function do_action() {}
function wp_cache_get() { return false; }
function wp_cache_set() { return true; }
function wp_cache_delete() { return true; }
$GLOBALS['opts'] = array();
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v) { $GLOBALS['opts'][$k] = $v; return true; }
function absint($v) { return abs(intval($v)); }
function wp_json_encode($v) { return json_encode($v); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
$GLOBALS['can'] = true;
function current_user_can($c) { return $GLOBALS['can']; }
function wp_strip_all_tags($s) { return trim(strip_tags((string) $s)); }

require $EFB . '/includes/json-efb.php';
require $EFB . '/includes/functions.php';

$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++;
	echo "[FAIL] $label\n  expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();
$save = function ($rows) use ($fn) {
	$json = json_encode($rows, JSON_UNESCAPED_UNICODE);
	$valp = json_decode(emsfb_json_normalize_efb($json), true);
	$valp = $fn->sanitize_obj_msg_efb($valp);
	$stored = str_replace('"', '\"', json_encode($valp, JSON_UNESCAPED_UNICODE));
	return json_decode(emsfb_json_normalize_efb($stored), true);
};
$form = array(
	array('type' => 'form', 'steps' => '1', 'formName' => 'Leads', 'email' => '', 'trackingCode' => true, 'st_uses' => 'forged,user',
		'thank_you' => 'rdrct', 'rePage' => 'https:@efb@example.com@efb@thanks?plan={field:plan.value}&src={get:utm_source|direct}#{entry:track}',
		'thank_you_message' => array('thankYou' => 'Thanks {field:nm|friend}, code {entry:track}', 'done' => 'Done, {user:first_name|you}'),
		'email_sub' => 'New lead {field:plan} from {get:utm_source}',
		'confirmation_rules' => array(array('id' => 'r1', 'priority' => 1, 'action' => 'redirect', 'url' => 'https://example.com/t?p={field:plan}&old={plan}', 'conditions' => array('logic' => 'and', 'items' => array(array('field' => 'plan', 'operator' => 'is_not_empty', 'value' => '')))))),
	array('id_' => '1', 'type' => 'step', 'step' => '1', 'name' => 'S'),
	array('id_' => 'nm', 'type' => 'text', 'name' => 'Why {field:plan|this}?', 'message' => 'Hi {user:display_name|there}', 'placeholder' => '{get:name|Your name}', 'value' => '{user:display_name}', 'step' => '1', 'prefill_key' => 'fullname', 'prefill_lock' => 1),
	array('id_' => 'plan', 'type' => 'select', 'name' => 'Plan', 'step' => '1', 'prefill_key' => 'FullName'),
	array('id_' => 'o1', 'type' => 'option', 'parent' => 'plan', 'value' => 'Pro', 'step' => '1'),
	array('id_' => 'h1', 'type' => 'hiddenField', 'name' => 'UTM', 'value' => "{get:utm_source}|{bogus:1}{date:Y-m-d H:i}\n", 'hdn_visitor_mail' => true, 'required' => 1, 'step' => '1', 'prefill_key' => 'src'),
	array('id_' => 'h2', 'type' => 'hiddenField', 'name' => 'Long', 'value' => str_repeat('a', 600), 'step' => '1'),
	array('id_' => 'pw', 'type' => 'password', 'name' => 'P', 'step' => '1', 'prefill_key' => 'pw'),
	array('id_' => 'pr', 'type' => 'prcfld', 'name' => 'Price', 'step' => '1', 'prefill_key' => 'price'),
	array('id_' => 'pr2', 'type' => 'payRadio', 'name' => 'Plan price', 'step' => '1', 'prefill_key' => 'plan2'),
	array('id_' => 'up', 'type' => 'file', 'name' => 'File', 'step' => '1', 'prefill_key' => 'file'),
	array('id_' => 'ml', 'type' => 'email', 'name' => 'Mail', 'step' => '1', 'prefill_key' => 'bad key!', 'prefill_lock' => 'yes'),
);
$s = $save($form);
$by = function ($id) use ($s) { foreach ($s as $r) if (($r['id_'] ?? '') === $id) return $r; return null; };
t('hidden: the template keeps { } : | ; the unknown namespace and the newline are gone', $by('h1')['value'], '{get:utm_source}|{date:Y-m-d H:i}');
t('hidden: never required', isset($by('h1')['required']), false);
t('hidden: hdn_visitor_mail as "1"', $by('h1')['hdn_visitor_mail'], '1');
t('hidden: a template is cut to 500 characters', mb_strlen($by('h2')['value']), 500);
t('prefill: a valid key and lock survive', array($by('nm')['prefill_key'], $by('nm')['prefill_lock']), array('fullname', '1'));
t('prefill: the same key in another case is dropped (once per form)', isset($by('plan')['prefill_key']), false);
t('prefill: a hidden field may have a key', $by('h1')['prefill_key'], 'src');
t('prefill: never on a password, an upload, a price field or a payment choice', array(isset($by('pw')['prefill_key']), isset($by('up')['prefill_key']), isset($by('pr')['prefill_key']), isset($by('pr2')['prefill_key'])), array(false, false, false, false));
t('prefill: an invalid key and its lock are dropped', array(isset($by('ml')['prefill_key']) && $by('ml')['prefill_key'] !== '', isset($by('ml')['prefill_lock'])), array(false, false));
t('labels, descriptions, placeholders and defaults keep their tags', array($by('nm')['name'], $by('nm')['message'], $by('nm')['placeholder'], $by('nm')['value']), array('Why {field:plan|this}?', 'Hi {user:display_name|there}', '{get:name|Your name}', '{user:display_name}'));
t('thank-you texts and the e-mail subject keep their tags', array($s[0]['thank_you_message']['thankYou'], $s[0]['thank_you_message']['done'], $s[0]['email_sub']), array('Thanks {field:nm|friend}, code {entry:track}', 'Done, {user:first_name|you}', 'New lead {field:plan} from {get:utm_source}'));
t('redirect address: tags kept around sanitize_url', $s[0]['rePage'], 'https:@efb@example.com@efb@thanks?plan={field:plan.value}&src={get:utm_source|direct}#{entry:track}');
t('confirmation rule address: new tags and the old {id} token kept', $s[0]['confirmation_rules'][0]['url'], 'https://example.com/t?p={field:plan}&old={plan}');
t('st_uses computed, the posted one ignored', $s[0]['st_uses'], 'get,user,date,field,entry,prefill');
$hostile = $save(array(array('type' => 'form', 'rePage' => 'https:@efb@x.test@efb@?a={get:x|"><script>}&b=<svg>'), array('id_' => 'h', 'type' => 'hiddenField', 'value' => '<script>alert(1)</script>{get:a}')));
t('a hostile fallback in a redirect address loses < > "', strpos($hostile[0]['rePage'], '<') === false && strpos($hostile[0]['rePage'], '"') === false, true);
t('a template with markup is stored without it', $hostile[1]['value'], 'alert(1){get:a}');
$old = array(array('type' => 'form', 'steps' => '1', 'formName' => 'Old', 'rePage' => 'https:@efb@x.test@efb@done', 'thank_you_message' => array('thankYou' => 'Thanks', 'done' => 'Done')), array('id_' => '1', 'type' => 'step', 'step' => '1', 'name' => 'S'), array('id_' => 'a', 'type' => 'text', 'name' => 'Name', 'value' => '', 'step' => '1'));
$o = $save($old);
t('an old form without tags comes back unchanged (no st_uses, same values)', array(isset($o[0]['st_uses']), $o[0]['rePage'], $o[2]['name']), array(false, 'https:@efb@x.test@efb@done', 'Name'));
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
