from pathlib import Path
import subprocess
import re
root = Path(__file__).resolve().parents[1]
addons = ['autofill', 'googlesheet', 'human-shield', 'import-export', 'logic', 'offline', 'paypal', 'persiapay', 'smssended', 'stripe', 'telegram']
php = list((root/'includes').rglob('*.php')) + list((root/'public').rglob('*.php')) + list(root.glob('*.php'))
js = list((root/'includes/admin/assets/js').glob('*efb*.js')) + list((root/'public/assets/js').glob('*efb*.js'))
for addon in addons:
    php.extend(p for p in (root/'vendor'/addon).rglob('*.php') if 'stripe-php' not in p.parts)
    js.extend((root/'vendor'/addon).rglob('*efb*.js'))
failures = []
for path in php:
    result = subprocess.run(['C:/xampp/php/php.exe', '-l', str(path)], capture_output=True, text=True)
    if result.returncode: failures.append(result.stdout + result.stderr)
    text = path.read_text(encoding='utf-8-sig')
    if re.search(r'''str_replace\(\s*(?:'\\\\'|"\\\\")\s*,\s*(?:''|"")''', text):
        failures.append('Destructive backslash removal: '+str(path.relative_to(root)))
for path in js:
    if 'jquery' in path.name: continue
    result = subprocess.run(['node', '--check', str(path)], capture_output=True, text=True)
    if result.returncode: failures.append(result.stderr)
print(f'PHP syntax and destructive JSON replacement audit: {len(php)} maintained files')
print(f'JavaScript syntax: {len(js)} maintained files (bundled jQuery skipped)')
if failures:
    print('\n'.join(failures))
    raise SystemExit(1)
print('PASS: no syntax errors or remaining strip-all str_replace calls')
