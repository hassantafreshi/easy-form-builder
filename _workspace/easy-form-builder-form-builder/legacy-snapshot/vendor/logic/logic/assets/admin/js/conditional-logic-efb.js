/**
 * EFB Conditional Logic Builder
 * Admin-side rule builder for Easy Form Builder 4.x
 */
(function () {
  'use strict';

  /* ────────────────────────────────────────────
     HELPERS
     ──────────────────────────────────────────── */
  const _id = (prefix = 'rule') => prefix + '_' + Math.random().toString(36).substr(2, 9);
  const _t = (k) => (typeof efb_var !== 'undefined' && efb_var.text && efb_var.text[k]) ? efb_var.text[k] : k;
  const _tf = (k, fallback) => {
    const value = _t(k);
    return value === k ? fallback : value;
  };
  const _esc = (s) => {
    if (typeof s !== 'string') return '';
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
  };

  /* ────────────────────────────────────────────
     PLAN GATING (Free / Free Plus / Pro)
     ──────────────────────────────────────────── */
  /* Free Plus builder limits; Pro is unlimited. */
  const FREE_PLUS_LIMITS = {
    fieldRules: 3,
    notificationRules: 2,
    fieldConditionsPerGroup: 2
  };
  /* Tabs that only the Pro plan can configure. */
  const PRO_ONLY_TABS = ['confirmation', 'webhook'];

  /* 'pro' | 'freeplus' | 'free'.
   * efb_var.pro is 1 for BOTH Pro and Free Plus (is_efb_pro), so
   * setting.package_type (1=Pro, 3=Free Plus) tells them apart.
   * When efb_var.pro is absent (tests/legacy contexts) stay permissive. */
  function getPlanTier() {
    if (typeof efb_var === 'undefined' || efb_var.pro === undefined || efb_var.pro === null) return 'pro';
    const proFlag = efb_var.pro == '1' || efb_var.pro == 1 || efb_var.pro === true;
    if (!proFlag) return 'free';
    const packageType = efb_var.setting ? Number(efb_var.setting.package_type) : NaN;
    return packageType === 3 ? 'freeplus' : 'pro';
  }

  function isProPlan() { return getPlanTier() === 'pro'; }
  function isFreePlusPlan() { return getPlanTier() === 'freeplus'; }

  function isTabProLocked(tab) {
    return PRO_ONLY_TABS.includes(tab) && !isProPlan();
  }

  /* Max rules per tab for the current plan. */
  function getRuleLimit(tab) {
    if (!isFreePlusPlan()) return Infinity;
    if (tab === 'field') return FREE_PLUS_LIMITS.fieldRules;
    if (tab === 'notification') return FREE_PLUS_LIMITS.notificationRules;
    return Infinity;
  }

  /* Max conditions per group for the current plan (fields tab only). */
  function getConditionLimit() {
    return isFreePlusPlan() && activeTab === 'field' ? FREE_PLUS_LIMITS.fieldConditionsPerGroup : Infinity;
  }

  function countGroupConditions(group) {
    if (!group || !Array.isArray(group.items)) return 0;
    return group.items.filter(item => !isGroupItem(item)).length;
  }

  function proOnlyMessage() {
    return _tf('fieldAvailableInProversion', 'This feature is only available in the Pro version of Easy Form Builder.');
  }

  function limitMessage(max, label) {
    return _tf('planLimitReached', 'You can create up to %1$s %2$s on your current plan. Upgrade to Pro for unlimited access.')
      .replace('%1$s', String(max))
      .replace('%2$s', label);
  }

  function showBuilderNotice(message) {
    if (typeof alert_message_efb === 'function') {
      alert_message_efb(_tf('proVersion', 'Pro Version'), message, 8, 'warning');
    }
  }

  function showProOnlyNotice() { showBuilderNotice(proOnlyMessage()); }

  function showFreePlanNotice() {
    showBuilderNotice(_tf('thisFeatureAvailableFreePlusPro', 'Want to use this feature? It is included in Free Plus and Pro plans.'));
  }

  function showLimitNotice(max, label) { showBuilderNotice(limitMessage(max, label)); }

  /* Locked panel shown in place of a Pro-only tab's content. */
  function renderProLockedPanel() {
    return `
      <div class="efb-logic-list">
        <div class="efb-logic-empty efb-logic-pro-panel">
          <div class="efb-logic-empty-icon efb-logic-pro-icon"><i class="efb bi-gem"></i></div>
          <h6>${_tf('proVersion', 'Pro Version')}</h6>
          <p>${_esc(proOnlyMessage())}</p>
          <button type="button" class="efb-logic-add-btn efb-logic-add-btn-center" onclick="EFB_Logic.openProUpgrade()">
            <i class="efb bi-gem"></i> ${_tf('upgradeToPro', 'Upgrade to Pro')}
          </button>
        </div>
      </div>`;
  }

  /* Locked panel shown to Free-plan users instead of the whole builder. */
  function renderFreeLockedPanel() {
    return `
      <div class="efb-logic-list">
        <div class="efb-logic-empty efb-logic-pro-panel">
          <div class="efb-logic-empty-icon efb-logic-pro-icon"><i class="efb bi-gem"></i></div>
          <h6>${_tf('proVersion', 'Pro Version')}</h6>
          <p>${_esc(_tf('thisFeatureAvailableFreePlusPro', 'Want to use this feature? It is included in Free Plus and Pro plans.'))}</p>
          <button type="button" class="efb-logic-add-btn efb-logic-add-btn-center" onclick="EFB_Logic.openProUpgrade()">
            <i class="efb bi-gem"></i> ${_tf('upgradeToPro', 'Upgrade to Pro')}
          </button>
        </div>
      </div>`;
  }

  /* operator definitions keyed by field category */
  const OPERATORS_BY_CATEGORY = {
    choice: ['is', 'is_not', 'is_empty', 'is_not_empty'],
    text: ['is', 'is_not', 'contains', 'not_contains', 'starts_with', 'ends_with', 'is_empty', 'is_not_empty'],
    number: ['is', 'is_not', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between', 'is_empty', 'is_not_empty'],
    date: ['is', 'is_not', 'date_before', 'date_after', 'date_between', 'is_empty', 'is_not_empty'],
    bool: ['is'],
    file: ['is_empty', 'is_not_empty'],
    payment: ['is_paid', 'is_not_paid', 'amount_eq', 'amount_gt', 'amount_lt']
  };

  const OPERATOR_LABELS = {
    is: 'ise',
    is_not: 'isne',
    contains: 'contains',
    not_contains: 'ncontains',
    starts_with: 'startw',
    ends_with: 'endw',
    gt: 'gthan',
    lt: 'lthan',
    gte: 'gtehan',
    lte: 'ltehan',
    between: 'between',
    not_between: 'nBetween',
    is_empty: 'empty',
    is_not_empty: 'nEmpty',
    is_paid: 'pay_completed',
    is_not_paid: 'pay_failed',
    amount_eq: 'ise',
    amount_gt: 'gthan',
    amount_lt: 'lthan',
    date_before: 'dateBefore',
    date_after: 'dateAfter',
    date_between: 'dateBetween'
  };

  const NO_VALUE_OPERATORS = new Set(['is_empty', 'is_not_empty', 'is_paid', 'is_not_paid']);
  const RANGE_OPERATORS = new Set(['between', 'not_between']);
  const DATE_RANGE_OPERATORS = new Set(['date_between']);

  /* Non-field condition sources (PRD §B: query params, user state, step) */
  const CONDITION_SOURCES = [
    { value: 'field',        label: () => _tf('field', 'Field') },
    { value: 'query_param',  label: () => _tf('urlParam', 'URL parameter') },
    { value: 'user',         label: () => _tf('userSource', 'User') },
    { value: 'current_step', label: () => _tf('currentStep', 'Current step') }
  ];
  const SOURCE_OPERATORS = {
    query_param: ['is', 'is_not', 'contains', 'not_contains', 'starts_with', 'ends_with', 'is_empty', 'is_not_empty'],
    user_logged_in: ['is'],
    user_role: ['is', 'is_not', 'is_empty', 'is_not_empty'],
    current_step: ['is', 'is_not', 'gt', 'gte', 'lt', 'lte']
  };

  /* field type → category mapping */
  const FIELD_CATEGORY = {
    text: 'text', textarea: 'text', email: 'text', url: 'text', tel: 'text', password: 'text',
    color: 'text', link: 'text', htmlcode: 'text', html: 'text',
    number: 'number', range: 'number',
    date: 'date',
    select: 'choice', multiselect: 'choice', radio: 'choice', checkbox: 'choice',
    conturyList: 'choice', stateProvince: 'choice', statePro: 'choice',
    country: 'choice', city: 'choice', cityList: 'choice',
    paySelect: 'choice', payMultiselect: 'choice', payRadio: 'choice', payCheckbox: 'choice',
    imgRadio: 'choice', chlRadio: 'choice', chlCheckBox: 'choice',
    yesNo: 'bool',
    file: 'file', signature: 'file', dadfile: 'file',
    audio_recorder: 'file', video_recorder: 'file', screen_recorder: 'file',
    stripe: 'payment', paypal: 'payment',
    maps: 'text', heading: 'text', pointr10: 'number', table_matrix: 'text'
  };

  /* field types that cannot be used as logic conditions */
  const EXCLUDED_CONDITION_TYPES = new Set([
    'html', 'htmlcode', 'link', 'maps', 'heading', 'chlCheckBox', 'pointr10', 'table_matrix'
  ]);

  /* action types */
  const ACTION_TYPES = [
    { value: 'show_field',    label: () => _t('show') + ' ' + _t('field') },
    { value: 'hide_field',    label: () => _t('hide') + ' ' + _t('field') },
    { value: 'set_required',  label: () => _t('required') },
    { value: 'set_optional',  label: () => _tf('optional', 'Optional') },
    { value: 'enable_field',  label: () => _tf('enable', 'Enable') },
    { value: 'disable_field', label: () => _tf('disable', 'Disable') },
    { value: 'show_step',     label: () => _t('show') + ' ' + _t('step') },
    { value: 'hide_step',     label: () => _t('hide') + ' ' + _t('step') },
    { value: 'jump_to_step',  label: () => efb_var.text.jumpStep  || 'Jump to Step' },
    { value: 'set_value',     label: () => efb_var.text.setValue  || 'Set Value' },
    { value: 'copy_value',    label: () => _tf('copyValue', 'Copy value from field') },
    { value: 'calculate',     label: () => _tf('calculate', 'Calculate') },
    { value: 'clear_value',   label: () => efb_var.text.clearValue || 'Clear Value' },
    { value: 'show_message',  label: () => efb_var.text.showMessage || 'Show Message' },
    { value: 'set_placeholder', label: () => _tf('setPlaceholder', 'Set placeholder') },
    { value: 'set_help',      label: () => _tf('setHelp', 'Set help text') },
    { value: 'set_label',     label: () => _tf('setLabel', 'Set label') },
    { value: 'focus_field',   label: () => _tf('focusField', 'Focus field') },
    { value: 'scroll_to_field', label: () => _tf('scrollToField', 'Scroll to field') },
    { value: 'block_submit',  label: () => _tf('blockSubmit', 'Block submit') },
    { value: 'end_form',      label: () => _tf('endForm', 'Custom submission message') }
  ];

  /* Form-level actions with no field/step target */
  const TARGETLESS_ACTIONS = new Set(['block_submit', 'end_form']);
  /* Actions whose value input is required text */
  const TEXT_VALUE_ACTIONS = new Set(['set_placeholder', 'set_help', 'set_label']);
  /* PRD §17: pricing logic (calculate) is a Pro-only capability */
  const PRO_ONLY_ACTIONS = new Set(['calculate']);

  /* ────────────────────────────────────────────
     STATE
     ──────────────────────────────────────────── */
  let rules = [];
  let currentRuleId = null;
  let view = 'list'; // 'list' | 'editor' | 'test'
  let activeTab = 'field'; // 'field' | 'notification' | 'confirmation' | 'webhook'
  let testValues = {};
  let testResults = [];
  let testInspector = null;

  /* ────────────────────────────────────────────
     FIELD HELPERS
     ──────────────────────────────────────────── */
  function getAllFields() {
    const r = [];
    if (typeof valj_efb === 'undefined') return r;
    for (let i = 1; i < valj_efb.length; i++) {
      const f = valj_efb[i];
      if (!f || f.type === 'option' || f.type === 'form' || f.type === 'r_matrix' || f.type === 'step' || f.type === 'buttonNav') continue;
      if (EXCLUDED_CONDITION_TYPES.has(f.type)) continue;
      r.push({ id_: f.id_, name: f.name || f.type, type: f.type, step: f.step || 1 });
    }
    return r;
  }

  function getAllSteps() {
    const r = [];
    if (typeof valj_efb === 'undefined') return r;
    for (let i = 1; i < valj_efb.length; i++) {
      const f = valj_efb[i];
      if (f && f.type === 'step') {
        r.push({ id_: f.id_, name: f.name || (_t('step') + ' ' + f.step), step: f.step });
      }
    }
    return r;
  }

  function getFieldOptions(fieldId) {
    const r = [];
    if (typeof valj_efb === 'undefined') return r;
    for (let i = 0; i < valj_efb.length; i++) {
      if (valj_efb[i].parent === fieldId) {
        r.push({ id_: valj_efb[i].id_, value: valj_efb[i].value || valj_efb[i].name || '' });
      }
    }
    return r;
  }

  function getFieldCategory(type) {
    return FIELD_CATEGORY[type] || 'text';
  }

  function getOperatorsForField(fieldId) {
    const f = typeof valj_efb !== 'undefined' ? valj_efb.find(x => x.id_ === fieldId) : null;
    const cat = f ? getFieldCategory(f.type) : 'text';
    return OPERATORS_BY_CATEGORY[cat] || OPERATORS_BY_CATEGORY.text;
  }

  function fieldHasOptions(fieldId) {
    const f = typeof valj_efb !== 'undefined' ? valj_efb.find(x => x.id_ === fieldId) : null;
    if (!f) return false;
    const cat = getFieldCategory(f.type);
    return cat === 'choice' || cat === 'bool';
  }

  function getFieldById(fieldId) {
    return typeof valj_efb !== 'undefined' ? valj_efb.find(x => x && x.id_ === fieldId) : null;
  }

  function getFieldName(fieldId) {
    const f = getFieldById(fieldId);
    return f ? _esc(f.name || f.type || fieldId) : _esc(fieldId || '');
  }

  function getRawFieldName(fieldId) {
    const f = getFieldById(fieldId);
    return f ? String(f.name || f.type || fieldId) : String(fieldId || '');
  }

  function getRawStepName(stepId) {
    const step = getAllSteps().find(s => String(s.id_) === String(stepId));
    return step ? String(step.name || step.id_) : String(stepId || '');
  }

  function getRawTargetName(target, isStep) {
    return isStep ? getRawStepName(target) : getRawFieldName(target);
  }

  function getOptionLabel(fieldId, value) {
    if (!value) return '';
    if (getFieldById(fieldId) && getFieldById(fieldId).type === 'yesNo') {
      return value === 'yes' ? 'Yes' : (value === 'no' ? 'No' : _esc(value));
    }
    const option = getFieldOptions(fieldId).find(o => o.id_ === value || o.value === value);
    return option ? _esc(option.value) : _esc(value);
  }

  /* Target list depending on action type */
  function getTargetsForAction(actionType) {
    if (actionType === 'show_step' || actionType === 'hide_step' || actionType === 'jump_to_step') {
      return getAllSteps().map(s => ({ id_: s.id_, name: s.name }));
    }
    return getAllFields().map(f => ({ id_: f.id_, name: f.name }));
  }

  /* Return true when autofill dataset is enabled and a dataset is selected */
  function isAutofillActive() {
    return typeof valj_efb !== 'undefined' && valj_efb[0]
      && Number(valj_efb[0].auto_fill) === 1
      && Number(valj_efb[0].autofill_id) > 0;
  }

  /* Return the array of dataset column key names, or [] if not yet loaded */
  function getAutofillSourceKeys() {
    if (!isAutofillActive()) return [];
    return Array.isArray(valj_efb[0].autofill_source_keys) ? valj_efb[0].autofill_source_keys : [];
  }

  /* ────────────────────────────────────────────
     LOAD / SAVE
     ──────────────────────────────────────────── */
  function loadRules() {
    if (typeof valj_efb === 'undefined' || !valj_efb[0]) return;
    const key = getActiveRulesKey();
    rules = Array.isArray(valj_efb[0][key]) ? JSON.parse(JSON.stringify(valj_efb[0][key])) : [];
  }

  function saveRules() {
    if (typeof valj_efb === 'undefined' || !valj_efb[0]) return;
    rules.forEach(rule => removeLeadingConnectors(rule.conditions));
    const key = getActiveRulesKey();
    valj_efb[0][key] = JSON.parse(JSON.stringify(rules));
    const fieldRules = activeTab === 'field'
      ? rules
      : (Array.isArray(valj_efb[0].logic_rules) ? valj_efb[0].logic_rules : []);
    valj_efb[0].logic = fieldRules.some(r => r.enabled && isRuleValid(r, 'field')) ? true : false;
  }

  function getActiveRulesKey() {
    if (activeTab === 'notification') return 'notification_rules';
    if (activeTab === 'confirmation') return 'confirmation_rules';
    if (activeTab === 'webhook') return 'webhook_rules';
    return 'logic_rules';
  }

  function getTabLabel(tab) {
    if (tab === 'notification') return _tf('notifications', 'Notifications');
    if (tab === 'confirmation') return _tf('confirmation', 'Confirmation');
    if (tab === 'webhook') return _tf('webhook', 'Webhook');
    return _tf('fields', 'Fields');
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
  }

  function isValidWebhookUrl(value) {
    const text = String(value || '').trim();
    return /^https?:\/\/[^\s<>"']{4,}$/i.test(text);
  }

  function isGroupItem(item) {
    return item && (item.type === 'group' || Array.isArray(item.items));
  }

  function newBlankCondition() {
    return { type: 'condition', source: 'field', field_id: '', compare: 'is', value: '' };
  }

  function normalizeConnector(value, fallback = 'AND') {
    return String(value || fallback).toUpperCase() === 'OR' ? 'OR' : 'AND';
  }

  function getItemConnector(group, item) {
    return normalizeConnector(item && item.connector, group && group.operator ? group.operator : 'AND');
  }

  function normalizePath(path) {
    if (path === undefined || path === null || path === '') return [];
    if (Array.isArray(path)) return path;
    if (typeof path === 'number') return [path];
    return String(path).split('.').filter(Boolean).map(n => Number.parseInt(n, 10)).filter(n => Number.isInteger(n));
  }

  function ensureConditionGroup(rule) {
    if (!rule.conditions || !Array.isArray(rule.conditions.items)) {
      rule.conditions = { type: 'group', operator: 'AND', items: [] };
    }
    rule.conditions.type = 'group';
    rule.conditions.operator = String(rule.conditions.operator || 'AND').toUpperCase() === 'OR' ? 'OR' : 'AND';
    return rule.conditions;
  }

  function getGroupByPath(rule, path) {
    let group = ensureConditionGroup(rule);
    const parts = normalizePath(path);
    for (let i = 0; i < parts.length; i++) {
      const item = group.items[parts[i]];
      if (!isGroupItem(item)) return null;
      group = item;
    }
    return group;
  }

  function getParentGroupForPath(rule, path) {
    const parts = normalizePath(path);
    if (!parts.length) return null;
    return getGroupByPath(rule, parts.slice(0, -1));
  }

  function getConditionByPath(rule, path) {
    const parts = normalizePath(path);
    const parent = getParentGroupForPath(rule, parts);
    if (!parent) return null;
    const item = parent.items[parts[parts.length - 1]];
    return isGroupItem(item) ? null : item;
  }

  function getItemByPath(rule, path) {
    const parts = normalizePath(path);
    const parent = getParentGroupForPath(rule, parts);
    if (!parent) return null;
    return parent.items[parts[parts.length - 1]] || null;
  }

  function removeLeadingConnectors(group) {
    if (!group || !Array.isArray(group.items)) return;
    group.items.forEach((item, index) => {
      if (index === 0 && item && Object.prototype.hasOwnProperty.call(item, 'connector')) {
        delete item.connector;
      }
      if (isGroupItem(item)) removeLeadingConnectors(item);
    });
  }

  function isConditionValid(cond) {
    if (!cond || !cond.field_id || !cond.compare) return false;
    const source = String(cond.source || 'field');
    if (source === 'query_param' && !String(cond.param || cond.field_id || '').trim()) return false;
    if (NO_VALUE_OPERATORS.has(cond.compare)) return true;
    if (RANGE_OPERATORS.has(cond.compare) || DATE_RANGE_OPERATORS.has(cond.compare)) {
      const parts = String(cond.value || '').split(',');
      return parts.length === 2 && parts[0].trim() !== '' && parts[1].trim() !== '';
    }
    return cond.value !== undefined && cond.value !== null && String(cond.value).length > 0;
  }

  function isConditionGroupValid(group) {
    if (!group || !Array.isArray(group.items) || !group.items.length) return false;
    return group.items.every(item => isGroupItem(item) ? isConditionGroupValid(item) : isConditionValid(item));
  }

  function hasConfiguredCondition(group) {
    if (!group || !Array.isArray(group.items)) return false;
    return group.items.some(item => isGroupItem(item) ? hasConfiguredCondition(item) : (item && item.field_id && item.field_id !== ''));
  }

  function normalizeTestValue(fieldId, value) {
    const field = getFieldById(fieldId);
    const type = field ? String(field.type || '').toLowerCase() : '';
    if (type === 'yesno') {
      const raw = String(value == null ? '' : value).toLowerCase();
      if (raw === String(fieldId).toLowerCase() + '_1' || raw === '1' || raw === 'yes') return 'yes';
      if (raw === String(fieldId).toLowerCase() + '_2' || raw === '0' || raw === 'no') return 'no';
      return '';
    }
    if (type.indexOf('checkbox') !== -1 || type.indexOf('multiselect') !== -1) {
      if (Array.isArray(value)) return value.filter(v => v !== '' && v != null);
      return String(value || '').split(/\s*,\s*/).filter(Boolean);
    }
    return value == null ? '' : value;
  }

  function getTestValuesMap() {
    const values = {};
    getAllFields().forEach(field => {
      values[field.id_] = normalizeTestValue(field.id_, testValues[field.id_] || '');
    });
    return values;
  }

  /* Mirrors PHP is_numeric(): empty/whitespace-only strings are NOT numeric
   * (isFinite('') is true because '' coerces to 0, which diverges from the
   * server-side evaluator). Numeric operators must never match them. */
  function isNumericScalar(value) {
    const s = String(value == null ? '' : value).trim();
    return s !== '' && isFinite(s);
  }

  function compareScalar(value, expected, compare) {
    const scalar = String(value == null ? '' : value).trim();
    const expectedScalar = Array.isArray(expected) ? expected.join(',') : String(expected == null ? '' : expected).trim();
    const left = scalar.toLowerCase();
    const right = expectedScalar.toLowerCase();
    switch (compare) {
      case 'is': return left === right;
      case 'is_not': return left !== right;
      case 'contains': return left.indexOf(right) !== -1;
      case 'not_contains': return left.indexOf(right) === -1;
      case 'starts_with': return left.indexOf(right) === 0;
      case 'ends_with': return right.length === 0 || left.slice(-right.length) === right;
      case 'gt':
      case 'amount_gt': return isNumericScalar(scalar) && isNumericScalar(expectedScalar) && Number(scalar) > Number(expectedScalar);
      case 'gte': return isNumericScalar(scalar) && isNumericScalar(expectedScalar) && Number(scalar) >= Number(expectedScalar);
      case 'lt':
      case 'amount_lt': return isNumericScalar(scalar) && isNumericScalar(expectedScalar) && Number(scalar) < Number(expectedScalar);
      case 'lte': return isNumericScalar(scalar) && isNumericScalar(expectedScalar) && Number(scalar) <= Number(expectedScalar);
      case 'amount_eq': return isNumericScalar(scalar) && isNumericScalar(expectedScalar) && Math.abs(Number(scalar) - Number(expectedScalar)) < 0.00001;
      case 'between':
      case 'not_between': {
        const range = Array.isArray(expected) ? expected : expectedScalar.split(/\s*,\s*/);
        if (range.length < 2 || !isNumericScalar(scalar) || !isNumericScalar(range[0]) || !isNumericScalar(range[1])) return false;
        const inside = Number(scalar) >= Number(range[0]) && Number(scalar) <= Number(range[1]);
        return compare === 'between' ? inside : !inside;
      }
      case 'is_empty': return scalar === '';
      case 'is_not_empty': return scalar !== '';
      case 'is_paid': return scalar !== '' && scalar !== '0';
      case 'is_not_paid': return scalar === '' || scalar === '0';
      case 'date_before':
      case 'date_after': {
        const valueTs = dateTimestamp(scalar);
        const expectedTs = dateTimestamp(right);
        if (valueTs === null || expectedTs === null) return false;
        return compare === 'date_before' ? valueTs < expectedTs : valueTs > expectedTs;
      }
      case 'date_between': {
        const dateRange = Array.isArray(expected) ? expected : expectedScalar.split(/\s*,\s*/);
        if (dateRange.length < 2) return false;
        const ts = dateTimestamp(scalar);
        const fromTs = dateTimestamp(dateRange[0]);
        const toTs = dateTimestamp(dateRange[1]);
        if (ts === null || fromTs === null || toTs === null) return false;
        return ts >= fromTs && ts <= toTs;
      }
      default: return false;
    }
  }

  /* Millisecond timestamp for a date string, null when unparseable — mirrors
   * the public runtime so Test Mode predicts the frontend exactly. */
  function dateTimestamp(value) {
    const text = String(value == null ? '' : value).trim();
    if (text === '') return null;
    const parsed = Date.parse(text.length === 10 ? text + 'T00:00:00' : text);
    return Number.isFinite(parsed) ? parsed : null;
  }

  /* Environment used by Test Mode for non-field sources; filled from the
   * dedicated test inputs (query params, user state, current step). */
  let testEnv = { query: {}, user: { logged_in: false, roles: [] }, current_step: null };

  function evaluateConditionWithValues(condition, values) {
    if (!condition || !condition.field_id) return false;
    const fieldId = String(condition.field_id);
    const compare = String(condition.compare || 'is');
    const expected = condition.value != null ? condition.value : '';
    const source = String(condition.source || 'field');

    if (source === 'query_param') {
      const param = String(condition.param || condition.field_id || '');
      const queryValue = Object.prototype.hasOwnProperty.call(testEnv.query, param) ? testEnv.query[param] : '';
      return compareScalar(queryValue, expected, compare);
    }
    if (source === 'user') {
      if (fieldId === 'logged_in') return compareScalar(testEnv.user.logged_in ? 'yes' : 'no', expected, compare);
      if (fieldId === 'role') {
        const roles = testEnv.user.roles.map(role => String(role).toLowerCase());
        const expectedRole = String(Array.isArray(expected) ? expected.join(',') : expected).toLowerCase().trim();
        const hasRole = roles.includes(expectedRole);
        if (compare === 'is') return hasRole;
        if (compare === 'is_not') return !hasRole;
        if (compare === 'is_empty') return roles.length === 0;
        if (compare === 'is_not_empty') return roles.length > 0;
        return compareScalar(roles.join(' '), expected, compare);
      }
      return false;
    }
    if (source === 'current_step') {
      return compareScalar(testEnv.current_step == null ? '' : String(testEnv.current_step), expected, compare);
    }

    const current = Object.prototype.hasOwnProperty.call(values, fieldId) ? values[fieldId] : '';
    if (Array.isArray(current)) {
      /* Same membership rule as the public runtime: a multi-value field holds
       * one entry per ticked option and the condition names one of them, spelled
       * either as the option's id_ or as its visible text. Test Mode feeds ids
       * from its own dropdowns, but matching both keeps this preview and the
       * live form answering identically for imported rules too. */
      const expectedScalar = Array.isArray(expected) ? expected.join(',') : String(expected);
      const wanted = new Set();
      const want = value => {
        const text = String(value == null ? '' : value).trim().toLowerCase();
        if (text !== '') wanted.add(text);
      };
      want(expectedScalar);
      getFieldOptions(fieldId).forEach(option => {
        if (String(option.id_) === expectedScalar) want(option.value);
        if (String(option.value) === expectedScalar) want(option.id_);
      });
      const present = current.some(entry => wanted.has(String(entry == null ? '' : entry).trim().toLowerCase()));
      if (compare === 'is') return present;
      if (compare === 'is_not') return !present;
      if (compare === 'is_empty') return current.length === 0;
      if (compare === 'is_not_empty') return current.length > 0;
      return compareScalar(current.join(' '), expected, compare);
    }
    return compareScalar(current, expected, compare);
  }

  function evaluateConditionGroupWithValues(group, values) {
    const items = group && Array.isArray(group.items) ? group.items : [];
    if (!items.length) return false;
    let result = false;
    items.forEach((item, index) => {
      const matched = isGroupItem(item)
        ? evaluateConditionGroupWithValues(item, values)
        : evaluateConditionWithValues(item, values);
      if (index === 0) {
        result = matched;
        return;
      }
      result = getItemConnector(group, item) === 'OR' ? (result || matched) : (result && matched);
    });
    /* negate turns AND into NAND, OR into NOR, and a single item into NOT. */
    return group && (group.negate === true || group.negate === 1 || group.negate === '1') ? !result : result;
  }

  function clonePlain(value) {
    return JSON.parse(JSON.stringify(value || {}));
  }

  function numberFromCalculationValue(value) {
    if (Array.isArray(value)) {
      let total = 0;
      for (let i = 0; i < value.length; i++) {
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
    const text = String(formula == null ? '' : formula).slice(0, 500);
    const tokens = [];
    let i = 0;
    while (i < text.length) {
      const ch = text.charAt(i);
      if (/\s/.test(ch)) { i++; continue; }
      if (ch === '{' || ch === '[') {
        const close = ch === '{' ? '}' : ']';
        const end = text.indexOf(close, i + 1);
        if (end === -1) return null;
        const ref = text.slice(i + 1, end).trim();
        if (!ref) return null;
        tokens.push({ type: 'ref', value: ref });
        i = end + 1;
        continue;
      }
      if (/[0-9.]/.test(ch)) {
        const start = i;
        let dots = 0;
        while (i < text.length && /[0-9.]/.test(text.charAt(i))) {
          if (text.charAt(i) === '.') dots++;
          i++;
        }
        const literal = text.slice(start, i);
        if (literal === '.' || dots > 1 || !isFinite(literal)) return null;
        tokens.push({ type: 'number', value: Number(literal) });
        continue;
      }
      if (/[A-Za-z_]/.test(ch)) {
        const start = i;
        while (i < text.length && /[A-Za-z0-9_-]/.test(text.charAt(i))) i++;
        tokens.push({ type: 'ref', value: text.slice(start, i) });
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

  function evaluateCalculationFormula(formula, values) {
    const tokens = tokenizeCalculationFormula(formula);
    if (!tokens || !tokens.length) return null;
    let pos = 0;
    const invalid = () => ({ valid: false, value: 0 });
    const fieldNumber = (fieldId) => {
      fieldId = String(fieldId || '');
      if (!getFieldById(fieldId)) return invalid();
      return numberFromCalculationValue(Object.prototype.hasOwnProperty.call(values, fieldId) ? values[fieldId] : '');
    };
    const parseExpression = () => {
      let left = parseTerm();
      while (left.valid && pos < tokens.length && tokens[pos].type === 'op' && (tokens[pos].value === '+' || tokens[pos].value === '-')) {
        const op = tokens[pos++].value;
        const right = parseTerm();
        if (!right.valid) return right;
        left.value = op === '+' ? left.value + right.value : left.value - right.value;
      }
      return left;
    };
    const parseTerm = () => {
      let left = parseFactor();
      while (left.valid && pos < tokens.length && tokens[pos].type === 'op' && (tokens[pos].value === '*' || tokens[pos].value === '/')) {
        const op = tokens[pos++].value;
        const right = parseFactor();
        if (!right.valid) return right;
        if (op === '/' && Math.abs(right.value) < 0.000000000001) return invalid();
        left.value = op === '*' ? left.value * right.value : left.value / right.value;
      }
      return left;
    };
    const parseFactor = () => {
      if (pos >= tokens.length) return invalid();
      const token = tokens[pos];
      if (token.type === 'op' && (token.value === '+' || token.value === '-')) {
        pos++;
        const unary = parseFactor();
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
        const nested = parseExpression();
        if (!nested.valid || pos >= tokens.length || tokens[pos].type !== 'paren' || tokens[pos].value !== ')') return invalid();
        pos++;
        return nested;
      }
      return invalid();
    };
    const result = parseExpression();
    if (!result.valid || pos !== tokens.length || !isFinite(result.value)) return null;
    return result.value;
  }

  function formatCalculationResult(value, decimals) {
    const hasDecimals = decimals !== undefined && decimals !== null && decimals !== '';
    if (hasDecimals) {
      const places = Math.max(0, Math.min(6, Number.parseInt(decimals, 10) || 0));
      return Number(value).toFixed(places);
    }
    return String(parseFloat(Number(value).toFixed(10)));
  }

  function resolveCalculationValueForTest(action, values) {
    const formula = String(action && action.value != null ? action.value : '').trim();
    if (!formula) return null;
    const value = evaluateCalculationFormula(formula, values);
    if (value === null) return null;
    return formatCalculationResult(value, action.decimals);
  }

  function isStepActionType(type) {
    return type === 'show_step' || type === 'hide_step' || type === 'jump_to_step';
  }

  function actionConflictFamily(type) {
    if (type === 'show_field' || type === 'hide_field') return 'visibility';
    if (type === 'set_required' || type === 'set_optional') return 'requirement';
    if (type === 'enable_field' || type === 'disable_field') return 'availability';
    if (type === 'show_step' || type === 'hide_step') return 'step_visibility';
    if (type === 'set_value' || type === 'copy_value' || type === 'calculate' || type === 'clear_value') return 'value';
    return '';
  }

  /* Which non-field sources do the current rules actually use? Drives the
   * extra Test Mode inputs (query params, user state, current step). */
  function collectTestEnvNeeds() {
    const needs = { query: [], user: false, step: false };
    const seen = new Set();
    const walk = (group) => {
      if (!group || !Array.isArray(group.items)) return;
      group.items.forEach(item => {
        if (isGroupItem(item)) { walk(item); return; }
        const source = String((item && item.source) || 'field');
        if (source === 'query_param') {
          const param = String(item.param || item.field_id || '').trim();
          if (param && !seen.has(param)) { seen.add(param); needs.query.push(param); }
        }
        if (source === 'user') needs.user = true;
        if (source === 'current_step') needs.step = true;
      });
    };
    rules.forEach(rule => walk(rule && rule.conditions));
    return needs;
  }

  function buildTestEnv() {
    const env = { query: {}, user: { logged_in: false, roles: [] }, current_step: null };
    Object.keys(testValues).forEach(key => {
      if (key.indexOf('__query__') === 0) env.query[key.slice(9)] = testValues[key];
    });
    env.user.logged_in = testValues.__user_logged_in === 'yes';
    const role = String(testValues.__user_role || '').trim();
    if (role) env.user.roles = [role];
    if (testValues.__current_step !== undefined && testValues.__current_step !== '' && isFinite(testValues.__current_step)) {
      env.current_step = Number(testValues.__current_step);
    }
    return env;
  }

  function isRuleEnabled(rule) {
    return !(rule && (rule.enabled === false || rule.enabled === 0 || rule.enabled === '0'));
  }

  function analyzeConflicts() {
    if (activeTab !== 'field') return [];
    const buckets = {};
    rules.forEach((rule, position) => {
      if (!isRuleEnabled(rule) || !Array.isArray(rule.actions)) return;
      rule.actions.forEach(action => {
        if (!action || !action.type || !action.target) return;
        const family = actionConflictFamily(action.type);
        if (!family) return;
        const key = `${family}:${action.target}`;
        if (!buckets[key]) buckets[key] = [];
        buckets[key].push({ rule, action, position });
      });
    });

    return Object.keys(buckets).map(key => {
      const entries = buckets[key];
      const family = key.split(':')[0];
      const target = entries[0] && entries[0].action ? entries[0].action.target : '';
      const uniqueTypes = Array.from(new Set(entries.map(entry => entry.action.type)));
      const conflicts = family === 'value'
        ? entries.length > 1
        : uniqueTypes.length > 1;
      if (!conflicts) return null;
      const isStep = isStepActionType(entries[0].action.type);
      const targetName = getRawTargetName(target, isStep);
      const names = entries.map(entry => {
        const fallback = _t('conlog') + ' ' + (entry.position + 1);
        return `${entry.rule.name || fallback} (#${Number(entry.rule.priority || 10)})`;
      }).join(', ');
      return {
        target,
        targetName,
        family,
        entries,
        message: `${targetName}: ${names}`,
        hint: `${_tf('priority', 'Priority')} / ${_tf('stopProcessing', 'Stop after this rule matches')}`
      };
    }).filter(Boolean).concat(analyzeStrippedValueConflicts());
  }

  /* A value written to a field that some rule also hides or disables is thrown
   * away: hidden and disabled fields are stripped from the entry on purpose, on
   * both the client and the server. Priority cannot resolve it — the value
   * action can win every race and the field is still dropped — so it is not a
   * same-family conflict, and the existing bucket analysis (which only compares
   * actions WITHIN a family) never sees it. The classic way to hit this is a
   * locked calculated total: Calculate + Disable field on one target, which
   * silently submits no total at all. */
  function analyzeStrippedValueConflicts() {
    if (activeTab !== 'field') return [];
    const writers = {};
    const strippers = {};
    rules.forEach((rule, position) => {
      if (!isRuleEnabled(rule) || !Array.isArray(rule.actions)) return;
      rule.actions.forEach(action => {
        if (!action || !action.target) return;
        const bucket = actionConflictFamily(action.type) === 'value' ? writers
          : (action.type === 'hide_field' || action.type === 'disable_field') ? strippers
          : null;
        if (!bucket) return;
        if (!bucket[action.target]) bucket[action.target] = [];
        bucket[action.target].push({ rule, action, position });
      });
    });

    return Object.keys(writers).filter(target => strippers[target]).map(target => {
      const entries = writers[target].concat(strippers[target]);
      const names = entries.map(entry => {
        const fallback = _t('conlog') + ' ' + (entry.position + 1);
        return `${entry.rule.name || fallback} (#${Number(entry.rule.priority || 10)})`;
      }).join(', ');
      return {
        target,
        targetName: getRawTargetName(target, false),
        family: 'value_stripped',
        entries,
        message: `${getRawTargetName(target, false)}: ${names}`,
        hint: _tf('valueOnStrippedField', 'A value is written to this field while another rule hides or disables it — hidden and disabled fields are not saved with the entry.')
      };
    });
  }

  function describeAppliedAction(action, resultValue) {
    const actionType = ACTION_TYPES.find(item => item.value === action.type);
    const label = actionType ? actionType.label() : action.type;
    if (TARGETLESS_ACTIONS.has(action.type)) {
      return action.value ? `${label} — "${action.value}"` : label;
    }
    const targetName = getRawTargetName(action.target, isStepActionType(action.type));
    if (action.type === 'calculate' && resultValue != null) return `${label} -> ${targetName} = ${resultValue}`;
    if (action.type === 'set_value' && action.value != null) return `${label} -> ${targetName} = ${action.value}`;
    if (action.type === 'copy_value' && action.value) return `${label} -> ${targetName} = {${getRawFieldName(action.value)}}`;
    if (TEXT_VALUE_ACTIONS.has(action.type) && action.value) return `${label} -> ${targetName} = "${action.value}"`;
    return targetName ? `${label} -> ${targetName}` : label;
  }

  function emptyInspectorResult(values) {
    return {
      stabilized: true,
      trace: [],
      matched_rules: [],
      hidden_fields: [],
      shown_fields: [],
      required_fields: [],
      optional_fields: [],
      disabled_fields: [],
      enabled_fields: [],
      hidden_steps: [],
      shown_steps: [],
      ignored_fields: [],
      values_map: clonePlain(values),
      set_values: {},
      cleared_fields: [],
      messages: [],
      jumps: [],
      errors: [],
      submit_blocked: false,
      block_messages: [],
      end_form: null,
      conflicts: analyzeConflicts()
    };
  }

  function evaluateInspectorPass(values, sorted) {
    const hidden = {};
    const shown = {};
    const required = {};
    const optional = {};
    const disabled = {};
    const enabled = {};
    const hiddenSteps = {};
    const shownSteps = {};
    const stoppedFieldTargets = {};
    const stoppedStepTargets = {};
    const nextValues = clonePlain(values);
    const result = emptyInspectorResult(nextValues);

    getAllFields().forEach(field => {
      const source = getFieldById(field.id_) || {};
      if (source.hidden === true || source.hidden === 1 || source.hidden === '1') hidden[field.id_] = true;
      if (source.disabled === true || source.disabled === 1 || source.disabled === '1') disabled[field.id_] = true;
    });
    getAllSteps().forEach(step => {
      const source = typeof valj_efb !== 'undefined' ? valj_efb.find(item => item && item.id_ === step.id_) : {};
      if (source && (source.hidden === true || source.hidden === 1 || source.hidden === '1')) hiddenSteps[step.id_] = true;
    });
    if (activeTab === 'field') {
      sorted.forEach(rule => {
        (rule.actions || []).forEach(action => {
          if (action.type === 'show_field' && action.target) hidden[action.target] = true;
          if (action.type === 'show_step' && action.target) hiddenSteps[action.target] = true;
        });
      });
    }

    sorted.forEach((rule, index) => {
      const name = rule.name || (_t('conlog') + ' ' + (index + 1));
      const ruleActions = Array.isArray(rule.actions) ? rule.actions : [];
      if (!isRuleEnabled(rule)) {
        result.trace.push({ rule, name, status: 'skipped', label: _tf('skipped', 'Skipped'), actions: [] });
        return;
      }
      const blockedByStop = activeTab === 'field' && ruleActions.length > 0 && ruleActions.every(action => {
        if (!action.target) return false;
        return isStepActionType(action.type)
          ? Object.prototype.hasOwnProperty.call(stoppedStepTargets, action.target)
          : Object.prototype.hasOwnProperty.call(stoppedFieldTargets, action.target);
      });
      if (blockedByStop) {
        result.trace.push({ rule, name, status: 'blocked', label: _tf('blockedByStop', 'Blocked by stop processing'), actions: [] });
        return;
      }
      const matched = evaluateConditionGroupWithValues(rule.conditions, nextValues);
      if (!matched) {
        result.trace.push({ rule, name, status: 'not-matched', label: _tf('notMatched', 'Not matched'), actions: [] });
        return;
      }

      const ruleId = String(rule.id || ('rule_' + index));
      const applied = [];
      result.matched_rules.push(ruleId);
      if (activeTab !== 'field') {
        applied.push(buildTestActionSummary(rule));
      } else {
        ruleActions.forEach((action, actionIndex) => {
          const target = action.target;
          /* form-level actions with no target */
          if (TARGETLESS_ACTIONS.has(action.type)) {
            result.submit_blocked = true;
            if (action.type === 'end_form' && !result.end_form) {
              result.end_form = { key: ruleId + ':' + actionIndex, message: String(action.value || '') };
            }
            if (action.type === 'block_submit' && action.value) {
              result.block_messages.push({ key: ruleId + ':' + actionIndex, value: String(action.value) });
            }
            applied.push(describeAppliedAction(action, null));
            return;
          }
          if (!target) return;
          let calculatedValue = null;
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
              nextValues[target] = action.value != null ? action.value : '';
              break;
            case 'copy_value': {
              const sourceId = String(action.value || '');
              if (getFieldById(sourceId)) {
                nextValues[target] = Object.prototype.hasOwnProperty.call(nextValues, sourceId) ? nextValues[sourceId] : '';
              }
              break;
            }
            case 'calculate':
              calculatedValue = resolveCalculationValueForTest(action, nextValues);
              if (calculatedValue == null) {
                result.errors.push({ rule: ruleId, action: actionIndex, message: _tf('formulaInvalid', 'Formula could not be calculated. Check field tokens and division by zero.') });
                return;
              }
              nextValues[target] = calculatedValue;
              break;
            case 'clear_value':
              nextValues[target] = '';
              break;
            case 'set_placeholder':
            case 'set_help':
            case 'set_label':
            case 'focus_field':
            case 'scroll_to_field':
              /* UI-only actions: shown in the trace, no state to track here */
              break;
            case 'show_message':
              result.messages.push({ key: ruleId + ':' + actionIndex, target, value: String(action.value || '') });
              break;
            case 'jump_to_step':
              result.jumps.push({ key: ruleId + ':' + actionIndex, target });
              break;
          }
          applied.push(describeAppliedAction(action, calculatedValue));
        });
      }
      result.trace.push({ rule, name, status: 'matched', label: _tf('matched', 'Matched'), actions: applied });

      if (activeTab === 'field' && rule.stop_processing) {
        ruleActions.forEach(action => {
          if (!action.target) return;
          if (isStepActionType(action.type)) stoppedStepTargets[action.target] = true;
          else stoppedFieldTargets[action.target] = true;
        });
      }
    });

    /* Mirrors the public runtime: step action targets are step ids (`id_`),
     * a field's `step` is its POSITION. Resolve the target to its position
     * before comparing — matching the raw id against a position makes hiding
     * step id_ "2" also ignore whatever sits at position 2, so Test Mode would
     * report answers as dropped that production keeps (and vice versa). */
    const ignored = { ...hidden, ...disabled };
    const hiddenStepPositions = {};
    Object.keys(hiddenSteps).forEach(stepId => {
      const step = getAllSteps().find(item => String(item.id_) === String(stepId));
      hiddenStepPositions[step && step.step != null ? String(step.step) : String(stepId)] = true;
    });
    getAllFields().forEach(field => {
      const source = getFieldById(field.id_) || {};
      const fieldStep = String(source.step || field.step || '');
      if (fieldStep && hiddenStepPositions[fieldStep]) ignored[field.id_] = true;
    });

    result.hidden_fields = Object.keys(hidden);
    result.shown_fields = Object.keys(shown);
    result.required_fields = Object.keys(required);
    result.optional_fields = Object.keys(optional);
    result.disabled_fields = Object.keys(disabled);
    result.enabled_fields = Object.keys(enabled);
    result.hidden_steps = Object.keys(hiddenSteps);
    result.shown_steps = Object.keys(shownSteps);
    result.ignored_fields = Object.keys(ignored);
    result.values_map = nextValues;
    return result;
  }

  /* Same budget as the public runtime, so Test Mode and production agree on
   * which rule sets converge. */
  const MAX_INSPECTOR_PASSES = 25;

  function evaluateRulesForInspector(initialValues) {
    const original = clonePlain(initialValues);
    const sorted = rules.map((rule, position) => ({ ...rule, _position: position }))
      .sort((a, b) => (Number(a.priority || 10) - Number(b.priority || 10)) || (a._position - b._position));
    let values = clonePlain(initialValues);
    let result = emptyInspectorResult(values);
    const seen = {};

    /* Mirrors the public runtime: exhausting the pass budget is a FAILURE to
     * reach a fixed point, not a success. Monotonic divergence ({total} =
     * {total} + {qty}) never repeats a signature, so only the budget catches
     * it — and Test Mode's "did not stabilize" warning is the whole point. */
    let stabilized = false;
    for (let pass = 0; pass < MAX_INSPECTOR_PASSES; pass++) {
      const signature = JSON.stringify(values);
      if (seen[signature]) break;
      seen[signature] = true;
      result = evaluateInspectorPass(values, sorted);
      const nextSignature = JSON.stringify(result.values_map);
      if (nextSignature === signature) {
        stabilized = true;
        break;
      }
      values = clonePlain(result.values_map);
    }
    result.stabilized = stabilized;
    if (!stabilized) result.values_map = clonePlain(original);

    result.set_values = {};
    result.cleared_fields = [];
    Object.keys({ ...original, ...result.values_map }).forEach(fieldId => {
      const before = Object.prototype.hasOwnProperty.call(original, fieldId) ? original[fieldId] : '';
      const after = Object.prototype.hasOwnProperty.call(result.values_map, fieldId) ? result.values_map[fieldId] : '';
      if (JSON.stringify(before) === JSON.stringify(after)) return;
      if (after == null || after === '' || (Array.isArray(after) && after.length === 0)) result.cleared_fields.push(fieldId);
      else result.set_values[fieldId] = after;
    });
    result.conflicts = analyzeConflicts();
    return result;
  }

  function isRuleValid(rule, tab = activeTab) {
    if (!rule || !rule.conditions || !Array.isArray(rule.conditions.items) || !rule.conditions.items.length) return false;
    const conditionsValid = isConditionGroupValid(rule.conditions);
    if (!conditionsValid) return false;
    if (tab === 'notification') {
      return isValidEmail(rule.recipient);
    }
    if (tab === 'confirmation') {
      const action = rule.action === 'redirect' ? 'redirect' : 'message';
      if (action === 'redirect') return String(rule.url || '').trim().length > 5;
      return String(rule.message || '').trim().length > 0;
    }
    if (tab === 'webhook') {
      if (rule.action === 'stop') return true; /* stop rules never call a URL */
      return isValidWebhookUrl(rule.url);
    }
    if (!Array.isArray(rule.actions) || !rule.actions.length) return false;
    const actionsValid = rule.actions.every(action => {
      if (!action || !action.type) return false;
      if (TARGETLESS_ACTIONS.has(action.type)) return true; /* message optional */
      if (!action.target) return false;
      if (action.type === 'show_message') return String(action.value || '').trim().length > 0;
      if (action.type === 'set_value') return String(action.value || '').length > 0;
      if (action.type === 'calculate') return String(action.value || '').trim().length > 0;
      if (action.type === 'copy_value') return String(action.value || '').length > 0;
      if (TEXT_VALUE_ACTIONS.has(action.type)) return String(action.value || '').trim().length > 0;
      return true;
    });
    return actionsValid;
  }

  /* ────────────────────────────────────────────
     HUMAN-READABLE SUMMARY
     ──────────────────────────────────────────── */
  function buildSummary(rule) {
    if (!rule || !rule.conditions || !rule.conditions.items || rule.conditions.items.length === 0) {
      return '';
    }
    /* Check if rule is actually configured (at least one condition has a field selected) */
    const configuredCondition = hasConfiguredCondition(rule.conditions);
    const hasConfiguredAction = (rule.actions || []).some(a => a.type && a.target && a.target !== '');
    if (!configuredCondition && !hasConfiguredAction) return '';

    const fieldName = (id) => {
      if (!id || typeof valj_efb === 'undefined') return '';
      const f = valj_efb.find(x => x.id_ === id);
      return f ? _esc(f.name || f.type) : '';
    };
    const optionValue = (id) => {
      if (!id || typeof valj_efb === 'undefined') return '';
      const f = valj_efb.find(x => x.id_ === id);
      return f ? _esc(f.value || f.name || '') : _esc(id || '');
    };

    const conditionText = (c) => {
      if (!c || !c.field_id || c.field_id === '') return '';
      const fn = fieldName(c.field_id);
      const op = _t(OPERATOR_LABELS[c.compare] || c.compare);
      if (NO_VALUE_OPERATORS.has(c.compare)) return `${fn} ${op}`;
      if (RANGE_OPERATORS.has(c.compare)) {
        const parts = String(c.value || '').split(',');
        const min = _esc((parts[0] || '').trim());
        const max = _esc((parts[1] || '').trim());
        if (!min && !max) return `${fn} ${op}`;
        return `${fn} ${op} ${min} ${_t('and')} ${max}`;
      }
      const val = fieldHasOptions(c.field_id) ? optionValue(c.value) : _esc(c.value || '');
      return val ? `${fn} ${op} "${val}"` : `${fn} ${op}`;
    };

    const groupText = (group, depth = 0) => {
      if (!group || !Array.isArray(group.items)) return '';
      let text = '';
      group.items.forEach((item, index) => {
        const part = (() => {
          if (isGroupItem(item)) {
            const nested = groupText(item, depth + 1);
            return nested ? `(${nested})` : '';
          }
          return conditionText(item);
        })();
        if (!part) return;
        if (text) text += getItemConnector(group, item) === 'OR' ? (' ' + _t('or') + ' ') : (' ' + _t('and') + ' ');
        text += part;
      });
      return text;
    };

    const condText = groupText(rule.conditions);

    if (activeTab === 'notification') {
      const recipient = _esc(rule.recipient || '');
      const subject = _esc(rule.subject || _tf('emailNotifications', 'Email notification'));
      const actionText = recipient ? `${_tf('send', 'Send')} ${subject} -> ${recipient}` : _tf('emailNotifications', 'Email notification');
      return condText ? condText + ' -> ' + actionText : actionText;
    }

    if (activeTab === 'confirmation') {
      const action = rule.action === 'redirect' ? 'redirect' : 'message';
      const actionText = action === 'redirect'
        ? `${_tf('redirect', 'Redirect')} -> ${_esc(rule.url || '')}`
        : _tf('thankYou', 'Thank you') + ' ' + _tf('message', 'Message');
      return condText ? condText + ' -> ' + actionText : actionText;
    }

    if (activeTab === 'webhook') {
      const actionText = `${_tf('webhook', 'Webhook')} -> ${_esc(rule.url || '')}`;
      return condText ? condText + ' -> ' + actionText : actionText;
    }

    const acts = (rule.actions || [])
      .filter(a => a.type && a.target && a.target !== '')
      .map(a => {
        const at = ACTION_TYPES.find(x => x.value === a.type);
        const label = at ? at.label() : a.type;
        const tn = fieldName(a.target);
        return tn ? `${label} → ${tn}` : at ? at.label() : '';
      })
      .filter(Boolean);

    if (!condText && acts.length === 0) return '';
    if (acts.length === 0) return condText;
    if (!condText) return acts.join(', ');
    return condText + ' ⟹ ' + acts.join(', ');
  }

  function buildTestActionSummary(rule) {
    if (activeTab === 'notification') {
      return rule.recipient ? `${_tf('email', 'Email')} -> ${_esc(rule.recipient)}` : _tf('emailNotifications', 'Email notification');
    }
    if (activeTab === 'confirmation') {
      return rule.action === 'redirect'
        ? `${_tf('redirect', 'Redirect')} -> ${_esc(rule.url || '')}`
        : _tf('message', 'Message');
    }
    if (activeTab === 'webhook') {
      return `${_tf('webhook', 'Webhook')} ${String(rule.method || 'POST').toUpperCase()} -> ${_esc(rule.url || '')}`;
    }
    return (rule.actions || []).map(action => {
      const actionType = ACTION_TYPES.find(item => item.value === action.type);
      const label = actionType ? actionType.label() : action.type;
      return action.target ? `${_esc(label)} -> ${getFieldName(action.target)}` : _esc(label);
    }).filter(Boolean).join(', ');
  }

  function renderConflictWarnings(conflicts = analyzeConflicts()) {
    if (!conflicts.length) return '';
    return `
      <div class="efb-logic-conflict-warning">
        <div class="efb-logic-conflict-title">
          <i class="efb bi-exclamation-triangle"></i>
          <strong>${_tf('conflicts', 'Conflicts')}</strong>
        </div>
        <div class="efb-logic-conflict-list">
          ${conflicts.map(conflict => `
            <div class="efb-logic-conflict-item">
              <span>${_esc(conflict.message)}</span>
              <small>${_esc(conflict.hint || (_tf('priority', 'Priority') + ' / ' + _tf('stopProcessing', 'Stop after this rule matches')))}</small>
            </div>
          `).join('')}
        </div>
      </div>`;
  }

  function formatInspectorValue(value) {
    if (Array.isArray(value)) return value.join(', ');
    if (value === undefined || value === null || value === '') return '-';
    return String(value);
  }

  function renderInspectorValues(inspector) {
    const rows = getAllFields()
      .filter(field => Object.prototype.hasOwnProperty.call(inspector.values_map || {}, field.id_))
      .map(field => `
        <div class="efb-logic-inspector-kv">
          <span>${_esc(field.name)}</span>
          <code>${_esc(formatInspectorValue(inspector.values_map[field.id_]))}</code>
        </div>`);
    return rows.length ? rows.join('') : `<div class="efb-logic-inspector-empty">${_tf('noFields', 'No fields found.')}</div>`;
  }

  function renderInspectorEffects(inspector) {
    const items = [
      ['shown_fields', _tf('shown', 'Shown')],
      ['hidden_fields', _tf('hidden', 'Hidden')],
      ['required_fields', _tf('required', 'Required')],
      ['optional_fields', _tf('optional', 'Optional')],
      ['disabled_fields', _tf('disabled', 'Disabled')],
      ['enabled_fields', _tf('enabled', 'Enabled')],
      ['set_values', _tf('setValue', 'Set Value')],
      ['cleared_fields', _tf('clearValue', 'Clear Value')]
    ];
    const rows = items.map(([key, label]) => {
      const value = key === 'set_values' ? Object.keys(inspector.set_values || {}) : (inspector[key] || []);
      if (!value.length) return '';
      return `
        <div class="efb-logic-inspector-effect">
          <span>${_esc(label)}</span>
          <strong>${_esc(value.map(id => getRawFieldName(id)).join(', '))}</strong>
        </div>`;
    }).filter(Boolean);
    if (inspector.errors && inspector.errors.length) {
      rows.push(inspector.errors.map(error => `
        <div class="efb-logic-inspector-effect efb-logic-inspector-error">
          <span>${_tf('warning', 'warning')}</span>
          <strong>${_esc(error.message)}</strong>
        </div>`).join(''));
    }
    return rows.length ? rows.join('') : `<div class="efb-logic-inspector-empty">-</div>`;
  }

  function renderInspectorPanel(inspector) {
    if (!inspector) return '';
    return `
      <div class="efb-logic-inspector">
        <div class="efb-logic-inspector-head">
          <h6><i class="efb bi-search"></i> ${_tf('inspector', 'Inspector')}</h6>
          <span>${inspector.stabilized ? _tf('stable', 'Stable') : _tf('loopWarning', 'Rules did not stabilize (possible loop)')}</span>
        </div>
        <div class="efb-logic-inspector-grid">
          <section>
            <h6>${_tf('finalValues', 'Final values')}</h6>
            ${renderInspectorValues(inspector)}
          </section>
          <section>
            <h6>${_tf('effects', 'Effects')}</h6>
            ${renderInspectorEffects(inspector)}
          </section>
        </div>
        ${renderConflictWarnings(inspector.conflicts || [])}
      </div>`;
  }

  /* ────────────────────────────────────────────
     RENDER — RULES LIST
     ──────────────────────────────────────────── */
  function renderList() {
    view = 'list';
    currentRuleId = null;
    // const mx = Number(efb_var.rtl) == 1 ? 'ms-2' : 'me-2';
    const mx ="";
    const conflicts = analyzeConflicts();
    if (rules.length === 0) {
      return `
        <div class="efb-logic-list">
          <div class="efb-logic-list-header">
            <button type="button" class="efb-logic-test-btn" onclick="EFB_Logic.openTestMode()"><i class="efb bi-play-circle"></i> ${_tf('testMode', 'Test Mode')}</button>
          </div>
          ${renderConflictWarnings(conflicts)}
          <div class="efb-logic-empty">
            <div class="efb-logic-empty-icon"><i class="efb bi-diagram-3 ${mx}"></i></div>
            <p>${_tf('addFirstRule', 'Add your first rule to start building smart forms.')}</p>
            <button type="button" class="efb-logic-add-btn efb-logic-add-btn-center" onclick="EFB_Logic.addRule()"><i class="efb bi-plus-lg"></i> ${_t('add')}</button>
          </div>
        </div>`;
    }

    let cards = '';
    rules.forEach((rule, idx) => {
      const summary = buildSummary(rule);
      /* PRD §9.2: rule card shows a scope badge and the rule priority */
      const badges = `
              <span class="efb-logic-rule-badge efb-logic-badge-scope">${_esc(getTabLabel(activeTab))}</span>
              <span class="efb-logic-rule-badge efb-logic-badge-priority" title="${_tf('priority', 'Priority')}">#${Number(rule.priority || 10)}</span>
              ${rule.stop_processing ? `<span class="efb-logic-rule-badge efb-logic-badge-stop" title="${_tf('stopProcessing', 'Stop after this rule matches')}"><i class="efb bi-sign-stop"></i></span>` : ''}`;
      cards += `
        <div class="efb-logic-rule-card ${rule.enabled ? '' : 'disabled'}" data-rule-id="${_esc(rule.id)}">
          <div class="efb-logic-rule-top">
            <label class="efb-logic-toggle efb-logic-rule-toggle">
              <input type="checkbox" ${rule.enabled ? 'checked' : ''} onchange="EFB_Logic.toggleRule('${_esc(rule.id)}')">
              <span class="efb-logic-toggle-track"></span>
              <span class="efb-logic-toggle-thumb"></span>
            </label>
            <div class="efb-logic-rule-info" onclick="EFB_Logic.editRule('${_esc(rule.id)}')">
              <p class="efb-logic-rule-name">${_esc(rule.name || (_t('conlog') + ' ' + (idx + 1)))}${badges}</p>
              <p class="efb-logic-rule-summary">${summary || '<span class="efb-logic-not-configured">⚙ Not configured — click edit</span>'}</p>
            </div>
            <div class="efb-logic-rule-actions">
              <button type="button" title="${_tf('duplicate', 'Duplicate')}" onclick="EFB_Logic.duplicateRule('${_esc(rule.id)}')"><i class="efb bi-copy"></i></button>
              <button type="button" title="Edit" onclick="EFB_Logic.editRule('${_esc(rule.id)}')"><i class="efb bi-pencil"></i></button>
              <button type="button" class="efb-logic-delete-btn" title="${_t('delete')}" onclick="EFB_Logic.deleteRule('${_esc(rule.id)}')"><i class="efb bi-trash"></i></button>
            </div>
          </div>
        </div>`;
    });

    const ruleLimit = getRuleLimit(activeTab);
    const atRuleLimit = rules.length >= ruleLimit;
    const addLockAttr = atRuleLimit
      ? ` title="${_esc(limitMessage(ruleLimit, getTabLabel(activeTab)))}"`
      : '';
    return `
      <div class="efb-logic-list">
        <div class="efb-logic-list-header">
          <button type="button" class="efb-logic-test-btn" onclick="EFB_Logic.openTestMode()"><i class="efb bi-play-circle"></i> ${_tf('testMode', 'Test Mode')}</button>
          ${renderExportImportButtons()}
          <button type="button" class="efb-logic-add-btn${atRuleLimit ? ' efb-logic-btn-locked' : ''}"${addLockAttr} onclick="EFB_Logic.addRule()"><i class="efb ${atRuleLimit ? 'bi-gem' : 'bi-plus-lg'}"></i> ${_t('add')}</button>
        </div>
        ${renderConflictWarnings(conflicts)}
        ${cards}
      </div>`;
  }

  /* Export/Import all rule sets as a portable JSON file (Pro, PRD §17) */
  function renderExportImportButtons() {
    const locked = !isProPlan();
    const lockAttr = locked ? ` title="${_esc(proOnlyMessage())}"` : '';
    return `
          <button type="button" class="efb-logic-test-btn${locked ? ' efb-logic-btn-locked' : ''}"${lockAttr} onclick="EFB_Logic.exportRules()"><i class="efb ${locked ? 'bi-gem' : 'bi-download'}"></i> ${_tf('exportRules', 'Export')}</button>
          <button type="button" class="efb-logic-test-btn${locked ? ' efb-logic-btn-locked' : ''}"${lockAttr} onclick="EFB_Logic.importRules()"><i class="efb ${locked ? 'bi-gem' : 'bi-upload'}"></i> ${_tf('importRules', 'Import')}</button>
          <input type="file" id="efb-logic-import-file" accept="application/json,.json" class="d-none" style="display:none" onchange="EFB_Logic.importRulesFile(this)">`;
  }

  function renderTestField(field) {
    const current = testValues[field.id_] || '';
    const cat = getFieldCategory(field.type);
    let input = '';
    if (cat === 'choice') {
      let options = `<option value="">${_t('select')}</option>`;
      getFieldOptions(field.id_).forEach(option => {
        const label = getOptionLabel(field.id_, option.id_);
        options += `<option value="${_esc(option.id_)}" ${current === option.id_ ? 'selected' : ''}>${label}</option>`;
      });
      input = `<select class="efb-logic-test-input" onchange="EFB_Logic.updateTestValue('${_esc(field.id_)}',this.value)">${options}</select>`;
    } else if (cat === 'bool') {
      input = `<select class="efb-logic-test-input" onchange="EFB_Logic.updateTestValue('${_esc(field.id_)}',this.value)">
        <option value="">${_t('select')}</option>
        <option value="yes" ${current === 'yes' ? 'selected' : ''}>Yes</option>
        <option value="no" ${current === 'no' ? 'selected' : ''}>No</option>
      </select>`;
    } else {
      const type = cat === 'number' || cat === 'payment' ? 'number' : (cat === 'date' ? 'date' : 'text');
      input = `<input type="${type}" class="efb-logic-test-input" value="${_esc(current)}" onchange="EFB_Logic.updateTestValue('${_esc(field.id_)}',this.value)">`;
    }
    return `
      <label class="efb-logic-test-field">
        <span>${_esc(field.name)}</span>
        ${input}
      </label>`;
  }

  function renderTestResults() {
    if (!testResults.length && !testInspector) return '';
    const blockedBanner = testInspector && testInspector.submit_blocked ? `
      <div class="efb-logic-conflict-warning efb-logic-submit-blocked">
        <div class="efb-logic-conflict-title">
          <i class="efb bi-sign-stop"></i>
          <strong>${_tf('submitBlocked', 'Submission is not allowed for the current answers.')}</strong>
        </div>
        ${(testInspector.end_form && testInspector.end_form.message) ? `<div class="efb-logic-conflict-item"><span>${_esc(testInspector.end_form.message)}</span></div>` : ''}
        ${(testInspector.block_messages || []).map(message => `<div class="efb-logic-conflict-item"><span>${_esc(message.value)}</span></div>`).join('')}
      </div>` : '';
    /* PRD §17: the Inspector (debugger) is Pro-only; Free Plus keeps the
     * simple matched/not-matched preview list. */
    const inspectorHtml = isProPlan()
      ? renderInspectorPanel(testInspector)
      : (testInspector ? `
      <div class="efb-logic-inspector efb-logic-pro-panel">
        <div class="efb-logic-inspector-head">
          <h6><i class="efb bi-search"></i> ${_tf('inspector', 'Inspector')} <i class="efb bi-gem efb-logic-pro-gem"></i></h6>
          <span>${_tf('proVersion', 'Pro Version')}</span>
        </div>
      </div>` : '');
    return `
      <div class="efb-logic-test-results">
        ${testResults.map(result => `
          <div class="efb-logic-test-result ${result.status}">
            <div class="efb-logic-test-result-main">
              <i class="efb ${result.status === 'matched' ? 'bi-check-circle' : (result.status === 'skipped' || result.status === 'blocked' ? 'bi-dash-circle' : 'bi-x-circle')}"></i>
              <strong>${_esc(result.name)}</strong>
              <span>${_esc(result.label)}</span>
            </div>
            ${result.actionText ? `<p>${result.actionText}</p>` : ''}
          </div>
        `).join('')}
      </div>
      ${blockedBanner}
      ${inspectorHtml}`;
  }

  /* Extra Test Mode inputs for non-field sources the rules reference */
  function renderTestEnvFields() {
    const needs = collectTestEnvNeeds();
    let html = '';
    needs.query.forEach(param => {
      const key = '__query__' + param;
      html += `
      <label class="efb-logic-test-field">
        <span>${_tf('urlParam', 'URL parameter')}: ${_esc(param)}</span>
        <input type="text" class="efb-logic-test-input" value="${_esc(testValues[key] || '')}" onchange="EFB_Logic.updateTestValue('${_esc(key)}',this.value)">
      </label>`;
    });
    if (needs.user) {
      html += `
      <label class="efb-logic-test-field">
        <span>${_tf('userSource', 'User')}: ${_tf('loggedIn', 'Logged in')}</span>
        <select class="efb-logic-test-input" onchange="EFB_Logic.updateTestValue('__user_logged_in',this.value)">
          <option value="no" ${testValues.__user_logged_in !== 'yes' ? 'selected' : ''}>${_tf('loggedOut', 'Logged out')}</option>
          <option value="yes" ${testValues.__user_logged_in === 'yes' ? 'selected' : ''}>${_tf('loggedIn', 'Logged in')}</option>
        </select>
      </label>
      <label class="efb-logic-test-field">
        <span>${_tf('userSource', 'User')}: ${_tf('userRole', 'Role')}</span>
        <input type="text" class="efb-logic-test-input" value="${_esc(testValues.__user_role || '')}" placeholder="subscriber" onchange="EFB_Logic.updateTestValue('__user_role',this.value)">
      </label>`;
    }
    if (needs.step) {
      html += `
      <label class="efb-logic-test-field">
        <span>${_tf('currentStep', 'Current step')}</span>
        <input type="number" min="1" step="1" class="efb-logic-test-input" value="${_esc(testValues.__current_step || '')}" placeholder="1" onchange="EFB_Logic.updateTestValue('__current_step',this.value)">
      </label>`;
    }
    return html;
  }

  function renderTestMode() {
    view = 'test';
    currentRuleId = null;
    const fields = getAllFields();
    return `
      <div class="efb-logic-test-panel">
        <div class="efb-logic-editor-header">
          <button type="button" class="efb-logic-back-btn" onclick="EFB_Logic.backToList()"><i class="efb bi-arrow-left"></i></button>
          <div class="efb-logic-editor-title">
            <h5>${_tf('testMode', 'Test Mode')}</h5>
          </div>
        </div>
        <div class="efb-logic-test-body">
          ${renderConflictWarnings(analyzeConflicts())}
          <div class="efb-logic-test-fields">
            ${fields.length ? fields.map(renderTestField).join('') : `<div class="efb-logic-group-empty">${_tf('noFields', 'No fields found.')}</div>`}
            ${renderTestEnvFields()}
          </div>
          <div class="efb-logic-test-runbar">
            <button type="button" class="efb-logic-apply-btn" onclick="EFB_Logic.runTest()"><i class="efb bi-play-circle"></i> ${_tf('runTest', 'Run Test')}</button>
          </div>
          ${renderTestResults()}
        </div>
      </div>`;
  }

  /* ────────────────────────────────────────────
     RENDER — RULE EDITOR
     ──────────────────────────────────────────── */
  function renderEditor(rule) {
    if (!rule) return renderList();
    view = 'editor';
    currentRuleId = rule.id;
    const ruleIdx = rules.findIndex(r => r.id === rule.id);

    const fields = getAllFields();
    ensureConditionGroup(rule);

    /* ── conditions ── */
    const conditionRows = renderConditionGroup(rule.conditions, '', fields, true);

    /* ── actions ── */
    let actionRows = '';
    if (activeTab === 'field') {
      (rule.actions || []).forEach((a, ai) => {
        actionRows += renderActionRow(a, ai);
      });
    } else if (activeTab === 'notification') {
      actionRows = renderNotificationSettings(rule);
    } else if (activeTab === 'confirmation') {
      actionRows = renderConfirmationSettings(rule);
    } else {
      actionRows = renderWebhookSettings(rule);
    }
    const actionAddButton = activeTab === 'field' ? `
              <div class="efb-logic-group-actions">
                <button type="button" class="efb-logic-add-row-btn" onclick="EFB_Logic.addAction()">
                  <i class="efb bi-plus"></i>${_t('add')}
                </button>
              </div>` : '';
    /* Priority + stop_processing are Pro-only controls */
    const proPlan = isProPlan();
    const proLockAttr = proPlan ? '' : ` title="${_esc(proOnlyMessage())}" onclick="EFB_Logic.notifyProFeature()"`;
    const proGem = proPlan ? '' : ' <i class="efb bi-gem efb-logic-pro-gem"></i>';
    const currentConflicts = analyzeConflicts().filter(conflict =>
      conflict.entries.some(entry => entry.rule && entry.rule.id === rule.id)
    );
    const stopProcessingField = activeTab === 'field' ? `
            <label class="efb-logic-stop-field${proPlan ? '' : ' efb-logic-pro-locked'}"${proLockAttr}>
              <span class="efb-logic-toggle efb-logic-stop-toggle">
                <input type="checkbox" ${rule.stop_processing ? 'checked' : ''} ${proPlan ? '' : 'disabled'}
                       onchange="EFB_Logic.setStopProcessing(this.checked)">
                <span class="efb-logic-toggle-track"></span>
                <span class="efb-logic-toggle-thumb"></span>
              </span>
              <span class="efb-logic-stop-label">${efb_var.text.stopProcessing || 'Stop after this rule matches'}${proGem}</span>
            </label>` : '';

    return `
      <div class="efb-logic-editor">
        <div class="efb-logic-editor-header">
          <button type="button" class="efb-logic-back-btn" onclick="EFB_Logic.backToList()"><i class="efb bi-arrow-left"></i></button>
          <div class="efb-logic-editor-title">
            <input type="text" value="${_esc(rule.name || (_t('conlog') + ' ' + (ruleIdx + 1)))}"
                   onchange="EFB_Logic.renameRule(this.value)" placeholder="${_t('conlog')}">
          </div>
        </div>
        <div class="efb-logic-editor-body">
          ${renderConflictWarnings(currentConflicts)}
          <!-- IF section -->
          <div class="efb-logic-section">
            <span class="efb-logic-section-label efb-if-label">${_t('logicIf')}</span>
            <div id="efb-logic-conditions">
              ${conditionRows}
            </div>
          </div>

          <!-- Connector -->
          <div class="efb-logic-connector">
            <div class="efb-logic-connector-dot"></div>
            <div class="efb-logic-connector-line"></div>
            <div class="efb-logic-connector-diamond"></div>
            <div class="efb-logic-connector-line"></div>
            <div class="efb-logic-connector-dot"></div>
          </div>

          <!-- THEN section -->
          <div class="efb-logic-section">
            <span class="efb-logic-section-label efb-then-label">${_t('logicThen')}</span>
            <div class="efb-logic-group efb-logic-actions-group">
              <div id="efb-logic-actions">
                ${actionRows}
              </div>
              ${actionAddButton}
            </div>
          </div>
        </div>
        <div class="efb-logic-editor-footer">
          <div class="efb-logic-footer-options">
            <label class="efb-logic-priority-field${proPlan ? '' : ' efb-logic-pro-locked'}"${proLockAttr}>
              <span class="efb-logic-priority-label">${_t('priority') || 'Priority'}${proGem}</span>
              <input type="number" class="efb-logic-priority-input" min="0" step="1" value="${Number(rule.priority || 10)}" ${proPlan ? '' : 'disabled'}
                     onchange="EFB_Logic.setPriority(this.value)">
            </label>
            ${stopProcessingField}
          </div>
          <button type="button" class="efb-logic-apply-btn" onclick="EFB_Logic.applyRule()">
            ${_t('save')}
          </button>
        </div>
      </div>`;
  }

  function renderConditionGroup(group, path, fields, isRoot = false) {
    group.operator = String(group.operator || 'AND').toUpperCase() === 'OR' ? 'OR' : 'AND';
    if (!Array.isArray(group.items)) group.items = [];

    const conditionLimit = getConditionLimit();
    const conditionAtLimit = countGroupConditions(group) >= conditionLimit;
    const groupProLocked = !isProPlan();
    const pathAttr = _esc(path || '');
    const title = isRoot ? _tf('logicConditions', 'Conditions') : _tf('logicGroup', 'Group');
    const removeBtn = isRoot ? '' : `
      <button type="button" class="efb-logic-group-remove-btn" onclick="EFB_Logic.removeGroup('${pathAttr}')" title="${_t('delete')}">
        <i class="efb bi-trash"></i>
      </button>`;
    const emptyState = group.items.length ? '' : `
      <div class="efb-logic-group-empty">${_tf('logicGroupEmpty', 'Add a condition or a group.')}</div>`;

    let itemHtml = '';
    group.items.forEach((item, index) => {
      const childPath = path ? `${path}.${index}` : String(index);
      if (index > 0) {
        const connector = getItemConnector(group, item);
        const childPathAttr = _esc(childPath);
        itemHtml += `
          <div class="efb-logic-operator-toggle efb-logic-item-connector">
            <button type="button" class="${connector === 'AND' ? 'active' : ''}" onclick="EFB_Logic.setItemConnector('${childPathAttr}','AND')">${_t('and')}</button>
            <button type="button" class="${connector === 'OR' ? 'active' : ''}" onclick="EFB_Logic.setItemConnector('${childPathAttr}','OR')">${_t('or')}</button>
          </div>`;
      }
      itemHtml += isGroupItem(item)
        ? renderConditionGroup(item, childPath, fields, false)
        : renderConditionRow(item, childPath, fields);
    });

    return `
      <div class="efb-logic-group ${isRoot ? 'efb-logic-root-group' : ''}" data-path="${pathAttr}">
        <div class="efb-logic-group-header">
          <div class="efb-logic-group-title">
            <i class="efb bi-diagram-3"></i>
            <span>${title}</span>
          </div>
          <div class="efb-logic-group-controls">
            <button type="button" class="efb-logic-negate-btn${group.negate ? ' active' : ''}${isProPlan() ? '' : ' efb-logic-btn-locked'}"
                    title="${isProPlan() ? _tf('negateGroup', 'NOT — invert this group (NAND/NOR)') : _esc(proOnlyMessage())}"
                    onclick="EFB_Logic.toggleGroupNegate('${pathAttr}')">${_tf('notOperator', 'NOT')}${isProPlan() ? '' : ' <i class="efb bi-gem efb-logic-pro-gem"></i>'}</button>
            ${removeBtn}
          </div>
        </div>
        <div class="efb-logic-group-body">
          ${emptyState}
          ${itemHtml}
        </div>
        <div class="efb-logic-group-actions">
          <button type="button" class="efb-logic-add-row-btn${conditionAtLimit ? ' efb-logic-btn-locked' : ''}"${conditionAtLimit ? ` title="${_esc(limitMessage(conditionLimit, _tf('logicConditions', 'Conditions')))}"` : ''} onclick="EFB_Logic.addCondition('${pathAttr}')">
            <i class="efb ${conditionAtLimit ? 'bi-gem' : 'bi-plus'}"></i>${_t('add')} ${_tf('logicCondition', 'Condition')}
          </button>
          <button type="button" class="efb-logic-add-row-btn efb-logic-add-group-btn${groupProLocked ? ' efb-logic-btn-locked' : ''}"${groupProLocked ? ` title="${_esc(proOnlyMessage())}"` : ''} onclick="EFB_Logic.addGroup('${pathAttr}')">
            <i class="efb ${groupProLocked ? 'bi-gem' : 'bi-diagram-3'}"></i>${_t('add')} ${_tf('logicGroup', 'Group')}
          </button>
        </div>
      </div>`;
  }

  /* Operators available for a condition, source-aware */
  function getOperatorsForCondition(cond) {
    const source = String(cond.source || 'field');
    if (source === 'query_param') return SOURCE_OPERATORS.query_param;
    if (source === 'user') return cond.field_id === 'role' ? SOURCE_OPERATORS.user_role : SOURCE_OPERATORS.user_logged_in;
    if (source === 'current_step') return SOURCE_OPERATORS.current_step;
    return cond.field_id ? getOperatorsForField(cond.field_id) : OPERATORS_BY_CATEGORY.text;
  }

  function renderConditionRow(cond, idx, fields) {
    if (!fields) fields = getAllFields();
    const pathAttr = _esc(String(idx));
    const source = String(cond.source || 'field');
    const ops = getOperatorsForCondition(cond);
    const needsValue = !NO_VALUE_OPERATORS.has(cond.compare);
    const hasOpts = source === 'field' && cond.field_id && fieldHasOptions(cond.field_id);

    /* detect payment amount operator (needs number input) */
    const fObj = source === 'field' && typeof valj_efb !== 'undefined' ? valj_efb.find(x => x.id_ === cond.field_id) : null;
    const isPaymentAmountOp = fObj && getFieldCategory(fObj.type) === 'payment' &&
      (cond.compare === 'amount_eq' || cond.compare === 'amount_gt' || cond.compare === 'amount_lt');

    /* source select (Field / URL parameter / User / Current step) */
    let sourceOpts = '';
    CONDITION_SOURCES.forEach(item => {
      sourceOpts += `<option value="${item.value}" ${item.value === source ? 'selected' : ''}>${_esc(item.label())}</option>`;
    });
    const sourceSelect = `<select class="efb-logic-source-select" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','source',this.value)">${sourceOpts}</select>`;

    /* subject select/input depending on source */
    let subjectHtml = '';
    if (source === 'query_param') {
      subjectHtml = `<input type="text" class="efb-logic-value-input efb-logic-param-input" value="${_esc(cond.param || cond.field_id || '')}" placeholder="utm_source" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','param',this.value)">`;
    } else if (source === 'user') {
      subjectHtml = `<select class="efb-logic-field-select" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','field_id',this.value)">
        <option value="logged_in" ${cond.field_id === 'logged_in' ? 'selected' : ''}>${_tf('loggedIn', 'Logged in')}</option>
        <option value="role" ${cond.field_id === 'role' ? 'selected' : ''}>${_tf('userRole', 'Role')}</option>
      </select>`;
    } else if (source === 'current_step') {
      subjectHtml = '';
    } else {
      let fieldOpts = `<option value="">${_t('select')} ${_t('field')}</option>`;
      fields.forEach(f => {
        fieldOpts += `<option value="${_esc(f.id_)}" ${f.id_ === cond.field_id ? 'selected' : ''}>${_esc(f.name)}</option>`;
      });
      subjectHtml = `<select class="efb-logic-field-select" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','field_id',this.value)">${fieldOpts}</select>`;
    }

    /* operator select */
    let opOpts = '';
    ops.forEach(op => {
      opOpts += `<option value="${op}" ${op === cond.compare ? 'selected' : ''}>${_t(OPERATOR_LABELS[op] || op)}</option>`;
    });

    /* value input */
    let valueHtml = '';
    if (needsValue) {
      if (source === 'user' && cond.field_id !== 'role') {
        valueHtml = `<select class="efb-logic-value-select" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','value',this.value)">
          <option value="">${_t('select')}</option>
          <option value="yes" ${cond.value === 'yes' ? 'selected' : ''}>${_tf('loggedIn', 'Logged in')}</option>
          <option value="no" ${cond.value === 'no' ? 'selected' : ''}>${_tf('loggedOut', 'Logged out')}</option>
        </select>`;
      } else if (source === 'current_step') {
        valueHtml = `<input type="number" min="1" step="1" class="efb-logic-value-input" value="${_esc(cond.value || '')}" placeholder="1" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','value',this.value)">`;
      } else if (DATE_RANGE_OPERATORS.has(cond.compare)) {
        const dateParts = String(cond.value || '').split(',');
        const fromVal = _esc((dateParts[0] || '').trim());
        const toVal = _esc((dateParts[1] || '').trim());
        valueHtml = `<div class="efb-logic-value-range">
          <input type="date" class="efb-logic-value-input efb-logic-value-range-min" value="${fromVal}" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','value',this.value+','+this.parentElement.querySelector('.efb-logic-value-range-max').value)">
          <span class="efb-logic-value-range-sep">–</span>
          <input type="date" class="efb-logic-value-input efb-logic-value-range-max" value="${toVal}" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','value',this.parentElement.querySelector('.efb-logic-value-range-min').value+','+this.value)">
        </div>`;
      } else if (cond.compare === 'date_before' || cond.compare === 'date_after') {
        valueHtml = `<input type="date" class="efb-logic-value-input" value="${_esc(cond.value || '')}" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','value',this.value)">`;
      } else if (RANGE_OPERATORS.has(cond.compare)) {
        const rangeParts = String(cond.value || '').split(',');
        const minVal = _esc((rangeParts[0] || '').trim());
        const maxVal = _esc((rangeParts[1] || '').trim());
        valueHtml = `<div class="efb-logic-value-range">
          <input type="number" step="any" class="efb-logic-value-input efb-logic-value-range-min" value="${minVal}" placeholder="${_t('min')}" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','value',this.value+','+this.parentElement.querySelector('.efb-logic-value-range-max').value)">
          <span class="efb-logic-value-range-sep">–</span>
          <input type="number" step="any" class="efb-logic-value-input efb-logic-value-range-max" value="${maxVal}" placeholder="${_t('max')}" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','value',this.parentElement.querySelector('.efb-logic-value-range-min').value+','+this.value)">
        </div>`;
      } else if (isPaymentAmountOp) {
        valueHtml = `<input type="number" min="0" step="0.01" class="efb-logic-value-input" value="${_esc(cond.value || '')}" placeholder="0" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','value',this.value)">`;
      } else if (hasOpts) {
        const fopts = getFieldOptions(cond.field_id);
        let vOpts = `<option value="">${_t('select')}</option>`;
        /* for yesNo type */
        if (fObj && fObj.type === 'yesNo') {
          vOpts += `<option value="yes" ${cond.value === 'yes' ? 'selected' : ''}>Yes</option>`;
          vOpts += `<option value="no" ${cond.value === 'no' ? 'selected' : ''}>No</option>`;
        } else {
          fopts.forEach(o => {
            vOpts += `<option value="${_esc(o.id_)}" ${o.id_ === cond.value ? 'selected' : ''}>${_esc(o.value)}</option>`;
          });
        }
        valueHtml = `<select class="efb-logic-value-select" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','value',this.value)">${vOpts}</select>`;
      } else {
        valueHtml = `<input type="text" class="efb-logic-value-input" value="${_esc(cond.value || '')}" placeholder="${_t('select')}" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','value',this.value)">`;
      }
    }

    return `
      <div class="efb-logic-condition-row" data-ci="${pathAttr}">
        ${sourceSelect}
        ${subjectHtml}
        <select class="efb-logic-operator-select" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','compare',this.value)">${opOpts}</select>
        ${valueHtml}
        <button type="button" class="efb-logic-remove-btn" onclick="EFB_Logic.removeCondition('${pathAttr}')" title="${_t('delete')}"><i class="efb bi-x-lg"></i></button>
      </div>`;
  }

  function renderActionRow(action, idx) {
    /* action type select — Pro-only actions are visible but disabled with a gem */
    const proPlanRow = isProPlan();
    let atOpts = `<option value="">${_t('select')}</option>`;
    ACTION_TYPES.forEach(at => {
      const locked = PRO_ONLY_ACTIONS.has(at.value) && !proPlanRow;
      atOpts += `<option value="${at.value}" ${at.value === action.type ? 'selected' : ''} ${locked ? 'disabled' : ''}>${at.label()}${locked ? ' 💎' : ''}</option>`;
    });

    /* target select — hidden for form-level actions (block_submit / end_form) */
    const isTargetless = TARGETLESS_ACTIONS.has(action.type);
    let targetHtml = '';
    if (!isTargetless) {
      const targets = action.type ? getTargetsForAction(action.type) : getAllFields();
      let tOpts = `<option value="">${_t('select')}</option>`;
      targets.forEach(t => {
        tOpts += `<option value="${_esc(t.id_)}" ${t.id_ === action.target ? 'selected' : ''}>${_esc(t.name)}</option>`;
      });
      targetHtml = `<select class="efb-logic-action-target-select" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'target',this.value)">${tOpts}</select>`;
    }

    /* value input for set_value / show_message actions */
    let valueHtml = '';
    if (action.type === 'show_message' || isTargetless) {
      valueHtml = `<input type="text" class="efb-logic-value-input" value="${_esc(action.value || '')}" placeholder="${_t('enterText') || 'Message&hellip;'}" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'value',this.value)">` ;
    }
    if (TEXT_VALUE_ACTIONS.has(action.type)) {
      valueHtml = `<input type="text" class="efb-logic-value-input" value="${_esc(action.value || '')}" placeholder="${_t('enterTheValueThisField') || 'Value&hellip;'}" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'value',this.value)">` ;
    }
    if (action.type === 'copy_value') {
      let sOpts = `<option value="">${_t('select')} ${_t('field')}</option>`;
      getAllFields().forEach(f => {
        if (f.id_ === action.target) return; /* copying a field onto itself is a no-op */
        sOpts += `<option value="${_esc(f.id_)}" ${f.id_ === action.value ? 'selected' : ''}>${_esc(f.name)}</option>`;
      });
      valueHtml = `<select class="efb-logic-value-select" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'value',this.value)">${sOpts}</select>`;
    }
    if (action.type === 'set_value') {
      const afActive = isAutofillActive();
      const afKeys   = getAutofillSourceKeys();
      const vType    = action.value_type || 'static';
      const phStatic = _t('enterTheValueThisField') || 'Value&hellip;';
      if (afActive) {
        /* Source-type selector: Static | Dataset column */
        let vtOpts = `<option value="static" ${vType === 'static' ? 'selected' : ''}>${_tf('staticValue', 'Static')}</option>`;
        if (afKeys.length > 0) {
          vtOpts += `<option value="autofill_key" ${vType === 'autofill_key' ? 'selected' : ''}>⛛ ${efb_var.text.datas || 'Dataset'}</option>`;
        }
        const vtSel = `<select class="efb-logic-value-type-sel" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'value_type',this.value)" title="${efb_var.text.datas || 'Value source'}">${vtOpts}</select>`;
        if (vType === 'autofill_key' && afKeys.length > 0) {
          /* Dropdown: pick which dataset column to copy into target field */
          let kOpts = `<option value="">—</option>`;
          afKeys.forEach(k => { kOpts += `<option value="${_esc(k)}" ${action.value === k ? 'selected' : ''}>${_esc(k)}</option>`; });
          valueHtml = vtSel + `<select class="efb-logic-value-select" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'value',this.value)">${kOpts}</select>`;
        } else {
          /* Static text input + optional "load keys" button when keys not yet fetched */
          valueHtml = vtSel + `<input type="text" class="efb-logic-value-input" value="${_esc(action.value || '')}" placeholder="${phStatic}" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'value',this.value)">` +
            (afKeys.length === 0 ? `<button type="button" class="efb-logic-remove-btn" style="color:#6c757d" onclick="EFB_Logic.loadAutofillKeys()" title="${efb_var.text.datasetsTab || 'Load dataset columns'}"><i class="efb bi-database"></i></button>` : '');
        }
      } else {
        /* Autofill not active — plain static input */
        valueHtml = `<input type="text" class="efb-logic-value-input" value="${_esc(action.value || '')}" placeholder="${phStatic}" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'value',this.value)">` ;
      }
    }
    if (action.type === 'calculate') {
      let tokenOptions = `<option value="">${_tf('insertField', 'Insert field')}</option>`;
      getAllFields().forEach(field => {
        tokenOptions += `<option value="${_esc(field.id_)}">${_esc(field.name)}</option>`;
      });
      const decimals = action.decimals !== undefined && action.decimals !== null && action.decimals !== '' ? Number(action.decimals) : '';
      valueHtml = `
        <input type="text" class="efb-logic-value-input efb-logic-formula-input" value="${_esc(action.value || '')}" placeholder="{price} * {qty}" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'value',this.value)">
        <input type="number" class="efb-logic-decimals-input" min="0" max="6" step="1" value="${_esc(String(decimals))}" placeholder="${_tf('decimals', 'Decimals')}" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'decimals',this.value)">
        <select class="efb-logic-calc-token-select" data-ai="${idx}" onchange="EFB_Logic.insertCalculationToken(${idx},this.value);this.value=''">${tokenOptions}</select>`;
    }

    return `
      <div class="efb-logic-action-row" data-ai="${idx}">
        <select class="efb-logic-action-type-select" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'type',this.value)">${atOpts}</select>
        ${targetHtml}
        ${valueHtml}
        <button type="button" class="efb-logic-remove-btn" onclick="EFB_Logic.removeAction(${idx})" title="${_t('delete')}"><i class="efb bi-x-lg"></i></button>
      </div>`;
  }

  /* ────────────────────────────────────────────
     MODAL MANAGEMENT
     ──────────────────────────────────────────── */
  function renderNotificationSettings(rule) {
    const template = rule.template || 'default';
    const ccText = Array.isArray(rule.cc) ? rule.cc.join(', ') : String(rule.cc || '');
    const bccText = Array.isArray(rule.bcc) ? rule.bcc.join(', ') : String(rule.bcc || '');
    return `
      <div class="efb-logic-action-row efb-logic-settings-row">
        <label class="efb-logic-setting-field">
          <span>${_tf('email', 'Email')}</span>
          <input type="email" class="efb-logic-value-input" value="${_esc(rule.recipient || '')}" placeholder="name@example.com"
                 onchange="EFB_Logic.updateNotification('recipient',this.value)">
        </label>
        <label class="efb-logic-setting-field">
          <span>${_tf('subject', 'Subject')} <small class="efb-logic-hint">{field_id}</small></span>
          <input type="text" class="efb-logic-value-input" value="${_esc(rule.subject || '')}" placeholder="${_tf('emailNotifications', 'Email notification')}"
                 onchange="EFB_Logic.updateNotification('subject',this.value)">
        </label>
        <label class="efb-logic-setting-field">
          <span>${_tf('template', 'Template')}</span>
          <select class="efb-logic-value-select" onchange="EFB_Logic.updateNotification('template',this.value)">
            <option value="default" ${template === 'default' ? 'selected' : ''}>${_tf('default', 'Default')}</option>
          </select>
        </label>
      </div>
      <div class="efb-logic-action-row efb-logic-settings-row">
        <label class="efb-logic-setting-field">
          <span>CC</span>
          <input type="text" class="efb-logic-value-input" value="${_esc(ccText)}" placeholder="a@example.com, b@example.com"
                 onchange="EFB_Logic.updateNotification('cc',this.value)">
        </label>
        <label class="efb-logic-setting-field">
          <span>BCC</span>
          <input type="text" class="efb-logic-value-input" value="${_esc(bccText)}" placeholder="c@example.com"
                 onchange="EFB_Logic.updateNotification('bcc',this.value)">
        </label>
      </div>`;
  }

  /* Curated bootstrap-icons choices for the confirmation done screen. */
  const CONFIRMATION_ICONS = [
    'bi-hand-thumbs-up', 'bi-check-circle', 'bi-check2-circle', 'bi-patch-check',
    'bi-trophy', 'bi-star', 'bi-heart', 'bi-emoji-smile', 'bi-envelope-check', 'bi-gift'
  ];

  function renderConfirmationColorField(labelKey, labelFallback, prop, value) {
    const hex = /^#[0-9a-fA-F]{6}$/.test(String(value || '')) ? value : '';
    return `
        <label class="efb-logic-setting-field">
          <span>${_tf(labelKey, labelFallback)}</span>
          <span class="efb-logic-color-wrap" style="display:flex;align-items:center;gap:4px;">
            <input type="color" class="efb-logic-value-input efb-logic-color-input" value="${hex || '#212529'}"
                   title="${_tf(labelKey, labelFallback)}"
                   onchange="EFB_Logic.updateConfirmation('${prop}',this.value)">
            <button type="button" class="efb-logic-icon-btn" title="${_tf('defaultOpt', 'Default')}" ${hex ? '' : 'disabled'}
                    onclick="EFB_Logic.updateConfirmation('${prop}','')">&times;</button>
          </span>
        </label>`;
  }

  function renderConfirmationSettings(rule) {
    const action = rule.action === 'redirect' ? 'redirect' : 'message';
    const actionInput = action === 'redirect'
      ? `<input type="url" class="efb-logic-value-input" value="${_esc(rule.url || '')}" placeholder="https://example.com/thanks"
                onchange="EFB_Logic.updateConfirmation('url',this.value)">`
      : `<textarea class="efb-logic-value-input efb-logic-message-input" placeholder="${_tf('thankYou', 'Thank you')}"
                   onchange="EFB_Logic.updateConfirmation('message',this.value)">${_esc(rule.message || '')}</textarea>`;

    let messageExtras = '';
    if (action === 'message') {
      const icon = CONFIRMATION_ICONS.includes(rule.icon) ? rule.icon : '';
      const iconOptions = [`<option value="">${_tf('defaultOpt', 'Default')}</option>`]
        .concat(CONFIRMATION_ICONS.map(ic =>
          `<option value="${ic}" ${ic === icon ? 'selected' : ''}>${ic.replace(/^bi-/, '').replace(/-/g, ' ')}</option>`))
        .join('');
      messageExtras = `
      <div class="efb-logic-action-row efb-logic-settings-row">
        <label class="efb-logic-setting-field">
          <span>${_tf('doneTitle', 'Done title')}</span>
          <input type="text" class="efb-logic-value-input" value="${_esc(rule.done || '')}" placeholder="${_tf('done', 'Done')}"
                 onchange="EFB_Logic.updateConfirmation('done',this.value)">
        </label>
        <label class="efb-logic-setting-field">
          <span>${_tf('doneIcon', 'Icon')} <i class="efb ${icon || 'bi-hand-thumbs-up'}"></i></span>
          <select class="efb-logic-value-select" onchange="EFB_Logic.updateConfirmation('icon',this.value)">
            ${iconOptions}
          </select>
        </label>
        <label class="efb-logic-setting-field">
          <span>${_tf('trackingCodeLabel', 'Tracking code label')}</span>
          <input type="text" class="efb-logic-value-input" value="${_esc(rule.tracking_label || '')}" placeholder="${_tf('trackingCode', 'Tracking code')}"
                 onchange="EFB_Logic.updateConfirmation('tracking_label',this.value)">
        </label>
      </div>
      <div class="efb-logic-action-row efb-logic-settings-row">
        ${renderConfirmationColorField('iconColor', 'Icon color', 'icon_color', rule.icon_color)}
        ${renderConfirmationColorField('titleColor', 'Title color', 'title_color', rule.title_color)}
        ${renderConfirmationColorField('messageColor', 'Message color', 'message_color', rule.message_color)}
      </div>`;
    }

    return `
      <div class="efb-logic-action-row efb-logic-settings-row">
        <label class="efb-logic-setting-field">
          <span>${_tf('action', 'Action')}</span>
          <select class="efb-logic-value-select" onchange="EFB_Logic.updateConfirmation('action',this.value)">
            <option value="message" ${action === 'message' ? 'selected' : ''}>${_tf('message', 'Message')}</option>
            <option value="redirect" ${action === 'redirect' ? 'selected' : ''}>${_tf('redirect', 'Redirect')}</option>
          </select>
        </label>
        <label class="efb-logic-setting-field efb-logic-setting-field-wide">
          <span>${action === 'redirect' ? _tf('url', 'URL') : _tf('message', 'Message')}</span>
          ${actionInput}
        </label>
      </div>${messageExtras}`;
  }

  function renderWebhookSettings(rule) {
    const method = String(rule.method || 'POST').toUpperCase() === 'GET' ? 'GET' : 'POST';
    const webhookAction = rule.action === 'stop' ? 'stop' : 'trigger';
    const payloadText = Array.isArray(rule.payload_fields) ? rule.payload_fields.join(', ') : String(rule.payload_fields || '');
    const triggerOnly = webhookAction === 'trigger' ? `
        <label class="efb-logic-setting-field">
          <span>${_tf('method', 'Method')}</span>
          <select class="efb-logic-value-select" onchange="EFB_Logic.updateWebhook('method',this.value)">
            <option value="POST" ${method === 'POST' ? 'selected' : ''}>POST</option>
            <option value="GET" ${method === 'GET' ? 'selected' : ''}>GET</option>
          </select>
        </label>
        <label class="efb-logic-setting-field efb-logic-setting-field-wide">
          <span>URL</span>
          <input type="url" class="efb-logic-value-input" value="${_esc(rule.url || '')}" placeholder="https://example.com/webhook"
                 onchange="EFB_Logic.updateWebhook('url',this.value)">
        </label>` : '';
    const payloadRow = webhookAction === 'trigger' ? `
      <div class="efb-logic-action-row efb-logic-settings-row">
        <label class="efb-logic-setting-field efb-logic-setting-field-wide">
          <span>${_tf('payloadFields', 'Payload fields (empty = all)')}</span>
          <input type="text" class="efb-logic-value-input" value="${_esc(payloadText)}" placeholder="field_a, field_b"
                 onchange="EFB_Logic.updateWebhook('payload_fields',this.value)">
        </label>
      </div>` : '';
    return `
      <div class="efb-logic-action-row efb-logic-settings-row">
        <label class="efb-logic-setting-field">
          <span>${_tf('action', 'Action')}</span>
          <select class="efb-logic-value-select" onchange="EFB_Logic.updateWebhook('action',this.value)">
            <option value="trigger" ${webhookAction === 'trigger' ? 'selected' : ''}>${_tf('triggerWebhook', 'Trigger webhook')}</option>
            <option value="stop" ${webhookAction === 'stop' ? 'selected' : ''}>${_tf('stopWebhook', 'Stop webhook')}</option>
          </select>
        </label>
        <label class="efb-logic-setting-field">
          <span>${_tf('webhook', 'Webhook')} ID${webhookAction === 'stop' ? ` <small class="efb-logic-hint">${_tf('stopWebhookHint', 'empty = stop all')}</small>` : ''}</span>
          <input type="text" class="efb-logic-value-input" value="${_esc(rule.webhook_id || '')}" placeholder="crm_hot_lead"
                 onchange="EFB_Logic.updateWebhook('webhook_id',this.value)">
        </label>
        ${triggerOnly}
      </div>${payloadRow}`;
  }

  function renderTabs() {
    const tabs = [
      { id: 'field', icon: 'bi-layout-text-sidebar', label: getTabLabel('field') },
      { id: 'notification', icon: 'bi-envelope', label: getTabLabel('notification') },
      { id: 'confirmation', icon: 'bi-check-circle', label: getTabLabel('confirmation') },
      { id: 'webhook', icon: 'bi-hdd-network', label: getTabLabel('webhook') }
    ];
    return `
      <div class="efb-logic-tabs">
        ${tabs.map(tab => {
          const locked = isTabProLocked(tab.id);
          return `
          <button type="button" class="${activeTab === tab.id ? 'active' : ''}${locked ? ' efb-logic-tab-locked' : ''}"${locked ? ` title="${_esc(proOnlyMessage())}"` : ''} onclick="EFB_Logic.switchTab('${tab.id}')">
            <i class="efb ${tab.icon}"></i>
            <span>${_esc(tab.label)}</span>${locked ? '<i class="efb bi-gem efb-logic-tab-gem"></i>' : ''}
          </button>`;
        }).join('')}
      </div>`;
  }

  function renderShell() {
    if (isTabProLocked(activeTab)) {
      return renderTabs() + renderProLockedPanel();
    }
    const content = view === 'test'
      ? renderTestMode()
      : (view === 'editor' && currentRuleId)
      ? renderEditor(rules.find(r => r.id === currentRuleId))
      : renderList();
    return renderTabs() + content;
  }

  function openModal() {
    /* Conditional logic is a Free Plus / Pro feature: Free plan users get the
     * standard upgrade dialog instead of the builder. */
    if (getPlanTier() === 'free') {
      if (typeof pro_show_efb === 'function') {
        pro_show_efb(3);
        return;
      }
      const freeBody = document.getElementById('settingModalEfb-body');
      if (freeBody) {
        freeBody.classList.remove('row');
        freeBody.innerHTML = renderFreeLockedPanel();
      }
      const freeTitle = document.getElementById('settingModalEfb-title');
      if (freeTitle) freeTitle.textContent = _t('conlog');
      if (typeof state_modal_show_efb === 'function') state_modal_show_efb(1);
      return;
    }
    loadRules();
    view = 'list';
    currentRuleId = null;
    testResults = [];
    testInspector = null;
    const mx = Number(efb_var.rtl) == 1 ? 'ms-2' : 'me-2';
    const modal = document.getElementById('settingModalEfb');
    if (!modal) return;

    modal.classList.add('efb-logic-modal');
    const titleEl = document.getElementById('settingModalEfb-title');
    if (titleEl) titleEl.textContent = _t('conlog');

    const iconEl = document.getElementById('settingModalEfb-icon');
    if (iconEl) iconEl.className = 'efb bi-diagram-3'+mx;

    const bodyEl = document.getElementById('settingModalEfb-body');
    if (bodyEl) {
      bodyEl.classList.remove('row');
      bodyEl.innerHTML = renderShell();
    }

    if (typeof state_modal_show_efb === 'function') state_modal_show_efb(1);
  }

  function closeModal() {
    const modal = document.getElementById('settingModalEfb');
    if (modal) modal.classList.remove('efb-logic-modal');
    if (typeof state_modal_show_efb === 'function') state_modal_show_efb(0);
  }

  function refreshView() {
    const bodyEl = document.getElementById('settingModalEfb-body');
    if (!bodyEl) return;
    if (view === 'editor' && currentRuleId) {
      bodyEl.innerHTML = renderShell();
    } else {
      bodyEl.innerHTML = renderShell();
    }
  }

  /* ────────────────────────────────────────────
     PUBLIC API
     ──────────────────────────────────────────── */
  window.EFB_Logic = {
    /* Open the builder modal */
    open: openModal,

    switchTab(tab) {
      if (!['field', 'notification', 'confirmation', 'webhook'].includes(tab)) return;
      activeTab = tab;
      view = 'list';
      currentRuleId = null;
      testResults = [];
      testInspector = null;
      loadRules();
      refreshView();
    },

    openTestMode() {
      view = 'test';
      currentRuleId = null;
      testResults = [];
      testInspector = null;
      refreshView();
    },

    updateTestValue(fieldId, value) {
      testValues[fieldId] = value;
    },

    runTest() {
      const values = getTestValuesMap();
      testEnv = buildTestEnv();
      testInspector = evaluateRulesForInspector(values);
      testResults = (testInspector.trace || []).map(item => {
        return {
          name: item.name,
          status: item.status,
          label: item.label,
          actionText: item.actions && item.actions.length ? item.actions.map(_esc).join('<br>') : ''
        };
      });
      refreshView();
    },

    /* Back from editor to list */
    backToList() {
      view = 'list';
      currentRuleId = null;
      testInspector = null;
      refreshView();
    },

    /* Add a new blank rule */
    addRule() {
      if (getPlanTier() === 'free') { showFreePlanNotice(); return; }
      if (isTabProLocked(activeTab)) { showProOnlyNotice(); return; }
      const ruleLimit = getRuleLimit(activeTab);
      if (rules.length >= ruleLimit) {
        showLimitNotice(ruleLimit, getTabLabel(activeTab));
        return;
      }
      const baseRule = {
        id: _id(activeTab === 'notification' ? 'nr' : (activeTab === 'confirmation' ? 'cr' : (activeTab === 'webhook' ? 'wr' : 'rule'))),
        name: '',
        scope: activeTab,
        enabled: true,
        priority: 10,
        conditions: {
          type: 'group',
          operator: 'AND',
          items: [
            newBlankCondition()
          ]
        }
      };
      const rule = activeTab === 'notification' ? {
        ...baseRule,
        recipient: '',
        subject: '',
        template: 'default'
      } : activeTab === 'confirmation' ? {
        ...baseRule,
        action: 'message',
        url: '',
        message: '',
        done: '',
        icon: '',
        tracking_label: '',
        icon_color: '',
        title_color: '',
        message_color: ''
      } : activeTab === 'webhook' ? {
        ...baseRule,
        webhook_id: '',
        url: '',
        method: 'POST',
        action: 'trigger',
        payload_fields: []
      } : {
        ...baseRule,
        stop_processing: false,
        actions: [
          { type: 'show_field', target: '' }
        ]
      };
      rules.push(rule);
      view = 'editor';
      currentRuleId = rule.id;
      refreshView();
    },

    /* Edit existing rule */
    editRule(id) {
      currentRuleId = id;
      view = 'editor';
      refreshView();
    },

    /* Duplicate a rule inside the same form (PRD Phase 2 "reusable rule groups") */
    duplicateRule(id) {
      const rule = rules.find(r => r.id === id);
      if (!rule) return;
      const ruleLimit = getRuleLimit(activeTab);
      if (rules.length >= ruleLimit) {
        showLimitNotice(ruleLimit, getTabLabel(activeTab));
        return;
      }
      const copy = JSON.parse(JSON.stringify(rule));
      copy.id = _id(activeTab === 'notification' ? 'nr' : (activeTab === 'confirmation' ? 'cr' : (activeTab === 'webhook' ? 'wr' : 'rule')));
      copy.name = (rule.name || '') + ' (copy)';
      rules.splice(rules.indexOf(rule) + 1, 0, copy);
      saveRules();
      refreshView();
    },

    /* Export every rule set of this form as a portable JSON file (Pro) */
    exportRules() {
      if (!isProPlan()) { showProOnlyNotice(); return; }
      if (typeof valj_efb === 'undefined' || !valj_efb[0]) return;
      const payload = {
        format: 'efb-logic-rules',
        version: 1,
        exported_at: new Date().toISOString(),
        logic_rules: Array.isArray(valj_efb[0].logic_rules) ? valj_efb[0].logic_rules : [],
        notification_rules: Array.isArray(valj_efb[0].notification_rules) ? valj_efb[0].notification_rules : [],
        confirmation_rules: Array.isArray(valj_efb[0].confirmation_rules) ? valj_efb[0].confirmation_rules : [],
        webhook_rules: Array.isArray(valj_efb[0].webhook_rules) ? valj_efb[0].webhook_rules : []
      };
      const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = 'efb-logic-rules.json';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(link.href);
    },

    /* Import rules from a previously exported JSON file (Pro).
     * Only the four known rule-set arrays are read; everything is re-sanitized
     * server-side on save, so a hand-crafted file cannot inject anything. */
    importRules() {
      if (!isProPlan()) { showProOnlyNotice(); return; }
      const input = document.getElementById('efb-logic-import-file');
      if (input) input.click();
    },

    importRulesFile(input) {
      if (!isProPlan() || !input || !input.files || !input.files[0]) return;
      const reader = new FileReader();
      reader.onload = () => {
        try {
          const data = JSON.parse(String(reader.result || ''));
          if (!data || data.format !== 'efb-logic-rules') throw new Error('format');
          ['logic_rules', 'notification_rules', 'confirmation_rules', 'webhook_rules'].forEach(key => {
            if (Array.isArray(data[key])) valj_efb[0][key] = JSON.parse(JSON.stringify(data[key]));
          });
          loadRules();
          saveRules();
          view = 'list';
          currentRuleId = null;
          refreshView();
          if (typeof alert_message_efb === 'function') {
            alert_message_efb(_tf('importRules', 'Import'), _tf('importDone', 'Rules imported. Review and save the form.'), 6, 'success');
          }
        } catch (error) {
          if (typeof alert_message_efb === 'function') {
            alert_message_efb(_tf('importRules', 'Import'), _tf('importInvalid', 'This file is not a valid EFB logic-rules export.'), 6, 'warning');
          }
        }
        input.value = '';
      };
      reader.readAsText(input.files[0]);
    },

    /* Delete a rule */
    deleteRule(id) {
      rules = rules.filter(r => r.id !== id);
      saveRules();
      refreshView();
    },

    /* Toggle rule enabled */
    toggleRule(id) {
      const rule = rules.find(r => r.id === id);
      if (rule) rule.enabled = !rule.enabled;
      saveRules();
      refreshView();
    },

    /* Rename current rule */
    renameRule(name) {
      const rule = rules.find(r => r.id === currentRuleId);
      if (rule) rule.name = (typeof sanitize_text_efb === 'function') ? sanitize_text_efb(name) : name;
    },

    setPriority(value) {
      if (!isProPlan()) { showProOnlyNotice(); refreshView(); return; }
      const rule = rules.find(r => r.id === currentRuleId);
      if (rule) rule.priority = Math.max(0, Number.parseInt(value, 10) || 0);
    },

    setStopProcessing(value) {
      if (!isProPlan()) { showProOnlyNotice(); refreshView(); return; }
      const rule = rules.find(r => r.id === currentRuleId);
      if (rule) rule.stop_processing = Boolean(value);
    },

    /* Toast used by Pro-locked controls (priority, stop_processing, ...) */
    notifyProFeature() {
      showProOnlyNotice();
    },

    /* Open the Pro upgrade page / plan picker */
    openProUpgrade() {
      if (typeof open_whiteStudio_efb === 'function') { open_whiteStudio_efb('pro'); return; }
      if (typeof showSetupAsOverlayPage === 'function') showSetupAsOverlayPage();
    },

    updateNotification(prop, value) {
      const rule = rules.find(r => r.id === currentRuleId);
      if (!rule) return;
      if (prop === 'recipient' || prop === 'subject' || prop === 'template') {
        rule[prop] = (typeof sanitize_text_efb === 'function') ? sanitize_text_efb(value) : value;
      }
      if (prop === 'cc' || prop === 'bcc') {
        rule[prop] = String(value || '').split(',').map(item => item.trim()).filter(item => isValidEmail(item));
      }
    },

    updateConfirmation(prop, value) {
      const rule = rules.find(r => r.id === currentRuleId);
      if (!rule) return;
      if (prop === 'action') {
        rule.action = value === 'redirect' ? 'redirect' : 'message';
        refreshView();
        return;
      }
      if (prop === 'url' || prop === 'message') {
        rule[prop] = value;
        return;
      }
      if (prop === 'done' || prop === 'tracking_label') {
        rule[prop] = (typeof sanitize_text_efb === 'function') ? sanitize_text_efb(value) : value;
        return;
      }
      if (prop === 'icon') {
        rule.icon = /^bi-[a-z0-9-]+$/.test(String(value)) ? String(value) : '';
        refreshView(); /* update the icon preview next to the select */
        return;
      }
      if (prop === 'icon_color' || prop === 'title_color' || prop === 'message_color') {
        rule[prop] = /^#[0-9a-fA-F]{6}$/.test(String(value)) ? String(value).toLowerCase() : '';
        if (rule[prop] === '') refreshView(); /* reset button: restore default swatch state */
      }
    },

    updateWebhook(prop, value) {
      const rule = rules.find(r => r.id === currentRuleId);
      if (!rule) return;
      if (prop === 'method') {
        rule.method = String(value || 'POST').toUpperCase() === 'GET' ? 'GET' : 'POST';
        return;
      }
      if (prop === 'webhook_id') {
        rule.webhook_id = (typeof sanitize_text_efb === 'function') ? sanitize_text_efb(value) : value;
        return;
      }
      if (prop === 'action') {
        rule.action = value === 'stop' ? 'stop' : 'trigger';
        refreshView();
        return;
      }
      if (prop === 'payload_fields') {
        rule.payload_fields = String(value || '').split(',').map(item => item.trim()).filter(Boolean);
        return;
      }
      if (prop === 'url') {
        rule.url = value;
      }
    },

    /* Set condition group operator */
    setCondOperator(op) {
      this.setGroupOperator('', op);
    },

    setGroupOperator(path, op) {
      const rule = rules.find(r => r.id === currentRuleId);
      const group = rule ? getGroupByPath(rule, path) : null;
      if (group) group.operator = String(op).toUpperCase() === 'OR' ? 'OR' : 'AND';
      refreshView();
    },

    setItemConnector(path, op) {
      const rule = rules.find(r => r.id === currentRuleId);
      const parts = normalizePath(path);
      if (!rule || parts.length === 0 || parts[parts.length - 1] === 0) return;
      const item = getItemByPath(rule, parts);
      if (item) item.connector = normalizeConnector(op);
      refreshView();
    },

    /* NOT toggle on a group — NAND/NOR semantics (Pro-only, PRD §17) */
    toggleGroupNegate(path = '') {
      if (!isProPlan()) { showProOnlyNotice(); return; }
      const rule = rules.find(r => r.id === currentRuleId);
      const group = rule ? getGroupByPath(rule, path) : null;
      if (!group) return;
      if (group.negate) delete group.negate;
      else group.negate = true;
      refreshView();
    },

    /* Add condition row */
    addCondition(path = '') {
      const rule = rules.find(r => r.id === currentRuleId);
      if (!rule) return;
      const group = getGroupByPath(rule, path);
      if (!group) return;
      const conditionLimit = getConditionLimit();
      if (countGroupConditions(group) >= conditionLimit) {
        showLimitNotice(conditionLimit, _tf('logicConditions', 'Conditions'));
        return;
      }
      const item = newBlankCondition();
      if (group.items.length > 0) item.connector = 'AND';
      group.items.push(item);
      refreshView();
    },

    addGroup(path = '') {
      if (!isProPlan()) { showProOnlyNotice(); return; }
      const rule = rules.find(r => r.id === currentRuleId);
      if (!rule) return;
      const group = getGroupByPath(rule, path);
      if (!group) return;
      const item = {
        type: 'group',
        operator: 'AND',
        items: [
          newBlankCondition()
        ]
      };
      if (group.items.length > 0) item.connector = 'AND';
      group.items.push(item);
      refreshView();
    },

    /* Remove condition row */
    removeCondition(path) {
      const rule = rules.find(r => r.id === currentRuleId);
      const parts = normalizePath(path);
      const parent = rule ? getParentGroupForPath(rule, parts) : null;
      const index = parts[parts.length - 1];
      if (!parent || !Number.isInteger(index) || !parent.items[index] || isGroupItem(parent.items[index])) return;
      parent.items.splice(index, 1);
      if (!parent.items.length) parent.items.push(newBlankCondition());
      removeLeadingConnectors(parent);
      refreshView();
    },

    removeGroup(path) {
      const rule = rules.find(r => r.id === currentRuleId);
      const parts = normalizePath(path);
      if (!parts.length) return;
      const parent = rule ? getParentGroupForPath(rule, parts) : null;
      const index = parts[parts.length - 1];
      if (!parent || !Number.isInteger(index) || !isGroupItem(parent.items[index])) return;
      parent.items.splice(index, 1);
      if (!parent.items.length) parent.items.push(newBlankCondition());
      removeLeadingConnectors(parent);
      refreshView();
    },

    /* Update a condition property */
    updateCondition(path, prop, value) {
      const rule = rules.find(r => r.id === currentRuleId);
      const cond = rule ? getConditionByPath(rule, path) : null;
      if (!cond) return;

      /* Switching the source resets the whole condition to a sensible default */
      if (prop === 'source') {
        cond.source = CONDITION_SOURCES.some(item => item.value === value) ? value : 'field';
        delete cond.param;
        cond.value = '';
        if (cond.source === 'user') cond.field_id = 'logged_in';
        else if (cond.source === 'current_step') cond.field_id = 'current_step';
        else cond.field_id = '';
        cond.compare = getOperatorsForCondition(cond)[0] || 'is';
        refreshView();
        return;
      }
      /* Query-param key: keep field_id mirrored for validity + summaries */
      if (prop === 'param') {
        const cleanParam = String(value || '').replace(/[^A-Za-z0-9_\-\[\]]/g, '');
        cond.param = cleanParam;
        cond.field_id = cleanParam;
        refreshView();
        return;
      }
      cond[prop] = value;

      /* When field changes, reset operator & value */
      if (prop === 'field_id') {
        const ops = getOperatorsForCondition(cond);
        if (!ops.includes(cond.compare)) cond.compare = ops[0] || 'is';
        cond.value = '';
        refreshView();
      }
      /* When operator changes, maybe hide value */
      if (prop === 'compare') {
        if (NO_VALUE_OPERATORS.has(value)) cond.value = '';
        refreshView();
      }
    },

    /* Add action row */
    addAction() {
      const rule = rules.find(r => r.id === currentRuleId);
      if (!rule) return;
      rule.actions.push({ type: '', target: '' });
      refreshView();
    },

    /* Remove action row */
    removeAction(idx) {
      const rule = rules.find(r => r.id === currentRuleId);
      if (!rule || rule.actions.length <= 1) return;
      rule.actions.splice(idx, 1);
      refreshView();
    },

    /* Update an action property */
    updateAction(idx, prop, value) {
      const rule = rules.find(r => r.id === currentRuleId);
      if (!rule || !rule.actions[idx]) return;
      /* PRD §17: pricing logic (calculate) is Pro-only */
      if (prop === 'type' && PRO_ONLY_ACTIONS.has(value) && !isProPlan()) {
        showProOnlyNotice();
        refreshView();
        return;
      }
      rule.actions[idx][prop] = value;
      /* When type changes, reset target and value fields */
      if (prop === 'type') {
        rule.actions[idx].target = '';
        delete rule.actions[idx].value_type;
        delete rule.actions[idx].value;
        delete rule.actions[idx].decimals;
        refreshView();
      }
      /* When value_type changes (static ↔ autofill_key), clear the current value */
      if (prop === 'decimals') {
        if (value === '') delete rule.actions[idx].decimals;
        else rule.actions[idx].decimals = Math.max(0, Math.min(6, Number.parseInt(value, 10) || 0));
        refreshView();
      }
      if (prop === 'value_type') {
        rule.actions[idx].value = '';
        refreshView();
      }
    },

    insertCalculationToken(idx, fieldId) {
      const rule = rules.find(r => r.id === currentRuleId);
      if (!rule || !rule.actions[idx] || !fieldId) return;
      const current = String(rule.actions[idx].value || '').trim();
      rule.actions[idx].value = current ? `${current} {${fieldId}}` : `{${fieldId}}`;
      refreshView();
    },

    /* Apply (save & close editor) */
    applyRule() {
      const rule = rules.find(r => r.id === currentRuleId);
      if (!isRuleValid(rule)) {
        const message = (efb_var.text && (efb_var.text.fillrequiredfields || efb_var.text.pleaseFillInRequiredFields))
          || 'Complete all condition and action fields before saving.';
        if (typeof alert_message_efb === 'function') alert_message_efb(message, '', 6, 'warning');
        return;
      }
      rules.forEach(rule => removeLeadingConnectors(rule.conditions));
      saveRules();
      view = 'list';
      currentRuleId = null;
      refreshView();
    },

    /* Get rules array (for runtime engine) */
    getRules() {
      return rules;
    },

    /* Load autofill dataset column keys via AJAX and refresh the builder view.
     * Called when the user clicks the ⛛ database icon in a set_value action row
     * and autofill is active but source_keys are not yet cached in valj_efb[0]. */
    async loadAutofillKeys() {
      if (!isAutofillActive()) return;
      if (typeof efbAjaxCalllistAutoFill !== 'function') return;
      const autofillId = Number(valj_efb[0].autofill_id);
      if (!autofillId) return;
      try {
        const res = await efbAjaxCalllistAutoFill('id', autofillId);
        if (Array.isArray(res) && res[0] && Object.prototype.hasOwnProperty.call(res[0], 'value_')) {
          const parsed = JSON.parse(res[0].value_);
          const rows   = Object.keys(parsed);
          if (rows.length > 0) {
            valj_efb[0].autofill_source_keys = Object.keys(parsed[rows[0]]);
            refreshView();
          }
        }
      } catch (e) { console.error('EFB Logic: Failed to load autofill keys', e); }
    }
  };
})();
