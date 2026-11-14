/**
 * F15-B: the undo / redo core (includes/admin/assets/js/history-efb.js) in Node, with a fake clock and
 * fake timers (spec §6.1): order of undo / redo, a new change clears redo, no-op entries dropped, the
 * same control within a second merged (typing 20 letters is one entry), the 50-entry and 20 M
 * character caps, the commit after the builder's timers settled (a change that lands late is still in
 * its entry), the gesture rule (a late change joins the last entry, a redraw is not an entry), late(),
 * record(), batch() (sync and Promise), reset(), write() that returns a Promise, labels and events.
 *
 *   node tests/test-builder-history.js
 */
'use strict';
const path = require('path');
const EfbHistory = require(path.join(__dirname, '..', 'includes', 'admin', 'assets', 'js', 'history-efb.js'));

let pass = 0, fail = 0;
function t(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) { pass++; console.log('[PASS] ' + label); } else { fail++; console.log('[FAIL] ' + label + '  got ' + JSON.stringify(actual) + ' want ' + JSON.stringify(expected)); }
}

/* A fake builder: state is a JSON string; timers run when the clock moves. */
function rig(opts) {
  let now = 1000;
  let timers = [];
  let seq = 0;
  const S = { state: [{ type: 'form' }], writes: [] };
  const h = EfbHistory.create(Object.assign({
    read: () => JSON.stringify(S.state),
    write: (json, info) => { S.state = JSON.parse(json); S.writes.push(info.dir); },
    now: () => now,
    setTimeout: (f, ms) => { const id = ++seq; timers.push({ id, at: now + ms, f }); return id; },
    clearTimeout: (id) => { timers = timers.filter((x) => x.id !== id); },
    settle: () => 100,
  }, opts || {}));
  S.h = h;
  S.tick = (ms) => {
    const end = now + ms;
    for (;;) {
      timers.sort((a, b) => a.at - b.at);
      const n = timers[0];
      if (!n || n.at > end) break;
      timers.shift();
      now = n.at;
      n.f();
    }
    now = end;
  };
  S.set = (fn) => { fn(S.state); };
  /** One builder edit: gesture, the change, commit, timers settled. */
  S.edit = (label, key, fn, settle) => { h.gesture(); h.begin(label, key); fn(S.state); h.commit(); S.tick(settle === undefined ? 150 : settle); };
  return S;
}
const J = (x) => JSON.stringify(x);

/* ---- basic order */
let r = rig();
const s0 = J(r.state);
r.edit('Add Email', 'a1', (s) => s.push({ id_: 'e', type: 'email' }));
const s1 = J(r.state);
r.edit('Add Text', 'a2', (s) => s.push({ id_: 't', type: 'text' }));
const s2 = J(r.state);
t('two edits → two entries, labels kept', [r.h.state().size, r.h.state().undoLabel], [2, 'Add Text']);
let u = r.h.undo();
t('undo restores the state before the last edit', [J(r.state), u.label, u.dir], [s1, 'Add Text', 'undo']);
r.h.undo();
t('undo again → the first state', J(r.state), s0);
t('nothing more to undo', [r.h.canUndo(), r.h.undo()], [false, null]);
r.h.redo();
t('redo → after the first edit', J(r.state), s1);
r.h.redo();
t('redo → after the second edit', J(r.state), s2);
t('nothing more to redo', [r.h.canRedo(), r.h.redo()], [false, null]);
r.h.undo();
r.edit('Add Phone', 'a3', (s) => s.push({ id_: 'p', type: 'tel' }));
t('a new change after undo clears redo', [r.h.canRedo(), r.h.state().size], [false, 2]);
t('write() got undo / redo', r.writes.slice(0, 5), ['undo', 'undo', 'redo', 'redo', 'undo']);

/* ---- no-op and merge */
r = rig();
r.edit('Nothing', 'n', () => {});
t('a change that changes nothing makes no entry', r.h.state().size, 0);
for (let i = 0; i < 20; i++) r.edit('Change Label', 'labelEl|x-id', (s) => { s[0].name = 'Field ' + 'abcdefghijklmnopqrst'.slice(0, i + 1); }, 150);
t('20 changes of one control within a second each: one entry', r.h.state().size, 1);
r.h.undo();
t('its undo goes back to before the first letter', r.state[0].name, undefined);
r = rig();
r.edit('Change Label', 'labelEl|x', (s) => { s[0].name = 'A'; });
r.tick(1500);
r.edit('Change Label', 'labelEl|x', (s) => { s[0].name = 'AB'; });
t('the same control after more than a second: a new entry', r.h.state().size, 2);
r = rig();
r.edit('Change Label', 'labelEl|x', (s) => { s[0].name = 'A'; });
r.edit('Change Description', 'desEl|x', (s) => { s[0].message = 'B'; });
t('two controls within a second: two entries', r.h.state().size, 2);
r = rig();
r.edit('Change Label', 'k', (s) => { s[0].name = 'A'; });
r.edit('Change Label', 'k', (s) => { delete s[0].name; });
t('typed back to where it started: the merged entry goes away', r.h.state().size, 0);

/* ---- caps */
r = rig();
for (let i = 0; i < 60; i++) r.edit('E' + i, 'k' + i, (s) => s.push({ i }));
t('at most 50 entries, the oldest dropped', [r.h.state().size, r.h.entries()[0].label], [50, 'E10']);
r = rig({ maxChars: 20000 });
for (let i = 0; i < 30; i++) r.edit('E' + i, 'k' + i, (s) => s.push({ pad: 'x'.repeat(100) }));
t('the character cap drops the oldest entries (≤ 20000 characters of snapshots kept)', [r.h.state().chars <= 20000, r.h.state().size < 30, r.h.state().size > 0], [true, true, true]);
r = rig({ maxChars: 50 });
r.edit('Big', 'b', (s) => s.push({ pad: 'x'.repeat(500) }));
t('one change bigger than the cap is still undoable once', r.h.state().size, 1);

/* ---- settle: the builder writes after its own timer (change_el_edit_Efb: len * 6 ms) */
r = rig({ settle: () => 600 });
r.h.gesture();
r.h.begin('Change Label', 'k');
r.h.commit();
r.tick(500);
r.set((s) => { s[0].name = 'late'; }); // the builder's timer fired at 500 ms
r.tick(200);
t('a change written by the builder\'s timer before the settle delay is in the entry', [r.h.state().size, (r.h.undo(), r.state[0].name)], [1, undefined]);
r = rig({ settle: () => 100 });
r.h.gesture(); r.h.begin('X', 'k'); r.h.commit(); r.tick(50); r.h.commit(); r.tick(80);
t('commit is debounced (a second commit restarts the wait)', r.h.state().size, 0);

/* ---- gesture rule: late change joins, redraw is not an entry */
r = rig();
r.edit('Duplicate Email', 'dup', (s) => s.push({ id_: 'c', type: 'email' }));
r.tick(1200);
r.set((s) => { s.push({ id_: 'c-op', type: 'option' }); }); // the duplicate's late part
r.h.gesture(); // the person's next action
t('a late change within 5 s joins the last entry (no extra entry)', r.h.state().size, 1);
r.h.undo();
t('its undo removes the late part too', J(r.state), J([{ type: 'form' }]));
r = rig();
r.set((s) => { s[0].steps = 1; }); // a redraw that normalized something, before anything was recorded
r.h.gesture();
t('drift before any entry becomes the base silently', [r.h.state().size, r.h.canUndo()], [0, false]);
r = rig();
r.edit('A', 'a', (s) => { s[0].a = 1; });
r.tick(6000);
r.set((s) => { s[0].steps = '1'; });
r.h.gesture();
t('drift after 5 s is not added to the old entry (its redo has no trace of it)', [r.h.state().size, (r.h.undo(), r.h.redo(), r.state[0].steps === undefined)], [1, true]);

/* ---- late() */
r = rig();
r.edit('Change country', 'cty', (s) => { s[0].country = 'de'; });
r.tick(400);
r.set((s) => s.push({ type: 'option', value: 'Berlin' }));
r.h.late('Load list');
t('late(): the loaded list joins the person\'s change', [r.h.state().size, r.h.state().undoLabel], [1, 'Change country']);
r = rig();
r.set((s) => s.push({ type: 'option' }));
r.h.gesture();
r.set((s) => s.push({ type: 'option', v: 2 }));
r.h.late('Load list');
t('late() with no recent entry of its own: an entry named by its label', [r.h.state().size, r.h.state().undoLabel], [1, 'Load list']);

/* ---- record (API) and batch */
r = rig();
r.set((s) => s.push({ id_: 'api1' }));
r.h.record('AutoPilot: Add fields', 'api|insertItems');
t('record(): an API change is one entry now', [r.h.state().size, r.h.state().undoLabel], [1, 'AutoPilot: Add fields']);
r = rig();
r.h.batch('AutoPilot: Build', () => {
  r.set((s) => s.push({ id_: 'b1' })); r.h.record('one');
  r.set((s) => s.push({ id_: 'b2' })); r.h.record('two');
});
t('batch(): several API calls are one entry with the batch label', [r.h.state().size, r.h.state().undoLabel], [1, 'AutoPilot: Build']);
r.h.undo();
t('its undo removes both', J(r.state), J([{ type: 'form' }]));
r = rig();
let done = r.h.batch('Async', () => Promise.resolve().then(() => { r.set((s) => s.push({ id_: 'p1' })); r.h.record('x'); }));
done.then(() => {
  t('batch() with a Promise: one entry when it resolves', [r.h.state().size, r.h.state().undoLabel], [1, 'Async']);

  /* ---- write() returning a Promise: nothing is recorded meanwhile; the drawn state is the base */
  const r2 = rig();
  let resolveDraw;
  r2.h.create = null;
  const h2 = EfbHistory.create({
    read: () => JSON.stringify(r2.state),
    write: (json) => { r2.state = JSON.parse(json); return new Promise((res) => { resolveDraw = () => { r2.state.forEach((x) => { if (x.amount !== undefined) x.amount = Number(x.amount); }); res(); }; }); },
    settle: 0, setTimeout: (f) => { f(); return 1; }, clearTimeout: () => {},
  });
  h2.gesture(); r2.state.push({ id_: 'q', amount: '3' }); h2.begin('Add', 'a'); h2.commit();
  h2.gesture(); r2.state.push({ id_: 'w', amount: '4' }); h2.begin('Add 2', 'b'); h2.commit();
  h2.undo();
  t('while write() draws, the history says it is applying', h2.isApplying(), true);
  h2.gesture(); h2.commit();
  return Promise.resolve().then(() => resolveDraw()).then(() => new Promise((res) => setTimeout(res, 5))).then(() => {
    t('after the draw: not applying; the drawn (normalized) state is the base', [h2.isApplying(), h2.state().size, h2.state().canRedo], [false, 1, true]);
    h2.gesture();
    t('the normalization of the draw is not an entry', h2.state().size, 1);

    /* ---- reset, events, labels */
    const r3 = rig();
    const seen = [];
    r3.h.on('change', (st) => seen.push(st.canUndo));
    r3.edit('A', 'a', (s) => { s[0].a = 1; });
    r3.h.reset();
    t('reset() empties both stacks (another form, a template, a draft)', [r3.h.canUndo(), r3.h.canRedo()], [false, false]);
    t('change events tell the toolbar', seen.includes(true) && seen[seen.length - 1] === false, true);
    r3.edit('B', 'b', (s) => { s[0].b = 1; });
    r3.h.undo();
    t('state(): labels of the next undo / redo', [r3.h.state().undoLabel, r3.h.state().redoLabel], ['', 'B']);
    t('create() refuses without read / write', (() => { try { EfbHistory.create({}); return false; } catch (e) { return true; } })(), true);

    console.log('\n========================================');
    console.log(`RESULTS: ${pass} passed, ${fail} failed`);
    process.exit(fail ? 1 : 0);
  });
}).catch((e) => { console.error(e); process.exit(1); });
