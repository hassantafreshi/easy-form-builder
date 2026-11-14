<?php
/**
 * F10-C on a real WordPress (the e2e site; tests/f11-http-lib.php for the requests, the site's own wp_kses in this
 * process): the visitor's rich text on the server.
 *
 *  1  Layout::rich_sanitize() with WordPress's wp_kses: the spec's vector; hostile vectors (events, style, class,
 *     svg / math / iframe / object, img, javascript: / vbscript: / data: / entity-encoded links, nested and
 *     unclosed tags) leave only p br strong b em i u ul ol li blockquote h3 h4 and <a href="http(s)|mailto"
 *     rel="nofollow ugc noopener" target="_blank">; the browser's <div> paragraphs become <p>;
 *  2  a submit (forms/message/add): the stored value is the cleaned HTML with fmt "html" (written by the server);
 *     mlen 10 on "<b>12345678901</b>" is refused with the field's ptrnMmx_ message and field_id (the text is
 *     counted); more raw HTML than the cap is refused; a plain textarea is unchanged (sanitize_text_field, no fmt);
 *     fmt sent by a browser on a plain textarea is never stored; the F03 word limit reads the text;
 *  3  every output of the stored answer is safe: the e-mail (email_get_content_efb, cleaned again), the export
 *     cell (plain text, lines kept), the notification-rule values (text), Smart_Tags values (text).
 *
 * Run (under the e2e lock): source the e2e env; php tests/test-rich-text-kses.php
 */
$passed = 0;
$failed = 0;
function t($name, $actual, $expected) {
	global $passed, $failed;
	if ($actual === $expected) { echo "[PASS] {$name}\n"; $passed++; return; }
	echo "[FAIL] {$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	$failed++;
}
require __DIR__ . '/f11-http-lib.php';
$H = new EfbF11Http();
if ($why = $H->unavailable()) { echo "[SKIP] {$why}\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
$wp_load = rtrim(getenv('EFB_E2E_SITE_DIR'), '/') . '/wp-load.php';
if (!is_readable($wp_load)) { echo "[SKIP] no wp-load.php\nRESULTS: 0 passed, 0 failed\n"; exit(0); }
require $wp_load;
use Emsfb\Layout;

/** Every tag is on the list; no attribute but href (http/https/mailto) + the server's rel / target on links. */
function efb_rich_only_allowed($html) {
	preg_match_all('/<[^>]*>/', $html, $m);
	foreach ($m[0] as $tag) {
		if (preg_match('#^</?(p|br|strong|b|em|i|u|ul|ol|li|blockquote|h3|h4)\s*/?>$#', $tag)) continue;
		if ($tag === '</a>') continue;
		if (preg_match('#^<a href="(https?://|mailto:)[^"]*" rel="nofollow ugc noopener" target="_blank">$#', $tag)) continue;
		return $tag;
	}
	return true;
}

/* 1 */
$H->ctl('plan', '3');
t('1 the spec vector', Layout::rich_sanitize('<p onclick=x>a</p><script>b</script><a href="javascript:x">c</a><span style="x">d</span>'), '<p>a</p>cd');
$vectors = array(
	'<img src=x onerror=alert(1)>', '<svg><script>alert(1)</script></svg>', '<svg onload=alert(1)>', '<math><mi xlink:href="javascript:alert(1)">x</mi></math>',
	'<iframe src="javascript:alert(1)"></iframe>', '<object data="x.swf"></object><embed src=x>', '<a href="JaVaScRiPt:alert(1)">x</a>', '<a href="&#106;avascript:alert(1)">x</a>',
	'<a href="java	script:alert(1)">x</a>', '<a href=" javascript:alert(1)">x</a>', '<a href="vbscript:msgbox(1)">x</a>', '<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>',
	'<a href="https://ok.test" onclick="evil()">x</a>', '<p style="background:url(javascript:alert(1))" class="c" id="i" data-x="1">s</p>', '<b>a<i>b</b>c</i>',
	'<a href="https://a.test"><a href="https://b.test">n</a></a>', '<!-- <script>alert(1)</script> -->x', '<p title="a>b" onmouseover="x">t</p>', '<scr<script>ipt>alert(1)</script>',
	'<form><input onfocus=alert(1) autofocus></form>', '<details open ontoggle=alert(1)>', '<a href="mailto:a@b.test">m</a>', '<style>p{color:red}</style><div>V</div>', '<a href="//evil.test">r</a>',
);
$bad = array();
foreach ($vectors as $v) { $o = Layout::rich_sanitize($v); $c = efb_rich_only_allowed($o); preg_match_all('/<[^>]*>/', $o, $tg); if ($c !== true || preg_match('/on[a-z]+=|javascript:|vbscript:|data:text/i', implode('', $tg[0]))) $bad[] = array($v, $o); }
t('1 no hostile vector leaves a tag or attribute out of the list (' . count($vectors) . ' vectors, real wp_kses)', $bad, array());
t('1 a kept link gets rel="nofollow ugc noopener" target="_blank"', Layout::rich_sanitize('<a href="https://ok.test/x?a=1&b=2" title="t">ok</a>'), '<a href="https://ok.test/x?a=1&#038;b=2" rel="nofollow ugc noopener" target="_blank">ok</a>');
t('1 the browser\'s <div> paragraphs become <p>; a mailto link is kept', Layout::rich_sanitize('<div>One</div><div><a href="mailto:a@b.test">mail</a></div>'), '<p>One</p><p><a href="mailto:a@b.test" rel="nofollow ugc noopener" target="_blank">mail</a></p>');

/* 2 */
$F = $H->ctl('f03-form', 'F10 rich', json_encode(array(
	array('key' => 'rt', 'type' => 'textarea', 'name' => 'Story', 'rich_text' => '1', 'rich_toolbar' => 'full', 'mlen' => '10'),
	array('key' => 'rw', 'type' => 'textarea', 'name' => 'Words', 'rich_text' => '1', 'wmax' => '3'),
	array('key' => 'big', 'type' => 'textarea', 'name' => 'Big', 'rich_text' => '1'),
	array('key' => 'pl', 'type' => 'textarea', 'name' => 'Plain'),
)));
t('2 the save keeps rich_text "1" and rich_toolbar "full"', array($F['rows'][2]['rich_text'] ?? null, $F['rows'][2]['rich_toolbar'] ?? null), array('1', 'full'));
$id = $F['ids'];
$fid = $F['form_id'];
$row = function ($key, $v, $extra = array()) use ($id) { return array_merge(array('id_' => $id[$key], 'name' => $key, 'type' => 'textarea', 'value' => $v), $extra); };
$entries = function () use ($H, $fid) { $e = (array) $H->ctl('entries', (string) $fid); usort($e, function ($a, $b) { return (int) $b['msg_id'] - (int) $a['msg_id']; }); return $e; };
$H->fresh_jar();
$s = $H->submit($fid, array($row('rt', '<b>12345678901</b>')));
$a = EfbF11Http::answer($s);
t('2 mlen 10 on "<b>12345678901</b>": refused at the field with the maximum message (the text is counted)', array(empty($a['success']), $a['field_id'] ?? '', (bool) preg_match('/10/', strip_tags((string) ($a['m'] ?? '')))), array(true, $id['rt'], true));
$H->fresh_jar();
$s = $H->submit($fid, array($row('big', '<p>' . str_repeat('x', 70000) . '</p>')));
t('2 more raw HTML than the cap (65535 without a maximum): refused', array(empty(EfbF11Http::answer($s)['success']), EfbF11Http::answer($s)['field_id'] ?? ''), array(true, $id['big']));
$H->fresh_jar();
$s = $H->submit($fid, array($row('rw', '<p>one <b>two</b> three four</p>')));
t('2 the F03 word limit (3) reads the text: four words refused', array(empty(EfbF11Http::answer($s)['success']), EfbF11Http::answer($s)['field_id'] ?? ''), array(true, $id['rw']));
$H->fresh_jar();
$hostile = '<p onclick=alert(1)>Hi <b>there</b></p><img src=x onerror=alert(2)><a href="javascript:alert(3)">bad</a><ul><li>it"em</li></ul><script>alert(4)</script>';
$s = $H->submit($fid, array($row('rt', '<i>short</i>'), $row('rw', '<p>one <u>two</u></p>'), $row('big', $hostile), $row('pl', '<b>plain</b> text', array('fmt' => 'html'))));
t('2 a valid submit is stored', !empty(EfbF11Http::answer($s)['success']), true);
$e = $entries();
$c = isset($e[0]['content']) ? $e[0]['content'] : array();
$by = array();
foreach ($c as $r) if (isset($r['id_'])) $by[$r['id_']] = $r;
t('2 the stored rich value is the cleaned HTML with fmt html (server-written)', array($by[$id['big']]['value'] ?? null, $by[$id['big']]['fmt'] ?? null), array('<p>Hi <b>there</b></p>bad<ul><li>it@efb@dq#em</li></ul>', 'html')); // " is stored as @efb@dq# (every value)
t('2 another rich answer: <i> kept, fmt html', array($by[$id['rt']]['value'] ?? null, $by[$id['rt']]['fmt'] ?? null), array('<i>short</i>', 'html'));
t('2 a plain textarea: as before (tags stripped by sanitize_text_field), no fmt even when the browser sent one', array($by[$id['pl']]['value'] ?? null, isset($by[$id['pl']]['fmt'])), array('plain text', false));

/* 3 */
$pub = new \Emsfb\_Public();
$stored = array_values($c);
$mail = $pub->email_get_content_efb($stored, 'TRK');
t('3 e-mail: the rich answer as safe HTML (no script, event, img, javascript:), its paragraph kept', (bool) (strpos($mail, '<p>Hi <b>there</b></p>') !== false && !preg_match('/<script|onclick|onerror|<img|javascript:/i', $mail)), true);
$ent = new ReflectionMethod('\Emsfb\Entries', 'export_row_text');
$ent->setAccessible(true);
$cell = $ent->invoke(null, $by[$id['big']], 1, array('opt_val' => 'label', 'sep_multi' => ' | '));
t('3 export cell: plain text, lines kept, no tag', $cell, "Hi there\nbad\nit\"em");
t('3 Smart_Tags values (subjects, messages): the text', \Emsfb\Smart_Tags::values_of(array($by[$id['big']]))[$id['big']] ?? null, 'Hi there bad it"em');
t('3 Layout::row_text (notification / confirmation / webhook rules)', Layout::row_text($by[$id['big']]), "Hi there\nbad\nit\"em");

echo "\nRESULTS: {$passed} passed, {$failed} failed\n";
exit($failed ? 1 : 0);
