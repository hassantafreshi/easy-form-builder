# Input masks: market research and gap list

Status: research only. No plugin code was changed.
Date: 2026-11-11, branch `dev4`.
Compared against: [`masks-catalog.json`](masks-catalog.json) (99 presets), [`masks-and-validators.en.md`](masks-and-validators.en.md) and [`../F01-input-masks.fa.md`](../F01-input-masks.fa.md).

How the evidence was gathered:

- **Read from code (strongest).** The current wordpress.org builds of seven free plugins were downloaded and their mask code was read:
  - WPForms Lite 2.0.4
  - Fluent Forms 6.2.17
  - Formidable 7.0
  - Ninja Forms 3.16.1
  - Forminator 1.58.4
  - Everest Forms 3.6.4
  - MetForm 4.3.4

  The mask libraries were read from npm the same way: Inputmask 5.0.9, IMask 7.6.1, Cleave.js 1.6.0, jQuery Mask Plugin 1.14.16, Maska 3.1.1 and libphonenumber-js 1.11.20. Add-on readmes came from `plugins.svn.wordpress.org` and install counts from `api.wordpress.org`.
- **Read from vendor docs** with WebFetch: Gravity Forms, WPForms, Fluent Forms, Formidable, Ninja Forms, Everest Forms, Jotform, WS Form and SurveyJS.
- **User requests** come from wordpress.org support threads, the Gravity Forms community, Jotform answers and other forums. The topic titles shown on the first listing pages of three mask add-on forums were also classified: 74 unique topics. Each source is cited by URL.
- **Every proposed preset was tested.** All 41 proposed presets in §6.1 were run through the reference engine `masks-ref/efb-masks.js` (normalize → regex, plus `formatValue` for each format example). Each checksum was written in JS and checked against python-stdnum 2.2. Each regex compiles in PCRE with `/u`. Result: 0 failures.
- **Not verified** means it is marked **(unverified)**.

---

## 0. Summary: top 15 additions

| # | Addition | Kind | Priority | Why |
|---|---|---|---|---|
| 1 | **Numeric / currency mode**: group and decimal separators, decimal digits, negative on/off, prefix/suffix, min/max, lakh/wan grouping | engine | P0 | Third most-cited request (§5 R3). Fluent, Forminator, Ninja, WPForms, Inputmask, IMask and Cleave all have it. Today we only have a fixed `,`/`.` thousands formatter. |
| 2 | **Case transform**: `A` (upper letter) and `&` (upper alphanumeric) tokens, plus a per-field `mask_case` = upper/lower | engine | P0 | WPForms, Everest, WS Form and Inputmask have it. Seven separate requests (R7). We only have the per-preset `uppercase` flag, so custom masks cannot uppercase. |
| 3 | **Reject an incomplete custom mask** with a `length` message (the `isComplete` already specified in F01 §9.2) | engine | P0 | Request R2. The add-on threads complain that short input still gets submitted. Keep it in F01 scope. |
| 4 | `br.cpf` with the CPF mod-11 checksum | preset | P0 | Brazil is the largest mask add-on market: about 29k installs across three add-ons that ship CPF/CNPJ/CEP. |
| 5 | `br.cnpj` covering the **alphanumeric CNPJ** (letters allowed since July 2026) | preset | P0 | A user asked for it in June 2026 (thread `cnpj-nao-aceita-letras`). No competitor supports it yet. |
| 6 | `br.mobile` and `br.cep` | preset | P0 | Shipped by every Elementor and CF7 mask add-on. Fluent Forms ships `(00) 0000-0000`. |
| 7 | `intl.time_24s` (HH:MM:SS) and `intl.datetime_dmy` (date + time, plus a `datetime` validator) | preset + validator | P0/P1 | Fluent presets `00:00:00` and `00/00/0000 00:00:00`. Also shipped by the add-ons. |
| 8 | **Alternation / dynamic masks**: a list of masks with the first full match winning, so landline and mobile lengths work in one field | engine | P1 | WPForms `\(1\|61\|52)`, Inputmask, IMask `dispatch`, jQuery Mask function masks (BR 8/9-digit phone). Most-cited request (R1). |
| 9 | **Phone mask taken from the selected country** (per-country national pattern, as-you-type) | engine | P1 | Cleave (about 240 region add-ons), libphonenumber AsYouType and MetForm. Request R1. Shared with F02/F17. |
| 10 | Mobile presets for the regions around our audience: `tr.mobile`, `pk.mobile`, `af.mobile`, `ps.mobile`, `ly.mobile`, `sd.mobile`, `ye.mobile`, `sy.mobile` | preset | P1 | Persian- and Arabic-speaking neighbours not yet in the catalog. Patterns come from libphonenumber metadata. |
| 11 | `tr.tc_kimlik` and `pk.cnic` | preset | P1 | National IDs for large markets next to our audience. TC Kimlik has a published checksum. |
| 12 | `es.dni`, `es.nie`, `it.codice_fiscale` and `ca.sin` (all with checksums) | preset | P1 | European and Canadian IDs. A codice fiscale request appears in the uppercase threads. |
| 13 | `intl.vin`, `intl.swift_bic`, `intl.month_year` and `us.phone_ext` | preset | P1 | Inputmask alias `vin`, GF preset "US Phone + Ext" and the add-on `start` mask `00/0000`. |
| 14 | MENA business IDs: `eg.tax_id` and `ma.ice` (ICE mod-97 check) | preset | P1 | Our catalog has company/tax IDs only for IR, SA, AE and AU. |
| 15 | **Custom regex validation** with a ReDoS guard (already specified in F03), plus admin-defined character classes | engine | P1 | Request R8. Formidable, Jotform, GF (hook/add-on) and Inputmask `regex` have it. |

The remaining presets (French, Dutch, Mexican, Indian and GS1 IDs, among others) are P2 and listed in §6.1.

---

## 1. Legend

- **Verified (code):** read from the plugin or library source file named in the row.
- **Verified (docs):** read from the vendor's own documentation page.
- **(unverified):** from a secondary source, a search snippet or memory, and not confirmed.
- **Priority:**
  - P0: should ship with F01.
  - P1: next release.
  - P2: on demand.

---

## 2. Competitor mask features

### 2.1 WordPress form builders

| Product (version read) | Where masks live | Mask presets shipped | Custom mask tokens | Numeric / currency formatting | Dynamic masks / alternation | Mask validation | Free / paid |
|---|---|---|---|---|---|---|---|
| **Gravity Forms** (docs, 2026) | Single Line Text and Post Custom Field only (verified docs). Masks are switched off when "Enable password input" is on. | `US Phone (999) 999-9999`, `US Phone + Ext (999) 999-9999? x99999`, `Date 99/99/9999`, `Tax ID 99-9999999`, `SSN 999-99-9999`, `Zip Code 99999`, `Full Zip Code 99999?-9999`. List is extendable with the `gform_input_masks` filter (verified docs). | `9` digit, `a` letter, `*` alphanumeric, `?` = everything after is optional. Other characters are literals. Users see `_` placeholders (verified docs). | Number field: "Number Format" (comma or dot decimal) and "Range" min/max (verified docs; exact option labels unverified). | None. Phone field has Standard/International formats; custom formats with mask + regex need the `gform_phone_formats` filter (verified docs). | Mask forces the shape but not the value. Support's advice is the `gform_validation` hook (R8, R10). | Paid only |
| **WPForms Lite 2.0.4** (Inputmask 5.0.9) | Single Line Text "Input Mask" (in Lite, verified code and support reply) | No presets list. Aliases `alias:numeric`, `alias:currency`, `alias:decimal`, `alias:integer`, `alias:percentage` (1–100), `alias:url`, `alias:email`. The docs say the email and url aliases do **not** validate (verified docs). | `9` `a` `A` (upper) `*` `&` (upper alnum), `[ ]` optional, `{n}` and `{n,m}` repeat, `\\` escape. Alternation of literals: `+\(1\|61\|52) 9999999999`. Date/time with `date:` and tokens `d dd m mm yy yyyy h hh H HH M MM s ss l L t tt T TT` (verified docs). | Through the aliases. The Inputmask `currency` default is group `,`, 2 fixed decimals (verified code). Support told one user WPForms has "no feature for multiple number formats" (R3). | Literal alternation only (verified docs) | Incomplete mask message `val_inputmask_incomplete`: "Please fill out the field in required format." (verified code) | Free (Lite) |
| **Fluent Forms 6.2.17** (jQuery Mask Plugin) | "Mask Input" field | `(000) 000-0000`, `(00) 0000-0000`, `00/00/0000`, `00:00:00`, `00/00/0000 00:00:00`, Custom (verified code `ElementCustomization.php`) | jQuery Mask: `0` digit, `9` optional digit, `#` recursive digit, `A` alphanumeric, `S` letter (verified library code). The docs also list `*` (unverified for the runtime). Custom-only options "Activating a reversible mask" and "Clear if not match" (verified code). | Numeric field "Number Format": US with/without decimals `123,456.00` / `123,456,789`, EU with/without decimals `123.456,00` / `123.456.789` (verified code `Helper::getNumericFormatters`). Has a "Mobile Keyboard Type" select. | Only through jQuery Mask function masks in custom code (not in the UI) | `clearIfNotMatch` (clears the field instead of showing an error) | Free |
| **Formidable 7.0** | Single Line Text / Phone "Format" box | None. Examples in the docs: `(999)999-9999`, `999-99-9999`, `99999?9`, `aaaa*` (verified docs). | Pro: `9`, `a`, `*` (= **any** character in the KB), `?` rest-optional (verified docs/code). A regex starting with `^` in the same box validates but adds no mask. Whether Lite runs the regex is (unverified). | Number field: `thousands_sep` applies **only to displays** (views/emails), not to the input (R3) | None | Regex errors appear on submit | Mask Pro; regex Lite (unverified) |
| **Ninja Forms 3.16.1** (jquery.maskedinput + autoNumeric) | Single Line Text → Restrictions → Input Mask; needs Developer Mode | `US Phone (999) 999-9999`, `Date 99/99/9999`, `Currency` (autoNumeric), Custom (verified code `FieldSettings.php`) | `a` letter, `9` digit, `*` alphanumeric, ASCII only (verified code) | Currency preset via autoNumeric | None. "No option for variable-length input" (verified blog). | Android bug report: typed text invisible (R11) | Free core |
| **Forminator 1.58.4** | No input mask on text fields (verified code, unchanged from `competitor-matrix.en.md`) | Phone: Standard `(999) 999-9999` mask, International (intl-tel-input) or character limit (verified code) | — | Number, Currency and Calculation fields use Inputmask with `groupSeparator`/`radixPoint`/`digits`. Separator choices: none, `1,000.00`, `1.000,00`, `1 000,00`, custom. Currency code is shown as a suffix (verified code). | — | — | Free |
| **Everest Forms 3.6.4** (Inputmask bundle) | Single Line Text and Phone "Input Mask". Phone defaults to `(999) 999-9999`. | None | `9` `a` `A` `*` `&` (verified docs). `alias:` values are passed straight to Inputmask (verified code), so the numeric aliases probably work (unverified). | Through `alias:` only | — | — | **Free.** Masks are in the wordpress.org build. This corrects "phone mask Pro" in `competitor-matrix.en.md`. |
| **MetForm 4.3.4** | No generic mask in the free build (grep of bundles). Mobile field formats per selected country with a react-phone-input style component (`defaultMask`, `alwaysDefaultMask`, `countryCodeEditable`); the library name is (unverified). | — | — | — | Per-country phone only | — | Free; Pro (unverified) |
| **Elementor Pro Forms** | No native mask found (unverified: neither docs nor code available). The gap is filled by add-ons, see §2.2. | — | — | — | — | — | Paid |

### 2.2 Add-ons that add masks to Elementor and Contact Form 7

| Add-on (slug, version, installs) | Host | Masks shipped | Custom | Notes |
|---|---|---|---|---|
| Form Input Masks for Elementor Forms (`form-masks-for-elementor` 2.7.4, **10,000+**) | Elementor Pro, Hello Plus | Phone `0000-0000`, `(000) 000-0000`, `(00) 0000-0000`, `(00) 0.0000-0000`. Card `0000-0000-0000-0000`, expiry `00/00`. Date `00/00/0000`, time `00:00:00`, date-time. Currency `00,000.00` and `00.000,00`. CPF `000.000.000-00`, CNPJ `00.000.000/0000-00`, CEP `00000-000` (verified readme). | Not yet: "We plan to introduce a feature for custom masks" (readme FAQ) | 10 forum topics sampled, see §5 |
| Input Mask For Elementor Form Fields (`mask-form-elementor` 4.4.5, **10,000+**) | Elementor Pro, Hello Plus | The same 14 presets (verified plugin page) | Through an id/class (old changelog) | 20 topics sampled, many Brazilian |
| Input Masks for Elementor Forms (`input-masks-for-elementor-forms` 1.0.7, 600+) | Elementor | US Phone, US Phone + Ext, SSN, Tax ID, Zip, Full Zip | Yes | Changelog: "Added: Check validate", "Added: Settings Hide Mask Placeholder (_)". Strict validation. |
| Masks Form Fields (`masks-form-fields` 1.12.4, **9,000+**, jQuery Mask) | CF7, or any input by CSS class | `date 00/00/0000`, `start 00/0000`, `time 00:00:00`, `time2 00:00`, `date_time`, `date_time2`, `cep`, `zip 00000`, `phone` (Brazil dynamic 8/9 digits), `phone_us`, `taxid 00-0000000`, `ssn`, `cpf`, `cnpj` (reverse, clearIfNotMatch), six money masks (`#.##0,00`, `#,##0.00` …), `ip_address 099.099.099.099`, six percent masks (verified readme) | Through jQuery code | 44 topics sampled |
| Input Masks for Contact Form 7 (`input-masks-for-contact-form-7` 1.0.6, 900+) | CF7 `[text_mask]` tag | Examples: `(999) 999-9999`, `*[*{0,50}]@oursite.com`, `+(1\|61\|52) 9999999999`, `(TN\|KY)9{10}` | Yes (Inputmask-style syntax) | Readme claims RTL support |
| Mask Contact Form (`mask-contact-form` 1.2, 10+, last update 2017) | CF7 | jQuery Mask classes for date, time, CEP, phones, CPF, CNPJ, money | — | Abandoned |
| "Contact Form 7 Masks", "CF7 Input Mask" | — | Slugs `contact-form-7-masks` and `cf7-input-mask` return 404 on plugins.svn.wordpress.org | — | Not found (unverified whether they exist under other names) |
| Regex Validation for Gravity Forms (`regex-validation-for-gravity-forms` 1.1.2, 10+, released 2026) | GF | Presets: Name, Email, US Phone, International Phone (E.164), Alphanumeric, No Special Characters. Unicode `\p{L}` support. | Regex | Shows demand for regex validation in GF (R8) |

### 2.3 SaaS and other builders

| Product | Masks | Validation presets | Notes |
|---|---|---|---|
| **Jotform** | Short Text: `@` letter, `#` digit, `*` letter or digit, `[ ]` optional character. Phone field: `#` only, default `(###) ###-####` (verified help 381). Also a "Masked Input" widget. | Short Text: Email, Alphabetic, AlphaNumeric, Numeric, Currency (verified answers). URL and Cyrillic are (unverified). Regex validation exists (verified help). | Support repeatedly says variable-length masks are not possible (R4). In 2026 they escalated a request for special characters inside masks (R15). Currency validation "doesn't auto-add commas or a dollar sign". |
| **WS Form** | `a` `A` `9` `*` `&` and `[ ]` on Text, Phone, Textarea and Password, plus an "Input Mask Validation" toggle (verified docs) | — | Free/Pro split (unverified) |
| **SurveyJS** | Mask types: Pattern, Date and Time, Numeric, Currency (verified docs) | — | Option names (unverified) |
| **Typeform / Tally / Fillout** | No input mask feature found (unverified) | Typeform "Answer validation" exists (unverified details) | — |

### 2.4 Token cross-reference (for import and for the builder help text)

| Meaning | EFB (ours) | GF / Ninja (jquery.maskedinput) | WPForms / Everest / WS Form (Inputmask) | Fluent / Masks Form Fields (jQuery Mask) | Jotform | Formidable Pro | IMask |
|---|---|---|---|---|---|---|---|
| digit | `9` | `9` | `9` | `0` | `#` | `9` | `0` |
| optional digit | `[9]` | `?` + rest | `[9]` | `9` | `[#]` | `?` + rest | `[0]` |
| letter | `a` (ASCII **and Arabic script**) | `a` (ASCII only) | `a` (Latin, Cyrillic, Latin-1; **no Arabic**) | `S` (ASCII) | `@` | `a` | `a` (Unicode letters, includes Arabic) |
| uppercase letter | — **(gap)** | — | `A` | — | — | — | via `prepareChar` |
| letter or digit | `*` | `*` | `*` | `A` | `*` | `*` = **any character** | `*` = **any character** |
| uppercase letter or digit | — **(gap)** | — | `&` | — | — | — | — |
| hex digit | — | — | `#` (extension) | — | — | — | — |
| repeat | `{n}`, `{n,m}` | — | `{n}`, `{n,m}`, `{+}`, `{*}` | `#` recursive | — | — | `repeat` option |
| alternation | — **(gap)** | — | `(a\|b)` | function mask | — | — | dynamic `mask: [...]` |
| escape | `\` | — | `\\` in the WPForms UI | custom translation | — | — | `\\` |

Two corrections to the `compatibility` block in `masks-catalog.json`:

1. **WPForms.** The block calls the syntax "identical", but WPForms also has `A` and `&` (uppercase tokens) and literal alternation, and in its UI the escape is written `\\` (WPForms docs examples `\\9\\9-&&&`, `inst\\agr\\am`). Import should map `A → a` and `& → *` and set `mask_case = upper`.
2. **Fluent Forms.** The block's "(unverified)" note can be dropped. Fluent 6.2.17 really uses jQuery Mask, where `0` is a digit, `9` an optional digit, `#` a recursive digit, `A` alphanumeric and `S` a letter (`jquery.mask.js` translation table).

Also, Formidable's and IMask's `*` mean "any character", not "alphanumeric". Show a warning when one of those masks is imported.

---

## 3. Library capabilities

| Capability | **Inputmask 5.0.9** | **IMask 7.6.1** | **Cleave.js 1.6.0** | **jQuery Mask 1.14.16** | **jquery.maskedinput** | **EFB reference engine** |
|---|---|---|---|---|---|---|
| Used by | WPForms, Everest, Forminator (numbers), CF7 add-on | — (Vue/React ecosystems) | — (last npm release 1.6.0; maintenance status unverified) | Fluent Forms, Masks Form Fields, Mask Contact Form | Ninja Forms; GF (unverified) | planned F01 |
| Pattern tokens | `9 a *` plus extensions `A & #` | `0 a *`, custom `definitions` | none: `blocks` + `delimiters` | `0 9 # A S`, custom `translation` with `optional`, `recursive`, `fallback` | `9 a *` | `9 a *` |
| Optional / repeat | `[ ]`, `{n}`, `{n,m}`, `{+}`, `{*}`, groups `( )` | `[ ]`, `{ }` (fixed text kept in value), `` ` `` (no shift), `repeat` | fixed blocks | `9` optional, `#` recursive | `?` rest | `[ ]`, `{n}`, `{n,m}` |
| Alternation / dynamic | alternator `\|`, `keepStatic`, `regex` mask | `mask: [..]` + `dispatch`, `MaskedRegExp`, `MaskedFunction`, `MaskedEnum`, `MaskedRange`, `pipe` | phone by `phoneRegionCode` (about 240 region add-on files) | function masks + `onKeyPress` (e.g. Brazilian `SPMaskBehavior`) | — | **none** |
| Numeric | `numeric` alias: `digits`, `digitsOptional`, `enforceDigitsOnBlur`, `radixPoint`, `groupSeparator`, `allowMinus`, `negationSymbol {front, back}`, `prefix`, `suffix`, `min`, `max`, `SetMaxOnOverflow`, `step`, `unmaskAsNumber`, `roundingFN`, shortcuts `k`=1000 `m`=1000000, `stripLeadingZeroes`, `substituteRadixPoint`, `rightAlign`. Aliases `currency` (`,` 2 fixed), `decimal`, `integer`, `percentage` (0–100, suffix `" %"`), `indianns` (lakh grouping). | `MaskedNumber`: `scale` (2), `radix` (`,`), `thousandsSeparator` (`''`), `mapToRadix`, `min`, `max`, `normalizeZeros`, `padFractionalZeros`, `autofix` | `numeral`: `numeralThousandsGroupStyle` thousand / lakh / wan / none, `numeralDecimalMark`, `numeralIntegerScale`, `numeralDecimalScale`, `numeralPositiveOnly`, `signBeforePrefix`, `prefix`, `tailPrefix` | `reverse: true` money masks such as `#.##0,00` | — | `formatter: thousands` (fixed `,`, `.` radix); 2 decimals enforced only by preset regex |
| Date / time | `datetime` alias: `inputFormat`, `displayFormat`, `outputFormat`, `min`, `max`; aliases `isoDate`, `isoTime`, `isoDateTime`, `isoUtcDateTime`; **rejects impossible dates** including 29 Feb (`isValidDate`) | `MaskedDate`: `pattern`, blocks via `MaskedRange`, `min`, `max`, `parse`, `format`, `autofix` | `date` + `datePattern`, `dateMin`, `dateMax`; `time` + `timePattern`, `timeFormat` 12/24 | fixed patterns only | fixed patterns only | `validator: date` with Gregorian, **Jalali** and **Hijri** calendars and `year_range`; time by regex |
| Case | definition `casing: upper`; option `casing` upper / lower / title / callback | `prepareChar` | `uppercase`, `lowercase` | — | — | per preset `uppercase` (ASCII-only) |
| Placeholder / blur | `placeholder` `_`, `showMaskOnFocus`, `showMaskOnHover`, `clearMaskOnLostFocus`, `clearIncomplete`, `jitMasking`, `nullable`, `positionCaretOnClick`, `insertMode` | `lazy: false` + `placeholderChar` (shows the template), `displayChar`, `eager`, `overwrite` | `delimiterLazyShow` | `placeholder`, `clearIfNotMatch`, `selectOnFocus` | `placeholder` `_`, `autoclear` | lazy literals, no template |
| Unmask | `autoUnmask`, `removeMaskOnSubmit`, `onUnMask` | `unmaskedValue`, `typedValue` | `getRawValue()` | `cleanVal()`, `unmask()` | — | `toStored()` (digits, E.164, strip grouping) |
| Built-in presets | `email`, `url`, `ip`, `mac`, `vin` (17 chars, I/O/Q excluded, upper, `clearIncomplete`), `ssn` (rejects 000/666/9xx, group 00, serial 0000), `cssunit` | — | credit card types uatp, amex, diners, discover, mastercard, dankort, instapayment, jcb15, jcb, maestro, visa, mir, unionPay | — | — | 99 catalog presets with checksums |
| Non-Latin digits | `9` = `0-9` and full-width `０-９` only; Persian/Arabic-Indic digits are **rejected** unless you add `substitutes` (verified `definitions.js`). Changelog 5.0.8: "persian/arabic currency mask with regex #2472". | `0` = `\d` (ASCII only) | ASCII | `\d` (ASCII only) | `[0-9]` | Persian, Arabic-Indic and full-width digits, Arabic separators and bidi marks are all normalized |

Maska 3.1.1 also offers custom tokens with transform functions, a number mode, and dynamic, reversed and eager masks (README feature list).

libphonenumber-js 1.11.20 metadata supplied the mobile patterns and national display formats for the phone presets proposed in §6.1.

---

## 4. Our coverage today, by category

The catalog has 18 phone_mobile, 16 postal_code, 15 iban, 13 national_id, 6 date, 6 generic, 5 tax_id, 4 phone_landline, 3 company_id, 3 bank_card, 3 time, 3 currency, 2 passport, 1 bank_account and 1 vehicle_plate presets.

By country: 18 intl and 15 IR; then 9 SA; 4 each for AE, EG, KW, JO, MA, TN, US, UK and IN; 3 each for QA, BH, OM, DZ and AU; 2 each for IQ and LB; 1 each for CA and DE.

Competitor presets we **already match**:

- US phone, date, SSN, Tax ID (EIN), ZIP and ZIP+4
- card, card expiry, CVC
- time HH:MM and 12-hour time
- percentage, IPv4, MAC, licence key, IBAN

Competitor presets we **lack**:

- US Phone + Ext (GF)
- time with seconds and date-time (Fluent and the add-ons)
- MM/YYYY (Masks Form Fields `start`)
- Brazil CPF, CNPJ, CEP and phones (every Elementor/CF7 mask add-on, Fluent `(00) 0000-0000`)
- VIN (Inputmask alias)
- numeric aliases integer and decimal, and EU / space / lakh currency styles (WPForms, Forminator, Fluent, Cleave, Inputmask)

---

## 5. What users ask for (ranked)

**How it was ranked.** Rank = number of independent sources found in this session, with the 74-topic add-on forum sample weighted as below.

**The forum sample.** The sample is the topic titles listed in three support forums:

- [`form-masks-for-elementor`](https://wordpress.org/support/plugin/form-masks-for-elementor/): 10 topics
- [`mask-form-elementor`](https://wordpress.org/support/plugin/mask-form-elementor/): 20 topics
- [`masks-form-fields`](https://wordpress.org/support/plugin/masks-form-fields/): 44 topics

Classified by feature, those 74 topics give:

| Feature | Topics |
|---|---|
| phone masks | 15 |
| custom masks / own characters | 9 |
| incomplete input accepted | 6 |
| Brazilian IDs | 4 |
| unmask before submit | 3 |
| currency | 2 |
| placeholder | 2 |
| min/max length or value | 2 |
| uppercase | 1 |
| mobile | 1 |
| textarea | 1 |
| invalid-state CSS | 1 |
| US ZIP | 1 |
| compatibility, bugs and how-to | about 27 |

| Rank | Request | Evidence (count, examples with quotes) | Our status |
|---|---|---|---|
| R1 | **Phone masks per country, variable length, with country code** | 15 forum topics, plus Jotform and GF. Examples:<br>- "Gostaria se tem como adicionar Mascara completa com país tipo +55 (00) 00000-0000" ([mascara-com-ddi-ddd-9-digitos](https://wordpress.org/support/topic/mascara-com-ddi-ddd-9-digitos/), 2023)<br>- [telefone-com-mascara-para-16-digitos](https://wordpress.org/support/topic/telefone-com-mascara-para-16-digitos/)<br>- Jotform [983103](https://www.jotform.com/answers/983103-input-masking-for-phone-numbers-without-defining-exact-length) and [35788901](https://www.jotform.com/answers/35788901-phone-number-field-input-mask-for-followed-by-7-to-14-digits) ("'+' followed by 7 to 14 digits")<br>- GF needs code for custom phone formats ([gform_phone_formats](https://docs.gravityforms.com/gform_phone_formats/))<br>- [country-code-field-for-elementor-form](https://wordpress.org/plugins/country-code-field-for-elementor-form/) has 10,000+ installs | 18 mobile presets plus E.164, but no dynamic mask and no mask from a selected country → E4, E5 |
| R2 | **Do not submit an incomplete mask** | 6 topics, plus GF and an add-on changelog. Examples:<br>- "I added US mask, and tried to submit the form with 9 characters … but it allowed the submission" ([less-characters-not-validated](https://wordpress.org/support/topic/less-characters-not-validated/), 2024)<br>- "Estamos recebendo telefones incompletos regularmente" ([exigir-telefone-completo](https://wordpress.org/support/topic/exigir-telefone-completo/), 2022)<br>- [force-to-send-form-with-minimal-characters](https://wordpress.org/support/topic/force-to-send-form-with-minimal-characters/)<br>- GF [16453](https://community.gravityforms.com/t/how-do-i-prevent-a-submission-from-triggering-notifications-if-field-does-not-match-input-mask/16453) (2023)<br>- "Added: Check validate" ([input-masks-for-elementor-forms](https://wordpress.org/plugins/input-masks-for-elementor-forms/)) | Presets have this (regex). Custom masks need `isComplete` (F01 §9.2) → E2 |
| R3 | **Currency with thousand separators, symbol prefix, EU style** | 9 sources. Examples:<br>- WPForms: "How do we create Currency Input Mask for USD"; support: no multiple number formats ([currency-input-mask](https://wordpress.org/support/topic/currency-input-mask/))<br>- CF7: "It would be perfect if thousand separator (comma) can be added in the submission form field" (unresolved, [p=17108478](https://wordpress.org/support/?p=17108478))<br>- Formidable: "Is there a way to have a calculated number field show its value with the thousands separated by a ','? … in the form itself" ([thousands-separator-in-number-field](https://connect.formidableforms.com/question/category/how-to/thousands-separator-in-number-field), 2022), and clients typing commas into number fields cause errors ([p=5144](https://connect.formidableforms.com/?p=5144), 2023)<br>- [dollar-sign money mask](https://wordpress.org/support/topic/is-it-possible-to-add-a-dollar-sign-at-the-beginning-of-the-money-mask/) (2023)<br>- [currency-field-setup](https://wordpress.org/support/topic/currency-field-setup/)<br>- Jotform [1196098](https://www.jotform.com/answers/1196098-form-builder-add-new-field-to-handle-currency-formatting), [39291521](https://www.jotform.com/answers/39291521-short-text-field-restrict-input-to-dollar-currency-format-only) | Fixed `,` / `.` thousands formatter only → E1 |
| R4 | **Optional / variable-length parts** | 8 sources. Examples:<br>- "Is there any way to use the mask but indicate that a character is optional?" → "not possible" (Jotform [906928](https://www.jotform.com/answers/906928-optional-characters-in-input-mask), 2016; more Jotform threads [2396756](https://www.jotform.com/answers/2396756-Input-Masks-for-varying-length), [1204071](https://www.jotform.com/answers/1204071-Short-Text-Entry-Variable-length-text-entry-with-input-mask), [1931211](https://www.jotform.com/answers/1931211-how-to-apply-input-mask-when-the-entry-length-is-not-fixed), [1335656](https://www.jotform.com/answers/1335656-Input-Mask-variable-length-special-character-support))<br>- Formidable [input-mask-format-characters-optional](https://wordpress.org/support/topic/input-mask-format-characters-optional/) (2020)<br>- Ninja Forms: "no option for variable-length input" ([blog](https://ninjaforms.com/blog/how-to-use-input-mask/)) | **Have** `[ ]` and `{n,m}`. Unbounded repeat is missing → E13 (P2) |
| R5 | **Custom masks and more presets** | 9 topics. Examples:<br>- "Custom masks it's only on Pro version" ([cant-create-custom-mask](https://wordpress.org/support/topic/cant-create-custom-mask/), 2021)<br>- [can-i-add-new-mask-types](https://wordpress.org/support/topic/can-i-add-new-mask-types/), [problem-with-custom-mask](https://wordpress.org/support/topic/problem-with-custom-mask/)<br>- Cool Plugins FAQ: custom masks "in future updates" | Have (free custom mask + catalog) |
| R6 | **Brazilian CPF / CNPJ / CEP, including the alphanumeric CNPJ** | 4 topics plus about 29k add-on installs. Examples:<br>- [cnpj-nao-aceita-letras](https://wordpress.org/support/topic/cnpj-nao-aceita-letras/) (June 2026): the user links the Receita Federal spec; the author asks for test cases<br>- [cpf-validation](https://wordpress.org/support/topic/cpf-validation/) (2021): "This plugin only add mask" | **Missing** → §6.1 `br.*` (P0) |
| R7 | **Uppercase transform** | 7 sources. Examples:<br>- GF (2013): "The input mask script does not differentiate between upper and lowercase letters" ([input-mask-uppercase](https://legacy.forums.gravityhelp.com/topic/input-mask-uppercase))<br>- [uppercase-field](https://wordpress.org/support/topic/uppercase-field/) (2021)<br>- Forminator [field-to-uppercase](https://wordpress.org/support/topic/field-to-uppercase/) (2022; answer: CSS `text-transform`, display only)<br>- [Gravity Wiz uppercase snippet](https://gravitywiz.com/snippet-library/gw-uppercase-entry-value/)<br>- Jotform [4396626](https://jotform.com/answers/4396626-is-there-a-way-to-make-the-masked-letters-all-capped) | Per preset only → E3 |
| R8 | **Regex validation beyond the mask** | 5 sources. Examples:<br>- GF UK postcode (2026): mask `**** 9aa`, "custom validation using regex" for reliability ([20004](https://community.gravityforms.com/t/input-mask-for-uk-postcode-resolved/20004))<br>- [Regex Validation for Gravity Forms](https://wordpress.org/plugins/regex-validation-for-gravity-forms/) (2026)<br>- [Formidable format](https://formidableforms.com/knowledgebase/format/)<br>- [Jotform regex](https://www.jotform.com/help/how-to-set-up-regular-expression-regex-validation-for-the-short-text-element/) | `uk.postcode` exists (regex). Custom regex → F03 (E7) |
| R9 | **Strip the mask before saving/sending** | 3 topics: [strip-the-mask-before-sending](https://wordpress.org/support/topic/strip-the-mask-before-sending/) (2021), [howto-hide-phone-prefix-but-send-it-at-submit](https://wordpress.org/support/topic/howto-hide-phone-prefix-but-send-it-at-submit/), [currency-field-setup](https://wordpress.org/support/topic/currency-field-setup/) | **Have** (`mask_store` canonical) |
| R10 | **Date masks that reject impossible dates** | 3 sources. Examples:<br>- GF docs: `99/99/9999` "would accept 05/21/2011 or 21/05/2011" ([input-mask](https://docs.gravityforms.com/input-mask/))<br>- GF [16453](https://community.gravityforms.com/t/how-do-i-prevent-a-submission-from-triggering-notifications-if-field-does-not-match-input-mask/16453): spam in a masked date field | **Have** (`validator: date`, 3 calendars). Missing: min/max date, date-time → E6 |
| R11 | **Masks breaking on mobile / Android** | 5 sources. Examples:<br>- Ninja Forms: "Android users do not see their inputted text when they type, and cannot submit the form if this field is required" ([input-mask-us-phone-doesnt-work-on-android](https://wordpress.org/support/topic/input-mask-us-phone-doesnt-work-on-android/), 3.6.11)<br>- [masks-acting-odd-on-mobile-phone](https://wordpress.org/support/topic/masks-acting-odd-on-mobile-phone/) (turned out to be cache)<br>- Stack Overflow "masked input not working in android mobiles" (18442481) (unverified URL: seen through a mirror) | **Designed out**: no `keydown` filter, IME composition guard, `inputmode` (guide §5.1–5.4) |
| R12 | **Time masks (HH:MM, HH:MM:SS)** | 4 sources: Fluent preset `00:00:00`; Masks Form Fields `time`/`time2`; wordpress.org time-format threads (search snippets only, unverified) | HH:MM only → `intl.time_24s` |
| R13 | **Persian / Arabic digits in masked fields** | 4 sources. Examples:<br>- [Inputmask + Persian digits](https://www.outsystems.com/forums/discussion/65605/integer-inputmask-problem-with-persian-numbers/)<br>- Inputmask changelog "persian/arabic currency mask with regex #2472"<br>- [Iran Phone Format For GForms](https://wordpress.org/plugins/iran-phone-format-for-gforms/) exists only to convert Persian digits before validation<br><br>Code check: Inputmask, jQuery Mask and maskedinput all reject them (§3). | **Have** (differentiator) |
| R14 | **Credit card grouping by brand** | Formidable KB (`frm_input_masks`): a fixed card mask "will not work with all credit card types, such as American Express". The add-ons ship a fixed `0000-0000-0000-0000`. | **Have** (`formatter: card`) |
| R15 | **Special characters inside the variable part** (handles, URLs) | Jotform [40282081](https://www.jotform.com/answers/40282081-input-mask-support-for-additional-characters-and-variable-lengths) (2026): "We'd really like to use the input masks to collect social media handles", escalated. Also [mask-url](https://wordpress.org/support/topic/mask-url/). | Missing → E8 (P2) |
| R16 | **Min/max value or length for numbers** | [minimum-number](https://wordpress.org/support/topic/minimum-number/), [max-lenght-2](https://wordpress.org/support/topic/max-lenght-2/); GF Number "Range" | Part of E1 |
| R17 | **Show or hide the `_` placeholder** | GF docs "users see only underscores"; Elementor add-on setting "Hide Mask Placeholder (_)"; [cf7-phone_us-placeholder-deletion](https://wordpress.org/support/topic/cf7-phone_us-placeholder-deletion/) | No template today → E9 (P2) |
| R18 | **Licence plate, VIN, driving licence** | A driving-licence pattern thread ([p=15047786](https://wordpress.org/support/?p=15047786), snippet only, unverified); the Jotform Masked Input widget names plates. No VIN thread found. | `ir.vehicle_plate` only → `intl.vin` (P1). Plates of other countries stay unverified. |
| R19 | **Masks in RTL forms** | No explicit request found. The CF7 add-on readme claims "Compatible with multilingual setups and RTL". | **Have** (guide §5.5) |

---

## 6. Gap list against our catalog

### 6.1 Presets to add

Conventions follow `masks-catalog.json`:

- The regex runs on the normalized value: digits folded to ASCII, `strip` characters removed, then ASCII-uppercased when `uppercase` is true.
- Phone regexes accept an optional `+cc` / `00cc` / `cc` / trunk prefix, the same way as `ir.mobile`.
- Mask literals follow §3 of the guide: `0599 …` means literal `0`, literal `5`, then digits.
- All valid/invalid/format examples below **passed** the reference engine (`efb-masks.js` validate + `formatValue`) and the new checksums (cross-checked with python-stdnum 2.2).
- New checksum and validator names are marked **new**. They need a JS + PHP twin in the engine (F01 sub-task A).
- Labels are given in English only. fa/ar labels still have to be written.
- In the tables, `\|` inside code stands for a plain `|`; that escaping is a Markdown table rule. Appendix A has the exact strings.

The full catalog-ready JSON for all 41 entries is in Appendix A.

#### Brazil (P0, except `br.phone` P1)

| id | category | mask | regex | checksum / rule | valid | invalid → code | source |
|---|---|---|---|---|---|---|---|
| `br.cpf` | national_id | `999.999.999-99` (strip ` .-`) | `^\d{11}$` | **new** `br_cpf`: weights 10..2 → DV1, 11..2 → DV2; r = Σ mod 11; dv = r<2 ? 0 : 11−r. Reject 11 equal digits as `rule` (common practice; the official status is unverified, and stdnum accepts them). | `123.456.789-09`, `529.982.247-25`, `۱۲۳۴۵۶۷۸۹۰۹` | `123.456.789-00` → checksum; `111.111.111-11` → rule; `1234567890` → format | stdnum `br/cpf.py`; Masks Form Fields; R6 |
| `br.cnpj` | company_id | `**.***.***/****-99` (strip ` .-/`, uppercase) | `^[0-9A-Z]{12}\d{2}$` | **new** `br_cnpj`: value = ASCII code − 48 (so `A`=17); weights `5,4,3,2,9,8,7,6,5,4,3,2` then `6,5,4,3,2,9,8,7,6,5,4,3,2`; dv = r<2 ? 0 : 11−r. All-zero base → rule. Numeric CNPJs stay valid. | `11.222.333/0001-81`, `12.ABC.345/01DE-35`, `12abc34501de35` | `11.222.333/0001-80` → checksum; `12.ABC.345/01DE-3A` → format; `00000000000000` → rule | Receita Federal FAQ PDF; stdnum `br/cnpj.py`; R6 thread 2026 |
| `br.cep` | postal_code | `99999-999` | `^\d{8}$` | — | `01310-100`, `01310100` | `1310-100` → format | add-on readmes |
| `br.mobile` | phone_mobile | `(99) 99999-9999` | `^(?:\+55\|0055\|55\|0)?(?:[14689][1-9]\|2[12478]\|3[1-578]\|5[13-5]\|7[13-579])9\d{8}$` | e164 cc 55, NSN 11 | `(11) 91234-5678`, `+55 11 91234-5678`, `(55) 99123-4567` | `(11) 81234-5678`, `(10) 91234-5678`, `(11) 1234-5678` → format | libphonenumber BR mobile |
| `br.phone` (P1) | phone_landline | `(99) 9999-9999` | same area-code group, then `[2-5]\d{7}` | e164 cc 55, NSN 10. The `[2-5]` subscriber prefix is (unverified). | `(11) 3456-7890` | `(11) 9345-6789` → format | libphonenumber area codes. Better served by E4: one field with `(99) 9999-9999` or `(99) 99999-9999`. |

#### Generic / competitor parity

| id | P | category | mask | regex | rule | valid | invalid → code | source |
|---|---|---|---|---|---|---|---|---|
| `intl.time_24s` | P0 | time | `99:99:99` | `^(?:[01]\d\|2[0-3]):[0-5]\d:[0-5]\d$` | — | `23:59:59`, `۰۸:۳۰:۰۰` | `24:00:00`, `12:60:00`, `12:30` → format | Fluent preset, Masks Form Fields |
| `intl.datetime_dmy` | P1 | date | `99/99/9999 99:99[:99]` | `^\d{2}/\d{2}/\d{4} (?:[01]\d\|2[0-3]):[0-5]\d(?::[0-5]\d)?$` | **new** validator `datetime`: run the `date` rule on the part before the space. Today's `date` validator splits on every non-digit, so the time would break it. | `29/02/2024 13:45`, `31/12/2024 23:59:59` | `31/12/2024 24:00` → format; `29/02/2023 13:45` → rule (once `datetime` exists) | Fluent, add-ons |
| `intl.month_year` | P1 | date | `99/9999` | `^(?:0[1-9]\|1[0-2])/\d{4}$` | **new** validator `month_year` (optional "not expired", like `card_expiry`) | `02/2027` | `13/2024`, `2/2024` → format | Masks Form Fields `start` |
| `us.phone_ext` | P1 | phone_landline | `(999) 999-9999[ x9{1,5}]` | `^(?:\+1\|1)?[2-9]\d{2}[2-9]\d{6}(?:x\d{1,5})?$` | `nanp` on the first 10 digits | `(202) 555-0123 x45`, `2025550123x12345` | `… x123456`, `(102) 555-0123` → format | GF "US Phone + Ext" |
| `intl.vin` | P1 | generic | `*{17}` (uppercase) | `^[A-HJ-NPR-Z0-9]{17}$` (no I, O, Q) | `checksum_strict`: **new** `vin_na` (position-9 check digit, North America only; EU VINs often lack it, so strict mode only) | `1M8GDM9AXKP042788`, `WVWZZZ1JZXW000001` | `1M8GDM9AIKP042788` → format; strict: `1M8GDM9A1KP042788` → checksum | Inputmask `vin` alias; Wikipedia VIN |
| `intl.swift_bic` | P1 | bank_account | `aaaaaa**[***]` (uppercase) | `^[A-Z]{6}[A-Z0-9]{2}(?:[A-Z0-9]{3})?$` | — (country code at positions 5–6 could be checked against ISO 3166: P2) | `DEUTDEFF`, `DEUTDEFF500` | `DEUT1EFF`, `DEUTDEF` → format | stdnum `bic.py`; pairs with our IBAN presets |
| `uk.sort_code` | P2 | bank_account | `99-99-99` | `^\d{6}$` | — | `20-00-00` | `20-00-0` → format | Wikipedia |
| `us.routing_number` | P2 | bank_account | `999999999` | `^(?:0\d\|1[0-2]\|2[1-9]\|3[0-2]\|6[1-9]\|7[0-2]\|80)\d{7}$` (prefix table unverified) | **new** `us_aba`: 3·(d1+d4+d7) + 7·(d2+d5+d8) + (d3+d6+d9) ≡ 0 (mod 10) | `011000015`, `021000021` | `011000016` → checksum | stdnum `us/rtn.py`, Wikipedia |
| `intl.imei` | P2 | generic | `99-999999-999999-9` | `^\d{15}$` | `luhn` | `49-015420-323751-8` | `490154203237519` → checksum | stdnum `imei.py` |
| `intl.gtin` | P2 | generic | `9{8,14}` | `^(?:\d{8}\|\d{12,14})$` | **new** `gs1_mod10` (weights 3/1 from the right) | `4006381333931`, `036000291452` | `4006381333932` → checksum | stdnum `ean.py` |

#### Our audience's neighbourhood: Persian- and Arabic-speaking countries (P1 unless noted)

Patterns come from libphonenumber-js 1.11.20 `metadata.max.json` (mobile type) and display formats from the same file.

| id | mask | regex | NSN / e164 | valid | invalid → format |
|---|---|---|---|---|---|
| `af.mobile` | `079 999 9999` | `^(?:\+93\|0093\|93\|0)?7\d{8}$` | 93 / 9 | `070 123 4567`, `+93 70 123 4567`, `۰۷۰۱۲۳۴۵۶۷` | `020 123 4567`, `070 123 456` |
| `tr.mobile` | `0599 999 99 99` | `^(?:\+90\|0090\|90\|0)?5(?:0[15-7]\|1[06]\|24\|[34]\d\|5[1-59]\|9[46])\d{7}$` | 90 / 10 | `0532 123 45 67`, `+90 532 123 4567` | `0212 123 45 67`, `0532 123 45 6` |
| `pk.mobile` | `0399 9999999` | `^(?:\+92\|0092\|92\|0)?3(?:[0-247]\d\|3[0-79]\|55\|64)\d{7}$` | 92 / 10 | `0300 1234567`, `+92 300 1234567` | `0500 1234567`, `0300 123456` |
| `ps.mobile` | `0599 999 999` | `^(?:\+970\|00970\|970\|0)?5[69]\d{7}$` | 970 / 9. Numbers dialled with +972 are not covered (unverified whether needed). | `0599 123 456`, `0569123456` | `0579 123 456` |
| `ly.mobile` | `0\99-9999999` | `^(?:\+218\|00218\|218\|0)?9[1-6]\d{7}$` | 218 / 9 | `091-2345678`, `+218 91 234 5678` | `097-2345678` |
| `sd.mobile` | `099 999 9999` | `^(?:\+249\|00249\|249\|0)?(?:1[0-2]\|9[0-3569])\d{7}$` | 249 / 9 | `091 234 5678`, `012 345 6789` | `094 234 5678` |
| `ye.mobile` | `0799 999 999` | `^(?:\+967\|00967\|967\|0)?7[01378]\d{7}$` | 967 / 9 | `0771 234 567` | `0751 234 567` |
| `sy.mobile` | `0999 999 999` | `^(?:\+963\|00963\|963\|0)?(?:50\|9[1-689])\d{7}$` | 963 / 9 | `0944 567 890` | `0974 567 890` |
| `mr.mobile` (P2) | `99 99 99 99` | `^(?:\+222\|00222\|222)?[2-4][0-46-9]\d{6}$` | 222 / 8, no trunk | `22 12 34 56` | `25 12 34 56` |

| id | P | category | mask | regex | checksum / rule | valid | invalid → code | source |
|---|---|---|---|---|---|---|---|---|
| `tr.tc_kimlik` | P1 | national_id | `99999999999` | `^[1-9]\d{10}$` | **new** `tr_tckimlik`: d10 = ((d1+d3+d5+d7+d9)·7 − (d2+d4+d6+d8)) mod 10; d11 = (d1+…+d10) mod 10 | `10000000146` | `10000000147` → checksum; `01234567890` → format | stdnum `tr/tckimlik.py` |
| `pk.cnic` | P1 | national_id | `99999-9999999-9` | `^\d{13}$` | — | `34201-0891231-8` | `34201-0891231` → format | stdnum `pk/cnic.py` |
| `eg.tax_id` | P1 | tax_id | `999-999-999` | `^\d{9}$` | — | `100-531-385`, `٣٣١-١٠٥-٢٦٨` | `12345` → format | stdnum `eg/tn.py` |
| `ma.ice` | P1 | company_id | `999999999999999` | `^\d{15}$` | **new** `mod97_0`: the 15-digit integer mod 97 = 0 (not the IBAN variant) | `001561191000066`, `00 21 36 09 30 00 040` | `001561191000065` → checksum | stdnum `ma/ice.py` |
| `dz.nif` | P2 | tax_id | `999999999999999[99999]` | `^(?:\d{15}\|\d{20})$` | — | `408020000150039`, `41201600000606600001` | 16 digits → format | stdnum `dz/nif.py` |
| `tn.mf` | P2 | tax_id | `9999999/a[/a/a/999]` (uppercase, strip ` /.-`) | `^\d{7}[A-HJ-NP-TV-Z](?:[ABDNP][MPCNE]\d{3})?$` | stdnum adds: if the branch code is not 000, the category must be `E` (not encoded here) | `1234567/M/A/E/001`, `1282182W` | `1219773U`, `1234567/M/A/X/000` → format | stdnum `tn/mf.py` |

#### Europe, the Americas and India

| id | P | category | mask | regex | checksum / rule | valid | invalid → code |
|---|---|---|---|---|---|---|---|
| `es.dni` | P1 | national_id | `99999999a` (uppercase) | `^\d{8}[A-Z]$` | **new** `es_dni`: letter = `TRWAGMYFPDXBNJZSQVHLCKE`[n mod 23] | `12345678Z`, `12345678-z` | `12345678A` → checksum; `1234567Z` → format |
| `es.nie` | P1 | national_id | `a9999999a` (uppercase) | `^[XYZ]\d{7}[A-Z]$` | **new** `es_nie`: X/Y/Z → 0/1/2, then the DNI letter rule | `X1234567L`, `Y1234567X` | `X1234567A` → checksum; `W1234567L` → format |
| `it.codice_fiscale` | P1 | tax_id | `aaaaaa**a**a***a` (uppercase; `*` allows omocodia letters) | `^[A-Z]{6}[0-9LMNP-V]{2}[ABCDEHLMPRST][0-9LMNP-V]{2}[A-Z][0-9LMNP-V]{3}[A-Z]$` | **new** `it_codice_fiscale`: odd/even value tables, Σ mod 26 → letter | `RCCMNL83S18D969H` | `RCCMNL83S18D969A` → checksum; `RCCMNL83X18D969H` → format |
| `ca.sin` | P1 | national_id | `999 999 999` | `^[1-79]\d{8}$` (stdnum rejects first digit 0 and 8) | `luhn` | `130 692 544`, `912345675` | `130 692 545` → checksum; `046 454 286`, `812 345 671` → format |
| `fr.siren` | P2 | company_id | `999 999 999` | `^\d{9}$` | `luhn` | `732 829 320` | `732 829 321` → checksum |
| `fr.siret` | P2 | company_id | `999 999 999 99999` | `^\d{14}$` | **new** `fr_siret`: Luhn; La Poste (SIREN 356000000) uses digit sum mod 5 | `732 829 320 00074` | `… 00075` → checksum |
| `nl.bsn` | P2 | national_id | `999999999` | `^\d{9}$` | **new** `nl_bsn` (11-proef): Σ d_i·(9..2) − d9 ≡ 0 (mod 11) | `111222333`, `123456782` | `111222334` → checksum |
| `nl.postcode` | P2 | postal_code | `9999 aa` (uppercase) | `^[1-9]\d{3}[A-Z]{2}$` (letter exclusions SA/SD/SS: unverified, not encoded) | — | `1234 AB`, `1234ab` | `0123 AB` → format |
| `mx.curp` | P2 | national_id | `aaaa999999aaaaaa*9` (uppercase) | `^[A-Z]{4}\d{6}[A-Z]{6}[0-9A-Z]\d$` | **new** `mx_curp`: Σ idx(c)·(18−i) over alphabet `0-9A-N&O-Z`, dv = (10 − Σ mod 10) mod 10. A YYMMDD date rule could be added (P2). | `BOXW310820HNERXN09` | `BOXW310820HNERXN08` → checksum |
| `in.gstin` | P2 | tax_id | `99aaaaa9999a*a*` (uppercase) | `^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$` | **new** `luhn_mod36` over `0-9A-Z` (stdnum also checks the state code list) | `27AAPFU0939F1ZV` | `27AAPFU0939F1ZW` → checksum; `…1YV` → format |
| `in.ifsc` | P2 | bank_account | `aaaa0******` (uppercase) | `^[A-Z]{4}0[A-Z0-9]{6}$` | — | `SBIN0001234` | `SBIN1001234` → format |

#### Numeric-mode presets (need engine feature E1, not regex-only)

| id | P | config (proposed E1 keys) | display → stored |
|---|---|---|---|
| `intl.integer` | P0 | group `""`, digits 0, negative on | `-42` → `-42` |
| `intl.decimal` | P0 | group `""`, radix `.`, digits optional (max 6) | `3.14159` → `3.14159` |
| `intl.amount_eu` | P1 | group `.`, radix `,`, digits 2 fixed | `1.234.567,89` → `1234567.89` |
| `intl.amount_space` | P1 | group ` ` (U+0020 / U+00A0 / U+202F all accepted), radix `,` | `1 234 567,89` → `1234567.89` |
| `in.amount_lakh` | P1 | grouping `lakh`, group `,`, radix `.` | `12,34,567.89` → `1234567.89` |
| `intl.currency`, `ir.amount_*` (existing) | P0 | move onto E1 (group `,`, radix `.`); `ir.*` digits 0 with an optional `٬` display | unchanged |

#### Candidates still to verify (not in Appendix A)

These were found in demand signals or regional relevance, but no primary or library source was read for them:

- `af.tazkira`: Afghan electronic ID, reportedly 13 digits shown as `9999-9999-99999` (unverified).
- `iq.national_card`: Iraqi unified national card number (unverified length).
- `sa.vehicle_plate`, `ae.vehicle_plate`: Arabic + Latin plate letters (unverified).
- `intl.ipv6`: IPv6 address; a regex-only preset, no competitor ships it.
- `intl.social_handle`: `^@?[A-Za-z0-9._]{1,30}$`; needs E8 for typing (R15).
- `us.itin`: `9XX-[50-65|70-88|90-92|94-99]-XXXX` (IRS ranges unverified).

### 6.2 Mask engine features to add

| # | Feature | Who has it | Our status | P | Spec notes |
|---|---|---|---|---|---|
| E1 | **Numeric / currency mode** | Inputmask `numeric`/`currency`/`decimal`/`integer`/`percentage`/`indianns`; IMask `MaskedNumber`; Cleave `numeral` (thousand/lakh/wan); Fluent Number Format; Forminator separators; Ninja (autoNumeric); jQuery Mask `reverse`; WPForms `alias:currency` | Partial: `formatter: thousands`, `,` and `.` fixed | **P0** | See the key list and the separator-mapping rule below. |
| E2 | **Completeness check for custom masks** with a `length` message | WPForms `val_inputmask_incomplete`; Jotform fill-mask enforcement; WS Form "Input Mask Validation"; Elementor add-on "strict validation"; Inputmask `isComplete`/`clearIncomplete` | Planned (F01 §9.2 `isComplete`/`validateCustom`) | **P0** | Keep it in F01. Use the `length` error code with the example placeholder. Optional parts must not count as missing. |
| E3 | **Case transform** | Inputmask `A`/`&` + `casing` (upper/lower/title); WPForms/Everest/WS Form `A`/`&`; Cleave `uppercase`/`lowercase`; IMask `prepareChar` | Per preset only (`uppercase`) | **P0** | Add tokens `A` (ASCII letter, uppercased) and `&` (ASCII letter or digit, uppercased). Add key `mask_case: ""\|upper\|lower`. Never locale-dependent (Turkish i). Arabic letters have no case, so `A` should reject them (otherwise use `a`). Also fixes the WPForms import mapping. |
| E4 | **Alternation / dynamic masks** | WPForms `\(a\|b)`; Inputmask alternator + `keepStatic`; IMask `mask:[..]` + `dispatch`; jQuery Mask function masks (BR phone); Maska dynamic | None | **P1** | Proposed syntax: `mask_custom` may hold several masks separated by `\|\|`. The engine picks the **shortest mask that the current data fits**, so `(99) 9999-9999\|\|(99) 99999-9999` switches at the 11th digit. Validation: OK if any mask is complete. Keep it linear (no regex). |
| E5 | **Phone mask from the selected country** | Cleave `phoneRegionCode` (about 240 regions); libphonenumber `AsYouType`; MetForm per-country formats; GF/WPForms/Fluent intl-tel-input (validation, formatting by intl-tel-input) | Per-country presets + `intl.phone_e164` (no mask) | **P1** | When the field has a country selector (F02/F17), switch to the `<cc>.mobile` preset mask of that country if one exists, otherwise to grouping by libphonenumber format. Store E.164. |
| E6 | **Date rules: min/max and relative dates** (today, ±N days), date-time | Inputmask `datetime` min/max; IMask `MaskedDate` min/max; Cleave `dateMin`/`dateMax` | `validator: date` (+ Jalali/Hijri, `year_range`) | **P1** | Add `date_min`/`date_max` (absolute or `today±N`) to the `date` validator; add the new `datetime` and `month_year` validators. Shared with F06. |
| E7 | **Custom regex validation** | Formidable Format regex; Jotform regex; GF hook / Regex Validation add-on (Unicode `\p{L}`); Inputmask `regex` mask | Planned F03 | **P1** | Keep the F03 ReDoS guard. Consider a small preset list like the GF add-on's (name, alphanumeric, no special characters) that uses our Arabic-aware letter class. |
| E8 | **Admin-defined character classes** | Inputmask `definitions`; IMask `definitions`; jQuery Mask `translation`; Maska tokens | None | P2 | For handles (`[A-Za-z0-9._]`), Latin-only letters (our `a` also accepts Arabic), Persian-only letters, hex. Proposal: `{name}` classes defined per field from an allow-list (latin, arabic, persian, hex, handle). |
| E9 | **Visible mask template / placeholder character** (show `__/__/____` on focus, always or never) | Inputmask `placeholder`, `showMaskOnFocus`, `showMaskOnHover`; IMask `lazy:false` + `placeholderChar`; jquery.maskedinput `_`; Elementor add-on "Hide Mask Placeholder" | None (lazy literals) | P2 | Default off. Screen readers read the underscores, and the template fights with the native placeholder. If added, put the template in the `placeholder` attribute only, never in `value`. |
| E10 | **Clear incomplete on blur** | Inputmask `clearIncomplete`; jQuery Mask `clearIfNotMatch`; Fluent "Clear if not match" | None (we show an error instead) | P2 | Offer as an option, default off: clearing loses the user's input, which is worse than an error. |
| E11 | **Unmask on submit / store canonical value** | Inputmask `autoUnmask`/`removeMaskOnSubmit`; jQuery Mask `cleanVal()` | **Have** (`mask_store`, `toStored`) | — | Answers R9. |
| E12 | **Right-to-left (reverse) money entry** | jQuery Mask `reverse`; Fluent "reversible mask"; Inputmask `numericInput`/`rightAlign` | None | P1 (part of E1) | Only inside numeric mode. Keep `dir="ltr"` (guide §5.5). |
| E13 | **Unbounded repeat** `9+` / `*{1,}` | Inputmask `{+}`/`{*}`; jQuery Mask `#` | `{n,m}` only | P2 | Allow `{n,}` with a hard cap (e.g. 64) so `maxlength` stays finite. |
| E14 | **Masks on textarea / password** | WS Form (Text, Phone, Textarea, Password); request [mask-for-textarea](https://wordpress.org/support/topic/mask-for-textarea/) | text, tel, postalcode | P2 | Password: no visible mask, validation only (GF switches masks off for passwords). |
| E15 | **Number shortcuts** (`k` = 1000, `m` = 1000000) | Inputmask numeric `shortcuts` | None | P2 | Part of E1, off by default. |
| E16 | **Import of competitor mask syntax** | — | Catalog note only | P2 | Use the mapping in §2.4: GF `?` → `[..]`; Jotform `#`/`@` → `9`/`a`; jQuery Mask `0 9 # A S` → `9 [9] 9{1,} * a`; Inputmask `A`/`&` → E3. Warn when Formidable or IMask `*` means "any character". |

**E1 keys (flat, per field).**

- `num_group`: `,` `.` space `'` `٬` or empty.
- `num_radix`: `.` `,` or `٫`.
- `num_digits`: 0–6.
- `num_digits_fixed`: 0 or 1.
- `num_negative`: 0 or 1.
- `num_min` and `num_max`: decimal strings.
- `num_prefix` and `num_suffix`: plain text, max 8 characters, shown outside the editable value.
- `num_grouping`: `thousand`, `lakh` or `wan`.

Store a canonical decimal string (`-1234567.89`). Never use floats on the server; compare with `bccomp` or a string compare.

**E1 separator mapping.** Map `٬` (U+066C) to the group separator and `٫` (U+066B) to the radix **before** the generic normalizer runs. Normalizer step 4 turns `٬` and `،` into `,`, which is wrong when `num_radix` is `,`. In numeric mode, also treat the configured group separator as removable and accept both `.` and `,` as radix when `num_group` is empty (Inputmask `substituteRadixPoint`).

### 6.3 Not gaps (deliberately not copied)

- **`alias:email` / `alias:url`** (WPForms, Inputmask). WPForms' own docs say they do not validate. Our email and url fields are the right tool.
- **`keydown` filtering** (jquery.maskedinput). It is the root of the Android reports in R11. Our guide §5.1 forbids it.
- **Forcing every optional slot** (Jotform "fill mask", Ninja Forms). Our `[ ]` / `{n,m}` are better.
- **Inputmask's `ip` typing mask** (`i{1,3}.j{1,3}…`). `intl.ipv4` is better validated by regex (or PHP `FILTER_VALIDATE_IP`) without a typing mask: variable-length octets make masks confusing.

---

## 7. Our differentiators (things no competitor checked here has)

1. **Checksum validation in core.** Luhn, ISO 7064 mod-97 IBAN with per-country lengths, Verhoeff (Aadhaar), Iranian national code and legal ID, Saudi NIN, Emirates ID, Kuwait Civil ID, ABN, NINO rules, SSN/EIN/NANP rules.
   - None of GF, WPForms, Fluent, Formidable, Ninja, Forminator, Everest, MetForm or Jotform validates a checksum. Their masks force only the shape.
   - GF community answers send users to regex or the `gform_validation` hook for anything beyond the shape (R8). A legacy GF reply says the mask "won't validate the value itself" (search snippet; the wording is unverified).
   - WPForms says its aliases "do not validate". The add-ons answer "This plugin only add mask" (R6).
   - The only checksum-like logic found in a library is Inputmask's SSN `postValidation` and Cleave's card brand detection.
2. **Server-side re-validation of the same rules.** Competitor masks are client-side typing aids. GF suggests a PHP hook for anything more.
3. **Persian, Arabic-Indic and full-width digits, Arabic separators (`٫ ٬ ،`) and bidi marks** are normalized in every masked field, and our `a` token accepts Arabic letters.
   - The code shows that Inputmask (`9` = `0-9` and full-width only; `a` has no Arabic), jQuery Mask (`\d`) and jquery.maskedinput (`[0-9]`) all reject Persian and Arabic digits.
   - That covers WPForms, Everest, Forminator, Fluent, Ninja and every CF7/Elementor add-on.
   - IMask's `a` includes Arabic, but its `0` does not include Persian digits.
4. **Jalali and Hijri date validation** (real-date check per calendar). Inputmask, IMask and Cleave only know the Gregorian calendar.
5. **Presets for Iran and 14 Arab countries** (mobile, IBAN, national ID, postal, Sheba, Shetab card). No competitor or add-on ships any of them; the add-on market is US- and Brazil-centric.
6. **Canonical storage** (digits, E.164, no grouping) chosen per field, plus typed error codes (`empty`/`format`/`checksum`/`rule`/`length`) with en/fa/ar messages.
7. **Brand-aware card grouping** (Amex 4-6-5 and others). Competitors ship a fixed `0000-0000-0000-0000`, which Formidable's own KB says "will not work with … American Express". Only Cleave (a library) has this.
8. **IME- and Android-safe binding** (no `keydown` filter, composition guard, per-preset `inputmode` and `autocomplete`). This answers the mobile complaints in R11. Fluent also has a keyboard-type select, but not the rest.
9. **Built in, not an add-on.** About 27 of the 74 add-on forum topics are about compatibility: multi-step forms, two forms on one page, themes, jQuery updates and other plugins. A native mask avoids that whole class of problems, provided F01 keeps working in multi-step forms and with several forms per page (F01 test cases).
10. **Test vectors**: 612 shared JS/PHP vectors in the catalog. No competitor publishes any.

---

## 8. Sources

Plugin and library code (read in this session):

- WPForms Lite 2.0.4 `includes/fields/class-text.php`, `src/Frontend/Frontend.php`, `assets/lib/jquery.inputmask.min.js` (Inputmask 5.0.9): https://wordpress.org/plugins/wpforms-lite/
- Fluent Forms 6.2.17 `app/Services/FormBuilder/ElementCustomization.php`, `app/Helpers/Helper.php` (`getNumericFormatters`), `app/Services/FormBuilder/Components/Text.php`: https://wordpress.org/plugins/fluentform/
- Formidable 7.0 `classes/views/frm-fields/back-end/input-mask-info.php`, `value-format.php`: https://wordpress.org/plugins/formidable/
- Ninja Forms 3.16.1 `includes/Config/FieldSettings.php`, `assets/js/lib/jquery.maskedinput.min.js`, `assets/js/min/autonumeric.min.js`: https://wordpress.org/plugins/ninja-forms/
- Forminator 1.58.4 `library/fields/phone.php`, `currency.php`, `number.php`, `library/abstracts/abstract-class-field.php` (`forminator_separators`): https://wordpress.org/plugins/forminator/
- Everest Forms 3.6.4 `includes/fields/class-evf-field-text.php`, `class-evf-field-phone.php`: https://wordpress.org/plugins/everest-forms/
- MetForm 4.3.4 `build/frontend/app/index.js`: https://wordpress.org/plugins/metform/
- Inputmask 5.0.9: https://unpkg.com/inputmask@5.0.9/lib/defaults.js, `lib/definitions.js`, `lib/extensions/inputmask.extensions.js`, `inputmask.numeric.extensions.js`, `inputmask.date.extensions.js`, `Changelog.md`. Docs: https://robinherbots.github.io/Inputmask/
- IMask 7.6.1: https://unpkg.com/imask@7.6.1/esm/masked/number.js, `esm/masked/pattern/input-definition.js`. Docs: https://imask.js.org/guide.html
- Cleave.js 1.6.0: https://unpkg.com/cleave.js@1.6.0/src/common/DefaultProperties.js, `src/shortcuts/CreditCardDetector.js`, `NumeralFormatter.js`. Repo: https://github.com/nosir/cleave.js
- jQuery Mask Plugin 1.14.16: https://unpkg.com/jquery-mask-plugin@1.14.16/src/jquery.mask.js. Docs: https://igorescobar.github.io/jQuery-Mask-Plugin/
- Maska 3.1.1 README: https://unpkg.com/maska@3.1.1/README.md
- libphonenumber-js 1.11.20 metadata: https://unpkg.com/libphonenumber-js@1.11.20/metadata.max.json
- python-stdnum 2.2 (checksum cross-check): https://github.com/arthurdejong/python-stdnum

Vendor documentation:

- https://docs.gravityforms.com/input-mask/
- https://docs.gravityforms.com/gform_input_masks/
- https://docs.gravityforms.com/gform_phone_formats/
- https://docs.gravityforms.com/number/
- https://wpforms.com/docs/how-to-use-custom-input-masks/
- https://fluentforms.com/docs/adding-a-mask-input-field-guide/
- https://formidableforms.com/knowledgebase/format/
- https://ninjaforms.com/blog/how-to-use-input-mask/
- https://docs.everestforms.net/docs/how-to-use-custom-input-mask/
- https://www.jotform.com/help/381-getting-started-with-the-basics-of-input-masking/
- https://www.jotform.com/help/how-to-set-up-regular-expression-regex-validation-for-the-short-text-element/
- https://wsform.com/knowledgebase/input-masks/
- https://surveyjs.io/survey-creator/documentation/end-user-guide/how-to-apply-input-mask-to-text-fields

Add-on readmes (plugins.svn.wordpress.org trunk) and install counts (api.wordpress.org, 2026-11-11):

- https://wordpress.org/plugins/form-masks-for-elementor/ (10,000+)
- https://wordpress.org/plugins/mask-form-elementor/ (10,000+)
- https://wordpress.org/plugins/masks-form-fields/ (9,000+)
- https://wordpress.org/plugins/input-masks-for-contact-form-7/ (900+)
- https://wordpress.org/plugins/input-masks-for-elementor-forms/ (600+)
- https://wordpress.org/plugins/mask-contact-form/ (10+)
- https://wordpress.org/plugins/regex-validation-for-gravity-forms/ (10+)
- https://wordpress.org/plugins/iran-phone-format-for-gforms/ (20+)
- https://wordpress.org/plugins/country-code-field-for-elementor-form/ (10,000+)

National ID rules:

- Receita Federal, CNPJ alfanumérico FAQ: https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/perguntas-e-respostas/cnpj/cnpj-alfanumerico.pdf
- VIN: https://en.wikipedia.org/wiki/Vehicle_identification_number
- ABA RTN: https://en.wikipedia.org/wiki/ABA_routing_transit_number
- UK sort code: https://en.wikipedia.org/wiki/Sort_code
- IFSC: https://en.wikipedia.org/wiki/Indian_Financial_System_Code

User requests: the threads linked in §5.

## 9. Unverified items (summary)

- Elementor Pro has no native mask (no docs or code available).
- Typeform, Tally and Fillout have no mask feature.
- Formidable Lite runs the regex Format.
- GF uses jquery.maskedinput.
- Fluent's `*` token at runtime.
- Everest numeric aliases work through `alias:`.
- MetForm phone library name; MetForm Pro features.
- Jotform URL/Cyrillic validation options.
- WS Form free/Pro split; SurveyJS option names.
- `br.phone` subscriber prefix `[2-5]`.
- `br.cpf` repdigit rejection (common practice, not confirmed as an official rule).
- `us.routing_number` prefix table.
- `nl.postcode` letter exclusions.
- `ps.mobile` with +972.
- Everything in "Candidates still to verify" (§6.1).
- R11 Stack Overflow URL (seen through a mirror); R12 and R18 snippets.

---

## Appendix A: catalog-ready entries for the proposed presets

Same shape as `masks-catalog.json` entries, minus the fa/ar labels and placeholders, which still have to be written. Extra fields:

- `priority`
- `checksum_strict` (VIN only)
- `examples_invalid_strict`

All examples passed the reference engine plus the new checksum functions (41 presets, 0 failures).

New checksum names to implement: `br_cpf`, `br_cnpj`, `us_aba`, `es_dni`, `es_nie`, `it_codice_fiscale`, `tr_tckimlik`, `vin_na`, `mod97_0`, `nl_bsn`, `mx_curp`, `luhn_mod36`, `gs1_mod10`, `fr_siret`.

New validators: `datetime`, `month_year`.

```json
[
 {"id":"br.cpf","country":"BR","category":"national_id","label":{"en":"Brazilian CPF"},
  "mask":"999.999.999-99","regex":"^\\d{11}$","strip":" .-","uppercase":false,"checksum":"br_cpf","validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["123.456.789-09","12345678909","529.982.247-25","۱۲۳۴۵۶۷۸۹۰۹"],
  "examples_invalid":[{"value":"123.456.789-00","expect":"checksum"},{"value":"111.111.111-11","expect":"rule"},{"value":"1234567890","expect":"format"}],
  "examples_format":[{"input":"12345678909","output":"123.456.789-09"}],
  "verification":"secondary","priority":"P0","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/br/cpf.py","https://wordpress.org/plugins/masks-form-fields/","https://wordpress.org/support/topic/cpf-validation/"]},
 {"id":"br.cnpj","country":"BR","category":"company_id","label":{"en":"Brazilian CNPJ (numeric or alphanumeric)"},
  "mask":"**.***.***/****-99","regex":"^[0-9A-Z]{12}\\d{2}$","strip":" .-/","uppercase":true,"checksum":"br_cnpj","validator":null,
  "inputmode":"text","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["11.222.333/0001-81","11222333000181","12.ABC.345/01DE-35","12abc34501de35"],
  "examples_invalid":[{"value":"11.222.333/0001-80","expect":"checksum"},{"value":"12.ABC.345/01DE-3A","expect":"format"},{"value":"00000000000000","expect":"rule"}],
  "examples_format":[{"input":"12abc34501de35","output":"12.ABC.345/01DE-35"},{"input":"11222333000181","output":"11.222.333/0001-81"}],
  "verification":"secondary","priority":"P0","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/br/cnpj.py","https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/perguntas-e-respostas/cnpj/cnpj-alfanumerico.pdf","https://wordpress.org/support/topic/cnpj-nao-aceita-letras/"]},
 {"id":"br.cep","country":"BR","category":"postal_code","label":{"en":"Brazilian CEP"},
  "mask":"99999-999","regex":"^\\d{8}$","strip":" .-","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"numeric","autocomplete":"postal-code","dir":"ltr","store":"digits",
  "examples_valid":["01310-100","01310100"],
  "examples_invalid":[{"value":"1310-100","expect":"format"}],
  "examples_format":[{"input":"01310100","output":"01310-100"}],
  "verification":"secondary","priority":"P0","sources":["https://wordpress.org/plugins/form-masks-for-elementor/","https://wordpress.org/plugins/masks-form-fields/"]},
 {"id":"br.mobile","country":"BR","category":"phone_mobile","label":{"en":"Brazilian mobile"},
  "mask":"(99) 99999-9999","regex":"^(?:\\+55|0055|55|0)?(?:[14689][1-9]|2[12478]|3[1-578]|5[13-5]|7[13-579])9\\d{8}$","strip":" -().","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"tel","autocomplete":"tel-national","dir":"ltr","store":"e164","e164":{"cc":"55","trunk":"0","nsn_length":[11]},
  "examples_valid":["(11) 91234-5678","+55 11 91234-5678","11912345678","(55) 99123-4567"],
  "examples_invalid":[{"value":"(11) 81234-5678","expect":"format"},{"value":"(10) 91234-5678","expect":"format"},{"value":"(11) 1234-5678","expect":"format"}],
  "examples_format":[{"input":"11912345678","output":"(11) 91234-5678"}],
  "verification":"secondary","priority":"P0","sources":["https://unpkg.com/libphonenumber-js@1.11.20/metadata.max.json","https://wordpress.org/support/topic/mascara-com-ddi-ddd-9-digitos/"]},
 {"id":"br.phone","country":"BR","category":"phone_landline","label":{"en":"Brazilian landline"},
  "mask":"(99) 9999-9999","regex":"^(?:\\+55|0055|55|0)?(?:[14689][1-9]|2[12478]|3[1-578]|5[13-5]|7[13-579])[2-5]\\d{7}$","strip":" -().","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"tel","autocomplete":"tel-national","dir":"ltr","store":"e164","e164":{"cc":"55","trunk":"0","nsn_length":[10]},
  "examples_valid":["(11) 3456-7890","+55 21 2345-6789"],
  "examples_invalid":[{"value":"(11) 9345-6789","expect":"format"},{"value":"(20) 3456-7890","expect":"format"}],
  "examples_format":[{"input":"1134567890","output":"(11) 3456-7890"}],
  "verification":"unverified","priority":"P1","sources":["https://unpkg.com/libphonenumber-js@1.11.20/metadata.max.json"]},
 {"id":"ca.sin","country":"CA","category":"national_id","label":{"en":"Canadian SIN"},
  "mask":"999 999 999","regex":"^[1-79]\\d{8}$","strip":" -","uppercase":false,"checksum":"luhn","validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["130 692 544","130-692-544","912345675"],
  "examples_invalid":[{"value":"130 692 545","expect":"checksum"},{"value":"046 454 286","expect":"format"},{"value":"812 345 671","expect":"format"}],
  "examples_format":[{"input":"130692544","output":"130 692 544"}],
  "verification":"secondary","priority":"P1","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/ca/sin.py"]},
 {"id":"us.routing_number","country":"US","category":"bank_account","label":{"en":"US ABA routing number"},
  "mask":"999999999","regex":"^(?:0\\d|1[0-2]|2[1-9]|3[0-2]|6[1-9]|7[0-2]|80)\\d{7}$","strip":" -","uppercase":false,"checksum":"us_aba","validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["011000015","021000021","122105155"],
  "examples_invalid":[{"value":"011000016","expect":"checksum"},{"value":"01100001","expect":"format"},{"value":"500000005","expect":"format"}],
  "examples_format":[],
  "verification":"unverified","priority":"P2","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/us/rtn.py","https://en.wikipedia.org/wiki/ABA_routing_transit_number"]},
 {"id":"us.phone_ext","country":"US","category":"phone_landline","label":{"en":"US phone with extension"},
  "mask":"(999) 999-9999[ x9{1,5}]","regex":"^(?:\\+1|1)?[2-9]\\d{2}[2-9]\\d{6}(?:x\\d{1,5})?$","strip":" -().","uppercase":false,"checksum":null,"validator":"nanp",
  "inputmode":"tel","autocomplete":"tel-national","dir":"ltr","store":"normalized",
  "examples_valid":["(202) 555-0123","(202) 555-0123 x45","2025550123x12345"],
  "examples_invalid":[{"value":"(202) 555-0123 x123456","expect":"format"},{"value":"(102) 555-0123","expect":"format"}],
  "examples_format":[{"input":"2025550123","output":"(202) 555-0123"},{"input":"202555012345","output":"(202) 555-0123 x45"}],
  "verification":"secondary","priority":"P1","sources":["https://docs.gravityforms.com/gform_input_masks/"]},
 {"id":"intl.time_24s","country":"intl","category":"time","label":{"en":"Time (HH:MM:SS)"},
  "mask":"99:99:99","regex":"^(?:[01]\\d|2[0-3]):[0-5]\\d:[0-5]\\d$","strip":" ","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["23:59:59","00:00:00","۰۸:۳۰:۰۰"],
  "examples_invalid":[{"value":"24:00:00","expect":"format"},{"value":"12:60:00","expect":"format"},{"value":"12:30","expect":"format"}],
  "examples_format":[{"input":"083000","output":"08:30:00"}],
  "verification":"secondary","priority":"P0","sources":["https://wordpress.org/plugins/fluentform/","https://wordpress.org/plugins/masks-form-fields/"]},
 {"id":"intl.datetime_dmy","country":"intl","category":"date","label":{"en":"Date and time (DD/MM/YYYY HH:MM[:SS])"},
  "mask":"99/99/9999 99:99[:99]","regex":"^\\d{2}/\\d{2}/\\d{4} (?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$","strip":"","uppercase":false,"checksum":null,"validator":"datetime",
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["29/02/2024 13:45","31/12/2024 23:59:59"],
  "examples_invalid":[{"value":"31/12/2024 24:00","expect":"format"}],
  "examples_format":[{"input":"290220241345","output":"29/02/2024 13:45"},{"input":"31122024235959","output":"31/12/2024 23:59:59"}],
  "verification":"secondary","priority":"P1","sources":["https://wordpress.org/plugins/fluentform/","https://wordpress.org/plugins/form-masks-for-elementor/"]},
 {"id":"intl.month_year","country":"intl","category":"date","label":{"en":"Month and year (MM/YYYY)"},
  "mask":"99/9999","regex":"^(?:0[1-9]|1[0-2])/\\d{4}$","strip":" ","uppercase":false,"checksum":null,"validator":"month_year",
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["02/2027","12/1999"],
  "examples_invalid":[{"value":"13/2024","expect":"format"},{"value":"2/2024","expect":"format"}],
  "examples_format":[{"input":"022027","output":"02/2027"}],
  "verification":"secondary","priority":"P1","sources":["https://wordpress.org/plugins/masks-form-fields/"]},
 {"id":"intl.vin","country":"intl","category":"generic","label":{"en":"Vehicle identification number (VIN)"},
  "mask":"*{17}","regex":"^[A-HJ-NPR-Z0-9]{17}$","strip":" -","uppercase":true,"checksum":null,"checksum_strict":"vin_na","validator":null,
  "inputmode":"text","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["1M8GDM9AXKP042788","5GZCZ43D13S812715","WVWZZZ1JZXW000001","1m8gdm9axkp042788"],
  "examples_invalid":[{"value":"1M8GDM9AIKP042788","expect":"format"},{"value":"1M8GDM9AXKP04278","expect":"format"}],"examples_invalid_strict":[{"value":"1M8GDM9A1KP042788","expect":"checksum"}],
  "examples_format":[{"input":"1m8gdm9axkp042788","output":"1M8GDM9AXKP042788"}],
  "verification":"secondary","priority":"P1","sources":["https://unpkg.com/inputmask@5.0.9/lib/extensions/inputmask.extensions.js","https://en.wikipedia.org/wiki/Vehicle_identification_number"]},
 {"id":"intl.swift_bic","country":"intl","category":"bank_account","label":{"en":"SWIFT / BIC code"},
  "mask":"aaaaaa**[***]","regex":"^[A-Z]{6}[A-Z0-9]{2}(?:[A-Z0-9]{3})?$","strip":" ","uppercase":true,"checksum":null,"validator":null,
  "inputmode":"text","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["DEUTDEFF","DEUTDEFF500","deutdeff"],
  "examples_invalid":[{"value":"DEUT1EFF","expect":"format"},{"value":"DEUTDEF","expect":"format"}],
  "examples_format":[{"input":"deutdeff500","output":"DEUTDEFF500"}],
  "verification":"secondary","priority":"P1","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/bic.py"]},
 {"id":"uk.sort_code","country":"UK","category":"bank_account","label":{"en":"UK bank sort code"},
  "mask":"99-99-99","regex":"^\\d{6}$","strip":" -","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["20-00-00","200000"],
  "examples_invalid":[{"value":"20-00-0","expect":"format"}],
  "examples_format":[{"input":"200000","output":"20-00-00"}],
  "verification":"secondary","priority":"P2","sources":["https://en.wikipedia.org/wiki/Sort_code"]},
 {"id":"es.dni","country":"ES","category":"national_id","label":{"en":"Spanish DNI"},
  "mask":"99999999a","regex":"^\\d{8}[A-Z]$","strip":" -","uppercase":true,"checksum":"es_dni","validator":null,
  "inputmode":"text","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["12345678Z","12345678-z"],
  "examples_invalid":[{"value":"12345678A","expect":"checksum"},{"value":"1234567Z","expect":"format"}],
  "examples_format":[{"input":"12345678z","output":"12345678Z"}],
  "verification":"secondary","priority":"P1","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/es/dni.py"]},
 {"id":"es.nie","country":"ES","category":"national_id","label":{"en":"Spanish NIE"},
  "mask":"a9999999a","regex":"^[XYZ]\\d{7}[A-Z]$","strip":" -","uppercase":true,"checksum":"es_nie","validator":null,
  "inputmode":"text","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["X1234567L","Y1234567X","x-1234567-l"],
  "examples_invalid":[{"value":"X1234567A","expect":"checksum"},{"value":"W1234567L","expect":"format"}],
  "examples_format":[{"input":"x1234567l","output":"X1234567L"}],
  "verification":"secondary","priority":"P1","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/es/nie.py"]},
 {"id":"it.codice_fiscale","country":"IT","category":"tax_id","label":{"en":"Italian codice fiscale"},
  "mask":"aaaaaa**a**a***a","regex":"^[A-Z]{6}[0-9LMNP-V]{2}[ABCDEHLMPRST][0-9LMNP-V]{2}[A-Z][0-9LMNP-V]{3}[A-Z]$","strip":" ","uppercase":true,"checksum":"it_codice_fiscale","validator":null,
  "inputmode":"text","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["RCCMNL83S18D969H","rccmnl83s18d969h"],
  "examples_invalid":[{"value":"RCCMNL83S18D969A","expect":"checksum"},{"value":"RCCMNL83S18D96","expect":"format"},{"value":"RCCMNL83X18D969H","expect":"format"}],
  "examples_format":[{"input":"rccmnl83s18d969h","output":"RCCMNL83S18D969H"}],
  "verification":"secondary","priority":"P1","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/it/codicefiscale.py"]},
 {"id":"fr.siren","country":"FR","category":"company_id","label":{"en":"French SIREN"},
  "mask":"999 999 999","regex":"^\\d{9}$","strip":" ","uppercase":false,"checksum":"luhn","validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["732 829 320"],
  "examples_invalid":[{"value":"732 829 321","expect":"checksum"}],
  "examples_format":[{"input":"732829320","output":"732 829 320"}],
  "verification":"secondary","priority":"P2","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/fr/siren.py"]},
 {"id":"fr.siret","country":"FR","category":"company_id","label":{"en":"French SIRET"},
  "mask":"999 999 999 99999","regex":"^\\d{14}$","strip":" ","uppercase":false,"checksum":"fr_siret","validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["732 829 320 00074"],
  "examples_invalid":[{"value":"732 829 320 00075","expect":"checksum"}],
  "examples_format":[{"input":"73282932000074","output":"732 829 320 00074"}],
  "verification":"secondary","priority":"P2","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/fr/siret.py"]},
 {"id":"nl.bsn","country":"NL","category":"national_id","label":{"en":"Dutch BSN"},
  "mask":"999999999","regex":"^\\d{9}$","strip":" .-","uppercase":false,"checksum":"nl_bsn","validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["111222333","123456782"],
  "examples_invalid":[{"value":"111222334","expect":"checksum"}],
  "examples_format":[],
  "verification":"secondary","priority":"P2","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/nl/bsn.py"]},
 {"id":"nl.postcode","country":"NL","category":"postal_code","label":{"en":"Dutch postcode"},
  "mask":"9999 aa","regex":"^[1-9]\\d{3}[A-Z]{2}$","strip":" ","uppercase":true,"checksum":null,"validator":null,
  "inputmode":"text","autocomplete":"postal-code","dir":"ltr","store":"normalized",
  "examples_valid":["1234 AB","1234ab"],
  "examples_invalid":[{"value":"0123 AB","expect":"format"}],
  "examples_format":[{"input":"1234ab","output":"1234 AB"}],
  "verification":"unverified","priority":"P2","sources":["https://en.wikipedia.org/wiki/Postal_codes_in_the_Netherlands"]},
 {"id":"mx.curp","country":"MX","category":"national_id","label":{"en":"Mexican CURP"},
  "mask":"aaaa999999aaaaaa*9","regex":"^[A-Z]{4}\\d{6}[A-Z]{6}[0-9A-Z]\\d$","strip":" ","uppercase":true,"checksum":"mx_curp","validator":null,
  "inputmode":"text","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["BOXW310820HNERXN09"],
  "examples_invalid":[{"value":"BOXW310820HNERXN08","expect":"checksum"}],
  "examples_format":[{"input":"boxw310820hnerxn09","output":"BOXW310820HNERXN09"}],
  "verification":"secondary","priority":"P2","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/mx/curp.py"]},
 {"id":"tr.mobile","country":"TR","category":"phone_mobile","label":{"en":"Turkish mobile"},
  "mask":"0599 999 99 99","regex":"^(?:\\+90|0090|90|0)?5(?:0[15-7]|1[06]|24|[34]\\d|5[1-59]|9[46])\\d{7}$","strip":" -().","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"tel","autocomplete":"tel-national","dir":"ltr","store":"e164","e164":{"cc":"90","trunk":"0","nsn_length":[10]},
  "examples_valid":["0532 123 45 67","+90 532 123 4567","5321234567"],
  "examples_invalid":[{"value":"0212 123 45 67","expect":"format"},{"value":"0532 123 45 6","expect":"format"}],
  "examples_format":[{"input":"05321234567","output":"0532 123 45 67"},{"input":"5321234567","output":"0532 123 45 67"}],
  "verification":"secondary","priority":"P1","sources":["https://unpkg.com/libphonenumber-js@1.11.20/metadata.max.json"]},
 {"id":"tr.tc_kimlik","country":"TR","category":"national_id","label":{"en":"Turkish T.C. Kimlik No"},
  "mask":"99999999999","regex":"^[1-9]\\d{10}$","strip":" ","uppercase":false,"checksum":"tr_tckimlik","validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["10000000146"],
  "examples_invalid":[{"value":"10000000147","expect":"checksum"},{"value":"01234567890","expect":"format"}],
  "examples_format":[],
  "verification":"secondary","priority":"P1","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/tr/tckimlik.py"]},
 {"id":"pk.mobile","country":"PK","category":"phone_mobile","label":{"en":"Pakistani mobile"},
  "mask":"0399 9999999","regex":"^(?:\\+92|0092|92|0)?3(?:[0-247]\\d|3[0-79]|55|64)\\d{7}$","strip":" -().","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"tel","autocomplete":"tel-national","dir":"ltr","store":"e164","e164":{"cc":"92","trunk":"0","nsn_length":[10]},
  "examples_valid":["0300 1234567","+92 300 1234567","3001234567"],
  "examples_invalid":[{"value":"0500 1234567","expect":"format"},{"value":"0300 123456","expect":"format"}],
  "examples_format":[{"input":"03001234567","output":"0300 1234567"}],
  "verification":"secondary","priority":"P1","sources":["https://unpkg.com/libphonenumber-js@1.11.20/metadata.max.json"]},
 {"id":"pk.cnic","country":"PK","category":"national_id","label":{"en":"Pakistani CNIC"},
  "mask":"99999-9999999-9","regex":"^\\d{13}$","strip":" -","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["34201-0891231-8","3420108912318"],
  "examples_invalid":[{"value":"34201-0891231","expect":"format"}],
  "examples_format":[{"input":"3420108912318","output":"34201-0891231-8"}],
  "verification":"secondary","priority":"P1","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/pk/cnic.py"]},
 {"id":"af.mobile","country":"AF","category":"phone_mobile","label":{"en":"Afghan mobile"},
  "mask":"079 999 9999","regex":"^(?:\\+93|0093|93|0)?7\\d{8}$","strip":" -().","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"tel","autocomplete":"tel-national","dir":"ltr","store":"e164","e164":{"cc":"93","trunk":"0","nsn_length":[9]},
  "examples_valid":["070 123 4567","+93 70 123 4567","0791234567","۰۷۰۱۲۳۴۵۶۷"],
  "examples_invalid":[{"value":"020 123 4567","expect":"format"},{"value":"070 123 456","expect":"format"}],
  "examples_format":[{"input":"0701234567","output":"070 123 4567"}],
  "verification":"secondary","priority":"P1","sources":["https://unpkg.com/libphonenumber-js@1.11.20/metadata.max.json"]},
 {"id":"ps.mobile","country":"PS","category":"phone_mobile","label":{"en":"Palestinian mobile"},
  "mask":"0599 999 999","regex":"^(?:\\+970|00970|970|0)?5[69]\\d{7}$","strip":" -().","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"tel","autocomplete":"tel-national","dir":"ltr","store":"e164","e164":{"cc":"970","trunk":"0","nsn_length":[9]},
  "examples_valid":["0599 123 456","+970 599 123 456","0569123456"],
  "examples_invalid":[{"value":"0579 123 456","expect":"format"}],
  "examples_format":[{"input":"0599123456","output":"0599 123 456"}],
  "verification":"secondary","priority":"P1","sources":["https://unpkg.com/libphonenumber-js@1.11.20/metadata.max.json"]},
 {"id":"ly.mobile","country":"LY","category":"phone_mobile","label":{"en":"Libyan mobile"},
  "mask":"0\\99-9999999","regex":"^(?:\\+218|00218|218|0)?9[1-6]\\d{7}$","strip":" -().","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"tel","autocomplete":"tel-national","dir":"ltr","store":"e164","e164":{"cc":"218","trunk":"0","nsn_length":[9]},
  "examples_valid":["091-2345678","+218 91 234 5678"],
  "examples_invalid":[{"value":"097-2345678","expect":"format"}],
  "examples_format":[{"input":"0912345678","output":"091-2345678"}],
  "verification":"secondary","priority":"P1","sources":["https://unpkg.com/libphonenumber-js@1.11.20/metadata.max.json"]},
 {"id":"sd.mobile","country":"SD","category":"phone_mobile","label":{"en":"Sudanese mobile"},
  "mask":"099 999 9999","regex":"^(?:\\+249|00249|249|0)?(?:1[0-2]|9[0-3569])\\d{7}$","strip":" -().","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"tel","autocomplete":"tel-national","dir":"ltr","store":"e164","e164":{"cc":"249","trunk":"0","nsn_length":[9]},
  "examples_valid":["091 234 5678","+249 91 234 5678","012 345 6789"],
  "examples_invalid":[{"value":"094 234 5678","expect":"format"}],
  "examples_format":[{"input":"0912345678","output":"091 234 5678"}],
  "verification":"secondary","priority":"P1","sources":["https://unpkg.com/libphonenumber-js@1.11.20/metadata.max.json"]},
 {"id":"ye.mobile","country":"YE","category":"phone_mobile","label":{"en":"Yemeni mobile"},
  "mask":"0799 999 999","regex":"^(?:\\+967|00967|967|0)?7[01378]\\d{7}$","strip":" -().","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"tel","autocomplete":"tel-national","dir":"ltr","store":"e164","e164":{"cc":"967","trunk":"0","nsn_length":[9]},
  "examples_valid":["0771 234 567","+967 771 234 567"],
  "examples_invalid":[{"value":"0751 234 567","expect":"format"}],
  "examples_format":[{"input":"0771234567","output":"0771 234 567"}],
  "verification":"secondary","priority":"P1","sources":["https://unpkg.com/libphonenumber-js@1.11.20/metadata.max.json"]},
 {"id":"sy.mobile","country":"SY","category":"phone_mobile","label":{"en":"Syrian mobile"},
  "mask":"0999 999 999","regex":"^(?:\\+963|00963|963|0)?(?:50|9[1-689])\\d{7}$","strip":" -().","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"tel","autocomplete":"tel-national","dir":"ltr","store":"e164","e164":{"cc":"963","trunk":"0","nsn_length":[9]},
  "examples_valid":["0944 567 890","+963 944 567 890"],
  "examples_invalid":[{"value":"0974 567 890","expect":"format"}],
  "examples_format":[{"input":"0944567890","output":"0944 567 890"}],
  "verification":"secondary","priority":"P1","sources":["https://unpkg.com/libphonenumber-js@1.11.20/metadata.max.json"]},
 {"id":"mr.mobile","country":"MR","category":"phone_mobile","label":{"en":"Mauritanian mobile"},
  "mask":"99 99 99 99","regex":"^(?:\\+222|00222|222)?[2-4][0-46-9]\\d{6}$","strip":" -().","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"tel","autocomplete":"tel-national","dir":"ltr","store":"e164","e164":{"cc":"222","trunk":"","nsn_length":[8]},
  "examples_valid":["22 12 34 56","+222 36 12 34 56"],
  "examples_invalid":[{"value":"25 12 34 56","expect":"format"}],
  "examples_format":[{"input":"22123456","output":"22 12 34 56"}],
  "verification":"secondary","priority":"P2","sources":["https://unpkg.com/libphonenumber-js@1.11.20/metadata.max.json"]},
 {"id":"eg.tax_id","country":"EG","category":"tax_id","label":{"en":"Egyptian tax registration number"},
  "mask":"999-999-999","regex":"^\\d{9}$","strip":" -","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["100-531-385","٣٣١-١٠٥-٢٦٨"],
  "examples_invalid":[{"value":"12345","expect":"format"}],
  "examples_format":[{"input":"100531385","output":"100-531-385"}],
  "verification":"secondary","priority":"P1","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/eg/tn.py"]},
 {"id":"ma.ice","country":"MA","category":"company_id","label":{"en":"Moroccan ICE"},
  "mask":"999999999999999","regex":"^\\d{15}$","strip":" ","uppercase":false,"checksum":"mod97_0","validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["001561191000066","00 21 36 09 30 00 040"],
  "examples_invalid":[{"value":"001561191000065","expect":"checksum"}],
  "examples_format":[],
  "verification":"secondary","priority":"P1","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/ma/ice.py"]},
 {"id":"dz.nif","country":"DZ","category":"tax_id","label":{"en":"Algerian NIF"},
  "mask":"999999999999999[99999]","regex":"^(?:\\d{15}|\\d{20})$","strip":" ","uppercase":false,"checksum":null,"validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["408020000150039","41201600000606600001"],
  "examples_invalid":[{"value":"4080200001500391","expect":"format"}],
  "examples_format":[],
  "verification":"secondary","priority":"P2","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/dz/nif.py"]},
 {"id":"tn.mf","country":"TN","category":"tax_id","label":{"en":"Tunisian matricule fiscal"},
  "mask":"9999999/a[/a/a/999]","regex":"^\\d{7}[A-HJ-NP-TV-Z](?:[ABDNP][MPCNE]\\d{3})?$","strip":" /.-","uppercase":true,"checksum":null,"validator":null,
  "inputmode":"text","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["1234567/M/A/E/001","1282182W","1496298/T/P/N/000"],
  "examples_invalid":[{"value":"1219773U","expect":"format"},{"value":"1234567/M/A/X/000","expect":"format"}],
  "examples_format":[{"input":"1234567MAE001","output":"1234567/M/A/E/001"}],
  "verification":"secondary","priority":"P2","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/tn/mf.py"]},
 {"id":"in.gstin","country":"IN","category":"tax_id","label":{"en":"Indian GSTIN"},
  "mask":"99aaaaa9999a*a*","regex":"^\\d{2}[A-Z]{5}\\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$","strip":" ","uppercase":true,"checksum":"luhn_mod36","validator":null,
  "inputmode":"text","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["27AAPFU0939F1ZV","27aapfu0939f1zv"],
  "examples_invalid":[{"value":"27AAPFU0939F1ZW","expect":"checksum"},{"value":"27AAPFU0939F1YV","expect":"format"}],
  "examples_format":[{"input":"27aapfu0939f1zv","output":"27AAPFU0939F1ZV"}],
  "verification":"secondary","priority":"P2","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/in_/gstin.py"]},
 {"id":"in.ifsc","country":"IN","category":"bank_account","label":{"en":"Indian IFSC"},
  "mask":"aaaa0******","regex":"^[A-Z]{4}0[A-Z0-9]{6}$","strip":" ","uppercase":true,"checksum":null,"validator":null,
  "inputmode":"text","autocomplete":null,"dir":"ltr","store":"normalized",
  "examples_valid":["SBIN0001234","sbin0001234"],
  "examples_invalid":[{"value":"SBIN1001234","expect":"format"}],
  "examples_format":[{"input":"sbin0001234","output":"SBIN0001234"}],
  "verification":"secondary","priority":"P2","sources":["https://en.wikipedia.org/wiki/Indian_Financial_System_Code"]},
 {"id":"intl.imei","country":"intl","category":"generic","label":{"en":"IMEI"},
  "mask":"99-999999-999999-9","regex":"^\\d{15}$","strip":" -/","uppercase":false,"checksum":"luhn","validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["49-015420-323751-8","490154203237518"],
  "examples_invalid":[{"value":"490154203237519","expect":"checksum"}],
  "examples_format":[{"input":"490154203237518","output":"49-015420-323751-8"}],
  "verification":"secondary","priority":"P2","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/imei.py"]},
 {"id":"intl.gtin","country":"intl","category":"generic","label":{"en":"GTIN / EAN / UPC barcode"},
  "mask":"9{8,14}","regex":"^(?:\\d{8}|\\d{12,14})$","strip":" -","uppercase":false,"checksum":"gs1_mod10","validator":null,
  "inputmode":"numeric","autocomplete":null,"dir":"ltr","store":"digits",
  "examples_valid":["4006381333931","036000291452","96385074"],
  "examples_invalid":[{"value":"4006381333932","expect":"checksum"},{"value":"123456789","expect":"format"}],
  "examples_format":[],
  "verification":"secondary","priority":"P2","sources":["https://github.com/arthurdejong/python-stdnum/blob/master/stdnum/ean.py"]}
]
```
