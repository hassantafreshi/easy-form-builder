# Releasing Easy Form Builder

How a core release is built, checked and published (REL-04). The zip is built from a commit by `bin/build-zip.sh`;
it is never built by hand and never committed.

## 1. Before the build

- `emsfb.php` `Version:`, `EMSFB_PLUGIN_VERSION` and `readme.txt` `Stable tag:` are the same version (the build
  refuses otherwise; `tests/test-release-readme.php` checks it too).
- `readme.txt`: the changelog starts with `= <version> =` (short and user-facing, at most 30 bullets), the Upgrade
  Notice has `= <version> =` (at most 300 characters), and `== External services ==` names every host the code calls
  (the test scans the code). The full list of changes goes to `CHANGELOG.md` under `## <version>`.
- The unit suite is green: `php tests/run-suite.php` (site tests under the e2e lock), and the e2e suite on a test site.
- Everything for the release is committed: the build packs the commit, not the working tree.

## 2. Build

```bash
bin/build-zip.sh                       # HEAD; --ref <commit> for another commit
```

What it does (details at the top of the script):

1. `git archive` of the commit, minus `.distignore` (tests, docs, bin, node_modules, _workspace, temp, agents' and
   editors' folders, the unused root Composer copy, README.md and planning notes).
2. `bin/fetch-public-assets.sh`: the response-box fonts, the country / state / city lists and Chart.js at pinned
   versions (owner decision 9), checked against `bin/public-assets.lock.sha256`. When an upstream file changed the
   build stops; review the difference, run `bin/build-zip.sh --update-asset-lock` and commit the lock. List files no
   code requests and whose names Plugin Check refuses (copies like `mah (1).json`) are left out.
3. Minified twins (`x.min.js`, `x.min.css`, terser and csso at pinned versions, installed in
   `~/.cache/efb-build-tools`) of every asset `_Public::asset_efb()` can pick. The sources stay in the zip; with
   `SCRIPT_DEBUG` the plugin serves them.
4. Checks (top-level entries, no development file, no `@` or space in a file name, PHP syntax, every twin present
   and parsing), fixed times (the commit's), fixed permissions and a fixed order (each twin after its source): the
   same commit and lock give the same zip, byte for byte.

Output in `dist/` (git-ignored):

| File | What |
|---|---|
| `easy-form-builder-<version>.zip` | the release |
| `easy-form-builder-<version>.zip.sha256` | the zip's sha256 |
| `easy-form-builder-<version>.sha256` | every file's sha256 (`sha256sum -c` in an unzipped copy) |
| `easy-form-builder-<version>.manifest.json` | commit, version, tools, the zip's sha256, each file's size and sha256 |
| `plugin-check-<version>.csv` | Plugin Check's findings (step 3) |

## 3. Plugin Check

On a WordPress of its own (never the shared test site: the build replaces the plugin folder there) with the
[Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin active:

```bash
EFB_PLUGIN_CHECK_WP=/path/to/wordpress EFB_WPCLI="php /path/to/wp-cli.phar" bin/build-zip.sh
```

The release needs 0 errors, or a written waiver for each remaining sniff (QA plan, exit criteria): the waivers and
the reasons the remaining warnings stay are in [`plugin-check-waivers.md`](plugin-check-waivers.md). As root, add
`--allow-root` to `EFB_WPCLI` (WP-CLI refuses to run otherwise and the CSV comes out empty).

## 4. Publish (owner decision 3)

1. Upload the add-on zips to the add-on server and set the catalog's minimum versions and `v = <version>`.
   Add-on zips of the add-ons repository (`vendores-efb`, `dev4` at `f8ec706`, 2026-11-19) changed for 4.3.2 and
   not yet uploaded; check each sha256 after the upload:

   | Zip | Bytes | sha256 |
   |---|---|---|
   | address.zip | 89034 | `1bb67db0b4240bca1a5754724ce719d8b6207e2603cc36ee00937fe35b507b4a` |
   | human-shield.zip | 1143405 | `93dfac903e527d3ad5591279b5b9e011d241949fe3a6dbda12c86b7fc631c774` |
   | logic.zip | 529692 | `17c83425e413c8941ca8953238bf1418dc49434432d09b5df5f3dfa548b924a7` |
   | offline.zip | 1998550 | `8ce3144974e9dd64299e361689ba81447295b9fcfe919dc94c59fe4d00c742b2` |
   | paymob.zip | 42187 | `b1df28e37b323e3c5904d4db015b93258c581225877534c5b09cb79a3b129f9d` |
   | paypal.zip | 397129 | `96282d46b8d64338b15223e2170c1c82eaeb2ce23da911dabbd35bec507781d7` |
   | stripe.zip | 775371 | `7f2c5958fe5fda53afbd6d917bf26bdbecf441049071d2d5a50c165a8f27a6d4` |
   | xendit.zip | 35874 | `c17df93b95a289e264f6e2acc070911efa69dc8eea4b4063f2ae5ac7237b541f` |
   | persiapay.zip | 32781 | `21520ed5be0a6179ec9ec7d4771b571d38b4b5c43e3bfd61382eafb8910303bf` |
   | smssended.zip | 405272 | `ecb1e38559d13f5328b1e0fb1359a1b0a00e50017469a3f98436d5a0c9a9d15e` |

   The first eight changed in batch B8 (add-on screens, Paymob payer fields, Xendit refunds made outside EFB,
   Conditional Logic file counts, the `emsfb_pay_` amount column); persiapay and smssended changed earlier and were
   not uploaded yet. Install each on a clean 4.3.1 and a clean 4.3.2 site before publishing core.
2. 24 hours later: commit the release folder to the wordpress.org SVN `trunk`, copy it to `tags/<version>`. The zip
   from step 2 is what goes there (unzip it into `trunk`).
3. The same day: Easy Form Builder Data Tools 1.1.0 (its own repository).
4. Keep `dist/easy-form-builder-<version>.manifest.json` with the release notes: it says what was published.
