<?php
defined('ABSPATH') || exit;

/**
 * Decode JSON without destroying escapes inside values.
 * Older EFB writers escaped every quote once more before storing JSON.
 * Reverse that quote-only layer only after ordinary JSON decoding fails.
 * WordPress request slashes should be removed with wp_unslash at the boundary.
 * No database migration or mutation is performed here.
 *
 * Guarded so an add-on compatibility shim loaded first can never cause a redeclaration.
 */
if (!function_exists('emsfb_json_valid_string_efb')) {
    function emsfb_json_valid_string_efb($raw) {
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        // Some exported legacy records start with a UTF-8 byte-order mark.
        // Remove only the prefix; BOM characters inside field values are data.
        if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
            $raw = substr($raw, 3);
        }
        json_decode($raw);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $raw;
        }
        // Repair only a recognizably escaped legacy envelope. Invalid escapes
        // inside an ordinary record must not be silently removed as "repair".
        $first_quote = strpos($raw, '"');
        if ($first_quote === false || $first_quote === 0 || $raw[$first_quote - 1] !== '\\') {
            return null;
        }
        $candidate = $raw;
        for ($i = 0; $i < 5; $i++) {
            $next = preg_replace('/\\\\(?=")/', '', $candidate);
            if ($next === $candidate) { break; }
            $candidate = $next;
            json_decode($candidate);
            if (json_last_error() === JSON_ERROR_NONE) { return $candidate; }
        }
        // Compatibility for older fully slashed payloads, independently of the
        // quote-only candidate so neither repair compounds the other.
        $candidate = $raw;
        for ($i = 0; $i < 5; $i++) {
            $next = stripslashes($candidate);
            if ($next === $candidate) { break; }
            $candidate = $next;
            json_decode($candidate);
            if (json_last_error() === JSON_ERROR_NONE) { return $candidate; }
        }
        return null;
    }
}

if (!function_exists('emsfb_json_decode_efb')) {
    function emsfb_json_decode_efb($raw, $assoc = false) {
        $candidate = emsfb_json_valid_string_efb($raw);
        return $candidate === null ? null : json_decode($candidate, $assoc);
    }
}

/** Valid JSON text, preserving number precision and the original encoding. */
if (!function_exists('emsfb_json_normalize_efb')) {
    function emsfb_json_normalize_efb($raw) {
        $candidate = emsfb_json_valid_string_efb($raw);
        return $candidate === null ? $raw : $candidate;
    }
}
