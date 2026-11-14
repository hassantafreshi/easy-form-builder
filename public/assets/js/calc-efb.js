/*!
 * Easy Form Builder - numbers and calculations (F04, docs/feature-gaps/F04-number-calculation.fa.md).
 *
 * The pure part (normalize, tokenize, parse, evaluate, run, roundHalfAway, toFixed, number parsing,
 * step / decimals / range checks, display format) is the twin of \Emsfb\Calc
 * (includes/class-Emsfb-calc.php): both give the same answer, byte for byte, for every vector of
 * docs/feature-gaps/reference/calc-test-vectors.json (tests/test-calc-engine.js|php,
 * tests/test-calc-parity.js). A formula is read by a hand-written tokenizer into a tree of plain
 * objects; the evaluator walks that tree. No string is ever executed: no eval, no Function.
 *
 * Grammar (spec §6.1): expr := term (('+'|'-') term)* ; term := unary (('*'|'/') unary)* ;
 * unary := ('-'|'+') unary | primary ; primary := NUMBER | ref | call | '(' expr ')' ;
 * call := IDENT '(' [expr (',' expr)*] ')' ; ref := '{' ['field:'] ID ['.' PART] '}'.
 * No < > " % characters: comparisons are gt() lt() eq() ..., the remainder is mod().
 *
 * Limits: 2000 characters, 512 tokens, nesting depth 32, 100 references; |result| <= 1e15 and the
 * scaled result below 2^53; an error is a code, never an exception.
 *
 * ES5, no dependencies. Browser: window.EFBCalc; Node: module.exports.
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module === 'object' && module.exports) { module.exports = api; } else { root.EFBCalc = api; }
})(typeof self !== 'undefined' ? self : this, function (root) {
  'use strict';

  var MAX_LEN = 2000, MAX_TOKENS = 512, MAX_DEPTH = 32, MAX_REFS = 100, MAX_ARGS = 200, MAX_ABS = 1e15, MAX_SAFE = 9007199254740992;
  var EPS = 1e-9;
  var POW10 = [1, 10, 100, 1000, 10000, 100000, 1000000];
  var FUNCS = {
    round: [1, 2], ceil: [1, 2], floor: [1, 2], min: [1, MAX_ARGS], max: [1, MAX_ARGS], abs: [1, 1], 'if': [3, 3],
    sum: [1, MAX_ARGS], avg: [1, MAX_ARGS], count: [1, MAX_ARGS], gt: [2, 2], gte: [2, 2], lt: [2, 2], lte: [2, 2],
    eq: [2, 2], neq: [2, 2], and: [1, MAX_ARGS], or: [1, MAX_ARGS], not: [1, 1], mod: [2, 2], pow: [2, 2]
  };
  var LIST_FUNCS = { sum: 1, avg: 1, min: 1, max: 1, count: 1 };
  var PARTS = { count: 1, price: 1, calc: 1, value: 1, lo: 1, hi: 1 };
  /** Field types a formula may not read (spec §6.3). */
  var FORBIDDEN_TYPES = ['password', 'file', 'dadfile', 'esign', 'maps', 'html', 'audio_recorder', 'video_recorder', 'screen_recorder'];

  function str(v) { return v === null || v === undefined ? '' : String(v); }

  /* ------------------------------------------------------------------ digits */

  /** Persian, Arabic-Indic and full-width digits to ASCII; Arabic decimal / thousands marks and comma. */
  function normalizeDigits(s) {
    return str(s).replace(/[\u06F0-\u06F9\u0660-\u0669\uFF10-\uFF19]/g, function (ch) {
      var c = ch.charCodeAt(0);
      if (c >= 0xFF10) return String.fromCharCode(c - 0xFF10 + 48);
      if (c >= 0x06F0) return String.fromCharCode(c - 0x06F0 + 48);
      return String.fromCharCode(c - 0x0660 + 48);
    });
  }

  /** A formula as the tokenizer reads it: local digits, ٫ = '.', ، = ','; bidi marks removed. */
  function normalizeFormula(s) {
    return normalizeDigits(s).replace(/\u066B/g, '.').replace(/[\u060C\uFF0C]/g, ',').replace(/[\u200B-\u200F\u202A-\u202E\u2066-\u2069\uFEFF]/g, '')
      .replace(/[\u2212\u2010-\u2015\uFE63\uFF0D]/g, '-').replace(/\u00D7/g, '*').replace(/\u00F7/g, '/');
  }

  /* ------------------------------------------------------------------ tokenizer and parser */

  function err(code, pos, near) { return { ok: false, code: code, pos: pos === undefined ? -1 : pos, near: near === undefined ? '' : near }; }

  var WS = ' \t\n\r\u000B\u000C\u00A0\u2000\u2001\u2002\u2003\u2004\u2005\u2006\u2007\u2008\u2009\u200A\u2028\u2029\u202F\u205F\u3000';

  /** The code points of a text (positions in errors count code points, as PHP does). */
  function chars(s) { return s === '' ? [] : s.match(/[\uD800-\uDBFF][\uDC00-\uDFFF]|[\s\S]/g); }

  function isDigit(c) { return c >= '0' && c <= '9'; }
  function isAlpha(c) { return (c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z'); }

  function tokenize(src) {
    var C = chars(normalizeFormula(src));
    var n = C.length;
    if (n > MAX_LEN) return err('calcLong', MAX_LEN, '');
    var out = [], i = 0, j, v;
    var slice = function (a, b) { return C.slice(a, Math.min(b, n)).join(''); };
    while (i < n) {
      var c = C[i];
      if (WS.indexOf(c) !== -1) { i++; continue; }
      if (out.length >= MAX_TOKENS) return err('calcTokens', i, c);
      if (isDigit(c)) {
        j = i;
        while (j < n && isDigit(C[j])) j++;
        if (C[j] === '.' && j + 1 < n && isDigit(C[j + 1])) { j++; while (j < n && isDigit(C[j])) j++; }
        var after = j < n ? C[j] : '';
        if (j - i > 30 || after === '.' || isAlpha(after) || after === '_') return err('calcSyntax', i, slice(i, i + 12));
        out.push({ t: 'num', v: slice(i, j), p: i });
        i = j;
        continue;
      }
      if (c === '{') {
        j = i + 1;
        while (j < n && C[j] !== '}' && j - i <= 60) j++;
        var inner = j < n && C[j] === '}' ? slice(i + 1, j) : null;
        var m = inner === null ? null : /^(?:field:)?([A-Za-z0-9_-]{1,40})(?:\.([A-Za-z0-9_-]{1,40}))?$/.exec(inner);
        if (!m) return err('calcSyntax', i, slice(i, inner === null ? i + 12 : j + 1));
        out.push({ t: 'ref', id: m[1], part: m[2] === undefined ? '' : m[2], raw: '{' + inner + '}', p: i });
        i = j + 1;
        continue;
      }
      if (isAlpha(c)) {
        j = i;
        while (j < n && (isAlpha(C[j]) || C[j] === '_')) j++;
        v = slice(i, j);
        if (j - i > 20) return err('calcSyntax', i, slice(i, i + 12));
        out.push({ t: 'id', v: v.toLowerCase(), p: i });
        i = j;
        continue;
      }
      if ('+-*/(),'.indexOf(c) !== -1) { out.push({ t: c, p: i }); i++; continue; }
      return err('calcSyntax', i, c);
    }
    return { ok: true, tokens: out, len: n };
  }

  function parse(src) {
    var tk = tokenize(src);
    if (!tk.ok) return tk;
    var toks = tk.tokens, pos = 0, refs = [], depth = 0, failure = null;
    function peek() { return toks[pos]; }
    function near(t) { return !t ? '' : (t.t === 'num' || t.t === 'id' ? t.v : (t.t === 'ref' ? t.raw : t.t)); }
    function fail(code, t) {
      if (!failure) failure = err(code, t ? t.p : tk.len, t ? near(t) : '');
      return null;
    }
    function deeper(t) { depth++; if (depth > MAX_DEPTH) { fail('calcDepth', t); return false; } return true; }
    function expr() {
      var a = term();
      while (a && peek() && (peek().t === '+' || peek().t === '-')) {
        var op = toks[pos++].t, b = term();
        if (!b) return null;
        a = { t: 'bin', op: op, a: a, b: b };
      }
      return a;
    }
    function term() {
      var a = unary();
      while (a && peek() && (peek().t === '*' || peek().t === '/')) {
        var op = toks[pos++].t, b = unary();
        if (!b) return null;
        a = { t: 'bin', op: op, a: a, b: b };
      }
      return a;
    }
    function unary() {
      var t = peek();
      if (t && (t.t === '-' || t.t === '+')) {
        pos++;
        if (!deeper(t)) return null;
        var a = unary();
        depth--;
        if (!a) return null;
        return t.t === '-' ? { t: 'neg', a: a } : a;
      }
      return primary();
    }
    function primary() {
      var t = peek();
      if (!t) return fail('calcSyntax', null);
      if (t.t === 'num') { pos++; return { t: 'num', v: Number(t.v) }; }
      if (t.t === 'ref') {
        pos++;
        if (refs.length >= MAX_REFS) return fail('calcRefs', t);
        var r = { t: 'ref', id: t.id, part: t.part, raw: t.raw };
        refs.push({ id: t.id, part: t.part });
        return r;
      }
      if (t.t === '(') {
        pos++;
        if (!deeper(t)) return null;
        var e = expr();
        depth--;
        if (!e) return null;
        if (!peek() || peek().t !== ')') return fail('calcSyntax', peek());
        pos++;
        return e;
      }
      if (t.t === 'id') {
        if (!Object.prototype.hasOwnProperty.call(FUNCS, t.v)) return fail('calcFn', t);
        pos++;
        if (!peek() || peek().t !== '(') return fail('calcSyntax', peek() || t);
        var open = toks[pos++];
        if (!deeper(open)) return null;
        var args = [];
        if (peek() && peek().t === ')') { pos++; } else {
          while (true) {
            var x = expr();
            if (!x) return null;
            args.push(x);
            if (peek() && peek().t === ',') { pos++; continue; }
            if (peek() && peek().t === ')') { pos++; break; }
            return fail('calcSyntax', peek());
          }
        }
        depth--;
        var lim = FUNCS[t.v];
        if (args.length < lim[0] || args.length > lim[1]) return fail('calcArgs', t);
        return { t: 'call', fn: t.v, args: args };
      }
      return fail('calcSyntax', t);
    }
    var ast = toks.length ? expr() : null;
    if (failure) return failure;
    if (!toks.length) return err('calcEmpty', 0, '');
    if (!ast) return err('calcSyntax', tk.len, '');
    if (pos < toks.length) return err('calcSyntax', toks[pos].p, near(toks[pos]));
    return { ok: true, ast: ast, refs: refs };
  }

  /** The distinct field ids a formula reads (in order of first use). */
  function refIds(src) {
    var p = typeof src === 'string' ? parse(src) : src;
    if (!p || !p.ok) return [];
    var seen = {}, out = [];
    p.refs.forEach(function (r) { if (!seen[r.id]) { seen[r.id] = 1; out.push(r.id); } });
    return out;
  }

  /* ------------------------------------------------------------------ evaluator */

  function Fail(code) { this.code = code; }

  function num(v) {
    if (typeof v !== 'number' || v !== v || v === Infinity || v === -Infinity) throw new Fail('calcRange');
    return v;
  }

  function scaleOf(d) {
    if (typeof d !== 'number' || d !== Math.floor(d) || d < 0 || d > 6) throw new Fail('calcArgs');
    return POW10[d];
  }

  /** Half away from zero with an epsilon (spec §6.2): never Math.round / PHP round. */
  function roundHalfAway(x, d) {
    var s = POW10[d || 0], a = Math.abs(x);
    var r = Math.floor(a * s + 0.5 + EPS) / s;
    return x < 0 ? -r : r;
  }

  /**
   * The canonical text of a number with d decimals (0-6), or with up to 6 and no trailing zeros
   * when d is null. Built from the scaled integer, never Number.prototype.toFixed. Returns null
   * when the scaled value is not exact in a double.
   */
  function toFixed(x, d) {
    var free = d === null || d === undefined || d === '';
    var k = free ? 6 : Number(d);
    var s = POW10[k];
    var a = Math.abs(x);
    var scaled = Math.floor(a * s + 0.5 + EPS);
    if (!(scaled < MAX_SAFE)) return null;
    var digits = String(scaled);
    while (digits.length <= k) digits = '0' + digits;
    var ip = digits.substr(0, digits.length - k), dp = digits.substr(digits.length - k);
    if (free) dp = dp.replace(/0+$/, '');
    var out = ip + (dp !== '' ? '.' + dp : '');
    if (x < 0 && /[1-9]/.test(out)) out = '-' + out;
    return out;
  }

  function truthy(v) { return Math.abs(v) >= EPS ? 1 : 0; }

  function evaluate(ast, resolver, opts) {
    opts = opts || {};
    var blank = opts.empty === 'blank';
    var isEmpty = false;
    function val(n, listOk) {
      switch (n.t) {
        case 'num': return n.v;
        case 'neg': return num(-scalar(n.a));
        case 'bin': {
          var a = scalar(n.a), b = scalar(n.b);
          if (n.op === '+') return num(a + b);
          if (n.op === '-') return num(a - b);
          if (n.op === '*') return num(a * b);
          if (b === 0) throw new Fail('calcDiv0');
          return num(a / b);
        }
        case 'ref': {
          var r = resolver({ id: n.id, part: n.part, raw: n.raw });
          if (r && typeof r === 'object' && !(r instanceof Array) && r.error) throw new Fail(String(r.error));
          if (r instanceof Array) {
            if (!listOk) throw new Fail('calcListCtx');
            var list = [];
            for (var i = 0; i < r.length; i++) {
              if (r[i] === null || r[i] === undefined || r[i] === '') { if (blank) { isEmpty = true; throw new Fail('#empty'); } continue; }
              list.push(num(Number(r[i])));
            }
            return list;
          }
          if (r === null || r === undefined || r === '') {
            if (blank) { isEmpty = true; throw new Fail('#empty'); }
            return 0;
          }
          return num(Number(r));
        }
        case 'call': return call(n);
      }
      throw new Fail('calcSyntax');
    }
    function scalar(n) { return val(n, false); }
    function flat(args) {
      var out = [];
      args.forEach(function (a) {
        var v = val(a, true);
        if (v instanceof Array) out.push.apply(out, v); else out.push(v);
      });
      return out;
    }
    function call(n) {
      var f = n.fn, A = n.args, i, x, k;
      switch (f) {
        case 'if': return truthy(scalar(A[0])) ? scalar(A[1]) : scalar(A[2]);
        case 'and': for (i = 0; i < A.length; i++) if (!truthy(scalar(A[i]))) return 0; return 1;
        case 'or': for (i = 0; i < A.length; i++) if (truthy(scalar(A[i]))) return 1; return 0;
        case 'not': return truthy(scalar(A[0])) ? 0 : 1;
        case 'gt': return scalar(A[0]) - scalar(A[1]) >= EPS ? 1 : 0;
        case 'gte': return scalar(A[0]) - scalar(A[1]) > -EPS ? 1 : 0;
        case 'lt': x = scalar(A[0]); return scalar(A[1]) - x >= EPS ? 1 : 0;
        case 'lte': x = scalar(A[0]); return scalar(A[1]) - x > -EPS ? 1 : 0;
        case 'eq': return Math.abs(scalar(A[0]) - scalar(A[1])) < EPS ? 1 : 0;
        case 'neq': return Math.abs(scalar(A[0]) - scalar(A[1])) < EPS ? 0 : 1;
        case 'abs': return Math.abs(scalar(A[0]));
        case 'round': case 'ceil': case 'floor': {
          x = scalar(A[0]);
          var d = A.length > 1 ? scalar(A[1]) : 0;
          var s = scaleOf(d);
          if (f === 'round') return roundHalfAway(x, d);
          if (f === 'ceil') return num(Math.ceil(x * s - EPS) / s);
          return num(Math.floor(x * s + EPS) / s);
        }
        case 'mod': {
          x = scalar(A[0]); k = scalar(A[1]);
          if (k === 0) throw new Fail('calcDiv0');
          return num(x % k);
        }
        case 'pow': {
          x = scalar(A[0]); k = scalar(A[1]);
          if (k !== Math.floor(k) || k < -10 || k > 10) throw new Fail('calcArgs');
          var p = 1;
          for (i = 0; i < Math.abs(k); i++) p = num(p * x);
          if (k < 0) { if (p === 0) throw new Fail('calcDiv0'); p = num(1 / p); }
          return p;
        }
        case 'count': {
          var c = 0;
          A.forEach(function (a) { var v = val(a, true); c += v instanceof Array ? v.length : 1; });
          return c;
        }
        case 'sum': case 'avg': case 'min': case 'max': {
          var L = flat(A);
          if (!L.length) { if (f === 'sum') return 0; throw new Fail('calcArgs'); }
          var acc = f === 'sum' || f === 'avg' ? 0 : L[0];
          for (i = 0; i < L.length; i++) {
            if (f === 'sum' || f === 'avg') acc = num(acc + L[i]);
            else if (f === 'min') acc = L[i] < acc ? L[i] : acc;
            else acc = L[i] > acc ? L[i] : acc;
          }
          return f === 'avg' ? num(acc / L.length) : acc;
        }
      }
      throw new Fail('calcFn');
    }
    try {
      var v = scalar(ast);
      return { ok: true, value: v };
    } catch (e) {
      if (e instanceof Fail) {
        if (e.code === '#empty' || isEmpty) return { ok: true, value: null, empty: true };
        return { ok: false, code: e.code };
      }
      throw e;
    }
  }

  /** The canonical result of a value with the field's decimals ('' = free): {ok, value: string|''} or {ok:false, code}. */
  function finish(res, decimals) {
    if (!res.ok) return res;
    if (res.empty || res.value === null) return { ok: true, value: '' };
    var v = res.value;
    if (typeof v !== 'number' || v !== v || Math.abs(v) === Infinity || Math.abs(v) > MAX_ABS) return { ok: false, code: 'calcRange' };
    var d = decimals === '' || decimals === null || decimals === undefined ? null : Number(decimals);
    if (d !== null && !(d >= 0 && d <= 6 && d === Math.floor(d))) d = null;
    var s = toFixed(d === null ? v : roundHalfAway(v, d), d);
    if (s === null) return { ok: false, code: 'calcRange' };
    return { ok: true, value: s };
  }

  /**
   * One formula from start to end (what the shared vectors check): values = {id or id.part: number,
   * numeric text, '' (empty), list, {error}}; an id that is not in values is an unknown field.
   */
  function run(formula, values, decimals, opts) {
    var p = parse(formula);
    if (!p.ok) return { ok: false, code: p.code, pos: p.pos, near: p.near };
    values = values || {};
    var r = evaluate(p.ast, function (ref) {
      var key = ref.part ? ref.id + '.' + ref.part : ref.id;
      if (!Object.prototype.hasOwnProperty.call(values, key)) return { error: 'calcRef' };
      return valueOf(values[key]);
    }, opts);
    var f = finish(r, decimals);
    return f.ok ? { ok: true, value: f.value } : { ok: false, code: f.code };
  }

  /** A raw answer as a formula reads it: number, list, null (empty) or {error:'calcNaN'}. */
  function valueOf(v) {
    if (v instanceof Array) {
      var out = [];
      for (var i = 0; i < v.length; i++) { var x = valueOf(v[i]); if (x && x.error) return x; out.push(x); }
      return out;
    }
    if (v && typeof v === 'object') return v.error ? { error: String(v.error) } : { error: 'calcNaN' };
    if (typeof v === 'number') return v;
    if (v === null || v === undefined) return null;
    var s = normalizeDigits(String(v)).replace(/\u066B/g, '.').replace(/[\u066C\u060C,\s]/g, '').replace(/[\u2212]/g, '-');
    if (s === '') return null;
    if (!/^-?\d+(\.\d+)?$/.test(s) || s.length > 40) return { error: 'calcNaN' };
    return Number(s);
  }

  /* ------------------------------------------------------------------ numbers typed by a visitor */

  var DEC_SEPS = { '.': '.', ',': ',', momayez: '\u066B' };
  var GRP_SEPS = { ',': ',', '.': '.', space: ' ', apos: "'", arabic: '\u066C', none: '' };
  var COMMA_DEC = ['de', 'es', 'it', 'nl', 'pt', 'tr', 'id', 'da', 'el', 'ro', 'hr', 'sl', 'sr', 'vi', 'fr', 'ru', 'pl', 'cs', 'sv', 'nb', 'nn', 'fi', 'uk', 'sk', 'hu', 'bg', 'lt', 'lv', 'et', 'kk', 'uz', 'az', 'ka', 'be', 'mk', 'sq', 'bs', 'is'];
  var SPACE_GRP = ['fr', 'ru', 'pl', 'cs', 'sv', 'nb', 'nn', 'fi', 'uk', 'sk', 'hu', 'bg', 'lt', 'lv', 'et', 'kk', 'uz', 'be'];
  var RTL_LANGS = ['fa', 'ar', 'he', 'ur', 'ps', 'ckb', 'ug', 'dv', 'yi', 'sd', 'ku'];
  var LATIN_ARABIC = ['ar_MA', 'ar_DZ', 'ar_TN', 'ary', 'ar_LY'];

  function lang(locale) { return str(locale).split(/[_-]/)[0].toLowerCase(); }
  function isRtl(locale) { return RTL_LANGS.indexOf(lang(locale)) !== -1; }

  /** 'fa' (Persian digits), 'ar' (Arabic-Indic) or 'latin' for num_digits auto / native and a locale. */
  function scriptOf(mode, locale) {
    if (mode !== 'native' && mode !== 'auto') return 'latin';
    var l = lang(locale);
    if (l === 'fa' || l === 'ps' || l === 'ur' || l === 'ckb') return 'fa';
    if (l === 'ar' && LATIN_ARABIC.indexOf(str(locale).replace('-', '_')) === -1) return 'ar';
    return 'latin';
  }

  /** The number keys of a field (spec §5.1) with the defaults of a locale. Twin: Calc::config(). */
  function config(f, ctx) {
    f = f || {}; ctx = ctx || {};
    var locale = str(ctx.locale || 'en_US');
    var l = lang(locale);
    var has = function (k) { return Object.prototype.hasOwnProperty.call(f, k) && f[k] !== null && f[k] !== undefined && String(f[k]) !== ''; };
    var type = str(f.type);
    var numberType = ['number', 'range', 'prcfld', 'calc'].indexOf(type) !== -1;
    var digits = numberType && has('num_digits') && /^(auto|latin|native)$/.test(String(f.num_digits)) ? String(f.num_digits) : 'latin';
    var script = scriptOf(digits, locale);
    var dec, grp;
    if (has('num_dec_sep') && DEC_SEPS.hasOwnProperty(String(f.num_dec_sep))) dec = DEC_SEPS[String(f.num_dec_sep)];
    else dec = script !== 'latin' ? '\u066B' : (COMMA_DEC.indexOf(l) !== -1 ? ',' : '.');
    if (has('num_grp_sep') && GRP_SEPS.hasOwnProperty(String(f.num_grp_sep))) grp = GRP_SEPS[String(f.num_grp_sep)];
    else grp = script !== 'latin' ? '\u066C' : (locale === 'de_CH' ? "'" : (SPACE_GRP.indexOf(l) !== -1 ? ' ' : (COMMA_DEC.indexOf(l) !== -1 ? '.' : ',')));
    if (locale === 'de_CH' && !has('num_dec_sep')) dec = '.';
    if (grp === dec) grp = dec === '.' ? ',' : '.';
    var decimals = has('num_decimals') && /^[0-6]$/.test(String(f.num_decimals)) ? Number(f.num_decimals) : (type === 'calc' && !has('num_decimals') ? 2 : null);
    if (type === 'calc' && has('num_decimals') && !/^[0-6]$/.test(String(f.num_decimals))) decimals = null;
    var cur = numberType && has('num_currency') && String(f.num_currency) === 'form' && ctx.currency ? ctx.currency : null;
    if (cur) decimals = typeof cur.d === 'number' ? cur.d : 2;
    var affix = function (k, k2) {
      var v = has(k) ? String(f[k]) : (k2 && has(k2) ? String(f[k2]) : '');
      return v.replace(/["<>]/g, '').substr(0, 20);
    };
    var dnum = function (k) { var v = has(k) ? normalizeDigits(String(f[k])).trim() : ''; return /^-?\d{1,15}(\.\d{1,6})?$/.test(v) ? v.replace(/^(-?)0+(?=\d)/, '$1') : ''; };
    var min = dnum('milen'), max = dnum('mlen');
    if (max !== '' && Number(max) === 0 && !(has('num_max_zero') && /^(1|true)$/.test(String(f.num_max_zero)))) max = '';
    var step = has('num_step') && /^\d{1,9}(\.\d{1,6})?$/.test(normalizeDigits(String(f.num_step))) && Number(normalizeDigits(String(f.num_step))) > 0 ? normalizeDigits(String(f.num_step)) : '';
    return {
      type: type, locale: locale, script: script, dec: dec, grp: grp,
      group: numberType && ((has('num_format') && String(f.num_format) === 'group') || !!cur),
      decimals: numberType ? decimals : null, step: step, min: min, max: max,
      prefix: cur ? '' : affix('num_prefix', type === 'number' ? 'prefix' : ''),
      suffix: cur ? '' : affix('num_suffix', type === 'number' ? 'suffix' : ''),
      cur: cur ? { s: str(cur.s), d: decimals } : null,
      rtl: isRtl(locale)
    };
  }

  /**
   * What a visitor typed, as a canonical ASCII number (spec §10.1): local digits, the prefix and suffix,
   * thousands separators and the decimal separator are taken off; {ok, canon} or {ok:false, err:'nan'|'empty'}.
   */
  function parseInput(raw, cfg) {
    cfg = cfg || {};
    var s = normalizeDigits(str(raw)).replace(/[\u200B-\u200F\u202A-\u202E\u2066-\u2069\uFEFF\u00AD]/g, '')
      .replace(/[\u2212\u2010-\u2015\uFE63\uFF0D]/g, '-').replace(/[\u00A0\u2007\u202F\u3000]/g, ' ').trim();
    if (s.length > 64) return { ok: false, err: 'nan', canon: '' };
    var strip = function (a, atStart) {
      if (!a) return;
      if (atStart && s.indexOf(a) === 0) s = s.substr(a.length).trim();
      if (!atStart && s.length >= a.length && s.substr(s.length - a.length) === a) s = s.substr(0, s.length - a.length).trim();
    };
    strip(cfg.prefix, true); strip(cfg.suffix, false);
    if (cfg.cur && cfg.cur.s) { strip(cfg.cur.s, true); strip(cfg.cur.s, false); }
    if (s === '') return { ok: false, err: 'empty', canon: '' };
    var dec = cfg.dec || '.', grp = cfg.grp === undefined ? ',' : cfg.grp;
    var neg = false;
    if (s.charAt(0) === '-') { neg = true; s = s.substr(1).trim(); }
    var out = '', seenDec = false;
    for (var i = 0; i < s.length; i++) {
      var c = s.charAt(i);
      if (c >= '0' && c <= '9') { out += c; continue; }
      if (c === '\u066B' || (c === dec)) { if (seenDec) return { ok: false, err: 'nan', canon: '' }; seenDec = true; out += '.'; continue; }
      if (c === '\u066C' || c === '\u060C' || c === grp || c === ' ' || c === "'" || (c === ',' && dec !== ',') ) { if (seenDec) return { ok: false, err: 'nan', canon: '' }; continue; }
      if (c === '.' && dec !== '.' && grp !== '.') { if (seenDec) return { ok: false, err: 'nan', canon: '' }; seenDec = true; out += '.'; continue; }
      return { ok: false, err: 'nan', canon: '' };
    }
    if (!/^(\d+\.?\d*|\.\d+)$/.test(out)) return { ok: false, err: 'nan', canon: '' };
    if (out.charAt(0) === '.') out = '0' + out;
    out = out.replace(/\.$/, '').replace(/^0+(?=\d)/, '');
    if (/\./.test(out)) out = out.replace(/0+$/, '').replace(/\.$/, '');
    if (neg && /[1-9]/.test(out)) out = '-' + out;
    return { ok: true, err: '', canon: out };
  }

  /** Sign-aware comparison of two decimal strings, without floats: -1, 0, 1. */
  function cmpDec(a, b) {
    var na = a.charAt(0) === '-', nb = b.charAt(0) === '-';
    if (na !== nb) return na ? -1 : 1;
    var pa = a.replace('-', '').split('.'), pb = b.replace('-', '').split('.');
    var ia = pa[0].replace(/^0+(?=\d)/, ''), ib = pb[0].replace(/^0+(?=\d)/, ''), r;
    if (ia.length !== ib.length) r = ia.length < ib.length ? -1 : 1;
    else if (ia !== ib) r = ia < ib ? -1 : 1;
    else {
      var da = pa[1] || '', db = pb[1] || '', n = Math.max(da.length, db.length);
      while (da.length < n) da += '0';
      while (db.length < n) db += '0';
      r = da === db ? 0 : (da < db ? -1 : 1);
    }
    return na ? -r : r;
  }

  function decCount(s) { var i = s.indexOf('.'); return i === -1 ? 0 : s.length - i - 1; }

  /** |x| scaled by 10^k as a digit string (x a canonical decimal string). */
  function scaledDigits(x, k) {
    var p = x.replace('-', '').split('.');
    var d = p[1] || '';
    while (d.length < k) d += '0';
    return (p[0] + d).replace(/^0+(?=\d)/, '');
  }

  /** x mod m, x a digit string, m an integer below 2^49 (exact in a double and in PHP). */
  function modDigits(x, m) {
    var r = 0;
    for (var i = 0; i < x.length; i++) r = (r * 10 + (x.charCodeAt(i) - 48)) % m;
    return r;
  }

  /** Whether value - base is a whole multiple of step (all canonical decimal strings), with scaled integers. */
  function onStep(value, step, base) {
    if (!step) return true;
    base = base || '0';
    var k = Math.max(decCount(value), decCount(step), decCount(base));
    var m = Number(scaledDigits(step, k));
    if (!(m > 0) || m >= 562949953421312) return true;
    var res = function (x) {
      var r = modDigits(scaledDigits(x, k), m);
      return x.charAt(0) === '-' && r !== 0 ? m - r : r;
    };
    return res(value) === res(base);
  }

  /**
   * The checks of a canonical number in order (spec §10.1): decimals, step, minimum, maximum.
   * '' when it passes, else 'dec' | 'step' | 'min' | 'max'.
   */
  function check(canon, cfg) {
    cfg = cfg || {};
    if (cfg.decimals !== null && cfg.decimals !== undefined && cfg.decimals !== '' && decCount(canon) > Number(cfg.decimals)) return 'dec';
    if (cfg.step && !onStep(canon, cfg.step, cfg.min || '0')) return 'step';
    if (cfg.min !== '' && cfg.min !== undefined && cmpDec(canon, cfg.min) < 0) return 'min';
    if (cfg.max !== '' && cfg.max !== undefined && cmpDec(canon, cfg.max) > 0) return 'max';
    return '';
  }

  /* ------------------------------------------------------------------ display */

  function toScript(s, script) {
    if (script === 'fa') return s.replace(/[0-9]/g, function (d) { return String.fromCharCode(0x06F0 + Number(d)); });
    if (script === 'ar') return s.replace(/[0-9]/g, function (d) { return String.fromCharCode(0x0660 + Number(d)); });
    return s;
  }

  /**
   * A canonical number as the visitor reads it (spec §4.1, §11): thousands separator, decimal
   * separator, local digits, prefix / suffix or the form currency. Anything that is not a canonical
   * number comes back unchanged. Twin: Calc::format().
   */
  function format(canon, cfg) {
    cfg = cfg || {};
    var s = str(canon).trim();
    if (!/^-?\d+(\.\d+)?$/.test(s)) return str(canon);
    var neg = s.charAt(0) === '-';
    var p = s.replace('-', '').split('.');
    var ip = p[0], dp = p[1] || '';
    if (cfg.cur) { while (dp.length < cfg.cur.d) dp += '0'; }
    if (cfg.group && cfg.grp) {
      var out = '';
      for (var i = ip.length; i > 0; i -= 3) out = ip.substring(Math.max(0, i - 3), i) + (out ? cfg.grp + out : '');
      ip = out;
    }
    var body = (neg ? '-' : '') + ip + (dp !== '' ? (cfg.dec || '.') + dp : '');
    body = toScript(body, cfg.script || 'latin');
    if (cfg.cur && cfg.cur.s) return cfg.rtl ? body + ' ' + cfg.cur.s : cfg.cur.s + body;
    var pre = cfg.prefix || '', suf = cfg.suffix || '';
    return (pre ? pre + (/[A-Za-z\u0600-\u06FF]$/.test(pre) ? ' ' : '') : '') + body + (suf ? (/^[A-Za-z\u0600-\u06FF]/.test(suf) ? ' ' : '') + suf : '');
  }



  /* ------------------------------------------------------------------ save rule (twin of Calc::sanitize_key) */

  var FIELD_KEYS = ['num_step', 'num_decimals', 'num_format', 'num_dec_sep', 'num_grp_sep', 'num_digits', 'num_prefix', 'num_suffix', 'num_currency', 'num_max_zero',
    'formula', 'formula_err', 'calc_show', 'calc_empty', 'calc_as_price', 'range_mode'];
  var ENUMS = {
    num_format: ['none', 'group'], num_dec_sep: ['.', ',', 'momayez'], num_grp_sep: [',', '.', 'space', 'apos', 'arabic', 'none'],
    num_digits: ['auto', 'latin', 'native'], num_currency: ['form'], calc_show: ['visible', 'hidden'], calc_empty: ['zero', 'blank'],
    range_mode: ['single', 'dual']
  };
  var NUMBER_TYPES = ['number', 'range', 'prcfld', 'calc'];

  function phpTrim(s) { return s.replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, ''); }

  /** One F04 key as stored ('' = not set). Twin: Calc::sanitize_key(). */
  function sanitizeKey(k, v, type) {
    if (v === null || v === undefined || typeof v === 'object') return '';
    if (typeof v === 'boolean') v = v ? '1' : '';
    var raw = String(v);
    var s = phpTrim(normalizeDigits(raw));
    switch (k) {
      case 'num_step': return /^\d{1,9}(\.\d{1,6})?$/.test(s) && Number(s) > 0 ? s.replace(/^0+(?=\d)/, '') : '';
      case 'num_decimals': return /^[0-6]$/.test(s) ? s : '';
      case 'calc': return /^-?\d{1,15}(\.\d{1,6})?$/.test(s) ? s : '';
      case 'num_max_zero': case 'calc_as_price': return ['1', 'true', 'on'].indexOf(s.toLowerCase()) !== -1 ? '1' : '';
      case 'num_prefix': case 'num_suffix': {
        var t = raw.replace(/<[^>]*>/g, '').replace(/["<>`]/g, '');
        t = phpTrim(t.replace(/[\x00-\x20\x7F]+/g, ' '));
        var cps = t.match(/[\uD800-\uDBFF][\uDC00-\uDFFF]|[\s\S]/g) || [];
        return cps.slice(0, 20).join('');
      }
      case 'formula': {
        var f = phpTrim(normalizeFormula(raw).replace(/[\x00-\x1F\x7F]+/g, ' '));
        if (unescape(encodeURIComponent(f)).length > MAX_LEN || !/^[A-Za-z0-9_{}().,:\s+\-*\/]*$/.test(f) || /[^\x00-\x7F]/.test(f)) return '';
        return f.replace(/ {2,}/g, ' ');
      }
      case 'formula_err': return '';
    }
    if (k === 'num_dec_sep' && s === '٫') return 'momayez';
    if (k === 'num_grp_sep') { if (raw === ' ') return 'space'; if (s === '٬') return 'arabic'; if (s === "'") return 'apos'; }
    if (Object.prototype.hasOwnProperty.call(ENUMS, k)) {
      for (var i = 0; i < ENUMS[k].length; i++) if (ENUMS[k][i].toLowerCase() === s.toLowerCase()) return ENUMS[k][i];
      return '';
    }
    return '';
  }

  /** Whether a key is one of F04's on this row type. Twin: Calc::is_key(). */
  function isKey(k, type) {
    if (k === 'calc') return type === 'option';
    if (k === 'num_digits' || k === 'num_prefix' || k === 'num_suffix') return NUMBER_TYPES.indexOf(type) !== -1;
    return FIELD_KEYS.indexOf(k) !== -1;
  }


  /* ------------------------------------------------------------------ a form's formulas (twin of Calc::form_errors) */

  var LAYOUT_TYPES = ['option', 'step', 'form', 'r_matrix', 'heading', 'link', 'html', 'buttonNav', 'submit'];

  function sortedKeys(o) { return Object.keys(o).sort(function (a, b) { return a < b ? -1 : (a > b ? 1 : 0); }); }

  /** Ids of the calc fields on a cycle of references. Twin: Calc::cycle_ids(). */
  function cycleIds(deps) {
    var state = {}, on = {};
    function visit(id, stack) {
      if (state[id] === 2) return;
      if (state[id] === 1) { var k = stack.indexOf(id); stack.slice(k < 0 ? 0 : k).forEach(function (c) { on[c] = true; }); return; }
      state[id] = 1;
      var st = stack.concat([id]);
      (deps[id] || []).forEach(function (d) { if (Object.prototype.hasOwnProperty.call(deps, d)) visit(d, st); });
      state[id] = 2;
    }
    sortedKeys(deps).forEach(function (id) { visit(id, []); });
    return sortedKeys(on);
  }

  /** id => {code, pos, near, ref} for every calc field with a formula problem. Twin: Calc::form_errors(). */
  function formErrors(rows) {
    var out = {}, types = {}, calc = {}, order = [];
    (rows || []).forEach(function (r, i) {
      if (i < 1 || !r || r.id_ === undefined || r.type === undefined || typeof r.id_ === 'object' || typeof r.type === 'object') return;
      var t = String(r.type);
      if (t === 'option' || t === 'step') return;
      types[String(r.id_)] = t;
      if (t === 'calc') { if (!Object.prototype.hasOwnProperty.call(calc, String(r.id_))) order.push(String(r.id_)); calc[String(r.id_)] = r.formula === undefined || r.formula === null || typeof r.formula === 'object' ? '' : String(r.formula); }
    });
    var deps = {};
    order.forEach(function (id) {
      var p = parse(calc[id]);
      if (!p.ok) { out[id] = { code: p.code, pos: p.pos, near: p.near, ref: '' }; return; }
      deps[id] = [];
      for (var i = 0; i < p.refs.length; i++) {
        var rid = p.refs[i].id;
        if (rid === id) { out[id] = { code: 'calcSelf', pos: -1, near: '{' + rid + '}', ref: rid }; break; }
        if (!Object.prototype.hasOwnProperty.call(types, rid)) { out[id] = { code: 'calcRef', pos: -1, near: '{' + rid + '}', ref: rid }; break; }
        if (FORBIDDEN_TYPES.indexOf(types[rid]) !== -1 || LAYOUT_TYPES.indexOf(types[rid]) !== -1) { out[id] = { code: 'calcForbidden', pos: -1, near: '{' + rid + '}', ref: rid }; break; }
        if (types[rid] === 'calc' && deps[id].indexOf(rid) === -1) deps[id].push(rid);
      }
    });
    cycleIds(deps).forEach(function (id) { if (!out[id]) out[id] = { code: 'calcCycle', pos: -1, near: '', ref: '' }; });
    var sorted = {};
    sortedKeys(out).forEach(function (k) { sorted[k] = out[k]; });
    return sorted;
  }

  /** The calc fields in computing order (references first). Twin: Calc::topo_order(). */
  function topoOrder(rows) {
    var errs = formErrors(rows), deps = {}, ids = [];
    (rows || []).forEach(function (r, i) {
      if (i < 1 || !r || r.type !== 'calc' || r.id_ === undefined) return;
      var id = String(r.id_);
      if (!Object.prototype.hasOwnProperty.call(deps, id)) ids.push(id);
      deps[id] = errs[id] ? null : refIds(r.formula === undefined || r.formula === null ? '' : String(r.formula));
    });
    var order = [], done = {};
    function visit(id) {
      if (done[id]) return;
      done[id] = true;
      (deps[id] || []).forEach(function (d) { if (Object.prototype.hasOwnProperty.call(deps, d)) visit(d); });
      order.push(id);
    }
    ids.forEach(visit);
    return order;
  }

  /* ------------------------------------------------------------------ typing (page) */

  /** format() without the prefix, suffix or currency symbol (the input box; the affixes sit beside it). */
  function formatBare(canon, cfg) {
    var c = {};
    for (var k in cfg) if (Object.prototype.hasOwnProperty.call(cfg, k)) c[k] = cfg[k];
    c.prefix = ''; c.suffix = '';
    c.cur = cfg && cfg.cur ? { s: '', d: cfg.cur.d } : null;
    return format(canon, c);
  }

  /**
   * One keystroke of a number box (spec §4.1): local digits to the shown digits, the thousands separator
   * put back in its place, one decimal separator, at most `decimals` digits after it, a minus sign only
   * first. The caret stays after the same digit. Returns {value, caret}.
   */
  function live(raw, caret, cfg) {
    cfg = cfg || {};
    raw = str(raw);
    if (caret === undefined || caret === null || caret > raw.length) caret = raw.length;
    var dec = cfg.dec || '.', grp = cfg.grp === undefined ? ',' : cfg.grp;
    var maxDec = cfg.decimals === null || cfg.decimals === undefined || cfg.decimals === '' ? -1 : Number(cfg.decimals);
    var neg = false, ip = '', dp = '', hasDec = false, before = 0;
    for (var i = 0; i < raw.length; i++) {
      var c = normalizeDigits(raw.charAt(i));
      var kept = false;
      if (c >= '0' && c <= '9') {
        if (hasDec) { if (maxDec < 0 || dp.length < maxDec) { dp += c; kept = true; } } else { ip += c; kept = true; }
      } else if (c === '-' || c === '−') {
        if (!neg && ip === '' && !hasDec) { neg = true; kept = true; }
      } else if (!hasDec && maxDec !== 0 && (c === dec || c === '٫' || (c === '.' && dec !== '.' && grp !== '.'))) {
        hasDec = true; kept = true;
      }
      if (kept && i < caret) before++;
    }
    ip = ip.replace(/^0+(?=\d)/, '');
    if (ip === '' && hasDec) ip = '0';
    var gi = ip;
    if (cfg.group && grp && ip.length > 3) {
      gi = '';
      for (var j = ip.length; j > 0; j -= 3) gi = ip.substring(Math.max(0, j - 3), j) + (gi ? grp + gi : '');
    }
    var out = toScript((neg ? '-' : '') + gi + (hasDec ? dec : '') + dp, cfg.script || 'latin');
    // The caret goes after the same number of kept characters (digits, minus, separator).
    var pos = 0, seen = 0;
    while (pos < out.length && seen < before) {
      var ch = normalizeDigits(out.charAt(pos));
      if ((ch >= '0' && ch <= '9') || ch === '-' || (hasDec && ch === dec)) seen++;
      pos++;
    }
    return { value: out, caret: pos };
  }

  /**
   * A number box as the page and the server read it: {empty} | {ok:false, code, msg} | {ok:true, canon}.
   * t = the texts (efb_var.text), name = the field label.
   */
  function checkInput(raw, cfg, t, name) {
    t = t || {};
    var p = parseInput(raw, cfg);
    if (!p.ok && p.err === 'empty') return { empty: true };
    var fill = function (k, en, a, b) {
      var s = (t[k] || en);
      return s.replace('%1$s', a).replace('%2$s', b).replace('%s', a).replace('NN', a);
    };
    if (!p.ok) return { ok: false, code: 'nan', msg: fill('numNanM_', 'Please enter a number in %s.', name || '', '') };
    var c = check(p.canon, cfg);
    if (c === 'dec') return { ok: false, code: c, msg: fill('numDecM_', '%1$s allows at most %2$s decimal places.', name || '', String(cfg.decimals)) };
    if (c === 'step') return { ok: false, code: c, msg: fill('numStepM_', '%1$s must be a multiple of %2$s.', name || '', toScript(cfg.step, cfg.script)) };
    if (c === 'min') return { ok: false, code: c, msg: fill('mcplen', 'Please enter a number that is greater than or equal to NN.', format(cfg.min, cfg), '') };
    if (c === 'max') return { ok: false, code: c, msg: fill('mxcplen', 'Please enter a number that is less than or equal to NN', format(cfg.max, cfg), '') };
    return { ok: true, canon: p.canon };
  }

  /** Live formatting of a number box (input event); the change handler of core-efb.js checks it. */
  function bindNumber(el, cfg) {
    if (!el || el.__efbNum) return;
    el.__efbNum = true;
    var composing = false;
    el.addEventListener('compositionstart', function () { composing = true; });
    el.addEventListener('compositionend', function () { composing = false; });
    el.addEventListener('input', function () {
      if (composing) return;
      var c = el.selectionStart;
      var r = live(el.value, c, cfg);
      if (r.value !== el.value) {
        el.value = r.value;
        try { if (document.activeElement === el) el.setSelectionRange(r.caret, r.caret); } catch (e) { /* not a text box */ }
      }
    });
  }

  /** The number settings of a box rendered by the server (data-numcfg). */
  function cfgOf(el) {
    try { return el && el.getAttribute('data-numcfg') ? JSON.parse(el.getAttribute('data-numcfg')) : null; } catch (e) { return null; }
  }

  /** A low..high pair of a two-handle slider ("10..50"), or null. */
  function splitRange(v) {
    var m = /^\s*(-?\d+(?:\.\d+)?)\s*\.\.\s*(-?\d+(?:\.\d+)?)\s*$/.exec(normalizeDigits(str(v)));
    return m ? { lo: m[1], hi: m[2] } : null;
  }

  return {
    MAX_LEN: MAX_LEN, MAX_TOKENS: MAX_TOKENS, MAX_DEPTH: MAX_DEPTH, MAX_REFS: MAX_REFS, FUNCS: Object.keys(FUNCS), PARTS: Object.keys(PARTS),
    FORBIDDEN_TYPES: FORBIDDEN_TYPES, LIST_FUNCS: Object.keys(LIST_FUNCS),
    normalizeDigits: normalizeDigits, normalizeFormula: normalizeFormula, tokenize: tokenize, parse: parse, refIds: refIds,
    evaluate: evaluate, finish: finish, run: run, valueOf: valueOf, roundHalfAway: roundHalfAway, toFixed: toFixed,
    config: config, parseInput: parseInput, check: check, cmpDec: cmpDec, onStep: onStep, format: format, toScript: toScript,
    scriptOf: scriptOf, isRtl: isRtl, splitRange: splitRange,
    formErrors: formErrors, cycleIds: cycleIds, topoOrder: topoOrder, LAYOUT_TYPES: LAYOUT_TYPES,
    FIELD_KEYS: FIELD_KEYS, ENUMS: ENUMS, sanitizeKey: sanitizeKey, isKey: isKey,
    formatBare: formatBare, live: live, checkInput: checkInput, bindNumber: bindNumber, cfgOf: cfgOf
  };
});
