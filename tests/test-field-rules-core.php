<?php
/**
 * F03-A: the shared field-rule module (Emsfb\Field_Rules) and its one server point.
 *
 *  1. The real "// Field rules gate (F03)" block of get_form_public_efb() (extracted from
 *     includes/class-Emsfb-public.php) runs in a fixture: a field without F03 keys is untouched
 *     (has_rules false, the row unchanged, no code); a rule failure sets is_valid 0, the owner's
 *     message, the field id and the stable code; a field hidden by Conditional Logic
 *     ($_ignored_set) is not checked.
 *  2. Messages: msg_<rule> wins over the default, %s = the label in <b>, the owner's text is
 *     escaped (a stored "<b>" is shown as text); the default sentence otherwise, English when
 *     the phrase is missing; customRequiredMsg is answered by the server (required_msg) and the
 *     required check of get_form_public_efb() uses it.
 *  3. The real email and url branches: mlen "0" or "" (the builder's empty Max) no longer
 *     refuses every value (§18.1); a real maximum still refuses.
 *  4. The default branch uses msg_minlen / msg_maxlen; the mask gate uses msg_format.
 *  5. Old forms: has_rules() is false for rows without the keys; sanitize_key keeps a message
 *     plain (no tags, no " < >, one line, 200 code points).
 *  6. The visitor phrases reach $translation_keys and the public subset; text_efb() has them.
 *
 * Run: php tests/test-field-rules-core.php
 */
define('ABSPATH', __DIR__ . '/');

$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}

function sanitize_text_field($v) {
	if (is_array($v) || is_object($v)) return '';
	$s = (string) $v;
	if (!preg_match('//u', $s)) return '';
	$s = strip_tags($s);
	$s = preg_replace('/%[a-f0-9]{2}/i', '', $s);
	$s = preg_replace('/[\r\n\t ]+/', ' ', $s);
	return trim($s);
}
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function sanitize_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function apply_filters($h, $v) { return $v; }
function wp_parse_url($u, $c = -1) { return parse_url($u, $c); }
require_once __DIR__ . '/../includes/class-Emsfb-masks.php';
require_once __DIR__ . '/../includes/class-Emsfb-field-rules.php';
use Emsfb\Field_Rules;

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');

/* ------------------------------------------------------------ 1. the gate block */
$a = strpos($src, '// Field rules gate (F03)');
$b = strpos($src, '// End field rules gate.', $a);
t('the gate block is in get_form_public_efb()', $a !== false && $b !== false && $a > strpos($src, 'public function get_form_public_efb('), true);
$gate = substr($src, $a, $b - $a);
eval('class FrGateFixture {
	public $lanText = array(); public $field_rules_code_efb = ""; public $unique_pending_efb = array(); public $ctx_calls = 0;
	private function field_rules_ctx_efb($a, $b) { $this->ctx_calls++; return array("form_id" => 7, "form_name" => "F", "emails" => array()); }
	public function run($f, $validated_item, $_ignored_set = null) {
		$is_valid = 1; $error_message = ""; $error_field_id = ""; $request_data = array(); $raw_passwords_efb = array(); $form_fields_array = array(); $submitted_values = array();
		if ($_ignored_set === null) unset($_ignored_set);
		' . $gate . '
		return array("is_valid" => $is_valid, "m" => $error_message, "field_id" => $error_field_id, "item" => $validated_item, "code" => $this->field_rules_code_efb);
	}
}');
$fx = new FrGateFixture();
$row = array('id_' => 'n1', 'name' => 'Name', 'type' => 'text', 'value' => 'Al');
$old = array('id_' => 'n1', 'type' => 'text', 'name' => 'Name', 'milen' => '5', 'mlen' => '10', 'required' => 0);
$r = $fx->run($old, $row);
t('1 old field (no F03 key): untouched, no code, no context built', array($r['is_valid'], $r['item'], $r['code'], $fx->ctx_calls), array(1, $row, '', 0));
$f = $old + array('msg_minlen' => 'Write at least five letters in %s.');
$r = $fx->run($f, $row);
t('1 rule failure: is_valid 0, owner message with the label, field id, code, row dropped', array($r['is_valid'], $r['m'], $r['field_id'], $r['code'], $r['item']), array(0, 'Write at least five letters in <b>Name</b>.', 'n1', 'minlen', null));
$fx->field_rules_code_efb = '';
$r = $fx->run($f, $row, array('n1' => 0));
t('1 a field hidden by Conditional Logic is not checked', array($r['is_valid'], $r['code']), array(1, ''));
$r = $fx->run($f, array('id_' => 'n1', 'type' => 'text', 'value' => 'Alice'));
t('1 a passing value keeps its row', array($r['is_valid'], $r['item']['value']), array(1, 'Alice'));
$r = $fx->run($f, array('id_' => 'n1', 'type' => 'text', 'value' => ''));
t('1 an empty optional value runs no rule', $r['is_valid'], 1);

/* ------------------------------------------------------------ 2. messages */
$f2 = array('name' => 'City <i>x</i>', 'msg_pattern' => '<b>%s</b> & co');
t('2 custom_msg escapes the owner text and the label', Field_Rules::custom_msg($f2, 'pattern'), '&lt;b&gt;<b>City &lt;i&gt;x&lt;/i&gt;</b>&lt;/b&gt; &amp; co');
t('2 custom_msg null when empty', Field_Rules::custom_msg(array('msg_pattern' => '  '), 'pattern'), null);
t('2 default phrase translated, %1$s label, %2$s number', Field_Rules::msg(array('name' => 'Bio'), 'maxlen', 'tvMaxLen_', array('tvMaxLen_' => 'MAX %1$s %2$s'), 9), 'MAX <b>Bio</b> <b>9</b>');
t('2 English default when the phrase is missing', Field_Rules::msg(array('name' => 'Bio'), 'unique', 'tvUnique_', array()), 'This value has already been used.');
t('2 required_msg: the field\'s customRequiredMsg (escaped)', Field_Rules::required_msg(array('name' => 'N', 'customRequiredMsg' => 'Tell us %s <now>')), 'Tell us <b>N</b> &lt;now&gt;');
t('2 required_msg: null without one', Field_Rules::required_msg(array('name' => 'N')), null);
t('2 the required check of the submit handler reads required_msg', strpos($src, '\Emsfb\Field_Rules::required_msg( $_req_f )') !== false, true);

/* ------------------------------------------------------------ 3. email / url mlen */
function fr_extract_case($src, $label) {
	$s = strpos($src, $label, strpos($src, 'public function get_form_public_efb('));
	$e = strpos($src, 'break;', strpos($src, '$still_processing = false;', $s)) + strlen('break;');
	return substr($src, $s, $e - $s);
}
$cases = fr_extract_case($src, "case 'email':") . "\n" . fr_extract_case($src, "case 'url':");
$methods = '';
foreach (array('filter_attributes_by_type_efb', 'sanitize_value_efb', 'filter_and_sanitize_attributes_efb') as $mname) {
	preg_match('/\tprivate function ' . $mname . '\(.*?\n\t}\n/s', $src, $mm);
	$methods .= $mm[0];
}
preg_match('/\tpublic static function normalize_email_input_efb\(.*?\n\t}\n/s', $src, $mm);
$methods .= $mm[0];
eval('class FrTypeFixture { public $lanText = array();' . $methods . '
	private function email_list_efb() {}
	public function run($f, $item) {
		$is_valid = null; $error_message = "provisional"; $validated_item = null; $still_processing = true; $email_recipients = array(); $form_fields_array = array(array());
		switch ($f["type"]) {' . $cases . '
		}
		return $is_valid;
	}
}');
$tf = new FrTypeFixture();
$em = function ($mlen) { return array('id_' => 'e1', 'type' => 'email', 'name' => 'E', 'milen' => '', 'mlen' => $mlen); };
$item = array('id_' => 'e1', 'name' => 'E', 'id_ob' => 'e1_', 'amount' => '2', 'type' => 'email', 'value' => 'a@example.com', 'session' => 's', 'form_id' => '7');
t('3 email with mlen "0": accepted (was refused)', $tf->run($em('0'), $item), 1);
t('3 email with mlen "": accepted', $tf->run($em(''), $item), 1);
t('3 email with mlen 5: refused', $tf->run($em('5'), $item), 0);
$url = array('id_' => 'u1', 'type' => 'url', 'name' => 'U', 'milen' => '', 'mlen' => '0');
$uitem = array('id_' => 'u1', 'name' => 'U', 'id_ob' => 'u1_', 'amount' => '2', 'type' => 'url', 'value' => 'https://example.com', 'session' => 's', 'form_id' => '7');
t('3 url with mlen "0": accepted', $tf->run($url, $uitem), 1);
t('3 url with mlen 10: refused', $tf->run(array('mlen' => '10') + $url, $uitem), 0);

/* ------------------------------------------------------------ 4. default branch and mask gate */
t('4 default branch: msg_minlen / msg_maxlen win', strpos($src, "\\Emsfb\\Field_Rules::custom_msg(\$f, 'minlen'), \\Emsfb\\Field_Rules::custom_msg(\$f, 'maxlen')") !== false, true);
t('4 mask gate: msg_format wins', strpos($src, "\\Emsfb\\Field_Rules::custom_msg(\$f, 'format')") !== false, true);

/* ------------------------------------------------------------ 5. old forms, sanitize */
$legacy = array(
	array('id_' => 'a', 'type' => 'text', 'name' => 'A', 'milen' => '2', 'mlen' => '20', 'required' => '1', 'customRequiredMsg' => 'x'),
	array('id_' => 'b', 'type' => 'email', 'name' => 'B', 'noti' => '1'),
	array('id_' => 'c', 'type' => 'textarea', 'name' => 'C', 'value' => 'x@n#y'),
	array('id_' => 'd', 'type' => 'password', 'name' => 'D'),
	array('id_' => 'e', 'type' => 'mobile', 'name' => 'E', 'c_n' => array('98')),
	array('id_' => 'f', 'type' => 'text', 'mask_preset' => 'ir.mobile'),
);
$any = false;
foreach ($legacy as $l) $any = $any || Field_Rules::has_rules($l);
t('5 legacy rows: no rules', $any, false);
t('5 sanitize msg: tags, quotes, newlines, spaces', Field_Rules::sanitize_key('msg_pattern', " <b>Use</b> \"A-Z\"\n only  <x "), 'Use A-Z only');
t('5 sanitize msg: 200 code points (Persian)', Field_Rules::sanitize_key('msg_unique', str_repeat('ب', 250)), str_repeat('ب', 200));
t('5 sanitize msg: arrays become ""', Field_Rules::sanitize_key('msg_domain', array('x')), '');

/* ------------------------------------------------------------ 6. phrases */
preg_match('/\$translation_keys = \[(.*?)\];/s', $src, $tk);
preg_match("/'partialConsent','partialNotice','popupClose',(.*?)\];/s", $src, $pub);
$miss_tk = array(); $miss_pub = array();
foreach (Field_Rules::message_keys() as $k) {
	if (strpos($tk[1], "'{$k}'") === false) $miss_tk[] = $k;
	if (strpos($pub[1], "'{$k}'") === false) $miss_pub[] = $k;
}
t('6 every message key in $translation_keys', $miss_tk, array());
t('6 every message key in the public subset', $miss_pub, array());
$fn = file_get_contents(__DIR__ . '/../includes/functions.php');
$miss = array();
foreach (Field_Rules::DEFAULTS as $k => $en) {
	if (strpos($fn, '"' . $k . '" => $state && isset($ac->text->' . $k . ') ? $ac->text->' . $k . " : esc_html__('" . str_replace("'", "\\'", $en) . "','easy-form-builder')") === false) $miss[] = $k;
}
t('6 text_efb() has every default with the same English text', $miss, array());

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
