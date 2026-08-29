import { EventEmitter } from '../core/EventEmitter.js';
import { FormState } from '../state/FormState.js';
import { deserializeForm } from '../serialization/formSerializer.js';

/**
 * Stable Form Builder API (docs/PUBLIC_API.md). This is the seam a future
 * UI (replacing src/ui-legacy) is meant to be built against, and the seam
 * src/bridge uses to keep legacy global-function callers working.
 *
 * mount() intentionally does not render anything itself yet - no UI has
 * been built at this stage of the extraction (Phase 2: state +
 * serialization only). It records the container and view, and is the
 * lifecycle anchor destroy() tears back down; see docs/PHASE-0-AUDIT.md §9
 * for why the legacy code's own lifecycle relies on DOM replacement rather
 * than explicit listener teardown, and why this class must not assume that
 * pattern for whatever view gets plugged in later.
 */
export class FormBuilder extends EventEmitter {
  /** @type {FormState|null} */
  #state = null;
  /** @type {import('../adapters/wordpress/FormBuilderWordPressAdapter.js').FormBuilderWordPressAdapter} */
  #adapter;
  /** @type {HTMLElement|null} */
  #container = null;
  /** @type {{render(container: HTMLElement, state: FormState): void, destroy(): void}|null} */
  #view = null;
  #destroyed = false;

  /**
   * @param {{ adapter: import('../adapters/wordpress/FormBuilderWordPressAdapter.js').FormBuilderWordPressAdapter, view?: {render(container: HTMLElement, state: FormState): void, destroy(): void} }} deps
   */
  constructor(deps) {
    super();
    this.#adapter = deps.adapter;
    this.#view = deps.view ?? null;
  }

  /**
   * @param {HTMLElement} container
   * @param {{formId?: number}} [context]
   */
  async mount(container, context = {}) {
    this.#assertNotDestroyed();
    this.#container = container;
    if (!this.#state) {
      this.#state = context.formId ? await this.loadForm(context.formId) : this.newForm();
    }
    this.#view?.render(container, this.#state);
    this.emit('ready', this.getState());
    return this;
  }

  /** @param {Record<string, unknown>[]} [initialRows] */
  newForm(initialRows) {
    this.#assertNotDestroyed();
    this.#state = FormState.newForm();
    if (initialRows) this.#state.setState({ rows: initialRows, mode: 'create' });
    this.#wireStateEvents(this.#state);
    return this.#state;
  }

  /** @param {number} formId */
  async loadForm(formId) {
    this.#assertNotDestroyed();
    const { id, rawValue } = await this.#adapter.loadForm(formId);
    const state = FormState.fromLoadedForm(rawValue, id);
    this.#state = state;
    this.#wireStateEvents(state);
    return state;
  }

  /**
   * Adopt an externally-provided snapshot (e.g. restored from an autosave
   * draft) without a network round trip.
   * @param {{formId?: number|null, mode?: 'create'|'edit', rows: Record<string, unknown>[]}} snapshot
   */
  hydrate(snapshot) {
    this.#assertNotDestroyed();
    const state = snapshot.mode === 'edit' && snapshot.formId
      ? FormState.fromLoadedForm(snapshot.rows, snapshot.formId)
      : FormState.newForm();
    if (snapshot.mode !== 'edit') state.setState({ rows: snapshot.rows, mode: 'create' });
    this.#state = state;
    this.#wireStateEvents(state);
    return state;
  }

  serialize() {
    this.#assertHasState();
    return this.#state.serialize();
  }

  /**
   * Structural validation only (well-formed rows with an id_). Field-level
   * validation rules (required/pattern/etc.) live in the legacy UI today
   * and have not yet been extracted into this layer - see
   * docs/PHASE-0-AUDIT.md pending items. This does not invent those rules.
   * @returns {{ valid: boolean, errors: string[] }}
   */
  validate() {
    this.#assertHasState();
    const rows = this.#state.getState().rows;
    const errors = [];
    if (rows.length === 0) errors.push('Form has no settings row');
    for (const row of rows.slice(1)) {
      if (row && row.type !== 'step' && typeof row.id_ === 'undefined') {
        errors.push('A field/option row is missing id_');
      }
    }
    const result = { valid: errors.length === 0, errors };
    if (!result.valid) this.emit('validationError', result);
    return result;
  }

  async save() {
    this.#assertHasState();
    const settings = this.#state.getSettings();
    this.emit('saveStarted');
    try {
      const result = await this.#adapter.createForm({
        name: String(settings.formName ?? ''),
        type: String(settings.type ?? ''),
        value: this.#state.serialize(),
      });
      this.#state.setFormId(result.id);
      this.#state.clearDirty();
      this.#state.setSaveState('saved');
      this.emit('saved', result);
      return result;
    } catch (err) {
      this.#state.setSaveState('error');
      this.emit('saveFailed', err);
      throw err;
    }
  }

  async update() {
    this.#assertHasState();
    const formId = this.#state.getFormId();
    if (!formId) throw new Error('update() called with no formId - call save() first for a new form');
    const settings = this.#state.getSettings();
    this.emit('saveStarted');
    try {
      const result = await this.#adapter.updateForm({
        id: formId,
        name: String(settings.formName ?? ''),
        value: this.#state.serialize(),
      });
      this.#state.clearDirty();
      this.#state.setSaveState('saved');
      this.emit('saved', result);
      return result;
    } catch (err) {
      this.#state.setSaveState('error');
      this.emit('saveFailed', err);
      throw err;
    }
  }

  getState() {
    return this.#state?.getState() ?? null;
  }

  /** @param {{formId?: number|null, mode?: 'create'|'edit', rows: Record<string, unknown>[]}} next */
  setState(next) {
    this.#assertHasState();
    this.#state.setState(next);
  }

  isDirty() {
    return this.#state?.isDirty() ?? false;
  }

  /** Static helper for callers that just have a raw payload and no FormBuilder instance yet. */
  static parseRawForm(raw) {
    return deserializeForm(raw);
  }

  destroy() {
    if (this.#destroyed) return;
    this.#view?.destroy();
    this.#state?.removeAllListeners();
    this.removeAllListeners();
    this.#container = null;
    this.#state = null;
    this.#destroyed = true;
    // emit after clearing internal refs but before returning, using a
    // detached emit so a destroyed:true listener registered just before
    // destroy() still fires (removeAllListeners() above already ran, so we
    // use a throwaway emitter path instead of `this.emit`).
    if (this.#destroyedListeners) {
      for (const fn of this.#destroyedListeners) fn();
    }
  }

  /** @type {(() => void)[]|null} */
  #destroyedListeners = null;

  /** @param {() => void} handler */
  onDestroyed(handler) {
    (this.#destroyedListeners ??= []).push(handler);
    return () => {
      this.#destroyedListeners = (this.#destroyedListeners ?? []).filter((h) => h !== handler);
    };
  }

  /** @param {FormState} state */
  #wireStateEvents(state) {
    for (const evt of ['stateChanged', 'fieldAdded', 'fieldUpdated', 'fieldRemoved', 'fieldReordered']) {
      state.on(evt, (payload) => this.emit(evt, payload));
    }
  }

  #assertHasState() {
    this.#assertNotDestroyed();
    if (!this.#state) throw new Error('No active form - call newForm()/loadForm()/hydrate() first');
  }

  #assertNotDestroyed() {
    if (this.#destroyed) throw new Error('FormBuilder instance has been destroyed');
  }
}
