# Exceptions

The package throws a single exception type:

`InitPHP\DotENV\Exceptions\DotENVException`

It extends `\InvalidArgumentException`, so existing
`catch (\InvalidArgumentException $e)` blocks keep working.

```
\InvalidArgumentException
 └── InitPHP\DotENV\Exceptions\DotENVException
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
