# Input masks, normalizers and validators — implementation reference

Status: reference for a future feature (no code in the plugin yet). Today the only related behaviour is a fixed
`pattern="^\+?(?:[0-9]|\s|\.|\(|\)|-){7,25}$"` on `tel` inputs (`includes/class-Emsfb-formbuilder.php:126`) and no
checksum validation anywhere.

Companion file: [`masks-catalog.json`](masks-catalog.json) — 99 presets with regexes, checksum names,
labels in en/fa/ar and test vectors. Everything in this guide (code included) was executed against that JSON in
Node 22.23.4 and PHP 8.3.6; results are at the end.

Contents

1. Design: mask vs validator vs normalizer
2. Data model (catalog fields)
3. Token syntax
4. Normalization
5. Client-side behaviour (typing, caret, paste, IME, mobile keyboards, RTL)
6. Server-side validation
7. Checksum algorithms (pseudocode + JavaScript + PHP)
8. Mask engine and storage helpers (JavaScript + PHP)
9. Recommended UX: presets, custom masks, storage option, error messages
10. Known pitfalls
11. Testing: vectors and results
12. Sources and unverified items

---

## 1. Design: three separate pieces

| Piece | Job | Runs | Can it reject? |
|---|---|---|---|
| **Normalizer** | Turn what the user typed or pasted into one canonical string: Persian/Arabic/full-width digits to ASCII, remove invisible bidi marks, fold separators, strip spaces/dashes, uppercase, left-pad. | Browser on every keystroke and paste; server before validating. | No. It never fails, it only rewrites. |
| **Mask (formatter)** | Display formatting while typing: inserts literals (`-`, `/`, spaces, `IR`), limits length, drops characters that fit no slot. | Browser only (optionally server, for "store formatted"). | No. A mask is a typing aid, not a check. |
| **Validator** | Decide whether the normalized value is acceptable: anchored regex → checksum (Luhn, mod-97, national-ID formula) → rule (real date, reserved ranges). Returns a failure *code*. | Browser on blur/submit (UX), and **always** on the server. | Yes: `empty`, `format`, `checksum`, `rule`. |

Pipeline for one field:

```
raw input ──normalize(mask)──► canonical ──regex──► checksum? ──validator? ──► ok
                │                                                        │
                └──formatValue(mask)──► what the input box shows          └──toStored(mask) ──► DB value
```

The three are independent on purpose: a preset may have a validator without a typing mask (UK postcode, Lebanese
mobile: variable shape), or a mask without a checksum (US ZIP), and an admin's *custom mask* never implies a
checksum.

## 2. Data model

Each object in `masks[]` of the catalog:

| Field | Meaning |
|---|---|
| `id` | `country.name` (`ir.national_id`, `intl.iban`). Stable key to store in form JSON. |
| `country` | ISO 3166-1 alpha-2 (UK uses `UK` in ids for readability, ISO is `GB`) or `intl`. |
| `category` | phone_mobile, phone_landline, national_id, company_id, tax_id, postal_code, bank_card, iban, bank_account, vehicle_plate, passport, date, time, currency, generic. |
| `label` / `placeholder` | `{en, fa, ar}`. Persian placeholders use Persian digits; the stored value never does. |
| `mask` | Typing mask in the token syntax (§3), or `null` when the shape is variable. |
| `formatter` | `thousands` or `card` for formatters that a fixed mask cannot express (amounts, brand-dependent card grouping). |
| `regex` | Anchored, applied to the **normalized** value. JS `new RegExp(src, 'u')`; PHP `preg_match('~'.src.'~u')`. Only syntax common to ES2017 and PCRE (no look-behind, no named groups, no `\p{}`), and never `~`. |
| `regex_strict` | Optional stricter variant (opt-in toggle). |
| `checksum` | Name of a checksum function (§7) or `null`. |
| `validator` | Name of a rule function (`date`, `us_ssn`, `egypt_nid` ...) or `null`. Extra fields used by it: `calendar`, `date_order`, `year_range`. |
| `strip`, `uppercase`, `fold_letters`, `pad_left` | Normalizer options (§4). |
| `store` | `digits`, `e164` (+ `e164: {cc, trunk, nsn_length}`), `strip_grouping`, `normalized`. |
| `inputmode`, `autocomplete`, `dir` | HTML attributes to emit. `dir` is always `ltr` for these fields. |
| `examples_valid`, `examples_invalid[{value, expect, reason}]`, `examples_format[{input, output}]` | Test vectors. `expect` is the failure code the validator must return. |
| `metadata` | Optional tables (Iran BIN → bank, Sheba bank codes, operator prefixes, Egypt governorates, IBAN lengths, card brands). Hints only — never reject on metadata. |
| `verification` | `confirmed` (checked against the primary source in this session), `secondary` (standard/Wikipedia-level source, not re-fetched), `unverified`. |
| `synthetic_examples` | `true`: checksum-valid examples were generated from obvious sequences (123456789…, 000012…) or are published test numbers — they are not real people's numbers. |

Top level also has `token_syntax`, `normalization`, `error_messages` (en/fa/ar, with `{label}`, `{placeholder}`,
`{length}` placeholders), and one-line summaries of every `checksums` / `validators` entry.

## 3. Token syntax (one language for the whole plugin)

| Token | Matches |
|---|---|
| `9` | one digit (after normalization, so `۹` and `٩` count) |
| `a` | one letter: A–Z, a–z, or an Arabic-script letter (U+0621–063A, U+0641–064A, U+066E–06D3, U+06FA–06FC) |
| `*` | one letter or digit |
| `[ … ]` | optional part |
| `{n}` / `{n,m}` | repeat the previous token n times / n to m times |
| `\x` | literal `x` (so `0\999 999 9999` = literal `0`, literal `9`, then digits) |
| anything else | literal, auto-inserted |

Behaviour: literals are written lazily (only once the next slot gets a character), a typed literal is consumed rather
than duplicated, a character that fits no slot is dropped, an optional slot that does not fit is skipped.
`maxlength` = token count after expansion.

**Compatibility.** `9`, `a`, `*` are the tokens of Gravity Forms (jQuery Masked Input) and of WPForms (Inputmask,
which also has `[ ]` and `{n,m}`). Gravity Forms marks "the rest is optional" with `?`: convert `999?9` → `999[9]`.
Fluent Forms uses jQuery Mask Plugin tokens (`0` digit, `9` optional digit, `A` alphanumeric, `S` letter): convert
`0→9`, `9→[9]`, `A→*`, `S→a` on import. (WPForms/Fluent details: unverified against their current docs.)

## 4. Normalization

Applied in this order (exact code in §8 `normalize`):

1. Digits: U+06F0–06F9 Persian `۰–۹`, U+0660–0669 Arabic-Indic `٠–٩`, U+FF10–FF19 full-width → ASCII `0–9`.
2. Remove invisible characters: U+200B–200F (ZWSP, **ZWNJ U+200C**, ZWJ, LRM, RLM), U+202A–202E, U+2066–2069, U+FEFF, U+00AD. Copy-paste from RTL apps routinely injects RLM/LRM.
3. Spaces U+00A0, U+2007, U+202F, U+3000 → space.
4. U+066B `٫` (Arabic decimal separator) → `.`; U+066C `٬` (Arabic thousands separator) and U+060C `،` (Arabic comma) → `,`.
5. Dash/minus variants (U+2010–2015, U+2212, U+FE58, U+FE63, U+FF0D) → `-`; full-width `+ / :` → ASCII.
6. Only if `fold_letters: "fa"`: `ي` U+064A and `ى` U+0649 → `ی` U+06CC; `ك` U+0643 → `ک` U+06A9. Do not fold in Arabic forms, where ي/ك are correct.
7. Collapse ASCII whitespace, trim.
8. `uppercase`: ASCII-only (never `strtoupper` with a locale, never `toLocaleUpperCase`).
9. Remove every character in `strip`.
10. `pad_left {to, min}`: all-digit values with `min ≤ length < to` are left-padded with `0` (Iranian national code).

**Stored vs displayed.** Store canonical ASCII (per `store`): `digits`, `e164` for phones, `strip_grouping` for
amounts, otherwise the normalized string. Display may re-apply the mask and convert digits to the viewer's script
(Persian digits on fa_IR sites, Arabic-Indic where an Arabic site prefers them). Never store the display form;
exports (CSV, Google Sheets, webhooks) get the canonical form so that spreadsheet formulas and CRMs work.

ZWNJ is meaningful in Persian *text*; the normalizer is only for masked/validated fields.

## 5. Client side

### 5.1 Keystroke formatting and caret

`attachMask()` (§8) listens to `input`, reformats with `applyMask()`, then restores the caret by **counting data
characters** (digits/letters, not literals) left of the caret before formatting, and placing the caret after the same
number of data characters in the new string (`caretForDataIndex`). This keeps the caret correct when a separator is
inserted or removed in the middle. Do not use `keydown`/`keypress` filtering: it breaks paste, autofill, IMEs,
Android keyboards (which often report `keyCode 229`) and accessibility tools.

### 5.2 Paste

Paste is just a big `input` event: the whole string goes through normalize → applyMask, so `IR06 0170 …`, `۰۹۱۲-۱۲۳-۴۵۶۷`,
`+966 50 123 4567` and text with RLM marks all end up formatted. For phone presets with a national mask, run
`toStored()`-style prefix handling first if you want `+98912…` pasted into a `0912 …` mask to become `0912 …`
(strip the country code, add the trunk) — otherwise the mask engine keeps the first characters that fit.

### 5.3 IME / composition

While `compositionstart … compositionend` is active (Persian/Arabic IMEs on some desktop systems, Japanese/Chinese,
Android voice input) never rewrite `value`: it cancels the composition. `attachMask` sets a flag and formats on
`compositionend`.

### 5.4 Mobile keyboards

Use `inputmode` from the catalog: `numeric` (IDs, cards, postal codes), `tel` (phones — includes `+`), `decimal`
(amounts, IP), `text` (IBAN/postcodes with letters). Keep `type="text"` (not `type="number"`: it drops leading zeros,
rejects separators and shows spinners). Set `autocomplete` (`tel-national`, `postal-code`, `cc-number`, `cc-exp`,
`cc-csc`) so autofill fills the right field. Many Persian/Arabic mobile keyboards type native digits even with
`inputmode="numeric"` — normalization handles it.

### 5.5 RTL

Numbers, IBANs and phone numbers are LTR sequences. In an RTL form:

```html
<label for="f1" dir="rtl">شماره موبایل</label>
<input id="f1" type="text" dir="ltr" inputmode="tel" autocomplete="tel-national"
       style="text-align:right" placeholder="۰۹۱۲ ۳۴۵ ۶۷۸۹">
```

Keep the label/help/error in the page direction, put `dir="ltr"` on the input (otherwise `+98 912` renders as
`912 98+` and the caret jumps between bidi runs), and use `text-align: right` (or `start` of the container) so the
field still lines up with the RTL layout. In read-only views (email, response viewer, PDF) wrap the value in
`<bdi dir="ltr">` or `<span dir="ltr">`.

### 5.6 When to show errors

Format on input; validate on blur and on submit; while typing only clear an existing error once the value becomes
valid. Use the failure `code` to choose the message (§9.4). Put the message in an element referenced by
`aria-describedby` and set `aria-invalid="true"`.

## 6. Server side

The server must normalize and validate again with the same catalog entry: client code can be bypassed, and cached
or old front-end scripts may run. Return the same failure codes so messages match. Persist `toStored()`. In
WordPress, load the catalog JSON from the plugin directory (`wp_json_file_decode()` / `json_decode(file_get_contents())`)
and cache it in a static; never accept a regex from the request — custom masks/regexes come only from saved form
settings (sanitized at save time).

## 7. Checksum and rule algorithms

All functions take the **normalized** string. JS and PHP below are copied verbatim from the tested reference files
(`efb-masks.js`, `efb-masks.php`).

### 7.1 Luhn (mod 10) — bank cards, Iran Shetab cards, Saudi NIN, Emirates ID

```
sum = 0; double = false
for each digit d from right to left:
    if double: d = d*2; if d > 9: d = d-9
    sum += d; double = not double
valid = sum mod 10 == 0
```

```js
function luhn(digits) {
  if (!/^\d+$/.test(digits)) { return false; }
  var sum = 0, dbl = false;
  for (var i = digits.length - 1; i >= 0; i--) {
    var d = digits.charCodeAt(i) - 48;
    if (dbl) { d *= 2; if (d > 9) { d -= 9; } }
    sum += d;
    dbl = !dbl;
  }
  return sum % 10 === 0;
}
function luhnCheckDigit(partial) { // digit to append so that luhn(partial + d) is true
  for (var d = 0; d <= 9; d++) { if (luhn(partial + d)) { return String(d); } }
  return null;
}
```

```php
<?php
function efbm_luhn($digits) {
	if (!preg_match('/^\d+$/', $digits)) {
		return false;
	}
	$sum = 0;
	$dbl = false;
	for ($i = strlen($digits) - 1; $i >= 0; $i--) {
		$d = (int) $digits[$i];
		if ($dbl) {
			$d *= 2;
			if ($d > 9) {
				$d -= 9;
			}
		}
		$sum += $d;
		$dbl = !$dbl;
	}
	return $sum % 10 === 0;
}
```

### 7.2 Iranian national code (کد ملی)

```
require 10 digits (after left-padding 8-9 digit input with zeros)
reject if all 10 digits are identical, or digits[3..8] == "000000"
s = Σ_{i=0..8} d[i] * (10 - i)
r = s mod 11
valid = (r < 2 and d[9] == r) or (r >= 2 and d[9] == 11 - r)
```

```js
function iranNationalCode(code) {
  if (!/^\d{10}$/.test(code)) { return false; }
  if (/^(\d)\1{9}$/.test(code)) { return false; }            // 0000000000 ... 9999999999
  if (code.substr(3, 6) === '000000') { return false; }      // middle block may not be all zero
  var sum = 0;
  for (var i = 0; i < 9; i++) { sum += (code.charCodeAt(i) - 48) * (10 - i); }
  var r = sum % 11, c = code.charCodeAt(9) - 48;
  return r < 2 ? c === r : c === 11 - r;
}
```

```php
<?php
function efbm_iran_national_code($code) {
	if (!preg_match('/^\d{10}$/', $code) || preg_match('/^(\d)\1{9}$/', $code)) {
		return false;
	}
	if (substr($code, 3, 6) === '000000') {
		return false;
	}
	$sum = 0;
	for ($i = 0; $i < 9; $i++) {
		$sum += (int) $code[$i] * (10 - $i);
	}
	$r = $sum % 11;
	$c = (int) $code[9];
	return $r < 2 ? $c === $r : $c === 11 - $r;
}
```

### 7.3 Iranian legal-entity national ID (شناسه ملی اشخاص حقوقی) — (unverified)

```
require 11 digits; reject repdigits and digits[3..8] == "000000"
add = d[9] + 2
s = Σ_{i=0..9} (d[i] + add) * W[i mod 5],   W = [29, 27, 23, 19, 17]
r = s mod 11; if r == 10: r = 0
valid = r == d[10]
```

No official specification was found. `@persian-tools/persian-tools` (implemented here) maps `r == 10 → 0`;
the `parsitext` crate maps `r < 2 ? r : 11 - r`. The catalog's synthetic examples have `r ∈ {0,1}` so they pass both;
before shipping, verify against a few published IDs of public bodies and pick the variant that matches.

```js
function iranLegalId(code) {
  if (!/^\d{11}$/.test(code)) { return false; }
  if (/^(\d)\1{10}$/.test(code)) { return false; }
  if (code.substr(3, 6) === '000000') { return false; }
  var weights = [29, 27, 23, 19, 17];
  var add = (code.charCodeAt(9) - 48) + 2;                  // tenth digit + 2 is added to every digit
  var sum = 0;
  for (var i = 0; i < 10; i++) { sum += ((code.charCodeAt(i) - 48) + add) * weights[i % 5]; }
  var r = sum % 11;
  if (r === 10) { r = 0; }
  return r === code.charCodeAt(10) - 48;
}
```

```php
<?php
function efbm_iran_legal_id($code) {
	if (!preg_match('/^\d{11}$/', $code) || preg_match('/^(\d)\1{10}$/', $code)) {
		return false;
	}
	if (substr($code, 3, 6) === '000000') {
		return false;
	}
	$weights = array(29, 27, 23, 19, 17);
	$add = (int) $code[9] + 2;
	$sum = 0;
	for ($i = 0; $i < 10; $i++) {
		$sum += ((int) $code[$i] + $add) * $weights[$i % 5];
	}
	$r = $sum % 11;
	if ($r === 10) {
		$r = 0;
	}
	return $r === (int) $code[10];
}
```

### 7.4 Verhoeff — Aadhaar

```
tables d (10x10 dihedral D5 multiplication), p (8x10 permutation), inv
c = 0
for i, digit in enumerate(digits reversed): c = d[c][p[i mod 8][digit]]
valid = c == 0
(to generate a check digit for a partial number use p[(i+1) mod 8] and append inv[c])
```

```js
var VH_D = [
  [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [1, 2, 3, 4, 0, 6, 7, 8, 9, 5], [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
  [3, 4, 0, 1, 2, 8, 9, 5, 6, 7], [4, 0, 1, 2, 3, 9, 5, 6, 7, 8], [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
  [6, 5, 9, 8, 7, 1, 0, 4, 3, 2], [7, 6, 5, 9, 8, 2, 1, 0, 4, 3], [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
  [9, 8, 7, 6, 5, 4, 3, 2, 1, 0]];
var VH_P = [
  [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [1, 5, 7, 6, 2, 8, 3, 0, 9, 4], [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
  [8, 9, 1, 6, 0, 4, 3, 5, 2, 7], [9, 4, 5, 3, 1, 2, 6, 8, 7, 0], [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
  [2, 7, 9, 3, 8, 0, 6, 4, 1, 5], [7, 0, 4, 6, 9, 1, 3, 2, 5, 8]];
var VH_INV = [0, 4, 3, 2, 1, 5, 6, 7, 8, 9];
function verhoeff(digits) {
  if (!/^\d+$/.test(digits)) { return false; }
  var c = 0, n = digits.length;
  for (var i = 0; i < n; i++) { c = VH_D[c][VH_P[i % 8][digits.charCodeAt(n - 1 - i) - 48]]; }
  return c === 0;
}
function verhoeffCheckDigit(partial) {
  var c = 0, n = partial.length;
  for (var i = 0; i < n; i++) { c = VH_D[c][VH_P[(i + 1) % 8][partial.charCodeAt(n - 1 - i) - 48]]; }
  return String(VH_INV[c]);
}
```

```php
<?php
function efbm_verhoeff($digits) {
	static $d = array(
		array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9), array(1, 2, 3, 4, 0, 6, 7, 8, 9, 5), array(2, 3, 4, 0, 1, 7, 8, 9, 5, 6),
		array(3, 4, 0, 1, 2, 8, 9, 5, 6, 7), array(4, 0, 1, 2, 3, 9, 5, 6, 7, 8), array(5, 9, 8, 7, 6, 0, 4, 3, 2, 1),
		array(6, 5, 9, 8, 7, 1, 0, 4, 3, 2), array(7, 6, 5, 9, 8, 2, 1, 0, 4, 3), array(8, 7, 6, 5, 9, 3, 2, 1, 0, 4),
		array(9, 8, 7, 6, 5, 4, 3, 2, 1, 0));
	static $p = array(
		array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9), array(1, 5, 7, 6, 2, 8, 3, 0, 9, 4), array(5, 8, 0, 3, 7, 9, 6, 1, 4, 2),
		array(8, 9, 1, 6, 0, 4, 3, 5, 2, 7), array(9, 4, 5, 3, 1, 2, 6, 8, 7, 0), array(4, 2, 8, 6, 5, 7, 3, 9, 0, 1),
		array(2, 7, 9, 3, 8, 0, 6, 4, 1, 5), array(7, 0, 4, 6, 9, 1, 3, 2, 5, 8));
	if (!preg_match('/^\d+$/', $digits)) {
		return false;
	}
	$c = 0;
	$n = strlen($digits);
	for ($i = 0; $i < $n; $i++) {
		$c = $d[$c][$p[$i % 8][(int) $digits[$n - 1 - $i]]];
	}
	return $c === 0;
}
```

### 7.5 ISO 7064 MOD 97-10 — IBAN / Sheba

```
normalize: remove spaces/dashes, uppercase
require ^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$ and length == LENGTHS[country]
rearranged = value[4:] + value[0:4]
numeric = rearranged with A=10, B=11, … Z=35
rem = 0; for each 7-digit chunk c of numeric: rem = int(str(rem) + c) mod 97     (no BigInt needed)
valid = rem == 1
check digits for a new IBAN = 98 - mod97(numeric(country + "00" + bban))
```

Length table (SWIFT registry, from the Wikipedia copy of the registry) plus national-use countries (IR Sheba, DZ, MA
and several African countries) — the same table is in the catalog under `intl.iban.metadata`:

- Registry (89): AD 24 · AE 23 · AL 28 · AT 20 · AZ 28 · BA 20 · BE 16 · BG 22 · BH 22 · BI 27 · BR 29 · BY 28 · CH 21 · CR 22 · CY 28 · CZ 24 · DE 22 · DJ 27 · DK 18 · DO 28 · EE 20 · EG 29 · ES 24 · FI 18 · FK 18 · FO 18 · FR 27 · GB 22 · GE 22 · GI 23 · GL 18 · GR 27 · GT 28 · HN 28 · HR 21 · HU 28 · IE 22 · IL 23 · IQ 23 · IS 26 · IT 27 · JO 30 · KW 30 · KZ 20 · LB 28 · LC 32 · LI 21 · LT 20 · LU 20 · LV 21 · LY 25 · MC 27 · MD 24 · ME 22 · MK 19 · MN 20 · MR 27 · MT 31 · MU 30 · NI 28 · NL 18 · NO 15 · OM 23 · PK 24 · PL 28 · PS 29 · PT 25 · QA 29 · RO 24 · RS 22 · RU 33 · SA 24 · SC 31 · SD 18 · SE 24 · SI 19 · SK 24 · SM 27 · SO 23 · ST 25 · SV 28 · TL 23 · TN 24 · TR 26 · UA 29 · VA 22 · VG 24 · XK 20 · YE 30
- National use only (22): IR 26 · DZ 26 · MA 28 · AO 25 · BJ 28 · BF 28 · CV 25 · CM 27 · CF 27 · TD 27 · KM 27 · CG 27 · CI 28 · GQ 27 · GA 27 · GW 25 · MG 27 · ML 28 · MZ 25 · NE 28 · SN 28 · TG 28

```js
// SWIFT IBAN registry lengths (+ national-use / "aspirational" countries marked in the catalog).
var IBAN_LENGTHS = {
  AD: 24, AE: 23, AL: 28, AT: 20, AZ: 28, BA: 20, BE: 16, BG: 22, BH: 22, BI: 27, BR: 29, BY: 28, CH: 21,
  CR: 22, CY: 28, CZ: 24, DE: 22, DJ: 27, DK: 18, DO: 28, EE: 20, EG: 29, ES: 24, FI: 18, FK: 18, FO: 18,
  FR: 27, GB: 22, GE: 22, GI: 23, GL: 18, GR: 27, GT: 28, HN: 28, HR: 21, HU: 28, IE: 22, IL: 23, IQ: 23,
  IS: 26, IT: 27, JO: 30, KW: 30, KZ: 20, LB: 28, LC: 32, LI: 21, LT: 20, LU: 20, LV: 21, LY: 25, MC: 27,
  MD: 24, ME: 22, MK: 19, MN: 20, MR: 27, MT: 31, MU: 30, NI: 28, NL: 18, NO: 15, OM: 23, PK: 24, PL: 28,
  PS: 29, PT: 25, QA: 29, RO: 24, RS: 22, RU: 33, SA: 24, SC: 31, SD: 18, SE: 24, SI: 19, SK: 24, SM: 27,
  SO: 23, ST: 25, SV: 28, TL: 23, TN: 24, TR: 26, UA: 29, VA: 22, VG: 24, XK: 20, YE: 30,
  // national use only (not in the SWIFT registry): Iran (Sheba), Algeria, Morocco + other partial adopters
  IR: 26, DZ: 26, MA: 28, AO: 25, BJ: 28, BF: 28, CV: 25, CM: 27, CF: 27, TD: 27, KM: 27, CG: 27, CI: 28,
  GQ: 27, GA: 27, GW: 25, MG: 27, ML: 28, MZ: 25, NE: 28, SN: 28, TG: 28
};
// Returns (numeric string of s) mod 97, processing in 7-digit chunks so no BigInt is needed.
function mod97(numeric) {
  var rem = 0;
  for (var i = 0; i < numeric.length; i += 7) {
    rem = parseInt(String(rem) + numeric.substr(i, 7), 10) % 97;
  }
  return rem;
}
function ibanToNumeric(iban) { // move first 4 chars to the end, letters -> 10..35
  var r = iban.substr(4) + iban.substr(0, 4), out = '';
  for (var i = 0; i < r.length; i++) {
    var c = r.charCodeAt(i);
    out += (c >= 65 && c <= 90) ? String(c - 55) : r.charAt(i);
  }
  return out;
}
function iban(value) {
  if (!/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/.test(value)) { return false; }
  var len = IBAN_LENGTHS[value.substr(0, 2)];
  if (!len || value.length !== len) { return false; }
  return mod97(ibanToNumeric(value)) === 1;
}
function ibanCheckDigits(country, bban) { // ISO 7064 MOD 97-10 check digits
  var cd = 98 - mod97(ibanToNumeric(country + '00' + bban));
  return (cd < 10 ? '0' : '') + cd;
}
```

```php
<?php
function efbm_iban_lengths() {
	return array(
		'AD' => 24, 'AE' => 23, 'AL' => 28, 'AT' => 20, 'AZ' => 28, 'BA' => 20, 'BE' => 16, 'BG' => 22, 'BH' => 22,
		'BI' => 27, 'BR' => 29, 'BY' => 28, 'CH' => 21, 'CR' => 22, 'CY' => 28, 'CZ' => 24, 'DE' => 22, 'DJ' => 27,
		'DK' => 18, 'DO' => 28, 'EE' => 20, 'EG' => 29, 'ES' => 24, 'FI' => 18, 'FK' => 18, 'FO' => 18, 'FR' => 27,
		'GB' => 22, 'GE' => 22, 'GI' => 23, 'GL' => 18, 'GR' => 27, 'GT' => 28, 'HN' => 28, 'HR' => 21, 'HU' => 28,
		'IE' => 22, 'IL' => 23, 'IQ' => 23, 'IS' => 26, 'IT' => 27, 'JO' => 30, 'KW' => 30, 'KZ' => 20, 'LB' => 28,
		'LC' => 32, 'LI' => 21, 'LT' => 20, 'LU' => 20, 'LV' => 21, 'LY' => 25, 'MC' => 27, 'MD' => 24, 'ME' => 22,
		'MK' => 19, 'MN' => 20, 'MR' => 27, 'MT' => 31, 'MU' => 30, 'NI' => 28, 'NL' => 18, 'NO' => 15, 'OM' => 23,
		'PK' => 24, 'PL' => 28, 'PS' => 29, 'PT' => 25, 'QA' => 29, 'RO' => 24, 'RS' => 22, 'RU' => 33, 'SA' => 24,
		'SC' => 31, 'SD' => 18, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27, 'SO' => 23, 'ST' => 25, 'SV' => 28,
		'TL' => 23, 'TN' => 24, 'TR' => 26, 'UA' => 29, 'VA' => 22, 'VG' => 24, 'XK' => 20, 'YE' => 30,
		// national use only (not in the SWIFT registry)
		'IR' => 26, 'DZ' => 26, 'MA' => 28, 'AO' => 25, 'BJ' => 28, 'BF' => 28, 'CV' => 25, 'CM' => 27, 'CF' => 27,
		'TD' => 27, 'KM' => 27, 'CG' => 27, 'CI' => 28, 'GQ' => 27, 'GA' => 27, 'GW' => 25, 'MG' => 27, 'ML' => 28,
		'MZ' => 25, 'NE' => 28, 'SN' => 28, 'TG' => 28,
	);
}

function efbm_mod97($numeric) {
	$rem = 0;
	$len = strlen($numeric);
	for ($i = 0; $i < $len; $i += 7) {
		$rem = (int) ((string) $rem . substr($numeric, $i, 7)) % 97;
	}
	return $rem;
}

function efbm_iban_to_numeric($iban) {
	$r = substr($iban, 4) . substr($iban, 0, 4);
	$out = '';
	$len = strlen($r);
	for ($i = 0; $i < $len; $i++) {
		$c = ord($r[$i]);
		$out .= ($c >= 65 && $c <= 90) ? (string) ($c - 55) : $r[$i];
	}
	return $out;
}

function efbm_iban($value) {
	if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $value)) {
		return false;
	}
	$lengths = efbm_iban_lengths();
	$cc = substr($value, 0, 2);
	if (!isset($lengths[$cc]) || strlen($value) !== $lengths[$cc]) {
		return false;
	}
	return efbm_mod97(efbm_iban_to_numeric($value)) === 1;
}
```

### 7.6 Saudi National ID / Iqama

```
require ^[12]\d{9}$      (1 = citizen, 2 = resident)
valid = luhn(all 10 digits)          (digits at indexes 0,2,4,6,8 are the doubled ones)
```

SAMA's rulebook confirms "first digit 1/2, 10th digit is a check digit"; the formula itself is only given by NIC
on request, and every public library uses Luhn (secondary).

```js
// 10 digits, first digit 1 = Saudi citizen, 2 = resident (Iqama); last digit = Luhn check digit.
function saudiNin(id) {
  return /^[12]\d{9}$/.test(id) && luhn(id);
}
```

```php
<?php
function efbm_saudi_nin($id) {
	return (bool) preg_match('/^[12]\d{9}$/', $id) && efbm_luhn($id);
}
```

### 7.7 Emirates ID

```
require ^784\d{12}$       (784-YYYY-NNNNNNN-C)
valid = luhn(15 digits)
```

```js
// 784-YYYY-NNNNNNN-C : 15 digits, 784 = ISO 3166 numeric code of the UAE, C = Luhn check digit.
function emiratesId(id) {
  return /^784\d{12}$/.test(id) && luhn(id);
}
```

```php
<?php
function efbm_emirates_id($id) {
	return (bool) preg_match('/^784\d{12}$/', $id) && efbm_luhn($id);
}
```

### 7.8 Kuwait Civil ID

```
require ^[123]\d{11}$; century = {1:1800, 2:1900, 3:2000}[d0]
require valid Gregorian date (century + d[1..2], month d[3..4], day d[5..6])
w = [2,1,6,3,7,9,10,5,8,4,2]
check = 11 - (Σ_{i=0..10} w[i]*d[i]) mod 11      (10 and 11 can never match)
valid = check == d[11]
```

```js
function isValidGregorian(y, m, d) {
  if (m < 1 || m > 12 || d < 1) { return false; }
  var dim = [31, (y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
  return d <= dim[m - 1];
}
// C YYMMDD NNNN K - C: 1=1800s 2=1900s 3=2000s; K = 11 - (weighted sum mod 11), 10/11 never valid.
function kuwaitCivilId(id) {
  if (!/^[123]\d{11}$/.test(id)) { return false; }
  var century = { '1': 1800, '2': 1900, '3': 2000 }[id.charAt(0)];
  if (!isValidGregorian(century + parseInt(id.substr(1, 2), 10), parseInt(id.substr(3, 2), 10), parseInt(id.substr(5, 2), 10))) { return false; }
  var w = [2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2], sum = 0;
  for (var i = 0; i < 11; i++) { sum += w[i] * (id.charCodeAt(i) - 48); }
  return 11 - (sum % 11) === id.charCodeAt(11) - 48;
}
```

```php
<?php
function efbm_is_valid_gregorian($y, $m, $d) {
	return $m >= 1 && $m <= 12 && $d >= 1 && checkdate($m, $d, $y);
}

function efbm_kuwait_civil_id($id) {
	if (!preg_match('/^[123]\d{11}$/', $id)) {
		return false;
	}
	$centuries = array('1' => 1800, '2' => 1900, '3' => 2000);
	$century = $centuries[$id[0]];
	if (!efbm_is_valid_gregorian($century + (int) substr($id, 1, 2), (int) substr($id, 3, 2), (int) substr($id, 5, 2))) {
		return false;
	}
	$w = array(2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
	$sum = 0;
	for ($i = 0; $i < 11; $i++) {
		$sum += $w[$i] * (int) $id[$i];
	}
	return 11 - ($sum % 11) === (int) $id[11];
}
```

### 7.9 Egypt National ID (structural)

```
require ^[23]\d{13}$       C YYMMDD GG SSSS K
year = (C == 2 ? 1900 : 2000) + YY; require valid Gregorian date
require GG in {01–04, 11–19, 21–29, 31–35, 88}
(13th digit odd = male; the check digit K has no published algorithm — not checked)
```

```js
var EG_GOVERNORATES = ['01', '02', '03', '04', '11', '12', '13', '14', '15', '16', '17', '18', '19', '21', '22',
  '23', '24', '25', '26', '27', '28', '29', '31', '32', '33', '34', '35', '88'];
// C YYMMDD GG SSSS K (14 digits). C: 2 = 1900-1999, 3 = 2000-2099. GG governorate. 13th digit odd = male.
// The check digit algorithm is not published -> structural check only.
function egyptNid(id) {
  if (!/^[23]\d{13}$/.test(id)) { return false; }
  var year = (id.charAt(0) === '2' ? 1900 : 2000) + parseInt(id.substr(1, 2), 10);
  if (!isValidGregorian(year, parseInt(id.substr(3, 2), 10), parseInt(id.substr(5, 2), 10))) { return false; }
  return EG_GOVERNORATES.indexOf(id.substr(7, 2)) !== -1;
}
```

```php
<?php
function efbm_egypt_nid($id) {
	static $gov = array('01', '02', '03', '04', '11', '12', '13', '14', '15', '16', '17', '18', '19', '21', '22',
		'23', '24', '25', '26', '27', '28', '29', '31', '32', '33', '34', '35', '88');
	if (!preg_match('/^[23]\d{13}$/', $id)) {
		return false;
	}
	$year = ($id[0] === '2' ? 1900 : 2000) + (int) substr($id, 1, 2);
	if (!efbm_is_valid_gregorian($year, (int) substr($id, 3, 2), (int) substr($id, 5, 2))) {
		return false;
	}
	return in_array(substr($id, 7, 2), $gov, true);
}
```

### 7.10 Australian ABN

```
require 11 digits
d[0] = d[0] - 1
valid = (Σ d[i] * [10,1,3,5,7,9,11,13,15,17,19][i]) mod 89 == 0
```

```js
function auAbn(abn) {
  if (!/^\d{11}$/.test(abn)) { return false; }
  var w = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19], sum = 0;
  for (var i = 0; i < 11; i++) {
    var d = abn.charCodeAt(i) - 48;
    if (i === 0) { d -= 1; }
    sum += d * w[i];
  }
  return sum % 89 === 0;
}
```

```php
<?php
function efbm_au_abn($abn) {
	if (!preg_match('/^\d{11}$/', $abn)) {
		return false;
	}
	$w = array(10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19);
	$sum = 0;
	for ($i = 0; $i < 11; $i++) {
		$d = (int) $abn[$i];
		if ($i === 0) {
			$d -= 1;
		}
		$sum += $d * $w[$i];
	}
	return $sum % 89 === 0;
}
```

### 7.11 UK National Insurance number (rules)

```
require ^[A-Z]{2}\d{6}[A-D]$
first letter not in DFIQUV; second letter not in DFIOQUV
prefix not in {BG, GB, KN, NK, NT, TN, ZZ}
```

```js
// HMRC: 2 prefix letters + 6 digits + suffix A-D. First letter not D F I Q U V; second not D F I O Q U V;
// prefixes BG GB KN NK NT TN ZZ are never allocated (temporary TRN-style numbers are rejected too).
function ukNino(v) {
  var m = /^([A-Z])([A-Z])\d{6}[A-D]$/.exec(v);
  if (!m) { return false; }
  if ('DFIQUV'.indexOf(m[1]) !== -1 || 'DFIOQUV'.indexOf(m[2]) !== -1) { return false; }
  return ['BG', 'GB', 'KN', 'NK', 'NT', 'TN', 'ZZ'].indexOf(m[1] + m[2]) === -1;
}
```

```php
<?php
function efbm_uk_nino($v) {
	if (!preg_match('/^([A-Z])([A-Z])\d{6}[A-D]$/', $v, $m)) {
		return false;
	}
	if (strpos('DFIQUV', $m[1]) !== false || strpos('DFIOQUV', $m[2]) !== false) {
		return false;
	}
	return !in_array($m[1] . $m[2], array('BG', 'GB', 'KN', 'NK', 'NT', 'TN', 'ZZ'), true);
}
```

### 7.12 US SSN, EIN and NANP rules

```
SSN: 9 digits; area != 000, 666, 900–999; group != 00; serial != 0000
EIN: 9 digits; first two digits in the IRS campus prefix list
NANP: strip optional +1/1; NXX NXX XXXX with N = 2–9; area code and exchange are not N11
```

```js
// 9 digits AAA-GG-SSSS. Never issued: area 000, 666, 900-999; group 00; serial 0000.
function usSsn(v) {
  if (!/^\d{9}$/.test(v)) { return false; }
  var area = v.substr(0, 3);
  if (area === '000' || area === '666' || area.charAt(0) === '9') { return false; }
  return v.substr(3, 2) !== '00' && v.substr(5, 4) !== '0000';
}
// US EIN: 9 digits, the 2-digit prefix must be on the IRS list of valid campus prefixes.
var EIN_PREFIXES = ('01 02 03 04 05 06 10 11 12 13 14 15 16 20 21 22 23 24 25 26 27 30 31 32 33 34 35 36 37 38 39 ' +
  '40 41 42 43 44 45 46 47 48 50 51 52 53 54 55 56 57 58 59 60 61 62 63 64 65 66 67 68 71 72 73 74 75 76 77 80 81 ' +
  '82 83 84 85 86 87 88 90 91 92 93 94 95 98 99').split(' ');
function usEin(v) {
  return /^\d{9}$/.test(v) && EIN_PREFIXES.indexOf(v.substr(0, 2)) !== -1;
}
// NANP (US/CA): optional country code 1, then NXX NXX XXXX; N = 2-9; area code and exchange may not be N11.
function nanp(v) {
  var d = v.replace(/^\+?1(?=\d{10}$)/, '');
  if (!/^[2-9]\d{2}[2-9]\d{6}$/.test(d)) { return false; }
  return d.substr(1, 2) !== '11' && d.substr(4, 2) !== '11';
}
```

```php
<?php
function efbm_us_ssn($v) {
	if (!preg_match('/^\d{9}$/', $v)) {
		return false;
	}
	$area = substr($v, 0, 3);
	if ($area === '000' || $area === '666' || $area[0] === '9') {
		return false;
	}
	return substr($v, 3, 2) !== '00' && substr($v, 5, 4) !== '0000';
}

function efbm_us_ein($v) {
	static $prefixes = '01 02 03 04 05 06 10 11 12 13 14 15 16 20 21 22 23 24 25 26 27 30 31 32 33 34 35 36 37 38 39 40 41 42 43 44 45 46 47 48 50 51 52 53 54 55 56 57 58 59 60 61 62 63 64 65 66 67 68 71 72 73 74 75 76 77 80 81 82 83 84 85 86 87 88 90 91 92 93 94 95 98 99';
	return (bool) preg_match('/^\d{9}$/', $v) && in_array(substr($v, 0, 2), explode(' ', $prefixes), true);
}

function efbm_nanp($v) {
	$d = preg_replace('/^\+?1(?=\d{10}$)/', '', $v);
	if (!preg_match('/^[2-9]\d{2}[2-9]\d{6}$/', $d)) {
		return false;
	}
	return substr($d, 1, 2) !== '11' && substr($d, 4, 2) !== '11';
}
```

### 7.13 Card brand detection, length and grouping

```
first matching prefix wins (order matters: Mir 2200–2204 before Mastercard 2221–2720, Maestro after Mastercard):
  amex 34,37 (15, 4-6-5) · diners 300–305,3095,36,38,39 (14–19, 4-6-4) · jcb 3528–3589 · mir 2200–2204
  mastercard 51–55, 2221–2720 (16) · discover 6011, 644–649, 65 · unionpay 62 · maestro 50,56–59,63,67 · visa 4 (13,16,19)
card valid = 12–19 digits AND luhn AND (unknown brand OR length in brand.lengths)
```

Iranian Shetab cards (e.g. 6037 99…) are not international brands: `cardBrand()` returns `null`, so use the Iranian
BIN table in `ir.bank_card.metadata.bins` to show the bank name instead.

```js
// Ordered: first match wins. lengths = allowed PAN lengths; groups = display grouping.
var CARD_BRANDS = [
  { brand: 'amex', re: /^3[47]/, lengths: [15], groups: [4, 6, 5], cvc: 4 },
  { brand: 'diners', re: /^3(?:0[0-5]|095|[689])/, lengths: [14, 15, 16, 17, 18, 19], groups: [4, 6, 4], cvc: 3 },
  { brand: 'jcb', re: /^35(?:2[89]|[3-8]\d)/, lengths: [16, 17, 18, 19], groups: [4, 4, 4, 4, 3], cvc: 3 },
  { brand: 'mir', re: /^220[0-4]/, lengths: [16, 17, 18, 19], groups: [4, 4, 4, 4, 3], cvc: 3 },
  { brand: 'mastercard', re: /^(?:5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d\d|27[01]\d|2720)/, lengths: [16], groups: [4, 4, 4, 4], cvc: 3 },
  { brand: 'discover', re: /^(?:6011|64[4-9]|65)/, lengths: [16, 17, 18, 19], groups: [4, 4, 4, 4, 3], cvc: 3 },
  { brand: 'unionpay', re: /^62/, lengths: [16, 17, 18, 19], groups: [4, 4, 4, 4, 3], cvc: 3 },
  { brand: 'maestro', re: /^(?:5[06-9]|6[37])/, lengths: [12, 13, 14, 15, 16, 17, 18, 19], groups: [4, 4, 4, 4, 3], cvc: 3 },
  { brand: 'visa', re: /^4/, lengths: [13, 16, 19], groups: [4, 4, 4, 4, 3], cvc: 3 }
];
function cardBrand(pan) {
  for (var i = 0; i < CARD_BRANDS.length; i++) { if (CARD_BRANDS[i].re.test(pan)) { return CARD_BRANDS[i]; } }
  return null;
}
// Iranian Shetab card: 16 digits + Luhn. Brand = issuing bank by 6-digit BIN (optional metadata in the catalog).
function card(pan) {
  if (!/^\d{12,19}$/.test(pan) || !luhn(pan)) { return false; }
  var b = cardBrand(pan);
  return b ? b.lengths.indexOf(pan.length) !== -1 : (pan.length >= 12 && pan.length <= 19);
}
function formatCard(pan) {
  var b = cardBrand(pan), groups = b ? b.groups : [4, 4, 4, 4, 3], out = [], pos = 0;
  for (var i = 0; i < groups.length && pos < pan.length; i++) { out.push(pan.substr(pos, groups[i])); pos += groups[i]; }
  if (pos < pan.length) { out.push(pan.substr(pos)); }
  return out.join(' ');
}
```

```php
<?php
function efbm_card_brands() {
	return array(
		array('brand' => 'amex', 're' => '/^3[47]/', 'lengths' => array(15), 'groups' => array(4, 6, 5), 'cvc' => 4),
		array('brand' => 'diners', 're' => '/^3(?:0[0-5]|095|[689])/', 'lengths' => array(14, 15, 16, 17, 18, 19), 'groups' => array(4, 6, 4), 'cvc' => 3),
		array('brand' => 'jcb', 're' => '/^35(?:2[89]|[3-8]\d)/', 'lengths' => array(16, 17, 18, 19), 'groups' => array(4, 4, 4, 4, 3), 'cvc' => 3),
		array('brand' => 'mir', 're' => '/^220[0-4]/', 'lengths' => array(16, 17, 18, 19), 'groups' => array(4, 4, 4, 4, 3), 'cvc' => 3),
		array('brand' => 'mastercard', 're' => '/^(?:5[1-5]|222[1-9]|22[3-9]\d|2[3-6]\d\d|27[01]\d|2720)/', 'lengths' => array(16), 'groups' => array(4, 4, 4, 4), 'cvc' => 3),
		array('brand' => 'discover', 're' => '/^(?:6011|64[4-9]|65)/', 'lengths' => array(16, 17, 18, 19), 'groups' => array(4, 4, 4, 4, 3), 'cvc' => 3),
		array('brand' => 'unionpay', 're' => '/^62/', 'lengths' => array(16, 17, 18, 19), 'groups' => array(4, 4, 4, 4, 3), 'cvc' => 3),
		array('brand' => 'maestro', 're' => '/^(?:5[06-9]|6[37])/', 'lengths' => array(12, 13, 14, 15, 16, 17, 18, 19), 'groups' => array(4, 4, 4, 4, 3), 'cvc' => 3),
		array('brand' => 'visa', 're' => '/^4/', 'lengths' => array(13, 16, 19), 'groups' => array(4, 4, 4, 4, 3), 'cvc' => 3),
	);
}

function efbm_card_brand_info($pan) {
	foreach (efbm_card_brands() as $b) {
		if (preg_match($b['re'], $pan)) {
			return $b;
		}
	}
	return null;
}

function efbm_card_brand($pan) {
	$b = efbm_card_brand_info($pan);
	return $b ? $b['brand'] : null;
}

function efbm_card($pan) {
	if (!preg_match('/^\d{12,19}$/', $pan) || !efbm_luhn($pan)) {
		return false;
	}
	$b = efbm_card_brand_info($pan);
	return $b ? in_array(strlen($pan), $b['lengths'], true) : true;
}

function efbm_format_card($pan) {
	$b = efbm_card_brand_info($pan);
	$groups = $b ? $b['groups'] : array(4, 4, 4, 4, 3);
	$out = array();
	$pos = 0;
	$len = strlen($pan);
	foreach ($groups as $g) {
		if ($pos >= $len) {
			break;
		}
		$out[] = substr($pan, $pos, $g);
		$pos += $g;
	}
	if ($pos < $len) {
		$out[] = substr($pan, $pos);
	}
	return implode(' ', $out);
}
```

### 7.14 Jalali calendar and date rules

```
Jalali month length: m 1–6 → 31; m 7–11 → 30; m 12 → leap(y) ? 30 : 29
leap(y): Borkowski break table (jalaali-js): find the break interval [jp, jm) containing y, jump = jm - jp,
         n = y - jp; if jump - n < 6: n = n - jump + floor((jump+4)/33)*33
         leap = ((n+1) mod 33 - 1) mod 4  (truncating mod); leap == 0 → leap year   (1399, 1403, 1408 …)
Hijri (Umm al-Qura): month length 29/30 is table-driven → validator only enforces 1..30
date(v, mask): split on non-digits into 3 parts, order by date_order (YMD|DMY|MDY), check year_range, month 1–12,
               then day limit by calendar
card_expiry: MM/YY, MM 01–12, optional "not expired" when a `now` is passed
```

```js
// Jalali (Solar Hijri) leap years from the Borkowski break table (same as jalaali-js); valid for 1-3177 AP.
var JALALI_BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324,
  2394, 2456, 3178];
function jalaliIsLeap(jy) {
  var jp = JALALI_BREAKS[0], jump = 0;
  for (var i = 1; i < JALALI_BREAKS.length; i++) {
    var jm = JALALI_BREAKS[i];
    jump = jm - jp;
    if (jy < jm) { break; }
    jp = jm;
  }
  var n = jy - jp;
  if (jump - n < 6) { n = n - jump + Math.floor((jump + 4) / 33) * 33; }
  var leap = (((n + 1) % 33) - 1) % 4;   // JS % truncates like the original
  if (leap === -1) { leap = 4; }
  return leap === 0;
}
function jalaliMonthLength(y, m) {
  if (m <= 6) { return 31; }
  if (m <= 11) { return 30; }
  return jalaliIsLeap(y) ? 30 : 29;
}
```

```php
<?php
function efbm_jalali_is_leap($jy) {
	static $breaks = array(-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262,
		2324, 2394, 2456, 3178);
	$jp = $breaks[0];
	$jump = 0;
	$count = count($breaks);
	for ($i = 1; $i < $count; $i++) {
		$jm = $breaks[$i];
		$jump = $jm - $jp;
		if ($jy < $jm) {
			break;
		}
		$jp = $jm;
	}
	$n = $jy - $jp;
	if ($jump - $n < 6) {
		$n = $n - $jump + intdiv($jump + 4, 33) * 33;
	}
	$leap = ((($n + 1) % 33) - 1) % 4; // PHP % truncates like JS
	if ($leap === -1) {
		$leap = 4;
	}
	return $leap === 0;
}

function efbm_jalali_month_length($y, $m) {
	if ($m <= 6) {
		return 31;
	}
	if ($m <= 11) {
		return 30;
	}
	return efbm_jalali_is_leap($y) ? 30 : 29;
}
```

```js
// Splits on any non-digit, orders by mask.date_order (YMD | DMY | MDY) and checks per calendar.
function dateParts(v, order) {
  var p = v.split(/\D+/);
  if (p.length !== 3) { return null; }
  var y = parseInt(p[order.indexOf('Y')], 10), m = parseInt(p[order.indexOf('M')], 10), d = parseInt(p[order.indexOf('D')], 10);
  return { y: y, m: m, d: d };
}
function validDate(v, mask) {
  var o = dateParts(v, mask.date_order || 'YMD');
  if (!o) { return false; }
  var yr = mask.year_range || [1, 9999];
  if (o.y < yr[0] || o.y > yr[1] || o.m < 1 || o.m > 12 || o.d < 1) { return false; }
  if (mask.calendar === 'jalali') { return o.d <= jalaliMonthLength(o.y, o.m); }
  if (mask.calendar === 'hijri') { return o.d <= 30; } // Umm al-Qura month = 29 or 30 days (table-driven)
  return isValidGregorian(o.y, o.m, o.d);
}
// MM/YY. Pass `now` (Date) to also reject expired cards; without it only the structure is checked.
function cardExpiry(v, mask, now) {
  var m = /^(\d{2})\/(\d{2})$/.exec(v);
  if (!m) { return false; }
  var mm = parseInt(m[1], 10), yy = 2000 + parseInt(m[2], 10);
  if (mm < 1 || mm > 12) { return false; }
  if (now) { return yy > now.getFullYear() || (yy === now.getFullYear() && mm >= now.getMonth() + 1); }
  return true;
}
```

```php
<?php
function efbm_valid_date($v, $mask) {
	$p = preg_split('/\D+/', $v);
	if (count($p) !== 3) {
		return false;
	}
	$order = isset($mask['date_order']) ? $mask['date_order'] : 'YMD';
	$y = (int) $p[strpos($order, 'Y')];
	$m = (int) $p[strpos($order, 'M')];
	$d = (int) $p[strpos($order, 'D')];
	$yr = isset($mask['year_range']) ? $mask['year_range'] : array(1, 9999);
	if ($y < $yr[0] || $y > $yr[1] || $m < 1 || $m > 12 || $d < 1) {
		return false;
	}
	$cal = isset($mask['calendar']) ? $mask['calendar'] : 'gregorian';
	if ($cal === 'jalali') {
		return $d <= efbm_jalali_month_length($y, $m);
	}
	if ($cal === 'hijri') {
		return $d <= 30;
	}
	return efbm_is_valid_gregorian($y, $m, $d);
}

/** MM/YY; pass $now (DateTimeInterface) to also reject expired cards. */
function efbm_card_expiry($v, $mask = array(), $now = null) {
	if (!preg_match('/^(\d{2})\/(\d{2})$/', $v, $m)) {
		return false;
	}
	$mm = (int) $m[1];
	$yy = 2000 + (int) $m[2];
	if ($mm < 1 || $mm > 12) {
		return false;
	}
	if ($now) {
		$ny = (int) $now->format('Y');
		return $yy > $ny || ($yy === $ny && $mm >= (int) $now->format('n'));
	}
	return true;
}
```

## 8. Mask engine, normalizer, dispatcher and storage (reference code)

### 8.1 Normalizer

```js
// Characters removed before anything else (zero-width, bidi controls, BOM, soft hyphen).
var INVISIBLE_RE = /[​-‏‪-‮⁦-⁩﻿­]/g;

function normalizeDigits(s) {
  return s.replace(/[۰-۹٠-٩０-９]/g, function (ch) {
    var c = ch.charCodeAt(0);
    if (c >= 0xFF10) { return String.fromCharCode(c - 0xFF10 + 48); }   // full-width
    if (c >= 0x06F0) { return String.fromCharCode(c - 0x06F0 + 48); }   // Persian (Extended Arabic-Indic)
    return String.fromCharCode(c - 0x0660 + 48);                          // Arabic-Indic
  });
}

function toAsciiUpper(s) {
  return s.replace(/[a-z]/g, function (c) { return String.fromCharCode(c.charCodeAt(0) - 32); });
}

/**
 * normalize(raw, mask) -> canonical string used for regex/checksum checks.
 * mask fields used: fold_letters ('fa'), uppercase (bool), strip (string of chars), pad_left ({to, min}).
 */
function normalize(raw, mask) {
  mask = mask || {};
  var s = String(raw === null || raw === undefined ? '' : raw);
  s = normalizeDigits(s);
  s = s.replace(INVISIBLE_RE, '');
  s = s.replace(/[   　]/g, ' ');                // no-break / figure / narrow / ideographic space
  s = s.replace(/٫/g, '.');                                      // ARABIC DECIMAL SEPARATOR
  s = s.replace(/[٬،]/g, ',');                              // ARABIC THOUSANDS SEPARATOR, ARABIC COMMA
  s = s.replace(/[‐-―−﹘﹣－]/g, '-');    // dash / minus variants
  s = s.replace(/＋/g, '+').replace(/／/g, '/').replace(/：/g, ':');
  if (mask.fold_letters === 'fa') {
    s = s.replace(/[يى]/g, 'ی').replace(/ك/g, 'ک'); // ي ى -> ی , ك -> ک
  }
  s = s.replace(/[\t\n\v\f\r ]+/g, ' ').trim();
  if (mask.uppercase) { s = toAsciiUpper(s); }
  if (mask.strip) {
    var out = '';
    for (var i = 0; i < s.length; i++) { if (mask.strip.indexOf(s.charAt(i)) === -1) { out += s.charAt(i); } }
    s = out;
  }
  if (mask.pad_left && /^\d+$/.test(s) && s.length >= mask.pad_left.min && s.length < mask.pad_left.to) {
    while (s.length < mask.pad_left.to) { s = '0' + s; }
  }
  return s;
}
```

```php
<?php
function efbm_normalize_digits($s) {
	$map = array();
	for ($i = 0; $i < 10; $i++) {
		$map[efbm_chr(0x06F0 + $i)] = (string) $i; // Persian
		$map[efbm_chr(0x0660 + $i)] = (string) $i; // Arabic-Indic
		$map[efbm_chr(0xFF10 + $i)] = (string) $i; // full-width
	}
	return strtr($s, $map);
}

function efbm_chr($cp) {
	return html_entity_decode('&#' . $cp . ';', ENT_QUOTES, 'UTF-8');
}

function efbm_ascii_upper($s) {
	return strtr($s, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
}

/** efbm_normalize($raw, $mask) -> canonical string (see efb-masks.js normalize()). $mask is an assoc array. */
function efbm_normalize($raw, $mask = array()) {
	$s = (string) ($raw === null ? '' : $raw);
	$s = efbm_normalize_digits($s);
	$s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{00AD}]/u', '', $s);
	$s = preg_replace('/[\x{00A0}\x{2007}\x{202F}\x{3000}]/u', ' ', $s);
	$s = preg_replace('/\x{066B}/u', '.', $s);
	$s = preg_replace('/[\x{066C}\x{060C}]/u', ',', $s);
	$s = preg_replace('/[\x{2010}-\x{2015}\x{2212}\x{FE58}\x{FE63}\x{FF0D}]/u', '-', $s);
	$s = preg_replace(array('/\x{FF0B}/u', '/\x{FF0F}/u', '/\x{FF1A}/u'), array('+', '/', ':'), $s);
	if (isset($mask['fold_letters']) && $mask['fold_letters'] === 'fa') {
		$s = preg_replace(array('/[\x{064A}\x{0649}]/u', '/\x{0643}/u'), array("\u{06CC}", "\u{06A9}"), $s);
	}
	$s = trim(preg_replace('/[\t\n\x0B\f\r ]+/', ' ', $s));
	if (!empty($mask['uppercase'])) {
		$s = efbm_ascii_upper($s);
	}
	if (!empty($mask['strip'])) {
		$chars = preg_split('//u', $mask['strip'], -1, PREG_SPLIT_NO_EMPTY);
		$s = str_replace($chars, '', $s);
	}
	if (!empty($mask['pad_left']) && preg_match('/^\d+$/', $s)
		&& strlen($s) >= $mask['pad_left']['min'] && strlen($s) < $mask['pad_left']['to']) {
		$s = str_pad($s, $mask['pad_left']['to'], '0', STR_PAD_LEFT);
	}
	return $s;
}
```

### 8.2 Dispatcher — `validate(mask, raw)`

```js
var CHECKSUMS = {
  luhn: luhn, verhoeff: verhoeff, iso7064_mod97_10: iban, iran_national_code: iranNationalCode,
  iran_legal_id: iranLegalId, saudi_nin: saudiNin, emirates_id: emiratesId, kuwait_civil_id: kuwaitCivilId,
  au_abn: auAbn, card: card
};
var VALIDATORS = {
  date: validDate, card_expiry: cardExpiry, egypt_nid: egyptNid, uk_nino: ukNino, us_ssn: usSsn,
  us_ein: usEin, nanp: nanp
};
var regexCache = {};
function compiled(src) { return regexCache[src] || (regexCache[src] = new RegExp(src, 'u')); }

/** validate(mask, raw) -> { ok, code: null|'empty'|'format'|'checksum'|'rule', value } */
function validate(mask, raw) {
  var v = normalize(raw, mask);
  if (v === '') { return { ok: false, code: 'empty', value: v }; }
  if (!compiled(mask.regex).test(v)) { return { ok: false, code: 'format', value: v }; }
  if (mask.checksum) {
    var fn = CHECKSUMS[mask.checksum];
    if (!fn) { throw new Error('Unknown checksum ' + mask.checksum); }
    if (!fn(v)) { return { ok: false, code: 'checksum', value: v }; }
  }
  if (mask.validator) {
    var vf = VALIDATORS[mask.validator];
    if (!vf) { throw new Error('Unknown validator ' + mask.validator); }
    if (!vf(v, mask)) { return { ok: false, code: 'rule', value: v }; }
  }
  return { ok: true, code: null, value: v };
}
```

```php
<?php
function efbm_validate($mask, $raw) {
	static $checksums = array(
		'luhn' => 'efbm_luhn', 'verhoeff' => 'efbm_verhoeff', 'iso7064_mod97_10' => 'efbm_iban',
		'iran_national_code' => 'efbm_iran_national_code', 'iran_legal_id' => 'efbm_iran_legal_id',
		'saudi_nin' => 'efbm_saudi_nin', 'emirates_id' => 'efbm_emirates_id',
		'kuwait_civil_id' => 'efbm_kuwait_civil_id', 'au_abn' => 'efbm_au_abn', 'card' => 'efbm_card',
	);
	static $validators = array(
		'date' => 'efbm_valid_date', 'card_expiry' => 'efbm_card_expiry', 'egypt_nid' => 'efbm_egypt_nid',
		'uk_nino' => 'efbm_uk_nino', 'us_ssn' => 'efbm_us_ssn', 'us_ein' => 'efbm_us_ein', 'nanp' => 'efbm_nanp',
	);
	$v = efbm_normalize($raw, $mask);
	if ($v === '') {
		return array('ok' => false, 'code' => 'empty', 'value' => $v);
	}
	// Catalog regexes never contain "~"; /u makes . and classes code-point aware like the JS "u" flag.
	if (!preg_match('~' . $mask['regex'] . '~u', $v)) {
		return array('ok' => false, 'code' => 'format', 'value' => $v);
	}
	if (!empty($mask['checksum'])) {
		if (!isset($checksums[$mask['checksum']])) {
			throw new InvalidArgumentException('Unknown checksum ' . $mask['checksum']);
		}
		if (!call_user_func($checksums[$mask['checksum']], $v)) {
			return array('ok' => false, 'code' => 'checksum', 'value' => $v);
		}
	}
	if (!empty($mask['validator'])) {
		if (!isset($validators[$mask['validator']])) {
			throw new InvalidArgumentException('Unknown validator ' . $mask['validator']);
		}
		if (!call_user_func($validators[$mask['validator']], $v, $mask)) {
			return array('ok' => false, 'code' => 'rule', 'value' => $v);
		}
	}
	return array('ok' => true, 'code' => null, 'value' => $v);
}
```

### 8.3 Stored value — `toStored(mask, value)`

E.164 conversion order: keep a leading `+`; `00…` → `+…`; leading trunk prefix → `+cc` + rest; length equal to the
national significant number length → `+cc` + value; starts with the country code → `+` + value.

```js
/** toStored(mask, normalizedValidValue): the canonical value to save in the database. */
function toStored(mask, v) {
  if (mask.store === 'e164' && mask.e164) {
    var cc = mask.e164.cc, trunk = mask.e164.trunk || '', nsn = mask.e164.nsn_length || [];
    if (v.charAt(0) === '+') { return v; }
    if (v.substr(0, 2) === '00') { return '+' + v.substr(2); }
    if (trunk && v.substr(0, trunk.length) === trunk) { return '+' + cc + v.substr(trunk.length); }
    if (nsn.indexOf(v.length) !== -1) { return '+' + cc + v; }
    if (v.substr(0, cc.length) === cc) { return '+' + v; }
    return v;
  }
  if (mask.store === 'digits') { return v.replace(/\D+/g, ''); }
  if (mask.store === 'strip_grouping') { return v.replace(/,/g, ''); }
  return v;
}
```

```php
<?php
function efbm_to_stored($mask, $v) {
	$store = isset($mask['store']) ? $mask['store'] : null;
	if ($store === 'e164' && !empty($mask['e164'])) {
		$cc = $mask['e164']['cc'];
		$trunk = isset($mask['e164']['trunk']) ? $mask['e164']['trunk'] : '';
		$nsn = isset($mask['e164']['nsn_length']) ? $mask['e164']['nsn_length'] : array();
		if (substr($v, 0, 1) === '+') {
			return $v;
		}
		if (substr($v, 0, 2) === '00') {
			return '+' . substr($v, 2);
		}
		if ($trunk !== '' && substr($v, 0, strlen($trunk)) === $trunk) {
			return '+' . $cc . substr($v, strlen($trunk));
		}
		if (in_array(strlen($v), $nsn, true)) {
			return '+' . $cc . $v;
		}
		if (substr($v, 0, strlen($cc)) === $cc) {
			return '+' . $v;
		}
		return $v;
	}
	if ($store === 'digits') {
		return preg_replace('/\D+/', '', $v);
	}
	if ($store === 'strip_grouping') {
		return str_replace(',', '', $v);
	}
	return $v;
}
```

### 8.4 Mask engine and formatters

```js
/*
 * Token language: 9 digit | a letter (ASCII or Arabic-script) | * letter or digit
 *                 [ ... ] optional part | {n} / {n,m} repeat previous token | \x literal x | anything else literal
 */
var LETTER_RE = /^[A-Za-zء-غف-يٮ-ۓۺ-ۼ]$/;
var DIGIT_RE = /^[0-9]$/;
function compileMask(pattern) {
  var tokens = [], optional = 0, i = 0;
  while (i < pattern.length) {
    var ch = pattern.charAt(i);
    if (ch === '\\' && i + 1 < pattern.length) { tokens.push({ k: 'lit', ch: pattern.charAt(i + 1), opt: optional > 0 }); i += 2; continue; }
    if (ch === '[') { optional++; i++; continue; }
    if (ch === ']') { optional = Math.max(0, optional - 1); i++; continue; }
    if (ch === '{') {
      var close = pattern.indexOf('}', i), spec = pattern.substring(i + 1, close).split(',');
      var min = parseInt(spec[0], 10), max = spec.length > 1 ? parseInt(spec[1], 10) : min, prev = tokens.pop();
      for (var r = 0; r < max; r++) { tokens.push({ k: prev.k, ch: prev.ch, opt: prev.opt || r >= min }); }
      i = close + 1; continue;
    }
    if (ch === '9') { tokens.push({ k: 'digit', opt: optional > 0 }); } else if (ch === 'a') { tokens.push({ k: 'letter', opt: optional > 0 }); } else if (ch === '*') { tokens.push({ k: 'alnum', opt: optional > 0 }); } else { tokens.push({ k: 'lit', ch: ch, opt: optional > 0 }); }
    i++;
  }
  return tokens;
}
function slotAccepts(k, c) {
  if (k === 'digit') { return DIGIT_RE.test(c); }
  if (k === 'letter') { return LETTER_RE.test(c); }
  return DIGIT_RE.test(c) || LETTER_RE.test(c);
}
function isDataChar(c) { return DIGIT_RE.test(c) || LETTER_RE.test(c); }
/**
 * applyMask(pattern, input) -> formatted string. Input is normalized first (digits -> ASCII).
 * Literals are written lazily: only when a following slot is filled (keeps Backspace natural).
 * A data char that fits no slot is dropped; an optional slot that does not fit is skipped.
 */
function applyMask(pattern, input) {
  var tokens = compileMask(pattern), s = Array.from(normalize(input, {})), out = '', pending = [], i = 0;
  for (var t = 0; t < tokens.length && i < s.length; t++) {
    var tok = tokens[t];
    if (tok.k === 'lit') {
      pending.push(tok);
      if (s[i] === tok.ch) { i++; }
      continue;
    }
    while (i < s.length && !isDataChar(s[i])) { i++; }
    if (i >= s.length) { break; }
    if (slotAccepts(tok.k, s[i])) {
      for (var p = 0; p < pending.length; p++) { out += pending[p].ch; }
      pending = []; out += s[i]; i++;
    } else if (tok.opt) {
      pending = pending.filter(function (x) { return !x.opt; });
    } else {
      i++; t--;                                   // drop the char, retry the same slot
    }
  }
  return out;
}
/** maxLength of a pattern (all optional parts filled) - use for the input's maxlength attribute. */
function maskMaxLength(pattern) { return compileMask(pattern).length; }

/** formatThousands('-1234567.5') -> '-1,234,567.5' (sep defaults to ','; display may swap in U+066C). */
function formatThousands(v, sep) {
  sep = sep || ',';
  var s = normalize(v, {}).replace(/[^0-9.\-]/g, '');
  var neg = s.charAt(0) === '-' ? '-' : '';
  s = s.replace(/-/g, '');
  var dot = s.indexOf('.'), intPart = dot === -1 ? s : s.substr(0, dot), dec = dot === -1 ? '' : s.substr(dot).replace(/\./g, function (m, off) { return off === 0 ? '.' : ''; });
  intPart = intPart.replace(/^0+(?=\d)/, '');
  var out = '';
  for (var i = intPart.length; i > 0; i -= 3) { out = intPart.substring(Math.max(0, i - 3), i) + (out ? sep + out : ''); }
  return neg + out + dec;
}
/** formatValue(mask, input): the one entry point the front-end calls while typing / on blur. */
function formatValue(mask, input) {
  var s = mask.uppercase ? toAsciiUpper(normalize(input, {})) : input;
  if (mask.formatter === 'thousands') { return formatThousands(s); }
  if (mask.formatter === 'card') { return formatCard(normalize(s, { strip: ' -' }).replace(/\D/g, '').substr(0, 19)); }
  if (mask.mask) { return applyMask(mask.mask, s); }
  return normalize(s, {});
}
```

```php
<?php
function efbm_compile_mask($pattern) {
	$chars = preg_split('//u', $pattern, -1, PREG_SPLIT_NO_EMPTY);
	$tokens = array();
	$optional = 0;
	$n = count($chars);
	for ($i = 0; $i < $n; $i++) {
		$ch = $chars[$i];
		if ($ch === '\\' && $i + 1 < $n) {
			$tokens[] = array('k' => 'lit', 'ch' => $chars[++$i], 'opt' => $optional > 0);
			continue;
		}
		if ($ch === '[') {
			$optional++;
			continue;
		}
		if ($ch === ']') {
			$optional = max(0, $optional - 1);
			continue;
		}
		if ($ch === '{') {
			$spec = '';
			while (++$i < $n && $chars[$i] !== '}') {
				$spec .= $chars[$i];
			}
			$parts = explode(',', $spec);
			$min = (int) $parts[0];
			$max = count($parts) > 1 ? (int) $parts[1] : $min;
			$prev = array_pop($tokens);
			for ($r = 0; $r < $max; $r++) {
				$tokens[] = array('k' => $prev['k'], 'ch' => $prev['ch'], 'opt' => $prev['opt'] || $r >= $min);
			}
			continue;
		}
		$kinds = array('9' => 'digit', 'a' => 'letter', '*' => 'alnum');
		$tokens[] = isset($kinds[$ch])
			? array('k' => $kinds[$ch], 'ch' => null, 'opt' => $optional > 0)
			: array('k' => 'lit', 'ch' => $ch, 'opt' => $optional > 0);
	}
	return $tokens;
}

function efbm_is_letter($c) {
	return (bool) preg_match('/^[A-Za-z\x{0621}-\x{063A}\x{0641}-\x{064A}\x{066E}-\x{06D3}\x{06FA}-\x{06FC}]$/u', $c);
}

function efbm_slot_accepts($k, $c) {
	$digit = (bool) preg_match('/^[0-9]$/', $c);
	if ($k === 'digit') {
		return $digit;
	}
	if ($k === 'letter') {
		return efbm_is_letter($c);
	}
	return $digit || efbm_is_letter($c);
}

function efbm_apply_mask($pattern, $input) {
	$tokens = efbm_compile_mask($pattern);
	$s = preg_split('//u', efbm_normalize($input, array()), -1, PREG_SPLIT_NO_EMPTY);
	$out = '';
	$pending = array();
	$i = 0;
	$ns = count($s);
	$nt = count($tokens);
	for ($t = 0; $t < $nt && $i < $ns; $t++) {
		$tok = $tokens[$t];
		if ($tok['k'] === 'lit') {
			$pending[] = $tok;
			if ($s[$i] === $tok['ch']) {
				$i++;
			}
			continue;
		}
		while ($i < $ns && !efbm_slot_accepts('alnum', $s[$i])) {
			$i++;
		}
		if ($i >= $ns) {
			break;
		}
		if (efbm_slot_accepts($tok['k'], $s[$i])) {
			foreach ($pending as $p) {
				$out .= $p['ch'];
			}
			$pending = array();
			$out .= $s[$i];
			$i++;
		} elseif ($tok['opt']) {
			$pending = array_values(array_filter($pending, function ($x) {
				return !$x['opt'];
			}));
		} else {
			$i++;
			$t--;
		}
	}
	return $out;
}

function efbm_format_thousands($v, $sep = ',') {
	$s = preg_replace('/[^0-9.\-]/', '', efbm_normalize($v, array()));
	$neg = substr($s, 0, 1) === '-' ? '-' : '';
	$s = str_replace('-', '', $s);
	$dot = strpos($s, '.');
	$int = $dot === false ? $s : substr($s, 0, $dot);
	$dec = $dot === false ? '' : '.' . str_replace('.', '', substr($s, $dot + 1));
	$int = preg_replace('/^0+(?=\d)/', '', $int);
	$out = '';
	for ($i = strlen($int); $i > 0; $i -= 3) {
		$start = max(0, $i - 3);
		$out = substr($int, $start, $i - $start) . ($out !== '' ? $sep . $out : '');
	}
	return $neg . $out . $dec;
}

function efbm_format_value($mask, $input) {
	$s = !empty($mask['uppercase']) ? efbm_ascii_upper(efbm_normalize($input, array())) : $input;
	$formatter = isset($mask['formatter']) ? $mask['formatter'] : null;
	if ($formatter === 'thousands') {
		return efbm_format_thousands($s);
	}
	if ($formatter === 'card') {
		return efbm_format_card(substr(preg_replace('/\D/', '', efbm_normalize($s, array('strip' => ' -'))), 0, 19));
	}
	if (!empty($mask['mask'])) {
		return efbm_apply_mask($mask['mask'], $s);
	}
	return efbm_normalize($s, array());
}
```

### 8.5 DOM binding (browser only)

```js
/**
 * attachMask(input, pattern): formats on every 'input' event, keeps the caret after the same number of
 * data characters, waits for IME composition to finish, and sets inputmode/dir if missing.
 */
function countData(str, end) {
  var n = 0, a = Array.from(str.slice(0, end));
  for (var i = 0; i < a.length; i++) { if (isDataChar(normalizeDigits(a[i]))) { n++; } }
  return n;
}
function caretForDataIndex(formatted, n) {
  if (n === 0) { return 0; }
  var seen = 0;
  for (var i = 0; i < formatted.length; i++) {
    if (isDataChar(formatted.charAt(i))) { seen++; if (seen === n) { return i + 1; } }
  }
  return formatted.length;
}
function attachMask(input, pattern, opts) {
  opts = opts || {};
  var composing = false;
  if (!input.getAttribute('inputmode') && opts.inputmode) { input.setAttribute('inputmode', opts.inputmode); }
  if (!input.getAttribute('dir')) { input.setAttribute('dir', opts.dir || 'ltr'); }
  input.setAttribute('maxlength', String(maskMaxLength(pattern)));
  function run() {
    var before = input.value, caret = input.selectionStart === null ? before.length : input.selectionStart;
    var dataBeforeCaret = countData(before, caret);
    var after = applyMask(pattern, before);
    if (after !== before) {
      input.value = after;
      var pos = caretForDataIndex(after, dataBeforeCaret);
      if (typeof input.setSelectionRange === 'function' && input === (input.ownerDocument || {}).activeElement) {
        input.setSelectionRange(pos, pos);
      }
    }
  }
  input.addEventListener('compositionstart', function () { composing = true; });
  input.addEventListener('compositionend', function () { composing = false; run(); });
  input.addEventListener('input', function () { if (!composing) { run(); } });
  input.addEventListener('blur', run);
  return { format: run };
}
```

Usage:

```js
// catalog entry loaded once (e.g. wp_localize_script / fetch of masks-catalog.json, cached)
var m = catalog.masks.find(function (x) { return x.id === 'ir.mobile'; });
var el = document.getElementById('efb-field-123');
EfbMasks.attachMask(el, m.mask, { inputmode: m.inputmode, dir: m.dir });
el.addEventListener('blur', function () {
  var r = EfbMasks.validate(m, el.value);
  // r.code: null | 'empty' | 'format' | 'checksum' | 'rule' → message from catalog.error_messages
});
```

## 9. Recommended UX

### 9.1 Presets in the builder

A "Format / Mask" select on single-line text and tel fields, with an `<optgroup>` per country, the site's own
locale first (fa_IR → Iran, ar → the Arab countries, en_US → United States …), then "International". Each option
shows `label[admin locale]` and the placeholder. Selecting a preset fills placeholder, `inputmode`, `autocomplete`,
`maxlength` and enables the validator. Grouped presets in this catalog:

| Group | id | Label (en / fa / ar) | Mask | Checksum / rule | Verif. |
|---|---|---|---|---|---|
| Iran | `ir.mobile` | Iran mobile number / شماره موبایل / رقم الجوال الإيراني | `0\999 999 9999` | — | secondary |
| Iran | `ir.landline` | Iran landline (with area code) / تلفن ثابت با کد شهر / هاتف أرضي إيراني مع رمز المنطقة | `099 9999 9999` | — | secondary |
| Iran | `ir.national_id` | Iranian national code / کد ملی / الرقم الوطني الإيراني | `999-999999-9` | iran_national_code | secondary |
| Iran | `ir.legal_id` | Iranian legal-entity national ID / شناسه ملی اشخاص حقوقی / المعرّف الوطني للأشخاص الاعتباريين (إيران) | `99999999999` | iran_legal_id | unverified |
| Iran | `ir.economic_code` | Iranian economic code / کد اقتصادی / الرمز الاقتصادي (إيران) | `999999999999` | — | unverified |
| Iran | `ir.postal_code` | Iranian postal code / کد پستی / الرمز البريدي الإيراني | `99999-99999` | — | unverified |
| Iran | `ir.bank_card` | Iranian bank card (Shetab) / شماره کارت بانکی / رقم البطاقة المصرفية الإيرانية | `9999 9999 9999 9999` | luhn | secondary |
| Iran | `ir.sheba` | Sheba (Iranian IBAN) / شماره شبا / رقم الشبا (آيبان إيران) | `IR99 9999 9999 9999 9999 9999 99` | iso7064_mod97_10 | secondary |
| Iran | `ir.account_number` | Iranian bank account number / شماره حساب بانکی / رقم الحساب المصرفي (إيران) | — | — | unverified |
| Iran | `ir.vehicle_plate` | Iranian car plate / پلاک خودرو / لوحة السيارة الإيرانية | `99 a 999 - 99` | — | unverified |
| Iran | `ir.passport` | Iranian passport number / شماره گذرنامه / رقم جواز السفر الإيراني | `a99999999` | — | unverified |
| Iran | `ir.date_jalali` | Jalali (Solar Hijri) date / تاریخ شمسی / تاريخ هجري شمسي | `9999/99/99` | date | secondary |
| Iran | `ir.time` | Time (24-hour) / ساعت (۲۴ ساعته) / الوقت (24 ساعة) | `99:99` | — | secondary |
| Iran | `ir.amount_toman` | Amount in Toman / مبلغ (تومان) / المبلغ بالتومان | formatter: thousands | — | secondary |
| Iran | `ir.amount_rial` | Amount in Rial / مبلغ (ریال) / المبلغ بالريال الإيراني | formatter: thousands | — | secondary |
| Saudi Arabia | `sa.mobile` | Saudi mobile number / شماره موبایل عربستان / رقم الجوال السعودي | `059 999 9999` | — | secondary |
| Saudi Arabia | `sa.landline` | Saudi landline / تلفن ثابت عربستان / الهاتف الثابت السعودي | `019 999 9999` | — | unverified |
| Saudi Arabia | `sa.national_id` | Saudi National ID / Iqama / شماره ملی / اقامه عربستان / رقم الهوية الوطنية / الإقامة | `9999999999` | saudi_nin | secondary |
| Saudi Arabia | `sa.cr_number` | Saudi Commercial Registration (CR) number / شماره ثبت تجاری عربستان / رقم السجل التجاري | `9999999999` | — | unverified |
| Saudi Arabia | `sa.vat` | Saudi VAT number / شماره مالیات بر ارزش افزوده عربستان / الرقم الضريبي (ضريبة القيمة المضافة) | `999999999999999` | — | secondary |
| Saudi Arabia | `sa.iban` | Saudi IBAN / شبا عربستان / رقم الآيبان السعودي | `SA99 99** **** **** **** ****` | iso7064_mod97_10 | secondary |
| Saudi Arabia | `sa.postal_code` | Saudi postal code / کد پستی عربستان / الرمز البريدي السعودي | `99999[-9999]` | — | secondary |
| Saudi Arabia | `sa.short_address` | Saudi National Address short code / کد آدرس کوتاه عربستان / العنوان المختصر | `aaaa9999` | — | unverified |
| Saudi Arabia | `sa.date_hijri` | Hijri date (Umm al-Qura) / تاریخ هجری قمری / التاريخ الهجري | `9999/99/99` | date | secondary |
| United Arab Emirates | `ae.mobile` | UAE mobile number / شماره موبایل امارات / رقم الجوال الإماراتي | `059 999 9999` | — | secondary |
| United Arab Emirates | `ae.emirates_id` | Emirates ID number / شماره کارت شناسایی امارات / رقم الهوية الإماراتية | `784-9999-9999999-9` | emirates_id | secondary |
| United Arab Emirates | `ae.iban` | UAE IBAN / شبا امارات / رقم الآيبان الإماراتي | `AE99 9999 9999 9999 9999 999` | iso7064_mod97_10 | secondary |
| United Arab Emirates | `ae.trn` | UAE Tax Registration Number (TRN) / شماره ثبت مالیاتی امارات / رقم التسجيل الضريبي | `999-9999-9999-9999` | — | unverified |
| Egypt | `eg.mobile` | Egyptian mobile number / شماره موبایل مصر / رقم المحمول المصري | `019 9999 9999` | — | secondary |
| Egypt | `eg.national_id` | Egyptian national ID / شماره ملی مصر / الرقم القومي المصري | `99999999999999` | egypt_nid | secondary |
| Egypt | `eg.iban` | Egyptian IBAN / شبا مصر / رقم الآيبان المصري | `EG99 9999 9999 9999 9999 9999 9999 9` | iso7064_mod97_10 | secondary |
| Egypt | `eg.postal_code` | Egyptian postal code / کد پستی مصر / الرمز البريدي المصري | `99999` | — | secondary |
| Kuwait | `kw.mobile` | Kuwaiti mobile number / شماره موبایل کویت / رقم الهاتف النقال الكويتي | `9999 9999` | — | secondary |
| Kuwait | `kw.civil_id` | Kuwaiti Civil ID / شماره مدنی کویت / الرقم المدني الكويتي | `999999999999` | kuwait_civil_id | secondary |
| Kuwait | `kw.iban` | Kuwaiti IBAN / شبا کویت / رقم الآيبان الكويتي | `KW99 aaaa **** **** **** **** **** **` | iso7064_mod97_10 | secondary |
| Kuwait | `kw.postal_code` | Kuwaiti postal code / کد پستی کویت / الرمز البريدي الكويتي | `99999` | — | unverified |
| Qatar | `qa.mobile` | Qatari mobile number / شماره موبایل قطر / رقم الجوال القطري | `9999 9999` | — | secondary |
| Qatar | `qa.qid` | Qatar ID (QID) / شماره شناسایی قطر / رقم البطاقة الشخصية القطرية | `99999999999` | — | unverified |
| Qatar | `qa.iban` | Qatari IBAN / شبا قطر / رقم الآيبان القطري | `QA99 aaaa **** **** **** **** **** *` | iso7064_mod97_10 | secondary |
| Bahrain | `bh.mobile` | Bahraini mobile number / شماره موبایل بحرین / رقم الهاتف النقال البحريني | `9999 9999` | — | secondary |
| Bahrain | `bh.cpr` | Bahraini CPR number / شماره CPR بحرین / الرقم الشخصي البحريني (CPR) | `999999999` | — | unverified |
| Bahrain | `bh.iban` | Bahraini IBAN / شبا بحرین / رقم الآيبان البحريني | `BH99 aaaa **** **** **** **` | iso7064_mod97_10 | secondary |
| Oman | `om.mobile` | Omani mobile number / شماره موبایل عمان / رقم الهاتف النقال العماني | `9999 9999` | — | secondary |
| Oman | `om.iban` | Omani IBAN / شبا عمان / رقم الآيبان العماني | `OM99 999* **** **** **** ***` | iso7064_mod97_10 | secondary |
| Oman | `om.postal_code` | Omani postal code / کد پستی عمان / الرمز البريدي العماني | `999` | — | secondary |
| Jordan | `jo.mobile` | Jordanian mobile number / شماره موبایل اردن / رقم الهاتف المتنقل الأردني | `079 999 9999` | — | secondary |
| Jordan | `jo.national_number` | Jordanian national number / شماره ملی اردن / الرقم الوطني الأردني | `9999999999` | — | unverified |
| Jordan | `jo.iban` | Jordanian IBAN / شبا اردن / رقم الآيبان الأردني | `JO99 aaaa 9999 **** **** **** **` | iso7064_mod97_10 | secondary |
| Jordan | `jo.postal_code` | Jordanian postal code / کد پستی اردن / الرمز البريدي الأردني | `99999` | — | secondary |
| Iraq | `iq.mobile` | Iraqi mobile number / شماره موبایل عراق / رقم الهاتف المحمول العراقي | `0799 999 9999` | — | secondary |
| Iraq | `iq.iban` | Iraqi IBAN / شبا عراق / رقم الآيبان العراقي | `IQ99 aaaa 9999 9999 9999 999` | iso7064_mod97_10 | secondary |
| Lebanon | `lb.mobile` | Lebanese mobile number / شماره موبایل لبنان / رقم الخلوي اللبناني | — | — | secondary |
| Lebanon | `lb.iban` | Lebanese IBAN / شبا لبنان / رقم الآيبان اللبناني | `LB99 9999 **** **** **** **** ****` | iso7064_mod97_10 | secondary |
| Morocco | `ma.mobile` | Moroccan mobile number / شماره موبایل مراکش / رقم الهاتف المحمول المغربي | `09 99 99 99 99` | — | secondary |
| Morocco | `ma.cin` | Moroccan CIN (national ID card) / کارت ملی مراکش / رقم البطاقة الوطنية المغربية | — | — | unverified |
| Morocco | `ma.iban` | Moroccan IBAN (RIB) / شبا مراکش / رقم الآيبان المغربي | `MA99 9999 9999 9999 9999 9999 9999` | iso7064_mod97_10 | secondary |
| Morocco | `ma.postal_code` | Moroccan postal code / کد پستی مراکش / الرمز البريدي المغربي | `99999` | — | secondary |
| Algeria | `dz.mobile` | Algerian mobile number / شماره موبایل الجزایر / رقم الهاتف النقال الجزائري | `0999 99 99 99` | — | secondary |
| Algeria | `dz.iban` | Algerian IBAN / شبا الجزایر / رقم الآيبان الجزائري | `DZ99 9999 9999 9999 9999 9999 99` | iso7064_mod97_10 | secondary |
| Algeria | `dz.postal_code` | Algerian postal code / کد پستی الجزایر / الرمز البريدي الجزائري | `99999` | — | secondary |
| Tunisia | `tn.mobile` | Tunisian mobile number / شماره موبایل تونس / رقم الهاتف الجوال التونسي | `99 999 999` | — | secondary |
| Tunisia | `tn.cin` | Tunisian CIN / کارت ملی تونس / رقم بطاقة التعريف الوطنية التونسية | `99999999` | — | unverified |
| Tunisia | `tn.iban` | Tunisian IBAN / شبا تونس / رقم الآيبان التونسي | `TN99 9999 9999 9999 9999 9999` | iso7064_mod97_10 | secondary |
| Tunisia | `tn.postal_code` | Tunisian postal code / کد پستی تونس / الرمز البريدي التونسي | `9999` | — | secondary |
| United States | `us.phone` | US / Canada phone (NANP) / شماره تلفن آمریکا/کانادا / رقم هاتف أمريكا الشمالية | `(999) 999-9999` | nanp | secondary |
| United States | `us.zip` | US ZIP code / کد پستی آمریکا (ZIP) / الرمز البريدي الأمريكي | `99999[-9999]` | — | secondary |
| United States | `us.ssn` | US Social Security number / شماره تأمین اجتماعی آمریکا / رقم الضمان الاجتماعي الأمريكي | `999-99-9999` | us_ssn | secondary |
| United States | `us.ein` | US Employer Identification Number (EIN) / شماره شناسایی کارفرما (EIN) / رقم تعريف صاحب العمل الأمريكي | `99-9999999` | us_ein | confirmed |
| United Kingdom | `uk.mobile` | UK mobile number / شماره موبایل بریتانیا / رقم الجوال البريطاني | `07999 999999` | — | secondary |
| United Kingdom | `uk.landline` | UK landline / تلفن ثابت بریتانیا / الهاتف الأرضي البريطاني | — | — | secondary |
| United Kingdom | `uk.postcode` | UK postcode / کد پستی بریتانیا / الرمز البريدي البريطاني | — | — | secondary |
| United Kingdom | `uk.nino` | UK National Insurance number / شماره بیمه ملی بریتانیا / رقم التأمين الوطني البريطاني | `aa 99 99 99 a` | uk_nino | secondary |
| Canada | `ca.postal_code` | Canadian postal code / کد پستی کانادا / الرمز البريدي الكندي | `a9a 9a9` | — | secondary |
| Australia | `au.mobile` | Australian mobile number / شماره موبایل استرالیا / رقم الجوال الأسترالي | `0499 999 999` | — | secondary |
| Australia | `au.postcode` | Australian postcode / کد پستی استرالیا / الرمز البريدي الأسترالي | `9999` | — | secondary |
| Australia | `au.abn` | Australian Business Number (ABN) / شماره کسب‌وکار استرالیا (ABN) / رقم الأعمال الأسترالي | `99 999 999 999` | au_abn | secondary |
| India | `in.mobile` | Indian mobile number / شماره موبایل هند / رقم الجوال الهندي | `99999 99999` | — | secondary |
| India | `in.pin` | Indian PIN code / کد پستی هند (PIN) / الرمز البريدي الهندي | `999 999` | — | secondary |
| India | `in.pan` | Indian PAN / شماره PAN هند / رقم حساب ضريبي هندي (PAN) | `aaaaa9999a` | — | secondary |
| India | `in.aadhaar` | Aadhaar number / شماره آدهار هند / رقم آدهار الهندي | `9999 9999 9999` | verhoeff | secondary |
| Germany | `de.plz` | German postal code (PLZ) / کد پستی آلمان / الرمز البريدي الألماني | `99999` | — | secondary |
| International | `intl.phone_e164` | International phone (E.164) / شماره تلفن بین‌المللی / رقم هاتف دولي | — | — | secondary |
| International | `intl.iban` | IBAN (any country) / شماره IBAN (همه کشورها) / رقم الآيبان (جميع الدول) | `aa99[ ****][ ****][ ****][ ****][ ****][ ****][ ****][ **]` | iso7064_mod97_10 | confirmed |
| International | `intl.card` | Credit / debit card number / شماره کارت اعتباری / رقم بطاقة الائتمان | formatter: card | card | secondary |
| International | `intl.cvc` | Card security code (CVC) / کد CVV2 / رمز التحقق (CVC) | `999[9]` | — | secondary |
| International | `intl.card_expiry` | Card expiry (MM/YY) / تاریخ انقضای کارت / تاريخ انتهاء البطاقة | `99/99` | card_expiry | secondary |
| International | `intl.date_mdy` | Date (MM/DD/YYYY) / تاریخ میلادی (ماه/روز/سال) / التاريخ (شهر/يوم/سنة) | `99/99/9999` | date | secondary |
| International | `intl.date_dmy` | Date (DD/MM/YYYY) / تاریخ میلادی (روز/ماه/سال) / التاريخ (يوم/شهر/سنة) | `99/99/9999` | date | secondary |
| International | `intl.date_iso` | Date (YYYY-MM-DD) / تاریخ ISO / التاريخ (ISO) | `9999-99-99` | date | secondary |
| International | `intl.time_24` | Time (24-hour) / ساعت (۲۴ ساعته) / الوقت (24 ساعة) | `99:99` | — | secondary |
| International | `intl.time_12` | Time (12-hour) / ساعت (۱۲ ساعته) / الوقت (12 ساعة) | `99:99 aa` | — | secondary |
| International | `intl.currency` | Currency amount / مبلغ / المبلغ | formatter: thousands | — | secondary |
| International | `intl.percentage` | Percentage / درصد / نسبة مئوية | — | — | secondary |
| International | `intl.ipv4` | IPv4 address / آدرس IPv4 / عنوان IPv4 | — | — | secondary |
| International | `intl.mac` | MAC address / آدرس MAC / عنوان MAC | `**:**:**:**:**:**` | — | secondary |
| International | `intl.hex_color` | Hex colour / رنگ هگز / لون سداسي عشري | — | — | secondary |
| International | `intl.slug` | URL slug / نامک (اسلاگ) / المعرّف النصي للرابط | — | — | secondary |
| International | `intl.license_key` | License key (XXXX-XXXX-XXXX-XXXX) / کلید لایسنس / مفتاح الترخيص | `****-****-****-****` | — | secondary |
| International | `intl.passport` | Passport number (generic) / شماره گذرنامه (عمومی) / رقم جواز السفر (عام) | — | — | secondary |

### 9.2 Custom mask

"Custom" option reveals a mask text box (token syntax of §3, with a live preview using `applyMask`) and an optional
"custom regex" box (validated with `new RegExp(x, 'u')` in the builder and `@preg_match` on save; reject `~` or escape
it; cap length at ~200 chars). A custom mask never enables a checksum; offer an optional "checksum" select (none,
luhn, iban, iran_national_code …) for advanced users.

### 9.3 Store formatted vs raw

Option per field: **Store canonical value (recommended)** = `toStored()` (digits / E.164 / no grouping) vs
**Store as displayed** = `formatValue()` output. Canonical is the default because search, de-duplication, CSV
export, Google Sheets and SMS/OTP add-ons need it. Whatever is stored, the response viewer can re-format for display.

### 9.4 Error messages per failure type

From `error_messages` in the catalog (placeholders `{label}`, `{placeholder}`, `{length}`):

| code | en | fa | ar |
|---|---|---|---|
| `empty` | Please enter {label}. | لطفاً {label} را وارد کنید. | يرجى إدخال {label}. |
| `format` | {label} is not in the correct format (example: {placeholder}). | قالب {label} درست نیست (نمونه: {placeholder}). | تنسيق {label} غير صحيح (مثال: {placeholder}). |
| `checksum` | {label} is not valid. Please check the digits for typos. | {label} معتبر نیست. لطفاً ارقام را دوباره بررسی کنید. | {label} غير صالح. يرجى التحقق من الأرقام. |
| `rule` | {label} contains an impossible value (for example a date that does not exist or a reserved number). | {label} مقدار غیرممکن دارد (مثلاً تاریخی که وجود ندارد یا شماره رزروشده). | {label} يحتوي على قيمة غير ممكنة (مثل تاريخ غير موجود أو رقم محجوز). |
| `length` | {label} must be {length} characters. | {label} باید {length} کاراکتر باشد. | يجب أن يتكون {label} من {length} خانات. |

Use the field's own label for `{label}`; for IDs prefer the specific wording ("کد ملی معتبر نیست") by letting the
admin override per field.

## 10. Known pitfalls

- **Iranian national code with leading zeros.** Codes beginning `00…` (Tehran) are often typed or stored as numbers,
  losing zeros (`12345678`). Rule: if the input is 8 or 9 digits, left-pad to 10 before the checksum
  (`pad_left: {to:10, min:8}`); store as text. Never use `type="number"` or integer DB columns.
- **Persian/Arabic digits from mobile keyboards.** `۰۹۱۲…` / `٠٥٠…` must validate; `/^\d+$/` alone rejects them
  (JS `\d` is ASCII-only, PCRE without UCP too). Normalize first.
- **ي vs ی, ك vs ک.** Arabic keyboards produce U+064A/U+0643; Persian ones U+06CC/U+06A9. Visually identical in
  most fonts. Fold only for Persian-context masks (plates), never in Arabic forms.
- **Invisible marks.** Copying a Sheba or phone from a banking app, SMS or WhatsApp often carries U+200F/U+200E or
  U+202A–202E. Strip them (step 2) or regexes fail mysteriously.
- **IBAN with spaces / lowercase / missing IR.** Strip spaces and dashes, uppercase; Iranian users often paste only
  the 24 digits — prepend `IR` when exactly 24 digits are given.
- **Saudi/UAE numbers with 00966 / +966 0.** Accept `00966…`, `966…`, `+966…`, `05…`; `+966 05…` (trunk kept after the
  country code) is a frequent mistake — either reject with a hint or remove the `0`; store E.164.
- **Amounts in Persian.** `۱٬۲۵۰٬۰۰۰` uses U+066C, sometimes `،` U+060C; `٫` U+066B is the decimal separator. Show the
  amount in words to catch an extra zero; Toman vs Rial confusion is common (label the unit).
- **Bidi rendering.** Without `dir="ltr"` on the input, `+98 912` displays as `912 98+` in an RTL page and the caret
  jumps; labels stay RTL.
- **Egypt NID / Emirates ID check digits.** Egypt's 14th digit algorithm is not public (do not apply Luhn); some
  real Emirates IDs are reported to fail Luhn — make that check a soft warning if users complain.
- **Dates.** Jalali 1403 is leap, 1402 is not; 1900 is not a Gregorian leap year; Hijri month length cannot be
  derived arithmetically.
- **Uppercasing.** Use ASCII-only uppercasing; locale-aware uppercasing turns `i` into `İ` on Turkish locales.
- **Operator prefixes change.** Do not reject Iranian mobiles by operator (number portability).

## 11. Testing

The test vectors are the `examples_valid`, `examples_invalid` (with expected failure code) and `examples_format` of
every catalog entry. The harness runs each vector through both implementations and compares `ok`, `code`,
normalized `value`, `toStored()` output and `formatValue()` output field by field:

```
node build-catalog.js masks-catalog.json            # regenerates synthetic checksum-valid examples
node test.js masks-catalog.json > js.json
php  test.php masks-catalog.json > php.json
node compare.js js.json php.json                    # pass/fail counts + any JS/PHP mismatch
node extra-test.js masks-catalog.json               # Jalali leap years, card brands, DOM caret/IME, catalog shape
```

Vector counts per preset:

| id | valid | invalid | format |
|---|---|---|---|
| `ir.mobile` | 5 | 4 | 3 |
| `ir.landline` | 3 | 3 | 1 |
| `ir.national_id` | 4 | 6 | 2 |
| `ir.legal_id` | 3 | 3 | 0 |
| `ir.economic_code` | 3 | 2 | 0 |
| `ir.postal_code` | 3 | 3 | 1 |
| `ir.bank_card` | 3 | 3 | 2 |
| `ir.sheba` | 4 | 4 | 3 |
| `ir.account_number` | 3 | 2 | 0 |
| `ir.vehicle_plate` | 5 | 3 | 1 |
| `ir.passport` | 2 | 2 | 1 |
| `ir.date_jalali` | 5 | 4 | 2 |
| `ir.time` | 3 | 2 | 2 |
| `ir.amount_toman` | 4 | 3 | 3 |
| `ir.amount_rial` | 2 | 2 | 1 |
| `sa.mobile` | 5 | 3 | 2 |
| `sa.landline` | 2 | 2 | 0 |
| `sa.national_id` | 5 | 4 | 0 |
| `sa.cr_number` | 3 | 2 | 0 |
| `sa.vat` | 3 | 3 | 0 |
| `sa.iban` | 3 | 2 | 1 |
| `sa.postal_code` | 3 | 2 | 2 |
| `sa.short_address` | 2 | 2 | 1 |
| `sa.date_hijri` | 3 | 3 | 1 |
| `ae.mobile` | 3 | 2 | 1 |
| `ae.emirates_id` | 3 | 3 | 2 |
| `ae.iban` | 2 | 2 | 1 |
| `ae.trn` | 2 | 2 | 1 |
| `eg.mobile` | 4 | 2 | 1 |
| `eg.national_id` | 3 | 4 | 0 |
| `eg.iban` | 2 | 2 | 0 |
| `eg.postal_code` | 2 | 2 | 0 |
| `kw.mobile` | 3 | 2 | 1 |
| `kw.civil_id` | 3 | 4 | 0 |
| `kw.iban` | 2 | 2 | 1 |
| `kw.postal_code` | 2 | 2 | 0 |
| `qa.mobile` | 3 | 2 | 1 |
| `qa.qid` | 2 | 2 | 0 |
| `qa.iban` | 2 | 2 | 0 |
| `bh.mobile` | 2 | 2 | 0 |
| `bh.cpr` | 2 | 2 | 0 |
| `bh.iban` | 2 | 2 | 0 |
| `om.mobile` | 2 | 2 | 0 |
| `om.iban` | 2 | 2 | 0 |
| `om.postal_code` | 2 | 2 | 0 |
| `jo.mobile` | 3 | 2 | 1 |
| `jo.national_number` | 2 | 2 | 0 |
| `jo.iban` | 2 | 2 | 0 |
| `jo.postal_code` | 2 | 2 | 0 |
| `iq.mobile` | 3 | 2 | 1 |
| `iq.iban` | 2 | 2 | 0 |
| `lb.mobile` | 4 | 2 | 0 |
| `lb.iban` | 2 | 2 | 0 |
| `ma.mobile` | 2 | 2 | 1 |
| `ma.cin` | 2 | 2 | 0 |
| `ma.iban` | 2 | 2 | 0 |
| `ma.postal_code` | 2 | 2 | 0 |
| `dz.mobile` | 3 | 2 | 1 |
| `dz.iban` | 2 | 2 | 0 |
| `dz.postal_code` | 2 | 2 | 0 |
| `tn.mobile` | 3 | 2 | 1 |
| `tn.cin` | 2 | 2 | 0 |
| `tn.iban` | 2 | 2 | 0 |
| `tn.postal_code` | 2 | 2 | 0 |
| `intl.phone_e164` | 4 | 4 | 0 |
| `us.phone` | 3 | 4 | 1 |
| `us.zip` | 3 | 3 | 2 |
| `us.ssn` | 3 | 6 | 1 |
| `us.ein` | 2 | 3 | 1 |
| `uk.mobile` | 3 | 3 | 1 |
| `uk.landline` | 3 | 2 | 0 |
| `uk.postcode` | 8 | 4 | 0 |
| `uk.nino` | 3 | 4 | 1 |
| `ca.postal_code` | 3 | 3 | 1 |
| `au.mobile` | 2 | 2 | 1 |
| `au.postcode` | 3 | 2 | 0 |
| `au.abn` | 3 | 2 | 1 |
| `in.mobile` | 3 | 2 | 1 |
| `in.pin` | 2 | 2 | 1 |
| `in.pan` | 2 | 2 | 1 |
| `in.aadhaar` | 2 | 3 | 1 |
| `de.plz` | 3 | 2 | 0 |
| `intl.iban` | 5 | 4 | 1 |
| `intl.card` | 8 | 4 | 4 |
| `intl.cvc` | 2 | 2 | 1 |
| `intl.card_expiry` | 3 | 3 | 1 |
| `intl.date_mdy` | 2 | 3 | 1 |
| `intl.date_dmy` | 3 | 3 | 1 |
| `intl.date_iso` | 2 | 3 | 1 |
| `intl.time_24` | 2 | 2 | 1 |
| `intl.time_12` | 3 | 3 | 1 |
| `intl.currency` | 5 | 3 | 3 |
| `intl.percentage` | 4 | 3 | 0 |
| `intl.ipv4` | 3 | 3 | 0 |
| `intl.mac` | 2 | 3 | 1 |
| `intl.hex_color` | 2 | 2 | 0 |
| `intl.slug` | 2 | 3 | 0 |
| `intl.license_key` | 2 | 2 | 1 |
| `intl.passport` | 3 | 2 | 0 |

### Results

| Check | JavaScript (Node 22.23.4) | PHP 8.3.6 |
|---|---|---|
| `examples_valid` accepted | 283/283 | 283/283 |
| `examples_invalid` rejected with the expected code | 255/255 | 255/255 |
| `examples_format` produce the expected display | 74/74 | 74/74 |
| JS vs PHP field mismatches (ok, code, value, stored, pass) over 612 vectors | 0 | 0 |
| Extra JS checks (Jalali leap 1370–1415, card brands, expiry with `now`, DOM caret/IME/attributes, catalog shape) | 658 passed, 0 failed | n/a |

PHP syntax: `php -l` clean; the PHP file uses no PHP 8-only syntax (target 7.4). Registry IBAN examples (SA, AE, EG, KW, QA, BH, OM, JO, IQ, LB, TN, DE, GB, FR, NO) pass mod-97 as published, which cross-checks the implementation against independent data.

## 12. Sources and unverified items

Primary or authoritative sources consulted in this session: SWIFT IBAN registry (via the Wikipedia country table,
which also lists the national-use / aspirational countries), IRS valid EIN prefixes page, SAMA rulebook (Saudi ID
structure), django-localflavor source (Kuwait Civil ID), persian-tools source (Iran national code, legal ID, BIN
and Sheba bank-code tables, operator prefixes, plate letters), parsitext source (legal ID variant), Gravity Forms
input-mask documentation. Per-preset URLs are in each catalog entry's `sources`.

Items marked **(unverified)** — implement them as soft rules or confirm before relying on them:

- `ir.legal_id` — add = d[9] + 2; s = Σ (d[i] + add) * W[i mod 5] for i=0..9 with W = [29,27,23,19,17]; r = s mod 11; r==10 -> 0; r must equal d[10]
- `ir.economic_code` — (unverified) The legacy economic code was 12 digits.
- `ir.postal_code` — 10 digits, first 5 = area/route, last 5 = building
- `ir.account_number` — Each bank uses its own length and separators (e.g
- `ir.vehicle_plate` — (unverified), ع = public transport, ک = agricultural, ژ = disabled/veterans, D/S = diplomatic, .
- `ir.passport` — (unverified) Current biometric passports show one Latin letter followed by 8 digits.
- `sa.landline` — Area codes 011 Riyadh, 012 Makkah/Jeddah, 013 Eastern, 014 Madinah/Tabuk/North, 016 Qassim/Hail, 017 South (unverified list).
- `sa.cr_number` — (unverified) Classic CRs are 10 digits whose first 4 digits identify the issuing office (e.
- `sa.short_address` — (unverified) 4 letters (region, branch, division, unique) + 4 digits (building number) as printed by Saudi Post / SPL.
- `ae.trn` — (unverified) 15 digits issued by the Federal Tax Authority, in practice starting with 100.
- `kw.postal_code` — 5 digits (rarely used in addresses).
- `qa.qid` — (unverified) 11 digits: century (2 = 1900s, 3 = 2000s), 2-digit birth year, 3-digit ISO 3166 numeric nationality code (634 = Qatar), 5-digit serial.
- `bh.cpr` — (unverified) 9 digits: YYMM of birth + 4-digit serial + check digit (algorithm not public).
- `jo.national_number` — (unverified) 10 digits; older numbers encode birth year and gender (e.
- `ma.cin` — (unverified) 1-2 letters (issuing region) followed by up to 6 digits.
- `tn.cin` — (unverified) 8 digits, no public checksum.
- `sa.national_id` (partly) — Official formula is only available from NIC (unverified).
- `ae.emirates_id` (partly) — Some developers report real IDs failing Luhn (unverified); consider making the checksum a warning rather than a hard error.
- `eg.national_id` (partly) — The 14th digit is a check digit whose algorithm is not published (unverified; some libraries try Luhn, which fails on real IDs).
- `kw.mobile` (partly) — Mobile ranges start with 5, 6, 9 (and 41 for newer allocations - unverified).
- `intl.card_expiry` (partly) — 05/03 = 1405 Khordad) - offer a separate "ir" variant if needed (unverified).
- Token compatibility details for WPForms (Inputmask) and Fluent Forms (jQuery Mask Plugin) — not re-checked against current docs.
