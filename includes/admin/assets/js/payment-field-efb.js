/**
 * PAY00 §10.4: the payment field in the builder: palette tiles of the registered gateways, the
 * defaults of a dropped field, the canvas / preview markup (efbPayCoreEfb.html, the twin of the PHP
 * renderer) and the settings panel. Data: efb_var.pay (gateways without any secret, the default
 * texts, the labels of this panel), from \Emsfb\Payments\Frontend::builder_vars().
 *
 * Everything is written as flat pf_* keys on the field row (and pay_act_* on row 0), sanitized with
 * efbPayCoreEfb.sanitizeKey() here and with Payments\Field::sanitize_key() on save.
 */
(function (root) {
   'use strict';
   if (!root || !root.document) return;
   var core = function () { return root.efbPayCoreEfb; };
   var P = function () { return (root.efb_var && root.efb_var.pay) || { gateways: {}, text: {}, labels: {} }; };
   var L = function (k, fb) { var l = P().labels || {}; return typeof l[k] === 'string' && l[k] !== '' ? l[k] : fb; };
   var rows = function () { return typeof valj_efb !== 'undefined' && Array.isArray(valj_efb) ? valj_efb : null; };
   var esc = function (s) { return core() ? core().esc(s) : String(s); };
   var define = function (name, value) {
      if (typeof root[name] === 'undefined') Object.defineProperty(root, name, { value: value, writable: true, configurable: true });
   };

   var langSel = {}; // the language open in the texts section, per field (not saved)

   function isGateway(type) {
      var g = P().gateways || {};
      return typeof type === 'string' && Object.prototype.hasOwnProperty.call(g, type);
   }
   function info(type) { return (P().gateways || {})[type] || null; }

   /** Palette: one tile per registered gateway (pushed once). */
   function palette(list) {
      if (!Array.isArray(list)) return;
      Object.keys(P().gateways || {}).forEach(function (slug) {
         if (list.some(function (x) { return x.id === slug; })) return;
         list.push({ name: info(slug).label, icon: 'bi-credit-card-2-back', id: slug, pro: false, tag: 'payment all', pgw: true });
      });
   }

   /** Defaults of a dropped gateway field and the form's payment keys (also the B2 formPatch). */
   function onAdd(iVJ) {
      var r = rows();
      if (!r || !r[iVJ]) return;
      var row = r[iVJ];
      var g = info(row.type);
      if (!g) return;
      row.name = g.label;
      row.placeholder = '';
      row.required = 0;
      var f0 = r[0];
      f0.type = 'payment';
      f0.getway = row.type;
      var cur = String(f0.currency || '').toUpperCase();
      if (!cur || (g.currencies && g.currencies.length && g.currencies.indexOf(cur) === -1)) f0.currency = (g.currencies && g.currencies.length ? g.currencies[0] : 'EUR');
      if (!Object.prototype.hasOwnProperty.call(f0, 'paymentmethod')) f0.paymentmethod = 'charge';
      if (typeof form_type_emsFormBuilder !== 'undefined') form_type_emsFormBuilder = 'payment';
   }

   /** Core's default texts with the gateway's own (field_defaults()['texts']), filled with its name. */
   function defaults(slug) {
      var t = Object.assign({}, P().text || {});
      var g = info(slug);
      var own = (g && g.defaults && g.defaults.texts) || {};
      Object.keys(own).forEach(function (k) { if (k !== 'test' && Object.prototype.hasOwnProperty.call(t, k) && own[k]) t[k] = own[k]; });
      var label = g ? g.label : slug;
      Object.keys(t).forEach(function (k) { t[k] = String(t[k]).split('%s').join(label); });
      return t;
   }

   /** The gateway's methods the form's currency can use (a method's extras may name its currencies). */
   function methodsFor(g) {
      var r = rows();
      var cur = String((r && r[0] && r[0].currency) || '').toUpperCase();
      return (g.methods || []).filter(function (m) {
         var x = m && m[2];
         return !(cur && x && Array.isArray(x.currencies) && x.currencies.length && x.currencies.indexOf(cur) === -1);
      });
   }

   function resolved(row) {
      var r = rows();
      var g = info(row.type) || {};
      return core().resolve(row, r ? r[0] : {}, { methods: methodsFor(g), logos: g.logos || [], mobile: !!P().mobile, defaults: (g.defaults && g.defaults.pf) || {} });
   }

   /** Core's save-time checks for this field, worded (twin of Registry::form_warnings() / warning_text() without the gateway's own). */
   function warningsList(row) {
      var r = rows();
      if (!r || !core().warnings) return [];
      var infos = {};
      Object.keys(P().gateways || {}).forEach(function (k) {
         var g = info(k);
         var sup = g.supports || {};
         infos[k] = { currencies: g.currencies || [], field_schema: g.field_schema || [], methods: g.methods || [], mode: g.mode, ready: typeof g.ready === 'boolean' ? g.ready : (g.configured ? !!g.configured[g.mode] : null), min_minor: sup.min_minor || {}, max_minor: sup.max_minor || {} };
      });
      var label = (info(row.type) || {}).label || row.type;
      var fmt = function (m, cur) { return core().format(m, cur, P().locale || 'en', 'latn'); };
      var fname = function (id, fb) { var f = r.find(function (x) { return x && String(x.id_) === String(id); }); return (f && f.name) || fb; };
      var sub = function (t, a) { a.forEach(function (v, i) { t = t.split('%' + (i + 1) + '$s').join(v); }); return a.length === 1 ? t.split('%s').join(a[0]) : t; };
      var text = function (w) {
         switch (w.code) {
            case 'pay_currency': return sub(L('wCurrency', '%1$s only accepts %2$s. This form uses %3$s, so visitors cannot pay. Change the currency in the payment field.'), [label, w.allowed.join(', '), w.currency || '-']);
            case 'pay_option_required': {
               if (w.owner_message) return w.owner_message;
               var ft = w.field_types || [];
               var kind = ft.length === 1 && ft[0] === 'email' ? L('kindEmail', 'an Email field') : (ft.some(function (x) { return x === 'mobile' || x === 'tel'; }) && ft.every(function (x) { return ['mobile', 'tel', 'text', 'number'].indexOf(x) !== -1; }) ? L('kindPhone', 'a phone number field') : L('kindAny', 'a field for it'));
               var names = (w.methods || []).map(function (id) { var m = ((info(w.gateway) || {}).methods || []).find(function (x) { return String(x[0]) === id; }); return m ? m[1] : id; });
               return names.length
                  ? sub(L('wRequiredFor', '%1$s requires "%2$s" to pay with %3$s. Add %4$s to the form and select it in the payment field\'s "%2$s" option.'), [label, w.label, names.join(', '), kind])
                  : sub(L('wRequired', '%1$s requires "%2$s". Add %3$s to the form and select it in the payment field\'s "%2$s" option.'), [label, w.label, kind]);
            }
            case 'pay_option_field_missing': return sub(L('wMissing', '%1$s: the field chosen for "%2$s" is no longer in the form. Choose another one.'), [label, w.label]);
            case 'pay_option_field_type': return sub(L('wType', '%1$s: the field chosen for "%2$s" is not the right kind of field.'), [label, w.label]);
            case 'pay_option_not_required': return sub(L('wNotRequired', '"%1$s" is needed by %2$s for the payment but is not marked Required. Visitors are asked for it when they press Pay; mark it Required so they see it earlier.'), [fname(w.target, w.label), label]);
            case 'pay_not_configured': return sub(w.mode === 'live' ? L('wNoKeysLive', '%s has no keys for Live mode yet, so visitors cannot pay. Add them in Payments → Gateways.') : L('wNoKeysTest', '%s has no keys for Test mode yet, so the form cannot be tried. Add them in Payments → Gateways.'), [label]);
            case 'pay_test_mode': return sub(L('wTestMode', '%s is in Test mode: no real payments are taken. Switch it to Live in Payments → Gateways when you are ready.'), [label]);
            case 'pay_webhook_local': return sub(L('wLocal', 'This site cannot be reached from the internet, so %s cannot send payment confirmations to it. Payments are confirmed when the visitor comes back and by the regular check.'), [label]);
            case 'pay_amount_limit': {
               var k = w.what === 'fixed' ? (w.limit === 'max' ? 'wFixedMax' : 'wFixedMin') : (w.limit === 'max' ? 'wDonMax' : 'wDonMin');
               return sub(L(k, '%1$s: %2$s / %3$s'), [label, fmt(w.limit_minor, w.currency), fmt(w.amount_minor, w.currency)]);
            }
            case 'pay_method_currency': {
               var mn = (w.methods || []).map(function (id) { var m = ((info(w.gateway) || {}).methods || []).find(function (x) { return String(x[0]) === id; }); return m ? m[1] : id; });
               return sub(L('wMethodCur', '%1$s: %2$s cannot take %3$s, so it is not offered on this form. Choose other methods or change the currency.'), [label, mn.join(', '), w.currency]);
            }
            case 'pay_multiple_fields': return L('wMultiple', 'This form has more than one payment field. A form can take one payment: remove the others.');
            case 'pay_form_type': return sub(L('wFormType', 'A payment field only works in a payment form. This form is a "%s" form: create a payment form or remove the payment field.'), [w.form_type || '-']);
         }
         return '';
      };
      return core().warnings(r, infos, { webhook_local: !!P().site_local }).filter(function (w) { return w.field_id === row.id_; }).map(function (w) { return Object.assign({}, w, { m: text(w) }); });
   }

   function warningsHtml(row) {
      var list = warningsList(row);
      if (!list.length) return '';
      var cls = list.some(function (w) { return w.level === 'error'; }) ? 'alert-danger' : (list.some(function (w) { return w.level === 'warning'; }) ? 'alert-warning' : 'alert-info');
      return '<div class="efb alert ' + cls + ' py-1 px-2 my-1 fs-7" role="status" id="efbPfWarnEl"><ul class="efb mb-0 ps-3">' + list.map(function (w) { return '<li data-code="' + esc(w.code) + '" data-level="' + esc(w.level) + '">' + esc(w.m) + '</li>'; }).join('') + '</ul></div>';
   }

   /** The gateway's own options (field_schema()): field pickers list the form's own fields. */
   function optionsHtml(row) {
      var g = info(row.type) || {};
      var schema = core().cleanSchema ? core().cleanSchema(g.field_schema || []) : [];
      if (!schema.length) return '';
      var r = rows() || [];
      var none = L('none', 'None');
      return schema.map(function (d) {
         var id = 'efbPfOpt_' + d.key;
         var v = core().sanitizeGatewayKey(d.key, row[d.key], schema);
         var help = d.help ? '<div class="efb form-text small">' + esc(d.help) + '</div>' : '';
         var label = d.label + (d.required ? ' *' : '');
         if (d.type === 'field') {
            var opts = [['', none]];
            r.forEach(function (f, i) {
               if (i === 0 || !f || !f.id_ || f.type === 'option' || f.type === 'step' || f.id_ === row.id_ || isGateway(f.type)) return;
               if (d.field_types.length && d.field_types.indexOf(f.type) === -1) return;
               opts.push([String(f.id_), (f.name || f.id_) + ' (' + f.type + ')']);
            });
            if (v !== '' && !opts.some(function (o) { return o[0] === v; })) opts.push([v, v + ' ?']);
            return selectRow(id, d.key, label, v, opts) + help;
         }
         if (d.type === 'select') return selectRow(id, d.key, label, v !== '' ? v : d.default, [['', none]].concat(d.options)) + help;
         if (d.type === 'checkbox') return '<div class="efb form-check mt-2"><input class="efb form-check-input" type="checkbox" id="' + id + '" data-efb-pf="' + d.key + '"' + (v === '1' ? ' checked' : '') + '><label class="efb form-check-label" for="' + id + '">' + esc(label) + '</label></div>' + help;
         return inputRow(id, d.key, label, v, 'maxlength="' + d.max + '"' + (d.type === 'code' ? ' dir="ltr"' : '') + (d.default ? ' placeholder="' + esc(d.default) + '"' : '')) + help;
      }).join('');
   }

   /** Which methods the field offers and in which order (pf_methods; empty = all of the gateway's). */
   function methodsHtml(row) {
      var g = info(row.type) || {};
      var avail = methodsFor(g);
      if (avail.length < 2) return '';
      var cur = core().sanitizeKey('pf_methods', row.pf_methods);
      var on = cur ? cur.split(',') : avail.map(function (m) { return String(m[0]); });
      var ids = on.filter(function (id) { return avail.some(function (m) { return String(m[0]) === id; }); });
      avail.forEach(function (m) { if (ids.indexOf(String(m[0])) === -1) ids.push(String(m[0])); });
      var label = function (id) { var m = avail.find(function (x) { return String(x[0]) === id; }); return m ? m[1] : id; };
      return '<p class="efb form-label mt-2 mb-1" id="efbPfMethodsLab">' + esc(L('methodsPick', 'Payment methods (order and choice)')) + '</p><ol class="efb list-unstyled mb-1" id="efbPfMethodsEl" aria-labelledby="efbPfMethodsLab">' + ids.map(function (id, i) {
         return '<li class="efb d-flex align-items-center gap-1 py-1 border-bottom" data-m="' + esc(id) + '"><label class="efb flex-grow-1 small"><input type="checkbox" data-efb-pf-method="' + esc(id) + '"' + (on.indexOf(id) !== -1 ? ' checked' : '') + '> ' + esc(label(id)) + '</label>' +
            '<button type="button" class="efb btn btn-sm btn-light" data-efb-pf-mmove="up" data-i="' + i + '" aria-label="' + esc(L('up', 'Move up') + ': ' + label(id)) + '"' + (i === 0 ? ' disabled' : '') + '>↑</button>' +
            '<button type="button" class="efb btn btn-sm btn-light" data-efb-pf-mmove="down" data-i="' + i + '" aria-label="' + esc(L('down', 'Move down') + ': ' + label(id)) + '"' + (i === ids.length - 1 ? ' disabled' : '') + '>↓</button></li>';
      }).join('') + '</ol><p class="efb small text-muted">' + esc(L('methodsHelp', 'Unticked methods are not offered on this form. The gateway still checks every payment.')) + '</p>';
   }

   /** The methods list as shown (ticked first in their order), for the up/down and tick handlers. */
   function methodOrder(row) {
      var g = info(row.type) || {};
      var avail = methodsFor(g).map(function (m) { return String(m[0]); });
      var cur = core().sanitizeKey('pf_methods', row.pf_methods);
      var on = cur ? cur.split(',').filter(function (id) { return avail.indexOf(id) !== -1; }) : avail.slice();
      var all = on.concat(avail.filter(function (id) { return on.indexOf(id) === -1; }));
      return { on: on, all: all, avail: avail };
   }

   /** The field's markup (without the builder wrappers). preview = the admin preview / canvas. */
   function fieldHtml(row, formId) {
      if (!core() || !isGateway(row.type)) return '';
      var g = info(row.type);
      var r = resolved(row);
      var t = core().texts(row, P().locale || 'en_US', defaults(row.type));
      return core().html(r, t, { id: row.id_, gateway: row.type, form_id: String(formId || 0), label: row.name || g.label, test: g.mode !== 'live', preview: true, placeholder: L('slot', 'The payment page or card fields of the gateway open here.') });
   }

   /** addNewElement(): the -f wrapper (closed by the builder) and the field. */
   function canvasHtml(iVJ, rndm, previewSate) {
      var r = rows();
      var row = r[iVJ];
      var warn = '';
      var g = info(row.type);
      if (previewSate !== true && g && !(g.configured && g.configured[g.mode])) {
         warn = '<div class="efb alert alert-warning py-1 px-2 my-1 fs-7" role="note">' + esc(L('notConfigured', 'This gateway has no keys yet. Visitors read that online payment is not available until it is set up.')) + '</div>';
      }
      ensureCss();
      return '<div class="efb col-sm-12 px-0 mx-0" id="' + rndm + '-f">' + warn + fieldHtml(row, 0);
   }

   function cssAll() {
      var r = rows();
      if (!r || !core()) return '';
      var out = '';
      r.forEach(function (row, i) { if (i > 0 && row && isGateway(row.type)) out += core().css(resolved(row), row.id_); });
      return out;
   }
   function ensureCss() {
      var el = document.getElementById('efbPayCanvasCss');
      if (!el) { el = document.createElement('style'); el.id = 'efbPayCanvasCss'; document.head.appendChild(el); }
      el.textContent = cssAll();
   }
   function repaint(row) {
      ensureCss();
      document.querySelectorAll('.efb-pf[data-pf="' + row.id_ + '"]').forEach(function (n) {
         var w = document.createElement('div');
         w.innerHTML = fieldHtml(row, n.getAttribute('data-formid') || 0);
         if (w.firstChild) n.replaceWith(w.firstChild);
      });
   }

   /* ---- the panel ------------------------------------------------------ */

   var CURRENCIES = ['EUR', 'USD', 'GBP', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'AED', 'SAR', 'KWD', 'BHD', 'OMR', 'QAR', 'JOD', 'EGP', 'THB', 'IDR', 'PHP', 'MYR', 'SGD', 'VND', 'JPY', 'AUD', 'CAD', 'NZD'];
   var EEA = ['EUR', 'SEK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON', 'BGN', 'NOK', 'ISK', 'CHF', 'GBP'];

   function inputRow(id, key, label, value, attrs) {
      return '<label for="' + id + '" class="efb form-label mt-2 mb-1">' + esc(label) + '</label>' +
         '<input id="' + id + '" data-efb-pf="' + key + '" class="efb form-control border-d rounded-4 h-d-efb mb-1" value="' + esc(value == null ? '' : value) + '" ' + (attrs || '') + '>';
   }
   function selectRow(id, key, label, value, opts) {
      return '<label for="' + id + '" class="efb form-label mt-2 mb-1">' + esc(label) + '</label>' +
         '<select id="' + id + '" data-efb-pf="' + key + '" class="efb form-select border-d rounded-4 mb-1">' +
         opts.map(function (o) { return '<option value="' + esc(o[0]) + '"' + (String(value) === String(o[0]) ? ' selected' : '') + '>' + esc(o[1]) + '</option>'; }).join('') + '</select>';
   }
   function colorRow(id, key, label, value, fallback) {
      return '<label for="' + id + '" class="efb form-label mt-2 mb-1">' + esc(label) + '</label>' +
         '<div class="efb d-flex gap-2 align-items-center"><input type="color" id="' + id + '" data-efb-pf="' + key + '" value="' + esc(value || fallback) + '" class="efb form-control-color border-d">' +
         '<button type="button" class="efb btn btn-sm btn-outline-secondary" data-efb-pf-reset="' + key + '">' + esc(L('reset', 'Default')) + '</button></div>';
   }
   function section(id, title, body, open) {
      return '<details class="efb efb-pf-sec border-bottom py-2"' + (open ? ' open' : '') + ' id="' + id + '"><summary class="efb fw-bold">' + esc(title) + '</summary><div class="efb pt-1">' + body + '</div></details>';
   }

   function contrastHtml(row) {
      var r = resolved(row);
      var c = core().contrast(r.btn.fg, r.btn.bg);
      var ok = c >= 4.5;
      var txt = ok ? L('contrastOk', 'Contrast %s:1, readable.') : L('contrastLow', 'The button text may be hard to read: contrast %s:1, at least 4.5:1 is needed.');
      return '<div class="efb small mt-1 ' + (ok ? 'text-success' : 'text-danger') + '" role="status" id="efbPfContrastEl">' + esc(txt.replace('%s', String(c))) + '</div>';
   }

   function orderHtml(row) {
      var C = core();
      var order = C.sanitizeKey('pf_order', row.pf_order);
      var list = order ? order.split(',') : [];
      C.ELEMENTS.forEach(function (e) { if (list.indexOf(e) === -1) list.push(e); });
      list = list.filter(function (e) { return e !== 'coupon'; });
      var hidden = (C.sanitizeKey('pf_hide', row.pf_hide) || '').split(',');
      var names = { summary: L('elSummary', 'Order summary'), amount: L('elAmount', 'Amount (donation)'), methods: L('elMethods', 'Payment methods'), fields: L('elFields', 'Card / QR area'), button: L('elButton', 'Pay button'), secure: L('elSecure', 'Security note'), logos: L('elLogos', 'Logos') };
      return '<ol class="efb list-unstyled mb-1" id="efbPfOrderEl" aria-label="' + esc(L('order', 'Order of the parts')) + '">' + list.map(function (e, i) {
         var can = C.HIDEABLE.indexOf(e) !== -1;
         return '<li class="efb d-flex align-items-center gap-1 py-1 border-bottom" data-el="' + e + '">' +
            '<span class="efb flex-grow-1">' + esc(names[e] || e) + '</span>' +
            (can ? '<label class="efb small"><input type="checkbox" data-efb-pf-show="' + e + '"' + (hidden.indexOf(e) === -1 ? ' checked' : '') + '> ' + esc(L('show', 'Show')) + '</label>' : '') +
            '<button type="button" class="efb btn btn-sm btn-light" data-efb-pf-move="up" data-i="' + i + '" aria-label="' + esc(L('up', 'Move up') + ': ' + (names[e] || e)) + '"' + (i === 0 ? ' disabled' : '') + '>↑</button>' +
            '<button type="button" class="efb btn btn-sm btn-light" data-efb-pf-move="down" data-i="' + i + '" aria-label="' + esc(L('down', 'Move down') + ': ' + (names[e] || e)) + '"' + (i === list.length - 1 ? ' disabled' : '') + '>↓</button></li>';
      }).join('') + '</ol>';
   }

   function langs(row) {
      var d = core().i18nDecode(typeof row.pf_i18n_b64 === 'string' ? row.pf_i18n_b64 : '');
      var site = P().locale || 'en_US';
      var list = [site].concat(Object.keys(d).filter(function (l) { return l !== site; }));
      return { d: d, list: list };
   }

   function textsHtml(row, loc) {
      var l = langs(row);
      loc = loc && l.list.indexOf(loc) !== -1 ? loc : l.list[0];
      var cur = l.d[loc] || {};
      var keys = [['btn', L('tBtn', 'Button text (%amount% = amount)')], ['secure', L('tSecure', 'Security note')], ['summary', L('tSummary', 'Summary title')], ['methods', L('tMethods', 'Methods title')], ['amount', L('tAmount', 'Amount title')], ['other', L('tOther', 'Other amount')], ['pending', L('tPending', 'Waiting message')], ['success', L('tSuccess', 'Success message')], ['fail', L('tFail', 'Failure message')]];
      var def = core().texts(row, loc, defaults(row.type));
      return selectRow('efbPfLangEl', '_lang', L('language', 'Language'), loc, l.list.map(function (x) { return [x, x]; })) +
         '<div class="efb d-flex gap-2"><input id="efbPfLangAddEl" class="efb form-control border-d rounded-4 h-d-efb" placeholder="de_DE" aria-label="' + esc(L('addLanguage', 'Add a language (locale, e.g. de_DE)')) + '"><button type="button" class="efb btn btn-sm btn-outline-secondary" data-efb-pf-lang-add>' + esc(L('add', 'Add')) + '</button></div>' +
         keys.map(function (k) { return inputRow('efbPfT_' + k[0], '_t:' + loc + ':' + k[0], k[1], cur[k[0]] || '', 'placeholder="' + esc(def[k[0]]) + '" maxlength="200"'); }).join('') +
         '<button type="button" class="efb btn btn-sm btn-outline-danger mt-2" data-efb-pf-lang-reset="' + esc(loc) + '">' + esc(L('resetTexts', 'Back to the default translation')) + '</button>';
   }

   function inner(indx) {
      var r = rows();
      var row = r[indx];
      var f0 = r[0];
      var g = info(row.type) || { label: row.type, configured: {}, mode: 'test', currencies: [], methods: [] };
      var C = core();
      var s = function (k) { return C.sanitizeKey(k, row[k]); };
      var conf = g.configured && g.configured[g.mode];
      var state = conf ? (g.mode === 'live' ? L('stLive', 'Live: connected') : L('stTest', 'Test mode only (no real payments)')) : L('stNoKeys', 'No keys yet');
      var curList = (g.currencies && g.currencies.length ? g.currencies : CURRENCIES).map(function (c) { return [c, c]; });
      var cur = String(f0.currency || '').toUpperCase();
      var eea = EEA.indexOf(cur) !== -1;
      var gw = '<p class="efb mb-1"><strong>' + esc(g.label) + '</strong> · <span class="efb ' + (conf ? 'text-success' : 'text-danger') + '">' + esc(state) + '</span></p>' +
         '<a class="efb small" href="' + esc(P().settings || '#') + '" target="_blank" rel="noopener">' + esc(L('settings', 'Gateway settings and keys')) + '</a>' +
         inputRow('efbPfNameEl', '_name', L('label', 'Label'), row.name || '', 'maxlength="120"') +
         selectRow('efbPfCurrencyEl', '_currency', L('currency', 'Currency'), cur, curList) +
         methodsHtml(row);
      var opts = optionsHtml(row);
      var amount = inputRow('efbPfFixedEl', 'pf_fixed', L('fixed', 'Fixed amount (added to the total)'), s('pf_fixed'), 'inputmode="decimal" dir="ltr"') +
         inputRow('efbPfFixedLabelEl', 'pf_fixed_label', L('fixedLabel', 'Name of the fixed amount'), s('pf_fixed_label'), 'maxlength="60"') +
         '<div class="efb form-check mt-2"><input class="efb form-check-input" type="checkbox" id="efbPfDonEl" data-efb-pf="pf_amount"' + (s('pf_amount') === 'donation' ? ' checked' : '') + '><label class="efb form-check-label" for="efbPfDonEl">' + esc(L('donation', 'Donation: the visitor chooses the amount')) + '</label></div>' +
         (s('pf_amount') === 'donation' ?
            inputRow('efbPfPresetsEl', 'pf_presets', L('presets', 'Suggested amounts (comma separated, up to 8)'), s('pf_presets'), 'dir="ltr"') +
            '<div class="efb form-check mt-1"><input class="efb form-check-input" type="checkbox" id="efbPfOtherEl" data-efb-pf="pf_other"' + (s('pf_other') === '1' ? ' checked' : '') + '><label class="efb form-check-label" for="efbPfOtherEl">' + esc(L('other', 'Allow another amount')) + '</label></div>' +
            inputRow('efbPfMinEl', 'pf_min', L('min', 'Minimum'), s('pf_min'), 'inputmode="decimal" dir="ltr"') +
            inputRow('efbPfMaxEl', 'pf_max', L('max', 'Maximum'), s('pf_max'), 'inputmode="decimal" dir="ltr"') +
            inputRow('efbPfDefaultEl', 'pf_default', L('default', 'Preselected amount'), s('pf_default'), 'inputmode="decimal" dir="ltr"') : '') +
         '<p class="efb small text-muted mt-2">' + esc(L('amountHelp', 'The total also counts the priced choices and price fields of the form. The server computes the amount; the browser only shows it.')) + '</p>' +
         (eea ? '<p class="efb small text-danger" role="note">' + esc(L('surchargeEu', 'Charging a mandatory fee on consumer cards is not allowed in the EU. Use the optional "cover the fee" setting for donations instead.')) + '</p>' : '');
      var r0 = resolved(row);
      var button = inputRow('efbPfBtnTextEl', 'pf_btn_text', L('btnText', 'Button text (%amount% = amount)'), s('pf_btn_text'), 'maxlength="60" placeholder="' + esc(defaults(row.type).btn) + '"') +
         colorRow('efbPfBtnBgEl', 'pf_btn_bg', L('btnBg', 'Button colour'), s('pf_btn_bg'), r0.btn.bg) +
         selectRow('efbPfBtnFgModeEl', '_fgmode', L('btnFg', 'Text colour'), s('pf_btn_fg') && s('pf_btn_fg') !== 'auto' ? 'custom' : 'auto', [['auto', L('auto', 'Automatic (readable)')], ['custom', L('custom', 'Custom')]]) +
         (s('pf_btn_fg') && s('pf_btn_fg') !== 'auto' ? colorRow('efbPfBtnFgEl', 'pf_btn_fg', L('btnFg', 'Text colour'), s('pf_btn_fg'), r0.btn.fg) : '') +
         colorRow('efbPfBtnBgHEl', 'pf_btn_bg_h', L('btnHover', 'Colour on hover'), s('pf_btn_bg_h'), r0.btn.bg_h) +
         contrastHtml(row) +
         inputRow('efbPfRadiusEl', 'pf_btn_radius', L('radius', 'Corner radius (px)'), r0.btn.radius, 'type="number" min="0" max="40"') +
         selectRow('efbPfSizeEl', 'pf_btn_size', L('size', 'Size'), r0.btn.size, [['sm', L('small', 'Small')], ['md', L('medium', 'Medium')], ['lg', L('large', 'Large')]]) +
         selectRow('efbPfWidthEl', 'pf_btn_width', L('width', 'Width'), r0.btn.width, [['full', L('full', 'Full width')], ['auto', L('fit', 'Fit the text')]]) +
         selectRow('efbPfIconEl', 'pf_btn_icon', L('icon', 'Icon'), r0.btn.icon, C.ICONS.map(function (i) { return [i, i === 'none' ? L('none', 'None') : i.replace('bi-', '')]; })) +
         selectRow('efbPfIconPosEl', 'pf_btn_icon_pos', L('iconPos', 'Icon position'), r0.btn.icon_pos, [['start', L('start', 'Before the text')], ['end', L('end', 'After the text')]]);
      var layout = orderHtml(row) +
         selectRow('efbPfLayoutEl', 'pf_layout', L('layout', 'Layout'), r0.layout, [['stack', L('stack', 'One column')], ['split', L('split', 'Summary beside (wide screens)')]]) +
         selectRow('efbPfMstyleEl', 'pf_methods_style', L('methodsStyle', 'Methods'), r0.methods_style, [['list', L('list', 'List')], ['grid', L('grid', 'Grid')], ['tabs', L('tabs', 'Tabs')], ['select', L('select', 'Drop-down')]]) +
         selectRow('efbPfSummaryEl', 'pf_summary', L('summary', 'Summary'), r0.summary, [['lines', L('lines', 'Every item')], ['total', L('totalOnly', 'Total only')]]) +
         selectRow('efbPfLabelPosEl', 'pf_label_pos', L('labelPos', 'Label'), r0.label_pos, [['top', L('top', 'Above')], ['start', L('beside', 'Beside')], ['hidden', L('hidden', 'Hidden (screen readers only)')]]) +
         colorRow('efbPfCardBgEl', 'pf_card_bg', L('cardBg', 'Background'), s('pf_card_bg'), '#ffffff') +
         colorRow('efbPfCardBorderEl', 'pf_card_border', L('cardBorder', 'Border'), s('pf_card_border'), '#dee2e6') +
         inputRow('efbPfCardRadiusEl', 'pf_card_radius', L('cardRadius', 'Box corner radius (px)'), r0.card_radius, 'type="number" min="0" max="24"') +
         colorRow('efbPfAccentEl', 'pf_accent', L('accent', 'Accent and focus colour'), s('pf_accent'), r0.accent) +
         selectRow('efbPfLogoEl', 'pf_logo_size', L('logoSize', 'Logo size'), r0.logo_size, [['sm', L('small', 'Small')], ['md', L('medium', 'Medium')]]) +
         selectRow('efbPfDigitsEl', 'pf_digits', L('digits', 'Digits'), r0.digits, [['latn', '0-9'], ['locale', L('localeDigits', 'Digits of the language')]]);
      var acts = [['pay_act_admin_mail', L('actAdmin', 'E-mail to the owner')], ['pay_act_user_mail', L('actUser', 'E-mail to the payer')], ['pay_act_sms', L('actSms', 'SMS')], ['pay_act_tlg', 'Telegram'], ['pay_act_gs', 'Google Sheets'], ['pay_act_wh', L('actWh', 'Webhooks')]];
      var when = [['paid', L('whenPaid', 'After payment')], ['submit', L('whenSubmit', 'When the form is sent (before payment)')], ['both', L('whenBoth', 'Both')]];
      var notif = '<p class="efb small text-muted">' + esc(L('actHelp', 'An unpaid entry stays "pending payment". Choose when each notification goes out.')) + '</p>' +
         acts.map(function (a) { return selectRow('efbPfAct_' + a[0], '0:' + a[0], a[1], C.sanitizeKey(a[0], f0[a[0]]) || 'paid', when); }).join('') +
         '<div class="efb form-check mt-2"><input class="efb form-check-input" type="checkbox" id="efbPfFailEl" data-efb-pf="0:pay_fail_notify"' + (C.sanitizeKey('pay_fail_notify', f0.pay_fail_notify) === 'admin' ? ' checked' : '') + '><label class="efb form-check-label" for="efbPfFailEl">' + esc(L('failNotify', 'E-mail the owner when a payment fails or expires')) + '</label></div>';
      var mobile = P().mobile ?
         selectRow('efbPfMSizeEl', 'mobile_pf_btn_size', L('size', 'Size'), s('mobile_pf_btn_size'), [['', L('asDesktop', 'As on wide screens')], ['sm', L('small', 'Small')], ['md', L('medium', 'Medium')], ['lg', L('large', 'Large')]]) +
         selectRow('efbPfMWidthEl', 'mobile_pf_btn_width', L('width', 'Width'), s('mobile_pf_btn_width'), [['', L('asDesktop', 'As on wide screens')], ['full', L('full', 'Full width')], ['auto', L('fit', 'Fit the text')]]) +
         inputRow('efbPfMHideEl', 'mobile_pf_hide', L('mobileHide', 'Hide on phones (comma separated: summary, secure, logos, methods, amount)'), s('mobile_pf_hide'), 'dir="ltr"')
         : '<p class="efb small text-muted">' + esc(L('mobilePro', 'Phone layout of the field is part of Pro.')) + '</p>';
      return warningsHtml(row) + section('efbPfSecGw', L('secGateway', 'Gateway'), gw, true) +
         (opts ? section('efbPfSecOpts', L('secOptions', 'Payer details and gateway options'), opts, true) : '') +
         section('efbPfSecAmount', L('secAmount', 'Amount'), amount, true) +
         section('efbPfSecBtn', L('secButton', 'Button'), button, false) +
         section('efbPfSecLayout', L('secLayout', 'Layout'), layout, false) +
         section('efbPfSecTexts', L('secTexts', 'Texts and languages'), textsHtml(row, langSel[row.id_]), false) +
         section('efbPfSecNotif', L('secNotify', 'Notifications'), notif, false) +
         section('efbPfSecMobile', L('secMobile', 'Phones'), mobile, false);
   }

   function panel(indx) {
      var r = rows();
      if (!r || !r[indx] || !isGateway(r[indx].type) || !core()) return '';
      return '<div class="efb efb-pf-panel col-12 px-2" id="efbPfPanelEl" data-efb-pf-panel data-indx="' + indx + '">' + inner(indx) + '</div>';
   }

   function rerender(indx, focusId) {
      var p = document.getElementById('efbPfPanelEl');
      if (!p) return;
      var open = {};
      p.querySelectorAll('details').forEach(function (d) { open[d.id] = d.open; });
      p.innerHTML = inner(indx);
      p.querySelectorAll('details').forEach(function (d) { if (Object.prototype.hasOwnProperty.call(open, d.id)) d.open = open[d.id]; });
      if (focusId) { var f = document.getElementById(focusId); if (f) f.focus(); }
   }

   function setKey(row, k, v) {
      var s = core().sanitizeKey(k, v);
      if (s === '') delete row[k]; else row[k] = s;
   }

   function writeI18n(row, loc, key, value) {
      var d = core().i18nDecode(typeof row.pf_i18n_b64 === 'string' ? row.pf_i18n_b64 : '');
      if (!/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/.test(loc)) return;
      d[loc] = d[loc] || {};
      if (String(value).trim() === '') delete d[loc][key]; else d[loc][key] = value;
      if (!Object.keys(d[loc]).length) delete d[loc];
      setKey(row, 'pf_i18n_b64', core().i18nEncode(d));
   }

   function handle(e) {
      var el = e.target && e.target.closest ? e.target.closest('[data-efb-pf],[data-efb-pf-move],[data-efb-pf-show],[data-efb-pf-reset],[data-efb-pf-lang-add],[data-efb-pf-lang-reset],[data-efb-pf-method],[data-efb-pf-mmove]') : null;
      if (!el) return;
      var p = el.closest('[data-efb-pf-panel]');
      if (!p) return;
      var r = rows();
      var indx = parseInt(p.getAttribute('data-indx'), 10);
      var row = r && r[indx];
      if (!row || !isGateway(row.type)) return;
      var click = e.type === 'click';
      var C = core();
      if (el.hasAttribute('data-efb-pf-mmove') || el.hasAttribute('data-efb-pf-method')) {
         var mo = methodOrder(row);
         if (el.hasAttribute('data-efb-pf-method')) {
            if (e.type !== 'change') return;
            var mid = el.getAttribute('data-efb-pf-method');
            var on2 = mo.all.filter(function (x) { return x === mid ? el.checked : mo.on.indexOf(x) !== -1; });
            if (!on2.length) { el.checked = true; return; } // at least one method
            setKey(row, 'pf_methods', on2.length === mo.avail.length && on2.join(',') === mo.avail.join(',') ? '' : on2.join(','));
         } else {
            if (!click) return;
            var list2 = mo.all.slice(), a = parseInt(el.getAttribute('data-i'), 10), b2 = el.getAttribute('data-efb-pf-mmove') === 'up' ? a - 1 : a + 1;
            if (b2 < 0 || b2 >= list2.length) return;
            var tmp2 = list2[a]; list2[a] = list2[b2]; list2[b2] = tmp2;
            setKey(row, 'pf_methods', list2.filter(function (x) { return mo.on.indexOf(x) !== -1; }).join(','));
         }
         rerender(indx); repaint(row); changed();
         return;
      }
      if (el.hasAttribute('data-efb-pf-move')) {
         if (!click) return;
         var order = C.sanitizeKey('pf_order', row.pf_order);
         var list = order ? order.split(',') : [];
         C.ELEMENTS.forEach(function (x) { if (list.indexOf(x) === -1) list.push(x); });
         list = list.filter(function (x) { return x !== 'coupon'; });
         var i = parseInt(el.getAttribute('data-i'), 10), j = el.getAttribute('data-efb-pf-move') === 'up' ? i - 1 : i + 1;
         if (j < 0 || j >= list.length) return;
         var tmp = list[i]; list[i] = list[j]; list[j] = tmp;
         setKey(row, 'pf_order', list.join(','));
         rerender(indx); repaint(row); changed();
         var btn = document.querySelector('#efbPfOrderEl li[data-el="' + tmp + '"] [data-efb-pf-move="' + el.getAttribute('data-efb-pf-move') + '"]');
         if (btn && !btn.disabled) btn.focus();
         return;
      }
      if (el.hasAttribute('data-efb-pf-show')) {
         if (e.type !== 'change') return;
         var hid = (C.sanitizeKey('pf_hide', row.pf_hide) || '').split(',').filter(Boolean);
         var name = el.getAttribute('data-efb-pf-show');
         hid = hid.filter(function (x) { return x !== name; });
         if (!el.checked) hid.push(name);
         setKey(row, 'pf_hide', hid.join(','));
         repaint(row); changed();
         return;
      }
      if (el.hasAttribute('data-efb-pf-reset')) {
         if (!click) return;
         delete row[el.getAttribute('data-efb-pf-reset')];
         rerender(indx); repaint(row); changed();
         return;
      }
      if (el.hasAttribute('data-efb-pf-lang-add')) {
         if (!click) return;
         var inp = document.getElementById('efbPfLangAddEl');
         var loc = inp ? String(inp.value).trim() : '';
         if (!/^[a-z]{2,3}(_[A-Z]{2})?$/.test(loc)) { if (inp) { inp.classList.add('border-danger'); inp.focus(); } return; }
         langSel[row.id_] = loc;
         rerender(indx, 'efbPfT_btn');
         return;
      }
      if (el.hasAttribute('data-efb-pf-lang-reset')) {
         if (!click) return;
         var d = C.i18nDecode(typeof row.pf_i18n_b64 === 'string' ? row.pf_i18n_b64 : '');
         delete d[el.getAttribute('data-efb-pf-lang-reset')];
         setKey(row, 'pf_i18n_b64', C.i18nEncode(d));
         rerender(indx); repaint(row); changed();
         return;
      }
      var key = el.getAttribute('data-efb-pf');
      var isCheck = el.type === 'checkbox';
      if (key.indexOf('pgw_') === 0) {
         // A gateway option: sanitized from the gateway's own field_schema(), like the server does.
         if (isCheck ? e.type !== 'change' : (e.type !== 'change' && !(e.type === 'input' && el.tagName === 'INPUT'))) return;
         var schema = (info(row.type) || {}).field_schema || [];
         var gv = C.sanitizeGatewayKey(key, isCheck ? (el.checked ? '1' : '') : el.value, schema);
         if (gv === '') delete row[key]; else row[key] = gv;
         if (e.type === 'change') { var w = document.getElementById('efbPfWarnEl'); var nw = warningsHtml(row); if (w) w.outerHTML = nw || '<span id="efbPfWarnEl"></span>'; else if (nw) rerender(indx, el.id); }
         changed();
         return;
      }
      if (isCheck ? e.type !== 'change' : (e.type !== 'change' && e.type !== 'input')) return;
      if (el.type === 'color' && e.type === 'input') { /* live */ }
      else if (e.type === 'input' && el.tagName === 'SELECT') return;
      var val = isCheck ? (el.checked ? (key === 'pf_amount' ? 'donation' : (key === '0:pay_fail_notify' ? 'admin' : '1')) : '') : el.value;
      if (el.getAttribute('inputmode') === 'decimal' || el.type === 'number') val = C.latinDigits(String(val));
      if (key === '_name') { row.name = String(val).replace(/[<>"]/g, '').slice(0, 120); repaint(row); var lab = document.getElementById(row.id_ + '_pflab'); if (lab) lab.textContent = row.name; changed(); return; }
      if (key === '_currency') { if (/^[A-Z]{3}$/.test(val)) r[0].currency = val; if (e.type === 'change') rerender(indx, el.id); repaint(row); changed(); return; }
      if (key === '_fgmode') { if (val === 'auto') row.pf_btn_fg = 'auto'; else row.pf_btn_fg = resolved(row).btn.fg; rerender(indx, el.id); repaint(row); changed(); return; }
      if (key === '_lang') { langSel[row.id_] = val; rerender(indx, el.id); return; }
      if (key.indexOf('_t:') === 0) {
         var parts = key.split(':');
         writeI18n(row, parts[1], parts[2], C.sanitizeKey('pf_t_summary', val));
         repaint(row); changed();
         return;
      }
      if (key.indexOf('0:') === 0) { var k0 = key.slice(2); setKey(r[0], k0, val === 'paid' ? '' : val); changed(); return; }
      if ((e.type === 'input' && el.tagName === 'INPUT' && el.type !== 'color')) {
         // Typing: keep the half-typed text in the box, write only what is valid.
         var sv = C.sanitizeKey(key, val);
         if (sv !== '' || String(val).trim() === '') { if (sv === '') delete row[key]; else row[key] = sv; repaint(row); var cEl = document.getElementById('efbPfContrastEl'); if (cEl) cEl.outerHTML = contrastHtml(row); }
         return;
      }
      setKey(row, key, val);
      if (key === 'pf_amount' || key === 'pf_btn_bg' || key === 'pf_btn_fg') rerender(indx, el.id);
      repaint(row); changed();
   }

   function changed() {
      try { if (typeof sessionStorage !== 'undefined' && rows()) sessionStorage.setItem('valj_efb', JSON.stringify(rows())); } catch (e) {} // eslint-disable-line no-empty
   }

   ['input', 'change', 'click'].forEach(function (t) { document.addEventListener(t, handle); });

   define('efbPayIsGatewayEfb', isGateway);
   define('efbPayPaletteEfb', palette);
   define('efbPayOnAddEfb', onAdd);
   define('efbPayCanvasHtmlEfb', canvasHtml);
   define('efbPayFieldHtmlEfb', fieldHtml);
   define('efbPayPanelEfb', panel);
   define('efbPayRepaintEfb', function () { var r = rows(); if (r) r.forEach(function (row, i) { if (i > 0 && row && isGateway(row.type)) repaint(row); }); });
})(typeof window !== 'undefined' ? window : null);
