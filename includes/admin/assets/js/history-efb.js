/*!
 * Easy Form Builder - undo / redo history of the form builder (F15-B,
 * docs/feature-gaps/F15-builder-productivity.fa.md §6.1).
 *
 * A memento history: every entry holds the whole form (valj_efb) as JSON text before and
 * after one change, so nothing has to know how to reverse a change, and the 100 places
 * that write valj_efb need no inverse command. The history only reads and writes the
 * state through two callbacks:
 *
 *   var h = EfbHistory.create({
 *     read:  function () { return JSON.stringify(valj_efb); },   // the state now
 *     write: function (json, info) { ... },                      // put a state back (may return a Promise)
 *     limit: 50, maxChars: 20e6, mergeMs: 1000,
 *     settle: function () { return Math.max(50, valj_efb.length * 6 + 20); }  // ms until the builder's timers settled
 *   });
 *   h.gesture();             // a person starts an action: what changed since the last entry joins it when that
 *                            // entry is recent (a duplicate redrawn late), else becomes the base silently (a redraw)
 *   h.begin(label, key);     // name the change that follows; same key within mergeMs merges into one entry
 *   h.commit();              // record it once the builder's timers settled (debounced)
 *   h.flush();               // record a pending change now
 *   h.record(label, key);    // name and record a change that already happened (an API call), now
 *   h.late(label);           // a change that finished late (a list from the network): joins the last entry
 *   h.batch(label, fn);      // everything fn() changes (sync or a Promise) is one entry
 *   h.undo(); h.redo();      // -> the entry, or null; write() is called with its before / after
 *   h.canUndo(); h.canRedo(); h.reset(); h.on('change', fn); h.state();
 *
 * An entry that changes nothing is dropped. Snapshots are text, never edited in place.
 * No DOM, no globals: the builder wiring lives in productivity-efb.js. ES5; browser:
 * window.EfbHistory, Node: module.exports (tests/test-builder-history.js).
 */
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) { module.exports = api; } else { root.EfbHistory = api; }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var VERSION = 1;

  function create(opts) {
    opts = opts || {};
    if (typeof opts.read !== 'function' || typeof opts.write !== 'function') throw new Error('EfbHistory.create needs read and write');
    var limit = opts.limit > 0 ? Math.floor(opts.limit) : 50;
    var maxChars = opts.maxChars > 0 ? opts.maxChars : 20e6;
    var mergeMs = opts.mergeMs >= 0 ? opts.mergeMs : 1000;
    var now = typeof opts.now === 'function' ? opts.now : function () { return Date.now(); };
    var setT = opts.setTimeout || function (f, ms) { return setTimeout(f, ms); };
    var clearT = opts.clearTimeout || function (t) { clearTimeout(t); };
    var settle = typeof opts.settle === 'function' ? opts.settle : function () { return opts.settle > 0 ? opts.settle : 50; };

    var undoStack = [];
    var redoStack = [];
    var base = null;          // the state as last recorded (or as found at the first gesture)
    var pending = null;       // {label, key, at} of the change being made
    var timer = null;
    var applying = 0;         // write() of an undo / redo in progress: nothing is recorded
    var batchDepth = 0;
    var batchLabel = null;
    var listeners = [];
    var sealed = false;       // the next entry never merges into the top one (after undo / redo / reset)
    var tok = 0;              // the gesture the entries being made belong to
    var lateMs = opts.lateMs >= 0 ? opts.lateMs : 5000;

    base = read();

    function emit() {
      var st = stateOf();
      for (var i = 0; i < listeners.length; i++) { try { listeners[i](st); } catch (e) { /* a listener's error is its own */ } }
    }
    function stateOf() {
      var top = undoStack.length ? undoStack[undoStack.length - 1] : null;
      var next = redoStack.length ? redoStack[redoStack.length - 1] : null;
      return {
        canUndo: undoStack.length > 0, canRedo: redoStack.length > 0,
        undoLabel: top ? top.label : '', redoLabel: next ? next.label : '',
        size: undoStack.length, redoSize: redoStack.length, chars: chars(), pending: !!pending, applying: applying > 0
      };
    }
    function read() { try { var s = opts.read(); return typeof s === 'string' ? s : null; } catch (e) { return null; } }
    function chars() {
      // Entries chain (one's after is the next one's before), so each snapshot is counted once.
      var n = 0;
      for (var i = 0; i < undoStack.length; i++) {
        var e = undoStack[i];
        n += e.after.length;
        if (i === 0 || undoStack[i - 1].after !== e.before) n += e.before.length;
      }
      for (var j = 0; j < redoStack.length; j++) n += redoStack[j].after.length;
      return n;
    }
    function trim() {
      while (undoStack.length > limit) undoStack.shift();
      while (undoStack.length > 1 && chars() > maxChars) undoStack.shift();
      // A single change bigger than the cap is still undoable once; redo entries go first.
      while (redoStack.length && chars() > maxChars) redoStack.shift();
    }
    function clearTimer() { if (timer !== null) { clearT(timer); timer = null; } }

    /* Record what changed since base, now. */
    function flush() {
      clearTimer();
      if (applying) { pending = null; return null; }
      if (batchDepth) return null;
      var p = pending;
      pending = null;
      var cur = read();
      if (cur === null) return null;
      if (base === null) { base = cur; if (p) emit(); return null; }
      if (cur === base) { if (p) emit(); return null; }
      var t = now();
      var label = (p && p.label) || '';
      var key = (p && p.key) || '';
      var top = undoStack.length ? undoStack[undoStack.length - 1] : null;
      var entry;
      if (!sealed && top && key && top.key === key && (t - top.t) < mergeMs && top.after === base) {
        top.after = cur;
        top.t = t;
        entry = top;
        if (top.after === top.before) undoStack.pop(); // typed back to where it started
      } else {
        entry = { label: label, key: key, before: base, after: cur, t: t, tok: tok };
        undoStack.push(entry);
      }
      sealed = false;
      base = cur;
      redoStack = [];
      trim();
      emit();
      return entry;
    }

    function schedule() {
      clearTimer();
      if (applying || batchDepth) return;
      var ms = Number(settle()) || 0;
      timer = setT(function () { timer = null; flush(); }, Math.max(0, ms));
    }

    var h = {
      version: VERSION,
      /** A person starts an action. Changes nobody recorded since the last entry (a redraw that
       *  normalized numbers, a list loaded from the network) become the base without an entry. */
      gesture: function () {
        if (applying || batchDepth || pending || timer !== null) return;
        var cur = read();
        if (cur === null) return;
        if (base !== null && cur !== base) {
          // Some builder actions finish after their own timers (a duplicate redraws the canvas after
          // len * p ms): what changed since belongs to the last entry when it is recent and still on top.
          var top = undoStack.length ? undoStack[undoStack.length - 1] : null;
          if (top && !sealed && top.tok === tok && top.after === base && (now() - top.t) < lateMs) {
            top.after = cur;
            redoStack = [];
            if (top.after === top.before) undoStack.pop();
            emit();
          }
        }
        base = cur;
        tok++;
      },
      begin: function (label, key) {
        if (applying) return;
        if (base === null) { var cur = read(); if (cur !== null && !pending) base = cur; }
        if (batchDepth) return;
        if (!pending) pending = { label: label ? String(label) : '', key: key ? String(key) : '' };
        else if (!pending.label && label) { pending.label = String(label); pending.key = key ? String(key) : pending.key; }
      },
      commit: function () { if (!applying && !batchDepth) { if (!pending) pending = { label: '', key: '' }; schedule(); } },
      flush: flush,
      /** A change made by code (an API call, a list from the network): one entry now. */
      record: function (label, key) {
        if (applying) return null;
        if (batchDepth) { if (!batchLabel && label) batchLabel = String(label); return null; }
        clearTimer();
        var keep = pending;
        pending = { label: label ? String(label) : (keep && keep.label) || '', key: key ? String(key) : '' };
        return flush();
      },
      batch: function (label, fn) {
        if (applying) return typeof fn === 'function' ? fn() : undefined;
        if (!batchDepth) { flush(); batchLabel = label ? String(label) : ''; }
        batchDepth++;
        var done = function () {
          batchDepth--;
          if (batchDepth === 0) { var l = batchLabel; batchLabel = null; pending = { label: l || '', key: '' }; return flush(); }
          return null;
        };
        var out;
        try { out = fn(); } catch (e) { done(); throw e; }
        if (out && typeof out.then === 'function') {
          return out.then(function (v) { done(); return v; }, function (e) { done(); throw e; });
        }
        done();
        return out;
      },
      undo: function () { return move(undoStack, redoStack, 'before', 'undo'); },
      redo: function () { return move(redoStack, undoStack, 'after', 'redo'); },
      canUndo: function () { return undoStack.length > 0 || (pending !== null && read() !== base); },
      canRedo: function () { return redoStack.length > 0; },
      reset: function (snap) {
        clearTimer();
        undoStack = []; redoStack = []; pending = null; batchDepth = 0; batchLabel = null; sealed = true;
        base = typeof snap === 'string' ? snap : read();
        emit();
      },
      /** A change that finished late (a list loaded from the network after the person's action): it
       *  joins the person's last entry when that is recent and on top, else it is an entry of its own. */
      late: function (label) {
        if (applying || batchDepth || pending || timer !== null) return null; // an open change will include it
        var cur = read();
        if (cur === null || base === null || cur === base) return null;
        var top = undoStack.length ? undoStack[undoStack.length - 1] : null;
        if (top && !sealed && top.tok === tok && top.after === base && (now() - top.t) < lateMs) {
          top.after = cur; base = cur; redoStack = [];
          if (top.after === top.before) undoStack.pop();
          emit();
          return top;
        }
        pending = { label: label ? String(label) : '', key: '' };
        return flush();
      },
      /** After the builder redrew a state it was given: take the state as drawn as the base. */
      rebase: function () { var cur = read(); if (cur !== null) base = cur; },
      on: function (ev, fn) { if (ev === 'change' && typeof fn === 'function') listeners.push(fn); return h; },
      off: function (ev, fn) { listeners = listeners.filter(function (x) { return x !== fn; }); return h; },
      state: stateOf,
      entries: function () { return undoStack.map(function (e) { return { label: e.label, key: e.key, t: e.t }; }); },
      isApplying: function () { return applying > 0; },
    };

    function move(from, to, side, dir) {
      flush();
      if (applying || !from.length) return null;
      var e = from.pop();
      to.push(e);
      var json = e[side];
      base = json;
      sealed = true;
      applying++;
      var finish = function () {
        applying = Math.max(0, applying - 1);
        // The builder may normalize what it draws (numbers as numbers): that is the new base, not a change.
        var cur = read();
        if (cur !== null) base = cur;
        emit();
      };
      var r;
      try { r = opts.write(json, { dir: dir, label: e.label }); } catch (err) { finish(); throw err; }
      if (r && typeof r.then === 'function') r.then(finish, finish); else finish();
      emit();
      return { label: e.label, dir: dir };
    }

    return h;
  }

  return { create: create, version: VERSION };
});
