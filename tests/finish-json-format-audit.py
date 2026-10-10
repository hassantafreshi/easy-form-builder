from pathlib import Path
p = Path(__file__).resolve().parents[1] / 'includes/class-Emsfb-public.php'
data = p.read_bytes()
start = data.index(b'\tpublic function replaceContentMessageEfb($value) {')
end = data.index(b"\t\t$value = str_replace('@efb@sq#'", start)
data = data[:start] + b'\tpublic function replaceContentMessageEfb($value) {\r\n\t\tif (!is_string($value)) { return $value; }\r\n\t\t// Decoded field text must retain its literal backslashes.\r\n\t\t$value = emsfb_json_normalize_efb($value);\r\n' + data[end:]
data = data.replace(b'* (the response viewer, the entries list, the PHP readers) strips ALL\r\n\t * backslashes before json_decode. A literal double quote inside a value\r\n\t * would survive that strip as a bare " and break the whole record, so it\r\n\t * is carried as the @efb@dq# marker the viewers and emails already decode -', b'* retains the legacy quote marker used by existing stored replies.\r\n\t * Readers now preserve JSON escapes; keeping the writer format avoids\r\n\t * a data migration or changes for older clients. The marker is decoded')
data = data.replace(b'* the backslash-stripped form is kept only as a fallback for an older or', b'* legacy quote escaping is decoded only as a fallback for an older or')
p.write_bytes(data)
