<?php
/**
 * F05-B/C on the server, without WordPress: \Emsfb\Choices on the rows of a submission.
 *
 *  - ov (the stored value of a choice) is written from the saved form only: radio / checkbox by the choice
 *    id, select by its text (an & stored as &amp; still matches), multiselect each part; a browser's ov or
 *    other is dropped; a field whose choices have no stored value is left exactly as before (no ov);
 *  - "Other": only on a field that allows it, plain text cut at op_other_max, other = 1, never a price;
 *    an empty text refused (otherReq); a forged "Other" on a paid radio pays nothing (Pricing skips it);
 *  - the submit handler calls these at the right places (source checks: radio / option / multiselect /
 *    ranking branches, after the loop, the allow-list keeps ov / other out).
 *
 * Run: php tests/test-option-value-submit.php
 */
define('ABSPATH', __DIR__ . '/');
$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n  expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}
function sanitize_text_field($v) { if (is_array($v) || is_object($v)) return ''; $s = strip_tags((string) $v); $s = preg_replace('/[\r\n\t ]+/', ' ', $s); return trim($s); }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
require __DIR__ . '/../includes/class-Emsfb-choices.php';
use Emsfb\Choices;

$form = array(
	array('type' => 'form'),
	array('id_' => 'prov', 'type' => 'radio', 'name' => 'Province', 'op_other' => '1', 'op_other_max' => '10'),
	array('id_' => 'p1', 'type' => 'option', 'parent' => 'prov', 'value' => 'Tehran', 'opt_value' => 'IR-23'),
	array('id_' => 'p2', 'type' => 'option', 'parent' => 'prov', 'value' => 'Qom'),
	array('id_' => 'size', 'type' => 'select', 'name' => 'Size'),
	array('id_' => 's1', 'type' => 'option', 'parent' => 'size', 'value' => 'Small &amp; cheap', 'opt_value' => 'S'),
	array('id_' => 'langs', 'type' => 'checkbox', 'name' => 'Langs'),
	array('id_' => 'l1', 'type' => 'option', 'parent' => 'langs', 'value' => 'PHP', 'opt_value' => 'php'),
	array('id_' => 'l2', 'type' => 'option', 'parent' => 'langs', 'value' => 'JS'),
	array('id_' => 'ms', 'type' => 'multiselect', 'name' => 'Multi', 'minSelect' => '2', 'maxSelect' => '3'),
	array('id_' => 'm1', 'type' => 'option', 'parent' => 'ms', 'value' => 'One', 'opt_value' => '1'),
	array('id_' => 'm2', 'type' => 'option', 'parent' => 'ms', 'value' => 'Two', 'opt_value' => '2'),
	array('id_' => 'old', 'type' => 'radio', 'name' => 'Old'),
	array('id_' => 'o1', 'type' => 'option', 'parent' => 'old', 'value' => 'Alpha'),
	array('id_' => 'pay', 'type' => 'payRadio', 'name' => 'Ticket', 'op_other' => '1'),
	array('id_' => 'pa', 'type' => 'option', 'parent' => 'pay', 'value' => 'Adult', 'price' => '25'),
);
$items = array(
	array('id_' => 'prov', 'id_ob' => 'p1', 'type' => 'radio', 'value' => 'Tehran', 'ov' => 'FORGED'),
	array('id_' => 'size', 'id_ob' => 'size_options', 'type' => 'select', 'value' => 'Small & cheap'),
	array('id_' => 'langs', 'id_ob' => 'l1', 'type' => 'checkbox', 'value' => 'PHP'),
	array('id_' => 'langs', 'id_ob' => 'l2', 'type' => 'checkbox', 'value' => 'JS', 'ov' => 'x'),
	array('id_' => 'ms', 'type' => 'multiselect', 'value' => 'One@efb!Two@efb!'),
	array('id_' => 'old', 'id_ob' => 'o1', 'type' => 'radio', 'value' => 'Alpha', 'ov' => 'FORGED'),
);
$out = Choices::add_ov($items, $form);
t('radio: ov from the saved choice; a forged ov replaced', $out[0]['ov'], 'IR-23');
t('select: matched by its text (& vs &amp;)', $out[1]['ov'], 'S');
t('checkbox: each choice; one without its own value stores its label', array($out[2]['ov'], $out[3]['ov']), array('php', 'JS'));
t('multiselect: the values in order', $out[4]['ov'], '1@efb!2@efb!');
t('a field without stored values: no ov at all (a forged one dropped)', array_key_exists('ov', $out[5]), false);
t('strip_client_keys drops ov and other', Choices::strip_client_keys(array('value' => 'x', 'ov' => 'y', 'other' => '1')), array('value' => 'x'));

/* Other */
$f = $form[1];
$r = Choices::other_row($f, array('id_' => 'prov', 'id_ob' => 'prov_oth', 'value' => '  Gilan <script>x</script> and more ', 'price' => '99', 'ov' => 'z', 'name' => 'Province'), array());
t('Other: plain text cut at op_other_max (10), other = 1, the field\'s id and type, no price / ov', array($r['ok'], $r['row']['value'], $r['row']['other'], $r['row']['id_ob'], $r['row']['type'], isset($r['row']['price']), isset($r['row']['ov'])), array(true, 'Gilan x an', '1', 'prov_oth', 'radio', false, false));
$r = Choices::other_row($f, array('id_' => 'prov', 'id_ob' => 'prov_oth', 'value' => '   '), array('otherReq' => 'Describe it for %s.'));
t('Other with no text: refused with otherReq naming the field', array($r['ok'], $r['code'], $r['m']), array(false, 'efb_choice_other', 'Describe it for <b><bdi>Province</bdi></b>.'));
$r = Choices::other_row($form[12], array('id_' => 'old', 'id_ob' => 'old_oth', 'value' => 'x'), array());
t('Other on a field without op_other: refused (efb_choice_invalid)', array($r['ok'], $r['code']), array(false, 'efb_choice_invalid'));
$r = Choices::other_row(array('id_' => 's', 'type' => 'select', 'op_other' => '1'), array('id_ob' => 's_oth', 'value' => 'x'), array());
t('Other on a type that has none (select): refused', $r['ok'], false);
t('is_other_row needs {field}_oth exactly', array(Choices::is_other_row($f, array('id_ob' => 'prov_oth')), Choices::is_other_row($f, array('id_ob' => 'prov_oth2')), Choices::is_other_row($f, array('id_ob' => 'x_oth'))), array(true, false, false));
$long = Choices::other_row(array('id_' => 'q', 'type' => 'checkbox', 'op_other' => '1'), array('id_ob' => 'q_oth', 'value' => str_repeat('ب', 300)), array());
t('Other without op_other_max: 200 characters', mb_strlen($long['row']['value']), 200);

/* Forged Other on a paid radio: Pricing skips it (source of the payment's price). */
$pricing = file_get_contents(__DIR__ . '/../includes/payments/class-Emsfb-payment-pricing.php');
t('Pricing skips the "Other" row of a priced field (never a price)', (bool) preg_match('/if \( \$ob === \$id \. \'_oth\' \) continue;/', $pricing), true);

/* Where the submit handler calls them. */
$pub = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
preg_match('/case \'imgRadio\':\s*\$is_valid = 0;(.*?)case \'switch\':/s', $pub, $m);
t('the radio branch handles "Other" first (Choices::other_row)', isset($m[1]) && strpos($m[1], 'Choices::is_other_row($f, $item)') !== false && strpos($m[1], 'Choices::other_row(') !== false, true);
t('the radio branch no longer counts booking places in the form JSON', isset($m[1]) && strpos($m[1], 'registered_count') === false, true);
t('the checkbox parent branch takes the "Other" row', (bool) preg_match('/F05: the "Other" row of a checkbox field.*?Choices::other_row\(/s', $pub), true);
t('after the loop: count_check, then add_ov', (bool) preg_match('/Choices::count_check\(\$form_fields_array, \$validated_items.*?Choices::add_ov\(\$validated_items, \$form_fields_array\)/s', $pub), true);
t('the multiselect branch checks min / max on the server', (bool) preg_match('/case \'multiselect\':.*?Choices::limit_check\(\$f, count\(/s', $pub), true);
t('the form is never written on submit (no update of form_structer in the submit handler)', strpos($pub, "\$r = \$this->db->update(\$table_name, ['form_structer' => \$value], ['form_id' => \$id]);") === false, true);
preg_match('/static \$attribute_map_efb = \[(.*?)\];/s', $pub, $am);
t('the allow-list never takes ov or other from a browser', isset($am[1]) && strpos($am[1], "'ov'") === false && strpos($am[1], "'other'") === false, true);
t('the allow-list takes a ranking\'s ids', isset($am[1]) && strpos($am[1], "'ranking' => ['ids' => true]") !== false, true);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
