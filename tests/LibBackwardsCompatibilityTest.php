<?php

declare(strict_types=1);

namespace InitPHP\DotENV\Tests;

use InitPHP\DotENV\Lib;
use InitPHP\DotENV\Repository;

use function class_exists;

/**
 * Locks the pre-3.0 `Lib` class name in place as an alias of {@see Repository}
 * so existing consumers that referenced it keep working.
 */
final class LibBackwardsCompatibilityTest extends DotEnvTestCase
{
    public function testLegacyLibClassResolvesToRepository(): void
    {
        self::assertTrue(class_exists(Lib::class));

        $lib = new Lib();

        self::assertInstanceOf(Repository::class, $lib);
    }

    public function testLegacyLibInstanceLoadsAndReads(): void
    {
        $file = $this->writeFile("LEGACY=works\n");

        $lib = new Lib();
        $lib->create($file);

        self::assertSame('works', $lib->get('LEGACY'));
    }
}
