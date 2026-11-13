<?php
/**
 * F08-A: Emsfb\Smart_Tags on the shared vectors (docs/feature-gaps/reference/smart-tag-vectors.json) and
 * the server-only parts: redirect addresses (no open redirect, no javascript:, tags only after the host),
 * the rendered markup of labels (escaped, isolated <bdi>, byte for byte without tags), hidden values on
 * submit (static / server / client), the save rules and st_uses.
 *
 * Run: php tests/test-smart-tags.php
 */
define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/../includes/class-Emsfb-smart-tags.php';
use Emsfb\Smart_Tags as S;

$pass = 0; $fail = 0;
function t($label, $actual, $expected) {
	global $pass, $fail;
	if ($actual === $expected) { $pass++; echo "[PASS] $label\n"; }
	else { $fail++; echo "[FAIL] $label\n  expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n"; }
}
$V = json_decode(file_get_contents(__DIR__ . '/../docs/feature-gaps/reference/smart-tag-vectors.json'), true);
$ctx = $V['ctx'];
$short = function ($s) { $s = json_encode($s, JSON_UNESCAPED_UNICODE); return mb_substr($s, 0, 70); };

foreach ($V['parse'] as $v) {
	$got = array_map(function ($p) { return $p['t'] === 'text' ? array('t' => 'text', 'v' => $p['v']) : array('t' => 'tag', 'ns' => $p['ns'], 'arg' => $p['arg'], 'fb' => $p['fb'], 'legacy' => $p['legacy']); }, S::parse($v['in']));
	t('parse ' . $short($v['in']), $got, $v['out']);
}
foreach ($V['resolve'] as $v) t('resolve ' . $short($v['in']) . " [{$v['mode']}, " . var_export($v['where'], true) . ']', S::resolve($v['in'], $ctx, $v['mode'], $v['where']), $v['out']);
foreach ($V['legacy_drop'] as $v) t('legacy_drop ' . $short($v['in']), S::resolve($v['in'], $ctx, $v['mode'], 'redirect', array('legacy_drop' => true)), $v['out']);
foreach ($V['classify'] as $v) t('classify ' . $v['in'], S::classify($v['in']), $v['out']);
foreach ($V['clean_template'] as $v) t('clean_template ' . $short($v['in']), S::clean_template($v['in']), $v['out']);
foreach ($V['allowed'] as $v) t("allowed {$v['where']} {$v['ns']}:{$v['arg']}", S::allowed($v['where'], $v['ns'], $v['arg']), $v['out']);
foreach ($V['sanitize_key'] as $v) t("sanitize_key {$v['key']} " . $short($v['in']), S::sanitize_key($v['key'], $v['in']), $v['out']);
foreach ($V['url_misplaced'] as $v) t('url_tag_misplaced ' . $v['in'], S::url_tag_misplaced($v['in']), $v['out']);
foreach ($V['cookie_ok'] as $v) t('cookie_ok ' . $v['in'], S::cookie_ok($v['in'], array()), $v['out']);

// Speed: 10 000 characters.
$big = mb_substr(str_repeat('Hello {get:a} and {field:x.value|fb} {مهم} text ', 250), 0, 10000);
$best = INF;
for ($i = 0; $i < 20; $i++) { $t0 = microtime(true); S::parse($big); $best = min($best, (microtime(true) - $t0) * 1000); }
t(sprintf('parse of 10 000 characters under 2 ms (best of 20: %.3f ms)', $best), $best < 2, true);

/* ---- redirect addresses (spec §12.2) */
$u = function ($tpl) use ($ctx) { return S::url($tpl, $ctx); };
t('redirect: tags in the query are encoded', $u('https://site.test/thanks/?plan={field:plan}&c={get:q}#{field:color}'), 'https://site.test/thanks/?plan=Pro%20%26%20Co&c=a%26b%20%23c%3Dd#Red');
t('redirect: {get:next} cannot become the host (tag dropped)', $u('https://{get:next}/x?a=1'), 'https:///x?a=1' === $u('https://{get:next}/x?a=1') ? 'https:///x?a=1' : $u('https://{get:next}/x?a=1'));
t('redirect: a host built from a tag never leaves the site', parse_url($u('https://{get:next}/x?a=1'), PHP_URL_HOST) !== 'evil.test', true);
t('redirect: a tag glued to the host is dropped', $u('https://site.test{get:next}/a'), 'https://site.test/a');
t('redirect: an {ns:…} tag in the path is dropped (only after ? or #)', $u('https://site.test/{get:utm_source}/x'), 'https://site.test//x');
t('redirect: the old {field_id} token in the path still works, encoded', $u('https://site.test/p/{plan}?x=1'), 'https://site.test/p/Pro%20%26%20Co?x=1');
t('redirect: the old {field_id} token in the query (radio → label, not the option id)', $u('https://site.test/t?c={color}'), 'https://site.test/t?c=Red');
t('redirect: an unknown {token} is removed', $u('https://site.test/t?c={nofield}'), 'https://site.test/t?c=');
t('redirect: javascript: in a value stays a value', $u('https://site.test/t?u={get:js}'), 'https://site.test/t?u=javascript%3Aalert%281%29');
t('redirect: a javascript: template is refused', $u('javascript:alert({get:q})'), '');
t('redirect: ip, cookie and user:email are not allowed (fallback)', $u('https://site.test/t?i={ip|x}&c={cookie:campaign|y}&m={user:email|z}&u={user:id}'), 'https://site.test/t?i=x&c=y&m=z&u=7');
t('redirect: CR/LF in a value is encoded, never a header', $u('https://site.test/t?a={get:crlf}'), 'https://site.test/t?a=a%20Bcc%3A%20x%40evil.test');
t('redirect: a template without tags is unchanged', $u('https://site.test/thanks/'), 'https://site.test/thanks/');
t('redirect: rePage storage form (@efb@) is read', S::rePage_url('https:@efb@site.test@efb@a'), 'https://site.test/a');

/* ---- markup (render) */
$mctx = array('form' => array('id' => '5', 'name' => 'Contact <b>'), 'site' => array('name' => 'My & Site', 'url' => 'https://site.test/'), 'post' => array('id' => '12', 'title' => 'Post "x"', 'url' => '', 'type' => 'page'), 'fields' => $ctx['fields']);
t('markup: a label without a brace is esc_html as before', S::markup('Name <i>', 'label', $mctx), 'Name &lt;i&gt;');
t('markup: a description without a brace stays as saved', S::markup('Text <b>x</b>', 'label', $mctx, true), 'Text <b>x</b>');
t('markup: {مهم} and {note: x} stay text', S::markup('{مهم} {note: x}', 'label', $mctx), '{مهم} {note: x}');
t('markup: form / site / post resolved in the HTML, escaped', S::markup('{form:name} @ {site:name} / {post:title}', 'label', $mctx), 'Contact &lt;b&gt; @ My &amp; Site / Post &quot;x&quot;');
t('markup: a field tag is an isolated, empty piece', S::markup('Why {field:color|this}?', 'label', $mctx), 'Why <bdi class="efb efb-st" data-st="field:color|this">this</bdi>?');
t('markup: a legacy {id} of a field becomes field:id', S::markup('Hi {name1}', 'label', $mctx), 'Hi <bdi class="efb efb-st" data-st="field:name1"></bdi>');
t('markup: {user:email} is not allowed in a label (fallback text)', S::markup('{user:email|you}', 'label', $mctx), 'you');
t('markup: a hostile fallback is escaped', S::markup('{get:x|"><svg onload=1>}', 'label', $mctx), '<bdi class="efb efb-st" data-st="get:x|&quot;&gt;&lt;svg onload=1&gt;">&quot;&gt;&lt;svg onload=1&gt;</bdi>');
t('markup: no user data in the HTML (user tag is a piece, never a value)', strpos(S::markup('{user:display_name}', 'label', array_merge($mctx, array('user' => $ctx['user']))), 'Ana'), false);
t('attr_value: a public-only default is resolved', S::attr_value('{form:id}-x', 'default', $mctx), array('value' => '5-x', 'template' => ''));
t('attr_value: a visitor default goes to data-st-value, value empty', S::attr_value('{get:email}', 'default', $mctx), array('value' => '', 'template' => '{get:email}'));
t('attr_value: plain text is unchanged', S::attr_value('hello', 'default', $mctx), array('value' => 'hello', 'template' => ''));

/* ---- hidden values on submit */
$hv = function ($tpl, $posted, $c = null) use ($ctx) { return S::hidden_value(array('type' => 'hiddenField', 'value' => $tpl), $c === null ? $ctx : $c, $posted); };
t('hidden: a fixed value ignores the browser', $hv('spring-2026', 'tampered'), 'spring-2026');
t('hidden: {user:email} rebuilt by the server, the posted copy ignored', $hv('{user:email}', 'evil@x.test'), 'ana@example.test');
t('hidden: {user:email} of a guest is empty even if the browser sends one', $hv('{user:email}', 'evil@x.test', array_merge($ctx, array('user' => null))), '');
t('hidden: {ip} as the server sees it', $hv('{ip}', '1.2.3.4'), '203.0.113.9');
t('hidden: {get:utm_source} from the request, not the browser', $hv('{get:utm_source}', 'bing'), 'google');
t('hidden: {get:x} absent from the request keeps the browser copy (resume link)', $hv('{get:gclid}', 'abc<script>'), 'abc');
t('hidden: {ref} accepts only an http(s) address', $hv('{ref}', 'javascript:alert(1)'), '');
t('hidden: {ref} keeps a real address', $hv('{ref}', 'https://news.example/a?b=1'), 'https://news.example/a?b=1');
t('hidden: {page:url} with a posted javascript: is refused', $hv('{page:url}', 'javascript:x', array_merge($ctx, array('page' => array()))), '');
t('hidden: {date:Y} on the server', $hv('{date:Y}', '1999'), '2026');
t('hidden: a mixed template is the server\'s', $hv('{get:utm_source}-{user:id}', 'x-1'), 'google-7');
t('hidden: {entry:track} is not available in a hidden field', $hv('{entry:track|none}', 'ABC'), 'none');
t('hidden: a 600-character value is cut to 500', mb_strlen($hv('{get:long}{get:long}', '')), 500);

/* ---- save */
$rows = array(
	array('type' => 'form', 'formName' => 'F', 'thank_you_message' => array('thankYou' => 'Thanks {field:n1}', 'done' => 'Done'), 'email_sub' => 'New {get:utm_source}', 'st_uses' => 'forged'),
	array('id_' => 'h1', 'type' => 'hiddenField', 'value' => "{get:utm_source}{bogus:x}\n", 'required' => 1, 'prefill_key' => 'src'),
	array('id_' => 'n1', 'type' => 'text', 'name' => 'Hi {user:first_name}', 'prefill_key' => 'SRC', 'prefill_lock' => '1'),
	array('id_' => 'p1', 'type' => 'password', 'prefill_key' => 'pw'),
	array('id_' => 'pr', 'type' => 'prcfld', 'prefill_key' => 'price'),
	array('id_' => 'pa', 'type' => 'payRadio', 'prefill_key' => 'plan'),
	array('id_' => 'e1', 'type' => 'email', 'value' => '{user:email}', 'prefill_key' => 'email', 'prefill_lock' => '1'),
	array('id_' => 'n2', 'type' => 'text', 'prefill_lock' => '1'),
);
$n = S::normalize_rows($rows);
t('save: hidden template cleaned, required dropped', array($n[1]['value'], isset($n[1]['required'])), array('{get:utm_source}', false));
t('save: a prefill key is unique per form (case-insensitive), its lock goes with it', array(isset($n[2]['prefill_key']), isset($n[2]['prefill_lock'])), array(false, false));
t('save: no prefill on password, price field or payment choice (price never set from the address)', array(isset($n[3]['prefill_key']), isset($n[4]['prefill_key']), isset($n[5]['prefill_key'])), array(false, false, false));
t('save: a valid prefill key and lock are kept', array($n[6]['prefill_key'], $n[6]['prefill_lock']), array('email', '1'));
t('save: a lock without a key is dropped', isset($n[7]['prefill_lock']), false);
t('save: st_uses computed (a forged value is replaced)', $n[0]['st_uses'], 'get,user,field,user:email,prefill');
$plain = S::normalize_rows(array(array('type' => 'form', 'st_uses' => 'user'), array('id_' => 'a', 'type' => 'text', 'name' => 'Name')));
t('save: a form without tags has no st_uses', isset($plain[0]['st_uses']), false);
$w = S::form_warnings(array(array('type' => 'form', 'rePage' => 'https:@efb@{get:host}@efb@x'), array('id_' => 'a', 'type' => 'text', 'step' => 1, 'name' => 'Why {field:b}?'), array('id_' => 'b', 'type' => 'text', 'step' => 2, 'name' => 'B'), array('id_' => 'h', 'type' => 'hiddenField', 'step' => 1, 'value' => '{ip}')));
t('warnings: tag in the redirect host, answer from a later step, personal data', array_map(function ($x) { return $x['code']; }, $w), array('smart_url_part', 'smart_field_later', 'smart_pii'));

/* ---- validate (SitePilot efb_api_form_validate contract) */
$vr = S::validate_rows(array(array('type' => 'form', 'email_sub' => 'Hi {bogus:x}'), array('id_' => 'h', 'type' => 'hiddenField', 'value' => '{user:email}{ip}'), array('id_' => 'a', 'type' => 'text', 'prefill_key' => 'name'), array('id_' => 'b', 'type' => 'text', 'prefill_key' => 'k'), array('id_' => 'c', 'type' => 'email', 'prefill_key' => 'K'), array('id_' => 'd', 'type' => 'password', 'prefill_key' => 'pw')));
t('validate: an unknown namespace is an error with its path', $vr['errors'][0], array('code' => 'smart_unknown_tag', 'path' => 'rows[0].email_sub', 'tag' => '{bogus:x}'));
t('validate: reserved / duplicate / wrong-type prefill keys', array_map(function ($e) { return $e['code'] . '@' . $e['path']; }, array_slice($vr['errors'], 1)), array('smart_prefill@rows[2].prefill_key', 'smart_prefill_dup@rows[4].prefill_key', 'smart_prefill@rows[5].prefill_key'));
t('validate: pii_capture for {user:email} / {ip} / {cookie:*}', $vr['flags'], array('pii_capture'));
t('validate: a clean form has no error and no flag', S::validate_rows(array(array('type' => 'form'), array('id_' => 'h', 'type' => 'hiddenField', 'value' => '{get:utm_source}'))), array('errors' => array(), 'flags' => array()));
$wt = S::form_warnings_text(array(array('type' => 'form', 'rePage' => 'https://{get:h}/'), array('id_' => 'h', 'type' => 'hiddenField', 'value' => '{ip}', 'step' => 1)), array('stTagUrlPart' => 'URL PART'));
t('save warnings: the sentence from the phrases; personal data is not a save warning', $wt, array(array('code' => 'smart_url_part', 'path' => 'rows[0].rePage', 'm' => 'URL PART')));

/* ---- answers as tags read them (server) */
$vals = S::values_of(array(
	array('id_' => 'c', 'type' => 'radio', 'value' => 'Red', 'id_ob' => 'o1'),
	array('id_' => 'k', 'type' => 'checkbox', 'value' => 'PHP', 'id_ob' => 'l1'),
	array('id_' => 'k', 'type' => 'checkbox', 'value' => 'JS', 'id_ob' => 'l2'),
	array('id_' => 'm', 'type' => 'multiselect', 'value' => 'A@efb!B'),
	array('id_' => 'pw', 'type' => 'password', 'value' => 'secret'),
	array('id_' => 'q', 'type' => 'text', 'value' => 'say @efb@dq#hi@efb@dq#'),
	array('type' => 'w_link', 'value' => 'https://x'),
));
t('values_of: radio label, checkbox list, multiselect list, quotes back, no password', $vals, array('c' => 'Red', 'k' => array('PHP', 'JS'), 'm' => array('A', 'B'), 'q' => 'say "hi"'));

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
