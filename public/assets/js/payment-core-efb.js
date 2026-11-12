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
   var DEFAULTABLE = ['pf_btn_bg', 'pf_btn_fg', 'pf_btn_bg_h', 'pf_btn_fg_h', 'pf_btn_radius', 'pf_btn_size', 'pf_btn_width', 'pf_btn_icon',
      'pf_btn_icon_pos', 'pf_order', 'pf_hide', 'pf_layout', 'pf_methods_style', 'pf_summary', 'pf_label_pos', 'pf_card_bg', 'pf_card_border',
      'pf_card_radius', 'pf_accent', 'pf_logo_size'];
   var PAYER_KEYS = { pgw_map_name: 'name', pgw_map_first: 'first_name', pgw_map_last: 'last_name', pgw_map_email: 'email', pgw_map_phone: 'phone',
      pgw_map_address: 'address', pgw_map_postcode: 'postcode', pgw_map_city: 'city', pgw_map_state: 'state', pgw_map_country: 'country' };
   var OPTION_TYPES = ['field', 'select', 'text', 'code', 'checkbox'];
   var FORMATS = ['email', 'phone', 'phone_e164', 'country'];
   var PAY_FORM_TYPES = ['payment'];
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
         // 'locale': the language's own digits. Arabic and Persian name them (ICU now gives Latin digits
         // for plain "ar"): Arabic-Indic (arab) for Arabic, extended Arabic-Indic (arabext) for Persian.
         if (digits !== 'locale') opt.numberingSystem = 'latn';
         else if (/^(ar|ary|arq|arz)(-|$)/i.test(loc)) opt.numberingSystem = 'arab';
         else if (/^(fa|ckb|ps|ur)(-|$)/i.test(loc)) opt.numberingSystem = 'arabext';
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
               if (out.length >= 40) break;
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

   /* ---- gateway options (pgw_*): twin of Field::clean_schema() and friends ---- */

   function fieldId(v) {
      if (typeof v === 'number' && Number.isInteger(v)) v = String(v);
      return typeof v === 'string' && /^[A-Za-z0-9_-]{1,40}$/.test(v) ? v : '';
   }
   function isScalar(v) { return typeof v === 'string' || typeof v === 'number' || typeof v === 'boolean'; }
   function phpStr(v) { return typeof v === 'boolean' ? (v ? '1' : '') : String(v); }
   function optionValue(d, v) {
      switch (d.type) {
         case 'field': return fieldId(v);
         case 'select':
            if (typeof v !== 'string') return '';
            for (var i = 0; i < d.options.length; i++) if (d.options[i][0] === v) return v;
            return '';
         case 'text': return text(v, d.max);
         case 'code':
            if (typeof v !== 'string') return '';
            v = ptrim(v);
            return /^[A-Za-z0-9_.-]+$/.test(v) && v.length <= d.max ? v : '';
         case 'checkbox': return v === 1 || v === '1' || v === true ? '1' : '';
      }
      return '';
   }
   function cleanSchema(schema) {
      var out = [], seen = {};
      if (!Array.isArray(schema) && !(schema && typeof schema === 'object')) return out;
      var list = Array.isArray(schema) ? schema : Object.keys(schema).map(function (k) { return schema[k]; });
      for (var i = 0; i < list.length; i++) {
         if (out.length >= 40) break;
         var d = list[i];
         if (!d || typeof d !== 'object' || Array.isArray(d) || !has(d, 'key') || !has(d, 'type') || d.key === null || d.type === null) continue;
         if (typeof d.key !== 'string' || !/^pgw_[a-z0-9_]{1,40}$/.test(d.key)) continue;
         if (typeof d.type !== 'string' || OPTION_TYPES.indexOf(d.type) === -1 || has(seen, d.key)) continue;
         seen[d.key] = true;
         var e = {
            key: d.key, type: d.type,
            label: text(typeof d.label === 'string' ? d.label : d.key, 120),
            help: text(typeof d.help === 'string' ? d.help : '', 300),
            required: !!d.required && d.required !== '0' && !(Array.isArray(d.required) && !d.required.length),
            required_for: [],
            owner_message: text(typeof d.owner_message === 'string' ? d.owner_message : '', 300),
            visitor_message: text(typeof d.visitor_message === 'string' ? d.visitor_message : '', 200),
            format: typeof d.format === 'string' && FORMATS.indexOf(d.format) !== -1 ? d.format : '',
            countries: [],
            invalid_message: text(typeof d.invalid_message === 'string' ? d.invalid_message : '', 200),
            options: [], max: 0, field_types: [], default: ''
         };
         if (d.type === 'select' && d.options && typeof d.options === 'object') {
            var keys = Array.isArray(d.options) ? d.options.map(function (x, j) { return j; }) : Object.keys(d.options);
            for (var j = 0; j < keys.length; j++) {
               if (e.options.length >= 50) break;
               var ov = String(keys[j]), ol = d.options[keys[j]];
               if (Array.isArray(ol) && ol.length === 2 && ol[0] != null && ol[1] != null && isScalar(ol[0])) { ov = phpStr(ol[0]); ol = ol[1]; }
               if (/^[A-Za-z0-9_.-]{0,40}$/.test(ov)) e.options.push([ov, text(isScalar(ol) ? phpStr(ol) : ov, 120)]);
            }
         }
         if (d.type === 'text' || d.type === 'code') {
            var cap = d.type === 'text' ? 200 : 60;
            var m = typeof d.max === 'number' && Number.isInteger(d.max) ? d.max : cap;
            e.max = Math.max(1, Math.min(cap, m));
         }
         if (d.type === 'field' && Array.isArray(d.field_types)) {
            d.field_types.forEach(function (ft) { if (typeof ft === 'string' && /^[A-Za-z]{1,30}$/.test(ft) && e.field_types.indexOf(ft) === -1) e.field_types.push(ft); });
         }
         if (Array.isArray(d.countries)) {
            d.countries.forEach(function (cc) { if (typeof cc === 'string' && /^[A-Za-z]{2}$/.test(cc) && e.countries.indexOf(cc.toUpperCase()) === -1 && e.countries.length < 250) e.countries.push(cc.toUpperCase()); });
         }
         if (d.type !== 'field') { e.format = ''; e.countries = []; }
         if (Array.isArray(d.required_for)) {
            d.required_for.forEach(function (mid) { if (typeof mid === 'string' && /^[a-z0-9_]{1,30}$/.test(mid) && e.required_for.indexOf(mid) === -1 && e.required_for.length < 40) e.required_for.push(mid); });
         }
         if (has(d, 'default') && d.default !== null && d.type !== 'field') e.default = optionValue(e, d.default);
         out.push(e);
      }
      return out;
   }
   function sanitizeGatewayKey(k, v, schema) {
      if (typeof k !== 'string' || !/^pgw_[a-z0-9_]{1,40}$/.test(k)) return '';
      var list = cleanSchema(schema), d = null;
      for (var i = 0; i < list.length; i++) if (list[i].key === k) { d = list[i]; break; }
      if (d === null) {
         if (k.indexOf('pgw_map_') === 0) return fieldId(v);
         return typeof v === 'string' || (typeof v === 'number' && Number.isInteger(v)) ? text(String(v), 200) : '';
      }
      return optionValue(d, v);
   }
   function gatewayOptions(row, schema) {
      var out = { pf_methods: sanitizeKey('pf_methods', get(row, 'pf_methods')) };
      cleanSchema(schema).forEach(function (d) { var v = optionValue(d, get(row, d.key)); out[d.key] = v !== '' ? v : d.default; });
      return out;
   }
   /** Twin of Field::country_iso(): two letters as typed, else the id_op of the form's option with that value. */
   function countryIso(form, fieldId_, value) {
      var v = isScalar(value) ? ptrim(phpStr(value).split('@efb@dq#').join('"')) : '';
      if (v === '') return '';
      if (/^[A-Za-z]{2}$/.test(v)) return v.toUpperCase();
      if (!Array.isArray(form) || fieldId_ === '') return '';
      for (var i = 1; i < form.length; i++) {
         var r = form[i];
         if (!r || typeof r !== 'object' || !has(r, 'parent') || phpStr(r.parent) !== String(fieldId_)) continue;
         var val = isScalar(r.value) ? ptrim(phpStr(r.value)) : '';
         var oid = isScalar(r.id_) ? phpStr(r.id_) : '';
         var iso = typeof r.id_op === 'string' ? r.id_op : '';
         if ((val === v || oid === v) && /^[A-Za-z]{2}$/.test(iso)) return iso.toUpperCase();
      }
      return '';
   }
   /** Twin of Field::payer_problem(): '' = fine, 'format' or 'country'. (The server's e-mail check is stricter.) */
   function payerProblem(d, value, iso) {
      var v = isScalar(value) ? ptrim(phpStr(value)) : '';
      if (v === '' || !d || !d.format) return '';
      if (d.format === 'email') return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v) ? '' : 'format';
      if (d.format === 'phone' || d.format === 'phone_e164') {
         var n = latinDigits(v).replace(/[\s().\-]/g, '');
         if (n.indexOf('00') === 0) n = '+' + n.slice(2);
         if (d.format === 'phone_e164') return /^\+[1-9][0-9]{6,14}$/.test(n) ? '' : 'format';
         return /^\+?[0-9]{7,15}$/.test(n) ? '' : 'format';
      }
      if (d.format === 'country') {
         if (!/^[A-Z]{2}$/.test(String(iso || ''))) return 'format';
         return d.countries && d.countries.length && d.countries.indexOf(iso) === -1 ? 'country' : '';
      }
      return '';
   }
   /** Twin of Field::confirmation_email_field(). */
   function confirmationEmailField(form) {
      if (!Array.isArray(form) || !form[0]) return '';
      var to = fieldId(get(form[0], 'email_to'));
      if (to === '') return '';
      for (var i = 1; i < form.length; i++) {
         var r = form[i];
         if (r && typeof r === 'object' && has(r, 'id_') && phpStr(r.id_) === to && r.type === 'email') return to;
      }
      return '';
   }
   function warnings(form, infos, site) {
      var out = [];
      if (!Array.isArray(form) || !form[0]) return out;
      infos = infos || {};
      site = site || {};
      var types = {}, required = {};
      form.forEach(function (r, i) {
         if (i > 0 && r && typeof r === 'object' && has(r, 'id_') && isScalar(r.id_)) {
            types[phpStr(r.id_)] = typeof r.type === 'string' ? r.type : '';
            required[phpStr(r.id_)] = [true, 1, '1', 'true'].indexOf(r.required) !== -1;
         }
      });
      var cur = get(form[0], 'currency');
      cur = typeof cur === 'string' ? ptrim(cur).toUpperCase() : '';
      var ftype = get(form[0], 'type');
      ftype = typeof ftype === 'string' ? ftype : '';
      var confEmail = confirmationEmailField(form);
      var count = 0;
      form.forEach(function (r, i) {
         if (i === 0 || !r || typeof r !== 'object' || typeof r.type !== 'string' || !has(r, 'id_') || !isScalar(r.id_) || !has(infos, r.type)) return;
         var g = infos[r.type] || {};
         var base = function (o) { return Object.assign({ field_id: phpStr(r.id_), gateway: r.type }, o); };
         count++;
         if (count === 2) out.push(base({ code: 'pay_multiple_fields', level: 'error' }));
         var mode = g.mode === 'live' ? 'live' : 'test';
         if (count === 1) {
            if (PAY_FORM_TYPES.indexOf(ftype) === -1) out.push(base({ code: 'pay_form_type', level: 'error', form_type: ftype }));
            if (has(g, 'ready') && g.ready === false) out.push(base({ code: 'pay_not_configured', level: 'error', mode: mode }));
            if (g.mode === 'test') out.push(base({ code: 'pay_test_mode', level: 'info' }));
            if (mode === 'live' && site.webhook_local) out.push(base({ code: 'pay_webhook_local', level: 'warning' }));
         }
         var allowed = Array.isArray(g.currencies) ? g.currencies.map(String) : [];
         var curOk = !(allowed.length && allowed.indexOf(cur) === -1);
         if (!curOk) out.push(base({ code: 'pay_currency', level: 'error', currency: cur, allowed: allowed }));
         if (curOk && cur !== '') {
            var mins = g.min_minor || {}, maxs = g.max_minor || {};
            var min = has(mins, cur) && mins[cur] !== null && !isNaN(Number(mins[cur])) ? Math.trunc(Number(mins[cur])) : null;
            var max = has(maxs, cur) && maxs[cur] !== null && !isNaN(Number(maxs[cur])) ? Math.trunc(Number(maxs[cur])) : null;
            var donation = sanitizeKey('pf_amount', get(r, 'pf_amount')) === 'donation';
            var checks = donation ? [['pf_min', 'min'], ['pf_max', 'max']] : [['pf_fixed', 'fixed']];
            checks.forEach(function (c) {
               var dv = sanitizeKey(c[0], get(r, c[0]));
               if (dv === '') return;
               var m = fromDecimal(dv, cur);
               if (m === null || m <= 0) return;
               if (max !== null && m > max && c[1] !== 'min') out.push(base({ code: 'pay_amount_limit', level: c[1] === 'fixed' ? 'error' : 'warning', what: c[1], limit: 'max', amount_minor: m, limit_minor: max, currency: cur }));
               else if (min !== null && m < min && c[1] !== 'max') out.push(base({ code: 'pay_amount_limit', level: 'warning', what: c[1], limit: 'min', amount_minor: m, limit_minor: min, currency: cur }));
            });
         }
         var own = sanitizeKey('pf_methods', get(r, 'pf_methods'));
         if (curOk && own !== '' && cur !== '' && Array.isArray(g.methods)) {
            var chosen = own.split(','), bad = [];
            g.methods.forEach(function (m) {
               if (!Array.isArray(m) || m[0] == null || chosen.indexOf(phpStr(m[0])) === -1) return;
               var mc = m[2] && Array.isArray(m[2].currencies) ? m[2].currencies : [];
               if (mc.length && mc.indexOf(cur) === -1) bad.push(phpStr(m[0]));
            });
            if (bad.length) out.push(base({ code: 'pay_method_currency', level: bad.length >= chosen.length ? 'error' : 'warning', methods: bad, currency: cur }));
         }
         var offered = offeredMethods(r, g);
         cleanSchema(g.field_schema || []).forEach(function (d) {
            var v = optionValue(d, has(r, d.key) ? r[d.key] : null);
            var forM = requiredMethods(d, offered);
            if (v === '' && d.key === 'pgw_map_email' && confEmail !== '') v = confEmail;
            if (v === '') {
               if (forM !== null && d.default === '') {
                  var all = !forM.length || (Array.isArray(offered) && offered.every(function (x) { return forM.indexOf(x) !== -1; }));
                  out.push(base({ code: 'pay_option_required', level: all ? 'error' : 'warning', key: d.key, label: d.label, methods: forM, field_types: d.field_types, owner_message: d.owner_message }));
               }
               return;
            }
            if (d.type !== 'field') return;
            if (!has(types, v)) out.push(base({ code: 'pay_option_field_missing', level: 'error', key: d.key, label: d.label }));
            else if (d.field_types.length && d.field_types.indexOf(types[v]) === -1) out.push(base({ code: 'pay_option_field_type', level: 'warning', key: d.key, label: d.label }));
            else if (forM !== null && !required[v]) out.push(base({ code: 'pay_option_not_required', level: 'info', key: d.key, label: d.label, target: v }));
         });
      });
      return out;
   }
   function offeredMethods(row, info) {
      var own = sanitizeKey('pf_methods', get(row, 'pf_methods'));
      if (own !== '') return own.split(',');
      if (!info || !Array.isArray(info.methods)) return null;
      var ids = [];
      info.methods.forEach(function (m) { if (Array.isArray(m) && m.length && m[0] != null && isScalar(m[0])) ids.push(phpStr(m[0])); });
      return ids;
   }
   function requiredMethods(d, offered) {
      if (d.required_for.length) {
         if (offered === null) return d.required_for.slice();
         var both = d.required_for.filter(function (x) { return offered.indexOf(x) !== -1; });
         return both.length ? both : null;
      }
      return d.required ? [] : null;
   }
   function logoUrl(u) {
      return typeof u === 'string' && u.length <= 500 && /^(?:https?:)?\/\/[^\x00-\x20"'<>()]+$|^\/[^\/\x00-\x20"'<>()][^\x00-\x20"'<>()]*$/i.test(u);
   }
   function methodEntry(m) {
      if (!Array.isArray(m) || m.length < 2 || m[0] == null || m[1] == null || !isScalar(m[0]) || !isScalar(m[1])) return null;
      var e = [phpStr(m[0]), phpStr(m[1])];
      var x = m[2] && typeof m[2] === 'object' ? m[2] : {};
      if (typeof x.logo === 'string' && logoUrl(x.logo)) e.push(x.logo);
      return e;
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
      var defs = opts.defaults && typeof opts.defaults === 'object' ? opts.defaults : {};
      var s = function (k) {
         var v = sanitizeKey(k, get(row, k));
         if (v === '' && has(defs, k) && DEFAULTABLE.indexOf(k) !== -1) v = sanitizeKey(k, defs[k]);
         return v;
      };
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
         allowed.split(',').forEach(function (mid) { avail.forEach(function (m) { var me = methodEntry(m); if (me && me[0] === mid) methods.push(me); }); });
      } else {
         avail.forEach(function (m) { var me = methodEntry(m); if (me) methods.push(me); });
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
                     o += '<button type="button" class="efb efb-pf-method" role="radio" aria-checked="' + (i === 0 ? 'true' : 'false') + '" tabindex="' + (i === 0 ? '0' : '-1') + '" data-pf-method="' + esc(m[0]) + '">' +
                        (m.length > 2 ? '<img class="efb efb-pf-method-logo" src="' + esc(m[2]) + '" alt="" aria-hidden="true" loading="lazy" decoding="async">' : '') + esc(m[1]) + '</button>';
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
      DEFAULTABLE: DEFAULTABLE, PAYER_KEYS: PAYER_KEYS, OPTION_TYPES: OPTION_TYPES, FORMATS: FORMATS,
      countryIso: countryIso, payerProblem: payerProblem, confirmationEmailField: confirmationEmailField,
      fieldId: fieldId, cleanSchema: cleanSchema, offeredMethods: offeredMethods, requiredMethods: requiredMethods, sanitizeGatewayKey: sanitizeGatewayKey, gatewayOptions: gatewayOptions, warnings: warnings, methodEntry: methodEntry, logoUrl: logoUrl,
      sanitizeKey: sanitizeKey, resolve: resolve, texts: texts, css: css, html: html, esc: esc,
      contrast: contrast, autoText: autoText, darken: darken, presets: presets, decimal: decimal,
      i18nDecode: i18nDecode, i18nEncode: i18nEncode, b64urlEncode: b64urlEncode, b64urlDecode: b64urlDecode,
      exponent: exponent, fromDecimal: fromDecimal, toDecimal: toDecimal, format: format, latinDigits: latinDigits,
      quote: quote, donationMinor: donationMinor
   };
   if (typeof module !== 'undefined' && module.exports) module.exports = core;
   if (root && typeof root.efbPayCoreEfb === 'undefined') root.efbPayCoreEfb = core;
})(typeof window !== 'undefined' ? window : null);
