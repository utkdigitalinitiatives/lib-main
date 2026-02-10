# Local setup for UTK

## Before You Start
- Ensure `ddev` is installed.

## Steps
1. Clone the project from GitHub into a `ddev`-enabled directory (see `ddev` documentation for requirements).
2. Navigate into the cloned directory:
   ```sh
   cd <cloned-directory>
   ```
3. Configure the project:
   ```sh
   ddev config --database=postgres:15 --project-type=drupal11 --docroot web
   ```
4. Start `ddev`:
   ```sh
   ddev start
   ```
5. Install Drush:
   ```sh
   ddev composer require 'drush/drush'
   ```