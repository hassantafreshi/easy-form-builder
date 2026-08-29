import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  deserializeForm,
  serializeForm,
  getFormSettings,
  getSteps,
  getFields,
  getOptionsForField,
} from '../../src/serialization/formSerializer.js';

const ROOT = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '../..');
const fixtureRaw = readFileSync(path.join(ROOT, 'fixtures/legacy-templates/contact.json'), 'utf8');

test('deserializeForm parses a JSON string into row objects', () => {
  const rows = deserializeForm(fixtureRaw);
  assert.equal(Array.isArray(rows), true);
  assert.equal(rows.length, 6);
  assert.equal(rows[0].type, 'form');
});

test('deserializeForm accepts an already-parsed array', () => {
  const parsed = JSON.parse(fixtureRaw);
  const rows = deserializeForm(parsed);
  assert.deepEqual(rows, parsed);
});

test('deserializeForm rejects non-array input', () => {
  assert.throws(() => deserializeForm('{"not":"an array"}'), TypeError);
});

test('round trip: deserialize -> serialize -> deserialize is lossless, including unknown keys', () => {
  const rows = deserializeForm(fixtureRaw);
  // Simulate an unknown/add-on/future key this audit has not catalogued.
  rows[2].some_future_addon_key = { nested: ['value', 1, null] };
  const serialized = serializeForm(rows);
  const roundTripped = deserializeForm(serialized);
  assert.deepEqual(roundTripped, rows);
  assert.deepEqual(roundTripped[2].some_future_addon_key, { nested: ['value', 1, null] });
});

test('serializeForm . deserializeForm is the identity for the original fixture', () => {
  const original = JSON.parse(fixtureRaw);
  const roundTripped = deserializeForm(serializeForm(original));
  assert.deepEqual(roundTripped, original);
});

test('getFormSettings returns element [0]', () => {
  const rows = deserializeForm(fixtureRaw);
  assert.equal(getFormSettings(rows).formName, 'Contact Us');
});

test('getSteps returns only type:"step" rows', () => {
  const rows = deserializeForm(fixtureRaw);
  const steps = getSteps(rows);
  assert.equal(steps.length, 1);
  assert.equal(steps[0].id_, '1');
});

test('getFields excludes the settings row, step rows, and option rows', () => {
  const rows = deserializeForm(fixtureRaw);
  const fields = getFields(rows);
  assert.equal(fields.length, 4);
  assert.equal(fields.every((f) => f.type !== 'step'), true);
});

test('getOptionsForField returns option rows whose parent matches', () => {
  const rows = [
    { type: 'form', formName: 'x' },
    { id_: 's', type: 'select', name: 'Pick one' },
    { id_: 'o1', type: 'option', parent: 's', value: 'A', id_op: 'o1' },
    { id_: 'o1', type: 'option', parent: 's', value: 'B', id_op: 'o2' },
  ];
  const opts = getOptionsForField(rows, 's');
  assert.equal(opts.length, 2);
  assert.equal(opts[1].id_op, 'o2');
});
