# Spam protection in the core (F12)

What every Easy Form Builder site has without an add-on or an outside service, how a submission is judged, and the
hooks for security plugins. Spec: `docs/feature-gaps/F12-spam-core.fa.md`. CAPTCHA outside a form:
`docs/spam/captcha-contract.en.md`.

## Settings › Captchas (site)

| Key | Values | New site | Site updated from 4.3.1 |
|---|---|---|---|
| `captcha_provider` | `recaptcha_v2` · `recaptcha_v3` · `hcaptcha` · `turnstile` | `recaptcha_v2` | `recaptcha_v2` |
| `siteKey` / `secretKey` (v2), `rc3SiteKey` / `rc3SecretKey`, `hcSiteKey` / `hcSecretKey`, `tsSiteKey` / `tsSecretKey` | strings (a Turnstile key starts with `0x`, or `1x`/`2x`/`3x` for test keys) | — | — |
| `rc3Threshold` | 0.0 … 1.0 | 0.5 | 0.5 |
| `rc3_low_action` | `spam` · `block` | `spam` | `spam` |
| `spam_honeypot` | `'1'` / `''` | `'1'` | `''` |
| `spam_min_time` | 0 … 60 seconds | 3 | 0 |
| `spam_require_js` | `'1'` / `''` | `''` | `''` |
| `spam_action` | `spam` (stored with the Spam status) · `reject` (nothing stored) | `spam` | `spam` |
| `spam_akismet` | `'1'` / `''` (needs Akismet with a key) | `''` | `''` |
| `spam_disallowed_keys` | `'1'` / `''` (Settings › Discussion › Disallowed Comment Keys) | `''` | `''` |
| `spam_retention_days` | 0 … 3650 (0 = kept) | 30 | 0 |

Site keys are public (they reach the page); secret keys never do (not in the page, `pub` settings, a form, a log or
`forms/{id}/schema`). Only an administrator (`manage_options`) changes these settings. After changing CAPTCHA keys,
clear the page cache of pages that show a form in a widget.

Form keys (row 0, builder › form settings): `captcha` (on / off, as before), `captcha_provider` ('' = the site's),
`rc3_threshold` ('' = the site's), `spam_off` ('1': no soft check; the CAPTCHA still applies), `spam_akismet`
('0': Akismet off for this form). All free on every plan.

## How a submission is judged

Every submit route runs the same order (`forms/message/add`, `payments/create`, `payments/quote`, the legacy Stripe /
PersiaPay routes, the offline add-on's restore — through message/add):

1. **Hidden trap field** (`efb_hp`): filled → reason `honeypot`. Missing key (an old page, a direct REST call) → neutral.
2. **Minimum time**: now − the tab's session start (`t0` of the runtime token from `forms/session/prepare`, kept across
   refreshes, so it works on cached pages) under `spam_min_time` → `too_fast`. No runtime token → neutral.
3. **JavaScript**: no runtime token while `spam_require_js` → `no_js` (check Request Health first: a firewall that
   blocks the REST route would make every visitor spam).
4. The fields' own checks (unchanged).
5. **CAPTCHA** of the form (hard: refused with "Please complete the security check" / "The check expired"; codes
   `efb_captcha_failed`, `efb_captcha_expired`, `efb_captcha_network`, …; no secret → the owner's message). reCAPTCHA v3:
   a score under the minimum → `low_score` (or refused with `rc3_low_action = block`).
6. **Shield Security silentCAPTCHA** (`efb_submit_bot_decision`, unchanged, hard).
7. **Disallowed words** → `disallowed_keys`; **Akismet** → `akismet` (`akismet_discard` with "pro tip: discard"); Akismet
   fails open. Only answers that passed every check are sent; a field hidden by Conditional Logic is not in them.
8. **Verdict**: filter `efb_spam_verdict`. `spam` → stored with entry_status 1 (F14's Spam tab), the sender sees the usual
   success and confirmation code, nothing is e-mailed / sent by SMS / Telegram / webhook / Google Sheets, no place of a
   form limit, a unique value, a choice capacity or a booking is taken, the visit is "spam" (not counted as sent).
   `reject` → nothing stored, the uploads of that submission are deleted, "errorMRobot", code `efb_spam_rejected`
   (`pay_spam` on the payment routes). Payment, registration and login forms always reject. A form that stores no
   entries (F13) keeps nothing for spam and sends nothing.

F02 `verify/send` refuses a filled hidden field (no code is sent). Drafts and partial answers (F11) are not
submissions and are not judged.

## Spam entries

The Spam tab of the entries screen lists them with their reasons ("Spam: Hidden field filled, Sent too fast"); the
viewer shows the badge and a history line "Saved as spam (…)". "Not spam" moves an entry back and sends nothing
again (F14's Resend does); for an Akismet decision it reports `submit-ham` to Akismet, "Mark as spam" reports
`submit-spam` on forms Akismet checks. Spam older than `spam_retention_days` is deleted by the daily job (with its
files, notes and history).

## Privacy

Akismet receives the answer's name, e-mail, link and text, the page address, the language and — only as the form's
IP mode allows (F13) — the address (store: as is; anonymize: anonymized; do not store: no address and no browser).
The CAPTCHA services receive the token and the secret, never the visitor's address from the plugin.

## Content-Security-Policy

| Service | Hosts |
|---|---|
| reCAPTCHA v2 / v3 | `www.google.com`, `www.gstatic.com` |
| hCaptcha | `js.hcaptcha.com`, `*.hcaptcha.com` |
| Cloudflare Turnstile | `challenges.cloudflare.com` |

## Hooks

| Hook | Type | Use |
|---|---|---|
| `efb_core_spam_checks` | filter `(['honeypot'=>true,'min_time'=>true,'js'=>true], $ctx)` | a security add-on switches the core's duplicates off |
| `efb_spam_verdict` | filter `(['verdict'=>'ham'|'spam'|'reject','reasons'=>[…]], $ctx)` | decide or change a verdict (`$ctx`: form_id, action, stage, type, form) |
| `efb_shield_allow_side_effect` | filter | the core answers false at priority 1 for a spam answer |
| `efb_akismet_data` / `efb_akismet_is_test` | filters | the comment-check request |
| `efb_spam_retention_days` | filter | days spam is kept |
| `efb_captcha_verify_result`, `efb_captcha_failed`, `efb_captcha_check_hostname` | see the CAPTCHA contract | |
| `efb_entries_rows` | filter `($rows, $args)` | the entries list's rows (F12 adds `spam_reasons`) |

## Human Shield (AdnHSH) and Shield Security

- Human Shield decides on `rest_pre_dispatch` before the core; its own honeypot and fill time are separate (field names
  differ). Its side-effect stop-loss runs at priority 10 and returns at once when the core said false, so spam spends
  none of it. No change to the add-on was needed for 4.3.2. Follow-ups for its repository: switch the core's duplicate
  checks off through `efb_core_spam_checks` when its own are on; map "quarantine" to a stored spam entry through
  `efb_spam_verdict` instead of a soft refusal.
- Shield Security is a separate plugin; its silentCAPTCHA stays a hard gate after the CAPTCHA. A form with both needs
  both to pass.
