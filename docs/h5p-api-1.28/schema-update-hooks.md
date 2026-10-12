# Obsolete H5P update hooks

The six Drupal 6 hooks `h5p_update_6100()` through `h5p_update_6105()` had empty
bodies. Removing their declarations and accompanying TODO comments is the only
production change. The installation schema, `h5p_update_1000()`, the real
`h5p_update_7123()` migration, `h5p_update_last_removed()` returning `7122`, and
all other hooks remain byte-for-byte unchanged. No new migration is introduced.

Backdrop discovers hooks from their function names. It orders versions
`7000..8999` before other versions, so H5P's exact discovery order is now:

```text
7100, 7101, 7102, 7103, 7104, 7105, 7106, 7107,
7108, 7109, 7110, 7111, 7112, 7113, 7114, 7115,
7116, 7117, 7118, 7119, 7120, 7121, 7122, 7123, 1000
```

Fresh installation uses the last discovered version, `1000`. The current
installation schema already supplies the Hub cache table and all four fields
from migration `7123`; historical update hooks are not run on fresh install.

| Stored version | Discovery after this correction |
| --- | --- |
| Fresh install | Records `1000`; no pending updates or status warning |
| `6105` | Status becomes `h5p (0 → 1000)`; queues `7100..7123`, then `1000`; still needs separate correction |
| `1000` | No pending updates or status warning |
| `7122` | Queues the genuine migration `7123`, then the conversion hook `1000` |
| `0` | Retained removed-update boundary blocks migration; requires separate investigation |

The displayed `0` can be Backdrop's normalization of a stored Drupal 6 version
such as `6105`. It does not establish that `{system}.schema_version` is actually
zero. Removing declarations cannot change an existing stored version.

## Procedure for existing installations

This procedure is for a separately authorized maintenance operation. The code
change and regression tests do not perform any of these operations on a site.

1. Deploy the declaration cleanup and inspect the raw installed version using
   `backdrop_get_installed_schema_version('h5p', TRUE)` in the correct site's
   bootstrapped maintenance context. Confirm the active module path. Do not
   infer the raw version from the status report.
2. For a native Backdrop installation stored at `6105`, independently confirm
   that its H5P schema is current and the Drupal-to-Backdrop conversion represented
   by hook `1000` is not pending. In particular, confirm the
   `h5p_content_hub_metadata_cache` table with `language`, `json` (big text) and
   `last_checked` fields, plus `h5p_nodes.shared`, `synced`, `content_hub_id` and
   `a11y_title`. Preserve existing H5P configuration and take a normal backup.
3. Once those conditions are verified, use
   `backdrop_set_installed_schema_version('h5p', 1000)` once in that maintenance
   context. This updates Backdrop's bookkeeping and resets its schema-version
   cache. It does not migrate tables or content. Recheck the raw version and
   status report; both should now be current at `1000`.
4. An existing `1000` installation needs no version correction. If real schema
   elements are missing, the site genuinely needs schema repair; do not mark it
   current. Retain migration `7123` and use an independently reviewed migration
   path. If the raw version is `0` or the site still needs Drupal conversion,
   investigate its installation history before changing the marker.

Running the updater with the cleaned declarations can also end at `1000`, but
a stored `6105` queues all retained legacy hooks, including the configuration
conversion in `1000`. The targeted bookkeeping correction above avoids
replaying that conversion on a verified, already installed Backdrop site.
Never force the marker to `6105` or `7123`, remove the genuine migration, or
change the removed-update boundary merely to suppress a warning.

## Focused verification

```sh
php -n tests/schema-update-hooks.php --backdrop-root=<Backdrop-source-root>
```

The suite uses Backdrop's actual discovery and update-list APIs, its fresh-install
version-selection block and its status-comparison block. Database access is
replaced entirely by memory rows. It checks fresh installs, existing `6105`
and `1000`, genuine pending `7123`, and the raw-zero boundary. It never executes
`update.php`, H5P update hooks, a site bootstrap or DDL. The exact reversible
[deletion register](schema-update-hooks-patches.json) keeps the existing frozen
installation-byte comparison intact. This does not establish the schema state
of any local or hosted database.
