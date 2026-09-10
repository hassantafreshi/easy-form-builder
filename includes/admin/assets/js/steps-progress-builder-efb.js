/* ==========================================================================
   Steps process + progress bar - markup builders
   --------------------------------------------------------------------------
   wp-admin only. The builder canvas and previewFormEfb() have to draw the same
   tree that class-Emsfb-public.php draws on the front end, so these are the
   JavaScript twins of the efb_steps_* helpers in includes/functions.php -
   change one, change the other. The front end loads none of this: there the
   markup arrives from PHP already rendered, and only the runtime next door in
   steps-progress-runtime-efb.js is inlined into the page.

   Attached as guarded window properties for the same reason the runtime is:
   these two files also load together on the front end during development, and
   nothing here may collide with a `let` in core-efb.js.

   The markup contract lives at the top of
   includes/admin/assets/css/steps-progress-efb.css.
   ========================================================================== */
(function () {
   'use strict';

   var defineEfb = function (name, value) {
      if (typeof window[name] === 'undefined') {
         Object.defineProperty(window, name, { value: value, writable: true, configurable: true });
      }
   };

   /* The bootstrap-ish colour names the plugin stores instead of a hex. Kept in
      step with ColorNameToHexEfbOfElEfb() in admin-efb.js and with
      efb_steps_color_hex_efb() in includes/functions.php. */
   var namedEfb = {
      primary: '#0d6efd', success: '#198754', secondary: '#6c757d', danger: '#ff455f',
      warning: '#e9c31a', info: '#31d2f2', light: '#fbfbfb', darkb: '#202a8d',
      labelEfb: '#898aa9', d: '#83859f', pinkEfb: '#ff4b93', white: '#ffffff',
      dark: '#212529', muted: '#777777'
   };

   /* A stored colour is a class, never a value: "btn-colorDEfb-4636f1" for a
      colour picked from the wheel, "text-pinkEfb" for one of the presets. Both
      shapes have to resolve to the hex the form is actually painted with, which
      is why this mirrors the slicing the rest of the plugin does. */
   var toHexEfb = function (cls, fallback) {
      fallback = fallback || '#202a8d';
      if (!cls) return fallback;
      var s = String(cls).trim();
      if (!s) return fallback;
      if (s.charAt(0) === '#') return s.length === 4 || s.length === 7 ? s : fallback;
      var at = s.indexOf('colorDEfb-');
      if (at !== -1) {
         var hex = s.slice(at + 'colorDEfb-'.length).replace(/[^0-9a-fA-F]/g, '');
         if (hex.length >= 6) return '#' + hex.slice(0, 6);
         if (hex.length === 3) return '#' + hex;
         return fallback;
      }
      var name = s.replace(/^(btn-outline-|btn-|text-|bg-|border-)/, '').trim();
      return namedEfb[name] || fallback;
   };

   var rgbEfb = function (hex) {
      var h = String(hex).replace('#', '');
      if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
      return [parseInt(h.slice(0, 2), 16) || 0, parseInt(h.slice(2, 4), 16) || 0, parseInt(h.slice(4, 6), 16) || 0];
   };

   var clampEfb = function (n) { return n < 0 ? 0 : (n > 255 ? 255 : Math.round(n)); };

   /* ratio < 0 darkens towards black, ratio > 0 lightens towards white. */
   var shadeEfb = function (hex, ratio) {
      var c = rgbEfb(hex);
      var target = ratio < 0 ? 0 : 255;
      var k = Math.abs(ratio);
      var out = '#';
      for (var i = 0; i < 3; i++) {
         out += ('0' + clampEfb(c[i] + (target - c[i]) * k).toString(16)).slice(-2);
      }
      return out;
   };

   /* Perceived brightness, so a caption that lands on a filled shape stays
      readable whichever colour the author picked. The 0.62 cut is deliberately
      above the usual half: mid blues read as dark long after the arithmetic
      says otherwise. */
   var onAccentEfb = function (hex) {
      var c = rgbEfb(hex);
      var lum = (c[0] * 0.299 + c[1] * 0.587 + c[2] * 0.114) / 255;
      return lum > 0.62 ? '#1b1f3b' : '#ffffff';
   };

   var paletteEfb = function (colorClass, fallback) {
      var accent = toHexEfb(colorClass, fallback);
      var c = rgbEfb(accent);
      return {
         accent: accent,
         accentDark: shadeEfb(accent, -0.28),
         soft: 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',.16)',
         onAccent: onAccentEfb(accent),
         /* The track and the hairlines are the author's colour washed out, not
            a grey chosen here - a blue form gets a blue-grey track, a pink one
            a pink-grey track, and neither has to be configured. */
         track: shadeEfb(accent, 0.88),
         line: shadeEfb(accent, 0.78),
         bg: shadeEfb(accent, 0.92)
      };
   };

   /* The custom properties the wrapper carries. Written as a style attribute
      rather than a stylesheet because every form on the page has its own accent
      and they all answer to the same .efb-sp class. */
   var styleVarsEfb = function (colorClass, fallback) {
      var p = paletteEfb(colorClass, fallback);
      return '--efb-sp-accent:' + p.accent +
         ';--efb-sp-accent-dark:' + p.accentDark +
         ';--efb-sp-soft:' + p.soft +
         ';--efb-sp-on-accent:' + p.onAccent +
         ';--efb-sp-track:' + p.track +
         ';--efb-sp-line:' + p.line;
   };

   /* One class per distinct step colour, the way the plugin already handles
      every other per-field colour. A form whose steps all share one icon colour
      - which is the default - prints a single rule, instead of five custom
      properties repeated on every one of twenty <li>. */
   var colorClassEfb = function (iconColorClass) {
      return 'efb-sp-c-' + toHexEfb(iconColorClass).replace('#', '').toLowerCase();
   };

   var colorRulesEfb = function (iconColorClasses) {
      var seen = {};
      var css = '';
      for (var i = 0; i < iconColorClasses.length; i++) {
         var hex = toHexEfb(iconColorClasses[i]);
         var key = hex.replace('#', '').toLowerCase();
         if (seen[key]) continue;
         seen[key] = true;
         var p = paletteEfb(hex);
         css += '.efb-sp .efb-sp-c-' + key + '{' +
            '--efb-sp-dot:' + p.accent +
            ';--efb-sp-dot-on:' + p.onAccent +
            ';--efb-sp-dot-soft:' + p.soft +
            ';--efb-sp-dot-line:' + p.line +
            ';--efb-sp-dot-bg:' + p.bg + '}';
      }
      return css;
   };

   /* 'classic' is the shape the plugin has always drawn, and the default, so a
      form saved before the styles existed keeps rendering exactly as it did and
      downloads none of the new CSS. */
   var STEP_STYLES_EFB = ['classic', 'circles', 'pills', 'chevrons'];
   var PROG_STYLES_EFB = ['classic', 'bar', 'segments', 'ring'];

   var stepsStyleEfb = function (row) {
      var v = row && row.steps_style ? String(row.steps_style) : '';
      return STEP_STYLES_EFB.indexOf(v) === -1 ? 'classic' : v;
   };
   var progStyleEfb = function (row) {
      var v = row && row.progress_style ? String(row.progress_style) : '';
      return PROG_STYLES_EFB.indexOf(v) === -1 ? 'classic' : v;
   };

   /* Ten is where the captions stop fitting on a desktop row and twenty is
      where the dots themselves start to touch; both tiers are handled in CSS,
      this only decides which of them applies. */
   var densityEfb = function (total) {
      var cls = '';
      if (total > 10) cls += ' efb-sp--compact';
      if (total > 20) cls += ' efb-sp--dense';
      return cls;
   };

   var escEfb = function (s) {
      return String(s === null || typeof s === 'undefined' ? '' : s)
         .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
   };

   var counterTextEfb = function (current, total) {
      if (typeof efbStepsCounterTextEfb === 'function') return efbStepsCounterTextEfb(current, total);
      return 'Step ' + current + ' of ' + total;
   };
   var completeTextEfb = function (percent) {
      if (typeof efbStepsCompleteTextEfb === 'function') return efbStepsCompleteTextEfb(percent);
      return percent + '% complete';
   };

   /* ---------------------------------------------------------------------
      Markup. Only the shell is built here - the <li> for each step is still
      written by whichever renderer owns it, because the three of them carry
      different ids and only they know the step rows. What they all share is
      the wrapper, the caption and the progress block, which is what drifts if
      it is copied three times.
      --------------------------------------------------------------------- */

   /* The wrapper exists only to carry the shared colours and the compact
      threshold, so a form on the classic styles throughout does not get one -
      which is also what keeps efbStepsSyncEfb() from claiming a classic row. */
   var needsShellEfb = function (o) {
      return (o.showSteps !== false && stepsStyleEfb({ steps_style: o.stepsStyle }) !== 'classic')
          || (o.showProgress !== false && progStyleEfb({ progress_style: o.progressStyle }) !== 'classic');
   };

   /* Two things make the caption redundant. A ring names the step itself, so
      printing the caption as well says the same thing twice. A classic row
      keeps all of its captions at every width, and the form's own heading names
      the current one above it. What is left is the case the caption exists for:
      one of the new rows, where the captions are dropped once they stop
      fitting, and a form with no row at all. */
   var wantsHeaderEfb = function (o) {
      if (o.showProgress !== false && o.progressStyle === 'ring') return false;
      return !(o.showSteps !== false && o.stepsStyle === 'classic');
   };

   var wrapOpenEfb = function (o) {
      var cls = 'efb efb-sp efb-sp--' + o.stepsStyle + ' efb-sp--prog-' + o.progressStyle + densityEfb(o.total);
      /* Only a form with no row at all - not one with a classic row, which
         carries its own captions - needs the caption forced on at every width. */
      if (o.showSteps === false) cls += ' efb-sp--norow';
      if (o.rtl) cls += ' efb-sp--rtl';
      return '<div class="' + cls + '" data-formid="' + escEfb(o.formId) + '"' +
         ' data-steps-style="' + o.stepsStyle + '" data-progress-style="' + o.progressStyle + '"' +
         ' style="' + styleVarsEfb(o.accentClass) + '">';
   };

   var headerEfb = function (o) {
      return '<div class="efb efb-sp__current" data-formid="' + escEfb(o.formId) + '">' +
         '<span class="efb efb-sp__counter">' + escEfb(counterTextEfb(o.current, o.total)) + '</span>' +
         '<span class="efb efb-sp__curname">' + escEfb(o.currentName) + '</span>' +
         '</div>';
   };

   /* The classic bar, unchanged: the author's colour on the track, the striped
      animated fill over it. Kept byte-for-byte so a form that never touched the
      new styles renders exactly what it always did. */
   var classicProgressEfb = function (o) {
      return '<div class="efb d-flex justify-content-center efb-progress-gap" id="f-progress-efb" data-formid="' + escEfb(o.formId) + '">' +
         '<div class="efb progress mx-3 w-100 ' + escEfb(o.accentClass || '') + '">' +
         '<div class="efb progress-bar-efb progress-bar-striped progress-bar-animated" role="progressbar"' +
         ' aria-valuemin="0" aria-valuemax="100" style="width:' + (o.total > 0 ? Math.round((o.current / o.total) * 10000) / 100 : 0) + '%"' +
         ' data-formid="' + escEfb(o.formId) + '"></div></div></div>';
   };

   var progressEfb = function (o) {
      if (o.progressStyle === 'classic') return classicProgressEfb(o);

      var percent = o.total > 0 ? Math.round((o.current / o.total) * 10000) / 100 : 0;
      var readable = Math.round(percent);
      var attrs = ' id="f-progress-efb" data-formid="' + escEfb(o.formId) + '"';
      var meta = '<div class="efb efb-sp__meta">' +
         '<span class="efb efb-sp__metaname">' + escEfb(counterTextEfb(o.current, o.total)) + '</span>' +
         '<span class="efb efb-sp__pct">' + readable + '%</span>' +
         '</div>';

      if (o.progressStyle === 'segments') {
         var segs = '';
         for (var i = 1; i <= o.total; i++) {
            segs += '<span class="efb efb-sp__seg ' + (i < o.current ? 'is-done' : (i === o.current ? 'is-active' : 'is-todo')) + '"></span>';
         }
         return '<div class="efb efb-sp__prog efb-progress-gap"' + attrs + '>' +
            '<div class="efb efb-sp__segs">' + segs + '</div>' + meta + '</div>';
      }

      if (o.progressStyle === 'ring') {
         return '<div class="efb efb-sp__prog efb-progress-gap"' + attrs + '>' +
            '<div class="efb efb-sp__ringwrap">' +
            '<div class="efb efb-sp__ring" style="--efb-sp-pct:' + percent + '">' +
            '<span class="efb efb-sp__ring-in">' + o.current + '/' + o.total + '</span>' +
            '</div>' +
            '<div class="efb efb-sp__ringtext">' +
            '<span class="efb efb-sp__curname">' + escEfb(o.currentName) + '</span>' +
            '<span class="efb efb-sp__ringsub">' + escEfb(counterTextEfb(o.current, o.total)) + ' &middot; ' + escEfb(completeTextEfb(readable)) + '</span>' +
            '</div></div></div>';
      }

      /* The author's colour rides on the fill rather than the track it used to
         sit on, so the bar reads as progress instead of a coloured strip. */
      return '<div class="efb efb-sp__prog efb-progress-gap"' + attrs + '>' + meta +
         '<div class="efb progress efb-sp__track">' +
         '<div class="efb progress-bar-efb efb-sp__fill ' + escEfb(o.accentClass || '') + '" role="progressbar"' +
         ' aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + percent + '"' +
         ' style="width:' + percent + '%;" data-formid="' + escEfb(o.formId) + '"></div>' +
         '</div></div>';
   };

   /* The <li> class list, so all three renderers stay in step over which state
      class a step carries and where the number for compact mode comes from. The
      author's icon and colour classes are passed straight through, and the
      colour class carries that step's own palette. */
   var itemClassEfb = function (num, current, iconColor, icon, stepsStyle) {
      /* The classic row keeps the class list it has always had, so the rules in
         style-efb.css - which are scoped to a <ul> without efb-sp__steps - still
         match it and nothing about it moves. */
      if (stepsStyleEfb({ steps_style: stepsStyle || 'circles' }) === 'classic') {
         return ('efb ' + (iconColor || '') + ' ' + (icon || '') + (num === current ? ' active' : '')).trim();
      }
      var state = num < current ? 'is-done' : (num === current ? 'is-active' : 'is-todo');
      return ('efb efb-sp__item ' + state + ' ' + colorClassEfb(iconColor) + ' ' +
         (iconColor || '') + ' ' + (icon || '') + (num === current ? ' active' : '')).trim();
   };

   /**
    * The whole head in one call.
    *
    * Both admin renderers assemble exactly the same thing, and the front end
    * assembles it from the PHP twins of these helpers. `items` is the <li> run
    * the caller built, since only the caller knows the ids its own markup uses.
    */
   var shellHeadEfb = function (o) {
      if (!o.showSteps && !o.showProgress) return '';

      var stepsClassic = o.stepsStyle === 'classic';
      var rules = o.colorRules || '';
      var row = o.showSteps
         ? '<ul id="steps-efb" class="efb ' + (stepsClassic ? '' : 'efb-sp__steps ') + 'mb-2 px-2">' + o.items + '</ul>'
         : '';

      /* Classic throughout: no wrapper, no custom properties, nothing for the
         runtime to claim - the same markup and the same code path as before the
         styles existed. */
      if (!needsShellEfb(o)) {
         return row + (o.showProgress ? classicProgressEfb(o) : '');
      }

      return wrapOpenEfb(o) +
         (rules ? '<style>' + rules + '</style>' : '') +
         (wantsHeaderEfb(o) ? headerEfb(o) : '') +
         row +
         (o.showProgress ? progressEfb(o) : '') +
         '</div>';
   };

   defineEfb('efbStepsShellHeadEfb', shellHeadEfb);
   defineEfb('efbStepsNeedsShellEfb', needsShellEfb);
   defineEfb('efbStepsWantsHeaderEfb', wantsHeaderEfb);
   defineEfb('efbStepsColorHexEfb', toHexEfb);
   defineEfb('efbStepsPaletteEfb', paletteEfb);
   defineEfb('efbStepsStyleVarsEfb', styleVarsEfb);
   defineEfb('efbStepsColorClassEfb', colorClassEfb);
   defineEfb('efbStepsColorRulesEfb', colorRulesEfb);
   defineEfb('efbStepsStyleNameEfb', stepsStyleEfb);
   defineEfb('efbProgressStyleNameEfb', progStyleEfb);
   defineEfb('efbStepsDensityClassEfb', densityEfb);
   defineEfb('efbStepsWrapOpenEfb', wrapOpenEfb);
   defineEfb('efbStepsHeaderEfb', headerEfb);
   defineEfb('efbStepsProgressEfb', progressEfb);
   defineEfb('efbStepsItemClassEfb', itemClassEfb);
   defineEfb('EFB_STEP_STYLES_EFB', STEP_STYLES_EFB);
   defineEfb('EFB_PROGRESS_STYLES_EFB', PROG_STYLES_EFB);
})();
