---
title: "How to Use the Email Template Builder in Easy Form Builder: 13 Blocks, 6 Templates, and Dynamic Shortcodes"
slug: "easy-form-builder-email-template-builder-guide-en"
meta_description: "Learn the Easy Form Builder email template builder step by step: where to find it, all 13 block types, the 6 ready-made templates, the 5 dynamic shortcodes, global font and color settings, the 50,000-character save limit, and exactly which emails your design applies to."
focus_keyphrase: "WordPress form email template"
secondary_keyphrases:
  - "design form notification email"
  - "Easy Form Builder email shortcode"
  - "build HTML email template WordPress"
  - "Easy Form Builder email blocks"
  - "WordPress registration confirmation email template"
search_intent: "instructional and configuration guide"
audience: "WordPress site owners and administrators using Easy Form Builder"
product_version: "Easy Form Builder 4.1.2 and later"
last_reviewed: "2026-07-29"
---

# How to Use the Email Template Builder in Easy Form Builder

**Keywords:** WordPress form email template, design form notification email, Easy Form Builder email shortcode, build HTML email template WordPress, Easy Form Builder email blocks, WordPress registration confirmation email template.

**Path:** Easy Form Builder Documentation › Email Template Builder › Complete Guide · Languages: [فارسی](EFB-Email-Template-Builder-Complete-Guide.fa.md) | English

Every email Easy Form Builder sends — a new-message notification, a registration welcome, a password reset — passes through one shared template. The **Email Template Builder** is where you design what that shared template looks like, by dragging and dropping blocks, without writing a line of code.

This guide describes exactly what is implemented in the plugin's code: what options each block has, where each shortcode gets replaced, where the limits are, and which emails your design actually applies to.

> **Scope of this document:** every block, template, shortcode, number, and behaviour described here was read directly from the plugin's own code in the current version — including cross-checking every number on both the client side (JavaScript) and the server side (PHP) to confirm they agree. Nothing here is guesswork.

## Short answer

- Path: **Panel → top menu, Settings → Email Template tab** (5th of 8 tabs). Full navigation detail is in the [Response Box guide](../responsebox/EFB-Response-Box-Complete-Guide.en.md#where-the-settings-are) since both tabs live on the same page.
- This setting is **global** too: one template for every form on the site.
- **13 block types** across four categories: Layout, Content, Shortcode, Advanced.
- **6 ready-made templates** to start from: Blank, Professional (the default), Modern Dark, Minimal Clean, Elegant, Colorful.
- **5 dynamic shortcodes**; only `shortcode_message` is required.
- No image upload — the Image and Logo blocks take a **URL** only.
- Save limit: **50,000 characters** maximum, and `shortcode_message` must be present.
- If you never open this tab, your site's emails use the plugin's original built-in design — not a blank template.
- Your design only shows up in **actual outgoing email**; the "Check Email Server" tool on the Email Settings tab does not use your template.

## Table of contents

- [Where the builder is and how it opens](#where-the-builder-is-and-how-it-opens)
- [The builder's layout](#the-builders-layout)
- [The 13 block types](#the-13-block-types)
- [The 6 ready-made templates](#the-6-ready-made-templates)
- [The 5 dynamic shortcodes](#the-5-dynamic-shortcodes)
- [Global settings](#global-settings)
- [Toolbar and keyboard shortcuts](#toolbar-and-keyboard-shortcuts)
- [Limits and validation on save](#limits-and-validation-on-save)
- [Exactly which emails this design applies to](#exactly-which-emails-this-design-applies-to)
- [If you never open this tab](#if-you-never-open-this-tab)
- [Security: what gets stripped](#security-what-gets-stripped)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)

## Where the builder is and how it opens

Path: **Panel → top menu of the panel, Settings → Email Template tab**.

The full order of the eight settings tabs, so you can find it faster: General · Responses & Confirmation · Captchas · Email Settings · **Email Template** · Localization · Payments · SMS Configuration.

The first time you open this tab, the canvas is not empty — the builder automatically loads the **Professional** template into it so you are not staring at a blank page. But that is only a starting point inside the editor, **not an autosave**; the [If you never open this tab](#if-you-never-open-this-tab) section below explains why that distinction matters.

Like every other settings tab, the **Save** button at the bottom of the page is shared across all tabs; changes on this tab are not permanent until you press it.

## The builder's layout

The builder is a three-column workspace, similar to modern page-builder editors:

- **Top toolbar:** Undo, Redo, Preview, Export, raw HTML code editor, and Reset.
- **Left column:** three internal tabs — **Blocks** (the draggable block palette), **Templates** (the 6 ready-made templates), and **Settings** (global color and font settings).
- **Center column (canvas):** the email you are building; drag blocks here from the left column, or click a block to add it to the end.
- **Right column:** the Properties panel — selecting any block on the canvas shows that block's editable fields here.

Every block on the canvas has four small buttons: move up, move down, duplicate, delete. The three-column layout is responsive down to roughly 900px wide; for serious template work, use a desktop-width browser window.

## The 13 block types

The block palette groups blocks into four categories:

### Layout (5 blocks)

| Block | Purpose | Editable properties |
| --- | --- | --- |
| **Header** | The container at the top of the email; by default holds a logo and a title inside it | Background color, custom CSS background (for gradients), padding, alignment; its children (logo/title) are editable from the same panel |
| **Divider** | A thin horizontal rule | Color, thickness (1–10px), width (10–100%), padding |
| **Spacer** | Empty vertical space | Height (5–100px), background color |
| **Two Columns** | Left and right text side by side | Content of each column (with shortcodes), text color per column, font, font size, gap between columns, background, padding |
| **Footer** | The text at the bottom of the email | Text (with shortcodes), text color, background, font, size, alignment, padding |

> **RTL note:** the Two Columns block is always a fixed "left column" and "right column" — it does not automatically mirror for Persian/Arabic sites. If you want a right-to-left reading order, arrange the content of each column yourself.

### Content (6 blocks)

| Block | Purpose | Editable properties |
| --- | --- | --- |
| **Logo** | Your site's logo image | Image URL, width in px, alt text, alignment |
| **Title** | The email's large heading | Text (with shortcodes), color, font, size, font weight (300–800), alignment |
| **Text Block** | A free-form paragraph | Text (with shortcodes), color, font, size, line height (1–3), alignment, padding |
| **Button** | A call-to-action | Text, link URL (both with shortcodes), background color, text color, border radius (0–50px), inner and outer padding, font, size, alignment |
| **Image** | An arbitrary picture | Image URL, alt text, width (% or px), alignment, padding, optional link URL |
| **Social Links** | A row of social-network icons | Alignment, icon color, icon size (16–48px), padding; per link: choose from **21 built-in icons** (Facebook, X, Instagram, LinkedIn, YouTube, TikTok, WhatsApp, Telegram, Pinterest, Snapchat, GitHub, Dribbble, Reddit, Discord, Twitch, Medium, Spotify, Behance, Vimeo, Website, Email) or a **custom SVG** code, plus the link URL |

### Shortcode (1 block, required)

| Block | Purpose |
| --- | --- |
| **Message Content \*** | Outputs `shortcode_message` — the actual submitted form data. This block carries the required asterisk; without it, neither preview nor save will work |

### Advanced (1 block)

| Block | Purpose |
| --- | --- |
| **Custom HTML** | Your own HTML for anything the built-in blocks don't cover; `<script>` tags are not allowed inside it |

Clicking any block in the palette also adds it to the end of the canvas — dragging is not required.

**About image URLs:** the Logo and Image blocks have no "upload" button and no connection to the WordPress media library — just a text field for a URL. To use an image, upload it somewhere first (the WordPress media library, for instance), copy its address, and paste that address into the field.

## The 6 ready-made templates

The **Templates** tab in the left column offers six ready-made starting points. Clicking one replaces the current canvas (this action is also recorded in the undo history, so Ctrl+Z recovers from an accidental click):

| Template | Block count | Look and feel |
| --- | --- | --- |
| **Blank** | 1 block | Just the Message Content block; start from nothing |
| **Professional** | 5 blocks | Purple-blue header with logo and title, message, light footer with site name and admin email; **the default shown on first open** |
| **Modern Dark** | 5 blocks | Dark background throughout, dark-grey header, purple "View Website" button, darker footer |
| **Minimal Clean** | 8 blocks | Light background, no colored header — just a small logo, a title, a thin colored divider, then the message and a rounded button |
| **Elegant** | 6 blocks | Navy header with a thin (weight-300) title, a sharp-cornered button with a "→" arrow, no border radius anywhere |
| **Colorful** | 6 blocks | Bold purple header, light-purple message background, a fully rounded button, a row of social icons (Facebook/X/Instagram by default) |

Choosing a template only changes the starting point; afterward you can edit, move, duplicate, or delete every block individually — templates are not locked.

## The 5 dynamic shortcodes

Shortcodes are where real data (the form's message, the title, the site name…) lands in your design. In every text-field block's properties panel, a row of small buttons beneath the text field lets you insert each shortcode instantly; the block palette also has a "Shortcode Reference" section listing all of them with insert and copy buttons.

| Shortcode | Required? | Replaced with |
| --- | --- | --- |
| `shortcode_message` | **Yes** | The actual submitted form data (fields and their values) |
| `shortcode_title` | No | The email's title (e.g. "New Message", or "Welcome!" for a registration email) |
| `shortcode_website_name` | No | Your site's name |
| `shortcode_website_url` | No | Your site's home page URL |
| `shortcode_admin_email` | No | The email address of the site's main admin user (user ID 1) |

When you insert a shortcode into a text field, it appears as a non-editable **chip**, not raw text — so typing around it can't accidentally delete or alter a letter in the middle of `shortcode_message` and silently break it.

If you remove the Message Content block (which contains `shortcode_message`) from the canvas, or delete the shortcode text from any other block that had it, both the Preview button and the final save are blocked with an error message until you add it back.

## Global settings

The third tab in the left column (**Settings**) affects the whole email, not just one block:

| Setting | Default | What it does |
| --- | --- | --- |
| Email Background | `#f8f9fa` | The color outside the main card (the surrounding space in the email client) |
| Content Background | `#ffffff` | The color of the main email card itself |
| Content Width (px) | `600` | The width of the main card |
| Border Radius | `8px` | The corner rounding of the main card |
| Default Font | Segoe UI and its fallbacks | The font any block without its own font setting uses |
| Direction | based on site language | `ltr` or `rtl`; only changes the base text direction, it does not automatically flip each block's own alignment setting |
| Button Background | `#202a8d` | The default color of newly created buttons; this same color also colors the confirmation-link button in the plugin's default design (when no custom template exists) |
| Button Text Color | `#ffffff` | The text color on those same buttons |

**Email-safe fonts only:** unlike the Response Box color builder, which offers downloadable Google Fonts, this font list is deliberately limited to **15 standard email-safe fonts** (Segoe UI, Arial, Helvetica, Verdana, Tahoma, Trebuchet MS, Lucida Sans, Georgia, Times New Roman, Palatino, Courier New, Lucida Console, Comic Sans MS, Impact, Tahoma for RTL) — the fonts nearly every email client (Gmail, Outlook, Apple Mail, and so on) already has on the user's system, so nothing needs to be downloaded to render them. There is no custom or downloadable font option in the email template builder.

**20 preset color swatches:** beside every color picker (in global settings and in each block's properties panel alike), 20 predefined colors appear as small clickable squares for faster brand matching; these are only shortcuts — the free color picker and the hex text field are always available too.

## Toolbar and keyboard shortcuts

| Button | What it does |
| --- | --- |
| **Undo** | Steps back one change; keeps up to **30 steps** of history |
| **Redo** | Restores a step that was undone |
| **Preview** | Opens a window with sample data ("John Doe", a sample email, sample message text) standing in for the shortcodes; if `shortcode_message` is missing from the canvas, preview refuses to run |
| **Export** | Downloads an `email-template.html` file containing the final HTML |
| **HTML** | Opens a raw code editor; edit the markup directly and press Apply to replace the whole canvas with it (`<script>` tags are rejected) |
| **Reset** | After a browser confirmation prompt, replaces the canvas with the **Professional** template — not with a fully empty canvas |

Keyboard shortcuts (when focus is not inside an input field): **Ctrl+Z** undo, **Ctrl+Y** or **Ctrl+Shift+Z** redo, **Delete** removes the selected block, **Up/Down arrows** reorder the selected block within the canvas.

## Limits and validation on save

When you press the settings page's Save button, these rules are checked **both in the browser and again on the server** (so even a manipulated request is still caught by the server):

- The saved content must contain `shortcode_message`, or the save is rejected with an error message.
- The maximum length is **50,000 characters**.
- Content that is empty or only a few characters long is treated the same as "no custom template".

For scale: 50,000 characters is generally more than enough even for a fairly detailed design with several dozen blocks and some extra custom HTML mixed in.

## Exactly which emails this design applies to

A detail that is not obvious at first glance: your design only changes the outer look (header, colors, fonts, button, footer), and it applies to **every kind of email built from form or account activity** — not just the "new message" one:

- The new form submission notification email (with or without a tracking-link button)
- The password recovery email
- The new user registration/welcome email

For the last two, what lands inside the message area is no longer "form data" — the plugin substitutes its own appropriate content (a password-reset link, a registration-confirmation link) into the same `shortcode_message` slot, but the colors, fonts, header, and footer you designed stay exactly the same.

**Note:** the "Check Email Server" tool on the **Email Settings** tab is a separate diagnostic feature. It sends a fixed, simple message (purely to test deliverability) through its own code path and does not use your custom template at all — however far your design has come, that button's message stays plain. To see your actual design, use the **Preview** button inside the builder, or wait for a real email (for example, by test-submitting one of your forms).

## If you never open this tab

If you have never saved anything on this tab, your site's emails run entirely on the plugin's **built-in default design**: a navy gradient header with the Easy Form Builder logo and a title, the message body, and a "Sent by [site name]" footer. This built-in design has nothing to do with the builder's blocks — it is defined entirely in the plugin's own PHP code.

A subtlety worth knowing: opening the Email Template tab for the first time loads the "Professional" template into the canvas so the page isn't blank — but that is only a preview inside the editor. Nothing about your site's actual outgoing email changes until you press the settings page's **Save** button; until then, the built-in default design stays active.

## Security: what gets stripped

Because this tool lets you enter raw HTML/CSS (in the Custom HTML block and the code editor), the plugin sanitizes content in multiple layers — once while you type in the browser, and again on the server when you save:

- Tags like `<script>`, `<iframe>`, `<object>`, `<embed>`, `<form>`, `<input>`, `<svg>`, and similar are stripped.
- Event-handler attributes such as `onclick`/`onerror` are removed.
- `javascript:`, `vbscript:`, and `data:text/html` URLs are blocked.
- Older CSS tricks like `expression()` and `-moz-binding` are stripped.

This sanitizer is built specifically for a complete HTML email document (not the plugin's usual post-content filter), so structural tags a real email needs — `<html>`, `<head>`, `<style>` — are kept intact; only the attack vectors are removed.

## Troubleshooting

**The Preview button doesn't work and shows an error.**
Your canvas is missing `shortcode_message`. Either add the **Message Content** block back, or if you had written it inside a Custom HTML block, make sure the exact text `shortcode_message` is still there.

**Save is rejected with a message about adding the message shortcode.**
The same rule, enforced again on the server. This exists so nobody can accidentally save a template that never actually displays the real form data.

**The logo image doesn't show up.**
The image URL field wants a link, not a file. Upload the image somewhere first (the WordPress media library, for example), copy its full public address, and paste that into the **Image URL** field.

**I clicked Preview and the real email didn't look the same.**
Preview uses sample data, not real data. If the layout and colors are right but the content differs, that's expected — real form data only fills the `shortcode_message` slot in an actual outgoing email.

**I used the "Check Email Server" tool and it didn't show my design.**
That's correct; that tool measures email deliverability, it isn't a template preview. Use the **Preview** button inside the builder, or submit a test form, to see your actual design.

**I saved my changes but the next email still shows the old/default design.**
Check whether the save was actually rejected (a missing `shortcode_message` or exceeding the character limit both block the save silently unless you notice the error message). Also make sure you pressed the **settings page's own Save button**, not just the buttons inside the builder.

**The Two Columns block looks backwards on my Persian site.**
That block is always a fixed "left" and "right" — it doesn't automatically flip for the site's direction. Rearrange the content of the two columns yourself, or note that the **Direction** setting in Global Settings only flips the base text direction, not the column layout.

## FAQ

**Are these settings per form or global?**
Global. You design it once and it applies to the emails of every form on the site — exactly like the response box settings.

**Do I need the Pro version?**
No. The email template builder itself — all 13 blocks, all 6 ready-made templates, the global settings, and the shortcodes — is fully available in the free version.

**Why do I see a ready-made design when I open the tab, even though I've never saved anything?**
The builder loads the "Professional" template into the editor as a convenient starting point. Until you save, your site's actual email still uses the plugin's built-in default design, not this starting point.

**Can I build a fully custom Custom HTML block?**
Yes, as long as it contains no `<script>` tag and fits within the template's overall 50,000-character limit. Other dangerous code (on* event handlers, javascript: URLs, and similar) is stripped automatically.

**Can I add my brand's own custom font here?**
Not in the email template builder — the font list is deliberately limited to standard email-safe fonts so it renders correctly across every email client. (This differs from the Response Box color builder, which does accept a downloadable custom font, because that one is a normal web page, not an email.)

**What does changing "Button Background" in global settings affect?**
Every newly created button on the canvas, and also — even if you never add a button block at all — the confirmation/tracking-link button that the plugin inserts on its own inside either your custom template or the default design.

**What happens if I delete the Message Content block?**
Both Preview and Save are blocked until you add it back, because without it no email would ever contain the actual form data.
