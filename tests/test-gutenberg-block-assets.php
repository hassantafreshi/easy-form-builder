<?php
/**
 * QA1-007 / QA1-008 / QA1-026 (FX-ADMIN): how the Easy Form Builder block brings its script and style to the editor.
 *  - block.json declares editorScript "file:./editor.js" with its asset file, editorStyle and the text domain, so
 *    WordPress registers the script with the block and loads its translations (QA1-007);
 *  - the PHP class enqueues neither the script nor the style a second time (QA1-026: the style reached the editor twice
 *    and WordPress warned that it was added to the iframe the wrong way); it only hands the script its data;
 *  - editor.js: the shortcode is a left-to-right <code>, the sentence dir="auto", the form name and code isolated (QA1-008).
 * The editor itself is checked by e2e qa1-block (fa_IR 375 / 1280, en 1280).
 *
 * Run: php tests/test-gutenberg-block-assets.php
 */
$root = dirname(__DIR__) . '/includes/page-builders/gutenberg/';
$pass = 0;
$fail = 0;
function t($name, $ok, $detail = '') {
	global $pass, $fail;
	if ($ok) { $pass++; echo "[PASS] $name\n"; } else { $fail++; echo "[FAIL] $name" . ($detail !== '' ? ' — ' . json_encode($detail) : '') . "\n"; }
}
$bj = json_decode((string) file_get_contents($root . 'block.json'), true);
t('block.json: editorScript file:./editor.js', isset($bj['editorScript']) && $bj['editorScript'] === 'file:./editor.js', $bj['editorScript'] ?? null);
t('block.json: editorStyle file:./editor.css and the text domain easy-form-builder', ($bj['editorStyle'] ?? '') === 'file:./editor.css' && ($bj['textdomain'] ?? '') === 'easy-form-builder');
define('ABSPATH', '/');
define('EMSFB_PLUGIN_VERSION', '9.9.9');
$asset = include $root . 'editor.asset.php';
t('editor.asset.php: the script\'s dependencies (wp-i18n among them) and the plugin version', is_array($asset) && in_array('wp-i18n', $asset['dependencies'], true) && in_array('wp-blocks', $asset['dependencies'], true) && $asset['version'] === '9.9.9', $asset);
$php = (string) file_get_contents($root . 'class-Emsfb-gutenberg-block.php');
t('the class enqueues no style or script of its own (block.json does)', strpos($php, 'wp_enqueue_style') === false && strpos($php, 'wp_enqueue_script') === false && strpos($php, 'wp_register_script') === false && strpos($php, 'wp_register_style') === false);
t('the class hands its data to the block\'s own script handle', strpos($php, "generate_block_asset_handle('easy-form-builder/form', 'editorScript')") !== false && strpos($php, "wp_localize_script(\$handle, 'efbBlockData'") !== false);
$js = (string) file_get_contents($root . 'editor.js');
t('editor.js: the shortcode <code> is dir="ltr"', (bool) preg_match("/'code',\s*\{\s*(?:\/\/[^\n]*\n\s*)?dir: 'ltr'/", $js));
t('editor.js: the preview sentence is dir="auto"', strpos($js, "{ dir: 'auto', style: { margin: '0 0 10px 0', fontSize: '14px' } },\n                                __('Form will be displayed here on the frontend.'") !== false);
t('editor.js: a form\'s name and code are isolated (<bdi>)', strpos($js, "wp.element.createElement('bdi', { key: 'n' }, form.name)") !== false && strpos($js, "wp.element.createElement('bdi', { key: 'i', dir: 'ltr' }, String(form.id))") !== false);
t('editor.js: every visible English word goes through __() (no bare "Easy Form Builder")', !preg_match("/^\s*'Easy Form Builder'\s*$/m", $js));
echo "\nRESULTS: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
