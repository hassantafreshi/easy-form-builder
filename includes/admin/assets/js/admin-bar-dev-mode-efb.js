/* global efbAdminBarDevMode */
(function () {
  'use strict';

  var config = window.efbAdminBarDevMode;
  var item = document.getElementById('wp-admin-bar-efb-development-mode');

  if (!config || !item) {
    return;
  }

  var link = item.querySelector('.ab-item');
  var label = item.querySelector('.efb-admin-bar-dev-mode__label');
  var busy = false;

  if (!link || !label || !window.fetch || !window.URLSearchParams) {
    return;
  }

  function showNotice(message, type, offerReload) {
    var notice = document.getElementById('efb-admin-bar-dev-mode-notice');

    if (!notice) {
      notice = document.createElement('div');
      notice.id = 'efb-admin-bar-dev-mode-notice';
      notice.setAttribute('role', 'status');
      notice.setAttribute('aria-live', 'polite');
      document.body.appendChild(notice);
    }

    notice.className = 'efb-admin-bar-dev-mode-notice is-' + type;
    notice.textContent = '';

    var messageElement = document.createElement('span');
    messageElement.className = 'efb-admin-bar-dev-mode-notice__message';
    messageElement.textContent = message;
    notice.appendChild(messageElement);

    if (offerReload) {
      var reloadButton = document.createElement('button');
      reloadButton.type = 'button';
      reloadButton.className = 'efb-admin-bar-dev-mode-notice__reload';
      reloadButton.textContent = config.labels.reload;
      reloadButton.addEventListener('click', function () {
        window.location.reload();
      });
      notice.appendChild(reloadButton);
    }

    var closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'efb-admin-bar-dev-mode-notice__close';
    closeButton.setAttribute('aria-label', config.labels.close);
    closeButton.title = config.labels.close;
    closeButton.textContent = '\u00d7';
    closeButton.addEventListener('click', function () {
      notice.remove();
    });
    notice.appendChild(closeButton);
  }

  function setState(enabled) {
    item.classList.toggle('is-enabled', enabled);
    item.classList.toggle('is-disabled', !enabled);
    label.textContent = enabled ? config.labels.on : config.labels.off;
    link.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    link.setAttribute('aria-label', label.textContent);
  }

  setState(item.classList.contains('is-enabled'));
  link.setAttribute('role', 'button');

  link.addEventListener('click', function (event) {
    event.preventDefault();

    if (busy) {
      return;
    }

    busy = true;
    item.classList.add('is-busy');
    link.setAttribute('aria-busy', 'true');
    label.textContent = config.labels.updating;

    window.fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: new URLSearchParams({
        action: 'efb_toggle_development_mode',
        nonce: config.nonce
      }).toString()
    })
      .then(function (response) {
        return response.json()
          .catch(function () {
            return { success: false, data: { message: config.labels.error } };
          })
          .then(function (payload) {
            if (!response.ok || !payload.success) {
              throw new Error(payload && payload.data && payload.data.message ? payload.data.message : config.labels.error);
            }
            return payload.data;
          });
      })
      .then(function (data) {
        setState(Boolean(data.enabled));
        showNotice(data.message, 'success', true);
        busy = false;
        item.classList.remove('is-busy');
        link.removeAttribute('aria-busy');
      })
      .catch(function (error) {
        setState(item.classList.contains('is-enabled'));
        showNotice(error && error.message ? error.message : config.labels.error, 'error');
        busy = false;
        item.classList.remove('is-busy');
        link.removeAttribute('aria-busy');
      });
  });
}());
