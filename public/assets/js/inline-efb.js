/**
 * F16 / F20-P21: inline validation and the error summary, as one small module any EFB page can use
 * (window.EFBInline, contract version 1; docs/a11y/inline-validation-contract.en.md).
 *
 *  - a field's error: the text under the field (textContent, never HTML), aria-invalid and the message
 *    first in aria-describedby (the description and F03 hints kept), a hidden "Error:" before it for screen
 *    readers (CSS reads data-efb-prefix, so textContent stays the message), cleared the same way;
 *  - one polite live region per form, a GOV.UK-style error summary with links that move focus to the field
 *    (a radio group: the chosen or first choice; a select; a phone; a file button), focus on a step;
 *  - "blur" timing for any form (attach(): nothing while typing, checked when leaving a changed field,
 *    cleared while typing once right, every field on Send) and a "leave this page?" warning while unsent;
 *  - numbers in the page's digits and lists joined the page's way (Intl), for messages.
 *
 * The form runtime (core-efb.js) keeps its own checks and calls these; another add-on (the customer portal)
 * can call attach() on its own form. Nothing here talks to the server: the server stays authoritative and its
 * answer ({field_id, m, code} or errors: [...], see efb_inline_error()) is shown with applyServer().
 */
(function (root) {
  'use strict';
  const D = () => root.document;
  const S = (v) => (v === null || v === undefined ? '' : String(v));

  /** A phrase of the page (efb_var / ajax_object_efm / efb_inline_text), else the English fallback. */
  function T(k, fb) {
    for (const o of [root.efb_inline_text, root.efb_var && root.efb_var.text, root.ajax_object_efm && root.ajax_object_efm.text]) {
      if (o && typeof o[k] === 'string' && o[k] !== '') return o[k];
    }
    return fb;
  }
  function locale(el) {
    const v = (root.efb_var && (root.efb_var.wp_lan || root.efb_var.language)) || (root.ajax_object_efm && (root.ajax_object_efm.wp_lan || root.ajax_object_efm.language)) || (el && el.closest && (el.closest('[lang]') || {}).lang) || (D().documentElement.lang) || 'en';
    return S(v).replace('_', '-');
  }
  /** A number in the page's digits (fa: ۳, ar: ٣). */
  const loc_ = (l) => (l ? S(l).replace('_', '-') : locale());
  function num(n, loc) { try { return Number(n).toLocaleString(loc_(loc)); } catch (e) { return S(n); } }
  /** "a, b and c" in the page's language (Persian and Arabic join with their own words and commas). */
  function list(items, loc, type) {
    items = (items || []).map(S).filter((x) => x !== '');
    try { if (root.Intl && Intl.ListFormat) return new Intl.ListFormat(loc_(loc), { style: 'long', type: type || 'conjunction' }).format(items); } catch (e) { /* old browser */ }
    return items.join(', ');
  }
  const esc = (v) => S(v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  /** The text of a server message (it may hold <b>): parsed, never run (DOMParser runs no handler). */
  function plain(m) {
    m = S(m);
    if (m.indexOf('<') === -1) return m;
    try { return new root.DOMParser().parseFromString(m, 'text/html').body.textContent || ''; } catch (e) { return m.replace(/<[^>]*>/g, ''); }
  }

  /* ------------------------------------------------------------------ where things are */
  /** The form (or the root given to attach()) of an element. */
  function scopeOf(el) {
    return (el && el.closest && (el.closest('[data-efb-inline]') || el.closest('.body_efb') || el.closest('form'))) || D();
  }
  function byId(scope, id) {
    if (!id) return null;
    const d = D();
    if (scope && scope !== d && scope.querySelector) {
      try { const x = scope.querySelector('#' + (root.CSS && CSS.escape ? CSS.escape(id) : id)); if (x) return x; } catch (e) { /* odd id */ }
    }
    return d.getElementById(id);
  }
  /** The field id (EFB: data-vid, else the id without its trailing "_"). */
  function fieldId(el) { return (el && el.dataset && el.dataset.vid) || S(el && el.id).replace(/_(options)?$/, ''); }
  /** The message box of a field: data-efb-msg, "{id}-message", or EFB's "{field}_-message". */
  function msgOf(el) {
    if (!el) return null;
    const sc = scopeOf(el);
    const a = el.getAttribute && el.getAttribute('data-efb-msg');
    return byId(sc, a) || (el.id ? byId(sc, el.id + '-message') : null) || byId(sc, fieldId(el) + '_-message');
  }
  /** Where aria-invalid goes: a choice of a group → the group (role group / radiogroup), else the field. */
  function hostOf(el) {
    if (el && (el.type === 'radio' || el.type === 'checkbox')) {
      const g = el.closest && el.closest('[role="radiogroup"], [role="group"]');
      if (g) return g;
    }
    return el;
  }
  const visible = (el) => !!(el && (el.offsetWidth || el.offsetHeight || (el.getClientRects && el.getClientRects().length)));
  const focusable = (el) => el && !el.disabled && el.getAttribute('aria-hidden') !== 'true' && visible(el);

  /* ------------------------------------------------------------------ aria-describedby */
  function ids(el) { return S(el && el.getAttribute('aria-describedby')).split(/\s+/).filter(Boolean); }
  function setIds(el, list) { if (list.length) el.setAttribute('aria-describedby', list.join(' ')); else el.removeAttribute('aria-describedby'); }

  /* ------------------------------------------------------------------ one field */
  /**
   * Show a field's error. opts: msgEl (the box), announce (true: "{label}: {message}" in the form's live region),
   * label, quiet (an error already shown keeps its words until the field is left: "punish late").
   */
  function setError(el, msg, opts) {
    opts = opts || {};
    if (!el) return null;
    const m = opts.msgEl || msgOf(el);
    const host = hostOf(el);
    const shown = m && m.style.display !== 'none' && m.textContent !== '';
    if (m) m._efbSeq = (m._efbSeq || 0) + 1;
    if (m && !(opts.quiet && shown)) m.textContent = opts.html ? plain(msg) : S(msg);
    if (m) mark(m, true);
    if (host && host.setAttribute) {
      host.setAttribute('aria-invalid', 'true');
      if (m && m.id && ids(host).indexOf(m.id) === -1) setIds(host, [m.id].concat(ids(host)));
    }
    if (opts.announce && !(opts.quiet && shown)) announce(scopeOf(el), (opts.label ? opts.label + ': ' : '') + (m ? m.textContent : plain(msg)));
    return m;
  }
  /**
   * Clear a field's error: the box emptied and hidden, aria-invalid and the box's id removed (the rest kept). While a
   * pointer is down (the field was left by pressing Send) it waits for the pointer to come up, so the box closing does
   * not move Send away from under it; an error shown meanwhile cancels it.
   */
  function clearError(el, opts) {
    opts = opts || {};
    if (!el) return;
    const m0 = opts.msgEl || msgOf(el);
    if (down && !opts.now) { const seq = m0 ? (m0._efbSeq = (m0._efbSeq || 0) + 1) : 0, t0 = m0 ? m0.textContent : ''; queue.push(() => { if (!m0 || (m0._efbSeq === seq && m0.textContent === t0)) clearError(el, Object.assign({}, opts, { now: true })); }); return; }
    const m = m0;
    const host = hostOf(el);
    if (m) { m.textContent = ''; m.style.display = 'none'; m.removeAttribute('data-efb-prefix'); }
    if (host && host.removeAttribute) {
      host.removeAttribute('aria-invalid');
      if (m && m.id) setIds(host, ids(host).filter((x) => x !== m.id));
    }
    // A summary on screen loses the line of a field put right; the last one hides it. Not while a pointer is down:
    // leaving the field by clicking Send would move Send away under the pointer (the click lost).
    const box = scopeOf(el).querySelector && scopeOf(el).querySelector('.efb-errsum:not(.d-none)');
    const id = fieldId(hostOf(el)).replace(/"/g, '');
    if (box) later(() => {
      const a = box.querySelector('a[data-efb-field="' + id + '"]');
      if (!a) return;
      a.closest('li').remove();
      const n = box.querySelectorAll('li').length, t = box.querySelector('.efb-errsum-t');
      if (!n) hideSummary(null, box); else if (t) t.textContent = sumTitle(n);
    });
  }
  let down = false;
  const queue = [];
  function later(fn) { if (down) queue.push(fn); else fn(); }
  if (root.document && root.addEventListener) {
    root.addEventListener('pointerdown', () => { down = true; }, true);
    root.addEventListener('pointerup', () => { down = false; setTimeout(() => { while (queue.length) queue.shift()(); }, 0); }, true);
  }
  /** The look of a shown message box: block, its own direction (mixed scripts keep their order), the hidden prefix. */
  function mark(m, on) {
    m.style.display = on ? 'block' : 'none';
    m.setAttribute('dir', 'auto');
    m.style.unicodeBidi = 'plaintext';
    m.classList.add('efb-field-error');
    m.setAttribute('data-efb-prefix', T('a11yErrPrefix', 'Error:') + ' ');
  }
  function isShown(el) { const m = msgOf(el); return !!(m && m.style.display !== 'none' && m.textContent !== ''); }

  /* ------------------------------------------------------------------ the form */
  /** The polite live region of a form (EFB renders efb-live-{id}; anything else gets one). */
  function live(scope) {
    scope = scope || D().body;
    let r = scope.querySelector ? scope.querySelector('[data-efb-live]') : null;
    if (!r) {
      const fid = scope.dataset && scope.dataset.formid;
      r = fid ? D().getElementById('efb-live-' + fid) : null;
    }
    if (!r) {
      r = D().createElement('div');
      r.className = 'efb efb-vh';
      r.setAttribute('role', 'status');
      r.setAttribute('aria-live', 'polite');
      r.setAttribute('aria-atomic', 'true');
      r.setAttribute('data-efb-live', '');
      (scope === D() ? D().body : scope).appendChild(r);
    }
    return r;
  }
  /** Say a sentence once (emptied first, written a moment later, so the same words are read again). */
  function announce(scope, text) {
    const r = live(scope);
    r.textContent = '';
    setTimeout(() => { r.textContent = S(text); }, 50);
    return r;
  }
  /** The element to focus for a field id: its input, the chosen (else first) choice, the select, a file button, or the field. */
  function target(scope, id) {
    const sc = scope || D();
    const own = byId(sc, id);
    if (own && /^(INPUT|SELECT|TEXTAREA)$/.test(own.tagName) && focusable(own)) return own;
    const order = [byId(sc, id + '_'), byId(sc, id + '_options'), byId(sc, id + '_yn')];
    for (const c of order) {
      if (!c) continue;
      if (/^(INPUT|SELECT|TEXTAREA|BUTTON)$/.test(c.tagName) && focusable(c)) return c;
      const inner = c.querySelector && (c.querySelector('input:checked') || c.querySelector('input:not([type="hidden"]), select, textarea, button, [tabindex="0"]'));
      if (focusable(inner)) return inner;
    }
    const w = byId(sc, id);
    if (w) {
      const inner = w.querySelector('input:not([type="hidden"]):not([disabled]), select, textarea, button, [tabindex="0"], canvas[tabindex]');
      if (focusable(inner)) return inner;
      if (!w.hasAttribute('tabindex')) w.setAttribute('tabindex', '-1');
      return w;
    }
    return null;
  }
  function focusField(scope, id) {
    const t = target(scope, id);
    if (!t) return null;
    try { t.focus({ preventScroll: true }); } catch (e) { t.focus(); }
    try { t.scrollIntoView({ block: 'center', behavior: reduced() ? 'auto' : 'smooth' }); } catch (e) { /* old browser */ }
    return t;
  }
  const reduced = () => !!(root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches);

  /**
   * The error summary at the top of a form: items [{id, label, message}]. opts: focus (default true), box (the
   * container; else the form's .efb-errsum, made at the start of scope). Returns the box, or null when empty.
   */
  function summary(scope, items, opts) {
    opts = opts || {};
    items = (items || []).filter((x) => x && x.id);
    let box = opts.box || (scope.querySelector && scope.querySelector('.efb-errsum'));
    if (!items.length) { if (box) hideSummary(scope, box); return null; }
    if (!box) {
      box = D().createElement('div');
      box.className = 'efb efb-errsum';
      scope.insertBefore(box, scope.firstChild);
    }
    const n = items.length;
    const tid = (box.id || ('efb-errsum-' + Math.random().toString(36).slice(2, 8))) + '-t';
    box.setAttribute('tabindex', '-1');
    box.setAttribute('aria-labelledby', tid);
    box.innerHTML = '<div role="alert"><h2 class="efb efb-errsum-t" id="' + esc(tid) + '">' + esc(sumTitle(n)) + '</h2><ul class="efb efb-errsum-l">'
      + items.map((x) => '<li><a href="#' + esc(x.id) + '_" data-efb-field="' + esc(x.id) + '" dir="auto">' + (x.label ? '<bdi>' + esc(plain(x.label)) + '</bdi>: ' : '') + esc(plain(x.message)) + '</a></li>').join('') + '</ul></div>';
    box.classList.remove('d-none');
    box.hidden = false;
    if (!box._efbBound) {
      box._efbBound = true;
      box.addEventListener('click', (e) => {
        const a = e.target && e.target.closest ? e.target.closest('a[data-efb-field]') : null;
        if (!a) return;
        e.preventDefault();
        focusField(scope, a.getAttribute('data-efb-field'));
      });
    }
    if (opts.focus !== false) { try { box.focus({ preventScroll: true }); } catch (e) { box.focus(); } try { box.scrollIntoView({ block: 'start', behavior: reduced() ? 'auto' : 'smooth' }); } catch (e) { /* idem */ } }
    return box;
  }
  const sumTitle = (n) => (n === 1 ? T('a11yErrSumOne', 'There is a problem') : T('a11yErrSumN', '%s problems need your attention').replace('%s', num(n)));
  function hideSummary(scope, box) {
    box = box || (scope && scope.querySelector && scope.querySelector('.efb-errsum'));
    if (!box) return;
    box.innerHTML = '';
    box.classList.add('d-none');
  }
  /** A step was shown: focus on it (no scroll jump) and say "Step 2 of 4". */
  function focusStep(fieldset, text) {
    if (!fieldset) return;
    if (!fieldset.hasAttribute('tabindex')) fieldset.setAttribute('tabindex', '-1');
    try { fieldset.focus({ preventScroll: true }); } catch (e) { fieldset.focus(); }
    if (text) announce(scopeOf(fieldset), text);
  }

  /* ------------------------------------------------------------------ the server's answer */
  /**
   * {success:false, field_id, m, code} or {errors:[{field_id, m, code, label}]} (efb_inline_error() / efb_inline_errors()):
   * every named field shows its sentence, the summary lists them. Returns the number of fields marked.
   */
  function applyServer(scope, data, opts) {
    opts = opts || {};
    if (!data) return 0;
    const list = Array.isArray(data.errors) ? data.errors : (data.field_id ? [{ field_id: data.field_id, m: data.m, code: data.code }] : []);
    const items = [];
    list.forEach((x) => {
      const t = target(scope, x.field_id);
      if (!t) return;
      setError(t, x.m, { html: true });
      items.push({ id: x.field_id, label: x.label || labelOf(scope, x.field_id), message: plain(x.m) });
    });
    if (items.length && opts.summary !== false) summary(scope, items, opts);
    return items.length;
  }
  function labelOf(scope, id) {
    // QA2-020: a repeater copy ({field}_r{n}) is named by its input ("Email, row 2"), as a screen reader reads it.
    const ri = /_r\d+$/.test(id) ? byId(scope, id + '_') : null;
    if (ri && ri.getAttribute('aria-label')) return S(ri.getAttribute('aria-label')).trim();
    const l = byId(scope, id + '_lab') || byId(scope, id + '_labG') || (scope.querySelector && scope.querySelector('label[for="' + id + '_"], label[for="' + id + '"]'));
    return l ? S(l.textContent).replace(/\*\s*$/, '').trim() : '';
  }

  /* ------------------------------------------------------------------ attach(): any form, blur timing */
  /**
   * opts: validate(el) → '' or a message (required), fields (selector, default inputs / selects / textareas
   * with a name or an id), timing 'blur' (default) | 'change', summary (default true), onValid(form, event).
   * Returns {check(): items, reset()}.
   */
  function attach(form, opts) {
    opts = opts || {};
    if (!form || typeof opts.validate !== 'function') return null;
    form.setAttribute('data-efb-inline', '1');
    const sel = opts.fields || 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea';
    const st = new WeakMap(); // pristine | dirty | shown
    const timers = new WeakMap();
    const idOf = (el) => { const h = hostOf(el); return fieldId(h !== el ? h : el); }; // a choice: its group
    const run = (el, quiet) => {
      const msg = S(opts.validate(el));
      if (msg) { setError(el, msg, { quiet: quiet, announce: !quiet, label: labelOf(form, idOf(el)) }); st.set(el, 'shown'); }
      else { clearError(el); if (st.get(el) === 'shown') st.set(el, 'dirty'); }
      return msg;
    };
    const fieldOf = (e) => (e.target && e.target.matches && e.target.matches(sel) ? e.target : null);
    form.addEventListener('input', (e) => {
      const el = fieldOf(e);
      if (!el) return;
      if (st.get(el) !== 'shown') { st.set(el, 'dirty'); return; }
      clearTimeout(timers.get(el));
      timers.set(el, setTimeout(() => run(el, true), 250));
    });
    form.addEventListener(opts.timing === 'change' ? 'change' : 'focusout', (e) => {
      const el = fieldOf(e);
      if (!el || (opts.timing !== 'change' && !st.get(el))) return; // passed with Tab only: no "required" yet
      run(el, false);
    });
    if (opts.timing !== 'change') form.addEventListener('change', (e) => { const el = fieldOf(e); if (el && (el.type === 'radio' || el.type === 'checkbox' || el.tagName === 'SELECT')) { st.set(el, 'dirty'); run(el, false); } });
    const check = () => {
      const items = [];
      const seen = {};
      form.querySelectorAll(sel).forEach((el) => {
        if (!visible(el) || el.disabled) return;
        const key = el.type === 'radio' ? 'r:' + el.name : el;
        if (typeof key === 'string') { if (seen[key]) return; seen[key] = 1; }
        // Checked now: a re-check still waiting from typing would later clear what the server answers for this field.
        clearTimeout(timers.get(el));
        timers.delete(el);
        const msg = run(el, false);
        if (msg) items.push({ id: idOf(el), label: labelOf(form, idOf(el)), message: msg });
      });
      if (opts.summary !== false) summary(form, items);
      else if (items.length) focusField(form, items[0].id);
      return items;
    };
    form.addEventListener('submit', (e) => {
      if (check().length) { e.preventDefault(); return; }
      hideSummary(form);
      if (typeof opts.onValid === 'function') opts.onValid(form, e);
    });
    return { check: check, reset: () => { form.querySelectorAll(sel).forEach((el) => { st.delete(el); clearError(el); }); hideSummary(form); } };
  }

  /* ------------------------------------------------------------------ "leave this page?" */
  const dirty = new Set();
  let bound = false;
  /** Warn before leaving while this form holds answers the visitor typed (trusted input / change only). */
  function watch(form) {
    if (!form || form._efbWatch) return;
    form._efbWatch = true;
    const on = (e) => { if (e.isTrusted && !form._efbClean) dirty.add(form); };
    form.addEventListener('input', on);
    form.addEventListener('change', on);
    if (!bound) {
      bound = true;
      root.addEventListener('beforeunload', (e) => {
        let any = false;
        dirty.forEach((f) => { if (f.isConnected !== false && !f._efbClean) any = true; });
        if (!any) return undefined;
        e.preventDefault();
        e.returnValue = '';
        return '';
      });
    }
  }
  /** The answers were sent (or saved elsewhere): no warning for this form any more. */
  function clean(form, forever) { dirty.delete(form); if (forever && form) form._efbClean = true; }
  const isDirty = (form) => dirty.has(form);

  const api = {
    version: 1,
    features: ['field_error', 'live_region', 'summary', 'focus', 'server_errors', 'timing', 'unsaved', 'digits', 'list'],
    T, num, list, plain, locale,
    setError, clearError, isShown, msgOf, hostOf, fieldId,
    live, announce, target, focusField, focusStep, summary, hideSummary, applyServer, labelOf,
    attach, watch, clean, isDirty, later, busy: () => down,
  };
  root.EFBInline = api;
  if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
