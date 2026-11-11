# Reference implementations: masks and validators

Verified JavaScript (ES2017, no dependencies) and PHP 7.4 twins of every
normalizer, mask engine and checksum described in
`../masks-and-validators.en.md`. Port them into the plugin; do not load these
files from `docs/` at runtime.

Run from this folder:

```bash
node test.js ../masks-catalog.json > /tmp/efb-js.json
```

```bash
php test.php ../masks-catalog.json > /tmp/efb-php.json
```

```bash
node compare.js /tmp/efb-js.json /tmp/efb-php.json
```

```bash
node extra-test.js ../masks-catalog.json
```

Last run (2026-11-11): 612/612 vectors pass in both languages, 0 JS/PHP
mismatches, 658/658 extra JS checks.
