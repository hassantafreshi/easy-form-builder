import { EventEmitter } from '../core/EventEmitter.js';
import { deserializeForm, serializeForm, getSteps, getFields, getOptionsForField } from '../serialization/formSerializer.js';

/**
 * Owns Form Builder state so the UI is never the authoritative holder of
 * persisted form data (see ../../docs/PUBLIC_API.md "STATE EXTRACTION").
 *
 * This is new code for a *future* UI layer sitting behind the same
 * WordPress adapter/AJAX contract as the legacy builder (docs/AJAX_API.md).
 * It does not replace or reach into the legacy builder's own globals
 * (`valj_efb`, etc.) - those stay untouched under src/ui-legacy per Phase 4.
 * The reorder algorithm here is a clean-room implementation constrained only
 * by the confirmed wire contract (docs/STATE_SCHEMA.md): `amount` establishes
 * order within a step and does not need to be contiguous (legacy seed
 * templates themselves skip values), so any monotonically-ordered
 * renumbering that preserves relative order is schema-compatible and keeps
 * forms openable by the legacy builder.
 *
 * Events emitted (docs/PUBLIC_API.md "Preferred events"): ready, stateChanged,
 * fieldAdded, fieldUpdated, fieldRemoved, fieldReordered.
 */
export class FormState extends EventEmitter {
  /** @type {Record<string, unknown>[]} */
  #rows = [];
  /** @type {number|null} */
  #formId = null;
  /** @type {'create'|'edit'} */
  #mode = 'create';
  #dirty = false;
  /** @type {'idle'|'loading'|'loaded'|'error'} */
  #loadState = 'idle';
  /** @type {'idle'|'saving'|'saved'|'error'} */
  #saveState = 'idle';

  /**
   * Build a fresh, empty create-mode state (no rows). Mirrors the legacy
   * "form"/"payment" template ids that seed an empty valj_efb array
   * (admin-efb.js:1056-1058, 1163-1166) rather than one of the pre-filled
   * template seeds - those seeds are fixture data, not engine behavior, and
   * belong in fixtures/legacy-templates/, applied via hydrate() if wanted.
   */
  static newForm() {
    const state = new FormState();
    state.#mode = 'create';
    state.#rows = [];
    return state;
  }

  /**
   * Build state from a loaded form's raw payload (the `ajax_value` string,
   * or an already-parsed array) plus the id the server returned it for.
   * @param {string|unknown[]} raw
   * @param {number} formId
   */
  static fromLoadedForm(raw, formId) {
    const state = new FormState();
    state.#rows = deserializeForm(raw);
    state.#formId = formId;
    state.#mode = 'edit';
    state.#loadState = 'loaded';
    return state;
  }

  /** @returns {Readonly<{formId: number|null, mode: string, rows: Record<string, unknown>[], dirty: boolean, loadState: string, saveState: string}>} */
  getState() {
    return {
      formId: this.#formId,
      mode: this.#mode,
      rows: this.#rows.map((r) => ({ ...r })),
      dirty: this.#dirty,
      loadState: this.#loadState,
      saveState: this.#saveState,
    };
  }

  /**
   * Replace the whole state wholesale (e.g. undo/redo, external sync).
   * Does not itself validate - call validate() separately if needed.
   * @param {{formId?: number|null, mode?: 'create'|'edit', rows: Record<string, unknown>[]}} next
   */
  setState(next) {
    this.#rows = next.rows.map((r) => ({ ...r }));
    if ('formId' in next) this.#formId = next.formId ?? null;
    if ('mode' in next) this.#mode = next.mode;
    this.#markDirty();
    this.emit('stateChanged', this.getState());
  }

  /** @returns {Record<string, unknown>} the settings row (element [0]) */
  getSettings() {
    return { ...this.#rows[0] };
  }

  /** @param {Record<string, unknown>} patch shallow-merged into the settings row */
  updateSettings(patch) {
    this.#rows[0] = { ...(this.#rows[0] ?? {}), ...patch };
    this.#markDirty();
    this.emit('stateChanged', this.getState());
  }

  getSteps() {
    return getSteps(this.#rows).map((r) => ({ ...r }));
  }

  getFields() {
    return getFields(this.#rows).map((r) => ({ ...r }));
  }

  /** @param {string} fieldId the field row's own id_ */
  getOptions(fieldId) {
    return getOptionsForField(this.#rows, fieldId).map((r) => ({ ...r }));
  }

  /**
   * Append a new row (field, step, or option). Caller supplies a complete
   * row object including a unique `id_` - id generation is a UI/adapter
   * concern (the legacy builder generates short random ids; this layer
   * does not assume any particular id format since none is documented as
   * load-bearing beyond "stable and unique per field").
   * @param {Record<string, unknown>} row
   */
  addField(row) {
    if (!row || typeof row.id_ === 'undefined') {
      throw new TypeError('addField: row must have an id_');
    }
    this.#rows.push({ ...row });
    this.#markDirty();
    this.emit('fieldAdded', { ...row });
    this.emit('stateChanged', this.getState());
  }

  /**
   * Shallow-merge a patch into the row matching id_. No-op (returns false)
   * if no such row exists, rather than throwing - callers doing bulk/async
   * updates against a UI the user may have already changed should be able
   * to check this without a try/catch.
   * @param {string} id
   * @param {Record<string, unknown>} patch
   */
  updateField(id, patch) {
    const idx = this.#rows.findIndex((r) => r && r.id_ === id);
    if (idx === -1) return false;
    this.#rows[idx] = { ...this.#rows[idx], ...patch };
    this.#markDirty();
    this.emit('fieldUpdated', { ...this.#rows[idx] });
    this.emit('stateChanged', this.getState());
    return true;
  }

  /**
   * Remove a row by id_. Also removes any option rows whose `parent`
   * references it (select/checkbox/radio children), matching the schema's
   * parent/child relationship (docs/STATE_SCHEMA.md "Option row").
   * @param {string} id
   */
  removeField(id) {
    const removed = this.#rows.find((r) => r && r.id_ === id);
    if (!removed) return false;
    this.#rows = this.#rows.filter((r) => r === this.#rows[0] || (r.id_ !== id && r.parent !== id));
    this.#markDirty();
    this.emit('fieldRemoved', { id });
    this.emit('stateChanged', this.getState());
    return true;
  }

  /**
   * Move a field to a new position within its step, renumbering `amount`
   * for every row in that step to reflect the new relative order. See the
   * class doc comment for why this is schema-compatible without needing to
   * match the legacy drag/drop algorithm byte-for-byte.
   * @param {string} id
   * @param {number} toIndex zero-based index among fields in the same step
   */
  reorderField(id, toIndex) {
    const moving = this.#rows.find((r) => r && r.id_ === id);
    if (!moving) return false;
    const step = moving.step;
    const stepRows = this.#rows.filter((r) => r && r.step === step && r.type !== 'option');
    const without = stepRows.filter((r) => r.id_ !== id);
    const clamped = Math.max(0, Math.min(toIndex, without.length));
    without.splice(clamped, 0, moving);
    without.forEach((row, i) => {
      row.amount = i + 1;
    });
    this.#markDirty();
    this.emit('fieldReordered', { id, toIndex: clamped });
    this.emit('stateChanged', this.getState());
    return true;
  }

  isDirty() {
    return this.#dirty;
  }

  /** @param {number} formId */
  setFormId(formId) {
    this.#formId = formId;
  }

  getFormId() {
    return this.#formId;
  }

  getMode() {
    return this.#mode;
  }

  /** Marks state as no longer dirty (call after a confirmed successful save). */
  clearDirty() {
    this.#dirty = false;
  }

  /** @param {'idle'|'loading'|'loaded'|'error'} s */
  setLoadState(s) {
    this.#loadState = s;
  }

  /** @param {'idle'|'saving'|'saved'|'error'} s */
  setSaveState(s) {
    this.#saveState = s;
  }

  /** @returns {string} JSON string in the exact wire shape the server expects */
  serialize() {
    return serializeForm(this.#rows);
  }

  #markDirty() {
    this.#dirty = true;
  }
}
