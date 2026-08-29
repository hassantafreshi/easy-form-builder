# Integration tests — not run in this task

Requires an authenticated WordPress admin session (valid `wp_rest` nonce + login cookie) against
a running instance of this plugin. Not available to this task; see `docs/TEST_PLAN.md`
"Integration" section for the exact assertions each planned test would make once credentials are
available. When ready to implement:

1. Point `FormBuilderWordPressAdapter` at the real `ajax_url` (`/wp-admin/admin-ajax.php`) and a
   real nonce obtained from an authenticated session (e.g. via a Playwright login flow, or a
   test-only WP-CLI-minted nonce).
2. Run the three scenarios listed in `docs/TEST_PLAN.md` against a disposable/test form id so
   nothing in a real dataset is mutated.
3. Clean up (delete the test form via `remove_id_Emsfb`) in an `afterEach`/`finally`.
