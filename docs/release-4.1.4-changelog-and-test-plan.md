# Easy Form Builder — 4.1.4 Change List & Test Plan
# لیست تغییرات و برنامه‌ی تست نسخه‌ی ۴.۱.۴

**Baseline (currently live on wordpress.org):** 4.1.3 — confirmed via the WordPress.org plugin API on 2026‑09‑03, and identical to the local `v4` branch tip (commit `5c3f2971`).
**Candidate:** this branch (`refactor/extract-form-builder-from-dev4`, commit `46192cf0` + uncommitted working‑tree changes), heading for **4.1.4**.

**نسخه‌ی مبنا (فعلاً روی wordpress.org منتشر شده):** ۴.۱.۳ — در تاریخ ۱۴۰۵/۰۶/۱۲ (۲۰۲۶‑۰۹‑۰۳) از طریق API خود وردپرس تأیید شد و دقیقاً با نوک شاخه‌ی محلی `v4` (کامیت `5c3f2971`) یکسان است.
**نسخه‌ی کاندید:** همین شاخه (`refactor/extract-form-builder-from-dev4`، کامیت `46192cf0` + تغییرات ذخیره‌نشده‌ی کاری)، برای انتشار به‌عنوان **۴.۱.۴**.

---

## Scope of this document / محدوده‌ی این سند

Comparing the full branch against `v4` shows **234 files / +184,920 / ‑255 lines** changed — but almost all of that is the in‑progress form‑builder redesign workspace (`_workspace/easy-form-builder-form-builder/`), new knowledge‑base articles (`docs/`), and automated test suites (`tests/`), none of which are part of the plugin's SVN/wordpress.org package. Restricting the diff to what actually ships (`emsfb.php`, `includes/`, `public/`, `languages/`, `vendor/`, `readme.txt`) leaves **12 files, +1,229 / ‑156 lines** — that is the real change set below, item by item, with concrete manual‑test steps. `languages/` and `vendor/` had zero changes.

مقایسه‌ی کل شاخه با `v4` عدد **۲۳۴ فایل / ۱۸۴٬۹۲۰+ / ۲۵۵‑ خط** را نشان می‌دهد — ولی تقریباً همه‌ی آن مربوط به فضای کاری بازطراحی فرم‌ساز (`_workspace/easy-form-builder-form-builder/`)، مقاله‌های جدید پایگاه‌دانش (`docs/`) و مجموعه‌تست‌های خودکار (`tests/`) است که هیچ‌کدام در بسته‌ی SVN/وردپرس.org پلاگین قرار نمی‌گیرند. با محدود کردن دیف فقط به چیزی که واقعاً منتشر می‌شود (`emsfb.php`، `includes/`، `public/`، `languages/`، `vendor/`، `readme.txt`) به **۱۲ فایل، ۱٬۲۲۹+ / ۱۵۶‑ خط** می‌رسیم — همان تغییرات واقعی‌ای که مورد‌به‌مورد در ادامه با مراحل تست دستی آمده. در `languages/` و `vendor/` هیچ تغییری نبود.

---

## ⚠️ Before you tag this release / قبل از تگ زدن این نسخه

- [ ] **`EMSFB_PLUGIN_VERSION` is still `"4.1.3"`** in [emsfb.php](../emsfb.php) (line 27), while the plugin header (line 6) and `readme.txt` already say 4.1.4. This constant drives the `?ver=` cache‑busting query string on every enqueued script/style. If it isn't bumped, browsers/CDNs that already cached the old `list_form-efb.js`, `val-efb.js`, `core-efb.js` may keep serving them, meaning end users might not actually receive the fixes in sections 1, 3 and 5 below. **Bump it to `4.1.4` before tagging.**
- [ ] **`EMSFB_MIRROR_SERVER_URL` points at `https://blog.whitestudio.team`, which does not resolve yet** (checked 2026‑09‑03: `getaddrinfo ENOTFOUND`). This is not a crash risk — the endpoint‑failover logic (section 2) just tries it, fails, and moves on — but the "independent mirror" feature provides zero real benefit until that site is actually deployed. Either deploy `blog.whitestudio.team` (the companion `payefb-blog-mirror` plugin already exists locally per `docs/addon-delivery-independent-mirror-implementation.md`) before/at release, or ship this version with the constant set to `""` and enable the mirror in a follow‑up release.
- [ ] `readme.txt`'s `= 4.1.4 =` changelog entry is currently just the placeholder **"Fixed issues"** — replace it with real changelog copy before publishing (this document is the source material).
- [ ] `emsfb.php` has a leftover duplicate line — a commented‑out `//define("EMSFB_MIRROR_SERVER_URL", ...)` sitting directly above the identical active `define(...)`. Harmless, but worth deleting for clarity.
- [ ] Confirm the WhiteStudio backend (`email-tester-service`) has the new `POST /handoff/{hash}` endpoint live in production — the client degrades safely if not (logs a 404 and continues with today's generic wording), but section 3 below only shows its full benefit once the service side is deployed too.

<br>

- [ ] **ثابت `EMSFB_PLUGIN_VERSION` هنوز `"4.1.3"` است** (در [emsfb.php](../emsfb.php)، خط ۲۷)، در حالی که هدر پلاگین (خط ۶) و `readme.txt` از قبل ۴.۱.۴ نوشته‌اند. همین ثابت است که رشته‌ی `?ver=` برای شکستن کش هر اسکریپت/استایل را می‌سازد. اگر آپدیت نشود، مرورگرها/CDN که نسخه‌ی قدیمی `list_form-efb.js`، `val-efb.js`، `core-efb.js` را کش کرده‌اند ممکن است همان‌ها را سرو کنند — یعنی کاربر نهایی اصلاحات بخش‌های ۱، ۳ و ۵ زیر را عملاً دریافت نکند. **قبل از تگ زدن، این ثابت را به `4.1.4` تغییر دهید.**
- [ ] **`EMSFB_MIRROR_SERVER_URL` به آدرس `https://blog.whitestudio.team` اشاره می‌کند که هنوز resolve نمی‌شود** (بررسی‌شده در ۱۴۰۵/۰۶/۱۲: خطای `getaddrinfo ENOTFOUND`). این خطرِ کرش ندارد — منطق فیل‌اُور بین endpointها (بخش ۲) فقط آن را امتحان می‌کند، شکست می‌خورد و به بعدی می‌رود — ولی تا وقتی آن سایت واقعاً deploy نشود، فیچر «میرور مستقل» هیچ فایده‌ی واقعی‌ای ندارد. یا `blog.whitestudio.team` را قبل/همزمان با انتشار بالا بیاورید (طبق `docs/addon-delivery-independent-mirror-implementation.md` پلاگین همراه آن یعنی `payefb-blog-mirror` از قبل به‌صورت محلی ساخته شده)، یا این نسخه را با مقدار خالی (`""`) برای این ثابت منتشر کنید و میرور را در نسخه‌ی بعدی فعال کنید.
- [ ] مدخل `= 4.1.4 =` در `readme.txt` فعلاً فقط placeholder **"Fixed issues"** است — قبل از انتشار با متن واقعی چنج‌لاگ (همین سند) جایگزین شود.
- [ ] در `emsfb.php` یک خط تکراری اضافه مانده — یک `//define("EMSFB_MIRROR_SERVER_URL", ...)` کامنت‌شده درست بالای همان `define(...)` فعال و یکسان. بی‌ضرر است ولی بهتر است برای خوانایی حذف شود.
- [ ] مطمئن شوید سرویس بک‌اند WhiteStudio (`email-tester-service`) اندپوینت جدید `POST /handoff/{hash}` را روی production دارد — اگر نداشته باشد کلاینت امن تخریب می‌شود (یک ۴۰۴ لاگ می‌شود و با پیام‌های قبلی ادامه می‌دهد)، ولی فایده‌ی کامل بخش ۳ زیر فقط وقتی است که سمت سرویس هم deploy شده باشد.

---

# Part 1 — English

## 1. Conditional Logic: notification, confirmation & webhook rules now evaluate correctly

**Files:** `includes/class-Emsfb-public.php`

Rules attached to email notifications, the confirmation message/redirect, and webhooks used to run through a separate, simpler evaluator than field‑level rules (show/hide, etc.). That evaluator compared a select/multiselect condition's stored **option id** directly against the row's value, which is sometimes the id and sometimes the visible **label** — so the condition silently never matched, and the notification/confirmation/webhook silently never fired. It's now delegated to the same `Emsfb_Logic_Validator` the field‑level rules use whenever the Conditional Logic add‑on is active; a local fallback (used only when that add‑on is inactive) got the same id/label resolution fix.

Two more fixes in the same area: `amount_gt` / `amount_lt` / `amount_eq` conditions on a payment field used to compare against the field's **position in the form**, not the amount actually charged. And `is_paid` / `is_not_paid` used to just check whether the field had *any* non‑empty value, not whether a payment genuinely went through.

- [ ] With the **Conditional Logic add‑on active**, build a form with a select or multiselect field. Add a conditional **notification** rule: *IF field IS [specific option] THEN send email to X*. Submit choosing that option → email arrives. Submit choosing a different option → email does **not** arrive.
- [ ] Repeat for a conditional **confirmation message/redirect** rule and for a **webhook** rule (e.g. point it at webhook.site) — same option‑match / no‑match behavior.
- [ ] Repeat both checks with the Conditional Logic add‑on **deactivated** (exercises the fallback path) — behavior should be identical.
- [ ] Add a payment field (Stripe/PayPal/PersiaPay). Add a rule using `amount_gt`/`amount_lt`/`amount_eq` against it. Complete a real or sandbox payment and confirm the rule fires based on the **actual charged amount**, not the field's position on the form.
- [ ] Add a rule using `is_paid` / `is_not_paid` on the same field — confirm it reflects whether the payment actually succeeded, not just whether the field carried a price.
- [ ] Regression: spot‑check an untouched condition type (text `contains`, number `gt`/`lt`, `is_empty`) still works.

## 2. Add‑on delivery: independent mirror, failover, and a security fix

**Files:** `emsfb.php`, `includes/functions.php`, `includes/admin/class-Emsfb-admin.php`, `includes/admin/class-Emsfb-addon.php`

`whitestudio.team` (the add‑on API/download host) has repeatedly been hit by a host‑level, IP‑reputation‑based 403 block that has nothing to do with the plugin's code but leaves paying customers unable to install add‑ons they already own. This release adds a second, independent origin (`EMSFB_MIRROR_SERVER_URL`, see the blocker above) and routes **every** add‑on call site — the Add‑ons page catalogue script, the interactive install handler, and the background recovery job — through one shared helper (`addon_api_domains_efb()`), so they can no longer disagree about which endpoint is live. A dead endpoint is remembered for an hour and skipped on the next attempt; the primary (`whitestudio.team`) is never permanently skipped.

**Security fix:** the add‑on archive download URL returned by the API is now validated against a host allow‑list for **every** locale. Previously that check only ran for `fa_IR` sites; with more than one trusted endpoint now in play, an unvalidated `link` from any of them would have been an open door to downloading and extracting an arbitrary archive.

**Correctness fix:** a licensed Pro (or Free Plus) customer on a Persian‑locale site — where licenses are validated offline by design — could be shown the "upgrade to Pro" upsell modal when the remote catalogue's own entitlement check disagreed with the local license. The install handler now trusts the local license first and only shows the upsell when the site genuinely isn't entitled; otherwise it treats the mismatch as an endpoint problem and tries the next one. A stale "please update the plugin" response, or a `plan_required` response, from one endpoint no longer ends the install outright — other endpoints get a turn first.

Also in this batch: a `version_compare()` PHP 8 warning on a payload missing its version field is fixed; every `/register-costumer` link (renewal/upgrade prompts) now points at `/checkout`; and the install‑debug logging (`addon_install_log_efb`, `emsfb_pro_log`) now actually writes to `debug.log` when enabled (it silently did nothing before), while staying off by default so it never runs on a normal customer click.

- [ ] Confirm the mirror blocker above is resolved (or intentionally deferred) before testing the rest of this section.
- [ ] On a non‑Persian site, install a free add‑on you don't already have — confirm it installs normally.
- [ ] Switch site language to Persian (`fa_IR`) — confirm the Add‑ons page catalogue still loads and an add‑on installs successfully (endpoint order: whitestudio.team → mirror → easyformbuilder.ir).
- [ ] With a valid, licensed Pro/Free Plus activation code on a Persian site, install a Pro‑only add‑on — confirm the "upgrade" modal does **not** appear.
- [ ] Simulate the primary endpoint being unreachable (e.g. block `whitestudio.team` in a hosts file on a test machine) — confirm install falls back to the next endpoint instead of failing outright.
- [ ] Click every "renew subscription" / expired‑license link you can find (Add‑ons page, dashboard notice, weekly email) — confirm each points at `/checkout`, not `/register-costumer`.
- [ ] With `WP_DEBUG` on, install an add‑on and confirm `[EFB Addon Install]` / `[EFB Pro ...]` lines appear in `debug.log`; with it off, confirm nothing is written.
- [ ] Run `tests/test-addon-mirror-endpoints.php` (13 assertions) and confirm it passes — this is the automated coverage for the allow‑list/endpoint‑ordering logic that isn't practically reachable from the UI.
- [ ] Also re‑run `test-addon-install-plan-gate.php` and `test-addon-gating-wordpress.php` as a regression check on unrelated add‑on gating logic.

## 3. Email deliverability test: WordPress can now say *where* the message got lost

**Files:** `includes/class-Emsfb-email-monitor.php`, `includes/admin/class-Emsfb-admin.php`, `includes/functions.php`, `includes/admin/assets/js/list_form-efb.js`, `includes/admin/assets/js/val-efb.js`

Previously, "no email arrived" always showed the same banner — *"Email Delivery Is Not Working"* — whether WordPress had failed to send anything at all, or had sent successfully and the message was lost somewhere downstream. The two need completely different fixes, and the old wording (plus a green checkmark on "email sent" sitting right above the red banner) read as a contradiction. The plugin now hooks `wp_mail_failed` and `phpmailer_init` around every test send, reports the outcome to the WhiteStudio tester service via a new `/handoff` endpoint, and uses the resulting `send_stage` (`wp_mail_failed` / `handed_off` / `unknown`) to show one of three distinct, stage‑specific messages everywhere: the test modal, the wp‑admin dashboard notice, the setup‑wizard live test, and the weekly report email.

A message that *arrives* but scores below the "healthy" threshold is now its own **amber "likely going to spam"** verdict, separate from total failure (previously conflated). The JS fallback score threshold was also corrected from 40 to 20 to match the server's actual default.

- [ ] Run the one‑click email test on a site with working SMTP → healthy (green) result end‑to‑end; step 2 now reads "WordPress Sends the Email".
- [ ] Force `wp_mail()` to fail (e.g. a bad SMTP host in your SMTP plugin) → confirm the message is **"WordPress could not send the email"**, not the old generic wording, in both the modal and the dashboard notice.
- [ ] Force a "sent but never arrives" scenario → confirm the message is **"WordPress sent the email, it just never arrived"**, not something implying WordPress itself is broken.
- [ ] Force a low‑but‑nonzero score (e.g. missing SPF/DKIM) → confirm an **amber**, not red, "arrives but lands in spam" result with its own message.
- [ ] Repeat the "send fails" and "sent but not arrived" cases inside the first‑run setup wizard's email test.
- [ ] Confirm only **one** "a full report will be emailed to you" box appears after a successful test (a duplicate used to appear).

## 4. Outgoing form emails: RTL and Outlook/mobile rendering fixes

**File:** `includes/class-email-handler.php`

All three HTML email builders now emit `dir="…"` on both `<html>` and `<body>` — not just the inline `direction:` CSS — so right‑to‑left templates render correctly in clients that ignore inline direction but honor the HTML attribute. The content and footer blocks are also now wrapped in MSO conditional comments plus a `max-width` div instead of a fixed‑width table, for better Outlook desktop and mobile rendering.

- [ ] Set a form's email template to RTL (Persian/Arabic), send a test submission, and check the received email in Outlook desktop — layout should be right‑to‑left, not mirrored or broken.
- [ ] Check the same RTL email in Gmail web, Gmail mobile, and Apple Mail for consistent rendering.
- [ ] Check an existing LTR (English) template in Outlook desktop — confirm header gradient, footer, and content width still look correct (no regression from the MSO wrapper change).
- [ ] Check either template in a narrow mobile inbox preview — no horizontal scrolling or clipped content.

## 5. Public form JS: crash guards for missing field values

**File:** `public/assets/js/core-efb.js`

Small defensive fixes so a field lookup that comes up empty (a field id not yet present in the submitted‑values array — e.g. one only rendered conditionally on a later step) no longer throws a JS error and silently breaks the rest of the form's client‑side behavior. Covers the generic change‑event dispatch, `file` upload fields, and `esign` (signature) fields.

- [ ] Build a multi‑step form with conditional logic that hides fields on later steps, including a `file` field and an `esign` field. With browser dev tools open, navigate through the steps and confirm no console errors appear.
- [ ] Specifically trigger navigation before a conditionally‑shown field has been rendered yet, and confirm the form still progresses normally.

## 6. Admin UI: map field button icons

**File:** `includes/admin/assets/js/pro_els-efb.js`

The Map field's "Search" and "Delete markers" buttons previously showed their icon only on mobile widths (`d-block d-md-none`); the icon now shows on desktop too, alongside the existing desktop‑only label text.

- [ ] Add a Map field in the builder. At desktop width, confirm both buttons show their icon plus label. At mobile width, confirm icon‑only, unchanged.

## 7. Version metadata

- [ ] `readme.txt` `Stable tag` and `emsfb.php` header bumped 4.1.3 → 4.1.4 (already done in the working tree) — pending the `EMSFB_PLUGIN_VERSION` constant fix and the real changelog text called out at the top of this document.

---

# بخش ۲ — فارسی

## ۱. منطق شرطی: قوانین اعلان، پیام تأیید و وب‌هوک حالا درست ارزیابی می‌شوند

**فایل‌ها:** `includes/class-Emsfb-public.php`

قوانینی که به ایمیل اعلان، پیام تأیید/ریدایرکت، و وب‌هوک وصل بودند، تا الان از یک ارزیاب جدا و ساده‌تر نسبت به قوانین سطح فیلد (نمایش/مخفی و…) عبور می‌کردند. آن ارزیاب، **id گزینه**‌ی ذخیره‌شده در شرط select/multiselect را مستقیماً با مقدار ردیف مقایسه می‌کرد — درحالی‌که مقدار ردیف گاهی همان id است و گاهی **متن نمایشی** گزینه؛ در نتیجه شرط بی‌سروصدا هیچ‌وقت match نمی‌شد و اعلان/پیام تأیید/وب‌هوک بی‌سروصدا هیچ‌وقت اجرا نمی‌شد. حالا وقتی افزونه‌ی Conditional Logic فعال باشد، همین قوانین به همان `Emsfb_Logic_Validator`ی که قوانین سطح فیلد استفاده می‌کنند واگذار می‌شوند؛ یک fallback محلی هم (فقط وقتی آن افزونه غیرفعال است) همین رفع اشکال تطبیق id/متن را گرفته.

دو رفع‌اشکال دیگر در همین بخش: شرط‌های `amount_gt` / `amount_lt` / `amount_eq` روی فیلد پرداخت، تا الان با **موقعیت فیلد در فرم** مقایسه می‌شدند، نه مبلغ واقعاً پرداخت‌شده. و `is_paid` / `is_not_paid` فقط چک می‌کردند فیلد هر مقدار غیرخالی‌ای دارد یا نه، نه اینکه پرداخت واقعاً موفق بوده یا نه.

- [ ] با **افزونه‌ی Conditional Logic فعال**، فرمی با فیلد select یا multiselect بسازید. یک قانون **اعلان** شرطی اضافه کنید: *اگر فیلد برابر [گزینه‌ی مشخص] بود، ایمیل به X ارسال شود*. با انتخاب همان گزینه ارسال کنید ← ایمیل برسد. با گزینه‌ی دیگری ارسال کنید ← ایمیل **نرسد**.
- [ ] همین را برای یک قانون **پیام تأیید/ریدایرکت** شرطی و یک قانون **وب‌هوک** (مثلاً به webhook.site) تکرار کنید — رفتار match/no‑match باید یکسان باشد.
- [ ] هر دو بررسی بالا را با افزونه‌ی Conditional Logic **غیرفعال** هم تکرار کنید (مسیر fallback را تست می‌کند) — رفتار باید یکسان باشد.
- [ ] یک فیلد پرداخت (Stripe/PayPal/PersiaPay) اضافه کنید. قانونی با `amount_gt`/`amount_lt`/`amount_eq` روی آن بسازید. یک پرداخت واقعی یا sandbox انجام دهید و مطمئن شوید قانون بر اساس **مبلغ واقعاً پرداخت‌شده** اجرا می‌شود، نه موقعیت فیلد در فرم.
- [ ] قانونی با `is_paid` / `is_not_paid` روی همان فیلد بسازید — مطمئن شوید نتیجه بازتاب‌دهنده‌ی موفقیت واقعی پرداخت است، نه صرفاً داشتن قیمت روی فیلد.
- [ ] رگرسیون: یک نوع شرط دست‌نخورده (متن `contains`، عدد `gt`/`lt`، `is_empty`) را هم سریع چک کنید که هنوز درست کار می‌کند.

## ۲. تحویل افزودنی‌ها: میرور مستقل، فیل‌اُور، و یک رفع‌اشکال امنیتی

**فایل‌ها:** `emsfb.php`، `includes/functions.php`، `includes/admin/class-Emsfb-admin.php`، `includes/admin/class-Emsfb-addon.php`

`whitestudio.team` (هاست API/دانلود افزودنی‌ها) بارها هدف یک بلاک ۴۰۳ سطح هاست بر اساس شهرت IP قرار گرفته که هیچ ربطی به کد پلاگین ندارد ولی باعث می‌شود مشتری‌های پولی نتوانند افزودنی‌هایی را که از قبل خریده‌اند نصب کنند. این نسخه یک مبدأ دوم و مستقل اضافه می‌کند (`EMSFB_MIRROR_SERVER_URL`، به هشدار بالا نگاه کنید) و **هر سه** نقطه‌ی فراخوانی افزودنی — اسکریپت کاتالوگ صفحه‌ی Add‑ons، هندلر نصب تعاملی، و job پس‌زمینه‌ی recovery — را از یک هلپر مشترک (`addon_api_domains_efb()`) عبور می‌دهد تا دیگر با هم درباره‌ی اینکه کدام endpoint زنده است اختلاف نداشته باشند. یک endpoint مرده به مدت یک ساعت به‌خاطر سپرده و در تلاش بعدی رد می‌شود؛ endpoint اصلی (`whitestudio.team`) هرگز به‌طور دائم کنار گذاشته نمی‌شود.

**رفع‌اشکال امنیتی:** آدرس دانلود آرشیو افزودنی که از API برمی‌گردد، حالا برای **همه‌ی** locale ها در برابر یک allow‑list از هاست‌ها اعتبارسنجی می‌شود. قبلاً این چک فقط برای سایت‌های `fa_IR` اجرا می‌شد؛ با وجود بیش از یک endpoint قابل‌اعتماد، یک `link` اعتبارسنجی‌نشده از هرکدام از آن‌ها می‌توانست دری باز برای دانلود و استخراج یک آرشیو دلخواه باشد.

**رفع‌اشکال منطقی:** یک مشتری Pro (یا Free Plus) دارای لایسنس معتبر روی یک سایت با locale فارسی — جایی که لایسنس‌ها عمداً به‌صورت آفلاین اعتبارسنجی می‌شوند — ممکن بود مودال «ارتقا به Pro» را ببیند، چون چک entitlement سمت کاتالوگ ریموت با لایسنس محلی اختلاف داشت. حالا هندلر نصب اول به لایسنس محلی اعتماد می‌کند و مودال ارتقا را فقط وقتی نشان می‌دهد که سایت واقعاً استحقاق نداشته باشد؛ در غیر این صورت اختلاف را یک مشکل endpoint در نظر می‌گیرد و endpoint بعدی را امتحان می‌کند. یک پاسخ کهنه‌ی «پلاگین را آپدیت کنید» یا `plan_required` از یک endpoint دیگر نصب را فوراً متوقف نمی‌کند — endpointهای دیگر هم نوبت می‌گیرند.

در همین دسته: یک هشدار PHP 8 در `version_compare()` روی پاسخی که فیلد نسخه ندارد رفع شده؛ همه‌ی لینک‌های `/register-costumer` (پیام‌های تمدید/ارتقا) حالا به `/checkout` اشاره می‌کنند؛ و لاگ دیباگ نصب (`addon_install_log_efb`، `emsfb_pro_log`) وقتی فعال باشد واقعاً روی `debug.log` می‌نویسد (قبلاً حتی وقتی «فعال» بود عملاً هیچ‌کاری نمی‌کرد)، ولی به‌طور پیش‌فرض خاموش می‌ماند تا هیچ‌وقت روی کلیک یک مشتری عادی اجرا نشود.

- [ ] قبل از تست بقیه‌ی این بخش، مطمئن شوید مسئله‌ی میرور در بالای سند حل شده (یا عمداً به تعویق افتاده).
- [ ] روی یک سایت غیرفارسی، یک افزودنی رایگان که هنوز ندارید نصب کنید — نصب عادی باید انجام شود.
- [ ] زبان سایت را فارسی (`fa_IR`) کنید — مطمئن شوید کاتالوگ صفحه‌ی Add‑ons هنوز لود می‌شود و یک افزودنی با موفقیت نصب می‌شود (ترتیب endpoint: whitestudio.team ← میرور ← easyformbuilder.ir).
- [ ] با یک کد فعال‌سازی معتبر Pro/Free Plus روی سایت فارسی، یک افزودنی مخصوص Pro نصب کنید — مطمئن شوید مودال «ارتقا» ظاهر **نمی‌شود**.
- [ ] در‌دسترس‌نبودن endpoint اصلی را شبیه‌سازی کنید (مثلاً بلاک کردن `whitestudio.team` در فایل hosts یک ماشین تست) — نصب باید به endpoint بعدی سوییچ کند، نه اینکه کامل شکست بخورد.
- [ ] هر لینک «تمدید اشتراک»/لایسنس‌منقضی‌شده که پیدا می‌کنید (صفحه‌ی Add‑ons، اعلان داشبورد، ایمیل هفتگی) را کلیک کنید — مطمئن شوید همه به `/checkout` می‌روند، نه `/register-costumer`.
- [ ] با `WP_DEBUG` روشن، یک افزودنی نصب کنید و مطمئن شوید خطوط `[EFB Addon Install]` / `[EFB Pro ...]` در `debug.log` ظاهر می‌شوند؛ با آن خاموش، مطمئن شوید چیزی نوشته نمی‌شود.
- [ ] `tests/test-addon-mirror-endpoints.php` (۱۳ assertion) را اجرا کنید و مطمئن شوید پاس می‌شود — این پوشش خودکار برای منطق allow‑list/ترتیب endpoint است که عملاً از UI قابل‌دسترس نیست.
- [ ] به‌عنوان چک رگرسیون روی منطق gating افزودنی‌های نامرتبط، `test-addon-install-plan-gate.php` و `test-addon-gating-wordpress.php` را هم دوباره اجرا کنید.

## ۳. تست قابلیت تحویل ایمیل: حالا وردپرس می‌گوید پیام *کجا* گم شده

**فایل‌ها:** `includes/class-Emsfb-email-monitor.php`، `includes/admin/class-Emsfb-admin.php`، `includes/functions.php`، `includes/admin/assets/js/list_form-efb.js`، `includes/admin/assets/js/val-efb.js`

قبلاً «هیچ ایمیلی نرسید» همیشه همان بنر را نشان می‌داد — *«Email Delivery Is Not Working»* — چه وردپرس اصلاً چیزی نتوانسته بود بفرستد، چه با موفقیت فرستاده بود و پیام یک‌جایی در مسیر بعدی گم شده بود. این دو حالت راه‌حل کاملاً متفاوتی دارند، و متن قدیمی (به‌همراه یک تیک سبز روی «ایمیل ارسال شد» درست بالای بنر قرمز) مثل یک تناقض خوانده می‌شد. پلاگین حالا `wp_mail_failed` و `phpmailer_init` را دور هر ارسال تستی هوک می‌کند، نتیجه را از طریق یک اندپوینت جدید `/handoff` به سرویس تستر WhiteStudio گزارش می‌دهد، و از `send_stage` حاصل (`wp_mail_failed` / `handed_off` / `unknown`) برای نمایش یکی از سه پیام مجزا و مختص‌همان‌مرحله در همه‌جا استفاده می‌کند: مودال تست، اعلان داشبورد wp‑admin، تست زنده‌ی ویزارد راه‌اندازی، و ایمیل گزارش هفتگی.

پیامی که *می‌رسد* ولی امتیازش زیر آستانه‌ی «سالم» است، حالا نتیجه‌ی مجزای خودش را دارد — یک وردیکت **کهربایی «احتمالاً می‌رود اسپم»** — جدا از شکست کامل (قبلاً این دو با هم قاطی می‌شدند). آستانه‌ی fallback سمت جاوااسکریپت هم از ۴۰ به ۲۰ اصلاح شد تا با مقدار پیش‌فرض واقعی سرور یکی باشد.

- [ ] تست یک‌کلیکی ایمیل را روی سایتی با SMTP سالم اجرا کنید ← نتیجه‌ی سالم (سبز) سرتاسر؛ مرحله‌ی ۲ حالا «WordPress Sends the Email» نوشته شده.
- [ ] `wp_mail()` را مجبور به شکست کنید (مثلاً یک هاست SMTP غلط در افزونه‌ی SMTP) ← مطمئن شوید پیام **«WordPress could not send the email»** است، نه متن کلی قدیمی، هم در مودال هم در اعلان داشبورد.
- [ ] سناریوی «فرستاده شد ولی هرگز نرسید» را شبیه‌سازی کنید ← مطمئن شوید پیام **«WordPress sent the email, it just never arrived»** است، نه چیزی که نشان دهد خود وردپرس خراب است.
- [ ] یک امتیاز پایین ولی غیرصفر بسازید (مثلاً SPF/DKIM ناقص) ← مطمئن شوید نتیجه **کهربایی**، نه قرمز، با متن مخصوص «می‌رسد ولی اسپم می‌شود» است.
- [ ] سناریوهای «ارسال شکست خورد» و «فرستاده شد ولی نرسید» را داخل تست ایمیل ویزارد راه‌اندازی اولیه هم تکرار کنید.
- [ ] مطمئن شوید بعد از یک تست موفق فقط **یک** باکس «گزارش کامل برایتان ایمیل می‌شود» نمایش داده می‌شود (قبلاً یک نسخه‌ی تکراری هم ظاهر می‌شد).

## ۴. ایمیل‌های خروجی فرم: رفع اشکال رندر RTL و Outlook/موبایل

**فایل:** `includes/class-email-handler.php`

هر سه سازنده‌ی ایمیل HTML حالا روی `<html>` و `<body>` هر دو `dir="…"` می‌گذارند — نه فقط CSS این‌لاین `direction:` — تا قالب‌های راست‌به‌چپ در کلاینت‌هایی که direction این‌لاین را نادیده می‌گیرند ولی به attribute خود HTML احترام می‌گذارند، درست رندر شوند. بلوک‌های محتوا و فوتر هم حالا داخل کامنت‌های شرطی MSO به‌همراه یک div با `max-width` قرار گرفته‌اند به‌جای یک جدول با عرض ثابت، برای رندر بهتر در Outlook دسکتاپ و موبایل.

- [ ] قالب ایمیل یک فرم را روی RTL (فارسی/عربی) بگذارید، یک ارسال تستی بفرستید، و ایمیل دریافتی را در Outlook دسکتاپ چک کنید — چیدمان باید راست‌به‌چپ باشد، نه آینه‌ای یا خراب.
- [ ] همان ایمیل RTL را در Gmail وب، Gmail موبایل، و Apple Mail چک کنید تا رندر یکسان باشد.
- [ ] یک قالب LTR (انگلیسی) موجود را در Outlook دسکتاپ چک کنید — مطمئن شوید گرادیان هدر، فوتر، و عرض محتوا هنوز درست است (بدون رگرسیون از تغییر پوشش MSO).
- [ ] هرکدام از قالب‌ها را در یک پیش‌نمایش inbox موبایل با عرض کم چک کنید — نباید اسکرول افقی یا محتوای بریده‌شده ببینید.

## ۵. جاوااسکریپت فرم عمومی: گارد در برابر کرش روی مقدار فیلد گم‌شده

**فایل:** `public/assets/js/core-efb.js`

چند رفع‌اشکال دفاعی کوچک تا وقتی جست‌وجوی یک فیلد نتیجه‌ی خالی می‌دهد (یک id فیلد که هنوز در آرایه‌ی مقادیر ارسالی نیست — مثلاً فیلدی که فقط به‌صورت شرطی در یک مرحله‌ی بعدی رندر می‌شود)، دیگر خطای جاوااسکریپت پرتاب نکند و بقیه‌ی رفتار سمت کلاینت فرم را بی‌صدا خراب نکند. شامل dispatch عمومی رویداد تغییر، فیلدهای آپلود `file`، و فیلدهای امضا `esign`.

- [ ] یک فرم چندمرحله‌ای با منطق شرطی بسازید که فیلدها را در مراحل بعدی مخفی می‌کند، شامل یک فیلد `file` و یک فیلد `esign`. با باز بودن dev tools مرورگر، بین مراحل حرکت کنید و مطمئن شوید هیچ خطایی در کنسول ظاهر نمی‌شود.
- [ ] مشخصاً پیش از رندر شدن یک فیلد شرطی، حرکت بین مراحل را امتحان کنید و مطمئن شوید فرم عادی پیش می‌رود.

## ۶. رابط کاربری ادمین: آیکون دکمه‌های فیلد نقشه

**فایل:** `includes/admin/assets/js/pro_els-efb.js`

دکمه‌های «جست‌وجو» و «حذف نشانگرها»ی فیلد نقشه قبلاً آیکونشان را فقط در عرض موبایل نشان می‌دادند (`d-block d-md-none`)؛ حالا آیکون در دسکتاپ هم نمایش داده می‌شود، در کنار متن برچسبی که قبلاً هم فقط در دسکتاپ بود.

- [ ] یک فیلد نقشه در فرم‌ساز اضافه کنید. در عرض دسکتاپ، مطمئن شوید هر دو دکمه هم آیکون هم برچسب دارند. در عرض موبایل، مطمئن شوید فقط آیکون است، بدون تغییر.

## ۷. متادیتای نسخه

- [ ] `Stable tag` در `readme.txt` و هدر `emsfb.php` از ۴.۱.۳ به ۴.۱.۴ تغییر کرده (در working tree انجام شده) — منتظر رفع ثابت `EMSFB_PLUGIN_VERSION` و متن واقعی چنج‌لاگ که در بالای همین سند اشاره شد.

---

## Appendix: intentionally left out of this list / پیوست: عمداً از این لیست کنار گذاشته شد

- **`_workspace/easy-form-builder-form-builder/`** — the in‑progress form‑builder redesign/extraction workspace (docs, a legacy snapshot, a new WordPress adapter, dist bundles, its own test suites). Not part of the plugin package; tracked separately in `REDESIGN_HANDOFF.md` and `MIGRATION_AND_ROLLBACK.md` inside that folder.
- **`docs/`** (this file's own folder) — knowledge‑base articles, licensing/survey‑form/email‑template help content, and design docs. Repo‑only, not shipped.
- **`tests/`** — PHP/JS automated test suites and fixtures. Not shipped; useful as regression coverage (referenced above where directly relevant).

<br>

- **`_workspace/easy-form-builder-form-builder/`** — فضای کاری در‌حال‌انجام بازطراحی/استخراج فرم‌ساز (اسناد، یک snapshot از نسخه‌ی قدیمی، یک adapter جدید وردپرس، باندل‌های dist، مجموعه‌تست‌های خودش). بخشی از بسته‌ی پلاگین نیست؛ جداگانه در `REDESIGN_HANDOFF.md` و `MIGRATION_AND_ROLLBACK.md` همان پوشه پیگیری می‌شود.
- **`docs/`** (همین پوشه‌ای که این فایل در آن است) — مقاله‌های پایگاه‌دانش، محتوای راهنمای لایسنس/فرم‌نظرسنجی/قالب‌ایمیل، و اسناد طراحی. فقط داخل ریپو، منتشر نمی‌شود.
- **`tests/`** — مجموعه‌تست‌های خودکار PHP/جاوااسکریپت و fixtureها. منتشر نمی‌شود؛ به‌عنوان پوشش رگرسیون مفید است (در بالا هرجا مستقیماً مرتبط بود ارجاع داده شد).
