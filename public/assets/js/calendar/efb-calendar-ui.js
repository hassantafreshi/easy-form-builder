/*!
 * Easy Form Builder - the date picker of the advanced date field (F06 §5, contract §4).
 * Vanilla JS, no jQuery, no build. Needs efb-calendar-core.js (and the engines of the field's calendars).
 * Mounts every [data-efb-cal] element: a typed input with a mask (always available), a button that opens
 * a dialog (popover; bottom sheet under 576 px) with the APG date-picker keyboard model (roving tabindex,
 * arrows reversed in RTL, PageUp/Down, Shift+PageUp/Down, Home/End, Esc, t), the calendar switch,
 * range / multiple / time, holidays and availability. The grid is built only when the dialog opens.
 * The value lives in the hidden input (.emsFormBuilder_v, canonical ISO); every committed change fires
 * EFBCalendar 'change', a bubbling DOM 'efb:date-change' and a native 'change' on that input.
 * The server checks the value again on submit (includes/class-Emsfb-calendar.php).
 */
(function (root) {
  'use strict';
  var C = root && root.EFBCalendar;
  if (!C || C.__ui) { return; }
  C.__ui = true;
  var doc = root.document;
  var R = C.rules, U = C._;

  // #region text
  var EN = {
    cal_choose_date: 'Choose date', cal_choose_range: 'Choose dates', cal_today: 'Today', cal_clear: 'Clear', cal_ok: 'OK',
    cal_prev_month: 'Previous month', cal_next_month: 'Next month', cal_prev_year: 'Previous year', cal_next_year: 'Next year',
    cal_month_year_view: 'Choose month and year', cal_from: 'From', cal_to: 'To', cal_nights_n: '%s nights', cal_days_n: '%s days',
    cal_time: 'Time', cal_selected_dates: 'Selected dates', cal_remove_date: 'Remove %s', cal_format_hint: 'Format: %s',
    cal_closed: 'not available', cal_holiday: 'holiday', cal_full: 'fully booked', cal_remaining_n: '%s left', cal_tz_note: 'time in %s',
    cal_approx_note: 'This date may differ by one day from the official calendar.', cal_switch_label: 'Calendar', cal_add_date: 'Add date',
    cal_year: 'Year', cal_selected: 'selected', cal_range_sum: '%1$s to %2$s (%3$s)', cal_dialog: 'Choose date, %s', cal_back: 'Back to days'
  };
  function tx(k) {
    var t = root.efb_var && root.efb_var.text ? root.efb_var.text : (root.ajax_object_efm && root.ajax_object_efm.text ? root.ajax_object_efm.text : null);
    return t && typeof t[k] === 'string' && t[k] !== '' ? t[k] : (EN[k] || k);
  }
  function msgLang() {
    var t = root.efb_var && root.efb_var.text ? root.efb_var.text : (root.ajax_object_efm && root.ajax_object_efm.text ? root.ajax_object_efm.text : {});
    return t || {};
  }
  var FSI = '⁨', PDI = '⁩';
  function el(tag, attrs, kids) {
    var e = doc.createElement(tag);
    if (attrs) { for (var k in attrs) { if (attrs.hasOwnProperty(k) && attrs[k] !== null && attrs[k] !== undefined && attrs[k] !== false) { if (k === 'text') { e.textContent = attrs[k]; } else if (k === 'cls') { e.className = attrs[k]; } else { e.setAttribute(k, attrs[k] === true ? '' : attrs[k]); } } } }
    if (kids) { for (var i = 0; i < kids.length; i++) { if (kids[i]) { e.appendChild(typeof kids[i] === 'string' ? doc.createTextNode(kids[i]) : kids[i]); } } }
    return e;
  }
  function isRtl(node) {
    var n = node;
    while (n && n.nodeType === 1) { var d = n.getAttribute('dir'); if (d === 'rtl' || d === 'ltr') { return d === 'rtl'; } n = n.parentNode; }
    try { return root.getComputedStyle(node).direction === 'rtl'; } catch (e) { return false; }
  }
  // #endregion

  // #region registry
  var fields = {}, waiters = {};
  function key(fid, formId) { return (formId ? String(formId) : '0') + ':' + fid; }
  function find(fid, formId) {
    if (formId !== undefined && formId !== null && fields[key(fid, formId)]) { return fields[key(fid, formId)]; }
    for (var k in fields) { if (fields.hasOwnProperty(k) && fields[k].fieldId === fid && (formId === undefined || formId === null || String(fields[k].formId) === String(formId))) { return fields[k]; } }
    return null;
  }
  C.get = function (fid, formId) { return find(fid, formId); };
  C.all = function (formId) { var o = []; for (var k in fields) { if (fields.hasOwnProperty(k) && (formId === undefined || String(fields[k].formId) === String(formId))) { o.push(fields[k]); } } return o; };
  C.whenReady = function (fid, formId) {
    var f = find(fid, formId);
    if (f) { return Promise.resolve(f); }
    return new Promise(function (res) { (waiters[fid] = waiters[fid] || []).push({ formId: formId, res: res }); });
  };
  function announceReady(f) {
    var w = waiters[f.fieldId];
    if (!w) { return; }
    waiters[f.fieldId] = w.filter(function (x) { if (x.formId === undefined || x.formId === null || String(x.formId) === String(f.formId)) { x.res(f); return false; } return true; });
  }
  // #endregion

  // #region holiday sets (lazy, one fetch per set)
  var setCache = {}, setPending = {};
  function holidaysUrl() {
    var v = root.efb_var || root.ajax_object_efm || {};
    return (v.cal && v.cal.holidays_url) || root.EFB_CAL_HOLIDAYS_URL || '';
  }
  function loadSets(ids) {
    var need = ids.filter(function (id) { return !setCache.hasOwnProperty(id); });
    if (!need.length || !holidaysUrl() || typeof fetch !== 'function') { return Promise.resolve(setCache); }
    return Promise.all(need.map(function (id) {
      if (!setPending[id]) {
        setPending[id] = fetch(holidaysUrl() + id + '.json', { credentials: 'omit' }).then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) { setCache[id] = d && typeof d === 'object' ? d : null; }, function () { setCache[id] = null; });
      }
      return setPending[id];
    })).then(function () { return setCache; });
  }
  // #endregion

  /** One mounted advdate field. */
  function Field(wrap) {
    var self = this;
    var cfgRaw = {};
    try { cfgRaw = JSON.parse(wrap.getAttribute('data-efb-cal') || '{}'); } catch (e) { cfgRaw = {}; }
    this.wrap = wrap;
    this.fieldId = wrap.getAttribute('data-fid') || cfgRaw.id_ || '';
    this.formId = Number(wrap.getAttribute('data-formid') || cfgRaw.form_id || 0);
    this.raw = cfgRaw;
    this.site = cfgRaw.site || {};
    if (this.site.tz) { U.setSiteTz(this.site.tz); }
    if (!cfgRaw.field) { cfgRaw.field = cfgRaw; }
    this.ctxBase = { locale: cfgRaw.locale || this.site.locale || 'en_US', site_tz: this.site.tz || U.siteTz(), plan: cfgRaw.plan || 'pro', localeDefaults: cfgRaw.locale_defaults || undefined };
    if (cfgRaw.names) { U.setNames(cfgRaw.names); }
    this.cfg = R.normalizeField(cfgRaw.field, this.ctxBase);
    this.input = wrap.querySelector('input.emsFormBuilder_v[type=hidden]') || wrap.querySelector('input[type=hidden]');
    this.text = wrap.querySelector('.efb-cal-input');
    this.text2 = wrap.querySelector('.efb-cal-input-to');
    this.timeIn = wrap.querySelector('.efb-cal-time');
    this.btn = wrap.querySelector('.efb-cal-btn');
    this.dispEl = wrap.querySelector('.efb-cal-disp');
    this.chipsEl = wrap.querySelector('.efb-cal-chips');
    this.msgEl = doc.getElementById((this.text ? this.text.id : this.fieldId + '_') + '-message');
    if (!this.msgEl) {
      this.msgEl = el('small', { id: (this.text ? this.text.id : this.fieldId + '_') + '-message', cls: 'efb efb-cal-msg', style: 'display:none' });
      wrap.appendChild(this.msgEl);
    }
    this.handlers = {};
    this.value = '';
    this.row = null;
    this.extra = null;       // setConstraints() overlay (runtime only)
    this.avail = null;       // availability source
    this.availCache = {};
    this.isOpen = false;
    this.rtl = isRtl(wrap);
    var pref = null;
    try { pref = root.localStorage ? root.localStorage.getItem('efb_cal_pref_' + this.formId + '_' + this.fieldId) : null; } catch (e) { pref = null; }
    this.cal = pref && this.cfg.cal_switch && this.cfg.cal_allowed.indexOf(pref) !== -1 ? pref : this.cfg.cal_default;
    this.pending = null;     // range start while choosing
    this.multi = [];
    this.time = '';
    this.bind();
    var initial = this.input && this.input.value ? this.input.value : (cfgRaw.initial || '');
    var rel = /^today([+-]\d{1,4})?$/.exec(initial);
    if (rel) { initial = U.addDays(R.resolve(this.cfg, this.ctx()).today, rel[1] ? parseInt(rel[1], 10) : 0); }
    var ready = (this.cfg.holiday_sets.length ? loadSets(this.cfg.holiday_sets) : Promise.resolve()).then(function () {
      if (initial) { return self.setValue(initial, { source: 'default' }); }
      self.render();
      return null;
    });
    this.ready = ready.then(function () { self.emit('ready', { fieldId: self.fieldId, formId: self.formId }); return self; });
  }

  Field.prototype.ctx = function (extra) {
    var vals = {};
    var all = C.all(this.formId);
    for (var i = 0; i < all.length; i++) { if (all[i] !== this && all[i].value) { vals[all[i].fieldId] = all[i].cfg.store_format === 'legacy_local' && all[i].row ? all[i].row.iso : all[i].value; } }
    var x = { locale: this.ctxBase.locale, localeDefaults: this.ctxBase.localeDefaults, site_tz: this.ctxBase.site_tz, plan: this.ctxBase.plan, values: vals, holidaySets: setCache, cal: this.cal,
      visitor_tz: (function () { try { return Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) { return ''; } })() };
    var self = this;
    if (this.avail) { x.availability = function (iso) { return self.availOf(iso); }; }
    if (extra) { for (var k in extra) { if (extra.hasOwnProperty(k)) { x[k] = extra[k]; } } }
    return x;
  };
  /** The field row with setConstraints() on top (runtime only; the server never sees it). */
  Field.prototype.fieldRaw = function () {
    var base = this.raw.field || {}, f = {}, k;
    for (k in base) { if (base.hasOwnProperty(k)) { f[k] = base[k]; } }
    if (this.extra) { for (k in this.extra) { if (this.extra.hasOwnProperty(k)) { f[k] = this.extra[k]; } } }
    return f;
  };
  Field.prototype.effective = function () { return this.extra ? R.normalizeField(this.fieldRaw(), this.ctxBase) : this.cfg; };
  Field.prototype.check = function (canonical, cal) {
    return R.validateValue(this.fieldRaw(), canonical, this.ctx({ cal: cal || this.cal }));
  };
  Field.prototype.constraints = function () {
    var c = this.effective();
    var r = R.resolve(c, this.ctx());
    r.holidays = R.listHolidays(c, r, this.ctx());
    return r;
  };

  // #region values
  /** Accepted inputs (contract §4): ISO string (always Gregorian), CanonicalValue, {cal, date[, time]}, {cal, start, end}, {cal, dates}, null. */
  Field.prototype.toCanonical = function (input) {
    if (input === null || input === undefined || input === '') { return { value: '' }; }
    if (typeof input === 'string') { return { value: U.normalize(input) }; }
    if (typeof input !== 'object') { return { error: 'invalid_format' }; }
    if (typeof input.iso === 'string') { return { value: input.iso, cal: input.cal }; }
    var cal = input.cal || 'gregory';
    var e = C.engines.get(cal);
    if (!e) { return { error: 'calendar_not_allowed' }; }
    if (cal !== 'gregory' && this.cfg.cal_allowed.indexOf(cal) === -1) { return { error: 'calendar_not_allowed' }; }
    var adj = this.cfg.cal_hijri_adjust;
    var one = function (s) {
      var m = /^(\d{1,4})-(\d{1,2})-(\d{1,2})$/.exec(U.normalize(s || ''));
      if (!m) { return null; }
      return U.localToIso({ y: +m[1], m: +m[2], d: +m[3] }, cal, adj);
    };
    var parts;
    if (input.start !== undefined || input.end !== undefined) { parts = [one(input.start), one(input.end)]; if (parts[0] === null || parts[1] === null) { return { error: 'invalid_date' }; } return { value: parts.join('/'), cal: cal }; }
    if (Array.isArray(input.dates)) { parts = input.dates.map(one); if (parts.indexOf(null) !== -1) { return { error: 'invalid_date' }; } return { value: parts.join(','), cal: cal }; }
    var d = one(input.date);
    if (d === null) { return { error: 'invalid_date' }; }
    return { value: d + (input.time ? 'T' + U.normalize(input.time) : ''), cal: cal };
  };
  Field.prototype.canonicalValue = function () {
    if (!this.row || !this.value) { return null; }
    var r = this.row, mode = r.mode;
    var iso = this.cfg.store_format === 'legacy_local' ? r.iso : r.value;
    var v = { v: 1, mode: mode, iso: iso, dates: mode === 'single' ? [iso.split('T')[0]] : iso.split(mode === 'range' ? '/' : ','), tz: r.tz, cal: r.cal, local: r.local, disp: r.disp };
    if (r.time) { v.time = r.time; }
    if (r.utc) { v.utc = r.utc; }
    if (r.approx) { v.approx = 1; }
    return v;
  };
  Field.prototype.getValue = function () { return this.canonicalValue(); };
  Field.prototype.result = function (canon) {
    if (canon.error) { return { ok: false, errors: [{ code: canon.error, message: R.message({ code: canon.error }, this.cfg, this.cal, msgLang()) }] }; }
    var r = this.check(canon.value, canon.cal || this.cal);
    if (!r.ok) {
      var self = this;
      return { ok: false, errors: r.errors.map(function (e) { var o = { code: e.code, message: R.message(e, self.cfg, canon.cal || self.cal, msgLang()) }; if (e.date) { o.date = e.date; } if (e.limit !== undefined) { o.limit = e.limit; } return o; }), raw: r };
    }
    return { ok: true, raw: r, cal: canon.cal || this.cal };
  };
  Field.prototype.validate = function (input) {
    var self = this;
    return this.loadAvailabilityFor(this.toCanonical(input).value).then(function () {
      var res = self.result(self.toCanonical(input));
      if (!res.ok) { return { ok: false, errors: res.errors }; }
      return { ok: true, value: self.preview(res) };
    });
  };
  Field.prototype.preview = function (res) {
    var keep = [this.row, this.value];
    this.row = res.raw.row; this.value = res.raw.row.value;
    var v = this.canonicalValue();
    this.row = keep[0]; this.value = keep[1];
    return v;
  };
  Field.prototype.setValue = function (input, opts) {
    opts = opts || {};
    var self = this;
    var canon = this.toCanonical(input);
    return this.loadAvailabilityFor(canon.value).then(function () {
      if (canon.value === '' && !canon.error) { self.clear({ silent: opts.silent, source: opts.source }); return { ok: true, value: null }; }
      var res = self.result(canon);
      if (!res.ok) {
        if (opts.source === 'typed' || opts.source === 'picker' || opts.source === 'restore') { self.showError(res.errors[0].message); }
        self.emit('invalid', { errors: res.errors, source: opts.source || 'api' });
        return { ok: false, errors: res.errors };
      }
      if (canon.cal && canon.cal !== self.cal && self.cfg.cal_allowed.indexOf(canon.cal) !== -1) { self.cal = canon.cal; }
      self.commit(res.raw.row, opts);
      return { ok: true, value: self.canonicalValue() };
    });
  };
  Field.prototype.commit = function (row, opts) {
    opts = opts || {};
    var previous = this.canonicalValue();
    this.row = row;
    this.value = row.value || '';
    if (this.cfg.date_mode === 'multiple') { this.multi = (this.cfg.store_format === 'legacy_local' ? row.iso : row.value || '').split(',').filter(Boolean); }
    this.time = row.time || '';
    if (this.input) { this.input.value = this.value; this.input.setAttribute('data-cal', row.cal || this.cal); }
    this.clearError();
    this.render();
    if (!opts.silent) { this.fire(previous, opts.source || 'api'); }
  };
  Field.prototype.clear = function (opts) {
    opts = opts || {};
    var previous = this.canonicalValue();
    this.row = null; this.value = ''; this.multi = []; this.pending = null; this.time = '';
    if (this.input) { this.input.value = ''; }
    if (this.text) { this.text.value = ''; }
    if (this.text2) { this.text2.value = ''; }
    if (this.timeIn) { this.timeIn.value = ''; }
    this.render();
    if (!opts.silent && previous) { this.fire(previous, opts.source || 'api'); }
  };
  Field.prototype.fire = function (previous, source) {
    var detail = { fieldId: this.fieldId, formId: this.formId, value: this.canonicalValue(), previous: previous, source: source };
    this.emit('change', detail);
    if (this.input) {
      try { this.input.dispatchEvent(new CustomEvent('efb:date-change', { bubbles: true, detail: detail })); } catch (e) { /* old browser */ }
      try { this.input.dispatchEvent(new Event('change', { bubbles: true })); } catch (e2) { /* old browser */ }
    }
  };
  // #endregion

  // #region events
  Field.prototype.on = function (ev, fn) { var self = this; (this.handlers[ev] = this.handlers[ev] || []).push(fn); return function () { self.off(ev, fn); }; };
  Field.prototype.off = function (ev, fn) { if (this.handlers[ev]) { this.handlers[ev] = this.handlers[ev].filter(function (h) { return h !== fn; }); } };
  Field.prototype.emit = function (ev, detail) { var h = (this.handlers[ev] || []).slice(); for (var i = 0; i < h.length; i++) { try { h[i](detail); } catch (e) { if (root.console) { root.console.warn(e); } } } };
  // #endregion

  // #region constraints / availability API
  Field.prototype.getConstraints = function () { return this.constraints(); };
  Field.prototype.setConstraints = function (partial, opts) {
    if (!partial || typeof partial !== 'object') { return; }
    this.extra = opts && opts.replace ? partial : Object.assign({}, this.extra || {}, partial);
    this.render();
  };
  Field.prototype.isDateAllowed = function (iso) {
    var c = this.effective(), r = R.resolve(c, this.ctx());
    var e = R.refuse(c, r, iso, this.ctx());
    var a = this.avail ? this.availOf(iso) : null;
    return { allowed: !e, code: e ? e.code : undefined, capacity: a && a.capacity !== undefined ? a.capacity : null, remaining: a && a.remaining !== undefined ? a.remaining : null };
  };
  Field.prototype.setAvailability = function (src) {
    this.avail = src || null;
    this.availCache = {};
    if (this.isOpen) { this.renderGrid(); }
  };
  Field.prototype.availOf = function (iso) {
    var s = this.avail;
    if (!s) { return null; }
    if (typeof s === 'object' && !s.url && typeof s !== 'function') {
      var v = s[iso];
      if (v === undefined) { return null; }
      return typeof v === 'number' ? { available: v > 0, remaining: v, code: v > 0 ? undefined : 'full' } : v;
    }
    var c = this.availCache[iso];
    return c && Date.now() - c.at < 60000 ? c.s : null;
  };
  /** Loads availability for the months a value touches (a function source or {url}); resolves after the fetch. */
  Field.prototype.loadAvailabilityFor = function (value) {
    if (!this.avail || typeof this.avail !== 'object' && typeof this.avail !== 'function') { return Promise.resolve(); }
    if (!this.avail.url && typeof this.avail !== 'function') { return Promise.resolve(); }
    var dates = String(value || '').split(/[\/,]/).map(function (x) { return x.split('T')[0]; }).filter(U.isoValid);
    if (!dates.length) { return Promise.resolve(); }
    dates.sort();
    return this.loadAvailability(dates[0], dates[dates.length - 1]);
  };
  Field.prototype.loadAvailability = function (from, to) {
    var self = this, s = this.avail;
    if (!s) { return Promise.resolve(); }
    var missing = false;
    for (var j = U.isoToJdn(from); j <= U.isoToJdn(to); j++) { var c = this.availCache[U.jdnToIso(j)]; if (!c || Date.now() - c.at >= 60000) { missing = true; break; } }
    if (!missing) { return Promise.resolve(); }
    this.emit('availability', { firstIso: from, lastIso: to, loaded: false });
    var grid = this.dialog ? this.dialog.querySelector('[role=grid]') : null;
    if (grid) { grid.setAttribute('aria-busy', 'true'); }
    var done = function () { if (grid) { grid.removeAttribute('aria-busy'); } self.emit('availability', { firstIso: from, lastIso: to, loaded: true }); };
    var put = function (iso, st) { self.availCache[iso] = { at: Date.now(), s: st }; };
    if (typeof s === 'function') {
      var jobs = [];
      for (var k = U.isoToJdn(from); k <= U.isoToJdn(to); k++) {
        (function (iso) { jobs.push(Promise.resolve(s(iso)).then(function (st) { put(iso, st || null); }, function () { put(iso, null); })); })(U.jdnToIso(k));
      }
      return Promise.all(jobs).then(done, done);
    }
    var url = s.url + (s.url.indexOf('?') === -1 ? '?' : '&') + 'from=' + from + '&to=' + to;
    if (this.pendingFetch && this.pendingFetch.url === url) { return this.pendingFetch.p; }
    var p = fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      var dates = d && d.dates ? d.dates : {};
      for (var q = U.isoToJdn(from); q <= U.isoToJdn(to); q++) {
        var iso = U.jdnToIso(q), x = dates[iso];
        put(iso, !x || x.s === 'open' ? { available: true, remaining: x && x.r !== undefined ? x.r : null } : { available: false, code: x.s === 'full' ? 'full' : 'closed', remaining: x.r !== undefined ? x.r : null });
      }
      done();
    }, done);
    this.pendingFetch = { url: url, p: p };
    return p;
  };
  // #endregion

  // #region calendar switch, navigation
  Field.prototype.getCalendar = function () { return this.cal; };
  Field.prototype.setCalendar = function (id) {
    if (!this.cfg.cal_switch || this.cfg.cal_allowed.indexOf(id) === -1) { return false; }
    if (id === this.cal) { return true; }
    var from = this.cal;
    this.cal = id;
    try { if (root.localStorage) { root.localStorage.setItem('efb_cal_pref_' + this.formId + '_' + this.fieldId, id); } } catch (e) { /* private mode */ }
    if (this.value) {
      var r = this.check(this.cfg.store_format === 'legacy_local' ? this.row.iso : this.value, id);
      if (r.ok) { this.row = r.row; this.value = r.row.value; if (this.input) { this.input.value = this.value; this.input.setAttribute('data-cal', id); } }
    }
    this.view = null;
    this.render();
    this.emit('calendarchange', { from: from, to: id });
    if (this.isOpen) { this.renderDialog(); this.emitMonth(); }
    return true;
  };
  Field.prototype.goTo = function (s) {
    s = U.normalize(s);
    var iso = /^\d{4}-\d{2}$/.test(s) ? s + '-01' : s;
    if (!U.isoValid(iso)) { return; }
    var l = U.isoToLocal(iso, this.cal, this.cfg.cal_hijri_adjust);
    this.view = { y: l.y, m: l.m };
    this.focusIso = iso;
    if (this.isOpen) { this.renderGrid(); this.emitMonth(); }
  };
  Field.prototype.emitMonth = function () {
    var v = this.viewMonth(), e = C.engines.get(this.cal);
    var first = U.localToIso({ y: v.y, m: v.m, d: 1 }, this.cal, this.cfg.cal_hijri_adjust);
    var last = U.localToIso({ y: v.y, m: v.m, d: e.daysInMonth(v.y, v.m) }, this.cal, this.cfg.cal_hijri_adjust);
    if (this.cfg.date_mode === 'range') { var n = this.shiftMonth(v, 1); last = U.localToIso({ y: n.y, m: n.m, d: e.daysInMonth(n.y, n.m) }, this.cal, this.cfg.cal_hijri_adjust); }
    this.emit('monthchange', { calendar: this.cal, year: v.y, month: v.m, firstIso: first, lastIso: last });
    if (this.avail) { var self = this; this.loadAvailability(first, last).then(function () { if (self.isOpen) { self.renderGrid(true); } }); }
  };
  Field.prototype.viewMonth = function () {
    if (!this.view) {
      var base = this.focusIso || (this.value ? R.firstDate(this.cfg.store_format === 'legacy_local' ? this.row.iso : this.value) : null) || this.todayIso();
      var r = this.constraints();
      if (!this.value && r.min && base < r.min) { base = r.min; }
      if (!this.value && r.max && base > r.max) { base = r.max; }
      var l = U.isoToLocal(base, this.cal, this.cfg.cal_hijri_adjust);
      this.view = { y: l.y, m: l.m };
      this.focusIso = this.focusIso || base;
    }
    return this.view;
  };
  Field.prototype.shiftMonth = function (v, n) { var t = v.y * 12 + (v.m - 1) + n; return { y: Math.floor(t / 12), m: (t % 12 + 12) % 12 + 1 }; };
  Field.prototype.todayIso = function () { return R.resolve(this.cfg, this.ctx()).today; };
  // #endregion

  // #region typed input
  Field.prototype.pattern = function () { return R.typedPatternOf(this.cfg, this.cal); };
  Field.prototype.fmt = function (iso, pattern) {
    var l = U.isoToLocal(iso, this.cal, this.cfg.cal_hijri_adjust);
    return C.engines.get(this.cal).format({ y: l.y, m: l.m, d: l.d }, pattern || this.pattern(), this.cfg.locale, { digits: this.cfg.digits });
  };
  Field.prototype.parseTyped = function (text) {
    var p = C.engines.get(this.cal).parse(text, this.pattern());
    return p ? U.localToIso(p, this.cal, this.cfg.cal_hijri_adjust) : null;
  };
  /** Mask: digits only, the pattern's separators inserted, local digits shown (F06 §5.4). */
  Field.prototype.mask = function (inp, deleting) {
    var digits = U.normalize(inp.value).replace(/[^0-9]/g, '');
    var parts = U.tokenize(this.pattern()), out = '', i = 0;
    for (var p = 0; p < parts.length && i < digits.length; p++) {
      var t = parts[p];
      if (t.lit !== undefined) { out += t.lit; continue; }
      var w = t.tok === 'YYYY' ? 4 : 2;
      var chunk = digits.slice(i, i + w);
      out += chunk; i += chunk.length;
      if (chunk.length < w) { break; }
      var next = parts[p + 1];
      if (next && next.lit !== undefined && i < digits.length) { out += next.lit; p++; }
      else if (next && next.lit !== undefined && !deleting && p + 2 < parts.length) { out += next.lit; p++; }
    }
    var shown = U.toDigits(out, this.cfg.digits);
    if (shown !== inp.value) { inp.value = shown; try { inp.setSelectionRange(shown.length, shown.length); } catch (e) { /* not focused */ } }
  };
  Field.prototype.typedCommit = function () {
    var self = this, mode = this.cfg.date_mode;
    var a = this.text ? this.text.value : '';
    if (mode === 'multiple') { return; }
    if (!U.normalize(a) && (!this.text2 || !U.normalize(this.text2.value))) {
      if (this.value) { this.clear({ source: 'typed' }); }
      if (this.cfg.required) { this.showError(R.message({ code: 'required' }, this.cfg, this.cal, msgLang())); } else { this.clearError(); }
      return;
    }
    var isoA = this.parseTyped(a);
    if (isoA === null) { this.invalidTyped(); return; }
    var value = isoA;
    if (mode === 'range') {
      var isoB = this.text2 ? this.parseTyped(this.text2.value) : null;
      if (isoB === null) { if (this.text2 && U.normalize(this.text2.value)) { this.invalidTyped(); } return; }
      value = isoA + '/' + isoB;
    } else if (this.cfg.time_enabled) {
      var tm = this.timeIn ? U.normalize(this.timeIn.value) : '';
      if (/^\d{1,2}:\d{2}$/.test(tm)) { tm = U.pad(+tm.split(':')[0]) + ':' + tm.split(':')[1]; }
      if (tm) { value += 'T' + tm; }
    }
    this.setValue(value, { source: 'typed' }).then(function (r) {
      if (!r.ok) {
        var prev = self.canonicalValue();
        self.row = null; self.value = '';
        if (self.input) { self.input.value = ''; }
        if (prev) { self.fire(prev, 'typed'); }
      }
    });
  };
  Field.prototype.invalidTyped = function () {
    var prev = this.canonicalValue();
    this.row = null; this.value = '';
    if (this.input) { this.input.value = ''; }
    this.showError(R.message({ code: 'invalid_format' }, this.cfg, this.cal, msgLang()));
    if (prev) { this.fire(prev, 'typed'); }
    this.emit('invalid', { errors: [{ code: 'invalid_format' }], source: 'typed' });
  };
  Field.prototype.showError = function (text) {
    var t = this.text || this.btn;
    if (t) {
      t.setAttribute('aria-invalid', 'true');
      t.classList.add('border-danger');
      t.classList.remove('border-success');
    }
    if (this.msgEl) {
      this.msgEl.textContent = text;
      this.msgEl.setAttribute('dir', 'auto');
      // A theme's `direction: rtl` outranks dir=auto; plaintext keeps an English sentence in its order on an RTL page.
      this.msgEl.style.unicodeBidi = 'plaintext';
      this.msgEl.setAttribute('role', 'alert');
      this.msgEl.style.display = 'block';
      if (t) { var ids = (t.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean); if (ids.indexOf(this.msgEl.id) === -1) { ids.push(this.msgEl.id); t.setAttribute('aria-describedby', ids.join(' ')); } }
    }
  };
  Field.prototype.clearError = function () {
    var t = this.text || this.btn;
    if (t) { t.removeAttribute('aria-invalid'); t.classList.remove('border-danger'); }
    if (this.msgEl && this.msgEl.getAttribute('role') === 'alert') { this.msgEl.textContent = ''; this.msgEl.removeAttribute('role'); this.msgEl.style.display = 'none'; }
  };
  // #endregion

  // #region render of the field (typed boxes, chips, the display line)
  Field.prototype.render = function () {
    var mode = this.cfg.date_mode, self = this;
    var iso = this.row ? (this.cfg.store_format === 'legacy_local' ? this.row.iso : this.row.value) : '';
    var hint = R.formatHint(this.cfg, this.cal);
    if (this.text) {
      this.text.placeholder = hint;
      if (mode === 'single') { this.text.value = iso ? this.fmt(iso.split('T')[0]) : (doc.activeElement === this.text ? this.text.value : ''); }
      if (mode === 'range') {
        var ab = iso ? iso.split('/') : [];
        this.text.value = ab[0] ? this.fmt(ab[0]) : (doc.activeElement === this.text ? this.text.value : '');
        if (this.text2) { this.text2.placeholder = hint; this.text2.value = ab[1] ? this.fmt(ab[1]) : (doc.activeElement === this.text2 ? this.text2.value : ''); }
      }
      if (mode === 'multiple' && doc.activeElement !== this.text) { this.text.value = ''; }
      if (this.value) { this.text.classList.remove('border-danger'); }
    }
    if (this.timeIn) { this.timeIn.value = this.time ? U.toDigits(this.time, this.cfg.digits) : (doc.activeElement === this.timeIn ? this.timeIn.value : ''); }
    var hintEl = this.wrap.querySelector('.efb-cal-hint');
    if (hintEl) { hintEl.textContent = tx('cal_format_hint').replace('%s', FSI + hint + PDI); }
    if (this.btn) { this.btn.setAttribute('aria-label', tx(mode === 'range' ? 'cal_choose_range' : 'cal_choose_date') + (this.row ? ', ' + this.row.disp : '')); }
    if (this.dispEl) {
      this.dispEl.textContent = '';
      if (this.row && this.row.disp) {
        var b = el('bdi', { text: this.row.disp });
        this.dispEl.appendChild(b);
        var sec = this.secondaryText(iso);
        if (sec) { this.dispEl.appendChild(doc.createTextNode(' · ')); this.dispEl.appendChild(el('bdi', { text: sec })); }
        if (this.row.approx) { this.dispEl.appendChild(el('span', { cls: 'efb-cal-approx', text: ' ' + tx('cal_approx_note') })); }
        if (this.row.time && this.cfg.tz === 'visitor' && this.row.tz) { this.dispEl.appendChild(el('span', { cls: 'efb-cal-tz', text: ' (' + tx('cal_tz_note').replace('%s', this.row.tz) + ')' })); }
      }
    }
    if (this.chipsEl) {
      this.chipsEl.textContent = '';
      if (mode === 'multiple') {
        this.multi.forEach(function (d) {
          var txt = R.message ? self.medium(d) : d;
          var chip = el('span', { cls: 'efb efb-cal-chip' }, [el('bdi', { text: txt })]);
          var rm = el('button', { type: 'button', cls: 'efb efb-cal-chip-x', 'aria-label': tx('cal_remove_date').replace('%s', txt), text: '×' });
          rm.addEventListener('click', function () { self.toggleMulti(d, 'picker'); });
          chip.appendChild(rm);
          self.chipsEl.appendChild(chip);
        });
      }
    }
  };
  Field.prototype.medium = function (iso) {
    var l = U.isoToLocal(iso, this.cal, this.cfg.cal_hijri_adjust);
    return C.engines.get(this.cal).format({ y: l.y, m: l.m, d: l.d }, U.mediumPattern(this.cfg.locale), this.cfg.locale, { digits: this.cfg.digits });
  };
  Field.prototype.secondaryText = function (iso) {
    var sc = this.cfg.cal_secondary;
    if (!sc || sc === this.cal || !iso) { return ''; }
    var d = R.firstDate(iso);
    if (!d) { return ''; }
    if (C.engines.get(sc)) {
      var l = U.isoToLocal(d, sc, this.cfg.cal_hijri_adjust);
      return C.engines.get(sc).format({ y: l.y, m: l.m, d: l.d }, U.mediumPattern(this.cfg.locale), this.cfg.locale, { digits: this.cfg.digits });
    }
    return C.label ? C.label(d, sc, this.cfg.locale) : '';
  };
  // #endregion

  // #region binding
  Field.prototype.bind = function () {
    var self = this, mode = this.cfg.date_mode;
    if (this.cfg.input_mode === 'typed' && this.btn) { this.btn.hidden = true; }
    var typedOff = this.cfg.input_mode === 'picker';
    [this.text, this.text2].forEach(function (inp) {
      if (!inp) { return; }
      inp.setAttribute('inputmode', 'numeric');
      inp.setAttribute('autocomplete', 'off');
      if (typedOff) { inp.readOnly = true; }
      inp.addEventListener('input', function (e) { if (mode !== 'multiple' || true) { self.mask(inp, e && e.inputType && e.inputType.indexOf('delete') === 0); } });
      inp.addEventListener('change', function (e) { e.stopPropagation(); if (mode === 'multiple') { self.addTypedMulti(); } else { self.typedCommit(); } });
      inp.addEventListener('keydown', function (e) {
        if ((e.key === 'ArrowDown' && (e.altKey || typedOff)) || (typedOff && (e.key === 'Enter' || e.key === ' '))) { e.preventDefault(); self.open(); }
        else if (e.key === 'Enter' && mode === 'multiple') { e.preventDefault(); self.addTypedMulti(); }
      });
      if (typedOff) { inp.addEventListener('click', function () { self.open(); }); }
    });
    if (this.timeIn) {
      this.timeIn.setAttribute('inputmode', 'numeric');
      this.timeIn.addEventListener('change', function (e) { e.stopPropagation(); self.typedCommit(); });
    }
    if (this.btn) {
      this.btn.setAttribute('aria-haspopup', 'dialog');
      this.btn.setAttribute('aria-expanded', 'false');
      this.btn.addEventListener('click', function () { if (self.isOpen) { self.close(true); } else { self.open(); } });
    }
  };
  Field.prototype.addTypedMulti = function () {
    var iso = this.parseTyped(this.text.value);
    if (iso === null) { if (U.normalize(this.text.value)) { this.showError(R.message({ code: 'invalid_format' }, this.cfg, this.cal, msgLang())); } return; }
    this.text.value = '';
    if (this.multi.indexOf(iso) === -1) { this.toggleMulti(iso, 'typed'); }
  };
  Field.prototype.toggleMulti = function (iso, source) {
    var list = this.multi.slice(), i = list.indexOf(iso);
    if (i === -1) { list.push(iso); } else { list.splice(i, 1); }
    list.sort();
    var self = this;
    if (!list.length) { this.clear({ source: source }); if (this.isOpen) { this.renderGrid(true); } return Promise.resolve(); }
    return this.setValue(list.join(','), { source: source }).then(function (r) { if (self.isOpen) { self.renderGrid(true); if (!r.ok) { self.live(r.errors[0].message); } } });
  };
  // #endregion

  // #region dialog
  Field.prototype.mobile = function () { try { return root.matchMedia('(max-width: 575.98px)').matches; } catch (e) { return false; } };
  Field.prototype.open = function () {
    if (this.isOpen || (this.text && this.text.disabled) || this.cfg.input_mode === 'typed') { return; }
    var self = this;
    this.isOpen = true;
    this.rtl = isRtl(this.wrap);
    this.view = null;
    this.focusIso = null;
    this.mode = 'days';
    if (!this.dialog) { this.buildDialog(); }
    this.dialog.hidden = false;
    this.dialog.classList.toggle('efb-cal-sheet', this.mobile());
    if (this.backdrop) { this.backdrop.hidden = !this.mobile(); }
    if (this.btn) { this.btn.setAttribute('aria-expanded', 'true'); }
    this.renderDialog();
    this.emit('open', {});
    this.emitMonth();
    var f = this.dialog.querySelector('[role=gridcell][tabindex="0"]');
    if (f) { f.focus(); }
    this.outside = function (e) { if (self.isOpen && !self.wrap.contains(e.target) && !(self.backdrop && e.target === self.backdrop && false)) { self.close(false); } };
    setTimeout(function () { doc.addEventListener('mousedown', self.outside, true); doc.addEventListener('touchstart', self.outside, true); }, 0);
  };
  Field.prototype.close = function (focusBack) {
    if (!this.isOpen) { return; }
    this.isOpen = false;
    this.pending = null;
    if (this.dialog) { this.dialog.hidden = true; }
    if (this.backdrop) { this.backdrop.hidden = true; }
    if (this.btn) { this.btn.setAttribute('aria-expanded', 'false'); }
    doc.removeEventListener('mousedown', this.outside, true);
    doc.removeEventListener('touchstart', this.outside, true);
    if (focusBack !== false) { (this.btn || this.text).focus(); }
    this.emit('close', {});
  };
  Field.prototype.buildDialog = function () {
    var self = this;
    var id = (this.text ? this.text.id : this.fieldId) + '-dlg';
    this.backdrop = el('div', { cls: 'efb efb-cal-backdrop', hidden: true });
    this.backdrop.addEventListener('click', function () { self.close(true); });
    this.dialog = el('div', { cls: 'efb efb-cal-dialog', role: 'dialog', 'aria-modal': 'true', id: id, hidden: true });
    this.liveEl = el('div', { cls: 'efb-cal-sr', 'aria-live': 'polite' });
    this.dialog.addEventListener('keydown', function (e) { self.dialogKey(e); });
    var sx = null;
    this.dialog.addEventListener('touchstart', function (e) { if (e.touches && e.touches[0]) { sx = e.touches[0].clientX; } }, { passive: true });
    this.dialog.addEventListener('touchend', function (e) {
      if (sx === null || !e.changedTouches || !e.changedTouches[0] || self.mode !== 'days') { return; }
      var dx = e.changedTouches[0].clientX - sx; sx = null;
      if (Math.abs(dx) < 50) { return; }
      var next = dx < 0;
      if (self.rtl) { next = !next; }
      self.moveMonth(next ? 1 : -1, true);
    });
    this.wrap.appendChild(this.backdrop);
    this.wrap.appendChild(this.dialog);
  };
  Field.prototype.live = function (text) { if (this.liveEl) { this.liveEl.textContent = ''; this.liveEl.textContent = text; } };
  Field.prototype.monthTitle = function (v) {
    var e = C.engines.get(this.cal);
    return e.format({ y: v.y, m: v.m, d: 1 }, 'MMMM YYYY', this.cfg.locale, { digits: this.cfg.digits });
  };
  Field.prototype.renderDialog = function () {
    var self = this, d = this.dialog, v = this.viewMonth();
    d.textContent = '';
    d.setAttribute('dir', this.rtl ? 'rtl' : 'ltr');
    d.setAttribute('aria-label', tx('cal_dialog').replace('%s', this.monthTitle(v)));
    if (this.cfg.cal_switch) {
      var rg = el('div', { cls: 'efb efb-cal-switch', role: 'radiogroup', 'aria-label': tx('cal_switch_label') });
      this.cfg.cal_allowed.forEach(function (c) {
        var on = c === self.cal;
        var b = el('button', { type: 'button', role: 'radio', 'aria-checked': on ? 'true' : 'false', tabindex: on ? '0' : '-1', cls: 'efb efb-cal-seg' + (on ? ' efb-on' : ''), 'data-cal': c, text: U.calendarLabel(c, self.cfg.locale) });
        b.addEventListener('click', function () { self.setCalendar(c); var x = self.dialog.querySelector('[role=radio][data-cal="' + c + '"]'); if (x) { x.focus(); } });
        b.addEventListener('keydown', function (e) {
          var k = e.key, list = self.cfg.cal_allowed, i = list.indexOf(c), n = null;
          if (k === 'ArrowRight' || k === 'ArrowDown') { n = list[(i + (self.rtl && k === 'ArrowRight' ? -1 : 1) + list.length) % list.length]; }
          if (k === 'ArrowLeft' || k === 'ArrowUp') { n = list[(i + (self.rtl && k === 'ArrowLeft' ? 1 : -1) + list.length) % list.length]; }
          if (n) { e.preventDefault(); e.stopPropagation(); self.setCalendar(n); var x = self.dialog.querySelector('[role=radio][data-cal="' + n + '"]'); if (x) { x.focus(); } }
        });
        rg.appendChild(b);
      });
      d.appendChild(rg);
    }
    var head = el('div', { cls: 'efb efb-cal-head' });
    var prev = el('button', { type: 'button', cls: 'efb efb-cal-nav efb-cal-prev', 'aria-label': tx('cal_prev_month') }, [el('span', { 'aria-hidden': 'true', text: this.rtl ? '›' : '‹' })]);
    var next = el('button', { type: 'button', cls: 'efb efb-cal-nav efb-cal-next', 'aria-label': tx('cal_next_month') }, [el('span', { 'aria-hidden': 'true', text: this.rtl ? '‹' : '›' })]);
    var title = el('button', { type: 'button', cls: 'efb efb-cal-title', 'aria-label': tx('cal_month_year_view'), 'aria-describedby': id2(this, 'ttl') }, [el('span', { id: id2(this, 'ttl'), text: this.monthTitle(v) })]);
    var today = el('button', { type: 'button', cls: 'efb efb-cal-today', text: tx('cal_today') });
    prev.addEventListener('click', function () { self.moveMonth(-1, false); });
    next.addEventListener('click', function () { self.moveMonth(1, false); });
    title.addEventListener('click', function () { self.mode = self.mode === 'days' ? 'months' : 'days'; self.renderDialog(); var f = self.dialog.querySelector(self.mode === 'days' ? '[role=gridcell][tabindex="0"]' : '.efb-cal-year'); if (f) { f.focus(); } });
    today.addEventListener('click', function () { self.focusIso = self.todayIso(); self.view = null; self.mode = 'days'; self.renderDialog(); self.emitMonth(); self.focusCell(); });
    head.appendChild(prev); head.appendChild(title); head.appendChild(next); head.appendChild(today);
    d.appendChild(head);
    this.prevBtn = prev; this.nextBtn = next; this.titleEl = title.firstChild;
    var body = el('div', { cls: 'efb efb-cal-body' });
    d.appendChild(body);
    this.bodyEl = body;
    if (this.mode === 'months') { this.renderMonths(); } else { this.renderGrid(); }
    if (this.cfg.time_enabled && this.cfg.date_mode === 'single') { d.appendChild(this.timeList()); }
    var foot = el('div', { cls: 'efb efb-cal-foot' });
    var clr = el('button', { type: 'button', cls: 'efb efb-cal-clear', text: tx('cal_clear') });
    var ok = el('button', { type: 'button', cls: 'efb efb-cal-ok', text: tx('cal_ok') });
    clr.addEventListener('click', function () { self.clear({ source: 'picker' }); self.renderGrid(true); });
    ok.addEventListener('click', function () { self.close(true); });
    foot.appendChild(clr); foot.appendChild(ok);
    d.appendChild(foot);
    d.appendChild(this.liveEl);
  };
  function id2(f, s) { return (f.text ? f.text.id : f.fieldId) + '-' + s; }
  Field.prototype.limitsLocal = function () {
    var r = this.constraints();
    return { r: r, min: r.min ? U.isoToLocal(r.min, this.cal, this.cfg.cal_hijri_adjust) : null, max: r.max ? U.isoToLocal(r.max, this.cal, this.cfg.cal_hijri_adjust) : null };
  };
  Field.prototype.moveMonth = function (n, keepFocusDay) {
    var v = this.shiftMonth(this.viewMonth(), n);
    var L = this.limitsLocal();
    if (L.min && v.y * 12 + v.m < L.min.y * 12 + L.min.m) { return; }
    if (L.max && v.y * 12 + v.m > L.max.y * 12 + L.max.m) { return; }
    this.view = v;
    var e = C.engines.get(this.cal), fl = U.isoToLocal(this.focusIso || this.todayIso(), this.cal, this.cfg.cal_hijri_adjust);
    this.focusIso = U.localToIso({ y: v.y, m: v.m, d: Math.min(fl.d, e.daysInMonth(v.y, v.m)) }, this.cal, this.cfg.cal_hijri_adjust);
    this.renderDialog();
    this.emitMonth();
    this.live(this.monthTitle(v));
    if (keepFocusDay) { this.focusCell(); }
  };
  Field.prototype.focusCell = function () { var f = this.dialog && this.dialog.querySelector('[role=gridcell][tabindex="0"]'); if (f) { f.focus(); } };
  Field.prototype.renderMonths = function () {
    var self = this, v = this.viewMonth(), e = C.engines.get(this.cal), L = this.limitsLocal();
    var body = this.bodyEl;
    var ylab = el('label', { cls: 'efb efb-cal-ylab', for: id2(this, 'year'), text: tx('cal_year') });
    var ysel = el('select', { cls: 'efb efb-cal-year', id: id2(this, 'year') });
    var tl = U.isoToLocal(this.todayIso(), this.cal, this.cfg.cal_hijri_adjust);
    var y0 = L.min ? L.min.y : tl.y - 120, y1 = L.max ? L.max.y : tl.y + 20;
    for (var y = y1; y >= y0; y--) { var o = el('option', { value: String(y), text: e.format({ y: y, m: 1, d: 1 }, 'YYYY', this.cfg.locale, { digits: this.cfg.digits }) }); if (y === v.y) { o.selected = true; } ysel.appendChild(o); }
    ysel.addEventListener('change', function () { self.view = { y: +ysel.value, m: v.m }; self.renderDialog(); var s = self.dialog.querySelector('.efb-cal-year'); if (s) { s.focus(); } });
    body.appendChild(el('div', { cls: 'efb efb-cal-yrow' }, [ylab, ysel]));
    var grid = el('div', { cls: 'efb efb-cal-months', role: 'group', 'aria-label': tx('cal_month_year_view') });
    var names = e.monthNames(this.cfg.locale, 'long');
    names.forEach(function (nm, i) {
      var m = i + 1, out = (L.min && v.y * 12 + m < L.min.y * 12 + L.min.m) || (L.max && v.y * 12 + m > L.max.y * 12 + L.max.m);
      var b = el('button', { type: 'button', cls: 'efb efb-cal-mon' + (m === v.m ? ' efb-on' : ''), 'aria-pressed': m === v.m ? 'true' : 'false', disabled: out ? true : null, text: nm });
      b.addEventListener('click', function () { self.view = { y: v.y, m: m }; var fl = U.isoToLocal(self.focusIso || self.todayIso(), self.cal, self.cfg.cal_hijri_adjust); self.focusIso = U.localToIso({ y: v.y, m: m, d: Math.min(fl.d, e.daysInMonth(v.y, m)) }, self.cal, self.cfg.cal_hijri_adjust); self.mode = 'days'; self.renderDialog(); self.emitMonth(); self.focusCell(); });
      grid.appendChild(b);
    });
    body.appendChild(grid);
  };
  Field.prototype.weekStart = function () {
    if (this.cfg.week_start) { return this.cfg.week_start; }
    try {
      var loc = new Intl.Locale(U.bcp(this.cfg.locale));
      var wi = loc.getWeekInfo ? loc.getWeekInfo() : loc.weekInfo;
      if (wi && wi.firstDay) { return wi.firstDay; }
    } catch (e) { /* fallback */ }
    var l = U.lang(this.cfg.locale), reg = (String(this.cfg.locale).split(/[_-]/)[1] || '').toUpperCase();
    if (l === 'fa' || l === 'ps' || (l === 'ar' && ['SA', 'YE', 'MA', 'TN', 'LB', 'AE'].indexOf(reg) === -1)) { return 6; }
    if ((l === 'en' && (reg === 'US' || reg === '')) || l === 'he' || l === 'ja' || (l === 'ar' && (reg === 'SA' || reg === 'YE'))) { return 7; }
    return 1;
  };
  Field.prototype.weekend = function () {
    try { var loc = new Intl.Locale(U.bcp(this.cfg.locale)); var wi = loc.getWeekInfo ? loc.getWeekInfo() : loc.weekInfo; if (wi && wi.weekend) { return wi.weekend; } } catch (e) { /* fallback */ }
    return U.lang(this.cfg.locale) === 'fa' ? [5] : [6, 7];
  };
  Field.prototype.renderGrid = function (keepFocus) {
    if (!this.dialog || this.mode !== 'days') { return; }
    var hadFocus = keepFocus && this.dialog.contains(doc.activeElement) && doc.activeElement.getAttribute('role') === 'gridcell';
    var body = this.bodyEl;
    body.textContent = '';
    var v = this.viewMonth();
    var months = this.cfg.date_mode === 'range' ? [v, this.shiftMonth(v, 1)] : [v];
    var wrap = el('div', { cls: 'efb efb-cal-grids' + (months.length > 1 ? ' efb-two' : '') });
    var self = this;
    var c = this.effective(), r = R.resolve(c, this.ctx());
    var hol = R.listHolidays(c, r, this.ctx(), U.localToIso({ y: v.y, m: v.m, d: 1 }, this.cal, c.cal_hijri_adjust), U.addDays(U.localToIso({ y: v.y, m: v.m, d: 1 }, this.cal, c.cal_hijri_adjust), 70));
    months.forEach(function (mv, idx) { wrap.appendChild(self.monthGrid(mv, idx, c, r, hol)); });
    body.appendChild(wrap);
    if (this.titleEl) { this.titleEl.textContent = this.monthTitle(v); }
    var L = this.limitsLocal(), pm = this.shiftMonth(v, -1), nm = this.shiftMonth(v, 1);
    if (this.prevBtn) { this.prevBtn.disabled = !!(L.min && pm.y * 12 + pm.m < L.min.y * 12 + L.min.m); }
    if (this.nextBtn) { this.nextBtn.disabled = !!(L.max && nm.y * 12 + nm.m > L.max.y * 12 + L.max.m); }
    if (hadFocus) { this.focusCell(); }
  };
  Field.prototype.selectedSet = function () {
    var s = {}, iso = this.row ? (this.cfg.store_format === 'legacy_local' ? this.row.iso : this.row.value) : '';
    if (!iso) { return s; }
    if (this.cfg.date_mode === 'range') {
      var ab = iso.split('/');
      for (var j = U.isoToJdn(ab[0]); j <= U.isoToJdn(ab[1]); j++) { s[U.jdnToIso(j)] = j === U.isoToJdn(ab[0]) ? 'start' : (j === U.isoToJdn(ab[1]) ? 'end' : 'mid'); }
    } else { iso.split(',').forEach(function (d) { s[d.split('T')[0]] = 'one'; }); }
    return s;
  };
  Field.prototype.monthGrid = function (v, idx, c, r, hol) {
    var self = this, e = C.engines.get(this.cal), adj = c.cal_hijri_adjust;
    var ws = this.weekStart(), weekend = this.weekend();
    var names = U.weekdayNames(this.cfg.locale, 'short'), longs = U.weekdayNames(this.cfg.locale, 'long');
    if (names.some(function (n) { return n.length > 4; })) { names = U.weekdayNames(this.cfg.locale, 'narrow'); }
    var tbl = el('table', { role: 'grid', cls: 'efb efb-cal-grid', 'aria-labelledby': id2(this, 'cap' + idx) });
    tbl.appendChild(el('caption', { id: id2(this, 'cap' + idx), cls: idx === 0 && this.cfg.date_mode !== 'range' ? 'efb-cal-sr' : 'efb efb-cal-cap', text: this.monthTitle(v) }));
    var thead = el('thead'), hr = el('tr');
    for (var i = 0; i < 7; i++) { var wd = ((ws - 1 + i) % 7) + 1; hr.appendChild(el('th', { role: 'columnheader', scope: 'col', abbr: longs[wd - 1], text: names[wd - 1], cls: weekend.indexOf(wd) !== -1 ? 'efb-cal-we' : null })); }
    thead.appendChild(hr); tbl.appendChild(thead);
    var tb = el('tbody');
    var firstIso = U.localToIso({ y: v.y, m: v.m, d: 1 }, this.cal, adj);
    var dim = e.daysInMonth(v.y, v.m);
    var lead = (U.weekdayOfJdn(U.isoToJdn(firstIso)) - ws + 7) % 7;
    var sel = this.selectedSet(), today = r.today;
    var focus = this.focusIso && this.focusIso >= firstIso && this.focusIso <= U.addDays(firstIso, dim - 1) ? this.focusIso : null;
    if (idx === 0 && !focus) { focus = sel && Object.keys(sel).sort().filter(function (d) { return d >= firstIso && d <= U.addDays(firstIso, dim - 1); })[0] || (today >= firstIso && today <= U.addDays(firstIso, dim - 1) ? today : firstIso); this.focusIso = focus; }
    var pendingEndBlock = null;
    if (this.cfg.date_mode === 'range' && this.pending) {
      for (var q = 1; q < 400; q++) { var dq = U.addDays(this.pending, q); if (R.refuse(c, r, dq, this.ctx())) { pendingEndBlock = c.range_unit === 'nights' ? U.addDays(dq, 1) : dq; break; } }
    }
    var tr = el('tr'), cells = 0;
    for (var k = 0; k < lead; k++) { tr.appendChild(el('td', { role: 'gridcell', 'aria-hidden': 'true', cls: 'efb-cal-pad' })); cells++; }
    for (var day = 1; day <= dim; day++) {
      var iso = U.addDays(firstIso, day - 1), wdn = U.weekdayOfJdn(U.isoToJdn(iso));
      var refused = R.refuse(c, r, iso, this.ctx());
      if (this.cfg.date_mode === 'range' && this.pending && refused && c.range_unit === 'nights' && iso > this.pending) {
        // a closed day can be the check-out day of a stay (F06 §5.6)
        var prevRef = R.refuse(c, r, U.addDays(iso, -1), this.ctx());
        if (!prevRef && (!pendingEndBlock || iso < pendingEndBlock) && (!r.max || iso <= r.max)) { refused = null; }
      }
      if (this.cfg.date_mode === 'range' && this.pending && !refused && iso > this.pending) {
        var len = U.nights(this.pending, iso) + (c.range_unit === 'days' ? 1 : 0);
        if ((pendingEndBlock && iso >= pendingEndBlock) || (c.range_min_days && len < c.range_min_days) || (c.range_max_days && len > c.range_max_days)) { refused = { code: 'range_contains_disabled' }; }
      }
      if (this.cfg.date_mode === 'multiple' && c.multi_max && this.multi.length >= c.multi_max && this.multi.indexOf(iso) === -1 && !refused) { refused = { code: 'too_many_dates' }; }
      var a = this.availOf(iso);
      var holiday = hol[iso];
      var s = sel[iso];
      var label = e.format(U.isoToLocal(iso, this.cal, adj), 'dddd D MMMM YYYY', this.cfg.locale, { digits: this.cfg.digits });
      var notes = [];
      if (holiday) { notes.push(tx('cal_holiday') + ': ' + holiday); }
      if (a && a.available === false) { notes.push(tx(a.code === 'full' ? 'cal_full' : 'cal_closed')); }
      else if (refused) { notes.push(tx('cal_closed')); }
      if (a && a.available !== false && typeof a.remaining === 'number') { notes.push(tx('cal_remaining_n').replace('%s', U.toDigits(String(a.remaining), this.cfg.digits))); }
      var td = el('td', {
        role: 'gridcell', tabindex: iso === focus ? '0' : '-1', 'data-iso': iso,
        'aria-selected': s ? 'true' : 'false', 'aria-disabled': refused ? 'true' : null, 'aria-current': iso === today ? 'date' : null,
        'aria-label': label + (notes.length ? ', ' + notes.join(', ') : ''),
        title: notes.length ? notes.join(', ') : null,
        cls: 'efb efb-cal-day' + (refused ? ' efb-off' : '') + (s ? ' efb-sel efb-sel-' + s : '') + (iso === today ? ' efb-today' : '') + (holiday ? ' efb-hol' : '') + (weekend.indexOf(wdn) !== -1 ? ' efb-cal-we' : '')
      });
      td.appendChild(el('span', { cls: 'efb-cal-n', 'aria-hidden': 'true', text: U.toDigits(String(day), this.cfg.digits) }));
      var sec = this.cellSecondary(iso, day === 1);
      if (sec) { td.appendChild(el('span', { cls: 'efb-cal-s', 'aria-hidden': 'true', text: sec })); }
      if (holiday && c.holiday_mode === 'mark') { td.appendChild(el('span', { cls: 'efb-cal-dot', 'aria-hidden': 'true' })); }
      (function (dIso) {
        td.addEventListener('click', function () { self.pick(dIso); });
        td.addEventListener('mouseenter', function () { if (self.pending) { self.hoverRange(dIso); } });
      })(iso);
      tr.appendChild(td); cells++;
      if (cells % 7 === 0) { tb.appendChild(tr); tr = el('tr'); }
    }
    if (cells % 7 !== 0) { while (cells % 7 !== 0) { tr.appendChild(el('td', { role: 'gridcell', 'aria-hidden': 'true', cls: 'efb-cal-pad' })); cells++; } tb.appendChild(tr); }
    tbl.appendChild(tb);
    return tbl;
  };
  Field.prototype.cellSecondary = function (iso, first) {
    var sc = this.cfg.cal_secondary;
    if (!sc || sc === this.cal) { return ''; }
    if (C.engines.get(sc)) {
      var l = U.isoToLocal(iso, sc, this.cfg.cal_hijri_adjust), e = C.engines.get(sc);
      var showMonth = first || l.d === 1;
      return U.toDigits(String(l.d), this.cfg.digits) + (showMonth ? ' ' + e.monthNames(this.cfg.locale, 'short')[l.m - 1] : '');
    }
    return C.label ? C.label(iso, sc, this.cfg.locale, 'day') : '';
  };
  Field.prototype.hoverRange = function (iso) {
    var cells = this.dialog.querySelectorAll('[role=gridcell][data-iso]'), a = this.pending;
    for (var i = 0; i < cells.length; i++) { var d = cells[i].getAttribute('data-iso'); cells[i].classList.toggle('efb-hover', iso >= a && d >= a && d <= iso); }
  };
  Field.prototype.pick = function (iso) {
    var self = this, mode = this.cfg.date_mode;
    var cell = this.dialog.querySelector('[data-iso="' + iso + '"]');
    this.focusIso = iso;
    if (cell && cell.getAttribute('aria-disabled') === 'true') {
      var c = this.effective(), r = R.resolve(c, this.ctx()), e = R.refuse(c, r, iso, this.ctx());
      this.live(e ? R.message(e, this.cfg, this.cal, msgLang()) : tx('cal_closed'));
      return;
    }
    if (mode === 'multiple') { this.toggleMulti(iso, 'picker'); return; }
    if (mode === 'range') {
      if (!this.pending || iso < this.pending) {
        this.pending = iso;
        this.renderGrid(true);
        this.live(tx('cal_from') + ' ' + this.medium(iso));
        return;
      }
      var a = this.pending;
      this.pending = null;
      this.setValue(a + '/' + iso, { source: 'picker' }).then(function (res) {
        if (res.ok) {
          var n = U.nights(a, iso);
          self.live(tx('cal_range_sum').replace('%1$s', self.medium(a)).replace('%2$s', self.medium(iso)).replace('%3$s', tx(self.cfg.range_unit === 'nights' ? 'cal_nights_n' : 'cal_days_n').replace('%s', U.toDigits(String(self.cfg.range_unit === 'nights' ? n : n + 1), self.cfg.digits))));
          if (!self.mobile()) { self.close(true); } else { self.renderGrid(true); }
        } else { self.live(res.errors[0].message); self.renderGrid(true); }
      });
      return;
    }
    var value = iso;
    if (this.cfg.time_enabled) {
      this.pickedDay = iso;
      if (!this.time) { this.renderDialog(); var first = this.dialog.querySelector('[role=option]:not([aria-disabled=true])'); if (first) { first.focus(); } return; }
      value = iso + 'T' + this.time;
    }
    this.setValue(value, { source: 'picker' }).then(function (res) {
      if (res.ok) {
        if (!self.mobile() && !self.cfg.time_enabled) { self.close(true); } else { self.renderDialog(); self.focusCell(); }
      } else { self.live(res.errors[0].message); self.showError(res.errors[0].message); self.renderGrid(true); }
    });
  };
  Field.prototype.slots = function (iso) {
    var c = this.cfg, out = [];
    var wd = String(U.weekdayOfJdn(U.isoToJdn(iso)));
    var windows = c.time_by_weekday.hasOwnProperty(wd) ? c.time_by_weekday[wd] : [[c.time_min || '00:00', c.time_max || '23:59']];
    var toMin = function (s) { var p = s.split(':'); return +p[0] * 60 + +p[1]; };
    windows.forEach(function (w) { for (var t = toMin(w[0]); t <= toMin(w[1]) && out.length < 500; t += c.time_step) { out.push(U.pad(Math.floor(t / 60)) + ':' + U.pad(t % 60)); } });
    var a = this.availOf(iso);
    if (a && Array.isArray(a.slots)) { out = a.slots.filter(function (s) { return s.available !== false; }).map(function (s) { return s.start; }); }
    return out;
  };
  Field.prototype.timeList = function () {
    var self = this;
    var day = this.pickedDay || (this.value ? R.firstDate(this.cfg.store_format === 'legacy_local' ? this.row.iso : this.value) : null) || this.focusIso || this.todayIso();
    var box = el('div', { cls: 'efb efb-cal-timebox' });
    var lid = id2(this, 'tl');
    box.appendChild(el('div', { id: lid, cls: 'efb efb-cal-tlabel', text: tx('cal_time') }));
    var lb = el('div', { role: 'listbox', 'aria-labelledby': lid, cls: 'efb efb-cal-times', tabindex: '-1' });
    var e = C.engines.get(this.cal);
    var slots = this.slots(day);
    slots.forEach(function (s, i) {
      var r = self.check(day + 'T' + s);
      var on = s === self.time;
      var o = el('div', { role: 'option', id: id2(self, 't' + i), tabindex: on || (!self.time && i === 0) ? '0' : '-1', 'aria-selected': on ? 'true' : 'false', 'aria-disabled': r.ok ? null : 'true', cls: 'efb efb-cal-time-o' + (on ? ' efb-sel' : '') + (r.ok ? '' : ' efb-off'),
        text: e.format({ y: 2000, m: 1, d: 1 }, self.cfg.time_format === '12' ? 'hh:mm A' : 'HH:mm', self.cfg.locale, { digits: self.cfg.digits, time: s }), 'data-t': s });
      o.addEventListener('click', function () { self.pickTime(day, s); });
      o.addEventListener('keydown', function (ev) {
        var opts = lb.querySelectorAll('[role=option]'), idx = Array.prototype.indexOf.call(opts, o), n = null;
        if (ev.key === 'ArrowDown') { n = opts[Math.min(idx + 1, opts.length - 1)]; }
        if (ev.key === 'ArrowUp') { n = opts[Math.max(idx - 1, 0)]; }
        if (ev.key === 'Home') { n = opts[0]; }
        if (ev.key === 'End') { n = opts[opts.length - 1]; }
        if (n) { ev.preventDefault(); ev.stopPropagation(); o.setAttribute('tabindex', '-1'); n.setAttribute('tabindex', '0'); n.focus(); }
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); ev.stopPropagation(); self.pickTime(day, s); }
      });
      lb.appendChild(o);
    });
    box.appendChild(lb);
    return box;
  };
  Field.prototype.pickTime = function (day, t) {
    var self = this;
    this.setValue(day + 'T' + t, { source: 'picker' }).then(function (res) {
      if (res.ok) { self.pickedDay = null; self.close(true); } else { self.live(res.errors[0].message); }
    });
  };
  Field.prototype.dialogKey = function (e) {
    var t = e.target, k = e.key;
    if (k === 'Escape') { e.preventDefault(); this.close(true); return; }
    if (k === 'Tab') {
      var f = Array.prototype.filter.call(this.dialog.querySelectorAll('button:not([disabled]), select, [tabindex="0"]'), function (x) { return !x.hidden && x.offsetParent !== null; });
      if (!f.length) { return; }
      if (e.shiftKey && doc.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && doc.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
      return;
    }
    if (!t || t.getAttribute('role') !== 'gridcell' || !t.getAttribute('data-iso')) { return; }
    var iso = t.getAttribute('data-iso'), next = null, e2 = C.engines.get(this.cal), adj = this.cfg.cal_hijri_adjust;
    var loc = U.isoToLocal(iso, this.cal, adj);
    var monthShift = function (n) { var tt = loc.y * 12 + (loc.m - 1) + n, y = Math.floor(tt / 12), m = (tt % 12 + 12) % 12 + 1; return U.localToIso({ y: y, m: m, d: Math.min(loc.d, e2.daysInMonth(y, m)) }, this.cal, adj); }.bind(this);
    var ws = this.weekStart(), wd = U.weekdayOfJdn(U.isoToJdn(iso)), off = (wd - ws + 7) % 7;
    switch (k) {
      case 'ArrowLeft': next = U.addDays(iso, this.rtl ? 1 : -1); break;
      case 'ArrowRight': next = U.addDays(iso, this.rtl ? -1 : 1); break;
      case 'ArrowUp': next = U.addDays(iso, -7); break;
      case 'ArrowDown': next = U.addDays(iso, 7); break;
      case 'Home': next = U.addDays(iso, -off); break;
      case 'End': next = U.addDays(iso, 6 - off); break;
      case 'PageUp': next = monthShift(e.shiftKey ? -12 : -1); break;
      case 'PageDown': next = monthShift(e.shiftKey ? 12 : 1); break;
      case 't': case 'T': next = this.todayIso(); break;
      case 'Enter': case ' ': e.preventDefault(); this.pick(iso); return;
      default: return;
    }
    e.preventDefault();
    this.focusIso = next;
    var cell = this.dialog.querySelector('[role=gridcell][data-iso="' + next + '"]');
    if (cell) {
      var old = this.dialog.querySelectorAll('[role=gridcell][tabindex="0"]');
      for (var i = 0; i < old.length; i++) { old[i].setAttribute('tabindex', '-1'); }
      cell.setAttribute('tabindex', '0');
      cell.focus();
      if (this.pending) { this.hoverRange(next); }
      return;
    }
    var nl = U.isoToLocal(next, this.cal, adj);
    this.view = { y: nl.y, m: nl.m };
    this.renderDialog();
    this.emitMonth();
    this.live(this.monthTitle(this.view));
    this.focusCell();
  };
  Field.prototype.destroy = function () {
    this.close(false);
    if (this.dialog && this.dialog.parentNode) { this.dialog.parentNode.removeChild(this.dialog); }
    if (this.backdrop && this.backdrop.parentNode) { this.backdrop.parentNode.removeChild(this.backdrop); }
    delete fields[key(this.fieldId, this.formId)];
    this.wrap.removeAttribute('data-efb-cal-on');
    this.handlers = {};
  };
  // #endregion


  var ICON = '<svg class="efb-cal-ico" width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/><path d="M5 7h2v2H5zm4 0h2v2H9zm-4 3h2v2H5zm4 0h2v2H9z"/></svg>';
  C.icon = ICON;
  function escAttr(v) { return String(v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
  /**
   * The field's markup (twin of the PHP renderer in class-Emsfb-formbuilder.php, used by the builder
   * canvas and preview): o = {id, formId, config (object), mode, time, inputMode, cls, name, required, disabled}.
   * The visible input has the id `${id}_` that the form's messages and labels use.
   */
  C.markup = function (o) {
    var id = String(o.id), mode = o.mode || 'single', cls = o.cls || 'efb form-control';
    var dis = o.disabled ? ' disabled' : '';
    var req = o.required ? ' aria-required="true"' : '';
    var out = '<div class="efb efb-cal" id="' + escAttr(id) + '_cal" data-fid="' + escAttr(id) + '" data-formid="' + escAttr(o.formId || 0) + '" data-efb-cal="' + escAttr(JSON.stringify(o.config || {})) + '">';
    out += '<div class="efb efb-cal-control"' + (mode === 'range' ? ' role="group" aria-labelledby="' + escAttr(id) + '_labG"' : '') + '>';
    out += '<input type="text" id="' + escAttr(id) + '_" class="' + escAttr(cls) + ' efb-cal-input" dir="ltr" inputmode="numeric" autocomplete="off" aria-describedby="' + escAttr(id) + '_-hint"' + req + dis + (mode === 'range' ? ' aria-label="' + escAttr(tx('cal_from')) + '"' : '') + '>';
    if (mode === 'range') { out += '<span class="efb efb-cal-sep" aria-hidden="true">–</span><input type="text" id="' + escAttr(id) + '_to" class="' + escAttr(cls) + ' efb-cal-input-to" dir="ltr" inputmode="numeric" autocomplete="off" aria-label="' + escAttr(tx('cal_to')) + '" aria-describedby="' + escAttr(id) + '_-hint"' + req + dis + '>'; }
    if (o.time && mode === 'single') { out += '<input type="text" id="' + escAttr(id) + '_time" class="' + escAttr(cls) + ' efb-cal-time" dir="ltr" inputmode="numeric" autocomplete="off" placeholder="HH:mm" aria-label="' + escAttr(tx('cal_time')) + '"' + dis + '>'; }
    if (o.inputMode !== 'typed') { out += '<button type="button" class="efb efb-cal-btn" aria-haspopup="dialog" aria-expanded="false" aria-label="' + escAttr(tx(mode === 'range' ? 'cal_choose_range' : 'cal_choose_date')) + '"' + dis + '>' + ICON + '</button>'; }
    out += '</div><small class="efb efb-cal-hint" id="' + escAttr(id) + '_-hint"></small><div class="efb efb-cal-disp" id="' + escAttr(id) + '_-disp"></div>';
    if (mode === 'multiple') { out += '<div class="efb efb-cal-chips" role="group" aria-label="' + escAttr(tx('cal_selected_dates')) + '"></div>'; }
    out += '<input type="hidden" class="emsFormBuilder_v efb-cal-value" id="' + escAttr(id) + '_v" data-vid="' + escAttr(id) + '" data-formid="' + escAttr(o.formId || 0) + '" data-type="advdate" name="' + escAttr(o.name || id) + '" value=""></div>';
    return out;
  };

  /** Mounts every [data-efb-cal] under root (default: the document). Safe to call again. */
  C.mount = function (scope) {
    var list = (scope || doc).querySelectorAll('[data-efb-cal]');
    for (var i = 0; i < list.length; i++) {
      var w = list[i];
      if (w.getAttribute('data-efb-cal-on')) { continue; }
      w.setAttribute('data-efb-cal-on', '1');
      var f = new Field(w);
      fields[key(f.fieldId, f.formId)] = f;
      (function (fi) { fi.ready.then(function () { announceReady(fi); }); })(f);
    }
  };
  C.Field = Field;
  if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', function () { C.mount(); }); } else { C.mount(); }
})(typeof window !== 'undefined' ? window : this);
