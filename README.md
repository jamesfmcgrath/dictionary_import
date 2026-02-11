# Dictionary Import

Imports dictionary definitions from an external API (Free Dictionary API) and creates Dictionary Entry content in Drupal. Exposes entries via JSON:API for consumption by the frontend.

## Prerequisites

- Drupal 10.x
- Node and JSON:API modules (declared as dependencies)
- Composer (module must be required via path repository in root project)

The Dictionary Entry content type with `field_word` and `field_definitions` fields is created automatically when the module is installed (via `hook_install`) or when `dictionary:setup` is run.

## Installation

The module is installed via Composer path repository. Ensure the root project `composer.json` includes:

```json
"repositories": [
  {"type": "path", "url": "web/modules/custom/dictionary_import", "options": {"symlink": true}}
],
"require": {
  "drupal/dictionary_import": "@dev"
}
```

Then:

```bash
ddev composer update drupal/dictionary_import
ddev drush en dictionary_import -y
ddev drush cr
```

## Usage

### Setup (if needed)

For existing installations where the content type was not created on module install:

```bash
ddev drush dictionary:setup
# or: ddev drush dict-setup
```

### Drush command

Import a word from the external dictionary API:

```bash
ddev drush dictionary:import hello
# or using the alias
ddev drush dict-import hello
```

On success, a Dictionary Entry node is created or updated with the word and definitions. On failure (word not found, API error), an error message is displayed.

### JSON:API

Once imported, entries are available via JSON:API:

```
GET /jsonapi/node/dictionary_entry
GET /jsonapi/node/dictionary_entry?filter[field_word]=hello
```

## Services

- `dictionary_import.api_client` — Fetches definitions from the external API
- `dictionary_import.importer` — Creates/updates Dictionary Entry nodes

## Testing

Kernel PHPUnit tests for this module live under:

- `tests/src/Kernel/DictionaryImporterTest.php`

They verify:

- Creating a new `Dictionary Entry` node for a previously unseen word.
- Updating an existing `Dictionary Entry` node when the word already exists.
- Not creating any nodes when the external API reports that a word is not found.

From the Drupal project root (where `phpunit.xml` is located), run:

```bash
ddev exec ./vendor/bin/phpunit web/modules/custom/dictionary_import/tests/src/Kernel/DictionaryImporterTest.php
```

Manual verification: import a word, then confirm the node exists and is exposed via JSON:API.
