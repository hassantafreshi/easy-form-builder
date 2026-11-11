<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Server side of the length, number, selection and disabled-field limits
 * (docs/feature-gaps/BUGS-FOUND.fa.md, "اعتبارسنجی"):
 *
 *   - email/url: a Max length of "0" or "" refused every non-empty value.
 *   - textarea: each line break ("@n#" on the wire) counted as 3 characters.
 *   - multiselect: minSelect/maxSelect were never checked on the server.
 *   - disabled field: a one-character value slipped through, and
 *     "$still_processing == false;" did nothing.
 *   - number: Max was printed as maxlength="%d" (decimals dropped) and the
 *     page had no min/max at all.
 *
 * The helpers are lifted out of the plugin source and run as they are, with
 * a stub for esc_attr(), so this exercises the shipped code. The validator
 * itself is one long inline method, so its call sites are checked in the
 * source.
 *
 * Run: php tests/test-validation-limits.php
 */

$pass = 0;
$fail = 0;
function t_lim($label, $ok) {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n";
}

if (!function_exists('esc_attr')) {
	function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
}

/** Lifts "private function NAME(...) { ... }" (tab-indented class method) out of $src. */
function lift_method_efb($src, $name) {
	if (!preg_match('/\tprivate function ' . preg_quote($name, '/') . '\(.*?\n\t\}\n/s', $src, $m)) {
		echo "[FAIL] $name() not found\n";
		exit(1);
	}
	return str_replace('private function', 'public function', $m[0]);
}

$public_src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$builder_src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-formbuilder.php');

eval('class Limits_Harness_Efb {'
	. lift_method_efb($public_src, 'submitted_text_length_efb')
	. lift_method_efb($public_src, 'length_outside_limits_efb')
	. lift_method_efb($public_src, 'selection_count_within_limits_efb')
	. lift_method_efb($public_src, 'disabled_field_value_submitted_efb')
	. lift_method_efb($builder_src, 'generateLengthAttributes_efb')
	. '}');
$h = new Limits_Harness_Efb();

/* ---- email / url Max length ------------------------------------------- */
$addr = 'visitor@example.com';
$l = strlen($addr);
t_lim('mlen "0" is no maximum', !$h->length_outside_limits_efb(array('mlen' => '0'), $l));
t_lim('mlen "" is no maximum', !$h->length_outside_limits_efb(array('mlen' => ''), $l));
t_lim('mlen 0 (int) is no maximum', !$h->length_outside_limits_efb(array('mlen' => 0), $l));
t_lim('missing mlen/milen: no limit (old forms)', !$h->length_outside_limits_efb(array(), $l));
t_lim('milen "" is no minimum', !$h->length_outside_limits_efb(array('milen' => ''), $l));
t_lim('a real Max still refuses a longer value', $h->length_outside_limits_efb(array('mlen' => '10'), $l));
t_lim('a real Max accepts a value at the limit', !$h->length_outside_limits_efb(array('mlen' => (string) $l), $l));
t_lim('a real Min still refuses a shorter value', $h->length_outside_limits_efb(array('milen' => '30'), $l));
t_lim('Min and Max both respected', !$h->length_outside_limits_efb(array('milen' => '5', 'mlen' => '0'), $l));

$email_case = substr($public_src, strpos($public_src, "case 'email':"), 1600);
$url_case = substr($public_src, strpos($public_src, "case 'url':"), 1600);
t_lim('email branch uses length_outside_limits_efb()', strpos($email_case, '$this->length_outside_limits_efb($f, $l)') !== false);
t_lim('url branch uses length_outside_limits_efb()', strpos($url_case, '$this->length_outside_limits_efb($f, $l)') !== false);
t_lim('the "$f[\'mlen\'] < $l" comparison is gone', strpos($public_src, "\$f['mlen'] < \$l") === false);

/* ---- textarea line breaks -------------------------------------------- */
t_lim('"a@n#b" is 3 characters, not 5', $h->submitted_text_length_efb('a@n#b') === 3);
t_lim('three line breaks count three', $h->submitted_text_length_efb('one@n#two@n#three@n#four') === 3 + 3 + 5 + 4 + 3);
t_lim('Persian text counts code points', $h->submitted_text_length_efb('سلام@n#دنیا') === 9);
t_lim('plain text unchanged', $h->submitted_text_length_efb('hello') === 5);
$default_case = substr($public_src, strpos($public_src, "\t\t\t\t\t\t\t\tdefault:\n\t\t\t\t\t\t\t\t\t\$is_valid = 0;"), 2400);
t_lim('default (text/textarea) branch measures with submitted_text_length_efb()', strpos($default_case, '$l = $this->submitted_text_length_efb($item[\'value\']);') !== false);

/* ---- multiselect minSelect / maxSelect ------------------------------- */
$f = array('minSelect' => '2', 'maxSelect' => '3');
t_lim('1 pick under minSelect 2 is refused', !$h->selection_count_within_limits_efb($f, 1));
t_lim('2 picks accepted', $h->selection_count_within_limits_efb($f, 2));
t_lim('3 picks accepted', $h->selection_count_within_limits_efb($f, 3));
t_lim('4 picks over maxSelect 3 are refused', !$h->selection_count_within_limits_efb($f, 4));
t_lim('missing limits: any count accepted (old forms)', $h->selection_count_within_limits_efb(array(), 9));
t_lim('empty limits: no limit', $h->selection_count_within_limits_efb(array('minSelect' => '', 'maxSelect' => ''), 9));
t_lim('minSelect 0 (new field default): no minimum', $h->selection_count_within_limits_efb(array('minSelect' => 0, 'maxSelect' => 2), 1));
t_lim('non-numeric limit ignored', $h->selection_count_within_limits_efb(array('maxSelect' => 'abc'), 9));
$ms_case = substr($public_src, strpos($public_src, "case 'multiselect':"), 2200);
t_lim('multiselect branch checks the picked count', strpos($ms_case, '$this->selection_count_within_limits_efb($f, count($picked))') !== false);

/* Mirror of the multiselect branch's matching loop, to pin how picks are counted. */
$options = array(
	array('type' => 'multiselect', 'id_' => 'ms'),
	array('type' => 'option', 'parent' => 'ms', 'value' => 'Red'),
	array('type' => 'option', 'parent' => 'ms', 'value' => 'Green'),
	array('type' => 'option', 'parent' => 'ms', 'value' => 'Blue'),
);
$count_picks = function ($value) use ($options) {
	$rs = explode('@efb!', $value);
	$picked = array();
	foreach ($options as $fr) {
		foreach ($rs as $k => $v) {
			if ($fr['type'] == 'option' && $fr['value'] === $v && $fr['parent'] == 'ms') $picked[$k] = true;
		}
	}
	return count($picked);
};
t_lim('"Red@efb!Blue@efb!" is 2 picks', $count_picks('Red@efb!Blue@efb!') === 2);
t_lim('an unknown option is not counted', $count_picks('Red@efb!Pink@efb!') === 1);

/* ---- disabled field -------------------------------------------------- */
t_lim('one-character value in a disabled field is refused', $h->disabled_field_value_submitted_efb(1));
t_lim('empty value in a disabled field is fine', !$h->disabled_field_value_submitted_efb(0));
t_lim('longer value still refused', $h->disabled_field_value_submitted_efb(12));
t_lim('"$still_processing == false;" no-op is gone', strpos($public_src, '$still_processing == false;') === false);
t_lim('disabled branch uses disabled_field_value_submitted_efb()', strpos($public_src, '&& $this->disabled_field_value_submitted_efb($item_value_len)') !== false);

/* ---- number min/max on the page -------------------------------------- */
$attrs = function ($milen, $mlen) use ($h) {
	$vj = new stdClass();
	if ($milen !== null) $vj->milen = $milen;
	if ($mlen !== null) $vj->mlen = $mlen;
	$r = $h->generateLengthAttributes_efb('number', $vj);
	return $r['minlen'] . ' ' . $r['maxlen'];
};
$a = $attrs('-10', '9.5');
t_lim('negative min printed as is', strpos($a, 'min="-10"') !== false);
t_lim('decimal max printed whole (not cut by %d)', strpos($a, 'max="9.5"') !== false);
t_lim('legacy maxlength kept for older core-efb.js', strpos($a, 'maxlength="9"') !== false);
t_lim('step="any" so decimals are not :invalid', strpos($a, 'step="any"') !== false);
$a = $attrs('1.5', '0');
t_lim('mlen "0" prints no max', strpos($a, 'max=') === false && strpos($a, 'maxlength') === false);
t_lim('decimal min printed whole', strpos($a, 'min="1.5"') !== false);
$a = $attrs('', '');
t_lim('empty min/max print no bounds', strpos($a, 'min=') === false && strpos($a, 'minlength') === false && strpos($a, 'max=') === false);
$a = $attrs(null, null);
t_lim('missing min/max print no bounds', trim($a) === 'step="any"');
$a = $attrs('" onfocus="x', '5');
t_lim('non-numeric min is not printed (no attribute injection)', strpos($a, 'onfocus') === false);
$a = $attrs(null, '-5');
t_lim('negative max printed (server enforces it too)', strpos($a, 'max="-5"') !== false && strpos($a, 'maxlength') === false);

$vj = new stdClass(); $vj->milen = '3'; $vj->mlen = '20';
$r = $h->generateLengthAttributes_efb('text', $vj);
t_lim('text fields unchanged: minlength/maxlength', $r['minlen'] === 'minlength="3"' && $r['maxlen'] === 'maxlength="20"');
$vj = new stdClass(); $vj->mlen = '0';
$r = $h->generateLengthAttributes_efb('email', $vj);
t_lim('email with mlen "0" gets no maxlength (browser accepts, as the server now does)', $r['maxlen'] === '');

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
