# Using a `.env.php` file

Instead of a plain-text `.env` file you can use a `.env.php` file that
**returns an associative array**. This is handy when you want native PHP
types (real booleans, integers, `null`) without relying on string coercion,
or when you want to compute values.

```php
<?php
// /path/to/project/.env.php

return [
    'SITE_URL'      => 'http://lvh.me',
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

DotENV::get('SITE_URL');      // "http://lvh.me"
DotENV::get('PAGE_URL');      // "http://lvh.me/page"  (interpolated on read)
DotENV::get('TRUE_VALUE');    // true   (bool, not coerced from a string)
DotENV::get('NULL_VALUE');    // null
DotENV::get('NUMERIC_VALUE'); // 13     (int)
```

You can also point `create()` at the directory and let it find the file:

```php
DotENV::create('/path/to/project'); // uses .env if present, else .env.php
```

## How values are treated

- **Strings** are stored and behave exactly like `.env` values: they are
  pushed to `putenv()` and resolved through coercion and `${VAR}`
  interpolation when you read them.
- **Non-strings** (`bool`, `int`, `float`, `null`, arrays, objects) are stored
  as-is in `$_ENV` / `$_SERVER` and returned unchanged by `get()`. They are
  *not* pushed to `putenv()`, because `putenv()` only accepts strings.
- Array keys are cast to strings.

## Validation

If the file does not return an array, a
[`DotENVException`](exceptions.md) is thrown (unless you disabled exceptions
with the second `create()` argument):

```php
DotENV::create('/path/to/bad.env.php');        // throws if it returns a non-array
DotENV::create('/path/to/bad.env.php', false); // silently does nothing instead
```

## A word of caution

A `.env.php` file is executed with `require`. Treat it as code, not data:
never load a `.env.php` file from an untrusted source. See the
[security notes](security.md).
