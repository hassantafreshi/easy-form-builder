/**
 * The rating dialog.
 *
 * A state machine over the markup Review_Request::render_modal_efb() printed:
 *
 *   ask --(4-5)--> praise --> claim --> checking --> result
 *    |
 *    `--(1-3)--> feedback --> sent
 *
 * No dependencies, no jQuery, no bootstrap - the dialog is opened and closed by
 * this file alone so it cannot be broken by whichever builder screen it happens
 * to appear on.
 *
 * Four rules this file keeps:
 *
 *   1. A choice is recorded before the dialog closes, never after. "Later" and
 *      "Do not ask again" fire their request first, with keepalive, so a closed
 *      tab still counts as an answer.
 *   2. An unhappy rating never reaches WordPress.org. It swaps to the private
 *      feedback step instead, and the review link is never shown.
 *   3. The star row is lit by index, not by CSS sibling selectors, because a
 *      sibling selector fills the wrong end of the row in RTL.
 *   4. The outcome of a claim is whatever the server said. This file never
 *      decides that a review exists; it only draws the seven answers.
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
	var outcomes = cfg.outcomes || {};
	var threshold = parseInt(cfg.threshold, 10) || 4;

	/*
	 * Preview mode: ?efb_review_preview=1 on any Easy Form Builder screen.
	 * Every screen can be walked and nothing is written or sent - no answer is
	 * recorded, no snooze is spent, and a claim cycles through the seven server
	 * answers locally instead of asking White Studio for a real coupon.
	 */
	var preview = Number(cfg.preview) === 1;
	var previewOrder = ['granted', 'pending', 'notFound', 'lowStars', 'used', 'badEmail', 'server'];

	/*
	 * Which outcome the next preview claim will show. Kept in sessionStorage,
	 * not a local counter: several of the outcomes offer no way back to the
	 * claim step, so seeing all seven means reloading - and a counter in this
	 * closure would restart at `granted` every time, leaving the other six
	 * unreachable.
	 */
	function previewCursor(next) {
		try {
			if (next !== undefined) {
				sessionStorage.setItem('efb_review_preview_at', String(next));
				return next;
			}
			return parseInt(sessionStorage.getItem('efb_review_preview_at'), 10) || 0;
		} catch (e) {
			// Private browsing, or storage switched off. One outcome is still
			// better than a broken preview.
			return 0;
		}
	}

	var stars = Array.prototype.slice.call(modal.querySelectorAll('[data-efb-review-rate]'));
	var hint = modal.querySelector('[data-efb-review-hint]');
	var errorBox = modal.querySelector('[data-efb-review-error]');
	var usernameInput = modal.querySelector('[data-efb-review-username]');
	var emailInput = modal.querySelector('[data-efb-review-email]');
	var commentInput = modal.querySelector('[data-efb-review-comment]');
	var contactInput = modal.querySelector('[data-efb-review-contact]');

	var steps = {};
	Array.prototype.forEach.call(modal.querySelectorAll('[data-efb-review-step]'), function (el) {
		steps[el.getAttribute('data-efb-review-step')] = el;
	});

	// Every action, for binding handlers to.
	var buttons = {};
	Array.prototype.forEach.call(modal.querySelectorAll('[data-efb-review-action]'), function (el) {
		buttons[el.getAttribute('data-efb-review-action')] = el;
	});

	// Only the footer's, for showing and hiding. The praise step's "I posted
	// it" and review link carry the same attribute but belong to their step -
	// hiding them along with the footer left the happy path with no way out.
	var footButtons = {};
	Array.prototype.forEach.call(modal.querySelectorAll('.efb-dlg__foot [data-efb-review-action]'), function (el) {
		footButtons[el.getAttribute('data-efb-review-action')] = el;
	});

	var rating = 0;
	var reviewOpened = false;
	var closing = false;
	var lastFocused = null;
	var checkTimers = [];

	/* ------------------------------------------------------------------ */
	/* Plumbing                                                            */
	/* ------------------------------------------------------------------ */

	function post(op, extra) {
		var body = new URLSearchParams();
		body.set('action', 'emsfb_review_request');
		body.set('nonce', cfg.nonce);
		body.set('op', op);

		Object.keys(extra || {}).forEach(function (key) {
			var value = extra[key];
			if (Array.isArray(value)) {
				value.forEach(function (item) { body.append(key + '[]', item); });
				return;
			}
			body.set(key, value);
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
			/* A dismissal that cannot be recorded must still close the dialog. */
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

	function busy(button, on) {
		if (!button) {
			return;
		}

		var label = button.querySelector('span');

		if (on) {
			button.disabled = true;
			if (label && !button.dataset.efbLabel) {
				button.dataset.efbLabel = label.textContent;
				label.textContent = text.sending || '';
			}
			return;
		}

		button.disabled = false;
		if (label && button.dataset.efbLabel) {
			label.textContent = button.dataset.efbLabel;
			delete button.dataset.efbLabel;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Open and close                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Whether some other Easy Form Builder dialog already has the screen.
	 *
	 * The invitation arrives on a timer, so it can land while the person is
	 * part-way through a field editor or a delete confirmation. Stacking a
	 * request for a favour on top of somebody's work is the fastest way to earn
	 * the two-star review this feature exists to avoid.
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

		checkTimers.forEach(clearTimeout);
		checkTimers = [];

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

		var focusable = modal.querySelectorAll(
			'button:not([hidden]):not([disabled]), a[href]:not([hidden]), input:not([hidden]), textarea:not([hidden])'
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

	/** Which footer buttons each step offers. */
	var FOOTER = {
		ask: ['never', 'later'],
		praise: ['later'],
		claim: ['later', 'getCode'],
		checking: [],
		feedback: ['later', 'send'],
		sent: ['done']
	};

	function showStep(name) {
		Object.keys(steps).forEach(function (key) {
			steps[key].hidden = key !== name;
		});

		showError('');

		if (FOOTER[name]) {
			showButtons(FOOTER[name]);
		}

		// Stars that were won travel with the person from step to step.
		if (name === 'praise' || name === 'claim' || name === 'feedback') {
			paintWon(steps[name]);
		}

		var focusable = steps[name] ? steps[name].querySelector('input, textarea, a, button') : null;
		if (focusable && (name === 'claim' || name === 'feedback')) {
			focusable.focus();
		}
	}

	function showButtons(names) {
		Object.keys(footButtons).forEach(function (key) {
			footButtons[key].hidden = names.indexOf(key) === -1;
		});
	}

	/** Fill the little star strip that heads praise, claim and feedback. */
	function paintWon(step) {
		var host = step ? step.querySelector('[data-efb-review-won]') : null;
		if (!host) {
			return;
		}

		// Praise and claim always show five: that is the review being asked
		// for, not the rating that was given.
		var count = (step === steps.feedback) ? Math.max(1, rating) : 5;
		host.innerHTML = '';

		for (var i = 0; i < count; i++) {
			var star = document.createElement('i');
			star.className = 'bi bi-star-fill';
			host.appendChild(star);
		}
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

			var icon = star.querySelector('i');
			if (icon) {
				icon.className = lit ? 'bi bi-star-fill' : 'bi bi-star';
			}
		});

		if (hint) {
			hint.textContent = value ? (text['r' + value] || '') : (text.r0 || '');
			hint.classList.toggle('is-picked', value > 0);
		}
	}

	function pick(value) {
		rating = value;
		paint(value);

		// Remember the number before branching, so a rating is not lost if the
		// person closes the tab on the next screen.
		if (!preview) {
			post('rate', { rating: String(value) }).catch(function () {});
		}

		showStep(value >= threshold ? 'praise' : 'feedback');
	}

	stars.forEach(function (star, index) {
		var value = parseInt(star.getAttribute('data-efb-review-rate'), 10) || index + 1;

		star.addEventListener('mouseenter', function () { paint(value); });
		star.addEventListener('focus', function () { paint(value); });
		star.addEventListener('click', function () { pick(value); });
	});

	var starRow = modal.querySelector('.efb-review__stars');
	if (starRow) {
		starRow.addEventListener('mouseleave', function () { paint(rating); });
	}

	/* ------------------------------------------------------------------ */
	/* The claim                                                           */
	/* ------------------------------------------------------------------ */

	/** Walk the three check rows while the server is being asked. */
	function runChecking() {
		showStep('checking');
		showButtons([]);

		var rows = Array.prototype.slice.call(modal.querySelectorAll('[data-efb-review-checks] li'));

		var setRow = function (index, state) {
			var row = rows[index];
			if (!row) {
				return;
			}

			row.classList.toggle('is-active', state === 'active');
			row.classList.toggle('is-waiting', state === 'waiting');

			var icon = row.querySelector('i');
			if (icon) {
				icon.className = state === 'done'
					? 'bi bi-check2'
					: (state === 'active' ? 'bi bi-arrow-repeat' : 'bi bi-dot');
			}
		};

		rows.forEach(function (_row, i) { setRow(i, i === 0 ? 'active' : 'waiting'); });

		checkTimers.push(setTimeout(function () {
			setRow(0, 'done');
			setRow(1, 'active');
		}, 700));

		checkTimers.push(setTimeout(function () {
			setRow(1, 'done');
			setRow(2, 'active');
		}, 1400));
	}

	function showResult(outcome, email) {
		var meta = outcomes[outcome] || outcomes.server;
		if (!meta) {
			showError(text.failed || '');
			showStep('claim');
			return;
		}

		var step = steps.result;
		step.className = 'efb-dlg__body efb-review__step is-tone-' + (meta.tone || 'good');

		var icon = step.querySelector('[data-efb-review-result-icon]');
		var title = step.querySelector('[data-efb-review-result-title]');
		var lead = step.querySelector('[data-efb-review-result-lead]');
		var detail = step.querySelector('[data-efb-review-result-detail]');
		var detailIcon = step.querySelector('[data-efb-review-detail-icon]');
		var detailText = step.querySelector('[data-efb-review-detail-text]');

		if (icon) { icon.className = 'bi ' + meta.icon; }
		if (title) { title.textContent = meta.title || ''; }
		if (lead) { lead.textContent = meta.lead || ''; }

		if (detail && detailText) {
			// "Sent to you@example.com" only makes sense when it really was.
			var line = meta.detail || '';
			if (outcome === 'granted' && email) {
				line = email + ' · ' + line;
			}
			detailText.textContent = line;
			detail.hidden = !line;
			if (detailIcon) { detailIcon.className = 'bi ' + (meta.detailIcon || 'bi-info-circle'); }
		}

		showStep('result');
		showButtons(meta.actions || ['done']);
	}

	function claim() {
		var username = usernameInput ? usernameInput.value.trim() : '';
		var email = emailInput ? emailInput.value.trim() : '';

		if (usernameInput) { usernameInput.classList.toggle('is-invalid', !username); }
		if (!username) {
			showError(text.errUsername || '');
			usernameInput && usernameInput.focus();
			return;
		}

		var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
		if (emailInput) { emailInput.classList.toggle('is-invalid', !emailOk); }
		if (!emailOk) {
			showError(text.errEmail || '');
			emailInput && emailInput.focus();
			return;
		}

		runChecking();

		// A preview cycles the seven answers rather than asking the service.
		if (preview) {
			var at = previewCursor();
			var next = previewOrder[at % previewOrder.length];
			previewCursor(at + 1);
			checkTimers.push(setTimeout(function () { showResult(next, email); }, 2000));
			return;
		}

		var started = Date.now();

		post('claim', { username: username, email: email })
			.then(function (response) {
				var data = response && response.data ? response.data : {};

				// Let the three rows finish; an answer that lands in 200ms
				// otherwise flashes past before anyone can read it.
				var wait = Math.max(0, 1900 - (Date.now() - started));

				checkTimers.push(setTimeout(function () {
					if (!response || !response.success || !data.ok) {
						showStep('claim');
						showError(data.message || text.failed || '');
						return;
					}

					showResult(data.outcome, data.email || email);
				}, wait));
			})
			.catch(function () {
				checkTimers.push(setTimeout(function () {
					showResult('server', email);
				}, 600));
			});
	}

	/* ------------------------------------------------------------------ */
	/* The feedback                                                        */
	/* ------------------------------------------------------------------ */

	var chosenTopics = [];

	Array.prototype.forEach.call(modal.querySelectorAll('[data-efb-review-topic]'), function (chip) {
		chip.addEventListener('click', function () {
			var key = chip.getAttribute('data-efb-review-topic');
			var at = chosenTopics.indexOf(key);

			if (at > -1) {
				chosenTopics.splice(at, 1);
			} else {
				chosenTopics.push(key);
			}

			chip.classList.toggle('is-on', at === -1);
			chip.setAttribute('aria-pressed', at === -1 ? 'true' : 'false');
			showError('');
		});
	});

	function sendFeedback() {
		var comment = commentInput ? commentInput.value.trim() : '';

		if (!comment && !chosenTopics.length) {
			showError(text.errComment || '');
			commentInput && commentInput.focus();
			return;
		}

		if (preview) {
			showStep('sent');
			return;
		}

		busy(footButtons.send, true);
		showError('');

		post('feedback', {
			comment: comment,
			topics: chosenTopics,
			contact_ok: (contactInput && contactInput.checked) ? '1' : ''
		})
			.then(function (response) {
				var data = response && response.data ? response.data : {};
				busy(footButtons.send, false);

				if (!response || !response.success || !data.ok) {
					showError(data.message || text.failed || '');
					return;
				}

				// Even a report the service refused is not this person's
				// problem: they wrote it, and the conversation is over.
				showStep('sent');
			})
			.catch(function () {
				busy(footButtons.send, false);
				showError(text.failed || '');
			});
	}

	/* ------------------------------------------------------------------ */
	/* Footer and in-body actions                                          */
	/* ------------------------------------------------------------------ */

	function on(name, handler) {
		if (buttons[name]) {
			buttons[name].addEventListener('click', handler);
		}
	}

	on('later', function () {
		record('later');
		close();
	});

	on('never', function () {
		record('never');
		close();
	});

	on('done', close);

	on('toClaim', function () {
		showStep('claim');
	});

	on('getCode', claim);
	on('retry', claim);
	on('send', sendFeedback);

	on('edit', function () {
		showStep('claim');
	});

	on('feedback', function () {
		showStep('feedback');
	});

	on('back', function () {
		rating = 0;
		paint(0);
		showStep('ask');
	});

	// The review link opens WordPress.org in a new tab. Nothing else changes:
	// the claim step is reached through its own button, because somebody who
	// opened the page and wrote nothing has no review for us to find.
	on('review', function () {
		reviewOpened = true;
	});

	Array.prototype.forEach.call(modal.querySelectorAll('[data-efb-review-close]'), function (el) {
		el.addEventListener('click', function () {
			record('later');
			close();
		});
	});

	/* ------------------------------------------------------------------ */
	/* Go                                                                  */
	/* ------------------------------------------------------------------ */

	paint(0);
	showStep('ask');

	/*
	 * A moment's delay so the invitation does not race the page it appears on,
	 * then a wait for a clear screen. The server has already counted this ask,
	 * so giving up the moment another dialog happens to be open would spend
	 * somebody's turn on a dialog they never saw - it waits for them to finish
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
