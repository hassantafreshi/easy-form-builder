<?php
/**
 * F12b-I: the question / math CAPTCHA add-on (AdnQCP), standalone. Nothing leaves the site.
 *
 *  - a sum: its answer passes, also typed in Persian (۷) or Arabic (٧) digits and with spaces; a wrong answer fails;
 *  - the owner's questions: several accepted answers, any case, ي / ی and ك / ک alike, the block of the visitor's
 *    language ([fa], [ar]) and the general block for others; "own" without questions falls back to sums;
 *  - the token holds HMACs of the answers only (never an answer); a tampered token fails; expired; replay;
 *  - the question is never in the page: the box carries the challenge URL, the label and help; the route is public,
 *    no-store, limited; the adapter is under 2 KB.
 *
 * Run: php tests/addons/test-question-provider.php
 */
require __DIR__ . '/provider-test-lib.php';
use Emsfb\Captcha\Registry;
use Emsfb\Captcha\Admin;

$D = efb_addon_dir('question-captcha');
function efb_addon_contract_versions() { return array('captcha_provider' => array('version' => 2)); }
function rest_url($p = '') { return 'https://www.site.test/wp-json/' . $p; }
function nocache_headers() {}
class WP_REST_Response { public $data; public $status; public $headers = array(); public function __construct($d, $s) { $this->data = $d; $this->status = $s; } public function header($k, $v) { $this->headers[$k] = $v; } }
require $D . '/routes-efb.php';
Registry::boot();
$p = Registry::get('question');
t('registered: endpoint kind, Free Plus, ready (the key is made on first use)', array($p ? $p->kind() : null, $p->plan(), $p->ready()), array('endpoint', 'free_plus', true));
$pay = function ($ch, $a) { return base64_encode(json_encode(array('t' => $ch['t'], 'a' => $a))); };
$rid = function () { return sprintf('%08x-1111-4111-8111-%012x', random_int(0, 0xffffffff), random_int(0, 0xffffffffff)); };
$sum = function ($q) { preg_match_all('/\d+/', $q, $m); $n = array_map('intval', $m[0]); return strpos($q, 'minus') !== false ? $n[0] - $n[1] : (strpos($q, 'times') !== false ? $n[0] * $n[1] : $n[0] + $n[1]); };
$ch = $p->challenge('math', 'en');
$a = $sum($ch['q']);
t('a sum: "What is … plus …?"; its answer passes', array((bool) preg_match('/^What is \d+ plus \d+\?$/', $ch['q']), Registry::verify('question', $pay($ch, (string) $a), array('request_id' => $rid()))['ok']), array(true, true));
$fa = strtr((string) $a, array('0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'));
$ar = strtr((string) $a, array('0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩'));
$c2 = $p->challenge('math'); $c3 = $p->challenge('math');
t('Persian and Arabic-Indic digits and spaces are accepted', array(Registry::verify('question', $pay($c2, ' ' . strtr((string) $sum($c2['q']), array('0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹')) . ' '), array('request_id' => $rid()))['ok'], Registry::verify('question', $pay($c3, strtr((string) $sum($c3['q']), array('0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩'))), array('request_id' => $rid()))['ok']), array(true, true));
$c4 = $p->challenge('math');
t('a wrong answer fails', Registry::verify('question', $pay($c4, (string) ($sum($c4['q']) + 1)))['code'], 'failed');
t('the token holds no answer', strpos(base64_decode(strtr($c4['t'], '-_', '+/')), '"' . $sum($c4['q']) . '"') === false, true);
Admin::save('question', array('mode' => 'own', 'questions' => "What colour is the sky? = blue | Blue sky\n[fa]\nرنگ آسمان؟ = آبی | ابی\nپایتخت ایران؟ = تهران\n[ar]\nعاصمة مصر؟ = القاهرة | قاهرة\nكم ٢ + ٢ ؟ = ٤"), array());
$q = $p->challenge('own', 'en');
t('own questions, general block for an English page; several answers, any case', array($q['q'], Registry::verify('question', $pay($q, 'BLUE'), array('request_id' => $rid()))['ok']), array('What colour is the sky?', true));
$qf = $p->challenge('own', 'fa');
$fa_ans = array('رنگ آسمان؟' => 'آبي', 'پایتخت ایران؟' => 'تهران');
t('the [fa] block for a Persian page; ي for ی is accepted', array(isset($fa_ans[$qf['q']]), Registry::verify('question', $pay($qf, $fa_ans[$qf['q']] ?? 'x'), array('request_id' => $rid()))['ok']), array(true, true));
$qa = $p->challenge('own', 'ar_EG');
$ar_ans = array('عاصمة مصر؟' => 'قاهرة', 'كم ٢ + ٢ ؟' => '4');
t('the [ar] block for ar_EG; another accepted answer; Latin digits for an Arabic answer', array(isset($ar_ans[$qa['q']]), Registry::verify('question', $pay($qa, $ar_ans[$qa['q']] ?? 'x'), array('request_id' => $rid()))['ok']), array(true, true));
t('ک and ك are the same', \Emsfb\Captcha\Question_Provider::norm('كتاب') === \Emsfb\Captcha\Question_Provider::norm('کتاب'), true);
Admin::save('question', array('questions' => ''), array());
t('"own" without questions asks a sum', (bool) preg_match('/\d/', $p->challenge('own', 'en')['q']), true);
$c5 = $p->challenge('math');
$t5 = json_decode(base64_decode(strtr($c5['t'], '-_', '+/')), true);
$t5['e'] = time() + 99999;
t('a tampered token (a later expiry) fails', Registry::verify('question', base64_encode(json_encode(array('t' => rtrim(strtr(base64_encode(json_encode($t5)), '+/', '-_'), '='), 'a' => (string) $sum($c5['q'])))))['code'], 'failed');
$c6 = $p->challenge('math');
t('an expired question says expired', Registry::verify('question', $pay($c6, (string) $sum($c6['q'])), array('now' => time() + 3600))['code'], 'expired');
$c7 = $p->challenge('math');
$tok7 = $pay($c7, (string) $sum($c7['q']));
Registry::verify('question', $tok7, array('request_id' => $rid()));
t('the same answer token again: replay', Registry::verify('question', $tok7, array('request_id' => $rid()))['code'], 'replay');
t('nothing was sent anywhere', count($GLOBALS['T']['calls']), 0);
$w = \Emsfb\Captcha::box_html('question', 6, 'form', 1, array('cp_label' => 'سؤال امنیتی', 'cp_help' => 'Digits are fine', 'cp_mode' => 'math'));
preg_match('/data-efb-captcha="([^"]+)"/', $w, $mm);
$cfg = json_decode(html_entity_decode($mm[1], ENT_QUOTES), true);
t('the box: the challenge URL, the label and help, never a question', array(strpos($cfg['ep'], 'captcha/question/challenge') !== false, $cfg['str']['label'], $cfg['help'], strpos($w, 'What is') === false), array(true, 'سؤال امنیتی', 'Digits are fine', true));
Registry::rest();
$r = $p->rest_challenge(new class { public function get_param($k) { return $k === 'l' ? 'fa' : 'math'; } });
t('the route: 200, no-store, {q, t}', array($r->status, $r->headers['Cache-Control'] ?? '', array_keys($r->data)), array(200, 'no-store, max-age=0', array('q', 't')));
$sch = \Emsfb\Spam::form_schema(array('type' => 'form', 'captcha' => '1', 'captcha_provider' => 'question'))['captcha'];
t('the form schema (AutoPilot): the question is a widget the visitor sees (kind endpoint, widget visible)', array($sch['effective_provider'], $sch['kind'], $sch['widget']), array('question', 'endpoint', 'visible'));
t('the adapter is under 2 KB', filesize($D . '/assets/question-adapter-efb.js') < 2048, true);
efb_done();
