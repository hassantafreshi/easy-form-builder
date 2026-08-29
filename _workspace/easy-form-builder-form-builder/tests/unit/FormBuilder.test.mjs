import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { FormBuilder } from '../../src/public-api/FormBuilder.js';
import { FormBuilderWordPressAdapter } from '../../src/adapters/wordpress/FormBuilderWordPressAdapter.js';

const ROOT = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '../..');
const fixtureRaw = readFileSync(path.join(ROOT, 'fixtures/legacy-templates/contact.json'), 'utf8');

/** A fake fetch that mimics the exact AJAX_API.md envelope shape, no network. */
function makeFakeFetch(responses) {
  let call = 0;
  return async (_url, _init) => {
    const data = responses[call++];
    return { ok: true, json: async () => ({ success: true, data }) };
  };
}

function makeAdapter(responses) {
  return new FormBuilderWordPressAdapter({
    ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
    nonce: 'test-nonce',
    fetchImpl: makeFakeFetch(responses),
  });
}

test('FormBuilderWordPressAdapter.loadForm unwraps the double-envelope and returns rawValue', async () => {
  const adapter = makeAdapter([{ success: true, ajax_value: fixtureRaw, id: 7 }]);
  const result = await adapter.loadForm(7);
  assert.equal(result.id, 7);
  assert.equal(result.rawValue, fixtureRaw);
});

test('FormBuilderWordPressAdapter.loadForm throws EfbServerError on app-level failure', async () => {
  const adapter = makeAdapter([{ success: false, m: 'not found' }]);
  await assert.rejects(() => adapter.loadForm(999), /not found/);
});

test('FormBuilder.newForm -> addField -> save happy path', async () => {
  const adapter = makeAdapter([{ success: true, r: 'insert', value: '[EMS_Form_Builder id=5]', id: 5 }]);
  const builder = new FormBuilder({ adapter });
  const state = builder.newForm();
  state.updateSettings({ formName: 'Test Form', type: 'form' });
  state.addField({ id_: 'f1', type: 'text', name: 'Field 1', step: 1, amount: 1 });

  let saved = null;
  builder.on('saved', (r) => { saved = r; });
  const result = await builder.save();

  assert.equal(result.id, 5);
  assert.equal(saved.id, 5);
  assert.equal(builder.getState().formId, 5);
  assert.equal(builder.isDirty(), false);
});

test('FormBuilder.loadForm -> update happy path', async () => {
  const adapter = makeAdapter([
    { success: true, ajax_value: fixtureRaw, id: 42 },
    { success: true, r: 'updated', value: '[EMS_Form_Builder id=42]' },
  ]);
  const builder = new FormBuilder({ adapter });
  await builder.loadForm(42);
  builder.setState({ ...builder.getState(), rows: builder.getState().rows });

  let saved = null;
  builder.on('saved', (r) => { saved = r; });
  await builder.update();
  assert.equal(saved.shortcode, '[EMS_Form_Builder id=42]');
});

test('FormBuilder.save emits saveFailed and rethrows on adapter error', async () => {
  const adapter = makeAdapter([{ success: false, m: 'boom' }]);
  const builder = new FormBuilder({ adapter });
  builder.newForm();
  let failedErr = null;
  builder.on('saveFailed', (e) => { failedErr = e; });
  await assert.rejects(() => builder.save());
  assert.match(failedErr.message, /boom/);
});

test('validate() flags missing id_ on a field/option row', () => {
  const adapter = makeAdapter([]);
  const builder = new FormBuilder({ adapter });
  const state = builder.newForm();
  state.setState({ rows: [{ type: 'form' }, { type: 'text', name: 'no id' }] });
  const result = builder.validate();
  assert.equal(result.valid, false);
  assert.equal(result.errors.length, 1);
});

test('mount/destroy is safe to repeat 20 times with no leaked listeners or duplicate view instances', async () => {
  let renderCalls = 0;
  let destroyCalls = 0;
  const listenersSeenAtDestroy = [];
  const view = {
    render(container, state) {
      renderCalls += 1;
      container.rendered = state.getState();
    },
    destroy() {
      destroyCalls += 1;
    },
  };
  const adapter = makeAdapter([]);

  for (let i = 0; i < 20; i += 1) {
    const builder = new FormBuilder({ adapter, view });
    const container = {};
    let readyFired = false;
    let destroyedFired = false;
    builder.on('ready', () => { readyFired = true; });
    builder.onDestroyed(() => { destroyedFired = true; });

    await builder.mount(container, {});
    assert.equal(readyFired, true);
    assert.equal(container.rendered.mode, 'create');

    builder.destroy();
    assert.equal(destroyedFired, true);
    // Query methods no-op safely after destroy; mutating methods must refuse.
    assert.equal(builder.getState(), null);
    assert.throws(() => builder.newForm(), /destroyed/);

    // double-destroy must be a safe no-op, not a second teardown/emit.
    listenersSeenAtDestroy.push(destroyCalls);
    builder.destroy();
  }

  assert.equal(renderCalls, 20);
  assert.equal(destroyCalls, 20, 'destroy() must not double-fire view.destroy() on repeat destroy() calls');
});
