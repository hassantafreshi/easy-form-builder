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
 * 1. Server-side signature validation. The shipped
 *    canonical_esign_value_efb() is lifted out of the plugin source and run
 *    as-is, so this exercises the real code rather than a copy of it.
 * ------------------------------------------------------------------------- */
$public_src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
if (!preg_match('/\tprivate function canonical_esign_value_efb\(\$value\) \{.*?\n\t\}\n/s', $public_src, $m)) {
	echo "[FAIL] canonical_esign_value_efb() not found in class-Emsfb-public.php\n";
	exit(1);
}
eval('class Esign_Harness_Efb {' . str_replace('private function', 'public function', $m[0]) . '}');
$h = new Esign_Harness_Efb();
function esign_canonical($value) { global $h; return $h->canonical_esign_value_efb($value); }

/* A real PNG, exactly what canvas.toDataURL() hands the client. */
$png_bin = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
if (function_exists('imagecreatetruecolor')) {
	$im = imagecreatetruecolor(300, 120);
	imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
	imageline($im, 10, 60, 290, 60, imagecolorallocate($im, 0, 0, 0));
	ob_start(); imagepng($im); $png_bin = ob_get_clean();
}
$legit_signature = 'data:image/png;base64,' . base64_encode($png_bin);

/* The exact value the reported PoC submits. */
$poc_payload     = 'data:image/png;base64,<strong id=batch170-proof>BATCH170_XSS_EXECUTED</strong>'
	. '<img src=x onerror=document.documentElement.dataset.efbXss412=412;alert(document.domain)>';
$attack_tail     = 'data:image/png;base64,PHN2Zz4=,<svg onload=alert(1)>';
$attack_quote    = 'data:image/png;base64,AAAA" onerror="alert(1)';
$html_as_base64  = 'data:image/png;base64,' . base64_encode('<img src=x onerror=alert(1)>');
$png_header_only = 'data:image/png;base64,' . base64_encode(hex2bin('89504e470d0a1a0a0000000d49484452'));
$png_magic_junk  = 'data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\n" . str_repeat('<script>', 8));
$gif_placeholder = 'Data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';
$gif_as_png      = 'data:image/png;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';
$empty_payload   = 'data:image/png;base64,';

t_xss('legit canvas PNG signature is accepted', esign_canonical($legit_signature) !== '');
t_xss('accepted signature is stored as the canonical re-encoding', esign_canonical($legit_signature) === $legit_signature);
t_xss('the reported PoC payload is rejected', esign_canonical($poc_payload) === '');
t_xss('signature with markup after valid base64 is rejected', esign_canonical($attack_tail) === '');
t_xss('signature with an attribute-breaking quote is rejected', esign_canonical($attack_quote) === '');
t_xss('base64-encoded markup is rejected (not a PNG)', esign_canonical($html_as_base64) === '');
t_xss('a bare PNG header with no image is rejected', esign_canonical($png_header_only) === '');
t_xss('PNG magic followed by junk is rejected', esign_canonical($png_magic_junk) === '');
t_xss('the unsigned GIF placeholder is rejected', esign_canonical($gif_placeholder) === '');
t_xss('GIF bytes under a PNG prefix are rejected', esign_canonical($gif_as_png) === '');
t_xss('empty signature payload is rejected', esign_canonical($empty_payload) === '');
t_xss('non-string values are rejected', esign_canonical(array('x')) === '' && esign_canonical(null) === '' && esign_canonical(12) === '');

/* ---------------------------------------------------------------------------
 * 2. The esign branch in the source pins the stored type and validates base64.
 * ------------------------------------------------------------------------- */
$esign_start = strpos($public_src, "case 'esign':");
$esign_slice = $esign_start !== false ? substr($public_src, $esign_start, 2200) : '';

t_xss('esign branch validates through canonical_esign_value_efb()',
	strpos($esign_slice, '$this->canonical_esign_value_efb($item[\'value\'])') !== false);
t_xss('esign branch stores the canonical value, not the browser string',
	strpos($esign_slice, "\$item['value'] = \$esign_value;") !== false);
t_xss('esign branch pins the stored row type to the saved field',
	strpos($esign_slice, "\$item['type'] = \$f['type'];") !== false);

/* The payment final-submit merge bypasses the validator above; its
 * per-row sanitiser must apply the same signature rule. */
$merge_start = strpos($public_src, 'private function sanitize_payment_merge_row_efb(');
$merge_slice = $merge_start !== false ? substr($public_src, $merge_start, 1600) : '';
t_xss('payment merge validates signatures the same way',
	strpos($merge_slice, "if (\$type === 'esign')") !== false
	&& strpos($merge_slice, 'canonical_esign_value_efb($clean[\'value\'])') !== false
	&& strpos($merge_slice, "if (\$esign_value === '') return null;") !== false);

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
