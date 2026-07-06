# تحقیق رقابتی قابلیت AI در Form Builderها

**Keywords/کلمات کلیدی:** AI form builder، فرم‌ساز هوشمند، تولید فرم با پرامپت، AI conditional logic، تحلیل پاسخ‌های باز، survey AI، AI workflow automation

> وضعیت: Research draft  
> تاریخ بررسی: 2026-07-06  
> محدوده: ساختار قابلیت‌های AI در فرم‌سازهای عمومی/سروی‌سازها و نیازهایی که کاربران در Reddit و کامیونیتی‌ها مطرح کرده‌اند.

## خلاصه اجرایی

AI در فرم‌سازهای جدید معمولاً فقط یک «تولیدکننده فرم با پرامپت» نیست. الگوی بالغ‌تر از 6 لایه تشکیل می‌شود:

1. دریافت ورودی از متن، فایل، URL، فرم موجود یا voice.
2. تولید draft فرم شامل سؤال‌ها، field type، layout، page/step و گاهی logic.
3. ویرایش تعاملی با چت یا assistant داخل builder.
4. ساخت/اصلاح logic، automation، contact workflow و follow-up.
5. تحلیل پاسخ‌ها شامل خلاصه، topic، sentiment، کیفیت پاسخ و chart.
6. لایه اعتماد شامل preview، diff، restore، admin controls، data-processing transparency و الزام review انسانی.

برای Easy Form Builder فرصت اصلی این است که AI را فقط روی ساخت فرم ساده محدود نکند. مزیت رقابتی بهتر، ترکیب `AI Form Generator` با `AI Logic Copilot` و `AI Response Insights` است؛ مخصوصاً چون کاربران در کامیونیتی‌ها بارها از پیچیدگی conditional logic، تحلیل پاسخ‌های متنی، ترجمه، کنترل روی خروجی AI و پاسخ‌های کم‌کیفیت شکایت کرده‌اند.

## نقشه قابلیت رقبا

| محصول | ساختار AI فعلی | نکته محصولی قابل اقتباس برای EFB |
| --- | --- | --- |
| Jotform | تولید فرم از prompt، ویرایش محتوایی/طراحی با چت، افزودن field، تغییر متن/رنگ، فرم‌های industry-specific، سپس review و share/embed. همچنین به AI Agentها برای پاسخ‌گویی و workflow اشاره می‌کند. | تجربه‌ی «چت کن، فرم را تغییر بده، بعد منتشر کن» برای کاربر غیرتخصصی خیلی قابل فهم است. |
| Typeform | AI assistant داخل workspace، builder، contacts و automations. می‌تواند سؤال بسازد/ویرایش/حذف/مرتب کند، end screen بسازد، branching rule ایجاد کند، recall logic را تغییر دهد، فرم را preview کند، نسخه قبلی پیشنهاد را restore کند، فایل آپلود بگیرد و با connectorها context بگیرد. | Typeform AI را به یک assistant عملیاتی تبدیل کرده، نه فقط generator. برای EFB باید actionها قابل approve و rollback باشند. |
| Fillout | ساخت فرم از description، مجموعه سؤال‌ها، Google Form و PDF؛ انتخاب layout/theme؛ خروجی در editor قابل ویرایش است و AI helper tools هم دارد. | مسیر import از فرم/سند موجود برای مهاجرت کاربران مهم است. |
| Formstack | AI Form گزینه‌ای در flow ساخت فرم است. کاربر نام و prompt می‌دهد، سیستم draft می‌سازد، سپس کاربر باید review و test کند. AI از Claude در AWS Bedrock استفاده می‌کند، اما فعلاً تصویر/logo و integrations/plugins را با AI نمی‌سازد. | باید مرز قابلیت‌ها روشن باشد: «draft سریع» نه خروجی کامل بدون تست. این شفافیت ریسک support را کم می‌کند. |
| Formless by Typeform | فرم مکالمه‌ای که سؤال می‌پرسد و جواب می‌دهد، روی داده کاربر train/context می‌شود، چندزبانه است و embed می‌شود. | برای lead-gen و feedback می‌توان یک حالت مکالمه‌ای جدا از فرم کلاسیک تعریف کرد. |
| SurveyMonkey | Build/import with AI، پیشنهاد question type، پیشنهاد answer option، بررسی bias/ساختار قبل از launch، تحلیل نتایج با chat، thematic analysis، sentiment و response quality. AI feature access و پردازش داده در سطح admin قابل کنترل است. | EFB می‌تواند بعد از submit هم ارزش بسازد: insight، sentiment، low-quality flag و گزارش قابل export. |
| forms.app | تولید فرم با prompt/goal، پیشنهاد بازنویسی label، تولید answer option، AI insights روی پاسخ‌ها، تولید سؤال از متن طولانی، import از URL و برنامه برای PDF/image. درباره اشتراک‌گذاری داده پاسخ با AI هم توضیح می‌دهد. | micro-AI کنار هر field می‌تواند adoption بالایی داشته باشد: label بهتر، option بهتر، validation بهتر. |
| MakeForms | prompt با انتخاب زبان و تعداد صفحه، update با چت، تولید field/logic/layout، اتصال به CRM/sheets/workflow، ابزارهای PDF/doc-to-form و multilingual form، همراه با پیام امنیت/compliance. | گزینه‌های «زبان»، «تعداد step» و «نوع فرم» قبل از prompt کیفیت خروجی را بالا می‌برد. |

## الگوی معماری محصولی پیشنهادی

### 1. AI Form Generator

- ورودی‌ها: توضیح آزاد، قالب آماده، متن طولانی، فایل PDF/Doc/Image در فاز بعد، URL در فاز بعد.
- خروجی: مدل ساختاری مطابق `efb-ai-form-generation-model.json`.
- تصمیم‌های AI: نوع field، label، placeholder، optionها، stepها، validationهای پایه، required بودن، پیام submit.
- کنترل کاربر: preview قبل از apply، diff تغییرات، regenerate بخش انتخابی، edit دستی.

### 2. AI Field Assistant

- بازنویسی label و help text.
- پیشنهاد option برای radio/checkbox/select.
- پیشنهاد validation مثل email، phone، min/max، regex ساده.
- پیشنهاد microcopy برای error و success message.
- ترجمه fieldها با امکان ویرایش دستی.

### 3. AI Logic Copilot

- تبدیل زبان طبیعی به conditional logic rule.
- توضیح ruleهای موجود به زبان ساده.
- تشخیص conflict، unreachable step، loop، ruleهای تکراری و actionهای متناقض.
- شبیه‌سازی سناریو: «اگر کاربر گزینه X را بزند چه می‌شود؟»
- پیشنهاد routing و dynamic prefill و triggerها.

### 4. AI Workflow Assistant

- ساخت notification/email بر اساس نوع فرم.
- پیشنهاد recipient و subject و body.
- پیشنهاد webhook/Google Sheet/CRM mapping.
- lead scoring و segmentation ساده.
- ساخت end screen یا redirect بر اساس outcome.

### 5. AI Response Insights

- خلاصه پاسخ‌ها.
- دسته‌بندی open-ended responses.
- sentiment، topic، trend و quote extraction.
- flag پاسخ‌های کم‌کیفیت، خیلی سریع، متناقض یا احتمالا AI-generated.
- export گزارش برای مدیر/مشتری.

### 6. Trust, Safety, Admin Controls

- AI هرگز مستقیم publish نکند؛ تغییرات باید apply/approve شوند.
- هر پیشنهاد باید explain و قابل undo باشد.
- برای داده‌های حساس، admin بتواند AI را خاموش/روشن کند.
- قبل از ارسال داده پاسخ‌ها به AI، کاربر/ادمین باید اطلاع داشته باشد.
- برای خروجی‌های hallucination-prone مثل URL، فقط allowlist یا selected site links استفاده شود.

## درخواست‌ها و دردهای کاربران از Reddit و Community

| محور درخواست | شواهد کاربران | برداشت برای EFB |
| --- | --- | --- |
| conditional logic باید ساده‌تر و native باشد | در r/nocode کاربران درخواست «native, customizable conditional logic» داده‌اند و از تفاوت کیفیت، نیاز به workaround، dynamic prefill و trigger email/database حرف زده‌اند. | AI Logic Copilot باید first-class feature باشد، نه add-on تزئینی. |
| تحلیل پاسخ‌های باز زمان‌بر است | در r/ChatGPT کاربری برای پرسشنامه‌ای با بیش از 600 پاسخ برای هر سؤال دنبال خلاصه‌سازی AI بود و دغدغه token limit و زبان دانمارکی داشت. در r/UXResearch هم کاربری از تحلیل 1.5k کامنت و صرف چند روز زمان گفت. | response insights باید batch/chunking، زبان‌های مختلف، خلاصه موضوعی و خروجی قابل اعتماد داشته باشد. |
| کاربران به خروجی AI اعتماد کامل ندارند | در r/ProductManagement تجربه‌ای مطرح شده که AI برای داده‌های Typeform پاسخ‌های خالی را علامت‌گذاری، زبان‌ها را ترجمه و دسته‌بندی می‌کند، اما کاربر همچنان ترجیح می‌دهد معنا را خودش بررسی کند. | EFB باید AI را «دستیار ساختاردهی» معرفی کند، نه قاضی نهایی insight. |
| AI نباید سؤال‌های کاربر را بی‌اجازه بازنویسی کند | در Typeform Community کاربری گفت وقتی 8 سؤال quiz را paste می‌کند، AI آن‌ها را بازنویسی و سؤال جدید تولید می‌کند، حتی وقتی صراحتاً خواسته فقط همان سؤال‌ها استفاده شود. | در prompt UI باید mode داشته باشیم: `Preserve exact text`، `Improve wording`، `Generate from scratch`. |
| AI باید grounded باشد و URL نسازد | در Formless Community کاربری گزارش کرده AI محصول مناسب را تشخیص می‌دهد اما URL ساختگی می‌دهد؛ حتی با دستور محدودکننده هم مشکل کامل حل نشده است. | AI برای لینک‌ها باید از source map/allowlist استفاده کند و لینک خارج از allowlist را reject کند. |
| چندزبانه باید قابل کنترل باشد | در Typeform Community درباره AI-powered translations، محدودیت فرم‌های بزرگ و نیاز به language toggle/dropdown برای respondent مطرح شده است. | ترجمه AI باید همراه با manual override، language selector و warning برای فرم‌های طولانی باشد. |
| کیفیت پاسخ و AI-generated submissions مسئله جدید است | در r/UXResearch کاربران درباره agentic AI که survey را پر می‌کند حرف زده‌اند و پیشنهادهایی مثل time-to-complete، check question و flag پاسخ کم‌کیفیت داده‌اند. | EFB می‌تواند `Response Quality Score`، attention checks و anomaly flags را در گزارش‌ها اضافه کند. |
| governance و compliance مهم است | در r/nocode علاوه بر logic، کاربران به security/governance در no-code اشاره کرده‌اند. SurveyMonkey هم کنترل admin برای AI feature access و data processing را مستند کرده است. | برای WordPress، تنظیمات site-level و role-based AI access ضروری است. |

## قابلیت‌هایی که کاربران احتمالاً از AI در EFB انتظار دارند

1. «این فرم را برای من بساز» از یک توضیح کوتاه.
2. «همین فرم کاغذی/PDF را آنلاین کن».
3. «سؤال‌های من را دست نزن، فقط field typeها را تشخیص بده».
4. «سؤال‌ها را حرفه‌ای‌تر کن، ولی معنی را تغییر نده».
5. «برای این سؤال checkbox، optionهای مناسب پیشنهاد بده».
6. «بر اساس جواب کاربر، step بعدی را تعیین کن».
7. «این منطق شرطی را از فارسی/انگلیسی بساز».
8. «بگو منطق فعلی فرم چه کار می‌کند».
9. «بگو چرا این فرم برای بعضی جواب‌ها گیر می‌کند یا فیلد اشتباه نشان می‌دهد».
10. «برای leadها score بساز و ایمیل مناسب بفرست».
11. «پاسخ‌های متنی را خلاصه و دسته‌بندی کن».
12. «پاسخ‌های مشکوک/خیلی سریع/متناقض را علامت بزن».
13. «فرم را ترجمه کن، اما اجازه بده خودم ترجمه را اصلاح کنم».
14. «لینک‌ها، قیمت‌ها و اطلاعات محصول را فقط از منابع تاییدشده بگیر».

## پیشنهاد اولویت‌بندی برای Easy Form Builder

### فاز 1: AI Form Draft + Safe Apply

- تولید فرم از prompt بر اساس contract موجود.
- انتخاب preset قبل از prompt: contact، registration، feedback، booking، quote، payment.
- modeهای `Generate`، `Import exact questions`، `Improve existing form`.
- preview + diff + apply.
- warning برای add-onهای موردنیاز.

### فاز 2: Field Assistant

- sparkles کنار label/placeholder/options.
- تولید option و help text.
- ترجمه fieldها با حالت manual review.
- validation suggestion.

### فاز 3: AI Logic Copilot

- تولید rule از متن.
- explain rule.
- conflict detection.
- scenario simulator.
- rule health score قبل از publish.

### فاز 4: Response Insights

- summary برای submissions.
- topic/sentiment برای textareaها.
- low-quality response flag.
- export insight report.

### فاز 5: Grounded AI و Workflow

- source/URL allowlist.
- product/service knowledge snippets.
- lead scoring.
- notification/webhook mapping.
- multilingual respondent selector.

## تصمیم‌های طراحی مهم

- AI باید command palette یا side panel داشته باشد، نه فقط یک صفحه جدا.
- هر action باید قبل از اعمال، structured diff نشان دهد.
- برای فرم‌های موجود، default باید conservative باشد: متن موجود حفظ شود مگر کاربر rewrite بخواهد.
- خروجی AI باید به مدل داخلی تبدیل شود، نه اینکه مستقیم `form_structer` legacy تولید کند.
- برای logic، AI باید rule object بسازد و validator مستقل آن را بررسی کند.
- برای تحلیل پاسخ‌ها، نتیجه باید همراه با confidence و sample evidence باشد.
- در نسخه WordPress، privacy copy و admin toggle باید از ابتدا در طراحی باشد.

## منابع بررسی‌شده

- Jotform AI Form Builder: https://www.jotform.com/ai/form-builder/
- Typeform AI Help Center: https://help.typeform.com/hc/en-us/articles/42053773420948-Use-Typeform-AI
- Fillout AI Form Generator: https://www.fillout.com/ai-form-builder
- Formstack AI Form Creation: https://help.formstack.com/hc/en-us/articles/44593216895763-AI-Form-Creation-with-Formstack-Forms
- Formless by Typeform: https://formless.ai/
- SurveyMonkey AI Survey Generator: https://www.surveymonkey.com/product/features/ai-survey-generator/
- SurveyMonkey AI Features/Data Processing: https://help.surveymonkey.com/en/surveymonkey/account/ai-feature-access/
- forms.app AI Form Generator: https://forms.app/en/ai-form-generator
- MakeForms AI Form Builder: https://makeforms.io/ai-form-builder
- Reddit - form feature requests / conditional logic: https://www.reddit.com/r/nocode/comments/1owykb5/whats_one_form_feature_you_wish_every_tool_had/
- Reddit - summarizing open-ended questionnaires: https://www.reddit.com/r/ChatGPT/comments/15ma4ax/using_ai_to_summarize_an_openended_questionnaire/
- Reddit - analyzing 1.5k open-ended comments: https://www.reddit.com/r/UXResearch/comments/151yvrm/how_to_deal_with_over_15k_openended_comments/
- Reddit - AI data analysis for tagging/sentiment: https://www.reddit.com/r/ProductManagement/comments/1m0k3em/ai_data_analysis_tools_for_tagging_and_sentiment/
- Reddit - agentic AI completing surveys: https://www.reddit.com/r/UXResearch/comments/1p207nr/people_are_using_agentic_ai_to_complete_surveys/
- Typeform Community - AI rewriting pasted questions: https://community.typeform.com/build-your-typeform-7/typeform-ai-rewriting-questions-17241
- Typeform Community - Formless AI and made-up URLs: https://community.typeform.com/build-your-typeform-7/what-are-the-best-ways-to-go-about-training-ai-for-formless-13615
- Typeform Community - multilingual AI translation limitations/control: https://community.typeform.com/share-your-typeform-6/typeform-s-multilingual-support-is-misleading-completely-broken-15409
