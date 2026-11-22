# Recipes

Practical, copy-pasteable patterns. Each one is built only from the public
API — see the [API Reference](API-Reference.md).

## Bootstrap configuration at app startup

Load the environment once, as early as possible (front controller, console
kernel, test bootstrap):

```php
<?php
declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use InitPHP\DotENV\DotENV;

DotENV::create(dirname(__DIR__));   // project root holds the .env

date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));
error_reporting(env('APP_DEBUG', false) ? E_ALL : 0);
```

## A typed config array

Resolve everything once into a plain array your app can pass around:

```php
$config = [
    'env'   => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'db'    => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', 5432),
        'name' => env('DB_NAME', 'app'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
    ],
    'url'   => env('SITE_URL', 'http://localhost'),
];
```

Because `DB_PORT` coerces to an `int` and `APP_DEBUG` to a `bool`, the array
already has the right types.

## Layered files: base + local override

Commit a base `.env`, let developers drop a `.env.local` that wins where it
defines something. Load the override **first** (immutability makes earlier
definitions win), in best-effort mode so it's optional:

```php
DotENV::create(__DIR__ . '/.env.local', false); // optional, wins where present
DotENV::create(__DIR__ . '/.env');              // required base
```

See [Loading & Precedence](Loading-and-Precedence.md#loading-more-than-once).

## Per-environment files

Pick a file based on an already-known environment variable:

```php
$appEnv = getenv('APP_ENV') ?: 'production';

DotENV::create(__DIR__ . "/.env.{$appEnv}", false); // .env.production / .env.staging…
DotENV::create(__DIR__ . '/.env', false);           // shared defaults
```

> Note: a file must be named exactly `.env` or `.env.php`. `.env.production`
> is **not** accepted as a file path — keep per-environment files in separate
> directories and point `create()` at the directory, or rename on deploy.
> A reliable directory-based variant:
>
> ```php
> DotENV::create(__DIR__ . "/config/{$appEnv}"); // loads config/<env>/.env
> ```

## Build URLs and DSNs with interpolation

Let the file assemble derived values for you:

```dotenv
DB_HOST = 127.0.0.1
DB_PORT = 5432
DB_NAME = app
DB_USER = app
DB_PASS = secret

DATABASE_URL = postgres://${DB_USER}:${DB_PASS}@${DB_HOST}:${DB_PORT}/${DB_NAME}
```

```php
DotENV::get('DATABASE_URL');
// "postgres://app:secret@127.0.0.1:5432/app"
```

See [Variable Interpolation](Variable-Interpolation.md).

## Require keys to be present

Fail fast at boot if mandatory configuration is missing:

```php
use InitPHP\DotENV\DotENV;

function requireEnv(string ...$names): void
{
    $missing = array_filter($names, static fn ($n) => DotENV::get($n) === null);
    if ($missing !== []) {
        throw new RuntimeException('Missing required env: ' . implode(', ', $missing));
    }
}

DotENV::create(__DIR__);
requireEnv('APP_KEY', 'DB_HOST', 'DB_NAME');
```

## Computed config with `.env.php`

When a value must be derived at load time, a
[`.env.php`](Using-a-PHP-env-File.md) is cleaner than a shell-quoting dance:

```php
<?php // .env.php
return [
    'APP_ENV'    => getenv('CI') ? 'testing' : 'local',
    'BUILD_SHA'  => trim((string) @shell_exec('git rev-parse --short HEAD')) ?: 'unknown',
    'CACHE_PATH' => sys_get_temp_dir() . '/app-cache',
    'DEBUG'      => true,   // a real bool
];
```

## Dependency injection instead of the facade

Prefer an injected object over global state? Build a `Repository` and pass it
around:

```php
use InitPHP\DotENV\Repository;

$env = new Repository();
$env->create(__DIR__);

$container->set(Repository::class, $env);

// elsewhere:
final class Mailer
{
    public function __construct(private Repository $env) {}

    public function dsn(): string
    {
        return $this->env->get('MAILER_DSN', 'smtp://localhost:1025');
    }
}
```

## Reload configuration in a long-running worker

In a queue worker or a reactor loop you may want to pick up a changed file
between jobs. `reset()` drops the shared instance (and unloads what it added,
leaving the real environment intact):

```php
while ($job = $queue->pop()) {
    DotENV::reset();        // forget the previous load
    DotENV::create(__DIR__);
    handle($job);
}
```

See [Testing](Testing.md) for the same pattern in a test suite.

## Next steps

- [Security Best Practices](Security-Best-Practices.md) — keep secrets safe
- [Testing](Testing.md) — isolate tests that touch the environment
- [FAQ](FAQ.md) — short answers to common questions
