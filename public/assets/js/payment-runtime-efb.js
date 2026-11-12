/**
 * PAY00 §7: the payment field on the public page (window.EFBPay).
 *
 *  - live summary and total (efbPayCoreEfb.quote, display only), donation chips / other amount,
 *    payment method choice;
 *  - the Pay button sends the form through its normal Send flow; core-efb.js hands the request to
 *    EFBPay.submit(), which posts it to payments/create (the server validates every field, computes
 *    the amount and stores the entry as "pending payment") and follows next: redirect to the
 *    provider, or an adapter of the gateway add-on for embedded / QR flows (EFBPay.adapters[slug]);
 *  - back from the provider (?efb_pay=&efb_ps=), it asks payments/status (never trusts the URL) and
 *    shows the result, polling while the payment is pending.
 *
 * A result in the browser never records a payment: only the server's provider check does.
 */
(function (root) {
   'use strict';
   if (!root || !root.document) return;
   var V = root.efb_pay_vars || { rest: '', text: {}, gateways: {} };
   var C = function () { return root.efbPayCoreEfb; };
   var T = function (k) { return (V.text && V.text[k]) || ''; };
   var PENDING = {};
   var adapters = {};

   var structure = function (fid) {
      try {
         if (typeof valj_efb_new !== 'undefined' && Array.isArray(valj_efb_new)) {
            var f = valj_efb_new.find(function (x) { return Number(x.id) === Number(fid); });
            if (f) return f.form_structer;
         }
      } catch (e) { /* not ready */ }
      return null;
   };
   var sendRows = function (fid) {
      try { return (sendBack_emsFormBuilder_pub || []).filter(function (x) { return x && Number(x.form_id) === Number(fid); }); } catch (e) { return []; }
   };
   var uuid = function () {
      if (root.crypto && typeof root.crypto.randomUUID === 'function') return root.crypto.randomUUID();
      var b = new Uint8Array(16);
      (root.crypto || root.msCrypto).getRandomValues(b);
      b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
      var h = Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
      return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
   };
   var fieldRow = function (el) {
      var s = structure(el.getAttribute('data-formid'));
      return s ? s.find(function (r) { return r && r.id_ === el.getAttribute('data-pf'); }) : null;
   };
   var gw = function (el) { return (V.gateways || {})[el.getAttribute('data-gw')] || {}; };
   var fmt = function (el, minor, cur) { return C().format(minor, cur, V.locale, el.getAttribute('data-digits') === 'locale' ? 'locale' : 'latn'); };
   var status = function (el, msg, kind) {
      var s = el.querySelector('[data-pf-status]');
      if (!s) return;
      s.className = 'efb efb-pf-status' + (kind ? ' efb-pf-status-' + kind : '');
      s.textContent = msg || '';
   };

   /* ---- live summary -------------------------------------------------- */

   function refresh(el) {
      var s = structure(el.getAttribute('data-formid'));
      if (!s || !C()) return null;
      var g = gw(el);
      var q = C().quote(s, sendRows(el.getAttribute('data-formid')), { field_id: el.getAttribute('data-pf'), supports: { min_minor: g.min_minor || {}, max_minor: g.max_minor || {}, currencies: g.currencies || [] } });
      el._q = q;
      var cur = q.currency || String((s[0] && s[0].currency) || '').toUpperCase();
      var total = q.ok ? fmt(el, q.total_minor, q.currency) : '';
      el.querySelectorAll('[data-pf-total],[data-pf-amount]').forEach(function (n) { n.textContent = total; });
      var ul = el.querySelector('[data-pf-lines]');
      if (ul) {
         ul.innerHTML = '';
         (q.ok ? q.items : []).forEach(function (it) {
            var li = document.createElement('li');
            li.className = 'efb efb-pf-line';
            var a = document.createElement('span'); a.textContent = it.label;
            var b = document.createElement('bdi'); b.textContent = fmt(el, it.total_minor, q.currency);
            li.appendChild(a); li.appendChild(b); ul.appendChild(li);
         });
      }
      el.querySelectorAll('[data-pf-preset]').forEach(function (b) {
         var m = C().fromDecimal(b.getAttribute('data-pf-preset'), cur);
         var bdi = b.querySelector('bdi');
         if (bdi && m !== null && cur) bdi.textContent = fmt(el, m, cur);
      });
      return q;
   }

   /* ---- donation and methods ------------------------------------------ */

   function setDonation(el, value) {
      var row = fieldRow(el);
      if (!row || typeof fun_sendBack_emsFormBuilder !== 'function') return;
      var v = C().latinDigits(String(value || '')).trim();
      var fid = el.getAttribute('data-formid');
      try {
         var i = sendBack_emsFormBuilder_pub.findIndex(function (x) { return x && x.id_ === row.id_ && Number(x.form_id) === Number(fid); });
         if (i !== -1) sendBack_emsFormBuilder_pub.splice(i, 1);
      } catch (e) { /* none */ }
      if (v !== '') fun_sendBack_emsFormBuilder({ id_: row.id_, name: row.name, id_ob: row.id_, amount: row.amount, type: row.type, value: v, session: '', form_id: Number(fid) });
      refresh(el);
   }

   function select(group, btn, attr) {
      group.querySelectorAll('[role="radio"]').forEach(function (b) {
         var on = b === btn;
         b.setAttribute('aria-checked', on ? 'true' : 'false');
         if (attr) b.setAttribute('tabindex', on ? '0' : '-1');
      });
   }

   function setup(el) {
      if (el._efbPay) return;
      el._efbPay = true;
      var fid = el.getAttribute('data-formid');
      var body = document.getElementById('body_efb_' + fid);
      // The Pay button replaces the form's own Send button on the step that holds the field.
      var s = structure(fid);
      var wrap = el.closest('[data-step]');
      var steps = s && s[0] ? Number(s[0].steps || 1) : 1;
      if (body && (!wrap || Number(wrap.getAttribute('data-step')) >= steps - 1)) body.classList.add('efb-pf-form');
      var amount = el.querySelector('[data-pf-el="amount"]');
      if (amount) {
         var def = amount.getAttribute('data-pf-default');
         if (def) setDonation(el, def);
         amount.addEventListener('click', function (e) {
            var b = e.target.closest('[data-pf-preset]');
            if (!b) return;
            select(amount, b);
            var other = amount.querySelector('[data-pf-other]');
            if (other) other.value = '';
            setDonation(el, b.getAttribute('data-pf-preset'));
         });
         var other = amount.querySelector('[data-pf-other]');
         if (other) other.addEventListener('input', function () {
            amount.querySelectorAll('[data-pf-preset]').forEach(function (b) { b.setAttribute('aria-checked', 'false'); });
            setDonation(el, other.value);
         });
      }
      var methods = el.querySelector('.efb-pf-method-list');
      if (methods) {
         var first = methods.querySelector('[data-pf-method]');
         if (first) el._method = first.getAttribute('data-pf-method');
         methods.addEventListener('click', function (e) {
            var b = e.target.closest('[data-pf-method]');
            if (!b) return;
            select(methods, b, true);
            el._method = b.getAttribute('data-pf-method');
         });
         methods.addEventListener('keydown', function (e) {
            if (['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp'].indexOf(e.key) === -1) return;
            var list = Array.prototype.slice.call(methods.querySelectorAll('[data-pf-method]'));
            var i = list.indexOf(document.activeElement);
            if (i === -1) return;
            e.preventDefault();
            var rtl = getComputedStyle(methods).direction === 'rtl';
            var fwd = e.key === 'ArrowDown' || (e.key === 'ArrowRight') !== rtl;
            var n = list[(i + (fwd ? 1 : -1) + list.length) % list.length];
            n.focus(); n.click();
         });
      }
      var sel = el.querySelector('[data-pf-method-select]');
      if (sel) { el._method = sel.value; sel.addEventListener('change', function () { el._method = sel.value; }); }
      var payBtn = el.querySelector('[data-pf-pay]');
      if (payBtn) payBtn.addEventListener('click', function () { pay(el); });
      refresh(el);
   }

   function pay(el) {
      var q = refresh(el);
      // Only what the visitor can fix here stops the click; the amount itself is the server's
      // (a price a logic rule computes may be unknown to this page and is computed there).
      if (q && !q.ok && q.error === 'pwyw_out_of_range') {
         status(el, T('invalidAmt'), 'error');
         var f = el.querySelector('[data-pf-other]') || el.querySelector('[data-pf-preset]');
         if (f) f.focus();
         return;
      }
      if (q && !q.ok && (q.error === 'below_min' || q.error === 'above_max')) {
         status(el, (q.error === 'below_min' ? T('min') : T('max')).replace('%s', fmt(el, q.limit_minor, q.currency)) || T('invalidAmt'), 'error');
         return;
      }
      status(el, '', '');
      var fid = el.getAttribute('data-formid');
      PENDING[fid] = el;
      var body = document.getElementById('body_efb_' + fid);
      var send = body ? body.querySelector('#btn_send_efb') : null;
      if (send) { send.click(); return; }
      var next = body ? body.querySelector('#next_efb') : null;
      if (next) next.click();
   }

   /* ---- create --------------------------------------------------------- */

   async function submit(data, fid) {
      var el = PENDING[fid] || document.querySelector('.efb-pf[data-formid="' + fid + '"]');
      if (!el) return false;
      if (!el._attempt) el._attempt = uuid();
      var q = refresh(el);
      var body = Object.assign({}, data, {
         pay: {
            field_id: el.getAttribute('data-pf'),
            attempt_key: el._attempt,
            method: el._method || '',
            expected_minor: q && q.ok ? q.total_minor : null,
            page_url: location.href.split('#')[0],
            locale: V.locale || ''
         }
      });
      var btn = el.querySelector('[data-pf-pay]');
      if (btn) { btn.setAttribute('aria-busy', 'true'); btn.disabled = true; }
      var fail = function (m, extra) {
         if (btn) { btn.removeAttribute('aria-busy'); btn.disabled = false; }
         status(el, m, 'error');
         var res = { success: true, data: Object.assign({ success: false, m: m }, extra || {}) };
         if (typeof response_fill_form_efb === 'function') response_fill_form_efb(res, fid);
      };
      var json;
      try {
         var opt = { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) };
         var resp = typeof efb_fetch_public_request_efb === 'function'
            ? await efb_fetch_public_request_efb(V.rest + 'create', opt, 'payment', data.sid, fid)
            : await fetch(V.rest + 'create', opt);
         json = await resp.json();
      } catch (e) {
         fail((e && e.message) || T('noScript'));
         return false;
      }
      var d = json && json.data ? json.data : {};
      if (!json || !json.success || !d.success || !d.pay) {
         if (d.code !== 'in_progress') el._attempt = null;
         if (d.code === 'quote_changed' && d.total_minor) {
            el.querySelectorAll('[data-pf-total],[data-pf-amount]').forEach(function (n) { n.textContent = fmt(el, d.total_minor, d.currency); });
         }
         fail(d.m || (json && json.data && json.data.m) || T('noScript'), d.field_id ? { field_id: d.field_id } : {});
         return false;
      }
      var p = d.pay;
      var banner = el.querySelector('[data-pf-el="test"]');
      if (banner) banner.hidden = !p.test_mode;
      try { sessionStorage.setItem('efb_pay_' + fid, p.uid); } catch (e) { /* private mode */ }
      var next = p.next || { type: 'none' };
      if (next.type === 'redirect' && next.url) {
         status(el, T('redirect'), 'info');
         var fs = document.getElementById('body_efb_' + fid);
         var fin = fs && typeof efb_get_final_step_efb === 'function' ? efb_get_final_step_efb(fid, fs) : null;
         if (fin) fin.innerHTML = '<div class="efb text-center my-3" role="status"><div class="efb spinner-border" aria-hidden="true"></div><p class="efb mt-2">' + C().esc(T('redirect')) + '</p></div>';
         location.assign(next.url);
         return true;
      }
      var ad = adapters[p.gateway];
      if (ad && typeof ad.mount === 'function') {
         ad.mount({ element: el, slot: el.querySelector('[data-pf-slot="fields"]'), payment: p, next: next, poll: function () { return watch(el, p, fid); } });
         return true;
      }
      return watch(el, p, fid);
   }

   /* ---- status / return ----------------------------------------------- */

   async function getStatus(uid, sig) {
      var r = await fetch(V.rest + 'status?p=' + encodeURIComponent(uid) + '&s=' + encodeURIComponent(sig), { cache: 'no-store', credentials: 'same-origin' });
      if (r.status === 429) return { state: 'pending' };
      return r.json();
   }

   function done(fid, st) {
      var body = document.getElementById('body_efb_' + fid);
      document.title = (st.state === 'paid' ? T('titleOk') : T('titleFail')) + ' — ' + document.title;
      if (st.state === 'paid') {
         if (st.redirect) { location.href = st.redirect; return; }
         if (body) {
            body.querySelectorAll('fieldset[data-step]').forEach(function (f) { f.classList.add('d-none'); });
            body.classList.add('efb-pf-done');
            var fin = typeof efb_get_final_step_efb === 'function' ? efb_get_final_step_efb(fid, body) : null;
            if (fin) fin.classList.remove('d-none');
         }
         if (typeof response_fill_form_efb === 'function') {
            try { response_fill_form_efb({ success: true, data: { success: true, ID: fid, track: st.track || '', nonce: '' } }, fid); } catch (e) { /* no final step */ }
         }
         var msg = document.querySelector('#body_efb_' + fid + ' .efb-pf-status') || null;
         if (msg) msg.textContent = st.message || T('success');
         var live = document.getElementById('efb-pf-live-' + fid);
         if (live) live.textContent = st.message || T('success');
      }
   }

   async function watch(el, p, fid) {
      var delay = 3000, until = Date.now() + 15 * 60 * 1000;
      status(el, T('checking'), 'info');
      while (Date.now() < until) {
         var st;
         try { st = await getStatus(p.uid, p.sig); } catch (e) { st = { state: 'pending' }; }
         if (st.state === 'paid' || st.state === 'failed') { show(el, fid, st); return true; }
         status(el, T('pending'), 'info');
         await new Promise(function (r) { setTimeout(r, delay); });
         delay = Math.min(15000, Math.round(delay * 1.5));
      }
      return false;
   }

   function show(el, fid, st) {
      if (st.state === 'paid') {
         if (el) status(el, st.message || T('success'), 'ok');
         done(fid, st);
         return;
      }
      if (!el) return;
      var s = el.querySelector('[data-pf-status]');
      status(el, st.message || T('fail'), 'error');
      if (st.can_retry && s) {
         var b = document.createElement('button');
         b.type = 'button';
         b.className = 'efb btn btn-outline-secondary btn-sm ms-2 efb-pf-retry';
         b.textContent = T('tryAgain');
         b.addEventListener('click', function () { el._attempt = null; status(el, '', ''); var pb = el.querySelector('[data-pf-pay]'); if (pb) { pb.disabled = false; pb.focus(); } });
         s.appendChild(b);
      }
      var btn = el.querySelector('[data-pf-pay]');
      if (btn) { btn.removeAttribute('aria-busy'); btn.disabled = false; }
      s && s.focus();
   }

   async function returned() {
      var qs = new URLSearchParams(location.search);
      var uid = qs.get('efb_pay'), sig = qs.get('efb_ps');
      if (!uid || !sig || !/^[0-9A-Za-z]{22}$/.test(uid) || !/^[0-9a-f]{32}$/.test(sig)) return;
      try {
         qs.delete('efb_pay'); qs.delete('efb_ps');
         var clean = location.pathname + (qs.toString() ? '?' + qs.toString() : '') + location.hash;
         history.replaceState(history.state, '', clean);
      } catch (e) { /* old browser */ }
      var st;
      try { st = await getStatus(uid, sig); } catch (e) { return; }
      if (!st || !st.form_id) return;
      var fid = String(st.form_id);
      var el = document.querySelector('.efb-pf[data-formid="' + fid + '"]');
      if (el) {
         var banner = el.querySelector('[data-pf-el="test"]');
         if (banner) banner.hidden = !st.test_mode;
         el.scrollIntoView({ block: 'center' });
      }
      if (st.state === 'pending') {
         if (el) watch(el, { uid: uid, sig: sig }, fid);
         return;
      }
      show(el, fid, st);
   }

   function init() {
      document.querySelectorAll('.efb-pf[data-pf][data-formid]').forEach(setup);
      returned();
   }

   var timer = null;
   var schedule = function () {
      if (timer) clearTimeout(timer);
      timer = setTimeout(function () { document.querySelectorAll('.efb-pf[data-pf][data-formid]').forEach(function (el) { if (el._efbPay) refresh(el); }); }, 60);
   };
   ['change', 'input', 'click', 'keyup'].forEach(function (t) { document.addEventListener(t, schedule, true); });

   root.EFBPay = {
      version: 1,
      isGateway: function (slug) { return !!(V.gateways && Object.prototype.hasOwnProperty.call(V.gateways, slug)); },
      submit: submit,
      refresh: function (fid) { document.querySelectorAll('.efb-pf[data-formid="' + fid + '"]').forEach(refresh); },
      /** A gateway add-on's browser adapter: EFBPay.registerAdapter('<slug>', {prepare(ctx){…}, mount(ctx){…}}). */
      registerAdapter: function (slug, a) { if (typeof slug === 'string' && a) adapters[slug] = a; },
      poll: function (uid, sig, fid) { var el = document.querySelector('.efb-pf[data-formid="' + fid + '"]'); return watch(el, { uid: uid, sig: sig }, fid); }
   };

   if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 0); });
   else setTimeout(init, 0);
   // Forms drawn later (popups, page builders): set up when they appear.
   var tries = 0;
   var again = setInterval(function () { tries++; document.querySelectorAll('.efb-pf[data-pf][data-formid]').forEach(setup); if (tries > 20) clearInterval(again); }, 500);
})(typeof window !== 'undefined' ? window : null);
