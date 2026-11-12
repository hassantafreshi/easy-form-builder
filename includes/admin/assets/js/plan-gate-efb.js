/**
 * Plan gate and upgrade prompt (F18).
 *
 * One registry decides which plan unlocks what: efb_var.capabilities, built by
 * \Emsfb\Builder_Api::capabilities() in PHP (AutoPilot B3). This file only reads it - it keeps
 * no plan table of its own - and adds, on top of it:
 *
 *   A  read-only plan helpers (efb_current_package_efb, efb_feature_required_efb, ...);
 *   B  the upgrade dialog, efb_upgrade_prompt_efb() (pro_show_efb() in admin-efb.js is its shim);
 *   D  the in-page plan change: efb_on_plan_changed_efb() and its subscribers;
 *   F  what the person clicked is done once the lock is gone (efb_run_resume_efb), also after a
 *      reload (add-on install), through sessionStorage efb_resume_efb.
 *   H  ?efb_upgrade=<feature> (the public page's note, SitePilot) opens the dialog - never activates.
 *
 * Loaded on the Create, Panel and Add-ons screens after admin-efb.js. Everything here is a
 * function or a var, so the file also runs in a vm for the tests.
 */

/* ------------------------------------------------------------------ A: registry readers */

/** 0|1|2|3 from the page's plan (efb_var.setting.package_type); -1, 10 or missing = 2 (Free). */
function efb_current_package_efb() {
  var s = (typeof efb_var !== 'undefined' && efb_var && efb_var.setting) ? efb_var.setting : null;
  var p = s && s.package_type !== undefined && s.package_type !== null && s.package_type !== '' ? Number(s.package_type) : NaN;
  return [0, 1, 2, 3].indexOf(p) !== -1 ? p : 2;
}

/** false while no plan was picked (-1, 10 or missing). */
function efb_plan_chosen_efb() {
  var s = (typeof efb_var !== 'undefined' && efb_var && efb_var.setting) ? efb_var.setting : null;
  var p = s ? Number(s.package_type) : NaN;
  return [0, 1, 2, 3].indexOf(p) !== -1;
}

/** 'free' | 'free_plus' | 'pro' | 'pro_pending' (0: a Pro licence that is not active). */
function efb_plan_name_efb(pkg) {
  var p = pkg === undefined ? efb_current_package_efb() : Number(pkg);
  return p === 1 ? 'pro' : (p === 3 ? 'free_plus' : (p === 0 ? 'pro_pending' : 'free'));
}

/* free < free_plus < pro; a lapsed Pro (0) counts as Free, as in PHP plan_at_least(). */
var EFB_PLAN_RANK_EFB = { free: 0, free_plus: 1, pro: 2 };
function efb_plan_rank_efb(pkg) {
  var n = efb_plan_name_efb(pkg);
  return n === 'pro_pending' ? 0 : EFB_PLAN_RANK_EFB[n];
}

/** The registry entry of a feature key (field:<type>, setting:<name>, addon:<code>, limit:<name>, …) or null. */
function efb_feature_entry_efb(key) {
  var caps = (typeof efb_var !== 'undefined' && efb_var && efb_var.capabilities) ? efb_var.capabilities : null;
  return caps && caps.features && Object.prototype.hasOwnProperty.call(caps.features, key) ? caps.features[key] : null;
}

/**
 * 'free' | 'free_plus' | 'pro'. An unknown key is Pro, as efb_feature_required_package_efb() in PHP.
 * fallback: the level the caller knew before the registry (a page cached without it).
 */
function efb_feature_required_efb(key, fallback) {
  var e = efb_feature_entry_efb(key);
  if (e && EFB_PLAN_RANK_EFB.hasOwnProperty(e.package)) return e.package;
  return EFB_PLAN_RANK_EFB.hasOwnProperty(fallback) ? fallback : 'pro';
}

/* Add-on flags as the page knows them now (efb_var.addons, patched after an install, and setting). */
function efb_addon_installed_efb(code) {
  if (typeof efb_var === 'undefined' || !efb_var) return false;
  var a = efb_var.addons && efb_var.addons[code] !== undefined ? efb_var.addons[code] : (efb_var.setting ? efb_var.setting[code] : undefined);
  return Number(a) === 1;
}

/**
 * Can the feature be used on plan pkg (default: the page's plan now)? Plan and add-on, the same
 * rule as efb_feature_available_efb($key, $package) in PHP.
 */
function efb_feature_available_efb(key, pkg, fallback) {
  var need = efb_feature_required_efb(key, fallback);
  var p = pkg === undefined || pkg === null ? efb_current_package_efb() : Number(pkg);
  if (efb_plan_rank_efb(p) < EFB_PLAN_RANK_EFB[need]) return false;
  var e = efb_feature_entry_efb(key);
  return !(e && e.addon) || efb_addon_installed_efb(e.addon);
}

/** The plan alone, without the add-on: is the plan the reason this is locked? */
function efb_feature_plan_ok_efb(key, pkg, fallback) {
  var p = pkg === undefined || pkg === null ? efb_current_package_efb() : Number(pkg);
  return efb_plan_rank_efb(p) >= EFB_PLAN_RANK_EFB[efb_feature_required_efb(key, fallback)];
}

/** The translated name of a feature: its label key in efb_var.text, the palette name of a field, or a readable key. */
function efb_feature_label_efb(key, label) {
  if (label) return String(label);
  var t = (typeof efb_var !== 'undefined' && efb_var && efb_var.text) ? efb_var.text : {};
  var e = efb_feature_entry_efb(key);
  var lk = e && e.label_key ? e.label_key : null;
  var parts = String(key || '').split(':');
  if (!lk && parts[0] === 'field') lk = parts[1];
  if (parts[0] === 'field' && typeof fields_efb !== 'undefined' && Array.isArray(fields_efb)) {
    for (var i = 0; i < fields_efb.length; i++) if (fields_efb[i].id === parts[1] && fields_efb[i].name) return String(fields_efb[i].name);
  }
  if (lk && typeof t[lk] === 'string' && t[lk] !== '' && t[lk].indexOf('%') === -1) return t[lk];
  var raw = parts.length > 1 ? parts.slice(1).join(':') : String(key || '');
  return raw.replace(/[_-]+/g, ' ').replace(/^./, function (c) { return c.toUpperCase(); });
}

/* ------------------------------------------------------------------ B: the upgrade dialog */
/*
 * efb_upgrade_prompt_efb({feature, required, label, limit, resume, origin, message, opener})
 *
 * One dialog for every lock (F18 §4). It names the feature and the plan that unlocks it, says
 * what that plan costs, and for Free Plus activates it in place: the credit-link disclosure sits
 * next to the button before the click, so no second dialog is needed. States (§4.2):
 *   S1 Free → Free Plus feature (activate in place)   S2 Free → Pro feature (buy Pro)
 *   S3 Free Plus → Pro feature                         S4a/S4b lapsed Pro licence (renew; or Free Plus)
 *   S5 the two-step limit (as S1)                      S6 a Free Plus limit (as S3)
 *   S7 a user who may not change the plan              S8 Free Plus is on, an add-on is still needed
 * Every text is escaped; a caller's label or message is never written as markup.
 * An agent (SitePilot / AutoPilot) may open it, never press its buttons: activation needs a
 * trusted click (event.isTrusted) on the button next to the disclosure.
 */

var efb_upg_state_efb = null; // the dialog on screen: {opts, view, busy, opener, onKey, observer}

function efb_upg_t_efb(key, fallback) {
  var t = (typeof efb_var !== 'undefined' && efb_var && efb_var.text) ? efb_var.text : {};
  var v = t[key];
  return typeof v === 'string' && v !== '' && v.indexOf('T:') !== 0 ? v : fallback;
}

function efb_upg_esc_efb(s) {
  return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

/* Latin product names keep their direction inside Persian or Arabic text. */
function efb_upg_bdi_efb(escaped) {
  return escaped.replace(/(Easy Form Builder|Free Plus|PayPal|Stripe|\bPro\b)/g, '<bdi>$1</bdi>');
}

/**
 * A translated sentence with its placeholders (%s, %d, %1$s, %2$s, %1$d …) filled. The sentence
 * and every argument are escaped; arguments are isolated in <bdi>.
 */
function efb_upg_fmt_efb(template, args) {
  args = args || [];
  var i = 0;
  var out = '';
  var re = /%(?:(\d)\$)?([sd])/g;
  var last = 0;
  var m;
  template = String(template);
  while ((m = re.exec(template)) !== null) {
    out += efb_upg_bdi_efb(efb_upg_esc_efb(template.slice(last, m.index)));
    var idx = m[1] ? Number(m[1]) - 1 : i++;
    var a = args[idx] === undefined ? '' : args[idx];
    if (m[2] === 'd' && typeof a === 'number') a = efb_upg_num_efb(a);
    out += '<bdi>' + efb_upg_esc_efb(a) + '</bdi>';
    last = re.lastIndex;
  }
  return out + efb_upg_bdi_efb(efb_upg_esc_efb(template.slice(last)));
}

function efb_upg_locale_efb() {
  var l = (typeof lan_name_emsFormBuilder !== 'undefined' && lan_name_emsFormBuilder) ? lan_name_emsFormBuilder
    : ((typeof efb_var !== 'undefined' && efb_var && efb_var.language) ? String(efb_var.language).replace('_', '-') : 'en');
  return l;
}
function efb_upg_num_efb(n) {
  try { return Number(n).toLocaleString(efb_upg_locale_efb()); } catch (e) { return String(n); }
}

/** "$27/year" in the site language. */
function efb_upg_price_efb() {
  var p = (typeof pro_price_efb !== 'undefined' && pro_price_efb) ? pro_price_efb : 27;
  return efb_upg_t_efb('priceyr', '$NN/year').replace('NN', efb_upg_num_efb(p));
}

/** May this user change the site's plan? efb_var.can_change_plan (PHP efb_can_change_plan_efb()). */
function efb_can_change_plan_efb() {
  if (typeof efb_var === 'undefined' || !efb_var || efb_var.can_change_plan === undefined || efb_var.can_change_plan === null) return true;
  var v = efb_var.can_change_plan;
  return v === true || v === 1 || v === '1' || v === 'true';
}

/* Links of the dialog. The purchase page is the one open_whiteStudio_efb('pro') opens. */
function efb_upg_link_efb(kind) {
  var fa = typeof efb_var !== 'undefined' && efb_var && efb_var.language === 'fa_IR';
  if (kind === 'pro' || kind === 'renew') {
    if (fa) return 'https://easyformbuilder.ir/#pricing';
    var sub = typeof lan_subdomain_wsteam_efb === 'function' ? lan_subdomain_wsteam_efb() : '';
    return 'https://' + sub + 'whitestudio.team/#pricing';
  }
  if (kind === 'licence') return 'admin.php?page=Emsfb&state=setting&tab=general';
  return '#';
}

/** The state of §4.2 for a request. */
function efb_upgrade_state_efb(opts, pkg) {
  var feature = opts.feature || 'generic';
  var need = efb_feature_required_efb(feature, opts.required || opts.fallback);
  if (opts.required && EFB_PLAN_RANK_EFB.hasOwnProperty(opts.required)) need = opts.required;
  var p = pkg === undefined ? efb_current_package_efb() : pkg;
  if (!efb_can_change_plan_efb()) return 'S7';
  var e = efb_feature_entry_efb(feature);
  var planOk = efb_plan_rank_efb(p) >= EFB_PLAN_RANK_EFB[need];
  if (planOk) return e && e.addon && !efb_addon_installed_efb(e.addon) ? 'S8' : 'open';
  if (p === 0) return need === 'pro' ? 'S4b' : 'S4a';
  if (need === 'free_plus') return feature === 'limit:steps' ? 'S5' : 'S1';
  if (feature.indexOf('limit:') === 0 && p === 3) return 'S6';
  return p === 3 ? 'S3' : 'S2';
}

function efb_upg_event_efb(action, extra) {
  var s = efb_upg_state_efb;
  var o = s ? s.opts : (extra && extra.opts) || {};
  var detail = { action: action, feature: o.feature || 'generic', required: s ? s.need : (o.required || null), origin: o.origin || null, state: s ? s.view : null };
  if (extra && extra.detail) for (var k in extra.detail) detail[k] = extra.detail[k];
  try { document.dispatchEvent(new CustomEvent('efb:upgrade-prompt', { detail: detail })); } catch (e) { /* no DOM */ }
}

/**
 * The gate every locked control uses: true when the feature can be used now (plan and add-on,
 * read live), else the upgrade dialog opens for it and the answer is false. opts as
 * efb_upgrade_prompt_efb(); opts.fallback is the level to assume on a page without the registry.
 */
function efb_gate_efb(key, opts) {
  opts = opts || {};
  if (efb_feature_available_efb(key, undefined, opts.fallback)) return true;
  efb_upgrade_prompt_efb(Object.assign({ feature: key }, opts));
  return false;
}

/* "Not now" three times for one feature in this session: the next click shows a small inline
   note with a link to the dialog instead of the dialog itself (§4.8). */
function efb_upg_dismiss_count_efb(feature, add) {
  var key = 'efb_upg_notnow_efb';
  var map = {};
  try { map = JSON.parse(sessionStorage.getItem(key) || '{}') || {}; } catch (e) { map = {}; }
  if (add) {
    map[feature] = (map[feature] || 0) + 1;
    try { sessionStorage.setItem(key, JSON.stringify(map)); } catch (e) { /* storage off */ }
  }
  return map[feature] || 0;
}

function efb_upg_inline_tip_efb(opts) {
  var opener = opts.opener && opts.opener.nodeType === 1 ? opts.opener : null;
  var old = document.getElementById('efbUpgTipEfb');
  if (old) old.remove();
  var tip = document.createElement('span');
  tip.id = 'efbUpgTipEfb';
  tip.className = 'efb efb-upg-tip';
  tip.setAttribute('role', 'status');
  var need = efb_feature_required_efb(opts.feature || 'generic', opts.required || opts.fallback);
  tip.textContent = need === 'free_plus' ? efb_upg_t_efb('upgTipFreePlus', 'Included in Free Plus (free)') : efb_upg_t_efb('upgTipPro', 'Pro feature');
  var more = document.createElement('button');
  more.type = 'button';
  more.className = 'efb efb-upg-tip__more';
  more.textContent = efb_upg_t_efb('upgSeeOptions', 'See options');
  more.addEventListener('click', function () { tip.remove(); efb_upgrade_prompt_efb(Object.assign({}, opts, { force: true })); });
  tip.appendChild(document.createTextNode(' '));
  tip.appendChild(more);
  if (opener && opener.parentNode) opener.parentNode.insertBefore(tip, opener.nextSibling); else document.body.appendChild(tip);
  setTimeout(function () { if (tip.parentNode) tip.remove(); }, 8000);
  return tip;
}

/**
 * Open the dialog for a locked feature. When the plan already unlocks it (a stale flag), the
 * caller's action runs at once instead. Returns {shown, state}.
 */
function efb_upgrade_prompt_efb(opts) {
  opts = Object.assign({}, opts || {});
  if (!opts.feature) opts.feature = 'generic';
  // What opened the dialog gets the focus back. Inline onclick callers pass `opener: this`; else
  // the element of the click in progress, else the focused element.
  if (!opts.opener && typeof window !== 'undefined' && window.event && window.event.target && window.event.target.closest) {
    opts.opener = window.event.target.closest('button, a, input, select, textarea, [tabindex], [onclick]');
  }
  if (!opts.opener && typeof document !== 'undefined' && document.activeElement && document.activeElement !== document.body) opts.opener = document.activeElement;
  var view = efb_upgrade_state_efb(opts);
  if (view === 'open') {
    if (opts.resume && typeof efb_run_resume_efb === 'function') efb_run_resume_efb(opts.resume, opts);
    return { shown: false, state: 'open' };
  }
  if (!opts.force && opts.feature !== 'generic' && efb_upg_dismiss_count_efb(opts.feature) >= 3) {
    efb_upg_inline_tip_efb(opts);
    return { shown: false, state: 'tip' };
  }
  var need = efb_feature_required_efb(opts.feature, opts.required || opts.fallback);
  if (opts.required && EFB_PLAN_RANK_EFB.hasOwnProperty(opts.required)) need = opts.required;
  efb_upg_state_efb = { opts: opts, view: view, need: need, busy: false, opener: opts.opener || null, done: false };
  efb_upg_render_efb(view);
  efb_upg_event_efb('shown');
  return { shown: true, state: view };
}

/* The name shown for the feature: the caller's label, else the registry's. */
function efb_upg_feature_name_efb(opts) {
  if (opts.label) return String(opts.label);
  if (!opts.feature || opts.feature === 'generic') return '';
  return efb_feature_label_efb(opts.feature);
}

/* Copy and actions of one view. Text is markup-safe (efb_upg_fmt_efb). */
function efb_upg_view_efb(view, opts, need) {
  var T = efb_upg_t_efb;
  var f = efb_upg_feature_name_efb(opts);
  var price = efb_upg_price_efb();
  var planName = need === 'pro' ? T('pro', 'Pro') : T('freePlus', 'Free Plus');
  var limit = opts.limit || {};
  var e = efb_feature_entry_efb(opts.feature);
  var v = { title: '', icon: 'bi-unlock', tone: 'efb-tone-brand', headline: '', text: '', note: '', disclosure: false, removesKey: false, actions: [] };
  var headFP = f ? efb_upg_fmt_efb(T('upgInFreePlus', '%s is included in Free Plus — free'), [f]) : efb_upg_fmt_efb(T('upgThisInFreePlus', 'This feature is included in Free Plus — free'));
  var headPro = f ? efb_upg_fmt_efb(T('upgIsPro', '%s is a Pro feature'), [f]) : efb_upg_fmt_efb(T('upgThisIsPro', 'This is a Pro feature'));
  var notNow = { id: 'notnow', kind: 'quiet', label: T('upgNotNow', 'Not now') };
  var buyPro = function (labelKey, fallback, primary) {
    return { id: 'buy', kind: primary ? 'gold' : 'ghost', href: efb_upg_link_efb('pro'), icon: 'bi-gem', label: efb_upg_fmt_efb(T(labelKey, fallback), [price]), html: true };
  };
  v.title = f ? efb_upg_fmt_efb(T('upgDialogTitle', 'Unlock %s'), [f]) : efb_upg_fmt_efb(T('upgDialogTitleGeneric', 'Unlock this feature'));
  switch (view) {
    case 'S1':
    case 'S5':
      if (view === 'S5') {
        var max = limit.max || (e && e.limits && e.limits.free) || 2;
        v.headline = efb_upg_fmt_efb(T('upgStepsTitle', 'Free allows up to %d steps per form'), [max]);
        v.text = efb_upg_fmt_efb(T('upgStepsBody', 'Free Plus and Pro allow unlimited steps.'));
      } else {
        v.headline = headFP;
        v.text = efb_upg_fmt_efb(T('upgFreePlusBody', 'Free Plus costs nothing. It also unlocks advanced fields, conditional logic, and Stripe and PayPal payments.'));
      }
      if (opts.message) v.text = efb_upg_esc_efb(opts.message) + ' ' + v.text;
      v.disclosure = true;
      v.actions = [{ id: 'activate', kind: 'primary', icon: 'bi-unlock', label: efb_upg_fmt_efb(T('upgActivateFreePlus', 'Activate Free Plus')), html: true, describedby: true },
        buyPro('upgGetProNoCredit', 'Get Pro — no credit · %s', false), notNow];
      break;
    case 'S2':
    case 'S3':
    case 'S6':
      v.icon = 'bi-gem';
      v.tone = 'efb-tone-gold';
      if (view === 'S6') {
        var lim = limit.max !== undefined ? limit.max : '';
        v.headline = efb_upg_fmt_efb(T('upgLimitTitle', 'Free Plus allows up to %1$d %2$s per form'), [lim, limit.unit_label || limit.unit || '']);
        v.text = efb_upg_fmt_efb(T('upgProBody', 'Pro includes every feature and add-on, shows no credit on your pages, and includes support.'));
      } else if (view === 'S3') {
        v.headline = headPro;
        v.text = f ? efb_upg_fmt_efb(T('upgOnFreePlus', 'Your site uses Free Plus. %s needs Pro.'), [f]) + ' ' + efb_upg_fmt_efb(T('upgProBody', 'Pro includes every feature and add-on, shows no credit on your pages, and includes support.'))
          : efb_upg_fmt_efb(T('upgProBody', 'Pro includes every feature and add-on, shows no credit on your pages, and includes support.'));
      } else {
        v.headline = headPro;
        v.text = efb_upg_fmt_efb(T('upgProBody', 'Pro includes every feature and add-on, shows no credit on your pages, and includes support.'));
        v.note = efb_upg_fmt_efb(T('upgNotInFreePlus', 'Free Plus does not include this.'));
      }
      if (opts.message) v.text = efb_upg_esc_efb(opts.message) + ' ' + v.text;
      v.actions = [buyPro('upgGetPro', 'Get Pro · %s', true),
        { id: 'licence', kind: 'quiet', href: efb_upg_link_efb('licence'), label: efb_upg_fmt_efb(T('upgHaveKey', 'I have a licence key')), html: true }, notNow];
      break;
    case 'S4a':
    case 'S4b':
      v.icon = 'bi-shield-exclamation';
      v.tone = 'efb-tone-warn';
      v.headline = efb_upg_fmt_efb(T('upgLapsedTitle', 'Your Pro licence is not active'));
      v.text = efb_upg_fmt_efb(T('upgLapsedBody', '%1$s needs %2$s. Renew Pro to get it back, or check your licence key.'), [f || T('upgThisFeature', 'This feature'), planName]);
      v.actions = [{ id: 'renew', kind: 'gold', href: efb_upg_link_efb('renew'), icon: 'bi-arrow-repeat', label: efb_upg_fmt_efb(T('upgRenewPro', 'Renew Pro')), html: true },
        { id: 'licence', kind: 'ghost', href: efb_upg_link_efb('licence'), label: efb_upg_fmt_efb(T('upgCheckLicence', 'Check licence')), html: true }];
      if (view === 'S4a') v.actions.push({ id: 'usefp', kind: 'quiet', label: efb_upg_fmt_efb(T('upgUseFreePlus', 'Use Free Plus instead')), html: true });
      v.actions.push(notNow);
      break;
    case 'S4a-confirm':
      v.icon = 'bi-unlock';
      v.headline = headFP;
      v.text = efb_upg_fmt_efb(T('upgFreePlusBody', 'Free Plus costs nothing. It also unlocks advanced fields, conditional logic, and Stripe and PayPal payments.'));
      v.disclosure = true;
      v.removesKey = true;
      v.actions = [{ id: 'activate', kind: 'primary', icon: 'bi-unlock', label: efb_upg_fmt_efb(T('upgSwitchFreePlus', 'Switch to Free Plus')), html: true, describedby: true, downgrade: true },
        { id: 'back', kind: 'quiet', label: efb_upg_fmt_efb(T('upgBack', 'Back')), html: true }];
      break;
    case 'S7':
      v.icon = need === 'pro' ? 'bi-gem' : 'bi-unlock';
      v.headline = need === 'pro' ? headPro : headFP;
      v.text = efb_upg_fmt_efb(T('upgAskAdmin', 'Only a site administrator can change the plan. Ask them to activate Free Plus (free) or Pro.'));
      v.actions = [{ id: 'close', kind: 'primary', label: efb_upg_fmt_efb(T('close', 'Close')), html: true }];
      break;
    case 'S8':
      var addon = e && e.addon ? e.addon : '';
      var addonName = addon ? efb_feature_label_efb('addon:' + addon) : f;
      v.icon = 'bi-puzzle';
      v.tone = 'efb-tone-success';
      v.headline = efb_upg_state_efb && efb_upg_state_efb.activated ? efb_upg_fmt_efb(T('upgFreePlusOn', 'Free Plus is on'))
        : (f ? efb_upg_fmt_efb(T('upgAddonNeeded', '%s needs an add-on'), [f]) : efb_upg_fmt_efb(T('upgFreePlusOn', 'Free Plus is on')));
      v.text = efb_upg_fmt_efb(T('upgAddonNext', 'One more step: install the %s add-on.'), [addonName]);
      v.actions = [{ id: 'install', kind: 'primary', icon: 'bi-download', label: efb_upg_fmt_efb(T('upgInstallAddon', 'Install %s'), [addonName]), html: true, addon: addon }, notNow];
      break;
  }
  return v;
}

function efb_upg_disclosure_html_efb(removesKey) {
  var T = efb_upg_t_efb;
  var credit = efb_upg_t_efb('upgCreditPreview', 'Powered by Easy Form Builder');
  return '<div class="efb efb-upg__disclosure" id="efbUpgDiscEfb">'
    + '<i class="efb bi-info-circle" aria-hidden="true"></i>'
    + '<div class="efb efb-upg__disclosure-body">'
    + '<p class="efb efb-upg__disclosure-text">' + efb_upg_fmt_efb(T('upgCreditDisclosure', 'With Free Plus, pages that show a form also show a small “Powered by Easy Form Builder” credit with a link. You can change your plan at any time in Settings.')) + '</p>'
    + (removesKey ? '<p class="efb efb-upg__disclosure-text efb-upg__removes-key">' + efb_upg_fmt_efb(T('upgRemovesKey', 'This also removes the stored licence key from this site. You can enter it again later.')) + '</p>' : '')
    + '<details class="efb efb-upg__details"><summary>' + efb_upg_fmt_efb(T('upgCreditWhat', 'What will appear?')) + '</summary>'
    + '<div class="efb efb-upg__preview" dir="ltr" aria-label="' + efb_upg_esc_efb(credit) + '"><span>' + efb_upg_esc_efb(credit) + '</span> <span class="efb efb-upg__preview-link">easyformbuilder</span></div>'
    + '<p class="efb efb-upg__meta">' + efb_upg_fmt_efb(T('upgCreditMeta', 'It also adds page metadata (structured data and an author link) that names Easy Form Builder.')) + '</p>'
    + '</details></div></div>';
}

function efb_upg_action_html_efb(a) {
  var label = a.html ? a.label : efb_upg_esc_efb(a.label);
  var icon = a.icon ? '<i class="efb ' + a.icon + '" aria-hidden="true"></i>' : '';
  var cls = 'efb efb-dlg-btn efb-dlg-btn--' + a.kind + ' efb-upg__btn efb-upg__btn--' + a.id;
  var data = ' data-efb-upg-action="' + a.id + '"' + (a.addon ? ' data-efb-addon="' + efb_upg_esc_efb(a.addon) + '"' : '') + (a.downgrade ? ' data-efb-downgrade="1"' : '');
  if (a.href) {
    var newTab = a.href.indexOf('http') === 0;
    return '<a class="' + cls + '"' + data + ' href="' + efb_upg_esc_efb(a.href) + '" target="_blank" rel="noopener noreferrer">' + icon + '<span>' + label + '</span>'
      + '<span class="efb efb-upg__sr">' + efb_upg_esc_efb(' ' + efb_upg_t_efb('upgNewTab', '(opens in a new tab)')) + '</span></a>';
  }
  return '<button type="button" class="' + cls + '"' + data + (a.describedby ? ' aria-describedby="efbUpgDiscEfb"' : '') + '>' + icon + '<span class="efb efb-upg__btn-label">' + label + '</span></button>';
}

/* Paint (or repaint in place) the dialog for a view. */
function efb_upg_render_efb(view) {
  var s = efb_upg_state_efb;
  if (!s) return;
  s.view = view;
  var v = efb_upg_view_efb(view, s.opts, s.need);
  s.copy = v;
  var body = '<div class="efb efb-upg" data-efb-upg-state="' + view + '" data-efb-feature="' + efb_upg_esc_efb(s.opts.feature) + '">'
    + '<div class="efb efb-dlg__badge efb-dlg__badge--square" aria-hidden="true"><i class="efb ' + v.icon + '"></i></div>'
    + '<h2 class="efb efb-dlg__headline efb-upg__headline" id="efbUpgHeadEfb" tabindex="-1">' + v.headline + '</h2>'
    + '<p class="efb efb-dlg__text efb-upg__text" id="efbUpgTextEfb">' + v.text + '</p>'
    + (v.note ? '<p class="efb efb-upg__note">' + v.note + '</p>' : '')
    + (v.disclosure ? efb_upg_disclosure_html_efb(v.removesKey) : '')
    + '<div class="efb efb-upg__alert" id="efbUpgAlertEfb" role="alert"></div>'
    + '</div>';
  var foot = v.actions.map(efb_upg_action_html_efb).join('');
  var onShown = function () {
    if (efb_upg_state_efb !== s) return;
    if (typeof efb_dlg_set_tone_efb === 'function') efb_dlg_set_tone_efb(v.tone);
    var sections = document.getElementById('settingModalEfb-sections');
    if (!sections) return;
    document.querySelectorAll('#save-result-foot-efb').forEach(function (el) { el.remove(); });
    var f = document.createElement('div');
    f.className = 'efb modal-footer efb-dlg__foot efb-upg__foot';
    f.id = 'save-result-foot-efb';
    f.innerHTML = foot;
    sections.appendChild(f);
    efb_upg_wire_efb();
  };
  var shell = document.getElementById('settingModalEfb');
  var mine = shell && shell.classList.contains('show') && s.wired;
  if (mine) {
    // A new view of the dialog already on screen: repaint in place, keep the shell and focus logic.
    document.getElementById('settingModalEfb-title').innerHTML = v.title;
    document.getElementById('settingModalEfb-body').innerHTML = body;
    onShown();
    var h = document.getElementById('efbUpgHeadEfb');
    if (h) h.focus();
    return;
  }
  if (typeof show_modal_efb !== 'function') return;
  var painted = show_modal_efb(body, v.title, 'efb ' + v.icon, 'proBpx', { onShown: onShown, flow: 'upgrade' });
  if (painted && typeof state_modal_show_efb === 'function') state_modal_show_efb(1);
}

/* ARIA, focus trap, Escape, focus return, button wiring. Runs once per paint. */
function efb_upg_wire_efb() {
  var s = efb_upg_state_efb;
  var shell = document.getElementById('settingModalEfb');
  if (!s || !shell) return;
  if (!s.wired) {
    s.wired = true;
    s.prevAria = { role: shell.getAttribute('role'), labelledby: shell.getAttribute('aria-labelledby'), describedby: shell.getAttribute('aria-describedby') };
    shell.setAttribute('role', 'dialog');
    shell.setAttribute('aria-modal', 'true');
    s.onKey = function (ev) { efb_upg_keydown_efb(ev); };
    document.addEventListener('keydown', s.onKey, true);
    if (typeof MutationObserver === 'function') {
      s.observer = new MutationObserver(function () { if (!shell.classList.contains('show')) efb_upg_closed_efb(); });
      s.observer.observe(shell, { attributes: true, attributeFilter: ['class'] });
    }
  }
  var dlg = document.getElementById('settingModalEfb_');
  if (dlg) dlg.classList.add('efb-upg-open');
  shell.setAttribute('aria-labelledby', 'efbUpgHeadEfb');
  shell.setAttribute('aria-describedby', 'efbUpgTextEfb');
  document.querySelectorAll('#save-result-foot-efb [data-efb-upg-action]').forEach(function (b) {
    b.addEventListener('click', function (ev) { efb_upg_click_efb(b.getAttribute('data-efb-upg-action'), b, ev); });
  });
  var h = document.getElementById('efbUpgHeadEfb');
  // Focus the headline, not the button: the disclosure is read before anything can be activated.
  if (h) setTimeout(function () { if (efb_upg_state_efb === s && !s.closed) h.focus(); }, 30);
}

function efb_upg_focusables_efb() {
  var shell = document.getElementById('settingModalEfb');
  if (!shell) return [];
  return Array.prototype.filter.call(shell.querySelectorAll('button, a[href], summary, input, select, textarea, [tabindex]:not([tabindex="-1"])'), function (el) {
    return !el.disabled && el.offsetParent !== null && el.getAttribute('aria-hidden') !== 'true';
  });
}

function efb_upg_keydown_efb(ev) {
  var s = efb_upg_state_efb;
  var shell = document.getElementById('settingModalEfb');
  if (!s || s.closed || !shell || !shell.classList.contains('show')) return;
  if (ev.key === 'Escape' || ev.key === 'Esc') {
    ev.preventDefault();
    ev.stopPropagation();
    efb_upg_close_efb('dismiss');
    return;
  }
  if (ev.key !== 'Tab') return;
  var items = efb_upg_focusables_efb();
  if (!items.length) return;
  var first = items[0];
  var last = items[items.length - 1];
  var inside = shell.contains(document.activeElement);
  if (ev.shiftKey && (document.activeElement === first || !inside || document.activeElement.id === 'efbUpgHeadEfb')) { ev.preventDefault(); last.focus(); }
  else if (!ev.shiftKey && (document.activeElement === last || !inside)) { ev.preventDefault(); first.focus(); }
}

/* Close the dialog (by its own buttons or Escape). The shell's own close paths (backdrop) end in
   efb_upg_closed_efb() through the observer. */
function efb_upg_close_efb(reason) {
  var s = efb_upg_state_efb;
  if (!s) return;
  s.closeReason = s.closeReason || reason;
  if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0);
  efb_upg_closed_efb();
}

function efb_upg_closed_efb() {
  var s = efb_upg_state_efb;
  if (!s || s.closed) return;
  s.closed = true;
  var shell = document.getElementById('settingModalEfb');
  if (s.onKey) document.removeEventListener('keydown', s.onKey, true);
  var dlg = document.getElementById('settingModalEfb_');
  if (dlg) dlg.classList.remove('efb-upg-open');
  if (s.observer) s.observer.disconnect();
  if (shell && s.prevAria) {
    ['role', 'labelledby', 'describedby'].forEach(function (k) {
      var attr = k === 'role' ? 'role' : 'aria-' + k;
      if (s.prevAria[k] === null || s.prevAria[k] === undefined) shell.removeAttribute(attr); else shell.setAttribute(attr, s.prevAria[k]);
    });
  }
  if (!s.closeReason || s.closeReason === 'dismiss') {
    s.closeReason = 'dismiss';
    if (!s.activated) {
      efb_upg_dismiss_count_efb(s.opts.feature, true);
      efb_upg_event_efb('dismiss');
    }
  }
  efb_upgrade_prompt_efb_last = s;
  if (efb_upg_state_efb === s) efb_upg_state_efb = null;
  // Focus goes back to what opened the dialog, unless the resumed action moved it on purpose.
  if (!s.focusMoved && s.opener && typeof s.opener.focus === 'function' && document.contains(s.opener)) {
    try { s.opener.focus(); } catch (e) { /* gone */ }
  }
}
var efb_upgrade_prompt_efb_last = null;

function efb_upg_alert_efb(html) {
  var a = document.getElementById('efbUpgAlertEfb');
  if (!a) return;
  a.innerHTML = html;
  a.classList.toggle('is-visible', !!html);
}

function efb_upg_busy_efb(on) {
  var s = efb_upg_state_efb;
  if (!s) return;
  s.busy = on;
  var btn = document.querySelector('#save-result-foot-efb [data-efb-upg-action="activate"]');
  document.querySelectorAll('#save-result-foot-efb [data-efb-upg-action]').forEach(function (b) {
    if (b === btn) return;
    if (b.tagName === 'A') { if (on) { b.setAttribute('aria-disabled', 'true'); b.tabIndex = -1; } else { b.removeAttribute('aria-disabled'); b.removeAttribute('tabindex'); } }
    else b.disabled = on;
  });
  if (!btn) return;
  var label = btn.querySelector('.efb-upg__btn-label');
  if (on) {
    btn.setAttribute('aria-busy', 'true');
    btn.disabled = true;
    btn.dataset.efbLabel = label ? label.innerHTML : '';
    if (label) label.innerHTML = '<span class="efb efb-upg__spinner" aria-hidden="true"></span>' + efb_upg_fmt_efb(efb_upg_t_efb('upgActivating', 'Activating…'));
  } else {
    btn.removeAttribute('aria-busy');
    btn.disabled = false;
    if (label && btn.dataset.efbLabel !== undefined) label.innerHTML = btn.dataset.efbLabel;
  }
}

function efb_upg_click_efb(action, btn, ev) {
  var s = efb_upg_state_efb;
  if (!s) return;
  if (btn.getAttribute('aria-disabled') === 'true') { if (ev) ev.preventDefault(); return; }
  switch (action) {
    case 'activate':
      // A person has to press this button: an agent's synthetic click (isTrusted false) is refused (§14).
      if (!ev || ev.isTrusted !== true) {
        efb_upg_alert_efb(efb_upg_fmt_efb(efb_upg_t_efb('upgNeedsClick', 'Free Plus can only be activated by a click on this button.')));
        efb_upg_event_efb('refused', { detail: { reason: 'untrusted_click' } });
        return;
      }
      efb_upg_activate_efb(btn.getAttribute('data-efb-downgrade') === '1');
      break;
    case 'buy':
    case 'renew':
    case 'licence':
      efb_upg_event_efb(action === 'licence' ? 'licence' : 'buy');
      break;
    case 'usefp':
      efb_upg_render_efb('S4a-confirm');
      break;
    case 'back':
      efb_upg_render_efb('S4a');
      break;
    case 'install':
      var code = btn.getAttribute('data-efb-addon');
      efb_upg_event_efb('install', { detail: { addon: code } });
      if (code && typeof efb_save_resume_efb === 'function') efb_save_resume_efb(s.opts);
      s.closeReason = 'install';
      efb_upg_close_efb('install');
      if (code && typeof actionSendAddons_efb === 'function') actionSendAddons_efb(code);
      break;
    case 'notnow':
    case 'close':
      efb_upg_close_efb('dismiss');
      break;
  }
}

/* The Free Plus request (§4.5, §8): source upgrade_prompt, credit_ack, the feature for the log. */
function efb_upg_activate_efb(downgradeConfirmed) {
  var s = efb_upg_state_efb;
  if (!s || s.busy) return;
  efb_upg_alert_efb('');
  efb_upg_busy_efb(true);
  efb_upg_event_efb('activate');
  var url = (typeof efb_var !== 'undefined' && efb_var && efb_var.ajax_url) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
  var nonce = (typeof _efb_nonce_ !== 'undefined' && _efb_nonce_) || (typeof efb_var !== 'undefined' && efb_var ? efb_var.nonce : '');
  var data = {
    selected_plan: 'free_plus',
    plan_data: { plan_name: 'Free Plus Plan', show_credit: true },
    timestamp: Date.now(),
    downgrade_confirmed: downgradeConfirmed === true,
    source: 'upgrade_prompt',
    feature: String(s.opts.feature || '').slice(0, 64),
    credit_ack: true
  };
  var done = function (ok, payload, status) { efb_upg_activation_result_efb(s, ok, payload, status); };
  if (typeof jQuery === 'undefined' || !jQuery.ajax) { done(false, null, 0); return; }
  jQuery.ajax({ url: url, type: 'POST', dataType: 'json', data: { action: 'efb_save_plan_selection', plan_data: JSON.stringify(data), nonce: nonce } })
    .done(function (res) { done(!!(res && res.success && res.data && Number(res.data.package_type) === 3), res && res.data ? res.data : null, 200); })
    .fail(function (xhr) { done(false, xhr && xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : null, xhr ? xhr.status : 0); });
}

function efb_upg_activation_result_efb(s, ok, payload, status) {
  var T = efb_upg_t_efb;
  var onScreen = efb_upg_state_efb === s && !s.closed;
  if (ok) {
    s.activated = true;
    if (payload && payload.activation_code_removed && typeof efb_var_patch_efb === 'function') efb_var_patch_efb(null, { activeCode: '' });
    s.planChanged = Promise.resolve(efb_on_plan_changed_efb(3, { source: 'upgrade_prompt', feature: s.opts.feature, resume: s.opts.resume, response: payload }));
    efb_upg_event_efb('activated', { detail: { package_type: 3 } });
    var e = efb_feature_entry_efb(s.opts.feature);
    if (e && e.addon && !efb_addon_installed_efb(e.addon)) {
      // S8: Free Plus is on; the add-on is the one step left. Shown in the same dialog.
      if (onScreen) { efb_upg_render_efb('S8'); return; }
    }
    var name = efb_upg_feature_name_efb(s.opts);
    s.closeReason = 'activated';
    if (onScreen) efb_upg_close_efb('activated');
    var toast = name ? T('upgReadyToast', 'Free Plus is on. %s is ready.').replace('%s', name) : T('upgFreePlusOn', 'Free Plus is on');
    if (typeof show_success_notification_efb === 'function') show_success_notification_efb(toast);
    if (s.opts.resume && typeof efb_run_resume_efb === 'function') {
      s.planChanged.then(function () { return efb_run_resume_efb(s.opts.resume, s.opts); }).then(function (target) {
        if (target && target.nodeType === 1) { s.focusMoved = true; try { target.focus(); } catch (er) { /* fine */ } }
      });
    }
    return;
  }
  if (!onScreen) {
    if (status === 409 || status === 403) return;
    var msg = payload && payload.message ? String(payload.message) : '';
    if (typeof alert_message_efb === 'function') alert_message_efb('', efb_upg_esc_efb(T('upgFailed', 'Free Plus could not be activated. %s').replace('%s', msg)), 10, 'danger');
    return;
  }
  efb_upg_busy_efb(false);
  if (status === 409 && payload && payload.code === 'downgrade_confirmation_required') {
    // A stored licence key would be removed: confirm in place (S4a), no second dialog.
    efb_upg_render_efb('S4a-confirm');
    return;
  }
  efb_upg_event_efb('error', { detail: { status: status } });
  if (status === 403) {
    efb_upg_alert_efb(efb_upg_fmt_efb(T('upgSessionExpired', 'Your session has expired. Save your work and reload the page.')));
    return;
  }
  var m = payload && (payload.message || payload.m) ? String(payload.message || payload.m) : '';
  efb_upg_alert_efb(efb_upg_fmt_efb(T('upgFailed', 'Free Plus could not be activated. %s'), [m])
    + ' <button type="button" class="efb efb-dlg-btn efb-dlg-btn--ghost efb-upg__retry" data-efb-upg-retry="1">' + efb_upg_fmt_efb(T('upgTryAgain', 'Try again')) + '</button>');
  var retry = document.querySelector('#efbUpgAlertEfb [data-efb-upg-retry]');
  if (retry) retry.addEventListener('click', function (ev) {
    if (!ev.isTrusted) return;
    efb_upg_activate_efb(s.view === 'S4a-confirm');
  });
}

/* ------------------------------------------------------------------ D: in-page plan change */
/*
 * efb_on_plan_changed_efb(newType, ctx): the page reaches the state a reload would give, without
 * the reload. The globals first (efb_var.pro, efb_var.setting.package_type, setting_emsFormBuilder,
 * valueJson_ws_setting, pro_efb through efb_apply_package_type_efb; ajax_object_efm.pro and
 * pro_ws_efb here), then every subscriber redraws what it locked, in order, then the plan badge
 * and one efb:plan-changed event. Returns a Promise resolved when the redraws are done (the
 * canvas draws on a timer), so the caller's action runs on the new screen.
 * Add-ons (Conditional Logic) register with efb_plan_subscribe_efb() or listen to the event.
 */
var EFB_PLAN_SUBSCRIBERS_EFB = [];

function efb_plan_subscribe_efb(name, fn) {
  if (typeof fn !== 'function') return;
  EFB_PLAN_SUBSCRIBERS_EFB = EFB_PLAN_SUBSCRIBERS_EFB.filter(function (s) { return s[0] !== name; });
  EFB_PLAN_SUBSCRIBERS_EFB.push([String(name), fn]);
}

/* The two plan flags efb_apply_package_type_efb() does not know: the panel's localized copy and
   the panel's own boolean. */
function efb_patch_legacy_plan_flags_efb(newType) {
  var isPro = newType === 1 || newType === 3;
  try { if (typeof ajax_object_efm === 'object' && ajax_object_efm) ajax_object_efm.pro = isPro ? '1' : '0'; } catch (e) { /* frozen */ }
  try { if (typeof pro_ws_efb !== 'undefined') pro_ws_efb = isPro; } catch (e) { /* not on this screen */ }
}

function efb_on_plan_changed_efb(newType, ctx) {
  newType = Number(newType);
  ctx = ctx || {};
  if ([0, 1, 2, 3].indexOf(newType) === -1) return Promise.resolve(false);
  var prev = efb_current_package_efb();
  if (typeof efb_apply_package_type_efb === 'function') efb_apply_package_type_efb(newType);
  efb_patch_legacy_plan_flags_efb(newType);
  var chain = Promise.resolve();
  EFB_PLAN_SUBSCRIBERS_EFB.slice().forEach(function (sub) {
    chain = chain.then(function () {
      try { return sub[1](newType, prev, ctx); } catch (e) {
        ctx.needsReload = true;
        if (typeof console !== 'undefined') console.error('efb plan subscriber', sub[0], e);
      }
    }).catch(function (e) {
      ctx.needsReload = true;
      if (typeof console !== 'undefined') console.error('efb plan subscriber', sub[0], e);
    });
  });
  return chain.then(function () {
    if (typeof updatePlanBadge_efb === 'function') { try { updatePlanBadge_efb(); } catch (e) { /* no badge here */ } }
    try {
      document.dispatchEvent(new CustomEvent('efb:plan-changed', { detail: { package_type: newType, previous: prev, plan: efb_plan_name_efb(newType), source: ctx.source || null, feature: ctx.feature || null } }));
    } catch (e) { /* no DOM */ }
    if (ctx.needsReload) efb_plan_reload_efb(ctx);
    return true;
  });
}

/* A redraw failed: keep the work, then reload once (§6). The caller's action survives the reload. */
function efb_plan_reload_efb(ctx) {
  try {
    if (typeof valj_efb !== 'undefined' && Array.isArray(valj_efb) && valj_efb.length > 1) sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb));
    if (ctx && ctx.resume && typeof efb_save_resume_efb === 'function') efb_save_resume_efb({ feature: ctx.feature, resume: ctx.resume });
  } catch (e) { return; } // storage failed: no reload, nothing is lost
  if (typeof show_info_notification_efb === 'function') show_info_notification_efb(efb_upg_t_efb('upgReloading', 'To finish, the page reloads now.'));
  setTimeout(function () { location.reload(); }, 1200);
}

/* Remove the lock markers of features the plan now opens: elements marked
   data-efb-lock="<feature>" are removed (data-efb-unlock="remove", gems) or lose the attributes
   named in data-efb-unlock-attrs (onclick/onchange that opened the dialog). */
function efb_unlock_dom_efb(root) {
  var n = 0;
  (root || document).querySelectorAll('[data-efb-lock]').forEach(function (el) {
    var key = el.getAttribute('data-efb-lock');
    if (!efb_feature_available_efb(key)) return;
    var mode = el.getAttribute('data-efb-unlock') || 'remove';
    if (mode === 'remove') { el.remove(); n++; return; }
    (el.getAttribute('data-efb-unlock-attrs') || '').split(',').forEach(function (a) { a = a.trim(); if (a) el.removeAttribute(a); });
    el.classList.remove('efb-locked', 'efb-attach-disabled', 'is-locked');
    if (el.hasAttribute('readonly') && el.getAttribute('data-efb-unlock-readonly') === '1') el.removeAttribute('readonly');
    el.removeAttribute('data-efb-lock');
    n++;
  });
  return n;
}

/* Built-in subscribers. Each one works only when its screen is in the page. */
efb_plan_subscribe_efb('palette', function () {
  var box = document.getElementById('efbPaletteTilesEfb');
  if (!box || typeof efb_palette_tiles_html_efb !== 'function') return;
  box.innerHTML = efb_palette_tiles_html_efb();
  if (typeof efb_bind_palette_tiles_efb === 'function') efb_bind_palette_tiles_efb(box);
  var active = document.querySelector('#listCatEfb .efb.cat.active');
  var cat = active ? ['all', 'basic', 'payment', 'advance'].filter(function (c) { return active.classList.contains(c); })[0] : null;
  if (cat && cat !== 'all' && typeof change_visible_el_efb === 'function') change_visible_el_efb(cat);
});

efb_plan_subscribe_efb('canvas', function () {
  var zone = document.getElementById('dropZoneEFB');
  if (!zone || typeof editFormEfb !== 'function' || typeof valj_efb === 'undefined' || !Array.isArray(valj_efb) || valj_efb.length < 2) return;
  // Only a canvas whose locks change needs the redraw: it shows a lock now (the Pro badge over a
  // field), or the plan went down and a field of the form is no longer included.
  var shown = !!zone.querySelector('.btn-pro-efb, [data-efb-lock]');
  var lockedNow = typeof efb_field_is_pro_efb === 'function' && typeof pro_efb !== 'undefined' && pro_efb === false
    && valj_efb.some(function (r, i) { return i > 0 && r && efb_field_is_pro_efb(r); });
  if (!shown && !lockedNow) return;
  var scroller = document.scrollingElement || document.documentElement;
  var y = scroller ? scroller.scrollTop : 0;
  // editFormEfb() reads sessionStorage first: write the live form there or it draws an old copy.
  sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb));
  return Promise.resolve(editFormEfb()).then(function () { if (scroller) scroller.scrollTop = y; });
});

efb_plan_subscribe_efb('settings_panel', function () {
  var side = document.getElementById('sideBoxEfb');
  if (!side || !side.classList.contains('show')) return;
  if (window.efbBuilderApi && typeof window.efbBuilderApi.refreshSettingsPanel === 'function') { window.efbBuilderApi.refreshSettingsPanel(); return; }
  var id = window.efbOpenSettingIdEfb;
  if (id && typeof show_setting_window_efb === 'function') show_setting_window_efb(id, { refresh: true });
});

efb_plan_subscribe_efb('templates', function () {
  if (document.getElementById('findCardFormEFB') && document.getElementById('listFormCardsEFB') && typeof FunfindCardFormEFB === 'function') FunfindCardFormEFB();
});

efb_plan_subscribe_efb('addons', function () {
  if (document.getElementById('findCardAddonEFB') && document.getElementById('listFormCardsEFB') && typeof FunfindCardAddonEFB === 'function') FunfindCardAddonEFB();
});

efb_plan_subscribe_efb('locks', function () { efb_unlock_dom_efb(document); });

efb_plan_subscribe_efb('reply_box', function () {
  if (typeof EfbResponseViewer !== 'undefined' && EfbResponseViewer && typeof EfbResponseViewer.refreshAttachButton === 'function') EfbResponseViewer.refreshAttachButton();
});

/* ------------------------------------------------------------------ F: the person's action resumes */
/*
 * resume = {kind, …} says what the click wanted (§4.6):
 *   add_field {id}   the palette id: the field is added, focused and scrolled into view
 *   add_step         a step is added
 *   toggle {id}      that switch is pressed (on the redrawn panel)
 *   focus {id}       that control gets the focus (a typed value stays)
 *   callback {name, args}  a function registered by name in EFB_RESUME_FNS_EFB (never a string to run)
 *   none             nothing; the focus goes back to the opener
 * efb_run_resume_efb() returns a Promise of the element that should take the focus (or null).
 */
var EFB_RESUME_FNS_EFB = {
  create_template: function (id) { if (typeof create_form_by_type_emsfb === 'function') { form_type_emsFormBuilder = id; create_form_by_type_emsfb(id, 'npreview'); } },
  bulk_read: function () { if (typeof event_selected_row_emsFormBuilder === 'function') event_selected_row_emsFormBuilder('read'); },
  delete_response: function (msg, track) { if (typeof emsFormBuilder_delete === 'function') emsFormBuilder_delete(msg, 'message', track); },
  open_color_modal: function () { if (typeof efb_open_color_modal === 'function') efb_open_color_modal(); },
  save_form: function (stated) { if (typeof saveFormEfb === 'function') saveFormEfb(stated === undefined || stated === null ? 1 : stated); }
};

/** Add-ons register their own resumable actions by name. */
function efb_resume_register_efb(name, fn) {
  if (typeof fn === 'function' && /^[A-Za-z0-9_]+$/.test(String(name))) EFB_RESUME_FNS_EFB[name] = fn;
}

function efb_resume_wait_efb(find, ms) {
  return new Promise(function (resolve) {
    var t0 = Date.now();
    (function poll() {
      var el = null;
      try { el = find(); } catch (e) { el = null; }
      if (el || Date.now() - t0 > (ms || 2500)) { resolve(el || null); return; }
      setTimeout(poll, 60);
    })();
  });
}

function efb_resume_focusable_efb(el) {
  if (!el) return null;
  if (!el.matches('a[href], button, input, select, textarea, [tabindex]')) el.setAttribute('tabindex', '-1');
  try { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) { /* old browser */ }
  return el;
}

function efb_run_resume_efb(resume, opts) {
  resume = resume || {};
  var byId = function (id) { return id ? document.getElementById(String(id)) : null; };
  switch (resume.kind) {
    case 'add_field':
    case 'add_step': {
      if (typeof fun_efb_add_el !== 'function' || typeof valj_efb === 'undefined' || !document.getElementById('dropZoneEFB')) return Promise.resolve(null);
      var before = {};
      valj_efb.forEach(function (r) { if (r && r.id_ !== undefined) before[r.id_] = 1; });
      var paletteId = resume.kind === 'add_step' ? 'steps' : String(resume.id || '');
      if (!/^[A-Za-z0-9_]+$/.test(paletteId)) return Promise.resolve(null);
      fun_efb_add_el(paletteId);
      return efb_resume_wait_efb(function () {
        var added = valj_efb.filter(function (r) { return r && r.id_ !== undefined && !before[r.id_] && r.type !== 'option'; });
        if (!added.length) return null;
        var row = added[0];
        return document.querySelector('#dropZoneEFB [data-id="' + String(row.dataId || row.id_).replace(/"/g, '') + '"]') || byId(row.id_);
      }).then(efb_resume_focusable_efb);
    }
    case 'toggle': {
      // The panel that holds the switch is redrawn after the plan change (on its own timers): press
      // the switch once that has settled, then hand back the node that is on screen by then.
      var later = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
      return later(350).then(function () { return efb_resume_wait_efb(function () { return byId(resume.id); }); })
        .then(function (el) { if (!el) return null; el.click(); return later(250).then(function () { return byId(resume.id) || el; }); });
    }
    case 'focus':
      return efb_resume_wait_efb(function () { var el = byId(resume.id); return el && !el.disabled ? el : null; }).then(efb_resume_focusable_efb);
    case 'callback': {
      var fn = Object.prototype.hasOwnProperty.call(EFB_RESUME_FNS_EFB, resume.name) ? EFB_RESUME_FNS_EFB[resume.name] : null;
      if (fn) { try { fn.apply(null, Array.isArray(resume.args) ? resume.args : []); } catch (e) { if (typeof console !== 'undefined') console.error('efb resume', resume.name, e); } }
      return Promise.resolve(null);
    }
    default:
      return Promise.resolve(null);
  }
}

/* Across a reload (an add-on install reloads the page): the action is kept for 5 minutes. */
var EFB_RESUME_KEY_EFB = 'efb_resume_efb';
var EFB_RESUME_TTL_EFB = 5 * 60 * 1000;

function efb_save_resume_efb(opts) {
  if (!opts || !opts.resume) return false;
  try {
    if (typeof valj_efb !== 'undefined' && Array.isArray(valj_efb) && valj_efb.length > 1) sessionStorage.setItem('valj_efb', JSON.stringify(valj_efb));
    sessionStorage.setItem(EFB_RESUME_KEY_EFB, JSON.stringify({ feature: String(opts.feature || ''), resume: opts.resume, at: Date.now() }));
    return true;
  } catch (e) { return false; }
}

/**
 * After a load (or once the builder canvas is drawn): run a kept action once, if it is younger than
 * five minutes and the plan (and add-on) now opens its feature. The key is removed before running,
 * so the action can never run twice. Returns the Promise of efb_run_resume_efb, or null.
 */
function efb_resume_after_load_efb() {
  var raw = null;
  try { raw = sessionStorage.getItem(EFB_RESUME_KEY_EFB); } catch (e) { return null; }
  if (!raw) return null;
  var saved = null;
  try { saved = JSON.parse(raw); } catch (e) { saved = null; }
  if (!saved || !saved.resume || !(Date.now() - Number(saved.at) < EFB_RESUME_TTL_EFB)) {
    try { sessionStorage.removeItem(EFB_RESUME_KEY_EFB); } catch (e) { /* storage off */ }
    return null;
  }
  // A field or step needs the builder canvas; wait for it (the draft may be reopened later).
  if ((saved.resume.kind === 'add_field' || saved.resume.kind === 'add_step') && !(document.querySelector('#dropZoneEFB') && typeof valj_efb !== 'undefined' && valj_efb.length > 1)) return null;
  if (saved.feature && efb_feature_entry_efb(saved.feature) && !efb_feature_available_efb(saved.feature)) return null;
  try { sessionStorage.removeItem(EFB_RESUME_KEY_EFB); } catch (e) { return null; }
  return efb_run_resume_efb(saved.resume, saved).then(function (el) { if (el && el.focus) { try { el.focus(); } catch (e) { /* fine */ } } return el; });
}

if (typeof document !== 'undefined' && document.addEventListener) {
  document.addEventListener('efb:canvas-rendered', function () { efb_resume_after_load_efb(); });
  document.addEventListener('DOMContentLoaded', function () { setTimeout(efb_resume_after_load_efb, 800); });
}

/* ------------------------------------------------------------------ H: ?efb_upgrade=<feature> */
/*
 * The administrator's note on a locked public field links to
 * admin.php?page=Emsfb_create&efb_upgrade=field:<type>; SitePilot sends the admin to the same URL
 * with &efb_origin=sitepilot. The dialog opens for that feature and waits for a person's click: the
 * link itself never changes the plan. The parameters are removed from the address so a reload
 * does not ask again. Only registry keys are accepted.
 */
/* The query string as the page was opened. On the Create screen admin-efb.js rewrites the address
   to ?page=Emsfb_create when jQuery is ready, before the timer below runs, so efb_upgrade was gone
   and the link from the public page (or SitePilot) opened nothing. Read once. */
var efb_upgrade_boot_search_efb = (typeof location !== 'undefined' && location.search) ? String(location.search) : '';

function efb_upgrade_from_url_efb() {
  var params;
  try { params = new URLSearchParams(location.search); } catch (e) { return null; }
  var feature = params.get('efb_upgrade');
  var originParam = params.get('efb_origin');
  if (!feature && efb_upgrade_boot_search_efb) {
    try {
      var boot = new URLSearchParams(efb_upgrade_boot_search_efb);
      feature = boot.get('efb_upgrade');
      originParam = boot.get('efb_origin');
    } catch (e) { /* fine */ }
  }
  efb_upgrade_boot_search_efb = '';
  if (!feature) return null;
  var origin = originParam === 'sitepilot' ? 'sitepilot' : 'public_page';
  params.delete('efb_upgrade');
  params.delete('efb_origin');
  try { history.replaceState(history.state, '', location.pathname + (params.toString() ? '?' + params.toString() : '') + location.hash); } catch (e) { /* fine */ }
  if (!/^(field|setting|addon|limit|template|form):[A-Za-z0-9_]{1,60}$/.test(feature) || !efb_feature_entry_efb(feature)) return null;
  return efb_upgrade_prompt_efb({ feature: feature, origin: origin });
}

if (typeof document !== 'undefined' && document.addEventListener) {
  // After the screen has drawn (the shell, the builder or the templates list).
  document.addEventListener('DOMContentLoaded', function () { setTimeout(efb_upgrade_from_url_efb, 1200); });
}
