<?php

declare(strict_types=1);

namespace InitPHP\DotENV\Tests;

use InitPHP\DotENV\DotENV;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function putenv;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Base test case that isolates each test from the global environment.
 *
 * `$_ENV` / `$_SERVER` are snapshotted before every test and restored
 * afterwards, the shared {@see DotENV} facade is reset, and any `putenv()`
 * value introduced during the test is removed. Tests can write throwaway
 * `.env` files with {@see writeFile()} / {@see writeDir()}; the temporary
 * directories are deleted in tear-down.
 */
abstract class DotEnvTestCase extends TestCase
{
    /** @var array<array-key, mixed> */
    private array $envBackup = [];

    /** @var array<array-key, mixed> */
    private array $serverBackup = [];

    /** @var list<string> */
    private array $tempDirs = [];

    /** @var list<string> */
    private array $putenvKeys = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->envBackup = $_ENV;
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        DotENV::reset();

        // Drop any putenv()/$_ENV keys the test introduced so they do not
        // leak into other tests through getenv().
        foreach (array_keys($_ENV) as $key) {
            if (!\array_key_exists($key, $this->envBackup)) {
                putenv((string) $key);
            }
        }

        foreach ($this->putenvKeys as $key) {
            putenv($key);
        }
        $this->putenvKeys = [];

        $_ENV = $this->envBackup;
        $_SERVER = $this->serverBackup;

        foreach ($this->tempDirs as $dir) {
            $this->deleteTree($dir);
        }
        $this->tempDirs = [];

        parent::tearDown();
    }

    /**
     * Sets an environment variable via putenv() and records it for removal
     * during tear-down.
     */
    protected function putenvValue(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $this->putenvKeys[] = $name;
    }

    /**
     * Writes a throwaway env file in a fresh temp directory and returns the
     * full path to the file.
     */
    protected function writeFile(string $contents, string $filename = '.env'): string
    {
        return $this->writeInto($this->makeDir(), $filename, $contents);
    }

    /**
     * Writes a throwaway env file and returns the directory that contains it
     * (for exercising directory-path loading).
     */
    protected function writeDir(string $contents, string $filename = '.env'): string
    {
        $dir = $this->makeDir();
        $this->writeInto($dir, $filename, $contents);

        return $dir;
    }

    /**
     * Writes a file inside an existing directory and returns its full path.
     */
    protected function writeInto(string $dir, string $filename, string $contents): string
    {
        $path = $dir . '/' . $filename;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * Creates an empty temp directory and returns its path.
     */
    protected function makeDir(): string
    {
        $dir = sys_get_temp_dir() . '/dotenv-test-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->deleteTree($path) : unlink($path);
        }
        rmdir($dir);
    }
}
