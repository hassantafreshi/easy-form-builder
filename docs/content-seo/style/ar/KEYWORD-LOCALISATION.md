# Keyword localisation (transcreation) for Arabic articles

**Purpose:** before an English article is published in Arabic under the same slug, decide *what Arabic readers actually type* and build the Arabic H1, headings and meta around that, instead of translating the English keyword word by word.
**Evidence:** Google and YouTube autosuggest, `hl=ar`, collected on **2026-11-13** (1,347 fast probes in 5 rounds + a slow, single-threaded verification pass of 120 seeds, see §4). Raw rows: `KEYWORD-AUTOSUGGEST-RAW.csv`. Tool: `tools/autosuggest.py`.
**Honesty box:** autosuggest is a *popularity proxy*, not search volume. A missing completion means "no completion for this prefix at this moment", not "no demand". I had **no** Google Search Console, Keyword Planner or SERP data, so every recommendation below is a hypothesis to be validated (see §5).

---

## 1. The method (8 steps, ~10 minutes per article)

| Step | Action | Output |
|---|---|---|
| 0 | Take from `content-queue.csv`: EN title, EN keyword, **intent** (HOW / USE / TROUBLE / COMPARE / HUB), plan. | the *concept*, not the words |
| 1 | Write the concept in one Arabic sentence the way a site owner would say it. | seed idea |
| 2 | Build **6-10 Arabic seeds** by four routes: **(a)** literal translation of the EN keyword; **(b)** synonym routes: verb (ربط / إرسال / إنشاء / إعداد / تفعيل), noun for form (نموذج / فورم / استمارة إلكترونية), plugin (إضافة / ملحق / plugin), spam (سبام / البريد المزعج / رسائل مزعجة); **(c)** *Latin tool name + Arabic modifier* (`stripe شرح`, `wp mail smtp شرح`); **(d)** problem phrasing (لا يعمل، لا تصل، خطأ). | seed list |
| 3 | Probe each seed on **Google** (`client=firefox&hl=ar&gl=sa`, repeat with `gl=eg`; add `ae`/`ma`/`dz` if the audience is there) and **YouTube** (`client=youtube&ds=yt&hl=ar`). **Use short head terms (1-3 words)**; expand the best 1-2 with the 28 Arabic letters (`--letters`). Space requests ≥ 1 s apart. | completions per seed |
| 4 | Read the signals: number of completions (0-10); whether Google **kept the Arabic or rewrote it to Latin**; intent words in the completions (`شرح`, `كورس`, `pdf`, `word`, `تحميل`, `جاهز`, `مجانا`, company names); competitor/brand names; spelling variants. | evidence table |
| 5 | **Intent check:** scan the top 10 completions of the candidate keyword: how many mean something else (template download, a bank app, consumer bots, school logic, Google Forms)? If more than half, add a disambiguator («في ووردبريس», «إلكتروني») or change the keyword. | go / change |
| 6 | Choose the **primary keyword**: ≥ 1 supporting completion, natural Arabic, ≤ 4 words, disambiguated, uses the **UI term** when the user has to find that label, keeps the **Latin tool name** when the completions do. Add 2-3 secondary variants. | keyword record |
| 7 | Compose H1 (≤ 60 chars), meta (140-155), H2 questions, FAQ phrasing with the formulas in `STYLE-GUIDE.md` §7. Keep the **EN slug**. | Arabic skeleton |
| 8 | Record the evidence (seed, engine, completions) in the article's QA record. Re-check after 4-6 weeks with **Search Console** (country SA/EG/AE/MA, query contains Arabic letters) and update the title if impressions show another phrasing. | QA record |

Output record per article (paste into the QA sign-off):
```yaml
slug: how-to-...                # = EN slug
en_keyword: wordpress conditional fields
intent: HOW
ar_primary: حقول شرطية في نماذج ووردبريس
ar_secondary: [المنطق الشرطي في النماذج, conditional logic wordpress]
evidence: [{seed: "conditional logic wordpress", engine: google-sa, completions: 3}, ...]
intent_check: "«المنطق الشرطي» alone = formal logic → keep 'في نماذج ووردبريس'"
confidence: low|medium|high      # high only with Search Console data
h1: "..."          # chars: ..
meta: "..."        # chars: ..
```

## 2. What the 2026-11-13 probes teach (generalisable rules)

### 2.1 Autosuggest is prefix-based: use short head terms
Of **59 literal Arabic seeds** (round R1), Google returned **no completion for 49 (83%)**; YouTube answered 39 of 59 but mostly with generic WordPress-course noise. Long exact phrases («حماية نموذج ووردبريس من الرسائل المزعجة») are never completed. The method therefore works from **1-3 word head terms** and expands them letter by letter.

### 2.2 Tool-specific demand is written in Latin + an Arabic modifier
Whenever the topic is a named tool or protocol, Google/YouTube complete with the **Latin name and an Arabic task word**: `stripe شرح` (9 YouTube completions), `wp mail smtp شرح`, `spf dkim dmarc شرح`, `contact form 7 شرح`, `wpforms شرح`, `google sheets شرح`. When the typed seed contains «ووردبريس» next to a Latin tool name, Google **rewrites it to "wordpress"** (`honeypot ووردبريس` → `honeypot wordpress plugin`, `stripe ووردبريس` → `stripe wordpress …`, `rtl ووردبريس` → `rtl wordpress …`). Generic WordPress topics stay Arabic: `ووردبريس شرح`, `قالب ووردبريس مجاني`, `اضافات ووردبريس مجانية / احترافية`, `تحميل ووردبريس`. **Rule:** keep the Latin tool name in the Arabic H1/H2/meta, and put «ووردبريس» (Arabic) where the topic is generic.

### 2.3 The recurring Arabic task modifiers
`شرح` (explanation/tutorial; the dominant one), `كورس` / `للمبتدئين` (course/beginner; WordPress video courses crowd YouTube), `تحميل` (download), `مجاني/مجانا`, `عربي`, `بالعربي`, a year. Use «شرح» in tool tutorials («شرح Stripe في ووردبريس…»); the site's newest articles already do («شرح سجل SPF…»).

### 2.4 «نموذج» is mostly a *template*, not a web form
The completions of «نموذج …» are documents: «نموذج تسجيل واقعة ميلاد», «نموذج دفع ضرائب», «نموذج توظيف جرير», «نموذج توقيع استلام راتب». The web-form sense is expressed with «فورم» or «استمارة إلكترونية», and the competing intent there is **Google/Microsoft Forms** («انشاء فورم جوجل», «عمل استمارة الكترونية على جوجل», «انشاء فورم مايكروسوفت»). «نموذج اتصال» is polluted by phone/«اتصال» meanings («نموذج اتصال تيك توك / فيسبوك / واتساب»), «نموذج تواصل» by school/ministry templates. **Rule:** the UI says «نموذج», so keep it, but always add a disambiguator in titles/keywords («في ووردبريس», «إلكتروني») and consider «فورم» as a secondary term in the body.

### 2.5 Brand: the Arabic name is not a search term, the Latin one is
`منشئ النماذج السهل` → **no completion** on Google or YouTube; `مُنشيء النماذج السهل` → only noise. `easy form builder` → 9 Google completions incl. `easy form builder free`, `easy form builder wordpress`, `easy form builder by whitestudio`. **Collision warning:** the same query also completes to «wpforms easy form builder for wordpress contact forms payment forms surveys & more»: WPForms' plugin title contains the phrase "Easy Form Builder", so the generic phrase is shared with a bigger competitor (an EN-level SEO issue as well). **Rule:** Latin «Easy Form Builder» (optionally with «by WhiteStudio») in titles/H2/meta; the Arabic name only inside step text, where it matches the admin menu.

### 2.6 Spelling variants: follow the UI
Google returns the **same «ووردبريس …» completions** (تحميل، شرح، عربي تحميل …) for `ووردبريس` and `وردبريس` (it merges them); `ورد بريس` returns the same list plus a few unrelated ones. For Telegram the four spellings `تيليجرام / تليجرام / تلجرام / تلغرام` each return a full list (10 Google completions, 14 YouTube) with their own spelling kept, so none dominates: keep the UI spelling «تيليجرام». For Google Sheets `جوجل شيت`, `قوقل شيت` and the official `جداول بيانات جوجل` are all heavy: use «جوجل شيت» in the H1 and the official name at first mention.

### 2.7 Saudi vs Egyptian lists
48 of the 78 seeds that returned something in both countries gave identical lists; the other 30 differ mainly in order (e.g. `انشاء فورم` → SA «انشاء فورمز / … استبيان / … مايكروسوفت», EG «انشاء فورم جوجل / … مايكروسوفت»). Probe both, expect small differences only.

### 2.8 Google vs YouTube
YouTube completions are tutorial-shaped (`… شرح`, `… كورس`, `… للمبتدئين`) and are the better source for task phrasing; Google completions are document/brand-shaped (`… pdf`, `… word`, `… تحميل`, company names). Use both: YouTube for the verb, Google for the noun and the intent check.

### 2.9 Terminology evidence (feeds `GLOSSARY.csv`), verified pass R6
| Seed | Google SA | Google EG | YouTube | Reading |
|---|---|---|---|---|
| `تثبيت اضافة` / `تنصيب اضافة` | 4 / 0 | 4 / 0 | 1 / 1 | «تثبيت» is the searched verb for plugins (UI/WordPress label «تنصيب» stays for buttons) |
| `تثبيت ووردبريس` / `تنصيب ووردبريس` | 5 / 5 | 5 / 5 | 7 / 4 | both used for installing WordPress itself |
| `تحميل ووردبريس` / `تنزيل ووردبريس` | 10 / 2 | 10 / 2 | 3 / 1 | download-intent titles may use «تحميل»; steps use «تنزيل» |
| `حقل مطلوب` / `حقل إلزامي` | 10 / 3 | 10 / 3 | 0 / 2 | «مطلوب» (= UI) wins |
| `تصميم متجاوب` / `تصميم مستجيب` | 1 / 0 | 1 / 0 | 2 / 0 | «متجاوب» |
| `اضافات ووردبريس` / `ملحق ووردبريس` | 9 / 0 | 9 / 0 | 3 / 0 | plugin = «إضافة»; «ملحق» is not a WordPress search word (it is an EFB term, explain it) |
| `قالب ووردبريس` / `ثيم ووردبريس` | 10 / 10 (Latin rewrite «theme wordpress») | 10 / 10 | 14 / 7 | «قالب» (WP core) |
| `رمز التأكيد` / `كود التأكيد` / `رمز التتبع` | 10 / 10 / 10 | 10 / 10 / 10 | 13 / 3 / 2 | all populated; «رمز التأكيد» = OTP/verification, «رمز التتبع» = parcel tracking: explain on first mention |
| `سبام` / `السبام` / `رسائل سبام` / `البريد المزعج` | 10 / 10 (noise: «السلام عليكم») / 6 / 10 | same | 14 / 14 / 10 / 6 | «البريد المزعج» = mailbox spam folder; «سبام» usable as keyword |
| `الاسئلة الشائعة` / `الاسئلة المتكررة` | 10 / 10 | 10 / 10 | 14 / 14 | both common: FAQ heading is a consistency choice |
| `منشئ النماذج السهل` / `easy form builder` | 0 / 9 | 0 / 9 | 0 / 0 | see 2.5 |


## 3. Demonstration on 12 topics from `content-queue.csv`

Ten were requested; I added K4 (CSV export) and J1 (signature field) because they show two further traps. «—» in a table cell means **no completion returned** in the verification pass (a retry 1.5 s later was also empty): no completion, not "no demand".

### C1. How to Migrate from Contact Form 7 to Easy Form Builder (One-Click Import)

* **EN keyword:** `migrate contact form 7 wordpress` · **plan:** Free · **intent:** HOW

| Arabic seed typed | Google autosuggest (hl=ar, gl=sa) | YouTube autosuggest |
|---|---|---|
| `contact form 7` | contact form 7 / contact form 7 wordpress / contact form 7 recaptcha / contact form 7 plugin / contact form 7 captcha / contact form 7 honeypot | contact form 7 wordpress شرح / contact form 7 شرح / contact form 7 / contact form 7 wordpress tutorial |
| `contact form 7 ووردبريس` | contact form 7 wordpress / contact form 7 wordpress plugin / contact form 7 wordpress how to use / contact form 7 wordpress recaptcha / contact form 7 wordpress plugin vulnerability / contact form 7 wordpress tutorial | contact form 7 wordpress tutorial / contact form 7 wordpress / contact form 7 wordpress شرح / contact form 7 wordpress setup |
| `wordpress contact form` | wordpress contact form / wordpress contact form 7 / wordpress contact form not sending email / wordpress contact form plugin / wordpress contact form 7 not sending email / wordpress contact form 7 captcha | wordpress contact form 7 / wordpress contact form / wordpress contact form 7 setup / wordpress contact form 7 tutorial |
| `نقل نماذج contact form 7` | — | — |
| `الانتقال من contact form 7` | — | — |
| `بديل contact form 7` | — | — |
| `نموذج اتصال ووردبريس` | — | انشاء منصة تعليمية ووردبريس |
| `نموذج تواصل ووردبريس` | — | — |

**What the data says**

* The Latin product name carries all the demand: «contact form 7 wordpress», «… شرح», «… not sending email».
* Every Arabic migration phrase («نقل / الانتقال من / بديل») returned **nothing** on Google and YouTube. Either the volume is tiny or people search in English.
* «نموذج اتصال» is polluted by phone/«اتصال» meanings (see lexical probes: «نموذج اتصال تيك توك / فيسبوك»), «نموذج تواصل» by school and ministry templates.

- **Recommended primary keyword (AR):** «الانتقال من Contact Form 7» (Latin product name kept; unvalidated long-tail)
- **Secondary:** «Contact Form 7 شرح», «استيراد نماذج Contact Form 7», «بديل Contact Form 7»
- **Recommended H1 (60 chars):** الانتقال من Contact Form 7 إلى Easy Form Builder بنقرة واحدة
- **Intent check:** migration/how-to; no template/download ambiguity once the product name is Latin
- **Confidence:** Low (no Arabic completion at all): rely on the Latin name; expect English-query traffic to dominate

### G1. Send WordPress Form Entries to Google Sheets Without Zapier

* **EN keyword:** `wordpress form to google sheets` · **plan:** Add-on (Pro) · **intent:** HOW

| Arabic seed typed | Google autosuggest (hl=ar, gl=sa) | YouTube autosuggest |
|---|---|---|
| `google sheets wordpress` | wordpress - google sheets / google sheets wordpress plugin / google sheets wordpress integration / google sheets to wordpress table / embed google sheets in wordpress / connect google sheets to wordpress | google sheets wordpress / wordpress google sheets integration |
| `ربط google sheets` | — | ربط google sheets مع n8n / ربط woocommerce مع google sheets / ربط جوجل شيت مع يوكان / ربط قوقل شيت بالاكسل |
| `ربط نموذج بجوجل شيت` | — | ربط الاكسيل بجوجل شيت / ربط جوجل فورم بجوجل شيت / نموذج ادخال بيانات جوجل شيت / ربط جوجل فورم بالاكسل |
| `ربط الفورم بجوجل شيت` | — | ربط الاكسيل بجوجل شيت / ربط جوجل فورم بجوجل شيت / ربط جوجل شيت مع يوكان |
| `ربط جوجل شيت بالموقع` | — | ربط جوجل شيت مع يوكان |
| `جوجل شيت` | جوجل شيت / جوجل شيت تحميل / جوجل شيت عربي / جوجل شيت للموبايل / جوجل شيت اون لاين / جوجل شيت اكسيل | جوجل شيت / جوجل شيت من الصفر / جوجل شيت للموبايل / جوجل شيت مخازن |
| `قوقل شيت` | قوقل شيت / قوقل شيت تحميل / قوقل شيت شرح / قوقل شيت ميزانيه الشهرية تحميل مجاني / قوقل شيت اونلاين / قوقل شيت وورد | قوقل شيت / قوقل شيت بالهاتف / قوقل شيت ايباد / قوقل شيت جدول |
| `جداول بيانات جوجل` | جداول بيانات جوجل / جداول بيانات جوجل google sheets / جداول بيانات جوجل apk / جداول بيانات جوجل للكمبيوتر / جداول بيانات جوجل تحميل / جداول بيانات جوجل شيت | جداول بيانات جوجل / جداول بيانات google / جدول بيانات جوجل / جدول بيانات قوقل |

**What the data says**

* People say **«ربط»** (link/connect) + «جوجل شيت»/«قوقل شيت»: «ربط جوجل شيت مع يوكان», «ربط woocommerce مع google sheets». «إرسال» (send) does not appear.
* Competing intent: «ربط جوجل فورم بجوجل شيت» = Google Forms. The article must say WordPress/نموذج ووردبريس.
* «جوجل شيت» and «قوقل شيت» are both heavy (10 completions each), the official «جداول بيانات جوجل» too; the UI uses the official name.

- **Recommended primary keyword (AR):** «ربط نموذج ووردبريس بجوجل شيت»
- **Secondary:** «جوجل شيت ووردبريس», «google sheets wordpress» (Latin: validated), «جداول بيانات جوجل» in the body (UI term)
- **Recommended H1 (48 chars):** ربط نموذج ووردبريس بجوجل شيت تلقائيًا دون Zapier
- **Intent check:** how-to; risk of Google Forms/Excel intent: keep «ووردبريس» in H1
- **Confidence:** Medium for the head terms «جوجل شيت» and «ربط»; the full phrase is unvalidated

### E1. Stop WordPress Form Spam Without CAPTCHA: Security & Spam Protection Guide

* **EN keyword:** `stop wordpress form spam without captcha` · **plan:** Add-on (Pro) · **intent:** HOW

| Arabic seed typed | Google autosuggest (hl=ar, gl=sa) | YouTube autosuggest |
|---|---|---|
| `سبام ووردبريس` | spam wordpress / spam wordpress contact form / spam wordpress plugin | spam wordpress / wordpress كورس / سيو ووردبريس |
| `honeypot ووردبريس` | honeypot wordpress plugin / honeypot wordpress / honeypot wordpress contact form 7 / honeypot wordpress elementor | honeypot wordpress |
| `recaptcha ووردبريس` | wordpress recaptcha / recaptcha wordpress plugin / recaptcha wordpress contact form 7 / recaptcha wordpress login / recaptcha wordpress elementor / recaptcha wordpress form | recaptcha wordpress / recaptcha wordpress contact form 7 / recaptcha wordpress elementor / recaptcha wordpress wpforms |
| `كابتشا ووردبريس` | captcha in wordpress / captcha wordpress login / captcha wordpress contact form 7 / captcha wordpress elementor / captcha wordpress form / captcha wordpress free | captcha wordpress / captcha wordpress contact form 7 / wordpress كورس / كورس الووردبريس |
| `منع السبام ووردبريس` | — | موقع هوستنجر |
| `سبام` | سبام / سباما / سباما ميديكال / سيباميد / سبام معنى / سبتمبر | سبام مكالمات / سبام رسائل / سبام / سبام فري فاير |
| `السبام` | السبام / السلام عليكم / السبانخ / السلام مول / السلام عليكم ورحمة الله وبركاته / السلام | السلام عليكم / السلام الملكي الاردني / السلامه دورها / السلام عليك يا رسول الله |
| `رسائل سبام` | رسائل سبام / سبام رسائل sms / رسائل spam / رسائل spam في gmail / معنى رسائل سبام / تطبيق سبام رسائل | رسائل سبام / سبام رسائل pydroid 3 / سبام رسائل sms / سبام رسائل مزعجه |
| `البريد المزعج` | البريد المزعج / البريد المزعج في الايفون / البريد المزعج في gmail / البريد المزعج spam / البريد المزعج بالانجليزي / مجلد البريد المزعج | البريد المزعج في الايفون / البريد المزعج في gmail / البريد المزعج / مجلد البريد المزعج في الايفون |

**What the data says**

* Google **rewrites the Arabic «ووردبريس» into Latin «wordpress»** in its completions: the demand is expressed with Latin tool words («honeypot wordpress», «wordpress recaptcha», «spam wordpress contact form»).
* «السبام» with the article completes to «السلام عليكم / السبانخ» (noise). Without the article «سبام» is usable but noisy («سباما ميديكال», «سبام مكالمات»). «البريد المزعج» is about the e-mail client spam folder (gmail, iPhone), not form spam. «رسائل سبام» = SMS spam.
* Long Arabic seeds («منع السبام ووردبريس», «حماية نموذج ووردبريس من الرسائل المزعجة») returned nothing.

- **Recommended primary keyword (AR):** «حماية نماذج ووردبريس من السبام» (+ «بدون كابتشا»)
- **Secondary:** «honeypot ووردبريس», «reCAPTCHA ووردبريس», «سبام ووردبريس»; in the body use the UI term «البريد المزعج»
- **Recommended H1 (42 chars):** حماية نماذج ووردبريس من السبام بدون كابتشا
- **Intent check:** how-to; ambiguity with e-mail spam folder: say «نماذج» and «ووردبريس» early
- **Confidence:** Low-Medium: Latin tool words validated, Arabic phrase not

### D1. Weekly Email Deliverability Report: How to Read Score, SPF, DKIM, DMARC

* **EN keyword:** `wordpress email deliverability report` · **plan:** Free · **intent:** HOW

| Arabic seed typed | Google autosuggest (hl=ar, gl=sa) | YouTube autosuggest |
|---|---|---|
| `smtp ووردبريس` | smtp wordpress / smtp wordpress plugin / smtp wordpress free / wordpress smtp with gmail / smtp wordpress hostinger / smtp wordpress plugins | smtp wordpress / smtp wordpress gmail / smtp wordpress setup / smtp wordpress contact form 7 |
| `wp mail smtp` | wp mail smtp / wp mail smtp pro / wp mail smtp plugin / wp mail smtp by wpforms / wp mail smtp free / wp mail smtp lite | wp mail smtp شرح / wp mail smtp / wp mail smtp gmail / wp mail smtp by wpforms |
| `spf dkim dmarc` | spf dkim dmarc / spf dkim dmarc check / spf dkim dmarc checker / spf dkim dmarc explained / spf dkim dmarc records / spf dkim dmarc authentication | spf dkim dmarc شرح / spf dkim dmarc / spf dkim dmarc google workspace / spf dkim dmarc gmail |
| `رسائل ووردبريس لا تصل` | — | رسائل الغفران / رسائل لا تصل / رسائل اسلاميه |
| `ووردبريس لا يرسل البريد الإلكتروني` | — | إرسال رسائل بريد إلكتروني من نموذج اتصال html |
| `مشكلة ارسال الايميل في ووردبريس` | — | wp mail smtp شرح / انشاء صفحة اتصل بنا ووردبريس / اضافة مقال في ووردبريس |
| `رسائل النموذج تذهب إلى السبام` | — | رسالة تيطس / رسائل الإيمبد |

**What the data says**

* Latin again: «smtp wordpress / wordpress smtp plugin / … free / … gmail / … hostinger», «wp mail smtp شرح», «spf dkim dmarc شرح» (YouTube).
* The Arabic problem phrasing returned noise («رسائل الغفران», «رسائل اسلاميه»); the only useful YouTube mapping for «مشكلة ارسال الايميل في ووردبريس» is «wp mail smtp شرح» and «انشاء صفحة اتصل بنا ووردبريس».
* The site's newest Arabic e-mail series already uses the right pattern: «شرح سجل SPF … وإعداده» (modifier «شرح»), and the problem title «رسائل ووردبريس لا تصل أو تذهب إلى البريد المزعج؟».

- **Recommended primary keyword (AR):** «SPF DKIM DMARC شرح» (report article) · «SMTP ووردبريس» (SMTP articles)
- **Secondary:** «تقرير تسليم البريد ووردبريس», «قابلية تسليم البريد» (UI term)
- **Recommended H1 (59 chars):** قراءة تقرير تسليم البريد: درجة SPF وDKIM وDMARC في ووردبريس
- **Intent check:** how-to/diagnose; no template ambiguity
- **Confidence:** Medium for «شرح» + Latin acronyms (YouTube completions exist)

### H4. Test Stripe Payments in Test Mode Before Going Live (Checklist)

* **EN keyword:** `stripe test mode wordpress form` · **plan:** Free Plus · **intent:** HOW

| Arabic seed typed | Google autosuggest (hl=ar, gl=sa) | YouTube autosuggest |
|---|---|---|
| `stripe wordpress` | stripe wordpress / stripe wordpress plugin / stripe wordpress integration / wordpress woocommerce stripe / stripe wordpress plugin free / stripe wordpress subscription | stripe wordpress / stripe wordpress woocommerce / stripe connect wordpress / stripe plugin wordpress |
| `stripe شرح` | stripe شرح / stripe payment شرح / stripe atlas شرح | stripe شرح / stripe شرح طريقة فتح حساب 2024 / stripe شرح tunisie / stripe atlas شرح |
| `stripe ووردبريس` | stripe wordpress / stripe wordpress plugin / stripe wordpress integration / wordpress woocommerce stripe / stripe wordpress plugin free / stripe wordpress subscription | stripe wordpress / stripe wordpress woocommerce |
| `بوابة دفع ووردبريس` | بوابة دفع ووردبريس | بوابة دفع ووردبريس |
| `الدفع ووردبريس` | — | ووردبريس الدفع عند الاستلام / بوابات الدفع ووردبريس / متجر ووردبريس الدفع عند الاستلام |
| `اضافة دفع ووردبريس` | — | اضافة وسيلة دفع على موقعك / اضافة طرق الدفع لموقعك / اضافة الدفع الالكتروني للموقع |
| `اختبار stripe في ووردبريس` | — | stripe شرح |

**What the data says**

* «stripe شرح» is rich on YouTube: «stripe شرح طريقة فتح حساب 2024», «stripe شرح tunisie», «stripe account شرح», «stripe connect شرح»: Arabic searchers mainly ask **how to open/use a Stripe account**, with country hints. A "test mode" Arabic phrase returned nothing.
* «بوابة دفع ووردبريس» exists but returns only itself; «الدفع ووردبريس» leads to «الدفع عند الاستلام» (cash on delivery, WooCommerce).

- **Recommended primary keyword (AR):** «Stripe شرح» (head) + «اختبار Stripe في ووردبريس» (long tail, unvalidated)
- **Secondary:** «وضع الاختبار Stripe», «بطاقة اختبار Stripe», «بوابة دفع ووردبريس»
- **Recommended H1 (55 chars):** اختبار دفعات Stripe في ووردبريس قبل الإطلاق: قائمة تحقق
- **Intent check:** how-to checklist; add a short paragraph on whether Stripe is available in the reader's country **only after verifying it** [UNVERIFIED]
- **Confidence:** Medium for the head term, Low for the exact phrase

### F1. How to Show or Hide Form Fields Based on a User's Answer in WordPress

* **EN keyword:** `wordpress conditional fields` · **plan:** Free Plus · **intent:** HOW

| Arabic seed typed | Google autosuggest (hl=ar, gl=sa) | YouTube autosuggest |
|---|---|---|
| `conditional logic wordpress` | conditional logic wordpress / conditional logic form wordpress / conditional logic forms wordpress free | — |
| `conditional fields wordpress` | wordpress conditional fields | — |
| `المنطق الشرطي` | المنطق الشرطي | المنطق الاقتراني الشرطي / المنطق والعبارات الشرطية / المنطق القضايا |
| `حقول شرطية` | — | — |
| `منطق شرطي نموذج` | — | — |
| `نموذج ذكي ووردبريس` | — | ووردبريس كورس / شرح ووردبريس من الألف إلى الياء - دورة تعلم ووردبريس بالعربي |

**What the data says**

* Only the Latin phrase has completions («conditional logic wordpress», «conditional logic form wordpress», «… free»).
* «المنطق الشرطي» is formal logic/maths: YouTube returns «المنطق الاقتراني الشرطي», «المنطق والعبارات الشرطية». «حقول شرطية» and «منطق شرطي نموذج» returned nothing.
* UI uses «المنطق الشرطي» (62 uses on the site). Keep it as the product term, but add «في نماذج ووردبريس» to avoid the logic-class intent.

- **Recommended primary keyword (AR):** «حقول شرطية في نماذج ووردبريس» (unvalidated) · «Conditional Logic» (Latin, validated)
- **Secondary:** «إظهار وإخفاء الحقول», «المنطق الشرطي في النماذج»
- **Recommended H1 (56 chars):** إظهار حقول النموذج وإخفاؤها حسب إجابة الزائر في ووردبريس
- **Intent check:** how-to; strong off-topic risk if «المنطق الشرطي» stands alone
- **Confidence:** Low

### M1. RTL Forms in WordPress: Arabic, Persian and Hebrew Setup Checklist

* **EN keyword:** `rtl wordpress form` · **plan:** Free · **intent:** HOW

| Arabic seed typed | Google autosuggest (hl=ar, gl=sa) | YouTube autosuggest |
|---|---|---|
| `rtl wordpress` | rtl wordpress / rtl wordpress themes / rtl wordpress theme / rtl wordpress themes free / rtl wordpress plugin / best rtl wordpress themes | rtl wordpress plugin / wordpress rtl |
| `نموذج عربي ووردبريس` | — | نماذج a2 / wordpress كورس |
| `اضافة نموذج ووردبريس` | — | إضافة مقالات ووردبريس / انشاء موقع بالووردبريس |
| `اضافة فورم ووردبريس` | — | إضافة مقالات ووردبريس / اضافات ووردبريس / اضافة منتجات ووردبريس |
| `افضل اضافة نماذج ووردبريس` | — | افضل استضافة مجانية ووردبريس |
| `wordpress forms plugin` | wordpress forms plugin / wordpress forms plugin free / wordpress forms plugin with database / wordpress gravity forms plugin / wordpress fluent forms plugin / wordpress formidable forms plugin | wordpress forms plugin / formidable forms wordpress plugin tutorial / everest forms wordpress plugin / formidable forms wordpress plugin |

**What the data says**

* «rtl wordpress» (Latin) is the validated query: «rtl wordpress themes / theme / plugin».
* Arabic phrasings («نموذج عربي», «اضافة فورم», «افضل اضافة نماذج») returned nothing useful (YouTube drifts to general WordPress courses: «wordpress كورس»).
* This is the site's home turf (the Arabic readers are the audience): write it first-class, not as a translation.

- **Recommended primary keyword (AR):** «RTL ووردبريس» (hybrid) + «نماذج ووردبريس بالعربية»
- **Secondary:** «نموذج ووردبريس يدعم العربية», «إضافة نماذج RTL»
- **Recommended H1 (52 chars):** نماذج ووردبريس بالعربية واتجاه RTL: قائمة تحقق كاملة
- **Intent check:** how-to/checklist; low ambiguity
- **Confidence:** Medium (Latin head) / Low (Arabic phrase)

### I1. Create a Job Application Form with Resume Upload in WordPress

* **EN keyword:** `job application form wordpress` · **plan:** Free · **intent:** USE

| Arabic seed typed | Google autosuggest (hl=ar, gl=sa) | YouTube autosuggest |
|---|---|---|
| `نموذج توظيف` | نموذج توظيف المراعي / نموذج توظيف / نموذج توظيف البيك / نموذج توظيف جرير / نموذج توظيف فوري / نموذج توظيف دانكن | نموذج طلب توظيف / نموذج مباراة توظيف تقنيين من الدرجة الرابعة / نماذج طلبات التوظيف / نموذج تفويض |
| `نموذج طلب توظيف` | نموذج طلب توظيف العثيم / نموذج طلب توظيف المراعي / نموذج طلب توظيف / نموذج طلب توظيف pdf / نموذج طلب توظيف رد تاغ / نموذج طلب توظيف word | نموذج طلب توظيف / نموذج طلب خطي للعمل / نموذج طلب عمل بالعربية |
| `نموذج طلب وظيفة` | نموذج طلب وظيفة جاهز / نموذج طلب وظيفة / نموذج طلب وظيفة جرير / نموذج طلب وظيفة حكومية pdf / نموذج طلب وظيفة إمام مسجد / نموذج طلب وظيفة عون سلطة | نموذج طلب عمل بالعربية / نموذج طلب توظيف / نموذج طلب خطي للعمل |
| `استمارة توظيف` | استمارة توظيف / استمارة توظيف جرير / استمارة توظيف كارفور / استمارة توظيف على اساس الشهادة / استمارة توظيف على اساس الاختبار / استمارة توظيف pdf | استمارة التطوع / استماره التطوع / استمارة الدراسات العليا |
| `نموذج تقديم وظيفة` | نموذج تقديم وظيفة / نموذج تقديم وظيفة word / نموذج تقديم وظيفة بالانجليزي / نموذج تقديم وظيفه بالعربي / نموذج طلب تقديم وظيفة / نموذج تقديم على وظيفة عن طريق الايميل | نموذج تقديم درس امام اللجنة / نماذج طلبات التوظيف |
| `نموذج سيرة ذاتية` | نموذج سيرة ذاتية جاهز للتعبئة pdf / نموذج سيرة ذاتية / نموذج سيرة ذاتية word / نموذج سيرة ذاتية جاهز للتعبئة word / نموذج سيرة ذاتية ats / نموذج سيرة ذاتية جاهز للكتابة | نموذج سيرة ذاتية / نموذج سيرة ذاتية بالعربية word / نموذج سيرة ذاتية بالعربي جاهزة / نموذج سيرة ذاتية ats |
| `wordpress job application form` | wordpress job application form / job application form plugin for wordpress | — |

**What the data says**

* Large, real Arabic demand, but for **documents**: company names (المراعي، البيك، جرير، العثيم), «pdf», «word», «جاهز», government «استمارة … على أساس الشهادة». The alphabet expansion of «نموذج توظيف» alone produced 146 distinct completions, none about WordPress.
* A WordPress job-application-form article cannot win «نموذج توظيف» without matching the template intent; it should rank for the explicit «في ووردبريس / إلكتروني / رفع السيرة الذاتية» variants.
* Latin «wordpress job application form» has two completions only.

- **Recommended primary keyword (AR):** «نموذج طلب توظيف إلكتروني في ووردبريس»
- **Secondary:** «رفع السيرة الذاتية في نموذج ووردبريس», «استمارة توظيف إلكترونية»
- **Recommended H1 (55 chars):** إنشاء نموذج طلب توظيف في ووردبريس مع رفع السيرة الذاتية
- **Intent check:** **intent mismatch risk (template download)**: mention «قالب جاهز» only if the plugin really ships a ready template [UNVERIFIED]
- **Confidence:** Medium demand / Low intent fit

### G7. Get WordPress Form Notifications in Telegram (Bot Setup in 5 Minutes)

* **EN keyword:** `wordpress form telegram notification` · **plan:** Add-on (Pro) · **intent:** HOW

| Arabic seed typed | Google autosuggest (hl=ar, gl=sa) | YouTube autosuggest |
|---|---|---|
| `telegram wordpress` | telegram wordpress / telegram wordpress plugin / telegram wordpress themes / wordpress telegram group link / wordpress telegram channel / plugins wordpress telegram | — |
| `تيليجرام ووردبريس` | — | #تيلجرام / تيلگرام / تيليجرام بوت / تنزيل الووردبريس |
| `تلجرام ووردبريس` | — | — |
| `بوت تيليجرام` | بوت تيليجرام / بوت تيليجرام أغاني / بوت تيليجرام تنزيل فيديوهات فيسبوك / بوت تيليجرام جيزي / بوت تيليجرام تعديل الصور / بوت تيليجرام مسلسلات تركية | بوت تليجرام / بوت تليجرام لتحميل الفيديو / بوت تيليجرام فري فاير / بوت تليجرام لتحميل الفيديوهات من اي موقع |
| `بوت تليجرام` | بوت تليجرام معرفة موقع حساب تيك توك / بوت تليجرام / بوت تليجرام لتحميل الفيديو / بوت تليجرام إزالة الكتابة من الفيديو / بوت تليجرام لتحميل الفيديو من تويتر / بوت تليجرام معرفة رقم المتصل | بوت تليجرام لتحميل الفيديو / بوت تليجرام / بوت تليجرام لتحميل الفيديوهات من اي موقع / بوت تليجرام تحويل الصور الى pdf |
| `اشعارات تلجرام` | اشعارات تلجرام / كيف اوقف اشعارات تلجرام | اشعارات تليجرام / اشعارات تليجرام ايفون / كيف اخفي اشعارات تليجرام / كتم الاشعارات في التليجرام |
| `ربط تلجرام بالموقع` | — | ربط التليجرام بالايميل / ربط التليجرام على الحاسبة / ربط المايكروتك بالتلجرام / ربط التليجرام باللاب توب |

**What the data says**

* Spelling variants (تيليجرام / تليجرام / تلجرام / تلغرام) all return full lists: Google treats them as variants → keep the UI spelling «تيليجرام».
* «بوت تيليجرام» is consumer-bot territory (download videos, free-fire, numbers): hundreds of completions, none about websites. «اشعارات تلجرام» = how to mute Telegram notifications.
* Latin «telegram wordpress / … plugin / … bot» has completions.

- **Recommended primary keyword (AR):** «إشعارات تيليجرام لنماذج ووردبريس» (unvalidated) + «Telegram WordPress» (Latin, validated)
- **Secondary:** «بوت تيليجرام ووردبريس», «ربط ووردبريس بتيليجرام»
- **Recommended H1 (54 chars):** إشعارات تيليجرام لنماذج ووردبريس: ربط بوت خلال 5 دقائق
- **Intent check:** how-to; noise from consumer bots → «ووردبريس» and «نماذج» must be in the first words
- **Confidence:** Low

### H1. Accept Donations with a WordPress Form (Stripe or PayPal)

* **EN keyword:** `wordpress donation form` · **plan:** Free Plus · **intent:** USE

| Arabic seed typed | Google autosuggest (hl=ar, gl=sa) | YouTube autosuggest |
|---|---|---|
| `donation wordpress` | donation wordpress plugin / donation wordpress theme / donation wordpress / wordpress donation plugin free / wordpress donation plugins / donation page wordpress | donation wordpress website / wordpress donation plugin free / wordpress donation plugin / donation page wordpress |
| `wordpress donation plugin` | wordpress donation plugin / wordpress donation plugin free / donation plugin wordpress / wordpress donation plugins / best wordpress donation plugin / give donation wordpress plugin | wordpress donation plugin / wordpress donation plugin free |
| `نموذج تبرع` | نموذج تبرع بالدم / نموذج تبرع / نموذج تبرع مالي / نموذج تبرع عيني / نموذج طلب تبرع مالي / نموذج عقد تبرع | نماذج المحاكاة |
| `نموذج تبرعات` | نموذج جمع تبرعات / نموذج استلام تبرعات عينية / نموذج رسالة جمع تبرعات / نموذج طلب جمع تبرعات لبناء مسجد / نموذج طلب جمع تبرعات / نموذج اعلان جمع تبرعات | نماذج المحاكاة |
| `زر تبرع` | — | كيف اسوي زر تبرع في روبلوكس / كيف اسوي زر تبرع |
| `اضافة تبرعات` | — | اضافه مولود ابشر / اضافه حقول في الاكسس / اضافة مصروف في قيود / اضافة مؤمن لهم |
| `قبول التبرعات في ووردبريس` | — | رفع قالب على الووردبريس / نقل موقع ووردبريس من استضافة لأخرى |

**What the data says**

* In Arabic «نموذج تبرع» = blood/cash donation **receipts and contracts** («نموذج تبرع بالدم», «نموذج عقد تبرع», «نموذج ايصال تبرع»).
* WordPress-specific demand is Latin («wordpress donation plugin free», «wordpress paypal donation plugin», «donation page wordpress»). «زر تبرع» leads to Roblox.

- **Recommended primary keyword (AR):** «التبرعات في ووردبريس» (hybrid) / «wordpress donation plugin» (Latin)
- **Secondary:** «قبول التبرعات عبر الإنترنت», «زر تبرع PayPal»
- **Recommended H1 (49 chars):** قبول التبرعات في ووردبريس بنموذج Stripe أو PayPal
- **Intent check:** USE-case article; intent mismatch with document templates → keep «ووردبريس» + payment brand names in the H1
- **Confidence:** Low

### K4. Export Form Entries to CSV (Free in Every Version)

* **EN keyword:** `export wordpress form entries csv` · **plan:** Free · **intent:** HOW

| Arabic seed typed | Google autosuggest (hl=ar, gl=sa) | YouTube autosuggest |
|---|---|---|
| `تصدير اكسل ووردبريس` | — | تصدير اكسل / تصدير اكسل الى وورد / تصدير ملف اكسل |
| `تصدير بيانات ووردبريس` | — | تصدير قاعدة البيانات phpmyadmin / رفع قالب على الووردبريس |
| `استجابات النموذج` | تصدير استجابات النموذج نستخدم / يمكن تصدير استجابات النموذج إلى جدول بيانات مايكروسوفت إكسل / خطوات تصدير استجابات النموذج إلى جدول بيانات مايكروسوفت إكسل / يمكن تصدير استجابات النموذج الى جدول بيانات / يمكن تصدير استجابات النموذج إلى جدول بيانات مايكروسوفت / يمكن تصدير استجابات النموذج | اقترانات التزايد والتناقص / استجابة النبات / الاضمحلال الاسي |
| `csv ووردبريس` | csv wordpress import / csv wordpress | csv wordpress |
| `export wordpress form entries` | wordpress export form entries | — |

**What the data says**

* Arabic readers say «اكسل» (Excel), rarely CSV. «استجابات النموذج» + «تصدير» matches Google Forms documentation phrases.
* No WordPress form-specific Arabic completion exists; Latin «csv wordpress» has two.

- **Recommended primary keyword (AR):** «تصدير نماذج ووردبريس إلى اكسل»
- **Secondary:** «تصدير ردود النموذج», «ملف CSV» in the body
- **Recommended H1 (43 chars):** تصدير إرسالات نماذج ووردبريس إلى Excel وCSV
- **Intent check:** how-to; low competition but also low evidence
- **Confidence:** Low

### J1. Signature Field: Collect E-Signatures in WordPress Forms

* **EN keyword:** `wordpress form signature field` · **plan:** Free Plus · **intent:** HOW

| Arabic seed typed | Google autosuggest (hl=ar, gl=sa) | YouTube autosuggest |
|---|---|---|
| `signature wordpress` | signature wordpress / e signature wordpress plugin / wordpress signature plugin / digital signature wordpress plugin / wordpress e-signature / digital signature wordpress | — |
| `اضافة توقيع الكتروني` | اضافة توقيع الكتروني / اضافة توقيع الكتروني على ملف pdf / اضافة توقيع الكتروني في الوورد / اضافة توقيع الكتروني ايفون / اضافة توقيع الكتروني في الايفون / اضافة توقيع الكتروني الراجحي | اضافة توقيع الكتروني على ملف pdf / اضافة توقيع الكتروني في الوورد / اضافة توقيع الكتروني ايفون / اضافة توقيع الكتروني في تطبيق الراجحي |
| `نموذج توقيع` | نموذج توقيع / نموذج توقيع استلام راتب / نموذج توقيع الكتروني / نموذج توقيع حضور وانصراف / نموذج توقيع رواتب العمالة المنزلية / نموذج توقيع ايميل | نموذج توقيع بخط اليد / نموذج توقيع |
| `توقيع الكتروني ووردبريس` | — | التعديل على موقع ووردبريس |

**What the data says**

* «اضافة توقيع الكتروني» = adding a signature to PDF/Word/iPhone/bank apps; «نموذج توقيع» = signature templates (payroll, attendance).
* Latin «e signature wordpress plugin», «digital signature wordpress plugin» have completions; the Arabic WordPress phrase has none.

- **Recommended primary keyword (AR):** «حقل التوقيع الإلكتروني في نماذج ووردبريس»
- **Secondary:** «التوقيع الإلكتروني ووردبريس», «e-signature plugin»
- **Recommended H1 (40 chars):** حقل التوقيع الإلكتروني في نماذج ووردبريس
- **Intent check:** how-to; heavy off-topic intent (documents/banks) → H1 names «نماذج ووردبريس»
- **Confidence:** Low


## 4. How the data was collected (reproducibility)

* Fast passes (4 parallel connections, 0.25 s spacing): **R1** 59 literal Arabic seeds × (Google SA, Google EG, YouTube) = 177 probes; **R2/R3** short head terms for 11 topics plus alphabet expansion (28 Arabic letters) of 22 head terms = 858 probes; **R4** lexical-variant probes for terminology (91 seeds × 3 = 273); **R5** brand/competitor probes (13 × 3 = 39). Total **1,347 probes**.
* **R6 verification pass:** every seed cited in §2-§3 (120 seeds) was re-run **single-threaded with ≥ 1 s spacing and one retry after 1.5 s for every empty answer**, to rule out throttling artefacts. Result: for the 259 probes that existed in both passes, the "empty / not empty" outcome was **identical in 259 of 259**, and no empty answer turned non-empty on retry. So the empties are real empties. (When Google SA was empty the EG probe was skipped.)
* Saudi (`gl=sa`) and Egyptian (`gl=eg`) lists: see 2.7.
* All probes: `suggestqueries.google.com`, `hl=ar`, 2026-11-13. Raw rows incl. R6: `KEYWORD-AUTOSUGGEST-RAW.csv` (855 rows). Re-run with `tools/autosuggest.py`.
* Nothing was posted or submitted; responses were parsed as data only.

## 5. Not verified / next steps

* **No volume data.** Validate the chosen primary keyword in Google Search Console after publication, and with Keyword Planner if available. Treat «Low» confidence rows as hypotheses.
* Suggestions depend on time, account state and exit IP; re-run before each article (the tool takes seconds).
* I did not inspect the SERPs. Before committing to a keyword, open the Arabic SERP once and look at who ranks (WordPress course videos? Google Forms tutorials? template sites?).
* Country coverage: only SA and EG were probed. Maghreb (MA/DZ/TN) and Gulf (AE/KW/QA) may differ (some completions already show Tunisian/Algerian wording).
* Unknown whether WordPress's own Arabic community uses «ووردبريس» or «وردبريس» in search: Google merges them, so follow the UI spelling.
