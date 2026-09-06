/**
 * The five-star invitation.
 *
 * A small state machine over the markup Review_Request::render_modal_efb()
 * printed: ask -> (reward | improve) -> done. No dependencies, no jQuery, and
 * no bootstrap - the modal is opened and closed by this file alone so it
 * cannot be broken by whichever builder screen it happens to appear on.
 *
 * Three rules this file keeps:
 *
 *   1. A choice is recorded before the modal closes, never after. "Later" and
 *      "Do not ask again" fire their request first so a closed tab still
 *      counts as an answer.
 *   2. A rating below the threshold never reaches WordPress.org. It swaps the
 *      footer for the support route instead.
 *   3. The star row is lit by index, not by CSS sibling selectors, because a
 *      sibling selector fills the wrong end of the row in RTL.
 */

(function () {
	'use strict';

	var cfg = window.efb_review;

	if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
		return;
	}

	var modal = document.getElementById('efb-review-modal');

	if (!modal) {
		return;
	}

	var text = cfg.text || {};
	var threshold = parseInt(cfg.threshold, 10) || 5;

	/*
	 * Preview mode: ?efb_review_preview=1 on any Easy Form Builder screen.
	 * Every screen can be walked, and nothing is written or sent - no answer is
	 * recorded, no snooze is spent, and the claim step answers itself instead
	 * of asking the service for a real coupon.
	 */
	var preview = Number(cfg.preview) === 1;

	var stars = Array.prototype.slice.call(modal.querySelectorAll('[data-efb-review-rate]'));
	var hint = modal.querySelector('[data-efb-review-hint]');
	var errorBox = modal.querySelector('[data-efb-review-error]');
	var emailInput = modal.querySelector('[data-efb-review-email]');

	var steps = {};
	Array.prototype.forEach.call(modal.querySelectorAll('[data-efb-review-step]'), function (el) {
		steps[el.getAttribute('data-efb-review-step')] = el;
	});

	var buttons = {};
	Array.prototype.forEach.call(modal.querySelectorAll('[data-efb-review-action]'), function (el) {
		buttons[el.getAttribute('data-efb-review-action')] = el;
	});

	var rating = 0;
	var reviewOpened = false;
	var closing = false;
	var lastFocused = null;

	/* ------------------------------------------------------------------ */
	/* Plumbing                                                            */
	/* ------------------------------------------------------------------ */

	function post(op, extra) {
		var body = new URLSearchParams();
		body.set('action', 'emsfb_review_request');
		body.set('nonce', cfg.nonce);
		body.set('op', op);

		Object.keys(extra || {}).forEach(function (key) {
			body.set(key, extra[key]);
		});

		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json();
		});
	}

	/**
	 * Record a choice without caring whether it lands.
	 *
	 * keepalive lets the request outlive the page, so "Later" is still stored
	 * when someone clicks it and immediately navigates away.
	 */
	function record(op) {
		if (preview) {
			return;
		}

		try {
			var body = new URLSearchParams();
			body.set('action', 'emsfb_review_request');
			body.set('nonce', cfg.nonce);
			body.set('op', op);

			fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				keepalive: true,
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			}).catch(function () {});
		} catch (e) {
			/* A dismissal that cannot be recorded must still close the modal. */
		}
	}

	function showError(message) {
		if (!errorBox) {
			return;
		}

		if (!message) {
			errorBox.hidden = true;
			errorBox.textContent = '';
			return;
		}

		errorBox.textContent = message;
		errorBox.hidden = false;
	}

	/* ------------------------------------------------------------------ */
	/* Open and close                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Whether some other Easy Form Builder dialog already has the screen.
	 *
	 * The invitation arrives on a timer, so it can land while the person is
	 * part-way through a field editor or a delete confirmation. Stacking a
	 * request for a favour on top of somebody's work is the fastest way to
	 * earn the two-star review this feature exists to avoid - so it stands
	 * down and waits for a page load where nothing else is open.
	 */
	function somethingElseIsOpen() {
		if (document.body.classList.contains('modal-open')) {
			return true;
		}

		if (document.querySelector('.efb-modal-backdrop')) {
			return true;
		}

		var shared = document.getElementById('settingModalEfb');

		return !!(shared && shared.classList.contains('show'));
	}

	function open() {
		lastFocused = document.activeElement;
		modal.hidden = false;
		document.body.classList.add('efb-dlg-open');
		document.addEventListener('keydown', onKeydown);

		// The dialog itself takes focus, never the first star: focusing a star
		// lights it, and a lit star reads as a rating the person did not give.
		var shell = modal.querySelector('.efb-dlg__shell');
		if (shell) {
			shell.focus();
		}
	}

	function close() {
		if (closing) {
			return;
		}
		closing = true;

		modal.hidden = true;
		document.body.classList.remove('efb-dlg-open');
		document.removeEventListener('keydown', onKeydown);

		if (lastFocused && typeof lastFocused.focus === 'function') {
			lastFocused.focus();
		}
	}

	/**
	 * Escape means "later", not "never" - closing a dialog you did not ask for
	 * should not spend the one permanent answer somebody has.
	 */
	function onKeydown(event) {
		if (event.key === 'Escape') {
			event.preventDefault();
			record('later');
			close();
			return;
		}

		if (event.key !== 'Tab') {
			return;
		}

		// Keep focus inside the dialog while it is open.
		var focusable = modal.querySelectorAll(
			'button:not([hidden]):not([disabled]), a[href]:not([hidden]), input:not([hidden])'
		);
		if (!focusable.length) {
			return;
		}

		var first = focusable[0];
		var last = focusable[focusable.length - 1];

		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	/* ------------------------------------------------------------------ */
	/* Steps                                                               */
	/* ------------------------------------------------------------------ */

	function showStep(name) {
		Object.keys(steps).forEach(function (key) {
			steps[key].hidden = key !== name;
		});
		showError('');
	}

	function showButtons(names) {
		Object.keys(buttons).forEach(function (key) {
			buttons[key].hidden = names.indexOf(key) === -1;
		});
	}

	/* ------------------------------------------------------------------ */
	/* Stars                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Light the first `value` stars.
	 *
	 * Index-based on purpose: `:hover ~ .star` would light the stars that come
	 * *after* the pointer, which in an RTL row is the low end of the scale.
	 */
	function paint(value) {
		stars.forEach(function (star, index) {
			var lit = index < value;
			star.classList.toggle('is-lit', lit);
			star.classList.toggle('is-peak', lit && index === value - 1);
			star.setAttribute('aria-checked', index === rating - 1 ? 'true' : 'false');
		});
	}

	function pick(value) {
		rating = value;
		paint(value);

		// The hint lives in the ask step, which is about to be replaced either
		// way; clearing it stops a stale prompt flashing during the swap.
		if (hint) {
			hint.textContent = '';
			hint.classList.toggle('is-happy', value >= threshold);
		}

		// Remember the number before branching, so a rating is not lost if the
		// person closes the tab on the next screen.
		if (!preview) {
			post('rate', { rating: String(value) }).catch(function () {});
		}

		if (value >= threshold) {
			showStep('reward');
			showButtons(['later', 'review']);
		} else {
			showStep('improve');
			showButtons(['support', 'close']);
		}
	}

	stars.forEach(function (star, index) {
		var value = parseInt(star.getAttribute('data-efb-review-rate'), 10) || index + 1;

		star.addEventListener('mouseenter', function () {
			paint(value);
		});
		star.addEventListener('focus', function () {
			paint(value);
		});
		star.addEventListener('click', function () {
			pick(value);
		});
	});

	var starRow = modal.querySelector('.efb-review__stars');
	if (starRow) {
		starRow.addEventListener('mouseleave', function () {
			paint(rating);
		});
	}

	/* ------------------------------------------------------------------ */
	/* Footer actions                                                      */
	/* ------------------------------------------------------------------ */

	if (buttons.later) {
		buttons.later.addEventListener('click', function () {
			record('later');
			close();
		});
	}

	if (buttons.never) {
		buttons.never.addEventListener('click', function () {
			record('never');
			close();
		});
	}

	if (buttons.close) {
		buttons.close.addEventListener('click', function () {
			close();
		});
	}

	if (buttons.support) {
		// The link opens in a new tab on its own; this only ends the ask.
		buttons.support.addEventListener('click', function () {
			record('never');
			setTimeout(close, 150);
		});
	}

	/*
	 * The review link opens WordPress.org in a new tab. Only after it has been
	 * opened does the claim button appear - asking for a code before anyone
	 * could have written a review is how you teach people to click past it.
	 */
	if (buttons.review) {
		buttons.review.addEventListener('click', function () {
			reviewOpened = true;
			showButtons(['later', 'claim']);
		});
	}

	if (buttons.claim) {
		buttons.claim.addEventListener('click', function () {
			if (!reviewOpened) {
				return;
			}

			// A preview answers itself rather than asking the service for a
			// coupon nobody is going to redeem.
			if (preview) {
				var sample = cfg.sample || {};
				finish({
					title: sample.title,
					message: sample.message,
					coupon: { state: 'issued', code: sample.code }
				});
				return;
			}

			var label = buttons.claim.querySelector('span');
			var original = label ? label.textContent : '';

			buttons.claim.disabled = true;
			if (label) {
				label.textContent = text.sending || '';
			}
			showError('');

			post('claim', { email: emailInput ? emailInput.value : '' })
				.then(function (response) {
					var data = response && response.data ? response.data : {};

					if (!response || !response.success || !data.ok) {
						buttons.claim.disabled = false;
						if (label) {
							label.textContent = original;
						}
						showError(data.message || text.failed || '');
						return;
					}

					finish(data);
				})
				.catch(function () {
					buttons.claim.disabled = false;
					if (label) {
						label.textContent = original;
					}
					showError(text.failed || '');
				});
		});
	}

	/* ------------------------------------------------------------------ */
	/* The last screen                                                     */
	/* ------------------------------------------------------------------ */

	function finish(data) {
		var title = modal.querySelector('[data-efb-review-done-title]');
		var message = modal.querySelector('[data-efb-review-done-message]');
		var couponBox = modal.querySelector('[data-efb-review-coupon]');
		var codeBox = modal.querySelector('[data-efb-review-code]');

		if (title) {
			title.textContent = data.title || '';
		}
		if (message) {
			message.textContent = data.message || '';
		}

		var coupon = data.coupon || {};
		if (couponBox && codeBox && coupon.state === 'issued' && coupon.code) {
			codeBox.textContent = coupon.code;
			couponBox.hidden = false;
		} else if (couponBox) {
			couponBox.hidden = true;
		}

		modal.classList.remove('efb-tone-warn');
		modal.classList.add('efb-tone-success');

		showStep('done');
		showButtons(['close']);
	}

	var copyButton = modal.querySelector('[data-efb-review-copy]');
	if (copyButton) {
		copyButton.addEventListener('click', function () {
			var codeBox = modal.querySelector('[data-efb-review-code]');
			var label = copyButton.querySelector('span');

			if (!codeBox || !codeBox.textContent) {
				return;
			}

			var done = function () {
				if (!label) {
					return;
				}
				label.textContent = text.copied || '';
				setTimeout(function () {
					label.textContent = text.copy || '';
				}, 2000);
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(codeBox.textContent).then(done).catch(function () {});
				return;
			}

			// execCommand is gone from the spec but is the only fallback that
			// works on an http:// admin, where the async clipboard is blocked.
			try {
				var range = document.createRange();
				range.selectNodeContents(codeBox);
				var selection = window.getSelection();
				selection.removeAllRanges();
				selection.addRange(range);
				document.execCommand('copy');
				selection.removeAllRanges();
				done();
			} catch (e) {
				/* Nothing to do: the code is on screen and can be read. */
			}
		});
	}

	/* ------------------------------------------------------------------ */
	/* Go                                                                  */
	/* ------------------------------------------------------------------ */

	Array.prototype.forEach.call(modal.querySelectorAll('[data-efb-review-close]'), function (el) {
		el.addEventListener('click', function () {
			record('later');
			close();
		});
	});

	showStep('ask');
	showButtons(['never', 'later']);

	/*
	 * A moment's delay so the invitation does not race the page it appears on,
	 * then a wait for a clear screen. The server has already counted this ask,
	 * so giving up the moment another dialog happens to be open would spend
	 * somebody's turn on a modal they never saw - it waits for them to finish
	 * instead, and only stops looking after half a minute of a busy screen.
	 */
	var waited = 0;

	function openWhenClear() {
		if (!somethingElseIsOpen()) {
			open();
			return;
		}

		waited += 1500;

		if (waited <= 30000) {
			setTimeout(openWhenClear, 1500);
		}
	}

	setTimeout(openWhenClear, 900);
})();
