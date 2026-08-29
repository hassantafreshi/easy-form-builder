/**
 * Lossless serializer/deserializer for the Easy Form Builder saved-form payload.
 *
 * Wire shape (confirmed in ../../docs/STATE_SCHEMA.md against admin-efb.js's seed
 * templates and the PHP read/write handlers in class-Emsfb-admin.php /
 * class-Emsfb-create.php): a single JSON array where element [0] is the form
 * settings object and elements [1..n] are step / field / option rows,
 * discriminated by their own `type` property (or by position for [0]).
 *
 * This module never enumerates a fixed key allowlist. Every row is passed
 * through as a plain object clone so that unknown/legacy/add-on keys this
 * audit has not catalogued yet survive a load -> edit -> save round trip
 * unchanged (see STATE_SCHEMA.md "Non-negotiable round-trip rule").
 */

/**
 * Parse a raw form payload (either the JSON string the server sends as
 * `ajax_value` / `form_structer`, or an already-parsed array) into plain
 * row objects. Never drops unknown properties.
 * @param {string|unknown[]} raw
 * @returns {Record<string, unknown>[]}
 */
export function deserializeForm(raw) {
  const arr = typeof raw === 'string' ? JSON.parse(raw) : raw;
  if (!Array.isArray(arr)) {
    throw new TypeError('deserializeForm: expected a JSON array (form_structer shape)');
  }
  return arr.map((row) => (row && typeof row === 'object' ? { ...row } : row));
}

/**
 * Serialize rows back to the exact array shape the server expects as the
 * `value` POST field for add_form_Emsfb / update_form_Emsfb (see
 * ../../docs/AJAX_API.md). Returns a JSON string; the WordPress adapter is
 * responsible for any transport-level escaping, not this module.
 * @param {Record<string, unknown>[]} rows
 * @returns {string}
 */
export function serializeForm(rows) {
  if (!Array.isArray(rows)) {
    throw new TypeError('serializeForm: expected an array of rows');
  }
  return JSON.stringify(rows);
}

/** @param {Record<string, unknown>[]} rows */
export function getFormSettings(rows) {
  return rows[0];
}

/** @param {Record<string, unknown>[]} rows */
export function getSteps(rows) {
  return rows.filter((r) => r && r.type === 'step');
}

/**
 * Field rows: not a step row, not an option row, and not element [0].
 * @param {Record<string, unknown>[]} rows
 */
export function getFields(rows) {
  return rows.slice(1).filter((r) => r && r.type !== 'step' && r.type !== 'option');
}

/**
 * Option rows belonging to a given field id_ (select/checkbox/radio children).
 * @param {Record<string, unknown>[]} rows
 * @param {string} fieldId
 */
export function getOptionsForField(rows, fieldId) {
  return rows.filter((r) => r && r.type === 'option' && r.parent === fieldId);
}

/**
 * SMS/Telegram notification templates are stripped from `form_structer` by
 * the server on save and re-merged on load (docs/AJAX_API.md, "update_form_Emsfb").
 * The in-memory client state DOES carry them after a load. This helper exists
 * so a future UI never has to special-case that asymmetry itself - it just
 * reads/writes the settings row like any other field, and the WordPress
 * adapter's save path is what must remember the server will silently drop
 * these keys from the persisted JSON (they still round-trip correctly
 * because the server re-attaches them from its own tables on next load).
 * @param {Record<string, unknown>[]} rows
 */
export function getNotificationChannelKeys() {
  return {
    sms: ['sms_msg_new_noti', 'sms_msg_responsed_noti', 'sms_msg_recived_usr', 'sms_admins_phone_no'],
    telegram: [
      'telegram_msg_new_noti',
      'telegram_msg_responsed_noti',
      'telegram_msg_recived_usr',
      'telegram_bot_token',
      'telegram_admin_chat_ids',
    ],
  };
}
