let exportView_emsFormBuilder = [];
let stepsCount;
let sessionPub_emsFormBuilder = "reciveFromClient"

/* Submit, Next and Previous are <a role="button"> without href; with
 * tabindex="0" they are reachable by Tab, and this gives them the Enter and
 * Space keys of a real button (WCAG 2.1.1). */
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') return;
  var t = e.target;
  if (!t || t.tagName !== 'A' || t.getAttribute('role') !== 'button' || t.hasAttribute('href') || !t.classList.contains('efb')) return;
  e.preventDefault();
  t.click();
});

function efb_steps_debug_efb(eventName, details) {
  try {
    if (typeof window === 'undefined' || window.EFB_STEPS_DEBUG !== true) return;
    if (typeof console !== 'undefined' && typeof console.log === 'function') {
      console.log('[EFB Steps Debug]', eventName, details || {});
    }
  } catch (e) {}
}

function efb_steps_dom_debug_efb(form_id, scope) {
  try {
    const root = scope || document;
    const body = document.getElementById('body_efb_' + form_id);
    const shell = body ? body.querySelector('.efb-sp') : root.querySelector('.efb-sp');
    const row = body ? body.querySelector('#steps-efb') : root.querySelector('#steps-efb');
    const ring = shell ? shell.querySelector('.efb-sp__ring') : null;
    const fill = shell ? (shell.querySelector('.efb-sp__fill') || shell.querySelector('.progress-bar-efb')) : null;
    const active = row ? row.querySelector('.efb-sp__item.is-active, li.active') : null;
    return {
      form_id: form_id,
      body_found: !!body,
      body_currentstep: body && body.dataset ? body.dataset.currentstep : null,
      body_steps: body && body.dataset ? body.dataset.steps : null,
      shell_found: !!shell,
      shell_classes: shell ? shell.className : null,
      row_found: !!row,
      row_items: row ? row.querySelectorAll('li').length : 0,
      sp_items: shell ? shell.querySelectorAll('.efb-sp__item').length : 0,
      active_id: active ? active.id : null,
      active_num: active ? active.getAttribute('data-num') : null,
      ring_found: !!ring,
      ring_pct: ring ? ring.style.getPropertyValue('--efb-sp-pct') : null,
      ring_text: shell && shell.querySelector('.efb-sp__ring-in') ? shell.querySelector('.efb-sp__ring-in').textContent : null,
      fill_found: !!fill,
      fill_width: fill ? fill.style.width : null,
      sync_function: typeof efbStepsSyncEfb
    };
  } catch (e) {
    return { form_id: form_id, snapshot_error: e && e.message ? e.message : String(e) };
  }
}

function efb_steps_counter_text_fallback_efb(current, total) {
  const tpl = (typeof efb_var !== 'undefined' && efb_var && efb_var.text && efb_var.text.stepXofY)
    ? efb_var.text.stepXofY
    : 'Step %1$s of %2$s';
  return String(tpl).replace('%1$s', current).replace('%2$s', total);
}

function efb_steps_complete_text_fallback_efb(percent) {
  const tpl = (typeof efb_var !== 'undefined' && efb_var && efb_var.text && efb_var.text.percentComplete)
    ? efb_var.text.percentComplete
    : '%s complete';
  return String(tpl).replace('%s', percent + '%');
}

function efb_steps_sync_fallback_efb(scope, current, total, currentName) {
  let root = null;
  if (!scope) {
    root = document.querySelector('.efb-sp');
  } else if (scope.classList && scope.classList.contains('efb-sp')) {
    root = scope;
  } else if (scope.querySelector) {
    root = scope.querySelector('.efb-sp');
  } else {
    root = document.querySelector('.efb-sp[data-formid="' + scope + '"]') || document.querySelector('.efb-sp');
  }
  if (!root) {
    efb_steps_debug_efb('core-fallback:stop:root-missing', { requested_step: current, requested_total: total });
    return false;
  }

  const items = root.querySelectorAll('.efb-sp__item');
  const rowLen = items.length || root.querySelectorAll('#steps-efb li').length;
  total = rowLen || Number(total) || 1;
  current = Number(current) || 1;
  if (current < 1) current = 1;
  if (current > total) current = total;

  let activeName = '';
  for (let i = 0; i < items.length; i++) {
    const num = Number(items[i].getAttribute('data-num')) || (i + 1);
    const state = num < current ? 'is-done' : (num === current ? 'is-active' : 'is-todo');
    items[i].classList.remove('is-done', 'is-active', 'is-todo');
    items[i].classList.add(state);
    if (num === current) {
      items[i].classList.add('active');
      const lab = items[i].querySelector('.efb-sp__label');
      activeName = lab ? lab.textContent : '';
    } else {
      items[i].classList.remove('active');
    }
  }

  const percent = Math.round((current / total) * 10000) / 100;
  const readable = Math.round(percent);
  const counter = efb_steps_counter_text_fallback_efb(current, total);
  const name = typeof currentName === 'string' && currentName !== '' ? currentName : activeName;
  const setText = (sel, value) => {
    root.querySelectorAll(sel).forEach((el) => { el.textContent = value; });
  };

  setText('.efb-sp__counter', counter);
  setText('.efb-sp__metaname', counter);
  setText('.efb-sp__pct', readable + '%');
  if (items.length || (typeof currentName === 'string' && currentName !== '')) setText('.efb-sp__curname', name);
  setText('.efb-sp__ringsub', counter + ' \u00b7 ' + efb_steps_complete_text_fallback_efb(readable));
  setText('.efb-sp__ring-in', current + '/' + total);

  const fill = root.querySelector('.efb-sp__fill') || root.querySelector('.progress-bar-efb');
  if (fill) {
    fill.style.width = percent + '%';
    fill.setAttribute('aria-valuenow', percent);
  }

  const segs = root.querySelectorAll('.efb-sp__seg');
  for (let s = 0; s < segs.length; s++) {
    const n = s + 1;
    segs[s].classList.remove('is-done', 'is-active', 'is-todo');
    segs[s].classList.add(n < current ? 'is-done' : (n === current ? 'is-active' : 'is-todo'));
  }

  const ring = root.querySelector('.efb-sp__ring');
  if (ring) ring.style.setProperty('--efb-sp-pct', percent);

  efb_steps_debug_efb('core-fallback:done', {
    current: current,
    total: total,
    percent: percent,
    name: name,
    items: items.length,
    fill_found: !!fill,
    ring_found: !!ring,
    ring_pct: ring ? ring.style.getPropertyValue('--efb-sp-pct') : null
  });
  return true;
}

/**
 * Conditional logic adapter. The addon-owned public runtime is the only engine
 * allowed to execute saved rules.
 */
function efb_get_logic_runtime_efb() {
  if (typeof window === 'undefined') return null;
  const runtime = window.efb_logic_runtime || window.EFBConditionalLogic;
  return runtime && typeof runtime.evaluate === 'function' ? runtime : null;
}

function fun_statement_logic_efb(triggeredId, triggeredType, formId) {
  const runtime = efb_get_logic_runtime_efb();
  if (!runtime) return;
  let resolvedFormId = formId;
  if ((resolvedFormId === undefined || resolvedFormId === null) && typeof infer_form_id_by_field_efb === 'function') {
    resolvedFormId = infer_form_id_by_field_efb(triggeredId);
  }
  return runtime.evaluate(resolvedFormId || 0);
}

/* Retained only for legacy reference; no public call path invokes this engine. */
function legacy_statement_logic_efb(triggeredId, triggeredType) {
  // console.log('Logic engine triggered by', triggeredId, 'of type', triggeredType);
  if (typeof valj_efb === 'undefined' || !valj_efb[0]) return;

  var rules = valj_efb[0].logic_rules;
  if (!Array.isArray(rules) || rules.length === 0) {
    /* Legacy fallback: no-op (handled by logic-runtime-efb.js if loaded) */
    return;
  }

  /* ── Read current value from sendBack_emsFormBuilder_pub ── */
  function _getFieldValue(fieldId) {
    if (typeof sendBack_emsFormBuilder_pub === 'undefined') return '';
    var fObj = valj_efb.find(function(x) { return x.id_ === fieldId; });
    if (!fObj) return '';
    var type = fObj.type;

    /* Checkbox types: multiple entries, one per checked option */
    if (type === 'checkbox' || type === 'payCheckbox' || type === 'chlCheckBox') {
      return sendBack_emsFormBuilder_pub
        .filter(function(x) { return x && x.id_ === fieldId; })
        .map(function(x) { return x.id_ob || x.value || ''; });
    }

    var row = sendBack_emsFormBuilder_pub.find(function(x) { return x && x.id_ === fieldId; });
    if (!row) return '';

    /* Multiselect: value joined by '@efb!' */
    if (type === 'multiselect' || type === 'payMultiselect') {
      var v = row.value;
      if (!v || typeof v !== 'string') return [];
      return v.split('@efb!').map(function(s) { return s.trim(); }).filter(function(s) { return s.length > 0; });
    }

    /* YesNo: id_ob is '{fieldId}_1' (yes) or '{fieldId}_2' (no) */
    if (type === 'yesNo') {
      if (!row.id_ob) return '';
      return row.id_ob === fieldId + '_1' ? 'yes' : row.id_ob === fieldId + '_2' ? 'no' : '';
    }

    /* Radio types: id_ob = checked option's id_ */
    if (type === 'radio' || type === 'payRadio' || type === 'imgRadio' || type === 'chlRadio') {
      return row.id_ob || '';
    }

    return row.value != null ? String(row.value) : '';
  }

  /* ── Resolve option id_ → display text ── */
  function _optionText(optId) {
    if (!optId) return '';
    var o = valj_efb.find(function(x) { return x.id_ === optId; });
    return o ? (o.value || o.name || '') : '';
  }

  /* ── Evaluate one condition ── */
  function _evalCondition(cond) {
    var val = _getFieldValue(cond.field_id);
    var op  = cond.compare || 'is';
    var exp = cond.value   || '';

    /* For select/country/state/city: cond.value = option id_, row.value = display text */
    var fObj = valj_efb.find(function(x) { return x.id_ === cond.field_id; });
    var selectTypes = ['select','paySelect','conturyList','stateProvince','statePro','country','city','cityList'];
    var effectiveExp = exp;
    if (fObj && selectTypes.indexOf(fObj.type) !== -1 && exp) {
      effectiveExp = _optionText(exp) || exp;
    }

    /* Array (checkbox / multiselect) */
    if (Array.isArray(val)) {
      var isMulti = fObj && (fObj.type === 'multiselect' || fObj.type === 'payMultiselect');
      var arrExp  = isMulti ? (_optionText(exp) || exp) : exp;
      switch (op) {
        case 'is':           return val.indexOf(arrExp) !== -1;
        case 'is_not':       return val.indexOf(arrExp) === -1;
        case 'contains':     return val.some(function(v) { return String(v).toLowerCase().indexOf(arrExp.toLowerCase()) !== -1; });
        case 'not_contains': return !val.some(function(v) { return String(v).toLowerCase().indexOf(arrExp.toLowerCase()) !== -1; });
        case 'is_empty':     return val.length === 0;
        case 'is_not_empty': return val.length > 0;
        default:             return false;
      }
    }

    /* Scalar */
    var sv = String(val).trim();
    switch (op) {
      case 'is':           return sv === effectiveExp || sv === exp;
      case 'is_not':       return sv !== effectiveExp && sv !== exp;
      case 'contains':     return sv.toLowerCase().indexOf(effectiveExp.toLowerCase()) !== -1;
      case 'not_contains': return sv.toLowerCase().indexOf(effectiveExp.toLowerCase()) === -1;
      case 'starts_with':  return sv.toLowerCase().indexOf(effectiveExp.toLowerCase()) === 0;
      case 'ends_with':    return sv.toLowerCase().lastIndexOf(effectiveExp.toLowerCase()) === sv.length - effectiveExp.length && sv.length >= effectiveExp.length;
      case 'gt':           return parseFloat(sv) > parseFloat(effectiveExp);
      case 'lt':           return parseFloat(sv) < parseFloat(effectiveExp);
      case 'is_empty':     return sv === '';
      case 'is_not_empty': return sv !== '';
      default:             return false;
    }
  }

  /* ── Evaluate condition group (AND / OR) ── */
  function _evalGroup(group) {
    if (!group || !group.items || group.items.length === 0) return false;
    var result = false;
    var evalItem = function(c) {
      if (c && (c.type === 'group' || Array.isArray(c.items))) return _evalGroup(c);
      return _evalCondition(c);
    };
    group.items.forEach(function(c, index) {
      var matched = evalItem(c);
      if (index === 0) {
        result = matched;
        return;
      }
      var connector = String((c && c.connector) || group.operator || 'AND').toUpperCase() === 'OR' ? 'OR' : 'AND';
      result = connector === 'OR' ? (result || matched) : (result && matched);
    });
    return result;
  }

  /* ── Capture original state of all rule target fields (runs once) ── */
  if (!fun_statement_logic_efb._states) {
    fun_statement_logic_efb._states = {};
    rules.forEach(function(rule) {
      (rule.actions || []).forEach(function(action) {
        if (!action.target || fun_statement_logic_efb._states.hasOwnProperty(action.target)) return;
        var wrapper = document.getElementById(action.target);
        var fObj    = valj_efb.find(function(x) { return x.id_ === action.target; });
        var inp     = document.getElementById(action.target + '_');
        var sel     = document.querySelector('select[data-vid="' + action.target + '"]');
        fun_statement_logic_efb._states[action.target] = {
          hidden:   wrapper ? wrapper.classList.contains('d-none') : false,
          required: fObj ? (fObj.required == 1 || fObj.required === true) : false,
          disabled: inp ? inp.disabled : (sel ? sel.disabled : false)
        };
      });
    });
  }

  /* ── Reset all targets to original state before applying current rules ── */
  var states = fun_statement_logic_efb._states;
  Object.keys(states).forEach(function(fieldId) {
    var s = states[fieldId];
    var wrapper = document.getElementById(fieldId);
    if (wrapper) {
      s.hidden ? wrapper.classList.add('d-none') : wrapper.classList.remove('d-none');
      wrapper.setAttribute('aria-hidden', String(s.hidden));
    }
    var idx = valj_efb.findIndex(function(x) { return x.id_ === fieldId; });
    if (idx !== -1) valj_efb[idx].required = s.required;
    var reqSpan = document.getElementById(fieldId + '_req');
    if (reqSpan) reqSpan.style.display = s.required ? '' : 'none';
    var inp = document.getElementById(fieldId + '_');
    if (inp) inp.disabled = s.disabled;
    var sel = document.querySelector('select[data-vid="' + fieldId + '"]');
    if (sel) sel.disabled = s.disabled;
    var ms = document.querySelector('.efblist[data-vid="' + fieldId + '"]');
    if (ms) { ms.style.pointerEvents = ''; ms.style.opacity = ''; }
  });

  /* ── Execute one action — only when matched; reversal handled by reset above ── */
  function _execAction(action, matched) {
    if (!action.target || !matched) return;
    var t = action.type;
    var wrapper = document.getElementById(action.target);
    if (t === 'show_field' && wrapper) { wrapper.classList.remove('d-none'); wrapper.setAttribute('aria-hidden', 'false'); }
    if (t === 'hide_field' && wrapper) { wrapper.classList.add('d-none');    wrapper.setAttribute('aria-hidden', 'true');  }
    if (t === 'set_required' || t === 'set_optional') {
      var req = (t === 'set_required');
      var idx = valj_efb.findIndex(function(x) { return x.id_ === action.target; });
      if (idx !== -1) valj_efb[idx].required = req;
      var reqSpan = document.getElementById(action.target + '_req');
      if (reqSpan) reqSpan.style.display = req ? '' : 'none';
    }
    if (t === 'enable_field' || t === 'disable_field') {
      var en = (t === 'enable_field');
      var el = document.getElementById(action.target + '_');
      if (el) el.disabled = !en;
      var sel = document.querySelector('select[data-vid="' + action.target + '"]');
      if (sel) sel.disabled = !en;
      var ms = document.querySelector('.efblist[data-vid="' + action.target + '"]');
      if (ms) { ms.style.pointerEvents = en ? '' : 'none'; ms.style.opacity = en ? '' : '0.5'; }
    }
    if (t === 'show_step' || t === 'hide_step') {
      var showStep = (t === 'show_step');
      var stepObj = valj_efb.find(function(x) { return x.id_ === action.target && x.type === 'step'; });
      if (stepObj) {
        var fs = document.querySelector('fieldset[data-step="step-' + stepObj.step + '-efb"]');
        if (fs) fs.dataset.logicHidden = showStep ? '0' : '1';
      }
    }
  }

  /* ── Run all enabled rules in priority order ── */
  var sorted = rules.slice().sort(function(a, b) { return (a.priority || 10) - (b.priority || 10); });
  sorted.forEach(function(rule) {
    if (!rule.enabled) return;
    var matched = _evalGroup(rule.conditions);
    (rule.actions || []).forEach(function(action) { _execAction(action, matched); });
  });
}

let stepNames_emsFormBuilder = [`t`, ``, ``];
let currentTab_emsFormBuilder = 0;
let multiSelectElemnets_emsFormBuilder = [];
let formNameEfb = ""
let files_emsFormBuilder = [];
let addons_emsFormBuilder =""
let recaptcha_emsFormBuilder = [];

if (typeof efb_var !== 'undefined') {
}
let poster_emsFormBuilder = '';
const fileSizeLimite_emsFormBuilder = 8300000;
let select_options_emsFormBuilder = [];
let form_type_emsFormBuilder = 'form';
let valueJson_ws = []
let motus_efb = {};
let g_timeout_efb = 100
let price_efb ="";
/* The id_ of the option a <select> is sitting on.
   Only data-op carries it in every renderer: the server prints data-id and
   data-op and no id at all, the builder prints all three, so reading .id
   alone came back empty on every live form - which is why a paySelect never
   matched its option row and its price never reached the total. */
selected_option_id_efb = (el) => {
  const op = el && el.options ? el.options[el.selectedIndex] : null;
  if (!op) return "";
  return op.id || (op.dataset ? (op.dataset.op || op.dataset.id || "") : "");
};
let sendback_efb_state= [];
let valj_efb_new = [];
let form_ID_emsFormBuilder = 0;
if (window.React || window.react) {
  reactJs_active_efb = true;
}
if (typeof(ajax_object_efm)=='object' && ajax_object_efm.hasOwnProperty('ajax_value') && typeof ajax_object_efm.ajax_value == "string") {
  g_timeout_efb = (g_timeout_efb, ajax_object_efm.ajax_value.match(/id_/g) || []).length;
  g_timeout_efb = g_timeout_efb * calPLenEfb(g_timeout_efb);
  ajax_object_efm.ajax_value_forms.forEach(form => {
    form.form_structer = efbParseJson(form.form_structer);
    // Saved amounts/steps are strings; every position compare is numeric (A2, efbOrderOf in new-efb.js).
    if (typeof efbNormalizeOrderEfb === 'function') efbNormalizeOrderEfb(form.form_structer);
  });
  valj_efb_new = ajax_object_efm.ajax_value_forms
}
g_timeout_efb = typeof ajax_object_efm == "object" && typeof ajax_object_efm.ajax_value == "string" ? g_timeout_efb : 1100;
function fun_render_view_efb(val, check) {
  var url = new URL(window.location);
  history.replaceState("EFBstep-1",null,url);
  exportView_emsFormBuilder = [];
  valueJson_ws = efbParseJson(val);
  valj_efb = valueJson_ws;
  state_efb = "run";
}

function deepFreeze_efb_pub(obj) {
  if (typeof obj !== "object" || obj === null) return obj;
  Object.keys(obj).forEach((key) => {
      if (typeof obj[key] === "object" && obj[key] !== null) {
          deepFreeze_efb_pub(obj[key]);
      }
  });
  return Object.freeze(obj);
}

function check_body_efb_timer (){
  g_timeout_efb -=10;
  if((document.getElementById('body_efb')==null && document.getElementById('body_tracker_emsFormBuilder')==null) && g_timeout_efb>10){
    setTimeout(() => {
      check_body_efb_timer();
    }, 800);
  }else{

    fun_efb_run();
  }
}
  function fun_efb_run() {
    if (window.efb_initialized) return;

    if (typeof window.jQuery != "function" || typeof jQuery != "function") {
      /* An optimiser may defer or delay jQuery while this file runs at once
         (or a theme loads it late). Wait for it; warn only if it never comes. */
      fun_efb_run.tries = (fun_efb_run.tries || 0) + 1;
      if (fun_efb_run.tries <= 50) {
        clearTimeout(fun_efb_run.timer);
        fun_efb_run.timer = setTimeout(fun_efb_run, 200);
        return;
      }
      window.efb_initialized = true;
      const txt = typeof ajax_object_efm === 'object' && ajax_object_efm && ajax_object_efm.text ? ajax_object_efm.text : {};
      let msg = `<div class="efb alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">
        <strong>${txt.alert || ''}</strong> ${txt.jqinl || 'jQuery is not loaded.'}
      </div>`;
      document.querySelectorAll('.body_efb').forEach(el => { el.innerHTML = msg; });
      if(document.getElementById('body_tracker_emsFormBuilder')) document.getElementById('body_tracker_emsFormBuilder').innerHTML = msg;
      return;
    }

    window.efb_initialized = true;

    g_timeout_efb = 100;

    const docBodyEfb = document.querySelector('.body_efb');

    jQuery(() => {
      if (typeof ajax_object_efm === 'undefined' || !ajax_object_efm.ajax_value) return;

      if (!docBodyEfb && !document.getElementById('body_tracker_emsFormBuilder')) {
        check_body_efb_timer();
      }

      poster_emsFormBuilder = deepFreeze_efb_pub(ajax_object_efm.poster);
      ajax_object_efm.text = deepFreeze_efb_pub(ajax_object_efm.text);
      lan_name_emsFormBuilder = ajax_object_efm.language.slice(0, 2);
      pro_efb = ajax_object_efm.pro == '1' ? true : false;
      page_state_efb = "public";

      setting_emsFormBuilder=typeof ajax_object_efm.form_settingJSON=='string' ? efbParseJson(ajax_object_efm.form_setting) : ajax_object_efm.form_settingJSON;
      efb_var = ajax_object_efm;
      efb_var.text = deepFreeze_efb_pub(efb_var.text);

      /* The "Cache Plugin Detected" and "Security Plugin Detected" entries that
         used to be logged here on every page view are gone: they alarmed site
         owners about plugins that were working fine (EFB already keeps its form
         pages out of page caches). A failed submit now explains its own cause
         in the admin panel (efb_report_submit_ajax_error_efb). */


      if (ajax_object_efm.state != 'tracker') {
        const ajax_value = typeof (ajax_object_efm.ajax_value) == "string" ? efbParseJson(ajax_object_efm.ajax_value) : ajax_object_efm.ajax_value;
        if (ajax_object_efm.form_setting && ajax_object_efm.form_setting.length > 0 && ajax_object_efm.form_setting !== ajax_object_efm.text.settingsNfound) {
          form_type_emsFormBuilder = ajax_object_efm.type;
          const vs = setting_emsFormBuilder;
          addons_emsFormBuilder = vs.addons;

          if (ajax_object_efm.type !== "userIsLogin") {
            if (Number(ajax_value[0]?.captcha) === 1) {
              if (vs.siteKey.length < 3) {
                document.getElementById('body_efb').innerHTML = alarm_emsFormBuilder(ajax_object_efm.text.formIsNotShown);
                return;
              }
              if(sitekye_emsFormBuilder==2)alert(c_r_efb);
              sitekye_emsFormBuilder = vs.siteKey;
            } else {
              sitekye_emsFormBuilder = "";
            }
          }
        }
      }

      if (!ajax_object_efm.hasOwnProperty('ajax_value_forms')) {
        if (ajax_object_efm.state !== 'settingError') {
          switch (ajax_object_efm.state) {
            case 'form':
              fun_render_view_efb(ajax_object_efm.ajax_value, 1);
              break;
            case 'tracker':
              fun_tracking_show_emsFormBuilder();
              break;
            case 'userIsLogin':
              document.getElementById('body_efb').innerHTML = show_user_profile_emsFormBuilder(ajax_object_efm.ajax_value);
              break;
            default:
              fun_show_alert_setting_emsFormBuilder();
          }
        } else {
          fun_show_alert_setting_emsFormBuilder();
        }
      }
    });

    (function () {
      var exportObj = {
        init: function (element, data, selectCb, options) {
          createMultiselect(element, data, selectCb, options);
        }
      };
      motus_efb.ElementMultiselect = exportObj;
    })();
  }

setTimeout( fun_efb_run, g_timeout_efb)

/* Back/Forward: the browser refills typed answers without firing change, so
 * the step check saw them as empty ("This field is required") and retyping
 * the same text changed nothing. Record what it restored, only on a page
 * reached through history so default values keep their usual handling. */
window.addEventListener('pageshow', function (e) {
  const nav = window.performance && performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
  if (!e.persisted && !(nav && nav.type === 'back_forward')) return;
  setTimeout(function () {
    if (typeof sendBack_emsFormBuilder_pub === 'undefined' || typeof valj_efb === 'undefined') return;
    document.querySelectorAll('.emsFormBuilder_v').forEach(function (el) {
      if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || !el.dataset.vid || !el.value) return;
      if (['hidden', 'file', 'submit', 'button', 'checkbox', 'radio', 'password', 'range', 'color'].includes(el.type)) return;
      const form_id = el.dataset.formid || 0;
      if (get_row_sendback_by_id_efb_v4(el.dataset.vid, form_id) !== -1) return;
      handle_change_event_efb_v4(el, form_id);
    });
  }, 600);
});

async function createStepsOfPublic() {
 // Not localized on a page that shows only the members-only lock card: no form to set up.
 if (typeof ajax_object_efm === 'undefined') return;
 // F06: advanced date fields that arrived after the picker's first pass (a gate swap, a popup).
 if (window.EFBCalendar && typeof EFBCalendar.mount === 'function') { EFBCalendar.mount(); setTimeout(efb_advdate_sync_efb, 0); }
 state_efb = "run";
 efb_var = ajax_object_efm;
 setting_emsFormBuilder = typeof ajax_object_efm.form_setting == "string" ? efbParseJson(ajax_object_efm.form_setting) : ajax_object_efm.form_setting;
  for (let el of document.querySelectorAll(`.emsFormBuilder_v`)) {
    let form_id = el.dataset.formid  || 0;
    if (el.tagName == "OPTION" || el.tagName=='P' || el.tagName=='A' || ( el.tagName=='INPUT' && el.type=='hidden')) continue;
    let price = '';
    let el_type =''
    const valj_efb_ =get_structure_by_form_id_efb(form_id);
    valj_efb = valj_efb_;
    form_ID_emsFormBuilder = parseInt(form_id);
    const id = el.dataset.vid ?? '';
    const classes = el.classList;
    let handler_state=true;
    const form_type = valj_efb_[0].hasOwnProperty('type') ? valj_efb_[0].type : 'form';
    let o =[{ id_: id, name: el.name, id_ob: el.id, amount: 0, type: el.type, value: el.value, session: sessionPub_emsFormBuilder, form_id: form_id }];
    if ('type' in el) {
      if( ( el.type=="checkbox" || el.type=="radio" ) && valj_efb_[0].type == "payment" && classes.contains('payefb')){
        let indx = valj_efb.findIndex(x => x.id_ === id);
        const row = valj_efb[indx];
        const parent_id= row.hasOwnProperty('parent_id') ? row.parent_id : -1;
        if(parent_id!=-1){
          let indx_parent = valj_efb.findIndex(x => x.id_ === parent_id);
          const row_parent = valj_efb[indx_parent];
          if (row.value.length > 0 || el.checked == true) {
            price = row_parent.hasOwnProperty('price') ? row_parent.price : 0;

          }
        }
        fun_total_pay_efb(form_id)
      }else if (el.type != "submit") {
        switch (el.type) {
          case "text":
          case 'email':
          case 'number':
          case 'tel':
          case 'password':
          case 'textarea':
          case 'image':
          case 'url':
          case 'range':
          case 'color':
            if ((classes.contains("pdpF2") || classes.contains("hijri-picker")) && el.value
              && efb_typed_date_efb(el.value, classes.contains("pdpF2") ? 'jalali' : 'hijri') === null) {
              // A default the picker cannot read (free text): start empty rather
              // than store text the visitor never sees, or flag it before any input.
              el.value = '';
            }
            // F01: bind the mask first, so a default value is formatted before it is checked.
            const mask_bound_efb = (el.type === 'text' || el.type === 'tel') ? efb_bind_mask_efb(el, form_id) : false;
            // F03: the live counter of a field with one (nothing on other fields).
            if (el.type === 'text' || el.type === 'textarea') efb_rules_bind_counter_efb(el, form_id);
            // F04: a number box with the F04 keys formats while typing (local digits, separators, caret kept).
            if (el.type === 'text' && el.dataset && el.dataset.num === '1' && window.EFBCalc) window.EFBCalc.bindNumber(el, window.EFBCalc.cfgOf(el) || {});
            let init_change_efb = null;
            if(el.value && el.value.length>0){
              if(el.type!="range" && el.type!="color"){
                init_change_efb = handle_change_event_efb_v4(el,form_id);
              }else{
                let indx = valj_efb.findIndex(x => x.id_ === id);
               if( indx==-1 ){
                  break;
                }else if( indx!=-1 && valj_efb[indx].hasOwnProperty('value') && valj_efb[indx].value==""){
                  break;
                }
                handle_change_event_efb_v4(el,form_id);
              }
            }
            if (classes.contains("pdpF2")) {
              call_fun_jalali_datepicker_efb_v4();
            }else if (classes.contains("hijri-picker")) {
              call_fun_hijri_datapicker_efb_v4();
              // Also when the date is cleared (arg.date false): the store drops
              // the old row, which otherwise was still submitted.
              jQuery("#"+el.id).on('dp.change', function () {
                handle_change_event_efb_v4(el,form_id);
              });
              // The picker may rewrite or clear the default it was given before
              // dp.change was bound: keep the stored row equal to the field.
              // After the default's own change handler has stored it: that one
              // is async, so a check made right away found no row yet.
              const hijri_id_efb = id, hijri_el_efb = el, hijri_form_efb = form_id;
              Promise.resolve(init_change_efb).catch(() => {}).then(() => {
                const hijri_row = get_row_sendback_by_id_efb_v4(hijri_id_efb, hijri_form_efb);
                if (hijri_row != -1 && sendBack_emsFormBuilder_pub[hijri_row].value !== hijri_el_efb.value) {
                  if (hijri_el_efb.value === '') {
                    sendBack_emsFormBuilder_pub.splice(hijri_row, 1);
                    try { localStorage.setItem('sendback', efb_sendback_for_storage_efb(sendBack_emsFormBuilder_pub)); } catch (e) {}
                    updateStepButtonState_efb(hijri_form_efb);
                  } else {
                    handle_change_event_efb_v4(hijri_el_efb, hijri_form_efb);
                  }
                }
              });
            }else if (classes.contains("intlPhone") && classes.contains("d-none")==false) {
              const indx = valj_efb_.findIndex(x => x.id_ === id);
              valj_efb=valj_efb_;
              load_intlTelInput_efb(id,indx);

            }
            // Not on a masked field: two rewrites on 'input' fight over the caret, and the keydown filter
            // breaks IMEs and Android keyboards (keyCode 229).
            if(el.type === 'tel' && !mask_bound_efb) {
              el.setAttribute('inputmode', 'tel');
              el.addEventListener('input', function() {
                var cursorPos = this.selectionStart;
                var oldLen = this.value.length;
                var cleaned = efb_ascii_digits_efb(this.value).replace(/[^0-9+\-\s().]/g, '');
                var firstPlus = cleaned.indexOf('+');
                if (firstPlus > 0) { cleaned = cleaned.replace(/\+/g, ''); }
                else if (firstPlus === 0) { cleaned = '+' + cleaned.slice(1).replace(/\+/g, ''); }
                if (cleaned !== this.value) {
                  this.value = cleaned;
                  var newLen = this.value.length;
                  this.setSelectionRange(cursorPos - (oldLen - newLen), cursorPos - (oldLen - newLen));
                }
              });
              el.addEventListener('paste', function(e) {
                e.preventDefault();
                var paste = (e.clipboardData || window.clipboardData).getData('text') || '';
                var cleaned = efb_ascii_digits_efb(paste).replace(/[^0-9+\-\s().]/g, '');
                var firstPlus = cleaned.indexOf('+');
                if (firstPlus > 0) { cleaned = cleaned.replace(/\+/g, ''); }
                else if (firstPlus === 0) { cleaned = '+' + cleaned.slice(1).replace(/\+/g, ''); }
                var start = this.selectionStart;
                var end = this.selectionEnd;
                var before = this.value.substring(0, start);
                var after = this.value.substring(end);
                this.value = before + cleaned + after;
                var newPos = start + cleaned.length;
                this.setSelectionRange(newPos, newPos);
                handle_change_event_efb_v4(this, this.dataset.formid || 0);
              });
              el.addEventListener('keydown', function(e) {
                if (e.ctrlKey || e.metaKey || e.altKey) return;
                if (e.key && e.key.length === 1) {
                  if (!/[0-9+\-\s().\u06F0-\u06F9\u0660-\u0669]/.test(e.key)) { e.preventDefault(); }
                  if (e.key === '+' && (this.selectionStart !== 0 || this.value.indexOf('+') !== -1)) { e.preventDefault(); }
                }
              });
            }
          break;
          case "file":
            const ob = valj_efb_.find(x => x.id_ === id);
            if(typeof(ob)=="undefined" || ob==null) break;
            /* F07: a field of several files (data-max-files > 1) is run by upload-efb.js: its own
               queue and list, one answer row per file, no placeholder row. */
            if (typeof window.EFBUpload !== 'undefined' && window.EFBUpload.bind(id, form_id)) { handler_state = false; break; }
            if(((ob.hasOwnProperty("disabled") && ob.disabled!=true ) || ob.hasOwnProperty("disabled")==false )&&
            ((ob.hasOwnProperty('hidden') && ob.hidden==true) || ob.hasOwnProperty('hidden')==false))

            files_emsFormBuilder.push({ id_: ob.id_, value: "@file@", state: 0, url: "", type: "file",state:0, name: ob.name, session: sessionPub_emsFormBuilder, form_id: form_id });

            if (el.classList.contains('dadfile')){
              const indx = valj_efb_.findIndex(x => x.id_ === id);
              set_dadfile_fun_efb(id,indx,form_id)
              handler_state=false;
            }
          break;
          case "hidden":
            el_type = el.dataset.type ?? '';
            if(el_type=='esign'){
              const ob = valj_efb_.find(x => x.id_ === id);
              if(typeof(ob)=="undefined" || ob==null) break;
              const disabled = ob.hasOwnProperty("disabled") && ob.disabled==true ? true : false;
              fun_event_esign_efb(id,form_id,disabled,ob);
            }

            break;
          case 'checkbox':
          case 'radio':
            document.getElementById(el.id).addEventListener("change", (e) => {
                if(el.dataset.tag && el.dataset.tag.includes("chl")!=false){
                }else if(classes.contains('payefb') && valj_efb_[0].type == "form"){
                }
              })
              if(el.hasOwnProperty('checked') || el.checked==true){
                handle_change_event_efb_v4(el,form_id);
              }
          break;
          case 'select-one':
            const selected_option_tag = el.querySelector('option:checked');
            if (selected_option_tag && selected_option_tag.id !== 'efbNotingSelected') {
              handle_change_event_efb_v4(el,form_id);
            }

          break;

          }
      }else if (el.type == "submit") {
        el.addEventListener("click", (e) => {
          const id_ = el.dataset.vid
          const form_id = el.dataset.formid || -1;
          const ob = valueJson_ws.find(x => x.id_ === id_);
          let o = [{ id_: id_, name: ob.name, id_ob: el.id, amount: ob.amount, type: el.type, value: el.value, session: sessionPub_emsFormBuilder, form_id: form_id }];
          if (valj_efb_[0].type == "payment" && classes.contains('payefb')) {
            let q = valueJson_ws.find(x => x.id_ === el.id);
            const p = price_efb.length > 0 ? { price: price } : { price: q.price }
            Object.assign(o[0], p)
            fun_sendBack_emsFormBuilder(o[0]);
            fun_total_pay_efb(form_id)
          } else {
            fun_sendBack_emsFormBuilder(o[0]);
          }
        });
      }
    }else if (el.classList.contains('maps-efb')){

        const c = valj_efb_.find(x => x.id_ === id);
        const parents = el.parentNode
        if(!c.hasOwnProperty('formid'))Object.assign(c , {'formid':parents.dataset.formid})
    }else if(form_type=="payment"){
      const getway = valj_efb_[0].hasOwnProperty('getway') ? valj_efb_[0].getway : '';
      if (getway == "stripe" && typeof post_api_stripe_apay_efb =="function") post_api_stripe_apay_efb(form_id);
    }
    if(handler_state){
      el.addEventListener("change", async(e) => {
        await handle_change_event_efb_v4(el,form_id);
      });

    }

  }

  for (let switchEl of document.querySelectorAll('.btn-toggle[onclick*="fun_switch_efb"]')) {
    const sw_form_id = switchEl.dataset.formid || 0;
    const sw_vid = switchEl.dataset.vid;
    if (!sw_vid) continue;
    try {
      const sw_valj = get_structure_by_form_id_efb(sw_form_id);
      const sw_v = sw_valj.find(x => x.id_ == sw_vid);
      if (!sw_v || sw_v.value == undefined || sw_v.value == null || sw_v.value == "") continue;
      const sw_value = switchEl.classList.contains('active') ? "1" : "0";
      const sw_ob = { id_: sw_v.id_, name: sw_v.name, amount: sw_v.amount, type: sw_v.type, value: sw_value, session: sessionPub_emsFormBuilder, form_id: sw_form_id };
      fun_sendBack_emsFormBuilder(sw_ob);
    } catch(e) {  }
  }

  for (let msEl of document.querySelectorAll('.efb.efblist.inplist[data-vid][data-select]')) {
    try {
      const ms_vid = msEl.dataset.vid;
      const ms_select = msEl.dataset.select;
      if (!ms_vid || !ms_select || ms_select.trim().length === 0) continue;
      if (get_row_sendback_by_id_efb_v4(ms_vid, 0) !== -1) continue;
      const ms_form_id_el = msEl.closest('[data-formid]');
      const ms_fid = ms_form_id_el ? ms_form_id_el.dataset.formid : (typeof form_ID_emsFormBuilder !== 'undefined' ? form_ID_emsFormBuilder : 0);
      const ms_valj = (typeof get_structure_by_form_id_efb === 'function' && ms_fid) ? get_structure_by_form_id_efb(ms_fid) : valj_efb;
      const ms_ob_def = ms_valj.find(x => x.id_ === ms_vid);
      if (!ms_ob_def) continue;
      const ms_v = msEl.innerHTML.trim().replaceAll(',', '@efb!');
      if (!ms_v || ms_v.trim() === efb_var.text.selectOption.trim()) continue;
      const ms_push = { id_: ms_vid, name: ms_ob_def.name, amount: ms_ob_def.amount, type: ms_ob_def.type, value: ms_v, session: sessionPub_emsFormBuilder, form_id: ms_fid };
      fun_sendBack_emsFormBuilder(ms_push);
    } catch(e) {  }
  }
  // F05: shuffled order (kept for the session), Other, Select all, the maximum lock, capacities, ranking (choices-efb.js).
  if (window.EfbChoices) { try { window.EfbChoices.initAll(); efb_choices_stock_efb(); } catch (e) { /* the form works without it; the server checks again */ } }

}
/* F05: a form with capacities asks forms/session/prepare for the places left now (a cached page has older numbers). */
function efb_choices_stock_efb() {
  if (!window.EfbChoices || !window.EFBRuntime || typeof valj_efb_new === 'undefined' || !Array.isArray(valj_efb_new)) return;
  valj_efb_new.forEach((f) => {
    const rows = f && f.form_structer;
    if (!Array.isArray(rows) || !rows[0]) return;
    const booking = rows[0].booking == 1 || rows[0].booking === true;
    if (!booking && !rows.some((r) => r && (r.inv_on === '1' || r.inv_on === 1))) return;
    window.EFBRuntime.prepare(Number(f.id)).then((v) => { if (v && v.stock) window.EfbChoices.applyStock(String(f.id), v.stock); }).catch(() => {});
  });
}
async function fun_sendBack_emsFormBuilder(ob) {
  if(typeof ob=='string' || ob.hasOwnProperty('value')==false ){
    return;
  }
  normalize_sendback_row_form_id_efb(ob, form_ID_emsFormBuilder || -1);
  const form_id = ob.form_id || -1;
  remove_ttmsg_efb(ob.id_)
  if(ob.hasOwnProperty('value') && typeof(ob.value)!='number' && typeof(ob.value)!='object' && typeof(ob.value)!='string') {ob.value=fun_text_forbiden_convert_efb(ob.value);
  }else if(ob.hasOwnProperty('value') && ( typeof(ob.value)=='object') &&  ob.type=="maps" ){
    ob.value=ob.value;
  }
  if (sendBack_emsFormBuilder_pub.length>0) {
    let indx = get_row_sendback_by_id_efb_v4(ob.id_,form_id);
    if (indx != -1 && ob.type != "switch" && (sendBack_emsFormBuilder_pub[indx].type == "checkbox" || sendBack_emsFormBuilder_pub[indx].type == "payCheckbox" || sendBack_emsFormBuilder_pub[indx].type == "multiselect" || sendBack_emsFormBuilder_pub[indx].type == "payMultiselect" || sendBack_emsFormBuilder_pub[indx].type == "chlCheckBox")) {
      indx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ === ob.id_ && x.value == ob.value && Number((x && x.hasOwnProperty('form_id')) ? x.form_id : form_id) === Number(form_id));
      indx == -1 ? sendBack_emsFormBuilder_pub.push(ob) : sendBack_emsFormBuilder_pub.splice(indx, 1);
    }
    else if(indx != -1 && ob.value == "@file@" ){
      /* F07: a field of several files keeps one row per stored file (id_ob = the stored name);
         a field of one file replaces its row, as before. */
      if (typeof window.EFBUpload !== 'undefined' && ob.id_ob && window.EFBUpload.isMulti(ob.id_, form_id)) {
        const same = sendBack_emsFormBuilder_pub.findIndex(x => x && x.id_ === ob.id_ && x.id_ob === ob.id_ob && Number(x.form_id || form_id) === Number(form_id));
        same == -1 ? sendBack_emsFormBuilder_pub.push(ob) : sendBack_emsFormBuilder_pub[same] = ob;
      } else {
        sendBack_emsFormBuilder_pub[indx]=ob;
      }
    }else if(ob.type == "r_matrix"){
      indx = sendBack_emsFormBuilder_pub.findIndex( x => x!=null && x.hasOwnProperty('id_ob') && x.id_ob === ob.id_ob);
      indx == -1 ? sendBack_emsFormBuilder_pub.push(ob) : sendBack_emsFormBuilder_pub[indx]=ob;
    }else if(indx != -1 && ob.type == "mobile" ){
      /* A changed number replaces the stored one. The value used to be computed and dropped, so the
         first valid number typed was the one sent even after the visitor corrected it (and an F02
         code verified for the corrected number could never match). */
      ob.value= ob.value.replace(/^(\+\d+)\1/, '$1');
      sendBack_emsFormBuilder_pub[indx].value = ob.value;
    } else {
      if (indx == -1) {
        sendBack_emsFormBuilder_pub.push(ob)
      } else {
        if (typeof ob.price != "string") {
          sendBack_emsFormBuilder_pub[indx].value = ob.value;
          /* Always sync id_ob so radio/yesNo/imgRadio changes are reflected correctly */
          if (ob.id_ob) sendBack_emsFormBuilder_pub[indx].id_ob = ob.id_ob;
        } else {
          sendBack_emsFormBuilder_pub[indx].value = ob.value;
          sendBack_emsFormBuilder_pub[indx].price = ob.price;
          sendBack_emsFormBuilder_pub[indx].id_ob = ob.id_ob;
        }
      }
    }
  } else {
    sendBack_emsFormBuilder_pub.push(ob);
  }
  if (Number(form_id) > 0 && typeof updateStepButtonState_efb === 'function') {
    // Some controls (like switch/multiselect callbacks) store values without passing through handle_change_event_efb_v4.
    // Re-evaluate submit button state after every successful store to remove order-dependent behavior.
    setTimeout(function() {
      updateStepButtonState_efb(Number(form_id));
    }, 0);
  }
  /* Queueing the value is the required operation; persistence is only a
     convenience and may be unavailable in private/restricted browsers. */
  try {
    localStorage.setItem('sendback', efb_sendback_for_storage_efb(sendBack_emsFormBuilder_pub));
    localStorage.setItem('formId', efb_var.id)
  } catch (e) {}
  // F08: pieces of labels / options and hidden values that read this answer ({field:id}).
  if (window.EFBSmart && ob.type !== 'hiddenField') { try { window.EFBSmart.refresh(Number(form_id), ob.id_); } catch (e) {} }
  // F04: the form's calc fields again (debounced; a calc result itself does not start another round).
  if (window.EFBCalc && window.EFBCalc.page && ob.type !== 'calc') { try { window.EFBCalc.page.schedule(Number(form_id)); } catch (e) {} }
}

function yesNoGetEFB(v, id, idl, form_id=0) {
  if (typeof sendBack_emsFormBuilder_pub === 'undefined') return;

  var clickedBtn = null;
  if (Number(form_id) > 0 && typeof efb_get_by_id_in_form_efb === 'function') {
    clickedBtn = efb_get_by_id_in_form_efb(form_id, idl);
  }
  if (!clickedBtn && typeof event !== 'undefined' && event && event.currentTarget && event.currentTarget.id === idl) {
    clickedBtn = event.currentTarget;
  }
  if (!clickedBtn) clickedBtn = document.getElementById(idl);

  if (!(Number(form_id) > 0) && clickedBtn && typeof efb_form_id_from_dom_node_efb === 'function') {
    form_id = efb_form_id_from_dom_node_efb(clickedBtn);
  }
  if (!(Number(form_id) > 0) && typeof efb_unique_dom_form_id_for_id_efb === 'function') {
    form_id = efb_unique_dom_form_id_for_id_efb(idl);
  }
  form_id = Number(form_id) > 0 ? Number(form_id) : 0;

  const getLocalEl = (targetId)=>{
    if (Number(form_id) > 0 && typeof efb_get_by_id_in_form_efb === 'function') {
      return efb_get_by_id_in_form_efb(form_id, targetId);
    }
    return document.getElementById(targetId);
  }

  const otherId = idl.slice(-4) === '_b_2' ? `${id}_b_1` : `${id}_b_2`;
  const otherBtn = getLocalEl(otherId);
  if (otherBtn) otherBtn.classList.remove('btn-set');
  if (clickedBtn && !clickedBtn.classList.contains('btn-set')) clickedBtn.classList.add('btn-set');

  const inputId = idl.slice(-4) === '_b_2' ? `${id}_2` : `${id}_1`;
  const inputEl = getLocalEl(inputId);
  if (inputEl) inputEl.checked = true;

  const structure = Number(form_id) > 0 ? get_structure_by_form_id_efb(form_id) : valj_efb;
  const indx = Array.isArray(structure) ? structure.findIndex(x => x && x.id_ == id) : -1;
  if (indx === -1) return;

  const row = structure[indx];
  const o = {
    id_: id,
    name: row.name,
    id_ob: inputId,
    amount: row.amount,
    type: "yesNo",
    value: v,
    session: sessionPub_emsFormBuilder
  };
  if (Number(form_id) > 0) o.form_id = form_id;
  fun_sendBack_emsFormBuilder(o);

  /* Re-evaluate conditional logic AFTER sendBack has been updated — yesNo clicks
   * don't go through handle_change_event_efb_v4, so this call was missing here,
   * causing rules depending on this field to only fire on a later, unrelated change. */
  const yn_form_id = Number(form_id) > 0 ? Number(form_id) : (typeof form_ID_emsFormBuilder !== 'undefined' ? form_ID_emsFormBuilder : 0);
  const yn_logicStructure = get_structure_by_form_id_efb(yn_form_id);
  if (yn_logicStructure && yn_logicStructure[0] &&
      ((yn_logicStructure[0].hasOwnProperty('logic') && yn_logicStructure[0].logic) ||
       (yn_logicStructure[0].hasOwnProperty('logic_rules') && Array.isArray(yn_logicStructure[0].logic_rules) && yn_logicStructure[0].logic_rules.length > 0))) {
    const logicRuntime = efb_get_logic_runtime_efb();
    if (logicRuntime) {
      logicRuntime.evaluate(yn_form_id);
    } else if (typeof fun_statement_logic_efb === 'function') {
      fun_statement_logic_efb(id, 'yesNo', yn_form_id);
    }
  }
}

function alarm_emsFormBuilder(val) {
  return `<div class="efb alert alert-warning alert-dismissible fade show " role="alert" id="alarm_emsFormBuilder">
      <div><i class="efb nmsgefb bi-exclamation-triangle-fill text-center"></i></div>
      <strong>${ajax_object_efm.text.alert} </strong>${val}
    </div>`
}

/* The final/confirmation fieldset and navigation controls are siblings of the
 * form body in the public renderer, not always descendants of #body_efb_ID.
 * Resolve them through the owning form first so one-step forms cannot dereference
 * null while their submit flow switches to the final screen. */
function efb_get_form_scope_efb(form_id, scope) {
  const safeId = String(form_id == null ? '' : form_id).replace(/[^0-9_-]/g, '');
  const nearestForm = scope && scope.closest ? scope.closest('form') : null;
  if (nearestForm) return nearestForm;
  if (safeId) {
    const form = document.querySelector('form[data-formid="' + safeId + '"]');
    if (form) return form;
  }
  return document.getElementById('efbform');
}

function efb_find_in_form_efb(form_id, selector, scope) {
  const local = scope && scope.querySelector ? scope.querySelector(selector) : null;
  if (local) return local;
  const form = efb_get_form_scope_efb(form_id, scope);
  const formMatch = form && form.querySelector ? form.querySelector(selector) : null;
  if (formMatch) return formMatch;
  /* The legacy public renderer can place navigation controls next to (rather
   * than inside) #efbform. Prefer its own data-formid before the global ID. */
  const safeId = String(form_id == null ? '' : form_id).replace(/[^0-9_-]/g, '');
  if (safeId) {
    const byFormId = document.querySelector(selector + '[data-formid="' + safeId + '"]');
    if (byFormId) return byFormId;
  }
  return document.querySelector(selector);
}

function efb_get_final_step_efb(form_id, scope) {
  const safeId = String(form_id == null ? '' : form_id).replace(/[^0-9_-]/g, '');
  const selector = safeId ? '#efb-final-step[data-formid="' + safeId + '"]' : '#efb-final-step';
  return efb_find_in_form_efb(form_id, selector, scope) || document.getElementById('efb-final-step');
}

/* #efb-final-step is rendered by the server already holding the form's own
 * loading message (class-Emsfb-public.php -> loading_message_efb()), but every
 * outcome — thank-you, validation error, submit error — overwrites it in place.
 * Nothing ever put it back, so a visitor who hit an error, pressed Previous,
 * fixed the field and submitted again saw the OLD error re-appear for as long
 * as the new request was in flight. Keep the server's markup so it can be
 * restored verbatim whenever the step is left or re-entered. */
const efb_final_step_pristine_efb = {};

function efb_capture_final_step_efb(form_id, scope) {
  const finalStep = efb_get_final_step_efb(form_id, scope);
  const key = String(form_id);
  if (!finalStep || Object.prototype.hasOwnProperty.call(efb_final_step_pristine_efb, key)) return false;
  efb_final_step_pristine_efb[key] = finalStep.innerHTML;
  return true;
}

/* Restore the loading placeholder. Called before the step is shown and again
 * when it is left, so no stale outcome is ever on screen. The first call for a
 * form happens before anything has written to the step, so an uncaptured form
 * is still pristine — record it and leave the DOM untouched. */
function efb_reset_final_step_efb(form_id, scope) {
  const finalStep = efb_get_final_step_efb(form_id, scope);
  if (!finalStep) return false;
  if (efb_capture_final_step_efb(form_id, scope)) return true;
  finalStep.innerHTML = efb_final_step_pristine_efb[String(form_id)];
  return true;
}

/* Every error state ends with the same Back button. It used to be copy-pasted
 * inline, which let it drift away from the real navigation button built in
 * add_buttons_zone_efb(): the icon carried mx-2 (a margin on both sides, so the
 * label sat 4px off centre and never mirrored in RTL) plus fs-6, which pinned it
 * to 18px while the label followed el_height at 17px. Build it once here with
 * the same icon markup the navigation button uses. */
function efb_error_prev_button_efb(valj_efb, onclick) {
  const v = valj_efb[0];
  const color = v.hasOwnProperty('button_color') ? v.button_color : 'btn-darkb';
  const corner = v.hasOwnProperty('corner') ? v.corner : 'rounded-3';
  const height = v.hasOwnProperty('el_height') ? v.el_height : 'h-l-efb';
  const icon = v.button_Previous_icon;
  /* This only ever renders after something already failed, so never let a
   * missing global throw and leave the visitor with no way back. */
  const gap = (typeof efb_var !== 'undefined' && efb_var.rtl == 1) ? 'ms-2' : 'me-2';
  const iconTag = icon && icon.length > 3 && icon != 'bi-undefined' && icon != 'bXXX'
    ? `<i class="efb ${icon} ${gap} ${v.icon_color} ${height}" id="button_group_Previous_icon"></i>` : '';

  return `<button id="prev_efb_send" type="button" class="efb btn ${color} ${corner} ${height} p-2 text-center btn-lg" onclick="${onclick}">${iconTag}<span id="button_group_Previous_button_text" class="efb ${v.el_text_color}">${v.button_Previous_text}</span></button>`;
}

 async function endMessage_emsFormBuilder_view(current_step,form_id) {
  let valj_efb = get_structure_by_form_id_efb(form_id);

  fun_check_upload_files_complate_efb = (form_id) => {
    let checkFile_uploaded = 0;
    let countFile_notUloaded = 0;
    for (let file of files_emsFormBuilder) {
      if(file.form_id!=form_id)continue;

      if ( file.state == 2) {
        checkFile_uploaded += 1;
      }else if (file.state == 1) {
        countFile_notUloaded += 1;
      }

    }
    if(countFile_notUloaded>0){
      setTimeout(() => {
        fun_check_upload_files_complate_efb(form_id)
      }, countFile_notUloaded*1000);
    }else{
      actionSendData_emsFormBuilder(form_id);
    }

  }
  let counter = 0;
  /* current_step is the last real step the user was on when they hit
   * Submit — by the time these error screens render, dataset.currentstep
   * has already been advanced past it (to current_step + 1), so
   * fun_prev_send() would try to hide a fieldset that doesn't exist and
   * throw. Go back to current_step directly instead. */
  const btn_prev = `efb_go_to_step_direct(${form_id}, ${current_step})`;
  const stepMax = current_step + 1;
  let notfilled = []
  for (let i = 1; i <= stepMax; i++) {
    if (-1 == (sendBack_emsFormBuilder_pub.findIndex(x => x!=null && x.hasOwnProperty('step')  && x.step == i))) notfilled.push(i);
  }
  let countRequired = 0;
  let valueExistsRequired = 0;
  for (let el of exportView_emsFormBuilder) {
    if (el.required == true) {
      const id = el.id_;
      countRequired += 1;
      if (-1 == (get_row_sendback_by_id_efb_v4(id,form_id))) valueExistsRequired += 1;
    }
  }
  const id_body ='body_efb_'+form_id
  const body_efb = document.getElementById(id_body);
  const efb_final_step = efb_get_final_step_efb(form_id, body_efb);
  if (!body_efb || !efb_final_step) {
    console.error('[EFB] The final form step could not be found for form ' + form_id + '.');
    return false;
  }
  /* A completed recorder now stays local until the visitor selects Upload.
   * If they go straight to Submit, upload any ready recordings first and wait
   * for each one in sequence before the legacy file/submission checks run. */
  if (typeof efbRecUploadPendingForForm === 'function') {
    const recorderUpload = await efbRecUploadPendingForForm(form_id);
    if (!recorderUpload || !recorderUpload.success) return;
  }
  if (countRequired != valueExistsRequired && sendBack_emsFormBuilder_pub.length < 1) {
    let str = ""
    currentTab_emsFormBuilder = 0;
    efb_final_step.innerHTML = `<h1 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center"></i></h1><h3 class="efb text-center">${ajax_object_efm.text.error}</h3> <span class="efb mb-2  text-center">${ajax_object_efm.text.pleaseMakeSureAllFields}</span>
    <div class="efb m-1">${efb_error_prev_button_efb(valj_efb, btn_prev)}</div></div>`;
  } else {
    let checkFile = 0;
    for (let file of files_emsFormBuilder) {
      if (files_emsFormBuilder.length > 0 && file.state == 1) {
        checkFile += 1;
      } else if (files_emsFormBuilder.length > 0 && file.state == 3) {
        checkFile = -100;
        efb_final_step.innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center"></i></h1><h3 class="efb font-weight-bold  text-center">${ajax_object_efm.text.error} - ${ajax_object_efm.text.file}</h3> <span class="efb font-weight-bold  text-center">${ajax_object_efm.text.youNotPermissionUploadFile}</br>${file.url}</span>
         <div class="efb m-1">${efb_error_prev_button_efb(valj_efb, btn_prev)}</div></div>`;
        return;
      }
    }
    if (checkFile == 0) {
      if (files_emsFormBuilder.length > 0) {
        for (const file of files_emsFormBuilder) {
          if (get_row_sendback_by_id_efb_v4(file.id_,form_id) == -1) {
             sendBack_emsFormBuilder_pub.push(file);
             localStorage.setItem('sendback', efb_sendback_for_storage_efb(sendBack_emsFormBuilder_pub)); }
        }
      }
      const state = await validation_before_send_efb(form_id);
      if ( state == true){ actionSendData_emsFormBuilder(form_id)
      }
    } else {
        let checkFile = 0;
        for (let file of files_emsFormBuilder) {
          if(file.form_id!=form_id)continue;

          if (files_emsFormBuilder.length > 0 && file.state == 1) {
            checkFile += 1;
          } else if (files_emsFormBuilder.length > 0 && file.state == 3) {
            checkFile = -100;
            efb_final_step.innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center"></i></h1><h3 class="efb fs-4  text-center">${ajax_object_efm.text.error} - ${ajax_object_efm.text.file}</h3> <span class="efb fs-6  text-center">${ajax_object_efm.text.youNotPermissionUploadFile}</br>${file.url}</span>
               <div class="efb m-1">${efb_error_prev_button_efb(valj_efb, btn_prev)}</div></div>`;
            return;
          }
        }
        if (checkFile == 0) {
          for (const file of files_emsFormBuilder) {
            sendBack_emsFormBuilder_pub.push(file);
            localStorage.setItem('sendback', efb_sendback_for_storage_efb(sendBack_emsFormBuilder_pub));
          }
          const state = await validation_before_send_efb(form_id);
          if (state == true){
            actionSendData_emsFormBuilder(form_id);
          }

        }else{
           fun_check_upload_files_complate_efb(form_id);
        }

    }
  }
}
async function actionSendData_emsFormBuilder(form_id=0) {
  let sendback = [];
  if(form_type_emsFormBuilder =='recovery'){
    sendback = sendBack_emsFormBuilder_pub;
  }else{
    sendback = sendBack_emsFormBuilder_pub.filter(x=>Number(x.form_id)==Number(form_id));
  }

  const vj =  fun_sid_efb(form_id);
  let formNameEfb = vj.formName;
  let recaptcha_emsFormBuilder_row =''
  if (ajax_object_efm.type == "userIsLogin") return 0;
  if (form_type_emsFormBuilder != 'login'){
     localStorage.setItem('sendback', efb_sendback_for_storage_efb(sendback));
     if(form_type_emsFormBuilder =='recovery'){
      vj.type ='recovery';
     }
     /* Do NOT wipe sendBack_emsFormBuilder_pub here — the submission may still fail
        validation server-side. Clearing happens in response_fill_form_efb once the
        final result (success, or a specific field error) is known. */
  }
  if( vj.captcah){
    const indx = sendback.findIndex(x => x.id_ == 'captcha_v2');

    if (indx != -1) {
      recaptcha_emsFormBuilder_row = sendback[indx].value;
      sendback.splice(indx, 1);
    }
   }
  if (!navigator.onLine) {
    await response_fill_form_efb({ success: false, data: { success: false, m: ajax_object_efm.text.offlineMSend } },form_id);
    return;
  }
  form_type_emsFormBuilder = valj_efb.length >2 ? valj_efb[0].type : form_type_emsFormBuilder
  let  data = {
      action: "get_form_Emsfb",
      value: JSON.stringify(sendback),
      name: formNameEfb,
      id: form_id,
      valid: recaptcha_emsFormBuilder_row,
      type:  vj.type,
      url:location.href.split('?')[0],
      query: efb_page_query_efb(),
      sid:vj.sid ,
      page_id: ajax_object_efm.page_id
    };
    if(vj.type=="payment" ){
      if(valj_efb[0].getway=="persiaPay"){
        data = {
          action: "get_form_Emsfb",
          value: JSON.stringify(sendback),
          name: formNameEfb,
          payid: sessionStorage.getItem("payId"),
          /* sessionStorage "id" is only written when the Pay button is used, so a
             Submit before paying sent id:null; the server could not match the form
             session and answered "The form context expired". */
          id: Number(form_id) || sessionStorage.getItem("id"),
          valid: recaptcha_emsFormBuilder_row,
          type:  vj.type,
          payment: 'persiaPay',
          auth:get_authority_efb,
          url:location.href.split('?')[0],
          query: efb_page_query_efb(),
          sid:vj.sid ,
          page_id: ajax_object_efm.page_id
        };
      }else if(valj_efb[0].getway=="stripe"){
        data = {
          action: "get_form_Emsfb",
          value: JSON.stringify(sendback),
          name: formNameEfb,
          id: form_id,
          payid: efb_var.payId,
          valid: recaptcha_emsFormBuilder_row ,
          type: vj.type,
          payment: 'stripe',
          url:location.href.split('?')[0],
          query: efb_page_query_efb(),
          sid:vj.sid ,
          page_id: ajax_object_efm.page_id
        };
      } else if (valj_efb[0].getway=="paypal"){
        const paypalPayId = efb_var.payId || localStorage.getItem('PayId') || '';
        data = {
          action: "get_form_Emsfb",
          value: JSON.stringify(sendback),
          name: formNameEfb,
          id: form_id,
          payid: paypalPayId,
          valid: recaptcha_emsFormBuilder_row,
          type: vj.type,
          payment: 'paypal',
          url:location.href.split('?')[0],
          query: efb_page_query_efb(),
          sid:vj.sid ,
          page_id: ajax_object_efm.page_id
        };
      }
    }
    // F03-H: the second password boxes travel beside the answers, never as an answer (not stored, not in localStorage).
    efb_rules_add_confirms_efb(data, form_id);
    // F02: the tokens of verified numbers / addresses and the second e-mail copies travel beside the answers.
    if (window.EFBVerify && typeof window.EFBVerify.addToRequest === 'function') window.EFBVerify.addToRequest(data, form_id);
    /* PAY00: the field of a gateway add-on (payment framework) sends the form to
       payments/create: the server validates it, stores it as "pending payment" and starts the payment. */
    if (vj.type == "payment" && window.EFBPay && typeof window.EFBPay.isGateway === 'function') {
      let pay_structure = null;
      try { pay_structure = get_structure_by_form_id_efb(form_id); } catch (e) { pay_structure = valj_efb; }
      const pay_gateway = Array.isArray(pay_structure) && pay_structure[0] ? pay_structure[0].getway : '';
      if (window.EFBPay.isGateway(pay_gateway)) {
        window.EFBPay.submit(data, form_id);
        return;
      }
    }
    post_api_forms_efb(data,form_id);
}
/* Persian (۰-۹) and Arabic-Indic (٠-٩) digits to 0-9: phone keyboards in
 * Iran and the Arab world type these, and the phone filter dropped them. */
/* A date typed into the Jalali or Hijri field as YYYY-MM-DD with ASCII
 * digits, or null when it is not a date. Persian/Arabic digits, "/" or "."
 * separators and unpadded parts are accepted. Mirrors
 * Emsfb_Public::normalize_typed_date_efb() in PHP. */
/* The page's URL parameters, sent with a submission so Conditional Logic
 * "URL parameter" rules are checked on the server even when a strict
 * Referrer-Policy strips the query from the Referer. */
function efb_page_query_efb() {
  try { return location.search ? location.search.slice(1, 2001) : ''; } catch (e) { return ''; }
}
function efb_typed_date_efb(str, kind) {
  const v = efb_ascii_digits_efb(String(str == null ? '' : str))
    .replace(/[\s\u00A0\u061C\u200B-\u200F\u202A-\u202E\u2060-\u2069\uFEFF]/g, '')
    .replace(/[\/.]/g, '-');
  const m = v.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (!m) return null;
  const mo = Number(m[2]), d = Number(m[3]);
  if (mo < 1 || mo > 12 || d < 1) return null;
  let max = 31;
  if (kind === 'jalali') max = mo <= 6 ? 31 : 30;
  else if (kind === 'hijri') max = 30;
  if (d > max) return null;
  return m[1] + '-' + (mo < 10 ? '0' : '') + mo + '-' + (d < 10 ? '0' : '') + d;
}
/* F04: a number box with the F04 keys (data-num="1", settings in data-numcfg from the server): what the visitor
 * typed is read with local digits and separators and checked in the server's order (decimals, step, min, max;
 * EFBCalc.checkInput, twin of Calc::check_row). On success the box shows the formatted number; on failure the
 * field's message says why. Returns {empty} | {ok:false} | {ok:true, canon}. */
function efb_num_check_efb(el, ob) {
  const cfg = window.EFBCalc.cfgOf(el) || {};
  const form_id = el.dataset.formid || 0;
  if (!ob) {
    const st = typeof get_structure_by_form_id_efb === 'function' ? get_structure_by_form_id_efb(form_id) : (typeof valj_efb !== 'undefined' ? valj_efb : []);
    ob = Array.isArray(st) ? st.find((x) => x && x.id_ === el.dataset.vid) : null;
  }
  const r = window.EFBCalc.checkInput(el.value, cfg, (typeof efb_var !== 'undefined' && efb_var.text) || {}, ob && ob.name ? ob.name : '');
  const vd = document.getElementById(`${el.id}-message`);
  if (r.empty) return r;
  if (!r.ok) {
    el.className = colorBorderChangerEfb(el.className, "border-danger");
    if (vd) { vd.textContent = r.msg; show_msg_efb(vd); }
    return r;
  }
  const shown = window.EFBCalc.formatBare(r.canon, cfg);
  if (el.value !== shown) el.value = shown;
  return r;
}
function efb_ascii_digits_efb(str) {
  return String(str).replace(/[\u06F0-\u06F9]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); })
    .replace(/[\u0660-\u0669]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); });
}
/* An email address as the visitor meant it: Persian/Arabic digits to 0-9,
 * invisible direction marks and spaces removed, a fullwidth "@" or a Persian
 * decimal separator for the dot mapped back. These made correct Gmail
 * addresses fail as "not valid". Mirrors normalize_email_input_efb() in PHP. */
function efb_normalize_email_efb(str) {
  return efb_ascii_digits_efb(str)
    .replace(/[\u00A0\u061C\u200B-\u200F\u202A-\u202E\u2060-\u2069\uFEFF]/g, '')
    .replace(/\uFF20/g, '@')
    .replace(/[\uFF0E\u3002\u066B\u06D4]/g, '.')
    .trim();
}
function valid_email_emsFormBuilder(el) {
  if (el && typeof el.value === 'string') {
    const normalized = efb_normalize_email_efb(el.value);
    if (normalized !== el.value) el.value = normalized;
  }
  let offsetw = offset_view_efb();
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${ajax_object_efm.text.enterTheEmail}','',10,'danger');"></div>` : ajax_object_efm.text.enterTheEmail;
  let check = 0;
  const format =/^[a-zA-Z0-9._%+'-]+@[a-zA-Z0-9.-]+\.(?:[a-zA-Z]{2,}|xn--[a-zA-Z0-9-]+)$/;
  check += el.value.match(format) ? 0 : 1;
  const form_id = el.dataset.formid || 0;
  if (check > 0) {
    el.value.match(format) ? 0 : el.className = colorBorderChangerEfb(el.className, "border-danger");
    if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
      document.getElementById(`${el.id}-message`).classList.add('unpx');
    }
    const _me=document.getElementById(`${el.id}-message`);
    _me.innerHTML = msg;
    show_msg_efb(_me);
    const i = get_row_sendback_by_id_efb_v4(el.dataset.vid,form_id);
    if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
    sendback_state_handler_efb_v4(el.dataset.vid,false,0,form_id)
  }
  else {
    el.className = colorBorderChangerEfb(el.className, "border-success")
    hide_msg_efb(document.getElementById(`${el.id}-message`));
  }
  return check > 0 ? false : true
}
/* F01 input masks. A masked input carries data-mask-preset (catalog id; the entry comes from
 * ajax_object_efm.masks) or data-mask (the admin's own token mask), plus data-mask-store and
 * data-mask-strict. Without masks-efb.js, or for a preset the page has no entry for, the field keeps
 * the checks it had before F01 (the server does the same). */
function efb_mask_fd_efb(el) {
  if (typeof EfbMasks === 'undefined' || !el || !el.dataset) return null;
  const ds = el.dataset;
  const store = ds.maskStore === 'display' ? 'display' : 'canonical';
  if (ds.mask) {
    if (!EfbMasks.checkMask(ds.mask)) return null;
    let checksum = '';
    try {
      const row = get_structure_by_form_id_efb(ds.formid || 0).find(x => x.id_ === ds.vid);
      if (row && EfbMasks.CUSTOM_CHECKSUMS.indexOf(row.mask_checksum) !== -1) checksum = row.mask_checksum;
    } catch (e) {}
    return { kind: 'custom', def: { mask: ds.mask, checksum: checksum, case: ds.maskCase === 'upper' || ds.maskCase === 'lower' ? ds.maskCase : '' }, store: store };
  }
  if (ds.maskPreset === 'numeric') {
    let row = null;
    try { row = get_structure_by_form_id_efb(ds.formid || 0).find(x => x.id_ === ds.vid); } catch (e) {}
    return row ? { kind: 'numeric', def: EfbMasks.numDef(row), store: store } : null;
  }
  if (ds.maskPreset) {
    const all = (typeof ajax_object_efm !== 'undefined' && ajax_object_efm && ajax_object_efm.masks) ? ajax_object_efm.masks : null;
    let def = all ? all[ds.maskPreset] : null;
    if (!def || !def.regex) return null;
    if (ds.maskStrict === '1' && EfbMasks.hasStrict(def)) def = EfbMasks.strictDef(def);
    return { kind: 'preset', def: def, store: store };
  }
  return null;
}
/* Live formatting while typing (no keydown filtering: IME, Android and paste keep working). */
function efb_bind_mask_efb(el, form_id) {
  const fd = efb_mask_fd_efb(el);
  if (!fd) return false;
  EfbMasks.bindField(el, fd);
  return true;
}
/* The message for a failure code, written as text (never HTML) into the field's message box. */
function efb_mask_error_efb(el, code, fd) {
  let row = null;
  try { row = get_structure_by_form_id_efb(el.dataset.formid || 0).find(x => x.id_ === el.dataset.vid); } catch (e) {}
  const name = row && row.name ? String(row.name) : String(el.getAttribute('aria-label') || '');
  const example = String(el.getAttribute('placeholder') || '') || (fd && fd.kind === 'preset' && fd.def.placeholder ? fd.def.placeholder : '');
  const text = (typeof ajax_object_efm !== 'undefined' && ajax_object_efm && ajax_object_efm.text) ? ajax_object_efm.text : {};
  const msg = EfbMasks.fieldMessage(code, name, example, text);
  el.className = colorBorderChangerEfb(el.className, "border-danger");
  if (typeof efb_set_field_error_efb === 'function') { efb_set_field_error_efb(el, msg); return; }
  const vd = document.getElementById(`${el.id}-message`);
  if (vd) { vd.textContent = msg; show_msg_efb(vd); }
}
/* Stored value -> what the input shows (offline restore puts +98912... back as 0912 ...). */
function efb_mask_display_efb(el, value) {
  const fd = efb_mask_fd_efb(el);
  if (!fd || value === '') return value;
  return fd.kind === 'custom' ? EfbMasks.applyMask(fd.def.mask, value) : EfbMasks.formatDisplay(fd.def, value);
}
/* National ids, card numbers, IBANs and CVCs are not kept in localStorage drafts (like passwords). */
function efb_mask_row_sensitive_efb(r) {
  if (!r || typeof r !== 'object' || !r.id_ob || typeof document === 'undefined') return false;
  const el = document.getElementById(r.id_ob);
  const fd = el ? efb_mask_fd_efb(el) : null;
  if (!fd) return false;
  if (fd.kind === 'custom') return fd.def.checksum !== '';
  return ['national_id', 'bank_card', 'iban', 'tax_id', 'passport'].indexOf(fd.def.category) !== -1 || fd.def.id === 'intl.cvc';
}
/* Email, phone, URL and number fields: true when the typed value is acceptable.
 * Email and phone show their message here; URL and number already show it from
 * the change handler (number Min/Max are values, carried in minlength/maxlength). */
function valid_typed_value_efb(type, el) {
  if (type == 'masked') {
    const fd = efb_mask_fd_efb(el);
    if (!fd) return el.type == 'tel' ? valid_phone_emsFormBuilder(el) : true;
    const r = EfbMasks.fieldCheck(fd, el.value);
    if (!r.ok) efb_mask_error_efb(el, r.code, fd);
    return r.ok;
  }
  if (type == 'email') return valid_email_emsFormBuilder(el);
  if (type == 'tel') return valid_phone_emsFormBuilder(el);
  if (type == 'number' && el.dataset && el.dataset.num === '1' && window.EFBCalc) {
    const nr = efb_num_check_efb(el, null);
    return !!(nr.empty || nr.ok);
  }
  if (type == 'number') {
    const n = Number(el.value);
    if (isNaN(n)) return false;
    if (el.hasAttribute('minlength') && n < el.minLength) return false;
    const max = el.hasAttribute('maxlength') ? el.maxLength : 0;
    return !(max > 0 && n > max);
  }
  return /^https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?!&\/=;',]*)$/.test(el.value);
}
function valid_password_emsFormBuilder(el) {
  let check = 0;
  const id = el.id;
  let offsetw = offset_view_efb();
  const form_id = el.dataset.formid || 0;
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${ajax_object_efm.text.enterThePassword}','',10,'danger');"></div>` : efb_var.text.enterThePassword;
  if (el.value.length < 3) {
    el.className = colorBorderChangerEfb(el.className, "border-danger");
    const i = get_row_sendback_by_id_efb_v4(el.dataset.vid,form_id);
    if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
    if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
      document.getElementById(`${id}-message`).classList.add('unpx');
    }
    sendback_state_handler_efb_v4(el.dataset.vid,false,0,form_id)
    const _mp=document.getElementById(`${id}-message`);
    _mp.innerHTML = msg;
    show_msg_efb(_mp);
    return false;
  }
  else {
    el.className = colorBorderChangerEfb(el.className, "border-success")
    hide_msg_efb(document.getElementById(`${id}-message`));
    return true;
  }
}
/* ---------------------------------------------------------------- F03 field rules (EfbRules, field-rules-efb.js) */

/** The browser's part of the rules (the server decides): plan parts and the context of a field. */
function efb_rules_ctx_efb(form_id, el) {
  const cfg = (typeof efb_var !== 'undefined' && efb_var.field_rules) || (typeof ajax_object_efm !== 'undefined' && ajax_object_efm.field_rules) || {};
  const structure = get_structure_by_form_id_efb(form_id) || [];
  const emails = [];
  structure.forEach((f) => {
    if (!f || f.type !== 'email') return;
    const e = efb_get_by_id_in_form_efb(form_id, `${f.id_}_`);
    if (e && typeof e.value === 'string' && e.value.indexOf('@') > 0) emails.push(e.value.split('@')[0]);
  });
  const ctx = { paid: !!cfg.paid, parts: cfg.parts || {}, emails: emails, formName: structure[0] && structure[0].formName ? String(structure[0].formName) : '' };
  if (el && el.type === 'password') {
    const c = document.getElementById(`${el.id}confirm`);
    if (c) ctx.confirm = c.value;
  }
  return ctx;
}

/** Whether the field has rules the browser checks (no EfbRules on the page: none). */
function efb_rules_active_efb(ob, form_id, el) {
  if (typeof EfbRules === 'undefined' || !ob) return false;
  return EfbRules.hasRules(ob, efb_rules_ctx_efb(form_id, el));
}

/** Whether the field's length limits are checked by EfbRules (its own messages) instead of the legacy check. */
function efb_rules_owns_length_efb(ob, form_id) {
  if (typeof EfbRules === 'undefined' || !ob) return false;
  return EfbRules.activeRules(ob, efb_rules_ctx_efb(form_id)).indexOf('length') !== -1;
}

/** Field-level error: the message under the field (textContent), aria-invalid and aria-describedby, announced. */
function efb_rules_show_error_efb(el, text) {
  if (!el) return;
  const vd = document.getElementById(`${el.id}-message`);
  el.className = colorBorderChangerEfb(el.className, "border-danger");
  el.setAttribute('aria-invalid', 'true');
  if (vd) {
    vd.textContent = text;
    vd.setAttribute('dir', 'auto'); // a sentence in another script than the page keeps its own order
    vd.style.unicodeBidi = 'plaintext'; // a theme's `direction: rtl` outranks dir=auto (BUGS-FOUND, F06)
    vd.setAttribute('role', 'alert');
    const ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    if (ids.indexOf(vd.id) === -1) { ids.push(vd.id); el.setAttribute('aria-describedby', ids.join(' ')); }
    show_msg_efb(vd);
  }
}
function efb_rules_clear_error_efb(el) {
  if (!el) return;
  el.removeAttribute('aria-invalid');
  const vd = document.getElementById(`${el.id}-message`);
  if (vd) {
    const ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter((x) => x && x !== vd.id);
    if (ids.length) el.setAttribute('aria-describedby', ids.join(' ')); else el.removeAttribute('aria-describedby');
    vd.removeAttribute('role');
  }
}

/**
 * The first failing rule of a field's value, shown at the field; true when the value passes.
 * The same order and the same texts as Field_Rules::check() (msg_<rule> of the owner, else the default).
 */
function efb_rules_check_efb(el, ob, value, form_id) {
  if (!efb_rules_active_efb(ob, form_id, el)) return true;
  const err = EfbRules.firstError(ob, value, efb_rules_ctx_efb(form_id, el));
  const confirm = el && el.type !== undefined ? document.getElementById(`${el.id}confirm`) : null;
  if (confirm) {
    // H: a mismatch is told at the second box (its own message, aria-invalid), the first stays as it is.
    const cm = document.getElementById(`${el.id}confirm-message`);
    if (err && err.code === 'mismatch') {
      efb_rules_clear_error_efb(el);
      confirm.setAttribute('aria-invalid', 'true');
      confirm.className = colorBorderChangerEfb(confirm.className, 'border-danger');
      if (cm) { cm.setAttribute('dir', 'auto'); cm.textContent = EfbRules.message(ob, 'mismatch', 'tvPwMismatch_', efb_var.text).text; }
      return false;
    }
    confirm.removeAttribute('aria-invalid');
    if (cm) cm.textContent = '';
  }
  if (!err) { efb_rules_clear_error_efb(el); return true; }
  const m = EfbRules.message(ob, err.rule, err.key, efb_var.text, err.n);
  efb_rules_show_error_efb(el, m.text);
  return false;
}

/** The polite live region of a form (one per form), for counter thresholds (§4.3). */
function efb_rules_announce_efb(form_id, text) {
  const id = `efb-live-${form_id}`;
  let live = document.getElementById(id);
  if (!live) {
    live = document.createElement('div');
    live.id = id;
    live.className = 'efb efb-vh';
    live.setAttribute('role', 'status');
    live.setAttribute('aria-live', 'polite');
    (document.getElementById(`body_efb_${form_id}`) || document.body).appendChild(live);
  }
  live.textContent = '';
  setTimeout(() => { live.textContent = text; }, 30);
}

/** The live counter under a field with counter = chars | words (updated on input; announced at thresholds only). */
function efb_rules_bind_counter_efb(el, form_id) {
  if (typeof EfbRules === 'undefined' || !el || !el.dataset || el.dataset.efbCounter === '1') return;
  const ob = (get_structure_by_form_id_efb(form_id) || []).find((x) => x && x.id_ === el.dataset.vid);
  const c = EfbRules.counter(ob);
  const box = c ? document.getElementById(`${el.dataset.vid}_-counter`) : null;
  if (!box) return;
  el.dataset.efbCounter = '1';
  const locale = (typeof efb_var !== 'undefined' && (efb_var.wp_lan || efb_var.language)) || document.documentElement.lang || 'en';
  let zone = null;
  const update = () => {
    const n = c.kind === 'words' ? EfbRules.countWords(el.value) : EfbRules.countChars(el.value);
    const r = EfbRules.counterText(c, n, (typeof efb_var !== 'undefined' && efb_var.text) || {}, locale);
    box.textContent = r.text;
    box.classList.toggle('efb-counter-over', r.over);
    if (zone !== null && r.zone !== zone && r.zone !== 'ok') efb_rules_announce_efb(form_id, r.text);
    zone = r.zone;
  };
  el.addEventListener('input', update);
  update();
}

/* F03-G: the help button of a field (toggletip): click / Enter / Space open and close it, Escape and a
 * click outside close it; the text is also the field's aria-describedby, so it is read either way. */
function efb_rules_toggletip_efb(btn, open) {
  const pop = btn ? document.getElementById(btn.getAttribute('aria-controls')) : null;
  if (!pop) return;
  const on = open === undefined ? btn.getAttribute('aria-expanded') !== 'true' : !!open;
  btn.setAttribute('aria-expanded', on ? 'true' : 'false');
  pop.classList.toggle('efb-help-open', on);
}
if (typeof document !== 'undefined' && typeof window !== 'undefined' && document.addEventListener && !window.efb_toggletip_bound_efb) {
  window.efb_toggletip_bound_efb = true;
  document.addEventListener('click', (e) => {
    const btn = e.target && e.target.closest ? e.target.closest('.efb-help-btn') : null;
    document.querySelectorAll('.efb-help-btn[aria-expanded="true"]').forEach((b) => {
      if (b !== btn && !(e.target.closest && e.target.closest('#' + CSS.escape(b.getAttribute('aria-controls'))))) efb_rules_toggletip_efb(b, false);
    });
    if (btn) { e.preventDefault(); efb_rules_toggletip_efb(btn); }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.efb-help-btn[aria-expanded="true"]').forEach((b) => {
      efb_rules_toggletip_efb(b, false);
      if (document.activeElement && (document.activeElement === b || (document.getElementById(b.getAttribute('aria-controls')) || {}).contains?.(document.activeElement))) b.focus();
    });
  });
}

/* F03-H: password confirmation, strength meter and the show-password button. */
function efb_rules_add_confirms_efb(data, form_id) {
  const body = document.getElementById('body_efb_' + form_id) || document;
  const boxes = body.querySelectorAll('input.efb-pw-confirm[data-for]');
  if (!boxes.length || !data || typeof data !== 'object') return;
  const out = {};
  boxes.forEach((b) => { out[b.getAttribute('data-for')] = b.value; });
  data.password_confirms = out;
}
function efb_rules_pw_meter_efb(el, form_id) {
  const meter = document.getElementById(`${el.id}-meter`);
  if (!meter || typeof EfbRules === 'undefined') return;
  const ctx = efb_rules_ctx_efb(form_id, el);
  const sc = el.value === '' ? -1 : EfbRules.pwScore(el.value, ctx);
  const words = [['tvPwWeak', 'Weak'], ['tvPwWeak', 'Weak'], ['tvPwFair', 'Fair'], ['tvPwGood', 'Good'], ['tvPwStrong', 'Strong']];
  const t = (k, en) => (typeof efb_var !== 'undefined' && efb_var.text && efb_var.text[k]) || en;
  meter.setAttribute('data-score', String(sc));
  const word = meter.querySelector('.efb-pw-word');
  if (word) word.textContent = sc < 0 ? '' : t('tvPwStrength', 'Password strength') + ': ' + t(words[sc][0], words[sc][1]);
}
if (typeof document !== 'undefined' && typeof window !== 'undefined' && document.addEventListener && !window.efb_pw_bound_efb) {
  window.efb_pw_bound_efb = true;
  document.addEventListener('click', (e) => {
    const b = e.target && e.target.closest ? e.target.closest('.efb-pw-toggle') : null;
    if (!b) return;
    e.preventDefault();
    const input = document.getElementById(b.getAttribute('aria-controls'));
    if (!input) return;
    const s0 = input.selectionStart, s1 = input.selectionEnd;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    b.setAttribute('aria-pressed', show ? 'true' : 'false');
    b.setAttribute('aria-label', show ? b.getAttribute('data-hide') : b.getAttribute('data-show'));
    try { if (s0 !== null && s0 !== undefined) input.setSelectionRange(s0, s1); } catch (x) { /* some types refuse a selection */ }
  });
  document.addEventListener('input', (e) => {
    const el = e.target;
    if (!el || el.tagName !== 'INPUT') return;
    if (el.type === 'password' || (el.type === 'text' && el.classList.contains('emsFormBuilder_v') && document.getElementById(`${el.id}-meter`))) {
      if (document.getElementById(`${el.id}-meter`)) efb_rules_pw_meter_efb(el, el.dataset.formid || 0);
    }
  });
  // The second box: checked again with the first when either changes (blur), never on every key.
  document.addEventListener('change', (e) => {
    const c = e.target;
    if (!c || !c.classList || !c.classList.contains('efb-pw-confirm')) return;
    const main = document.getElementById(`${c.getAttribute('data-for')}_`);
    if (main && main.value !== '' && typeof handle_change_event_efb_v4 === 'function') handle_change_event_efb_v4(main, main.dataset.formid || 0);
  });
}

/** A server answer that names a field (field_id): the field shows the server's sentence. */
function efb_rules_server_error_efb(form_id, field_id, m) {
  const el = efb_get_by_id_in_form_efb(form_id, `${field_id}_`);
  if (!el || typeof m !== 'string' || m === '') return;
  // The server's message is escaped text plus the <b> label: read it as text (DOMParser runs no handler).
  let text = m;
  try { text = new DOMParser().parseFromString(m, 'text/html').body.textContent || ''; } catch (e) { text = m.replace(/<[^>]*>/g, ''); }
  efb_rules_show_error_efb(el, text);
}

function valid_phone_emsFormBuilder(el) {
  let offsetw = offset_view_efb();
  const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${ajax_object_efm.text.enterThePhones}','',10,'danger');"></div>` : ajax_object_efm.text.enterThePhones;
  let check = 0;
  var val = el.value.replace(/\s+/g, ' ').trim();
  var formatChars = /^[0-9+\-\s().]+$/;
  var digitCount = (val.match(/[0-9]/g) || []).length;
  var plusPos = val.indexOf('+');
  var plusCount = (val.match(/\+/g) || []).length;
  var consecutiveSpecial = /[\-\s().]{3,}/.test(val);
  var openParen = (val.match(/\(/g) || []).length;
  var closeParen = (val.match(/\)/g) || []).length;
  if (!formatChars.test(val) || digitCount < 7 || digitCount > 15 || val.length > 25 || plusCount > 1 || (plusCount === 1 && plusPos !== 0) || consecutiveSpecial || openParen !== closeParen) {
    check = 1;
  }
  const id = el.id;
  const form_id = el.dataset.formid || 0;
  let msg_el = document.getElementById(`${id}-message`);
  if (check >0) {
    el.className = colorBorderChangerEfb(el.className, "border-danger");
    const i = get_row_sendback_by_id_efb_v4(el.dataset.vid,form_id);
    if (i != -1) { sendBack_emsFormBuilder_pub.splice(i, 1) }
    if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
      msg_el.classList.add('unpx');
    }
    msg_el.innerHTML = msg;
    show_msg_efb(msg_el);
    sendback_state_handler_efb_v4(el.dataset.vid,false,0,form_id)
  }
  else {
    el.className = colorBorderChangerEfb(el.className, "border-success")
    hide_msg_efb(msg_el);
  }
  return check > 0 ? false : true
}
function valid_file_emsFormBuilder(id,tp,filed,form_id) {
  let valj_efb = get_structure_by_form_id_efb(form_id);
  let msgEl = document.getElementById(`${id}_-message`);
  hide_msg_efb(msgEl);
  document.getElementById(`${id}_`).classList.remove('border-danger');
  let file = ''
  if (true) {
    const f = valj_efb.find(x => x.id_ === id);
    file = f.file && f.file.length > 3 ? f.file : 'Zip';
    file = file.toLocaleLowerCase();
  }
  let check = 0;
  let rtrn = false;
  let fileName = ''
  const recorderTypes_efb = ["audio_recorder", "video_recorder", "screen_recorder"];
  const i = recorderTypes_efb.indexOf(valj_efb.find(x => x.id_ === id)?.type) !== -1 ? `${id}_file` : `${id}_`;
  let message = "";
  /* F07 §12: the same default as the server (Upload_Guard::DEFAULT_MAX_MB) and as the message below. */
  let file_size = 20*1024*1024;
  const indx = valj_efb.findIndex(x => x.id_ === id);
  let val_in = valj_efb[indx];
    if(val_in.hasOwnProperty('max_fsize') && val_in.max_fsize.length>0){
      file_size = Number(val_in.max_fsize) * 1024 * 1024;
    }
    const el = document.getElementById(i);
    if(filed==''){
      if (el.files[0] && el.files[0].size <= file_size) {
        const filetype = el.files[0].type.length > 1 && file!='customize'  ? el.files[0].type : el.files[0].name.slice(el.files[0].name.lastIndexOf(".") + 1)
        const r = validExtensions_efb_fun(file, filetype,indx)
        if (r == true) {
          check = +1;
        }
        filed = el.files;
      }
    }else{
      if (filed && filed.size <= file_size) {
        const filetype = filed.type.length > 1 && file!='customize'  ? filed.type : filed.name.slice(filed.name.lastIndexOf(".") + 1)
        const r = validExtensions_efb_fun(file, filetype,indx)
        if (r == true) {
          check = +1;
        }
      }
      let fi = filed ;
      filed =[];
      filed.push(fi);
    }
    if (check > 0) {
      hide_msg_efb(msgEl);
      fun_upload_file_api_emsFormBuilder(id, filed[0].type,tp,filed[0]);
      rtrn = true;
    } else {
      const f_s_l = val_in.hasOwnProperty('max_fsize') && val_in.max_fsize.length>0 ? val_in.max_fsize : 20;
      const m =ajax_object_efm.text.pleaseUploadA.replace('NN', efb_var.text[val_in.file]);
      const size_m = ajax_object_efm.text.fileSizeIsTooLarge.replace('NN', f_s_l);
      if (filed[0] && message.length < 2){ message = filed[0].size <= file_size ? m : size_m;}
      else if(filed.length==0){ message =size_m;}
      const newClass = colorTextChangerEfb(msgEl.className, "text-danger");
      newClass!=false ? msgEl.className=newClass:0;
      msgEl.innerHTML = message;
      show_msg_efb(msgEl);
      rtrn = false;
    }
    return rtrn;
}
function fun_show_alert_setting_emsFormBuilder() {
  const m = `<div class="efb alert alert-danger" role="alert"> <h2 class="efb font-weight-bold">
            ${ajax_object_efm.text.error}</br>
            ${ajax_object_efm.text.formIsNotShown}</br>
            <a href="https://www.youtube.com/embed/JI7RojBgU_o"  target="_blank" class="efb font-weight-normal">${ajax_object_efm.text.pleaseWatchTutorial}</a> </h2> </div>`
  if (document.getElementById('body_emsFormBuilder')) {
    document.getElementById('body_emsFormBuilder').innerHTML = m;
  } else if (document.getElementById('body_tracker_emsFormBuilder')) {
    document.getElementById('body_tracker_emsFormBuilder').innerHTML = m;
  } else {
    window.alert(`${ajax_object_efm.text.error} ${ajax_object_efm.text.formIsNotShown}`)
  }
}
async function validation_before_send_efb(form_id) {
  let valj_efb
  if (form_id){
    valj_efb = get_structure_by_form_id_efb(form_id);
  }else{
    valj_efb = valj_efb;
  }
  /* This validation runs right after a final-submit attempt, so
   * dataset.currentstep may already be past the last real step (see
   * efb_go_to_step_direct's doc comment) — fall back to the last real
   * step instead of fun_prev_send(), which assumes a currently-visible
   * fieldset exists at dataset.currentstep. */
  const btn_prev = `efb_go_to_step_direct(${form_id}, ${Number(valj_efb[0].steps) || 1})`;
  const count = [0, 0]
  let fill = 0;
  let require = 0;
  const id_body = form_id == 0 ? 'body_efb' : `body_efb_${form_id}`;
  const fileLikeTypes_efb = ["file", "audio_recorder", "video_recorder", "screen_recorder"];
  const recorderTypes_efb_v4 = ["audio_recorder", "video_recorder", "screen_recorder"];
  for (const v of valj_efb) {
    require += v.required == true && fileLikeTypes_efb.indexOf(v.type) === -1 ? 1 : 0;
    if (fileLikeTypes_efb.indexOf(v.type) !== -1) {
      const fileInputId_efb = recorderTypes_efb_v4.indexOf(v.type) !== -1 ? `${v.id_}_file` : `${v.id_}_`;
      // F07: a field of several files empties its input after each choice; its answer is its rows.
      if (typeof window.EFBUpload !== 'undefined' && window.EFBUpload.isMulti(v.id_, form_id)) {
        if (v.required == true && window.EFBUpload.count(v.id_, form_id) < 1) fill -= 1;
        continue;
      }
      const fileInputEl_efb = document.getElementById(fileInputId_efb);
      if (fileInputEl_efb && fileInputEl_efb.files && fileInputEl_efb.files[0] == undefined && v.required == true) {
        fill -= 1;
      }
    }
  }
  const fileRowsCounted_efb = {};
  let count_ = 0;
  for (const row of sendBack_emsFormBuilder_pub) {
    if(form_id && Number((row && row.hasOwnProperty('form_id') ? row.form_id : infer_form_id_by_field_efb(row))) !== Number(form_id)) {
      continue;
    }
    count_ += 1;
    if(row==null || typeof(row)!='object') {
      count_ -= 1;
      continue;
    }
    // F08: a hidden value is not an answer: a form with nothing filled in is still empty.
    if (row.type === 'hiddenField') { count_ -= 1; continue; }
    const rowFilled = is_required_value_filled_efb(row, row.type || '');
    count[0] += 1;
    if (row.value == "@file@") {
      // F07: the files of one field count once.
      if (fileRowsCounted_efb[row.id_]) { count[0] -= 1; continue; }
      fileRowsCounted_efb[row.id_] = true;
      const indx = valj_efb.findIndex(x => x.id_ == row.id_);
      if (indx != -1) {
        if(valj_efb[indx].hasOwnProperty("disabled") && valj_efb[indx].disabled==true &&
           ((valj_efb[indx].hasOwnProperty('hidden') && valj_efb[indx].hidden==false) || valj_efb[indx].hasOwnProperty('hidden')==false)
          ){
            const i = get_row_sendback_by_id_efb_v4(valj_efb[indx].id_,valj_efb[indx].form_id);

            sendBack_emsFormBuilder_pub.splice(i,1);
            count[0] -= 1;
            continue
          }
        if (rowFilled) {
          fill += valj_efb[indx].required == true ? 1 : 0;
          count[1] += 1;
        }
      }
    } else if (row.type != "@file@" && row.type != "payment") {
      const indx = valj_efb.findIndex(x => x.id_ == row.id_);
      if(indx!=-1){
        if ( (valj_efb[indx].type == "multiselect" || valj_efb[indx].type == "option" || valj_efb[indx].type == "Select"
        || valj_efb[indx].type == "payMultiselect" || valj_efb[indx].type == "paySelect")) {
        const exists = valj_efb[indx].type == "multiselect" || valj_efb[indx].type == "payMultiselect" ? valj_efb.findIndex(x => x.parent == valj_efb[indx].id_) : valj_efb.findIndex(x => x.parents == valj_efb[indx].id_);
        fill += valj_efb[indx].required == true && exists > -1 ? 1 : 0;
      }else if(valj_efb[indx].type == "chlCheckBox"){
          const exists = valj_efb.findIndex(x => x.parents == valj_efb[indx].id_)
          fill += valj_efb[indx].required == true && exists > -1 ? 1 : 0;
        }else {
          fill += valj_efb[indx].required == true ? 1 : 0;
        }
      if (rowFilled) count[1] += 1;
      }
    } else {
      if (rowFilled) count[1] += 1;
    }
  }
  require = require > fill ? 1 : 0;
  if (((count[1] == 0 && count[0] != 0) || (count[0] == 0 && count[1] == 0) || require == 1) && ( (valj_efb[0].hasOwnProperty("logic")== true || valj_efb[0].hasOwnProperty("logic")==false) && valj_efb[0].logic==false )) {
    const body_efb = document.getElementById(id_body);
    const efb_final_step = efb_get_final_step_efb(form_id, body_efb);
    if (efb_final_step) efb_final_step.innerHTML = `<h3 class='efb emsFormBuilder'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center fs-2 efb"></i></h1><h3 class="efb fs-3 efb text-muted">${ajax_object_efm.text.error}</h3> <span class="efb mb-2 fs-5 efb text-muted"> ${require != 1 ? ajax_object_efm.text.PleaseFillForm : ajax_object_efm.text.pleaseFillInRequiredFields} </br></span>
     <div class="efb m-1">${efb_error_prev_button_efb(valj_efb, btn_prev)}</div></div>`;
    smoothy_scroll_postion_efb(id_body)
    for (const v of valj_efb) {
      if (v.type != 'file' && v.type != 'dadfile' && v.type != 'checkbox' && v.type != 'radiobutton' && v.type != 'option' && v.type != 'multiselect' && v.type != 'select' && v.type != 'payMultiselect' && v.type != 'paySelect' && v.type != 'payRadio' && v.type != 'payCheckbox' && v.type != 'chlCheckBox') {
        (v.id_ && document.getElementById(v.id_).value.length < 5) ? document.getElementById(`${v.id_}-message`).innerHTML = ajax_object_efm.text.enterTheValueThisField : 0
        return false;
      }
    }
  } else {
    return true;
  }
}
function show_user_profile_emsFormBuilder(ob, form_id = 0) {
  /* The card built here mirrors the server one (Formbuilder::show_user_profile_emsFormBuilder),
     which passes the form id to the logout call. Without it the click built the
     body id "body_efb_undefined" and threw before the request was sent. */
  const efb_logout_form_id = Number(form_id) || 0;
  return `<div class="efb mt-5"><div class="efb card-block text-center text-dark ">
              <div class="efb mb-3 d-flex justify-content-center"> <img src="${ob.user_image}" class="efb userProfileImageEFB" alt="${ob.display_name}"> </div>
              <h6 class="efb  fs-5 mb-1 d-flex justify-content-center text-dark">${ob.display_name}</h6> <p class="efb  fs-6">${ob.user_login}</p>
              <button type="button"  class="efb btn fs-5 btn-lg btn-danger efb mt-1 " onclick="fun_logout_efb(${efb_logout_form_id})">${ajax_object_efm.text.logout}</button>
          </div> </div>`
}
function fun_logout_efb(form_id) {
  form_id = (form_id === undefined || form_id === null || form_id === '') ? 0 : form_id;
  const id_body = form_id == 0 ? 'body_efb' : `body_efb_${form_id}`;
  const logout_body_efb = document.getElementById(id_body);
  if (logout_body_efb) logout_body_efb.innerHTML = loading_messge_efb();
  form_type_emsFormBuilder = "logout";
  formNameEfb = "logout";
  ajax_object_efm.type = "logout";
  sendBack_emsFormBuilder_pub.push ({ logout: true ,type:'logout' ,form_id: form_id});
  recaptcha_emsFormBuilder = '';
  indx = valj_efb_new.findIndex(x => x.id == form_id);
  if(indx!=-1) {
    valj_efb_new[indx].type = 'logout';
  }
  actionSendData_emsFormBuilder(form_id);
}
function Show_recovery_pass_efb() {
  let el = document.getElementById('recoverySectionemsFormBuilder');
  let elBtnBack = document.getElementById('prev_efb_send');
  let iconBtn = document.getElementById('icon_btn_Show_recovery_efb');
  let elMsg = document.getElementById('alertFinalStepEFB');
  const isHidden = el.classList.contains('d-none');

  if (isHidden) {

      el.classList.remove('d-none');
      el.classList.remove('fadeOut');
      el.classList.add('fadeIn');
      iconBtn.className = 'efb bi-chevron-up';
      el = document.getElementById('btn_recovery_pass_efb');
      el.disabled = true;
      elMsg.classList.add('d-none');
      elBtnBack.classList.add('d-none');
  } else {
      el.classList.remove('fadeIn');
      el.classList.add('fadeOut');

      iconBtn.className = 'efb bi-chevron-down';

      el.addEventListener('animationend', function() {
          el.classList.add('d-none');
          elBtnBack.classList.remove('d-none');
          elMsg.classList.remove('d-none');
      }, { once: true });
  }

  document.getElementById('recoverySectionemsFormBuilder').scrollIntoView({behavior: "smooth", block: "center", inline: "center"});

  if ( el.dataset.hasOwnProperty('id') &&el.dataset.id == 1) {
    el.dataset.id = 0;
    const us = document.getElementById('username_recovery_pass_efb');
    const format = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    el.addEventListener("click", (e) => {
      form_type_emsFormBuilder = "recovery";
      formNameEfb = form_type_emsFormBuilder;
      document.getElementById('efb-final-step').innerHTML = `<div class="efb" style="text-align:center;padding:32px 0;"><div style="width:40px;height:40px;border:3px solid var(--efb-resp-primary-10);border-top-color:var(--efb-resp-primary);border-radius:50%;animation:efb-spin .7s linear infinite;margin:0 auto 12px;"></div><p style="color:var(--efb-resp-text-muted);font-family:var(--efb-resp-font-family);font-size:var(--efb-resp-font-size);">${ajax_object_efm.text.pleaseWaiting}</p></div>`
      const valj = valj_efb_new.find(x => x.type ==  'login');
      const form_id = valj ? valj.id : 0;
      sendBack_emsFormBuilder_pub = { email: efb_normalize_email_efb(us.value) ,'recovery':true , form_id: form_id};
      actionSendData_emsFormBuilder(form_id)
    })
    us.addEventListener("keyup", (e) => {
      const isValid = efb_normalize_email_efb(us.value).match(format);
      if (isValid) {
        el.classList.remove('disabled');
        el.disabled = false;
      } else {
        if (!el.classList.contains('disabled')) {
          el.classList.add('disabled');
        }
        el.disabled = true;
      }
    })
  }
}
/* Remove only the sendBack_emsFormBuilder_pub rows that belong to a single field.
   Most field types keep one row per id_, but checkbox/multiselect/chlCheckBox-style
   fields can keep several rows for the same field (one per selected option, matched
   either by id_ or, for chlCheckBox, by id_ob referencing the parent field's id_) —
   so every matching row for that field must be removed, not just the first. */
function remove_sendback_rows_by_field_id_efb(field_id, form_id) {
  if (!field_id) return;
  let changed = false;
  for (let i = sendBack_emsFormBuilder_pub.length - 1; i >= 0; i--) {
    const row = sendBack_emsFormBuilder_pub[i];
    if (!row) continue;
    if (Number(row.form_id) !== Number(form_id)) continue;
    if (row.id_ === field_id || row.id_ob === field_id) {
      sendBack_emsFormBuilder_pub.splice(i, 1);
      changed = true;
    }
  }
  if (changed) localStorage.setItem('sendback', efb_sendback_for_storage_efb(sendBack_emsFormBuilder_pub));
}
/* Remove every sendBack_emsFormBuilder_pub row that belongs to the submitted form,
   leaving rows for any other forms on the page untouched. */
function clear_sendback_rows_by_form_id_efb(form_id) {
  const before = sendBack_emsFormBuilder_pub.length;
  sendBack_emsFormBuilder_pub = sendBack_emsFormBuilder_pub.filter(row => row && Number(row.form_id) !== Number(form_id));
  if (sendBack_emsFormBuilder_pub.length !== before) localStorage.setItem('sendback', efb_sendback_for_storage_efb(sendBack_emsFormBuilder_pub));
}
/* Password values never go to browser storage. The whole answer list was
 * written to localStorage['sendback'] on every keystroke, password rows included,
 * and it is cleared only after a successful submit - so a login or register
 * password stayed readable on a shared computer after a failed submit or when
 * the visitor simply left. The in-memory list still carries the value for the
 * submit itself. */
function efb_sendback_for_storage_efb(rows) {
  // Every writer of localStorage['sendback'] goes through here: note when, so
  // answers left behind expire (efb_expire_stored_answers_efb).
  try { localStorage.setItem('sendback_t', String(Date.now())); } catch (e) {}
  if (!Array.isArray(rows)) return JSON.stringify(rows);
  return JSON.stringify(rows.filter(r => !(r && typeof r === 'object' && String(r.type || '').toLowerCase() === 'password') && !(typeof efb_mask_row_sensitive_efb === 'function' && efb_mask_row_sensitive_efb(r))));
}
function efb_scrub_stored_passwords_efb() {
  try {
    const raw = localStorage.getItem('sendback');
    if (!raw || raw.indexOf('assword') === -1) return;
    const rows = JSON.parse(raw);
    if (Array.isArray(rows)) localStorage.setItem('sendback', efb_sendback_for_storage_efb(rows));
  } catch (e) {}
}
/* Answers kept in the browser (name, phone, address...) stayed readable on a
 * shared computer for good. They are dropped when a page loads more than a
 * day after the last write, or when their age is unknown, unless the visitor
 * is coming back from the bank (PersiaPay restores the form from them). */
const EFB_STORED_ANSWERS_MAX_AGE_MS = 24 * 60 * 60 * 1000;
function efb_payment_return_pending_efb() {
  try {
    return /[?&]Authority=/i.test(location.search) || !!sessionStorage.getItem('payId');
  } catch (e) { return false; }
}
function efb_expire_stored_answers_efb() {
  try {
    if (!localStorage.getItem('sendback')) { localStorage.removeItem('sendback_t'); return; }
    if (efb_payment_return_pending_efb()) return;
    const t = Number(localStorage.getItem('sendback_t'));
    if (!t || Date.now() - t > EFB_STORED_ANSWERS_MAX_AGE_MS) {
      localStorage.removeItem('sendback');
      localStorage.removeItem('sendback_t');
    }
  } catch (e) {}
}
/* What was stored before this page wrote anything: the form's own default
 * values are stored while it is built, which replaced the saved answers. */
let efb_stored_answers_at_load_efb = [];
try {
  efb_scrub_stored_passwords_efb();
  efb_expire_stored_answers_efb();
  const stored_at_load = JSON.parse(localStorage.getItem('sendback') || '[]');
  if (Array.isArray(stored_at_load)) efb_stored_answers_at_load_efb = stored_at_load;
  // Add-ons write the list too; whatever they stored is cleaned when the page is left.
  window.addEventListener('pagehide', efb_scrub_stored_passwords_efb);
} catch (e) {}
function efb_offline_addon_active_efb() {
  try {
    if (typeof fun_state_check_addon_AdnOF_setting_efb === 'function') return fun_state_check_addon_AdnOF_setting_efb();
    const s = typeof ajax_object_efm.form_setting == 'string' ? JSON.parse(ajax_object_efm.form_setting) : ajax_object_efm.form_setting;
    const a = s && s.addons ? s.addons.AdnOF : null;
    return !!a && (a === true || a == 1 || a.active === true || a.active == 1);
  } catch (e) { return false; }
}
/* "Offline form" (AfLnFrm) on: answers kept after a lost connection come back
 * when the page is opened again, as the offline message promises, so the
 * visitor can press Send once online. Only plain fields are filled, through
 * the normal change handler so every check and rule runs. Payment forms keep
 * their own return path. */
const EFB_RESTORABLE_FIELD_TYPES = ['text', 'email', 'url', 'number', 'date', 'color', 'range', 'textarea', 'firstName', 'lastName', 'tel', 'radio', 'checkbox', 'select', 'pdate', 'ardate'];
async function efb_restore_offline_answers_efb() {
  const rows = efb_stored_answers_at_load_efb;
  efb_stored_answers_at_load_efb = [];
  if (!Array.isArray(rows) || rows.length === 0) return;
  if (efb_payment_return_pending_efb() || !efb_offline_addon_active_efb()) return;
  if (typeof get_structure_by_form_id_efb !== 'function') return;
  const form_ids = [...new Set(rows.filter(r => r && typeof r === 'object' && r.form_id != null).map(r => Number(r.form_id)))];
  for (const fid of form_ids) {
    let structure = null;
    try { structure = get_structure_by_form_id_efb(fid); } catch (e) { structure = null; }
    if (!Array.isArray(structure) || !structure[0]) continue;
    const head = structure[0];
    if (!(head.AfLnFrm === true || Number(head.AfLnFrm) === 1) || head.type === 'payment') continue;
    await efb_apply_saved_rows_efb(fid, rows);
  }
}
/* Fills a form with saved answers through the normal change handler, so every check and rule
 * runs (shared by the offline restore and F11 "save and continue later"). Returns the ids of
 * the rows it could not show (types that draw their own UI): the visitor fills those again. */
async function efb_apply_saved_rows_efb(fid, rows) {
  fid = Number(fid);
  const skipped = [];
  for (const row of (Array.isArray(rows) ? rows : [])) {
      if (!row || typeof row !== 'object' || Number(row.form_id) !== fid || !row.id_ob) continue;
      // F05: an "Other" text and a ranking go back through their own controls.
      if (window.EfbChoices && window.EfbChoices.restoreRow(row, fid)) continue;
      // Only fields whose visible state is the control itself. Yes/No, switch,
      // image choices, ratings, phone (intl-tel-input) and the like draw their
      // own UI: checking their hidden input put an answer in the entry that
      // the visitor could not see.
      // F06: an advanced date goes back through the picker, so today's rules decide again (a past date is refused).
      if (String(row.type || '') === 'advdate') {
        if (!window.EFBCalendar || typeof EFBCalendar.whenReady !== 'function') { skipped.push(row.id_); continue; }
        try {
          const cal = await EFBCalendar.whenReady(row.id_, fid);
          const r = await cal.setValue(row.cal ? { iso: String(row.value || ''), cal: row.cal } : String(row.value || ''), { source: 'restore' });
          if (!r.ok) skipped.push(row.id_);
        } catch (e) { skipped.push(row.id_); }
        continue;
      }
      // F08: a hidden value saved with the draft comes back only when this page gives none (a resume link has
      // no campaign parameters); the server rebuilds it on submit anyway.
      if (String(row.type || '') === 'hiddenField') {
        const cur = typeof get_row_sendback_by_id_efb_v4 === 'function' ? get_row_sendback_by_id_efb_v4(row.id_, fid) : -1;
        if (cur === -1 && typeof row.value === 'string' && row.value !== '') {
          sendBack_emsFormBuilder_pub.push({ id_: row.id_, name: row.name || '', id_ob: row.id_ + '_', amount: row.amount, type: 'hiddenField', value: row.value, session: sessionPub_emsFormBuilder, form_id: fid });
        }
        continue;
      }
      if (EFB_RESTORABLE_FIELD_TYPES.indexOf(String(row.type || '')) === -1) { skipped.push(row.id_); continue; }
      const el = document.getElementById(row.id_ob);
      // F08 §4.5: a value from the page address wins over a saved draft.
      if (el && el.getAttribute && el.getAttribute('data-efb-prefilled') === '1') continue;
      if (!el || el.disabled || el.readOnly || el.classList.contains('intlPhone')) { skipped.push(row.id_); continue; }
      if (el.dataset && el.dataset.formid && Number(el.dataset.formid) !== fid) continue;
      const tag = el.tagName;
      const type = String(el.type || '').toLowerCase();
      let value = row.value == null ? '' : String(row.value);
      if (tag === 'TEXTAREA' || (tag === 'INPUT' && ['text', 'email', 'tel', 'url', 'number', 'date', 'color', 'range'].indexOf(type) !== -1)) {
        // Stored textarea answers carry line breaks as "@n#".
        if (tag === 'TEXTAREA') value = typeof text_nr_efb === 'function' ? text_nr_efb(value, 0) : value.replace(/@n#/g, '\n');
        else value = efb_mask_display_efb(el, value); // F01: canonical +98912... back to 0912 ...
        if (value === '' || el.value === value) continue;
        el.value = value;
      } else if (tag === 'INPUT' && (type === 'radio' || type === 'checkbox')) {
        if (el.checked) continue;
        el.checked = true;
      } else if (tag === 'SELECT' && !el.multiple) {
        if (value === '' || el.value === value || !Array.prototype.some.call(el.options, o => o.value === value)) continue;
        el.value = value;
      } else {
        skipped.push(row.id_);
        continue;
      }
      try { await handle_change_event_efb_v4(el, fid); } catch (e) {}
  }
  return skipped;
}
function efb_get_error_panel_efb() {
  return window.EFB_ERROR_PANEL || (typeof EFB_ERROR_PANEL !== 'undefined' ? EFB_ERROR_PANEL : null);
}
function efb_show_submit_ajax_badge_efb(form_id) {
  const panel = efb_get_error_panel_efb();
  if (panel && typeof panel.showInlineBadge === 'function') {
    panel.showInlineBadge(form_id);
  }
}
function efb_hide_submit_ajax_badge_efb(form_id) {
  const panel = efb_get_error_panel_efb();
  if (panel && typeof panel.clearSubmissionBadge === 'function') {
    panel.clearSubmissionBadge(form_id);
  }
}
/* F08: the tags of the thank-you texts: the server's values (res.data.st, resolved from the stored answers),
 * else this page's; written with textContent only. */
function efb_smart_fill_done_efb(box, form_id, res) {
  if (!box || !window.EFBSmart || typeof window.EFBSmart.fillMessage !== 'function') return;
  try { window.EFBSmart.fillMessage(box, Number(form_id) || 0, res && res.data && res.data.st, { track: res && res.data ? res.data.track : '' }); } catch (e) {}
}
/* An address for href="…": http(s) only, quotes and angle brackets encoded. */
function efb_attr_url_efb(u) {
  u = String(u == null ? '' : u);
  if (!/^https?:\/\//i.test(u)) return '#';
  return u.replace(/&/g, '&amp;').replace(/"/g, '%22').replace(/</g, '%3C').replace(/>/g, '%3E').replace(/'/g, '%27');
}
async function response_fill_form_efb(res ,form_id=0) {
  form_id = Number(form_id);
  /* Some flows (e.g. password recovery) temporarily replace sendBack_emsFormBuilder_pub
     with a plain object before submitting — restore array form so the helpers below
     (and any later array operations) keep working. */
  if (!Array.isArray(sendBack_emsFormBuilder_pub)) sendBack_emsFormBuilder_pub = [];
  if (res.data.success == true) {
    efb_hide_submit_ajax_badge_efb(form_id);
    /* Final submit succeeded — now it is safe to drop this form's stored values.
       Other forms on the same page keep their data untouched. */
    clear_sendback_rows_by_form_id_efb(form_id);
  } else if (res.data && res.data.field_id) {
    /* Validation failed for one specific field — remove only that field's rows
       (by id_, or by id_ob for chlCheckBox-style fields) so the rest of the
       already-filled-in form data survives and doesn't have to be re-entered. */
    // F02: a verification refusal keeps the answer (the value is fine; it needs a code again).
    if (!(typeof res.data.code === 'string' && res.data.code.indexOf('efb_verify_') === 0)) remove_sendback_rows_by_field_id_efb(res.data.field_id, form_id);
    // F03: a field rule refused it on the server (e.g. a value already used): the field shows why.
    if (typeof res.data.code === 'string' && res.data.code.indexOf('efb_field_') === 0) efb_rules_server_error_efb(form_id, res.data.field_id, res.data.m);
    // F02: the field's code block goes back to "Send code" with the server's sentence.
    if (typeof res.data.code === 'string' && res.data.code.indexOf('efb_verify_') === 0 && window.EFBVerify) window.EFBVerify.serverError(form_id, res.data.field_id, res.data.code, res.data.m);
    // F05: a choice the server refused (Other without text, too few / many, a choice full meanwhile, a ranking):
    // the field's choices are cleared (their rows were dropped above), a full choice is marked, the field says why.
    if (typeof res.data.code === 'string' && res.data.code.indexOf('efb_choice_') === 0 && window.EfbChoices && typeof window.EfbChoices.serverError === 'function') {
      try { window.EfbChoices.serverError(form_id, res.data.field_id, res.data.code, res.data.m, res.data.option_id); } catch (e) { /* the sentence is on the final step too */ }
    }
    // F06: a date the server refused (a rule, or the place was taken meanwhile): the picker is emptied
    // (its row was dropped above) and shows the server's sentence, so Previous leads to a field to fix.
    if (typeof res.data.code === 'string' && res.data.code.indexOf('efb_date_') === 0 && window.EFBCalendar && typeof EFBCalendar.get === 'function') {
      const cf = EFBCalendar.get(res.data.field_id, form_id);
      if (cf) {
        let text = typeof res.data.m === 'string' ? res.data.m : '';
        try { text = new DOMParser().parseFromString(text, 'text/html').body.textContent || ''; } catch (e) { text = text.replace(/<[^>]*>/g, ''); }
        cf.clear({ silent: true });
        if (text) cf.showError(text);
      }
    }
  }
  let btn_prev ='';
  const t = valj_efb_new.find(x => x.id == form_id);
  const valj_efb = t.form_structer;
  const stps = t.form_structer && t.form_structer.hasOwnProperty('steps')  ? Number(valj_efb[0].steps) : -123;
  const id_body = 'body_efb_'+form_id;
  const body_efb = document.getElementById(id_body);
  const efb_final_step = efb_get_final_step_efb(form_id, body_efb);
  /* A logged-in visitor on a login/register form gets the server-rendered
     profile card instead of the form, and that card has no #efb-final-step.
     The guard below therefore returned before the switch could reach
     case "logout", leaving the card stuck on its loading message with the
     user already signed out on the server. Answer the logout reply here,
     where body_efb (the card's own container) is all that is on screen. */
  if ((t && t.type === 'logout') || (res.data && res.data.m === 'logout')) {
    if (res.data.success == true) {
      location.reload();
      return;
    }
    const logoutTarget = efb_final_step || body_efb;
    if (logoutTarget) {
      const logoutError = (res.data && typeof res.data.m === 'string' && res.data.m !== 'logout') ? res.data.m : ajax_object_efm.text.error;
      logoutTarget.innerHTML = `<div class="efb text-center my-3"><i class="efb nmsgefb bi-exclamation-triangle-fill efb fs-3"></i><h3 class="efb fs-3 text-muted">${ajax_object_efm.text.error}</h3><span class="efb mb-2 fs-5 text-muted">${logoutError}</span></div>`;
    }
    return;
  }
  if (!body_efb || !efb_final_step) {
    console.error('[EFB] The final form step could not be found for form ' + form_id + '.');
    return;
  }
  const isSubmitAjaxError = res.data && res.data.efb_ajax_submission_error === true;
  if (valj_efb.length > 1) {
    /* If the server rejected one specific field, send Previous straight back
     * to THAT field's step — not "one step back from dataset.currentstep",
     * which by this point may already be past the last real step (the
     * step-advance code sets it before the AJAX call even runs) and would
     * make fun_prev_send() throw trying to hide a fieldset that doesn't
     * exist. */
    const _erroredField = (res.data && res.data.field_id)
      ? valj_efb.find((f) => f && f.id_ === res.data.field_id)
      : null;
    const _backStep = (_erroredField && _erroredField.step != null) ? Number(_erroredField.step) : (Number(stps) || 1);
    btn_prev = `efb_go_to_step_direct(${form_id}, ${_backStep})`;
  }
  if (res.data.success == true) {
    // Register and login answer with their message in m and the address in
    // redirect_url; their own cases below redirect, m is not a URL for them.
    if(valj_efb.length>0 && valj_efb[0].hasOwnProperty('thank_you')==true && valj_efb[0].thank_you=='rdrct' && typeof res.data.m === 'string' && t.type != 'register' && t.type != 'login'){
      efb_final_step.innerHTML = `
      <h3 class="efb fs-4 text-center">${efb_var.text.sentSuccessfully}</h3>
      <h3 class="efb  text-center">${efb_var.text.pWRedirect} <br><a class="efb text-darkb" href="${efb_attr_url_efb(res.data.m)}">${efb_var.text.orClickHere}</a></h3>
      `
      setTimeout(function() {
        window.location.href = res.data.m;
      }, 3700);
      return ;
    }
    switch (t.type) {
      case 'form':
      case 'payment':
        if (res.data.conditional_redirect === true && typeof res.data.m === 'string') {
          efb_final_step.innerHTML = `
          <h3 class="efb fs-4 text-center">${efb_var.text.sentSuccessfully}</h3>
          <h3 class="efb  text-center">${efb_var.text.pWRedirect} <a class="efb text-darkb" href="${efb_attr_url_efb(res.data.m)}">${efb_var.text.orClickHere}</a></h3>
          `
          window.location.href = res.data.m;
          break;
        }
        efb_final_step.innerHTML = funTnxEfb(res.data.track, '', res.data.conditional_message || '', res.data.conditional_confirmation || null)
        efb_smart_fill_done_efb(efb_final_step, form_id, res);
        break;
      case 'survey':
        if(valj_efb[0].hasOwnProperty('thank_you') && valj_efb[0].thank_you=='rdrct' && typeof res.data.m === 'string'  ){
          efb_final_step.innerHTML = `
          <h3 class="efb fs-4 text-center">${efb_var.text.sentSuccessfully}</h3>
          <h3 class="efb  text-center">${efb_var.text.pWRedirect} <a class="efb text-darkb" href="${efb_attr_url_efb(res.data.m)}">${efb_var.text.orClickHere}</a></h3>
          `
          window.location.href = res.data.m;
          break;
        }
        // Use custom thank_you_message if set, otherwise use server response
        const surveyThankYou = valj_efb[0].thank_you_message && valj_efb[0].thank_you_message.thankYou ? valj_efb[0].thank_you_message.thankYou : res.data.m;
        efb_final_step.innerHTML = funTnxEfb('','', surveyThankYou);
        efb_smart_fill_done_efb(efb_final_step, form_id, res);
        if (res.data.survey_chart_type && res.data.survey_chart_type !== 'none' && res.data.survey_results && res.data.survey_results.length > 0) {
          if (typeof renderSurveyResultsChart === 'function') {
            renderSurveyResultsChart(res.data, efb_final_step, form_id);
          } else {
          }
        } else {
        }
        break;
      case 'subscribe':
        // Use thank_you_message values for subscribe success
        const subscribeThankYou = valj_efb[0].thank_you_message && valj_efb[0].thank_you_message.thankYou ? valj_efb[0].thank_you_message.thankYou : ajax_object_efm.text.YouSubscribed;
        efb_final_step.innerHTML = funTnxEfb('','', subscribeThankYou);
        efb_smart_fill_done_efb(efb_final_step, form_id, res);
        break;
      case 'register':
          // Use thank_you_message values for register success
          const registerThankYou = valj_efb[0].thank_you_message && valj_efb[0].thank_you_message.thankYou ? valj_efb[0].thank_you_message.thankYou : (res.data.m || efb_var.text.createAcountDoneM);
          if(res.data.hasOwnProperty('redirect_url') && typeof res.data.redirect_url === 'string' && res.data.redirect_url.length > 5 && res.data.redirect_url !== 'null'){
            efb_final_step.innerHTML = `
            <h3 class="efb fs-4 text-center">${registerThankYou}</h3>
            <h3 class="efb  text-center">${efb_var.text.pWRedirect} <a class="efb text-darkb" href="${efb_attr_url_efb(res.data.redirect_url)}">${efb_var.text.orClickHere}</a></h3>
            `
            window.location.href = res.data.redirect_url;
          }else{
            const m = form_type_emsFormBuilder !='recovery' ? registerThankYou : ajax_object_efm.text.checkYourEmail;
            efb_final_step.innerHTML = funTnxEfb('','',m );
            efb_smart_fill_done_efb(efb_final_step, form_id, res);
          }
          break;
      case 'recovery':
        efb_final_step.innerHTML = `<div class="efb efb-recovery-success-card"><div class="efb efb-recovery-success-icon"><i class="efb bi-envelope"></i></div><p class="efb" style="color:var(--efb-resp-text);font-family:var(--efb-resp-font-family);font-size:var(--efb-resp-font-size);margin:0;">${res.data.m}</p></div>`;
      break;
      case 'login':

        if (res.data.m && typeof res.data.m === 'object' && res.data.m.state == true) {
          if(res.data.m.hasOwnProperty('redirect_url') && res.data.m.redirect_url.length>5){
            efb_final_step.innerHTML = `
            <h3 class="efb  text-center">${efb_var.text.pWRedirect} <a class="efb text-darkb" href="${efb_attr_url_efb(res.data.m.redirect_url)}">${efb_var.text.orClickHere}</a></h3>
            `
            window.location.href = res.data.m.redirect_url;

          }else{
            document.getElementById('body_efb_'+form_id).innerHTML = show_user_profile_emsFormBuilder(res.data.m, form_id);
            location.reload();
          }
        } else if(typeof res.data.m === 'string' && res.data.success == true){
          document.getElementById('body_efb_'+form_id).innerHTML = `
          <div class="efb efb-recovery-success-card">
              <div class="efb efb-recovery-success-icon"><i class="efb bi-envelope-check"></i></div>
              <p class="efb efb-login-error-msg" style="color:var(--efb-resp-text)">${res.data.m}</p>
          </div>
          `
        } else {
          const errorMsg = res.data.m && res.data.m.error ? res.data.m.error : (res.data.m || ajax_object_efm.text.error);
          efb_final_step.innerHTML = `
            <div class="efb" style="display:flex;flex-direction:column;align-items:center;text-align:center;font-family:var(--efb-resp-font-family);font-size:var(--efb-resp-font-size);">
              <!-- Error Message -->
              <div id="alertFinalStepEFB" class="efb" style="display:flex;flex-direction:column;align-items:center;margin-bottom:16px;">
                <h5 class="efb efb-login-error-title">${ajax_object_efm.text.error}</h5>
                <p class="efb efb-login-error-msg">${errorMsg}</p>
              </div>

              <!-- Recovery Link -->
              <button id="btn_Show_recovery_efb" type="button"
                class="efb efb-recovery-toggle-btn mb-2"
                onclick="Show_recovery_pass_efb()">
                <span>${ajax_object_efm.text.passwordRecovery}</span>
                <i id="icon_btn_Show_recovery_efb" class="efb bi-chevron-down"></i>
              </button>

              <!-- Recovery Form Section -->
              <div class="efb efb-recovery-section d-none" id="recoverySectionemsFormBuilder" style="text-align:center;">
                <div class="efb efb-recovery-section-icon">
                  <i class="efb bi-envelope"></i>
                </div>
                <p class="efb efb-recovery-section-text">${ajax_object_efm.text.servpss}</p>
                <input type="email" id="username_recovery_pass_efb"
                  class="efb efb-recovery-input mb-2"
                  placeholder="${ajax_object_efm.text.email || 'Email'}"
                  autocomplete="email">
                <button id="btn_recovery_pass_efb"
                  class="efb efb-recovery-send-btn"
                  data-id="1" disabled>
                  <i class="efb bi-send"></i>${ajax_object_efm.text.send}
                </button>
              </div>

              <!-- Back Button -->
              ${efb_error_prev_button_efb(valj_efb, `fun_prev_send(${form_id})`)}
            </div>`;
        }
        break;
      case "logout":
        location.reload();
        break;
    }

      if(stps>1 ){smoothy_scroll_postion_efb(id_body)}
  } else {
    if(efb_final_step){efb_final_step.innerHTML = `<h3 class='efb emsFormBuilder text-center'><i class="efb nmsgefb bi-exclamation-triangle-fill text-center efb fs-3  text-center"></i></h1><h3 class="efb  text-center fs-3 text-muted">${ajax_object_efm.text.error}</h3> <span class="efb mb-2 efb fs-5"> ${res.data.m}</span>
    ${isSubmitAjaxError ? `<div class="efb efb-submit-error-badge-slot my-2 d-flex justify-content-center" id="efb-submit-error-badge-slot-${form_id}"></div>` : ``}
    <div class="efb m-1">${efb_error_prev_button_efb(valj_efb, `efb_hide_submit_ajax_badge_efb(${form_id}); ${btn_prev}`)}</div></div>`;
      if (isSubmitAjaxError) { efb_show_submit_ajax_badge_efb(form_id); }
    }else{
      alert_message_efb(res.data.m,'',14,'warning');
    }
  }
}
  function loadCaptcha_efb(timer = 20) {
  let sitekye_emsFormBuilder = ajax_object_efm?.form_setting?.siteKey;
  if (!sitekye_emsFormBuilder) return;

  if (!window.grecaptcha || !window.grecaptcha.render) {
    setTimeout(() => {
      loadCaptcha_efb(timer + 100);
    }, timer);
  } else {
    document.querySelectorAll('.g-recaptcha').forEach(el => {
      if (el.dataset.grecaptchaWidgetId) return;
      const formId = el.dataset.formid ?? -1;
      const widgetId = grecaptcha.render(el, {
        sitekey: sitekye_emsFormBuilder,
        callback: (token) => recaptchaSuccessEfb(token, formId),
        'expired-callback': () => recaptchaExpiredEfb(formId),
        'error-callback': () => recaptchaErrorEfb?.(formId),
      });
      el.dataset.grecaptchaWidgetId = String(widgetId);
    });
  }
};

recaptchaExpiredEfb = (formId)=>{
  formId = formId==-1 ? -1 : Number(formId);
  const i = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ == 'captcha_v2' && x.form_id == formId);
  if(i!=-1) sendBack_emsFormBuilder_pub.splice(i,1);

  if(formId==0 || formId==-1){
    const bdy = document.getElementById('body_tracker_emsFormBuilder');
    if(bdy){
      const btn = bdy.querySelector('#vaid_check_emsFormBuilder');
      if(btn){
        btn.classList.add('disabled');
      }
    }
  }else{
    updateStepButtonState_efb(formId);
  }
}

recaptchaErrorEfb = (formId) =>{
}

recaptchaSuccessEfb = (token, formid) => {
  formid = formid==-1 ? -1 : Number(formid);
  fun_sendBack_emsFormBuilder({ id_: 'captcha_v2', name: 'recaptcha', value: token ,form_id:formid, type:'captcha_v2' });
  if(formid==0 || formid==-1){
    const bdy = document.getElementById('body_tracker_emsFormBuilder');
    if(bdy){
      const btn = bdy.querySelector('#vaid_check_emsFormBuilder');
      if(btn){
        btn.classList.remove('disabled');
      }
    }
  }else{
    updateStepButtonState_efb(formid);
  }
}
function calPLenEfb(len) {
  if (len <= 5) { return 40;}
  else if (len <= 10) { return 20; }
  else if (len <= 50) { return 15; }
  else if (len <= 100) { return 9; }
  else if (len <= 300) { return 3; }
  else if (len <= 600) { return 1.5; }
  else if (len <= 1000) { return 1.2; }
  else { return 1.1; }
}
fun_text_forbiden_convert_efb=(value)=>{
 value= value.replaceAll("'", "@efb@sq#");
 value= value.replaceAll("`", "@efb@vq#");
 value= value.replaceAll(`"`, "@efb@dq#");
 value= value.replaceAll(`\t`, " ");
 value= value.replaceAll(`\b`, " ");
 value= value.replaceAll(`\r`, "@efb@nq#");
 value= value.replaceAll(`\n`, "@efb@nq#");
 value= value.replaceAll(`\r`, " ");
 return value;
}
show_msg_efb=(el)=>{ if(el) el.style.display='block'; }
hide_msg_efb=(el)=>{ if(el){ el.style.display='none'; el.innerHTML=''; } }
remove_ttmsg_efb=(id)=>{
  const m=document.getElementById(`${id}_-message`);
  hide_msg_efb(m);
}
change_url_back_persia_pay_efb=()=>{
  const indx = document.URL.indexOf('?');
  if(indx!=-1)history.pushState({'page_id': 1},`${document.title} !`, document.URL.slice(0,indx));
}
window.addEventListener("popstate",e=>{
  // A #anchor link (the theme's skip link, say) pops a null or object state.
  if (typeof e.state !== 'string' || e.state.search('EFBstep-') ==-1) return
    Number(e.state.slice(8)) <= Number(current_s_efb)  ? prev_btn_efb() :jQuery("#next_efb").trigger('click');
 })
 /* F08-D: the old iefb / sefb / defb / hefb / vefb parameters are gone (they never ran on a rendered form,
    indexed the wrong row and could enable a disabled field). URL prefill is now EFBSmart.applyPrefill(): only
    fields whose owner gave them a prefill_key (smart-tags-efb.js). */
 /* F05: a booking choice the visitor picks: closed by its date (ISO, the visitor's today) or with no place left (the
    numbers forms/session/prepare gave, else the form's). The sentences are the phrases bkXpM_ / bkFlM_. Only a choice is
    checked: a text field of a booking form is not a choice (it used to answer "Row not Found!" and drop the answer). */
 fun_booking_avilable =(el)=>{
  let r =[true,''];
  let id = el.id;
  if(el.type=='select' || el.type=='select-one'){
    const o = el.options[el.selectedIndex];
    id = o && o.dataset ? o.dataset.id : '';
  }
  const form_id = el.dataset && el.dataset.formid ? el.dataset.formid : 0;
  let rows = valj_efb;
  try { if (form_id && typeof get_structure_by_form_id_efb === 'function') rows = get_structure_by_form_id_efb(form_id) || valj_efb; } catch (e) {}
  const row = rows.find(x=>x && x.id_==id);
  if(!row || row.type !== 'option') return r;
  const uncheck =()=>{ if(el.type=="radio" || el.type=="checkbox") el.checked=false; };
  const t = (k, fb) => (efb_var && efb_var.text && efb_var.text[k] ? efb_var.text[k] : fb);
  const label = String(row.value || '');
  const d = new Date();
  const today = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  if (row.hasOwnProperty('dateExp') && /^\d{4}-\d{2}-\d{2}$/.test(String(row.dateExp)) && String(row.dateExp) < today) {
    uncheck();
    return [false, t('bkXpM_', 'We are sorry, the booking time for the %s option has expired. Please choose from the other available options.').replace('%s', '\u2068' + label + '\u2069')];
  }
  const stock = window.EfbChoices && window.EfbChoices._state.stock[String(form_id)];
  const left = stock && Object.prototype.hasOwnProperty.call(stock, row.id_) ? Number(stock[row.id_])
    : (row.hasOwnProperty('mlen') && /^\d+$/.test(String(row.mlen)) ? Number(row.mlen) - Number(row.registered_count || 0) : Infinity);
  if (left <= 0) {
    uncheck();
    return [false, t('bkFlM_', 'We are sorry, the %s option is currently at full capacity. Please choose from the other available options.').replace('%s', '\u2068' + label + '\u2069')];
  }
  return r;
 }
efb_refresh_nonce=async(sid, fid)=>{
  try {
    // The sid is sent so the server can slide a still-open session forward, but
    // it is no longer required: on a page-cached site the sid is frozen into the
    // stored HTML together with the nonce and has expired by the time a refresh
    // is needed, which is exactly when this has to work.
    const session_id = sid || (typeof efb_var !== 'undefined' && efb_var.sid) || '';
    const headers = {};
    if (session_id) headers['sid'] = session_id;
    if (fid) headers['form-id'] = fid;
    const r = await fetch(efb_var.rest_url+'Emsfb/v1/nonce/refresh',{method:'GET',credentials:'same-origin',cache:'no-store',headers:headers});
    if(r.ok){
      const d = await r.json();
      if(d && d.nonce){
        try { efb_var.nonce = d.nonce; } catch (e) {}
        // Share the fresh token with the Human Shield client, which reads its
        // own copy and would otherwise keep replaying the dead one.
        try { if (window.EFBHumanShield) window.EFBHumanShield.wpRestNonce = d.nonce; } catch(e){}
        return d.nonce;
      }
    }
  } catch(e){}
  return false;
}
function efb_report_submit_ajax_error_efb(error, details = {}) {
  if (window.EFBRequestHealth) window.EFBRequestHealth.report('form', details.status || 0);
  const panel = efb_get_error_panel_efb(); // administrators only (see check_error_console_efb)
  if (!panel || typeof panel.log !== 'function') return;
  const f = Object.assign({ cause: 'rejected', status: details.status || 0, code: '', serverMessage: '', visitorText: error && error.message ? error.message : '' }, details.failure || {});
  f.at = Date.now();
  try { f.timeLabel = new Date(f.at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); } catch (e) { f.timeLabel = ''; }
  f.formId = details.formId || details.form_id || null;
  f.page = location.pathname;
  const parseList = (v) => { try { const l = typeof v === 'string' ? JSON.parse(v) : v; return Array.isArray(l) ? l : []; } catch (e) { return []; } };
  /* The cache is named only with evidence: an expired token or context that
     came from the page itself (no live runtime context), a page cache plugin
     that is active, and a page that did not tell the caches to skip it. */
  const pageCaches = parseList(ajax_object_efm && ajax_object_efm.cache_plugins);
  if ((f.cause === 'stale_token' || f.cause === 'stale_context') && f.legacy && pageCaches.length && !(ajax_object_efm && Number(ajax_object_efm.cache_bypassed) === 1)) {
    f.cachePlugin = pageCaches[0] && pageCaches[0].name ? pageCaches[0].name : '';
  }
  f.visitorText = efb_plain_text_efb(f.visitorText);
  f.serverMessage = efb_plain_text_efb(f.serverMessage);
  if (f.cause === 'blocked') {
    if (f.shape === 'challenge') {
      f.challenge = true; // Cloudflare answered with a challenge page (cf-mitigated)
    } else {
      // Only plugins that run a firewall can refuse a request with 403.
      const firewalls = ['wordfence', 'better-wp-security', 'all-in-one-wp-security-and-firewall', 'shield-security', 'sucuri-scanner', 'bbq-firewall', 'wp-cerber', 'bulletproof-security'];
      const sec = parseList(ajax_object_efm && ajax_object_efm.security_plugins).filter(p => p && p.name && firewalls.includes(String(p.slug || '')));
      if (sec.length) f.securityPlugin = sec[0].name;
    }
  }
  panel.log(f.visitorText || 'Form submission failed', {
    source: 'efb-submit',
    type: 'plugin',
    name: 'Easy Form Builder',
    captureStack: false,
    showBadge: true,
    context: 'submissionAjax',
    formId: f.formId,
    failure: f
  });
}
// Only explicit token errors are safe to replay. A generic 403 may be a security veto.
async function efb_is_nonce_error_efb(response) {
  if (response.status !== 403) return false;
  try {
    const data = await response.clone().json();
    return ['rest_cookie_invalid_nonce', 'efb_nonce_expired', 'efb_nonce_missing'].includes(data.code);
  } catch (e) { return false; }
}
// The message an EFB endpoint (or an add-on such as Human Shield) put in a refusal:
// {success:false, data:{m:'...'}}. Empty when the body is not that shape.
async function efb_response_message_efb(response) {
  try {
    const data = await response.clone().json();
    const m = data && data.data && data.data.m;
    return typeof m === 'string' && m.trim() ? m : '';
  } catch (e) { return ''; }
}
/* Phrases from text_efb() arrive HTML-escaped (esc_html__), which is right for
 * innerHTML but shows "&#039;" when a phrase is used as plain text. */
function efb_plain_text_efb(s) {
  try { const ta = document.createElement('textarea'); ta.innerHTML = String(s == null ? '' : s); return ta.value; } catch (e) { return String(s == null ? '' : s); }
}
/* What a refused response says about itself: HTTP status, the EFB or WordPress
 * error code, an EFB message written for visitors (data.m) and the body's shape.
 * Never the raw body: a firewall page or a stack trace is not for visitors. */
async function efb_response_info_efb(response) {
  const info = { status: response ? response.status : 0, code: '', m: '', shape: 'none' };
  if (!response) return info;
  try { if (response.headers && response.headers.get('cf-mitigated')) { info.shape = 'challenge'; return info; } } catch (e) {}
  let d = null;
  try { d = await response.clone().json(); } catch (e) {
    try { info.shape = /html/i.test(response.headers.get('Content-Type') || '') ? 'html' : 'none'; } catch (e2) {}
    return info;
  }
  if (d && d.data && typeof d.data === 'object') {
    if (typeof d.data.m === 'string' && d.data.m.trim()) { info.m = d.data.m; info.shape = 'efb'; }
    if (typeof d.data.code === 'string') info.code = d.data.code;
  }
  if (!info.code && d && typeof d.code === 'string') { info.code = d.code; if (info.shape === 'none') info.shape = 'wp'; }
  return info;
}
/* The cause of a failed public request, from what the response proves. A cache
 * plugin is never the cause here: in runtime mode the nonce and session come
 * live from forms/session/prepare, not from the (possibly cached) page. */
function efb_failure_cause_efb(info) {
  const s = info ? Number(info.status) || 0 : 0, c = info ? String(info.code || '') : '';
  if (!s) return 'unknown_outcome';
  // F11: the form gate (closed, not open yet, full, per-person limit, role) says it in its own words.
  if (['efb_form_pending', 'efb_form_closed', 'efb_form_full', 'efb_limit_reached', 'efb_role_denied'].includes(c)) return 'form_gate';
  // The X-WP-Nonce header never arrived: a proxy, firewall or security plugin removed it.
  if (c === 'efb_nonce_missing') return 'blocked';
  if (['rest_cookie_invalid_nonce', 'efb_nonce_expired'].includes(c)) return 'stale_token';
  if (c === 'efb_context_expired') return 'stale_context';
  if (c === 'efb_identity_changed') return 'identity_changed';
  if (/human_shield/.test(c)) return 'server_refusal';
  if (s >= 500) {
    if (/^efb_.*(storage|save|insert)/.test(c) || (info.shape === 'efb' && !c)) return 'server_failed';
    if (info.shape === 'efb' || /^efb_/.test(c)) return 'server_refusal';
    return 'server_error'; // a PHP fatal or a proxy error page: the outcome is unknown
  }
  if (info.shape === 'efb') return 'server_refusal';
  if (s === 401 || ['rest_not_logged_in', 'rest_forbidden', 'rest_disabled', 'rest_cannot_access', 'rest_login_required'].includes(c)) return 'closed';
  if (s === 429) return 'rate_limited';
  if (s === 413) return 'too_large';
  if (s === 403) return 'blocked';
  return 'rejected';
}
/* One or two plain sentences for the visitor: what happened and the one thing to do. */
function efb_visitor_text_efb(cause, info) {
  const t = (typeof ajax_object_efm !== 'undefined' && ajax_object_efm && ajax_object_efm.text) ? ajax_object_efm.text : {};
  const status = info && info.status ? Number(info.status) : 0;
  const withCode = (str) => String(str).replace('%d', String(status));
  switch (cause) {
    case 'stale_token': return t.nonceExpired || 'Your session has expired. Please refresh the page and try again.';
    case 'stale_context': return t.formExpiredSendAgain || 'The form expired while this page was open. It has been renewed, so please send it again. Your answers are kept.';
    case 'identity_changed': return t.loginChangedSendAgain || 'You signed in or out in another tab. Please send the form again.';
    case 'form_gate':
    case 'server_refusal':
    case 'server_failed': if (info && info.m) return info.m; break;
    case 'closed': return t.formsClosed || 'This website is not accepting forms from visitors right now. Please contact the site owner.';
    case 'rate_limited': return (info && info.m) || t.tooManyRequests || 'Too many requests. Please try again shortly.';
    case 'too_large': return t.formTooLarge || 'The form is too large to send, usually because of an attached file or a signature. Use a smaller file and try again.';
    case 'blocked': return t.requestBlocked || "The website's security settings blocked this form. Please try again in a few minutes. If it happens again, contact the site owner.";
    case 'server_error':
    case 'bad_answer':
    case 'unknown_outcome': return t.noAnswerCheckFirst || 'The connection was interrupted, so we could not confirm that your form arrived. If you did not receive a confirmation, please send it again.';
  }
  return withCode(t.formNotSentCode || 'The form could not be sent (error %d). Your answers are kept. Please try again later.');
}
/* Same causes for the confirmation-code lookup and the reply box, which are
 * requests rather than forms: no answer means "try again", not "check first". */
function efb_request_text_efb(cause, info) {
  const t = (typeof ajax_object_efm !== 'undefined' && ajax_object_efm && ajax_object_efm.text) ? ajax_object_efm.text : {};
  if (cause === 'unknown_outcome') return t.noAnswerRetry || 'No answer from the website. Please check your connection and try again.';
  // The server's own text already says to refresh; these requests are not re-prepared in place.
  if ((cause === 'stale_context' || cause === 'identity_changed') && info && info.m) return info.m;
  if (cause === 'stale_context' || cause === 'identity_changed') return t.nonceExpired || 'Your session has expired. Please refresh the page and try again.';
  if (cause === 'rejected' || ((cause === 'server_refusal' || cause === 'server_failed') && !(info && info.m))) {
    return String(t.requestFailedCode || 'Your request could not be completed (error %d). Please try again later.').replace('%d', String(info && info.status ? info.status : 0));
  }
  return efb_visitor_text_efb(cause, info);
}
/* Turns a refused response into an Error whose message is already the visitor text. */
async function efb_request_error_efb(response) {
  const info = await efb_response_info_efb(response);
  const cause = efb_failure_cause_efb(info);
  if ((cause === 'stale_context' || cause === 'identity_changed') && window.EFBRuntime && typeof window.EFBRuntime.reset === 'function') window.EFBRuntime.reset(0);
  const error = new Error(efb_request_text_efb(cause, info));
  error.status = response.status;
  error.code = info.code;
  error.efb_visitor_text = true;
  return error;
}
async function efb_fetch_public_request_efb(url, options, operation, sid, fid) {
  try {
    options=Object.assign({},options,{cache:'no-store',credentials:'same-origin',signal:typeof AbortSignal!=='undefined' && AbortSignal.timeout ? AbortSignal.timeout(45000) : undefined});
    if (window.EFBRuntime) {
      const runtime=await window.EFBRuntime.prepare(Number(fid)||0);
      const headers=new Headers(options.headers); headers.set('X-WP-Nonce',runtime.nonce); headers.set('sid',runtime.sid);
      options=Object.assign({},options,{headers});
    }
    let response = await fetch(url, options);
    if (await efb_is_nonce_error_efb(response)) {
      const fresh = await efb_refresh_nonce(sid, fid);
      if (fresh) {
        const headers = new Headers(options.headers);
        headers.set('X-WP-Nonce', fresh);
        response = await fetch(url, Object.assign({}, options, { headers }));
      }
    }
    if (!response.ok && window.EFBRequestHealth) window.EFBRequestHealth.report(operation, response.status);
    return response;
  } catch (error) {
    if (window.EFBRequestHealth) window.EFBRequestHealth.report(operation, error.status || 0);
    /* A refusal from forms/session/prepare (expired context, login changed, login
       required) carries a code: say that, not "no answer from the website", and
       drop the refused context so a reload is not the only way out. */
    if (error && error.code && !error.efb_visitor_text) {
      const info = { status: error.status || 0, code: error.code, m: error.message || '', shape: 'efb' };
      const cause = efb_failure_cause_efb(info);
      if ((cause === 'stale_context' || cause === 'identity_changed') && window.EFBRuntime && typeof window.EFBRuntime.reset === 'function') window.EFBRuntime.reset(Number(fid) || 0);
      error.message = efb_request_text_efb(cause, info);
      error.efb_visitor_text = true;
    }
    throw error;
  }
}
 post_api_forms_efb=async(data,form_id)=>{
    let runtime;
    // F11-E: the saved answers this submission completes (emptied on the server once it is stored).
    try { const dt = sessionStorage.getItem('efb_draft_' + Number(form_id)); if (dt && /^[a-f0-9]{64}$/.test(dt)) data.draft_token = dt; } catch (e) {}
    try {
      if (window.EFBRuntime) runtime=await window.EFBRuntime.decorate(data,Number(form_id)||0,'form');
    } catch (error) {
      // prepare refused the form context itself (expired, login changed, login required...).
      const info={status:error.status||0,code:error.code||'',m:error.message||'',shape:'efb'};
      const cause=efb_failure_cause_efb(info);
      if ((cause==='stale_context' || cause==='identity_changed') && window.EFBRuntime && typeof window.EFBRuntime.reset==='function') window.EFBRuntime.reset(Number(form_id)||0);
      const visitorText=efb_visitor_text_efb(cause,info);
      efb_report_submit_ajax_error_efb(new Error(visitorText),{status:error.status||0,formId:form_id,failure:{cause,status:error.status||0,code:info.code,serverMessage:info.m,visitorText,step:'prepare'}});
      await response_fill_form_efb({success:false,data:{success:false,m:visitorText,efb_ajax_submission_error:true}},form_id);
      return;
    }
    const url = efb_var.rest_url+'Emsfb/v1/forms/message/add';
    const efbForce403Test = false; // Set to true to force a 403 response for testing nonce refresh flow
    const headers = new Headers({
     'Content-Type': 'application/json',
     'X-WP-Nonce': efbForce403Test ? 'efb-force-403-test' : (runtime ? runtime.nonce : efb_var.nonce),
     'form-id': form_id ? form_id : 0,
     'sid': efbForce403Test ? 'efb-force-403-test' : (data.sid ? data.sid : ''),
    });

    const jsonData = JSON.stringify(data);

    const requestOptions = {
      method: 'POST',
      headers,
      body: jsonData,
      cache: 'no-store', credentials: 'same-origin',
      signal: typeof AbortSignal!=='undefined' && AbortSignal.timeout ? AbortSignal.timeout(45000) : undefined,
    };

  let refreshed = false;
  try {
    let response = await fetch(url, requestOptions);
    if (await efb_is_nonce_error_efb(response)) {
      refreshed = await efb_refresh_nonce(data && data.sid ? data.sid : '', form_id);

      if (refreshed) {
        const retryHeaders = new Headers({
          'Content-Type': 'application/json',
          'X-WP-Nonce': efbForce403Test ? 'efb-force-403-test' : refreshed,
          'form-id': form_id ? form_id : 0,
          'sid': efbForce403Test ? 'efb-force-403-test' : (data.sid ? data.sid : ''),
        });
        response = await fetch(url, Object.assign({},requestOptions,{headers:retryHeaders}));
      }
    }
    if (!response.ok) {
      const info = await efb_response_info_efb(response);
      let cause = efb_failure_cause_efb(info);
      // A fresh nonce was issued and still refused: something on the way strips the header or the cookies.
      if (cause === 'stale_token' && refreshed) cause = 'blocked';
      if (cause === 'unknown_outcome' || cause === 'server_error') {
        // No EFB answer (a 5xx page, a proxy timeout): the entry may or may not
        // be saved, so the catch below asks the server before saying anything.
        const requestError = new Error(`HTTP ${response.status}`);
        requestError.status = response.status;
        throw requestError;
      }
      if ((cause === 'stale_context' || cause === 'identity_changed') && window.EFBRuntime && typeof window.EFBRuntime.reset === 'function') {
        // Without this the next "send" reused the refused context for 4 minutes.
        window.EFBRuntime.reset(Number(form_id) || 0);
      }
      const visitorText = efb_visitor_text_efb(cause, info);
      efb_report_submit_ajax_error_efb(new Error(visitorText), { status: response.status, url, formId: form_id, failure: { cause, status: response.status, code: info.code, serverMessage: info.m, visitorText, shape: info.shape, legacy: !runtime || !!runtime.legacy } });
      await response_fill_form_efb({ success: false, data: { success: false, m: visitorText, efb_ajax_submission_error: true } }, form_id);
      return;
    }
    let responseData;
    try { responseData = await response.json(); } catch (parseError) {
      // A 2xx whose body is not JSON: PHP notices or another plugin printed before the answer.
      const unreadable = new Error('Unreadable answer (HTTP ' + response.status + ')');
      unreadable.status = response.status; unreadable.efb_bad_answer = true;
      throw unreadable;
    }
	if (window.EFBRuntime && responseData?.data?.success === true) window.EFBRuntime.complete(Number(form_id)||0,'form');
    if (responseData?.data?.success === true) { try { sessionStorage.removeItem('efb_draft_' + Number(form_id)); } catch (e) {} if (typeof efb_partial_stop_efb === 'function') efb_partial_stop_efb(form_id); }

    await response_fill_form_efb(responseData, form_id);
    if (localStorage.getItem('sendback')) localStorage.removeItem('sendback');
    try { localStorage.removeItem('sendback_t'); } catch (e) {}
  } catch (error) {
    if (runtime && data.type==='form') {
      const saved=await window.EFBRuntime.resolve(data,Number(form_id)||0,'form');
      if (saved) {
        window.EFBRuntime.complete(Number(form_id)||0,'form');
        await response_fill_form_efb(saved,form_id);
        return;
      }
    }
    /* No answer, a timeout, or a 5xx without an EFB message. "jQuery" was never
       the cause; say what is known: the outcome could not be confirmed. */
    const failureMessage=runtime && (!error.status || error.status>=500 || error.efb_bad_answer) && typeof efb_request_health!=='undefined' && efb_request_health.unknown_message
      ? efb_request_health.unknown_message
      : efb_visitor_text_efb('unknown_outcome',{status:error.status||0});
    const outcomeCause = error && error.efb_bad_answer ? 'bad_answer' : (error && error.status >= 500 ? 'server_error' : 'unknown_outcome');
    efb_report_submit_ajax_error_efb(error, { status: error.status || 0, url, formId: form_id, failure: { cause: outcomeCause, status: error.status || 0, code: '', serverMessage: '', visitorText: failureMessage, legacy: !runtime || !!runtime.legacy } });
    await response_fill_form_efb({ success: false, data: { success: false, m: failureMessage, efb_ajax_submission_error: true } }, form_id);
  }
  bdy = document.getElementById('body_efb_'+form_id);
  if(bdy){
    const steps = bdy.dataset.steps ? Number(bdy.dataset.steps) : 1;
    if(steps==1){ return; }
    const prev_efb = bdy.querySelector('#prev_efb');
    const next_efb = bdy.querySelector('next_efb');
    if(prev_efb && prev_efb.classList.contains('d-none')==false){prev_efb.classList.add('d-none')}
    if(next_efb && next_efb.classList.contains('d-none')==false){next_efb.classList.add('d-none')}
  }
}
post_api_tracker_check_efb=(data,innrBtn)=>{
  const url = efb_var.rest_url+'Emsfb/v1/forms/response/get';
  const headers = new Headers({
    'Content-Type': 'application/json',
    'X-WP-Nonce': efb_var.nonce,
    'form-id': data.id ? data.id : 0,
    'sid':efb_var.sid ? efb_var.sid : '',
  });
  const jsonData = JSON.stringify(data);
  const requestOptions = {
    method: 'POST',
    headers,
    body: jsonData,
  };
  efb_fetch_public_request_efb(url, requestOptions, 'tracker', efb_var.sid || '', data.id || 0)
  .then(async response => {
    if (!response.ok) {
      throw await efb_request_error_efb(response);
    }
    return response.json();
  })
  .then(responseData => {
    if (document.getElementById('vaid_check_emsFormBuilder')) {
      document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn;
      document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled');
    }
    /* The Response Box renderer reads this scope while it is being created,
       so store it before rendering. The token is issued by the server only
       after this tracking code successfully opened this ticket. */
    if (responseData && responseData.data && responseData.data.success === true) {
      efb_var.nonce_msg = responseData.data.nonce_msg;
      efb_var.msg_id = responseData.data.id;
      efb_var.response_upload_token = responseData.data.response_upload_token || '';
      if (window.EFBRuntime) window.EFBRuntime.rememberTicket(responseData.data);
    }
    if (!(responseData && responseData.data && responseData.data.success === true)) efb_reset_tracker_captcha_efb();
    response_Valid_tracker_efb(responseData);
  })
  .catch(error => {
    if (document.getElementById('vaid_check_emsFormBuilder')) {
      document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn;
      document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled');
    }
    efb_reset_tracker_captcha_efb();
    // Never a raw "Network response was not ok" or a JSON parse error.
    const trackerText = error && error.efb_visitor_text ? error.message : efb_request_text_efb('unknown_outcome', { status: 0 });
    response_Valid_tracker_efb({ success: false, data: { success: false, m: trackerText } });
  });
}
/* A reCAPTCHA token is good for one verification only, and the server now
   checks the one sent with each lookup. After a lookup that did not open a
   ticket, clear the widget and the stored token so the next try gets a
   fresh one instead of failing with a spent token. */
efb_reset_tracker_captcha_efb=()=>{
  const el = document.querySelector('.g-recaptcha[data-formid="-1"]');
  if (!el) return;
  try {
    if (window.grecaptcha && typeof window.grecaptcha.reset === 'function' && el.dataset.grecaptchaWidgetId !== undefined) {
      window.grecaptcha.reset(Number(el.dataset.grecaptchaWidgetId));
    }
  } catch (e) {}
  if (typeof recaptchaExpiredEfb === 'function') recaptchaExpiredEfb(-1);
}
post_api_r_message_efb=async(data,message)=>{
  let runtime;
  try {
    if (window.EFBRuntime) {
      runtime=await window.EFBRuntime.decorate(data,0,'reply');
    }
  } catch(error) {
    response_rMessage_id({success:false,data:{success:false,m:error.message}},message);
    return;
  }
  const url = efb_var.rest_url+'Emsfb/v1/forms/response/add';
  const headers = new Headers({
    'Content-Type': 'application/json',
    'X-WP-Nonce': runtime ? runtime.nonce : efb_var.nonce,
    'form-id': 0,
    'sid':data.sid ? data.sid : '',
    'page-id': efb_var.page_id ? efb_var.page_id : '',
  });
  const jsonData = JSON.stringify(data);
  const requestOptions = {
    method: 'POST',
    headers,
    body: jsonData,
  };
    efb_fetch_public_request_efb(url, requestOptions, 'reply', data.sid || '', 0)
    .then(async response => {
      if (!response.ok) {
        throw await efb_request_error_efb(response);
      }
      return response.json();
    })
    .then(responseData => {
      if (window.EFBRuntime && responseData?.data?.success===true) window.EFBRuntime.complete(0,'reply');
      response_rMessage_id(responseData, message);
    })
    .catch(async error => {
      if (window.EFBRequestHealth) window.EFBRequestHealth.report('reply',error.status||0);
      if (runtime) {
        const saved=await window.EFBRuntime.resolve(data,0,'reply');
        if (saved) { window.EFBRuntime.complete(0,'reply'); response_rMessage_id(saved,message); return; }
      }
      /* A reply failure belongs in the reply composer. Sending it through the
         tracker handler replaces the whole open conversation and leaves the
         reply button stuck in its loading state. */
      const replyText = error && error.efb_visitor_text ? error.message : efb_request_text_efb('unknown_outcome', { status: 0 });
      response_rMessage_id({ success: false, data: { success: false, m: replyText } }, message);
    });
}

document.addEventListener("DOMContentLoaded",async function() {
  // Store admin sc before cleaning URL, for later use in response submissions
  const _urlParams = new URLSearchParams(window.location.search);
  if (_urlParams.has('sc')) {
    sessionStorage.setItem('efb_sc', _urlParams.get('sc'));
  }
  // Clean admin-related params from URL
  if (_urlParams.has('sc') || _urlParams.has('user')) {
    _urlParams.delete('sc');
    _urlParams.delete('user');
    const _cleanUrl = _urlParams.toString()
      ? window.location.pathname + '?' + _urlParams.toString()
      : window.location.pathname;
    window.history.replaceState(null, '', _cleanUrl);
  }

  let elements = document.querySelectorAll('#body_efb');
  // Absent on a page that shows only the members-only lock card.
  const _efm_text = typeof ajax_object_efm !== 'undefined' && ajax_object_efm.text ? ajax_object_efm.text : {};
  const msg = `<h3 class="efb fs-5 text-center text-dark bg-warning m-3 p-3">${_efm_text.fetf || ''} <div class='efb mt-1 fs-6'> ${_efm_text.easyFormBuilder || ''}</div> </h3>`
  fun =()=>{
    for (let i = 1; i < elements.length; i++) {
      elements[i].innerHTML = msg;
    }
  }
  if( (document.getElementById('body_efb') && document.getElementById('body_tracker_emsFormBuilder'))){
   if(document.getElementById('body_tracker_emsFormBuilder')) document.getElementById('body_tracker_emsFormBuilder').innerHTML =msg;
   if(elements.length > 1) fun();
  }else if (elements.length > 1){
    fun();
  }

  fun_wait_form_load_efb =(state ,speed)=>{
    if(speed!='fast'){
      const body_efbs = document.querySelectorAll('.body_efb');
      body_efbs.forEach((body_efb) => {
        const form_id = body_efb.dataset.formid;
        if(state ==true){
          body_efb.classList.add(`efb-waiting-${form_id}`);
        }else{
          body_efb.classList.remove(`efb-waiting-${form_id}`);
        }
      })
    }
  }

  await createStepsOfPublic();
  /* Snapshot every confirmation step while it still holds the server-rendered
   * loading message. createStepsOfPublic() only reads the existing fields, so
   * nothing has overwritten it yet at this point. Several forms can share the
   * page, hence the attribute selector rather than getElementById. */
  document.querySelectorAll('[id="efb-final-step"]').forEach((finalStep) => {
    efb_capture_final_step_efb(finalStep.dataset.formid);
  });
  // F08: hidden values, tag defaults and placeholders, answer pieces and URL prefill, before the first logic
  // pass (a rule may read a hidden field); user:* arrive later from forms/session/prepare.
  try { if (window.EFBSmart && typeof window.EFBSmart.init === 'function') window.EFBSmart.init().catch(() => {}); } catch (e) {}
  if (typeof window.efb_logic_runtime !== 'undefined' &&
      typeof window.efb_logic_runtime.initAll === 'function') {
    window.efb_logic_runtime.initAll();
  }
  // After the logic runtime is up, so restored answers run their rules too.
  try { await efb_restore_offline_answers_efb(); } catch (e) {}
  // F11: a resume link (?efb_resume=…) fills the form; partial answers start listening.
  try { await efb_f11_after_init_efb(); } catch (e) {}
  fun_wait_form_load_efb(false,'nfast');
  let captcha = false ;
  valj_efb_new.forEach((valj_efb) => {
    if(captcha==true) return;
    captcha = valj_efb.form_structer[0].captcha ;
    captcha = [1, '1', true, 'true'].includes(captcha) ? true : false;
  });

  if(captcha) loadCaptcha_efb(20);

  try {
    valj_efb_new.forEach(function(formData) {
      if (formData && formData.id && formData.form_structer && formData.form_structer[0]) {
        var steps = Number(formData.form_structer[0].steps) || 1;
        if (steps > 1) {
          updateStepButtonState_efb(formData.id);
        }
      }
    });
  } catch (e) { }
});

function updateStepButtonState_efb(form_id) {
  try {
    var id_body = 'body_efb_' + form_id;
    var body_efb = document.getElementById(id_body);
    if (!body_efb) {
      efb_steps_debug_efb('button-state:stop:body-missing', { form_id: form_id, body_id: id_body });
      return;
    }

    var currentStep = Number(body_efb.dataset.currentstep || 0);
    var maxStep = Number(body_efb.dataset.steps || 0);
    var nextTextEl = body_efb.querySelector('#button_group_Next_button_text');
    efb_steps_debug_efb('button-state:start', {
      form_id: form_id,
      current_step: currentStep,
      max_step: maxStep,
      next_text_found: !!nextTextEl,
      next_text: nextTextEl ? nextTextEl.textContent : null,
      next_button_found: !!body_efb.querySelector('#next_efb'),
      next_button_hidden: body_efb.querySelector('#next_efb') ? body_efb.querySelector('#next_efb').classList.contains('d-none') : null,
      prev_button_found: !!body_efb.querySelector('#prev_efb'),
      prev_button_hidden: body_efb.querySelector('#prev_efb') ? body_efb.querySelector('#prev_efb').classList.contains('d-none') : null
    });

    if (nextTextEl) {
      if (!nextTextEl.dataset.defaultText) {
        nextTextEl.dataset.defaultText = nextTextEl.textContent || 'Next';
      }

      var submitText =
        (typeof efb_var !== 'undefined' && efb_var && efb_var.text && efb_var.text.submit)
          ? efb_var.text.submit
          : ((typeof ajax_object_efm !== 'undefined' && ajax_object_efm && ajax_object_efm.text && ajax_object_efm.text.submit)
            ? ajax_object_efm.text.submit
            : 'Submit');

      nextTextEl.textContent = currentStep === maxStep ? submitText : nextTextEl.dataset.defaultText;
      efb_steps_debug_efb('button-state:text-updated', {
        form_id: form_id,
        current_step: currentStep,
        max_step: maxStep,
        default_text: nextTextEl.dataset.defaultText,
        submit_text: submitText,
        final_text: nextTextEl.textContent
      });
    }

    // Backfill missing form_id on legacy rows only when the owner can be inferred confidently.
    for (var bi = 0; bi < sendBack_emsFormBuilder_pub.length; bi++) {
      normalize_sendback_row_form_id_efb(sendBack_emsFormBuilder_pub[bi], -1);
    }
  } catch (e) {
    efb_steps_debug_efb('button-state:error', {
      form_id: form_id,
      message: e && e.message ? e.message : String(e)
    });
    console.error('[updateStepButtonState_efb] ERROR:', e);
  }
}

function efb_css_escape_efb(value) {
  var text = String(value || '');
  if (typeof CSS !== 'undefined' && CSS && typeof CSS.escape === 'function') {
    return CSS.escape(text);
  }
  return text.replace(/([ #;?%&,.+*~\':"!^$[\]()=>|\/@])/g, '\\$1');
}

function efb_form_body_efb(form_id) {
  return document.getElementById('body_efb_' + form_id) ||
    (Number(form_id) === 0 ? document.getElementById('body_efb') : null);
}

function efb_query_in_form_efb(form_id, selector) {
  var body = efb_form_body_efb(form_id);
  if (body && typeof body.querySelector === 'function') {
    try {
      return body.querySelector(selector);
    } catch (e) {}
  }
  try {
    return document.querySelector(selector);
  } catch (e) {
    return null;
  }
}

function efb_get_by_id_in_form_efb(form_id, id) {
  if (id === null || id === undefined || String(id).length === 0) return null;
  var body = efb_form_body_efb(form_id);
  var selector = '#' + efb_css_escape_efb(id);
  if (body && typeof body.querySelector === 'function') {
    try {
      var local = body.querySelector(selector);
      if (local) return local;
    } catch (e) {}
  }
  var globalEl = document.getElementById(String(id));
  if (!body) return globalEl;
  return globalEl && body.contains(globalEl) ? globalEl : null;
}

function efb_structure_for_form_efb(form_id) {
  if (!Array.isArray(valj_efb_new)) return null;
  var form = valj_efb_new.find(function(item) { return item && Number(item.id) === Number(form_id); });
  return form && Array.isArray(form.form_structer) ? form.form_structer : null;
}

function efb_field_owner_count_efb(field_id) {
  if (!Array.isArray(valj_efb_new) || field_id === null || field_id === undefined) return 0;
  var count = 0;
  for (var i = 0; i < valj_efb_new.length; i++) {
    var structure = valj_efb_new[i] && valj_efb_new[i].form_structer;
    if (!Array.isArray(structure)) continue;
    if (structure.some(function(field) { return field && String(field.id_) === String(field_id); })) count++;
  }
  return count;
}

function efb_field_exists_in_form_efb(field_id, form_id) {
  var structure = efb_structure_for_form_efb(form_id);
  if (!structure) return false;
  return structure.some(function(field) { return field && String(field.id_) === String(field_id); });
}

function efb_explicit_sendback_scope_efb(row) {
  if (!row || typeof row !== 'object') return null;
  if (!row.hasOwnProperty('form_id')) return null;
  if (row.form_id === '' || row.form_id === null || row.form_id === undefined) return null;
  var parsedFormId = Number(row.form_id);
  if (Number.isNaN(parsedFormId) || parsedFormId === 0) return null;
  return parsedFormId;
}

function efb_has_form_id_efb(row) {
  var scopeId = efb_explicit_sendback_scope_efb(row);
  return scopeId !== null && scopeId > 0;
}

function efb_form_id_from_dom_node_efb(node) {
  var cursor = node;
  while (cursor) {
    if (cursor.dataset && cursor.dataset.formid !== undefined && cursor.dataset.formid !== '') {
      var dataFormId = Number(cursor.dataset.formid);
      if (Number.isNaN(dataFormId) === false) return dataFormId;
    }
    if (cursor.id && /^body_efb_\d+$/.test(String(cursor.id))) {
      return Number(String(cursor.id).replace('body_efb_', ''));
    }
    cursor = cursor.parentElement || cursor.parentNode || cursor.parent || null;
  }
  return -1;
}

function efb_unique_dom_form_id_for_id_efb(id) {
  if (!id || typeof document !== 'object') return -1;
  var nodes = [];
  if (typeof document.querySelectorAll === 'function') {
    var safeAttr = String(id).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    try {
      nodes = Array.prototype.slice.call(document.querySelectorAll('[id="' + safeAttr + '"]'));
    } catch (e) {}
  }
  if (nodes.length === 0 && document.getElementById) {
    var el = document.getElementById(String(id));
    if (el) nodes = [el];
  }
  var found = [];
  for (var i = 0; i < nodes.length; i++) {
    var formId = efb_form_id_from_dom_node_efb(nodes[i]);
    if (formId !== -1 && found.indexOf(formId) === -1) found.push(formId);
  }
  return found.length === 1 ? found[0] : -1;
}

function efb_row_belongs_to_form_efb(row, form_id) {
  if (Number(form_id) === 0) return true;
  if (!row || typeof row !== 'object') return false;
  /* Positive ids belong to forms; negative ids are reserved scopes such as
     the public response box (-1). An explicit reserved scope must never be
     inferred as the only form rendered on the page. */
  var explicitScopeId = efb_explicit_sendback_scope_efb(row);
  if (explicitScopeId !== null) return explicitScopeId === Number(form_id);
  var inferred = infer_form_id_by_field_efb(row);
  if (inferred !== -1) return Number(inferred) === Number(form_id);
  var rowId = row.id_ || row.id || '';
  return rowId && efb_field_owner_count_efb(rowId) === 1 && efb_field_exists_in_form_efb(rowId, form_id);
}

smoothy_scroll_postion_efb=(id, form_id=0)=>{

  const element = Number(form_id) > 0 && typeof efb_get_by_id_in_form_efb === 'function'
    ? efb_get_by_id_in_form_efb(form_id, id)
    : document.getElementById(id);
  if (element) {
    const elementRect = element.getBoundingClientRect();
    const elementTop = elementRect.top + window.scrollY;
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
    const scrollPosition = elementTop - (viewportHeight / 2) + (elementRect.height / 2);

    window.scrollTo({
      top: scrollPosition,
      behavior: 'smooth'
    });
  }

}

setTimeout(() => {
  const sourceElement = document.getElementById('step-1-efb');
  const targetElement = document.getElementById('efb-final-step');

  if (sourceElement && targetElement) {
    const sourceHeight = sourceElement.offsetHeight;

    if(sourceElement.offsetHeight<300 && sourceElement.offsetWidth<300 ){
    }
  } else {
  }
}, 5000);

/* PAY00 readiness: the "online payment is not available" notice of a form (data-efb-pay-blocked),
   or null. When there is one, the form's Send / Submit is shown disabled and a click on it moves
   the visitor to the notice instead of sending. */
function efb_pay_blocked_efb(form_id, body) {
  const b = body || document.getElementById('body_efb_' + form_id);
  const n = b ? b.querySelector('[data-efb-pay-blocked="1"]') : null;
  if (n) {
    n.setAttribute('tabindex', '-1');
    try { n.scrollIntoView({ block: 'center' }); } catch (e) {}
    try { n.focus({ preventScroll: true }); } catch (e) {}
  }
  return n;
}
function efb_pay_block_send_efb(root) {
  (root || document).querySelectorAll('[data-efb-pay-blocked="1"]').forEach(function (n) {
    const body = n.closest('[id^="body_efb_"]');
    if (!body) return;
    const msg = (n.querySelector('.efb-pay-blocked__visitor') || n).textContent.trim();
    body.querySelectorAll('#btn_send_efb').forEach(function (btn) {
      btn.setAttribute('aria-disabled', 'true');
      btn.classList.add('disabled', 'efb-pay-send-off');
      btn.style.opacity = '0.55';
      btn.style.cursor = 'not-allowed';
      if (msg) btn.setAttribute('title', msg);
    });
  });
}
document.addEventListener('DOMContentLoaded', function () { efb_pay_block_send_efb(document); });
async function btn_navigate_handle_efb(form_id , form_type , btn_state,el){

  const id_body = 'body_efb_'+form_id;
  let parent_body = document.getElementById(id_body)
  efb_steps_debug_efb('navigate:start', {
    form_id: form_id,
    form_type: form_type,
    btn_state: btn_state,
    clicked_id: el ? el.id : null,
    clicked_classes: el ? el.className : null,
    body_id: id_body,
    body_found: !!parent_body,
    dom: efb_steps_dom_debug_efb(form_id)
  });

  if (!parent_body) {
    efb_steps_debug_efb('navigate:stop:body-missing', { form_id: form_id, body_id: id_body });
    return false;
  }

  const max_step = Number(parent_body.dataset.steps);
  let no_step = Number(parent_body.dataset.currentstep);
  /* PAY00 readiness: a form that cannot take its payment now (no keys, add-on off, …) shows a notice
     in place of the payment field and never sends; the server refuses it too (pay_not_ready). */
  if ((btn_state === 'btn_send_efb' || (btn_state === 'next_efb' && no_step >= max_step)) && efb_pay_blocked_efb(form_id, parent_body)) {
    return false;
  }
  let valj_efb = get_structure_by_form_id_efb(form_id);
  const progessbar = parent_body.querySelector('.progress-bar-efb') ?? null;
  const steps_shell_efb = parent_body.querySelector('.efb-sp') ?? null;
  efb_steps_debug_efb('navigate:resolved-state', {
    form_id: form_id,
    max_step: max_step,
    current_step: no_step,
    structure_rows: Array.isArray(valj_efb) ? valj_efb.length : null,
    first_row: valj_efb && valj_efb[0] ? {
      steps: valj_efb[0].steps,
      show_icon: valj_efb[0].show_icon,
      progress_style: valj_efb[0].progress_style,
      steps_style: valj_efb[0].steps_style
    } : null,
    progressbar_found: !!progessbar,
    steps_shell_found: !!steps_shell_efb,
    sync_function: typeof efbStepsSyncEfb,
    dom: efb_steps_dom_debug_efb(form_id, parent_body)
  });
  /* The wrapper can be there for the progress half alone - a classic steps row
     beside one of the new progress bars - so owning the row is a separate
     question from existing, and the classic row still needs its own handler. */
  const steps_shell_owns_row_efb = () => !!(steps_shell_efb && steps_shell_efb.querySelector('.efb-sp__item'));
  /* The name of a step, for the caption that stands in for the step titles on a
     long form or a phone. Read from the structure rather than from the row that
     was just clicked, because the steps list is not rendered at all when the
     author switched it off - and that is exactly when the caption matters. */
  const name_of_step_efb = (n) => {
    const row = (valj_efb ?? []).find(x => String(x.type) === 'step' && Number(x.step) === Number(n));
    if (row && row.name) return row.name;
    return (typeof efb_var !== 'undefined' && efb_var.text && efb_var.text.finish) ? efb_var.text.finish : '';
  };
  /* Both halves of the indicator move together now - the fill, the segments or
     the ring, and the done/active/todo run across the steps row - so they are
     driven from one call. efbStepsSyncEfb comes from new-efb.js, which is
     enqueued ahead of this file; the old width write stays as the fallback for
     a page that somehow loaded this script on its own. */
  fun_progessbar = (no_step, max_step) => {
    const total = Number(max_step) + 1;
    efb_steps_debug_efb('progress:before', {
      form_id: form_id,
      requested_step: no_step,
      max_step: max_step,
      total: total,
      has_sync: typeof efbStepsSyncEfb === 'function',
      has_core_fallback: typeof efb_steps_sync_fallback_efb === 'function',
      has_shell: !!steps_shell_efb,
      has_progressbar: !!progessbar,
      dom: efb_steps_dom_debug_efb(form_id, parent_body)
    });
    const syncHandler = typeof efbStepsSyncEfb === 'function' ? efbStepsSyncEfb : efb_steps_sync_fallback_efb;
    if (typeof syncHandler === 'function' && steps_shell_efb) {
      const syncResult = syncHandler(steps_shell_efb, no_step, total, name_of_step_efb(no_step));
      efb_steps_debug_efb('progress:sync-result', {
        form_id: form_id,
        requested_step: no_step,
        total: total,
        result: syncResult,
        handler: typeof efbStepsSyncEfb === 'function' ? 'runtime' : 'core-fallback',
        dom: efb_steps_dom_debug_efb(form_id, parent_body)
      });
      return true;
    }
    if (progessbar) {
      progessbar.style.width = ((no_step / total) * 100) + '%';
      efb_steps_debug_efb('progress:fallback-width', {
        form_id: form_id,
        requested_step: no_step,
        total: total,
        width: progessbar.style.width,
        reason: !steps_shell_efb ? 'steps-shell-missing' : 'sync-handler-missing',
        dom: efb_steps_dom_debug_efb(form_id, parent_body)
      });
    } else {
      efb_steps_debug_efb('progress:stop:no-target', {
        form_id: form_id,
        requested_step: no_step,
        total: total,
        has_sync: typeof syncHandler === 'function',
        has_shell: !!steps_shell_efb,
        has_progressbar: !!progessbar,
        dom: efb_steps_dom_debug_efb(form_id, parent_body)
      });
    }
    return true;
  }
  fun_check_step_has_necessary_fields_efb=(no_step ,valj_efb)=>{
    sendBack_emsFormBuilder_pub = sendBack_emsFormBuilder_pub.filter(Boolean);
    const result = (valj_efb ?? []).filter(x =>
      Number(x.step) === Number(no_step) && Number(x.required) === 1
    );
    if (result.length==0) return 0;

    for (let i = 0; i < result.length; i++) {
      const field_id = result[i].id_;
      const indx = sendBack_emsFormBuilder_pub.findIndex(x => x.id_ === field_id && Number(x.form_id) === Number(form_id));
      if (indx == -1) { return 1;  }
    }
    return 0;
  }
  step_payment_exists = -1;

  const title_efb = parent_body.querySelector('#title_efb') ?? null;
  const desc_efb = parent_body.querySelector('#desc_efb') ?? null;
  const steps_efb = parent_body.querySelector('#steps-efb') ?? null;
  fun_handle_header_efb = async(no_step,nav_state)=>{
    efb_steps_debug_efb('header:start', {
      form_id: form_id,
      requested_step: no_step,
      nav_state: nav_state,
      title_found: !!title_efb,
      desc_found: !!desc_efb,
      steps_found: !!steps_efb,
      show_icon: valj_efb && valj_efb[0] ? valj_efb[0].show_icon : null,
      owns_row: steps_shell_owns_row_efb(),
      sync_function: typeof efbStepsSyncEfb,
      dom: efb_steps_dom_debug_efb(form_id, parent_body)
    });
    if(!title_efb || !desc_efb || !steps_efb) {
      efb_steps_debug_efb('header:stop:missing-dom', {
        form_id: form_id,
        title_found: !!title_efb,
        desc_found: !!desc_efb,
        steps_found: !!steps_efb
      });
      return false;
    }
    if(Number(valj_efb[0].show_icon)==1){
      efb_steps_debug_efb('header:skip:show-icon-disabled', { form_id: form_id, requested_step: no_step });
      return true;
    }
    /* Every step is recomputed from the current one instead of the old pair of
       add/remove calls on the two rows either side. That pair could only ever
       express "this is the step you are on"; a step the visitor has already
       finished has to be drawn differently from one they have not reached, and
       a conditional-logic jump moves by more than one step at a time. */
    icon_step_handler = (no_step,form_id,nav_state)=>{
      const syncHandler = typeof efbStepsSyncEfb === 'function' ? efbStepsSyncEfb : efb_steps_sync_fallback_efb;
      if (typeof syncHandler === 'function' && steps_shell_owns_row_efb()) {
        const headerSyncResult = syncHandler(steps_shell_efb, no_step, max_step + 1, name_of_step_efb(no_step));
        efb_steps_debug_efb('header:sync-result', {
          form_id: form_id,
          requested_step: no_step,
          total: max_step + 1,
          result: headerSyncResult,
          handler: typeof efbStepsSyncEfb === 'function' ? 'runtime' : 'core-fallback',
          dom: efb_steps_dom_debug_efb(form_id, parent_body)
        });
        return true;
      }
      let id_active_icon = `${no_step}-f-step-efb-${form_id}`;
      let active_step_icon = document.getElementById(id_active_icon);
      active_step_icon ?  active_step_icon.classList.add('active') : false;
      efb_steps_debug_efb('header:fallback-active-add', {
        form_id: form_id,
        requested_step: no_step,
        active_id: id_active_icon,
        active_found: !!active_step_icon,
        nav_state: nav_state,
        reason: !steps_shell_owns_row_efb() ? 'steps-row-not-owned' : 'sync-handler-missing'
      });
      if(nav_state=='forward'){
        id_active_icon = `${(no_step-1)}-f-step-efb-${form_id}`;
        if(no_step==1) return true;
      }else{
        id_active_icon = `${(no_step+1)}-f-step-efb-${form_id}`;
      }
      active_step_icon = document.getElementById(id_active_icon)
      active_step_icon ? active_step_icon.classList.remove('active') : false;
      efb_steps_debug_efb('header:fallback-active-remove', {
        form_id: form_id,
        requested_step: no_step,
        removed_id: id_active_icon,
        removed_found: !!active_step_icon,
        nav_state: nav_state,
        dom: efb_steps_dom_debug_efb(form_id, parent_body)
      });
      return true;

    }
    if(no_step<=max_step){
    icon_step_handler(no_step,form_id,nav_state);
    const cp = no_step;
    const val = valj_efb.find(x=>x.step==cp);
      title_efb.className = colorTextChangerEfb(title_efb.className,val["label_text_color"]);
      desc_efb.className = colorTextChangerEfb(desc_efb.className,val["message_text_color"]);
      title_efb.innerHTML = val["name"];
      desc_efb.innerHTML = val["message"];
    }else{
      icon_step_handler(no_step,form_id,nav_state);
      const val = valj_efb.find(x=>x.step==1);
      title_efb.className = colorTextChangerEfb(title_efb.className,val["label_text_color"]);
      desc_efb.className = colorTextChangerEfb(desc_efb.className,val["message_text_color"]);
      title_efb.textContent = efb_var.text.finish;
      desc_efb.textContent = ' ';
    }
    return true;
  }

  const first_row = valj_efb[0];
  const current_fieldset = parent_body.querySelector(`[data-step="step-${no_step}-efb"]`);

  if (!current_fieldset) {
    efb_steps_debug_efb('navigate:stop:current-fieldset-missing', {
      form_id: form_id,
      current_step: no_step,
      selector: `[data-step="step-${no_step}-efb"]`,
      dom: efb_steps_dom_debug_efb(form_id, parent_body)
    });
    return false;
  }
  let payment_done_efb = true; // false only for a payment form that has not been paid yet
  if(form_type == 'payment'){
    /* The pay-first guard. It used to test findIndex() as a boolean: -1 (not
       paid) is truthy, so it never stopped an unpaid visitor, and it blocked a
       paid one with alert("undefined") when the payment row happened to be
       first. The gateway element of ZarinPal is "persiaPay", so the list is
       compared in lower case. A payment row without a form id still counts as
       paid: some gateways do not record it, and the server checks the payment
       in any case - this guard is only there to say "pay first" sooner. */
    const payment_method = ['paypal','stripe','persiapay','zarinpal'];
    const payment_rows = (valj_efb ?? []).filter(x => x && payment_method.includes(String(x.type).toLowerCase()) && x.hasOwnProperty('step'));
    if (payment_rows.length > 0) {
      step_payment_exists = Number(payment_rows[0].step);
      const paid = Array.isArray(sendBack_emsFormBuilder_pub) && sendBack_emsFormBuilder_pub.some(x => {
        if (!x || x.id_ !== 'payment') return false;
        const scope = efb_explicit_sendback_scope_efb(x);
        return scope === null || scope === Number(form_id);
      });
      payment_done_efb = paid;
      if (!paid && btn_state != 'prev_efb' && Number(no_step) >= step_payment_exists) {
        const _pt = (typeof ajax_object_efm !== 'undefined' && ajax_object_efm.text) ? ajax_object_efm.text : {};
        alert_message_efb(_pt.paymentNotCompleted || 'Your payment has not been completed. Please pay before sending the form.', '', 10, 'warning');
        return false;
      }
    }
  }

  if(btn_state != 'prev_efb'){
    efb_steps_debug_efb('validation:before-navigation', {
      form_id: form_id,
      btn_state: btn_state,
      current_step: no_step,
      sendback_rows: Array.isArray(sendBack_emsFormBuilder_pub) ? sendBack_emsFormBuilder_pub.filter(Boolean).length : null,
      dom: efb_steps_dom_debug_efb(form_id, parent_body)
    });
    const validate = await fun_validation_efb_v4(form_id);
    efb_steps_debug_efb('validation:after-navigation', {
      form_id: form_id,
      btn_state: btn_state,
      current_step_before: no_step,
      result: validate,
      body_currentstep_after_validation: parent_body.dataset.currentstep,
      dom: efb_steps_dom_debug_efb(form_id, parent_body)
    });
    if (validate == false) {
      efb_steps_debug_efb('navigate:stop:validation-false', {
        form_id: form_id,
        btn_state: btn_state,
        current_step: no_step,
        dom: efb_steps_dom_debug_efb(form_id, parent_body)
      });
      return false;
    }
    /* A field the visitor filled in but that is not valid (a mobile number, a URL,
       a number out of range) has already been taken out of the answers by its own
       check, with its message under it. It was then sent without that field, so an
       optional mobile number simply disappeared from the entry. Stop on it like on
       an empty required field. Fields hidden by conditional logic do not count. */
    const invalidFilled = (typeof sendback_efb_state !== 'undefined' && Array.isArray(sendback_efb_state) ? sendback_efb_state : []).filter(x => {
      if (!x || x.state !== false || Number(x.form_id) !== Number(form_id)) return false;
      if (x.step && Number(x.step) > Number(no_step)) return false;
      const fieldEl = document.getElementById(String(x.id_) + '_');
      if (!fieldEl || fieldEl.offsetParent === null) return false;
      // Emptied again and not required: nothing invalid is left to send.
      const isEmpty = typeof fieldEl.value === 'string' && fieldEl.value.trim() === '';
      const isRequired = fieldEl.required || fieldEl.getAttribute('aria-required') === 'true' || fieldEl.classList.contains('required');
      return !(isEmpty && !isRequired);
    });
    if (invalidFilled.length > 0) {
      const fieldEl = document.getElementById(String(invalidFilled[0].id_) + '_');
      try { fieldEl.scrollIntoView({ behavior: 'smooth', block: 'center' }); fieldEl.focus({ preventScroll: true }); } catch (e) {}
      const _vt = (typeof ajax_object_efm !== 'undefined' && ajax_object_efm.text) ? ajax_object_efm.text : {};
      alert_message_efb(_vt.pleaseEnterVaildValue || 'Please enter a valid value.', '', 8, 'warning');
      efb_steps_debug_efb('navigate:stop:invalid-filled-field', { form_id: form_id, fields: invalidFilled.map(x => x.id_) });
      return false;
    }
    /* F02: a filled field that asks for a one-time code moves on only once verified (and a "type it
       twice" e-mail only when both copies match); the reason is shown at the field. */
    if (window.EFBVerify && typeof window.EFBVerify.guard === 'function' && !window.EFBVerify.guard(form_id, no_step, btn_state, max_step)) {
      efb_steps_debug_efb('navigate:stop:verification', { form_id: form_id });
      return false;
    }
  }

  if(btn_state=='next_efb'){
        let prev_btn = parent_body.querySelector('#prev_efb');
        const _liveStepAfterValidation = Number(parent_body.dataset.currentstep);
        const _jumpedDuringValidation = _liveStepAfterValidation !== no_step;
        efb_steps_debug_efb('next:post-validation-state', {
          form_id: form_id,
          current_step_before: no_step,
          live_step_after_validation: _liveStepAfterValidation,
          jumped_during_validation: _jumpedDuringValidation,
          prev_button_found: !!prev_btn,
          max_step: max_step,
          dom: efb_steps_dom_debug_efb(form_id, parent_body)
        });

        if (_jumpedDuringValidation) {
          /* A conditional-logic jump_to_step action ran as a side effect of
           * the validation/evaluate() call above (e.g. a rule on the field
           * the user is leaving) and already moved the visible step —
           * fieldset visibility, progress bar, and title/description are
           * already correct. Adopt that destination instead of ALSO
           * advancing by one more step below, which would double-count the
           * jump and could overshoot past the real (or even the last) step. */
          no_step = _liveStepAfterValidation;
          if (prev_btn) prev_btn.classList.toggle('d-none', no_step <= 1);
          efb_steps_debug_efb('next:adopted-logic-jump', {
            form_id: form_id,
            adopted_step: no_step,
            max_step: max_step,
            dom: efb_steps_dom_debug_efb(form_id, parent_body)
          });
        } else {
          if(no_step<2){
            if(prev_btn)prev_btn.classList.remove('d-none');
          }
          no_step = Number(no_step)+1;
          efb_steps_debug_efb('next:incremented', {
            form_id: form_id,
            next_step_candidate: no_step,
            max_step: max_step
          });

          /* Skip logic-hidden steps forward — if all remaining steps are hidden,
           * no_step will exceed max_step and the form will proceed to submission. */
          while (no_step <= max_step) {
            const _cs = parent_body.querySelector('[data-step="step-' + no_step + '-efb"]');
            efb_steps_debug_efb('next:check-hidden-step', {
              form_id: form_id,
              step: no_step,
              fieldset_found: !!_cs,
              logic_hidden: _cs && _cs.dataset ? _cs.dataset.logicHidden : null
            });
            if (!_cs || _cs.dataset.logicHidden !== '1') break;
            no_step++;
          }

          await fun_handle_header_efb(no_step,'forward');
          current_fieldset.classList.add('d-none');
          const next_fieldset = parent_body.querySelector(`[data-step="step-${no_step}-efb"]`);
          efb_steps_debug_efb('next:fieldset-switch', {
            form_id: form_id,
            from_step: Number(parent_body.dataset.currentstep),
            to_step: no_step,
            current_fieldset_id: current_fieldset ? current_fieldset.id : null,
            next_fieldset_found: !!next_fieldset,
            next_fieldset_id: next_fieldset ? next_fieldset.id : null,
            overshoot: no_step > max_step
          });
          /* Overshooting the last step lands on #efb-final-step, which may still
           * show the previous attempt's error. Put the loading message back
           * before it becomes visible, never after. */
          if (no_step > max_step) efb_reset_final_step_efb(form_id, parent_body);
          if (next_fieldset) next_fieldset.classList.remove('d-none');
        }

       parent_body.dataset.currentstep = no_step;
       efb_reevaluate_logic_on_step_efb(form_id);
       efb_steps_debug_efb('next:dataset-updated', {
         form_id: form_id,
         current_step: no_step,
         max_step: max_step,
         dom: efb_steps_dom_debug_efb(form_id, parent_body)
       });
       fun_progessbar(no_step,max_step);

       if(no_step>max_step){
         if(el) el.classList.add('d-none');
         if(prev_btn) prev_btn.classList.add('d-none');
         efb_steps_debug_efb('next:overshoot-submit', {
           form_id: form_id,
           current_step: no_step,
           max_step: max_step,
           clicked_hidden: el ? el.classList.contains('d-none') : null,
           prev_hidden: prev_btn ? prev_btn.classList.contains('d-none') : null
         });
         endMessage_emsFormBuilder_view(max_step,form_id);
       }else if(no_step==max_step){
        updateStepButtonState_efb(form_id);
        efb_steps_debug_efb('next:last-real-step', {
          form_id: form_id,
          current_step: no_step,
          max_step: max_step,
          dom: efb_steps_dom_debug_efb(form_id, parent_body)
        });
       }
       smoothy_scroll_postion_efb(id_body);
       // Arriving on the gateway step locks Next until the payment is made - not after it
       // (a visitor back from the bank already paid; nothing would unlock it again).
       if(no_step==step_payment_exists && !payment_done_efb){
         if(el) el.classList.add('disabled');
         efb_steps_debug_efb('next:payment-step-disabled-button', {
           form_id: form_id,
           current_step: no_step,
           payment_step: step_payment_exists,
           clicked_id: el ? el.id : null
         });
       }

  }else if (btn_state=='prev_efb'){
    efb_steps_debug_efb('prev:start', {
      form_id: form_id,
      current_step_before: no_step,
      max_step: max_step,
      dom: efb_steps_dom_debug_efb(form_id, parent_body)
    });
    no_step = Number(no_step)-1;

    /* Skip logic-hidden steps backward */
    while (no_step > 1) {
      const _cs = parent_body.querySelector('[data-step="step-' + no_step + '-efb"]');
      efb_steps_debug_efb('prev:check-hidden-step', {
        form_id: form_id,
        step: no_step,
        fieldset_found: !!_cs,
        logic_hidden: _cs && _cs.dataset ? _cs.dataset.logicHidden : null
      });
      if (!_cs || _cs.dataset.logicHidden !== '1') break;
      no_step--;
    }

    if (no_step <= 1) {
      no_step = 1;
      let prev_btn = parent_body.querySelector('#prev_efb');
      if(prev_btn) prev_btn.classList.add('d-none');
    }

    parent_body.dataset.currentstep =  no_step;
    efb_reevaluate_logic_on_step_efb(form_id);
    const prev_fieldset = parent_body.querySelector(`[data-step="step-${no_step}-efb"]`);
    current_fieldset.classList.add('d-none');

    if(prev_fieldset)prev_fieldset.classList.remove('d-none');
    efb_steps_debug_efb('prev:fieldset-switch', {
      form_id: form_id,
      to_step: no_step,
      prev_fieldset_found: !!prev_fieldset,
      prev_fieldset_id: prev_fieldset ? prev_fieldset.id : null,
      dom: efb_steps_dom_debug_efb(form_id, parent_body)
    });
    fun_progessbar(no_step,max_step);
    smoothy_scroll_postion_efb(id_body);
    await fun_handle_header_efb(no_step,'backward');
    updateStepButtonState_efb(form_id);
  }else if (btn_state=='btn_send_efb'){
    efb_steps_debug_efb('send:start', {
      form_id: form_id,
      current_step_before: no_step,
      max_step: max_step,
      dom: efb_steps_dom_debug_efb(form_id, parent_body)
    });
    no_step = Number(no_step)+1;

    parent_body.dataset.currentstep = no_step;
    /* The confirmation fieldset lives beside the form body. Looking only in
     * parent_body made next_fieldset null on one-step forms and aborted Submit. */
    const next_fieldset = efb_get_final_step_efb(form_id, parent_body);
    /* Must happen before endMessage_emsFormBuilder_view() runs: that call can
     * write its own validation error into this step synchronously, and resetting
     * afterwards would wipe the message the visitor needs to read. */
    efb_reset_final_step_efb(form_id, parent_body);
    const submitFlow = endMessage_emsFormBuilder_view(Number(no_step) - 1, form_id);
    if (current_fieldset) current_fieldset.classList.add('d-none');
    if (next_fieldset) next_fieldset.classList.remove('d-none');
    efb_steps_debug_efb('send:final-step-visible', {
      form_id: form_id,
      current_step: no_step,
      final_step_found: !!next_fieldset,
      final_step_id: next_fieldset ? next_fieldset.id : null,
      dom: efb_steps_dom_debug_efb(form_id, parent_body)
    });
    fun_progessbar(no_step,max_step);
    smoothy_scroll_postion_efb(id_body);
    await fun_handle_header_efb(no_step,'forward');

    if(el) el.classList.add('d-none');
    await submitFlow;
  }

}

get_structure_by_form_id_efb=(form_id)=>{
  let ob = valj_efb_new.find(x => x.id == form_id);
  const form_structer = ob.form_structer;
  form_ID_emsFormBuilder = form_id;
  return form_structer.filter(Boolean);
}

infer_form_id_by_field_efb=(field_id)=>{
  // Fast path via cached map (field_id -> form_id), rebuilt when form schema changes.
  const get_map_efb =()=>{
    if (!Array.isArray(valj_efb_new)) return null;
    const cacheKey = `len:${valj_efb_new.length}|ids:${valj_efb_new.map(f => Number(f && f.id || -1)).join(',')}|rows:${valj_efb_new.map(f => Array.isArray(f && f.form_structer) ? f.form_structer.length : 0).join(',')}`;
    if (window.__efb_field_form_map_cache && window.__efb_field_form_map_cache.key === cacheKey) {
      return window.__efb_field_form_map_cache.map;
    }
    const mp = new Map();
    const put_efb = (key, fId)=>{
      if (key === null || key === undefined || String(key).length === 0) return;
      const normalizedKey = String(key);
      const existing = mp.get(normalizedKey);
      if (existing === undefined) {
        mp.set(normalizedKey, fId);
      } else if (existing !== null && Number(existing) !== Number(fId)) {
        mp.set(normalizedKey, null);
      }
    }
    for (let i = 0; i < valj_efb_new.length; i++) {
      const form = valj_efb_new[i];
      if (!form || !Array.isArray(form.form_structer)) continue;
      const fId = Number(form.id);
      for (let j = 0; j < form.form_structer.length; j++) {
        const row = form.form_structer[j];
        if (!row || typeof row !== 'object') continue;
        if (row.hasOwnProperty('id_') && String(row.id_).length > 0) {
          put_efb(String(row.id_), fId);
        }
        if (row.hasOwnProperty('id_ob') && String(row.id_ob).length > 0) {
          put_efb(`id_ob:${String(row.id_ob)}`, fId);
        }
        if (row.hasOwnProperty('name') && String(row.name).length > 0) {
          put_efb(`name:${String(row.name).toLowerCase()}`, fId);
        }
      }
    }
    window.__efb_field_form_map_cache = { key: cacheKey, map: mp };
    return mp;
  }

  if (!Array.isArray(valj_efb_new) || valj_efb_new.length === 0) return -1;

  const isObjectInput = field_id && typeof field_id === 'object';
  if (isObjectInput) {
    const explicitScopeId = efb_explicit_sendback_scope_efb(field_id);
    if (explicitScopeId !== null) return explicitScopeId;
  }

  const candidateId = isObjectInput
    ? (field_id.id_ || field_id.id || '')
    : field_id;

  const map = get_map_efb();
  if (!map) return -1;
  const read_hit_efb = (key)=>{
    const hit = map.get(key);
    if (hit === undefined || hit === null) return -1;
    return Number(hit);
  }

  if (candidateId !== null && candidateId !== undefined && String(candidateId).length > 0) {
    const hit = read_hit_efb(String(candidateId));
    if (hit !== -1) return hit;
  }

  // Fallback for objects that may not have id_ (legacy/special rows).
  if (isObjectInput) {
    if (field_id.hasOwnProperty('id_ob') && String(field_id.id_ob).length > 0) {
      const byOb = read_hit_efb(`id_ob:${String(field_id.id_ob)}`);
      if (byOb !== -1) return byOb;
    }
    if (field_id.hasOwnProperty('name') && String(field_id.name).length > 0) {
      const byName = read_hit_efb(`name:${String(field_id.name).toLowerCase()}`);
      if (byName !== -1) return byName;
    }
    // DOM fallback: if this row belongs to an input/select/textarea element, use dataset formid.
    const domId = field_id.id_ob || field_id.id_ || field_id.id || '';
    if (domId && typeof document === 'object') {
      const domFormId = efb_unique_dom_form_id_for_id_efb(String(domId));
      if (domFormId !== -1) return domFormId;
      const domInputFormId = efb_unique_dom_form_id_for_id_efb(`${domId}_`);
      if (domInputFormId !== -1) return domInputFormId;
    }
  }

  // Last fallback only when there is a single form on the page. With multiple
  // forms, guessing from active global context can validate the wrong form.
  if (Array.isArray(valj_efb_new) && valj_efb_new.length === 1 && form_ID_emsFormBuilder !== undefined && form_ID_emsFormBuilder !== null) {
    const activeFormId = Number(form_ID_emsFormBuilder);
    if (Number.isNaN(activeFormId) === false && activeFormId >= 0) return activeFormId;
  }

  return -1;
}

is_required_value_filled_efb=(row, fieldType='')=>{
  if (!row || typeof row !== 'object') return false;

  const t = String(fieldType || row.type || '').toLowerCase();
  const value = row.hasOwnProperty('value') ? row.value : undefined;

  const hasTextValue_efb = (v)=>{
    if (v === null || v === undefined) return false;
    if (typeof v === 'string') {
      const compact = v.replace(/@efb!/g, '').trim();
      return compact.length > 0;
    }
    if (Array.isArray(v)) return v.length > 0;
    if (typeof v === 'number') return true;
    if (typeof v === 'object') return Object.keys(v).length > 0;
    return !!v;
  }

  // Switch: required fields must be ON; value is always present.
  if (t === 'switch') {
    const v = String(value || '').toLowerCase().trim();
    return v === '1' || v === 'true' || v === 'on' || v === 'yes';
  }

  // File-like rows may carry url/type metadata while value is @file@ or even empty.
  if (t === 'file' || t === 'dadfile' || t === 'image' || t === 'document' || t === 'media' || t === 'allformat') {
    const hasUrl = typeof row.url === 'string' && row.url.trim().length > 0;
    if (hasUrl) return true;
    if (String(value || '') === '@file@') return false;
  }

  // Payment-related rows in viewer can be rendered by payment metadata even when value is empty.
  if (t === 'payment' || t === 'prcfld' || t.indexOf('pay') === 0) {
    if (hasTextValue_efb(value)) return true;
    if (hasTextValue_efb(row.price)) return true;
    if (hasTextValue_efb(row.total)) return true;
    if (hasTextValue_efb(row.paymentIntent)) return true;
    if (hasTextValue_efb(row.paymentmethod)) return true;
    if (hasTextValue_efb(row.refId)) return true;
    if (hasTextValue_efb(row.paymentCard)) return true;
    if (hasTextValue_efb(row.paymentCreated)) return true;
    if (hasTextValue_efb(row.updatetime)) return true;
    if (hasTextValue_efb(row.interval)) return true;
    return false;
  }

  // Image radio can be meaningful by src even if value is empty.
  if (t.indexOf('imgradio') !== -1) {
    if (hasTextValue_efb(value)) return true;
    if (typeof row.src === 'string' && row.src.trim().length > 0) return true;
    return false;
  }

  // Default behavior: use value, then common viewer fallbacks.
  if (hasTextValue_efb(value)) return true;
  if (typeof row.url === 'string' && row.url.trim().length > 0) return true;
  if (hasTextValue_efb(row.price)) return true;
  if (typeof row.src === 'string' && row.src.trim().length > 0) return true;

  return false;
}

normalize_sendback_row_form_id_efb=(row, fallback_form_id=-1)=>{
  if (!row || typeof row !== 'object') return row;
  /* Preserve both a real form id and a reserved negative scope. In
     particular, response-box message/captcha rows deliberately use -1. */
  if (efb_explicit_sendback_scope_efb(row) !== null) return row;
  const inferred = infer_form_id_by_field_efb(row);
  if (inferred !== -1) {
    row.form_id = inferred;
    return row;
  }
  if (Number(fallback_form_id) > 0 && row.id_ && efb_field_owner_count_efb(row.id_) === 1 && efb_field_exists_in_form_efb(row.id_, fallback_form_id)) {
    row.form_id = fallback_form_id;
  }
  return row;
}

sendback_state_handler_efb_v4=(id_,state,step,form_id)=>{
  const id_body = 'body_efb_'+form_id;
  const body_efb = document.getElementById(id_body);
  if (!body_efb) return;
  const indx = sendback_efb_state.findIndex(x=>x.id_==id_ && Number(x.form_id)==Number(form_id));
  if(indx==-1 && state==false){
    var actualStep = step;
    try {
      var _valj = get_structure_by_form_id_efb(form_id);
      var _field = _valj ? _valj.find(function(v){ return v.id_ === id_; }) : null;
      if (_field && _field.step) actualStep = Number(_field.step);
    } catch(e) {}
    sendback_efb_state.push({id_:id_,state:state,step:actualStep,form_id:form_id});
    updateStepButtonState_efb(form_id);
  }else if(indx>-1 && state==true && sendback_efb_state.length>0){
    sendback_efb_state.splice(indx,1);
    setTimeout(function() {
      updateStepButtonState_efb(form_id);
    }, 100);
  }
}

get_row_sendback_by_id_efb_v4=(id_,form_id=0)=>{

  if(form_id==0){
    return sendBack_emsFormBuilder_pub.findIndex(x => x!=null && x.hasOwnProperty('id_') && x.id_ == id_)

  }else{
    return sendBack_emsFormBuilder_pub.findIndex(x => x!=null && x.hasOwnProperty('id_') && x.id_ == id_ && efb_row_belongs_to_form_efb(x, form_id))
  }
 }

fun_prev_send =(form_id =0) =>{
  efb_steps_debug_efb('fun-prev-send:start', {
    form_id: form_id,
    dom: efb_steps_dom_debug_efb(form_id)
  });
  efb_hide_submit_ajax_badge_efb(form_id);
  let valj_efb = get_structure_by_form_id_efb(form_id);
  if (!Array.isArray(valj_efb) || !valj_efb.length) {
    efb_steps_debug_efb('fun-prev-send:stop:structure-missing', { form_id: form_id });
    return false;
  }
  var stp = Number(valj_efb[0].steps) + 1;
  const id_body = 'body_efb_'+form_id;
  const body_efb = document.getElementById(id_body);
  if (!body_efb) {
    efb_steps_debug_efb('fun-prev-send:stop:body-missing', { form_id: form_id, body_id: id_body });
    return false;
  }
  current_s_efb = body_efb.dataset.currentstep;
  const finalStepEl = efb_get_final_step_efb(form_id, body_efb);
  efb_reset_final_step_efb(form_id, body_efb);
  let id = `step-${current_s_efb}-efb`;
  var current_s = body_efb.querySelector(`[data-step="${id}"]`) || finalStepEl;

  /* Find last visible (non-logic-hidden) step to return to */
  let _prevTarget = Number(current_s_efb) - 1;
  while (_prevTarget > 1) {
    const _pc = body_efb.querySelector('[data-step="step-' + _prevTarget + '-efb"]');
    if (!_pc || _pc.dataset.logicHidden !== '1') break;
    _prevTarget--;
  }
  id = `step-${_prevTarget}-efb`;
  const prev_s_efb = body_efb.querySelector(`[data-step="${id}"]`);

  const steps_shell_efb = body_efb.querySelector('.efb-sp');
  const steps_shell_owns_row_efb = !!(steps_shell_efb && steps_shell_efb.querySelector('.efb-sp__item'));
  fun_progessbar = (current_step,max_step)=>{
    const total = Number(max_step) + 1;
    efb_steps_debug_efb('fun-prev-send:progress-before', {
      form_id: form_id,
      current_step: current_step,
      max_step: max_step,
      total: total,
      has_sync: typeof efbStepsSyncEfb === 'function',
      has_core_fallback: typeof efb_steps_sync_fallback_efb === 'function',
      has_shell: !!steps_shell_efb,
      dom: efb_steps_dom_debug_efb(form_id, body_efb)
    });
    const syncHandler = typeof efbStepsSyncEfb === 'function' ? efbStepsSyncEfb : efb_steps_sync_fallback_efb;
    if (typeof syncHandler === 'function' && steps_shell_efb) {
      const row = (valj_efb ?? []).find(x => String(x.type) === 'step' && Number(x.step) === Number(current_step));
      const result = syncHandler(steps_shell_efb, current_step, total, row && row.name ? row.name : '');
      efb_steps_debug_efb('fun-prev-send:progress-sync-result', {
        form_id: form_id,
        current_step: current_step,
        total: total,
        result: result,
        handler: typeof efbStepsSyncEfb === 'function' ? 'runtime' : 'core-fallback',
        dom: efb_steps_dom_debug_efb(form_id, body_efb)
      });
      return true;
    }
    const progessbar = body_efb.querySelector('.progress-bar-efb')
    if(!progessbar){
      efb_steps_debug_efb('fun-prev-send:progress-stop:no-target', { form_id: form_id, current_step: current_step, total: total });
      return false;
    }
    progessbar.style.width = ((current_step / total) * 100) + '%';
    efb_steps_debug_efb('fun-prev-send:progress-fallback-width', {
      form_id: form_id,
      current_step: current_step,
      total: total,
      width: progessbar.style.width,
      dom: efb_steps_dom_debug_efb(form_id, body_efb)
    });
    return true;
  }

  /* Left to the sync below when it is available: clearing `active` here and
     setting it again further down cannot express a step the visitor has
     already been through. */
  if(Number(valj_efb[0].show_icon)!=1 && !(steps_shell_owns_row_efb && (typeof efbStepsSyncEfb === 'function' || typeof efb_steps_sync_fallback_efb === 'function'))) {
    const currentIcon = document.getElementById(current_s_efb + '-f-step-efb-' + form_id);
    if (currentIcon) currentIcon.classList.remove("active");
  }
  if (current_s) current_s.classList.add("d-none");
  if (stp == 2) {
       const btn_send_efb = efb_find_in_form_efb(form_id, '#btn_send_efb', body_efb);
       if (btn_send_efb) btn_send_efb.classList.remove('d-none');
       const gRecaptcha = efb_find_in_form_efb(form_id, '#gRecaptcha', body_efb);
    if(gRecaptcha) {
      gRecaptcha.classList.remove('d-none');
      }
  } else {
    const next_efb = efb_find_in_form_efb(form_id, '#next_efb', body_efb);
    if (next_efb) next_efb.classList.remove('d-none');
  }
  var s = "" + _prevTarget + "";
  var val = valj_efb.find(x => x.step == s);
  if(Number(valj_efb[0].show_icon)!=1){
    const title_efb = body_efb.querySelector("#title_efb");
    const desc_efb = body_efb.querySelector("#desc_efb");
    if (val && title_efb && desc_efb) {
      title_efb.className = colorTextChangerEfb(title_efb.className,val['label_text_color']);
      desc_efb.className = colorTextChangerEfb(desc_efb.className,val['message_text_color']);
      title_efb.textContent = val['name'];
      desc_efb.textContent = val['message'];
    }

    if (!(steps_shell_owns_row_efb && (typeof efbStepsSyncEfb === 'function' || typeof efb_steps_sync_fallback_efb === 'function'))) {
      let id_active_icon = `${s}-f-step-efb-${form_id}`;
      const next_active_step_icon = document.getElementById(id_active_icon);
      if (next_active_step_icon) next_active_step_icon.classList.add('active');
    }
  }
  const prev_efb = efb_find_in_form_efb(form_id, '#prev_efb', body_efb);
  if(prev_efb)prev_efb.classList.toggle("d-none");
  if(current_s) current_s.classList.add('d-none');
  if(prev_s_efb) prev_s_efb.classList.remove('d-none');
  current_s_efb = _prevTarget;
  body_efb.dataset.currentstep = current_s_efb;
  efb_reevaluate_logic_on_step_efb(form_id);
  fun_progessbar(current_s_efb,stp);
  efb_steps_debug_efb('fun-prev-send:done', {
    form_id: form_id,
    current_step: current_s_efb,
    prev_target: _prevTarget,
    current_fieldset_found: !!current_s,
    prev_fieldset_found: !!prev_s_efb,
    dom: efb_steps_dom_debug_efb(form_id, body_efb)
  });
  smoothy_scroll_postion_efb(id_body);

}

/**
 * Show a specific step directly, regardless of whatever value
 * dataset.currentstep currently holds. fun_prev_send() always assumes
 * dataset.currentstep points at a real, currently-visible fieldset and just
 * decrements it — which throws (querySelector(...).classList on null) right
 * after a final-submit attempt, because the step-advance code in
 * btn_navigate_handle_efb already sets dataset.currentstep to max_step + 1
 * before the AJAX call even runs, and no fieldset exists for that step.
 * Used for the "Previous" button rendered on synthetic error screens
 * (#efb-final-step) after a failed submit/validation, where we cannot rely
 * on dataset.currentstep being a real, displayed step.
 */
function efb_go_to_step_direct(form_id, targetStep) {
  const body_efb = document.getElementById('body_efb_' + form_id);
  efb_steps_debug_efb('direct-step:start', {
    form_id: form_id,
    requested_target: targetStep,
    body_found: !!body_efb,
    dom: efb_steps_dom_debug_efb(form_id, body_efb || document)
  });
  if (!body_efb) {
    efb_steps_debug_efb('direct-step:stop:body-missing', { form_id: form_id, requested_target: targetStep });
    return false;
  }
  const valj_efb = get_structure_by_form_id_efb(form_id);
  const maxStep = Number(valj_efb[0].steps) || 1;
  targetStep = Math.max(1, Math.min(Number(targetStep) || 1, maxStep));
  efb_steps_debug_efb('direct-step:normalized-target', {
    form_id: form_id,
    target_step: targetStep,
    max_step: maxStep
  });

  if (typeof efb_hide_submit_ajax_badge_efb === 'function') efb_hide_submit_ajax_badge_efb(form_id);

  body_efb.querySelectorAll('[data-step^="step-"][data-step$="-efb"]').forEach((fieldset) => {
    fieldset.classList.add('d-none');
  });
  const finalStep = efb_get_final_step_efb(form_id, body_efb);
  if (finalStep) finalStep.classList.add('d-none');
  /* Drop the error the visitor is walking away from — including the field label
   * it names — so the step holds nothing but its loading message next time. */
  efb_reset_final_step_efb(form_id, body_efb);
  const targetFieldset = body_efb.querySelector('[data-step="step-' + targetStep + '-efb"]');
  if (targetFieldset) targetFieldset.classList.remove('d-none');

  body_efb.dataset.currentstep = targetStep;
  current_s_efb = targetStep;
  efb_reevaluate_logic_on_step_efb(form_id);

  const stepsShellEfb = body_efb.querySelector('.efb-sp');
  const stepsShellOwnsRowEfb = !!(stepsShellEfb && stepsShellEfb.querySelector('.efb-sp__item'));
  const directSyncHandler = typeof efbStepsSyncEfb === 'function' ? efbStepsSyncEfb : efb_steps_sync_fallback_efb;
  if (typeof directSyncHandler === 'function' && stepsShellEfb) {
    const jumped = valj_efb.find((x) => String(x.type) === 'step' && String(x.step) === String(targetStep));
    const directSyncResult = directSyncHandler(stepsShellEfb, targetStep, maxStep + 1, jumped && jumped.name ? jumped.name : '');
    efb_steps_debug_efb('direct-step:sync-result', {
      form_id: form_id,
      target_step: targetStep,
      total: maxStep + 1,
      result: directSyncResult,
      handler: typeof efbStepsSyncEfb === 'function' ? 'runtime' : 'core-fallback',
      dom: efb_steps_dom_debug_efb(form_id, body_efb)
    });
  } else {
    const progressBar = body_efb.querySelector('.progress-bar-efb');
    if (progressBar) progressBar.style.width = (targetStep / (maxStep + 1)) * 100 + '%';
    efb_steps_debug_efb('direct-step:fallback-width', {
      form_id: form_id,
      target_step: targetStep,
      total: maxStep + 1,
      progressbar_found: !!progressBar,
      width: progressBar ? progressBar.style.width : null,
      reason: !stepsShellEfb ? 'steps-shell-missing' : 'sync-handler-missing',
      dom: efb_steps_dom_debug_efb(form_id, body_efb)
    });
  }

  /* #next_efb is the SAME element whether it currently reads "Next" or
   * "Submit" — only its inner text is swapped by updateStepButtonState_efb()
   * (also used by jumpToStep() for the same reason). But the overshoot path
   * in btn_navigate_handle_efb hides the clicked button itself
   * (`el.classList.add('d-none')`) when treating the form as finished, and
   * that `el` is #next_efb — so it must be explicitly shown again here,
   * since we are by definition NOT finished if we're displaying a real step. */
  const nextBtn = efb_find_in_form_efb(form_id, '#next_efb', body_efb);
  if (nextBtn) nextBtn.classList.remove('d-none');
  /* #btn_send_efb is the equivalent single-step-form submit button (mutually
   * exclusive with #next_efb/#prev_efb) and gets hidden the same way on its
   * own overshoot path in btn_navigate_handle_efb — restore it too. */
  const sendBtn = efb_find_in_form_efb(form_id, '#btn_send_efb', body_efb);
  if (sendBtn) sendBtn.classList.remove('d-none');
  if (typeof updateStepButtonState_efb === 'function') updateStepButtonState_efb(form_id);

  const prevBtn = efb_find_in_form_efb(form_id, '#prev_efb', body_efb);
  if (prevBtn) prevBtn.classList.toggle('d-none', targetStep <= 1);

  if (Number(valj_efb[0].show_icon) !== 1) {
    const titleEl = body_efb.querySelector('#title_efb');
    const descEl = body_efb.querySelector('#desc_efb');
    const stepData = valj_efb.find((x) => String(x.step) === String(targetStep));
    if (stepData && titleEl && descEl) {
      titleEl.className = colorTextChangerEfb(titleEl.className, stepData['label_text_color']);
      descEl.className = colorTextChangerEfb(descEl.className, stepData['message_text_color']);
      titleEl.textContent = stepData['name'];
      descEl.textContent = stepData['message'];
    }
    if (!(stepsShellOwnsRowEfb && (typeof efbStepsSyncEfb === 'function' || typeof efb_steps_sync_fallback_efb === 'function'))) {
      for (let i = 1; i <= maxStep; i++) {
        const icon = document.getElementById(i + '-f-step-efb-' + form_id);
        if (icon) icon.classList.toggle('active', i === targetStep);
      }
    }
  }

  if (typeof smoothy_scroll_postion_efb === 'function') smoothy_scroll_postion_efb('body_efb_' + form_id);
  efb_steps_debug_efb('direct-step:done', {
    form_id: form_id,
    target_step: targetStep,
    steps_shell_owns_row: stepsShellOwnsRowEfb,
    target_fieldset_found: !!targetFieldset,
    dom: efb_steps_dom_debug_efb(form_id, body_efb)
  });
  return true;
}

async function handle_change_event_efb_v4(el ,form_id=0){
  let valj_efb = get_structure_by_form_id_efb(form_id);
  slice_sback=(i)=>{
    sendBack_emsFormBuilder_pub.splice(i, 1)
  }
  delete_by_id=(id)=>{
    const i = get_row_sendback_by_id_efb_v4(id,form_id);
    if (i != -1) { slice_sback(i) }
    // F08: an answer that is gone empties the pieces that read it.
    if (window.EFBSmart) { try { window.EFBSmart.refresh(Number(form_id), id); } catch (e) {} }
  }
  el_empty_value=(id)=>{
    const id_ = id +'_';
    const s = valj_efb.find(x => x.id_ == id);
    const el_msg = document.getElementById(id_+'-message');
    const el = document.getElementById(id_);
    if(Number(s.required)==0){
      if(el !== null) el.className = colorBorderChangerEfb(el.className, s.el_border_color);
      hide_msg_efb(el_msg);
     sendback_state_handler_efb_v4(id,true,0,form_id);
    }else{
      if(el !== null) el.className = colorBorderChangerEfb(el.className, "border-danger");
      if(el_msg!=null){
        // Use custom required message if set, otherwise use default
        const customMsg = s.hasOwnProperty('customRequiredMsg') && s.customRequiredMsg.length > 0 ? s.customRequiredMsg : efb_var.text.enterTheValueThisField;
        el_msg.innerHTML = customMsg;
        show_msg_efb(el_msg);
      }
    }
    delete_by_id(id);
    updateStepButtonState_efb(form_id);
    // Callers return straight after clearing a field, so without this a rule
    // that depended on the old value kept its effect until another field changed.
    efb_reevaluate_logic_after_change_efb(form_id, el);
  }
  validate_len_efb_v4 =async()=>{

    let offsetw = offset_view_efb();
    // The implicit minimum of 2 only ever applied to inputs with a data-type: a text input without
    // minlength accepts one character, as the server does (F03 §18.2 made its failures count).
    const mi=()=> {return  el.type!="number" && el.dataset.hasOwnProperty('type') ? 2 :0}
    let len = el.hasAttribute('minlength')  ? el.minLength :mi();
    // F03 §18.3: a textarea newline travels as "@n#" and counts as one character (as on the server); code points, not UTF-16 units.
    const vlen = Array.from(String(value).replace(/@n#/g, '\n')).length;
    if (vlen < len && el.type!="number") {
      state = false;
      el.className = colorBorderChangerEfb(el.className, "border-danger");
      vd = document.getElementById(`${el.id}-message`);
      let m = efb_var.text.mmplen.replace('NN',len);
      let msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${m}','',10,'danger')"></div>` : m ;
            if(vd){
              if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
                vd.classList.add('unpx');
              }
              vd.innerHTML =msg;
              vd.style.display='block';
            }
            delete_by_id(id_);
            return 0;
    }else if(el.type=="number" && el.hasAttribute('minlength') && Number(value) < len){
      state = false;
      el.className = colorBorderChangerEfb(el.className, "border-danger");
      vd = document.getElementById(`${el.id}-message`);
      let m = efb_var.text.mcplen.replace('NN',len);
      let msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${m}','',10,'danger')"></div>` : m ;
            if(vd){
              if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
                vd.classList.add('unpx');
              }
              vd.innerHTML =msg;
              vd.style.display='block';
            }
            delete_by_id(id_);
            return 0;
    }
    len =  el.hasAttribute('maxlength') ? el.maxLength :0;
    if(len==0) return 1;
    if (vlen > len && el.type!="number") {
      state = false;
      el.className = colorBorderChangerEfb(el.className, "border-danger");
      vd = document.getElementById(`${el.id}-message`);
      let m = efb_var.text.mmxplen.replace('NN',len);
      let msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${m}','',10,'danger')"></div>` : m ;
            if(vd){
              if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
                vd.classList.add('unpx');
              }
              vd.innerHTML =msg;
              vd.style.display='block';
            }
            delete_by_id(id_);
            return 0;
    }else if(value > len && el.type=="number"){
      state = false;
      el.className = colorBorderChangerEfb(el.className, "border-danger");
      vd = document.getElementById(`${el.id}-message`);
      let m = efb_var.text.mxcplen.replace('NN',len);
      let msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${m}','',10,'danger')"></div>` : m ;
            if(vd){
              if(Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
                vd.classList.add('unpx');
              }
              vd.innerHTML =msg;
              vd.style.display='block';
            }
            delete_by_id(id_);
            return 0;
    }
          return 1;
  }

  if(form_id==0) form_id = el.dataset.formid;
  valueJson_ws = get_structure_by_form_id_efb(form_id)
  let ob = valueJson_ws.find(x => x.id_ === el.dataset.vid);
  let value = ""
  const id_ = el.dataset.vid
  let state
  if(!ob){
    if(el.id.includes('chl')!=false){
      ob= sendBack_emsFormBuilder_pub.find(x => x.id_ob === el.dataset.id);
    }
  }
  let vd ;
  if(valj_efb[0].hasOwnProperty('booking')==true && Number(valj_efb[0].booking)==1) {
    const r = fun_booking_avilable(el)
    if(r[0]==false){
      alert_message_efb(r[1],'',150,'danger')
      return
    }
  }
  // F01: a masked field is checked by the mask engine instead of the length / phone rules, and the
  // value kept for the entry is the stored form (canonical, or as displayed when the field says so).
  const mask_fd_efb = (el.type === 'text' || el.type === 'tel') ? efb_mask_fd_efb(el) : null;
  let num_disp_efb = '';
  if (mask_fd_efb) {
    if (EfbMasks.normalize(el.value, {}) === '') { el_empty_value(id_); return; }
    const mr = EfbMasks.fieldCheck(mask_fd_efb, el.value);
    if (!mr.ok) {
      efb_mask_error_efb(el, mr.code, mask_fd_efb);
      sendback_state_handler_efb_v4(id_,false,0,form_id);
      delete_by_id(id_);
      updateStepButtonState_efb(form_id);
      efb_reevaluate_logic_after_change_efb(form_id, el);
      return;
    }
    value = mr.stored;
    state = true;
    el.className = colorBorderChangerEfb(el.className, "border-success");
    hide_msg_efb(document.getElementById(`${el.id}-message`));
  } else if (el.type === 'text' && el.dataset && el.dataset.num === '1' && window.EFBCalc) {
    // F04: a number box with the F04 keys: the canonical number is sent (1250000.5), the box shows it formatted.
    const nr = efb_num_check_efb(el, ob);
    if (nr.empty) { el_empty_value(id_); return; }
    if (!nr.ok) {
      sendback_state_handler_efb_v4(id_,false,0,form_id);
      delete_by_id(id_);
      updateStepButtonState_efb(form_id);
      efb_reevaluate_logic_after_change_efb(form_id, el);
      return;
    }
    value = nr.canon;
    num_disp_efb = window.EFBCalc.format(nr.canon, window.EFBCalc.cfgOf(el) || {});
    state = true;
    el.className = colorBorderChangerEfb(el.className, "border-success");
    hide_msg_efb(document.getElementById(`${el.id}-message`));
  } else
  switch (el.type) {
    case "text":
    case "color":
    case "number":
    case "date":
    case "textarea":
      const outp = el.type =="textarea" ?true : false
      value = sanitize_text_efb(el.value,outp);
      if(value.length==0){el_empty_value(id_); return;}
      if(el.classList.contains("intlPhone")==true){
        el.value = el.value.replace(/\s/g, '');
        value = el.value;
       return;
      }
      if(el.classList.contains("pdpF2") || el.classList.contains("hijri-picker")){
        // A typed date was stored as any text ("tomorrow", mixed digits).
        const typed_date = efb_typed_date_efb(el.value, el.classList.contains("pdpF2") ? 'jalali' : 'hijri');
        if(typed_date===null){
          el.className = colorBorderChangerEfb(el.className, "border-danger");
          vd = document.getElementById(`${el.id}-message`);
          if(vd){
            vd.innerHTML = efb_var.text.enterValidDate || efb_var.text.pleaseEnterVaildValue;
            show_msg_efb(vd);
          }
          sendback_state_handler_efb_v4(id_,false,0,form_id);
          delete_by_id(id_);
          updateStepButtonState_efb(form_id);
          return;
        }
        if(el.value!==typed_date) el.value = typed_date;
        value = typed_date;
      }
      {
        // F03: a field whose length belongs to its rules is measured by EfbRules after the switch.
        let lenResult = efb_rules_owns_length_efb(ob, form_id) ? 1 : await validate_len_efb_v4();
        // Number inputs carry no data-type, so their Min/Max message was shown and then ignored.
        // F03 §18.2: every failed length stops here (text inputs carry no data-type, so their failure used to be ignored).
        if(lenResult===0 && !(el.dataset.hasOwnProperty('type') && el.dataset.type=="chlCheckBox")){
          sendback_state_handler_efb_v4(id_,false,0,form_id);
          updateStepButtonState_efb(form_id);
          return;
        }else {
          el.className = colorBorderChangerEfb(el.className, "border-success");
          vd= document.getElementById(`${el.id}-message`)
          hide_msg_efb(vd);
        }
      }
      break;
    case 'url':
      vd = document.getElementById(`${el.id}-message`)
      const che = el.value.match(/^https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?!&\/=;',]*)$/g);
      if(el.value.length==0){
        el_empty_value(id_);
      } else if (che == null) {
        valid = false;
        el.className = colorBorderChangerEfb(el.className, "border-danger");
        vd.innerHTML = efb_var.text.enterValidURL;
        show_msg_efb(vd);
         sendback_state_handler_efb_v4(id_,false,0,form_id)
        delete_by_id(id_);
      } else {
        valid = true;
        value = el.value;
        hide_msg_efb(vd);
        el.className = colorBorderChangerEfb(el.className, "border-success");
      }
      break;
    case "checkbox":
    case "radio":
      value = sanitize_text_efb(el.value);
      if (ob.type == "switch") value = el.checked == true ? efb_var.text.on : efb_var.text.off;
      vd =document.getElementById(`${ob.id_}_-message`)
      if (el.value.length > 1 || el.checked == true) {
        hide_msg_efb(vd);
      } else {
        // Use custom required message if set, otherwise use default
        const customMsgCb = ob.hasOwnProperty('customRequiredMsg') && ob.customRequiredMsg.length > 0 ? ob.customRequiredMsg : efb_var.text.enterTheValueThisField;
        vd.innerHTML = customMsgCb;
        show_msg_efb(vd);
      }
      if( el.checked == false && el.type =="checkbox") {
        const indx= sendBack_emsFormBuilder_pub.findIndex(x=>x!=null && x.hasOwnProperty('id_ob')==true && x.id_ob ==el.id);
        if(indx!=-1) {
          slice_sback(indx)
          if(ob.type=="payCheckbox") fun_total_pay_efb(form_id);
          /* sendBack already updated via slice_sback — safe to evaluate logic now */
          const logicRuntime = efb_get_logic_runtime_efb();
          if (logicRuntime) {
            logicRuntime.evaluate(form_id);
          } else if (typeof fun_statement_logic_efb === 'function') {
            fun_statement_logic_efb(el.id, el.type, form_id);
          }
          return ;
        }
       }
       const indx =valj_efb.findIndex(x=>x.id_ ==el.name);
       if(indx!=-1 && valj_efb[indx].type.includes('chl')!=false && el.checked == true){
          if(el.type=="checkbox"){
            const id = el.id +'_chl';
            document.getElementById(id).disabled=false;
          }
       }else if (indx!=-1 && valj_efb[indx].type.includes('chl')!=false && el.checked == false){
        const id = el.id +'_chl';
        document.getElementById(id).disabled=true;
        document.getElementById(id).value ="";
       }
      break;
    case "select-one":
    case "select":
      value = sanitize_text_efb(el.value);
      vd =document.getElementById(`${ob.id_}_-message`)
      hide_msg_efb(vd);
      el.className = colorBorderChangerEfb(el.className, "border-success");
      if (valj_efb[0].type == "payment" && el.classList.contains('payefb')) {
        let v = selected_option_id_efb(el);
        v = valueJson_ws.find(x => x.id_ == v && x.value == el.value);
        /* Reset rather than leave the previous plan behind: price_efb is
           shared across the whole form, so a stale value here would be spent
           on whichever priced field the visitor touched next. */
        price_efb = v && typeof v.price == "string" ? v.price : "";
      }
      if(el.dataset.hasOwnProperty('type') && el.dataset.type=="conturyList"){
        let temp = valj_efb.findIndex(x => x.id_ === el.dataset.vid);
           await fun_check_link_state_efb(el.options[el.selectedIndex].dataset.iso , temp,el.dataset.formid);
      }else if(el.dataset.hasOwnProperty('type') && el.dataset.type=="stateProvince"){
           let temp = valj_efb.findIndex(x => x.id_ === el.dataset.vid);
            iso_con = el.options[el.selectedIndex].dataset.isoc
            iso_state = el.options[el.selectedIndex].dataset.iso
            setTimeout(async() => {
              if(iso_state && iso_con) {
               await fun_check_link_city_efb(iso_con,iso_state , temp, form_id);
             }
            }, 100);
      }else if(el.dataset.hasOwnProperty('type') && el.dataset.type=="cityList"){
            let temp = valj_efb.findIndex(x => x.id_ === el.dataset.vid);
            iso_state = el.options[el.selectedIndex].dataset.statepov
            iso_con = el.options[el.selectedIndex].dataset.isoc

      }
      break;
    case "range":
        value = sanitize_text_efb(el.value);
        vd = document.getElementById(`${ob.id_}_-message`);
        hide_msg_efb(vd);
      break;
    case "email":
      if(el.value.length==0){ el_empty_value(id_); return;}
      state = valid_email_emsFormBuilder(el);
      value = state == true ? sanitize_text_efb(el.value) : '';
      break;
    case "tel":
      // An empty optional phone is valid: stop here like email/text do, or the
      // check below marks it invalid again and Next/Send refuse the form.
      if(el.value.length==0){ el_empty_value(id_); return;}
      state = valid_phone_emsFormBuilder(el);
      value = state == true ? sanitize_text_efb(el.value) : '';
      break;
    case "password":
      state = valid_password_emsFormBuilder(el);
      // As typed: the text sanitizer drops "%41" and rewrites "<", which gave
      // the account a different password from the one the visitor chose.
      value = state == true ? el.value : '0';
      break;
    case "select-multiple":
      const parents = el.name;
      if (el.classList.contains('multiple-emsFormBuilder') == true) {
        for (let i = 0; i < el.children.length; i++) {
          value += el.children[i].value + ",";
        }
      }
      break;
    case "file":
      if(!el.classList.contains('dadfile')){
        valid_file_emsFormBuilder(id_,'msg','',form_id);
      }
      break;
    case "hidden":
      value = sanitize_text_efb(el.value);
      break;
    case undefined:
      let check = false;
      for (let ex of exportView_emsFormBuilder) {
        if (ex.id_ == el.id) {
          check = true;
          break;
        }
      }
      if (check == true) {
        ob = valueJson_ws.find(x => x.id_ === el.id);
        for (let o of select_options_emsFormBuilder) {
          value += o + `,`;
        }
      }
      break;
      default:
        vd=document.getElementById(`${ob.id_}_-message`);
        if(!ob) { hide_msg_efb(vd); }
      break;
  }
  form_id = el.dataset.hasOwnProperty('formid') ? el.dataset.formid : 0;
  // F03: the field's own rules (words, pattern, domains, password confirm / strength, messages) after its type check.
  if (state !== false && ob && typeof value === 'string' && value !== '' && !efb_rules_check_efb(el, ob, el.type === 'password' ? el.value : value, form_id)) {
    sendback_state_handler_efb_v4(id_,false,0,form_id);
    delete_by_id(id_);
    updateStepButtonState_efb(form_id);
    efb_reevaluate_logic_after_change_efb(form_id, el);
    return;
  }
  if(state===false && value.length > 0) {
    sendback_state_handler_efb_v4(id_,false,0,form_id);
  }
  if (value != "" || value.length > 0) {

    const type = ob.type;
    const id_ob = ob.type != "paySelect" ? el.id : selected_option_id_efb(el);
    let o = [{ id_: id_, name: ob.name, id_ob: id_ob, amount: ob.amount, type: type, value: value, session: sessionPub_emsFormBuilder,form_id:  form_id }];
    // F04: the display of a formatted number, for answer piping in the page (the server writes its own).
    if (num_disp_efb !== '' && num_disp_efb !== value) o[0].disp = num_disp_efb;
     sendback_state_handler_efb_v4(id_,true,0,form_id);
    if (el.classList.contains('payefb')) {
      let q = valueJson_ws.find(x => x.id_ === el.id);
      let p ;
      if(type =='prcfld'){
        p= Object.assign(o[0], {price: el.value});
      }else{
        /* `price` was never declared in this scope - the moment a paySelect
           reached this line it threw. price_efb is the price of the option
           just chosen and only a paySelect may spend it; a select has no row
           of its own in valueJson_ws (only its options do), so q is undefined
           there and every other field type has to read its own row. */
        p = { price: type == "paySelect" && price_efb.length > 0 ? price_efb : (q ? q.price : 0) };
      }
      Object.assign(o[0], p)

      fun_sendBack_emsFormBuilder(o[0]);
      fun_total_pay_efb(form_id)
    }else if(type.includes('option')){
      const ch = el.id.includes('_chl')
      const qty = ch  ? document.getElementById(el.id).value :'';
      if(ch==false){
      }else{
        el.classList.remove('bg-danger');
        const v =fun_text_forbiden_convert_efb(qty);
        const vid= el.dataset.vid;
        const indx = sendBack_emsFormBuilder_pub.findIndex(x=>x.id_ob==vid);

        if(indx!=-1){
          sendBack_emsFormBuilder_pub[indx].hasOwnProperty('qty') ? sendBack_emsFormBuilder_pub[indx].qty = v : Object.assign(sendBack_emsFormBuilder_pub[indx], {qty: v});
          return;
        }
      }

       await fun_sendBack_emsFormBuilder(o[0]);
    }else if (o[0].type=="email"){

      await fun_sendBack_emsFormBuilder(o[0]);
    }else {
      await fun_sendBack_emsFormBuilder(o[0]);
    }
  }
  updateStepButtonState_efb(form_id);
  /* Re-evaluate conditional logic AFTER sendBack has been updated — covers all field types */
  efb_reevaluate_logic_after_change_efb(form_id, el);
}

/**
 * Re-run a form's conditional logic after the visitor moved to another step,
 * for forms with a "current step" condition: nothing else changed, so the
 * runtime would otherwise keep the previous step's result until a field
 * changed. The guard stops a jump_to_step action, which moves the step itself,
 * from re-entering.
 */
function efb_reevaluate_logic_on_step_efb(form_id) {
  if (efb_reevaluate_logic_on_step_efb._busy) return;
  const logicStructure = get_structure_by_form_id_efb(form_id);
  const rules = logicStructure && logicStructure[0] ? logicStructure[0].logic_rules : null;
  if (!Array.isArray(rules) || rules.length === 0) return;
  let usesStep = false;
  try { usesStep = JSON.stringify(rules).indexOf('current_step') !== -1; } catch (e) { usesStep = false; }
  if (!usesStep) return;
  const logicRuntime = efb_get_logic_runtime_efb();
  if (!logicRuntime) return;
  efb_reevaluate_logic_on_step_efb._busy = true;
  try { logicRuntime.evaluate(form_id); } finally { efb_reevaluate_logic_on_step_efb._busy = false; }
}

/**
 * Re-run a form's conditional logic after one of its answers changed.
 * Does nothing for a form without rules.
 */
function efb_reevaluate_logic_after_change_efb(form_id, el) {
  const logicStructure = get_structure_by_form_id_efb(form_id);
  if (logicStructure && logicStructure[0] &&
      ((logicStructure[0].hasOwnProperty('logic') && logicStructure[0].logic) ||
       (logicStructure[0].hasOwnProperty('logic_rules') && Array.isArray(logicStructure[0].logic_rules) && logicStructure[0].logic_rules.length > 0))) {
    const logicRuntime = efb_get_logic_runtime_efb();
    if (logicRuntime) {
      logicRuntime.evaluate(form_id);
    } else if (typeof fun_statement_logic_efb === 'function' && el) {
      fun_statement_logic_efb(el.id, el.type, form_id);
    }
  }
}

/* F06: the advanced date field (advdate). The picker (public/assets/js/calendar) owns the field and
 * fires efb:date-change on its hidden canonical input after every committed change; this keeps the
 * answer row (sendBack_emsFormBuilder_pub), the step state and Conditional Logic in step with it.
 * The server checks the value again (includes/class-Emsfb-calendar.php). */
function efb_advdate_change_efb(e) {
  const d = e && e.detail ? e.detail : null;
  const input = e ? e.target : null;
  if (!d || !input || input.dataset.type !== 'advdate' || typeof get_structure_by_form_id_efb !== 'function') return;
  const form_id = d.formId || input.dataset.formid || 0;
  const id = d.fieldId;
  let structure = null;
  try { structure = get_structure_by_form_id_efb(form_id); } catch (x) { structure = null; }
  const ob = Array.isArray(structure) ? structure.find(x => x && x.id_ === id) : null;
  if (!ob) return;
  const visible = document.getElementById(id + '_');
  const i = get_row_sendback_by_id_efb_v4(id, form_id);
  if (!d.value) {
    if (i !== -1) sendBack_emsFormBuilder_pub.splice(i, 1);
    sendback_state_handler_efb_v4(id, Number(ob.required) !== 1 && !(visible && String(visible.value || '').trim() !== ''), 0, form_id);
  } else {
    const f = window.EFBCalendar ? EFBCalendar.get(id, form_id) : null;
    const row = { id_: id, name: ob.name, id_ob: id + '_', amount: ob.amount, type: 'advdate', value: input.value, cal: d.value.cal, session: sessionPub_emsFormBuilder, form_id: form_id };
    try { row.tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (x) { /* old browser */ }
    if (f && f.cfg && f.cfg.locale) row.loc = f.cfg.locale;
    fun_sendBack_emsFormBuilder(row);
    sendback_state_handler_efb_v4(id, true, 0, form_id);
    if (visible) visible.className = colorBorderChangerEfb(visible.className, 'border-success');
  }
  try { localStorage.setItem('sendback', efb_sendback_for_storage_efb(sendBack_emsFormBuilder_pub)); } catch (x) { /* private mode */ }
  updateStepButtonState_efb(form_id);
  efb_reevaluate_logic_after_change_efb(form_id, visible || input);
}
document.addEventListener('efb:date-change', efb_advdate_change_efb);
/* F06: rows of advanced dates that got their value (a default, a restored page) before this script was ready. */
function efb_advdate_sync_efb() {
  if (!window.EFBCalendar || typeof EFBCalendar.all !== 'function') return;
  EFBCalendar.all().forEach(function (f) {
    Promise.resolve(f.ready).then(function () {
      if (!f.value || !f.input || get_row_sendback_by_id_efb_v4(f.fieldId, f.formId) !== -1) return;
      efb_advdate_change_efb({ target: f.input, detail: { fieldId: f.fieldId, formId: f.formId, value: f.getValue(), source: 'sync' } });
    });
  });
}
/* F06: a typed date the picker refused (wrong format, a closed day) has no answer row: Next / Send stop at it. */
function efb_advdate_typed_invalid_efb(row, form_id) {
  if (!row || row.type !== 'advdate') return false;
  const el = efb_get_by_id_in_form_efb(form_id, `${row.id_}_`);
  if (!el || String(el.value || '').trim() === '') return false;
  return get_row_sendback_by_id_efb_v4(row.id_, form_id) === -1;
}

async function fun_validation_efb_v4(form_id) {
  var body_efb_v = document.getElementById('body_efb_' + form_id);
  if (body_efb_v) {
    current_s_efb = Number(body_efb_v.dataset.currentstep) || 1;
  }
  efb_steps_debug_efb('validation:start', {
    form_id: form_id,
    current_step: current_s_efb,
    body_found: !!body_efb_v,
    logic_runtime_found: typeof window.efb_logic_runtime !== 'undefined',
    logic_validate_found: typeof window.efb_logic_runtime !== 'undefined' && typeof window.efb_logic_runtime.validate === 'function',
    sendback_rows: Array.isArray(sendBack_emsFormBuilder_pub) ? sendBack_emsFormBuilder_pub.filter(Boolean).length : null,
    files_rows: Array.isArray(files_emsFormBuilder) ? files_emsFormBuilder.length : null,
    dom: efb_steps_dom_debug_efb(form_id, body_efb_v || document)
  });
  if (typeof window.efb_logic_runtime !== 'undefined' &&
      typeof window.efb_logic_runtime.validate === 'function') {
    const logicValidation = window.efb_logic_runtime.validate(form_id, current_s_efb);
    efb_steps_debug_efb('validation:logic-result', {
      form_id: form_id,
      current_step: current_s_efb,
      result: logicValidation
    });
    if (!logicValidation.valid) {
      /* block_submit / end_form carry their own message in missing_name */
      const logicAlertMsg = logicValidation.submit_blocked && logicValidation.missing_name
        ? logicValidation.missing_name
        : efb_var.text.fillrequiredfields;
      alert_message_efb(logicAlertMsg, '', 6, 'warning');
      if (logicValidation.missing_field && typeof smoothy_scroll_postion_efb === 'function') {
        smoothy_scroll_postion_efb(logicValidation.missing_field, form_id);
      }
      efb_steps_debug_efb('validation:stop:logic-invalid', {
        form_id: form_id,
        current_step: current_s_efb,
        submit_blocked: logicValidation.submit_blocked,
        missing_field: logicValidation.missing_field,
        missing_name: logicValidation.missing_name
      });
      return false;
    }
  }

  let offsetw = offset_view_efb();
  const defaultMsg = efb_var.text.enterTheValueThisField;
  let state = true;
  let idi = "null";
  let name_field = "";
  let valj_efb = get_structure_by_form_id_efb(form_id);
  let id_noti_message = valj_efb.steps > 1 ?  `step-${current_s_efb}-efb-msg` : 'alert_efb';
  efb_steps_debug_efb('validation:structure', {
    form_id: form_id,
    current_step: current_s_efb,
    rows: Array.isArray(valj_efb) ? valj_efb.length : null,
    first_row: valj_efb && valj_efb[0] ? {
      steps: valj_efb[0].steps,
      captcha: valj_efb[0].captcha,
      logic: valj_efb[0].logic,
      logic_rules_count: Array.isArray(valj_efb[0].logic_rules) ? valj_efb[0].logic_rules.length : 0
    } : null,
    notice_target_id: id_noti_message
  });
  for (let row in valj_efb) {
    let s =  get_row_sendback_by_id_efb_v4(valj_efb[row].id_,form_id);
    /* Skip validation for fields hidden by conditional logic (wrapper has d-none class,
     * or efb-anim-hide while the hide animation is still playing) */
    const _wrapper_v = efb_get_by_id_in_form_efb(form_id, valj_efb[row].id_);
    if (_wrapper_v && (_wrapper_v.classList.contains('d-none') || _wrapper_v.classList.contains('efb-anim-hide'))) {
      if (Number(valj_efb[row].step) === Number(current_s_efb)) {
        efb_steps_debug_efb('validation:skip-hidden-field', {
          form_id: form_id,
          current_step: current_s_efb,
          row_index: row,
          field_id: valj_efb[row].id_,
          field_type: valj_efb[row].type,
          wrapper_classes: _wrapper_v.className
        });
      }
      continue;
    }
    /* An email field holding text that is not an address. valid_email_emsFormBuilder()
     * already dropped the value, so an optional field used to submit without it,
     * silently, and a required one said "required" instead of what is wrong. */
    // F07: a field of several files: files still uploading, fewer than the minimum (§8.1).
    if (row > 1 && current_s_efb == valj_efb[row].step && (valj_efb[row].type == 'file' || valj_efb[row].type == 'dadfile')
      && typeof window.EFBUpload !== 'undefined' && window.EFBUpload.isMulti(valj_efb[row].id_, form_id)) {
      const upChk = window.EFBUpload.check(valj_efb[row].id_, form_id);
      if (upChk) {
        const upMsgEl = efb_get_by_id_in_form_efb(form_id, `${valj_efb[row].id_}_-message`);
        if (upMsgEl) { upMsgEl.textContent = upChk.message; upMsgEl.setAttribute('role', 'alert'); show_msg_efb(upMsgEl); }
        if (state == true) { state = false; idi = valj_efb[row].id_; name_field = valj_efb[row].name; }
        continue;
      }
    }
    // F05: an "Other" choice without its text, fewer / more choices than allowed, a required ranking not ordered yet.
    if (row > 1 && current_s_efb == valj_efb[row].step && window.EfbChoices && !window.EfbChoices.check(valj_efb[row], form_id)) {
      if (state == true) { state = false; idi = valj_efb[row].id_; name_field = valj_efb[row].name; }
      continue;
    }
    // F06: an advanced date typed but refused keeps its own message at the field.
    if (row > 1 && current_s_efb == valj_efb[row].step && efb_advdate_typed_invalid_efb(valj_efb[row], form_id)) {
      if (state == true) { state = false; idi = valj_efb[row].id_; name_field = valj_efb[row].name; }
      continue;
    }
    const masked_row_efb = row > 1 && typeof valj_efb[row].mask_preset === 'string' && valj_efb[row].mask_preset !== '';
    if (row > 1 && (masked_row_efb || ['email', 'tel', 'url', 'number'].includes(valj_efb[row].type)) && current_s_efb == valj_efb[row].step) {
      // The same holds for phone, URL, number and masked (F01) fields: their change handlers drop an invalid value too.
      const typedEl = efb_get_by_id_in_form_efb(form_id, `${valj_efb[row].id_}_`);
      if (typedEl && typeof typedEl.value === 'string' && typedEl.value.trim() !== '' && !valid_typed_value_efb(masked_row_efb ? 'masked' : valj_efb[row].type, typedEl)) {
        if (state == true) { state = false; idi = valj_efb[row].id_; name_field = valj_efb[row].name; }
        continue; // the field already shows its "enter a valid …" message
      }
    }
    // F03: a field with rules is measured again on Next / Send (its change handler may not have run, e.g. a default value).
    const rulesEl = row > 1 && current_s_efb == valj_efb[row].step ? efb_get_by_id_in_form_efb(form_id, `${valj_efb[row].id_}_`) : null;
    if (rulesEl && efb_rules_active_efb(valj_efb[row], form_id, rulesEl)) {
      if (typeof rulesEl.value === 'string' && rulesEl.value.trim() !== '') {
        const sbi = get_row_sendback_by_id_efb_v4(valj_efb[row].id_, form_id);
        const rulesVal = sbi !== -1 && sendBack_emsFormBuilder_pub[sbi] && typeof sendBack_emsFormBuilder_pub[sbi].value === 'string' && rulesEl.type !== 'password'
          ? sendBack_emsFormBuilder_pub[sbi].value : (rulesEl.tagName === 'TEXTAREA' ? sanitize_text_efb(rulesEl.value, true) : rulesEl.value);
        if (!efb_rules_check_efb(rulesEl, valj_efb[row], rulesVal, form_id)) {
          if (state == true) { state = false; idi = valj_efb[row].id_; name_field = valj_efb[row].name; }
          continue;
        }
      }
    }
    if (row > 1 && valj_efb[row].required == true && current_s_efb == valj_efb[row].step && valj_efb[row].type != "chlCheckBox") {
      const id = valj_efb[row].type == "yesNo" ? `${valj_efb[row].id_}_yn` : (fun_el_select_in_efb(valj_efb[row].type) == false ? `${valj_efb[row].id_}_` : `${valj_efb[row].id_}_options`);
      let el = efb_get_by_id_in_form_efb(form_id, `${valj_efb[row].id_}_-message`);
      let inputEl = efb_get_by_id_in_form_efb(form_id, id);

      let fieldFailed = false;
      if (valj_efb[row].type=='file' || valj_efb[row].type=='dadfile'){
        let r = files_emsFormBuilder.findIndex(x => x.id_ == valj_efb[row].id_ && efb_row_belongs_to_form_efb(x, form_id));
        if (r !== -1) {
          fieldFailed = files_emsFormBuilder[r].hasOwnProperty('state') && Number(files_emsFormBuilder[r].state) == 0;
        } else {
          // Upload completed — entry removed from files_emsFormBuilder; check sendback for uploaded URL
          const sbIndx = sendBack_emsFormBuilder_pub.findIndex(x =>
            x.id_ === valj_efb[row].id_ &&
            efb_row_belongs_to_form_efb(x, form_id) &&
            x.value === '@file@' &&
            typeof x.url === 'string' &&
            x.url.length > 0
          );
          fieldFailed = sbIndx === -1;
        }
      } else {
        fieldFailed = s == -1 || !is_required_value_filled_efb(sendBack_emsFormBuilder_pub[s], valj_efb[row].type);
      }
      efb_steps_debug_efb('validation:required-field', {
        form_id: form_id,
        current_step: current_s_efb,
        field_id: valj_efb[row].id_,
        field_name: valj_efb[row].name,
        field_type: valj_efb[row].type,
        sendback_index: s,
        sendback_row: s !== -1 ? sendBack_emsFormBuilder_pub[s] : null,
        input_id: id,
        input_found: !!inputEl,
        message_found: !!el,
        failed: fieldFailed
      });

      if (fieldFailed) {
        if (state == true) { state = false; idi = valj_efb[row].id_ , name_field = valj_efb[row].name }
        if(el && Number(offsetw)<525 && window.matchMedia("(max-width: 480px)").matches==0){
          el.classList.add('unpx');
        }
        // Use custom required message if set, otherwise use default
        const fieldMsg = valj_efb[row].hasOwnProperty('customRequiredMsg') && valj_efb[row].customRequiredMsg.length > 0 ? valj_efb[row].customRequiredMsg : defaultMsg;
        const msg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${fieldMsg}','',10,'danger')"></div>` : fieldMsg;
        if (el) {
          el.innerHTML = msg;
          show_msg_efb(el);
        }
        if (inputEl && type_validate_efb(valj_efb[row].type) == true) {
          inputEl.className = colorBorderChangerEfb(inputEl.className, "border-danger");}
      } else {
        idi = valj_efb[row].id_;
        if (el) hide_msg_efb(el);
        if (inputEl && type_validate_efb(valj_efb[row].type) == true) inputEl.className = colorBorderChangerEfb(inputEl.className, "border-success");
        const v = sendBack_emsFormBuilder_pub.length>0 && valj_efb[row].type == "multiselect" && sendBack_emsFormBuilder_pub[s].hasOwnProperty('value') ? sendBack_emsFormBuilder_pub[s].value.split("@efb!") :"";
        if ((valj_efb[row].type == "multiselect" || valj_efb[row].type == "payMultiselect") && (v.length - 1) < valj_efb[row].minSelect) {
          name_field = valj_efb[row].name
          if (inputEl) inputEl.className = colorBorderChangerEfb(inputEl.className, "border-danger");
          if (el) {
            el.innerHTML = efb_var.text.minSelect + " " + valj_efb[row].minSelect
            show_msg_efb(el);
          }
          if (state == true) { state = false; idi = valj_efb[row].id_ }
        }
      }
    }else if (row > 1 && valj_efb[row].type == "chlCheckBox" && current_s_efb == valj_efb[row].step && !(_wrapper_v && (_wrapper_v.classList.contains('d-none') || _wrapper_v.classList.contains('efb-anim-hide')))){
      name_field = valj_efb[row].name;
      idi = valj_efb[row].id_;
      efb_steps_debug_efb('validation:chl-checkbox-field', {
        form_id: form_id,
        current_step: current_s_efb,
        field_id: idi,
        field_name: name_field
      });
      fun_noti_chlcheckbox = (idi,name_field,id_noti_message,form_id) => {
         const vd = idi+"_chl";
          state = false;

          const messag = efb_var.text.sfmcfop.replace('%s',`<b>${name_field}</b>`);
          noti_message_efb_v4(messag, 'danger' ,id_noti_message ,form_id);

      }
        let state_of_ch = true;;
        const msgEl = efb_get_by_id_in_form_efb(form_id, idi+'_-message');
        const wrapperEl = efb_get_by_id_in_form_efb(form_id, idi);
        const em = sendBack_emsFormBuilder_pub.find(x => x.id_ob == idi && efb_row_belongs_to_form_efb(x, form_id));
        if(em==undefined || em==null ){
            // Use custom required message if set, otherwise use default
            const chlFieldMsg = valj_efb[row].hasOwnProperty('customRequiredMsg') && valj_efb[row].customRequiredMsg.length > 0 ? valj_efb[row].customRequiredMsg : defaultMsg;
            const chlMsg = Number(offsetw)<380 && window.matchMedia("(max-width: 480px)").matches==0 ? `<div class="efb fs-5 nmsgefb bi-exclamation-diamond-fill" onclick="alert_message_efb('${chlFieldMsg}','',10,'danger')"></div>` : chlFieldMsg;
            if (msgEl) {
              msgEl.innerHTML = chlMsg;
              show_msg_efb(msgEl);
            }

            if (wrapperEl) wrapperEl.classList.add('bg-warning');

        }else if(em.id_ob == idi && em.hasOwnProperty('qty')==false && em.qty == "" && em.value.length == 0 ) {
          fun_noti_chlcheckbox(idi,name_field,id_noti_message,form_id);

        }else{
            if (msgEl) hide_msg_efb(msgEl);
            if (wrapperEl) wrapperEl.classList.remove('bg-warning');
        }

    }
  }

  if (state === true && valj_efb[0]) {
    var max_step_v = Number(valj_efb[0].steps) || 1;
    if (Number(current_s_efb) === max_step_v && Number(valj_efb[0].captcha) === 1) {
      var hasCaptcha = sendBack_emsFormBuilder_pub.findIndex(function(x) {
        return x != null && x.id_ === 'captcha_v2' && Number(x.form_id) === Number(form_id);
      }) !== -1;
      if (!hasCaptcha) {
        state = false;
        efb_steps_debug_efb('validation:captcha-missing', {
          form_id: form_id,
          current_step: current_s_efb,
          max_step: max_step_v
        });
        alert_message_efb(efb_var.text.checkedBoxIANotRobot, '', 8, 'warning');
        return false;
      }
    }
  }

  if (state===false) {
    efb_steps_debug_efb('validation:stop:required-invalid', {
      form_id: form_id,
      current_step: current_s_efb,
      first_invalid_id: idi,
      first_invalid_name: name_field,
      dom: efb_steps_dom_debug_efb(form_id, body_efb_v || document)
    });
    alert_message_efb(efb_var.text.fillrequiredfields, '', 6, 'warning');
    if (idi != "null") {
      if(typeof smoothy_scroll_postion_efb === 'function'){
        smoothy_scroll_postion_efb(idi, form_id)
      }else{
        const scrollTarget = efb_get_by_id_in_form_efb(form_id, idi);
        if (scrollTarget) scrollTarget.scrollIntoView({behavior: "smooth", block: "center", inline: "center"});
      }
    }
    return false;
  }
  efb_steps_debug_efb('validation:success', {
    form_id: form_id,
    current_step: current_s_efb,
    dom: efb_steps_dom_debug_efb(form_id, body_efb_v || document)
  });
  return state
}

function noti_message_efb_v4(message, alert ,id,form_id=0){
  alert = alert ? `alert-${alert}` : 'alert-info';
  let d = document.querySelector(`#${id}[data-formid="${form_id}"]`);
  if(d.querySelector('#noti_content_efb')){
    d.querySelector('#noti_content_efb').remove()
  }
    d.innerHTML += ` <div id="noti_content_efb" class="efb w-75 mt-0 my-1 alert-dismissible alert ${alert}  ${efb_var.rtl == 1 ? 'rtl-text' : ''}" role="alert">
    <p class="efb my-0">${message}</p>
  </div>`
}

function call_fun_jalali_datepicker_efb_v4(){
  if(typeof load_style_persian_data_picker_efb =="function"){
    load_style_persian_data_picker_efb();
  }else{
    setTimeout(() => {
      console.warn('[EFB] Jalali date picker add-on is not loaded');
      alert_message_efb(ajax_object_efm.text.error, ajax_object_efm.text.tfnapca, 20 , 'info');
    }, 1000);
  }
}

function call_fun_hijri_datapicker_efb_v4(){
  if(typeof  load_hijir_data_picker_efb=="function"){
    load_hijir_data_picker_efb()

  }else{
    setTimeout(() => {
      console.warn('[EFB] Hijri date picker add-on is not loaded');
      alert_message_efb(ajax_object_efm.text.error, ajax_object_efm.text.tfnapca, 20 , 'info')
    }, 1000);
  }
}
const fun_sid_efb =(form_id)=>{
  let vj = valj_efb_new.find(x=>x.id==form_id);
  const sid = vj.sid;
  valj_efb = vj.form_structer;
  formNameEfb = valj_efb[0].formName;
  captcah = valj_efb[0].hasOwnProperty('captcha') ? valj_efb[0].captcha : false;
  let r=  {sid:sid,type:vj.type,formName:formNameEfb,captcah:captcah};
  if(vj.hasOwnProperty('nonce_msg')){
    r = Object.assign(r,{nonce_msg:vj.nonce_msg});
  }
  return r;
}

const speed_test_efb=()=>{
    if ('connection' in navigator) {
      const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;

      if( connection.downlink<1){
        return 'slow';
      }else if(connection.downlink>=1 && connection.downlink<2){
        return 'medium';
      }else {
        return 'fast'
      }
  } else {
     return 'notSupported';
  }
}

const check_form_payment_filled_efb = (form_id=0) =>{
  const payment_method =['paypal','stripe','persiapay' ,'zarinpal'];
  let valj_efb;
  try { valj_efb = get_structure_by_form_id_efb(form_id); }
  catch (e) { valj_efb = window.valj_efb; }
  if(!Array.isArray(valj_efb) || !valj_efb[0]) return false;
  if(valj_efb[0].type != 'payment') return true;

  // Conditional-logic forms must stay on the page after payment: the payment
  // rules (is_paid / amount_*) may reveal or require fields the user still has
  // to fill, so the form is never auto-submitted — only re-evaluated.
  if (typeof EFBConditionalLogic !== 'undefined' && EFBConditionalLogic &&
      typeof EFBConditionalLogic.hasActiveRules === 'function' &&
      EFBConditionalLogic.hasActiveRules(valj_efb)) {
    const logicRuntime = efb_get_logic_runtime_efb();
    if (logicRuntime) logicRuntime.evaluate(form_id);
    return false;
  }
  let necessary_fields_filed = [];
  let is_getway_last_filed = false;
  last_row_index = valj_efb.length - 1;
  const steps = Number(valj_efb[0].steps);
  const peyment_type = valj_efb[last_row_index].type.toLowerCase();
  if(payment_method.includes(peyment_type)){
    is_getway_last_filed = true;
  }else{
    return false;
  }

  for (let row in valj_efb) {

    if (valj_efb[row].hasOwnProperty('required') && valj_efb[row].required == true) {
      necessary_fields_filed.push(valj_efb[row].id_);
    }
  }
    for(let i=0; i<necessary_fields_filed.length; i++){
      const field_id = necessary_fields_filed[i];
      const s =  get_row_sendback_by_id_efb_v4(field_id, form_id);
      if(s == -1){
        return false;
      }
    }
    const bdy = document.getElementById(`body_efb_${form_id}`);
    if(!bdy) return false;
    if(steps==1){
      const btn_el =bdy.querySelector('#btn_send_efb');
      btn_navigate_handle_efb(form_id , 'payment' , 'btn_send_efb' , btn_el);

    }else{
      bdy.dataset.currentstep = steps;
      current_s_efb = steps;
      const btn_el =bdy.querySelector('#next_efb');
      btn_navigate_handle_efb(form_id , 'payment' , 'next_efb' , btn_el);

    }

}

/* ---------------------------------------------------------------------------------------------
 * F11: the form gate in the page (docs/feature-gaps/F11-form-limits-resume.fa.md, "رندر").
 * The server decides (render, forms/session/prepare, every submit); these only show it.
 * efb_gate_card_html_efb() is the twin of Form_Gate::card_html() (tests/test-f11-parity.js).
 * A card in "swap" mode sits in a cacheable page (widget, popup) next to the hidden or shown
 * form: prepare says which one is right now. A card in "card" mode (or a hidden one that only
 * carries the next change) reloads the page when the form opens or closes within a day.
 * ------------------------------------------------------------------------------------------- */
const EFB_GATE_CODES = ['efb_form_pending', 'efb_form_closed', 'efb_form_full', 'efb_limit_reached', 'efb_role_denied'];
const EFB_GATE_STATES = { efb_form_pending: 'pending', efb_form_closed: 'closed', efb_form_full: 'full', efb_limit_reached: 'limited', efb_role_denied: 'role' };
const EFB_GATE_ICONS = { open: 'bi-check2-circle', pending: 'bi-hourglass-split', closed: 'bi-calendar-x', full: 'bi-people-fill', limited: 'bi-person-check', role: 'bi-shield-lock-fill' };
function efb_gate_escape_efb(v) {
  return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
function efb_gate_card_html_efb(state, message, fid, next, mode) {
  state = Object.prototype.hasOwnProperty.call(EFB_GATE_ICONS, state) ? state : 'closed';
  const hidden = state === 'open' ? ' d-none' : '';
  const n = next ? String(Math.floor(Number(next)) || '') : '';
  return '<div class="efb efb-gate-card text-center my-4 p-4' + hidden + '" id="efb-gate-' + (parseInt(fid, 10) || 0) + '" role="status" data-efb-gate="' + state + '" data-efb-gate-next="' + n + '" data-efb-gate-mode="' + (mode === 'swap' ? 'swap' : 'card') + '" data-formid="' + (parseInt(fid, 10) || 0) + '">'
    + '<div class="efb efb-gate-icon ' + EFB_GATE_ICONS[state] + ' display-4 mb-2" aria-hidden="true"></div>'
    + '<p class="efb efb-gate-msg fs-5 mb-0" dir="auto" style="unicode-bidi:plaintext">' + efb_gate_escape_efb(message) + '</p>'
    + '</div>';
}
/* The language's digits (twin of Form_Gate::locale_digits()). */
function efb_gate_digits_efb(s, locale) {
  const lang = String(locale || '').toLowerCase().split(/[_-]/)[0];
  let base = 0;
  if (['fa', 'ps', 'azb', 'haz'].includes(lang)) base = 0x06F0;
  else if (['ar', 'ary', 'arq', 'ckb', 'ur'].includes(lang)) base = 0x0660;
  if (!base) return String(s);
  return String(s).replace(/[0-9]/g, (d) => String.fromCharCode(base + Number(d)));
}
function efb_gate_left_html_efb(left, fid, text, locale, grouped) {
  const t = String(text || '%s places left').replace('%s', efb_gate_digits_efb(grouped != null ? grouped : String(left), locale));
  return '<p class="efb efb-gate-left text-center fs-6 my-2" id="efb-gate-left-' + (parseInt(fid, 10) || 0) + '" aria-live="polite" data-left="' + (parseInt(left, 10) || 0) + '">' + efb_gate_escape_efb(t) + '</p>';
}
function efb_gate_locale_efb() {
  try { if (typeof ajax_object_efm !== 'undefined' && ajax_object_efm && ajax_object_efm.language) return ajax_object_efm.language; } catch (e) {}
  try { return document.documentElement.lang || ''; } catch (e) { return ''; }
}
/* The visitor's gate now (POST forms/gate/state: 200 with {state, code, message, next, left}; a
 * closed form is an answer there, so the page logs no failed request and no session is made). */
async function efb_gate_state_efb(fid) {
  const rest = (typeof efb_request_health !== 'undefined' && efb_request_health.rest_url) ? efb_request_health.rest_url
    : ((typeof efb_var !== 'undefined' && efb_var.rest_url) ? efb_var.rest_url + 'Emsfb/v1/' : '/wp-json/Emsfb/v1/');
  const r = await fetch(rest + 'forms/gate/state', { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ form_id: Number(fid) }) });
  if (!r.ok) return null;
  return r.json();
}
/* Show the card or the form of one cached widget, as the server answers now. */
async function efb_gate_check_efb(fid) {
  const card = document.getElementById('efb-gate-' + fid);
  const body = document.getElementById('body_efb_' + fid);
  if (!card) return null;
  let v = null;
  try { v = await efb_gate_state_efb(fid); } catch (e) { v = null; }
  if (!v || typeof v.state !== 'string') return null; // no answer: the page stays as printed, the submit decides
  const state = Object.prototype.hasOwnProperty.call(EFB_GATE_ICONS, v.state) ? v.state : 'closed';
  const message = v.message || '';
  const next = Number(v.next) || 0;
  const left = v.left != null ? Number(v.left) : null;
  card.outerHTML = efb_gate_card_html_efb(state, message, fid, next, 'swap');
  if (body) body.classList.toggle('d-none', state !== 'open');
  if (left != null) {
    const t = (typeof ajax_object_efm !== 'undefined' && ajax_object_efm && ajax_object_efm.text) ? ajax_object_efm.text.limLeft : '';
    const old = document.getElementById('efb-gate-left-' + fid);
    const html = efb_gate_left_html_efb(left, fid, t, efb_gate_locale_efb());
    if (old) old.outerHTML = html; else if (body) body.insertAdjacentHTML('beforebegin', html);
  }
  efb_gate_timer_efb(fid, next, 'swap');
  return state;
}
function efb_gate_timer_efb(fid, next, mode) {
  const at = Number(next) * 1000;
  if (!at) return;
  const wait = at - Date.now() + 1500;
  if (wait <= 0 || wait > 86400000) return;
  setTimeout(() => {
    if (mode === 'swap') efb_gate_check_efb(fid);
    else { try { location.reload(); } catch (e) {} }
  }, wait);
}
function efb_gate_boot_efb(root) {
  const cards = (root || document).querySelectorAll('[data-efb-gate]');
  cards.forEach((card) => {
    const fid = Number(card.getAttribute('data-formid')) || 0;
    if (!fid) return;
    if (card.getAttribute('data-efb-gate-mode') === 'swap') efb_gate_check_efb(fid);
    else efb_gate_timer_efb(fid, card.getAttribute('data-efb-gate-next'), 'card');
  });
}
/* F11-H: popup forms (native <dialog>, showModal): Esc and the close button close it, the focus goes
 * back to the button that opened it, efb:popup-open (and a resize) lets maps, the signature pad and
 * intl-tel-input measure again. popup_delay > 0 opens it once per browser session. */
function efb_popup_open_efb(fid, opener) {
  const dlg = document.getElementById('efb-dlg-' + fid);
  if (!dlg || dlg.open) return false;
  if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
  dlg._efbOpener = opener || document.getElementById('efb-dlg-open-' + fid);
  try { document.dispatchEvent(new CustomEvent('efb:popup-open', { detail: { formId: Number(fid), dialog: dlg } })); } catch (e) {}
  try { window.dispatchEvent(new Event('resize')); } catch (e) {}
  return true;
}
function efb_popup_close_efb(fid) {
  const dlg = document.getElementById('efb-dlg-' + fid);
  if (!dlg) return;
  if (typeof dlg.close === 'function') dlg.close(); else dlg.removeAttribute('open');
}
function efb_popup_boot_efb(root) {
  (root || document).querySelectorAll('[data-efb-popup]').forEach((btn) => {
    if (btn._efbPopup) return;
    btn._efbPopup = true;
    const fid = btn.getAttribute('data-efb-popup');
    btn.addEventListener('click', () => efb_popup_open_efb(fid, btn));
    const dlg = document.getElementById('efb-dlg-' + fid);
    if (dlg) {
      dlg.addEventListener('close', () => { const o = dlg._efbOpener || btn; try { o.focus(); } catch (e) {} });
      dlg.addEventListener('click', (e) => { if (e.target === dlg) efb_popup_close_efb(fid); });
      const x = dlg.querySelector('[data-efb-popup-close]');
      if (x) x.addEventListener('click', () => efb_popup_close_efb(fid));
    }
    const delay = Number(btn.getAttribute('data-efb-popup-delay')) || 0;
    if (delay > 0) {
      let seen = false;
      try { seen = sessionStorage.getItem('efb_popup_' + fid) === '1'; } catch (e) {}
      if (!seen) setTimeout(() => { if (efb_popup_open_efb(fid, btn)) { try { sessionStorage.setItem('efb_popup_' + fid, '1'); } catch (e) {} } }, delay * 1000);
    }
  });
}
if (typeof document !== 'undefined' && typeof window !== 'undefined' && !window.EFB_GATE_NO_BOOT) {
  const efbF11Boot = () => { efb_gate_boot_efb(document); efb_popup_boot_efb(document); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', efbF11Boot);
  else setTimeout(efbF11Boot, 0);
}

/* ---------------------------------------------------------------------------------------------
 * F11-E/F: "Save and continue later" and partial answers (docs/feature-gaps/F11-form-limits-resume.fa.md
 * §5, §6). The server keeps only what it may (Emsfb\Drafts::clean_rows); the browser leaves out the
 * same types first. Routes: forms/draft/save|load|email|discard, forms/partial/save (runtime token,
 * nonce, same origin). The resume token lives in sessionStorage of this tab only.
 * ------------------------------------------------------------------------------------------- */
const EFB_DRAFT_NOT_SAVED = ['password', 'file', 'dadfile', 'esign', 'audio_recorder', 'video_recorder', 'screen_recorder', 'image', 'document', 'media', 'allformat', 'zip',
  'stripe', 'paypal', 'persiaPay', 'persiapay', 'zarinPal', 'payment', 'captcha_v2', 'prcfld', 'ttlprc', 'html', 'heading', 'link', 'step', 'steps', 'w_link'];
function efb_f11_text_efb(k, fb) {
  try { const t = (typeof ajax_object_efm !== 'undefined' && ajax_object_efm && ajax_object_efm.text) ? ajax_object_efm.text : null; if (t && t[k]) return t[k]; } catch (e) {}
  return fb;
}
function efb_f11_flag_efb(k) {
  try { return !!(typeof ajax_object_efm !== 'undefined' && ajax_object_efm.f11 && ajax_object_efm.f11[k]); } catch (e) { return false; }
}
function efb_f11_row0_efb(fid) {
  try { const s = get_structure_by_form_id_efb(Number(fid)); return Array.isArray(s) && s[0] ? s[0] : null; } catch (e) { return null; }
}
const efb_f11_on_efb = (v) => ['1', 'true', 1, true].includes(v);
/* The answers of one form that a draft may keep (no password, file, signature, payment, …). */
function efb_draft_rows_efb(fid) {
  if (typeof sendBack_emsFormBuilder_pub === 'undefined' || !Array.isArray(sendBack_emsFormBuilder_pub)) return [];
  const gw = (typeof efb_var !== 'undefined' && efb_var && efb_var.pay && Array.isArray(efb_var.pay.gateways)) ? efb_var.pay.gateways : [];
  return sendBack_emsFormBuilder_pub.filter((r) => r && typeof r === 'object' && Number(r.form_id) === Number(fid)
    && !EFB_DRAFT_NOT_SAVED.includes(String(r.type || '')) && !gw.includes(String(r.type || ''))
    && !(r.id_ob && document.getElementById(r.id_ob) && document.getElementById(r.id_ob).closest('[data-efb-logic-hidden="1"], .efb-logic-hidden')))
    .map((r) => { const o = {}; ['id_', 'id_ob', 'name', 'type', 'value', 'amount', 'form_id', 'qty', 'ids'].forEach((k) => { if (r[k] !== undefined) o[k] = r[k]; }); return o; });
}
function efb_f11_step_efb(fid) {
  const b = document.getElementById('body_efb_' + fid);
  return b ? (Number(b.dataset.currentstep) || 1) : 1;
}
async function efb_f11_post_efb(route, fid, body, keepalive) {
  const rest = (typeof efb_var !== 'undefined' && efb_var.rest_url) ? efb_var.rest_url : '/wp-json/';
  let rt = null;
  try { rt = window.EFBRuntime ? await window.EFBRuntime.prepare(Number(fid)) : null; } catch (e) { const err = new Error(e.message || ''); err.code = e.code; throw err; }
  if (!rt || !rt.runtime_token) { const err = new Error(efb_f11_text_efb('nonceExpired', 'Your session has expired. Please refresh the page and try again.')); err.code = 'efb_context_expired'; throw err; }
  const r = await fetch(rest + 'Emsfb/v1/' + route, {
    method: 'POST', credentials: 'same-origin', cache: 'no-store', keepalive: !!keepalive,
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rt.nonce, 'sid': rt.sid, 'form-id': String(fid) },
    body: JSON.stringify(Object.assign({ form_id: Number(fid), runtime_token: rt.runtime_token }, body)),
  });
  let j = null;
  try { j = await r.json(); } catch (e) { j = null; }
  if (!r.ok) { const err = new Error((j && (j.message || (j.data && j.data.m))) || efb_f11_text_efb('draftFailed', 'Your answers could not be saved. Please try again.')); err.code = j && j.code; err.status = r.status; throw err; }
  return j;
}
function efb_f11_page_url_efb() {
  try { const u = new URL(location.href); u.hash = ''; u.searchParams.delete('efb_resume'); return u.toString(); } catch (e) { return location.href.split('#')[0]; }
}
function efb_draft_status_efb(fid, msg, kind) {
  const zone = document.querySelector('.efb-draft-zone[data-formid="' + fid + '"]');
  if (!zone) return;
  let st = zone.querySelector('.efb-draft-status');
  if (!st) { st = document.createElement('p'); st.className = 'efb efb-draft-status fs-7 mb-0 mx-2'; st.setAttribute('role', 'status'); st.setAttribute('aria-live', 'polite'); zone.appendChild(st); }
  st.textContent = msg || '';
  st.classList.toggle('text-danger', kind === 'error');
}
async function efb_draft_save_efb(fid, btn) {
  fid = Number(fid);
  if (btn) { btn.disabled = true; btn.setAttribute('aria-busy', 'true'); }
  try {
    let token = '';
    try { token = sessionStorage.getItem('efb_draft_' + fid) || ''; } catch (e) {}
    const res = await efb_f11_post_efb('forms/draft/save', fid, { rows: efb_draft_rows_efb(fid), step: efb_f11_step_efb(fid), resume_token: token, url: efb_f11_page_url_efb() });
    try { if (res.resume_token) sessionStorage.setItem('efb_draft_' + fid, res.resume_token); } catch (e) {}
    efb_draft_status_efb(fid, '', '');
    efb_draft_dialog_efb(fid, res);
    return res;
  } catch (e) {
    efb_draft_status_efb(fid, e.message || efb_f11_text_efb('draftFailed', 'Your answers could not be saved. Please try again.'), 'error');
    return null;
  } finally {
    if (btn) { btn.disabled = false; btn.removeAttribute('aria-busy'); }
  }
}
/* The dialog after saving: link + copy, until when, e-mail it, what is not saved. */
function efb_draft_dialog_efb(fid, res) {
  const id = 'efb-draft-dlg-' + fid;
  let dlg = document.getElementById(id);
  const t = (k, fb) => efb_gate_escape_efb(efb_f11_text_efb(k, fb));
  const rtl = (typeof efb_var !== 'undefined' && efb_var.rtl) ? ' dir="rtl"' : '';
  const row0 = efb_f11_row0_efb(fid) || {};
  const html = '<div class="efb efb-popup-head"><h2 class="efb efb-popup-title" id="' + id + '-title">' + t('draftSaved', 'Your answers are saved.') + '</h2>'
    + '<button type="button" class="efb efb-popup-close" aria-label="' + t('popupClose', 'Close') + '" data-efb-draft-close="' + fid + '"><span aria-hidden="true">&times;</span></button></div>'
    + '<div class="efb efb-popup-body">'
    + '<label class="efb form-label" for="' + id + '-url">' + t('draftCopy', 'Copy link') + '</label>'
    + '<div class="efb efb-draft-link d-flex gap-2"><input type="text" readonly dir="ltr" class="efb form-control" id="' + id + '-url" value="' + efb_gate_escape_efb(res.url || '') + '">'
    + '<button type="button" class="efb btn btn-primary" data-efb-draft-copy="' + fid + '"><i class="efb bi-clipboard me-1" aria-hidden="true"></i>' + t('draftCopy', 'Copy link') + '</button></div>'
    + '<p class="efb fs-7 mt-2 mb-1">' + efb_gate_escape_efb(efb_f11_text_efb('draftExpires', 'This link works until %s.').replace('%s', res.expires_text || '')) + '</p>'
    + '<p class="efb fs-7 text-muted mb-3">' + t('draftKeepSafe', 'Anyone with this link can see your saved answers. Keep it private.') + '</p>'
    + '<label class="efb form-label" for="' + id + '-email">' + t('draftEmail', 'Email address') + '</label>'
    + '<div class="efb efb-draft-link d-flex gap-2"><input type="email" dir="ltr" autocomplete="email" class="efb form-control" id="' + id + '-email" value="">'
    + '<button type="button" class="efb btn btn-outline-primary" data-efb-draft-email="' + fid + '"><i class="efb bi-envelope me-1" aria-hidden="true"></i>' + t('draftSendLink', 'Send link') + '</button></div>'
    + '<p class="efb fs-7 mt-1 mb-2" id="' + id + '-msg" role="status" aria-live="polite"></p>'
    + '<p class="efb fs-7 text-muted mb-0"><i class="efb bi-info-circle me-1" aria-hidden="true"></i>' + t('draftNotSaved', 'Passwords, files, recordings, signatures and payment details are not saved.') + '</p>'
    + '</div>';
  if (!dlg) {
    dlg = document.createElement('dialog');
    dlg.id = id;
    dlg.className = 'efb efb-popup efb-draft-dialog';
    dlg.setAttribute('aria-labelledby', id + '-title');
    if (rtl) dlg.setAttribute('dir', 'rtl');
    document.body.appendChild(dlg);
    dlg.addEventListener('click', async (e) => {
      const tgt = e.target && e.target.closest ? e.target : null;
      if (!tgt) return;
      if (tgt === dlg || tgt.closest('[data-efb-draft-close]')) { dlg.close(); return; }
      if (tgt.closest('[data-efb-draft-copy]')) {
        const inp = document.getElementById(id + '-url');
        try { await navigator.clipboard.writeText(inp.value); } catch (x) { try { inp.select(); document.execCommand('copy'); } catch (y) {} }
        document.getElementById(id + '-msg').textContent = efb_f11_text_efb('copiedClipboard', 'Copied');
        return;
      }
      if (tgt.closest('[data-efb-draft-email]')) {
        const btn = tgt.closest('[data-efb-draft-email]');
        const msg = document.getElementById(id + '-msg');
        const em = document.getElementById(id + '-email');
        btn.disabled = true;
        try {
          let token = '';
          try { token = sessionStorage.getItem('efb_draft_' + fid) || ''; } catch (x) {}
          const r = await efb_f11_post_efb('forms/draft/email', fid, { resume_token: token, email: em.value.trim(), url: efb_f11_page_url_efb() });
          msg.textContent = r && r.success ? efb_f11_text_efb('draftLinkSent', 'The link was sent.') : efb_f11_text_efb('draftFailed', 'Your answers could not be saved. Please try again.');
          em.removeAttribute('aria-invalid');
        } catch (x) { msg.textContent = x.message || ''; em.setAttribute('aria-invalid', 'true'); }
        btn.disabled = false;
      }
    });
    const opener = document.getElementById('efb-draft-save-' + fid);
    dlg.addEventListener('close', () => { try { (opener || document.body).focus(); } catch (e) {} });
  }
  dlg.innerHTML = html;
  // The e-mail field starts with the address typed into the form's own e-mail field (email_to).
  try {
    const rows = efb_draft_rows_efb(fid);
    const e = rows.find((r) => r.id_ && row0.email_to && r.id_ === row0.email_to) || rows.find((r) => r.type === 'email');
    if (e && e.value) document.getElementById(id + '-email').value = String(e.value);
  } catch (e) {}
  if (typeof dlg.showModal === 'function') { if (!dlg.open) dlg.showModal(); } else dlg.setAttribute('open', '');
  const first = document.getElementById(id + '-url');
  try { first.focus(); first.select(); } catch (e) {}
  return dlg;
}
/* Banner after a resume link: restored (and what to enter again), or the link did not work. */
function efb_draft_banner_efb(fid, text, kind, startOver) {
  const body = document.getElementById('body_efb_' + fid);
  if (!body) return null;
  const old = document.getElementById('efb-draft-banner-' + fid);
  if (old) old.remove();
  const div = document.createElement('div');
  div.id = 'efb-draft-banner-' + fid;
  div.className = 'efb alert ' + (kind === 'error' ? 'alert-warning' : 'alert-info') + ' d-flex flex-wrap align-items-center gap-2 mx-3 my-2 efb-draft-banner';
  div.setAttribute('role', 'status');
  div.innerHTML = '<span class="efb">' + efb_gate_escape_efb(text) + '</span>' + (startOver ? ' <button type="button" class="efb btn btn-sm btn-outline-secondary" data-efb-draft-startover="' + fid + '">' + efb_gate_escape_efb(efb_f11_text_efb('draftStartOver', 'Start over')) + '</button>' : '');
  body.parentNode.insertBefore(div, body);
  const so = div.querySelector('[data-efb-draft-startover]');
  if (so) so.addEventListener('click', async () => {
    let token = '';
    try { token = sessionStorage.getItem('efb_draft_' + fid) || ''; sessionStorage.removeItem('efb_draft_' + fid); } catch (e) {}
    try { if (token) await efb_f11_post_efb('forms/draft/discard', fid, { resume_token: token }); } catch (e) {}
    try { location.replace(efb_f11_page_url_efb()); } catch (e) { location.reload(); }
  });
  return div;
}
async function efb_draft_resume_efb() {
  let token = '';
  try { token = new URL(location.href).searchParams.get('efb_resume') || ''; } catch (e) { token = ''; }
  if (!/^[a-f0-9]{64}$/.test(token)) return false;
  // The link leaves the address bar at once (shared screens, history, Referer).
  try { const u = new URL(location.href); u.searchParams.delete('efb_resume'); history.replaceState(history.state, '', u.toString()); } catch (e) {}
  const forms = (typeof valj_efb_new !== 'undefined' && Array.isArray(valj_efb_new)) ? valj_efb_new : [];
  for (const f of forms) {
    const fid = Number(f.id);
    const row0 = f.form_structer && f.form_structer[0];
    if (!row0 || !efb_f11_on_efb(row0.draft_on)) continue;
    const rest = (typeof efb_var !== 'undefined' && efb_var.rest_url) ? efb_var.rest_url : '/wp-json/';
    let rt = null;
    try { rt = window.EFBRuntime ? await window.EFBRuntime.prepare(fid) : null; } catch (e) { rt = null; }
    let res = null;
    try {
      const r = await fetch(rest + 'Emsfb/v1/forms/draft/load', { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rt ? rt.nonce : (efb_var.nonce || ''), 'sid': rt ? rt.sid : '' }, body: JSON.stringify({ form_id: fid, resume_token: token }) });
      res = r.ok ? await r.json() : null;
    } catch (e) { res = null; }
    if (!res || !res.success) { efb_draft_banner_efb(fid, efb_f11_text_efb('draftInvalid', 'This link has expired or is not valid.'), 'error', false); continue; }
    try { sessionStorage.setItem('efb_draft_' + fid, token); } catch (e) {}
    const rows = (res.rows || []).map((r) => Object.assign({}, r, { form_id: fid }));
    await efb_apply_saved_rows_efb(fid, rows);
    // To the saved step: Next as the visitor would press it (every step is checked).
    for (let i = 1; i < Number(res.step || 1); i++) {
      const body = document.getElementById('body_efb_' + fid);
      const next = body ? body.querySelector('#next_efb') : null;
      if (!next || next.closest('.d-none')) break;
      const before = efb_f11_step_efb(fid);
      try { await btn_navigate_handle_efb(String(fid), row0.type, 'next_efb', next); } catch (e) { break; }
      await new Promise((ok) => setTimeout(ok, 350));
      if (efb_f11_step_efb(fid) === before) break;
    }
    efb_draft_banner_efb(fid, efb_f11_text_efb('draftRestored', 'Your saved answers were restored. Enter files and passwords again.'), 'info', true);
    return true;
  }
  return false;
}

/* F: partial answers, only with the visitor's consent. */
const EFB_PARTIAL = {};
function efb_partial_contact_ok_efb(rows) {
  return rows.some((r) => (r.type === 'email' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(r.value || '').trim()))
    || (['mobile', 'tel'].includes(r.type) && String(efb_ascii_digits_efb(String(r.value || ''))).replace(/\D+/g, '').length >= 7));
}
async function efb_partial_send_efb(fid, keepalive) {
  const st = EFB_PARTIAL[fid];
  if (!st || st.stopped) return false;
  if (st.mode === 'checkbox') { const cb = document.getElementById('efb-partial-consent-' + fid); if (!cb || !cb.checked) return false; }
  const rows = efb_draft_rows_efb(fid);
  if (!rows.length || (st.needContact && !efb_partial_contact_ok_efb(rows))) return false;
  const sig = JSON.stringify(rows);
  if (sig === st.last) return false;
  st.last = sig;
  st.at = Date.now();
  try { await efb_f11_post_efb('forms/partial/save', fid, { rows, step: efb_f11_step_efb(fid), consent: 1 }, keepalive); return true; } catch (e) { st.last = ''; return false; }
}
function efb_partial_stop_efb(fid) { if (EFB_PARTIAL[Number(fid)]) EFB_PARTIAL[Number(fid)].stopped = true; }
function efb_partial_boot_efb(fid, row0) {
  fid = Number(fid);
  if (!efb_f11_on_efb(row0.partial_on) || !efb_f11_flag_efb('partials') || EFB_PARTIAL[fid]) return;
  const body = document.getElementById('body_efb_' + fid);
  const form = body ? body.querySelector('form') : null;
  if (!form) return;
  const mode = row0.partial_consent === 'notice' ? 'notice' : 'checkbox';
  // Not part of the answers: no emsFormBuilder_v class, no data-vid (F02 §1.3).
  const html = mode === 'notice'
    ? '<p class="efb efb-partial-notice fs-7 text-muted mx-3 my-2" role="note">' + efb_gate_escape_efb(efb_f11_text_efb('partialNotice', 'Your answers are saved as you type so the site owner can follow up.')) + '</p>'
    : '<div class="efb efb-partial-consent form-check mx-3 my-2"><input type="checkbox" class="efb form-check-input" id="efb-partial-consent-' + fid + '"><label class="efb form-check-label fs-7" for="efb-partial-consent-' + fid + '">' + efb_gate_escape_efb(efb_f11_text_efb('partialConsent', 'Save my answers before I send them.')) + '</label></div>';
  form.insertAdjacentHTML('afterbegin', html);
  const st = EFB_PARTIAL[fid] = { mode, needContact: row0.partial_need_contact !== '0', last: '', at: 0, timer: null, stopped: false };
  const later = () => {
    clearTimeout(st.timer);
    const wait = Math.max(3000, 10000 - (Date.now() - st.at));
    st.timer = setTimeout(() => efb_partial_send_efb(fid, false), wait);
  };
  body.addEventListener('input', later, true);
  body.addEventListener('change', (e) => { if (e.target && e.target.id === 'efb-partial-consent-' + fid) { if (e.target.checked) later(); return; } later(); }, true);
  body.addEventListener('click', (e) => { if (e.target && e.target.closest && e.target.closest('#next_efb')) setTimeout(() => efb_partial_send_efb(fid, false), 400); }, true);
  window.addEventListener('pagehide', () => { clearTimeout(st.timer); efb_partial_send_efb(fid, true); });
}
async function efb_f11_after_init_efb() {
  if (typeof document === 'undefined') return;
  document.querySelectorAll('.efb-draft-save[data-formid]').forEach((b) => {
    if (b._efbDraft || b.disabled) return;
    b._efbDraft = true;
    b.addEventListener('click', () => efb_draft_save_efb(b.getAttribute('data-formid'), b));
  });
  const forms = (typeof valj_efb_new !== 'undefined' && Array.isArray(valj_efb_new)) ? valj_efb_new : [];
  forms.forEach((f) => { const r0 = f.form_structer && f.form_structer[0]; if (r0) efb_partial_boot_efb(f.id, r0); });
  await efb_draft_resume_efb();
}
