<?php

declare(strict_types=1);

namespace InitPHP\DotENV\Tests;

use InitPHP\DotENV\Exceptions\DotENVException;
use InitPHP\DotENV\Repository;

final class CreateTest extends DotEnvTestCase
{
    public function testLoadsEnvFileByFilePath(): void
    {
        $file = $this->writeFile("APP_NAME=DotENV\n");
        $repo = new Repository();
        $repo->create($file);

        self::assertSame('DotENV', $repo->get('APP_NAME'));
    }

    public function testLoadsEnvFileByDirectoryPath(): void
    {
        // Regression: directory-path resolution used to be broken by a
        // `rtrim($dir . '\\/')` typo and always threw.
        $dir = $this->writeDir("APP_ENV=production\n");
        $repo = new Repository();
        $repo->create($dir);

        self::assertSame('production', $repo->get('APP_ENV'));
    }

    public function testLoadsEnvFileByDirectoryPathWithTrailingSlash(): void
    {
        $dir = $this->writeDir("WITH_SLASH=yes\n");
        $repo = new Repository();
        $repo->create($dir . '/');

        self::assertSame('yes', $repo->get('WITH_SLASH'));
    }

    public function testPrefersDotEnvOverDotEnvPhpInTheSameDirectory(): void
    {
        $dir = $this->makeDir();
        $this->writeInto($dir, '.env', "SOURCE=env\n");
        $this->writeInto($dir, '.env.php', "<?php return ['SOURCE' => 'php'];");

        $repo = new Repository();
        $repo->create($dir);

        self::assertSame('env', $repo->get('SOURCE'));
    }

    public function testMissingFileThrowsByDefault(): void
    {
        $this->expectException(DotENVException::class);
        $this->expectExceptionMessage('could not be found');

        (new Repository())->create('/no/such/path/.env');
    }

    public function testMissingFileIsSilentWhenDebugDisabled(): void
    {
        (new Repository())->create('/no/such/path/.env', false);

        self::assertNull((new Repository())->get('ANYTHING_AT_ALL'));
    }

    public function testDirectoryWithoutEnvFileThrows(): void
    {
        $this->expectException(DotENVException::class);
        $this->expectExceptionMessage('could not be found in the directory');

        (new Repository())->create($this->makeDir());
    }

    public function testRejectsFileWithUnacceptedName(): void
    {
        $path = $this->writeFile("X=1\n", 'config.txt');

        $this->expectException(DotENVException::class);
        $this->expectExceptionMessage('must be a ".env" or ".env.php"');

        (new Repository())->create($path);
    }

    public function testRejectsFileWithUnacceptedNameSilentlyWhenDebugDisabled(): void
    {
        $path = $this->writeFile("X=1\n", 'config.txt');
        (new Repository())->create($path, false);

        self::assertNull((new Repository())->get('X'));
    }

    public function testDirectoryWithoutEnvFileIsSilentWhenDebugDisabled(): void
    {
        (new Repository())->create($this->makeDir(), false);

        self::assertNull((new Repository())->get('NOTHING_HERE'));
    }

    public function testUnreadableFileThrows(): void
    {
        $file = $this->makeUnreadable("UNREADABLE=1\n");

        $this->expectException(DotENVException::class);
        $this->expectExceptionMessage('could not be read');

        (new Repository())->create($file);
    }

    public function testUnreadableFileIsSilentWhenDebugDisabled(): void
    {
        $file = $this->makeUnreadable("UNREADABLE=1\n");
        (new Repository())->create($file, false);

        self::assertNull((new Repository())->get('UNREADABLE'));
    }

    private function makeUnreadable(string $contents): string
    {
        $file = $this->writeFile($contents);
        chmod($file, 0000);
        if (is_readable($file)) {
            self::markTestSkipped('Filesystem does not enforce read permissions here (running as root?).');
        }

        return $file;
    }
}
