/*!
 * Easy Form Builder - field rules of text-like fields (F03, docs/feature-gaps/F03-text-validation.fa.md).
 * ES2017, no build step, no dependencies. Works in browsers (window.EfbRules) and Node (module.exports).
 * Every function here has a twin in includes/class-Emsfb-field-rules.php (\Emsfb\Field_Rules); both are
 * tested against the same vectors (tests/fixtures/*-vectors.json, tests/test-field-rules-*.js|php).
 * The browser check is a convenience: the server runs every rule again on submit.
 */
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) { module.exports = api; } else { root.EfbRules = api; }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var KEYS = ['unique', 'unique_scope', 'wmin', 'wmax', 'counter', 'counter_style', 'pattern_b64', 'pattern_case', 'pattern_ascii',
    'email_domain_mode', 'email_domains', 'readonly', 'prefix', 'suffix', 'message_place',
    'pw_confirm', 'pw_meter', 'pw_toggle', 'pw_strength_min', 'pw_confirm_label',
    'msg_format', 'msg_minlen', 'msg_maxlen', 'msg_words', 'msg_pattern', 'msg_unique', 'msg_domain', 'msg_mismatch', 'msg_strength'];
  var MSG_RULES = ['format', 'minlen', 'maxlen', 'words', 'pattern', 'unique', 'domain', 'mismatch', 'strength'];
  var CODES = ['required', 'format', 'minlen', 'maxlen', 'words', 'pattern', 'domain', 'mismatch', 'strength', 'unique'];
  var GATED = ['unique', 'pattern_b64', 'email_domain_mode', 'pw_strength_min'];
  var TYPES = {
    unique: ['text', 'email', 'tel', 'mobile', 'number', 'url', 'postalcode', 'address_line', 'firstName', 'lastName'],
    length: ['text', 'textarea', 'email', 'url', 'tel', 'password', 'postalcode', 'address_line', 'firstName', 'lastName'],
    words: ['text', 'textarea', 'address_line', 'firstName', 'lastName'],
    counter: ['text', 'textarea', 'address_line', 'firstName', 'lastName'],
    pattern: ['text', 'textarea', 'tel', 'email', 'url', 'postalcode', 'address_line', 'firstName', 'lastName'],
    domain: ['email'],
    readonly: ['text', 'textarea', 'email', 'tel', 'url', 'number', 'postalcode', 'address_line', 'firstName', 'lastName'],
    affix: ['text', 'number', 'email', 'url', 'tel', 'postalcode', 'address_line', 'firstName', 'lastName'],
    help: ['text', 'textarea', 'email', 'tel', 'url', 'number', 'password', 'postalcode', 'address_line', 'firstName', 'lastName', 'mobile', 'pdate', 'ardate', 'prcfld'],
    password: ['password']
  };
  var DEFAULTS = {
    tvUnique_: 'This value has already been used.',
    tvMinLen_: '%1$s needs at least %2$s characters.',
    tvMaxLen_: '%1$s can have at most %2$s characters.',
    tvMinWords_: '%1$s needs at least %2$s words.',
    tvMaxWords_: '%1$s can have at most %2$s words.',
    tvPattern_: 'The %s field is not in the expected format.',
    tvDomAllow_: 'Please use an email address from an allowed domain.',
    tvDomDeny_: 'This email domain is not accepted.',
    tvPwMismatch_: 'The two passwords do not match.',
    tvPwWeak_: 'Please choose a stronger password.'
  };

  // #region keys
  function on(f, k, dflt) {
    if (!f || !Object.prototype.hasOwnProperty.call(f, k)) { return !!dflt; }
    var v = f[k];
    return v === 1 || v === '1' || v === true || v === 'true';
  }
  function typeOf(f) { return f && typeof f.type === 'string' ? f.type : ''; }
  function typeOk(f, rule) { return !!TYPES[rule] && TYPES[rule].indexOf(typeOf(f)) !== -1; }
  function int(f, k) {
    var n = f && f[k] !== undefined && f[k] !== null && f[k] !== '' ? parseInt(String(f[k]), 10) : 0;
    return isFinite(n) && n > 0 ? n : 0;
  }
  function hasMsg(f, rule) {
    var v = f ? f['msg_' + rule] : undefined;
    return typeof v === 'string' && v.trim() !== '';
  }
  /** The rules the browser checks for this field (plan: ctx.paid). */
  function activeRules(f, ctx) {
    var out = [];
    if (!f) { return out; }
    if (typeOk(f, 'length') && (hasMsg(f, 'minlen') || hasMsg(f, 'maxlen')) && (int(f, 'milen') > 0 || int(f, 'mlen') > 0)) { out.push('length'); }
    return out;
  }
  function hasRules(f, ctx) { return activeRules(f, ctx).length > 0; }
  /** Plain text of a message or label: no tags, one line, at most max code points (Field_Rules::plain). */
  function plain(v, max) {
    if (v === null || v === undefined || typeof v === 'object') { return ''; }
    // " < > never: the builder's save transport and the server's text rules do not keep them.
    var s = String(v).replace(/<[^>]*>?/g, '').replace(/["<>]/g, '').replace(/[\r\n\t]+/g, ' ').replace(/ {2,}/g, ' ').trim();
    return Array.from(s).slice(0, max).join('');
  }
  /** The stored value of one F03 key, as the server keeps it (twin of Field_Rules::sanitize_key). */
  function sanitizeKey(k, v) {
    k = String(k);
    if (k.indexOf('msg_') === 0) { return plain(v, 200); }
    return plain(v, 200);
  }
  // #endregion keys

  // #region counting
  /** '@n#' (the newline token of textarea values) and CR LF back to one newline. */
  function valueText(v) {
    return String(v === null || v === undefined ? '' : v).replace(/\r\n|\r|@n#/g, '\n');
  }
  /** Code points (Array.from(s).length = mb_strlen). */
  function countChars(v) { return Array.from(valueText(v)).length; }
  // #endregion counting

  // #region messages
  function fill(t, name, n) {
    return String(t).split('%1$s').join(name).split('%2$s').join(n === undefined || n === null ? '' : String(n)).split('%s').join(name);
  }
  /**
   * The text of a failed rule: the owner's msg_<rule> (custom: true; write it with textContent), else
   * the translated default (texts = efb_var.text) or English. %1$s / %s = the label, %2$s = n.
   */
  function message(f, rule, defaultKey, texts, n) {
    var name = f && typeof f.name === 'string' ? f.name : '';
    if (hasMsg(f, rule)) { return { text: fill(f['msg_' + rule], name, n), custom: true }; }
    var t = texts && typeof texts[defaultKey] === 'string' && texts[defaultKey] !== '' ? texts[defaultKey] : (DEFAULTS[defaultKey] || '');
    return { text: fill(t, name, n), custom: false };
  }
  // #endregion messages

  // #region check
  /**
   * The first failing rule of a value, in the server's order (§4.1), or null.
   * Returns {code, rule, key, n}; message(f, rule, key, texts, n) gives its text.
   * ctx: {paid, confirm (second password), emails, formName}. An empty value runs no rule.
   */
  function firstError(f, v, ctx) {
    ctx = ctx || {};
    var s = valueText(v);
    if (s.trim() === '') { return null; }
    var rules = activeRules(f, ctx);
    if (rules.indexOf('length') !== -1) {
      var n = countChars(s);
      var mi = int(f, 'milen'), mx = int(f, 'mlen');
      if (mi > 0 && n < mi) { return { code: 'minlen', rule: 'minlen', key: 'tvMinLen_', n: mi }; }
      if (mx > 0 && n > mx) { return { code: 'maxlen', rule: 'maxlen', key: 'tvMaxLen_', n: mx }; }
    }
    return null;
  }
  // #endregion check

  return {
    KEYS: KEYS, MSG_RULES: MSG_RULES, CODES: CODES, GATED: GATED, TYPES: TYPES, DEFAULTS: DEFAULTS,
    on: on, typeOk: typeOk, activeRules: activeRules, hasRules: hasRules, plain: plain, sanitizeKey: sanitizeKey,
    valueText: valueText, countChars: countChars,
    message: message, firstError: firstError
  };
});
