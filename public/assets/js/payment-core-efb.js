/**
 * PAY00: the pure core of the payment field and of the amount shown to the visitor.
 *
 * Twin of includes/payments/payment-field-functions-efb.php (Field) and
 * includes/payments/class-Emsfb-payment-money.php / class-Emsfb-payment-pricing.php (Money, Pricing):
 * change one, change the other. tests/fixtures/pay-field-vectors.json and pay-pricing-vectors.json
 * hold both to the same output byte for byte (node tests/test-pay-field-parity.js,
 * node tests/test-pay-pricing-parity.js).
 *
 * No DOM. Loaded in the builder (canvas, preview) and on the public page (live total). The quote
 * here is for display only: the amount a gateway charges is computed on the server.
 */
(function (root) {
   'use strict';

   var ELEMENTS = ['summary', 'amount', 'coupon', 'methods', 'fields', 'button', 'secure', 'logos'];
   var HIDEABLE = ['summary', 'amount', 'coupon', 'methods', 'secure', 'logos'];
   var ICONS = ['none', 'bi-lock', 'bi-lock-fill', 'bi-credit-card', 'bi-wallet2', 'bi-qr-code', 'bi-bag-check', 'bi-heart'];
   var TEXT_KEYS = ['btn', 'secure', 'summary', 'methods', 'pending', 'success', 'fail', 'redirect', 'test', 'amount', 'other', 'total'];
   var KEYS = ['pf_btn_text', 'pf_btn_bg', 'pf_btn_fg', 'pf_btn_bg_h', 'pf_btn_fg_h', 'pf_btn_radius', 'pf_btn_size', 'pf_btn_width',
      'pf_btn_icon', 'pf_btn_icon_pos', 'pf_order', 'pf_hide', 'pf_layout', 'pf_methods_style', 'pf_methods', 'pf_summary',
      'pf_label_pos', 'pf_card_bg', 'pf_card_border', 'pf_card_radius', 'pf_accent', 'pf_logo_size', 'pf_digits', 'pf_i18n_b64',
      'pf_secure_text', 'pf_t_summary', 'pf_t_methods', 'pf_t_pending', 'pf_t_success', 'pf_t_fail', 'pf_t_amount',
      'pf_fixed', 'pf_fixed_label', 'pf_amount', 'pf_presets', 'pf_other', 'pf_default', 'pf_min', 'pf_max',
      'mobile_pf_order', 'mobile_pf_hide', 'mobile_pf_btn_size', 'mobile_pf_btn_width', 'mobile_pf_methods_style'];
   var FORM_KEYS = ['pay_act_admin_mail', 'pay_act_user_mail', 'pay_act_sms', 'pay_act_tlg', 'pay_act_gs', 'pay_act_wh', 'pay_fail_notify'];
   var NAMED = { primary: '#0d6efd', success: '#198754', secondary: '#6c757d', danger: '#ff455f', warning: '#e9c31a', info: '#31d2f2', light: '#fbfbfb', darkb: '#202a8d', pinkEfb: '#ff4b93', dark: '#212529', white: '#ffffff' };
   var SIZES = { sm: ['.4rem .85rem', '.875rem'], md: ['.65rem 1.1rem', '1rem'], lg: ['.85rem 1.4rem', '1.15rem'] };
   var EXPONENTS = {
      BIF: 0, CLP: 0, DJF: 0, GNF: 0, ISK: 0, JPY: 0, KMF: 0, KRW: 0, PYG: 0, RWF: 0, UGX: 0, UYI: 0, VND: 0, VUV: 0, XAF: 0, XOF: 0, XPF: 0,
      BHD: 3, IQD: 3, JOD: 3, KWD: 3, LYD: 3, OMR: 3, TND: 3, CLF: 4, UYW: 4
   };
   var DIGITS = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9', '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9', '٫': '.', '٬': '' };

   /* PHP trim()'s default set (JS trim() also strips Unicode spaces such as U+2028). */
   var ptrim = function (s) { return String(s).replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, ''); };
   var has = function (o, k) { return o !== null && typeof o === 'object' && Object.prototype.hasOwnProperty.call(o, k); };
   var get = function (row, k) { return has(row, k) ? row[k] : null; };

   /* ---- money ---------------------------------------------------------- */

   function currencyCode(iso) {
      var s = ptrim(iso == null ? '' : iso).toUpperCase();
      return /^[A-Z]{3}$/.test(s) ? s : '';
   }
   function exponent(iso) {
      var c = currencyCode(iso);
      return has(EXPONENTS, c) ? EXPONENTS[c] : 2;
   }
   function latinDigits(s) {
      return String(s).replace(/[۰-۹٠-٩٫٬]/g, function (d) { return DIGITS[d]; });
   }
   function fromDecimal(value, currency) {
      var s;
      if (typeof value === 'boolean' || value == null) return null;
      if (typeof value === 'number') {
         if (!isFinite(value)) return null;
         s = Number.isInteger(value) ? String(value) : value.toFixed(10).replace(/0+$/, '').replace(/\.$/, '');
      } else if (typeof value === 'string') {
         s = latinDigits(ptrim(value));
         if (s.indexOf(',') !== -1 && s.indexOf('.') === -1) s = s.replace(/,/g, '.');
      } else return null;
      var m = s.match(/^(-?)(\d{1,15})(?:\.(\d{0,12}))?$/);
      if (!m) {
         var m2 = s.match(/^(-?)\.(\d{1,12})$/);
         if (!m2) return null;
         m = [s, m2[1], '0', m2[2]];
      }
      var neg = m[1] === '-';
      var intp = m[2].replace(/^0+/, '');
      var frac = m[3] || '';
      var e = exponent(currency);
      var keep = (frac + '0'.repeat(e)).slice(0, e);
      var next = frac.length > e ? Number(frac.charAt(e)) : 0;
      var n = Number((((intp === '' ? '0' : intp) + keep).replace(/^0+/, '')) || '0');
      if (next >= 5) n++;
      return neg ? -n : n;
   }
   function toDecimal(minor, currency) {
      minor = Math.trunc(Number(minor) || 0);
      var e = exponent(currency);
      var neg = minor < 0;
      var s = String(Math.abs(minor));
      if (e > 0) {
         while (s.length < e + 1) s = '0' + s;
         s = s.slice(0, -e) + '.' + s.slice(-e);
      }
      return (neg ? '-' : '') + s;
   }
   /** Display only. digits 'latn' keeps 0-9 in every language; 'locale' uses the language's digits. */
   function format(minor, currency, locale, digits) {
      var cur = currencyCode(currency);
      var dec = toDecimal(minor, cur);
      try {
         var loc = String(locale || 'en').replace('_', '-');
         var opt = { style: 'currency', currency: cur, minimumFractionDigits: exponent(cur), maximumFractionDigits: exponent(cur) };
         if (digits !== 'locale') opt.numberingSystem = 'latn';
         return new Intl.NumberFormat(loc, opt).format(Number(dec));
      } catch (e) {
         return (dec + ' ' + cur).trim();
      }
   }

   /* ---- sanitize ------------------------------------------------------- */

   function hex(v) {
      if (typeof v !== 'string') return '';
      var m = ptrim(v).match(/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/);
      if (!m) return '';
      var h = m[1].toLowerCase();
      if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
      return '#' + h;
   }
   function int(v, min, max) {
      var n;
      if (typeof v === 'boolean') return '';
      if (typeof v === 'number') { if (!isFinite(v)) return ''; n = v; }
      else if (typeof v === 'string' && /^-?\d{1,6}(\.\d{1,6})?$/.test(ptrim(v))) n = parseFloat(ptrim(v));
      else return '';
      n = Math.floor(n + 0.5);
      return String(Math.max(min, Math.min(max, n)));
   }
   function text(v, max) {
      if (typeof v !== 'string') return '';
      v = v.replace(/[\u0000-\u001F\u007F\u2028\u2029<>]/g, ' ').replace(/ +/g, ' ').replace(/^ +| +$/g, '');
      var cps = Array.from(v);
      if (cps.length > max) v = cps.slice(0, max).join('');
      return v.replace(/^ +| +$/g, '');
   }
   function decimal(v) {
      if (typeof v === 'number' && Number.isInteger(v)) v = String(v);
      if (typeof v !== 'string') return '';
      v = latinDigits(ptrim(v));
      return /^\d{1,9}(\.\d{1,4})?$/.test(v) ? v : '';
   }
   function idList(v, allowed) {
      if (typeof v !== 'string') return '';
      var out = [];
      v.split(',').forEach(function (p) { p = ptrim(p); if (allowed.indexOf(p) !== -1 && out.indexOf(p) === -1) out.push(p); });
      return out.join(',');
   }
   function presets(v) {
      if (typeof v !== 'string' || v === '') return [];
      var out = [];
      var parts = v.split(',');
      for (var i = 0; i < parts.length; i++) {
         var d = decimal(parts[i]);
         if (d !== '' && parseFloat(d) > 0 && out.indexOf(d) === -1) out.push(d);
         if (out.length >= 8) break;
      }
      return out;
   }
   function utf8Encode(s) {
      if (typeof TextEncoder !== 'undefined') return new TextEncoder().encode(s);
      return Buffer.from(s, 'utf8');
   }
   function utf8Decode(bytes) {
      try {
         if (typeof TextDecoder !== 'undefined') return new TextDecoder('utf-8', { fatal: true }).decode(bytes);
      } catch (e) { return null; }
      return Buffer.from(bytes).toString('utf8');
   }
   function b64urlEncode(s) {
      var bytes = utf8Encode(s);
      var bin = '';
      for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
      var b = typeof btoa === 'function' ? btoa(bin) : Buffer.from(bin, 'binary').toString('base64');
      return b.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
   }
   function b64urlDecode(s) {
      if (typeof s !== 'string' || s === '' || s.length > 40000 || !/^[A-Za-z0-9_-]+$/.test(s)) return null;
      if (s.length % 4 === 1) return null;
      var b = s.replace(/-/g, '+').replace(/_/g, '/');
      while (b.length % 4) b += '=';
      var bin;
      try { bin = typeof atob === 'function' ? atob(b) : Buffer.from(b, 'base64').toString('binary'); } catch (e) { return null; }
      var bytes = new Uint8Array(bin.length);
      for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
      return utf8Decode(bytes);
   }
   function i18nDecode(v) {
      var json = b64urlDecode(v);
      if (json === null) return {};
      var d;
      try { d = JSON.parse(json); } catch (e) { return {}; }
      if (!d || typeof d !== 'object') return {};
      var out = {};
      var n = 0;
      var keys = Array.isArray(d) ? d.map(function (x, i) { return i; }) : Object.keys(d);
      for (var i = 0; i < keys.length; i++) {
         if (n >= 30) break;
         var loc = keys[i], texts = d[loc];
         if (typeof loc !== 'string' || !/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/.test(loc) || !texts || typeof texts !== 'object' || Array.isArray(texts)) continue;
         var clean = {}, any = false;
         TEXT_KEYS.forEach(function (k) {
            if (has(texts, k)) { var t = text(texts[k], 200); if (t !== '') { clean[k] = t; any = true; } }
         });
         if (any) { out[loc] = clean; n++; }
      }
      return out;
   }
   function i18nEncode(d) {
      if (!d || !Object.keys(d).length) return '';
      return b64urlEncode(JSON.stringify(d));
   }

   function sanitizeKey(k, v) {
      var base = k.indexOf('mobile_') === 0 ? k.slice(7) : k;
      var isStr = typeof v === 'string';
      switch (base) {
         case 'pf_btn_text': case 'pf_fixed_label': return text(v, 60);
         case 'pf_secure_text': case 'pf_t_summary': case 'pf_t_methods': case 'pf_t_pending': case 'pf_t_success': case 'pf_t_fail': case 'pf_t_amount': return text(v, 200);
         case 'pf_btn_fg': return v === 'auto' ? 'auto' : hex(v);
         case 'pf_btn_bg': case 'pf_btn_bg_h': case 'pf_btn_fg_h': case 'pf_card_bg': case 'pf_card_border': case 'pf_accent': return hex(v);
         case 'pf_btn_radius': return int(v, 0, 40);
         case 'pf_card_radius': return int(v, 0, 24);
         case 'pf_btn_size': return isStr && ['sm', 'md', 'lg'].indexOf(v) !== -1 ? v : '';
         case 'pf_btn_width': return isStr && ['auto', 'full'].indexOf(v) !== -1 ? v : '';
         case 'pf_btn_icon': return isStr && ICONS.indexOf(v) !== -1 ? v : '';
         case 'pf_btn_icon_pos': return isStr && ['start', 'end'].indexOf(v) !== -1 ? v : '';
         case 'pf_order': return idList(v, ELEMENTS);
         case 'pf_hide': return idList(v, HIDEABLE);
         case 'pf_layout': return isStr && ['stack', 'split'].indexOf(v) !== -1 ? v : '';
         case 'pf_methods_style': return isStr && ['list', 'grid', 'tabs', 'select'].indexOf(v) !== -1 ? v : '';
         case 'pf_methods': {
            if (!isStr) return '';
            var out = [];
            var parts = v.split(',');
            for (var i = 0; i < parts.length; i++) {
               var p = ptrim(parts[i]);
               if (/^[a-z0-9_]{1,30}$/.test(p) && out.indexOf(p) === -1) out.push(p);
               if (out.length >= 20) break;
            }
            return out.join(',');
         }
         case 'pf_summary': return isStr && ['total', 'lines'].indexOf(v) !== -1 ? v : '';
         case 'pf_label_pos': return isStr && ['top', 'start', 'hidden'].indexOf(v) !== -1 ? v : '';
         case 'pf_logo_size': return isStr && ['sm', 'md'].indexOf(v) !== -1 ? v : '';
         case 'pf_digits': return isStr && ['latn', 'locale'].indexOf(v) !== -1 ? v : '';
         case 'pf_i18n_b64': return i18nEncode(i18nDecode(v));
         case 'pf_fixed': case 'pf_default': case 'pf_min': case 'pf_max': return decimal(v);
         case 'pf_amount': return v === 'donation' ? 'donation' : '';
         case 'pf_presets': return presets(v).join(',');
         case 'pf_other': return v === 1 || v === '1' || v === true ? '1' : '';
         case 'pay_act_admin_mail': case 'pay_act_user_mail': case 'pay_act_sms': case 'pay_act_tlg': case 'pay_act_gs': case 'pay_act_wh':
            return isStr && ['paid', 'submit', 'both'].indexOf(v) !== -1 ? v : '';
         case 'pay_fail_notify': return v === 'admin' ? 'admin' : '';
      }
      return '';
   }

   /* ---- colour --------------------------------------------------------- */

   function rgb(h) { return [parseInt(h.substr(1, 2), 16), parseInt(h.substr(3, 2), 16), parseInt(h.substr(5, 2), 16)]; }
   function luminance(h) {
      var l = rgb(h).map(function (c) { c = c / 255; return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); });
      return 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2];
   }
   function ratio(a, b) {
      var x = luminance(a) + 0.05, y = luminance(b) + 0.05;
      return Math.max(x, y) / Math.min(x, y);
   }
   function contrast(a, b) { return Math.round(ratio(a, b) * 100) / 100; }
   function autoText(bg) { return ratio('#000000', bg) >= ratio('#ffffff', bg) ? '#000000' : '#ffffff'; }
   function darken(h, pct) {
      pct = pct == null ? 8 : pct;
      return '#' + rgb(h).map(function (c) { return ('0' + Math.floor(c * (100 - pct) / 100 + 0.5).toString(16)).slice(-2); }).join('');
   }
   function formButtonHex(form0) {
      var v = get(form0, 'button_color');
      if (typeof v === 'string') {
         var m = v.match(/#[0-9a-fA-F]{6}\b/);
         if (m) return m[0].toLowerCase();
         m = v.match(/(?:^|\s)btn-([A-Za-z]+)/);
         if (m && has(NAMED, m[1])) return NAMED[m[1]];
      }
      return '#202a8d';
   }

   /* ---- resolve / texts ------------------------------------------------ */

   function resolve(row, form0, opts) {
      opts = opts || {};
      var s = function (k) { return sanitizeKey(k, get(row, k)); };
      var bg = s('pf_btn_bg') || formButtonHex(form0);
      var fg = s('pf_btn_fg');
      if (fg === '' || fg === 'auto') fg = autoText(bg);
      var bgH = s('pf_btn_bg_h') || darken(bg, 8);
      var fgH = s('pf_btn_fg_h') || fg;
      var accent = s('pf_accent') || bg;
      var radius = s('pf_btn_radius');
      var cardRadius = s('pf_card_radius');
      var avail = Array.isArray(opts.methods) ? opts.methods : [];
      var methods = [];
      var allowed = s('pf_methods');
      if (allowed !== '') {
         allowed.split(',').forEach(function (mid) { avail.forEach(function (m) { if (String(m[0]) === mid) methods.push([String(m[0]), String(m[1])]); }); });
      } else {
         avail.forEach(function (m) { methods.push([String(m[0]), String(m[1])]); });
      }
      var logos = Array.isArray(opts.logos) ? opts.logos : [];
      var amountMode = s('pf_amount');
      var orderOf = function (orderS, hideS) {
         var order = orderS === '' ? [] : orderS.split(',');
         ELEMENTS.forEach(function (e) { if (order.indexOf(e) === -1) order.push(e); });
         var hide = hideS === '' ? [] : hideS.split(',');
         return order.filter(function (e) {
            if (hide.indexOf(e) !== -1) return false;
            if (e === 'coupon') return false;
            if (e === 'amount' && amountMode !== 'donation') return false;
            if (e === 'methods' && methods.length < 2) return false;
            if (e === 'logos' && !logos.length) return false;
            return true;
         });
      };
      var r = {
         btn: {
            bg: bg, fg: fg, bg_h: bgH, fg_h: fgH,
            radius: radius === '' ? 8 : parseInt(radius, 10),
            size: s('pf_btn_size') || 'md',
            width: s('pf_btn_width') || 'full',
            icon: s('pf_btn_icon') || 'bi-lock',
            icon_pos: s('pf_btn_icon_pos') || 'start'
         },
         order: orderOf(s('pf_order'), s('pf_hide')),
         layout: s('pf_layout') || 'stack',
         methods_style: s('pf_methods_style') || 'list',
         methods: methods,
         logos: logos,
         summary: s('pf_summary') || 'lines',
         label_pos: s('pf_label_pos') || 'top',
         card_bg: s('pf_card_bg'),
         card_border: s('pf_card_border'),
         card_radius: cardRadius === '' ? 12 : parseInt(cardRadius, 10),
         accent: accent,
         logo_size: s('pf_logo_size') || 'sm',
         digits: s('pf_digits') || 'latn',
         amount: {
            mode: amountMode,
            presets: presets(typeof get(row, 'pf_presets') === 'string' ? get(row, 'pf_presets') : ''),
            other: s('pf_other') === '1',
            default: s('pf_default'),
            min: s('pf_min'),
            max: s('pf_max'),
            fixed: s('pf_fixed')
         },
         mobile: null
      };
      if (opts.mobile) {
         var mo = s('mobile_pf_order'), mh = s('mobile_pf_hide');
         var m = {
            order: (mo !== '' || mh !== '') ? orderOf(mo !== '' ? mo : s('pf_order'), mh !== '' ? mh : s('pf_hide')) : null,
            size: s('mobile_pf_btn_size'),
            width: s('mobile_pf_btn_width'),
            methods_style: s('mobile_pf_methods_style')
         };
         if (m.order !== null || m.size !== '' || m.width !== '' || m.methods_style !== '') r.mobile = m;
      }
      return r;
   }

   function texts(row, locale, defaults) {
      var i18n = i18nDecode(typeof get(row, 'pf_i18n_b64') === 'string' ? get(row, 'pf_i18n_b64') : '');
      locale = String(locale || '');
      var lang = locale.indexOf('_') !== -1 ? locale.slice(0, locale.indexOf('_')) : locale;
      var own = { btn: 'pf_btn_text', secure: 'pf_secure_text', summary: 'pf_t_summary', methods: 'pf_t_methods', pending: 'pf_t_pending', success: 'pf_t_success', fail: 'pf_t_fail', amount: 'pf_t_amount' };
      var out = {};
      TEXT_KEYS.forEach(function (k) {
         var v = '';
         if (has(i18n, locale) && has(i18n[locale], k)) v = i18n[locale][k];
         else if (has(i18n, lang) && has(i18n[lang], k)) v = i18n[lang][k];
         else if (has(own, k)) v = sanitizeKey(own[k], get(row, own[k]));
         if (v === '') v = defaults && has(defaults, k) ? text(String(defaults[k]), 200) : '';
         out[k] = v;
      });
      return out;
   }

   /* ---- css / html ----------------------------------------------------- */

   function css(r, id) {
      var sel = '.efb-pf[data-pf="' + id + '"]';
      var v = [
         '--efb-pf-btn-bg:' + r.btn.bg, '--efb-pf-btn-fg:' + r.btn.fg, '--efb-pf-btn-bg-h:' + r.btn.bg_h, '--efb-pf-btn-fg-h:' + r.btn.fg_h,
         '--efb-pf-btn-radius:' + r.btn.radius + 'px', '--efb-pf-btn-pad:' + SIZES[r.btn.size][0], '--efb-pf-btn-fs:' + SIZES[r.btn.size][1],
         '--efb-pf-btn-w:' + (r.btn.width === 'full' ? '100%' : 'auto'), '--efb-pf-accent:' + r.accent, '--efb-pf-card-radius:' + r.card_radius + 'px'
      ];
      if (r.card_bg !== '') v.push('--efb-pf-card-bg:' + r.card_bg);
      if (r.card_border !== '') v.push('--efb-pf-card-border:' + r.card_border);
      var out = sel + '{' + v.join(';') + '}';
      var m = r.mobile;
      if (m) {
         var inner = [], mv = [];
         if (m.size !== '') { mv.push('--efb-pf-btn-pad:' + SIZES[m.size][0]); mv.push('--efb-pf-btn-fs:' + SIZES[m.size][1]); }
         if (m.width !== '') mv.push('--efb-pf-btn-w:' + (m.width === 'full' ? '100%' : 'auto'));
         if (mv.length) inner.push(sel + '{' + mv.join(';') + '}');
         if (m.order !== null) {
            ELEMENTS.forEach(function (e) {
               var pos = m.order.indexOf(e);
               inner.push(sel + ' [data-pf-el="' + e + '"]{' + (pos === -1 ? 'display:none' : 'order:' + (pos + 1)) + '}');
            });
         }
         if (inner.length) out += '@media (max-width:767.98px){' + inner.join('') + '}';
      }
      return out;
   }

   function esc(s) {
      return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
   }

   function html(r, t, ctx) {
      var id = esc(ctx.id);
      var o = '<div class="efb efb-pf" data-pf="' + id + '" data-gw="' + esc(ctx.gateway) + '" data-formid="' + esc(ctx.form_id) + '" data-layout="' + r.layout + '" data-label-pos="' + r.label_pos + '" data-digits="' + r.digits + '" role="group" aria-labelledby="' + id + '_pflab">';
      o += '<div class="efb efb-pf-label' + (r.label_pos === 'hidden' ? ' efb-pf-sr' : '') + '" id="' + id + '_pflab">' + esc(ctx.label) + '</div>';
      o += '<div class="efb efb-pf-test" data-pf-el="test" role="note"' + (ctx.test ? '' : ' hidden') + '><i class="efb bi-cone-striped" aria-hidden="true"></i> ' + esc(t.test) + '</div>';
      o += '<div class="efb efb-pf-body">';
      r.order.forEach(function (el) {
         switch (el) {
            case 'summary':
               o += '<div class="efb efb-pf-el efb-pf-summary" data-pf-el="summary" data-pf-mode="' + r.summary + '"><div class="efb efb-pf-sum-title">' + esc(t.summary) + '</div>' +
                  (r.summary === 'lines' ? '<ul class="efb efb-pf-lines" data-pf-lines></ul>' : '') +
                  '<div class="efb efb-pf-total"><span>' + esc(t.total) + '</span> <bdi data-pf-total></bdi></div></div>';
               break;
            case 'amount':
               o += '<div class="efb efb-pf-el efb-pf-amount" data-pf-el="amount" role="radiogroup" aria-label="' + esc(t.amount) + '"' +
                  (r.amount.default !== '' ? ' data-pf-default="' + r.amount.default + '"' : '') +
                  (r.amount.min !== '' ? ' data-pf-min="' + r.amount.min + '"' : '') +
                  (r.amount.max !== '' ? ' data-pf-max="' + r.amount.max + '"' : '') + '>';
               r.amount.presets.forEach(function (p) {
                  var on = p === r.amount.default;
                  o += '<button type="button" class="efb efb-pf-chip" role="radio" aria-checked="' + (on ? 'true' : 'false') + '" data-pf-preset="' + p + '"><bdi>' + p + '</bdi></button>';
               });
               if (r.amount.other || !r.amount.presets.length) {
                  o += '<label class="efb efb-pf-other"><span class="efb efb-pf-other-t">' + esc(t.other) + '</span><input type="text" inputmode="decimal" autocomplete="off" dir="ltr" class="efb efb-pf-other-in" data-pf-other></label>';
               }
               o += '</div>';
               break;
            case 'methods':
               o += '<div class="efb efb-pf-el efb-pf-methods" data-pf-el="methods" data-style="' + r.methods_style + '">';
               o += '<div class="efb efb-pf-sum-title" id="' + id + '_pfmet">' + esc(t.methods) + '</div>';
               if (r.methods_style === 'select') {
                  o += '<select class="efb efb-pf-method-select" data-pf-method-select aria-labelledby="' + id + '_pfmet">';
                  r.methods.forEach(function (m, i) { o += '<option value="' + esc(m[0]) + '"' + (i === 0 ? ' selected' : '') + '>' + esc(m[1]) + '</option>'; });
                  o += '</select>';
               } else {
                  o += '<div class="efb efb-pf-method-list" role="radiogroup" aria-labelledby="' + id + '_pfmet">';
                  r.methods.forEach(function (m, i) {
                     o += '<button type="button" class="efb efb-pf-method" role="radio" aria-checked="' + (i === 0 ? 'true' : 'false') + '" tabindex="' + (i === 0 ? '0' : '-1') + '" data-pf-method="' + esc(m[0]) + '">' + esc(m[1]) + '</button>';
                  });
                  o += '</div>';
               }
               o += '</div>';
               break;
            case 'fields':
               o += '<div class="efb efb-pf-el efb-pf-fields" data-pf-el="fields" data-pf-slot="fields">' + (ctx.preview ? '<div class="efb efb-pf-placeholder" data-pf-preview="1">' + esc(ctx.placeholder || '') + '</div>' : '') + '</div>';
               break;
            case 'button': {
               var icon = r.btn.icon === 'none' ? '' : '<i class="efb ' + r.btn.icon + '" aria-hidden="true"></i>';
               var label = esc(t.btn).split('%amount%').join('<bdi data-pf-amount></bdi>');
               o += '<div class="efb efb-pf-el efb-pf-btn-wrap" data-pf-el="button"><button type="button" class="efb efb-pf-btn" data-pf-pay data-icon-pos="' + r.btn.icon_pos + '"' +
                  (r.order.indexOf('secure') !== -1 ? ' aria-describedby="' + id + '_pfsec"' : '') + '>' +
                  (r.btn.icon_pos === 'start' ? icon : '') + '<span class="efb efb-pf-btn-t">' + label + '</span>' + (r.btn.icon_pos === 'end' ? icon : '') + '</button></div>';
               break;
            }
            case 'secure':
               o += '<div class="efb efb-pf-el efb-pf-secure" data-pf-el="secure" id="' + id + '_pfsec"><i class="efb bi-shield-lock" aria-hidden="true"></i> ' + esc(t.secure) + '</div>';
               break;
            case 'logos':
               o += '<div class="efb efb-pf-el efb-pf-logos" data-pf-el="logos" data-size="' + r.logo_size + '">';
               r.logos.forEach(function (l) { o += '<img class="efb efb-pf-logo" src="' + esc(l[0]) + '" alt="' + esc(l[1]) + '" loading="lazy" decoding="async">'; });
               o += '</div>';
               break;
         }
      });
      o += '</div><div class="efb efb-pf-status" role="status" aria-live="polite" data-pf-status tabindex="-1"></div></div>';
      return o;
   }

   /* ---- quote (display twin of Pricing::quote without B7) -------------- */

   var PAY_TYPES = ['payRadio', 'paySelect', 'payCheckbox', 'payMultiselect', 'prcfld'];
   var decodeEntities = function (s) {
      return String(s).replace(/&(amp|lt|gt|quot|#039|#39|apos);/g, function (m, e) { return { amp: '&', lt: '<', gt: '>', quot: '"', '#039': "'", '#39': "'", apos: "'" }[e]; });
   };

   function donationMinor(pf, given, currency) {
      var pre = [];
      presets(typeof get(pf, 'pf_presets') === 'string' ? get(pf, 'pf_presets') : '').forEach(function (p) { var m = fromDecimal(p, currency); if (m !== null && m > 0) pre.push(m); });
      var other = sanitizeKey('pf_other', get(pf, 'pf_other')) === '1';
      var minS = sanitizeKey('pf_min', get(pf, 'pf_min')), maxS = sanitizeKey('pf_max', get(pf, 'pf_max'));
      var min = minS !== '' ? fromDecimal(minS, currency) : null, max = maxS !== '' ? fromDecimal(maxS, currency) : null;
      if (given === null) {
         var def = sanitizeKey('pf_default', get(pf, 'pf_default'));
         if (def === '') return 0;
         given = def;
      }
      var m = fromDecimal(given, currency);
      if (m === null || m <= 0) return 'pwyw_out_of_range';
      if (pre.indexOf(m) !== -1) return m;
      if (!other && pre.length) return 'pwyw_out_of_range';
      if ((min !== null && m < min) || (max !== null && m > max)) return 'pwyw_out_of_range';
      return m;
   }

   function quote(form, rows, ctx) {
      ctx = ctx || {};
      var err = function (code, fid) { var e = { ok: false, error: code }; if (fid) e.field_id = fid; return e; };
      var item = function (id, label, minor, kind) { return { id: String(id), label: String(label), qty: 1, unit_minor: minor, total_minor: minor, kind: kind }; };
      var form0 = form && form[0] ? form[0] : {};
      var currency = currencyCode(get(form0, 'currency'));
      if (currency === '') return err('currency_unsupported');
      var supports = ctx.supports || {};
      if (supports.currencies && supports.currencies.length && supports.currencies.map(function (c) { return String(c).toUpperCase(); }).indexOf(currency) === -1) return err('currency_unsupported');
      var fields = {};
      form.forEach(function (r, i) { if (i > 0 && r && typeof r === 'object' && has(r, 'id_')) fields[String(r.id_)] = r; });
      var optionBy = function (parent, ob, txt) {
         if (ob !== '' && has(fields, ob) && String(fields[ob].parent || '') === parent && fields[ob].type === 'option') return fields[ob];
         if (txt == null || typeof txt === 'object') return null;
         var t = decodeEntities(txt);
         for (var k in fields) {
            var f = fields[k];
            if (f.type === 'option' && String(f.parent || '') === parent && has(f, 'value') && decodeEntities(f.value) === t) return f;
         }
         return null;
      };
      var priceMinor = function (opt) {
         if (!has(opt, 'price') || (typeof opt.price === 'string' && ptrim(opt.price) === '')) return 0;
         var m = fromDecimal(typeof opt.price === 'string' ? ptrim(opt.price) : opt.price, currency);
         return m === null || m < 0 ? null : m;
      };
      var items = [], seen = {};
      rows = (rows || []).filter(function (r) { return r && typeof r === 'object' && !Array.isArray(r); });
      for (var i = 0; i < rows.length; i++) {
         var row = rows[i];
         var id = has(row, 'id_') && row.id_ != null && typeof row.id_ !== 'object' ? String(row.id_) : '';
         if (id === '' || !has(fields, id)) continue;
         var field = fields[id];
         var ftype = String(field.type || '');
         if (PAY_TYPES.indexOf(ftype) === -1) continue;
         var ob = has(row, 'id_ob') && row.id_ob != null && typeof row.id_ob !== 'object' ? String(row.id_ob) : '';
         var key = id + '|' + (ftype === 'payCheckbox' ? ob : '');
         if (seen[key]) continue;
         seen[key] = true;
         var label = String(field.name || '');
         var opt, minor;
         if (ftype === 'payRadio' || ftype === 'payCheckbox') {
            opt = ob !== '' && has(fields, ob) ? fields[ob] : null;
            if (!opt || opt.type !== 'option' || String(opt.parent || '') !== id) return err('invalid_selection', id);
            minor = priceMinor(opt);
            if (minor === null) return err('invalid_selection', id);
            items.push(item(id + (ftype === 'payCheckbox' ? '|' + ob : ''), label + ': ' + String(opt.value == null ? '' : opt.value), minor, 'option'));
         } else if (ftype === 'paySelect') {
            opt = optionBy(id, ob, has(row, 'value') ? row.value : null);
            if (!opt) return err('invalid_selection', id);
            minor = priceMinor(opt);
            if (minor === null) return err('invalid_selection', id);
            items.push(item(id, label + ': ' + String(opt.value == null ? '' : opt.value), minor, 'option'));
         } else if (ftype === 'payMultiselect') {
            var txts = typeof row.value === 'string' ? row.value.split('@efb!').filter(function (x) { return x.length; }) : [];
            var ids = typeof row.ids === 'string' ? row.ids.split(',').filter(function (x) { return x.length; }) : [];
            var picked = {}, order = [];
            var pick = function (o) { var k = String(o.id_); if (!has(picked, k)) order.push(k); picked[k] = o; };
            ids.forEach(function (oid) { if (has(fields, oid) && String(fields[oid].parent || '') === id) pick(fields[oid]); });
            txts.forEach(function (tx) { var o = optionBy(id, '', tx); if (o) pick(o); });
            if (!order.length && (txts.length || ids.length)) return err('invalid_selection', id);
            for (var j = 0; j < order.length; j++) {
               var o2 = picked[order[j]];
               minor = priceMinor(o2);
               if (minor === null) return err('invalid_selection', id);
               items.push(item(id + '|' + order[j], label + ': ' + String(o2.value == null ? '' : o2.value), minor, 'option'));
            }
         } else if (ftype === 'prcfld') {
            var v = has(row, 'value') && row.value != null && typeof row.value !== 'object' && String(row.value) !== '' ? row.value : (has(row, 'price') ? row.price : '');
            if (v === '') continue;
            minor = fromDecimal(v, currency);
            if (minor === null || minor < 0) return err('invalid_selection', id);
            var isNum = function (x) { return x != null && x !== '' && !isNaN(Number(x)); };
            var min = isNum(field.milen) ? fromDecimal(String(field.milen), currency) : null;
            var max = isNum(field.mlen) && Number(field.mlen) > 0 ? fromDecimal(String(field.mlen), currency) : null;
            if ((min !== null && minor < min) || (max !== null && minor > max)) return err('pwyw_out_of_range', id);
            if (minor > 0) items.push(item(id, label, minor, 'price'));
         }
      }
      var fid = ctx.field_id ? String(ctx.field_id) : '';
      var pf = fid !== '' && has(fields, fid) ? fields[fid] : null;
      if (pf) {
         var fixed = sanitizeKey('pf_fixed', get(pf, 'pf_fixed'));
         if (fixed !== '') {
            var fm = fromDecimal(fixed, currency);
            if (fm !== null && fm > 0) {
               var fl = sanitizeKey('pf_fixed_label', get(pf, 'pf_fixed_label'));
               items.push(item(fid + '|fixed', fl !== '' ? fl : String(pf.name || ''), fm, 'fixed'));
            }
         }
         if (sanitizeKey('pf_amount', get(pf, 'pf_amount')) === 'donation') {
            var given = null;
            for (var x = 0; x < rows.length; x++) {
               if (has(rows[x], 'id_') && String(rows[x].id_) === fid && has(rows[x], 'value') && rows[x].value != null && typeof rows[x].value !== 'object' && String(rows[x].value) !== '') { given = String(rows[x].value); break; }
            }
            var d = donationMinor(pf, given, currency);
            if (typeof d === 'string') return err(d, fid);
            if (d > 0) {
               var dl = sanitizeKey('pf_t_amount', get(pf, 'pf_t_amount'));
               items.push(item(fid + '|donation', dl !== '' ? dl : String(pf.name || ''), d, 'donation'));
            }
         }
      }
      var subtotal = items.reduce(function (a, b) { return a + b.total_minor; }, 0);
      var q = { ok: true, currency: currency, items: items, subtotal_minor: subtotal, discount_minor: 0, coupon: null, fee_minor: 0, tax_minor: 0, tax_inclusive_minor: 0, total_minor: subtotal, recurring: null };
      if (q.total_minor <= 0) return err('no_amount');
      var mins = supports.min_minor || {}, maxs = supports.max_minor || {};
      if (has(mins, currency) && q.total_minor < Number(mins[currency])) return { ok: false, error: 'below_min', limit_minor: Number(mins[currency]), currency: currency };
      if (has(maxs, currency) && q.total_minor > Number(maxs[currency])) return { ok: false, error: 'above_max', limit_minor: Number(maxs[currency]), currency: currency };
      return q;
   }

   var core = {
      ELEMENTS: ELEMENTS, KEYS: KEYS, FORM_KEYS: FORM_KEYS, TEXT_KEYS: TEXT_KEYS, ICONS: ICONS, HIDEABLE: HIDEABLE,
      sanitizeKey: sanitizeKey, resolve: resolve, texts: texts, css: css, html: html, esc: esc,
      contrast: contrast, autoText: autoText, darken: darken, presets: presets, decimal: decimal,
      i18nDecode: i18nDecode, i18nEncode: i18nEncode, b64urlEncode: b64urlEncode, b64urlDecode: b64urlDecode,
      exponent: exponent, fromDecimal: fromDecimal, toDecimal: toDecimal, format: format, latinDigits: latinDigits,
      quote: quote, donationMinor: donationMinor
   };
   if (typeof module !== 'undefined' && module.exports) module.exports = core;
   if (root && typeof root.efbPayCoreEfb === 'undefined') root.efbPayCoreEfb = core;
})(typeof window !== 'undefined' ? window : null);
