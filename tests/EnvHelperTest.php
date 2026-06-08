<?php

declare(strict_types=1);

namespace InitPHP\DotENV\Tests;

use InitPHP\DotENV\DotENV;

use function env;

final class EnvHelperTest extends DotEnvTestCase
{
    public function testHelperReturnsLoadedValue(): void
    {
        $file = $this->writeFile("HELPER_KEY=helper_value\n");
        DotENV::create($file);

        self::assertSame('helper_value', env('HELPER_KEY'));
    }

    public function testHelperReturnsDefaultForMissingKey(): void
    {
        self::assertSame('default', env('MISSING_HELPER_KEY', 'default'));
    }

    public function testHelperSharesStateWithFacade(): void
    {
        $file = $this->writeFile("SHARED=42\n");
        DotENV::create($file);

        self::assertSame(42, env('SHARED'));
        self::assertSame(42, DotENV::get('SHARED'));
    }
}
