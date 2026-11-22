# Using a PHP `.env` File

Instead of a plain-text `.env`, you can use a **`.env.php`** file that
**returns an associative array**. This is useful when you want native PHP
types without relying on string coercion, or when you want to compute values.

```php
<?php
// /path/to/project/.env.php

return [
    'SITE_URL'      => 'https://example.test',
    'PAGE_URL'      => '${SITE_URL}/page',
    'TRUE_VALUE'    => true,
    'FALSE_VALUE'   => false,
    'NULL_VALUE'    => null,
    'NUMERIC_VALUE' => 13,
];
```

```php
use InitPHP\DotENV\DotENV;

DotENV::create('/path/to/project/.env.php');

DotENV::get('SITE_URL');      // "https://example.test"
DotENV::get('PAGE_URL');      // "https://example.test/page"  (interpolated on read)
DotENV::get('TRUE_VALUE');    // true   (bool, not coerced from a string)
DotENV::get('NULL_VALUE');    // null
DotENV::get('NUMERIC_VALUE'); // 13     (int)
```

You can also point `create()` at the **directory** and let it find the file:

```php
DotENV::create('/path/to/project'); // uses .env if present, otherwise .env.php
```

## When to reach for `.env.php`

| Use a `.env.php` when… | Example |
| --- | --- |
| You want exact PHP types with no coercion ambiguity | `'PORT' => 5432` is always an `int` |
| A value needs to be computed | `'BUILD' => trim(shell_exec('git rev-parse --short HEAD'))` |
| You want to keep config in PHP your IDE can analyse | autocompletion, constants, etc. |
| You ship a cached/compiled config | a build step writes `.env.php` |

## How values are treated

- **Strings** behave exactly like `.env` values: they are pushed to
  `putenv()` and resolved through [coercion](Value-Types-and-Coercion.md) and
  [`${VAR}` interpolation](Variable-Interpolation.md) when read.
- **Non-strings** (`bool`, `int`, `float`, `null`, arrays, objects) are stored
  as-is in `$_ENV` / `$_SERVER` and returned unchanged by `get()`.
- **Array keys are cast to strings.**

### One limitation to know

`putenv()` only accepts strings, so **non-string values are not written to
`getenv()`**. They live in `$_ENV` / `$_SERVER` (and `get()` reads them), but
third-party code that calls `getenv()` directly will not see them:

```php
DotENV::create('/path/to/.env.php');   // returns ['PORT' => 8080]

DotENV::get('PORT');    // 8080  (int)
$_ENV['PORT'];          // 8080  (int)
getenv('PORT');         // false — non-string values are not pushed to putenv()
```

If a value must be visible through `getenv()`, store it as a string
(`'PORT' => '8080'`); it will still be coerced back to an int on read.

## Validation

If the file does **not** return an array, a
[`DotENVException`](Error-Handling.md) is thrown — unless you disabled exceptions
with the second `create()` argument:

```php
DotENV::create('/path/to/bad.env.php');        // throws if it returns a non-array
DotENV::create('/path/to/bad.env.php', false); // silently does nothing instead
```

## Security: a `.env.php` runs code

A `.env.php` file is loaded with `require`, so it is **executed as PHP**. Treat
it as code, not data — never load a `.env.php` from an untrusted or
user-writable location. See [Security Best Practices](Security-Best-Practices.md).

## Next steps

- [Value Types & Coercion](Value-Types-and-Coercion.md) — how string values are
  typed
- [Loading & Precedence](Loading-and-Precedence.md) — directory resolution and
  immutability
- [Security Best Practices](Security-Best-Practices.md) — the `.env.php` caveat in
  context
