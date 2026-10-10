<?php
// In-memory fixtures only: no WordPress bootstrap, database or filesystem writes.
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../includes/json-efb.php';
require __DIR__ . '/../includes/class-Emsfb.php';
$checks = 0;
function legacy_check($condition, $label) {
    global $checks;
    if (!$condition) { throw new RuntimeException($label); }
    $checks++;
}
// Historical snapshot writers use json_encode followed by quote-only escaping.
$rows = [
    ['type' => 'text', 'name' => 'Name', 'value' => 'old@efb@dq#text@efb@nq#next'],
    ['type' => 'file', 'url' => 'https://example.test/uploads/a.pdf', 'value' => '@file@'],
    ['type' => 'text', 'value' => "O'Reilly C:\\new\\file می‌شود"],
];
$settings = ['smtp' => true, 'emailTemp' => '<div class="mail">می‌شود</div>', 'femail' => 'sender@example.test'];
$form = [['type' => 'text', 'id_' => 'field1', 'required' => true, 'label' => 'Old form']];
foreach (['submission' => $rows, 'settings' => $settings, 'form' => $form] as $kind => $expected) {
    $raw = json_encode($expected, JSON_UNESCAPED_UNICODE);
    foreach ([0, 1, 2, 5] as $layers) {
        $stored = $raw;
        for ($i = 0; $i < $layers; $i++) { $stored = str_replace('"', '\\"', $stored); }
        $original = $stored;
        legacy_check(emsfb_json_decode_efb($stored, true) === $expected, "$kind: $layers quote layers");
        legacy_check(json_decode(emsfb_json_normalize_efb($stored), true) === $expected, "$kind: normalized reader");
        legacy_check($stored === $original, "$kind: stored input untouched");
    }
    legacy_check(emsfb_json_decode_efb("\xEF\xBB\xBF" . $raw, true) === $expected, "$kind: export BOM");
}
// A fully slashed historical payload containing apostrophes is unambiguous:
// quote-only decoding fails, so the independent stripslashes branch is used.
$raw = json_encode($rows, JSON_UNESCAPED_UNICODE);
legacy_check(emsfb_json_decode_efb(addslashes($raw), true) === $rows, 'fully slashed legacy payload');
$clean = new ReflectionMethod('Emsfb', 'clean_raw_json_efb');
$clean->setAccessible(true);
$char_map = str_split(json_encode($settings));
legacy_check(json_decode($clean->invoke(null, json_encode($char_map)), true) === $settings, 'historical settings character map');
legacy_check(json_decode($clean->invoke(null, htmlspecialchars(json_encode($settings), ENT_QUOTES, 'UTF-8')), true) === $settings, 'historical HTML-encoded settings');
legacy_check(emsfb_json_normalize_efb('{"truncated":') === '{"truncated":', 'damaged raw record retained');
echo "PASS: $checks legacy compatibility checks; no stored data changed\n";
