"""Repair JSON.parse callers in maintained EFB JavaScript; no files deleted."""
from pathlib import Path
import re

root = Path(__file__).resolve().parent.parent
paths = list((root / 'includes' / 'admin' / 'assets' / 'js').glob('*efb*.js'))
paths += list((root / 'public' / 'assets' / 'js').glob('*efb*.js'))
for addon in ['autofill', 'googlesheet', 'human-shield', 'import-export', 'logic', 'offline', 'paypal', 'persiapay', 'smssended', 'stripe', 'telegram']:
    paths += list((root / 'vendor' / addon).rglob('*efb*.js'))
expression = re.compile(r'''JSON\.parse\(\s*([\w.$\[\]]+)\.replace\(/(?:\[\\\\\]|\\\\)/g,\s*(['"])\2\)\s*\)''')
report = []
for path in paths:
    if 'jquery' in path.name: continue
    text = path.read_bytes().decode('utf-8-sig')
    def replace(match):
        report.append(str(path.relative_to(root)) + ': ' + match.group())
        return 'efbParseJson(' + match.group(1) + ')'
    changed, count = expression.subn(replace, text)
    if count:
        path.write_bytes(changed.encode('utf-8'))
        print(path.relative_to(root), count, 'JSON reads repaired')
(root / 'tests' / 'json-js-read-repairs.txt').write_text('\n'.join(report), encoding='utf-8')
