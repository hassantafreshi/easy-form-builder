"""Qualify global helpers and remove unsafe nested decoding; retain all files."""
from pathlib import Path
import re
root = Path(__file__).resolve().parents[1]
paths = list((root/'includes').rglob('*.php')) + list((root/'public').rglob('*.php'))
for addon in ['autofill', 'googlesheet', 'paypal', 'persiapay', 'stripe']:
    paths.extend(p for p in (root/'vendor'/addon).rglob('*.php') if 'stripe-php' not in p.parts)
total = 0
for path in paths:
    raw = path.read_bytes()
    source = raw.decode('utf-8')
    if path.name == 'json-efb.php': continue
    pattern = re.compile(r'json_decode\(\s*emsfb_json_normalize_efb\(')
    for match in reversed(list(pattern.finditer(source))):
        index = match.end(); begin = index; depth = 1; quote = None; escaped = False
        while index < len(source):
            char = source[index]
            if quote:
                if escaped: escaped = False
                elif char == '\\': escaped = True
                elif char == quote: quote = None
            elif char in "'\"": quote = char
            elif char == '(': depth += 1
            elif char == ')':
                depth -= 1
                if depth == 0: break
            index += 1
        tail = re.match(r'\s*(?:,\s*(true|false|\$assoc)\s*)?\)', source[index+1:])
        if not tail: raise RuntimeError(f'Unexpected decoder arguments in {path}')
        arg = source[begin:index].strip()
        mode = ', ' + tail.group(1) if tail.group(1) else ''
        source = source[:match.start()] + 'emsfb_json_decode_efb(' + arg + mode + ')' + source[index+1+tail.end():]
        total += 1
    if re.search(r'^namespace\s+', source, re.M):
        source = re.sub(r'(?<![\w\\])emsfb_json_(normalize|decode|valid_string)_efb\(', r'\\emsfb_json_\1_efb(', source)
    changed = source.encode('utf-8')
    if changed != raw:
        path.write_bytes(changed)
        print(path.relative_to(root))
print(f'{total} nested decoders now use the type-safe helper')
