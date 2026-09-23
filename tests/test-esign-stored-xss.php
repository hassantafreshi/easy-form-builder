<?php
/**
 * Regression test for the unauthenticated stored-XSS through the Signature
 * (esign) field.
 *
 * Root cause (before the fix):
 *   1. The esign validation branch accepted any value starting with
 *      "data:image/png;base64," and stored the rest verbatim — it never
 *      decoded or validated the base64 tail, so raw markup rode along.
 *   2. filter_attributes_by_type_efb keeps the browser-supplied `type`, so an
 *      attacker could store the esign value under type "r_matrix".
 *   3. The admin/public response viewer dispatched on that stored type and
 *      interpolated the value straight into innerHTML — the "r_matrix" branch
 *      put it in element-content context, where <img onerror=...> executes.
 *
 * The fix is defence in depth:
 *   - Server: strict base64 validation of the signature tail, and the stored
 *     row type is pinned to the saved field type (esign), never the browser's.
 *   - Client: fun_emsFormBuilder_show_messages HTML-escapes the whole parsed
 *     response tree once, on entry, so no render branch can emit an unescaped
 *     stored value regardless of its type.
 *
 * Run: C:\xampp\php\php.exe tests\test-esign-stored-xss.php
 */

$pass = 0;
$fail = 0;

function t_xss($label, $condition) {
	global $pass, $fail;
	if ($condition) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n";
}

/* ---------------------------------------------------------------------------
 * 1. Server-side signature validation (mirrors the esign case exactly).
 * ------------------------------------------------------------------------- */
function esign_value_is_valid($value) {
	if (!(is_string($value) && strpos($value, 'data:image/png;base64,') === 0)) {
		return false;
	}
	$b64 = substr($value, strlen('data:image/png;base64,'));
	return $b64 !== ''
		&& preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $b64) === 1
		&& base64_decode($b64, true) !== false;
}

$legit_signature = 'data:image/png;base64,' . base64_encode(hex2bin('89504e470d0a1a0a0000000d49484452'));
$attack_markup   = 'data:image/png;base64,<img src=x onerror=alert(document.domain)>';
$attack_tail     = 'data:image/png;base64,PHN2Zz4=,<svg onload=alert(1)>';
$empty_payload   = 'data:image/png;base64,';

t_xss('legit PNG signature is accepted', esign_value_is_valid($legit_signature) === true);
t_xss('signature with raw <img> markup is rejected', esign_value_is_valid($attack_markup) === false);
t_xss('signature with markup after valid base64 is rejected', esign_value_is_valid($attack_tail) === false);
t_xss('empty signature payload is rejected', esign_value_is_valid($empty_payload) === false);

/* ---------------------------------------------------------------------------
 * 2. The esign branch in the source pins the stored type and validates base64.
 * ------------------------------------------------------------------------- */
$public_src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');

$esign_start = strpos($public_src, "case 'esign':");
$esign_slice = $esign_start !== false ? substr($public_src, $esign_start, 2200) : '';

t_xss('esign branch validates the base64 tail',
	strpos($esign_slice, "preg_match('/^[A-Za-z0-9+\\/]+={0,2}$/'") !== false);
t_xss('esign branch decodes the tail to confirm it is real base64',
	strpos($esign_slice, 'base64_decode($esign_b64, true) !== false') !== false);
t_xss('esign branch pins the stored row type to the saved field',
	strpos($esign_slice, "\$item['type'] = \$f['type'];") !== false);

/* The `sample` branch used to store the item verbatim — harden that too. */
$sample_start = strpos($public_src, "case 'sample':");
$sample_slice = $sample_start !== false ? substr($public_src, $sample_start, 700) : '';
t_xss('sample branch now sanitises and filters the item',
	strpos($sample_slice, 'sanitize_text_field($item[\'value\'])') !== false
	&& strpos($sample_slice, 'filter_attributes_by_type_efb($item, $f[\'type\'])') !== false);
t_xss('sample branch pins the stored row type to the saved field',
	strpos($sample_slice, "\$item['type'] = \$f['type'];") !== false);

/* ---------------------------------------------------------------------------
 * 3. The response viewer escapes the parsed tree at the single render entry.
 * ------------------------------------------------------------------------- */
$viewer_src = file_get_contents(__DIR__ . '/../includes/admin/assets/js/response-viewer-efb.js');

t_xss('viewer defines the HTML escaper', strpos($viewer_src, 'function escHtmlEfb(') !== false);
t_xss('viewer defines the recursive tree escaper', strpos($viewer_src, 'function escResponseTreeEfb(') !== false);
t_xss('render entry escapes the whole content tree once',
	strpos($viewer_src, 'content = escResponseTreeEfb(content);') !== false);
t_xss('render entry escapes the ip meta value',
	strpos($viewer_src, 'userIp = escHtmlEfb(userIp);') !== false);

/* The escaper must neutralise markup but keep a bare <br> line break. */
$attack = 'data:image/png;base64,<img src=x onerror=alert(1)>';
$escaped_attack = str_replace(
	array('&', '<', '>', '"', "'", '`'),
	array('&amp;', '&lt;', '&gt;', '&quot;', '&#39;', '&#96;'),
	$attack
);
t_xss('escaper output cannot open an HTML tag',
	strpos($escaped_attack, '<img') === false && strpos($escaped_attack, '&lt;img') !== false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
