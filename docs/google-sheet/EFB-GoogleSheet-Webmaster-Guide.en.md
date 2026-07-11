---
title: "Easy Form Builder Google Sheets Integration: Complete Webmaster Guide"
description: "Learn how to connect Easy Form Builder to Google Sheets, upload a service account JSON key, create or link spreadsheets, map form fields to columns, apply sheet styles, and troubleshoot sync issues."
slug: easy-form-builder-google-sheets-integration-guide
keywords:
  - Easy Form Builder Google Sheets
  - WordPress forms to Google Sheets
  - Google Sheets integration WordPress
  - Easy Form Builder Google Sheet addon
  - form submissions to Google Sheets
  - service account Google Sheets WordPress
  - Google Sheets API WordPress form plugin
  - map form fields to spreadsheet columns
  - WordPress form automation
  - Google Sheet sync logs
author: WhiteStudio
date: 2026-07-11
canonical: https://whitestudio.team/docs/easy-form-builder-google-sheets-integration-guide/
excerpt: "A complete English guide for WordPress webmasters who want to send Easy Form Builder submissions to Google Sheets with server-side syncing, field mapping, worksheet styling, and detailed logs."
product: Easy Form Builder
feature: Google Sheet Addon
audience:
  - WordPress webmasters
  - site owners
  - marketers
  - form admins
---

# Easy Form Builder Google Sheets Integration: Complete Setup, Configuration, and Troubleshooting Guide

**TL;DR:** Easy Form Builder can sync WordPress form submissions to Google Sheets with a Google service account. You upload a service account JSON key in the **Connections** tab, enable both the **Google Sheets API** and **Google Drive API**, choose or create a spreadsheet, bind each form to a worksheet tab, map fields to columns, optionally apply a sheet style, and monitor every sync in **Google Sheet Logs**. No browser login or webhook is required.

This guide is written for webmasters and site owners who want a practical, SEO-friendly, and technically accurate explanation of how the Easy Form Builder Google Sheet addon works today.

---

## Quick Answers

### Does Easy Form Builder support Google Sheets?

Yes. The Google Sheet addon can send submissions from Easy Form Builder directly to Google Sheets.

### Does it require OAuth or a browser login?

No. It uses a **Google service account JSON key** and authenticates server-side.

### Can I use my existing spreadsheet?

Yes. You must share that spreadsheet with the **service account email** as **Editor** first.

### Can I create a new spreadsheet from inside the plugin?

Yes. The wizard includes **Create New Sheet**, and the plugin can automatically share the new file to your own Google email.

### Can I choose which fields go to which columns?

Yes. The current addon includes a **Columns & Fields** step where you can reorder fields, rename headers, and disable columns you do not want to sync.

### Does it keep logs?

Yes. Easy Form Builder stores both a latest-per-form sync status and a full **Google Sheet Logs** history page.

---

## Why This Integration Matters for Webmasters

If you manage leads, support requests, registrations, surveys, or payment-related form data inside WordPress, Google Sheets gives you a fast way to:

- review submissions without opening the WordPress dashboard every time
- share live form data with a team
- build simple reports and filters
- connect spreadsheet data to internal workflows
- keep an accessible backup-like working view of submissions

The Easy Form Builder Google Sheet addon is especially useful because it works **server-side**. That means the sync does not depend on a site owner's browser session staying logged in.

---

## What the Easy Form Builder Google Sheet Addon Does

The current addon supports the following real features:

- one or more **service account** connections
- a **default service account** for new bindings
- global on/off control for the whole Google Sheets integration
- per-form **binding** to a specific spreadsheet and worksheet tab
- **Browse My Sheets** for spreadsheets the service account can access
- **Create New Sheet** directly from the binding wizard
- a 4-step binding wizard:
  1. Select Form
  2. Choose Sheet
  3. Columns & Fields
  4. Style & Save
- visual **field-to-column mapping**
- custom spreadsheet headers
- column reordering
- per-column enable/disable
- built-in **Submitted At** timestamp column
- automatic worksheet tab creation if the tab does not exist yet
- automatic header creation and header repair
- optional sheet styles:
  - `None`
  - `Minimal`
  - `Green`
  - `Blue`
  - `Slate`
  - `Sunset`
  - `Grape`
- **Test Connection** before saving
- **Google Sheet Logs** for success and failure monitoring

Important limitation: the current build does **not** expose a user-facing retry queue yet. It does retry transient API/network failures once automatically and then logs the outcome.

---

## How the Integration Works

At a technical level, Easy Form Builder follows this flow:

1. You upload a **service account JSON** key in the **Connections** tab.
2. The plugin tests the connection and stores the service account details in WordPress.
3. For each form, you create a **binding** that stores:
   - the service account
   - the spreadsheet ID
   - the worksheet tab
   - the enabled state
   - the column mapping
   - the selected style template
4. When the form is submitted, Easy Form Builder sends the integration context to the Google Sheet addon.
5. The addon:
   - verifies the binding is enabled
   - verifies the global Google Sheet switch is enabled
   - ensures the worksheet tab exists
   - builds a row from the submitted values
   - writes or repairs the header row if needed
   - appends the new row
   - applies the chosen style if it has not been applied yet
   - stores the sync result in the logs

The addon is designed so that a Google Sheets sync failure does **not** break the normal form submission flow. The failure is logged instead.

---

## Requirements Before You Start

Before you configure the addon, make sure you have:

- a WordPress site with **Easy Form Builder** installed
- at least one form created in Easy Form Builder
- a Google account that can access **Google Cloud Console**
- permission to create or use a Google Cloud project
- the **Google Sheets API** enabled
- the **Google Drive API** enabled
- a **service account JSON key**
- PHP **OpenSSL** enabled on your server

Why OpenSSL matters: the addon signs Google authentication requests with the private key inside your JSON file. Without PHP OpenSSL, the addon cannot authenticate to Google.

---

## Step 1: Create or Choose a Google Cloud Project

1. Open `https://console.cloud.google.com/`.
2. Sign in with your Google account.
3. Use the project picker in the top bar.
4. Create a new project or choose an existing one.

Use a project name that makes sense for your site, such as `EFB Sheets Sync`.

---

## Step 2: Enable the Required Google APIs

Easy Form Builder needs **both** APIs below:

- **Google Sheets API**: used to read sheet metadata, create tabs, write headers, and append rows
- **Google Drive API**: used to list spreadsheets and create new spreadsheet files

To enable them:

1. In Google Cloud Console, go to **APIs & Services > Library**.
2. Search for **Google Sheets API** and click **Enable**.
3. Search for **Google Drive API** and click **Enable**.

If one of these APIs is missing, the addon may show what looks like a permissions error even though the real issue is that the API is disabled.

---

## Step 3: Create a Service Account and Download the JSON Key

1. In Google Cloud Console, go to **APIs & Services > Credentials**.
2. Click **Create Credentials > Service Account**.
3. Give the service account a name.
4. Finish the setup.
5. Open the new service account.
6. Go to the **Keys** tab.
7. Click **Add Key > Create new key**.
8. Choose **JSON** and download the file.

This file is the credential Easy Form Builder uses to connect to Google.

Treat it like a password:

- do not commit it to a repository
- do not send it in public chat
- rotate it if it is ever exposed

---

## Step 4: Open the Google Sheet Addon in Easy Form Builder

Inside WordPress admin:

1. open the **Easy Form Builder** menu
2. click **Google Sheet**

You will see the main Google Sheet page with:

- a connection status header
- **Connections**
- **Form Bindings**
- **Help & Guide**

There is also a separate **Google Sheet Logs** page in the same admin menu.

---

## Step 5: Configure the Connections Tab

The **Connections** tab is where you add and manage Google service accounts.

### Upload the JSON key

You can:

- drag and drop one or more `.json` files
- click the upload area and browse for the file

After upload, the addon:

- validates the JSON
- extracts the service account email
- stores the connection
- runs an automatic connection test

### Save "Your Google email"

The Connections tab also includes a field called **Your Google email**.

This is strongly recommended.

Why it matters:

- when the plugin creates a brand-new spreadsheet, that file is owned by the service account
- if you save your own Google email, the plugin shares each newly created spreadsheet to you automatically
- this makes the new sheet appear in your Google Drive under **Shared with me**

Without this step, newly created sheets may feel "missing" because they live inside the service account's own Drive space.

### Multiple service accounts

The addon supports multiple service account connections. This is useful if you want:

- one connection for production and another for staging
- separate connections for different clients or brands
- isolation between teams

You can also choose one connection as the **default service account**.

---

## Step 6: Decide Whether to Use an Existing Sheet or Create a New One

Easy Form Builder supports two workflows.

### Option A: Create a new spreadsheet inside the wizard

This is the easiest path.

When you use **Create New Sheet**, the addon:

- creates the spreadsheet with the service account
- returns the spreadsheet ID and open link
- can share the file to your saved Google email automatically

This is the best option if you want the addon to create a clean destination for form data.

### Option B: Use an existing spreadsheet

If you want to sync into a spreadsheet you already own, you must first share it with the service account email.

Steps:

1. open your spreadsheet in Google Sheets
2. click **Share**
3. paste the service account email shown on the connection card
4. set the role to **Editor**
5. save the sharing change

After that, return to the wizard and click **Browse My Sheets**.

Important: the addon only lists spreadsheets the chosen service account can actually access.

---

## Step 7: Create a Form Binding in the 4-Step Wizard

Go to **Form Bindings** and create a new binding.

### Step 1 - Select Form

Choose which Easy Form Builder form should send data to Google Sheets.

This step is simple, but important: every binding belongs to one specific form.

### Step 2 - Choose Sheet

In this step you:

- choose the service account for this binding
- click **Browse My Sheets** to list accessible spreadsheets
- or click **Create New Sheet**
- choose the worksheet tab
- optionally type a new tab name

If the worksheet tab does not exist yet, Easy Form Builder creates it automatically on the first sync.

### Step 3 - Columns & Fields

This is where the current addon becomes much more useful than a basic sheet sync.

You can:

- enable or pause sync for this specific form
- choose the order of columns
- rename spreadsheet headers
- disable any column you do not want to write
- keep the built-in **Submitted At** column

The UI shows column letters like `A`, `B`, `C` so you can see where each field will land.

If the addon cannot read the form structure automatically, it falls back gracefully:

- the binding still works
- all submitted fields are synced automatically
- the addon writes columns in submission order

In that fallback mode, mapping is not visual, but sync still works.

### Step 4 - Style & Save

The last step lets you:

- choose a sheet style
- click **Apply style now**
- click **Test Connection**
- click **Save Binding**

The available styles are:

- `None`
- `Minimal`
- `Green`
- `Blue`
- `Slate`
- `Sunset`
- `Grape`

These styles are implemented with Google Sheets formatting features such as:

- frozen top row
- colored header
- alternating row colors

They are cosmetic and do not change your data structure.

---

## What Data Easy Form Builder Writes to Google Sheets

The addon does more than dump raw values. It normalizes several field types for a cleaner spreadsheet.

### Built-in timestamp

Every row can include a built-in **Submitted At** value.

### Standard fields

Regular form fields are written by their field names and mapped into the selected columns.

### File uploads

If a submitted field is a file upload, the addon writes the usable **file URL** rather than a placeholder token.

### Password fields

Password values are **masked** and are not written as plain text.

### Multi-select and checkbox values

List-style values are converted into readable text, usually comma-separated.

### Payment-related fields

When a payment field exists, the addon can build a one-cell payment summary and mirror it to the relevant payment field name so mapped payment columns are usable.

### New tabs

If the target worksheet tab does not exist, it is created automatically.

### Header self-repair

If the first row was cleared or manually changed, the addon compares the real header row with the expected columns and rewrites the header when necessary.

This is useful for long-term reliability.

---

## How Sheet Headers and Column Mapping Behave Over Time

Webmasters often need to know what happens after a form changes.

### If you use visual column mapping

The mapping is deterministic:

- only enabled columns are written
- column order follows your mapping
- header labels follow your custom titles

If you later add a new field to the form, it is **not** added automatically to a mapped binding. You should open the binding and use **Refresh fields from form**.

### If the addon falls back to all-fields mode

In fallback mode, new fields can be added automatically on future submissions because the addon works from the submitted data rather than a fixed visual map.

---

## How to Test the Integration

Before you trust a live workflow, test it.

Use this sequence:

1. save the binding
2. click **Test Connection**
3. submit the form once from the front end
4. open the spreadsheet
5. confirm:
   - the tab exists
   - the header row exists
   - the new row appears
6. open **Google Sheet Logs** to confirm the sync result

This is the fastest way to validate both setup and real submission flow.

---

## How to Monitor Sync Activity

Easy Form Builder includes a dedicated **Google Sheet Logs** page.

The logs show:

- time
- form
- event
- spreadsheet ID
- worksheet tab
- status
- detail message

This matters because not every sync event is a simple contact-form submission. Depending on the form flow, the addon can receive different event types such as:

- `form_submit`
- `payment`
- `register`
- `login`
- `subscribe`
- `survey`
- reply-related events

For a webmaster, the logs are the first place to check when a user says, "The form submitted, but I do not see the row."

---

## What Happens If You Delete a Binding

Deleting a binding only stops future syncing.

It does **not**:

- delete the Google spreadsheet
- delete the worksheet tab
- delete existing rows already written

The same rule applies when you remove **all** bindings. Your Google Sheets remain untouched.

---

## Best Practices for Webmasters

### Save your Google email in the Connections tab

This prevents confusion when new sheets are created by the service account.

### Use separate service accounts for staging and production

This keeps testing data away from live reporting sheets.

### Prefer one form per tab when reporting matters

You can use separate tabs for clearer reporting, filtering, and team handoff.

### Keep header names stable

If external reporting, formulas, or dashboards depend on column titles, avoid renaming headers casually after launch.

### Protect the JSON key

The JSON key should be treated like a secret.

### Re-test after major form changes

If you add payment logic, file uploads, or new required fields, run a fresh test submission.

---

## Troubleshooting Guide

### Problem: "A required Google API is not enabled for this project"

**Cause:** The Google Sheets API or Google Drive API is disabled.

**Fix:** Enable both APIs in Google Cloud Console and wait a minute before re-testing.

### Problem: "Access denied" or "The caller does not have permission"

**Cause:** Most commonly, the existing spreadsheet was not shared with the service account email as **Editor**.

**Fix:** Share the spreadsheet with the service account email, then try **Browse My Sheets** or **Test Connection** again.

### Problem: The connection test fails right after uploading the JSON file

**Cause:** Possible reasons include:

- invalid JSON key
- corrupted private key
- server clock skew
- required API not enabled

**Fix:** Re-download the JSON key, verify the correct Google Cloud project, and check the server time.

### Problem: I created a new sheet, but I cannot find it in my Google Drive

**Cause:** New sheets are created by the service account, not by your personal Google account.

**Fix:** Save **Your Google email** in the Connections tab so newly created sheets are shared to you automatically.

### Problem: My existing spreadsheet does not appear under "Browse My Sheets"

**Cause:** The service account cannot access it yet.

**Fix:** Open the spreadsheet, click **Share**, add the service account email as **Editor**, then browse again.

### Problem: OpenSSL is missing

**Cause:** Your server does not have the PHP OpenSSL extension available.

**Fix:** Ask your host or server admin to enable the `openssl` PHP extension.

### Problem: The form submits, but no row appears

Check all of these:

- the global Google Sheet integration switch is enabled
- the form binding itself is enabled
- the correct form is bound
- the correct spreadsheet and tab are selected
- the logs page shows the result

### Problem: Google API rate limit reached

**Cause:** Google returned a quota or rate-limit response.

**Fix:** Wait for the quota window to reset. The addon retries transient failures once automatically and logs the result.

### Problem: I changed the form fields and now the sheet layout is wrong

**Fix:** Open the binding, go to **Columns & Fields**, and use **Refresh fields from form** if the binding uses visual mapping.

---

## FAQ

### Can I connect more than one service account?

Yes. The addon supports multiple service account JSON keys and lets you choose a default one.

### Does the connection expire?

There is no browser session for the site owner to re-authorize. The addon authenticates server-side and renews access tokens automatically as needed.

### Can I map one form to one tab and another form to a different tab?

Yes. Each binding is configured per form and targets its own spreadsheet and worksheet tab.

### Can I style the sheet automatically?

Yes. Choose a style in **Style & Save** and apply it immediately or let the addon apply it on the first successful sync.

### Can I stop syncing without losing data already written?

Yes. Disable the binding or delete the binding. Existing sheet data remains intact.

### Will the addon create headers automatically?

Yes. It writes the header row automatically and can repair it if the header no longer matches the expected layout.

### Does the addon support existing sheets and newly created sheets?

Yes. Both workflows are supported.

### Is a webhook required?

No. The current Google Sheet addon works directly with the Google APIs and does not require a separate webhook setup.

---

## Recommended SEO Summary for Publishing

If you publish this article on a help center, blog, or product docs site, keep these elements:

- a clear keyword-focused title
- a short answer near the top
- step-by-step headings
- a troubleshooting section
- a FAQ section
- internal links to your Easy Form Builder docs, pricing, and support pages

This article structure is also friendly for AI summaries because it contains:

- direct question-and-answer blocks
- explicit feature descriptions
- task-based steps
- honest limitations

---

## Final Takeaway

The Easy Form Builder Google Sheet addon is more than a basic "send entries to Sheets" connector. In its current form, it gives webmasters a server-side Google Sheets workflow with multiple service accounts, a 4-step binding wizard, visual field mapping, automatic worksheet creation, optional styling, and actionable sync logs.

If you want the smoothest setup, follow this order:

1. enable both Google APIs
2. upload the service account JSON key
3. save your Google email
4. create a new sheet from the wizard or share an existing one with the service account
5. configure the 4-step binding
6. submit one real test entry
7. confirm success in **Google Sheet Logs**

That sequence matches how the addon actually works in Easy Form Builder today.
