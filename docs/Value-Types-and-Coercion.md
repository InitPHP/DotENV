# Value Types & Coercion

A `.env` file only stores text, but reading a value back gives you a typed PHP
value. Coercion happens inside [`get()`](API-Reference.md#get) (and therefore
[`env()`](API-Reference.md#the-env-helper)), once per name — the result is cached
after the first read.

The pipeline for a string value is:

1. **Keyword?** `true` / `false` / `null` / `empty` (case-insensitive) → the
   matching PHP value.
2. **Interpolate** any `${VAR}` references (see
   [Variable Interpolation](Variable-Interpolation.md)).
3. **Number?** Coerce to `int` / `float` **only if it round-trips exactly**.
4. Otherwise, return the string.

## Keywords

| In the file | `get()` returns | Type |
| --- | --- | --- |
| `true` | `true` | `bool` |
| `false` | `false` | `bool` |
| `null` | `null` | `null` |
| `empty` | `''` | `string` |
| `KEY=` (nothing) | `''` | `string` |

Matching is **case-insensitive**, so `TRUE`, `True` and `true` all become the
boolean `true`.

```dotenv
DEBUG = true
CACHE = False
TOKEN = null
NOTE  = empty
BLANK =
```

```php
DotENV::get('DEBUG'); // true
DotENV::get('CACHE'); // false
DotENV::get('TOKEN'); // null
DotENV::get('NOTE');  // ""
DotENV::get('BLANK'); // ""
```

> The keyword check runs on the **whole** value. `KEY=true story` is not a
> keyword — it stays the string `"true story"`.

## Numbers — loss-free by design

A value is coerced to `int` or `float` **only when converting it back to a
string reproduces the original text**. This keeps numeric-looking identifiers
intact.

```dotenv
PORT    = 5432      # int    5432
PI      = 3.14      # float  3.14
OFFSET  = -42       # int    -42
ZERO    = 0         # int    0
```

```php
DotENV::get('PORT');   // 5432   (int)
DotENV::get('PI');     // 3.14   (float)
DotENV::get('OFFSET'); // -42    (int)
```

Values that *look* numeric but would lose information stay **strings**:

| In the file | `get()` returns | Why it stays a string |
| --- | --- | --- |
| `007` | `"007"` | leading zero would be lost (`7`) |
| `+15551234567` | `"+15551234567"` | leading `+` is not reproduced |
| `0x1A` | `"0x1A"` | not a decimal number |
| `1e3` | `"1e3"` | `(string)(float)"1e3"` is `"1000"`, not `"1e3"` |
| `99999999999999999999` | `"999…"` | overflows `PHP_INT_MAX` |
| `1_000` | `"1_000"` | underscores are PHP source syntax, not data |
| `3.14.15` | `"3.14.15"` | not numeric at all |

```php
DotENV::get('007');           // "007"  — not 7
DotENV::get('PHONE');         // "+15551234567"  (string)
```

> **Why this matters.** Earlier versions ran `intval()` / `floatval()`
> unconditionally, turning ZIP codes like `007` into `7` and phone numbers
> into floats. The round-trip rule fixes that without you having to quote
> every identifier.

> **Note:** quoting does **not** keep a clean number as a string — coercion
> runs on the unquoted content, so `PORT="5432"` still reads back as the int
> `5432`. If you need the string form of a plain number, read it and cast
> yourself: `(string) DotENV::get('PORT')`.

### Forcing a type

If you need a specific type regardless of the file content, cast at the call
site:

```php
$port    = (int) DotENV::get('DB_PORT');
$enabled = filter_var(DotENV::get('FEATURE_X'), FILTER_VALIDATE_BOOL);
```

## Values from a `.env.php` file

A [`.env.php`](Using-a-PHP-env-File.md) file returns a real PHP array, so its
values can already be the right type. Non-string values are returned
**unchanged** — no coercion is applied:

```php
<?php // .env.php
return [
    'DEBUG' => true,   // stays bool true
    'PORT'  => 5432,   // stays int 5432
    'NAME'  => 'app',  // string, behaves like a .env value (coerced/interpolated)
];
```

String values in a `.env.php` file go through the same coercion and
interpolation as `.env` values when you read them.

## Interpolated values

A `${VAR}` reference is inserted via PHP's string cast and then the whole
value is re-coerced. That has a subtle consequence for keyword/number
references — see
[Variable Interpolation → How the referenced value is inserted](Variable-Interpolation.md#how-the-referenced-value-is-inserted).

## Next steps

- [Variable Interpolation](Variable-Interpolation.md) — `${VAR}` references
- [The `.env` File Format](The-env-File-Format.md) — the line grammar
- [API Reference](API-Reference.md#get) — `get()` / `env()` signatures
