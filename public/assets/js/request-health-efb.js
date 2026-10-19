(function () {
  'use strict';
  const seen = new Set();
  let tokenPromise;
  let flushing=false;
  const storageKey='efb_request_health_pending_v1';
  let pending=[];
  try {
    const value=JSON.parse(window.sessionStorage.getItem(storageKey)||'[]');
    if (Array.isArray(value)) pending=value.filter(row=>row && typeof row.operation==='string' && Number.isInteger(row.status) && Number.isFinite(row.time) && Date.now()-row.time<86400000).slice(-8);
  } catch(e) {}
  function persist() { try { window.sessionStorage.setItem(storageKey,JSON.stringify(pending)); } catch(e) {} }
  const send = (data) => fetch(efb_request_health.url, {
    method: 'POST', credentials: 'same-origin', cache: 'no-store',
    body: new URLSearchParams(Object.assign({ action: 'efb_request_health' }, data)),
    signal: typeof AbortSignal !== 'undefined' && AbortSignal.timeout ? AbortSignal.timeout(8000) : undefined
  }).then(r => r.ok ? r.json() : null);
  async function flush() {
    if (flushing || !pending.length) return;
    flushing=true;
    try {
      while (pending.length) {
        const row=pending[0];
        if (!tokenPromise) tokenPromise=send({phase:'token'}).finally(()=>{tokenPromise=null;});
        const token=await tokenPromise;
        if (!token?.success || !token.data?.token) break;
        const result=await send({phase:'report',token:token.data.token,operation:row.operation,status:row.status});
        if (!result?.success) break;
        pending.shift(); persist();
      }
    } catch(e) {} finally { flushing=false; }
  }
  window.EFBRequestHealth = {
    report(operation, status) {
      status = Number(status) || 0;
      if (!['form', 'tracker', 'reply', 'upload', 'refresh', 'prepare', 'shield', 'payment', 'admin_reply', 'admin_tracker'].includes(operation) ||
          !Number.isInteger(status) || (status !== 0 && (status < 400 || status > 599))) return;
      const key = operation + ':' + status;
      if (seen.has(key) || seen.size >= 8) return;
      seen.add(key);
      // No form values, tracking codes, URLs, error bodies or credentials in reports.
      if (!pending.some(row=>row.operation===operation && row.status===status)) pending.push({operation,status,time:Date.now()});
      pending=pending.slice(-8); persist(); flush();
    }
  };
  if (window.addEventListener) window.addEventListener('online',flush);
  if (pending.length) flush();
}());

(function () {
  'use strict';
  if (typeof document === 'undefined' || typeof efb_request_health === 'undefined' || !efb_request_health.rest_url) return;
  const contexts = new Map();
  const intents = new Map();
  const tickets = new Map();
  // Refusals a retry or an older request would not change. Every other prepare failure
  // (offline, timeout, 429, 5xx, a WAF or REST allow-list blocking the route, a proxy
  // that rewrites Origin) falls back to the request 4.3.0 sent: the page nonce and sid,
  // no runtime_token or request_id. The server still checks that request in full and
  // Form_Runtime::begin() accepts it as an older client.
  const FINAL = ['efb_login_required', 'efb_context_expired', 'efb_identity_changed', 'efb_form_missing', 'efb_form_invalid'];
  const TICKET_FINAL = ['efb_ticket_denied', 'efb_ticket_invalid', 'efb_context_expired', 'efb_identity_changed'];
  const REUSE_MAX = 3600000; // A context prepared on this page within the hour is still signed and live.
  function timeout(ms) {
    if (typeof AbortSignal !== 'undefined' && AbortSignal.timeout) return AbortSignal.timeout(ms);
    if (typeof AbortController === 'undefined' || typeof setTimeout === 'undefined') return undefined;
    const controller = new AbortController(); setTimeout(() => controller.abort(), ms); return controller.signal;
  }
  function uuid() {
    if (!window.crypto || !window.crypto.getRandomValues) throw new Error('Secure browser randomness unavailable.');
    const bytes = window.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
    const h = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
    return h.slice(0,8)+'-'+h.slice(8,12)+'-'+h.slice(12,16)+'-'+h.slice(16,20)+'-'+h.slice(20);
  }
  function share(fid, value) {
    try { if (typeof efb_var !== 'undefined') { efb_var.nonce=value.nonce; if (!fid) efb_var.sid=value.sid; } } catch (e) {}
    try { if (typeof _efb_core_nonce_ !== 'undefined') _efb_core_nonce_=value.nonce; } catch (e) {}
    try { if (typeof valj_efb_new !== 'undefined') { const f=valj_efb_new.find(f=>Number(f.id)===fid); if (f) f.sid=value.sid; } } catch (e) {}
    try { if (window.EFBHumanShield) window.EFBHumanShield.wpRestNonce=value.nonce; } catch (e) {}
  }
  function legacy(fid, context) {
    if (context.value && Date.now()-context.issued < REUSE_MAX) return context.value;
    let nonce='', sid='';
    try { if (typeof efb_var !== 'undefined' && efb_var.nonce) nonce=String(efb_var.nonce); } catch (e) {}
    try { if (!nonce && typeof _efb_core_nonce_ !== 'undefined' && _efb_core_nonce_) nonce=String(_efb_core_nonce_); } catch (e) {}
    try { if (fid && typeof valj_efb_new !== 'undefined') { const f=valj_efb_new.find(f=>Number(f.id)===fid); if (f && f.sid) sid=String(f.sid); } } catch (e) {}
    try { if (!sid && typeof efb_var !== 'undefined' && efb_var.sid) sid=String(efb_var.sid); } catch (e) {}
    return {nonce, sid, legacy:true};
  }
  async function prepare(fid, force) {
    fid=Number(fid)||0;
    let context=contexts.get(fid);
    if (!context) { context={}; contexts.set(fid,context); }
    if (context.promise) return context.promise;
    if (!force && context.value && Date.now()-context.time < 240000) return context.value;
    // After a failure, focus and submit use the fallback at once instead of waiting on the route again.
    if (!force && context.retry && Date.now() < context.retry) return legacy(fid,context);
    context.promise=(async () => {
      let response, value;
      try {
        response=await fetch(efb_request_health.rest_url+'forms/session/prepare', {
          method:'POST', credentials:'same-origin', cache:'no-store',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({form_id:fid,runtime_token:context.value ? context.value.runtime_token : ''}),
          signal:timeout(12000)
        });
        value=await response.json();
      } catch (error) {}
      if (response && response.ok && value && value.nonce && value.sid && value.runtime_token) {
        context.value=value; context.time=context.issued=Date.now(); context.retry=0; share(fid,value); return value;
      }
      const status=response ? response.status : 0;
      window.EFBRequestHealth.report('prepare',status>=400 ? status : 0);
      const code=value && typeof value.code==='string' ? value.code : '';
      if (FINAL.includes(code)) {
        const error=new Error(value.message || 'The form could not be prepared. Your input has been kept.');
        error.status=status; error.code=code; throw error;
      }
      context.retry=Date.now()+(status===429 ? 60000 : 15000);
      return legacy(fid,context);
    })().finally(()=>{context.promise=null;});
    return context.promise;
  }
  window.EFBRuntime={
    prepare,
    rememberTicket(value) { if (value.response_access_token) tickets.set(Number(value.id),{proof:value.response_access_token,time:0}); },
    async prepareTicket(id,force) {
      const ticket=tickets.get(Number(id));
      if (!ticket) return null; // Older server-rendered viewer must reopen through the existing lookup.
      if (ticket.promise) return ticket.promise;
      if (!force && ticket.value && Date.now()-ticket.time<240000) return ticket.value;
      ticket.promise=(async()=>{
        const runtime=await prepare(0);
        let response, value;
        try {
          response=await fetch(efb_request_health.rest_url+'forms/response/session',{
            method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-WP-Nonce':runtime.nonce},
            body:JSON.stringify({id:Number(id),response_access_token:ticket.proof}),
            signal:timeout(12000)
          });
          value=await response.json();
        } catch (error) {}
        if (response && response.ok && value && value.response_upload_token) { ticket.value=value; ticket.time=Date.now(); return value; }
        window.EFBRequestHealth.report('upload',response && response.status>=400 ? response.status : 0);
        if (value && TICKET_FINAL.includes(value.code)) throw new Error(value.message || 'The conversation could not be verified. Your draft has been kept.');
        return null; // Keep the upload token the tracker lookup issued; the server still checks it.
      })().finally(()=>{ticket.promise=null;});
      return ticket.promise;
    },
    async resolve(data,fid,kind) {
      if (!data.runtime_token || !data.request_id) return null;
      try {
        const response=await fetch(efb_request_health.rest_url+'forms/operation/status',{
          method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json'},
          body:JSON.stringify({kind,form_id:fid,request_id:data.request_id,runtime_token:data.runtime_token}),
          signal:timeout(8000)
        });
        const value=await response.json();
        return response.ok && value.state==='done' ? {success:true,data:value.result} : null;
      } catch(error) { return null; }
    },
    async decorate(data,fid,kind) {
      const value=await prepare(fid);
      if (!value.runtime_token) {
        // Legacy request: without runtime fields the server handles it exactly as 4.3.0 did.
        delete data.runtime_token; delete data.request_id;
        if (!data.sid && value.sid) data.sid=value.sid;
        return typeof data.nonce==='string' && data.nonce ? Object.assign({},value,{nonce:data.nonce}) : value;
      }
      data.sid=value.sid; data.runtime_token=value.runtime_token;
      const canonical=Object.assign({},data);
      ['sid','runtime_token','request_id','valid','nonce'].forEach(k=>delete canonical[k]);
      const fingerprint=JSON.stringify(canonical), key=kind+':'+fid;
      let intent=intents.get(key);
      if (!intent || intent.fingerprint!==fingerprint) { intent={fingerprint,id:uuid()}; intents.set(key,intent); }
      data.request_id=intent.id;
      return value;
    },
    complete(fid,kind) { intents.delete(kind+':'+fid); },
    invalidate() { contexts.forEach(context=>{context.time=0;}); tickets.forEach(ticket=>{ticket.time=0;}); }
  };
  document.addEventListener('focusin',event=>{
    const target=event.target && event.target.closest && event.target.closest('#body_efb,[id^="body_efb_"],[data-formid]');
    if (!target) return;
    const fid=Number(target.dataset.formid || String(target.id||'').replace('body_efb_',''))||0;
    prepare(fid).catch(()=>{});
  },true);
  document.addEventListener('visibilitychange',()=>{if (!document.hidden) window.EFBRuntime.invalidate();});
}());
