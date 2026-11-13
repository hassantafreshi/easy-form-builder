/*!
 * Easy Form Builder - field rules of text-like fields (F03, docs/feature-gaps/F03-text-validation.fa.md).
 * ES2017, no build step, no dependencies. Works in browsers (window.EfbRules) and Node (module.exports).
 * Every function here has a twin in includes/class-Emsfb-field-rules.php (\Emsfb\Field_Rules); both are
 * tested against the same vectors (tests/fixtures/*-vectors.json, tests/test-field-rules-*.js|php).
 * The browser check is a convenience: the server runs every rule again on submit.
 */
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) { module.exports = api; } else { root.EfbRules = api; }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var KEYS = ['unique', 'unique_scope', 'wmin', 'wmax', 'counter', 'counter_style', 'pattern_b64', 'pattern_case', 'pattern_ascii',
    'email_domain_mode', 'email_domains', 'readonly', 'prefix', 'suffix', 'message_place',
    'pw_confirm', 'pw_meter', 'pw_toggle', 'pw_strength_min', 'pw_confirm_label',
    'msg_format', 'msg_minlen', 'msg_maxlen', 'msg_words', 'msg_pattern', 'msg_unique', 'msg_domain', 'msg_mismatch', 'msg_strength'];
  var MSG_RULES = ['format', 'minlen', 'maxlen', 'words', 'pattern', 'unique', 'domain', 'mismatch', 'strength'];
  var CODES = ['required', 'format', 'minlen', 'maxlen', 'words', 'pattern', 'domain', 'mismatch', 'strength', 'unique'];
  var GATED = ['unique', 'pattern_b64', 'email_domain_mode', 'pw_strength_min'];
  var TYPES = {
    unique: ['text', 'email', 'tel', 'mobile', 'number', 'url', 'postalcode', 'address_line', 'firstName', 'lastName'],
    length: ['text', 'textarea', 'email', 'url', 'tel', 'password', 'postalcode', 'address_line', 'firstName', 'lastName'],
    words: ['text', 'textarea', 'address_line', 'firstName', 'lastName'],
    counter: ['text', 'textarea', 'address_line', 'firstName', 'lastName'],
    pattern: ['text', 'textarea', 'tel', 'email', 'url', 'postalcode', 'address_line', 'firstName', 'lastName'],
    domain: ['email'],
    readonly: ['text', 'textarea', 'email', 'tel', 'url', 'number', 'postalcode', 'address_line', 'firstName', 'lastName'],
    affix: ['text', 'number', 'email', 'url', 'tel', 'postalcode', 'address_line', 'firstName', 'lastName'],
    help: ['text', 'textarea', 'email', 'tel', 'url', 'number', 'password', 'postalcode', 'address_line', 'firstName', 'lastName', 'mobile', 'pdate', 'ardate', 'prcfld'],
    password: ['password']
  };
  var DEFAULTS = {
    tvUnique_: 'This value has already been used.',
    tvMinLen_: '%1$s needs at least %2$s characters.',
    tvMaxLen_: '%1$s can have at most %2$s characters.',
    tvMinWords_: '%1$s needs at least %2$s words.',
    tvMaxWords_: '%1$s can have at most %2$s words.',
    tvPattern_: 'The %s field is not in the expected format.',
    tvDomAllow_: 'Please use an email address from an allowed domain.',
    tvDomDeny_: 'This email domain is not accepted.',
    tvPwMismatch_: 'The two passwords do not match.',
    tvPwWeak_: 'Please choose a stronger password.'
  };

  // #region keys
  function on(f, k, dflt) {
    if (!f || !Object.prototype.hasOwnProperty.call(f, k)) { return !!dflt; }
    var v = f[k];
    return v === 1 || v === '1' || v === true || v === 'true';
  }
  function typeOf(f) { return f && typeof f.type === 'string' ? f.type : ''; }
  function typeOk(f, rule) { return !!TYPES[rule] && TYPES[rule].indexOf(typeOf(f)) !== -1; }
  function int(f, k) {
    var n = f && f[k] !== undefined && f[k] !== null && f[k] !== '' ? parseInt(String(f[k]), 10) : 0;
    return isFinite(n) && n > 0 ? n : 0;
  }
  /** Persian, Arabic-Indic and full-width digits to 0-9 (Masks::normalize_digits). */
  function asciiDigits(s) {
    return String(s).replace(/[\u06F0-\u06F9\u0660-\u0669\uFF10-\uFF19]/g, function (ch) {
      var c = ch.charCodeAt(0);
      return String.fromCharCode(c - (c >= 0xFF10 ? 0xFF10 : (c >= 0x06F0 ? 0x06F0 : 0x0660)) + 48);
    });
  }
  /** A read-only field (Field_Rules::is_readonly). */
  function isReadonly(f) { return !!f && typeOk(f, 'readonly') && on(f, 'readonly') && !on(f, 'disabled'); }
  function masked(f) { return !!(f && typeof f.mask_preset === 'string' && f.mask_preset !== ''); }
  function hasMsg(f, rule) {
    var v = f ? f['msg_' + rule] : undefined;
    return typeof v === 'string' && v.trim() !== '';
  }
  /** The rules the browser checks for this field (plan: ctx.paid). */
  function activeRules(f, ctx) {
    var out = [];
    if (!f) { return out; }
    var m = masked(f);
    if (isReadonly(f)) { out.push('readonly'); }
    if (!m && typeOk(f, 'words') && (int(f, 'wmin') > 0 || int(f, 'wmax') > 0)) { out.push('words'); }
    if (typeOk(f, 'pattern') && partOk(ctx, 'pattern') && fieldPattern(f)) { out.push('pattern'); }
    if (typeOk(f, 'domain') && partOk(ctx, 'domains') && (f.email_domain_mode === 'allow' || f.email_domain_mode === 'deny') && typeof f.email_domains === 'string' && f.email_domains.trim() !== '') { out.push('domain'); }
    if (!m && typeOk(f, 'length') && (int(f, 'milen') > 0 || int(f, 'mlen') > 0) && (out.length || hasMsg(f, 'minlen') || hasMsg(f, 'maxlen'))) { out.unshift('length'); }
    return out;
  }
  /** The counter of a field: {kind: chars|words, style: remaining|used, max} or null (Field_Rules::counter). */
  function counter(f) {
    if (!f || !typeOk(f, 'counter') || masked(f)) { return null; }
    var kind = f.counter === 'chars' || f.counter === 'words' ? f.counter : '';
    if (!kind) { return null; }
    return { kind: kind, style: f.counter_style === 'used' ? 'used' : 'remaining', max: int(f, kind === 'words' ? 'wmax' : 'mlen') };
  }
  /** Whether the site's plan honours a paid part (ctx.parts from ajax_object_efm.field_rules; tests pass {paid: true}). */
  function partOk(ctx, part) {
    if (!ctx) { return false; }
    if (ctx.parts && Object.prototype.hasOwnProperty.call(ctx.parts, part)) { return !!ctx.parts[part]; }
    return !!ctx.paid;
  }
  function hasRules(f, ctx) { return activeRules(f, ctx).length > 0; }
  /** Plain text of a message or label: no tags, one line, at most max code points (Field_Rules::plain). */
  function plain(v, max) {
    if (v === null || v === undefined || typeof v === 'object') { return ''; }
    // " < > never: the builder's save transport and the server's text rules do not keep them.
    var s = String(v).replace(/<[^>]*>?/g, '').replace(/["<>]/g, '').replace(/[\r\n\t]+/g, ' ').replace(/ {2,}/g, ' ').trim();
    return Array.from(s).slice(0, max).join('');
  }
  /** The stored value of one F03 key, as the server keeps it (twin of Field_Rules::sanitize_key). */
  function sanitizeKey(k, v) {
    k = String(k);
    if (k.indexOf('msg_') === 0) { return plain(v, 200); }
    switch (k) {
      case 'wmin':
      case 'wmax': {
        if (v === null || v === undefined || typeof v === 'object' || typeof v === 'boolean') { return ''; }
        var d = asciiDigits(String(v).trim());
        if (!/^\d{1,9}$/.test(d)) { return ''; }
        var n = Math.min(100000, parseInt(d, 10));
        return n > 0 ? String(n) : '';
      }
      case 'counter': return ['none', 'chars', 'words'].indexOf(v) !== -1 ? v : 'none';
      case 'counter_style': return v === 'used' ? 'used' : 'remaining';
      case 'pattern_b64': {
        if (typeof v !== 'string' || v === '' || v.length > 344) { return ''; }
        var src = b64urlDecode(v);
        if (src === null || src === '' || lintRegex(src) !== null) { return ''; }
        return b64urlEncode(src);
      }
      case 'unique':
      case 'readonly': return [1, '1', true, 'true'].indexOf(v) !== -1 ? '1' : '0';
      case 'unique_scope': return 'form';
      case 'email_domain_mode': return ['none', 'allow', 'deny'].indexOf(v) !== -1 ? v : 'none';
      case 'email_domains': return (v === null || v === undefined || typeof v === 'object') ? '' : domainsList(String(v)).value;
      case 'pattern_case': return [1, '1', true, 'true'].indexOf(v) !== -1 ? '1' : '0';
      case 'pattern_ascii': return [0, '0', false, 'false'].indexOf(v) !== -1 ? '0' : '1';
    }
    return plain(v, 200);
  }
  // #endregion keys

  // #region counting
  /** '@n#' (the newline token of textarea values) and CR LF back to one newline. */
  function valueText(v) {
    return String(v === null || v === undefined ? '' : v).replace(/\r\n|\r|@n#/g, '\n');
  }
  /** Code points (Array.from(s).length = mb_strlen). */
  function countChars(v) { return Array.from(valueText(v)).length; }
  /** Word separators (§4.3): whitespace and the Unicode spaces; ZWNJ (U+200C) is not one. */
  var WORD_SEP = /[\t\n\u000B\f\r \u00A0\u1680\u2000-\u200B\u2028\u2029\u202F\u205F\u3000]+/;
  function countWords(v) {
    var s = valueText(v);
    if (s === '') { return 0; }
    return s.split(WORD_SEP).filter(function (x) { return x !== ''; }).length;
  }
  /** A number in the page's language digits, no grouping (the same locales as Form_Gate::locale_digits). */
  function fmtNum(n, locale) {
    var lang = String(locale || 'en').toLowerCase().split(/[_-]/)[0];
    var base = ['fa', 'ps', 'azb', 'haz'].indexOf(lang) !== -1 ? 0x06F0 : (['ar', 'ary', 'arq', 'ckb', 'ur'].indexOf(lang) !== -1 ? 0x0660 : 0);
    var s = String(Math.trunc(Number(n) || 0));
    return base ? s.replace(/[0-9]/g, function (d) { return String.fromCharCode(base + Number(d)); }) : s;
  }
  /**
   * The counter's text for n characters / words: {text, over, zone}. zone: ok | low20 (20 % left) |
   * low10 (10 left) | zero | over; the page announces a change of zone (§4.3), not every key.
   */
  function counterText(c, n, texts, locale) {
    texts = texts || {};
    var words = c.kind === 'words';
    var T = function (k, en) { return typeof texts[k] === 'string' && texts[k] !== '' ? texts[k] : en; };
    var used = T(words ? 'tvWordsUsed' : 'tvCharsUsed', words ? '%1$s of %2$s words' : '%1$s of %2$s characters');
    if (!(c.max > 0)) { return { text: fmtNum(n, locale), over: false, zone: 'ok' }; }
    var left = c.max - n;
    var zone = left < 0 ? 'over' : (left === 0 ? 'zero' : (left <= 10 ? 'low10' : (left <= c.max * 0.2 ? 'low20' : 'ok')));
    var text;
    if (c.style === 'used' || left < 0) {
      text = used.split('%1$s').join(fmtNum(n, locale)).split('%2$s').join(fmtNum(c.max, locale));
    } else {
      text = T(words ? 'tvWordsLeft' : 'tvCharsLeft', words ? '%s words left' : '%s characters left').split('%s').join(fmtNum(left, locale));
    }
    return { text: text, over: left < 0, zone: zone };
  }
  // #endregion counting

  // #region messages
  function fill(t, name, n) {
    return String(t).split('%1$s').join(name).split('%2$s').join(n === undefined || n === null ? '' : String(n)).split('%s').join(name);
  }
  /**
   * The text of a failed rule: the owner's msg_<rule> (custom: true; write it with textContent), else
   * the translated default (texts = efb_var.text) or English. %1$s / %s = the label, %2$s = n.
   */
  function message(f, rule, defaultKey, texts, n) {
    var name = f && typeof f.name === 'string' ? f.name : '';
    if (hasMsg(f, rule)) { return { text: fill(f['msg_' + rule], name, n), custom: true }; }
    var t = texts && typeof texts[defaultKey] === 'string' && texts[defaultKey] !== '' ? texts[defaultKey] : (DEFAULTS[defaultKey] || '');
    return { text: fill(t, name, n), custom: false };
  }
  // #endregion messages

  // #region pattern
  /** UTF-8 → base64url without padding (the stored form of a pattern, pattern_b64). */
  function b64urlEncode(str) {
    var bytes = new TextEncoder().encode(String(str));
    var bin = '';
    for (var i = 0; i < bytes.length; i++) { bin += String.fromCharCode(bytes[i]); }
    var b = typeof btoa === 'function' ? btoa(bin) : Buffer.from(bin, 'binary').toString('base64');
    return b.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }
  /** base64url → UTF-8 text; null when it is not a valid encoding of valid UTF-8. */
  function b64urlDecode(s) {
    s = String(s === null || s === undefined ? '' : s);
    if (!/^[A-Za-z0-9_-]*$/.test(s) || s.length % 4 === 1) { return null; }
    var b = s.replace(/-/g, '+').replace(/_/g, '/');
    while (b.length % 4) { b += '='; }
    try {
      var bin = typeof atob === 'function' ? atob(b) : Buffer.from(b, 'base64').toString('binary');
      var bytes = new Uint8Array(bin.length);
      for (var i = 0; i < bin.length; i++) { bytes[i] = bin.charCodeAt(i); }
      return new TextDecoder('utf-8', { fatal: true }).decode(bytes);
    } catch (e) { return null; }
  }
  function utf8Len(s) { return new TextEncoder().encode(String(s)).length; }

  var RX_MAX_BYTES = 256;
  var RX_PROPS = ['L', 'Lu', 'Ll', 'N', 'Nd', 'P', 'S', 'Z'];
  var RX_SYNTAX = '^$\\.*+?()[]{}|/';
  var RX_CLASS_ESC = 'dDwWsS';
  /**
   * Static check of an owner's pattern (§9.3): only the part of ES2018 (u) and PCRE2 (u, D) that
   * both engines read the same way, and no nested or overlapping repetition (ReDoS). The parser
   * walks the pattern once (no regular expression decides). Returns null or {code, pos} (pos in
   * code points). Twin: Field_Rules::lint_regex().
   */
  function lintRegex(src) {
    src = String(src === null || src === undefined ? '' : src);
    if (utf8Len(src) > RX_MAX_BYTES) { return { code: 'too_long', pos: 0 }; }
    var c = Array.from(src);
    var n = c.length;
    var fail = function (code, pos) { return { code: code, pos: pos }; };
    var isDigit = function (x) { return x >= '0' && x <= '9'; };
    var isHex = function (x) { return /^[0-9A-Fa-f]$/.test(x || ''); };
    // A frame per open group: its branches (each a list of atoms) and whether something inside repeats without end.
    var stack = [{ kind: 'root', branches: [[]], unbounded: false, start: 0 }];
    var unboundedTotal = 0;
    var top = function () { return stack[stack.length - 1]; };
    var branch = function () { var f = top(); return f.branches[f.branches.length - 1]; };
    var i = 0;
    // An escape outside or inside a class: returns {len, atom} or an error object {code}.
    var escape = function (at, inClass) {
      var d = c[at + 1];
      if (d === undefined) { return { err: 'syntax' }; }
      if (RX_CLASS_ESC.indexOf(d) !== -1) { return { len: 2, atom: { t: 'class' } }; }
      if (d === 'b' || d === 'B') { return { len: 2, atom: { t: inClass ? 'lit' : 'assert', ch: '\b' } }; }
      if (d === 'n' || d === 'r' || d === 't' || d === 'f') { return { len: 2, atom: { t: 'lit', ch: '\\' + d } }; }
      if (d === 'p' || d === 'P') {
        if (c[at + 2] !== '{') { return { err: 'escape' }; }
        var close = c.indexOf('}', at + 3);
        if (close === -1) { return { err: 'syntax' }; }
        var name = c.slice(at + 3, close).join('');
        if (RX_PROPS.indexOf(name) === -1) { return { err: 'script_property' }; }
        return { len: close - at + 1, atom: { t: 'class' } };
      }
      if (d === 'x') {
        if (c[at + 2] === '{') { return { err: 'unicode_escape' }; }
        if (isHex(c[at + 2]) && isHex(c[at + 3])) { return { len: 4, atom: { t: 'lit', ch: 'x' + c[at + 2] + c[at + 3] } }; }
        return { err: 'syntax' };
      }
      if (d === 'u') { return { err: 'unicode_escape' }; }
      if (d >= '1' && d <= '9') { return { err: 'backref' }; }
      if (d === 'k') { return { err: 'named_group' }; }
      if (RX_SYNTAX.indexOf(d) !== -1 || (inClass && d === '-')) { return { len: 2, atom: { t: 'lit', ch: d } }; }
      return { err: 'escape' };
    };
    while (i < n) {
      var ch = c[i];
      if (ch === '~') { return fail('tilde', i); }
      if (ch === '\\') {
        var e = escape(i, false);
        if (e.err) { return fail(e.err, i); }
        branch().push(e.atom);
        i += e.len;
        continue;
      }
      if (ch === '[') {
        var j = i + 1;
        if (c[j] === '^') { j++; }
        if (c[j] === ']') { return fail('empty_class', i); }
        var prev = null; // last single literal (for a range)
        var closed = false;
        while (j < n) {
          var x = c[j];
          if (x === ']') { closed = true; break; }
          if (x === '~') { return fail('tilde', j); }
          if (x === '[' && c[j + 1] === ':') { return fail('posix_class', j); }
          var lit = null, len = 1, isClassEsc = false;
          if (x === '\\') {
            if (c[j + 1] === 'D' || c[j + 1] === 'W' || c[j + 1] === 'S') { return fail('class_escape', j); }
            var ce = escape(j, true);
            if (ce.err) { return fail(ce.err, j); }
            len = ce.len;
            if (ce.atom.t === 'class') { isClassEsc = true; } else { lit = ce.atom.ch; }
          } else { lit = x; }
          if (c[j + len] === '-' && c[j + len + 1] !== undefined && c[j + len + 1] !== ']') {
            // a range lo-hi
            var k = j + len + 1, hi = null, hlen = 1, hiClass = false;
            if (c[k] === '\\') {
              var he = escape(k, true);
              if (he.err) { return fail(he.err, k); }
              hlen = he.len;
              if (he.atom.t === 'class') { hiClass = true; } else { hi = he.atom.ch; }
            } else { hi = c[k]; }
            if (isClassEsc || hiClass) { return fail('class_range', j); }
            var lo = lit.length === 1 ? lit.codePointAt(0) : (lit.charAt(0) === 'x' ? parseInt(lit.slice(1), 16) : -1);
            var hv = hi.length === 1 ? hi.codePointAt(0) : (hi.charAt(0) === 'x' ? parseInt(hi.slice(1), 16) : -1);
            if (lit.length > 1 && lit.charAt(0) === '\\') { lo = { n: 10, r: 13, t: 9, f: 12 }[lit.charAt(1)]; }
            if (hi.length > 1 && hi.charAt(0) === '\\') { hv = { n: 10, r: 13, t: 9, f: 12 }[hi.charAt(1)]; }
            if (lo > hv) { return fail('range_order', j); }
            j = k + hlen;
            continue;
          }
          prev = lit;
          j += len;
        }
        if (!closed) { return fail('syntax', i); }
        branch().push({ t: 'class' });
        i = j + 1;
        continue;
      }
      if (ch === '(') {
        var kind = 'group';
        var adv = 1;
        if (c[i + 1] === '?') {
          var q = c[i + 2];
          if (q === ':') { adv = 3; }
          else if (q === '=' || q === '!') { kind = 'look'; adv = 3; }
          else if (q === '<' && (c[i + 3] === '=' || c[i + 3] === '!')) { return fail('lookbehind', i); }
          else if (q === '<' || q === 'P' || q === "'") { return fail('named_group', i); }
          else if (q === '>') { return fail('atomic', i); }
          else if (q !== undefined && /[A-Za-z^-]/.test(q)) { return fail('inline_flag', i); }
          else { return fail('syntax', i); }
        }
        stack.push({ kind: kind, branches: [[]], unbounded: false, start: i });
        i += adv;
        continue;
      }
      if (ch === ')') {
        if (stack.length < 2) { return fail('syntax', i); }
        var fr = stack.pop();
        branch().push({ t: fr.kind, unbounded: fr.unbounded, branches: fr.branches, start: fr.start });
        if (fr.unbounded) { top().unbounded = true; }
        i++;
        continue;
      }
      if (ch === '|') { top().branches.push([]); i++; continue; }
      if (ch === '*' || ch === '+' || ch === '?' || ch === '{') {
        var min = 0, max = Infinity, qlen = 1;
        if (ch === '+') { min = 1; }
        if (ch === '?') { max = 1; }
        if (ch === '{') {
          var end = c.indexOf('}', i);
          var body = end === -1 ? '' : c.slice(i + 1, end).join('');
          var mm = /^(\d{1,4})(,(\d{0,4}))?$/.exec(body);
          if (!mm) { return fail('brace', i); }
          min = parseInt(mm[1], 10);
          max = mm[2] === undefined ? min : (mm[3] === '' ? Infinity : parseInt(mm[3], 10));
          if (min > 1000 || (max !== Infinity && max > 1000)) { return fail('too_large', i); }
          if (max < min) { return fail('range_order', i); }
          qlen = end - i + 1;
        }
        var b = branch();
        var atom = b[b.length - 1];
        if (!atom || atom.q) { return fail('nothing_to_repeat', i); }
        if (atom.t === 'anchor' || atom.t === 'assert' || atom.t === 'look') { return fail('quantified_assertion', i); }
        var next = c[i + qlen];
        if (next === '+') { return fail('possessive', i + qlen); }
        if (next === '?') { qlen++; }
        atom.q = true;
        if (max > 1 && atom.unbounded) { return fail('nested_quantifier', atom.start); }
        if (max === Infinity && atom.branches && atom.branches.length > 1) {
          // (a|aa)+ : every branch a plain different word, none the start of another.
          var words = [];
          for (var bi = 0; bi < atom.branches.length; bi++) {
            var w = '';
            var br = atom.branches[bi];
            if (!br.length) { return fail('alternation', atom.start); }
            for (var ai = 0; ai < br.length; ai++) { if (br[ai].t !== 'lit' || br[ai].q) { return fail('alternation', atom.start); } w += br[ai].ch + '\u0000'; }
            words.push(w);
          }
          for (var wa = 0; wa < words.length; wa++) { for (var wb = 0; wb < words.length; wb++) { if (wa !== wb && words[wb].indexOf(words[wa]) === 0) { return fail('alternation', atom.start); } } }
        }
        if (max === Infinity) { top().unbounded = true; unboundedTotal++; if (unboundedTotal > 4) { return fail('too_complex', i); } }
        i += qlen;
        continue;
      }
      if (ch === '}' || ch === ']') { return fail('brace', i); }
      if (ch === '^' || ch === '$') { branch().push({ t: 'anchor' }); i++; continue; }
      branch().push(ch === '.' ? { t: 'class' } : { t: 'lit', ch: ch });
      i++;
    }
    if (stack.length > 1) { return fail('syntax', stack[stack.length - 1].start); }
    try { new RegExp('^(?:' + src + ')$', 'u'); } catch (e) { return fail('syntax', 0); }
    return null;
  }

  /** The text a pattern is matched against: one line, F01's normalizer (digits only with ascii). */
  function patternSubject(v, ascii) {
    var s = valueText(v);
    if (ascii) { s = asciiDigits(s); }
    s = s.replace(/[\u200B-\u200F\u202A-\u202E\u2066-\u2069\uFEFF\u00AD]/g, '');
    s = s.replace(/[\u00A0\u2007\u202F\u3000\u2028\u2029]/g, ' ');
    return s.replace(/[\t\n\v\f\r ]+/g, ' ').trim();
  }
  var rxCache = {};
  /** The compiled pattern of a field, or null (none, refused by the lint, or not decodable). */
  function fieldPattern(f) {
    if (!f || typeof f.pattern_b64 !== 'string' || f.pattern_b64 === '') { return null; }
    var key = f.pattern_b64 + '|' + (on(f, 'pattern_case') ? 'i' : '');
    if (Object.prototype.hasOwnProperty.call(rxCache, key)) { return rxCache[key]; }
    var src = b64urlDecode(f.pattern_b64);
    var re = null;
    if (src !== null && src !== '' && lintRegex(src) === null) {
      try { re = new RegExp('^(?:' + src + ')$', 'u' + (on(f, 'pattern_case') ? 'i' : '')); } catch (e) { re = null; }
    }
    rxCache[key] = re;
    return re;
  }
  /** true / false, or null when the browser leaves it to the server (more than 1000 characters). */
  function checkPattern(f, v) {
    var re = fieldPattern(f);
    if (!re) { return true; }
    var s = patternSubject(v, on(f, 'pattern_ascii', true));
    if (Array.from(s).length > Math.max(1000, int(f, 'mlen'))) { return null; }
    return re.test(s);
  }
  // #endregion pattern

  // #region domains
  /** A host in ASCII (punycode, the URL parser's IDNA) and lower case; '' when it cannot be. */
  function asciiHost(h) {
    h = String(h || '').trim().toLowerCase();
    if (h === '' || !/[^\x00-\x7F]/.test(h)) { return h; }
    try { return new URL('http://' + h).hostname.toLowerCase(); } catch (e) { return ''; }
  }
  /** The owner's list as the server keeps it (Field_Rules::domains_list): {value, kept, dropped}. */
  function domainsList(raw) {
    var items = String(raw || '').toLowerCase().split(/[\s,;]+/).filter(function (x) { return x !== ''; });
    var keep = [];
    var dropped = 0;
    items.forEach(function (it) {
      var at = it.lastIndexOf('@');
      var local = at === -1 ? '' : it.slice(0, at + 1);
      var host = at === -1 ? it : it.slice(at + 1);
      var star = host.indexOf('*.') === 0 ? '*.' : '';
      host = asciiHost(star ? host.slice(2) : host);
      var p = local + star + host;
      if (host === '' || /%[0-9a-f]{2}/.test(p) || !/^(\*@|[a-z0-9._%+-]+@)?(\*\.)?[a-z0-9.-]+$/.test(p) || host.indexOf('..') !== -1 || host.charAt(0) === '.' || host.charAt(host.length - 1) === '.'
        || (local === '*@' && star) || keep.indexOf(p) !== -1 || keep.length >= 200 || keep.concat([p]).join(',').length > 2000) { dropped++; return; }
      keep.push(p);
    });
    return { value: keep.join(','), kept: keep.length, dropped: dropped };
  }
  /** Whether an address matches one of the patterns (Field_Rules::match_domain). */
  function matchDomain(patterns, email) {
    email = String(email || '').trim().toLowerCase();
    var at = email.lastIndexOf('@');
    if (at === -1) { return false; }
    var local = email.slice(0, at);
    var domain = asciiHost(email.slice(at + 1));
    if (domain === '') { return false; }
    var addr = local + '@' + domain;
    var list = Array.isArray(patterns) ? patterns : String(patterns || '').split(',');
    for (var i = 0; i < list.length; i++) {
      var p = String(list[i]).trim();
      if (p === '') { continue; }
      if (p.indexOf('*@') === 0) { if (domain === p.slice(2)) { return true; } continue; }
      if (p.indexOf('@') !== -1) { if (addr === p) { return true; } continue; }
      if (p.indexOf('*.') === 0) { var suf = p.slice(1); if (domain.length > suf.length && domain.slice(-suf.length) === suf) { return true; } continue; }
      if (domain === p) { return true; }
    }
    return false;
  }
  /** F02 hook: the field's msg_mismatch (custom) or the default (Field_Rules::mismatch_message). */
  function mismatchMessage(f, dflt) {
    return hasMsg(f, 'mismatch') ? fill(f.msg_mismatch, f && typeof f.name === 'string' ? f.name : '') : dflt;
  }
  // #endregion domains

  // #region unique
  /**
   * The comparison key of a value (Unique_Values::key): e-mail lower case after the e-mail normalizer,
   * a number as a number, any other text normalized, lower case and NFC (a masked field's key is the
   * F01 canonical value, which the server makes). The check itself is the server's (no public lookup in v1).
   */
  function uniqueKey(f, v) {
    var s = valueText(v);
    var type = typeOf(f);
    if (type === 'email') {
      s = asciiDigits(s).replace(/[\uFF20]/g, '@').replace(/[\uFF0E\u3002\u066B\u06D4]/g, '.').replace(/[\u00A0\u061C\u200B-\u200F\u202A-\u202E\u2060-\u2069\uFEFF]/g, '');
      return s.trim().toLowerCase();
    }
    var n = asciiDigits(s).replace(/[\u200B-\u200F\u202A-\u202E\u2066-\u2069\uFEFF\u00AD]/g, '').replace(/[\u00A0\u2007\u202F\u3000]/g, ' ')
      .replace(/\u066B/g, '.').replace(/[\u066C\u060C]/g, ',').replace(/[\u2010-\u2015\u2212\uFE58\uFE63\uFF0D]/g, '-')
      .replace(/\uFF0B/g, '+').replace(/\uFF0F/g, '/').replace(/\uFF1A/g, ':').replace(/[\t\n\v\f\r ]+/g, ' ').trim();
    if (type === 'number' && n !== '' && isFinite(Number(n)) && /^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$/.test(n)) { return String(Number(n)); }
    return n.normalize('NFC').toLowerCase();
  }
  // #endregion unique

  // #region check
  /**
   * The first failing rule of a value, in the server's order (§4.1), or null.
   * Returns {code, rule, key, n}; message(f, rule, key, texts, n) gives its text.
   * ctx: {paid, confirm (second password), emails, formName}. An empty value runs no rule.
   */
  function firstError(f, v, ctx) {
    ctx = ctx || {};
    var s = valueText(v);
    if (s.trim() === '') { return null; }
    var rules = activeRules(f, ctx);
    if (rules.indexOf('length') !== -1) {
      var n = countChars(s);
      var mi = int(f, 'milen'), mx = int(f, 'mlen');
      if (mi > 0 && n < mi) { return { code: 'minlen', rule: 'minlen', key: 'tvMinLen_', n: mi }; }
      if (mx > 0 && n > mx) { return { code: 'maxlen', rule: 'maxlen', key: 'tvMaxLen_', n: mx }; }
    }
    if (rules.indexOf('words') !== -1) {
      var w = countWords(s);
      var wi = int(f, 'wmin'), wx = int(f, 'wmax');
      if (wi > 0 && w < wi) { return { code: 'words', rule: 'words', key: 'tvMinWords_', n: wi }; }
      if (wx > 0 && w > wx) { return { code: 'words', rule: 'words', key: 'tvMaxWords_', n: wx }; }
    }
    if (rules.indexOf('pattern') !== -1 && checkPattern(f, s) === false) { return { code: 'pattern', rule: 'pattern', key: 'tvPattern_' }; }
    if (rules.indexOf('domain') !== -1) {
      var hit = matchDomain(f.email_domains, s);
      if (f.email_domain_mode === 'allow' && !hit) { return { code: 'domain', rule: 'domain', key: 'tvDomAllow_' }; }
      if (f.email_domain_mode === 'deny' && hit) { return { code: 'domain', rule: 'domain', key: 'tvDomDeny_' }; }
    }
    return null;
  }
  // #endregion check

  return {
    KEYS: KEYS, MSG_RULES: MSG_RULES, CODES: CODES, GATED: GATED, TYPES: TYPES, DEFAULTS: DEFAULTS,
    on: on, typeOk: typeOk, activeRules: activeRules, hasRules: hasRules, plain: plain, sanitizeKey: sanitizeKey,
    valueText: valueText, countChars: countChars, countWords: countWords, counter: counter, asciiDigits: asciiDigits, counterText: counterText, fmtNum: fmtNum, partOk: partOk,
    b64urlEncode: b64urlEncode, b64urlDecode: b64urlDecode, lintRegex: lintRegex, patternSubject: patternSubject, checkPattern: checkPattern, fieldPattern: fieldPattern, uniqueKey: uniqueKey,
    isReadonly: isReadonly, asciiHost: asciiHost, domainsList: domainsList, matchDomain: matchDomain, mismatchMessage: mismatchMessage,
    message: message, firstError: firstError
  };
});
