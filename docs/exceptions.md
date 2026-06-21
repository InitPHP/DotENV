# Exceptions

The package throws from a single base type:

`InitPHP\DotENV\Exceptions\DotENVException`

It extends `\InvalidArgumentException`, so existing
`catch (\InvalidArgumentException $e)` blocks keep working. The strict
[drift](env-drift.md) mode adds a `DriftException` subclass of it.

```
\InvalidArgumentException
 └── InitPHP\DotENV\Exceptions\DotENVException
      └── InitPHP\DotENV\Exceptions\DriftException
```

## When it is thrown

`create()` throws a `DotENVException` when, with `$debug` left at its default
of `true`:

| Situation | Message (abridged) |
| --------- | ------------------ |
| A directory was given but contains no `.env`/`.env.php` | *…could not be found in the directory you specified.* |
| The path does not point at an existing file | *The "…" file could not be found.* |
| The file name is not `.env` or `.env.php` | *The file to be loaded must be a ".env" or ".env.php" file.* |
| The file could not be read | *The "…" file could not be read.* |
| A `.env.php` file did not return an array | *The ".env.php" file must return an associative array.* |

`get()` / `env()` never throw for a missing key — they return the default.

## `DriftException`

`InitPHP\DotENV\Exceptions\DriftException`

Thrown only by the strict [drift](env-drift.md) mode,
`Repository::assertNoDrift()` / `DotENV::assertNoDrift()`, when the loaded
environment drifts from its reference. It extends `DotENVException` (and thus
`\InvalidArgumentException`), and carries the offending report:

```php
use InitPHP\DotENV\DotENV;
use InitPHP\DotENV\Exceptions\DriftException;

try {
    DotENV::assertNoDrift(__DIR__ . '/.env.example');
} catch (DriftException $e) {
    $e->getMessage();              // human-readable summary
    $e->getReport()->getMissing(); // the missing keys
}
```

The non-throwing `drift()` method never throws for drift — it returns a
`DriftReport`. It can still raise a `DotENVException` if a *reference file path*
cannot be found or read (an array reference never throws).

## Suppressing exceptions

Pass `false` as the second argument to `create()` to turn every one of the
situations above into a silent no-op:

```php
use InitPHP\DotENV\DotENV;
use InitPHP\DotENV\Exceptions\DotENVException;

// Throwing style
try {
    DotENV::create('/etc/app/.env');
} catch (DotENVException $e) {
    // log and fall back to defaults
}

// Best-effort style — load it if it's there, ignore it if it isn't
DotENV::create('/etc/app/.env', false);
```
