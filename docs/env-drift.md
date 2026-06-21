# Environment drift

After an env file is loaded, **drift detection** compares the *actual loaded
environment* against a *reference* — the keys that should be present — and
reports the differences. It catches the failure that bites in CI and
production: a key your code expects that was never provisioned in the
environment it is running in.

The check is **read-only** and **opt-in**. It never defines, reads-through, or
mutates an environment value, so running it after `create()` does not change
what is loaded.

## The reference

The reference declares the expected keys. It can be either of:

- **A file path** — typically a checked-in `.env.example` (any file name works;
  it does not have to be `.env`/`.env.php`). It is parsed with the same grammar
  as a real env file, so comments, quoting and the `export` prefix all behave
  the same. Only the **keys** are read; values are never defined. A directory
  argument is resolved to the `.env`/`.env.php` inside it, and a `*.env.php`
  reference is `require`d and must return an array.
- **An array** — either a list of required key names
  (`['DB_HOST', 'DB_PORT']`) or an associative map
  (`['DB_HOST' => 'localhost', 'DB_PORT' => 3306]`). For a map, only the keys
  matter.

```php
use InitPHP\DotENV\DotENV;

DotENV::create('/home/www/.env');

$report = DotENV::drift('/home/www/.env.example');   // file reference
$report = DotENV::drift(['DB_HOST', 'DB_PORT']);     // required-keys list
$report = DotENV::drift(['DB_HOST' => 'localhost']); // map (keys only)
```

A key counts as **present** in the actual environment when it is defined in
`$_ENV`, `$_SERVER`, or `getenv()` — the same three sources `get()` reads from.

## What is reported

`drift()` returns a `InitPHP\DotENV\Drift\DriftReport` with three buckets:

| Bucket | Meaning | Default |
| ------ | ------- | ------- |
| **missing** | a reference key that is absent from the actual environment | always reported |
| **extra** | a key this loader defined that is **not** in the reference | opt-in |
| **empty** | a reference key that is present but resolves to a blank value | opt-in |

`extra` and `empty` are off by default. Extra keys are usually noise, and only
keys *this loader itself defined* (tracked the same way `flush()` tracks them)
are ever candidates — unrelated OS environment variables are never reported as
extra. A value is treated as **empty** when it resolves to an empty string,
`null`, or `false` (the forms a `.env` file produces for a blank, `empty`,
`null` or `false` entry).

Enable the opt-in buckets through the `$options` argument:

```php
$report = DotENV::drift('/home/www/.env.example', [
    'extra' => true,   // also flag undocumented loaded keys
    'empty' => true,   // also flag blank-but-required keys
]);
```

## The report

```php
$report->hasDrift();    // bool — any drift at all?
$report->isEmpty();     // bool — inverse of hasDrift()
$report->getMissing();  // list<string>
$report->getExtra();    // list<string>
$report->getEmpty();    // list<string>
$report->count();       // int — total drifted keys across every bucket
$report->toArray();     // ['missing' => [...], 'extra' => [...], 'empty' => [...]]
(string) $report;       // human-readable summary, e.g. for a CI log
```

```php
$report = DotENV::drift('/home/www/.env.example');

if ($report->hasDrift()) {
    error_log((string) $report);
    foreach ($report->getMissing() as $key) {
        error_log("Undefined required key: {$key}");
    }
}
```

## Strict mode: `assertNoDrift()`

`assertNoDrift()` runs the same comparison and throws a
[`DriftException`](exceptions.md) the moment any drift is found, returning
silently when the environment is clean. It is the fail-fast form for a
bootstrap guard or a CI step:

```php
use InitPHP\DotENV\DotENV;
use InitPHP\DotENV\Exceptions\DriftException;

DotENV::create(__DIR__);

try {
    DotENV::assertNoDrift(__DIR__ . '/.env.example', ['empty' => true]);
} catch (DriftException $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    fwrite(STDERR, 'Missing: ' . implode(', ', $e->getReport()->getMissing()) . PHP_EOL);
    exit(1);
}
```

`DriftException` extends [`DotENVException`](exceptions.md) (and therefore
`\InvalidArgumentException`), so it is catchable by existing handlers, and the
offending `DriftReport` is attached via `getReport()`.

## Standalone and helper forms

Everything above works on a standalone `Repository` instance and through the
global `env_drift()` helper:

```php
use InitPHP\DotENV\Repository;

$env = new Repository();
$env->create('/home/www/.env');
$report = $env->drift(['APP_KEY']);

// Global helper over the shared facade instance:
$report = env_drift('/home/www/.env.example', ['extra' => true]);
```
