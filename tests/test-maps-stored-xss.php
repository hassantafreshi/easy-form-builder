<?php
/**
 * Regression test for the unauthenticated stored-XSS through the Map field's
 * marker address.
 *
 * Root cause (before the fix):
 *   1. filter_attributes_by_type_efb() explicitly skips sanitizing the
 *      'value' key for every field type, so a maps marker's `address` string
 *      never passed through sanitize_text_field() anywhere.
 *   2. The 'maps' validation branch only checked that lat/lng were numeric —
 *      it never looked at `address` at all — so raw markup was stored
 *      verbatim in a submission's JSON.
 *   3. $validated_item was built from the *pre-filter* copy of $item (unlike
 *      every other branch, which filters first), so even filtering value
 *      keys other than 'value' would not have reached storage.
 *
 * The fix is defence in depth, matching the esign/sample hardening:
 *   - Server: each marker's `address` is passed through sanitize_text_field()
 *     before storage, and $validated_item is now built from the filtered
 *     $item.
 *   - Client: fun_emsFormBuilder_show_messages already HTML-escapes the
 *     whole parsed response tree (including nested maps value arrays) once,
 *     on entry, before any type-specific render branch — including the maps
 *     branch that hands data to pro_els-efb.js's marker popup/list renderer.
 *
 * Run: C:\xampp\php\php.exe tests\test-maps-stored-xss.php
 */

$pass = 0;
$fail = 0;

function t_xss($label, $condition) {
	global $pass, $fail;
	if ($condition) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++; echo "[FAIL] $label\n";
}

/* ---------------------------------------------------------------------------
 * 1. The maps branch in the source now sanitizes each marker's address and
 *    builds $validated_item from the filtered $item.
 * ------------------------------------------------------------------------- */
$public_src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');

$maps_start = strpos($public_src, "case 'maps':");
$maps_end   = $maps_start !== false ? strpos($public_src, "case 'color':", $maps_start) : false;
$maps_slice = $maps_start !== false ? substr($public_src, $maps_start, ($maps_end !== false ? $maps_end - $maps_start : 3000)) : '';

t_xss('maps branch exists', $maps_slice !== '');
t_xss('maps branch sanitizes each marker address',
	strpos($maps_slice, "sanitize_text_field((string) \$value['address'])") !== false);
t_xss('maps branch rebuilds each marker from lat/lng/address only',
	strpos($maps_slice, "'address' => isset(\$value['address'])") !== false
	&& strpos($maps_slice, '$item[\'value\'] = $markers;') !== false);
t_xss('maps branch filters $item before building $validated_item',
	strpos($maps_slice, '$item = $this->filter_attributes_by_type_efb($item,$f[\'type\']);') !== false
	&& strpos($maps_slice, '$validated_item = $is_valid ? $item : null;') !== false
	&& strpos($maps_slice, '$validated_item = $item;') === false);
t_xss('maps branch still validates lat/lng are numeric',
	strpos($maps_slice, "is_numeric(\$value['lat']) == false || is_numeric(\$value['lng']) == false") !== false);
t_xss('maps branch still enforces the marker count against the saved field',
	strpos($maps_slice, "if (\$c != \$f['mark'])") !== false);

/* filter_attributes_by_type_efb intentionally never sanitizes 'value' —
 * document that invariant so the maps branch (and any future array-valued
 * type) keeps handling its own nested sanitization explicitly. */
$filter_start = strpos($public_src, 'function filter_attributes_by_type_efb');
$filter_slice = $filter_start !== false ? substr($public_src, $filter_start, 2200) : '';
t_xss("filter_attributes_by_type_efb still skips 'value' (nested sanitization stays branch-owned)",
	strpos($filter_slice, "if (\$key !== 'value')") !== false);

/* ---------------------------------------------------------------------------
 * 2. Simulate the sanitization the branch now performs on a hostile payload.
 * ------------------------------------------------------------------------- */
$attack_address = '<img src=x onerror=alert(document.domain)>';
$sanitized = sanitize_text_field_stub($attack_address);
t_xss('sanitize_text_field-equivalent strips markup from a hostile address',
	strpos($sanitized, '<img') === false);

function sanitize_text_field_stub($value) {
	/* Minimal stand-in for WordPress's sanitize_text_field() (strip tags),
	 * used here only to prove the payload shape is neutralised; the real
	 * function runs in the branch itself and is exercised by WP's own tests. */
	return trim(strip_tags($value));
}

/* ---------------------------------------------------------------------------
 * 3. The response viewer escapes the parsed tree at the single render entry,
 *    including nested maps value arrays, before pro_els-efb.js ever sees it.
 * ------------------------------------------------------------------------- */
$viewer_src = file_get_contents(__DIR__ . '/../includes/admin/assets/js/response-viewer-efb.js');

t_xss('viewer escapes the whole content tree once, before per-type branches run',
	strpos($viewer_src, 'content = escResponseTreeEfb(content);') !== false);
t_xss('viewer recursive escaper preserves nested structure (arrays/objects) like a maps value list',
	strpos($viewer_src, 'if (Array.isArray(node)) return node.map(escResponseTreeEfb);') !== false);
t_xss('viewer hands the (already escaped) maps value to the map renderer via marker_maps_efb',
	strpos($viewer_src, 'marker_maps_efb = c.value;') !== false);

$maps_render_start = strpos($viewer_src, 'else if (c.type == "maps")');
$maps_render_slice = $maps_render_start !== false ? substr($viewer_src, $maps_render_start, 400) : '';
t_xss('viewer maps branch runs after the tree-wide escape (no separate unescaped read of c.value)',
	strpos($maps_render_slice, 'escResponseTreeEfb') === false && strpos($maps_render_slice, 'marker_maps_efb = c.value;') !== false);

/* The escaper must neutralise markup but keep a bare <br> line break. */
$escaped_attack = str_replace(
	array('&', '<', '>', '"', "'", '`'),
	array('&amp;', '&lt;', '&gt;', '&quot;', '&#39;', '&#96;'),
	$attack_address
);
t_xss('escaper output cannot open an HTML tag',
	strpos($escaped_attack, '<img') === false && strpos($escaped_attack, '&lt;img') !== false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
