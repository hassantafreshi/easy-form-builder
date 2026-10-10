"""Deterministic PHP/JS differential tests, without DB, network or deletion."""
import json
import random
import subprocess
from pathlib import Path
root = Path(__file__).resolve().parents[1]
rng = random.Random(7037)
texts = ['', '"', '\\', '\\"', '\\\\', '\\n', '\n', '\r\n', '\t', '\0', "O'Reilly", 'می‌شود', '🙂', '&quot;', '\ufeff', '@efb@dq#', 'C:\\new\\file', '</script>', '\\u1234']
cases, expected = [], []
for index in range(300):
    value = ''.join(rng.choice(texts) for _ in range(rng.randrange(1, 9)))
    record = {'text': value, 'nested': [value, None, True, index], 'key"\\': 'value'}
    raw = json.dumps(record, ensure_ascii=index % 2 == 0, separators=(',', ':'))
    for layers in range(6):
        stored = raw
        for _ in range(layers): stored = stored.replace('"', '\\"')
        cases.append(stored); expected.append(raw)
    cases.append('\ufeff'+raw); expected.append(raw)
for raw in ['{"large":900719925474099312345}', '{ "float":1.0000000000000001,"zero":-0,"exp":1e309 }', '[]', '{}', 'null', 'false', '0', '"text"', ' { "spacing" : true } ', '{broken', '{"cut":', '', 'not JSON', '{"bad":"\\q"}', '{"bad":"\\uZZZZ"}', '{"bad":"literal\nnewline"}']:
    cases.append(raw); expected.append(raw)
payload = json.dumps(cases, ensure_ascii=False)
for runtime, command in [('PHP', ['C:/xampp/php/php.exe', 'tests/json-matrix-php.php']), ('JavaScript', ['node', 'tests/json-matrix-js.js'])]:
    result = subprocess.run(command, cwd=root, input=payload, capture_output=True, encoding='utf-8', check=True)
    actual = json.loads(result.stdout)
    for index, (got, want) in enumerate(zip(actual, expected)):
        if got != want:
            raise AssertionError(f'{runtime} scenario {index}: input={cases[index]!r}, got={got!r}, expected={want!r}')
    assert len(actual) == len(expected)
    print(f'PASS: {runtime}: {len(cases)} scenarios; exact JSON encoding preserved')
