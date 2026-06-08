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

use InitPHP\DotENV\Exceptions\DotENVException;

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
     * @param string $name    The environment variable name.
     * @param mixed  $default Returned when the variable is not defined.
     * @return mixed
     */
    public function get(string $name, mixed $default = null): mixed
    {
        if (\array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }
        if (\array_key_exists($name, $_ENV)) {
            return $this->cache[$name] = $this->convert($_ENV[$name]);
        }
        if (\array_key_exists($name, $_SERVER)) {
            return $this->cache[$name] = $this->convert($_SERVER[$name]);
        }

        $env = getenv($name);
        if ($env !== false) {
            return $this->cache[$name] = $this->convert($env);
        }

        return $default;
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
     * that already exists in `$_ENV` or `$_SERVER`.
     *
     * @param array<string, mixed> $values
     * @return void
     */
    private function store(array $values): void
    {
        foreach ($values as $key => $value) {
            if (\array_key_exists($key, $_ENV) || \array_key_exists($key, $_SERVER)) {
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
     * Replaces every `${VAR}` reference with the value of `VAR`, guarding
     * against circular references (which resolve to an empty string).
     *
     * @param string $data
     * @return string
     */
    private function interpolate(string $data): string
    {
        $result = preg_replace_callback('/\${([^}]+)}/', function (array $match): string {
            $name = trim($match[1], " \t\n\r\0\x0B\"'");
            if ($name === '' || \in_array($name, $this->resolving, true)) {
                return '';
            }

            $this->resolving[] = $name;
            try {
                $value = $this->get($name);
            } finally {
                array_pop($this->resolving);
            }

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
