<?php
/**
 * Standalone regression test for the file-upload extension blocklist.
 * Mirrors the exact decision logic in:
 *   - includes/class-Emsfb-public.php  (file_upload_public @3185, file_upload_api @3376)
 *   - includes/admin/class-Emsfb-admin.php (file_upload_public @2156)
 *
 * Goal: confirm html/htm/xhtml/xht/shtm/svgz are now blocked, that all
 * previously-allowed legitimate files still pass, and that no executable
 * type regressed.
 *
 * Run: php tests/test-upload-html-blocklist.php
 */

$pass = 0;
$fail = 0;

function test($label, $actual, $expected) {
    global $pass, $fail;
    $ok = $actual === $expected;
    if ($ok) {
        $pass++;
        echo "[PASS] $label\n";
    } else {
        $fail++;
        echo "[FAIL] $label\n";
        echo "  Expected: " . json_encode($expected) . "\n";
        echo "  Actual:   " . json_encode($actual) . "\n";
    }
}

/*
 * Exact blocklist as now shipped in all three upload handlers.
 * Keep this array in sync with the source if the list changes.
 */
$blocked_ext = array('php','php3','php4','php5','php7','php8','phtml','phar','cgi','pl','py','asp','aspx','jsp','sh','bash','bat','cmd','com','exe','dll','msi','shtml','htaccess','svg','html','htm','xhtml','xht','shtm','svgz');

/*
 * Reproduces the server-side filename derivation + block check:
 *   $name = 'efb-PLG-<date>-<rand>.' . pathinfo($original, PATHINFO_EXTENSION);
 *   $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
 *   blocked if in_array($file_ext, $blocked_ext)
 * Returns true when the upload would be REJECTED.
 */
function upload_is_blocked($original_filename, $blocked_ext) {
    $generated_name = 'efb-PLG-260703-ABCD1234.' . pathinfo($original_filename, PATHINFO_EXTENSION);
    $file_ext = strtolower(pathinfo($generated_name, PATHINFO_EXTENSION));
    return in_array($file_ext, $blocked_ext, true);
}

echo "--- NEWLY BLOCKED: html family must be rejected ---\n";
foreach (['page.html','index.htm','doc.xhtml','x.xht','y.shtm','logo.svgz'] as $f) {
    test("blocked: $f", upload_is_blocked($f, $blocked_ext), true);
}

echo "\n--- CASE-INSENSITIVITY: uppercase variants must also be rejected ---\n";
foreach (['PAGE.HTML','Index.Htm','Doc.XHTML','LOGO.SVGZ'] as $f) {
    test("blocked (case): $f", upload_is_blocked($f, $blocked_ext), true);
}

echo "\n--- REGRESSION: previously blocked executables stay blocked ---\n";
foreach (['shell.php','x.phtml','a.phar','s.svg','t.shtml','e.exe','h.htaccess','w.aspx'] as $f) {
    test("still blocked: $f", upload_is_blocked($f, $blocked_ext), true);
}

echo "\n--- NO DISRUPTION: legitimate uploads must still pass ---\n";
$allowed_files = [
    'photo.jpg','photo.jpeg','image.png','anim.gif','scan.pdf','song.mp3',
    'clip.mp4','voice.wav','sheet.xlsx','report.docx','slides.pptx',
    'notes.txt','archive.zip','data.csv','picture.heic','movie.webm'
];
foreach ($allowed_files as $f) {
    test("allowed: $f", upload_is_blocked($f, $blocked_ext), false);
}

echo "\n--- EDGE: double extension keeps final ext semantics (jpg wins, allowed) ---\n";
test("double ext evil.html.jpg -> jpg (allowed)", upload_is_blocked('evil.html.jpg', $blocked_ext), false);
test("double ext evil.jpg.html -> html (blocked)", upload_is_blocked('evil.jpg.html', $blocked_ext), true);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";

exit($fail > 0 ? 1 : 0);
