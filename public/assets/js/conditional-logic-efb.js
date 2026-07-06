/**
 * Easy Form Builder conditional-logic runtime.
 *
 * The pure evaluator is shared by browser behavior and Node regression tests.
 * Browser state is isolated by form id so multiple forms can coexist safely.
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) {
    root.EFBConditionalLogic = api;
    root.efb_logic_runtime = api;
  }
})(typeof window !== 'undefined' ? window : null, function (root) {
  'use strict';

  var contexts = {};
  var debounceTimers = {};

  /* ── Show/hide animation layer ─────────────────────────────────────────────
   * applyVisualState() re-applies d-none declaratively on every evaluation, so
   * animation is done by reconciliation: only wrappers whose visibility really
   * changed get a fade/slide effect. Disabled automatically outside a real
   * browser (Node regression tests build a minimal fake DOM without head/rAF)
   * and on the very first paint of a form (initial page-load state). */
  var ANIM_MS = 280;

  function animSupported() {
    return !!(root && root.document && root.document.head && typeof root.requestAnimationFrame === 'function');
  }

  function injectAnimStyles() {
    if (!animSupported() || root.document.getElementById('efb-logic-anim-css')) return;
    var st = root.document.createElement('style');
    st.id = 'efb-logic-anim-css';
    st.textContent =
      '.efb-anim-show{animation:efbLogicFieldIn .28s ease-out both;}' +
      '.efb-anim-hide{animation:efbLogicFieldOut .28s ease-in both;pointer-events:none;}' +
      '@keyframes efbLogicFieldIn{from{opacity:0;transform:translateY(-10px) scale(.98);}to{opacity:1;transform:none;}}' +
      '@keyframes efbLogicFieldOut{from{opacity:1;transform:none;}to{opacity:0;transform:translateY(-10px) scale(.98);}}' +
      '@media (prefers-reduced-motion:reduce){.efb-anim-show{animation:none;}.efb-anim-hide{animation:none;display:none!important;}}';
    root.document.head.appendChild(st);
  }

  /* Reconcile a wrapper's visibility with the evaluation result, animating
   * real transitions. A wrapper mid fade-out (efb-anim-hide, d-none deferred
   * until the animation ends) counts as hidden so repeated evaluations do not
   * cut the effect short. */
  function applyWrapperVisibility(context, wrapper, hide) {
    wrapper.setAttribute('aria-hidden', hide ? 'true' : 'false');
    var hiddenNow = wrapper.classList.contains('d-none') || wrapper.classList.contains('efb-anim-hide');
    if (hide === hiddenNow) return;
    if (!context.animReady || !animSupported()) {
      wrapper.classList.toggle('d-none', hide);
      return;
    }
    injectAnimStyles();
    if (wrapper._efbAnimTimer) { clearTimeout(wrapper._efbAnimTimer); wrapper._efbAnimTimer = null; }
    if (hide) {
      /* keep the element visible for the fade-out; d-none lands when it ends */
      wrapper.classList.remove('d-none', 'efb-anim-show');
      wrapper.classList.add('efb-anim-hide');
      wrapper._efbAnimTimer = setTimeout(function () {
        wrapper.classList.remove('efb-anim-hide');
        wrapper.classList.add('d-none');
        wrapper._efbAnimTimer = null;
      }, ANIM_MS + 40);
    } else {
      wrapper.classList.remove('d-none', 'efb-anim-hide');
      wrapper.classList.add('efb-anim-show');
      wrapper._efbAnimTimer = setTimeout(function () {
        wrapper.classList.remove('efb-anim-show');
        wrapper._efbAnimTimer = null;
      }, ANIM_MS + 40);
    }
  }
  var STRUCTURAL = ['form', 'step', 'option', 'submit', 'r_matrix', 'buttonnav'];
  var SELECT_TYPES = ['select', 'payselect', 'conturylist', 'stateprovince', 'statepro', 'country', 'city', 'citylist'];
  var RADIO_TYPES = ['radio', 'payradio', 'imgradio', 'chlradio'];
  var CHECKBOX_TYPES = ['checkbox', 'paycheckbox', 'chlcheckbox'];
  var MULTISELECT_TYPES = ['multiselect', 'paymultiselect'];
  var PAYMENT_TYPES = ['payment', 'stripe', 'paypal', 'persiapay'];

  function bool(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
  }

  function clone(value) {
    return JSON.parse(JSON.stringify(value));
  }

  function globalForms() {
    if (typeof valj_efb_new !== 'undefined' && Array.isArray(valj_efb_new)) return valj_efb_new;
    return root && Array.isArray(root.valj_efb_new) ? root.valj_efb_new : [];
  }

  function globalSendBack() {
    if (typeof sendBack_emsFormBuilder_pub !== 'undefined' && Array.isArray(sendBack_emsFormBuilder_pub)) {
      return sendBack_emsFormBuilder_pub;
    }
    return root && Array.isArray(root.sendBack_emsFormBuilder_pub) ? root.sendBack_emsFormBuilder_pub : [];
  }

  function globalFiles() {
    if (typeof files_emsFormBuilder !== 'undefined' && Array.isArray(files_emsFormBuilder)) return files_emsFormBuilder;
    return root && Array.isArray(root.files_emsFormBuilder) ? root.files_emsFormBuilder : [];
  }

  function currentFormId() {
    if (typeof form_ID_emsFormBuilder !== 'undefined') return form_ID_emsFormBuilder;
    return root ? root.form_ID_emsFormBuilder : 0;
  }

  function emptyResult() {
    return {
      is_conditional: false,
      stabilized: true,
      hidden_fields: [],
      shown_fields: [],
      required_fields: [],
      optional_fields: [],
      disabled_fields: [],
      enabled_fields: [],
      hidden_steps: [],
      shown_steps: [],
      ignored_fields: [],
      matched_rules: [],
      set_values: {},
      cleared_fields: [],
      values_map: {},
      messages: [],
      jumps: []
    };
  }

  function indexStructure(structure) {
    var index = { fields: {}, steps: {}, options: {} };
    (structure || []).forEach(function (item) {
      if (!item || !item.id_) return;
      var id = String(item.id_);
      var type = String(item.type || '').toLowerCase();
      if (type === 'step') {
        index.steps[id] = item;
      } else if (type === 'option') {
        var parent = String(item.parent || '');
        if (!index.options[parent]) index.options[parent] = {};
        index.options[parent][id] = item.value != null ? item.value : id;
      } else if (STRUCTURAL.indexOf(type) === -1) {
        index.fields[id] = item;
      }
    });
    return index;
  }

  function isEnabled(rule) {
    return rule && rule.enabled !== false && rule.enabled !== 0 && rule.enabled !== '0';
  }

  function sortedRules(structure) {
    var rules = structure && structure[0] && Array.isArray(structure[0].logic_rules)
      ? structure[0].logic_rules
      : [];
    var normalized = rules.map(function (rule, position) {
      var copy = Object.assign({}, rule, { _position: position });
      return copy;
    }).filter(function (rule) {
      return isEnabled(rule) &&
        rule.conditions && Array.isArray(rule.conditions.items) && rule.conditions.items.length > 0 &&
        Array.isArray(rule.actions) && rule.actions.length > 0;
    }).sort(function (a, b) {
      var priority = Number(a.priority || 10) - Number(b.priority || 10);
      return priority || a._position - b._position;
    });
    if (normalized.length) return normalized;
    return legacyRules(structure);
  }

  function legacyRules(structure) {
    var header = structure && structure[0] ? structure[0] : {};
    var legacy = Array.isArray(header.conditions) ? header.conditions : [];
    var index = indexStructure(structure);
    return legacy.map(function (condition, position) {
      if (!condition || !bool(condition.state)) return null;
      var items = (condition.condition || []).filter(function (item) {
        return item && item.one && item.two;
      }).map(function (item) {
        return {
          type: 'condition',
          source: 'field',
          field_id: item.one,
          compare: item.term || 'is',
          value: item.two
        };
      });
      if (!items.length || !condition.id_) return null;
      var target = String(condition.id_);
      var isStep = !!index.steps[target];
      var show = condition.show !== false && condition.show !== 0 && condition.show !== '0';
      return {
        id: 'legacy_' + position,
        enabled: true,
        priority: 10 + position,
        _position: position,
        conditions: { type: 'group', operator: 'AND', items: items },
        actions: [{
          type: isStep ? (show ? 'show_step' : 'hide_step') : (show ? 'show_field' : 'hide_field'),
          target: target
        }]
      };
    }).filter(Boolean);
  }

  function hasActiveRules(structure) {
    return sortedRules(structure).length > 0;
  }

  function rowsForField(rows, fieldId) {
    return (rows || []).filter(function (row) {
      return row && String(row.id_ || '') === String(fieldId);
    });
  }

  function buildValuesMap(structure, rows) {
    var index = indexStructure(structure);
    var values = {};
    (rows || []).forEach(function (row) {
      if (!row || !row.id_) return;
      var fieldId = String(row.id_);
      var type = String(row.type || (index.fields[fieldId] && index.fields[fieldId].type) || '').toLowerCase();

      if (CHECKBOX_TYPES.indexOf(type) !== -1) {
        if (!Array.isArray(values[fieldId])) values[fieldId] = [];
        var checked = row.id_ob != null && row.id_ob !== '' ? row.id_ob : row.value;
        if (checked != null && checked !== '' && values[fieldId].indexOf(checked) === -1) {
          values[fieldId].push(checked);
        }
        return;
      }

      if (type === 'yesno') {
        var yesNo = String(row.id_ob != null ? row.id_ob : (row.value || '')).toLowerCase();
        if (yesNo === fieldId.toLowerCase() + '_1' || yesNo === 'yes' || yesNo === '1') values[fieldId] = 'yes';
        else if (yesNo === fieldId.toLowerCase() + '_2' || yesNo === 'no' || yesNo === '0') values[fieldId] = 'no';
        else values[fieldId] = '';
        return;
      }

      if (RADIO_TYPES.indexOf(type) !== -1) {
        values[fieldId] = row.id_ob != null ? row.id_ob : (row.value || '');
        return;
      }

      if (MULTISELECT_TYPES.indexOf(type) !== -1) {
        var raw = row.value || '';
        values[fieldId] = Array.isArray(raw)
          ? raw.filter(notEmpty)
          : String(raw).split('@efb!').map(function (item) { return item.trim(); }).filter(notEmpty);
        return;
      }

      values[fieldId] = row.value != null ? row.value : '';
    });
    return values;
  }

  function notEmpty(value) {
    return value !== '' && value != null;
  }

  function normalizeValue(structure, fieldId, value) {
    var index = indexStructure(structure);
    var field = index.fields[fieldId] || {};
    var type = String(field.type || '').toLowerCase();

    if (type === 'yesno') {
      var yesNo = String(value == null ? '' : value).toLowerCase();
      if (yesNo === String(fieldId).toLowerCase() + '_1' || yesNo === '1') return 'yes';
      if (yesNo === String(fieldId).toLowerCase() + '_2' || yesNo === '0') return 'no';
      return yesNo === 'yes' || yesNo === 'no' ? yesNo : '';
    }

    if (SELECT_TYPES.indexOf(type) !== -1 || MULTISELECT_TYPES.indexOf(type) !== -1) {
      var input = Array.isArray(value) ? value : [value];
      var resolved = input.map(function (item) {
        return index.options[fieldId] && Object.prototype.hasOwnProperty.call(index.options[fieldId], item)
          ? index.options[fieldId][item]
          : item;
      });
      return MULTISELECT_TYPES.indexOf(type) !== -1 ? resolved : resolved[0];
    }

    if (CHECKBOX_TYPES.indexOf(type) !== -1) return Array.isArray(value) ? value : [value];
    return value;
  }

  /* Mirrors PHP is_numeric(): empty/whitespace-only strings are NOT numeric
   * (isFinite('') is true because '' coerces to 0, which diverges from the
   * server-side evaluator). Numeric operators must never match them. */
  function isNumericScalar(value) {
    var s = String(value == null ? '' : value).trim();
    return s !== '' && isFinite(s);
  }

  function compareScalar(value, expected, operator) {
    var scalar = String(value == null ? '' : value).trim();
    var expectedScalar = Array.isArray(expected) ? expected.join(',') : String(expected == null ? '' : expected).trim();
    var left = scalar.toLowerCase();
    var right = expectedScalar.toLowerCase();
    var range;
    var inside;

    switch (operator) {
      case 'is': return left === right;
      case 'is_not': return left !== right;
      case 'contains': return left.indexOf(right) !== -1;
      case 'not_contains': return left.indexOf(right) === -1;
      case 'starts_with': return left.indexOf(right) === 0;
      case 'ends_with': return right.length === 0 || left.slice(-right.length) === right;
      case 'gt': return isNumericScalar(scalar) && isNumericScalar(expectedScalar) && Number(scalar) > Number(expectedScalar);
      case 'gte': return isNumericScalar(scalar) && isNumericScalar(expectedScalar) && Number(scalar) >= Number(expectedScalar);
      case 'lt': return isNumericScalar(scalar) && isNumericScalar(expectedScalar) && Number(scalar) < Number(expectedScalar);
      case 'lte': return isNumericScalar(scalar) && isNumericScalar(expectedScalar) && Number(scalar) <= Number(expectedScalar);
      case 'between':
      case 'not_between':
        range = Array.isArray(expected) ? expected : expectedScalar.split(/\s*,\s*/);
        if (range.length < 2 || !isNumericScalar(scalar) || !isNumericScalar(range[0]) || !isNumericScalar(range[1])) return false;
        inside = Number(scalar) >= Number(range[0]) && Number(scalar) <= Number(range[1]);
        return operator === 'between' ? inside : !inside;
      case 'is_empty': return scalar === '';
      case 'is_not_empty': return scalar !== '';
      default: return false;
    }
  }

  function paymentState(fieldId, rows, values) {
    var paidStates = ['paid', 'succeeded', 'success', 'completed', 'complete', 'approved', 'captured', 'authorized'];
    var paid = false;
    var amount = null;
    (rows || []).forEach(function (row) {
      if (!row) return;
      var type = String(row.type || '').toLowerCase();
      if (String(row.id_ || '') !== String(fieldId) && PAYMENT_TYPES.indexOf(type) === -1) return;
      ['payment_status', 'status', 'state'].forEach(function (key) {
        if (row[key] != null && paidStates.indexOf(String(row[key]).toLowerCase()) !== -1) paid = true;
      });
      if (row.paymentIntent || row.transaction_id || row.refId || row.authority) paid = true;
      ['amount', 'total', 'price', 'paid_amount'].forEach(function (key) {
        if (isNumericScalar(row[key])) amount = Number(row[key]);
      });
    });
    if (amount == null && isNumericScalar(values[fieldId])) amount = Number(values[fieldId]);
    return { paid: paid, amount: amount };
  }

  function evaluateCondition(condition, values, structure, rows) {
    if (!condition || !condition.field_id) return false;
    var fieldId = String(condition.field_id);
    var operator = String(condition.compare || 'is');
    var expected = condition.value != null ? condition.value : '';

    if (['is_paid', 'is_not_paid', 'amount_eq', 'amount_gt', 'amount_lt'].indexOf(operator) !== -1) {
      var payment = paymentState(fieldId, rows, values);
      if (operator === 'is_paid') return payment.paid;
      if (operator === 'is_not_paid') return !payment.paid;
      if (payment.amount == null || !isNumericScalar(expected)) return false;
      if (operator === 'amount_eq') return Math.abs(payment.amount - Number(expected)) < 0.00001;
      if (operator === 'amount_gt') return payment.amount > Number(expected);
      return payment.amount < Number(expected);
    }

    var current = Object.prototype.hasOwnProperty.call(values, fieldId) ? values[fieldId] : '';
    expected = normalizeValue(structure, fieldId, expected);
    if (Array.isArray(current)) {
      if (operator === 'is') return current.indexOf(expected) !== -1;
      if (operator === 'is_not') return current.indexOf(expected) === -1;
      if (operator === 'is_empty') return current.length === 0;
      if (operator === 'is_not_empty') return current.length > 0;
      return compareScalar(current.join(' '), expected, operator);
    }
    return compareScalar(current, expected, operator);
  }

  function evaluateGroup(group, values, structure, rows) {
    var items = group && Array.isArray(group.items) ? group.items : [];
    if (!items.length) return false;
    var result = false;
    for (var i = 0; i < items.length; i++) {
      var item = items[i] || {};
      var isGroup = item.type === 'group' || Array.isArray(item.items);
      var matched = isGroup
        ? evaluateGroup(item, values, structure, rows)
        : evaluateCondition(item, values, structure, rows);
      if (i === 0) {
        result = matched;
        continue;
      }
      var connector = String(item.connector || group.operator || 'AND').toUpperCase() === 'OR' ? 'OR' : 'AND';
      result = connector === 'OR' ? (result || matched) : (result && matched);
    }
    return result;
  }

  function resolveActionValue(action, structure, values) {
    var value = action.value != null ? action.value : '';
    if (action.value_type !== 'autofill_key') return value;
    var mappings = structure[0] && Array.isArray(structure[0].autofill_conditions)
      ? structure[0].autofill_conditions
      : [];
    for (var i = 0; i < mappings.length; i++) {
      if (mappings[i] && mappings[i].source === value) {
        return Object.prototype.hasOwnProperty.call(values, mappings[i].id_) ? values[mappings[i].id_] : null;
      }
    }
    return null;
  }

  function numberFromCalculationValue(value) {
    if (Array.isArray(value)) {
      var total = 0;
      for (var i = 0; i < value.length; i++) {
        if (value[i] === '' || value[i] == null) continue;
        if (!isNumericScalar(value[i])) return { valid: false, value: 0 };
        total += Number(value[i]);
      }
      return { valid: true, value: total };
    }
    if (value === '' || value == null) return { valid: true, value: 0 };
    if (!isNumericScalar(value)) return { valid: false, value: 0 };
    return { valid: true, value: Number(value) };
  }

  function tokenizeCalculationFormula(formula) {
    var text = String(formula == null ? '' : formula).slice(0, 500);
    var tokens = [];
    var i = 0;
    while (i < text.length) {
      var ch = text.charAt(i);
      if (/\s/.test(ch)) {
        i++;
        continue;
      }
      if (ch === '{' || ch === '[') {
        var close = ch === '{' ? '}' : ']';
        var end = text.indexOf(close, i + 1);
        if (end === -1) return null;
        var ref = text.slice(i + 1, end).trim();
        if (!ref) return null;
        tokens.push({ type: 'ref', value: ref });
        i = end + 1;
        continue;
      }
      if (/[0-9.]/.test(ch)) {
        var start = i;
        var dots = 0;
        while (i < text.length && /[0-9.]/.test(text.charAt(i))) {
          if (text.charAt(i) === '.') dots++;
          i++;
        }
        var literal = text.slice(start, i);
        if (literal === '.' || dots > 1 || !isFinite(literal)) return null;
        tokens.push({ type: 'number', value: Number(literal) });
        continue;
      }
      if (/[A-Za-z_]/.test(ch)) {
        var refStart = i;
        while (i < text.length && /[A-Za-z0-9_-]/.test(text.charAt(i))) i++;
        tokens.push({ type: 'ref', value: text.slice(refStart, i) });
        continue;
      }
      if ('+-*/()'.indexOf(ch) !== -1) {
        tokens.push({ type: ch === '(' || ch === ')' ? 'paren' : 'op', value: ch });
        i++;
        continue;
      }
      return null;
    }
    return tokens;
  }

  function evaluateCalculationFormula(formula, structure, values) {
    var tokens = tokenizeCalculationFormula(formula);
    if (!tokens || !tokens.length) return null;
    var index = indexStructure(structure);
    var pos = 0;

    function invalid() { return { valid: false, value: 0 }; }

    function fieldNumber(fieldId) {
      fieldId = String(fieldId || '');
      if (!Object.prototype.hasOwnProperty.call(index.fields, fieldId)) return invalid();
      return numberFromCalculationValue(Object.prototype.hasOwnProperty.call(values, fieldId) ? values[fieldId] : '');
    }

    function parseExpression() {
      var left = parseTerm();
      while (left.valid && pos < tokens.length && tokens[pos].type === 'op' && (tokens[pos].value === '+' || tokens[pos].value === '-')) {
        var op = tokens[pos++].value;
        var right = parseTerm();
        if (!right.valid) return right;
        left.value = op === '+' ? left.value + right.value : left.value - right.value;
      }
      return left;
    }

    function parseTerm() {
      var left = parseFactor();
      while (left.valid && pos < tokens.length && tokens[pos].type === 'op' && (tokens[pos].value === '*' || tokens[pos].value === '/')) {
        var op = tokens[pos++].value;
        var right = parseFactor();
        if (!right.valid) return right;
        if (op === '/' && Math.abs(right.value) < 0.000000000001) return invalid();
        left.value = op === '*' ? left.value * right.value : left.value / right.value;
      }
      return left;
    }

    function parseFactor() {
      if (pos >= tokens.length) return invalid();
      var token = tokens[pos];
      if (token.type === 'op' && (token.value === '+' || token.value === '-')) {
        pos++;
        var unary = parseFactor();
        if (!unary.valid) return unary;
        return { valid: true, value: token.value === '-' ? -unary.value : unary.value };
      }
      if (token.type === 'number') {
        pos++;
        return { valid: true, value: token.value };
      }
      if (token.type === 'ref') {
        pos++;
        return fieldNumber(token.value);
      }
      if (token.type === 'paren' && token.value === '(') {
        pos++;
        var nested = parseExpression();
        if (!nested.valid || pos >= tokens.length || tokens[pos].type !== 'paren' || tokens[pos].value !== ')') return invalid();
        pos++;
        return nested;
      }
      return invalid();
    }

    var result = parseExpression();
    if (!result.valid || pos !== tokens.length || !isFinite(result.value)) return null;
    return result.value;
  }

  function formatCalculationResult(value, decimals) {
    var hasDecimals = decimals !== undefined && decimals !== null && decimals !== '';
    if (hasDecimals) {
      var places = Math.max(0, Math.min(6, parseInt(decimals, 10) || 0));
      return Number(value).toFixed(places);
    }
    return String(parseFloat(Number(value).toFixed(10)));
  }

  function resolveCalculationValue(action, structure, values) {
    var formula = String(action && action.value != null ? action.value : '').trim();
    if (!formula) return null;
    var value = evaluateCalculationFormula(formula, structure, values);
    if (value === null) return null;
    return formatCalculationResult(value, action.decimals);
  }

  function evaluatePass(structure, rows, rules, initialValues) {
    var index = indexStructure(structure);
    var values = clone(initialValues);
    var hidden = {};
    var shown = {};
    var required = {};
    var optional = {};
    var disabled = {};
    var enabled = {};
    var hiddenSteps = {};
    var shownSteps = {};
    var matchedRules = [];
    var messages = [];
    var jumps = [];

    Object.keys(index.fields).forEach(function (id) {
      if (bool(index.fields[id].hidden)) hidden[id] = true;
      if (bool(index.fields[id].disabled)) disabled[id] = true;
    });
    Object.keys(index.steps).forEach(function (id) {
      if (bool(index.steps[id].hidden)) hiddenSteps[id] = true;
    });

    rules.forEach(function (rule) {
      (rule.actions || []).forEach(function (action) {
        if (action.type === 'show_field' && action.target) hidden[action.target] = true;
        if (action.type === 'show_step' && action.target) hiddenSteps[action.target] = true;
      });
    });

    var stoppedFieldTargets = {};
    var stoppedStepTargets = {};
    function isStepActionType(type) {
      return type === 'show_step' || type === 'hide_step' || type === 'jump_to_step';
    }

    for (var r = 0; r < rules.length; r++) {
      var rule = rules[r];
      var ruleActions = rule.actions || [];

      /* stop_processing only freezes the SPECIFIC targets a stopping rule acted
         on (Task 3: "Multiple Rules on Same Target"), not every later rule in
         the form. A rule is skipped here only if ALL of its action targets were
         already frozen by an earlier matched stop_processing rule. */
      var blockedByStop = ruleActions.length > 0 && ruleActions.every(function (action) {
        if (!action.target) return false;
        return isStepActionType(action.type)
          ? Object.prototype.hasOwnProperty.call(stoppedStepTargets, action.target)
          : Object.prototype.hasOwnProperty.call(stoppedFieldTargets, action.target);
      });
      if (blockedByStop) continue;

      if (!evaluateGroup(rule.conditions, values, structure, rows)) continue;
      var ruleId = String(rule.id || ('rule_' + r));
      matchedRules.push(ruleId);

      ruleActions.forEach(function (action, actionIndex) {
        var target = action.target;
        if (!target) return;
        switch (action.type) {
          case 'show_field': delete hidden[target]; shown[target] = true; break;
          case 'hide_field': hidden[target] = true; delete shown[target]; break;
          case 'set_required': required[target] = true; delete optional[target]; break;
          case 'set_optional': optional[target] = true; delete required[target]; break;
          case 'enable_field': delete disabled[target]; enabled[target] = true; break;
          case 'disable_field': disabled[target] = true; delete enabled[target]; break;
          case 'show_step': delete hiddenSteps[target]; shownSteps[target] = true; break;
          case 'hide_step': hiddenSteps[target] = true; delete shownSteps[target]; break;
          case 'set_value':
            var setValue = resolveActionValue(action, structure, values);
            if (setValue != null) values[target] = normalizeValue(structure, target, setValue);
            break;
          case 'calculate':
            var calculatedValue = resolveCalculationValue(action, structure, values);
            if (calculatedValue != null) values[target] = normalizeValue(structure, target, calculatedValue);
            break;
          case 'clear_value': values[target] = ''; break;
          case 'show_message':
            messages.push({ key: ruleId + ':' + actionIndex, target: target, value: String(action.value || '') });
            break;
          case 'jump_to_step':
            jumps.push({ key: ruleId + ':' + actionIndex, target: target });
            break;
        }
      });

      if (bool(rule.stop_processing)) {
        ruleActions.forEach(function (action) {
          if (!action.target) return;
          if (isStepActionType(action.type)) stoppedStepTargets[action.target] = true;
          else stoppedFieldTargets[action.target] = true;
        });
      }
    }

    var ignored = Object.assign({}, hidden, disabled);
    Object.keys(index.fields).forEach(function (fieldId) {
      var fieldStep = String(index.fields[fieldId].step || '');
      Object.keys(hiddenSteps).forEach(function (stepId) {
        var stepNumber = String(index.steps[stepId] && index.steps[stepId].step != null ? index.steps[stepId].step : stepId);
        if (fieldStep && (fieldStep === String(stepId) || fieldStep === stepNumber)) ignored[fieldId] = true;
      });
    });

    return {
      is_conditional: true,
      stabilized: true,
      hidden_fields: Object.keys(hidden),
      shown_fields: Object.keys(shown),
      required_fields: Object.keys(required),
      optional_fields: Object.keys(optional),
      disabled_fields: Object.keys(disabled),
      enabled_fields: Object.keys(enabled),
      hidden_steps: Object.keys(hiddenSteps),
      shown_steps: Object.keys(shownSteps),
      ignored_fields: Object.keys(ignored),
      matched_rules: matchedRules,
      set_values: {},
      cleared_fields: [],
      values_map: values,
      messages: messages,
      jumps: jumps
    };
  }

  function evaluateDefinition(structure, rows) {
    var result = emptyResult();
    var rules = sortedRules(structure);
    if (!rules.length) return result;

    var originalValues = buildValuesMap(structure, rows);
    var values = clone(originalValues);
    var seen = {};
    result.is_conditional = true;

    for (var pass = 0; pass < 10; pass++) {
      var signature = JSON.stringify(values);
      if (seen[signature]) {
        result.stabilized = false;
        break;
      }
      seen[signature] = true;
      result = evaluatePass(structure, rows, rules, values);
      var nextSignature = JSON.stringify(result.values_map);
      if (nextSignature === signature) {
        result.stabilized = true;
        break;
      }
      values = clone(result.values_map);
    }

    result.set_values = {};
    result.cleared_fields = [];
    var ids = Object.keys(Object.assign({}, originalValues, result.values_map));
    ids.forEach(function (fieldId) {
      var before = Object.prototype.hasOwnProperty.call(originalValues, fieldId) ? originalValues[fieldId] : '';
      var after = Object.prototype.hasOwnProperty.call(result.values_map, fieldId) ? result.values_map[fieldId] : '';
      if (JSON.stringify(before) === JSON.stringify(after)) return;
      if (after == null || after === '' || (Array.isArray(after) && after.length === 0)) result.cleared_fields.push(fieldId);
      else result.set_values[fieldId] = after;
    });
    return result;
  }

  function getFormRecord(formId) {
    return globalForms().find(function (form) {
      return form && Number(form.id) === Number(formId);
    }) || null;
  }

  function init(formId) {
    if (!root) return null;
    var record = getFormRecord(formId);
    if (!record || !Array.isArray(record.form_structer) || !hasActiveRules(record.form_structer)) return null;
    var key = String(formId);
    contexts[key] = {
      formId: Number(formId),
      structure: record.form_structer,
      definition: clone(record.form_structer),
      lastJumpKeys: {},
      evaluating: false,
      animReady: false, /* first applyVisualState paints instantly; later ones animate */
      state: emptyResult()
    };
    evaluate(formId);
    return contexts[key];
  }

  function initAll() {
    globalForms().forEach(function (record) {
      if (record && record.id != null) init(record.id);
    });
  }

  function getContext(formId) {
    var key = String(formId);
    return contexts[key] || init(formId);
  }

  function inferRowFormId(row) {
    if (!row) return null;
    if (row.form_id != null && Number(row.form_id) > 0) return Number(row.form_id);
    var found = null;
    Object.keys(contexts).some(function (key) {
      if (contexts[key].definition.some(function (field) { return field && field.id_ === row.id_; })) {
        found = Number(key);
        return true;
      }
      return false;
    });
    return found;
  }

  function getRows(formId) {
    return globalSendBack().filter(function (row) {
      return row && Number(inferRowFormId(row)) === Number(formId);
    });
  }

  function sameFormRow(row, formId, fieldId) {
    return row && String(row.id_ || '') === String(fieldId) &&
      Number(inferRowFormId(row)) === Number(formId);
  }

  function removeRows(formId, fieldIds) {
    var sendBack = globalSendBack();
    var remove = {};
    fieldIds.forEach(function (id) { remove[id] = true; });
    var changed = false;
    for (var i = sendBack.length - 1; i >= 0; i--) {
      var row = sendBack[i];
      if (row && remove[row.id_] && Number(inferRowFormId(row)) === Number(formId)) {
        sendBack.splice(i, 1);
        changed = true;
      }
    }
    var files = globalFiles();
    if (Array.isArray(files)) {
      for (var f = files.length - 1; f >= 0; f--) {
        var file = files[f];
        if (file && remove[file.id_] && Number(file.form_id || formId) === Number(formId)) {
          files.splice(f, 1);
          changed = true;
        }
      }
    }
    return changed;
  }

  function rowsForValue(context, fieldId, value) {
    var index = indexStructure(context.definition);
    var field = index.fields[fieldId];
    if (!field) return [];
    var type = String(field.type || 'text');
    var lower = type.toLowerCase();
    var common = {
      id_: fieldId,
      name: field.name || '',
      amount: field.amount || 0,
      type: type,
      session: typeof sessionPub_emsFormBuilder !== 'undefined' ? sessionPub_emsFormBuilder : 'reciveFromClient',
      form_id: context.formId
    };

    if (lower === 'yesno') {
      var yes = String(value).toLowerCase() === 'yes';
      return [Object.assign({}, common, { id_ob: fieldId + (yes ? '_1' : '_2'), value: yes ? 'yes' : 'no' })];
    }
    if (RADIO_TYPES.indexOf(lower) !== -1) {
      return [Object.assign({}, common, { id_ob: String(value), value: String(value) })];
    }
    if (CHECKBOX_TYPES.indexOf(lower) !== -1) {
      return (Array.isArray(value) ? value : [value]).map(function (option) {
        return Object.assign({}, common, { id_ob: String(option), value: String(option) });
      });
    }
    if (MULTISELECT_TYPES.indexOf(lower) !== -1) {
      value = Array.isArray(value) ? value.join('@efb!') : String(value);
    }
    return [Object.assign({}, common, { value: Array.isArray(value) ? value.join(',') : String(value) })];
  }

  function syncResultData(context, result) {
    var sendBack = globalSendBack();
    var removeIds = result.ignored_fields.concat(result.cleared_fields, Object.keys(result.set_values));
    var changed = removeRows(context.formId, removeIds);
    Object.keys(result.set_values).forEach(function (fieldId) {
      if (result.ignored_fields.indexOf(fieldId) !== -1) return;
      rowsForValue(context, fieldId, result.set_values[fieldId]).forEach(function (row) {
        sendBack.push(row);
        changed = true;
      });
    });
    if (changed && root && root.localStorage) {
      try {
        root.localStorage.setItem('sendback', JSON.stringify(sendBack));
      } catch (error) {
        // Storage can be unavailable in privacy modes; in-memory submit remains valid.
      }
    }
    return changed;
  }

  function bodyFor(context) {
    return root.document.getElementById('body_efb_' + context.formId);
  }

  function cssEscape(value) {
    var text = String(value || '');
    if (root.CSS && typeof root.CSS.escape === 'function') return root.CSS.escape(text);
    return text.replace(/([ #;?%&,.+*~\':"!^$[\]()=>|\/@])/g, '\\$1');
  }

  function elementInForm(context, id) {
    var body = bodyFor(context);
    if (!body) return null;
    if (body.querySelector) {
      try {
        var local = body.querySelector('#' + cssEscape(id));
        if (local) return local;
      } catch (error) {}
    }
    var element = root.document.getElementById(id);
    return element && body.contains(element) ? element : null;
  }

  function clearFieldDom(context, fieldId) {
    var wrapper = elementInForm(context, fieldId);
    if (!wrapper) return;
    wrapper.querySelectorAll('input, textarea, select').forEach(function (input) {
      if (input.type === 'checkbox' || input.type === 'radio') input.checked = false;
      else if (input.type !== 'file') input.value = '';
    });
    wrapper.querySelectorAll('.active').forEach(function (element) { element.classList.remove('active'); });
    var multi = wrapper.querySelector('.efblist[data-vid="' + fieldId + '"]');
    if (multi && root.efb_var && root.efb_var.text) multi.textContent = root.efb_var.text.selectOption || '';
  }

  function setFieldDom(context, fieldId, value) {
    var wrapper = elementInForm(context, fieldId);
    if (!wrapper) return;
    var index = indexStructure(context.definition);
    var field = index.fields[fieldId] || {};
    var type = String(field.type || '').toLowerCase();
    var values = Array.isArray(value) ? value.map(String) : [String(value)];
    var input = elementInForm(context, fieldId + '_');

    if (type === 'yesno') {
      wrapper.querySelectorAll('input[type="radio"]').forEach(function (radioInput) { radioInput.checked = false; });
      wrapper.querySelectorAll('.active').forEach(function (active) { active.classList.remove('active'); });
      var yes = String(value).toLowerCase() === 'yes';
      var radio = elementInForm(context, fieldId + (yes ? '_1' : '_2'));
      if (radio) radio.checked = true;
      var button = elementInForm(context, fieldId + (yes ? '_b_1' : '_b_2'));
      if (button) button.classList.add('active');
      return;
    }
    if (RADIO_TYPES.indexOf(type) !== -1 || CHECKBOX_TYPES.indexOf(type) !== -1) {
      wrapper.querySelectorAll('input[type="radio"], input[type="checkbox"]').forEach(function (choice) {
        choice.checked = values.indexOf(String(choice.id)) !== -1 ||
          values.indexOf(String(choice.value)) !== -1 ||
          values.indexOf(String(choice.dataset.id || '')) !== -1;
      });
      return;
    }
    var select = wrapper.querySelector('select[data-vid="' + fieldId + '"]') || wrapper.querySelector('select');
    if (select) {
      Array.prototype.forEach.call(select.options, function (option) {
        option.selected = values.indexOf(String(option.value)) !== -1 || values.indexOf(String(option.text)) !== -1;
      });
      return;
    }
    if (input && input.type !== 'file') input.value = Array.isArray(value) ? value.join(',') : value;
  }

  function applyVisualState(context, result) {
    var index = indexStructure(context.definition);
    var hidden = {};
    var required = {};
    var optional = {};
    var disabled = {};
    var ignored = {};
    result.hidden_fields.forEach(function (id) { hidden[id] = true; });
    result.required_fields.forEach(function (id) { required[id] = true; });
    result.optional_fields.forEach(function (id) { optional[id] = true; });
    result.disabled_fields.forEach(function (id) { disabled[id] = true; });
    result.ignored_fields.forEach(function (id) { ignored[id] = true; });

    Object.keys(index.fields).forEach(function (fieldId) {
      var wrapper = elementInForm(context, fieldId);
      if (wrapper) {
        applyWrapperVisibility(context, wrapper, !!hidden[fieldId]);
      }

      var isRequired = ignored[fieldId]
        ? false
        : (required[fieldId] ? true : (optional[fieldId] ? false : bool(index.fields[fieldId].required)));
      var liveField = context.structure.find(function (field) { return field && field.id_ === fieldId; });
      if (liveField) liveField.required = isRequired;
      var requiredMarker = elementInForm(context, fieldId + '_req');
      if (requiredMarker) requiredMarker.style.display = isRequired ? '' : 'none';

      if (wrapper) {
        wrapper.querySelectorAll('input, textarea, select, button').forEach(function (control) {
          if (control.type !== 'submit') control.disabled = !!disabled[fieldId];
        });
        var multi = wrapper.querySelector('.efblist[data-vid="' + fieldId + '"]');
        if (multi) {
          multi.style.pointerEvents = disabled[fieldId] ? 'none' : '';
          multi.style.opacity = disabled[fieldId] ? '0.5' : '';
        }
      }
    });

    Object.keys(index.steps).forEach(function (stepId) {
      var step = index.steps[stepId];
      var hiddenStep = result.hidden_steps.indexOf(stepId) !== -1;
      var body = bodyFor(context);
      var fieldset = body && body.querySelector('[data-step="step-' + step.step + '-efb"]');
      if (fieldset) fieldset.dataset.logicHidden = hiddenStep ? '1' : '0';
      var nav = elementInForm(context, stepId);
      if (nav) {
        nav.style.opacity = hiddenStep ? '0.35' : '';
        nav.style.pointerEvents = hiddenStep ? 'none' : '';
      }
    });

    result.ignored_fields.forEach(function (fieldId) {
      if (result.disabled_fields.indexOf(fieldId) === -1) clearFieldDom(context, fieldId);
    });
    result.cleared_fields.forEach(function (fieldId) { clearFieldDom(context, fieldId); });
    Object.keys(result.set_values).forEach(function (fieldId) {
      if (result.ignored_fields.indexOf(fieldId) === -1) setFieldDom(context, fieldId, result.set_values[fieldId]);
    });

    var body = bodyFor(context);
    if (body) body.querySelectorAll('.efb-logic-inline-msg').forEach(function (message) { message.remove(); });
    result.messages.forEach(function (message) {
      if (!message.value) return;
      var wrapper = elementInForm(context, message.target);
      if (!wrapper) return;
      var element = root.document.createElement('div');
      element.className = 'efb efb-logic-inline-msg alert alert-info mt-1 py-1 small';
      element.dataset.logicMsg = message.key;
      element.textContent = message.value;
      wrapper.appendChild(element);
    });

    applyJumps(context, result.jumps);
    if (typeof root.updateStepButtonState_efb === 'function') root.updateStepButtonState_efb(context.formId);
    context.animReady = true;
  }

  function applyJumps(context, jumps) {
    var nextKeys = {};
    jumps.forEach(function (jump) {
      nextKeys[jump.key] = true;
      if (context.lastJumpKeys[jump.key]) return;
      jumpToStep(context, jump.target);
    });
    context.lastJumpKeys = nextKeys;
  }

  function jumpToStep(context, stepId) {
    var index = indexStructure(context.definition);
    var step = index.steps[stepId];
    var body = bodyFor(context);
    if (!step || !body) return;
    var target = Number(step.step);
    var current = Number(body.dataset.currentstep || 1);
    if (target === current) return;
    var targetFieldset = body.querySelector('[data-step="step-' + target + '-efb"]');
    if (!targetFieldset || targetFieldset.dataset.logicHidden === '1') return;
    var currentFieldset = body.querySelector('[data-step="step-' + current + '-efb"]');
    if (currentFieldset) currentFieldset.classList.add('d-none');
    targetFieldset.classList.remove('d-none');
    body.dataset.currentstep = target;

    /* jump_to_step can fire purely from a field change (debounced evaluate),
     * with no Next/Previous click involved at all — the click handlers in
     * core-efb.js are the only other code that manages #prev_efb visibility,
     * so without this the button is left stuck in whatever state it was in
     * before the jump (typically hidden, since forms start on step 1). */
    var prevBtn = body.querySelector('#prev_efb');
    if (prevBtn) prevBtn.classList.toggle('d-none', target <= 1);

    var max = Number(body.dataset.steps || 1);
    var progress = body.querySelector('.progress-bar-efb');
    if (progress) progress.style.width = (target / (max + 1)) * 100 + '%';
    for (var i = 1; i <= max; i++) {
      var icon = root.document.getElementById(i + '-f-step-efb-' + context.formId);
      if (icon) icon.classList.toggle('active', i === target);
    }
    var title = body.querySelector('#title_efb');
    var description = body.querySelector('#desc_efb');
    if (title) title.innerHTML = step.name || '';
    if (description) description.innerHTML = step.message || '';
    if (typeof root.smoothy_scroll_postion_efb === 'function') root.smoothy_scroll_postion_efb('body_efb_' + context.formId);
  }

  function evaluate(formId) {
    var context = getContext(formId);
    if (!context || context.evaluating) return context ? context.state : emptyResult();
    context.evaluating = true;
    try {
      var result = emptyResult();
      for (var pass = 0; pass < 5; pass++) {
        var before = JSON.stringify(getRows(context.formId));
        result = evaluateDefinition(context.definition, getRows(context.formId));
        syncResultData(context, result);
        var after = JSON.stringify(getRows(context.formId));
        if (before === after) break;
      }
      context.state = result;
      applyVisualState(context, result);
      return result;
    } finally {
      context.evaluating = false;
    }
  }

  function evaluateDebounced(formId, delay) {
    var key = String(formId);
    clearTimeout(debounceTimers[key]);
    debounceTimers[key] = setTimeout(function () { evaluate(formId); }, Number(delay || 120));
  }

  function hasSubmittedValue(rows, field) {
    var fieldRows = rowsForField(rows, field.id_);
    var type = String(field.type || '').toLowerCase();
    if (!fieldRows.length) return false;
    if (CHECKBOX_TYPES.indexOf(type) !== -1 || RADIO_TYPES.indexOf(type) !== -1 || type === 'yesno') {
      return fieldRows.some(function (row) { return row.id_ob != null && row.id_ob !== ''; });
    }
    if (['file', 'dadfile', 'esign', 'audio_recorder', 'video_recorder', 'screen_recorder'].indexOf(type) !== -1) {
      return fieldRows.some(function (row) { return row.value || row.url; });
    }
    return fieldRows.some(function (row) {
      return Array.isArray(row.value) ? row.value.length > 0 : row.value != null && row.value !== '';
    });
  }

  function validate(formId, stepNumber) {
    var context = getContext(formId);
    if (!context) return { valid: true, missing_field: null };
    var result = evaluate(formId);

    /* evaluate() above may have just run a jump_to_step action as a side
     * effect of a rule matching on a field change, moving the actual
     * visible step. If the caller captured stepNumber BEFORE that jump
     * (the common case: callers read dataset.currentstep, then call
     * validate(), which is what triggers evaluate() in the first place),
     * validating against the stale stepNumber would check the WRONG
     * step's required fields entirely and let a premature submission
     * through. Always trust the live DOM step over a parameter that may
     * predate evaluate()'s side effects. */
    var body = root ? bodyFor(context) : null;
    if (body && body.dataset.currentstep) {
      stepNumber = Number(body.dataset.currentstep);
    }

    var ignored = {};
    var required = {};
    var optional = {};
    result.ignored_fields.forEach(function (id) { ignored[id] = true; });
    result.required_fields.forEach(function (id) { required[id] = true; });
    result.optional_fields.forEach(function (id) { optional[id] = true; });
    var rows = getRows(formId);

    for (var i = 0; i < context.definition.length; i++) {
      var field = context.definition[i];
      if (!field || !field.id_ || STRUCTURAL.indexOf(String(field.type || '').toLowerCase()) !== -1) continue;
      if (stepNumber != null && Number(field.step) !== Number(stepNumber)) continue;
      if (ignored[field.id_]) continue;
      var isRequired = required[field.id_] ? true : (optional[field.id_] ? false : bool(field.required));
      if (!isRequired || hasSubmittedValue(rows, field)) continue;

      var message = elementInForm(context, field.id_ + '_-message');
      if (message) {
        message.textContent = field.customRequiredMsg ||
          (root.efb_var && root.efb_var.text && root.efb_var.text.enterTheValueThisField) ||
          'This field is required.';
        message.classList.remove('d-none');
        message.style.display = 'block';
      }
      return { valid: false, missing_field: field.id_, missing_name: field.name || field.id_ };
    }
    return { valid: true, missing_field: null, missing_name: null };
  }

  function getState(formId) {
    var context = getContext(formId);
    return context ? context.state : emptyResult();
  }

  var api = {
    init: init,
    initAll: initAll,
    evaluate: evaluate,
    evaluateDebounced: evaluateDebounced,
    validate: validate,
    getState: getState,
    evaluateDefinition: evaluateDefinition,
    buildValuesMap: buildValuesMap,
    hasActiveRules: hasActiveRules
  };

  if (root) {
    root.fun_statement_logic_efb = function (triggeredId, triggeredType, formId) {
      var resolved = formId;
      if (resolved == null && typeof root.infer_form_id_by_field_efb === 'function') {
        resolved = root.infer_form_id_by_field_efb(triggeredId);
      }
      if (resolved == null || Number(resolved) <= 0) resolved = currentFormId() || 0;
      return evaluate(resolved);
    };
  }

  return api;
});
