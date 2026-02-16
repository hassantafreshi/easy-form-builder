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
   generatePDF_EFB, closed_resp_emsFormBuilder
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
    return `
    <div class="efb-reply-actions">
      <button type="submit" class="efb-reply-btn" id="replayB_emsFormBuilder"
              onclick="fun_send_replayMessage_emsFormBuilder(${msgId})">
        <i class="bi bi-reply"></i> ${_t('reply')}
      </button>
      <p class="efb-reply-status" id="replay_state__emsFormBuilder"></p>
    </div>`;
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
    const replySection = buildRichEditor(msg_id, savedValue) + `
    <div class="efb-reply-actions">
      <button type="submit" class="efb-reply-btn" id="replayB_emsFormBuilder"
              onclick="fun_send_replayMessage_emsFormBuilder(${msg_id})">
        <i class="bi bi-reply"></i> ${typeof ajax_object_efm !== 'undefined' && ajax_object_efm.text ? ajax_object_efm.text.reply : _t('reply')}
      </button>
      ${typeof sitekye_emsFormBuilder !== 'undefined' && sitekye_emsFormBuilder ?
        `<div class="efb row mx-3"><div class="efb g-recaptcha my-2 mx-2" data-sitekey="${sitekye_emsFormBuilder}" id="recaptcha"></div><small class="efb text-danger" id="recaptcha-message"></small></div>` : ''}
      <p class="efb-reply-status" id="replay_state__emsFormBuilder"></p>
    </div>`;

    const body = `
    <div class="efb-resp-viewer">
      <div class="efb modal-header efb py-4">
        <h5 class="efb modal-title fs-5"></h5>
      </div>
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
    // FIRST: append attach/close buttons (before setting up editor listeners)
    if (isPanel && typeof reply_attach_efb === 'function') {
      reply_attach_efb(msgId);
    }

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
  // PUBLIC API
  // ──────────────────────────────────────────────────────
  return {
    buildAdminResponseBody: buildAdminResponseBody,
    buildPublicResponseBody: buildPublicResponseBody,
    buildRichEditor: buildRichEditor,
    buildReplyActions: buildReplyActions,
    initAfterRender: initAfterRender,
    initRichEditor: initRichEditor,
    getEditorValue: getEditorValue,
    shortcodeToHtml: shortcodeToHtml,
    htmlToShortcode: htmlToShortcode,
    formatMessageForDisplay: formatMessageForDisplay
  };

})();
