# Lib-Main
The libraries main website built on drupal 11

## Description

## Development

### Prerequisites

- [DDEV](https://ddev.com/get-started/)

### Installation Steps

1. **Clone the repository** (if not already done):
   ```zsh
   git clone <repository-url>
   cd lib-main
   ```

2. **Start DDEV**:
   ```zsh
   ddev start
   ```

3. **Install PHP dependencies**:
   ```zsh
   ddev composer install
   ```

4. **Set up the Drupal installation** (if this is a fresh installation):
   ```zsh
   ddev drush site:install standard --account-name=admin --account-pass=admin
   ```

5. **Import database** (if you have an existing database export):
   ```zsh
   ddev drush sql:cli < path/to/database.sql
   ```

6. **Clear caches**:
   ```zsh
   ddev drush cache:rebuild
   ```

7. **Access the site**:
   - Open your browser and navigate to: `https://lib-main.ddev.site`
   - Admin login: `https://lib-main.ddev.site/user/login`

### Useful DDEV Commands

- `ddev start` - Start services
- `ddev stop` - Stop services
- `ddev restart` - Restart services
- `ddev drush` - Run Drush commands
- `ddev composer` - Run Composer commands
- `ddev logs` - View service logs
- `ddev describe` - View project information

### Troubleshooting

- If you encounter permission issues, run: `ddev fix-permissions`
- To reset the database: `ddev drush sql:drop` followed by `ddev drush site:install standard`
- For more help, consult the [DDEV documentation](https://ddev.readthedocs.io/)
