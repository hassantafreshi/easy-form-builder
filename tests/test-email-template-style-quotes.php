<?php
// Isolate EFB's style handling; WordPress KSES is a passthrough test stub.
// This verifies quote preservation, not WordPress's own KSES implementation.
define('ABSPATH', __DIR__ . '/');
function home_url() { return 'https://example.test'; }
function wp_parse_url($url, $component) { return parse_url($url, $component); }
function wp_allowed_protocols() { return ['http', 'https', 'mailto']; }
function wp_kses($html, $tags, $protocols) { return $html; }
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false); }
require __DIR__ . '/../includes/functions.php';
$helper = (new ReflectionClass('efbFunction'))->newInstanceWithoutConstructor();
$checks = 0;
function style_check($ok, $label) {
    global $checks;
    if (!$ok) { throw new RuntimeException($label); }
    $checks++;
}
foreach ([
    '<td style="font-family:\'Segoe UI\',sans-serif; color:#123456">text</td>',
    "<td style='font-family:\"Segoe UI\",sans-serif; color:#123456'>text</td>",
    '<td style="font-family:&quot;Segoe UI&quot;,sans-serif; color:#123456">text</td>',
] as $html) {
    $output = $helper->sanitize_full_html_efb($html);
    preg_match('/style="([^"]*)"/', $output, $match);
    $css = html_entity_decode($match[1] ?? '', ENT_QUOTES, 'UTF-8');
    style_check(strpos($css, 'Segoe UI') !== false && strpos($css, ',sans-serif') !== false, 'font stack preserved');
    style_check(strpos($css, 'color: #123456') !== false, 'next CSS rule preserved');
    style_check(strpos($output, '>text</td>') !== false, 'HTML attribute and closing tag intact');
    $raw = json_encode(['emailTemp' => $output]);
    style_check(emsfb_json_decode_efb($raw, true)['emailTemp'] === $output, 'settings JSON round trip');
    style_check(emsfb_json_decode_efb(str_replace('"', '\\"', $raw), true)['emailTemp'] === $output, 'legacy settings round trip');
}
style_check(strpos($helper->sanitize_style_attribute_efb('color:expression(alert(1));font-family:Segoe UI'), 'expression') === false, 'existing CSS guard retained');
echo "PASS: $checks email-template style checks; no stored data changed\n";
