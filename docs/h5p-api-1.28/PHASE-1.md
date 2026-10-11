# H5P API 1.28 — Phase 1 development baseline

Prepared 2026-10-11. Status: preparation complete; implementation awaits approval.

## 1. Git baseline and scope

The initial branch was `1.x-1.x`, with a clean index and working tree. The requested branch `feature/h5p-api-1.28` was created and checked out at the same commit:

`3760086a451a96dc781cabb9b0d29b6d082054cd`

Origin is `https://github.com/rezeau/h5p-for-backdrop.git`. No commit or push was made. Existing tracked module files and installed library/export files remain byte-for-byte unchanged. The only new working-tree files belong to `docs/h5p-api-1.28/`.

The two module metadata files declare no release version: [h5p.info](C:/wamp64/www/backdrop/modules/h5p/h5p.info:1) and [modules/h5peditor/h5peditor.info](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor.info:1). Use the commit above as the exact module version. Current Core API is 1.24 at [library/h5p.classes.php](C:/wamp64/www/backdrop/modules/h5p/library/h5p.classes.php:2061). Current editor is a mixed historical vendor snapshot, rather than a proven single upstream release. Its exact files are frozen by Git blob IDs and local SHA256 hashes. Its bundled CKEditor is 4.17.1 at [modules/h5peditor/h5peditor/ckeditor/ckeditor.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/ckeditor/ckeditor.js:5); H5P jQuery is 1.9.1 at [library/js/jquery.js](C:/wamp64/www/backdrop/modules/h5p/library/js/jquery.js:1).

Local Backdrop is 1.35.1 at [bootstrap.inc](C:/wamp64/www/backdrop/core/includes/bootstrap.inc:10). The tested installed PHP executables are 7.4.33 and 8.4.22. No Backdrop bootstrap, current database query, package import, library installation, cache rebuild, Composer/npm installation or dependency update was performed.

## 2. Exact upstream selection

These are immutable source pins for Phase 2. Static source inspection supports this pairing; qualification with Backdrop, the existing content and PHP runtimes remains an implementation test gate.

| Component | Selected source | Exact revision |
| --- | --- | --- |
| PHP Core and corresponding core JS/CSS/fonts/images | [h5p/h5p-php-library, tag 1.28.0](https://github.com/h5p/h5p-php-library/blob/2aeb0b83fa603e331381b3a6b8bf42c3773ba140/h5p.classes.php#L2080) | `2aeb0b83fa603e331381b3a6b8bf42c3773ba140` |
| PHP Editor and corresponding editor JS/CSS/languages/fonts/images | [h5p/h5p-editor-php-library, tag moodle-1.28.2](https://github.com/h5p/h5p-editor-php-library/blob/73dfed6073b8916faa7e8b1a5fca5adb2bde5dc4/h5peditor.class.php) | `73dfed6073b8916faa7e8b1a5fca5adb2bde5dc4` |
| H5P ClassicEditor custom build, CKEditor 5 version 43.3.0 | [ckeditor5/package.json](https://github.com/h5p/h5p-editor-php-library/blob/73dfed6073b8916faa7e8b1a5fca5adb2bde5dc4/ckeditor5/package.json#L11) and [source](https://github.com/h5p/h5p-editor-php-library/blob/73dfed6073b8916faa7e8b1a5fca5adb2bde5dc4/ckeditor5/src/ckeditor.ts) in that same editor revision | Compiled `ckeditor/ckeditor.js` Git blob `7bc5a5dcbe220514c527793aef4b4dc730135a8d` |
| CKEditor table customization, 0.0.14 | [H5P CKEditor table](https://github.com/h5p/h5p-ckeditor-table/tree/ab4ebd330013b6ed1b8aae5fc7123ff148e8ebd8), resolved in the editor's CKEditor package lock | `ab4ebd330013b6ed1b8aae5fc7123ff148e8ebd8` |
| H5P image cropper, package version 1.0.0 | [H5P image cropper](https://github.com/h5p/h5p-image-cropper/tree/09d0960367fd89e0581539ee8fb8366fd918ab05), resolved in the editor's root package lock | `09d0960367fd89e0581539ee8fb8366fd918ab05` |
| H5P jQuery | [Core's committed jQuery 3.5.1](https://github.com/h5p/h5p-php-library/blob/2aeb0b83fa603e331381b3a6b8bf42c3773ba140/js/jquery.js#L1) | Git blob `4f34d7590ccc8a13a265c8b965d84317d00179c0` |
| Zebra date picker | Editor's committed bundle; package version 1.9.11 | Git blob `e62803474b6c5acdf86e29db6e1d1c5091531c0c` |

Core tag 1.28.0 is annotated: tag object `e733dc9496382bd933a3e2205ce2702a7e02876c` resolves to the core commit above. Editor tag `moodle-1.28.2` is a lightweight tag resolving directly to its commit. The editor repository uses platform release tags; the tag name is not a claim that Backdrop should use Moodle platform code. Only its framework-independent vendor library is selected. Moving `master` is not an upgrade source.

Both upstream Composer manifests require PHP >=7.0.0: [core requirement](https://github.com/h5p/h5p-php-library/blob/2aeb0b83fa603e331381b3a6b8bf42c3773ba140/composer.json#L23) and [editor requirement](https://github.com/h5p/h5p-editor-php-library/blob/73dfed6073b8916faa7e8b1a5fca5adb2bde5dc4/composer.json#L23). Those metadata declarations do not prove runtime compatibility. This module vendors these files and registers its own class loader; a Composer update is unnecessary for importing the pinned sources.

[upstream-sources.lock.json](C:/wamp64/www/backdrop/modules/h5p/docs/h5p-api-1.28/upstream-sources.lock.json:1) records all 67 core and 214 editor upstream file paths, Git blob IDs, byte sizes and intended vendor destinations. It also records the PHP asset entry-point arrays, CKEditor and cropper source pins and package-lock blob IDs. This is documentation, not a Composer lock or an installed dependency update.

Use the committed compiled assets from these revisions. The root editor lock is Git blob `4b22e0a1c3344e4b55d5914eb1030705782508e3`; the CKEditor build lock is `c7385cfe14ebc60a0ac2d67fb9470ae88d5cb888`. No npm build is required for the proposed import. Any future rebuild would be separate work, with those locks and their Git dependencies pinned first.

## 3. CKEditor 5 and complete asset requirements

CKEditor 5 is mandatory for the unmodified selected editor. Its [HTML widget](https://github.com/h5p/h5p-editor-php-library/blob/73dfed6073b8916faa7e8b1a5fca5adb2bde5dc4/scripts/h5peditor-html.js#L434) calls `ClassicEditor.create` and configures CKEditor 5 plugin names. Its custom source extends CKEditor 5 ClassicEditor. There is no CKEditor 4 fallback in that code. Core API 1.28 itself does not select a rich-text engine.

Retaining CKEditor 4 would require a separately qualified editor revision or a maintained compatibility backport, with different source pins and regression coverage. It is not a configuration toggle. The inspected older `Drupal-7.x-1.55` editor tag also uses CKEditor 5, so it is not a CKEditor 4 alternative.

Phase 2 must take `scripts/h5peditor-html.js`, `ckeditor/ckeditor.js`, the editor styles and associated assets together. Copying the old CKEditor configuration or plugin tree into the new bundle is not a valid migration. Preserve supported saved HTML, tags, tables, links, formatting and localization through tests; custom CKEditor 4 plugin capabilities would need explicit mapping if any consuming modules use them.

The selected editor also changes the image-editing stack from Fabric/Darkroom to the H5P image cropper. Its [image popup loads libs/cropper.js dynamically](https://github.com/h5p/h5p-editor-php-library/blob/73dfed6073b8916faa7e8b1a5fca5adb2bde5dc4/scripts/h5peditor-image-popup.js#L129), while its [style list](https://github.com/h5p/h5p-editor-php-library/blob/73dfed6073b8916faa7e8b1a5fca5adb2bde5dc4/h5peditor.class.php#L11) includes `libs/cropper.css`. Copy `images/cropper/*.svg`, generated `styles/css/*.png`, date-picker assets and fonts along with the compiled code. The committed source map is recorded too; serving it is optional and is not a runtime requirement.

Core 1.28 adds `js/h5p-tooltip.js`, `styles/h5p-fonts.css`, `styles/h5p-theme.css`, `styles/h5p-theme-variables.css`, `styles/h5p-tooltip.css` and `styles/h5p-table.css`, plus core-30, theme, Inter and Open Sans fonts. Its [asset arrays](https://github.com/h5p/h5p-php-library/blob/2aeb0b83fa603e331381b3a6b8bf42c3773ba140/h5p.classes.php#L2084) are the loader source of truth. Existing Backdrop loader loops are at [h5p.module](C:/wamp64/www/backdrop/modules/h5p/h5p.module:988), [h5p.module](C:/wamp64/www/backdrop/modules/h5p/h5p.module:1660) and [modules/h5peditor/h5peditor.module](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor.module:219). Preserve loading order, iframe access and CSS/font/image relative URLs. Retain upstream license notices.

## 4. Vendor differences and preservation register

The baseline contains 449 tracked vendor files: 50 under `library/` and 399 under `modules/h5peditor/h5peditor/`. Comparing their Git blobs with the target revisions gives:

| Comparison | Identical | Different | Local only | Upstream only |
| --- | ---: | ---: | ---: | ---: |
| Core | 23 | 19 | 8 | 25 |
| Editor | 50 | 60 | 289 | 104 |

The many editor removals are largely the CKEditor 4 distribution and old asset formats. These counts are file differences, not a count of Backdrop customizations.

[vendor-comparison.json](C:/wamp64/www/backdrop/modules/h5p/docs/h5p-api-1.28/vendor-comparison.json:1) is the exhaustive path-by-path register. It also includes comparisons with 2021 upstream anchors and text deltas for every differing non-CKEditor editor PHP/script/SCSS/language file. The anchors are core `aaf26d584bd076d8b1f7fcb2afeca09c2e3b1646` and editor `2e70b2262ec7a9fe18051a2d747ae65fcb91e369`. They are comparison references, not proven exact original vendor releases. The initial port and later local commits establish provenance.

| Existing change or behavior | Evidence | Phase 2 disposition |
| --- | --- | --- |
| PHP 8/8.2 property declarations and square-bracket string indexing | [library/h5p.classes.php](C:/wamp64/www/backdrop/modules/h5p/library/h5p.classes.php:2117); [library/h5p-development.class.php](C:/wamp64/www/backdrop/modules/h5p/library/h5p-development.class.php:67); local commit `ecf8bfff2ddbaf6c3e9b9d4bec037ec1df19198b` | Preserve behavior. The new core declares these properties and uses square brackets; do not duplicate the fix. |
| Missing metadataSettings guard in development mode | [library/h5p-development.class.php](C:/wamp64/www/backdrop/modules/h5p/library/h5p-development.class.php:98); commit `ba92d98fb692395ecbdc59bec6bb5ee036c9f5a6` | Already preserved: the entire local development class has the same Git blob as the selected core. |
| ZIP ignored-file regex changed for Windows path handling | [library/h5p.classes.php](C:/wamp64/www/backdrop/modules/h5p/library/h5p.classes.php:856) and line 936; commit `282a77a1c6bcf8b1d19f52dc3f4b7aa0d8ecb0e4` | The target contains the same expressions at lines 864/944. Preserve and regression-test actual matching behavior, including backslash paths; matching source is not proof of correctness. |
| Audio/video upload error markup and media class fix | [modules/h5peditor/h5peditor/scripts/h5peditor-av.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/scripts/h5peditor-av.js:1); commit `748ec20aaa2a9dcab53f6b98175d48fd840cd374` | Equivalent behavior is present upstream. Retain tests for audio/video errors and icons without reapplying the old patch. |
| Backdrop name in event documentation | [library/h5p-event-base.class.php](C:/wamp64/www/backdrop/modules/h5p/library/h5p-event-base.class.php:144); commit `11e67e556e3d583a07dee86c50ed08201d5dfb86` | Cosmetic; optionally retain as a documented local patch. |
| Dependency-path helper replaced with an inline old folder formatter in the initial port | [library/h5p.classes.php](C:/wamp64/www/backdrop/modules/h5p/library/h5p.classes.php:2503); port commit `29e7d2c6d24a4913b9b5f19467a43c76ac9c08cc` | Keep the intended filesystem path behavior, use the new upstream helper. Do not carry the removed helper override forward. |
| Initial-port extra mandatory-group loop with commented-out errors | [library/h5p.classes.php](C:/wamp64/www/backdrop/modules/h5p/library/h5p.classes.php:4634); same port commit | It adds a NULL return and a loop that reports no error. Do not blindly restore the no-op loop. Characterize absent/optional/required groups on the baseline and target before accepting the upstream validator. |
| H5P jQuery downgraded and isolated from the CMS instance | [library/js/jquery.js](C:/wamp64/www/backdrop/modules/h5p/library/js/jquery.js:1) and line 15; initial port | Preserve `H5P.jQuery` isolation and Backdrop's own jQuery. Use the target's 3.5.1 bundle with matching modern scripts; exercise third-party library scripts and Backdrop behaviors. |
| Core fullscreen and iframe initialization differ from the 2021 anchor | [library/js/h5p.js](C:/wamp64/www/backdrop/modules/h5p/library/js/h5p.js:26) and line 380 | Preserve supported fullscreen, iframe language and playback behavior using upstream implementation. The old spelling `requestFullScreen` and old iframe loading code are not required Backdrop APIs. |
| Old core font names and media icon SCSS differ | [library/styles/h5p.css](C:/wamp64/www/backdrop/modules/h5p/library/styles/h5p.css:6); [modules/h5peditor/h5peditor/styles/scss/application.scss](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/styles/scss/application.scss:361) | Use matched upstream fonts/CSS/JS and verify icon rendering. Do not mix core-27 CSS with new font files. |
| Editor file extension comparison lacks the upstream lowercase normalization | [modules/h5peditor/h5peditor/h5peditor-file.class.php](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/h5peditor-file.class.php:107) | Treat as inherited divergence; test uppercase extensions and use target normalization. |
| Cached icon data removed from editor's library conversion | [modules/h5peditor/h5peditor/h5peditor.class.php](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/h5peditor.class.php:593) | Use target icon handling; make the adapter's returned library records conform. |
| Editor JS loading coalesces concurrent requests; clipboard logic ignores the restriction check in the 2021 anchor | [modules/h5peditor/h5peditor/scripts/h5peditor.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/scripts/h5peditor.js:59) and line 1399 | Preserve prevention of duplicate loads. Keep intended library permission enforcement using upstream code; do not carry forward a bypass of restrictions. |
| Editor iframe `.load` shorthand, summary-node test and initial-language/cancel behavior differ | [modules/h5peditor/h5peditor/scripts/h5peditor-editor.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/scripts/h5peditor-editor.js:278); [modules/h5peditor/h5peditor/scripts/h5peditor-group.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/scripts/h5peditor-group.js:316); [modules/h5peditor/h5peditor/scripts/h5peditor-form.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/scripts/h5peditor-form.js:416); [modules/h5peditor/h5peditor/scripts/h5peditor-init.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/scripts/h5peditor-init.js:2) | Take coherent upstream scripts; test title, language, form submission and cancel/delete actions through the separate Backdrop application script. |
| Debug console logging in library widget | [modules/h5peditor/h5peditor/scripts/h5peditor-library.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/scripts/h5peditor-library.js:83) | Do not preserve logging. |
| CKEditor 4 build/config/HTML-widget differences, code-button removals and translations | [modules/h5peditor/h5peditor/ckeditor/config.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/ckeditor/config.js:1); [modules/h5peditor/h5peditor/ckeditor/build-config.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/ckeditor/build-config.js:1); [modules/h5peditor/h5peditor/scripts/h5peditor-html.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/scripts/h5peditor-html.js:295) | Replace with the selected custom CKEditor 5 build and widget as a unit; preserve content behavior through HTML round-trip tests. |
| Editor language deltas: missing restricted-paste text in 16 languages, Finnish typo correction and Russian translation differences; reduced language list in h5peditor.js | [modules/h5peditor/h5peditor/language/fi.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/language/fi.js:96); [modules/h5peditor/h5peditor/language/ru.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/language/ru.js:7); exhaustive JSON deltas | No evidence of Backdrop-specific translation requirements. Take complete selected translations; retain any desired local wording only as explicit separate patches. |

The vendor register separates these changes from the actual Backdrop platform boundary. Preserve and review the following platform code rather than overwrite it with another platform's adapters:

- [h5p.classes.inc](C:/wamp64/www/backdrop/modules/h5p/h5p.classes.inc:3): framework implementation, database mapping, hooks, filesystem URLs, options, permissions and Hub cache.
- [h5p.module](C:/wamp64/www/backdrop/modules/h5p/h5p.module:1359): alternate editor storage directory under `files/h5peditor`; line 1363 overrides `relativePathRegExp`. The target default storage supports the alternate directory.
- [h5p.module](C:/wamp64/www/backdrop/modules/h5p/h5p.module:1091) and line 1187: filtered-parameter and JS/CSS alter hooks, also used by embed code at line 1686.
- [modules/h5peditor/h5peditor-backdrop-storage.class.inc](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor-backdrop-storage.class.inc:187): editor asset alters, managed uploads, temporary/private files and cleanup.
- [modules/h5peditor/h5peditor-backdrop-ajax.class.inc](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor-backdrop-ajax.class.inc:7): authenticated AJAX endpoints and library records.
- [modules/h5peditor/scripts/application.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/scripts/application.js:18): Backdrop settings and editor namespace, node revision ID, asynchronous save, title and submitter handling. This file sits outside the vendor tree.
- [h5p.module](C:/wamp64/www/backdrop/modules/h5p/h5p.module:697) and line 1539: node revision deletion and revision-to-node mapping; preserve revision isolation.
- [h5p.api.php](C:/wamp64/www/backdrop/modules/h5p/h5p.api.php:1), event and Views integrations: preserve module extension contracts and reporting.

## 5. Installed content-library baseline

All 17 manifests are under `C:/wamp64/www/backdrop/files/h5p/libraries/`. Their exact manifest contents, SHA256 values, declared dependency lists, asset paths and 305 file hashes are in [local-baseline.json](C:/wamp64/www/backdrop/modules/h5p/docs/h5p-api-1.28/local-baseline.json:1).

| Manifest | Version | Minimum Core API | Runnable | Declared dependencies |
| --- | --- | --- | --- | ---: |
| [FontAwesome](C:/wamp64/www/backdrop/files/h5p/libraries/FontAwesome-4.5/library.json:1) | 4.5.6 | unspecified | no | 0 |
| [H5P.AdvancedBlanksPapiJo](C:/wamp64/www/backdrop/files/h5p/libraries/H5P.AdvancedBlanksPapiJo-1.1/library.json:1) | 1.1.7 | 1.6 | yes | 6 |
| [H5P.Audio](C:/wamp64/www/backdrop/files/h5p/libraries/H5P.Audio-1.5/library.json:1) | 1.5.12 | 1.19 | yes | 2 |
| [H5P.DialogcardsPapiJo](C:/wamp64/www/backdrop/files/h5p/libraries/H5P.DialogcardsPapiJo-1.16/library.json:1) | 1.16.20 | 1.15 | yes | 6 |
| [H5P.FontIcons](C:/wamp64/www/backdrop/files/h5p/libraries/H5P.FontIcons-1.0/library.json:1) | 1.0.11 | unspecified | no | 0 |
| [H5P.Image](C:/wamp64/www/backdrop/files/h5p/libraries/H5P.Image-1.1/library.json:1) | 1.1.22 | 1.19 | no | 1 |
| [H5P.JoubelUI](C:/wamp64/www/backdrop/files/h5p/libraries/H5P.JoubelUI-1.3/library.json:1) | 1.3.19 | 1.3 | no | 3 |
| [H5P.Question](C:/wamp64/www/backdrop/files/h5p/libraries/H5P.Question-1.5/library.json:1) | 1.5.15 | 1.7 | no | 2 |
| [H5P.Transition](C:/wamp64/www/backdrop/files/h5p/libraries/H5P.Transition-1.0/library.json:1) | 1.0.4 | unspecified | no | 0 |
| [H5P.TrueFalse](C:/wamp64/www/backdrop/files/h5p/libraries/H5P.TrueFalse-1.8/library.json:1) | 1.8.11 | 1.19 | yes | 6 |
| [H5PEditor.ColorSelector](C:/wamp64/www/backdrop/files/h5p/libraries/H5PEditor.ColorSelector-1.3/library.json:1) | 1.3.1 | 1.24 | no | 0 |
| [H5PEditor.RadioGroup](C:/wamp64/www/backdrop/files/h5p/libraries/H5PEditor.RadioGroup-1.1/library.json:1) | 1.1.4 | 1.12 | no | 0 |
| [H5PEditor.RangeList](C:/wamp64/www/backdrop/files/h5p/libraries/H5PEditor.RangeList-1.0/library.json:1) | 1.0.13 | 1.14 | no | 1 |
| [H5PEditor.ShowWhen](C:/wamp64/www/backdrop/files/h5p/libraries/H5PEditor.ShowWhen-1.0/library.json:1) | 1.0.9 | unspecified | no | 0 |
| [H5PEditor.ShowWhenpapijo](C:/wamp64/www/backdrop/files/h5p/libraries/H5PEditor.ShowWhenpapijo-1.0/library.json:1) | 1.0.5 | unspecified | no | 0 |
| [H5PEditor.TableList](C:/wamp64/www/backdrop/files/h5p/libraries/H5PEditor.TableList-1.0/library.json:1) | 1.0.4 | 1.14 | no | 0 |
| [H5PEditor.VerticalTabs](C:/wamp64/www/backdrop/files/h5p/libraries/H5PEditor.VerticalTabs-1.3/library.json:1) | 1.3.16 | 1.24 | no | 1 |

All declared dependency versions and listed JS/CSS files exist. None of the declared API requirements exceeds 1.24, so raising the platform API number to 1.28 does not itself require changing these libraries. Runtime/editor behavior still needs testing.

Existing issues to retain as explicit regression cases:

1. Both ShowWhen libraries register `H5PEditor.ShowWhen` and `widgets.showWhen`, despite differing implementations: [standard widget](C:/wamp64/www/backdrop/files/h5p/libraries/H5PEditor.ShowWhen-1.0/h5p-show-when.js:1) and [PapiJo widget](C:/wamp64/www/backdrop/files/h5p/libraries/H5PEditor.ShowWhenpapijo-1.0/h5p-show-when.js:1), with registrations at line 166. Nested media can load both; test load order and conditional visibility.
2. PapiJo upgrade scripts register the upstream machine names: [AdvancedBlanks upgrade](C:/wamp64/www/backdrop/files/h5p/libraries/H5P.AdvancedBlanksPapiJo-1.1/upgrades.js:3) and [Dialogcards upgrade](C:/wamp64/www/backdrop/files/h5p/libraries/H5P.DialogcardsPapiJo-1.16/upgrades.js:3). The current upgrade engine looks up the actual library name at [library/js/h5p-content-upgrade-process.js](C:/wamp64/www/backdrop/modules/h5p/library/js/h5p-content-upgrade-process.js:96). Do not invoke automatic content-library upgrades in platform tests.
3. [AdvancedBlanks semantics](C:/wamp64/www/backdrop/files/h5p/libraries/H5P.AdvancedBlanksPapiJo-1.1/semantics.json:14) offers H5P.Video 1.6, which is not installed. This optional editor selection is outside the complete declared dependency closure; do not silently install it.
4. Metadata `a11y_title` serialization can produce invalid JSON for an ordinary plain title at [library/h5p-metadata.class.php](C:/wamp64/www/backdrop/modules/h5p/library/h5p-metadata.class.php:64), called from [h5p.admin.inc](C:/wamp64/www/backdrop/modules/h5p/h5p.admin.inc:851). The target retains that code. Record the existing failure separately; importing Core 1.28 does not fix it.

No installed manifest, semantics, upgrade script, media file or library asset was changed.

## 6. Available export fixtures

There are 21 export packages, all with distinct file SHA256 hashes: 6 AdvancedBlanksPapiJo, 2 DialogcardsPapiJo and 13 TrueFalse. The embedded main-library versions are respectively 1.1.7, 1.16.20 and 1.8.11, matching installed versions. Package headers and embedded manifests were read directly from ZIP archives without extracting or importing them. No content-path SVG/XML entries were found.

| Existing export | Main library | Header language | Bytes | SHA256 prefix |
| --- | --- | --- | ---: | --- |
| [advanced-fill-the-blanks-papijo-2-9.h5p](C:/wamp64/www/backdrop/files/h5p/exports/advanced-fill-the-blanks-papijo-2-9.h5p) | AdvancedBlanksPapiJo | und | 791,566 | `ccd31abb8eb4` |
| [advanced-fill-the-blanks-papijo-20.h5p](C:/wamp64/www/backdrop/files/h5p/exports/advanced-fill-the-blanks-papijo-20.h5p) | AdvancedBlanksPapiJo | en | 791,563 | `7e473587957f` |
| [advanced-fill-the-blanks-papijo-3-20.h5p](C:/wamp64/www/backdrop/files/h5p/exports/advanced-fill-the-blanks-papijo-3-20.h5p) | AdvancedBlanksPapiJo | en | 791,563 | `0b01c77b9480` |
| [advanced-fill-the-blanks-papijo-9.h5p](C:/wamp64/www/backdrop/files/h5p/exports/advanced-fill-the-blanks-papijo-9.h5p) | AdvancedBlanksPapiJo | und | 791,566 | `735a69b32da4` |
| [advanced-fill-the-blanks-papijo-francais-2-21.h5p](C:/wamp64/www/backdrop/files/h5p/exports/advanced-fill-the-blanks-papijo-francais-2-21.h5p) | AdvancedBlanksPapiJo | fr | 791,569 | `b3e70c3c94d7` |
| [advanced-fill-the-blanks-papijo-francais-21.h5p](C:/wamp64/www/backdrop/files/h5p/exports/advanced-fill-the-blanks-papijo-francais-21.h5p) | AdvancedBlanksPapiJo | fr | 791,569 | `399e8b8047ce` |
| [flags-and-countries-with-categories-2-8.h5p](C:/wamp64/www/backdrop/files/h5p/exports/flags-and-countries-with-categories-2-8.h5p) | DialogcardsPapiJo | und | 767,166 | `d615d871c065` |
| [flags-and-countries-with-categories-8.h5p](C:/wamp64/www/backdrop/files/h5p/exports/flags-and-countries-with-categories-8.h5p) | DialogcardsPapiJo | und | 767,166 | `6a592294d43b` |
| [h5p-true-false-18.h5p](C:/wamp64/www/backdrop/files/h5p/exports/h5p-true-false-18.h5p) | TrueFalse | en | 707,773 | `b1fd0a2d8209` |
| [h5p-true-false-2-18.h5p](C:/wamp64/www/backdrop/files/h5p/exports/h5p-true-false-2-18.h5p) | TrueFalse | en | 707,773 | `54032cdfa1f9` |
| [h5p-true-false-fr-19.h5p](C:/wamp64/www/backdrop/files/h5p/exports/h5p-true-false-fr-19.h5p) | TrueFalse | fr | 707,843 | `e70b831a2905` |
| [h5p-true-false-fr-2-19.h5p](C:/wamp64/www/backdrop/files/h5p/exports/h5p-true-false-fr-2-19.h5p) | TrueFalse | fr | 707,848 | `5bb36cef4316` |
| [this-is-treu-17.h5p](C:/wamp64/www/backdrop/files/h5p/exports/this-is-treu-17.h5p) | TrueFalse | und | 707,771 | `591811590dcf` |
| [this-is-treu-2-17.h5p](C:/wamp64/www/backdrop/files/h5p/exports/this-is-treu-2-17.h5p) | TrueFalse | und | 707,771 | `0d8c3d005d0a` |
| [true-false-2-7.h5p](C:/wamp64/www/backdrop/files/h5p/exports/true-false-2-7.h5p) | TrueFalse | und | 740,061 | `08e2e8f65ebc` |
| [true-false-7.h5p](C:/wamp64/www/backdrop/files/h5p/exports/true-false-7.h5p) | TrueFalse | und | 724,613 | `2faa196c1ac9` |
| [true-false-en-2-22.h5p](C:/wamp64/www/backdrop/files/h5p/exports/true-false-en-2-22.h5p) | TrueFalse | en | 707,780 | `90818f3f1641` |
| [true-false-en-22.h5p](C:/wamp64/www/backdrop/files/h5p/exports/true-false-en-22.h5p) | TrueFalse | en | 707,780 | `05657de99906` |
| [true-story-10.h5p](C:/wamp64/www/backdrop/files/h5p/exports/true-story-10.h5p) | TrueFalse | und | 707,771 | `a17b1edbc838` |
| [vrai-ou-faux-fr-2-23.h5p](C:/wamp64/www/backdrop/files/h5p/exports/vrai-ou-faux-fr-2-23.h5p) | TrueFalse | fr | 707,846 | `ab04d0061a8f` |
| [vrai-ou-faux-fr-23.h5p](C:/wamp64/www/backdrop/files/h5p/exports/vrai-ou-faux-fr-23.h5p) | TrueFalse | fr | 707,782 | `3ae5ae0f9ec6` |

Full hashes, ZIP entry counts, dependency declarations and embedded library versions are in `local-baseline.json`. These are private local source fixtures, not committed package binaries. Keep original exports immutable; copy selected packages into a future lab. Exports alone do not capture database revisions, users, scores, schema update status or all node-level metadata.

Minimum representative set: `advanced-fill-the-blanks-papijo-20.h5p`, `advanced-fill-the-blanks-papijo-francais-21.h5p`, `flags-and-countries-with-categories-8.h5p`, `true-false-en-22.h5p`, `vrai-ou-faux-fr-23.h5p` and the image-bearing `true-false-7.h5p`. Run archive/import validation on all 21 when the lab exists. Use a synthetic media-rich fixture in the lab to cover formatting features not demonstrated by these exports.

## 7. Safe isolated testing arrangement — proposed only

Create a separate local Backdrop 1.35.1 installation, for example `C:/wamp64/www/backdrop-h5p-api-128-test`, exposed on a dedicated localhost hostname or port. Copy module and library files; never use symlinks, directory junctions or hard links back to the live installation.

Give it an independent database, for example `backdrop_h5p_api_128_test`, and a dedicated database account whose grants cover only that database. Write lab-specific settings instead of reusing the current site's credentials. Keep public/private files, configuration, sessions, temporary directories and caches separate. Verify every configured storage path resolves into the lab before installing/importing anything.

Before running the lab, disable real Hub registration/sharing, usage statistics, mail and scheduled jobs; stub external requests. A new site must receive its own identity rather than copy Hub secrets/site identity. Restrict browser access to the lab and avoid opening the current site's pages as a test.

First install the current module snapshot in the fresh lab, import copies of representative exports, and record playback/editor/save/export results. Then apply Phase 2 only there and repeat the same cases. Imports may write the lab's library directories and database, which is why both must be separate.

A fresh lab does not verify an existing database migration. If revision/schema migration testing becomes necessary, a later explicitly approved step can restore an existing backup or separately authorized database dump into a second isolated database, with lab-only credentials and outbound services disabled. No dump or database access is part of this preparation.

No lab directory, database, user, site settings or installed library has been created or changed in Phase 1.

## 8. Minimum useful automated tests

The existing [h5peditor.tests.info](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor.tests.info:1) is empty. No usable PHPUnit, browser-test suite or CI test workflow is present in the module checkout.

Established now: [verify-baseline.ps1](C:/wamp64/www/backdrop/modules/h5p/docs/h5p-api-1.28/verify-baseline.ps1:1) checks the frozen repository hashes, installed library file hashes, manifest dependency/asset closure and export hashes. It is read-only and does not bootstrap Backdrop. Run from the repository:

```powershell
& ./docs/h5p-api-1.28/verify-baseline.ps1
```

The snapshot consists of 474 original tracked module files, 305 installed library files and 21 exports: 800 hashes. SHA256 hashes measure actual disk bytes, including line endings; Git blob IDs identify normalized Git objects. New preparation documents are deliberately excluded from the original file snapshot.

To verify protected site files after legitimate source changes, use `-SkipRepositoryFiles`. For a copy of the fixture/library tree, supply `-SiteRoot <lab-root>`. A hash check against the original module snapshot should fail after implementation; it is a baseline guard, not a claim that source changes are forbidden forever.

Before changing vendor code in Phase 2, establish these tests with the current implementation and classify existing failures:

| Test | Minimum assertions | Arrangement/dependencies |
| --- | --- | --- |
| PHP syntax and adapter contracts | All PHP/inc/module/install files parse; framework/storage/AJAX adapter classes load; method signatures satisfy selected interfaces | Existing PHP CLI; pure PHP reflection, no CMS bootstrap. Test 7.4 and 8.4 already available. |
| API/package validation | Valid synthetic API 1.24 package accepted on baseline; API 1.28 rejected there and accepted after upgrade; newer minor/major rejected; malformed archives rejected | Pure framework stub and disposable lab temp directory. Use valid synthetic manifests; do not edit installed manifests. |
| Folder/storage contracts | Display name is `H5P.X 1.2`; folder is `H5P.X-1.2`; patch suffix only when explicitly enabled; editor temp path, content media moves and icon/upgrades.js paths remain correct | Pure PHP helpers/default storage with a temp tree. Include Windows paths and absent patch-folder flag. |
| Backdrop adapter options/Hub failures | Correct platform version; missing option defaults respected; returned library records have folder flag; mocked Hub 403 resets only intended Hub organization settings | Stubs or isolated lab configuration/database; no live requests. |
| Existing exports and editor round trips | All 21 import/validate in lab; representative EN/FR packages load, edit, save twice, export/reimport; preserve parameters, media, language, title and library versions | Isolated Backdrop database and copies of fixtures. Assert semantic JSON/media equality rather than ZIP byte equality or generated IDs. |
| Browser editor and playback | CKEditor toolbar/formatting/HTML preservation; image crop/rotate/save; audio error handling; ShowWhen collision case; iframe/div playback, fullscreen, scoring and Backdrop behaviors | Browser against lab; include console/network failures and missing font/image assets. Add automation runner only under separately approved test dependencies. |
| Existing-defect characterization | Metadata a11y_title JSON failure, missing/optional groups, PapiJo upgrade-name mismatch and clipboard restrictions are recorded distinctly | Synthetic inputs; expected existing failures do not disguise new regressions. |

Do not install a test framework just to start. Pure PHP CLI/stubs and the read-only PowerShell verifier provide immediate checks. Backdrop integration tests require the isolated database; browser automation can be added later with declared dependencies. An upstream `.travis.yml` or npm build script is not an existing Backdrop test suite.

Checks completed in Phase 1: all 800 hashes pass; declared dependency/asset closure passes; 23 original PHP/inc/module/install files pass `php -n -l` under both PHP 7.4.33 and 8.4.22; the three current adapter classes load under PHP 8.4.22 with their current interfaces, without bootstrapping Backdrop. These checks do not validate the target runtime or database migrations.

## 9. Precise Phase 2 implementation plan

No step below has been executed. The dependency order matters.

### Step A — establish tests and qualify the lab

Affected new files: proposed `tests/baseline-contracts.php`, `tests/package-validation.php`, `tests/storage-paths.php`, `tests/fixture-roundtrip.php` and a test README; a browser suite only if its dependency arrangement is approved. Keep local export binaries outside Git.

Dependency: the safe lab from section 7 for integration tests. Capture current behavior, expected existing failures and screenshots/results before vendor import. Preserve the frozen Phase 1 snapshot.

### Step B — import the pinned core/editor distributions together

Affected vendor roots: `C:/wamp64/www/backdrop/modules/h5p/library/` and `C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/`.

The exact upstream-to-local file mapping and blob IDs are in `upstream-sources.lock.json`; exact add/change/remove candidates are in `vendor-comparison.json`. Review the mapping rather than blindly overlay files or deleting whole directories. Do not import another platform adapter or upstream CI settings. Preserve module-local hooks and loaders outside those roots.

Core files to reconcile:

- `library/h5p.classes.php`, `h5p-default-storage.class.php`, `h5p-file-storage.interface.php`, `embed.php`, and the other core PHP classes.
- All matched `library/js/`, `library/styles/`, `library/fonts/` and `library/images/` files listed in the lock, including the new theme/tooltip/table assets and fonts.
- Preserve the already-matching development class and metadata behavior unless separately fixing a characterized defect.

Editor files to reconcile:

- `modules/h5peditor/h5peditor/h5peditor.class.php`, `h5peditor-file.class.php` and matched interfaces/AJAX class.
- All selected `scripts/`, `language/`, `styles/css/`, `images/`, `fonts/` and `libs/` files, including `h5peditor-html.js`, `h5peditor-pre-save.js`, image popup and cropper dependencies.
- Replace the obsolete CKEditor 4 distribution with the selected `ckeditor/ckeditor.js` custom build. Remove only obsolete assets shown by the reviewed mapping and proven unreferenced; keep licenses and the selected build provenance. Old Fabric/Darkroom files should be removed only after verifying no remaining code references them.
- Source/build files, maps and README/license files can be retained for provenance as appropriate. Copy upstream Composer metadata if vendoring it, but do not run Composer or change a Composer dependency resolution.

Dependency: copy compiled PHP/JS/CSS/font/image assets from the exact commits together; no npm rebuild, Composer update, installed content-library update or database migration is inherent in this source import.

### Step C — adapt the Backdrop integration

| File and present location | Required work | Dependency |
| --- | --- | --- |
| [h5p.classes.inc](C:/wamp64/www/backdrop/modules/h5p/h5p.classes.inc:3) | Implement `resetHubOrganizationData()` to satisfy the new framework interface. Its target declaration is at core line 675; Hub failure branches call it at lines 3929, 4004 and 4138. Define/reset only the organization/Hub settings required by upstream semantics, preserving content and unrelated options; test with mocked responses. | New Core interface and configuration stubs/lab. |
| [h5p.classes.inc](C:/wamp64/www/backdrop/modules/h5p/h5p.classes.inc:8) | Report `BACKDROP_VERSION` rather than Drupal compatibility `VERSION` at line 15. | Existing Backdrop constant. |
| [h5p.classes.inc](C:/wamp64/www/backdrop/modules/h5p/h5p.classes.inc:663) and [config/h5p.settings.json](C:/wamp64/www/backdrop/modules/h5p/config/h5p.settings.json:1) | Review file allowlists. Target defaults add gltf/glb and move SVG/XML from content to library extras, but this adapter currently uses saved config and ignores supplied defaults. Make the policy explicit and test it; do not silently overwrite a site's saved configuration. | Core validator behavior plus fixtures/synthetic SVG/XML cases. |
| [h5p.classes.inc](C:/wamp64/www/backdrop/modules/h5p/h5p.classes.inc:977) | Audit missing-option/default handling; `getOption` currently ignores its default argument. Add regression assertions and implement the documented interface behavior where required. | Upstream option callers and isolated configuration. |
| [modules/h5peditor/h5peditor-backdrop-ajax.class.inc](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor-backdrop-ajax.class.inc:14) | Add `patch_version_in_folder_name = FALSE` to returned latest-library records for existing unpatched folder naming. The selected editor accesses that property when constructing icon paths at its lines 628–633. Prefer an adapter-computed field; a new database column is not required. | New editor plus folder tests. |
| [modules/h5peditor/h5peditor-backdrop-storage.class.inc](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor-backdrop-storage.class.inc:96) and [h5p.classes.inc](C:/wamp64/www/backdrop/modules/h5p/h5p.classes.inc:767) | Check returned keys, library metadata, extension validation, managed temporary/private upload behavior and new path helper usage. Add explicit false folder flags only where consumed; preserve metadata and hook behavior. | New editor/core, pure tests and lab uploads. |
| [h5p.module](C:/wamp64/www/backdrop/modules/h5p/h5p.module:988), line 1359 and line 1660 | Load all new core assets in normal/iframe modes; preserve separate editor storage and relative-path override; confirm autoload mappings at line 1765 cover selected PHP classes. | Complete core assets and storage contracts. |
| [modules/h5peditor/h5peditor.module](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor.module:219), line 240 and line 577 | Load new editor assets/languages, keep CKEditor handling appropriate for its new bundle, review API/settings propagation and class registrations. Confirm dynamically loaded cropper URLs work. | Complete editor assets and browser tests. |
| [modules/h5peditor/scripts/application.js](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/scripts/application.js:18) | Review namespace/API/asset settings, title/language and asynchronous save/submitter behavior. This is Backdrop code and must be preserved while integrating the new editor. | New editor and lab form tests. |
| [h5p.admin.inc](C:/wamp64/www/backdrop/modules/h5p/h5p.admin.inc:851) and [library/h5p-metadata.class.php](C:/wamp64/www/backdrop/modules/h5p/library/h5p-metadata.class.php:64) | Track the existing metadata JSON defect. If its repair is included, make it a separate tested change; do not conflate it with the API bump. | Existing-defect test and explicit scope decision. |
| [h5p.install](C:/wamp64/www/backdrop/modules/h5p/h5p.install:359) and [modules/h5peditor/h5peditor.install](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor.install:1) | Review schema/cache compatibility; retain Hub API requirement fields. Add an update hook only if tests demonstrate an actual required configuration/schema migration. | Inspection of lab schema and cloned-database tests, if later authorized. |

The new folder API is a concrete coupling: [`libraryToString`](https://github.com/h5p/h5p-php-library/blob/2aeb0b83fa603e331381b3a6b8bf42c3773ba140/h5p.classes.php#L2761) now produces only display names; [`libraryToFolderName`](https://github.com/h5p/h5p-php-library/blob/2aeb0b83fa603e331381b3a6b8bf42c3773ba140/h5p.classes.php#L2772) handles filesystem names and an optional patch suffix. Existing old folder calls occur in core validator/storage and editor at [library/h5p-default-storage.class.php](C:/wamp64/www/backdrop/modules/h5p/library/h5p-default-storage.class.php:42), [library/h5p.classes.php](C:/wamp64/www/backdrop/modules/h5p/library/h5p.classes.php:996), [modules/h5peditor/h5peditor/h5peditor.class.php](C:/wamp64/www/backdrop/modules/h5p/modules/h5peditor/h5peditor/h5peditor.class.php:406) and lines 625/747. Update core and editor together so these old calls are superseded by their matching upstream code. Keep existing content-library directories as `name-major.minor`; do not rename them or enable patch suffixes for the current installation.

The selected editor storage interface, AJAX interface and AJAX class are byte-identical to the local versions. The selected framework interface adds one missing required method, `resetHubOrganizationData`. This reduces adapter work but does not replace the target load/runtime tests.

### Step D — qualify and prepare a reviewable result

Run lint/contract/path/package tests before importing any content in the lab. Run all 21 package validations, representative edit-save-export-reimport cases and browser checks, including EN/FR PapiJo, cropper, rich text, icons/fonts, scoring, iframe and hooks. Verify original installed libraries and exports with the baseline verifier's `-SkipRepositoryFiles` option.

Record exact upstream pins, any retained local patches and test results. Review each vendor deletion and asset dependency. Any failing content-preservation or interface test blocks acceptance. No current-site schema updates, content-library upgrades, commits or pushes follow automatically from this plan.

Phase 1 ends here. Approval is required before implementing Phase 2.

