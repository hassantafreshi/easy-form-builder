
(function () {
  'use strict';

  const BUILDER_ID_efb = 'efb-email-builder';
  const CANVAS_ID_efb  = 'efb-email-canvas';
  const TEXTAREA_ID_efb = 'emailTemp_emsFirmBuilder';

  const isRtl_efb = () => typeof efb_var !== 'undefined' && Number(efb_var.rtl) === 1;

  const t_efb = (key, fallback) => {
    if (typeof efb_var !== 'undefined' && efb_var.text && efb_var.text[key]) {
      return efb_var.text[key];
    }
    return fallback || key;
  };

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
  ];

  const DEFAULT_FONT_efb = "'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif";

  function _gFont_efb() {
    return builderState_efb?.globalSettings?.fontFamily || DEFAULT_FONT_efb;
  }

  const COLOR_PRESETS_efb = [
    '#202a8d', '#667eea', '#0ea5e9', '#10b981', '#f59e0b',
    '#ef4444', '#8b5cf6', '#ec4899', '#2D3445', '#1e3a8a',
    '#333333', '#666666', '#999999', '#ffffff', '#f8f9fa',
    '#f0f9ff', '#fefce8', '#fef2f2', '#f5f3ff', '#000000'
  ];

  const SOCIAL_PRESETS_efb = {
    facebook:    { label: 'Facebook',    color: '#1877F2', svg: '<path d="M24 12.073c0-6.627-5.373-12-12-12S0 5.446 0 12.073c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953h-1.513c-1.491 0-1.956.925-1.956 1.875v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>' },
    x:           { label: 'X',           color: '#000000', svg: '<path d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932zM17.61 20.644h2.039L6.486 3.24H4.298z"/>' },
    instagram:   { label: 'Instagram',   color: '#E4405F', svg: '<path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405a1.441 1.441 0 11-2.882 0 1.441 1.441 0 012.882 0z"/>' },
    linkedin:    { label: 'LinkedIn',    color: '#0A66C2', svg: '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>' },
    youtube:     { label: 'YouTube',     color: '#FF0000', svg: '<path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>' },
    tiktok:      { label: 'TikTok',      color: '#000000', svg: '<path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>' },
    whatsapp:    { label: 'WhatsApp',    color: '#25D366', svg: '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>' },
    telegram:    { label: 'Telegram',    color: '#26A5E4', svg: '<path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>' },
    pinterest:   { label: 'Pinterest',   color: '#BD081C', svg: '<path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12.017 24c6.624 0 11.99-5.367 11.99-11.988C24.007 5.367 18.641 0 12.017 0z"/>' },
    snapchat:    { label: 'Snapchat',    color: '#FFFC00', svg: '<path d="M12.206.793c.99 0 4.347.276 5.93 3.821.529 1.193.403 3.219.299 4.847l-.003.06c-.012.18-.022.345-.03.51.075.045.203.09.401.09.3-.016.659-.12 1.033-.301a.32.32 0 01.139-.029c.108 0 .234.029.365.104.21.12.3.27.3.42v.012c-.06.45-.539.63-.959.719-.03.011-.06.016-.09.026-.21.059-.39.105-.45.359l-.009.031c-.12.48.12.9.33 1.32.36.72.87 1.38 1.47 1.89.33.27.6.51.96.63.12.06.27.12.27.36-.06.27-.33.42-.56.481-.27.075-.56.12-.84.18-.27.045-.53.089-.78.149a.37.37 0 00-.27.27c-.03.105 0 .225.06.36.12.21.18.45.21.66.032.24-.068.48-.208.62-.18.18-.42.24-.66.24-.27.014-.54-.06-.81-.18-.27-.12-.51-.18-.78-.24-.15-.03-.3-.049-.45-.049-.54 0-.96.33-1.29.57-.66.45-1.17.81-2.16.87a4.98 4.98 0 01-.36.01c-.21 0-.51-.03-.78-.06-1.2-.15-2.01-.57-2.73-1.07-.45-.3-.87-.51-1.35-.51-.15 0-.3.015-.45.045-.27.06-.51.12-.78.24-.27.12-.54.196-.81.18a.982.982 0 01-.66-.24c-.14-.14-.24-.38-.21-.62.03-.21.09-.45.21-.66.06-.135.09-.255.06-.36a.37.37 0 00-.27-.27c-.24-.06-.51-.105-.78-.15-.3-.06-.57-.104-.84-.18-.24-.06-.51-.21-.57-.48l.003-.06c.03-.18.06-.33.27-.345.36-.12.63-.36.96-.63.6-.51 1.11-1.17 1.47-1.89.21-.42.45-.84.33-1.32l-.009-.03c-.06-.255-.24-.3-.45-.36-.03-.009-.06-.015-.09-.024-.42-.09-.9-.27-.96-.72v-.015c0-.15.09-.3.3-.42.12-.075.255-.105.365-.105a.35.35 0 01.135.03c.375.18.735.285 1.035.3.3 0 .435-.074.465-.09l-.003-.06a30.9 30.9 0 00-.033-.51c-.104-1.628-.23-3.654.3-4.847C7.86 1.07 11.216.793 12.206.793z"/>' },
    github:      { label: 'GitHub',      color: '#181717', svg: '<path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>' },
    dribbble:    { label: 'Dribbble',    color: '#EA4C89', svg: '<path d="M12 24C5.385 24 0 18.615 0 12S5.385 0 12 0s12 5.385 12 12-5.385 12-12 12zm10.12-10.358c-.35-.11-3.17-.953-6.384-.438 1.34 3.684 1.887 6.684 1.992 7.308a10.28 10.28 0 004.395-6.87zm-6.115 7.808c-.153-.9-.75-4.032-2.19-7.77l-.066.02c-5.79 2.015-7.86 6.025-8.04 6.4a10.161 10.161 0 006.29 2.166c1.42 0 2.77-.29 4.006-.816zM4.855 18.546c.24-.395 3.004-4.936 8.348-6.613.135-.045.27-.084.405-.12-.26-.585-.54-1.167-.832-1.74C7.17 11.775 1.65 11.7 1.2 11.685v.315c0 2.633.998 5.037 2.655 6.845zm-1.56-8.735c.46.008 5.225.03 10.44-1.415A76.27 76.27 0 0010.2 3.216 10.232 10.232 0 002.295 9.81zm9.56-7.38c.79 1.207 1.558 2.497 2.288 3.855 3.36-1.26 4.785-3.164 4.952-3.394A10.174 10.174 0 0012.856 3.43zm8.478 1.816c-.21.264-1.8 2.293-5.31 3.704.249.515.489 1.035.717 1.56.08.186.16.37.236.555 3.396-.428 6.77.265 7.104.335-.02-2.235-.794-4.29-2.146-5.88z"/>' },
    reddit:      { label: 'Reddit',      color: '#FF4500', svg: '<path d="M12 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 01-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 01.042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 014.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 01.14-.197.35.35 0 01.238-.042l2.906.617a1.214 1.214 0 011.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 00-.231.094.33.33 0 000 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 00.029-.463.33.33 0 00-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 00-.232-.095z"/>' },
    discord:     { label: 'Discord',     color: '#5865F2', svg: '<path d="M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189z"/>' },
    twitch:      { label: 'Twitch',      color: '#9146FF', svg: '<path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714z"/>' },
    medium:      { label: 'Medium',      color: '#000000', svg: '<path d="M13.54 12a6.8 6.8 0 01-6.77 6.82A6.8 6.8 0 010 12a6.8 6.8 0 016.77-6.82A6.8 6.8 0 0113.54 12zM20.96 12c0 3.54-1.51 6.42-3.38 6.42-1.86 0-3.38-2.88-3.38-6.42s1.52-6.42 3.38-6.42 3.38 2.88 3.38 6.42M24 12c0 3.17-.53 5.75-1.19 5.75-.66 0-1.19-2.58-1.19-5.75s.53-5.75 1.19-5.75C23.47 6.25 24 8.83 24 12z"/>' },
    spotify:     { label: 'Spotify',     color: '#1DB954', svg: '<path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>' },
    behance:     { label: 'Behance',     color: '#1769FF', svg: '<path d="M6.938 4.503c.702 0 1.34.06 1.92.188.577.13 1.07.33 1.485.61.41.28.733.65.96 1.12.225.47.34 1.05.34 1.73 0 .74-.17 1.36-.507 1.86-.338.5-.837.9-1.502 1.22.906.26 1.576.72 2.022 1.37.448.66.665 1.45.665 2.36 0 .75-.13 1.39-.41 1.93-.28.55-.67 1-1.16 1.35-.48.348-1.05.6-1.67.767-.63.165-1.27.25-1.95.25H0V4.51h6.938v-.007zM16.94 16.665c.44.428 1.073.643 1.894.643.59 0 1.1-.148 1.53-.447.424-.29.68-.61.78-.94h2.588c-.403 1.28-1.048 2.2-1.9 2.75-.85.56-1.884.83-3.08.83-.837 0-1.584-.13-2.272-.4a4.948 4.948 0 01-1.72-1.14 5.1 5.1 0 01-1.077-1.77c-.253-.69-.373-1.45-.373-2.27 0-.803.135-1.54.403-2.23.27-.7.644-1.28 1.12-1.79.495-.51 1.063-.895 1.736-1.194s1.4-.433 2.22-.433c.91 0 1.69.164 2.38.523.67.34 1.22.82 1.66 1.4.44.586.75 1.26.94 2.02.19.75.25 1.54.21 2.38h-7.69c.055 1.023.47 1.84.91 2.267zM3.577 8.377c0-.41-.086-.74-.258-1.01-.172-.27-.41-.47-.68-.61-.283-.13-.586-.21-.94-.24a8.018 8.018 0 00-1.008-.06H3.59v3.93H.93c-.372 0-.74-.026-1.087-.087-.36-.06-.67-.17-.94-.33a1.697 1.697 0 01-.638-.62c-.16-.27-.24-.62-.24-1.04 0-.06.006-.117.013-.173.007-.057.017-.107.027-.157zM9.58 5.89c0-.316-.06-.585-.17-.82a1.45 1.45 0 00-.46-.57 1.86 1.86 0 00-.69-.33c-.26-.06-.55-.1-.85-.1H3.59v3.58h3.77c.34 0 .65-.03.96-.1s.55-.18.78-.34c.22-.16.393-.37.52-.64.12-.27.18-.6.18-1z"/>' },
    vimeo:       { label: 'Vimeo',       color: '#1AB7EA', svg: '<path d="M23.977 6.416c-.105 2.338-1.739 5.543-4.894 9.609-3.268 4.247-6.026 6.37-8.29 6.37-1.409 0-2.578-1.294-3.553-3.881L5.322 11.4C4.603 8.816 3.834 7.522 3.01 7.522c-.179 0-.806.378-1.881 1.132L0 7.197c1.185-1.044 2.351-2.084 3.501-3.128C5.08 2.701 6.266 1.984 7.055 1.91c1.867-.18 3.016 1.1 3.447 3.838.465 2.953.789 4.789.971 5.507.539 2.45 1.131 3.674 1.776 3.674.502 0 1.256-.796 2.265-2.385 1.004-1.589 1.54-2.797 1.612-3.628.144-1.371-.395-2.061-1.614-2.061-.574 0-1.167.121-1.777.391 1.186-3.868 3.434-5.757 6.762-5.637 2.473.06 3.628 1.664 3.493 4.797l-.013.01z"/>' },
    website:     { label: 'Website',     color: '#4A5568', svg: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>' },
    email:       { label: 'Email',       color: '#EA4335', svg: '<path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 010 19.366V5.457c0-2.023 2.309-3.178 3.927-1.964L5.455 4.64 12 9.548l6.545-4.91 1.528-1.145C21.69 2.28 24 3.434 24 5.457z"/>' }
  };

  function _socialSvg_efb(key, size, color) {
    const preset = SOCIAL_PRESETS_efb[key];
    if (!preset) return '';
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="${size}" height="${size}" fill="${color || preset.color}">${preset.svg}</svg>`;
  }

  function _socialSvgImg_efb(key, size, color) {
    const preset = SOCIAL_PRESETS_efb[key];
    if (!preset) return '';
    const svgStr = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="${size}" height="${size}" fill="${color || preset.color}">${preset.svg}</svg>`;
    try {
      const b64 = btoa(unescape(encodeURIComponent(svgStr)));
      return `<img src="data:image/svg+xml;base64,${b64}" width="${size}" height="${size}" alt="${preset.label}" style="display:inline-block;vertical-align:middle;border:none;" />`;
    } catch(e) { return ''; }
  }

  function _svgToImg_efb(svgStr, size, altText) {
    try {
      const b64 = btoa(unescape(encodeURIComponent(svgStr)));
      return `<img src="data:image/svg+xml;base64,${b64}" width="${size}" height="${size}" alt="${altText || ''}" style="display:inline-block;vertical-align:middle;border:none;" />`;
    } catch(e) { return ''; }
  }

  const _xssPatterns_efb = [
    /<script[\s>\/]/gi,
    /<\/script>/gi,
    /\bon\w+\s*=/gi,
    /javascript\s*:/gi,
    /vbscript\s*:/gi,
    /data\s*:\s*text\/html/gi,
    /<iframe[\s>\/]/gi,
    /<\/iframe>/gi,
    /<object[\s>\/]/gi,
    /<\/object>/gi,
    /<embed[\s>\/]/gi,
    /<\/embed>/gi,
    /<form[\s>\/]/gi,
    /<\/form>/gi,
    /<input[\s>\/]/gi,
    /<textarea[\s>\/]/gi,
    /<\/textarea>/gi,
    /<button[\s>\/]/gi,
    /<\/button>/gi,
    /<select[\s>\/]/gi,
    /<\/select>/gi,
    /<meta[\s>\/]/gi,
    /<link[\s>\/]/gi,
    /<base[\s>\/]/gi,
    /<svg[\s>\/]/gi,
    /<\/svg>/gi,
    /<math[\s>\/]/gi,
    /<\/math>/gi,
    /expression\s*\(/gi,
    /-moz-binding\s*:/gi,
    /behavior\s*:/gi,
    /url\s*\(\s*['"]*\s*javascript/gi, // url(javascript:)
  ];

  function sanitizeAttr_efb(str) {
    if (!str && str !== 0) return '';
    let s = String(str);
    for (const rx of _xssPatterns_efb) {
      rx.lastIndex = 0;
      s = s.replace(rx, '');
    }
    s = s.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
    return s;
  }

  function sanitizeText_efb(str) {
    if (!str && str !== 0) return '';
    let s = sanitizeAttr_efb(str);
    s = s.replace(/&/g, '&amp;')
         .replace(/</g, '&lt;')
         .replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;');
    return s;
  }

  function sanitizeUrl_efb(url) {
    if (!url) return '';
    let s = String(url).trim();
    s = s.replace(/\x00/g, '');
    const tmp = s.replace(/&#(\d+);?/g, (_, n) => String.fromCharCode(n))
                 .replace(/&#x([0-9a-f]+);?/gi, (_, h) => String.fromCharCode(parseInt(h, 16)));
    const lower = tmp.replace(/\s+/g, '').toLowerCase();
    if (lower.startsWith('javascript:') || lower.startsWith('vbscript:') ||
        lower.startsWith('data:text/html') || lower.startsWith('data:application')) {
      return '';
    }
    if (s.startsWith('shortcode_')) return s;
    return s;
  }

  function sanitizeCss_efb(css) {
    if (!css) return '';
    let s = String(css);
    s = s.replace(/expression\s*\(/gi, '')
         .replace(/-moz-binding\s*:/gi, '')
         .replace(/behavior\s*:/gi, '')
         .replace(/url\s*\(\s*['"]?\s*javascript/gi, 'url(blocked')
         .replace(/url\s*\(\s*['"]?\s*vbscript/gi, 'url(blocked')
         .replace(/url\s*\(\s*['"]?\s*data\s*:\s*text\/html/gi, 'url(blocked');
    s = s.replace(/[\x00]/g, '');
    return s;
  }

  function sanitizeHtmlBlock_efb(html) {
    if (!html) return '';
    let s = String(html);
    for (const rx of _xssPatterns_efb) {
      rx.lastIndex = 0;
      s = s.replace(rx, '');
    }
    s = s.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
    return s;
  }

  const BLOCK_TYPES_efb = {

    header: {
      label: t_efb('ebHeader', 'Header'),
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
      label: t_efb('ebLogo', 'Logo'),
      icon: 'bi-image-fill',
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
      label: t_efb('ebTitle', 'Title'),
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
        const ff = sanitizeCss_efb(data.fontFamily || _gFont_efb());
        return `<tr><td align="${sanitizeAttr_efb(data.align)}">
          <h1 style="margin: 0; padding: 0; color: ${sanitizeCss_efb(data.color)}; font-size: ${sanitizeAttr_efb(data.fontSize)}px; font-weight: ${sanitizeAttr_efb(data.fontWeight)}; line-height: 1.3; text-align: ${sanitizeAttr_efb(data.align)}; font-family: ${ff};">${sanitizeText_efb(data.text)}</h1>
        </td></tr>`;
      }
    },

    text: {
      label: t_efb('ebTextBlock', 'Text Block'),
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
        const ff = sanitizeCss_efb(data.fontFamily || _gFont_efb());
        return `<tr><td style="padding: ${sanitizeCss_efb(data.padding)};">
          <p style="margin: 0; color: ${sanitizeCss_efb(data.color)}; font-size: ${sanitizeAttr_efb(data.fontSize)}px; line-height: ${sanitizeAttr_efb(data.lineHeight)}; text-align: ${sanitizeAttr_efb(data.align)}; font-family: ${ff};">${sanitizeText_efb(data.text)}</p>
        </td></tr>`;
      }
    },

    message: {
      label: t_efb('ebMessageContent', 'Message Content') + ' *',
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
        const ff = sanitizeCss_efb(data.fontFamily || _gFont_efb());
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
      label: t_efb('ebButton', 'Button'),
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
        const ff = sanitizeCss_efb(data.fontFamily || _gFont_efb());
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
      label: t_efb('ebDivider', 'Divider'),
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
      label: t_efb('ebSpacer', 'Spacer'),
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
      label: t_efb('ebImage', 'Image'),
      icon: 'bi-image',
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
      label: t_efb('ebTwoColumns', 'Two Columns'),
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
        const ff = sanitizeCss_efb(data.fontFamily || _gFont_efb());
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
      label: t_efb('ebSocialLinks', 'Social Links'),
      icon: 'bi-share',
      category: 'content',
      defaultData: {
        align: 'center',
        padding: '20px 30px',
        color: '#667eea',
        iconSize: '24',
        links: [
          { icon: 'website', name: 'Website', url: 'shortcode_website_url' }
        ]
      },
      render(data) {
        const iconSz = parseInt(data.iconSize, 10) || 24;
        const linksHtml = data.links.map(l => {
          const preset = SOCIAL_PRESETS_efb[l.icon];
          let iconHtml;
          if (l.icon === 'custom' && l.customSvg) {
            const svgStr = l.customSvg.includes('<svg') ? l.customSvg
              : `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="${iconSz}" height="${iconSz}">${l.customSvg}</svg>`;
            iconHtml = _svgToImg_efb(svgStr, iconSz, sanitizeAttr_efb(l.name)) || sanitizeText_efb(l.name);
          } else if (preset) {
            iconHtml = _socialSvgImg_efb(l.icon, iconSz, sanitizeCss_efb(data.color));
          } else {
            iconHtml = sanitizeText_efb(l.name);
          }
          return `<a href="${sanitizeUrl_efb(l.url)}" target="_blank" style="display: inline-block; margin: 0 6px; text-decoration: none; vertical-align: middle; line-height: 1;">${iconHtml}</a>`;
        }).join('');
        return `<tr><td align="${sanitizeAttr_efb(data.align)}" style="padding: ${sanitizeCss_efb(data.padding)};">
          ${linksHtml}
        </td></tr>`;
      }
    },

    footer: {
      label: t_efb('ebFooter', 'Footer'),
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
        const ff = sanitizeCss_efb(data.fontFamily || _gFont_efb());
        return `<tr><td style="padding: ${sanitizeCss_efb(data.padding)}; background-color: ${sanitizeCss_efb(data.bgColor)}; border-radius: ${sanitizeCss_efb(data.borderRadius)};">
          <p style="margin: 0; color: ${sanitizeCss_efb(data.color)}; font-size: ${sanitizeAttr_efb(data.fontSize)}px; line-height: 1.5; text-align: ${sanitizeAttr_efb(data.align)}; font-family: ${ff};">${sanitizeText_efb(data.text)}</p>
        </td></tr>`;
      }
    },

    htmlBlock: {
      label: t_efb('ebCustomHTML', 'Custom HTML'),
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
        { type: 'button', data: { text: t_efb('ebViewWebsite', 'View Website'), url: 'shortcode_website_url', bgColor: '#8b5cf6', textColor: '#ffffff', borderRadius: '25', padding: '14px 35px', fontSize: '16', align: 'center', containerPadding: '20px 30px' } },
        { type: 'divider', data: { color: '#e9d5ff', thickness: '1', width: '80', padding: '15px 30px' } },
        { type: 'social', data: { color: '#8b5cf6', align: 'center', padding: '10px 30px', iconSize: '24', links: [{icon:'facebook',name:'Facebook',url:'#'},{icon:'x',name:'X',url:'#'},{icon:'instagram',name:'Instagram',url:'#'}] } },
        { type: 'footer', data: { text: 'shortcode_website_name | shortcode_admin_email', color: '#7c3aed', fontSize: '13', align: 'center', bgColor: '#faf5ff', padding: '25px 30px', borderRadius: '0 0 8px 8px' } }
      ],
      globalSettings: { bgColor: '#faf5ff', contentBgColor: '#ffffff', borderRadius: '12' }
    }
  };

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
      direction: isRtl_efb() ? 'rtl' : 'ltr',
      btnBgColor: '#202a8d',
      btnTextColor: '#ffffff'
    },
    undoStack: [],
    redoStack: []
  };

  let _blockIdCounter_efb = 0;
  function genId_efb() { return 'efb-blk-' + (++_blockIdCounter_efb) + '-' + Date.now().toString(36); }

  function saveState_efb() {
    builderState_efb.undoStack.push(JSON.stringify({
      blocks: builderState_efb.blocks,
      globalSettings: builderState_efb.globalSettings
    }));
    if (builderState_efb.undoStack.length > 30) builderState_efb.undoStack.shift();
    builderState_efb.redoStack = [];
  }

  function undo_efb() {
    if (builderState_efb.undoStack.length === 0) return;
    builderState_efb.redoStack.push(JSON.stringify({
      blocks: builderState_efb.blocks,
      globalSettings: builderState_efb.globalSettings
    }));
    const restored = JSON.parse(builderState_efb.undoStack.pop());

    if (Array.isArray(restored)) {
      builderState_efb.blocks = restored;
    } else {
      builderState_efb.blocks = restored.blocks;
      if (restored.globalSettings) {
        builderState_efb.globalSettings = Object.assign(builderState_efb.globalSettings, restored.globalSettings);
      }
    }
    builderState_efb.selectedBlock = null;
    renderCanvas_efb();
    renderGlobalSettings_efb();
    syncToTextarea_efb();
  }

  function redo_efb() {
    if (builderState_efb.redoStack.length === 0) return;
    builderState_efb.undoStack.push(JSON.stringify({
      blocks: builderState_efb.blocks,
      globalSettings: builderState_efb.globalSettings
    }));
    const restored = JSON.parse(builderState_efb.redoStack.pop());
    if (Array.isArray(restored)) {
      builderState_efb.blocks = restored;
    } else {
      builderState_efb.blocks = restored.blocks;
      if (restored.globalSettings) {
        builderState_efb.globalSettings = Object.assign(builderState_efb.globalSettings, restored.globalSettings);
      }
    }
    builderState_efb.selectedBlock = null;
    renderCanvas_efb();
    renderGlobalSettings_efb();
    syncToTextarea_efb();
  }

  function renderBlock_efb(block) {
    const def = BLOCK_TYPES_efb[block.type];
    if (!def) return '';
    const data = Object.assign({}, def.defaultData, block.data || {});
    if (block.children && block.children.length) {
      data.children = block.children;
    }
    let html = def.render(data);
    const cbg = sanitizeCss_efb(builderState_efb.globalSettings.contentBgColor || '#ffffff');
    const firstTd = html.match(/<td([^>]*)style="([^"]*)"/i);
    if (firstTd) {
      if (!/background/i.test(firstTd[2])) {
        html = html.replace(
          /(<td[^>]*style=")/i,
          `$1background-color: ${cbg}; `
        );
      }
    } else {
      html = html.replace(
        /<td(?=[ >])/i,
        `<td style="background-color: ${cbg};"`
      );
    }
    return html;
  }

  function generateFullHTML_efb() {
    const gs = builderState_efb.globalSettings;
    const br = parseInt(gs.borderRadius) || 0;
    const blocksHtml = builderState_efb.blocks.map((b, i, arr) => {
      let html = renderBlock_efb(b);
      if (br > 0 && (i === 0 || i === arr.length - 1)) {
        const topR = i === 0 ? br + 'px' : '0';
        const botR = i === arr.length - 1 ? br + 'px' : '0';
        const radiusCss = `border-radius: ${topR} ${topR} ${botR} ${botR}`;
        const tdStyle = html.match(/<td[^>]*style="([^"]*)"/i);
        if (tdStyle && /border-radius/i.test(tdStyle[1])) {
          html = html.replace(
            /(<td[^>]*style="[^"]*?)border-radius:\s*[^;"]+;?/i,
            `$1${radiusCss};`
          );
        } else if (tdStyle) {
          html = html.replace(/(<td[^>]*style=")/i, `$1${radiusCss}; `);
        }
      }
      return html;
    }).join('\n');

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
  .efb-email-wrapper { max-width: 100% !important; width: 100% !important; }
  .efb-email-container { width: 100% !important; }
  .efb-email-container td { padding-left: 15px !important; padding-right: 15px !important; }
  img { max-width: 100% !important; height: auto !important; }
}
</style>
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: ${sanitizeCss_efb(gs.bgColor)}; direction: ${sanitizeAttr_efb(gs.direction)}; font-family: ${sanitizeCss_efb(gs.fontFamily)};">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: ${sanitizeCss_efb(gs.bgColor)};">
<tr><td align="center" style="padding: 20px 0;">
<!--[if mso]><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="${sanitizeAttr_efb(gs.contentWidth)}" align="center"><tr><td><![endif]-->
<div class="efb-email-wrapper" style="max-width: ${sanitizeAttr_efb(gs.contentWidth)}px; margin: 0 auto; border-radius: ${sanitizeAttr_efb(gs.borderRadius)}px; overflow: hidden; background-color: ${sanitizeCss_efb(gs.contentBgColor)};">
<table class="efb-email-container" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: ${sanitizeCss_efb(gs.contentBgColor)};">
${blocksHtml}
</table>
</div>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>`;
  }

  function syncToTextarea_efb() {
    const textarea = document.getElementById(TEXTAREA_ID_efb);
    if (!textarea) return;
    const html = generateFullHTML_efb();
    try {
      const builderData = { blocks: builderState_efb.blocks, globalSettings: builderState_efb.globalSettings };
      const encoded = encodeURIComponent(JSON.stringify(builderData));
      textarea.value = html + '\n<!-- EFBDATA:' + encoded + ' -->';
      const jsonStore = document.getElementById('efb-builder-json');
      if (jsonStore) {
        jsonStore.value = JSON.stringify(builderData);
      }
      const gs = builderState_efb.globalSettings;
      const btnBgEl = document.getElementById('emailBtnBgColor_emsFormBuilder');
      const btnTxtEl = document.getElementById('emailBtnTextColor_emsFormBuilder');
      if (btnBgEl) btnBgEl.value = gs.btnBgColor || '#202a8d';
      if (btnTxtEl) btnTxtEl.value = gs.btnTextColor || '#ffffff';
    } catch(e) {
      textarea.value = html;
    }
  }

  function tryParseExistingTemplate_efb(html) {
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
        }
      }
    }

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

    if (!html || html.trim().length < 10) {
      loadTemplate_efb('professional');
      return true;
    }

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

    if (tpl.globalSettings) {
      builderState_efb.globalSettings = Object.assign({}, builderState_efb.globalSettings, tpl.globalSettings);
    } else {
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
    renderGlobalSettings_efb();
    syncToTextarea_efb();
  }

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
        data-block-id="${block.id}" data-index="${index}" draggable="true"
        role="listitem" tabindex="0"
        aria-label="${escHtml_efb(label)} - ${t_efb('ebBlock', 'Block')} ${index + 1}" aria-selected="${isSelected}">
        <div class="efb-block-label">
          <span><i class="efb ${def?.icon || 'bi-square'}"></i> ${label}</span>
          <div class="efb-block-actions" role="toolbar" aria-label="${t_efb('ebBlockActions', 'Block actions')}">
            <button class="efb-blk-btn" onclick="efbEmailBuilder.moveBlock_efb('${block.id}',-1)" title="${t_efb('ebMoveUp', 'Move Up')}" aria-label="${t_efb('ebMoveUp', 'Move Up')}"><i class="efb bi-arrow-up"></i></button>
            <button class="efb-blk-btn" onclick="efbEmailBuilder.moveBlock_efb('${block.id}',1)" title="${t_efb('ebMoveDown', 'Move Down')}" aria-label="${t_efb('ebMoveDown', 'Move Down')}"><i class="efb bi-arrow-down"></i></button>
            <button class="efb-blk-btn" onclick="efbEmailBuilder.duplicateBlock_efb('${block.id}')" title="${t_efb('duplicate', 'Duplicate')}" aria-label="${t_efb('duplicate', 'Duplicate')}"><i class="efb bi-copy"></i></button>
            <button class="efb-blk-btn efb-blk-btn-danger" onclick="efbEmailBuilder.removeBlock_efb('${block.id}')" title="${t_efb('delete', 'Delete')}" aria-label="${t_efb('delete', 'Delete')}"><i class="efb bi-trash"></i></button>
          </div>
        </div>
        <div class="efb-block-preview">${renderBlockPreview_efb(block)}</div>
      </div>`;
    });

    canvas.innerHTML = html;

    canvas.querySelectorAll('.efb-canvas-block').forEach(el => {
      el.addEventListener('click', (e) => {
        if (e.target.closest('.efb-blk-btn')) return;
        builderState_efb.selectedBlock = el.dataset.blockId;
        renderCanvas_efb();
        renderPropertiesPanel_efb();
      });
    });

    initCanvasDragDrop_efb();

    updateCanvasGlobalStyles_efb();
  }

  function renderBlockPreview_efb(block) {
    const data = Object.assign({}, BLOCK_TYPES_efb[block.type]?.defaultData || {}, block.data || {});
    const pfs = (sz) => Math.max(10, Math.min(Math.round(Number(sz) * 0.6), 28));
    const spad = (p) => {
      if (!p) return '5px';
      return String(p).replace(/(\d+)/g, (m, n) => Math.max(2, Math.round(Number(n) * 0.5)));
    };
    switch (block.type) {
      case 'header':
        const ha = data.align || 'center';
        const childrenHtml = (block.children || []).map(c => {
          const cd = Object.assign({}, BLOCK_TYPES_efb[c.type]?.defaultData || {}, c.data || {});
          if (c.type === 'logo') { const lm = ha === 'left' ? '0 auto 10px 0' : ha === 'right' ? '0 0 10px auto' : '0 auto 10px'; return `<div style="text-align:${ha};"><img src="${cd.src}" style="width:${Math.min(cd.width,80)}px;height:auto;display:block;margin:${lm};" /></div>`; }
          if (c.type === 'title') return `<div style="text-align:${ha};color:${cd.color};font-size:${pfs(cd.fontSize)}px;font-weight:${cd.fontWeight};font-family:${cd.fontFamily || _gFont_efb()};">${highlightShortcodes_efb(cd.text)}</div>`;
          return '';
        }).join('');
        return `<div style="background:${data.bgGradient || data.bgColor};padding:${spad(data.padding)};border-radius:4px;text-align:${ha};">${childrenHtml}</div>`;
      case 'logo':
        const logoM = data.align === 'left' ? '0 auto 0 0' : data.align === 'right' ? '0 0 0 auto' : '0 auto';
        return `<div style="text-align:${data.align};padding:8px;"><img src="${data.src}" style="width:${Math.min(data.width,60)}px;height:auto;display:block;margin:${logoM};" onerror="this.style.display='none'" /></div>`;
      case 'title':
        return `<div style="text-align:${data.align};color:${data.color};font-size:${pfs(data.fontSize)}px;font-weight:${data.fontWeight};font-family:${data.fontFamily || _gFont_efb()};padding:5px;">${highlightShortcodes_efb(data.text)}</div>`;
      case 'text':
        return `<div style="text-align:${data.align};color:${data.color};font-size:${pfs(data.fontSize)}px;font-family:${data.fontFamily || _gFont_efb()};padding:${spad(data.padding)};line-height:1.4;">${highlightShortcodes_efb(data.text)}</div>`;
      case 'message':
        return `<div style="text-align:${data.align};background:${data.bgColor};padding:${spad(data.padding)};border:2px dashed #667eea;border-radius:4px;">
          <i class="efb bi-chat-square-text" style="font-size:20px;color:#667eea;"></i>
          <div style="color:#667eea;font-size:12px;margin-top:4px;font-weight:600;">shortcode_message</div>
          <div style="color:#94a3b8;font-size:10px;">${t_efb('ebFormContentHere', 'Form content appears here')}</div>
        </div>`;
      case 'button':
        return `<div style="text-align:${data.align};padding:${spad(data.containerPadding || '8px')};">
          <span style="display:inline-block;background:${data.bgColor};color:${data.textColor};padding:${spad(data.padding)};border-radius:${data.borderRadius}px;font-size:${pfs(data.fontSize)}px;font-weight:600;font-family:${data.fontFamily || _gFont_efb()};">${highlightShortcodes_efb(data.text)}</span>
        </div>`;
      case 'divider':
        return `<div style="padding:${spad(data.padding)};"><hr style="border:none;border-top:${data.thickness}px solid ${data.color};width:${data.width}%;margin:0 auto;" /></div>`;
      case 'spacer':
        return `<div style="height:${Math.min(data.height,30)}px;background:${data.bgColor};border:1px dashed #e5e7eb;text-align:center;line-height:${Math.min(data.height,30)}px;color:#cbd5e1;font-size:10px;">${data.height}px</div>`;
      case 'image':
        if (!data.src) return `<div style="text-align:center;padding:15px;border:2px dashed #e5e7eb;border-radius:4px;color:#94a3b8;"><i class="efb bi-card-image" style="font-size:24px;"></i><div style="font-size:11px;margin-top:4px;">${t_efb('ebAddImageURL', 'Add image URL')}</div></div>`;
        return `<div style="text-align:${data.align};padding:${spad(data.padding)};"><img src="${data.src}" style="max-width:100%;max-height:80px;height:auto;" onerror="this.style.display='none'" /></div>`;
      case 'columns':
        return `<div style="display:flex;gap:8px;padding:${spad(data.padding)};">
          <div style="flex:1;background:#f8fafc;padding:8px;border-radius:4px;font-size:${pfs(data.fontSize)}px;color:${data.leftColor};font-family:${data.fontFamily || _gFont_efb()};">${highlightShortcodes_efb(data.leftContent)}</div>
          <div style="flex:1;background:#f8fafc;padding:8px;border-radius:4px;font-size:${pfs(data.fontSize)}px;color:${data.rightColor};font-family:${data.fontFamily || _gFont_efb()};">${highlightShortcodes_efb(data.rightContent)}</div>
        </div>`;
      case 'social':
        return `<div style="text-align:${data.align};padding:${spad(data.padding)};display:flex;gap:6px;justify-content:${data.align === 'center' ? 'center' : data.align === 'right' ? 'flex-end' : 'flex-start'};flex-wrap:wrap;">
          ${(data.links || []).map(l => {
            const sz = Math.max(12, Math.round((parseInt(data.iconSize) || 24) * 0.55));
            if (l.icon === 'custom' && l.customSvg) return `<span style="display:inline-block;width:${sz}px;height:${sz}px;">${l.customSvg}</span>`;
            const pr = SOCIAL_PRESETS_efb[l.icon || 'website'];
            return pr ? `<svg viewBox="0 0 24 24" width="${sz}" height="${sz}" fill="${data.color || pr.color}">${pr.svg}</svg>` : '';
          }).join('')}
        </div>`;
      case 'footer':
        return `<div style="text-align:${data.align};background:${data.bgColor};padding:${spad(data.padding)};border-radius:4px;color:${data.color};font-size:${pfs(data.fontSize)}px;font-family:${data.fontFamily || _gFont_efb()};">${highlightShortcodes_efb(data.text)}</div>`;
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

  let _canvasDropBound_efb = false;
  let _dragThrottleTimer_efb = null;

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
        if (_dragThrottleTimer_efb) return;
        _dragThrottleTimer_efb = requestAnimationFrame(() => {
          _dragThrottleTimer_efb = null;
          const isPanel = e.dataTransfer.types.includes('efb-new-block');
          e.dataTransfer.dropEffect = isPanel ? 'copy' : 'move';
          const rect = el.getBoundingClientRect();
          const mid = rect.top + rect.height / 2;
          el.classList.toggle('efb-drop-above', e.clientY < mid);
          el.classList.toggle('efb-drop-below', e.clientY >= mid);
        });
      });

      el.addEventListener('dragleave', () => {
        el.classList.remove('efb-drop-above', 'efb-drop-below');
      });

      el.addEventListener('drop', (e) => {
        e.preventDefault();
        el.classList.remove('efb-drop-above', 'efb-drop-below');
        const targetIndex = parseInt(el.dataset.index);

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
    const block = builderState_efb.blocks.find(b => b.id === id);
    if (block && block.type === 'message') {
      const msgCount = builderState_efb.blocks.filter(b => b.type === 'message').length;
      if (msgCount <= 1) {
        showNotification_efb('<i class="efb bi-exclamation-triangle-fill me-1"></i>' + t_efb('ebSCRequired', 'shortcode_message is required!'), 'warning');
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
    saveState_efb();
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

    switch (block.type) {
      case 'header':
        html += propColor_efb('bgColor', t_efb('ebBgColor', 'Background Color'), data.bgColor);
        html += propInput_efb('bgGradient', t_efb('ebBgCSS', 'Background (CSS)'), data.bgGradient || data.bgColor);
        html += propPadding_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
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
              html += propChipInput_efb('text', t_efb('text', 'Text'), cd.text, child.id);
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
        html += propChipInput_efb('text', t_efb('ebTitleText', 'Title Text'), data.text);
        html += propShortcodeButtons_efb(block.id, 'text');
        html += propColor_efb('color', t_efb('clr', 'Color'), data.color);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propSelect_efb('fontWeight', t_efb('ebWeight', 'Weight'), data.fontWeight, ['300','400','500','600','700','800']);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        break;

      case 'text':
        html += propChipTextarea_efb('text', t_efb('content', 'Content'), data.text);
        html += propShortcodeButtons_efb(block.id, 'text');
        html += propColor_efb('color', t_efb('clr', 'Color'), data.color);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propRange_efb('lineHeight', t_efb('ebLineHeight', 'Line Height'), data.lineHeight, 1, 3, null, 0.1);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        html += propPadding_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
        break;

      case 'message':
        html += `<div class="efb-props-notice"><i class="efb bi-info-circle"></i> ${t_efb('ebMessageNotice', 'This block outputs <strong>shortcode_message</strong> — the submitted form data.')}</div>`;
        html += propPadding_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
        html += propColor_efb('bgColor', t_efb('ebBackground', 'Background'), data.bgColor);
        html += propColor_efb('color', t_efb('ebTextColor', 'Text Color'), data.color);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        break;

      case 'button':
        html += propChipInput_efb('text', t_efb('ebButtonText', 'Button Text'), data.text);
        html += propShortcodeButtons_efb(block.id, 'text');
        html += propChipInput_efb('url', t_efb('ebLinkURL', 'Link URL'), data.url);
        html += propShortcodeButtons_efb(block.id, 'url');
        html += propColor_efb('bgColor', t_efb('ebBackground', 'Background'), data.bgColor);
        html += propColor_efb('textColor', t_efb('ebTextColor', 'Text Color'), data.textColor);
        html += propRange_efb('borderRadius', t_efb('ebBorderRadius', 'Border Radius (px)'), data.borderRadius, 0, 50);
        html += propPadding_efb('padding', t_efb('ebInnerPadding', 'Inner Padding'), data.padding);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        html += propPadding_efb('containerPadding', t_efb('ebOuterPadding', 'Outer Padding'), data.containerPadding);
        break;

      case 'divider':
        html += propColor_efb('color', t_efb('clr', 'Color'), data.color);
        html += propRange_efb('thickness', t_efb('ebThickness', 'Thickness (px)'), data.thickness, 1, 10);
        html += propRange_efb('width', t_efb('ebWidthPercent', 'Width (%)'), data.width, 10, 100);
        html += propPadding_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
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
        html += propPadding_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
        html += propInput_efb('link', t_efb('ebLinkURL', 'Link URL'), data.link);
        break;

      case 'columns':
        html += propChipTextarea_efb('leftContent', t_efb('ebLeftColumn', 'Left Column'), data.leftContent);
        html += propShortcodeButtons_efb(block.id, 'leftContent');
        html += propChipTextarea_efb('rightContent', t_efb('ebRightColumn', 'Right Column'), data.rightContent);
        html += propShortcodeButtons_efb(block.id, 'rightContent');
        html += propColor_efb('leftColor', t_efb('ebLeftTextColor', 'Left Text Color'), data.leftColor);
        html += propColor_efb('rightColor', t_efb('ebRightTextColor', 'Right Text Color'), data.rightColor);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propRange_efb('gap', t_efb('ebGap', 'Gap (px)'), data.gap, 0, 60);
        html += propColor_efb('bgColor', t_efb('ebBackground', 'Background'), data.bgColor);
        html += propPadding_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
        break;

      case 'social':
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        html += propColor_efb('color', t_efb('ebIconColor', 'Icon Color'), data.color);
        html += propRange_efb('iconSize', t_efb('ebIconSize', 'Icon Size (px)'), data.iconSize || 24, 16, 48);
        html += propPadding_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);

        html += `<div class="efb-props-divider"></div><h6 class="efb-props-subtitle">${t_efb('ebLinks', 'Links')}</h6>`;

        (data.links || []).forEach((link, li) => {
          const curIcon = link.icon || 'website';
          const preset = SOCIAL_PRESETS_efb[curIcon];
          const iconPreview = (curIcon === 'custom' && link.customSvg)
            ? `<span class="efb-sl-icon-preview efb-sl-custom-preview">${link.customSvg}</span>`
            : preset
              ? `<span class="efb-sl-icon-preview"><svg viewBox="0 0 24 24" width="18" height="18" fill="${preset.color}">${preset.svg}</svg></span>`
              : `<span class="efb-sl-icon-preview"><i class="efb bi-globe"></i></span>`;

          html += `<div class="efb-social-link-card" data-link-idx="${li}">
            <div class="efb-sl-header">
              ${iconPreview}
              <span class="efb-sl-name">${escHtml_efb(preset?.label || link.name || 'Custom')}</span>
              <button class="efb-blk-btn efb-blk-btn-danger" onclick="efbEmailBuilder.removeSocialLink_efb('${block.id}',${li})" title="${t_efb('delete', 'Delete')}"><i class="efb bi-x"></i></button>
            </div>
            <div class="efb-sl-body">
              <label class="efb-sl-field-label">${t_efb('ebIcon', 'Icon')}</label>
              <div class="efb-sl-icon-grid" data-block-id="${block.id}" data-link-idx="${li}">`;

          Object.entries(SOCIAL_PRESETS_efb).forEach(([key, p]) => {
            const active = curIcon === key ? ' active' : '';
            html += `<button type="button" class="efb-sl-icon-btn${active}" data-icon="${key}" title="${p.label}">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="${p.color}">${p.svg}</svg>
            </button>`;
          });
          html += `<button type="button" class="efb-sl-icon-btn${curIcon === 'custom' ? ' active' : ''}" data-icon="custom" title="${t_efb('ebCustomSVG', 'Custom SVG')}">
            <i class="efb bi-code-slash" style="font-size:14px;color:#64748b;"></i>
          </button>`;
          html += `</div>`;

          if (curIcon === 'custom') {
            html += `<label class="efb-sl-field-label" style="margin-top:4px;">${t_efb('ebCustomSVGCode', 'Custom SVG Code')}</label>
              <textarea class="efb-prop-textarea efb-sl-custom-svg" rows="3" data-block-id="${block.id}" data-link-idx="${li}"
                placeholder="<svg ...>...</svg>">${escHtml_efb(link.customSvg || '')}</textarea>`;
          }

          html += `<label class="efb-sl-field-label" style="margin-top:4px;">${t_efb('url', 'URL')}</label>
              <input type="text" class="efb-prop-input efb-sl-url" value="${escHtml_efb(link.url)}"
                onchange="efbEmailBuilder.updateSocialLink_efb('${block.id}',${li},'url',this.value)" />
            </div>
          </div>`;
        });

        html += `<button class="efb-btn-sm efb-btn-add" onclick="efbEmailBuilder.showSocialPicker_efb('${block.id}')"><i class="efb bi-plus"></i> ${t_efb('ebAddLink', 'Add Link')}</button>`;
        break;

      case 'footer':
        html += propChipTextarea_efb('text', t_efb('ebFooterText', 'Footer Text'), data.text);
        html += propShortcodeButtons_efb(block.id, 'text');
        html += propColor_efb('color', t_efb('ebTextColor', 'Text Color'), data.color);
        html += propColor_efb('bgColor', t_efb('ebBackground', 'Background'), data.bgColor);
        html += propFontFamily_efb('fontFamily', t_efb('ebFontFamily', 'Font'), data.fontFamily);
        html += propFontSize_efb('fontSize', t_efb('ebFontSize', 'Font Size'), data.fontSize);
        html += propSelect_efb('align', t_efb('align', 'Align'), data.align, ['left','center','right']);
        html += propPadding_efb('padding', t_efb('ebPadding', 'Padding'), data.padding);
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
    const presetSwatches = COLOR_PRESETS_efb.map(c =>
      `<button type="button" class="efb-color-swatch" style="background:${c};" data-color="${c}" data-prop="${key}" data-block="${blockId}" title="${c}" onclick="efbEmailBuilder._applyColorPreset_efb(this)"></button>`
    ).join('');
    return `<div class="efb-prop-row efb-prop-color-row">
      <label class="efb-prop-label">${label}</label>
      <div class="efb-color-picker-wrap">
        <input type="color" class="efb-prop-color" data-prop="${key}" data-block="${blockId}" value="${value && value.startsWith('#') ? value : '#333333'}" />
        <input type="text" class="efb-prop-input efb-prop-color-text" data-prop="${key}" data-block="${blockId}" value="${escHtml_efb(value)}" readonly/>
      </div>
      <div class="efb-color-presets">${presetSwatches}</div>
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

  function propRange_efb(key, label, value, min, max, targetId, step) {
    const blockId = (typeof targetId === 'string' && targetId) ? targetId : builderState_efb.selectedBlock;
    const s = step ? ` step="${step}"` : '';
    return `<div class="efb-prop-row">
      <label class="efb-prop-label">${label}: <span class="efb-range-val">${value}</span></label>
      <input type="range" class="efb-prop-range" data-prop="${key}" data-block="${blockId}" value="${value}" min="${min}" max="${max}"${s} />
    </div>`;
  }

  function _parsePaddingShorthand_efb(val) {
    const parts = String(val || '0').trim().split(/\s+/).map(v => parseInt(v, 10) || 0);
    if (parts.length === 1) return { top: parts[0], right: parts[0], bottom: parts[0], left: parts[0] };
    if (parts.length === 2) return { top: parts[0], right: parts[1], bottom: parts[0], left: parts[1] };
    if (parts.length === 3) return { top: parts[0], right: parts[1], bottom: parts[2], left: parts[1] };
    return { top: parts[0], right: parts[1], bottom: parts[2], left: parts[3] };
  }

  function _paddingSidesToShorthand_efb(t, r, b, l) {
    if (t === r && r === b && b === l) return `${t}px`;
    if (t === b && r === l) return `${t}px ${r}px`;
    if (r === l) return `${t}px ${r}px ${b}px`;
    return `${t}px ${r}px ${b}px ${l}px`;
  }

  function propPadding_efb(key, label, value, targetId) {
    const blockId = targetId || builderState_efb.selectedBlock;
    const p = _parsePaddingShorthand_efb(value);
    const linked = (p.top === p.right && p.right === p.bottom && p.bottom === p.left);
    return `<div class="efb-prop-row">
      <label class="efb-prop-label">${label}</label>
      <div class="efb-padding-editor${linked ? ' efb-pad-linked' : ''}" data-prop="${key}" data-block="${blockId}">
        <div class="efb-pad-sides">
          <div class="efb-pad-side">
            <span class="efb-pad-side-label">T</span>
            <input type="number" class="efb-pad-input" data-side="top" value="${p.top}" min="0" max="200" />
          </div>
          <div class="efb-pad-side">
            <span class="efb-pad-side-label">R</span>
            <input type="number" class="efb-pad-input" data-side="right" value="${p.right}" min="0" max="200" />
          </div>
          <div class="efb-pad-side">
            <span class="efb-pad-side-label">B</span>
            <input type="number" class="efb-pad-input" data-side="bottom" value="${p.bottom}" min="0" max="200" />
          </div>
          <div class="efb-pad-side">
            <span class="efb-pad-side-label">L</span>
            <input type="number" class="efb-pad-input" data-side="left" value="${p.left}" min="0" max="200" />
          </div>
        </div>
        <button type="button" class="efb-pad-link-btn${linked ? ' active' : ''}" title="Link all sides">
          <i class="efb bi-${linked ? 'link-45deg' : 'unlock'}"></i>
        </button>
      </div>
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

  const _SHORTCODE_RE_efb = /(shortcode_message|shortcode_title|shortcode_website_name|shortcode_website_url|shortcode_admin_email)/g;

  const _SHORTCODE_LABELS_efb = {
    'shortcode_message':       () => t_efb('ebSCMessage', 'Message *'),
    'shortcode_title':         () => t_efb('ebSCTitle', 'Title'),
    'shortcode_website_name':  () => t_efb('ebSCSiteName', 'Site Name'),
    'shortcode_website_url':   () => t_efb('ebSCSiteURL', 'Site URL'),
    'shortcode_admin_email':   () => t_efb('ebSCAdminEmail', 'Admin Email')
  };

  function _shortcodeChipHtml_efb(code) {
    const labelFn = _SHORTCODE_LABELS_efb[code];
    const label = labelFn ? labelFn() : code;
    return `<span class="efb-chip" contenteditable="false" data-shortcode="${escHtml_efb(code)}"><i class="efb bi-braces me-1"></i>${escHtml_efb(label)}<button type="button" class="efb-chip-remove" tabindex="-1">&times;</button></span>`;
  }

  function _valueToChipHtml_efb(value) {
    if (!value) return '';
    const parts = value.split(_SHORTCODE_RE_efb);
    return parts.map(part => {
      if (_SHORTCODE_LABELS_efb[part]) return _shortcodeChipHtml_efb(part);
      return escHtml_efb(part);
    }).join('');
  }

  function _chipEditorToValue_efb(editorEl) {
    let value = '';
    const walk = (node) => {
      if (node.nodeType === Node.TEXT_NODE) {
        value += node.textContent;
      } else if (node.nodeType === Node.ELEMENT_NODE) {
        if (node.classList.contains('efb-chip')) {
          value += node.dataset.shortcode || '';
        } else if (node.tagName === 'BR') {
          value += '\n';
        } else {
          if (node.tagName === 'DIV' && value.length > 0 && !value.endsWith('\n')) {
            value += '\n';
          }
          node.childNodes.forEach(walk);
        }
      }
    };
    editorEl.childNodes.forEach(walk);
    return value;
  }

  function propChipInput_efb(key, label, value, targetId) {
    const blockId = targetId || builderState_efb.selectedBlock;
    const chipHtml = _valueToChipHtml_efb(value);
    return `<div class="efb-prop-row">
      <label class="efb-prop-label">${label}</label>
      <div class="efb-chip-editor efb-chip-editor-single" contenteditable="true" data-prop="${key}" data-block="${blockId}" spellcheck="false">${chipHtml}</div>
    </div>`;
  }

  function propChipTextarea_efb(key, label, value, targetId, rows) {
    const blockId = targetId || builderState_efb.selectedBlock;
    const chipHtml = _valueToChipHtml_efb(value);
    const minH = (rows || 3) * 20;
    return `<div class="efb-prop-row">
      <label class="efb-prop-label">${label}</label>
      <div class="efb-chip-editor efb-chip-editor-multi" contenteditable="true" data-prop="${key}" data-block="${blockId}" spellcheck="false" style="min-height:${minH}px;">${chipHtml}</div>
    </div>`;
  }

  let _propDebounce_efb = null;
  let _propStateSaved_efb = false;

  function initPropertyDelegation_efb() {
    const panel = document.getElementById('efb-properties-panel');
    if (!panel) return;

    function applyPropChange(target, immediate) {
      const bid = target.dataset.block;
      const prop = target.dataset.prop;
      if (!bid || !prop) return;

      const b = findBlockById_efb(bid);
      if (!b) {
        return;
      }
      if (!b.data) b.data = {};

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

    panel.addEventListener('input', (e) => {
      const t = e.target;

      if (t.matches('.efb-prop-range')) {
        const val = t.closest('.efb-prop-row')?.querySelector('.efb-range-val');
        if (val) val.textContent = t.value;
        applyPropChange(t, true);
        return;
      }

      if (t.matches('.efb-prop-color')) {
        applyPropChange(t, true);
        const textInput = panel.querySelector(
          `.efb-prop-color-text[data-prop="${t.dataset.prop}"][data-block="${t.dataset.block}"]`
        );
        if (textInput) textInput.value = t.value;
        return;
      }

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

      if (t.matches('.efb-pad-input')) {
        const editor = t.closest('.efb-padding-editor');
        if (!editor) return;
        const isLinked = editor.classList.contains('efb-pad-linked');
        if (isLinked) {
          editor.querySelectorAll('.efb-pad-input').forEach(inp => { inp.value = t.value; });
        }
        const sides = {};
        editor.querySelectorAll('.efb-pad-input').forEach(inp => { sides[inp.dataset.side] = parseInt(inp.value, 10) || 0; });
        const shorthand = _paddingSidesToShorthand_efb(sides.top, sides.right, sides.bottom, sides.left);
        const bid = editor.dataset.block;
        const prop = editor.dataset.prop;
        const b = findBlockById_efb(bid);
        if (b) {
          if (!b.data) b.data = {};
          if (!_propStateSaved_efb) { saveState_efb(); _propStateSaved_efb = true; }
          b.data[prop] = shorthand;
          clearTimeout(_propDebounce_efb);
          _propDebounce_efb = setTimeout(() => { renderCanvas_efb(); syncToTextarea_efb(); }, 150);
        }
        return;
      }

      if (t.matches('.efb-prop-input')) {
        applyPropChange(t, false);
        return;
      }

      if (t.matches('.efb-prop-textarea')) {
        applyPropChange(t, false);
        return;
      }

      if (t.matches('.efb-chip-editor')) {
        const bid = t.dataset.block;
        const prop = t.dataset.prop;
        const b = findBlockById_efb(bid);
        if (b) {
          if (!b.data) b.data = {};
          if (!_propStateSaved_efb) { saveState_efb(); _propStateSaved_efb = true; }
          b.data[prop] = _chipEditorToValue_efb(t);
          clearTimeout(_propDebounce_efb);
          _propDebounce_efb = setTimeout(() => { renderCanvas_efb(); syncToTextarea_efb(); }, 250);
        }
        return;
      }
    });

    panel.addEventListener('change', (e) => {
      if (e.target.matches('.efb-prop-select')) {
        applyPropChange(e.target, true);
      }
      if (e.target.matches('.efb-sl-custom-svg')) {
        const bid = e.target.dataset.blockId;
        const idx = parseInt(e.target.dataset.linkIdx, 10);
        updateSocialLink_efb(bid, idx, 'customSvg', e.target.value);
      }
    });

    panel.addEventListener('click', (e) => {
      const linkBtn = e.target.closest('.efb-pad-link-btn');
      if (linkBtn) {
        e.preventDefault();
        const editor = linkBtn.closest('.efb-padding-editor');
        if (!editor) return;
        const isLinked = editor.classList.toggle('efb-pad-linked');
        const icon = linkBtn.querySelector('i');
        if (icon) {
          icon.className = isLinked ? 'efb bi-link-45deg' : 'efb bi-unlock';
        }
        linkBtn.classList.toggle('active', isLinked);
        if (isLinked) {
          const topVal = editor.querySelector('.efb-pad-input[data-side="top"]')?.value || '0';
          editor.querySelectorAll('.efb-pad-input').forEach(inp => { inp.value = topVal; });
          const sides = { top: +topVal, right: +topVal, bottom: +topVal, left: +topVal };
          const shorthand = _paddingSidesToShorthand_efb(sides.top, sides.right, sides.bottom, sides.left);
          const bid = editor.dataset.block;
          const prop = editor.dataset.prop;
          const b = findBlockById_efb(bid);
          if (b) {
            if (!b.data) b.data = {};
            if (!_propStateSaved_efb) { saveState_efb(); _propStateSaved_efb = true; }
            b.data[prop] = shorthand;
            renderCanvas_efb();
            syncToTextarea_efb();
          }
        }
        return;
      }

      const iconBtn = e.target.closest('.efb-sl-icon-btn');
      if (iconBtn) {
        e.preventDefault();
        const grid = iconBtn.closest('.efb-sl-icon-grid');
        if (grid) {
          const bid = grid.dataset.blockId;
          const idx = parseInt(grid.dataset.linkIdx, 10);
          const iconKey = iconBtn.dataset.icon;
          selectSocialIcon_efb(bid, idx, iconKey);
        }
        return;
      }

      const removeBtn = e.target.closest('.efb-chip-remove');
      if (!removeBtn) return;
      e.preventDefault();
      e.stopPropagation();
      const chip = removeBtn.closest('.efb-chip');
      const editor = chip?.closest('.efb-chip-editor');
      if (chip && editor) {
        chip.remove();
        const bid = editor.dataset.block;
        const prop = editor.dataset.prop;
        const b = findBlockById_efb(bid);
        if (b) {
          if (!b.data) b.data = {};
          if (!_propStateSaved_efb) { saveState_efb(); _propStateSaved_efb = true; }
          b.data[prop] = _chipEditorToValue_efb(editor);
          renderCanvas_efb();
          syncToTextarea_efb();
        }
      }
    });

    panel.addEventListener('keydown', (e) => {
      const editor = e.target.closest('.efb-chip-editor');
      if (!editor) return;

      if (e.key === 'Enter' && editor.classList.contains('efb-chip-editor-single')) {
        e.preventDefault();
        return;
      }

      if (e.key === 'Backspace') {
        const sel = window.getSelection();
        if (!sel.isCollapsed || sel.rangeCount === 0) return;
        const range = sel.getRangeAt(0);
        let prevNode = null;
        if (range.startOffset === 0 && range.startContainer !== editor) {
          prevNode = range.startContainer.previousSibling;
          if (!prevNode && range.startContainer.parentNode !== editor) {
            prevNode = range.startContainer.parentNode.previousSibling;
          }
        } else if (range.startContainer === editor && range.startOffset > 0) {
          prevNode = editor.childNodes[range.startOffset - 1];
        }
        if (prevNode && prevNode.nodeType === Node.ELEMENT_NODE && prevNode.classList?.contains('efb-chip')) {
          e.preventDefault();
          prevNode.remove();
          const bid = editor.dataset.block;
          const prop = editor.dataset.prop;
          const b = findBlockById_efb(bid);
          if (b) {
            if (!b.data) b.data = {};
            if (!_propStateSaved_efb) { saveState_efb(); _propStateSaved_efb = true; }
            b.data[prop] = _chipEditorToValue_efb(editor);
            renderCanvas_efb();
            syncToTextarea_efb();
          }
        }
      }
    });

    panel.addEventListener('paste', (e) => {
      const editor = e.target.closest('.efb-chip-editor');
      if (!editor) return;
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text/plain');
      if (editor.classList.contains('efb-chip-editor-single')) {
        const clean = text.replace(/[\r\n]+/g, ' ');
        document.execCommand('insertHTML', false, _valueToChipHtml_efb(clean));
      } else {
        document.execCommand('insertHTML', false, _valueToChipHtml_efb(text));
      }
      const bid = editor.dataset.block;
      const prop = editor.dataset.prop;
      const b = findBlockById_efb(bid);
      if (b) {
        if (!b.data) b.data = {};
        if (!_propStateSaved_efb) { saveState_efb(); _propStateSaved_efb = true; }
        b.data[prop] = _chipEditorToValue_efb(editor);
        clearTimeout(_propDebounce_efb);
        _propDebounce_efb = setTimeout(() => { renderCanvas_efb(); syncToTextarea_efb(); }, 250);
      }
    });
  }

  function _applyColorPreset_efb(btn) {
    const color = btn.dataset.color;
    const prop = btn.dataset.prop;
    const blockId = btn.dataset.block;
    if (!color || !prop || !blockId) return;

    const block = findBlockById_efb(blockId);
    if (!block) return;
    if (!block.data) block.data = {};

    saveState_efb();
    block.data[prop] = color;

    const row = btn.closest('.efb-prop-row');
    if (row) {
      const cp = row.querySelector(`.efb-prop-color[data-prop="${prop}"]`);
      if (cp) cp.value = color;
      const txt = row.querySelector(`.efb-prop-color-text[data-prop="${prop}"]`);
      if (txt) txt.value = color;
    }

    renderCanvas_efb();
    syncToTextarea_efb();
  }

  function insertShortcode_efb(blockId, propName, shortcode) {
    const block = findBlockById_efb(blockId);
    if (!block) return;
    if (!block.data) block.data = {};
    saveState_efb();
    const def = BLOCK_TYPES_efb[block.type]?.defaultData || {};
    const current = block.data[propName] !== undefined ? block.data[propName] : (def[propName] || '');
    const separator = current && !current.endsWith(' ') && !current.endsWith('\n') ? ' ' : '';
    block.data[propName] = current + separator + shortcode;
    renderCanvas_efb();
    renderPropertiesPanel_efb();
    syncToTextarea_efb();
  }

  function updateSocialLink_efb(blockId, index, key, value) {
    const block = findBlockById_efb(blockId);
    if (!block || !block.data?.links?.[index]) return;
    saveState_efb();
    block.data.links[index][key] = value;
    renderCanvas_efb();
    syncToTextarea_efb();
  }

  function addSocialLink_efb(blockId, iconKey) {
    const block = findBlockById_efb(blockId);
    if (!block) return;
    saveState_efb();
    if (!block.data) block.data = {};
    if (!block.data.links) block.data.links = [];
    const key = iconKey || 'website';
    const preset = SOCIAL_PRESETS_efb[key];
    block.data.links.push({ icon: key, name: preset?.label || 'Link', url: '#' });
    renderCanvas_efb();
    renderPropertiesPanel_efb();
    syncToTextarea_efb();
  }

  function selectSocialIcon_efb(blockId, index, iconKey) {
    const block = findBlockById_efb(blockId);
    if (!block?.data?.links?.[index]) return;
    saveState_efb();
    const link = block.data.links[index];
    link.icon = iconKey;
    const preset = SOCIAL_PRESETS_efb[iconKey];
    if (preset) link.name = preset.label;
    if (iconKey !== 'custom') delete link.customSvg;
    renderCanvas_efb();
    renderPropertiesPanel_efb();
    syncToTextarea_efb();
  }

  function showSocialPicker_efb(blockId) {
    addSocialLink_efb(blockId, 'website');
  }

  function removeSocialLink_efb(blockId, index) {
    const block = findBlockById_efb(blockId);
    if (!block?.data?.links) return;
    saveState_efb();
    block.data.links.splice(index, 1);
    renderCanvas_efb();
    renderPropertiesPanel_efb();
    syncToTextarea_efb();
  }

  function showNotification_efb(msg, type) {
    const el = document.createElement('div');
    el.className = `efb-builder-notification efb-notif-${type || 'info'}`;
    el.innerHTML = msg;
    const builder = document.getElementById(BUILDER_ID_efb);
    if (builder) builder.appendChild(el);
    setTimeout(() => el.remove(), 3500);
  }

  function showPreview_efb() {
    const html = generateFullHTML_efb();
    if (!html.includes('shortcode_message')) {
      showNotification_efb('<i class="efb bi-exclamation-triangle-fill me-1"></i>' + t_efb('ebMustContainSC', 'Template must contain shortcode_message!'), 'warning');
      return;
    }

    let preview = html
      .replace(/shortcode_message/g, '<div style="background:#f0fdf4;padding:15px;border-radius:8px;border:1px solid #bbf7d0;"><strong>' + t_efb('name', 'Name') + ':</strong> John Doe<br><strong>' + t_efb('email', 'Email') + ':</strong> john@example.com<br><strong>' + t_efb('message', 'Message') + ':</strong> This is a sample form submission.</div>')
      .replace(/shortcode_title/g, t_efb('message', 'New Message'))
      .replace(/shortcode_website_name/g, 'My Website')
      .replace(/shortcode_website_url/g, '#')
      .replace(/shortcode_admin_email/g, 'admin@example.com');

    if (typeof show_modal_efb === 'function') {
      const blob = new Blob([preview], { type: 'text/html;charset=utf-8' });
      const blobUrl = URL.createObjectURL(blob);
      const iframeHtml = `<iframe src="${blobUrl}" style="width:100%;height:70vh;border:none;border-radius:8px;background:#fff;" onload="try{URL.revokeObjectURL(this.src)}catch(e){}"></iframe>`;
      show_modal_efb(iframeHtml, t_efb('preview', 'Preview'), '', 'saveBox');
      if (typeof state_modal_show_efb === 'function') state_modal_show_efb(1);
    } else {
      const win = window.open('', '_blank', 'width=700,height=800');
      if (win) {
        win.document.write(preview);
        win.document.close();
      }
    }
  }

  let _gsAbortController_efb = null;

  function renderGlobalSettings_efb() {
    const panel = document.getElementById('efb-global-settings');
    if (!panel) return;

    if (_gsAbortController_efb) _gsAbortController_efb.abort();
    _gsAbortController_efb = new AbortController();
    const signal = _gsAbortController_efb.signal;

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
          <label class="efb-prop-label">${t_efb('ebBorderRadius', 'Border Radius (px)')}: <span class="efb-range-val">${gs.borderRadius}</span></label>
          <input type="range" class="efb-prop-range efb-gs-range" data-gs="borderRadius" value="${gs.borderRadius}" min="0" max="30" />
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
        <div class="efb-prop-row efb-prop-color-row">
          <label class="efb-prop-label">${t_efb('ebBtnBgColor', 'Button Background')}</label>
          <div class="efb-color-picker-wrap">
            <input type="color" class="efb-gs-color" data-gs="btnBgColor" value="${gs.btnBgColor || '#202a8d'}" />
            <input type="text" class="efb-prop-input efb-gs-text" data-gs="btnBgColor" value="${gs.btnBgColor || '#202a8d'}" />
          </div>
        </div>
        <div class="efb-prop-row efb-prop-color-row">
          <label class="efb-prop-label">${t_efb('ebBtnTextColor', 'Button Text Color')}</label>
          <div class="efb-color-picker-wrap">
            <input type="color" class="efb-gs-color" data-gs="btnTextColor" value="${gs.btnTextColor || '#ffffff'}" />
            <input type="text" class="efb-prop-input efb-gs-text" data-gs="btnTextColor" value="${gs.btnTextColor || '#ffffff'}" />
          </div>
        </div>
      </div>`;

    updateCanvasGlobalStyles_efb();

    let _gsDebounce_efb = null;
    let _gsStateSaved_efb = false;
    function gsChanged_efb() {
      clearTimeout(_gsDebounce_efb);
      _gsDebounce_efb = setTimeout(() => {
        updateCanvasGlobalStyles_efb();
        syncToTextarea_efb();
      }, 200);
    }

    panel.addEventListener('input', (e) => {
      const t = e.target;

      if (!_gsStateSaved_efb) { saveState_efb(); _gsStateSaved_efb = true; }

      if (t.matches('.efb-gs-color')) {
        builderState_efb.globalSettings[t.dataset.gs] = t.value;
        const txt = panel.querySelector(`.efb-gs-text[data-gs="${t.dataset.gs}"]`);
        if (txt) txt.value = t.value;
        updateCanvasGlobalStyles_efb();
        clearTimeout(_gsDebounce_efb);
        _gsDebounce_efb = setTimeout(() => { syncToTextarea_efb(); }, 200);
        return;
      }

      if (t.matches('.efb-gs-text')) {
        builderState_efb.globalSettings[t.dataset.gs] = t.value;
        const clr = panel.querySelector(`.efb-gs-color[data-gs="${t.dataset.gs}"]`);
        if (clr && /^#[0-9a-fA-F]{6}$/.test(t.value)) clr.value = t.value;
        gsChanged_efb();
        return;
      }

      if (t.matches('.efb-gs-input')) {
        builderState_efb.globalSettings[t.dataset.gs] = t.value;
        gsChanged_efb();
        return;
      }

      if (t.matches('.efb-gs-range')) {
        const lbl = t.closest('.efb-prop-row')?.querySelector('.efb-range-val');
        if (lbl) lbl.textContent = t.value;
        builderState_efb.globalSettings[t.dataset.gs] = t.value;
        updateCanvasGlobalStyles_efb();
        clearTimeout(_gsDebounce_efb);
        _gsDebounce_efb = setTimeout(() => { syncToTextarea_efb(); }, 150);
        return;
      }
    }, { signal });

    panel.addEventListener('change', (e) => {
      if (e.target.matches('.efb-gs-select')) {
        if (!_gsStateSaved_efb) { saveState_efb(); _gsStateSaved_efb = true; }
        builderState_efb.globalSettings[e.target.dataset.gs] = e.target.value;
        updateCanvasGlobalStyles_efb();
        syncToTextarea_efb();
      }
    }, { signal });
  }

  function updateCanvasGlobalStyles_efb() {
    const gs = builderState_efb.globalSettings;
    const canvas = document.getElementById(CANVAS_ID_efb);
    if (!canvas) return;

    canvas.style.backgroundColor = gs.contentBgColor || '#ffffff';
    canvas.style.maxWidth = (gs.contentWidth || 600) + 'px';
    canvas.style.borderRadius = (gs.borderRadius || 0) + 'px';
    canvas.style.direction = gs.direction || 'ltr';
    canvas.style.fontFamily = gs.fontFamily || DEFAULT_FONT_efb;

    const wrap = canvas.closest('.efb-builder-canvas-wrap');
    if (wrap) wrap.style.backgroundColor = gs.bgColor || '#f5f5f5';

    const br = parseInt(gs.borderRadius) || 0;
    const previews = canvas.querySelectorAll('.efb-block-preview');
    if (br > 0 && previews.length > 0) {
      previews[0].style.borderRadius = previews.length === 1
        ? br + 'px'
        : `${br}px ${br}px 0 0`;
      previews[0].style.overflow = 'hidden';
      if (previews.length > 1) {
        previews[previews.length - 1].style.borderRadius = `0 0 ${br}px ${br}px`;
        previews[previews.length - 1].style.overflow = 'hidden';
      }
    }
  }

  function escHtml_efb(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function insertShortcodeFromPanel_efb(shortcode) {
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
          showNotification_efb('<i class="efb bi-check-circle-fill me-1"></i>' + t_efb('ebSCInserted', 'Shortcode inserted!'), 'success');
          return;
        }
      }
    }
    showNotification_efb('<i class="efb bi-lightbulb me-1"></i>' + t_efb('ebSCSelectBlock', 'Select a text block first, or shortcode copied to clipboard.'), 'info');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(shortcode).catch(() => {});
    }
  }

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
    showNotification_efb('<i class="efb bi-check-circle-fill me-1"></i>' + t_efb('ebCopied', 'Copied!'), 'success');
    setTimeout(() => {
      if (icon) icon.className = 'efb bi-clipboard';
      btn.classList.remove('efb-sc-copied');
    }, 1500);
  }

  let _tooltipEl_efb = null;
  let _tooltipHideTimer_efb = null;

  function initTooltipHandler_efb() {
    const builder = document.getElementById(BUILDER_ID_efb);
    if (!builder) return;

    if (_tooltipEl_efb && _tooltipEl_efb.parentNode) {
      _tooltipEl_efb.parentNode.removeChild(_tooltipEl_efb);
    }

    _tooltipEl_efb = document.createElement('div');
    _tooltipEl_efb.className = 'efb-tooltip-js';
    document.body.appendChild(_tooltipEl_efb);

    builder.addEventListener('mouseenter', (e) => {
      const target = e.target.closest('[data-efb-tooltip]');
      if (!target) return;
      clearTimeout(_tooltipHideTimer_efb);
      const text = target.getAttribute('data-efb-tooltip');
      if (!text) return;
      _tooltipEl_efb.textContent = text;
      _tooltipEl_efb.className = 'efb-tooltip-js';

      const rect = target.getBoundingClientRect();
      const ttWidth = 240;
      const ttHeight = _tooltipEl_efb.offsetHeight || 60;
      const vpWidth = window.innerWidth;
      const vpHeight = window.innerHeight;
      const margin = 10;

      _tooltipEl_efb.style.visibility = 'hidden';
      _tooltipEl_efb.style.opacity = '0';
      _tooltipEl_efb.style.display = 'block';

      if (rect.right + margin + ttWidth < vpWidth) {
        _tooltipEl_efb.style.left = (rect.right + margin) + 'px';
        _tooltipEl_efb.style.top = (rect.top + rect.height / 2 - ttHeight / 2) + 'px';
        _tooltipEl_efb.classList.add('efb-tooltip-right');
      } else if (rect.left - margin - ttWidth > 0) {
        _tooltipEl_efb.style.left = (rect.left - margin - ttWidth) + 'px';
        _tooltipEl_efb.style.top = (rect.top + rect.height / 2 - ttHeight / 2) + 'px';
        _tooltipEl_efb.classList.add('efb-tooltip-left');
      } else {
        _tooltipEl_efb.style.left = (rect.left + rect.width / 2 - ttWidth / 2) + 'px';
        _tooltipEl_efb.style.top = (rect.bottom + margin) + 'px';
        _tooltipEl_efb.classList.add('efb-tooltip-bottom');
      }

      const ttRect = _tooltipEl_efb.getBoundingClientRect();
      if (ttRect.top < 0) _tooltipEl_efb.style.top = '4px';
      if (ttRect.bottom > vpHeight) _tooltipEl_efb.style.top = (vpHeight - ttHeight - 4) + 'px';
      if (ttRect.left < 0) _tooltipEl_efb.style.left = '4px';
      if (ttRect.right > vpWidth) _tooltipEl_efb.style.left = (vpWidth - ttWidth - 4) + 'px';

      _tooltipEl_efb.classList.add('efb-tooltip-visible');
      _tooltipEl_efb.style.visibility = '';
      _tooltipEl_efb.style.opacity = '';
    }, true);

    builder.addEventListener('mouseleave', (e) => {
      const target = e.target.closest('[data-efb-tooltip]');
      if (!target) return;
      _tooltipHideTimer_efb = setTimeout(() => {
        if (_tooltipEl_efb) {
          _tooltipEl_efb.classList.remove('efb-tooltip-visible');
        }
      }, 100);
    }, true);
  }

  function exportHTML_efb() {
    const html = generateFullHTML_efb();
    const blob = new Blob([html], { type: 'text/html' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'email-template.html';
    a.click();
    URL.revokeObjectURL(a.href);
    showNotification_efb('<i class="efb bi-check-circle-fill me-1"></i>' + t_efb('ebTemplateExported', 'Template exported!'), 'success');
  }

  function showCodeEditor_efb() {
    const textarea = document.getElementById(TEXTAREA_ID_efb);
    const codePanel = document.getElementById('efb-code-editor-panel');
    const codeArea = document.getElementById('efb-code-editor-textarea');
    if (!codePanel || !codeArea) return;

    codeArea.value = generateFullHTML_efb();
    codePanel.style.display = codePanel.style.display === 'none' ? 'block' : 'none';
  }

  function applyCodeEditor_efb() {
    const codeArea = document.getElementById('efb-code-editor-textarea');
    const textarea = document.getElementById(TEXTAREA_ID_efb);
    if (!codeArea || !textarea) return;

    const code = codeArea.value;
    if (code.includes('<script')) {
      showNotification_efb('<i class="efb bi-exclamation-triangle-fill me-1"></i>' + t_efb('NAllowedscriptTag', 'Script tags are not allowed!'), 'warning');
      return;
    }
    textarea.value = code;
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
    showNotification_efb('<i class="efb bi-check-circle-fill me-1"></i>' + t_efb('ebHTMLApplied', 'HTML code applied!'), 'success');
  }

  function renderBlocksPanel_efb() {
    const panel = document.getElementById('efb-blocks-panel');
    if (!panel) return;

    const categories = {
      'layout': { label: '<i class="efb bi-rulers me-1"></i>' + t_efb('ebCatLayout', 'Layout'), blocks: [] },
      'content': { label: '<i class="efb bi-pencil-square me-1"></i>' + t_efb('ebCatContent', 'Content'), blocks: [] },
      'shortcode': { label: '<i class="efb bi-braces me-1"></i>' + t_efb('ebCatShortcodes', 'Shortcodes'), blocks: [] },
      'advanced': { label: '<i class="efb bi-gear me-1"></i>' + t_efb('ebCatAdvanced', 'Advanced'), blocks: [] }
    };

    for (const [type, def] of Object.entries(BLOCK_TYPES_efb)) {
      const cat = categories[def.category] || categories.advanced;
      cat.blocks.push({ type, ...def });
    }

    const shortcodeDefs = [
      { code: 'shortcode_message', label: t_efb('ebSCMessage', 'Message *'), desc: t_efb('shortcodeMessageInfo', 'Add this shortcode inside an HTML tag to display the message content of an email.'), required: true },
      { code: 'shortcode_title', label: t_efb('ebSCTitle', 'Title'), desc: t_efb('shortcodeTitleInfo', 'Add this shortcode inside a tag to display the title of the email.'), required: false },
      { code: 'shortcode_website_name', label: t_efb('ebSCSiteName', 'Site Name'), desc: t_efb('shortcodeWebsiteNameInfo', 'To display the website name, add this shortcode inside an HTML tag.'), required: false },
      { code: 'shortcode_website_url', label: t_efb('ebSCSiteURL', 'Site URL'), desc: t_efb('shortcodeWebsiteUrlInfo', 'Add this shortcode within an HTML tag to display the Website URL.'), required: false },
      { code: 'shortcode_admin_email', label: t_efb('ebSCAdminEmail', 'Admin Email'), desc: t_efb('shortcodeAdminEmailInfo', 'You can display the Admin Email address of your WordPress site by adding this shortcode within an HTML tag.'), required: false }
    ];

    let html = '';
    for (const [catKey, cat] of Object.entries(categories)) {
      if (catKey === 'shortcode') {
        html += `<div class="efb-block-category">
          <div class="efb-cat-label">${cat.label}</div>
          <div class="efb-cat-blocks">`;

        cat.blocks.forEach(b => {
          html += `<div class="efb-draggable-block efb-sc-draggable-required" draggable="true" data-block-type="${b.type}" title="${b.label}">
            <i class="efb ${b.icon}"></i>
            <span>${b.label}</span>
            <span class="efb-sc-badge-required">*</span>
          </div>`;
        });

        html += `<div class="efb-sc-section-header">
          <i class="efb bi-code-square"></i>
          <span>${t_efb('ebSCReference', 'Shortcode Reference')}</span>
        </div>`;

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

    panel.querySelectorAll('.efb-draggable-block').forEach(el => {
      el.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('efb-new-block', el.dataset.blockType);
        e.dataTransfer.effectAllowed = 'copy';
      });
      el.addEventListener('click', () => {
        addBlock_efb(el.dataset.blockType);
      });
    });
  }

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
          <div id="${CANVAS_ID_efb}" class="efb-builder-canvas" role="list" aria-label="${t_efb('ebEmailBlocks', 'Email template blocks')}"></div>
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
            <button class="efb-tb-btn efb text-dark" onclick="efbEmailBuilder.applyCodeEditor_efb()"><i class="efb bi-check-lg text-info"></i> ${t_efb('ebApply', 'Apply')}</button>
            <button class="efb-tb-btn efb text-dark" onclick="document.getElementById('efb-code-editor-panel').style.display='none'"><i class="efb bi-x-lg text-danger"></i> ${t_efb('close', 'Close')}</button>
          </div>
        </div>
        <textarea id="efb-code-editor-textarea" class="efb-code-textarea" spellcheck="false"></textarea>
      </div>

      <!-- Hidden builder JSON storage -->
      <input type="hidden" id="efb-builder-json" value="" />
    `;

    const textarea = document.getElementById(TEXTAREA_ID_efb);
    const existingHtml = textarea ? textarea.value : '';
    tryParseExistingTemplate_efb(existingHtml);

    const _btnBgInit = document.getElementById('emailBtnBgColor_emsFormBuilder');
    const _btnTxtInit = document.getElementById('emailBtnTextColor_emsFormBuilder');
    if (!builderState_efb.globalSettings.btnBgColor || builderState_efb.globalSettings.btnBgColor === '#202a8d') {
      if (_btnBgInit && _btnBgInit.value && _btnBgInit.value !== '#202a8d') builderState_efb.globalSettings.btnBgColor = _btnBgInit.value;
    }
    if (!builderState_efb.globalSettings.btnTextColor || builderState_efb.globalSettings.btnTextColor === '#ffffff') {
      if (_btnTxtInit && _btnTxtInit.value && _btnTxtInit.value !== '#ffffff') builderState_efb.globalSettings.btnTextColor = _btnTxtInit.value;
    }

    renderBlocksPanel_efb();
    renderTemplatesPanel_efb();
    renderGlobalSettings_efb();
    renderCanvas_efb();
    renderPropertiesPanel_efb();
    initPropertyDelegation_efb();
    initTooltipHandler_efb();
    syncToTextarea_efb();

    if (window._efbKeyHandler) document.removeEventListener('keydown', window._efbKeyHandler);
    window._efbKeyHandler = (e) => {
      if (!document.getElementById(BUILDER_ID_efb)) return;
      if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) { e.preventDefault(); undo_efb(); }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); redo_efb(); }
      if (e.key === 'Delete' && builderState_efb.selectedBlock) {
        const active = document.activeElement;
        if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable)) return;
        removeBlock_efb(builderState_efb.selectedBlock);
      }
      if ((e.key === 'ArrowUp' || e.key === 'ArrowDown') && builderState_efb.selectedBlock) {
        const active = document.activeElement;
        if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable)) return;
        e.preventDefault();
        const idx = builderState_efb.blocks.findIndex(b => b.id === builderState_efb.selectedBlock);
        if (idx === -1) return;
        const newIdx = e.key === 'ArrowUp' ? idx - 1 : idx + 1;
        if (newIdx >= 0 && newIdx < builderState_efb.blocks.length) {
          builderState_efb.selectedBlock = builderState_efb.blocks[newIdx].id;
          renderCanvas_efb();
          renderPropertiesPanel_efb();
          const el = document.querySelector(`.efb-canvas-block[data-block-id="${builderState_efb.selectedBlock}"]`);
          if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }
    };
    document.addEventListener('keydown', window._efbKeyHandler);
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
    showNotification_efb('<i class="efb bi-check-circle-fill me-1"></i>' + t_efb('ebTemplateReset', 'Template reset to default!'), 'success');
  }

  function injectStyles_efb() {
    if (document.getElementById('efb-email-builder-styles')) return;
    const style = document.createElement('style');
    style.id = 'efb-email-builder-styles';
    style.textContent = `

    #${BUILDER_ID_efb} {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      background: #f8fafc;
      overflow: hidden;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      font-size: 13px;
      position: relative;
    }

    #${BUILDER_ID_efb} .me-1 { margin-inline-end: .25rem; }
    #${BUILDER_ID_efb} .ms-1 { margin-inline-start: .25rem; }

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

    .efb-builder-layout {
      display: grid;
      grid-template-columns: 220px 1fr 260px;
      grid-template-rows: 1fr;
      min-height: 550px;
      max-height: 80vh;
      overflow: hidden;
    }

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

    .efb-canvas-block {
      position: relative;
      margin: 4px 0;
      border: 2px solid transparent;
      border-radius: 6px;
      transition: all .15s;
      cursor: pointer;
      animation: efbBlockIn .25s ease-out;
    }
    @keyframes efbBlockIn {
      from { opacity: 0; transform: translateY(-8px); }
      to { opacity: 1; transform: translateY(0); }
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

    .efb-canvas-block.efb-dragging { opacity: 0.4; }
    .efb-canvas-block.efb-drop-above { border-top: 3px solid #667eea; }
    .efb-canvas-block.efb-drop-below { border-bottom: 3px solid #667eea; }

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

    .efb-prop-color-row { }
    .efb-color-picker-wrap { display: flex; gap: 6px; align-items: center; }
    .efb-prop-color {
      width: 32px; height: 32px; padding: 1px; border: 1px solid #e2e8f0; border-radius: 6px;
      cursor: pointer; background: none; flex-shrink: 0;
    }
    .efb-prop-color-text { flex: 1; font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace; font-size: 12px; letter-spacing: 0.5px; direction: ltr; text-align: start; }

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

    .efb-chip-editor {
      width: 100%;
      padding: 5px 8px;
      border: 1px solid #e2e8f0;
      border-radius: 5px;
      font-size: 12px;
      color: #334155;
      background: #ffffff;
      transition: border-color .15s, box-shadow .15s;
      box-sizing: border-box;
      font-family: inherit;
      min-height: 32px;
      line-height: 1.9;
      outline: none;
      cursor: text;
      word-wrap: break-word;
      white-space: pre-wrap;
    }
    .efb-chip-editor:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }
    .efb-chip-editor:empty::before {
      content: attr(data-placeholder);
      color: #94a3b8;
      pointer-events: none;
    }
    .efb-chip-editor-single {
      white-space: nowrap;
      overflow: hidden;
    }
    .efb-chip-editor-multi {
      min-height: 60px;
      overflow-y: auto;
    }
    .efb-chip {
      display: inline-flex;
      align-items: center;
      gap: 2px;
      padding: 1px 5px 1px 6px;
      margin: 1px 2px;
      background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
      border: 1px solid #c7d2fe;
      border-radius: 4px;
      font-size: 10.5px;
      font-weight: 600;
      color: #4338ca;
      line-height: 1.6;
      cursor: default;
      user-select: none;
      vertical-align: middle;
      white-space: nowrap;
    }
    .efb-chip i { font-size: 10px; opacity: .7; }
    .efb-chip-remove {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 14px;
      height: 14px;
      margin-inline-start: 3px;
      border: none;
      border-radius: 50%;
      background: transparent;
      color: #818cf8;
      cursor: pointer;
      font-size: 13px;
      font-weight: 700;
      line-height: 1;
      padding: 0;
      transition: all .15s;
    }
    .efb-chip-remove:hover {
      background: #c7d2fe;
      color: #4338ca;
    }

    .efb-padding-editor {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .efb-pad-sides {
      display: flex;
      gap: 4px;
      flex: 1;
    }
    .efb-pad-side {
      display: flex;
      flex-direction: column;
      align-items: center;
      flex: 1;
    }
    .efb-pad-side-label {
      font-size: 9px;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      margin-bottom: 2px;
      letter-spacing: 0.5px;
    }
    .efb-pad-input {
      width: 100%;
      padding: 4px 2px;
      border: 1px solid #e2e8f0;
      border-radius: 4px;
      font-size: 12px;
      color: #334155;
      background: #ffffff;
      text-align: center;
      box-sizing: border-box;
      transition: border-color .15s;
      -moz-appearance: textfield;
    }
    .efb-pad-input::-webkit-inner-spin-button,
    .efb-pad-input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
    .efb-pad-input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 2px rgba(102,126,234,0.12);
    }
    .efb-pad-link-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      background: #f8fafc;
      color: #94a3b8;
      cursor: pointer;
      font-size: 14px;
      padding: 0;
      flex-shrink: 0;
      transition: all .15s;
      margin-top: 10px;
    }
    .efb-pad-link-btn:hover { background: #f1f5f9; border-color: #cbd5e1; }
    .efb-pad-link-btn.active {
      background: #eef2ff;
      border-color: #c7d2fe;
      color: #667eea;
    }
    .efb-pad-linked .efb-pad-input {
      border-color: #c7d2fe;
      background: #fefeff;
    }

    .efb-social-link-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; margin-bottom:8px; overflow:hidden; }
    .efb-sl-header { display:flex; align-items:center; gap:6px; padding:6px 8px; background:#f1f5f9; border-bottom:1px solid #e2e8f0; }
    .efb-sl-header .efb-blk-btn { margin-inline-start:auto; }
    .efb-sl-name { font-size:11px; font-weight:600; color:#334155; flex:1; }
    .efb-sl-icon-preview { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; }
    .efb-sl-icon-preview svg { display:block; }
    .efb-sl-custom-preview svg { width:18px; height:18px; }
    .efb-sl-body { padding:8px; }
    .efb-sl-field-label { display:block; font-size:10px; font-weight:600; color:#64748b; margin-bottom:2px; text-transform:uppercase; letter-spacing:.3px; }
    .efb-sl-icon-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(28px,1fr)); gap:3px; margin-bottom:4px; }
    .efb-sl-icon-btn { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px;
      border:1px solid #e2e8f0; border-radius:4px; background:#fff; cursor:pointer; padding:0; transition:all .15s; }
    .efb-sl-icon-btn svg, .efb-sl-icon-btn i { pointer-events:none; }
    .efb-sl-icon-btn:hover { border-color:#93c5fd; background:#eff6ff; transform:scale(1.1); }
    .efb-sl-icon-btn.active { border-color:#3b82f6; background:#dbeafe; box-shadow:0 0 0 2px rgba(59,130,246,.25); }
    .efb-sl-url { width:100%; }
    .efb-sl-custom-svg { width:100%; font-family:monospace; font-size:10px; resize:vertical; min-height:40px; }
    .efb-btn-sm { padding: 4px 8px; font-size: 11px; }
    .efb-btn-add {
      display: inline-flex; align-items: center; gap: 3px;
      background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px;
      color: #16a34a; cursor: pointer; font-size: 11px; padding: 4px 8px;
    }
    .efb-btn-add:hover { background: #dcfce7; }

    .efb-child-props {
      padding: 8px;
      margin: 6px 0;
      background: #fafbfc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
    }

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

    .efb-sidebar-content::-webkit-scrollbar,
    .efb-builder-canvas-wrap::-webkit-scrollbar,
    .efb-builder-sidebar-right::-webkit-scrollbar {
      width: 6px;
    }
    .efb-sidebar-content::-webkit-scrollbar-track,
    .efb-builder-canvas-wrap::-webkit-scrollbar-track,
    .efb-builder-sidebar-right::-webkit-scrollbar-track {
      background: transparent;
    }
    .efb-sidebar-content::-webkit-scrollbar-thumb,
    .efb-builder-canvas-wrap::-webkit-scrollbar-thumb,
    .efb-builder-sidebar-right::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 3px;
    }
    .efb-sidebar-content::-webkit-scrollbar-thumb:hover,
    .efb-builder-canvas-wrap::-webkit-scrollbar-thumb:hover,
    .efb-builder-sidebar-right::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }
    .efb-sidebar-content,
    .efb-builder-canvas-wrap,
    .efb-builder-sidebar-right {
      scrollbar-width: thin;
      scrollbar-color: #cbd5e1 transparent;
    }

    .efb-tb-btn:focus-visible,
    .efb-blk-btn:focus-visible,
    .efb-stab:focus-visible,
    .efb-sc-action-btn:focus-visible,
    .efb-sc-btn:focus-visible,
    .efb-draggable-block:focus-visible,
    .efb-template-card:focus-visible {
      outline: 2px solid #667eea;
      outline-offset: 2px;
    }
    .efb-prop-input:focus-visible,
    .efb-prop-textarea:focus-visible,
    .efb-prop-select:focus-visible,
    .efb-chip-editor:focus-visible {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
    }
    .efb-canvas-block:focus-visible {
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
      outline: none;
    }

    .efb-color-presets {
      display: flex;
      flex-wrap: wrap;
      gap: 3px;
      margin-top: 4px;
    }
    .efb-color-swatch {
      width: 18px;
      height: 18px;
      border-radius: 4px;
      border: 1px solid #e2e8f0;
      cursor: pointer;
      transition: transform .1s, box-shadow .1s;
      padding: 0;
      outline: none;
    }
    .efb-color-swatch:hover {
      transform: scale(1.2);
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      z-index: 1;
    }

    @media (max-width: 900px) {
      .efb-builder-layout {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr auto;
      }
      .efb-builder-sidebar-left { border-right: none; border-bottom: 1px solid #e2e8f0; max-height: 200px; }
      .efb-builder-sidebar-right { border-left: none; border-top: 1px solid #e2e8f0; max-height: 300px; }
    }

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

    .efb-tooltip-js {
      position: fixed;
      z-index: 9999;
      background: #1e293b;
      color: #f1f5f9;
      font-size: 11px;
      font-weight: 400;
      line-height: 1.5;
      padding: 8px 12px;
      border-radius: 6px;
      width: 220px;
      max-width: 280px;
      pointer-events: none;
      opacity: 0;
      visibility: hidden;
      transition: opacity .2s ease, visibility .2s ease;
      box-shadow: 0 4px 16px rgba(0,0,0,0.18);
      text-align: start;
      word-wrap: break-word;
    }
    .efb-tooltip-js.efb-tooltip-visible {
      opacity: 1;
      visibility: visible;
    }
    .efb-tooltip-js::before {
      content: '';
      position: absolute;
      border: 6px solid transparent;
    }
    .efb-tooltip-js.efb-tooltip-right::before {
      right: 100%;
      top: 50%;
      transform: translateY(-50%);
      border-right-color: #1e293b;
    }
    .efb-tooltip-js.efb-tooltip-left::before {
      left: 100%;
      top: 50%;
      transform: translateY(-50%);
      border-left-color: #1e293b;
    }
    .efb-tooltip-js.efb-tooltip-bottom::before {
      bottom: 100%;
      left: 50%;
      transform: translateX(-50%);
      border-bottom-color: #1e293b;
    }
    .efb-tooltip-js.efb-tooltip-top::before {
      top: 100%;
      left: 50%;
      transform: translateX(-50%);
      border-top-color: #1e293b;
    }
    [data-efb-tooltip] { position: relative; }
    [data-efb-tooltip]::after,
    [data-efb-tooltip]::before { display: none !important; }
    `;
    document.head.appendChild(style);
  }

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
    selectSocialIcon_efb,
    showSocialPicker_efb,
    loadTemplate_efb,
    showPreview_efb,
    exportHTML_efb,
    showCodeEditor_efb,
    applyCodeEditor_efb,
    resetBuilder_efb,
    switchSidebarTab_efb,
    copyShortcode_efb,
    insertShortcodeFromPanel_efb,
    _applyColorPreset_efb,
    undo_efb,
    redo_efb,
    getState: () => builderState_efb,
    generateHTML: generateFullHTML_efb,
    syncToTextarea_efb
  };

})();
