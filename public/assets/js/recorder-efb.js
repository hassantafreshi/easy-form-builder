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
	var required = vj.required == 1 || vj.required == true;
	var requiredClass = required ? 'required' : '';
	var requiredAttr = required ? 'required' : '';
	var domain = (typeof window !== 'undefined' && window.location) ? window.location.hostname : '';

	var mediaPreview = kind === 'audio_recorder'
		? '<canvas class="efb efb-recorder-meter d-none" id="' + rndm + '-meter" width="300" height="64"></canvas>'
		: '<video class="efb efb-recorder-video d-none" id="' + rndm + '-preview" playsinline muted></video>' +
			'<div class="efb efb-recorder-watermark" id="' + rndm + '-watermark"><span>' + efbRecText('recWatermark', 'Made by Easy Form Builder') + '</span><span class="efb efb-recorder-domain">' + domain + '</span></div>';

	return '' +
		'<div class="efb efb-recorder-shell ' + classMap[kind] + '" id="' + rndm + '_" data-id="' + rndm + '" data-kind="' + kind + '" data-quality="' + quality + '" data-duration="' + duration + '" data-formid="' + (formId || 0) + '" data-state="idle">' +
			'<div class="efb efb-recorder-frame" id="' + rndm + '-frame">' +
				mediaPreview +
				'<div class="efb efb-recorder-idle-hint" id="' + rndm + '-idle"><i class="efb bi ' + iconMap[kind] + '"></i><span>' + efbRecText('recTapToStart', 'Tap to start recording') + '</span></div>' +
				'<div class="efb efb-recorder-timer d-none" id="' + rndm + '-timer">00:00</div>' +
				'<div class="efb efb-recorder-action-row" id="' + rndm + '-controls">' +
					'<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="pause" data-id="' + rndm + '" title="' + efbRecText('recPause', 'Pause') + '"><i class="efb bi-pause-fill"></i></button>' +
					'<button type="button" class="efb efb-recorder-primary-btn" data-action="start" data-id="' + rndm + '" title="' + efbRecText('recStart', 'Start Recording') + '"><i class="efb bi ' + iconMap[kind] + '"></i></button>' +
					'<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="resume" data-id="' + rndm + '" title="' + efbRecText('recResume', 'Resume') + '"><i class="efb bi-record-circle"></i></button>' +
					'<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="redo" data-id="' + rndm + '" title="' + efbRecText('recRedo', 'Re-record') + '"><i class="efb bi-arrow-counterclockwise"></i></button>' +
					'<button type="button" class="efb efb-recorder-secondary-btn d-none" data-action="play" data-id="' + rndm + '" title="' + efbRecText('recPlay', 'Play') + '"><i class="efb bi-play-fill"></i></button>' +
				'</div>' +
				'<div class="efb efb-recorder-progress-track"><div class="efb efb-recorder-progress-bar" id="' + rndm + '-progress"></div></div>' +
			'</div>' +
			'<div class="efb efb-recorder-status-row">' +
				'<span class="efb efb-recorder-status" id="' + rndm + '-status"><span class="efb efb-recorder-status-dot"></span>' + efbRecText('recReady', 'Ready to record') + '</span>' +
				'<span class="efb efb-recorder-badge">' + efbRecText(kind, kind) + '</span>' +
			'</div>' +
			'<input type="file" hidden accept="' + acceptMap[kind] + '" data-type="' + kind + '" data-vid="' + rndm + '" data-id="' + rndm + '" class="efb emsFormBuilder_v ' + kind + ' ' + requiredClass + '" id="' + rndm + '_file" data-formid="' + (formId || 0) + '" onchange="valid_file_emsFormBuilder(\'' + rndm + '\',\'msg\',\'\',' + (formId || 0) + ')" ' + requiredAttr + '>' +
		'</div>';
}

function efbRecQualityConstraints(kind, quality) {
	if (kind === 'audio_recorder') {
		var audioBitsMap = { low: 32000, standard: 96000, high: 192000 };
		return {
			audio: { channelCount: quality === 'low' ? 1 : 2 },
			audioBitsPerSecond: audioBitsMap[quality] || audioBitsMap.standard
		};
	}
	var videoMap = {
		'480p': { width: 854, height: 480, bitrate: 1200000 },
		'720p': { width: 1280, height: 720, bitrate: 2500000 },
		'1080p': { width: 1920, height: 1080, bitrate: 4500000 }
	};
	var v = videoMap[quality] || videoMap['720p'];
	return {
		video: { width: { ideal: v.width }, height: { ideal: v.height } },
		videoBitsPerSecond: v.bitrate
	};
}

function efbRecMimeType(kind) {
	var candidates = kind === 'audio_recorder'
		? ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus']
		: ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];
	for (var i = 0; i < candidates.length; i++) {
		if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(candidates[i])) {
			return candidates[i];
		}
	}
	return kind === 'audio_recorder' ? 'audio/webm' : 'video/webm';
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
		recording: ['pause'],
		paused: ['resume'],
		stopped: ['redo', 'play']
	};
	var shown = map[state] || [];
	controls.querySelectorAll('.efb-recorder-secondary-btn').forEach(function (btn) {
		btn.classList.toggle('d-none', shown.indexOf(btn.dataset.action) === -1);
	});

	var primary = controls.querySelector('.efb-recorder-primary-btn');
	if (primary) {
		var kind = shell.dataset.kind;
		var iconMap = { audio_recorder: 'bi-mic', video_recorder: 'bi-camera-video', screen_recorder: 'bi-display' };
		if (state === 'idle') {
			primary.dataset.action = 'start';
			primary.title = efbRecText('recStart', 'Start Recording');
			primary.innerHTML = '<i class="efb bi ' + iconMap[kind] + '"></i>';
		} else if (state === 'recording' || state === 'paused') {
			primary.dataset.action = 'stop';
			primary.title = efbRecText('recStop', 'Stop');
			primary.innerHTML = '<i class="efb bi-stop-fill"></i>';
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

async function efbRecStart(id) {
	var shell = efbRecShell(id);
	if (!shell) return;
	var kind = shell.dataset.kind;
	var quality = shell.dataset.quality || (kind === 'audio_recorder' ? 'standard' : '720p');
	var maxDuration = Number(shell.dataset.duration) || 0;

	if (typeof MediaRecorder === 'undefined' || !navigator.mediaDevices) {
		efbRecAlert(efbRecText('recNotSupported', 'Your browser does not support this recording feature.'));
		return;
	}

	var state = EFB_REC_STATE[id] || (EFB_REC_STATE[id] = {});
	state.kind = kind;
	state.maxDuration = maxDuration;
	state.elapsedMs = 0;
	state.chunks = [];

	try {
		var constraints = efbRecQualityConstraints(kind, quality);
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
			video.srcObject = state.stream;
			video.muted = true;
			video.play().catch(function () {});
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
	efbRecSetStatus(id, efbRecText('recRecording', 'Recording...'));
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
		efbRecSetStatus(id, efbRecText('recRecording', 'Recording...'));
	}
}

function efbRecStop(id) {
	var state = EFB_REC_STATE[id];
	if (!state || !state.recorder) return;
	if (state.recorder.state !== 'inactive') state.recorder.stop();
	efbRecClearTimer(state);
}

function efbRecFinish(id, state) {
	efbRecStopStream(state);

	var blob = new Blob(state.chunks, { type: state.mimeType });
	var fileName = id + '-' + Date.now() + '.webm';
	var file = new File([blob], fileName, { type: state.mimeType });

	var shell = efbRecShell(id);
	var formId = shell ? Number(shell.dataset.formid) || 0 : 0;

	var input = document.getElementById(id + '_file');
	if (input) {
		try {
			var dt = new DataTransfer();
			dt.items.add(file);
			input.files = dt.files;
		} catch (e) {}
	}

	if (state.kind !== 'audio_recorder') {
		var video = efbRecEl(id, '-preview');
		if (video) {
			video.srcObject = null;
			video.src = URL.createObjectURL(blob);
			video.muted = false;
			video.controls = true;
			video.pause();
		}
	} else {
		var meter = efbRecEl(id, '-meter');
		if (meter) meter.classList.add('d-none');
	}

	efbRecApplyState(id, 'stopped');
	efbRecSetStatus(id, efbRecText('recReadyToSubmit', 'Recording ready.'));

	if (typeof valid_file_emsFormBuilder === 'function' && input) {
		valid_file_emsFormBuilder(id, 'msg', '', formId);
	}
}

function efbRecRedo(id) {
	var state = EFB_REC_STATE[id] || (EFB_REC_STATE[id] = {});
	efbRecStopStream(state);
	state.chunks = [];
	state.elapsedMs = 0;

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
		if (video.src) URL.revokeObjectURL(video.src);
		video.removeAttribute('src');
		video.srcObject = null;
		video.classList.add('d-none');
		video.controls = false;
	}

	var timerEl = efbRecEl(id, '-timer');
	if (timerEl) {
		timerEl.textContent = '00:00';
		timerEl.classList.add('d-none');
	}
	var progressBar = efbRecEl(id, '-progress');
	if (progressBar) progressBar.style.width = '0%';

	efbRecApplyState(id, 'idle');
	efbRecSetStatus(id, efbRecText('recReady', 'Ready to record'));
}

function efbRecPlay(id) {
	var video = efbRecEl(id, '-preview');
	if (video && video.src) {
		video.play().catch(function () {});
		return;
	}
	var audioPlayback = efbRecEl(id, '-audio-playback');
	if (audioPlayback) audioPlayback.play().catch(function () {});
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
});
