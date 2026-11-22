# FAQ

Short answers to common questions. Most link to a fuller page.

### Does this overwrite my real environment variables?

No. `create()` never overwrites a name already defined in `$_ENV`,
`$_SERVER`, or `getenv()`. Real environment variables always win — see
[Loading & Precedence](Loading-and-Precedence.md#immutability--real-environment-variables-win).

### Why did `007` come back as the string `"007"` and not `7`?

Because coercing it to `7` would lose the leading zero. Numbers are coerced to
`int`/`float` only when the conversion round-trips exactly. See
[Value Types & Coercion](Value-Types-and-Coercion.md#numbers--loss-free-by-design).

### How do I force a value to be an int / bool?

Cast at the call site:

```php
$port    = (int) DotENV::get('DB_PORT');
$enabled = filter_var(DotENV::get('FEATURE_X'), FILTER_VALIDATE_BOOL);
```

### Can a value reference another value?

Yes — use `${OTHER}`. References are resolved on read, can appear multiple
times per line, and can nest. See
[Variable Interpolation](Variable-Interpolation.md).

### Does it support `$VAR` without braces?

No. Only the `${VAR}` form is recognised; a bare `$VAR` is left as literal
text.

### How do I load different files per environment?

A file must be named exactly `.env` or `.env.php`, so either keep
per-environment files in their own directories and point `create()` at the
directory, or layer an optional override file. See the patterns in
[Recipes](Recipes.md#per-environment-files).

### Why doesn't `getenv()` see my `.env.php` integer?

`putenv()` only accepts strings, so non-string values from a `.env.php` file
are stored in `$_ENV` / `$_SERVER` (and `get()` reads them) but are not pushed
to `getenv()`. Store it as a string if `getenv()` must see it. See
[Using a PHP `.env` File](Using-a-PHP-env-File.md#one-limitation-to-know).

### `create()` threw an exception — how do I make it optional?

Pass `false` as the second argument: `DotENV::create($path, false)` turns
every error (missing file, wrong type, unreadable) into a silent no-op. See
[Error Handling](Error-Handling.md).

### What's the difference between `DotENV::get()` and `env()`?

Nothing functional — `env()` is a global helper equivalent to
`DotENV::get()`, sharing the same state. Use whichever reads better. See
[API Reference](API-Reference.md#the-env-helper).

### `DotENV` vs `Repository` — which should I use?

`DotENV` is a static facade over a single shared `Repository`; it's the usual
entry point. Use `Repository` directly when you want an isolated instance (DI,
tests, loading multiple file sets). See
[API Reference](API-Reference.md#the-repository-class).

### I changed `$_ENV` after reading a value and `get()` still returns the old one.

`get()` caches on first read. Call `DotENV::flush()` (or use a fresh
`Repository`) to clear the cache. See
[Loading & Precedence](Loading-and-Precedence.md#caching).

### How do I reload the file in a long-running worker?

`DotENV::reset()` drops the shared instance and unloads what it added (leaving
the real environment intact), then call `create()` again. See
[Recipes](Recipes.md#reload-configuration-in-a-long-running-worker).

### Is it safe to keep my `.env` in the project?

Keep it out of the web root (or block it) and out of version control. A
`.env.php` additionally executes as PHP — only load trusted ones. See
[Security Best Practices](Security-Best-Practices.md).

### Can I use this with framework X?

Yes — it's a standalone loader with no framework ties. Call `create()` in your
bootstrap and read with `get()` / `env()`. If your framework already defines a
global `env()`, this package's helper steps aside (it only declares `env()`
when the name is free).

### Which PHP versions are supported?

PHP **8.2 through 8.5** (tested on each in CI). For PHP 5.6–7.x, use the `2.0.x`
line. See [Migration from 2.x](Migration-from-2.x.md).

## Next steps

- [Troubleshooting](Troubleshooting.md) — concrete failures and fixes
- [API Reference](API-Reference.md) — every public member
