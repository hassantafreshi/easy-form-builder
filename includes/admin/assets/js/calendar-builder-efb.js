/**
 * F06 in the builder: the advanced date field's settings panel (F06 §6.2), its canvas markup, the
 * "Upgrade to the advanced date" / "Back to the old field" actions of date, pdate and ardate (§6.4)
 * and the checks the save and AutoPilot share (EFBCalendar.rules.sanitizeKey / configIssues, twins of
 * \Emsfb\Calendar::sanitize_key / config_issues). Controls write valj_efb directly with the save rules
 * (no change_el_edit_Efb: it refuses characters and runs on a timer); the server sanitises again.
 * Plan gating (F06 §10.1) through efb_var.capabilities (setting:advdate_*).
 */
(function (root) {
  'use strict';
  const C = () => root.EFBCalendar || null;
  const T = (k, fb) => { try { if (root.efb_var && root.efb_var.text && root.efb_var.text[k]) return root.efb_var.text[k]; } catch (e) { /* none */ } return fb; };
  const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const CFG = () => (root.EFB_CAL_ADMIN || (root.efb_var && root.efb_var.cal) || {});
  const LEGACY = { date: 'gregory', pdate: 'persian', ardate: 'islamic-umalqura' };
  const PART_CAP = { calendars: 'setting:advdate_calendars', dates: 'setting:advdate_dates', field_limits: 'setting:advdate_field_limits', age: 'setting:advdate_age', time: 'setting:advdate_time', booking: 'setting:advdate_booking' };

  function rowById(dataId) {
    if (typeof valj_efb === 'undefined') return -1;
    return valj_efb.findIndex((x, i) => i > 0 && x && (x.dataId == dataId || x.id_ == dataId));
  }
  function paid() { return (typeof pro_efb !== 'undefined' && pro_efb === true) || !!(root.efb_var && (root.efb_var.pro == '1' || root.efb_var.pro === 1 || root.efb_var.pro === true)); }
  function plan(part) {
    const key = PART_CAP[part];
    if (!key) return true;
    const cap = root.efb_var && root.efb_var.capabilities && root.efb_var.capabilities.features;
    if (cap && cap[key]) return !!cap[key].available_now;
    return part === 'booking' ? (typeof efbHasMobileProAccessEfb === 'function' ? efbHasMobileProAccessEfb() : paid()) : paid();
  }
  function gem(part) {
    if (plan(part)) return '';
    const lvl = part === 'booking' ? 'Pro' : 'Free Plus';
    return ` <a type="button" role="button" tabindex="0" class="efb pro-version-efb efb-cal-gem" data-cap="${PART_CAP[part]}" onclick="efb_cal_gem_efb(this)" title="${esc(lvl)}" aria-label="${esc(lvl)}"><i class="efb bi-gem text-light" aria-hidden="true"></i></a>`;
  }
  const lock = (part) => (part && !plan(part) ? ' disabled aria-disabled="true"' : '');
  root.efb_cal_gem_efb = function (el) {
    const cap = el && el.getAttribute('data-cap');
    if (typeof efb_upgrade_prompt_efb === 'function') efb_upgrade_prompt_efb({ feature: cap, fallback: cap === PART_CAP.booking ? 'pro' : 'free_plus', origin: 'settings', opener: el });
  };

  /* ------------------------------------------------------------------ controls */
  const id = (k) => `calEl_${k}`;
  function row(i) { return typeof valj_efb !== 'undefined' ? valj_efb[i] : null; }
  function val(r, k, d) { return r && r[k] !== undefined && r[k] !== null && r[k] !== '' ? r[k] : d; }
  function sel(r, idset, key, label, options, dflt, part, extra) {
    const cur = String(val(r, key, dflt));
    return `<div class="efb mt-2 mb-1 efb-cal-b"><label for="${id(key)}" class="efb mb-1 fs-7">${esc(label)}${part ? gem(part) : ''}</label>
      <select class="efb form-select efb border-d rounded-4 efb-cal-set" id="${id(key)}" data-id="${esc(idset)}" data-key="${key}" onchange="efb_cal_change_efb(this)"${part ? lock(part) : ''}${extra || ''}>
      ${options.map((o) => `<option value="${esc(o[0])}" ${cur === String(o[0]) ? 'selected' : ''}>${esc(o[1])}</option>`).join('')}</select></div>`;
  }
  function txt(r, idset, key, label, opts) {
    opts = opts || {};
    const v = opts.value !== undefined ? opts.value : val(r, key, '');
    return `<div class="efb mt-2 mb-1 efb-cal-b ${opts.wrap || ''}"><label for="${id(opts.idk || key)}" class="efb mb-1 fs-7">${esc(label)}${opts.part ? gem(opts.part) : ''}</label>
      <input type="text" class="efb form-control efb h-d-efb border-d rounded-4 efb-cal-set" id="${id(opts.idk || key)}" data-id="${esc(idset)}" data-key="${key}" ${opts.sub ? `data-sub="${opts.sub}"` : ''} value="${esc(v)}" placeholder="${esc(opts.placeholder || '')}" ${opts.dir ? `dir="${opts.dir}"` : 'dir="ltr"'} ${opts.numeric ? 'inputmode="numeric"' : ''} onchange="efb_cal_change_efb(this)"${opts.part ? lock(opts.part) : ''}>
      ${opts.help ? `<small class="efb form-text text-muted d-block mt-1 fs-8">${esc(opts.help)}</small>` : ''}</div>`;
  }
  function area(r, idset, key, label, value, opts) {
    opts = opts || {};
    return `<div class="efb mt-2 mb-1 efb-cal-b"><label for="${id(key)}" class="efb mb-1 fs-7">${esc(label)}${opts.part ? gem(opts.part) : ''}</label>
      <textarea class="efb form-control efb border-d rounded-4 efb-cal-set" rows="3" id="${id(key)}" data-id="${esc(idset)}" data-key="${key}" dir="ltr" placeholder="${esc(opts.placeholder || '')}" onchange="efb_cal_change_efb(this)"${opts.part ? lock(opts.part) : ''}>${esc(value)}</textarea>
      ${opts.help ? `<small class="efb form-text text-muted d-block mt-1 fs-8">${esc(opts.help)}</small>` : ''}</div>`;
  }
  function toggle(r, idset, key, label, part) {
    const on = String(val(r, key, '0')) === '1';
    return `<div class="efb mx-1 my-2 efb-cal-b d-flex align-items-center"><button type="button" id="${id(key)}" data-id="${esc(idset)}" data-key="${key}" class="efb mx-0 btn h-s-efb btn-toggle efb-cal-set ${on ? 'active' : ''}" aria-pressed="${on ? 'true' : 'false'}" aria-labelledby="${id(key)}Lbl" onclick="efb_cal_change_efb(this)"${part ? lock(part) : ''}><div class="efb handle"></div></button>
      <span id="${id(key)}Lbl" class="efb mx-2 fs-7">${esc(label)}${part ? gem(part) : ''}</span></div>`;
  }
  const openGroups = { basic: true };
  function group(key, title, body) {
    return `<details class="efb efb-cal-group my-2 border rounded-3 px-2" ${openGroups[key] ? 'open' : ''} data-group="${key}" ontoggle="efb_cal_group_efb(this)"><summary class="efb fs-6 fw-bold py-2">${esc(title)}</summary>${body}</details>`;
  }
  function calOptions(withAuto) {
    const loc = (root.efb_var && root.efb_var.wp_lan) || 'en_US';
    const list = ['gregory', 'persian', 'islamic-umalqura', 'islamic-civil', 'islamic-tbla', 'buddhist', 'roc', 'japanese'];
    const lab = (c) => (C() ? C()._.calendarLabel(c, loc, true) : c);
    const out = list.map((c) => [c, lab(c)]);
    if (withAuto) {
      const d = CFG().locale_defaults ? CFG().locale_defaults['default'] : 'gregory';
      out.unshift(['auto', T('cal_b_auto', 'Automatic, from the site language') + ' (' + lab(d) + ')']);
    }
    return out;
  }
  function limitText(l) {
    if (!l || typeof l !== 'object') return '';
    if (l.type === 'absolute') return l.value || '';
    if (l.type === 'relative') return String(l.value) + (l.unit && l.unit !== 'day' ? ' ' + l.unit : '');
    if (l.type === 'field') return (l.offset ? String(l.offset) : '0') + (l.unit && l.unit !== 'day' ? ' ' + l.unit : '');
    return '';
  }
  function limitEls(r, idset, key, label) {
    const l = C() ? C().rules.sanitizeKey(key, r[key]) : (r[key] || { type: 'none' });
    const others = typeof valj_efb !== 'undefined' ? valj_efb.filter((x, i) => i > 0 && x && x.type === 'advdate' && x.id_ !== r.id_) : [];
    const types = [['none', T('cal_b_lim_none', 'No limit')], ['relative', T('cal_b_lim_relative', 'Days from today')], ['absolute', T('cal_b_lim_absolute', 'A fixed date (YYYY-MM-DD)')]];
    if (others.length || l.type === 'field') types.push(['field', T('cal_b_lim_field', 'Another date field')]);
    const fieldLock = l.type === 'field' ? lock('field_limits') : '';
    let html = `<div class="efb efb-cal-limit border-start ps-2 my-2" data-key="${key}">
      <div class="efb mt-2 mb-1 efb-cal-b"><label for="${id(key)}_type" class="efb mb-1 fs-7">${esc(label)}${l.type === 'field' ? gem('field_limits') : ''}</label>
      <select class="efb form-select efb border-d rounded-4 efb-cal-set" id="${id(key)}_type" data-id="${esc(idset)}" data-key="${key}" data-sub="type" onchange="efb_cal_change_efb(this)">
      ${types.map((o) => `<option value="${o[0]}" ${o[0] === l.type ? 'selected' : ''}>${esc(o[1])}</option>`).join('')}</select></div>`;
    if (l.type === 'field') {
      html += `<div class="efb mt-1 mb-1 efb-cal-b"><label for="${id(key)}_field" class="efb mb-1 fs-7">${esc(T('cal_b_lim_which', 'Which field'))}</label>
      <select class="efb form-select efb border-d rounded-4 efb-cal-set" id="${id(key)}_field" data-id="${esc(idset)}" data-key="${key}" data-sub="field" onchange="efb_cal_change_efb(this)"${fieldLock}>
      ${others.map((x) => `<option value="${esc(x.id_)}" ${x.id_ === l.field ? 'selected' : ''}>${esc(x.name || x.id_)}</option>`).join('')}</select></div>`;
    }
    if (l.type !== 'none') {
      html += txt(r, idset, key, l.type === 'absolute' ? T('cal_b_lim_date', 'Date (YYYY-MM-DD)') : T('cal_b_lim_amount', 'How many (e.g. 1, -1, 2 week, 1 month)'), { idk: key + '_v', sub: 'value', value: limitText(l), numeric: l.type !== 'absolute', part: l.type === 'field' ? 'field_limits' : null });
    }
    return html + '</div>';
  }
  function weekdaysEl(r, idset) {
    const loc = (root.efb_var && root.efb_var.wp_lan) || 'en_US';
    const names = C() ? C()._.weekdayNames(loc, 'long') : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    let ws = 1;
    try { const li = new Intl.Locale(loc.replace('_', '-')); const wi = li.getWeekInfo ? li.getWeekInfo() : li.weekInfo; if (wi && wi.firstDay) ws = wi.firstDay; } catch (e) { /* Monday */ }
    const cur = C() ? C().rules.sanitizeKey('disabled_weekdays', r.disabled_weekdays) : [];
    let html = `<fieldset class="efb mt-2 mb-1 efb-cal-b"><legend class="efb mb-1 fs-7">${esc(T('cal_b_closed_days', 'Closed days of the week'))}</legend><div class="efb d-flex flex-wrap gap-2">`;
    for (let i = 0; i < 7; i++) {
      const wd = ((ws - 1 + i) % 7) + 1;
      html += `<label class="efb fs-7 d-inline-flex align-items-center gap-1"><input type="checkbox" class="efb form-check-input efb-cal-set" data-id="${esc(idset)}" data-key="disabled_weekdays" data-wd="${wd}" ${cur.indexOf(wd) !== -1 ? 'checked' : ''} onchange="efb_cal_change_efb(this)">${esc(names[wd - 1])}</label>`;
    }
    return html + '</div></fieldset>';
  }
  function holidaySetsEl(r, idset) {
    const sets = CFG().holiday_sets || [];
    if (!sets.length) return '';
    const cur = Array.isArray(r.holiday_sets) ? r.holiday_sets : [];
    let html = `<fieldset class="efb mt-2 mb-1 efb-cal-b"><legend class="efb mb-1 fs-7">${esc(T('cal_b_holiday_sets', 'Holiday lists'))}${gem('dates')}</legend>`;
    sets.forEach((s) => { html += `<label class="efb fs-7 d-flex align-items-center gap-1"><input type="checkbox" class="efb form-check-input efb-cal-set" data-id="${esc(idset)}" data-key="holiday_sets" data-set="${esc(s.id)}" ${cur.indexOf(s.id) !== -1 ? 'checked' : ''} onchange="efb_cal_change_efb(this)"${lock('dates')}>${esc(s.label)}${s.verified ? '' : ' <small class="efb text-muted">(' + esc(T('cal_b_unverified', 'check the dates before use')) + ')</small>'}</label>`; });
    return html + '</fieldset>';
  }
  function holidaysText(list) {
    return (Array.isArray(list) ? list : []).map((h) => {
      const lb = typeof h.label === 'string' ? h.label : (h.label && (h.label.en || Object.values(h.label)[0])) || '';
      return h.date ? `${h.date} ${lb}`.trim() : `${h.cal} ${h.md}${h.days > 1 ? ' ' + h.days : ''} ${lb}`.trim();
    }).join('\n');
  }
  function weekTimesText(o) {
    return Object.keys(o || {}).sort().map((k) => `${k}: ${(o[k] || []).map((w) => w[0] + '-' + w[1]).join(', ') || 'closed'}`).join('\n');
  }
  /** Ninety days with the same rules the server runs: how many can be chosen and the first ones that cannot. */
  function previewText(r) {
    if (!C()) return '';
    const cfg = CFG();
    const ctx = { locale: cfg.locale || 'en_US', site_tz: cfg.site_tz || 'UTC', plan: cfg.plan || 'pro', localeDefaults: cfg.locale_defaults };
    const c = C().rules.normalizeField(r, ctx), rr = C().rules.resolve(c, ctx);
    let open = 0; const closed = [];
    for (let i = 0; i < 90; i++) {
      const d = C()._.addDays(rr.today, i);
      const e = C().rules.refuse(c, rr, d, ctx);
      if (!e) open++; else if (closed.length < 3) closed.push(d + ' (' + e.code + ')');
    }
    return T('cal_b_preview_n', 'Next 90 days: %1$s can be chosen.').replace('%1$s', String(open)) + (closed.length ? ' ' + closed.join(', ') + (90 - open > 3 ? ' …' : '') : '');
  }

  /** The advdate panel (val-efb.js case 'advdate'). */
  root.efbAdvdatePanelEls = function (indx, idset) {
    const r = row(indx);
    if (!r) return '';
    const mode = val(r, 'date_mode', 'single');
    const cal = C() ? C().rules.sanitizeKey('cal_allowed', r.cal_allowed) : [];
    const loc = (root.efb_var && root.efb_var.wp_lan) || 'en_US';
    const basic = sel(r, idset, 'date_mode', T('cal_b_mode', 'Kind of answer'), [['single', T('cal_b_mode_single', 'One date')], ['range', T('cal_b_mode_range', 'Start and end (range)')], ['multiple', T('cal_b_mode_multiple', 'Several dates')]], 'single', mode !== 'single' || !plan('booking') ? 'booking' : null)
      + sel({ p: '' }, idset, 'preset', T('cal_b_preset', 'Quick setup'), [['', '—'], ['future', T('cal_b_preset_future', 'Only future dates (from tomorrow)')], ['today_on', T('cal_b_preset_today', 'Today or later')], ['past', T('cal_b_preset_past', 'Only past dates')], ['birth', T('cal_b_preset_birth', 'Date of birth')]], '')
      + txt(r, idset, 'value', T('cal_b_default', 'Default value'), { placeholder: 'today, today+7, 2026-03-20', help: T('cal_b_default_help', 'Empty, "today", "today+N" or a date as YYYY-MM-DD (Gregorian).') });
    const calendars = sel(r, idset, 'cal_default', T('cal_b_cal_default', 'Calendar'), calOptions(true), 'auto')
      + `<fieldset class="efb mt-2 mb-1 efb-cal-b"><legend class="efb mb-1 fs-7">${esc(T('cal_b_cal_allowed', 'Calendars the visitor can switch to'))}${gem('calendars')}</legend>`
      + calOptions(false).map((o) => `<label class="efb fs-7 d-flex align-items-center gap-1"><input type="checkbox" class="efb form-check-input efb-cal-set" data-id="${esc(idset)}" data-key="cal_allowed" data-cal="${o[0]}" ${cal.indexOf(o[0]) !== -1 ? 'checked' : ''} onchange="efb_cal_change_efb(this)"${lock('calendars')}>${esc(o[1])}</label>`).join('') + '</fieldset>'
      + toggle(r, idset, 'cal_switch', T('cal_b_cal_switch', 'Let the visitor switch calendars'), 'calendars')
      + sel(r, idset, 'cal_secondary', T('cal_b_cal_secondary', 'Second calendar under each day'), [['', '—']].concat(calOptions(false), [['hebrew', C() ? C()._.calendarLabel('hebrew', loc, true) : 'hebrew']]), '', 'calendars')
      + sel(r, idset, 'cal_hijri_adjust', T('cal_b_hijri_adjust', 'Hijri sighting adjustment (days)'), [['-2', '-2'], ['-1', '-1'], ['0', '0'], ['1', '+1'], ['2', '+2']], '0', 'calendars')
      + sel(r, idset, 'cal_digits', T('cal_b_digits', 'Digits'), [['auto', T('cal_b_auto_lang', 'From the site language')], ['latn', '0123'], ['arabext', '۰۱۲۳'], ['arab', '٠١٢٣']], 'auto')
      + sel(r, idset, 'week_start', T('cal_b_week_start', 'Week starts on'), [['auto', T('cal_b_auto_lang', 'From the site language')]].concat([6, 7, 1].map((d) => [String(d), C() ? C()._.weekdayNames(loc, 'long')[d - 1] : String(d)])), 'auto')
      + sel(r, idset, 'display_format', T('cal_b_format', 'Typed format'), [['auto', T('cal_b_auto_cal', 'From the calendar and language')], ['YYYY/MM/DD', 'YYYY/MM/DD'], ['DD/MM/YYYY', 'DD/MM/YYYY'], ['MM/DD/YYYY', 'MM/DD/YYYY'], ['DD.MM.YYYY', 'DD.MM.YYYY'], ['YYYY-MM-DD', 'YYYY-MM-DD']], 'auto');
    const limits = limitEls(r, idset, 'date_min', T('cal_b_min', 'Earliest date')) + limitEls(r, idset, 'date_max', T('cal_b_max', 'Latest date')) + weekdaysEl(r, idset)
      + area(r, idset, 'disabled_dates', T('cal_b_disabled_dates', 'Closed dates'), (C() ? C().rules.sanitizeKey('disabled_dates', r.disabled_dates) : []).join('\n'), { part: 'dates', placeholder: '2026-12-25\n2027-01-01/2027-01-03', help: T('cal_b_dates_help', 'One per line: YYYY-MM-DD or a range YYYY-MM-DD/YYYY-MM-DD (Gregorian).') })
      + area(r, idset, 'enabled_dates', T('cal_b_enabled_dates', 'Only these dates'), (C() ? C().rules.sanitizeKey('enabled_dates', r.enabled_dates) : []).join('\n'), { part: 'dates', help: T('cal_b_enabled_help', 'When filled, only these dates can be chosen (closed weekdays stay closed).') })
      + holidaySetsEl(r, idset)
      + area(r, idset, 'holidays_custom', T('cal_b_holidays_custom', 'Own holidays'), holidaysText(C() ? C().rules.sanitizeKey('holidays_custom', r.holidays_custom) : []), { part: 'dates', placeholder: 'persian 01-01 4 Nowruz\n2026-12-25 Christmas', help: T('cal_b_holidays_help', 'One per line: a calendar, month-day and days (every year), or a Gregorian date, then the name.') })
      + sel(r, idset, 'holiday_mode', T('cal_b_holiday_mode', 'Holidays'), [['disable', T('cal_b_hol_disable', 'Cannot be chosen')], ['mark', T('cal_b_hol_mark', 'Marked, can be chosen')]], 'disable', 'dates');
    const range = sel(r, idset, 'range_unit', T('cal_b_range_unit', 'Count'), [['nights', T('cal_b_nights', 'Nights (check-out day can be closed)')], ['days', T('cal_b_days', 'Days (every day must be open)')]], 'nights', 'booking')
      + txt(r, idset, 'range_min_days', T('cal_b_range_min', 'At least'), { numeric: true, part: 'booking' }) + txt(r, idset, 'range_max_days', T('cal_b_range_max', 'At most'), { numeric: true, part: 'booking' })
      + txt(r, idset, 'multi_max', T('cal_b_multi_max', 'Most dates (several dates)'), { numeric: true, part: 'booking' });
    const time = toggle(r, idset, 'time_enabled', T('cal_b_time', 'Ask for a time'), 'time')
      + sel(r, idset, 'time_format', T('cal_b_time_format', 'Clock'), [['auto', T('cal_b_auto_lang', 'From the site language')], ['24', '24 h'], ['12', '12 h']], 'auto', 'time')
      + txt(r, idset, 'time_step', T('cal_b_time_step', 'Step in minutes'), { numeric: true, part: 'time' })
      + txt(r, idset, 'time_min', T('cal_b_time_min', 'From (HH:mm)'), { part: 'time' }) + txt(r, idset, 'time_max', T('cal_b_time_max', 'Until (HH:mm)'), { part: 'time' })
      + area(r, idset, 'time_by_weekday', T('cal_b_time_week', 'Hours per weekday'), weekTimesText(C() ? C().rules.sanitizeKey('time_by_weekday', r.time_by_weekday) : {}), { part: 'booking', placeholder: '6: 09:00-13:00, 14:00-18:00\n5: closed', help: T('cal_b_time_week_help', 'One weekday per line (1 = Monday … 7 = Sunday).') });
    const age = txt(r, idset, 'age_min', T('cal_b_age_min', 'Youngest age'), { numeric: true, part: 'age' }) + txt(r, idset, 'age_max', T('cal_b_age_max', 'Oldest age'), { numeric: true, part: 'age' });
    const adv = sel(r, idset, 'tz', T('cal_b_tz', 'Time zone for "today" and times'), [['site', T('cal_b_tz_site', 'The site\'s') + ' (' + (CFG().site_tz || 'UTC') + ')'], ['visitor', T('cal_b_tz_visitor', 'The visitor\'s (within a day of the site)')]].concat(r.tz && r.tz !== 'site' && r.tz !== 'visitor' ? [[r.tz, r.tz]] : []), 'site')
      + sel(r, idset, 'input_mode', T('cal_b_input_mode', 'Input'), [['both', T('cal_b_input_both', 'Typing and the calendar')], ['picker', T('cal_b_input_picker', 'The calendar only')], ['typed', T('cal_b_input_typed', 'Typing only')]], 'both')
      + (r.upg_from ? sel(r, idset, 'store_format', T('cal_b_store', 'Stored value'), [['iso', T('cal_b_store_iso', 'Gregorian (YYYY-MM-DD)')], ['legacy_local', T('cal_b_store_local', 'Keep the old local format')]], 'iso') : '')
      + `<p class="efb fs-7 text-muted mt-2 efb-cal-preview" id="calElPreview" aria-live="polite">${esc(previewText(r))}</p>`;
    const back = r.upg_from ? `<div class="efb efb-cal-upgraded border rounded-3 p-2 my-2"><p class="efb fs-7 mb-1">${esc(T('cal_b_upgraded_note', 'This field was upgraded from the old date field. Its old settings are kept.'))}</p>
      <button type="button" class="efb btn btn-sm btn-outline-secondary" id="calElRollback" data-id="${esc(idset)}" onclick="efb_cal_rollback_efb(this)">${esc(T('cal_b_rollback', 'Back to the old field'))}</button></div>` : '';
    const issues = C() && typeof valj_efb !== 'undefined' ? C().rules.configIssues(r, valj_efb) : [];
    return `<div class="efb efb-cal-panel" data-id="${esc(idset)}">
      <div class="efb alert alert-warning fs-7 py-1 my-1 ${issues.length ? '' : 'd-none'}" role="alert" id="calElIssues">${esc(issues.map((x) => issueText(x)).join(' '))}</div>
      ${back}
      ${group('basic', T('cal_b_g_basic', 'Date'), basic)}
      ${group('calendar', T('cal_b_g_calendar', 'Calendar'), calendars)}
      ${group('limits', T('cal_b_g_limits', 'Limits'), limits)}
      ${group('range', T('cal_b_g_range', 'Range and several dates'), range)}
      ${group('time', T('cal_b_g_time', 'Time'), time)}
      ${group('age', T('cal_b_g_age', 'Age'), age)}
      ${group('advanced', T('cal_b_g_advanced', 'Advanced'), adv)}
    </div>`;
  };
  function issueText(x) {
    const m = {
      date_min_after_max: T('cal_b_err_minmax', 'The earliest date is after the latest date.'),
      time_min_after_max: T('cal_b_err_time', 'The first time must come before the last time.'),
      range_min_after_max: T('cal_b_err_range', 'The shortest stay is longer than the longest.'),
      age_min_after_max: T('cal_b_err_age', 'The youngest age is above the oldest age.'),
      dangling_reference: T('cal_b_err_dangling', 'A limit names a date field that is not in this form.'),
      limit_self: T('cal_b_err_self', 'A limit cannot name its own field.'),
      limit_cycle: T('cal_b_err_cycle', 'Two date fields limit each other in a circle.'),
      default_not_allowed: T('cal_b_err_default', 'The calendar must be one of the calendars the visitor can use.')
    };
    return m[x.code] || x.code;
  }

  /* ------------------------------------------------------------------ handlers */
  function parseLimit(cur, el) {
    const sub = el.getAttribute('data-sub');
    const l = Object.assign({ type: 'none' }, cur && typeof cur === 'object' ? cur : {});
    if (sub === 'type') {
      const t = el.value;
      if (t === 'none') return { type: 'none' };
      if (t === 'relative') return { type: 'relative', value: 0, unit: 'day' };
      if (t === 'absolute') return { type: 'absolute', value: C() ? C().today('site') : '' };
      const first = (typeof valj_efb !== 'undefined' ? valj_efb : []).find((x, i) => i > 0 && x && x.type === 'advdate' && x.id_ !== rowId(el));
      return { type: 'field', field: first ? first.id_ : '', offset: 0, unit: 'day' };
    }
    if (sub === 'field') { l.field = el.value; return l; }
    const s = C() ? C()._.normalize(el.value) : el.value;
    if (l.type === 'absolute') { l.value = s; return l; }
    const m = /^(-?\d+)\s*(day|days|week|weeks|month|months|year|years)?$/i.exec(s);
    if (!m) return l;
    const unit = (m[2] || 'day').toLowerCase().replace(/s$/, '');
    if (l.type === 'relative') { l.value = parseInt(m[1], 10); l.unit = unit; } else { l.offset = parseInt(m[1], 10); l.unit = unit; }
    return l;
  }
  function rowId(el) { const i = rowById(el.getAttribute('data-id')); return i > 0 ? valj_efb[i].id_ : ''; }
  function parseHolidays(text) {
    return String(text || '').split(/\n+/).map((line) => line.trim()).filter(Boolean).map((line) => {
      const p = line.split(/\s+/);
      if (/^\d{4}-\d{2}-\d{2}(\/\d{4}-\d{2}-\d{2})?$/.test(C() ? C()._.normalize(p[0]) : p[0])) return { date: C() ? C()._.normalize(p[0]) : p[0], label: p.slice(1).join(' ') };
      const days = p[2] && /^\d{1,2}$/.test(p[2]) ? parseInt(p[2], 10) : 1;
      return { cal: p[0], md: C() ? C()._.normalize(p[1] || '') : (p[1] || ''), days: days, label: p.slice(days > 1 || (p[2] && /^\d{1,2}$/.test(p[2])) ? 3 : 2).join(' ') };
    });
  }
  function parseWeekTimes(text) {
    const o = {};
    String(text || '').split(/\n+/).forEach((line) => {
      const m = /^\s*([1-7])\s*:\s*(.*)$/.exec(C() ? C()._.normalize(line) : line);
      if (!m) return;
      o[m[1]] = /closed/i.test(m[2]) || m[2].trim() === '' ? [] : m[2].split(/\s*,\s*/).map((w) => w.split(/\s*-\s*/)).filter((w) => w.length === 2);
    });
    return o;
  }
  /** Writes one key with the save rules and refreshes the canvas field and the panel's notes. */
  function setKey(indx, key, value) {
    const r = valj_efb[indx];
    const v = C() ? C().rules.sanitizeKey(key, value) : value;
    if (v === '' || v === null || (Array.isArray(v) && !v.length && key !== 'cal_allowed')) delete r[key]; else r[key] = v;
    return v;
  }
  function applyPreset(indx, p) {
    const r = valj_efb[indx];
    ['date_min', 'date_max', 'age_min', 'age_max'].forEach((k) => delete r[k]);
    if (p === 'future') r.date_min = { type: 'relative', value: 1, unit: 'day' };
    if (p === 'today_on') r.date_min = { type: 'relative', value: 0, unit: 'day' };
    if (p === 'past') r.date_max = { type: 'relative', value: -1, unit: 'day' };
    if (p === 'birth') { r.date_max = { type: 'relative', value: 0, unit: 'day' }; r.input_mode = 'typed'; if (plan('age')) r.age_min = 18; }
  }
  root.efb_cal_group_efb = function (d) { if (d && d.getAttribute('data-group')) openGroups[d.getAttribute('data-group')] = !!d.open; };
  root.efb_cal_change_efb = function (el) {
    if (!el || typeof valj_efb === 'undefined') return;
    const indx = rowById(el.getAttribute('data-id'));
    if (indx < 1) return;
    const r = valj_efb[indx];
    const key = el.getAttribute('data-key');
    let redraw = false;
    switch (key) {
      case 'preset': applyPreset(indx, el.value); redraw = true; break;
      case 'value': r.value = String(el.value || '').replace(/[<>"]/g, '').trim(); break;
      case 'cal_switch': case 'time_enabled': {
        el.classList.toggle('active');
        const on = el.classList.contains('active');
        el.setAttribute('aria-pressed', on ? 'true' : 'false');
        setKey(indx, key, on ? 1 : 0);
        if (key === 'time_enabled') redraw = true;
        break;
      }
      case 'cal_allowed': {
        const boxes = document.querySelectorAll(`.efb-cal-set[data-key="cal_allowed"][data-id="${el.getAttribute('data-id')}"]`);
        setKey(indx, key, Array.prototype.filter.call(boxes, (b) => b.checked).map((b) => b.getAttribute('data-cal')));
        break;
      }
      case 'disabled_weekdays': {
        const boxes = document.querySelectorAll(`.efb-cal-set[data-key="disabled_weekdays"][data-id="${el.getAttribute('data-id')}"]`);
        setKey(indx, key, Array.prototype.filter.call(boxes, (b) => b.checked).map((b) => Number(b.getAttribute('data-wd'))));
        break;
      }
      case 'holiday_sets': {
        const boxes = document.querySelectorAll(`.efb-cal-set[data-key="holiday_sets"][data-id="${el.getAttribute('data-id')}"]`);
        setKey(indx, key, Array.prototype.filter.call(boxes, (b) => b.checked).map((b) => b.getAttribute('data-set')));
        break;
      }
      case 'disabled_dates': case 'enabled_dates': setKey(indx, key, String(el.value || '').split(/[\n,]+/).map((x) => x.trim()).filter(Boolean)); break;
      case 'holidays_custom': setKey(indx, key, parseHolidays(el.value)); break;
      case 'time_by_weekday': setKey(indx, key, parseWeekTimes(el.value)); break;
      case 'date_min': case 'date_max': setKey(indx, key, parseLimit(r[key], el)); redraw = el.getAttribute('data-sub') === 'type'; break;
      case 'date_mode': setKey(indx, key, el.value); redraw = true; break;
      default: setKey(indx, key, el.value);
    }
    // The calendar the field opens with is always one the visitor may use (F06 §6.2).
    if ((key === 'cal_default' || key === 'cal_allowed') && r.cal_default && r.cal_default !== 'auto' && Array.isArray(r.cal_allowed) && r.cal_allowed.length && r.cal_allowed.indexOf(r.cal_default) === -1) r.cal_allowed.unshift(r.cal_default);
    refreshCanvas(r);
    if (redraw && typeof show_setting_window_efb === 'function') { show_setting_window_efb(r.dataId, { refresh: true }); return; }
    const p = document.getElementById('calElPreview');
    if (p) p.textContent = previewText(r);
    const is = document.getElementById('calElIssues');
    if (is) { const x = C() ? C().rules.configIssues(r, valj_efb) : []; is.textContent = x.map(issueText).join(' '); is.classList.toggle('d-none', !x.length); }
  };

  /* ------------------------------------------------------------------ canvas */
  function config(r, formId) {
    const cfg = CFG();
    const field = { type: 'advdate', id_: r.id_ };
    (C() ? C().rules.fieldKeys : []).forEach((k) => { if (r[k] !== undefined) field[k] = r[k]; });
    field.required = Number(r.required) === 1 ? 1 : 0;
    return { v: 1, form_id: formId || 0, field: field, locale: cfg.locale || (root.efb_var && root.efb_var.wp_lan) || 'en_US', locale_defaults: cfg.locale_defaults, plan: cfg.plan || 'pro', site: { tz: cfg.site_tz || 'UTC' }, initial: r.value || '' };
  }
  /** The canvas / preview markup of an advdate row (addNewElement case 'advdate'). */
  root.efbAdvdateCanvas = function (r, previewSate, classes) {
    if (!C() || typeof C().markup !== 'function') return `<input type="text" class="efb form-control efb-cal-input" id="${esc(r.id_)}_" readonly>`;
    const cfg = config(r, previewSate ? 1 : 0);
    const mode = C().rules.normalizeField(cfg.field, { locale: cfg.locale, plan: cfg.plan, localeDefaults: cfg.locale_defaults }).date_mode;
    let html = C().markup({ id: r.id_, formId: cfg.form_id, config: cfg, mode: mode, time: String(r.time_enabled) === '1', inputMode: r.input_mode, cls: 'efb input-efb px-2 mb-0 w-100 form-control ' + (classes || ''), name: r.name, required: Number(r.required) === 1 });
    if (!previewSate) html = html.replace(/data-efb-cal="/, 'data-efb-cal-off="').replace(/<input type="text"/g, '<input type="text" readonly tabindex="-1"');
    else setTimeout(() => { if (C() && C().mount) C().mount(); }, 0);
    return html;
  };
  function refreshCanvas(r) {
    const old = document.getElementById(`${r.id_}_cal`);
    if (!old) return;
    const preview = old.hasAttribute('data-efb-cal');
    const holder = document.createElement('div');
    holder.innerHTML = root.efbAdvdateCanvas(r, preview, '');
    if (holder.firstElementChild) old.replaceWith(holder.firstElementChild);
  }

  /* ------------------------------------------------------------------ upgrade (F06 §6.4) */
  /** The panel controls of a date / pdate / ardate field: the upgrade button and its warning. */
  root.efbAdvdateUpgradeEls = function (indx, idset) {
    const r = row(indx);
    if (!r || !LEGACY[r.type]) return '';
    const local = r.type !== 'date';
    return `<div class="efb efb-cal-upgrade border rounded-3 p-2 my-2" id="calElUpgradeBox">
      <p class="efb fs-7 mb-1">${esc(T('cal_b_upgrade_note', 'The advanced date field has every calendar, limits, holidays, ranges and times, without the old add-ons. New answers are stored as Gregorian dates (YYYY-MM-DD). If a webhook or Google Sheet expects the local date, keep the old format.'))}</p>
      ${local ? `<label class="efb fs-7 d-flex align-items-center gap-1"><input type="checkbox" class="efb form-check-input" id="calElKeepLocal">${esc(T('cal_b_keep_local', 'Keep the old local format in new answers'))}</label>` : ''}
      <button type="button" class="efb btn btn-sm btn-outline-primary mt-2" id="calElUpgrade" data-id="${esc(idset)}" onclick="efb_cal_upgrade_efb(this)">${esc(T('cal_b_upgrade', 'Upgrade to the advanced date'))}</button>
    </div>`;
  };
  root.efb_cal_upgrade_efb = function (el) {
    const indx = rowById(el.getAttribute('data-id'));
    if (indx < 1 || !C()) return;
    const keep = document.getElementById('calElKeepLocal');
    valj_efb[indx] = C().rules.upgradeRow(valj_efb[indx], { keepLocal: !!(keep && keep.checked) });
    redrawField(valj_efb[indx]);
  };
  root.efb_cal_rollback_efb = function (el) {
    const indx = rowById(el.getAttribute('data-id'));
    if (indx < 1 || !C()) return;
    valj_efb[indx] = C().rules.rollbackRow(valj_efb[indx]);
    redrawField(valj_efb[indx]);
  };
  function redrawField(r) {
    const api = root.efbBuilderApi;
    const done = () => { if (typeof show_setting_window_efb === 'function') show_setting_window_efb(r.dataId, { refresh: true }); };
    if (api && typeof api.render === 'function') api.render().then(done, done); else done();
  }
})(typeof window !== 'undefined' ? window : this);
