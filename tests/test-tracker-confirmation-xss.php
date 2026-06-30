<?php
/**
 * Regression test for confirmation-code finder reflected XSS via ?track=.
 * Run: C:\xampp\php\php.exe tests\test-tracker-confirmation-xss.php
 */

$pass = 0;
$fail = 0;

function test_tracker_xss($label, $condition) {
	global $pass, $fail;
	if ($condition) {
		$pass++;
		echo "[PASS] $label\n";
		return;
	}
	$fail++;
	echo "[FAIL] $label\n";
}

function test_tracker_sanitize_text_field($value) {
	return trim(strip_tags((string) $value));
}

function test_tracker_esc_attr($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$payload = '" autofocus onfocus=alert(document.cookie)//';
$sanitized = test_tracker_sanitize_text_field($payload);
$vulnerable_html = '<input type="text" value="' . $sanitized . '" autocomplete="off">';
$fixed_html = '<input type="text" value="' . test_tracker_esc_attr($sanitized) . '" autocomplete="off">';

test_tracker_xss('sanitize_text_field-style filtering keeps the quote that can break an attribute', strpos($sanitized, '"') === 0);
test_tracker_xss('unescaped attribute rendering is exploitable', strpos($vulnerable_html, 'value="" autofocus onfocus=alert(document.cookie)//"') !== false);
test_tracker_xss('escaped attribute rendering keeps payload inside value', strpos($fixed_html, 'value="&quot; autofocus onfocus=alert(document.cookie)//"') !== false);
test_tracker_xss('escaped attribute rendering does not create autofocus attribute', strpos($fixed_html, 'value="" autofocus') === false);

$source = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
test_tracker_xss('tracker shortcode output uses esc_attr for get_track value', strpos($source, 'esc_attr($get_track)') !== false);
test_tracker_xss('tracker shortcode unslashes track before sanitizing', strpos($source, "sanitize_text_field(wp_unslash(\$_GET['track']))") !== false);
test_tracker_xss('tracker shortcode escapes placeholder attribute text', strpos($source, "esc_attr(\$text['entrTrkngNo'])") !== false);
test_tracker_xss('tracker shortcode escapes captcha site key attribute', strpos($source, 'esc_attr($valstng->siteKey)') !== false);
test_tracker_xss('tracker shortcode escapes visible tracker labels', strpos($source, "esc_html(\$text['trackingCode'])") !== false);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
