# Variable Interpolation

A value may reference another variable with `${NAME}`. References are resolved
**when you read the value** with [`get()`](API-Reference.md#get), not when the
file is loaded.

```dotenv
SITE_URL = https://example.test
PAGE_URL = ${SITE_URL}/page
```

```php
DotENV::get('PAGE_URL'); // "https://example.test/page"
```

Only the `${NAME}` form is recognised. A bare `$NAME` (no braces) is left
untouched.

## Multiple references on one line

Any number of references may appear in a single value:

```dotenv
HOST = localhost
PORT = 8080
ADDR = ${HOST}:${PORT}
```

```php
DotENV::get('ADDR'); // "localhost:8080"
```

## Nested references

A referenced value may itself contain references; they resolve recursively:

```dotenv
ROOT = /var/www
APP  = ${ROOT}/app
LOGS = ${APP}/storage/logs
```

```php
DotENV::get('LOGS'); // "/var/www/app/storage/logs"
```

## Resolution source

`${NAME}` is resolved with the same lookup that `get()` uses
(`$_ENV` → `$_SERVER` → `getenv()`), so a reference can point at a **real
environment variable**, not just another line in the same file:

```dotenv
# If HOME is set in the real environment:
CONFIG_PATH = ${HOME}/.config/app
```

## How the referenced value is inserted

A reference is replaced with the **string cast** of the referenced value, and
then the whole surrounding value is coerced as usual (see
[Value Types & Coercion](Value-Types-and-Coercion.md)). So referencing a coerced
type behaves like PHP's `(string)` cast:

| `NAME`'s value | `${NAME}` inserts | `WRAP=${NAME}` becomes |
| --- | --- | --- |
| `"text"` | `text` | `"text"` (string) |
| `42` | `42` | `42` (int) |
| `true` | `1` | `1` (int) |
| `false` | *(empty)* | `""` (string) |
| `null` | *(empty)* | `""` (string) |

A **non-scalar** referenced value (an array or object loaded from a
[`.env.php`](Using-a-PHP-env-File.md) file) inserts an empty string.

```dotenv
FLAG = true
WRAP = ${FLAG}
```

```php
DotENV::get('WRAP'); // 1  (int) — true cast to "1", then re-coerced
```

> If you want the literal text `true`, reference a string instead of a keyword
> (e.g. set `FLAG="true"`)… but note a quoted keyword is still the keyword on
> read. For predictable textual composition, interpolate string values.

## Missing references

A reference to an undefined name resolves to an empty string:

```dotenv
VALUE = ${DOES_NOT_EXIST}suffix
```

```php
DotENV::get('VALUE'); // "suffix"
```

## Whitespace and empty braces

Whitespace inside the braces is ignored, so `${ NAME }` works the same as
`${NAME}`. Empty braces are left literal because the pattern needs at least
one character between them:

```dotenv
NAME    = world
SPACED  = hello ${ NAME }   # "hello world"
EMPTYBR = a${}b             # "a${}b"  (left as-is)
ONLYWS  = a${ }b            # "ab"     (name is empty → "")
```

## Circular references

A reference back to a name that is **still being resolved** (a self-reference,
or a cycle through several names) is replaced with an empty string instead of
recursing forever. Any literal text around the reference is kept:

```dotenv
A = ${A}
B = ${C}
C = ${B}
D = ${D}-tail
```

```php
DotENV::get('A'); // ""
DotENV::get('B'); // ""
DotENV::get('C'); // ""
DotENV::get('D'); // "-tail"   (the cyclic ${D} drops out, "-tail" remains)
```

Self-references and simple cycles are fully deterministic. A pathological
mutual cycle where **both** sides also carry literal text (e.g. `X=${Y}x`,
`Y=${X}y`) terminates safely but has no single well-defined value — avoid
writing those.

## Next steps

- [Value Types & Coercion](Value-Types-and-Coercion.md) — how the assembled value
  is typed
- [The `.env` File Format](The-env-File-Format.md) — quoting and comments
- [Loading & Precedence](Loading-and-Precedence.md) — where `${NAME}` looks
