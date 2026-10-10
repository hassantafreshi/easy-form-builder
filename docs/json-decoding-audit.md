# JSON escape preservation audit

Local Easy Form Builder readers previously removed every backslash before
decoding JSON. This can invalidate quoted text and silently alter file paths,
regular expressions, line breaks and email templates.

`includes/json-efb.php` provides a shared decoder: decode valid JSON unchanged;
if decoding fails, unwrap the historical quote-only escaping (up to five
layers), then try the older fully slashed compatibility representation.
WordPress request slashes are removed at the request boundary with `wp_unslash`.
Ambiguous legacy encodings cannot be inferred reliably; new writers retain the
existing storage format, and no bulk data migration is introduced.

The audited readers cover settings, form definitions, submissions, replies,
uploads, autofill, Google Sheets and payment add-ons. JavaScript uses the same
valid-first policy. Field formatting preserves literal backslashes and
JSON-looking text; existing EFB display markers remain supported. Encoded quote
entities in email content are retained.

Ordinary path normalization, template placeholders and CSS sanitization still
use their intentional replacements. Historical snapshots/backups and bundled
third-party libraries were not modified. Add-on sources may be ignored by Git;
they still need inclusion when packaging the plugin.

Validation is isolated from WordPress persistence and real email delivery:

- `php tests/test-json-preservation.php`: decoder, actual settings cleaner and
  actual field formatter, including Persian joining characters, quotes,
  backslashes, paths, nested values and five legacy layers.
- `node tests/test-json-preservation.js`: actual browser parser and formatter.
- `php tests/test-submission-settings-email.php`: cache/settings regressions.
- `php tests/test-email-sender-diagnosis.php`: sender precedence, mail gate and
  encoded quote retention, using an in-memory mail stub.
- `python tests/verify-json-audit.py`: syntax checks and destructive replacement
  scan across maintained PHP/JavaScript sources.

This audit makes no server deployment and performs no file or data deletion.

## Historical record compatibility

The legacy snapshot's form/submission writers were checked: they apply
`json_encode` followed by quote-only escaping. These records are handled on
read without rewriting the stored payload. The tests additionally cover zero,
one, two and five escape layers for forms, submissions and settings, UTF-8 BOM
exports, unambiguous fully slashed records, HTML-encoded settings, the settings
character-map representation, file references and the existing display markers.

`php tests/test-legacy-json-compatibility.php` performs 43 in-memory checks.
The JavaScript preservation test also exercises historical record scenarios.
Truncated records retain their original raw representation when normalization
fails. Previously lost data cannot be reconstructed, and ambiguous slashing
without format metadata cannot be reliably distinguished; the known historical
quote-only writer format takes precedence over the fully slashed fallback.

## Final boundary and differential verification

Normalization now returns validated JSON text directly rather than encoding
decoded values again. This preserves very large number literals, decimal
precision, negative zero and whitespace. Native PHP/JavaScript numeric decoding
still follows each runtime's number limits; preserving text does not create
arbitrary-precision arithmetic.

Repair requires an escaped first quotation mark indicating a legacy envelope.
Invalid escapes inside an ordinary JSON record are not silently stripped.
The remaining Stripe credential reader and Autofill dataset update reader now
use the common decoder; the update request had already been `wp_unslash`ed.

- `python tests/test-json-scenario-matrix.py`: 2,116 deterministic scenarios
  each in PHP and JavaScript, comparing exact JSON text, including generated
  quote/backslash/Unicode combinations, all zero-to-five legacy layers, BOM,
  huge numeric literals and malformed records.
- `php tests/test-json-reader-integration.php`: 28 checks using the actual
  form/upload-definition and client-row reader methods plus upload field counts.
- Existing preservation, legacy, cache/mail-gate and sender/content tests pass.

These are isolated tests and source syntax audits, not a live browser submission,
real upload, payment transaction or email delivery test. Unrecognizable damaged
records and ambiguous historical escaping cannot be guaranteed recoverable.

## WordPress loading and namespace verification

The main plugin loads `class-Emsfb.php`, which requires the helper before the
core constructor registers callbacks or loads admin/public/add-on classes.
`functions.php` and the standalone upload guard also use the absolute
`__DIR__` path with `require_once`. The helper is global; unqualified function
calls inside the `Emsfb` namespace correctly fall back to global functions.
It is independent of WordPress APIs, so it needs no `init` or `plugins_loaded`
hook and is available during earlier settings reads as well.

`php tests/test-json-wordpress-loading.php core` (and `functions`, `upload`)
passes 27 isolated loading, namespace, callback and repeated-require checks.
No live WordPress request or persistent state is involved in these tests.

Callers use normalization for encoded JSON strings and then decode with the
appropriate object/array mode. Normalization is not a sanitizer and does not
remove WordPress request slashes: POST data must be `wp_unslash`ed once at its
boundary; database JSON must not be unslashed indiscriminately. Non-string or
invalid input is retained by normalization, so callers must keep their existing
type/shape validation after decoding.

### Explicit global calls and safe decoding

Namespaced consumers now explicitly call `\emsfb_json_normalize_efb` and
`\emsfb_json_decode_efb`, avoiding dependence on namespace fallback. Unqualified
calls were valid PHP, but the explicit form also prevents a namespaced function
with the same name from taking precedence. Seventeen immediate nested
`json_decode(normalize(...))` calls now use the type-checked decoder directly.
Arrays, objects and other unexpected inputs are rejected with `null` without
passing them to PHP's string-only `json_decode`. Callers that need encoded text
continue to use normalization. The helper is still loaded with `require_once`
before normal WordPress plugin callbacks execute.
