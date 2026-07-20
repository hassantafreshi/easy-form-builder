# دستورالعمل تست دستی — Inspector، Calculations، Conflict Warnings، Release و قابلیت‌های PRD (فازهای 7 تا 10 + Gap Analysis)

> [فهرست مستندات](README.md) · [تست پذیرش جامع (فازهای 0-6)](EFB-Conditional-Logic-E2E-TEST-FA.md) · [Roadmap محصول](EFB-Conditional-Logic-Product-ROADMAP.md) · [تحلیل شکاف PRD](EFB-4x-Conditional-Logic-PRD-GAP-ANALYSIS.fa.md)

> **تاریخ:** 2026-07-07 · **شاخه:** `dev4`
> این سند مکمل [تست پذیرش جامع](EFB-Conditional-Logic-E2E-TEST-FA.md) است و قابلیت‌های فازهای 7 تا 10 به‌علاوه موارد پیاده‌سازی‌شده از [تحلیل شکاف PRD](EFB-4x-Conditional-Logic-PRD-GAP-ANALYSIS.fa.md) را پوشش می‌دهد:
> Debugger/Inspector، اکشن Calculate، هشدارهای Conflict، Plan Gating، منابع شرط جدید (URL param / کاربر / step جاری)، عملگرهای تاریخ، اکشن‌های جدید (copy value، placeholder/label/help، focus/scroll، block submit، end form)، NOT/NAND/NOR، CC/BCC و tokenها، stop webhook و payload، Export/Import، debug فرانت‌اند و هوک‌های developer.

---

## 0. پیش‌نیازها

- WordPress + Easy Form Builder فعال، addon با کلید `AdnSMF` فعال (`efb_var.addons.AdnSMF === 1` در Console).
- پلن Pro فعال باشد (برای بخش 5 به Free Plus و Free هم نیاز است).
- Console مرورگر باز باشد؛ هیچ خطای JS در هیچ مرحله‌ای نباید ظاهر شود.
- cache مرورگر پاک شده باشد تا نسخه جدید `conditional-logic-efb.js` و CSS لود شود.

## 1. تست خودکار پایه (قبل از تست دستی)

از ریشه افزونه اجرا کنید و مطمئن شوید عدد مورد انتظار برمی‌گردد:

```powershell
node tests\test-conditional-logic-runtime.js          # 112 passed, 0 failed
node tests\test-conditional-logic-builder-ui.js       # 96 passed, 0 failed
node tests\test-conditional-logic-plan-gating.js      # 53 passed, 0 failed
node tests\test-conditional-logic-validate-step.js    # 10 passed, 0 failed
node tests\test-thankyou-overrides.js                 # 24 passed, 0 failed
node tests\test-payment-autosubmit-logic-gate.js      # 11 passed, 0 failed
node tests\test-core-multiform-validation-scope.js    # 17 passed, 0 failed
C:\xampp\php\php.exe tests\test-conditional-logic-sanitizer.php                   # 69 passed
C:\xampp\php\php.exe tests\test-conditional-logic-validator.php                   # 75 passed
C:\xampp\php\php.exe tests\test-conditional-logic-submission.php                  # 15 passed
C:\xampp\php\php.exe tests\test-conditional-logic-final-guard.php                 # 8 passed
C:\xampp\php\php.exe tests\test-conditional-logic-notification-confirmation.php   # 80 passed
C:\xampp\php\php.exe tests\test-conditional-logic-payment.php                     # 42 passed
C:\xampp\php\php.exe tests\test-conditional-logic-webhook.php                     # 23 passed
C:\xampp\php\php.exe tests\test-addon-settings.php                                # 12 passed
```

**مجموع مورد انتظار: 647 تست، 0 شکست.** اگر عددی کمتر بود یا failed داشتید، ادامه ندهید و اول همان را بررسی کنید.

---

## 2. Phase 7 — Debugger / Inspector (در Test Mode بیلدر)

فرم نمونه بسازید: `customer_type` (select: personal/business)، `vat_number` (text)، `price` (number)، `qty` (number)، `total` (number).

قانون نمونه: «اگر `customer_type = business` → show `vat_number` + set_required `vat_number`».

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 2.1 | در بیلدر → Form Settings → Conditional Logic → دکمه **Test Mode** | پنل مقادیر تست با همه فیلدهای فرم باز می‌شود |
| 2.2 | مقدار `customer_type = business` را وارد و **Run Test** بزنید | در trace: قانون با برچسب **Matched** سبز می‌شود و زیر آن اکشن‌های اجراشده (`Show -> vat_number`, `Required -> vat_number`) دیده می‌شود |
| 2.3 | مقدار را `personal` کنید و دوباره Run Test | برچسب **Not matched**؛ هیچ اکشنی لیست نمی‌شود |
| 2.4 | قانون را Disable کنید و Run Test | برچسب **Skipped** با آیکون خاکستری |
| 2.5 | پنل **Inspector** زیر نتایج | دو ستون: **Final values** (مقدار نهایی هر فیلد بعد از اجرای قوانین) و **Effects** (Shown/Hidden/Required/…) — مقادیر باید با قوانین سازگار باشند |
| 2.6 | دو قانون بسازید که یکی `stop_processing` داشته باشد و هر دو روی یک target عمل کنند؛ قانون اول match شود | قانون دوم با برچسب **Blocked by stop processing** نمایش داده می‌شود |
| 2.7 | قانونی با priority کوچکتر (مثلاً 5) بعد از قانونی با priority بزرگتر (20) بسازید | در trace ترتیب اجرا بر اساس priority است (5 قبل از 20)، نه ترتیب ساخت |
| 2.8 | وضعیت بالای Inspector | اگر قوانین حلقه نسازند «Stable»؛ اگر دو قانون مقدار یکدیگر را مدام تغییر دهند «Rules did not stabilize (possible loop)» |

## 3. Phase 8 — Basic Calculations (اکشن Calculate)

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 3.1 | قانون بسازید: IF `price is_not_empty` THEN **Calculate** → target = `total`، فرمول: `{price} * {qty}`، Decimals = 2 | در ردیف اکشن، ورودی فرمول + ورودی Decimals + دراپ‌داون **Insert field** ظاهر می‌شود |
| 3.2 | از دراپ‌داون Insert field فیلد `price` را انتخاب کنید | توکن `{فیلد}` به انتهای فرمول اضافه می‌شود |
| 3.3 | در Test Mode: `price=100`, `qty=3` → Run Test | در Final values مقدار `total = 300.00` |
| 3.4 | فرمول را `({price} + 50) * {qty}` کنید، `price=100, qty=2` | `total = 300.00` — پرانتز و تقدم عملگرها درست است |
| 3.5 | فرمول تقسیم بر صفر: `{price} / {qty}` با `qty=0` | در Effects خطای «Formula could not be calculated…» ظاهر می‌شود؛ مقدار total تغییر نمی‌کند؛ هیچ crash یا خطای Console وجود ندارد |
| 3.6 | فرمول با فیلد ناموجود: `{ghost_field} + 1` | همان خطای formulaInvalid — مقدار set نمی‌شود |
| 3.7 | مقدار غیرعددی: `price = "abc"` | خطای formulaInvalid (مقدار غیرعددی هرگز 0 فرض نمی‌شود) |
| 3.8 | فرم را ذخیره کنید، صفحه را reload کنید و قانون را باز کنید | فرمول و Decimals دقیقاً حفظ شده‌اند (sanitizer آنها را خراب نکرده) |
| 3.9 | فرم منتشرشده را در frontend باز کنید؛ `price` و `qty` را پر کنید | فیلد `total` بلافاصله (بعد از debounce ~120ms) به‌روز می‌شود |
| 3.10 | در frontend مقدار `total` را دستی دستکاری کنید و submit کنید | مقدار ذخیره‌شده در دیتابیس، مقدار **محاسبه‌شده سرور** است نه مقدار دستکاری‌شده (validator PHP همان فرمول را اجرا می‌کند) |

## 4. Phase 9 — Conflict Warnings (هشدار تعارض در UI)

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 4.1 | قانون A: show `vat_number`؛ قانون B: hide `vat_number` (هر دو enabled) | بالای لیست قوانین، باکس زرد **Conflicts** با پیام «vat_number: قانون A (#10), قانون B (#10)» ظاهر می‌شود |
| 4.2 | قانون A: set_required `email`؛ قانون B: set_optional `email` | هشدار conflict برای خانواده required/optional |
| 4.3 | قانون A: enable `phone`؛ قانون B: disable `phone` | هشدار conflict برای خانواده enable/disable |
| 4.4 | دو قانون: show_step / hide_step روی یک step | هشدار conflict برای step visibility |
| 4.5 | دو قانون که هر دو `set_value` یا `calculate` روی `total` دارند | هشدار «چند writer روی یک مقدار» (خانواده value حتی با نوع اکشن یکسان conflict است) |
| 4.6 | قانون B را Disable کنید | هشدار حذف می‌شود (قوانین غیرفعال در تحلیل نیستند) |
| 4.7 | داخل ویرایشگر قانونِ درگیر بروید | همان باکس هشدار فقط با conflictهای مربوط به همین قانون بالای ویرایشگر دیده می‌شود |
| 4.8 | در Test Mode با قوانین متعارض Run Test بزنید | هشدار Conflicts در پنل Inspector هم نمایش داده می‌شود |

## 5. Plan Gating (Free / Free Plus / Pro)

برای هر پلن، `package_type` را در تنظیمات تغییر دهید (Pro=1، Free Plus=3) یا افزونه را در حالت Free تست کنید.

| # | پلن | مرحله | نتیجه مورد انتظار |
|---|-----|-------|-------------------|
| 5.1 | Free | دکمه Conditional Logic را بزنید | دیالوگ upgrade (`pro_show_efb`) به‌جای بیلدر باز می‌شود |
| 5.2 | Free Plus | افزودن قانون چهارم در تب Fields | پیام «You can create up to 3 …» و اجازه افزودن نمی‌دهد؛ آیکون 💎 روی دکمه Add |
| 5.3 | Free Plus | افزودن شرط سوم در یک گروه | پیام محدودیت (حداکثر 2 شرط در گروه) |
| 5.4 | Free Plus | افزودن قانون سوم Notification | پیام محدودیت (حداکثر 2) |
| 5.5 | Free Plus | تب Confirmation و Webhook | پنل قفل Pro با دکمه Upgrade to Pro؛ افزودن قانون ممکن نیست |
| 5.6 | Free Plus | فیلدهای Priority و Stop processing در ویرایشگر | غیرفعال (disabled) با آیکون 💎؛ کلیک روی آنها toast پیام Pro می‌دهد |
| 5.7 | Free Plus | دکمه Add Group (گروه تودرتو) | قفل Pro |
| 5.8 | Pro | همه موارد بالا | بدون قفل و بدون badge 💎 |

## 6. Phase 10 — i18n / RTL / Responsive

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 6.1 | زبان سایت را فارسی کنید و بیلدر را باز کنید | همه برچسب‌های جدید (Inspector، Rule trace، Final values، Effects، Conflicts، Calculate، Formula، Decimals، Insert field، Set Value، Clear Value، Show Message، Jump to Step، Optional، Enable، Disable، Notifications، Confirmation، Webhook، Fields، Redirect، Shown، Hidden، Stable، …) از فایل ترجمه خوانده می‌شوند — متن hardcoded انگلیسی فقط وقتی دیده می‌شود که ترجمه‌ای موجود نباشد |
| 6.2 | در حالت RTL بیلدر را باز کنید | جهت toggleها، آیکون Back و چیدمان ردیف شرط/اکشن درست است؛ متن‌ها بیرون نمی‌زنند |
| 6.3 | عرض پنجره را زیر 768px ببرید | ردیف‌های شرط/اکشن ستونی می‌شوند؛ grid دو ستونه Inspector تک‌ستونه می‌شود؛ modal با عرض 95% |
| 6.4 | تست‌های سناریو E2E پایه | طبق [تست پذیرش جامع](EFB-Conditional-Logic-E2E-TEST-FA.md) — سناریوهای فرم تماس هوشمند، multi-step، nested logic و notification |

## 7. منابع شرط جدید — URL param، کاربر، step جاری (G1-G3)

در ردیف هر شرط، اولین dropdown حالا **Source** است: Field / URL parameter / User / Current step.

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 7.1 | قانون: IF «URL parameter» `utm_source` is `google` → show `vat_number` | ورودی متنی برای نام پارامتر ظاهر می‌شود؛ عملگرهای متنی (contains و…) در دسترس‌اند |
| 7.2 | فرم را در frontend با `?utm_source=google` باز کنید | فیلد نمایش داده می‌شود؛ بدون پارامتر یا با مقدار دیگر مخفی می‌ماند |
| 7.3 | قانون: IF «User» Logged in is «Logged in» → show فیلد | با کاربر وارد شده فیلد دیده می‌شود؛ در پنجره Incognito (مهمان) مخفی است |
| 7.4 | قانون: IF «User» Role is `editor` → show فیلد | فقط برای کاربری با نقش editor نمایش داده می‌شود (بدون حساسیت به بزرگی/کوچکی حروف) |
| 7.5 | قانون: IF «Current step» gte `2` → show_message روی فیلدی از step 2 | پیام از step 2 به بعد ظاهر می‌شود، در step 1 نه |
| 7.6 | در Test Mode برای همین قوانین Run Test بزنید | ورودی‌های اضافه (URL parameter: utm_source، Logged in/Role، Current step) بالای دکمه Run Test ظاهر می‌شوند و نتیجه با مقدارشان تغییر می‌کند |
| 7.7 | سمت سرور: با DevTools شرط hidden مبتنی بر user را دور بزنید و submit کنید | سرور وضعیت واقعی کاربر (session وردپرس) را ملاک می‌گیرد نه ورودی کلاینت |

## 8. عملگرهای تاریخ (G4)

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 8.1 | فیلد date + قانون `date_before 2026-06-15` | dropdown عملگر گزینه‌های before/after/between dates دارد؛ ورودی مقدار از نوع date-picker است |
| 8.2 | تاریخ `2026-06-01` وارد کنید | match؛ تاریخ `2026-07-01` → عدم match |
| 8.3 | عملگر `date_between` انتخاب کنید | دو date-picker (از/تا) ظاهر می‌شود؛ مرزها inclusive هستند |
| 8.4 | مقدار نامعتبر (متن آزاد) در فیلد تاریخ | هیچ عملگر تاریخی match نمی‌کند (نه true نه crash) |

## 9. اکشن‌های جدید فیلد (G6-G8)

| # | اکشن | مرحله | نتیجه مورد انتظار |
|---|------|-------|-------------------|
| 9.1 | Copy value from field | قانون: IF `trigger` is `go` → copy از `source` به `target` | dropdown انتخاب فیلد مبدأ (خود target در لیست نیست)؛ در frontend مقدار source به target کپی و real-time به‌روز می‌شود |
| 9.2 | Set placeholder | روی فیلد متنی | placeholder ورودی عوض می‌شود؛ وقتی شرط برنقر نشد placeholder اصلی برمی‌گردد |
| 9.3 | Set label | روی هر فیلد | متن برچسب (`{id}_lab`) عوض و با عدم match برگردانده می‌شود |
| 9.4 | Set help text | روی هر فیلد | متن راهنمای زیر فیلد عوض می‌شود (اگر نبود ساخته می‌شود) |
| 9.5 | Focus field | با تغییر فیلد trigger | فوکوس یک‌بار به اولین input فیلد هدف می‌رود (در ارزیابی‌های بعدی focus نمی‌دزدد) |
| 9.6 | Scroll to field | فرم بلند | صفحه smooth تا فیلد هدف اسکرول می‌شود، فقط یک بار به ازای هر match |

## 10. Block submit و End form (G9-G10)

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 10.1 | قانون: IF `score` lt `10` → **Block submit** با پیام «امتیاز کافی نیست» | برای این اکشن، فیلد target نمایش داده نمی‌شود؛ فقط ورودی پیام |
| 10.2 | در frontend مقدار 5 وارد کنید و Submit بزنید | پیام هشدار زرد بالای دکمه ارسال؛ فرم submit نمی‌شود |
| 10.3 | مقدار را 50 کنید | پیام حذف و submit ممکن می‌شود |
| 10.4 | با DevTools مرحله frontend را دور بزنید و مستقیماً POST کنید | سرور با همان پیام رد می‌کند (لایه authoritative) |
| 10.5 | قانون: IF `age` lt `18` → **End form with message** «این فرم برای شما نیست» | همه fieldset ها و دکمه‌های Next/Submit/Previous مخفی و پیام info نمایش داده می‌شود |
| 10.6 | مقدار را به ≥18 برگردانید | فرم و دکمه‌ها به حالت عادی برمی‌گردند |
| 10.7 | در Test Mode با مقدار مسدودکننده Run Test بزنید | بنر قرمز «Submission is not allowed…» بالای Inspector ظاهر می‌شود |

## 11. NOT / NAND / NOR (G17)

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 11.1 | در هدر گروه شرط‌ها دکمه **NOT** را بزنید (Pro) | دکمه قرمز/active می‌شود؛ برای Free Plus قفل 💎 با toast |
| 11.2 | گروه AND با دو شرط + NOT | فقط وقتی هر دو شرط برقرار نباشند match می‌شود (NAND) |
| 11.3 | گروه OR با دو شرط + NOT | فقط وقتی هیچ‌کدام برقرار نباشد match می‌شود (NOR) |
| 11.4 | ذخیره، reload، Test Mode | فلگ NOT حفظ شده و نتیجه Test Mode با frontend یکی است |

## 12. CC/BCC و Token در ایمیل و Redirect (G11-G12)

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 12.1 | قانون notification با CC=`boss@x.com` و BCC=`audit@x.com` | ورودی‌های CC/BCC (با کاما جدا) در ادیتور هست؛ ایمیل‌های نامعتبر هنگام ذخیره حذف می‌شوند |
| 12.2 | فرم را submit کنید (mail log فعال) | سه ارسال: recipient و CC و BCC (کپی‌های جداگانه با همان موضوع) |
| 12.3 | موضوع = `سفارش {product} — {email}` | در ایمیل نهایی، توکن‌ها با مقدار فیلدهای submit شده جایگزین می‌شوند؛ توکن ناشناخته حذف می‌شود |
| 12.4 | قانون confirmation از نوع redirect با URL: `https://x.com/thanks?p={plan}` | بعد از submit، کاربر به URL با مقدار urlencode شده هدایت می‌شود |

## 13. Stop webhook و Payload fields (G13)

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 13.1 | در تب Webhook، Action را روی **Stop webhook** بگذارید | ورودی URL/Method مخفی می‌شود؛ فقط Webhook ID می‌ماند (خالی = توقف همه) |
| 13.2 | قانون stop با شرط `vip=yes` و webhook_id مشخص + دو قانون trigger | وقتی stop match شود فقط trigger هم‌ID لغو می‌شود؛ webhook دیگر ارسال می‌شود |
| 13.3 | stop بدون webhook_id | هیچ webhook مشروطی ارسال نمی‌شود |
| 13.4 | در قانون trigger مقدار **Payload fields** = `lead_score` | body ارسالی فقط همان فیلد را در values/submitted_values دارد (در webhook.site چک کنید) |

## 14. Export / Import / Duplicate (G18-G19)

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 14.1 | دکمه **Export** در هدر لیست (Pro) | فایل `efb-logic-rules.json` با هر ۴ مجموعه قانون دانلود می‌شود؛ برای Free Plus قفل 💎 |
| 14.2 | در فرم دیگری **Import** بزنید و همان فایل را بدهید | قوانین لود و پیام موفقیت نمایش داده می‌شود؛ بعد از ذخیره فرم، سرور دوباره sanitize می‌کند |
| 14.3 | فایل JSON نامعتبر بدهید | پیام «فایل معتبر نیست» — بدون تغییر در قوانین |
| 14.4 | آیکون **Duplicate** روی کارت قانون | کپی با نام «(copy)» و id جدید زیر همان قانون؛ محدودیت تعداد پلن رعایت می‌شود |

## 15. Debug فرانت‌اند (G20) و هوک‌های Developer (G5)

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 15.1 | فرم منتشرشده را با `?efb_logic_debug=1` باز کنید | با هر ارزیابی، در Console یک گروه `[EFB Logic] form N — X matched…` با trace قوانین (✅/❌/⛔)، values و effects چاپ می‌شود |
| 15.2 | در Console: `efb_logic_runtime.enableDebug()` | همان خروجی بدون پارامتر URL فعال می‌شود؛ `disableDebug()` خاموشش می‌کند |
| 15.3 | در یک mu-plugin: `add_filter('efb_logic_after_evaluate_rule', fn($m,$r)=> $r['id']==='rule_x' ? false : $m, 10, 2);` | قانون rule_x در سرور هرگز اعمال نمی‌شود (veto) — سایر هوک‌ها: `efb_logic_before_evaluate_rule`، `efb_logic_modify_result`، `efb_logic_before_actions`، `efb_logic_after_actions` |

## 16. Plan Gating تکمیلی (P1)

| # | پلن | مرحله | نتیجه مورد انتظار |
|---|-----|-------|-------------------|
| 16.1 | Free Plus | گزینه **Calculate** در dropdown اکشن | disabled با 💎؛ انتخاب آن toast پیام Pro می‌دهد |
| 16.2 | Free Plus | Run Test در Test Mode | لیست matched/not matched دیده می‌شود ولی بدنه Inspector (Final values/Effects) قفل Pro است |
| 16.3 | Free Plus | دکمه‌های Export/Import و NOT | قفل 💎 با toast |
| 16.4 | Pro | همه موارد بالا | بدون قفل |

## 17. بازبینی امنیتی سریع (Spot-check)

| # | مرحله | نتیجه مورد انتظار |
|---|-------|-------------------|
| 17.1 | در فرمول Calculate مقدار `alert(1)` یا `<script>` وارد و ذخیره کنید | بعد از reload، مقدار sanitize شده؛ فرمول نامعتبر فقط پیام formulaInvalid می‌دهد؛ هرگز اجرا نمی‌شود (parser بدون eval است) |
| 17.2 | نام قانون را `<img src=x onerror=alert(1)>` بگذارید | در لیست قوانین، trace و پیام conflict به‌صورت escaped نمایش داده می‌شود؛ alert اجرا نمی‌شود |
| 17.3 | با DevTools مقدار یک فیلد hidden (که قانون آن را مخفی کرده) را پر کنید و submit کنید | سرور مقدار فیلد ignore‌شده را ذخیره نمی‌کند (لایه H11) |
| 17.4 | payload ذخیره فرم را دستکاری کنید: `decimals: 99` و `type: 'evil_action'` | بعد از ذخیره، decimals به بازه 0..6 clamp شده و اکشن ناشناخته حذف شده است |
| 17.5 | پیام block_submit را `<script>alert(1)</script>` بگذارید | پیام سمت سرور sanitize و در فرانت با textContent درج می‌شود — اجرا نمی‌شود |
| 17.6 | نام پارامتر query را `utm<script>` بگذارید | هنگام ذخیره فقط کاراکترهای مجاز URL می‌ماند (`utmscript`) |
| 17.7 | در copy_value با دستکاری payload، مبدأ را فیلد ناموجود کنید | sanitizer کل آن اکشن را حذف می‌کند |
| 17.8 | در payload_fields وب‌هوک، id فیلد ناموجود بدهید | هنگام ذخیره حذف می‌شود؛ whitelist فقط فیلدهای واقعی فرم را می‌پذیرد |

## 18. معیار قبولی

- همه ردیف‌های بالا سبز؛ **647 تست خودکار بدون شکست**.
- هیچ خطای Console در هیچ سناریو.
- هیچ تغییری در رفتار فرم‌های **بدون** قانون منطق شرطی (فرم عادی را هم یک بار submit کنید).
