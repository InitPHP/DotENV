# Migration from 2.x

`3.0` is a maintenance-focused major release: real bug fixes, corrected value
semantics, a modern PHP 8 codebase, plus tests, CI and documentation. The
**public API is unchanged** — `DotENV::create()`, `get()`, `env()` and the
global `env()` helper all keep their signatures. Most projects upgrade by
bumping the constraint and re-reading this page.

```bash
composer require initphp/dotenv:^3.0
```

## What might affect you

### 1. PHP 8.0+ is now required

The minimum PHP version went from **5.6** to **8.0** in 3.0 (version
4.0.0 raises it to **8.2**; see the
[changelog](https://github.com/InitPHP/DotENV/blob/main/CHANGELOG.md)). If you are on an older
PHP, stay on the `2.0.x` line:

```json
{ "require": { "initphp/dotenv": "^2.0" } }
```

### 2. Numbers are only coerced when it's loss-free

This is the change most likely to be visible. In 2.x, any numeric-looking
value went through `intval()` / `floatval()` unconditionally. In 3.0 a value
is coerced to `int` / `float` **only when converting it back to a string
reproduces the original text**.

| Value in file | 2.x `get()` | 3.0 `get()` |
| --- | --- | --- |
| `13` | `13` (int) | `13` (int) — unchanged |
| `3.14` | `3.14` (float) | `3.14` (float) — unchanged |
| `007` | `7` (int) | `"007"` (string) ✅ preserved |
| `+15551234567` | `1.5551234567E+10` (float) | `"+15551234567"` (string) ✅ |
| `99999999999999999999` | overflowed int/float | `"999…"` (string) ✅ |
| `1e3` | `1000.0` (float) | `"1e3"` (string) |

If your code relied on `get('ZIP') === 7`, update it. See
[Value Types & Coercion](Value-Types-and-Coercion.md). If you specifically want a
number as an int, cast at the call site: `(int) DotENV::get('PORT')`.

### 3. Several parsing bugs are fixed — results change

These were broken in 2.x and now work correctly. If you had worked *around*
the old behaviour, revisit it:

- **Directory paths work.** `create('/some/dir')` used to always throw; it now
  finds `.env` / `.env.php` inside the directory.
- **Multiple `${VAR}` on one line** used to collapse to an empty string; they
  now resolve (`${A}:${B}` → both).
- **Quoted values with spaces around `=`** (`KEY = "value"`) used to keep their
  quotes; the quotes are now stripped.
- **Values starting with `#`** (e.g. `#ffffff`) used to be swallowed entirely;
  they are now preserved.
- **Lines without `=`** used to raise PHP warnings and store a junk key; they
  are now ignored.
- **Circular `${VAR}` references** used to recurse until the stack overflowed;
  they now resolve to an empty string (keeping surrounding literal text).

### 4. Immutability now also consults `getenv()`

`create()` already refused to overwrite names in `$_ENV` / `$_SERVER`. It now
also checks `getenv()`, so a real environment variable visible only through
`getenv()` (when `variables_order` excludes `E`) is no longer clobbered by a
`.env` file. This only ever *protects* a real value that 2.x might have
overwritten. See
[Loading & Precedence](Loading-and-Precedence.md#immutability--real-environment-variables-win).

### 5. The internal `Lib` class is now `Repository`

The worker class was renamed `Lib` → `Repository`. If you referenced
`InitPHP\DotENV\Lib` directly, it still works — `Lib` is registered as a
**deprecated alias** of `Repository` — but you should switch:

```php
// before
$env = new \InitPHP\DotENV\Lib();
// after
$env = new \InitPHP\DotENV\Repository();
```

The facade `DotENV` and the `env()` helper are unchanged.

## New in 3.0 (optional)

You don't have to use these, but they're available:

- **`DotENV::flush()` / `DotENV::reset()`** and **`Repository::flush()`** —
  unload what was loaded; ideal for [tests](Testing.md) and workers.
- **`DotENV::instance()`** — access the shared `Repository`.
- An optional leading **`export `** on a key is now stripped.

## Upgrade checklist

- [ ] Project runs on PHP 8.2+ (or pin `^2.0`).
- [ ] Audit reads of numeric-looking values that are really identifiers
      (ZIP codes, phone numbers, ordering ids) — they are strings now.
- [ ] Remove any workarounds for the parsing bugs listed above.
- [ ] Replace `InitPHP\DotENV\Lib` with `InitPHP\DotENV\Repository`.
- [ ] (Optional) Use `flush()` / `reset()` to isolate tests.

## Next steps

- [Value Types & Coercion](Value-Types-and-Coercion.md) — the new typing rules
- [API Reference](API-Reference.md) — the full surface
- The repository [`CHANGELOG.md`](https://github.com/InitPHP/DotENV/blob/main/CHANGELOG.md)
