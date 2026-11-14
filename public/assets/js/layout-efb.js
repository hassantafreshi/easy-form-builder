/**
 * F10: layout and special fields on the public page (docs/feature-gaps/F10-layout-special-fields.fa.md).
 *
 *  - EFBLayoutCore (no DOM; also module.exports for the Node tests): the save rule of every F10 key
 *    (sanitizeKey, twin of \Emsfb\Layout::sanitize_key()), and the text of an answer on the review page
 *    (answerText: what the visitor typed, never a password, never markup).
 *  - EFBLayout (the page): the review page before sending (A).
 *
 * core-efb.js calls EFBLayout.reviewIntercept() where it would send the form, and EFBLayout.reviewReturn()
 * when Next is pressed on a step opened from the review page. Values are written with textContent only.
 */
(function (root) {
  'use strict';

  /* ================================================================== core (no DOM) */

  var FORM_KEYS = ['review_before_submit', 'review_hide_empty', 'review_title'];
  var FIELD_KEYS = [];
  var RUNTIME_KEYS = ['vis_view'];
  var LIMITS = { review_title: 120 };
  var DEFAULTS = { review_before_submit: '0', review_hide_empty: '1', review_title: '' };
  var LEGACY_PAY_TYPES = ['stripe', 'paypal', 'persiaPay', 'persiapay', 'zarinPal'];
  /** Rows that are never an answer on the review page (structure, secrets, hidden values). */
  var SKIP_TYPES = ['form', 'step', 'option', 'r_matrix', 'html', 'heading', 'link', 'steps', 'buttonNav', 'ttlprc', 'section',
    'password', 'pswRegister', 'pswLogin', 'hiddenField', 'captcha', 'stripe', 'paypal', 'persiaPay', 'persiapay', 'zarinPal', 'payment'];
  var SECRET_IDS = ['passwordRegisterEFB', 'repasswordRegisterEFB', 'passwordlogin'];
  var FILE_TYPES = ['file', 'dadfile', 'audio_recorder', 'video_recorder', 'screen_recorder'];
  var SIG_RE = /^data:image\/png;base64,[A-Za-z0-9+\/]+={0,2}$/;

  function str(v) { return v === null || v === undefined ? '' : String(v); }
  function on(v) { return v === true || v === 1 || v === '1' || v === 'true' || v === 'on'; }
  /** sanitize_text_field() as the save does it: tags, %XX octets and runs of white space. */
  function textField(v) { return str(v).replace(/<[^>]*>/g, '').replace(/%[a-f0-9]{2}/gi, '').replace(/[\r\n\t ]+/g, ' ').trim(); }
  function cut(s, n) { return Array.from(s).slice(0, n).join(''); }

  /** The stored value of an F10 key ('' = not set). Twin of \Emsfb\Layout::sanitize_key(). */
  function sanitizeKey(k, v) {
    if (RUNTIME_KEYS.indexOf(k) !== -1) return '';
    if (typeof v === 'boolean') v = v ? '1' : '0';
    if (v === null || v === undefined || typeof v === 'object') return '';
    v = String(v);
    switch (k) {
      case 'review_before_submit':
      case 'review_hide_empty':
        return on(v) ? '1' : '0';
      case 'review_title':
        return cut(textField(v), LIMITS.review_title);
    }
    return '';
  }

  function legacyPayment(rows) {
    return (rows || []).some(function (r, i) { return i > 0 && r && LEGACY_PAY_TYPES.indexOf(str(r.type)) !== -1; });
  }
  function reviewOn(rows) {
    if (!rows || !rows[0] || !on(rows[0].review_before_submit)) return false;
    var t = str(rows[0].type);
    if (t === 'login' || t === 'register') return false;
    return !legacyPayment(rows);
  }

  /** Stored tokens back to text: @efb@dq# = ", @n# = a new line. */
  function untoken(v) { return str(v).replace(/@efb@dq#/g, '"').replace(/@n#/g, '\n'); }
  /** The words of rich text (F10-C stores HTML): block ends and line breaks become new lines, every tag goes. */
  function plainOfHtml(html) {
    var s = str(html).replace(/<\s*br\s*\/?>/gi, '\n').replace(/<\/\s*(p|li|h3|h4|blockquote|div)\s*>/gi, '\n').replace(/<[^>]*>/g, '');
    s = s.replace(/&nbsp;/g, ' ').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&amp;/g, '&');
    return s.replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
  }
  function basename(u) { var s = str(u).split('?')[0].split('#')[0]; return s.slice(s.lastIndexOf('/') + 1); }

  /**
   * The text of one field's answer on the review page.
   * field: the structure row; rows: its answer rows (sendBack); ctx: {sep, matrix: [{id_, value}] (r_matrix rows)}.
   * Returns null (not shown at all: structure, secret, hidden value) or {empty, text, sig?, sigText?, files?}.
   */
  function answerText(field, rows, ctx) {
    ctx = ctx || {};
    var sep = ctx.sep || ', ';
    var type = str(field && field.type);
    var id = str(field && field.id_);
    if (!field || SKIP_TYPES.indexOf(type) !== -1 || SECRET_IDS.indexOf(id) !== -1) return null;
    if (on(field.hidden)) return null;
    rows = (rows || []).filter(function (r) { return r && typeof r === 'object' && str(r.type) !== 'password'; });
    var vals = function () { return rows.map(function (r) { return r.value; }).filter(function (v) { return v !== undefined && v !== null && typeof v !== 'object' && str(v) !== ''; }); };
    var out = { empty: false, text: '' };
    if (type === 'esign') {
      var r0 = rows[0];
      var src = r0 && SIG_RE.test(str(r0.value)) ? str(r0.value) : '';
      if (!src) return { empty: true, text: '' };
      out.sig = src;
      out.sigText = r0 && r0.sig_mode === 'type' ? textField(r0.sig_text) : '';
      out.text = out.sigText;
      return out;
    }
    if (FILE_TYPES.indexOf(type) !== -1) {
      var names = rows.filter(function (r) { return str(r.value) === '@file@' && /^https?:\/\//i.test(str(r.url)); }).map(function (r) { return r.fnm ? str(r.fnm) : basename(r.url); });
      if (!names.length) return { empty: true, text: '' };
      out.files = names;
      out.text = names.join(sep);
      return out;
    }
    if (type === 'maps') {
      var m = rows[0] && Array.isArray(rows[0].value) ? rows[0].value : [];
      var pts = m.filter(function (x) { return x && typeof x === 'object'; }).map(function (x) { return str(x.address) !== '' ? str(x.address) : (str(x.lat) + ', ' + str(x.lng)); });
      return pts.length ? { empty: false, text: pts.join('\n') } : { empty: true, text: '' };
    }
    if (type === 'table_matrix') {
      var lines = (ctx.matrix || []).map(function (q) {
        var a = rows.filter(function (r) { return str(r.id_) === str(q.id_) || str(r.id_ob) === str(q.id_); })[0];
        return a && str(a.value) !== '' ? str(q.value) + ': ' + str(a.value) : '';
      }).filter(Boolean);
      return lines.length ? { empty: false, text: lines.join('\n') } : { empty: true, text: '' };
    }
    if (type === 'ranking') {
      var rk = str(rows[0] && rows[0].value).split('@efb!').filter(function (x) { return x !== ''; });
      return rk.length ? { empty: false, text: rk.map(function (x, i) { return (i + 1) + '. ' + untoken(x); }).join(sep) } : { empty: true, text: '' };
    }
    if (type === 'range' && rows[0] && /^-?[0-9.]+\.\.-?[0-9.]+$/.test(str(rows[0].value))) {
      var lh = str(rows[0].value).split('..');
      return { empty: false, text: lh[0] + ' – ' + lh[1], ltr: true };
    }
    if ((type === 'number' || type === 'calc' || type === 'range' || type === 'advdate') && rows[0] && (str(rows[0].disp) !== '' || str(rows[0].value) !== '')) {
      return { empty: false, text: str(rows[0].disp) !== '' ? str(rows[0].disp) : str(rows[0].value) };
    }
    if (type === 'textarea' && rows[0] && (rows[0].fmt === 'html' || on(field.rich_text))) {
      var p = plainOfHtml(untoken(rows[0].value));
      return p !== '' ? { empty: false, text: p } : { empty: true, text: '' };
    }
    var parts = [];
    rows.forEach(function (r) {
      if (r.value === undefined || r.value === null || typeof r.value === 'object') return;
      var v = str(r.value);
      if (v === '' || v === '@file@') return;
      var pieces = v.split('@efb!').filter(function (x) { return x !== ''; }).map(untoken);
      var t = pieces.join(sep);
      if (on(r.other)) t = (ctx.otherLabel || 'Other') + ': ' + t;
      if (r.qty !== undefined && str(r.qty) !== '') t += ': ' + str(r.qty);
      parts.push(t);
    });
    if (!parts.length && !vals().length) return { empty: true, text: '' };
    out.text = parts.join(sep);
    return out;
  }

  var core = {
    FORM_KEYS: FORM_KEYS, FIELD_KEYS: FIELD_KEYS, RUNTIME_KEYS: RUNTIME_KEYS, LIMITS: LIMITS, DEFAULTS: DEFAULTS, LEGACY_PAY_TYPES: LEGACY_PAY_TYPES, SKIP_TYPES: SKIP_TYPES,
    on: on, sanitizeKey: sanitizeKey, legacyPayment: legacyPayment, reviewOn: reviewOn, answerText: answerText, plainOfHtml: plainOfHtml, untoken: untoken
  };

  /* ================================================================== page */

  var page = (function () {
    function doc() { return typeof document !== 'undefined' ? document : null; }
    function ev() { return (typeof efb_var !== 'undefined' && efb_var) || root.efb_var || (typeof ajax_object_efm !== 'undefined' ? ajax_object_efm : null) || {}; }
    function T(k, fb) { var e = ev(); return (e.text && e.text[k]) || fb; }
    function locale() { var e = ev(); return str(e.wp_lan || e.language || (doc() && doc().documentElement.lang) || 'en_US'); }
    function lang() { return locale().split(/[_-]/)[0].toLowerCase(); }
    function rtlLang() { return ['fa', 'ar', 'ps', 'ur', 'ckb', 'azb', 'haz', 'ary', 'arq', 'he'].indexOf(lang()) !== -1; }
    /** Digits of the page's language (Persian / Arabic-Indic), as F09 numbers rows. */
    function digits(s) {
      var l = lang();
      var base = ['fa', 'ps', 'azb', 'haz'].indexOf(l) !== -1 ? 0x06F0 : (['ar', 'ary', 'arq', 'ckb', 'ur'].indexOf(l) !== -1 ? 0x0660 : 0);
      return base ? String(s).replace(/[0-9]/g, function (d) { return String.fromCharCode(base + Number(d)); }) : String(s);
    }
    /** A sentence with an inserted value isolated (FSI … PDI): the value keeps its place in a right-to-left sentence. */
    function fill(t, a) { return String(t).replace('%s', '⁨' + a + '⁩'); }
    function structure(fid) {
      try { if (typeof get_structure_by_form_id_efb === 'function') return get_structure_by_form_id_efb(fid); } catch (e) { /* no form */ }
      return [];
    }
    function bodyOf(fid) { var d = doc(); return d ? d.getElementById('body_efb_' + fid) : null; }
    function el(tag, cls, text) {
      var e = doc().createElement(tag);
      if (cls) e.className = cls;
      if (text !== undefined && text !== null) e.textContent = text;
      return e;
    }
    function inForm(fid, sel) {
      var b = bodyOf(fid);
      if (typeof efb_find_in_form_efb === 'function') { try { return efb_find_in_form_efb(fid, sel, b); } catch (e) { /* below */ } }
      return b ? b.querySelector(sel) : null;
    }
    function byId(fid, id) {
      if (typeof efb_get_by_id_in_form_efb === 'function') return efb_get_by_id_in_form_efb(fid, id);
      return doc().getElementById(id);
    }
    function answers(fid) {
      var all = (typeof sendBack_emsFormBuilder_pub !== 'undefined' && Array.isArray(sendBack_emsFormBuilder_pub)) ? sendBack_emsFormBuilder_pub : [];
      return all.filter(function (r) { return r && typeof r === 'object' && (r.form_id === undefined || Number(r.form_id) === Number(fid)); });
    }

    /* ---------------------------------------------------------------- what is shown */

    function stepHidden(fid, n) {
      var b = bodyOf(fid);
      var fs = b ? b.querySelector('[data-step="step-' + n + '-efb"]') : null;
      return !fs || (fs.dataset && (fs.dataset.logicHidden === '1' || fs.dataset.visEmpty === '1'));
    }
    /** A field the visitor cannot see now: hidden by Conditional Logic (its wrapper or a hidden group / section). */
    function fieldHidden(fid, row) {
      var w = byId(fid, row.id_);
      if (!w) return true;
      if (w.classList.contains('d-none') || w.classList.contains('efb-anim-hide')) return true;
      if (typeof efb_rpt_hidden_efb === 'function' && efb_rpt_hidden_efb(fid, row)) return true;
      var reg = w.closest ? w.closest('.efb-section-region') : null;
      if (reg && (reg.classList.contains('d-none') || reg.classList.contains('efb-anim-hide'))) return true;
      return false;
    }
    function rowsOfField(all, id) { return all.filter(function (r) { return str(r.id_) === str(id); }); }

    /** The entries of the review page: [{step, name, items: [{row, label, ans, rpt?}]}]. */
    function collect(fid) {
      var st = structure(fid) || [];
      var s0 = st[0] || {};
      var hideEmpty = s0.review_hide_empty === undefined ? true : on(s0.review_hide_empty);
      var all = answers(fid);
      var max = Number(s0.steps) || 1;
      var ctxBase = { sep: rtlLang() ? '، ' : ', ', otherLabel: T('otherOpt', 'Other') };
      var out = [];
      var byAmount = function (a, b) { return Number(a.amount || 0) - Number(b.amount || 0); };
      for (var n = 1; n <= max; n++) {
        if (stepHidden(fid, n)) continue;
        var stepRow = st.filter(function (r) { return r && r.type === 'step' && Number(r.step) === n; })[0] || {};
        var items = [];
        var rowsOfStep = st.filter(function (r, i) { return i > 0 && r && r.type !== 'step' && Number(r.step) === n && !r.rpt_base && !(r.group && !r.rpt_group); }).sort(byAmount);
        rowsOfStep.forEach(function (row) {
          if (row.rpt_group) return; // a copy is listed under its group
          if (row.type === 'repeater') {
            if (fieldHidden(fid, row)) return;
            var copies = st.filter(function (r) { return r && r.rpt_group === row.id_ && r.type !== 'option'; });
            // The rows in the page's order (EFBRepeater numbers them); copies carry their row's n (rpt_n).
            var ns = [];
            try { if (root.EFBRepeater && typeof root.EFBRepeater.rowsOf === 'function') ns = root.EFBRepeater.rowsOf(fid, row.id_).map(function (x) { return Number(x.n); }); } catch (e) { ns = []; }
            if (!ns.length) copies.forEach(function (c) { var n = Number(c.rpt_n); if (ns.indexOf(n) === -1) ns.push(n); });
            var rowsOut = [];
            ns.forEach(function (rn, k) {
              var sub = [];
              copies.filter(function (c) { return Number(c.rpt_n) === rn; }).sort(byAmount).forEach(function (c) {
                if (fieldHidden(fid, c)) return;
                var a = answerText(c, rowsOfField(all, c.id_), ctxBase);
                if (!a || (a.empty && hideEmpty)) return;
                sub.push({ row: c, label: str(c.name), ans: a });
              });
              if (sub.length) rowsOut.push({ n: k + 1, items: sub });
            });
            if (rowsOut.length || !hideEmpty) items.push({ row: row, label: str(row.name), group: rowsOut });
            return;
          }
          if (fieldHidden(fid, row)) return;
          var ctx = ctxBase;
          if (row.type === 'table_matrix') ctx = Object.assign({}, ctxBase, { matrix: st.filter(function (r) { return r && r.type === 'r_matrix' && str(r.parent) === str(row.id_); }) });
          var rows = row.type === 'table_matrix' ? all.filter(function (r) { return str(r.id_ob) === str(row.id_) || st.some(function (q) { return q && q.type === 'r_matrix' && str(q.parent) === str(row.id_) && str(q.id_) === str(r.id_); }); }) : rowsOfField(all, row.id_);
          var a = answerText(row, rows, ctx);
          if (!a || (a.empty && hideEmpty)) return;
          items.push({ row: row, label: str(row.name), ans: a });
        });
        out.push({ step: n, name: str(stepRow.name), items: items });
      }
      return out;
    }

    /* ---------------------------------------------------------------- the page */

    function wanted(fid) {
      var b = bodyOf(fid);
      if (!b || b.dataset.reviewOk === '1') return false;
      return reviewOn(structure(fid));
    }
    function navButtons(fid) {
      return ['#next_efb', '#prev_efb', '#btn_send_efb'].map(function (s) { return inForm(fid, s); }).filter(Boolean);
    }
    function lastVisibleStep(fid) {
      var max = Number((structure(fid)[0] || {}).steps) || 1;
      for (var n = max; n >= 1; n--) if (!stepHidden(fid, n)) return n;
      return 1;
    }
    function setHeader(fid, title) {
      var b = bodyOf(fid);
      if (!b) return;
      var t = b.querySelector('#title_efb'), d = b.querySelector('#desc_efb');
      if (t && d && Number((structure(fid)[0] || {}).show_icon) !== 1) { t.textContent = title; d.textContent = ' '; }
      var shell = b.querySelector('.efb-sp');
      var max = Number(b.dataset.steps) || 1;
      try { if (shell && typeof efbStepsSyncEfb === 'function') efbStepsSyncEfb(shell, max, max + 1, title); } catch (e) { /* the bar keeps its state */ }
      var fillEl = b.querySelector('.efb-sp__fill') || b.querySelector('.progress-bar-efb');
      if (fillEl) { fillEl.style.width = '95%'; fillEl.setAttribute('aria-valuenow', '95'); }
    }
    function focusField(fid, id) {
      var w = byId(fid, id);
      if (!w) return;
      if (typeof efb_section_reveal_efb === 'function') { try { efb_section_reveal_efb(id, fid); } catch (e) { /* no section */ } }
      if (root.EFBRepeater && typeof root.EFBRepeater.reveal === 'function') { try { root.EFBRepeater.reveal(id, fid); } catch (e) { /* no row */ } }
      var f = w.querySelector('input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [contenteditable="true"], canvas, [tabindex]:not([tabindex="-1"]), button:not([disabled])');
      try { (f || w).focus({ preventScroll: false }); } catch (e) { /* not focusable */ }
      try { (f || w).scrollIntoView({ block: 'center' }); } catch (e) { /* old browser */ }
    }
    function nextText(fid, review) {
      var n = inForm(fid, '#button_group_Next_button_text');
      if (!n) return;
      if (review) { if (!n.dataset.efbReviewText) n.dataset.efbReviewText = '1'; n.textContent = T('rvwBackToReview', 'Back to review'); }
      else if (n.dataset.efbReviewText) { delete n.dataset.efbReviewText; if (typeof updateStepButtonState_efb === 'function') updateStepButtonState_efb(fid); }
    }
    /** Edit: back to a step (or one field) with "Back to review" on Next. */
    function edit(fid, step, fieldId) {
      var b = bodyOf(fid);
      if (!b) return;
      close(fid);
      b.dataset.returnReview = '1';
      if (typeof efb_go_to_step_direct === 'function') efb_go_to_step_direct(fid, step);
      nextText(fid, true);
      var sendBtn = inForm(fid, '#btn_send_efb');
      if (sendBtn) sendBtn.classList.remove('d-none');
      if (fieldId) setTimeout(function () { focusField(fid, fieldId); }, 60);
      else {
        var fs = b.querySelector('[data-step="step-' + step + '-efb"]');
        var first = fs ? fs.querySelector('input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [contenteditable="true"]') : null;
        setTimeout(function () { try { (first || fs).focus(); } catch (e) { /* not focusable */ } }, 60);
      }
    }
    function close(fid) {
      var b = bodyOf(fid);
      var d = doc();
      var r = d ? d.getElementById('efb-review-step-' + fid) : null;
      if (r) r.classList.add('d-none');
      if (b) delete b.dataset.review;
      navButtons(fid).forEach(function (x) { if (x.dataset.efbReviewHid === '1') { x.classList.remove('d-none'); delete x.dataset.efbReviewHid; } });
    }
    function btnClasses(s0, kind) {
      var color = kind === 'prev' ? (s0.button_color || 'btn-primary') : (s0.button_color || 'btn-primary');
      return 'efb btn ' + color + ' ' + (s0.corner || 'rounded-3') + ' ' + (s0.el_height || 'h-d-efb') + ' efb-btn-lg p-2 m-1 efb-review-btn';
    }

    /** Draws the review page of a form (the sent answers as the server will get them; nothing is sent). */
    function render(fid) {
      var d = doc();
      var b = bodyOf(fid);
      if (!d || !b) return null;
      var st = structure(fid) || [];
      var s0 = st[0] || {};
      var title = str(s0.review_title) !== '' ? str(s0.review_title) : T('rvwTtl', 'Review your answers');
      if (root.EFBSmart && /\{/.test(title) && typeof root.EFBSmart.resolve === 'function' && typeof root.EFBSmart.context === 'function') {
        try { title = root.EFBSmart.resolve(title, root.EFBSmart.context(fid), 'text', 'label') || title; } catch (e) { /* the title as typed */ }
      }
      var box = d.getElementById('efb-review-step-' + fid);
      if (!box) {
        box = el('fieldset', 'efb efb-review row my-2 mx-0 px-2');
        box.id = 'efb-review-step-' + fid;
        box.setAttribute('data-formid', String(fid));
        box.setAttribute('aria-labelledby', 'efb-review-title-' + fid);
        var fin = typeof efb_get_final_step_efb === 'function' ? efb_get_final_step_efb(fid, b) : null;
        if (fin && fin.parentNode) fin.parentNode.insertBefore(box, fin); else b.appendChild(box);
      }
      box.textContent = '';
      box.classList.remove('d-none');
      // Headings and labels align with the page (an English phrase on a right-to-left page still sits on the right).
      var rtl = false;
      try { rtl = getComputedStyle(b).direction === 'rtl'; } catch (e) { rtl = false; }
      box.classList.toggle('efb-review-rtl', rtl);
      var h = el('h3', 'efb efb-review-title fs-5 mb-1', title);
      h.id = 'efb-review-title-' + fid;
      h.tabIndex = -1;
      box.appendChild(h);
      box.appendChild(el('p', 'efb efb-review-help fs-7 text-muted mb-2', T('rvwHelp', 'Check your answers. Use Edit to change one, then send the form.')));
      var groups = collect(fid);
      var multi = (Number(s0.steps) || 1) > 1;
      groups.forEach(function (g) {
        if (!g.items.length && !multi) return;
        var sec = el('section', 'efb efb-review-step');
        var hid = 'efb-review-s' + g.step + '-' + fid;
        sec.setAttribute('aria-labelledby', hid);
        var head = el('div', 'efb efb-review-step-head');
        var h4 = el('h4', 'efb efb-review-step-name fs-6', g.name || digits(g.step));
        h4.id = hid;
        head.appendChild(h4);
        if (multi) {
          var eb = el('button', 'efb btn btn-link efb-review-edit', T('rvwEdit', 'Edit'));
          eb.type = 'button';
          eb.setAttribute('aria-label', fill(T('rvwEditStep', 'Edit step %s'), g.name || digits(g.step)));
          eb.addEventListener('click', function () { edit(fid, g.step, null); });
          head.appendChild(eb);
        }
        sec.appendChild(head);
        var dl = el('dl', 'efb efb-review-list');
        g.items.forEach(function (it) {
          dl.appendChild(item(fid, g.step, it));
        });
        if (!g.items.length) dl.appendChild(el('div', 'efb efb-review-none text-muted fs-7', T('rvwNoAnswer', 'No answer')));
        sec.appendChild(dl);
        box.appendChild(sec);
      });
      amountRow(fid, box, st);
      var act = el('div', 'efb efb-review-actions d-flex flex-wrap ' + (s0.btns_align || 'justify-content-center'));
      var back = el('button', btnClasses(s0, 'prev'));
      back.type = 'button';
      back.classList.add('efb-review-back');
      var bt = el('span', 'efb ' + str(s0.el_text_color || ''), str(s0.button_Previous_text) || T('previous', 'Previous'));
      back.appendChild(bt);
      back.addEventListener('click', function () { edit(fid, lastVisibleStep(fid), null); delete b.dataset.returnReview; nextText(fid, false); });
      var ok = el('button', btnClasses(s0, 'next'));
      ok.type = 'button';
      ok.classList.add('efb-review-confirm');
      ok.appendChild(el('span', 'efb ' + str(s0.el_text_color || ''), T('rvwConfirm', 'Confirm and submit')));
      ok.addEventListener('click', function () { confirm(fid); });
      act.appendChild(back);
      act.appendChild(ok);
      box.appendChild(act);
      return box;
    }
    function item(fid, step, it) {
      var row = el('div', 'efb efb-review-row');
      row.setAttribute('data-field', str(it.row.id_));
      var dt = el('dt', 'efb efb-review-label ' + str(it.row.label_align || ''));
      dt.appendChild(el('bdi', 'efb', it.label));
      row.appendChild(dt);
      var dd = el('dd', 'efb efb-review-value');
      if (it.group) {
        it.group.forEach(function (gr) {
          var blk = el('div', 'efb efb-review-rpt');
          blk.appendChild(el('div', 'efb efb-review-rpt-n fw-bold fs-7', fill(T('rowN', 'Row %s'), digits(gr.n))));
          var sub = el('dl', 'efb efb-review-list efb-review-sub');
          gr.items.forEach(function (x) { sub.appendChild(item(fid, step, x)); });
          blk.appendChild(sub);
          dd.appendChild(blk);
        });
        if (!it.group.length) dd.appendChild(el('span', 'efb text-muted', T('rvwNoAnswer', 'No answer')));
      } else {
        dd.appendChild(valueNode(it.ans));
        var eb = el('button', 'efb btn btn-link efb-review-edit-field', T('rvwEdit', 'Edit'));
        eb.type = 'button';
        eb.setAttribute('aria-label', fill(T('rvwEditField', 'Edit %s'), it.label));
        eb.addEventListener('click', function () { edit(fid, step, it.row.id_); });
        dd.appendChild(eb);
      }
      row.appendChild(dd);
      return row;
    }
    function valueNode(a) {
      var wrap = el('span', 'efb efb-review-answer');
      if (a.empty) { wrap.appendChild(el('span', 'efb text-muted', T('rvwNoAnswer', 'No answer'))); return wrap; }
      if (a.sig) {
        var img = el('img', 'efb efb-review-sig img-thumbnail');
        if (SIG_RE.test(a.sig)) img.src = a.sig;
        img.alt = a.sigText || T('rvwSigned', 'Signature');
        wrap.appendChild(img);
        if (a.sigText) { var bs = el('bdi', 'efb efb-review-sigtext', a.sigText); bs.dir = 'auto'; wrap.appendChild(bs); }
        return wrap;
      }
      if (a.files) {
        a.files.forEach(function (n) { var f = el('bdi', 'efb efb-review-file', n); f.dir = 'auto'; wrap.appendChild(f); });
        return wrap;
      }
      a.text.split('\n').forEach(function (line, i) {
        if (i) wrap.appendChild(el('br'));
        var b = el('bdi', 'efb', line);
        b.dir = a.ltr ? 'ltr' : 'auto';
        wrap.appendChild(b);
      });
      return wrap;
    }
    /** A payment-framework form: the server's amount (payments/quote), never one computed here. */
    function amountRow(fid, box, st) {
      var pf = null;
      var e = ev();
      for (var i = 1; i < st.length; i++) {
        var r = st[i];
        if (r && root.EFBPay && typeof root.EFBPay.isGateway === 'function' && root.EFBPay.isGateway(str(r.type))) { pf = r; break; }
      }
      if (!pf) return;
      var row = el('div', 'efb efb-review-amount d-flex flex-wrap gap-2 align-items-baseline my-2');
      row.setAttribute('role', 'status');
      row.appendChild(el('span', 'efb fw-bold', T('rvwAmount', 'Amount to pay')));
      var v = el('bdi', 'efb efb-review-amount-val', T('rvwAmountLoading', 'Checking the amount…'));
      v.dir = 'auto';
      row.appendChild(v);
      box.appendChild(row);
      if (typeof efb_review_quote_efb !== 'function') { v.textContent = T('rvwAmountLater', 'The amount is confirmed when you pay.'); return; }
      Promise.resolve(efb_review_quote_efb(fid, str(pf.id_))).then(function (q) {
        if (q && q.ok && typeof q.amount === 'string' && q.amount !== '') { v.textContent = q.amount; v.dir = 'ltr'; v.setAttribute('data-total-minor', str(q.total_minor)); }
        else v.textContent = T('rvwAmountLater', 'The amount is confirmed when you pay.');
      }, function () { v.textContent = T('rvwAmountLater', 'The amount is confirmed when you pay.'); });
      void e;
    }

    /** Shows the review page in place of the steps (from the last step's Send, or Next past the last step). */
    function open(fid, fromStep) {
      var b = bodyOf(fid);
      if (!b) return false;
      delete b.dataset.returnReview;
      nextText(fid, false);
      b.querySelectorAll('[data-step^="step-"][data-step$="-efb"]').forEach(function (f) { f.classList.add('d-none'); });
      var fin = typeof efb_get_final_step_efb === 'function' ? efb_get_final_step_efb(fid, b) : null;
      if (fin) fin.classList.add('d-none');
      var box = render(fid);
      if (!box) return false;
      navButtons(fid).forEach(function (x) { if (!x.classList.contains('d-none')) { x.classList.add('d-none'); x.dataset.efbReviewHid = '1'; } });
      b.dataset.review = '1';
      b.dataset.currentstep = String(fromStep && Number(fromStep) <= (Number(b.dataset.steps) || 1) ? fromStep : lastVisibleStep(fid));
      b.dataset.reviewFrom = b.dataset.currentstep;
      var st = structure(fid)[0] || {};
      setHeader(fid, str(st.review_title) !== '' ? box.querySelector('.efb-review-title').textContent : T('rvwTtl', 'Review your answers'));
      var h = box.querySelector('.efb-review-title');
      setTimeout(function () { try { h.focus(); } catch (e) { /* not focusable */ } }, 30);
      try { box.scrollIntoView({ block: 'start' }); } catch (e) { /* old browser */ }
      return true;
    }
    /** Confirm: the form goes through its own Send (every check again, captcha, Human Shield, the payment). */
    function confirm(fid) {
      var b = bodyOf(fid);
      if (!b) return;
      close(fid);
      b.dataset.reviewOk = '1';
      var from = Number(b.dataset.reviewFrom) || lastVisibleStep(fid);
      var max = Number(b.dataset.steps) || 1;
      var type = str((structure(fid)[0] || {}).type || 'form');
      b.dataset.currentstep = String(from);
      var send = inForm(fid, '#btn_send_efb');
      var next = inForm(fid, '#next_efb');
      var run = (max > 1 || !send) ? btn_navigate_handle_efb(String(fid), type, 'next_efb', next) : btn_navigate_handle_efb(String(fid), type, 'btn_send_efb', send);
      Promise.resolve(run).then(function (r) { if (r === false) { delete b.dataset.reviewOk; if (Number(b.dataset.currentstep) <= max) { b.querySelectorAll('[data-step="step-' + b.dataset.currentstep + '-efb"]').forEach(function (f) { f.classList.remove('d-none'); }); navButtons(fid).forEach(function (x) { x.classList.remove('d-none'); }); if (typeof updateStepButtonState_efb === 'function') updateStepButtonState_efb(fid); } } else { setTimeout(function () { delete b.dataset.reviewOk; }, 0); } });
    }

    /**
     * core-efb.js, where the form would be sent: true = the review page is shown instead (the caller stops).
     * fromStep: the last step the visitor saw.
     */
    function reviewIntercept(fid, fromStep) {
      if (!wanted(fid)) return false;
      return open(fid, fromStep);
    }
    /** core-efb.js, Next on a step opened from the review page (after the step's checks): back to the review page. */
    function reviewReturn(fid, step) {
      var b = bodyOf(fid);
      if (!b || b.dataset.returnReview !== '1') return false;
      if (!wanted(fid)) { delete b.dataset.returnReview; nextText(fid, false); return false; }
      return open(fid, Math.max(Number(step) || 1, Number(b.dataset.reviewFrom) || 1));
    }
    /** A Conditional Logic jump during the step's checks: the normal flow goes on (spec §11). */
    function reviewCancelReturn(fid) {
      var b = bodyOf(fid);
      if (b && b.dataset.returnReview === '1') { delete b.dataset.returnReview; nextText(fid, false); }
    }

    return { wanted: wanted, collect: collect, render: render, open: open, close: close, confirm: confirm, edit: edit,
      reviewIntercept: reviewIntercept, reviewReturn: reviewReturn, reviewCancelReturn: reviewCancelReturn, digits: digits };
  })();

  root.EFBLayoutCore = core;
  root.EFBLayout = page;
  if (typeof module === 'object' && module.exports) module.exports = core;
})(typeof window !== 'undefined' ? window : globalThis);
