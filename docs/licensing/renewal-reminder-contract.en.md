# Licence server contract v1: the expiry date (F21 renewal reminder)

> [Licensing docs](README.md) · Spec: [F21](../feature-gaps/F21-renewal-reminder.fa.md) · Status: the plugin side is
> built (Easy Form Builder 4.3.2, `includes/class-Emsfb-renewal-reminder.php`). **The licence server is not changed
> yet: until it sends the `license` field below, the plugin shows no reminder and sends no reminder e-mail.**

Seven days before a Pro subscription ends, the customer's own site tells the people who run it: a notice on the
plugin's screens and the WordPress dashboard (administrators only) and one e-mail sent by the site itself. After the
end, one "Pro features are paused" e-mail and notice. The plugin cannot know the date today: the licence check
(`POST {EMSFB_LICENSE_SERVER_URL}/wp-json/wl/v1/pro/key`, `includes/functions.php` `make_post_request_efb()`) answers
`r`, `state`, `key` and `pakcage` only. This page is what the licence server (whitestudio.team) has to add.

Owner decisions (2026-11-14 / 2026-11-15) this contract follows: the 30-day reminder is the vendor server's own job
(e-mail to the licence's address); the grace period after the end is the server's (it answers `deactive` when Pro
should stop); the renew link carries a short-lived token, never the activation code; sites in `fa_IR` are one-time
purchases and never contact this server (the plugin checks their code offline), so nothing here applies to them.

## 1. Request (additive)

The body the plugin already sends gets one key:

```json
{ "key": "<activation code>", "info": { "domain": "…", "email": "…", "version_efb": "4.3.2", "…": "…" },
  "caps": ["license_v1"] }
```

`caps` says the plugin understands contract v1. No new personal data is sent. A server that ignores `caps` keeps
working exactly as today.

## 2. Response (additive)

Next to the existing keys, for `state` `active`, `new` and `deactive`:

```json
{
  "r": true, "state": "active", "pakcage": 1,
  "license": {
    "v": 1,
    "expires_at": "2026-11-21T00:00:00Z",
    "server_time": "2026-11-14T09:12:00Z",
    "auto_renew": false,
    "renew_url": "https://whitestudio.team/checkout?renew=rt_6f1c…",
    "lapse_effect": "pause_pro"
  }
}
```

| Field | Required | Exact rule the plugin applies (anything else ⇒ the whole field is ignored: no reminder) |
|---|---|---|
| `v` | yes | the JSON **number** `1`. Another number or a string ⇒ ignored (a future v2 is not misread). |
| `server_time` | yes | ISO-8601 in UTC: `YYYY-MM-DDTHH:MM:SS`, optional fraction, then `Z` or `+00:00`. The plugin's clock: it stores `server_time − site time` and counts days on the server's clock, so a wrong site clock changes nothing. |
| `expires_at` | yes | Same format: the **moment access ends** (the last day shown to the customer is the day of `expires_at − 1 second` in the site's time zone). `null` = a lifetime licence (no reminder ever). Must be between `server_time − 400 days` and `server_time + 5 years`. |
| `auto_renew` | no | JSON `true` / `false`. `true` ⇒ no reminder, no e-mail (Settings shows "Renews automatically on …"). Missing or `null` = `false`. |
| `renew_url` | no | `https://` only; host `whitestudio.team` or `easyformbuilder.ir` (or the host of `EMSFB_SERVER_URL`), optional `www.`; no user/password, no port other than 443; no spaces, quotes or `<>`; ≤ 2048 characters; **must not contain the activation code or its part after `@`** (checked also after URL-decoding twice). A link that breaks any rule is dropped and the fallback (§3) is used. |
| `lapse_effect` | no | `"pause_pro"` (Pro features stop at the end: the global channel, today's behaviour) or `"updates_only"` (only updates, new add-ons and support stop). Missing = `pause_pro`. Any other value ⇒ the field is ignored. |

`state` keeps its meaning. What the server must do around the date:

1. **Renewal** (early or late): from the moment it is paid, answer `state: "active"` with the **new** `expires_at`.
   The plugin asks daily in the last 8 days and "Already renewed? Check again" asks at once, so the reminder goes
   away within minutes of a renewal.
2. **The end**: answer `state: "deactive"` **with the same `license` field** (the "paused" words show the date).
   The server decides the grace: as long as it answers `active` after `expires_at`, Pro stays on and the plugin
   says nothing (no "ended" message before the server says so).
3. **Unknown code**: `notExists` / `invalid_key` as today (the plugin drops any stored date).
4. **renew_url**: a short-lived, single-purpose token that opens the renewal checkout for this licence (suggested:
   valid 30 days, usable to pay only, never to sign in or to reveal the code). The plugin shows it on the button
   of the notice and the e-mail; the e-mail names the host so the administrator can tell it from phishing.
5. **The 30-day reminder** (optional, owner decision): from the vendor server to the licence's e-mail address. The
   site itself sends only the 7-day reminder and the one "paused" e-mail per expiry date.

## 3. Fallback renew link

When `renew_url` is missing or rejected, every Renew button in the plugin (the notice, the e-mail, the card of a
site whose Pro is off, the add-on gate, the licence-suspended e-mail, the add-on download message) opens

```
{EMSFB_SERVER_URL}/checkout?renew=site&d=<md5 of the site's domain>
```

`d` is the part of the activation code before `@`, which anyone can compute from the domain; it is not a secret and
must never be accepted as proof of anything. The checkout should find the licence of that domain and ask the buyer
to sign in. Until 4.3.2 these buttons carried the whole activation code (`?renew=<code>`), which ended up in
forwarded e-mails, browser history and server logs.

## 4. What the plugin does with it

| Stored | Where | Notes |
|---|---|---|
| `emsfb_license_info` | option, not autoloaded | `{v, expires_at, offset, checked_at, auto_renew, renew_url, lapse_effect, last_state, period}`; `period` = first 16 hex of `sha1(code + "|" + expires_at)`. Written only from a real server answer; an answer the plugin makes up itself (fa_IR offline check, a failed connectivity probe) changes nothing. Dropped when the server sends no field, a garbled field, `notExists`, or the code is removed. |
| `emsfb_renew_reminder` | option | `{expires_at, period, t7_sent, t0_sent}`: which e-mail went out for which date. |
| `emsfb_renew_lock_{expires_at}_{t7\|t0}` | options | `add_option()` locks: exactly one e-mail per expiry date even with two cron runs at once, a plan change, or a code entered again. Removed 60 days after the date. |
| `emsfb_license_lapse_cause` | option | why Pro is off: `expired`, `not_found`, `domain`, `outage`. |
| `efb_renew_notice` | user meta | `"{period}:{stage}"` dismissed by that administrator. |

Silence rules: nothing is shown or sent when the last successful answer is 8 days old or more, with `auto_renew`,
for a lifetime licence, on Free / Free Plus, on `fa_IR`, or to anyone without `manage_options`.

## 5. Test it without the real server

`tests/e2e-wp/mu-plugins/efb-e2e-f21.php` answers the licence server on the test site through `pre_http_request`
(`tests/e2e-wp/ctl.php f21-license <days> [auto_renew] [state] [lapse_effect] [missing|garbled|down]`), and
`tests/test-renewal-*.php` cover the parser and the date maths with the vectors of this page. Never use a real
activation code in a test.
