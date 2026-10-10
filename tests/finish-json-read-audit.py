from pathlib import Path
root = Path(__file__).resolve().parents[1]
def edit(name, replacements):
    path = root / name
    data = path.read_bytes()
    for old, new in replacements:
        old, new = old.encode(), new.encode()
        assert old in data, (name, old)
        data = data.replace(old, new)
    path.write_bytes(data)
edit('vendor/autofill/class-Emsfb-autofill-api.php', [(f'json_decode(stripslashes(${v}), true)', f'emsfb_json_decode_efb(${v}, true)') for v in ['fields', 'params', 'headers', 'mappings']])
edit('includes/class-Emsfb-public.php', [('stripslashes($messages[0]->content)', 'emsfb_json_normalize_efb($messages[0]->content)'), ('stripslashes($message->content)', 'emsfb_json_normalize_efb($message->content)')])
edit('includes/admin/class-Emsfb-admin.php', [('sanitize_textarea_field($_POST[\'plan_data\'])', 'sanitize_textarea_field(wp_unslash($_POST[\'plan_data\']))'), ('json_decode(stripslashes($plan_data_raw), true)', 'emsfb_json_decode_efb($plan_data_raw, true)')])
