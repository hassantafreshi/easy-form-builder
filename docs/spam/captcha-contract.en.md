# CAPTCHA check outside a form — core contract v1 (F12 / F20 P20)

Stable since 4.3.2 (F12). For add-ons that need a CAPTCHA on a screen that is not an Easy Form Builder form: the
customer portal's sign-in (F20), the tracking-code box, a custom login. The site's CAPTCHA service is used
(Settings › Captchas: reCAPTCHA v2, reCAPTCHA v3, hCaptcha or Cloudflare Turnstile); secrets never leave the server.

## Discovery

```php
if (function_exists('efb_captcha_api')) {
    $api = efb_captcha_api(); // ['version' => 1, 'providers' => [...], 'provider' => 'turnstile', 'ready' => true]
}
```

`ready` is false when the site's service has no site key or no secret: show no widget and do not require one.

## Showing the widget

```php
echo efb_captcha_widget('portal_signin'); // '' when no service is ready; enqueues the service's script and captcha-efb.js
```

- `$context`: `[a-z0-9_]` (other characters are dropped). It names the widget: `data-ctx`, id `efb-captcha-{context}`,
  its live region `efb-captcha-msg-{context}`.
- Markup: a `role="group"` box labelled "Security check" (visually hidden), the widget, a `role="alert"
  aria-live="assertive"` message (expired, failed). reCAPTCHA v3 has no widget: a hidden marker and Google's notice
  ("This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service apply.").
- Language: the page's locale (`fa_IR` → `fa`, `ar` → `ar`, `pt_BR` → `pt-BR`); phones get the compact (hCaptcha) or
  flexible (Turnstile) size.
- Args (optional): `provider` (one of the four), `lang`, `settings` (tests).

## In the browser

```js
// A widget fires "efb:captcha" on itself when solved: event.detail = {ctx, formId: -1, token}
document.addEventListener('efb:captcha', (e) => { if (e.detail.ctx === 'portal_signin') token = e.detail.token; });
// Or ask when sending (a v3 context gets a fresh token from Google here; a widget context answers its last token):
const token = await window.EFBCaptcha.token('portal_signin');
// After a refused answer (a token is spent once):
window.EFBCaptcha.reset(-1);
```

Send the token to your own endpoint; never store or log it.

## Checking the token (server)

```php
$r = efb_captcha_verify($token, 'portal_signin'); // = \Emsfb\Spam::verify_captcha()
if (empty($r['ok'])) { /* refuse: $r['code'] */ }
```

Returns `['ok' => bool, 'code' => string, 'provider' => string, 'score' => float|null, 'spam' => bool]`.

| code | meaning |
|---|---|
| `''` | solved |
| `config` | the service has no secret key (refuse; tell the owner) |
| `missing` | no token / too short (nothing sent to the service) |
| `network` | the service did not answer (fails closed) |
| `invalid_response` | the answer was not JSON (fails closed) |
| `failed` | the service said no |
| `expired` | `timeout-or-duplicate`: the token was spent or is older than its life |
| `hostname` | solved on another host (v3, hCaptcha, Turnstile; filter `efb_captcha_check_hostname`) |
| `action` | v3 / Turnstile token for another action (the core asks for `efb_submit`) |
| `low_score` | v3 score under the site's minimum (outside a form a low score is always a refusal) |

Args: `provider`, `idempotency_key` (Turnstile; a UUID — the core uses the request id so a network retry does not
spend the token twice), `low_score` (`block` default outside forms, `soft` = ok with `spam` true), `settings` (tests).

The request is a POST with a timeout of 8 seconds; the remote IP is not sent. The official test keys of hCaptcha and
Turnstile pass (their dummy hostname is not checked).

## Hooks

- `efb_captcha_verify_result` (`$result, $context, $args`): change the answer for a context.
- `efb_captcha_failed` (action: `$context, $provider, $code`): count refusals (rate limits, logs without tokens).
- `efb_captcha_check_hostname` (`$check, $provider`): false for a site served on several domains.

## Versioning

The function names, arguments and result keys above are frozen for version 1. New codes or keys may be added;
existing ones keep their meaning. `efb_captcha_api()['version']` changes only on an incompatible change.
