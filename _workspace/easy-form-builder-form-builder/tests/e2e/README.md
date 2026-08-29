# E2E tests — not run in this task

The plugin root already has `playwright` as a devDependency
(`c:\xampp\htdocs\wp\wp-content\plugins\easy-form-builder\package.json`) and prior project work
used a Playwright e2e stub pattern for another subsystem (PersiaPay bank-return flow) — follow
that same pattern here once a local admin login is available:

1. `page.goto('/wp-admin/admin.php?page=Emsfb_create')`, log in if needed.
2. Click a "create new form" card (selector: `.efbCreateNewForm` — see
   `docs/PHASE-0-AUDIT.md` §1 for why this is a class, not a function).
3. Add a field via drag from the palette (`.draggable-efb`) to `#dropZoneEFB`.
4. Click Save, wait for the result modal (`#settingModalEfb`), capture the returned form id.
5. Navigate to `admin.php?page=Emsfb&state=edit-form&id=<id>` directly (confirms the deep-link
   reload path documented in `docs/ARCHITECTURE.md`/`docs/DEPENDENCY_GRAPH.md`).
6. Assert the field added in step 3 is present, with the same label/order.
7. Edit the field, click Save (update path), reload the browser again, re-assert.

Not implemented here because it requires interactive credentials this task was not given and
should not guess at.
