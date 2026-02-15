/**
 * Easy Form Builder - Professional Drag & Drop Email Template Builder
 *
 * Replaces the plain textarea email template editor with a visual
 * drag-and-drop builder that outputs email-safe HTML.
 *
 * Shortcodes supported (replaced server-side in class-email-handler.php):
 *   shortcode_message       * (required) - Form submission content
 *   shortcode_title           - Email title / form name
 *   shortcode_website_name    - Blog name (get_bloginfo('name'))
 *   shortcode_website_url     - Home URL (home_url())
 *   shortcode_admin_email     - Admin email address
 *
 * Output is stored in the hidden #emailTemp_emsFirmBuilder textarea
 * using the same encoding convention: URLs encoded with @efb@
 * (handled by the existing f() / u() functions in list_form-efb.js)
 *
 * @package Easy Form Builder
 * @since 4.x
 */

(function () {
  'use strict';

  /* ──────────────────────────── CONFIGURATION ──────────────────────────── */

  const BUILDER_ID_efb = 'efb-email-builder';
  const CANVAS_ID_efb  = 'efb-email-canvas';
  const TEXTAREA_ID_efb = 'emailTemp_emsFirmBuilder';

  const isRtl_efb = () => typeof efb_var !== 'undefined' && Number(efb_var.rtl) === 1;

  // Text helper - falls back to English
  const t_efb = (key, fallback) => {
    if (typeof efb_var !== 'undefined' && efb_var.text && efb_var.text[key]) {
      return efb_var.text[key];
    }
    return fallback || key;
  };

  /* ─────────────────── EMAIL-SAFE FONT STACKS ───────────────────────── */

  const EMAIL_SAFE_FONTS_efb = [
    { label: 'Segoe UI',      value: "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif" },
    { label: 'Arial',         value: "Arial, Helvetica, sans-serif" },
    { label: 'Helvetica',     value: "Helvetica, Arial, sans-serif" },
    { label: 'Verdana',       value: "Verdana, Geneva, sans-serif" },
    { label: 'Tahoma',        value: "Tahoma, Geneva, sans-serif" },
    { label: 'Trebuchet MS',  value: "'Trebuchet MS', Helvetica, sans-serif" },
    { label: 'Lucida Sans',   value: "'Lucida Sans Unicode', 'Lucida Grande', sans-serif" },
    { label: 'Georgia',       value: "Georgia, 'Times New Roman', Times, serif" },
    { label: 'Times New Roman', value: "'Times New Roman', Times, serif" },
    { label: 'Palatino',      value: "'Palatino Linotype', 'Book Antiqua', Palatino, serif" },
    { label: 'Courier New',   value: "'Courier New', Courier, monospace" },
    { label: 'Lucida Console', value: "'Lucida Console', Monaco, monospace" },
    { label: 'Comic Sans MS', value: "'Comic Sans MS', cursive" },
    { label: 'Impact',        value: "Impact, Charcoal, sans-serif" },
    { label: 'Tahoma (RTL)',  value: "Tahoma, Arial, sans-serif" },
    { label: 'B Nazanin',     value: "'B Nazanin', Tahoma, Arial, sans-serif" },
  ];

  const DEFAULT_FONT_efb = "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif";

  /* ─────────────────────────── COLOR PRESETS ─────────────────────────── */

  const COLOR_PRESETS_efb = [
    '#202a8d', '#667eea', '#0ea5e9', '#10b981', '#f59e0b',
    '#ef4444', '#8b5cf6', '#ec4899', '#2D3445', '#1e3a8a',
    '#333333', '#666666', '#999999', '#ffffff', '#f8f9fa',
    '#f0f9ff', '#fefce8', '#fef2f2', '#f5f3ff', '#000000'
  ];

  /* ─────────────────────────── XSS SANITIZERS ────────────────────────── */

  /**
   * Regex that matches dangerous HTML/JS patterns.
   * Used to strip XSS vectors from user-supplied block data *before*
   * it is interpolated into the email HTML output.
   */
  const _xssPatterns_efb = [
    /<script[\s>\/]/gi,           // <script> tags
    /<\/script>/gi,               // </script>
    /\bon\w+\s*=/gi,              // on* event handlers (onclick=, onerror=, etc.)
    /javascript\s*:/gi,           // javascript: URIs
    /vbscript\s*:/gi,             // vbscript: URIs
    /data\s*:\s*text\/html/gi,    // data:text/html URIs
    /<iframe[\s>\/]/gi,           // <iframe>
    /<\/iframe>/gi,
    /<object[\s>\/]/gi,           // <object>
    /<\/object>/gi,
    /<embed[\s>\/]/gi,            // <embed>
    /<\/embed>/gi,
    /<form[\s>\/]/gi,             // <form>
    /<\/form>/gi,
    /<input[\s>\/]/gi,            // <input>
    /<textarea[\s>\/]/gi,         // <textarea>
    /<\/textarea>/gi,
    /<button[\s>\/]/gi,           // <button>
    /<\/button>/gi,
    /<select[\s>\/]/gi,           // <select>
    /<\/select>/gi,
    /<meta[\s>\/]/gi,             // <meta> (refresh redirect)
    /<link[\s>\/]/gi,             // <link>
    /<base[\s>\/]/gi,             // <base>
    /<svg[\s>\/]/gi,              // <svg> (can contain scripts)
    /<\/svg>/gi,
    /<math[\s>\/]/gi,             // <math> (MathML injection)
    /<\/math>/gi,
    /expression\s*\(/gi,          // CSS expression()
    /-moz-binding\s*:/gi,         // -moz-binding CSS
    /behavior\s*:/gi,             // IE behavior CSS
    /url\s*\(\s*['"]*\s*javascript/gi, // url(javascript:)
  ];

  /**
   * Strip all dangerous patterns from a string.
   * Returns cleaned text safe to insert as HTML attribute value or CSS.
   */
  function sanitizeAttr_efb(str) {
    if (!str && str !== 0) return '';
    let s = String(str);
    for (const rx of _xssPatterns_efb) {
      rx.lastIndex = 0;          // reset stateful /g regex
      s = s.replace(rx, '');
    }
    // Remove null bytes and other control chars (except \n \r \t)
    s = s.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
    return s;
  }

  /**
   * Sanitize text that will appear as HTML *content* (not inside an attribute).
   * Escapes < > & " while preserving shortcode_* placeholder names.
   * Also strips all XSS patterns first.
   */
  function sanitizeText_efb(str) {
    if (!str && str !== 0) return '';
    let s = sanitizeAttr_efb(str);
    // Escape HTML entities
    s = s.replace(/&/g, '&amp;')
         .replace(/</g, '&lt;')
         .replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;');
    return s;
  }

  /**
   * Sanitize a URL — reject dangerous schemes, keep only safe ones.
   */
  function sanitizeUrl_efb(url) {
    if (!url) return '';
    let s = String(url).trim();
    // Remove null bytes
    s = s.replace(/\x00/g, '');
    // Decode HTML entities to catch obfuscated javascript: etc.
    const tmp = s.replace(/&#(\d+);?/g, (_, n) => String.fromCharCode(n))
                 .replace(/&#x([0-9a-f]+);?/gi, (_, h) => String.fromCharCode(parseInt(h, 16)));
    const lower = tmp.replace(/\s+/g, '').toLowerCase();
    if (lower.startsWith('javascript:') || lower.startsWith('vbscript:') ||
        lower.startsWith('data:text/html') || lower.startsWith('data:application')) {
      return '';
    }
    // Shortcodes are allowed as-is
    if (s.startsWith('shortcode_')) return s;
    return s;
  }

  /**
   * Sanitize CSS value for style attributes — strip expression(), url(javascript:), etc.
   */
  function sanitizeCss_efb(css) {
    if (!css) return '';
    let s = String(css);
    s = s.replace(/expression\s*\(/gi, '')
         .replace(/-moz-binding\s*:/gi, '')
         .replace(/behavior\s*:/gi, '')
         .replace(/url\s*\(\s*['"]?\s*javascript/gi, 'url(blocked')
         .replace(/url\s*\(\s*['"]?\s*vbscript/gi, 'url(blocked')
         .replace(/url\s*\(\s*['"]?\s*data\s*:\s*text\/html/gi, 'url(blocked');
    // Remove null bytes
    s = s.replace(/[\x00]/g, '');
    return s;
  }

  /**
   * Sanitize content for the htmlBlock block type.
   * Strips dangerous tags/attributes but allows safe HTML for email.
   */
  function sanitizeHtmlBlock_efb(html) {
    if (!html) return '';
    let s = String(html);
    for (const rx of _xssPatterns_efb) {
      rx.lastIndex = 0;
      s = s.replace(rx, '');
    }
    // Remove null bytes and other control chars (except \n \r \t)
    s = s.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
    return s;
  }

  /* ──────────────────────── BLOCK DEFINITIONS ───────────────────────── */

  const BLOCK_TYPES_efb = {

    header: {
      label: '📧 ' + t_efb('ebHeader', 'Header'),
      icon: 'bi-card-heading',
      category: 'layout',
      defaultData: {
        bgColor: '#202a8d',
        bgGradient: 'linear-gradient(135deg, #667eea 0%, #202a8d 100%)',
        padding: '40px 30px 30px 30px',
        align: 'center'
      },
      render(data) {
        const ha = sanitizeAttr_efb(data.align);
        return `<tr><td align="${ha}" style="padding: ${sanitizeCss_efb(data.padding)}; background: ${sanitizeCss_efb(data.bgGradient || data.bgColor)}; text-align: ${ha};">
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            ${data.children ? data.children.map(c => {
              const def = BLOCK_TYPES_efb[c.type];
              if (!def) return '';
              const cd = Object.assign({}, def.defaultData, c.data || {}, { align: data.align });
              return def.render(cd);
            }).join('') : ''}
          </table>
        </td></tr>`;
      }
    },

    logo: {
      label: '🖼️ ' + t_efb('ebLogoImage', 'Logo / Image'),
      icon: 'bi-image',
      category: 'content',
      defaultData: {
        src: (typeof efb_var !== 'undefined' && efb_var.images && efb_var.images.emailTemplate1)
              ? efb_var.images.emailTemplate1 : '',
        alt: 'Logo',
        width: '120',
        align: 'center'
      },
      render(data) {
        const imgMargin = data.align === 'left' ? '0 auto 20px 0' : data.align === 'right' ? '0 0 20px auto' : '0 auto 20px auto';
        return `<tr><td align="${sanitizeAttr_efb(data.align)}">
          <img src="${sanitizeUrl_efb(data.src)}" alt="${sanitizeAttr_efb(data.alt)}" style="width: ${sanitizeAttr_efb(data.width)}px; height: auto; display: block; margin: ${imgMargin}; border: none;" />
        </td></tr>`;
      }
    },

    title: {
      label: '📝 ' + t_efb('ebTitle', 'Title'),
      icon: 'bi-type-h1',
      category: 'content',
      defaultData: {
        text: 'shortcode_title',
        color: '#ffffff',
        fontSize: '28',
        fontWeight: '600',
        fontFamily: '',
        align: 'center'
      },
      render(data) {
        const ff = sanitizeCss_efb(data.fontFamily || DEFAULT_FONT_efb);
        return `<tr><td align="${sanitizeAttr_efb(data.align)}">
          <h1 style="margin: 0; padding: 0; color: ${sanitizeCss_efb(data.color)}; font-size: ${sanitizeAttr_efb(data.fontSize)}px; font-weight: ${sanitizeAttr_efb(data.fontWeight)}; line-height: 1.3; text-align: ${sanitizeAttr_efb(data.align)}; font-family: ${ff};">${sanitizeText_efb(data.text)}</h1>
        </td></tr>`;
      }
    },

    text: {
      label: '📄 ' + t_efb('ebTextBlock', 'Text Block'),
      icon: 'bi-text-paragraph',
      category: 'content',
      defaultData: {
        text: 'Your text here...',
        color: '#333333',
        fontSize: '16',
        fontFamily: '',
        lineHeight: '1.6',
        align: 'center',
        padding: '20px 30px'
      },
      render(data) {
        const ff = sanitizeCss_efb(data.fontFamily || DEFAULT_FONT_efb);
        return `<tr><td style="padding: ${sanitizeCss_efb(data.padding)};">
          <p style="margin: 0; color: ${sanitizeCss_efb(data.color)}; font-size: ${sanitizeAttr_efb(data.fontSize)}px; line-height: ${sanitizeAttr_efb(data.lineHeight)}; text-align: ${sanitizeAttr_efb(data.align)}; font-family: ${ff};">${sanitizeText_efb(data.text)}</p>
        </td></tr>`;
      }
    },

    message: {
      label: '💬 ' + t_efb('ebMessageContent', 'Message Content') + ' *',
      icon: 'bi-chat-square-text',
      category: 'shortcode',
      defaultData: {
        padding: '40px 30px',
        bgColor: '#ffffff',
        color: '#333333',
        fontSize: '16',
        fontFamily: '',
        align: 'center'
      },
      render(data) {
        const ff = sanitizeCss_efb(data.fontFamily || DEFAULT_FONT_efb);
        return `<tr><td style="padding: ${sanitizeCss_efb(data.padding)}; background-color: ${sanitizeCss_efb(data.bgColor)};">
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr><td align="${sanitizeAttr_efb(data.align)}" style="color: ${sanitizeCss_efb(data.color)}; font-size: ${sanitizeAttr_efb(data.fontSize)}px; line-height: 1.6; text-align: ${sanitizeAttr_efb(data.align)}; font-family: ${ff};">
              shortcode_message
            </td></tr>
          </table>
        </td></tr>`;
      }
    },

    button: {
      label: '🔘 ' + t_efb('ebButton', 'Button'),
      icon: 'bi-link-45deg',
      category: 'content',
      defaultData: {
        text: 'Visit Website',
        url: 'shortcode_website_url',
        bgColor: '#202a8d',
        textColor: '#ffffff',
        borderRadius: '8',
        padding: '16px 32px',
        fontSize: '17',
        fontFamily: '',
        align: 'center',
        containerPadding: '25px 30px'
      },
      render(data) {
        const ff = sanitizeCss_efb(data.fontFamily || DEFAULT_FONT_efb);
        const ba = sanitizeAttr_efb(data.align);
        const btnMargin = ba === 'left' ? '0 auto 0 0' : ba === 'right' ? '0 0 0 auto' : '0 auto';
        return `<tr><td align="${ba}" style="padding: ${sanitizeCss_efb(data.containerPadding)};">
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="${ba}" style="margin: ${btnMargin};">
            <tr>
              <td style="background: ${sanitizeCss_efb(data.bgColor)}; border-radius: ${sanitizeAttr_efb(data.borderRadius)}px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                <a href="${sanitizeUrl_efb(data.url)}" target="_blank" style="display: inline-block; padding: ${sanitizeCss_efb(data.padding)}; color: ${sanitizeCss_efb(data.textColor)}; text-decoration: none; font-family: ${ff}; font-size: ${sanitizeAttr_efb(data.fontSize)}px; font-weight: 600; line-height: 1;">${sanitizeText_efb(data.text)}</a>
              </td>
            </tr>
          </table>
        </td></tr>`;
      }
    },

    divider: {
      label: '➖ ' + t_efb('ebDivider', 'Divider'),
      icon: 'bi-dash-lg',
      category: 'layout',
      defaultData: {
        color: '#e5e7eb',
        thickness: '1',
        width: '100',
        padding: '20px 30px'
      },
      render(data) {
        return `<tr><td style="padding: ${sanitizeCss_efb(data.padding)};">
          <hr style="margin: 0; padding: 0; border: none; border-top: ${sanitizeAttr_efb(data.thickness)}px solid ${sanitizeCss_efb(data.color)}; width: ${sanitizeAttr_efb(data.width)}%;" />
        </td></tr>`;
      }
    },

    spacer: {
      label: '↕️ ' + t_efb('ebSpacer', 'Spacer'),
      icon: 'bi-arrows-expand',
      category: 'layout',
      defaultData: {
        height: '20',
        bgColor: 'transparent'
      },
      render(data) {
        return `<tr><td style="height: ${sanitizeAttr_efb(data.height)}px; background-color: ${sanitizeCss_efb(data.bgColor)};">&nbsp;</td></tr>`;
      }
    },

    image: {
      label: '🖼️ ' + t_efb('ebImage', 'Image'),
      icon: 'bi-card-image',
      category: 'content',
      defaultData: {
        src: '',
        alt: 'Image',
        width: '100',
        widthUnit: '%',
        align: 'center',
        padding: '15px 30px',
        link: ''
      },
      render(data) {
        const safeWidth = sanitizeAttr_efb(data.width);
        const w = data.widthUnit === '%' ? `${safeWidth}%` : `${safeWidth}px`;
        const img = `<img src="${sanitizeUrl_efb(data.src)}" alt="${sanitizeAttr_efb(data.alt)}" style="width: ${w}; max-width: 100%; height: auto; display: block; border: none;" />`;
        const linked = data.link ? `<a href="${sanitizeUrl_efb(data.link)}" target="_blank" style="text-decoration:none;">${img}</a>` : img;
        return `<tr><td align="${sanitizeAttr_efb(data.align)}" style="padding: ${sanitizeCss_efb(data.padding)};">
          ${linked}
        </td></tr>`;
      }
    },

    columns: {
      label: '📊 ' + t_efb('ebTwoColumns', 'Two Columns'),
      icon: 'bi-layout-split',
      category: 'layout',
      defaultData: {
        padding: '20px 30px',
        gap: '20',
        leftContent: 'Left column content',
        rightContent: 'Right column content',
        leftColor: '#333333',
        rightColor: '#333333',
        fontSize: '14',
        fontFamily: '',
        bgColor: '#ffffff'
      },
      render(data) {
        const ff = sanitizeCss_efb(data.fontFamily || DEFAULT_FONT_efb);
        return `<tr><td style="padding: ${sanitizeCss_efb(data.padding)}; background-color: ${sanitizeCss_efb(data.bgColor)};">
          <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
              <td width="48%" valign="top" style="padding-right: ${Math.round(sanitizeAttr_efb(data.gap)/2)}px; color: ${sanitizeCss_efb(data.leftColor)}; font-size: ${sanitizeAttr_efb(data.fontSize)}px; line-height: 1.6; font-family: ${ff};">
                ${sanitizeText_efb(data.leftContent)}
              </td>
              <td width="4%"></td>
              <td width="48%" valign="top" style="padding-left: ${Math.round(sanitizeAttr_efb(data.gap)/2)}px; color: ${sanitizeCss_efb(data.rightColor)}; font-size: ${sanitizeAttr_efb(data.fontSize)}px; line-height: 1.6; font-family: ${ff};">
                ${sanitizeText_efb(data.rightContent)}
              </td>
            </tr>
          </table>
        </td></tr>`;
      }
    },

    social: {
      label: '🌐 ' + t_efb('ebSocialLinks', 'Social Links'),
      icon: 'bi-share',
      category: 'content',
      defaultData: {
        align: 'center',
        padding: '20px 30px',
        color: '#667eea',
        fontSize: '14',
        fontFamily: '',
        links: [
          { name: 'Website', url: 'shortcode_website_url' }
        ]
      },
      render(data) {
        const ff = sanitizeCss_efb(data.fontFamily || DEFAULT_FONT_efb);
        const linksHtml = data.links.map(l =>
          `<a href="${sanitizeUrl_efb(l.url)}" target="_blank" style="display: inline-block; margin: 0 8px; color: ${sanitizeCss_efb(data.color)}; text-decoration: none; font-size: ${sanitizeAttr_efb(data.fontSize)}px; font-family: ${ff};">${sanitizeText_efb(l.name)}</a>`
        ).join(' | ');
        return `<tr><td align="${sanitizeAttr_efb(data.align)}" style="padding: ${sanitizeCss_efb(data.padding)};">
          ${linksHtml}
        </td></tr>`;
      }
    },

    footer: {
      label: '📋 ' + t_efb('ebFooter', 'Footer'),
      icon: 'bi-card-text',
      category: 'layout',
      defaultData: {
        text: 'Sent by shortcode_website_name',
        color: '#6b7280',
        fontSize: '13',
        fontFamily: '',
        align: 'center',
        bgColor: '#f8f9fa',
        padding: '30px',
        borderRadius: '0 0 8px 8px'
      },
      render(data) {
        const ff = sanitizeCss_efb(data.fontFamily || DEFAULT_FONT_efb);
        return `<tr><td style="padding: ${sanitizeCss_efb(data.padding)}; background-color: ${sanitizeCss_efb(data.bgColor)}; border-radius: ${sanitizeCss_efb(data.borderRadius)};">
          <p style="margin: 0; color: ${sanitizeCss_efb(data.color)}; font-size: ${sanitizeAttr_efb(data.fontSize)}px; line-height: 1.5; text-align: ${sanitizeAttr_efb(data.align)}; font-family: ${ff};">${sanitizeText_efb(data.text)}</p>
        </td></tr>`;
      }
    },

    htmlBlock: {
      label: '🖥️ ' + t_efb('ebCustomHTML', 'Custom HTML'),
      icon: 'bi-code-slash',
      category: 'advanced',
      defaultData: {
        html: '<p style="text-align:center; color:#333;">Custom HTML content</p>'
      },
      render(data) {
        return `<tr><td>${sanitizeHtmlBlock_efb(data.html)}</td></tr>`;
      }
    }
  };

  /* ───────────────────── PRE-BUILT TEMPLATES ──────────────────────────── */

  const TEMPLATES_efb = {

    blank: {
      label: t_efb('blank', 'Blank'),
      icon: 'bi-file-earmark',
      blocks: [
        { type: 'message', data: {} }
      ]
    },

    professional: {
      label: t_efb('ebProfessional', 'Professional'),
      icon: 'bi-briefcase',
      blocks: [
        { type: 'header', data: { bgGradient: 'linear-gradient(135deg, #667eea 0%, #202a8d 100%)', padding: '40px 30px 30px 30px' },
          children: [
            { type: 'logo', data: { width: '120', align: 'center' } },
            { type: 'title', data: { text: 'shortcode_title', color: '#ffffff', fontSize: '28', fontWeight: '600', align: 'center' } }
          ]
        },
        { type: 'message', data: { padding: '40px 30px', bgColor: '#ffffff', color: '#333333', fontSize: '16', align: 'center' } },
        { type: 'spacer', data: { height: '20', bgColor: '#ffffff' } },
        { type: 'footer', data: { text: 'shortcode_website_name | shortcode_admin_email', color: '#6b7280', fontSize: '14', align: 'center', bgColor: '#f8f9fa', padding: '30px' } },
        { type: 'text', data: { text: t_efb('ebDisclaimerText', 'This email was sent automatically. Please do not reply directly.'), color: '#64748b', fontSize: '12', align: 'center', padding: '15px 25px' } }
      ]
    },

    modern: {
      label: t_efb('ebModernDark', 'Modern Dark'),
      icon: 'bi-moon-stars',
      blocks: [
        { type: 'header', data: { bgColor: '#111827', bgGradient: 'linear-gradient(180deg, #1f2937 0%, #111827 100%)', padding: '45px 30px 35px 30px' },
          children: [
            { type: 'logo', data: { width: '100', align: 'center' } },
            { type: 'title', data: { text: 'shortcode_title', color: '#f9fafb', fontSize: '26', fontWeight: '600', align: 'center' } }
          ]
        },
        { type: 'message', data: { padding: '35px 30px', bgColor: '#1f2937', color: '#d1d5db', fontSize: '15', align: 'center' } },
        { type: 'button', data: { text: t_efb('ebViewWebsite', 'View Website'), url: 'shortcode_website_url', bgColor: '#4f46e5', textColor: '#ffffff', borderRadius: '6', padding: '14px 36px', fontSize: '16', align: 'center', containerPadding: '25px 30px' } },
        { type: 'divider', data: { color: '#374151', thickness: '1', width: '100', padding: '10px 30px' } },
        { type: 'footer', data: { text: 'shortcode_website_name | shortcode_admin_email', color: '#9ca3af', fontSize: '13', align: 'center', bgColor: '#111827', padding: '25px 30px', borderRadius: '0 0 8px 8px' } }
      ],
      globalSettings: { bgColor: '#0f172a', contentBgColor: '#1f2937', borderRadius: '12' }
    },

    minimal: {
      label: t_efb('ebMinimalClean', 'Minimal Clean'),
      icon: 'bi-layout-text-window',
      blocks: [
        { type: 'spacer', data: { height: '35', bgColor: '#ffffff' } },
        { type: 'logo', data: { width: '80', align: 'center' } },
        { type: 'title', data: { text: 'shortcode_title', color: '#1f2937', fontSize: '24', fontWeight: '700', align: 'center' } },
        { type: 'divider', data: { color: '#6366f1', thickness: '3', width: '50', padding: '15px 30px' } },
        { type: 'message', data: { padding: '25px 35px', bgColor: '#ffffff', color: '#4b5563', fontSize: '15', align: 'center' } },
        { type: 'button', data: { text: 'shortcode_website_name', url: 'shortcode_website_url', bgColor: '#6366f1', textColor: '#ffffff', borderRadius: '25', padding: '13px 30px', fontSize: '15', align: 'center', containerPadding: '20px 30px' } },
        { type: 'spacer', data: { height: '15', bgColor: '#ffffff' } },
        { type: 'footer', data: { text: 'shortcode_admin_email', color: '#9ca3af', fontSize: '12', align: 'center', bgColor: '#ffffff', padding: '20px 30px', borderRadius: '0 0 8px 8px' } }
      ],
      globalSettings: { bgColor: '#f3f4f6', contentBgColor: '#ffffff', borderRadius: '8' }
    },

    elegant: {
      label: t_efb('ebElegant', 'Elegant'),
      icon: 'bi-gem',
      blocks: [
        { type: 'header', data: { bgGradient: 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)', padding: '50px 30px 40px 30px' },
          children: [
            { type: 'logo', data: { width: '80', align: 'center' } },
            { type: 'title', data: { text: 'shortcode_title', color: '#e2e8f0', fontSize: '30', fontWeight: '300', align: 'center' } }
          ]
        },
        { type: 'message', data: { padding: '40px 40px', bgColor: '#ffffff', color: '#475569', fontSize: '15', align: 'center' } },
        { type: 'button', data: { text: t_efb('ebViewWebsite', 'View Website') + ' →', url: 'shortcode_website_url', bgColor: '#1e293b', textColor: '#ffffff', borderRadius: '4', padding: '14px 40px', fontSize: '15', align: 'center', containerPadding: '20px 30px' } },
        { type: 'divider', data: { color: '#e2e8f0', thickness: '1', width: '80', padding: '20px 30px' } },
        { type: 'text', data: { text: 'shortcode_website_name', color: '#94a3b8', fontSize: '13', align: 'center', padding: '5px 30px' } },
        { type: 'footer', data: { text: 'shortcode_admin_email', color: '#94a3b8', fontSize: '11', align: 'center', bgColor: '#f8fafc', padding: '25px 30px', borderRadius: '0 0 8px 8px' } }
      ],
      globalSettings: { bgColor: '#f1f5f9', contentBgColor: '#ffffff', borderRadius: '0' }
    },

    colorful: {
      label: t_efb('ebColorful', 'Colorful'),
      icon: 'bi-palette',
      blocks: [
        { type: 'header', data: { bgGradient: 'linear-gradient(135deg, #ec4899 0%, #8b5cf6 50%, #6366f1 100%)', padding: '45px 30px 35px 30px' },
          children: [
            { type: 'logo', data: { width: '100', align: 'center' } },
            { type: 'title', data: { text: 'shortcode_title', color: '#ffffff', fontSize: '28', fontWeight: '700', align: 'center' } }
          ]
        },
        { type: 'message', data: { padding: '35px 30px', bgColor: '#faf5ff', color: '#4c1d95', fontSize: '16', align: 'center' } },
        { type: 'button', data: { text: '🔗 ' + t_efb('ebViewWebsite', 'View Website'), url: 'shortcode_website_url', bgColor: '#8b5cf6', textColor: '#ffffff', borderRadius: '25', padding: '14px 35px', fontSize: '16', align: 'center', containerPadding: '20px 30px' } },
        { type: 'divider', data: { color: '#e9d5ff', thickness: '1', width: '80', padding: '15px 30px' } },
        { type: 'social', data: { color: '#8b5cf6', align: 'center', padding: '10px 30px', fontSize: '14' } },
        { type: 'footer', data: { text: '💜 shortcode_website_name | shortcode_admin_email', color: '#7c3aed', fontSize: '13', align: 'center', bgColor: '#faf5ff', padding: '25px 30px', borderRadius: '0 0 8px 8px' } }
      ],
      globalSettings: { bgColor: '#faf5ff', contentBgColor: '#ffffff', borderRadius: '12' }
    }
  };


  /* ────────────────────── BUILDER STATE ────────────────────────────── */

  let builderState_efb = {
    blocks: [],
    selectedBlock: null,
    isDragging: false,
    dragBlock: null,
    globalSettings: {
      bgColor: '#f8f9fa',
      contentBgColor: '#ffffff',
      contentWidth: '600',
      borderRadius: '8',
      fontFamily: "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif",
      direction: isRtl_efb() ? 'rtl' : 'ltr'
    },
    undoStack: [],
    redoStack: []
  };

  /* ──────────────────── UNIQUE ID GENERATOR ──────────────────────── */
  let _blockIdCounter_efb = 0;
  function genId_efb() { return 'efb-blk-' + (++_blockIdCounter_efb) + '-' + Date.now().toString(36); }

  /* ──────────────────── UNDO / REDO ────────────────────────────── */

  function saveState_efb() {
    builderState_efb.undoStack.push(JSON.stringify(builderState_efb.blocks));
    if (builderState_efb.undoStack.length > 30) builderState_efb.undoStack.shift();
    builderState_efb.redoStack = [];
  }

  function undo_efb() {
    if (builderState_efb.undoStack.length === 0) return;
    builderState_efb.redoStack.push(JSON.stringify(builderState_efb.blocks));
    builderState_efb.blocks = JSON.parse(builderState_efb.undoStack.pop());
    builderState_efb.selectedBlock = null;
    renderCanvas_efb();
    syncToTextarea_efb();
  }

  function redo_efb() {
    if (builderState_efb.redoStack.length === 0) return;
    builderState_efb.undoStack.push(JSON.stringify(builderState_efb.blocks));
    builderState_efb.blocks = JSON.parse(builderState_efb.redoStack.pop());
    builderState_efb.selectedBlock = null;
    renderCanvas_efb();
    syncToTextarea_efb();
  }

  /* ──────────────────── RENDER SINGLE BLOCK ────────────────────── */

  function renderBlock_efb(block) {
    const def = BLOCK_TYPES_efb[block.type];
    if (!def) return '';
    const data = Object.assign({}, def.defaultData, block.data || {});
    // For blocks with children (like header)
    if (block.children && block.children.length) {
      data.children = block.children;
    }
    return def.render(data);
  }

  /* ──────────────────── GENERATE FULL HTML ──────────────────────── */

  function generateFullHTML_efb() {
    const gs = builderState_efb.globalSettings;
    const blocksHtml = builderState_efb.blocks.map(b => renderBlock_efb(b)).join('\n');

    // Check if message shortcode exists
    const hasMessage = builderState_efb.blocks.some(b => b.type === 'message');

    return `<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<style type="text/css">
body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { -ms-interpolation-mode: bicubic; border: 0; }
body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
@media only screen and (max-width: 600px) {
  .efb-email-container { width: 100% !important; }
  .efb-email-container td { padding-left: 15px !important; padding-right: 15px !important; }
  img { max-width: 100% !important; height: auto !important; }
}
</style>
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: ${sanitizeCss_efb(gs.bgColor)}; direction: ${sanitizeAttr_efb(gs.direction)}; font-family: ${sanitizeCss_efb(gs.fontFamily)};">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: ${sanitizeCss_efb(gs.bgColor)};">
<tr><td align="center" style="padding: 20px 0;">
<table class="efb-email-container" role="presentation" cellspacing="0" cellpadding="0" border="0" width="${sanitizeAttr_efb(gs.contentWidth)}" style="margin: 0 auto; background-color: ${sanitizeCss_efb(gs.contentBgColor)}; border-radius: ${sanitizeAttr_efb(gs.borderRadius)}px;">
${blocksHtml}
</table>
</td></tr>
</table>
</body>
</html>`;
  }

  /* ──────────────────── SYNC TO TEXTAREA ───────────────────────── */

  function syncToTextarea_efb() {
    const textarea = document.getElementById(TEXTAREA_ID_efb);
    if (!textarea) return;
    const html = generateFullHTML_efb();
    // Embed builder JSON as HTML comment so it survives save/reload
    // encodeURIComponent output has NO / or " chars → safe from @efb@ encoding & quote replacement
    try {
      const builderData = { blocks: builderState_efb.blocks, globalSettings: builderState_efb.globalSettings };
      const encoded = encodeURIComponent(JSON.stringify(builderData));
      textarea.value = html + '\n<!-- EFBDATA:' + encoded + ' -->';
      // Also keep the in-memory JSON store in sync
      const jsonStore = document.getElementById('efb-builder-json');
      if (jsonStore) {
        jsonStore.value = JSON.stringify(builderData);
      }
    } catch(e) {
      textarea.value = html;
    }
  }

  /* ──────────────────── PARSE EXISTING TEMPLATE ───────────────── */

  function tryParseExistingTemplate_efb(html) {
    // Priority 1: Extract embedded EFBDATA from the HTML (survives save/reload)
    if (html && html.includes('<!-- EFBDATA:')) {
      const match = html.match(/<!-- EFBDATA:(.*?) -->/);
      if (match && match[1]) {
        try {
          const saved = JSON.parse(decodeURIComponent(match[1]));
          if (saved.blocks && saved.blocks.length) {
            builderState_efb.blocks = saved.blocks;
            if (saved.globalSettings) {
              builderState_efb.globalSettings = Object.assign(builderState_efb.globalSettings, saved.globalSettings);
            }
            return true;
          }
        } catch (e) {
          // EFBDATA corrupt, fall through to other methods
        }
      }
    }

    // Priority 2: Check in-memory JSON store (within same session)
    const jsonStore = document.getElementById('efb-builder-json');
    if (jsonStore && jsonStore.value && jsonStore.value.length > 10) {
      try {
        const saved = JSON.parse(jsonStore.value);
        if (saved.blocks && saved.blocks.length) {
          builderState_efb.blocks = saved.blocks;
          if (saved.globalSettings) {
            builderState_efb.globalSettings = Object.assign(builderState_efb.globalSettings, saved.globalSettings);
          }
          return true;
        }
      } catch (e) {}
    }

    // Priority 3: Empty template → load default
    if (!html || html.trim().length < 10) {
      loadTemplate_efb('professional');
      return true;
    }

    // Priority 4: Legacy template (not built by us) → wrap in htmlBlock
    // Strip any EFBDATA comment remnant from the HTML before wrapping
    const cleanHtml = html.replace(/\n?<!-- EFBDATA:.*? -->/g, '').trim();
    const hasMessage = cleanHtml.includes('shortcode_message');
    builderState_efb.blocks = [];
    builderState_efb.blocks.push({
      id: genId_efb(),
      type: 'htmlBlock',
      data: { html: cleanHtml }
    });
    if (!hasMessage) {
      builderState_efb.blocks.push({
        id: genId_efb(),
        type: 'message',
        data: {}
      });
    }
    return true;
  }

  /* ──────────────────── LOAD TEMPLATE ──────────────────────────── */

  function loadTemplate_efb(name) {
    const tpl = TEMPLATES_efb[name];
    if (!tpl) return;

    saveState_efb();
    builderState_efb.blocks = [];
    builderState_efb.selectedBlock = null;

    function processBlocks_efb(templateBlocks) {
      return templateBlocks.map(tb => {
        const block = {
          id: genId_efb(),
          type: tb.type,
          data: Object.assign({}, BLOCK_TYPES_efb[tb.type]?.defaultData || {}, tb.data || {})
        };
        if (tb.children) {
          block.children = tb.children.map(c => ({
            id: genId_efb(),
            type: c.type,
            data: Object.assign({}, BLOCK_TYPES_efb[c.type]?.defaultData || {}, c.data || {})
          }));
        }
        return block;
      });
    }

    builderState_efb.blocks = processBlocks_efb(tpl.blocks);

    // Apply template-specific global settings (colors, border-radius, etc.)
    if (tpl.globalSettings) {
      builderState_efb.globalSettings = Object.assign({}, builderState_efb.globalSettings, tpl.globalSettings);
    } else {
      // Reset to defaults for templates without custom global settings
      builderState_efb.globalSettings = {
        bgColor: '#f8f9fa',
        contentBgColor: '#ffffff',
        contentWidth: '600',
        borderRadius: '8',
        fontFamily: "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif",
        direction: isRtl_efb() ? 'rtl' : 'ltr'
      };
    }

    renderCanvas_efb();
    renderPropertiesPanel_efb();
    syncToTextarea_efb();
  }

  /* ──────────────── RENDER CANVAS (VISUAL EDITOR) ─────────────── */

  function renderCanvas_efb() {
    const canvas = document.getElementById(CANVAS_ID_efb);
    if (!canvas) return;

    if (builderState_efb.blocks.length === 0) {
      canvas.innerHTML = `
        <div class="efb-empty-canvas">
          <i class="efb bi-envelope-plus" style="font-size:48px;color:#cbd5e1;"></i>
          <p style="color:#94a3b8;margin-top:12px;font-size:16px;">${t_efb('ebDragBlocksHere', 'Drag blocks here to build your email template')}</p>
          <p style="color:#cbd5e1;font-size:13px;">${t_efb('ebOrChooseTemplate', 'or choose a template from the Templates panel')}</p>
        </div>`;
      return;
    }

    let html = '';
    builderState_efb.blocks.forEach((block, index) => {
      const isSelected = builderState_efb.selectedBlock === block.id;
      const def = BLOCK_TYPES_efb[block.type];
      const label = def ? def.label : block.type;
      const isMessage = block.type === 'message';

      html += `<div class="efb-canvas-block ${isSelected ? 'efb-block-selected' : ''} ${isMessage ? 'efb-block-required' : ''}"
        data-block-id="${block.id}" data-index="${index}" draggable="true">
        <div class="efb-block-label">
          <span><i class="efb ${def?.icon || 'bi-square'}"></i> ${label}</span>
          <div class="efb-block-actions">
            <button class="efb-blk-btn" onclick="efbEmailBuilder.moveBlock_efb('${block.id}',-1)" title="${t_efb('ebMoveUp', 'Move Up')}"><i class="efb bi-arrow-up"></i></button>
            <button class="efb-blk-btn" onclick="efbEmailBuilder.moveBlock_efb('${block.id}',1)" title="${t_efb('ebMoveDown', 'Move Down')}"><i class="efb bi-arrow-down"></i></button>
            <button class="efb-blk-btn" onclick="efbEmailBuilder.duplicateBlock_efb('${block.id}')" title="${t_efb('duplicate', 'Duplicate')}"><i class="efb bi-copy"></i></button>
            <button class="efb-blk-btn efb-blk-btn-danger" onclick="efbEmailBuilder.removeBlock_efb('${block.id}')" title="${t_efb('delete', 'Delete')}"><i class="efb bi-trash"></i></button>
          </div>
        </div>
        <div class="efb-block-preview">${renderBlockPreview_efb(block)}</div>
      </div>`;
    });

    canvas.innerHTML = html;

    // Bind click to select
    canvas.querySelectorAll('.efb-canvas-block').forEach(el => {
      el.addEventListener('click', (e) => {
        if (e.target.closest('.efb-blk-btn')) return;
        builderState_efb.selectedBlock = el.dataset.blockId;
        renderCanvas_efb();
        renderPropertiesPanel_efb();
      });
    });

    // Canvas drag-drop reorder
    initCanvasDragDrop_efb();

    // Apply global styles visually to canvas
    updateCanvasGlobalStyles_efb();
  }

  /* ──────────────── BLOCK PREVIEW (simplified visual) ─────────── */

  function renderBlockPreview_efb(block) {
    const data = Object.assign({}, BLOCK_TYPES_efb[block.type]?.defaultData || {}, block.data || {});
    // Scale font sizes for compact preview (60% of actual, min 10px, max 28px)
    const pfs = (sz) => Math.max(10, Math.min(Math.round(Number(sz) * 0.6), 28));
    switch (block.type) {
      case 'header':
        const ha = data.align || 'center';
        const childrenHtml = (block.children || []).map(c => {
          const cd = Object.assign({}, BLOCK_TYPES_efb[c.type]?.defaultData || {}, c.data || {});
          if (c.type === 'logo') { const lm = ha === 'left' ? '0 auto 10px 0' : ha === 'right' ? '0 0 10px auto' : '0 auto 10px'; return `<div style="text-align:${ha};"><img src="${cd.src}" style="width:${Math.min(cd.width,80)}px;height:auto;display:block;margin:${lm};" /></div>`; }
          if (c.type === 'title') return `<div style="text-align:${ha};color:${cd.color};font-size:${pfs(cd.fontSize)}px;font-weight:${cd.fontWeight};font-family:${cd.fontFamily || DEFAULT_FONT_efb};">${highlightShortcodes_efb(cd.text)}</div>`;
          return '';
        }).join('');
        return `<div style="background:${data.bgGradient || data.bgColor};padding:15px;border-radius:4px;text-align:${ha};">${childrenHtml}</div>`;
      case 'logo':
        const logoM = data.align === 'left' ? '0 auto 0 0' : data.align === 'right' ? '0 0 0 auto' : '0 auto';
        return `<div style="text-align:${data.align};padding:8px;"><img src="${data.src}" style="width:${Math.min(data.width,60)}px;height:auto;display:block;margin:${logoM};" onerror="this.style.display='none'" /></div>`;
      case 'title':
        return `<div style="text-align:${data.align};color:${data.color};font-size:${pfs(data.fontSize)}px;font-weight:${data.fontWeight};font-family:${data.fontFamily || DEFAULT_FONT_efb};padding:5px;">${highlightShortcodes_efb(data.text)}</div>`;
      case 'text':
        return `<div style="text-align:${data.align};color:${data.color};font-size:${pfs(data.fontSize)}px;font-family:${data.fontFamily || DEFAULT_FONT_efb};padding:5px;line-height:1.4;">${highlightShortcodes_efb(data.text)}</div>`;
      case 'message':
        return `<div style="text-align:${data.align};background:${data.bgColor};padding:12px;border:2px dashed #667eea;border-radius:4px;">
          <i class="efb bi-chat-square-text" style="font-size:20px;color:#667eea;"></i>
          <div style="color:#667eea;font-size:12px;margin-top:4px;font-weight:600;">shortcode_message</div>
          <div style="color:#94a3b8;font-size:10px;">${t_efb('ebFormContentHere', 'Form content appears here')}</div>
        </div>`;
      case 'button':
        return `<div style="text-align:${data.align};padding:8px;">
          <span style="display:inline-block;background:${data.bgColor};color:${data.textColor};padding:8px 20px;border-radius:${data.borderRadius}px;font-size:${pfs(data.fontSize)}px;font-weight:600;font-family:${data.fontFamily || DEFAULT_FONT_efb};">${highlightShortcodes_efb(data.text)}</span>
        </div>`;
      case 'divider':
        return `<div style="padding:8px ${data.padding.split(' ')[1] || '20px'};"><hr style="border:none;border-top:${data.thickness}px solid ${data.color};width:${data.width}%;margin:0 auto;" /></div>`;
      case 'spacer':
        return `<div style="height:${Math.min(data.height,30)}px;background:${data.bgColor};border:1px dashed #e5e7eb;text-align:center;line-height:${Math.min(data.height,30)}px;color:#cbd5e1;font-size:10px;">${data.height}px</div>`;
      case 'image':
        if (!data.src) return `<div style="text-align:center;padding:15px;border:2px dashed #e5e7eb;border-radius:4px;color:#94a3b8;"><i class="efb bi-card-image" style="font-size:24px;"></i><div style="font-size:11px;margin-top:4px;">${t_efb('ebAddImageURL', 'Add image URL')}</div></div>`;
        return `<div style="text-align:${data.align};padding:5px;"><img src="${data.src}" style="max-width:100%;max-height:80px;height:auto;" onerror="this.style.display='none'" /></div>`;
      case 'columns':
        return `<div style="display:flex;gap:8px;padding:5px;">
          <div style="flex:1;background:#f8fafc;padding:8px;border-radius:4px;font-size:${pfs(data.fontSize)}px;color:${data.leftColor};font-family:${data.fontFamily || DEFAULT_FONT_efb};">${highlightShortcodes_efb(data.leftContent)}</div>
          <div style="flex:1;background:#f8fafc;padding:8px;border-radius:4px;font-size:${pfs(data.fontSize)}px;color:${data.rightColor};font-family:${data.fontFamily || DEFAULT_FONT_efb};">${highlightShortcodes_efb(data.rightContent)}</div>
        </div>`;
      case 'social':
        return `<div style="text-align:${data.align};padding:5px;font-size:${pfs(data.fontSize)}px;color:${data.color};font-family:${data.fontFamily || DEFAULT_FONT_efb};">
          ${data.links.map(l => l.name).join(' | ')}
        </div>`;
      case 'footer':
        return `<div style="text-align:${data.align};background:${data.bgColor};padding:10px;border-radius:4px;color:${data.color};font-size:${pfs(data.fontSize)}px;font-family:${data.fontFamily || DEFAULT_FONT_efb};">${highlightShortcodes_efb(data.text)}</div>`;
      case 'htmlBlock':
        return `<div style="padding:5px;font-size:11px;color:#64748b;border:1px dashed #cbd5e1;border-radius:4px;max-height:60px;overflow:hidden;"><code>&lt;/&gt; ${t_efb('ebCustomHTML', 'Custom HTML')}</code></div>`;
      default:
        return `<div style="padding:8px;color:#94a3b8;">${t_efb('ebUnknownBlock', 'Unknown block')}</div>`;
    }
  }

  function highlightShortcodes_efb(text) {
    if (!text) return '';
    return text.replace(/(shortcode_\w+)/g, '<span style="background:#dbeafe;color:#1e40af;padding:1px 4px;border-radius:2px;font-size:0.85em;">$1</span>');
  }

  /* ──────────────── CANVAS DRAG-DROP (REORDER) ────────────────── */

  let _canvasDropBound_efb = false;

  function initCanvasDragDrop_efb() {
    const canvas = document.getElementById(CANVAS_ID_efb);
    if (!canvas) return;

    let dragSrcIndex = null;

    canvas.querySelectorAll('.efb-canvas-block').forEach(el => {
      el.addEventListener('dragstart', (e) => {
        dragSrcIndex = parseInt(el.dataset.index);
        el.classList.add('efb-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', el.dataset.blockId);
      });

      el.addEventListener('dragend', () => {
        el.classList.remove('efb-dragging');
        canvas.querySelectorAll('.efb-drop-indicator').forEach(d => d.remove());
      });

      el.addEventListener('dragover', (e) => {
        e.preventDefault();
        const isPanel = e.dataTransfer.types.includes('efb-new-block');
        e.dataTransfer.dropEffect = isPanel ? 'copy' : 'move';
        const rect = el.getBoundingClientRect();
        const mid = rect.top + rect.height / 2;
        el.classList.toggle('efb-drop-above', e.clientY < mid);
        el.classList.toggle('efb-drop-below', e.clientY >= mid);
      });

      el.addEventListener('dragleave', () => {
        el.classList.remove('efb-drop-above', 'efb-drop-below');
      });

      el.addEventListener('drop', (e) => {
        e.preventDefault();
        el.classList.remove('efb-drop-above', 'efb-drop-below');
        const targetIndex = parseInt(el.dataset.index);

        // Check if this is from the blocks panel (new block) — must be before reorder guard
        const newBlockType = e.dataTransfer.getData('efb-new-block');
        if (newBlockType) {
          const rect = el.getBoundingClientRect();
          const insertPos = e.clientY < (rect.top + rect.height / 2) ? targetIndex : targetIndex + 1;
          addBlockAt_efb(newBlockType, insertPos);
          return;
        }

        if (dragSrcIndex === null || dragSrcIndex === targetIndex) return;

        saveState_efb();
        const [moved] = builderState_efb.blocks.splice(dragSrcIndex, 1);
        const insertAt = e.clientY < (el.getBoundingClientRect().top + el.getBoundingClientRect().height / 2)
          ? targetIndex : targetIndex + 1;
        builderState_efb.blocks.splice(dragSrcIndex < targetIndex ? insertAt - 1 : insertAt, 0, moved);
        renderCanvas_efb();
        syncToTextarea_efb();
      });
    });

    // Attach canvas-level listeners only once (canvas element persists across re-renders)
    if (!_canvasDropBound_efb) {
      _canvasDropBound_efb = true;

      canvas.addEventListener('dragover', (e) => {
        if (e.dataTransfer.types.includes('efb-new-block')) {
          e.preventDefault();
          e.dataTransfer.dropEffect = 'copy';
        }
      });

      canvas.addEventListener('drop', (e) => {
        const newBlockType = e.dataTransfer.getData('efb-new-block');
        if (newBlockType) {
          e.preventDefault();
          addBlockAt_efb(newBlockType, builderState_efb.blocks.length);
        }
      });
    }
  }

  /* ──────────────── BLOCK OPERATIONS ──────────────────────────── */

  function addBlock_efb(type) {
    addBlockAt_efb(type, builderState_efb.blocks.length);
  }

  function addBlockAt_efb(type, index) {
    const def = BLOCK_TYPES_efb[type];
    if (!def) return;
    saveState_efb();
    const block = {
      id: genId_efb(),
      type: type,
      data: JSON.parse(JSON.stringify(def.defaultData))
    };
    // For header, add default children
    if (type === 'header') {
      block.children = [
        { id: genId_efb(), type: 'logo', data: JSON.parse(JSON.stringify(BLOCK_TYPES_efb.logo.defaultData)) },
        { id: genId_efb(), type: 'title', data: JSON.parse(JSON.stringify(BLOCK_TYPES_efb.title.defaultData)) }
      ];
    }
    builderState_efb.blocks.splice(index, 0, block);
    builderState_efb.selectedBlock = block.id;
    renderCanvas_efb();
    renderPropertiesPanel_efb();
    syncToTextarea_efb();
  }

  function removeBlock_efb(id) {
    // Don't allow removing the only message block
    const block = builderState_efb.blocks.find(b => b.id === id);
    if (block && block.type === 'message') {
      const msgCount = builderState_efb.blocks.filter(b => b.type === 'message').length;
      if (msgCount <= 1) {
        showNotification_efb('⚠️ ' + t_efb('ebSCRequired', 'shortcode_message is required!'), 'warning');
        return;
      }
    }
    saveState_efb();
    builderState_efb.blocks = builderState_efb.blocks.filter(b => b.id !== id);
    if (builderState_efb.selectedBlock === id) {
      builderState_efb.selectedBlock = null;
    }
    renderCanvas_efb();
    renderPropertiesPanel_efb();
    syncToTextarea_efb();
  }

  function duplicateBlock_efb(id) {
    const idx = builderState_efb.blocks.findIndex(b => b.id === id);
    if (idx === -1) return;
    saveState_efb();
    const original = builderState_efb.blocks[idx];
    const copy = JSON.parse(JSON.stringify(original));
    copy.id = genId_efb();
    if (copy.children) copy.children.forEach(c => c.id = genId_efb());
    builderState_efb.blocks.splice(idx + 1, 0, copy);
    builderState_efb.selectedBlock = copy.id;
    renderCanvas_efb();
    renderPropertiesPanel_efb();
    syncToTextarea_efb();
  }

  function moveBlock_efb(id, direction) {
    const idx = builderState_efb.blocks.findIndex(b => b.id === id);
    if (idx === -1) return;
    const newIdx = idx + direction;
    if (newIdx < 0 || newIdx >= builderState_efb.blocks.length) return;
    saveState_efb();
    [builderState_efb.blocks[idx], builderState_efb.blocks[newIdx]] = [builderState_efb.blocks[newIdx], builderState_efb.blocks[idx]];
    renderCanvas_efb();
    syncToTextarea_efb();
  }

  function updateBlockData_efb(id, key, value) {
    const block = findBlockById_efb(id);
    if (!block) return;
    if (!block.data) block.data = {};
    block.data[key] = value;
    renderCanvas_efb();
    syncToTextarea_efb();
  }

  function findBlockById_efb(id) {
    for (const b of builderState_efb.blocks) {
      if (b.id === id) return b;
      if (b.children) {
        for (const c of b.children) {
          if (c.id === id) return c;
        }
      }
    }
    return null;
  }

  /* ──────────────── PROPERTIES PANEL ──────────────────────────── */

  function renderPropertiesPanel_efb() {
    const panel = document.getElementById('efb-properties-panel');
    if (!panel) return;

    if (!builderState_efb.selectedBlock) {
      panel.innerHTML = `
        <div class="efb-props-empty">
          <i class="efb bi-hand-index" style="font-size:32px;color:#cbd5e1;"></i>
          <p style="color:#94a3b8;margin-top:8px;font-size:13px;">${t_efb('ebSelectBlock', 'Select a block to edit its properties')}</p>
        </div>`;
      return;
    }

    const block = findBlockById_efb(builderState_efb.selectedBlock);
    if (!block) {
      panel.innerHTML = '';
      return;
    }

    const def = BLOCK_TYPES_efb[block.type];
    const data = Object.assign({}, def?.defaultData || {}, block.data || {});

    let html = `<div class="efb-props-header">
      <span><i class="efb ${def?.icon || 'bi-square'}"></i> ${def?.label || block.type}</span>
    </div>
    <div class="efb-props-body">`;

    // Generate property editors based on block type
    switch (block.type) {
      case 'header':
        html += propColor_efb('bgColor', t_efb('ebBgColor', 'Background Color'), data.bgColor);
        html += propInput_efb('bgGradient', t_efb('ebBgCSS', 'Background (CSS)'), data.bgGradient || data.bgColor);
        html += propInput_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        if (block.children) {
          html += `<div class="efb-props-divider"></div><h6 class="efb-props-subtitle">${t_efb('ebHeaderChildren', 'Header Children')}</h6>`;
          block.children.forEach((child, ci) => {
            const cd = Object.assign({}, BLOCK_TYPES_efb[child.type]?.defaultData || {}, child.data || {});
            html += `<div class="efb-child-props" data-child-id="${child.id}">
              <small style="color:#667eea;font-weight:600;">${BLOCK_TYPES_efb[child.type]?.label || child.type}</small>`;
            if (child.type === 'logo') {
              html += propInput_efb('src', t_efb('ebImageURL', 'Image URL'), cd.src, child.id);
              html += propInput_efb('width', t_efb('ebWidthPx', 'Width (px)'), cd.width, child.id);
              html += propInput_efb('alt', t_efb('ebAltText', 'Alt Text'), cd.alt, child.id);
            } else if (child.type === 'title') {
              html += propInput_efb('text', t_efb('text', 'Text'), cd.text, child.id);
              html += propShortcodeButtons_efb(child.id, 'text');
              html += propColor_efb('color', t_efb('clr', 'Color'), cd.color, child.id);
              html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), cd.fontFamily, child.id);
              html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), cd.fontSize, child.id);
            }
            html += `</div>`;
          });
        }
        break;

      case 'logo':
        html += propInput_efb('src', t_efb('ebImageURL', 'Image URL'), data.src);
        html += propInput_efb('width', t_efb('ebWidthPx', 'Width (px)'), data.width);
        html += propInput_efb('alt', t_efb('ebAltText', 'Alt Text'), data.alt);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        break;

      case 'title':
        html += propInput_efb('text', t_efb('ebTitleText', 'Title Text'), data.text);
        html += propShortcodeButtons_efb(block.id, 'text');
        html += propColor_efb('color', t_efb('clr', 'Color'), data.color);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propSelect_efb('fontWeight', t_efb('ebWeight', 'Weight'), data.fontWeight, ['300','400','500','600','700','800']);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        break;

      case 'text':
        html += propTextarea_efb('text', t_efb('content', 'Content'), data.text);
        html += propShortcodeButtons_efb(block.id, 'text');
        html += propColor_efb('color', t_efb('clr', 'Color'), data.color);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propInput_efb('lineHeight', t_efb('ebLineHeight', 'Line Height'), data.lineHeight);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        html += propInput_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
        break;

      case 'message':
        html += `<div class="efb-props-notice"><i class="efb bi-info-circle"></i> ${t_efb('ebMessageNotice', 'This block outputs <strong>shortcode_message</strong> — the submitted form data.')}</div>`;
        html += propInput_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
        html += propColor_efb('bgColor', t_efb('ebBackground', 'Background'), data.bgColor);
        html += propColor_efb('color', t_efb('ebTextColor', 'Text Color'), data.color);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        break;

      case 'button':
        html += propInput_efb('text', t_efb('ebButtonText', 'Button Text'), data.text);
        html += propShortcodeButtons_efb(block.id, 'text');
        html += propInput_efb('url', t_efb('ebLinkURL', 'Link URL'), data.url);
        html += propShortcodeButtons_efb(block.id, 'url');
        html += propColor_efb('bgColor', t_efb('ebBackground', 'Background'), data.bgColor);
        html += propColor_efb('textColor', t_efb('ebTextColor', 'Text Color'), data.textColor);
        html += propInput_efb('borderRadius', t_efb('ebBorderRadius', 'Border Radius (px)'), data.borderRadius);
        html += propInput_efb('padding', t_efb('ebInnerPadding', 'Inner Padding'), data.padding);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        html += propInput_efb('containerPadding', t_efb('ebOuterPadding', 'Outer Padding'), data.containerPadding);
        break;

      case 'divider':
        html += propColor_efb('color', t_efb('clr', 'Color'), data.color);
        html += propInput_efb('thickness', t_efb('ebThickness', 'Thickness (px)'), data.thickness);
        html += propRange_efb('width', t_efb('ebWidthPercent', 'Width (%)'), data.width, 10, 100);
        html += propInput_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
        break;

      case 'spacer':
        html += propRange_efb('height', t_efb('ebHeightPx', 'Height (px)'), data.height, 5, 100);
        html += propColor_efb('bgColor', t_efb('ebBackground', 'Background'), data.bgColor);
        break;

      case 'image':
        html += propInput_efb('src', t_efb('ebImageURL', 'Image URL'), data.src);
        html += propInput_efb('alt', t_efb('ebAltText', 'Alt Text'), data.alt);
        html += propInput_efb('width', t_efb('width', 'Width'), data.width);
        html += propSelect_efb('widthUnit', t_efb('ebWidthUnit', 'Width Unit'), data.widthUnit, ['%', 'px']);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        html += propInput_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
        html += propInput_efb('link', t_efb('ebLinkURL', 'Link URL'), data.link);
        break;

      case 'columns':
        html += propTextarea_efb('leftContent', t_efb('ebLeftColumn', 'Left Column'), data.leftContent);
        html += propShortcodeButtons_efb(block.id, 'leftContent');
        html += propTextarea_efb('rightContent', t_efb('ebRightColumn', 'Right Column'), data.rightContent);
        html += propShortcodeButtons_efb(block.id, 'rightContent');
        html += propColor_efb('leftColor', t_efb('ebLeftTextColor', 'Left Text Color'), data.leftColor);
        html += propColor_efb('rightColor', t_efb('ebRightTextColor', 'Right Text Color'), data.rightColor);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propInput_efb('gap', t_efb('ebGap', 'Gap (px)'), data.gap);
        html += propColor_efb('bgColor', t_efb('ebBackground', 'Background'), data.bgColor);
        html += propInput_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
        break;

      case 'social':
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        html += propColor_efb('color', t_efb('ebLinkColor', 'Link Color'), data.color);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propInput_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
        html += `<div class="efb-props-divider"></div><h6 class="efb-props-subtitle">${t_efb('ebLinks', 'Links')}</h6>`;
        (data.links || []).forEach((link, li) => {
          html += `<div class="efb-social-link-row">
            <input type="text" class="efb-prop-input" value="${escHtml_efb(link.name)}" placeholder="${t_efb('name', 'Name')}"
              onchange="efbEmailBuilder.updateSocialLink_efb('${block.id}',${li},'name',this.value)" />
            <input type="text" class="efb-prop-input" value="${escHtml_efb(link.url)}" placeholder="${t_efb('url', 'URL')}"
              onchange="efbEmailBuilder.updateSocialLink_efb('${block.id}',${li},'url',this.value)" />
            <button class="efb-blk-btn efb-blk-btn-danger" onclick="efbEmailBuilder.removeSocialLink_efb('${block.id}',${li})"><i class="efb bi-x"></i></button>
          </div>`;
        });
        html += `<button class="efb-btn-sm efb-btn-add" onclick="efbEmailBuilder.addSocialLink_efb('${block.id}')"><i class="efb bi-plus"></i> ${t_efb('ebAddLink', 'Add Link')}</button>`;
        break;

      case 'footer':
        html += propTextarea_efb('text', t_efb('ebFooterText', 'Footer Text'), data.text);
        html += propShortcodeButtons_efb(block.id, 'text');
        html += propColor_efb('color', t_efb('ebTextColor', 'Text Color'), data.color);
        html += propColor_efb('bgColor', t_efb('ebBackground', 'Background'), data.bgColor);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        html += propInput_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
        break;

      case 'htmlBlock':
        html += propTextarea_efb('html', t_efb('htmlCode', 'HTML Code'), data.html, null, 8);
        html += `<div class="efb-props-notice"><i class="efb bi-exclamation-triangle"></i> ${t_efb('ebNoScript', 'Use email-safe HTML only. No &lt;script&gt; tags.')}</div>`;
        break;
    }

    html += '</div>';
    panel.innerHTML = html;
    _propStateSaved_efb = false;
  }

  /* ──────────── PROPERTY FIELD GENERATORS ──────────────────────── */

  function propInput_efb(key, label, value, targetId) {
    const blockId = targetId || builderState_efb.selectedBlock;
    return `<div class="efb-prop-row">
      <label class="efb-prop-label">${label}</label>
      <input type="text" class="efb-prop-input" data-prop="${key}" data-block="${blockId}" value="${escHtml_efb(value)}" />
    </div>`;
  }

  function propTextarea_efb(key, label, value, targetId, rows) {
    const blockId = targetId || builderState_efb.selectedBlock;
    return `<div class="efb-prop-row">
      <label class="efb-prop-label">${label}</label>
      <textarea class="efb-prop-textarea" data-prop="${key}" data-block="${blockId}" rows="${rows || 3}">${escHtml_efb(value)}</textarea>
    </div>`;
  }

  function propColor_efb(key, label, value, targetId) {
    const blockId = targetId || builderState_efb.selectedBlock;
    return `<div class="efb-prop-row efb-prop-color-row">
      <label class="efb-prop-label">${label}</label>
      <div class="efb-color-picker-wrap">
        <input type="color" class="efb-prop-color" data-prop="${key}" data-block="${blockId}" value="${value && value.startsWith('#') ? value : '#333333'}" />
        <input type="text" class="efb-prop-input efb-prop-color-text" data-prop="${key}" data-block="${blockId}" value="${escHtml_efb(value)}" />
      </div>
    </div>`;
  }

  function propSelect_efb(key, label, value, options, targetId) {
    const blockId = targetId || builderState_efb.selectedBlock;
    const opts = options.map(o => `<option value="${o}" ${o == value ? 'selected' : ''}>${o}</option>`).join('');
    return `<div class="efb-prop-row">
      <label class="efb-prop-label">${label}</label>
      <select class="efb-prop-select" data-prop="${key}" data-block="${blockId}">${opts}</select>
    </div>`;
  }

  function propRange_efb(key, label, value, min, max, targetId) {
    const blockId = targetId || builderState_efb.selectedBlock;
    return `<div class="efb-prop-row">
      <label class="efb-prop-label">${label}: <span class="efb-range-val">${value}</span></label>
      <input type="range" class="efb-prop-range" data-prop="${key}" data-block="${blockId}" value="${value}" min="${min}" max="${max}" />
    </div>`;
  }

  function propFontFamily_efb(key, label, value, targetId) {
    const blockId = targetId || builderState_efb.selectedBlock;
    const safeVal = value || '';
    const opts = EMAIL_SAFE_FONTS_efb.map(f => {
      const sel = (safeVal === f.value) ? 'selected' : '';
      return `<option value="${escHtml_efb(f.value)}" ${sel} style="font-family:${f.value};">${f.label}</option>`;
    }).join('');
    const isDefault = !safeVal || safeVal === DEFAULT_FONT_efb;
    return `<div class="efb-prop-row">
      <label class="efb-prop-label">${label}</label>
      <select class="efb-prop-select efb-prop-font-select" data-prop="${key}" data-block="${blockId}">
        <option value="" ${isDefault ? 'selected' : ''}>${t_efb('ebDefaultFont', '— Default —')}</option>
        ${opts}
      </select>
    </div>`;
  }

  function propFontSize_efb(key, label, value, targetId) {
    const blockId = targetId || builderState_efb.selectedBlock;
    const sizes = ['10','11','12','13','14','15','16','17','18','20','22','24','26','28','30','32','36','40','48'];
    const opts = sizes.map(s => `<option value="${s}" ${String(value) === s ? 'selected' : ''}>${s}px</option>`).join('');
    return `<div class="efb-prop-row">
      <label class="efb-prop-label">${label}</label>
      <select class="efb-prop-select" data-prop="${key}" data-block="${blockId}">
        ${opts}
      </select>
    </div>`;
  }

  function propShortcodeButtons_efb(blockId, targetProp) {
    const shortcodes = [
      { code: 'shortcode_message', label: t_efb('ebSCMessage', 'Message *'), desc: t_efb('ebSCFormData', 'Form data') },
      { code: 'shortcode_title', label: t_efb('ebSCTitle', 'Title'), desc: t_efb('ebSCFormName', 'Form name') },
      { code: 'shortcode_website_name', label: t_efb('ebSCSiteName', 'Site Name'), desc: t_efb('ebSCBlogName', 'Blog name') },
      { code: 'shortcode_website_url', label: t_efb('ebSCSiteURL', 'Site URL'), desc: t_efb('ebSCHomeURL', 'Home URL') },
      { code: 'shortcode_admin_email', label: t_efb('ebSCAdminEmail', 'Admin Email'), desc: t_efb('ebSCAdminEmailDesc', 'Admin email') }
    ];
    return `<div class="efb-shortcode-btns">
      <small style="color:#64748b;">${t_efb('ebInsertShortcode', 'Insert shortcode:')}</small>
      <div class="efb-sc-btn-wrap">
        ${shortcodes.map(sc =>
          `<button type="button" class="efb-sc-btn" title="${sc.desc}"
            onclick="efbEmailBuilder.insertShortcode_efb('${blockId}','${targetProp}','${sc.code}')">${sc.label}</button>`
        ).join('')}
      </div>
    </div>`;
  }

  /* ──────────── PROPERTY EVENT DELEGATION ──────────────────────── */

  let _propDebounce_efb = null;
  let _propStateSaved_efb = false;

  /**
   * Delegated event handling for the properties panel.
   * Called ONCE during init — survives all panel re-renders.
   */
  function initPropertyDelegation_efb() {
    const panel = document.getElementById('efb-properties-panel');
    if (!panel) return;

    function applyPropChange(target, immediate) {
      const bid = target.dataset.block;
      const prop = target.dataset.prop;
      if (!bid || !prop) return;

      const b = findBlockById_efb(bid);
      if (!b) {
        console.warn('[EFB] Block not found for property edit:', bid);
        return;
      }
      if (!b.data) b.data = {};

      // save undo state once per editing session
      if (!_propStateSaved_efb) {
        saveState_efb();
        _propStateSaved_efb = true;
      }

      b.data[prop] = target.value;

      if (immediate) {
        renderCanvas_efb();
        syncToTextarea_efb();
      } else {
        clearTimeout(_propDebounce_efb);
        _propDebounce_efb = setTimeout(() => {
          renderCanvas_efb();
          syncToTextarea_efb();
        }, 250);
      }
    }

    // ── input event (text inputs, textareas, color pickers, ranges) ──
    panel.addEventListener('input', (e) => {
      const t = e.target;

      // Range sliders — immediate
      if (t.matches('.efb-prop-range')) {
        const val = t.closest('.efb-prop-row')?.querySelector('.efb-range-val');
        if (val) val.textContent = t.value;
        applyPropChange(t, true);
        return;
      }

      // Color pickers — immediate + sync text input
      if (t.matches('.efb-prop-color')) {
        applyPropChange(t, true);
        const textInput = panel.querySelector(
          `.efb-prop-color-text[data-prop="${t.dataset.prop}"][data-block="${t.dataset.block}"]`
        );
        if (textInput) textInput.value = t.value;
        return;
      }

      // Color text inputs — debounced + sync color picker
      if (t.matches('.efb-prop-color-text')) {
        const bid = t.dataset.block;
        const prop = t.dataset.prop;
        const b = findBlockById_efb(bid);
        if (b) {
          if (!b.data) b.data = {};
          if (!_propStateSaved_efb) { saveState_efb(); _propStateSaved_efb = true; }
          b.data[prop] = t.value;
          const cp = panel.querySelector(`.efb-prop-color[data-prop="${prop}"][data-block="${bid}"]`);
          if (cp && /^#[0-9a-fA-F]{6}$/.test(t.value)) cp.value = t.value;
          clearTimeout(_propDebounce_efb);
          _propDebounce_efb = setTimeout(() => { renderCanvas_efb(); syncToTextarea_efb(); }, 250);
        }
        return;
      }

      // Regular text inputs — debounced
      if (t.matches('.efb-prop-input')) {
        applyPropChange(t, false);
        return;
      }

      // Textareas — debounced
      if (t.matches('.efb-prop-textarea')) {
        applyPropChange(t, false);
        return;
      }
    });

    // ── change event (selects — fire immediately) ──
    panel.addEventListener('change', (e) => {
      if (e.target.matches('.efb-prop-select')) {
        applyPropChange(e.target, true);
      }
    });
  }

  /* ──────────── SHORTCODE INSERTION ──────────────────────────── */

  function insertShortcode_efb(blockId, propName, shortcode) {
    const block = findBlockById_efb(blockId);
    if (!block) return;
    if (!block.data) block.data = {};
    const def = BLOCK_TYPES_efb[block.type]?.defaultData || {};
    const current = block.data[propName] !== undefined ? block.data[propName] : (def[propName] || '');
    block.data[propName] = current + shortcode;
    renderCanvas_efb();
    renderPropertiesPanel_efb();
    syncToTextarea_efb();
  }

  /* ──────────── SOCIAL LINK HELPERS ─────────────────────────── */

  function updateSocialLink_efb(blockId, index, key, value) {
    const block = findBlockById_efb(blockId);
    if (!block || !block.data?.links?.[index]) return;
    block.data.links[index][key] = value;
    renderCanvas_efb();
    syncToTextarea_efb();
  }

  function addSocialLink_efb(blockId) {
    const block = findBlockById_efb(blockId);
    if (!block) return;
    if (!block.data) block.data = {};
    if (!block.data.links) block.data.links = [];
    block.data.links.push({ name: t_efb('link', 'Link'), url: '#' });
    renderPropertiesPanel_efb();
    syncToTextarea_efb();
  }

  function removeSocialLink_efb(blockId, index) {
    const block = findBlockById_efb(blockId);
    if (!block?.data?.links) return;
    block.data.links.splice(index, 1);
    renderCanvas_efb();
    renderPropertiesPanel_efb();
    syncToTextarea_efb();
  }

  /* ──────────── NOTIFICATION ────────────────────────────────── */

  function showNotification_efb(msg, type) {
    const el = document.createElement('div');
    el.className = `efb-builder-notification efb-notif-${type || 'info'}`;
    el.innerHTML = msg;
    const builder = document.getElementById(BUILDER_ID_efb);
    if (builder) builder.appendChild(el);
    setTimeout(() => el.remove(), 3500);
  }

  /* ──────────── PREVIEW ────────────────────────────────────── */

  function showPreview_efb() {
    const html = generateFullHTML_efb();
    if (!html.includes('shortcode_message')) {
      showNotification_efb('⚠️ ' + t_efb('ebMustContainSC', 'Template must contain shortcode_message!'), 'warning');
      return;
    }

    // Replace shortcodes with sample data for preview
    let preview = html
      .replace(/shortcode_message/g, '<div style="background:#f0fdf4;padding:15px;border-radius:8px;border:1px solid #bbf7d0;"><strong>' + t_efb('name', 'Name') + ':</strong> John Doe<br><strong>' + t_efb('email', 'Email') + ':</strong> john@example.com<br><strong>' + t_efb('message', 'Message') + ':</strong> This is a sample form submission.</div>')
      .replace(/shortcode_title/g, t_efb('message', 'New Message'))
      .replace(/shortcode_website_name/g, 'My Website')
      .replace(/shortcode_website_url/g, '#')
      .replace(/shortcode_admin_email/g, 'admin@example.com');

    // Render inside an iframe so the email HTML gets its own document context
    // (avoids inheriting WP admin RTL direction, styles, etc.)
    if (typeof show_modal_efb === 'function') {
      const blob = new Blob([preview], { type: 'text/html;charset=utf-8' });
      const blobUrl = URL.createObjectURL(blob);
      const iframeHtml = `<iframe src="${blobUrl}" style="width:100%;height:70vh;border:none;border-radius:8px;background:#fff;" onload="try{URL.revokeObjectURL(this.src)}catch(e){}"></iframe>`;
      show_modal_efb(iframeHtml, t_efb('preview', 'Preview'), '', 'saveBox');
      if (typeof state_modal_show_efb === 'function') state_modal_show_efb(1);
    } else {
      // Fallback to new window
      const win = window.open('', '_blank', 'width=700,height=800');
      if (win) {
        win.document.write(preview);
        win.document.close();
      }
    }
  }

  /* ──────────── GLOBAL SETTINGS PANEL ──────────────────────── */

  function renderGlobalSettings_efb() {
    const panel = document.getElementById('efb-global-settings');
    if (!panel) return;
    const gs = builderState_efb.globalSettings;
    panel.innerHTML = `
      <div class="efb-props-body">
        <div class="efb-prop-row efb-prop-color-row">
          <label class="efb-prop-label">${t_efb('ebEmailBg', 'Email Background')}</label>
          <div class="efb-color-picker-wrap">
            <input type="color" class="efb-gs-color" data-gs="bgColor" value="${gs.bgColor}" />
            <input type="text" class="efb-prop-input efb-gs-text" data-gs="bgColor" value="${gs.bgColor}" />
          </div>
        </div>
        <div class="efb-prop-row efb-prop-color-row">
          <label class="efb-prop-label">${t_efb('ebContentBg', 'Content Background')}</label>
          <div class="efb-color-picker-wrap">
            <input type="color" class="efb-gs-color" data-gs="contentBgColor" value="${gs.contentBgColor}" />
            <input type="text" class="efb-prop-input efb-gs-text" data-gs="contentBgColor" value="${gs.contentBgColor}" />
          </div>
        </div>
        <div class="efb-prop-row">
          <label class="efb-prop-label">${t_efb('ebContentWidth', 'Content Width (px)')}</label>
          <input type="text" class="efb-prop-input efb-gs-input" data-gs="contentWidth" value="${gs.contentWidth}" />
        </div>
        <div class="efb-prop-row">
          <label class="efb-prop-label">${t_efb('ebBorderRadius', 'Border Radius (px)')}</label>
          <input type="text" class="efb-prop-input efb-gs-input" data-gs="borderRadius" value="${gs.borderRadius}" />
        </div>
        <div class="efb-prop-row">
          <label class="efb-prop-label">${t_efb('ebDefaultFont', 'Default Font')}</label>
          <select class="efb-prop-select efb-gs-select" data-gs="fontFamily">
            ${EMAIL_SAFE_FONTS_efb.map(f => {
              const sel = (gs.fontFamily === f.value) ? 'selected' : '';
              return `<option value="${escHtml_efb(f.value)}" ${sel} style="font-family:${f.value};">${f.label}</option>`;
            }).join('')}
          </select>
        </div>
        <div class="efb-prop-row">
          <label class="efb-prop-label">${t_efb('ebDirection', 'Direction')}</label>
          <select class="efb-prop-select efb-gs-select" data-gs="direction">
            <option value="ltr" ${gs.direction==='ltr'?'selected':''}>LTR</option>
            <option value="rtl" ${gs.direction==='rtl'?'selected':''}>RTL</option>
          </select>
        </div>
      </div>`;

    // Apply current global styles to canvas visually
    updateCanvasGlobalStyles_efb();

    // Debounce helper for global settings
    let _gsDebounce_efb = null;
    function gsChanged_efb() {
      clearTimeout(_gsDebounce_efb);
      _gsDebounce_efb = setTimeout(() => {
        updateCanvasGlobalStyles_efb();
        syncToTextarea_efb();
      }, 200);
    }

    // Bind using event delegation on the panel
    panel.addEventListener('input', (e) => {
      const t = e.target;

      // Color picker — immediate visual update
      if (t.matches('.efb-gs-color')) {
        builderState_efb.globalSettings[t.dataset.gs] = t.value;
        const txt = panel.querySelector(`.efb-gs-text[data-gs="${t.dataset.gs}"]`);
        if (txt) txt.value = t.value;
        updateCanvasGlobalStyles_efb();
        clearTimeout(_gsDebounce_efb);
        _gsDebounce_efb = setTimeout(() => { syncToTextarea_efb(); }, 200);
        return;
      }

      // Color text input — debounced + sync color picker
      if (t.matches('.efb-gs-text')) {
        builderState_efb.globalSettings[t.dataset.gs] = t.value;
        const clr = panel.querySelector(`.efb-gs-color[data-gs="${t.dataset.gs}"]`);
        if (clr && /^#[0-9a-fA-F]{6}$/.test(t.value)) clr.value = t.value;
        gsChanged_efb();
        return;
      }

      // Text inputs (contentWidth, borderRadius) — debounced
      if (t.matches('.efb-gs-input')) {
        builderState_efb.globalSettings[t.dataset.gs] = t.value;
        gsChanged_efb();
        return;
      }
    });

    // Select (direction) — fires on change, immediate
    panel.addEventListener('change', (e) => {
      if (e.target.matches('.efb-gs-select')) {
        builderState_efb.globalSettings[e.target.dataset.gs] = e.target.value;
        updateCanvasGlobalStyles_efb();
        syncToTextarea_efb();
      }
    });
  }

  /** Apply global settings visually to the canvas wrapper */
  function updateCanvasGlobalStyles_efb() {
    const gs = builderState_efb.globalSettings;
    const canvas = document.getElementById(CANVAS_ID_efb);
    if (!canvas) return;

    canvas.style.backgroundColor = gs.contentBgColor || '#ffffff';
    canvas.style.maxWidth = (gs.contentWidth || 600) + 'px';
    canvas.style.borderRadius = (gs.borderRadius || 0) + 'px';
    canvas.style.direction = gs.direction || 'ltr';
    canvas.style.fontFamily = gs.fontFamily || DEFAULT_FONT_efb;

    // Apply email background to the canvas wrapper
    const wrap = canvas.closest('.efb-builder-canvas-wrap');
    if (wrap) wrap.style.backgroundColor = gs.bgColor || '#f5f5f5';
  }

  /* ──────────── UTILITY ────────────────────────────────────── */

  function escHtml_efb(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* ──────────── INSERT SHORTCODE FROM PANEL ───────────────────── */

  function insertShortcodeFromPanel_efb(shortcode) {
    // If a block is selected, try to insert shortcode into its main text property
    if (builderState_efb.selectedBlock) {
      const block = findBlockById_efb(builderState_efb.selectedBlock);
      if (block) {
        const textProps = {
          'header': null,
          'logo': null,
          'title': 'text',
          'text': 'text',
          'message': null,
          'button': 'text',
          'divider': null,
          'spacer': null,
          'image': null,
          'columns': 'leftContent',
          'social': null,
          'footer': 'text',
          'htmlBlock': 'html'
        };
        const prop = textProps[block.type];
        if (prop) {
          insertShortcode_efb(block.id, prop, shortcode);
          showNotification_efb('✅ ' + t_efb('ebSCInserted', 'Shortcode inserted!'), 'success');
          return;
        }
      }
    }
    // No suitable block selected — copy to clipboard instead
    showNotification_efb('💡 ' + t_efb('ebSCSelectBlock', 'Select a text block first, or shortcode copied to clipboard.'), 'info');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(shortcode).catch(() => {});
    }
  }

  /* ──────────── COPY SHORTCODE TO CLIPBOARD ───────────────────── */

  function copyShortcode_efb(code, btn) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(code).then(() => {
        showCopyFeedback_efb(btn);
      }).catch(() => {
        fallbackCopy_efb(code, btn);
      });
    } else {
      fallbackCopy_efb(code, btn);
    }
  }

  function fallbackCopy_efb(text, btn) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); showCopyFeedback_efb(btn); } catch(e) {}
    document.body.removeChild(ta);
  }

  function showCopyFeedback_efb(btn) {
    if (!btn) return;
    const icon = btn.querySelector('i');
    if (icon) {
      icon.className = 'efb bi-check-lg';
      btn.classList.add('efb-sc-copied');
    }
    showNotification_efb('✅ ' + t_efb('ebCopied', 'Copied!'), 'success');
    setTimeout(() => {
      if (icon) icon.className = 'efb bi-clipboard';
      btn.classList.remove('efb-sc-copied');
    }, 1500);
  }

  /* ──────────── EXPORT / IMPORT HTML ────────────────────────── */

  function exportHTML_efb() {
    const html = generateFullHTML_efb();
    const blob = new Blob([html], { type: 'text/html' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'email-template.html';
    a.click();
    URL.revokeObjectURL(a.href);
    showNotification_efb('✅ ' + t_efb('ebTemplateExported', 'Template exported!'), 'success');
  }

  function showCodeEditor_efb() {
    const textarea = document.getElementById(TEXTAREA_ID_efb);
    const codePanel = document.getElementById('efb-code-editor-panel');
    const codeArea = document.getElementById('efb-code-editor-textarea');
    if (!codePanel || !codeArea) return;

    // Show clean HTML without EFBDATA metadata
    codeArea.value = generateFullHTML_efb();
    codePanel.style.display = codePanel.style.display === 'none' ? 'block' : 'none';
  }

  function applyCodeEditor_efb() {
    const codeArea = document.getElementById('efb-code-editor-textarea');
    const textarea = document.getElementById(TEXTAREA_ID_efb);
    if (!codeArea || !textarea) return;

    const code = codeArea.value;
    if (code.includes('<script')) {
      showNotification_efb('⚠️ ' + t_efb('NAllowedscriptTag', 'Script tags are not allowed!'), 'warning');
      return;
    }
    textarea.value = code;
    // Reset builder to htmlBlock mode with this code
    builderState_efb.blocks = [{
      id: genId_efb(),
      type: 'htmlBlock',
      data: { html: code }
    }];
    if (!code.includes('shortcode_message')) {
      builderState_efb.blocks.push({
        id: genId_efb(),
        type: 'message',
        data: {}
      });
    }
    renderCanvas_efb();
    syncToTextarea_efb();
    document.getElementById('efb-code-editor-panel').style.display = 'none';
    showNotification_efb('✅ ' + t_efb('ebHTMLApplied', 'HTML code applied!'), 'success');
  }

  /* ──────────── RENDER BLOCKS PANEL (LEFT SIDEBAR) ─────────── */

  function renderBlocksPanel_efb() {
    const panel = document.getElementById('efb-blocks-panel');
    if (!panel) return;

    const categories = {
      'layout': { label: '📐 ' + t_efb('ebCatLayout', 'Layout'), blocks: [] },
      'content': { label: '📝 ' + t_efb('ebCatContent', 'Content'), blocks: [] },
      'shortcode': { label: '🔗 ' + t_efb('ebCatShortcodes', 'Shortcodes'), blocks: [] },
      'advanced': { label: '⚙️ ' + t_efb('ebCatAdvanced', 'Advanced'), blocks: [] }
    };

    for (const [type, def] of Object.entries(BLOCK_TYPES_efb)) {
      const cat = categories[def.category] || categories.advanced;
      cat.blocks.push({ type, ...def });
    }

    // Shortcode definitions for the shortcode category tooltip buttons
    const shortcodeDefs = [
      { code: 'shortcode_message', label: t_efb('ebSCMessage', 'Message *'), desc: t_efb('shortcodeMessageInfo', 'Add this shortcode inside an HTML tag to display the message content of an email.'), required: true },
      { code: 'shortcode_title', label: t_efb('ebSCTitle', 'Title'), desc: t_efb('shortcodeTitleInfo', 'Add this shortcode inside a tag to display the title of the email.'), required: false },
      { code: 'shortcode_website_name', label: t_efb('ebSCSiteName', 'Site Name'), desc: t_efb('shortcodeWebsiteNameInfo', 'To display the website name, add this shortcode inside a HTML tag.'), required: false },
      { code: 'shortcode_website_url', label: t_efb('ebSCSiteURL', 'Site URL'), desc: t_efb('shortcodeWebsiteUrlInfo', 'Add this shortcode within a HTML tag to display the Website URL.'), required: false },
      { code: 'shortcode_admin_email', label: t_efb('ebSCAdminEmail', 'Admin Email'), desc: t_efb('shortcodeAdminEmailInfo', 'You can display the Admin Email address of your WordPress site by adding this shortcode within an HTML tag.'), required: false }
    ];

    let html = '';
    for (const [catKey, cat] of Object.entries(categories)) {
      if (catKey === 'shortcode') {
        // Special rendering for shortcode category
        html += `<div class="efb-block-category">
          <div class="efb-cat-label">${cat.label}</div>
          <div class="efb-cat-blocks">`;

        // Add the message block as draggable (required block)
        cat.blocks.forEach(b => {
          html += `<div class="efb-draggable-block efb-sc-draggable-required" draggable="true" data-block-type="${b.type}" title="${b.label}">
            <i class="efb ${b.icon}"></i>
            <span>${b.label}</span>
            <span class="efb-sc-badge-required">*</span>
          </div>`;
        });

        // Shortcode reference section header
        html += `<div class="efb-sc-section-header">
          <i class="efb bi-code-square"></i>
          <span>${t_efb('ebSCReference', 'Shortcode Reference')}</span>
        </div>`;

        // All 5 shortcode items with tooltips, copy and insert
        shortcodeDefs.forEach(sc => {
          const reqClass = sc.required ? ' efb-sc-item-required' : '';
          const reqBadge = sc.required ? `<span class="efb-sc-req-dot" title="${t_efb('ebSCRequired', 'Required')}">●</span>` : '';
          html += `<div class="efb-sc-item${reqClass}">
            <div class="efb-sc-item-top">
              <div class="efb-sc-item-info" data-efb-tooltip="${escHtml_efb(sc.desc)}">
                <span class="efb-sc-item-label">${reqBadge}${sc.label}</span>
                <code class="efb-sc-item-code">${sc.code}</code>
              </div>
              <div class="efb-sc-item-actions">
                <button type="button" class="efb-sc-action-btn efb-sc-insert-btn" onclick="efbEmailBuilder.insertShortcodeFromPanel_efb('${sc.code}')" title="${t_efb('ebInsertShortcode', 'Insert shortcode')}">
                  <i class="efb bi-plus-circle"></i>
                </button>
                <button type="button" class="efb-sc-action-btn efb-sc-copy-btn" onclick="efbEmailBuilder.copyShortcode_efb('${sc.code}', this)" title="${t_efb('ebCopyShortcode', 'Copy shortcode')}">
                  <i class="efb bi-clipboard"></i>
                </button>
              </div>
            </div>
          </div>`;
        });

        html += `</div></div>`;
        continue;
      }

      if (cat.blocks.length === 0) continue;
      html += `<div class="efb-block-category">
        <div class="efb-cat-label">${cat.label}</div>
        <div class="efb-cat-blocks">`;
      cat.blocks.forEach(b => {
        html += `<div class="efb-draggable-block" draggable="true" data-block-type="${b.type}" title="${b.label}">
          <i class="efb ${b.icon}"></i>
          <span>${b.label}</span>
        </div>`;
      });
      html += `</div></div>`;
    }

    panel.innerHTML = html;

    // Make blocks draggable to canvas
    panel.querySelectorAll('.efb-draggable-block').forEach(el => {
      el.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('efb-new-block', el.dataset.blockType);
        e.dataTransfer.effectAllowed = 'copy';
      });
      // Also allow click to add
      el.addEventListener('click', () => {
        addBlock_efb(el.dataset.blockType);
      });
    });
  }

  /* ──────────── TEMPLATES PANEL ────────────────────────────── */

  function renderTemplatesPanel_efb() {
    const panel = document.getElementById('efb-templates-panel');
    if (!panel) return;

    let html = '<div class="efb-templates-grid">';
    for (const [name, tpl] of Object.entries(TEMPLATES_efb)) {
      html += `<div class="efb-template-card" onclick="efbEmailBuilder.loadTemplate_efb('${name}')">
        <div class="efb-tpl-icon"><i class="efb ${tpl.icon}" style="font-size:24px;"></i></div>
        <div class="efb-tpl-name">${tpl.label}</div>
        <div class="efb-tpl-count">${tpl.blocks.length} ${t_efb('ebBlkCount', 'blocks')}</div>
      </div>`;
    }
    html += '</div>';
    panel.innerHTML = html;
  }

  /* ──────────── MAIN BUILDER RENDERER ──────────────────────── */

  function initBuilder_efb() {
    const container = document.getElementById(BUILDER_ID_efb);
    if (!container) return;

    container.innerHTML = `
      <div class="efb-builder-toolbar">
        <div class="efb-toolbar-left">
          <button class="efb-tb-btn" onclick="efbEmailBuilder.undo_efb()" title="${t_efb('ebUndo', 'Undo')} (Ctrl+Z)"><i class="efb bi-arrow-counterclockwise"></i></button>
          <button class="efb-tb-btn" onclick="efbEmailBuilder.redo_efb()" title="${t_efb('ebRedo', 'Redo')} (Ctrl+Y)"><i class="efb bi-arrow-clockwise"></i></button>
          <span class="efb-tb-sep"></span>
          <button class="efb-tb-btn efb-tb-primary" onclick="efbEmailBuilder.showPreview_efb()"><i class="efb bi-eye"></i> ${t_efb('preview', 'Preview')}</button>
          <button class="efb-tb-btn" onclick="efbEmailBuilder.exportHTML_efb()"><i class="efb bi-download"></i> ${t_efb('ebExport', 'Export')}</button>
          <button class="efb-tb-btn" onclick="efbEmailBuilder.showCodeEditor_efb()"><i class="efb bi-code-slash"></i> HTML</button>
        </div>
        <div class="efb-toolbar-right">
          <button class="efb-tb-btn efb-tb-danger" onclick="efbEmailBuilder.resetBuilder_efb()"><i class="efb bi-trash"></i> ${t_efb('reset', 'Reset')}</button>
        </div>
      </div>

      <div class="efb-builder-layout">
        <!-- Left: Blocks & Templates -->
        <div class="efb-builder-sidebar-left">
          <div class="efb-sidebar-tabs">
            <button class="efb-stab active" data-tab="blocks" onclick="efbEmailBuilder.switchSidebarTab_efb('blocks',this)">${t_efb('ebBlocks', 'Blocks')}</button>
            <button class="efb-stab" data-tab="templates" onclick="efbEmailBuilder.switchSidebarTab_efb('templates',this)">${t_efb('templates', 'Templates')}</button>
            <button class="efb-stab" data-tab="settings" onclick="efbEmailBuilder.switchSidebarTab_efb('settings',this)">${t_efb('setting', 'Settings')}</button>
          </div>
          <div class="efb-sidebar-content">
            <div id="efb-blocks-panel" class="efb-stab-panel active"></div>
            <div id="efb-templates-panel" class="efb-stab-panel"></div>
            <div id="efb-global-settings" class="efb-stab-panel"></div>
          </div>
        </div>

        <!-- Center: Canvas -->
        <div class="efb-builder-canvas-wrap">
          <div id="${CANVAS_ID_efb}" class="efb-builder-canvas"></div>
        </div>

        <!-- Right: Properties -->
        <div class="efb-builder-sidebar-right">
          <div class="efb-sidebar-rtitle">${t_efb('ebProperties', 'Properties')}</div>
          <div id="efb-properties-panel"></div>
        </div>
      </div>

      <!-- Code editor overlay -->
      <div id="efb-code-editor-panel" style="display:none;">
        <div class="efb-code-editor-header">
          <span><i class="efb bi-code-slash"></i> ${t_efb('ebHTMLSourceCode', 'HTML Source Code')}</span>
          <div>
            <button class="efb-tb-btn" onclick="efbEmailBuilder.applyCodeEditor_efb()"><i class="efb bi-check-lg"></i> ${t_efb('ebApply', 'Apply')}</button>
            <button class="efb-tb-btn" onclick="document.getElementById('efb-code-editor-panel').style.display='none'"><i class="efb bi-x-lg"></i> ${t_efb('close', 'Close')}</button>
          </div>
        </div>
        <textarea id="efb-code-editor-textarea" class="efb-code-textarea" spellcheck="false"></textarea>
      </div>

      <!-- Hidden builder JSON storage -->
      <input type="hidden" id="efb-builder-json" value="" />
    `;

    // Load existing template if any
    const textarea = document.getElementById(TEXTAREA_ID_efb);
    const existingHtml = textarea ? textarea.value : '';
    tryParseExistingTemplate_efb(existingHtml);

    // Render all panels
    renderBlocksPanel_efb();
    renderTemplatesPanel_efb();
    renderGlobalSettings_efb();
    renderCanvas_efb();
    renderPropertiesPanel_efb();
    initPropertyDelegation_efb();
    syncToTextarea_efb();

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      if (!document.getElementById(BUILDER_ID_efb)) return;
      if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) { e.preventDefault(); undo_efb(); }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); redo_efb(); }
      if (e.key === 'Delete' && builderState_efb.selectedBlock) {
        const active = document.activeElement;
        if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT')) return;
        removeBlock_efb(builderState_efb.selectedBlock);
      }
    });
  }

  function switchSidebarTab_efb(tab, btn) {
    const sidebar = btn.closest('.efb-builder-sidebar-left');
    sidebar.querySelectorAll('.efb-stab').forEach(b => b.classList.remove('active'));
    sidebar.querySelectorAll('.efb-stab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const panel = sidebar.querySelector(`#efb-${tab === 'settings' ? 'global-settings' : tab + '-panel'}`);
    if (panel) panel.classList.add('active');

    if (tab === 'settings') renderGlobalSettings_efb();
  }

  function resetBuilder_efb() {
    if (!confirm(t_efb('ebResetConfirm', 'Are you sure you want to reset the email template? This cannot be undone.'))) return;
    saveState_efb();
    builderState_efb.blocks = [];
    builderState_efb.selectedBlock = null;
    loadTemplate_efb('professional');
    showNotification_efb('✅ ' + t_efb('ebTemplateReset', 'Template reset to default!'), 'success');
  }

  /* ──────────── INJECT CSS ──────────────────────────────────── */

  function injectStyles_efb() {
    if (document.getElementById('efb-email-builder-styles')) return;
    const style = document.createElement('style');
    style.id = 'efb-email-builder-styles';
    style.textContent = `

    /* ── Builder Container ── */
    #${BUILDER_ID_efb} {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      background: #f8fafc;
      overflow: hidden;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      font-size: 13px;
      position: relative;
    }

    /* ── Toolbar ── */
    .efb-builder-toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 12px;
      background: #ffffff;
      border-bottom: 1px solid #e2e8f0;
      gap: 8px;
      flex-wrap: wrap;
    }
    .efb-toolbar-left, .efb-toolbar-right { display: flex; align-items: center; gap: 4px; }
    .efb-tb-btn {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 6px 10px;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      background: #ffffff;
      color: #475569;
      cursor: pointer;
      font-size: 12px;
      transition: all .15s;
      white-space: nowrap;
    }
    .efb-tb-btn:hover { background: #f1f5f9; border-color: #cbd5e1; }
    .efb-tb-btn.efb-tb-primary { background: #667eea; color: #fff; border-color: #667eea; }
    .efb-tb-btn.efb-tb-primary:hover { background: #5a6fd6; }
    .efb-tb-btn.efb-tb-danger { color: #ef4444; }
    .efb-tb-btn.efb-tb-danger:hover { background: #fef2f2; border-color: #fca5a5; }
    .efb-tb-sep { width: 1px; height: 20px; background: #e2e8f0; margin: 0 4px; }

    /* ── Layout ── */
    .efb-builder-layout {
      display: grid;
      grid-template-columns: 220px 1fr 260px;
      grid-template-rows: 1fr;
      min-height: 550px;
      max-height: 80vh;
      overflow: hidden;
    }

    /* ── Left Sidebar ── */
    .efb-builder-sidebar-left {
      background: #ffffff;
      border-right: 1px solid #e2e8f0;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      min-height: 0;
    }
    .efb-sidebar-tabs {
      display: flex;
      border-bottom: 1px solid #e2e8f0;
      background: #f8fafc;
    }
    .efb-stab {
      flex: 1;
      padding: 8px 4px;
      background: none;
      border: none;
      border-bottom: 2px solid transparent;
      color: #64748b;
      font-size: 11px;
      font-weight: 600;
      cursor: pointer;
      transition: all .15s;
    }
    .efb-stab:hover { color: #334155; }
    .efb-stab.active { color: #667eea; border-bottom-color: #667eea; }
    .efb-sidebar-content { flex: 1; overflow-y: auto; min-height: 0; }
    .efb-stab-panel { display: none; padding: 8px; }
    .efb-stab-panel.active { display: block; }

    /* ── Blocks Panel ── */
    .efb-block-category { margin-bottom: 12px; }
    .efb-cat-label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; padding: 4px 0; letter-spacing: 0.5px; }
    .efb-cat-blocks { display: flex; flex-direction: column; gap: 3px; }
    .efb-draggable-block {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 7px 10px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      cursor: grab;
      font-size: 12px;
      color: #475569;
      transition: all .15s;
      user-select: none;
    }
    .efb-draggable-block:hover { background: #eef2ff; border-color: #a5b4fc; color: #4338ca; }
    .efb-draggable-block:active { cursor: grabbing; }
    .efb-draggable-block i { font-size: 14px; width: 16px; text-align: center; }

    /* ── Templates Panel ── */
    .efb-templates-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .efb-template-card {
      padding: 12px 8px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      text-align: center;
      cursor: pointer;
      transition: all .15s;
    }
    .efb-template-card:hover { border-color: #667eea; background: #eef2ff; transform: translateY(-1px); }
    .efb-tpl-icon { margin-bottom: 4px; color: #667eea; }
    .efb-tpl-name { font-size: 11px; font-weight: 600; color: #334155; }
    .efb-tpl-count { font-size: 10px; color: #94a3b8; }

    /* ── Canvas ── */
    .efb-builder-canvas-wrap {
      background: #f1f5f9;
      overflow-y: auto;
      padding: 20px;
    }
    .efb-builder-canvas {
      max-width: 620px;
      margin: 0 auto;
      min-height: 400px;
      background: #ffffff;
      border-radius: 8px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      padding: 8px;
    }
    .efb-empty-canvas {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 350px;
      text-align: center;
    }

    /* ── Canvas Blocks ── */
    .efb-canvas-block {
      position: relative;
      margin: 4px 0;
      border: 2px solid transparent;
      border-radius: 6px;
      transition: all .15s;
      cursor: pointer;
    }
    .efb-canvas-block:hover { border-color: #c7d2fe; }
    .efb-canvas-block.efb-block-selected { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
    .efb-canvas-block.efb-block-required { }
    .efb-block-label {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 3px 8px;
      background: #f1f5f9;
      border-radius: 4px 4px 0 0;
      font-size: 10px;
      color: #64748b;
      font-weight: 600;
      opacity: 0;
      transition: opacity .15s;
    }
    .efb-canvas-block:hover .efb-block-label,
    .efb-canvas-block.efb-block-selected .efb-block-label { opacity: 1; }
    .efb-block-actions { display: flex; gap: 2px; }
    .efb-blk-btn {
      width: 22px; height: 22px;
      display: inline-flex; align-items: center; justify-content: center;
      border: none; border-radius: 4px; background: transparent;
      color: #64748b; cursor: pointer; font-size: 11px;
      transition: all .1s;
    }
    .efb-blk-btn:hover { background: #e2e8f0; color: #334155; }
    .efb-blk-btn.efb-blk-btn-danger:hover { background: #fef2f2; color: #ef4444; }
    .efb-block-preview { padding: 4px; }

    /* Drag indicators */
    .efb-canvas-block.efb-dragging { opacity: 0.4; }
    .efb-canvas-block.efb-drop-above { border-top: 3px solid #667eea; }
    .efb-canvas-block.efb-drop-below { border-bottom: 3px solid #667eea; }

    /* ── Right Sidebar (Properties) ── */
    .efb-builder-sidebar-right {
      background: #ffffff;
      border-left: 1px solid #e2e8f0;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      min-height: 0;
    }
    .efb-sidebar-rtitle {
      padding: 10px 12px;
      font-size: 12px;
      font-weight: 700;
      color: #334155;
      border-bottom: 1px solid #e2e8f0;
      background: #f8fafc;
    }
    .efb-props-empty {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
      text-align: center;
    }
    .efb-props-header {
      padding: 10px 12px;
      background: #eef2ff;
      color: #4338ca;
      font-weight: 600;
      font-size: 12px;
      border-bottom: 1px solid #c7d2fe;
    }
    .efb-props-body { padding: 8px 12px; }
    .efb-props-divider { height: 1px; background: #e2e8f0; margin: 10px 0; }
    .efb-props-subtitle { font-size: 11px; font-weight: 700; color: #64748b; margin: 0 0 8px 0; }
    .efb-props-notice {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 6px;
      padding: 8px 10px;
      font-size: 11px;
      color: #1e40af;
      margin-bottom: 10px;
      line-height: 1.4;
    }

    /* ── Property Inputs ── */
    .efb-prop-row { margin-bottom: 8px; }
    .efb-prop-label {
      display: block;
      font-size: 11px;
      font-weight: 600;
      color: #64748b;
      margin-bottom: 3px;
    }
    .efb-prop-input, .efb-prop-textarea, .efb-prop-select {
      width: 100%;
      padding: 6px 8px;
      border: 1px solid #e2e8f0;
      border-radius: 5px;
      font-size: 12px;
      color: #334155;
      background: #ffffff;
      transition: border-color .15s;
      box-sizing: border-box;
      font-family: inherit;
    }
    .efb-prop-input:focus, .efb-prop-textarea:focus, .efb-prop-select:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }
    .efb-prop-textarea { resize: vertical; min-height: 50px; font-family: monospace; font-size: 11px; }
    .efb-prop-select { cursor: pointer; }
    .efb-prop-font-select { font-size: 13px; }
    .efb-prop-range { width: 100%; cursor: pointer; accent-color: #667eea; }

    /* Color picker */
    .efb-prop-color-row { }
    .efb-color-picker-wrap { display: flex; gap: 6px; align-items: center; }
    .efb-prop-color {
      width: 32px; height: 32px; padding: 1px; border: 1px solid #e2e8f0; border-radius: 6px;
      cursor: pointer; background: none; flex-shrink: 0;
    }
    .efb-prop-color-text { flex: 1; }

    /* ── Shortcode Buttons ── */
    .efb-shortcode-btns { margin: 4px 0 10px; }
    .efb-shortcode-btns small { display: block; margin-bottom: 4px; }
    .efb-sc-btn-wrap { display: flex; flex-wrap: wrap; gap: 3px; }
    .efb-sc-btn {
      padding: 3px 7px;
      background: #eef2ff;
      border: 1px solid #c7d2fe;
      border-radius: 4px;
      color: #4338ca;
      font-size: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all .1s;
    }
    .efb-sc-btn:hover { background: #c7d2fe; }

    /* ── Social link rows ── */
    .efb-social-link-row { display: flex; gap: 4px; margin-bottom: 4px; align-items: center; }
    .efb-social-link-row .efb-prop-input { flex: 1; }
    .efb-btn-sm { padding: 4px 8px; font-size: 11px; }
    .efb-btn-add {
      display: inline-flex; align-items: center; gap: 3px;
      background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px;
      color: #16a34a; cursor: pointer; font-size: 11px; padding: 4px 8px;
    }
    .efb-btn-add:hover { background: #dcfce7; }

    /* ── Child Properties ── */
    .efb-child-props {
      padding: 8px;
      margin: 6px 0;
      background: #fafbfc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
    }

    /* ── Code Editor ── */
    #efb-code-editor-panel {
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background: #1e293b;
      z-index: 100;
      display: flex;
      flex-direction: column;
    }
    .efb-code-editor-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 12px;
      background: #0f172a;
      color: #e2e8f0;
      font-size: 13px;
      font-weight: 600;
    }
    .efb-code-editor-header .efb-tb-btn { color: #e2e8f0; border-color: #475569; }
    .efb-code-editor-header .efb-tb-btn:hover { background: #334155; }
    .efb-code-textarea {
      flex: 1;
      width: 100%;
      padding: 16px;
      background: #1e293b;
      color: #e2e8f0;
      border: none;
      font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
      font-size: 13px;
      line-height: 1.6;
      resize: none;
      box-sizing: border-box;
      height: -webkit-fill-available;
    }
    .efb-code-textarea:focus { outline: none; }

    /* ── Notifications ── */
    .efb-builder-notification {
      position: absolute;
      top: 55px;
      left: 50%;
      transform: translateX(-50%);
      padding: 10px 20px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      z-index: 200;
      animation: efbNotifIn .3s ease;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .efb-notif-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .efb-notif-warning { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
    .efb-notif-info { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    @keyframes efbNotifIn { from { opacity: 0; transform: translateX(-50%) translateY(-10px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }

    /* ── Responsive ── */
    @media (max-width: 900px) {
      .efb-builder-layout {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr auto;
      }
      .efb-builder-sidebar-left { border-right: none; border-bottom: 1px solid #e2e8f0; max-height: 200px; }
      .efb-builder-sidebar-right { border-left: none; border-top: 1px solid #e2e8f0; max-height: 300px; }
    }

    /* ── Shortcode Section (Blocks Panel) ── */
    .efb-sc-draggable-required {
      border-color: #a5b4fc !important;
      background: #eef2ff !important;
      position: relative;
    }
    .efb-sc-draggable-required:hover {
      background: #e0e7ff !important;
      border-color: #818cf8 !important;
    }
    .efb-sc-badge-required {
      margin-left: auto;
      color: #ef4444;
      font-size: 14px;
      font-weight: 700;
      line-height: 1;
    }
    [dir="rtl"] .efb-sc-badge-required { margin-left: 0; margin-right: auto; }

    .efb-sc-section-header {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 8px 4px 4px;
      margin-top: 6px;
      font-size: 10px;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-top: 1px dashed #cbd5e1;
    }
    .efb-sc-section-header i { font-size: 12px; color: #94a3b8; }

    .efb-sc-item {
      position: relative;
      margin-bottom: 4px;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      background: #fcfcfd;
      transition: all .2s ease;
      overflow: visible;
    }
    .efb-sc-item:hover {
      border-color: #93c5fd;
      background: #f0f7ff;
      box-shadow: 0 2px 8px rgba(59, 130, 246, 0.08);
    }
    .efb-sc-item.efb-sc-item-required {
      border-left: 3px solid #667eea;
    }
    [dir="rtl"] .efb-sc-item.efb-sc-item-required {
      border-left: 1px solid #e2e8f0;
      border-right: 3px solid #667eea;
    }
    .efb-sc-item-top {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 7px 8px;
    }
    .efb-sc-item-info {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      gap: 2px;
      cursor: help;
    }
    .efb-sc-item-label {
      font-size: 11px;
      font-weight: 600;
      color: #334155;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .efb-sc-req-dot {
      color: #667eea;
      font-size: 8px;
      line-height: 1;
    }
    .efb-sc-item-code {
      font-size: 9.5px;
      font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
      color: #6b7280;
      background: rgba(102, 126, 234, 0.06);
      padding: 2px 5px;
      border-radius: 3px;
      word-break: break-all;
      display: inline-block;
      max-width: 100%;
      border: 1px solid rgba(102, 126, 234, 0.1);
    }
    .efb-sc-item-actions {
      display: flex;
      gap: 3px;
      flex-shrink: 0;
    }
    .efb-sc-action-btn {
      width: 26px;
      height: 26px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #e2e8f0;
      border-radius: 5px;
      background: #ffffff;
      color: #94a3b8;
      cursor: pointer;
      font-size: 12px;
      transition: all .15s;
    }
    .efb-sc-action-btn:hover {
      color: #475569;
      border-color: #94a3b8;
    }
    .efb-sc-insert-btn:hover {
      background: #eef2ff;
      border-color: #a5b4fc;
      color: #667eea;
    }
    .efb-sc-copy-btn:hover {
      background: #f0fdf4;
      border-color: #86efac;
      color: #16a34a;
    }
    .efb-sc-copy-btn.efb-sc-copied,
    .efb-sc-action-btn.efb-sc-copied {
      background: #f0fdf4;
      border-color: #86efac;
      color: #16a34a;
    }

    /* ── Hover Tooltip (data-efb-tooltip) ── */
    [data-efb-tooltip] {
      position: relative;
    }
    [data-efb-tooltip]::after {
      content: attr(data-efb-tooltip);
      position: absolute;
      left: 105%;
      top: 50%;
      transform: translateY(-50%);
      z-index: 999;
      background: #1e293b;
      color: #f1f5f9;
      font-size: 11px;
      font-weight: 400;
      line-height: 1.5;
      padding: 8px 12px;
      border-radius: 6px;
      white-space: normal;
      width: 220px;
      max-width: 260px;
      pointer-events: none;
      opacity: 0;
      visibility: hidden;
      transition: opacity .2s ease, visibility .2s ease;
      box-shadow: 0 4px 16px rgba(0,0,0,0.18);
      text-align: start;
    }
    [data-efb-tooltip]::before {
      content: '';
      position: absolute;
      left: 100%;
      top: 50%;
      transform: translateY(-50%);
      z-index: 999;
      border: 6px solid transparent;
      border-right-color: #1e293b;
      pointer-events: none;
      opacity: 0;
      visibility: hidden;
      transition: opacity .2s ease, visibility .2s ease;
    }
    [data-efb-tooltip]:hover::after,
    [data-efb-tooltip]:hover::before {
      opacity: 1;
      visibility: visible;
    }

    /* ── Tooltip RTL Support ── */
    [dir="rtl"] [data-efb-tooltip]::after,
    .rtl [data-efb-tooltip]::after {
      left: auto;
      right: 105%;
      text-align: right;
    }
    [dir="rtl"] [data-efb-tooltip]::before,
    .rtl [data-efb-tooltip]::before {
      left: auto;
      right: 100%;
      border-right-color: transparent;
      border-left-color: #1e293b;
    }

    /* ── Tooltip fallback for narrow left panel: show below ── */
    @media (max-width: 900px) {
      [data-efb-tooltip]::after {
        left: 50%;
        right: auto;
        top: auto;
        bottom: calc(100% + 8px);
        transform: translateX(-50%);
      }
      [data-efb-tooltip]::before {
        left: 50%;
        right: auto;
        top: auto;
        bottom: 100%;
        transform: translateX(-50%);
        border-right-color: transparent;
        border-top-color: #1e293b;
        border-left-color: transparent;
      }
      [dir="rtl"] [data-efb-tooltip]::after,
      .rtl [data-efb-tooltip]::after {
        left: 50%;
        right: auto;
        transform: translateX(-50%);
      }
      [dir="rtl"] [data-efb-tooltip]::before,
      .rtl [data-efb-tooltip]::before {
        left: 50%;
        right: auto;
        border-left-color: transparent;
      }
    }
    `;
    document.head.appendChild(style);
  }

  /* ──────────── PUBLIC API ──────────────────────────────────── */

  window.efbEmailBuilder = {
    init: function () {
      injectStyles_efb();
      initBuilder_efb();
    },
    addBlock_efb,
    removeBlock_efb,
    duplicateBlock_efb,
    moveBlock_efb,
    updateBlockData_efb,
    insertShortcode_efb,
    updateSocialLink_efb,
    addSocialLink_efb,
    removeSocialLink_efb,
    loadTemplate_efb,
    showPreview_efb,
    exportHTML_efb,
    showCodeEditor_efb,
    applyCodeEditor_efb,
    resetBuilder_efb,
    switchSidebarTab_efb,
    copyShortcode_efb,
    insertShortcodeFromPanel_efb,
    undo_efb,
    redo_efb,
    getState: () => builderState_efb,
    generateHTML: generateFullHTML_efb,
    syncToTextarea_efb
  };

})();
