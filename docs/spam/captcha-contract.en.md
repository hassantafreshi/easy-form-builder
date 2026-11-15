# CAPTCHA in Easy Form Builder — the public API (v1) and the provider contract (v2)

Two contracts live here:

- **The public API, version 1** (stable since 4.3.2, F12 / F20 P20): for add-ons that need a CAPTCHA on a screen that
  is not an Easy Form Builder form (the customer portal's sign-in, a custom login). `efb_captcha_api()`,
  `efb_captcha_widget()`, `efb_captcha_verify()`, `window.EFBCaptcha.token()` / `reset()`. Unchanged by F12b; new codes
  and keys were only added.
- **The provider contract, version 2** (F12b): how a CAPTCHA service plugs into core. Google reCAPTCHA (three modes)
  is in core; Cloudflare Turnstile (`AdnCTS`), hCaptcha (`AdnHCP`), ALTCHA (`AdnALT`), the question / math CAPTCHA
  (`AdnQCP`) and Friendly Captcha (`AdnFRC`) are add-ons on Free Plus and Pro. Spec:
  `docs/feature-gaps/F12b-captcha-providers.fa.md`.

Secrets never leave the server: not in a page, `efb_var`, a REST answer, a form package (F15), AutoPilot or a log.

---

## Part 1 — public API v1

### Discovery

```php
if (function_exists('efb_captcha_api')) {
    $api = efb_captcha_api();
    // ['version' => 1, 'contract' => 2, 'providers' => ['recaptcha_v2', 'recaptcha_v2i', 'recaptcha_v3', 'turnstile', …],
    //  'provider' => 'turnstile', 'ready' => true]
}
```

`providers` lists the services registered and usable on this plan (use `in_array()`; the list grows with add-ons).
`provider` is the site's effective service; `ready` is false when no service can be shown (no keys, add-on gone): show
no widget and do not require one.

### Showing the widget

```php
echo efb_captcha_widget('portal_signin'); // '' when no service is ready; enqueues the service's script and captcha-efb.js
```

- `$context`: `[a-z0-9_]` (other characters are dropped). It is the box's `data-ctx`. The box id is
  `efb-cp-{form id | context}-{n}` (`n` counts the boxes of the request, so two boxes never share an id), its live
  region `{box id}-m`, its label `{box id}-l`.
- Markup: a `role="group"` box labelled "Security check" (visually hidden), the widget, a `role="alert"
  aria-live="assertive"` message (expired, failed). reCAPTCHA v3 has no widget: a hidden marker and, when the badge is
  hidden, Google's notice ("This site is protected by reCAPTCHA and the Google Privacy Policy and Terms of Service
  apply.").
- Language: the page's locale (`fa_IR` → `fa`, `ar` → `ar`, `pt_BR` → `pt-BR`), unless the form sets `cp_lang`;
  narrow boxes (under 304 px, or a phone) get the compact / flexible size of the service.
- Args (optional): `provider` (any registered id), `lang`, `settings` (tests).

### In the browser

```js
// A widget fires "efb:captcha" on itself when solved: event.detail = {ctx, formId: -1, token}
document.addEventListener('efb:captcha', (e) => { if (e.detail.ctx === 'portal_signin') token = e.detail.token; });
// Or ask when sending (an invisible service gets a fresh token here; a visible widget answers its last token):
const token = await window.EFBCaptcha.token('portal_signin');
// After a refused answer (a token is spent once): reset that context's box only.
window.EFBCaptcha.reset(-1, 'portal_signin');
```

Send the token to your own endpoint; never store or log it.

### Checking the token (server)

```php
$r = efb_captcha_verify($token, 'portal_signin'); // = \Emsfb\Spam::verify_captcha()
if (empty($r['ok'])) { /* refuse: $r['code'] */ }
```

Returns `['ok' => bool, 'code' => string, 'provider' => string, 'score' => float|null, 'spam' => bool]`.

| code | meaning |
|---|---|
| `''` | solved |
| `config` | the service has no keys (refuse; tell the owner) |
| `missing` | no token / too short or too long (nothing sent to the service) |
| `network` | the service did not answer in 8 seconds (fails closed) |
| `invalid_response` | the answer was not JSON, or not the service's shape (fails closed) |
| `failed` | the service said no |
| `expired` | the token was spent or is older than its life (`token_ttl` + 60 s) |
| `replay` | this token was already accepted once (another request id) |
| `hostname` | solved on another host (every service but reCAPTCHA v2; filter `efb_captcha_check_hostname`) |
| `action` | a token for another action (v3, Turnstile; core asks for `efb_submit`, login `efb_login` when set) |
| `low_score` | v3 score under the minimum (outside a form a low score is always a refusal) |
| `challenge` | v3 score too low on a form with `rc3_fallback`: show the v2 checkbox (forms only) |

Args: `provider`, `request_id` / `idempotency_key` (a UUID; a network retry with the same id is not a replay), `kind`
(`v2`: the checkbox of a v3 form with the fallback), `action`, `low_score` (`block` default outside forms, `soft` = ok
with `spam` true), `settings` (tests).

The request is a POST with a timeout of 8 seconds and no redirects, only to the service's own verify host; the remote
IP is never sent. The official test keys of the services pass (their dummy hostname is not checked).

---

## Part 2 — provider contract v2

### Registering

```php
add_action('efb_register_captcha_providers', function ($registry) {
    require_once __DIR__ . '/class-my-provider.php';
    $registry->register(new My_Provider()); // true, or false with a reason in Registry::errors()
});
```

An add-on is loaded by core's add-on catalogue (loader `routes`: `vendor/<dir>/routes-efb.php`, contract
`captcha_provider` version 2 in `efb_addon_contract_versions`) only when its flag is on and the plan allows it.
`register()` refuses: an id outside `^[a-z][a-z0-9_]{2,23}$`, a `recaptcha_*` id from an add-on, a duplicate, an add-on
code core's catalogue does not know, and a class whose `const API` is not 2 (`addon_too_old` / `core_too_old`: the
owner sees "update the add-on" / "update Easy Form Builder" and the forms fall back, §5).

### The class

`namespace Emsfb\Captcha; class My_Provider extends Provider { const API = 2; … }`

| method | returns |
|---|---|
| `id()` | the provider id stored in forms (`captcha_provider`) |
| `label()` | the service's name (not translated) |
| `addon_key()` | the add-on code (`AdnXXX`); null only for core |
| `plan()` | `free` · `free_plus` (default) · `pro` |
| `kind()` | `widget` (visible), `invisible` (asked on send), `endpoint` (own widget that talks to a REST route of the add-on) |
| `token_ttl()` | seconds a token lives (default 120) |
| `has_action()` / `action($s0, $ctx)` | whether the service echoes an action, and the one expected |
| `settings_schema()` | site settings: `[{key, type: text|secret|select|number|textarea|toggle, label_key, label, required, public, pattern, values, min, max, default, help_key, help}]` — `secret` fields are write-only |
| `form_options_schema()` | per-form options, keys `cp_*` (and `rc3_*` for Google), same field shape plus `group` |
| `ready($row)` | every required setting is present |
| `widget($ctx)` | `['config' => [k, theme, size, lang, align, …], 'class' => '', 'after_html' => '']`; `$ctx`: form (row 0), fid, context, settings, lang |
| `scripts($ctx)` | `[['handle', 'src', 'adapter', 'ver', 'replace']]`: the service's script (https) and the adapter |
| `verify($token, $ctx)` | `['ok', 'code', 'hostname', 'action', 'ts', 'score', 'codes', 'test']` — only the service's answer; core adds the common checks |
| `verify_hosts()` / `csp_hosts()` | hosts `\Emsfb\Captcha::http_post()` may call / the page needs |
| `privacy()` | `['sends' => […], 'where' => '…', 'cookies' => bool]` for the settings screen and the privacy guide |
| `test_keys()`, `is_test_secret()` | the service's public test keys |
| `rest_routes($ns)` | an `endpoint` provider's challenge route (no-store) |
| `lang_map($wp_locale)` | the service's language code |

Helpers: `$this->setting($key, $row)` (secrets decrypted), `$this->opt($s0, $key)` (a form option or its default),
`Provider::by_schema($field, $value)` (the sanitizer core and AutoPilot share), `\Emsfb\Captcha::http_post($p, $url,
$body, $headers)` → `['json' => …]` or `['ok' => false, 'code' => 'network'|'invalid_response']`.

### What core does around `verify()`

1. Not ready → `config`; token under 20 or over 8,192 characters → `missing` (nothing sent).
2. The provider's `verify()`.
3. Hostname (unless the provider says `test`): the answer's hostname must be this site's (`www.` ignored); reCAPTCHA v2
   is not checked by default; filter `efb_captcha_check_hostname` for a site on several domains.
4. Action: `has_action()` and an action in the answer → it must be `action($s0, $ctx)`.
5. Age: `ts` older than `token_ttl() + 60` → `expired`.
6. Replay: `Rate_Limit::reserve('cptk', sha256(token))` plus a request-id transient: the same token is accepted once;
   the same token with the same request id (a network retry) passes again.

### Settings storage

- Google: the site settings row (`siteKey` / `secretKey`, `rciSiteKey` / `rciSecretKey`, `rc3SiteKey` /
  `rc3SecretKey`, `rc_host`).
- Add-ons: option `emsfb_cpv_<id>` = `['public' => […], 'secret' => […]]`, secrets encrypted (`enc:v1:`, the payments
  secret box); the public values of every add-on also in `emsfb_cpv_public` (autoloaded, for rendering).
- Screens get a secret as `{"set": true, "last4": "…"}` (last4 only for secrets of 16+ characters). On save `''`, the
  mask or that object keeps the stored value; `'__clear__'` removes it. Filter `efb_secret_setting_keys` adds keys of
  the settings row that must be treated as secrets.
- Uninstall: removing an add-on keeps its settings (its public map entry is dropped, action `efb_addon_removed`); the
  provider's static `uninstall()` deletes them.

### Which provider protects a form (`Registry::effective_for`)

The form's `captcha_provider`, else the site's `captcha_provider`. When that service is not usable (add-on not
installed or switched off, plan, keys, contract) the site's Google mode protects the form if it is ready, else nobody
does and the soft checks are forced on (hidden field, at least 3 seconds; never on login). Administrators see why on the
page (`efb-cp-admin-note`) and on EFB screens (a notice listing the forms, dismissed until the list changes). The
filter `efb_captcha_active_for_form` (`$provider, ['form_id', 'form']`) may change the answer for a form.

### Browser adapter

```js
window.EFBCaptcha.provider('my_id', {
  ready()  { return !!window.MyService; },             // the service's script is there
  render(el, cfg, cb) { return MyService.render(el, {   // cfg: k, theme, dark, size, narrow, lang, action, ex, …
    sitekey: cfg.k, callback: cb.solved, 'expired-callback': cb.expired, 'error-callback': cb.error }); },
  execute(id, cfg, el) {},                             // invisible / execute mode: start the check
  reset(id, cfg, el) {},                               // after a refused answer
  remove(id) {}                                        // optional
});
```

- One box, widget and token per box; boxes render when they come into view (IntersectionObserver, 200 px margin), in a
  popup or a tab when they appear (MutationObserver, `elementor/popup/show`), and on demand when sending.
- A form drawn after the footer: the box's config carries `src` and `ad`; after a short wait the runtime adds those
  scripts itself (https, or this site's own adapter), once per page.
- `EFBCaptcha.needsWidgetToken(fid)`: the visitor must solve a visible widget first (`kind` widget / endpoint, unless
  the config says `ex: 1`); otherwise `beforeSend()` asks for a token right before sending (10-second limit).
- `EFBCaptcha.otpToken(fid, action)`: an invisible box's token for another request of the form (the F02 send-code
  request asks for `efb_otp`; reCAPTCHA v2 invisible is reset after it, so the form's own send gets a fresh one); `''`
  for a visible widget or no box. The server (`Verification::otp_captcha()`) asks for it only when the form's effective
  service is invisible (reCAPTCHA v2 invisible, v3): a low v3 score is refused (`efb_captcha_low_score`), never "spam".
- `EFBCaptcha.challenge(fid)`: the v3 → v2 checkbox in the same box (the server answered `efb_captcha_challenge`; the
  next send carries `captcha_kind=v2`).

### Hooks

| Hook | Type | Use |
|---|---|---|
| `efb_register_captcha_providers` | action `($registry)` | register a provider |
| `efb_captcha_providers` | filter `($list)` | the registered list (remove one on a site) |
| `efb_captcha_active_for_form` | filter `($provider, $args)` | the provider protecting a form |
| `efb_captcha_verify_result` | filter `($result, $context, $args)` | change the answer for a context |
| `efb_captcha_failed` | action `($context, $provider, $code)` | count refusals (rate limits, logs without tokens) |
| `efb_captcha_check_hostname` | filter `($check, $provider)` | false for a site served on several domains |
| `efb_reply_rate_limits` | filter `(['ip' => [600, 10], 'track' => [3600, 30]])` | the reply box's limits (D3) |
| `efb_secret_setting_keys` | filter `($keys)` | extra write-only keys of the settings row (D4) |

### Versioning

The public API's names, arguments and result keys are frozen for version 1; new codes or keys may be added.
`efb_captcha_api()['contract']` is the provider contract (2); a provider whose `API` differs is refused with a notice,
never loaded half-way.
