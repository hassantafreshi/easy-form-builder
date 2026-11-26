# Easy Form Builder: full changelog

Every change of each release, with the notes for developers and add-on authors. `readme.txt` keeps a short,
user-facing summary of each release; this file keeps everything. Batches that change the plugin before 4.3.2 is
published add their lines to the 4.3.2 section below.

## 4.3.2

Database version 1.3 → 1.13 (the steps run automatically on the first request after the update). Requires
WordPress 6.3 or later and PHP 7.4 or later.

* Fixed (FX-ADMIN, QA-1): Settings › Verification is stored by the page's own Save (its second Save, which the page
  Save ignored, is gone); every number of Settings is checked against its range in the browser (aria-invalid, the
  reason under the box, the tab opened) and on the server (invalid_<key>, nothing stored; Verification::invalid_number(),
  Admin::invalid_setting_number_efb()), never clamped; the verification limits are labelled in words (QA1-001, 010, 011).
* Fixed (QA1-004 … 006): no serious or critical axe issue on Settings › Responses, Captchas, Email, the Email Template
  designer and an open entry (labels, aria-current instead of aria-selected, decorative preview images, contrast).
* Fixed (QA1-007, 008, 026): the block's editor script comes from block.json (editorScript, editor.asset.php) with its
  text domain, so its words are translated; the shortcode is left to right; the stylesheet reaches the editor once.
* Fixed (QA1-009, 020, 028): the entry viewer is a full-width sheet on a phone; a reloaded entries URL keeps the list and
  its &efbq= filters; efb_admin_date_efb() writes the admin's dates in the site's digits.
* Fixed (QA1-012): Versions › Compare normalises types (1 / true / "1"), ignores EfbVersion and amount, names changes
  in words.
* Fixed (QA1-013, FR): a lapsed Pro site shows its "Pro paused" card, not the plan picker; an update from before 4.3.2
  ends the first-run e-mail check.
* Fixed (QA1-002, 003, 016 … 019, 022, 024, 025, 027, 029): the builder's fields are tab stops that open with Enter, with
  a "Skip to the form" link; the shared dialog manages focus (Esc, trap, return); the Jalali / Hijri tiles add Date
  (advanced) with that calendar; help "?" and doc links are real links (efb_help_link_efb()) and a topic without a page
  has no Help; the auto-save offer only on the forms list and the template chooser; one phrase per template title and
  per "deleted" message; _n() in the CAPTCHA notice; the visitor's confirmation e-mail headline; text-free Panel
  artwork; stacked plan cards on a phone; the save dialog's shortcode panel is not clipped.
* Fixed (QA1-015, I18N-05): the Pro box lists Pro-only features of the registry; the Pro price comes from the server
  (Builder_Api::pro_price(), efb_var.pro_price, filter efb_pro_price, option emsfb_pro_price; Toman on fa_IR) and the
  phrase "%s/year" keeps a placeholder.
* Fixed (QA1-023): the e-mail template names the plugin's address {efb_plugin_url} (resolved when sending and drawing);
  the update migrates stored addresses of any host once.
* Fixed (QA5-002): the builder sends base_hash with Save (update_form_Emsfb); a form saved elsewhere since it was opened
  answers stale_hash (nothing written) and the builder asks "Overwrite" (overwrite=1) or "Reload".
* Fixed (QA3-014): priced choices and totals use Payments\Money::format() / efbPayCoreEfb.format() like the payment
  summary. Settings › Payments: "Move every form back" (REST rollback-all) and the server's reason when a Move back
  is refused. capabilities.payments.migration.backup = "the form before the latest move".
* Fixed: "Unlock D&D File Upload" (the feature name was escaped twice); "1 entry will be removed".
* Fixed (FX-PUB, QA-2): the visitor runtime. A Jalali / Hijri date field whose add-on folder is missing draws the
  SPLIT §4.1 notice and is not required (addNewElement_efb() returned the whole-form wait card as a string: stray
  "<", ReferenceError, Send dead); efbBuilderApi.newItems('pdate' / 'ardate') and Builder_Api::template_addon() answer
  addon_required without the add-on (QA2-001). The mobile field starts on the site's country
  (Formbuilder::mobile_country_efb()) and a refused number stops the step (QA2-002). Persian / Arabic digits typed or
  pasted into a Number field are written as 0-9 (QA2-003). The multiselect is a button + listbox with the keyboard
  (bootstrap-select.min-efb.js), the stars and Yes / No are radio groups in the Tab order (QA2-004, QA2-005). A failed
  send that is not the visitor's offers "Try again" and resends when back online (QA2-006). After a Conditional Logic
  jump, Next on an empty required field focuses it; a jump applied during the Next check no longer reports the step it
  lands on (QA2-007, QA5-014). The same form twice on a page keeps one copy (QA2-008). Elementor / WPBakery print
  nothing for a draft (QA2-009). Google's published reCAPTCHA test secret skips the hostname check
  (efb_captcha_check_hostname, QA2-010). A CAPTCHA whose script cannot load says so (captchaNoLoad, QA2-011). RTL
  Previous / Next order and arrows (QA2-012). The code finder: Enter, a wrong code keeps the box, reply dates in words
  (QA2-014). E-mails: no trailing comma, terms links, checkbox choices on one row (QA2-015); "1 place left" and no
  stale line over a full form, left_text in forms/gate/state (QA2-016); the page-builder placeholder's mojibake
  (QA2-017); the designed template's direction "auto" and <bdi> codes (QA2-018); no weekly-report option reads and no
  response-viewer CSS on visitor pages (QA2-019); repeater rows named in the error summary (QA2-020).
* Fixed (FX-PUB): a refused file type lists the allowed types (fileTypeNotAllowed); an upload over post_max_size is
  answered "too large" with wp_max_upload_size() (QA4-008); no "Undefined property show_icon …" for 4.3.1-shape forms
  (QA4-010); with the Conditional Logic add-on off a field targeted by a rule is optional (Split::unrequire_absent());
  insertItems(newItems('steps')) adds a third step; a new File field takes all formats and the terms field links the
  site's privacy policy page (efb_var.privacy_url).

* Changed (PC-01): Plugin Check on the release zip: 282 errors and 1,791 warnings before, 5 errors after (each with a written waiver: docs/release/plugin-check-waivers.md). Translatable strings with placeholders carry translator comments and number them; output is escaped (the notices' inline scripts through wp_print_inline_script_tag()); IN (...) lists are one placeholder per value and tables %i; wp_parse_url, wp_delete_file, wp_mkdir_p, wp_is_writable and gmdate replace the PHP functions; the streaming writers (XLSX, CSV export, the upload scan) are annotated. Unused files removed: includes/class-Emsfb-webhook.php (a test route), class-Emsfb-requirement.php, integrate-wpb.php and five images.
* Fixed: an e-mail template or HTML field with a style url() pointing to a host made the save fail on PHP 8 (validate_url_efb() read a global that was never set). The add-on server's error sentence showed "%s" instead of the server and the code.
* Changed (SEC-02): the Panel's entry charts use the plugin's Chart.js (no Google Charts), and Create and the Panel read the country list (js/wp/countries.js, fetched with the other lists by bin/fetch-public-assets.sh) and the province lists from the plugin.
* Security (SEC-05): the option emsfb_settings (a copy of the settings row) holds each secret as "••••••" and is no longer autoloaded; every writer goes through Emsfb::settings_option_efb(). The settings row itself is unchanged (see docs/feature-gaps/BUGS-FOUND.fa.md, B6).
* Fixed (BUGS-FOUND 214): a gateway's own currency exponent (efb_currency_exponent with the gateway) applies to the display, the refund boxes, e-mails, messages and the page's amounts (Money::format() takes the gateway; Registry::public_info()['exponents']; payment-core-efb.js quote() ctx.exponents).
* Developers (B6): contract logic 1.1 (min 1) in efb_addon_contract_versions(); count_gte / count_lte kept by the logic save and Builder_Api::LOGIC_COMPARES (core reads them as gte / lte); efb:upload on document (detail {form_id, field_id, state, count}) when a file finishes, fails or is removed; efbFunction::send_email_state_new() passes the 8th argument (content type); the 12-hour emsfb_tables_ok check records the statistics table and the form status column (a form view asks neither).
* Fixed: the Pro gem after a field setting's label no longer covers the label (RTL and LTR); Settings › Privacy's descriptions are the size of the other descriptions; the survey template's labels and the answers of several admin and REST routes are translatable (PUB-03).

* New (LIFE-01): Settings › Privacy › "Delete all data when the plugin is deleted" (off by default). Deleting the plugin from the Plugins screen always removes its scheduled events, transients and temporary preview drafts; with the switch on it also removes the plugin's tables, options, private upload folder, the Media Library files of entries, its post and user meta, capabilities and roles. On a network each site decides for itself; user meta goes once no site keeps its data. Deactivating removes every scheduled event of the plugin and its add-ons (four named ones before) and deletes nothing else. Developers: uninstall.php, Emsfb\Lifecycle (includes/class-Emsfb-lifecycle.php), setting prv_delete_on_uninstall (registry setting:privacy_site).
* Security (SEC-04 follow-up): password answers stored before 4.3.2 (password fields, the register and login forms' password rows, and their edits in the entries' history) are replaced by the same mask (••••) once, after the update, in the background (WP-Cron, slices of 2,000 entry ids, an 8-second budget per run, continued from an administrator's screen when WP-Cron does not run). Until it has reached an entry, the CSV and Excel exports mask those answers themselves. The result is kept in the option emsfb_password_scrub and written once to the PHP error log. 1,000,000 entries on MySQL 8.4: three runs.
* Changed (FR-01): the first-run plan chooser says what each plan gets, as the add-on catalogue gives it: Free lists its free add-ons; Free Plus lists payments (Stripe, PayPal and other gateways) and conditional logic, calculations and CAPTCHA as its free add-ons and the small "Powered by Easy Form Builder" link; only the Pro add-ons are locked; Pro lists every add-on and Human Shield. One badge (Recommended); readable colours (at least 4.5:1) and a named Close button.
* Fixed (BUG-03): a field settings edit reaches the form within 150 ms on any form (on a form with a country list it took about 2 s, behind a busy overlay); Settings › Localization's inputs have their own ids (efbLoc_<key>; a phrase key could collide with another id of the screen) and names; the global search shows dates in the site's format and digits like the entries list, and a partial search works on SQLite (WordPress Playground).
* i18n (PUB-03): the ready-made form templates' texts (labels, help texts, step titles, headings, choices) are 220 phrases of the phrase list (includes/admin/template-phrases-efb.php, context "form template", efb_var.text on the Create screen), so a form made from a template follows the site's language once translated; typos in the templates fixed. The builder's Calculation tile and the e-mail subject box use phrases.
* Changed (UPG-01): the 4.3.2 schema update changes emsfb_msg_ in two statements instead of five: the eight new columns with ALGORITHM=INSTANT (no table copy), then the six keys together; a server without INSTANT gets one combined ALTER. 1,000,000 entries: 28.3 s → 5.9 s on MySQL 8.4, 6.2 s on MariaDB 10.11 (Install::upgrade_msg_table()).
* Changed (COMP-01): on WordPress older than 6.3 (a copy of 4.3.2 on a site that was not updated) the plugin loads nothing and shows "Easy Form Builder is paused" with both versions and an Update WordPress button; forms, entries and settings are kept. mb_chr(), mb_ord() and utf8_encode() are no longer called (mbstring is optional; WordPress polyfills them only from 7.1 / 6.9). Removed the workarounds for WordPress before 6.3 (block registration, CAPTCHA script loading, Cache_Compat); object-cache group flushes only where the cache supports them (wp_cache_supports).
* New: a form made by an assistant (AutoPilot) or an import can be a draft: visitors do not see it (its shortcode, block and widgets show nothing), administrators see it with a notice so they can try it. Publish it with the Publish button in the forms list.
* Fix: on a phone, the form builder's field settings panel takes the whole width (it was a narrow strip and the payment field's settings did not fit), and Settings uses the full width of the screen on every tab (texts and the "Forms moved" list wrapped word by word).
* Fix: the form builder draws a form made elsewhere (an import or an assistant) whose settings have no button texts, icons or colours, and its settings panels open; it uses the values of a new form, and the form keeps them when you save it.
* Fix: in the form builder's payment field settings, the "Gateway settings and keys" link has a line of its own (it sat beside the next field's label).
* Security (QA4-002): site-wide settings, add-ons, Development Mode, the uploads cleanup, the e-mail check and onboarding recipient, and the Telegram, Google Sheets and Auto-Populate API settings need `manage_options` (`efbFunction::user_permission_efb_site_settings()`); the `Emsfb` capability alone keeps forms and entries (and, by owner decision, the plan). `remove_addons_Emsfb` accepts only an add-on code of `Addon_Catalog` (it set any settings key to 0). Who may do what: docs/EFB-CORE-KNOWLEDGE-BASE.en.md.
* Security (QA4-004, owner decision 13): the upload token is required by default (`emsfb_upload_require_token`, filter false for the 4.3.1 rule); recordings and the single-file path send it. Tools › Site Health test `efb_private_uploads` fetches a canary file from `uploads/efb-private`: critical when it is served, with `Uploads::nginx_rule()`.
* Fixed (QA4-001, QA4-011, QA4-012): with "Delete all data when the plugin is deleted" the visitors' uploads of the default storage (`uploads/<y>/<m>/efb-PLG-*`, `efb-rec-*`, the files the entries name) and the `emsfb_pending_uploads` ledger go too; a role is never removed (only the `Emsfb*` capabilities); the plugin's row on the Plugins screen says whether deleting keeps or deletes the data.
* Fixed (QA4-005): an add-on whose files are gone after an update is "missing / Install", not "Update … to keep using it" (`Addon_Catalog::files_present()`).
* Fixed (QA4-006): below WordPress 6.3 (paused) the shortcodes and the block render nothing and the plugin's screens show the pause message instead of WordPress' 403; the activation's file check no longer fatals in WP-Cron.
* Fixed (QA4-007): the conversion rate is never above 100 % ("—" when submissions exceed views after the statistics backfill, `Entries::conversion_rate()`).
* Changed (QA4-009, QA-5): the rating invitation asks after a week and three entries, never while add-ons must be reinstalled or for three days after an update (`emsfb_updated_at`), never on the first visit, in the builder or over the AI assistant's dock.
* Fixed (QA3-002): an outdated add-on updates from the Add-ons page (card "Update"): `Emsfb\Addon_Installer` unzips into a staging folder, swaps the folder in one rename (rolled back on failure) and invalidates OPcache; failures are reported; Remove deletes the add-on's files. The recovery uses the same path.
* Fixed (QA3-007, QA1-014): the add-on catalogue script's address has no port (a 404 Chrome blocked), loads async, and the page shows the catalogue built from `Addon_Catalog` (all add-ons, incl. PAY00, CAPTCHA, Persia Payment, Visitor Country) within about a second when the server is away (it waited about 30 s); the card grid stays even.
* Fixed (QA3-004, QA3-006): the Pro card's title is readable (1.02:1 before) and the Telegram screen without Pro shows it (it was blank). Human Shield (Pro) shows the Pro card on other plans; Address search leaves the menu on Free.
* Changed (QA5-001): `Form_Package::delete($form_id, $token, $force = false)` refuses with 409 `package_form_in_use` a form changed since its import, published or with entries, unless `$force`.
* Developers: 19 methods without a caller removed to keep the core size budget (none used by an add-on, Data Tools or the AI plugin).
* Developers: form package 1.2: Form_Package::read() / update() (form.update: patches to a saved form with base_hash, through the builder's own save path; refusals stale_hash, unknown_field, addon_required, plan_required; REST Emsfb/v1/admin/forms/{id}/package and /update for the plugin's admins, cookie + nonce or an application password), import() name_policy (keep | suffix | fail) and status (draft), publish() / set_status() (REST /publish, /status), action efb_form_status_changed; efb:form-saved and the efb_form_saved / efb_form_created hooks carry the save warnings; field_templates_efb() answers addon_required for an add-on's field while the add-on is not active (as efbBuilderApi.newItems()); capabilities.payment_gateways lists every gateway with installed / active / plan / addon; capabilities.save_events, form_update, form_status; EMSFB_PLUGIN_VERSION and the registry say 4.3.2 (the registry said 4.3.1). No database change (a draft is the existing emsfb_form.status column = 2).
* Changed (Conditional Logic): a jump that moves the visitor forward (for example from step 2 to step 4) because of their answers skips the steps in between: their fields are no longer required, checked, priced or stored, and Previous goes back over them. If the visitor goes back and chooses differently, those steps are asked again. A payment form whose payment field is on a skipped step takes no payment. The server applies the same rule to the answers it receives.
* Changed (payment gateways are add-ons): the Stripe, PayPal and Persia Pay code left the plugin for their add-ons. Their field settings in the builder, their keys (each add-on has its own settings page; Settings › Payments links to it; the keys moved there once, automatically, encrypted), their server-side pricing and their payments pages are the add-ons'. A site whose add-on is older than this version shows "Update … to keep using it with this version." instead of taking a payment; nothing is charged twice.
* New: the Stripe, PayPal and Persia Pay (ZarinPal) add-ons each add a payment field on the payment framework (Stripe Checkout, PayPal, ZarinPal: pay, retry, receipts, refunds from the Payments screen). Forms that used the older fields are moved to them one by one, automatically: the same amounts, earlier entries and receipts as they were. Settings › Payments lists the moved forms, with "Move back" (the form exactly as before) and "Move now". Subscription forms stay on the older field.
* Developers: the legacy_gateway contract is 2.2 and deprecated (removed in version 5.0); an older add-on calling the old members gets _doing_it_wrong() and a clear answer, never a fatal error. Filters efb_legacy_gateway_target, efb_legacy_gateway_seed, efb_legacy_gateway_same_amount, efb_legacy_migration_auto; REST Emsfb/v1/payments/legacy-migration (administrators); capabilities.legacy_gateways for AutoPilot; readiness code pay_addon_update.
* Fix: Persia Pay no longer changes the time zone of the whole request; the Stripe payments table is created by the Stripe add-on on its first request (a site that never opened its payments page had none) and is never dropped.
* New: the form builder's preview checks the fields as the published form does (the form's "Check fields" timing, the message under the field, the error summary on Next / Send).
* Fix: an Iran or Saudi address made by AutoPilot loads its province list on the page (a text box when the list cannot load) and its answer is accepted; such a form could not be sent before.
* Fix: on right-to-left sites, untranslated English sentences (the "Needs the … add-on" notice, Payments › Gateway settings, the builder's help texts, the "Address search" screen) keep their full stop at the end and start on the right.
* Fix: on a phone, the form builder's field settings open on screen (they opened far below the page).
* Changed: a new Email field now sends the visitor a confirmation e-mail to the address they type, by default (the form's first e-mail field; turn it off with the field's "Enable email notifications" switch). Forms you already saved keep their setting. The switch no longer refuses to turn on before mail delivery is confirmed; it shows the delivery check as a hint instead.
* Fix: forms made from a template lost the accessibility defaults of a new form when the builder reopened them.
* Developers: a reply stored through the portal core contract (F20-core, inert without its separate add-on) gets a complete notification e-mail (its sentences were empty).
* Fix: PHP warnings on booking forms, on forms made through the builder API, and on account recovery requests.
* Fix: two settings saved at the same moment (for example a CAPTCHA add-on's page and the plugin's own settings, or an add-on install) no longer undo each other's change.
* Fix: the Address & Postal add-on's "Address search" page shows only its own messages, like the CAPTCHA add-ons' pages (developers: efb_addon_screen_own_notices() for any add-on settings page).
* Fix: a message from the server about a field (for example "already used") no longer disappears a moment later when the visitor had just typed in that field.
* Developers: a form package's dropped[] entry for a setting of an add-on that is not active now has code addon_inactive like the other entries (reason is kept).
* Fix: a site whose licence the server ended (or does not know) no longer gets Pro back while the licence server cannot be reached; the outage grace period only keeps an active licence on.
* Changed: three files no page used are gone from the plugin package (an old copy of jQuery and two PNG flag images; the phone field uses the WebP flags): about 340 KB smaller.
* Fix: a ticket closed in the entries viewer stays closed: a reply, opening the entry again or "Mark as read" no longer reopen it (the Closed tab lists it); a visitor's reply on a read or closed entry shows as a new reply, also in the admin menu's count; a pending payment keeps its state.
* Fix: a CAPTCHA add-on's settings page shows only its own messages, not the plugin's other notices.
* Changed: the Address & Postal add-on keeps its "Address search" settings with the plugin's other settings (moved once, automatically).
* Developers: customer portal core contract v1 (`EMSFB_PORTAL_API`, `efb_portal_*()` functions, `efb_entry_stored` and related actions) for a separate portal add-on; it does nothing until that add-on is active. A private owner index lets a visitor's e-mail or phone find their own entries (built in the background, off until the add-on turns it on). Database version 1.12.
* Fix: the verification code e-mail keeps punctuation in place when an untranslated sentence appears in a right-to-left template.
* Fix: a form whose Conditional Logic rules came from an assistant (AutoPilot), an import or form.update, without the builder's logic switch, now shows and hides its fields in the browser too (only the server applied the rules).
* Security: the e-mail debug log (EMSFB_EMAIL_DEBUG, for support only) is written to the private uploads folder (uploads/efb-private/logs/, under a name no one can guess) instead of wp-content/efb-email-debug.log, which most servers serve to anyone; the old file is removed on the next debug write, and debug.log gets the recipients masked and no subject.
* Fix: the Send, Next and Previous labels (and the confirmation code's Copy button) stay readable whatever colours a form was given: a label colour with less than 4.5:1 against its button is drawn white or near-black on the page (an old or imported form could show grey text on a dark blue button). The form keeps its colours in the builder.
* Fix: a signature drawn with the mouse or a finger is saved as drawn, no longer squeezed (the pad's pixels follow its size on the page, sharp on high-density screens), and two signature fields on one page each keep their own drawing. Signatures saved before are unchanged.
* Fix: answers the form gives visitors are in the site's language: an invalid e-mail on the password recovery form, a reply to a confirmation code that cannot be found, a form that no longer exists, and an upload that failed (they were fixed English sentences such as "Track not found" or "Invalid JSON response from server").
* Fix: the Help buttons of the add-ons open that add-on's own guide in your language, or are not shown while it has none (they opened the documentation index); the builder's help for Conditional Logic and the map's points opens its guide; a card's Help no longer opens two tabs.
* Fix: in the form builder (from 992 px wide) a field's settings panel opens below the toolbar, beside the canvas, so Save, Undo and Preview stay on the screen while you edit; its head says "Done" and has a named Close button, Esc closes it and returns you to the field. Right-to-left screens get it on the right.
* Changed: Easy Form Builder's screens show one notice at a time, the most important; the others wait behind a "Notices" bell in the plugin's header (with their number), and none is shown above the form builder. Every notice has "Remind me in 7 days". Payment setup problems are a badge on each form in the forms list and a summary on Payments, no longer a red notice on every screen; red is kept for payments that fail now.
* Changed: the Data Tools card waits until the site has a form.
* Developers: Emsfb\Notices::add( $id, array( 'render' => callable, 'priority' => int|callable, 'severity' => 'error'|'warning'|'info'|'success', 'screens' => 'efb'|'admin'|callable, 'snooze' => bool ) ) registers a notice with the manager; the plugin's and its add-ons' callbacks registered straight on admin_notices are taken in too.
* Fix (accessibility): the plugin's admin screens meet WCAG 2.1 AA contrast (darker muted text, palette labels, codes, links and pink text; the public form is unchanged), its images have text alternatives, icon-only links and buttons have names, and lists no longer hold a stray line element.
* Changed: Settings › Captchas links to the Human Shield add-on's settings while it is on (its screen links back).
* Developers: Emsfb\Addon_Catalog::screen_header() and ::save_bar() draw the header (back link, title, actions, the add-on's Help, a description) and the sticky save bar of an add-on's settings screen in the plugin's screen form.
* Security: the answer to a password field of an ordinary form is used to check it and is never stored, e-mailed, exported or sent to integrations: entries, e-mails and exports show •••• instead (owner decision). Registration and login forms work as before. Notification e-mails of older entries mask it too.
* Faster form pages: a page with a form reads the add-on, privacy and SMS options in one database query instead of about twenty (a simple form page went from about 33 queries of the plugin to 12 on a site without an object cache).
* Faster form pages: the response viewer (about 124 KB of JavaScript: the conversation, replies, PDF and CSV) loads only on the confirmation code finder and a response page, not on every form page; the form's settings are printed in the page once instead of twice (and once per form, not once per form for each form before it, on a page with several forms). A simple form page now loads about 159 KB of JavaScript (gzipped, jQuery included; was about 194 KB).
* Developers: the form page's scripts and styles load their minified twin (x.min.js / x.min.css next to x.js / x.css, made by the release build) when it is there and not older than the source, unless SCRIPT_DEBUG is on (_Public::asset_efb()); the version of each file is its time, so caches never keep an older copy. ajax_object_efm.ajax_value is "@vf" on a form page (core-efb.js reads the last of ajax_value_forms).
* Privacy: the response box's fonts, the province and city lists of address fields and the poll results chart come from the plugin itself (bundled when the release is built) instead of Google Fonts and jsDelivr, so visitors' addresses are not sent there and province lists work where those services are blocked. A PDF of a response is printed with the system's fonts (it used to load Google Fonts). A custom font address set in Settings › Response box is still loaded from where you put it.
* Fix: a file uploaded to a site whose uploads address starts with www. is accepted when the visitor opened the site without www (the check looked for "wwww.").
* Developers: EmsfbEmailHandler::send_email_state_new() takes the e-mail's content type as an eighth argument (traking_link, message_link or just_message); a state given as a string is no longer read as a list (its third letter was taken as the type). EmsfbEmailHandler::content_type_for_state().

* New: Localized names. Adding a Name field asks for its format: one field, first and last name, with a title (Mr, Mrs, Dr … in the site's language), with a middle name, or the four- and three-part Arabic names (with father's and grandfather's name). New name fields accept letters of any language, spaces, hyphens, apostrophes and the zero-width non-joiner, and refuse digits ("Letters only", per field).
* New: Address formats. Adding an Address asks for the country's layout: Iran (province, city, street, alley, plaque, floor, unit, 10-digit postal code), Saudi National Address (building number, street, district, region, city, postal code, additional number, short address; optionally "the short address or the full address"), United Arab Emirates (with Makani and P.O. Box), Egypt, United States, United Kingdom, Canada, Germany, Japan, Türkiye, or International. The site's language suggests one, and "Always use this" remembers your choice.
* New: In those addresses Persian and Arabic digits are accepted and stored as Latin digits, postal codes are checked and stored in the country's shape (1234567890, SW1A 1AA, K1A 0B1, 20500-0003, 100-0001), the city list follows the chosen province (a text box takes its place if the list cannot load), and every part gets the right browser autofill. The parts of a name or address are announced as one group to screen readers.
* New: Entries, the entries CSV, the PDF and notification e-mails show each name and address as one line in its country's order, besides its parts.
* New: Address search (Address & Postal add-on, Free Plus and Pro): a "Find address" button in an address fills its parts from OpenStreetMap, and a postal code fills the city; searches go through your site by default (cached, limited, nothing logged), never while typing; Easy Form Builder → Address search chooses through this site, from the visitor's browser, or off, and the service address.
* Fix: first and last name fields told browsers "autofill off"; they now say given-name / family-name, so browsers fill them.
* Fix: saving the settings no longer reports success (and caches the new values for half an hour) when the database refused the write.
* Developers: efb_address_display() / efb_name_display() / efb_name_address_groups() and window.EFBNameAddr (display contract version 1: docs/name-address/display-contract.en.md); the field keys cmp_grp, cmp_kind, name_format, name_part, name_strict, addr_scheme, addr_part, addr_cascade, addr_lookup, addr_req_mode; efbBuilderApi.newItems('name'|'address', {format|scheme}) and setNameAddress(); the filters efb_name_address_data, efb_geo_strict_lists, efb_addr_group_extra.

* Changed: Each CAPTCHA add-on (Cloudflare Turnstile, hCaptcha, ALTCHA, Question CAPTCHA, Friendly Captcha) has its own settings page. Settings › Captchas stays short: Google's keys, the reply box and — only while a CAPTCHA add-on is installed and active — a list with each add-on's status (Ready, Needs keys, Off for your plan) and a Settings button. With no CAPTCHA add-on there is nothing about them on the screen. Their settings are now kept with the plugin's other settings (keys still encrypted and never shown again); they move there once, automatically, and are not touched when you save the main Settings window.
* Developers: an add-on's CAPTCHA settings are the key captcha_<id> of the settings row, written only through set_setting_Emsfb() (Captcha\Admin::save() / import() / remove()); core draws the add-on's screen (admin.php?page=Emsfb_captcha&provider=<id>) from settings_schema(). docs/spam/captcha-contract.en.md.

* New: Accessible errors on every form. A field's error is written under it as text, is tied to the field for screen readers (aria-invalid, aria-describedby) and is read once; on phones it is words again (no more icon-only warning) and sits between the label and the field instead of over the label, right to left too.
* New: "Check fields when the visitor leaves a field" (Form settings, the default for new forms): no error while someone is still typing, the error when a changed field is left, gone as soon as it is right; Next / Send still check everything. Forms made before keep checking when a value changes.
* New: A list of problems at the top of the form after Next / Send (new forms), with links that take the keyboard to each field; a field the server refuses brings the visitor back to it with the same list.
* New: Keyboard and screen-reader focus follows the form: each step is announced ("Step 2 of 4"), the thank-you message gets the focus after sending, and a redirect after sending waits longer with a "Stay on this page" button.
* New: Browser autofill for every field type (name, e-mail, phone, address, country / state / city lists, amounts), and a "Browser autofill" choice per field. Older forms keep "off" on plain text fields.
* New: "Warn visitors who leave a half-filled form" (Form settings; on for new forms of several steps).
* Changed: the mobile field is a phone input (type tel), the required star is no longer read aloud, choice groups are named by their label, and Windows high contrast, "more contrast" and "reduce motion" are respected.
* Fix: on right-to-left sites labels sit on the right even when their text is English; the repeater group's help text is no longer indented; the form versions dialog shows dates in your calendar and digits; lists in the entries screen (spam reasons, addresses) are joined the language's way.
* Developers: window.EFBInline (inline validation and the error summary for any page, contract version 1: docs/a11y/inline-validation-contract.en.md) with efb_inline_api() / efb_inline_error(); efb_form_defaults_efb(); efbBuilderApi.setAutocomplete(); the event efb:submit-result.

* New: Pro renewal reminder: seven days before your Pro subscription ends, your own site tells you, with a short notice on the plugin's screens and the WordPress dashboard (administrators only) and one e-mail sent by the site, that says what stays safe and what pauses if it lapses. "Already renewed? Check again" confirms a renewal at once. After the end, one "Pro features are paused" e-mail. It needs the expiry date from the licence server, so it starts when the server sends it; nothing is shown on Persian (fa_IR) sites, on Free and Free Plus, or with automatic renewal. Settings › General shows "Pro is active until …" and the switch "Email me before my Pro subscription ends".
* Fix: the message shown when Pro is off said "Your activation code has expired!" for every cause; it now says why (the subscription ended, the code was made for another domain, or the licence could not be confirmed for a while).
* Security: renew links (the notice, the add-on pages, the licence e-mails) no longer contain your activation code.
* Fix: a translation with an apostrophe in the "Pro is off" card no longer stops the plugin's admin pages from loading.

* Fix: a paid form whose amount comes from a calculation is never charged without that part: when the calculation engine is not available the payment is refused before the payment page opens (pay_calc_unavailable), and the payment readiness says why.
* Fix: saving a form on a site where an add-on it uses is not active keeps that add-on's settings (formulas, number format, typed-signature mode, postal format, booking dates) instead of removing them; the save answer says which were kept (addon_keys_kept).
* Changed: number format, steps and decimals, two-handle sliders, calculated fields, calculation values on choices and a calculated amount to pay are now the Numbers & Calculations add-on (Free Plus and Pro). Sites that use them get the add-on installed automatically on update (a Free site that already used them keeps them). Without it, a number is the plain number box (Persian and Arabic digits are still read) and a calculated field shows a short notice; the form still sends, and its settings are kept.
* Changed: signing by typing the name (the e-signature field's "Draw or type") is now the Typed Signature add-on (Free Plus and Pro), with its fonts. Without it every signature is drawn; signatures already typed still show the name.
* Changed: the postal-code formats of 18 countries and the map field (Location Picker) are now the Address & Postal add-on, which every plan can install. It ships Leaflet itself (no request to unpkg.com any more). Without it a postal code is a plain text box and the map shows a short notice; the form still sends, and stored map answers still show.
* Changed: editing booking forms (a choice's end date and number of places, the Booking form template) is now the Booking add-on (Pro). Booking forms already on a site keep working without it: an expired choice is still refused and places are still counted.
* Changed: importing forms from Contact Form 7 now lives in the free plugin Easy Form Builder Data Tools (with form export and import, other form plugins and backups); the forms panel offers to install it.
* Fix: a site whose Easy Form Builder tables were removed while the plugin stays active gets them back once, instead of a database error on every page.
* New: the Visitor Country add-on (Free Plus and Pro) fills a hidden field with the visitor's country ({geo:country}, {geo:country_name}) when the form is sent, with the service you choose (ipinfo.io or ip-api.com) and your own API key; the IP address is not stored by it, and the form shows a short notice. The "coming soon" entry is gone from the hidden field's list.
* Changed: the Stripe, PayPal and PersiaPay code now lives in their add-ons (core keeps a small versioned bridge). A form with one of these payment fields shows "Online payment for this form is not available right now" and is not sent while its add-on is missing or from an older version; nothing is charged.
* Developers: form package contract 1.1: import() returns an import_token, Form_Package::delete($form_id, $import_token) undoes an import, Form_Package::import_entries($form_id, $rows) stores entries through the submit's own rules (docs/form-package-contract.en.md).
* Changed: after the update, the forms are read once in the background (in batches, never while a page loads); the add-ons they use are switched on and downloaded, and the forms panel lists any form that still needs an add-on, with Install (or "See the plans"), "Try again" when the add-on server could not be reached, and a badge in the forms list. The page and the core are about 500 KB smaller.
* Fix: a required map or calculated field whose add-on is not active no longer stops visitors from sending the form (it shows its notice and is not required, on the page and on the server).
* Developers: one add-on catalogue (Emsfb\Addon_Catalog, filter efb_addon_catalog) for the add-on keys, plans, required files and the builder's mirror; efb_addon_contracts() reports the contracts core keeps for features that are add-ons; capabilities.split in efb_var.capabilities.
* New: Undo and Redo in the form builder (toolbar buttons, Ctrl/Cmd+Z, Ctrl/Cmd+Shift+Z): every change — a field added, moved, duplicated or deleted, a setting, an option, a step, the form settings, a change made by the AI assistant — can be taken back; the message names what was undone.
* New: Form versions: every save keeps a version of the form (who saved it and how). The Versions dialog in the builder shows them, lists the fields of a version, compares it with the form now and restores it (it opens unsaved, so you can look before saving, and Undo takes it back). Settings › General sets how many are kept (Free 3, Free Plus 10, Pro 50).
* New: Search the field list of the builder by name or by other words (for example "phone" finds Mobile and Phone, in any language), together with the categories; Enter adds the first field found.
* New: Keyboard shortcuts in the builder (Save, Undo, Redo, search, delete / duplicate / move the selected field, close the panel, preview) that also work with Persian and Arabic keyboards, with a "Keyboard shortcuts" dialog; the one-key shortcuts / and ? can be switched off.
* Developers: a stable form package API (export, validate, import a form; add-on settings travel with it through efb_form_export_extras / efb_form_import_extras; references follow new ids through efb_form_package_remap_ids): efb_form_package_api(), see docs/form-package-contract.en.md. Database version 1.11 (table emsfb_form_rev_); efbBuilderApi.history and efbBuilderApi.revisions.
* Fixed: The "an auto-saved version exists" question could appear over the builder a few seconds after it opened.
* New: Google reCAPTCHA per form, on every plan: the v2 checkbox, v2 invisible (checked when the visitor sends), v3 with a score, and v3 that shows the v2 checkbox in the same place when the score is low. Theme, size, badge position, language, alignment, the v3 action name and your own messages per form; reCAPTCHA can load from recaptcha.net where google.com is blocked.
* New: CAPTCHA add-ons (Free Plus and Pro): Cloudflare Turnstile, hCaptcha, ALTCHA (a proof of work in the visitor's browser, nothing sent to another company), a question or sum CAPTCHA (your own questions in each language), and Friendly Captcha. Each has its own settings in Settings › Captchas and its own options in the builder.
* Changed: hCaptcha and Cloudflare Turnstile are now add-ons (Free Plus and Pro). Your keys are kept and move into the add-on when you install it; until then those forms are protected by your Google reCAPTCHA (or by the hidden field and the minimum time) and administrators see a notice listing them.
* New: Many forms on one page, each with its own CAPTCHA service, work side by side: every widget has its own id and token, each service's script loads once, widgets load when they come into view (also in popups and tabs).
* Fixed: The CAPTCHA of login and registration forms was not checked on the server; it is now (switch: "Also check the CAPTCHA on login and registration"). Password recovery is limited to 5 requests an hour per address.
* New: The reply box of a tracked entry stops bots without bothering people: with "CAPTCHA in the response box" a visitor solves the check once and is not asked again for 30 minutes (each reply renews it); the signed-in sender of the entry and your staff are never asked; a hidden trap field and limits (5 replies per conversation in 10 minutes, 20 per address in an hour; adjustable, the per-address one can be turned off). Over a limit the visitor sees when they can send again, Send counts down and the typed text is kept.
* New: A form protected by invisible reCAPTCHA (v2 invisible or v3) also checks the CAPTCHA before it sends a verification code by SMS or e-mail, so bots cannot spend your SMS credit.
* New: Sites updated from an earlier version see "Turn on recommended protection" on the plugin's screens: one click turns on the hidden field and the 3-second minimum.
* Security: CAPTCHA and payment secret keys are no longer printed in the admin pages; key fields are write-only ("Saved, ends with …", Replace, Remove).
* Developers: CAPTCHA provider contract 2 (efb_register_captcha_providers, efb_captcha_providers, efb_captcha_active_for_form, efb_reply_rate_limits, efb_secret_setting_keys; window.EFBCaptcha.provider()); efb_captcha_api() lists every service; AutoPilot answers addon_required / plan_required for a CAPTCHA service; see docs/spam/captcha-contract.en.md.

* New: Payment framework for new gateway add-ons (Mollie, Payplug, Tap, Omise, PayTabs, Paymob, Xendit, coming as separate add-ons). The amount is always computed on the server (option prices, price fields, computed prices, a fixed amount and donations with suggested amounts); a payment is recorded only after the gateway confirms it, once, whether the confirmation comes from the gateway's webhook, the visitor's return or the background check.
* New: A customizable payment field: button colour, text and icon, the order of its parts, the summary, payment methods, texts per language, phone layout (Pro). A banner tells every visitor when a gateway is in test mode.
* New: Easy Form Builder → Payments: every payment with filters and totals, its event history, re-check, refund (when the gateway supports it), and the gateway settings. Keys are stored encrypted and never shown again.
* New: For payment forms, choose when each notification goes out (after payment, when the form is sent, or both); an entry waits as "pending payment" until the payment is confirmed.
* Fixed: The builder preview of a multi-step PayPal payment form stopped with an error and its buttons did not work.
* New: Mollie add-on (iDEAL | Wero, Bancontact, cards, PayPal and more) on the new payment framework: Mollie's hosted payment page, every payment checked with Mollie before it counts, refunds from the Payments screen, separate Test and Live keys.
* New: Payplug add-on (Cartes Bancaires, Visa, Mastercard, Bancontact, Oney instalments and more, euro only) on the new payment framework: Payplug's payment page or card fields inside the form with a choice of card brand, every payment checked with Payplug before it counts, refunds from the Payments screen, separate Test and Live keys.
* New: Tap Payments add-on (Gulf: mada, KNET, BENEFIT, cards and the methods of Tap's hosted page) on the payment framework; amounts in KWD, BHD and OMR with three decimals, refunds from the Payments screen.
* New: Omise add-on (Thailand: PromptPay) on the payment framework: the visitor scans a PromptPay QR code inside the form, with a countdown, "Save QR image" and "Get a new QR code"; every payment checked with Omise before it counts, signed webhooks, separate Test and Live keys.
* New: PayTabs add-on (Saudi Arabia, UAE, Egypt, Oman, Jordan, Iraq and global: cards, mada, STC Pay, Apple Pay, KNET, Meeza QR, valU and more) on the payment framework: the PayTabs page as a redirect or inside the form, the account region per Test and Live, signed notifications checked with PayTabs, refunds, three-decimal currencies.
* New: Paymob add-on (Egypt, Saudi Arabia, UAE, Oman, Pakistan: cards, Meeza, mobile wallets, kiosk, valU, mada, STC Pay, Apple Pay and more) on the payment framework: Paymob's Unified Checkout with the methods you map to your integration IDs, callbacks checked with your HMAC secret, refunds from the Payments screen, separate Test and Live keys.
* New: Xendit add-on (Indonesia, the Philippines, Thailand, Vietnam, Malaysia, Singapore: QRIS, GCash, Maya, OVO, DANA, virtual accounts, cards and more) on the payment framework: Xendit's hosted payment page with the channels you choose, every payment checked with Xendit before it counts, refunds from the Payments screen, separate Test and Live keys.
* Improved: Payment forms explain every problem: a field the gateway needs (for example the payer's e-mail for Tap, the wallet number for Paymob, the mobile number and address for Oney) is marked required and the message appears next to that field; declined, cancelled, expired or 3-D Secure payments, insufficient funds and an unreachable gateway each have their own message with what to do next; the builder shows red the problems that stop a form from taking payments (no keys, wrong currency, two payment fields …) and the gateway settings tell you when the gateway refused your keys or its webhooks.
* Improved: Tap's payment page opens in Arabic for Arabic visitors.
* Improved: The payer's e-mail sent to a gateway is the field you chose in the payment field or the form's confirmation e-mail field, never another e-mail field of the form. Mollie can take a payer e-mail (it sends bank transfer instructions there) and no longer lists methods it cannot start from a form (Klarna, in3, Riverty, Billie, Billink, Alma, vouchers).
* Fixed: Payplug Oney instalments follow Payplug's documented rules: Italian addresses are accepted and Monaco is no longer offered, the payment is sent as an authorization that is captured once Oney accepts, with the cart and shipping details Oney requires, and an e-mail address with "+" (which Oney refuses) is caught at the field before anything is sent. While Oney reviews a request, the visitor is told so.
* Improved: A payment form that cannot take payments yet (no gateway keys, the gateway add-on switched off, a currency the gateway does not take, a payer field the gateway needs …) still saves, but visitors see "Online payment for this form is not available right now" instead of the payment field, its Send button is disabled and nothing is stored. You see what is missing with a link to fix it: in the "Saved" dialog, on the form's page while logged in, as a "Payment setup incomplete" badge in the forms list and in a notice on Easy Form Builder's screens. Adding the keys later makes the form ready without saving it again.
* Developers: database version 1.5 (payment tables); hooks efb_register_payment_gateways, efb_payment_paid, efb_payment_status_changed and more; see docs/payments/PAY00-payment-framework.fa.md.
* New: Limit how many answers a form takes; when it is full visitors see your "full" message instead of the form, and deleting an answer frees its place. Optionally show "N places left". Two visitors can never take the last place, and a payment that fails or expires gives its place back.
* New: Open and close a form on a schedule (site time) with "not open yet" and "closed" messages; a page opened just before closing can still send for the minutes you allow.
* New: Limit answers per logged-in user, IP address, browser or e-mail address, ever or per day, week, month or year (Free Plus). The IP limit follows the form's privacy setting (anonymized, or not applied when the form stores no IP).
* New: Private forms can be limited to chosen user roles; administrators always pass.
* New: "Save and continue later": visitors save their answers and get a private link (copy or e-mail) that works for the days you choose; passwords, files, signatures and payment details are never saved (Pro).
* New: Partial answers: with the visitor's consent, answers left before sending appear in a "Partial" list with progress and a masked contact (Pro).
* New: Custom CSS for one form, applied only to that form and cleaned of anything that could load content from elsewhere.
* New: Show a form in a popup: [EMS_Form_Builder id=5 popup="1" popup_label="Register"] or the block's "Show as a popup" (Free Plus).
* Fixed: A shortcode with an extra attribute (for example popup="1") showed the wrong form.
* Developers: database version 1.6 (counter and draft tables); form keys lim_*, sched_*, allowed_roles, custom_css, draft_*, partial_*; REST forms/gate, forms/draft/*, forms/partial/save; efbBuilderApi.setFormSettings() and formGate(); see docs/autopilot-integration.md (F11).
* New: Accept each value only once (for example one registration per e-mail address or ID number) in a form; answers sent before you switch it on are checked too. Two visitors can never both use the same value, a payment that fails gives the value back, and deleting an answer frees it (Free Plus).
* New: Word limits, a maximum length for text areas and a live counter ("88 characters left") that screen readers hear only near the limit; Persian and Arabic digits in the counter.
* New: A custom pattern (regular expression) for text fields, checked as you type it in the builder with a test box; patterns that could freeze a page are refused (Free Plus).
* New: Allow or block e-mail domains (example.com, *.edu, name@example.com) (Free Plus).
* New: Read-only fields, prefix and suffix ("+98", "kg"), and the description above the field, while the field is in focus, or behind a "?" help button.
* New: Passwords: ask to type the password twice, a strength meter, a minimum strength (Free Plus) and a show-password button.
* New: Your own error message for each rule (Free Plus); the server now answers with the field's own "required" message too.
* Fixed: An e-mail or website field whose maximum length was left empty refused every answer on the server.
* Fixed: A text area counted each line break as three characters, and a too-short text field did not stop Next in the browser.
* Developers: database version 1.7 (unique-value table); field keys unique, wmin, wmax, counter, pattern_b64, email_domain_mode, email_domains, readonly, prefix, suffix, message_place, pw_*, msg_*; efbBuilderApi.setFieldRules(); error codes efb_field_<rule>; see docs/autopilot-integration.md (F03).
* New: "Confirm email": ask visitors to type their e-mail address twice; the two copies are compared in the page and on the server, pasting is allowed (all plans).
* New: Verify an e-mail address with a one-time code before the form is sent: a "Send code" button next to the field, a 6-digit code by e-mail through your e-mail template (no link in it), entered in one box that accepts Persian and Arabic digits and pasted text; the form cannot be sent with an address that was not verified, and changing the address asks for a new code (Free Plus).
* New: Verify a mobile number with an SMS code, sent through the WP SMS plugin (its gateway and settings) with the SMS add-on; codes fill in by themselves on Android Chrome and iPhone; choose the countries that may receive codes (Pro).
* New: Settings → Verification: which channels can send now, countries, code length and lifetime, daily budgets and limits, a test code, the last 24 hours' numbers; you get an e-mail when the daily budget is nearly used or when many codes are sent but few are entered (possible SMS fraud).
* Security: codes are random, kept only as one-way fingerprints, valid 5 minutes (SMS) or 10 minutes (e-mail), 5 tries, single use; limits per address or number, per visitor, per form and per day stop abuse such as mail bombing and SMS pumping; when a code cannot be sent (no WP SMS, e-mail sending off, a lower plan) the form refuses instead of skipping the check. Entries show "Verified by SMS / email".
* Fixed: Correcting a mobile number after typing a first valid one sent the first number with the form.
* Developers: database version 1.8 (verification table); field keys otp_enabled, otp_countries, otp_required_when, otp_btn_label, email_confirm_mode, email_retype_label, email_otp_*; REST verify/send and verify/check; request keys verifications and email_confirms; efbBuilderApi.setVerification(); filters efb_verify_providers (a sender interface for add-ons), efb_verify_limit, efb_verify_plan; see docs/autopilot-integration.md (F02).
* New: "Date (advanced)" field: one date field for the Gregorian, Persian (Jalali) and Hijri (Umm al-Qura and tabular) calendars, plus Buddhist, ROC and Japanese; visitors type the date (Persian and Arabic digits work) or pick it in an accessible calendar (full keyboard, right to left, a bottom sheet on phones), and may switch calendars without losing the date.
* New: Date limits: earliest and latest date (fixed, days from today, or another date field), closed weekdays and dates, only some dates, holiday lists, minimum and maximum age, a time with steps and opening hours, a stay (check-in and check-out, nights or days) or several dates; "today" follows the site's time zone and every rule is checked again on the server.
* New: Answers keep the date as YYYY-MM-DD plus the calendar the visitor used; e-mails, the response viewer, CSV and PDF show it in the site's calendar with the ISO date (Settings → "Date display for admins"); CSV gets a sortable ISO column.
* New: Conditional Logic for dates: day of the week, age at least / at most, within the next N days, nights of a stay, and before / after / between "today+N" (a date can change the price of a paid form).
* New: Upgrade an existing date, Persian date or Hijri date field to the advanced field from its settings, and go back; old fields keep working unchanged.
* Fixed: An English error sentence under a field on a right-to-left page started with its full stop.
* Developers: field type advdate (keys of docs/feature-gaps/reference/calendar-engine-contract.en.md); window.EFBCalendar; REST calendar/availability, calendar/convert, calendar/check and forms/{id}/schema; hooks efb_date_constraints, efb_date_availability, efb_date_availability_range, efb_date_before_commit, efb_date_after_commit, efb_date_commit_failed, efb_date_display_value, efb_message_deleted, efb_calendar_plan_rank, efb_calendar_default_for_locale; efbBuilderApi.setDateConfig(), upgradeDateField(), rollbackDateField(); see docs/autopilot-integration.md (F06).
* New: File fields can take several files (a maximum, a minimum and a total size), chosen at once or dropped one after another: each file has its own progress, can be removed or sent again, and the field says how many files and megabytes are left (Free Plus).
* New: Ask for a photo with the phone's front or back camera, and make photos smaller in the visitor's browser before they are sent, which also removes their location data (Free Plus).
* New: Keep a field's files in the Media Library (as private attachments, Free Plus) or in a private folder that is only reachable through WordPress: you open them from the entry, and the notification e-mail gets a download link that expires (Settings > Privacy).
* New: Attach the uploaded files to the admin notification e-mail, up to 10 MB in total (Pro).
* Improved: The files of one field are shown together in the entry, the e-mail and the CSV export (one cell, separated by " | "); images get a thumbnail.
* Security: A submitted file must have been uploaded into that form field by the same visitor (a signed upload token); another visitor's file, another form's file, a file of the media library or a file already used in an entry is refused. Upload names use a secure random generator and the upload allowance is counted atomically, per visitor and per address.
* Fixed: Recordings were never cleaned up when the form was not sent, and the browser's default file size limit was 8 MB while the server allowed 20 MB.
* Developers: field keys max_files, min_files, max_total_mb, capture, img_max_px, img_quality, file_store and form key email_attach; window.EFBUpload; efbBuilderApi.setUploadConfig(); REST files/signed; error codes efb_file_max_files, efb_file_min_files, efb_file_max_total, efb_file_not_owned; filters emsfb_upload_require_token, emsfb_upload_max_files_cap, emsfb_upload_quota_cap, emsfb_upload_ip_quota_limit, emsfb_private_upload_dir, emsfb_email_attach_max_bytes, efb_upload_plan_ok; see docs/autopilot-integration.md (F07).
* New: Hidden field: keeps a value the visitor never sees, from presets (UTM parameters, gclid / fbclid / msclkid, any address parameter or cookie, referrer, page address and title, post ID, IP address, the signed-in user, date and time, a unique ID) or your own text. It is stored with the entry, shown in the entry viewer, CSV and the owner's e-mail, and in the visitor's copy only when you choose. The server rebuilds every value it can know itself, so a visitor cannot change it.
* New: Smart tags {name:argument|fallback} in default values, placeholders, e-mail subjects, thank-you messages, confirmation messages and the redirect address, for example {get:utm_source|direct}, {user:display_name}, {date:Y-m-d}, {form:title}. An "Insert smart tag" list next to each box shows what each place accepts.
* New: Show an earlier answer in a later label, description, option or heading with {field:…} (Free Plus); it updates as the visitor types and is always shown as plain text.
* New: Prefill a field from the page address: give the field a prefill key (for example email) and link to the page with ?email=…; choose whether the visitor may still change it. Text, number, date, choices, Yes/No and rating fields can be filled; passwords and files never. The old iefb / vefb parameters are removed (they did not work).
* Security: Pages stay safe in page caches: values of a visitor or a signed-in user are filled in the browser and never written into the cached HTML; the signed-in user's login is no longer printed into form pages. Values in the redirect address are encoded and cannot change its host. Tags never read WordPress or login cookies.
* Fixed: {field_id} in an e-mail subject or redirect address gave the option's ID for radio buttons and checkboxes; it now gives the option's label ({field:id.value} gives its value). The redirect address lost {…} tags when it was saved.
* Fixed (Google Sheets add-on): An answer starting with =, +, - or @ was run as a formula in the sheet; it is now kept as text.
* Developers: field type hiddenField; field keys value, hdn_visitor_mail, prefill_key, prefill_lock (st_uses is computed on save); window.EFBSmart; efbBuilderApi.setHiddenField() and setPrefill(); capabilities.smart_tags and prefill in forms/{id}/schema; filter efb_smart_cookie_denied; see docs/autopilot-integration.md (F08).
* New: Add many options at once: paste one option per line (label|value) or pick a ready list (31 Iranian provinces, Arab states, US states, months, weekdays, satisfaction and agreement scales and more, in English, Persian and Arabic); a preview shows what will be added.
* New: A stored value for each option, separate from its label (for example "Tehran" shown, "IR-23" stored). E-mails, the entry viewer, CSV (choose label or value), Google Sheets, smart tags ({field:id.value}) and payments use it; answers sent before keep showing as before.
* New: Shuffle options for each visitor (the order stays the same while the visitor stays on the page; chosen options such as "None of the above" stay in place), an "Other" option with a text box, "Select all" for checkboxes, and a minimum and maximum number of choices for checkboxes; all of them are checked again on the server.
* New: Stock for each option (Pro): set how many times an option can be chosen; a sold-out option is disabled, hidden or marked "sold out", and "3 left" can be shown. Two visitors can never take the last place, a payment that fails or expires gives the place back, and deleting an entry frees it when you choose.
* New: Options from your site (Pro): published posts or pages, categories or tags, users (display names only) or a CSV file from the Media Library; the list is checked again when the form is sent.
* New: Ranking field (Free Plus): visitors put options in order by dragging, with up and down buttons or with the keyboard (Alt + arrow keys); screen readers hear each move. Ask for the top N only if you like.
* Fixed: A multiselect's minimum and maximum number of choices were checked only in the browser; the server now refuses an answer outside them.
* Fixed: A booking form rewrote the whole form on every submit (changes made by conditional logic could stick), counted places before the captcha and could sell the last place twice; booking messages ("this date has passed", "full") were in English on every site.
* Fixed: A text field in a booking form showed "Row not Found!" when the form was sent.
* Fixed: An option of another field could be sent for a radio or checkbox field and was stored.
* Fixed: An option label with & or ' could break the form's HTML; changing an option's ID twice lost the field's default choice.
* Changed: Contact Form 7 import: a free_text option becomes the field's "Other" option, and "Label|value" pipes become the option's stored value.
* Fixed (Google Sheets add-on): Only the last checked box of a checkbox field reached the sheet.
* Developers: database version 1.9 (stock table emsfb_stock_); option keys opt_value, op_lock, inv_cap; field keys op_random, op_other, op_other_label, op_other_max, op_select_all, minSelect, maxSelect (checkbox too), inv_on, inv_show, inv_left, inv_release, dyn_src, dyn_post_type, dyn_tax, dyn_term, dyn_role, dyn_orderby, dyn_order, dyn_limit, dyn_csv, rank_top, rank_touch; field type ranking; answer keys ov, other, ids (written by the server); error codes efb_choice_other, efb_choice_min, efb_choice_max, efb_choice_invalid, efb_choice_full, efb_choice_expired, efb_choice_ranking, efb_stock_unavailable; efbBuilderApi.setOptions() and setChoices(); filter efb_choices_plan; capabilities.choices in forms/{id}/schema; see docs/autopilot-integration.md (F05).
* New: Number fields can take a step (for example 0.5), a number of decimal places, negative and decimal minimum and maximum, a thousands separator, the decimal separator and digits of your language (Persian and Arabic digits are accepted while typing and shown if you choose), a prefix or suffix, or the form's currency. The number is formatted while the visitor types (the cursor stays where it was) and is stored as a plain number (1250000.5) that sorts and exports correctly; e-mails and the response viewer show it formatted.
* New: Calculation field (Free Plus): a result computed from other answers with + - * / ( ), round, min, max, if, sum, avg and more, for example ({price} + 50) * {qty}. It updates live and is announced to screen readers; the builder shows formula errors as you type. The server computes every result again, so a value changed in the browser is never stored. Choices can carry a calculation value (Free Plus).
* New: Range sliders with two handles (for example a budget from 10 to 50), with keyboard support (arrows, Page Up / Page Down) and right-to-left layouts.
* New: A calculation result can be the amount to pay on payment forms (Pro); it is computed on the server and a lowered amount in the request is refused.
* Fixed: Setting a minimum made a field required; a maximum like 9.5 was cut to 9 in the page; the builder refused number maximums above 524288 (for fields that use the new number settings).
* Developers: field keys num_step (the step of a number; "step" stays the form step), num_decimals, num_format, num_dec_sep, num_grp_sep, num_digits, num_prefix, num_suffix, num_currency, num_max_zero, range_mode; field type calc with formula, calc_show, calc_empty, calc_as_price (formula_err is written by the server); option key calc; answer keys disp (the display, written by the server), lo and hi (two-handle sliders); codes efb_num_nan, efb_num_dec, efb_num_step, efb_num_min, efb_num_max, efb_num_order, efb_calc_required, efb_calc_error; efbBuilderApi.setCalc() and validateFormula(); filter efb_calc_plan; capabilities.calc; one formula engine in PHP and JavaScript (\Emsfb\Calc, window.EFBCalc) with shared test vectors (docs/feature-gaps/reference/calc-test-vectors.json); see docs/autopilot-integration.md (F04).
* New: Repeater group: visitors add and remove rows of the same fields (for example one row per traveller with name, age and meal), with a minimum and maximum, the rows to show at start, a row title with its number ("Traveller {n}"), rows that fold to a summary, "Ask before removing a row", or a number of rows taken from another field. Each row is checked like any field, on the page and on the server ("Name in row 2 is required."). Keyboard and screen-reader friendly, right-to-left and phone layouts, row numbers in Persian / Arabic digits. A group of fields without repeating is Free Plus; repeating rows are Pro.
* New: Calculations per row and across rows: {price}*{qty} inside a row, sum({items.line}) or count({items}) outside it; the amount to pay can come from the rows and is computed on the server.
* New: Answers with rows: the response viewer shows a block per row, the CSV has stable columns "Group › Field #1", "Group › Field #2" and "Group (count)", the e-mail shows up to 20 rows, Google Sheets writes the rows of a field in one cell; files, choices with capacity, unique values, "Save and continue later" and the offline copy all work inside rows; privacy tools (export, erase, anonymize) cover every row.
* Fixed: The CSV export dropped every column after the 100th. The phone preview in the builder was cut at the first "#" in the form.
* Developers: field type repeater with min_rows, max_rows, default_rows, rows_from, row_label, collapsible, collapse_prev, rpt_layout, btn_add_text, rmv_confirm; field key group (the group's id) on its fields; answers keep each field's own id with rg (group) and ri (row from 0); codes efb_rpt_min, efb_rpt_max, efb_rpt_count, efb_rpt_required; filters efb_repeater_plan, efb_repeater_rows_cap, efb_repeater_children_cap, efb_repeater_groups_cap, efb_submission_rows_cap, efb_email_repeater_rows, efb_logic_supports_repeater_rows; window.EFBRepeater (addRow, removeRow, rowsOf, fieldAt); efbBuilderApi.addGroup(), moveIntoGroup(), moveOutOfGroup(), setGroup(); capabilities.repeater; forms/{id}/schema shows groups and row inputs; see docs/autopilot-integration.md (F09) and docs/repeater/README.md.

* برای یکپارچگی با AutoPilot (for the AI Site Assistant AutoPilot integration, API version 1):
* New: A builder API for assistants and integrations, window.efbBuilderApi: read, replace and insert fields, field templates without the canvas, a render that resolves when the canvas is really drawn, refresh of the open settings panel, and events for every change (efb:form-changed, efb:canvas-rendered). PHP: get_efbFunction()->field_templates_efb() and capabilities_efb(), efb_logic_validate_rules(), efb_plan_efb() and efb_plan_at_least_efb(). See docs/autopilot-integration.md.
* New: One plan and add-on registry (efb_var.capabilities) used by the builder's own locks, so a lock and its upgrade message always name the same plan.
* New: Choosing "Login" or "Register" as the form type adds the fields those forms need; Register is available again; Save refuses a login form without them.
* Improved: The SMS switch of a mobile field also turns on the form's SMS, fills the default messages and checks admin phone numbers on the server.
* Improved: A price field computed by a conditional-logic rule (for example $5 per guest) is shown in the total and charged as the server computes it.
* Improved: Installing an add-on updates the page without an immediate reload when an integration asks for it; the install answer no longer contains secret keys.
* Fixed: The state list stopped following the country after a form was saved.
* Fixed: A redirect address was shown with one slash and could switch the form back to "show a message" when saved again.
* Fixed: The redirect, sender email, weekly report, colours and reply attachments showed "Pro only" although Free Plus includes them; SMS and Telegram showed two messages.
* Fixed: Saving a form with SMS or Telegram switched on failed when that add-on was not active; it is now saved with the notification off and a warning.
* Fixed: "window.intlTelInput is not a function" on pages with a mobile field behind a cache plugin that delays scripts.
* Fixed: The settings screen could store "NotFoundEl" as a value.
* New (privacy): Settings > Privacy: store visitors' IP addresses and browsers, store them anonymized, or not at all (per form too); clear the addresses already stored.
* New (privacy): Delete or anonymize entries automatically N days after their last activity, with their replies and uploaded files; a preview says how many entries the next run removes.
* New (privacy): "Do not store entries, send notifications only" for contact and subscribe forms.
* New (privacy): Tools > Export Personal Data and Erase Personal Data include form entries; a suggested privacy policy text is added to the Privacy Policy Guide.
* Fixed: Deleting an entry or a form left its replies behind, and no delete removed the uploaded files; uploads never claimed by an entry were never cleaned up.
* Security: The visitor's IP address is taken from forwarding headers only when the request comes through a trusted proxy (filter efb_trusted_proxies), so it cannot be chosen by the visitor; debug logs no longer contain full IP addresses; the CSV export rows are no longer kept in the browser's storage.
* New: Payment gateway add-ons can add their own options to the payment field (for example which form fields hold the payer's name, e-mail or billing address), their own button text and look, a logo and amount limits per payment method, and warnings when a form is saved (for example "only accepts EUR"). The builder shows these options and warnings; payment methods can be chosen and ordered in the field.
* New: "Try again" and "Get a new QR code" start a new payment for the same entry instead of sending the form again. The page keeps checking a pending payment as long as the gateway allows (up to 24 hours).
* New: Payments screen: "Mark as refunded outside EFB" (with a reason, recorded in the payment's history), refunds that the gateway only accepted are shown as waiting and count once confirmed, and payment notices and extra buttons per gateway on its settings card; "Test connection" shows the gateway's own message.
* New: A second webhook address (/?efb_pay_webhook=…) for hosts that block the WordPress REST API for visitors.
* Fixed: A payment refunded at the gateway before the site saw it paid stayed "pending" forever; a gateway answer about another payment made the gateway resend its webhook for a day; the payment method of a pending payment was not saved.
* Changed: The payment framework ships no gateway: the Payments menu and the payment field appear once a gateway add-on is active.
* New (entries): The entries of a form load page by page from the server (20, 50 or 100), with tabs and counts (All, Unread, Starred, Payment pending, Closed, Spam, Trash), search (tracking code, answers, replies and notes) and a date range. Free Plus adds up to three conditions on answers and your own columns.
* New (entries): Star an entry, mark it as spam, move it to the trash with Undo, restore it or delete it permanently; select one page or every result. The trash empties itself after 30 days (Settings → Privacy; 0 = never).
* New (entries): Internal notes on an entry (only administrators see them) and a history of every change: who starred, moved, edited, resent or exported, and when.
* New (entries, Free Plus): Edit an entry. The same checks as the form run on the server; calculations are computed again; a paid entry keeps the amount charged; a choice the form no longer has is kept; replace or remove files; a reason goes into the history; a change made meanwhile by someone else is never overwritten.
* New (entries, Free Plus): Resend an entry's notification as it was sent, to the administrator only, to the visitor only, or (administrators) to other addresses; up to five times an hour per entry.
* New (entries): Export to CSV from the server with the columns, dates, status and filters you choose, in batches, with no column limit; Excel (XLSX) on Free Plus. Cells that start with =, +, - or @ are made safe.
* New (entries): Print one entry or up to 50, one per page, with or without the notes.
* New (entries, Free Plus): Statistics per form: views, submissions, spam and conversion for 7, 30 or 90 days; the dashboard widget lists your most viewed forms.
* New (entries): AI assistants (SitePilot AI) can read entries or only statistics when you allow it in Settings → Privacy (off by default); personal answers are hidden from them unless an administrator asks.
* Fixed: Deleting an entry from the list removed an unrelated form from the forms list instead; the entries list showed times shifted by the site's time zone; two fields with the same label shared one CSV column.
* Developers: database version 1.10 (entry_status, starred, trashed_at, edited_at; tables emsfb_msg_notes_, emsfb_msg_log_, emsfb_stats_); hooks efb_entry_updated, efb_entry_status_changed, efb_entries_purged, efb_api_entries_read; filters efb_entries_plan, efb_trash_days, efb_export_sync_max, efb_api_entries_per_minute, efb_api_entry_row; read-only functions efb_api_entries_query, efb_api_entry_get, efb_api_entries_stats and REST GET Emsfb/v1/admin/forms/{id}/entries; see docs/feature-gaps/F14-entries-management.fa.md.
* New: Review answers before submitting: after the last step visitors see their answers by step, edit a step or a single answer ("Back to review" returns them) and confirm; passwords and hidden values are never shown. Payment forms on the new payment framework show the amount the server will charge; not offered on forms that take a Stripe, PayPal or PersiaPay payment inside the form (Free).
* New: Section field: a heading that holds the fields after it in its step and can be collapsed, closed when the form opens, or kept one-open-at-a-time; a required field in a closed section opens it when the visitor sends. Without JavaScript every field stays visible (Free).
* New: Rich text editor for visitors on a text area (Free Plus): bold, italic, underline and lists, plus links, quotes and headings with the full toolbar, from the keyboard too; pasted text from Word keeps only the allowed formatting. The site keeps only those tags (links get rel="nofollow ugc noopener"), length limits count the text, and every place that shows the answer (entries, print, e-mail, CSV, Google Sheets, smart tags) shows it safely or as plain text.
* New: Show a field to everyone, only to signed-in visitors, only to guests or to chosen user roles (Pro). The site leaves the field out of the page and out of the sent answers for anyone else, and it is never required for them; a page with such a form is kept out of page caches. Administrators follow the rule too.
* New: Typed signature: a signature field can be drawn, typed or both; the typed name is drawn as a signature with a handwriting font for Latin or Persian / Arabic names, so visitors without a mouse or a touch screen can sign. Entries, the e-mail and the export say "Typed signature: name".
* Fixed: A signature drawn with a finger on a phone or tablet was not sent with the form.
* Fixed: The redirect address of a form that redirects after sending was still readable in the page.
* Developers: form keys review_before_submit, review_hide_empty, review_title, section_accordion; field type section (collapsible, collapsed); field keys rich_text, rich_toolbar, vis_mode, vis_roles, esign_mode, esign_font; answer keys fmt ("html", written by the server) and sig_mode, sig_text, sig_font; REST POST payments/quote; filters efb_layout_plan and the emsfb_cache_bypass context "vis"; window.EFBLayout, EFBRich, EFBSign; efbBuilderApi.setFieldLayout(); capabilities.layout; forms/{id}/schema per viewer with review and sections; fonts Dancing Script and Aref Ruqaa (SIL OFL) in public/assets/fonts; see docs/autopilot-integration.md (F10).

### Spam protection (F12), first published as the upgrade notice of the development builds

* New: Spam protection without a CAPTCHA (Free): a hidden trap field visitors never see or reach, a minimum time to fill (measured from when the visitor starts the form, so it works on cached pages), and an optional JavaScript check. New sites have the trap field and 3 seconds on; sites updated from an earlier version keep their behaviour until you turn them on in Settings › Captchas.
* New: Spam answers are kept in the Spam tab of the entries screen with the reason ("Hidden field filled", "Sent too fast", "Akismet", …) instead of being lost: no e-mail, SMS, Telegram, webhook or Google Sheets row is sent for them, they do not use up a form limit, a choice capacity or a booking, and "Not spam" brings one back. Or refuse spam outright. Payment, registration and login forms always refuse it. Spam is deleted after 30 days (you choose).
* New: CAPTCHA services: Google reCAPTCHA v3 (with a minimum score: low scores saved as spam or refused), hCaptcha and Cloudflare Turnstile, next to reCAPTCHA v2; one service for the site and another per form if you like. Widgets have a label and announce their errors, use the page's language and fit a phone screen.
* New: Akismet (when installed) and WordPress' own disallowed words (Settings › Discussion) check the answers; Akismet only gets the visitor's IP address as your privacy setting allows.
* New: The tracking-code box uses the site's CAPTCHA service; add-ons can use the same check outside a form (efb_captcha_verify, efb_captcha_widget).
* Fixed: A form with captcha that had ever used conditional logic was shown without the captcha and could not be sent.
* Fixed: Pages with a captcha form asked Google for a response while the page was built (slow, and the captcha did not load when the server could not reach Google).
* Fixed: In the tracking-code box, a message after a search re-created the box: a solved captcha stopped working and the typed code was cleared.
* Developers: form keys captcha_provider, rc3_threshold, spam_off, spam_akismet; filters efb_core_spam_checks, efb_spam_verdict, efb_captcha_verify_result, efb_captcha_check_hostname, efb_akismet_data, efb_spam_retention_days, efb_entries_rows; action efb_captcha_failed; functions efb_captcha_api, efb_captcha_verify, efb_captcha_widget (contract v1, docs/spam/captcha-contract.en.md); window.EFBCaptcha; submit codes efb_spam_rejected, pay_spam, efb_captcha_*; capabilities.spam and forms/{id}/schema "spam"; no database change (spam uses the entries' status from 1.10); see docs/spam/README.en.md.

### Add-ons updated with this release

Upload these add-on zips before core 4.3.2 (release order, owner decision 3); `docs/release/RELEASE.en.md` lists them with their sha256.

* Paymob: when the form has no name or mobile field, the visitor types them next to Pay (Paymob never gets "NA" for a missing value, owner decision 15); the settings show the Integration IDs as a table.
* Stripe and PayPal: the amount column of their payments table (`emsfb_pay_`) is `decimal(15,3)`; an existing `decimal(12,2)` column is widened once, so KWD, BHD, OMR and JOD amounts keep their three decimals (BUGS-FOUND row 135).
* Xendit: refunds made in the Xendit dashboard, outside Easy Form Builder, are recorded on the payment.
* Conditional Logic: conditions on the number of files of an upload field (stored as `gte` / `lte`); a jump over a step the visitor cannot see; named Undo entries in the builder; the add-on sends `efb:builder-mutate` (logic contract 1.1).
* Telegram, Google Sheets, Auto-Populate: their settings, connections and credentials need `manage_options` (QA4-002); Telegram stays quiet on the Pro card (QA3-006).
* Human Shield: its screen shows the Pro card on other plans; the upload route needs a valid token like every protected route, and "Monitor only" (the default) blocks nothing by design (QA-4 note). Address: Address search leaves the menu on the Free plan.
* The add-on screens look like the plugin's own (shared header and save bar): the Security & Spam Protection screen is now named "Human Shield" (menu and heading), and the Stripe and PayPal payment lists are "Older Stripe payments" / "Older PayPal payments", shown only while that table holds payments of the gateway.

## 4.3.1

* Improved: Forms use your theme's font; typed Jalali and Hijri dates are checked; offline answers come back after a reload; fixes for Stripe, PayPal, ZarinPal, SMS, Telegram, Google Sheets, Conditional Logic, Auto-Populate and the date pickers.

* Security: Stripe one-time payments are now verified with Stripe on the server before the form is submitted. A confirmation that fails for a moment after the card was charged is retried and the form is still submitted, and pending payments are re-checked in the background; an attempt that was declined or abandoned is marked Failed after an hour.

* Changed: Form emails are on by default. Updating from an older version turns "This site can send emails" on once if it was off (sites set up before 4.3.1 started with it off, and a failed email check used to switch it off). If you had turned it off on purpose, turn it off again in Panel > Settings > Email; it stays off. An email check no longer turns form emails off.

* Changed: The owner's notification email now includes the submitted answers and has Reply-To set to the visitor's address. The visitor's confirmation email keeps only the confirmation code and link unless the form chooses otherwise.

* Security: Fixed a stored cross-site scripting issue through the Signature field. A submitted signature must now be a complete, valid PNG image; it is stored as a re-encoded copy under the field's saved type, and every value shown in the response viewer is escaped, including responses saved by earlier versions.

* Security: Hardened every path that stores or shows visitor data. Each saved answer keeps the field's own label and type from the form, whatever the browser sent. Replies on the confirmation-code page are rebuilt from an allow-list (attachments must be uploads on this site); register forms no longer store the password or rows for fields the form does not have; notification emails are built from the validated answers and send the visitor copy to one verified address; the page link must be on this site's own domain; the confirmation-code finder escapes the code from the address bar; the response viewer only links http(s) attachments and ignores invalid colors, ratings and currencies; passwords saved by earlier versions are masked.

* Fixed: Saving the settings replaced the Stripe and PayPal secret keys with an internal placeholder, so payments then failed (Stripe answered 401). Secret keys are now saved as typed, and a placeholder is never stored or used as a key.

* Fixed: A double quote typed by a visitor showed as two apostrophes ('') in the response viewer, the entries list, the CSV export and the notification email. It now shows as a double quote, also in entries saved by earlier versions.

* Changed: In the CSV export a Signature field is a link to the signature image instead of the image data, which was thousands of characters long and could exceed a spreadsheet cell's limit. The link opens only for a logged-in user who may read responses; the signature is not made public.

* Fixed: Ticking the reCAPTCHA on the confirmation-code page sent a request the server refused, which logged a false "request failure". "Enable Google reCAPTCHA in the response box" can now be switched off after the site key was removed, and the page shows no reCAPTCHA without a site key (it used to block every lookup).

* Security: The Google reCAPTCHA on the confirmation-code page is now verified on the server for every lookup; before, it was checked only in the browser and could be skipped. Sites that turned it on (Settings > "Enable Google reCAPTCHA in the response box") need the secret key saved as well.

* Security: Uploaded archives must really be the archive type they are named as, and files containing a PHP open tag are refused.

* New: The Free plan has 20 form fields: terms and conditions, country list, star rating and heading joined the 16. Forms that already use them keep working on every plan.

* New: CSV export of form entries is free in every version.

* New: Stripe and PayPal payments are included in Free Plus.

* New: Import from Contact Form 7. The Create and Panel screens offer to copy your Contact Form 7 forms into Easy Form Builder in one click: fields, choices, required fields, notification addresses, the visitor confirmation email and the message after sending. A report for each form says what was carried over and what could not be, and why. Your Contact Form 7 forms are not changed or deleted. Optionally the new form replaces the old one on your pages; each page is saved as a revision first, so the change can be undone. The importer loads only on these screens.

* Improved: Only Persian sites check the Iran CDN; other sites no longer contact it.

* Fixed: Email addresses typed with Persian or Arabic digits, invisible direction marks, a fullwidth "@" or a Persian decimal separator were rejected as not valid (in forms, registration, login and password recovery).

* Fixed: The email check reported "not received" when the test message took more than 45 seconds to arrive (common with Microsoft 365 and hosts that queue mail). It now waits until the message arrives or the test expires.

* Fixed: The email check forced its own sender address over the one an SMTP plugin sets, so it failed on Microsoft 365 while form emails arrived. It now sends exactly like a form email.

* Fixed: A new form was missing from the Easy Form Builder block for up to an hour.

* Fixed: On phones, the field category tabs were cut off and Form Settings and Help were hidden in a sideways-scrolling toolbar.

* Fixed: Saving a form said "updated" even when the database refused the change or the form no longer existed, and the builder then discarded its local draft. A save that is not stored now says so, and the draft is kept.

* Fixed: Where Google Fonts, jsDelivr or gstatic.com hang instead of failing (common on some networks, for example in Iran), Easy Form Builder admin screens stayed blank or on "Please wait". These files no longer hold up the page.

* Fixed: The heading field's font size control was labelled "Height".

* Fixed: The admin email shortcode in email templates (shortcode_admin_email, shown in the default footer) now uses the site's Administration Email Address from Settings > General instead of the first user's address.

* Fixed: In Email Settings, the descriptions under the two weekly report switches were swapped.

* Fixed: A form holding a field the site's plan does not include (for example a signature after moving from Pro to Free) showed the plan notice but pushed the following fields and the Submit button outside the form. The rest of the form now works.

* Fixed: An entry with malformed data could stop the owner's notification email with a server error.

* New: Pages that show a form in their content, and the tracking and payment views, are kept out of page caches (LiteSpeed, WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache, WP-Optimize, Cache Enabler, SiteGround, Cloudflare and others). Pages with a form only in a sidebar, footer or widget stay cached. Developers can adjust this with the `emsfb_cache_bypass` filter.

* New: Form scripts are excluded from script optimizers (delay/defer, combine and minify JavaScript options) and wait for jQuery when it loads late, so forms also work with Cloudflare Rocket Loader.

* New: Each form gets a short-lived session before it is submitted. When a firewall, a REST API restriction or a rate limit blocks that step, the form falls back to the 4.3.0 request, which the server still fully validates.

* New: A dashboard notice reports form requests that fail on the server. It can be dismissed and shows details only on Easy Form Builder screens.

* Fixed: Network activation on WordPress Multisite caused a fatal error on admin pages, and sub-sites never got their database tables. Each sub-site now gets them the first time its dashboard opens.

* Fixed: The confirmation code (tracking) page caused a fatal error on WordPress versions before 6.5 with PHP 8.

* Fixed: Custom dashboard texts saved by version 3.x stopped the settings from saving.

* Fixed: Apostrophes inside email template styles (for example font names) were broken when the template was saved.

* Fixed: Form submissions and replies containing double quotes failed.

* Improved: Reinstalling add-ons after an update is time-limited, so admin pages no longer wait on an add-on server that does not answer.

* Improved: The email test report warns about active page caching plugins, and SiteGround and Swift Performance Lite are detected.

* Improved: Payment requests with malformed values get a clear error message instead of a server error.

* Improved: When a submission is refused (for example by Form Security & Spam Protection in strict mode or a rate limit), visitors see the actual reason instead of a generic error.

* Changed: Requires WordPress 5.7 or later and PHP 7.4 or later.

## 4.3.0

* Security: Improved form builder security and set up the i18n translation and localization infrastructure.

## 4.2.0

* New: Added an onboarding wizard for new installations, with setup progress and direct links to the relevant settings tabs.

* New: Added a weekly email deliverability report with delivery status, score, authentication checks, and recommendations.

* New: Added an Email Error Log and expanded email activity details in the Easy Form Builder dashboard widget.

* Improved: Improved email delivery handling, error reporting, and payment form notification reliability.

* Improved: Improved add-on installation and endpoint handling, including more resilient download and subscription flows.

* Improved: Improved form submission handling on cached pages and strengthened session, nonce, and recovery-token validation.

* Security: Hardened upload, recorder, login, registration, and tracking-code handling with additional validation and abuse protections.

* Changed: Updated the database schema to version 1.2. Existing installations run the schema upgrade automatically when the plugin loads.

## 4.1.3

* New: File upload form fields now have a built-in limit (3 files per field, no add-on needed), your own budget/size settings, server-side file-type enforcement, and fixed audio/video/screen recording uploads with clearer error messages.

* New: Setup guide for new installs, with direct links to each settings tab.

* New: Weekly email delivery report for your forms.

* Fixed: Stray empty paragraphs no longer appear between fields on themes/page builders that run wpautop after shortcodes.

* Fixed: Payment form notification emails could fail to send.

* Fixed: Forms keep working on cached pages instead of showing a security error.

* Improved: Faster page loads - forms no longer wait on a remote check to render.

## 4.1.2

* Fixed: Forms with file upload or recorder fields no longer get wrongly blocked by Form Security & Spam Protection in WordPress Form Builder.

* Fixed: Genuine visitors are no longer blocked when their browser or password manager auto-fills a hidden field.

* New: Audio, video, and screen recording fields released for WordPress Form Builder.

* Improved: Smarter spam scoring in Form Builder for recorder and file-upload forms, so real visitors aren't mistaken for bots.

* Fixed: Password field placeholder now displays correctly.

* Improved: Safe plugin loading - if any plugin file is missing after an update or an incomplete deploy, the plugin now stays inactive for that request and shows an admin notice asking for a reinstall, instead of causing a fatal error on the whole site.

## 4.1.1

* Fixed: Restored plugin files that were missing from the 4.1.0 release package on wordpress.org, which caused a fatal error after updating.

## 4.1.0

* New: Visual Conditional Logic Builder - build smart forms without code. Show or hide fields and steps, set/copy/clear values, jump to a step, dynamically change required/optional, run basic calculations, and combine conditions with numeric/date operators and nested AND/OR groups.

* New: Conditional workflows - route submissions with conditional email notifications, show a different thank-you message or redirect, and fire webhooks only when your conditions are met. Includes an in-builder Test mode and Inspector.

* New: Automated email deliverability monitor - sends a real test email through your server and verifies actual delivery (not just "sent") on activation, after each update, and weekly.

* New: AI-analyzed email deliverability report - the test email is inspected on the WhiteStudio server and analyzed with AI to produce a plain-language diagnosis, including a deliverability score, spam-score analysis (SpamAssassin), SPF/DKIM/DMARC authentication checks, delivery details, likely causes, and actionable recommendations, delivered to your admin email.

* New: One-click email test with live step-by-step progress and an instant deliverability score and grade, plus a weekly form activity digest (forms, page views, submissions, emails sent/failed).

* New: Multiple file uploads with improved upload progress feedback.

* New: Form builder autosave - never lose your work while designing a form.

* Improved: Session management and nonce refresh so forms served from cached pages stay valid and submittable.

* Improved: Smarter add-on management with automatic CDN failover and more resilient add-on downloads (retry, resume, and subscription handling).

* Security: Per-IP throttling for tracking-code lookups, an expanded list of blocked upload file extensions, a capability check for form preview, and hardened Google reCAPTCHA verification.

* Fixed: Guard against fatal errors on hosts that disable certain PHP functions.

* Tested up to WordPress 7.0.2

Looking for even older release notes? See the [full changelog archive](https://plugins.svn.wordpress.org/easy-form-builder/assets/changelog_old.txt).
