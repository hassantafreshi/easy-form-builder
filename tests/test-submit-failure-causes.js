/**
 * Unit checks for 4.3.1 submit-failure handling in public/assets/js/core-efb.js
 * and the admin Error Monitor card in includes/class-Emsfb-formbuilder.php:
 *  - efb_failure_cause_efb names the cause only from what the response proves;
 *  - efb_visitor_text_efb never mentions jQuery and fills the HTTP status;
 *  - password rows never reach localStorage['sendback'];
 *  - the Error Monitor is printed for administrators only and its card texts exist.
 * Run: node tests/test-submit-failure-causes.js
 */
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const core = fs.readFileSync(__dirname + '/../public/assets/js/core-efb.js', 'utf8');
const slice = (from, to) => { const a = core.indexOf(from); const b = core.indexOf(to, a); assert(a >= 0 && b > a, 'slice ' + from); return core.slice(a, b); };

(async () => {
  // Cause classifier and visitor text.
  const sandbox = { ajax_object_efm: { text: { formNotSentCode: 'Not sent (error %d).', requestBlocked: 'BLOCKED' } } };
  vm.createContext(sandbox);
  vm.runInContext(slice('async function efb_response_info_efb', 'async function efb_fetch_public_request_efb'), sandbox);
  assert(typeof sandbox.efb_request_text_efb === 'function', 'request text helper present');
  assert.strictEqual(sandbox.efb_request_text_efb('identity_changed', { status: 409, m: 'You signed in or out in another tab. Please refresh the page and send the form again.' }), 'You signed in or out in another tab. Please refresh the page and send the form again.', 'tracker/reply keep the refresh instruction');
  const cause = (status, code, m, shape) => sandbox.efb_failure_cause_efb({ status, code: code || '', m: m || '', shape: shape || (m ? 'efb' : 'none') });
  const table = [
    [[0], 'unknown_outcome'],
    [[403, 'efb_nonce_expired'], 'stale_token'],
    [[403, 'efb_nonce_missing', '', 'wp'], 'blocked'],
    [[503, 'efb_human_shield_blocked', 'Please try again later'], 'server_refusal'],
    [[500, '', 'The form could not be saved. Your input has been kept.'], 'server_failed'],
    [[403, 'rest_cookie_invalid_nonce'], 'stale_token'],
    [[409, 'efb_context_expired', 'x'], 'stale_context'],
    [[409, 'efb_identity_changed', 'x'], 'identity_changed'],
    [[500, 'efb_storage_failed', 'could not be saved'], 'server_failed'],
    [[502, '', '', 'html'], 'server_error'],
    [[403, 'efb_human_shield_blocked', 'too fast'], 'server_refusal'],
    [[401, 'rest_not_logged_in', '', 'wp'], 'closed'],
    [[403, 'rest_forbidden', '', 'wp'], 'closed'],
    [[429, '', '', 'html'], 'rate_limited'],
    [[413, '', '', 'html'], 'too_large'],
    [[403, '', '', 'html'], 'blocked'],
    [[404, 'rest_no_route', '', 'wp'], 'rejected'],
  ];
  for (const [args, expected] of table) assert.strictEqual(cause(...args), expected, JSON.stringify(args));
  assert.strictEqual(sandbox.efb_visitor_text_efb('rejected', { status: 404 }), 'Not sent (error 404).');
  assert.strictEqual(sandbox.efb_visitor_text_efb('blocked', { status: 403 }), 'BLOCKED');
  assert.strictEqual(sandbox.efb_visitor_text_efb('server_refusal', { status: 403, m: 'Human Shield says no' }), 'Human Shield says no');
  for (const c of ['stale_token', 'stale_context', 'identity_changed', 'closed', 'rate_limited', 'too_large', 'unknown_outcome', 'rejected']) {
    const t = sandbox.efb_visitor_text_efb(c, { status: 418 });
    assert(t && !/jquery/i.test(t), c + ' has a plain message');
  }
  // Phrases arrive HTML-escaped; plain-text uses decode them once.
  const s3 = { document: { createElement: () => { const o = { set innerHTML(v) { this.value = v.replace(/&#039;/g, "'").replace(/&amp;/g, '&'); } }; return o; } } };
  vm.createContext(s3);
  vm.runInContext(slice('function efb_plain_text_efb', '/* What a refused response says about itself:'), s3);
  assert.strictEqual(s3.efb_plain_text_efb('The website&#039;s security'), "The website's security");
  // An HTML firewall page is read as such, never shown.
  const html = { status: 403, headers: { get: h => (h === 'Content-Type' ? 'text/html' : null) }, clone() { return { json: async () => { throw new SyntaxError('<'); } }; } };
  const info = await sandbox.efb_response_info_efb(html);
  assert.strictEqual(info.shape, 'html'); assert.strictEqual(info.m, '');

  // Password rows never reach localStorage.
  const store = {};
  const s2 = { localStorage: { getItem: k => (k in store ? store[k] : null), setItem: (k, v) => { store[k] = String(v); } }, window: { addEventListener() {} } };
  vm.createContext(s2);
  vm.runInContext(slice('function efb_sendback_for_storage_efb', 'function efb_get_error_panel_efb'), s2);
  const rows = [{ id_: 'u', type: 'text', value: 'someone' }, { id_: 'p', type: 'password', value: 'S3cret' }];
  assert(!s2.efb_sendback_for_storage_efb(rows).includes('S3cret'), 'storage copy has no password');
  assert.strictEqual(rows.length, 2, 'the in-memory list keeps the password for the submit');
  store.sendback = JSON.stringify(rows);
  s2.efb_scrub_stored_passwords_efb();
  assert(!store.sendback.includes('S3cret') && store.sendback.includes('someone'), 'an old stored password is removed, other answers kept');
  const writes = (core.match(/localStorage\.setItem\('sendback', JSON\.stringify/g) || []).length;
  assert.strictEqual(writes, 0, 'every core write goes through efb_sendback_for_storage_efb');

  // Error Monitor: administrators only, and its card texts are defined.
  const pub = fs.readFileSync(__dirname + '/../includes/class-Emsfb-public.php', 'utf8');
  assert(/\$console_script_efb = \(is_user_logged_in\(\) && current_user_can\('manage_options'\)\)/.test(pub), 'Error Monitor printed for administrators only');
  assert(!/\$ar_core\['cache_plugins_public'\]/.test(pub), 'the cache plugin list is no longer sent to visitors');
  const fb = fs.readFileSync(__dirname + '/../includes/class-Emsfb-formbuilder.php', 'utf8');
  for (const key of ['f_expired_title', 'f_cache_title', 'f_blocked_title', 'f_noanswer_title', 'f_storage_title', 'f_rejected_title', 'f_server_title', 'f_badjson_title', 'f_challenge_do', 'badge', 'onlyYou', 'visitorSaw', 'techDetails']) {
    assert(fb.includes("'" + key + "'"), 'panel text ' + key);
  }
  const panelJs = fb.slice(fb.indexOf('failureTexts(f) {'), fb.indexOf('getDismissLabel(label) {'));
  assert(panelJs.length > 100 && !panelJs.includes("'") && !panelJs.includes('\\'), 'panel JS stays valid inside the single-quoted PHP string');
  console.log('PASS: failure causes and visitor texts, HTML pages never shown, passwords kept out of localStorage, admin-only Error Monitor with plain-language cards');
})().catch(e => { console.error(e); process.exitCode = 1; });
