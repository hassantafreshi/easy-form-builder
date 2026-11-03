/**
 * Easy Form Builder - Import from Contact Form 7.
 *
 * Loaded only on the Panel and Create screens, for administrators, when
 * Contact Form 7 forms exist (see class-Emsfb-cf7-importer.php). Draws a small
 * card into the screen the plugin renders, and runs the import dialog:
 * choose forms -> import -> per-form result. Talks to admin-ajax only
 * (efb_cf7_list, efb_cf7_import) with the plugin's wp_rest nonce.
 *
 * Self-contained: no dependency on the builder's globals.
 */
(function () {
	'use strict';

	var cfg = window.efb_cf7_import;
	if (!cfg || !cfg.text) {
		return;
	}
	var T = cfg.text;
	var state = {
		forms: [],
		busy: false,
		imported: false,
		lastFocus: null
	};

	/* ------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------- */

	function esc(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	/* "%s" in order, or "%1$s" by position. */
	function fmt(text) {
		var args = Array.prototype.slice.call(arguments, 1);
		var next = 0;
		return String(text).replace(/%(\d+)\$s|%s/g, function (m, pos) {
			var value = pos ? args[Number(pos) - 1] : args[next++];
			return value == null ? '' : value;
		});
	}

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', cfg.nonce);
		Object.keys(data || {}).forEach(function (key) {
			var value = data[key];
			if (Array.isArray(value)) {
				value.forEach(function (v) { body.append(key + '[]', v); });
			} else {
				body.append(key, value);
			}
		});
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json().catch(function () { return null; }).then(function (json) {
				if (!json || !json.success) {
					var message = json && json.data && json.data.message ? json.data.message : T.error;
					throw new Error(message);
				}
				return json.data;
			});
		});
	}

	function copyText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		return new Promise(function (resolve, reject) {
			var area = document.createElement('textarea');
			area.value = text;
			area.setAttribute('readonly', '');
			area.style.position = 'fixed';
			area.style.opacity = '0';
			document.body.appendChild(area);
			area.select();
			try {
				document.execCommand('copy') ? resolve() : reject(new Error('copy'));
			} catch (e) {
				reject(e);
			}
			document.body.removeChild(area);
		});
	}

	/* ------------------------------------------------------------------
	 * Entry points: a card on Create (and on Panel while forms wait)
	 * ---------------------------------------------------------------- */

	function cardElement(id) {
		var card = document.createElement('div');
		card.id = id;
		card.className = 'efb-cf7-card';
		card.setAttribute('role', 'region');
		card.setAttribute('aria-label', T.open);
		card.innerHTML =
			'<span class="efb-cf7-card__icon" aria-hidden="true"><i class="bi bi-box-arrow-in-down"></i></span>' +
			'<span class="efb-cf7-card__body">' +
				'<strong class="efb-cf7-card__title">' + esc(T.cardTitle) + '</strong>' +
				'<span class="efb-cf7-card__text">' + esc(cfg.screen === 'panel' && T.cardTextPending ? T.cardTextPending : T.cardText) + '</span>' +
			'</span>' +
			'<button type="button" class="efb-cf7-card__btn">' + esc(T.open) + '</button>';
		card.querySelector('button').addEventListener('click', openDialog);
		return card;
	}

	function placeEntryPoints() {
		if (cfg.screen === 'create') {
			// The template gallery of the Create screen.
			var gallery = document.getElementById('listFormCardsEFB');
			if (gallery && !document.getElementById('efb-cf7-card')) {
				var search = document.getElementById('findCardFormEFB');
				var card = cardElement('efb-cf7-card');
				if (search && search.parentElement && search.parentElement.parentElement) {
					search.parentElement.insertAdjacentElement('afterend', card);
				} else {
					gallery.parentElement.insertBefore(card, gallery);
				}
			}
			return;
		}

		if (Number(cfg.pending) < 1) {
			return;
		}
		// Panel, forms list.
		var list = document.getElementById('emsFormBuilder-list');
		if (list && !document.getElementById('efb-cf7-card')) {
			var holder = list.closest('.card') || list;
			holder.parentElement.insertBefore(cardElement('efb-cf7-card'), holder);
			return;
		}
		// Panel without any form yet: a button next to "Create forms".
		var actions = document.querySelector('#header-efb .efb-header-actions');
		if (!list && actions && !document.getElementById('efb-cf7-header-btn')) {
			var button = document.createElement('a');
			button.id = 'efb-cf7-header-btn';
			button.setAttribute('role', 'button');
			button.tabIndex = 0;
			// The same look as the "Tutorial" button next to it.
			button.className = 'efb btn efb btn-outline-pink efb-cta-btn efb-cf7-header-btn' + (window.innerWidth >= 768 ? ' btn-lg' : '');
			button.innerHTML = '<i class="efb bi-box-arrow-in-down mx-1" aria-hidden="true"></i>' + esc(T.open);
			button.addEventListener('click', openDialog);
			button.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					openDialog();
				}
			});
			actions.appendChild(button);
		}
	}

	var scheduled = false;
	function schedulePlace() {
		if (scheduled) {
			return;
		}
		scheduled = true;
		window.requestAnimationFrame(function () {
			scheduled = false;
			placeEntryPoints();
		});
	}

	function watch() {
		placeEntryPoints();
		// The plugin draws its screens with innerHTML after load and again on
		// every navigation; the card follows the screen it belongs to.
		new MutationObserver(schedulePlace).observe(document.body, { childList: true, subtree: true });
	}

	/* ------------------------------------------------------------------
	 * The dialog
	 * ---------------------------------------------------------------- */

	function dialog() {
		return document.getElementById('efb-cf7-dialog');
	}

	function openDialog() {
		if (dialog()) {
			return;
		}
		state.lastFocus = document.activeElement;
		state.imported = false;
		var root = document.createElement('div');
		root.id = 'efb-cf7-dialog';
		root.className = 'efb-dlg efb-cf7';
		root.setAttribute('role', 'dialog');
		root.setAttribute('aria-modal', 'true');
		root.setAttribute('aria-labelledby', 'efb-cf7-title');
		root.setAttribute('dir', Number(cfg.rtl) === 1 ? 'rtl' : 'ltr');
		root.innerHTML =
			'<div class="efb-dlg__backdrop" data-efb-cf7-close></div>' +
			'<div class="efb-dlg__shell efb-tone-brand" tabindex="-1">' +
				'<div class="efb-dlg__head">' +
					'<i class="bi bi-box-arrow-in-down efb-dlg__head-icon" aria-hidden="true"></i>' +
					'<h2 class="efb-dlg__title" id="efb-cf7-title">' + esc(T.open) + '</h2>' +
					'<button type="button" class="efb-dlg__close" data-efb-cf7-close aria-label="' + esc(T.close) + '"><i class="bi bi-x-lg" aria-hidden="true"></i></button>' +
				'</div>' +
				'<div class="efb-dlg__body efb-cf7__body" aria-live="polite"></div>' +
				'<div class="efb-dlg__foot efb-cf7__foot"></div>' +
			'</div>';
		document.body.appendChild(root);
		document.body.classList.add('efb-dlg-open');

		root.addEventListener('click', function (e) {
			if (e.target.closest('[data-efb-cf7-close]')) {
				closeDialog();
			}
		});
		root.addEventListener('keydown', onKey);
		root.querySelector('.efb-dlg__shell').focus();
		loadList();
	}

	function closeDialog() {
		var root = dialog();
		if (!root || state.busy) {
			return;
		}
		root.parentNode.removeChild(root);
		document.body.classList.remove('efb-dlg-open');
		if (state.imported && cfg.screen === 'panel') {
			// The forms list is drawn from the page data: show the new forms.
			window.location.reload();
			return;
		}
		if (state.lastFocus && typeof state.lastFocus.focus === 'function') {
			state.lastFocus.focus();
		}
	}

	function onKey(e) {
		if (e.key === 'Escape') {
			e.preventDefault();
			closeDialog();
			return;
		}
		if (e.key !== 'Tab') {
			return;
		}
		// Keep focus inside the dialog.
		var root = dialog();
		var nodes = Array.prototype.filter.call(
			root.querySelectorAll('button, [href], input, [tabindex]:not([tabindex="-1"])'),
			function (el) { return !el.disabled && el.offsetParent !== null; }
		);
		if (!nodes.length) {
			return;
		}
		var first = nodes[0];
		var last = nodes[nodes.length - 1];
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	function setBody(html) {
		var body = dialog().querySelector('.efb-cf7__body');
		body.innerHTML = html;
		body.scrollTop = 0;
		return body;
	}

	function setFoot(html) {
		var foot = dialog().querySelector('.efb-cf7__foot');
		foot.innerHTML = html;
		return foot;
	}

	function spinner(text) {
		return '<div class="efb-cf7__wait"><span class="efb-cf7__spin" aria-hidden="true"></span><span>' + esc(text) + '</span></div>';
	}

	function errorBox(message) {
		return '<div class="efb-dlg__note efb-dlg__note--warn efb-cf7__error" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span>' + esc(message) + '</span></div>';
	}

	/* Step 1: the list ------------------------------------------------- */

	function loadList() {
		setBody(spinner(T.loading));
		setFoot('<button type="button" class="efb-btn efb-btn--ghost" data-efb-cf7-close>' + esc(T.cancel) + '</button>');
		post('efb_cf7_list', {}).then(function (data) {
			if (!dialog()) {
				return;
			}
			state.forms = data && data.forms ? data.forms : [];
			renderList();
		}).catch(function (err) {
			if (dialog()) {
				setBody(errorBox(err.message || T.error));
			}
		});
	}

	function pageLabel(page) {
		var title = esc(page.title);
		return page.builder ? title + ' <span class="efb-cf7__muted">(' + esc(fmt(T.builderPage, page.builder)) + ')</span>' : title;
	}

	function renderList() {
		if (!state.forms.length) {
			setBody('<p class="efb-cf7__intro">' + esc(T.empty) + '</p>');
			return;
		}

		var rows = state.forms.map(function (form) {
			var done = form.imported && form.imported.length;
			var last = done ? form.imported[form.imported.length - 1] : null;
			var meta = esc(fmt(T.fields, form.fields)) + ' <span aria-hidden="true">·</span> <span class="efb-cf7__ltr">#' + esc(form.id) + '</span>';
			var pages = form.pages && form.pages.length
				? '<span class="efb-cf7__pages"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> ' + fmt(esc(T.shownOn), form.pages.map(pageLabel).join(', ')) + '</span>'
				: '';
			return '<li class="efb-cf7__item">' +
				'<label class="efb-cf7__row">' +
					'<input type="checkbox" class="efb-cf7__check" value="' + esc(form.id) + '"' + (done ? '' : ' checked') + '>' +
					'<span class="efb-cf7__main">' +
						'<span class="efb-cf7__name">' + esc(form.title) + '</span>' +
						'<span class="efb-cf7__meta">' + meta + '</span>' +
						(done ? '<span class="efb-cf7__badge"><i class="bi bi-check2-circle" aria-hidden="true"></i> ' + esc(fmt(T.imported, last.id)) + '</span>' +
							'<span class="efb-cf7__again" hidden>' + esc(T.importAgain) + '</span>' : '') +
						pages +
					'</span>' +
				'</label>' +
			'</li>';
		}).join('');

		var anyPages = state.forms.some(function (f) { return f.pages && f.pages.length; });
		var replace = anyPages
			? '<div class="efb-cf7__replace">' +
				'<label class="efb-cf7__switch">' +
					'<input type="checkbox" id="efb-cf7-replace">' +
					'<span>' + esc(T.replace) + '</span>' +
				'</label>' +
				'<ul class="efb-cf7__pagelist" id="efb-cf7-pagelist"></ul>' +
				'<p class="efb-cf7__help">' + esc(T.replaceHelp) + '</p>' +
			'</div>'
			: '';

		var body = setBody(
			'<p class="efb-cf7__intro">' + esc(T.intro) + '</p>' +
			'<ul class="efb-cf7__list">' + rows + '</ul>' +
			replace +
			'<div class="efb-cf7__status" id="efb-cf7-status"></div>'
		);

		var foot = setFoot(
			'<button type="button" class="efb-btn efb-btn--ghost" data-efb-cf7-close>' + esc(T.cancel) + '</button>' +
			'<button type="button" class="efb-btn efb-btn--primary" id="efb-cf7-go"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i><span></span></button>'
		);

		body.addEventListener('change', function (e) {
			if (e.target.classList.contains('efb-cf7__check')) {
				var again = e.target.closest('.efb-cf7__row').querySelector('.efb-cf7__again');
				if (again) {
					again.hidden = !e.target.checked;
				}
			}
			syncList();
		});
		foot.querySelector('#efb-cf7-go').addEventListener('click', runImport);
		syncList();
	}

	function selectedIds() {
		return Array.prototype.map.call(
			dialog().querySelectorAll('.efb-cf7__check:checked'),
			function (el) { return Number(el.value); }
		);
	}

	/* Button label and the pages the switch would change, for the current selection. */
	function syncList() {
		var ids = selectedIds();
		var go = document.getElementById('efb-cf7-go');
		if (go) {
			go.querySelector('span').textContent = T.import + (ids.length ? ' (' + ids.length + ')' : '');
			go.disabled = ids.length === 0;
		}
		var list = document.getElementById('efb-cf7-pagelist');
		if (!list) {
			return;
		}
		var seen = {};
		var items = [];
		state.forms.forEach(function (form) {
			if (ids.indexOf(Number(form.id)) === -1) {
				return;
			}
			(form.pages || []).forEach(function (page) {
				if (!seen[page.id]) {
					seen[page.id] = true;
					items.push('<li>' + pageLabel(page) + '</li>');
				}
			});
		});
		list.innerHTML = items.join('');
		var wrap = list.closest('.efb-cf7__replace');
		wrap.hidden = items.length === 0;
	}

	/* Step 2: import ---------------------------------------------------- */

	function runImport() {
		var ids = selectedIds();
		var status = document.getElementById('efb-cf7-status');
		if (!ids.length) {
			if (status) {
				status.innerHTML = errorBox(T.chooseOne);
			}
			return;
		}
		var replaceBox = document.getElementById('efb-cf7-replace');
		var replaceWrap = replaceBox ? replaceBox.closest('.efb-cf7__replace') : null;
		var replace = replaceBox && replaceBox.checked && replaceWrap && !replaceWrap.hidden ? '1' : '0';

		state.busy = true;
		dialog().classList.add('is-busy');
		setBody(spinner(T.working));
		setFoot('');

		post('efb_cf7_import', { ids: ids, replace: replace }).then(function (data) {
			state.busy = false;
			state.imported = (data.results || []).some(function (r) { return r.ok; });
			if (dialog()) {
				dialog().classList.remove('is-busy');
				renderResults(data.results || []);
			}
		}).catch(function (err) {
			state.busy = false;
			if (dialog()) {
				dialog().classList.remove('is-busy');
				setBody(errorBox(err.message || T.error));
				setFoot('<button type="button" class="efb-btn efb-btn--ghost" data-efb-cf7-close>' + esc(T.close) + '</button>');
			}
		});
	}

	/* Step 3: results --------------------------------------------------- */

	function list(items, cls) {
		return '<ul class="efb-cf7__lines ' + (cls || '') + '">' + items.join('') + '</ul>';
	}

	function resultHtml(r) {
		if (!r.ok) {
			return '<section class="efb-cf7__result is-failed">' +
				'<div class="efb-cf7__rhead"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><strong>' + esc(r.title) + '</strong></div>' +
				'<p class="efb-cf7__muted">' + esc(r.message || T.failed) + '</p>' +
			'</section>';
		}

		var fields = (r.fields || []).map(function (f) {
			return '<li><span class="efb-cf7__flabel">' + esc(f.label) + '</span>' +
				'<span class="efb-cf7__ftype">' + esc(f.typeLabel) + (f.required ? ' · ' + esc(T.required) : '') + '</span></li>';
		});
		var settings = (r.settings || []).map(function (s) { return '<li>' + esc(s) + '</li>'; });
		var notes = (r.notes || []).map(function (n) { return '<li>' + esc(n) + '</li>'; });

		var pagesOk = [];
		var pagesNo = [];
		(r.pages || []).forEach(function (p) {
			if (p.ok) {
				pagesOk.push('<li><span>' + esc(p.title) + '</span> ' +
					(p.view ? '<a href="' + esc(p.view) + '" target="_blank" rel="noopener">' + esc(T.view) + '</a>' : '') +
					(p.undo ? ' <span aria-hidden="true">·</span> <a href="' + esc(p.undo) + '" target="_blank" rel="noopener">' + esc(T.undo) + '</a>' : '') +
				'</li>');
			} else {
				pagesNo.push('<li><span>' + esc(p.title) + '</span> <span class="efb-cf7__muted">(' + esc(p.reason) + ')</span></li>');
			}
		});

		return '<section class="efb-cf7__result">' +
			'<div class="efb-cf7__rhead">' +
				'<i class="bi bi-check2-circle" aria-hidden="true"></i>' +
				'<strong>' + esc(r.title) + '</strong>' +
				'<span class="efb-cf7__newform">' + esc(fmt(T.newForm, r.efb)) + '</span>' +
			'</div>' +
			'<div class="efb-dlg__code-panel efb-cf7__code">' +
				'<div class="efb-dlg__code-panel-row">' +
					'<code class="efb-dlg__code-box">' + esc(r.shortcode) + '</code>' +
					'<button type="button" class="efb-btn efb-btn--ghost efb-cf7__copy" data-code="' + esc(r.shortcode) + '"><i class="bi bi-clipboard" aria-hidden="true"></i><span>' + esc(T.copyShortcode) + '</span></button>' +
					'<a class="efb-btn efb-btn--primary efb-cf7__edit" href="' + esc(cfg.editUrl + r.efb) + '"><i class="bi bi-pencil" aria-hidden="true"></i><span>' + esc(T.edit) + '</span></a>' +
				'</div>' +
			'</div>' +
			(pagesOk.length ? '<h3 class="efb-cf7__h">' + esc(T.pagesDone) + '</h3>' + list(pagesOk, 'is-pages') : '') +
			(pagesNo.length ? '<h3 class="efb-cf7__h">' + esc(T.pagesNotDone) + '</h3>' + list(pagesNo, 'is-pages') : '') +
			'<details class="efb-cf7__details"' + (fields.length <= 8 ? ' open' : '') + '>' +
				'<summary>' + esc(T.carried) + ' <span class="efb-cf7__count">' + fields.length + '</span></summary>' +
				list(fields, 'is-fields') +
			'</details>' +
			(settings.length ? '<h3 class="efb-cf7__h">' + esc(T.settings) + '</h3>' + list(settings, 'is-settings') : '') +
			'<h3 class="efb-cf7__h">' + esc(T.notCarried) + '</h3>' +
			(notes.length ? list(notes, 'is-notes') : '<p class="efb-cf7__ok">' + esc(T.allCarried) + '</p>') +
		'</section>';
	}

	function renderResults(results) {
		dialog().querySelector('#efb-cf7-title').textContent = T.doneTitle;
		var body = setBody(results.map(resultHtml).join(''));
		setFoot('<button type="button" class="efb-btn efb-btn--primary" data-efb-cf7-close>' + esc(T.close) + '</button>');

		body.addEventListener('click', function (e) {
			var button = e.target.closest('.efb-cf7__copy');
			if (!button) {
				return;
			}
			var label = button.querySelector('span');
			copyText(button.getAttribute('data-code')).then(function () {
				label.textContent = T.copied;
				button.classList.add('is-copied');
				setTimeout(function () {
					label.textContent = T.copyShortcode;
					button.classList.remove('is-copied');
				}, 1800);
			}).catch(function () {
				var code = button.parentElement.querySelector('code');
				if (code && window.getSelection) {
					var range = document.createRange();
					range.selectNodeContents(code);
					window.getSelection().removeAllRanges();
					window.getSelection().addRange(range);
				}
			});
		});
		var shell = dialog().querySelector('.efb-dlg__shell');
		if (shell) {
			shell.focus();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', watch);
	} else {
		watch();
	}
}());
