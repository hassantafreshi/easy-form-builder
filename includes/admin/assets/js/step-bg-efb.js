/* ==========================================================================
   F19 - per-step background (solid colour or linear/radial gradient)
   --------------------------------------------------------------------------
   Two parts:
   1. The core (sanitize, resolve, CSS, contrast): the JavaScript twin of
      includes/step-bg-functions-efb.php - change one, change the other. The
      vectors in tests/fixtures/step-bg-vectors.json hold both to the same
      output byte for byte. It has no DOM dependency and loads in Node.
   2. The builder: the "Step background" section of the step settings panel,
      the paint of the step header on the canvas and the stylesheet of the
      in-admin preview. wp-admin only; the front end gets its CSS from PHP and
      needs no JavaScript at all (a step's fieldset carries its background, so
      showing a step shows its background).

   Data keys: docs/feature-gaps/F19-step-backgrounds.fa.md §5. Values are
   structured scalars, never CSS strings.
   ========================================================================== */
(function (root) {
   'use strict';

   var KEYS = ['step_bg_type', 'step_bg_color', 'step_bg_stops', 'step_bg_angle', 'step_bg_pos', 'step_bg_opacity', 'step_bg_text', 'mobile_step_bg'];
   var TEXT_SEL = '.col-form-label,.col-form-label>span:not(.text-danger),label.hStyleOpEfb,small.form-text[id$="-des"]';

   /* ---- core ---------------------------------------------------------- */

   function hex(v) {
      if (typeof v !== 'string') return '';
      var m = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.exec(v.trim());
      if (!m) return '';
      var h = m[1].toLowerCase();
      if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
      return '#' + h;
   }

   function int(v, min, max) {
      var n;
      if (typeof v === 'number') {
         if (!isFinite(v)) return '';
         n = v;
      } else if (typeof v === 'string' && /^-?\d{1,6}(\.\d{1,6})?$/.test(v.trim())) {
         n = parseFloat(v.trim());
      } else {
         return '';
      }
      n = Math.floor(n + 0.5);
      if (n < min) n = min;
      if (n > max) n = max;
      return String(n);
   }

   function parseStops(v) {
      if (typeof v !== 'string' || v.length > 200) return [];
      var out = [];
      var parts = v.split(',');
      for (var i = 0; i < parts.length && out.length < 5; i++) {
         var m = /^(#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6})\s+(\d{1,3})$/.exec(parts[i].trim());
         if (!m) continue;
         var pos = parseInt(m[2], 10);
         if (pos > 100) pos = 100;
         out.push([hex(m[1]), pos]);
      }
      if (out.length < 2) return [];
      return out.map(function (s, i) { return [s, i]; })
         .sort(function (a, b) { return a[0][1] === b[0][1] ? a[1] - b[1] : a[0][1] - b[0][1]; })
         .map(function (x) { return x[0]; });
   }

   function stopsString(stops) {
      return stops.map(function (s) { return s[0] + ' ' + s[1]; }).join(',');
   }

   function sanitizeKey(k, v) {
      switch (k) {
         case 'step_bg_type': return typeof v === 'string' && ['none', 'solid', 'linear', 'radial'].indexOf(v) !== -1 ? v : '';
         case 'step_bg_color': return hex(v);
         case 'step_bg_stops': return stopsString(parseStops(v));
         case 'step_bg_angle': return int(v, 0, 360);
         case 'step_bg_opacity': return int(v, 0, 100);
         case 'step_bg_pos': return typeof v === 'string' && ['center', 'top', 'bottom', 'left', 'right'].indexOf(v) !== -1 ? v : '';
         case 'step_bg_text': return v === 'auto' ? 'auto' : hex(v);
         case 'mobile_step_bg': return typeof v === 'string' && ['none', 'solid'].indexOf(v) !== -1 ? v : '';
      }
      return '';
   }

   function get(row, k) {
      return row && typeof row === 'object' && Object.prototype.hasOwnProperty.call(row, k) ? row[k] : null;
   }

   function resolve(row, form0, opts) {
      opts = opts || {};
      var own = sanitizeKey('step_bg_type', get(row, 'step_bg_type'));
      var src = null;
      if (own !== '') {
         src = row;
      } else {
         var def = sanitizeKey('step_bg_type', get(form0, 'step_bg_type'));
         if (def !== '' && def !== 'none') src = form0;
      }
      if (src === null) return null;
      var type = sanitizeKey('step_bg_type', get(src, 'step_bg_type'));
      if (type === 'none') return null;
      var op = sanitizeKey('step_bg_opacity', get(src, 'step_bg_opacity'));
      op = op === '' ? 100 : parseInt(op, 10);
      var stops, kind;
      if (type === 'solid') {
         var c = hex(get(src, 'step_bg_color'));
         if (c === '') return null;
         stops = [[c, 0]];
         kind = 'solid';
      } else {
         stops = parseStops(get(src, 'step_bg_stops'));
         if (!stops.length) return null;
         kind = opts.gradient ? type : 'solid';
         if (kind === 'solid') stops = [[stops[0][0], 0]];
      }
      var angle = sanitizeKey('step_bg_angle', get(src, 'step_bg_angle'));
      var pos = sanitizeKey('step_bg_pos', get(src, 'step_bg_pos'));
      var mob = opts.mobile ? sanitizeKey('mobile_step_bg', get(src, 'mobile_step_bg')) : '';
      if (mob === 'solid' && kind === 'solid') mob = '';
      return {
         kind: kind,
         stops: stops,
         angle: angle === '' ? 135 : parseInt(angle, 10),
         pos: pos === '' ? 'center' : pos,
         opacity: op,
         text: sanitizeKey('step_bg_text', get(src, 'step_bg_text')),
         mobile: mob
      };
   }

   function alpha(op) {
      var s = (op / 100).toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
      return s === '' ? '0' : s;
   }

   function colorCss(h, op) {
      if (op >= 100) return h;
      return 'rgba(' + parseInt(h.substr(1, 2), 16) + ',' + parseInt(h.substr(3, 2), 16) + ',' + parseInt(h.substr(5, 2), 16) + ',' + alpha(op) + ')';
   }

   function gradientCss(r) {
      if (r.kind === 'solid') return '';
      var p = r.stops.map(function (s) { return colorCss(s[0], r.opacity) + ' ' + s[1] + '%'; }).join(',');
      if (r.kind === 'radial') return 'radial-gradient(ellipse at ' + r.pos + ',' + p + ')';
      return 'linear-gradient(' + r.angle + 'deg,' + p + ')';
   }

   function rgb(h, op) {
      if (op === undefined) op = 100;
      var a = op / 100;
      return [1, 3, 5].map(function (i) { return a * parseInt(h.substr(i, 2), 16) + (1 - a) * 255; });
   }

   function luminance(c3) {
      var l = c3.map(function (c) {
         c = c / 255;
         return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
      });
      return 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2];
   }

   function samples(r) {
      var rgbs = r.stops.map(function (s) { return rgb(s[0], r.opacity); });
      if (rgbs.length < 2) return rgbs;
      var out = [];
      for (var i = 0; i < rgbs.length - 1; i++) {
         for (var j = 0; j < 9; j++) {
            var t = j / 9;
            out.push([
               rgbs[i][0] + (rgbs[i + 1][0] - rgbs[i][0]) * t,
               rgbs[i][1] + (rgbs[i + 1][1] - rgbs[i][1]) * t,
               rgbs[i][2] + (rgbs[i + 1][2] - rgbs[i][2]) * t
            ]);
         }
      }
      out.push(rgbs[rgbs.length - 1]);
      return out;
   }

   /* Round half away from zero to 2 decimals, as PHP round() does. */
   function round2(x) {
      return Math.round(x * 100 + (x >= 0 ? 1e-9 : -1e-9)) / 100;
   }

   function contrast(fg, smp) {
      var lf = luminance(rgb(fg));
      var min = 21;
      smp.forEach(function (s) {
         var lb = luminance(s);
         var ratio = (Math.max(lf, lb) + 0.05) / (Math.min(lf, lb) + 0.05);
         if (ratio < min) min = ratio;
      });
      return round2(min);
   }

   function autoText(smp) {
      return contrast('#ffffff', smp) > contrast('#111827', smp) ? '#ffffff' : '#111827';
   }

   function textColor(r, smp) {
      if (r.text === '') return '';
      if (r.text !== 'auto') return r.text;
      return autoText(smp || samples(r));
   }

   function css(rows, prefix, opts) {
      if (!Array.isArray(rows) || !rows.length) return '';
      var form0 = rows[0];
      var painted = [];
      var count = 0;
      for (var i = 1; i < rows.length; i++) {
         var row = rows[i];
         if (get(row, 'type') !== 'step') continue;
         count++;
         var n = parseInt(get(row, 'step'), 10);
         if (!(n >= 1)) n = count;
         var r = resolve(row, form0, opts);
         if (r) painted.push([prefix + '[data-step="step-' + n + '-efb"]', r]);
      }
      if (count > 0) {
         var rf = resolve({}, form0, opts);
         if (rf) painted.push([prefix + '#efb-final-step', rf]);
      }
      if (!painted.length) return '';

      var fgRules = function (s, fg) { return s + '{color:' + fg + '}' + s + ' :is(' + TEXT_SEL + '){color:' + fg + '!important}'; };
      var bef = painted.map(function (p) { return p[0] + '::before'; }).join(',');
      var out = painted.map(function (p) { return p[0]; }).join(',') + '{position:relative;isolation:isolate;border-radius:.75rem;padding:1rem .75rem!important}';
      out += bef + '{content:"";position:absolute;inset:0;z-index:-1;border-radius:inherit;pointer-events:none;animation:efb-sbg-in .4s ease-out both}';
      out += '@keyframes efb-sbg-in{from{opacity:0}to{opacity:1}}';
      var mobile = '';
      var printFg = [];
      painted.forEach(function (p) {
         var s = p[0], r = p[1];
         var base = colorCss(r.stops[0][0], r.opacity);
         var grad = gradientCss(r);
         out += s + '::before{background-color:' + base + (grad !== '' ? ';background-image:' + grad : '') + '}';
         var smp = samples(r);
         var fg = textColor(r, smp);
         if (fg !== '') {
            out += r.mobile === 'none' ? '@media (min-width:768px){' + fgRules(s, fg) + '}' : fgRules(s, fg);
            printFg.push(s + ',' + s + ' :is(' + TEXT_SEL + ')');
         }
         if (r.mobile === 'none') {
            mobile += s + '::before{display:none}' + s + '{padding:0 2%!important;border-radius:0}';
         } else if (r.mobile === 'solid') {
            mobile += s + '::before{background-image:none;background-color:' + base + '}';
            if (r.text === 'auto') {
               var fg2 = autoText([rgb(r.stops[0][0], r.opacity)]);
               if (fg2 !== fg) mobile += fgRules(s, fg2);
            }
         }
      });
      if (mobile !== '') out += '@media (max-width:767.98px){' + mobile + '}';
      out += '@media (prefers-reduced-motion:reduce){' + bef + '{animation:none}}';
      out += '@media (forced-colors:active){' + bef + '{display:none}}';
      out += '@media print{' + bef + '{display:none}' + (printFg.length ? printFg.join(',') + '{color:#000!important}' : '') + '}';
      return out;
   }

   var core = {
      KEYS: KEYS, hex: hex, int: int, parseStops: parseStops, stopsString: stopsString, sanitizeKey: sanitizeKey,
      resolve: resolve, colorCss: colorCss, gradientCss: gradientCss, rgb: rgb, luminance: luminance,
      samples: samples, contrast: contrast, autoText: autoText, textColor: textColor, css: css
   };

   if (typeof module !== 'undefined' && module.exports) module.exports = core;
   if (!root || !root.document) return;

   /* ---- builder ------------------------------------------------------- */

   var define = function (name, value) {
      if (typeof root[name] === 'undefined') {
         Object.defineProperty(root, name, { value: value, writable: true, configurable: true });
      }
   };

   var PRESETS = [
      { type: 'linear', angle: '135', stops: '#f8fafc 0,#e2e8f0 100' },
      { type: 'linear', angle: '135', stops: '#e0f2fe 0,#c7d2fe 100' },
      { type: 'linear', angle: '135', stops: '#ecfdf5 0,#a7f3d0 100' },
      { type: 'linear', angle: '135', stops: '#fff7ed 0,#fecdd3 100' },
      { type: 'linear', angle: '135', stops: '#0369a1 0,#4338ca 100', text: 'auto' },
      { type: 'linear', angle: '135', stops: '#c2410c 0,#be185d 100', text: 'auto' },
      { type: 'linear', angle: '160', stops: '#0f172a 0,#334155 100', text: 'auto' },
      { type: 'radial', pos: 'center', stops: '#fef9c3 0,#fde68a 60,#fdba74 100' }
   ];
   var NAMED = {
      primary: '#0d6efd', success: '#198754', secondary: '#6c757d', danger: '#ff455f', warning: '#e9c31a', info: '#31d2f2',
      light: '#fbfbfb', darkb: '#202a8d', labelEfb: '#898aa9', d: '#83859f', pinkEfb: '#ff4b93', white: '#ffffff', dark: '#212529', muted: '#777777'
   };

   var T = function (k, fb) {
      var t = root.efb_var && root.efb_var.text ? root.efb_var.text[k] : undefined;
      return typeof t === 'string' && t !== '' ? t : fb;
   };
   var esc = function (s) {
      return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
   };
   var rowsEfb = function () { return typeof valj_efb !== 'undefined' && Array.isArray(valj_efb) ? valj_efb : null; };

   /* What the site's plan lets the builder show: gradients (Free Plus) and the mobile override (Pro). */
   var planOpts = function () {
      var proNow = typeof pro_efb !== 'undefined' && pro_efb === true;
      var gradient = typeof efb_capability_available_efb === 'function' ? !!efb_capability_available_efb('setting:step_bg_gradient', proNow) : proNow;
      var mobile = typeof efbHasMobileProAccessEfb === 'function' ? !!efbHasMobileProAccessEfb() : false;
      return { gradient: gradient, mobile: mobile };
   };
   var mobileView = function () { return typeof currentViewEfb !== 'undefined' && currentViewEfb === 'mobile'; };

   var labelHex = function (cls) {
      var s = String(cls || '').trim();
      var at = s.indexOf('colorDEfb-');
      if (at !== -1) { var h = hex('#' + s.slice(at + 10).replace(/[^0-9a-fA-F]/g, '').slice(0, 6)); if (h) return h; }
      var name = s.replace(/^(btn-outline-|btn-|text-|bg-|border-)/, '');
      return NAMED[name] || '#212529';
   };

   /* The inline paint of a canvas step header: '' for a step without a background, so the
      canvas markup of an old form is unchanged. */
   function canvasAttr(iVJ) {
      var rows = rowsEfb();
      if (!rows || !rows[iVJ] || rows[iVJ].type !== 'step') return '';
      var r = resolve(rows[iVJ], rows[0], planOpts());
      if (!r) return '';
      if (mobileView() && r.mobile === 'none') return '';
      if (mobileView() && r.mobile === 'solid') r = Object.assign({}, r, { kind: 'solid', stops: [r.stops[0]] });
      var grad = gradientCss(r);
      var fg = textColor(r);
      var style = 'background-color:' + colorCss(r.stops[0][0], r.opacity) + ';' + (grad ? 'background-image:' + grad + ';' : '') + 'border-radius:.5rem;' + (fg ? '--efb-sbg-fg:' + fg + ';' : '');
      return ' data-efb-sbg="1"' + (fg ? ' data-efb-sbg-fg="1"' : '') + ' style="' + style + '"';
   }

   /* Repaint every step header on the canvas (after an edit, "use for all steps", or a view switch). */
   function paintCanvas() {
      var rows = rowsEfb();
      if (!rows) return;
      rows.forEach(function (row, i) {
         if (i === 0 || row.type !== 'step') return;
         var sec = document.querySelector('section[data-tag="steps"][data-step="' + String(row.id_).replace(/"/g, '') + '"]');
         if (!sec) return;
         sec.removeAttribute('data-efb-sbg');
         sec.removeAttribute('data-efb-sbg-fg');
         sec.removeAttribute('style');
         var a = canvasAttr(i);
         var m = /data-efb-sbg="1"( data-efb-sbg-fg="1")? style="([^"]*)"/.exec(a);
         if (!m) return;
         sec.setAttribute('data-efb-sbg', '1');
         if (m[1]) sec.setAttribute('data-efb-sbg-fg', '1');
         sec.setAttribute('style', m[2]);
      });
   }

   function previewCss() {
      var rows = rowsEfb();
      return rows ? css(rows, '.efb-sbg-preview fieldset.steps-efb', planOpts()) : '';
   }

   function ensureBuilderCss() {
      if (document.getElementById('efb-sbg-builder-css')) return;
      var st = document.createElement('style');
      st.id = 'efb-sbg-builder-css';
      st.textContent = 'section[data-efb-sbg-fg] [id$="_lab"],section[data-efb-sbg-fg] [id$="-des"]{color:var(--efb-sbg-fg)!important}'
         + '.efb-sbg-panel .efb-sbg-bar{height:28px;border-radius:.5rem;border:1px solid #d0d4e4}'
         + '.efb-sbg-panel .efb-sbg-swatch{width:34px;height:34px;border-radius:.5rem;border:1px solid #d0d4e4;padding:0}'
         + '.efb-sbg-panel .efb-sbg-swatch[aria-pressed="true"]{outline:2px solid #4636f1;outline-offset:2px}'
         + '.efb-sbg-panel .efb-sbg-modes .btn[aria-checked="true"]{background:#4636f1;color:#fff}'
         + '.efb-sbg-panel input[type=color]{width:42px;height:32px;padding:2px}'
         + '.efb-sbg-panel .efb-sbg-stop input[type=number]{width:70px}';
      document.head.appendChild(st);
   }

   function modeOf(row) {
      var t = sanitizeKey('step_bg_type', get(row, 'step_bg_type'));
      if (t === '') return 'inherit';
      if (t === 'linear' || t === 'radial') return 'gradient';
      return t;
   }

   function summary(r) {
      if (!r) return '';
      var g = gradientCss(r);
      return 'background-color:' + colorCss(r.stops[0][0], r.opacity) + ';' + (g ? 'background-image:' + g + ';' : '');
   }

   function contrastHtml(row) {
      var rows = rowsEfb();
      var r = resolve(row, rows[0], planOpts());
      if (!r) return '';
      var smp = samples(r);
      var fg = textColor(r, smp) || labelHex(row.label_text_color);
      var ratio = contrast(fg, smp);
      var shown = (Math.floor(ratio * 10) / 10).toFixed(1);
      if (ratio >= 4.5) return '<i class="efb bi-check-circle text-success"></i> ' + esc(T('sbgContrastOk', 'Contrast %s:1 — easy to read').replace('%s', shown));
      return '<i class="efb bi-exclamation-triangle text-danger"></i> ' + esc(T('sbgContrastLow', 'Text may be hard to read: contrast %s:1, at least 4.5:1 is needed. Choose “Automatic” text color or change the background.').replace('%s', shown));
   }

   function inner(indx) {
      var rows = rowsEfb();
      var row = rows[indx];
      var mode = modeOf(row);
      var opts = planOpts();
      var lockG = !opts.gradient;
      var gem = '<i class="efb bi-gem text-warning mx-1" aria-hidden="true"></i>';
      var id = 'efbSbg';
      var modeBtn = function (v, label, locked) {
         return '<button type="button" role="radio" class="efb btn btn-sm btn-outline-secondary" data-efb-sbg="mode" data-v="' + v + '" aria-checked="' + (mode === v) + '" id="' + id + 'Mode_' + v + '">' + esc(label) + (locked ? gem : '') + '</button>';
      };
      var h = '<label class="efb form-label mt-3 mb-1 efb d-block" id="' + id + 'TitleEl"><i class="efb bi-palette fs-7 ' + (typeof iconMarginGlobal !== 'undefined' ? iconMarginGlobal : 'me-2') + '"></i>' + esc(T('sbgTitle', 'Step background')) + '</label>';
      h += '<div class="efb btn-group efb-sbg-modes flex-wrap mb-2" role="radiogroup" aria-labelledby="' + id + 'TitleEl">'
         + modeBtn('inherit', T('sbgInherit', 'Form default'))
         + modeBtn('none', T('sbgNone', 'None'))
         + modeBtn('solid', T('sbgSolid', 'Solid color'))
         + modeBtn('gradient', T('sbgGradient', 'Gradient'), lockG)
         + '</div>';

      var r = resolve(row, rows[0], opts);
      if (mode === 'inherit') {
         var d = resolve({}, rows[0], opts);
         h += d ? '<div class="efb d-flex align-items-center gap-2 mb-2"><span class="efb efb-sbg-bar flex-grow-1" style="' + summary(d) + '"></span></div><small class="efb text-muted d-block mb-2">' + esc(T('sbgInheritHint', 'This step uses the form default.')) + '</small>'
            : '<small class="efb text-muted d-block mb-2">' + esc(T('sbgInheritNone', 'No form default is set. Use “Use for all steps” to set one.')) + '</small>';
      }
      if (mode === 'solid' || mode === 'gradient') {
         h += '<div class="efb efb-sbg-bar mb-2" id="' + id + 'BarEl" style="' + summary(r) + '"></div>';
      }
      if (mode === 'solid') {
         h += '<label for="' + id + 'ColorEl" class="efb form-label mb-1 me-2">' + esc(T('sbgSolid', 'Solid color')) + '</label>'
            + '<input type="color" id="' + id + 'ColorEl" data-efb-sbg="color" value="' + (hex(row.step_bg_color) || '#e0f2fe') + '"><br>';
      }
      if (mode === 'gradient') {
         var type = sanitizeKey('step_bg_type', row.step_bg_type);
         h += '<label for="' + id + 'TypeEl" class="efb form-label mb-1">' + esc(T('sbgGradient', 'Gradient')) + '</label>'
            + '<select id="' + id + 'TypeEl" data-efb-sbg="gtype" class="efb form-select efb border-d rounded-4 mb-2">'
            + '<option value="linear"' + (type === 'linear' ? ' selected' : '') + '>' + esc(T('sbgLinear', 'Linear')) + '</option>'
            + '<option value="radial"' + (type === 'radial' ? ' selected' : '') + '>' + esc(T('sbgRadial', 'Radial')) + '</option></select>';
         if (type === 'radial') {
            var pos = sanitizeKey('step_bg_pos', row.step_bg_pos) || 'center';
            h += '<label for="' + id + 'PosEl" class="efb form-label mb-1">' + esc(T('sbgCenter', 'Center point')) + '</label><select id="' + id + 'PosEl" data-efb-sbg="pos" class="efb form-select efb border-d rounded-4 mb-2">'
               + [['center', 'sbgPosCenter', 'Center'], ['top', 'sbgPosTop', 'Top'], ['bottom', 'sbgPosBottom', 'Bottom'], ['left', 'sbgPosLeft', 'Left'], ['right', 'sbgPosRight', 'Right']]
                  .map(function (o) { return '<option value="' + o[0] + '"' + (pos === o[0] ? ' selected' : '') + '>' + esc(T(o[1], o[2])) + '</option>'; }).join('')
               + '</select>';
         } else {
            var ang = sanitizeKey('step_bg_angle', row.step_bg_angle) || '135';
            h += '<label for="' + id + 'AngleEl" class="efb form-label mb-1">' + esc(T('sbgAngle', 'Angle')) + '</label>'
               + '<div class="efb d-flex align-items-center gap-2 mb-2"><input type="range" min="0" max="360" step="1" id="' + id + 'AngleEl" data-efb-sbg="angle" value="' + ang + '" class="efb form-range flex-grow-1">'
               + '<input type="number" min="0" max="360" id="' + id + 'AngleNumEl" data-efb-sbg="angle" value="' + ang + '" class="efb form-control efb border-d rounded-4" style="width:80px" aria-label="' + esc(T('sbgAngle', 'Angle')) + '"></div>';
         }
         var stops = parseStops(row.step_bg_stops);
         h += '<fieldset class="efb mb-2"><legend class="efb form-label fs-7 mb-1">' + esc(T('sbgStops', 'Colors')) + '</legend>';
         stops.forEach(function (s, i) {
            h += '<div class="efb d-flex align-items-center gap-2 mb-1 efb-sbg-stop">'
               + '<input type="color" id="' + id + 'StopC' + i + 'El" data-efb-sbg="stopc" data-i="' + i + '" value="' + s[0] + '" aria-label="' + esc(T('sbgStops', 'Colors')) + ' ' + (i + 1) + '">'
               + '<input type="number" min="0" max="100" id="' + id + 'StopP' + i + 'El" data-efb-sbg="stopp" data-i="' + i + '" value="' + s[1] + '" class="efb form-control efb border-d rounded-4" aria-label="' + esc(T('sbgStopPos', 'Position (%)')) + ' ' + (i + 1) + '">'
               + (stops.length > 2 ? '<button type="button" class="efb btn btn-sm btn-outline-danger" data-efb-sbg="stopdel" data-i="' + i + '" id="' + id + 'StopD' + i + 'El" aria-label="' + esc(T('sbgRemoveStop', 'Remove this color')) + '"><i class="efb bi-x-lg"></i></button>' : '')
               + '</div>';
         });
         h += (stops.length < 5 ? '<button type="button" class="efb btn btn-sm btn-outline-secondary" data-efb-sbg="stopadd" id="' + id + 'StopAddEl"><i class="efb bi-plus-lg"></i> ' + esc(T('sbgAddStop', 'Add color')) + '</button>' : '') + '</fieldset>';
      }
      if (mode === 'solid' || mode === 'gradient') {
         var op = sanitizeKey('step_bg_opacity', row.step_bg_opacity) || '100';
         h += '<label for="' + id + 'OpacityEl" class="efb form-label mb-1">' + esc(T('sbgOpacity', 'Opacity')) + ' <span id="' + id + 'OpacityValEl">' + op + '%</span></label>'
            + '<input type="range" min="0" max="100" step="5" id="' + id + 'OpacityEl" data-efb-sbg="opacity" value="' + op + '" class="efb form-range mb-2">';
         var text = sanitizeKey('step_bg_text', row.step_bg_text);
         var tmode = text === '' ? 'keep' : (text === 'auto' ? 'auto' : 'custom');
         h += '<label for="' + id + 'TextEl" class="efb form-label mb-1">' + esc(T('sbgText', 'Text color')) + '</label>'
            + '<div class="efb d-flex align-items-center gap-2 mb-1"><select id="' + id + 'TextEl" data-efb-sbg="text" class="efb form-select efb border-d rounded-4 flex-grow-1">'
            + '<option value="keep"' + (tmode === 'keep' ? ' selected' : '') + '>' + esc(T('sbgTextKeep', 'Keep field colors')) + '</option>'
            + '<option value="auto"' + (tmode === 'auto' ? ' selected' : '') + '>' + esc(T('sbgTextAuto', 'Automatic (readable)')) + '</option>'
            + '<option value="custom"' + (tmode === 'custom' ? ' selected' : '') + '>' + esc(T('sbgTextCustom', 'Custom')) + '</option></select>'
            + (tmode === 'custom' ? '<input type="color" id="' + id + 'TextColorEl" data-efb-sbg="textc" value="' + text + '" aria-label="' + esc(T('sbgText', 'Text color')) + '">' : '') + '</div>';
         h += '<div class="efb fs-7 mb-2" id="' + id + 'ContrastEl" role="status" aria-live="polite">' + contrastHtml(row) + '</div>';
      }
      h += '<div class="efb mb-1 fs-7">' + esc(T('sbgPresets', 'Presets')) + '</div><div class="efb d-flex flex-wrap gap-2 mb-2">';
      PRESETS.forEach(function (p, i) {
         var pr = resolve({ step_bg_type: p.type, step_bg_stops: p.stops, step_bg_angle: p.angle, step_bg_pos: p.pos }, {}, { gradient: true });
         var on = mode === 'gradient' && row.step_bg_stops === p.stops && row.step_bg_type === p.type;
         h += '<button type="button" class="efb efb-sbg-swatch" data-efb-sbg="preset" data-i="' + i + '" id="' + id + 'Preset' + i + 'El" aria-pressed="' + on + '" aria-label="' + esc(T('sbgPreset', 'Preset %s').replace('%s', String(i + 1))) + (lockG ? ' (' + esc(T('upgFtrStepBgGradient', 'Gradient step backgrounds')) + ')' : '') + '" style="' + summary(pr) + '"></button>';
      });
      h += '</div>';
      if (mode !== 'inherit') {
         h += '<button type="button" class="efb btn btn-sm btn-outline-primary mb-2" data-efb-sbg="applyall" id="' + id + 'ApplyAllEl"><i class="efb bi-files"></i> ' + esc(T('sbgApplyAll', 'Use for all steps')) + '</button>';
      }
      if (mode === 'solid' || mode === 'gradient') {
         var mob = sanitizeKey('mobile_step_bg', row.mobile_step_bg);
         var mob_html = '<div class="efb efb-mobile-settings-efb' + (mobileView() ? '' : ' d-none') + '"><label for="' + id + 'MobileEl" class="efb form-label mb-1"><i class="efb bi-phone fs-7"></i> ' + esc(T('sbgMobile', 'Background on mobile')) + '</label>'
            + '<select id="' + id + 'MobileEl" data-efb-sbg="mobile" class="efb form-select efb border-d rounded-4 mb-2"' + (opts.mobile ? '' : ' disabled') + '>'
            + '<option value=""' + (mob === '' ? ' selected' : '') + '>' + esc(T('sbgMobileSame', 'Same as desktop')) + '</option>'
            + '<option value="none"' + (mob === 'none' ? ' selected' : '') + '>' + esc(T('sbgMobileNone', 'No background')) + '</option>'
            + '<option value="solid"' + (mob === 'solid' ? ' selected' : '') + '>' + esc(T('sbgMobileSolid', 'First color only')) + '</option></select></div>';
         h += typeof efbMobileProControlEfb === 'function' ? efbMobileProControlEfb(mob_html) : mob_html;
      }
      return h;
   }

   /* The section the step settings panel shows (val-efb.js, case "steps"). */
   function panel(indx) {
      var rows = rowsEfb();
      if (!rows || !rows[indx] || rows[indx].type !== 'step') return '';
      ensureBuilderCss();
      return '<div class="efb efb-sbg-panel col-12 px-0" id="efbSbgPanelEl" data-efb-sbg-panel data-indx="' + indx + '">' + inner(indx) + '</div>';
   }

   function rerender(indx, focusId) {
      var p = document.getElementById('efbSbgPanelEl');
      if (!p) return;
      p.innerHTML = inner(indx);
      if (focusId) { var f = document.getElementById(focusId); if (f) f.focus(); }
   }

   function setKey(row, k, v) {
      var s = sanitizeKey(k, v);
      if (s === '') delete row[k]; else row[k] = s;
   }

   function refresh(indx) {
      var rows = rowsEfb();
      var row = rows[indx];
      var r = resolve(row, rows[0], planOpts());
      var bar = document.getElementById('efbSbgBarEl');
      if (bar) bar.setAttribute('style', summary(r));
      var c = document.getElementById('efbSbgContrastEl');
      if (c) c.innerHTML = contrastHtml(row);
      var ov = document.getElementById('efbSbgOpacityValEl');
      if (ov) ov.textContent = (sanitizeKey('step_bg_opacity', row.step_bg_opacity) || '100') + '%';
      paintCanvas();
   }

   var gradientLocked = function () {
      if (planOpts().gradient) return false;
      if (typeof efb_upgrade_prompt_efb === 'function') efb_upgrade_prompt_efb({ feature: 'setting:step_bg_gradient', fallback: 'free_plus', origin: 'field_settings' });
      return true;
   };
   var latin = function (v) {
      return String(v).replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); })
         .replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); });
   };

   function handle(e) {
      var el = e.target && e.target.closest ? e.target.closest('[data-efb-sbg]') : null;
      if (!el) return;
      var p = el.closest('[data-efb-sbg-panel]');
      if (!p) return;
      var rows = rowsEfb();
      var indx = parseInt(p.getAttribute('data-indx'), 10);
      var row = rows && rows[indx];
      if (!row || row.type !== 'step') return;
      var act = el.getAttribute('data-efb-sbg');
      var isClick = e.type === 'click';
      var isInput = e.type === 'input';
      if ((act === 'mode' || act === 'stopdel' || act === 'stopadd' || act === 'preset' || act === 'applyall') !== isClick) return;
      var i = parseInt(el.getAttribute('data-i'), 10);
      var stops = parseStops(row.step_bg_stops);
      switch (act) {
         case 'mode': {
            var v = el.getAttribute('data-v');
            if (v === 'gradient' && gradientLocked()) return;
            if (v === 'inherit') KEYS.forEach(function (k) { delete row[k]; });
            else if (v === 'none') row.step_bg_type = 'none';
            else if (v === 'solid') {
               row.step_bg_type = 'solid';
               if (!hex(row.step_bg_color)) row.step_bg_color = stops.length ? stops[0][0] : '#e0f2fe';
            } else {
               row.step_bg_type = row.step_bg_type === 'radial' ? 'radial' : 'linear';
               if (!stops.length) row.step_bg_stops = (hex(row.step_bg_color) || '#e0f2fe') + ' 0,#c7d2fe 100';
               if (row.step_bg_type === 'linear' && sanitizeKey('step_bg_angle', row.step_bg_angle) === '') row.step_bg_angle = '135';
            }
            rerender(indx, 'efbSbgMode_' + v);
            paintCanvas();
            return;
         }
         case 'color': if (!isInput && e.type !== 'change') return; setKey(row, 'step_bg_color', el.value); break;
         case 'opacity': setKey(row, 'step_bg_opacity', latin(el.value)); break;
         case 'angle': {
            if (!isInput && e.type !== 'change') return;
            setKey(row, 'step_bg_angle', latin(el.value));
            ['efbSbgAngleEl', 'efbSbgAngleNumEl'].forEach(function (x) { var o = document.getElementById(x); if (o && o !== el) o.value = row.step_bg_angle || '135'; });
            break;
         }
         case 'gtype': if (e.type !== 'change') return; setKey(row, 'step_bg_type', el.value); if (row.step_bg_type === 'radial' && !row.step_bg_pos) row.step_bg_pos = 'center'; rerender(indx, 'efbSbgTypeEl'); paintCanvas(); return;
         case 'pos': if (e.type !== 'change') return; setKey(row, 'step_bg_pos', el.value); break;
         case 'stopc':
            if (!stops[i] || !hex(el.value)) return;
            stops[i][0] = hex(el.value);
            row.step_bg_stops = stopsString(stops);
            break;
         case 'stopp': {
            if (e.type !== 'change' || !stops[i]) return;
            var n = sanitizeKey('step_bg_opacity', latin(el.value));
            if (n === '') { el.value = stops[i][1]; return; }
            stops[i][1] = parseInt(n, 10);
            row.step_bg_stops = sanitizeKey('step_bg_stops', stopsString(stops));
            rerender(indx, el.id);
            paintCanvas();
            return;
         }
         case 'stopdel':
            if (stops.length <= 2 || !stops[i]) return;
            stops.splice(i, 1);
            row.step_bg_stops = stopsString(stops);
            rerender(indx, 'efbSbgStopAddEl');
            paintCanvas();
            return;
         case 'stopadd': {
            if (stops.length >= 5) return;
            var at = 0, gap = -1;
            for (var j = 0; j < stops.length - 1; j++) { if (stops[j + 1][1] - stops[j][1] > gap) { gap = stops[j + 1][1] - stops[j][1]; at = j; } }
            var a = rgb(stops[at][0]), b = rgb(stops[at + 1][0]);
            var mid = '#' + [0, 1, 2].map(function (k) { return ('0' + Math.round((a[k] + b[k]) / 2).toString(16)).slice(-2); }).join('');
            stops.splice(at + 1, 0, [mid, Math.round((stops[at][1] + stops[at + 1][1]) / 2)]);
            row.step_bg_stops = sanitizeKey('step_bg_stops', stopsString(stops));
            rerender(indx, 'efbSbgStopC' + (at + 1) + 'El');
            paintCanvas();
            return;
         }
         case 'preset': {
            var pr = PRESETS[i];
            if (!pr || gradientLocked()) return;
            row.step_bg_type = pr.type;
            row.step_bg_stops = pr.stops;
            if (pr.angle) row.step_bg_angle = pr.angle;
            if (pr.pos) row.step_bg_pos = pr.pos;
            if (pr.text) row.step_bg_text = pr.text;
            rerender(indx, el.id);
            paintCanvas();
            return;
         }
         case 'text': {
            if (e.type !== 'change') return;
            if (el.value === 'keep') delete row.step_bg_text;
            else if (el.value === 'auto') row.step_bg_text = 'auto';
            else row.step_bg_text = hex(row.step_bg_text) || '#111827';
            rerender(indx, 'efbSbgTextEl');
            paintCanvas();
            return;
         }
         case 'textc': if (!hex(el.value)) return; row.step_bg_text = hex(el.value); break;
         case 'mobile':
            if (e.type !== 'change') return;
            if (typeof efbHasMobileProAccessEfb === 'function' && !efbHasMobileProAccessEfb()) { el.value = row.mobile_step_bg || ''; return; }
            setKey(row, 'mobile_step_bg', el.value);
            break;
         case 'applyall': {
            if (!root.confirm(T('sbgApplyAllConfirm', 'Use this background for every step? Steps with their own background will use it too.'))) return;
            var f0 = rows[0];
            KEYS.forEach(function (k) { if (Object.prototype.hasOwnProperty.call(row, k)) f0[k] = row[k]; else delete f0[k]; });
            rows.forEach(function (r2, x) { if (x > 0 && r2.type === 'step') KEYS.forEach(function (k) { delete r2[k]; }); });
            rerender(indx, 'efbSbgMode_inherit');
            paintCanvas();
            return;
         }
         default: return;
      }
      refresh(indx);
   }

   ['input', 'change', 'click'].forEach(function (t) { document.addEventListener(t, handle); });

   define('efbStepBgCoreEfb', core);
   define('efbStepBgCssEfb', css);
   define('efbStepBgContrastEfb', function (fg, r) { return contrast(fg, samples(r)); });
   define('efbStepBgPanelEfb', panel);
   define('efbStepBgCanvasAttrEfb', canvasAttr);
   define('efbStepBgPaintCanvasEfb', paintCanvas);
   define('efbStepBgPreviewCssEfb', previewCss);
})(typeof window !== 'undefined' ? window : null);
