# Bookplate migration

Imports published legacy `bookplate` nodes for one award year, including book
covers and honoree photos as image media and files. Source IDs are used only in
migration maps, never as destination entity IDs. All text fields, booleans, ISBN,
record link, and timestamps are mapped; formatted text uses `editor_html`.
Only the default-language node and the first item of each field are imported.

## Prerequisites

Back up the destination database and files first. The legacy database must be
available as `$databases['migrate']['default']`. The fallback in `settings.php`
preserves the intended connection from `settings.local.php` when defined there.
Production must be able to reach that database and the legacy public files URL.
Deployment of the module code and database credentials still requires the normal
sysadmin/deployment process; the browser cannot configure database credentials.

## GUI-only walkthrough (production)

No Drush, command shell, configuration import, or migration config entities are
required to select a year, import, inspect messages, retry failures, or roll back.

1. Deploy this module's code. Sign in as an administrator with **Administer
   modules** and **Administer migrations** permissions. On **Extend**
   (`/admin/modules`), enable **Custom Bookplate Migrate** and its dependencies.
   If already enabled before deploying this GUI, use **Configuration >
   Development > Performance > Clear all caches**
   (`/admin/config/development/performance`) to discover the new routes/menu.
2. Open **Structure > Bookplate migration** (or the module's **Configure** link
   on Extend)
   (`/admin/structure/bookplate-migrate`). Use this dedicated page, **not**
   Migrate Tools' generic Migration groups UI: these migration definitions live
   in `migrations/` rather than configuration entities.
3. Enter **Award year**: use **2025** for local trials, or **2026** when those
   records are available in the production source database. The default is 2026.
   The status table shows totals across **all** imported years, not a preview
   count for the selected year.
4. Choose **Import selected year**, leave **Retry failed rows** unchecked for a
   first run, and click **Execute**. A missing/unreachable source database is
   reported as a form error and logged. A year with no published source
   bookplates is rejected without starting an import.
5. Keep the Drupal batch progress page open until it returns to the migration
   page. Files run first, then image media, then nodes. Work is divided into small
   requests so the entire import need not fit into one web request. The selected
   year is captured for every batch step, even if a developer changes the
   site-wide year setting in the meantime.
6. Review the result notices, the **Failed** column, and each **Messages** link.
   Messages are paginated (50 per page, newest first), across all years. Duplicate
   skips identify both source and existing destination nodes in their message;
   they are expected and do not mean the existing node was updated. Failures
   stop the run before later steps execute; a finished progress bar alone is
   not proof that all rows succeeded.
7. Verify imported bookplates under **Content**, including award year, cover,
   honoree photo, and the catalog link. Existing matching bookplates should be
   unchanged. A completed import can be run again without duplicating items.
8. If rows failed, fix the reported database/file/data problem, choose the same
   year, check **Retry failed rows for the selected year**, and execute **Import**
   again. Only failed rows eligible for that year are marked for retry;
   successful and skipped rows are not deliberately updated.

Only one operator should run migrations at a time. GUI runs reserve the module
until finished; do not run Drush migrations concurrently with a GUI batch.
Web-server access to the source files and writable destination storage are still
required. Batching cannot prevent a timeout on a single unusually slow download.

### Interrupted progress / abandoned runs

First try resuming the original browser progress page. If it is unrecoverable,
ensure no browser or CLI worker is still running. Wait **15 minutes after the
last GUI batch activity**, then select **Reset an abandoned run**, check the
confirmation checkbox, and execute. Reset clears statuses/reservations only; it
does not delete content or discard migration maps. Start Import again for the
same year; check **Retry failed rows** if needed. Do not resume the old batch
after a reset; its reservation is invalidated.

### Rollback through the GUI

On the same page, choose **Roll back ALL bookplate migration years**, read the
warning, check the confirmation checkbox, and execute. The year input does
**not** restrict rollback. A batch deletes imported nodes, then media, then
files, across **all years** in these maps. Skipped pre-existing bookplates and
year terms remain. Do not roll back if imported media/files have since been
reused elsewhere. Back up first.

Enabling the module changes active configuration. Export configuration through
the normal deployment workflow when appropriate; exports are not necessary for
the GUI import itself.

## Drush workflow (DDEV / sysadmins)

```sh
drush en custom_bookplate_migrate -y
drush cr

# Default is 2026. Use 2025 for trials against the current legacy snapshot.
drush state:set custom_bookplate_migrate.award_year 2025
drush migrate:status --tag=Bookplates
drush migrate:import --tag=Bookplates
drush migrate:messages bookplate_nodes

# Select 2026 when those source records are available.
drush state:set custom_bookplate_migrate.award_year 2026
```

Prefix commands with `ddev` locally. Enabling the module changes active
configuration; export configuration through the normal deployment workflow.
Migration definitions are discovered from `migrations/`, not installed config
entities, so changing years requires no configuration import or module reinstall.
The year selection is site-local Drupal state; the default is 2026 if unset.
The GUI and Drush share the same maps, duplicate protection, and default year.
The GUI adds batch execution and reports/controls for these discovered plugins;
it does not require the Drush executable on the production server.

## Existing content and failures

An exact match on **node title + stored book-title value** skips the entire node,
including on `--update`. Matching spans all destination award years and includes
unpublished content. No normalization of HTML, whitespace, or punctuation is
performed; confirm that these values agree between sites before importing.
Each skipped occurrence is recorded in migration messages and the
`custom_bookplate_migrate` logger, including the source and destination node IDs.
Existing duplicate pairs are never merged or modified.

Image migrations exclude media used only by already-existing pairs. Shared
source files/media are imported once by their source IDs. Missing referenced
images/files fail the dependent row rather than silently dropping the image.
Empty honoree names/book titles fail because the pair cannot identify the item
safely. Missing optional images are allowed. Do not run imports concurrently.

Year terms are looked up by name in `bookplate_year` and created when missing;
legacy term IDs are not copied. Re-runs use the migration maps to avoid repeated
imports. Changing year adds another year's rows to the same maps.

Downloads use the destination's default file scheme (including `azblob://`) and
write under `bookplate-migrate/<source fid>/` to avoid overwriting unrelated
files. They use the same `saveData()` approach as the news migration's blob
workaround, without requiring the news module. To change the legacy public
files base URL, set this in the site's local settings:

```php
$settings['custom_bookplate_migrate_source_public_prefix'] = 'https://lib.utk.edu/sites/default/files/';
```

Only legacy `public://` image files are supported. HTTP/storage errors are
reported as migration failures.

## Rollback

```sh
drush migrate:rollback bookplate_nodes
drush migrate:rollback bookplate_images
drush migrate:rollback bookplate_files
```

Rollback removes **all** rows imported by these migrations, across all selected
years, not just the currently selected year. It does not remove skipped existing
bookplates or year terms. Do not reuse imported media/files elsewhere before
rolling back.

## Regression trial

On the local DDEV site with the migration dependencies enabled:

```sh
ddev drush php:script web/modules/custom/custom_bookplate_migrate/tests/migration-regression.php
```

The script verifies the migration connection against `settings.local.php`,
reads real 2025 source data, and runs an import trial plus synthetic edge cases.
It also exercises the GUI form validation, abandoned-run recovery, batch
import/failure/retry, captured year, paginated messages, and all-year batch
rollback. An intentionally injected failure is expected in the test output.
It registers the module for that process only, rolls back imported entities and
files, and rolls back database changes. It refuses to run if these migrations
already have map tables. It requires a PostgreSQL destination so schema changes
can also be rolled back, and PDO SQLite for in-memory source-query fixtures.
Synthetic image downloads are mocked; any real 2025 images needed for new
bookplates are downloaded normally.
