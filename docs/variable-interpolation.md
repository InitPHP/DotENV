# Variable interpolation

A value may reference another variable with `${NAME}`. References are resolved
**when you read the value** with `get()`, not when the file is loaded.

```dotenv
SITE_URL = http://lvh.me
PAGE_URL = ${SITE_URL}/page
```

```php
DotENV::get('PAGE_URL'); // "http://lvh.me/page"
```

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

A referenced value may itself contain references; they are resolved
recursively:

```dotenv
A = root
B = ${A}/b
C = ${B}/c
```

```php
DotENV::get('C'); // "root/b/c"
```

## Resolution source

`${NAME}` is resolved with the same lookup that `get()` uses
(`$_ENV` → `$_SERVER` → `getenv()`), so a reference can point at a real
environment variable, not just another line in the same file.

## How the referenced value is inserted

A reference is replaced with the **string cast** of the referenced value,
then the whole surrounding value is coerced as usual. So referencing a
[coerced type](value-types.md) behaves like PHP's `(string)` cast:

| `NAME` value | `${NAME}` inserts | `WRAP=${NAME}` becomes |
| ------------ | ----------------- | ---------------------- |
| `"text"`     | `text`            | `"text"`               |
| `13`         | `13`              | `13` (int)             |
| `true`       | `1`               | `1` (int)              |
| `false`      | *(empty)*         | `""`                   |
| `null`       | *(empty)*         | `""`                   |

A non-scalar referenced value (an array or object loaded from a `.env.php`
file) inserts an empty string.

## Missing references

A reference to an undefined name resolves to an empty string:

```dotenv
VALUE = ${DOES_NOT_EXIST}suffix
```

```php
DotENV::get('VALUE'); // "suffix"
```

## Circular references

A reference back to a name that is still being resolved (a self-reference or
a cycle) is replaced with an empty string instead of recursing forever. Any
literal text around the reference is kept:

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
DotENV::get('D'); // "-tail"   (the cyclic ${D} is dropped, "-tail" remains)
```
