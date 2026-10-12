# H5P integration regression tests

Run commands from the module root using the selected PHP executable on PATH.
Core package tests require the existing `zip` and `mbstring` extensions. No
Composer, npm or test-framework installation is needed.

```sh
php tests/core-api-128.php
php tests/editor-compatibility.php
php tests/editor-metadata-json.php
php tests/content-metadata.php
php tests/editor-assets.php en new
php tests/editor-assets.php en existing
php tests/editor-assets.php fr new
php tests/editor-assets.php fr existing
node tests/core-jquery-compatibility.cjs
node tests/editor-language-compatibility.cjs
node tests/editor-runtime-compatibility.cjs
```

The resizer, node-cache and schema-update tests additionally read Backdrop source.
Substitute an existing source checkout for the placeholder; none bootstraps its
site, loads settings or connects to its database.

```sh
php tests/resizer-language.php --backdrop-root=<Backdrop-source-root>
php tests/node-cache.php --backdrop-root=<Backdrop-source-root>
php -n tests/schema-update-hooks.php --backdrop-root=<Backdrop-source-root>
```

Replace `php` with the selected version's executable as needed. PHP 7.4 and 8.4
were used for upgrade verification. For extension-isolated runs, use `php -n`
with the appropriate extension-loading options; ZIP may be built in or loaded.

## Isolation and coverage

Tests load the actual vendors and adapters with in-memory configuration, SQL,
stream-wrapper and hook doubles. Unexpected database queries fail. Package
creation, extraction, storage copies and cleanup are confined to randomly named,
owned OS temporary directories. Libraries are never installed and site content
is never saved. Unmasked PHP warnings and deprecations fail tests.

| Suite | Coverage |
| --- | --- |
| Core API | API compatibility/rejection, interfaces, synthetic folders, storage, copied media, allowlists, options, assets, source provenance and parameters |
| Editor compatibility | Storage calling conventions, library records, folder flags and cached icon initialization |
| Editor metadata JSON | Null, empty, valid, scalar and malformed JSON in both record paths |
| Content metadata | Actual submit, insert, update and import hooks; omitted/valid/malformed metadata and preserved parameters |
| Editor assets | Ordered local resources, English/French UI and new/existing content |
| Resizer language | Actual locale rewriting; unprefixed resizer and normal French prefixes |
| Node cache | Actual Backdrop cache and Core filtering; targeted reset, revision IDs, warm playback/edit reuse and unrelated nodes |
| Schema update hooks | Actual Backdrop discovery, fresh-install version selection, status comparisons and memory-only version storage; existing 6105/1000, genuine pending 7123, preserved removed-update boundary and exact installation-byte provenance |
| JavaScript | Wrapper, nested languages, CKEditor configuration, rich-text preservation, summaries, initialization callback and asset closure |

The runtime JavaScript suite uses the committed synthetic
[HTML semantics fixture](fixtures/editor-html/H5P.EditorFixture-1.0/semantics.json)
by default. `--libraries=<semantics-directory>` can read caller-supplied semantics
without modifying them. It requires at least one HTML widget and checks every
discovered field, without a site-specific count. Fixtures are never installed.

The pinned wrapper's unsupported `.load(url)` behavior remains explicitly
characterized; `.load(fn)` is covered as a working contract. These tests do not
establish full browser behavior, HTML save round trips, live Hub registration,
permissions or database migrations.

The schema-update suite loads only installation/update API definitions and H5P
installation definitions. It evaluates the actual fresh-install version-selection
and status-comparison source blocks with memory-only collaborators. It never
loads `update.php`, settings, vendors or a database driver, and never executes H5P
update hooks. It verifies discovery and bookkeeping, not a live schema migration.
See [the existing-site procedure](../docs/h5p-api-1.28/schema-update-hooks.md).

## Portable baseline and provenance

[local-baseline.json](../docs/h5p-api-1.28/local-baseline.json) contains module
hashes from the public base commit, synthetic folder cases and the two documented
Core differences. No site paths, installed-library inventories or exported-content
records are included. The upstream lock checks all 214 Editor files and all 65
imported Core files, accounting for the two exact differences. Conflicting legacy
Editor files are rejected.

[step2-provenance.php](step2-provenance.php) reverses only the exact adapter spans
registered in [the upgrade documentation](../docs/h5p-api-1.28/README.md) before
public-baseline comparisons. Before/after hashes and unique replacement spans
remain checked; no adapter file is generally excluded.
The [schema hook register](../docs/h5p-api-1.28/schema-update-hooks-patches.json)
reverses only the six empty Drupal 6 declarations before the original
`h5p.install` hash comparison; every other installation byte stays protected.

## Optional export packages

Supply a directory of disposable or read-only `.h5p` packages explicitly:

```sh
php tests/core-api-128.php --fixtures=fixture-files
php tests/core-api-128.php --fixtures=fixture-files --fixture-manifest=fixture-hashes.json
```

Without a manifest, `.h5p` files are discovered in that directory. With a manifest,
selected basenames and expected SHA-256 hashes are checked. The manifest is a
nonempty JSON list with `file` and `sha256` records. Replace the hash placeholder:

```json
[{"file":"example.h5p","sha256":"<64 hexadecimal characters>"}]
```

Unsafe archive paths are rejected before disposable-copy validation. Parameters
and header identity are compared with the original, and each source hash is
checked before and after. Missing packages, duplicate names, escaping paths and
invalid manifest entries are rejected. No private inventory or historical export
digest is distributed.

The retained `--skip-fixture-baseline` option explicitly skips the external
manifest's expected-hash comparison. It never skips per-run source integrity or
package validation. Omit it when verifying an approved manifest.
