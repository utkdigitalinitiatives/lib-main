# Developer Onboarding Guide

This guide walks new developers through setting up the Lib-Main project locally and covers the team's daily development workflow.

For additional internal documentation, see the team SharePoint site — **Central Docs** folder.

---

## Prerequisites

Install the following tools before starting:

- [DDEV](https://ddev.com/get-started/) — local development environment manager
- [Azure CLI](https://learn.microsoft.com/en-us/cli/azure/install-azure-cli) — Azure command-line tools
- [AzCopy](https://learn.microsoft.com/en-us/azure/storage/common/storage-use-azcopy-v10) — Azure Blob Storage sync tool

You will also need:

- Access to the Azure Portal (coordinate with a team member or system administrator)
- Storage Blob Data Contributor role on the dev storage account (coordinate with system administrator)
- Access to the team's Microsoft Teams workspace
- Access to the team's SharePoint site **Central Docs** folder

---

## First-Time Local Setup

### 1. Clone the repository

```zsh
git clone https://github.com/utkdigitalinitiatives/lib-main
cd lib-main
```

### 2. Start DDEV

```zsh
ddev start
```

> **Important:** Do **not** run `ddev config`. DDEV's interactive configuration defaults to MySQL or MariaDB and will overwrite the committed `.ddev/config.yaml`, which configures PostgreSQL 16 to match production. Always use the committed configuration as-is.

### 3. Install Composer dependencies

```zsh
ddev composer install
```

### 4. Configure Azure asset credentials

The `ddev pull-assets` and `ddev refresh-local` commands require read access to the Azure Blob Storage container that holds the site's public files.

1. Copy the example environment file:

   ```zsh
   cp .ddev/.env.assets.dist .ddev/.env.assets
   ```

2. Open `.ddev/.env.assets` and fill in `AZURE_PUBLIC_BLOB_URL` with the Azure Blob SAS URL. Obtain this value from a team member or system administrator.

> `.ddev/.env.assets` is listed in `.gitignore` and must **never** be committed to the repository.

### 5. Obtain a database dump

Export and download a database backup from Azure Portal Cloud Shell (get the shell command and password from the System Administrator). Save the file somewhere accessible on your local machine (for example, `db_dump.sql` in the project root).

### 6. Run the local refresh

```zsh
ddev refresh-local path/to/db_dump.sql
```

If the dump is named `db_dump.sql` and placed in the project root, the path argument can be omitted:

```zsh
ddev refresh-local
```

This command handles the full local setup sequence in one step. See [Custom DDEV Commands](#custom-ddev-commands) for details on what it does.

> **Important local override policy:** Local-only `settings.local.php` overrides are required after `ddev refresh-local` (including for admin pages that touch storage wrappers). Do not include override values in this onboarding guide. Use the team's shared `settings.local.php` guidance maintained outside the repository so local setups stay consistent and uncommitted.

### 7. Verify the site

- **Site:** `https://lib-main.ddev.site`
- **Admin login:** `https://lib-main.ddev.site/user/login`

User accounts are included in the database dump — no need to create an admin account manually.

---

## Local vs. Production File Handling

Understanding how file storage differs between environments is important for working safely on this project.

**In production**, Drupal uses Azure Blob Storage as the file system backend. Uploaded and managed files use `azblob://` URIs and are served from Azure Blob.

**In local development**, this is intentionally changed to use the local public filesystem (`public://`). The `ddev refresh-local` command handles this automatically by:

1. Setting Drupal's default file scheme to `public://`
2. Rewriting all existing `azblob://` URIs in the database to `public://`
3. Syncing a copy of the Azure Blob assets into `web/sites/default/files/`

> **This storage wrapper change must only ever occur on local development environments.** It must never be applied to production or any shared/staging instance. The `ddev refresh-local` command is designed to run only against a local DDEV environment — do not run it against any remote database.

---

## Solr Configuration

Search API Solr servers are pointed at their endpoint via environment variables, both locally and in deployed environments.

- Locally, DDEV starts Solr (`ddev start` / `ddev restart`) and supplies `SOLR_SCHEME`, `SOLR_HOST`, `SOLR_PORT`, `SOLR_PATH`, `SOLR_CORE`, `SOLR_USERNAME`, and `SOLR_PASSWORD` via `.ddev/config.solr.yaml`.
- `web/sites/default/settings.php` reads `SOLR_*` env vars (when `SOLR_HOST` is set) and applies them as a runtime config override on `search_api.server.solr`, so the connection is never written to config storage or `/config`.
- Because it's a read-time override, `drush config:import` can be run at any time without breaking or needing to reapply the Solr connection.
- In deployed environments, Terraform provides the same `SOLR_*` variables as application runtime environment variables; no separate Drupal-side configuration is required.

---

## Custom DDEV Commands

### `ddev refresh-local [path/to/db_dump.sql]`

The primary command for setting up or refreshing your local environment from a production database dump.

```zsh
ddev refresh-local path/to/db_dump.sql
# or, if db_dump.sql is in the project root:
ddev refresh-local
```

**What it does, in order:**

1. Imports the specified database dump
2. Configures Drupal's default file scheme to `public://` (local filesystem)
3. Rewrites all `azblob://` URIs in `file_managed` to `public://`
4. Runs `ddev drush config:import` to sync active configuration with committed config files in `/config/`
5. Runs `ddev pull-assets` to sync Azure Blob assets to `web/sites/default/files/`
6. Clears Drupal caches

Run this before starting work on any feature-scope changes to ensure your local environment reflects the current production state.

### `ddev pull-assets`

Syncs the Azure Blob public assets to your local `web/sites/default/files/` directory using AzCopy, without importing a database.

```zsh
ddev pull-assets
```

Use this when you only need to refresh local asset files independently — for example, after `ddev refresh-local` has already been run and new media has been added to production.

Requires `.ddev/.env.assets` to be configured with a valid `AZURE_PUBLIC_BLOB_URL`.

---

## Daily Development

### Starting and stopping DDEV

```zsh
ddev start      # Start services
ddev stop       # Stop services
ddev restart    # Restart services
```

### Common commands

| Command                        | Description                                     |
| ------------------------------ | ----------------------------------------------- |
| `ddev refresh-local`           | Import DB dump, sync config, and sync assets    |
| `ddev pull-assets`             | Sync Azure Blob assets only                     |
| `ddev drush config:import`     | Sync active config with committed config files  |
| `ddev drush config:export`     | Export changes to configuration to config files |
| `ddev drush cr`                | Rebuild Drupal caches                           |
| `ddev drush <cmd>`             | Run any Drush command                           |
| `ddev composer <cmd>`          | Run any Composer command                        |
| `ddev drush image:flush --all` | Flush all image style derivatives               |
| `ddev logs`                    | View service logs                               |

### When to refresh your local environment

Run `ddev refresh-local` before starting work on any feature-scope updates. This ensures your local database and files reflect the current production state before you begin.

---

## Git Workflow

### Branch naming

Create a branch named for what is being worked on. Do not use prefixes such as `feature/`, `bugfix/`, or similar.

**Examples:**

- `profile-fields-and-feed`
- `homepage-layout`
- `event-feed-fix`
- `accessibility-nav-updates`

### Branch strategy

In almost all cases, new topic branches should be branched off the `dev` branch.

All work happens in topic branches. Pull requests from topic branches must target **`dev`**. Only the **`dev`** branch may merge into **`main`**.

```
topic-branch → dev → main
```

### Merge strategy

All PRs should use the "merge commit" strategy.

### Pull requests

Open pull requests on GitHub. PR reviews and discussions happen on GitHub. Day-to-day communication happens on Microsoft Teams — not GitHub.

---

## Troubleshooting

### `ddev config` overwrites the database configuration

Running `ddev config` interactively resets the database type to MySQL or MariaDB, which breaks this project's PostgreSQL setup. If this happens, restore the correct configuration from version control:

```zsh
git restore .ddev/config.yaml
ddev restart
```

### `ddev pull-assets` fails: missing `.env.assets`

If you see `ERROR: Missing .ddev/.env.assets`, copy and fill in the example file:

```zsh
cp .ddev/.env.assets.dist .ddev/.env.assets
```

Then fill in `AZURE_PUBLIC_BLOB_URL` with the SAS URL from a team member or system administrator.

### `ddev pull-assets` fails: `azcopy` not found

Install AzCopy locally. See the [AzCopy installation documentation](https://learn.microsoft.com/en-us/azure/storage/common/storage-use-azcopy-v10).

### Images not displaying after `refresh-local`

Flush image style derivatives:

```zsh
ddev drush image:flush --all
```

### Permission issues

If you encounter file permission errors — such as Drupal being unable to write to `web/sites/default/files/`, or errors when running Drush commands — run:

```zsh
ddev fix-permissions
```

This command resets ownership and permissions on the project's files to match what the DDEV web container expects. Common situations where this may be needed include:

- After manually copying or moving files into `web/sites/default/files/` from outside DDEV
- After running `ddev pull-assets` results in files owned by a different user or process
- After a system update or Docker restart that affects container file ownership

### General DDEV help

See the [DDEV documentation](https://ddev.readthedocs.io/) for general troubleshooting and reference.

---

## Communication and Resources

| Resource                    | Where                                                                     |
| --------------------------- | ------------------------------------------------------------------------- |
| Day-to-day communication    | Microsoft Teams                                                           |
| Pull request discussions    | GitHub                                                                    |
| Internal team documentation | SharePoint — Central Docs folder                                          |
| Infrastructure/deployment   | [lib-main-infra](https://github.com/utkdigitalinitiatives/lib-main-infra) |
