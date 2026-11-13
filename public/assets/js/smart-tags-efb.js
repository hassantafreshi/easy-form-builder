/**
 * F08 smart tags in the browser: window.EFBSmart (docs/feature-gaps/F08-hidden-smart-tags.fa.md).
 *
 * The pure part (parse, allowed, classify, cleanTemplate, cleanValue, resolve, formatDate, url helpers,
 * sanitizeKey) is the twin of Emsfb\Smart_Tags (includes/class-Emsfb-smart-tags.php): both give the same
 * answer for every vector of docs/feature-gaps/reference/smart-tag-vectors.json
 * (tests/test-smart-tags-parity.js). The page part fills what the cached HTML cannot carry: hidden field
 * values, tag defaults and placeholders, the <bdi class="efb-st"> pieces of labels and messages (answer
 * piping) and the URL prefill of the fields that have a prefill_key. Every value goes in through
 * .value or .textContent, never innerHTML, and no eval.
 *
 * Nothing here is trusted by the server: on submit it rebuilds every hidden value it can and resolves
 * e-mails, confirmation messages and redirects itself.
 */
(function (root) {
  'use strict';

  var NS = ['get', 'cookie', 'ref', 'page', 'post', 'user', 'date', 'ip', 'uid', 'field', 'form', 'site', 'entry', 'geo'];
  var MAX = 500;
  var ARGS = {
    page: ['url', 'path', 'title', 'id'],
    post: ['id', 'title', 'url', 'type'],
    user: ['id', 'login', 'email', 'display_name', 'first_name', 'last_name', 'role'],
    form: ['id', 'name'],
    site: ['name', 'url'],
    entry: ['track'],
    geo: ['country', 'country_name']
  };
  var PUBLIC_NS = ['form', 'site', 'post'];
  var PREFILL_TYPES = ['text', 'firstName', 'lastName', 'email', 'tel', 'mobile', 'url', 'number', 'textarea', 'date', 'select', 'radio', 'checkbox',
    'multiselect', 'imgRadio', 'conturyList', 'stateProvince', 'cityList', 'hiddenField', 'range', 'rating', 'pointr5', 'pointr10', 'yesNo',
    'address_line', 'postalcode'];
  var DEFAULT_TYPES = ['text', 'firstName', 'lastName', 'email', 'tel', 'url', 'number', 'textarea', 'date', 'address_line', 'postalcode'];
  var COOKIE_DENY = /^(wordpress|wp-|wp_|comment_|phpsessid|efb|emsfb|woocommerce_|wc_)/i;
  var FIELD_KEYS = ['value', 'hdn_visitor_mail', 'prefill_key', 'prefill_lock'];
  var PRESETS = {
    'static': '', utm_source: '{get:utm_source}', utm_medium: '{get:utm_medium}', utm_campaign: '{get:utm_campaign}',
    utm_term: '{get:utm_term}', utm_content: '{get:utm_content}', gclid: '{get:gclid}', fbclid: '{get:fbclid}',
    msclkid: '{get:msclkid}', param: '{get:<name>}', ref: '{ref}', page_url: '{page:url}', page_title: '{page:title}',
    post_id: '{post:id}', ip: '{ip}', user_id: '{user:id}', user_email: '{user:email}', user_login: '{user:login}',
    user_name: '{user:display_name}', user_role: '{user:role}', date: '{date:Y-m-d}', time: '{date:H:i}',
    datetime: '{date:Y-m-d H:i}', uid: '{uid}', cookie: '{cookie:<name>}'
  };

  function inArr(a, v) { return a.indexOf(v) !== -1; }
  function str(v) { return v === null || v === undefined ? '' : (typeof v === 'string' ? v : (typeof v === 'number' || typeof v === 'boolean' ? String(v) : '')); }
  function isScalar(v) { return typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean'; }

  /* ---------------------------------------------------------------- pure core (twin of Smart_Tags) */

  function allowed(where, ns, arg) {
    ns = str(ns); arg = str(arg);
    var full = arg === '' ? ns : ns + ':' + arg;
    switch (str(where)) {
      case 'default': return inArr(['get', 'cookie', 'page', 'post', 'user', 'date', 'form', 'site'], ns);
      case 'hidden': return ns !== 'entry';
      case 'placeholder': return inArr(['get', 'page', 'post', 'date', 'form', 'site'], ns) || inArr(['user:display_name', 'user:first_name'], full);
      case 'label': return inArr(['field', 'get', 'date', 'form', 'site'], ns) || inArr(['user:display_name', 'user:first_name', 'page:title', 'post:title'], full);
      case 'message': return !inArr(['ip', 'cookie', 'uid'], ns) && full !== 'user:email';
      case 'email_admin': return true;
      case 'email_visitor': return !inArr(['ip', 'cookie'], ns);
      case 'redirect': return !inArr(['ip', 'cookie'], ns) && full !== 'user:email';
    }
    return false;
  }

  function interpret(inner) {
    var m = /^([a-z]{2,6})(?::([^|]{1,64}))?(?:\|([^{}]{0,80}))?$/u.exec(inner);
    if (m && inArr(NS, m[1])) return { t: 'tag', ns: m[1], arg: m[2] === undefined ? '' : m[2], fb: m[3] === undefined ? '' : m[3], hasfb: m[3] !== undefined, legacy: false };
    if (/^[A-Za-z0-9_-]{1,64}$/.test(inner)) return { t: 'tag', ns: 'field', arg: inner, fb: '', hasfb: false, legacy: true };
    return null;
  }

  function parse(text) {
    text = str(text);
    var out = [];
    if (text === '' || text.indexOf('{') === -1) { if (text !== '') out.push({ t: 'text', v: text }); return out; }
    var re = /\{([^{}]{1,200})\}/gu, m, pos = 0;
    while ((m = re.exec(text)) !== null) {
      var tag = interpret(m[1]);
      if (!tag) continue;
      if (m.index > pos) out.push({ t: 'text', v: text.slice(pos, m.index) });
      tag.raw = m[0];
      out.push(tag);
      pos = m.index + m[0].length;
    }
    if (pos < text.length) out.push({ t: 'text', v: text.slice(pos) });
    var merged = [];
    out.forEach(function (p) {
      var n = merged.length;
      if (p.t === 'text' && n > 0 && merged[n - 1].t === 'text') merged[n - 1].v += p.v; else merged.push(p);
    });
    return merged;
  }

  function tags(text, legacy) { return parse(text).filter(function (p) { return p.t === 'tag' && (legacy || !p.legacy); }); }
  function hasTags(text) { return typeof text === 'string' && text.indexOf('{') !== -1 && tags(text).length > 0; }
  function keyOf(tag) {
    var arg = tag.arg;
    if (tag.ns === 'field') arg = arg.replace(/\.(label|value)$/, '');
    return arg === '' ? tag.ns : tag.ns + ':' + arg;
  }

  function classify(template) {
    var ts = tags(template);
    if (!ts.length) return 'static';
    for (var i = 0; i < ts.length; i++) {
      var t = ts[i], k = t.arg === '' ? t.ns : t.ns + ':' + t.arg;
      if (!inArr(['get', 'cookie', 'ref'], t.ns) && !inArr(['page:url', 'page:path'], k)) return 'server';
    }
    return 'client';
  }

  function cut(s, n) { return Array.from(s).slice(0, n).join(''); }

  function cleanValue(v) {
    if (Array.isArray(v)) v = v.map(function (x) { return isScalar(x) ? String(x) : ''; }).join(', ');
    v = isScalar(v) ? String(v) : '';
    if (v === '') return '';
    v = v.replace(/[\r\n\t]+/gu, ' ');
    v = v.replace(/[\u0000-\u001F\u007F-\u009F\u2028\u2029\u202A-\u202E\u2066-\u2069]/gu, '');
    return cut(v, MAX);
  }

  function cleanTemplate(t) {
    t = cleanValue(isScalar(t) ? String(t) : '');
    t = t.replace(/\{([a-z][a-z0-9_]{0,15}):[^{}]{0,200}\}/gu, function (whole, ns) { return inArr(NS, ns) ? whole : ''; });
    return cut(t, MAX).replace(/^[ \x0B]+|[ \x0B]+$/g, '');
  }

  function esc(s) {
    return str(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }
  /* PHP rawurlencode(): everything but A-Z a-z 0-9 - _ . ~ as %XX. */
  function rawurlencode(s) {
    return encodeURIComponent(str(s)).replace(/[!'()*]/g, function (c) { return '%' + c.charCodeAt(0).toString(16).toUpperCase(); });
  }
  function encode(v, mode) {
    if (mode === 'html' || mode === 'attr') return esc(v);
    if (mode === 'url_query') return rawurlencode(v);
    return v;
  }
  function hasField(ctx, id) { return !!(ctx && ctx.fields && typeof ctx.fields === 'object' && Object.prototype.hasOwnProperty.call(ctx.fields, String(id))); }
  function argBase(p) { return p.ns === 'field' ? '' : p.arg; }

  function resolve(text, ctx, mode, where, opts) {
    ctx = ctx || {}; mode = mode || 'text'; opts = opts || {};
    var out = '';
    parse(text).forEach(function (p) {
      if (p.t === 'text') { out += (mode === 'html' || mode === 'attr') && !opts.raw_static ? esc(p.v) : p.v; return; }
      if (p.legacy && !hasField(ctx, p.arg)) {
        if (opts.legacy_drop) return;
        out += (mode === 'html' || mode === 'attr') && !opts.raw_static ? esc(p.raw) : p.raw;
        return;
      }
      var v = (where === null || where === undefined || allowed(where, p.ns, argBase(p))) ? value(p, ctx) : null;
      if (v === null || v === '') v = p.fb;
      v = cleanValue(v);
      out += encode(v, mode);
    });
    if (mode === 'subject') out = out.replace(/[\r\n]+/g, ' ').trim();
    return out;
  }

  function cookieOk(name, ctx) {
    name = str(name);
    if (!/^[A-Za-z0-9_\-.]{1,64}$/.test(name) || COOKIE_DENY.test(name)) return false;
    if (ctx && Array.isArray(ctx.cookie_deny) && ctx.cookie_deny.indexOf(name) !== -1) return false;
    return true;
  }
  function httpUrl(u) { return typeof u === 'string' && u.length <= 2000 && /^https?:\/\/[^\x00-\x20/?#]+[^\x00-\x20]*$/i.test(u); }

  function value(p, ctx) {
    var ns = p.ns, arg = str(p.arg), o;
    switch (ns) {
      case 'get': {
        if (!/^[A-Za-z0-9_\-[\]]{1,64}$/.test(arg)) return null;
        var q = ctx.query && typeof ctx.query === 'object' ? ctx.query : {};
        var key = arg.replace(/\[\]$/, '');
        if (!Object.prototype.hasOwnProperty.call(q, key)) return null;
        var v = q[key];
        if (Array.isArray(v)) v = v.map(function (x) { return isScalar(x) ? String(x) : ''; }).join(', ');
        return isScalar(v) ? String(v) : null;
      }
      case 'cookie': {
        if (!cookieOk(arg, ctx)) return null;
        var c = ctx.cookies && typeof ctx.cookies === 'object' ? ctx.cookies : {};
        return Object.prototype.hasOwnProperty.call(c, arg) && isScalar(c[arg]) ? String(c[arg]) : null;
      }
      case 'ref': {
        if (arg !== '') return null;
        var r = isScalar(ctx.ref) ? String(ctx.ref) : '';
        return httpUrl(r) ? r : null;
      }
      case 'page': case 'post': case 'form': case 'site': case 'entry':
        if (!inArr(ARGS[ns], arg)) return null;
        o = ctx[ns] && typeof ctx[ns] === 'object' ? ctx[ns] : {};
        return Object.prototype.hasOwnProperty.call(o, arg) && isScalar(o[arg]) ? String(o[arg]) : null;
      case 'user': {
        if (!inArr(ARGS.user, arg)) return null;
        var u = ctx.user && typeof ctx.user === 'object' ? ctx.user : null;
        if (!u || !u.id) return null;
        return Object.prototype.hasOwnProperty.call(u, arg) && isScalar(u[arg]) ? String(u[arg]) : null;
      }
      case 'date':
        if (typeof ctx.now !== 'number' || !isFinite(ctx.now)) return null;
        return formatDate(arg === '' ? 'Y-m-d' : arg, Math.floor(ctx.now), Number(ctx.tz) || 0);
      case 'ip':
        if (arg !== '') return null;
        return isScalar(ctx.ip) ? String(ctx.ip) : null;
      case 'uid': {
        var n = arg === '' ? 12 : (/^\d{1,2}$/.test(arg) ? parseInt(arg, 10) : 0);
        if (n < 6 || n > 32) return null;
        if (ctx.uid === undefined || ctx.uid === null) return null;
        if (typeof ctx.uid === 'function') return String(ctx.uid(n));
        return String(ctx.uid).slice(0, n);
      }
      case 'field': return fieldValue(arg, ctx);
      case 'geo': return null;
    }
    return null;
  }

  function fieldValue(arg, ctx) {
    var sub = 'label', m = /^(.+)\.(label|value)$/.exec(arg);
    if (m) { arg = m[1]; sub = m[2]; }
    if (!/^[A-Za-z0-9_-]{1,64}$/.test(arg)) return null;
    var vals = ctx.values && typeof ctx.values === 'object' ? ctx.values : {};
    if (!Object.prototype.hasOwnProperty.call(vals, arg)) return null;
    var v = vals[arg];
    var list = (Array.isArray(v) ? v : [v]).map(function (x) { return isScalar(x) ? String(x) : ''; }).filter(function (x) { return x !== ''; });
    if (sub === 'value' && ctx.fields && ctx.fields[arg] && Array.isArray(ctx.fields[arg].options)) {
      var opts = ctx.fields[arg].options;
      list = list.map(function (label) {
        for (var i = 0; i < opts.length; i++) {
          if (opts[i] && str(opts[i].label) === label) return str(opts[i].value) !== '' ? str(opts[i].value) : label;
        }
        return label;
      });
    }
    return list.join(', ');
  }

  var DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function formatDate(fmt, ts, offset) {
    var d = new Date((Number(ts) + (Number(offset) || 0)) * 1000);
    var Y = d.getUTCFullYear(), mo = d.getUTCMonth() + 1, day = d.getUTCDate(), H = d.getUTCHours(), i = d.getUTCMinutes(), s = d.getUTCSeconds(), w = d.getUTCDay();
    var h12 = H % 12 === 0 ? 12 : H % 12, out = '';
    Array.from(str(fmt)).slice(0, 32).forEach(function (c) {
      switch (c) {
        case 'Y': out += String(Y).padStart(4, '0'); break;
        case 'y': out += pad(Y % 100); break;
        case 'm': out += pad(mo); break;
        case 'n': out += mo; break;
        case 'd': out += pad(day); break;
        case 'j': out += day; break;
        case 'H': out += pad(H); break;
        case 'G': out += H; break;
        case 'h': out += pad(h12); break;
        case 'g': out += h12; break;
        case 'i': out += pad(i); break;
        case 's': out += pad(s); break;
        case 'A': out += H < 12 ? 'AM' : 'PM'; break;
        case 'a': out += H < 12 ? 'am' : 'pm'; break;
        case 'D': out += DAYS[w].slice(0, 3); break;
        case 'l': out += DAYS[w]; break;
        case 'M': out += MONTHS[mo - 1].slice(0, 3); break;
        case 'F': out += MONTHS[mo - 1]; break;
        case 'N': out += w === 0 ? 7 : w; break;
        case 'U': out += Math.floor(Number(ts)); break;
        case '-': case '/': case '.': case ':': case ',': case ' ': out += c; break;
        default: break;
      }
    });
    return out;
  }

  function stripTagsText(text) { return parse(text).filter(function (p) { return p.t === 'text'; }).map(function (p) { return p.v; }).join(''); }
  function urlTagMisplaced(template) {
    template = str(template);
    var cutAt = template.search(/[?#]/);
    var base = cutAt === -1 ? template : template.slice(0, cutAt);
    var m = /^[a-z][a-z0-9+.-]*:\/\/[^/]*/i.exec(base), auth = m ? m[0] : '';
    if (auth !== '' && tags(auth, true).length > 0) return true;
    return tags(base.slice(auth.length)).length > 0;
  }
  function sanitizeKey(k, v) {
    switch (k) {
      case 'prefill_key': v = isScalar(v) ? String(v).trim() : ''; return /^[A-Za-z0-9_-]{1,40}$/.test(v) ? v : '';
      case 'prefill_lock':
      case 'hdn_visitor_mail': return (v === 1 || v === '1' || v === true || v === 'true') ? '1' : '';
      case 'st_uses': return '';
    }
    return isScalar(v) ? String(v) : '';
  }

  /** Builder / AutoPilot: the save rules of the F08 keys of a whole form (twin of Smart_Tags::normalize_rows). */
  function normalizeRows(rows) {
    var seen = {}, uses = {};
    var addUses = function (text) { tags(text).forEach(function (t) { uses[t.ns] = true; if (t.ns === 'user' && t.arg === 'email') uses['user:email'] = true; }); };
    (rows || []).forEach(function (r, i) {
      if (!r || typeof r !== 'object') return;
      var type = typeof r.type === 'string' ? r.type : '';
      if (i > 0 && type === 'hiddenField') { r.value = cleanTemplate(r.value); delete r.required; }
      if (i > 0 && r.prefill_key !== undefined && r.prefill_key !== '') {
        var k = sanitizeKey('prefill_key', r.prefill_key);
        if (k === '' || !inArr(PREFILL_TYPES, type) || seen[k.toLowerCase()]) { delete r.prefill_key; delete r.prefill_lock; }
        else { r.prefill_key = k; seen[k.toLowerCase()] = true; uses.prefill = true; }
      } else if (i > 0 && r.prefill_lock !== undefined) { delete r.prefill_lock; }
      if (i > 0) {
        ['name', 'message', 'placeholder'].forEach(function (k2) { if (typeof r[k2] === 'string') addUses(r[k2]); });
        if (typeof r.value === 'string' && (type === 'hiddenField' || inArr(DEFAULT_TYPES, type) || type === 'option' || type === 'heading')) addUses(r.value);
      } else {
        ['email_sub', 'rePage'].forEach(function (k2) { if (typeof r[k2] === 'string') addUses(r[k2]); });
        if (r.thank_you_message && typeof r.thank_you_message === 'object') Object.keys(r.thank_you_message).forEach(function (k2) { if (typeof r.thank_you_message[k2] === 'string') addUses(r.thank_you_message[k2]); });
      }
    });
    if (rows && rows[0] && typeof rows[0] === 'object') {
      var list = NS.concat(['user:email', 'prefill']).filter(function (n) { return uses[n]; });
      if (list.length) rows[0].st_uses = list.join(','); else delete rows[0].st_uses;
    }
    return rows;
  }

  /* ---------------------------------------------------------------- page runtime */

  var doc = root.document;
  var users = {};          // form id -> user from forms/session/prepare
  var server = {};         // form id -> context pieces the page got (smart block of ajax_object_efm)
  var prefilled = {};      // form id -> {field id: true}
  var busy = false;

  function structure(fid) {
    try { if (typeof root.get_structure_by_form_id_efb === 'function') { var s = root.get_structure_by_form_id_efb(fid); if (Array.isArray(s)) return s; } } catch (e) {}
    try {
      var list = root.valj_efb_new || (root.ajax_object_efm && root.ajax_object_efm.ajax_value_forms) || [];
      for (var i = 0; i < list.length; i++) if (Number(list[i].id) === Number(fid)) return typeof list[i].form_structer === 'string' ? JSON.parse(list[i].form_structer) : list[i].form_structer;
    } catch (e2) {}
    if (Array.isArray(root.valj_efb)) return root.valj_efb;
    return [];
  }
  function smartBlock() {
    var o = root.ajax_object_efm || root.efb_var || {};
    return o && o.smart && typeof o.smart === 'object' ? o.smart : {};
  }
  function query() {
    var out = {};
    try {
      var sp = new URLSearchParams(root.location ? root.location.search : '');
      sp.forEach(function (v, k) {
        var key = k.replace(/[. ]/g, '_');
        if (/\[\]$/.test(key)) { key = key.slice(0, -2); if (!Array.isArray(out[key])) out[key] = []; out[key].push(v); }
        else out[key] = v;
      });
    } catch (e) {}
    return out;
  }
  function cookies() {
    var out = {};
    try {
      str(doc && doc.cookie).split(';').forEach(function (p) {
        var at = p.indexOf('=');
        if (at < 1) return;
        var k = p.slice(0, at).trim();
        try { out[k] = decodeURIComponent(p.slice(at + 1).trim()); } catch (e) { out[k] = p.slice(at + 1).trim(); }
      });
    } catch (e) {}
    return out;
  }
  /* The address the visitor came from when this tab first opened the site (not an earlier page of it). */
  function landingRef() {
    var r = '';
    try {
      r = root.sessionStorage.getItem('efb_landing_ref');
      if (r === null) {
        r = '';
        var ref = doc && doc.referrer ? String(doc.referrer) : '';
        if (ref && httpUrl(ref) && new URL(ref).host !== root.location.host) r = ref;
        root.sessionStorage.setItem('efb_landing_ref', r);
      }
    } catch (e) { r = ''; }
    return r || '';
  }
  function fieldsOf(rows) {
    var f = {};
    (rows || []).forEach(function (r, i) {
      if (i < 1 || !r || !r.id_ || !r.type || inArr(['option', 'r_matrix', 'step'], r.type)) return;
      f[String(r.id_)] = { type: String(r.type), name: str(r.name), options: [] };
    });
    (rows || []).forEach(function (r, i) {
      if (i < 1 || !r || r.type !== 'option' || !r.parent || !f[String(r.parent)]) return;
      f[String(r.parent)].options.push({ id: str(r.id_), label: str(r.value), value: str(r.opt_value) });
    });
    return f;
  }
  /* The answers of one form as tags read them (choices by their label, several as a list). */
  function valuesOf(fid, fields) {
    var vals = {};
    var rows = Array.isArray(root.sendBack_emsFormBuilder_pub) ? root.sendBack_emsFormBuilder_pub : [];
    rows.forEach(function (r) {
      if (!r || typeof r !== 'object' || r.id_ === undefined || Number(r.form_id) !== Number(fid)) return;
      var id = String(r.id_), type = str(r.type);
      if (inArr(['password', 'esign', 'maps', 'w_link', 'payment'], type) || (fields[id] && inArr(['password', 'esign', 'maps'], fields[id].type))) return;
      var v = r.value;
      if (!isScalar(v)) return;
      v = String(v);
      if (v === '@file@') v = str(r.fnm);
      v = v.replace(/@efb@dq#/g, '"').replace(/@n#/g, ' ');
      if (v.indexOf('@efb!') !== -1) { vals[id] = v.split('@efb!').map(function (x) { return x.trim(); }).filter(Boolean); return; }
      if (/checkbox/i.test(type) || Array.isArray(vals[id])) { if (!Array.isArray(vals[id])) vals[id] = vals[id] ? [vals[id]] : []; if (v !== '') vals[id].push(v); return; }
      vals[id] = v;
    });
    return vals;
  }
  function context(fid) {
    var rows = structure(fid);
    var sb = smartBlock();
    var fields = fieldsOf(rows);
    var loc = root.location || {};
    var s0 = rows[0] || {};
    var o = root.ajax_object_efm || {};
    return {
      query: query(),
      cookies: cookies(),
      ref: landingRef(),
      page: { url: str(loc.href).split('#')[0], path: str(loc.pathname), title: doc ? str(doc.title) : '', id: o.page_id ? String(o.page_id) : '' },
      post: sb.post && typeof sb.post === 'object' ? sb.post : {},
      user: users[Number(fid)] || null,
      now: Math.floor(Date.now() / 1000),
      tz: Number(sb.tz_offset) || 0,
      ip: null, uid: null,
      form: { id: String(fid), name: str(s0.formName) },
      site: sb.site && typeof sb.site === 'object' ? sb.site : {},
      fields: fields,
      values: valuesOf(fid, fields),
      entry: { track: '' }
    };
  }
  function formRoot(fid) { return doc ? doc.getElementById('body_efb_' + fid) : null; }
  function each(rootEl, sel, fn) { if (rootEl) Array.prototype.forEach.call(rootEl.querySelectorAll(sel), fn); }

  /* Fill the <bdi class="efb-st"> pieces (labels, descriptions, options, messages) with textContent. */
  function fillPieces(rootEl, ctx, where, only) {
    each(rootEl, '.efb-st[data-st]', function (el) {
      var raw = '{' + el.getAttribute('data-st') + '}';
      var p = parse(raw)[0];
      if (!p || p.t !== 'tag') return;
      if (only && !(p.ns === 'field' && keyOf(p) === 'field:' + only)) return;
      var w = el.getAttribute('data-st-where') || where || 'label';
      var v = resolve(raw, ctx, 'text', w);
      if (el.textContent !== v) el.textContent = v;
    });
  }

  /* Hidden fields: the template resolved here (for Conditional Logic and the visitor's own page); the
   * server rebuilds the value on submit. A field with a prefill_key takes the URL parameter first. */
  function bindHidden(fid, ctx, onlyField) {
    var rows = structure(fid), q = null;
    rows.forEach(function (r, i) {
      if (i < 1 || !r || r.type !== 'hiddenField') return;
      var tpl = str(r.value);
      if (onlyField && !tags(tpl).some(function (t) { return t.ns === 'field' && keyOf(t) === 'field:' + onlyField; })) return;
      var el = doc ? doc.getElementById(r.id_ + '_') : null;
      if (el && el.dataset && el.dataset.formid && Number(el.dataset.formid) !== Number(fid)) el = null;
      var v = resolve(tpl, ctx, 'text', 'hidden');
      if (r.prefill_key) {
        if (!q) q = prefillParams();
        var pv = q[r.prefill_key];
        if (pv && pv.length) { v = cleanValue(pv.join(', ')); (prefilled[fid] = prefilled[fid] || {})[r.id_] = true; }
      }
      v = cleanValue(v);
      if (el) el.value = v;
      if (el && el.getAttribute('data-st-done') === v) return;
      if (el) el.setAttribute('data-st-done', v);
      var row = { id_: r.id_, name: str(r.name), id_ob: r.id_ + '_', amount: r.amount, type: 'hiddenField', value: v, session: root.sessionPub_emsFormBuilder, form_id: Number(fid) };
      store(row, fid);
    });
  }
  function store(row, fid) {
    if (!Array.isArray(root.sendBack_emsFormBuilder_pub)) return;
    var list = root.sendBack_emsFormBuilder_pub;
    var at = -1;
    for (var i = 0; i < list.length; i++) if (list[i] && list[i].id_ === row.id_ && Number(list[i].form_id) === Number(fid)) { at = i; break; }
    if (row.value === '') { if (at !== -1) list.splice(at, 1); }
    else if (at === -1) list.push(row); else list[at].value = row.value;
    try { root.localStorage.setItem('sendback', typeof root.efb_sendback_for_storage_efb === 'function' ? root.efb_sendback_for_storage_efb(list) : JSON.stringify(list)); } catch (e) {}
  }

  /* Inputs whose default / placeholder holds tags the HTML could not carry (data-st-value / data-st-ph). */
  function bindDefaults(fid, ctx, rootEl) {
    each(rootEl, '[data-st-ph]', function (el) {
      var v = resolve(el.getAttribute('data-st-ph'), ctx, 'text', 'placeholder');
      if (el.getAttribute('placeholder') !== v) el.setAttribute('placeholder', v);
    });
    each(rootEl, '[data-st-value]', function (el) {
      if ((prefilled[fid] || {})[el.dataset.vid]) return;
      if (el.getAttribute('data-st-touched') === '1') return;
      var v = resolve(el.getAttribute('data-st-value'), ctx, 'text', 'default');
      if (el.tagName === 'TEXTAREA') v = v.replace(/@n#/g, '\n');
      if (v === '' || el.value === v) return;
      el.value = v;
      el.setAttribute('data-st-filled', '1');
      fire(el, fid);
    });
  }
  function fire(el, fid) {
    try {
      if (typeof root.handle_change_event_efb_v4 === 'function') { root.handle_change_event_efb_v4(el, fid); return; }
    } catch (e) {}
    try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch (e2) {}
  }

  /* ---- URL prefill (spec §4.5): only fields whose owner gave them a prefill_key. */
  function prefillParams() {
    var out = {};
    try {
      var sp = new URLSearchParams(root.location ? root.location.search : '');
      sp.forEach(function (v, k) {
        var key = k.replace(/\[\]$/, '');
        if (!/^[A-Za-z0-9_-]{1,40}$/.test(key)) return;
        (out[key] = out[key] || []).push(v);
      });
    } catch (e) {}
    return out;
  }
  function norm(s) {
    return str(s).replace(/[\u200B-\u200F\u202A-\u202E\u2066-\u2069\uFEFF\u00AD]/g, '').replace(/[\u06F0-\u06F9]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); })
      .replace(/[\u0660-\u0669]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); }).replace(/\s+/g, ' ').trim().toLowerCase();
  }
  /* Which option a value names: opt_value, then the option id, then the label (case, digits and invisible marks ignored). */
  function matchOption(options, v) {
    var n = norm(v);
    if (n === '') return null;
    var order = [function (o) { return norm(o.value) !== '' && norm(o.value) === n; }, function (o) { return norm(o.id) === n; }, function (o) { return norm(o.label) === n; }];
    for (var k = 0; k < order.length; k++) for (var i = 0; i < options.length; i++) if (order[k](options[i])) return options[i];
    return null;
  }
  function splitValues(list) {
    var out = [];
    (list || []).forEach(function (v) { str(v).split(',').forEach(function (x) { x = x.trim(); if (x !== '') out.push(x); }); });
    return out;
  }
  function applyPrefill(fid) {
    var rows = structure(fid), rootEl = formRoot(fid), q = prefillParams(), done = [];
    if (!rootEl) return done;
    var fields = fieldsOf(rows);
    rows.forEach(function (r, i) {
      if (i < 1 || !r || !r.prefill_key || !inArr(PREFILL_TYPES, r.type) || r.type === 'hiddenField') return;
      var vals = q[r.prefill_key];
      if (!vals || !vals.length) return;
      if (r.disabled == 1 || r.disabled === true) return;
      var ok = setField(fid, r, fields[r.id_] || { options: [] }, vals, rootEl);
      if (ok) {
        (prefilled[fid] = prefilled[fid] || {})[r.id_] = true;
        done.push(r.id_);
        if (r.prefill_lock === '1' || r.prefill_lock === 1) lock(r, rootEl);
      }
    });
    return done;
  }
  function byId(rootEl, id) { var el = doc.getElementById(id); return el && rootEl.contains(el) ? el : null; }
  function setField(fid, r, f, vals, rootEl) {
    var type = r.type, el = byId(rootEl, r.id_ + '_'), one = cleanValue(vals[vals.length - 1]);
    if (inArr(['text', 'firstName', 'lastName', 'email', 'tel', 'url', 'textarea', 'address_line', 'postalcode', 'mobile'], type)) {
      if (!el || one === '') return false;
      if (el.maxLength > 0) one = cut(one, el.maxLength);
      el.value = one; el.setAttribute('data-efb-prefilled', '1'); fire(el, fid); return true;
    }
    if (type === 'number' || type === 'range') {
      var n = norm(one).replace(/\u066B/g, '.').replace(/,/g, '');
      if (!el || !/^-?\d+(\.\d+)?$/.test(n)) return false;
      el.value = n; el.setAttribute('data-efb-prefilled', '1'); fire(el, fid); return true;
    }
    if (type === 'date') {
      var dv = norm(one);
      if (!el || !/^\d{4}-\d{2}-\d{2}$/.test(dv)) return false;
      el.value = dv; el.setAttribute('data-efb-prefilled', '1'); fire(el, fid); return true;
    }
    if (inArr(['select', 'conturyList', 'stateProvince', 'cityList'], type)) {
      if (!el || el.tagName !== 'SELECT') return false;
      var opts = Array.prototype.map.call(el.options, function (o) { return { id: str(o.id || o.getAttribute('data-id') || o.getAttribute('data-vid')), label: str(o.textContent), value: str(o.value), el: o }; });
      f.options.forEach(function (fo) { opts.forEach(function (o) { if (o.id === fo.id || o.label === fo.label) { o.id = fo.id; if (fo.value) o.value = fo.value; } }); });
      var hit = matchOption(opts.filter(function (o) { return o.el.id !== 'efbNotingSelected' && !o.el.disabled; }), one);
      if (!hit) return false;
      el.value = hit.el.value; el.setAttribute('data-efb-prefilled', '1'); fire(el, fid); return true;
    }
    if (inArr(['radio', 'imgRadio', 'checkbox', 'multiselect', 'yesNo'], type)) {
      var want = type === 'checkbox' || type === 'multiselect' ? splitValues(vals) : [one];
      var any = false;
      if (type === 'multiselect') return prefillMultiselect(fid, r, f, want, rootEl);
      if (type === 'yesNo') {
        var yn = norm(one), pick = /^(1|yes|y|true|on|\u0628\u0644\u0647|\u0646\u0639\u0645)$/.test(yn) ? 1 : (/^(0|no|n|false|off|\u062E\u06CC\u0631|\u0646\u0647|\u0644\u0627)$/.test(yn) ? 2 : 0);
        var btn = pick ? byId(rootEl, r.id_ + '_' + pick) : null;
        if (!btn) return false;
        btn.click(); return true;
      }
      want.forEach(function (w) {
        var hit2 = matchOption(f.options, w);
        if (!hit2) return;
        var input = byId(rootEl, hit2.id) || byId(rootEl, hit2.id + '_');
        if (!input || input.disabled || input.checked) { if (input && input.checked) any = true; return; }
        input.checked = true; input.setAttribute('data-efb-prefilled', '1'); fire(input, fid); any = true;
      });
      return any;
    }
    if (inArr(['rating', 'pointr5', 'pointr10'], type)) {
      var k = parseInt(norm(one), 10), max = type === 'pointr10' ? 10 : 5, min = type === 'pointr10' ? 0 : 1;
      if (isNaN(k) || k < min || k > max) return false;
      var star = rootEl.querySelector('[data-vid="' + r.id_ + '"][data-point="' + k + '"], [id="' + r.id_ + '_star' + k + '"], [data-id="' + r.id_ + '"][data-value="' + k + '"]');
      if (!star) return false;
      star.click(); return true;
    }
    return false;
  }
  function prefillMultiselect(fid, r, f, want, rootEl) {
    var labels = [];
    want.forEach(function (w) { var hit = matchOption(f.options, w); if (hit && labels.indexOf(hit.label) === -1) labels.push(hit.label); });
    if (!labels.length) return false;
    var box = rootEl.querySelector('.efblist.inplist[data-vid="' + r.id_ + '"]');
    if (box) { box.setAttribute('data-select', labels.join(',')); box.textContent = labels.join(','); }
    store({ id_: r.id_, name: str(r.name), id_ob: r.id_ + '_options', amount: r.amount, type: r.type, value: labels.join('@efb!'), session: root.sessionPub_emsFormBuilder, form_id: Number(fid) }, fid);
    return true;
  }
  function lock(r, rootEl) {
    var el = byId(rootEl, r.id_ + '_');
    if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA')) { el.readOnly = true; el.setAttribute('aria-readonly', 'true'); el.setAttribute('data-efb-readonly', '1'); }
    else if (el && el.tagName === 'SELECT') { el.setAttribute('aria-readonly', 'true'); el.setAttribute('data-efb-locked', '1'); el.addEventListener('mousedown', function (e) { e.preventDefault(); }); el.addEventListener('keydown', function (e) { if (e.key !== 'Tab') e.preventDefault(); }); }
  }

  /* The form uses user:* tags: forms/session/prepare answers with smart.user (no-store, same origin). */
  function wantsUser(fid) {
    var s0 = structure(fid)[0] || {};
    return str(s0.st_uses).split(',').indexOf('user') !== -1;
  }
  function loadUser(fid) {
    if (!root.EFBRuntime || typeof root.EFBRuntime.prepare !== 'function') return Promise.resolve(null);
    return Promise.resolve(root.EFBRuntime.prepare(Number(fid))).then(function (r) {
      if (r && r.smart && r.smart.user && typeof r.smart.user === 'object') { setUser(fid, r.smart.user); return r.smart.user; }
      return null;
    }, function () { return null; });
  }
  function setUser(fid, user) {
    users[Number(fid)] = user && user.id ? user : null;
    bindForm(fid, { noPrefill: true });
  }

  /** Everything the page fills for one form. */
  function bindForm(fid, opts) {
    opts = opts || {};
    var rootEl = formRoot(fid);
    if (!rootEl || busy) return;
    busy = true;
    try {
      var ctx = context(fid);
      if (!opts.noPrefill) applyPrefill(fid);
      bindHidden(fid, context(fid));
      ctx = context(fid);
      bindDefaults(fid, ctx, rootEl);
      fillPieces(rootEl, context(fid), 'label');
    } finally { busy = false; }
  }
  /** One answer changed: only the pieces and hidden fields that read it. */
  function refresh(fid, changedId) {
    if (busy) return;
    var rootEl = formRoot(fid);
    if (!rootEl) return;
    busy = true;
    try {
      var ctx = context(fid);
      fillPieces(rootEl, ctx, 'label', changedId ? String(changedId) : null);
      bindHidden(fid, ctx, changedId ? String(changedId) : null);
    } finally { busy = false; }
  }
  function init() {
    var forms = root.valj_efb_new || (root.ajax_object_efm && root.ajax_object_efm.ajax_value_forms) || [];
    var jobs = [];
    (Array.isArray(forms) ? forms : []).forEach(function (f) {
      var fid = Number(f.id);
      if (!fid) return;
      bindForm(fid);
      if (wantsUser(fid)) jobs.push(loadUser(fid));
    });
    return Promise.all(jobs);
  }

  /* ---- messages (thank-you, done): the static text as saved, each tag an isolated piece. */
  function messageMarkup(text) {
    text = str(text);
    if (text.indexOf('{') === -1) return text;
    return parse(text).map(function (p) {
      if (p.t === 'text') return p.v;
      var inner = p.legacy ? 'field:' + p.arg : p.raw.slice(1, -1);
      return '<bdi class="efb efb-st" data-st-where="message" data-st="' + esc(inner) + '">' + esc(cleanValue(p.fb)) + '</bdi>';
    }).join('');
  }
  /** Fill a message: the server's values first (resolved from the stored answers), else this page's. */
  function fillMessage(container, fid, serverValues, extra) {
    if (!container) return;
    var ctx = context(fid);
    if (extra && extra.track) ctx.entry = { track: String(extra.track) };
    each(container, '.efb-st[data-st]', function (el) {
      var key = el.getAttribute('data-st');
      var v;
      if (serverValues && typeof serverValues === 'object' && Object.prototype.hasOwnProperty.call(serverValues, key) && isScalar(serverValues[key])) v = cleanValue(serverValues[key]);
      else v = resolve('{' + key + '}', ctx, 'text', 'message');
      el.textContent = v;
    });
  }

  /* ---- builder canvas: tags shown as badges (textContent only). */
  function decorate(el, mode) {
    if (!el || !doc) return;
    var text = el.textContent;
    if (!text || text.indexOf('{') === -1 || !tags(text, false).length) return;
    var frag = doc.createDocumentFragment();
    parse(text).forEach(function (p) {
      if (p.t === 'text' || p.legacy) { frag.appendChild(doc.createTextNode(p.t === 'text' ? p.v : p.raw)); return; }
      var b = doc.createElement(mode === 'live' ? 'bdi' : 'span');
      if (mode === 'live') { b.className = 'efb efb-st'; b.setAttribute('data-st', p.raw.slice(1, -1)); b.textContent = cleanValue(p.fb); }
      else { b.className = 'efb badge bg-light text-dark border efb-st-badge'; b.setAttribute('dir', 'ltr'); b.textContent = p.raw; }
      frag.appendChild(b);
    });
    while (el.firstChild) el.removeChild(el.firstChild);
    el.appendChild(frag);
  }

  var api = {
    NS: NS, ARGS: ARGS, MAX: MAX, PUBLIC_NS: PUBLIC_NS, PREFILL_TYPES: PREFILL_TYPES, DEFAULT_TYPES: DEFAULT_TYPES, FIELD_KEYS: FIELD_KEYS, PRESETS: PRESETS,
    allowed: allowed, parse: parse, tags: tags, hasTags: hasTags, keyOf: keyOf, classify: classify, cleanTemplate: cleanTemplate, cleanValue: cleanValue,
    resolve: resolve, value: value, fieldValue: fieldValue, cookieOk: cookieOk, httpUrl: httpUrl, formatDate: formatDate, esc: esc, rawurlencode: rawurlencode,
    stripTagsText: stripTagsText, urlTagMisplaced: urlTagMisplaced, sanitizeKey: sanitizeKey, normalizeRows: normalizeRows,
    // page
    context: context, bind: bindForm, refresh: refresh, init: init, applyPrefill: applyPrefill, setUser: setUser, loadUser: loadUser,
    isPrefilled: function (fid, id) { return !!(prefilled[Number(fid)] || {})[String(id)]; },
    messageMarkup: messageMarkup, fillMessage: fillMessage, decorate: decorate, matchOption: matchOption, fieldsOf: fieldsOf, valuesOf: valuesOf,
    _server: server
  };
  root.EFBSmart = api;
  if (typeof module === 'object' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
