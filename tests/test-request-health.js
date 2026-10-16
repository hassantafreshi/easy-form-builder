'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const read = p => fs.readFileSync(p, 'utf8');
const core = read('public/assets/js/core-efb.js');
const upload = read('includes/admin/assets/js/new-efb.js');
const slice = (s, a, b) => s.slice(s.indexOf(a), s.indexOf(b, s.indexOf(a)));
const flush = () => new Promise(resolve => setImmediate(resolve));
const response = (status, code) => ({status, ok: status < 400,
  clone: () => ({json: async () => { if (code === 'html') throw Error('HTML'); return {code}; }})});

(async () => {
  let calls = [], reports = [], replies = [], refreshes = 0;
  const sandbox = {Headers, window: {EFBRequestHealth: {report: (...args) => reports.push(args)}},
    fetch: async (...args) => { calls.push(args); return replies.shift(); },
    efb_refresh_nonce: async () => { refreshes++; return 'same-nonce'; }};
  vm.createContext(sandbox);
  vm.runInContext(slice(core, 'async function efb_is_nonce_error_efb', ' post_api_forms_efb='), sandbox);
  const options = {method: 'POST', headers: {'X-WP-Nonce':'same-nonce'}, body:'original-body'};
  replies = [response(403, 'waf_denied')];
  await sandbox.efb_fetch_public_request_efb('/reply', options, 'reply', 'sid', 0);
  assert.equal(calls.length, 1); assert.equal(refreshes, 0); assert.equal(reports[0][1], 403);
  calls = []; reports = [];
  replies = [response(403, 'html')];
  await sandbox.efb_fetch_public_request_efb('/reply', options, 'reply', 'sid', 0);
  assert.equal(calls.length, 1);
  calls = []; reports = [];
  replies = [response(403, 'efb_nonce_expired'), response(200)];
  await sandbox.efb_fetch_public_request_efb('/reply', options, 'reply', 'sid', 0);
  assert.equal(calls.length, 2); assert.equal(reports.length, 0);
  assert.equal(calls[1][1].body, 'original-body'); assert.equal(calls[1][1].headers.get('X-WP-Nonce'), 'same-nonce');
  calls = []; replies = [response(403, 'efb_nonce_expired'), response(403, 'efb_nonce_expired')];
  await sandbox.efb_fetch_public_request_efb('/reply', options, 'reply', 'sid', 0);
  assert.equal(calls.length, 2); assert.equal(reports.at(-1)[1], 403);
  sandbox.fetch = async () => { throw Error('network'); };
  await assert.rejects(sandbox.efb_fetch_public_request_efb('/reply', options, 'reply', 'sid', 0));
  assert.equal(reports.at(-1)[1], 0);

  // Run the real upload transport: a refresh must preserve ticket and file binding.
  let statuses = [], sent = [], uploadReports = [];
  class FakeXHR {
    constructor() { this.events = {}; this.upload = {addEventListener() {}}; this.headers = {}; }
    addEventListener(name, fn) { this.events[name] = fn; }
    open() {}
    setRequestHeader(k, v) { this.headers[k] = v; }
    send(body) { sent.push({body, headers:this.headers}); const r = statuses.shift(); this.status = r.status; this.responseText = JSON.stringify(r.data); this.events.load(); }
  }
  class FakeFormData { constructor() { this.values = {}; } append(k,v) { this.values[k] = v; } }
  const up = {window:{EFBRequestHealth:{report:(...a)=>uploadReports.push(a)}},
    efb_var:{page_id:7,rest_url:'/wp-json/'}, XMLHttpRequest:FakeXHR, FormData:FakeFormData,
    document:{getElementById:()=>null}, efb_fetch_fresh_nonce_efb:async ()=>'same-nonce'};
  vm.createContext(up);
  vm.runInContext(slice(upload, 'function fetch_uploadFile(', 'if (!Array.prototype.findIndex)'), up);
  const file = {name:'document.pdf'};
  const scope = {response_id:42, response_track:'private-track', response_token:'private-token'};
  statuses = [{status:403,data:{code:'efb_nonce_expired'}}, {status:200,data:{success:true}}];
  await up.fetch_uploadFile(file,'f','resp','same-nonce',7,0,'sid',scope);
  assert.equal(sent.length,2);
  for (const request of sent) {
    assert.equal(request.body.values['async-upload'],file);
    assert.equal(request.body.values.response_token,'private-token');
    assert.equal(request.body.values.response_id,'42');
    assert.equal(request.body.values.sid,'sid');
  }
  sent = []; statuses = [{status:403,data:{code:'permission_denied'}}];
  await assert.rejects(up.fetch_uploadFile(file,'f','resp','same-nonce',7,0,'sid',scope));
  assert.equal(sent.length,1); assert.equal(uploadReports[0][1],403);
  let ticketRenewals=0;
  up.window.EFBRuntime={prepareTicket:async(id,force)=>{assert.equal(id,42);assert.equal(force,true);ticketRenewals++;return {response_upload_token:'renewed-token'};}};
  sent=[];statuses=[{status:200,data:{success:true,data:{success:false,code:'efb_ticket_access_expired'}}},{status:200,data:{success:true,data:{success:true}}}];
  await up.fetch_uploadFile(file,'f','resp','same-nonce',7,0,'sid',scope);
  assert.equal(ticketRenewals,1);assert.equal(sent.length,2);
  assert.equal(sent[0].body.values.response_token,'private-token');assert.equal(sent[1].body.values.response_token,'renewed-token');
  assert.equal(sent[1].body.values['async-upload'],file);assert.equal(sent[1].body.values.response_track,'private-track');
  assert.equal(scope.response_token,'private-token');
  sent=[];statuses=[{status:200,data:{success:true,data:{success:false,code:'efb_ticket_access_expired'}}},{status:200,data:{success:true,data:{success:false,code:'efb_ticket_access_expired'}}}];
  await up.fetch_uploadFile(file,'f','resp','same-nonce',7,0,'sid',scope);
  assert.equal(sent.length,2);assert.equal(ticketRenewals,2); // One renewal per operation, never a loop.

  // Dashboard recovery preserves ticket/file payload and never replays a security veto.
  let adminReplies = [], adminCalls = [], adminReports = [], successCount = 0;
  const admin = {window:{EFBRequestHealth:{report:(...a)=>adminReports.push(a)}},
    document:{getElementById:()=>null}, efb_var:{text:{}}, _efb_core_nonce_:'old',
    ajax_object_efm:{ajax_url:'/admin-ajax.php'}, efb_fetch_fresh_nonce_efb:async ()=>'fresh',
    state_rply_btn_efb:()=>{}, jQuery:{post:(url,body,success)=> {
      adminCalls.push(body); const reply = adminReplies.shift();
      if (reply.ok) success(reply);
      return {fail(fn) { if (!reply.ok) fn(reply); return this; }};
    }}};
  vm.createContext(admin);
  vm.runInContext(slice(read('includes/admin/assets/js/list_form-efb.js'), 'function efb_response_admin_post_efb(', 'function fun_emsFormBuilder_get_all_response_by_id'),admin);
  const payload = {action:'set_replyMessage_id_Emsfb',id:42,nonce:'old',message:'unchanged attachment payload'};
  adminReplies = [{status:403,responseJSON:{data:{code:'efb_nonce_expired'}}}, {ok:true}];
  admin.efb_response_admin_post_efb(payload,()=>successCount++,'admin_reply');
  await flush();
  assert.equal(successCount,1); assert.equal(adminCalls.length,2);
  assert.equal(adminCalls[1].message,payload.message); assert.equal(adminCalls[1].id,42);
  assert.equal(adminCalls[1].nonce,'fresh'); assert.equal(payload.nonce,'old');
  adminCalls = []; adminReplies = [{status:403,responseJSON:{data:{code:'permission_denied'}}}];
  admin.efb_response_admin_post_efb(payload,()=>successCount++,'admin_reply');
  assert.equal(adminCalls.length,1); assert.equal(adminReports.at(-1)[1],403);

  // Browser reporter contains only allowlisted metadata, deduplicates, and never rejects callers.
  const bodies = [];
  const report = {window:{}, URLSearchParams, efb_request_health:{url:'/admin-ajax.php'},
    fetch:async (url, opts)=> { bodies.push(Object.fromEntries(opts.body)); return {ok:true,json:async()=>({success:true,data:{token:'fresh'}})}; }};
  vm.createContext(report); vm.runInContext(read('public/assets/js/request-health-efb.js'),report);
  report.window.EFBRequestHealth.report('reply',403);
  report.window.EFBRequestHealth.report('reply',403);
  report.window.EFBRequestHealth.report('attacker-controlled',500);
  await flush(); await flush();
  assert.equal(bodies.length,2);
  assert.deepEqual(Object.keys(bodies[1]).sort(),['action','operation','phase','status','token']);
  report.fetch = async () => { throw Error('blocked'); };
  report.window.EFBRequestHealth.report('upload',500);
  await flush(); await flush();
  console.log('PASS: selective recovery, one retry, network failure, unchanged upload scope, metadata privacy, deduplication, blocked reporting');
})().catch(e => { console.error(e); process.exitCode = 1; });
