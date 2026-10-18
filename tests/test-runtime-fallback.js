'use strict';
// A failed forms/session/prepare must never block a submission the server would accept.
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const {webcrypto}=require('node:crypto');
const health=fs.readFileSync('public/assets/js/request-health-efb.js','utf8');
const core=fs.readFileSync('public/assets/js/core-efb.js','utf8');
const slice=(s,a,b)=>s.slice(s.indexOf(a),s.indexOf(b,s.indexOf(a)));
const reply=(status,body)=>{const r={ok:status>=200&&status<300,status,json:async()=>{if(body==='html')throw Error('Unexpected token <');return body;}};r.clone=()=>r;return r;};
let now=1e12;
function boot(route,extra={}) {
  const calls=[];
  const window={crypto:webcrypto,sessionStorage:{getItem:()=>null,setItem(){}},addEventListener(){}};
  const sandbox=Object.assign({window,URLSearchParams,Uint8Array,Headers,Date:{now:()=>now},
    efb_request_health:{url:'/ajax',rest_url:'/api/'},document:{addEventListener(){}},
    efb_var:{nonce:'page-nonce',sid:'page-sid',rest_url:'/wp-json/'},valj_efb_new:[{id:7,sid:'form-7-sid'}],
    fetch:async(url,options)=>{calls.push({url,options});if(url==='/ajax')return reply(200,{success:false});return route(url,options);}},extra);
  vm.createContext(sandbox);vm.runInContext(health,sandbox);
  return {sandbox,runtime:window.EFBRuntime,calls,prepares:()=>calls.filter(c=>c.url.endsWith('session/prepare')).length};
}
(async()=>{
  // Recoverable failures resolve to the 4.3.0 request: page nonce + page sid, no runtime fields.
  for (const [label,response] of [
    ['429',()=>reply(429,{code:'rest_too_many_requests'})],
    ['network',()=>{throw new TypeError('Failed to fetch');}],
    ['origin',()=>reply(403,{code:'efb_refresh_origin_denied',message:'x'})],
    ['route blocked',()=>reply(404,{code:'rest_no_route'})],
    ['WAF html',()=>reply(403,'html')],
    ['storage',()=>reply(503,{code:'efb_session_storage_failed'})],
    ['malformed',()=>reply(200,{nonce:'n'})],
  ]) {
    const app=boot(response);
    const value=await app.runtime.prepare(7);
    assert.equal(value.legacy,true,label);assert.equal(value.nonce,'page-nonce',label);assert.equal(value.sid,'form-7-sid',label);
    const data={value:'v',sid:'form-7-sid'};
    const context=await app.runtime.decorate(data,7,'form');
    assert.equal(context.nonce,'page-nonce',label);assert.equal(data.sid,'form-7-sid',label);
    assert.equal('runtime_token' in data,false,label);assert.equal('request_id' in data,false,label);
    assert.equal(app.prepares(),1,label+': the backoff answers focus and submit without another request');
    assert.equal((await app.runtime.prepare(0)).sid,'page-sid',label);
  }
  // Refusals that a legacy request would not change still stop.
  for (const [status,code] of [[403,'efb_login_required'],[409,'efb_context_expired'],[409,'efb_identity_changed'],[404,'efb_form_missing'],[400,'efb_form_invalid']]) {
    const app=boot(()=>reply(status,{code,message:'refused:'+code}));
    await assert.rejects(app.runtime.prepare(7),error=>error.code===code&&error.status===status&&error.message==='refused:'+code);
    await assert.rejects(app.runtime.decorate({},7,'form'),new RegExp(code));
  }
  // A context prepared earlier on the page is reused (duplicate protection kept), then expires to legacy.
  now=1e12; let fail=false, serial=0;
  const app=boot(()=>fail?reply(429,{code:'rest_too_many_requests'}):reply(200,{nonce:'live-'+(++serial),sid:'live-sid',runtime_token:'signed'}));
  const data={value:'v'}; await app.runtime.decorate(data,7,'form'); const id=data.request_id;
  assert.equal(data.runtime_token,'signed');
  fail=true; now+=300000;
  const retry={value:'v'}; await app.runtime.decorate(retry,7,'form');
  assert.equal(retry.runtime_token,'signed');assert.equal(retry.request_id,id,'a retry keeps its operation id');
  now+=3600000+60000;
  const late={value:'v'}; const lateContext=await app.runtime.decorate(late,7,'form');
  assert.equal(lateContext.legacy,true);assert.equal('runtime_token' in late,false);
  const forced=app.prepares(); await app.runtime.prepare(7,true); assert.equal(app.prepares(),forced+1,'force bypasses the backoff');
  fail=false; now+=61000; assert.equal((await app.runtime.prepare(7)).runtime_token,'signed','recovers once the route answers');
  // Dashboard calls keep their own nonce when legacy.
  const admin=boot(()=>reply(503,{code:'x'}));
  const payload={action:'set_replyMessage_id_Emsfb',nonce:'core-nonce'};
  assert.equal((await admin.runtime.decorate(payload,0,'admin_reply')).nonce,'core-nonce');
  // Response box: a failed ticket refresh keeps the tracker's token; an explicit refusal stops.
  let ticketReply=reply(503,{code:'efb_ticket_token_storage_failed'});
  const box=boot(url=>url.endsWith('response/session')?ticketReply:reply(200,{nonce:'n',sid:'s',runtime_token:'t'}));
  box.runtime.rememberTicket({id:9,response_access_token:'proof'});
  assert.equal(await box.runtime.prepareTicket(9),null);
  ticketReply=reply(403,{code:'efb_origin_denied'}); assert.equal(await box.runtime.prepareTicket(9,true),null);
  ticketReply=reply(403,{code:'efb_ticket_denied',message:'Reopen'}); await assert.rejects(box.runtime.prepareTicket(9,true),/Reopen/);
  // Real public transport: the request goes out with the page nonce and sid when prepare is throttled.
  const sent=[];
  const pub=boot(url=>url.endsWith('session/prepare')?reply(429,{code:'rest_too_many_requests'}):(sent.push(url),reply(200,{success:true})),{efb_refresh_nonce:async()=>false,AbortSignal});
  vm.runInContext(slice(core,'async function efb_is_nonce_error_efb',' post_api_forms_efb='),pub.sandbox);
  const response=await pub.sandbox.efb_fetch_public_request_efb('/wp-json/Emsfb/v1/forms/response/get',{method:'POST',headers:{'X-WP-Nonce':'page-nonce','sid':'page-sid'},body:'{}'},'tracker','page-sid',0);
  assert.equal(response.status,200);assert.deepEqual(sent,['/wp-json/Emsfb/v1/forms/response/get']);
  const out=pub.calls.find(c=>c.url.endsWith('response/get'));
  assert.equal(out.options.headers.get('X-WP-Nonce'),'page-nonce');assert.equal(out.options.headers.get('sid'),'page-sid');
  // Stripe: a charged card is always confirmed, even when prepare is throttled.
  const stripe=boot(url=>url.endsWith('session/prepare')?reply(429,{code:'rest_too_many_requests'}):reply(200,{data:{success:true}}),{console:{log(){},error(){}},AbortSignal});
  stripe.sandbox.window.EFBRequestHealth={report(){}};
  vm.runInContext(fs.readFileSync('public/assets/js/stripe_pay-efb.js','utf8'),stripe.sandbox);
  assert.equal(await stripe.sandbox.confirm_stripe_payment_efb('pi_test','track',7),'verified');
  const confirm=stripe.calls.find(c=>c.url.endsWith('stripe/confirm'));
  assert.equal(confirm.options.headers.get('X-WP-Nonce'),'page-nonce');assert.equal(confirm.options.headers.get('sid'),'form-7-sid');
  // A refusal that carries its own message (Human Shield strict mode 403, rate limit 429) is shown
  // as that message instead of the generic jQuery error; other failures keep the generic text.
  for (const [status,body,expected] of [
    [403,{success:false,data:{success:false,m:'Your request looked too fast or unusual.',code:'efb_human_shield_blocked'}},'Your request looked too fast or unusual.'],
    [429,{success:false,data:{success:false,m:'Too many requests. Please try again shortly.'}},'Too many requests. Please try again shortly.'],
    [403,'html','JQ-500'],
    [403,{code:'rest_forbidden',message:'Sorry'},'JQ-500'],
    [500,{success:false,data:{m:'internal detail'}},'JQ-500'],
  ]) {
    const shown=[];
    const sub=boot(()=>reply(status,body),{efb_refresh_nonce:async()=>false,AbortSignal,localStorage:{getItem:()=>null,removeItem(){}},
      ajax_object_efm:{text:{eJQ500:'JQ-500',nonceExpired:'expired'}},
      efb_report_submit_ajax_error_efb(){},response_fill_form_efb:async(res)=>{shown.push(res.data.m);},document:{getElementById:()=>null,addEventListener(){}}});
    sub.sandbox.window.EFBRuntime=undefined;
    vm.runInContext(slice(core,'async function efb_is_nonce_error_efb','post_api_tracker_check_efb='),sub.sandbox);
    await sub.sandbox.post_api_forms_efb({type:'form',sid:'s'},7);
    assert.deepEqual(shown,[expected],`HTTP ${status}`);
  }
  console.log('PASS: prepare fallback for 429/network/origin/route/WAF/5xx/malformed, final refusals, context reuse, backoff, admin nonce, ticket fallback, public transport, Stripe confirm and server refusal messages');
})().catch(error=>{console.error(error);process.exitCode=1;});
