<?php
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../includes/json-efb.php';
$fixture = ['text' => "متن می‌شود \"quoted\" &quot;", 'path' => 'C:\\folder\\new\\file.txt', 'literal' => '\\n \\t \\u1234', 'newline' => "line\nnext", 'html' => '<a href="https://example.test/a">link</a>', 'nested' => ['regex' => '/\\d+\\s/', 'list' => [1, true, null]]];
$raw = json_encode($fixture, JSON_UNESCAPED_UNICODE);
$checks = 0;
function check_json($condition, $label) { global $checks; if (!$condition) { throw new RuntimeException($label); } $checks++; }
check_json(emsfb_json_decode_efb($raw, true) === $fixture, 'ordinary values preserved');
check_json(json_decode(emsfb_json_normalize_efb($raw), true) === $fixture, 'normalization preserves values');
$legacy = $raw;
for ($i = 1; $i <= 5; $i++) {
    $legacy = str_replace('"', '\\"', $legacy);
    check_json(emsfb_json_decode_efb($legacy, true) === $fixture, 'quote-only legacy layer ' . $i);
}
check_json(emsfb_json_decode_efb(stripslashes(addslashes($raw)), true) === $fixture, 'request boundary unslash');
check_json(emsfb_json_decode_efb('[1,"\\\\n",null]', true) === [1, '\\n', null], 'array values');
check_json(emsfb_json_decode_efb('{broken', true) === null, 'invalid input rejected');
check_json(emsfb_json_normalize_efb('{broken') === '{broken', 'invalid raw retained');
check_json(emsfb_json_decode_efb(null) === null, 'non-string guard');
foreach ([[], new stdClass(), true, false, 12, 1.5, null] as $unexpected) {
    check_json(emsfb_json_decode_efb($unexpected, true) === null, 'unexpected input safely rejected without TypeError');
}
check_json(emsfb_json_decode_efb('false') === false, 'valid scalar');
check_json(emsfb_json_normalize_efb('"text"') === '"text"', 'scalar raw retained');
require __DIR__ . '/../includes/class-Emsfb.php';
$cleaner = new ReflectionMethod('Emsfb', 'clean_raw_json_efb');
$cleaner->setAccessible(true);
check_json(json_decode($cleaner->invoke(null, $raw), true) === $fixture, 'actual settings cleaner preserves valid Unicode and entities');
check_json(json_decode($cleaner->invoke(null, $legacy), true) === $fixture, 'actual settings cleaner preserves legacy Unicode');
check_json(json_decode($cleaner->invoke(null, "\xEF\xBB\xBF" . $raw), true) === $fixture, 'BOM compatibility');
check_json(json_decode($cleaner->invoke(null, '{&quot;smtp&quot;:true}'), true) === ['smtp' => true], 'HTML encoded legacy JSON');
$public_source = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$method_start = strpos($public_source, 'public function replaceContentMessageEfb($value)');
$method_end = strpos($public_source, '/**', $method_start);
eval('class JsonFieldFormatterFixture {' . substr($public_source, $method_start, $method_end - $method_start) . '}');
$public = new JsonFieldFormatterFixture();
check_json($public->replaceContentMessageEfb('plain \\n text') === 'plain \\n text', 'actual PHP field formatter preserves backslashes');
check_json($public->replaceContentMessageEfb('line@efb@nq#next') === 'line<br>next', 'actual PHP legacy line marker');
check_json($public->replaceContentMessageEfb('{ "user text": "keep spacing" }') === '{ "user text": "keep spacing" }', 'JSON-looking field text preserved');
$html = '<!DOCTYPE html><html><head><style>.mail{font-family:"Segoe UI";}</style></head><body><table role="presentation"><tr><td style="font-family:\'Segoe UI\',sans-serif">می‌شود &quot;quoted&quot; shortcode_message</td></tr></table><!-- EFBDATA:%7B%22blocks%22%3A%5B%5D%7D --></body></html>';
$template_json = json_encode(['smtp' => true, 'emailTemp' => $html], JSON_UNESCAPED_UNICODE);
check_json(emsfb_json_decode_efb($template_json, true)['emailTemp'] === $html, 'complete HTML email template preserved');
check_json(emsfb_json_decode_efb(str_replace('"', '\\"', $template_json), true)['emailTemp'] === $html, 'legacy complete HTML email template preserved');
echo "PASS: $checks JSON preservation checks\n";
