# The modal design system

Every dialog Easy Form Builder opens now wears one look, defined once in
`includes/admin/assets/css/modal-system-efb.css`.

| What | Where |
| ---- | ----- |
| the tokens and the `.efb-dlg__*` classes | `modal-system-efb.css` |
| the shared builder shell | `#settingModalEfb`, in panel/create/addon templates |
| its behaviour | `show_modal_efb()` in `new-efb.js`, `state_modal_show_efb()` in `admin-efb.js` |
| the reference implementation | the review invitation, `class-Emsfb-review-request.php` |

---

## Three rules the stylesheet keeps

1. **Every value comes from a token.** A dialog that needs a different colour
   picks a *tone*, it does not hard-code a hex.
2. **Direction is expressed with logical properties only** - `inset-inline`,
   `margin-inline`, `padding-inline`, `text-align: start/end`. There is no
   `[dir="rtl"]` section, because there is nothing to override.
3. **It is enqueued last.** `admin-rtl-efb.css` is registered *before*
   `bootstrap.min-efb.css`, so a class-level rule there already loses to
   bootstrap. Anything weaker than this file's position would lose too.

## Tokens

| Group | Values |
| ----- | ------ |
| Ink | `#1a1a2e` · soft `#4a5078` · muted `#8a90a8` · faint `#657096` |
| Surfaces | `#fff` · soft `#fafbfe` · sunken `#f4f5fb` · note `#eef1fb` |
| Lines | `#ecedf5` · strong `#e4e6f0` |
| Brand | `#3644d2` → `#202a8d`, light `#667eea` |
| Shape | radius 18px (22px mobile) · width 540px (412px mobile) |
| Shadow | `0 24px 60px rgba(26,26,46,.16), 0 2px 6px rgba(26,26,46,.06)` |
| Font | `Vazirmatn, Inter, -apple-system, "Segoe UI", Roboto, sans-serif` |

### Tones

Set one class on the dialog and the badge, head icon and shadow all follow:

| Class | Used for |
| ----- | -------- |
| `.efb-tone-brand` | duplicate, preview, drag guide |
| `.efb-tone-danger` | delete, crash report, email-template errors |
| `.efb-tone-warn` | auto-save restore, closing a conversation, the invitation |
| `.efb-tone-success` | the email server test, an issued coupon |
| `.efb-tone-gold` | Pro and Free Plus upsells |
| `.efb-tone-neutral` | form not found |
| `.efb-tone-orange` | plan downgrade |

## Anatomy

```
.efb-dlg                 overlay, flex-centred
  .efb-dlg__backdrop     rgba(26,26,46,.55) + 2px blur
  .efb-dlg__shell        white card, 18px, tabindex="-1"
    .efb-dlg__head       toned icon · 15/700 title · 32px close square
    .efb-dlg__body
      .efb-dlg__badge    62px gradient circle, efbDlgPop
      .efb-dlg__headline 18/700
      .efb-dlg__text     13.5/1.75, max 430px
      .efb-dlg__chip     the item being acted on
      .efb-dlg__note     the soft blue aside
    .efb-dlg__foot       centred pills on #fafbfe
```

Buttons are pills: `.efb-btn` plus `--primary`, `--danger`, `--gold`,
`--success`, `--ghost` or `--quiet`.

## How the existing dialogs were updated

The 27 `show_modal_efb()` call sites were **not** rewritten. The bottom half of
`modal-system-efb.css` maps the markup they already produce onto the tokens:

| Existing markup | Becomes |
| --------------- | ------- |
| `.efb-confirm-icon-wrap.efb-icon-*` | the 62px toned badge |
| `.efb-confirm-title` / `.efb-confirm-message` | headline / text |
| `.efb-confirm-message b` | the item chip |
| `.efb-btn-cancel` / `-confirm-danger` / `-confirm-primary` | ghost / danger / primary pills |
| `#settingModalEfb-close` | the 32px close square |
| `.efb-deactivate-*` | the survey, same tokens |
| `#efbColorResetModal`, `#efbModalFontFamily`, `#efbModalFontSize` | head / body / foot treatment |

Two dialogs were rebuilt rather than restyled, because their markup could not
carry the design:

- **the email server test** — see [email-server-test.md](email-server-test.md);
- **the auto-save restore prompt** — it used bare bootstrap buttons labelled
  "Yes" and "NO", neither of which said which one kept the draft. It now goes
  through `efb_build_confirm_body()` with an amber clock badge over a *blue*
  confirm (restoring is not destructive; "Start fresh" is the one that throws
  work away) and a chip saying when the draft was written. `store_form_efb()`
  had never recorded that time, so it does now.

`show_modal_efb()` takes an optional fifth argument for the confirm footer,
`{confirmLabel, cancelLabel}` — "Yes"/"No" is right for a delete and vague for
everything else.

### Two `!important` radii had to be dealt with

`admin-efb.css` pinned the shell to `20px !important` and confirm dialogs to
`16px !important`, which no later stylesheet could correct. Both now read
`var(--efb-dlg-radius, 18px)`; the `!important` stays where it was only ever
there to beat bootstrap, and the literal is the did-not-load fallback.

### Confirm dialogs keep their head bar hidden

`efb_build_confirm_body()` already prints the design's centred badge, title and
message. Showing the head bar as well would print the same word twice - "Delete"
over "Delete" - because both are passed `efb_var.text.delete`. The bar stays
hidden for `.efb-confirm-dialog` and only there.

## RTL

There is no RTL section, in this file or in `review-request-efb.css`. Both are
asserted comment-free against `[dir=`, `.rtl`, and every physical
`left`/`right`/`margin-left`-style property by `tests/test-review-request.php`,
section H.

The one thing logical properties cannot carry is the **star row**, which must
fill left-to-right in LTR and right-to-left in RTL. A `:hover ~ .star` selector
lights the stars *after* the pointer, which on an RTL row is the low end of the
scale - so the script lights them by index instead.

Codes, email addresses and shortcodes are pinned `direction: ltr` with
`unicode-bidi: isolate`, so a discount code is never mirrored inside a Persian
sentence.

## Tests

```
node tests/test-modal-system-browser.js    # 20 assertions
```

Drives the real `#settingModalEfb` through `show_modal_efb()` and reads the
computed styles back: the stylesheet is loaded and ordered after bootstrap, the
shell is 18px, the badge is a 62px gradient circle in the right tone per
variant, the buttons are pills, the footer is on the soft surface, and in RTL
the close control moves to the inline-end edge with no horizontal overflow.

No form is deleted or duplicated: the dialogs are opened directly rather than by
clicking a row action.
