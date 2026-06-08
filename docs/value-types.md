# Value types

`.env` files only store text, but reading a value back gives you a typed PHP
value. Coercion happens in `get()` (and therefore `env()`), once per name.

## Keywords

These literal values (case-insensitive) are coerced to PHP types:

| In the file        | `get()` returns |
| ------------------ | --------------- |
| `true`             | `true` (bool)   |
| `false`            | `false` (bool)  |
| `null`             | `null`          |
| `empty`            | `''` (string)   |
| *(nothing)* `KEY=` | `''` (string)   |

```dotenv
DEBUG  = true
CACHE  = false
TOKEN  = null
NOTE   = empty
BLANK  =
```

```php
DotENV::get('DEBUG'); // true
DotENV::get('CACHE'); // false
DotENV::get('TOKEN'); // null
DotENV::get('NOTE');  // ""
DotENV::get('BLANK'); // ""
```

## Numbers

A value is coerced to `int` or `float` **only when the conversion round-trips
exactly** — that is, when turning the number back into a string reproduces the
original text. This keeps numeric-looking identifiers intact.

```dotenv
PORT = 8080      # int    8080
PI   = 3.14      # float  3.14
NEG  = -42       # int    -42
```

```php
DotENV::get('PORT'); // 8080   (int)
DotENV::get('PI');   // 3.14   (float)
DotENV::get('NEG');  // -42    (int)
```

Values that look numeric but would lose information stay **strings**:

```dotenv
ZIP     = 007                    # string "007"      (leading zero)
PHONE   = +905551112233          # string "+905..."  (leading plus)
BIG     = 99999999999999999999   # string            (beyond PHP_INT_MAX)
SCI     = 1e3                    # string "1e3"       (not "1000")
```

```php
DotENV::get('ZIP');   // "007"   — not 7
DotENV::get('PHONE'); // "+905551112233"
```

> This is deliberate. Earlier versions used `intval()`/`floatval()`
> unconditionally, which turned `007` into `7` and phone numbers into floats.
> See the [changelog](../CHANGELOG.md).

## Everything else

Any value that is not a keyword and not a round-tripping number is returned as
a string, after `${VAR}` interpolation. See
[Variable interpolation](variable-interpolation.md).

## `.env.php` values

Values coming from a [`.env.php`](php-env-file.md) file that are already
non-strings (real booleans, ints, `null`, arrays) are returned unchanged —
no coercion is applied to them.
