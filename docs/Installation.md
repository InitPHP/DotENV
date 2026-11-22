# Installation

`initphp/dotenv` is distributed via
[Packagist](https://packagist.org/packages/initphp/dotenv). It has **no
runtime dependencies** and needs **no PHP extensions** beyond the core.

## Requirements

| Requirement | Notes |
| --- | --- |
| **PHP `>= 8.2`** | The code uses `declare(strict_types=1)`, `mixed`, union types and `str_contains()`. |
| Composer | For installation and autoloading. |

> Still on PHP 5.6–7.x? Use the `2.0.x` line (`composer require initphp/dotenv:^2.0`).
> The 3.x series requires PHP 8.0+ and the current development line requires PHP 8.2+. See [Migration from 2.x](Migration-from-2.x.md).

## Install

```bash
composer require initphp/dotenv
```

That's it. Composer registers the PSR-4 namespace `InitPHP\DotENV\` **and**
the global [`env()`](API-Reference.md#the-env-helper) and `env_drift()` helpers (via the package's
`files` autoloader), so both are available as soon as you require
`vendor/autoload.php`.

## Verify the install

Drop this script into your project root as `check-dotenv.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use InitPHP\DotENV\Repository;

// Write a throwaway .env next to this script.
$dir = sys_get_temp_dir() . '/dotenv-check';
@mkdir($dir);
file_put_contents($dir . '/.env', <<<ENV
APP_ENV   = production
APP_DEBUG = false
DB_PORT   = 5432
SITE_URL  = https://example.test
PAGE_URL  = \${SITE_URL}/page
ZIP_CODE  = 007
ENV);

$env = new Repository();
$env->create($dir);

$checks = [
    ['APP_ENV',   'production',                  'string'],
    ['APP_DEBUG', false,                         'boolean'],
    ['DB_PORT',   5432,                          'integer'],
    ['PAGE_URL',  'https://example.test/page',   'string'],
    ['ZIP_CODE',  '007',                         'string'],
];

$ok = true;
foreach ($checks as [$key, $expected, $type]) {
    $actual = $env->get($key);
    $pass   = $actual === $expected;
    $ok     = $ok && $pass;
    printf("[%s] %-10s => %s (%s)\n", $pass ? 'ok ' : 'FAIL', $key, var_export($actual, true), gettype($actual));
}

@unlink($dir . '/.env');
@rmdir($dir);

echo $ok ? "DotENV is working.\n" : "Something is off.\n";
exit($ok ? 0 : 1);
```

Run it:

```bash
php check-dotenv.php
```

Expected output:

```
[ok ] APP_ENV    => 'production' (string)
[ok ] APP_DEBUG  => false (boolean)
[ok ] DB_PORT    => 5432 (integer)
[ok ] PAGE_URL   => 'https://example.test/page' (string)
[ok ] ZIP_CODE   => '007' (string)
DotENV is working.
```

If `ZIP_CODE` comes back as `7` (int) instead of `'007'` (string), you are on
the old 2.x line — see [Migration from 2.x](Migration-from-2.x.md).

## What Composer installs

The package is tiny — a handful of files in `src/`, no runtime dependencies:

```
vendor/
└── initphp/
    └── dotenv/
        ├── src/
        │   ├── DotENV.php          # static facade
        │   ├── Repository.php      # the worker
        │   ├── Lib.php             # deprecated BC alias of Repository
        │   ├── helpers.php         # global env() and env_drift() functions
        │   ├── Drift/
        │   │   └── DriftReport.php
        │   └── Exceptions/
        │       ├── DotENVException.php
        │       └── DriftException.php
        ├── composer.json
        └── LICENSE
```

The dev tooling (`phpunit`, `phpstan`, `php-cs-fixer`) is **`require-dev`
only** — Composer skips it for production installs.

## Composer scripts (for contributors)

If you cloned the repo to contribute, the `composer.json` exposes the quality
gates:

```bash
composer test           # PHPUnit suite
composer stan           # PHPStan (max level)
composer cs-check       # PHP-CS-Fixer, read-only
composer cs-fix         # PHP-CS-Fixer, fixes in place
composer ci             # cs-check + stan + test, in sequence
```

All of these run on CI for every push and pull request across PHP 8.2–8.5.

## Next steps

- [Quick Start](Quick-Start.md) — load your first file and read a value
- [The `.env` File Format](The-env-File-Format.md) — every parsing rule
- [Loading & Precedence](Loading-and-Precedence.md) — how values are resolved
