# German keyword localisation (transcreation) with Google and YouTube autosuggest

Status: method + demonstration, captured 2026-11-13. Applies to every German article that follows an English original with the same slug.

## 1. Principle

The EN keyword in `content-queue.csv` is a hypothesis about **meaning**, not a string to translate. For each article the German primary keyword is chosen from what German searchers actually type, in the form they type it:

- they use the compound noun **"Kontaktformular"** far more often than "Formular" (see section 5);
- for plugin-related searches they keep the **English plugin name and English modifiers** ("contact form 7 alternative", "wpforms conditional logic", "wordpress forms to google sheets");
- long German task phrases that look natural to a translator ("WordPress-Formular-Spam stoppen", "Formulareinträge exportieren") often return **no suggestion at all**;
- some German head words belong to a different intent (generic "Spendenformular" = donation receipt template for associations, generic "Bewerbungsformular" = Word/PDF template).

Output of this step, per article: `primary keyword`, 2-4 `secondary keywords`, `H1/title` (≤ 60 characters), and an evidence tier. The slug stays the EN slug (never localised).

## 2. Method (repeat for each article)

### 2.1 Endpoints

```
Google : https://suggestqueries.google.com/complete/search?client=firefox&hl=de&gl=de&q=<query>
         -> JSON  ["<query>", ["suggestion", ...], ...]
YouTube: https://suggestqueries.google.com/complete/search?client=youtube&ds=yt&hl=de&gl=de&q=<query>
         -> JSONP window.google.ac.h([...]) ; suggestions are item[0] of each entry
```

Notes: `gl=de` made no visible difference to results in testing (`hl=de` with and without `gl=de` returned identical lists for 5 head queries). The proxy exit location is not controlled, so treat results as "German-language, location-neutral". The endpoints are public and unauthenticated; no login or form submission is involved.

### 2.2 Steps

1. **Concept map (5 min).** Write the EN keyword in plain words, then list 4-8 German phrasings: (a) literal translation, (b) German compound-noun version ("Kontaktformular", "Spamschutz", "Spendenformular"), (c) competitor/plugin-anchored version ("contact form 7 …", "wpforms …"), (d) problem-wording ("funktioniert nicht", "kommt nicht an"), (e) task-wording ("erstellen", "einbinden", "exportieren").
2. **Probe each phrasing** against Google. Record the number of suggestions and the first 5-10.
3. **Prefix ladder.** Autosuggest is prefix completion: if the full phrase returns nothing, drop the last word and query again until something returns (`wordpress formular google sheets` → falls back to `wordpress formular`). The **longest prefix that returns completions** and what it completes to is the evidence. An empty result for the exact phrase is itself a data point: very low demand for that wording.
4. **Alphabet expansion** on the best 1-2 prefixes: `<prefix> a` … `<prefix> z` (26 calls) and `wie <prefix>`, `kostenlos <prefix>`.
5. **YouTube pass** on the best phrasings (video demand; the plan has a 60-second video per article). YouTube suggestions are noisier (Word/Excel/Google Forms hits) - use them to confirm, never to choose.
6. **Intent check.** Read the completions: are they WordPress/plugin searches or a different thing (templates, PDFs, MS Access, associations)? Drop the generic head if its completions are off-topic.
7. **Choose by tier:**
   - **A** - the exact German phrase (or a one-word variant) appears as a completion → use it as primary.
   - **B** - a shorter head + modifier appears ("wordpress spendenformular", "kontaktformular spam") → primary = head, add the task verb in the title.
   - **C** - only English/plugin-anchored completions exist → primary = the English-anchored phrase written the way Germans write it ("Contact Form 7 Alternative"); the German explanation goes in H1/H2.
   - **D** - no completions → primary = intent-led German task phrase, flagged **low confidence**; verify with Google Search Console after 30 days.
8. **Write the title** with the primary keyword in the first 40 characters, sentence case, ≤ 60 characters (formulas in `STYLE-GUIDE.md` section 7).
9. **Cannibalisation check** against the 86 live German posts and `content-queue.csv` (e.g. F1 vs the existing "Bedingte Logik … Leitfaden").
10. **Log** the evidence in the article's QA report: probes, completions, tier, date.

### 2.3 Pitfalls found while testing

- Empty arrays are normal for long-tail German phrases. In 10 of 12 topics the literal translation of the EN keyword returned zero suggestions.
- German "Formular" alone is weak and ambiguous (Word/PDF/Access/Google Forms); "Kontaktformular" and plugin names carry the WordPress intent.
- YouTube returns tutorial noise ("word felder einfügen", "excel daten in word formular übertragen").
- Autosuggest does not give volumes. It is a relative demand signal. Volumes and competition need Google Search Console / Keyword Planner (not available in this session).
- Personalisation/IP variance: repeat if a decision is close.
- Etiquette: ≥ 0.3 s between calls, no parallel hammering, no scraping at scale; cache results with the date.

### 2.4 Reproducible snippet

```python
import json, subprocess, urllib.parse, time
def g(q):
    u = "https://suggestqueries.google.com/complete/search?client=firefox&hl=de&gl=de&q=" + urllib.parse.quote(q)
    out = subprocess.run(["curl","-sS","-m","30","-L","-A","Mozilla/5.0",u],capture_output=True,text=True).stdout
    time.sleep(0.35)
    return json.loads(out)[1]
def ladder(phrase):
    w = phrase.split()
    for n in range(len(w), 1, -1):
        r = g(" ".join(w[:n]))
        if r: return " ".join(w[:n]), r
    return None, []
```

## 3. Cross-topic findings

1. German demand is expressed as `wordpress kontaktformular …` (10 completions: erstellen, spam, plugin kostenlos, smtp, spam schutz, email kommt nicht an, einfügen, captcha, …), not `wordpress formular …` + niche word (most niche combinations return nothing).
2. English plugin names and English modifiers dominate completions: `contact form 7 alternative`, `contact form 7 vs wpforms`, `wpforms conditional logic`, `contact form 7 excel export`, `wordpress forms to google sheets`.
3. "einbinden" has a completion (`wordpress formular einbinden`), "einbetten" has none → use "einbinden" in titles (glossary).
4. Spam: `wordpress spamschutz`, `wordpress spam schutz` both complete; `kontaktformular spamschutz ohne captcha` exists → "Kontaktformular" + closed compound "Spamschutz".
5. Multi-page forms: `wordpress formular mehrseitig` completes to `wordpress mehrseitiges formular`; "mehrstufig" returns nothing. The UI says "mehrstufig" - use "mehrseitiges Formular (mehrstufig)" at first mention in multi-step articles (one data point, indicative).
6. Brand collision: `easy form builder` completes to `wpforms easy form builder for wordpress …` (WPForms' own tagline) - searches for the generic phrase can surface WPForms. Always pair the brand with "WhiteStudio" or "Easy Form Builder Plugin" in meta/descriptions where useful.

## 4. Demonstration: 12 topics from `content-queue.csv`

Notation: "→" = completions returned. "0" = empty array. Evidence tier per section 2.2.

### T1 - C1 "How to Migrate from Contact Form 7 to Easy Form Builder (One-Click Import)"  (EN keyword: `migrate contact form 7 wordpress`)

| Item | Result |
|---|---|
| Literal DE probes | `contact form 7 migrieren` 0 · `… importieren` 0 · `… wechseln` 0 · `… ersetzen` → falls back to head `contact form 7` · `… umziehen` 0 · `… umstieg` → head |
| Google, head | `contact form 7` → captcha, honeypot, wordpress, spam schutz, recaptcha, styling, spam protection, placeholder, templates |
| English-anchored | `contact form 7 alternative` → `contact form 7 alternative`, `… alternatives` · `contact form 7 export` → `excel export`, `export to csv`, `export import` |
| YouTube | `contact form 7 tutorial deutsch`, `contact form 7 wordpress tutorial deutsch`, `contact form 7 design anpassen` |
| Reading | No German "migrieren/umziehen" demand visible; people search the plugin name plus English words. The tutorial format is popular in German on YouTube. |
| **Primary (tier D, low)** | `Contact Form 7 importieren` |
| Secondary | `Contact Form 7 Alternative` (tier C - shared with B8), `Contact Form 7 Export Import` (tier C), `Contact Form 7 Tutorial deutsch` (video) |
| **Title (56)** | **Contact Form 7 Formulare importieren: Umstieg in 1 Klick** |
| Risk | Intent-led guess; verify in Search Console. Do not call it "migrieren" in the title (no demand evidence). |

### T2 - G1 "Send WordPress Form Entries to Google Sheets Without Zapier"  (EN: `wordpress form to google sheets`)

| Item | Result |
|---|---|
| Literal DE probes | `wordpress formular google sheets` → falls back to `wordpress formular` · `wordpress formular in google tabelle speichern` 0 · `wordpress formular google sheets ohne zapier` 0 · `formular daten google sheets` 0 |
| Better anchors | `wordpress google sheets formular` → `wordpress google sheets plugin`, `… api`, `… embed`, `wordpress form data to google sheets`, `wordpress forms to google sheets` · `wpforms google sheets` → addon/integration/connector · `contact form 7 google sheets` → connector/integration |
| YouTube | `google sheets wordpress`, `wordpress google sheets integration`, `formular daten in google tabellen` (YT only) |
| Reading | German searchers write "google sheets" in English and put "wordpress" first; "Formular" next to "Google Sheets" drifts to Google Forms / spreadsheet formulas (`google sheets formular erstellen`, `google sheets formula`). |
| **Primary (tier B/C)** | `WordPress Formular Google Sheets` (best proxy: `wordpress google sheets formular`) |
| Secondary | `Formulardaten in Google Sheets speichern`, `Google Sheets WordPress Plugin`, `ohne Zapier` (differentiator, demand not evidenced) |
| **Title (60)** | **WordPress-Formular mit Google Sheets verbinden (ohne Zapier)** |
| Risk | Avoid "Google Sheets Formular" alone (wrong intent). Use "Google Sheets (Google Tabellen)" once for the German product name (UI uses both). |

### T3 - B1 "Easy Form Builder vs Contact Form 7: Which Is Better in 2026?"  (EN: `easy form builder vs contact form 7`)

| Item | Result |
|---|---|
| Literal DE probes | `easy form builder vs contact form 7` 0 · `wordpress formular plugin vergleich` 0 · `bestes wordpress formular plugin` → `bestes formular plugin wordpress` |
| Pattern evidence | `contact form 7 vs` → wpforms, wpforms lite, fluent forms, forminator, gravity forms, ninja forms · `wpforms alternative` → `… free`, `… for wordpress` · `wpforms kostenlos` · `fluent forms vs wpforms` |
| Brand | `easy form builder` → `… wordpress`, `… by whitestudio`, `… free`, `wpforms easy form builder for wordpress` (collision) |
| YouTube | `contact form 7 vs wpforms`, `wpforms tutorial deutsch`, `wpforms deutsch` |
| Reading | Germans type "vs" (English) between plugin names, and "kostenlos"/"deutsch" as modifiers. No completion contains German "oder" or "Vergleich" (the probe `contact form 7 oder wpforms` was completed with `vs`/`or` forms). |
| **Primary (tier C)** | `Easy Form Builder vs Contact Form 7` |
| Secondary | `Contact Form 7 Alternative`, `Contact Form 7 vs WPForms` (competitor page), `kostenloses WordPress Formular Plugin` (weak) |
| **Title (52)** | **Easy Form Builder vs. Contact Form 7: Vergleich 2026** |
| Risk | Brand volume unknown; brand collision with WPForms. Use "vs." in titles, keep "vs" in the keyword log. |

### T4 - E1 "Stop WordPress Form Spam Without CAPTCHA"  (EN: `stop wordpress form spam without captcha`)

| Item | Result |
|---|---|
| Literal DE probes | `wordpress formular spam verhindern` 0 · `wordpress kontaktformular spam ohne captcha` 0 · `formular spam ohne captcha` 0 · `wordpress honeypot formular` 0 |
| Compound-noun probes | `kontaktformular spam` → `kontaktformular spamschutz`, `… spam`, **`kontaktformular spamschutz ohne captcha`**, `… spam vermeiden`, `… spamschutz wordpress` · `wordpress spam kontaktformular` → `wordpress kontaktformular spam schutz`, `… spam verhindern`, `wordpress elementor kontaktformular spam` · `wordpress spamschutz` → `… kontaktformular`, `wordpress formular spamschutz` · `wordpress honeypot` → `… plugin`, `… contact form 7`, `wordpress form honeypot` |
| English-anchored | `contact form 7 captcha` → `… dsgvo`, `… recaptcha without google`, `… cloudflare` |
| YouTube | `wordpress recaptcha`, `captcha wordpress`, `wordpress honeypot`, `contact form 7 spam` |
| Reading | Strong, specific German demand for "Kontaktformular Spamschutz ohne CAPTCHA"; DSGVO links to CAPTCHA (`contact form 7 captcha dsgvo`) - a good angle. |
| **Primary (tier A)** | `Kontaktformular Spamschutz ohne CAPTCHA` |
| Secondary | `Kontaktformular Spam vermeiden`, `WordPress Kontaktformular Spam verhindern`, `WordPress Honeypot`, `reCAPTCHA DSGVO` |
| **Title (52)** | **Kontaktformular-Spamschutz ohne CAPTCHA in WordPress** |
| Risk | Low. Make sure the hub (N3 "Form Security and Spam Hub") targets `WordPress Spamschutz`. |

### T5 - F1 "How to Show or Hide Form Fields Based on a User's Answer in WordPress"  (EN: `wordpress conditional fields`)

| Item | Result |
|---|---|
| Literal DE probes | `wordpress formular bedingte logik` 0 · `… felder ausblenden` 0 · `… bedingte felder` 0 · `conditional logic wordpress formular` 0 · `wpforms bedingte logik` 0 · `kontaktformular bedingte felder` 0 |
| Off-topic hit | `formular bedingte logik` → `access formular bedingte formatierung …` (Microsoft Access) |
| English-anchored | `contact form 7 conditional fields` → `… without plugin`, `… email`, `… plugin`, `… not working` · `wpforms conditional logic` → `… free`, `wpforms smart conditional logic` |
| YouTube | `contact form 7 conditional fields`, `wpforms conditional logic` |
| Reading | Germans use the English terms ("conditional fields/logic") with plugin names; the German "bedingte Logik" has no completions and collides with MS Access. |
| **Primary (tier C/D)** | `Felder in WordPress-Formularen ein- und ausblenden` (task) with `Conditional Logic` as secondary |
| Secondary | `Conditional Logic WordPress Formular`, `Contact Form 7 Conditional Fields Alternative`, `bedingte Logik` (only as UI term) |
| **Title (50)** | **Felder in WordPress-Formularen ein- und ausblenden** |
| Risk | Cannibalisation with the existing German pillar `/document/easy-form-builder-conditional-logic-complete-guide/` ("Bedingte Logik für WordPress-Formulare"): F1 = task page (show/hide), the pillar = overview. Link both ways. Do not use "bedingte Logik" alone without "WordPress". |

### T6 - K4 "Export Form Entries to CSV (Free in Every Version)"  (EN: `export wordpress form entries csv`)

| Item | Result |
|---|---|
| Literal DE probes | `wordpress formular einträge exportieren` 0 · `… einträge csv` 0 · `wordpress formulareinträge exportieren` 0 · `wordpress formular daten exportieren excel` 0 · `wordpress formulare exportieren csv` 0 · `wpforms einträge exportieren` 0 |
| English-anchored | `contact form 7 export` → `excel export`, `export to csv`, `export import` · `wpforms entries export` → `wpforms export entries` · `contact form 7 flamingo export` → flamingo variants |
| Noun test | `wordpress formular einträge` 0 · `… einsendungen` 0 · `… übermittlungen` 0 · `… nachrichten` 0 · `… anfragen` 0 |
| YouTube | no relevant German suggestion |
| Reading | No German phrasing has a completion; the demand that exists is English ("export to csv", "excel export") and plugin-anchored. |
| **Primary (tier D, low)** | `Formulareinträge exportieren` |
| Secondary | `CSV Export`, `Excel Export` (tier C: "excel export" appears), `Contact Form 7 Export` (competitor angle) |
| **Title (47)** | **Formulareinträge als CSV oder Excel exportieren** (60 with "in WordPress": `Formulareinträge in WordPress als CSV oder Excel exportieren`) |
| Risk | Intent-led guess. Entry noun is not evidence-based: keep the glossary choice "Eintrag/Formulareintrag". Link to the existing Excel-import article. |

### T7 - H1 "Accept Donations with a WordPress Form (Stripe or PayPal)"  (EN: `wordpress donation form`)

| Item | Result |
|---|---|
| Literal DE probes | `wordpress spendenformular erstellen` 0 · `… paypal` 0 · `… stripe` 0 · `wordpress spendenformular` → itself |
| Anchors | `wordpress spenden plugin` → `… kostenlos`, `wordpress paypal spenden plugin` · `paypal spendenbutton wordpress` → `paypal spenden button wordpress` · `wordpress spenden paypal` → `wordpress paypal spenden plugin` |
| Generic head | `spendenformular` → `… gemeinnütziger verein`, `… vorlage`, `… verein vorlage`, `… finanzamt` (off-topic: receipt templates) |
| YouTube | `wordpress paypal`, `wordpress stripe` (no donation-specific) |
| **Primary (tier B)** | `WordPress Spendenformular` |
| Secondary | `WordPress Spenden Plugin PayPal`, `PayPal Spendenbutton WordPress`, `Spenden per Stripe` |
| **Title (56)** | **Spendenformular in WordPress erstellen (PayPal & Stripe)** |
| Risk | Always keep "WordPress"; the generic word attracts non-WordPress template searchers. Plan: Free Plus (verify). Legal: Spendenquittung (donation receipt) is a German tax topic - do not give tax advice. |

### T8 - I1 "Create a Job Application Form with Resume Upload in WordPress"  (EN: `job application form wordpress`)

| Item | Result |
|---|---|
| Literal DE probes | `bewerbungsformular wordpress plugin` → `wordpress bewerbungsformular plugin` · `bewerbungsformular erstellen wordpress` 0 · `bewerbungsformular lebenslauf upload wordpress` 0 |
| Anchors | `bewerbungsformular wordpress` → `wordpress bewerbungsformular`, `wordpress bewerbungsformular plugin` · `bewerbung formular wordpress` → `wordpress bewerbung plugin` |
| Generic head | `bewerbungsformular` → vorlage, englisch, pdf, online, `bewerbungsbogen bundeswehr` (templates) · `online bewerbungsformular` → `… erstellen`, `… mit cv vorausfüllen` |
| YouTube | `online bewerbung erstellen`, `online bewerben` (job-seeker intent!) |
| **Primary (tier A)** | `WordPress Bewerbungsformular` |
| Secondary | `Bewerbungsformular Plugin`, `Bewerbungsformular erstellen`, `Datei-Upload` |
| **Title (48)** | **Bewerbungsformular in WordPress mit Datei-Upload** |
| Risk | YouTube side is job-seeker intent - the 60-second video title must say "WordPress" and "für Arbeitgeber/dein Team". |

### T9 - E8 "GDPR-Friendly WordPress Forms: Local Storage, Retention and Deleting Entries"  (EN: `gdpr wordpress form`)

| Item | Result |
|---|---|
| Literal DE probes | `wordpress formular dsgvo` → `wordpress formular plugin dsgvo` · `dsgvo konformes kontaktformular wordpress` 0 · `wordpress formular einträge löschen dsgvo` 0 |
| Compound-noun | `kontaktformular dsgvo` → `… konform`, `… checkbox`, `dsgvo kontaktformular einwilligung muster`, `… pflichtfelder dsgvo`, `wordpress kontaktformular dsgvo konform` · `kontaktformular datenschutz` → `… checkbox notwendig`, `… mustertext`, `… datenschutzerklärung` · `kontaktformular einwilligung` → `… datenschutz`, `einwilligungserklärung kontaktformular` |
| Competitors | `contact form 7 dsgvo` → `… konform` · `wpforms dsgvo` → `… konform` |
| **Primary (tier A)** | `Kontaktformular DSGVO konform` |
| Secondary | `Kontaktformular Datenschutz Checkbox`, `WordPress Formular Plugin DSGVO`, `Einwilligung Kontaktformular`, `Formulareinträge löschen` (task, tier D) |
| **Title (54)** | **Kontaktformular DSGVO-konform in WordPress: Checkliste** |
| Risk | Legal: "konform" is the search phrase but the article must not promise compliance (see `STYLE-GUIDE.md` section 13); owner decision. EN "local storage/retention" angle has no German demand evidence - keep as H2s. |

### T10 - L5 "Form Stuck on 'Please Wait' or Not Submitting? Checklist"  (EN: `wordpress form not submitting`)

| Item | Result |
|---|---|
| Literal DE probes | `wordpress formular wird nicht abgeschickt` 0 · `wordpress formular absenden funktioniert nicht` 0 · `wordpress formular sendet nicht` → falls back to `wordpress formular` |
| Anchors | `wordpress kontaktformular funktioniert nicht` → itself · `wordpress kontaktformular sendet nicht` → itself · `wordpress kontaktformular email kommt nicht an` → itself · `kontaktformular wordpress` → `… funktioniert nicht`, `… kostenlos`, `… erstellen`, `… plugin`, `… elementor` |
| Competitors | `contact form 7 sendet keine mails` → itself + `wordpress contact form 7 sendet keine mails` · `contact form 7 fehler` → `contact form 7 fehler beim senden` · `contact form 7 mails kommen nicht an` |
| YouTube | `wordpress kontaktformular e mail kommt nicht an` |
| **Primary (tier A)** | `WordPress Kontaktformular funktioniert nicht` |
| Secondary | `WordPress Kontaktformular sendet nicht`, `Kontaktformular E-Mail kommt nicht an`, `Contact Form 7 sendet keine Mails` |
| **Title (57)** | **WordPress-Kontaktformular sendet nicht: Ursachen & Lösung** |
| Risk | Overlaps with the existing e-mail deliverability cluster (SPF/DKIM/DMARC): link to it. The EFB-specific symptom "Bitte warten" is UI wording (`Bitte warten` in UI-STRINGS.csv). |

### T11 (bonus) - E9 "Accessible WordPress Forms: A WCAG-Friendly Checklist"  (EN: `accessible wordpress forms wcag`)

| Item | Result |
|---|---|
| Literal DE probes | `barrierefreie formulare wordpress` 0 · `wordpress formular barrierefreiheit` 0 · `barrierefreies kontaktformular wcag` 0 · `bfsg kontaktformular` → itself · `bfsg formular` 0 |
| Anchors | `wordpress barrierefreiheit` → `… plugin`, `… plugin deutsch`, `… plugin kostenlos`, `… prüfen`, `barrierefreiheitsstärkungsgesetz wordpress` · `bfsg wordpress` → `bfsg wordpress plugin` · `barrierefreies kontaktformular` → itself |
| Generic head | `barrierefreie formulare` → pdf, indesign, word, bedeutung (document-accessibility intent) |
| **Primary (tier B)** | `Barrierefreies Kontaktformular WordPress` |
| Secondary | `WordPress Barrierefreiheit`, `BFSG WordPress`, `barrierefreie Formulare erstellen` (watch PDF intent) |
| **Title (53)** | **Barrierefreie Formulare in WordPress: BFSG-Checkliste** |
| Risk | BFSG scope/date UNVERIFIED; do not state legal obligations without checking. |

### T12 (bonus) - J7 "Terms and Conditions Checkbox: Collect Consent in Forms"  (EN: `wordpress form consent checkbox`)

| Item | Result |
|---|---|
| Literal DE probes | `wordpress formular datenschutz checkbox` 0 · `… einwilligung checkbox` 0 · `agb checkbox formular wordpress` 0 |
| Anchors | `kontaktformular datenschutz checkbox` → `… notwendig`, `… text` · `datenschutz checkbox` → `… text`, `shopware 6 datenschutz checkbox` · `dsgvo checkbox` → `kontaktformular dsgvo checkbox` |
| Competitors | `contact form 7 checkbox` → styling, label, required · `contact form 7 acceptance` → required, checkbox, optional |
| **Primary (tier A/B)** | `Kontaktformular Datenschutz Checkbox` |
| Secondary | `Datenschutz Checkbox Text`, `Einwilligung Checkbox Formular`, `Contact Form 7 Acceptance` (competitor) |
| **Title (53)** | **Datenschutz-Checkbox im WordPress-Formular einrichten** |
| Risk | Reframe "Terms and Conditions" as **Datenschutz/Einwilligung** (German demand), mention AGB only as a secondary use. Legal wording: no sample consent text without owner approval. |

## 5. Term-variant probes (choose the noun Germans type)

Run on 2026-11-13 with `hl=de&gl=de`. "n" = number of completions returned.

| Concept | Variants tested (n) | Decision for glossary/titles |
|---|---|---|
| Embed a form | `wordpress formular einbinden` (2) · `einfügen` (1) · `einbauen` (1) · `einbetten` (0) | **einbinden**; "einfügen" for pasting a shortcode |
| Contact form | `wordpress kontaktformular` (10) · `wordpress contact form` (10) · `wordpress kontakt formular` (1, corrected to the compound) | closed compound **Kontaktformular** |
| Form builder noun | `wordpress form builder` (10) · `wordpress formular plugin` (3) · `wordpress formular builder` / `generator` / `formularersteller` / `formulare erstellen` (0) | keep the EN loan "Form Builder" in keyword lists; say "Formular-Plugin" in prose |
| Spam protection | `wordpress spamschutz` (3) · `wordpress spam schutz` (3) · `wordpress kontaktformular spamschutz` (1) · `wordpress formular spam schutz` (0) | closed **Spamschutz** (matches UI) |
| File upload | `wordpress formular datei upload` (1) · `dateiupload` (1, corrected to "datei upload") | **Datei-Upload** (two words in searches) |
| Multi-step | `wordpress formular mehrseitig` (1 → `wordpress mehrseitiges formular`) · `mehrstufig` (0) · `mehrere schritte` (0) · `multi step formular` (0) | add "mehrseitiges Formular" next to UI's "mehrstufig" |
| Signature | `wordpress formular unterschrift` (1) · `signatur` (0) · `elektronische unterschrift` (0) | say "Unterschrift" in titles, "Signatur" as UI label |
| Survey | `wordpress umfrage plugin` (1) · `umfrage formular` (0) · `befragung formular` (0) | **Umfrage** |
| E-mail problems | `wordpress kontaktformular email kommt nicht an` (1) · `formular mails landen im spam` (0) | anchor on "Kontaktformular … E-Mail kommt nicht an" |
| Entries noun | `einträge` (0) · `einsendungen` (0) · `übermittlungen` (0) · `nachrichten` (0) · `anfragen` (0) · `submissions` (0) | no autosuggest evidence; keep glossary "Eintrag" |
| Button/Schaltfläche, Dropdown/Auswahlliste, Pflichtfeld, Dankeseite, Vorlage/Template, Zahlung/Bezahlung, Checkbox/Kontrollkästchen | all 0 for `wordpress formular <term>` | no search evidence; decided by UI/site consistency (glossary) |
| Brand | `easy form builder` (9) · `easy form builder wordpress` (3) · `easy form builder plugin` (0) · `easy form builder deutsch` (0) | brand spelling "Easy Form Builder" only |

## 6. Template for the per-article keyword record

```
slug: <same as EN>                    section: <same as EN>
EN keyword / EN title: …
German probes (≥ 6): … -> n completions
Longest prefix with completions: "…" -> [top 5]
YouTube: [top 3]
Intent check: WordPress/plugin? (yes/no) - off-topic hits: …
Tier: A / B / C / D          confidence: high / medium / low
Primary keyword: …           Secondary: …, …, …
H1/title (≤ 60): …  (n chars)
Cannibalisation check vs live DE posts: …
Date of capture: YYYY-MM-DD
```

## 7. Not verified

- Search volume, competition, SERP layout and intent (no SERP fetch, no Search Console, no Keyword Planner here).
- Regional variance (AT/CH); `gl=at` / `gl=ch` were not tested.
- Whether titles will be accepted unchanged by the theme (the site appends " – Easy Form Builder", see `STYLE-GUIDE.md` section 7).
- Tier-D keywords are intent-led guesses and must be re-evaluated after 30 days of Search Console data.
