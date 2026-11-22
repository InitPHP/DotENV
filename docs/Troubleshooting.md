# Troubleshooting

Concrete symptoms and how to fix them. If your issue isn't here, check the
[FAQ](FAQ.md) or [open an issue](https://github.com/InitPHP/DotENV/issues/new).

## `DotENVException: The "…" file could not be found.`

The path you passed doesn't point at an existing file.

- Double-check the path. Relative paths resolve against the **current working
  directory**, which may not be your project root — prefer an absolute path or
  `__DIR__`.
- If you passed a **directory**, the message instead mentions "in the
  directory you specified" — see the next entry.
- To make a missing file non-fatal, pass `false`:
  `DotENV::create($path, false)`. See [Error Handling](Error-Handling.md).

## `DotENVException: …could not be found in the directory you specified.`

You pointed `create()` at a directory that contains neither `.env` nor
`.env.php`.

- Confirm the file is actually there and named **exactly** `.env` or
  `.env.php` (no `.env.txt`, no `env`).
- Remember dotfiles are hidden — `ls -a`, not `ls`.

## `DotENVException: The file to be loaded must be a ".env" or ".env.php" file.`

You passed a file whose name isn't `.env` or `.env.php` (e.g.
`config.env`, `.env.production`, `settings.ini`).

- Rename it, or keep per-environment files in separate directories and point
  `create()` at the directory. See
  [Recipes → Per-environment files](Recipes.md#per-environment-files).

## `DotENVException: The "…" file could not be read.`

The file exists but PHP can't read it — almost always a **permissions** issue.

```bash
ls -l /path/to/.env
chmod u+r /path/to/.env          # make it readable by the owner
```

Check the user your PHP process runs as (web server / FPM pool user) has read
access.

## `DotENVException: The ".env.php" file must return an associative array.`

Your `.env.php` didn't `return` an array.

```php
<?php
return [            // ← must return an array
    'KEY' => 'value',
];
```

A `.env.php` that prints instead of returns, or returns a string/int, triggers
this. See [Using a PHP `.env` File](Using-a-PHP-env-File.md).

## A value comes back as the wrong type

- **Expected an int, got a string `"007"` / `"+90…"`:** that's intentional —
  coercion is loss-free, so identifiers keep their exact text. Cast yourself
  if you need a number. See
  [Value Types & Coercion](Value-Types-and-Coercion.md#numbers--loss-free-by-design).
- **Expected `"true"`, got boolean `true`:** `true`/`false`/`null`/`empty` are
  keywords. Quote it (`KEY="true"`)… but note a quoted keyword is still the
  keyword on read; if you need the literal word, use a non-keyword value.
- **Expected an int, got a string after interpolation:** `${VAR}` is inserted
  via string cast and the value re-coerced — see
  [Variable Interpolation](Variable-Interpolation.md#how-the-referenced-value-is-inserted).

## My `.env` value is ignored / I get a different value

Almost always **immutability**: a real environment variable with the same name
already exists, and it wins.

```php
var_dump(getenv('DB_HOST'), $_ENV['DB_HOST'] ?? null, $_SERVER['DB_HOST'] ?? null);
```

If any of those is already set, `create()` won't overwrite it. This is by
design — see
[Loading & Precedence](Loading-and-Precedence.md#immutability--real-environment-variables-win).
To force the file's value during local debugging, unset the real one first
(`putenv('DB_HOST')`, `unset($_ENV['DB_HOST'], $_SERVER['DB_HOST'])`) or use a
fresh name.

## I updated `$_ENV` but `get()` returns the old value

`get()` caches on first read. Clear it:

```php
DotENV::flush();          // or use a fresh Repository
DotENV::get('CHANGED');   // re-resolved
```

See [Loading & Precedence → Caching](Loading-and-Precedence.md#caching).

## `#ffffff` or a value with `#` got truncated/emptied

You're likely on **2.x**, where a leading `#` was treated as a comment. 3.0
keeps a `#` that isn't preceded by whitespace. Upgrade, or quote the value
(`COLOR="#ffffff"`). See [Migration from 2.x](Migration-from-2.x.md).

## `getenv()` doesn't see a `.env.php` non-string value

By design — `putenv()` only takes strings. Store it as a string if `getenv()`
must see it. See
[Using a PHP `.env` File](Using-a-PHP-env-File.md#one-limitation-to-know).

## Tests leak environment state into each other

Use `DotENV::reset()` in `tearDown()` and snapshot/restore `$_ENV` /
`$_SERVER`. See [Testing](Testing.md).

## `Call to undefined function env()`

The global helper is registered by Composer's `files` autoloader. Make sure
you required `vendor/autoload.php`, and that you ran `composer dump-autoload`
after installing. If another library defines `env()`, this package defers to
it (it only declares `env()` when the name is free) — use `DotENV::get()`
directly in that case.

## Still stuck?

- Re-run the [install verification script](Installation.md#verify-the-install).
- Search or open an [issue](https://github.com/InitPHP/DotENV/issues).
- Ask in [Discussions](https://github.com/orgs/InitPHP/discussions).
