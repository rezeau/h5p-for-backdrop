# H5P Core API 1.28 integration

The module vendors H5P Core 1.28.0 and the matching Editor, including the H5P
CKEditor 5 build, table support, French translation, image cropper and controls.
The Backdrop adapters preserve existing content parameters, metadata and
major/minor library directory names. No database schema migration or installed
content-library upgrade is part of this change.

## Source provenance

| Component | Upstream revision |
| --- | --- |
| PHP Core and runtime assets | [h5p/h5p-php-library, 1.28.0](https://github.com/h5p/h5p-php-library/tree/2aeb0b83fa603e331381b3a6b8bf42c3773ba140) |
| PHP Editor and bundled assets | [h5p/h5p-editor-php-library, moodle-1.28.2](https://github.com/h5p/h5p-editor-php-library/tree/73dfed6073b8916faa7e8b1a5fca5adb2bde5dc4) |

[upstream-sources.lock.json](upstream-sources.lock.json) records the complete
upstream file inventories, Git blobs, asset entry points and bundled dependency
pins. Core includes all 65 distribution files. Its upstream `.gitignore` and
`.travis.yml` are intentionally excluded; they are not runtime dependencies.
Eight existing font files remain for backward compatibility. The Editor tree
contains exactly all 214 pinned files, including its `.gitignore`, with no vendor
patches or conflicting legacy CKEditor assets. Bundled assets require no local
Composer or npm installation.

## Intentional Core vendor differences

Two Core files differ from the pin:

1. [libraryToString()](../../library/h5p.classes.php) retains the legacy optional
   folder-name argument and delegates it to `libraryToFolderName()`. Normal
   display names and major/minor folder names retain their existing behavior.
2. [The event-base comment](../../library/h5p-event-base.class.php) retains the
   Backdrop platform name. This difference has no runtime effect.

Their exact SHA-256 hashes are recorded in the portable baseline below. The
Core regression checks these two files and all 63 other imported files against
their respective hashes. Vendor source is not normalized or rewritten.

## Backdrop integration

- [Framework adapter](../../h5p.classes.inc): Core interface compliance, Backdrop
  version reporting, option defaults, folder flags, optional metadata handling
  and targeted node-cache invalidation after derived fields are persisted.
- [Module hooks](../../h5p.module): metadata-preserving submission/import paths,
  unprefixed resizer URLs in multilingual contexts and the storage bridge.
- [File-storage bridge](../../h5p-backdrop-file-storage.class.inc): supports both
  Core's three-argument and Editor's library-record upgrade-script calls.
- [Editor integration](../../modules/h5peditor/h5peditor.module): ordered local
  assets and English/French UI settings. The [compatibility script](../../modules/h5peditor/scripts/h5peditor-backdrop-compat.js)
  preserves nested language updates and rich-text language initialization outside
  the pinned vendor code.
- [Editor storage](../../modules/h5peditor/h5peditor-backdrop-storage.class.inc)
  treats missing optional JSON values safely while retaining native malformed
  nonempty JSON results.

## Portable test fixtures and patch registers

[local-baseline.json](local-baseline.json) retains its filename for compatibility
with the Core suite. It now contains only 25 module-source hash records from
the public base commit `3760086a451a96dc781cabb9b0d29b6d082054cd`, 17 synthetic
library folder cases and the two intentional Core differences. It contains no
site paths, installed-library inventory, exported-content metadata or credentials.
Vendor inventories are verified independently using the upstream source lock.

The exact reversible adapter spans remain in
[phase-3b-step2-patches.json](phase-3b-step2-patches.json),
[phase-3b-step3-patches.json](phase-3b-step3-patches.json),
[metadata-null-patches.json](metadata-null-patches.json),
[content-metadata-patches.json](content-metadata-patches.json) and
[node-cache-patches.json](node-cache-patches.json). These registers allow the
tests to recover the public baseline without broadly excluding adapter files.

See [tests/README.md](../../tests/README.md) for commands, coverage and optional
caller-supplied export fixtures. Tests use memory-only database/configuration
doubles and owned temporary storage; they do not bootstrap a live site.

## Verification and limits

The upgrade's automated suites passed on PHP 7.4 and 8.4. Contributor-reported
manual tests covered creation, editing, saving and playback of official and
third-party content, including the updated H5P appearance. Library-specific
appearance still depends on the installed content library and its version.
This is not exhaustive verification of every content type.

Live Hub registration, network operations and permissions require separate
qualification. The pinned jQuery wrapper's unsupported `.load(url)` behavior
remains explicitly characterized by its regression. Tests do not establish
full browser behavior, HTML save round trips or database migration behavior.
