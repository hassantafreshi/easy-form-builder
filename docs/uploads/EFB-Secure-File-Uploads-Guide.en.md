---
title: "How to Secure File Uploads on a WordPress Form"
slug: "secure-file-uploads-wordpress-form"
meta_description: "Allowing file uploads on a WordPress form is safe when the server checks every file. See what Easy Form Builder blocks automatically and what to set yourself."
focus_keyphrase: "secure file uploads WordPress form"
secondary_keyphrases:
  - "WordPress form file upload security"
  - "prevent malicious file upload WordPress"
  - "block PHP file upload WordPress form"
  - "is it safe to allow file uploads on a website"
  - "WordPress file upload vulnerability"
  - "restrict file types WordPress form"
search_intent: "Informational — a site owner deciding whether an upload field is safe, and how to harden it"
audience: "WordPress site owners, administrators, and agencies using Easy Form Builder"
product_version: "Easy Form Builder 4.1.3 and later"
last_reviewed: "2026-08-17"
---

# How to Secure File Uploads on a WordPress Form

**Keywords:** secure file uploads WordPress form, WordPress form file upload security, prevent malicious file upload WordPress, block PHP file upload WordPress form, is it safe to allow file uploads on a website, WordPress file upload vulnerability, restrict file types WordPress form.

**Breadcrumb:** Easy Form Builder Documentation › Uploads › Secure File Uploads · Languages: English

An upload field is the only part of a form that writes a visitor's file to your server. That is exactly why it is the part attackers look for first. The good news is that a file upload is only dangerous when the server trusts what the browser tells it — and Easy Form Builder does not.

This guide explains, in plain language, what an attacker actually tries, what Easy Form Builder stops on its own with nothing to switch on, and the four settings that are genuinely worth your time.

> **Documentation scope:** Every list, default, limit, and quoted message in this guide was read directly from the plugin's own code — `includes/class-Emsfb-upload-guard.php`, `includes/class-Emsfb-public.php`, and `includes/phrases.php` — in the current version. Nothing is estimated. Labels may read slightly differently on older versions.

## Quick answer

- **Yes, an upload field is safe to use.** Easy Form Builder validates every file on the server, not in the browser, so editing the page or posting directly to the endpoint does not get a file past the checks.
- **Executable file types are refused no matter how the field is configured.** `.php` (and every variant of it), `.phar`, `.exe`, `.sh`, `.bat`, `.jar`, `.py`, `.asp`, `.jsp`, `.htaccess`, and more are on a permanent blocklist.
- **Renaming a file does not work.** The plugin reads the file's actual bytes and refuses it when the contents do not match the extension — a `.php` renamed to `.jpg` is rejected.
- **`.html` and `.svg` are blocked too**, because both can run JavaScript on your site's own domain even though neither is an executable.
- **Every accepted file is renamed** to a random name such as `efb-PLG-260817-1NBGMJ30.zip` before it is stored, so nobody can predict or guess a file's URL.
- **The file is checked a second time when the form is submitted**, so a tampered URL in the submission cannot attach a file that was never validated.
- **Each visitor gets 3 uploads per upload field per hour** by default, and files from abandoned forms are deleted automatically after 24 hours.
- **For per-IP rate limiting and bot detection**, enable the **Form Security & Spam Protection** add-on and set its mode to soft block.

## Table of contents

- [What can actually go wrong with an upload field](#what-can-actually-go-wrong-with-an-upload-field)
- [Layer 1: File types that are never accepted](#layer-1-file-types-that-are-never-accepted)
- [Layer 2: The contents must match the file name](#layer-2-the-contents-must-match-the-file-name)
- [Layer 3: Every file is renamed before it is stored](#layer-3-every-file-is-renamed-before-it-is-stored)
- [Layer 4: The file is re-checked when the form is submitted](#layer-4-the-file-is-re-checked-when-the-form-is-submitted)
- [Layer 5: A budget per visitor](#layer-5-a-budget-per-visitor)
- [Layer 6: Abandoned files are deleted](#layer-6-abandoned-files-are-deleted)
- [Attachments in the Response Box](#attachments-in-the-response-box)
- [The four settings worth your time](#the-four-settings-worth-your-time)
- [Adding rate limiting and bot detection](#adding-rate-limiting-and-bot-detection)
- [One thing to do outside the plugin](#one-thing-to-do-outside-the-plugin)
- [Common errors and how to fix them](#common-errors-and-how-to-fix-them)
- [Checklist](#checklist)
- [Frequently asked questions](#frequently-asked-questions)

## What can actually go wrong with an upload field

It helps to know what you are defending against, because most of it sounds worse than it is.

| The attempt | What the attacker wants | Where it stops |
| --- | --- | --- |
| Upload a `.php` file | Run their own code on your server — a "web shell" | Blocklist (Layer 1) |
| Rename `shell.php` to `photo.jpg` | Slip past an extension check | Content check (Layer 2) |
| Upload an `.html` or `.svg` file | Run JavaScript on your domain to steal sessions | Blocklist (Layer 1) |
| Guess the URL of a private file someone else uploaded | Read documents that are not theirs | Random rename (Layer 3) |
| Submit the form pointing at a file that was never checked | Attach anything they like to a submission | Re-check at submit (Layer 4) |
| Upload thousands of files | Fill your disk | Budget + cleanup (Layers 5 and 6) |

Every one of these is handled on the server. Browser-side checks exist only to give people fast feedback; they are never what protects you.

## Layer 1: File types that are never accepted

Some extensions are refused permanently. This list applies **after** whatever the field is set to accept, so it cannot be widened by a form setting, by a preset, or by a filter that adds file types:

- **PHP in every spelling:** `php`, `php3`–`php8`, `pht`, `phtm`, `phtml`, `phps`, `php-s`, `phar`, `inc`, `hphp`
- **Other server-side languages:** `cgi`, `pl`, `py`, `rb`, `asp`, `aspx`, `ashx`, `asmx`, `jsp`, `jspx`, `cfm`
- **Shells and programs:** `sh`, `bash`, `zsh`, `bat`, `cmd`, `com`, `exe`, `dll`, `msi`, `scr`, `jar`
- **Server configuration:** `htaccess`, `htpasswd`, `user.ini`, `ini`
- **Markup that runs in the browser on your domain:** `shtml`, `shtm`, `stm`, `html`, `htm`, `xhtml`, `xht`, `svg`, `svgz`, `xml`, `xsl`

That last group surprises people. An `.svg` is just an image to most of us, but an SVG file can contain a `<script>` tag, and an `.html` file can contain anything at all. Because they would be served from *your* domain, any script inside them runs with your site's origin — which is enough to read a logged-in visitor's session. Blocking them is not paranoia; it is the standard advice for any site that accepts uploads.

## Layer 2: The contents must match the file name

Blocking extensions alone is not enough, because the obvious next move is to rename the file. So the plugin opens the file and reads its actual bytes, then checks that the real type agrees with the extension.

Each accepted extension is tied to the file types it may legitimately contain. If they disagree, the upload is refused. A PDF whose bytes are really plain text is rejected. A `.jpg` whose bytes are really PHP is rejected. The visitor sees:

> **This file type is not accepted here. Please attach one of the file types listed on the field.**

This is the check that makes the whole feature safe, and it is the one that browser-only validation in other tools tends to miss.

## Layer 3: Every file is renamed before it is stored

An accepted file never keeps the name it arrived with. It is renamed to a random, unguessable name and stored in your WordPress uploads folder, for example:

```
efb-PLG-260817-1NBGMJ30.zip
```

The pattern is a fixed prefix, the date, and eight random characters. Two consequences matter to you:

1. **Nobody can guess someone else's file URL.** If a visitor uploads a passport scan, an attacker cannot find it by trying likely file names.
2. **A booby-trapped file name cannot do anything.** Names crafted to confuse the server — double extensions, path characters, null bytes — are discarded along with the original name.

## Layer 4: The file is re-checked when the form is submitted

Uploading and submitting are two separate requests. The file is sent first, and the form is submitted afterwards carrying the file's URL. That gap is an opportunity, so the plugin closes it: when the form is submitted, the server takes the URL it was given and validates it all over again.

It confirms the URL really points inside your uploads folder, refuses anything containing `..` or path tricks, resolves the real path on disk and confirms it is still inside the uploads folder, confirms the file exists, and then re-runs the size and type checks for that specific field.

In practice this means a submission cannot smuggle in a file from somewhere else on your server, from another site, or from a field with looser rules.

## Layer 5: A budget per visitor

Each visitor gets **3 uploads per upload field, per hour**. A form with one attachment field allows 3; a form with four attachment fields allows 12. The Response Box has a single attachment slot, so its budget is 3. When the budget is used up the visitor sees:

> **You can attach up to 3 files here. Please remove a file you already added, or wait about 60 minutes before trying again.**

The allowance exists because ordinary people do pick the wrong file and upload again. It is deliberately generous enough not to annoy anyone, and small enough that automated abuse from one session stops quickly.

The full explanation of the budget maths, and how to change it, lives in the companion guide: [How to Limit the Number of File Uploads in Easy Form Builder](EFB-File-Upload-Limits-Guide.en.md).

## Layer 6: Abandoned files are deleted

People upload a file and then abandon the form all the time. Those files would otherwise sit on your disk forever. Easy Form Builder records every upload that has not yet been attached to a submission and deletes anything still unclaimed after **24 hours**. Files that belong to a real submission are never touched.

This keeps a slow drip of abandoned uploads from becoming a disk-space problem, with nothing for you to maintain.

## Attachments in the Response Box

The Response Box — where a visitor opens a ticket with a tracking code and replies to you — needs its own rules, because it is not a form field and has no field settings to inherit.

Two things protect it:

- **An attachment is bound to one conversation.** When a file is uploaded for a ticket it is reserved for that ticket for 30 minutes. A different tracking code cannot reuse it, so a visitor cannot attach a file to somebody else's conversation.
- **The visitor must prove they opened the ticket.** A logged-out visitor only receives permission to attach after successfully looking up that exact tracking code, and that permission is tied to their address and expires. Administrators are authorised by their WordPress role instead.

The Response Box accepts the wider "all formats" list rather than a narrow field preset, because it is a support conversation and people send all sorts of things. The permanent blocklist and the content check from Layers 1 and 2 still apply to every one of those files.

## The four settings worth your time

Everything above is automatic. These are the decisions only you can make, in the order they matter.

### 1. Narrow "Acceptable file types" on every upload field

Open the form in the form builder, select the upload field, and set **Acceptable file types** to the narrowest option that still fits the job:

| Option | Accepts |
| --- | --- |
| **Image** | png, jpg, jpeg, gif, heic, heif |
| **Document** | pdf, doc, docx, dot, dotx, xls, xlsx, ppt, pptx, pptm, txt, rtf, odt, ods, odp |
| **Media** | mp3, wav, ogg, oga, webm, m4a, aac, mp4, mkv, avi, mpeg, mpg, mov |
| **Zip** | zip, rar, 7z, tar, gz, gzip, tgz, bz, bz2, bzip, bzip2, tbz, tbz2, tz, tz2, z |
| **All formats** | No narrowing beyond the permanent blocklist |
| **Customize** | Exactly the extensions you list |

If you are collecting CVs, choose **Document**. If you are collecting profile photos, choose **Image**. "All formats" is convenient and it is still protected by the blocklist and the content check — but a narrower list is simply less to think about.

### 2. Set a real Max File Size

Set **Max File Size** on the field to the largest file you genuinely expect. When you leave it empty the limit is 20 MB. Your hosting account's own upload limit still applies on top, and whichever number is smallest wins. Oversized files are refused with:

> **This file is too large. The maximum size for this field is 20 MB.**

A realistic size limit is one of the cheapest protections you have: it caps what a single request can cost you.

### 3. Only ask for files you actually need

The most secure upload field is the one that is not on the form. If a phone number would answer the question, do not ask for a scan. Every file you collect is a file you are then responsible for storing.

### 4. Turn on rate limiting

See the next section.

## Adding rate limiting and bot detection

The protections above are about *what* a file is. They are deliberately not about *how fast* requests arrive, because that is a different job.

If your forms are public and attract automated traffic, the **Form Security & Spam Protection** add-on adds the missing layer. It is one of the Pro add-ons, and it is off until you enable it. Install it from **Easy Form Builder → Add-ons**, open **Easy Form Builder → Form Security & Spam Protection**, and switch on **Enable Human Shield**.

What it adds on top of everything above:

| Setting | Default | What it does |
| --- | --- | --- |
| **Uploads per IP/min** | 3 | Caps how fast one address can upload, independently of the hourly budget |
| **All API requests per IP/min** | 30 | A general backstop across the plugin's endpoints |
| **Total uploads per visitor** | 0 | A fixed total that replaces the automatic per-field budget; 0 keeps the automatic calculation |

One setting decides whether any of it takes effect. **Mode** starts on monitor, and the add-on's own hint says it plainly:

> **Monitor logs only; soft block is recommended for production rollout.**

Monitor mode records what it would have done and lets every request through, which is useful for a week while you watch the log. If you leave it there, nothing is ever blocked. Switch **Mode** to soft block once the log looks sensible.

Form Security & Spam Protection also brings an IP blocklist and a behavioural check that separates a person filling in a form from a script posting to it — neither of which the standard upload protections attempt.

**Worth being clear about:** none of this is DDoS protection. Rate limiting inside WordPress runs after the request has already reached PHP. A genuine flood is stopped at the network level, by a CDN or a firewall in front of your site. Form Security & Spam Protection is for automated abuse and spam, which is what almost every site actually experiences.

## One thing to do outside the plugin

Even though no executable file can reach your uploads folder through Easy Form Builder, it is good practice to stop PHP from ever running there at all — that protects you from *any* plugin or theme that is less careful.

Ask your hosting provider to disable PHP execution in `wp-content/uploads`, or add it to your server configuration. Most managed WordPress hosts do this already; it takes one support ticket to confirm.

## Common errors and how to fix them

| Symptom | Cause | Fix |
| --- | --- | --- |
| "This file type is not accepted here." on a file that looks fine | The extension is not in the field's **Acceptable file types**, or the contents do not match the extension | Widen the field's accepted types, or re-save the file in its real format instead of renaming it |
| A `.svg` logo is refused | SVG is on the permanent blocklist because it can contain scripts | Upload it as `.png`, or add it through the WordPress Media Library where you control the source |
| "This file is too large" although your host allows more | The field's **Max File Size**, or the 20 MB default, is lower than the host limit | Raise **Max File Size** on the field |
| "You can attach up to 3 files here…" during ordinary testing | The hourly budget for that field is used up | Wait for the window to reset, or raise the allowance — see the limits guide |
| A visitor says the file uploaded but the form did not submit | The submission failed a later check, or the upload never finished | Check the message shown under the field; it names the field with the problem |
| Uploaded files pile up in the uploads folder | Files that were never attached to a submission | They are removed automatically after 24 hours; no action needed |

## Checklist

- [ ] Every upload field has **Acceptable file types** set to the narrowest option that fits.
- [ ] Every upload field has a realistic **Max File Size**.
- [ ] You are not collecting files you do not actually need.
- [ ] You have tested the form once and seen a rejected file behave as expected.
- [ ] PHP execution is disabled in `wp-content/uploads` at the server level.
- [ ] For public, high-traffic forms: **Form Security & Spam Protection** is enabled and **Mode** is set to soft block, not left on monitor.
- [ ] You know where submitted files live and who on your team can read them.

## Frequently asked questions

### Is it safe to let visitors upload files to my WordPress form?

Yes. An upload field is safe when the server — not the browser — decides what is acceptable. Easy Form Builder refuses executable file types outright, verifies that a file's contents match its extension, renames every accepted file, and validates the file a second time when the form is submitted.

### Can someone upload a PHP file or a web shell through my form?

No. Every spelling of PHP is on a permanent blocklist, including `php3` to `php8`, `phtml`, `phar`, and `inc`. The blocklist is applied after the field's own settings, so no form configuration can re-admit them.

### What happens if someone renames a .php file to .jpg and uploads it?

It is rejected. The plugin reads the file's actual bytes and requires the real type to agree with the extension, so a renamed file fails the check even though its extension looks harmless.

### Which file types does Easy Form Builder never accept?

Server-side code (`php` and variants, `phar`, `cgi`, `pl`, `py`, `rb`, `asp`, `aspx`, `jsp`, `cfm`), shells and programs (`sh`, `bat`, `cmd`, `exe`, `dll`, `msi`, `scr`, `jar`), server configuration files (`htaccess`, `htpasswd`, `user.ini`, `ini`), and browser-executable markup (`html`, `htm`, `shtml`, `xhtml`, `svg`, `svgz`, `xml`, `xsl`).

### Why are .html and .svg blocked when they are not executable?

Because both can contain JavaScript, and they would be served from your own domain. A script running on your domain can read a logged-in visitor's session, so these formats are treated as unsafe uploads on any site that accepts files from the public.

### Do I need an add-on to get secure file uploads?

No. The blocklist, the content check, the renaming, the re-check at submission, the per-visitor budget, and the automatic cleanup are all built into Easy Form Builder and need nothing switched on. The **Form Security & Spam Protection** add-on adds per-IP rate limiting and bot detection on top.

### Can a visitor attach a file to someone else's ticket in the Response Box?

No. An attachment is reserved for the conversation it was uploaded to, and a different tracking code cannot reuse it. A logged-out visitor also has to look up that exact tracking code before being allowed to attach anything at all.

### What happens to files from forms that were never submitted?

They are deleted automatically. Any upload that has not been attached to a submission is removed after 24 hours. Files belonging to real submissions are never touched.

### How do I stop one visitor from flooding my form with uploads?

The built-in budget already limits each visitor to 3 uploads per upload field per hour. For a per-minute cap and bot detection, enable the **Form Security & Spam Protection** add-on and set **Mode** to soft block — on the default monitor setting it only writes to the log and blocks nothing.

<!-- FAQPage JSON-LD -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Is it safe to let visitors upload files to my WordPress form?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes. An upload field is safe when the server — not the browser — decides what is acceptable. Easy Form Builder refuses executable file types outright, verifies that a file's contents match its extension, renames every accepted file, and validates the file a second time when the form is submitted."
      }
    },
    {
      "@type": "Question",
      "name": "Can someone upload a PHP file or a web shell through my form?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "No. Every spelling of PHP is on a permanent blocklist, including php3 to php8, phtml, phar, and inc. The blocklist is applied after the field's own settings, so no form configuration can re-admit them."
      }
    },
    {
      "@type": "Question",
      "name": "What happens if someone renames a .php file to .jpg and uploads it?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "It is rejected. The plugin reads the file's actual bytes and requires the real type to agree with the extension, so a renamed file fails the check even though its extension looks harmless."
      }
    },
    {
      "@type": "Question",
      "name": "Which file types does Easy Form Builder never accept?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Server-side code (php and variants, phar, cgi, pl, py, rb, asp, aspx, jsp, cfm), shells and programs (sh, bat, cmd, exe, dll, msi, scr, jar), server configuration files (htaccess, htpasswd, user.ini, ini), and browser-executable markup (html, htm, shtml, xhtml, svg, svgz, xml, xsl)."
      }
    },
    {
      "@type": "Question",
      "name": "Why are .html and .svg blocked when they are not executable?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Because both can contain JavaScript, and they would be served from your own domain. A script running on your domain can read a logged-in visitor's session, so these formats are treated as unsafe uploads on any site that accepts files from the public."
      }
    },
    {
      "@type": "Question",
      "name": "Do I need an add-on to get secure file uploads?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "No. The blocklist, the content check, the renaming, the re-check at submission, the per-visitor budget, and the automatic cleanup are all built into Easy Form Builder and need nothing switched on. The Form Security & Spam Protection add-on adds per-IP rate limiting and bot detection on top."
      }
    },
    {
      "@type": "Question",
      "name": "Can a visitor attach a file to someone else's ticket in the Response Box?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "No. An attachment is reserved for the conversation it was uploaded to, and a different tracking code cannot reuse it. A logged-out visitor also has to look up that exact tracking code before being allowed to attach anything at all."
      }
    },
    {
      "@type": "Question",
      "name": "What happens to files from forms that were never submitted?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "They are deleted automatically. Any upload that has not been attached to a submission is removed after 24 hours. Files belonging to real submissions are never touched."
      }
    },
    {
      "@type": "Question",
      "name": "How do I stop one visitor from flooding my form with uploads?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "The built-in budget already limits each visitor to 3 uploads per upload field per hour. For a per-minute cap and bot detection, enable the Form Security & Spam Protection add-on and set Mode to soft block — on the default monitor setting it only writes to the log and blocks nothing."
      }
    }
  ]
}
</script>
