<?php
require __DIR__ . '/efb-masks.php';
$cat = json_decode(file_get_contents($argv[1]), true);
$rows = array();
foreach ($cat['masks'] as $m) {
	foreach ($m['examples_valid'] as $v) { $r = efbm_validate($m, $v); $rows[] = array('id' => $m['id'], 'kind' => 'valid', 'input' => $v, 'ok' => $r['ok'], 'code' => $r['code'], 'value' => $r['value'], 'stored' => $r['ok'] ? efbm_to_stored($m, $r['value']) : null, 'pass' => $r['ok']); }
	foreach ($m['examples_invalid'] as $x) { $r = efbm_validate($m, $x['value']); $rows[] = array('id' => $m['id'], 'kind' => 'invalid', 'input' => $x['value'], 'ok' => $r['ok'], 'code' => $r['code'], 'value' => $r['value'], 'stored' => null, 'pass' => !$r['ok'] && $r['code'] === $x['expect']); }
	if (!empty($m['examples_format'])) foreach ($m['examples_format'] as $f) { $out = efbm_format_value($m, $f['input']); $rows[] = array('id' => $m['id'], 'kind' => 'format', 'input' => $f['input'], 'ok' => $out === $f['output'], 'code' => null, 'value' => $out, 'stored' => null, 'pass' => $out === $f['output']); }
}
echo json_encode($rows, JSON_UNESCAPED_UNICODE);
