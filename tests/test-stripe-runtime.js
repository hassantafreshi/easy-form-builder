const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const calls = [], reports = [];
let result = { success: true, data: { success: true } };
let code = 200;
let preparations = 0;
const sandbox = {
  Headers,
  console: { log() {}, error() {} },
  efb_var: { nonce: 'cached-nonce', sid: 'cached-sid', rest_url: 'https://example.test/wp-json/' },
  window: {
    EFBRuntime: { async prepare(fid) { assert.strictEqual(fid, 134); preparations++; return {nonce: 'live-nonce', sid: 'live-sid'}; } },
    EFBRequestHealth: { report(operation, status) { reports.push({operation,status}); } },
  },
  async fetch(url, options) { calls.push({url,options}); return {ok: code===200, status: code, async json() {return result;} }; },
};
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync('public/assets/js/stripe_pay-efb.js','utf8'),sandbox);
async function exerciseUI(mode) {
  const nodes = new Map(), network = [];
  const node = selector => {
    if (!nodes.has(selector)) {
      const classes = new Set(['disabled']);
      nodes.set(selector,{dataset:{formid:'134'},style:{},textContent:'',innerHTML:'',classList:{add(v){classes.add(v);},remove(v){classes.delete(v);},contains(v){return classes.has(v);}},addEventListener(event,callback){this[event]=callback;}});
    }
    return nodes.get(selector);
  };
  const body = {querySelector: node};
  const globals = {
    Headers, AbortSignal, console:{log(){},warn(){},error(){}}, navigator:{onLine:true},
    document:{getElementById(){return body;}},
    efb_var:{nonce:'old',sid:'old-session',rest_url:'https://example.test/wp-json/',text:{error:'Error',payNow:'Pay',pleaseWaiting:'Waiting'}},
    efb_request_health:{payment_pending_message:'Do not pay again; payment needs confirmation.'},
    ajax_object_efm:{paymentKey:'pk_test_fixture'},valj_efb:[{paymentmethod:'charge',steps:1}],valueJson_ws:[{captcha:false}],
    sendBack_emsFormBuilder_pub:[{price:1}],formNameEfb:'Test',lan_name_emsFormBuilder:'en-US',
    localStorage:{setItem(){}},jQuery(){return{show(){}};},fun_disabled_all_pay_efb(){},check_form_payment_filled_efb(){globals.finished=true;},alert_message_efb(){},
    window:{EFBRuntime:{async prepare(){return{nonce:'fresh',sid:'fresh-session'};}},EFBRequestHealth:{report(){}}},
    Stripe(){return{elements(){return{create(){return{mount(){},on(){},update(){},focus(){}};}};},async confirmCardPayment(){if(mode==='sdk-rejected')throw new Error('Provider connection lost');if(mode==='sdk-uncertain')return{error:{type:'api_error'}};return{paymentIntent:{id:'pi_test',status:'succeeded'}};}};},
    async fetch(url,options){
      network.push({url,options});
      if(mode==='lost' && url.endsWith('/card/add')) throw new Error('Response lost');
      const value=url.endsWith('/confirm')?{data:{success:mode==='success'}}:{data:{success:true,client_secret:'test-secret',id:'test-track',transStat:{paymentIntent:'pi_test',paymentcurrency:'USD',total:1}}};
      return{ok:true,status:200,async json(){return value;}};
    },
  };
  globals.window.efb_request_health=globals.efb_request_health;
  vm.createContext(globals);vm.runInContext(fs.readFileSync('public/assets/js/stripe_pay-efb.js','utf8'),globals);
  globals.post_api_stripe_apay_efb(134);
  node('#btnStripeEfb').click();node('#btnStripeEfb').click();
  for(let i=0;i<8;i++)await new Promise(resolve=>setImmediate(resolve));
  assert.strictEqual(network.filter(call=>call.url.endsWith('/card/add')).length,1,'Double click cannot initiate another payment');
  const request=network.find(call=>call.url.endsWith('/card/add'));
  assert.strictEqual(request.options.headers.get('X-WP-Nonce'),'fresh');
  assert.strictEqual(request.options.headers.get('sid'),'fresh-session');
  assert.strictEqual(JSON.parse(request.options.body).sid,'fresh-session');
  if(mode==='success')assert.strictEqual(globals.finished,true,'Verified payment renders through controls scoped to this form');
  else {
    assert.strictEqual(globals.finished,undefined,'Uncertain payment never renders success');
    assert(node('#statusStripEfb').textContent.includes('Do not pay again'));
    assert(node('#btnStripeEfb').classList.contains('disabled'));
    node('#btnStripeEfb').click();
    for(let i=0;i<2;i++)await new Promise(resolve=>setImmediate(resolve));
    assert.strictEqual(network.filter(call=>call.url.endsWith('/card/add')).length,1,'Uncertain payment cannot be started again');
  }
}
(async () => {
  assert.strictEqual(await sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), true);
  assert.strictEqual(preparations,1);
  assert.strictEqual(calls[0].options.headers.get('X-WP-Nonce'),'live-nonce');
  assert.strictEqual(calls[0].options.headers.get('sid'),'live-sid');
  assert.strictEqual(calls[0].options.headers.get('form-id'),'134');
  assert.strictEqual(calls[0].options.cache,'no-store');
  assert.strictEqual(calls[0].options.credentials,'same-origin');
  code = 403; result = {code:'waf_blocked'};
  assert.strictEqual(await sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), false);
  assert.strictEqual(calls.length,2, 'A generic 403 must not automatically replay financial operations');
  assert(reports.some(r=>r.operation==='payment' && r.status===403));
  code = 200; result = {success:true,data:{success:false}};
  assert.strictEqual(await sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), false);
  sandbox.window.EFBRuntime.prepare=async()=>{throw new Error('identity changed');};
  assert.strictEqual(await sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), false);
  assert.strictEqual(calls.length,3,'Failed context validation must not send payment confirmation');
  await exerciseUI('success');
  await exerciseUI('unverified');
  await exerciseUI('lost');
  await exerciseUI('sdk-rejected');
  await exerciseUI('sdk-uncertain');
  console.log('PASS: Stripe live nonce/session headers, verification result, failure reporting and no blind financial retries');
})().catch(error=>{console.error(error);process.exitCode=1;});
