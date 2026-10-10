<?php
// Verify real dependency entry points and PHP namespace lookup in isolation.
// No WordPress bootstrap, hooks with side effects, database or network.
namespace {
    define('ABSPATH', __DIR__ . '/');
    $entry = $argv[1] ?? 'core';
    $entries = [
        'core' => 'class-Emsfb.php',
        'functions' => 'functions.php',
        'upload' => 'class-Emsfb-upload-guard.php',
    ];
    if (!isset($entries[$entry])) { throw new \RuntimeException('Unknown entry'); }
    require __DIR__ . '/../includes/' . $entries[$entry];
    $checks = 0;
    function loading_check($condition, $label) {
        global $checks;
        if (!$condition) { throw new \RuntimeException($label); }
        $checks++;
    }
    loading_check(function_exists('emsfb_json_normalize_efb'), 'global helper is loaded');
    loading_check(function_exists('emsfb_json_decode_efb'), 'decoder is loaded');
    loading_check(!function_exists('Emsfb\\emsfb_json_normalize_efb'), 'helper stays in global namespace');
    $raw = '{"path":"C:\\\\new\\\\file","smtp":true}';
    loading_check(emsfb_json_normalize_efb($raw) === $raw, 'global caller');
}
namespace Emsfb {
    function json_namespace_loading_fixture($raw) {
        // This is the same unqualified call style as existing namespaced classes.
        return emsfb_json_normalize_efb($raw);
    }
    class JsonNamespaceLoadingFixture {
        public function read($raw) { return json_decode(emsfb_json_normalize_efb($raw), true); }
    }
}
namespace {
    loading_check(\Emsfb\json_namespace_loading_fixture($raw) === $raw, 'namespaced global fallback');
    loading_check((new \Emsfb\JsonNamespaceLoadingFixture())->read($raw)['smtp'] === true, 'namespaced class caller');
    loading_check(\emsfb_json_normalize_efb($raw) === $raw, 'explicit global call');
    $callback = 'emsfb_json_normalize_efb';
    loading_check(call_user_func($callback, $raw) === $raw, 'global callback style');
    require_once __DIR__ . '/../includes/json-efb.php';
    require_once __DIR__ . '/../includes/json-efb.php';
    loading_check(emsfb_json_normalize_efb($raw) === $raw, 'require_once prevents redeclaration');
    echo "PASS: $entry entry: $checks loading/namespace checks; no persistent state changed\n";
}
