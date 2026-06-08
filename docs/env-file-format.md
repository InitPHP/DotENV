# The `.env` file format

A `.env` file is a list of `KEY=VALUE` lines. This page describes exactly how
each line is parsed.

```dotenv
# This is a comment
APP_NAME = InitPHP
APP_ENV  = production

DB_HOST = 127.0.0.1
DB_PORT = 3306
```

## Keys

- A key and its value are split on the **first** `=` on the line, so a value
  may itself contain `=`:

  ```dotenv
  DSN = mysql:host=localhost;dbname=app
  # key: DSN, value: mysql:host=localhost;dbname=app
  ```

- Surrounding whitespace around the key is trimmed (`FOO = bar` and `FOO=bar`
  are equivalent).

- An optional leading `export ` is stripped, so shell-sourced files work too:

  ```dotenv
  export TOKEN = abc123
  # key: TOKEN
  ```

## Comments

A line is treated as a **comment** when its first character (after trimming)
is anything other than a letter, a digit, `_`, or `-`. So all of these are
comments:

```dotenv
# hash comment
; semicolon comment
// slash comment
```

Blank lines are ignored.

### Inline comments

An inline comment begins at the first `#` that is **preceded by whitespace**:

```dotenv
URL = http://lvh.me   # this part is a comment   -> http://lvh.me
```

A `#` that is *not* preceded by whitespace is part of the value, so values
such as colours and URL fragments survive:

```dotenv
COLOR    = #ffffff       # -> #ffffff
FRAGMENT = page#section  # -> page#section
```

## Quoting

Wrap a value in matching single or double quotes to keep it verbatim. The
quotes are removed; everything between them — including a `#` — is preserved.

```dotenv
GREETING = "hello world"     # -> hello world
HASH     = "#ffffff"         # -> #ffffff
RAW      = 'no ${VAR} here'  # -> no ${VAR} here  (still interpolated on read, see note)
```

Quoting also works when there is whitespace around the `=`:

```dotenv
SITE = "http://lvh.me"   # -> http://lvh.me
```

An inline comment is still recognised after the closing quote:

```dotenv
TOKEN = "abc" # secret   # -> abc
```

> **Note:** quoting changes how the *line* is parsed, not how the *value* is
> resolved later. `${VAR}` interpolation and type coercion happen when you call
> `get()`, regardless of whether the value was quoted in the file. See
> [Variable interpolation](variable-interpolation.md) and
> [Value types](value-types.md).

## Malformed lines

A non-comment line with no `=` is ignored rather than producing a junk entry:

```dotenv
THIS_LINE_IS_IGNORED
VALID = 1
```
