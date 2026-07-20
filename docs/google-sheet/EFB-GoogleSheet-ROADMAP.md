# Google Sheet Addon — Roadmap & Checklist

Tracks remaining work for the Google Sheet integration, broken into testable steps. Check items off as they are implemented and verified.

## Architecture decision: Service Account vs OAuth "Connect with Google"

**Question:** can we offer a one-click "Connect with Google" button instead of pasting a JSON key?

**Answer:** Not from inside this plugin alone, and it is the wrong default for this audience.

- A real one-click OAuth flow needs a registered OAuth client with a **client secret**. A WordPress plugin is downloaded and run on thousands of independent sites — a secret baked into the plugin is not a secret anymore (anyone can extract it and impersonate the app). The only safe way to do OAuth-with-a-button is to run a **hosted relay/broker service** (our own backend) that holds the secret, handles the consent redirect, and proxies refreshed tokens back to each site. That is a real piece of infrastructure to build and operate (uptime, security, GDPR data handling for tokens), not a small addition.
- OAuth user tokens also expire and need silent refresh; if the relay goes down, every connected site stops syncing — a single point of failure across all installs.
- The current **Service Account** approach has no such dependency: each site holds its own credentials, talks to Google directly, and nothing expires on a schedule (service account keys are valid until revoked). For a webmaster who is not a developer, "paste one JSON file, share one sheet" is one screen of friction done once — comparable to or less than "create OAuth client, set redirect URI, verify consent screen" which is what *we* would have to do once, but every *user* still has to click through a Google consent screen with our app's name and possibly an "unverified app" warning unless we pay for and pass Google's OAuth verification review.

**Recommendation:** keep the Service Account flow as the supported path (already implemented), and treat a hosted-relay OAuth experience as a separate, larger product decision — not a checklist item here. If we ever build it, it deserves its own infra/roadmap doc, not a line item in this plugin's todo.

What we *can* and should still do to make the Service Account flow feel as close to "Connect with Google" as possible — covered below (auto-detect bad config, one-click test, plain-language errors, connection health).

---

## 1. Connection UX polish

- [x] Drag-and-drop JSON upload, multi-file
- [x] Multiple service accounts, switch/set default
- [x] One-click "Test Connection" / "Retest" per account
- [x] Plain-language error messages (not raw Google API JSON) — `humanize_error()`
- [x] Step-by-step in-plugin guide (Connections tab sidebar + Help tab)
- [ ] **Connection Health badge** per service account: `Healthy` / `Action Required` / `Disconnected`, computed from last test result + last sync log entries (e.g. 3 consecutive failures ⇒ Action Required)
- [ ] Periodic background health check (WP-Cron, e.g. every 6h) that re-tests each connection and emails the admin once if a previously healthy connection starts failing
- [ ] Show "Added on" / "Last verified" timestamps on each connection card (data already stored in `last_test.tested_at`, just needs surfacing)

## 2. Existing Spreadsheet / Tab support

- [x] Pick an existing spreadsheet visually (Drive picker grid) instead of always creating a new one
- [x] Pick an existing tab, or type a new tab name (auto-created on first sync)
- [x] Optionally create a brand-new spreadsheet from the wizard
- [x] Never deletes/overwrites existing rows — sync only appends
- [x] Detects existing header row reuse on rebind (signature check keeps `header_fields` if connection/sheet/tab/mode unchanged)
- [ ] **Detect a pre-existing header row already in the sheet** (when binding to a sheet that already has data/headers from manual use) and offer "use this row as header" instead of always writing a fresh one
- [ ] Let the admin manually pick which existing row is the header row (for sheets where row 1 isn't the header)

## 3. Real field mapping

- [ ] Per-column field mapping UI: choose which form field feeds which sheet column (today: column order = field discovery order, "Selected Fields Only" can pick a subset but not reorder per column)
- [ ] Drag-and-drop reordering of the mapping list
- [ ] Allow ignoring specific fields without using "Selected Fields Only" allow-list syntax
- [ ] Allow multiple form fields to merge into one column (e.g. first + last name → "Full Name")
- [ ] Allow one field's value to be duplicated into multiple columns
- [ ] Static/constant value columns (e.g. "Campaign", "Site Name") not tied to any form field
- [ ] "Preview one row" before saving a binding, using the most recent real submission or a synthetic sample
- [ ] Warn if a previously-mapped column name no longer exists in the sheet header (renamed/deleted manually)

## 4. Label vs Value control

- [ ] Per-field option for Select / Radio / Checkbox: `Send Label` / `Send Value` / `Send Both`
- [ ] For multi-value fields (checkboxes): `Separate values by comma` / `Separate into columns` / `JSON format`
- [ ] Make this configurable per binding, not global, since different sheets may be consumed by different downstream automations

## 5. Reliable delivery (no silent data loss)

This is flagged as more important than UI polish — currently a sync failure is logged but **not retried**.

- [ ] Persist every submission intended for Google Sheets in its own DB-backed queue table (`{prefix}_emsfb_googlesheet_queue`) the moment the form is submitted — independent of whether the Sheets API call succeeds
- [ ] Submission states: `pending` → `synced` / `failed` / `retrying`
- [ ] Background retry worker (WP-Cron) with increasing backoff (e.g. 1m, 5m, 30m, 2h, then stop and mark `failed`)
- [ ] Form submission must never fail or block on a Sheets API error — already true today (sync runs after the success response via `do_action`), keep this invariant when the queue is added
- [ ] Admin UI: "Resend to Google Sheets" button per failed row
- [ ] Admin UI: "Bulk Retry" for all failed rows
- [ ] Email alert to admin after N consecutive failures for the same binding
- [ ] Queue table is the backup of record — never deleted automatically, only prunable manually or by retention setting

## 6. Sync Logs page

- [x] Submenu page under Google Sheet showing sync history (time, form, event, spreadsheet, tab, status, message)
- [x] Clear Log action
- [ ] Filter by form / status / date range
- [ ] "Send Test Row" button from the Logs page (writes one test row to a chosen binding's sheet on demand)
- [ ] "Copy Error Details" button per failed row (for support/bug reports)
- [ ] "Reconnect" shortcut linking back to the relevant Connections card when a log entry's failure is auth-related
- [ ] Once the retry queue (section 5) exists: show retry count and next retry time per row, and a `Row Number` column once known from the Sheets API response

---

## Suggested implementation order

1. Field mapping UI (section 3) — biggest day-to-day usability complaint once basic sync works
2. Label vs Value control (section 4) — small, pairs naturally with field mapping UI
3. Reliable delivery / retry queue (section 5) — most important for trust, independent of UI work
4. Logs page enhancements (section 6) — mostly additive once the queue exists
5. Connection health checks (section 1 remaining items) — polish once the above is stable
6. Pre-existing header row detection (section 2 remaining items) — edge case, lowest priority

Each section above should be tested manually against a real Google Sheet before checking its box: connect a service account, share a real sheet, submit a real form, and confirm the row (and behavior under a forced failure, e.g. revoke sheet access) matches the spec for that item.
