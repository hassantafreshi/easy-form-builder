# Easy Form Builder Conditional Logic - Visual Feature Brief for WordPress Admins

This document summarizes the current Conditional Logic capabilities in Easy Form Builder from the visual point of view of a WordPress admin building a form. It is based on the AI Conditional Logic roadmap, the product/implementation roadmaps, and the current admin/public/server code paths for conditional rules.

The goal is to give content writers a complete feature map. Each section can later become a full article, tutorial, or documentation page.

---

## English

### 1. Conditional Logic Entry Point in the Form Builder

Conditional Logic is available in the form builder when the Conditional Logic add-on is active. In the WordPress admin form builder, the admin opens the form settings area and enters the Conditional Logic modal, where the interface is organized as rule cards and four main tabs: Fields, Notifications, Confirmation, and Webhook. Each tab stores its own rules, so visual field behavior, email routing, final messages, redirects, and webhook delivery can be managed separately.

The admin does not need to write code. Rules are created visually with an IF / THEN editor: the IF area defines one or more conditions, and the THEN area defines what should happen when those conditions match. The list screen shows each rule as a card with a rule name, summary, enable/disable toggle, edit button, delete button, Add button, and Test Mode button.

### 2. Rule Cards, Rule Editor, and Save Flow

Every rule can be named, enabled or disabled, edited, deleted, and saved from the modal. The rule editor visually separates logic into IF and THEN sections connected by a vertical flow line, making it easy for an admin to understand that user input triggers a form behavior. Field rules also include a Priority number and a Stop after this rule matches toggle in the footer.

The builder blocks incomplete rules before saving. For example, a rule without a valid condition, action target, required message text, valid email recipient, or valid webhook URL cannot be saved. This helps admins avoid publishing half-configured behavior.

### 3. Conditions: Selecting the Field, Operator, and Value

Each condition row has three visual parts: a field dropdown, an operator dropdown, and a value control. The field dropdown only lists usable form inputs, not structural or display-only elements such as form containers, steps, options, headings, HTML blocks, maps, matrix rows, or navigation buttons. Once a field is selected, the operator list changes based on that field type.

For option-based fields, the value is selected from a dropdown of the field's options. For number and payment amount comparisons, the value input becomes numeric. For range operators such as between and not between, the UI shows separate Min and Max inputs. For empty/not empty and paid/not paid checks, the value field is hidden because no comparison value is needed.

### 4. Supported Condition Operators

Text-style fields support is, is not, contains, does not contain, starts with, ends with, is empty, and is not empty. Choice fields such as select, radio, checkbox, country, state, city, and payment choice fields support is, is not, is empty, and is not empty. Boolean Yes/No fields support matching yes or no.

Numeric fields support is, is not, greater than, greater than or equal, less than, less than or equal, between, not between, is empty, and is not empty. Date fields support is, is not, greater than, less than, is empty, and is not empty. File-like fields support is empty and is not empty. Payment fields such as Stripe and PayPal support paid, not paid, amount equals, amount greater than, and amount less than.

### 5. AND / OR Logic and Nested Groups

Admins can add more than one condition to a rule and choose whether each relationship works as AND or OR. This makes simple logic easy, such as "Country is Canada AND Customer Type is Business," while also allowing broader logic such as "Department is Support OR Issue Type is Technical."

Nested groups are also supported visually. The admin can add a group inside another group and combine grouped logic with AND/OR connectors, for example: "(Plan is Premium AND Budget is greater than 5000) OR Coupon Code is FORCE." This allows advanced branching without writing formulas or scripts.

### 6. Field Visibility: Show and Hide Fields

The Fields tab supports Show Field and Hide Field actions. A common use case is showing a VAT Number field only when Customer Type is Business, or hiding company-related fields when the visitor selects Personal. The runtime also treats fields controlled by Show Field as hidden until their rule matches, so the form starts in the expected clean state.

On the front end, visibility changes are applied to the field wrapper with accessible hidden state and a small show/hide animation when the browser supports it. Hidden fields are also ignored during validation and submission, so a required field that is currently hidden will not block the user.

### 7. Required and Optional Fields

The Fields tab supports Set Required and Set Optional actions. Admins can make a field required only in certain paths, such as requiring Company Name for business customers but leaving it optional for personal users. The visual required marker is updated when the condition matches.

This behavior is enforced in both the browser and the submission process. If a field becomes optional or is hidden by logic, it will not be treated as required. If it becomes required in the current path, the user must fill it before moving forward or submitting.

### 8. Enable and Disable Fields

The Fields tab supports Enable Field and Disable Field actions. This is useful when an admin wants to keep a field visible for context but prevent the user from editing it under certain conditions, such as locking a calculated or prefilled value after a choice is made.

When a field is disabled by logic, the controls inside that field are disabled visually. The runtime also clears ignored disabled data from the client-side submission data, and the server has a final guard so stale or manipulated values from ignored fields are removed before the entry is saved.

### 9. Step Visibility: Show and Hide Steps

For multi-step forms, the Fields tab supports Show Step and Hide Step actions. The admin selects a step as the target instead of a field. This can be used to skip an entire company information step for personal users, hide a payment step for free requests, or reveal an extra details step only for high-value inquiries.

Fields inside a hidden step are ignored during validation and submission. The front-end form marks hidden steps so navigation and required-field checks do not force users to complete a step that their path should not show.

### 10. Jump to Step

Jump to Step moves the visitor directly to another step when a condition matches. This is different from simply hiding a step: it actively changes the current step, updates the visible fieldset, progress bar, step title, description, and Previous button state.

This is useful for guided forms where a specific answer should immediately route the user to the right section, such as sending support requests to a troubleshooting step or sending qualified leads to a quote step. The runtime protects against jumping into a hidden step and validates the actual visible step after a jump.

### 11. Set Value and Clear Value

Set Value lets the admin automatically fill a target field when a rule matches. The value can be a static value typed in the builder. When the Auto-Populate/Dataset feature is active, the Set Value action can also use a dataset column as the source, allowing the form to copy a dataset value into a field.

Clear Value empties a field when the rule matches. These actions are useful for resetting stale answers when a user changes path, prefilling known information, or controlling hidden helper fields used for routing and reporting.

### 12. Inline Conditional Messages

Show Message displays a small inline message near a target field when the rule matches. The admin writes the message in the action row, selects the target field, and the front end inserts the message inside that field's wrapper.

This is useful for contextual guidance, warnings, or explanations. For example, if the budget is below a threshold, the form can show a note explaining the minimum project size; if a support path is selected, the form can show a reminder to provide an activation code.

### 13. Priority and Stop Processing

Priority controls the order in which matching rules are evaluated. Lower priority numbers run first; if two rules have the same priority, the saved order is used. When multiple rules affect the same target, later matching actions can override earlier ones unless Stop after this rule matches is enabled.

Stop Processing does not stop every rule in the form. It freezes only the targets affected by that matched rule, so rules for unrelated fields or steps can still run. This gives admins a visual way to resolve same-target conflicts while keeping the rest of the form dynamic.

### 14. Test Mode

Test Mode lets admins preview rule matching inside the builder without publishing the form or filling the public form. The admin enters sample values for the available fields and clicks Run Test. Each rule returns a visual status such as Matched, Not matched, or Skipped.

For matched rules, the test panel shows a short action summary, such as which field will be shown, which email will be sent, which confirmation will be used, or which webhook will run. This is especially helpful before publishing complex forms with several paths.

### 15. Conditional Email Notifications

The Notifications tab lets admins send additional or targeted emails only when a rule matches. The admin builds the IF conditions visually, then enters the recipient email, subject, and template selection. The current UI supports the default template option.

On submission, the server evaluates notification rules against the submitted values and sends the matching email. This supports workflows such as sending sales leads to a sales inbox, support requests to support staff, or high-budget inquiries to a manager.

### 16. Conditional Confirmation Messages and Redirects

The Confirmation tab lets admins control what happens after form submission based on user answers. A matched rule can show a custom message or redirect the user to a specific URL. For message confirmations, the admin can also customize the done title, icon, tracking-code label, icon color, title color, and message color.

This allows different final experiences for different paths. For example, a job applicant can see a tailored thank-you message, a qualified lead can be redirected to a booking page, and a support request can receive a different confirmation message than a sales request.

### 17. Conditional Webhooks

The Webhook tab lets admins send webhook requests only when conditions match. The admin enters a webhook ID, URL, and method, choosing between POST and GET. The rule still uses the same visual IF condition builder as field, notification, and confirmation rules.

When a webhook rule matches on submission, Easy Form Builder sends a payload containing the webhook ID, rule ID, rule name, tracking code, form ID, event type, page URL, mapped values, and submitted values. This is useful for sending only specific leads, support cases, or payment-related submissions to external systems.

### 18. Data Safety, Validation, and Hidden Field Handling

Conditional Logic is not only a visual layer. Hidden fields, disabled fields, and fields inside hidden steps are treated as ignored fields. Ignored fields do not block required validation, and stale values from those fields are removed before the final submission record is saved.

The save process also validates references to real fields and steps, allowed action types, valid targets, email addresses, webhook URLs, Bootstrap icon class names, and hex colors. From the admin's perspective, this means the builder is designed to reject invalid visual setups before they become broken public forms.

### 19. Multi-Form and Legacy Compatibility

The public runtime is isolated by form ID, so multiple forms with conditional rules can exist on the same page without sharing state. The script is loaded only when the Conditional Logic add-on is active and the published form actually has active logic rules.

Older saved conditional structures are also bridged into the newer rule format for basic show/hide behavior. This protects existing forms while allowing admins to use the newer visual rule builder for more advanced behavior.

### 20. Current Limitations to Mention Clearly

The current builder has priority and stop-processing controls, but a full visual conflict-warning panel is still a roadmap item. Basic calculations are also described in the product roadmap but are not implemented in the current active builder/runtime as a completed visual feature.

AI-powered rule generation, explanation, optimization, template recommendation, simulation, and personalization are roadmap concepts, not current production UI features in the inspected code. They should be presented as planned or future capabilities unless implemented later.

### 21. AI Logic Copilot Roadmap: Natural-Language Rule Generation

The AI roadmap proposes a Generate with AI experience where the admin can describe desired behavior in plain language, such as "If the user selects Business, show the VAT field." The AI would convert that request into valid rule JSON, map the referenced fields, show a preview, explain what it created, and let the admin apply, edit, or discard the result.

Visually, this should feel like an optional assistant inside the Logic tab, not a replacement for the manual builder. The admin must always be able to inspect generated rules, understand affected fields and actions, and approve changes before they are saved.

### 22. AI Rule Explainer

The AI roadmap includes an Explain action for existing rules. From the admin's perspective, this would translate a technical IF / THEN rule into a readable explanation: when the rule runs, which fields it depends on, what it changes, and what risks may exist.

This is especially valuable for agencies, support teams, and admins inheriting older forms. A writer can position this feature as a way to make complex form behavior understandable before editing or publishing.

### 23. AI Conflict Detector and Validator

The AI roadmap proposes a Review My Logic action that checks rules before publishing. It should detect issues such as a field being hidden and required at the same time, conflicting actions on the same target, unreachable steps, circular dependencies, redundant rules, and notification rules that can never run.

Visually, findings should be grouped by severity: Critical, Warning, and Suggestion. This would extend the current deterministic safety layer with admin-friendly explanations and publish-time guidance.

### 24. AI Optimizer

The AI roadmap includes Optimize Rules for large or messy rule sets. The admin would see a before/after comparison showing which rules can be merged, simplified, or made easier to maintain.

This should remain optional and reversible. The admin should be able to apply selected improvements, keep some rules unchanged, and understand why the AI recommends a simpler structure.

### 25. AI Template Recommender

The roadmap proposes logic templates based on the form's intent, fields, steps, pricing setup, notifications, and selected industry. Examples include lead qualification, quote request branching, support triage, medical intake, job application branching, and event registration with dynamic pricing.

Visually, this could become a suggested logic pack library inside the builder. The admin would choose a template, preview the rules that will be added, adjust field mappings, and apply the pack.

### 26. AI Form Behavior Simulator

The AI roadmap proposes a simulator that tests the form across realistic scenarios. Unlike current Test Mode, which uses one set of manually entered values, the simulator would generate multiple scenarios and report visible fields, hidden fields, required fields, step flow, notification outcomes, redirect or confirmation results, pricing calculations, and submit success or failure.

This would be most useful for agencies and advanced admins building complex multi-step forms. A writer can describe it as a pre-launch behavior lab for checking every important user path before the form goes live.

### 27. AI Personalization Engine

The long-term roadmap includes privacy-aware personalization, such as shortening forms for mobile visitors, prefilling fields for logged-in users, routing campaign visitors into a shorter path, or simplifying lead forms based on user state.

This should be described carefully as a future, opt-in, explainable capability. The admin must be able to understand why a personalized path is suggested and disable it easily.

### 28. Visual Build Steps for Admins

To build a conditional field rule, the admin opens the form in the WordPress builder, adds the needed fields and steps, opens Conditional Logic, stays on the Fields tab, clicks Add, names the rule, creates the IF condition by selecting a field/operator/value, then creates the THEN action by selecting the action type and target. After setting priority or Stop Processing if needed, the admin clicks Save and can use Test Mode to check the rule.

To build conditional notifications, confirmations, or webhooks, the admin follows the same first steps but switches to the matching tab before clicking Add. The IF section works the same in all tabs; only the THEN settings change: email recipient/subject/template for Notifications, message or redirect settings for Confirmation, and webhook ID/URL/method for Webhook.

### 29. Practical Article Topics for Writers

Writers can turn each capability into a separate tutorial: how to show and hide fields, how to require fields conditionally, how to skip steps, how to jump between steps, how to display inline guidance, how to clear or prefill values, how to route emails by answer, how to show different thank-you messages, how to send conditional webhooks, how to test logic before publishing, and how to plan complex AND/OR groups.

For advanced documentation, writers should also cover priority, stop processing, hidden-field validation behavior, dataset-based Set Value, payment-related operators, multi-step routing, and the difference between implemented features and AI roadmap features.

---

## فارسی

### 1. محل ورود به کاندیشنال لاجیک در فرم‌ساز

کاندیشنال لاجیک زمانی در فرم‌ساز وردپرس در دسترس است که افزونه یا add-on مربوط به Conditional Logic فعال باشد. ادمین در محیط ساخت فرم وارد بخش تنظیمات فرم و سپس پنجره Conditional Logic می‌شود؛ این پنجره به صورت کارت‌های rule و چهار تب اصلی Fields، Notifications، Confirmation و Webhook سازمان‌دهی شده است. هر تب قوانین خودش را دارد، بنابراین رفتار ظاهری فیلدها، مسیر ارسال ایمیل، پیام نهایی، ریدایرکت و ارسال وبهوک جداگانه مدیریت می‌شوند.

ادمین نیازی به کدنویسی ندارد. ساختار هر قانون به شکل بصری IF / THEN است: در بخش IF شرط یا شرط‌ها تعیین می‌شوند و در بخش THEN مشخص می‌شود اگر شرط برقرار بود چه اتفاقی بیفتد. صفحه لیست ruleها برای هر قانون یک کارت نشان می‌دهد که شامل نام قانون، خلاصه قانون، سوییچ فعال/غیرفعال، دکمه ویرایش، دکمه حذف، دکمه افزودن و دکمه Test Mode است.

### 2. کارت قانون، ویرایشگر قانون و روند ذخیره

هر rule قابل نام‌گذاری، فعال/غیرفعال کردن، ویرایش، حذف و ذخیره است. داخل ویرایشگر، منطق به دو بخش IF و THEN تقسیم شده و بین آن‌ها یک جریان بصری دیده می‌شود تا ادمین بفهمد ورودی کاربر باعث اجرای یک رفتار در فرم می‌شود. در قوانین تب Fields، پایین ویرایشگر گزینه Priority و سوییچ Stop after this rule matches نیز وجود دارد.

فرم‌ساز اجازه ذخیره rule ناقص را نمی‌دهد. مثلاً قانونی که شرط معتبر، action معتبر، target، متن پیام لازم، ایمیل معتبر یا URL معتبر وبهوک نداشته باشد ذخیره نمی‌شود. این موضوع کمک می‌کند ادمین رفتار نیمه‌کاره یا خراب را روی فرم منتشر نکند.

### 3. شرط‌ها: انتخاب فیلد، عملگر و مقدار

هر ردیف شرط سه بخش بصری دارد: انتخاب فیلد، انتخاب operator و انتخاب یا ورود مقدار. لیست فیلدها فقط ورودی‌های قابل استفاده فرم را نشان می‌دهد و عناصر ساختاری یا نمایشی مثل خود فرم، step، option، heading، HTML، map، matrix و دکمه‌های ناوبری را به عنوان منبع شرط نمایش نمی‌دهد. بعد از انتخاب فیلد، لیست operator متناسب با نوع همان فیلد تغییر می‌کند.

برای فیلدهای گزینه‌ای، مقدار از بین optionهای همان فیلد انتخاب می‌شود. برای مقایسه عددی یا مبلغ پرداخت، ورودی مقدار عددی می‌شود. برای between و not between، رابط کاربری دو ورودی Min و Max نشان می‌دهد. برای is empty، is not empty، is paid و is not paid فیلد مقدار نمایش داده نمی‌شود، چون این نوع شرط‌ها مقدار جداگانه لازم ندارند.

### 4. operatorهای پشتیبانی‌شده

فیلدهای متنی از is، is not، contains، not contains، starts with، ends with، is empty و is not empty پشتیبانی می‌کنند. فیلدهای انتخابی مثل select، radio، checkbox، کشور، استان، شهر و گزینه‌های پرداخت از is، is not، is empty و is not empty پشتیبانی می‌کنند. فیلد Yes/No نیز امکان شرط بر اساس yes یا no را دارد.

فیلدهای عددی از is، is not، greater than، greater than or equal، less than، less than or equal، between، not between، is empty و is not empty پشتیبانی می‌کنند. فیلدهای تاریخ از is، is not، greater than، less than، is empty و is not empty پشتیبانی می‌کنند. فیلدهای فایل‌مانند از is empty و is not empty پشتیبانی دارند. فیلدهای پرداخت مثل Stripe و PayPal هم paid، not paid، amount equals، amount greater than و amount less than را پشتیبانی می‌کنند.

### 5. منطق AND / OR و گروه‌های تو در تو

ادمین می‌تواند بیش از یک شرط داخل یک rule بسازد و رابطه بین شرط‌ها را با AND یا OR مشخص کند. برای مثال شرط ساده می‌تواند این باشد: "Country برابر Canada باشد AND Customer Type برابر Business باشد." همچنین می‌توان مسیرهای بازتر ساخت، مثل: "Department برابر Support باشد OR Issue Type برابر Technical باشد."

Nested Group یا گروه تو در تو هم به صورت بصری پشتیبانی می‌شود. ادمین می‌تواند داخل یک گروه، گروه دیگری اضافه کند و آن‌ها را با AND/OR ترکیب کند؛ مثل: "(Plan برابر Premium باشد AND Budget بزرگ‌تر از 5000 باشد) OR Coupon Code برابر FORCE باشد." این امکان برای ساخت فرم‌های شاخه‌ای پیشرفته بدون کدنویسی ضروری است.

### 6. نمایش و مخفی کردن فیلدها

در تب Fields، اکشن‌های Show Field و Hide Field وجود دارد. کاربرد رایج آن این است که فیلد VAT Number فقط وقتی نمایش داده شود که Customer Type برابر Business باشد، یا فیلدهای مربوط به شرکت زمانی مخفی شوند که کاربر گزینه Personal را انتخاب می‌کند. فیلدهایی که با Show Field کنترل می‌شوند در حالت اولیه مخفی در نظر گرفته می‌شوند تا فرم از ابتدا ظاهر تمیز و درست داشته باشد.

در فرانت‌اند، نمایش و مخفی شدن روی wrapper فیلد اعمال می‌شود و در مرورگرهای پشتیبانی‌شده همراه با انیمیشن کوچک است. فیلد مخفی از validation و submission کنار گذاشته می‌شود؛ بنابراین اگر یک فیلد required باشد ولی در مسیر فعلی مخفی شده باشد، مانع ارسال فرم نمی‌شود.

### 7. required و optional کردن فیلدها

در تب Fields، اکشن‌های Set Required و Set Optional وجود دارد. ادمین می‌تواند یک فیلد را فقط در مسیر خاص required کند؛ مثلاً Company Name برای مشتری تجاری required باشد اما برای کاربر شخصی optional بماند. علامت required در ظاهر فرم نیز با برقرار شدن شرط به‌روزرسانی می‌شود.

این رفتار هم در مرورگر و هم در فرآیند ارسال فرم اعمال می‌شود. اگر فیلد optional شود یا با منطق شرطی مخفی شود، required محسوب نمی‌شود. اگر در مسیر فعلی required شود، کاربر باید آن را قبل از رفتن به مرحله بعد یا ارسال فرم پر کند.

### 8. فعال و غیرفعال کردن فیلدها

در تب Fields، اکشن‌های Enable Field و Disable Field وجود دارد. این قابلیت زمانی مفید است که ادمین می‌خواهد فیلد برای توضیح یا نمایش باقی بماند اما کاربر در شرایط خاص نتواند آن را ویرایش کند؛ مثلاً مقدار محاسبه‌شده یا از قبل پرشده بعد از انتخاب یک گزینه قفل شود.

وقتی فیلد با منطق شرطی disabled می‌شود، کنترل‌های داخل آن از نظر بصری غیرفعال می‌شوند. runtime داده‌های مربوط به فیلدهای ignored/disabled را از داده ارسالی سمت کاربر پاک می‌کند و سمت سرور هم یک لایه نهایی وجود دارد تا مقادیر قدیمی یا دستکاری‌شده این فیلدها قبل از ذخیره entry حذف شوند.

### 9. نمایش و مخفی کردن stepها

برای فرم‌های چندمرحله‌ای، تب Fields اکشن‌های Show Step و Hide Step دارد. ادمین در این حالت به جای فیلد، یک step را به عنوان target انتخاب می‌کند. این قابلیت برای رد کردن مرحله اطلاعات شرکت برای کاربران شخصی، مخفی کردن مرحله پرداخت برای درخواست‌های رایگان، یا نمایش مرحله جزئیات بیشتر فقط برای درخواست‌های مهم کاربرد دارد.

فیلدهای داخل step مخفی‌شده در validation و submission نادیده گرفته می‌شوند. فرم در فرانت‌اند stepهای مخفی‌شده را علامت‌گذاری می‌کند تا ناوبری و بررسی required کاربر را مجبور نکند مرحله‌ای را کامل کند که در مسیر او نباید نمایش داده شود.

### 10. پرش به یک step مشخص

Jump to Step کاربر را زمانی که شرط برقرار می‌شود مستقیماً به step دیگری منتقل می‌کند. این قابلیت با مخفی کردن step فرق دارد؛ چون فعالانه step فعلی را عوض می‌کند و fieldset قابل مشاهده، progress bar، عنوان step، توضیحات step و وضعیت دکمه Previous را به‌روزرسانی می‌کند.

این قابلیت برای فرم‌های راهنمایی‌شده مناسب است؛ مثلاً پاسخ خاصی کاربر را مستقیم به مرحله پشتیبانی، مرحله عیب‌یابی یا مرحله دریافت قیمت منتقل کند. runtime اجازه پرش به step مخفی‌شده را نمی‌دهد و بعد از پرش، validation را روی step واقعی و قابل مشاهده انجام می‌دهد.

### 11. Set Value و Clear Value

Set Value به ادمین اجازه می‌دهد وقتی یک rule match شد، یک فیلد target به صورت خودکار مقدار بگیرد. این مقدار می‌تواند یک مقدار ثابت باشد که داخل builder نوشته می‌شود. اگر قابلیت Auto-Populate/Dataset فعال باشد، Set Value می‌تواند مقدار را از یک ستون dataset بگیرد و داخل فیلد قرار دهد.

Clear Value مقدار یک فیلد را هنگام match شدن rule خالی می‌کند. این دو اکشن برای پاک کردن پاسخ‌های قدیمی هنگام تغییر مسیر کاربر، پرکردن خودکار اطلاعات شناخته‌شده، یا کنترل فیلدهای کمکی برای routing و گزارش‌گیری کاربرد دارند.

### 12. پیام‌های شرطی داخل فرم

Show Message یک پیام کوچک را کنار یا داخل محدوده یک فیلد target نمایش می‌دهد. ادمین متن پیام را در ردیف action وارد می‌کند، فیلد target را انتخاب می‌کند و در فرانت‌اند پیام داخل wrapper همان فیلد اضافه می‌شود.

این قابلیت برای راهنمایی، هشدار یا توضیح‌های وابسته به پاسخ کاربر مفید است. مثلاً اگر بودجه کمتر از حد مشخصی باشد، فرم می‌تواند توضیح دهد حداقل بودجه پروژه چقدر است؛ یا اگر مسیر پشتیبانی انتخاب شد، پیام یادآوری کند که کد فعال‌سازی وارد شود.

### 13. Priority و Stop Processing

Priority ترتیب اجرای ruleها را تعیین می‌کند. عدد کمتر زودتر اجرا می‌شود و اگر دو rule اولویت یکسان داشته باشند، ترتیب ذخیره‌شدن ruleها ملاک است. وقتی چند rule روی یک target اثر می‌گذارند، actionهای match‌شده بعدی می‌توانند نتیجه قبلی را تغییر دهند، مگر اینکه Stop after this rule matches فعال باشد.

Stop Processing کل فرم را متوقف نمی‌کند. این گزینه فقط targetهایی را قفل می‌کند که همان rule روی آن‌ها اثر گذاشته است؛ بنابراین ruleهای مربوط به فیلدها یا stepهای دیگر همچنان اجرا می‌شوند. این برای کنترل تعارض روی یک target، بدون از کار انداختن بقیه رفتارهای فرم، کاربرد دارد.

### 14. Test Mode

Test Mode به ادمین اجازه می‌دهد بدون انتشار فرم و بدون پر کردن فرم در سایت، match شدن ruleها را داخل builder بررسی کند. ادمین برای فیلدهای موجود مقدار نمونه وارد می‌کند و روی Run Test می‌زند. نتیجه هر rule به صورت Matched، Not matched یا Skipped نمایش داده می‌شود.

برای ruleهای match‌شده، پنل تست یک خلاصه از اکشن نشان می‌دهد؛ مثلاً کدام فیلد نمایش داده می‌شود، کدام ایمیل ارسال می‌شود، کدام confirmation استفاده می‌شود یا کدام webhook اجرا می‌شود. این قابلیت قبل از انتشار فرم‌های پیچیده و چندمسیره بسیار مهم است.

### 15. ایمیل‌های شرطی

تب Notifications به ادمین اجازه می‌دهد فقط وقتی شرط مشخصی برقرار شد ایمیل هدفمند یا اضافه ارسال کند. ادمین شرط را در بخش IF به صورت بصری می‌سازد و در بخش تنظیمات notification، ایمیل گیرنده، subject و template را وارد می‌کند. در UI فعلی گزینه template به صورت default پشتیبانی شده است.

هنگام ارسال فرم، سرور notification ruleها را با مقدارهای ارسال‌شده بررسی می‌کند و اگر rule match شود ایمیل مربوط را ارسال می‌کند. این قابلیت برای ارسال leadهای فروش به تیم فروش، درخواست‌های پشتیبانی به تیم پشتیبانی، یا درخواست‌های با بودجه بالا به مدیر مناسب است.

### 16. پیام تایید یا ریدایرکت شرطی

تب Confirmation مشخص می‌کند بعد از ارسال فرم، بسته به پاسخ کاربر چه اتفاقی بیفتد. rule match‌شده می‌تواند یک پیام اختصاصی نشان دهد یا کاربر را به URL مشخص ریدایرکت کند. برای حالت پیام، ادمین می‌تواند done title، icon، برچسب tracking code، رنگ آیکن، رنگ عنوان و رنگ پیام را هم تنظیم کند.

این قابلیت تجربه نهایی متفاوت برای مسیرهای مختلف می‌سازد. مثلاً متقاضی شغل پیام تشکر مخصوص ببیند، lead واجد شرایط به صفحه رزرو منتقل شود، و درخواست پشتیبانی پیام متفاوتی نسبت به درخواست فروش دریافت کند.

### 17. وبهوک شرطی

تب Webhook اجازه می‌دهد درخواست وبهوک فقط وقتی ارسال شود که شرط مشخصی match شده باشد. ادمین webhook ID، URL و method را وارد می‌کند و بین POST و GET انتخاب دارد. بخش IF در این تب هم همان سازنده شرط بصری سایر تب‌هاست.

وقتی webhook rule هنگام ارسال فرم match شود، Easy Form Builder payload شامل webhook ID، rule ID، rule name، tracking code، form ID، event type، page URL، values و submitted values را ارسال می‌کند. این برای فرستادن فقط برخی leadها، پرونده‌های پشتیبانی یا submissionهای مرتبط با پرداخت به سیستم‌های خارجی کاربرد دارد.

### 18. امنیت داده، validation و مدیریت فیلدهای مخفی

کاندیشنال لاجیک فقط یک لایه ظاهری نیست. فیلدهای مخفی، disabled و فیلدهای داخل step مخفی‌شده به عنوان ignored fields شناخته می‌شوند. این فیلدها مانع validation required نمی‌شوند و مقدارهای قدیمی آن‌ها قبل از ذخیره نهایی entry حذف می‌شود.

روند ذخیره همچنین بررسی می‌کند که field و step واقعاً وجود داشته باشند، action و target مجاز باشند، ایمیل معتبر باشد، URL وبهوک معتبر باشد، کلاس آیکن Bootstrap درست باشد و رنگ‌ها hex معتبر باشند. از دید ادمین، یعنی builder طراحی شده تا تنظیمات بصری نامعتبر قبل از تبدیل شدن به فرم خراب رد شوند.

### 19. سازگاری با چند فرم و ساختارهای قدیمی

runtime فرانت‌اند بر اساس form ID جدا شده است؛ بنابراین چند فرم دارای conditional logic می‌توانند در یک صفحه باشند بدون اینکه state آن‌ها با هم قاطی شود. اسکریپت فقط زمانی بارگذاری می‌شود که add-on کاندیشنال لاجیک فعال باشد و فرم منتشرشده rule فعال داشته باشد.

ساختارهای قدیمی‌تر condition نیز برای رفتارهای پایه show/hide به ساختار جدید rule تبدیل می‌شوند. این کار از فرم‌های موجود محافظت می‌کند و هم‌زمان اجازه می‌دهد ادمین از rule builder جدید برای رفتارهای پیشرفته‌تر استفاده کند.

### 20. محدودیت‌های فعلی که باید شفاف گفته شوند

در builder فعلی Priority و Stop Processing وجود دارد، اما پنل کامل هشدار تعارض‌ها هنوز جزو roadmap است. Basic Calculations نیز در نقشه راه محصول آمده اما در builder/runtime فعلی به عنوان قابلیت بصری کامل‌شده پیاده‌سازی نشده است.

AI rule generation، توضیح rule با AI، optimizer، پیشنهاد template، simulator و personalization در سند AI roadmap به عنوان قابلیت‌های برنامه‌ریزی‌شده مطرح شده‌اند و در کد بررسی‌شده UI تولیدی فعلی محسوب نمی‌شوند. در مقاله‌ها باید این موارد به عنوان قابلیت آینده یا برنامه توسعه معرفی شوند، مگر اینکه بعداً پیاده‌سازی شوند.

### 21. نقشه راه AI Logic Copilot: ساخت rule با زبان طبیعی

در AI roadmap تجربه Generate with AI پیشنهاد شده است؛ یعنی ادمین بتواند رفتار مورد نظرش را به زبان ساده بنویسد، مثل: "اگر کاربر Business را انتخاب کرد، فیلد VAT را نمایش بده." سپس AI این درخواست را به rule معتبر تبدیل کند، فیلدهای اشاره‌شده را map کند، پیش‌نمایش نشان دهد، توضیح بدهد چه ساخته و به ادمین اجازه بدهد آن را apply، edit یا discard کند.

از نظر بصری، این قابلیت باید مثل یک دستیار اختیاری داخل تب Logic باشد، نه جایگزین builder دستی. ادمین همیشه باید بتواند rule تولیدشده را ببیند، فیلدها و actionهای تحت تأثیر را بفهمد و قبل از ذخیره تغییرات را تأیید کند.

### 22. توضیح rule با AI

در roadmap، برای هر rule یک اکشن Explain پیشنهاد شده است. از دید ادمین، این قابلیت rule فنی IF / THEN را به یک توضیح قابل خواندن تبدیل می‌کند: rule چه زمانی اجرا می‌شود، به کدام فیلدها وابسته است، چه چیزی را تغییر می‌دهد و چه ریسک‌هایی ممکن است داشته باشد.

این قابلیت برای آژانس‌ها، تیم‌های پشتیبانی و ادمین‌هایی که فرم‌های قدیمی را تحویل گرفته‌اند ارزش زیادی دارد. نویسنده می‌تواند آن را به عنوان راهی برای فهمیدن رفتار فرم قبل از ویرایش یا انتشار معرفی کند.

### 23. تشخیص تعارض و اعتبارسنجی با AI

در AI roadmap اکشن Review My Logic پیشنهاد شده است تا قبل از انتشار، قوانین بررسی شوند. این بررسی باید مشکلاتی مثل مخفی و required بودن هم‌زمان یک فیلد، actionهای متعارض روی یک target، stepهای غیرقابل‌دسترسی، dependency چرخه‌ای، ruleهای تکراری یا notificationهایی که هیچ‌وقت اجرا نمی‌شوند را تشخیص دهد.

از نظر بصری، نتیجه باید با سطح اهمیت دسته‌بندی شود: Critical، Warning و Suggestion. این قابلیت در آینده لایه امنیت deterministic فعلی را با توضیح‌های قابل فهم برای ادمین و هشدارهای قبل از انتشار تکمیل می‌کند.

### 24. بهینه‌سازی ruleها با AI

در roadmap قابلیت Optimize Rules برای فرم‌هایی با ruleهای زیاد یا پیچیده پیشنهاد شده است. ادمین باید مقایسه قبل/بعد ببیند و متوجه شود کدام ruleها قابل ادغام، ساده‌سازی یا نگهداری بهتر هستند.

این قابلیت باید اختیاری و قابل بازگشت باشد. ادمین باید بتواند فقط بعضی پیشنهادها را اعمال کند، بعضی ruleها را دست‌نخورده نگه دارد و دلیل پیشنهاد AI را بفهمد.

### 25. پیشنهاد template با AI

roadmap پیشنهاد می‌کند بر اساس هدف فرم، فیلدها، stepها، تنظیمات قیمت، notificationها و صنعت انتخاب‌شده، templateهای آماده logic پیشنهاد شوند. نمونه‌ها شامل lead qualification، quote request branching، support triage، medical intake، job application branching و event registration با قیمت پویا هستند.

از نظر بصری، این می‌تواند به یک کتابخانه logic pack داخل builder تبدیل شود. ادمین template را انتخاب می‌کند، ruleهایی که قرار است اضافه شوند را پیش‌نمایش می‌بیند، mapping فیلدها را اصلاح می‌کند و سپس pack را اعمال می‌کند.

### 26. شبیه‌ساز رفتار فرم با AI

در AI roadmap یک simulator پیشنهاد شده که فرم را با چند سناریوی واقعی تست کند. برخلاف Test Mode فعلی که فقط یک مجموعه مقدار دستی را بررسی می‌کند، simulator چند سناریو تولید می‌کند و فیلدهای visible/hidden، required fields، مسیر stepها، نتیجه notification، redirect یا confirmation، محاسبات قیمت و موفقیت یا شکست submit را گزارش می‌دهد.

این قابلیت برای آژانس‌ها و ادمین‌های حرفه‌ای که فرم‌های چندمرحله‌ای پیچیده می‌سازند بسیار ارزشمند است. نویسنده می‌تواند آن را به عنوان آزمایشگاه رفتار فرم قبل از انتشار معرفی کند.

### 27. موتور personalization با AI

در نقشه راه بلندمدت، personalization با رعایت حریم خصوصی پیشنهاد شده است؛ مثل کوتاه کردن فرم برای کاربران موبایل، پرکردن خودکار فیلدها برای کاربران لاگین‌کرده، فرستادن کاربران کمپین‌ها به مسیر کوتاه‌تر، یا ساده‌سازی فرم lead بر اساس وضعیت کاربر.

این بخش باید با دقت به عنوان قابلیت آینده، اختیاری، قابل توضیح و قابل غیرفعال‌سازی معرفی شود. ادمین باید همیشه بفهمد چرا یک مسیر شخصی‌سازی‌شده پیشنهاد شده و بتواند آن را به سادگی خاموش کند.

### 28. مراحل بصری ساخت برای ادمین

برای ساخت یک rule مربوط به فیلد، ادمین فرم را در builder وردپرس باز می‌کند، فیلدها و stepهای لازم را می‌سازد، وارد Conditional Logic می‌شود، در تب Fields روی Add کلیک می‌کند، rule را نام‌گذاری می‌کند، در بخش IF فیلد/operator/value را انتخاب می‌کند و در بخش THEN نوع action و target را تعیین می‌کند. سپس در صورت نیاز Priority یا Stop Processing را تنظیم می‌کند، Save می‌زند و با Test Mode rule را بررسی می‌کند.

برای ساخت notification، confirmation یا webhook شرطی، ادمین همین مسیر را طی می‌کند اما قبل از کلیک روی Add وارد تب مربوطه می‌شود. بخش IF در همه تب‌ها یکسان است؛ فقط تنظیمات THEN فرق می‌کند: ایمیل/subject/template برای Notifications، پیام یا redirect برای Confirmation، و webhook ID/URL/method برای Webhook.

### 29. موضوعات پیشنهادی مقاله برای نویسنده

نویسنده می‌تواند هر قابلیت را به یک مقاله جدا تبدیل کند: نمایش و مخفی کردن فیلدها، required کردن شرطی، رد کردن stepها، پرش بین stepها، نمایش پیام راهنما، پاک کردن یا پرکردن خودکار مقدار، ارسال ایمیل بر اساس پاسخ، نمایش thank-you متفاوت، ارسال webhook شرطی، تست logic قبل از انتشار و طراحی گروه‌های AND/OR پیچیده.

برای مستندات پیشرفته، بهتر است Priority، Stop Processing، رفتار validation فیلدهای مخفی، Set Value مبتنی بر dataset، operatorهای پرداخت، routing چندمرحله‌ای و تفاوت قابلیت‌های پیاده‌سازی‌شده با قابلیت‌های AI roadmap نیز پوشش داده شود.
