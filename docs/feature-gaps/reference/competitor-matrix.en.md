# Competitor feature matrix (native capabilities, no integrations)

Researched 2026-11-10 from official docs of each product. Use this file as the
"what does the market do" reference when writing or implementing a spec in
`docs/feature-gaps/`. Items marked *(unverified)* were not confirmed on an
official page. Tier notes: GF = Gravity Forms (no free tier; Elite-only
add-ons noted), WPF = WPForms (Lite free / Basic / Plus / Pro / Elite),
FF = Fluent Forms (free / Pro), FRM = Formidable (Lite / Pro), NF = Ninja
Forms (free core + paid add-ons), FMN = Forminator (free; Pro only for PDF,
Geolocation, Stripe subscriptions), CF7 = Contact Form 7.

Status of Easy Form Builder (EFB) for every row is in
`../README.fa.md` and, with code evidence, in `current-capabilities.en.md`.

## 1. Text-like fields

| Capability | Who has it (how) |
|---|---|
| Input mask | GF (Single Line Text; presets US phone, date, tax ID, SSN, ZIP; tokens `9 a * ?`), WPF (`9 a A * &`, `[]` optional, `{n,m}`, aliases numeric/currency/decimal/integer/percentage/url/email, `date:`), FF (Mask Input field; 5 presets, tokens `0 9 # A *`), FRM (Pro input mask; Lite regex), NF (US phone/date/currency/custom `a 9 *`), Jotform (`# @ *`, "fill mask" forces completion), Everest (phone mask Pro). FMN: none found. |
| Regex / custom format validation | FRM (Lite), Jotform (help article), Everest (how-to), CF7 none |
| Validation presets | Jotform: alphabetic, alphanumeric, cyrillic, email, numeric, currency |
| Max characters | all |
| Min characters | CF7 `minlength:`, Jotform (min/max), FMN no |
| Word limit + live counter | WPF (characters OR words, live counter), NF (char/word limit with counter text), CF7 `[count]` tag (down counter), GF counter via filter only |
| Unique value (no duplicates) | GF "No Duplicates" (most fields), FF "Validate as Unique", FRM Pro "Unique", NF one unique field per form, WPF "Require unique answer" (Form Locker), Jotform "Unique Question" |
| Read-only | WPF, FRM Pro, Jotform, CF7 `readonly`, FMN (calculations only) |
| Disabled | NF "Disable Input" |
| Prefix / suffix text | FF (prefix/suffix label), FRM Pro (before/after input), FMN (calculations, slider) |
| Help tooltip / hover text | NF (help text icon), FF (help message: tooltip / below / on focus / before input), Jotform hover text |
| Description placement per field | GF (above/below), FMN (above/below) |
| Label placement | GF form-level top/left/right; FF top/left/right; FRM top/left/right/inline/none/hidden/floating; NF above/below/left/right/hidden |
| Admin-only label | GF, FF, NF |
| Field visibility: hidden / admin only / roles / logged-in | GF (visible/hidden/administrative), FRM Pro (everyone/roles/logged-in/logged-out) |
| Autocomplete attribute per field | GF (any token), FF (auto/off/specific), NF (developer mode), CF7 `autocomplete:` |
| Custom name attribute | FF, NF (dev mode) |
| Custom error message per rule | GF one custom validation message per field; FF per rule (required, email, numeric, min, max, digits, url, file types, file size, file count, phone); FRM Pro (required, unique, invalid format); FMN required + format messages; NF form-level custom error labels |
| Email confirmation (type twice) | GF, WPF, FRM Pro (inline/below), FMN, NF (Confirm field matches any field) |
| Email allow/deny list (domain wildcards) | WPF (`*@domain.com`, `*.tld`), FMN (address or domain, `*`) |
| Email domain typo suggestion | WPF |
| Email verification before submit | Jotform E-mail Validator widget, Fillout login page (email verification), Tally *(email verification)* |
| Phone formats | GF (international formatted/unformatted, US), WPF (Smart with flags, US, international), FF Pro (validate by country, auto country by IP, default country, allow/hide/only lists), FRM (international with flags Pro, custom mask/regex), FMN (national/international/char limit) |
| Phone OTP verification | Jotform *(unverified)*; otherwise third-party plugins only — not native in WP competitors |
| Password | GF (confirm, visibility toggle, strength meter + minimum), WPF (confirm, minimum strength), FMN (confirm, strength None/Short/Bad/Good/Strong, toggle) |
| Rich text input for visitors | GF (Paragraph "Use Rich Text Editor"), WPF (Rich Text field: basic/full toolbar, media upload), FF, FRM Pro, NF, FMN (textarea rich toggle), Fillout |
| Textarea rows | FF (rows/cols), FMN (default height), GF (field height) |
| Name field formats | GF (prefix/first/middle/last/suffix, sub-labels), WPF (simple / first last / first middle last), FF (first/middle/last each configurable), FMN (single or multiple incl. prefix) |
| Address field | GF (international/US/Canadian, per-input defaults, "same as previous" copy), WPF (US/international scheme, hide line2/postal/country), FF (6 subfields + Pro autocomplete Google/OSM, save lat/lng), FRM (international/US/Europe/other), FMN (Pro geolocation autocomplete + map) |

## 2. Number, calculation, pricing

| Capability | Who has it |
|---|---|
| Min / max | all |
| Step | FF (any float), FRM, NF, CF7, WPF slider increment |
| Number format (decimal comma/dot, thousands) | GF (dot/comma), FF (EU/US, separators), FRM Pro (custom thousand/decimal/decimals/symbol), FMN (decimal separator, rounding) |
| Currency format | FRM Pro, FMN Currency field, Jotform validation |
| Mobile keyboard type | FF (numeric/decimal/telephone) |
| Calculation / formula field | GF (Number "Enable Calculation", Product calc), WPF Pro Calculations add-on (if/elseif, AI formula), FF (formula with math functions), FRM Pro (Calculate value math/text), NF (named calcs, chainable), FMN (Calculations field, avg/min/max/round, repeater wildcard totals), Jotform Form Calculation widget, Paperform Excel-style |
| Calc value per choice | FF, NF, FMN, Ninja |
| Slider | WPF Number Slider (min/max/default/increment, `{value}` display), FF Pro Range Slider (quantity mapping), FRM Pro Slider, FMN (single or range, step labels, prefix/suffix, scale) |
| Per-option stock / inventory | FF Pro (simple or global across forms, hide/disable, show remaining), FRM (per-option limits across entries), FMN (select per-option submission limits), Jotform Inventory widget, Paperform product stock |
| Coupon | GF add-on, WPF Pro, FF |

## 3. Choices

| Capability | Who has it |
|---|---|
| Bulk add / predefined lists | GF (Bulk Add / Predefined Choices), WPF (Bulk Add + AI generate), FF (bulk edit with data sets), FRM (presets: countries, states, age, satisfaction, importance, agreement, likely), FMN (manual, CSV import, predefined lists), NF (bulk import) |
| Separate value and label | GF (Show Values), FF, FRM Pro (`Label|value`), NF, FMN, CF7 pipes |
| Randomize | WPF, FF (shuffle), FMN, Typeform, Google/Microsoft Forms (question shuffle) |
| "Other" option | GF (Multiple Choice select-one), WPF (radio/checkbox), FF (checkbox/radio), FRM Pro, FMN (radio/checkbox), CF7 `free_text`, Typeform, Paperform |
| Select all | GF (Checkboxes, Multiple Choice) |
| Min / max / exact selections | GF Multiple Choice (one / multiple / exact / range), FF (min/max), FRM (checkbox min/max), WPF (max only), FMN (limit), Tally, Paperform |
| Choice layout columns | GF (columns + across/down), WPF (1/2/3/inline), FF (vertical/horizontal/grid/button), FRM (1–4 columns), FMN (vertical/horizontal), NF image columns |
| Image choices | GF, WPF (+ icon choices, 2,000+ icons), FF, FRM Pro, NF Select Image, FMN, Typeform, Paperform |
| Searchable dropdown | GF (enhanced UI), WPF (Modern), FF (smart options), FRM (autocomplete), FMN (search) |
| Option groups (optgroup) | FF |
| Dynamic choices (posts, taxonomies, users, entries, CSV) | WPF (post type / taxonomy), FF Pro Dynamic Field (posts, terms, users, submissions, CSV/Google Sheet, filters), FRM Pro Lookup/Dynamic (from another form, cascading), Jotform dynamic dropdowns |
| Chained / dependent selects | GF add-on (Elite), FF Pro (CSV), FRM Pro Lookup cascading, Jotform |
| Ranking field | WPF, FF, FRM (Surveys add-on), GF Survey add-on (Rank), Typeform, Tally, Fillout, Paperform, Microsoft Forms |
| Likert / matrix | GF Survey add-on, WPF Pro, FF Pro Checkable Grid, FRM Surveys, Typeform, Tally, Jotform Input Table |
| NPS | WPF Pro, FF Pro, FRM Surveys, Typeform, Tally, Microsoft Forms |
| Rating options | WPF (scale 2–10, star/heart/thumb/smiley, size, color, low/high labels), FF Pro (custom SVG icons, show text), NF (number of stars), FMN (up to 50, icons, size) |

## 4. Date, time, upload, signature, location

| Capability | Who has it |
|---|---|
| Date input styles | GF (text inputs / picker / dropdowns, 7 formats), WPF (picker or dropdowns, 7 formats), FMN (calendar / dropdowns / text, 9 formats) |
| Min / max date | FF (Flatpickr minDate/maxDate), FRM add-on, FMN (fixed or relative to today), Jotform (start/end, relative `today+7`), CF7 |
| Disable weekdays | WPF (Limit Days), FMN (allowed weekdays), NF add-on, FF (Flatpickr `disable`) |
| Disable specific dates / ranges | FMN, FF, NF add-on, FRM add-on, Jotform |
| Disable past dates | WPF |
| Date range mode | FF (`mode: range`), Tally, Fillout |
| Age validation | Jotform |
| Default date (today ± N) | FMN, NF (default to current date) |
| Time field | GF (12/24h), WPF (12/24, interval 15/30/60, limit hours), FF (enableTime, increments), FRM Pro (12/24, step, start/end, unique with date = booking), NF (minute increment), FMN (dropdowns/number, increments, start/end), Jotform, Typeform *(unverified)* |
| Per date/time submission cap (booking) | NF add-on, FRM (unique time with date), Jotform Appointment |
| Multiple files in one field | GF (multi-file + max count), WPF (max file number, modern drag-drop chunked), FF Pro (max count), FRM Pro (multiple, max files, min size), FMN (single/multiple, count limit), NF add-on |
| Store uploads in Media Library | WPF, FMN, NF add-on |
| Camera capture | WPF (Enable Camera photo/video, Camera field), Jotform Take Photo, CF7 `capture:` |
| Image auto-resize / file protection | FRM Pro (auto resize, protect files, noindex), WPF (file access restrictions by user/password) |
| Delete file with entry | GF, FRM, FMN |
| Signature | GF add-on (colors, pen size, typed fonts), WPF Pro (draw or type), NF (typed/drawn/both, font), FMN (PNG/JPG, stroke), Jotform, Tally, Fillout, Paperform |
| Map / geolocation | WPF Pro Geolocation (address autocomplete + map), FF Pro (Google/OSM autocomplete, lat/lng), FMN Pro, Jotform Geolocation widget, Fillout location coordinates |
| Color picker | FF, Fillout, Paperform (restrict swatch) |
| QR / barcode scanner | Jotform widgets |

## 5. Structure and layout

| Capability | Who has it |
|---|---|
| Repeater | GF (beta; max rows, labels, nestable; no logic/calc inside), WPF (rows or blocks, min/max, presets), FF Pro Repeat Field and Repeat Container (calc values), FRM Pro Repeater (default/inline/grid, min/max, collapsible, cross-row totals), NF Repeatable Fieldset, FMN Field Group (min/max, dynamic by another field, wildcard totals), Jotform Configurable List, Fillout Subforms + Table |
| Multi-column layout container | WPF Layout field (1–4 columns), FF Container (6 variants, resizable), FRM 12-column grid, GF columns (up to 4), FMN up to 4 per row |
| Collapsible section / accordion | FF Accordion, Jotform Section Collapse, Fillout |
| Review / summary before submit | WPF Entry Preview (4 styles), FRM (Summary field), Jotform Preview Before Submit, MetForm Summary |
| Conversational (one question at a time) | GF add-on, WPF Pro, FF, FRM, NF add-on, Typeform, Jotform cards, Paperform |
| Multi-step progress styles | GF (bar / steps / none, page names, conditional next), WPF (bar / circles / connector, clickable navigation), FF (bar / steps / tabs top/left, animations, auto page change on last radio, per-step save), FRM (bar / rootline, clickable, conditional skip), NF (breadcrumbs), FMN (steps/bar, page visibility conditions) |
| Popup / modal | FF Pro shortcode modal, FRM Bootstrap modal |
| Landing page / standalone form page | FF Pro, WPF Pro Form Pages, FRM, NF public link, Tally/Typeform native |

## 6. Form-level behaviour

| Capability | Who has it |
|---|---|
| Entry limit (total) | GF, WPF Form Locker, FF (per day/week/month/year/per user), FRM, NF, FMN (expire after N), Paperform, Jotform |
| Schedule open / close (+ messages) | GF (pending + expired messages), WPF Form Locker, FF (dates + weekdays), FRM, FMN (expire on date), Microsoft Forms |
| Per user / IP / cookie / email limits | FRM (user, IP, cookie, email field; per period), FF (per user), WPF Form Locker (IP and/or email with windows), FMN (logged-in per user), Jotform (cookies / cookies+IP), Tally, Typeform, Google Forms |
| Require login | GF, WPF, FF, FMN, NF, Fillout |
| Password / age / email gate before form | WPF Form Locker, Tally (password), Fillout (passcode / email / SSO) |
| Save and resume (link + email) | GF (30-day link), WPF Pro (link/email, 30 days), FF Pro (button, link, email), FRM (logged-in drafts; logged-out via add-on), NF add-on (logged-in or local browser), FMN (retention days, email link), Jotform, Fillout |
| Silent local autosave | Typeform (local storage), FRM autosave on page turn |
| Partial entries / abandonment | GF add-on (Elite), WPF Pro (save only if email/phone present, abandoned notifications), FF Pro (step forms), Typeform submit points, Tally, Fillout, Paperform |
| Multiple confirmations + conditional | GF, WPF (paid), FF, FRM Pro, FMN |
| Redirect passing field data in query string | GF, FF, FMN (merge tags in URL), Typeform recall |
| Hide or reset form after submit | FF, NF, FMN |
| Double opt-in | FF |
| Admin approval workflow | FF (approve/decline links, auto-decline), Jotform Workflows |
| Deny empty submission | FF |
| Spam: honeypot | GF Advanced Honeypot, WPF modern anti-spam, FRM (default on), NF, FMN |
| Spam: minimum time to submit | GF Submission Speed Check, WPF (default 2s) |
| Spam: Akismet | WPF, FRM, NF, FMN |
| Spam: keyword / country / IP filters | WPF *(tier unverified)*, FF (IP, country, keywords), FRM (denylist, WP disallowed keys, StopForumSpam) |
| Captcha choice | WPF (reCAPTCHA v2/v3, hCaptcha, Turnstile, custom math/Q&A), FRM, NF, FMN (v2, v2 invisible, v3 with threshold, Enterprise, hCaptcha, Turnstile), GF (v2 + Turnstile add-on) |
| Answer piping (insert earlier answer into later label) | Typeform recall `@`, Tally, Fillout, Paperform, Jotform |
| Smart tags / merge tags in defaults | GF merge tags, WPF smart tags (`{query_var}`, `{user_meta}`, `{unique_value}`), FF smart codes (`{get.x}`, `{cookie.x}`, `{user.meta.x}`, `{embed_post.*}`, `{date.*}`, `{random_string}`), FRM shortcodes (`[get]`, `[date offset]`, `[user_meta]`, `[post_meta]`, `[ip]`, `[auto_id]`), NF merge tags (`{querystring}`, `{user_meta}`, `{post_meta}`, `{submission:sequence}`), FMN hidden presets, CF7 `default:get/user_*` |
| Hidden field presets | FMN (IP, date/time, embed post, user agent, referrer, user id/name/email/login, query param, custom), Tally (hidden fields passed to redirect), Typeform URL params |
| Per-form custom CSS (and JS) | WPF (CSS), FF (CSS + JS), FMN (CSS) |
| Style presets / styler | GF Orbital theme + block styles, WPF themes, FF Pro styler (6 presets, import), FMN presets with bulk apply |
| Form import / export (JSON) | GF, WPF (+ importers from CF7, Ninja, Pirate), FF (+ migrator), FRM (XML), FMN (+ CF7 importer) |
| Form revisions / history | WPF (restore), FF (edit history with restore), FMN (autosave, revert) |
| Accessibility checker / accessible defaults | GF 3.0 accessibility warnings in editor, Jotform checker, Typeform check |
| Multi-language forms | Tally (in progress), Typeform (AI translation), Jotform (form translation) |
| Quiz / scoring | GF add-on, FF Pro (points, grades, randomize), FMN (knowledge + personality), FRM, Typeform, Paperform, Google Forms |
| Polls with live results | WPF Pro, FMN (vote limits by IP/cookie, open/close), GF add-on |
| User registration / login / profile update | GF add-on, FF, FMN, NF add-on, FRM |
| Front-end post creation | GF (core post fields), FF, FMN (Post Data), NF add-on, FRM |

## 7. Privacy

| Capability | Who has it |
|---|---|
| Do not store IP / user agent | GF, WPF, FMN (IP retention) |
| Do not store entries at all | WPF, FF, FRM, FMN |
| Auto-delete after N days | GF (trash or delete), WPF, FF, FRM (GDPR tab), FMN |
| WordPress personal data export / erase | GF (identification field + per-field choices), NF (Export/Delete Data Request actions), FMN (account erasure handling) |
| Consent field storing consent text version | GF |

## 8. Entries

| Capability | Who has it |
|---|---|
| Column chooser | GF, WPF, FF, FRM (screen options), NF, FMN |
| Star / favourite | GF, WPF, FF |
| Read / unread | GF, WPF, FF |
| Spam and trash statuses with restore | GF, WPF, FF, FRM (spam reason) |
| Keyword search, date range, field filters | WPF (incl. notes/IP), FF (advanced filter with OR groups), FMN (date/ID ranges), NF, FRM |
| Notes on entry | GF (optionally emailed), WPF, FF, FRM |
| Edit entry (admin) | GF, WPF, FF, FRM, NF |
| Resend notifications | GF (bulk, to alternate address), WPF, FRM Pro, NF (bulk re-trigger), FMN |
| Print entry | GF, WPF, FF, FRM |
| Export formats | GF CSV; WPF CSV/XLSX; FF CSV/XLSX/ODS/JSON; FRM CSV + XML; NF CSV (+ add-ons); FMN CSV |
| Export field selection / date range / status filter | GF, WPF, FF, FMN |
| Scheduled exports | WPF Elite, NF add-on, FMN |
| Reports / charts | WPF (entries graph, survey charts), FF (visual reports), FRM (reports tab), FMN (views, conversion, location; scheduled email reports) |
| Front-end entry views / user dashboards | FRM Views, NF submissions table block, FF (front-end entry view link) |
| Visitor edit link | Jotform, Fillout, Google Forms, FRM (front-end editing own entries) |
| User Journey (pages visited before submit) | WPF Pro |

## 9. Builder UX

| Capability | Who has it |
|---|---|
| Undo / redo | WPF (Ctrl+Z / Shift+Z since 1.9.9) |
| Keyboard shortcuts | WPF (save, preview, embed, field search, help…), FF (`/` field search, tab navigation) |
| Field search in palette | GF, WPF, FF |
| Duplicate field | GF, WPF (D), FRM (field or group), NF, FMN |
| Multi-select fields in builder | FRM |
| Saved / favourite fields | NF Favorite Fields |
| Compact view | GF |
| AI form generation | WPF (Lite, rate-limited), FF, FRM, NF |
| Templates | WPF (~2,100 *(vendor figure)*), FMN 27 + cloud, GF library, FRM library with favourites |

## 10. User-demand signals (ranked synthesis)

1. Conditional logic free and reliable. 2. Repeater ("most requested": WPForms
2024, Fluent 2025). 3. Save and continue later ("top request": Forminator).
4. Reliable notifications with failure visibility. 5. Built-in spam protection.
6. Free entry management. 7. Multi-step with progress. 8. Unsaved-changes
warning (top-viewed GF request). 9. Inline validation (Baymard: 31% of sites
lack it). 10. Calculations. 11. Accessibility. 12. Multi-file upload.
13. Styling presets. 14. Lightweight front end. 15. Dynamic/pre-populated
fields. 16. Ranking field. 17. Edit submitted entry. 18. Templates.
19. Conversational layout. 20. Multi-language forms. 21. Address autocomplete.
22. Partial entries. 23. Custom validation messages. 24. Finer time inputs.
25. Rich content blocks. 26. Saved/scheduled exports. 27. Signature.
28. Front-end entry views. 29. Duplicate/unique submission control.
30. Features that work together (repeater + save + calc + logic).

Owner-reported request (EFB users): verify the mobile number with an SMS
one-time code before the form can be submitted — see
`../F02-verification-otp.fa.md`.

## Sources

Gravity Forms: docs.gravityforms.com (form-fields, common-field-settings,
input-mask, number, email, phone, name, address, date, time, drop-down,
checkboxes, multiple-choice-field, image-choice-field, file-upload, list,
consent, repeater, page-break, form-settings, configuring-confirmations,
notifications-settings-reference, save-continue, personal-data-settings,
entries, exporting-form-entries, form-editor, working-with-columns).
WPForms: wpforms.com/docs (field options, smart tags, input masks, limit
words/characters, email allowlist/denylist, date/time, multiple choice,
checkboxes, dropdown, file upload, layout, repeater, multi-page, entry
preview, rating, likert, signature, prevent spam, save and resume, form
abandonment, form locker, conversational forms, surveys and polls, user
journey, form revisions, import/export, entries, export, entry automation,
keyboard shortcuts, AI). Fluent Forms: docs.fluentforms.com (fields overview,
text, numeric, phone, date, dropdown, checkbox, upload, address, dynamic
field, smart codes, multi-step, restrictions, layout, confirmation,
notifications, entries, inventory, quiz, save progress, edit history,
shortcuts). Formidable: formidableforms.com/knowledgebase (field types/options,
dynamic defaults, text, number, phone, email, address, checkbox/radio,
dropdown, date, time, upload, repeater, lookup, settings, confirmation, email,
page breaks, spam, entries, export). Ninja Forms: ninjaforms.com/docs.
Forminator: wpmudev.com/docs/wpmu-dev-plugins/forminator. CF7: plugin source
on plugins.svn.wordpress.org. Jotform: jotform.com/help (fields, short text,
regex, date limits, upload limits, configurable list, barcode, QR, email
validator, unique submissions, save and continue, analytics,
accessible-forms). Typeform: typeform.com/feature-glossary, help.typeform.com.
Tally: tally.so/help. Fillout: fillout.com/help. Paperform: paperform.co/help.
Demand: wpforms.com 1.8.9 repeater announcement, fluentforms.com year in review
2024/2025, wpmudev.com Forminator blog, community.gravityforms.com feature
requests, baymard.com (inline validation, multi-column forms),
web.dev sign-up form best practices, oddjar.com 2026 comparison,
zapier.com best WordPress form plugin, theadminbar.com agency roundup.
