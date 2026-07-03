# تست پذیرش جامع Conditional Logic

> [فهرست مستندات](README.md) · [Test Plan انگلیسی](EFB-Conditional-Logic-TEST-PLAN.md) · [Implementation Roadmap](EFB-Conditional-Logic-Implementation-ROADMAP.md)

این تست آخرین دروازه قبل از شروع مرحله بعد است. هدف آن بررسی یکپارچه مسیر زیر است:

`Builder → Save/Sanitize → Publish → Runtime → Frontend Validation → Server Validation → Stored Submission`

## 1. پیش‌نیازها

- WordPress و Easy Form Builder فعال باشند.
- addon با کلید `AdnSMF` فعال باشد.
- `WP_DEBUG_LOG` برای محیط توسعه فعال باشد.
- برای سناریوهای Notification، یک ابزار ثبت ایمیل فعال باشد؛ یکی از این گزینه‌ها کافی است:
  - افزونه mail log در WordPress
  - SMTP provider با لاگ ارسال
  - MailHog / Mailpit / log داخلی سرور
- Console و Network مرورگر باز باشند.
- cache مرورگر و cache افزونه پاک شده باشد.
- تست ابتدا با Chrome و سپس حداقل یک بار با Firefox اجرا شود.

## 2. تست خودکار پایه

از ریشه افزونه اجرا کنید:

```powershell
C:\xampp\php\php.exe -l includes\functions.php
C:\xampp\php\php.exe -l includes\class-Emsfb-public.php
node -c includes\admin\assets\js\conditional-logic-efb.js
node -c public\assets\js\core-efb.js
C:\xampp\php\php.exe tests\test-conditional-logic-sanitizer.php
C:\xampp\php\php.exe tests\test-conditional-logic-submission.php
C:\xampp\php\php.exe tests\test-conditional-logic-validator.php
C:\xampp\php\php.exe tests\test-conditional-logic-final-guard.php
C:\xampp\php\php.exe tests\test-conditional-logic-webhook.php
node tests\test-conditional-logic-runtime.js
node tests\test-conditional-logic-builder-ui.js
node tests\test-conditional-logic-validate-step.js
node tests\test-core-multiform-validation-scope.js
```

نتیجه مورد انتظار (آخرین اجرای دستی کامل: 2026-06-29):

```text
PHP syntax: includes/functions.php OK
PHP syntax: includes/class-Emsfb-public.php OK
JS syntax: includes/admin/assets/js/conditional-logic-efb.js OK
JS syntax: public/assets/js/core-efb.js OK
Sanitizer: 49/49 passed
Submission: 15/15 passed
Validator (PHP addon واقعی): 24/24 passed
Final-save guard: 8/8 passed
Conditional webhook: 15/15 passed
Runtime: 65/65 passed
Builder UI: 30/30 passed
Validate-step (H13 + H14): 7/7 passed
Core multi-form validation scope: 17/17 passed
```

- [ ] تمام تست‌های PHP پاس شدند.
- [ ] تمام تست‌های JavaScript پاس شدند (شامل تست جدید `test-conditional-logic-builder-ui.js`).
- [ ] هیچ warning یا fatal جدیدی ثبت نشد.

### 2.1 تست خودکار هدفمند Phase 3

این smoke testها منطق جدید Notification/Confirmation را بدون ارسال ایمیل واقعی بررسی می‌کنند. از ریشه افزونه اجرا کنید.

#### 2.1.1 تست UI Builder برای تب‌های Notification و Confirmation

```powershell
@'
const path = require('path');
const mockEls = {};
function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
global.document = {
  createElement(){ return { _text:'', appendChild(n){ this._text += escapeHtml(n && n.nodeValue !== undefined ? n.nodeValue : ''); }, get innerHTML(){ return this._text; } }; },
  createTextNode(s){ return { nodeValue: String(s) }; },
  getElementById(id){ return mockEls[id] || (mockEls[id] = { innerHTML:'', textContent:'', className:'', classList:{ add(){}, remove(){} } }); }
};
global.window = global;
global.efb_var = { text: {}, rtl: 0 };
global.valj_efb = [
  { id_:'form', type:'form', logic_rules: [{ id:'existing', enabled:true, conditions:{ type:'group', operator:'AND', items:[{ type:'condition', field_id:'service', compare:'is', value:'sales' }] }, actions:[{ type:'show_field', target:'notes' }] }] },
  { id_:'service', type:'select', name:'Service' },
  { id_:'sales', type:'option', parent:'service', value:'Sales' },
  { id_:'notes', type:'text', name:'Notes' }
];
let alerts = [];
global.alert_message_efb = (m) => { alerts.push(String(m)); };
require(path.join(process.cwd(), 'includes/admin/assets/js/conditional-logic-efb.js'));
function assert(label, condition){ if(!condition) throw new Error(label); console.log('[PASS] ' + label); }

EFB_Logic.switchTab('notification');
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'service');
EFB_Logic.updateCondition('0', 'value', 'sales');
EFB_Logic.applyRule();
assert('notification invalid email blocks save', !valj_efb[0].notification_rules && alerts.length === 1);
EFB_Logic.updateNotification('recipient', 'sales@example.com');
EFB_Logic.updateNotification('subject', 'Sales lead [confirmation_code]');
EFB_Logic.applyRule();
assert('notification rule saved', Array.isArray(valj_efb[0].notification_rules) && valj_efb[0].notification_rules.length === 1);
assert('field logic array preserved', valj_efb[0].logic_rules.length === 1 && valj_efb[0].logic_rules[0].id === 'existing');

EFB_Logic.switchTab('confirmation');
EFB_Logic.addRule();
EFB_Logic.updateCondition('0', 'field_id', 'service');
EFB_Logic.updateCondition('0', 'value', 'sales');
EFB_Logic.updateConfirmation('action', 'redirect');
EFB_Logic.applyRule();
assert('confirmation redirect without URL blocks save', !valj_efb[0].confirmation_rules);
EFB_Logic.updateConfirmation('url', 'https://example.com/vip-thanks');
EFB_Logic.applyRule();
assert('confirmation redirect rule saved', Array.isArray(valj_efb[0].confirmation_rules) && valj_efb[0].confirmation_rules[0].action === 'redirect');
assert('logic flag still reflects existing field rules', valj_efb[0].logic === true);
'@ | node -
```

نتیجه مورد انتظار:

```text
[PASS] notification invalid email blocks save
[PASS] notification rule saved
[PASS] field logic array preserved
[PASS] confirmation redirect without URL blocks save
[PASS] confirmation redirect rule saved
[PASS] logic flag still reflects existing field rules
```

#### 2.1.2 تست Server-side بدون ارسال ایمیل واقعی

این تست با Reflection متدهای private مربوط به Phase 3 را اجرا می‌کند و `send_email_Emsfb_` را override می‌کند تا ایمیل واقعی ارسال نشود.

```powershell
@'
<?php
define('ABSPATH', __DIR__ . '/');
function add_action(){ }
function add_shortcode(){ }
function register_rest_route(){ }
function get_current_user_id(){ return 1; }
function get_efbFunction(){ return null; }
function get_setting_Emsfb(){ return []; }
function do_action(){ }
function is_admin(){ return false; }
function sanitize_email($v){ return filter_var((string)$v, FILTER_SANITIZE_EMAIL); }
function is_email($v){ return filter_var((string)$v, FILTER_VALIDATE_EMAIL) !== false; }
function sanitize_text_field($v){ return is_array($v) ? '' : trim(strip_tags((string)$v)); }
function esc_url($v){ return filter_var((string)$v, FILTER_SANITIZE_URL); }
function wp_kses_post($v){ return strip_tags((string)$v, '<b><strong><em><i><br><p><a>'); }
require 'includes/class-Emsfb-public.php';

class Test_EFB_Public extends Emsfb\_Public {
    public $sent = [];
    public function send_email_Emsfb_($to, $track, $pro, $state, $link, $content = 'null', $sub = 'null') {
        $this->sent[] = compact('to', 'track', 'pro', 'state', 'link', 'content', 'sub');
    }
}
function assert_true($label, $cond) {
    if (!$cond) { echo "[FAIL] $label\n"; exit(1); }
    echo "[PASS] $label\n";
}
$ref = new ReflectionClass('Test_EFB_Public');
$obj = $ref->newInstanceWithoutConstructor();
$parent = new ReflectionClass('Emsfb\\_Public');
$notify = $parent->getMethod('process_conditional_notification_rules');
$notify->setAccessible(true);
$confirm = $parent->getMethod('get_conditional_confirmation_result');
$confirm->setAccessible(true);

$form = [[
    'type' => 'form',
    'notification_rules' => [
        ['id' => 'nr_sales', 'enabled' => true, 'priority' => 10, 'recipient' => 'sales@example.com', 'subject' => 'Sales [confirmation_code]', 'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [['type' => 'condition', 'field_id' => 'service', 'compare' => 'is', 'value' => 'sales']]]],
        ['id' => 'nr_support', 'enabled' => true, 'priority' => 10, 'recipient' => 'support@example.com', 'subject' => 'Support', 'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [['type' => 'condition', 'field_id' => 'service', 'compare' => 'is', 'value' => 'support']]]],
        ['id' => 'nr_bad', 'enabled' => true, 'priority' => 10, 'recipient' => 'bad-email', 'subject' => 'Bad', 'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [['type' => 'condition', 'field_id' => 'service', 'compare' => 'is', 'value' => 'sales']]]],
    ],
    'confirmation_rules' => [
        ['id' => 'cr_vip', 'enabled' => true, 'priority' => 5, 'action' => 'redirect', 'url' => 'https://example.com/vip', 'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [['type' => 'condition', 'field_id' => 'budget', 'compare' => 'gt', 'value' => '1000']]]],
        ['id' => 'cr_msg', 'enabled' => true, 'priority' => 10, 'action' => 'message', 'message' => '<strong>Thanks</strong><script>x</script>', 'conditions' => ['type' => 'group', 'operator' => 'AND', 'items' => [['type' => 'condition', 'field_id' => 'service', 'compare' => 'is', 'value' => 'sales']]]],
    ],
], ['id_' => 'service', 'type' => 'text'], ['id_' => 'budget', 'type' => 'number']];
$submitted = [
    ['id_' => 'service', 'type' => 'text', 'value' => 'sales'],
    ['id_' => 'budget', 'type' => 'number', 'value' => '1500'],
];
$status = ['content' => 'body', 'subject' => 'Default subject', 'type' => 'message_link'];
$notify->invoke($obj, $form, $submitted, 'TRK123', true, 'https://site.test/form', $status);
assert_true('only one matching valid conditional email is sent', count($obj->sent) === 1);
assert_true('matching email recipient is sales@example.com', $obj->sent[0]['to'][0] === 'sales@example.com');
assert_true('conditional subject is passed to existing email sender', $obj->sent[0]['sub'] === 'Sales [confirmation_code]');
assert_true('existing email content/type is reused', $obj->sent[0]['content'] === 'body' && $obj->sent[0]['state'][2] === 'message_link');

$result = $confirm->invoke($obj, $form, $submitted);
assert_true('higher-priority matching confirmation returns redirect', is_array($result) && $result['action'] === 'redirect' && $result['url'] === 'https://example.com/vip');
$submittedLow = [ ['id_' => 'service', 'type' => 'text', 'value' => 'sales'], ['id_' => 'budget', 'type' => 'number', 'value' => '500'] ];
$resultLow = $confirm->invoke($obj, $form, $submittedLow);
assert_true('fallback matching confirmation returns sanitized message', is_array($resultLow) && $resultLow['action'] === 'message' && strpos($resultLow['message'], '<script>') === false && strpos($resultLow['message'], '<strong>Thanks</strong>') !== false);
$submittedNone = [ ['id_' => 'service', 'type' => 'text', 'value' => 'billing'], ['id_' => 'budget', 'type' => 'number', 'value' => '500'] ];
assert_true('no matching confirmation returns null', $confirm->invoke($obj, $form, $submittedNone) === null);
'@ | C:\xampp\php\php.exe
```

نتیجه مورد انتظار:

```text
[PASS] only one matching valid conditional email is sent
[PASS] matching email recipient is sales@example.com
[PASS] conditional subject is passed to existing email sender
[PASS] existing email content/type is reused
[PASS] higher-priority matching confirmation returns redirect
[PASS] fallback matching confirmation returns sanitized message
[PASS] no matching confirmation returns null
```

### 2.2 تست خودکار هدفمند Phase 4

این تست ارسال Conditional Webhook را بدون تماس شبکه واقعی بررسی می‌کند. `wp_remote_post` و `wp_remote_get` در تست stub شده‌اند تا فقط payload، header، شرط‌ها و hookهای before/after بررسی شوند.

```powershell
C:\xampp\php\php.exe tests\test-conditional-logic-webhook.php
```

نتیجه مورد انتظار:

```text
[PASS] T1.1 only one matching webhook is sent
[PASS] T1.2 matching webhook URL is used
[PASS] T1.3 sent metadata records matching rule
[PASS] T1.4 payload includes track code
[PASS] T1.5 payload includes values map
[PASS] T1.6 payload includes webhook id header
[PASS] T1.7 before/after hooks fired
[PASS] T2.1 GET webhook does not use POST transport
[PASS] T2.2 GET webhook is sent once
[PASS] T2.3 GET webhook URL includes track_code and event_type
[PASS] T2.4 GET webhook metadata records GET method
[PASS] T3.1 non-matching value sends no webhook
[PASS] T3.2 non-matching result is empty
[PASS] T4.1 form with no webhook_rules keeps old behavior and sends nothing
[PASS] T4.2 form with no webhook_rules returns empty result
```

### 2.3 تست خودکار هدفمند Phase 6

این تست داخل `test-conditional-logic-builder-ui.js` اجرا می‌شود و Test Mode در builder را بررسی می‌کند: باز شدن پنل، اجرای ruleها با مقدار تست، نمایش Matched / Not matched، نمایش Skipped برای rule غیرفعال، و عدم تغییر تعداد ruleها.

```powershell
node tests\test-conditional-logic-builder-ui.js
```

نتیجه‌های مرتبط با Phase 6:

```text
[PASS] T8.1 test mode panel rendered
[PASS] T8.2 price=7 shows a matched rule
[PASS] T8.3 price=7 shows a not matched rule
[PASS] T8.4 disabled rule appears as skipped in Test Mode
[PASS] T8.5 Test Mode does not create or remove field rules
```

## 3. ساخت Fixture اصلی

یک فرم سه‌مرحله‌ای با نام `CL Full Acceptance` بسازید.

### Step 1 — Basic

| شناسه پیشنهادی | نوع | عنوان | وضعیت اولیه |
|---|---|---|---|
| `customer_type` | select | Customer type | Required |
| `has_budget` | yesNo | Do you have a budget? | Required |
| `budget` | number | Budget | Optional |
| `logic_command` | text | Logic command | Optional |

گزینه‌های `customer_type`:

- `Individual`
- `Company`

### Step 2 — Company

| شناسه پیشنهادی | نوع | عنوان | وضعیت اولیه |
|---|---|---|---|
| `company_name` | text | Company name | Required |
| `company_email` | email | Company email | Required |
| `internal_code` | text | Internal code | Optional |

### Step 3 — Final

| شناسه پیشنهادی | نوع | عنوان | وضعیت اولیه |
|---|---|---|---|
| `final_note` | textarea | Final note | Required |
| `conflict_target` | text | Conflict target | Optional |

## 4. تعریف Ruleها

Ruleها را دقیقاً با priority زیر ایجاد کنید.

| Rule | Priority | شرط | Action |
|---|---:|---|---|
| R1 | 10 | `customer_type is Individual` | `hide_step` روی Step 2 |
| R2 | 10 | `customer_type is Company` | `show_step` روی Step 2 |
| R3 | 20 | `has_budget is yes` | `show_field` روی `budget` |
| R4 | 21 | `has_budget is yes` | `set_required` روی `budget` |
| R5 | 20 | `has_budget is no` | `hide_field` روی `budget` |
| R6 | 21 | `has_budget is no` | `clear_value` روی `budget` |
| R7 | 30 | `budget gt 1000` | `show_message` روی `budget` |
| R8 | 30 | `customer_type is Company` | `set_value` روی `internal_code` با مقدار `COMPANY` |
| R9 | 31 | `customer_type is Company` | `disable_field` روی `internal_code` |
| R10 | 10 | `logic_command is conflict` | `hide_field` روی `conflict_target` |
| R11 | 20 | `logic_command is conflict` | `show_field` روی `conflict_target` |
| R12 | 5 | `logic_command is stop` | `hide_field` روی `conflict_target` و `stop_processing=true` |
| R13 | 30 | `logic_command is stop` | `show_field` روی `conflict_target` |
| R14 | 40 | `logic_command is jump` | `jump_to_step` روی Step 3 |
| R15 | 50 | گروه تو در تو: `(customer_type is Company AND budget gte 1000)` با connector **OR** نسبت به `logic_command is vip` | `show_message` روی `budget` |

برای R15 از دکمه «+ Add Group» داخل ادیتور rule استفاده کنید تا یک گروه تو در تو بسازید؛ کاندیشن سوم (`logic_command is vip`) را به‌صورت یک آیتم هم‌سطح با connector جداگانه **OR** به گروه اول وصل کنید (نه AND پیش‌فرض گروه).

ذخیره کنید، builder را ببندید و دوباره باز کنید.

- [X] تمام ruleها باقی مانده‌اند.
- [X] priorityها بدون تغییر ذخیره شده‌اند.
- [X] `stop_processing` برای R12 فعال مانده است.
- [X] actionهای `set_value` و `show_message` مقدار خود را حفظ کرده‌اند.
- [X] گروه تو در تو در R15 بعد از reload باز هم به‌صورت یک گروه (نه flatten‌شده) نمایش داده می‌شود.
- [X] connector بین گروه و کاندیشن سوم در R15 همچنان **OR** است (نه AND پیش‌فرض).
- [X] Console هنگام ذخیره و بازگشایی خطا ندارد.

## 5. سناریوی A — فرم عادی

یک فرم ساده بدون `logic_rules` بسازید و submit کنید.

- [X] required validation عادی همچنان کار می‌کند.
- [X] فرم با داده معتبر submit می‌شود.
- [X] فایل `conditional-logic-efb.js` برای این فرم enqueue نمی‌شود.
- [X] داده فرم عادی توسط runtime شرطی تغییر نمی‌کند.

## 6. سناریوی B — Step مخفی و Server Validation

1. فرم `CL Full Acceptance` را باز کنید.
2. `customer_type = Individual` را انتخاب کنید.
3. بررسی کنید Step 2 غیرفعال/مخفی شده است.
4. `has_budget = no` را انتخاب کنید.
5. `final_note` را پر کنید و submit کنید.

نتیجه مورد انتظار:

- [X] Step 2 در navigation رد می‌شود.
- [X] `company_name` و `company_email` با وجود required بودن خطا نمی‌دهند.
- [X] `budget` مخفی و optional است.
- [X] submit سمت سرور موفق است.
- [X] داده ذخیره‌شده شامل فیلدهای Step 2 و `budget` نیست.

## 7. سناریوی C — Required پویا و Message

1. صفحه فرم را refresh کنید.
2. `customer_type = Company` را انتخاب کنید.
3. `has_budget = yes` را انتخاب کنید.
4. `budget` را خالی بگذارید و Next/Submit را بزنید.
5. سپس مقدار `1500` وارد کنید.

نتیجه مورد انتظار:

- [X] Step 2 قابل مشاهده است.
- [X] `budget` نمایش داده شده و required است.
- [X] با budget خالی، validation frontend مانع ادامه می‌شود.
- [X] با مقدار `1500` پیام inline مربوط به R7 نمایش داده می‌شود.
- [X] `internal_code` برابر `COMPANY` است.
- [X] `internal_code` disabled است.
- [X] submit با تکمیل فیلدهای قابل مشاهده موفق است.
- [X] server مقدار قدیمی یا دستکاری‌شده فیلد disabled را قبول نمی‌کند.

## 8. سناریوی D — پاک‌شدن مقدار قبلی

1. `has_budget = yes` را انتخاب کنید.
2. در `budget` مقدار `2000` وارد کنید.
3. سپس `has_budget = no` را انتخاب کنید.

نتیجه مورد انتظار:

- [X] `budget` مخفی می‌شود.
- [X] مقدار DOM آن پاک می‌شود.
- [X] row مربوط به `budget` از `sendBack_emsFormBuilder_pub` حذف می‌شود.
- [X] submit شامل budget قبلی نیست.

برای بررسی Console:

```javascript
sendBack_emsFormBuilder_pub.filter(row => row && row.id_ === 'budget')
```

خروجی باید آرایه خالی باشد.

## 9. سناریوی E — تعارض، Priority و Stop Processing

### Conflict

1. در `logic_command` مقدار `conflict` را وارد کنید.
2. R10 ابتدا target را hide و R11 بعداً آن را show می‌کند.

- [X] `conflict_target` در پایان نمایش داده می‌شود.
- [X] rule ناموفق یا evaluation بعدی نتیجه R11 را پاک نمی‌کند.

### Stop processing

1. مقدار `logic_command` را به `stop` تغییر دهید.
2. R12 match می‌شود و اجرای ruleهای بعدی را متوقف می‌کند.

- [ ] `conflict_target` مخفی می‌ماند.
- [ ] R13 اجرا نمی‌شود.
- [ ] نتیجه frontend و server یکسان است.
- [ ] (2026-06-26) اگر همین rule هم‌زمان `jump_to_step` دارد: بعد از تایپ `stop` و کلیک Next، دکمه Previous **نباید** ناخواسته مخفی شود (باگ H12 — رفع شد در `public/assets/js/core-efb.js`؛ علت: کلیک Next مقدار step را قبل از validation کش می‌کرد و jump رخ‌داده در حین validation را نادیده می‌گرفت، در نتیجه step را دوبار جلو می‌برد و فرم را زودهنگام «تمام‌شده» در نظر می‌گرفت).
- [ ] فرم بعد از jump دقیقاً روی step مقصد بماند (نه یک step جلوتر/عقب‌تر)، و progress bar/عنوان step درست باشد.
- [ ] (2026-06-26 — H13) اگر مقصد jump یک step با فیلد required خالی باشد، کلیک Next/Submit باید واقعاً block شود (نه اینکه submit ناقص انجام شود). این ریشه‌ی عمیق‌تر همان باگ Previous بود: تابع `validate()` در `public/assets/js/conditional-logic-efb.js` step قدیمی (قبل از jump) را چک می‌کرد، نه step واقعی بعد از jump — یعنی required-fieldهای step واقعی اصلاً دیده نمی‌شدند. رفع شد؛ تست خودکار: `tests/test-conditional-logic-validate-step.js`.
- [ ] (2026-06-26 — H14، علت سوم) حتی اگر jump بدون هیچ کلیکی رخ دهد (مثلاً فقط با تایپ یک مقدار، بدون زدن Next)، دکمه Previous باید بر اساس step مقصد به‌درستی نمایش/مخفی شود. علت این بود که `jumpToStep()` در runtime عمومی هیچ‌وقت `#prev_efb` را مدیریت نمی‌کرد — فقط کلیک دستی Next/Previous این کار را می‌کرد. رفع شد: حالا `jumpToStep()` خودش این دکمه را مدیریت می‌کند، مستقل از این‌که jump از کجا trigger شده باشد.
- [ ] (2026-06-26 — H15، علت چهارم) روی کلیک دکمه Previous (مخصوصاً در صفحه خطای نهایی validation، مثل وقتی فیلد required خالی مانده) هیچ خطایی در Console نباید باشد و واقعاً باید به step قبلی برگردد. علت قبلی: `onclick="logic_fun_prev_send(form_id)"` به تابعی اشاره می‌کرد که در هیچ‌جای کدبیس تعریف نشده بود (`Uncaught ReferenceError`) — فقط روی فرم‌های conditional رخ می‌داد، فرم‌های عادی از قبل درست بودند. رفع شد در `public/assets/js/core-efb.js` (هر سه محل، با استفاده یکدست از `fun_prev_send`).
- [ ] (2026-06-26 — H16، علت پنجم) دقیقاً همان صفحه خطا (مثلاً وقتی سرور می‌گوید «Please enter valid value for the Customer type field») را تست کنید: کلیک Previous باید **بدون خطای جدید در Console** کار کند و کاربر را دقیقاً به step فیلد «Customer type» برگرداند (نه صرفاً یک step عقب‌تر از جایی نامعلوم). علت: `fun_prev_send()` فرض می‌کند `dataset.currentstep` همیشه یک step واقعی است، اما درست قبل از ارسال submit نهایی، این مقدار به `max_step + 1` تنظیم می‌شود (یک step که اصلاً وجود ندارد) — این باگ **هم روی فرم عادی و هم conditional** بود. رفع شد با تابع امن جدید `efb_go_to_step_direct()` که از `field_id` پاسخ سرور برای رفتن دقیق به step درست استفاده می‌کند.
- [ ] (اصلاحیه H16) بعد از برگشت به step میانی، لیبل دکمه Next/Submit باید دقیقاً «Next» باشد، نه «Submit». علت قبلی: تابع جدید به‌اشتباه فرض کرد دو دکمه‌ی جدا برای Next و Submit وجود دارد؛ در حالی‌که معمولاً یک دکمه است که فقط متنش عوض می‌شود. رفع شد با فراخوانی `updateStepButtonState_efb(form_id)`.

## 10. سناریوی F — Jump

1. فرم را در Step 1 باز کنید.
2. در `logic_command` مقدار `jump` را وارد کنید.

- [X] فرم فقط یک بار به Step 3 می‌رود.
- [X] progress bar و عنوان step به‌روز می‌شوند.
- [X] jump باعث loop یا چند بار navigation نمی‌شود.
- [X] دکمه‌های Previous/Next/Submit وضعیت درست دارند.

## 11. سناریوی G — چند فرم در یک صفحه

1. دو فرم conditional متفاوت را در یک صفحه قرار دهید.
2. در فرم اول condition مربوط به hide/show را فعال کنید.

- [X] فقط target فرم اول تغییر می‌کند.
- [X] state، message، step و sendBack فرم دوم دست‌نخورده می‌مانند.
- [X] submit هر فرم فقط rowهای `form_id` خودش را ارسال می‌کند.
- [X] Console خطای duplicate ID یا global state ندارد.

## 12. سناریوی H — Addon Gating

1. `AdnSMF` را غیرفعال کنید.
2. builder و صفحه منتشرشده را reload کنید.

- [ ] دکمه Conditional Logic در builder نمایش داده نمی‌شود.
- [ ] asset ادمین conditional logic بارگذاری نمی‌شود.
- [ ] `public/assets/js/conditional-logic-efb.js` بارگذاری نمی‌شود.
- [ ] ruleهای ذخیره‌شده حذف نمی‌شوند.
- [ ] فرم مانند فرم عادی کار می‌کند.

سپس addon را دوباره فعال کنید:

- [ ] ruleهای قبلی دوباره در builder وجود دارند.
- [ ] runtime و server validator دوباره فعال می‌شوند.

## 13. سناریوی I — Payment Operators

در یک فرم payment، حداقل این ruleها را تست کنید:

- `is_paid`
- `is_not_paid`
- `amount_eq`
- `amount_gt`
- `amount_lt`

- [ ] operatorها بعد از save/reload باقی می‌مانند.
- [ ] نتیجه موفق/ناموفق پرداخت درست تشخیص داده می‌شود.
- [ ] مقایسه amount با مقدار واقعی پرداخت انجام می‌شود.
- [ ] تغییر داده در مرورگر نمی‌تواند server result را دور بزند.

## 14. سناریوی J — Legacy Compatibility

یک فرم قدیمی دارای `conditions` و بدون `logic_rules` باز کنید.

- [ ] show/hide قدیمی همچنان کار می‌کند.
- [ ] submit server با وضعیت نمایش frontend هماهنگ است.
- [ ] ذخیره مجدد فرم داده legacy را خراب نمی‌کند.

## 15. سناریوی K — Nested Condition Groups و Connector ترکیبی AND/OR

این سناریو رول R15 ساخته‌شده در بخش 4 را تست می‌کند: `(customer_type is Company AND budget gte 1000) OR (logic_command is vip)` → `show_message` روی `budget`.

1. صفحه فرم را refresh کنید.
2. `customer_type = Company` و `budget = 1500` را تنظیم کنید (شاخه اول گروه باید true باشد).
3. بررسی کنید پیام inline مربوط به R15 روی `budget` نمایش داده می‌شود.
4. `budget` را به `500` تغییر دهید (شاخه اول false می‌شود) و `logic_command` را خالی نگه دارید.
5. بررسی کنید پیام دیگر نمایش داده نمی‌شود.
6. `logic_command = vip` را وارد کنید (شاخه دوم، متصل با connector **OR**، باید true شود) درحالی‌که `budget` همچنان `500` است.
7. بررسی کنید پیام دوباره نمایش داده می‌شود — یعنی connector مستقل OR باعث true شدن کل گروه شده، نه عملگر AND پیش‌فرض گروه داخلی.

نتیجه مورد انتظار:

- [ ] حالت 2 (هر دو شرط گروه AND برقرار) → پیام نمایش داده می‌شود.
- [ ] حالت 4 (یکی از شروط گروه AND نادرست) و logic_command خالی → پیام نمایش داده نمی‌شود.
- [ ] حالت 6 (شرط مستقل با connector OR برقرار، گروه AND نادرست) → پیام دوباره نمایش داده می‌شود.
- [ ] رفتار frontend (runtime) و نتیجه ذخیره‌شده پس از submit با هم هماهنگ‌اند.
- [ ] هیچ خطای Console در حین تغییر مقادیر دیده نمی‌شود.

## 16. سناریوی L — Operators عددی (gte, lte, between, not_between)

1. در builder، یک rule جدید با شرط روی فیلد `budget` بسازید.
2. عملگر را روی `between` بگذارید و بررسی کنید دو ورودی Min/Max به‌جای یک ورودی ساده نمایش داده می‌شود.
3. Min = `500`، Max = `2000` وارد کنید؛ Action: `show_field` روی `conflict_target`.
4. فیلد را به `text` (مثلاً `logic_command`) تغییر دهید و بررسی کنید عملگرهای عددی (`gte`, `lte`, `between`, `not_between`) از لیست عملگرها حذف می‌شوند؛ سپس دوباره فیلد را به `budget` برگردانید.
5. ذخیره کنید و فرم منتشرشده را باز کنید.

نتیجه مورد انتظار (ارزیابی `between` روی فرم منتشرشده):

- [ ] `budget = 1000` (داخل بازه) → `conflict_target` نمایش داده می‌شود.
- [ ] `budget = 500` یا `budget = 2000` (روی مرز بازه) → `conflict_target` نمایش داده می‌شود (شامل مرزها).
- [ ] `budget = 3000` (خارج از بازه) → `conflict_target` مخفی است.
- [ ] `budget` خالی یا غیرعددی → شرط هرگز true نمی‌شود.

سپس عملگر را به `not_between` تغییر دهید و دوباره تست کنید:

- [ ] `budget = 1000` (داخل بازه قبلی) → اکنون `conflict_target` مخفی است.
- [ ] `budget = 3000` (خارج از بازه) → اکنون `conflict_target` نمایش داده می‌شود.

در پایان، یک بار سعی کنید rule را با Min پر و Max خالی ذخیره کنید:

- [ ] پیام هشدار اعتبارسنجی نمایش داده می‌شود و rule ذخیره نمی‌شود.

## 17. سناریوی M — Conditional Notification و Confirmation

این سناریو Phase 3 را تست می‌کند و باید ثابت کند که:

- ایمیل معمولی فرم هنوز مثل قبل ارسال می‌شود.
- ایمیل شرطی فقط وقتی شرط خودش برقرار است ارسال می‌شود.
- confirmation شرطی می‌تواند پیام تشکر یا redirect را override کند.
- `logic_rules`، `notification_rules` و `confirmation_rules` جدا از هم ذخیره می‌شوند.

### 17.1 آماده‌سازی ایمیل و فرم

1. در تنظیمات اصلی افزونه، ارسال ایمیل/SMTP را مثل حالت عادی فعال کنید.
2. یک ابزار mail log فعال کنید و قبل از شروع تست، log را خالی کنید.
3. در فرم `CL Full Acceptance` مطمئن شوید email notification معمولی فرم فعال است.
4. برای ایمیل معمولی فرم، recipient پیش‌فرض را مثلا `admin@example.com` تنظیم کنید.
5. در صورت وجود subject پیش‌فرض، مقدار قابل تشخیص بگذارید:

```text
Default form notification [confirmation_code]
```

نتیجه مورد انتظار:

- [ ] فرم بدون rule شرطی هم می‌تواند ایمیل معمولی به `admin@example.com` بفرستد.
- [ ] mail log قبل از تست اصلی خالی است.
- [ ] هیچ rule شرطی هنوز ساخته نشده یا اگر ساخته شده، برای این سناریو قابل تشخیص است.

### 17.2 ساخت Notification Rules در Builder

در modal Conditional Logic، تب `Notifications` را باز کنید و ruleهای زیر را بسازید.

| Rule | Priority | شرط | Recipient | Subject |
|---|---:|---|---|---|
| NR1 | 10 | `customer_type is Company` | `sales@example.com` | `Sales lead [confirmation_code]` |
| NR2 | 20 | `has_budget is yes AND budget gt 1000` | `vip@example.com` | `VIP lead [confirmation_code]` |
| NR3 | 30 | `logic_command is support` | `support@example.com` | `Support request [confirmation_code]` |

برای NR2:

1. یک condition اول روی `has_budget is yes` بسازید.
2. condition دوم را با connector `AND` روی `budget gt 1000` بسازید.
3. priority را `20` قرار دهید.

تست اعتبارسنجی UI:

1. یک notification rule جدید بسازید.
2. شرط را کامل کنید اما recipient را خالی بگذارید.
3. روی Save بزنید.

نتیجه مورد انتظار:

- [ ] rule ناقص ذخیره نمی‌شود.
- [ ] پیام هشدار اعتبارسنجی نمایش داده می‌شود.
- [ ] بعد از وارد کردن ایمیل معتبر، rule ذخیره می‌شود.

### 17.3 ساخت Confirmation Rules در Builder

در همان modal، تب `Confirmation` را باز کنید و ruleهای زیر را بسازید.

| Rule | Priority | شرط | Action | مقدار |
|---|---:|---|---|---|
| CR1 | 5 | `has_budget is yes AND budget gt 1000` | Redirect | `https://example.com/vip-thanks` |
| CR2 | 20 | `logic_command is support` | Message | `درخواست پشتیبانی شما ثبت شد.` |
| CR3 | 30 | `customer_type is Individual` | Message | `فرم شخص حقیقی با موفقیت ثبت شد.` |

تست اعتبارسنجی UI:

1. یک confirmation rule جدید بسازید.
2. action را `Redirect` بگذارید اما URL را خالی بگذارید.
3. روی Save بزنید.
4. سپس URL معتبر وارد کنید و دوباره Save کنید.

نتیجه مورد انتظار:

- [ ] redirect rule بدون URL ذخیره نمی‌شود.
- [ ] message rule بدون متن پیام ذخیره نمی‌شود.
- [ ] بعد از تکمیل مقدار لازم، rule ذخیره می‌شود.

### 17.4 Save/Reload و جداسازی داده‌ها

1. فرم را ذخیره کنید.
2. صفحه builder را refresh کنید.
3. دوباره modal Conditional Logic را باز کنید.
4. هر سه تب را بررسی کنید.

نتیجه مورد انتظار:

- [ ] تب `Fields` همان `logic_rules` قبلی R1 تا R15 را نشان می‌دهد.
- [ ] تب `Notifications` فقط NR1 تا NR3 را نشان می‌دهد.
- [ ] تب `Confirmation` فقط CR1 تا CR3 را نشان می‌دهد.
- [ ] ساخت یا ویرایش notification rule باعث حذف یا تغییر field logic نمی‌شود.
- [ ] ساخت یا ویرایش confirmation rule باعث حذف یا تغییر field logic نمی‌شود.
- [ ] مقدار `valj_efb[0].logic_rules` فقط ruleهای field/step را دارد.
- [ ] مقدار `valj_efb[0].notification_rules` فقط ruleهای ایمیل شرطی را دارد.
- [ ] مقدار `valj_efb[0].confirmation_rules` فقط ruleهای confirmation را دارد.

برای بررسی Console:

```javascript
valj_efb[0].logic_rules
valj_efb[0].notification_rules
valj_efb[0].confirmation_rules
```

### 17.5 Submit مسیر Company با بودجه VIP

فرم منتشرشده را باز کنید و مقادیر زیر را وارد کنید:

| Field | مقدار |
|---|---|
| `customer_type` | `Company` |
| `has_budget` | `yes` |
| `budget` | `1500` |
| `company_name` | `ACME Test` |
| `company_email` | `company@example.com` |
| `final_note` | `VIP test` |
| `logic_command` | خالی |

Submit کنید.

نتیجه مورد انتظار frontend:

- [ ] فرم submit موفق دارد.
- [ ] کاربر به `https://example.com/vip-thanks` redirect می‌شود یا پیام redirect همراه لینک نمایش داده می‌شود.
- [ ] پیام thank-you پیش‌فرض نمایش داده نمی‌شود، چون CR1 match شده است.
- [ ] در Console خطای JavaScript وجود ندارد.

نتیجه مورد انتظار email/mail log:

- [ ] ایمیل معمولی فرم به `admin@example.com` ارسال شده است.
- [ ] ایمیل شرطی NR1 به `sales@example.com` ارسال شده است.
- [ ] ایمیل شرطی NR2 به `vip@example.com` ارسال شده است.
- [ ] ایمیل NR3 به `support@example.com` ارسال نشده است.
- [ ] subject ایمیل‌های شرطی شامل confirmation code جایگزین‌شده یا همان الگوی قابل تشخیص است.
- [ ] محتوای ایمیل شرطی از template/content فعلی فرم استفاده می‌کند و submission data را دارد.

### 17.6 Submit مسیر Company غیر VIP

mail log را خالی کنید، فرم را refresh کنید و مقادیر زیر را وارد کنید:

| Field | مقدار |
|---|---|
| `customer_type` | `Company` |
| `has_budget` | `yes` |
| `budget` | `500` |
| `company_name` | `Small Co` |
| `company_email` | `small@example.com` |
| `final_note` | `Non VIP test` |
| `logic_command` | خالی |

Submit کنید.

نتیجه مورد انتظار:

- [ ] فرم submit موفق دارد.
- [ ] redirect مربوط به CR1 انجام نمی‌شود.
- [ ] اگر هیچ confirmation rule دیگری match نشود، thank-you پیش‌فرض فرم نمایش داده می‌شود.
- [ ] ایمیل معمولی فرم به `admin@example.com` ارسال می‌شود.
- [ ] NR1 به `sales@example.com` ارسال می‌شود.
- [ ] NR2 به `vip@example.com` ارسال نمی‌شود.
- [ ] NR3 به `support@example.com` ارسال نمی‌شود.

### 17.7 Submit مسیر Support

mail log را خالی کنید، فرم را refresh کنید و مقادیر زیر را وارد کنید:

| Field | مقدار |
|---|---|
| `customer_type` | `Individual` |
| `has_budget` | `no` |
| `final_note` | `Support test` |
| `logic_command` | `support` |

Submit کنید.

نتیجه مورد انتظار:

- [ ] Step 2 مخفی/رد می‌شود و requiredهای Step 2 مانع submit نمی‌شوند.
- [ ] فرم submit موفق دارد.
- [ ] پیام CR2 نمایش داده می‌شود: `درخواست پشتیبانی شما ثبت شد.`
- [ ] redirect انجام نمی‌شود.
- [ ] ایمیل معمولی فرم به `admin@example.com` ارسال می‌شود.
- [ ] NR3 به `support@example.com` ارسال می‌شود.
- [ ] NR1 به `sales@example.com` ارسال نمی‌شود.
- [ ] NR2 به `vip@example.com` ارسال نمی‌شود.

### 17.8 Submit مسیر Individual بدون support

mail log را خالی کنید، فرم را refresh کنید و مقادیر زیر را وارد کنید:

| Field | مقدار |
|---|---|
| `customer_type` | `Individual` |
| `has_budget` | `no` |
| `final_note` | `Individual test` |
| `logic_command` | خالی |

Submit کنید.

نتیجه مورد انتظار:

- [ ] پیام CR3 نمایش داده می‌شود: `فرم شخص حقیقی با موفقیت ثبت شد.`
- [ ] ایمیل معمولی فرم به `admin@example.com` ارسال می‌شود.
- [ ] هیچ ایمیل شرطی به `sales@example.com`، `vip@example.com` یا `support@example.com` ارسال نمی‌شود.
- [ ] داده‌های Step 2 و `budget` در submission ذخیره‌شده وجود ندارند.

### 17.9 تست عدم تداخل با ایمیل معمولی

1. تمام `notification_rules` را موقتاً disable کنید.
2. فرم را ذخیره کنید.
3. یک submit معتبر انجام دهید.

نتیجه مورد انتظار:

- [ ] ایمیل معمولی فرم همچنان ارسال می‌شود.
- [ ] هیچ ایمیل شرطی ارسال نمی‌شود.
- [ ] submit و thank-you/redirect پیش‌فرض فرم خراب نمی‌شود.

سپس:

1. email notification معمولی فرم را از تنظیمات فرم/افزونه غیرفعال کنید.
2. `notification_rules` را فعال نگه دارید.
3. یک submit انجام دهید.

نتیجه مورد انتظار:

- [ ] اگر مسیر قدیمی ایمیل فرم طبق تنظیمات غیرفعال است، ایمیل معمولی ارسال نمی‌شود.
- [ ] ایمیل شرطی هم نباید مسیر قدیمی را دور بزند و بی‌هوا ارسال شود.
- [ ] submit فرم همچنان موفق است و فقط ارسال ایمیل انجام نمی‌شود.

### 17.10 تست امنیت و sanitize

در builder مقدارهای زیر را امتحان کنید:

- Recipient نامعتبر: `not-an-email`
- Subject شامل HTML/script: `<script>alert(1)</script> Sales`
- Confirmation URL نامعتبر یا JavaScript-like: `javascript:alert(1)`
- Confirmation message شامل script:

```html
<strong>OK</strong><script>alert(1)</script>
```

نتیجه مورد انتظار:

- [ ] recipient نامعتبر ذخیره یا اجرا نمی‌شود.
- [ ] subject به متن امن تبدیل می‌شود.
- [ ] URL نامعتبر ذخیره/اجرا نمی‌شود یا به URL امن sanitize می‌شود.
- [ ] script از message حذف می‌شود اما HTML مجاز مثل `<strong>` باقی می‌ماند.
- [ ] در frontend هیچ script تزریق‌شده‌ای اجرا نمی‌شود.
- [ ] در `debug.log` خطای جدید وجود ندارد.

## 18. بررسی نهایی فنی

در پایان همه سناریوها:

- [ ] Console بدون JavaScript error است.
- [ ] Network بدون AJAX 4xx/5xx غیرمنتظره است.
- [ ] `wp-content/debug.log` خطای جدید ندارد.
- [ ] hidden/disabled/hidden-step values در submission ذخیره نشده‌اند.
- [ ] required visible fields هم در frontend و هم server enforce شده‌اند.
- [ ] notification_rules فقط پس از submit موفق اجرا می‌شوند، نه قبل از ذخیره submission.
- [ ] confirmation_rules فقط response نهایی همان submit را تغییر می‌دهند و روی فرم‌های دیگر اثر ندارند.
- [ ] form templates و فرم‌های معمولی regression ندارند.
- [ ] RTL و mobile layout قابل استفاده است.

## 19. گزارش نتیجه

| بخش | نتیجه | توضیح |
|---|---|---|
| Automated tests | ⬜ Pass / ⬜ Fail | |
| Builder save/reload | ⬜ Pass / ⬜ Fail | |
| Runtime actions | ⬜ Pass / ⬜ Fail | |
| Frontend validation | ⬜ Pass / ⬜ Fail | |
| Server validation | ⬜ Pass / ⬜ Fail | |
| Multi-form | ⬜ Pass / ⬜ Fail | |
| Addon gating | ⬜ Pass / ⬜ Fail | |
| Payment | ⬜ Pass / ⬜ Fail | |
| Legacy forms | ⬜ Pass / ⬜ Fail | |
| Nested groups / connector (Scenario K) | ⬜ Pass / ⬜ Fail | |
| Numeric operators gte/lte/between (Scenario L) | ⬜ Pass / ⬜ Fail | |
| Conditional notification / confirmation (Scenario M) | ⬜ Pass / ⬜ Fail | |
| Regression | ⬜ Pass / ⬜ Fail | |

**معیار تأیید نهایی:** تمام ردیف‌ها Pass باشند و هیچ خطای Critical/High باز باقی نماند.
