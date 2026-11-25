<?php
/**
 * PUB-03 (B5 → B7 hand-off): the builder's texts reach the phrase list.
 *
 *  - forms-efb.js: every text of the ready-made templates a person reads (field labels, help texts, step titles,
 *    headings, choices, the hint of a text box) is efbTplT(key, English); includes/admin/template-phrases-efb.php has
 *    each key with the same English through __() and a translators comment; efbFunction::efb_sentence_forms() merges
 *    them into efb_var.text on the Create screen. No literal English is left but the technical values (the
 *    placeholders of choice and file fields that no one sees, "document", the Stripe field);
 *  - the templates' English typos are fixed ("storet", "insatgram", "More then", a Lorem ipsum invitation …);
 *  - a template made on a site with the phrases translated carries the translations; without them the English;
 *  - val-efb.js: calcField is a phrase of core (the tile while the Numbers & Calculations add-on is not installed);
 *    the e-mail subject box uses the existing emailSubjectLabel; every efb_var.text key the builder, the settings
 *    and the templates read exists in the phrase list.
 *
 * Run: php tests/test-template-phrases.php   (the template run needs node)
 */
$passed = 0; $failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . ', got ' . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n"; $failed++;
}
$root = dirname(__DIR__);
define('ABSPATH', sys_get_temp_dir() . '/');
function __($s, $d = '') { return $s; }
function wp_strip_all_tags($s) { return strip_tags((string) $s); }
$file = $root . '/includes/admin/template-phrases-efb.php';
t('includes/admin/template-phrases-efb.php exists', is_file($file), true);
if (!is_file($file)) { echo "\nRESULTS: {$passed} passed, {$failed} failed\n"; exit(1); }
require $file;
$ph = emsfb_template_phrases_efb();
$js = (string) file_get_contents($root . '/includes/admin/assets/js/forms-efb.js');
$src = (string) file_get_contents($file);

preg_match_all('/efbTplT\("([A-Za-z0-9]+)", ("(?:[^"\\\\]|\\\\.)*")\)/', $js, $m, PREG_SET_ORDER);
t('forms-efb.js reads the template texts through efbTplT() (' . count($m) . ' texts)', count($m) >= 240, true);
$bad = array();
foreach ($m as $x) {
	$en = json_decode($x[2]);
	if (!isset($ph[$x[1]])) $bad[] = 'missing ' . $x[1];
	elseif ($ph[$x[1]] !== $en) $bad[] = $x[1] . ': "' . $ph[$x[1]] . '" vs "' . $en . '"';
}
t('every key is a phrase of template-phrases-efb.php, with the same English', $bad, array());
$used = array_unique(array_map(function ($x) { return $x[1]; }, $m));
t('… and every phrase there is used (' . count($ph) . ')', array_values(array_diff(array_keys($ph), $used)), array());
t('every phrase has a translators comment', substr_count($src, "/* translators: ready-made form template:"), count($ph));
t('the phrases go through __() in the plugin\'s text domain', substr_count($src, "', 'easy-form-builder'),"), count($ph));

$left = array();
if (preg_match_all('/"(name|message|placeholder|value|formName)"\s*:\s*"([^"]*)"/', $js, $lm, PREG_SET_ORDER)) {
	foreach ($lm as $x) {
		if (!preg_match('/[A-Za-z]{2}/', $x[2]) || preg_match('/^(bi-|text-|btn-|h-d|fs-|justify|border|rounded|#|form$)/', $x[2])) continue;
		if (in_array($x[1] . ':' . $x[2], array('placeholder:Radio', 'placeholder:file', 'value:document', 'placeholder:Stripe', 'name:Stripe', 'placeholder:Rating', 'placeholder:Multiple Select'), true)) continue;
		$left[] = $x[1] . ':' . $x[2];
	}
}
t('no literal English text left in the templates (technical values aside)', array_values(array_unique($left)), array());
t('the typos are gone (storet, insatgram, More then, Lorem ipsum, "Happy")', (bool) preg_match('/storet|insatgram|More then|Lorem ipsum|be Happy to/', $js . $src), false);
t('… the store survey reads "I was greeted upon entering the store"', in_array('I was greeted upon entering the store', $ph, true), true);

$fn = (string) file_get_contents($root . '/includes/functions.php');
t('efb_sentence_forms() merges them (the Create screen\'s text_efb(1))', (bool) preg_match('/function efb_sentence_forms\(\)\{.*?emsfb_template_phrases_efb\(\).*?return \$r;/s', $fn), true);

/* The templates in node, with and without translations. */
if (trim((string) shell_exec('command -v node')) !== '') {
	$prog = "global.document = { addEventListener() {} };\n"
		. "const run = (text) => { global.efb_var = { rtl: 0, text, setting: { emailSupporter: '' } };\n"
		. "  const f = new Function(require('fs').readFileSync(" . json_encode($root . '/includes/admin/assets/js/forms-efb.js') . ", 'utf8') + '; return { a: StoreExperienceSurveyTemplate_efb(), b: party_invite_form_efb(), c: recreational_Sports_league_signup_form_efb() };');\n"
		. "  return f(); };\n"
		. "const en = run({});\n"
		. "const fa = run({ tplIWasGreetedUponEntering: 'هنگام ورود به فروشگاه به من خوشامد گفته شد', tplPartyInvite: 'دعوت به مهمانی', tplKickballTuesThurs: 'کیک‌بال (سه‌شنبه/پنجشنبه)' });\n"
		. "const pick = (o, rows, k, v) => { const r = rows.find((x) => x[k] === v); return r ? r : null; };\n"
		. "console.log(JSON.stringify({ en: [en.a.some((r) => r.name === 'I was greeted upon entering the store'), en.b.some((r) => r.name === 'Party Invite')], fa: [fa.a.some((r) => r.name === 'هنگام ورود به فروشگاه به من خوشامد گفته شد'), fa.b.some((r) => r.name === 'دعوت به مهمانی'), fa.c.some((r) => r.value === 'کیک‌بال (سه‌شنبه/پنجشنبه)')], invite: (en.b.find((r) => r.type === 'step') || {}).message }));\n";
	$tmp = tempnam(sys_get_temp_dir(), 'efbtpl') . '.js';
	file_put_contents($tmp, $prog);
	$out = json_decode(trim((string) shell_exec('node ' . escapeshellarg($tmp) . ' 2>&1')), true);
	@unlink($tmp);
	t('without the phrases a template is in English', isset($out['en']) ? $out['en'] : $out, array(true, true));
	t('with the phrases translated it carries them (a label, a step title, a choice)', isset($out['fa']) ? $out['fa'] : $out, array(true, true, true));
	t('the party invitation says something, not Lorem ipsum', isset($out['invite']) ? $out['invite'] : null, "We're having a party and would love to see you. Please let us know if you can come.");
}

/* val-efb.js and the other screens: every key exists. */
preg_match_all('/"([A-Za-z0-9_]+)"\s*=>/', $fn, $km);
$keys = array_flip($km[1]);
preg_match_all("/'([A-Za-z0-9_]+)'\s*=>/", $fn, $km2);
$keys += array_flip($km2[1]);
$keys += array_flip(array_keys($ph));
$missing = array();
foreach (array('val-efb.js', 'forms-efb.js', 'list_form-efb.js') as $f) {
	$code = (string) file_get_contents($root . '/includes/admin/assets/js/' . $f);
	preg_match_all('/efb_var\.text\.([A-Za-z0-9_]+)/', $code, $um);
	foreach (array_unique($um[1]) as $k) if (!isset($keys[$k])) $missing[] = "$f: $k";
}
// rangeThumbClr / rangeValueClr are in commented-out lines of val-efb.js.
$missing = array_values(array_filter($missing, function ($x) { return !preg_match('/range(Thumb|Value)Clr$/', $x); }));
t('every efb_var.text key read by val-efb.js, forms-efb.js and list_form-efb.js is a phrase', $missing, array());
t('calcField is a core phrase ("Calculation", with a translators comment)', (bool) preg_match('#/\* translators:[^*]*\*/\s*"calcField" => [^\n]*esc_html__\(\'Calculation\',\'easy-form-builder\'\)#', $fn), true);
$val = (string) file_get_contents($root . '/includes/admin/assets/js/val-efb.js');
t('the e-mail subject box uses emailSubjectLabel (emailSubject was never a phrase)', strpos($val, 'efb_var.text.emailSubject ') === false && strpos($val, '${efb_var.text.emailSubjectLabel || \'Email subject\'}') !== false, true);

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
