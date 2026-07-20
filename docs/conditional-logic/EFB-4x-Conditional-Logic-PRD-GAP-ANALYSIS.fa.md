# تحلیل شکاف PRD — چه چیزهایی از EFB-4x-Conditional-Logic-PRD ساخته نشده است؟

> [فهرست مستندات](README.md) · [PRD اصلی](EFB-4x-Conditional-Logic-PRD.md) · [Roadmap محصول](EFB-Conditional-Logic-Product-ROADMAP.md) · [دستورالعمل تست دستی](EFB-Conditional-Logic-MANUAL-TEST-GUIDE.fa.md)

> **تاریخ بررسی:** 2026-07-06 · **به‌روزرسانی پیاده‌سازی:** 2026-07-07 · **شاخه:** `dev4`
> **روش:** مقایسه بند‌به‌بند PRD با کد موجود (`includes/admin/assets/js/conditional-logic-efb.js`، `public/assets/js/conditional-logic-efb.js`، `vendor/logic/class-Emsfb-logic-validator.php`، `includes/functions.php`) و تست‌های خودکار.

---

## خلاصه مدیریتی

هسته PRD (بندهای ۱ تا ۸ از «۲۲) توصیه نهایی») **کامل ساخته و تست شده است**: rule builder بصری، AND/OR + گروه تودرتو، show/hide + required/optional + enable/disable، منطق step، notification/confirmation شرطی، webhook شرطی، محاسبات پایه و Debugger/Inspector.

> ✅ **به‌روزرسانی 2026-07-07:** از ۲۰ شکاف شناسایی‌شده، **۱۷ مورد + هر دو مغایرت پکیجینگ پیاده‌سازی و تست شدند** (G1-G14، G17، G18، G20، G5، P1، P2 و G19 به‌صورت Duplicate rule). نتیجه اجرای تست‌ها بعد از پیاده‌سازی: **۶۴۴ تست خودکار، ۰ شکست**. جزئیات هر قابلیت و تست دستی آن در [دستورالعمل تست دستی](EFB-Conditional-Logic-MANUAL-TEST-GUIDE.fa.md) بخش‌های ۷ تا ۱۷ آمده است. فقط G15 (تب مستقل Pricing/Form scope)، G16 (option price) و بخش «preset کتابخانه‌ای» G19 باز مانده‌اند — دلیل در جدول پایین.

---

## ۱) ساخته‌شده و تست‌شده (مطابق PRD)

| بند PRD | قابلیت | وضعیت |
|---|---|---|
| A1 | ساختار rule: نام، enabled، شرط‌ها، اکشن‌ها، priority، stop_processing | ✅ |
| A2 | AND / OR + گروه تودرتو + connector ترکیبی در یک گروه | ✅ |
| A3 عمومی | is، is_not، contains، not_contains، starts_with، ends_with، is_empty، is_not_empty | ✅ |
| A3 عددی | gt، gte، lt، lte، between، not_between | ✅ |
| A3 بولی | از طریق is/is_not روی فیلدهای yes/no و checkbox | ✅ |
| B | شرط روی همه انواع فیلد فرم (text، number، select، radio، checkbox، date، hidden، …) | ✅ |
| B | نتیجه محاسبه به‌عنوان منبع شرط (زنجیره قوانین با multi-pass تا پایداری) | ✅ |
| — | عملگرهای پرداخت (is_paid، amount_gt، …) — فراتر از PRD | ✅ |
| C1 | show/hide، enable/disable، required/optional، set value، clear value | ✅ |
| C2 | show step، hide step (معادل skip)، jump to step | ✅ |
| C3 | پیام inline (show_message) | ✅ |
| C4 | ایمیل شرطی با گیرنده/موضوع/قالب مستقل per-rule | ✅ |
| C5 | thank-you سفارشی و redirect شرطی (+ سفارشی‌سازی آیکون/رنگ/برچسب — فراتر از PRD) | ✅ |
| C6 | webhook شرطی per-rule با URL و method | ✅ |
| C7 | معادل همه اکشن‌های pricing از طریق فرمول calculate (جمع/تفریق/درصد/round با decimals) | ✅ |
| ۹.۳/۹.۴ | کنترل‌های ساده + جمله خلاصه خوانا روی کارت هر rule | ✅ |
| ۹.۵ | Test Mode با مقادیر آزمایشی | ✅ |
| ۱۰ | Inspector: rule matched/not-matched/skipped/blocked، ترتیب اجرا، مقادیر نهایی، effects، خطاها، conflicts | ✅ |
| ۱۱ | priority + stop_processing + هشدار تعارض (show/hide، required/optional، enable/disable، step، چند writer روی یک مقدار) | ✅ |
| ۱۲ | فرمول با `+ - * / ( )` و گرد کردن؛ parser امن بدون eval در JS و PHP | ✅ |
| ۱۳ | ذخیره JSON در ساختار فرم (`logic_rules`، `notification_rules`، `confirmation_rules`، `webhook_rules`) | ✅ |
| ۱۴ | runtime با debounce، multi-form safe، بدون flicker + اجرای server-side منطق‌های حساس | ✅ |
| ۱۶ | backward compatibility (فرم‌های بدون rule بدون تغییر؛ فرمت legacy conditions پشتیبانی می‌شود) | ✅ |
| ۱۷ | plan gating: Free (قفل کامل)، Free Plus (۳ rule فیلد، ۲ notification، ۲ شرط در گروه، بدون webhook/confirmation/priority/nested)، Pro (کامل) | ✅ |

---

## ۲) وضعیت شکاف‌ها (به‌روزرسانی 2026-07-07)

### ✅ پیاده‌سازی و تست شد

| # | بند PRD | قابلیت | جزئیات پیاده‌سازی |
|---|---|---|---|
| G1 | B، ۲۰.۵ | شرط بر اساس Query string / URL param | `source: 'query_param'` با ورودی نام پارامتر در builder؛ frontend از `location.search`، سرور از referer صفحه فرم؛ تست دستی §7 |
| G2 | B، ۲۰.۵ | شرط بر اساس وضعیت لاگین و نقش کاربر | `source: 'user'` (logged_in / role)؛ state کاربر با `efb_var.user_state` به runtime تزریق و سمت سرور با session واقعی وردپرس ارزیابی می‌شود (قابل دور زدن نیست) |
| G3 | B | شرط بر اساس step جاری | `source: 'current_step'` با عملگرهای عددی؛ در submit سرور برابر آخرین step فرم |
| G4 | A3 | عملگرهای تاریخ | `date_before` / `date_after` / `date_between` با date-picker در builder؛ تاریخ نامعتبر هرگز match نمی‌کند |
| G5 | ۱۵ | هوک‌های توسعه‌دهنده | هر ۵ هوک PRD در validator: `efb_logic_before_evaluate_rule` (فیلتر/veto)، `efb_logic_after_evaluate_rule` (تغییر matched)، `efb_logic_modify_result`، `efb_logic_before_actions`، `efb_logic_after_actions` |
| G6 | C1 | Copy value from another field | اکشن `copy_value` با dropdown فیلد مبدأ؛ مبدأ نامعتبر در sanitizer حذف می‌شود |
| G7 | C1 | Change placeholder / help / label | اکشن‌های `set_placeholder` / `set_help` / `set_label` — declarative و برگشت‌پذیر (مقدار اصلی cache و restore می‌شود) |
| G8 | C1 | Focus / Scroll to field | `focus_field` / `scroll_to_field` — یک‌بار به ازای هر match (dedup مثل jump_to_step) |
| G9 | C2 | End form early with message | اکشن `end_form` — فرم و ناوبری مخفی، پیام نمایش، submit در سرور هم رد می‌شود |
| G10 | C3 | Block submit | اکشن `block_submit` بدون target با پیام؛ فرانت پیام هشدار کنار دکمه ارسال؛ سرور authoritative رد می‌کند |
| G11 | C4 | CC/BCC شرطی + موضوع داینامیک | فیلدهای CC/BCC (کاما جدا) + توکن `{field_id}` در subject؛ کپی‌ها ارسال جداگانه |
| G12 | C5 | Redirect با query داینامیک | توکن `{field_id}` در URL با rawurlencode + esc_url |
| G13 | C6 | Stop webhook + payload | `action: stop` (با webhook_id یا خالی=همه) + `payload_fields` (whitelist فیلدهای payload) |
| G14 | ۹.۲ | Badge های کارت rule | badge های scope، priority (#N) و آیکون stop_processing روی هر کارت |
| G17 | ۱۷ | NOT / NAND / NOR | دکمه NOT روی هدر هر گروه (Pro): AND+NOT=NAND، OR+NOT=NOR — در هر سه evaluator |
| G18 | ۱۷ | Export / Import قوانین | دکمه‌های Export (دانلود JSON هر ۴ مجموعه) و Import (اعتبارسنجی format + re-sanitize سمت سرور هنگام ذخیره) — Pro |
| G19* | Phase 2 | Reusable rules — به‌صورت **Duplicate rule** | دکمه کپی روی کارت هر قانون (id جدید، نام «(copy)»)؛ *کتابخانه preset جدا هنوز ساخته نشده* |
| G20 | ۱۰ | Debug فرانت‌اند | `?efb_logic_debug=1` یا `efb_logic_runtime.enableDebug()` → trace کامل قوانین/values/effects در Console |
| P1 | ۱۷ | پکیجینگ Free Plus | Calculate و بدنه Inspector (و Export/Import و NOT) برای Free Plus قفل 💎 شدند؛ لیست preview ساده Test Mode برای Free Plus باقی است |
| P2 | ۱۷ | وعده NOR/NOT/NAND در Pro | با پیاده‌سازی G17 دیگر مغایرتی نیست |

### 🔴 هنوز باز (با دلیل)

| # | بند PRD | قابلیت | چرا انجام نشد |
|---|---|---|---|
| G15 | ۸ | Scope مستقل «Form Logic» و تب مستقل «Pricing» | صرفاً سازماندهی UI است؛ محاسبات در تب Fields کاملاً در دسترس‌اند. تب جدید یعنی state management موازی با ریسک regression، بدون قابلیت تازه. پیشنهاد: در بازطراحی بعدی UI بیلدر. |
| G16 | ۱۲ | Option price به‌عنوان منبع فرمول | نیازمند تغییر داده‌مدل محصول است (گزینه‌های select قیمت جدا از value ندارند). workaround مستند: مقدار گزینه = قیمت. |
| G19b | ۲۳ | کتابخانه preset قابل استفاده مجدد | نیاز به ذخیره‌سازی global (بین فرم‌ها) دارد؛ Duplicate + Export/Import نیاز اصلی («reuse») را پوشش می‌دهد. کاندیدای 4.2+. |

---

## ۳) موج بعدی (4.X+2)

1. **G15** — تب Pricing به‌عنوان نمای فیلترشده قوانین calculate + scope form-level.
2. **G16** — قیمت روی گزینه‌های select/radio در داده‌مدل فیلد + توکن `{field.price}` در فرمول.
3. **G19b** — preset library (ذخیره rule pack در options و درج در هر فرم).
4. API عمومی operator/source/action سفارشی (فراتر از ۵ هوک فعلی — نیازمند قرارداد پایدار بین JS و PHP).

---

## ۴) یادداشت‌های فنی برای پیاده‌سازهای بعدی

- افزودن operator جدید ⇒ سه جا: `OPERATORS_BY_CATEGORY`/`OPERATOR_LABELS` در builder، `evaluateCondition()` در `public/assets/js/conditional-logic-efb.js`، `evaluate_condition()` در `vendor/logic/class-Emsfb-logic-validator.php` + whitelist `$allowed_compares` در `includes/functions.php` + تست در `tests/test-conditional-logic-runtime.js` و `tests/test-conditional-logic-validator.php`.
- افزودن اکشن جدید ⇒ `ACTION_TYPES` در builder، `$allowed_action_types` در sanitizer، `evaluatePass()` در runtime عمومی، `evaluate_pass()` در validator PHP، و `evaluateInspectorPass()` برای نمایش در Inspector.
- منابع شرط غیر-فیلدی (G1-G3) باید **هم در سرور** ارزیابی شوند وگرنه قابل دور زدن هستند (الگوی موجود: `efb_logic_prepare_submission`).
