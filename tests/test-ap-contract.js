/**
 * AutoPilot Part C: the contract AutoPilot relies on today is still in place.
 *
 * Static checks over the shipped files for every name in Part C of
 * docs/autopilot/EFB_INTEGRATION_PROMPT_FA.md (globals, functions, AJAX actions and answers,
 * options, form keys, fixed ids, shortcode), plus the new API version markers.
 *
 * Run: node tests/test-ap-contract.js
 */
'use strict';
const fs = require('fs');
const path = require('path');
const ROOT = path.join(__dirname, '..');
const r = (p) => fs.readFileSync(path.join(ROOT, p), 'utf8');
let pass = 0, fail = 0;
const check = (l, ok) => { if (ok) { pass++; console.log('[PASS] ' + l); } else { fail++; console.log('[FAIL] ' + l); } };
const js = ['admin-efb.js', 'val-efb.js', 'new-efb.js', 'pro_els-efb.js'].map((f) => r('includes/admin/assets/js/' + f)).join('\n');
const pub = r('includes/class-Emsfb-public.php');
const adm = r('includes/admin/class-Emsfb-admin.php');

check('JS: let valj_efb', /\nlet valj_efb = \[\];/.test(js));
check('JS: sessionStorage valj_efb read by editFormEfb()', /let editFormEfb =async \(\) => \{[\s\S]{0,300}sessionStorage\.getItem\('valj_efb'\)/.test(js));
check('JS: addNewElement(elementId, rndm, editState, previewSate)', /function addNewElement\(elementId, rndm, editState, previewSate\)/.test(js));
check('JS: show_setting_window_efb(idset) still toggles without opts', /function show_setting_window_efb\(idset, opts\) \{\n\s*if\(document\.getElementById\('sideBoxEfb'\)\.classList\.contains\('show'\) && !\(opts && opts\.refresh\)\)\{\n\s*if \(window\.efbOpenSettingIdEfb === idset \|\| window\.efbOpenSettingIdEfb === null\)/.test(js));
check('JS: sideMenuEfb(s), window.efbOpenSettingIdEfb, #sideBoxEfb.show', /function sideMenuEfb\(s\)/.test(js) && /window\.efbOpenSettingIdEfb = window\.efbOpenSettingIdEfb \|\| null;/.test(js) && /el\.classList\.add\('show'\)/.test(js));
check('JS: saveFormEfb, pro_show_efb(state), form_type_emsFormBuilder, fun_check_link_state_efb', /const saveFormEfb = async \(stated\)/.test(js) && /function pro_show_efb\(state\)/.test(js) && /let form_type_emsFormBuilder = 'form';/.test(js) && /fun_check_link_state_efb=async\(iso2_country , indx,form_id\)/.test(js));
check('JS: efb_var.addons / efb_var.pro / efb_var.text localized', /"addons"=>\$addons/.test(r('includes/admin/class-Emsfb-create.php')) && /'pro' => \$pro \? 1 : 0/.test(r('includes/admin/class-Emsfb-create.php')) && /'text' => \$lang/.test(r('includes/admin/class-Emsfb-create.php')));
check('AJAX: add_addons_Emsfb (value, nonce; r done; addon_plan_required + required_package) and remove_id_Emsfb', /wp_ajax_add_addons_Emsfb/.test(adm) && /'r' => "done"/.test(adm) && /addon_plan_required/.test(adm) && /required_package/.test(adm) && /wp_ajax_remove_id_Emsfb/.test(adm));
check('options emsfb_pro and emsfb_settings / setting keys read as before', /get_option\('emsfb_pro'/.test(r('includes/functions.php') + adm));
check('form keys still sanitized as scalars or by their own case (rePage with @efb@, logic_rules ...)', /case 'rePage':/.test(r('includes/functions.php')) && /case 'logic_rules':/.test(r('includes/functions.php')));
check('rePage @efb@ decoded by the server', /function string_to_url\(\$string\)/.test(pub) && /http:@efb@/.test(pub));
check('fixed login ids emaillogin / passwordlogin, register ids', /case 'emaillogin':/.test(pub) && /case 'passwordlogin':/.test(pub) && /case 'usernameRegisterEFB':/.test(pub));
check('html field value markers @! and @efb@nq# still handled', /@efb@nq#/.test(js));
check('shortcode [EMS_Form_Builder id=N]', /\[EMS_Form_Builder id=\$id\]/.test(adm));
check('EMSFB_API_VERSION = 1 and efb_var.api_version', /define\("EMSFB_API_VERSION", 1\);/.test(r('emsfb.php')) && /\$vars\['api_version'\]/.test(r('includes/class-Emsfb-builder-api.php')));
check('builder API enqueued on the Create screen and in the panel', /builder-api-efb\.js/.test(r('includes/admin/class-Emsfb-create.php')) && /builder-api-efb\.js/.test(r('includes/admin/class-Emsfb-panel.php')));
console.log(`\n========================================\nRESULTS: ${pass} passed, ${fail} failed\n========================================`);
process.exit(fail ? 1 : 0);
