<?php
// Run only the production settings loader, with in-memory WordPress services.
// No WordPress bootstrap, database changes, cleanup, or outbound requests.
$source = file_get_contents(dirname(__DIR__) . '/includes/class-Emsfb-public.php');
$start = strpos($source, 'private function get_submission_settings_efb(');
$end = strpos($source, 'public function get_form_public_efb(', $start);
if ($start === false || $end === false) { throw new RuntimeException('Production loader missing'); }
eval('class SubmissionSettingsDiagnostic { public $setting; public function load(&$source) { return $this->get_submission_settings_efb($source); } ' . substr($source, $start, $end - $start) . '}');
$cache = false;
$reads = 0;
$decoded = null;
function wp_cache_get($key, $group) { global $cache; return $cache; }
function wp_cache_set($key, $value, $group, $ttl) { global $cache; $cache = $value; return true; }
function wp_json_encode($value) { return json_encode($value); }
function get_setting_Emsfb($mode) {
    global $reads, $decoded;
    if ($mode !== 'decoded') { throw new RuntimeException('Must use canonical decoded settings'); }
    $reads++;
    return $decoded;
}
function verify_settings($label, $condition) {
    if (!$condition) { throw new RuntimeException($label); }
    echo "PASS: $label\n";
}
$expected = ['smtp' => true, 'femail' => 'postmaster@example.org', 'emailSupporter' => 'recipient@example.invalid',
    'emailTemp' => '<a title="Quoted">سلام</a>', 'path' => 'C:\\mail\\templates', 'nested' => ['text' => "Line 1\nLine 2"]];
$decoded = json_decode(json_encode($expected));
$loader = new SubmissionSettingsDiagnostic();
foreach ([false, null, '', 'invalid cached JSON', []] as $bad_cache) {
    $cache = $bad_cache;
    $before = $reads;
    $settings = $loader->load($origin);
    verify_settings('Invalid or missing cache reloads settings safely: ' . gettype($bad_cache), $origin === 'storage' && $settings === $expected && $reads === $before + 1);
    verify_settings('Template escapes, Unicode and nested values survive', json_decode(json_encode($loader->setting), true) === $expected);
}
$before = $reads;
$settings = $loader->load($origin);
verify_settings('Valid cache uses same settings for gate and template', $origin === 'object-cache' && $reads === $before && $settings === $expected);
$cache = ['smtp' => false, 'femail' => 'postmaster@example.org'];
$settings = $loader->load($origin);
verify_settings('Explicitly disabled email stays disabled', $settings['smtp'] === false && $loader->setting->smtp === false);
$cache = false;
$decoded = null;
$settings = $loader->load($origin);
verify_settings('Missing settings never enable email', $settings === []);
$cache = false;
$decoded = $expected;
$settings = $loader->load($origin);
verify_settings('Canonical getter array remains supported', $settings === $expected);
echo "All settings regression checks passed; no persistent state changed.\n";
