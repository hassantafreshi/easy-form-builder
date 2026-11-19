<?php
/**
 * SEC-06: with EMSFB_EMAIL_DEBUG, the full e-mail (recipients, subject, HTML) was appended to
 * wp-content/efb-email-debug.log, a public address on most servers (Plugin Check: PluginDirectoryWrite). It now goes
 * to the private uploads folder (uploads/efb-private/logs/, deny-all .htaccess, index.php) under a name derived from
 * the site's salts; debug.log gets a summary with masked addresses and no subject; the old public file is removed.
 *
 * Run: php tests/test-email-debug-log.php   (no WordPress needed: the few WordPress functions are stubbed)
 */
$tmp = sys_get_temp_dir() . '/efb-sec06-' . getmypid();
@mkdir($tmp . '/wp-content/uploads', 0777, true);
define('ABSPATH', $tmp . '/');
define('WP_CONTENT_DIR', $tmp . '/wp-content');
define('EMSFB_EMAIL_DEBUG', true);
function wp_upload_dir($t = null, $c = true) { return array('basedir' => WP_CONTENT_DIR . '/uploads', 'baseurl' => 'https://site.test/wp-content/uploads', 'error' => false); }
function trailingslashit($s) { return rtrim($s, '/\\') . '/'; }
function wp_normalize_path($p) { return str_replace('\\', '/', $p); }
function apply_filters($h, $v) { return $v; }
function wp_mkdir_p($d) { return is_dir($d) || mkdir($d, 0777, true); }
function wp_hash($d) { return hash_hmac('md5', $d, 'test-salt'); }
function wp_date($f) { return gmdate($f); }
function wp_delete_file($f) { @unlink($f); }
function wp_json_encode($v) { return json_encode($v); }
function emsfb_is_php_function_available_efb($f) { return function_exists($f); }
$errlog = $tmp . '/debug.log';
ini_set('log_errors', '1');
ini_set('error_log', $errlog);

require __DIR__ . '/../includes/class-email-handler.php';

$pass = 0; $fail = 0;
function t($label, $ok, $info = '') { global $pass, $fail; if ($ok) { $pass++; echo "[PASS] $label\n"; return; } $fail++; echo "[FAIL] $label" . ($info !== '' ? ": $info" : '') . "\n"; }

/* A log from before 4.3.2 is still in wp-content. */
file_put_contents(WP_CONTENT_DIR . '/efb-email-debug.log', "old <p>secret answer</p>\n");

$h = new EmsfbEmailHandler();
$m = new ReflectionMethod('EmsfbEmailHandler', 'log_email_debug');
$m->setAccessible(true);
$m->invoke($h, 'newMessage', array('owner@example.test', 'visitor.name@example.org'), 'New entry from Jane Roe', '<p>Answer: my-secret-answer</p>', 'https://site.test/x', 'all');

t('nothing is written to wp-content/efb-email-debug.log, and the old public log is removed', !file_exists(WP_CONTENT_DIR . '/efb-email-debug.log'));
$logs = glob(WP_CONTENT_DIR . '/uploads/efb-private/logs/efb-email-debug-*.log');
t('the full e-mail goes to uploads/efb-private/logs/', is_array($logs) && count($logs) === 1, json_encode($logs));
$file = $logs ? $logs[0] : '';
t('the file name carries a salted 20-character part (not guessable)', $file !== '' && preg_match('/efb-email-debug-[0-9a-f]{20}\.log$/', $file) === 1 && basename($file) !== 'efb-email-debug.log');
t('the private log holds the e-mail for the person debugging', $file !== '' && strpos(file_get_contents($file), 'my-secret-answer') !== false);
t('the private folder has its deny-all .htaccess and index.php files', is_file(WP_CONTENT_DIR . '/uploads/efb-private/.htaccess') && strpos(file_get_contents(WP_CONTENT_DIR . '/uploads/efb-private/.htaccess'), 'deny') !== false && is_file(WP_CONTENT_DIR . '/uploads/efb-private/logs/index.php'));
t('email_debug_log_file() names the same file', EmsfbEmailHandler::email_debug_log_file() === $file);

$dl = is_file($errlog) ? file_get_contents($errlog) : '';
t('debug.log gets a summary line', strpos($dl, '[EFB Email Debug] State: newMessage') !== false, $dl);
t('debug.log masks the addresses', strpos($dl, 'o***@example.test') !== false && strpos($dl, 'v***@example.org') !== false && strpos($dl, 'owner@example.test') === false && strpos($dl, 'visitor.name@') === false, $dl);
t('debug.log has neither the subject nor the HTML', strpos($dl, 'Jane Roe') === false && strpos($dl, 'my-secret-answer') === false, $dl);
t('debug.log does not print the server path of the log', strpos($dl, $tmp) === false, $dl);

/* The send result lines (log_email_success / log_email_failure) mask the recipients too. */
$src = file_get_contents(__DIR__ . '/../includes/class-email-handler.php');
t('the result lines mask the recipients and drop the subject', substr_count($src, "'to' => self::mask_email_addresses(\$to),") === 2 && strpos($src, "'subject' => self::safe_substr((string)\$subject, 0, 120)") === false);
t("no write under WP_CONTENT_DIR remains (only the old file's removal)", preg_match_all("/WP_CONTENT_DIR \. '\/efb-email-debug\.log'/", $src) === 2 && strpos($src, "\$log_file = WP_CONTENT_DIR") === false);
t('mask_email_addresses() keeps the domain', EmsfbEmailHandler::mask_email_addresses('a.b@x.io, c@y.io') === 'a***@x.io, c***@y.io');

/* Without EMSFB_EMAIL_DEBUG nothing is written: the switch is checked first (source). */
t('the log is still an explicit opt-in (EMSFB_EMAIL_DEBUG)', preg_match('/function log_email_debug\([^)]*\) \{\s*if \(!self::email_debug_enabled\(\)\) \{\s*return;/', $src) === 1);

// clean up
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) { $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname()); }
rmdir($tmp);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
