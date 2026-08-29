/** Minimal dependency-free event emitter used by FormState and the public API. */
export class EventEmitter {
  #listeners = new Map();

  /**
   * @param {string} event
   * @param {(...args: any[]) => void} handler
   * @returns {() => void} unsubscribe function
   */
  on(event, handler) {
    if (!this.#listeners.has(event)) this.#listeners.set(event, new Set());
    this.#listeners.get(event).add(handler);
    return () => this.off(event, handler);
  }

  /** @param {string} event @param {(...args: any[]) => void} handler */
  off(event, handler) {
    this.#listeners.get(event)?.delete(handler);
  }

  /** @param {string} event @param {...any} args */
  emit(event, ...args) {
    for (const handler of this.#listeners.get(event) ?? []) handler(...args);
  }

  /** Removes every listener for every event. Called by destroy() paths. */
  removeAllListeners() {
    this.#listeners.clear();
  }
}
