import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { FormState } from '../../src/state/FormState.js';

const ROOT = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '../..');
const fixtureRaw = readFileSync(path.join(ROOT, 'fixtures/legacy-templates/contact.json'), 'utf8');

test('newForm starts empty, create mode, not loading', () => {
  const state = FormState.newForm();
  assert.deepEqual(state.getState().rows, []);
  assert.equal(state.getMode(), 'create');
  assert.equal(state.getFormId(), null);
  assert.equal(state.isDirty(), false);
});

test('fromLoadedForm parses raw JSON and sets edit mode + formId', () => {
  const state = FormState.fromLoadedForm(fixtureRaw, 42);
  assert.equal(state.getMode(), 'edit');
  assert.equal(state.getFormId(), 42);
  assert.equal(state.getState().rows.length, 6);
  assert.equal(state.isDirty(), false);
});

test('serialize() round-trips through formSerializer losslessly', () => {
  const original = JSON.parse(fixtureRaw);
  const state = FormState.fromLoadedForm(original, 1);
  const serialized = state.serialize();
  assert.deepEqual(JSON.parse(serialized), original);
});

test('addField appends a row, marks dirty, emits fieldAdded + stateChanged', () => {
  const state = FormState.fromLoadedForm(fixtureRaw, 1);
  let addedPayload = null;
  let changedCount = 0;
  state.on('fieldAdded', (p) => { addedPayload = p; });
  state.on('stateChanged', () => { changedCount += 1; });

  state.addField({ id_: 'new1', dataId: 'new1-id', type: 'text', name: 'New Field', step: 1, amount: 99 });

  assert.equal(state.getFields().length, 5);
  assert.equal(addedPayload.id_, 'new1');
  assert.equal(changedCount, 1);
  assert.equal(state.isDirty(), true);
});

test('addField throws without an id_', () => {
  const state = FormState.newForm();
  assert.throws(() => state.addField({ type: 'text' }), TypeError);
});

test('updateField shallow-merges a patch by id_ and returns true', () => {
  const state = FormState.fromLoadedForm(fixtureRaw, 1);
  const ok = state.updateField('uoghulv7f', { required: false, placeholder: 'Given name' });
  assert.equal(ok, true);
  const field = state.getFields().find((f) => f.id_ === 'uoghulv7f');
  assert.equal(field.required, false);
  assert.equal(field.placeholder, 'Given name');
  // untouched sibling keys survive
  assert.equal(field.type, 'text');
});

test('updateField returns false for an unknown id', () => {
  const state = FormState.fromLoadedForm(fixtureRaw, 1);
  assert.equal(state.updateField('does-not-exist', { x: 1 }), false);
});

test('removeField deletes the row and cascades option children', () => {
  const rows = [
    { type: 'form' },
    { id_: 's1', type: 'step', step: 1 },
    { id_: 'sel1', type: 'select', name: 'pick', step: 1 },
    { id_: 'o1', type: 'option', parent: 'sel1', value: 'A' },
    { id_: 'o2', type: 'option', parent: 'sel1', value: 'B' },
    { id_: 'txt1', type: 'text', name: 'other', step: 1 },
  ];
  const state = FormState.fromLoadedForm(rows, 1);
  const ok = state.removeField('sel1');
  assert.equal(ok, true);
  const remainingIds = state.getState().rows.map((r) => r.id_);
  assert.equal(remainingIds.includes('sel1'), false);
  assert.equal(remainingIds.includes('o1'), false);
  assert.equal(remainingIds.includes('o2'), false);
  assert.equal(remainingIds.includes('txt1'), true);
});

test('reorderField renumbers amount within the same step, preserving relative order', () => {
  const rows = [
    { type: 'form' },
    { id_: 'a', type: 'text', step: 1, amount: 1 },
    { id_: 'b', type: 'text', step: 1, amount: 2 },
    { id_: 'c', type: 'text', step: 1, amount: 3 },
  ];
  const state = FormState.fromLoadedForm(rows, 1);
  state.reorderField('c', 0); // move c to the front
  const order = state.getFields().sort((x, y) => x.amount - y.amount).map((f) => f.id_);
  assert.deepEqual(order, ['c', 'a', 'b']);
});

test('setState/getState round-trip and dirty tracking', () => {
  const state = FormState.newForm();
  assert.equal(state.isDirty(), false);
  state.setState({ rows: JSON.parse(fixtureRaw), mode: 'create' });
  assert.equal(state.isDirty(), true);
  assert.equal(state.getState().rows.length, 6);
  state.clearDirty();
  assert.equal(state.isDirty(), false);
});

test('stateChanged fires on every mutating operation', () => {
  const state = FormState.fromLoadedForm(fixtureRaw, 1);
  let events = 0;
  state.on('stateChanged', () => { events += 1; });
  state.updateSettings({ formName: 'Renamed' });
  state.addField({ id_: 'x', type: 'text', step: 1, amount: 1 });
  state.updateField('x', { name: 'Y' });
  state.reorderField('x', 0);
  state.removeField('x');
  assert.equal(events, 5);
});
