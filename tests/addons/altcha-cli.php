<?php
/**
 * The ALTCHA add-on's challenge and check from the command line, for tests/addons/test-altcha-adapter.js (the official
 * widget in a browser solves what the add-on's PHP made; the PHP checks what the widget sent). Standalone, with a
 * fixed test signing key (a placeholder, never a real one). Used only by the tests.
 *
 *   php tests/addons/altcha-cli.php challenge [low|medium|high]  → the challenge JSON
 *   php tests/addons/altcha-cli.php verify <payload>              → {"ok": …, "code": …}
 */
require __DIR__ . '/provider-test-lib.php';
function efb_addon_contract_versions() { return array('captcha_provider' => array('version' => 2)); }
function rest_url($p = '') { return 'https://site.test/wp-json/' . $p; }
function did_action($h) { return 1; }
$GLOBALS['T']['options']['emsfb_altcha_v'] = 2;
require efb_addon_dir('altcha') . '/routes-efb.php';
\Emsfb\Captcha\Registry::boot();
\Emsfb\Captcha\Admin::save('altcha', array('complexity' => $argv[3] ?? 'low', 'expires' => '20'), array('hmac_key' => 'altcha-cli-test-key-placeholder-0000000000'));
\Emsfb\Captcha\Registry::forget('altcha');
$p = \Emsfb\Captcha\Registry::get('altcha');
if (($argv[1] ?? '') === 'challenge') { echo json_encode($p->challenge($argv[2] ?? ''), JSON_UNESCAPED_SLASHES); exit(0); }
$r = $p->verify($argv[2] ?? '', array());
echo json_encode(array('ok' => !empty($r['ok']), 'code' => $r['code'], 'codes' => $r['codes'] ?? array()));
