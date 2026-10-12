# H5P API 1.28 — Phase 2 implementation report

Prepared 2026-10-11. Core integration and standalone verification complete.
Implementation remains uncommitted; browser/CMS qualification remains pending.

## Checkpoint and restrictions

The initial branch was `feature/h5p-api-1.28`, at
`3760086a451a96dc781cabb9b0d29b6d082054cd`. Tracked files and index were clean;
the only untracked files were the five authorized Phase 1 preparation files.
Their report, manifests, pins, comparison register and verifier were reviewed.
Before implementation, the verifier passed all 800 frozen file hashes.

Only those five files were committed, with the exact requested message:
`docs: establish H5P API 1.28 upgrade baseline`.
Checkpoint/HEAD: `ef5a2554ab3b65e3e94dac2e2951cb3359ee0f9b`.
They remain unchanged. No implementation files were staged or committed.
No push, release, editor upgrade, CKEditor replacement, dependency installation,
Composer resolution, database connection/update, library installation, content
migration or cache rebuild was performed by this task.

## Source and actual integration

Source is [h5p/h5p-php-library at the requested immutable revision](https://github.com/h5p/h5p-php-library/tree/2aeb0b83fa603e331381b3a6b8bf42c3773ba140):
`2aeb0b83fa603e331381b3a6b8bf42c3773ba140` (Core 1.28.0).
All 67 archive files were checked for byte length and Git blob SHA1 against
the committed Phase 1 source lock before copying. The 65 distribution files
were copied to `library/`; upstream `.gitignore` and `.travis.yml` were excluded.

The PHP core, default storage, storage interface, standalone embed helper and
matching runtime JavaScript/CSS/fonts were imported together. The Composer
manifest was copied as vendor metadata (PHP >=7.0.0); Composer was not run.
The API declaration is at
[library/h5p.classes.php:2080](C:/wamp64/www/backdrop/modules/h5p/library/h5p.classes.php:2080).
Core resource arrays at lines 2084/2094 control existing Backdrop loader loops.
CSS resources, including every local referenced font/image, passed existence checks.
All 63 unpatched imported distribution files match their upstream Git blobs.

Two deliberate differences from the pinned vendor tree are retained:

1. [libraryToString compatibility shim](C:/wamp64/www/backdrop/modules/h5p/library/h5p.classes.php:2764):
   preserve the optional legacy `$folderName` argument and delegate `TRUE` to
   the new `libraryToFolderName()`. The untouched old editor uses that argument
   in three places. Without this shim, PHP accepts its extra argument but returns
   a display string with a space, producing incorrect library/presave asset URLs.
   Ordinary display names stay `H5P.X 1.2`; default directories stay `H5P.X-1.2`.
   Patch suffixes remain opt-in through `patchVersionInFolderName`, never injected
   into installed library records. New core/default-storage calls use the native
   folder helper at line 2779. No directories were renamed.
2. [event comment](C:/wamp64/www/backdrop/modules/h5p/library/h5p-event-base.class.php:144):
   retain “Used by Backdrop, Drupal and WordPress.” This is cosmetic and leaves
   the file byte-for-byte equal to the local baseline, so it is absent from Git's
   implementation diff.

Eight old font files are retained for compatibility/provenance, with no deletion:
`h5p-core-27.{eot,svg,ttf,woff}` and `h5p-hub-publish.{eot,svg,ttf,woff}`.
The new core CSS uses its matching new fonts. Old editor font/assets remain intact.

## Backdrop adapter changes

Only [h5p.classes.inc](C:/wamp64/www/backdrop/modules/h5p/h5p.classes.inc:3)
changes outside the core vendor tree:

| Method / line | Result |
| --- | --- |
| `getPlatformInfo()`, line 15 | Reports `BACKDROP_VERSION`, preserving platform name and module release metadata. The current site is Backdrop 1.35.1; tests use a distinct mock constant to detect regressions. |
| `getWhitelist()`, line 663 | Preserves saved content/library policies, including explicit empty values. Only absent values use the defaults supplied by Core 1.28. No configuration file or saved site setting is changed. |
| `getOption()`, line 983 | Honors the interface's default only when the stored value is NULL; preserves FALSE, zero, empty strings and literal `"NULL"`. |
| `resetHubOrganizationData()`, line 1004 | Implements the new required framework method. Clears only `h5p_hub_secret` through the existing option setter, allowing registration after a Hub organization credential becomes invalid. Preserves site UUID, enablement, content, libraries, users and unrelated settings. The core's mocked HTTP 403 path is tested; the current site's method was never invoked. |

The framework now loads as a concrete implementation of the complete Core 1.28
interface. Default storage and existing editor storage/AJAX adapters also pass
complete interface checks. Default storage has the matching upstream
`saveFileFromZip()` implementation.

## Preserved Backdrop behavior

| Behavior | Evidence / verification |
| --- | --- |
| PHP 8 property declarations and square-bracket indexing | Present in pinned core; strict PHP 8.4 execution produces no warning/deprecation. Validator property declaration is at core line 684. |
| Development metadataSettings guard | Development class is unchanged and identical to upstream; guard remains at [lines 98 onward](C:/wamp64/www/backdrop/modules/h5p/library/h5p-development.class.php:98). |
| Windows ZIP ignored-file handling | Upstream retains the expressions at core lines 864/944. Synthetic archives containing both `content/.ignored.exe` and `content\\.ignored.exe` validate; the unapproved EXE entries are skipped. |
| Separate editor file directory | Actual [Backdrop factory](C:/wamp64/www/backdrop/modules/h5p/h5p.module:1352) still supplies `files/h5peditor` to default storage at line 1359. A real temporary media copy proves the alternate source is used, not `h5p/editor`. |
| Copied-media relative paths | Actual factory's override at [h5p.module:1363](C:/wamp64/www/backdrop/modules/h5p/h5p.module:1363) recognizes editor and content paths and retains source IDs/file names. |
| Folder and dependency URL handling | All 17 frozen manifests yield the existing directory names. Actual core dependency URLs and old editor presave paths retain `name-major.minor`; patch remains in the cache buster. |
| Extra mandatory-group loop from the initial port | Not restored because it reported no errors. Valid French text/FALSE values and absent required fields behave as characterized before/after the import. This does not claim all semantics combinations are qualified. |
| Editor PHP/JS/CSS, CKEditor 4.17.1, application script and adapters | Every existing editor file matches the Phase 1 SHA256 baseline; no editor file is changed. Existing asset alter hooks remain callable and can alter the asset list. |
| Node revisions, database mapping, permissions, filtered-parameter hooks, event, API, Views and configuration integration | All baseline module files outside `library/` and `h5p.classes.inc` match frozen hashes. Database/revision behavior itself remains a future isolated-site test. |
| H5P/CMS jQuery separation | Pinned jQuery 3.5.1 retains `noConflict(true)` and `H5P.jQuery`. Actual wrapper tests cover namespace retention, isolation and old editor `.load(fn)` dispatch/chainability, using doubles. |

All 305 installed library file hashes remain identical, covering all 17 manifests
and their library assets. No installed content-library file was written.

## Tests and results

Run instructions and isolation guarantees are in
[tests/README.md](C:/wamp64/www/backdrop/modules/h5p/tests/README.md:1).
Tests require only the existing PHP ZIP/mbstring extensions and existing Node.

| Check | Result |
| --- | --- |
| Standalone PHP core/adapter suite, PHP 7.4.33 | 283 assertions, zero failures. |
| Standalone PHP core/adapter suite, PHP 8.4.22 | 283 assertions, zero failures; strict E_ALL warning/deprecation handling. |
| Both runtimes, all 21 current export copies, with explicit `--skip-fixture-baseline` | 388 assertions, zero failures per runtime. All packages accepted; parsed content parameters, language and main-library identity preserved; original hashes unchanged by validation. |
| PHP 8.4 full fixture run with frozen hash check enabled | 409 assertions, three failures, solely the three user-confirmed regenerated export hashes. All functional fixture checks pass. |
| PHP syntax | All 25 module/test PHP, INC, MODULE and INSTALL files pass on both PHP runtimes. |
| JavaScript syntax | All 24 core JavaScript files plus the Node test (25 total) pass Node `--check`. |
| Actual H5P jQuery wrapper | Ten working-contract assertions pass; one additional expected TypeError characterizes the URL-form limitation below. |
| Initial Phase 1 integrity verifier | All 800 frozen hashes passed before implementation. |
| Installed-library integrity after implementation | 305/305 file hashes unchanged; manifests, dependency/assets baseline retained. |
| Source/platform/editor provenance | 63 exact upstream blobs and all unaffected baseline platform/editor files pass hash checks. |
| `git diff --check` | Reports two inherited upstream whitespace issues: confirmation-dialog.js line 49 trailing space; core-button.css line 91 blank EOF line. Vendor bytes are intentionally retained to match the source lock. |

The PHP harness uses actual H5P validation and storage against temporary files;
it never saves libraries/content or connects to Backdrop. Configuration, Hub HTTP,
the one permitted platform-information query and hooks have in-memory doubles.
It does not prove live database behavior, browser rendering or all content
semantics/HTML round trips. Native ZIP temporary renames required sandbox
escalation in this session; all destructive cleanup is confined to a verified
owned OS-temp directory.

## Regenerated fixture observations

During this session, three exports changed from the initially verified Phase 1
hashes before test reads. The user confirmed that the working site regenerated
them. Both old Core 1.24 and upgraded Core 1.28 validators accepted the current
packages; test reads/copies did not change the originals. The frozen historical
baseline is not rewritten. Exact old/new hashes and observed modification times
are in [fixture-observations.json](C:/wamp64/www/backdrop/modules/h5p/docs/h5p-api-1.28/fixture-observations.json:1).

- `advanced-fill-the-blanks-papijo-3-20.h5p`
- `advanced-fill-the-blanks-papijo-francais-21.h5p`
- `vrai-ou-faux-fr-2-23.h5p`

The other 18 exports still match the frozen baseline. `verify-baseline.ps1
-SkipRepositoryFiles` now correctly reports these three historical differences.
Its normal module hash checks would also report the intended core/adapter import;
it is a historical-baseline verifier, not an upgrade acceptance test.

## Remaining compatibility concerns and decisions

1. **Old editor/browser qualification:** PHP interfaces, folder/presave calls and
   the legacy iframe handler contract pass. No browser edit/save/playback test
   against a CMS has been performed. The old editor/CKEditor 4 stays unchanged;
   further incompatibility should be recorded and addressed in Phase 3, not by
   an implicit editor migration.
2. **Inherited jQuery `.load(url)` defect:** [pinned wrapper line 5](C:/wamp64/www/backdrop/modules/h5p/library/js/jquery.js:5)
   stores `H5P.jQuery.load` instead of `H5P.jQuery.fn.load`. Calling the URL form
   throws TypeError. The old local bundle already had this defect; Core 1.28's
   import does not introduce it. The old editor and the inspected PapiJo
   Dialogcards image handlers use the supported function form. The upstream
   bundle remains exact. Decide in follow-up whether to apply a separate tested
   correction; do not claim arbitrary old jQuery consumers are compatible.
3. **Existing PapiJo defects remain:** both ShowWhen libraries register the same
   widget; AdvancedBlanksPapiJo/DialogcardsPapiJo upgrade scripts use upstream
   machine names. Retain the Phase 1 load-order checks and avoid invoking those
   automatic content-library upgrades. Package validation alone does not exercise
   these widget/upgrade paths.
4. **Existing metadata defect remains:** the unchanged
   [a11y_title serialization](C:/wamp64/www/backdrop/modules/h5p/library/h5p-metadata.class.php:64)
   can generate invalid JSON for a plain title. A repair requires its own scoped
   change and regression test; it is not hidden in this vendor import.
5. **Saved allowlist remains the site's policy:** previously enabled SVG/XML is
   retained. Missing settings use the new core defaults; installed settings are
   not rewritten to those defaults. Any policy change is separate work.
6. **PHP support:** upstream metadata declares PHP >=7.0; actual checks were on
   7.4.33 and 8.4.22 only. Other versions are not qualified by this task.

No additional core integration decision prevents this Phase 2 preparation from
being reviewed. Browser/CMS acceptance remains pending. Implementation and the
observed fixture record are available for review without committing/pushing.

## Recommended Phase 3

1. Provision a separate Backdrop checkout, private files/config directories and
   an empty dedicated database with a distinct least-privilege account. Bind the
   test hostname only to that checkout; never point it at the working-site
   database or files. Disable cron, outbound mail and automatic Hub/library
   updates there. No such environment has been created during Phase 2.
2. First qualify the current core plus old editor in the lab: div/iframe playback,
   fullscreen, scoring, fonts/icons/theme/CMS CSS, French content, presave/media
   uploads, edit/save/cancel, revisions, permissions and exported parameters.
   Include both ShowWhen widgets and nested PapiJo audio/images. Compare data
   before/after ordinary edits; do not run migrations/library upgrades.
3. For the planned editor upgrade, use Phase 1's immutable editor pin
   `73dfed6073b8916faa7e8b1a5fca5adb2bde5dc4`. Import that editor's PHP/runtime
   assets as a complete set. Its HTML widget requires its CKEditor 5 custom
   build (43.3.0), not CKEditor 4 configuration; include cropper, date picker,
   fonts, generated CSS/images and translations from the lock. No dependency
   rebuild/install is needed for the committed upstream bundles.
4. Update Backdrop editor adapters only for concrete changed contracts, preserving
   permissions, managed temporary uploads, hooks, node-revision IDs and async
   submit behavior. Replace old folder-helper calls with the modern helper;
   retire the temporary legacy shim only after all consumers are checked.
5. Add browser automation/HTML/media round-trip coverage under separately agreed
   test dependencies. Resolve characterized editor/widget defects separately.
   Review acceptance results before any implementation commit, current-site
   database action or release. None is authorized automatically by this report.

## Exact changed-file inventory

[phase-2-changes.json](C:/wamp64/www/backdrop/modules/h5p/docs/h5p-api-1.28/phase-2-changes.json:1)
lists every modified/new implementation, test and report file, with status,
SHA256 and upstream source mapping where applicable. Its own SHA256 is omitted
to avoid a recursive digest. There are no deleted files. Core files recopied
identically are described above but are not listed as Git changes.
