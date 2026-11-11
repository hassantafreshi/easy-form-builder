/**
 * Plan gate and upgrade prompt (F18).
 *
 * One registry decides which plan unlocks what: efb_var.capabilities, built by
 * \Emsfb\Builder_Api::capabilities() in PHP (AutoPilot B3). This file only reads it - it keeps
 * no plan table of its own - and adds, on top of it:
 *
 *   A  read-only plan helpers (efb_current_package_efb, efb_feature_required_efb, ...);
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
