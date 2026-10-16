'use strict';
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const {webcrypto}=require('node:crypto');
const source=fs.readFileSync('public/assets/js/request-health-efb.js','utf8');
const tick=()=>new Promise(resolve=>setImmediate(resolve));
function boot(fetch,storage=new Map()) {
  const handlers={}, events={};
  const window={crypto:webcrypto,sessionStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v)},addEventListener:(k,f)=>events[k]=f};
  const sandbox={window,fetch,URLSearchParams,Uint8Array,AbortSignal,efb_request_health:{url:'/ajax',rest_url:'/api/'},document:{addEventListener:(k,f)=>handlers[k]=f},efb_var:Object.freeze({nonce:'cached',sid:'cached'})};
  vm.createContext(sandbox);vm.runInContext(source,sandbox);
  return {runtime:window.EFBRuntime,health:window.EFBRequestHealth,events,handlers,storage};
}
const ok=value=>({ok:true,status:200,json:async()=>value});
(async()=>{
  const calls=[];
  let fail=false,done=false,serial=0;
  const app=boot(async(url,options)=>{
    calls.push({url,options});
    if(url==='/ajax') return ok({success:true,data:{token:'report'}});
    if(url.endsWith('prepare')) {
      if(fail) return {ok:false,status:403,json:async()=>({code:'efb_identity_changed',message:'Login changed'})};
      return ok({nonce:'fresh',sid:'live-'+(++serial),runtime_token:'signed-'+serial});
    }
    if(url.endsWith('status')) return ok({state:done?'done':'processing',result:done?{success:true,track:'confirmed'}:null});
    if(url.endsWith('response/session')) return ok({response_upload_token:'ticket-'+(++serial)});
    throw Error('Unexpected route');
  });
  const [a,b]=await Promise.all([app.runtime.prepare(42),app.runtime.prepare(42)]);
  assert.equal(a,b);assert.equal(calls.length,1);
  assert.equal(calls[0].options.cache,'no-store');assert.equal(JSON.parse(calls[0].options.body).runtime_token,'');
  const c=await app.runtime.prepare(43);assert.notEqual(a.sid,c.sid);
  const data={value:'private',valid:'captcha-1'};
  await app.runtime.decorate(data,42,'form');const id=data.request_id;
  data.valid='captcha-2';await app.runtime.decorate(data,42,'form');assert.equal(data.request_id,id);
  await app.runtime.prepare(42,true);await app.runtime.decorate(data,42,'form');assert.equal(data.request_id,id);
  assert.equal(await app.runtime.resolve(data,42,'form'),null);
  done=true;assert.equal((await app.runtime.resolve(data,42,'form')).data.track,'confirmed');
  app.runtime.complete(42,'form');await app.runtime.decorate(data,42,'form');assert.notEqual(data.request_id,id);
  const before=data.request_id;data.value='edited';await app.runtime.decorate(data,42,'form');assert.notEqual(data.request_id,before);
  assert.equal(await app.runtime.prepareTicket(99),null);
  app.runtime.rememberTicket({id:99,response_access_token:'proof'});
  const ticket=await app.runtime.prepareTicket(99);assert.equal(await app.runtime.prepareTicket(99),ticket);
  assert.notEqual((await app.runtime.prepareTicket(99,true)).response_upload_token,ticket.response_upload_token);
  fail=true;await assert.rejects(app.runtime.prepare(42,true),/Login changed/);await tick();
  assert.equal(calls.filter(c=>c.url.endsWith('prepare')&&JSON.parse(c.options.body).form_id===42).length,3);
  const storage=new Map();let blocked=true;
  const reporter=boot(async()=>{if(blocked)throw Error('WAF');return ok({success:true,data:{token:'report'}});},storage);
  reporter.health.report('form',403);await tick();
  const queued=JSON.parse(storage.get('efb_request_health_pending_v1'));assert.equal(queued[0].status,403);assert.deepEqual(Object.keys(queued[0]).sort(),['operation','status','time']);
  blocked=false;reporter.events.online();await tick();await tick();assert.equal(JSON.parse(storage.get('efb_request_health_pending_v1')).length,0);
  console.log('PASS: live context coalescing, independent forms, stable operation IDs, identity rejection, uncertain saves, ticket renewal and deferred private reporting');
})().catch(error=>{console.error(error);process.exitCode=1;});
