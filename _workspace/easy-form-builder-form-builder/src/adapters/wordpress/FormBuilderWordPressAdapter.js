/**
 * Wraps the existing, unmodified WordPress AJAX contract documented in
 * ../../../docs/AJAX_API.md. Future UI code must go through this adapter
 * instead of calling jQuery.ajax/admin-ajax.php directly (see
 * ../../../docs/PUBLIC_API.md "WORDPRESS ADAPTER").
 *
 * This does not change any endpoint, parameter name, response shape, nonce
 * action name, or capability check on the server. It only:
 *  - unwraps the wp_send_json_success() double-envelope quirk (res.data.success
 *    is the real result; the outer res.success from WP core is always true)
 *    into a plain boolean/throw contract for callers,
 *  - centralizes the nonce + ajax_url + form field names in one place.
 */

/**
 * @typedef {Object} EfbRuntimeConfig
 * @property {string} ajaxUrl      admin-ajax.php URL (efb_var.ajax_url / ajax_object_efm.ajax_url)
 * @property {string} nonce        current wp_rest nonce value
 * @property {(input: RequestInfo, init?: RequestInit) => Promise<Response>} [fetchImpl]
 */

class EfbServerError extends Error {
  /** @param {string} message @param {unknown} [responseData] */
  constructor(message, responseData) {
    super(message);
    this.name = 'EfbServerError';
    this.responseData = responseData;
  }
}

export class FormBuilderWordPressAdapter {
  /** @param {EfbRuntimeConfig} config */
  constructor(config) {
    this.ajaxUrl = config.ajaxUrl;
    this.nonce = config.nonce;
    this.fetch = config.fetchImpl ?? globalThis.fetch.bind(globalThis);
  }

  /**
   * @param {Record<string, string>} params
   * @returns {Promise<any>} the unwrapped `res.data` object
   */
  async #post(params) {
    const body = new URLSearchParams({ nonce: this.nonce, ...params });
    const response = await this.fetch(this.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });
    if (!response.ok) {
      throw new EfbServerError(`HTTP ${response.status} calling ${params.action}`);
    }
    const json = await response.json();
    // wp_send_json_success() always sets the outer envelope's success to
    // true; the real app-level result is json.data.success (docs/AJAX_API.md).
    if (!json || !json.data) {
      throw new EfbServerError(`Malformed response for ${params.action}`, json);
    }
    return json.data;
  }

  /**
   * @param {{ name: string, type: string, value: string, email?: string }} form
   * @returns {Promise<{id: number, shortcode: string}>}
   */
  async createForm(form) {
    const data = await this.#post({
      action: 'add_form_Emsfb',
      name: form.name,
      type: form.type,
      value: form.value,
      ...(form.email ? { email: form.email } : {}),
    });
    if (data.success !== true) {
      throw new EfbServerError(data.m ?? 'add_form_Emsfb failed', data);
    }
    return { id: Number(data.id), shortcode: data.value };
  }

  /**
   * @param {{ id: number, name: string, value: string }} form
   * @returns {Promise<{shortcode: string}>}
   */
  async updateForm(form) {
    const data = await this.#post({
      action: 'update_form_Emsfb',
      id: String(form.id),
      name: form.name,
      value: form.value,
    });
    if (data.success !== true) {
      throw new EfbServerError(data.m ?? 'update_form_Emsfb failed', data);
    }
    return { shortcode: data.value };
  }

  /**
   * @param {number} id
   * @returns {Promise<{id: number, rawValue: string}>} rawValue is the
   *   `form_structer` JSON string, unparsed - pass to
   *   serialization/formSerializer.js#deserializeForm.
   */
  async loadForm(id) {
    const data = await this.#post({ action: 'get_form_id_Emsfb', id: String(id) });
    if (data.success !== true) {
      throw new EfbServerError(data.m ?? 'get_form_id_Emsfb failed', data);
    }
    return { id: Number(data.id), rawValue: data.ajax_value };
  }

  /**
   * @param {number} id
   * @returns {Promise<{deletedRows: number}>}
   */
  async deleteForm(id) {
    const data = await this.#post({ action: 'remove_id_Emsfb', id: String(id) });
    return { deletedRows: Number(data.r ?? 0) };
  }

  /**
   * @param {number} id
   * @param {string} type must be 'form' - the server no-ops other types (docs/AJAX_API.md)
   * @returns {Promise<{id: number, name: string}>}
   */
  async duplicateForm(id, type) {
    const data = await this.#post({ action: 'dup_efb', id: String(id), type });
    if (data.success !== true) {
      throw new EfbServerError(data.m ?? 'dup_efb failed', data);
    }
    return { id: Number(data.form_id), name: data.form_name };
  }

  /** Updates the nonce held by this adapter (e.g. after a heartbeat refresh). */
  setNonce(nonce) {
    this.nonce = nonce;
  }
}

export { EfbServerError };
