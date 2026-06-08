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

A non-scalar referenced value (an array or object loaded from a `.env.php`
file) resolves to an empty string inside an interpolation.

## Missing references

A reference to an undefined name resolves to an empty string:

```dotenv
VALUE = ${DOES_NOT_EXIST}suffix
```

```php
DotENV::get('VALUE'); // "suffix"
```

## Circular references

A self-reference or a cycle resolves to an empty string instead of recursing
forever:

```dotenv
A = ${A}
B = ${C}
C = ${B}
```

```php
DotENV::get('A'); // ""
DotENV::get('B'); // ""
DotENV::get('C'); // ""
```
