---
title: "How to Set Up and Customize the Response Box in Easy Form Builder"
slug: "easy-form-builder-response-box-guide-en"
meta_description: "The Easy Form Builder response box settings live under Panel → Settings → Responses & Confirmation. Set it up step by step: the confirmation code finder shortcode, session duration, confirmation code style, the four response box switches, and 13 colors plus fonts matched to your brand."
focus_keyphrase: "WordPress form response box"
secondary_keyphrases:
  - "where are the response box settings"
  - "WordPress form tracking code"
  - "confirmation code finder"
  - "customize form colors WordPress"
  - "form submission follow-up WordPress"
  - "WordPress form reply box"
  - "Easy Form Builder response box"
search_intent: "instructional and configuration guide"
audience: "WordPress site owners and administrators using Easy Form Builder"
product_version: "Easy Form Builder 4.1.2 and later"
last_reviewed: "2026-07-28"
---

# How to Set Up and Customize the Response Box in Easy Form Builder

**Keywords:** WordPress form response box, WordPress form tracking code, form confirmation code, customize form colors WordPress, confirmation code finder, form submission follow-up WordPress, WordPress form reply box, Easy Form Builder response box.

**Path:** Easy Form Builder Documentation › Response Box › Complete Guide · Languages: [فارسی](EFB-Response-Box-Complete-Guide.fa.md) | English

Most forms are one-way. A visitor sends something and the conversation ends there. The response box is where that relationship becomes two-way. Whoever filled in the form receives a confirmation code, comes back with it, sees their own submission, reads your reply, and can write back — all without creating an account.

This guide explains exactly where that page comes from, what every setting does, and how to make it look like the rest of your site.

> **Scope of this document:** every name, number, default value, and behaviour described here was read directly from the plugin's own code in the current version. Nothing here is guesswork and nothing is copied from another plugin's documentation.

## Short answer

- The response box settings are here: **Panel → top menu, click Settings → Responses & Confirmation tab**. They are **global**, not per form.
- To let visitors come back with their code, put the `[Easy_Form_Builder_confirmation_code_finder]` shortcode on a page.
- The confirmation code has **7 styles**, defaulting to `date_en_mix`. A live preview beside the selector shows the result.
- Session duration is adjustable from **1 to 7 days**; the default is **5 days**.
- The response box has **4 switches**: reCAPTCHA, file upload, download button, and require admin login.
- The **Colors & Fonts** section lets you change **13 colors**, the font family, the font size, and add a custom font — this section and the four switches above require the **Pro version**.
- If you leave a color untouched, the plugin emits no extra CSS for it at all.

## Table of contents

- [What the response box actually is](#what-the-response-box-actually-is)
- [Where the settings are](#where-the-settings-are)
- [Step 1: Build the confirmation code finder page](#step-1-build-the-confirmation-code-finder-page)
- [Step 2: Session duration](#step-2-session-duration)
- [Step 3: Confirmation code style](#step-3-confirmation-code-style)
- [Step 4: The four response box switches](#step-4-the-four-response-box-switches)
- [Step 5: Colors and fonts](#step-5-colors-and-fonts)
- [How fonts are loaded](#how-fonts-are-loaded)
- [Adding a custom font](#adding-a-custom-font)
- [What the plugin actually outputs on the front end](#what-the-plugin-actually-outputs-on-the-front-end)
- [Color reference table](#color-reference-table)
- [Choosing the right settings for your site](#choosing-the-right-settings-for-your-site)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)

## What the response box actually is

When someone submits one of your forms, they receive a **confirmation code**. That code is their key to a private page showing only their own submission: the values they filled in, the date it was sent, any reply you have written, and a small editor so they can write back. That whole surface is what the settings call the **response box**.

Visitors reach it in one of two ways:

1. **The confirmation code finder page** — a page you create yourself with a shortcode. The visitor types their code and presses search.
2. **The link inside the notification email** — a link the plugin puts in the email that opens the conversation directly.

Both routes land on the same thing, and both use the same settings and the same color scheme.

## Where the settings are

The response box settings are not in the WordPress dashboard menu; they are inside the **Easy Form Builder panel itself**. The full path in three clicks:

**Panel → top menu, click Settings → Responses & Confirmation tab**

Step by step:

1. **Open the panel.** In the WordPress dashboard sidebar click **Easy Form Builder**, then **Panel**. The direct address of this page is `wp-admin/admin.php?page=Emsfb`.
2. **Click "Settings" in the panel's top menu.** Across the top of the panel page is a horizontal bar with four items: **Forms | Settings | Create | Help**. Click the second one, **Settings**.
3. **Select the "Responses & Confirmation" tab.** When the settings open, a row of tabs appears starting with **General**. **Responses & Confirmation** is the **second** tab and carries a speech-bubble icon.

The full tab order, so you can spot it faster: General · **Responses & Confirmation** · Captchas · Email Settings · Email Template · Localization · Payments · SMS.

> **Common mix-up:** "Settings" in the panel's top menu is not the same as **WordPress Settings** in the dashboard sidebar. Looking for it in the WordPress sidebar will not find it. Clicking it also does not reload the page; it switches the view in place.

**Saving:** at the bottom of the settings page there is one **Save** button with a disk icon, and it is **shared by every tab**. You can make changes across several tabs and save once at the end. But nothing is stored until you press it; leave the page beforehand and your changes are gone.

One detail that wastes a lot of time if you do not know it: these settings are **global**. Unlike the form design, which is separate for every form, what you change here affects the response box of **every** form on the site. If you have ten forms, all ten share one color scheme and one confirmation code style.

The Responses & Confirmation tab has five sections from top to bottom, and we will go through them one at a time.

## Step 1: Build the confirmation code finder page

The first section of the tab is **Confirmation Code Finder**, and it hands you a ready-made shortcode with a copy button:

```text
[Easy_Form_Builder_confirmation_code_finder]
```

Create a new page — something like "Track your request" or "Check status" — and make that shortcode its only content. That is all. The plugin renders a card with a shield icon, the heading **Confirmation Code**, an input for the tracking code, and a search button.

If you later switch on the response box reCAPTCHA, the captcha widget is added to this same page, below the input.

Link the page somewhere visitors will find it later: the footer, the main menu, or the confirmation message you show after a submission.

> The page depends on the shortcode, not on any particular page type. Anywhere WordPress runs shortcodes — a page, a post, or most page builders — will work.

## Step 2: Session duration

The second section is **Session Duration**, a simple selector with options from **1 to 7 days**. The default is **5 days**.

This number controls how long form security tokens stay valid. The plugin's own description is blunt about the trade-off: longer durations give a better user experience but may reduce security.

In practice, think of the balance this way. A smaller number means someone who left a form page open and came back days later may hit an error when they submit. A larger number means tokens stay alive longer. For most sites the 5-day default is the right point and there is no reason to touch it.

## Step 3: Confirmation code style

The third section controls what the code itself looks like. A selector with **7 styles**, and directly underneath it a **live preview** that generates a real sample every time you change the choice.

| Style | Structure | Sample |
| --- | --- | --- |
| `date_num` | Date + number | `260728-54321` |
| `date_local_mix` | Date + (local language, letters, number) | `۲۶۰۷۲۸ک۳ب۹م` |
| `date_local_alpha` | Date + local letters | `260728کبمشپ` |
| `date_en_mix` (default) | Date + (English, letters, number) | `260728A7KQ2` |
| `date_local_num` | Date + local numerals | `۲۶۰۷۲۸-۵۴۳۲۱` |
| `unique_num` | Unique number | `26072854321` |
| `local_mix` | Local letters and digits (11 characters) | `ک۳بم۹پ۷شل۲ت` |

The "local language" styles use the letters and digits of your site's language. On a Persian site that means the confirmation code is built from Persian characters and numerals — far easier for someone who has to read the code off paper or an SMS and type it back.

Choosing between them is almost entirely a user-experience decision:

- If you read codes out **over the phone**, the digit-only styles (`date_num`, `unique_num`, `date_local_num`) cause the fewest misunderstandings.
- If people **copy and paste** the code, the mixed styles are shorter and collide less often.
- If your audience speaks the site's language and **types the code by hand**, the local styles remove the keyboard-switching.

The date portion in the date-prefixed styles is six digits (`YYMMDD`), which lets you tell what day a submission belongs to straight from the code, without opening the record.

## Step 4: The four response box switches

The **Response box** section has four on/off switches. All four require the **Pro version**; in the free version clicking them opens the upgrade dialog.

### Enable Google reCAPTCHA in the response box

**Default: off**

Adds a reCAPTCHA widget to the confirmation code finder page, before the code is looked up.

This switch has one prerequisite: you must already have a **Site Key** saved in the **Captchas** tab — the next tab along from Responses & Confirmation. If you try to switch it on without a key, the plugin flips it back and shows the reCAPTCHA setup error. Get and save the keys first, then come back and switch this on.

Turning it on makes sense when your finder page is public and indexed and you do not want anyone sweeping it by guessing codes.

### Enable file upload in the response box

**Default: on**

Lets the visitor attach a file to their reply, and on the other side, displays files attached to the original submission inside the response box.

There is an important security detail behind this switch: when the plugin is about to show a file in the response box, it checks that the file's address is on **your own site's domain**. If the address points anywhere else it is dropped rather than displayed. That means this switch cannot become a route for injecting an external link into a user's private page.

Switching it off means files are neither displayed nor accepted.

### Enable download button in the response box

**Default: off**

Adds a download button so the visitor can take a copy of their own submission.

For forms where the user will later need a receipt or a record — registrations, orders, support requests — switching it on is worth it and cuts down the "could you resend that?" emails.

### Require admin login to view responses

**Default: off**

This switch affects **admin** links only, not the visitor — and that is where it is usually misread.

The plugin puts a link in the notification emails it sends you that goes straight to the conversation. The behaviour is:

- **Admin logged into WordPress:** always granted access, regardless of this switch.
- **Not logged in, switch on:** the link is blocked and a card is shown saying "It seems that you are the admin of this form. Please log in and try again."
- **Not logged in, switch off:** the link works without logging in.

Switch it on if your admin links might reach a shared device, a team inbox, or an SMS that gets forwarded. Leave it off if you are the only person who sees those emails and convenience matters more.

That warning card shows an extra note if the link came from an older format: the link is old and newer notifications carry updated links. The note is informational and needs nothing from you.

## Step 5: Colors and fonts

The last section of the tab is **Colors & Fonts**, described as "Customize colors and fonts of the response viewer to match your brand", with one button: **Customize Colors**.

This section also requires the **Pro version**. In the free version the button opens the upgrade dialog.

Clicking the button opens a panel with a **live preview** at the top and the controls below it. The preview is a small but genuine rendering of what the visitor sees: the response card with sender name and date, two sample rows of form data, the reply editor with its "Type your reply…" placeholder, the reply button, and beneath it a miniature of the code finder card.

Every change you make is applied to that preview **immediately**. You do not need to save and go look.

### 13 colors in 4 groups

The color controls are organised into four groups:

**Brand** — the four colors with the most visual impact:

- **Primary** `#3644d2` — the dominant color of buttons, icons, and emphasis
- **Primary Dark** `#202a8d` — the far end of button gradients and the shield icon
- **Accent** `#ffc107` — the small markers beside submission metadata
- **Button Text** `#ffffff` — the text color on buttons

**Text** — two colors:

- **Text** `#1a1a2e` — the main text
- **Muted Text** `#657096` — dates, labels, and secondary descriptions

**Backgrounds** — four surfaces:

- **Card Background** `#ffffff` — the main body of the response card
- **Meta Background** `#f6f7fb` — the date and submission info strip
- **Tracker Background** `#ffffff` — the confirmation code finder card
- **Response Area Background** `#f8f9fd` — the area the conversation is displayed in

**Editor** — three colors belonging to the reply box:

- **Editor Background** `#ffffff`
- **Editor Text** `#1a1a2e`
- **Placeholder** `#a0aec0`

Beside each picker the current hex code is printed and updates as you drag.

### Font size

**9 sizes** from 12px to 20px. The default is **15px** (`0.9rem`).

You see the labels in pixels but the stored value is in `rem`. That is deliberate: the whole response box takes its sizing from this one variable and everything else is computed relative to it. Changing this one number scales headings and secondary text proportionally, without anything breaking.

### Reset button

At the bottom of the panel, **Reset to Defaults** returns every color, the font, and the size to their factory values in one click.

### Saving

The color panel has no save button of its own. Every change you make is written into the settings form immediately; to make it permanent you must press the **Save button of the settings page itself**. If you close the panel and leave the page without saving, the changes are lost.

## How fonts are loaded

The **Font Family** selector has one behaviour worth knowing: its list is built **from your site's language**.

The first option is always **Default (Inherit)**, meaning impose no font and let the response box use the theme's own font. For most sites this is the best choice: the page stays consistent and no extra font file is downloaded.

After that, if your WordPress language starts with `fa`, **eight Persian fonts** are added:

| Font | Persian name |
| --- | --- |
| Vazirmatn | وزیرمتن |
| Vazir | وزیر |
| Sahel | ساحل |
| Samim | صمیم |
| Shabnam | شبنم |
| Parastoo | پرستو |
| Gandom | گندم |
| Lalezar | لاله‌زار |

If the language starts with `ar`, **six Arabic fonts** appear instead: Cairo, Tajawal, Noto Sans Arabic, IBM Plex Sans Arabic, Amiri, and Noto Kufi Arabic.

At the end come the general fonts available in every language: System UI, Segoe UI, Helvetica, Inter, Roboto, Open Sans, Tahoma, Georgia (Serif), and Courier (Mono) — and finally the **✦ Custom Font…** option.

An important point: if you pick one of the built-in fonts, the plugin adds that font's CSS file to the page **itself**. You do not need to install or enqueue the font separately. The Persian fonts come from jsDelivr (except Vazirmatn and Lalezar, which are on Google Fonts) and the rest from Google Fonts.

If **Default (Inherit)** is left in place, no font link is added at all.

## Adding a custom font

If your brand font is not in the list, pick the last option in the selector: **✦ Custom Font…**. Choosing it opens two inputs:

- **Font Name** — exactly the name declared as `font-family` in the CSS file.
- **Font URL (CSS/Google Fonts)** — the address of the CSS file, not of a `.woff2` file.

For example, for a font on Google Fonts:

```text
Font Name:  Estedad
Font URL:   https://fonts.googleapis.com/css2?family=Estedad:wght@100..900&display=swap
```

Or for a font you host yourself:

```text
Font Name:  IranSans
Font URL:   https://example.com/wp-content/uploads/fonts/iransans/font-face.css
```

Two small details usually explain a custom font that does not work:

1. **The URL must point at CSS, not at a font file.** What the browser fetches has to contain the `@font-face` rule.
2. **The name must match the one inside the CSS exactly.** If the CSS declares `IRANSans` and you write `IranSans`, the browser will not find it and falls back.

A custom font **takes priority** over the built-in list: if you have entered a URL, the plugin loads that one and does not consult its internal font map.

The name and URL are stored together as a JSON structure, and on output the URL passes through WordPress's `esc_url()`.

## What the plugin actually outputs on the front end

This section is for anyone who wants to know what happens under the hood — or wants to override something in CSS themselves.

Every color and font setting maps to a **CSS custom property**, and the plugin writes them into a `<style>` block on `:root`.

The most important implementation detail is this: **only values that differ from the default are written.** If you have not touched a single color, no extra `<style>` block is produced — not one byte. If you changed only the primary color, only that one variable (plus its derivatives) is written.

**Derived variables:** if you change the primary color from its default, the plugin extracts its RGB components and generates six more variables automatically: three transparency levels for soft backgrounds, the border color, and two shadows (normal and hover). Change one color and the borders and shadows fall in line with your brand on their own, with nothing else to configure.

**Sanitisation:** before writing, the characters `<`, `>`, `&`, `{`, and `}` are stripped from the values so nothing can break out of the style block through a color value.

**Where it applies:** on the code finder page and the conversation page, on the "please log in" warning card, and on login/register form types.

**Where it does not apply:** the response viewer in the **admin dashboard** loads the same CSS file but does not receive these overrides. Your admin panel therefore always keeps the default palette, however much you have changed the front end. That is deliberate, not a bug.

## Color reference table

| Setting in the panel | CSS variable | Default |
| --- | --- | --- |
| Primary | `--efb-resp-primary` | `#3644d2` |
| Primary Dark | `--efb-resp-primary-dark` | `#202a8d` |
| Accent | `--efb-resp-accent` | `#ffc107` |
| Text | `--efb-resp-text` | `#1a1a2e` |
| Muted Text | `--efb-resp-text-muted` | `#657096` |
| Card Background | `--efb-resp-bg-card` | `#ffffff` |
| Meta Background | `--efb-resp-bg-meta` | `#f6f7fb` |
| Tracker Background | `--efb-resp-bg-track` | `#ffffff` |
| Response Area Background | `--efb-resp-bg-resp` | `#f8f9fd` |
| Editor Background | `--efb-resp-bg-editor` | `#ffffff` |
| Editor Text | `--efb-resp-editor-text` | `#1a1a2e` |
| Placeholder | `--efb-resp-editor-ph` | `#a0aec0` |
| Button Text | `--efb-resp-btn-text` | `#ffffff` |
| Font Family | `--efb-resp-font-family` | `inherit` |
| Font Size | `--efb-resp-font-size` | `0.9rem` |

Variables generated from the primary color: `--efb-resp-primary-06`, `--efb-resp-primary-08`, `--efb-resp-primary-10`, `--efb-resp-border`, `--efb-resp-shadow`, `--efb-resp-shadow-hover`.

## Choosing the right settings for your site

| Situation | Suggestion |
| --- | --- |
| Simple contact form on a company site | Leave everything at its default; just match **Primary** to your brand color. |
| Dark theme | Darken **Card Background**, **Response Area Background**, and **Editor Background**, and lighten **Text**; do not forget **Muted Text**. |
| Site with its own brand font | Keep **Default (Inherit)** so the theme's font is used, unless that font is poor for longer text. |
| Persian site on an English-first theme | Pick one of the eight Persian fonts so the response box is readable even if the rest of the site is not. |
| Order tracking or support requests | Switch on **download button** and **file upload**; use a numeric code style so it can be read out over the phone. |
| Public, indexed tracking page | Switch on **reCAPTCHA** and move the code style away from plain date+number to a mixed one. |
| Admin links that reach a shared device | Switch on **require admin login**. |
| Audience that types the code by hand | Pick one of the "local language" styles so nobody has to switch keyboard layout. |

## Troubleshooting

**I changed the colors but the front end looks the same.**
The color panel has no save of its own. After closing it you must press the settings page's own save button. If you did save and it still has not changed, you probably have a page cache plugin; clear the cache.

**The colors changed on the front end but not in the dashboard.**
That is correct and not a bug. The overrides are applied to public output only, and the dashboard response viewer deliberately keeps the default palette.

**My custom font is not being applied.**
Check two things. First, that the URL points at a **CSS** file rather than a `.woff2` or `.ttf`. Second, that the **Font Name** matches the `font-family` declared inside that CSS exactly; capitalisation matters.

**The Persian fonts are not in the list.**
The list is built from the WordPress language. If the site language is not `fa_IR`, the Persian fonts are not shown. Check **Settings › General › Site Language**, or use the custom font option.

**The reCAPTCHA switch will not stay on.**
That switch needs a saved Site Key. Enter and save your reCAPTCHA keys in the **Captchas** tab first (Panel → Settings → Captchas), then come back and switch it on.

**My email link says I have to log in.**
The **require admin login** switch is on and you are not logged into WordPress in that browser. Either log in, or switch it off if those links only ever reach you.

**I changed the colors and now the text is unreadable.**
**Reset to Defaults** in the color panel puts everything back in one click. Watch the contrast pairs: Text against Card Background, Editor Text against Editor Background, and Button Text against Primary.

## FAQ

**Are these settings per form or global?**
Global. You configure them once and they apply to the response box of every form on the site.

**Do I need the Pro version?**
For the four response box switches and the Colors & Fonts section, yes. The confirmation code finder shortcode, the session duration, and the confirmation code style are available in the free version too.

**If I change no colors, does the plugin send extra CSS?**
No. Only values differing from the default are written. Untouched settings produce no extra output.

**Does picking a built-in font slow the site down?**
It adds one font CSS file for the browser to fetch. If that matters to you, keep **Default (Inherit)** so the font the theme already loads is used.

**Can I override the CSS variables in my own theme?**
Yes. The variables are defined on `:root`, so any rule in your theme's CSS with higher specificity will win. If you only want to move one detail, that is cleaner than changing the settings.

**Does changing the confirmation code style affect existing codes?**
No. The style is applied at the moment a code is generated. Codes already issued are untouched and keep working.

**Does session duration mean the tracking code expires after that many days?**
No. That setting governs the validity of form security tokens, not the lifetime of a confirmation code.

**Does the visitor need an account to see their response?**
No. That is the whole point of the response box: the confirmation code takes the place of an account.

**If I enable file upload in the response box, what limits apply?**
The plugin's normal file upload rules. The details are in the [file upload limits guide](../uploads/EFB-File-Upload-Limits-Guide.en.md).
