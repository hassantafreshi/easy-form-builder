<?php
/**
 * Regression test for Password field placeholders in the PHP renderer.
 * Run: php tests/test-password-placeholder-render.php
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

$pass = 0;
$fail = 0;

function test_password_placeholder($label, $condition) {
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

$field = (object) array(
    'type' => 'password',
    'placeholder' => 'Enter "secure" password',
    'el_border_color' => 'border-d',
    'required' => false,
    'name' => 'Password',
    'value' => '',
    'classes' => ''
);

$builder = new \Emsfb\Formbuilder(array($field), false);
$method = new ReflectionMethod(\Emsfb\Formbuilder::class, 'generateElementSpecificFields_efb');
$method->setAccessible(true);
$rendered = $method->invoke(
    $builder,
    'password',
    'password-field',
    $field,
    array('', '', '', ''),
    '',
    '',
    '',
    '',
    '',
    '',
    123,
    array()
);

$html = $rendered['ui'] ?? '';
test_password_placeholder('password input type is rendered', strpos($html, 'type="password"') !== false);
test_password_placeholder('password placeholder is rendered', strpos($html, 'placeholder="Enter &quot;secure&quot; password"') !== false);
test_password_placeholder('password placeholder remains attribute-safe', strpos($html, 'placeholder="Enter "secure" password"') === false);

$admin_source = file_get_contents(__DIR__ . '/../includes/admin/assets/js/admin-efb.js');
test_password_placeholder('admin preview does not suppress password placeholders', strpos($admin_source, "elementId != 'password'") === false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
