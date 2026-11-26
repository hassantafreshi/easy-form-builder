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
   Add-on zips of the add-ons repository (`vendores-efb`, `dev4` at `208a437`, 2026-11-25; checked again in the final
   regression on 2026-11-26: each zip rebuilt in a scratch folder has the same files and bytes) changed for 4.3.2 and
   not yet uploaded; check each sha256 after the upload. The full list of the 29 zips, with the minimum core of each,
   is in `RELEASE-CHECKLIST-4.3.2.fa.md`:

   | Zip | Bytes | sha256 |
   |---|---|---|
   | address.zip | 89422 | `051cf5efae873c349f65f484a41a59c08a57eceb8bc94f86631ba28dacb34279` |
   | human-shield.zip | 1143760 | `689e5e3efe4eedca29115415884cd7a71cad0c8ecae1d1bf911c62ae9c672ffa` |
   | logic.zip | 529692 | `17c83425e413c8941ca8953238bf1418dc49434432d09b5df5f3dfa548b924a7` |
   | offline.zip | 1998550 | `8ce3144974e9dd64299e361689ba81447295b9fcfe919dc94c59fe4d00c742b2` |
   | paymob.zip | 42187 | `b1df28e37b323e3c5904d4db015b93258c581225877534c5b09cb79a3b129f9d` |
   | paypal.zip | 398814 | `d9bf66739e751a5d7f5852cbf9d0b8ab74fa5fc6ca6ea8b2c9ed5582299203d2` |
   | stripe.zip | 777143 | `12c2a764c4d2dd46d4efb468ef16ba538a697d35a28fae55a6eb1a429ee35498` |
   | xendit.zip | 36271 | `f20a699027b71b5c590377151ec6de4e99d05c879c09fe5dc7ae0e9356d29927` |
   | persiapay.zip | 38270 | `473750af8a6602f8e7e152d6d98058893abcfb3687017d6e82aa0217dc57a3a9` |
   | smssended.zip | 405272 | `ecb1e38559d13f5328b1e0fb1359a1b0a00e50017469a3f98436d5a0c9a9d15e` |
   | telegram.zip | 610180 | `aa7c291a1cc0c3410cda743679f3a800f80e8be6ec70d49ddc54c8f2d3f6a6e8` |
   | googlesheet.zip | 1564019 | `4b66bdba08f406110df0442954796d3362881f70d233d913b081066d9c44a97e` |
   | autofill.zip | 837727 | `5329af3ab80f3fa2ace0640981788587c1e78178f1dca12d2754ed8822ccc7b9` |

   The first eight changed in batch B8 (add-on screens, Paymob payer fields, Xendit refunds made outside EFB,
   Conditional Logic file counts, the `emsfb_pay_` amount column); persiapay and smssended changed earlier and were
   not uploaded yet. FX-PAY (QA-3, 2026-11-25) rebuilt paypal, stripe, xendit and persiapay (older payments screens
   in gettext, Xendit and ZarinPal amount checks before storage, ZarinPal texts in efb-persiapay with its fa_IR file).
   Install each on a clean 4.3.1 and a clean 4.3.2 site before publishing core.
2. 24 hours later: commit the release folder to the wordpress.org SVN `trunk`, copy it to `tags/<version>`. The zip
   from step 2 is what goes there (unzip it into `trunk`).
3. The same day: Easy Form Builder Data Tools 1.1.0 (its own repository): `bin/build-zip.sh` there packs the
   committed files minus `.distignore` (no tests/ or docs/), reproducibly; with `EFBDT_PLUGIN_CHECK_WP` and
   `EFBDT_WPCLI` (as root with `--allow-root`) it runs Plugin Check too. 1.1.0 from commit `94a3c00` (Undo of an
   import works with core 4.3.2's QA5-001 refusal): `easy-form-builder-data-tools-1.1.0.zip`, 150162 bytes, sha256
   `ac982206faf15a9dff85ee9beb7f7199659b3986f212b0d6aa2a9c77379e3881` (two builds, the same bytes); Plugin Check 0
   errors, 30 warnings (direct database calls on core's tables). The earlier build of `7c49d53` (sha256 `a89d02c3…`)
   must not be uploaded.
4. Keep `dist/easy-form-builder-<version>.manifest.json` with the release notes: it says what was published.

   4.3.2 (final regression, 2026-11-26): `bin/build-zip.sh --ref c2f6c32a` → `easy-form-builder-4.3.2.zip`, 9,638,936
   bytes, 4,014 files, sha256 `1ae016e93285d2391509b893cc842f90fb5218a1878b543c120960c336a1da2c` (two builds, the same
   bytes); manifest sha256 `106ef91c5cd94b06bb77fc64d99324c555a5e68bb62142105fb0d95a9ace7c47`. Plugin Check 5 errors
   (all waived), 1,775 warnings. The owner's checklist: `RELEASE-CHECKLIST-4.3.2.fa.md`.
