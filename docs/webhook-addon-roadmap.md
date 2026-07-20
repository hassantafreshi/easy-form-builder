# نقشه راه افزودنی Webhook و Workflow برای Easy Form Builder

## هدف محصول

افزودنی Webhook نباید فقط یک «ارسال کننده ساده درخواست HTTP» باشد. هدف این افزودنی ساخت یک موتور مدرن برای اتصال فرم ها به APIها، CRMها، سرویس های اتوماسیون، سیستم های داخلی، پنل های پشتیبانی، پاسخ های ادمین و جریان های کاری چندمرحله ای است.

در نسخه رقابتی و آینده نگر، Webhook در Easy Form Builder باید این نقش را داشته باشد:

```text
Form Event -> Workflow Engine -> Conditions -> Transform -> Delivery -> Response Handling -> Logs/Retry/Automation
```

یعنی محصول باید هم برای کاربر ساده قابل استفاده باشد و هم برای کاربر حرفه ای و تیم های فنی محدودیت ایجاد نکند.

## برداشت از رقبا و نیاز واقعی کاربران

در Gravity Forms، WPForms، Formidable Forms، Fluent Forms و Ninja Forms، مفهوم اصلی Webhook معمولا ارسال داده های فرم به یک URL خارجی بعد از submit است. قابلیت های رایج شامل URL مقصد، متد HTTP، ارسال JSON یا form-data، mapping فیلدها، headerهای سفارشی، شرط اجرای ساده، و گاهی لاگ یا retry محدود است.

اما ضعف های تکراری در بسیاری از فرم سازها این هاست:

- ساختار JSON پیچیده و nested در UI سخت یا غیرممکن است.
- mapping معمولا ساده است و transform داده واقعی ندارد.
- response API معمولا فقط ذخیره یا دیده می شود و وارد منطق فرم نمی شود.
- API chaining و workflow چندمرحله ای وجود ندارد یا بسیار محدود است.
- debug console واقعی برای دیدن request، response، headers و timeline وجود ندارد.
- تست بدون submit واقعی، mock payload و simulation تجربه خوبی ندارد.
- queue، retry، failover و مانیتورینگ برای سایت های بزرگ کامل نیست.
- کاربر غیر فنی برای اتصال به APIهای واقعی هنوز نیازمند توسعه دهنده است.

بنابراین فرصت اصلی Easy Form Builder این است:

```text
Webhook = Workflow Engine
```

نه فقط:

```text
Webhook = Send POST request
```

## اصول طراحی 2026

- UX ساده برای شروع سریع، ولی عمق حرفه ای برای APIهای پیچیده.
- طراحی event-based به جای submit-based.
- ارسال async و قابل اعتماد، بدون کند کردن تجربه ثبت فرم.
- لاگ کامل، replay، retry و عیب یابی قابل فهم.
- امنیت پیش فرض با HMAC SHA-256، timestamp و secret rotation.
- payload نسخه دار و قابل مهاجرت.
- پشتیبانی از nested JSON، array، object، transform و expression.
- قابلیت توسعه از طریق hooks، REST API، WP-CLI و SDK داخلی.
- آماده برای پنل مستقل Automation/Webhook Center در آینده.
- سازگار با RTL، فارسی، دسترسی پذیری و تجربه کاربری وردپرس.

## مخاطبان اصلی

### کاربر ساده

می خواهد فرم را به Slack، Telegram، Google Sheets، Zapier، Make یا یک CRM وصل کند، بدون اینکه JSON و headerها را عمیق بداند.

### کاربر حرفه ای

می خواهد payload سفارشی، nested JSON، شرط های چندگانه، route، response handling، retry و debug کامل داشته باشد.

### تیم فنی و SaaS

می خواهد workflow چندمرحله ای بسازد، response مرحله اول را در مرحله دوم استفاده کند، secret را rotate کند، delivery logs را export بگیرد و همه چیز قابل مشاهده و قابل کنترل باشد.

### مدیر سایت و پشتیبانی

می خواهد بداند چرا ارسال به CRM شکست خورده، چه داده ای ارسال شده، response چه بوده، و بتواند بدون ورود به کد retry یا replay انجام دهد.

## تعریف Eventها

افزودنی باید از ابتدا با event catalog طراحی شود. هر رویداد یک type، payload version، source، subject، timestamp و delivery id داشته باشد.

### Eventهای فرم و submission

- `form.submission.created`
- `form.submission.updated`
- `form.submission.deleted`
- `form.submission.spam_detected`
- `form.submission.restored`
- `form.submission.status_changed`
- `form.submission.note_added`
- `form.submission.exported`

### Eventهای پرداخت و سفارش

- `form.payment.started`
- `form.payment.succeeded`
- `form.payment.failed`
- `form.payment.refunded`
- `form.payment.canceled`
- `form.order.created`
- `form.order.updated`

### Eventهای فایل

- `form.file.uploaded`
- `form.file.deleted`
- `form.file.scan_passed`
- `form.file.scan_failed`

### Eventهای کاربر

- `form.user.registered`
- `form.user.logged_in_before_submit`
- `form.user.profile_updated_by_form`

### Eventهای Quiz و محاسبات

- `form.quiz.completed`
- `form.quiz.passed`
- `form.quiz.failed`
- `form.calculation.completed`
- `form.score.changed`

### Eventهای response ادمین

این بخش باید جداگانه و رسمی در event catalog باشد، چون بسیاری از فرم ها فقط submission نیستند و بعدا توسط مدیر، پشتیبان یا اپراتور پاسخ داده می شوند.

- `admin.response.created`
- `admin.response.updated`
- `admin.response.deleted`
- `admin.response.published`
- `admin.response.sent_to_user`
- `admin.response.internal_note_added`
- `admin.response.status_changed`
- `admin.response.assigned`
- `admin.response.resolved`
- `admin.response.reopened`

کاربردها:

- وقتی ادمین به یک entry پاسخ داد، پیام به CRM یا helpdesk ارسال شود.
- اگر پاسخ ادمین status را به resolved تغییر داد، webhook به Slack یا Telegram برود.
- اگر ادمین پاسخ داخلی ثبت کرد، فقط به سیستم داخلی ارسال شود و به کاربر نمایش داده نشود.
- اگر پاسخ ادمین نیازمند تایید بود، بعد از publish شدن webhook اجرا شود، نه هنگام draft.

### Eventهای Response Box

Response Box باید به عنوان یک سطح مستقل در طراحی دیده شود، نه فقط یک field ساده. این بخش می تواند محل پیام، نتیجه API، پاسخ ادمین، وضعیت پیگیری، یا پیام dynamic بعد از ارسال فرم باشد.

- `response_box.created`
- `response_box.updated`
- `response_box.displayed`
- `response_box.clicked`
- `response_box.closed`
- `response_box.action_clicked`
- `response_box.dynamic_message_generated`
- `response_box.api_response_bound`
- `response_box.user_replied`
- `response_box.admin_replied`
- `response_box.status_changed`

کاربردها:

- اگر API خارجی success داد، response box پیام سفارشی نشان دهد.
- اگر API خارجی error داد، response box پیام خطای قابل فهم نشان دهد.
- اگر response box توسط کاربر دیده شد، یک webhook tracking ارسال شود.
- اگر کاربر روی action داخل response box کلیک کرد، event جداگانه اجرا شود.
- اگر ادمین از داخل response box پاسخ داد، eventهای admin response نیز trigger شوند.

## مدل Payload پیشنهادی

### Payload پایه

```json
{
  "id": "evt_01J...",
  "type": "form.submission.created",
  "source": "easy-form-builder",
  "site": {
    "url": "https://example.com",
    "timezone": "Asia/Qatar"
  },
  "form": {
    "id": 12,
    "title": "Contact Form"
  },
  "submission": {
    "id": 345,
    "created_at": "2026-07-10T12:00:00+03:00",
    "fields": {}
  },
  "meta": {
    "payload_version": "2026-01",
    "delivery_id": "del_01J...",
    "attempt": 1
  }
}
```

### Payload برای admin response

```json
{
  "id": "evt_01J...",
  "type": "admin.response.created",
  "form": {
    "id": 12,
    "title": "Support Form"
  },
  "submission": {
    "id": 345
  },
  "admin_response": {
    "id": 89,
    "status": "published",
    "visibility": "public",
    "message": "Your request has been reviewed.",
    "created_by": {
      "id": 1,
      "display_name": "Admin"
    },
    "created_at": "2026-07-10T12:30:00+03:00"
  },
  "meta": {
    "payload_version": "2026-01"
  }
}
```

### Payload برای response box

```json
{
  "id": "evt_01J...",
  "type": "response_box.action_clicked",
  "form": {
    "id": 12
  },
  "submission": {
    "id": 345
  },
  "response_box": {
    "id": "rbx_confirmation",
    "state": "visible",
    "message_type": "success",
    "action": "open_ticket",
    "bound_api_response": {
      "webhook_id": 7,
      "path": "$.ticket.id",
      "value": "TCK-1024"
    }
  },
  "meta": {
    "payload_version": "2026-01"
  }
}
```

## فاز 1: MVP قابل عرضه

### قابلیت های اصلی

- ساخت چند webhook برای هر فرم.
- فعال/غیرفعال کردن هر webhook.
- انتخاب trigger از event catalog.
- URL مقصد با پشتیبانی از merge tags.
- متدهای HTTP: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`.
- فرمت body: `JSON`, `form-data`, `x-www-form-urlencoded`, `raw`.
- headerهای سفارشی.
- Basic Auth، Bearer Token و API Key.
- ارسال همه فیلدها یا فیلدهای انتخابی.
- شرط اجرای ساده.
- تست webhook با داده نمونه.
- preview برای URL، headers و body.
- لاگ پایه برای success و failed.

### UX فاز 1

- wizard سه مرحله ای: مقصد، داده، تست.
- حالت ساده برای کاربران غیر فنی.
- حالت Advanced برای header، auth و body.
- دکمه `Send Test`.
- پیام خطای قابل فهم.
- نمایش آخرین وضعیت هر webhook در لیست فرم.

## فاز 2: Smart Webhook Builder

### JSON Builder

- ساخت visual JSON با object، array و nested object.
- drag/drop فیلدهای فرم داخل ساختار JSON.
- Code Editor برای کاربران حرفه ای.
- اعتبارسنجی JSON در لحظه.
- sample payload قابل کپی.
- امکان import از نمونه JSON.
- امکان import از OpenAPI schema در آینده.

### Transform داده

- ترکیب چند فیلد، مثل نام و نام خانوادگی.
- تبدیل حروف: lowercase، uppercase، title case.
- format تاریخ و زمان.
- تبدیل عدد، boolean، null و empty string.
- split و join برای متن ها.
- map value، مثلا `yes` به `true`.
- حذف فیلدهای خالی از payload.
- ساخت مقدار fallback.

### Expression سبک

نمونه ها:

```text
{{first_name}} + " " + {{last_name}}
lowercase({{email}})
if({{country}} == "Iran", "IR", "GLOBAL")
```

هدف این نیست که کاربر کد خطرناک JavaScript اجرا کند؛ هدف ساخت expression امن، محدود و قابل کنترل است.

## فاز 3: Conditional Routing و Workflow

### Routing

- if / else برای انتخاب webhook.
- اجرای چند webhook همزمان.
- route بر اساس مقدار فیلد، نقش کاربر، مبلغ پرداخت، country، language، score یا source page.
- route بر اساس response وبهوک قبلی.

### Workflow چندمرحله ای

نمونه:

```text
1. Send lead to CRM
2. Read crm_contact_id from response
3. Send notification to Slack with crm_contact_id
4. Show dynamic message in response box
```

### Nodeهای پیشنهادی Workflow

- Trigger
- Condition
- Filter
- Router
- HTTP Request
- Transform
- Delay
- Save Response
- Update Entry
- Update Response Box
- Send Email
- Admin Response Action
- Stop Workflow

## فاز 4: Response Handling

این فاز یکی از مهم ترین تفاوت ها با رقباست. بسیاری از سیستم ها فقط request را ارسال می کنند، اما پاسخ API را وارد تجربه فرم نمی کنند.

### ذخیره response

- ذخیره response body.
- ذخیره response headers.
- ذخیره status code.
- ذخیره timing.
- mask داده حساس.
- تعیین retention.

### استفاده از response در فرم

- نمایش پیام موفقیت dynamic.
- نمایش پیام خطا بر اساس response.
- redirect به URL برگشتی از API.
- ذخیره مقدار از response در hidden field یا entry meta.
- تغییر status submission بر اساس response.
- ساخت admin response از روی response API.
- پر کردن response box با داده API.

### قوانین response

نمونه:

```text
If status_code is 200 and $.success is true:
  show response box success message
  save $.ticket_id as entry meta

If status_code is 400:
  show validation message from $.message

If status_code is 500:
  keep submission pending
  retry delivery
```

## فاز 5: Admin Response و Response Box Automation

### Admin Response Automation

افزودنی باید بتواند پاسخ های ادمین را به عنوان رویداد و action مدیریت کند.

قابلیت ها:

- ساخت webhook هنگام ایجاد پاسخ ادمین.
- ارسال فقط پاسخ public یا فقط internal.
- اجرای شرط بر اساس وضعیت پاسخ.
- ارسال پاسخ ادمین به helpdesk، CRM، Slack یا ایمیل سازمانی.
- ایجاد admin response خودکار از response یک API.
- اتصال پاسخ ادمین به workflow.
- ثبت لاگ اینکه کدام پاسخ ادمین باعث کدام delivery شده است.

### Response Box Automation

Response Box باید بتواند به webhook و response handling متصل شود.

قابلیت ها:

- bind کردن مقدار response API به متن response box.
- انتخاب template پیام موفق، خطا، pending و manual review.
- رویداد برای نمایش، کلیک، بسته شدن و reply.
- ارسال webhook وقتی کاربر داخل response box واکنش نشان می دهد.
- تغییر state response box از workflow.
- ذخیره تاریخچه پیام های response box.

### سناریوهای نمونه

```text
Scenario A:
Submit form -> API creates ticket -> response box shows ticket number -> admin response gets linked to ticket.

Scenario B:
Admin replies -> webhook sends reply to CRM -> response box updates for user.

Scenario C:
API returns "manual_review" -> submission status changes -> response box shows pending message -> admin is notified.
```

## فاز 6: Queue، Retry و Reliability

### Queue

- ارسال async با Action Scheduler یا جدول اختصاصی.
- جلوگیری از کند شدن submit.
- کنترل concurrency.
- امکان pause/resume queue.
- اولویت بندی deliveryها.

### Retry Policy

- retry خودکار با exponential backoff.
- تعداد تلاش قابل تنظیم.
- timeout قابل تنظیم.
- retry بر اساس status code.
- عدم retry برای خطاهای قطعی مثل 404 یا 410، اگر کاربر انتخاب کند.
- retry برای 408، 429، 500، 502، 503، 504.
- fallback URL.
- dead letter queue برای deliveryهای شکست خورده.

### Manual Replay

- replay یک delivery.
- bulk retry.
- replay با payload قبلی.
- replay با payload جدید ساخته شده از submission فعلی.
- replay با ویرایش دستی payload برای تست.

## فاز 7: Debug Console

Debug Console باید شبیه DevTools برای webhook باشد.

### اطلاعات هر delivery

- Event type.
- Webhook feed.
- Trigger source.
- Request URL.
- Request method.
- Request headers.
- Request body.
- Response status.
- Response headers.
- Response body.
- Duration.
- Attempt count.
- Retry schedule.
- Error stack یا خطای قابل فهم.
- Timeline کامل.

### فیلترها

- form.
- event type.
- webhook.
- status.
- date range.
- response code.
- retry count.
- contains text.

### امنیت لاگ

- mask کردن secretها و tokenها.
- عدم نمایش فیلدهای حساس برای نقش های غیرمجاز.
- retention قابل تنظیم.
- export با سطح دسترسی جداگانه.

## فاز 8: Webhook Simulator

- تست بدون submit واقعی.
- ساخت mock submission.
- انتخاب entry واقعی به عنوان نمونه.
- preview نهایی payload بعد از transform.
- اجرای mock request.
- نمایش response در همان صفحه.
- تست condition و route.
- تست signature.
- تولید sample curl.
- تولید sample endpoint برای developer.

## فاز 9: Security 2026

### Signature

- HMAC SHA-256 برای body.
- headerهای پیشنهادی:

```text
X-EFB-Event-ID
X-EFB-Delivery-ID
X-EFB-Timestamp
X-EFB-Signature
X-EFB-Webhook-ID
X-EFB-Payload-Version
```

- امضای `timestamp.event_id.raw_body`.
- مقایسه امن signature.
- timestamp tolerance برای کاهش replay attack.
- secret rotation با active و previous secret.

### URL Safety

- هشدار برای HTTP غیر امن.
- امکان اجبار HTTPS.
- جلوگیری از SSRF.
- بلاک localhost، private IP و metadata endpoints.
- allowlist/denylist دامنه.

### Permission

- capability جدا برای create، edit، test، view logs، retry، view sensitive payload.
- audit log برای تغییر URL، secret، header و auth.

## فاز 10: Templates و Hybrid Integrations

افزودنی باید Webhook عمومی بماند، اما templateهای آماده داشته باشد تا کاربر سریع تر شروع کند.

### Templateهای پیشنهادی

- Slack
- Discord
- Telegram Bot
- Notion
- Airtable
- Google Sheets via Apps Script
- HubSpot
- Pipedrive
- Zoho CRM
- Make.com
- Zapier
- n8n
- Shopify
- WooCommerce internal endpoint
- HelpScout
- Zendesk
- Jira
- GitHub Issues
- Custom REST API

### ساختار template

- endpoint hint.
- method.
- headers.
- body schema.
- response rules.
- common errors.
- test payload.

## فاز 11: AI Webhook Builder

این قابلیت می تواند مزیت جدی Easy Form Builder باشد.

### تجربه کاربر

کاربر می نویسد:

```text
Send form data to my CRM, then notify Slack with the CRM contact ID.
```

سیستم پیشنهاد می دهد:

- ساختار JSON.
- headers.
- field mapping.
- routeها.
- response extraction.
- متن response box.
- retry policy.

### محدودیت های لازم

- AI نباید secret بسازد یا حدس بزند.
- قبل از ذخیره، همه چیز باید preview و تایید شود.
- تغییرات AI باید قابل rollback باشد.
- prompt و خروجی نباید حاوی داده حساس ذخیره نشده باشد، مگر با رضایت کاربر.

## فاز 12: پنل مستقل آینده

در طراحی باید از ابتدا فرض شود که بعدا یک پنل مستقل به عنوان ویژگی افزودنی اضافه خواهد شد. این پنل نباید فقط داخل تنظیمات هر فرم باشد.

### نام پیشنهادی

```text
Automation Center
Webhook Center
Workflow Center
```

### هدف پنل مستقل

- مدیریت همه webhookها در یک مکان.
- مشاهده سلامت همه اتصال ها.
- دیدن queue و delivery logs کل سایت.
- ساخت workflowهای cross-form.
- مدیریت templateها.
- مدیریت secretها.
- مشاهده event catalog.
- مانیتورینگ failure rate.
- retry و replay گروهی.

### بخش های پنل

#### Dashboard

- تعداد deliveryهای امروز.
- success rate.
- failed deliveries.
- average latency.
- active webhooks.
- paused webhooks.
- queue size.

#### Webhooks

- لیست همه webhook feedها.
- فیلتر بر اساس فرم، event، status.
- clone، pause، export، delete.

#### Workflows

- workflow builder مستقل.
- drag/drop nodes.
- اتصال چند فرم به یک workflow.
- reusable workflow templates.

#### Deliveries

- همه delivery logs در سطح سایت.
- replay و retry.
- فیلتر پیشرفته.

#### Events

- event catalog.
- payload schema.
- sample payload.
- تست event.

#### Secrets

- مدیریت secretها.
- rotation.
- last used.
- warning برای secret قدیمی.

#### Templates

- templateهای آماده.
- templateهای سفارشی سایت.
- import/export.

#### Settings

- queue settings.
- retry defaults.
- log retention.
- security defaults.
- permissions.
- data masking.

### نکته معماری

نسخه اول می تواند داخل تنظیمات هر فرم ساخته شود، اما data model باید از ابتدا global باشد تا انتقال به پنل مستقل بدون migration سنگین ممکن باشد.

## فاز 13: Developer Experience

- hooks و filters برای تغییر payload، headers، URL، auth، response handling و retry.
- REST API برای CRUD webhookها و مشاهده deliveryها.
- WP-CLI:

```bash
wp efb webhook list
wp efb webhook test <id>
wp efb webhook retry <delivery_id>
wp efb webhook logs
wp efb workflow run <workflow_id>
```

- SDK داخلی برای تعریف trigger جدید.
- امکان ثبت transformer سفارشی.
- امکان ثبت template سفارشی.
- مستندات verify signature برای PHP، Node.js و Python.

## فاز 14: معماری داده پیشنهادی

### جدول webhook feedها

```text
efb_webhook_feeds
- id
- form_id
- name
- status
- event_type
- endpoint_url
- method
- auth_type
- headers_json
- body_type
- body_schema_json
- conditions_json
- response_rules_json
- retry_policy_json
- security_json
- created_by
- created_at
- updated_at
```

### جدول workflowها

```text
efb_workflows
- id
- name
- status
- scope
- trigger_event
- nodes_json
- edges_json
- settings_json
- created_by
- created_at
- updated_at
```

### جدول deliveryها

```text
efb_webhook_deliveries
- id
- event_id
- delivery_id
- feed_id
- workflow_id
- form_id
- submission_id
- event_type
- status
- attempt_count
- next_attempt_at
- request_url
- request_method
- request_headers_json
- request_body
- response_status
- response_headers_json
- response_body
- duration_ms
- error_message
- created_at
- updated_at
```

### جدول eventها

```text
efb_events
- id
- event_id
- event_type
- source
- form_id
- submission_id
- payload_version
- payload_json
- created_at
```

### جدول secretها

```text
efb_webhook_secrets
- id
- feed_id
- secret_hash_or_encrypted_value
- status
- created_at
- rotated_at
- expires_at
```

## فاز 15: UX جزئی

### ساخت webhook جدید

1. انتخاب رویداد.
2. انتخاب template یا custom.
3. وارد کردن endpoint.
4. mapping داده.
5. تنظیم condition.
6. تنظیم response handling.
7. تست.
8. فعال سازی.

### حالت های UI

- Simple Mode: مناسب اتصال سریع.
- Advanced Mode: headers، auth، JSON builder، response rules.
- Developer Mode: raw editor، signature، curl، schema.

### پیام های خطای قابل فهم

- `401`: توکن یا Authorization اشتباه است.
- `403`: دسترسی endpoint کافی نیست.
- `404`: URL اشتباه است یا endpoint حذف شده.
- `422`: ساختار payload با API مقصد سازگار نیست.
- `429`: rate limit مقصد فعال شده است.
- `500`: خطای سمت سرور مقصد.

## فاز 16: معیارهای پذیرش

- کاربر بتواند در کمتر از 3 دقیقه یک webhook ساده بسازد.
- کاربر بتواند بدون کدنویسی nested JSON بسازد.
- هر delivery لاگ کامل داشته باشد.
- failed delivery قابل retry و replay باشد.
- response API بتواند response box را تغییر دهد.
- admin response بتواند webhook trigger کند.
- webhookها submit فرم را کند نکنند.
- secretها در UI کامل نمایش داده نشوند.
- همه قابلیت های اصلی با RTL سازگار باشند.
- data model آماده پنل مستقل آینده باشد.

## اولویت بندی نسخه ها

### نسخه 1.0

- چند webhook برای هر فرم.
- eventهای submit و update.
- method، URL، headers، auth.
- JSON/form body.
- mapping ساده.
- condition ساده.
- test و preview.
- لاگ پایه.

### نسخه 1.5

- JSON Builder.
- nested object و array.
- transformها.
- response ذخیره شود.
- retry دستی.
- eventهای admin response و response box.

### نسخه 2.0

- queue async.
- retry خودکار.
- debug console.
- response rules.
- dynamic response box.
- HMAC signature و secret rotation.

### نسخه 2.5

- workflow chaining.
- conditional routing پیشرفته.
- save response to entry meta.
- admin response automation.
- templates.

### نسخه 3.0

- پنل مستقل Webhook/Automation Center.
- delivery dashboard.
- global event catalog.
- bulk replay.
- workflow builder drag/drop.

### نسخه 3.5

- AI-assisted mapping.
- OpenAPI import.
- advanced observability.
- enterprise permission و audit log.

## منابع و الهام ها

- Gravity Forms Webhooks: https://docs.gravityforms.com/triggering-webhooks-form-submissions/
- WPForms Webhooks Addon: https://wpforms.com/docs/how-to-install-and-use-the-webhooks-addon-with-wpforms/
- Fluent Forms Webhook Integration: https://wpmanageninja.com/docs/fluent-form/integrations-available-in-wp-fluent-form/webhook-integration/
- Ninja Forms Webhooks: https://ninjaforms.com/docs/webhooks/
- Formidable Forms API/Webhooks: https://formidableforms.com/knowledgebase/formidable-api/
- Typeform Webhook Retry Behavior: https://www.typeform.com/developers/webhooks/
- GitHub Webhook Signature Validation: https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries
- Stripe Webhook Best Practices: https://docs.stripe.com/webhooks
- CloudEvents Specification: https://cloudevents.io/

## جمع بندی

افزودنی Webhook در Easy Form Builder باید از همان نسخه اول پایه های یک موتور workflow را داشته باشد. نسخه های اولیه می توانند ساده و قابل فروش باشند، اما data model، event catalog، delivery log و response handling باید آینده نگر طراحی شوند.

مزیت رقابتی اصلی این افزودنی:

- JSON Builder واقعی.
- response handling کاربردی.
- admin response و response box به عنوان eventهای رسمی.
- queue، retry و debug console.
- workflow chaining.
- پنل مستقل آینده.
- AI-assisted setup.

اگر این مسیر درست اجرا شود، افزودنی Webhook می تواند از سطح افزونه های رایج وردپرس فراتر برود و به یک Automation Engine داخل WordPress تبدیل شود.
