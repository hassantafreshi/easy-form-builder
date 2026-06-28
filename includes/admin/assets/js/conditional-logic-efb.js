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

  /* operator definitions keyed by field category */
  const OPERATORS_BY_CATEGORY = {
    choice: ['is', 'is_not', 'is_empty', 'is_not_empty'],
    text: ['is', 'is_not', 'contains', 'not_contains', 'starts_with', 'ends_with', 'is_empty', 'is_not_empty'],
    number: ['is', 'is_not', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between', 'is_empty', 'is_not_empty'],
    date: ['is', 'is_not', 'gt', 'lt', 'is_empty', 'is_not_empty'],
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
    amount_lt: 'lthan'
  };

  const NO_VALUE_OPERATORS = new Set(['is_empty', 'is_not_empty', 'is_paid', 'is_not_paid']);
  const RANGE_OPERATORS = new Set(['between', 'not_between']);

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
    file: 'file', signature: 'file',
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
    { value: 'set_optional',  label: () => 'Optional' },
    { value: 'enable_field',  label: () => 'Enable' },
    { value: 'disable_field', label: () => 'Disable' },
    { value: 'show_step',     label: () => _t('show') + ' ' + _t('step') },
    { value: 'hide_step',     label: () => _t('hide') + ' ' + _t('step') },
    { value: 'jump_to_step',  label: () => efb_var.text.jumpStep  || 'Jump to Step' },
    { value: 'set_value',     label: () => efb_var.text.setValue  || 'Set Value' },
    { value: 'clear_value',   label: () => efb_var.text.clearValue || 'Clear Value' },
    { value: 'show_message',  label: () => efb_var.text.showMessage || 'Show Message' }
  ];

  /* ────────────────────────────────────────────
     STATE
     ──────────────────────────────────────────── */
  let rules = [];
  let currentRuleId = null;
  let view = 'list'; // 'list' | 'editor'
  let activeTab = 'field'; // 'field' | 'notification' | 'confirmation'

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
    return 'logic_rules';
  }

  function getTabLabel(tab) {
    if (tab === 'notification') return _tf('notifications', 'Notifications');
    if (tab === 'confirmation') return _tf('confirmation', 'Confirmation');
    return _tf('fields', 'Fields');
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
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
    if (NO_VALUE_OPERATORS.has(cond.compare)) return true;
    if (RANGE_OPERATORS.has(cond.compare)) {
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
    if (!Array.isArray(rule.actions) || !rule.actions.length) return false;
    const actionsValid = rule.actions.every(action => {
      if (!action || !action.type || !action.target) return false;
      if (action.type === 'show_message') return String(action.value || '').trim().length > 0;
      if (action.type === 'set_value') return String(action.value || '').length > 0;
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

  /* ────────────────────────────────────────────
     RENDER — RULES LIST
     ──────────────────────────────────────────── */
  function renderList() {
    view = 'list';
    currentRuleId = null;
    const mx = Number(efb_var.rtl) == 1 ? 'ms-2' : 'me-2';
    if (rules.length === 0) {
      return `
        <div class="efb-logic-list">
          <div class="efb-logic-empty">
            <div class="efb-logic-empty-icon"><i class="efb bi-diagram-3 ${mx}"></i></div>
            <p>Add your first rule to start building smart forms.</p>
            <button type="button" class="efb-logic-add-btn efb-logic-add-btn-center" onclick="EFB_Logic.addRule()"><i class="efb bi-plus-lg"></i> ${_t('add')}</button>
          </div>
        </div>`;
    }

    let cards = '';
    rules.forEach((rule, idx) => {
      const summary = buildSummary(rule);
      cards += `
        <div class="efb-logic-rule-card ${rule.enabled ? '' : 'disabled'}" data-rule-id="${_esc(rule.id)}">
          <div class="efb-logic-rule-top">
            <label class="efb-logic-toggle efb-logic-rule-toggle">
              <input type="checkbox" ${rule.enabled ? 'checked' : ''} onchange="EFB_Logic.toggleRule('${_esc(rule.id)}')">
              <span class="efb-logic-toggle-track"></span>
              <span class="efb-logic-toggle-thumb"></span>
            </label>
            <div class="efb-logic-rule-info" onclick="EFB_Logic.editRule('${_esc(rule.id)}')">
              <p class="efb-logic-rule-name">${_esc(rule.name || (_t('conlog') + ' ' + (idx + 1)))}</p>
              <p class="efb-logic-rule-summary">${summary || '<span class="efb-logic-not-configured">⚙ Not configured — click edit</span>'}</p>
            </div>
            <div class="efb-logic-rule-actions">
              <button type="button" title="Edit" onclick="EFB_Logic.editRule('${_esc(rule.id)}')"><i class="efb bi-pencil"></i></button>
              <button type="button" class="efb-logic-delete-btn" title="${_t('delete')}" onclick="EFB_Logic.deleteRule('${_esc(rule.id)}')"><i class="efb bi-trash"></i></button>
            </div>
          </div>
        </div>`;
    });

    return `
      <div class="efb-logic-list">
        <div class="efb-logic-list-header">
          <button type="button" class="efb-logic-add-btn" onclick="EFB_Logic.addRule()"><i class="efb bi-plus-lg"></i> ${_t('add')}</button>
        </div>
        ${cards}
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
    } else {
      actionRows = renderConfirmationSettings(rule);
    }
    const actionAddButton = activeTab === 'field' ? `
              <div class="efb-logic-group-actions">
                <button type="button" class="efb-logic-add-row-btn" onclick="EFB_Logic.addAction()">
                  <i class="efb bi-plus"></i>${_t('add')}
                </button>
              </div>` : '';
    const stopProcessingField = activeTab === 'field' ? `
            <label class="efb-logic-stop-field">
              <span class="efb-logic-toggle efb-logic-stop-toggle">
                <input type="checkbox" ${rule.stop_processing ? 'checked' : ''}
                       onchange="EFB_Logic.setStopProcessing(this.checked)">
                <span class="efb-logic-toggle-track"></span>
                <span class="efb-logic-toggle-thumb"></span>
              </span>
              <span class="efb-logic-stop-label">${efb_var.text.stopProcessing || 'Stop after this rule matches'}</span>
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
          <!-- IF section -->
          <div class="efb-logic-section">
            <span class="efb-logic-section-label efb-if-label">IF</span>
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
            <span class="efb-logic-section-label efb-then-label">THEN</span>
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
            <label class="efb-logic-priority-field">
              <span class="efb-logic-priority-label">${_t('priority') || 'Priority'}</span>
              <input type="number" class="efb-logic-priority-input" min="0" step="1" value="${Number(rule.priority || 10)}"
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
            ${removeBtn}
          </div>
        </div>
        <div class="efb-logic-group-body">
          ${emptyState}
          ${itemHtml}
        </div>
        <div class="efb-logic-group-actions">
          <button type="button" class="efb-logic-add-row-btn" onclick="EFB_Logic.addCondition('${pathAttr}')">
            <i class="efb bi-plus"></i>${_t('add')} ${_tf('logicCondition', 'Condition')}
          </button>
          <button type="button" class="efb-logic-add-row-btn efb-logic-add-group-btn" onclick="EFB_Logic.addGroup('${pathAttr}')">
            <i class="efb bi-diagram-3"></i>${_t('add')} ${_tf('logicGroup', 'Group')}
          </button>
        </div>
      </div>`;
  }

  function renderConditionRow(cond, idx, fields) {
    if (!fields) fields = getAllFields();
    const pathAttr = _esc(String(idx));
    const ops = cond.field_id ? getOperatorsForField(cond.field_id) : OPERATORS_BY_CATEGORY.text;
    const needsValue = !NO_VALUE_OPERATORS.has(cond.compare);
    const hasOpts = cond.field_id && fieldHasOptions(cond.field_id);

    /* detect payment amount operator (needs number input) */
    const fObj = typeof valj_efb !== 'undefined' ? valj_efb.find(x => x.id_ === cond.field_id) : null;
    const isPaymentAmountOp = fObj && getFieldCategory(fObj.type) === 'payment' &&
      (cond.compare === 'amount_eq' || cond.compare === 'amount_gt' || cond.compare === 'amount_lt');

    /* field select */
    let fieldOpts = `<option value="">${_t('select')} ${_t('field')}</option>`;
    fields.forEach(f => {
      fieldOpts += `<option value="${_esc(f.id_)}" ${f.id_ === cond.field_id ? 'selected' : ''}>${_esc(f.name)}</option>`;
    });

    /* operator select */
    let opOpts = '';
    ops.forEach(op => {
      opOpts += `<option value="${op}" ${op === cond.compare ? 'selected' : ''}>${_t(OPERATOR_LABELS[op] || op)}</option>`;
    });

    /* value input */
    let valueHtml = '';
    if (needsValue) {
      if (RANGE_OPERATORS.has(cond.compare)) {
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
        <select class="efb-logic-field-select" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','field_id',this.value)">${fieldOpts}</select>
        <select class="efb-logic-operator-select" data-ci="${pathAttr}" onchange="EFB_Logic.updateCondition('${pathAttr}','compare',this.value)">${opOpts}</select>
        ${valueHtml}
        <button type="button" class="efb-logic-remove-btn" onclick="EFB_Logic.removeCondition('${pathAttr}')" title="${_t('delete')}"><i class="efb bi-x-lg"></i></button>
      </div>`;
  }

  function renderActionRow(action, idx) {
    /* action type select */
    let atOpts = `<option value="">${_t('select')}</option>`;
    ACTION_TYPES.forEach(at => {
      atOpts += `<option value="${at.value}" ${at.value === action.type ? 'selected' : ''}>${at.label()}</option>`;
    });

    /* target select */
    const targets = action.type ? getTargetsForAction(action.type) : getAllFields();
    let tOpts = `<option value="">${_t('select')}</option>`;
    targets.forEach(t => {
      tOpts += `<option value="${_esc(t.id_)}" ${t.id_ === action.target ? 'selected' : ''}>${_esc(t.name)}</option>`;
    });

    /* value input for set_value / show_message actions */
    let valueHtml = '';
    if (action.type === 'show_message') {
      valueHtml = `<input type="text" class="efb-logic-value-input" value="${_esc(action.value || '')}" placeholder="${_t('enterText') || 'Message...'}" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'value',this.value)">` ;
    }
    if (action.type === 'set_value') {
      const afActive = isAutofillActive();
      const afKeys   = getAutofillSourceKeys();
      const vType    = action.value_type || 'static';
      const phStatic = _t('enterTheValueThisField') || 'Value...';
      if (afActive) {
        /* Source-type selector: Static | Dataset column */
        let vtOpts = `<option value="static" ${vType === 'static' ? 'selected' : ''}>${efb_var.text.setValue || 'Static'}</option>`;
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

    return `
      <div class="efb-logic-action-row" data-ai="${idx}">
        <select class="efb-logic-action-type-select" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'type',this.value)">${atOpts}</select>
        <select class="efb-logic-action-target-select" data-ai="${idx}" onchange="EFB_Logic.updateAction(${idx},'target',this.value)">${tOpts}</select>
        ${valueHtml}
        <button type="button" class="efb-logic-remove-btn" onclick="EFB_Logic.removeAction(${idx})" title="${_t('delete')}"><i class="efb bi-x-lg"></i></button>
      </div>`;
  }

  /* ────────────────────────────────────────────
     MODAL MANAGEMENT
     ──────────────────────────────────────────── */
  function renderNotificationSettings(rule) {
    const template = rule.template || 'default';
    return `
      <div class="efb-logic-action-row efb-logic-settings-row">
        <label class="efb-logic-setting-field">
          <span>${_tf('email', 'Email')}</span>
          <input type="email" class="efb-logic-value-input" value="${_esc(rule.recipient || '')}" placeholder="name@example.com"
                 onchange="EFB_Logic.updateNotification('recipient',this.value)">
        </label>
        <label class="efb-logic-setting-field">
          <span>${_tf('subject', 'Subject')}</span>
          <input type="text" class="efb-logic-value-input" value="${_esc(rule.subject || '')}" placeholder="${_tf('emailNotifications', 'Email notification')}"
                 onchange="EFB_Logic.updateNotification('subject',this.value)">
        </label>
        <label class="efb-logic-setting-field">
          <span>${_tf('template', 'Template')}</span>
          <select class="efb-logic-value-select" onchange="EFB_Logic.updateNotification('template',this.value)">
            <option value="default" ${template === 'default' ? 'selected' : ''}>${_tf('default', 'Default')}</option>
          </select>
        </label>
      </div>`;
  }

  function renderConfirmationSettings(rule) {
    const action = rule.action === 'redirect' ? 'redirect' : 'message';
    const actionInput = action === 'redirect'
      ? `<input type="url" class="efb-logic-value-input" value="${_esc(rule.url || '')}" placeholder="https://example.com/thanks"
                onchange="EFB_Logic.updateConfirmation('url',this.value)">`
      : `<textarea class="efb-logic-value-input efb-logic-message-input" placeholder="${_tf('thankYou', 'Thank you')}"
                   onchange="EFB_Logic.updateConfirmation('message',this.value)">${_esc(rule.message || '')}</textarea>`;

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
      </div>`;
  }

  function renderTabs() {
    const tabs = [
      { id: 'field', icon: 'bi-layout-text-sidebar', label: getTabLabel('field') },
      { id: 'notification', icon: 'bi-envelope', label: getTabLabel('notification') },
      { id: 'confirmation', icon: 'bi-check-circle', label: getTabLabel('confirmation') }
    ];
    return `
      <div class="efb-logic-tabs">
        ${tabs.map(tab => `
          <button type="button" class="${activeTab === tab.id ? 'active' : ''}" onclick="EFB_Logic.switchTab('${tab.id}')">
            <i class="efb ${tab.icon}"></i>
            <span>${_esc(tab.label)}</span>
          </button>
        `).join('')}
      </div>`;
  }

  function renderShell() {
    const content = (view === 'editor' && currentRuleId)
      ? renderEditor(rules.find(r => r.id === currentRuleId))
      : renderList();
    return renderTabs() + content;
  }

  function openModal() {
    loadRules();
    view = 'list';
    currentRuleId = null;
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
      if (!['field', 'notification', 'confirmation'].includes(tab)) return;
      activeTab = tab;
      view = 'list';
      currentRuleId = null;
      loadRules();
      refreshView();
    },

    /* Back from editor to list */
    backToList() {
      view = 'list';
      currentRuleId = null;
      refreshView();
    },

    /* Add a new blank rule */
    addRule() {
      const baseRule = {
        id: _id(activeTab === 'notification' ? 'nr' : (activeTab === 'confirmation' ? 'cr' : 'rule')),
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
        message: ''
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
      const rule = rules.find(r => r.id === currentRuleId);
      if (rule) rule.priority = Math.max(0, Number.parseInt(value, 10) || 0);
    },

    setStopProcessing(value) {
      const rule = rules.find(r => r.id === currentRuleId);
      if (rule) rule.stop_processing = Boolean(value);
    },

    updateNotification(prop, value) {
      const rule = rules.find(r => r.id === currentRuleId);
      if (!rule) return;
      if (prop === 'recipient' || prop === 'subject' || prop === 'template') {
        rule[prop] = (typeof sanitize_text_efb === 'function') ? sanitize_text_efb(value) : value;
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

    /* Add condition row */
    addCondition(path = '') {
      const rule = rules.find(r => r.id === currentRuleId);
      if (!rule) return;
      const group = getGroupByPath(rule, path);
      if (!group) return;
      const item = newBlankCondition();
      if (group.items.length > 0) item.connector = 'AND';
      group.items.push(item);
      refreshView();
    },

    addGroup(path = '') {
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
      cond[prop] = value;

      /* When field changes, reset operator & value */
      if (prop === 'field_id') {
        const ops = getOperatorsForField(value);
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
      rule.actions[idx][prop] = value;
      /* When type changes, reset target and value fields */
      if (prop === 'type') {
        rule.actions[idx].target = '';
        delete rule.actions[idx].value_type;
        delete rule.actions[idx].value;
        refreshView();
      }
      /* When value_type changes (static ↔ autofill_key), clear the current value */
      if (prop === 'value_type') {
        rule.actions[idx].value = '';
        refreshView();
      }
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
