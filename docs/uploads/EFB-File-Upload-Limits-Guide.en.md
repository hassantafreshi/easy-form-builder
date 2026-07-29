---
title: "How to Limit File Uploads in Easy Form Builder"
slug: "easy-form-builder-limit-file-uploads"
meta_description: "Control how many files each visitor can upload to your WordPress form, set a maximum file size that is actually enforced, restrict file types, and clean up abandoned uploads - all in Easy Form Builder."
focus_keyphrase: "limit file uploads WordPress form"
secondary_keyphrases:
  - "WordPress form upload limit"
  - "maximum file size WordPress form"
  - "restrict file types form upload"
  - "stop upload spam WordPress"
  - "Easy Form Builder file upload"
search_intent: "Informational and setup guide"
audience: "WordPress site owners and administrators using Easy Form Builder"
product_version: "Easy Form Builder 4.1.3 and later"
last_reviewed: "2026-07-28"
---

# How to Limit the Number of File Uploads in Easy Form Builder

**Keywords:** limit file uploads WordPress form, WordPress form upload limit, maximum file size WordPress form, restrict file types form upload, stop upload spam WordPress, Easy Form Builder file upload, form attachment limit.

**Breadcrumb:** Easy Form Builder Documentation › Uploads › File Upload Limits · Languages: [فارسی](EFB-File-Upload-Limits-Guide.fa.md) | English

A file upload field is one of the most useful things you can put on a form, and also the one thing on a form that writes to your server's disk. This guide explains exactly how Easy Form Builder limits that, what happens automatically without you configuring anything, and how to change each limit when your form needs something different.

> **Documentation scope:** Every number, label, and behaviour in this guide was read directly from the plugin's own code in the current version. Nothing here is estimated or borrowed from another plugin's documentation.

## Quick answer

- Easy Form Builder limits uploads **out of the box**, with no add-on and no setting to switch on.
- Each visitor may upload **3 files for every file upload field on the form**. A one-field form allows 3, a four-field form allows 12.
- The **reply box** on a confirmation-code conversation allows **3 files**.
- The allowance **resets after one hour**.
- Each field's **Max File Size** setting is enforced on the server. Fields with no value set use **20 MB**.
- Files a visitor uploads but never submits are **deleted automatically after 24 hours**.
- To change any of these numbers, use the **File upload budget** section on the **Form Security & Spam Protection** page, or a filter in your theme.

## Table of contents

- [Why a form needs an upload limit at all](#why-a-form-needs-an-upload-limit-at-all)
- [The limit you already have](#the-limit-you-already-have)
- [What the visitor sees when they hit the limit](#what-the-visitor-sees-when-they-hit-the-limit)
- [Limit 1: how many files (the upload budget)](#limit-1-how-many-files-the-upload-budget)
- [Limit 2: how large each file may be](#limit-2-how-large-each-file-may-be)
- [Limit 3: which file types are accepted](#limit-3-which-file-types-are-accepted)
- [Limit 4: uploads per minute from one visitor](#limit-4-uploads-per-minute-from-one-visitor)
- [Automatic cleanup of abandoned uploads](#automatic-cleanup-of-abandoned-uploads)
- [Changing the limits with code](#changing-the-limits-with-code)
- [Choosing the right numbers for your form](#choosing-the-right-numbers-for-your-form)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)

## Why a form needs an upload limit at all

When someone attaches a file to your form, two separate things happen. First the file is uploaded and stored. Then, when the person presses Submit, the form record is saved and points at that file.

Those are two different requests, and nothing guarantees the second one ever happens. A visitor can attach a file and close the tab. More importantly, anyone can repeat the first step on its own, over and over, without ever submitting anything. Without a limit, each repetition writes another file to your server.

That is not a dramatic attack. It is closer to a slow leak: no error appears anywhere, nothing breaks today, and one day the disk is full. The limits in this guide exist to close that leak while staying invisible to people filling in your form honestly.

## The limit you already have

You do not need to install or enable anything. Easy Form Builder calculates an upload budget from your own form.

**The rule: each visitor may upload 3 files for every file upload field on the form.**

The plugin counts these five field types when working out the budget:

| Field type in the builder | Counts toward the budget |
| --- | --- |
| File | Yes |
| Drag & drop file | Yes |
| Audio recorder | Yes |
| Video recorder | Yes |
| Screen recorder | Yes |
| Every other field (text, email, select…) | No |

So:

| Your form | Upload fields | Files one visitor may upload per hour |
| --- | --- | --- |
| Contact form with one attachment | 1 | 3 |
| Job application: CV + cover letter | 2 | 6 |
| Insurance claim: 4 photo fields | 4 | 12 |
| Form with no upload fields | 0 | 3 (floor) |
| Reply box on a tracked conversation | — | 3 |

### Why 3 and not 1?

Because uploading the wrong file is normal. Someone picks last year's CV, notices, removes it, and picks the right one. That is two uploads for one attachment. A limit of exactly one file per field would break that entirely honest behaviour.

Three per field leaves room for a couple of corrections while still meaning that a script trying to write files in a loop stops after a handful instead of running indefinitely.

### How long the limit lasts

The count resets **one hour** after a visitor's first upload. Somebody who genuinely needs to start over can simply come back later, and in practice nobody filling in a form honestly ever notices this limit exists.

### Each visitor is counted separately

The budget is tracked per form session, so:

- Two different visitors never share a budget.
- The same visitor filling in two different forms gets a separate budget for each.
- The reply box has its own budget, separate from any form.

## What the visitor sees when they hit the limit

The person filling in the form gets a plain sentence, not an error code:

> You can attach up to 3 files here. Please remove a file you already added, or wait about 60 minutes before trying again.

The number of files and the number of minutes both reflect your actual settings, so if you raise the limit the message updates itself. The same applies to the other two refusals:

> This file is too large. The maximum size for this field is 8 MB.

> This file type is not accepted here. Please attach one of the file types listed on the field.

These messages are translatable like the rest of the plugin's text.

## Limit 1: how many files (the upload budget)

If the automatic budget does not suit your form, you can set the numbers yourself.

**Where:** WordPress admin → **Easy Form Builder** → **Form Security & Spam Protection** → **File upload budget**.

Every box in that section starts at **0**, which means *"keep the automatic behaviour"*. Nothing changes until you type a number, so installing or enabling the add-on never silently alters how your forms behave.

| Setting | What it does | Leave at 0 to… |
| --- | --- | --- |
| **Uploads allowed per file field** | Replaces the default of 3. Set it to 5 and a two-field form allows 10 uploads. | keep 3 per field |
| **Total uploads per visitor** | A flat total that ignores how many fields the form has. Set 4 and *every* form allows 4. | keep the per-field calculation |
| **Budget resets after (seconds)** | How long before the allowance refreshes. 3600 is one hour, 1800 is thirty minutes. | keep one hour |
| **Maximum file size (MB)** | A site-wide ceiling on every upload. | keep each field's own setting |

If you fill in both **Uploads allowed per file field** and **Total uploads per visitor**, the flat total wins.

> **Note:** These four boxes live on the Form Security & Spam Protection page because that is where the site's other limits are, but the upload budget itself is part of the main plugin and keeps working whether or not that add-on is active. The add-on gives you the controls and the security log; it is not what provides the protection.

## Limit 2: how large each file may be

Every file upload field has its own size limit in the form builder.

**Where:** open your form in the builder, click the file upload field, and find **Max File Size** with **(MB)** next to it. The box accepts a whole number from **1** to **300**.

Underneath it, the builder shows the real ceiling of your hosting, for example *"Your hosting accepts uploads up to 40 MB."* If you type a number larger than that, a red warning appears immediately, because your hosting's PHP configuration always has the final word and a larger figure here would simply cause uploads to fail.

**If you leave Max File Size empty, the limit is 20 MB.**

The order of precedence, from strongest to weakest:

1. **Your hosting's PHP limit** — can only lower the figure, never raise it.
2. **The site-wide ceiling** in File upload budget, if you set one — can only tighten a field, never loosen it.
3. **The field's own Max File Size**.
4. **20 MB**, when nothing else is set.

So a field set to 2 MB stays at 2 MB even if the site-wide ceiling is 50 MB. Tightening always wins.

## Limit 3: which file types are accepted

**Where:** in the form builder, on the file upload field, the **Acceptable file types** dropdown offers:

| Option | Accepts |
| --- | --- |
| **All formats** | everything below, combined |
| **Image** | jpg, jpeg, png, gif, heic, heif |
| **Media** | mp3, wav, ogg, oga, m4a, aac, mp4, webm, mkv, avi, mpeg, mpg, mov |
| **Document** | pdf, doc, docx, dot, dotx, xls, xlsx, ppt, pptx, pptm, txt, rtf, odt, ods, odp |
| **Zip** | zip, rar, 7z, tar, gz, gzip, tgz, bz, bz2, bzip, bzip2, tbz, tbz2, tz, tz2, z |
| **Customize** (drag & drop field) | exactly the extensions you type, comma separated, e.g. `jpg, png, pdf` |

Three things are worth knowing about how this is checked.

**The setting is enforced on the server.** This changed in 4.1.3. Previously only the Customize option was checked server-side, so a field set to Document would still store an image if the file was sent straight to the server rather than through the form. All five options are now enforced.

**The file's contents are inspected, not just its name.** A file called `report.pdf` whose contents are not actually a PDF is refused. This is what stops a harmful file from arriving dressed as a harmless one.

**Narrowing is possible, widening is not.** If you type `pdf, exe` into the Customize box, the PDF works and the EXE does not. Executable and script file types — anything that a web server might try to run rather than serve — are refused on every field regardless of what you configure. That includes PHP in all of its spellings, shell scripts, Windows executables, server configuration files, and HTML or SVG, which can carry scripts that run on your own domain. The same applies to the presets: the Zip list historically included `.jar` and `.war`, and those are refused because they are executable.

### What about the reply box?

The reply box on a confirmation-code conversation is not part of a form, so it has no **Acceptable file types** setting to read. It accepts the same broad range as an **All formats** field — images, documents, archives, audio, and video — and refuses executables and scripts exactly like every other upload path. That is deliberate: it matches the check the reply box already performs in the browser, so tightening the form presets did not silently break replies on existing conversations.

If you do want the reply box restricted, a developer can set it explicitly:

```php
add_filter( 'emsfb_upload_response_box_extensions', function ( $extensions ) {
    return array( 'pdf', 'jpg', 'png' ); // reply box accepts only these
} );
```

## Limit 4: uploads per minute from one visitor

The budget in Limit 1 controls the *total* over an hour. If you also want to control the *rate*, the Form Security & Spam Protection add-on adds a per-minute cap.

**Where:** **Form Security & Spam Protection** → **Uploads per IP/min**. The default is **3**.

The two work together: the per-minute cap flattens bursts, and the budget caps the total. Set the per-minute figure to 0 to switch that particular check off.

## Automatic cleanup of abandoned uploads

Some uploads never become a submission. The visitor attaches a file and then closes the page, or changes their mind, or the payment step fails.

Easy Form Builder keeps a record of every file it stores and removes the ones that were never claimed:

- Files sit for **24 hours** before they are eligible for removal, so a slow form-filler is never affected.
- A file is only removed if it is **not referenced by any submission or reply**. This is checked against the stored records, not assumed.
- Only files the plugin generated itself are ever touched — those whose names begin with `efb-PLG-` or `efb-rec-`. Files from your Media Library, other plugins, or anywhere else are never considered.
- The check runs **once a day** through WordPress's scheduled tasks.

The result is that a visitor who abandons a form costs you nothing permanent, while every file attached to a real submission stays exactly where it is.

## Changing the limits with code

If you prefer configuration in your theme's `functions.php` or a small site plugin, every limit is a WordPress filter. These override the admin settings.

**Give one specific form a bigger budget:**

```php
add_filter( 'emsfb_upload_quota_limit', function ( $limit, $context ) {
    // Form 12 is the insurance claim form; allow 30 photos per visitor.
    if ( (int) $context['form_id'] === 12 ) {
        return 30;
    }
    return $limit;
}, 10, 2 );
```

**Allow more corrections per field, everywhere:**

```php
add_filter( 'emsfb_upload_retry_allowance', function ( $allowance, $context ) {
    return 5; // 5 uploads per file field instead of 3.
}, 10, 2 );
```

**Make the budget reset every 15 minutes instead of every hour:**

```php
add_filter( 'emsfb_upload_quota_window', function ( $seconds, $context ) {
    return 15 * MINUTE_IN_SECONDS;
}, 10, 2 );
```

**Cap every upload on the site at 5 MB:**

```php
add_filter( 'emsfb_upload_max_bytes', function ( $bytes, $context ) {
    return min( $bytes, 5 * 1024 * 1024 );
}, 10, 2 );
```

**Refuse an extra file type site-wide:**

```php
add_filter( 'emsfb_upload_blocked_extensions', function ( $blocked ) {
    $blocked[] = 'iso';
    return $blocked;
} );
```

**Keep abandoned uploads for 3 days instead of 1:**

```php
add_filter( 'emsfb_upload_orphan_ttl', function ( $ttl ) {
    return 3 * DAY_IN_SECONDS;
} );
```

The `$context` array passed to most of these contains `source` (`form` or `response`), `form_id`, `field_id`, and `fields` (the number of upload fields on that form), so you can make any of these decisions per form or per field.

There is also `emsfb_upload_quota_enabled`, which switches the budget off entirely. Think carefully before using it: it removes the only thing standing between your disk and an upload loop.

## Choosing the right numbers for your form

| Your situation | Suggested setting |
| --- | --- |
| Ordinary contact form with one attachment | Leave everything alone. The automatic 3 is right. |
| Job applications (CV + portfolio) | Leave the budget alone; set **Max File Size** per field, e.g. 5 MB for the CV. |
| Photo-heavy claim or inspection form | Raise **Uploads allowed per file field** to 5, or set a **Total uploads per visitor** matching the real maximum. |
| Public form with a history of junk submissions | Lower **Budget resets after** to 1800 and keep **Uploads per IP/min** at 3. |
| Internal form, logged-in staff only | Raise **Total uploads per visitor** generously; the audience is trusted. |
| Tight hosting disk quota | Set a site-wide **Maximum file size** and lower **Uploads allowed per file field** to 2. |

## Troubleshooting

**Real visitors report "You can attach up to N files here".**
Their form probably needs more attachments than its field count suggests, or they are retrying a lot because uploads are failing for another reason. Check the size and type settings on the field first; if they are correct, raise **Uploads allowed per file field**.

**A file is refused as "too large" even though it is under the limit.**
Check the note under **Max File Size** in the builder. If your hosting accepts less than the figure you typed, the hosting limit applies. Ask your host to raise `upload_max_filesize` and `post_max_size`, or lower the field's setting to match.

**A valid file is refused as the wrong type.**
The contents are checked, not just the name. A file that was renamed rather than converted — a `.jpg` renamed to `.pdf`, for example — is refused. Re-export or convert the file properly.

**Uploads worked before and now stop after a few files.**
That is the budget doing its job. If the form legitimately needs more attachments than its field count, raise the setting as described above.

**Files are disappearing from the uploads folder.**
Only files that were uploaded through this plugin, are older than 24 hours, and are not attached to any submission or reply are removed. If a file that *is* attached to a submission has disappeared, that is not this cleanup and is worth reporting.

## FAQ

**Does this require the paid version or an add-on?**
No. The upload budget, the enforced size limit, the file type checking, and the cleanup are all part of the main plugin. The Form Security & Spam Protection add-on only adds the admin controls, the per-minute rate limit, and the security log.

**Will this change how my existing forms behave?**
Only if a visitor tries to upload more than three files per upload field within an hour. Ordinary use is unaffected, and no setting you have already saved is altered.

**Is a visitor's file counted if it was refused?**
No. The budget is only charged for files that were actually stored. Someone who tries three files that are too large has spent none of their allowance.

**Can I switch the limit off?**
Yes, through the `emsfb_upload_quota_enabled` filter, or by setting a very high **Total uploads per visitor**. Neither is recommended on a public form.

**Does the limit apply to the admin dashboard?**
No. Replies you send from the message management dashboard are not subject to the visitor budget.

**Where are the files stored?**
In your normal WordPress uploads folder, with generated names beginning `efb-PLG-` for form attachments and `efb-rec-` for recordings. The original file name is not used, which avoids collisions and stops a crafted name from mattering.
