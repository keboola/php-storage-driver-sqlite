# Keboola SQLite Storage Driver

PHP implementation of the Keboola Storage Driver for SQLite databases.

## Installation

```bash
composer require keboola/storage-driver-sqlite
```

## Requirements

- PHP 8.2 or higher
- PDO SQLite extension
- SQLite3 extension

## Usage

```php
use Keboola\StorageDriver\Sqlite\SqliteDriverClient;

$client = new SqliteDriverClient();
```

## Development

### Running Tests

```bash
composer tests-unit
composer tests-functional
composer tests
```

### Code Quality

```bash
composer phpcs
composer phpstan
composer check
```

## License

MIT
