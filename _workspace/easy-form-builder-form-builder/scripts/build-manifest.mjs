#!/usr/bin/env node
/**
 * Builds dist/efb-form-builder.manifest.json from the legacy-snapshot/ files
 * and the SHA-256 hash list generated during Phase 1. Metadata below is
 * transcribed from docs/SOURCE_MAP.md, docs/ARCHITECTURE.md,
 * docs/GLOBAL_DEPENDENCIES.md, docs/CSS_SCOPE_REPORT.md and
 * docs/ADDON_COMPATIBILITY.md - every field traces back to those documents,
 * nothing here is invented. Re-run after any legacy-snapshot change.
 */
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync, statSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const ROOT = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const SNAPSHOT_DIR = path.join(ROOT, 'legacy-snapshot');
const OUT = path.join(ROOT, 'dist', 'efb-form-builder.manifest.json');

/**
 * Metadata keyed by the file's path relative to legacy-snapshot/ (which
 * mirrors its original plugin-relative path). `load_order` is the position
 * within its own type's enqueue sequence (per CSS_SCOPE_REPORT.md /
 * SOURCE_MAP.md), not a global ordinal - scripts and styles have separate
 * sequences, and PHP files aren't "enqueued" at all (load_order: null).
 */
const META = {
  'includes/admin/class-Emsfb-create.php': { type: 'php', required: true, ajax_dependencies: ['add_form_Emsfb'], runtime_conditions: ['page=Emsfb_create'], notes: 'Builder shell + script/style registration + efb_var (create context)' },
  'includes/admin/class-Emsfb-admin.php': { type: 'php', required: true, ajax_dependencies: ['update_form_Emsfb', 'get_form_id_Emsfb', 'remove_id_Emsfb', 'dup_efb', 'heartbeat_Emsfb'], runtime_conditions: ['any Emsfb* admin screen'], notes: 'Every form-CRUD handler except create; plugin-admin-wide CSS/JS enqueue' },
  'includes/admin/class-Emsfb-panel.php': { type: 'php', required: true, ajax_dependencies: [], runtime_conditions: ['page=Emsfb'], notes: 'Forms list shell; efb_var (panel context) + ajax_object_efm (inline forms list)' },
  'includes/admin/class-Emsfb-addon.php': { type: 'php', required: false, notes: 'Add-ons marketplace page; not form-CRUD' },
  'includes/class-Emsfb-install.php': { type: 'php', required: true, notes: 'emsfb_form table schema + related tables' },
  'includes/functions.php': { type: 'php', required: true, notes: 'efb_list_form(), text_efb(), sanitizers, addon key registry' },
  'includes/class-Emsfb.php': { type: 'php', required: true, notes: 'Add-on class-instantiation gating; unrelated public-REST nonce filter' },
  'includes/class-Emsfb-public.php': { type: 'php', required: false, notes: 'Public front-end REST routes; shares wp_rest nonce action name only' },
  'includes/class-Emsfb-addon-compatibility.php': { type: 'php', required: true, notes: 'PHP-version/function-availability compatibility check' },
  'includes/page-builders/gutenberg/class-Emsfb-gutenberg-block.php': { type: 'php', required: false, notes: 'Read-only form picker REST routes, adjacent not builder CRUD' },

  'includes/admin/assets/js/new-efb.js': { type: 'js', required: true, load_order: 12, globals_written: ['valj_efb', 'mobile_view_efb', 'activeEl_efb', 'amount_el_efb', 'step_el_efb', 'steps_index_efb', 'maps_efb', 'state_efb', 'formName_Efb', 'page_state_efb', 'sendback_efb_state'], dom_dependencies: ['#settingModalEfb'], notes: 'Global state declarations + shared modal primitives; also loaded on public frontend' },
  'includes/admin/assets/js/admin-efb.js': { type: 'js', required: true, load_order: 7, globals_read: ['valj_efb', 'efb_var'], globals_written: ['state_check_ws_p', 'valueJson_ws_p', 'form_ID_emsFormBuilder', 'form_type_emsFormBuilder', '_efb_nonce_'], dom_dependencies: ['#dropZoneEFB', '#tab_container_efb', '#settingModalEfb', '#sideBoxEfb'], ajax_dependencies: ['add_form_Emsfb', 'update_form_Emsfb', 'heartbeat_Emsfb'], notes: 'Core engine: editFormEfb() render loop, field CRUD, native DnD, save/autosave' },
  'includes/admin/assets/js/val-efb.js': { type: 'js', required: true, load_order: 8, globals_read: ['valj_efb'], dom_dependencies: ['#content-efb', '#dropZoneEFB', '.draggable-efb'], notes: 'creator_form_builder_Efb(), property-panel body generator, field palette catalog' },
  'includes/admin/assets/js/list_form-efb.js': { type: 'js', required: true, load_order: null, ajax_dependencies: ['get_form_id_Emsfb'], notes: 'Panel-page only; load-for-edit entry point, deep-link handling. NOT enqueued on Emsfb_create.' },
  'includes/admin/assets/js/core-efb.js': { type: 'js', required: true, load_order: 11, globals_written: ['_efb_core_nonce_'], notes: 'Shared public-form rendering primitives; also loaded on public frontend' },
  'includes/admin/assets/js/forms-efb.js': { type: 'js', required: true, load_order: 10, notes: 'Shared form-rendering helpers' },
  'includes/admin/assets/js/pro_els-efb.js': { type: 'js', required: true, load_order: 9, notes: 'Pro-plan upsell UI elements' },
  'includes/admin/assets/js/bootstrap-select.min-efb.js': { type: 'js', required: true, load_order: 13, vendor: 'bootstrap-select v1.13.1' },
  'includes/admin/assets/js/intlTelInput.min-efb.js': { type: 'js', required: true, load_order: 4, vendor: 'intlTelInput' },
  'includes/admin/assets/js/jquery-ui-efb.js': { type: 'js', required: true, load_order: 1, vendor: 'jQuery UI 1.13.1 (widget/position/effects/mouse only)', notes: 'Only .sortable() is actually used' },
  'includes/admin/assets/js/jquery-dd-efb.js': { type: 'js', required: true, load_order: 2, vendor: 'jQuery UI Mouse touch-punch polyfill' },
  'includes/admin/assets/js/email-template-builder-efb.js': { type: 'js', required: false, load_order: null, runtime_conditions: ['Panel page only'], notes: 'Email template builder, dep on list_form-efb.js' },
  'includes/admin/assets/js/response-viewer-efb.js': { type: 'js', required: false, notes: 'Response/message viewer, loaded alongside builder' },
  'public/assets/js/stripe_pay-efb.js': { type: 'js', required: false, load_order: 5, runtime_conditions: ['enqueued unconditionally on create screen, functionally gated by AdnSPF at the field-palette level'], notes: 'Stripe field preview/logic' },
  'public/assets/js/recorder-efb.js': { type: 'js', required: true, load_order: 6, notes: 'Audio/video/screen recorder field type, unconditional' },

  'includes/admin/assets/css/admin-efb.css': { type: 'css', required: true, load_order: 1, runtime_conditions: ['every Emsfb* admin screen'] },
  'includes/admin/assets/css/admin-rtl-efb.css': { type: 'css', required: false, load_order: 2, runtime_conditions: ['is_rtl()'], notes: 'Loads before Bootstrap in the same enqueue call - needs ID-level specificity to override' },
  'includes/admin/assets/css/style-efb.css': { type: 'css', required: true, load_order: 3 },
  'includes/admin/assets/css/min-1200-style.css': { type: 'css', required: true, load_order: 4, notes: 'Actual breakpoint is max-width:1320px despite filename' },
  'includes/admin/assets/css/bootstrap.min-efb.css': { type: 'css', required: true, load_order: 5, vendor: 'Bootstrap 5.0.1' },
  'includes/admin/assets/css/bootstrap-icons-efb.css': { type: 'css', required: true, load_order: 6, vendor: 'Bootstrap Icons' },
  'includes/admin/assets/css/bootstrap-select-efb.css': { type: 'css', required: true, load_order: 7, vendor: 'bootstrap-select v1.13.1' },
  'includes/admin/assets/css/response-viewer-efb.css': { type: 'css', required: false, load_order: 8 },
  'includes/admin/assets/css/intlTelInput.min-efb.css': { type: 'css', required: true, load_order: 11, vendor: 'intlTelInput' },
  'includes/admin/assets/css/recorder-efb.css': { type: 'css', required: true, load_order: 12 },
  'includes/admin/assets/css/fonts/bootstrap-icons.woff': { type: 'font', required: true, notes: 'Referenced via @font-face in bootstrap-icons-efb.css' },
  'includes/admin/assets/css/fonts/bootstrap-icons.woff2': { type: 'font', required: true, notes: 'Referenced via @font-face in bootstrap-icons-efb.css' },

  'includes/admin/assets/image/logo-easy-form-builder.svg': { type: 'image', required: true, notes: 'efb_var.images.logo' },
  'includes/admin/assets/image/header.png': { type: 'image', required: true, notes: 'efb_var.images.head' },
  'includes/admin/assets/image/title.svg': { type: 'image', required: true, notes: 'efb_var.images.title' },
  'includes/admin/assets/image/reCaptcha.png': { type: 'image', required: true, notes: 'efb_var.images.recaptcha' },
  'includes/admin/assets/image/move-button.gif': { type: 'image', required: true, notes: 'efb_var.images.movebtn' },
  'includes/admin/assets/image/efb-256.gif': { type: 'image', required: true, notes: 'efb_var.images.logoGif' },
  'includes/admin/assets/image/flags.png': { type: 'image', required: false, notes: 'intlTelInput sprite fallback' },
  'includes/admin/assets/image/flags.webp': { type: 'image', required: true, notes: 'intlTelInput sprite' },
  'includes/admin/assets/image/flags@2x.png': { type: 'image', required: false, notes: 'intlTelInput sprite fallback' },
  'includes/admin/assets/image/flags@2x.webp': { type: 'image', required: true, notes: 'intlTelInput sprite' },
  'includes/admin/assets/image/globe.webp': { type: 'image', required: true, notes: 'intlTelInput sprite' },
  'includes/admin/assets/image/globe@2x.webp': { type: 'image', required: true, notes: 'intlTelInput sprite' },

  'vendor/stripe/routes-efb.php': { type: 'php', required: false, addon: 'AdnSPF', notes: 'Payment REST checkout routes, not builder-save coupled' },
  'vendor/paypal/paypalefb.php': { type: 'php', required: false, addon: 'AdnPAP', runtime_conditions: ['instantiated directly by class-Emsfb-create.php on the builder screen'], notes: 'Enqueues paypal_efb.js unconditionally once instantiated' },
  'vendor/paypal/routes-efb.php': { type: 'php', required: false, addon: 'AdnPAP' },
  'vendor/persiapay/persiapayefb.php': { type: 'php', required: false, addon: 'AdnPPF', runtime_conditions: ['locale=fa_IR AND AdnPPF active'], notes: 'Registers efb_enqueue_persia action listener' },
  'vendor/persiapay/routes-efb.php': { type: 'php', required: false, addon: 'AdnPPF' },
  'vendor/persiadatepicker/persiandate.php': { type: 'php', required: false, addon: 'AdnPDP', notes: 'Mutually exclusive with AdnADP in add-ons settings UI' },
  'vendor/arabicdatepicker/arabicdate.php': { type: 'php', required: false, addon: 'AdnADP', notes: 'Binds to .hijri-picker class rendered by core admin-efb.js, not by this add-on' },
  'vendor/offline/json/countries.js': { type: 'js', required: false, addon: 'AdnOF', notes: 'Local data source swapped in for the CDN countries-js when active' },
  'vendor/smssended/smsefb.php': { type: 'php', required: false, addon: 'AdnSS', notes: 'Piggybacks the core save handler directly (special case, not a hook) - see AJAX_API.md' },
  'vendor/telegram/telegram-new-efb.php': { type: 'php', required: false, addon: 'AdnTLG', notes: 'Uses genuine WP action/filter hooks fired from the public submit pipeline' },
  'vendor/autofill/autofillefb.php': { type: 'php', required: false, addon: 'AdnATF' },
  'vendor/logic/logic/class-Emsfb-logic-validator.php': { type: 'php', required: false, addon: 'AdnSMF', notes: 'Registers filters only (efb_logic_evaluate etc.), consumed at submission time. vendor/logic/ and vendor/_logic/ duplicate copies exist and are dead - not snapshotted.' },
  'vendor/logic/logic/assets/admin/js/conditional-logic-efb.js': { type: 'js', required: false, addon: 'AdnSMF', dom_dependencies: ['#settingModalEfb'], notes: 'window.EFB_Logic; renders into the shared builder modal, not its own' },
  'vendor/logic/logic/assets/public/js/conditional-logic-efb.js': { type: 'js', required: false, addon: 'AdnSMF', notes: 'Front-end evaluator/preview counterpart' },
  'vendor/logic/logic/assets/admin/css/conditional-logic-efb.css': { type: 'css', required: false, addon: 'AdnSMF' },
};

function sha256(filePath) {
  return createHash('sha256').update(readFileSync(filePath)).digest('hex');
}

function walk(dir, base = dir) {
  const entries = readdirSync(dir, { withFileTypes: true });
  let files = [];
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) files = files.concat(walk(full, base));
    else files.push(path.relative(base, full).split(path.sep).join('/'));
  }
  return files;
}

const files = walk(SNAPSHOT_DIR).sort();
const manifest = {
  generated_at: new Date().toISOString(),
  source_branch: 'dev4',
  source_commit: process.env.EFB_SOURCE_COMMIT ?? null,
  file_count: files.length,
  files: files.map((rel) => {
    const abs = path.join(SNAPSHOT_DIR, rel);
    const meta = META[rel] ?? { type: 'unknown', required: null, notes: 'No metadata authored yet - see docs before treating as load-bearing' };
    return {
      original_path: rel,
      extracted_path: `legacy-snapshot/${rel}`,
      hash: `sha256:${sha256(abs)}`,
      size_bytes: statSync(abs).size,
      type: meta.type,
      load_order: meta.load_order ?? null,
      globals_read: meta.globals_read ?? [],
      globals_written: meta.globals_written ?? [],
      dom_dependencies: meta.dom_dependencies ?? [],
      css_dependencies: meta.css_dependencies ?? [],
      php_dependencies: meta.php_dependencies ?? [],
      ajax_dependencies: meta.ajax_dependencies ?? [],
      runtime_conditions: meta.runtime_conditions ?? [],
      addon: meta.addon ?? null,
      vendor: meta.vendor ?? null,
      required: meta.required ?? null,
      notes: meta.notes ?? null,
    };
  }),
};

writeFileSync(OUT, JSON.stringify(manifest, null, 2) + '\n');
console.log(`Wrote ${OUT} (${files.length} files)`);
