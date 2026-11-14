# Builds en/de/ar/fa SRT for R47 with identical cue structure. Out-times are extended into
# the silent gaps (<=1.5 s drift, >=0.08 s before the next cue) so translated lines get reading time.
import sys, os
OUT = sys.argv[1]
T = [  # (in, out)
 ("00:00:00,000","00:00:02,720"),
 ("00:00:02,800","00:00:06,980"),
 ("00:00:07,060","00:00:08,320"),
 ("00:00:08,400","00:00:11,840"),
 ("00:00:11,920","00:00:16,847"),
 ("00:00:16,927","00:00:20,360"),
 ("00:00:20,440","00:00:23,000"),
 ("00:00:23,080","00:00:28,980"),
 ("00:00:30,380","00:00:34,880"),
 ("00:00:34,960","00:00:37,120"),
 ("00:00:37,200","00:00:42,540"),
 ("00:00:43,940","00:00:48,053"),
 ("00:00:48,133","00:00:49,700"),
 ("00:00:49,780","00:00:54,700"),
]
EN = [
 "Take Stripe payments with a WordPress\nform.",
 "Easy Form Builder's Stripe add-on\nruns on the Free Plus plan, no Pro",
 "license needed.",
 "Open Add-ons and install the Stripe\nPayment Add-on.",
 "Next, go to Panel, Settings,\nPayments, paste your Public Key and",
 "Secret Key, and click Save.",
 "Under Create, pick Payment Form.",
 "Add Name, Email, and a Payment Single\nChoose field with your prices.",
 "Then add the Stripe field, and click\nSave to get the shortcode.",
 "Put the shortcode on any page.",
 "Visitors choose an option, enter\ntheir card, and click Pay Now.",
 "Each payment is verified with Stripe\non your server and shows as Completed",
 "under Stripe Payments.",
 "Get Easy Form Builder free on\nWordPress.org.",
]
DE = [
 "Stripe-Zahlungen mit einem\nWordPress-Formular.",
 "Das Stripe-Add-on läuft im Plan\nFree Plus, ohne Pro-Lizenz.",
 "Ganz ohne Lizenz.",
 "Öffne „Add-ons“ und installiere\n„Stripe Payment Add-on“.",
 "Geh zu Panel › Settings › Payments\nund füge Public Key und",
 "Secret Key ein.\nKlicke auf „Save“.",
 "Unter „Create“: „Payment Form“.",
 "Füge Name, Email und ein Feld\n„Payment Single Choose“ mit Preisen hinzu.",
 "Dann das Feld „Stripe“ hinzufügen\nund mit „Save“ den Shortcode holen.",
 "Shortcode auf eine Seite setzen.",
 "Besucher wählen eine Option, geben\ndie Karte ein und klicken „Pay Now“.",
 "Jede Zahlung wird auf deinem Server\nmit Stripe geprüft und erscheint",
 "unter „Stripe Payments“.",
 "Easy Form Builder gibt es kostenlos\nauf WordPress.org.",
]
AR = [
 "استقبل مدفوعات Stripe\nعبر نموذج ووردبريس.",
 "ملحق Stripe في منشئ النماذج السهل\nيعمل على خطة Free Plus،",
 "دون ترخيص Pro.",
 "افتح Add-ons وثبّت\nملحق Stripe Payment Add-on.",
 "ثم اذهب إلى Panel ثم Settings ثم Payments،\nوالصق Public Key و",
 "Secret Key، ثم اضغط Save.",
 "من Create اختر Payment Form.",
 "أضف Name وEmail وحقل\nPayment Single Choose مع أسعارك.",
 "ثم أضف حقل Stripe واضغط Save\nلتحصل على الكود القصير.",
 "ضع الكود القصير في أي صفحة.",
 "يختار الزائر خيارًا ويُدخل بطاقته\nثم يضغط Pay Now.",
 "يُتحقق من كل دفعة لدى Stripe على خادمك\nوتظهر بحالة Completed",
 "في قائمة Stripe Payments.",
 "حمّل منشئ النماذج السهل مجانًا\nمن WordPress.org.",
]
FA = [
 "دریافت پرداخت Stripe\nبا یک فرم وردپرس.",
 "افزودنی Stripe در Easy Form Builder\nروی پلن Free Plus کار می‌کند،",
 "بدون لایسنس Pro.",
 "Add-ons را باز کنید و\nStripe Payment Add-on را نصب کنید.",
 "سپس به Panel،‏ Settings،‏ Payments بروید\nو Public Key و",
 "Secret Key را بچسبانید و Save بزنید.",
 "در Create، گزینهٔ Payment Form.",
 "Name،‏ Email و فیلد Payment Single Choose\nرا با قیمت‌ها اضافه کنید.",
 "بعد فیلد Stripe را اضافه کنید\nو با Save شورت‌کد را بگیرید.",
 "شورت‌کد را در هر صفحه بگذارید.",
 "بازدیدکننده گزینه را انتخاب، کارت را\nوارد و روی Pay Now کلیک می‌کند.",
 "هر پرداخت روی سرور شما با Stripe\nبررسی می‌شود و در Stripe Payments",
 "وضعیتش Completed می‌شود.",
 "Easy Form Builder را رایگان\nاز WordPress.org بگیرید.",
]
def rlm(c): return "\n".join("\u200f"+l for l in c.split("\n"))
AR=[rlm(c) for c in AR]; FA=[rlm(c) for c in FA]
for lang, cues in (("en",EN),("de",DE),("ar",AR),("fa",FA)):
    assert len(cues)==len(T)
    s="".join(f"{i}\n{a} --> {b}\n{c}\n\n" for i,((a,b),c) in enumerate(zip(T,cues),1))
    open(os.path.join(OUT,f"r47-stripe.{lang}.srt"),"w",encoding="utf-8",newline="\n").write(s.rstrip("\n")+"\n")
