# Easy Form Builder Documentation

This path is the single reference index for Easy Form Builder's internal and development documentation.

## Categories

### Article Writing Playbook

[Article writing guide](EFB-ARTICLE-WRITING-PLAYBOOK.en.md)

- Fixed, permanent guidelines for writing any article/user guide: article structure and frontmatter, terminology rules (ban on the word "core" and its equivalents), the sourcing order for terms from `languages/*.json` and the WordPress glossary, and language-specific rules for German (informal "du" tone), Arabic (مُنشئ النماذج السهل), and Persian (فرم ساز آسان)

### Conditional Logic

[Conditional Logic index](conditional-logic/README.md)

- Product PRD and roadmap
- Implementation roadmap
- Persian acceptance tests and English Test Plan
- AI roadmap

### Ticketing

[Ticketing index](ticketing/README.md)

- Full roadmap for the standalone ticketing add-on: ticket panel, public portal, email OTP, form user/admin access levels, and integration with Conditional Logic

### Quiz

[Quiz index](quiz/README.md)

- Full roadmap for the standalone quiz/test form add-on: `quiz` form type, server-side scoring, timer, question bank, attempt limits, results report, and analytics

### Calculation

[Calculation index](calculation/README.md)

- Full roadmap for the standalone calculation add-on: calculation field, formula builder, live calculation and secure server-side recalculation, dynamic pricing for payments

### Debugging

[Debugging index](debugging/README.md)

- Full form-button debugging package
- Quick guide, function reference, and sample logs

### Licensing / Pro Activation

[Pro activation index](licensing/README.md)

- Complete end-user guide for obtaining and registering an activation code, the real differences between Free/Free Plus/Pro plans, the plan management section, connecting a license to a domain, and subscription expiry/renewal, in 4 languages, along with JSON-LD FAQ schema and SEO notes

### Autofill / Auto-Populate

[Auto-Populate index](autofill/README.md)

- Complete end-user guide for enabling, installing, and using Auto-Populate (Dataset, previous submissions, external API) in 4 languages, along with JSON-LD FAQ schema and SEO notes

### File Uploads

[File upload index](uploads/README.md)

- Complete end-user guide for limiting the number of uploaded files in 2 languages (automatic quota based on number of file fields, server-side size cap, content-based file type checking, cleanup of orphaned files), along with a filter reference and test list
- Security guide for upload fields (English): the permanent extension blocklist, content-vs-extension checking, random renaming, URL re-validation at submit, Response Box attachment rules, and the per-IP rate limiting added by the Form Security & Spam Protection add-on

### Response Box

[Response box index](responsebox/README.md)

- Complete end-user guide for setting up and customizing the response box in 2 languages (confirmation-code lookup shortcode, session validity duration, seven confirmation code patterns, four response box keys, 13 colors, and site-language-dependent font list), along with a CSS variable reference table

### Email Notifications

[Email notifications index](email-notifications/README.md)

- Complete end-user guide for form email notifications in 4 languages (basic email settings in the panel, per-form admin notification with three content modes, user notification via email field, the built-in "Email Server Check" tool with five steps and a deliverability score), along with a note on email template customization and JSON-LD FAQ schema

### Email Template Builder

[Email template builder index](email-template/README.md)

- Complete end-user guide for the email template builder in 4 languages (13 block types across four categories, 6 ready-made templates, 5 dynamic shortcodes, global font/color settings, 50,000-character limit), along with exactly which category of the plugin's emails the design applies to

### Survey Forms

[Survey forms index](survey-form/README.md)

- Complete end-user guide for survey forms and the "show survey results" setting in 4 languages (bar/pie chart after submission, per-field public display key and its automatic behavior, field type compatibility with the chart), along with JSON-LD FAQ schema and SEO notes

### Testing

[Testing index](testing/README.md)

- Email Server test guide
- Conditional Logic tests, in that feature's dedicated folder
- Autofill test guide via external API (Auto-Populate Integrations) in 4 languages: [Persian](testing/autofill-api/AUTOFILL-API-TEST-GUIDE.fa.md) · [English](testing/autofill-api/AUTOFILL-API-TEST-GUIDE.en.md) · [Arabic](testing/autofill-api/AUTOFILL-API-TEST-GUIDE.ar.md) · [German](testing/autofill-api/AUTOFILL-API-TEST-GUIDE.de.md)

### Compatibility

[Compatibility index](compatibility/README.md)

- Compatibility with security plugins and fixing the 403 error

### Migrations

[Migrations index](migrations/README.md)

- Migration guide from version `4.0.7` to `4.0.10`

### Audits

[Audits index](audits/README.md)

- Version 4 purchase flow audit
- Unused translation keys report

## Maintenance convention

- New internal documentation must be created in the appropriate topic subfolder inside `docs/`.
- The root README is only for the repository's public introduction and a link into this index.
- READMEs and documentation belonging to packages under `node_modules/` and `vendor/` belong to their dependencies and must not be moved.
- Temporary files must not be the primary source of documentation.
- Test guides designed for users/search/AI must include a **Keywords** line right after the main title, listing commonly used related terms, and — when offered in multiple languages — a breadcrumb line for navigating between languages and back to the index.
