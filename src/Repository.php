<?php

/**
 * Repository.php
 *
 * This file is part of InitPHP DotENV.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/DotENV/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\DotENV;

use InitPHP\DotENV\Drift\DriftReport;
use InitPHP\DotENV\Exceptions\DotENVException;
use InitPHP\DotENV\Exceptions\DriftException;

use function array_keys;
use function array_pop;
use function basename;
use function explode;
use function file;
use function is_dir;
use function is_file;
use function is_numeric;
use function is_readable;
use function ltrim;
use function preg_match;
use function preg_replace;
use function preg_replace_callback;
use function putenv;
use function rtrim;
use function str_contains;
use function strncmp;
use function strtolower;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;
use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;

/**
 * Loads `.env` / `.env.php` files into the process environment and reads
 * values back out with type coercion and `${VAR}` interpolation.
 *
 * This is the concrete worker behind the {@see DotENV} facade. It can also
 * be used standalone:
 *
 * ```php
 * $env = new Repository();
 * $env->create('/path/to/project');
 * $debug = $env->get('APP_DEBUG', false);
 * ```
 */
final class Repository
{
    /**
     * Accepted file names, in lookup priority order, when a directory path
     * is given to {@see create()}.
     */
    private const FILENAMES = ['.env', '.env.php'];

    /**
     * Resolved values, keyed by name. Acts as a read cache so a value is
     * coerced/interpolated at most once per name.
     *
     * @var array<string, mixed>
     */
    private array $cache = [];

    /**
     * Names this instance has written into `$_ENV` / `$_SERVER` / `putenv()`.
     * Tracked so {@see flush()} can remove exactly what it added without
     * touching pre-existing environment variables.
     *
     * @var array<string, string>
     */
    private array $loaded = [];

    /**
     * Names currently being interpolated. Used to break circular `${VAR}`
     * references (e.g. `A=${B}`, `B=${A}`) instead of recursing forever.
     *
     * @var list<string>
     */
    private array $resolving = [];

    /**
     * Reads and defines a `.env` or `.env.php` file.
     *
     * If `$path` is a directory, the repository looks for a `.env` file and
     * then a `.env.php` file inside it. Values that already exist in `$_ENV`
     * or `$_SERVER` are never overwritten.
     *
     * @param string $path  Path to a `.env`/`.env.php` file, or to a
     *                       directory that contains one.
     * @param bool   $debug When true (default), problems throw a
     *                       {@see DotENVException}; when false they are
     *                       silently ignored and the method returns.
     * @return void
     * @throws DotENVException
     */
    public function create(string $path, bool $debug = true): void
    {
        $file = $this->resolvePath($path, $debug);
        if ($file === null) {
            return;
        }

        $values = $this->parseFile($file, $debug);
        if ($values === null) {
            return;
        }

        $this->store($values);
    }

    /**
     * Returns an environment value.
     *
     * Resolution order is `$_ENV` → `$_SERVER` → `getenv()`. String values
     * are coerced (`"true"`/`"false"`/`"null"`/`"empty"`, integers and
     * floats) and any `${VAR}` references are interpolated.
     *
     * The resolved value is cached on first read, so later external changes
     * to `$_ENV` / `$_SERVER` / `getenv()` for the same name are not picked
     * up until {@see flush()} clears the cache. A name that is undefined is
     * not cached, so it can still be defined and read later.
     *
     * @param string $name    The environment variable name.
     * @param mixed  $default Returned when the variable is not defined.
     * @return mixed
     */
    public function get(string $name, mixed $default = null): mixed
    {
        if (\array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }
        if (\in_array($name, $this->resolving, true)) {
            // Re-entered while this same name is already being resolved: a
            // circular `${VAR}` reference. Break the cycle with an empty
            // string and do not cache it (the in-progress outer frame owns
            // the cache entry).
            return '';
        }
        if (\array_key_exists($name, $_ENV)) {
            return $this->cache[$name] = $this->resolve($name, $_ENV[$name]);
        }
        if (\array_key_exists($name, $_SERVER)) {
            return $this->cache[$name] = $this->resolve($name, $_SERVER[$name]);
        }

        $env = getenv($name);
        if ($env !== false) {
            return $this->cache[$name] = $this->resolve($name, $env);
        }

        return $default;
    }

    /**
     * Coerces and interpolates a raw value while marking `$name` as being
     * resolved, so a `${VAR}` reference back to it short-circuits in
     * {@see get()} instead of recursing.
     *
     * @param string $name
     * @param mixed  $raw
     * @return mixed
     */
    private function resolve(string $name, mixed $raw): mixed
    {
        $this->resolving[] = $name;
        try {
            return $this->convert($raw);
        } finally {
            array_pop($this->resolving);
        }
    }

    /**
     * Alias of {@see get()}.
     *
     * @param string $name    The environment variable name.
     * @param mixed  $default Returned when the variable is not defined.
     * @return mixed
     */
    public function env(string $name, mixed $default = null): mixed
    {
        return $this->get($name, $default);
    }

    /**
     * Removes every value this instance defined and clears the read cache.
     *
     * Pre-existing environment variables (those present before {@see create()}
     * ran) are left untouched. Primarily a seam for tests and long-running
     * workers that need to reload configuration.
     *
     * @return void
     */
    public function flush(): void
    {
        foreach ($this->loaded as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
        $this->loaded = [];
        $this->cache = [];
        $this->resolving = [];
    }

    /**
     * Compares the loaded environment against a reference and reports drift.
     *
     * The reference declares the keys that *should* be present. It can be:
     *
     * - a **path** to a `.env` / `.env.php` file (typically `.env.example`),
     *   parsed with the same grammar {@see create()} uses, or
     * - an **array of required key names** (`['DB_HOST', 'DB_PORT']`), or
     * - an **associative array** (`['DB_HOST' => 'localhost']`); only its keys
     *   are used for the comparison.
     *
     * A key is considered present in the actual environment when it is defined
     * in `$_ENV`, `$_SERVER`, or `getenv()` — the same three sources
     * {@see get()} reads from.
     *
     * Buckets in the returned {@see DriftReport}:
     *
     * - **missing** — a reference key absent from the actual environment.
     * - **extra** — a key this repository loaded that is not in the reference.
     *   Off by default (usually noise); enable with `['extra' => true]`. Only
     *   keys this instance defined are candidates, so unrelated OS variables
     *   are never reported.
     * - **empty** — a reference key that is present but resolves to an empty
     *   value. Off by default; enable with `['empty' => true]`.
     *
     * This is a read-only diagnostic: it never defines, reads-through, or
     * mutates any environment value, so existing load/parse behaviour is
     * untouched.
     *
     * @param string|array<int|string, mixed> $reference Reference file path or
     *                                                    required-keys list.
     * @param array{extra?: bool, empty?: bool} $options  Opt-in buckets.
     * @return DriftReport
     * @throws DotENVException When a reference *file path* cannot be located,
     *                         read, or parsed. An array reference never throws.
     */
    public function drift(string|array $reference, array $options = []): DriftReport
    {
        $referenceKeys = $this->referenceKeys($reference);
        $withExtra = ($options['extra'] ?? false) === true;
        $withEmpty = ($options['empty'] ?? false) === true;

        // Membership set for O(1) "is this key in the reference?" lookups.
        $referenceSet = [];
        foreach ($referenceKeys as $key) {
            $referenceSet[$key] = true;
        }

        $missing = [];
        $empty = [];
        foreach ($referenceKeys as $key) {
            if (!$this->isDefined($key)) {
                $missing[] = $key;
                continue;
            }
            if ($withEmpty && $this->isEmptyValue($key)) {
                $empty[] = $key;
            }
        }

        $extra = [];
        if ($withExtra) {
            foreach (array_keys($this->loaded) as $key) {
                if (!\array_key_exists($key, $referenceSet)) {
                    $extra[] = $key;
                }
            }
        }

        return new DriftReport($missing, $extra, $empty);
    }

    /**
     * Strict, fail-fast counterpart of {@see drift()} for CI gates.
     *
     * Runs the same comparison and throws a {@see DriftException} (carrying the
     * {@see DriftReport}) the moment any drift is found; returns silently when
     * the environment is clean.
     *
     * @param string|array<int|string, mixed> $reference Reference file path or
     *                                                    required-keys list.
     * @param array{extra?: bool, empty?: bool} $options  Opt-in buckets.
     * @return void
     * @throws DriftException  When drift is detected.
     * @throws DotENVException When a reference file path cannot be loaded.
     */
    public function assertNoDrift(string|array $reference, array $options = []): void
    {
        $report = $this->drift($reference, $options);
        if ($report->hasDrift()) {
            throw new DriftException($report);
        }
    }

    /**
     * Normalises a reference into a de-duplicated list of key names.
     *
     * A string is treated as a file path and parsed with the package's own
     * grammar. A list of strings is taken as required key names. An
     * associative array contributes its keys.
     *
     * @param string|array<int|string, mixed> $reference
     * @return list<string>
     * @throws DotENVException
     */
    private function referenceKeys(string|array $reference): array
    {
        if (\is_string($reference)) {
            return array_keys($this->parseReference($reference));
        }

        $keys = [];
        foreach ($reference as $key => $value) {
            // List form: ['DB_HOST', 'DB_PORT'] — the value *is* the key name.
            // Map form:  ['DB_HOST' => '...']   — the array key is the name.
            $name = \is_int($key) ? $value : $key;
            if (\is_string($name) && $name !== '' && !\array_key_exists($name, $keys)) {
                $keys[$name] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Parses a reference file into a key/value map for drift comparison.
     *
     * Unlike {@see create()}, a reference file is conventionally named
     * `.env.example` / `.env.sample` (anything), so the loadable-filename
     * allow-list is deliberately not enforced — only the keys are read, never
     * defined. A directory argument is resolved to the `.env` / `.env.php`
     * inside it; a `*.php` file is `require`d and must return an array;
     * everything else is parsed with the `.env` line grammar.
     *
     * @param string $path
     * @return array<string, mixed>
     * @throws DotENVException When the file is missing, unreadable, or a
     *                         `.php` reference does not return an array.
     */
    private function parseReference(string $path): array
    {
        if (is_dir($path)) {
            $found = $this->locateInDirectory($path);
            if ($found === null) {
                throw new DotENVException('The reference ".env" or ".env.php" file could not be found in the directory you specified.');
            }
            $path = $found;
        }

        if (!is_file($path)) {
            throw new DotENVException(\sprintf('The reference file "%s" could not be found.', $path));
        }

        if (substr($path, -8) === '.env.php') {
            $values = $this->requirePhpFile($path);
            if (!\is_array($values)) {
                throw new DotENVException('The reference ".env.php" file must return an associative array.');
            }
            $normalised = [];
            foreach ($values as $key => $value) {
                $normalised[(string) $key] = $value;
            }

            return $normalised;
        }

        $lines = is_readable($path)
            ? file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            : false;
        if ($lines === false) {
            throw new DotENVException(\sprintf('The reference file "%s" could not be read.', $path));
        }

        return $this->parseLines($lines);
    }

    /**
     * Whether a name is defined in any of the three sources {@see get()} reads.
     *
     * @param string $name
     * @return bool
     */
    private function isDefined(string $name): bool
    {
        return \array_key_exists($name, $_ENV)
            || \array_key_exists($name, $_SERVER)
            || getenv($name) !== false;
    }

    /**
     * Whether a defined name resolves to an empty value. A value is empty when
     * the coerced result is an empty string, `null`, or `false` — the forms a
     * `.env` file produces for a blank, `empty`, `null` or `false` entry.
     *
     * @param string $name
     * @return bool
     */
    private function isEmptyValue(string $name): bool
    {
        $value = $this->get($name);

        return $value === '' || $value === null || $value === false;
    }

    /**
     * Turns a path into a concrete, accepted file path, or null.
     *
     * @param string $path
     * @param bool   $debug
     * @return string|null  The resolved file path, or null when something is
     *                      wrong and `$debug` is false.
     * @throws DotENVException
     */
    private function resolvePath(string $path, bool $debug): ?string
    {
        if (is_dir($path)) {
            $found = $this->locateInDirectory($path);
            if ($found === null) {
                if ($debug) {
                    throw new DotENVException('The file ".env" or ".env.php" could not be found in the directory you specified.');
                }
                return null;
            }
            $path = $found;
        }

        if (!is_file($path)) {
            if ($debug) {
                throw new DotENVException(\sprintf('The "%s" file could not be found.', $path));
            }
            return null;
        }

        if (!\in_array(basename($path), self::FILENAMES, true)) {
            if ($debug) {
                throw new DotENVException('The file to be loaded must be a ".env" or ".env.php" file.');
            }
            return null;
        }

        return $path;
    }

    /**
     * Finds the first accepted env file inside a directory.
     *
     * @param string $directory
     * @return string|null
     */
    private function locateInDirectory(string $directory): ?string
    {
        $base = rtrim($directory, '\\/') . DIRECTORY_SEPARATOR;
        foreach (self::FILENAMES as $filename) {
            if (is_file($base . $filename)) {
                return $base . $filename;
            }
        }

        return null;
    }

    /**
     * Reads a resolved file into an associative array of raw values.
     *
     * @param string $file
     * @param bool   $debug
     * @return array<string, mixed>|null
     * @throws DotENVException
     */
    private function parseFile(string $file, bool $debug): ?array
    {
        if (basename($file) === '.env.php') {
            $values = $this->requirePhpFile($file);
            if (!\is_array($values)) {
                if ($debug) {
                    throw new DotENVException('The ".env.php" file must return an associative array.');
                }
                return null;
            }

            $normalised = [];
            foreach ($values as $key => $value) {
                $normalised[(string) $key] = $value;
            }

            return $normalised;
        }

        $lines = is_readable($file)
            ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            : false;
        if ($lines === false) {
            if ($debug) {
                throw new DotENVException(\sprintf('The "%s" file could not be read.', $file));
            }
            return null;
        }

        return $this->parseLines($lines);
    }

    /**
     * Parses the lines of a `.env` file into a key/value map.
     *
     * @param array<int, string> $lines
     * @return array<string, string>
     */
    private function parseLines(array $lines): array
    {
        $values = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $this->isComment($line)) {
                continue;
            }
            // A non-comment line with no '=' is malformed; skip it rather
            // than defining a key with an empty/null value.
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = $this->normaliseKey($key);
            // Defensive: the comment and `=` checks above make an empty key
            // unreachable today, but guard anyway so a future grammar change
            // can never define a blank-named variable.
            if ($key === '') {
                continue;
            }

            $values[$key] = $this->normaliseValue($value);
        }

        return $values;
    }

    /**
     * Decides whether a (already trimmed, non-empty) line is a comment.
     *
     * A line is treated as a comment unless it begins with a word character
     * (`A-Z`, `a-z`, `0-9`, `_`) or a hyphen. This makes `#`, `;`, `//` and
     * similar prefixes comments.
     *
     * @param string $line
     * @return bool
     */
    private function isComment(string $line): bool
    {
        return preg_match('/^[\w-]/u', $line) !== 1;
    }

    /**
     * Trims a key and strips an optional leading `export ` shell prefix.
     *
     * @param string $key
     * @return string
     */
    private function normaliseKey(string $key): string
    {
        $key = trim($key);
        if (strncmp($key, 'export ', 7) === 0) {
            $key = ltrim(substr($key, 7));
        }

        return $key;
    }

    /**
     * Normalises a raw value: trims it, strips matching surrounding quotes,
     * and removes a trailing inline comment.
     *
     * A quoted value keeps its contents verbatim (including any `#`). For an
     * unquoted value, an inline comment starts at the first `#` that is
     * preceded by whitespace, so a value such as `#ffffff` is preserved.
     *
     * @param string $value
     * @return string
     */
    private function normaliseValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (($value[0] === '"' || $value[0] === "'")
            && preg_match('/^(["\'])(.*)\1\s*(?:#.*)?$/s', $value, $matches) === 1) {
            return $matches[2];
        }

        $stripped = preg_replace('/\s+#.*$/s', '', $value);

        return rtrim($stripped ?? $value);
    }

    /**
     * Writes parsed values into the environment without overwriting any name
     * that is already defined.
     *
     * A name is considered already defined if it is present in `$_ENV`,
     * `$_SERVER`, or `getenv()` — the same three sources {@see get()} reads
     * from. Checking `getenv()` too matters because a real environment
     * variable can be visible there while absent from the superglobals (when
     * `variables_order` excludes `E`); without it, a `.env` file would
     * silently clobber a genuine environment variable.
     *
     * @param array<string, mixed> $values
     * @return void
     */
    private function store(array $values): void
    {
        foreach ($values as $key => $value) {
            if (\array_key_exists($key, $_ENV)
                || \array_key_exists($key, $_SERVER)
                || getenv($key) !== false) {
                continue;
            }

            if (\is_string($value)) {
                putenv(\sprintf('%s=%s', $key, $value));
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            $this->loaded[$key] = $key;
        }
    }

    /**
     * Coerces a raw value to its scalar type and interpolates `${VAR}`
     * references. Non-string values are returned unchanged.
     *
     * @param mixed $data
     * @return mixed
     */
    private function convert(mixed $data): mixed
    {
        if (!\is_string($data)) {
            return $data;
        }

        switch (strtolower($data)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
            case 'empty':
            case '':
                return '';
        }

        return $this->toScalar($this->interpolate($data));
    }

    /**
     * Coerces a numeric string to int or float, but only when the conversion
     * round-trips exactly. This preserves values such as `007`, `+90555…`,
     * `1e3` and integers beyond `PHP_INT_MAX` as strings instead of silently
     * mangling them.
     *
     * @param string $data
     * @return int|float|string
     */
    private function toScalar(string $data): int|float|string
    {
        if (!is_numeric($data)) {
            return $data;
        }
        if ((string) (int) $data === $data) {
            return (int) $data;
        }
        if ((string) (float) $data === $data) {
            return (float) $data;
        }

        return $data;
    }

    /**
     * Replaces every `${VAR}` reference with the value of `VAR`.
     *
     * Cycle handling lives in {@see get()} / {@see resolve()}: a reference
     * back to a name that is already being resolved short-circuits to an
     * empty string. A reference to an undefined or non-scalar value also
     * resolves to an empty string; a scalar is inserted via PHP's string
     * cast (so `true` becomes `"1"`, `false`/`null` become `""`).
     *
     * @param string $data
     * @return string
     */
    private function interpolate(string $data): string
    {
        $result = preg_replace_callback('/\${([^}]+)}/', function (array $match): string {
            $name = trim($match[1], " \t\n\r\0\x0B\"'");
            if ($name === '') {
                return '';
            }

            $value = $this->get($name);

            return \is_scalar($value) ? (string) $value : '';
        }, $data);

        return $result ?? $data;
    }

    /**
     * Includes a `.env.php` file and returns whatever it produces.
     *
     * @param string $file
     * @return mixed
     */
    private function requirePhpFile(string $file): mixed
    {
        return require $file;
    }
}
