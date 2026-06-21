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
| `DotENV::drift(string\|array $reference, array $options = [])` | `DriftReport` | Compare the loaded env against a reference and report drift. |
| `DotENV::assertNoDrift(string\|array $reference, array $options = [])` | `void` | Strict mode: throw `DriftException` when drift is found. |
| `env(string $name, mixed $default = null)` | `mixed` | Global helper for `DotENV::get()`. |
| `env_drift(string\|array $reference, array $options = [])` | `DriftReport` | Global helper for `DotENV::drift()`. |

See the [API reference](docs/api-reference.md) for details.

## Env drift

Once an env file is loaded you can check it for **drift** against a reference —
the keys that *should* be present. It catches the failure that bites in
CI/production: a key your code expects that was never provisioned.

The reference can be a **`.env.example` path** (the usual convention) or a
**required-keys array**:

```php
use InitPHP\DotENV\DotENV;

DotENV::create('/home/www/.env');

// Reference = a checked-in example file.
$report = DotENV::drift('/home/www/.env.example');

// Reference = an explicit required-keys list.
$report = DotENV::drift(['DB_HOST', 'DB_PORT', 'APP_KEY']);

if ($report->hasDrift()) {
    echo $report;                 // human-readable summary for the log
    $report->getMissing();        // ['APP_KEY']
    $report->toArray();           // ['missing' => [...], 'extra' => [...], 'empty' => [...]]
}
```

Drift is grouped into three buckets:

| Bucket | Meaning | Default |
| ------ | ------- | ------- |
| **missing** | a reference key absent from the actual environment | always reported |
| **extra** | a key this loader defined that is **not** in the reference | opt-in (`['extra' => true]`) |
| **empty** | a reference key that is present but blank | opt-in (`['empty' => true]`) |

`extra` and `empty` are off by default: extra keys are usually noise, and only
keys *this loader defined* are ever considered for `extra` (unrelated OS
variables are never reported).

```php
// Also flag undocumented and blank-but-required keys.
$report = DotENV::drift('/home/www/.env.example', ['extra' => true, 'empty' => true]);
```

### Strict mode (CI gate)

`assertNoDrift()` runs the same comparison and throws a
[`DriftException`](docs/exceptions.md) the moment any drift is found — drop it
into a bootstrap or a CI check to fail fast:

```php
use InitPHP\DotENV\Exceptions\DriftException;

try {
    DotENV::assertNoDrift('/home/www/.env.example');
} catch (DriftException $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    fwrite(STDERR, implode(', ', $e->getReport()->getMissing()) . PHP_EOL);
    exit(1);
}
```

`DriftException` extends `DotENVException` (and `InvalidArgumentException`), so
existing catch blocks keep working, and the offending `DriftReport` is attached
via `getReport()`.

Drift checking is **read-only**: it never defines, reads-through, or mutates any
environment value, so it is safe to run after `create()` without changing what
is loaded. See [`docs/env-drift.md`](docs/env-drift.md) for the full reference.

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
