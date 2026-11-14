/**
 * F10-C: the visitor's rich text editor on a textarea (rich_text), no build step, no library
 * (docs/feature-gaps/F10-layout-special-fields.fa.md §4.3).
 *
 *  - EFBRichCore (no DOM; module.exports for the Node tests): clean(html) keeps only the allow-list of the server
 *    (\Emsfb\Layout::rich_allowed_html(): p br strong b em i u ul ol li blockquote h3 h4, and a with an http(s) or
 *    mailto href; no attribute else, no style, class, id, data-*, image or frame); the content of script / style /
 *    head / svg … is dropped; any other tag (span, font, Word's o:p …) leaves its text. plain(html) is the text
 *    (block ends and line breaks as new lines) and plainLength() its length in code points (milen / mlen count it).
 *  - EFBRich (the page): mount(textarea) puts a toolbar (role=toolbar, roving tabindex, aria-pressed) and a
 *    contenteditable box (role=textbox, aria-multiline, dir=auto) before the textarea, which stays the field's
 *    value (hidden, cleaned HTML) so every check, draft and submit path works as before. Without JavaScript the
 *    plain textarea is shown. Paste is cleaned the same way. Ctrl/⌘+B / I / U; Tab leaves the box.
 *
 * The server cleans again (wp_kses with the same list) and every display cleans again (efbRichSafeHtml in
 * response-viewer-efb.js uses EFBRichCore when it is loaded, else plain text).
 */
(function (root) {
  'use strict';

  var ALLOWED = { p: 1, br: 1, strong: 1, b: 1, em: 1, i: 1, u: 1, ul: 1, ol: 1, li: 1, blockquote: 1, h3: 1, h4: 1, a: 1 };
  /** Tags turned into an allowed one (the browser's own paragraphs, other heading levels). */
  var MAP = { div: 'p', h1: 'h3', h2: 'h3', h5: 'h4', h6: 'h4' };
  /** Tags whose content is dropped with them. */
  var DROP = { script: 1, style: 1, xml: 1, head: 1, title: 1, template: 1, object: 1, iframe: 1, noscript: 1, svg: 1, math: 1, select: 1, textarea: 1, button: 1, embed: 1, applet: 1 };
  var VOID = { br: 1, img: 1, hr: 1, input: 1, meta: 1, link: 1, wbr: 1, col: 1, area: 1, base: 1, source: 1, track: 1, param: 1, embed: 1 };
  var TOOLBARS = { basic: ['bold', 'italic', 'underline', 'bullets', 'numbers', 'clear'], full: ['bold', 'italic', 'underline', 'bullets', 'numbers', 'link', 'quote', 'heading', 'clear'] };

  function str(v) { return v === null || v === undefined ? '' : String(v); }
  function esc(s) { return str(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
  var NAMED = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', '#039': "'" };
  function decode(s) {
    return str(s).replace(/&(#x[0-9a-f]{1,6}|#[0-9]{1,7}|[a-z][a-z0-9]{1,8});/gi, function (m, e) {
      var k = e.toLowerCase();
      if (k.charAt(0) === '#') {
        var n = k.charAt(1) === 'x' ? parseInt(k.slice(2), 16) : parseInt(k.slice(1), 10);
        if (!n || n > 0x10FFFF || (n >= 0xD800 && n <= 0xDFFF)) return '';
        try { return String.fromCodePoint(n); } catch (x) { return ''; }
      }
      return Object.prototype.hasOwnProperty.call(NAMED, k) ? NAMED[k] : m;
    });
  }
  /** An address a link may keep: http(s) or mailto only (control characters and spaces inside the scheme removed first). */
  function safeHref(raw) {
    var u = decode(raw).replace(/[\u0000- \u007f-\u009f]+/g, function (m, i) { return i === 0 ? '' : m; }).trim();
    var probe = u.replace(/[\u0000- \u007f-\u009f]/g, '');
    if (!/^(https?:\/\/|mailto:)/i.test(probe)) return '';
    if (/^https?:\/\/[^\s]+$/i.test(u) || /^mailto:[^\s]+$/i.test(u)) return u;
    return '';
  }
  var TOKEN = /<!--[\s\S]*?(?:-->|$)|<!\[CDATA\[[\s\S]*?(?:\]\]>|$)|<\/?([a-zA-Z][a-zA-Z0-9:_-]*)((?:[^>"']|"[^"]*"|'[^']*')*)>|<[!?][^>]*>/g;

  /**
   * The allowed subset of html as a string (the same list as the server). opts.display adds rel / target to links
   * (the viewer); without it a link keeps only its href (the server writes rel and target).
   */
  function clean(html, opts) {
    opts = opts || {};
    var s = str(html);
    var out = '';
    var stack = [];
    var drop = 0;
    var dropTag = '';
    var last = 0;
    var m;
    TOKEN.lastIndex = 0;
    var text = function (t) { if (!drop && t) out += esc(decode(t)); };
    while ((m = TOKEN.exec(s)) !== null) {
      text(s.slice(last, m.index));
      last = TOKEN.lastIndex;
      if (!m[1]) continue; // comment, CDATA, doctype, processing instruction
      var closing = m[0].charAt(1) === '/';
      var name = m[1].toLowerCase();
      if (drop) {
        if (name === dropTag) drop += closing ? -1 : (VOID[name] ? 0 : 1);
        continue;
      }
      if (DROP[name]) { if (!closing && !/\/\s*$/.test(m[2] || '')) { drop = 1; dropTag = name; } continue; }
      var tag = MAP[name] || name;
      if (!ALLOWED[tag]) continue; // span, font, o:p, img …: the tag goes, its text stays
      if (tag === 'br') { if (!closing) out += '<br>'; continue; }
      if (closing) {
        var at = -1;
        for (var k = stack.length - 1; k >= 0; k--) if (stack[k].t === tag) { at = k; break; }
        if (at === -1) continue;
        while (stack.length > at) { var e = stack.pop(); if (!e.skip) out += '</' + e.t + '>'; }
        continue;
      }
      if (tag === 'a') {
        var hm = /(?:^|\s)href\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'>]+))/i.exec(m[2] || '');
        var href = hm ? safeHref(hm[1] !== undefined ? hm[1] : (hm[2] !== undefined ? hm[2] : hm[3])) : '';
        if (!href || stack.some(function (x) { return x.t === 'a'; })) { stack.push({ t: 'a', skip: true }); continue; }
        out += '<a href="' + esc(href) + '"' + (opts.display ? ' rel="nofollow ugc noopener" target="_blank"' : '') + '>';
        stack.push({ t: 'a' });
        continue;
      }
      // A block inside a paragraph closes the paragraph first (what a browser does), so nesting stays valid.
      if (tag !== 'li' && /^(p|ul|ol|blockquote|h3|h4)$/.test(tag)) {
        while (stack.length && /^(p|h3|h4)$/.test(stack[stack.length - 1].t)) { var c = stack.pop(); if (!c.skip) out += '</' + c.t + '>'; }
      }
      out += '<' + tag + '>';
      stack.push({ t: tag });
    }
    text(s.slice(last));
    while (stack.length) { var e2 = stack.pop(); if (!e2.skip) out += '</' + e2.t + '>'; }
    return out.replace(/<(p|h3|h4|li|blockquote|strong|b|em|i|u)><\/\1>/g, '').trim();
  }
  /** The words of rich text: block ends and line breaks are new lines, every tag goes, entities read. */
  function plain(html) {
    var s = str(html).replace(/<\s*br\s*\/?>/gi, '\n').replace(/<\/?\s*(p|ul|ol|li|h3|h4|blockquote|div)\b[^>]*>/gi, '\n').replace(/<[^>]*>/g, '');
    return decode(s).replace(/\u00a0/g, ' ').replace(/[ \t]+\n/g, '\n').replace(/\n[ \t]*/g, '\n').replace(/\n{2,}/g, '\n').trim();
  }
  function plainLength(html) { return Array.from(plain(html)).length; }

  var core = { ALLOWED: ALLOWED, TOOLBARS: TOOLBARS, clean: clean, plain: plain, plainLength: plainLength, safeHref: safeHref, esc: esc, decode: decode };

  /* ================================================================== page */

  var page = (function () {
    function doc() { return typeof document !== 'undefined' ? document : null; }
    function ev() { return (typeof efb_var !== 'undefined' && efb_var) || root.efb_var || (typeof ajax_object_efm !== 'undefined' ? ajax_object_efm : null) || {}; }
    function T(k, fb) { var e = ev(); return (e.text && e.text[k]) || fb; }
    var CMD = {
      bold: { cmd: 'bold', icon: 'bi-type-bold', key: 'rteBold', fb: 'Bold', state: true },
      italic: { cmd: 'italic', icon: 'bi-type-italic', key: 'rteItalic', fb: 'Italic', state: true },
      underline: { cmd: 'underline', icon: 'bi-type-underline', key: 'rteUnderline', fb: 'Underline', state: true },
      bullets: { cmd: 'insertUnorderedList', icon: 'bi-list-ul', key: 'rteBullets', fb: 'Bulleted list', state: true },
      numbers: { cmd: 'insertOrderedList', icon: 'bi-list-ol', key: 'rteNumbers', fb: 'Numbered list', state: true },
      link: { cmd: 'createLink', icon: 'bi-link-45deg', key: 'rteLink', fb: 'Link' },
      quote: { cmd: 'formatBlock', arg: 'blockquote', icon: 'bi-quote', key: 'rteQuote', fb: 'Quote' },
      heading: { cmd: 'formatBlock', arg: 'h3', icon: 'bi-type-h3', key: 'rteHeading', fb: 'Heading' },
      clear: { cmd: 'removeFormat', icon: 'bi-eraser', key: 'rteClear', fb: 'Clear formatting' },
    };
    function exec(c, arg) { try { return doc().execCommand(c, false, arg); } catch (e) { return false; } }
    function sync(ta, box, fire) {
      var html = clean(box.innerHTML);
      if (plainLength(html) === 0) html = '';
      // "changed" is against the last value the form was told about (typing writes the textarea before blur).
      var changed = (ta._efbRteSent === undefined ? ta.defaultValue : ta._efbRteSent) !== html;
      ta.value = html;
      box.classList.toggle('efb-rte-empty', html === '');
      try { ta.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) { /* old browser */ } // counters
      if (fire && changed) {
        ta._efbRteSent = html;
        try { ta.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) { /* old browser */ } // the form's own handler
      }
    }
    function states(bar) {
      bar.querySelectorAll('[data-rte-cmd]').forEach(function (b) {
        var c = CMD[b.getAttribute('data-rte-cmd')];
        if (!c || !c.state) return;
        var on = false;
        try { on = doc().queryCommandState(c.cmd); } catch (e) { on = false; }
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }
    /** Puts the editor on one textarea (data-rte="1"); a textarea is set up once. Returns the editable box. */
    function mount(ta) {
      var d = doc();
      if (!d || !ta || ta.tagName !== 'TEXTAREA' || ta.dataset.rteMounted === '1') return null;
      ta.dataset.rteMounted = '1';
      var id = ta.id || ('efbrte' + Math.random().toString(36).slice(2, 8));
      var tb = TOOLBARS[ta.getAttribute('data-rte-tb')] || TOOLBARS.basic;
      var wrap = d.createElement('div');
      wrap.className = 'efb efb-rte';
      var bar = d.createElement('div');
      bar.className = 'efb efb-rte-bar';
      bar.setAttribute('role', 'toolbar');
      bar.setAttribute('aria-label', T('rteToolbar', 'Formatting'));
      bar.setAttribute('aria-controls', id + 'rte');
      tb.forEach(function (k, i) {
        var c = CMD[k];
        var b = d.createElement('button');
        b.type = 'button';
        b.className = 'efb efb-rte-btn';
        b.setAttribute('data-rte-cmd', k);
        b.setAttribute('aria-label', T(c.key, c.fb));
        b.title = T(c.key, c.fb);
        b.tabIndex = i === 0 ? 0 : -1;
        if (c.state) b.setAttribute('aria-pressed', 'false');
        var ic = d.createElement('i');
        ic.className = 'efb bi ' + c.icon;
        ic.setAttribute('aria-hidden', 'true');
        b.appendChild(ic);
        bar.appendChild(b);
      });
      var box = d.createElement('div');
      box.id = id + 'rte';
      box.className = 'efb efb-rte-box form-control ' + (ta.className.match(/\b(h-[a-z-]+efb|rounded-\d|efb-square|border-[a-z]+|text-[A-Za-z]+)\b/g) || []).join(' ');
      box.setAttribute('role', 'textbox');
      box.setAttribute('aria-multiline', 'true');
      box.setAttribute('dir', 'auto');
      box.contentEditable = ta.disabled ? 'false' : 'true';
      var lab = d.getElementById(String(ta.id || '').replace(/_$/, '') + '_lab');
      if (lab) box.setAttribute('aria-labelledby', lab.id); else if (ta.getAttribute('aria-label')) box.setAttribute('aria-label', ta.getAttribute('aria-label'));
      if (ta.getAttribute('aria-describedby')) box.setAttribute('aria-describedby', ta.getAttribute('aria-describedby'));
      if (ta.required || ta.getAttribute('aria-required') === 'true') box.setAttribute('aria-required', 'true');
      if (ta.placeholder) box.setAttribute('data-placeholder', ta.placeholder);
      box.innerHTML = clean(ta.value); // only the allow-list ever reaches innerHTML
      wrap.appendChild(bar);
      wrap.appendChild(box);
      ta.parentNode.insertBefore(wrap, ta);
      ta.classList.add('d-none', 'efb-rte-source');
      ta.tabIndex = -1;
      ta.setAttribute('aria-hidden', 'true');
      ta._efbRte = box;
      box.classList.toggle('efb-rte-empty', clean(ta.value) === '');
      try { exec('defaultParagraphSeparator', 'p'); } catch (e) { /* older engines */ }
      var timer = null;
      box.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { sync(ta, box, true); }, 350); sync(ta, box, false); });
      box.addEventListener('blur', function () { clearTimeout(timer); sync(ta, box, true); });
      box.addEventListener('keydown', function (e) {
        if (!(e.ctrlKey || e.metaKey) || e.altKey) return;
        var k = String(e.key || '').toLowerCase();
        var map = { b: 'bold', i: 'italic', u: 'underline' };
        if (map[k]) { e.preventDefault(); exec(CMD[map[k]].cmd); states(bar); sync(ta, box, false); }
      });
      box.addEventListener('paste', function (e) {
        var cd = e.clipboardData;
        if (!cd) return;
        e.preventDefault();
        var html = cd.getData('text/html');
        if (html) exec('insertHTML', clean(html));
        else exec('insertText', cd.getData('text/plain'));
        sync(ta, box, false);
      });
      box.addEventListener('drop', function (e) { e.preventDefault(); });
      ['keyup', 'mouseup', 'focus'].forEach(function (t) { box.addEventListener(t, function () { states(bar); }); });
      bar.addEventListener('mousedown', function (e) { if (e.target.closest('button')) e.preventDefault(); }); // keep the selection
      bar.addEventListener('click', function (e) {
        var b = e.target.closest('[data-rte-cmd]');
        if (!b || ta.disabled) return;
        var c = CMD[b.getAttribute('data-rte-cmd')];
        box.focus();
        if (c.cmd === 'createLink') {
          var u = typeof root.prompt === 'function' ? root.prompt(T('rteLinkPrompt', 'Link address (https://…)'), 'https://') : '';
          var ok = safeHref(u || '');
          if (!ok) { if (u && u !== 'https://' && typeof alert_message_efb === 'function') alert_message_efb('', T('rteLinkBad', 'Only web (http, https) and e-mail links are allowed.'), 6, 'warning'); return; }
          exec('createLink', ok);
        } else if (c.cmd === 'removeFormat') {
          exec('removeFormat'); exec('unlink');
        } else {
          exec(c.cmd, c.arg ? '<' + c.arg + '>' : undefined);
        }
        states(bar);
        sync(ta, box, true);
      });
      bar.addEventListener('keydown', function (e) {
        var btns = Array.prototype.slice.call(bar.querySelectorAll('button'));
        var i = btns.indexOf(d.activeElement);
        if (i === -1) return;
        var rtl = getComputedStyle(bar).direction === 'rtl';
        var n = -1;
        if (e.key === 'ArrowRight') n = rtl ? i - 1 : i + 1;
        else if (e.key === 'ArrowLeft') n = rtl ? i + 1 : i - 1;
        else if (e.key === 'Home') n = 0;
        else if (e.key === 'End') n = btns.length - 1;
        if (n === -1) return;
        e.preventDefault();
        n = (n + btns.length) % btns.length;
        btns.forEach(function (b, j) { b.tabIndex = j === n ? 0 : -1; });
        btns[n].focus();
      });
      return box;
    }
    /** Every rich textarea under root (the page at load, a repeater row added later). */
    function mountIn(rootEl) {
      var r = rootEl || doc();
      if (!r || !r.querySelectorAll) return 0;
      var n = 0;
      r.querySelectorAll('textarea[data-rte="1"]').forEach(function (ta) { if (mount(ta)) n++; });
      return n;
    }
    /** The box shows the textarea's value again (a draft or an offline copy put back). */
    function refresh(ta) {
      if (!ta || !ta._efbRte) return;
      ta._efbRte.innerHTML = clean(ta.value);
      ta._efbRte.classList.toggle('efb-rte-empty', clean(ta.value) === '');
    }
    return { mount: mount, mountIn: mountIn, refresh: refresh };
  })();

  root.EFBRichCore = core;
  root.EFBRich = page;
  if (typeof module === 'object' && module.exports) module.exports = core;
})(typeof window !== 'undefined' ? window : globalThis);
