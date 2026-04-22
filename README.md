# Lib-Main

The University of Tennessee Libraries main website ([lib.utk.edu](https://lib.utk.edu)), built on Drupal 11. Managed with Composer, developed locally with DDEV, and deployed to Azure.

- **Repository:** https://github.com/utkdigitalinitiatives/lib-main
- **Infrastructure/Deployment:** https://github.com/utkdigitalinitiatives/lib-main-infra

## Architecture

| Environment | Database              | File Storage       |
| ----------- | --------------------- | ------------------ |
| Local       | PostgreSQL (via DDEV) | Local filesystem   |
| Production  | PostgreSQL (Azure)    | Azure Blob Storage |

## Local Development

### Prerequisites

- [DDEV](https://ddev.com/get-started/)
- Access to a recent database backup (see below)

### Setup

1. **Clone the repository:**

   ```zsh
   git clone https://github.com/utkdigitalinitiatives/lib-main
   cd lib-main
   ```

2. **Start DDEV and install dependencies:**

   ```zsh
   ddev start
   ddev composer install
   ```

   > **Note:** `.ddev/config.yaml` is committed to the repository and pre-configures DDEV to use PostgreSQL 16, matching production. Do **not** run `ddev config` — it will reset the database type to MySQL and the import will fail.

3. **Get a database backup** from the Azure Portal Cloud Shell:

   ```zsh
   pg_dump <connection-options> > backup.sql
   ```

   Contact a team member for the exact connection string and credentials.

4. **Import the database:**

   ```zsh
   ddev import-db --file={path/to/backup.sql}
   ```

   The backup includes existing user accounts — no need to create an admin user manually.

5. **Clear caches:**

   ```zsh
   ddev drush cache:rebuild
   ```

6. **Access the site:**
   - Site: `https://lib-main.ddev.site`
   - Admin login: `https://lib-main.ddev.site/user/login`

### Common DDEV Commands

| Command               | Description           |
| --------------------- | --------------------- |
| `ddev start`          | Start services        |
| `ddev stop`           | Stop services         |
| `ddev restart`        | Restart services      |
| `ddev drush <cmd>`    | Run Drush commands    |
| `ddev composer <cmd>` | Run Composer commands |
| `ddev logs`           | View service logs     |

## Deployment

Production deployments are managed via GitHub Actions in the [lib-main-infra](https://github.com/utkdigitalinitiatives/lib-main-infra) repository.

## Troubleshooting

- Permission issues: `ddev fix-permissions`
- See the [DDEV documentation](https://ddev.readthedocs.io/) for general help
