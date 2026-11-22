# The `.env` File Format

A `.env` file is a list of `KEY=VALUE` lines. This page documents **exactly**
how each line is parsed, with the edge cases spelled out.

```dotenv
# Application settings
APP_NAME = InitPHP
APP_ENV  = production

DB_HOST = 127.0.0.1
DB_PORT = 5432
```

Reading happens lazily: the file is parsed once at `create()` time, but type
coercion and `${VAR}` interpolation happen when you call
[`get()`](API-Reference.md#get). This page is about the **line grammar**; for
what the value turns into, see [Value Types & Coercion](Value-Types-and-Coercion.md)
and [Variable Interpolation](Variable-Interpolation.md).

## Keys

The line is split on the **first** `=`. Everything before it is the key,
everything after is the value.

```dotenv
DSN = mysql:host=localhost;dbname=app
#     └── value keeps every '=' after the first one
```

- Whitespace around the key is trimmed: `FOO = bar` and `FOO=bar` are the
  same.
- An optional leading **`export `** is stripped, so a file that is also
  `source`-able by a shell works:

  ```dotenv
  export TOKEN = abc123      # key is TOKEN
  ```

  The prefix must be the literal word `export` followed by a **single
  space**. `export\tFOO` (a tab) is not treated as the prefix.

## Comments

A line is a **comment** when its first character (after trimming) is anything
other than a letter, a digit, `_`, or `-`. So every one of these is ignored:

```dotenv
# hash comment
; semicolon comment
// double-slash comment
* star comment
```

Blank lines are ignored too.

### Inline comments

An inline comment begins at the first `#` that is **preceded by whitespace**.
The `#` and everything after it are dropped:

```dotenv
URL = https://example.test   # everything here is a comment
#                            └── value becomes "https://example.test"
```

A `#` that is **not** preceded by whitespace is part of the value. This keeps
hex colours, URL fragments and the like intact:

```dotenv
COLOR    = #ffffff       # value: "#ffffff"
FRAGMENT = page#section  # value: "page#section"
```

> **Why the whitespace rule?** Without it, `COLOR=#ffffff` would be read as an
> empty value followed by a comment. Requiring a space before `#` lets a
> value legitimately start with `#`.

## Quoting

Wrap a value in matching single (`'`) or double (`"`) quotes to take it
**verbatim**. The surrounding quotes are removed; everything between them —
including a `#`, spaces, or the other quote character — is preserved.

```dotenv
GREETING = "hello world"       # value: hello world
PADDED   = "  keep my spaces " # value: "  keep my spaces " (inner spaces kept)
HASH     = "#ffffff"           # value: #ffffff
APOS     = "it's fine"         # value: it's fine
```

Quoting works even when there is whitespace around the `=`:

```dotenv
SITE = "https://example.test"  # value: https://example.test
```

An inline comment is still recognised **after** the closing quote:

```dotenv
TOKEN = "abc" # secret         # value: abc
```

Escape sequences are **not** processed inside quotes — `\n`, `\t` and `\"` are
kept literally. If you need real newlines or tabs in a value, set them another
way (for example through a [`.env.php`](Using-a-PHP-env-File.md) file).

## Whitespace

Leading and trailing whitespace is trimmed from both the key and the value:

```dotenv
   SPACED   =    value with trailing spaces
#  └ key: "SPACED", value: "value with trailing spaces"
```

To preserve leading/trailing spaces in a value, quote it (see above).

## Malformed lines

A non-comment line with **no `=`** is ignored rather than producing a junk
entry. It does not raise a warning:

```dotenv
THIS_LINE_IS_IGNORED
VALID = 1                 # only VALID is defined
```

## Parsing cheat-sheet

| Line | Parsed key | Parsed value |
| --- | --- | --- |
| `FOO=bar` | `FOO` | `bar` |
| `FOO = bar` | `FOO` | `bar` |
| `export FOO=bar` | `FOO` | `bar` |
| `FOO="a b"` | `FOO` | `a b` |
| `FOO='a b'` | `FOO` | `a b` |
| `FOO=a # c` | `FOO` | `a` |
| `FOO=#abc` | `FOO` | `#abc` |
| `FOO=a#b` | `FOO` | `a#b` |
| `FOO=mysql:a=b` | `FOO` | `mysql:a=b` |
| `# comment` | — | *(ignored)* |
| `; comment` | — | *(ignored)* |
| `NO_EQUALS_HERE` | — | *(ignored)* |
| *(blank)* | — | *(ignored)* |

## Next steps

- [Value Types & Coercion](Value-Types-and-Coercion.md) — what the parsed string
  becomes when you read it
- [Variable Interpolation](Variable-Interpolation.md) — `${VAR}` references
- [Using a PHP `.env` File](Using-a-PHP-env-File.md) — native types via a PHP
  array
