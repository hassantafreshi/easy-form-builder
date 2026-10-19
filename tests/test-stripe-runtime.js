const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync('public/assets/js/stripe_pay-efb.js', 'utf8');
// Retry pauses run immediately here; the schedule itself is asserted through the recorded delays.
// The 30 s offline wait never times out in the test, so only the 'online' event can end it.
const pauses = [];
const fastTimers = {
  setTimeout(fn, ms) { if (ms >= 30000) return 0; pauses.push(ms); setImmediate(fn); return pauses.length; },
  clearTimeout() {},
};

function confirmSandbox(responses, options = {}) {
  const calls = [], reports = [], preparations = [], listeners = {};
  const sandbox = Object.assign({
    Headers, Date,
    console: { log() {}, error() {} },
    navigator: { onLine: options.offline ? false : true },
    efb_var: { nonce: 'cached-nonce', sid: 'cached-sid', rest_url: 'https://example.test/wp-json/' },
    window: {
      EFBRuntime: options.noRuntime ? undefined : {
        async prepare(fid, force) {
          assert.strictEqual(fid, 134);
          preparations.push(!!force);
          // request-health-efb.js answers a throttled/blocked route with the page nonce and session
          // ({legacy:true}) and throws only for a refusal of the form context itself.
          if (options.prepareRefused) { const e = new Error('The form context expired.'); e.status = 409; e.code = 'efb_context_expired'; throw e; }
          if (options.prepareLegacy) return { nonce: sandbox.efb_var.nonce, sid: 'form-sid', legacy: true };
          // A route that is down after an earlier success answers with the context prepared before.
          if (options.prepareReused) return { nonce: 'reused-nonce', sid: 'reused-sid', runtime_token: 'rt' };
          return { nonce: force ? 'forced-nonce' : 'live-nonce', sid: 'live-sid' };
        }
      },
      EFBRequestHealth: { report(operation, status) { reports.push({ operation, status }); } },
      addEventListener(event, fn) { listeners[event] = fn; if (options.offline && event === 'online') setImmediate(() => { sandbox.navigator.onLine = true; fn(); }); },
      removeEventListener(event) { delete listeners[event]; },
    },
    async fetch(url, init) {
      calls.push({ url, init });
      const next = responses.length > 1 ? responses.shift() : responses[0];
      if (next === 'network') throw new TypeError('Failed to fetch');
      if (next === 'html') return { ok: true, status: 200, async json() { throw new SyntaxError('Unexpected token <'); } };
      return { ok: next.code >= 200 && next.code < 300, status: next.code, async json() { return next.body; } };
    },
  }, fastTimers);
  if (options.refreshNonce) sandbox.efb_refresh_nonce = async () => { sandbox.efb_var.nonce = 'refreshed-nonce'; return 'refreshed-nonce'; };
  vm.createContext(sandbox);
  vm.runInContext(source, sandbox);
  return { sandbox, calls, reports, preparations };
}
const ok = { code: 200, body: { success: true, data: { success: true } } };
const unavailable = { code: 503, body: { success: true, data: { success: false, m: 'Payment verification is temporarily unavailable.' } } };
const mismatch = { code: 409, body: { success: true, data: { success: false, m: 'Payment has not been verified.' } } };
const staleNonce = { code: 403, body: { code: 'efb_nonce_expired', message: 'Invalid or expired nonce' } };

async function exerciseUI(mode) {
  const nodes = new Map(), network = [];
  const node = selector => {
    if (!nodes.has(selector)) {
      const classes = new Set(['disabled']);
      nodes.set(selector,{dataset:{formid:'134'},style:{display:'none'},textContent:'',innerHTML:'',classList:{add(v){classes.add(v);},remove(v){classes.delete(v);},contains(v){return classes.has(v);}},addEventListener(event,callback){this[event]=callback;}});
    }
    return nodes.get(selector);
  };
  const body = {querySelector: node};
  const alerts = [];
  let prepares = 0;
  const globals = Object.assign({
    Headers, AbortSignal, Date, console:{log(){},warn(){},error(){}}, navigator:{onLine:true},
    document:{getElementById(){return body;}},
    efb_var:{nonce:'old',sid:'old-session',rest_url:'https://example.test/wp-json/',text:{error:'Error',payNow:'Pay',pleaseWaiting:'Waiting',trackingCode:'Confirmation Code'}},
    efb_request_health:{payment_pending_message:'Do not pay again; payment needs confirmation.'},
    ajax_object_efm:{paymentKey:'pk_test_fixture'},valj_efb:[{paymentmethod:'charge',steps:1}],valueJson_ws:[{captcha:false}],
    sendBack_emsFormBuilder_pub:[{price:1}],formNameEfb:'Test',lan_name_emsFormBuilder:'en-US',
    localStorage:{setItem(){}},jQuery(){return{show(){}};},fun_disabled_all_pay_efb(){},check_form_payment_filled_efb(){globals.finished=true;},alert_message_efb(title,message){alerts.push(message);},
    window:{EFBRuntime:{async prepare(){
      prepares++;
      if(mode==='refused'){const e=new Error('Please log in to use this form.');e.status=403;e.code='efb_login_required';throw e;}
      if(mode==='legacy')return{nonce:'old',sid:'form-session',legacy:true};
      return{nonce:'fresh',sid:'fresh-session'};
    }},EFBRequestHealth:{report(){}}},
    Stripe(){return{elements(){return{create(){return{mount(){},on(){},update(){},focus(){}};}};},async confirmCardPayment(){if(mode==='sdk-rejected')throw new Error('Provider connection lost');if(mode==='sdk-uncertain')return{error:{type:'api_error'}};return{paymentIntent:{id:'pi_test',status:'succeeded'}};}};},
    async fetch(url,options){
      network.push({url,options});
      if(mode==='lost' && url.endsWith('/card/add')) throw new Error('Response lost');
      if(url.endsWith('/confirm')){
        const r = mode==='success' || mode==='legacy' ? ok : mode==='rejected' ? mismatch : unavailable;
        return{ok:r.code===200,status:r.code,async json(){return r.body;}};
      }
      const value={data:{success:true,client_secret:'test-secret',id:'test-track',transStat:{paymentIntent:'pi_test',paymentcurrency:'USD',total:1}}};
      return{ok:true,status:200,async json(){return value;}};
    },
  }, fastTimers);
  globals.window.efb_request_health=globals.efb_request_health;
  vm.createContext(globals);vm.runInContext(source,globals);
  globals.post_api_stripe_apay_efb(134);
  node('#btnStripeEfb').click();node('#btnStripeEfb').click();
  for(let i=0;i<60;i++)await new Promise(resolve=>setImmediate(resolve));
  if (mode==='refused') {
    // A refusal a retry would not change stops before anything is charged, and says why.
    assert.strictEqual(network.length,0,'No payment request after a refused form context');
    assert.deepStrictEqual(alerts,['Please log in to use this form.']);
    assert(!node('#btnStripeEfb').classList.contains('disabled'),'Pay Now is usable again');
    assert.strictEqual(node('#btnStripeEfb').innerHTML,'Pay');
    node('#btnStripeEfb').click();
    for(let i=0;i<20;i++)await new Promise(resolve=>setImmediate(resolve));
    assert.strictEqual(prepares,2,'The visitor can try again after logging in');
    return;
  }
  assert.strictEqual(network.filter(call=>call.url.endsWith('/card/add')).length,1,'Double click cannot initiate another payment');
  const request=network.find(call=>call.url.endsWith('/card/add'));
  const nonce = mode==='legacy' ? 'old' : 'fresh', session = mode==='legacy' ? 'form-session' : 'fresh-session';
  assert.strictEqual(request.options.headers.get('X-WP-Nonce'),nonce);
  assert.strictEqual(request.options.headers.get('sid'),session);
  assert.strictEqual(JSON.parse(request.options.body).sid,session);
  const confirms = network.filter(call=>call.url.endsWith('/confirm')).length;
  if (mode==='success' || mode==='legacy') {
    assert.strictEqual(globals.finished,true,'Verified payment renders through controls scoped to this form');
    assert.strictEqual(confirms,1);
  } else if (mode==='unverified') {
    assert.strictEqual(confirms,4,'Unavailable verification is retried');
    assert.strictEqual(globals.finished,true,'A charged visitor can still submit when verification stays unavailable');
  } else {
    if (mode==='rejected') assert.strictEqual(confirms,1,'A definitive mismatch is not retried');
    assert.strictEqual(globals.finished,undefined,'Uncertain or rejected payment never renders success');
    assert(node('#statusStripEfb').textContent.includes('Do not pay again'));
    if (mode==='rejected') {
      assert(node('#statusStripEfb').textContent.includes('Confirmation Code: test-track'),'The tracking code is shown so the charge can be matched');
      assert.strictEqual(node('#statusStripEfb').style.display,'block','The message is visible');
    }
    assert(node('#btnStripeEfb').classList.contains('disabled'));
    node('#btnStripeEfb').click();
    for(let i=0;i<2;i++)await new Promise(resolve=>setImmediate(resolve));
    assert.strictEqual(network.filter(call=>call.url.endsWith('/card/add')).length,1,'Uncertain payment cannot be started again');
  }
}

(async () => {
  let t;
  // Verified on the first attempt with the live session.
  t = confirmSandbox([ok]);
  assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'verified');
  assert.deepStrictEqual(t.preparations, [false]);
  assert.strictEqual(t.calls[0].init.headers.get('X-WP-Nonce'),'live-nonce');
  assert.strictEqual(t.calls[0].init.headers.get('sid'),'live-sid');
  assert.strictEqual(t.calls[0].init.headers.get('form-id'),'134');
  assert.strictEqual(t.calls[0].init.cache,'no-store');
  assert.strictEqual(t.calls[0].init.credentials,'same-origin');
  assert.deepStrictEqual(JSON.parse(t.calls[0].init.body), {paymentIntentId:'pi_test',trackid:'track-test'});

  // Definitive mismatch: stop at once.
  t = confirmSandbox([mismatch]);
  assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'rejected');
  assert.strictEqual(t.calls.length, 1, '409 is not retried');
  assert(t.reports.some(r=>r.operation==='payment' && r.status===409));

  // Gateway unavailable: 4 attempts on the 0/2/5/10 s schedule, then the caller may submit.
  pauses.length = 0;
  t = confirmSandbox([unavailable]);
  assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'unverified');
  assert.strictEqual(t.calls.length, 4);
  assert.deepStrictEqual(pauses, [2000, 5000, 10000]);
  assert(t.reports.some(r=>r.operation==='payment' && r.status===503));

  // Transient failures followed by success.
  t = confirmSandbox(['network', 'html', {code:502, body:{}}, ok]);
  assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'verified');
  assert.strictEqual(t.calls.length, 4);
  assert(t.reports.some(r=>r.operation==='payment' && r.status===0));

  // A throttled or blocked prepare resolves to the legacy context: the confirmation goes out with the
  // page nonce and the form's own session, and no second fallback is needed here.
  t = confirmSandbox([ok], {prepareLegacy:true});
  assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'verified');
  assert.strictEqual(t.calls.length, 1, 'A failed prepare still sends the confirmation');
  assert.strictEqual(t.calls[0].init.headers.get('X-WP-Nonce'),'cached-nonce');
  assert.strictEqual(t.calls[0].init.headers.get('sid'),'form-sid');

  // A refused form context (expired, login changed) still confirms a charged card with the page session.
  t = confirmSandbox([ok], {prepareRefused:true});
  assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'verified');
  assert.strictEqual(t.calls.length, 1, 'A refused context still sends the confirmation');
  assert.strictEqual(t.calls[0].init.headers.get('X-WP-Nonce'),'cached-nonce');
  assert.strictEqual(t.calls[0].init.headers.get('sid'),'cached-sid');

  // Stale page nonce while prepare keeps falling back: the legacy refresh supplies a new nonce and the
  // form session is kept.
  t = confirmSandbox([staleNonce, ok], {prepareLegacy:true, refreshNonce:true});
  assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'verified');
  assert.deepStrictEqual(t.preparations, [false, true]);
  assert.strictEqual(t.calls[0].init.headers.get('X-WP-Nonce'),'cached-nonce');
  assert.strictEqual(t.calls[1].init.headers.get('X-WP-Nonce'),'refreshed-nonce');
  assert.strictEqual(t.calls[1].init.headers.get('sid'),'form-sid');

  // Stale nonce while the forced prepare can only hand back the context prepared earlier on this page
  // (same refused nonce, no legacy flag): the refresh still runs instead of replaying the dead nonce.
  t = confirmSandbox([staleNonce, ok], {prepareReused:true, refreshNonce:true});
  assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'verified');
  assert.deepStrictEqual(t.preparations, [false, true]);
  assert.strictEqual(t.calls[0].init.headers.get('X-WP-Nonce'),'reused-nonce');
  assert.strictEqual(t.calls[1].init.headers.get('X-WP-Nonce'),'refreshed-nonce');
  assert.strictEqual(t.calls[1].init.headers.get('sid'),'reused-sid');

  // Stale nonce: a forced prepare, then the replay.
  t = confirmSandbox([staleNonce, ok]);
  assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'verified');
  assert.deepStrictEqual(t.preparations, [false, true]);
  assert.strictEqual(t.calls[1].init.headers.get('X-WP-Nonce'),'forced-nonce');

  // Stale nonce without the runtime (request-health-efb.js blocked): the legacy refresh supplies it.
  t = confirmSandbox([staleNonce, ok], {noRuntime:true, refreshNonce:true});
  assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'verified');
  assert.strictEqual(t.calls[0].init.headers.get('X-WP-Nonce'),'cached-nonce');
  assert.strictEqual(t.calls[1].init.headers.get('X-WP-Nonce'),'refreshed-nonce');

  // A generic 403 (security plugin, WAF) is not replayed; it is not a payment mismatch either.
  t = confirmSandbox([{code:403, body:{code:'waf_blocked'}}]);
  assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'unverified');
  assert.strictEqual(t.calls.length, 1, 'A generic 403 is not retried');
  assert(t.reports.some(r=>r.operation==='payment' && r.status===403));

  // Deterministic non-success answers are not retried.
  for (const answer of [{code:200, body:{data:{success:false}}}, {code:400, body:{data:{success:false}}}, {code:404, body:{code:'rest_no_route'}}]) {
    t = confirmSandbox([answer]);
    assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'unverified');
    assert.strictEqual(t.calls.length, 1);
  }

  // Offline: wait for the browser to come back online before the retry.
  t = confirmSandbox(['network', ok], {offline:true});
  assert.strictEqual(await t.sandbox.confirm_stripe_payment_efb('pi_test','track-test',134), 'verified');
  assert.strictEqual(t.calls.length, 2);
  assert.strictEqual(t.sandbox.navigator.onLine, true);

  await exerciseUI('success');
  await exerciseUI('legacy');
  await exerciseUI('refused');
  await exerciseUI('unverified');
  await exerciseUI('rejected');
  await exerciseUI('lost');
  await exerciseUI('sdk-rejected');
  await exerciseUI('sdk-uncertain');
  console.log('PASS: Stripe confirmation: live/legacy session, refused context, bounded retries, 409 fails closed, unavailable verification still submits, no card/add replays');
})().catch(error=>{console.error(error);process.exitCode=1;});
