# دليل اختبار حقيقي: التعبئة التلقائية عبر API خارجي (Emsfb_autofill_api_efb)

> [Docs index](../../README.md) · [Testing](../README.md) · اللغات: العربية | [English](AUTOFILL-API-TEST-GUIDE.en.md) | [فارسی](AUTOFILL-API-TEST-GUIDE.fa.md) | [Deutsch](AUTOFILL-API-TEST-GUIDE.de.md)

**الكلمات المفتاحية:** Easy Form Builder autofill API، التعبئة التلقائية لنماذج ووردبريس عبر API خارجي، Auto-Populate Integrations، EFB autofill api، إضافة ووردبريس لتعبئة النماذج تلقائياً من REST API، ربط حقول النموذج بـ API (field mapping)، search_params، response_path، مصادقة Bearer token للنماذج، مصادقة API key لنماذج ووردبريس، مدة التخزين المؤقت cache duration، تكامل REST API مع نماذج ووردبريس، JSONPlaceholder API اختبار، تعبئة النماذج ديناميكياً من API، أتمتة النماذج بالذكاء الاصطناعي، تعبئة حقول النموذج تلقائياً من API خارجي.

يشرح هذا الدليل كيفية اختبار ميزة **Auto-Populate Integrations** عملياً (تعبئة حقول النموذج تلقائياً من API خارجي). تستخدم جميع الأمثلة واجهات برمجة تطبيقات عامة ومجانية (jsonplaceholder.typicode.com و httpbin.org) حتى تتمكن من الاختبار دون إعداد أي خادم إضافي.

> المتطلبات المسبقة: أنشئ نموذجاً تجريبياً في Easy Form Builder يحتوي على عدة حقول نصية، كل منها له Label و `id_` واضحين. مثال: `user_id`، `full_name`، `email_field`، `phone_field`، `website_field`.

---

## الجزء ١ — إنشاء اتصال جديد من admin.php?page=Emsfb_autofill_api_efb

انتقل إلى **Emsfb → Auto-Populate Integrations** واضغط على "إضافة اتصال جديد". سيظهر معالج (wizard) من ٤ خطوات.

### السيناريو ١: طلب GET بسيط مع Placeholder في الرابط (بدون مصادقة)

الهدف: عند إدخال رقم في حقل `user_id`، يتم جلب بيانات مستخدم من JSONPlaceholder وتعبئة حقول الاسم/البريد الإلكتروني/الهاتف/الموقع.

**الخطوة ١ - المعلومات الأساسية:**
- Name: `Test User Lookup`
- Method: `GET`
- Endpoint URL: `https://jsonplaceholder.typicode.com/users/{{user_id}}`
- Body Template: فارغ (لأنه طلب GET)

**الخطوة ٢ - المصادقة:**
- Auth Type: `None`
- لا حاجة لأي رؤوس (headers) مخصصة

**الخطوة ٣ - ربط الحقول (Field Mapping):**
- Target Form: اختر نموذجك التجريبي
- Search Fields: ضع علامة على `user_id`. لا حاجة لتغيير عمود "API Parameter" (لأننا نستخدم `{{user_id}}` مباشرة في الرابط، وليس كـ query string)
- Response Path: فارغ (الاستجابة عبارة عن object واحد، لا حاجة لاستخراج مسار)
- Field Mappings (api_field → form_field):
  - `name` → الحقل `full_name`
  - `email` → الحقل `email_field`
  - `phone` → الحقل `phone_field`
  - `website` → الحقل `website_field`
- Cache Duration: `0` (بدون تخزين مؤقت)

**الخطوة ٤ - الاختبار والحفظ:**
- قيمة الاختبار لـ `user_id`: `1`
- اضغط على "Test" — يجب أن تظهر استجابة تحتوي على `Leanne Graham`، `Sincere@april.biz`، إلخ.
- اضغط على "Save".

### الاختبار في الواجهة الأمامية (السيناريو ١):
1. انشر النموذج في صفحة.
2. أدخل رقماً من `1` إلى `10` (مثلاً `3`) في حقل `user_id`.
3. اخرج من الحقل عبر Tab أو Enter أو بالنقر خارج الحقل (blur).
4. في DevTools ← تبويب Network، يجب أن تشاهد طلب POST إلى `wp-json/Emsfb/v1/autofill/external` يحتوي على body مثل `{api_id, form_id, search_data:[{id:"user_id", value:"3"}]}`.
5. يجب أن تكون الاستجابة `success:true, m:"done", data:[...]`، ويتم تعبئة الحقول `full_name`، `email_field`، `phone_field`، `website_field` تلقائياً ببيانات المستخدم رقم ٣ (`Clementine Bauch`).

---

### السيناريو ٢: طلب GET مع query params مبنية من search_params

الهدف: اختبار ربط حقل البحث باسم معامل API (`search_params`)، بدون استخدام placeholder في الرابط.

**الخطوة ١:**
- Name: `Test Comments by Post`
- Method: `GET`
- Endpoint URL: `https://jsonplaceholder.typicode.com/comments`

**الخطوة ٢:**
- Auth Type: `None`

**الخطوة ٣:**
- Search Fields: أنشئ حقلاً جديداً في النموذج باسم `post_id` وضع علامة عليه. في عمود "API Parameter" أدخل `postId` (وهو بالضبط المعامل الذي تتوقعه API: `?postId=1`).
- Response Path: فارغ (الاستجابة عبارة عن مصفوفة؛ يأخذ الخادم الخلفي أول عنصر تلقائياً)
- Field Mappings:
  - `name` → الحقل `commenter_name`
  - `email` → الحقل `commenter_email`
  - `body` → الحقل `comment_body`

**الاختبار:**
- قيمة الاختبار لـ `post_id`: `1`
- المتوقع: يتم إرسال طلب حقيقي إلى `https://jsonplaceholder.typicode.com/comments?postId=1`، ويُعاد أول تعليق، ويتم تعبئة الحقول `commenter_name`، `commenter_email`، `comment_body`.

> ملاحظة: إذا تركت حقل "API Parameter" فارغاً، فإن السلوك الافتراضي الجديد يجعل `search_params[post_id] = "post_id"` (يُستخدم معرّف الحقل كاسم للمعامل). لهذا السيناريو، تأكد من إدخال `postId` بشكل صريح لأن اسم معامل API يختلف عن معرّف حقل النموذج.

---

### السيناريو ٣: طلب POST مع Body Template ومصادقة Bearer (التحقق من الرؤوس والـ Body)

الهدف: اختبار `auth_type=bearer` و `body_template` مع placeholder. نستخدم `httpbin.org/post` لأنه يُعيد بالضبط ما استقبله (الرؤوس والـ body) في استجابة JSON.

**الخطوة ١:**
- Name: `Test POST with Bearer`
- Method: `POST`
- Endpoint URL: `https://httpbin.org/post`
- Body Template:
  ```json
  {"national_id": "{{national_code}}", "lookup": true}
  ```
  (أضف حقل `national_code` إلى نموذجك التجريبي)

**الخطوة ٢:**
- Auth Type: `Bearer`
- Auth Value: `my-secret-token-123`

**الخطوة ٣:**
- Search Fields: ضع علامة على `national_code` (اسم المعامل غير مهم هنا لأننا نستخدم `body_template` وليس `mapped_search_data`)
- Response Path: `json` (يُعيد httpbin البيانات المُرسلة داخل مفتاح `json`)
- Field Mappings:
  - `national_id` → الحقل `result_field` (أنشئ حقلاً نصياً جديداً في النموذج)

**الاختبار:**
- قيمة الاختبار لـ `national_code`: `0012345678`
- المتوقع: يجب أن تُظهر استجابة الاختبار `national_id: "0012345678"` (أي تم استبدال placeholder بشكل صحيح)، ويتم تعبئة `result_field` بالقيمة نفسها.
- للتحقق من رأس Authorization: اضغط على "Test" وانظر إلى الاستجابة الخام تحت `headers.Authorization`؛ يجب أن تكون `Bearer my-secret-token-123`.

---

### السيناريو ٤: مصادقة API Key + رأس مخصص

**الخطوة ١:**
- Method: `POST`
- Endpoint URL: `https://httpbin.org/post`
- Body Template: `{"ping": "pong"}`

**الخطوة ٢:**
- Auth Type: `API Key`
- Auth Value: `abc123secret`
- أضف أيضاً رأساً مخصصاً: Key=`X-Custom-Source`، Value=`efb-test`

**الخطوة ٣:**
- Response Path: `headers`
- Field Mappings:
  - `X-Api-Key` → الحقل `apikey_check_field`
  - `X-Custom-Source` → الحقل `custom_header_check_field`

**الاختبار:**
- اضغط على "Test". يجب أن تُظهر الاستجابة `X-Api-Key: abc123secret` و `X-Custom-Source: efb-test`، ويتم تعبئة الحقول المقابلة بهاتين القيمتين.

> ملاحظة: اسم رأس API Key مُحدد بشكل ثابت في الكود (`build_headers()`) كـ `X-API-Key`، لكن httpbin يُطبّع أسماء الرؤوس بصيغة Title Case (`X-Api-Key`) — إذا رجعت القيمة فارغة، غيّر مفتاح field mapping إلى `X-Api-Key`.

---

## الجزء ٢ — الاختبار في منشئ النماذج (Form Builder)

بعد إنشاء الاتصالات في الجزء ١، افتح نموذجك التجريبي في منشئ النماذج:

1. في الإعدادات العامة للنموذج (الصف الأول/على مستوى النموذج)، فعّل AutoFill بوضع **External API** واختر الاتصال الذي أنشأته (مثلاً السيناريو ١).
2. يجب أن تظهر بطاقة معلومات كبيرة بنفسجية/وردية بعنوان "API AutoFill Integration is Active" **مرة واحدة فقط**، على مستوى إعدادات النموذج (وليس لكل حقل).
3. انتقل إلى الحقول المحددة كأهداف في `field_mappings` لهذا الاتصال (مثل `full_name`، `email_field`، `phone_field`، `website_field`). يجب أن يظهر على كل منها شارة صغيرة "Auto-filled via External API".
4. الحقول التي لا تنتمي إلى الربط (حقول أخرى في النموذج) لا يجب أن تظهر عليها أي بطاقة أو شارة.
5. احفظ النموذج.

---

## الجزء ٣ — اختبار التخزين المؤقت (Cache)

1. عدّل أحد الاتصالات (مثلاً السيناريو ١) وضع `Cache Duration` على `1` (دقيقة)، ثم احفظ.
2. افتح النموذج المنشور، أدخل `user_id = 1` واخرج من الحقل (blur).
3. في DevTools ← Network، تحقق من الاستجابة الأولى — لا يجب أن تحتوي على `cached`، أو يجب ألا تكون `true` (تم إجراء طلب حقيقي إلى API).
4. أعد تحميل الصفحة وأدخل `user_id = 1` مرة أخرى (خلال الدقيقة نفسها).
5. يجب أن تحتوي الاستجابة الثانية على `"cached": true` — أي تمت قراءتها من التخزين المؤقت `transient`، وليس من API.
6. بعد مرور دقيقة واحدة، يجب أن يُعيد تكرار الطلب استدعاء API مرة أخرى (بدون `cached`).

---

## الجزء ٤ — سيناريوهات الأخطاء

| السيناريو | التهيئة | النتيجة المتوقعة |
|---|---|---|
| رابط غير صالح | غيّر Endpoint URL إلى `https://does-not-exist.invalid/api` | استجابة REST بـ `success:false`، الرمز 500، ورسالة خطأ اتصال |
| رمز خطأ HTTP ≥ 400 | غيّر الرابط إلى `https://httpbin.org/status/404` | رسالة `api_returned_error` مع رمز الحالة 404 |
| استجابة JSON غير صالحة | غيّر الرابط إلى `https://httpbin.org/html` (يُعيد HTML) | خطأ `parse_error` |
| لا يوجد field_mappings مطابق | اترك `field_mappings` فارغاً، أو اربطه بحقول غير موجودة في استجابة API | استجابة `success:false` مع رسالة `no_matching_data` (الرمز 200) |
| النموذج غير موجود | غيّر `target_form_id` إلى معرّف نموذج محذوف (أو عدّل `form_id` مباشرة في `emsfb_autofill_api_settings` في قاعدة البيانات) | استجابة `form_not_found`، الرمز 404 |
| اتصال معطّل | عطّل أحد الاتصالات من القائمة، ثم اختبر من الواجهة الأمامية | يجب ألا ينجح الطلب الخارجي / يجب اعتبار الاتصال معطلاً |

---

## قائمة تحقق ملخصة

- [ ] السيناريو ١ (GET + Placeholder في الرابط، بدون مصادقة) — تتم تعبئة الحقول
- [ ] السيناريو ٢ (GET + search_params ← query string) — تتم تعبئة الحقول
- [ ] السيناريو ٣ (POST + body_template + Bearer) — يتم إرسال رأس Authorization والـ body بشكل صحيح
- [ ] السيناريو ٤ (API Key + رأس مخصص) — كلا الرأسين يظهران في الطلب الخارجي
- [ ] منشئ النماذج: البطاقة الكبيرة تظهر مرة واحدة فقط على مستوى النموذج، والشارات فقط على الحقول المرتبطة
- [ ] التخزين المؤقت: الطلب الثاني خلال `cache_duration` يُعيد `cached:true`
- [ ] الأخطاء: كل حالة في جدول الجزء ٤ تُعيد الرسالة المناسبة
