# Language packs from WhiteStudio

A plugin ships only the languages its team maintains by hand. Every other
language a site uses is downloaded after activation and kept up to date: from
the **WhiteStudio language server** first, from **wordpress.org** when
WhiteStudio has no pack for that language or cannot be reached. It runs from
WP-Cron, and also from the first page view after an install or update, for
servers where WP-Cron never runs. If neither server works, nothing breaks. The
plugin shows the translations it already has (English, when it has none), and the
site admin gets one email saying what is wrong, how to fix it, and how to install
the files by hand, with a link to the [manual installation guide](manual-install.md).

This document is the whole contract. Hand it to whoever adds the feature to
another WhiteStudio plugin: [Porting](#porting-to-another-plugin) is the
checklist, and everything above it explains why the code behaves as it does.

| Part | Lives in |
| --- | --- |
| the client | [`includes/class-Emsfb-language-packs.php`](../../includes/class-Emsfb-language-packs.php): one file, no dependency on Easy Form Builder |
| its wiring in EFB | `includes/class-Emsfb.php`, next to `Review_Request` |
| the server | the `ws-language-plugins-handler` plugin on whitestudio.team |
| the guide the email links to | [`docs/language-packs/manual-install.md`](manual-install.md) |
| the phrase list and the packs' content | agent `efb-core-i18n`, see [Producing the packs](#producing-the-packs) |
| the tests | [`tests/test-language-packs.php`](../../tests/test-language-packs.php), [`tests/core-i18n/test-core-i18n-tools.php`](../../tests/core-i18n/test-core-i18n-tools.php) |

---

## Rules the client keeps

1. **It never breaks the plugin.** It never uses the network while a page is
   being built: only from WP-Cron, or from a separate `admin-ajax.php` request
   that the browser sends after the page has loaded. Every entry point catches
   `Throwable`, and a missing class file is not counted as a broken install. When
   something fails, the translations already on disk stay exactly as they were.
2. **WhiteStudio first, wordpress.org second, nothing third.**
3. **It never installs executable code from the network.** WordPress
   `include`s `.l10n.php` translation files as PHP, so the client ignores any
   `.l10n.php` in a download and generates its own from the verified `.mo`.
   Script translations (`.json`) are decoded and re-encoded before they are
   written, so one can never close a `<script>` tag.
4. **It only replaces files it can prove it may replace.** A translation made by
   hand (Poedit, Loco Translate…) is never overwritten.
5. **It emails the admin only about problems the admin can fix**: this site
   cannot connect, or cannot write the files. There are two emails. The
   [outage email](#cannot-connect-and-the-email) (WP-Cron) waits 72 hours and
   3 failed attempts. The [install email](#when-the-first-install-fails) goes out
   as soon as a page view's install fails. Each goes out at most once in 30 days
   for the same problem, and one about an outage replaces the other. An error
   answered by the WhiteStudio server itself proves the connection works, so it
   never triggers an email.

## Which languages

Every locale the site uses, from all of these sources:

- the site language (`get_locale()`, `WPLANG`)
- every WordPress language pack installed (`get_available_languages()`)
- every user's own language (the `locale` user meta)
- on a multisite, the network language and every site's language (first 200 sites)
- Polylang, WPML and TranslatePress languages
- the host plugin's own list (`extra_locales`; EFB passes `emsfb_get_active_locales_efb()`)
- the `{prefix}_locales` filter

Then the **bundled locales** are removed: the locales the plugin ships itself.
They are exact locales, not languages. Shipping `fa_IR` says nothing about
`fa_AF`, and shipping `de_DE` says nothing about `de_AT` or `de_DE_formal`:
every other variant is downloaded in the user's own language (user rule
2026-09-16). `en_US` is always bundled.

| Plugin | `bundled_locales` (never downloaded) | Downloaded, for example |
| --- | --- | --- |
| Easy Form Builder | `fa_IR`, `ar`, `en_US`, `de_DE` | `fa_AF`, `ary`, `en_GB`, `en_AU`, `de_AT`, `de_CH`, `de_DE_formal` |
| Social Chat Buttons | `en_US` | everything else |

A site that uses only bundled locales makes **no request at all**: no
catalog, no email.

## Where the files go, and how WordPress loads them

Packs are installed exactly where WordPress installs wordpress.org packs:

```
wp-content/languages/plugins/{text-domain}-{locale}.po
wp-content/languages/plugins/{text-domain}-{locale}.mo
wp-content/languages/plugins/{text-domain}-{locale}.l10n.php   generated locally from the .mo
wp-content/languages/plugins/{text-domain}-{locale}-{md5}.json script translations, if any
```

So:

- **No custom loader.** WordPress's just-in-time loading reads this folder
  first.
- **The files survive plugin updates**, because they live outside the plugin
  folder.
- **Deleting the plugin removes them.** `delete_plugins()` removes every
  translation that has a `.po`, and every pack includes a `.po`.
- **The source is recorded in the files themselves.** A WhiteStudio pack's
  `.po` has `X-Generator: WhiteStudio Language Packs …`, while a wordpress.org
  pack's says `GlotPress/…`. Anything else counts as hand-made. Because the
  marker is in the file, a WhiteStudio pack installed by hand is recognised
  too.
- **An old `.l10n.php` can't hide a new translation.** WordPress prefers the
  `.l10n.php` over the `.mo`, so the client rewrites the `.l10n.php` on every
  install, or deletes it when it can't be generated (WordPress before 6.5).

### WordPress's own translation updates

WordPress updates wordpress.org packs on its own, and the site transient
`update_plugins` lists what it plans to download. The client filters that list
(`site_transient_update_plugins`) and removes offers for this plugin in two
cases:

- a locale whose installed pack came from WhiteStudio, which wordpress.org must
  not overwrite;
- a bundled locale whose file ships in `bundled_dir`, which would be
  downloaded and never used.

Offers for other plugins and other locales are left alone. When WhiteStudio
drops a language, the client installs the wordpress.org pack itself on the next
run, and WordPress's normal updates take over from there.

Trade-off: while a WhiteStudio pack is installed, newer wordpress.org
translations of that locale are ignored. **Publish updated WhiteStudio packs
with every release**, or new strings stay in English on those sites until you
do.

### Bundled files

WordPress prefers `wp-content/languages/plugins/` over a plugin's own
`languages/` folder. A shipped translation would therefore lose to a
wordpress.org pack of the same locale. The client hooks `lang_dir_for_domain`,
which runs when WordPress picks a translation folder, and points bundled
locales at `bundled_dir`, but only when a bundled file for that locale
actually exists. A locale without a shipped file keeps whatever WordPress finds
(today `ar` still uses the wordpress.org pack). Needs WordPress 6.6 or later; on
older versions the wordpress.org pack wins.

## When it runs

| Trigger | What happens |
| --- | --- |
| activation | a single event 30 s later (the activation request itself does nothing) |
| every day | `{prefix}_daily`, first run spread 1–12 h after scheduling so a release doesn't hit the server all at once |
| plugin updated (any route, FTP included) | `upgrader_process_complete`, or the next admin page load, schedules a run |
| site starts using a new language | admin page loads check at most every 10 minutes; a change of the site language schedules at once |
| WhiteStudio unreachable | retries after 1 h, 3 h, 6 h, 12 h, then daily |
| run hit its 20 s budget | continues in a follow-up event |
| a page is shown in a language with no pack, not tried yet for this version | installs at once, see [the first page view](#without-wp-cron-the-first-page-view) |
| a page view, and the plugin was updated or no run completed for 2 days | a background run, at most once an hour |

A lock (an option; it expires after 10 minutes) keeps two runs from
overlapping. On a multisite, state, lock and email are network-wide (the
languages folder is shared), and a daily run skips if another site already
completed one in the last 20 hours.

The client does not write files (and does not contact any server) when
WordPress itself would refuse to install a language pack: `DISALLOW_FILE_MODS`,
a languages folder that is not writable, or a filesystem that needs FTP
credentials.

## Without WP-Cron: the first page view

Some servers never run WP-Cron: `DISABLE_WP_CRON` with no system cron, or a
host that blocks the loopback request WP-Cron needs. So every page view also
asks one question, answered from an autoloaded option with no request and no
file scan beyond one `is_file()`. **What should a page shown in locale L do?**
(`inline_need( L )`, where L is `determine_locale()`):

| Situation | Answer |
| --- | --- |
| L is bundled, `en_US`, or not a well-formed locale | nothing |
| no pack for L on disk, and none was tried for this plugin version | **install** |
| no pack for L, already tried for this version | retry in the background, at most once an hour |
| a pack for L is on disk, but the plugin was updated since the last completed run, or no run completed for 2 days | refresh in the background, at most once an hour |
| anything else | nothing |

"Not tried for this version" covers both a fresh install and the first page
after an update. The installing message appears **once per locale per plugin
version**. Every later attempt is silent. After an update, packs that are
still on disk are refreshed silently, because the page already has a translation.

What the page does with the answer:

| Where | **install** | background / refresh |
| --- | --- | --- |
| an admin screen in `blocking_screens` (EFB: Panel `Emsfb`, Create `Emsfb_create`), user has `capability` | a layer covers the page from its first paint, so nothing typed can be lost: *Installing language files*. When the pack is in, the page **reloads by itself**. | silent beacon |
| any other admin screen, user has `capability` | an admin notice *Easy Form Builder is installing its language files (German (Austria) (de_AT))…*, which becomes *installed* with a *Reload this page* link. No automatic reload, since the user may be typing on that screen. | silent beacon |
| admin screen, user without `capability` | silent beacon | silent beacon |
| front end, a page that enqueued one of `front_script_handles` | silent beacon. **This view shows the form in English**; the next one is translated. | silent beacon |
| front end without the plugin's script | nothing | nothing |

The work never happens inside the page request. The browser starts it after the
page has loaded, with a request to `admin-ajax.php`, and the server makes no
loopback request (the thing that fails on these hosts):

- **`{prefix}_install`**: admin only, nonce plus `capability`. It runs a normal
  sync with L first, then the site's other locales within the 20 s budget. It
  answers `installed`, `nothing`, `not_available` (nobody has a translation:
  *No translation yet*, the plugin stays in English), `failed` (with the
  message below) or `busy` (another run holds the lock; the page asks again every
  3 s). After 90 s the layer says *Still installing*; the run carries on.
  On a failure or a timeout the layer shows what happened and a
  **Continue in English** button that removes it.
- **`{prefix}_background`**: admins and visitors, sent with `navigator.sendBeacon`,
  and no nonce so that cached pages still work. Nothing in the request decides
  what is downloaded: the locale counts only if the site itself uses it (it has
  to be in the site's locale list), and runs are throttled by the option (one an
  hour), so repeated calls cost nothing. The script does not fire from a page
  built more than 30 minutes ago (a cached copy), and fires at most once an hour
  per browser (`localStorage`).

The messages are in English on purpose: they appear exactly when the
translation is missing.

After anything was installed, the client fires `{prefix}_installed` with the
installed locales. **The host plugin must clear its own translation caches
there**, or the reloaded page still shows the old strings. EFB bumps
`emsfb_text_version`.

Bookkeeping lives in the autoloaded option `{prefix}_runtime`: `version` and
`synced_at` of the last completed run, `attempts` (locale → version, time,
outcome, reason; attempts from older versions are dropped), and `bg_at`, the
last background run. Deleting that option makes the next page view a "first
visit" again. `'inline' => false` turns all of this off, leaving only WP-Cron.

### When the first install fails

The failure is sorted into a kind, and only the kinds the admin can fix send
an email:

| Kind | For example | Email |
| --- | --- | --- |
| `unreachable` | cURL/DNS/TLS error, firewall page, `WP_HTTP_BLOCK_EXTERNAL` | **at once** |
| `write` | `DISALLOW_FILE_MODS`, FTP-only filesystem, languages folder not writable | **at once** |
| `server` | the language server answered with an error | no: retried |
| `package` | damaged download | no: retried |
| nobody has a translation | | no: *No translation yet* |

WhiteStudio is asked first, so when it could not be reached, that is the
problem reported, even if wordpress.org then had nothing or failed too. The
same problem is emailed at most once in 30 days. An unreachable email counts as
the outage email and the other way round, so an admin never gets both. The
`{prefix}_send_install_failure_email` filter (`bool, string[] $locales, array
$error, string $origin`) can stop it.

The email is the installation protocol itself: what failed, where it happened,
the exact fix for the kind (allow outgoing HTTPS to the host, remove
`DISALLOW_FILE_MODS`, direct file writes, a writable folder), and the manual
install with a download link per language. For a visitor who could not reach
the server, it reads:

```
Subject: [My Site] Easy Form Builder could not install its language files

Easy Form Builder could not install its translation into German (Austria) (de_AT) on https://example.com/.
This happened when a visitor opened a page with a form; the form was shown in English.
The plugin keeps working normally, in English, until the translation is installed.

What went wrong: the server this website runs on cannot connect to whitestudio.team.
Technical detail: cURL error 28: Connection timed out after 10001 milliseconds
This server could not reach WordPress.org either, so outgoing connections from it are probably blocked in general.

What you can do:
1. Ask your hosting provider to allow outgoing HTTPS connections (port 443) from this server to whitestudio.team. The plugin installs the translation by itself as soon as it can connect.
2. Or install the translation yourself, in a few minutes:
   - Download German (Austria) (de_AT): https://whitestudio.team/wp-json/wl/v1/translations/easy-form-builder/package/de_AT
   - Unzip it and upload the files to wp-content/languages/plugins/ on your server, replacing any file with the same name.
   - Step-by-step guide: https://github.com/hassantafreshi/easy-form-builder/blob/HEAD/docs/language-packs/manual-install.md

You will not receive this email again for the same problem in the next 30 days.
```

From the dashboard, the second line reads *This happened when the plugin was
opened in the WordPress dashboard.* Language names come from WordPress's cached
language list, then PHP `intl`, then a built-in list (*Persian (fa_AF)*), with
no request.

## Decision per language

With the WhiteStudio catalog loaded:

| WhiteStudio has it? | Installed now | Result |
| --- | --- | --- |
| yes | nothing, or a wordpress.org pack | install from WhiteStudio |
| yes | an older WhiteStudio build | install from WhiteStudio (checksum differs, or newer date) |
| yes | the same WhiteStudio build | nothing |
| yes, but the download fails | anything | keep what is installed; if nothing is, try wordpress.org |
| no | a WhiteStudio pack | WhiteStudio dropped it: install from wordpress.org if it has one |
| no | nothing, or a wordpress.org pack | install or update from wordpress.org |
| either | a hand-made translation | nothing, ever |

With WhiteStudio unreachable: installed WhiteStudio packs are kept, hand-made
ones are kept, and everything else goes through wordpress.org as above.

Result codes stored in the state (`last_run.results`): `installed_ws`,
`up_to_date_ws`, `kept_ws`, `installed_wporg`, `up_to_date_wporg`,
`kept_custom`, `not_available`, `wporg_unreachable`, `wporg_server_error`,
`failed_ws:<code>`, `failed_wporg:<code>`.

## "Cannot connect" and the email

Only a response carrying the `X-WS-Language-Handler` header came from the
language server. That header is how the client tells the two cases apart:

| Response | Counts as |
| --- | --- |
| cURL / DNS / TLS error, timeout, `WP_HTTP_BLOCK_EXTERNAL` | **unreachable** |
| any status **without** the header (firewall page, bot challenge, proxy error, CDN "origin down") | **unreachable** |
| a WordPress REST error body (`{"code":"rest_…"}`) without the header, e.g. the server plugin is not deployed yet | server error |
| any error **with** the header | server error |
| 200 with the header and a valid catalog | ok |
| catalog ok, but every package download intercepted | **unreachable** |

A server error resets the outage counter: it proves this site can connect.

The admin (`admin_email`; `site_option admin_email` on a network) is emailed
once all of these hold:

- the outage counter has reached **3 failures**;
- the first failure of this outage was **72 hours** ago or more;
- no email was sent for this outage yet;
- no email was sent in the last **30 days**;
- the `{prefix}_send_unreachable_email` filter doesn't return false.

The email is plain text: the site, the server host, the languages affected,
the time the outage started, the last error, what each language uses in the
meantime (wordpress.org, what was installed before, nothing), whether
wordpress.org was unreachable too, and two ways out: ask the host to allow
outgoing HTTPS to the server, or follow the manual guide. If sending fails, it
is retried on the next runs, at most 3 attempts per outage.

The 72 hours are there because of WhiteStudio's own outages: if the server is
down for an afternoon, every site sees "unreachable" at once, and nobody should
get an email about that.

## The server protocol

Base: `https://whitestudio.team/wp-json/wl/v1`. Public, no authentication:
language packs are free content. Every response under `/translations`,
errors included, carries `X-WS-Language-Handler: <server version>`.

### Catalog: `GET /translations/{product}?version=4.2.0&locales=de_DE,fr_FR`

`locales` is optional (default: all). `version` is informational.

```json
{
  "product": "easy-form-builder",
  "known": true,
  "translations": [
    {
      "type": "plugin",
      "slug": "easy-form-builder",
      "language": "de_DE",
      "version": "4.2.0",
      "updated": "2026-09-16 09:00:00",
      "package": "https://whitestudio.team/wp-json/wl/v1/translations/easy-form-builder/package/de_DE?fp=33cf0f68a7aae4f74008e30ad7c7e87a",
      "sha256": "4b7fd98701fa09d28c262f105b0f9abb20735a2574ae461e3ade1dee86b49cde",
      "size": 1497,
      "autoupdate": true
    }
  ]
}
```

Same shape as `api.wordpress.org/translations/plugins/1.0/`, plus `sha256` and
`size`. An unknown product answers `200` with `"known": false` and no
translations. That is not an error.

The client accepts an entry only if:

- `language` is one it asked for;
- `package` is on the server's own host, over https (http only when the
  configured server URL is http, i.e. a local test server);
- `sha256` is 64 hex characters, since a WhiteStudio entry without a checksum
  is ignored.

wordpress.org entries must point at `downloads.wordpress.org`.

### Package: `GET /translations/{product}/package/{locale}`

Streams a flat zip, never a redirect. It holds `{text-domain}-{locale}.po`
(with the server marker), `.mo`, any `-{md5}.json`, and a `.l10n.php` for people
installing by hand; the client ignores that one. Headers: `Content-Type:
application/zip`, `Content-Length`, `X-Content-SHA256`, `Content-Disposition`.
With `?fp=` matching the current build it's cacheable for a day; without it,
`no-cache`.

`404 {"code":"wslph_not_found"}` means there is no pack for that locale; it's
also the answer for bundled locales. The client verifies size, then SHA-256,
then reads only these top-level entries: `{domain}-{locale}.po`, `.mo` (magic
number checked) and `{domain}-{locale}-{32 hex}.json`. Nested paths and
every other name are ignored. The `.po` and `.mo` are both required. Files are
staged next to their destination and renamed into place: script translations
first, `.mo` next, `.po` last. Until the new `.po` lands, the pack still looks
like the old one, so an interrupted install is simply redone.

## Configuration

```php
\Emsfb\Language_Packs::boot([
    'product'           => 'easy-form-builder',   // required: slug on the server
    'text_domain'       => 'easy-form-builder',   // required
    'version'           => EMSFB_PLUGIN_VERSION,  // required
    'plugin_file'       => EMSFB_PLUGIN_FILE,     // required: activation/deactivation hooks
    'prefix'            => 'emsfb_language_packs', // required: options, events, filters
    'servers'           => [EMSFB_SERVER_URL],    // required: tried in order
    'plugin_name'       => 'Easy Form Builder',   // used in the email
    'wporg_slug'        => 'easy-form-builder',   // '' = no wordpress.org fallback
    'bundled_locales'   => ['fa_IR', 'ar', 'en_US', 'de_DE'], // exact locales
    'bundled_dir'       => EMSFB_PLUGIN_DIRECTORY . 'languages/',
    'guide_url'         => 'https://github.com/hassantafreshi/easy-form-builder/blob/HEAD/docs/language-packs/manual-install.md',
    'extra_locales'     => 'emsfb_get_active_locales_efb', // optional callable
    'languages_dir'     => '',                    // default WP_LANG_DIR/plugins/
    'enabled'           => true,
    'notify'            => true,                  // false = neither email
    'debug'             => false,                 // one error_log line per run
    // The first page view (see "Without WP-Cron"):
    'inline'            => true,                  // false = WP-Cron only
    'capability'        => 'install_languages',   // who sees the installing message
    'blocking_screens'  => ['Emsfb', 'Emsfb_create'], // ?page= values that block and reload
    'front_script_handles' => ['efb-main-js'],    // front pages that use the plugin
]);
```

`boot()` never throws. A broken configuration returns `null`, and the plugin
simply has no language packs.

EFB constants (wp-config.php):

| Constant | Effect |
| --- | --- |
| `EMSFB_LANGUAGE_PACKS_SERVER_URL` | use another server, e.g. `http://127.0.0.1/ws` locally (default `EMSFB_SERVER_URL`) |
| `EMSFB_DISABLE_LANGUAGE_PACKS` | `true` turns every download and the email off |
| `EFB_DEBUG` | logs one line per run |

Filters, all prefixed with `prefix` (`emsfb_language_packs_…` in EFB):

| Filter | Arguments | Use |
| --- | --- | --- |
| `{prefix}_enabled` | `bool` | turn downloads off at runtime |
| `{prefix}_servers` | `string[]` | add a mirror, point at a test server |
| `{prefix}_locales` | `string[] $found, bool $light` | add or remove locales before the bundled ones are dropped |
| `{prefix}_send_unreachable_email` | `bool, array $state` | silence the outage email |
| `{prefix}_send_install_failure_email` | `bool, string[] $locales, array $error, string $origin` | silence the install email |
| `{prefix}_sslverify` | `bool, string $url` | a local server with a self-signed certificate |

Action: `{prefix}_installed` (`string[] $locales`), fired after a run installed
anything, from WP-Cron or a page view. Clear the plugin's own caches there.

State lives in the option `{prefix}_state` (network option on multisite): the
server health, outage counter, email bookkeeping (both emails), one record per
installed pack, the locales and plugin version of the last run, and the last
run's results. `wp option get emsfb_language_packs_state --format=json` is the
first thing to look at, then `{prefix}_runtime` for the page-view attempts.

## Every scenario

| Scenario | What happens |
| --- | --- |
| fresh install, site in German | 30 s after activation, `de_DE` comes from WhiteStudio |
| site only in ar/fa/en | nothing is requested, ever |
| multilingual site (WPML/Polylang/TranslatePress), user languages, multisite | every language is kept up to date |
| a new language added later | picked up on the next admin page load (≤ 10 min) or the daily run |
| plugin updated | a run is scheduled; the packs stay in place meanwhile |
| WhiteStudio has no pack for a language | wordpress.org |
| wordpress.org has none either | no pack, no error, English strings |
| WhiteStudio down for hours | packs kept, retries back off, no email |
| site blocked from WhiteStudio for 72 h+ | one email, then quiet for 30 days |
| server plugin not deployed yet | server error: no install, no email, wordpress.org fallback |
| firewall/bot page instead of the catalog | unreachable |
| catalog loads, downloads blocked | unreachable; installed packs kept |
| corrupt or truncated download | size/checksum refused; installed files untouched |
| package with PHP, `../` paths, other domains | ignored; only the pack's own files are read |
| hand-made translation in the languages folder | never touched |
| old `.l10n.php` next to a new `.mo` | regenerated or removed |
| WordPress wants to update a WhiteStudio pack from wordpress.org | offer removed |
| two runs at once | the second one sees the lock and stops |
| run killed mid-install | staged files are renamed only when all are written; the lock expires |
| `DISALLOW_FILE_MODS`, unwritable folder, FTP-only filesystem | nothing is requested or written |
| `WP_HTTP_BLOCK_EXTERNAL` without the host allowed | unreachable; the email names the error |
| no admin email address, or mail cannot be sent | no email; sending is retried at most 3 times |
| server without WP-Cron, fresh install, admin opens Panel or Create | installing layer, install, automatic reload in the new language |
| server without WP-Cron, admin opens another admin screen first | installing notice, then *installed* and a reload link |
| server without WP-Cron, a visitor is the first to open a form | form in English on that view; installs in the background; later views translated |
| first-view install fails: cannot connect or cannot write | the admin sees why (Panel/Create), *Continue in English*; the admin is emailed at once with the fix and the manual steps |
| first-view install fails on the server's side, or the download is damaged | message on screen, no email; retried in the background hourly |
| nobody has a translation for the language | *No translation yet*, English, no email, not shown again this version |
| plugin updated, packs still on disk | refreshed silently in the background; no message |
| page served from a full-page cache | the beacon stops firing 30 min after the page was built |
| many visitors at once | one run; the others see the lock or the hourly throttle |
| plugin deactivated | schedule removed; packs and state stay |
| plugin deleted | WordPress removes the packs |
| class file missing from an incomplete update | the standard "missing file" notice; the form builder works |

## Porting to another plugin

Example: Social Chat Buttons (`wpscb.php`, text domain `social-chat-buttons`).

1. **Copy the class.** `includes/class-Emsfb-language-packs.php` →
   `includes/class-wpscb-language-packs.php`.
2. **Rename the namespace.** `namespace Emsfb;` → `namespace WPSCB;`. Two
   plugins must never share one: both are active on the same site.
3. **Replace the text domain** of the email and message strings: every
   `'easy-form-builder'` in the file (57, all inside `__()` / `esc_html__()`) →
   `'social-chat-buttons'`. Nothing else in the file is plugin-specific.
4. **Boot it** from the main plugin file, after the plugin's constants:

   ```php
   require_once WPSCB_PLUGIN_DIR . 'includes/class-wpscb-language-packs.php';
   \WPSCB\Language_Packs::boot( array(
       'product'           => 'social-chat-buttons',
       'text_domain'       => 'social-chat-buttons',
       'wporg_slug'        => 'social-chat-buttons',
       'version'           => '1.0.5', // the plugin's real version
       'plugin_file'       => WPSCB_PLUGIN_FILE,
       'plugin_name'       => 'Social Chat Buttons',
       'prefix'            => 'wpscb_language_packs',
       'servers'           => array( 'https://whitestudio.team' ),
       'bundled_locales'   => array( 'en_US' ),
       'bundled_dir'       => WPSCB_PLUGIN_DIR . 'languages/',
       'guide_url'         => 'https://github.com/hassantafreshi/easy-form-builder/blob/HEAD/docs/language-packs/manual-install.md',
       'blocking_screens'  => array( 'wpscb-settings' ), // its own admin page slugs
       'front_script_handles' => array( 'wpscb-widget' ), // the handle of its front-end script
   ) );
   add_action( 'wpscb_language_packs_installed', 'wpscb_flush_caches' ); // if it caches strings
   ```

   `register_activation_hook()` needs the main plugin file, so `boot()` must run
   when that file is loaded, not later on a hook. Use the plugin's real admin
   slugs and script handles: a wrong handle only means visitors never trigger
   an install, while WP-Cron and the admin screens still do.
5. **Match the server.** The product must exist in the server's registry
   (`WSLPH\Products`) with the same `text_domain` and `bundled_locales`.
   `social-chat-buttons` already does.
6. **Publish packs** on the server. Until then, sites get wordpress.org
   translations and no error. To produce them the way EFB does
   ([Producing the packs](#producing-the-packs)), copy `tests/core-i18n/` and the
   two `.claude/agents/efb-core-i18n*.md` files into the other plugin. Then change
   `EFB_CI18N_DOMAIN` in `lib.php`, the defaults `product` (`lib.php`) and
   `main-file` / `core-excludes` (`extract.php`), the locale list, the agent
   names, and the paths in the two agent files.
7. **Test.** Copy `tests/test-language-packs.php`, change the config block and
   the text domain, and run it. It needs no server, and runs its end-to-end part
   only when the local WhiteStudio site answers.

Not covered: translations that are not gettext. Social Chat Buttons' widget
reads its own `lang/*.js` files, and those stay bundled. To move them onto
language packs, switch that script to `wp.i18n` with
`wp_set_script_translations()`. The resulting `{domain}-{locale}-{md5}.json`
files travel in the same packs.

## Operating the server

Packs are folders. The `efb-core-i18n` agent writes them (next section), but you
can also copy one in by hand. For example, `de_AT` for Easy Form Builder:

```
{ABSPATH}source/files/lang/easy-form-builder/de_AT/easy-form-builder-de_AT.po
{ABSPATH}source/files/lang/easy-form-builder/de_AT/easy-form-builder-de_AT.mo
{ABSPATH}source/files/lang/easy-form-builder/de_AT/pack.json   optional: {"version": "4.2.0", "updated": "2026-09-16 10:00:00"}
```

Without `pack.json`, the version comes from the `.po`'s `Project-Id-Version`
and the date from `PO-Revision-Date`. Replacing any file publishes a new build
on the next request. Deleting the folder unpublishes the pack, and the next
catalog request forgets its build. **Tools → Language Packs** on the server
lists every pack, what is wrong with the broken ones, and the download links.
A folder for a bundled locale is never served.

Deploy the server plugin before a client release reaches sites. Nothing breaks
if you don't: sites fall back to wordpress.org and send no email. But no
WhiteStudio pack is installed until the plugin is live.

## Producing the packs

Call the **`efb-core-i18n`** agent once before every release, with no argument.
In that one call it:

1. **Updates the phrase list.** `tests/core-i18n/extract.php` runs
   `wp i18n make-pot` over the core and over the add-ons that still use the
   `easy-form-builder` text domain, then merges the result into
   [`docs/core-i18n/catalog.json`](../core-i18n/catalog.json). New phrases are
   added with the version they first appeared in. A phrase removed from the code
   becomes obsolete but stays in this version's packs, for sites that haven't
   updated yet; the next version drops it. A phrase that comes back is active
   again.
2. **Translates only what changed**, into the 50 locales of
   [`tests/core-i18n/locales.json`](../../tests/core-i18n/locales.json).
   Translation runs in `efb-core-i18n-translator` (Sonnet 5, effort xhigh) and
   follows the same rules as the add-on translator. For each phrase and locale it
   uses, in order: the approved wordpress.org translation, verbatim; the locale's
   published translation; a migrated add-on's identical string; and only then a
   new translation. A variant is translated after the locale it is adapted from.
3. **Checks** every translation (placeholders, tags, entities, whitespace, line
   breaks, plural forms) with `tests/addon-i18n/check-translations.php`, and that
   the built `.mo` and `.l10n.php` load in WordPress.
4. **Publishes** every locale to the WhiteStudio storage
   (`source/files/lang/easy-form-builder/<locale>/`), except `fa_IR` and `de_DE`,
   which ship with the plugin in `languages/`. A locale whose translations did not
   change is not rewritten, so sites don't download the same pack again.

With `extract` as the argument it only updates the phrase list; `locales=de_AT,fr_CA`
limits a run to those locales. An interrupted run continues where it stopped,
because the translation memory is the published packs.

The first full run is long: about 2,500 phrases times 50 locales, minus what
wordpress.org already has approved. After that, a release translates only its new
phrases.

The agent commits nothing. After a run, commit and deploy the ws repo
(`source/files/lang/`) first, then commit the plugin repo (the catalog and the two
bundled packs) with the release.

## Testing

```
C:\xampp\php\php.exe tests/test-language-packs.php
```

Part one mocks the server and wordpress.org and covers every row of the
scenario table that doesn't need a real network. Part two publishes a
throwaway pack on the local WhiteStudio site (`http://127.0.0.1/ws`, override
with `EFB_LP_WS_URL` / `EFB_LP_WS_STORAGE`), installs it over real HTTP, and
removes it again. It skips if that site doesn't answer, or if a real `de_DE`
pack is already published there.

To watch a real site use the local server, add this to its wp-config.php:
`define( 'EMSFB_LANGUAGE_PACKS_SERVER_URL', 'http://127.0.0.1/ws' );`. Then run
`wp cron event run emsfb_language_packs_daily`.

To see the first page view in a browser, put the site in the state of a server
without WP-Cron that has just installed the plugin:

1. add `define( 'DISABLE_WP_CRON', true );` to wp-config.php;
2. set the site language (e.g. `wp option update WPLANG ru_RU`) and delete
   `wp-content/languages/plugins/easy-form-builder-ru_RU.*`;
3. `wp option delete emsfb_language_packs_runtime emsfb_language_packs_lock`
   (also `emsfb_language_packs_state` to forget that an email was already sent);
4. open **Panel**: the layer, then the page reloads in Russian. For the failure
   path, point `EMSFB_LANGUAGE_PACKS_SERVER_URL` at an address that doesn't answer
   and repeat from step 2 with a language wordpress.org has no pack for (e.g.
   `fa_AF`); otherwise the wordpress.org fallback succeeds and nothing fails.

Remove the `DISABLE_WP_CRON` line afterwards.
