# InitPHP DotENV — Documentation

Welcome to the official documentation for **`initphp/dotenv`** — a small,
dependency-free environment-variable loader for **PHP 8.2+**.

It reads a `.env` (or `.env.php`) file, pushes the values into PHP's
environment (`$_ENV`, `$_SERVER`, `getenv()`), and reads them back with
**type coercion** and **`${VAR}` interpolation** — without ever overwriting a
real environment variable.

```bash
composer require initphp/dotenv
```

```php
use InitPHP\DotENV\DotENV;

DotENV::create(__DIR__);          // loads __DIR__/.env (or .env.php)

DotENV::get('APP_ENV', 'local');  // → string|int|float|bool|null
env('APP_ENV', 'local');          // global helper, same shared state
```

Given this `.env`:

```dotenv
APP_ENV   = production
APP_DEBUG = false
DB_PORT   = 5432
SITE_URL  = https://example.test
PAGE_URL  = ${SITE_URL}/page
ZIP_CODE  = 007
```

you get correctly typed values back:

```php
DotENV::get('APP_ENV');   // "production"  (string)
DotENV::get('APP_DEBUG'); // false         (bool)
DotENV::get('DB_PORT');   // 5432          (int)
DotENV::get('PAGE_URL');  // "https://example.test/page"  (interpolated)
DotENV::get('ZIP_CODE');  // "007"         (string — leading zero preserved)
```

## The package in one table

| Symbol | Role |
| --- | --- |
| [`DotENV`](API-Reference.md#the-dotenv-facade) | Static facade over a shared repository — the usual entry point. |
| [`Repository`](API-Reference.md#the-repository-class) | The worker doing the loading/parsing/reading; use directly for an isolated instance. |
| [`env()`](API-Reference.md#the-env-helper) | Global helper function, equivalent to `DotENV::get()`. |
| [`env_drift()`](API-Reference.md#the-env_drift-helper) | Global helper, equivalent to `DotENV::drift()`. |
| [`DriftReport`](API-Reference.md#driftreport) | Result of an [environment drift](Environment-Drift.md) check. |
| [`DotENVException`](Error-Handling.md) | Base exception type, extends `\InvalidArgumentException`; [`DriftException`](Error-Handling.md#driftexception) extends it. |

## Start here

- **New to the package?** Read [Installation](Installation.md), then
  [Quick Start](Quick-Start.md).
- **Writing a `.env` file?** [The `.env` File Format](The-env-File-Format.md)
  spells out every parsing rule.
- **Wondering why `007` stays a string?**
  [Value Types & Coercion](Value-Types-and-Coercion.md).
- **Chaining values with `${VAR}`?**
  [Variable Interpolation](Variable-Interpolation.md).
- **Want CI to catch a key that was never provisioned?**
  [Environment Drift](Environment-Drift.md).
- **Coming from 2.x?** Read [Migration from 2.x](Migration-from-2.x.md) — 3.0
  raises the PHP floor and changes value coercion.
- **Looking for a specific method?** The full [API Reference](API-Reference.md)
  lists every public member.

## What you get

- **Two file formats.** Plain-text [`.env`](The-env-File-Format.md) or a
  [`.env.php`](Using-a-PHP-env-File.md) that returns a native PHP array.
- **Smart, loss-free typing.** `true`/`false`/`null`/`empty` become real PHP
  values; numbers become `int`/`float` **only when it round-trips**, so
  `007`, `+90555…`, oversized integers and `1e3` stay strings. See
  [Value Types & Coercion](Value-Types-and-Coercion.md).
- **`${VAR}` interpolation.** References are resolved on read, support
  multiple and nested references on one line, and never recurse forever on a
  cycle. See [Variable Interpolation](Variable-Interpolation.md).
- **Real env vars always win.** `create()` never overwrites a name already
  defined in `$_ENV`, `$_SERVER`, or `getenv()`. See
  [Loading & Precedence](Loading-and-Precedence.md).
- **One exception family.** Every failure raises `DotENVException` (or its subclass `DriftException`); one `catch`
  covers everything, and `$debug = false` turns load failures into silent no-ops.
  See [Error Handling](Error-Handling.md).
- **Drift detection.** Compare the loaded environment against a `.env.example`
  or a required-keys list and get a report of missing, extra and empty keys.
  See [Environment Drift](Environment-Drift.md).
- **Test-friendly.** `flush()` / `reset()` unload exactly what was loaded,
  leaving the real environment untouched. See [Testing](Testing.md).

## At a glance — behaviour

| Input in the file | `get()` returns |
| --- | --- |
| `KEY=hello` | `"hello"` (string) |
| `KEY=true` / `KEY=false` | `true` / `false` (bool) |
| `KEY=null` | `null` |
| `KEY=empty` or `KEY=` | `""` (string) |
| `KEY=42` / `KEY=-3.5` | `42` (int) / `-3.5` (float) |
| `KEY=007` / `KEY=+15551234` | `"007"` / `"+15551234"` (string) |
| `KEY="  spaced  "` | `"  spaced  "` (quotes stripped, content verbatim) |
| `KEY=#ffffff` | `"#ffffff"` (leading `#` kept) |
| `KEY=val # note` | `"val"` (inline comment stripped) |
| `KEY=${OTHER}/x` | `OTHER`'s value + `/x` (interpolated) |

## Package metadata

- **License**: MIT
- **Minimum PHP**: 8.2
- **Required extensions**: none beyond the PHP core
- **Runtime dependencies**: none
- **Packagist**: [`initphp/dotenv`](https://packagist.org/packages/initphp/dotenv)
- **Source**: [github.com/InitPHP/DotENV](https://github.com/InitPHP/DotENV)
- **Issues**: [github.com/InitPHP/DotENV/issues](https://github.com/InitPHP/DotENV/issues)
- **Discussions**: [github.com/orgs/InitPHP/discussions](https://github.com/orgs/InitPHP/discussions)

If something in this documentation is unclear, wrong, or out of date,
[open an issue](https://github.com/InitPHP/DotENV/issues/new) — documentation
fixes are merged eagerly.
