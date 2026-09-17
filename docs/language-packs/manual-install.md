# Installing a plugin's translation by hand

You're probably here because WordPress sent you an email saying that
**Easy Form Builder** (or another WhiteStudio plugin) could not download its
translations. Nothing on your site is broken. The plugin works as before, and
any translation that was already installed is still there. The only thing
missing is the newest translation files, because your server cannot connect to
`whitestudio.team`.

There are two ways to fix it. The first takes one message to your hosting
provider. The second takes about ten minutes and no technical knowledge beyond
uploading files.

- [Option 1: let your server reach WhiteStudio](#option-1-let-your-server-reach-whitestudio)
- [Option 2: install the files yourself](#option-2-install-the-files-yourself)
- [No WhiteStudio translation for your language?](#no-whitestudio-translation-for-your-language)
- [Troubleshooting](#troubleshooting)

---

## Option 1: let your server reach WhiteStudio

Usually a firewall on the hosting server is blocking the connection. Send your
hosting provider this message:

> Please allow outgoing HTTPS connections (port 443) from my website's server
> to **whitestudio.team**. A WordPress plugin on my site downloads its
> translation files from there, and the connection is currently blocked or
> timing out.

If you manage the server yourself, also check `wp-config.php`: if it defines
`WP_HTTP_BLOCK_EXTERNAL`, add the host to the allowed list:

```php
define( 'WP_ACCESSIBLE_HOSTS', 'whitestudio.team, api.wordpress.org, downloads.wordpress.org' );
```

Once the connection works, the plugin downloads the translations by itself
within a day. You don't need to do anything else.

## Option 2: install the files yourself

Your own computer can reach WhiteStudio even when your server can't, so you
download the files in your browser and upload them to the site.

### 1. Find your language code

The email lists them, for example `de_DE`, `fr_FR` or `es_ES`. You can also find
it in WordPress under **Settings → General → Site Language**. The code is the
one WordPress uses, such as `de_DE` for German or `pt_BR` for Brazilian
Portuguese.

A multilingual site (WPML, Polylang, TranslatePress) needs one download per
language. Repeat steps 2–4 for each of them.

### 2. Download the translation

Open this address in your browser, replacing `de_DE` with your language code:

```
https://whitestudio.team/wp-json/wl/v1/translations/easy-form-builder/package/de_DE
```

For another WhiteStudio plugin, use its name instead of `easy-form-builder`:

| Plugin | Name in the address |
| --- | --- |
| Easy Form Builder | `easy-form-builder` |
| Social Chat Buttons | `social-chat-buttons` |

Your browser downloads a zip file, such as `easy-form-builder-de_DE.zip`. If you
see a short message with `wslph_not_found` instead, WhiteStudio has no
translation for that language: go to
[No WhiteStudio translation for your language?](#no-whitestudio-translation-for-your-language)

### 3. Unzip it

Inside are files named like this (a `.json` file may be there too):

```
easy-form-builder-de_DE.po
easy-form-builder-de_DE.mo
easy-form-builder-de_DE.l10n.php
```

Don't rename them. The names are how WordPress finds them.

### 4. Upload the files

Upload **all** of the files into this folder of your site:

```
wp-content/languages/plugins/
```

Use your hosting control panel's **File Manager**, or an FTP program such as
FileZilla. If `languages` or `plugins` doesn't exist yet, create it. If files
with the same names are already there, **replace them**.

### 5. Check it

Clear any cache your site uses (a caching plugin, or your host's cache), then
reload a page of the plugin in the WordPress dashboard. Its texts should now be
in your language.

That's it. When your server can reach WhiteStudio again, the plugin keeps the
translation up to date by itself. WordPress's own translation updates won't
overwrite these files, because they're marked as coming from WhiteStudio.

## No WhiteStudio translation for your language?

Then the translation comes from the WordPress community on wordpress.org.

**If your server can reach wordpress.org**, go to **Dashboard → Updates** and
click **Update Translations**. WordPress installs everything that's available.

**If it can't:**

1. Open `https://translate.wordpress.org/projects/wp-plugins/easy-form-builder/stable/`
   (or the plugin's name in place of `easy-form-builder`).
2. Click your language in the list.
3. At the bottom of the page, set **Export** to **all current** as
   **Portable Object Message Catalog (.po/.pot)** and click **Export**. Do it a
   second time with **Machine Object Message Catalog (.mo)**.
4. Rename the two files to `easy-form-builder-de_DE.po` and
   `easy-form-builder-de_DE.mo`, with your own language code.
5. Upload both to `wp-content/languages/plugins/`. If a file named
   `easy-form-builder-de_DE.l10n.php` is already there, **delete it**:
   WordPress prefers that file, and it holds the old translation.

If wordpress.org has no translation for your language either, the plugin is
shown in English.

## Troubleshooting

**The texts are still in English.**

- Check the file names: plugin name, a hyphen, the exact language code
  (`de_DE`, not `de` or `de-de`), then the extension.
- Check the folder: `wp-content/languages/plugins/`, not the plugin's own
  folder.
- Look for an older `easy-form-builder-de_DE.l10n.php` in that folder. Replace
  it with the one from the zip, or delete it.
- Clear every cache, including an object cache (Redis, Memcached) if your host
  has one.
- The plugin's texts follow **your user's language** (Users → Profile →
  Language), which can differ from the site language.

**I want to undo it.** Delete the files you uploaded. WordPress goes back to
the wordpress.org translation, or to English.

**I don't want automatic downloads or this email.** Add this line to
`wp-config.php`:

```php
define( 'EMSFB_DISABLE_LANGUAGE_PACKS', true );
```

This constant is for Easy Form Builder. Other WhiteStudio plugins have their
own.

**Why did the email only arrive now?** The plugin waits until the connection
has failed repeatedly for three days before it writes to you, so a short
outage never sends an email. You won't get another email about the same
problem.

Still stuck? Contact WhiteStudio support at
[whitestudio.team](https://whitestudio.team) and include the "Last error" line
from the email.
