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

Several items that used to be listed here as roadmap-only are now shipped: the visual conflict-warning panel, basic calculations/formula fields, the rule Inspector/Test-Mode upgrades, non-field condition sources, date operators, group NOT/NAND/NOR, the extra field actions, notification CC/BCC and token replacement, webhook stop/payload control, and Export/Import/Duplicate. All of these are documented with precise setup steps in the **New Capabilities (latest 4.x release)** group below (sections 30–45).

AI-powered rule generation, explanation, optimization, template recommendation, simulation, and personalization are still roadmap concepts, not current production UI features in the inspected code. They should be presented as planned or future capabilities unless implemented later. A dedicated Pricing/Calculations tab, per-option prices inside formulas, and a ready-made preset/template library also remain future items.

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

## New Capabilities (latest 4.x release)

The features below were added after the original brief and are live in the current builder, public runtime, and server evaluator. Each entry lists what the feature does and the exact setup steps in the admin UI. Unless noted as **Pro**, the feature is available on all packages.

### 30. Non-Field Condition Sources (URL Parameter, User, Current Step)

A condition no longer has to compare a form field. Each condition row now starts with a **Source** dropdown offering **Field**, **URL parameter**, **User**, and **Current step**. This lets rules react to how the visitor arrived and who they are, not only what they typed.

- **URL parameter** — pick `URL parameter` as the source, type the query-string key (for example `utm_source`), choose an operator (is / is not / contains / starts with / ends with / empty), and enter the value. The rule matches against `?utm_source=google` in the page URL. On the server the same key is read from the referrer URL, so behavior stays identical.
- **User** — pick `User`, then in the second control choose `Logged in` (operator `is` with value Yes/No) or `Role` (is / is not / empty against a WordPress role slug such as `administrator` or `subscriber`).
- **Current step** — pick `Current step` and compare the active step number with is / is not / greater / greater-or-equal / less / less-or-equal. Useful for showing a message only once the visitor reaches step 2.

Setup: open a rule, in the IF area open the leftmost **Source** dropdown, choose the source, fill the key/operator/value that appears, then finish the THEN action as usual.

### 31. Date Operators (Before, After, Between)

Date fields gained real date comparisons in addition to the generic ones. In a condition whose field is a Date field, the operator list now includes **date before**, **date after**, and **date between** (which shows two date inputs, From and To). Invalid or empty dates never match, so a blank date field cannot accidentally trigger a rule.

Setup: add a condition, select a Date field, pick `date before` / `date after` / `date between`, then choose the date(s). Example: `Birth date` `date before` `2008-01-01` to reveal an "adults only" note.

### 32. Group NOT / NAND / NOR (Negated Groups) — Pro

Every condition group now has a **NOT** toggle in its header. When active, the group's combined result is inverted: an AND group becomes NAND, an OR group becomes NOR. This expresses rules like "show this note *unless* Service is Sales AND Score is over 50" without rebuilding the logic.

Setup: inside the IF area, click the **NOT** button on the group header so it turns active (highlighted). Nested groups can each have their own NOT. The toggle is a **Pro** capability — on lower plans the button shows a gem badge and stays inactive.

### 33. Copy Value From Another Field

The new **Copy value from field** action fills the target field with the current value of another field whenever the rule matches — for example copying `Full name` into a `Nickname` field. Unlike Set Value (a fixed string), the value is live and follows the source field.

Setup: THEN → action type **Copy value from field** → choose the target field → in the source dropdown pick the field to copy from (the target field is excluded to avoid a self-copy).

### 34. Dynamic Field Text: Placeholder, Help Text, Label

Three new actions rewrite a field's on-screen text when a rule matches: **Set placeholder**, **Set help text**, and **Set label**. They are reversible — when the rule stops matching, the original placeholder/help/label is restored automatically.

Setup: THEN → pick **Set placeholder** / **Set help text** / **Set label** → choose the target field → type the new text in the value box. Combine several on one rule to re-skin a field for a specific path (for example switch a generic "Name" label to "Company name" for business customers).

### 35. Focus Field and Scroll to Field

**Focus field** moves the cursor into the target field, and **Scroll to field** smoothly scrolls the page to it, when the rule matches. Both are useful for guiding attention after a branching answer.

Setup: THEN → **Focus field** or **Scroll to field** → choose the target field. No value is needed. Each target is de-duplicated per evaluation so the page will not fight the user by scrolling repeatedly.

### 36. Block Submit and End Form (Form-Level Guards)

Two new form-level actions have **no field/step target**. **Block submit** prevents the form from being sent while its condition holds and shows the message you type; the server independently rejects a blocked submission, so it cannot be bypassed by editing the page. **End form with message** replaces the form with a final message and stops the flow entirely (for example an age gate).

Setup: THEN → choose **Block submit** or **End form with message** → the target dropdown disappears → type the message shown to the visitor. Example: `Score` less than `10` → Block submit "Please raise your score before submitting"; `Age` less than `18` → End form "You must be 18+".

### 37. Calculations / Formula Fields — Pro

The **Calculate** action computes a numeric result from a formula and writes it into the target field, live as the visitor types. The formula uses `{field_id}` tokens and standard math, and you can set the number of decimal places.

Setup: THEN → **Calculate** (Pro; on lower plans it is disabled with a gem badge) → choose the target field → type the formula in the value box (placeholder `{price} * {qty}`). Use the **Insert field** dropdown next to the box to drop a `{field}` token in without typing its id, and set **Decimals** (0–6) for rounding. The result is shown in the visible input and submitted with the entry.

### 38. Conditional Email CC / BCC and {field} Subject Tokens

Notification rules now support **CC** and **BCC** recipients (comma-separated) and **token replacement in the subject**: any `{field_id}` in the subject is replaced with the submitted value. Each recipient (To, and every CC/BCC address) is sent its own copy.

Setup: Notifications tab → open/create a rule → besides Email/Subject/Template, fill the **CC** and **BCC** rows with comma-separated addresses → put tokens in the subject, e.g. `New {service} order — score {score}`. The small `{field_id}` hint under the Subject label reminds you tokens are allowed.

### 39. {field} Tokens in Confirmation Redirect URLs

Confirmation redirect rules now replace `{field_id}` tokens in the destination URL with submitted values (URL-encoded), so you can pass answers to the next page.

Setup: Confirmation tab → choose action **Redirect** → in the URL box include tokens, e.g. `https://site.com/thanks?svc={service}&score={score}`. On submit the tokens are filled from the entry before redirecting.

### 40. Webhook Trigger vs Stop, and Payload Whitelist

Webhook rules gained an **Action** selector with **Trigger webhook** and **Stop webhook**. A Stop rule cancels a previously-queued webhook by its ID (leave the ID empty to stop *all* webhooks) — handy for suppressing delivery for VIPs or test submissions. Trigger rules also gained a **Payload fields** box: list the field ids to send (empty = send everything), so you only forward what the external system needs.

Setup: Webhook tab → open/create a rule → set **Action** to Trigger or Stop. For Trigger: fill Webhook ID, Method (POST/GET), URL, and optionally **Payload fields** (`field_a, field_b`). For Stop: fill the Webhook ID to cancel (or leave empty for all); Method/URL/Payload are hidden because they are not needed.

### 41. Export, Import, and Duplicate Rules

The rule list has **Export** and **Import** buttons, and every rule card has a **duplicate** icon. Export downloads all of the form's logic rules as a JSON file; Import loads a rule file into another form; Duplicate clones a single rule so you can tweak a copy. Export/Import are **Pro** (they show a gem badge on lower plans).

Setup: in the rule list toolbar click **Export** to download, or **Import** and pick a previously exported `.json` file (then review and Save the form). Click the copy icon on any rule card to duplicate that rule in place.

### 42. Package Tiers and Plan Gating (Pro / Free Plus / Free)

The builder now recognizes three tiers and gates advanced pieces accordingly. **Pro** unlocks everything; **Free Plus** and **Free** hide or disable the Pro-only pieces: the **Calculate** action, **group NOT/NAND/NOR**, **Export/Import**, and the detailed **Inspector body**. Gated controls remain visible with a gem badge so admins can see what upgrading adds, but they cannot be activated.

Setup: no configuration needed — the tier is derived from the license/package. When testing locally, the tier follows the package type, so switching packages changes which gem-badged items unlock.

### 43. Rule Inspector / Test Mode Upgrades

Test Mode became a full **Inspector**. Besides Matched / Not matched / Skipped per rule, it now shows an environment panel where you supply test values for the non-field sources (URL parameter, logged-in state, role, current step), a banner when a **Block submit** is active, a line for any **End form** message, and per-rule action summaries including the new actions. The detailed Inspector body is a **Pro** view.

Setup: open a rule and click **Test Mode / Run Test**. Fill the sample field values and any environment fields that appear (they show up only for sources your rules actually use), then read each rule's status and the form-level banners.

### 44. Front-End Debug Mode

For live troubleshooting, the public runtime can print a grouped trace to the browser console on every field change: which rules matched, which actions ran, and the resulting shown/hidden/required state.

Setup: open the published form with `?efb_logic_debug=1` appended to the URL, or run `efb_logic_runtime.enableDebug()` in the console. Turn it off by removing the parameter (or `disableDebug()`). Keep it off in production.

### 45. Developer Hooks (Filters and Actions)

For developers, the server evaluator exposes hooks around rule evaluation so custom code can observe or adjust behavior: `efb_logic_before_evaluate_rule`, `efb_logic_after_evaluate_rule`, `efb_logic_modify_result`, `efb_logic_before_actions`, and `efb_logic_after_actions`. Each is guarded so the engine works whether or not anything is hooked.

Setup: add the filter/action in a theme or plugin, e.g. `add_filter('efb_logic_modify_result', function($result, $rule, $env){ /* … */ return $result; }, 10, 3);`. Use these for auditing, custom telemetry, or last-mile result tweaks; the deterministic safety layer still runs afterward.

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

چند موردی که قبلاً اینجا فقط جزو roadmap بودند اکنون پیاده‌سازی و منتشر شده‌اند: پنل بصری هشدار تعارض، محاسبات/فیلد فرمول، ارتقای Inspector/Test Mode، منابع شرط غیرفیلدی، operatorهای تاریخ، NOT/NAND/NOR گروهی، اکشن‌های جدید فیلد، CC/BCC و جایگزینی توکن در ایمیل، کنترل stop/payload وبهوک و Export/Import/Duplicate. همه این‌ها با «روش تنظیم دقیق» در گروه **قابلیت‌های جدید (آخرین نسخه 4.x)** پایین همین سند (بخش‌های ۳۰ تا ۴۵) توضیح داده شده‌اند.

قابلیت‌های AI (ساخت rule، توضیح rule، optimizer، پیشنهاد template، simulator و personalization) همچنان جزو AI roadmap هستند و در کد UI تولیدی فعلی محسوب نمی‌شوند؛ در مقاله‌ها باید به عنوان قابلیت آینده معرفی شوند مگر اینکه بعداً پیاده‌سازی شوند. یک تب اختصاصی Pricing/Calculations، قیمت هر option داخل فرمول، و کتابخانه آماده preset/template نیز همچنان جزو موارد آینده‌اند.

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

---

## قابلیت‌های جدید (آخرین نسخه 4.x)

قابلیت‌های زیر بعد از نسخه اولیه این سند اضافه شده‌اند و هم‌اکنون در builder، runtime فرانت‌اند و ارزیاب سرور فعال هستند. برای هر مورد، کارِ قابلیت و «مراحل دقیق تنظیم» در پنل ادمین آمده است. هر جا که با علامت **Pro** مشخص نشده باشد، قابلیت روی همه پکیج‌ها در دسترس است.

### 30. منابع شرط غیرفیلدی (URL parameter، User، Current step)

دیگر لازم نیست هر شرط حتماً یک فیلد فرم را مقایسه کند. اکنون هر ردیف شرط با یک دراپ‌داون **Source** شروع می‌شود که گزینه‌های **Field**، **URL parameter**، **User** و **Current step** را دارد. با این کار قوانین می‌توانند به «نحوه ورود بازدیدکننده» و «هویت او» واکنش نشان دهند، نه فقط به مقداری که تایپ کرده است.

- **URL parameter** — منبع را `URL parameter` بگذارید، کلید query-string را بنویسید (مثلاً `utm_source`)، یک operator انتخاب کنید (is / is not / contains / starts with / ends with / empty) و مقدار را وارد کنید. قانون با `?utm_source=google` در URL صفحه match می‌شود. سمت سرور همین کلید از URL referrer خوانده می‌شود تا رفتار یکسان بماند.
- **User** — `User` را انتخاب کنید و در کنترل دوم یا `Logged in` (operator = is با مقدار بله/خیر) یا `Role` (is / is not / empty نسبت به نقش وردپرس مثل `administrator` یا `subscriber`) را انتخاب کنید.
- **Current step** — `Current step` را انتخاب کنید و شماره مرحله فعلی را با is / is not / بزرگ‌تر / بزرگ‌تر-مساوی / کوچک‌تر / کوچک‌تر-مساوی مقایسه کنید. برای نمایش یک پیام فقط وقتی کاربر به مرحله ۲ می‌رسد مفید است.

تنظیم: قانون را باز کنید، در بخش IF دراپ‌داون **Source** سمت راست را باز کنید، منبع را انتخاب کنید، کلید/operator/مقدارِ ظاهرشده را پر کنید و مثل قبل اکشن THEN را تکمیل کنید.

### 31. operatorهای تاریخ (before، after، between)

فیلدهای تاریخ علاوه بر operatorهای عمومی، مقایسه‌های واقعی تاریخ هم گرفتند. در شرطی که فیلدش از نوع Date است، لیست operator شامل **date before**، **date after** و **date between** (که دو ورودی تاریخ From و To نشان می‌دهد) می‌شود. تاریخ نامعتبر یا خالی هیچ‌وقت match نمی‌شود، پس یک فیلد تاریخ خالی به‌اشتباه قانون را فعال نمی‌کند.

تنظیم: یک شرط اضافه کنید، فیلد Date را انتخاب کنید، `date before` / `date after` / `date between` را بزنید و تاریخ(ها) را انتخاب کنید. مثال: `Birth date` `date before` `2008-01-01` برای نمایش یادداشت «مخصوص بزرگسالان».

### 32. NOT / NAND / NOR گروهی (گروه‌های negate‌شده) — Pro

اکنون هر گروه شرط یک دکمه **NOT** در هدرش دارد. با فعال شدن، نتیجه ترکیبی گروه معکوس می‌شود: گروه AND به NAND و گروه OR به NOR تبدیل می‌شود. این کار قوانینی مثل «این یادداشت را نشان بده *مگر اینکه* Service برابر Sales و Score بالای ۵۰ باشد» را بدون بازسازی منطق ممکن می‌کند.

تنظیم: در بخش IF روی دکمه **NOT** هدر گروه کلیک کنید تا فعال (highlight) شود. هر گروه تو در تو می‌تواند NOT مستقل خودش را داشته باشد. این کلید یک قابلیت **Pro** است — روی پکیج‌های پایین‌تر دکمه با نشان gem دیده می‌شود و غیرفعال می‌ماند.

### 33. کپی مقدار از فیلد دیگر (Copy value)

اکشن جدید **Copy value from field** هر بار که قانون match شود، فیلد target را با مقدار فعلی یک فیلد دیگر پر می‌کند — مثلاً کپی `Full name` داخل فیلد `Nickname`. برخلاف Set Value (رشته ثابت)، مقدار زنده است و از فیلد مبدأ پیروی می‌کند.

تنظیم: THEN → نوع اکشن **Copy value from field** → فیلد target را انتخاب کنید → در دراپ‌داون مبدأ، فیلدی که باید کپی شود را بردارید (خود فیلد target حذف شده تا self-copy رخ ندهد).

### 34. متن پویای فیلد: placeholder، help text، label

سه اکشن جدید متن روی‌صفحه فیلد را هنگام match شدن قانون بازنویسی می‌کنند: **Set placeholder**، **Set help text** و **Set label**. این‌ها reversible هستند — وقتی قانون دیگر match نکند، placeholder/help/label اصلی خودکار برمی‌گردد.

تنظیم: THEN → یکی از **Set placeholder** / **Set help text** / **Set label** را بزنید → فیلد target را انتخاب کنید → متن جدید را در باکس مقدار بنویسید. می‌توانید چند تا را روی یک قانون ترکیب کنید تا فیلد را برای یک مسیر خاص «پوسته عوض» کنید (مثلاً برای مشتری تجاری، label «Name» را به «Company name» تغییر دهید).

### 35. Focus field و Scroll to field

**Focus field** مکان‌نما را داخل فیلد target می‌برد و **Scroll to field** صفحه را به‌آرامی به آن اسکرول می‌کند، وقتی قانون match شود. هر دو برای هدایت توجه کاربر بعد از یک پاسخ شاخه‌ای مفیدند.

تنظیم: THEN → **Focus field** یا **Scroll to field** → فیلد target را انتخاب کنید. مقدار لازم نیست. هر target در هر ارزیابی dedup می‌شود تا صفحه با اسکرول مکرر با کاربر «کلنجار» نرود.

### 36. Block submit و End form (گاردهای سطح فرم)

دو اکشن جدید سطح فرم **هیچ target فیلد/step ندارند**. **Block submit** تا وقتی شرطش برقرار باشد جلوی ارسال فرم را می‌گیرد و پیامی که تایپ می‌کنید نشان می‌دهد؛ سرور به‌طور مستقل submission بلوکه‌شده را رد می‌کند، پس با دستکاری صفحه دور نمی‌خورد. **End form with message** فرم را با یک پیام نهایی جایگزین می‌کند و کل جریان را متوقف می‌کند (مثلاً محدودیت سنی).

تنظیم: THEN → **Block submit** یا **End form with message** → دراپ‌داون target ناپدید می‌شود → پیام نمایشی به کاربر را بنویسید. مثال: `Score` کوچک‌تر از `10` → Block submit با پیام «لطفاً قبل از ارسال امتیاز را بالا ببرید»؛ `Age` کوچک‌تر از `18` → End form با پیام «باید ۱۸+ باشید».

### 37. محاسبات / فیلد فرمول — Pro

اکشن **Calculate** یک نتیجه عددی از روی فرمول محاسبه می‌کند و همان لحظه که کاربر تایپ می‌کند در فیلد target می‌نویسد. فرمول از توکن‌های `{field_id}` و ریاضیات استاندارد استفاده می‌کند و می‌توانید تعداد رقم اعشار را تعیین کنید.

تنظیم: THEN → **Calculate** (Pro؛ روی پکیج پایین‌تر غیرفعال با نشان gem) → فیلد target را انتخاب کنید → فرمول را در باکس مقدار بنویسید (placeholder نمونه `{price} * {qty}`). از دراپ‌داون **Insert field** کنار باکس برای درج توکن `{field}` بدون تایپ id استفاده کنید و **Decimals** (۰ تا ۶) را برای گرد کردن تنظیم کنید. نتیجه در input قابل مشاهده نمایش داده و همراه entry ارسال می‌شود.

### 38. CC / BCC ایمیل شرطی و توکن {field} در Subject

قوانین Notification اکنون گیرنده‌های **CC** و **BCC** (جداشده با کاما) و **جایگزینی توکن در subject** را پشتیبانی می‌کنند: هر `{field_id}` در subject با مقدار ارسال‌شده جایگزین می‌شود. برای هر گیرنده (To و هر آدرس CC/BCC) یک نسخه جدا ارسال می‌شود.

تنظیم: تب Notifications → قانون را باز/ایجاد کنید → کنار Email/Subject/Template، ردیف‌های **CC** و **BCC** را با آدرس‌های جداشده با کاما پر کنید → در subject توکن بگذارید، مثل `New {service} order — score {score}`. نشانه کوچک `{field_id}` زیر برچسب Subject یادآوری می‌کند که توکن مجاز است.

### 39. توکن {field} در URL ریدایرکت Confirmation

قوانین ریدایرکت Confirmation اکنون توکن‌های `{field_id}` در URL مقصد را با مقدارهای ارسال‌شده (URL-encoded) جایگزین می‌کنند تا بتوانید پاسخ‌ها را به صفحه بعد پاس بدهید.

تنظیم: تب Confirmation → اکشن **Redirect** را انتخاب کنید → در باکس URL توکن بگذارید، مثل `https://site.com/thanks?svc={service}&score={score}`. هنگام ارسال، توکن‌ها قبل از ریدایرکت از entry پر می‌شوند.

### 40. Trigger در مقابل Stop وبهوک و whitelist محموله

قوانین Webhook یک انتخاب‌گر **Action** با **Trigger webhook** و **Stop webhook** گرفتند. یک قانون Stop، وبهوکِ قبلاً صف‌شده را بر اساس ID لغو می‌کند (ID را خالی بگذارید تا *همه* وبهوک‌ها متوقف شوند) — مناسب برای جلوگیری از ارسال برای VIPها یا submissionهای تستی. قوانین Trigger هم یک باکس **Payload fields** گرفتند: id فیلدهایی که باید ارسال شوند را لیست کنید (خالی = ارسال همه‌چیز) تا فقط چیزی که سیستم بیرونی لازم دارد فوروارد شود.

تنظیم: تب Webhook → قانون را باز/ایجاد کنید → **Action** را Trigger یا Stop بگذارید. برای Trigger: Webhook ID، Method (POST/GET)، URL و در صورت نیاز **Payload fields** (`field_a, field_b`) را پر کنید. برای Stop: Webhook ID مورد نظر برای لغو را بنویسید (یا خالی برای همه)؛ Method/URL/Payload پنهان می‌شوند چون لازم نیستند.

### 41. Export، Import و Duplicate قوانین

لیست قوانین دکمه‌های **Export** و **Import** دارد و هر کارت قانون یک آیکن **duplicate**. Export همه قوانین logic فرم را به‌صورت فایل JSON دانلود می‌کند؛ Import یک فایل قانون را در فرم دیگر بارگذاری می‌کند؛ Duplicate یک قانون را کلون می‌کند تا روی کپی‌اش تغییر بدهید. Export/Import قابلیت **Pro** هستند (روی پکیج پایین‌تر نشان gem دارند).

تنظیم: در نوار ابزار لیست قوانین روی **Export** بزنید تا دانلود شود، یا **Import** را بزنید و یک فایل `.json` که قبلاً export شده را انتخاب کنید (سپس بازبینی و فرم را Save کنید). روی آیکن کپی هر کارت قانون بزنید تا همان قانون در جا duplicate شود.

### 42. سطوح پکیج و plan gating (Pro / Free Plus / Free)

اکنون builder سه سطح را می‌شناسد و بخش‌های پیشرفته را متناسب gate می‌کند. **Pro** همه‌چیز را باز می‌کند؛ **Free Plus** و **Free** بخش‌های Pro-only را مخفی یا غیرفعال می‌کنند: اکشن **Calculate**، **NOT/NAND/NOR گروهی**، **Export/Import** و **بدنه تفصیلی Inspector**. کنترل‌های gate‌شده با نشان gem دیده می‌شوند تا ادمین ببیند ارتقا چه اضافه می‌کند، اما قابل فعال شدن نیستند.

تنظیم: نیاز به پیکربندی ندارد — سطح از روی لایسنس/پکیج مشخص می‌شود. هنگام تست محلی، سطح از package type پیروی می‌کند، پس تعویض پکیج تعیین می‌کند کدام موارد gem-badge باز شوند.

### 43. ارتقای Rule Inspector / Test Mode

Test Mode به یک **Inspector** کامل تبدیل شد. علاوه بر Matched / Not matched / Skipped برای هر قانون، اکنون یک پنل environment دارد که در آن مقدار تست برای منابع غیرفیلدی (URL parameter، وضعیت لاگین، نقش، مرحله فعلی) می‌دهید، یک بنر وقتی **Block submit** فعال است، یک خط برای پیام **End form**، و خلاصه اکشن هر قانون شامل اکشن‌های جدید. بدنه تفصیلی Inspector یک نمای **Pro** است.

تنظیم: یک قانون را باز کنید و **Test Mode / Run Test** را بزنید. مقدار نمونه فیلدها و هر فیلد environment که ظاهر می‌شود را پر کنید (این‌ها فقط برای منابعی که قوانین شما واقعاً استفاده می‌کنند نمایش داده می‌شوند)، سپس وضعیت هر قانون و بنرهای سطح فرم را بخوانید.

### 44. حالت debug فرانت‌اند

برای عیب‌یابی زنده، runtime فرانت‌اند می‌تواند در هر تغییر فیلد یک trace گروه‌بندی‌شده در کنسول مرورگر چاپ کند: کدام قوانین match شدند، کدام اکشن‌ها اجرا شدند و وضعیت نهایی shown/hidden/required.

تنظیم: فرم منتشرشده را با `?efb_logic_debug=1` در انتهای URL باز کنید، یا `efb_logic_runtime.enableDebug()` را در کنسول اجرا کنید. با حذف پارامتر (یا `disableDebug()`) خاموش می‌شود. در محیط production خاموش نگه دارید.

### 45. hookهای توسعه‌دهنده (فیلترها و اکشن‌ها)

برای توسعه‌دهندگان، ارزیاب سرور hookهایی حول ارزیابی قانون در اختیار می‌گذارد تا کد سفارشی رفتار را ببیند یا تنظیم کند: `efb_logic_before_evaluate_rule`، `efb_logic_after_evaluate_rule`، `efb_logic_modify_result`، `efb_logic_before_actions` و `efb_logic_after_actions`. هرکدام guard دارند، پس موتور چه چیزی به آن‌ها hook شده باشد و چه نباشد کار می‌کند.

تنظیم: فیلتر/اکشن را در تم یا پلاگین اضافه کنید، مثلاً `add_filter('efb_logic_modify_result', function($result, $rule, $env){ /* … */ return $result; }, 10, 3);`. از این‌ها برای auditing، telemetry سفارشی یا اصلاح نهایی نتیجه استفاده کنید؛ لایه امنیت deterministic همچنان بعد از آن اجرا می‌شود.
