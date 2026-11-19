<?php
/**
 * PUB-02 (BUGS-FOUND 143): a form with Conditional Logic rules but no `logic:"1"` flag on row 0 (built by AutoPilot,
 * imported, or changed through form.update) loads the logic runtime in the browser, as the server already applies the
 * rules on submit. The old test was strpos($value, '"logic_rules"') on JSON that is stored slash-escaped, so it never
 * matched. The real helper is lifted from class-Emsfb-public.php and run on a stored (escaped) and a decoded copy.
 *
 * Run: php tests/test-public-logic-flag.php   (no WordPress needed)
 */
$pass = 0; $fail = 0;
function t($label, $ok) { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label\n"; }

$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
if (!preg_match('/\n\tpublic static function form_json_has_logic_efb\(.*?\n\t\}\n/s', $src, $m)) { echo "[FAIL] form_json_has_logic_efb() not found\nRESULTS: 0 passed, 1 failed\n"; exit(1); }
eval('class EfbLogicFlagHarness {' . $m[0] . '}');
$has = function ($json) { return EfbLogicFlagHarness::form_json_has_logic_efb($json); };

/* A form package as the Builder API / import writes it: rules on row 0, no flag. */
$rule = array('id' => 'r1', 'enabled' => true, 'conditions' => array('type' => 'group', 'operator' => 'AND', 'items' => array(array('field' => 'a', 'operator' => 'is', 'value' => 'x'))), 'actions' => array(array('type' => 'hide', 'target' => 'b')));
$form = array(
	array('type' => 'form', 'steps' => 1, 'logic_rules' => array($rule)),
	array('id_' => '1', 'type' => 'step'),
	array('id_' => 'a', 'type' => 'text', 'step' => 1),
	array('id_' => 'b', 'type' => 'text', 'step' => 1),
);
$decoded = json_encode($form, JSON_UNESCAPED_UNICODE);
$stored  = str_replace('"', '\\"', $decoded); // how form_structer is kept in the database

t('rules without the flag, stored (slash-escaped) JSON: logic runtime is needed', $has($stored));
t('rules without the flag, decoded JSON: logic runtime is needed', $has($decoded));
t('rules without the flag, pretty-printed JSON: logic runtime is needed', $has(json_encode($form, JSON_PRETTY_PRINT)));

$flag = $form; unset($flag[0]['logic_rules']);
foreach (array('"1"' => '1', 'true' => true, '1' => 1) as $label => $v) {
	$flag[0]['logic'] = $v;
	$j = json_encode($flag);
	t("flag logic:$label, stored JSON", $has(str_replace('"', '\\"', $j)));
	t("flag logic:$label, decoded JSON", $has($j));
}

$none = $form; $none[0]['logic_rules'] = array();
t('an empty rule list does not load the runtime', !$has(str_replace('"', '\\"', json_encode($none))) && !$has(json_encode($none)));
$off = $form; unset($off[0]['logic_rules']); $off[0]['logic'] = '0';
t('flag "0" without rules does not load the runtime', !$has(str_replace('"', '\\"', json_encode($off))));
$off[0]['logic'] = false;
t('flag false without rules does not load the runtime', !$has(json_encode($off)));
t('a plain form does not load the runtime', !$has(str_replace('"', '\\"', json_encode(array(array('type' => 'form'), array('id_' => 'a', 'type' => 'text', 'name' => 'logic_rules'))))));
t('not a string: false', !$has(null) && !$has(array('logic_rules' => array(1))));

/* The render path uses the helper (the old strpos test is gone). */
t('the render path asks form_json_has_logic_efb()', strpos($src, '$_efb_form_has_logic = self::form_json_has_logic_efb($value);') !== false);
t("the old strpos(\$value , '\"logic_rules\"') test is gone", strpos($src, "strpos(\$value , '\"logic_rules\"')") === false);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
