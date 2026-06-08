<?php

declare(strict_types=1);

namespace InitPHP\DotENV\Tests;

use InitPHP\DotENV\Exceptions\DotENVException;
use InitPHP\DotENV\Repository;

final class PhpEnvFileTest extends DotEnvTestCase
{
    public function testLoadsNativeTypesFromPhpFile(): void
    {
        $repo = $this->loadPhp(<<<'PHP'
            <?php
            return [
                'STR'  => 'string value',
                'BOOL' => true,
                'INT'  => 13,
                'NULL' => null,
            ];
            PHP);

        self::assertSame('string value', $repo->get('STR'));
        self::assertTrue($repo->get('BOOL'));
        self::assertSame(13, $repo->get('INT'));
        self::assertNull($repo->get('NULL'));
    }

    public function testInterpolatesStringValuesFromPhpFile(): void
    {
        $repo = $this->loadPhp(<<<'PHP'
            <?php
            return [
                'SITE_URL' => 'http://lvh.me',
                'PAGE_URL' => '${SITE_URL}/page',
            ];
            PHP);

        self::assertSame('http://lvh.me/page', $repo->get('PAGE_URL'));
    }

    public function testNonArrayReturnThrows(): void
    {
        $file = $this->writeFile('<?php return "not an array";', '.env.php');

        $this->expectException(DotENVException::class);
        $this->expectExceptionMessage('must return an associative array');

        (new Repository())->create($file);
    }

    public function testNonArrayReturnIsSilentWhenDebugDisabled(): void
    {
        $file = $this->writeFile('<?php return 42;', '.env.php');
        (new Repository())->create($file, false);

        self::assertNull((new Repository())->get('ANYTHING'));
    }

    public function testLoadsPhpFileFromDirectory(): void
    {
        $dir = $this->writeDir("<?php return ['FROM_DIR' => 'yes'];", '.env.php');
        $repo = new Repository();
        $repo->create($dir);

        self::assertSame('yes', $repo->get('FROM_DIR'));
    }

    private function loadPhp(string $contents): Repository
    {
        $repo = new Repository();
        $repo->create($this->writeFile($contents, '.env.php'));

        return $repo;
    }
}
