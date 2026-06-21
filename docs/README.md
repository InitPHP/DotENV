# InitPHP DotENV — Documentation

InitPHP DotENV loads environment variables from a `.env` or `.env.php` file
into PHP's environment (`$_ENV`, `$_SERVER` and `getenv()`), with type
coercion and `${VAR}` interpolation on read.

## Contents

| Guide | What it covers |
| ----- | -------------- |
| [Getting started](getting-started.md) | Install, the two entry points, your first load. |
| [The `.env` file format](env-file-format.md) | Comments, quoting, inline comments, the `export` prefix. |
| [Using a `.env.php` file](php-env-file.md) | Returning a native PHP array instead of plain text. |
| [Variable interpolation](variable-interpolation.md) | `${VAR}` references, nesting, and circular-reference handling. |
| [Value types](value-types.md) | How `true`/`false`/`null`/`empty`, ints and floats are coerced. |
| [Environment drift](env-drift.md) | Compare the loaded env against a `.env.example` or required-keys list. |
| [API reference](api-reference.md) | Every public method and the `env()` helper. |
| [Exceptions](exceptions.md) | What is thrown, when, and how to catch it. |
| [Security notes](security.md) | Keeping `.env` files out of reach and the `.env.php` caveat. |

## At a glance

```php
require 'vendor/autoload.php';

use InitPHP\DotENV\DotENV;

DotENV::create(__DIR__);          // loads __DIR__/.env (or .env.php)

DotENV::get('APP_ENV', 'local');  // string|int|float|bool|null
env('APP_ENV', 'local');          // the global helper, same thing
```

## Requirements

- PHP 8.0 or newer
- No required extensions beyond the PHP core
