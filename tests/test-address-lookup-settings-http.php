<?php
/**
 * The Address & Postal add-on's "Address search" settings in the settings row (F12b-L rule; F20-core brief item 6), on
 * the real WordPress (the e2e site), through HTTP as an administrator:
 *  - a site from before: the settings in the old option emsfb_adr_lookup; the next request moves them once into the
 *    settings row's top-level key adr_lookup (written with set_setting_Emsfb(), read back, then the option deleted;
 *    the flag emsfb_adr_moved), the other keys of the row untouched;
 *  - the add-on's own screen (Easy Form Builder → Address search) shows them and saves into adr_lookup only (the
 *    other keys of the row as they were); a service address that is not https is refused, nothing written;
 *  - core's main Settings window: it never receives adr_lookup, and its save keeps it even when the request sends
 *    something under it;
 *  - AutoPilot / SitePilot: capabilities say where the settings live (no value).
 *
 * Run (the e2e site, under the e2e lock): php tests/test-address-lookup-settings-http.php
 */
require __DIR__ . '/f12b-http-lib.php';
$H = new EfbF12bHttp();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\n"; echo "\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
if (!is_readable($H->root . '/vendor/address/class-Emsfb-address-lookup.php')) { echo "[SKIP] the Address & Postal add-on is not in vendor/address\n\nRESULTS: 0 passed, 0 failed\n"; exit(0); }

$c17 = function (...$a) use ($H) {
	$parts = array_merge(preg_split('/\s+/', trim($H->wpcli)), array('eval-file', $H->root . '/tests/e2e-wp/ctl-f17.php'), array_map('strval', $a), array('--path=' . $H->site, '--allow-root'));
	$lines = array_values(array_filter(explode("\n", (string) shell_exec(implode(' ', array_map('escapeshellarg', $parts)) . ' 2>/dev/null')), 'strlen'));
	return json_decode($lines ? end($lines) : '', true);
};
$row = function () use ($c17) { return (array) $c17('adr-row'); };
$others = function ($r) { unset($r['adr_lookup']); ksort($r); return json_encode($r); };

$H->ctl('plan', '3');
$c17('adr', '1');
try {
	echo "--- a site from before: the old option is moved once ---\n";
	$o = $c17('adr-old', 'direct', 'https://geo.example.org/');
	t('before: the old option holds the settings, the row has no adr_lookup, the move not done', array($o['in_row'], $o['old'], $o['moved']), array(false, array('mode' => 'direct', 'url' => 'https://geo.example.org/'), null));
	$before = $row();
	$H->http('GET', '/', null, array(), false); // any request: init runs the move
	$a = $c17('adr', '1');
	$after = $row();
	t('the next request: adr_lookup in the row (the cleaned address), the old option gone, the flag set', array($a['lookup'], $a['old'], (string) $a['moved']), array(array('mode' => 'direct', 'url' => 'https://geo.example.org'), null, '1'));
	t('… every other key of the row as it was', $others($after) === $others($before), true);
	$H->http('GET', '/', null, array(), false);
	t('… idempotent: the next requests change nothing', $row() == $after, true);

	echo "--- the add-on's own screen ---\n";
	$nonce_rest = $H->admin();
	$p = $H->http('GET', '/wp-admin/admin.php?page=Emsfb_adr_lookup', null, array(), false)['body'];
	t('Easy Form Builder → Address search shows the moved settings (From the visitor\'s browser, the address)', array((bool) preg_match('#<option value="direct" selected=\'selected\'>#', $p), strpos($p, 'value="https://geo.example.org"') !== false), array(true, true));
	$n = preg_match('#id="efbAdrForm" data-nonce="([a-f0-9]{10})"#', $p, $m) ? $m[1] : '';
	$save = function ($mode, $url) use ($H, $n) { $r = $H->ajax(array('action' => 'efb_adr_lookup_save', 'nonce' => $n, 'mode' => $mode, 'url' => $url)); return json_decode($r['body'], true); };
	$before = $row();
	$r = $save('proxy', 'https://nominatim.example.net/');
	$after = $row();
	t('Save: "Saved.", adr_lookup changed, every other key of the row as it was, no option written', array($r['success'] ?? null, $r['data']['m'] ?? '', json_decode(json_encode($after['adr_lookup']), true), $others($after) === $others($before), $c17('adr', '1')['old']), array(true, 'Saved.', array('mode' => 'proxy', 'url' => 'https://nominatim.example.net'), true, null));
	$r = $save('off', 'http://not-https.example');
	t('a service address that is not https: refused with its message, nothing written', array($r['success'] ?? null, (bool) preg_match('/https address/', $r['data']['m'] ?? ''), json_decode(json_encode($row()['adr_lookup']), true)), array(false, true, array('mode' => 'proxy', 'url' => 'https://nominatim.example.net')));
	$r = $H->ajax(array('action' => 'efb_adr_lookup_save', 'nonce' => 'bad0000000', 'mode' => 'off', 'url' => ''));
	t('a bad nonce: 403, nothing written', array($r['status'], json_decode(json_encode($row()['adr_lookup']), true)['mode']), array(403, 'proxy'));

	echo "--- core's main Settings window ---\n";
	$page = (array) $H->ctlb('settings-redacted');
	t('the settings the main window gets have no adr_lookup', array_key_exists('adr_lookup', $page), false);
	$m = $page;
	$m['adr_lookup'] = array('mode' => 'off', 'url' => '');
	$m['spam_retention_days'] = '32';
	$r = $H->ajax(array('action' => 'set_settings_Emsfb', 'nonce' => $nonce_rest, 'message' => json_encode($m)));
	$after = $row();
	t('its save (even with something under adr_lookup): success, its own change stored, adr_lookup as it was', array(!empty(json_decode($r['body'], true)['data']['success']), (string) ($after['spam_retention_days'] ?? ''), json_decode(json_encode($after['adr_lookup']), true)), array(true, '32', array('mode' => 'proxy', 'url' => 'https://nominatim.example.net')));
	$H->ctl('f12-setting', 'spam_retention_days=30');

	echo "--- AutoPilot / SitePilot ---\n";
	$cap = $c17('caps');
	$e = is_array($cap) && isset($cap['features']['setting:addr_lookup']) ? $cap['features']['setting:addr_lookup'] : array();
	t('capabilities: setting:addr_lookup says the mode and where it is stored (settings row, key adr_lookup), never the address', array($e['mode'] ?? null, (bool) preg_match('/settings row, key adr_lookup/', $e['stored'] ?? ''), strpos(json_encode($cap), 'nominatim.example.net')), array('proxy', true, false));
} finally {
	$c17('adr', '1', 'proxy');
}
efb_done();
