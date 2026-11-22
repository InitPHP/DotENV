# API Reference

The package exposes one worker class (`Repository`), a static facade over a
shared instance of it (`DotENV`), two global helpers (`env()` and
`env_drift()`), a drift report value object (`DriftReport`), and an exception
type (`DotENVException`) with one subclass (`DriftException`).

All classes live in the `InitPHP\DotENV` namespace (the report in
`InitPHP\DotENV\Drift`, the exceptions in `InitPHP\DotENV\Exceptions`).

---

## The `Repository` class

`InitPHP\DotENV\Repository` — the concrete worker. Use it directly for an
isolated instance; the facade wraps a single shared one.

```php
use InitPHP\DotENV\Repository;

$env = new Repository();
$env->create('/path/to/project');
$debug = $env->get('APP_DEBUG', false);
```

### `create()`

```php
public function create(string $path, bool $debug = true): void
```

Reads and defines a `.env` or `.env.php` file.

- **`$path`** — a path to a `.env`/`.env.php` file, or to a directory that
  contains one. For a directory, `.env` is tried first, then `.env.php`.
- **`$debug`** — when `true` (default), problems throw a
  [`DotENVException`](#dotenvexception); when `false`, problems are ignored and
  the method returns without defining anything.

Values already present in `$_ENV`, `$_SERVER`, or `getenv()` are not
overwritten. See [Loading & Precedence](Loading-and-Precedence.md).

### `get()`

```php
public function get(string $name, mixed $default = null): mixed
```

Returns an environment value, resolved `$_ENV` → `$_SERVER` → `getenv()`.
String values are [coerced](Value-Types-and-Coercion.md) and `${VAR}` references
are [interpolated](Variable-Interpolation.md). Returns `$default` when the name is
not defined anywhere.

The resolved value is cached on first read; later external changes to the same
name are not seen until [`flush()`](#flush). An undefined name is not cached.

```php
$env->get('APP_ENV');            // value or null
$env->get('APP_ENV', 'local');   // value or 'local'
```

### `env()`

```php
public function env(string $name, mixed $default = null): mixed
```

Alias of [`get()`](#get).

### `flush()`

```php
public function flush(): void
```

Removes every value this repository defined — from `$_ENV`, `$_SERVER` and
`getenv()` — and clears the read cache. **Pre-existing environment variables
are left untouched** (only what this instance loaded is removed).

```php
$env->create('/app');
$env->flush();          // unload; the real environment is unchanged
```

### `drift()`

```php
public function drift(string|array $reference, array $options = []): DriftReport
```

Compares the loaded environment against a reference and returns a
[`DriftReport`](#driftreport). Read-only: nothing is defined or changed.

- **`$reference`** — a path to a reference file (typically `.env.example`; a
  directory resolves to the `.env`/`.env.php` inside it), a list of required key
  names, or an associative array whose keys are the required names.
- **`$options`** — `['extra' => true]` also reports loaded keys that are not in
  the reference; `['empty' => true]` also reports required keys that resolve to
  a blank value. Both are off by default.

Throws [`DotENVException`](#dotenvexception) only when a reference *file path*
cannot be found, read or parsed. See [Environment Drift](Environment-Drift.md).

### `assertNoDrift()`

```php
public function assertNoDrift(string|array $reference, array $options = []): void
```

Same comparison as `drift()`, but throws a
[`DriftException`](#driftexception) when any drift is found and returns
silently otherwise.

---

## The `DotENV` facade

`InitPHP\DotENV\DotENV` — a static facade that forwards `create()`, `get()`,
`env()`, `flush()`, `drift()` and `assertNoDrift()` to a single shared
`Repository`, plus two lifecycle helpers.

```php
use InitPHP\DotENV\DotENV;

DotENV::create(__DIR__);
DotENV::get('APP_ENV');
DotENV::env('APP_ENV');
DotENV::flush();
```

The forwarded calls (`create`, `get`, `env`, `flush`, `drift`, `assertNoDrift`)
have the same signatures as on `Repository`.

### `instance()`

```php
public static function instance(): Repository
```

Returns the shared repository, creating it on first use. Handy if you need the
object itself (for DI, or to pass it somewhere).

```php
$repo = DotENV::instance(); // the shared Repository
```

### `reset()`

```php
public static function reset(): void
```

Flushes the shared repository **and** discards it, so the next facade call
builds a fresh one. Useful in tests and long-running workers. Pre-existing
environment variables are left untouched.

```php
DotENV::create(__DIR__);
DotENV::flush();   // unload, keep the instance
DotENV::reset();   // unload and drop the instance
```

---

## The `env()` helper

A global function registered through Composer's `files` autoloader:

```php
function env(string $name, mixed $default = null): mixed
```

Equivalent to `DotENV::get($name, $default)`, sharing the same shared
repository. It is only defined if no other `env()` function already exists, so
it won't clash with a framework that ships its own.

```php
$appEnv = env('APP_ENV', 'production');
```

## The `env_drift()` helper

```php
function env_drift(string|array $reference, array $options = []): DriftReport
```

Equivalent to `DotENV::drift($reference, $options)`. Like `env()`, it is only
defined if no function of that name exists yet.

```php
$report = env_drift('/home/www/.env.example', ['extra' => true]);
```

---

## `DriftReport`

`InitPHP\DotENV\Drift\DriftReport` — the result of a drift check, with three
buckets: **missing**, **extra** and **empty**.

| Method | Returns |
| --- | --- |
| `getMissing()` | `list<string>` — reference keys absent from the environment |
| `getExtra()` | `list<string>` — loaded keys not in the reference (opt-in) |
| `getEmpty()` | `list<string>` — required keys with a blank value (opt-in) |
| `hasDrift()` | `bool` — any drift at all |
| `isEmpty()` | `bool` — inverse of `hasDrift()` |
| `count()` | `int` — total drifted keys across every bucket |
| `toArray()` | `array` with the keys `missing`, `extra`, `empty` |
| `(string) $report` | human-readable summary |

---

## `DotENVException`

`InitPHP\DotENV\Exceptions\DotENVException` — the base exception type. It
extends `\InvalidArgumentException`:

```
\InvalidArgumentException
 └── InitPHP\DotENV\Exceptions\DotENVException
      └── InitPHP\DotENV\Exceptions\DriftException
```

So both of these catch it:

```php
use InitPHP\DotENV\Exceptions\DotENVException;

try {
    DotENV::create('/app/.env');
} catch (DotENVException $e) {           // specific
    // ...
} catch (\InvalidArgumentException $e) { // or the parent
    // ...
}
```

See [Error Handling](Error-Handling.md) for when it is thrown.

## `DriftException`

`InitPHP\DotENV\Exceptions\DriftException` extends `DotENVException`. Thrown by
[`assertNoDrift()`](#assertnodrift); `getReport()` returns the `DriftReport`
that triggered it. See [Error Handling](Error-Handling.md#driftexception).

---

## Backwards compatibility: `Lib`

Before 3.0 the worker class was named `InitPHP\DotENV\Lib`. That name still
resolves — it is registered as an alias of `Repository` — so existing code
keeps working:

```php
$env = new \InitPHP\DotENV\Lib(); // works; instanceof Repository
```

It is **deprecated**. Use `Repository` in new code. See
[Migration from 2.x](Migration-from-2.x.md).

## Quick index

| Member | Type | Returns |
| --- | --- | --- |
| `Repository::create($path, $debug = true)` | method | `void` |
| `Repository::get($name, $default = null)` | method | `mixed` |
| `Repository::env($name, $default = null)` | method | `mixed` |
| `Repository::flush()` | method | `void` |
| `Repository::drift($reference, $options = [])` | method | `DriftReport` |
| `Repository::assertNoDrift($reference, $options = [])` | method | `void` |
| `DotENV::create / get / env / flush / drift / assertNoDrift` | static (forwarded) | as above |
| `DotENV::instance()` | static | `Repository` |
| `DotENV::reset()` | static | `void` |
| `env($name, $default = null)` | function | `mixed` |
| `env_drift($reference, $options = [])` | function | `DriftReport` |
| `DriftReport` | class | drift result |
| `DotENVException` | class | extends `\InvalidArgumentException` |
| `DriftException` | class | extends `DotENVException` |
