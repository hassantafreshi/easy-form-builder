<?php
/**
 * F07-A: the upload keys survive a real save round trip (as the builder sends them):
 *   JSON -> emsfb_json_normalize_efb -> json_decode -> sanitize_obj_msg_efb (explicit cases ->
 *   Uploads::sanitize_key) -> Uploads::normalize_rows -> json_encode -> stored -> json_decode.
 * The same vectors as the browser (tests/fixtures/upload-keys-vectors.json, test-upload-keys-parity.js);
 * min_files above max_files, a total under the per-file size, the camera on a document field and the
 * Media Library for a user who may not upload are fixed by the server; old forms come back unchanged;
 * the effective configuration follows the plan; the save refreshes the stale cache keys (§5, playbook §2.8).
 *
 * Run: php tests/test-upload-keys-roundtrip.php
 */
define('ABSPATH', __DIR__ . '/');
define('EMSFB_PLUGIN_VERSION', '4.3.1-test');
$EFB = __DIR__ . '/..';

function sanitize_text_field($v) {
	if (is_array($v) || is_object($v)) return '';
	$s = strip_tags((string) $v);
	$s = preg_replace('/%[a-f0-9]{2}/i', '', $s);
	$s = preg_replace('/[\r\n\t ]+/', ' ', $s);
	return trim($s);
}
function wp_check_invalid_utf8($s) { return preg_match('//u', (string) $s) ? (string) $s : ''; }
function sanitize_email($v) { return filter_var((string) $v, FILTER_SANITIZE_EMAIL); }
function sanitize_url($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function sanitize_file_name($v) { $v = preg_replace('/[^\p{L}\p{N}._ -]/u', '', (string) $v); return preg_replace('/\s+/', '-', trim($v)); }
function is_email($v) { return filter_var((string) $v, FILTER_VALIDATE_EMAIL) !== false; }
function esc_url_raw($v) { return filter_var((string) $v, FILTER_SANITIZE_URL); }
function wp_kses_post($v) { return strip_tags((string) $v, '<b><strong><em><i><br><p><a>'); }
function esc_html__($s, $d = null) { return $s; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
function __($s, $d = null) { return $s; }
$GLOBALS['filters'] = array();
function apply_filters($h, $v) { $a = func_get_args(); array_shift($a); foreach ($GLOBALS['filters'][$h] ?? array() as $cb) $a[0] = call_user_func_array($cb, $a); return $a[0]; }
function add_filter($h, $cb = null) { if ($cb) $GLOBALS['filters'][$h][] = $cb; return true; }
function add_action() { return true; }
function do_action() {}
function wp_cache_get() { return false; }
function wp_cache_set() { return true; }
function wp_cache_delete() { return true; }
$GLOBALS['opts'] = array();
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['opts']) ? $GLOBALS['opts'][$k] : $d; }
function update_option($k, $v) { $GLOBALS['opts'][$k] = $v; return true; }
function absint($v) { return abs(intval($v)); }
function wp_json_encode($v) { return json_encode($v); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
$GLOBALS['can'] = true;
function current_user_can($c) { return $GLOBALS['can']; }

require $EFB . '/includes/json-efb.php';
require $EFB . '/includes/class-Emsfb-upload-guard.php';
require $EFB . '/includes/class-Emsfb-uploads.php';
require $EFB . '/includes/functions.php';

use Emsfb\Uploads;

$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
	$fail++;
	echo "[FAIL] $label\n  expected " . json_encode($expected) . "\n  actual   " . json_encode($actual) . "\n";
}

/* 1. The shared vectors. */
$vectors = json_decode(file_get_contents(__DIR__ . '/fixtures/upload-keys-vectors.json'), true)['vectors'];
foreach ($vectors as $v) t("sanitize_key({$v[0]}, " . json_encode($v[1]) . ')', Uploads::sanitize_key($v[0], $v[1]), $v[2]);
t('an array is empty', Uploads::sanitize_key('max_files', array(5)), '');

/* 2. The save round trip, as update_form_id_Emsfb() runs it. */
$fn = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();
$form = array(
	array('type' => 'form', 'steps' => '1', 'formName' => 'Docs', 'email' => '', 'email_attach' => 1, 'trackingCode' => true),
	array('id_' => '1', 'type' => 'step', 'step' => '1', 'name' => 'S'),
	array('id_' => 'docs', 'type' => 'file', 'file' => 'image', 'value' => 'image', 'name' => 'Documents', 'step' => '1', 'max_fsize' => '10',
		'max_files' => '۵', 'min_files' => '7', 'max_total_mb' => '4', 'capture' => 'environment', 'img_max_px' => '1920', 'img_quality' => '0.8', 'file_store' => 'private'),
	array('id_' => 'pdfs', 'type' => 'dadfile', 'file' => 'document', 'value' => 'document', 'name' => 'PDFs', 'step' => '1',
		'max_files' => '3', 'capture' => 'user', 'img_max_px' => '1200', 'file_store' => 'media'),
	array('id_' => 'old', 'type' => 'file', 'file' => 'zip', 'value' => 'zip', 'name' => 'Old', 'step' => '1'),
	array('id_' => 'rec', 'type' => 'audio_recorder', 'name' => 'Voice', 'step' => '1', 'max_files' => '4', 'file_store' => 'private'),
	array('id_' => 'bad', 'type' => 'file', 'name' => 'Bad', 'step' => '1', 'max_files' => array(9), 'file_store' => '../../etc', 'capture' => '<script>x'),
);
$posted = str_replace('\\"', '"', json_encode($form, JSON_UNESCAPED_UNICODE)); // builder transport
$valp = json_decode(emsfb_json_normalize_efb($posted), true);
$valp = $fn->sanitize_obj_msg_efb($valp);
$GLOBALS['can'] = false; // the saving user may not upload files: no Media Library
list($valp, $warnings) = Uploads::normalize_rows($valp, null);
$stored = str_replace('"', '\\"', json_encode($valp, JSON_UNESCAPED_UNICODE));
$back = json_decode(emsfb_json_normalize_efb($stored), true);
$docs = $back[2]; $pdfs = $back[3]; $old = $back[4]; $rec = $back[5]; $bad = $back[6];
t('max_files in Persian digits is stored as 5', $docs['max_files'], '5');
t('min_files above max_files becomes max_files', $docs['min_files'], '5');
t('a total under the per-file size becomes the per-file size', $docs['max_total_mb'], '10');
t('the camera of an image field survives', $docs['capture'], 'environment');
t('img_max_px and img_quality survive', array($docs['img_max_px'], $docs['img_quality']), array('1920', '0.8'));
t('file_store private survives', $docs['file_store'], 'private');
t('the camera is cleared on a document field', $pdfs['capture'], '');
t('resizing is cleared on a document field', $pdfs['img_max_px'], '');
t('Media Library refused for a user who may not upload', $pdfs['file_store'], '');
t('form key email_attach survives as "1"', $back[0]['email_attach'], '1');
t('an old field without the keys comes back unchanged', array_keys($old), array('id_', 'type', 'file', 'value', 'name', 'step'));
t('a recorder never keeps the keys (one recording, uploads folder)', array(isset($rec['max_files']), isset($rec['file_store'])), array(false, false));
t('hostile values are emptied', array($bad['max_files'], $bad['file_store'], $bad['capture']), array('', '', ''));
$codes = array_map(function ($w) { return $w['code']; }, $warnings);
t('save warnings: total raised, media denied', array(in_array('upload_total_lt_file', $codes, true), in_array('upload_media_denied', $codes, true)), array(true, true));
$GLOBALS['can'] = true;

/* 3. security_changes: file_store / max_files changed on a stored form. */
$old_rows = $back;
$next = $back; $next[2]['file_store'] = ''; $next[2]['max_files'] = '2';
list(, $w2) = Uploads::normalize_rows($next, $old_rows);
$sec = array_values(array_filter($w2, function ($w) { return $w['code'] === 'upload_security_change'; }));
t('a change of file_store and max_files is reported', isset($sec[0]) ? count($sec[0]['changes']) : 0, 2);

/* 4. The effective configuration follows the plan (Free: one file, no resize, no Media Library). */
$GLOBALS['opts']['emsfb_pro'] = 2;
$c = Uploads::config($docs);
t('Free: one file, minimum 1 at most', array($c['multi'], $c['max_files'], $c['min_files']), array(false, 1, 1));
t('Free: no resizing; private store and camera stay (free parts)', array($c['img_max_px'], $c['file_store'], $c['capture']), array(0, 'private', 'environment'));
t('Free: the Media Library is not used', Uploads::config(array('type' => 'file', 'max_files' => '3', 'file_store' => 'media'))['file_store'], '');
$GLOBALS['opts']['emsfb_pro'] = 3;
$c = Uploads::config($docs);
t('Free Plus: five files, the minimum, the total in bytes', array($c['multi'], $c['max_files'], $c['min_files'], $c['max_total_bytes']), array(true, 5, 5, 10 * 1048576));
t('Free Plus: no e-mail attachments (Pro)', Uploads::plan_ok('attach'), false);
$GLOBALS['opts']['emsfb_pro'] = 1;
t('Pro: e-mail attachments', Uploads::plan_ok('attach'), true);
$GLOBALS['opts']['emsfb_pro'] = 3;
t('a field without the keys = today', array_intersect_key(Uploads::config($old), array_flip(array('max_files', 'min_files', 'max_mb', 'file_store'))), array('max_files' => 1, 'min_files' => 0, 'max_mb' => 20.0, 'file_store' => ''));
t('emsfb_upload_max_files_cap lowers the ceiling', (function () { add_filter('emsfb_upload_max_files_cap', function () { return 3; }); $v = Uploads::sanitize_key('max_files', '10'); $GLOBALS['filters']['emsfb_upload_max_files_cap'] = array(); return $v; })(), '3');

/* 5. The rendered attributes (one accept list with the server's extensions). */
$a = Uploads::input_attributes((object) $docs);
t('input: accept lists the image extensions the server keeps', (bool) preg_match('/accept="[^"]*\.png[^"]*\.webp/', $a), true);
t('input: no SVG or HTML in accept', (bool) preg_match('/svg|html/', $a), false);
t('input: multiple, capture and the limits', array(strpos($a, ' multiple') !== false, strpos($a, 'capture="environment"') !== false, strpos($a, 'data-max-files="5"') !== false, strpos($a, 'data-img-max="1920"') !== false), array(true, true, true, true));
t('input: one file has no multiple', strpos(Uploads::input_attributes((object) $old), ' multiple'), false);
t('accept of a custom list keeps only allowed, non-executable extensions', Uploads::accept_attribute('customize', 'pdf, php, svg, png'), '.pdf,.png,image/png');

/* 6. The save refreshes the keys the upload route and the quota read (stale for an hour before). */
$admin = file_get_contents($EFB . '/includes/admin/class-Emsfb-admin.php');
t('update_form_id_Emsfb refreshes the [form_structer] cache key', (bool) preg_match("/update_form_cache_efb\\(\\\$id, \\(object\\) array\\('form_structer' => \\\$value_\\), array\\('form_structer'\\)\\)/", $admin), true);
t('update_form_id_Emsfb drops the upload slot count', strpos($admin, "wp_cache_delete('upload_fields:' . \$id, 'emsfb')") !== false, true);
t('sanitize_obj_msg_efb has explicit cases for the keys', (bool) preg_match("/case 'max_files':.*?case 'email_attach':.*?Uploads::sanitize_key/s", file_get_contents($EFB . '/includes/functions.php')), true);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
