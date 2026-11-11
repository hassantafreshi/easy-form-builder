# Brief for writing Easy Form Builder feature-gap task specs

Repo: /home/user/easy-form-builder (branch dev4). Do NOT change code and do NOT commit (the parent session commits). Write each spec file as soon as it is finished (one file at a time) so work survives an interruption.

Read first:
- docs/feature-gaps/README.fa.md — the queue. Your task's scope is its row. In the Phase-2 table, every item whose "ترکیب با" column names your task must appear in your spec as an optional-extension section.
- docs/feature-gaps/IMPLEMENTATION-PLAYBOOK.en.md — exact recipes and pitfalls. Follow them and cite them by section.
- docs/feature-gaps/reference/current-capabilities.en.md — what already exists; never re-spec it.
- docs/feature-gaps/reference/competitor-matrix.en.md — market behaviour.
- docs/feature-gaps/F02-verification-otp.fa.md and docs/feature-gaps/F06-advanced-calendar.fa.md — the quality and format bar (skim structure).
- For masks/validation: docs/feature-gaps/reference/masks-and-validators.en.md, masks-catalog.json, masks-ref/ (verified JS+PHP reference code).
Verify every code reference you cite by reading the code (line numbers from dev4 today).

Critical findings to respect (from the playbook):
- `sanitize_obj_msg_efb()` (includes/functions.php:3977) flattens unknown keys with sanitize_text_field: arrays/objects become "", tags/%XX/newlines stripped, booleans become "1"/"". Every new key needs an explicit sanitizer case or safe encoding. The builder also refuses `"`, `<`, `>` in settings inputs.
- `filter_attributes_by_type_efb()` (includes/class-Emsfb-public.php:9085) drops unknown field types on submit without error.
- The whole form JSON reaches visitors (only "email" and rePage blanked, ~1391/1400): never put secrets in form JSON.
- Server validation for text/tel/password/textarea/address line/postal code is a length-only `default:` branch.
- Four renderers must stay in sync: PHP Formbuilder (mind `$single_close_efb` close-tag counts and wpautop block-tag rules), JS addNewElement (builder canvas + admin preview), pro_els-efb.js helpers, CF7 importer mapper.
- Two logic evaluators (vendor/logic add-on is git-ignored and absent in this checkout; public evaluator delegates to it with fallback).
- Caches: saving refreshes only the ['form_structer','form_type'] cache key; ['form_structer'] can stay stale up to an hour.
- Access rules must be enforced at render (~1439), session prepare (~230) and submit (~2509), like `stateForm`; widget forms can be page-cached.
- Tables: emsfb_msg_, emsfb_rsp_, emsfb_stts_; DB version 1.3 option `Emsfb_db_version`; upgrade_schema() early-return pitfall. Entry `read_` codes: 0 new, 1 read, 2 pending payment, 3 new reply, 4 closed. Core phrases live in text_efb() in includes/functions.php; phrases.php holds add-on phrases.
- URL prefill `fun_gets_url_efb` (iefb/sefb/defb/hefb/vefb) is dead on the current render path and vefb writes to the wrong index.
- The builder redesign module in _workspace/easy-form-builder-form-builder/docs (STATE_SCHEMA.md, REDESIGN_HANDOFF.md) defines the future state schema; new keys must fit it.

Every spec (Persian prose, English identifiers and code) contains these sections:
هدف؛ وضعیت فعلی با شاهد (file:line)؛ رقبا (short table)؛ مشخصات رفتار و تجربهٔ کاربری (RTL، موبایل، ارقام فارسی و عربی، دسترس‌پذیری)؛ کلیدهای داده یا جداول (name, type, default, where stored, sanitizer case; DDL + migration if tables)؛ تغییرات سازنده یا پیشخوان (val-efb.js controls and handlers)؛ رندر (all renderers)؛ مسیرهای REST/AJAX در صورت نیاز (permission checks)؛ اعتبارسنجی مرورگر و سرور (exact functions to extend)؛ نمایش در پاسخ‌ها، CSV، PDF و ایمیل؛ منطق شرطی؛ کش و امنیت؛ سازگاری با فرم‌ها و داده‌های قدیمی؛ سطح نسخهٔ پیشنهادی (رایگان / Free Plus / Pro)؛ رشته‌های ترجمه (key + English text; other locales via the pipeline in docs/tasks/CLAUDE-HANDOFF.fa.md)؛ زیرتسک‌های A، B، C… (each: files, acceptance criteria, tests in repo conventions — standalone `php tests/…` and `node tests/…` scripts, Playwright for browser)؛ گسترش‌های فاز ۲ مرتبط؛ پرسش‌های باز.
Start the file with a one-line metadata header: `وضعیت: سند آماده، پیاده‌سازی نشده · اولویت: … · تلاش: … · وابستگی: …` and end each sub-task with an empty "Checkpoint" block (فایل‌های تغییرکرده / تست‌ها / وضعیت ترجمه / مورد باز / اقدام بعدی) for the implementer to fill in.
Target 250–550 lines per file. Return a short summary per file and any bugs found in passing (with file:line).
