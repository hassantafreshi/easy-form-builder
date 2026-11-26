<?php
/**
 * QA2-015: the answers of a notification e-mail (email_get_content_efb), with the real snippets of
 * includes/class-Emsfb-public.php: a multiselect answer has no trailing comma ("M1, M2"), a terms answer's
 * [text](link) is a link (it was raw markdown), and the choices of one checkbox field are one row ("C1, C3"), not a
 * row each. Hostile values stay escaped.
 *
 * Run: php tests/test-qa2-email.php
 */
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$start = strpos($src, "if (isset(\$c['value']) && is_string(\$c['value'])) {", strpos($src, 'function email_get_content_efb'));
$end = strpos($src, "if (isset(\$c['qty']))", $start);
preg_match('/\t(?:public |private |protected )?(?:static )?function email_escape_leaf_efb\(.*?\n\t}\n/s', $src, $m);
$ms = strpos($src, '$cb_at_efb = array();', strpos($src, 'function email_get_content_efb'));
$me = $ms === false ? false : strpos($src, '$content = $cb_out_efb;', $ms);
$merge = 'public static function email_merge_choices_efb($content) { ' . ($ms !== false && $me !== false ? substr($src, $ms, $me - $ms) . ' return $cb_out_efb;' : 'return $content;') . ' }';
eval('class Qa2EmailFixture {' . $m[0] . $merge . ' public function row($c) { $q = ""; ' . substr($src, $start, $end - $start) . ' return $q; } }');
$pass = 0; $fail = 0;
function et($label, $cond, $detail = '') { global $pass, $fail; if ($cond) { $pass++; echo "[PASS] $label\n"; } else { $fail++; echo "[FAIL] $label\n      $detail\n"; } }
$fx = new Qa2EmailFixture();
$o = $fx->row(array('value' => 'M1@efb!M2@efb!'));
et('a multiselect answer reads "M1, M2" (no trailing comma)', $o === '<b>M1, M2</b>', $o);
$o = $fx->row(array('value' => 'I agree to [the terms](https://site.test/privacy/)'));
et('a terms answer\'s [text](link) is a link', $o === '<b>I agree to <a href="https://site.test/privacy/">the terms</a></b>', $o);
$o = $fx->row(array('value' => '[x](javascript:alert(1)) <b>'));
et('… only http(s) links; markup stays escaped', strpos($o, '<a ') === false && strpos($o, '&lt;b&gt;') !== false, $o);
$r = Qa2EmailFixture::email_merge_choices_efb(array(array('id_' => 'n', 'type' => 'text', 'value' => 'A'), array('id_' => 'c', 'type' => 'checkbox', 'value' => 'C1'), array('id_' => 'c', 'type' => 'checkbox', 'value' => 'C3'), array('id_' => 'd', 'type' => 'checkbox', 'value' => 'D1')));
et('the choices of one checkbox field are one row ("C1, C3"); other fields keep theirs', count($r) === 3 && $fx->row($r[1]) === '<b>C1, C3</b>' && $r[2]['value'] === 'D1', json_encode($r));
// QA2-018: the direction of a designed template ("auto", or none) follows the e-mail's language; a fixed one is kept.
$hs = file_get_contents(__DIR__ . '/../includes/class-email-handler.php');
$GLOBALS['qa2_rtl'] = true;
function is_rtl() { return $GLOBALS['qa2_rtl']; }
$line = preg_match('/^\s*\$direction\s*=\s*isset\(\$gs\[\'direction\'\]\).*;$/m', $hs, $dm) ? $dm[0] : (preg_match('/^\s*\$direction\s*= \$gs\[\'direction\'\].*;$/m', $hs, $dm) ? $dm[0] : '$direction = "?";');
$dir = function ($gs) use ($line) { eval($line); return $direction; };
et('QA2-018: a template saved as "auto" (or without a direction) is right to left in a Persian / Arabic e-mail; a fixed "ltr" stays', array($dir(array('direction' => 'auto')), $dir(array()), $dir(array('direction' => 'ltr'))) === array('rtl', 'rtl', 'ltr'), $line);
et('QA2-018: the confirmation code is isolated (<bdi dir="ltr">) in every e-mail kind', substr_count($hs, "\"<bdi dir='ltr'>\" . \$track_id . \"</bdi>\"") >= 5 && strpos($hs, "\"<bdi dir='ltr'>\" . \$m . \"</bdi>\"") !== false);
$bs = file_get_contents(__DIR__ . '/../includes/admin/assets/js/email-template-builder-efb.js');
et('QA2-018: the designer saves "auto" by default and offers "Automatic (site language)"', substr_count($bs, "direction: 'auto'") === 2 && strpos($bs, '<option value="auto"') !== false);

echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
