"""Replace audited destructive JSON reads in maintained EFB PHP, no deletions."""
from pathlib import Path
import re

root = Path(__file__).resolve().parent.parent
paths = list((root / 'includes').rglob('*.php'))
for addon in ['autofill', 'googlesheet', 'human-shield', 'import-export', 'logic', 'offline', 'paypal', 'persiapay', 'smssended', 'stripe', 'telegram']:
    paths.extend(p for p in (root / 'vendor' / addon).rglob('*.php') if 'stripe-php' not in p.parts)
prefix = re.compile(r'''str_replace\(\s*(?:'\\\\'|"\\\\")\s*,\s*(?:''|"")\s*,\s*''')
report = []
for path in paths:
    if path.name == 'json-efb.php':
        continue
    original = path.read_bytes()
    text = original.decode('utf-8')
    matches = list(prefix.finditer(text))
    for match in reversed(matches):
        depth = 1
        quote = None
        escaped = False
        index = match.end()
        while index < len(text):
            ch = text[index]
            if quote:
                if escaped: escaped = False
                elif ch == '\\': escaped = True
                elif ch == quote: quote = None
            elif ch in "'\"": quote = ch
            elif ch == '(': depth += 1
            elif ch == ')':
                depth -= 1
                if depth == 0: break
            index += 1
        if depth != 0:
            raise RuntimeError('Unbalanced call in ' + str(path))
        argument = text[match.end():index]
        old = text[match.start():index + 1]
        line = text[:match.start()].count('\n') + 1
        report.append(f'{path.relative_to(root)}:{line}: {old}')
        text = text[:match.start()] + 'emsfb_json_normalize_efb(' + argument + ')' + text[index + 1:]
    if text != original.decode('utf-8'):
        path.write_bytes(text.encode('utf-8'))
        print(path.relative_to(root), len(matches), 'JSON reads repaired')
(root / 'tests' / 'json-read-repairs.txt').write_text('\n'.join(sorted(report)), encoding='utf-8')
