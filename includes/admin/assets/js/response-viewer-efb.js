
const EfbResponseViewer = (function () {
  'use strict';

  function _t(key) {
    if (typeof efb_var !== 'undefined' && efb_var.text && efb_var.text[key]) return efb_var.text[key];
    if (typeof ajax_object_efm !== 'undefined' && ajax_object_efm.text && ajax_object_efm.text[key]) return ajax_object_efm.text[key];
    return key;
  }

  function isRtl() {
    return (typeof efb_var !== 'undefined' && efb_var.rtl == 1);
  }

  function shortcodeToHtml(text) {
    if (!text || typeof text !== 'string') return text || '';
    let html = text;

    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');
    html = html.replace(/__(.+?)__/g, '<u>$1</u>');

    html = html.replace(
      /(?<!"|\bhref="|>)(https?:\/\/[^\s<"']+)/gi,
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
    );

    return html;
  }

  function htmlToShortcode(html) {
    if (!html || typeof html !== 'string') return '';
    let text = html;

    text = text.replace(/<br\s*\/?>/gi, '\n');
    text = text.replace(/<\/?(div|p|li|blockquote)[^>]*>/gi, '\n');

    text = text.replace(/<(strong|b)\b[^>]*>([\s\S]*?)<\/\1>/gi, '**$2**');
    text = text.replace(/<(em|i)\b[^>]*>([\s\S]*?)<\/\1>/gi, '*$2*');
    text = text.replace(/<u\b[^>]*>([\s\S]*?)<\/u>/gi, '__$1__');

    text = text.replace(/<a\b[^>]*href="([^"]*)"[^>]*>[\s\S]*?<\/a>/gi, '$1');

    text = text.replace(/<[^>]+>/g, '');

    text = text.replace(/&amp;/g, '&');
    text = text.replace(/&lt;/g, '<');
    text = text.replace(/&gt;/g, '>');
    text = text.replace(/&nbsp;/g, ' ');
    text = text.replace(/&quot;/g, '"');

    text = text.replace(/\n{3,}/g, '\n\n');
    text = text.trim();

    return text;
  }

  function formatMessageForDisplay(text) {
    if (!text || typeof text !== 'string') return text || '';
    let formatted = text.replace(/@efb@nq#/g, '<br>');
    formatted = shortcodeToHtml(formatted);
    return formatted;
  }

  function buildRichEditor(msgId, savedValue) {
    const placeholderText = _t('enterYourMessage') || 'Type your reply...';
    const initialHtml = savedValue ? shortcodeToHtml(savedValue.replace(/@efb@nq#/g, '<br>')) : '';

    return `
    <div class="efb-reply-section ${isRtl() ? 'rtl-text' : ''}" id="replay_section__emsFormBuilder">
      <div class="efb-reply-label" id="label_replyM_efb">
        <i class="bi bi-reply"></i> ${_t('reply')}:
      </div>
      <!-- Toolbar -->
      <div class="efb-editor-toolbar" id="efb_editor_toolbar">
        <button type="button" class="efb-editor-btn" data-cmd="bold" title="Bold (Ctrl+B)">
          <i class="bi bi-type-bold"></i>
        </button>
        <button type="button" class="efb-editor-btn" data-cmd="italic" title="Italic (Ctrl+I)">
          <i class="bi bi-type-italic"></i>
        </button>
        <button type="button" class="efb-editor-btn" data-cmd="underline" title="Underline (Ctrl+U)">
          <i class="bi bi-type-underline"></i>
        </button>
        <span class="efb-editor-toolbar-sep"></span>
        <button type="button" class="efb-editor-btn" data-cmd="removeFormat" title="Clear formatting">
          <i class="bi bi-eraser"></i>
        </button>
        ${_buildAttachToolbarBtn(msgId)}
      </div>
      <!-- Editable Area -->
      <div class="efb-rich-editor"
           id="efb_rich_editor"
           contenteditable="true"
           data-placeholder="${placeholderText}"
           data-id="${msgId}"
           spellcheck="true">${initialHtml}</div>
      <!-- Hidden textarea for compatibility -->
      <textarea class="efb-editor-raw" id="replayM_emsFormBuilder" rows="5" data-id="${msgId}">${savedValue || ''}</textarea>
    </div>`;
  }

  function initRichEditor(msgId) {
    const editor = document.getElementById('efb_rich_editor');
    const raw = document.getElementById('replayM_emsFormBuilder');
    const toolbar = document.getElementById('efb_editor_toolbar');
    if (!editor || !raw || !toolbar) return;

    toolbar.addEventListener('click', function (e) {
      const btn = e.target.closest('.efb-editor-btn');
      if (!btn) return;
      e.preventDefault();
      const cmd = btn.dataset.cmd;
      if (!cmd) return;

      editor.focus();
      document.execCommand(cmd, false, null);
      _syncRawFromEditor(editor, raw, msgId);
      _updateToolbarState(toolbar);
    });

    editor.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && !e.shiftKey) {
        switch (e.key.toLowerCase()) {
          case 'b':
            e.preventDefault();
            document.execCommand('bold', false, null);
            break;
          case 'i':
            e.preventDefault();
            document.execCommand('italic', false, null);
            break;
          case 'u':
            e.preventDefault();
            document.execCommand('underline', false, null);
            break;
        }
        _syncRawFromEditor(editor, raw, msgId);
        _updateToolbarState(toolbar);
      }
    });

    editor.addEventListener('input', function () {
      _syncRawFromEditor(editor, raw, msgId);
      var replyBtn = document.getElementById('replayB_emsFormBuilder');
      if (replyBtn && replyBtn.classList.contains('disabled')) {
        replyBtn.classList.remove('disabled');
      }
    });

    document.addEventListener('selectionchange', function () {
      if (document.activeElement === editor) {
        _updateToolbarState(toolbar);
      }
    });

    editor.addEventListener('paste', function (e) {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
    });
  }

  function _syncRawFromEditor(editor, raw, msgId) {
    const shortcode = htmlToShortcode(editor.innerHTML);
    raw.value = shortcode;
    if (typeof localStorage !== 'undefined' && msgId) {
      localStorage.setItem('replayM_emsFormBuilder_' + msgId, shortcode);
    }
  }

  function _updateToolbarState(toolbar) {
    const buttons = toolbar.querySelectorAll('.efb-editor-btn[data-cmd]');
    buttons.forEach(function (btn) {
      const cmd = btn.dataset.cmd;
      if (cmd === 'removeFormat') return;
      if (document.queryCommandState(cmd)) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    });
  }

  function buildReplyActions(msgId, isPanel) {
    const uploadHtml = buildFileUploadArea(msgId, isPanel);
    return `
    <div class="efb-reply-actions efb pb-2">
      <button type="submit" class="efb-reply-btn" id="replayB_emsFormBuilder"
              onclick="fun_send_replayMessage_emsFormBuilder(${msgId})">
        <i class="bi bi-reply"></i> ${_t('reply')}
      </button>
      <p class="efb-reply-status" id="replay_state__emsFormBuilder"></p>
    </div>
    ${uploadHtml}`;
  }

  function buildAdminResponseBody(indx, formType) {
    const msg_id = valueJson_ws_messages[indx].msg_id;
    const userIp = valueJson_ws_messages[indx].ip;
    const track = valueJson_ws_messages[indx].track;
    const date = valueJson_ws_messages[indx].date;
    const content = JSON.parse(replaceContentMessageEfb(valueJson_ws_messages[indx].content));

    let by = valueJson_ws_messages[indx].read_by !== null ? valueJson_ws_messages[indx].read_by : "Unkown";
    if (by == 1) { by = 'Admin'; } else if (by == 0 || by.length == 0 || by.length == -1) { by = '#first'; }

    const m = fun_emsFormBuilder_show_messages(content, by, userIp, track, date);

    form_type_emsFormBuilder = formType;

    let replySection = '';
    if (formType !== 'subscribe' && formType !== 'register' && formType !== 'survey') {
      const savedValue = localStorage.getItem('replayM_emsFormBuilder_' + msg_id) || '';
      replySection = buildRichEditor(msg_id, savedValue) + buildReplyActions(msg_id, true);
    }

    const body = `
      <div class="efb-resp-viewer ${isRtl() ? 'rtl-text' : ''}">
        <div class="efb-resp-messages ${isRtl() ? 'rtl-text' : ''}" id="resp_efb">${m}</div>
        ${replySection}
      </div>`;

    return body;
  }

  function buildPublicResponseBody(value, content) {
    const msg_id = value.msg_id;
    const track = value.track;
    const date = value.date;
    const val = JSON.parse(replaceContentMessageEfb(value.content));
    let m = fun_emsFormBuilder_show_messages(val, '#first', '', track, date);

    for (let c of content) {
      const cval = JSON.parse(c.content.replace(/[\\]/g, ''));
      m += `<div class="efb mb-3"><div class="efb clearfix">${fun_emsFormBuilder_show_messages(cval, c.rsp_by, '', track, c.date)}</div></div>`;
    }

    const savedValue = '';
    const uploadHtml = buildFileUploadArea(msg_id, false);
    const replySection = buildRichEditor(msg_id, savedValue) + `
    <div class="efb-reply-actions">
      <button type="submit" class="efb-reply-btn" id="replayB_emsFormBuilder"
              onclick="fun_send_replayMessage_emsFormBuilder(${msg_id})">
        <i class="bi bi-reply"></i> ${typeof ajax_object_efm !== 'undefined' && ajax_object_efm.text ? ajax_object_efm.text.reply : _t('reply')}
      </button>
      ${typeof sitekye_emsFormBuilder !== 'undefined' && sitekye_emsFormBuilder ?
        `<div class="efb row mx-3"><div class="efb g-recaptcha my-2 mx-2" data-sitekey="${sitekye_emsFormBuilder}" id="recaptcha"></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ''}
      <p class="efb-reply-status" id="replay_state__emsFormBuilder"></p>
    </div>
    ${uploadHtml}`;

    const body = `
    <div class="efb-resp-viewer ${isRtl() ? 'rtl-text' : ''}">
      <div class="efb-resp-messages ${isRtl() ? 'rtl-text' : ''}" id="resp_efb">${m}</div>
      ${replySection}
    </div>`;

    return body;
  }

  function initAfterRender(msgId, isPanel) {
    initFileUpload(msgId);

    initRichEditor(msgId);

    window.scrollTo({ top: 0, behavior: 'smooth' });

const chatHistory = document.getElementById('resp_efb');
    if (chatHistory) {
      setTimeout(function () {
        chatHistory.scrollTop = chatHistory.scrollHeight;
      }, 50);
    }
  }

  function getEditorValue() {
    const raw = document.getElementById('replayM_emsFormBuilder');
    if (!raw) return '';
    let value = raw.value;
    value = value.replace(/\n/g, '@efb@nq#');
    return value;
  }

  function _buildAttachToolbarBtn(msgId) {
    if (typeof setting_emsFormBuilder !== 'undefined' &&
      setting_emsFormBuilder.hasOwnProperty('dsupfile') &&
      setting_emsFormBuilder.dsupfile == false &&
      typeof efb_var !== 'undefined' && !efb_var.hasOwnProperty('setting')) {
      return '';
    }

    const isPro = typeof pro_efb !== 'undefined' && pro_efb === true;
    const attachTitle = _t('dsupfile') || 'Attach file';
    const proText = _t('fieldAvailableInProversion') || 'Available in Pro version';
    const titleupload = _t('file') || 'File Upload';
    if (!isPro) {
      return `
        <span class="efb-editor-toolbar-sep"></span>
        <button type="button" class="efb-editor-btn efb-attach-btn efb-attach-disabled"
                id="efb_attach_btn" title="${proText}"
                onclick="pro_show_efb(1)">
          <i class="bi bi-paperclip"></i>
          <span class="efb-attach-pro-tag"><i class="bi bi-gem"></i></span>
        </button>`;
    }

    return `
      <span class="efb-editor-toolbar-sep"></span>
      <button type="button" class="efb-editor-btn efb-attach-btn" id="efb_attach_btn"
              title="${titleupload}" data-id="${msgId}">
        <i class="bi bi-paperclip"></i>
      </button>
      <input type="file" class="efb-upload-input" id="resp_file_efb_" name="file" data-id="${msgId}">`;
  }

  function buildFileUploadArea(msgId, isPanel) {
    if (typeof setting_emsFormBuilder !== 'undefined' &&
      setting_emsFormBuilder.hasOwnProperty('dsupfile') &&
      setting_emsFormBuilder.dsupfile == false &&
      typeof efb_var !== 'undefined' && !efb_var.hasOwnProperty('setting')) {
      return '';
    }

    let closeBtn = '';
    if (isPanel) {
      const isOpen = typeof stock_state_efb !== 'undefined' && stock_state_efb === true;
      closeBtn = `<button type="button" class="efb-close-resp-btn ${isOpen ? 'open-state' : ''}"
                    onclick="closed_resp_emsFormBuilder(${msgId})"
                    data-state="${isOpen ? 1 : 0}" id="respStateEfb" disabled>
                    ${isOpen ? _t('open') : _t('close')}
                  </button>`;
    }

    return `
    <div class="efb efb-upload-zone d-none" id="efb_upload_zone">
      <div class="efb efb-upload-file-info d-none p-1 px-2 my-1" id="efb_upload_file_info">
        <i class="bi bi-file-earmark"></i>
        <span class="efb-upload-file-name" id="efb_upload_file_name"></span>
        <button type="button" class="efb-upload-file-remove" id="efb_upload_file_remove" title="${_t('delete') || 'Remove'}">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="efb efb-upload-progress d-none" id="resp_file_efb-prG">
        <div class=" efb efb-upload-progress-bar d-none" id="resp_file_efb-prA">
          <div class="efb-upload-progress-fill" id="resp_file_efb-prB" role="progressbar" style="width:0%">0%</div>
        </div>
      </div>
    </div>
    ${closeBtn}`;
  }

  function initFileUpload(msgId) {
    const attachBtn = document.getElementById('efb_attach_btn');
    const fileInput = document.getElementById('resp_file_efb_');
    const uploadZone = document.getElementById('efb_upload_zone');
    const fileInfo = document.getElementById('efb_upload_file_info');
    const fileName = document.getElementById('efb_upload_file_name');
    const removeBtn = document.getElementById('efb_upload_file_remove');

    if (!attachBtn || !fileInput) return;

    if (attachBtn.dataset.efbBound) return;
    attachBtn.dataset.efbBound = '1';

    attachBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      fileInput.click();
    });

    fileInput.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        _handleFileSelected(this.files[0], msgId, uploadZone, fileInfo, fileName, attachBtn);
      }
    });

    const editor = document.getElementById('efb_rich_editor');
    if (editor) {
      editor.addEventListener('dragover', function (e) {
        e.preventDefault();
        editor.classList.add('efb-editor-dragover');
      });
      editor.addEventListener('dragleave', function () {
        editor.classList.remove('efb-editor-dragover');
      });
      editor.addEventListener('drop', function (e) {
        e.preventDefault();
        editor.classList.remove('efb-editor-dragover');
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
          fileInput.files = e.dataTransfer.files;
          _handleFileSelected(e.dataTransfer.files[0], msgId, uploadZone, fileInfo, fileName, attachBtn);
        }
      });
    }

    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        _handleFileRemoved(uploadZone, fileInfo, fileInput, attachBtn);
      });
    }
  }

  function _handleFileSelected(file, msgId, uploadZone, fileInfo, fileNameEl, attachBtn) {
    if (typeof validExtensions_efb_fun === 'function') {
      if (!validExtensions_efb_fun('allformat', file.type, 0)) {
        const m = _t('pleaseUploadA') || 'Please upload a valid file';
        if (typeof alert_message_efb === 'function') {
          alert_message_efb('', m.replace('NN', `${_t('media')}, ${_t('document')} ${_t('or')} ${_t('zip')}`), 4, 'danger');
        }
        return;
      }
    }

    if (uploadZone) uploadZone.classList.remove('d-none');

    if (attachBtn) attachBtn.classList.add('efb-attach-active');

    const prG = document.getElementById('resp_file_efb-prG');
    const prA = document.getElementById('resp_file_efb-prA');
    if (prG) prG.classList.remove('d-none');
    if (prA) { prA.classList.remove('d-none'); prA.classList.add('d-block'); }

    if (typeof window !== 'undefined') window.fileEfb = file;

    if (typeof files_emsFormBuilder !== 'undefined' && typeof sessionPub_emsFormBuilder !== 'undefined') {
      files_emsFormBuilder.push({
        id_: 'resp_file_efb',
        value: '@file@',
        state: 0,
        url: '',
        type: 'file',
        name: 'file',
        session: sessionPub_emsFormBuilder,
        amount: 0
      });

      const reader = new FileReader();
      reader.onload = function () {
        const idx = files_emsFormBuilder.findIndex(function (x) { return x.id_ === 'resp_file_efb'; });
        if (idx !== -1) files_emsFormBuilder[idx].url = reader.result;
      };
      reader.readAsDataURL(file);

      if (typeof fun_upload_file_api_emsFormBuilder === 'function') {
        fun_upload_file_api_emsFormBuilder('resp_file_efb', 'allformat', 'resp', file);
        _watchUploadProgress();
      }
    }
  }

  function _watchUploadProgress() {
    const prB = document.getElementById('resp_file_efb-prB');
    const prA = document.getElementById('resp_file_efb-prA');
    const prG = document.getElementById('resp_file_efb-prG');
    const fileInfo = document.getElementById('efb_upload_file_info');
    const fileNameEl = document.getElementById('efb_upload_file_name');
    const fileInput = document.getElementById('resp_file_efb_');
    if (!prB || !prA) return;

    let checks = 0;
    const maxChecks = 600;
    const interval = setInterval(function () {
      checks++;
      const width = parseFloat(prB.style.width);
      if (width >= 100 || checks >= maxChecks) {
        clearInterval(interval);
        setTimeout(function () {
          prA.classList.remove('d-block');
          prA.classList.add('d-none');
          if (prG) prG.classList.add('d-none');
          prB.style.width = '0%';
          prB.textContent = '0%';

          if (fileInfo && fileInput && fileInput.files && fileInput.files[0]) {
            const name = fileInput.files[0].name;
            if (fileNameEl) {
              fileNameEl.textContent = name.length > 30 ? name.slice(0, 27) + '...' : name;
              fileNameEl.title = name;
            }
            fileInfo.classList.remove('d-none');
            fileInfo.classList.add('d-block');
          }
        }, 2000);
      }
    }, 100);
  }

  function _handleFileRemoved(uploadZone, fileInfo, fileInput, attachBtn) {
    if (fileInfo) { fileInfo.classList.remove('d-block'); fileInfo.classList.add('d-none'); }

    const fileNameEl = document.getElementById('efb_upload_file_name');
    if (fileNameEl) { fileNameEl.textContent = ''; fileNameEl.title = ''; }

    if (fileInput) fileInput.value = '';

    if (uploadZone) uploadZone.classList.add('d-none');

    if (attachBtn) attachBtn.classList.remove('efb-attach-active');

    const prG = document.getElementById('resp_file_efb-prG');
    const prA = document.getElementById('resp_file_efb-prA');
    const prB = document.getElementById('resp_file_efb-prB');
    if (prG) prG.classList.add('d-none');
    if (prA) { prA.classList.remove('d-block'); prA.classList.add('d-none'); }
    if (prB) { prB.style.width = '0%'; prB.textContent = '0%'; }

    if (typeof files_emsFormBuilder !== 'undefined') {
      const idx = files_emsFormBuilder.findIndex(function (x) { return x.id_ === 'resp_file_efb'; });
      if (idx !== -1) {
        files_emsFormBuilder.splice(idx, 1);
      }
    }

    if (typeof sendBack_emsFormBuilder_pub !== 'undefined') {
      for (let i = sendBack_emsFormBuilder_pub.length - 1; i >= 0; i--) {
        if (sendBack_emsFormBuilder_pub[i].name === 'file') {
          sendBack_emsFormBuilder_pub.splice(i, 1);
        }
      }
    }

    if (typeof window !== 'undefined') window.fileEfb = null;
  }

  return {
    buildAdminResponseBody: buildAdminResponseBody,
    buildPublicResponseBody: buildPublicResponseBody,
    buildRichEditor: buildRichEditor,
    buildReplyActions: buildReplyActions,
    buildFileUploadArea: buildFileUploadArea,
    initAfterRender: initAfterRender,
    initRichEditor: initRichEditor,
    initFileUpload: initFileUpload,
    getEditorValue: getEditorValue,
    shortcodeToHtml: shortcodeToHtml,
    htmlToShortcode: htmlToShortcode,
    formatMessageForDisplay: formatMessageForDisplay,
    _handleFileRemoved: _handleFileRemoved
  };

})();

let _efbRespColorsApplied = false;
function efb_apply_resp_colors() {
  if (_efbRespColorsApplied) return;
  _efbRespColorsApplied = true;

  let s = null;
  try {
    if (typeof ajax_object_efm !== 'undefined' && ajax_object_efm.setting && ajax_object_efm.setting[0]) {
      const raw = ajax_object_efm.setting[0].setting;
      s = typeof raw === 'string' ? JSON.parse(raw.replace(/[\\]/g, '')) : raw;
    }
  } catch (e) {  }

  if (!s) {
    try {
      if (typeof ajax_object_efm !== 'undefined' && ajax_object_efm.respPrimary) {
        s = ajax_object_efm;
      }
    } catch (e) {  }
  }

  if (!s) return;

  const defaults = {
    respPrimary: '#3644d2',
    respPrimaryDark: '#202a8d',
    respAccent: '#ffc107',
    respText: '#1a1a2e',
    respTextMuted: '#657096',
    respBgCard: '#ffffff',
    respBgMeta: '#f6f7fb',
    respBgTrack: '#ffffff',
    respBgResp: '#f8f9fd',
    respBgEditor: '#ffffff',
    respEditorText: '#1a1a2e',
    respEditorPh: '#a0aec0',
    respBtnText: '#ffffff',
    respFontFamily: 'inherit',
    respFontSize: '0.9rem',
  };

  const map = {
    respPrimary: '--efb-resp-primary',
    respPrimaryDark: '--efb-resp-primary-dark',
    respAccent: '--efb-resp-accent',
    respText: '--efb-resp-text',
    respTextMuted: '--efb-resp-text-muted',
    respBgCard: '--efb-resp-bg-card',
    respBgMeta: '--efb-resp-bg-meta',
    respBgTrack: '--efb-resp-bg-track',
    respBgResp: '--efb-resp-bg-resp',
    respBgEditor: '--efb-resp-bg-editor',
    respEditorText: '--efb-resp-editor-text',
    respEditorPh: '--efb-resp-editor-ph',
    respBtnText: '--efb-resp-btn-text',
    respFontFamily: '--efb-resp-font-family',
    respFontSize: '--efb-resp-font-size',
  };

  const root = document.documentElement;

  const customFontRaw = s.respCustomFont || '';
  if (customFontRaw) {
    try {
      const cf = typeof customFontRaw === 'string' ? JSON.parse(customFontRaw) : customFontRaw;
      if (cf && cf.url) {
        let link = document.getElementById('efbCustomFontLink');
        if (!link) {
          link = document.createElement('link');
          link.id = 'efbCustomFontLink';
          link.rel = 'stylesheet';
          document.head.appendChild(link);
        }
        link.href = cf.url;
      }
    } catch (e) {  }
  }

  const fontFamilyVal = s.respFontFamily || '';
  if (fontFamilyVal && fontFamilyVal !== 'inherit' && !customFontRaw) {
    const builtinFontCssMap = {
      "Vazirmatn, Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100..900&display=swap",
      "Vazir, Tahoma, sans-serif": "https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@latest/dist/font-face.css",
      "Sahel, Tahoma, sans-serif": "https://cdn.jsdelivr.net/gh/rastikerdar/sahel-font@latest/dist/font-face.css",
      "Samim, Tahoma, sans-serif": "https://cdn.jsdelivr.net/gh/rastikerdar/samim-font@latest/dist/font-face.css",
      "'Shabnam', Tahoma, sans-serif": "https://cdn.jsdelivr.net/gh/rastikerdar/shabnam-font@latest/dist/font-face.css",
      "Parastoo, Tahoma, sans-serif": "https://cdn.jsdelivr.net/gh/rastikerdar/parastoo-font@latest/dist/font-face.css",
      "Gandom, Tahoma, sans-serif": "https://cdn.jsdelivr.net/gh/rastikerdar/gandom-font@latest/dist/font-face.css",
      "Lalezar, Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=Lalezar&display=swap",
      "Cairo, Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=Cairo:wght@200..1000&display=swap",
      "Tajawal, Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=Tajawal:wght@200;300;400;500;700;800;900&display=swap",
      "'Noto Sans Arabic', Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@100..900&display=swap",
      "'IBM Plex Sans Arabic', Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@100;200;300;400;500;600;700&display=swap",
      "Amiri, Tahoma, serif": "https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400;1,700&display=swap",
      "'Noto Kufi Arabic', Tahoma, sans-serif": "https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@100..900&display=swap",
      "'Inter', sans-serif": "https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap",
      "'Roboto', sans-serif": "https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700;900&display=swap",
      "'Open Sans', sans-serif": "https://fonts.googleapis.com/css2?family=Open+Sans:wght@300..800&display=swap",
    };
    const builtinUrl = builtinFontCssMap[fontFamilyVal];
    if (builtinUrl) {
      let bLink = document.getElementById('efbBuiltinFontLink');
      if (!bLink) {
        bLink = document.createElement('link');
        bLink.id = 'efbBuiltinFontLink';
        bLink.rel = 'stylesheet';
        document.head.appendChild(bLink);
      }
      bLink.href = builtinUrl;
    }
  }

  for (const [key, cssVar] of Object.entries(map)) {
    const val = s[key] || defaults[key];
    if (val && val !== defaults[key]) {
      root.style.setProperty(cssVar, val);
    }
  }

  const primary = s.respPrimary || defaults.respPrimary;
  if (primary !== defaults.respPrimary) {
    const hex2rgba = (hex, a) => {
      const r = parseInt(hex.slice(1, 3), 16);
      const g = parseInt(hex.slice(3, 5), 16);
      const b = parseInt(hex.slice(5, 7), 16);
      return `rgba(${r},${g},${b},${a})`;
    };
    root.style.setProperty('--efb-resp-primary-08', hex2rgba(primary, 0.08));
    root.style.setProperty('--efb-resp-primary-10', hex2rgba(primary, 0.10));
    root.style.setProperty('--efb-resp-primary-06', hex2rgba(primary, 0.06));
    root.style.setProperty('--efb-resp-border', hex2rgba(primary, 0.12));
    root.style.setProperty('--efb-resp-shadow', `0 2px 16px ${hex2rgba(primary, 0.07)}`);
    root.style.setProperty('--efb-resp-shadow-hover', `0 4px 24px ${hex2rgba(primary, 0.13)}`);
  }
}

function fun_emsFormBuilder_show_messages(content, by, userIp, track, date) {
  efb_apply_resp_colors();
  stock_state_efb=false;
  let totalpaid =0;
  if(content[(content.length)- 1].type=="w_link")content.pop();
  const ipSection = userIp!='' ? `<div class="efb-msg-meta-item"><i class="bi bi-globe2"></i><span class="efb-msg-meta-label">${efb_var.text.ip}:</span><span class="efb-msg-meta-val">${userIp}</span></div>` :''
  let byName = '';
  let byIsAdmin = false;
  if (by == 1) {
     byName = 'Admin'; byIsAdmin = true;
  } else if (by ==''  && (efb_var.hasOwnProperty('user_name') &&  efb_var.user_name.length > 1)){
    byName = efb_var.hasOwnProperty('user_name') &&  efb_var.user_name.length > 1 ? efb_var.user_name : efb_var.text.guest;
  }else if(by==-1){
    byName = 'Admin'; byIsAdmin = true;
  }else if (by==undefined ||by == 0 || by.length == 0 || by.length == -1) {
    byName = efb_var.text.guest;
  }else if(by=='#first'){
    byName = '';
  }else {
    byName = by;
   }
  const bySection = byName ? `<div class="efb-msg-sender">
    <div class="efb-msg-avatar ${byIsAdmin ? 'efb-msg-avatar--admin' : ''}"><i class="bi ${byIsAdmin ? 'bi-shield-check' : 'bi-person'}"></i></div>
    <div class="efb-msg-sender-info"><span class="efb-msg-sender-role">${byIsAdmin ? 'Admin' : efb_var.text.by}:</span><span class="efb-msg-sender-name">${byName}</span></div>
  </div>` : '';
  let m = `<div class="efb bg-response efb card-body my-2 py-2 efb-msg-card ${efb_var.rtl == 1 ? 'rtl-text' : ''}">
    <div class="efb efb-msg-header">
     ${bySection}
     <div class="efb-msg-header-actions">
       ${efb_var.hasOwnProperty('setting') || (typeof setting_emsFormBuilder !== 'undefined' && (setting_emsFormBuilder.activeDlBtn == true || setting_emsFormBuilder.activeDlBtn == '1' || setting_emsFormBuilder.activeDlBtn === 1)) ? `<div class="efb efb-msg-download" data-toggle="tooltip" data-placement="bottom" title="${efb_var.text.download}" onclick="generatePDF_EFB('resp_efb')"><i class="bi bi-download"></i></div>` : ''}
     </div>
    </div>
    <div class="efb-msg-meta-bar">
      ${ipSection}
      ${track != 0 ? `<div class="efb-msg-meta-item"><i class="bi bi-hash"></i><span class="efb-msg-meta-label">${efb_var.text.trackNo}:</span><span class="efb-msg-meta-val">${track}</span></div>` : ''}
      <div class="efb-msg-meta-item"><i class="bi bi-calendar3"></i><span class="efb-msg-meta-label">${efb_var.text.ddate}:</span><span class="efb-msg-meta-val">${date}</span></div>
    </div>
  <div class="efb-msg-divider"></div>
  <div class="efb-msg-fields">
  `;
  content.sort((a, b) => (Number(a.amount) > Number(b.amount)) ? 1 : -1);
  let list = []
  let s = false;
  let checboxs=[];
  let currency = content[0].hasOwnProperty('paymentcurrency') ? content[0].paymentcurrency :'usd';
  let last_type ='';
  for (const c of content) {
    if (c.hasOwnProperty('price')){ totalpaid +=Number(c.price)}
    if(c.hasOwnProperty('value') && c.type!="maps"){ c.value = replaceContentMessageEfb(c.value)}
    if(c.hasOwnProperty('qty')){ c.qty = replaceContentMessageEfb(c.qty)}
    if (c.hasOwnProperty('currency')){ currency = c.currency}
    s = false;
    let value = typeof(c.value)=="string" ? `<span class="efb-formatted">${(typeof EfbResponseViewer !== 'undefined' ? EfbResponseViewer.formatMessageForDisplay(c.value.toString().replaceAll('@efb!', ',').replace(/,\s*$/, '')) : c.value.toString().replaceAll('@efb!', ',').replace(/,\s*$/, ''))}</span>` :'';
    if(c.hasOwnProperty('qty')!=false) value+=`: <b> ${c.qty}</b>`
    if (c.value == "@file@" && list.findIndex(x => x == c.url) == -1) {
      s = true;
      list.push(c.url);
      $name = c.url.slice((c.url.lastIndexOf("/") + 1), (c.url.lastIndexOf(".")));
      if (c.type == "Image" || c.type == "image") {
        value = `<img src="${c.url}" alt="${c.name}" class="efb img-thumbnail m-1">`
      } else if (c.type == "Document" || c.type == "document" || c.type == "allformat") {
        value = `<a class="efb-reply-btn" href="${c.url}" target="_blank" >${c.url.split('/').pop()}</a>`
      } else if (c.type == "Media" || c.type == "media") {
        const audios = ['mp3', 'wav', 'ogg'];
        let media = "video";
        audios.forEach(function (aud) {
          if (c.url.indexOf(aud) !== -1) {
            media = 'audio';
          }
        })
        if (media == "video") {
          const len = c.url.length;
          const type = c.url.slice((len - c.url.lastIndexOf(x => x == ".")), len);
          value = type !== 'avi' ? `</br><div class="efb px-1"><video poster="${poster_emsFormBuilder}" src="${c.url}" type='video/${type}'controls></video></div><p class="efb text-center" ><a href="${c.url}">${efb_var.text.videoDownloadLink}</a></p>` : `<p class="efb text-center"><a href="${c.url}">${efb_var.text.downloadViedo}</a></p>`;
        } else {
          value = `<div ><audio controls><source src="${c.url}"></audio> </div>`;
        }
      } else {
        value = c.url.length > 1 ? `<a class="efb-reply-btn" href="${c.url}" target="_blank" >${c.url.split('/').pop()}</a>` : `<span class="efb  fs-5">💤</span>`
      }
    } else if (c.type == "esign") {
      let title = c.hasOwnProperty('name') ? c.name.toLowerCase() :'';
      title = efb_var.text[title] || c.name ;
      s = true;
      value = `<img src="${c.value}" alt="${c.name}" class="efb img-thumbnail efb-msg-esign-img">`;
      m += `<div class="efb efb-msg-field-row efb-msg-field-block"><span class="efb-msg-field-label"><i class="bi bi-pen"></i> ${title}:</span><div class="efb-msg-field-value"> ${value}</div></div>`;
    } else if (c.type == "color") {
      let title = c.hasOwnProperty('name') ? c.name.toLowerCase() :'';
      title = efb_var.text[title] || c.name ;
      s = true;
      value = `<span class="efb-msg-color-swatch" style="background-color:${c.value};">&nbsp;</span><code>${c.value}</code>`;
      m += `<div class="efb efb-msg-field-row"><span class="efb-msg-field-label"><i class="bi bi-palette"></i> ${title}:</span> <span class="efb-msg-field-value">${value}</span></div>`;
    } else if (c.type == "maps") {
      if (typeof (c.value) == "object") {
        s = true;
        value = maps_os_pro_efb(false, '', c.id_,'')
        marker_maps_efb = c.value;
        m += value;
        setTimeout(() => {
          if (typeof efbCreateMap === 'function') efbCreateMap(c.id_ ,c,true)
        }, 800);
      }
    } else if (c.type == "rating") {
      s = true;
      let title = c.hasOwnProperty('name') ? c.name.toLowerCase() :'';
      title = efb_var.text[title] || c.name ;
      value = `<div class='efb efb-msg-rating ${efb_var.rtl == 1 ? 'text-end' : 'text-start'}'>`;
      for (let i = 0; i < parseInt(c.value); i++) {
        value += `<i class="efb bi bi-star-fill"></i>`
      }
      value += "</div>";
      m += `<div class="efb efb-msg-field-row"><span class="efb-msg-field-label"><i class="bi bi-star"></i> ${title}:</span><span class="efb-msg-field-value">${value}</span></div>`;
    } else if (c.type=="checkbox" && checboxs.includes(c.id_)==false){
      s = true;
      let vc ='null';
      checboxs.push(c.id_);
      for(let op of content){
        if(op.type=="checkbox" && op.id_ == c.id_){
          vc=='null' ? vc =`<span class="efb-msg-checkbox-item"><i class="bi bi-check2-square"></i> ${op.value}</span>` :vc +=`<span class="efb-msg-checkbox-item"><i class="bi bi-check2-square"></i> ${op.value}</span>`
        }
      }
      m += `<div class="efb efb-msg-field-row efb-msg-field-block"><span class="efb-msg-field-label">${c.name}:</span><div class="efb-msg-checkbox-list">${vc}</div></div>`;
    }else if (c.type=="r_matrix"){
      s = true;
      vc =`${c.hasOwnProperty('label') && last_type!='r_matrix' ? `<div class="efb-msg-field-label efb-msg-matrix-header">${c.label}</div>` : '' }<div class="efb efb-msg-field-row efb-msg-matrix-item"><span class="efb-msg-field-label">${c.name}:</span><span class="efb-msg-field-value">${c.value}</span></div>`
      m += `${vc}`;
    }
    if (c.id_ == 'passwordRegisterEFB') { m += value; value = '**********' };
    if (((s == true && c.value == "@file@") || (s == false && c.value != "@file@")) && c.id_!="payment" && c.type!="checkbox"){
        let title = c.hasOwnProperty('name') ? c.name.toLowerCase() :'';
        if(title=="file") title ="atcfle"
        title = efb_var.text[title] || c.name ;
        let q =value !== '<b>@file@</b>' ? value : '';;
        if(c.type.includes('pay')  || c.type == 'prcfld') {
          const price = c.price ?? c.value ;
          q+=`<span class="efb efb-msg-price-tag">${Number(price).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span>`
        }else if(c.type.includes('checkbox')){
        }else if(c.type.includes('imgRadio')){
          q = typeof fun_imgRadio_efb === 'function' ? `<div class="efb w-25">`+fun_imgRadio_efb(c.id_, c.src ,c)+`</div>` : `<div class="efb w-25"><img src="${c.src || ''}" class="efb img-fluid rounded" alt="${c.value || ''}"></div>`
        }
        m += `<div class="efb efb-msg-field-row"><span class="efb-msg-field-label">${title}:</span> <span class="efb-msg-field-value">${text_nr_efb(q,1)}</span></div>`
      }
    if (c.type == "payment") {
      if(c.paymentGateway == "stripe" || c.paymentGateway == "paypal"){
        m += `<div class="efb efb-msg-payment">
            <div class="efb-msg-payment-header"><i class="bi bi-credit-card"></i> ${efb_var.text.payment}</div>
            <div class="efb-msg-payment-grid">
            <div class="efb-msg-payment-item"><span class="efb-msg-payment-label">${efb_var.text.payment} ${efb_var.text.id}</span><span class="efb-msg-payment-val efb-msg-payment-id">${c.paymentIntent}</span></div>
            <div class="efb-msg-payment-item"><span class="efb-msg-payment-label">${efb_var.text.payAmount}</span><span class="efb-msg-payment-val efb-msg-payment-amount">${Number(c.total).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></div>
            <div class="efb-msg-payment-item"><span class="efb-msg-payment-label">${efb_var.text.ddate}</span><span class="efb-msg-payment-val">${c.paymentCreated}</span></div>
            <div class="efb-msg-payment-item"><span class="efb-msg-payment-label">${efb_var.text.updated}</span><span class="efb-msg-payment-val">${c.updatetime}</span></div>
            <div class="efb-msg-payment-item"><span class="efb-msg-payment-label">${efb_var.text.methodPayment}</span><span class="efb-msg-payment-val"><span class="efb-msg-payment-badge">${c.paymentmethod}</span></span></div>
            ${c.paymentmethod != 'charge' ? `<div class="efb-msg-payment-item"><span class="efb-msg-payment-label">${efb_var.text.interval}</span><span class="efb-msg-payment-val text-capitalize">${c.interval}</span></div>` : ''}
            </div></div>`
      }else {
        m += `<div class="efb efb-msg-payment">
            <div class="efb-msg-payment-header"><i class="bi bi-credit-card"></i> ${efb_var.text.payment}</div>
            <div class="efb-msg-payment-grid">
            <div class="efb-msg-payment-item"><span class="efb-msg-payment-label">${efb_var.text.payment} ${efb_var.text.id}</span><span class="efb-msg-payment-val efb-msg-payment-id">${c.paymentIntent}</span></div>
            <div class="efb-msg-payment-item"><span class="efb-msg-payment-label">${efb_var.text.payAmount}</span><span class="efb-msg-payment-val efb-msg-payment-amount">${Number(c.total).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></div>
            <div class="efb-msg-payment-item"><span class="efb-msg-payment-label">${efb_var.text.methodPayment}</span><span class="efb-msg-payment-val"><span class="efb-msg-payment-badge">${c.paymentmethod}</span></span></div>
            <div class="efb-msg-payment-item"><span class="efb-msg-payment-label">${efb_var.text.ddate}</span><span class="efb-msg-payment-val">${c.paymentCreated}</span></div>
            ${c.paymentCard ? `<div class="efb-msg-payment-item"><span class="efb-msg-payment-label">${efb_var.text.cardNumber}</span><span class="efb-msg-payment-val">${c.paymentCard}</span></div>` : ''}
            ${c.refId ? `<div class="efb-msg-payment-item"><span class="efb-msg-payment-label">${efb_var.text.refCode || 'Reference Code'}</span><span class="efb-msg-payment-val">${c.refId}</span></div>` : ''}
            </div></div>`
      }
    }else if (c.type =="closed"){
      stock_state_efb=true;
    }else if (c.type =="opened"){
      stock_state_efb=false;
    }
    last_type = c.hasOwnProperty('type') ? c.type :'';
  }
  m += '</div>';
  if(totalpaid>0){
    m +=`<div class="efb efb-msg-total">
    <div class="efb-msg-total-inner"><span class="efb-msg-total-label"><i class="bi bi-calculator"></i> ${efb_var.text.ttlprc}:</span><span class="efb-msg-total-amount">${Number(totalpaid).toLocaleString(lan_name_emsFormBuilder, { style: 'currency', currency: currency })}</span></div>
    </div>`
  }
  m += '</div>';
  return m;
}

function state_rply_btn_efb(t){
    if(pro_efb ==false){return};
   setTimeout(() => {
     if(stock_state_efb==true){
       let d= document.getElementById('respStateEfb');
       if(d){
          d.disabled=false;
         d.classList.remove('btn-outline-pink');
         d.classList.contains('btn-outline-pink') ? 0 : d.classList.remove('btn-outline-pink');
         d.classList.contains('btn-outline-success') ? 0 : d.classList.add('btn-outline-success');
         d.innerHTML =  efb_var.text.open;
         d.dataset.state ="1";
       }
       document.getElementById("replayB_emsFormBuilder").remove();
       if(document.getElementById("attach_efb")) document.getElementById("attach_efb").remove();
       document.getElementById("replayM_emsFormBuilder").remove();
       document.getElementById("label_replyM_efb").remove();
       if(document.getElementById("efb_editor_toolbar")) document.getElementById("efb_editor_toolbar").remove();
       if(document.getElementById("efb_rich_editor")) document.getElementById("efb_rich_editor").remove();
       document.getElementById("replay_state__emsFormBuilder").innerHTML=`<h5 class="efb fs-4 my-3 text-center text-pinkEfb">${efb_var.text.clsdrspn}</h5>`
      }else{
        let d= document.getElementById('respStateEfb');
        if(d){
          d.disabled=false;
          d.classList.contains('btn-outline-success') ? 0 : d.classList.remove('btn-outline-success');
          d.classList.contains('btn-outline-pink') ? 0 : d.classList.add('btn-outline-pink');
          d.innerHTML =  efb_var.text.close;
          d.dataset.state ="0";
        }
      }
   }, t);
}

function check_msg_ext_resp_efb() {
  const replayM_emsFormBuilder = document.querySelector("#replayM_emsFormBuilder");
  replayM_emsFormBuilder.addEventListener("keypress", (event) => {
    if (document.querySelector("#replayB_emsFormBuilder").classList.contains("disabled")) {
      document.querySelector("#replayB_emsFormBuilder").classList.remove("disabled");
    }
    if (event.which === 13) {
      event.preventDefault();
    }
  });
}

function checkBrowserSupport_efb() {
  return {
    templateLiterals: (function() {
      try {
        eval('`test`');
        return true;
      } catch (e) {
        return false;
      }
    })(),
    urlConstructor: typeof URL !== 'undefined',
    popupAllowed: true,
    isMobile: (function() {
      return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
             (navigator.maxTouchPoints && navigator.maxTouchPoints > 2 && /MacIntel/.test(navigator.platform));
    })(),
    isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent),
    isAndroid: /Android/.test(navigator.userAgent),
    touchSupport: 'ontouchstart' in window || navigator.maxTouchPoints > 0
  };
}

function checkGoogleFontsAccess_efb(callback) {
  var testLink = document.createElement('link');
  testLink.rel = 'stylesheet';
  testLink.href = 'https://fonts.googleapis.com/css2?family=Open+Sans&display=swap';
  testLink.style.position = 'absolute';
  testLink.style.left = '-9999px';

  var timeoutId = setTimeout(function() {
    callback(false);
  }, 3000);

  testLink.onload = function() {
    clearTimeout(timeoutId);
    callback(true);
  };

  testLink.onerror = function() {
    clearTimeout(timeoutId);
    callback(false);
  };

  document.head.appendChild(testLink);
}

function getFallbackFont_efb(locale) {
  var fallbackFonts = {
    'am': 'serif',
    'ar': 'Tahoma, Arial, sans-serif',
    'fa_IR': 'Tahoma, Arial, sans-serif',
    'arq': 'Arial, sans-serif',
    'az_TR': 'Arial, sans-serif',
    'bn_BD': 'Arial Unicode MS, sans-serif',
    'cs_CZ': 'Arial, sans-serif',
    'hat': 'Times New Roman, serif',
    'he_IL': 'David, Arial, sans-serif',
    'hr': 'Arial, sans-serif',
    'hy': 'Arial, sans-serif',
    'id_ID': 'Arial, sans-serif',
    'ja': 'MS Gothic, sans-serif',
    'ka_GE': 'Arial, sans-serif',
    'km': 'Arial Unicode MS, sans-serif',
    'ko_KR': 'Malgun Gothic, sans-serif',
    'lt_LT': 'Arial, sans-serif',
    'ml_IN': 'Arial Unicode MS, sans-serif',
    'ms_MY': 'Arial, sans-serif',
    'ne_NP': 'Arial Unicode MS, sans-serif',
    'ru_RU': 'Arial, sans-serif',
    'sw': 'Arial, sans-serif',
    'th': 'Tahoma, Arial, sans-serif',
    'ur': 'Tahoma, Arial, sans-serif',
    'uz_UZ': 'Arial, sans-serif',
    'vi': 'Arial, sans-serif',
    'zh_CN': 'SimSun, serif',
    'zh_HK': 'SimSun, serif',
    'zh_TW': 'SimSun, serif'
  };

  return fallbackFonts[locale] || 'Arial, sans-serif';
}

function generatePDF_EFB(id)
{
  var browserSupport = checkBrowserSupport_efb();
  var isRtl = efb_var.rtl == 1;
  var direction = isRtl ? 'rtl' : 'ltr';
  var textAlign = isRtl ? 'right' : 'left';
  var fonts_name = [
    {loc:'am', font:'Noto Serif Ethiopic'},
    {loc:'ar', font:'Noto Sans Arabic'},
    {loc:'fa_IR', font:'Noto Sans Arabic'},
    {loc:'arq', font:'Alegreya Sans SC'},
    {loc:'az_TR', font:'Noto Sans'},
    {loc:'bn_BD', font:'Noto Sans Bengali'},
    {loc:'cs_CZ', font:'Signika'},
    {loc:'hat', font:'Tinos'},
    {loc:'he_IL', font:'Noto Sans Hebrew'},
    {loc:'hr', font:'Noto Sans'},
    {loc:'hy', font:'Noto Sans Armenian'},
    {loc:'id_ID', font:'Noto Sans'},
    {loc:'ja', font:'Noto Sans JP'},
    {loc:'ka_GE', font:'Noto Sans Georgian'},
    {loc:'km', font:'Noto Sans Khmer'},
    {loc:'ko_KR', font:'Noto Sans KR'},
    {loc:'lt_LT', font:'Noto Sans'},
    {loc:'ml_IN', font:'Noto Sans'},
    {loc:'ms_MY', font:'Noto Sans'},
    {loc:'ne_NP', font:'Noto Sans'},
    {loc:'ru_RU', font:'Noto Sans'},
    {loc:'sw', font:'Noto Sans'},
    {loc:'th', font:'Noto Sans Thai'},
    {loc:'ur', font:'Noto Nastaliq Urdu'},
    {loc:'uz_UZ', font:'Noto Sans'},
    {loc:'vi', font:'Noto Sans'},
    {loc:'zh_CN', font:'Noto Sans SC'},
    {loc:'zh_HK', font:'Noto Sans HK'},
    {loc:'zh_TW', font:'Noto Sans TC'}
  ];

  var indx = fonts_name.findIndex(function(x) { return x.loc === efb_var.wp_lan; });
  var googleFontName = indx !== -1 ? fonts_name[indx].font : 'Noto Sans';
  var fallbackFontName = getFallbackFont_efb(efb_var.wp_lan);

  var divPrint = document.getElementById(id);
  if (!divPrint) {
    return;
  }

  function collectCssVars_efb() {
    var root = document.documentElement;
    var cs = getComputedStyle(root);
    var vars = [
      '--efb-resp-primary','--efb-resp-primary-dark','--efb-resp-accent',
      '--efb-resp-text','--efb-resp-text-muted','--efb-resp-bg-card',
      '--efb-resp-bg-meta','--efb-resp-bg-track','--efb-resp-bg-resp',
      '--efb-resp-bg-editor','--efb-resp-editor-text','--efb-resp-editor-ph',
      '--efb-resp-btn-text','--efb-resp-font-family','--efb-resp-font-size',
      '--efb-resp-border','--efb-resp-primary-08','--efb-resp-primary-15',
      '--efb-resp-primary-25'
    ];
    var result = ':root{';
    for (var i = 0; i < vars.length; i++) {
      var val = cs.getPropertyValue(vars[i]);
      if (val && val.trim()) result += vars[i] + ':' + val.trim() + ';';
    }
    result += '}';
    return result;
  }

  function buildPrintCSS_efb(useGoogleFonts, fontName, fallbackFont) {
    var googleFontLink = useGoogleFonts
      ? '<link href="https://fonts.googleapis.com/css2?family=' + fontName.replace(/ /g, '+') + ':wght@400;600;700&display=swap" rel="stylesheet">'
      : '';
    var fontFamily = useGoogleFonts
      ? "'" + fontName + "', " + fallbackFont
      : fallbackFont;

    var cssVars = collectCssVars_efb();

    var css = cssVars + '\n' +
      'html,body{margin:0;padding:0;font-family:' + fontFamily + ';font-size:14px;line-height:1.6;color:#333;background:#fff;direction:' + direction + ';text-align:' + textAlign + ';}' +
      '*,*::before,*::after{box-sizing:border-box;}' +
      'img{max-width:100%;height:auto;}' +
      'a{color:#0066cc;text-decoration:underline;}' +
      '.efb-pdf-header{text-align:center;padding:18px 10px 12px;border-bottom:2px solid #e8e8e8;margin-bottom:12px;}' +
      '.efb-pdf-header h2{margin:4px 0;font-size:1.1rem;font-weight:600;color:#555;}' +
      '.efb-pdf-header a{color:var(--efb-resp-primary,#4361ee);text-decoration:none;font-weight:700;}' +
      '.efb-pdf-footer{text-align:center;padding:10px;margin-top:16px;border-top:1px solid #eee;font-size:0.75rem;color:#999;}' +
      '.efb-msg-card{padding:12px 16px;margin:10px 0;border-radius:12px;background:var(--efb-resp-bg-card,#fff);border:1px solid var(--efb-resp-border,#e5e7eb);}' +
      '.efb-msg-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:8px;}' +
      '.efb-msg-sender{display:flex;align-items:center;gap:8px;}' +
      '.efb-msg-avatar{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;background:var(--efb-resp-bg-meta,#f1f3f5);color:var(--efb-resp-text-muted,#6b7280);}' +
      '.efb-msg-avatar--admin{background:var(--efb-resp-primary-15,rgba(67,97,238,0.15));color:var(--efb-resp-primary,#4361ee);}' +
      '.efb-msg-sender-info{display:flex;flex-direction:column;}' +
      '.efb-msg-sender-role{font-size:0.7rem;color:var(--efb-resp-text-muted,#6b7280);text-transform:uppercase;letter-spacing:0.4px;}' +
      '.efb-msg-sender-name{font-weight:700;font-size:0.9rem;color:var(--efb-resp-text,#1a1a2e);}' +
      '.efb-msg-header-actions{display:none;}' +
      '.efb-msg-download{display:none !important;}' +
      '.efb-msg-meta-bar{display:flex;flex-wrap:wrap;gap:12px;padding:6px 10px;border-radius:8px;background:var(--efb-resp-bg-meta,#f8f9fa);margin-bottom:8px;font-size:0.82rem;}' +
      '.efb-msg-meta-item{display:flex;align-items:center;gap:4px;}' +
      '.efb-msg-meta-label{color:var(--efb-resp-text-muted,#6b7280);font-weight:600;}' +
      '.efb-msg-meta-val{color:var(--efb-resp-text,#333);font-weight:500;}' +
      '.efb-msg-divider{border:none;border-top:1px solid var(--efb-resp-border,#e5e7eb);margin:8px 0;}' +
      '.efb-msg-fields{display:flex;flex-direction:column;gap:4px;}' +
      '.efb-msg-field-row{display:flex;align-items:baseline;gap:6px;padding:4px 0;flex-wrap:wrap;}' +
      '.efb-msg-field-block{flex-direction:column;gap:2px;}' +
      '.efb-msg-field-label{font-weight:600;color:var(--efb-resp-text-muted,#6b7280);font-size:0.85rem;min-width:100px;white-space:nowrap;}' +
      '.efb-msg-field-value{color:var(--efb-resp-text,#333);font-size:0.9rem;word-break:break-word;}' +
      '.efb-msg-checkbox-list{display:flex;flex-wrap:wrap;gap:6px;}' +
      '.efb-msg-checkbox-item{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:6px;background:var(--efb-resp-primary-08,rgba(67,97,238,0.08));font-size:0.85rem;color:var(--efb-resp-text,#333);}' +
      '.efb-msg-rating .bi-star-fill{color:var(--efb-resp-accent,#f7b731);font-size:1rem;margin:0 1px;}' +
      '.efb-msg-color-swatch{display:inline-block;width:20px;height:20px;border-radius:4px;border:1px solid #ccc;vertical-align:middle;margin-inline-end:4px;}' +
      '.efb-msg-esign-img{max-width:220px;border-radius:6px;border:1px solid var(--efb-resp-border,#e5e7eb);}' +
      '.efb-msg-price-tag{display:inline-block;padding:2px 8px;border-radius:4px;background:var(--efb-resp-primary-08,rgba(67,97,238,0.08));color:var(--efb-resp-primary-dark,#3a0ca3);font-weight:700;font-size:0.85rem;margin-inline-start:4px;}' +
      '.efb-msg-payment{margin:10px 0;border-radius:12px;overflow:hidden;border:1px solid var(--efb-resp-border,#e5e7eb);background:var(--efb-resp-bg-card,#fff);}' +
      '.efb-msg-payment-header{display:flex;align-items:center;gap:8px;padding:10px 14px;background:linear-gradient(135deg,var(--efb-resp-text,#1a1a2e) 0%,var(--efb-resp-primary-dark,#3a0ca3) 100%);color:#fff;font-weight:700;font-size:0.9rem;letter-spacing:0.3px;}' +
      '.efb-msg-payment-header i{font-size:1.1rem;color:var(--efb-resp-accent,#f7b731);}' +
      '.efb-msg-payment-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;}' +
      '.efb-msg-payment-item{display:flex;flex-direction:column;padding:8px 14px;border-bottom:1px solid var(--efb-resp-border,#e5e7eb);border-inline-end:1px solid var(--efb-resp-border,#e5e7eb);}' +
      '.efb-msg-payment-item:nth-child(even){border-inline-end:none;}' +
      '.efb-msg-payment-item:nth-last-child(-n+2){border-bottom:none;}' +
      '.efb-msg-payment-label{font-size:0.72rem;font-weight:600;color:var(--efb-resp-text-muted,#6b7280);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px;}' +
      '.efb-msg-payment-val{font-size:0.88rem;color:var(--efb-resp-text,#333);font-weight:500;word-break:break-all;}' +
      '.efb-msg-payment-amount{font-weight:700;color:var(--efb-resp-primary-dark,#3a0ca3);font-size:1rem;}' +
      '.efb-msg-payment-id{font-family:"Courier New",Courier,monospace;font-size:0.78rem;color:#555;}' +
      '.efb-msg-payment-badge{display:inline-block;padding:2px 8px;border-radius:4px;background:var(--efb-resp-primary-08,rgba(67,97,238,0.08));color:var(--efb-resp-primary,#4361ee);font-size:0.78rem;font-weight:600;text-transform:capitalize;}' +
      '.efb-msg-total{margin-top:10px;border-radius:10px;overflow:hidden;background:linear-gradient(135deg,var(--efb-resp-text,#1a1a2e) 0%,var(--efb-resp-primary-dark,#3a0ca3) 100%);}' +
      '.efb-msg-total-inner{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;gap:12px;}' +
      '.efb-msg-total-label{color:rgba(255,255,255,0.85);font-weight:600;font-size:0.88rem;display:flex;align-items:center;gap:6px;}' +
      '.efb-msg-total-amount{color:var(--efb-resp-accent,#f7b731);font-weight:800;font-size:1.15rem;letter-spacing:0.5px;}' +
      '.efb-msg-matrix-header{font-weight:700;font-size:0.95rem;margin:6px 0 2px;color:var(--efb-resp-text,#333);padding-bottom:2px;border-bottom:1px solid var(--efb-resp-border,#e5e7eb);}' +
      '.efb-msg-matrix-item{padding:2px 0 2px 12px;}' +
      '.img-thumbnail{border:1px solid #dee2e6;border-radius:6px;padding:4px;max-width:200px;}' +
      (isRtl ?
        '.efb-msg-header{flex-direction:row-reverse;}' +
        '.efb-msg-sender{flex-direction:row-reverse;}' +
        '.efb-msg-meta-bar{flex-direction:row-reverse;}' +
        '.efb-msg-meta-item{flex-direction:row-reverse;}' +
        '.efb-msg-field-row{flex-direction:row-reverse;text-align:right;}' +
        '.efb-msg-field-label{text-align:right;}' +
        '.efb-msg-field-value{text-align:right;}' +
        '.efb-msg-checkbox-list{flex-direction:row-reverse;}' +
        '.efb-msg-checkbox-item{flex-direction:row-reverse;}' +
        '.efb-msg-payment-header{flex-direction:row-reverse;}' +
        '.efb-msg-payment-item{text-align:right;}' +
        '.efb-msg-total-inner{flex-direction:row-reverse;}' +
        '.efb-msg-total-label{flex-direction:row-reverse;}' +
        '.efb-msg-rating{text-align:right;}'
        : '') +
      '@media screen and (max-width:768px){' +
        'body{font-size:13px!important;}' +
        '.efb-msg-payment-grid{grid-template-columns:1fr;}' +
        '.efb-msg-payment-item{border-inline-end:none;}' +
        '.efb-msg-payment-item:nth-last-child(-n+2){border-bottom:1px solid var(--efb-resp-border,#e5e7eb);}' +
        '.efb-msg-payment-item:last-child{border-bottom:none;}' +
        '.efb-msg-field-row{flex-direction:column;gap:1px;}' +
      '}' +
      '@media print{' +
        'body{font-size:11pt!important;background:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
        '.efb-msg-card{box-shadow:none!important;border:1px solid #ddd!important;break-inside:avoid;}' +
        '.efb-msg-payment{break-inside:avoid;}' +
        '.efb-msg-total{break-inside:avoid;}' +
        '.efb-pdf-header{border-bottom-color:#ccc;}' +
        'a[href]:after{content:" (" attr(href) ")";font-size:0.75em;color:#888;}' +
        '.efb-msg-download,.efb-msg-header-actions{display:none!important;}' +
      '}';

    return '<!DOCTYPE html><html lang="' + (efb_var.wp_lan || 'en') + '" dir="' + direction + '"><head>' +
      '<meta charset="utf-8">' +
      '<meta name="viewport" content="width=device-width,initial-scale=1.0">' +
      googleFontLink +
      '<style>' + css + '</style></head>';
  }

  function processLinksForPDF_efb(element) {
    var clonedElement = element.cloneNode(true);
    var actions = clonedElement.querySelectorAll('.efb-msg-download, .efb-msg-header-actions');
    for (var a = 0; a < actions.length; a++) {
      actions[a].parentNode.removeChild(actions[a]);
    }
    var elementsWithHref = clonedElement.querySelectorAll
      ? clonedElement.querySelectorAll('[href]')
      : clonedElement.getElementsByTagName('a');
    for (var i = 0; i < elementsWithHref.length; i++) {
      var el = elementsWithHref[i];
      if (el.href &&
          el.href.indexOf('http') !== 0 &&
          el.href.indexOf('mailto') !== 0 &&
          el.href.indexOf('tel') !== 0) {
        if (browserSupport.urlConstructor) {
          try { el.href = new URL(el.href, window.location.origin).href; } catch (e) {  }
        } else if (el.href.indexOf('/') === 0) {
          el.href = window.location.protocol + '//' + window.location.host + el.href;
        }
      }
      if (!el.title && el.href) el.title = el.href;
      if (el.href && (el.href.indexOf('http') === 0 || el.href.indexOf('mailto') === 0 || el.href.indexOf('tel') === 0)) {
        el.target = '_blank';
        el.rel = 'noopener noreferrer';
      }
    }
    return clonedElement.innerHTML;
  }

  function generatePDFContent_efb(useGoogleFonts) {
    var processedContent = processLinksForPDF_efb(divPrint);
    var headMarkup = buildPrintCSS_efb(useGoogleFonts, googleFontName, fallbackFontName);
    var websiteUrl = window.location.protocol + '//' + window.location.hostname;
    var headerHtml = '<div class="efb-pdf-header">';
    headerHtml += '<h2><a href="' + websiteUrl + '" target="_blank">' + window.location.hostname + '</a></h2>';
    const efb_link = efb_var.wp_lan === 'fa_IR' ? 'https://easyformbuilder.ir' : 'https://whitestudio.team/';
    if (efb_var.pro !== 1) {
      headerHtml += '<h2>' + efb_var.text.createdBy + ' <a href="' + efb_link + '" target="_blank">' + efb_var.text.easyFormBuilder + '</a></h2>';
    }
    headerHtml += '</div>';
    var footerHtml = '<div class="efb-pdf-footer">' +
      (efb_var.text.createdBy || 'Created by') + ' ' + (efb_var.text.easyFormBuilder || 'Easy Form Builder') +
      ' &mdash; ' + new Date().toLocaleDateString((efb_var.wp_lan || 'en').replace(/_/g, '-'), { year:'numeric', month:'long', day:'numeric' }) +
      '</div>';
    return headMarkup +
      '<title>' + (efb_var.text.download || 'Download') + ' - ' + window.location.hostname + '</title>' +
      '<body onload="winprint()">' +
      '<script>' +
        'function winprint(){setTimeout(function(){if(window.print)window.print();},200);}' +
      '<\/script>' +
      headerHtml +
      '<div class="efb-pdf-content">' + processedContent + '</div>' +
      footerHtml +
      '</body></html>';
  }

  function openPrintWindow_efb(content) {
    var printWindow = null;
    if (browserSupport.isMobile) {
      if (browserSupport.isIOS) {
        var printContent = content.replace(
          'function winprint(){',
          'function winprint(){if(typeof window.print==="undefined"){alert("' +
            (efb_var.text.download || 'Please use Share > Print') +
            '");return;}'
        );
        try {
          printWindow = window.open('', '_blank', 'width=device-width,initial-scale=1');
          if (printWindow) { printWindow.document.open(); printWindow.document.write(printContent); printWindow.document.close(); return; }
        } catch (e) {  }
      } else if (browserSupport.isAndroid) {
        try {
          printWindow = window.open('', 'Print-Window-EFB', 'width=device-width,initial-scale=1,user-scalable=yes');
          if (printWindow) {
            printWindow.document.open(); printWindow.document.write(content); printWindow.document.close();
            setTimeout(function() { if (printWindow && !printWindow.closed) printWindow.focus(); }, 500);
            return;
          }
        } catch (e) {  }
      }
      try {
        var blob = new Blob([content], { type: 'text/html' });
        var url = window.URL.createObjectURL(blob);
        printWindow = window.open(url, '_blank');
        if (printWindow) { setTimeout(function() { window.URL.revokeObjectURL(url); }, 5000); return; }
      } catch (e) {  }
      if (confirm(efb_var.text.download || 'Open in new tab for printing?')) {
        var newWindow = window.open('about:blank', '_blank');
        if (newWindow) { newWindow.document.write(content); newWindow.document.close(); }
      }
      return;
    }
    try {
      printWindow = window.open('', 'Print-Window-EFB', 'width=800,height=600,scrollbars=yes,resizable=yes');
      if (!printWindow) throw new Error('Popup blocked');
      printWindow.document.open();
      printWindow.document.write(content);
      setTimeout(function() { if (printWindow && !printWindow.closed) printWindow.document.close(); }, 100);
    } catch (e) {
      if (confirm(efb_var.text.download || 'Popup blocked. Try again?')) {
        setTimeout(function() { generatePDF_EFB(id); }, 1000);
      } else {
        var iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;';
        document.body.appendChild(iframe);
        iframe.contentDocument.open();
        iframe.contentDocument.write(content);
        iframe.contentDocument.close();
        setTimeout(function() {
          if (iframe.contentWindow && iframe.contentWindow.print) iframe.contentWindow.print();
          document.body.removeChild(iframe);
        }, 500);
      }
    }
  }

  checkGoogleFontsAccess_efb(function(hasGoogleFontsAccess) {
    var content = generatePDFContent_efb(hasGoogleFontsAccess);
    openPrintWindow_efb(content);
  });
}

function fun_tracking_show_emsFormBuilder() {
  const time = pro_efb==true ? 10 :900;
  const getUrlparams = new URLSearchParams(location.search);
  let get_track = getUrlparams.get('track') !=null ? sanitize_text_efb(getUrlparams.get('track')) :null;
  if(get_track){ get_track= `value="${get_track}"`; change_url_back_persia_pay_efb()}else{get_track='';}
  setTimeout(() => {
    if (typeof efb_apply_resp_colors === 'function') efb_apply_resp_colors();
    document.getElementById("body_tracker_emsFormBuilder").innerHTML = `
    <div class="efb  ${ajax_object_efm.rtl == 1 ? 'rtl-text' : ''}" >
                  <div class="efb efb-tracker-card" id="body_efb-track" data-formid="0">
                      <div class="efb efb-tracker-icon-wrap">
                        <i class="efb bi-shield-check efb-tracker-icon-circle"></i>
                      </div>
                      <h4 class="efb efb-tracker-title">${ajax_object_efm.text.pleaseEnterTheTracking}</h4>
                      <p class="efb efb-tracker-subtitle">${ajax_object_efm.text.trackingCode}</p>
                      <div class="efb efb-tracker-input-group">
                        <i class="efb bi-hash efb-tracker-input-icon"></i>
                        <input type="text" class="efb input-efb efb-tracker-input" placeholder="${ajax_object_efm.text.entrTrkngNo}" id="trackingCodeEfb" ${get_track} autocomplete="off" spellcheck="false">
                      </div>
                      ${setting_emsFormBuilder.scaptcha==true ? `<div class="efb efb-tracker-captcha"><div id="gRecaptcha" class="efb g-recaptcha" data-sitekey="${setting_emsFormBuilder.siteKey}" data-callback="verifyCaptcha"></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ``}
                      <button type="submit" class="efb btn btn-pinkEfb efb-tracker-btn" id="vaid_check_emsFormBuilder" onclick="fun_vaid_tracker_check_emsFormBuilder()">
                        <i class="efb bi-search efb-tracker-btn-icon"></i> ${ajax_object_efm.text.search}
                      </button>
                  </div>
              <!-- efb -->
          </div>
          <div id="alert_efb" class="efb mx-5"></div>
  `
    if(setting_emsFormBuilder.scaptcha==true ){
      sitekye_emsFormBuilder=setting_emsFormBuilder.siteKey;
      loadCaptcha_efb(20);
    }
  }, time);
}

function fun_get_tracking_code(){
}

function fun_vaid_tracker_check_emsFormBuilder() {
  if (!navigator.onLine) {
    noti_message_efb_v4(efb_var.text.offlineSend , 'danger' , `body_efb-track`,0 );
    return;
  }
  const innrBtn = document.getElementById('vaid_check_emsFormBuilder').innerHTML;
  document.getElementById('vaid_check_emsFormBuilder').innerHTML = `<i class="efb fs-5 bi-hourglass-split"></i>`
  document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
  el = document.getElementById('trackingCodeEfb').value;
  if (el.length < 5) {
    document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn
    document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
    noti_message_efb_v4(ajax_object_efm.text.trackingCodeIsNotValid, 'danger' ,'body_efb-track',0)
  } else {
    if (currentTab_emsFormBuilder == 0) {
        const captcha = sendBack_emsFormBuilder_pub.filter(x=>Number(x.form_id)==-1 && x.id_=='captcha_v2');
        recaptcha_emsFormBuilder = '';
        if(captcha.length>0){
            recaptcha_emsFormBuilder=captcha.length>0 ? captcha[0].value : '';
        }else{
          let captacha_exists = document.querySelector('#gRecaptcha[data-formid="-1"]');
          if (captacha_exists) {
            document.getElementById('vaid_check_emsFormBuilder').innerHTML = innrBtn
            document.getElementById('vaid_check_emsFormBuilder').classList.toggle('disabled')
            noti_message_efb_v4(ajax_object_efm.text.checkedBoxIANotRobot, 'danger' ,'body_efb-track',0)
            return;
          }
        }
        sessionStorage.setItem('track', el);
        data = {
          action: "get_track_Emsfb",
          value: el,
          name: formNameEfb,
          valid: recaptcha_emsFormBuilder,
          nonce: (typeof _efb_core_nonce_ !== 'undefined' && _efb_core_nonce_) ? _efb_core_nonce_ : ajax_object_efm.nonce,
          sid:efb_var.sid,
          id: 0,
        };
        post_api_tracker_check_efb(data,innrBtn);
    }
  }
}

function emsFormBuilder_show_content_message(value, content) {
  const body = EfbResponseViewer.buildPublicResponseBody(value, content);
  setTimeout(() => {
    EfbResponseViewer.initAfterRender(value.msg_id, false);
  }, 50);
  return body;
}

function fun_send_replayMessage_emsFormBuilder(id) {
  document.getElementById('replayB_emsFormBuilder').classList.add('disabled');
  document.getElementById('replayB_emsFormBuilder').innerHTML =`<i class="efb fs-5 bi-hourglass-split mx-1"></i>`+efb_var.text.sending;
  setTimeout(() => {
    let message = EfbResponseViewer.getEditorValue();
    message=sanitize_text_efb(message);
    const by = ajax_object_efm.user_name.length > 1 ? ajax_object_efm.user_name : efb_var.text.guest;
    const ob = [{id_:'message', name:'message', type:'text', amount:0, value: message, by: by , session: sessionPub_emsFormBuilder,form_id:-1}];
    fun_sendBack_emsFormBuilder(ob[0])
    if (message.length < 1 ) {
      check_msg_ext_resp_efb();
      document.getElementById('replay_state__emsFormBuilder').innerHTML = `<p class="efb fs-6"><i class="efb bi-exclamation-triangle-fill nmsgefb"></i> ${efb_var.text.error}: ${efb_var.text.pleaseEnterVaildValue}</p>`;
      document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
       document.getElementById('replayB_emsFormBuilder').innerHTML = efb_var.text.reply;
      return;
    } else {
      if(setting_emsFormBuilder.hasOwnProperty('dsupfile')==true && setting_emsFormBuilder.dsupfile !=true) {
        for(const s in sendBack_emsFormBuilder_pub ){ if(sendBack_emsFormBuilder_pub[s].name=="file") sendBack_emsFormBuilder_pub.splice(s,1)  }
      }
      let messages = sendBack_emsFormBuilder_pub.filter(x=>(Number(x.form_id)==-1 || x.id_=='resp_file_efb') && x.id_!='captcha_v2');
      fun_send_replayMessage_reast_emsFormBuilder(messages);
    }
  }, 100);
}

function fun_send_replayMessage_reast_emsFormBuilder(message) {
  if (!navigator.onLine) {
    noti_message_efb_v4(efb_var.text.offlineSend , 'danger' , `replay_state__emsFormBuilder`,0 );
    return;
  }
  f_btn =()=>{
    document.getElementById('replay_state__emsFormBuilder').innerHTML = efb_var.text.enterYourMessage;
    document.getElementById('replayM_emsFormBuilder').value = "";
    var _re = document.getElementById('efb_rich_editor'); if (_re) _re.innerHTML = '';
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
  }
  if (message.length < 1) {
    f_btn();
    return;
  }
  const track = sessionStorage.getItem('track') ?? 'null';
  const is_user_track = (ajax_object_efm && ajax_object_efm.is_user === 'admin') ? 'admin' : 'user';
  const efb_sc = (ajax_object_efm && ajax_object_efm.admin_sc) ? ajax_object_efm.admin_sc : (sessionStorage.getItem('efb_sc') || '');
  data = {
    action: "set_rMessage_id_Emsfb",
    type: "POST",
    id: efb_var.msg_id,
    valid: recaptcha_emsFormBuilder,
    message: JSON.stringify(message),
    type: form_type_emsFormBuilder,
    sid:efb_var.sid,
    user_type : is_user_track,
    sc: efb_sc,
    page_id: ajax_object_efm.page_id,
    track: track,
  };
  post_api_r_message_efb(data,message);
}

function fun_emsFormBuilder__add_a_response_to_messages(message, by, userIp, track, date) {
  const resp = fun_emsFormBuilder_show_messages(message, by, '',track, date);
  const body = `<div class="efb   mb-3"><div class="efb  clearfix">${resp}</div></div>`
  document.getElementById('resp_efb').innerHTML += body
}

function response_Valid_tracker_efb(res) {
  if (res.data.success == true) {
    document.getElementById('body_efb-track').innerHTML = emsFormBuilder_show_content_message(res.data.value, res.data.content)
    setTimeout(() => {
     if(typeof EfbResponseViewer === 'undefined' && typeof reply_attach_efb =='function') {
       reply_attach_efb(res.data.value.msg_id);
     }
     state_rply_btn_efb(100)
    }, 50);
    document.getElementById('body_efb-track').classList.add('card');
  } else {
    document.getElementById('body_efb-track').innerHTML = `<div class="efb text-center"><h3 class='efb emsFormBuilder mt-3  text-center'><i class="efb nmsgefb  bi-exclamation-triangle-fill text-center efb fs-1"></i></h1><h3 class="efb  fs-3 text-muted  text-center">${ajax_object_efm.text.error}</h3> <span class="efb mb-2 efb fs-5 mx-1"> ${res.data.m}</span>
     <div class="efb display-btn emsFormBuilder"> <button type="button" id="emsFormBuilder-text-prevBtn-view" class="efb  btn btn-darkb m-5 text-white" onclick="(() => {  location.reload(); })()" style="display;"><i class="efb ${ajax_object_efm.rtl == 1 ? 'bi-arrow-right' : 'bi-arrow-left'}"></i></button></div></div>`;
  }
}

function response_rMessage_id(res, message) {
  if (res.success == true && res.data.success == true) {
    document.getElementById('replayM_emsFormBuilder').value = "";
    const richEditor = document.getElementById('efb_rich_editor');
    if (richEditor) richEditor.innerHTML = '';
    document.getElementById('replay_state__emsFormBuilder').innerHTML = res.data.m;
    document.getElementById('replayB_emsFormBuilder').classList.remove('disabled');
    document.getElementById('replayB_emsFormBuilder').innerHTML =ajax_object_efm.text.reply;
     if(document.getElementById('name_attach_efb')) document.getElementById('name_attach_efb').innerHTML =ajax_object_efm.text.file
    if (typeof EfbResponseViewer !== 'undefined' && EfbResponseViewer._handleFileRemoved) {
      var _uz = document.getElementById('efb_upload_zone');
      var _fi = document.getElementById('efb_upload_file_info');
      var _inp = document.getElementById('resp_file_efb_');
      var _ab = document.getElementById('efb_attach_btn');
      EfbResponseViewer._handleFileRemoved(_uz, _fi, _inp, _ab);
    }
    const date = Date();
    fun_emsFormBuilder__add_a_response_to_messages(message, res.data.by, 0, 0, date);
    const chatHistory = document.getElementById("resp_efb");
    chatHistory.scrollTop = chatHistory.scrollHeight;
  } else {
    document.getElementById('replayB_emsFormBuilder').innerHTML =ajax_object_efm.text.reply;
    document.getElementById('replay_state__emsFormBuilder').innerHTML = `<p class="efb text-danger bg-warning p-2">${res.data.m}</p>`;
  }
}
