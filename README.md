# InitPHP DotENV

Loads environment variables from a `.env` or `.env.php` file into PHP's
environment (`$_ENV`, `$_SERVER`, `getenv()`), with type coercion and
`${VAR}` interpolation.

[![CI](https://github.com/InitPHP/DotENV/actions/workflows/ci.yml/badge.svg)](https://github.com/InitPHP/DotENV/actions/workflows/ci.yml)
[![Latest Stable Version](http://poser.pugx.org/initphp/dotenv/v)](https://packagist.org/packages/initphp/dotenv) [![Total Downloads](http://poser.pugx.org/initphp/dotenv/downloads)](https://packagist.org/packages/initphp/dotenv) [![License](http://poser.pugx.org/initphp/dotenv/license)](https://packagist.org/packages/initphp/dotenv) [![PHP Version Require](http://poser.pugx.org/initphp/dotenv/require/php)](https://packagist.org/packages/initphp/dotenv)

## Requirements

- PHP 8.0 or higher
- No extensions beyond the PHP core

## Installation

```bash
composer require initphp/dotenv
```

## Quick start

`/home/www/.env`:

```dotenv
# Comment line
SITE_URL = http://lvh.me
PAGE_URL = ${SITE_URL}/page

; Another comment
TRUE_VALUE  = true
FALSE_VALUE = false
NULL_VALUE  = null
EMPTY_VALUE = empty

NUMERIC_VALUE = 13
PI_NUMBER     = 3.14
ZIP_CODE      = 007
```

```php
require 'vendor/autoload.php';

use InitPHP\DotENV\DotENV;

DotENV::create('/home/www/.env');     // or DotENV::create('/home/www');

DotENV::get('SITE_URL');      // "http://lvh.me"
DotENV::get('PAGE_URL');      // "http://lvh.me/page"
DotENV::get('TRUE_VALUE');    // true       (bool)
DotENV::get('FALSE_VALUE');   // false      (bool)
DotENV::get('NULL_VALUE');    // null
DotENV::get('EMPTY_VALUE');   // ""         (string)
DotENV::get('NUMERIC_VALUE'); // 13         (int)
DotENV::get('PI_NUMBER');     // 3.14       (float)
DotENV::get('ZIP_CODE');      // "007"      (string — leading zero preserved)

DotENV::get('NOT_FOUND', 'hi'); // "hi"

env('SITE_URL');              // global helper, same shared state
```

Prefer an isolated instance (e.g. for DI or tests)? Use the `Repository`
directly:

```php
use InitPHP\DotENV\Repository;

$env = new Repository();
$env->create('/home/www/.env');
$env->get('SITE_URL');
```

## File format in brief

- `KEY = VALUE` — split on the first `=`; whitespace around the key is trimmed.
- Comments start a line with anything other than a letter/digit/`_`/`-`
  (`#`, `;`, `//`); inline comments start at a `#` preceded by whitespace.
- Quote a value (`"..."` or `'...'`) to keep it verbatim, including a leading
  `#` such as `#ffffff`.
- `${OTHER}` references are expanded when the value is read.
- `true` / `false` / `null` / `empty` and round-tripping numbers are coerced;
  everything else stays a string.

Full details: [`docs/`](docs/README.md).

## API summary

| Call | Returns | Purpose |
| ---- | ------- | ------- |
| `DotENV::create(string $path, bool $debug = true)` | `void` | Load a `.env`/`.env.php` file or a directory containing one. |
| `DotENV::get(string $name, mixed $default = null)` | `mixed` | Read a value (`$_ENV` → `$_SERVER` → `getenv()`). |
| `DotENV::env(string $name, mixed $default = null)` | `mixed` | Alias of `get()`. |
| `DotENV::flush()` | `void` | Unload everything this instance defined. |
| `DotENV::reset()` | `void` | `flush()` and drop the shared instance. |
| `env(string $name, mixed $default = null)` | `mixed` | Global helper for `DotENV::get()`. |

See the [API reference](docs/api-reference.md) for details.

## Notes

- **Immutability:** values already in `$_ENV` or `$_SERVER` are never
  overwritten, so real environment variables win over a committed `.env`.
- **`$debug`:** when `false`, `create()` swallows every error (missing file,
  wrong type, unreadable) instead of throwing a
  [`DotENVException`](docs/exceptions.md).
- **Security:** keep `.env` files out of the web root, and remember a
  `.env.php` file is executed as code — see the
  [security notes](docs/security.md).

## Upgrading from 2.x

3.0 is a maintenance-focused major release. The public API (`DotENV::create`,
`get`, `env` and the `env()` helper) is unchanged, but note:

- **PHP 8.0+** is now required (was 5.6+).
- **Numbers are only coerced when it is loss-free.** `007` and `+90555…` now
  stay strings instead of becoming `7` and a float.
- Real bug fixes change previously broken results: directory-path loading,
  multiple `${VAR}` references on one line, quoted values with spaces around
  `=`, and values like `#ffffff` all work now.
- The internal `Lib` class is renamed `Repository`; `Lib` remains as a
  deprecated alias.

See the [changelog](CHANGELOG.md) for the full list.

## Contributing

Bug reports and pull requests are welcome. The CI runs PHP-CS-Fixer, PHPStan
(max level) and PHPUnit across PHP 8.0–8.4; run the same bundle locally with:

```bash
composer ci
```

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 InitPHP — released under the [MIT License](./LICENSE).
