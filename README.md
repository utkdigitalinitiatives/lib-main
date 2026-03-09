# Libraries Mainsite

On Drupal 11

## Prerequisites

- [DDEV](https://ddev.readthedocs.io/en/stable/) (v1.22.0 or higher)
- [Composer](https://getcomposer.org/) (included with DDEV)

## Getting Started

### Initial Setup

1. **Clone the repository**
   ```zsh
   git clone <repository-url>
   cd lib-main
   ```

2. **Start DDEV**
   ```zsh
   ddev start
   ```

3. **Install dependencies**
   ```zsh
   ddev composer install
   ```

4. **Install Drupal**

   @TODO: include database instructions

   Or import an existing database:
   ```zsh
   ddev import-db --file=/path/to/database.sql.gz
   ```

## Development Workflow

### Configuration Management

1. **Export configuration after changes**
   ```zsh
   ddev drush cex
   git add config/
   git commit -m "{commit message}"
   ```

2. **Import configuration before making changes**
   ```zsh
   git switch dev && git pull
   ddev drush cim -y
   ddev drush cr
   git switch -c {new-branch-name}
   ```

## Troubleshooting

### Clear all caches
```zsh
ddev drush cr
```

### Rebuild DDEV containers
```zsh
ddev restart
```

### Fix file permissions
```zsh
ddev exec chmod -R 755 web/sites/default/files
```

### Reset local environment
```zsh
ddev stop
ddev delete -O
ddev start
ddev composer install
ddev drush cim -y
ddev drush cr
```

### View error logs
```zsh
# PHP error logs
ddev logs

# Drupal watchdog logs
ddev drush watchdog:show

# Tail watchdog logs
ddev drush watchdog:tail
```

### Performance Issues
```bash
# Disable CSS/JS aggregation for local development
ddev drush config:set system.performance css.preprocess 0 -y
ddev drush config:set system.performance js.preprocess 0 -y
ddev drush cr
```

## License

See [LICENSE.txt](LICENSE.txt) for details.
