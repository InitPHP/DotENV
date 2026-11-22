# Error Handling

The package throws one exception type — plus a subclass for
[drift checks](Environment-Drift.md) — and gives you a switch to silence
load failures. Reading a value never throws for a missing key — it returns the default.

## The exception

`InitPHP\DotENV\Exceptions\DotENVException` extends
`\InvalidArgumentException`, so existing `catch (\InvalidArgumentException $e)`
blocks keep working.

```
\InvalidArgumentException
 └── InitPHP\DotENV\Exceptions\DotENVException
      └── InitPHP\DotENV\Exceptions\DriftException
```

## When `create()` throws

With `$debug` left at its default of `true`, `create()` throws a
`DotENVException` in each of these cases:

| Situation | Message (abridged) |
| --- | --- |
| A **directory** was given but contains no `.env`/`.env.php` | *…could not be found in the directory you specified.* |
| The path does not point at an existing file | *The "…" file could not be found.* |
| The file name is not `.env` or `.env.php` | *The file to be loaded must be a ".env" or ".env.php" file.* |
| The file exists but could not be read | *The "…" file could not be read.* |
| A `.env.php` file did not return an array | *The ".env.php" file must return an associative array.* |

`get()` / `env()` **never** throw for a missing key — they return the
`$default` (or `null`).

## Throwing style

Let it throw and decide what to do at the boundary:

```php
use InitPHP\DotENV\DotENV;
use InitPHP\DotENV\Exceptions\DotENVException;

try {
    DotENV::create('/etc/app/.env');
} catch (DotENVException $e) {
    // Configuration is required here — fail loudly.
    fwrite(STDERR, 'Could not load environment: ' . $e->getMessage() . "\n");
    exit(1);
}
```

## Best-effort style — `$debug = false`

Pass `false` as the second argument to turn **every** condition above into a
silent no-op. Nothing is thrown; nothing is defined for the missing/invalid
file:

```php
// Load it if it's there; ignore it if it isn't.
DotENV::create('/etc/app/.env', false);

// Layer an optional local override on top of a committed file:
DotENV::create(__DIR__ . '/.env');             // required — throws if missing
DotENV::create(__DIR__ . '/.env.local', false); // optional — silent if missing
```

This pairs naturally with immutability: load the optional, more-specific file
in best-effort mode, then the base file. See
[Loading & Precedence](Loading-and-Precedence.md#loading-more-than-once).

## A defensive bootstrap

A common pattern — require the base file, allow a local override, and surface
a clear message if the base is missing:

```php
use InitPHP\DotENV\DotENV;
use InitPHP\DotENV\Exceptions\DotENVException;

function bootstrapEnv(string $root): void
{
    try {
        DotENV::create($root . '/.env');
    } catch (DotENVException $e) {
        throw new RuntimeException(
            "Missing or unreadable .env in {$root}. Copy .env.example to .env.",
            0,
            $e
        );
    }

    // Optional, never fatal:
    DotENV::create($root . '/.env.local', false);
}
```

## `DriftException`

`InitPHP\DotENV\Exceptions\DriftException` extends `DotENVException`. It is
thrown only by [`assertNoDrift()`](Environment-Drift.md#strict-mode-assertnodrift)
when the loaded environment differs from its reference, and carries the
`DriftReport` that triggered it:

```php
try {
    DotENV::assertNoDrift(__DIR__ . '/.env.example');
} catch (DriftException $e) {
    $missing = $e->getReport()->getMissing();
}
```

Because it is a `DotENVException`, an existing `catch (DotENVException $e)`
block also catches it. `drift()` and `assertNoDrift()` can also throw a plain
`DotENVException` when a reference *file path* cannot be found, read or parsed;
an array reference never throws.

## Validating required keys yourself

For a reusable check against a `.env.example` or a key list, use
[environment drift detection](Environment-Drift.md). For a one-off guard in
your own code:

```php
function requireEnv(string ...$names): void
{
    $missing = array_filter($names, static fn ($n) => DotENV::get($n) === null);
    if ($missing !== []) {
        throw new RuntimeException('Missing required env: ' . implode(', ', $missing));
    }
}

requireEnv('APP_KEY', 'DB_HOST', 'DB_NAME');
```

## Next steps

- [Loading & Precedence](Loading-and-Precedence.md) — the `$debug` flag in context
- [API Reference](API-Reference.md#dotenvexception) — the exception
- [Troubleshooting](Troubleshooting.md) — concrete failures and fixes
