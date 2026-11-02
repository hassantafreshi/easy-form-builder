<?php
/**
 * A correct email address typed on a Persian/Arabic keyboard, or pasted with
 * invisible direction marks, must not fail as "not valid".
 *
 * Run inside WordPress, or with EFB_WP_LOAD pointing at wp-load.php:
 *   php tests/test-email-input-normalize.php
 */
$wp_load = getenv('EFB_WP_LOAD') ?: dirname(__DIR__, 4) . '/wp-load.php';
if (!is_readable($wp_load)) {
	echo "[SKIP] WordPress bootstrap was not found at {$wp_load}\n";
	exit(0);
}
require_once $wp_load;
$find = function () {
	foreach (get_declared_classes() as $c) {
		if (method_exists($c, 'normalize_email_input_efb')) { return $c; }
	}
	return null;
};
$cls = $find();
if (null === $cls) {
	require_once dirname(__DIR__) . '/includes/class-Emsfb-public.php';
	$cls = $find();
}
$passed = 0; $failed = 0;
function efb_ein_check($label, $ok) {
	global $passed, $failed;
	if ($ok) { $passed++; echo "[PASS] {$label}\n"; } else { $failed++; echo "[FAIL] {$label}\n"; }
}
efb_ein_check('the public class has normalize_email_input_efb()', null !== $cls);
if (null !== $cls) {
	$n = function ($v) use ($cls) { return $cls::normalize_email_input_efb($v); };
	$cases = array(
		"ali\u{06F1}\u{06F2}\u{06F3}@gmail.com"   => 'ali123@gmail.com',
		"ali\u{0661}\u{0662}@gmail.com"           => 'ali12@gmail.com',
		"\u{200F}sara@gmail.com\u{200E}"          => 'sara@gmail.com',
		"sara\u{200C}@gmail.com"                  => 'sara@gmail.com',
		"x\u{FF20}gmail.com"                       => 'x@gmail.com',
		"a@gmail\u{066B}com"                       => 'a@gmail.com',
		"\u{00A0} user@example.com \u{FEFF}"       => 'user@example.com',
	);
	foreach ($cases as $in => $out) {
		$got = $n($in);
		efb_ein_check('normalised: ' . json_encode($in) . ' -> ' . $out, $got === $out && false !== filter_var(sanitize_email($got), FILTER_VALIDATE_EMAIL));
	}
	efb_ein_check('a plain address is unchanged', 'a.b+tag@example.co.uk' === $n('a.b+tag@example.co.uk'));
	efb_ein_check('a non-address stays invalid', false === filter_var(sanitize_email($n('not an email')), FILTER_VALIDATE_EMAIL));
	efb_ein_check('non-strings pass through', array(1) === $n(array(1)));
	$src = file_get_contents(dirname(__DIR__) . '/includes/class-Emsfb-public.php');
	efb_ein_check('email fields are normalised before validation', false !== strpos($src, "sanitize_email(self::normalize_email_input_efb(\$item['value']))"));
	efb_ein_check('register and login emails are normalised', false !== strpos($src, "\$email = self::normalize_email_input_efb(\$rv['value'])") && false !== strpos($src, "\$username = self::normalize_email_input_efb(\$value['value'])"));
}
echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
