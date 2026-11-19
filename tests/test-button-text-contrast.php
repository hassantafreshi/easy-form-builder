<?php
/**
 * PUB-01: the Send / Next / Previous label took row 0's el_text_color whatever the button's colour, so a form with
 * text-labelEfb on btn-darkb (old forms, e2e fixtures, assistant-built forms) showed #657096 on #202a8d: about 1.4:1.
 * Formbuilder::button_text_class_efb() (PHP, the page) and efb_btn_text_class_efb() (new-efb.js, the page's later
 * redraws and the preview) keep a label that reads at 4.5:1 against both ends of the button's gradient, and otherwise
 * draw it white or near-black, whichever reads better. The stored form is not changed.
 *
 * Run: php tests/test-button-text-contrast.php   (no WordPress needed; node for the JS twin)
 */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!function_exists('get_option')) { function get_option($o, $d = false) { return $d; } }
if (!function_exists('is_rtl')) { function is_rtl() { return false; } }
if (!function_exists('esc_html__')) { function esc_html__($t, $d = null) { return $t; } }
require_once __DIR__ . '/../includes/class-Emsfb-formbuilder.php';

$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

$has = method_exists('\\Emsfb\\Formbuilder', 'button_text_class_efb');
t('Formbuilder::button_text_class_efb() exists', $has);

/* WCAG 2.1 contrast, written here independently of the code under test. */
function lum_t($h) { $c = array(); foreach (array(1, 3, 5) as $i) { $v = hexdec(substr($h, $i, 2)) / 255; $c[] = $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4); } return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2]; }
function ratio_t($a, $b) { $x = lum_t($a); $y = lum_t($b); return (max($x, $y) + 0.05) / (min($x, $y) + 0.05); }
/* The colours the page really paints (style-efb.css and bootstrap.min-efb.css). */
$BG = array('btn-primary' => array('#3644d2'), 'btn-darkb' => array('#202a8d', '#4757e7'), 'btn-pinkEfb' => array('#ff4b93', '#ce0051'), 'btn-danger' => array('#e91a26', '#ec5560'),
	'btn-info' => array('#31d2f2', '#a5fbff'), 'btn-warning' => array('#e9c31a', '#ecdd55'), 'btn-success' => array('#0fda31', '#77f5a7'), 'btn-default' => array('#2c3c91', '#eb5892'),
	'btn-muted' => array('#6e6e6e', '#4c4c4c'), 'btn-labelEfb' => array('#657096'), 'btn-secondary' => array('#6c757d'), 'btn-light' => array('#f8f9fa'), 'btn-dark' => array('#212529'),
	'btn-colorDEfb-ffffff' => array('#ffffff'), 'btn-colorDEfb-0a0a0a' => array('#0a0a0a'));
$TX = array('text-labelEfb' => '#657096', 'text-darkb' => '#202a8d', 'text-pinkEfb' => '#ff4b93', 'text-white' => '#ffffff', 'text-dark' => '#212529', 'text-muted' => '#6c757d',
	'text-light' => '#f8f9fa', 'text-primary' => '#0d6efd', 'text-success' => '#3fd18b', 'text-danger' => '#ff455f', 'text-warning' => '#ffc107', 'text-info' => '#0dcaf0',
	'text-colorDEfb-eeeeee' => '#eeeeee', 'text-colorDEfb-111111' => '#111111');
$minr = function ($fg, $bgs) { $r = 21; foreach ($bgs as $b) $r = min($r, ratio_t($fg, $b)); return $r; };

if ($has) {
	$f = function ($b, $x) { return \Emsfb\Formbuilder::button_text_class_efb($b, $x); };
	t('the audit case: text-labelEfb on btn-darkb becomes text-white', $f('btn-darkb', 'text-labelEfb') === 'text-white');
	t('a new form (text-white on btn-primary) is unchanged', $f('btn-primary', 'text-white') === 'text-white');
	t('text-white on the yellow button becomes text-dark', $f('btn-warning', 'text-white') === 'text-dark');
	t('text-white on the pink gradient stays (near-black reads no better)', $f('btn-pinkEfb', 'text-white') === 'text-white');
	t('a custom light label on a custom white button becomes text-dark', $f('btn-colorDEfb-ffffff', 'text-colorDEfb-eeeeee') === 'text-dark');
	t('a custom dark label on a custom near-black button becomes text-white', $f('btn-colorDEfb-0a0a0a', 'text-colorDEfb-111111') === 'text-white');
	t('an unknown button class is left as it is', $f('btn-mytheme', 'text-labelEfb') === 'text-labelEfb');
	t('an unknown text class is left as it is', $f('btn-darkb', 'my-label') === 'my-label');
	t('empty values are left as they are', $f('', '') === '' && $f(null, null) === '');

	/* The whole palette: never worse than chosen, 4.5:1 whenever white or near-black can reach it. */
	$bad = array(); $n = 0;
	foreach ($BG as $b => $bgs) foreach ($TX as $x => $hex) {
		$n++;
		$got = $f($b, $x);
		$gh = isset($TX[$got]) ? $TX[$got] : null;
		if ($gh === null) { $bad[] = "$b/$x → $got"; continue; }
		$before = $minr($hex, $bgs); $after = $minr($gh, $bgs);
		$best = max($minr('#ffffff', $bgs), $minr('#212529', $bgs));
		if ($after + 1e-9 < $before) $bad[] = "$b/$x worse ($before → $after)";
		elseif ($before < 4.5 && $best >= 4.5 && $after < 4.5) $bad[] = "$b/$x still " . round($after, 2);
		elseif ($before >= 4.5 && $got !== $x) $bad[] = "$b/$x changed although it read ($before)";
	}
	t("all $n palette pairs: a readable label is kept, an unreadable one reaches 4.5:1 where white or near-black can", !$bad, implode('; ', array_slice($bad, 0, 6)));

	/* The rendered buttons of a form like the audit's form 84 (old form, text-labelEfb on btn-darkb). */
	$row0 = (object) array('type' => 'form', 'steps' => 2, 'button_color' => 'btn-darkb', 'el_text_color' => 'text-labelEfb', 'icon_color' => 'text-white', 'el_height' => 'h-d-efb',
		'button_single_text' => 'Send', 'button_Next_text' => 'Next', 'button_Previous_text' => 'Previous', 'icon' => 'bi-send', 'button_Next_icon' => 'bi-chevron-right', 'button_Previous_icon' => 'bi-chevron-left');
	$fb = (new ReflectionClass('\\Emsfb\\Formbuilder'))->newInstanceWithoutConstructor();
	$single = $fb->add_buttons_zone_efb(0, 'x', array($row0), array(), 84);
	$multi  = $fb->add_buttons_zone_efb(1, 'x', array($row0), array(), 84);
	t('Send: the label is drawn text-white', preg_match('/id="button_group_button_single_text" class="efb text-white"/', $single) === 1, substr($single, 0, 400));
	t('Next and Previous: the labels are drawn text-white', preg_match('/id="button_group_Next_button_text" class="efb text-white"/', $multi) === 1 && preg_match('/id="button_group_Previous_button_text" class="efb text-white"/', $multi) === 1);
	t('the stored row is not changed', $row0->el_text_color === 'text-labelEfb');
}

/* JS twin: the same answer for every pair. */
$js = file_get_contents(__DIR__ . '/../includes/admin/assets/js/new-efb.js');
if (!preg_match('/\nfunction efb_btn_text_class_efb\(btn, text\) \{.*?\n\}\n/s', $js, $m)) {
	t('new-efb.js has efb_btn_text_class_efb()', false);
} else {
	$pairs = array(); foreach (array_keys($BG) as $b) foreach (array_keys($TX) as $x) $pairs[] = array($b, $x);
	$pairs[] = array('btn-mytheme', 'text-labelEfb'); $pairs[] = array('btn-darkb', 'my-label'); $pairs[] = array('', '');
	$tmp = tempnam(sys_get_temp_dir(), 'efbbtn') . '.js';
	file_put_contents($tmp, $m[0] . "\nconst pairs = " . json_encode($pairs) . ";\nprocess.stdout.write(JSON.stringify(pairs.map((p) => efb_btn_text_class_efb(p[0], p[1]))));\n");
	$out = json_decode((string) shell_exec('node ' . escapeshellarg($tmp) . ' 2>&1'), true);
	@unlink($tmp);
	$diff = array();
	if (is_array($out) && $has) foreach ($pairs as $i => $p) { $php = \Emsfb\Formbuilder::button_text_class_efb($p[0], $p[1]); if (!isset($out[$i]) || $out[$i] !== $php) $diff[] = "{$p[0]}/{$p[1]}: php $php js " . ($out[$i] ?? 'none'); }
	t('JS and PHP choose the same label class for all ' . count($pairs) . ' pairs', is_array($out) && $has && !$diff, implode('; ', array_slice($diff, 0, 5)));
	$addz = substr($js, strpos($js, 'let add_buttons_zone_efb = (state, id) => {'), 6000);
	t('the page buttons use it (add_buttons_zone_efb), the builder canvas keeps the chosen colour', strpos($addz, 'const btnTextEfb = id == "dropZoneEFB" ? valj_efb[0].el_text_color : efb_btn_text_class_efb(valj_efb[0].button_color, valj_efb[0].el_text_color);') !== false && substr_count($addz, '${btnTextEfb}') === 3);
	t('the Next label redrawn on a step change and the copy-code button use it', substr_count($js, 'efb_btn_text_class_efb(valj_efb[0].button_color, valj_efb[0].el_text_color)') >= 5);
}

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
