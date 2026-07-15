(function () {
  'use strict';

  var cfg = window.EFBHumanShield || {};

  // Graceful degradation: when a required browser API is missing the shield
  // steps aside completely. Forms keep working; the server decides (monitor
  // mode passes them, strict/soft-block modes answer with a clear message).
  var missingFeatures = [];
  if (!window.fetch) missingFeatures.push('fetch');
  if (!window.Promise) missingFeatures.push('Promise');
  if (typeof window.URL !== 'function') missingFeatures.push('URL');
  if (typeof window.Set !== 'function') missingFeatures.push('Set');
  if (!window.JSON || !JSON.stringify) missingFeatures.push('JSON');

  if (!cfg.restUrl || missingFeatures.length) {
    window.EFBHumanShieldStatus = {
      active: false,
      reason: !cfg.restUrl ? 'missing_config' : 'unsupported_browser',
      missing: missingFeatures
    };
    if (window.console && console.warn) {
      console.warn(
        '[EFB Form Security] Disabled in this browser: ' +
        (!cfg.restUrl ? 'configuration was not loaded.' : 'missing ' + missingFeatures.join(', ') + '.') +
        ' Forms still submit; the server-side shield decides on its own.'
      );
    }
    return;
  }

  const state = {
    startedAt: Date.now(),
    firstInteractionAt: 0,
    lastInteractionAt: 0,
    focusCount: 0,
    inputCount: 0,
    keyCount: 0,
    pointerMoveCount: 0,
    pointerDistance: 0,
    touchCount: 0,
    clickCount: 0,
    scrollCount: 0,
    pasteCount: 0,
    visibilityChanges: 0,
    fieldsTouched: new Set(),
    fieldCount: 0,
    lastPointer: null,
    lastPointerAt: 0,
    honeypotFilled: false,
    touchCapable: ('ontouchstart' in window) || (navigator.maxTouchPoints > 0),
    webdriver: Boolean(navigator.webdriver)
  };

  window.EFBHumanShieldStatus = { active: true, reason: '', missing: [] };

  const originalFetch = window.fetch.bind(window);
  const originalOpen = window.XMLHttpRequest && window.XMLHttpRequest.prototype.open;
  const originalSend = window.XMLHttpRequest && window.XMLHttpRequest.prototype.send;
  const originalSetHeader = window.XMLHttpRequest && window.XMLHttpRequest.prototype.setRequestHeader;
  const attestTimeoutMs = Math.max(1000, Number(cfg.attestTimeoutMs || 4500));

  function markInteraction() {
    const now = Date.now();
    if (!state.firstInteractionAt) state.firstInteractionAt = now;
    state.lastInteractionAt = now;
  }

  function installHoneypots() {
    const containers = document.querySelectorAll('[id^="body_efb_"], [data-formid]');
    containers.forEach((container) => {
      if (container.querySelector('[data-efb-hs-honeypot]')) return;
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'efb_company_website_confirm';
      input.tabIndex = -1;
      input.autocomplete = 'off';
      input.setAttribute('aria-hidden', 'true');
      input.setAttribute('data-efb-hs-honeypot', '1');
      input.style.position = 'absolute';
      input.style.left = '-10000px';
      input.style.width = '1px';
      input.style.height = '1px';
      input.style.opacity = '0';
      input.addEventListener('input', () => { state.honeypotFilled = true; });
      container.appendChild(input);
    });
  }

  function refreshFieldCount() {
    const selectors = 'input:not([type="hidden"]):not([data-efb-hs-honeypot]), textarea, select, button';
    state.fieldCount = document.querySelectorAll(selectors).length || 0;
  }

  /* Fields of one specific form (not the whole page), so the server can set a
   * fill-time floor that matches the actual form size. */
  function formFieldCount(formId) {
    if (!formId) return 0;
    const container = document.getElementById('body_efb_' + formId) ||
      document.querySelector('[data-formid="' + formId + '"]');
    if (!container) return 0;
    const selectors = 'input:not([type="hidden"]):not([data-efb-hs-honeypot]), textarea, select';
    return container.querySelectorAll(selectors).length || 0;
  }

  document.addEventListener('focusin', (event) => {
    markInteraction();
    state.focusCount += 1;
    const id = fieldKey(event.target);
    if (id) state.fieldsTouched.add(id);
    maybePrefetchFor(event.target);
  }, true);

  document.addEventListener('input', (event) => {
    markInteraction();
    if (event.target && event.target.matches && event.target.matches('[data-efb-hs-honeypot]')) {
      state.honeypotFilled = true;
    }
    state.inputCount += 1;
    const id = fieldKey(event.target);
    if (id) state.fieldsTouched.add(id);
  }, true);

  document.addEventListener('keydown', () => {
    markInteraction();
    state.keyCount += 1;
  }, true);

  document.addEventListener('paste', () => {
    markInteraction();
    state.pasteCount += 1;
  }, true);

  document.addEventListener('click', () => {
    markInteraction();
    state.clickCount += 1;
  }, true);

  document.addEventListener('touchstart', () => {
    markInteraction();
    state.touchCount += 1;
  }, { capture: true, passive: true });

  document.addEventListener('touchmove', () => {
    markInteraction();
    state.touchCount += 1;
  }, { capture: true, passive: true });

  document.addEventListener('pointermove', (event) => {
    const now = Date.now();
    if (now - state.lastPointerAt < 100) return;
    state.lastPointerAt = now;
    markInteraction();
    state.pointerMoveCount += 1;
    if (state.lastPointer) {
      const dx = event.clientX - state.lastPointer.x;
      const dy = event.clientY - state.lastPointer.y;
      state.pointerDistance += Math.round(Math.sqrt(dx * dx + dy * dy));
    }
    state.lastPointer = { x: event.clientX, y: event.clientY };
  }, { capture: true, passive: true });

  window.addEventListener('scroll', () => {
    markInteraction();
    state.scrollCount += 1;
  }, { passive: true });

  document.addEventListener('visibilitychange', () => {
    state.visibilityChanges += 1;
  });

  function fieldKey(target) {
    if (!target) return '';
    return target.id || target.name || (target.dataset && (target.dataset.vid || target.dataset.formid)) || '';
  }

  function isProtectedUrl(url) {
    const path = urlPath(url);
    if (!path) return false;
    if (path.indexOf('/EmsfbShield/v1/') !== -1) return false;
    if (path.indexOf('/Emsfb/v1/forms/message/add') !== -1) return true;
    // Response/tracking lookups are throttled by request-count on the server
    // only; they must not carry an attestation ("quick check") token, so leave
    // them out of the client-side protected set.
    if (path.indexOf('/Emsfb/v1/forms/response/add') !== -1) return true;
    if (path.indexOf('/Emsfb/v1/forms/file/upload') !== -1) return true;
    if (path.indexOf('/Emsfb/v1/forms/payment/') !== -1) return true;
    return false;
  }

  function routeFromUrl(url) {
    const path = urlPath(url);
    const marker = '/Emsfb/v1/';
    const index = path.indexOf(marker);
    return index === -1 ? path : '/Emsfb/v1/' + path.slice(index + marker.length);
  }

  function urlPath(url) {
    try {
      return new URL(url, window.location.href).pathname;
    } catch (error) {
      return String(url || '');
    }
  }

  function parseBody(body) {
    if (!body) return {};
    if (typeof body === 'string') {
      try { return JSON.parse(body); } catch (error) { return {}; }
    }
    if (window.FormData && body instanceof FormData) {
      const out = {};
      body.forEach((value, key) => { if (typeof value === 'string') out[key] = value; });
      return out;
    }
    if (window.URLSearchParams && body instanceof URLSearchParams) {
      const out = {};
      body.forEach((value, key) => { out[key] = value; });
      return out;
    }
    return {};
  }

  function headerValue(headers, name) {
    if (!headers) return '';
    try {
      if (window.Headers && headers instanceof Headers) {
        return headers.get(name) || '';
      }
      if (Array.isArray(headers)) {
        for (let i = 0; i < headers.length; i++) {
          if (String(headers[i][0]).toLowerCase() === name) return String(headers[i][1]);
        }
        return '';
      }
      const keys = Object.keys(headers);
      for (let i = 0; i < keys.length; i++) {
        if (keys[i].toLowerCase() === name) return String(headers[keys[i]]);
      }
    } catch (error) { /* header container we do not understand */ }
    return '';
  }

  /* Must mirror the server-side context builder exactly: body id/form_id/fid,
   * then the `form-id` header EFB core sends on every REST call. Anything
   * beyond that would bind the token to a form id the server cannot see. */
  function inferFormId(body, headers) {
    const parsed = parseBody(body);
    const keys = ['id', 'form_id', 'fid'];
    for (let i = 0; i < keys.length; i++) {
      if (parsed[keys[i]]) return parseInt(parsed[keys[i]], 10) || 0;
    }
    return parseInt(headerValue(headers, 'form-id'), 10) || 0;
  }

  function inferSid(body, headers) {
    const parsed = parseBody(body);
    if (parsed.sid) return String(parsed.sid);
    const fromHeader = headerValue(headers, 'sid');
    if (fromHeader) return fromHeader;
    if (window.efb_var && window.efb_var.sid) return String(window.efb_var.sid);
    return '';
  }

  function metricsSnapshot(formId) {
    const now = Date.now();
    refreshFieldCount();
    installHoneypots();
    return {
      formFieldCount: formFieldCount(formId),
      durationMs: Math.max(0, now - state.startedAt),
      firstInteractionDelayMs: state.firstInteractionAt ? Math.max(0, state.firstInteractionAt - state.startedAt) : 0,
      focusCount: state.focusCount,
      inputCount: state.inputCount,
      keyCount: state.keyCount,
      pointerMoveCount: state.pointerMoveCount,
      pointerDistance: state.pointerDistance,
      touchCount: state.touchCount,
      clickCount: state.clickCount,
      scrollCount: state.scrollCount,
      pasteCount: state.pasteCount,
      visibilityChanges: state.visibilityChanges,
      fieldsTouched: state.fieldsTouched.size,
      fieldCount: state.fieldCount,
      honeypotFilled: state.honeypotFilled,
      touchCapable: state.touchCapable,
      webdriver: state.webdriver
    };
  }

  async function postJson(endpoint, payload) {
    const headers = {
      'Content-Type': 'application/json'
    };
    if (cfg.wpRestNonce) headers['X-WP-Nonce'] = cfg.wpRestNonce;
    const url = cfg.restUrl.replace(/\/$/, '') + '/' + endpoint + '?efb_hs=' + encodeURIComponent(String(Date.now()));
    const response = await withTimeout(originalFetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers,
      body: JSON.stringify(payload)
    }), attestTimeoutMs);
    const responseText = await response.text();
    let data = null;
    try {
      data = JSON.parse(responseText);
    } catch (error) {
      throw new Error('human shield returned non-json response');
    }
    if (!response.ok) {
      const err = new Error(data && data.message ? data.message : 'human shield request failed');
      err.efbCode = data && data.code ? data.code : 'http_' + response.status;
      throw err;
    }
    return data;
  }

  function withTimeout(promise, timeoutMs) {
    let timer = null;
    const timeout = new Promise((resolve, reject) => {
      timer = window.setTimeout(() => reject(new Error('human shield request timed out')), timeoutMs);
    });
    return Promise.race([promise, timeout]).finally(() => {
      if (timer) window.clearTimeout(timer);
    });
  }

  /* Challenges carry no behavior metrics (those travel with attest), so one
   * can be requested ahead of time. As soon as the visitor starts interacting
   * with an EFB form we prefetch a challenge in the background; the submit
   * path then only pays for attest + the protected request itself. Entries
   * are single-use and any miss falls back to a fresh challenge request. */
  const challengePrefetch = {};

  function prefetchKeyOf(route, formId, sid) {
    return route + '|' + formId + '|' + sid;
  }

  function prefetchChallenge(route, formId, sid) {
    if (!formId || !sid) return;
    const key = prefetchKeyOf(route, formId, sid);
    const existing = challengePrefetch[key];
    const now = Math.floor(Date.now() / 1000);
    if (existing && (existing.pending || (existing.expiresAt || 0) - 30 > now)) return;
    const entry = { pending: true, challengeId: '', expiresAt: 0 };
    challengePrefetch[key] = entry;
    postJson('challenge', { route, formId, sid })
      .then((challenge) => {
        if (challenge && challenge.success && challenge.challengeId) {
          entry.challengeId = challenge.challengeId;
          entry.expiresAt = Number(challenge.expiresAt) || 0;
          entry.pending = false;
        } else {
          delete challengePrefetch[key];
        }
      })
      .catch(() => { delete challengePrefetch[key]; });
  }

  function takePrefetchedChallenge(route, formId, sid) {
    const key = prefetchKeyOf(route, formId, sid);
    const entry = challengePrefetch[key];
    if (!entry || entry.pending || !entry.challengeId) return '';
    delete challengePrefetch[key]; // single-use, like the challenge itself
    const now = Math.floor(Date.now() / 1000);
    if ((entry.expiresAt || 0) - 10 <= now) return '';
    return entry.challengeId;
  }

  function maybePrefetchFor(target) {
    if (!target || !target.closest) return;
    const container = target.closest('[id^="body_efb_"], [data-formid]');
    if (!container) return;
    const fromData = container.dataset && container.dataset.formid ? container.dataset.formid : '';
    const formId = parseInt(fromData || String(container.id || '').replace('body_efb_', ''), 10) || 0;
    // Derive sid through the same helper the submit path uses, so the prefetch
    // key can never drift from the challenge the attest step will look for.
    const sid = inferSid(null, null);
    if (formId && sid) prefetchChallenge('/Emsfb/v1/forms/message/add', formId, sid);
  }

  /* Tokens are single-use and bound to one challenge, so every protected
   * request gets a fresh challenge + attestation. Reusing challenges is what
   * broke second submissions (server rejects a used challenge). */
  async function mintToken(route, formId, sid) {
    let challengeId = takePrefetchedChallenge(route, formId, sid);
    if (!challengeId) {
      const challenge = await postJson('challenge', { route, formId, sid });
      if (!challenge || !challenge.success || !challenge.challengeId) {
        const err = new Error('human shield challenge failed');
        err.efbCode = challenge && challenge.code ? challenge.code : 'challenge_failed';
        throw err;
      }
      challengeId = challenge.challengeId;
    }
    const result = await postJson('attest', {
      challengeId,
      route,
      formId,
      sid,
      metrics: metricsSnapshot(formId)
    });
    if (!result || !result.success || !result.token) {
      const err = new Error('human shield attestation failed');
      err.efbCode = result && result.code ? result.code : 'attest_failed';
      throw err;
    }
    return result.token;
  }

  async function tokenFor(url, body, headers) {
    const route = routeFromUrl(url);
    const formId = inferFormId(body, headers);
    const sid = inferSid(body, headers);
    try {
      return await mintToken(route, formId, sid);
    } catch (error) {
      // Server told us the shield is off or the host is not ready: do not
      // retry, the request should simply continue without a token.
      if (error && (error.efbCode === 'shield_disabled' || error.efbCode === 'requirements_missing')) {
        throw error;
      }
      // One retry with a completely fresh challenge covers expired/used
      // challenges and transient network hiccups.
      return await mintToken(route, formId, sid);
    }
  }

  function mergeHeader(headers, name, value) {
    if (window.Headers && headers instanceof Headers) {
      headers.set(name, value);
      return headers;
    }
    const out = Object.assign({}, headers || {});
    out[name] = value;
    return out;
  }

  window.fetch = async function (input, init) {
    const requestUrl = typeof input === 'string' ? input : (input && input.url);
    if (!isProtectedUrl(requestUrl)) {
      return originalFetch(input, init);
    }

    init = init ? Object.assign({}, init) : {};
    const requestHeaders = init.headers || (input && input.headers) || null;
    const body = init.body || (input && input.body) || null;
    try {
      const token = await tokenFor(requestUrl, body, requestHeaders);
      init.headers = mergeHeader(requestHeaders, 'X-EFB-Human-Token', token);
      init.headers = mergeHeader(init.headers, 'X-EFB-Shield-Version', cfg.pluginVersion || '0.1.0');
    } catch (error) {
      // Never break the submit from the client side; the server makes the
      // final call and answers with a translated, user-readable message.
      init.headers = mergeHeader(requestHeaders, 'X-EFB-Shield-Client-Error', (error && error.efbCode) || 'attest_failed');
      if (window.console && console.warn) {
        console.warn('[EFB Form Security] Could not attest this request (' + ((error && error.message) || 'unknown error') + '). The server-side shield decides.');
      }
    }
    return originalFetch(input, init);
  };

  if (originalOpen && originalSend && originalSetHeader) {
    window.XMLHttpRequest.prototype.open = function (method, url) {
      this.__efbHsUrl = url;
      this.__efbHsMethod = method;
      this.__efbHsHeaders = {};
      return originalOpen.apply(this, arguments);
    };

    window.XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
      if (this.__efbHsHeaders) this.__efbHsHeaders[String(name).toLowerCase()] = String(value);
      return originalSetHeader.apply(this, arguments);
    };

    window.XMLHttpRequest.prototype.send = function (body) {
      if (!isProtectedUrl(this.__efbHsUrl)) {
        return originalSend.call(this, body);
      }
      tokenFor(this.__efbHsUrl, body, this.__efbHsHeaders)
        .then((token) => {
          try {
            originalSetHeader.call(this, 'X-EFB-Human-Token', token);
            originalSetHeader.call(this, 'X-EFB-Shield-Version', cfg.pluginVersion || '0.1.0');
          } catch (error) { /* request already sent or header rejected */ }
          originalSend.call(this, body);
        })
        .catch((error) => {
          try {
            originalSetHeader.call(this, 'X-EFB-Shield-Client-Error', (error && error.efbCode) || 'attest_failed');
          } catch (headerError) { /* ignore: header stage already closed */ }
          originalSend.call(this, body);
        });
      return undefined;
    };
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      refreshFieldCount();
      installHoneypots();
    });
  } else {
    refreshFieldCount();
    installHoneypots();
  }
})();
