/**
 * Deactivation feedback modal (Plugins screen).
 *
 * Intercepts the Deactivate link for Easy Form Builder, asks one question, and
 * then continues to the very same URL WordPress was going to follow anyway.
 * Nothing in here may end without that navigation happening: an unanswered
 * question must never leave someone stuck with a plugin they asked to turn off.
 *
 * No jQuery on purpose - this runs on a core screen shared with every other
 * plugin, so it depends on nothing.
 */
(function () {
	'use strict';

	var cfg = window.efb_deactivate;
	if (!cfg || !cfg.text) {
		return;
	}

	// The markup is printed by PHP further down the footer, so nothing is
	// resolved until the document has finished parsing.
	var modal;
	var box;
	var bodyPane;
	var donePane;
	var doneMessage;
	var couponWrap;
	var couponCode;
	var copyButton;
	var contactWrap;
	var emailInput;
	var consentInput;
	var honeypot;
	var errorLine;
	var skipButton;
	var submitButton;
	var continueButton;
	var reasons = [];

	var deactivateUrl = '';
	var lastFocus = null;
	var busy = false;

	/**
	 * Is this the Deactivate link of our own plugin row?
	 *
	 * Two independent checks: the row's data-plugin attribute, which core
	 * prints, and the query string of the link itself. Either one alone has
	 * been known to change shape between WordPress releases.
	 */
	function isOurDeactivateLink(link) {
		var href = link.getAttribute('href') || '';
		if (href.indexOf('action=deactivate') === -1) {
			return false;
		}

		var encoded = encodeURIComponent(cfg.plugin);
		if (href.indexOf('plugin=' + encoded) !== -1 || href.indexOf('plugin=' + cfg.plugin) !== -1) {
			return true;
		}

		var row = link.closest ? link.closest('tr[data-plugin]') : null;
		return !!row && row.getAttribute('data-plugin') === cfg.plugin;
	}

	function openModal(url) {
		deactivateUrl = url;
		lastFocus = document.activeElement;
		modal.hidden = false;
		document.body.classList.add('efb-deactivate-open');

		var first = modal.querySelector('input[name="efb_deactivate_reason"]');
		if (first) {
			first.focus();
		}
	}

	function closeModal() {
		modal.hidden = true;
		document.body.classList.remove('efb-deactivate-open');
		if (lastFocus && lastFocus.focus) {
			lastFocus.focus();
		}
	}

	function proceed() {
		if (deactivateUrl) {
			window.location.href = deactivateUrl;
		} else {
			closeModal();
		}
	}

	function selectedReason() {
		var checked = modal.querySelector('input[name="efb_deactivate_reason"]:checked');
		if (!checked) {
			return null;
		}

		var item = checked.closest ? checked.closest('.efb-deactivate-reason') : null;
		if (!item) {
			return null;
		}

		return {
			key: item.getAttribute('data-reason'),
			needsDetail: item.getAttribute('data-detail') === '1',
			wantsContact: item.getAttribute('data-contact') === '1',
			textarea: item.querySelector('.efb-deactivate-details')
		};
	}

	function showError(message) {
		if (!errorLine) {
			return;
		}
		errorLine.textContent = message;
		errorLine.hidden = !message;
	}

	function onReasonChange() {
		var current = selectedReason();

		reasons.forEach(function (item) {
			var panel = item.querySelector('.efb-deactivate-panel');
			var isCurrent = !!current && item.getAttribute('data-reason') === current.key;
			item.classList.toggle('is-selected', isCurrent);
			if (panel) {
				panel.hidden = !isCurrent;
			}
		});

		if (contactWrap) {
			contactWrap.hidden = !current || !current.wantsContact;
		}

		showError('');

		if (current && current.textarea) {
			current.textarea.focus();
		}
	}

	function submitFeedback() {
		if (busy) {
			return;
		}

		var current = selectedReason();
		if (!current) {
			showError(cfg.text.chooseReason);
			return;
		}

		var details = current.textarea ? current.textarea.value.trim() : '';
		if (current.needsDetail && !details) {
			showError(cfg.text.detailsRequired);
			if (current.textarea) {
				current.textarea.focus();
			}
			return;
		}

		// A one-word bug report is scored as coupon farming by the service, so
		// ask for the sentence here rather than let it be filed as spam.
		if (current.key === 'bug' && details.length < (cfg.minBugText || 15)) {
			showError(cfg.text.detailsTooShort);
			if (current.textarea) {
				current.textarea.focus();
			}
			return;
		}

		var wantsContact = current.wantsContact && consentInput && consentInput.checked;
		var payload = new FormData();
		payload.append('action', 'emsfb_deactivation_feedback');
		payload.append('nonce', cfg.nonce);
		payload.append('reason', current.key);
		payload.append('details', details);
		payload.append('email', wantsContact && emailInput ? emailInput.value.trim() : '');
		payload.append('contact_ok', wantsContact ? '1' : '0');
		payload.append('hp', honeypot ? honeypot.value : '');

		busy = true;
		showError('');
		submitButton.disabled = true;
		skipButton.disabled = true;
		submitButton.textContent = cfg.text.sending;

		// However this request ends - answer, error, or the browser giving up -
		// the person still gets a way forward.
		request(payload)
			.then(function (response) {
				if (response && response.success && response.data && response.data.ok) {
					showDone(response.data);
					return;
				}

				var message = response && response.data && response.data.message
					? response.data.message
					: cfg.text.sendFailed;
				failGracefully(message);
			})
			.catch(function () {
				failGracefully(cfg.text.sendFailed);
			});
	}

	/**
	 * POST the payload, giving up after 15 seconds.
	 */
	function request(payload) {
		if (!window.fetch) {
			return legacyRequest(payload);
		}

		var options = { method: 'POST', body: payload, credentials: 'same-origin' };
		var timer = null;

		if (window.AbortController) {
			var controller = new AbortController();
			options.signal = controller.signal;
			timer = window.setTimeout(function () {
				controller.abort();
			}, 15000);
		}

		return window.fetch(cfg.ajaxUrl, options)
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				if (timer) {
					window.clearTimeout(timer);
				}
				return json;
			}, function (error) {
				if (timer) {
					window.clearTimeout(timer);
				}
				throw error;
			});
	}

	/**
	 * XHR fallback for browsers without fetch.
	 */
	function legacyRequest(payload) {
		return new Promise(function (resolve, reject) {
			var xhr = new XMLHttpRequest();
			xhr.open('POST', cfg.ajaxUrl, true);
			xhr.timeout = 15000;
			xhr.onload = function () {
				try {
					resolve(JSON.parse(xhr.responseText));
				} catch (e) {
					reject(e);
				}
			};
			xhr.onerror = function () {
				reject(new Error('network'));
			};
			xhr.ontimeout = function () {
				reject(new Error('timeout'));
			};
			xhr.send(payload);
		});
	}

	function showDone(data) {
		busy = false;
		bodyPane.hidden = true;
		donePane.hidden = false;
		submitButton.hidden = true;
		skipButton.hidden = true;
		continueButton.hidden = false;
		doneMessage.textContent = data.message || cfg.text.thanksMessage;

		var code = data.coupon && data.coupon.code ? data.coupon.code : '';
		if (code) {
			couponCode.textContent = code;
			couponWrap.hidden = false;
			continueButton.focus();
			return;
		}

		continueButton.focus();
		// Nothing left to read, so do not make anyone click twice.
		window.setTimeout(function () {
			if (!modal.hidden) {
				proceed();
			}
		}, 2500);
	}

	function failGracefully(message) {
		busy = false;
		showError(message);
		submitButton.hidden = true;
		skipButton.hidden = true;
		continueButton.hidden = false;
		continueButton.disabled = false;
		continueButton.focus();
	}

	function copyCoupon() {
		var code = couponCode.textContent || '';
		if (!code) {
			return;
		}

		var done = function () {
			copyButton.textContent = cfg.text.copied;
		};

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(code).then(done, fallbackCopy);
			return;
		}

		fallbackCopy();

		function fallbackCopy() {
			var scratch = document.createElement('textarea');
			scratch.value = code;
			scratch.setAttribute('readonly', 'readonly');
			scratch.style.position = 'absolute';
			scratch.style.left = '-9999px';
			document.body.appendChild(scratch);
			scratch.select();
			try {
				document.execCommand('copy');
				done();
			} catch (e) {
				// Copying is a convenience; the code is on screen either way.
			}
			document.body.removeChild(scratch);
		}
	}

	function init() {
		modal = document.getElementById('efb-deactivate-modal');
		if (!modal) {
			return;
		}

		box = modal.querySelector('.efb-deactivate-box');
		bodyPane = modal.querySelector('.efb-deactivate-body');
		donePane = modal.querySelector('.efb-deactivate-done');
		doneMessage = modal.querySelector('.efb-deactivate-done-message');
		couponWrap = modal.querySelector('.efb-deactivate-coupon');
		couponCode = modal.querySelector('.efb-deactivate-coupon-code');
		copyButton = modal.querySelector('.efb-deactivate-copy');
		contactWrap = modal.querySelector('.efb-deactivate-contact');
		emailInput = modal.querySelector('.efb-deactivate-email');
		consentInput = modal.querySelector('.efb-deactivate-consent-input');
		honeypot = modal.querySelector('.efb-deactivate-hp-input');
		errorLine = modal.querySelector('.efb-deactivate-error');
		skipButton = modal.querySelector('.efb-deactivate-skip');
		submitButton = modal.querySelector('.efb-deactivate-submit');
		continueButton = modal.querySelector('.efb-deactivate-continue');
		reasons = [].slice.call(modal.querySelectorAll('.efb-deactivate-reason'));

		if (!skipButton || !submitButton || !continueButton) {
			return;
		}

		document.addEventListener('click', function (event) {
			var link = event.target && event.target.closest ? event.target.closest('a') : null;
			if (!link || !isOurDeactivateLink(link)) {
				return;
			}

			event.preventDefault();
			openModal(link.href);
		});

		modal.addEventListener('click', function (event) {
			if (event.target && event.target.getAttribute && event.target.getAttribute('data-efb-close')) {
				closeModal();
			}
		});

		modal.addEventListener('change', function (event) {
			if (event.target && event.target.name === 'efb_deactivate_reason') {
				onReasonChange();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (modal.hidden) {
				return;
			}

			if (event.key === 'Escape') {
				closeModal();
				return;
			}

			// Keep Tab inside the dialog while it is open.
			if (event.key !== 'Tab' || !box) {
				return;
			}

			var focusable = box.querySelectorAll('a[href], button:not([disabled]), textarea, input, select');
			var visible = [].slice.call(focusable).filter(function (node) {
				return node.offsetParent !== null;
			});

			if (!visible.length) {
				return;
			}

			var first = visible[0];
			var last = visible[visible.length - 1];

			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});

		skipButton.addEventListener('click', proceed);
		submitButton.addEventListener('click', submitFeedback);
		continueButton.addEventListener('click', proceed);

		if (copyButton) {
			copyButton.addEventListener('click', copyCoupon);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
