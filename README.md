# Dictionary Import Module

Drop-in Drupal module for importing dictionary definitions via Drush command. Imports dictionary definitions from the Free Dictionary API and creates Dictionary Entry content in Drupal, exposing entries via JSON:API for consumption by a frontend.

## Requirements

- Drupal 10.x or 11.x
- PHP 8.1+ (8.3+ recommended for Drupal 11)
- Drush 12+
- Node and JSON:API core modules (declared as dependencies)

**Compatibility:** This module uses standard Drupal APIs (Entity API, services, Drush) and is compatible with both Drupal 10 and Drupal 11. The `core_version_requirement` in `dictionary_import.info.yml` is set to `^10 || ^11`.

The `Dictionary Entry` content type with `field_word` and `field_definitions` fields is created automatically when the module is installed (via `hook_install`) or when `dictionary:setup` is run.

## Installation

### Drop-in install (recommended for reviewers)

1. Clone into your Drupal custom modules directory:

```bash
cd web/modules/custom
git clone [repo-url] dictionary_import
```

2. Enable the module:

```bash
drush en dictionary_import -y
drush cr
```

### Composer path repository (optional)

If you prefer to wire this module into a larger project via Composer, add a path repository to your root `composer.json` (paths may vary depending on your project layout):

```json
"repositories": [
  { "type": "path", "url": "web/modules/custom/dictionary_import", "options": { "symlink": true } }
],
"require": {
  "drupal/dictionary_import": "@dev"
}
```

Then run:

```bash
composer update drupal/dictionary_import
drush en dictionary_import -y
drush cr
```

## Usage

### Setup (if needed)

For existing installations where the content type was not created on module install:

```bash
drush dictionary:setup
# or: drush dict-setup
```

### Import a word

Import a word from the external dictionary API:

```bash
drush dictionary:import hello
# or using the alias
drush dict-import hello
```

On success, a `Dictionary Entry` node is created or updated with the word and definitions. On failure (word not found, API error), an error message is displayed.

## JSON:API Access

Once imported, entries are available via JSON:API:

```bash
curl "https://your-site.com/jsonapi/node/dictionary_entry"
curl 'https://your-site.com/jsonapi/node/dictionary_entry?filter%5Bfield_word%5D=hello'
```

> **Note:** Some shells treat square brackets (`[]`) as glob patterns. Either URL‑encode them (as above) or escape them to avoid `curl: (3) bad range in URL` errors.

## Architecture

- **DictionaryApiClient** (`dictionary_import.api_client`): Fetches definitions from the Free Dictionary API.
- **DictionaryImporter** (`dictionary_import.importer`): Creates/updates `Dictionary Entry` nodes.
- **DictionaryCommands**: Drush integration for `dictionary:setup` and `dictionary:import` (and their aliases).

## Testing

### PHPUnit Kernel Tests

Kernel PHPUnit tests for this module live under:

- `tests/src/Kernel/DictionaryImporterTest.php`

They verify:

- Creating a new `Dictionary Entry` node for a previously unseen word.
- Updating an existing `Dictionary Entry` node when the word already exists.
- Not creating any nodes when the external API reports that a word is not found.

### Setup PHPUnit (if not already configured)

If your Drupal site doesn't already have PHPUnit configured, follow these steps:

1. **Check your Drupal core version:**

```bash
# From your Drupal root
composer show drupal/core-recommended | grep versions
```

2. **Install Drupal's testing dependencies** (required for Drupal's test bootstrap):

```bash
# Remove standalone PHPUnit if installed (Drupal manages its own version)
composer remove --dev phpunit/phpunit 2>/dev/null || true

# Install core-dev matching your Drupal version
# For Drupal 10.x:
composer require --dev "drupal/core-dev:^10" --with-all-dependencies

# For Drupal 11.x:
composer require --dev "drupal/core-dev:^11" --with-all-dependencies
```

**Important notes:**

- Do not install PHPUnit separately! `drupal/core-dev` includes the correct PHPUnit version for your Drupal version (9.x for Drupal 10, 10.x/11.x for Drupal 11).
- The `--with-all-dependencies` flag allows Composer to downgrade packages if needed to match Drupal's requirements.
- If you get version conflicts, ensure your site's Composer dependencies are properly managed. Drupal 10 requires Symfony 6, Drupal 11 requires Symfony 7.

2. **Create `phpunit.xml` in your Drupal root** (if it doesn't exist):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="web/core/tests/bootstrap.php"
         colors="true">
  <php>
    <ini name="error_reporting" value="32767"/>
    <ini name="memory_limit" value="-1"/>
    <!-- Replace with your local site URL -->
    <env name="SIMPLETEST_BASE_URL" value="http://localhost"/>
    <!-- Replace with your database connection string -->
    <env name="SIMPLETEST_DB" value="mysql://user:pass@localhost/dbname"/>
  </php>
  <testsuites>
    <testsuite name="kernel">
      <directory>web/modules/custom/*/tests/src/Kernel</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

3. **Update the environment variables** in `phpunit.xml`:
   - `SIMPLETEST_BASE_URL`: Your local Drupal site URL
   - `SIMPLETEST_DB`: Your database connection string (format: `mysql://user:pass@host/dbname`)

### Running the Tests

From your Drupal project root (where `phpunit.xml` is located):

```bash
vendor/bin/phpunit web/modules/custom/dictionary_import/tests/src/Kernel/DictionaryImporterTest.php
```

**Note:** If your module is installed in a different path (e.g., `modules/contrib/dictionary_import`), adjust the path accordingly.

### Manual Verification

Import a word via Drush, then confirm the node exists and is exposed via JSON:API:

```bash
drush dictionary:import hello
curl 'https://your-site.com/jsonapi/node/dictionary_entry?filter%5Bfield_word%5D=hello'
```
