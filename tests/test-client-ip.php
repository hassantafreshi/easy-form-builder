<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * Visitor IP is REMOTE_ADDR unless the request came through a trusted proxy.
 *
 * Bug (BUGS-FOUND.fa.md, "امنیت و حریم خصوصی"): get_ip_address() in
 * class-Emsfb-public.php and functions.php (and copies in the admin, panel and
 * email handler) read Client-IP and X-Forwarded-For before REMOTE_ADDR. Any
 * visitor could choose the IP stored with an entry, the key of every per-IP
 * rate limit, and the address passed to Shield Security's bot lookup.
 *
 * Part 1 drives the real Emsfb\Client_IP with stub filters. Part 2 checks that
 * every get_ip_address() copy delegates to it and that no plugin file reads the
 * forwarded headers on its own any more.
 *
 * Run: php tests/test-client-ip.php
 */

$pass = 0;
$fail = 0;
function test($label, $actual, $expected) {
    global $pass, $fail;
    if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; return; }
    $fail++;
    echo "[FAIL] $label\n  Expected: " . var_export($expected, true) . "\n  Actual:   " . var_export($actual, true) . "\n";
}

define('ABSPATH', __DIR__ . '/');
$GLOBALS['efb_proxies'] = array();
$GLOBALS['efb_headers'] = null;
function apply_filters($hook, $value) {
    if ($hook === 'efb_trusted_proxies') return array_merge((array) $value, $GLOBALS['efb_proxies']);
    if ($hook === 'efb_trusted_proxy_headers' && $GLOBALS['efb_headers'] !== null) return $GLOBALS['efb_headers'];
    return $value;
}
require_once __DIR__ . '/../includes/class-Emsfb-client-ip.php';
use Emsfb\Client_IP;

function ip($server, $proxies = array(), $headers = null) {
    $GLOBALS['efb_proxies'] = $proxies;
    $GLOBALS['efb_headers'] = $headers;
    return Client_IP::resolve($server);
}

echo "--- 1. No trusted proxy: forwarded headers are ignored ---\n";
$spoof = array('REMOTE_ADDR' => '198.51.100.9', 'HTTP_CLIENT_IP' => '1.2.3.4', 'HTTP_X_FORWARDED_FOR' => '5.6.7.8', 'HTTP_X_REAL_IP' => '9.9.9.9');
test('Client-IP is not trusted', ip($spoof), '198.51.100.9');
test('X-Forwarded-For is not trusted', ip(array('REMOTE_ADDR' => '198.51.100.9', 'HTTP_X_FORWARDED_FOR' => '5.6.7.8')), '198.51.100.9');
test('each request with a new X-Forwarded-For gets the same rate-limit key',
    ip(array('REMOTE_ADDR' => '198.51.100.9', 'HTTP_X_FORWARDED_FOR' => '10.0.0.' . mt_rand(1, 254))), '198.51.100.9');
test('IPv6 REMOTE_ADDR', ip(array('REMOTE_ADDR' => '2001:db8::7')), '2001:db8::7');
test('no REMOTE_ADDR (CLI, cron) keeps the old fallback', ip(array()), '1.1.1.1');
test('a malformed REMOTE_ADDR falls back', ip(array('REMOTE_ADDR' => '<script>')), '1.1.1.1');
test('a peer that is not in the list does not unlock the headers',
    ip(array('REMOTE_ADDR' => '203.0.113.50', 'HTTP_X_FORWARDED_FOR' => '5.6.7.8'), array('10.0.0.0/8')), '203.0.113.50');

echo "\n--- 2. Behind a trusted proxy ---\n";
$p = array('10.0.0.0/8', '2001:db8:ffff::/48');
test('visitor from X-Forwarded-For', ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '203.0.113.5'), $p), '203.0.113.5');
test('a spoofed left entry is skipped: rightmost untrusted hop wins',
    ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 203.0.113.5'), $p), '203.0.113.5');
test('trusted hops in the chain are skipped',
    ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '203.0.113.5, 10.9.9.9, 10.8.8.8'), $p), '203.0.113.5');
test('a chain of only trusted hops gives the leftmost', ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '10.4.4.4, 10.5.5.5'), $p), '10.4.4.4');
test('a malformed hop stops the walk (no injected value)',
    ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '203.0.113.5, <script>'), $p), '10.1.2.3');
test('IPv4 with a port', ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '203.0.113.5:54321'), $p), '203.0.113.5');
test('bracketed IPv6 with a port', ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '[2001:db8::1]:443'), $p), '2001:db8::1');
test('IPv6 proxy range', ip(array('REMOTE_ADDR' => '2001:db8:ffff::1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.20'), $p), '198.51.100.20');
test('X-Real-IP when there is no X-Forwarded-For', ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_REAL_IP' => '198.51.100.21'), $p), '198.51.100.21');
test('Client-IP stays ignored even behind the proxy', ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_CLIENT_IP' => '1.2.3.4'), $p), '10.1.2.3');
test('extra header via efb_trusted_proxy_headers (CF-Connecting-IP)',
    ip(array('REMOTE_ADDR' => '10.1.2.3', 'HTTP_CF_CONNECTING_IP' => '198.51.100.22'), $p, array('HTTP_CF_CONNECTING_IP')), '198.51.100.22');
test('single address entry (no CIDR)', ip(array('REMOTE_ADDR' => '172.16.0.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.23'), array('172.16.0.5')), '198.51.100.23');
test('invalid list entries are dropped', ip(array('REMOTE_ADDR' => '172.16.0.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.23'), array('not-an-ip', '172.16.0.0/99')), '172.16.0.5');

echo "\n--- 3. CIDR matching ---\n";
test('10.0.0.0/8 holds 10.255.0.1', Client_IP::in_list('10.255.0.1', array('10.0.0.0/8')), true);
test('10.0.0.0/8 does not hold 11.0.0.1', Client_IP::in_list('11.0.0.1', array('10.0.0.0/8')), false);
test('/20 boundary inside', Client_IP::in_list('192.168.15.255', array('192.168.0.0/20')), true);
test('/20 boundary outside', Client_IP::in_list('192.168.16.0', array('192.168.0.0/20')), false);
test('an IPv4 address never matches an IPv6 range', Client_IP::in_list('10.0.0.1', array('::/0')), false);

echo "\n--- 4. Every copy delegates to Client_IP ---\n";
$root = __DIR__ . '/../includes/';
foreach (array('class-Emsfb-public.php', 'functions.php', 'class-email-handler.php', 'admin/class-Emsfb-admin.php') as $f) {
    $src = file_get_contents($root . $f);
    test("$f get_ip_address() returns Client_IP::resolve()",
        (bool) preg_match('/function get_ip_address\(\) \{[^}]*?return \\\\Emsfb\\\\Client_IP::resolve\(\);/s', $src), true);
}
test('admin panel uses Client_IP', strpos(file_get_contents($root . 'admin/class-Emsfb-panel.php'), '\Emsfb\Client_IP::resolve()') !== false, true);
$readers = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (substr($file->getFilename(), -4) !== '.php' || $file->getFilename() === 'class-Emsfb-client-ip.php') continue;
    if (preg_match('/\$_SERVER\s*\[\s*[\'"]HTTP_(CLIENT_IP|X_FORWARDED_FOR|X_REAL_IP)/', file_get_contents($file->getPathname()))) {
        $readers[] = str_replace(realpath($root) . '/', '', realpath($file->getPathname()));
    }
}
test('no other plugin file reads Client-IP / X-Forwarded-For / X-Real-IP', $readers, array());
test('Client_IP is loaded with the core files', strpos(file_get_contents($root . 'class-Emsfb.php'), "includes/class-Emsfb-client-ip.php") !== false, true);
test('Shield Security receives the resolved IP from the submission',
    strpos(file_get_contents($root . 'class-Emsfb-public.php'), "'ip' => \$this->get_ip_address()") !== false, true);

echo "\n========================================\n";
echo "RESULTS: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
