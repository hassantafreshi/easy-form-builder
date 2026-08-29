/**
 * Compatibility bridge: makes a future replacement UI (built on FormBuilder,
 * see ../public-api/FormBuilder.js) satisfy the same global function
 * contract legacy scripts call directly, so those callers keep working
 * without modification. See ../../docs/INTEGRATION_CONTRACT.md.
 *
 * NOT wired up anywhere yet - nothing in this project or in the live
 * plugin calls installLegacyGlobalsBridge(). It exists as a ready-to-use
 * seam for the moment a real replacement UI exists to install it, per
 * REDESIGN_HANDOFF.md. Until then the legacy JS files (ui-legacy /
 * legacy-snapshot) keep defining these functions themselves, unmodified,
 * inside WordPress - so there is nothing to bridge yet.
 *
 * Only the specific global names confirmed as cross-file call targets in
 * docs/GLOBAL_DEPENDENCIES.md are covered. Do not add more without a
 * corresponding confirmed caller - an unconfirmed bridge entry is worse
 * than none, since it invites a future UI to satisfy a contract nobody
 * actually depends on while missing ones that matter.
 *
 * @param {import('../public-api/FormBuilder.js').FormBuilder} formBuilder
 * @param {{ render(container: HTMLElement, state: unknown): void }} view
 *   the replacement UI's render function, invoked wherever legacy code
 *   expected editFormEfb()/creator_form_builder_Efb() to (re)draw the canvas
 * @param {HTMLElement} container
 */
export function installLegacyGlobalsBridge(formBuilder, view, container) {
  const g = /** @type {Record<string, unknown>} */ (globalThis);

  // list_form-efb.js:472-480 fun_ws_show_edit_form -> creator_form_builder_Efb(); editFormEfb()
  g.creator_form_builder_Efb = () => {
    view.render(container, formBuilder.getState());
  };
  g.editFormEfb = () => {
    view.render(container, formBuilder.getState());
  };

  // list_form-efb.js:383-388 - list-page "Edit" click entry point
  g.emsFormBuilder_get_edit_form = (id) => {
    formBuilder.loadForm(Number(id)).then(() => {
      view.render(container, formBuilder.getState());
    });
  };

  // admin-efb.js:498 actionSendData_emsFormBuilder(saveMode) - Save button entry point.
  // saveMode is accepted for signature compatibility (legacy passes 1 for an
  // explicit save vs. 0 for a silent pre-preview save) but this bridge does
  // not yet replicate that autosave-clearing distinction - see
  // docs/ARCHITECTURE.md "Autosave" for what a full port would need to match.
  g.actionSendData_emsFormBuilder = async (_saveMode) => {
    const state = formBuilder.getState();
    if (state && state.formId) {
      await formBuilder.update();
    } else {
      await formBuilder.save();
    }
  };

  return function uninstallLegacyGlobalsBridge() {
    delete g.creator_form_builder_Efb;
    delete g.editFormEfb;
    delete g.emsFormBuilder_get_edit_form;
    delete g.actionSendData_emsFormBuilder;
  };
}
