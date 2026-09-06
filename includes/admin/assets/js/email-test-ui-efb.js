/**
 * The email server test, drawn once.
 *
 * The test runs in two places - the modal behind clickToCheckEmailServer() on
 * the settings screen, and the inline panel in the setup wizard a site sees
 * just after activation - and used to be drawn by two unrelated pieces of code
 * that had drifted into two different designs. This module is the only one that
 * draws it now; both callers hand it the same view model.
 *
 * It is deliberately presentation only. Deciding *what* the test found - the
 * verdict, the guidance, which score counts as too low - stays with the callers
 * that own that domain logic. This file turns a decision into markup and
 * nothing else, which is why it needs no plugin globals and can be rendered
 * from a test with a hand-written view model.
 *
 * The view model:
 *
 *   {
 *     phase:   'run' | 'done' | 'warn' | 'fail',
 *     percent: 0-100,
 *     steps:   { start, send, wait, quick, full }   each: done|active|warning|error|waiting
 *     hero:    { icon, title, sub, score, grade },
 *     message: '',
 *     smtp:    { title, desc, cta, url },
 *     upgrade: { code, label, url },
 *     ok:      { text, email },
 *     spam:    { title, desc, email },
 *     rows:    [ { label, value, badge: 'ok'|'bad', mono: true } ],
 *     groups:  [ { title, items: [ '' ] } ],
 *     tips:    [ '' ],
 *     strings: { ... },
 *     inline:  false,
 *     activeTab: 'delivery'
 *   }
 *
 * `phase` picks the tone, so a caller never names a colour.
 */

(function (root) {
	'use strict';

	var STEP_KEYS = ['start', 'send', 'wait', 'quick', 'full'];

	var STEP_ICON = {
		done: 'bi-check-lg',
		active: 'bi-arrow-repeat',
		warning: 'bi-exclamation-lg',
		error: 'bi-x-lg',
		waiting: 'bi-dot'
	};

	// The tone is a consequence of the phase, never a separate decision.
	var PHASE_TONE = { run: 'blue', done: 'green', warn: 'amber', fail: 'red' };

	var DEFAULT_STRINGS = {
		title: 'Email Server',
		phRunning: 'Running',
		phDone: 'Finished',
		phWarn: 'Needs attention',
		phFailed: 'Failed',
		stepPrepare: 'Prepare Test',
		stepSend: 'Send Test Email',
		stepWait: 'Waiting for Delivery',
		stepQuick: 'Quick Result',
		stepFull: 'Full Report',
		stepPrepareDesc: 'Connecting to WhiteStudio to generate a unique test email address.',
		stepSendDesc: 'WordPress is sending a real email to verify your server can deliver mail.',
		stepWaitDesc: 'Checking whether the test email arrived at our server (usually takes a few seconds).',
		stepQuickDesc: 'Showing the first delivery result - you will see right away if email is working.',
		stepFullDesc: 'A detailed HTML report with full diagnostics is being prepared and emailed to you.',
		stepsDone: '%s of 5 steps done',
		stepsRunning: 'Step %s of 5',
		tabDelivery: 'Delivery details',
		tabDiagnosis: 'Diagnosis',
		tabTips: 'Recommendations',
		scoreOutOf: '/ 100'
	};

	function escapeHtml(value) {
		return String(value === null || value === undefined ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	/** Only http(s) survives, so a value from the server cannot become javascript:. */
	function safeUrl(value) {
		var url = String(value === null || value === undefined ? '' : value).trim();

		return /^https?:\/\//i.test(url) ? escapeHtml(url) : '';
	}

	function format(template, value) {
		return String(template === null || template === undefined ? '' : template)
			.replace('%s', value);
	}

	/**
	 * Digits in the reader's own numerals.
	 *
	 * A Persian panel that says "Step 3 of 5" in Latin digits beside Persian
	 * words reads as a half-finished translation, so the numbers this module
	 * writes follow the page.
	 */
	function localeDigits(value, rtl) {
		if (!rtl) {
			return String(value);
		}

		return String(value).replace(/[0-9]/g, function (d) {
			return '۰۱۲۳۴۵۶۷۸۹'[Number(d)];
		});
	}

	function isRtl() {
		if (typeof document === 'undefined') {
			return false;
		}

		var dir = document.documentElement && document.documentElement.getAttribute('dir');

		return dir === 'rtl' || (document.body && document.body.classList.contains('rtl'));
	}

	function renderSteps(view, s, rtl) {
		var steps = view.steps || {};
		var meta = [
			[s.stepPrepare, s.stepPrepareDesc],
			[s.stepSend, s.stepSendDesc],
			[s.stepWait, s.stepWaitDesc],
			[s.stepQuick, s.stepQuickDesc],
			[s.stepFull, s.stepFullDesc]
		];

		var states = STEP_KEYS.map(function (key) {
			return steps[key] || 'waiting';
		});

		var reached = function (index) {
			return index >= 0 && index < states.length && states[index] !== 'waiting';
		};

		var cells = states.map(function (state, i) {
			var before = i === 0
				? ''
				: '<span class="efb-est__seg efb-est__seg--before' + (reached(i) ? ' is-on' : '') + '"></span>';
			var after = i === STEP_KEYS.length - 1
				? ''
				: '<span class="efb-est__seg efb-est__seg--after' + (reached(i + 1) ? ' is-on' : '') + '"></span>';

			return '<div class="efb-est__step is-' + state + '">' +
				before + after +
				'<span class="efb-est__dot"><i class="bi ' + STEP_ICON[state] + '" aria-hidden="true"></i></span>' +
				'<span class="efb-est__step-label" dir="auto">' + escapeHtml(meta[i][0]) + '</span>' +
				'</div>';
		}).join('');

		var doneCount = states.filter(function (x) { return x === 'done'; }).length;
		var activeIndex = states.indexOf('active');
		var summary = activeIndex >= 0
			? format(s.stepsRunning, localeDigits(activeIndex + 1, rtl))
			: format(s.stepsDone, localeDigits(doneCount, rtl));

		// Describe whatever the eye lands on: the running step, else the step
		// that failed, else the last one finished.
		var focus = activeIndex;
		if (focus < 0) {
			focus = states.lastIndexOf('error');
		}
		if (focus < 0) {
			focus = states.lastIndexOf('warning');
		}
		if (focus < 0) {
			focus = Math.max(0, doneCount - 1);
		}

		return '<div class="efb-est__steps">' +
			'<div class="efb-est__rail" role="list">' + cells + '</div>' +
			'<div class="efb-est__rail-note">' +
			'<span class="efb-est__step-badge" dir="auto">' + escapeHtml(summary) + '</span>' +
			'<span class="efb-est__step-desc" dir="auto">' + escapeHtml(meta[focus][1]) + '</span>' +
			'</div></div>';
	}

	function renderHero(view, s, rtl, running) {
		var hero = view.hero || {};
		var hasScore = hero.score !== undefined && hero.score !== null && hero.score !== '';
		var figure;

		if (hasScore) {
			var score = Math.max(0, Math.min(100, Number(hero.score) || 0));
			// The sweep is the datum, so it is the one inline style here.
			figure = '<div class="efb-est__gauge" style="background:conic-gradient(var(--efb-est-tone) ' +
				(score * 3.6) + 'deg, #e7e9f4 0deg);" role="img" aria-label="' +
				escapeHtml(score + ' / 100') + '">' +
				'<span class="efb-est__gauge-inner">' +
				'<span class="efb-est__score">' + escapeHtml(localeDigits(score, rtl)) + '</span>' +
				'<span class="efb-est__score-max">' + escapeHtml(s.scoreOutOf) + '</span>' +
				'</span></div>';
		} else {
			figure = '<div class="efb-est__hero-icon"><i class="bi ' +
				escapeHtml(hero.icon || 'bi-envelope-check') + '" aria-hidden="true"></i></div>';
		}

		var grade = hero.grade
			? '<span class="efb-est__grade">' + escapeHtml(hero.grade) + '</span>'
			: '';

		var percent = Math.max(0, Math.min(100, Number(view.percent) || 0));
		var percentLabel = localeDigits(percent, rtl) + (rtl ? '٪' : '%');

		return '<div class="efb-est__hero">' +
			'<div class="efb-est__hero-figure">' + figure + '</div>' +
			'<div class="efb-est__hero-main">' +
			'<div class="efb-est__hero-titles">' +
			'<span class="efb-est__hero-title" dir="auto">' + escapeHtml(hero.title || '') + '</span>' + grade +
			'</div>' +
			(hero.sub ? '<div class="efb-est__hero-sub" dir="auto">' + escapeHtml(hero.sub) + '</div>' : '') +
			'<div class="efb-est__progress">' +
			'<div class="efb-est__track" role="progressbar" aria-valuenow="' + percent +
			'" aria-valuemin="0" aria-valuemax="100">' +
			'<div class="efb-est__bar" style="width:' + percent + '%;"></div></div>' +
			'<span class="efb-est__percent">' + escapeHtml(percentLabel) + '</span>' +
			'</div></div></div>';
	}

	function renderBlocks(view) {
		var out = '';

		if (view.message) {
			out += '<div class="efb-est__note"><i class="bi bi-arrow-right-circle-fill" aria-hidden="true"></i>' +
				'<span>' + escapeHtml(view.message) + '</span></div>';
		}

		if (view.smtp && view.smtp.title) {
			var smtpUrl = safeUrl(view.smtp.url);
			out += '<div class="efb-est__panel"><div class="efb-est__panel-row">' +
				'<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>' +
				'<div class="efb-est__panel-body">' +
				'<div class="efb-est__panel-title" dir="auto">' + escapeHtml(view.smtp.title) + '</div>' +
				'<div class="efb-est__panel-desc" dir="auto">' + escapeHtml(view.smtp.desc || '') + '</div>' +
				(smtpUrl && view.smtp.cta
					? '<a class="efb-est__panel-cta" href="' + smtpUrl + '" target="_blank" rel="noopener noreferrer">' +
					'<i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>' + escapeHtml(view.smtp.cta) + '</a>'
					: '') +
				'</div></div></div>';
		}

		if (view.upgrade && view.upgrade.code) {
			var upUrl = safeUrl(view.upgrade.url);
			out += '<div class="efb-est__upgrade">' +
				'<i class="bi bi-lightning-charge-fill" aria-hidden="true"></i>' +
				'<span class="efb-est__upgrade-code" dir="auto">' + escapeHtml(view.upgrade.code) + '</span>' +
				(upUrl
					? '<a class="efb-est__upgrade-cta" href="' + upUrl + '" target="_blank" rel="noopener noreferrer">' +
					escapeHtml(view.upgrade.label || '') + '</a>'
					: '') +
				'</div>';
		}

		if (view.ok && view.ok.text) {
			out += '<div class="efb-est__ok"><i class="bi bi-envelope-check-fill" aria-hidden="true"></i>' +
				'<span>' + escapeHtml(view.ok.text) +
				(view.ok.email ? '<b class="efb-est__mail">' + escapeHtml(view.ok.email) + '</b>' : '') +
				'</span></div>';
		}

		if (view.spam && view.spam.title) {
			out += '<div class="efb-est__spam"><i class="bi bi-envelope-paper" aria-hidden="true"></i>' +
				'<div><div class="efb-est__spam-title" dir="auto">' + escapeHtml(view.spam.title) + '</div>' +
				'<div class="efb-est__spam-desc" dir="auto">' + escapeHtml(view.spam.desc || '') +
				(view.spam.email ? '<b class="efb-est__mail">' + escapeHtml(view.spam.email) + '</b>' : '') +
				'</div></div></div>';
		}

		return out;
	}

	function renderDetails(view, s) {
		var rows = Array.isArray(view.rows) ? view.rows : [];
		var groups = Array.isArray(view.groups) ? view.groups : [];
		var tips = Array.isArray(view.tips) ? view.tips : [];

		var tabs = [];
		if (rows.length) {
			tabs.push({ id: 'delivery', label: s.tabDelivery, icon: 'bi-envelope-paper' });
		}
		if (groups.length) {
			tabs.push({ id: 'diagnosis', label: s.tabDiagnosis, icon: 'bi-tools' });
		}
		if (tips.length) {
			tabs.push({ id: 'tips', label: s.tabTips, icon: 'bi-lightbulb' });
		}

		if (!tabs.length) {
			return '';
		}

		var active = tabs.some(function (t) { return t.id === view.activeTab; })
			? view.activeTab
			: tabs[0].id;

		var head = tabs.map(function (t) {
			return '<button type="button" class="efb-est__tab' + (t.id === active ? ' is-active' : '') +
				'" data-efb-est-tab="' + t.id + '" role="tab" aria-selected="' + (t.id === active) + '">' +
				'<i class="bi ' + t.icon + '" aria-hidden="true"></i>' + escapeHtml(t.label) + '</button>';
		}).join('');

		var panes = '';

		if (rows.length) {
			panes += '<div class="efb-est__pane efb-est__pane--rows" data-efb-est-pane="delivery"' +
				(active === 'delivery' ? '' : ' hidden') + '>' +
				rows.map(function (r) {
					var value = r.badge
						? '<span class="efb-est__badge efb-est__badge--' + (r.badge === 'ok' ? 'ok' : 'bad') + '">' +
						escapeHtml(r.value) + '</span>'
						: '<span class="efb-est__row-value' + (r.mono ? ' efb-est__row-value--mono' : '') + '">' +
						escapeHtml(r.value) + '</span>';

					return '<div class="efb-est__row"><span class="efb-est__row-label" dir="auto">' +
						escapeHtml(r.label) + '</span>' + value + '</div>';
				}).join('') +
				'</div>';
		}

		if (groups.length) {
			panes += '<div class="efb-est__pane efb-est__pane--groups" data-efb-est-pane="diagnosis"' +
				(active === 'diagnosis' ? '' : ' hidden') + '>' +
				groups.map(function (g) {
					return '<div><div class="efb-est__group-title" dir="auto">' + escapeHtml(g.title) + '</div>' +
						'<div class="efb-est__group-items">' +
						(g.items || []).map(function (item) {
							return '<div class="efb-est__item"><i class="bi bi-chevron-right" aria-hidden="true"></i>' +
								'<span>' + escapeHtml(item) + '</span></div>';
						}).join('') +
						'</div></div>';
				}).join('') +
				'</div>';
		}

		if (tips.length) {
			panes += '<div class="efb-est__pane efb-est__pane--tips" data-efb-est-pane="tips"' +
				(active === 'tips' ? '' : ' hidden') + '>' +
				tips.map(function (tip, i) {
					return '<div class="efb-est__tip"><span class="efb-est__tip-n">' + (i + 1) + '</span>' +
						'<span>' + escapeHtml(tip) + '</span></div>';
				}).join('') +
				'</div>';
		}

		return '<div class="efb-est__details">' +
			'<div class="efb-est__tabs" role="tablist">' + head + '</div>' + panes + '</div>';
	}

	/**
	 * Draw the whole thing.
	 *
	 * @param {Object} view The view model described at the top of this file.
	 * @return {string} HTML for the body of the test, head and foot excluded -
	 *                  the modal supplies those, the wizard does not want them.
	 */
	function render(view) {
		view = view || {};

		var s = Object.assign({}, DEFAULT_STRINGS, view.strings || {});
		var phase = PHASE_TONE[view.phase] ? view.phase : 'run';
		var running = phase === 'run';
		var rtl = view.rtl === undefined ? isRtl() : !!view.rtl;

		var classes = ['efb-est', 'efb-est--' + PHASE_TONE[phase]];
		if (running) {
			classes.push('efb-est--running');
		}
		if (view.inline) {
			classes.push('efb-est--inline');
		}

		return '<div class="' + classes.join(' ') + '" data-efb-est>' +
			renderHero(view, s, rtl, running) +
			renderSteps(view, s, rtl) +
			renderBlocks(view) +
			renderDetails(view, s) +
			'</div>';
	}

	/** The phase chip that belongs in the modal's head bar. */
	function phaseChip(view) {
		var s = Object.assign({}, DEFAULT_STRINGS, (view && view.strings) || {});
		var phase = PHASE_TONE[view && view.phase] ? view.phase : 'run';
		var label = { run: s.phRunning, done: s.phDone, warn: s.phWarn, fail: s.phFailed }[phase];

		return '<span class="efb-est efb-est--' + PHASE_TONE[phase] + ' efb-est__phase">' +
			escapeHtml(label) + '</span>';
	}

	/**
	 * Make the tab strip work inside a container.
	 *
	 * Delegated from the container rather than bound per button, so a re-render
	 * mid-poll - which replaces every one of those buttons - does not quietly
	 * leave the tabs dead.
	 */
	function bindTabs(container) {
		if (!container || container.dataset.efbEstTabsBound === '1') {
			return;
		}

		container.dataset.efbEstTabsBound = '1';

		container.addEventListener('click', function (event) {
			var tab = event.target.closest ? event.target.closest('[data-efb-est-tab]') : null;
			if (!tab || !container.contains(tab)) {
				return;
			}

			var root = tab.closest('[data-efb-est]');
			if (!root) {
				return;
			}

			var wanted = tab.getAttribute('data-efb-est-tab');

			Array.prototype.forEach.call(root.querySelectorAll('[data-efb-est-tab]'), function (el) {
				var on = el.getAttribute('data-efb-est-tab') === wanted;
				el.classList.toggle('is-active', on);
				el.setAttribute('aria-selected', on ? 'true' : 'false');
			});

			Array.prototype.forEach.call(root.querySelectorAll('[data-efb-est-pane]'), function (el) {
				el.hidden = el.getAttribute('data-efb-est-pane') !== wanted;
			});
		});
	}

	/** Which tab is open right now, so a re-render can put it back. */
	function activeTab(container) {
		if (!container) {
			return '';
		}

		var open = container.querySelector('[data-efb-est-tab].is-active');

		return open ? open.getAttribute('data-efb-est-tab') : '';
	}

	root.efbEmailTestUI = {
		render: render,
		phaseChip: phaseChip,
		bindTabs: bindTabs,
		activeTab: activeTab,
		escapeHtml: escapeHtml,
		localeDigits: localeDigits,
		STEP_KEYS: STEP_KEYS
	};
})(typeof window !== 'undefined' ? window : this);
