const fs = require('fs'), vm = require('vm'), path = require('path');
const source = fs.readFileSync(path.join(__dirname, '../includes/admin/assets/js/new-efb.js'), 'utf8');
const start = source.indexOf('function efbParseJson('), end = source.indexOf('function fun_upload_file_api_emsFormBuilder(', start);
const context = {}; vm.createContext(context); vm.runInContext(source.slice(start, end), context);
const cases = JSON.parse(fs.readFileSync(0, 'utf8'));
process.stdout.write(JSON.stringify(cases.map(value => { try { return context.efbNormalizeJson(value); } catch (_) { return value; } })));
