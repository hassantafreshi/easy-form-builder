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
   | paypal.zip | 398814 | `d9bf66739e751a5d7f5852cbf9d0b8ab74fa5fc6ca6ea8b2c9ed5582299203d2` |
   | stripe.zip | 777143 | `12c2a764c4d2dd46d4efb468ef16ba538a697d35a28fae55a6eb1a429ee35498` |
   | xendit.zip | 36271 | `f20a699027b71b5c590377151ec6de4e99d05c879c09fe5dc7ae0e9356d29927` |
   | persiapay.zip | 38270 | `473750af8a6602f8e7e152d6d98058893abcfb3687017d6e82aa0217dc57a3a9` |
   | smssended.zip | 405272 | `ecb1e38559d13f5328b1e0fb1359a1b0a00e50017469a3f98436d5a0c9a9d15e` |

   The first eight changed in batch B8 (add-on screens, Paymob payer fields, Xendit refunds made outside EFB,
   Conditional Logic file counts, the `emsfb_pay_` amount column); persiapay and smssended changed earlier and were
   not uploaded yet. FX-PAY (QA-3, 2026-11-25) rebuilt paypal, stripe, xendit and persiapay (older payments screens
   in gettext, Xendit and ZarinPal amount checks before storage, ZarinPal texts in efb-persiapay with its fa_IR file).
   Install each on a clean 4.3.1 and a clean 4.3.2 site before publishing core.
2. 24 hours later: commit the release folder to the wordpress.org SVN `trunk`, copy it to `tags/<version>`. The zip
   from step 2 is what goes there (unzip it into `trunk`).
3. The same day: Easy Form Builder Data Tools 1.1.0 (its own repository): `bin/build-zip.sh` there packs the
   committed files minus `.distignore` (no tests/ or docs/), reproducibly; with `EFBDT_PLUGIN_CHECK_WP` and
   `EFBDT_WPCLI` (as root with `--allow-root`) it runs Plugin Check too. 1.1.0 from commit `7c49d53`:
   `easy-form-builder-data-tools-1.1.0.zip`, 149759 bytes, sha256
   `a89d02c3a1103b76bb5af2f4fbc7bb5c58114d36808e19dd3de893c9ce26c88f`; Plugin Check 0 errors, 30 warnings (direct
   database calls on core's tables).
4. Keep `dist/easy-form-builder-<version>.manifest.json` with the release notes: it says what was published.
