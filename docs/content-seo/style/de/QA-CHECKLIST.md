# QA checklist for a German article (translated from the EN original)

Use one copy per article. Every line must be PASS, or N/A with a reason. A single FAIL in sections A, B or H blocks publication. Rules referenced: `STYLE-GUIDE.md` (SG), `GLOSSARY.csv`, `UI-STRINGS.csv`, `KEYWORD-LOCALISATION.md` (KL), `SAMPLE-PAIRS.md`.

```
Article: ______________________   EN URL: ______________________   DE URL: ______________________
EN version/date checked: ______   Plugin version tested: 4.3.1   WP version: ______   Checked by / date: ______
```

## A. Identity, URL, hreflang (blocking)

| # | Check | How |
|---|---|---|
| A1 | **Slug identical** to EN (character for character, no localisation, no year). | compare last path segment |
| A2 | **Section / post type identical** to EN (`blog`, `document`, `advanced-addons`, `developer-guide`). hreflang on this site is section+slug based: 18 live German posts differ from EN and lost their `en` hreflang. | compare the first path segment on both sites |
| A3 | German page is `status: published` only after EN is published; EN counterpart returns 200. | `curl -sI` both URLs |
| A4 | `<html lang="de">`, canonical = the German URL itself (self-referencing). | view source |
| A5 | hreflang block lists **all four** entries (`en`, `de`, `ar` when AR exists, `x-default` = EN) and each URL returns 200; reciprocal tags exist on EN and AR. | `curl -s URL \| grep -o 'hreflang="[^"]*" href="[^"]*"'` |
| A6 | If the EN slug changed (e.g. security update `v4-0-12` → `v4-0-13`), the German slug is changed too and the old URL has a 301. | |
| A7 | Exactly one H1; the first H2 does not repeat the H1; no slug text or duplicated EN/DE title inside the H1 (seen on two live posts). | view source |
| A8 | Sitemap: URL is listed in a **German** posts sitemap (3 live German posts sit in `sitemap-fa-posts-1.xml`). | `curl -s …/sitemap.xml` |
| A9 | Title tag length: see SG 7.1 rule 6 (target ≤ 60 incl. suffix; if the theme still appends " – Easy Form Builder", headline ≤ 40 or document the exception). | |

## B. Terminology and UI fidelity (blocking)

| # | Check | How |
|---|---|---|
| B1 | **Every quoted UI label** („…“) equals the German UI string in `UI-STRINGS.csv` (or is explicitly a third-party UI label / English UI label with a German gloss). | run the script below; resolve every item it lists |
| B2 | English UI path given once in parentheses for each UI path (SG 8 rule 3); paths verified in the 4.3.1 panel (screenshot reference in the QA record). | |
| B3 | Terms follow `GLOSSARY.csv` (de_recommended). In particular: Bestätigungscode, Formulareinstellungen, Bedienfeld, Add-on, Datei-Upload, Dropdown-Menü, Spamschutz, Dankeseite, Weiterleitung, Eintrag, Button, Icon, E-Mail (never "Email"), CAPTCHA/reCAPTCHA/silentCAPTCHA casing. | `rg -n 'Email\b\|Addon\|Add-On\|Spam-Schutz\|Drop-down' draft` |
| B4 | **UI strings known to be wrong are not quoted** (`STYLE-GUIDE.md` 12.1: rate-limit message, "Steps style", "Building forms", "Editor Background", "Upgrade required", "Free Plus Guide", "Etikett…", the 12 Sie strings). | |
| B5 | Plan labels exactly `Free`, `Free Plus`, `Pro`, `Add-on (Pro)`; "Tarif"; "Pro-Version" hyphenated; every plan/limit statement matches the 4.3.1 behaviour **and** `/pricing/` (stale EN claims are corrected, not translated). | |
| B6 | Brand: "Easy Form Builder" without article/declension, no "EFB", no mixed compound "Easy Form Builder-Plugin". | script |
| B7 | One term per concept inside the article (Button vs Schaltfläche, Icon vs Symbol, Eintrag vs Einsendung). | |
| B8 | Technical strings unchanged and untranslated: shortcodes, hooks, filter names, code, file paths, DNS values, API field names; in code format, not in quotes. | diff against EN code blocks |
| B9 | Product facts (version numbers, limits, prices, dates) identical to EN or deliberately corrected with a note to the editor. | |

## C. Voice and typography

| # | Check | How |
|---|---|---|
| C1 | **du only.** 0 occurrences of Sie/Ihr/Ihnen/"Klicken Sie" (also in alt text, captions, FAQ answers, meta, CTA). | script: "formal tokens" |
| C2 | No "ich/mein" in body copy (FAQ questions excepted); team voice "wir". | script |
| C3 | Imperative steps; one action per step; ≤ 20 words per step; no sentence > 25 words (code/UI quotes excepted). | script |
| C4 | Headings: verbs/questions, "Schritt N: <Infinitiv>", no nominalised gerunds, no emojis, no pipes, no EN leftovers. | read the TOC |
| C5 | Quotes „…“ and nested ‚…‘; no straight/English quotes outside code; no »…«. | script |
| C6 | Numbers/dates/units: decimal comma, `100 %`, `13. November 2026`, `27 $`, `10 MB`, `z. B.` with space, spaced en dash " – "; no EN formats ("$27/year", "May 5, 2026"). | script |
| C7 | No filler openers ("In diesem Artikel zeigen wir …"), no "leistungsstark/nützlich/ganz einfach" stock phrases. | |
| C8 | Gender: no `*`, `:`, `_`, Binnen-I; neutral rephrasing where natural. | |

## D. SEO and keyword localisation

| # | Check | How |
|---|---|---|
| D1 | Keyword record filled (KL section 6): probes, longest prefix with completions, YouTube, intent check, tier, date. The primary keyword is the **German** evidence-based one, not the literal EN translation. | |
| D2 | Primary keyword (as Germans type it, compound noun in one piece) within the first 40 characters of the H1 and in the first 100 words; in at least one H2; not stuffed. | |
| D3 | H1 ≤ 60 characters; formula from SG 7.1; sentence case; no year in slug. | script |
| D4 | Meta description 140-155 characters, du-form, keyword + result + soft CTA; unique on the site. | script |
| D5 | "Kurz gesagt:" direct answer in the first 40-60 words with brand, plan and tested version; fact box (Tarif / Zeitaufwand / Getestet mit / Zuletzt geprüft). | |
| D6 | FAQ: H2 "Häufig gestellte Fragen", 4-6 H3 questions in "ich" voice, answers in du, ≤ 60 words, direct answer first. | |
| D7 | Cannibalisation: no live German post already targets the same primary keyword (check the 86 live posts + `content-queue.csv`); if overlap, internal link and different intent. | |
| D8 | Update note at the end: "Aktualisiert für Version 4.3.1: …". | |

## E. Links

| # | Check | How |
|---|---|---|
| E1 | Internal links point to **de.whitestudio.team** URLs (never to EN), with German anchor text; each target returns 200 and is the same section/slug as in EN. | `curl -sI` every link |
| E2 | Link set: 1 hub/pillar, 2-3 sibling articles, 1 conversion link (Pricing or Download), and ≥ 3 inbound links added from existing German articles. | |
| E3 | If the EN-linked article has no German twin yet: link to the EN page with anchor "(auf Englisch)" or omit; do not link a 404. | |
| E4 | External links only to primary sources (WordPress.org, Stripe/PayPal/Google/Telegram docs), `rel="noopener"`; German-language version of the vendor doc if it exists. | |
| E5 | No link text like "hier klicken"; anchor describes the target. | |

## F. Media

| # | Check | How |
|---|---|---|
| F1 | Every informative image has a German alt text (descriptive, UI label in „…“, no "Screenshot von", ≤ ≈125 chars); decorative = `alt=""` on purpose; no `alt="WhiteStudio.team"`, no file names. | script/HTML |
| F2 | Screenshots are from 4.3.1 in the **German** UI (or labelled English UI) and show the same state as the text; no API keys, personal data or real e-mail addresses. | |
| F3 | Caption only when it adds information; video caption: "(Video auf Englisch)" - add "mit deutschen Untertiteln" only if German subtitles exist. | |
| F4 | File names ASCII + German keyword (`formular-einstellungen-schritt-2.png`). | |
| F5 | Video block: `VideoObject` data and a full transcript (German or clearly marked English) below the video; chapters ≤ 5. | |
| F6 | Schema: `Article`/`TechArticle` + `BreadcrumbList` + `VideoObject` (+ `FAQPage`), `inLanguage: de`, `dateModified` correct, real author. | |

## G. Parity with the EN original

| # | Check | How |
|---|---|---|
| G1 | Same heading structure as EN (same number of H2/H3 unless a deliberate German addition is documented). | compare outlines |
| G2 | Length ratio DE/EN words between 0.9 and 1.25 (German runs ≈10-20 % longer); a ratio < 0.8 means something is missing (live examples: 0.26 and 0.28). | `wc -w` |
| G3 | No untranslated chrome strings on the page (TOC "On this page" is a theme string: report once, not per article). | |
| G4 | All EN lists, tables, warnings, FAQ items, code blocks are present. | |
| G5 | Numbers, limits, prices, versions identical to EN (or corrected in both). | |
| G6 | EN article's last-modified date is not newer than the German source it was translated from; if the EN changed meanwhile, re-translate the diff. | compare `lastmod` |

## H. Legal and claims (blocking)

| # | Check |
|---|---|
| H1 | "DSGVO" (not "GDPR"); no compliance guarantee; operator responsibility stated where privacy is discussed; "keine Rechtsberatung" where needed (SG 13). |
| H2 | No promise of payment methods or e-signature validity beyond what 4.3.1 and the provider docs support. |
| H3 | Third-party data flow (reCAPTCHA, Google Sheets, Telegram, Stripe) mentioned neutrally; no Auftragsverarbeitung wording unless the owner approved it. |
| H4 | No Impressum/legal-page text in the article. |
| H5 | No secrets, API keys or tokens in text, images or code. |

## I. Automated checks (copy-paste)

```bash
# 1) Pronoun/format/UI-label check on the draft text (code blocks are ignored).
python3 -I qa_de.py draft.txt "<H1>" "<meta description>" docs/content-seo/style/de/UI-STRINGS.csv

# 2) hreflang + status of the three language versions
for u in https://whitestudio.team/<section>/<slug>/ https://de.whitestudio.team/<section>/<slug>/ https://ar.whitestudio.team/<section>/<slug>/; do
  curl -sS -m 40 -L -A "Mozilla/5.0" -o /tmp/p.html -w "%{http_code} $u\n" "$u"
  grep -o 'hreflang="[^"]*" href="[^"]*"' /tmp/p.html
done
```

`qa_de.py` (save next to the draft; it only reads files):

```python
import csv, re, sys
body = open(sys.argv[1], encoding='utf-8').read()
h1 = sys.argv[2] if len(sys.argv) > 2 else ''
meta = sys.argv[3] if len(sys.argv) > 3 else ''
ui_path = sys.argv[4] if len(sys.argv) > 4 else 'docs/content-seo/style/de/UI-STRINGS.csv'
text = re.sub(r'```.*?```', ' ', body, flags=re.S)
text = re.sub(r'`[^`]*`', ' ', text)
print('H1 length:', len(h1), '(<= 60)' if len(h1) <= 60 else '(TOO LONG)')
print('Meta length:', len(meta), '(140-155)' if 140 <= len(meta) <= 155 else '(OUT OF RANGE)')
du = len(re.findall(r'\b(?:du|dir|dich|dein\w*|Du|Dir|Dein\w*)\b', text))
sie = re.findall(r'(?<=[a-zäöüß,;:\(] )Sie\b|\b(?:Ihre|Ihren|Ihrem|Ihrer|Ihres|Ihnen)\b|(?<=[a-zäöüß,;:\(] )Ihr\b', text)
print('du tokens:', du, '| formal tokens (must be 0):', len(sie), sie[:5])
print('first person outside questions:', [l[:80] for l in text.split('\n') if re.search(r'\b(?:ich|Ich|mein\w*)\b', l) and not l.strip().endswith('?')][:5])
print('straight quotes:', len(re.findall(r'"[^"\n]{1,60}"', text)), '| English curly:', len(re.findall(r'“[^”\n]{1,60}”', text)), '| »«:', text.count('»'))
print('z.B. without space:', len(re.findall(r'z\.B\.', text)), '| % without space:', len(re.findall(r'\d%', text)), '| $ before number:', len(re.findall(r'\$\s?\d', text)))
print('emoji in headings:', [l for l in text.split('\n') if l.startswith('#') and re.search(r'[\U0001F300-\U0001FAFF☀-➿]', l)][:3])
print('brand issues:', re.findall(r'\b(?:im|vom|beim|zum|dem) Easy Form Builder|Easy Form Builder-Plugin|\bEFB\b', text)[:5])
ui = list(csv.DictReader(open(ui_path, encoding='utf-8-sig')))
labels = {r['msgstr_de'].strip().lower() for r in ui}
quoted = [m.strip().rstrip('.:') for m in re.findall(r'„([^“„”\n]{2,60})“', text)]
unknown = sorted({q for q in quoted if q.lower() not in labels})
print('quoted strings:', len(quoted), '| not in UI-STRINGS.csv:')
for q in unknown: print('   -', q)
print('sentences > 25 words:', sum(1 for s in re.split(r'(?<=[.!?])\s+', text) if len(s.split()) > 25))
```

Tested on an existing article (`/document/how-to-customize-the-submit-button/`): it reports a first-person defect ("In dieser Anleitung habe ich …"), two quoted strings that are WordPress core labels, and 4 long sentences.

## J. Sign-off record

```
A (identity/hreflang): PASS / FAIL     B (terms/UI): PASS / FAIL      C (voice/typography): PASS / FAIL
D (SEO/keywords): PASS / FAIL          E (links): PASS / FAIL          F (media): PASS / FAIL
G (parity): PASS / FAIL                H (legal): PASS / FAIL
Open items for owner: ______________________________________________
Screenshots/UI evidence stored at: ____________________________________
```
