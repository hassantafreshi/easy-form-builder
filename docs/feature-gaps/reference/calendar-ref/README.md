# Reference implementations: calendar engines

`engines.js` and `verify.php` are the JavaScript and pure-PHP (no ext-intl)
twins of the Jalali, Umm al-Qura and tabular Hijri conversions recommended in
`../../F06-advanced-calendar.fa.md` and `../calendar-engine-contract.en.md`.
`intl-generic.js` shows the Intl-based display path.

Check the PHP engine against the committed vectors:

```bash
php verify.php ../calendar-test-vectors.json
```

It prints the comparison summary and writes `results-php.json` next to the
script; do not commit that file. The vectors were cross-checked against Node's
Intl, PHP ext-intl, the official Umm al-Qura tables and .NET's
UmAlQuraCalendar; see section 20 of F06 for the results and known ICU
discrepancies.
