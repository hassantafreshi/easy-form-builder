'use strict';
const fs = require('fs'); const E = require('./efb-masks.js');
const cat = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const rows = [];
for (const m of cat.masks) {
  for (const v of m.examples_valid) { const r = E.validate(m, v); rows.push({ id: m.id, kind: 'valid', input: v, ok: r.ok, code: r.code, value: r.value, stored: r.ok ? E.toStored(m, r.value) : null, pass: r.ok }); }
  for (const x of m.examples_invalid) { const r = E.validate(m, x.value); rows.push({ id: m.id, kind: 'invalid', input: x.value, ok: r.ok, code: r.code, value: r.value, stored: null, pass: !r.ok && r.code === x.expect }); }
  for (const f of (m.examples_format || [])) { const out = E.formatValue(m, f.input); rows.push({ id: m.id, kind: 'format', input: f.input, ok: out === f.output, code: null, value: out, stored: null, pass: out === f.output }); }
}
process.stdout.write(JSON.stringify(rows));
