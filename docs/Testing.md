# Testing

Loading a `.env` mutates global state — `$_ENV`, `$_SERVER`, and the process
environment via `putenv()`. That makes tests that touch the environment prone
to leaking into one another. This page shows how to keep them isolated.

## The two tools

| Call | Effect |
| --- | --- |
| [`DotENV::flush()`](API-Reference.md#flush) | Removes only what the shared repository loaded; clears the read cache. Pre-existing env untouched. |
| [`DotENV::reset()`](API-Reference.md#reset) | `flush()` **and** discards the shared instance, so the next call starts fresh. |

Both remove **only the names the library defined** — they never touch
variables that already existed.

## Reset between tests

The simplest reliable pattern: `reset()` in `tearDown()`.

```php
use InitPHP\DotENV\DotENV;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        DotENV::reset();   // unload whatever this test loaded
        parent::tearDown();
    }

    public function testLoadsDatabasePort(): void
    {
        DotENV::create(__DIR__ . '/fixtures/db');  // dir with a .env
        self::assertSame(5432, DotENV::get('DB_PORT'));
    }
}
```

## Fully isolate the superglobals

`reset()` removes names the library added, but a test that also writes to
`$_ENV` / `$_SERVER` directly (or expects them pristine) should snapshot and
restore them. This is exactly what the package's own test suite does:

```php
use InitPHP\DotENV\DotENV;
use PHPUnit\Framework\TestCase;

abstract class EnvTestCase extends TestCase
{
    /** @var array<array-key, mixed> */
    private array $envBackup = [];
    /** @var array<array-key, mixed> */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->envBackup    = $_ENV;
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        DotENV::reset();

        // Drop putenv()/$_ENV keys this test introduced.
        foreach (array_keys($_ENV) as $key) {
            if (!array_key_exists($key, $this->envBackup)) {
                putenv((string) $key);
            }
        }

        $_ENV    = $this->envBackup;
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }
}
```

## Prefer a fresh `Repository` over the facade

Because the facade is global singleton state, tests are cleaner when they use
their own [`Repository`](API-Reference.md#the-repository-class) — no shared
instance to reset, just local state plus the superglobal restore above:

```php
use InitPHP\DotENV\Repository;

public function testInterpolation(): void
{
    $repo = new Repository();
    $repo->create($this->fixtureDir('interpolation')); // dir containing a .env

    self::assertSame('localhost:8080', $repo->get('ADDR'));
}
```

## Generating throwaway `.env` files

A file must be named exactly `.env` or `.env.php`, so create one in a unique
temp directory per test and clean it up:

```php
protected function writeEnv(string $contents): string
{
    $dir = sys_get_temp_dir() . '/dotenv-test-' . uniqid('', true);
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/.env', $contents);
    // remember $dir to delete it in tearDown()
    return $dir; // pass to create()
}

public function testQuotedValue(): void
{
    $repo = new Repository();
    $repo->create($this->writeEnv('GREETING = "hello world"' . "\n"));
    self::assertSame('hello world', $repo->get('GREETING'));
}
```

## Avoid colliding with the real environment

Inside a test runner, `$_SERVER` is full of real keys (`PATH`, `PWD`, CI
variables…). Because of [immutability](Loading-and-Precedence.md#immutability--real-environment-variables-win),
`create()` will **not** overwrite a fixture key that happens to match one of
them. Use fixture key names that won't collide (a prefix, or domain-specific
names like `DB_PORT`, not `PATH`).

## Watch out for the read cache

`get()` caches on first read. Within a single test that loads, reads, then
re-loads different values for the same name, call `flush()` (or use a fresh
`Repository`) between phases so you don't read a stale cached value. See
[Loading & Precedence → Caching](Loading-and-Precedence.md#caching).

## Next steps

- [API Reference](API-Reference.md#flush) — `flush()` / `reset()`
- [Loading & Precedence](Loading-and-Precedence.md) — immutability and caching
- [Recipes](Recipes.md) — reloading in long-running processes
