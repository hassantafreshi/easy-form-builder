'use strict';
// fun_efb_run() must wait for a jQuery that an optimiser deferred or delayed (LiteSpeed, SG,
// Flying Scripts...) instead of failing for good, and its fallback warning must not throw.
// Run from the plugin root: node tests/test-core-jquery-wait.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
const core = fs.readFileSync(path.join(root, 'public/assets/js/core-efb.js'), 'utf8');
const start = core.indexOf('  function fun_efb_run() {');
const end = core.indexOf('    jQuery(() => {', start);
assert.ok(start > 0 && end > start, 'fun_efb_run() not found');
const source = core.slice(start, end) + '}\nfun_efb_run;';

function sandbox(withText = true) {
  const timers = [];
  const bodies = [{ innerHTML: 'form 1' }, { innerHTML: 'form 2' }];
  const tracker = { innerHTML: 'tracker' };
  const ctx = {
    g_timeout_efb: 1100,
    document: {
      querySelector: sel => (sel === '.body_efb' ? bodies[0] : null),
      querySelectorAll: sel => (sel === '.body_efb' ? bodies : []),
      getElementById: id => (id === 'body_tracker_emsFormBuilder' ? tracker : null),
    },
    setTimeout: (fn, ms) => { timers.push({ fn, ms }); return timers.length; },
    clearTimeout: () => {},
  };
  if (withText) ctx.ajax_object_efm = { text: { alert: 'Alert', jqinl: 'jQuery is not loaded' } };
  ctx.window = ctx;
  vm.createContext(ctx);
  const run = vm.runInContext(source, ctx);
  return { ctx, run, timers, bodies, tracker };
}

// jQuery arrives after three polls: the form initialises, no warning is shown.
{
  const s = sandbox();
  s.run();
  assert.equal(s.ctx.efb_initialized, undefined, 'must not mark itself initialised while waiting');
  assert.equal(s.timers.length, 1, 'schedules a retry');
  assert.equal(s.timers[0].ms, 200);
  s.timers.shift().fn(); s.timers.shift().fn();
  s.ctx.jQuery = function () {};
  s.timers.shift().fn();
  assert.equal(s.ctx.efb_initialized, true, 'initialises once jQuery is there');
  assert.equal(s.ctx.g_timeout_efb, 100);
  assert.equal(s.bodies[0].innerHTML, 'form 1', 'no warning when jQuery arrived');
  assert.equal(s.timers.length, 0, 'no further polling once initialised');
}
// jQuery never arrives: every form gets the warning, nothing throws.
{
  const s = sandbox();
  s.run();
  let polls = 0;
  while (s.timers.length) { s.timers.shift().fn(); polls++; }
  assert.equal(polls, 50, 'gives up after the bounded wait (about 10 seconds)');
  assert.equal(s.ctx.efb_initialized, true);
  assert.match(s.bodies[0].innerHTML, /jQuery is not loaded/);
  assert.match(s.bodies[1].innerHTML, /jQuery is not loaded/, 'every form, not just the first');
  assert.match(s.tracker.innerHTML, /jQuery is not loaded/);
}
// Neither jQuery nor the localized text ever arrive (an optimiser dropped both): still no throw.
{
  const s = sandbox(false);
  s.run();
  while (s.timers.length) s.timers.shift().fn();
  assert.equal(s.ctx.efb_initialized, true);
  assert.match(s.bodies[0].innerHTML, /alarm_emsFormBuilder/);
}
// No shipped file may pin EFB with data-cfasync: Rocket Loader would split it from its data.
{
  const offenders = [];
  const walk = dir => {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      if (['node_modules', '_workspace', 'tests', '.git', 'vendor'].includes(entry.name)) continue;
      const file = path.join(dir, entry.name);
      if (entry.isDirectory()) walk(file);
      else if (/\.(php|js|html)$/.test(entry.name) && /data-cfasync\s*=|['"]data-cfasync['"]\s*=>/i.test(fs.readFileSync(file, 'utf8'))) offenders.push(path.relative(root, file));
    }
  };
  walk(root);
  assert.deepEqual(offenders, [], 'data-cfasync found in ' + offenders.join(', '));
}
console.log('PASS: late jQuery is awaited; missing jQuery warns every form without a TypeError; no data-cfasync is shipped');
