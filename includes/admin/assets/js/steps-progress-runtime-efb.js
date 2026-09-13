/* ==========================================================================
   Steps process + progress bar - runtime
   --------------------------------------------------------------------------
   The part a rendered form needs: one function that moves the indicator when
   the visitor changes step. Nothing here builds markup, because on the front
   end the markup comes from PHP; the builders live next door in
   steps-progress-builder-efb.js and only wp-admin loads them.

   The front end never enqueues this file. efb_steps_inline_runtime_efb() in
   includes/functions.php reads it and prints it inside the form's own <script>
   block, and only when the form uses one of the new styles - a form left on
   the classic styles keeps the old add/remove path in core-efb.js and pays for
   none of this. Every caller therefore guards on
   `typeof efbStepsSyncEfb === 'function'` and falls back.

   Attached as a guarded window property rather than declared: this runs on the
   front end, where public/assets/js/core-efb.js redeclares a number of the
   plugin's globals with `let`, and a bare const here would throw before any of
   it ran. See the markup contract at the top of
   includes/admin/assets/css/steps-progress-efb.css.
   ========================================================================== */
(function () {
   'use strict';

   var defineEfb = function (name, value) {
      if (typeof window[name] === 'undefined') {
         Object.defineProperty(window, name, { value: value, writable: true, configurable: true });
      }
   };

   var debugEfb = function (eventName, details) {
      try {
         if (typeof window !== 'undefined' && window.EFB_STEPS_DEBUG === false) return;
         console.log('[EFB Steps Debug]', 'runtime:' + eventName, details || {});
      } catch (e) {}
   };

   var textEfb = function (key, fallback) {
      try {
         if (typeof efb_var !== 'undefined' && efb_var && efb_var.text && efb_var.text[key]) return efb_var.text[key];
      } catch (e) { /* efb_var is not defined in every harness */ }
      return fallback;
   };

   var counterTextEfb = function (current, total) {
      var tpl = textEfb('stepXofY', 'Step %1$s of %2$s');
      return String(tpl).replace('%1$s', current).replace('%2$s', total);
   };

   var completeTextEfb = function (percent) {
      var tpl = textEfb('percentComplete', '%s complete');
      return String(tpl).replace('%s', percent + '%');
   };

   /**
    * Move the whole indicator to a step.
    *
    * Called by every navigation path there is: core-efb.js on the front end,
    * new-efb.js and admin-efb.js in the preview, and persia_pay-efb.js when a
    * visitor comes back from a bank. Recomputing every row from the current
    * step is what makes a "done" state possible at all - the pair of add/remove
    * calls it replaced could only ever say which step you were on, and a
    * conditional-logic jump moves by more than one step at a time.
    *
    * Safe to call on a form whose steps row is still the classic one: there are
    * no .efb-sp__item rows to recompute, so it leaves them to the legacy
    * handler and only moves the progress half.
   */
   var syncEfb = function (scope, current, total, currentName) {
      var root = null;
      debugEfb('sync:start', {
         scope_type: !scope ? 'empty' : (scope.classList ? 'element' : typeof scope),
         scope_class: scope && scope.className ? String(scope.className) : null,
         requested_current: current,
         requested_total: total,
         requested_name: currentName
      });
      if (!scope) {
         root = document.querySelector('.efb-sp');
      } else if (scope.classList && scope.classList.contains('efb-sp')) {
         root = scope;
      } else if (scope.querySelector) {
         root = scope.querySelector('.efb-sp');
      } else {
         root = document.querySelector('.efb-sp[data-formid="' + scope + '"]') || document.querySelector('.efb-sp');
      }
      if (!root) {
         debugEfb('sync:stop:root-missing', {
            requested_current: current,
            requested_total: total,
            requested_name: currentName
         });
         return false;
      }

      /* The row itself is the authority on how many stops there are. The count
         the caller passes comes from valj_efb[0].steps, which is not always the
         number of steps that were actually drawn - the free plan clamps it to
         its own limit while leaving the step rows in place, and a form built
         under a licence that has since lapsed then reports fewer steps than it
         renders. Trusting the caller there put "Step 1 of 3" under a row of
         four. The passed count is the fallback for a form whose steps row is
         switched off or still classic, where there is nothing to count. */
      var items = root.querySelectorAll('.efb-sp__item');
      /* A classic row carries no .efb-sp__item, but it is still a row and still
         knows how many stops there are, so it is counted for the total even
         though its states are left to the handler that owns it. */
      var rowLen = items.length || root.querySelectorAll('#steps-efb li').length;
      debugEfb('sync:root-resolved', {
         root_classes: root.className,
         root_formid: root.getAttribute ? root.getAttribute('data-formid') : null,
         sp_items: items.length,
         row_items: rowLen,
         requested_current: current,
         requested_total: total,
         current_name: currentName,
         ring_found: !!root.querySelector('.efb-sp__ring'),
         fill_found: !!(root.querySelector('.efb-sp__fill') || root.querySelector('.progress-bar-efb')),
         seg_count: root.querySelectorAll('.efb-sp__seg').length
      });
      total = rowLen || Number(total) || 1;
      current = Number(current) || 1;
      if (current < 1) current = 1;
      if (current > total) current = total;

      var activeName = '';
      for (var i = 0; i < items.length; i++) {
         var num = Number(items[i].getAttribute('data-num')) || (i + 1);
         var state = num < current ? 'is-done' : (num === current ? 'is-active' : 'is-todo');
         items[i].classList.remove('is-done', 'is-active', 'is-todo');
         items[i].classList.add(state);
         /* .active is what the plugin's own selectors and the classic
            stylesheet still look for, so it is kept in step rather than
            replaced. */
         if (num === current) {
            items[i].classList.add('active');
            var lab = items[i].querySelector('.efb-sp__label');
            activeName = lab ? lab.textContent : '';
         } else {
            items[i].classList.remove('active');
         }
      }
      debugEfb('sync:items-painted', {
         current: current,
         total: total,
         active_name_from_row: activeName,
         active_id: root.querySelector('.efb-sp__item.is-active') ? root.querySelector('.efb-sp__item.is-active').id : null,
         classes: Array.prototype.map.call(items, function (item) {
            return {
               id: item.id,
               num: item.getAttribute('data-num'),
               className: item.className
            };
         })
      });

      /* Two numbers on purpose: the width and the arc want the exact fraction,
         the caption wants something a person can read - "66.67%" beside
         "Step 2 of 3" is noise. */
      var percent = Math.round((current / total) * 10000) / 100;
      var readable = Math.round(percent);
      var counter = counterTextEfb(current, total);

      var setText = function (sel, value) {
         var els = root.querySelectorAll(sel);
         for (var j = 0; j < els.length; j++) els[j].textContent = value;
      };
      setText('.efb-sp__counter', counter);
      setText('.efb-sp__metaname', counter);
      setText('.efb-sp__pct', readable + '%');
      /* With the steps row switched off or classic there are no <li> to read
         the caption from, so the caller passes the name it just put in the
         title instead. */
      var name = typeof currentName === 'string' && currentName !== '' ? currentName : activeName;
      if (items.length || (typeof currentName === 'string' && currentName !== '')) setText('.efb-sp__curname', name);
      setText('.efb-sp__ringsub', counter + ' \u00b7 ' + completeTextEfb(readable));
      setText('.efb-sp__ring-in', current + '/' + total);

      var fill = root.querySelector('.efb-sp__fill') || root.querySelector('.progress-bar-efb');
      if (fill) {
         fill.style.width = percent + '%';
         fill.setAttribute('aria-valuenow', percent);
      }

      var segs = root.querySelectorAll('.efb-sp__seg');
      for (var s = 0; s < segs.length; s++) {
         var n = s + 1;
         segs[s].classList.remove('is-done', 'is-active', 'is-todo');
         segs[s].classList.add(n < current ? 'is-done' : (n === current ? 'is-active' : 'is-todo'));
      }

      var ring = root.querySelector('.efb-sp__ring');
      if (ring) ring.style.setProperty('--efb-sp-pct', percent);
      debugEfb('sync:progress-painted', {
         current: current,
         total: total,
         percent: percent,
         readable: readable,
         name: name,
         counter: counter,
         fill_found: !!fill,
         fill_width: fill ? fill.style.width : null,
         fill_aria: fill ? fill.getAttribute('aria-valuenow') : null,
         ring_found: !!ring,
         ring_pct: ring ? ring.style.getPropertyValue('--efb-sp-pct') : null,
         ring_text: root.querySelector('.efb-sp__ring-in') ? root.querySelector('.efb-sp__ring-in').textContent : null,
         ring_sub: root.querySelector('.efb-sp__ringsub') ? root.querySelector('.efb-sp__ringsub').textContent : null
      });

      /* A strip that scrolls is useless if the step you are on is off-screen.
         scrollLeft is set directly rather than through scrollIntoView(), which
         would also scroll the page and fight the smooth scroll the navigation
         buttons already do. */
      var list = root.querySelector('.efb-sp__steps');
      if (list && list.scrollWidth > list.clientWidth + 1) {
         var act = root.querySelector('.efb-sp__item.is-active');
         if (act) {
            var target = act.offsetLeft - (list.clientWidth - act.offsetWidth) / 2;
            var max = list.scrollWidth - list.clientWidth;
            target = target < 0 ? 0 : (target > max ? max : target);
            try { list.scrollTo({ left: target, behavior: 'smooth' }); }
            catch (e) { list.scrollLeft = target; }
            debugEfb('sync:scrolled-active-into-view', {
               target: target,
               max: max,
               list_scroll_width: list.scrollWidth,
               list_client_width: list.clientWidth
            });
         }
      }
      debugEfb('sync:done', {
         current: current,
         total: total,
         percent: percent,
         active_id: root.querySelector('.efb-sp__item.is-active') ? root.querySelector('.efb-sp__item.is-active').id : null
      });
      return true;
   };

   defineEfb('efbStepsCounterTextEfb', counterTextEfb);
   defineEfb('efbStepsCompleteTextEfb', completeTextEfb);
   defineEfb('efbStepsSyncEfb', syncEfb);
   debugEfb('loaded', {
      counter_defined: typeof window.efbStepsCounterTextEfb === 'function',
      complete_defined: typeof window.efbStepsCompleteTextEfb === 'function',
      sync_defined: typeof window.efbStepsSyncEfb === 'function'
   });
})();
