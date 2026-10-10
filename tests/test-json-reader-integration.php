<?php
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../includes/json-efb.php';
require __DIR__ . '/../includes/class-Emsfb-upload-guard.php';
$source = file_get_contents(__DIR__ . '/../includes/class-Emsfb-public.php');
$methods = '';
foreach (['get_upload_field_definition_efb', 'efb_decode_client_rows_efb'] as $name) {
    if (!preg_match('/private function ' . $name . '\(.*?^\t\}/ms', $source, $match)) { throw new RuntimeException($name); }
    $methods .= str_replace('private function', 'public function', $match[0]);
}
eval('class ReaderIntegrationFixture { public $raw; public function get_form_data_efb($id, $fields) { return (object)["form_structer" => $this->raw]; }' . $methods . '}');
$reader = new ReaderIntegrationFixture();
$form = [['id_' => 'file1', 'type' => 'file', 'label' => 'C:\\new\\file "quote"'], ['id_' => 'audio1', 'type' => 'audio_recorder'], ['id_' => 'text1', 'type' => 'text']];
$raw = json_encode($form);
$checks = 0;
function reader_check($ok) { global $checks; if (!$ok) { throw new RuntimeException('Reader check ' . $checks); } $checks++; }
foreach ([0, 1, 2, 5] as $layers) {
    $stored = $raw;
    for ($i=0; $i<$layers; $i++) { $stored = str_replace('"', '\\"', $stored); }
    $reader->raw = $stored;
    reader_check($reader->get_upload_field_definition_efb(1, 'file1') === $form[0]);
    reader_check($reader->get_upload_field_definition_efb(1, 'audio1') === $form[1]);
    reader_check($reader->get_upload_field_definition_efb(1, 'text1') === null);
    reader_check($reader->get_upload_field_definition_efb(1, 'missing') === null);
    reader_check($reader->efb_decode_client_rows_efb($stored) === $form);
    reader_check(\Emsfb\Upload_Guard::count_upload_fields($stored) === 2);
}
$reader->raw = '{broken';
reader_check($reader->get_upload_field_definition_efb(1, 'file1') === null);
reader_check($reader->efb_decode_client_rows_efb('{broken') === null);
reader_check($reader->efb_decode_client_rows_efb('false') === null);
reader_check($reader->efb_decode_client_rows_efb('"text"') === null);
echo "PASS: $checks actual reader integration checks; no uploads or persistence\n";
