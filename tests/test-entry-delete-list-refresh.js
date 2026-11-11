/**
 * Deleting one entry from the entries list must update the entries list.
 *
 * fun_confirm_remove_message_emsFormBuilder() (list_form-efb.js, called from
 * emsFormBuilder_delete() in admin-efb.js for type 'message') used to look up
 * a *form* whose form_id equalled the entry's msg_id, remove it from
 * valueJson_ws_form and redraw the forms view. The function is lifted out of
 * the shipped file and run against stubs.
 *
 * Run: node tests/test-entry-delete-list-refresh.js
 */
'use strict';

const fs = require('fs');
const path = require('path');

const LIST = path.join(__dirname, '..', 'includes/admin/assets/js/list_form-efb.js');
const src = fs.readFileSync(LIST, 'utf8');

function grab(name) {
  const m = src.match(new RegExp('function ' + name + '\\([\\s\\S]*?\\n}\\n'));
  if (!m) throw new Error('missing ' + name + ' in list_form-efb.js');
  return m[0];
}

let pass = 0;
let fail = 0;
function t(label, condition, detail) {
  if (condition) { pass++; console.log(`[PASS] ${label}`); }
  else { fail++; console.log(`[FAIL] ${label}${detail ? ' — ' + detail : ''}`); }
}

function run(entryId, messages, forms) {
  const calls = { server: [], listRender: [], formsRender: 0 };
  const state = { valueJson_ws_messages: messages, valueJson_ws_form: forms };
  const factory = new Function('state', 'calls',
    'let valueJson_ws_messages = state.valueJson_ws_messages;' +
    'let valueJson_ws_form = state.valueJson_ws_form;' +
    'const count_row_emsFormBuilder = 10;' +
    'function fun_delete_message_with_id_by_server(id) { calls.server.push(id); }' +
    'function fun_ws_show_list_messages(v) { calls.listRender.push(v.map(x => x.msg_id)); }' +
    'function fun_emsFormBuilder_render_view() { calls.formsRender++; }' +
    grab('fun_confirm_remove_message_emsFormBuilder') +
    'return function (id) { fun_confirm_remove_message_emsFormBuilder(id); state.valueJson_ws_messages = valueJson_ws_messages; state.valueJson_ws_form = valueJson_ws_form; };'
  );
  factory(state, calls)(entryId);
  return { calls, state };
}

const forms = () => [{ form_id: '7', form_name: 'Contact' }, { form_id: '12', form_name: 'Order' }];
const messages = () => [{ msg_id: '12', track: 'AAA' }, { msg_id: '15', track: 'BBB' }];

console.log('=== single entry delete ===');
let r = run(12, messages(), forms());
t('server delete is called with the entry id', JSON.stringify(r.calls.server) === '[12]');
t('the entry is removed from the entries list', JSON.stringify(r.state.valueJson_ws_messages.map(x => x.msg_id)) === '["15"]');
t('the entries list is redrawn with the remaining entries', JSON.stringify(r.calls.listRender) === '[["15"]]');
t('a form whose id equals the entry id is left alone', r.state.valueJson_ws_form.length === 2 && r.state.valueJson_ws_form[1].form_id === '12');
t('the forms view is not redrawn', r.calls.formsRender === 0);

r = run(99, messages(), forms());
t('unknown id: list unchanged but still redrawn safely', JSON.stringify(r.calls.listRender) === '[["12","15"]]');

r = run(12, [], forms());
t('empty entries list does not throw', JSON.stringify(r.calls.listRender) === '[[]]');

console.log('\n=== caller ===');
const admin = fs.readFileSync(path.join(__dirname, '..', 'includes/admin/assets/js/admin-efb.js'), 'utf8');
t("admin-efb.js still routes type 'message' to this function", /type=='message'\)\{\s*fun_confirm_remove_message_emsFormBuilder\(Number\(id\)\)/.test(admin));

console.log(`\n========================================\nRESULTS: ${pass} passed, ${fail} failed\n========================================`);
process.exit(fail > 0 ? 1 : 0);
