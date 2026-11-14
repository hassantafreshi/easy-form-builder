<?php
/**
 * F04-C: the server's display of numbers (Calc::format / display / with_display) — the same answers as the
 * page for fa, ar, en, de (the shared vectors; tests/test-calc-parity.js compares 400 more) — and where it is
 * used: the row's disp is the server's own (a browser's disp is dropped), e-mails print it isolated
 * (dir="auto", unicode-bidi isolate) so a currency or suffix never flips, the viewer prints it with the
 * canonical number for the CSV, smart tags {field:id} read it and {field:id.value} the plain number.
 *
 * Run: php tests/test-number-format.php
 */
define('ABSPATH', __DIR__ . '/');
function get_locale() { return $GLOBALS['loc'] ?? 'en_US'; }
require __DIR__ . '/../includes/class-Emsfb-calc.php';
require __DIR__ . '/../includes/class-Emsfb-smart-tags.php';
use Emsfb\Calc;
$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n  expected " . var_export($expected, true) . "\n  actual   " . var_export($actual, true) . "\n";
}
$V = json_decode(file_get_contents(__DIR__ . '/../docs/feature-gaps/reference/calc-test-vectors.json'), true);
$ok = 0;
foreach ($V['format'] as $v) if (Calc::format($v['canon'], Calc::config($v['field'], array('locale' => $v['locale'], 'currency' => isset($v['currency']) ? $v['currency'] : null))) === $v['expect']) $ok++;
t('every shared format vector (fa, ar, en, de, fr, de_CH)', $ok, count($V['format']));

$GLOBALS['loc'] = 'fa_IR';
$f = array('id_' => 'a', 'type' => 'number', 'name' => 'Amount', 'num_format' => 'group', 'num_digits' => 'native', 'num_suffix' => 'تومان');
t('fa: display of 1250000 = ۱٬۲۵۰٬۰۰۰ تومان', Calc::display($f, '1250000'), '۱٬۲۵۰٬۰۰۰ تومان');
$row = Calc::with_display(array('id_' => 'a', 'type' => 'number', 'value' => '1250000', 'disp' => '<b>forged</b>'), $f);
t('with_display: the stored value stays canonical, disp is the server\'s (a forged one is replaced)', array($row['value'], $row['disp']), array('1250000', '۱٬۲۵۰٬۰۰۰ تومان'));
$GLOBALS['loc'] = 'en_US';
t('no disp for a number shown as stored', isset(Calc::with_display(array('value' => '12', 'disp' => 'x'), array('type' => 'number', 'num_step' => '1'))['disp']), false);
t('no disp for an old number (no F04 keys)', Calc::display(array('type' => 'number'), '1250000'), '');
t('a two-handle slider: "lo – hi"', Calc::display(array('type' => 'range', 'range_mode' => 'dual', 'num_format' => 'group'), '1000..25000'), '1,000 – 25,000');
t('the form currency of row 0 (USD)', Calc::display(array('type' => 'calc', 'num_currency' => 'form'), '1234.5', array('currency' => 'usd')), '$1,234.50');
t('isolate(): FSI … PDI', Calc::isolate('x'), "\u{2068}x\u{2069}");

// Smart tags.
$rows = array(array('id_' => 'a', 'type' => 'number', 'value' => '1250000', 'disp' => '1,250,000 kg'), array('id_' => 'c', 'type' => 'calc', 'value' => '12.50', 'disp' => '$12.50'));
$vals = \Emsfb\Smart_Tags::values_of($rows);
$ov = \Emsfb\Smart_Tags::ovals_of($rows);
t('{field:a} reads the display, {field:a.value} the number', array($vals['a'], $ov['a'][0], $vals['c'], $ov['c'][0]), array('1,250,000 kg', '1250000', '$12.50', '12.50'));

// The e-mail and the submit handler.
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
t('e-mail: number / range / calc rows print disp isolated', strpos($src, "in_array(\$c['type'], array('number', 'range', 'calc'), true) && isset(\$c['disp'])") !== false && strpos($src, '<span dir="auto" style="unicode-bidi:isolate">\' . $this->email_escape_leaf_efb($c[\'disp\'])') !== false, true);
t('submit: the number branch writes the server\'s display (Calc::with_display)', strpos($src, '\\Emsfb\\Calc::with_display($validated_item, $f,') !== false, true);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
