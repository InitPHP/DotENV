# Getting started

## Install

```bash
composer require initphp/dotenv
```

The package needs PHP 8.0 or newer and no extensions beyond the PHP core.

## The two entry points

### 1. The `DotENV` static facade

The quickest way in. It keeps a single shared repository behind the scenes,
so every call sees the same loaded values.

```php
use InitPHP\DotENV\DotENV;

DotENV::create('/path/to/project/.env');

$debug = DotENV::get('APP_DEBUG', false);
```

### 2. The `Repository` object

Use this when you want an isolated instance — for dependency injection, for
loading more than one file set side by side, or for tests.

```php
use InitPHP\DotENV\Repository;

$env = new Repository();
$env->create('/path/to/project/.env');

$debug = $env->get('APP_DEBUG', false);
```

The facade is simply a thin static wrapper around one shared `Repository`.

## Loading a file

`create()` accepts either a file path or a directory path:

```php
DotENV::create('/path/to/project/.env');      // explicit file
DotENV::create('/path/to/project/.env.php');  // explicit PHP file
DotENV::create('/path/to/project');           // directory: finds .env, then .env.php
```

When you pass a directory, the repository looks for a `.env` file first and a
`.env.php` file second.

## Reading values

```php
DotENV::get('NAME');             // value, or null if undefined
DotENV::get('NAME', 'fallback'); // value, or 'fallback' if undefined
DotENV::env('NAME');             // alias of get()
env('NAME', 'fallback');         // global helper, registered via Composer
```

Resolution order is `$_ENV` → `$_SERVER` → `getenv()`. The first store that
defines the name wins. See [Value types](value-types.md) for how the raw
string becomes a `bool`, `int`, `float`, `null` or `string`.

## Immutability

Values already present in `$_ENV` or `$_SERVER` are **never** overwritten by
`create()`. This lets real environment variables (set by the OS, the web
server, or a container) take precedence over a committed `.env` file.

## Reloading (tests and workers)

`create()` is additive and immutable, so to start over — typically in a test
or a long-running worker — drop what was loaded first:

```php
DotENV::flush();   // removes only what this repository defined
DotENV::reset();   // flush() + discard the shared instance
```

Pre-existing environment variables are left untouched by both.
