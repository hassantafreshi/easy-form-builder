# Inline validation and the error summary — the page contract (v1)

F16 (`docs/feature-gaps/F16-a11y-inline-validation.fa.md`) built accessible errors for every Easy Form Builder form.
Its reusable part is a small module any page can load: **`window.EFBInline`, contract version 1** (F20 prerequisite
**P21**). The customer portal add-on (F20) uses it for its sign-in and edit screens; any add-on that draws fields of its
own (calculations, typed signature, postal formats, booking, CAPTCHA boxes, payment fields) uses it through the core
helpers below.

The module never talks to the server and never decides what is valid. The server stays authoritative; the module only
shows what a check (yours, or the server's answer) found, in a way screen readers, keyboards and phones can use.

Files: `public/assets/js/inline-efb.js` (script handle `efb-inline-js`), PHP twins in
`includes/inline-validation-functions-efb.php`. Tests: `tests/test-inline-api.js`, `tests/test-a11y-*.js|php`.

---

## 1. Discovery and loading

```php
if (function_exists('efb_inline_api')) {
    $api = efb_inline_api();
    // ['version' => 1, 'features' => ['field_error', 'live_region', 'summary', 'focus', 'server_errors', 'timing',
    //  'unsaved', 'digits', 'list'], 'script' => 'efb-inline-js', 'global' => 'EFBInline']
    $handle = efb_inline_enqueue(); // the script, plus window.efb_inline_text (its phrases) for a page without a form
}
```

On a page with an EFB form the script is already there (a dependency of the form runtime). `efb_inline_enqueue(false)`
skips the phrases when the page has its own `efb_var.text` / `ajax_object_efm.text`. In JavaScript, check
`window.EFBInline && EFBInline.version >= 1`. A breaking change will be version 2; features are only added to v1.

Phrases (`efb_inline_texts()`): `a11yErrPrefix` "Error:", `a11yErrSumOne` "There is a problem", `a11yErrSumN`
"%s problems need your attention", `a11yStepNow` "Step %1$s of %2$s", `a11yStay`. Numbers in them use the page's digits.

## 2. Markup the module expects

| Piece | Convention | Notes |
|---|---|---|
| a field | `<input|select|textarea id="{id}_">` | any id works when you pass the message box yourself |
| its message box | `<small id="{id}_-message" style="display:none"></small>` | or `{element id}-message`, or `data-efb-msg="{box id}"` on the field |
| its label | `id="{id}_lab"`, or `<label for="{id}_">` | used for the summary line and the announcement |
| a group | `role="radiogroup"` / `role="group"`, `id="{id}_options"`, `aria-labelledby` | `aria-invalid` and the message go on the group |
| the form's live region | `[data-efb-live]` inside the form (made when missing) | `role="status" aria-live="polite" aria-atomic="true"` |
| the summary box | `.efb-errsum` inside the form (made at its start when missing) | EFB forms render `#efb-errsum-{form id}` |

The message box should sit inside the field's block, before or after the input, never inside the `<label>`.

## 3. API

### One field

- `EFBInline.setError(el, message, opts)` — shows `message` **as text** in the field's box, sets `aria-invalid="true"`
  on the field (or its group), puts the box id first in `aria-describedby` (the existing ids stay), adds the hidden
  "Error:" (CSS reads `data-efb-prefix`, so `textContent` stays the message). `opts`: `msgEl` (the box), `announce`
  (say "{label}: {message}" in the live region), `label`, `quiet` (an error already shown keeps its words: used while
  the visitor is still typing), `html` (the message is server HTML such as `<b>Email</b>`: parsed, never run).
- `EFBInline.clearError(el, opts)` — hides and empties the box, removes `aria-invalid` and the box id (the rest of
  `aria-describedby` stays), and the field's line in a summary on screen (the last line hides the summary). While a
  pointer is down (the field was left by pressing Send) the box closes after the click, so Send does not move away.
- `EFBInline.isShown(el)`, `EFBInline.msgOf(el)`, `EFBInline.hostOf(el)`, `EFBInline.fieldId(el)`, `EFBInline.labelOf(scope, id)`.

### The form

- `EFBInline.announce(scope, text)` / `EFBInline.live(scope)` — the form's polite live region (one per form).
- `EFBInline.summary(scope, items, opts)` — the error summary: `items` = `[{id, label, message}]` in page order; a title
  ("There is a problem" / "N problems need your attention"), one link per item (`<bdi>` around the label, `dir="auto"`),
  an inner `role="alert"`, the box focused (`opts.focus: false` to skip) and scrolled to (no animation with
  `prefers-reduced-motion`). A link focuses its field: the input, the chosen (else first) choice of a group, the select,
  a phone input, a file button, else the field's block. Returns the box; `[]` hides it.
- `EFBInline.hideSummary(scope)`, `EFBInline.target(scope, id)`, `EFBInline.focusField(scope, id)`.
- `EFBInline.focusStep(fieldset, text)` — after showing another step: focus on the fieldset (`tabindex=-1`, its legend
  names it) and `text` in the live region.

### The server's answer

- `EFBInline.applyServer(scope, data, opts)` — `data` is the submit route's failure (`{success: false, m, field_id,
  code}`) or a list (`{errors: [{field_id, m, code, label}]}`). Every named field shows its sentence; the summary lists
  them (`opts.summary: false` to skip). Returns the number of fields marked.
- PHP: `efb_inline_error($field_id, $message, $code = '', $extra = [])` and `efb_inline_errors([[field_id, message,
  code?, label?], …], $code)` build those shapes. EFB's own submit route already answers `{success:false, m, field_id,
  code}` for a refused field (with `row_index` for a repeater row); keep the same keys.
- EFB forms fire `document` event `efb:submit-result` with `{form_id, ok, code, field_id}` after every answer.

### Any form: `attach()`

```js
const v = EFBInline.attach(document.getElementById('portal-signin'), {
  validate: (el) => el.name === 'email' && !/@/.test(el.value) ? T('enterTheEmail', 'Enter your e-mail') : '',
  onValid: (form, event) => { event.preventDefault(); /* send it */ },
  // timing: 'blur' (default) | 'change'; summary: true; fields: a selector
});
v.check(); // validate everything now, show the summary; returns the items
v.reset();
```

Timing "blur" (Baymard): nothing while typing; a field the visitor changed is checked when left; a field passed with
Tab only gets no "required" until Submit; once its error shows it is checked again 250 ms after each key and cleared as
soon as it is right, while a new kind of problem waits for the field to be left. Submit checks every visible field and
shows the summary; a valid Submit calls `onValid`. The value is never emptied.

### "Leave this page?"

`EFBInline.watch(form)` asks before the page is left while the form holds what the visitor typed (trusted `input` /
`change` only: defaults, restored drafts and scripts do not count). `EFBInline.clean(form, forever)` after sending (or
when the answers are kept elsewhere); `EFBInline.isDirty(form)`. Browsers show their own words; iOS Safari ignores it.

### Words and numbers

- `EFBInline.num(n, locale)` — the locale's digits (fa_IR ۱۲, ar_EG ١٢), from the browser's CLDR.
- `EFBInline.list(items, locale, type)` — "a, b and c" the language's way (Persian and Arabic "و" with their own comma).
- `EFBInline.plain(html)` — the text of a server message (DOMParser; nothing runs). `EFBInline.T(key, fallback)`.

## 4. In EFB forms (core helpers)

`core-efb.js` keeps its own checks and calls the module. Add-ons that draw a field inside an EFB form call:

| Helper | Use |
|---|---|
| `efb_set_field_error_efb(el, message, opts)` | the field's error (also paints the border, writes text) |
| `efb_clear_field_error_efb(el)` | clear it |
| `show_msg_efb(box)` / `hide_msg_efb(box)` | older code that writes the box itself: the ARIA follows |
| `efb_announce_efb(form_id, text)`, `efb_field_target_efb(form_id, id)`, `efb_focus_step_efb(form_id, step)` | live region, focus target, step focus |

Never set a message with `innerHTML`, never give the box `role="alert"` (the live region says it once), never hide an
error by colour only. Form keys: `val_timing` ("blur" | "change", missing = "change"), `err_summary` and
`warn_unsaved` ("1" | "0", missing = "0"); field key `autocomplete` ("" automatic, "off", or an autofill token with an
optional `section-*` and `shipping` / `billing`). New forms get `efb_form_defaults_efb()`.

## 5. Accessibility rules the module keeps (WCAG 2.2 AA)

1.3.1 / 4.1.2 (name, role, state: `aria-invalid`, `aria-describedby`, groups), 1.3.5 (autocomplete tokens), 1.4.1 (text,
not colour), 2.1.1 (keyboard: every link and target), 2.2.1 (no timed redirect without "Stay"), 2.4.3 (focus order: the
summary, the step, the final heading; never `<body>`), 3.3.1 / 3.3.3 (what is wrong and how to fix it), 4.1.3 (status
messages through one polite live region). The stylesheet adds visible focus, 44 px summary links, `forced-colors` and
`prefers-contrast: more` outlines and `prefers-reduced-motion`.
