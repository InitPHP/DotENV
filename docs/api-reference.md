# API reference

The package exposes one worker class (`Repository`), a static facade over a
shared instance of it (`DotENV`), and one global helper (`env()`).

## `Repository`

`InitPHP\DotENV\Repository`

### `create()`

```php
public function create(string $path, bool $debug = true): void
```

Reads and defines a `.env` or `.env.php` file.

- **`$path`** — a path to a `.env`/`.env.php` file, or to a directory that
  contains one. When a directory is given, `.env` is tried first, then
  `.env.php`.
- **`$debug`** — when `true` (default), problems throw a
  [`DotENVException`](exceptions.md); when `false`, problems are ignored and
  the method returns without defining anything.

Values already present in `$_ENV` or `$_SERVER` are not overwritten.

### `get()`

```php
public function get(string $name, mixed $default = null): mixed
```

Returns an environment value, looked up as `$_ENV` → `$_SERVER` → `getenv()`.
Strings are coerced ([value types](value-types.md)) and `${VAR}` references
are interpolated ([interpolation](variable-interpolation.md)). Returns
`$default` when the name is not defined anywhere.

### `env()`

```php
public function env(string $name, mixed $default = null): mixed
```

Alias of `get()`.

### `flush()`

```php
public function flush(): void
```

Removes every value this repository defined (from `$_ENV`, `$_SERVER` and
`putenv()`) and clears the read cache. Pre-existing environment variables are
left untouched.

### `drift()`

```php
public function drift(string|array $reference, array $options = []): DriftReport
```

Compares the loaded environment against a `$reference` (a `.env.example` file
path, or a required-keys array) and returns an
`InitPHP\DotENV\Drift\DriftReport` listing **missing**, optional **extra** and
optional **empty** keys. Read-only — it never defines or mutates a value.
`$options` accepts `['extra' => bool, 'empty' => bool]` (both default `false`).
Throws a [`DotENVException`](exceptions.md) only when a reference *file path*
cannot be located or read. See [environment drift](env-drift.md) for the full
treatment.

### `assertNoDrift()`

```php
public function assertNoDrift(string|array $reference, array $options = []): void
```

Strict counterpart of `drift()`: throws a
[`DriftException`](exceptions.md) (carrying the `DriftReport`) the moment any
drift is found, and returns silently when the environment is clean. For CI
gates and bootstrap guards.

## `DotENV` (facade)

`InitPHP\DotENV\DotENV`

A static facade that forwards `create()`, `get()`, `env()`, `flush()`,
`drift()` and `assertNoDrift()` to a single shared `Repository`. It adds two
lifecycle helpers:

### `instance()`

```php
public static function instance(): Repository
```

Returns the shared repository, creating it on first use.

### `reset()`

```php
public static function reset(): void
```

Flushes the shared repository and discards it, so the next call builds a fresh
one. Useful in tests and long-running workers.

```php
use InitPHP\DotENV\DotENV;

DotENV::create(__DIR__);
DotENV::get('APP_ENV');
DotENV::flush();   // unload, keep the instance
DotENV::reset();   // unload and drop the instance
```

## `env()` global helper

Registered through Composer's `files` autoloader:

```php
function env(string $name, mixed $default = null): mixed
```

Equivalent to `DotENV::get($name, $default)` and shares the same shared
repository. It is only defined if no other `env()` function already exists.

```php
$appEnv = env('APP_ENV', 'production');
```

## `env_drift()` global helper

Also registered through Composer's `files` autoloader:

```php
function env_drift(string|array $reference, array $options = []): DriftReport
```

Equivalent to `DotENV::drift($reference, $options)` over the same shared
repository. Only defined if no other `env_drift()` function already exists. See
[environment drift](env-drift.md).

```php
$report = env_drift(__DIR__ . '/.env.example');
```

## Backwards compatibility: `Lib`

Before 3.0 the worker class was named `InitPHP\DotENV\Lib`. That name still
resolves — it is registered as an alias of `Repository` — but it is
deprecated. Use `Repository` in new code.
