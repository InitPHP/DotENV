# Quick Start

Five minutes from `composer require` to typed configuration values.

## 1. Write a `.env` file

Put a `.env` in your project root (and add it to `.gitignore` — see
[Security Best Practices](Security-Best-Practices.md)):

```dotenv
# Application
APP_ENV   = production
APP_DEBUG = false

# Database
DB_HOST = 127.0.0.1
DB_PORT = 5432
DB_NAME = app

# Derived value — resolved when you read it
SITE_URL = https://example.test
API_URL  = ${SITE_URL}/api
```

## 2. Load it

```php
<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use InitPHP\DotENV\DotENV;

DotENV::create(__DIR__);          // looks for __DIR__/.env, then __DIR__/.env.php
```

You can also point at the file explicitly:

```php
DotENV::create(__DIR__ . '/.env');
```

## 3. Read values

```php
DotENV::get('APP_ENV');            // "production"  (string)
DotENV::get('APP_DEBUG');          // false         (bool)
DotENV::get('DB_PORT');            // 5432          (int)
DotENV::get('API_URL');            // "https://example.test/api"

DotENV::get('MISSING', 'default'); // "default"     (key not defined)
```

Notice the **types**: `false` is a real boolean, `5432` is a real integer.
That happens because of [value coercion](Value-Types-and-Coercion.md). And
`API_URL` was assembled from `${SITE_URL}` by
[interpolation](Variable-Interpolation.md).

## 4. Or use the global `env()` helper

Composer auto-loads a global `env()` function that shares the same state:

```php
$debug = env('APP_DEBUG', false);
$host  = env('DB_HOST', '127.0.0.1');
```

`env('X')` is exactly `DotENV::get('X')`. Use whichever reads better in your
codebase.

## 5. Prefer an isolated instance? Use `Repository`

The facade keeps a single shared instance. When you want your own — for
dependency injection, or to load several file sets independently — use the
[`Repository`](API-Reference.md#the-repository-class) directly:

```php
use InitPHP\DotENV\Repository;

$env = new Repository();
$env->create(__DIR__);

$port = $env->get('DB_PORT', 5432);
```

The facade and the helper are just thin wrappers around one `Repository`.

## What just happened

1. `create()` read the file and copied each value into `$_ENV`, `$_SERVER`
   and `putenv()` — **unless** the name was already defined in the real
   environment, which always wins. See
   [Loading & Precedence](Loading-and-Precedence.md).
2. `get()` looked the name up (`$_ENV` → `$_SERVER` → `getenv()`), coerced
   the string to its natural type, and expanded any `${VAR}` references.
3. The resolved value was cached, so repeated reads are cheap.

## Things worth knowing immediately

- **Real environment variables are never overwritten.** A value set by your
  OS / container / web server takes precedence over the `.env` file. This is
  intentional — see [Loading & Precedence](Loading-and-Precedence.md).
- **Missing files throw by default.** `DotENV::create('/nope/.env')` throws a
  `DotENVException`. Pass `false` as the second argument to make it a silent
  no-op: `DotENV::create($path, false)`. See [Error Handling](Error-Handling.md).
- **Numbers stay strings when coercion would lose information.** `007` stays
  `"007"`; `5432` becomes `5432`. See
  [Value Types & Coercion](Value-Types-and-Coercion.md).

## Next steps

- [The `.env` File Format](The-env-File-Format.md) — comments, quoting, the
  `export` prefix
- [Value Types & Coercion](Value-Types-and-Coercion.md) — the full typing rules
- [Variable Interpolation](Variable-Interpolation.md) — `${VAR}`, nested and
  multiple references
- [Recipes](Recipes.md) — bootstrapping, defaults, multiple files, framework glue
