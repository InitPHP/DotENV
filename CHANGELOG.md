# Changelog

All notable changes to `initphp/dotenv` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0]

A maintenance-focused major release: real bug fixes, corrected value
semantics, a modern PHP 8 codebase, full tests, static analysis, CI and
documentation. The public API (`DotENV::create`, `get`, `env`, and the global
`env()` helper) is unchanged.

### Requirements

- **Raised the minimum PHP version to 8.0** (was 5.6). The whole library now
  uses `declare(strict_types=1)` and native type declarations.

### Fixed

- **Directory-path loading.** `create('/some/dir')` was broken by a
  `rtrim($dir . '\\/')` typo and always threw "could not be found in the
  directory". It now correctly finds `.env` / `.env.php` inside a directory.
- **Multiple `${VAR}` references on one line.** A greedy pattern matched
  across two references and resolved the value to an empty string. `${A}:${B}`
  now expands both.
- **Quoted values with whitespace around `=`.** `KEY = "value"` kept its
  quotes because the quote check ran on the untrimmed value. Quotes are now
  stripped regardless of spacing.
- **Values beginning with `#`.** A value such as `#ffffff` was swallowed
  entirely as a comment. An inline comment now only starts at a `#` preceded
  by whitespace, so leading-`#` values survive.
- **Lines without `=`.** A non-comment line with no `=` raised PHP 8
  "undefined array key" / "passing null" warnings and stored a junk key. Such
  lines are now ignored.
- **Circular `${VAR}` references** (`A=${A}`, or `B=${C}` / `C=${B}`) recursed
  until the stack overflowed. They now resolve to an empty string.

### Changed

- **Loss-free numeric coercion.** Numbers are coerced to `int`/`float` only
  when the conversion round-trips exactly. `007`, `+905551112233`,
  values beyond `PHP_INT_MAX`, and `1e3` now stay strings instead of being
  silently mangled. `13` and `3.14` still coerce as before.
- **Immutability check** now uses `array_key_exists()` instead of `isset()`,
  so a pre-existing name whose value is `null` is still respected.
- Renamed the internal worker class `Lib` to **`Repository`**. `Lib` remains
  available as a deprecated alias.
- Documentation, comments and PHPDoc are now in English and match the actual
  behaviour.

### Added

- **`Repository::flush()`** and **`DotENV::flush()` / `DotENV::reset()`** to
  unload values and reset the shared instance (useful in tests and workers).
- **`DotENV::instance()`** to access the shared repository.
- An optional leading **`export `** prefix on a key is now stripped.
- A full **PHPUnit** test suite, **PHPStan** (max level) configuration,
  **PHP-CS-Fixer** configuration, a **GitHub Actions CI** workflow (PHP
  8.0–8.4), and a **`docs/`** directory.

## [2.0.1]

- Inline comment parsing fix.

## [2.0]

- Support for `.env.php` files and `${VAR}` interpolation.

## [1.0]

- Initial release.

[3.0.0]: https://github.com/InitPHP/DotENV/releases/tag/3.0.0
[2.0.1]: https://github.com/InitPHP/DotENV/releases/tag/2.0.1
[2.0]: https://github.com/InitPHP/DotENV/releases/tag/2.0
[1.0]: https://github.com/InitPHP/DotENV/releases/tag/1.0
