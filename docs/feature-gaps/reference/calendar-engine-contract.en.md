# Calendar engine, picker and integration contract (F06)

Status: **implemented** on `dev4`, 2026-11-13 (proposal 2026-11-10). Differences from the text below,
as built: the global PHP functions live in `includes/calendar-functions-efb.php` and the routes in
`includes/class-Emsfb-calendar-rest.php`; refusals on submit carry `code: efb_date_<code>`;
`efb_date_validated` is a filter (return a `WP_Error` to refuse); the `availability` route is not
plan-gated (it answers from the filters an add-on registers); `date_is_holiday` and a `cal` unit on limits were not built. Details:
`docs/autopilot-integration.md` (F06) and the status section at the end of the spec. Companion of `../F06-advanced-calendar.fa.md` (the product spec,
in Persian). This file holds the parts other code relies on: the engine interface, the public JS
API, the PHP API and hooks, the REST routes, the machine-readable field schema, the stored value
format, versioning and security. The Booking add-on (`AdnBEF`, reserved in
`includes/functions.php` ~4631) and the separate AI site assistant plugin may rely on everything
marked **stable**. Anything marked *internal* may change without notice.

Verified conversion data lives in `calendar-test-vectors.json` next to this file (see §10).

---

## 1. Terms

| Term | Meaning |
|---|---|
| calendar id | A CLDR/BCP 47 calendar identifier: `gregory`, `persian`, `islamic-umalqura`, `islamic-civil`, `islamic-tbla`, `buddhist`, `roc`, `japanese`, `hebrew`, `indian`, `coptic`, `ethiopic`. Matches the `-u-ca-` value that `Intl.DateTimeFormat` and ICU take, so no mapping table is needed. |
| local date | A date in some calendar, as `{y, m, d}` (1-based month) or the string `YYYY-MM-DD` with ASCII digits, e.g. `1403-12-30` in `persian`. |
| canonical date | A proleptic Gregorian date as an ISO 8601 calendar date, `YYYY-MM-DD`. This is the only form that is stored, compared and validated. |
| JDN | Julian Day Number (integer). Every engine converts through JDN, so any calendar converts to any other calendar as `B.fromJdn(A.toJdn(x))`. |
| field tz | The IANA time zone used for "today" and for times: the site zone (`wp_timezone_string()`) by default. |
| `advdate` | The new field type string, stored in `form_structer` rows. Lower case, like `pdate`, `ardate` and `dadfile`. Legacy `date`, `pdate` and `ardate` stay as they are (§9). |

## 2. Calendar support tiers

| Tier | Calendars | JS | PHP (no ext-intl needed) | Grid + typed input | Server-side rules in that calendar |
|---|---|---|---|---|---|
| 1 | `gregory`, `persian`, `islamic-umalqura`, `islamic-civil`, `islamic-tbla` | own arithmetic (`efb-calendar-core.js`) | own arithmetic (`class-Emsfb-calendar.php`) | yes | yes (recurring holidays, local min/max) |
| 2 | `buddhist`, `roc`, `japanese` | Gregorian grid, year/era label from `Intl` | year offset (`buddhist` +543, `roc` −1911); `japanese` era from ext-intl when loaded, else Gregorian year | yes (same months as Gregorian) | via Gregorian |
| 3 | `hebrew`, `indian`, `coptic`, `ethiopic` (+ `chinese`, `dangi` if asked) | display only: secondary label under each Gregorian day and in the value's `disp` | ext-intl `IntlDateFormatter` when loaded, else the ISO date | no grid in v1 | no |

Why Tier 3 has no grid in v1: Hebrew has a leap month (`Adar I`/`Adar II`), Coptic and Ethiopic
have 13 months, and Chinese/Dangi have leap months (`Mo5bis`). `Intl` gives month *names*, not
stable month numbers, for these (verified: `en-u-ca-hebrew` formats month as `Adar I`/`Adar II`,
`chinese` as `Mo5bis`). A grid needs `monthCode` handling. `Temporal` gives it, and it ships in
Chromium 144+ and Firefox 139+ (both true in the probe below) but not in stable Safari, so Tier 3
grids wait for Safari or for a reason to write the arithmetic.

## 3. JS engine interface (stable)

One object per calendar, registered in `EFBCalendar.engines`. Pure functions, no DOM, no
`Date` objects (a `Date` carries a time zone and silently shifts days).

```ts
interface LocalDate { y: number; m: number; d: number }          // m is 1-based

interface CalendarEngine {
  id: string;                         // 'persian'
  tier: 1 | 2 | 3;
  // structure
  monthsInYear(y: number): number;    // 12 for Tier 1/2
  daysInMonth(y: number, m: number): number;
  isLeap(y: number): boolean;         // persian: 30 Esfand exists; islamic: 355-day year
  range: { min: LocalDate; max: LocalDate };   // exact range; outside it `approximate()` is true
  approximate(y: number): boolean;    // islamic-umalqura outside AH 1318..1500 -> true (civil fallback)
  // conversion
  toJdn(y: number, m: number, d: number): number;
  fromJdn(jdn: number): LocalDate;
  toGregorian(date: LocalDate): LocalDate;     // = gregory.fromJdn(this.toJdn(...))
  fromGregorian(date: LocalDate): LocalDate;
  isValid(date: LocalDate): boolean;  // m in 1..monthsInYear, d in 1..daysInMonth
  // names (from Intl; bundled fallback strings when Intl lacks the locale)
  monthNames(locale: string, style?: 'long' | 'short' | 'narrow'): string[];   // index 0 = month 1
  weekdayNames(locale: string, style?: 'long' | 'short' | 'narrow'): string[]; // index 0 = Monday (ISO)
  eraName?(y: number, locale: string): string;
  // text
  format(date: LocalDate, pattern: string, locale: string, opts?: { digits?: 'latn' | 'arabext' | 'arab' }): string;
  parse(text: string, pattern: string, locale: string): LocalDate | null;  // null = not a date in this calendar
}
```

Rules every engine follows:

- `parse` first maps Persian (U+06F0..U+06F9) and Arabic-Indic (U+0660..U+0669) digits to ASCII,
  strips U+200B..U+200F, U+202A..U+202E, U+2060..U+2069, U+061C, U+00A0 and U+FEFF, accepts `/`,
  `.` and `-` as separators, then reads the parts in the order the pattern gives. It never guesses
  D/M versus M/D: text that does not fit the field's pattern returns `null`
  (`calendar-test-vectors.json` → `edge_cases[kind=normalize]`). This is the same normalisation as
  today's `efb_typed_date_efb()` (`public/assets/js/core-efb.js` ~1280) and
  `Emsfb_Public::normalize_typed_date_efb()` (`includes/class-Emsfb-public.php` ~9669), with
  one difference: validity uses the engine's real month lengths. The legacy helpers accept
  `1404-12-30` (they allow 30 days in every month 7..12) and `1446-09-30`; both are invalid.
- Pattern tokens: `YYYY`, `YY` (display only), `MM`, `M`, `MMM`, `MMMM` (month name),
  `DD`, `D`, `ddd`, `dddd` (weekday name), `HH`, `hh`, `mm`, `A` (am/pm). Literal text in `[...]`.
  Patterns are written left to right; RTL ordering is done by the browser's bidi algorithm, with
  the whole value wrapped in U+2068..U+2069 (FSI/PDI) when shown inside an RTL label.
- Weekday arithmetic is on JDN: ISO weekday = `(jdn mod 7) + 1` (1 = Monday .. 7 = Sunday).
- Hijri sighting adjustment (`cal_hijri_adjust`, integer −2..+2) is applied by the picker, not
  the engine: shown local date = `engine.fromJdn(jdn + adjust)`; picked local date maps back to
  `jdn = engine.toJdn(...) - adjust`. Vectors: `edge_cases[kind=hijri_adjust]`.

### 3.1 Algorithms (Tier 1)

| Calendar | Algorithm | Source / license | Verified range |
|---|---|---|---|
| `persian` | Borkowski "breaks" algorithm, exactly as in jalaali-js 1.x/2.x (`jalCal`, `j2d`, `d2j`) | jalaali-js, MIT (keep the notice) | identical to ICU 74.2 (PHP), 78.3 (Node 22), Chromium 153 and Firefox 155 for every day of AP 1300..1499; identical to Node ICU for AP 1179..1501 (Gregorian 1800-03-21..2123-03-20); first difference 2124-03-20 |
| `islamic-umalqura` | table lookup: one 12-bit month-length mask per year AH 1318..1500, chained from 1 Muharram 1318 = 1900-04-30 (JDN 2415140); 549 hex characters | `dotnet/runtime` `UmAlQuraCalendar.cs`, MIT; AH 1420..1500 identical to `hijridate` (MIT) | table itself; see §3.2 for how ICU differs |
| `islamic-civil` | tabular, epoch JDN 1948440 (Friday 16 July 622 Julian), leap iff `(14 + 11y) mod 30 < 11` | public algorithm | identical to ICU for every day of AH 1300..1599 |
| `islamic-tbla` | same, epoch JDN 1948439 (Thursday) | public algorithm | identical to ICU for every day of AH 1300..1599 |
| `gregory` | proleptic Gregorian ↔ JDN (Fliegel–Van Flandern) | public algorithm | identical to JS `Date` for 1800..2200 |

Outside AH 1318..1500, `islamic-umalqura` falls back to `islamic-civil`, `approximate()` returns
true, and the stored value carries `approx: 1` (§6).

### 3.2 Why Umm al-Qura is not taken from `Intl`

Measured month by month (first day of every month) against the published table:

| AH years | Node ICU 78.3 | PHP ICU 74.2 | Chromium 153 | Firefox 155 | .NET table vs hijridate |
|---|---|---|---|---|---|
| 1318..1419 | 226 / 1224 | 226 / 1224 | same as Node (6555 / 36146 days) | same | 612 / 924 month starts (1343..1419; the historical tables disagree with each other) |
| 1420..1450 | 372 / 372 | 372 / 372 | 10985 / 10985 days | same | 372 / 372 |
| 1451..1500 | 389 / 600 | 389 / 600 | 11390 / 17719 days | same | 600 / 600 |

So every ICU build tested, browser and server, starts 211 of the 600 months from AH 1451
(Gregorian 2029–2077) one day late, for example 1 Muharram 1452 (table 2030-05-03, ICU
2030-05-04). A form that takes bookings for 2030 would show the wrong Hijri day. The engine ships
its own table. Before AH 1420 the official calculation rule was different and the published
historical tables disagree with each other by a day in places. The engine uses the .NET table
there and the UI notes that historical Hijri dates (birth dates) may be off by one day. Canonical
storage is Gregorian, so only the label is affected, never the stored data.

`Intl` is still used for month and weekday names, digits, era names and Tier 2/3 labels.

## 4. Picker public JS API (stable)

Loaded only on pages with an `advdate` field (§8 of the F06 spec). Global: `window.EFBCalendar`.

```ts
interface CanonicalValue {
  v: 1;                         // value schema version
  mode: 'single' | 'range' | 'multiple';
  iso: string;                  // 'single': '2025-03-20' or '2025-03-20T14:30'
                                // 'range' : '2025-03-20/2025-03-23' (ISO 8601 interval)
                                // 'multiple': '2025-03-20,2025-03-25' (sorted, unique)
  dates: string[];              // the same dates as an array (range: [start, end])
  time?: string;                // 'HH:mm' 24h, local wall time in `tz`
  utc?: string;                 // with time: the instant, '2025-03-20T11:00:00Z'
  tz: string;                   // IANA zone used for today/time, e.g. 'Asia/Tehran'
  cal: string;                  // calendar the visitor used, e.g. 'persian'
  local: string;                // same shape as iso, in `cal`: '1403-12-30'
  disp: string;                 // display text as shown, e.g. '۳۰ اسفند ۱۴۰۳'
  approx?: 1;                   // a date outside an exact calendar table
}

interface ConstraintError {
  code: ErrorCode;              // §4.3
  message: string;              // translated, ready to show
  date?: string;                // the offending ISO date
  limit?: string | number;      // e.g. the resolved min date, or the age limit
}

interface SetResult { ok: boolean; value?: CanonicalValue; errors?: ConstraintError[] }

interface EFBCalendarStatic {
  version: string;                                    // semver of this API, '1.0.0'
  supports(feature: 'range' | 'multiple' | 'time' | 'availability' | 'calendar:' + string): boolean;
  engines: { get(id: string): CalendarEngine | null; list(): string[]; register(engine: CalendarEngine): void };
  get(fieldId: string, formId?: number): EFBCalendarField | null;   // null until mounted
  whenReady(fieldId: string, formId?: number): Promise<EFBCalendarField>;
  all(formId?: number): EFBCalendarField[];
  convert(date: string | LocalDate, from: string, to: string, opts?: { hijriAdjust?: number }): LocalDate & { iso: string; approx?: boolean };
  today(tz?: string): string;                         // ISO date in tz (default: site tz)
}

interface EFBCalendarField {
  readonly fieldId: string;           // row id_
  readonly formId: number;
  readonly input: HTMLInputElement;   // the hidden canonical input (class emsFormBuilder_v)
  getValue(): CanonicalValue | null;
  setValue(input: string | CanonicalValue | { cal: string; date: string; time?: string } | { cal: string; start: string; end: string } | null,
           opts?: { source?: 'api' | 'assistant' | 'autofill' | 'booking'; silent?: boolean }): Promise<SetResult>;
  clear(opts?: { silent?: boolean }): void;
  validate(input: Parameters<EFBCalendarField['setValue']>[0]): Promise<SetResult>;   // dry run, changes nothing
  isDateAllowed(iso: string): { allowed: boolean; code?: ErrorCode; capacity?: number | null; remaining?: number | null };
  getConstraints(): ResolvedConstraints;   // §4.2, with today and field-relative limits resolved
  setConstraints(partial: Partial<FieldConfig>, opts?: { replace?: boolean }): void;   // runtime only
  setAvailability(source: ((iso: string) => AvailabilityStatus | Promise<AvailabilityStatus>)
                        | Record<string, number | AvailabilityStatus>     // { '2026-03-21': 0 } = full
                        | { url: string } | null): void;
  getCalendar(): string;
  setCalendar(id: string): boolean;        // false if not in cal_allowed or cal_switch is off
  goTo(isoOrMonth: string): void;          // '2026-03' or '2026-03-21'; no value change
  open(): void; close(): void;
  on(event: EventName, handler: (detail: any) => void): () => void;   // returns unsubscribe
  off(event: EventName, handler: Function): void;
  destroy(): void;
}

type AvailabilityStatus = { available: boolean; capacity?: number | null; remaining?: number | null;
                            code?: 'full' | 'closed' | 'holiday'; label?: string; slots?: Slot[] };
type Slot = { start: string /* 'HH:mm' */; end: string; remaining?: number | null; available: boolean };
type EventName = 'ready' | 'change' | 'invalid' | 'monthchange' | 'calendarchange' | 'open' | 'close' | 'availability';
```

Behaviour that consumers rely on:

- `setValue` runs the same checks the server runs (§5.3) plus the availability source, shows the
  result in the UI and returns the errors. A rejected value leaves the field unchanged. It
  resolves after a pending availability request for that month (no race with a booking fetch).
- `setValue('2025-03-20')` is always Gregorian ISO. Calendar-local input needs
  `{cal: 'persian', date: '1403-12-30'}`. Plain strings are never read as local dates, so the
  same call means the same thing on every site.
- `setConstraints` changes only what the visitor can pick. The server does not see it. An add-on
  that narrows constraints must also hook `efb_date_constraints` in PHP (§5.2), or the server
  will accept dates the picker refused.
- After every committed change the field fires `change` and the hidden input fires a bubbling DOM
  `CustomEvent('efb:date-change', { detail: { fieldId, formId, value, previous, source } })`,
  then a native `change` event so the existing core-efb.js pipeline (`handle_change_event_efb_v4`
  → `sendBack_emsFormBuilder_pub`) and Conditional Logic update without special cases.
- `monthchange` fires with `{ calendar, year, month, firstIso, lastIso }` whenever the visible
  grid changes, including switching calendar. This is when a booking add-on should load
  availability for `firstIso..lastIso`.
- `calendarchange` fires with `{ from, to }`. The selected canonical value does not change, only
  its rendering. Constraints are already canonical, so nothing needs re-checking.
- `availability` fires with `{ firstIso, lastIso, loaded: boolean }` around availability fetches.

### 4.1 Field config (row keys) as read by the picker

The row object in `form_structer` (opaque, unknown keys preserved, per
`_workspace/easy-form-builder-form-builder/docs/STATE_SCHEMA.md`). All keys are optional. A
missing key means the default. The JSON Schema in §7.1 is the source of truth; summary:

| Key | Type | Default | Notes |
|---|---|---|---|
| `type` | `"advdate"` | – | |
| `cal_v` | integer | `1` | config schema version |
| `cal_default` | `"auto"` \| calendar id | `"auto"` | `auto` = from the site locale (F06 §5.1) |
| `cal_allowed` | calendar id[] | `[cal_default]` | order = switcher order |
| `cal_switch` | `0`/`1` | `0` | visitor may switch among `cal_allowed` |
| `cal_secondary` | calendar id \| `""` | `""` | small second label in each day cell (e.g. Hijri under Gregorian) |
| `cal_hijri_adjust` | integer −2..2 | `0` | applies to `islamic-*` only |
| `cal_locale` | `"auto"` \| BCP 47 | `"auto"` | names/digits locale; auto = page locale |
| `cal_digits` | `"auto"` \| `"latn"` \| `"arabext"` \| `"arab"` | `"auto"` | auto = `Intl` default for the locale |
| `week_start` | `"auto"` \| 1..7 | `"auto"` | ISO weekday; auto = `Intl.Locale#getWeekInfo().firstDay` |
| `date_mode` | `"single"` \| `"range"` \| `"multiple"` | `"single"` | |
| `date_min`, `date_max` | Limit | `{type:"none"}` | see below |
| `disabled_weekdays` | int[] (1..7) | `[]` | |
| `disabled_dates` | string[] | `[]` | `YYYY-MM-DD` or `YYYY-MM-DD/YYYY-MM-DD` (inclusive range), canonical |
| `enabled_dates` | string[] | `[]` | same syntax; when non-empty, **only** these dates can be picked |
| `holiday_sets` | string[] | `[]` | ids from the holiday registry (§5.4), e.g. `"ir-official"` |
| `holidays_custom` | HolidayRule[] | `[]` | `{cal, md:"MM-DD", label, days?:n}` recurring, or `{date:"YYYY-MM-DD", label}` |
| `holiday_mode` | `"disable"` \| `"mark"` | `"disable"` | `mark` shows a dot and label but allows picking |
| `range_min_days`, `range_max_days` | integer ≥ 0 | `0` (= no limit) | nights when `range_unit="nights"` |
| `range_unit` | `"nights"` \| `"days"` | `"nights"` | `nights`: start..end−1 must be allowed, end only ≤ max; `days`: every day inclusive |
| `multi_max` | integer ≥ 0 | `0` | `multiple` mode; 0 = no limit |
| `time_enabled` | `0`/`1` | `0` | |
| `time_format` | `"auto"` \| `"12"` \| `"24"` | `"auto"` | |
| `time_step` | integer minutes 1..720 | `15` | |
| `time_min`, `time_max` | `"HH:mm"` | `""` | |
| `time_by_weekday` | `{ "1".."7": [["HH:mm","HH:mm"], ...] }` | `{}` | overrides min/max on that weekday; `[]` = closed |
| `age_min`, `age_max` | integer ≥ 0 \| `""` | `""` | completed years at today in `tz` (birth-date fields) |
| `tz` | `"site"` \| `"visitor"` \| IANA | `"site"` | |
| `display_format` | `"auto"` \| pattern \| `"long"` \| `"medium"` | `"auto"` | |
| `store_format` | `"iso"` \| `"legacy_local"` | `"iso"` | `legacy_local` only for upgraded `pdate`/`ardate` (§9) |
| `input_mode` | `"both"` \| `"picker"` \| `"typed"` | `"both"` | typed input is always available to assistive tech, see F06 §4.6 |
| `availability` | `"none"` \| `"booking"` | `"none"` | set by the Booking add-on; core treats unknown values as `none` |
| `bk_*` | any | – | reserved for the Booking add-on; core never reads or writes them |
| `value` | string | `""` | default: ISO, or relative `"today"`, `"today+7"` |

```ts
type Limit =
  | { type: 'none' }
  | { type: 'absolute'; value: string }                                   // canonical ISO
  | { type: 'relative'; value: number; unit?: 'day' | 'week' | 'month' | 'year' }  // today ± value (unit default 'day')
  | { type: 'field'; field: string; offset?: number; unit?: 'day' | 'week' | 'month' | 'year' };  // other date field (+offset)
```

`relative` with `unit: 'month'|'year'` clamps to the last day of the target month (Jan 31 + 1
month = Feb 28/29). Month and year arithmetic is Gregorian. For a Jalali "one month from today"
use `unit: 'day', value: 30` or a field-level limit; F06 §5.3 explains why.

### 4.2 Resolved constraints

`getConstraints()` and the PHP `efb_date_resolve_constraints()` return the same JSON:

```json
{
  "today": "2026-11-10", "tz": "Asia/Tehran",
  "min": "2026-11-11", "max": "2027-05-09",
  "min_source": "relative", "max_source": "field:check_out_1",
  "disabled_weekdays": [5], "disabled": ["2026-12-25", ["2027-01-01","2027-01-03"]],
  "enabled": [], "holidays": { "2027-03-21": "نوروز" }, "holiday_mode": "disable",
  "mode": "range", "range": { "unit": "nights", "min": 1, "max": 14 },
  "multi_max": 0,
  "time": { "enabled": true, "step": 30, "min": "09:00", "max": "17:00", "by_weekday": { "6": [["10:00","14:00"]] } },
  "age": { "min": 18, "max": null },
  "calendars": { "default": "persian", "allowed": ["persian","gregory"], "switch": true, "hijri_adjust": 0 }
}
```

Holidays are listed only inside `[min, max]` and at most 400 days ahead of `today`.

### 4.3 Error codes (stable)

`invalid_format`, `invalid_date`, `calendar_not_allowed`, `before_min`, `after_max`,
`weekday_disabled`, `date_disabled`, `not_enabled`, `holiday`, `unavailable`, `full`,
`range_order`, `range_too_short`, `range_too_long`, `range_contains_disabled`, `too_many_dates`,
`time_required`, `time_out_of_range`, `time_step`, `time_closed_weekday`, `age_too_young`,
`age_too_old`, `depends_on_empty` (a `field` limit points at an empty field), `required`.
Warnings (do not block): `approximate_date`.

The AI assistant should branch on `code`, never on `message`. New codes may be added in minor
versions. Codes are never renamed.

## 5. PHP API (stable)

New file `includes/class-Emsfb-calendar.php`, namespace `Emsfb`, loaded by core (not an add-on).
Global wrapper functions so add-ons do not depend on class names:

```php
/** @return array{y:int,m:int,d:int,iso:string,approx:bool}|\WP_Error */
efb_calendar_convert( $date, string $from_cal, string $to_cal, array $args = array() );
//   $date: 'YYYY-MM-DD' or ['y'=>,'m'=>,'d'=>]; $args['hijri_adjust'] int.
//   efb_calendar_convert('1403-12-30','persian','gregory') → ['y'=>2025,'m'=>3,'d'=>20,'iso'=>'2025-03-20','approx'=>false]

efb_calendar_engine( string $cal_id );          // object with to_jdn(), from_jdn(), days_in_month(), is_leap(), month_name($m, $locale) | null
efb_calendar_format( string $iso, string $cal, string $pattern = 'auto', string $locale = '', array $args = array() ): string;
efb_calendar_today( string $tz = 'site' ): string;  // ISO date "now" in that zone (uses wp_timezone() for 'site')
efb_calendar_supported(): array;                // ['gregory'=>1,'persian'=>1,...,'hebrew'=>3] id => tier, for this server

/** @return array resolved constraints, same JSON as §4.2 */
efb_date_resolve_constraints( array $field, array $context = array() );
//   $context: ['form_id'=>int, 'values'=>[field_id => canonical value], 'now'=>DateTimeImmutable]

/** @return true|\WP_Error  code = one of §4.3, data = ['date'=>..,'limit'=>..] */
efb_date_is_allowed( array $field, string $iso, array $context = array() );

/** Full check of a submitted value (all modes, time, age, availability).
 *  @return array{ok:bool, row:array, errors:\WP_Error[]}   row = normalised submission row (§6) */
efb_date_validate_value( array $field, $raw, array $context = array() );

efb_date_field_schema( array $field, array $context = array() ): array;   // §7.1 field entry
efb_form_schema( int $form_id, array $context = array() ): array|\WP_Error; // §7.2
```

Rules: pure-PHP engines for Tier 1 (`ext-intl` not required). `ext-intl`
(`IntlDateFormatter`, locale `xx_XX@calendar=<id>`) is used only for names and Tier 2/3 labels
when it is loaded. All four Tier 1 non-Gregorian ids are recognised by ICU 74.2's `IntlCalendar`
(`getType()` returns the same id). It is still not trusted for Umm al-Qura arithmetic (§3.2).
"Today" always comes from `wp_timezone()` or the field's IANA zone, never from `date()`, which in
WordPress is UTC. Today's `date` validation (`class-Emsfb-public.php` ~2815) uses `date('Y-m-d')`
while booking uses `wp_date()`. The new field must not copy that mismatch.

### 5.1 Hook catalogue (stable)

| Hook | Type | Signature | Purpose |
|---|---|---|---|
| `efb_calendar_engines` | filter | `(array $engines)` id ⇒ object | register/replace a PHP engine (e.g. a Hebrew engine) |
| `efb_calendar_holiday_sets` | filter | `(array $sets)` id ⇒ `['label'=>, 'rules'=>[...], 'dates'=>[iso=>label]]` | add or override holiday sets |
| `efb_calendar_default_for_locale` | filter | `(array $cfg, string $locale)` → `['default'=>,'allowed'=>[],'secondary'=>,'digits'=>]` | change the locale → calendar table (F06 §5.1) |
| `efb_date_constraints` | filter | `(array $resolved, array $field, int $form_id, array $context)` | narrow/widen limits server-side; the same result is sent to the picker on render |
| `efb_date_availability` | filter | `(array\|null $status, string $iso, array $field, int $form_id, array $context)` | per-date availability; `null` = no opinion. Return §4 `AvailabilityStatus` as an array |
| `efb_date_availability_range` | filter | `(array $map, string $from, string $to, array $field, int $form_id, array $context)` | bulk form of the above, used by REST (§6.1); `$map` iso ⇒ status. Implement both or let core call the single filter per day |
| `efb_date_validated` | filter | `(true\|\WP_Error $ok, array $row, array $field, int $form_id, array $context)` | last veto after core checks, before the submission is stored |
| `efb_date_before_commit` | filter | `(true\|\WP_Error $ok, array $rows, int $form_id, array $context)` | called once per submission with every `advdate` row, right before the message insert. **The Booking add-on reserves capacity here, atomically** |
| `efb_date_after_commit` | action | `(array $rows, int $form_id, string $track, array $context)` | after the message row exists; link reservations to the tracking code |
| `efb_date_commit_failed` | action | `(array $rows, int $form_id, array $context)` | the insert failed or a later check refused: release reservations |
| `efb_date_display_value` | filter | `(string $text, array $row, array $field, string $channel)` | `channel` ∈ `email`, `viewer`, `csv`, `sms`, `telegram`, `sheet`, `webhook`, `pdf` |
| `efb_form_schema` | filter | `(array $schema, int $form_id, array $context)` | add-ons add their own `x-efb-*` data (booking slots, pricing) |
| `efb_form_schema_field` | filter | `(array $entry, array $field, int $form_id)` | per field |

The submit pipeline already has `efb_3rd_party_*` and `efb_after_form_integration` actions
(`class-Emsfb-public.php` ~10754–10763). They keep working: they receive rows whose `value` is
the canonical ISO string.

## 6. Stored value (stable)

A submission row for an `advdate` field (inside the message `content` JSON, next to the usual
`id_`, `name`, `type`, `amount`, `id_ob`):

```json
{ "id_": "chk_in", "type": "advdate", "name": "Check-in / out",
  "value": "2026-03-19/2026-03-22",
  "cal": "persian", "local": "1404-12-28/1405-01-02",
  "disp": "۲۸ اسفند ۱۴۰۴ – ۲ فروردین ۱۴۰۵",
  "tz": "Asia/Tehran", "mode": "range", "v": 1 }
```

- `value` is always canonical (§4 `iso`). Every existing consumer that reads `value` (email,
  SMS, Telegram, Google Sheet, webhooks, Conditional Logic `date_before/after/between` through
  `efb_conditional_date_ts()` ~6297) gets a sortable Gregorian date it can parse. Today a `pdate`
  value `1403-12-30` goes through `strtotime()` as Gregorian year 1403, so Jalali dates in
  conditional rules compare wrongly. The new field fixes this by design.
- `cal`, `local`, `disp`, `tz`, `mode`, `v`, plus `time`/`utc`/`approx` when present, are
  **recomputed on the server** from `value` with the PHP engines. Client copies are discarded, so
  a visitor cannot inject display text. `advdate` must be added to both copies of the attribute
  allow-list `filter_attributes_by_type_efb()` (`class-Emsfb-formbuilder.php` ~2165 and
  `class-Emsfb-public.php` ~9098) with `['cal'=>1,'local'=>1,'disp'=>1,'tz'=>1,'mode'=>1,'v'=>1,'time'=>1,'utc'=>1,'approx'=>1]`.
- Display per channel: admin-facing channels (viewer, CSV, email to admin) use the admin
  calendar setting (F06 §6.2, default "site calendar + ISO in parentheses"). Visitor emails use
  `disp`. Machine channels (webhook, Google Sheet) send `value` plus the extra keys. All of them
  pass through `efb_date_display_value`.

### 6.1 REST routes (stable)

All in namespace `Emsfb/v1`, registered in the existing `rest_api_init` closure of
`class-Emsfb-public.php` (~49). GET, read-only, JSON. Shared rules:

- The form must exist and the field must be an `advdate` field of that form, otherwise
  `404 efb_not_found`. The same 404 is returned for a field that exists but is hidden by settings,
  so ids cannot be probed.
- Forms with `stateForm` set (login required, checked the same way as `class-Emsfb-public.php`
  ~230/~2509) return `403 efb_login_required` to anonymous callers.
- No nonce is required (read-only, and cached pages carry stale nonces, see
  `efb_refresh_nonce` in core-efb.js). An IP rate limit is applied with
  `efb_ip_rate_allowed_efb('efb_cal_', apply_filters('efb_calendar_rest_per_minute', 60))`.
- Responses carry `Cache-Control: public, max-age=60` when no availability filter is attached,
  otherwise `private, max-age=15`.

**`GET calendar/availability`**

| Param | Type | Notes |
|---|---|---|
| `form_id` | int, required | |
| `field_id` | string, required | the row `id_` |
| `from`, `to` | ISO date, required | inclusive; `to - from` ≤ 92 days, else `400 efb_range_too_long` |
| `with_slots` | 0/1 | ask the booking add-on for time slots (if it provides them) |

```json
{ "v": 1, "form_id": 12, "field_id": "chk_in", "tz": "Asia/Tehran", "today": "2026-11-10",
  "min": "2026-11-11", "max": "2027-05-09",
  "dates": {
    "2026-11-13": { "s": "closed", "code": "weekday_disabled" },
    "2026-11-14": { "s": "open" },
    "2026-11-15": { "s": "full", "code": "full" },
    "2026-11-16": { "s": "open", "r": 3, "slots": [ { "start": "09:00", "end": "10:00", "r": 1 } ] }
  } }
```

`s` ∈ `open`, `closed`, `full`, `holiday`, `out_of_range`. `r` (remaining) is sent only when the
field enables `bk_show_remaining` (Booking add-on). Otherwise capacity numbers never leave the
server (§8). Dates omitted from `dates` inside `[from, to]` are `open`.

**`GET calendar/convert`** (utility; cheap; no form needed)
`?date=1403-12-30&from=persian&to=gregory[&hijri_adjust=0]` →
`{ "iso": "2025-03-20", "y": 2025, "m": 3, "d": 20, "approx": false }`. Tier 1/2 only.

**`GET forms/{id}/schema`** (for the AI site assistant): returns §7.2 for a published form.
Only what a visitor can already see on the page: labels, help text, options, constraints,
required flags, steps. Never: notification e-mails, webhook URLs, SMS/Telegram config, logic
rule internals, payment keys, submissions or counts.

**`POST calendar/check`** (optional, Phase F): `{form_id, field_id, value}` → the same
`SetResult` as the JS `validate()`, checked by the server. Lets an assistant that is not running
in the page (chat channel, voice) confirm a date before it tells the visitor "booked". It
reserves nothing. Rate-limited like the GET routes.

## 7. Machine-readable schema (stable)

### 7.1 `advdate` field config — JSON Schema (draft 2020-12)

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://whitestudio.team/schemas/efb/advdate-config.v1.json",
  "title": "EFB advdate field config v1",
  "type": "object",
  "required": ["id_", "type"],
  "additionalProperties": true,
  "$defs": {
    "cal": { "enum": ["gregory","persian","islamic-umalqura","islamic-civil","islamic-tbla","buddhist","roc","japanese","hebrew","indian","coptic","ethiopic"] },
    "iso": { "type": "string", "pattern": "^\\d{4}-\\d{2}-\\d{2}$" },
    "isoOrRange": { "type": "string", "pattern": "^\\d{4}-\\d{2}-\\d{2}(/\\d{4}-\\d{2}-\\d{2})?$" },
    "hm": { "type": "string", "pattern": "^([01]\\d|2[0-3]):[0-5]\\d$" },
    "unit": { "enum": ["day","week","month","year"] },
    "limit": { "oneOf": [
      { "type": "object", "properties": { "type": { "const": "none" } }, "required": ["type"] },
      { "type": "object", "properties": { "type": { "const": "absolute" }, "value": { "$ref": "#/$defs/iso" } }, "required": ["type","value"] },
      { "type": "object", "properties": { "type": { "const": "relative" }, "value": { "type": "integer", "minimum": -36500, "maximum": 36500 }, "unit": { "$ref": "#/$defs/unit" } }, "required": ["type","value"] },
      { "type": "object", "properties": { "type": { "const": "field" }, "field": { "type": "string", "minLength": 1 }, "offset": { "type": "integer" }, "unit": { "$ref": "#/$defs/unit" } }, "required": ["type","field"] }
    ] },
    "holidayRule": { "oneOf": [
      { "type": "object", "properties": { "cal": { "$ref": "#/$defs/cal" }, "md": { "type": "string", "pattern": "^\\d{2}-\\d{2}$" }, "days": { "type": "integer", "minimum": 1, "maximum": 14 }, "label": { "type": "string" } }, "required": ["cal","md"] },
      { "type": "object", "properties": { "date": { "$ref": "#/$defs/isoOrRange" }, "label": { "type": "string" } }, "required": ["date"] }
    ] }
  },
  "properties": {
    "id_": { "type": "string" },
    "type": { "const": "advdate" },
    "cal_v": { "const": 1 },
    "name": { "type": "string" }, "message": { "type": "string" },
    "required": { "enum": [0, 1, true, false, "0", "1"] },
    "value": { "type": "string" },
    "cal_default": { "anyOf": [ { "const": "auto" }, { "$ref": "#/$defs/cal" } ], "default": "auto" },
    "cal_allowed": { "type": "array", "items": { "$ref": "#/$defs/cal" }, "uniqueItems": true },
    "cal_switch": { "enum": [0, 1], "default": 0 },
    "cal_secondary": { "anyOf": [ { "const": "" }, { "$ref": "#/$defs/cal" } ], "default": "" },
    "cal_hijri_adjust": { "type": "integer", "minimum": -2, "maximum": 2, "default": 0 },
    "cal_locale": { "type": "string", "default": "auto" },
    "cal_digits": { "enum": ["auto","latn","arabext","arab"], "default": "auto" },
    "week_start": { "anyOf": [ { "const": "auto" }, { "type": "integer", "minimum": 1, "maximum": 7 } ], "default": "auto" },
    "date_mode": { "enum": ["single","range","multiple"], "default": "single" },
    "date_min": { "$ref": "#/$defs/limit" }, "date_max": { "$ref": "#/$defs/limit" },
    "disabled_weekdays": { "type": "array", "items": { "type": "integer", "minimum": 1, "maximum": 7 }, "uniqueItems": true },
    "disabled_dates": { "type": "array", "items": { "$ref": "#/$defs/isoOrRange" }, "maxItems": 2000 },
    "enabled_dates": { "type": "array", "items": { "$ref": "#/$defs/isoOrRange" }, "maxItems": 2000 },
    "holiday_sets": { "type": "array", "items": { "type": "string", "pattern": "^[a-z0-9-]{2,40}$" } },
    "holidays_custom": { "type": "array", "items": { "$ref": "#/$defs/holidayRule" }, "maxItems": 500 },
    "holiday_mode": { "enum": ["disable","mark"], "default": "disable" },
    "range_unit": { "enum": ["nights","days"], "default": "nights" },
    "range_min_days": { "type": "integer", "minimum": 0, "default": 0 },
    "range_max_days": { "type": "integer", "minimum": 0, "default": 0 },
    "multi_max": { "type": "integer", "minimum": 0, "default": 0 },
    "time_enabled": { "enum": [0, 1], "default": 0 },
    "time_format": { "enum": ["auto","12","24"], "default": "auto" },
    "time_step": { "type": "integer", "minimum": 1, "maximum": 720, "default": 15 },
    "time_min": { "anyOf": [ { "const": "" }, { "$ref": "#/$defs/hm" } ] },
    "time_max": { "anyOf": [ { "const": "" }, { "$ref": "#/$defs/hm" } ] },
    "time_by_weekday": { "type": "object", "patternProperties": { "^[1-7]$": { "type": "array", "items": { "type": "array", "prefixItems": [ { "$ref": "#/$defs/hm" }, { "$ref": "#/$defs/hm" } ], "minItems": 2, "maxItems": 2 } } }, "additionalProperties": false },
    "age_min": { "anyOf": [ { "const": "" }, { "type": "integer", "minimum": 0, "maximum": 150 } ] },
    "age_max": { "anyOf": [ { "const": "" }, { "type": "integer", "minimum": 0, "maximum": 150 } ] },
    "tz": { "type": "string", "default": "site" },
    "display_format": { "type": "string", "default": "auto" },
    "store_format": { "enum": ["iso","legacy_local"], "default": "iso" },
    "input_mode": { "enum": ["both","picker","typed"], "default": "both" },
    "availability": { "type": "string", "default": "none" }
  }
}
```

Builder save must run this schema (or the equivalent PHP sanitizer, §5) and drop invalid keys.
Unknown keys are kept, per STATE_SCHEMA.md's round-trip rule.

### 7.2 Form schema for the AI site assistant (`GET Emsfb/v1/forms/{id}/schema`)

```json
{
  "efb_schema": 1,
  "form_id": 12, "title": "Room booking", "locale": "fa_IR", "dir": "rtl",
  "steps": [ { "step": 1, "title": "Dates" }, { "step": 2, "title": "Guest" } ],
  "submit": { "how": "in_page", "note": "fill fields through the page APIs, then let the visitor press the submit button" },
  "fields": [
    {
      "id": "chk_in", "type": "advdate", "label": "تاریخ ورود و خروج", "help": "", "step": 1, "required": true,
      "fill": { "api": "EFBCalendar", "call": "EFBCalendar.get('chk_in', 12).setValue(value)" },
      "value_schema": {
        "type": "object",
        "oneOf": [
          { "properties": { "start": { "type": "string", "format": "date" }, "end": { "type": "string", "format": "date" } }, "required": ["start","end"] },
          { "properties": { "cal": { "enum": ["persian","gregory"] }, "start": { "type": "string" }, "end": { "type": "string" } }, "required": ["cal","start","end"] }
        ]
      },
      "x-efb-date": {
        "mode": "range", "calendars": { "default": "persian", "allowed": ["persian","gregory"] },
        "tz": "Asia/Tehran",
        "constraints": { "min": { "type": "relative", "value": 1 }, "max": { "type": "relative", "value": 180 },
                         "disabled_weekdays": [], "range": { "unit": "nights", "min": 1, "max": 14 } },
        "availability_url": "/wp-json/Emsfb/v1/calendar/availability?form_id=12&field_id=chk_in",
        "error_codes": "see calendar-engine-contract.en.md §4.3"
      }
    }
  ]
}
```

The schema gives limits **unresolved** (as configured: "today+1"), so it can be cached. The
assistant gets concrete dates from `availability` or from `EFBCalendar.get(...).getConstraints()`
in the page. Other field types get a `fill` hint only once their own APIs exist. Until then the
schema lists them with `"fill": { "api": "dom" }` and the input's `id`. A general field-fill
API for every type is a separate piece of work (F06 §11.2).

## 8. Security rules

1. Server is authoritative: every rule in §4.1 is re-checked in PHP from the stored field config
   at submit time, with `efb_date_constraints` and the availability filters applied. Client-side
   `setConstraints`/`setAvailability` never weakens what the server accepts.
2. Capacity and remaining counts are not public unless the field opts in (`bk_show_remaining`).
   By default the availability route only says `open`/`full`/`closed`.
3. Login-required forms (`stateForm`) answer 403 to anonymous REST callers, like the rest of the
   public surface.
4. No route returns submission data. Nothing new is fetched by tracking code (the known IDOR on
   `forms/response/get` is not widened).
5. `disp`/`local` are recomputed on the server and escaped on output (`esc_html` in email
   builders and the viewer). Holiday labels and custom labels are admin input: sanitized with
   `sanitize_text_field` on save and escaped on output.
6. Size limits on save: `disabled_dates`/`enabled_dates` ≤ 2000 entries, `holidays_custom` ≤ 500,
   ranges ≤ 3660 days each. The REST window is ≤ 92 days.
7. Booking capacity must be reserved atomically in its own table inside
   `efb_date_before_commit` (for example `UPDATE … SET used = used + 1 WHERE slot = %s AND used < cap`
   and checking `rows_affected`). Today's booking mode increments `registered_count` inside the
   form JSON and rewrites the whole `form_structer` on every submission
   (`class-Emsfb-public.php` ~2926 and ~3472). Two simultaneous submissions can both pass the
   check, and a submission can overwrite an admin's builder save. The add-on must not reuse that
   pattern.

## 9. Versioning and backward compatibility

- `EFBCalendar.version` is semver. Minor = additive (new methods, events, error codes, config
  keys with defaults that keep old behaviour). Major = removal, which needs a deprecation period
  of two minor plugin releases with `console.warn` and a `_doing_it_wrong()` notice.
- Config: `cal_v` on the row. Readers must accept a missing `cal_v` as `1`. A reader that sees a
  higher `cal_v` than it knows renders with the keys it understands and never rewrites the row.
- Value: `v` in the row and in `CanonicalValue`. `value` stays canonical ISO in every version.
- REST: additive fields only inside `Emsfb/v1`. A breaking change gets `Emsfb/v2`.
- Legacy types: `date`, `pdate` and `ardate` keep their type strings, keys (`milen`/`mlen`, `1` =
  today) and stored values, as REDESIGN_HANDOFF.md requires. Upgrading a field is an explicit
  builder action that changes `type` to `advdate`, writes the new keys, keeps `milen`/`mlen` on
  the row (ignored by `advdate`), and sets `store_format: "legacy_local"` for former
  `pdate`/`ardate` fields whose owners keep integrations that expect local strings. Old
  submissions keep their own row `type`, so the viewer still renders them through the legacy
  path. Mapping: F06 §6.4.
- The redesigned builder (`_workspace/easy-form-builder-form-builder`) treats `advdate` like any
  row: `formState.addField({ id_, type: 'advdate', ... })` / `updateField(id, {date_min: ...})`.
  The config keys are plain JSON, so `formSerializer.js` round-trips them with no change. Any
  settings UI the redesign builds reads §7.1 for labels/defaults instead of hard-coding them.

## 10. Verification performed for this contract

Scratch implementations were written in JS and PHP (Borkowski Jalali, tabular Hijri, Umm al-Qura
table, Gregorian JDN) and compared, day by day, with every `Intl`/ICU available. Outputs are
recorded in `calendar-test-vectors.json` → `verification`.

| Check | Result |
|---|---|
| Jalali vs Node ICU 78.3, PHP ICU 74.2, Chromium 153, Firefox 155 — every day AP 1300-01-01..1499-12-29 (73 049 days) | 73 049 / 73 049 in all four; JS ↔ PHP round-trip 100 % |
| Jalali vs Node ICU, AP 1179..1699 | identical through AP 1501; 732 days differ from 2124-03-20 on (outside any practical form range) |
| Jalali leap years 1300..1499: Borkowski vs the plain 33-year rule `(25y+11) mod 33 < 8` | no difference (first difference in AP 1634) |
| `islamic-civil` and `islamic-tbla` vs ICU, every day AH 1300..1599 (106 310 days each) | 106 310 / 106 310 in Node, PHP, Chromium and Firefox |
| Umm al-Qura month starts, table vs ICU | 1318–1419: 226/1224 · 1420–1450: 372/372 · 1451–1500: 389/600 (same in all four ICU builds) |
| Umm al-Qura, .NET table vs hijridate table, every day | 1420–1450: 10 985/10 985 · 1451–1500: 17 719/17 719 · 1343–1419: 17 996/27 287 (historical tables differ) |
| Gregorian JDN vs JS `Date`, 1800..2200 | 146 462 / 146 462 |
| 91 published vectors, JS engine vs PHP engine | 91 / 91 (85 agree with ICU too; the 6 that do not are Umm al-Qura dates before 1420 or after 1450, flagged per vector) |
| WebKit (Safari engine) | not run: the Playwright WebKit build could not start in this container (missing system libraries). **Run `tests/calendar-intl-browsers.js` against WebKit before release.** |
| Browser facts seen | `Intl.supportedValuesOf('calendar')` lists all Tier 1–3 ids in Chromium 153 and Firefox 155 (Firefox lacks `islamic` and `islamic-rgsa`, which we do not use). `new Intl.DateTimeFormat('ar-SA').resolvedOptions().calendar` is `gregory` in both, so "Arabic → Hijri" must come from our locale table, not from `Intl`. `fa-IR` defaults to `persian`. `Intl.Locale('fa-IR').getWeekInfo()` = `{firstDay: 6, weekend: [5]}`. `Temporal` exists in both. |

Sources (accessed 2026-11-10): jalaali-js README (MIT, Borkowski algorithm, "agrees with Intl
1800–2256"); dralshehri/hijridate `ummalqura.py` (MIT, AH 1343–1500); dotnet/runtime
`UmAlQuraCalendar.cs` (MIT, AH 1318–1500); R. H. van Gent, Umm al-Qura converter (Utrecht,
AH 1356–1500); WAI-ARIA APG Date Picker Dialog and Date Picker Combobox examples; Temporal
shipping status (Firefox 139, Chrome 144, TC39 stage 4 in March 2026, Safari not stable).
