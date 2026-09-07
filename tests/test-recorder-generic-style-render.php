<?php
/**
 * Regression test: generic style settings (Height, Corners, Border Color, CSS Classes)
 * must reach the recorder shell in BOTH markup factories, so the builder dropzone and
 * the published form actually show them. See docs/recorder-fields.md.
 * Run: php tests/test-recorder-generic-style-render.php
 */

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://example.test' . $path;
    }
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
}

$pass = 0;
$fail = 0;

function check($label, $condition) {
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "[PASS] $label\n";
        return;
    }

    $fail++;
    echo "[FAIL] $label\n";
}

require_once __DIR__ . '/../includes/class-Emsfb-formbuilder.php';

$texts = array(
    'recWatermark' => 'Made by Easy Form Builder',
    'recTapToStart' => 'Tap to start recording',
    'recPause' => 'Pause',
    'recStart' => 'Start Recording',
    'recResume' => 'Resume',
    'recRedo' => 'Re-record',
    'recPlay' => 'Play',
    'recReady' => 'Ready to record',
    'recDownload' => 'Download recording',
    'recUpload' => 'Upload',
);

function render_recorder($vj, $texts) {
    $builder = new \Emsfb\Formbuilder(array($vj), false);
    $method = new ReflectionMethod(\Emsfb\Formbuilder::class, 'ui_recorder_efb');
    $method->setAccessible(true);
    return $method->invoke($builder, $vj, 123, $texts, '');
}

function shell_class_attr($html) {
    return preg_match('/<div class="([^"]*efb-recorder-shell[^"]*)"/', $html, $m) ? $m[1] : '';
}

foreach (array('audio_recorder', 'video_recorder', 'screen_recorder') as $kind) {
    $vj = (object) array(
        'type' => $kind,
        'id_' => 'zTest123',
        'required' => 1,
        'el_height' => 'h-xl-efb',
        'corner' => 'rounded-3',
        'el_border_color' => 'border-colorDEfb-ff0000',
        'classes' => 'my-css,other-css',
    );

    $html = render_recorder($vj, $texts);
    $shellClass = shell_class_attr($html);

    check("$kind: shell carries el_height", strpos($shellClass, 'h-xl-efb') !== false);
    check("$kind: shell carries corner", strpos($shellClass, 'rounded-3') !== false);
    check("$kind: shell carries border color", strpos($shellClass, 'border-colorDEfb-ff0000') !== false);
    check("$kind: shell carries custom CSS classes", strpos($shellClass, 'my-css other-css') !== false);
    check("$kind: shell carries data-css for the classes pipeline", strpos($html, 'data-css="zTest123"') !== false);
    check("$kind: hidden file input keeps required", strpos($html, 'required') !== false);
}

// Legacy saved forms (props absent) must fall back to the same defaults the
// builder-side class-swap handlers expect to find in the markup.
$legacy = (object) array('type' => 'audio_recorder', 'id_' => 'zLegacy', 'required' => 0);
$legacyClass = shell_class_attr(render_recorder($legacy, $texts));
check('legacy field: default height token present', strpos($legacyClass, 'h-d-efb') !== false);
// A field saved without a corner takes the plugin-wide default, which is rounded-3
// (it was efb-square before; efb-square is still honoured when a form stores it).
check('legacy field: default corner token present', strpos($legacyClass, 'rounded-3') !== false);
$square = (object) array('type' => 'audio_recorder', 'id_' => 'zSquare', 'required' => 0, 'corner' => 'efb-square');
check('saved efb-square corner is still rendered', strpos(shell_class_attr(render_recorder($square, $texts)), 'efb-square') !== false);
check('legacy field: default border token present', strpos($legacyClass, 'border-d') !== false);

// The client-side factory (builder dropzone / admin preview) must mirror the PHP markup.
$js = file_get_contents(__DIR__ . '/../public/assets/js/recorder-efb.js');
check('JS factory reads vj.el_height', strpos($js, 'vj.el_height') !== false);
check('JS factory reads vj.corner', strpos($js, 'vj.corner') !== false);
check('JS factory reads vj.el_border_color', strpos($js, 'vj.el_border_color') !== false);
check('JS factory reads vj.classes', strpos($js, 'vj.classes') !== false);
check('JS factory emits data-css on the shell', strpos($js, "data-css=") !== false);

// The stylesheet must translate the shell classes onto the frame.
$css = file_get_contents(__DIR__ . '/../includes/admin/assets/css/recorder-efb.css');
check('CSS maps audio height classes to frame size', strpos($css, '.efb-recorder-audio.h-xl-efb .efb-recorder-frame') !== false);
check('CSS maps rounded classes to frame radius', strpos($css, '.efb-recorder-shell.rounded-3 .efb-recorder-frame') !== false);
check('CSS forwards shell border-color to the frame', strpos($css, 'border-color: inherit') !== false);
// video/screen frames lock to a 16:9 aspect-ratio, so height must release it
check('CSS maps video height classes to frame size', strpos($css, '.efb-recorder-video-kind.h-xl-efb .efb-recorder-frame') !== false);
check('CSS maps screen height classes to frame size', strpos($css, '.efb-recorder-screen-kind.h-xl-efb .efb-recorder-frame') !== false);
check('CSS releases aspect-ratio for sized video/screen frames', strpos($css, 'aspect-ratio: auto') !== false);

// Builder-side Required toggle must know the recorder input id suffix (X_file).
$admin = file_get_contents(__DIR__ . '/../includes/admin/assets/js/admin-efb.js');
check('required toggle maps recorder types to _file', strpos($admin, 'audio_recorder: "_file"') !== false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
