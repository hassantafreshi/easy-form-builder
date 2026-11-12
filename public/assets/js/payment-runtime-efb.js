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
 *    shows the result, polling while the payment is pending (until the server's poll_until: the
 *    gateway's pending_ttl());
 *  - an adapter may have prepare(ctx): it runs after the form is valid and before payments/create
 *    and returns {token, data} (e.g. the card token of the provider's hosted fields); the token is
 *    sent once as pay.client_token and is never kept by EFB;
 *  - "Try again" / an adapter's EFBPay.retry(): a new attempt for the SAME pending entry
 *    (payments/retry), without sending the form again.
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
            markRequired(el);
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
      if (sel) { el._method = sel.value; sel.addEventListener('change', function () { el._method = sel.value; markRequired(el); }); }
      var payBtn = el.querySelector('[data-pf-pay]');
      if (payBtn) payBtn.addEventListener('click', function () { pay(el); });
      markRequired(el);
      refresh(el);
   }

   /** The chosen method's own limits (gateway methods extras): a message naming the method, or ''. */
   function methodLimit(el, q) {
      var lim = ((gw(el).method_limits || {})[el._method || '']) || null;
      if (!lim || !q || !q.ok) return '';
      var cur = q.currency, min = (lim.min_minor || {})[cur], max = (lim.max_minor || {})[cur];
      if (min != null && q.total_minor < Number(min)) return (T('methodMin') || '%1$s: %2$s').replace('%1$s', lim.label || '').replace('%2$s', fmt(el, Number(min), cur));
      if (max != null && q.total_minor > Number(max)) return (T('methodMax') || '%1$s: %2$s').replace('%1$s', lim.label || '').replace('%2$s', fmt(el, Number(max), cur));
      return '';
   }

   /* ---- payer fields the gateway needs ---------------------------------- */

   /** The chosen method (or the only one) and its label. */
   function methodOf(el) {
      var tiles = el.querySelectorAll('[data-pf-method]');
      var sel = el.querySelector('[data-pf-method-select]');
      var method = el._method || (tiles.length === 1 ? tiles[0].getAttribute('data-pf-method') : (sel ? sel.value : ''));
      var label = '';
      tiles.forEach(function (b) { if (b.getAttribute('data-pf-method') === method) label = b.textContent.trim(); });
      if (!label && sel && sel.selectedIndex >= 0 && sel.options[sel.selectedIndex]) label = sel.options[sel.selectedIndex].textContent.trim();
      return { id: method, label: label };
   }

   /** The form field an option points to (the payer e-mail falls back to the confirmation e-mail field). */
   function targetOf(row, d, s) {
      var t = typeof row[d.key] === 'string' ? row[d.key] : '';
      if (!t && d.key === 'pgw_map_email' && C().confirmationEmailField) t = C().confirmationEmailField(s);
      return t;
   }

   function inputOf(el, id) {
      var body = document.getElementById('body_efb_' + el.getAttribute('data-formid')) || document;
      return body.querySelector('[data-id="' + id + '-el"]') || body.querySelector('[id="' + id + '_"]') || body.querySelector('[id="' + id + '_options"]');
   }

   /**
    * The payer values the gateway needs (required, or required_for the chosen method), checked before
    * payments/create (the server checks again): empty, or in the wrong format (email, phone,
    * phone_e164, country and its countries). The message (the gateway's own, else core's) and the field.
    */
   function payerCheck(el) {
      var g = gw(el), row = fieldRow(el);
      if (!row || !Array.isArray(g.requires) || !g.requires.length) return null;
      var fid = el.getAttribute('data-formid');
      var m = methodOf(el);
      var s = structure(fid) || [];
      for (var i = 0; i < g.requires.length; i++) {
         var d = g.requires[i];
         var needed = d.required || (m.id !== '' && Array.isArray(d.required_for) && d.required_for.indexOf(m.id) !== -1);
         var target = targetOf(row, d, s);
         // Only what this payment needs is checked (the server does the same).
         if (!target || !needed) continue;
         var got = sendRows(fid).find(function (x) { return x && String(x.id_) === target && x.value != null && String(x.value).trim() !== ''; });
         var f = s.find(function (x) { return x && String(x.id_) === target; });
         var name = (f && f.name) || d.label;
         if (!got) {
            var msg = d.visitor_message || ((g.messages || {}).payer_missing || '').split('%field%').join(name).split('%method%').join(m.label) ||
               (m.id && d.required_for && d.required_for.length ? (T('payerMissingFor') || '%1$s').replace('%1$s', name).replace('%2$s', m.label || m.id) : (T('payerMissing') || '%s').replace('%s', name));
            return { code: 'payer_missing', message: msg, field_id: target };
         }
         var val = String(got.value).split('@efb@dq#').join('"');
         var problem = C().payerProblem ? C().payerProblem(d, val, d.format === 'country' ? C().countryIso(s, target, val) : '') : '';
         if (problem) {
            var key = problem === 'country' ? 'payerCountryNo' : ({ email: 'payerEmail', phone: 'payerPhone', phone_e164: 'payerPhoneIntl', country: 'payerCountry' })[d.format];
            var own = d.invalid_message || ((g.messages || {}).payer_invalid || '').split('%field%').join(name).split('%method%').join(m.label);
            return { code: 'payer_invalid', message: own || (T(key) || '%s').replace('%s', name), field_id: target };
         }
      }
      return null;
   }

   /**
    * A message at a form field (its own message slot, as the form's own checks use): role alert,
    * the input aria-invalid and aria-describedby the message, focus. Cleared when the visitor edits it.
    */
   function fieldMessage(el, id, msg) {
      var input = inputOf(el, id);
      var slot = document.getElementById(id + '_-message');
      if (slot) {
         slot.textContent = msg;
         slot.setAttribute('role', 'alert');
         slot.setAttribute('aria-live', 'assertive');
         slot.classList.add('efb-pf-field-msg');
         slot.style.display = 'block';
      }
      if (input) {
         input.setAttribute('aria-invalid', 'true');
         if (slot) {
            var ids = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (ids.indexOf(slot.id) === -1) { ids.push(slot.id); input.setAttribute('aria-describedby', ids.join(' ')); }
         }
         input.classList.add('border-danger');
         var clear = function () {
            input.removeAttribute('aria-invalid');
            input.classList.remove('border-danger');
            if (slot && slot.classList.contains('efb-pf-field-msg')) { slot.textContent = ''; slot.style.display = 'none'; slot.classList.remove('efb-pf-field-msg'); }
            input.removeEventListener('input', clear); input.removeEventListener('change', clear);
         };
         input.addEventListener('input', clear); input.addEventListener('change', clear);
         try { input.focus({ preventScroll: false }); } catch (e) { input.focus(); }
      }
      return !!(slot || input);
   }

   /** The fields the payment needs are marked required on the page (aria-required and the star), for the chosen method too. */
   function markRequired(el) {
      var g = gw(el), row = fieldRow(el);
      if (!row || !Array.isArray(g.requires)) return;
      var s = structure(el.getAttribute('data-formid')) || [];
      var m = methodOf(el);
      g.requires.forEach(function (d) {
         var target = targetOf(row, d, s);
         if (!target) return;
         var f = s.find(function (x) { return x && String(x.id_) === target; });
         if (f && (f.required === true || f.required === 1 || f.required === '1')) return;
         var on = d.required || (m.id !== '' && Array.isArray(d.required_for) && d.required_for.indexOf(m.id) !== -1);
         var input = inputOf(el, target);
         if (input) input.setAttribute('aria-required', on ? 'true' : 'false');
         var star = document.getElementById(target + '_req');
         if (star) { star.textContent = on ? '*' : ''; star.title = on ? T('neededForPay') : ''; star.classList.toggle('efb-pf-req', on); }
      });
   }

   function pay(el) {
      if (el._retryable && el._last) { retry(el); return; }
      var q = refresh(el);
      var ml = methodLimit(el, q);
      if (ml) { status(el, ml, 'error'); return; }
      var pm = payerCheck(el);
      if (pm) {
         // At the field (and once in the field's status line): no payments/create request is sent.
         status(el, pm.message, 'error');
         fieldMessage(el, pm.field_id, pm.message);
         return;
      }
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

   /** A short-lived browser token: printable ASCII, at most 4096 characters (the server checks again). */
   var okToken = function (t) { return typeof t === 'string' && t.length <= 4096 && /^[\x21-\x7E]+$/.test(t); };

   /**
    * The adapter's prepare(ctx) step, if it has one: {token, data} | null (nothing to add) | false
    * (the adapter showed its own message and the payment stops here). ctx: element, slot, formId,
    * gateway, method, quote (display only), status(msg, kind).
    */
   async function prepareFor(el, q) {
      var ad = adapters[el.getAttribute('data-gw')];
      if (!ad || typeof ad.prepare !== 'function') return null;
      var out;
      try {
         out = await Promise.race([
            Promise.resolve(ad.prepare({ element: el, slot: el.querySelector('[data-pf-slot="fields"]'), formId: el.getAttribute('data-formid'), gateway: el.getAttribute('data-gw'), method: el._method || '', quote: q && q.ok ? { total_minor: q.total_minor, currency: q.currency } : null, status: function (m, k) { status(el, m, k || 'info'); } })),
            new Promise(function (r) { setTimeout(function () { r(false); }, 300000); })
         ]);
      } catch (e) {
         throw new Error((e && e.message) || T('noScript'));
      }
      if (out === false) return false;
      if (!out || typeof out !== 'object') return null;
      var res = {};
      if (out.token != null) {
         if (!okToken(out.token)) throw new Error(T('noScript'));
         res.client_token = out.token;
      }
      if (out.data && typeof out.data === 'object' && !Array.isArray(out.data)) {
         res.client_data = {};
         Object.keys(out.data).slice(0, 20).forEach(function (k) { var v = out.data[k]; if (/^[a-z0-9_]{1,40}$/.test(k) && (typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean')) res.client_data[k] = typeof v === 'string' ? v.slice(0, 200) : v; });
      }
      return res;
   }

   async function post(route, body, sid, fid) {
      var opt = { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) };
      var resp;
      try {
         resp = typeof efb_fetch_public_request_efb === 'function'
            ? await efb_fetch_public_request_efb(V.rest + route, opt, 'payment', sid, fid)
            : await fetch(V.rest + route, opt);
      } catch (e) {
         var n = new Error(T('network')); n.efbNetwork = true; throw n;
      }
      try { return await resp.json(); } catch (e) {
         var x = new Error(T('noScript')); throw x;
      }
   }

   async function submit(data, fid) {
      var el = PENDING[fid] || document.querySelector('.efb-pf[data-formid="' + fid + '"]');
      if (!el) return false;
      if (!el._attempt) el._attempt = uuid();
      el._sid = data.sid;
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
         var prep = await prepareFor(el, q);
         if (prep === false) { fail(''); return false; }
         if (prep) Object.assign(body.pay, prep);
         json = await post('create', body, data.sid, fid);
         if (body.pay.client_token) body.pay.client_token = ''; // used once
      } catch (e) {
         // A thrown adapter step keeps its own message; a request that never reached the site says so.
         fail((e && e.efbNetwork) ? T('network') : ((e && e.message) || T('noScript')));
         return false;
      }
      var d = json && json.data ? json.data : {};
      if (!json || !json.success || !d.success || !d.pay) {
         if (d.code !== 'in_progress') el._attempt = null;
         if (d.code === 'quote_changed' && d.total_minor) {
            el.querySelectorAll('[data-pf-total],[data-pf-amount]').forEach(function (n) { n.textContent = fmt(el, d.total_minor, d.currency); });
         }
         var m = d.m || (json && json.data && json.data.m) || (d.code === 'efb_rate_limited' ? T('rateLimited') : T('noScript'));
         fail(m, d.field_id ? { field_id: d.field_id } : {});
         // The server's payer check (or the provider refusing a payer value): the message at that field too.
         if (d.field_id && (d.code === 'payer_missing' || d.code === 'payer_invalid')) fieldMessage(el, d.field_id, m);
         return false;
      }
      return follow(el, d.pay, fid);
   }

   /**
    * "Try again" / "Get a new QR code": a new attempt for the same pending entry (payments/retry),
    * with a new attempt key; the form is not sent again. Falls back to nothing when the page does
    * not know the previous attempt (the visitor presses Pay and the form is sent as usual).
    */
   async function retry(el) {
      if (!el || !el._last) return false;
      var fid = el.getAttribute('data-formid');
      el._retryable = false;
      el._retrying = true;
      el._attempt = uuid();
      var btn = el.querySelector('[data-pf-pay]');
      if (btn) { btn.setAttribute('aria-busy', 'true'); btn.disabled = true; }
      status(el, T('newAttempt'), 'info');
      var json;
      try {
         var body = { uid: el._last.uid, sig: el._last.sig, attempt_key: el._attempt, method: el._method || '', locale: V.locale || '' };
         var prep = await prepareFor(el, refresh(el));
         if (prep === false) { if (btn) { btn.removeAttribute('aria-busy'); btn.disabled = false; } el._retryable = true; return false; }
         if (prep) Object.assign(body, prep);
         json = await post('retry', body, el._sid || '', fid);
      } catch (e) {
         json = null;
         var netErr = !!(e && e.efbNetwork);
      }
      var d = json && json.data ? json.data : {};
      if (!json || !d.success || !d.pay) {
         if (btn) { btn.removeAttribute('aria-busy'); btn.disabled = false; }
         status(el, d.m || (netErr ? T('network') : T('noScript')), 'error');
         if (d.code !== 'retry_unavailable') el._retryable = true; else el._last = null;
         return false;
      }
      return follow(el, d.pay, fid);
   }

   /** What the server answered for a new attempt (create or retry): the provider's page, an adapter, or polling. */
   function follow(el, p, fid) {
      el._last = { uid: p.uid, sig: p.sig };
      el._pollUntil = p.poll_until || 0;
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
         ad.mount({ element: el, slot: el.querySelector('[data-pf-slot="fields"]'), payment: p, next: next, poll: function () { return watch(el, p, fid); }, retry: function () { return retry(el); } });
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

   /** Until when to keep asking: the server's poll_until (the gateway's pending_ttl()), else 15 minutes; at most 24 hours. */
   function untilOf(sec) {
      var now = Date.now();
      var u = Number(sec) > 0 ? Number(sec) * 1000 : now + 15 * 60 * 1000;
      return Math.min(now + 24 * 3600 * 1000, Math.max(now + 60 * 1000, u));
   }

   async function watch(el, p, fid) {
      var delay = 3000, until = untilOf(p.poll_until || (el && el._pollUntil));
      status(el, T('checking'), 'info');
      while (Date.now() < until) {
         var st;
         try { st = await getStatus(p.uid, p.sig); } catch (e) { st = { state: 'pending' }; }
         if (st.state === 'paid' || st.state === 'failed') { show(el, fid, st); return true; }
         if (st.poll_until) until = untilOf(st.poll_until);
         status(el, T('pending'), 'info');
         await new Promise(function (r) { setTimeout(r, delay); });
         delay = Math.min(15000, Math.round(delay * 1.5));
      }
      return false;
   }

   /** Polls without changing the status text (the visitor already sees what to do); stops when paid or failed. */
   async function watchQuiet(el, p, fid) {
      var delay = 4000, until = untilOf(p.poll_until);
      while (Date.now() < until && !el._retrying) {
         await new Promise(function (r) { setTimeout(r, delay); });
         if (el._retrying || (el._last && el._last.uid !== p.uid)) return;
         var st;
         try { st = await getStatus(p.uid, p.sig); } catch (e) { st = { state: 'pending' }; }
         if (st.state === 'paid') { show(el, fid, st); return; }
         if (st.state === 'failed') return;
         delay = Math.min(15000, Math.round(delay * 1.5));
      }
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
         // A new attempt for the same entry when the page knows the last one; else the form is sent again.
         el._retryable = !!el._last;
         var b = document.createElement('button');
         b.type = 'button';
         b.className = 'efb btn btn-outline-secondary btn-sm ms-2 efb-pf-retry';
         b.textContent = T('tryAgain');
         b.addEventListener('click', function () {
            if (el._retryable && el._last) { retry(el); return; }
            el._attempt = null; status(el, '', ''); var pb = el.querySelector('[data-pf-pay]'); if (pb) { pb.disabled = false; pb.focus(); }
         });
         s.appendChild(b);
      }
      var btn = el.querySelector('[data-pf-pay]');
      if (btn) { btn.removeAttribute('aria-busy'); btn.disabled = false; }
      s && s.focus();
   }

   async function returned() {
      var qs = new URLSearchParams(location.search);
      var uid = qs.get('efb_pay'), sig = qs.get('efb_ps'), cancelled = qs.get('efb_pc') === '1';
      if (!uid || !sig || !/^[0-9A-Za-z]{22}$/.test(uid) || !/^[0-9a-f]{32}$/.test(sig)) return;
      try {
         qs.delete('efb_pay'); qs.delete('efb_ps'); qs.delete('efb_pc');
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
         el._last = { uid: uid, sig: sig };
      }
      if (st.state === 'pending' && cancelled && el) {
         // Back through the provider's cancel link and still open there: say so and offer Try again
         // (a new attempt cancels the open one); the page keeps watching in case it is paid after all.
         show(el, fid, { state: 'failed', can_retry: true, message: T('returnedCancel') });
         watchQuiet(el, { uid: uid, sig: sig, poll_until: st.poll_until }, fid);
         return;
      }
      if (st.state === 'pending') {
         if (el) watch(el, { uid: uid, sig: sig, poll_until: st.poll_until }, fid);
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
      /** A gateway add-on's browser adapter: EFBPay.registerAdapter('<slug>', {prepare(ctx){…}, mount(ctx){…}}).
          prepare(ctx) (optional) runs before payments/create and resolves to {token, data}, null or false. */
      registerAdapter: function (slug, a) { if (typeof slug === 'string' && a) adapters[slug] = a; },
      poll: function (uid, sig, fid) { var el = document.querySelector('.efb-pf[data-formid="' + fid + '"]'); return watch(el, { uid: uid, sig: sig }, fid); },
      /** A new attempt for the same pending entry (a new QR, "Try again"): EFBPay.retry(fieldElement). */
      retry: function (el) { return retry(el && el.nodeType ? el : document.querySelector('.efb-pf[data-formid="' + el + '"]')); }
   };

   if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 0); });
   else setTimeout(init, 0);
   // Forms drawn later (popups, page builders): set up when they appear.
   var tries = 0;
   var again = setInterval(function () { tries++; document.querySelectorAll('.efb-pf[data-pf][data-formid]').forEach(setup); if (tries > 20) clearInterval(again); }, 500);
})(typeof window !== 'undefined' ? window : null);
