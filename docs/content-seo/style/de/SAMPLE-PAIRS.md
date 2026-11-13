# EN → DE sample pairs (good vs. avoid)

How to read: each pair shows the English source, an **Avoid** version (real examples from https://de.whitestudio.team/ are marked with the URL; constructed ones are marked "constructed") and the **Good** version in the target style (`STYLE-GUIDE.md`: du, UI-exact labels, ≤ 60-character titles, German SEO formulas). UI labels in Good examples exist in `UI-STRINGS.csv`; UI **paths** and plan statements are examples and must be re-verified on a 4.3.1 install (UNVERIFIED).

---

### 1. How-to title: keyword first, no EN word order

- **EN:** How to Create a Responsive Contact Form in WordPress (2025 Guide)
- **Avoid** (77 chars, https://de.whitestudio.team/blog/create-responsive-contact-form-wordpress-2025-guide/): `So erstellst du ein responsives Kontaktformular in WordPress (2025 Anleitung)`
- **Good** (57): `Responsives Kontaktformular in WordPress erstellen (2026)`
- **Why:** the searched compound "Kontaktformular … erstellen" is in the first 40 characters; "(2025 Anleitung)" copies English word order ("2025 Guide"); the year stays only because people search it and is never in the slug.

### 2. Calque "Wie man …" and wrong capitalisation

- **EN:** How to Import Files from Easy Form Builder into Excel (caption: "How to Import Non-English Easy Form Builder Files into Excel")
- **Avoid** (https://de.whitestudio.team/document/how-import-files-from-easy-form-builder-excel/): `Wie man Nicht-Englische Easy Form Builder Dateien in Excel importiert`
- **Good** (53): `Easy-Form-Builder-Export korrekt in Excel importieren`
- **Why:** "Wie man …" is a literal "How to"; "Nicht-Englische" must not be capitalised mid-phrase; brand + noun = full hyphen coupling, as the UI does ("Easy-Form-Builder-Bericht"). Mention non-Latin text in the first paragraph.

### 3. Nominalised or untranslated step heading

- **EN:** Adding a New Form
- **Avoid** (https://de.whitestudio.team/document/how-to-create-your-first-form-with-easy-form-builder/): `Adding a New Form` (left in English) and the list entry `Hinzufügen eines neuen Formulars`
- **Good:** `Schritt 1: Neues Formular hinzufügen`
- **Why:** steps are headed "Schritt N: <Infinitiv-Phrase>" (102 uses on the site already); gerund-nouns read like legal German; never leave EN headings.

### 4. Question in "ich" form + article before the brand

- **EN:** How to Create a Payment Form in Easy Form Builder?
- **Avoid** (https://de.whitestudio.team/document/how-to-create-a-payment-form-in-easy-form-builder/): `Wie erstelle ich ein Zahlungsformular im Easy Form Builder?`
- **Good** (48): `Zahlungsformular mit Easy Form Builder erstellen`  (alternative with "So": `So erstellst du ein Zahlungsformular mit Easy Form Builder`, 58)
- **Why:** H1 formulas are noun-first or "So … du"; "ich" belongs only in FAQ questions; the brand takes no article ("in/mit Easy Form Builder").

### 5. Sie → du in a step

- **EN:** Click Save, then copy the shortcode.
- **Avoid** (constructed; typical of 33 Sie articles): `Klicken Sie auf Speichern und kopieren Sie anschließend den Shortcode.`
- **Good:** `Klicke auf „Speichern“ und kopiere danach den Shortcode.`
- **Why:** du everywhere (D1); UI label in „…“ exactly as in the UI („Speichern“).

### 6. UI label drift

- **EN:** Click Check Email Server and wait for the test to finish.
- **Avoid** (article wording, https://de.whitestudio.team/document/how-to-activate-pro-version-easy-form-builder-plugin/): `„E-Mail-Server überprüfen“`
- **Good:** `Klicke auf „E-Mail-Server prüfen“ und warte, bis der Test abgeschlossen ist.`
- **Why:** UI string "Check Email Server" = „E-Mail-Server prüfen“ (`UI-STRINGS.csv`). Only 26 % of quoted labels in current articles match the UI exactly.

### 7. Nested quotation marks

- **EN:** Turn on "Force the sender address in the 'From' field".
- **Avoid** (https://de.whitestudio.team/document/send-email-using-smtp-plugin/): `„E-Mail-Adresse des Absenders im "Von"-Feld erzwingen“`
- **Good:** `„E-Mail-Adresse des Absenders im ‚Von‘-Feld erzwingen“`
- **Why:** German outer „…“, inner ‚…‘; straight quotes only inside code.

### 8. UI path with English fallback

- **EN:** Go to Easy Form Builder → Settings → Email Settings.
- **Avoid** (constructed): `Gehe zu Easy Form Builder → Settings → Email Settings.`
- **Good:** `Öffne Easy Form Builder → Einstellungen → E-Mail-Einstellungen (englische Oberfläche: Easy Form Builder → Settings → Email Settings).`
- **Why:** German UI labels first, English once for English-UI users - the pattern the newest posts already use (https://de.whitestudio.team/document/set-up-spf-record-for-wordpress-email/). Verify the path in the panel.

### 9. Brand with article and mixed compound

- **EN:** Install the Easy Form Builder plugin and open the Easy Form Builder dashboard.
- **Avoid** (pattern from 106 + 36 uses): `Installiere das Easy Form Builder-Plugin und öffne das Dashboard im Easy Form Builder.`
- **Good:** `Installiere das Plugin Easy Form Builder und öffne in Easy Form Builder das Bedienfeld.`
- **Why:** no article/declension for the brand; avoid the mixed "Easy Form Builder-Plugin" (if a compound is needed: "Easy-Form-Builder-Plugin"); the main menu item is „Bedienfeld“ in the UI, not "Dashboard"/"Panel".

### 10. One term for one thing (Button / Schaltfläche, Icon / Symbol)

- **EN:** Click the Submit button, then change the button color and the icon.
- **Avoid** (mixes terms in one article, https://de.whitestudio.team/document/how-to-customize-the-submit-button/): `Klicke auf die Schaltfläche „Absenden“ und ändere dann die Button-Farbe und das Symbol.`
- **Good:** `Klicke auf den Button „Absenden“ und ändere die Button-Farbe und das Icon.`
- **Why:** the UI says "Button-Farbe", "Button-Text", "Icon" (`UI-STRINGS.csv`); decide per article and keep it (glossary: Button, Icon).

### 11. Plan claim: verify, use plan labels

- **EN:** Q: Do I need the Pro version to use Stripe payments? A: Yes, Stripe and all payment-related features are part of the Pro version.
- **Avoid** (https://de.whitestudio.team/blog/wordpress-stripe-payment-form-plugin/): `F: Benötige ich die Pro-Version für Stripe-Zahlungen? A: Ja, Stripe und alle Zahlungsfunktionen sind nur in der Pro-Version enthalten.`
- **Good** (question 49 chars): `Brauche ich die Pro-Version für Stripe-Zahlungen?` → `Nein. Stripe und PayPal sind im Tarif Free Plus enthalten.` **(verify against 4.3.1 before publishing)**
- **Why:** the German pricing page (https://de.whitestudio.team/pricing/) says Stripe and PayPal come with Free Plus at no cost; the article contradicts it. Never translate a stale plan claim; write plan names as Free / Free Plus / Pro.

### 12. Number, date, currency formats

- **EN:** Basic plan: $27/year (regular $49). Updated May 5, 2026. 40% off.
- **Avoid** (constructed EN copy): `Basic-Tarif: $27/Jahr (regulär $49). Aktualisiert 05/05/2026. 40%.`
- **Good:** `Basic-Tarif: 27 $ pro Jahr (regulär 49 $). Stand: 5. Mai 2026. 40 % Rabatt.`
- **Why:** number + (no-break) space + symbol, long date with dot, "40 %" with space; copy prices from `/pricing/` at publication time (currency question is open).

### 13. FAQ heading

- **EN:** FAQ
- **Avoid** (https://de.whitestudio.team/blog/wordpress-stripe-payment-form-plugin/, https://de.whitestudio.team/document/how-to-set-up-form-notification-emails-in-easy-form-builder/): `💬 Häufige Fragen (FAQ)` / `5. Häufig gestellte Fragen`
- **Good:** H2 `Häufig gestellte Fragen`, then H3 `Kann ich das Formular auch ohne Plugin-Update testen?` (question in "ich"), answer starts with the direct answer in du form.
- **Why:** majority wording on the site (13 uses), no emoji, no stray numbering.

### 14. Alt text

- **EN:** alt="Email settings tab" (or empty)
- **Avoid** (80 % of current alts: `alt=""` 249×, `alt="WhiteStudio.team"` 166×, no alt 120× of 611): `alt="WhiteStudio.team"`
- **Good:** `alt="Der Tab „E-Mail-Einstellungen“ mit dem Button „E-Mail-Server prüfen“"`
- **Why:** describes what the reader needs to see, quotes the UI exactly, no "Screenshot von".

### 15. Meta description

- **EN:** Learn how to customize the submit button in Easy Form Builder to match your site's design and improve submission rates …
- **Avoid** (copy of the intro, no keyword/CTA, 154 chars; https://de.whitestudio.team/document/how-to-create-your-first-form-with-easy-form-builder/): `Easy Form Builder ist ein leistungsstarkes und nützliches Plugin zur Formularerstellung, mit dem du ganz einfach professionelle Formulare erstellen kannst`
- **Good** (149): `Erstelle dein erstes WordPress-Formular mit Easy Form Builder: Felder per Drag-and-drop anordnen, Einstellungen anpassen und per Shortcode einbinden.`
- **Why:** keyword + concrete result + du-address, 140-155 characters; drop "leistungsstark/nützlich/ganz einfach".

### 16. Direct-answer opener ("Kurz gesagt")

- **EN:** To stop form spam without CAPTCHA, combine a honeypot, server-side validation and rate limiting.
- **Avoid** (constructed; filler openers such as "In diesem Artikel zeigen wir …" or "Easy Form Builder ist ein …" start the intro of 32 existing articles by a combined pattern count): `In diesem Artikel zeigen wir Ihnen, wie Sie Spam in Ihren WordPress-Formularen verhindern können.`
- **Good** (30 words): `Kurz gesagt: Ohne CAPTCHA schützt du dein Kontaktformular mit drei Bausteinen: einem Honeypot-Feld, einer serverseitigen Prüfung und einer Ratenbegrenzung. In Easy Form Builder aktivierst du sie unter „Formularsicherheit und Spamschutz“.`
- **Why:** answer first (40-60 words once plan, version and one more fact are added), searched compound "Kontaktformular", UI label exact; "Kurz gesagt:" is already used in 7 articles.

### 17. Troubleshooting sentence

- **EN:** If you don't receive emails, check your spam folder first.
- **Avoid** (constructed, Sie + nominal): `Wenn Sie keine E-Mails erhalten, sollten Sie zunächst Ihren Spam-Ordner überprüfen.`
- **Good:** `Kommt keine E-Mail an, prüfe zuerst den Spam-Ordner.`
- **Why:** conditional without "wenn", imperative du, 8 words, term "Spam-Ordner" as in the UI.

### 18. Marketing calque / monster compound

- **EN:** Elevate Your Form-Building Experience with Easy Form Builder's version 3.7.21!
- **Avoid** (https://de.whitestudio.team/blog/elevate-your-form-building-experience-with-easy-form-builders-version-3-7-21/): `Verbessere deine Formularerstellungserfahrung mit Easy Form Builder Version 3.7.21!`
- **Good** (60): `Easy Form Builder 3.7.21: Formulare noch einfacher erstellen`
- **Why:** "Formularerstellungserfahrung" is a calque of "form-building experience"; release titles follow "Easy Form Builder x.y.z: <Nutzen>" (no exclamation mark, no "Elevate/Discover").

### 19. Pipe separator and nominal title

- **EN:** Setting Minimum Value Requirement for Input Fields | Easy Form Builder Tutorial
- **Avoid** (https://de.whitestudio.team/blog/minimum-value-requirement-input-fields-easy-form-builder/, 92 chars): `Einstellung der Anforderung von Mindestwerten für Eingabefelder | Easy Form Builder Tutorial`
- **Good** (39): `Mindestwert für Eingabefelder festlegen`
- **Why:** no pipes, no "Anforderung" bureaucracy, verb-based, ≤ 60 characters.

### 20. Gender-neutral wording without signs

- **EN:** Visitors can find their submission with the confirmation code.
- **Avoid** (constructed; no gender signs exist on the site, keep it that way): `Besucher*innen können ihren Eintrag mit dem Bestätigungscode wiederfinden.`
- **Good:** `Wer das Formular ausgefüllt hat, findet den Eintrag mit dem Bestätigungscode wieder.`
- **Why:** consistent with the existing content (no signs), neutral through rephrasing, exact term "Bestätigungscode".

### 21. DSGVO claim

- **EN:** GDPR-compliant forms
- **Avoid** (constructed): `Garantiert DSGVO-konforme Formulare`
- **Good:** `Formulare DSGVO-freundlich einrichten: Einwilligung, Speicherung und Löschung im Blick (keine Rechtsberatung)`
- **Why:** no compliance promise; use "DSGVO", not "GDPR"; the operator stays responsible. The H1 may still contain the search phrase "DSGVO-konform" if the owner accepts it (open question 4).

### 22. Compound keyword spelling

- **EN:** WordPress contact form spam protection
- **Avoid** (constructed): `WordPress Kontakt Formular Spam Schutz`
- **Good** (44): `Spamschutz für das WordPress-Kontaktformular`
- **Why:** closed compounds "Kontaktformular" and "Spamschutz"; hyphen between the proper noun and the compound ("WordPress-Kontaktformular").
