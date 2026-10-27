const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync(require('path').join(__dirname, '../includes/admin/assets/js/new-efb.js'), 'utf8');
const start = source.indexOf('function efbParseJson(');
// The boundary is now declared async; slicing at "function …" left a stray "async" behind.
const asyncEnd = source.indexOf('async function fun_upload_file_api_emsFormBuilder(', start);
const end = asyncEnd !== -1 ? asyncEnd : source.indexOf('function fun_upload_file_api_emsFormBuilder(', start);
const context = {};
vm.createContext(context);
vm.runInContext(source.slice(start, end), context);
const fixture = {text: 'متن می‌شود "quoted" &quot;', path: 'C:\\folder\\new\\file.txt', literal: '\\n \\t \\u1234', newline: 'line\nnext', html: '<a href="https://example.test/a">link</a>', nested: {regex: '/\\d+\\s/', list: [1, true, null]}};
const raw = JSON.stringify(fixture);
let checks = 0;
function equal(value, expected) { assert.strictEqual(JSON.stringify(value), JSON.stringify(expected)); checks++; }
equal(context.efbParseJson(raw), fixture);
equal(JSON.parse(context.efbNormalizeJson(raw)), fixture);
let legacy = raw;
for(let i=1; i<=5; i++) { legacy = legacy.replace(/"/g, '\\"'); equal(context.efbParseJson(legacy), fixture); }
equal(JSON.parse(context.replaceContentMessageEfb(raw)), fixture);
equal(context.replaceContentMessageEfb('plain \\n text'), 'plain \\n text');
equal(context.replaceContentMessageEfb('line@efb@nq#next'), 'line<br>next');
equal(context.replaceContentMessageEfb('{ "user text": "keep spacing" }'), '{ "user text": "keep spacing" }');
equal(context.efbParseJson(context.replaceContentMessageEfb(legacy)), fixture);
assert.throws(() => context.efbParseJson('{broken')); checks++;
equal(context.efbParseJson(false), false);
const legacyRecords = [
  [{type:'text', value:'old@efb@dq#text@efb@nq#next'}, {type:'file', value:'@file@', url:'https://example.test/a.pdf'}],
  {smtp:true, emailTemp:'<div class="mail">می‌شود</div>', femail:'sender@example.test'},
  [{type:'text', id_:'field1', required:true, label:'Old form'}]
];
for (const record of legacyRecords) {
  const rawRecord = JSON.stringify(record);
  for (const layers of [0, 1, 2, 5]) {
    let stored = rawRecord;
    for (let i=0; i<layers; i++) stored = stored.replace(/"/g, '\\"');
    equal(context.efbParseJson(stored), record);
    equal(JSON.parse(context.efbNormalizeJson(stored)), record);
  }
  equal(context.efbParseJson('\uFEFF' + rawRecord), record);
}
const fullSlashRecord = {text:"O'Reilly", path:'C:\\new\\file'};
const fullSlashed = JSON.stringify(fullSlashRecord).replace(/[\\'"\0]/g, ch => '\\' + (ch === '\0' ? '0' : ch));
equal(context.efbParseJson(fullSlashed), fullSlashRecord);
equal(context.efbParseJson(context.replaceContentMessageEfb('[{\\"value\\":\\"old@efb@dq#text@efb@nq#next\\"}]')), [{value:"old''text<br>next"}]);
console.log(`PASS: ${checks} JavaScript JSON preservation checks`);
