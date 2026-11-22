# Loading & Precedence

This page covers how `create()` finds and loads a file, and how `get()`
resolves a value — including the immutability rule that lets real environment
variables win.

## What `create()` accepts

```php
public function create(string $path, bool $debug = true): void
```

`$path` may be **a file** or **a directory**:

```php
DotENV::create('/app/.env');       // explicit .env file
DotENV::create('/app/.env.php');   // explicit .env.php file
DotENV::create('/app');            // directory: tries .env, then .env.php
```

When you pass a directory, the loader looks for **`.env` first**, then
`.env.php`. The first one found is used.

Only files literally named `.env` or `.env.php` are accepted. A path like
`/app/config.txt` is rejected (see [Error Handling](Error-Handling.md)).

## Where loaded values go

For each `KEY=VALUE` parsed from the file, `create()` writes the value into
**all three** stores — unless the name is already defined (see immutability
below):

| Store | Written with | Notes |
| --- | --- | --- |
| `$_ENV` | `$_ENV[$key] = $value` | any type |
| `$_SERVER` | `$_SERVER[$key] = $value` | any type |
| `getenv()` | `putenv("$key=$value")` | **string values only** — `putenv()` can't take non-strings |

So after loading a string value, all of `$_ENV['KEY']`, `$_SERVER['KEY']` and
`getenv('KEY')` return it. Non-string values from a
[`.env.php`](Using-a-PHP-env-File.md) file skip `putenv()`.

## Immutability — real environment variables win

`create()` **never overwrites** a name that is already defined. A name counts
as defined if it is present in **any** of the three read sources:

```
$_ENV   OR   $_SERVER   OR   getenv()
```

```php
// Real environment (set by your container/OS):
//   DB_HOST=db.internal

DotENV::create('/app/.env'); // .env says DB_HOST=127.0.0.1

DotENV::get('DB_HOST');      // "db.internal" — the real value wins
```

This is what makes the pattern "commit a `.env` for local development, set
real environment variables in production" work: in production the real values
take precedence and the `.env` file (if present at all) fills only the gaps.

> Checking `getenv()` too — not just the superglobals — matters because a real
> environment variable can be visible through `getenv()` while absent from
> `$_ENV` / `$_SERVER` when PHP's `variables_order` excludes `E`. Without that
> check a `.env` file could silently clobber a genuine environment variable.

## How `get()` resolves a value

```php
public function get(string $name, mixed $default = null): mixed
```

Lookup order:

1. **Internal cache** — if this name was read before, return the cached value.
2. **`$_ENV`**
3. **`$_SERVER`**
4. **`getenv()`**
5. Otherwise return **`$default`** (`null` if you didn't pass one).

The first store that defines the name wins. The raw value is then
[coerced and interpolated](Value-Types-and-Coercion.md), and the result is cached.

```php
DotENV::get('TIMEZONE');            // null if undefined anywhere
DotENV::get('TIMEZONE', 'UTC');     // "UTC" if undefined
```

### Caching

A resolved value is cached on first read, so repeated `get()` calls are cheap
and coercion runs at most once per name. The trade-off: if you mutate `$_ENV`
/ `$_SERVER` / the real environment **after** a value has been read, `get()`
keeps returning the cached value until you [`flush()`](API-Reference.md#flush).

A name that resolves to the **default** (undefined) is *not* cached, so it can
still be defined and read later.

## The `$debug` flag

The second argument to `create()` controls error handling:

```php
DotENV::create('/app/.env');        // $debug = true  → throws on any problem
DotENV::create('/app/.env', false); // $debug = false → silent no-op on any problem
```

See [Error Handling](Error-Handling.md) for the full list of conditions.

## Loading more than once

`create()` is additive: call it several times to layer files. Because of
immutability, **earlier definitions win over later ones** — load the most
specific file first:

```php
DotENV::create('/app/.env.local', false); // wins where present (optional)
DotENV::create('/app/.env');              // fills the rest
```

To start over (e.g. between tests), use
[`flush()` / `reset()`](API-Reference.md#flush) — they remove only what the
repository loaded, leaving the real environment intact.

## Next steps

- [Error Handling](Error-Handling.md) — what throws, and silent mode
- [Reading values — API Reference](API-Reference.md#get) — `get()` / `env()`
- [Testing](Testing.md) — `flush()` / `reset()` between tests
