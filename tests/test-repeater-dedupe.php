<?php
/**
 * F09-G: _Public::dedupe_by_id_and_ob_efb() keys rows by id_ | id_ob | ri (ri absent = the key of every other row
 * is as before): two rows of a repeater differ by ri and are both kept; an exact duplicate in one row is one row;
 * rows without ri are deduplicated exactly as before F09. The method is taken from the shipped file and run alone.
 *
 * Run: php tests/test-repeater-dedupe.php
 */
$src = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
if (!preg_match('/public function dedupe_by_id_and_ob_efb\(array \$rows\): array \{.*?\n    return \$out;\n\}/s', $src, $m)) { echo "[FAIL] method not found\nRESULTS: 0 passed, 1 failed\n"; exit(1); }
eval('class EfbDedupeProbe { ' . $m[0] . ' }');
$d = new EfbDedupeProbe();
$pass = 0; $fail = 0;
function t($l, $a, $e) { global $pass, $fail; if ($a === $e) { $pass++; echo "[PASS] $l\n"; } else { $fail++; echo "[FAIL] $l\n  expected " . json_encode($e) . "\n  actual   " . json_encode($a) . "\n"; } }
$r = function ($id, $ob, $v, $ri = null) { $x = array('id_' => $id, 'id_ob' => $ob, 'value' => $v); if ($ri !== null) $x['ri'] = $ri; return $x; };
t('two rows of a repeater (ri 0 and 1) are both kept', count($d->dedupe_by_id_and_ob_efb(array($r('nm', 'nm_', 'A', '0'), $r('nm', 'nm_', 'B', '1')))), 2);
t('the same row twice (same ri) is one row (the first)', $d->dedupe_by_id_and_ob_efb(array($r('nm', 'nm_', 'A', '0'), $r('nm', 'nm_', 'B', '0'))), array($r('nm', 'nm_', 'A', '0')));
t('checkbox choices of two rows (same option id, ri 0 / 1) both kept', count($d->dedupe_by_id_and_ob_efb(array($r('ex', 'ex1', 'Bag', '0'), $r('ex', 'ex1', 'Bag', '1')))), 2);
$old = array($r('a', 'a_', 'x'), $r('a', 'a_', 'y'), $r('c', 'o1', 'X'), $r('c', 'o2', 'Y'), array('value' => 'no id'), array('value' => 'no id'));
t('rows without ri: exactly as before (first of a duplicate kept, rows without ids all kept)', $d->dedupe_by_id_and_ob_efb($old), array($r('a', 'a_', 'x'), $r('c', 'o1', 'X'), $r('c', 'o2', 'Y'), array('value' => 'no id'), array('value' => 'no id')));
t('a row with ri and the same row without ri are two keys (the server drops the wrong one later)', count($d->dedupe_by_id_and_ob_efb(array($r('nm', 'nm_', 'A', '0'), $r('nm', 'nm_', 'A')))), 2);
echo "\nRESULTS: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
