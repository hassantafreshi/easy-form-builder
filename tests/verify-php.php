<?php
/**
 * Not a test. The PHP twin of EFBVerify's pure helpers for the Node parity tests: reads
 * [{fn, args}] as JSON on stdin and prints the PHP answers (Verification::normalize_destination,
 * sanitize_key, field_spec, duration, digits_only) as JSON.
 */
ob_start();
require __DIR__ . '/verify-test-lib.php';
ob_end_clean();
use Emsfb\Verification as V;
$in = json_decode(stream_get_contents(STDIN), true);
$out = array();
foreach ((array) $in as $c) {
	switch ($c['fn']) {
		case 'normDest': $out[] = V::normalize_destination($c['args'][0], $c['args'][1]); break;
		case 'sanitizeKey': $out[] = V::sanitize_key($c['args'][0], $c['args'][1]); break;
		case 'fieldSpec': $out[] = V::field_spec($c['args'][0]); break;
		case 'fmtTime': $out[] = V::duration($c['args'][0]); break;
		case 'codeDigits': $out[] = V::digits_only($c['args'][0]); break;
		default: $out[] = '?';
	}
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
