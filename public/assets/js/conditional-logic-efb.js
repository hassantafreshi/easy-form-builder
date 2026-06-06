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
      case 'gt': return isFinite(scalar) && isFinite(expectedScalar) && Number(scalar) > Number(expectedScalar);
      case 'gte': return isFinite(scalar) && isFinite(expectedScalar) && Number(scalar) >= Number(expectedScalar);
      case 'lt': return isFinite(scalar) && isFinite(expectedScalar) && Number(scalar) < Number(expectedScalar);
      case 'lte': return isFinite(scalar) && isFinite(expectedScalar) && Number(scalar) <= Number(expectedScalar);
      case 'between':
      case 'not_between':
        range = Array.isArray(expected) ? expected : expectedScalar.split(/\s*,\s*/);
        inside = range.length >= 2 && isFinite(scalar) && isFinite(range[0]) && isFinite(range[1]) &&
          Number(scalar) >= Number(range[0]) && Number(scalar) <= Number(range[1]);
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
        if (row[key] != null && isFinite(row[key])) amount = Number(row[key]);
      });
    });
    if (amount == null && values[fieldId] != null && isFinite(values[fieldId])) amount = Number(values[fieldId]);
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
      if (payment.amount == null || !isFinite(expected)) return false;
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
    var isOr = String(group.operator || 'AND').toUpperCase() === 'OR';
    for (var i = 0; i < items.length; i++) {
      var item = items[i] || {};
      var isGroup = item.type === 'group' || Array.isArray(item.items);
      var matched = isGroup
        ? evaluateGroup(item, values, structure, rows)
        : evaluateCondition(item, values, structure, rows);
      if (isOr && matched) return true;
      if (!isOr && !matched) return false;
    }
    return !isOr;
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

    for (var r = 0; r < rules.length; r++) {
      var rule = rules[r];
      if (!evaluateGroup(rule.conditions, values, structure, rows)) continue;
      var ruleId = String(rule.id || ('rule_' + r));
      matchedRules.push(ruleId);

      (rule.actions || []).forEach(function (action, actionIndex) {
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
          case 'clear_value': values[target] = ''; break;
          case 'show_message':
            messages.push({ key: ruleId + ':' + actionIndex, target: target, value: String(action.value || '') });
            break;
          case 'jump_to_step':
            jumps.push({ key: ruleId + ':' + actionIndex, target: target });
            break;
        }
      });
      if (bool(rule.stop_processing)) break;
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

  function elementInForm(context, id) {
    var element = root.document.getElementById(id);
    var body = bodyFor(context);
    return element && body && body.contains(element) ? element : null;
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
    var input = root.document.getElementById(fieldId + '_');

    if (type === 'yesno') {
      wrapper.querySelectorAll('input[type="radio"]').forEach(function (radioInput) { radioInput.checked = false; });
      wrapper.querySelectorAll('.active').forEach(function (active) { active.classList.remove('active'); });
      var yes = String(value).toLowerCase() === 'yes';
      var radio = root.document.getElementById(fieldId + (yes ? '_1' : '_2'));
      if (radio) radio.checked = true;
      var button = root.document.getElementById(fieldId + (yes ? '_b_1' : '_b_2'));
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
        wrapper.classList.toggle('d-none', !!hidden[fieldId]);
        wrapper.setAttribute('aria-hidden', hidden[fieldId] ? 'true' : 'false');
      }

      var isRequired = ignored[fieldId]
        ? false
        : (required[fieldId] ? true : (optional[fieldId] ? false : bool(index.fields[fieldId].required)));
      var liveField = context.structure.find(function (field) { return field && field.id_ === fieldId; });
      if (liveField) liveField.required = isRequired;
      var requiredMarker = root.document.getElementById(fieldId + '_req');
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
    if (['file', 'dadfile', 'esign'].indexOf(type) !== -1) {
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
