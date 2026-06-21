<?php

/**
 * DotENV.php
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

/**
 * Static facade over a shared {@see Repository} instance.
 *
 * ```php
 * DotENV::create('/path/to/project');
 * $url = DotENV::get('SITE_URL');
 * ```
 *
 * @method static void  create(string $path, bool $debug = true)
 * @method static mixed get(string $name, mixed $default = null)
 * @method static mixed env(string $name, mixed $default = null)
 * @method static void  flush()
 * @method static \InitPHP\DotENV\Drift\DriftReport drift(string|array<int|string, mixed> $reference, array{extra?: bool, empty?: bool} $options = [])
 * @method static void assertNoDrift(string|array<int|string, mixed> $reference, array{extra?: bool, empty?: bool} $options = [])
 *
 * @see Repository
 */
final class DotENV
{
    /** @var Repository|null The shared repository instance. */
    private static ?Repository $instance = null;

    /**
     * Returns the shared repository, creating it on first use.
     *
     * @return Repository
     */
    public static function instance(): Repository
    {
        if (self::$instance === null) {
            self::$instance = new Repository();
        }

        return self::$instance;
    }

    /**
     * Flushes and drops the shared repository instance.
     *
     * After this call the next facade call builds a fresh repository. Useful
     * in tests and long-running workers. Pre-existing environment variables
     * are left untouched.
     *
     * @return void
     */
    public static function reset(): void
    {
        if (self::$instance !== null) {
            self::$instance->flush();
        }
        self::$instance = null;
    }

    /**
     * Forwards instance calls to the shared repository.
     *
     * @param string            $name
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        return self::instance()->{$name}(...$arguments);
    }

    /**
     * Forwards static calls to the shared repository.
     *
     * @param string            $name
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return self::instance()->{$name}(...$arguments);
    }
}
