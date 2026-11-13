# EN → FA sample pairs: good versus avoid

> Rules are in [`STYLE-GUIDE.md`](STYLE-GUIDE.md); terms in [`GLOSSARY.csv`](GLOSSARY.csv); UI labels in [`UI-STRINGS.csv`](UI-STRINGS.csv).
> The English sources are real sentences from whitestudio.team (Nov 2026 email series and others) or from the plugin UI. "Good" follows the guide; "Avoid" imitates mistakes actually found on easyformbuilder.ir or typical machine output.
> **UNVERIFIED:** all Persian below was written by an AI assistant and has not been reviewed by a native editor. Treat it as a style reference, not as publishable copy.
> In "Good", UI labels are shown **bold** and come from `UI-STRINGS.csv`; ZWNJ (U+200C) is present where the rules require it.

---

## 1. Direct answer with code

**EN:** Short answer: add one TXT record named `_dmarc` with the value `v=DMARC1; p=none` in your DNS panel.

**Good:** پاسخ کوتاه: در پنل DNS خود یک رکورد TXT با نام `_dmarc` و مقدار `v=DMARC1; p=none` اضافه کنید.

**Avoid:** پاسخ کوتاه: شما باید یک رکورد TXT را که _dmarc نامیده می‌شود با ارزش v=DMARC1; p=none در پنل دی‌ان‌اس خودتان اضافه نمایید.

**Why:** «ارزش» is the wrong sense of "value" (use «مقدار»); passive/relative clause is a calque; unnecessary «شما» and «خودتان»; stiff «نمایید»; DNS is spelled out phonetically; the record is not in code format.

---

## 2. Negation of scope

**EN:** This is a domain setting, not a plugin setting.

**Good:** این یک تنظیم دامنه است، نه تنظیم افزونه.

**Avoid:** این یک تنظیمات دامنه است و تنظیمات پلاگین نیست.

**Why:** «تنظیمات» is plural (use singular «تنظیم» for one setting); «پلاگین» is forbidden (glossary); the negative clause is clumsy.

---

## 3. Navigation path

**EN:** Open **Easy Form Builder → Panel → Settings → Email Settings** and press **Check Email Server**.

**Good:** **فرم‌ساز آسان ← پنل ← تنظیمات ← تنظیمات ایمیل** را باز کنید و روی **بررسی سرور ایمیل** کلیک کنید.

**Avoid:** Easy Form Builder > Panel > Settings > Email Settings را باز کرده و Check Email Server را بزنید.

**Why:** Untranslated labels inside an RTL sentence reorder unpredictably; `>` is mirrored by the bidi algorithm; «بزنید» is colloquial; labels are not the UI strings («پنل»، «تنظیمات ایمیل»، «بررسی سرور ایمیل»).

---

## 4. Button instruction

**EN:** Press **Save**.

**Good:** روی **ذخیره** کلیک کنید.

**Avoid:** دکمهٔ ذخیره را فشار دهید.

**Why:** The word «کلیک» appears 178 times on the site as the standard way to instruct a click; «فشار دهید» is a literal calque of "press" and reads stiff in a web UI. Keep the label bold.

---

## 5. Option that exists in the UI

**EN:** You can turn on silentCAPTCHA Spam Protection for each form.

**Good:** می‌توانید برای هر فرم، **محافظت در برابر هرزنامه با silentCAPTCHA** را فعال کنید.

**Avoid:** شما می توانید silentCAPTCHA Spam Protection را برای هر فرم روشن کنید.

**Why:** The label must be the UI string; «می‌توانید» needs ZWNJ; the UI says «فعال کنید» for switches, not «روشن کنید».

---

## 6. Numbers in prose

**EN:** The test takes about 5 minutes and returns a score out of 100.

**Good:** این آزمایش حدود ۵ دقیقه طول می‌کشد و امتیازی از ۱۰۰ برمی‌گرداند.

**Avoid:** این آزمایش در حدود 5 دقیقه زمان می برد و یک امتیاز از 100 بر می گرداند.

**Why:** Persian digits in prose; ZWNJ after «می»; «برمی‌گرداند» is one verb; «در حدود … زمان می‌برد» is wordier than «طول می‌کشد».

---

## 7. Version numbers

**EN:** Tested with Easy Form Builder 4.3.1 and WordPress 7.1.

**Good:** آزمایش‌شده با فرم‌ساز آسان نسخهٔ 4.3.1 و وردپرس 7.1

**Avoid:** تست شده با فرم ساز آسان نسخه ۴٫۳٫۱ و وردپرس ۷٫۱

**Why:** Version numbers stay Latin; the Persian decimal separator «٫» breaks them; «تست شده» needs ZWNJ and the brand needs ZWNJ too.

---

## 8. Plan claim (copied from the English article)

**EN:** Stripe and PayPal are included in Free Plus and Pro since version 4.3.1.

**Good:** از نسخهٔ 4.3.1، استرایپ (Stripe) و پی‌پال (PayPal) در پلن‌های رایگان پلاس و ویژه گنجانده شده‌اند.

**Avoid:** استریپ و پی پال فقط در نسخه پرو موجود است.

**Why:** The Avoid sentence reproduces the wrong pre-4.3.1 claim ("only in Pro"); «استریپ» differs from the UI («استرایپ»); «پی پال» lacks ZWNJ; «پرو» is forbidden (use «ویژه»).

---

## 9. Confirmation Code (term conflict)

**EN:** Every submission gets a Confirmation Code.

**Good:** هر پاسخ یک **کد تأیید** (کد رهگیری) دریافت می‌کند.

**Avoid:** به هر ورودی یک کد ترکینگ داده می‌شود.

**Why:** The label is «کد تأیید» (UI); the gloss «کد رهگیری» helps search; «ترکینگ» is an unnecessary loanword; «ورودی» means an entry in a list, «پاسخ» is the UI word for a submission.

---

## 10. Spam folder

**EN:** Your form emails land in the spam folder.

**Good:** ایمیل‌های فرم شما به پوشهٔ هرزنامه (اسپم) می‌روند.

**Avoid:** ایمیل های فرم شما اسپم می شوند.

**Why:** ZWNJ in «ایمیل‌های» and «می‌شوند»; ezafe «پوشهٔ» (the UI style); «اسپم می‌شوند» is slang that turns "spam" into a verb. Use «هرزنامه» as the primary word (UI) with «اسپم» once as a search gloss.

---

## 11. Question and punctuation

**EN:** Do I still need SPF, DKIM and DMARC? Yes, for the best result.

**Good:** آیا همچنان به SPF، DKIM و DMARC نیاز دارم؟ بله، برای بهترین نتیجه.

**Avoid:** آیا هنوز SPF, DKIM و DMARC لازم دارم? بله, برای بهترین نتیجه.

**Why:** Persian comma «،» and question mark «؟»; Latin `?` and `,` appear in the old posts (10 and 12 times). Latin acronyms are fine, but separated by «،».

---

## 12. Quoting an on-screen message

**EN:** The result says “Needs setup”.

**Good:** نتیجه «نیازمند تنظیم» را نشان می‌دهد.

**Avoid:** نتیجه "Needs setup" را نشان می دهد.

**Why:** Guillemets «» for quotes (never straight quotes in Persian paragraphs); the status text exists in Persian in the UI («نیازمند تنظیم»); ZWNJ in «می‌دهد».

---

## 13. Field named in a sentence that ends with a Latin word

**EN:** Paste the key into the field called API Key.

**Good:** کلید را در فیلد **کلید API** جای‌گذاری کنید.

**Avoid:** کلید را در فیلد API Key وارد کنید.

**Why:** The UI label is «کلید API» (msgid `API KEY`); an English label left in the middle of an RTL sentence, followed by Persian text, creates a bidi hazard; «جای‌گذاری» (paste) is more precise than «وارد کنید» here. (When a sentence must end with a Latin word, see the RLM rule in the subtitle spec.)

---

## 14. Date

**EN:** Last verified: November 13, 2026.

**Good:** آخرین بررسی: ۲۲ آبان ۱۴۰۵ (2026-11-13)

**Avoid:** آخرین بررسی: 2026/11/13

**Why:** Persian readers expect the Jalali date; the ISO date in parentheses keeps the English metadata; Latin slashes without a calendar are ambiguous.

---

## 15. Register

**EN:** Don't worry, it takes two minutes.

**Good:** نگران نباشید؛ فقط دو دقیقه طول می‌کشد.

**Avoid:** نگران نباش، فقط دو دقیقه طول می‌کشه.

**Why:** Formal-friendly «شما» address; no colloquial «می‌کشه»; semicolon «؛» joins two clauses.

---

## 16. Plugin versus add-on

**EN:** Install the Stripe add-on from the Add-ons screen.

**Good:** از بخش **افزودنی‌ها**، افزودنی پرداخت Stripe را نصب کنید.

**Avoid:** از قسمت اد-آن‌ها پلاگین استرایپ را نصب کنید.

**Why:** «افزودنی‌ها» is the screen name; plugin = «افزونه», add-on = «افزودنی», and the add-on card in the UI is «افزودنی پرداخت Stripe»; «اد-آن» and «پلاگین» are forbidden.

---

## 17. Counts of fields

**EN:** The Free version includes 20 form fields.

**Good:** نسخهٔ رایگان ۲۰ فیلد فرم دارد.

**Avoid:** نسخه رایگان 20 المان فرم دارد.

**Why:** Persian digits in prose; «فیلد» (UI) not «المان»; ezafe «نسخهٔ». Re-verify the number in code before use (see English style guide section 6).

---

## 18. Step heading

**EN:** Step 2 — open your DNS records

**Good:** مرحله ۲ — رکوردهای DNS خود را باز کنید

**Avoid:** قدم دوم: بازکردن رکوردهای دی ان اس

**Why:** Same step formula as English («مرحله N — …» with a spaced em dash and a verb phrase); «مرحله» for tutorial steps (reserve «گام» for the Step field); «بازکردن» (noun, attached) is wrong and DNS stays Latin.

---

## 19. Frequently asked question

**EN:** Does this change anything on my website? No. It is a public note about your domain, read only by mailboxes.

**Good:** آیا این کار چیزی را در وب‌سایت من تغییر می‌دهد؟ خیر. این فقط یک یادداشت عمومی دربارهٔ دامنهٔ شماست که صندوق‌های ایمیل آن را می‌خوانند.

**Avoid:** آیا این چیزی در سایت من عوض می کند ؟ نه. این یک یادداشت عمومی در مورد دامنه شما هست که فقط توسط صندوق ها خوانده می شود.

**Why:** No space before «؟»; «خیر» matches the UI; «دربارهٔ» not «در مورد»; passive «توسط … خوانده می‌شود» is a calque; ZWNJ throughout; ezafe «دامنهٔ شماست».

---

## 20. English mixed into a list of technologies

**EN:** Works with LiteSpeed Cache, WP Rocket and W3 Total Cache.

**Good:** با افزونه‌های کش LiteSpeed Cache، WP Rocket و W3 Total Cache سازگار است.

**Avoid:** با پلاگین های کش LiteSpeed Cache, WP Rocket و W3 Total Cache کار میکنه.

**Why:** «افزونه‌های کش» (term and ZWNJ); product names stay Latin and are separated by the Persian comma «،»; formal verb «سازگار است».
