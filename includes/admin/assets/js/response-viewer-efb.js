/**
 * Response Viewer & Rich Reply Editor Module
 * Easy Form Builder
 *
 * This module provides:
 * 1. Professional response message viewer UI
 * 2. Rich text editor for reply with visual Bold/Italic/Underline
 * 3. Markdown-like shortcode storage (**bold**, *italic*, __underline__)
 * 4. Auto-linkify URLs in displayed messages
 * 5. Formatted display of saved messages
 */

/* global efb_var, ajax_object_efm, fun_emsFormBuilder_show_messages,
   reply_attach_efb, stock_state_efb, form_type_emsFormBuilder,
   sessionPub_emsFormBuilder, sendBack_emsFormBuilder_pub,
   fun_sendBack_emsFormBuilder, sanitize_text_efb, check_msg_ext_resp_efb,
   fun_send_replayMessage_ajax_emsFormBuilder, show_modal_efb, state_modal_show_efb,
   page_state_efb, pro_efb, setting_emsFormBuilder, valNotFound_efb,
   replaceContentMessageEfb, valueJson_ws_messages, noti_message_efb_v4,
   post_api_r_message_efb, recaptcha_emsFormBuilder, sitekye_emsFormBuilder,
   generatePDF_EFB, closed_resp_emsFormBuilder,
   files_emsFormBuilder, fileEfb, viewfileReplyEfb,
   fun_upload_file_api_emsFormBuilder, fun_addProgessiveEl_efb, fun_removeProgessiveEl_efb,
   validExtensions_efb_fun
*/

const EfbResponseViewer = (function () {
  'use strict';

  // ──────────────────────────────────────────────────────
  // UTILITY: Get text helper (admin or public)
  // ──────────────────────────────────────────────────────
  function _t(key) {
    if (typeof efb_var !== 'undefined' && efb_var.text && efb_var.text[key]) return efb_var.text[key];
    if (typeof ajax_object_efm !== 'undefined' && ajax_object_efm.text && ajax_object_efm.text[key]) return ajax_object_efm.text[key];
    return key;
  }

  function isRtl() {
    return (typeof efb_var !== 'undefined' && efb_var.rtl == 1);
  }

  // ──────────────────────────────────────────────────────
  // RICH TEXT <-> SHORTCODE CONVERSION
  // ──────────────────────────────────────────────────────

  /**
   * Convert shortcode-formatted text to HTML for display
   * **text** => <strong>text</strong>
   * *text*  => <em>text</em>
   * __text__ => <u>text</u>
   * URLs => clickable links
   */
  function shortcodeToHtml(text) {
    if (!text || typeof text !== 'string') return text || '';
    let html = text;

    // Escape HTML first (but preserve existing safe tags)
    // Bold: **text**
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    // Italic: *text* (but not **)
    html = html.replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');
    // Underline: __text__
    html = html.replace(/__(.+?)__/g, '<u>$1</u>');

    // Auto-linkify URLs (http/https)
    html = html.replace(
      /(?<!"|\bhref="|>)(https?:\/\/[^\s<"']+)/gi,
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
    );

    return html;
  }

  /**
   * Convert HTML from contentEditable back to shortcode text for storage
   * <strong>text</strong> / <b>text</b> => **text**
   * <em>text</em> / <i>text</i>         => *text*
   * <u>text</u>                         => __text__
   * <br>                                => newline
   * <a href="url">text</a>             => just text (URLs auto-detected)
   */
  function htmlToShortcode(html) {
    if (!html || typeof html !== 'string') return '';
    let text = html;

    // Replace <br> / <br/> with newline marker
    text = text.replace(/<br\s*\/?>/gi, '\n');
    // Replace block-level elements with newline
    text = text.replace(/<\/?(div|p|li|blockquote)[^>]*>/gi, '\n');

    // Bold
    text = text.replace(/<(strong|b)\b[^>]*>([\s\S]*?)<\/\1>/gi, '**$2**');
    // Italic
    text = text.replace(/<(em|i)\b[^>]*>([\s\S]*?)<\/\1>/gi, '*$2*');
    // Underline
    text = text.replace(/<u\b[^>]*>([\s\S]*?)<\/u>/gi, '__$1__');

    // Links: keep just the URL or text
    text = text.replace(/<a\b[^>]*href="([^"]*)"[^>]*>[\s\S]*?<\/a>/gi, '$1');

    // Strip all remaining HTML tags
    text = text.replace(/<[^>]+>/g, '');

    // Decode HTML entities
    text = text.replace(/&amp;/g, '&');
    text = text.replace(/&lt;/g, '<');
    text = text.replace(/&gt;/g, '>');
    text = text.replace(/&nbsp;/g, ' ');
    text = text.replace(/&quot;/g, '"');

    // Normalize newlines
    text = text.replace(/\n{3,}/g, '\n\n');
    text = text.trim();

    return text;
  }

  /**
   * Format stored message text for display
   * Applies shortcode->HTML conversion and auto-linkify
   */
  function formatMessageForDisplay(text) {
    if (!text || typeof text !== 'string') return text || '';
    // First handle @efb@nq# line breaks
    let formatted = text.replace(/@efb@nq#/g, '<br>');
    // Then convert shortcodes
    formatted = shortcodeToHtml(formatted);
    return formatted;
  }

  // ──────────────────────────────────────────────────────
  // RICH TEXT EDITOR UI
  // ──────────────────────────────────────────────────────

  /**
   * Build the rich text editor HTML (replaces plain textarea)
   * @param {string|number} msgId - message ID
   * @param {string} savedValue - previously saved text (shortcode format)
   * @returns {string} HTML string
   */
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

  /**
   * Initialize the rich text editor after it's added to the DOM
   */
  function initRichEditor(msgId) {
    const editor = document.getElementById('efb_rich_editor');
    const raw = document.getElementById('replayM_emsFormBuilder');
    const toolbar = document.getElementById('efb_editor_toolbar');
    if (!editor || !raw || !toolbar) return;

    // Toolbar button clicks
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

    // Keyboard shortcuts
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

    // Sync on input
    editor.addEventListener('input', function () {
      _syncRawFromEditor(editor, raw, msgId);
      // Re-enable reply button if it was disabled (compatibility with check_msg_ext_resp_efb)
      var replyBtn = document.getElementById('replayB_emsFormBuilder');
      if (replyBtn && replyBtn.classList.contains('disabled')) {
        replyBtn.classList.remove('disabled');
      }
    });

    // Track selection changes for toolbar state
    document.addEventListener('selectionchange', function () {
      if (document.activeElement === editor) {
        _updateToolbarState(toolbar);
      }
    });

    // Prevent pasting styled content (paste as plain text)
    editor.addEventListener('paste', function (e) {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
    });
  }

  /**
   * Sync contenteditable HTML -> hidden textarea (shortcode format)
   */
  function _syncRawFromEditor(editor, raw, msgId) {
    const shortcode = htmlToShortcode(editor.innerHTML);
    raw.value = shortcode;
    // Also save to localStorage for persistence
    if (typeof localStorage !== 'undefined' && msgId) {
      localStorage.setItem('replayM_emsFormBuilder_' + msgId, shortcode);
    }
  }

  /**
   * Update toolbar button active state based on current selection
   */
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

  // ──────────────────────────────────────────────────────
  // REPLY ACTIONS UI
  // ──────────────────────────────────────────────────────

  /**
   * Build the reply action buttons (Reply, Attach, Close/Open)
   * @param {string|number} msgId
   * @param {boolean} isPanel - true if admin panel
   * @returns {string} HTML
   */
  function buildReplyActions(msgId, isPanel) {
    const uploadHtml = buildFileUploadArea(msgId, isPanel);
    return `
    <div class="efb-reply-actions">
      <button type="submit" class="efb-reply-btn" id="replayB_emsFormBuilder"
              onclick="fun_send_replayMessage_emsFormBuilder(${msgId})">
        <i class="bi bi-reply"></i> ${_t('reply')}
      </button>
      <p class="efb-reply-status" id="replay_state__emsFormBuilder"></p>
    </div>
    ${uploadHtml}`;
  }

  // ──────────────────────────────────────────────────────
  // RESPONSE VIEWER: BUILD FULL MODAL BODY
  // ──────────────────────────────────────────────────────

  /**
   * Build the admin panel response viewer body
   * This replaces the inline HTML in list_form-efb.js
   *
   * @param {number} indx - index in valueJson_ws_messages
   * @param {string} formType - form type (subscribe, register, survey, etc.)
   * @returns {string} HTML for modal body
   */
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

    // Build reply section (only for message/form types that support reply)
    let replySection = '';
    if (formType !== 'subscribe' && formType !== 'register' && formType !== 'survey') {
      const savedValue = localStorage.getItem('replayM_emsFormBuilder_' + msg_id) || '';
      replySection = buildRichEditor(msg_id, savedValue) + buildReplyActions(msg_id, true);
    }

    const body = `
      <div class="efb-resp-viewer">
        <div class="efb-resp-messages ${isRtl() ? 'rtl-text' : ''}" id="resp_efb">${m}</div>
        ${replySection}
      </div>`;

    return body;
  }

  /**
   * Build the public-facing (tracker) response viewer body
   * This replaces the inline HTML in core-efb.js emsFormBuilder_show_content_message
   *
   * @param {object} value - message object
   * @param {array} content - array of response messages
   * @returns {string} HTML for the viewer
   */
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
    <div class="efb-resp-viewer">
      <div class="efb-resp-messages ${isRtl() ? 'rtl-text' : ''}" id="resp_efb">${m}</div>
      ${replySection}
    </div>`;

    return body;
  }

  /**
   * Initialize the response viewer after it's rendered in the DOM.
   * Must be called after the modal is shown.
   *
   * @param {string|number} msgId
   * @param {boolean} isPanel
   */
  function initAfterRender(msgId, isPanel) {
    // Initialize file upload area (modern version built into the HTML)
    initFileUpload(msgId);

    // THEN: init the rich text editor listeners (after DOM is finalized)
    initRichEditor(msgId);

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Scroll messages to bottom
    const chatHistory = document.getElementById('resp_efb');
    if (chatHistory) {
      setTimeout(function () {
        chatHistory.scrollTop = chatHistory.scrollHeight;
      }, 50);
    }
  }

  /**
   * Get editor value in storage format (plain text with shortcodes + @efb@nq# newlines)
   * @returns {string}
   */
  function getEditorValue() {
    const raw = document.getElementById('replayM_emsFormBuilder');
    if (!raw) return '';
    let value = raw.value;
    // Replace newlines with @efb@nq# for storage compatibility
    value = value.replace(/\n/g, '@efb@nq#');
    return value;
  }

  // ──────────────────────────────────────────────────────
  // FILE UPLOAD: Modern attach UI
  // ──────────────────────────────────────────────────────

  /**
   * Build modern file upload area HTML
   * @param {string|number} msgId
   * @param {boolean} isPanel - true if admin panel
   * @returns {string} HTML
   */
  /**
   * Build the attach button for the editor toolbar
   * @param {string|number} msgId
   * @returns {string} HTML
   */
  function _buildAttachToolbarBtn(msgId) {
    // Hide when dsupfile is explicitly false on public page
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
    // Hide when dsupfile is explicitly false on public page
    if (typeof setting_emsFormBuilder !== 'undefined' &&
      setting_emsFormBuilder.hasOwnProperty('dsupfile') &&
      setting_emsFormBuilder.dsupfile == false &&
      typeof efb_var !== 'undefined' && !efb_var.hasOwnProperty('setting')) {
      return '';
    }

    // Close/Open response button (admin panel only)
    let closeBtn = '';
    if (isPanel) {
      const isOpen = typeof stock_state_efb !== 'undefined' && stock_state_efb === true;
      closeBtn = `<button type="button" class="efb-close-resp-btn ${isOpen ? 'open-state' : ''}"
                    onclick="closed_resp_emsFormBuilder(${msgId})"
                    data-state="${isOpen ? 1 : 0}" id="respStateEfb" disabled>
                    ${isOpen ? _t('open') : _t('close')}
                  </button>`;
    }

    // Slim upload zone: file info + progress only (attach button is in toolbar)
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

  /**
   * Initialize the file upload area after DOM is ready
   * @param {string|number} msgId
   */
  function initFileUpload(msgId) {
    const attachBtn = document.getElementById('efb_attach_btn');
    const fileInput = document.getElementById('resp_file_efb_');
    const uploadZone = document.getElementById('efb_upload_zone');
    const fileInfo = document.getElementById('efb_upload_file_info');
    const fileName = document.getElementById('efb_upload_file_name');
    const removeBtn = document.getElementById('efb_upload_file_remove');

    if (!attachBtn || !fileInput) return;

    // Guard: prevent double-binding event listeners
    if (attachBtn.dataset.efbBound) return;
    attachBtn.dataset.efbBound = '1';

    // Click attach button → open file picker
    attachBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      fileInput.click();
    });

    // File selected via input
    fileInput.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        _handleFileSelected(this.files[0], msgId, uploadZone, fileInfo, fileName, attachBtn);
      }
    });

    // Drag & drop on the rich editor area
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

    // Remove file
    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        _handleFileRemoved(uploadZone, fileInfo, fileInput, attachBtn);
      });
    }
  }

  /**
   * Handle a file being selected (validate + start upload)
   */
  function _handleFileSelected(file, msgId, uploadZone, fileInfo, fileNameEl, attachBtn) {
    // Validate file type
    if (typeof validExtensions_efb_fun === 'function') {
      if (!validExtensions_efb_fun('allformat', file.type, 0)) {
        const m = _t('pleaseUploadA') || 'Please upload a valid file';
        if (typeof alert_message_efb === 'function') {
          alert_message_efb('', m.replace('NN', `${_t('media')}, ${_t('document')} ${_t('or')} ${_t('zip')}`), 4, 'danger');
        }
        return;
      }
    }

    // Show upload zone (container)
    if (uploadZone) uploadZone.classList.remove('d-none');

    // Mark attach button as active
    if (attachBtn) attachBtn.classList.add('efb-attach-active');

    // Show progress bar (prA = d-block during upload)
    const prG = document.getElementById('resp_file_efb-prG');
    const prA = document.getElementById('resp_file_efb-prA');
    if (prG) prG.classList.remove('d-none');
    if (prA) { prA.classList.remove('d-none'); prA.classList.add('d-block'); }

    // Set global fileEfb for compatibility with pro_els-efb.js pipeline
    if (typeof window !== 'undefined') window.fileEfb = file;

    // Push to files array & start upload
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

      // Read as data URL for the record
      const reader = new FileReader();
      reader.onload = function () {
        const idx = files_emsFormBuilder.findIndex(function (x) { return x.id_ === 'resp_file_efb'; });
        if (idx !== -1) files_emsFormBuilder[idx].url = reader.result;
      };
      reader.readAsDataURL(file);

      // Upload via existing pipeline — hide progress after completion
      if (typeof fun_upload_file_api_emsFormBuilder === 'function') {
        fun_upload_file_api_emsFormBuilder('resp_file_efb', 'allformat', 'resp', file);
        // Watch for upload completion to hide progress bar
        _watchUploadProgress();
      }
    }
  }

  /**
   * Watch progress bar and hide it once upload reaches 100%
   */
  function _watchUploadProgress() {
    const prB = document.getElementById('resp_file_efb-prB');
    const prA = document.getElementById('resp_file_efb-prA');
    const prG = document.getElementById('resp_file_efb-prG');
    const fileInfo = document.getElementById('efb_upload_file_info');
    const fileNameEl = document.getElementById('efb_upload_file_name');
    const fileInput = document.getElementById('resp_file_efb_');
    if (!prB || !prA) return;

    let checks = 0;
    const maxChecks = 600; // 60 seconds max
    const interval = setInterval(function () {
      checks++;
      const width = parseFloat(prB.style.width);
      if (width >= 100 || checks >= maxChecks) {
        clearInterval(interval);
        // After 100%: wait 2 seconds then hide progress, show file info
        setTimeout(function () {
          // Hide progress bar
          prA.classList.remove('d-block');
          prA.classList.add('d-none');
          if (prG) prG.classList.add('d-none');
          // Reset progress for next use
          prB.style.width = '0%';
          prB.textContent = '0%';

          // Show file info with name
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

  /**
   * Handle file removal
   */
  function _handleFileRemoved(uploadZone, fileInfo, fileInput, attachBtn) {
    // Hide file info
    if (fileInfo) { fileInfo.classList.remove('d-block'); fileInfo.classList.add('d-none'); }

    // Reset file name
    const fileNameEl = document.getElementById('efb_upload_file_name');
    if (fileNameEl) { fileNameEl.textContent = ''; fileNameEl.title = ''; }

    // Clear file input
    if (fileInput) fileInput.value = '';

    // Hide entire upload zone
    if (uploadZone) uploadZone.classList.add('d-none');

    // Remove active state from attach button
    if (attachBtn) attachBtn.classList.remove('efb-attach-active');

    // Hide & reset progress bar
    const prG = document.getElementById('resp_file_efb-prG');
    const prA = document.getElementById('resp_file_efb-prA');
    const prB = document.getElementById('resp_file_efb-prB');
    if (prG) prG.classList.add('d-none');
    if (prA) { prA.classList.remove('d-block'); prA.classList.add('d-none'); }
    if (prB) { prB.style.width = '0%'; prB.textContent = '0%'; }

    // Remove from files_emsFormBuilder
    if (typeof files_emsFormBuilder !== 'undefined') {
      const idx = files_emsFormBuilder.findIndex(function (x) { return x.id_ === 'resp_file_efb'; });
      if (idx !== -1) {
        files_emsFormBuilder.splice(idx, 1);
      }
    }

    // Remove from sendBack
    if (typeof sendBack_emsFormBuilder_pub !== 'undefined') {
      for (let i = sendBack_emsFormBuilder_pub.length - 1; i >= 0; i--) {
        if (sendBack_emsFormBuilder_pub[i].name === 'file') {
          sendBack_emsFormBuilder_pub.splice(i, 1);
        }
      }
    }

    if (typeof window !== 'undefined') window.fileEfb = null;
  }

  // ──────────────────────────────────────────────────────
  // PUBLIC API
  // ──────────────────────────────────────────────────────
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
