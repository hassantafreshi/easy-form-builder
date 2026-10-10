<?php
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../includes/json-efb.php';
$cases = json_decode(file_get_contents('php://stdin'), true);
$output = [];
foreach ($cases as $case) { $output[] = emsfb_json_normalize_efb($case); }
echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
