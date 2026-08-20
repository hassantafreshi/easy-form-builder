/* Shared MediaRecorder engine for audio_recorder / video_recorder / screen_recorder fields.
 * Loaded on public forms, the admin builder canvas and the admin "test"/preview alike, so the
 * exact same record/stop/redo/play behaviour is available everywhere these fields are rendered.
 *
 * DOM id convention (per field id `X`):
 *   X_       -> outer shell (the element generic settings like border/height/corner target,
 *               same convention every other field type already uses for its main element)
 *   X_file   -> the real (visually hidden) <input type="file"> that the existing file-upload
 *               pipeline (valid_file_emsFormBuilder / fun_upload_file_api_emsFormBuilder) drives
 *   X-frame, X-preview, X-meter, X-watermark, X-idle, X-timer, X-controls, X-status, X-progress
 *            -> internal widget parts
 */

var EFB_REC_STATE = {};

function efbRecText(key, fallback) {
	if (typeof ajax_object_efm !== 'undefined' && ajax_object_efm && ajax_object_efm.text && ajax_object_efm.text[key]) return ajax_object_efm.text[key];
	if (typeof efb_var !== 'undefined' && efb_var && efb_var.text && efb_var.text[key]) return efb_var.text[key];
	return fallback;
}

function efbRecAlert(msg) {
	if (typeof alert_message_efb === 'function') {
		alert_message_efb('', msg, 12, 'danger');
	} else {
		window.alert(msg);
	}
}

/* Builds the recorder widget markup client-side (builder canvas, admin "test"/preview, and
 * the simplified admin live-render switch). The public-facing form is rendered server-side by
 * Formbuilder::ui_recorder_efb() in PHP, which mirrors this exact structure/ids. */
function efbRecorderWidgetHtml(rndm, vj, formId) {
	var kind = vj.type;
	var iconMap = { audio_recorder: 'bi-mic', video_recorder: 'bi-camera-video', screen_recorder: 'bi-display' };
	var acceptMap = { audio_recorder: 'audio/*', video_recorder: 'video/*', screen_recorder: 'video/*' };
	var classMap = { audio_recorder: 'efb-recorder-audio', video_recorder: 'efb-recorder-video-kind', screen_recorder: 'efb-recorder-screen-kind' };
	var quality = vj.record_quality || (kind === 'audio_recorder' ? 'standard' : '720p');
	var duration = vj.max_duration || 90;
	var maxSize = Number(vj.max_fsize) > 0 ? Number(vj.max_fsize) : 20;
	var required = vj.required == 1 || vj.required == true;
	var requiredClass = required ? 'required' : '';
	var requiredAttr = required ? 'required' : '';
	var domain = (typeof window !== 'undefined' && window.location) ? window.location.hostname : '';
	// UX extras (see docs/recorder-fields.md §2); ui_recorder_efb (PHP) mirrors these
	var countdown = vj.hasOwnProperty('rec_countdown') ? Number(vj.rec_countdown) || 0 : 3;
	var download = vj.hasOwnProperty('rec_download') ? (Number(vj.rec_download) ? 1 : 0) : 1;
	var noise = vj.hasOwnProperty('rec_noise') ? (Number(vj.rec_noise) ? 1 : 0) : 1;
	var facing = vj.rec_facing === 'environment' ? 'environment' : 'user';
	var mirror = vj.hasOwnProperty('rec_mirror') ? (Number(vj.rec_mirror) ? 1 : 0) : 1;
	var watermark = vj.hasOwnProperty('rec_watermark') ? (Number(vj.rec_watermark) ? 1 : 0) : 1;
	// Generic style settings live on the shell (the settings handlers swap these class
	// tokens in place, so the defaults must be present in the markup). recorder-efb.css
	// translates them onto the inner frame.
	var elHeight = vj.el_height || 'h-d-efb';
	var corner = vj.corner || 'efb-square';
	var borderColor = vj.el_border_color || 'border-d';
	var extraClasses = (vj.classes || '').split(',').join(' ').trim();

	// Watermark stays in the markup (class-hidden when off) so the builder's
	// live toggle works without a canvas re-render.
	var mediaPreview = kind === 'audio_recorder'
		? '<canvas class="efb efb-recorder-meter d-none" id="' + rndm + '-meter" width="300" height="64"></canvas>'
		: '<video class="efb efb-recorder-video d-none" id="' + rndm + '-preview" playsinline muted></video>' +
			'<div class="efb efb-recorder-watermark ' + (watermark ? '' : 'd-none') + '" id="' + rndm + '-watermark"><span>' + efbRecText('recWatermark', 'Made by Easy Form Builder') + '</span><span class="efb efb-recorder-domain">' + domain + '</span></div>';

	return '' +
		'<div class="efb efb-recorder-shell ' + classMap[kind] + ' ' + elHeight + ' ' + corner + ' ' + borderColor + ' efb1 ' + extraClasses + '" data-css="' + rndm + '" id="' + rndm + '_" data-id="' + rndm + '" data-kind="' + kind + '" data-quality="' + quality + '" data-duration="' + duration + '" data-max-size="' + maxSize + '" data-countdown="' + countdown + '" data-download="' + download + '" data-noise="' + noise + '" data-facing="' + facing + '" data-mirror="' + mirror + '" data-formid="' + (formId || 0) + '" data-state="idle">' +
			'<div class="efb efb-recorder-frame" id="' + rndm + '-frame">' +
				mediaPreview +
				'<div class="efb efb-recorder-idle-hint" id="' + rndm + '-idle"><i class="efb bi ' + iconMap[kind] + '"></i><span>' + efbRecText('recTapToStart', 'Tap to start recording') + '</span></div>' +
				'<div class="efb efb-recorder-timer d-none" id="' + rndm + '-timer">00:00</div>' +
				'<div class="efb efb-recorder-action-row" id="' + rndm + '-controls">' +
					'<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="pause" data-id="' + rndm + '" title="' + efbRecText('recPause', 'Pause') + '"><i class="efb bi-pause-fill"></i></button>' +
					'<button type="button" class="efb efb-recorder-primary-btn" data-action="start" data-id="' + rndm + '" data-start-icon="' + iconMap[kind] + '" data-stop-icon="bi-stop-fill" title="' + efbRecText('recStart', 'Start Recording') + '"><i class="efb bi ' + iconMap[kind] + '"></i></button>' +
					'<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="resume" data-id="' + rndm + '" title="' + efbRecText('recResume', 'Resume') + '"><i class="efb bi-record-circle"></i></button>' +
					'<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="redo" data-id="' + rndm + '" title="' + efbRecText('recRedo', 'Re-record') + '"><i class="efb bi-arrow-counterclockwise"></i></button>' +
					'<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="play" data-id="' + rndm + '" title="' + efbRecText('recPlay', 'Play') + '"><i class="efb bi-play-fill"></i></button>' +
					'<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="download" data-id="' + rndm + '" title="' + efbRecText('recDownload', 'Download recording') + '"><i class="efb bi-download"></i></button>' +
					'<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="upload" data-id="' + rndm + '" id="' + rndm + '-upload" title="' + efbRecText('recUpload', 'Upload') + '" aria-label="' + efbRecText('recUpload', 'Upload') + '"><i class="efb bi-cloud-arrow-up-fill" aria-hidden="true"></i></button>' +
				'</div>' +
				'<div class="efb efb-recorder-progress-track"><div class="efb efb-recorder-progress-bar" id="' + rndm + '-progress"></div></div>' +
			'</div>' +
			'<div class="efb efb-recorder-status-row">' +
				'<span class="efb efb-recorder-status" id="' + rndm + '-status"><span class="efb efb-recorder-status-dot"></span>' + efbRecText('recReady', 'Ready to record') + '</span>' +
				'<span class="efb efb-recorder-badge"><i class="efb bi ' + iconMap[kind] + '" aria-hidden="true"></i><span>' + efbRecText(kind, kind) + '</span></span>' +
			'</div>' +
			'<input type="file" hidden accept="' + acceptMap[kind] + '" data-type="' + kind + '" data-vid="' + rndm + '" data-id="' + rndm + '" class="efb emsFormBuilder_v ' + kind + ' ' + requiredClass + '" id="' + rndm + '_file" data-formid="' + (formId || 0) + '" onchange="valid_file_emsFormBuilder(\'' + rndm + '\',\'msg\',\'\',' + (formId || 0) + ')" ' + requiredAttr + '>' +
		'</div>';
}

/* ---- Capability detection (host & browser awareness) ----------------------
 * Classifies why recording cannot work here so the user sees the REASON, not
 * a dead button: 'https' (page not on a secure origin - hosts without SSL),
 * 'screen' (getDisplayMedia missing - practically every mobile browser),
 * 'nosupport' (no MediaRecorder/getUserMedia - old browsers, some webviews). */
function efbRecSupportInfo(kind) {
	if (typeof window !== 'undefined' && window.isSecureContext === false) {
		return { ok: false, reason: 'https', msg: efbRecText('recNeedsHttps', 'Recording requires a secure (HTTPS) connection. Please ask the site administrator to enable HTTPS on this host.') };
	}
	if (typeof MediaRecorder === 'undefined' || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
		return { ok: false, reason: 'nosupport', msg: efbRecText('recNotSupported', 'Your browser does not support this recording feature.') };
	}
	if (kind === 'screen_recorder' && !navigator.mediaDevices.getDisplayMedia) {
		return { ok: false, reason: 'screen', msg: efbRecText('recScreenNotSupported', 'Screen recording is not supported on this device or browser (most mobile browsers do not allow it). Please open the form in a desktop browser such as Chrome, Edge or Firefox.') };
	}
	return { ok: true, reason: '', msg: '' };
}

/* Stamps an unsupported shell once: warning inside the frame + disabled start. */
function efbRecApplySupportNotice(shell) {
	if (!shell || shell.dataset.supportChecked === '1') return;
	shell.dataset.supportChecked = '1';
	var info = efbRecSupportInfo(shell.dataset.kind);
	if (info.ok) return;
	shell.classList.add('efb-recorder-unsupported');
	var idle = efbRecEl(shell.dataset.id, '-idle');
	if (idle) {
		idle.innerHTML = '<i class="efb bi-exclamation-triangle-fill"></i><span class="efb efb-recorder-unsupported-msg">' + info.msg + '</span>';
	}
	var controls = efbRecEl(shell.dataset.id, '-controls');
	var primary = controls ? controls.querySelector('.efb-recorder-primary-btn') : null;
	if (primary) {
		primary.disabled = true;
		primary.title = info.msg;
	}
	efbRecSetStatus(shell.dataset.id, info.msg);
}

function efbRecScanSupport(root) {
	var scope = root && root.querySelectorAll ? root : document;
	scope.querySelectorAll('.efb-recorder-shell').forEach(function (shell) {
		efbRecApplySupportNotice(shell);
	});
}

if (typeof document !== 'undefined') {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { efbRecScanSupport(document); });
	} else {
		efbRecScanSupport(document);
	}
	/* Widgets are also injected dynamically (builder canvas re-renders, popup and
	 * multi-step forms); watch for them instead of expecting every render path
	 * to remember to call the scan. */
	if (typeof MutationObserver !== 'undefined' && document.body) {
		new MutationObserver(function () { efbRecScanSupport(document); }).observe(document.body, { childList: true, subtree: true });
	} else if (typeof MutationObserver !== 'undefined') {
		document.addEventListener('DOMContentLoaded', function () {
			new MutationObserver(function () { efbRecScanSupport(document); }).observe(document.body, { childList: true, subtree: true });
		});
	}
}

function efbRecQualityConstraints(kind, quality, opts) {
	opts = opts || {};
	if (kind === 'audio_recorder') {
		var audioBitsMap = { low: 32000, standard: 96000, high: 192000 };
		var audio = { channelCount: quality === 'low' ? 1 : 2 };
		// Browsers that don't implement these constraints simply ignore them.
		if (opts.noise !== undefined) {
			audio.noiseSuppression = !!opts.noise;
			audio.echoCancellation = !!opts.noise;
		}
		return {
			audio: audio,
			audioBitsPerSecond: audioBitsMap[quality] || audioBitsMap.standard
		};
	}
	var videoMap = {
		'480p': { width: 854, height: 480, bitrate: 1200000 },
		'720p': { width: 1280, height: 720, bitrate: 2500000 },
		'1080p': { width: 1920, height: 1080, bitrate: 4500000 }
	};
	var v = videoMap[quality] || videoMap['720p'];
	var video = { width: { ideal: v.width }, height: { ideal: v.height } };
	// `ideal` keeps desktops (single camera) working while phones pick front/back.
	if (kind === 'video_recorder' && opts.facing) {
		video.facingMode = { ideal: opts.facing };
	}
	return {
		video: video,
		videoBitsPerSecond: v.bitrate
	};
}

function efbRecMimeType(kind) {
	/* webm covers Chrome/Edge/Firefox/Android; mp4 covers Safari (macOS/iOS),
	 * which records AAC/H.264 and rejects every webm flavour. */
	var candidates = kind === 'audio_recorder'
		? ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus']
		: ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'];
	for (var i = 0; i < candidates.length; i++) {
		if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(candidates[i])) {
			return candidates[i];
		}
	}
	return kind === 'audio_recorder' ? 'audio/webm' : 'video/webm';
}

/* File extension for the container the browser ACTUALLY produced (never assume
 * webm - Safari records mp4). */
function efbRecFileExt(mime) {
	if (!mime) return '.webm';
	if (mime.indexOf('mp4') !== -1) return kindOf(mime) === 'audio' ? '.m4a' : '.mp4';
	if (mime.indexOf('ogg') !== -1) return '.ogg';
	return '.webm';
	function kindOf(m) { return m.indexOf('audio') === 0 ? 'audio' : 'video'; }
}

function efbRecFormatTime(sec) {
	sec = Math.max(0, Math.floor(sec));
	var m = Math.floor(sec / 60);
	var s = sec % 60;
	return (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
}

function efbRecEl(id, suffix) {
	return document.getElementById(id + suffix);
}

function efbRecShell(id) {
	return document.getElementById(id + '_');
}

/* Drives the visible state of the widget: which buttons show, the shell's data-state
 * (used by CSS for the pulsing record button / red frame / progress styling), and the
 * primary button's icon+action (idle = start, recording/paused = stop). */
function efbRecApplyState(id, state) {
	var shell = efbRecShell(id);
	if (!shell) return;
	shell.dataset.state = state;

	var controls = efbRecEl(id, '-controls');
	if (!controls) return;
	var map = {
		idle: [],
		countdown: [],
		recording: ['pause'],
		paused: ['resume'],
		stopped: ['redo', 'play', 'upload']
	};
	var shown = map[state] || [];
	if (state === 'stopped' && shell.dataset.download === '1') shown = shown.concat(['download']);
	if (state === 'stopped' && Number(shell.dataset.formid || 0) < 1) shown = shown.filter(function (action) { return action !== 'upload'; });
	controls.querySelectorAll('.efb-recorder-secondary-btn').forEach(function (btn) {
		btn.classList.toggle('d-none', shown.indexOf(btn.dataset.action) === -1);
	});

	var primary = controls.querySelector('.efb-recorder-primary-btn');
	if (primary) {
		var kind = shell.dataset.kind;
		var iconMap = { audio_recorder: 'bi-mic', video_recorder: 'bi-camera-video', screen_recorder: 'bi-display' };
		var startIcon = primary.dataset.startIcon || iconMap[kind];
		var stopIcon = primary.dataset.stopIcon || 'bi-stop-fill';
		if (state === 'idle') {
			primary.dataset.action = 'start';
			primary.title = efbRecText('recStart', 'Start Recording');
			primary.innerHTML = '<i class="efb bi ' + startIcon + '"></i>';
		} else if (state === 'recording' || state === 'paused' || state === 'countdown') {
			primary.dataset.action = 'stop';
			primary.title = efbRecText('recStop', 'Stop');
			primary.innerHTML = '<i class="efb ' + stopIcon + '"></i>';
		}
	}
}

function efbRecSetStatus(id, text) {
	var statusEl = efbRecEl(id, '-status');
	if (!statusEl) return;
	var dot = statusEl.querySelector('.efb-recorder-status-dot');
	statusEl.textContent = '';
	if (dot) statusEl.appendChild(dot);
	else statusEl.innerHTML = '<span class="efb efb-recorder-status-dot"></span>';
	statusEl.appendChild(document.createTextNode(text));
}

function efbRecUploadEls(id) {
	return {
		button: efbRecEl(id, '-upload')
	};
}

/* Upload feedback stays in the existing status row and turns the neighbouring
 * Download-style icon into a spinner/check. This avoids a separate action row. */
function efbRecSetUploadUi(id, mode, percent, message) {
	var ui = efbRecUploadEls(id);
	if (!ui.button) return;
	var isUploading = mode === 'uploading';
	var isUploaded = mode === 'uploaded';
	ui.button.disabled = isUploading || isUploaded || mode === 'invalid';
	ui.button.classList.toggle('efb-recorder-uploading', isUploading);
	ui.button.classList.toggle('efb-recorder-uploaded', isUploaded);
	ui.button.classList.toggle('efb-recorder-upload-error', mode === 'failed' || mode === 'invalid');
	var label = mode === 'uploaded' ? efbRecText('recUploaded', 'Recording uploaded.') :
		(mode === 'uploading' ? efbRecText('recUploading', 'Uploading recording…') : efbRecText('recUpload', 'Upload'));
	ui.button.title = label;
	ui.button.setAttribute('aria-label', label);
	ui.button.innerHTML = '<i class="efb bi-' + (isUploaded ? 'check-lg' : (isUploading ? 'arrow-repeat' : 'cloud-arrow-up-fill')) + '" aria-hidden="true"></i>';
	var safePercent = Math.max(0, Math.min(100, Number(percent) || 0));
	if (isUploading) efbRecSetStatus(id, label + ' ' + safePercent + '%');
}

function efbRecAllowedMimes(kind) {
	return kind === 'audio_recorder'
		? ['audio/webm', 'audio/mp4', 'audio/ogg']
		: ['video/webm', 'video/mp4'];
}

function efbRecValidateFile(id) {
	var shell = efbRecShell(id);
	var state = EFB_REC_STATE[id];
	if (!shell || !state || !state.file) return { ok: false, message: efbRecText('recNoFile', 'No recording is ready to upload.') };
	var maxSizeMb = Number(shell.dataset.maxSize);
	if (!isFinite(maxSizeMb) || maxSizeMb <= 0) maxSizeMb = 20;
	/* A field can never exceed the actual PHP/WordPress upload ceiling. Public
	 * forms receive this hint from the server; server validation remains final. */
	var hostMaxMb = typeof efb_var !== 'undefined' && efb_var ? Number(efb_var.upload_max) : 0;
	if (isFinite(hostMaxMb) && hostMaxMb > 0) maxSizeMb = Math.min(maxSizeMb, hostMaxMb);
	var maxBytes = maxSizeMb * 1024 * 1024;
	if (!state.file.size || state.file.size > maxBytes) {
		return { ok: false, message: efbRecText('recFileTooLarge', 'The recording is larger than the allowed file size.') + ' (' + maxSizeMb + ' MB)' };
	}
	var mime = String(state.file.type || state.mimeType || '').split(';')[0].toLowerCase();
	if (efbRecAllowedMimes(shell.dataset.kind).indexOf(mime) === -1) {
		return { ok: false, message: efbRecText('recInvalidFile', 'This recording format is not allowed.') };
	}
	var maxDuration = Number(shell.dataset.duration) || 90;
	if (Number(state.elapsedMs) > (maxDuration * 1000) + 1500) {
		return { ok: false, message: efbRecText('recDurationExceeded', 'The recording is longer than the allowed duration.') };
	}
	return { ok: true, mime: mime, duration: Math.max(0, Math.ceil(Number(state.elapsedMs || 0) / 1000)) };
}

function efbRecShowUploadError(id, message, isInvalid) {
	efbRecSetStatus(id, message);
	efbRecSetUploadUi(id, isInvalid ? 'invalid' : 'failed', 0, message);
	var msgEl = document.getElementById(id + '_-message');
	if (msgEl && typeof show_msg_efb === 'function') {
		msgEl.textContent = message;
		show_msg_efb(msgEl);
	}
}

function efbRecClearTimer(state) {
	if (state.timerInterval) {
		clearInterval(state.timerInterval);
		state.timerInterval = null;
	}
}

function efbRecStartTimer(id, state) {
	var timerEl = efbRecEl(id, '-timer');
	if (timerEl) timerEl.classList.remove('d-none');
	state.startTs = Date.now() - (state.elapsedMs || 0);
	efbRecClearTimer(state);
	state.timerInterval = setInterval(function () {
		var elapsed = (Date.now() - state.startTs) / 1000;
		state.elapsedMs = elapsed * 1000;
		if (timerEl) timerEl.textContent = efbRecFormatTime(elapsed);
		var progressBar = efbRecEl(id, '-progress');
		if (progressBar && state.maxDuration > 0) {
			progressBar.style.width = Math.min(100, (elapsed / state.maxDuration) * 100) + '%';
		}
		if (state.maxDuration > 0 && elapsed >= state.maxDuration) {
			efbRecAlert(efbRecText('recMaxDurationReached', 'Maximum recording duration reached.'));
			efbRecStop(id);
		}
	}, 250);
}

function efbRecStopStream(state) {
	if (state.stream) {
		state.stream.getTracks().forEach(function (t) { t.stop(); });
		state.stream = null;
	}
	if (state.audioCtx) {
		try { state.audioCtx.close(); } catch (e) {}
		state.audioCtx = null;
	}
	if (state.meterRaf) {
		cancelAnimationFrame(state.meterRaf);
		state.meterRaf = null;
	}
}

function efbRecDrawMeter(id, state) {
	var canvas = efbRecEl(id, '-meter');
	if (!canvas || !state.analyser) return;
	var ctx = canvas.getContext('2d');
	var data = new Uint8Array(state.analyser.frequencyBinCount);
	function draw() {
		if (!state.analyser) return;
		state.analyser.getByteTimeDomainData(data);
		ctx.clearRect(0, 0, canvas.width, canvas.height);
		ctx.beginPath();
		ctx.strokeStyle = '#a98bf0';
		ctx.lineWidth = 2;
		var slice = canvas.width / data.length;
		for (var i = 0; i < data.length; i++) {
			var y = (data[i] / 255) * canvas.height;
			i === 0 ? ctx.moveTo(0, y) : ctx.lineTo(i * slice, y);
		}
		ctx.stroke();
		state.meterRaf = requestAnimationFrame(draw);
	}
	draw();
}

/* Aborts a live session that never produced a recording (countdown cancelled,
 * screen-share ended from the browser UI, ...): releases hardware + resets UI. */
function efbRecAbortLive(id, state) {
	efbRecClearTimer(state);
	efbRecStopStream(state);
	state.recorder = null;
	state.chunks = [];
	state.elapsedMs = 0;
	var shell = efbRecShell(id);
	if (shell) shell.classList.remove('efb-recorder-mirrored');
	var cd = efbRecEl(id, '-countdown');
	if (cd) cd.remove();
	var video = efbRecEl(id, '-preview');
	if (video) {
		video.srcObject = null;
		video.classList.add('d-none');
	}
	var meter = efbRecEl(id, '-meter');
	if (meter) meter.classList.add('d-none');
	efbRecApplyState(id, 'idle');
	efbRecSetStatus(id, efbRecText('recReady', 'Ready to record'));
}

/* Big get-ready overlay (3..2..1) shown AFTER permission is granted, over the
 * already-live preview. Resolves false when the user hits stop meanwhile. */
function efbRecCountdown(id, seconds, state) {
	return new Promise(function (resolve) {
		var frame = efbRecEl(id, '-frame');
		if (!frame) { resolve(true); return; }
		var overlay = document.createElement('div');
		overlay.className = 'efb efb-recorder-countdown';
		overlay.id = id + '-countdown';
		overlay.textContent = seconds;
		frame.appendChild(overlay);
		state.countdownCancelled = false;
		var n = seconds;
		state.countdownTimer = setInterval(function () {
			if (state.countdownCancelled) {
				clearInterval(state.countdownTimer);
				if (overlay.parentNode) overlay.remove();
				resolve(false);
				return;
			}
			n -= 1;
			if (n <= 0) {
				clearInterval(state.countdownTimer);
				if (overlay.parentNode) overlay.remove();
				resolve(true);
			} else {
				overlay.textContent = n;
			}
		}, 1000);
	});
}

async function efbRecStart(id) {
	var shell = efbRecShell(id);
	if (!shell) return;
	var kind = shell.dataset.kind;
	var quality = shell.dataset.quality || (kind === 'audio_recorder' ? 'standard' : '720p');
	var maxDuration = Number(shell.dataset.duration) || 0;

	// Ignore double-activation while already live (touch devices fire fast).
	if (shell.dataset.state !== 'idle' && shell.dataset.state !== 'stopped') return;

	var support = efbRecSupportInfo(kind);
	if (!support.ok) {
		efbRecApplySupportNotice(shell);
		efbRecAlert(support.msg);
		return;
	}

	var state = EFB_REC_STATE[id] || (EFB_REC_STATE[id] = {});
	state.kind = kind;
	state.maxDuration = maxDuration;
	state.elapsedMs = 0;
	state.chunks = [];

	try {
		var constraints = efbRecQualityConstraints(kind, quality, {
			noise: shell.dataset.noise !== '0',
			facing: shell.dataset.facing || 'user'
		});
		if (kind === 'audio_recorder') {
			state.stream = await navigator.mediaDevices.getUserMedia({ audio: constraints.audio });
		} else if (kind === 'video_recorder') {
			state.stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: constraints.video });
		} else {
			state.stream = await navigator.mediaDevices.getDisplayMedia({ video: constraints.video, audio: true });
		}
	} catch (err) {
		efbRecAlert(efbRecText('recPermissionDenied', 'Permission to access your microphone/camera/screen was denied.'));
		return;
	}

	if (kind === 'audio_recorder') {
		var meter = efbRecEl(id, '-meter');
		if (meter) meter.classList.remove('d-none');
		var playback = efbRecEl(id, '-audio-playback');
		if (playback) playback.classList.add('d-none');
		state.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
		var source = state.audioCtx.createMediaStreamSource(state.stream);
		state.analyser = state.audioCtx.createAnalyser();
		state.analyser.fftSize = 1024;
		source.connect(state.analyser);
		efbRecDrawMeter(id, state);
	} else {
		var video = efbRecEl(id, '-preview');
		if (video) {
			video.classList.remove('d-none');
			video.controls = false;
			video.srcObject = state.stream;
			video.muted = true;
			video.play().catch(function () {});
		}
		// Mirror only the LIVE selfie preview; the recorded file stays unmirrored.
		if (kind === 'video_recorder' && shell.dataset.mirror === '1') {
			shell.classList.add('efb-recorder-mirrored');
		}
		// Screen share can be ended from the browser's own UI - treat it as stop.
		var vTrack = state.stream.getVideoTracks()[0];
		if (vTrack) {
			vTrack.onended = function () {
				if (state.recorder && state.recorder.state !== 'inactive') efbRecStop(id);
				else if (shell.dataset.state === 'countdown') { state.countdownCancelled = true; }
			};
		}
	}

	// Optional get-ready countdown (configured per field, data-countdown).
	var countdownSecs = Number(shell.dataset.countdown) || 0;
	if (countdownSecs > 0) {
		efbRecApplyState(id, 'countdown');
		efbRecSetStatus(id, efbRecText('recReady', 'Ready to record'));
		var proceed = await efbRecCountdown(id, countdownSecs, state);
		if (!proceed || !state.stream) {
			efbRecAbortLive(id, state);
			return;
		}
	}

	var mimeType = efbRecMimeType(kind);
	state.mimeType = mimeType.split(';')[0];
	try {
		state.recorder = new MediaRecorder(state.stream, {
			mimeType: mimeType,
			audioBitsPerSecond: constraints.audioBitsPerSecond,
			videoBitsPerSecond: constraints.videoBitsPerSecond
		});
	} catch (e) {
		state.recorder = new MediaRecorder(state.stream);
	}

	state.recorder.ondataavailable = function (e) {
		if (e.data && e.data.size > 0) state.chunks.push(e.data);
	};
	state.recorder.onstop = function () {
		efbRecFinish(id, state);
	};

	state.recorder.start();
	efbRecApplyState(id, 'recording');
	efbRecSetStatus(id, efbRecText('recRecording', 'Recording…'));
	efbRecStartTimer(id, state);
}

function efbRecPauseResume(id) {
	var state = EFB_REC_STATE[id];
	if (!state || !state.recorder) return;
	if (state.recorder.state === 'recording') {
		state.recorder.pause();
		efbRecClearTimer(state);
		efbRecApplyState(id, 'paused');
		efbRecSetStatus(id, efbRecText('recPaused', 'Paused'));
	} else if (state.recorder.state === 'paused') {
		state.recorder.resume();
		efbRecStartTimer(id, state);
		efbRecApplyState(id, 'recording');
		efbRecSetStatus(id, efbRecText('recRecording', 'Recording…'));
	}
}

function efbRecStop(id) {
	var state = EFB_REC_STATE[id];
	if (!state) return;
	// Stop during the countdown = cancel before anything was recorded.
	if (state.countdownTimer && (!state.recorder || state.recorder.state === 'inactive')) {
		state.countdownCancelled = true;
		return;
	}
	if (!state.recorder) return;
	if (state.recorder.state !== 'inactive') state.recorder.stop();
	efbRecClearTimer(state);
}

function efbRecFinish(id, state) {
	efbRecStopStream(state);

	// Container/extension from what the browser ACTUALLY produced: Chrome/Firefox
	// deliver webm, Safari (macOS/iOS) delivers mp4 - never assume .webm.
	var actualMime = state.recorder && state.recorder.mimeType ? state.recorder.mimeType.split(';')[0] : state.mimeType;
	if (actualMime) state.mimeType = actualMime;
	var blob = new Blob(state.chunks, { type: state.mimeType });
	var fileName = id + '-' + Date.now() + efbRecFileExt(state.mimeType);
	var file = new File([blob], fileName, { type: state.mimeType });

	var shell = efbRecShell(id);
	var formId = shell ? Number(shell.dataset.formid) || 0 : 0;
	if (shell) shell.classList.remove('efb-recorder-mirrored');

	var input = document.getElementById(id + '_file');
	if (input) {
		try {
			var dt = new DataTransfer();
			dt.items.add(file);
			input.files = dt.files;
		} catch (e) {}
	}

	if (state.lastUrl) {
		try { URL.revokeObjectURL(state.lastUrl); } catch (e) {}
	}
	state.lastUrl = URL.createObjectURL(blob);
	state.lastFileName = fileName;
	state.file = file;
	state.uploadState = 'ready';
	state.uploadPromise = null;

	if (state.kind !== 'audio_recorder') {
		var video = efbRecEl(id, '-preview');
		if (video) {
			video.srcObject = null;
			video.src = state.lastUrl;
			video.muted = false;
			video.controls = true;
			video.pause();
		}
	} else {
		var meter = efbRecEl(id, '-meter');
		if (meter) meter.classList.add('d-none');
		// Inline playback for the recorded audio (the Play button drives it too).
		var playback = efbRecEl(id, '-audio-playback');
		if (!playback) {
			playback = document.createElement('audio');
			playback.id = id + '-audio-playback';
			playback.className = 'efb efb-recorder-audio-playback';
			playback.controls = true;
			var frame = efbRecEl(id, '-frame');
			var controlsRow = efbRecEl(id, '-controls');
			if (frame) frame.insertBefore(playback, controlsRow || null);
		}
		playback.src = state.lastUrl;
		playback.classList.remove('d-none');
	}

	efbRecApplyState(id, 'stopped');
	var validation = efbRecValidateFile(id);
	if (!validation.ok) {
		state.uploadState = 'invalid';
		efbRecShowUploadError(id, validation.message, true);
		return;
	}
	var msgEl = document.getElementById(id + '_-message');
	if (msgEl && typeof hide_msg_efb === 'function') hide_msg_efb(msgEl);
	efbRecSetStatus(id, efbRecText('recReadyToSubmit', 'Recording ready.'));
	/* Builder previews have no real form/session to submit. Keep recording and
	 * playback useful there, but do not offer an upload that cannot succeed. */
	if (formId > 0) efbRecSetUploadUi(id, 'ready', 0, '');
}

function efbRecUpload(id) {
	var shell = efbRecShell(id);
	var state = EFB_REC_STATE[id];
	if (!shell || !state) return Promise.resolve({ success: false });
	if (state.uploadState === 'uploaded') return Promise.resolve({ success: true });
	if (state.uploadState === 'uploading' && state.uploadPromise) return state.uploadPromise;

	var validation = efbRecValidateFile(id);
	if (!validation.ok) {
		state.uploadState = 'invalid';
		efbRecShowUploadError(id, validation.message, true);
		return Promise.resolve({ success: false, error: validation.message });
	}
	if (!navigator.onLine) {
		var offlineMessage = efbRecText('recUploadOffline', 'You are offline. Please reconnect and upload the recording again.');
		state.uploadState = 'failed';
		efbRecShowUploadError(id, offlineMessage);
		return Promise.resolve({ success: false, error: offlineMessage });
	}
	if (typeof fun_upload_file_api_emsFormBuilder !== 'function') {
		var unavailableMessage = efbRecText('recUploadUnavailable', 'Uploading is not available right now. Please try again.');
		state.uploadState = 'failed';
		efbRecShowUploadError(id, unavailableMessage);
		return Promise.resolve({ success: false, error: unavailableMessage });
	}

	state.uploadState = 'uploading';
	efbRecSetStatus(id, efbRecText('recUploading', 'Uploading recording…'));
	efbRecSetUploadUi(id, 'uploading', 0, '0%');
	state.uploadPromise = fun_upload_file_api_emsFormBuilder(id, validation.mime, 'msg', state.file, {
		form_id: Number(shell.dataset.formid) || 0,
		recorder_type: shell.dataset.kind,
		recording_duration: validation.duration,
		silent: true,
		delay: 0,
		onProgress: function (percent) {
			efbRecSetUploadUi(id, 'uploading', percent, percent + '%');
		},
		onSuccess: function () {
			state.uploadState = 'uploaded';
			efbRecSetStatus(id, efbRecText('recUploaded', 'Recording uploaded.'));
			efbRecSetUploadUi(id, 'uploaded', 100, '100%');
		},
		onError: function (message) {
			state.uploadState = 'failed';
			efbRecShowUploadError(id, message || efbRecText('recUploadFailed', 'The recording could not be uploaded. Please try again.'));
		}
	}).then(function (result) {
		if (!result || !result.success) {
			state.uploadState = 'failed';
			var message = (result && result.error) || efbRecText('recUploadFailed', 'The recording could not be uploaded. Please try again.');
			efbRecShowUploadError(id, message);
		}
		return result || { success: false };
	}).finally(function () {
		state.uploadPromise = null;
	});
	return state.uploadPromise;
}

/* Called by the final-submit flow. It uploads only recordings that are ready,
 * in sequence, then lets the normal form submit pipeline run. Sequencing avoids
 * the burst of concurrent requests that can trip rate-limits/Human Shield. */
async function efbRecUploadPendingForForm(formId) {
	var pending = [];
	Object.keys(EFB_REC_STATE).forEach(function (id) {
		var state = EFB_REC_STATE[id];
		var shell = efbRecShell(id);
		if (!shell || Number(shell.dataset.formid || 0) !== Number(formId || 0) || !state || !state.file) return;
		if (state.uploadState !== 'uploaded') pending.push(id);
	});
	for (var i = 0; i < pending.length; i++) {
		var state = EFB_REC_STATE[pending[i]];
		if (state.uploadState === 'failed' || state.uploadState === 'invalid') {
			return { success: false, error: efbRecText('recUploadFailed', 'Please upload the recording before submitting the form.') };
		}
		var result = await efbRecUpload(pending[i]);
		if (!result || !result.success) return result || { success: false };
	}
	return { success: true };
}

function efbRecRedo(id) {
	var state = EFB_REC_STATE[id] || (EFB_REC_STATE[id] = {});
	efbRecStopStream(state);
	state.chunks = [];
	state.elapsedMs = 0;
	state.file = null;
	state.uploadState = 'idle';
	state.uploadPromise = null;
	/* A re-record replaces an already uploaded URL. Remove the old send-back row
	 * so the final form never submits the previous recording by mistake. */
	if (typeof sendBack_emsFormBuilder_pub !== 'undefined') {
		for (var s = sendBack_emsFormBuilder_pub.length - 1; s >= 0; s--) {
			if (sendBack_emsFormBuilder_pub[s] && sendBack_emsFormBuilder_pub[s].id_ === id) sendBack_emsFormBuilder_pub.splice(s, 1);
		}
	}

	var input = document.getElementById(id + '_file');
	if (input) {
		try { input.value = ''; } catch (e) {}
		try {
			var dt = new DataTransfer();
			input.files = dt.files;
		} catch (e) {}
	}

	var video = efbRecEl(id, '-preview');
	if (video) {
		video.removeAttribute('src');
		video.srcObject = null;
		video.classList.add('d-none');
		video.controls = false;
	}
	var playback = efbRecEl(id, '-audio-playback');
	if (playback) {
		playback.pause();
		playback.removeAttribute('src');
		playback.classList.add('d-none');
	}
	if (state.lastUrl) {
		try { URL.revokeObjectURL(state.lastUrl); } catch (e) {}
		state.lastUrl = null;
	}

	var shell = efbRecShell(id);
	if (shell) shell.classList.remove('efb-recorder-mirrored');
	var cd = efbRecEl(id, '-countdown');
	if (cd) cd.remove();

	var timerEl = efbRecEl(id, '-timer');
	if (timerEl) {
		timerEl.textContent = '00:00';
		timerEl.classList.add('d-none');
	}
	var progressBar = efbRecEl(id, '-progress');
	if (progressBar) progressBar.style.width = '0%';

	efbRecApplyState(id, 'idle');
	efbRecSetStatus(id, efbRecText('recReady', 'Ready to record'));
	efbRecSetUploadUi(id, 'hidden', 0, '');
}

function efbRecPlay(id) {
	var video = efbRecEl(id, '-preview');
	if (video && video.src) {
		video.play().catch(function () {});
		return;
	}
	var audioPlayback = efbRecEl(id, '-audio-playback');
	if (audioPlayback && audioPlayback.src) audioPlayback.play().catch(function () {});
}

/* Hands the visitor a local copy of what they just recorded (rec_download). */
function efbRecDownload(id) {
	var state = EFB_REC_STATE[id];
	if (!state || !state.lastUrl) return;
	var a = document.createElement('a');
	a.href = state.lastUrl;
	a.download = state.lastFileName || (id + efbRecFileExt(state.mimeType));
	document.body.appendChild(a);
	a.click();
	a.remove();
}

document.addEventListener('click', function (e) {
	var btn = e.target.closest ? e.target.closest('[data-action]') : null;
	if (!btn) return;
	var shell = btn.closest('.efb-recorder-shell');
	if (!shell) return;
	var id = shell.dataset.id;
	var action = btn.dataset.action;
	if (action === 'start') efbRecStart(id);
	else if (action === 'stop') efbRecStop(id);
	else if (action === 'pause' || action === 'resume') efbRecPauseResume(id);
	else if (action === 'redo') efbRecRedo(id);
	else if (action === 'play') efbRecPlay(id);
	else if (action === 'download') efbRecDownload(id);
	else if (action === 'upload') efbRecUpload(id);
});
