# Connecting Easy Form Builder to Google Sheets — Complete Setup Guide

> A step-by-step walkthrough of every process involved in sending your WordPress form
> submissions to Google Sheets with **Easy Form Builder (EFB)** — from creating a Google
> Cloud project to seeing your first row land in a spreadsheet.
>
> This document describes the **processes** only. It is written to be refined later into a
> published help-center article or blog post.

---

## 1. Overview

Easy Form Builder syncs form responses to Google Sheets using a **Google service account** —
a robot account that belongs to a Google Cloud project rather than to a person. Because the
plugin authenticates as this service account, there is **no browser login, no OAuth consent
screen, and no token that expires** for the site owner to re-authorize. You set it up once,
and submissions flow into your sheet automatically.

At a high level, the setup has four stages:

1. **Google Cloud Console** — create a project, enable the two required APIs, create a
   service account, and download its JSON key.
2. **WordPress / EFB** — install and activate the plugin, then add the service account by
   uploading that JSON key.
3. **Google Sheet** — either let the plugin create a new spreadsheet, or share an existing
   spreadsheet with the service account.
4. **Form binding** — link a specific form to a specific sheet tab and enable syncing.

The whole process typically takes 10–15 minutes.

---

## 2. How the integration works (concepts)

Before the steps, three concepts make everything else make sense:

- **Service account** — an identity like
  `my-bot@my-project-123456.iam.gserviceaccount.com`. It has its own Google Drive storage
  and can own or be granted access to spreadsheets, exactly like a human user.
- **JSON key** — a downloaded credentials file that lets the plugin prove it *is* that
  service account. It contains a `client_email` and a `private_key`. Treat it like a
  password.
- **Two Google APIs** — the integration needs **both**:
  - **Google Sheets API** — reads and writes rows, tabs, and headers.
  - **Google Drive API** — creates new spreadsheet files and lists the spreadsheets the
    service account can see.

  If either API is disabled in your Cloud project, actions such as *Create sheet* or
  *List sheets* fail with a 403 error. Enabling both up front avoids the most common
  problems.

The scopes the plugin requests are:

```
https://www.googleapis.com/auth/drive
https://www.googleapis.com/auth/spreadsheets
```

---

## 3. Prerequisites

- A **Google account** (any Gmail or Google Workspace account) that can access
  [Google Cloud Console](https://console.cloud.google.com/).
- A WordPress site with **Easy Form Builder** installed, and permission to manage plugins.
- At least one **form** already built in EFB (you can also create it later).
- Your server clock should be reasonably accurate. Service-account authentication signs a
  time-based token; a badly skewed server clock causes `invalid_grant` errors.

---

## 4. Part 1 — Google Cloud Console setup

You only do this part once per Google Cloud project. If you already have a project with a
service account, skip to the step you need.

### Step 1 — Create (or select) a Google Cloud project

1. Go to **[console.cloud.google.com](https://console.cloud.google.com/)** and sign in.
2. In the top bar, click the **project picker** (the drop-down next to the "Google Cloud"
   logo).
3. Click **New Project**.
4. Give it a recognizable **name** (e.g. `EFB Sheets Sync`), leave the organization/location
   as-is unless your Workspace admin requires a specific one, and click **Create**.
5. Wait a few seconds, then make sure the new project is **selected** in the top bar before
   continuing. Every step below applies to the *currently selected* project.

> Note the **project ID** (something like `efb-sheets-sync-123456`). It appears in error
> messages and in the API-enable links, so it helps to recognize it.

### Step 2 — Enable the two required APIs

This is the step most people miss, and it is the cause of the majority of "permission"
errors later.

1. Open the navigation menu (**☰**) → **APIs & Services** → **Library**.
2. Search for **"Google Sheets API"**, open it, and click **Enable**.
3. Go back to the Library, search for **"Google Drive API"**, open it, and click **Enable**.

You can also enable them directly (replace the project ID with your own):

- Sheets API: `https://console.cloud.google.com/apis/library/sheets.googleapis.com`
- Drive API: `https://console.cloud.google.com/apis/library/drive.googleapis.com`

> After enabling, allow **1–2 minutes** for the change to propagate across Google's systems
> before testing in WordPress.

### Step 3 — Create a service account

1. Navigation menu (**☰**) → **APIs & Services** → **Credentials**.
2. Click **Create Credentials** → **Service account**.
   *(Alternatively: **IAM & Admin** → **Service Accounts** → **Create service account**.)*
3. Enter a **service account name** (e.g. `efb-sheets-writer`). Google auto-generates the
   account's email address from this name.
4. Click **Create and continue**.
5. **Grant roles** — for this integration you can **skip** granting project-level roles.
   Access is granted per-spreadsheet by sharing the sheet with the service account (Part 3),
   so no broad IAM role is required. Click **Continue**, then **Done**.
6. Back on the Credentials/Service Accounts list, **copy the service account's email
   address**. You will share your sheet with it, and it is displayed on the account's card
   inside the plugin.

### Step 4 — Create and download the JSON key

1. In **Credentials** (or **Service Accounts**), click the service account you just created.
2. Open the **Keys** tab.
3. Click **Add Key** → **Create new key**.
4. Choose **JSON**, then click **Create**.
5. The `.json` key file **downloads automatically** to your computer. Keep this file
   private and secure — anyone who has it can act as your service account.

> **If key creation is blocked:** Some Google Workspace organizations enforce a policy
> (`iam.disableServiceAccountKeyCreation`) that prevents downloading JSON keys. If the
> **Create** button is greyed out or you get a policy error, ask your Workspace admin to
> allow service-account key creation for this project, or to create the key for you.

At the end of Part 1 you should have:

- ✅ A project with **Google Sheets API** and **Google Drive API** enabled.
- ✅ A service account and its **email address**.
- ✅ A downloaded **JSON key file**.

---

## 5. Part 2 — Activate and configure the plugin

### Step 5 — Install and activate Easy Form Builder

1. In WordPress admin, go to **Plugins → Add New** (or upload the plugin ZIP).
2. Install and **Activate** Easy Form Builder.
3. The **Google Sheet** integration is bundled with the plugin; no separate add-on install is
   required.

### Step 6 — Open the Google Sheet settings page

1. In the WordPress admin sidebar, open the **Easy Form Builder** menu.
2. Click the **Google Sheet** submenu item
   (`admin.php?page=Emsfb_googlesheet_efb`).
3. You will see a page with a status header ("Connected" / "Not Connected") and three tabs:
   - **Connections** — manage service accounts.
   - **Form Bindings** — link forms to sheets.
   - **Help & Guide** — an in-product quick-start.

### Step 7 — Add the service account (upload the JSON key)

1. Go to the **Connections** tab.
2. **Drag and drop** the JSON key file you downloaded into the upload box — or click the box
   to browse for it. You can add **multiple** service accounts at once by dropping several
   files.
3. The plugin reads the key, extracts the **service account email** and **project**, saves the
   connection, and immediately runs a **connection test**.

### Step 8 — Verify the connection

- On success you'll see **"Service account added and verified successfully."** and the header
  badge switches to **Connected**.
- Each connection appears as a **card** showing its label and the **service account email** —
  this is the address you will share your spreadsheet with.
- If you have more than one service account, one is marked as the **active connection**, used
  by default when creating or listing sheets.

> If the test fails here, jump to **Troubleshooting** (Section 9). The most common cause is a
> disabled API (Step 2) or a clock/skew problem.

---

## 6. Part 3 — Prepare your Google Sheet

You have two options. Pick whichever fits your workflow.

### Option A — Let the plugin create a brand-new spreadsheet

1. During form binding (Part 4, Step 2) choose **Create a new spreadsheet**.
2. Give it a title and click **Create**.
3. The service account creates and **owns** the new spreadsheet — no manual sharing needed.
4. *(Optional)* Provide your own email so the plugin also shares the new sheet **with you** as
   an editor, so you can open it in your own Google Drive.

### Option B — Use an existing spreadsheet you already own

Because the spreadsheet was created by a *human* account, the service account has no access to
it until you grant it. This is the single most important manual step:

1. Open your Google Sheet in the browser.
2. Click **Share** (top-right).
3. Paste the **service account email** (shown on the connection card in the plugin, e.g.
   `efb-sheets-writer@my-project-123456.iam.gserviceaccount.com`).
4. Set its role to **Editor**.
5. Untick "Notify people" (the service account has no inbox) and click **Share / Send**.

> **Rule of thumb:** *Create-new* needs no sharing; *use-existing* always needs you to share
> the sheet with the service account as **Editor**.

---

## 7. Part 4 — Bind a form to a sheet

The **Form Bindings** tab uses a simple 3-step wizard. Repeat it once per form.

### Step 1 of the wizard — Select Form

- Choose which EFB form should sync to a sheet, then click **Next**.

### Step 2 of the wizard — Choose Sheet

- Pick the **service account (connection)** to use.
- Then either:
  - **Select an existing spreadsheet** from the list (populated via the Drive API), or
  - **Paste a spreadsheet URL / ID**, or
  - **Create a new spreadsheet** on the spot.
- Click **Next** once a spreadsheet is chosen.

### Step 3 of the wizard — Configure & Save

- Confirm the target **tab** name. If the tab does not exist yet, the plugin **creates it
  automatically on the first submission**.
- On the first write, the plugin also **generates a header row** from your form fields.
- Ensure the binding's **Enabled** switch is on, then **Save**.

Each saved binding appears in the **bindings table** at the top of the tab, where you can edit
or delete it later.

---

## 8. Part 5 — Enable syncing and run a test

1. Make sure the **global sync toggle** is enabled (syncing is off until both the global
   toggle and the per-form binding are on).
2. Submit a **test entry** through the bound form on the front end.
3. Open the target spreadsheet/tab — a new row should appear, with the header row present on
   the first write.

### Monitoring (Logs)

- In the Easy Form Builder menu, open **Google Sheet Logs**
  (`admin.php?page=Emsfb_googlesheet_logs_efb`).
- This page lists recent sync attempts with their status and any error detail, which is the
  fastest way to confirm a submission was delivered — or to see why it wasn't.
- You can clear the log from this page.

---

## 9. Troubleshooting

### "A required Google API is not enabled for this project…"

- **Cause:** The **Google Drive API** and/or **Google Sheets API** is not enabled in the Cloud
  project.
- **Fix:** Enable both (Part 1, Step 2), wait 1–2 minutes, and retry. The error message
  includes a direct link to the exact API-enable page for your project.

### "Access denied. Make sure the spreadsheet is shared with the service account email as Editor. (The caller does not have permission)"

- **Cause 1 — Existing sheet not shared:** You are using an *existing* spreadsheet that has not
  been shared with the service account.
  - **Fix:** Share it with the service account email as **Editor** (Part 3, Option B).
- **Cause 2 — Disabled API masquerading as a permission error:** When the Drive API is
  disabled, some endpoints (such as *Create sheet*) return a short *"The caller does not have
  permission"* message even though the real cause is the disabled API.
  - **Fix:** Enable both APIs (Part 1, Step 2). The plugin now detects Google's
    `SERVICE_DISABLED` / `accessNotConfigured` reason and shows the correct "enable the API"
    guidance instead of the sharing message.

### "Authentication failed… JSON key may be invalid, or your server clock is out of sync."

- **Cause:** A corrupted/incomplete JSON key, or a server clock that is significantly off.
- **Fix:** Re-download a fresh JSON key (Part 1, Step 4) and re-upload it. If it persists,
  check that your server's time (NTP) is correct.

### "Spreadsheet or worksheet tab not found."

- **Cause:** A wrong spreadsheet ID/URL, or a tab name that doesn't match.
- **Fix:** Re-check the spreadsheet ID/URL and the tab name. Remember the tab is created
  automatically on the first submission if it doesn't exist yet.

### Cannot download the JSON key (button disabled / policy error)

- **Cause:** A Workspace org policy disabling service-account key creation.
- **Fix:** Ask your Google Workspace admin to allow key creation for the project, or to
  generate the key for you.

### Nothing syncs, but no error appears

- Check that **both** the global sync toggle **and** the per-form binding are enabled.
- Confirm the form you tested is the one actually **bound**.
- Open **Google Sheet Logs** to see the most recent attempt.

---

## 10. Security and best practices

- **Protect the JSON key.** It is equivalent to a password for the service account. Do not
  commit it to a repository, email it, or store it in a public location.
- **Grant the least access needed.** Prefer sharing individual spreadsheets with the service
  account over granting broad project roles.
- **Rotate keys if exposed.** If a key leaks, delete it in the Cloud Console (Service account
  → Keys) and upload a fresh one in the plugin.
- **Use separate service accounts** per site or per environment (staging vs. production) so
  you can revoke one without affecting the others.
- **Keep the two APIs enabled.** Disabling them later will silently break syncing.

---

## Appendix — Quick checklist

- [ ] Google Cloud project created and selected
- [ ] **Google Sheets API** enabled
- [ ] **Google Drive API** enabled
- [ ] Service account created; email copied
- [ ] JSON key downloaded
- [ ] EFB installed and activated
- [ ] Service account uploaded in **Google Sheet → Connections** and verified
- [ ] Target sheet created by the plugin **or** existing sheet shared with the service account as **Editor**
- [ ] Form bound to a sheet tab (3-step wizard) and **enabled**
- [ ] Global sync toggle **on**
- [ ] Test submission appears in the sheet; confirmed in **Google Sheet Logs**
