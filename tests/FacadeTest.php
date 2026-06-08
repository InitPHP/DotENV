<?php

declare(strict_types=1);

namespace InitPHP\DotENV\Tests;

use InitPHP\DotENV\DotENV;
use InitPHP\DotENV\Repository;

final class FacadeTest extends DotEnvTestCase
{
    public function testStaticCallsAreForwardedToTheRepository(): void
    {
        $file = $this->writeFile("FACADE_KEY=facade_value\n");
        DotENV::create($file);

        self::assertSame('facade_value', DotENV::get('FACADE_KEY'));
        self::assertSame('facade_value', DotENV::env('FACADE_KEY'));
    }

    public function testGetReturnsDefaultThroughFacade(): void
    {
        self::assertSame('def', DotENV::get('MISSING_FACADE_KEY', 'def'));
    }

    public function testInstanceReturnsSharedRepository(): void
    {
        $first = DotENV::instance();
        $second = DotENV::instance();

        self::assertInstanceOf(Repository::class, $first);
        self::assertSame($first, $second);
    }

    public function testResetDropsTheSharedInstance(): void
    {
        $before = DotENV::instance();
        DotENV::reset();
        $after = DotENV::instance();

        self::assertNotSame($before, $after);
    }

    public function testFlushRemovesValuesThatWereLoaded(): void
    {
        $file = $this->writeFile("FLUSH_ME=value\n");
        DotENV::create($file);
        self::assertSame('value', DotENV::get('FLUSH_ME'));

        DotENV::flush();

        self::assertArrayNotHasKey('FLUSH_ME', $_ENV);
        self::assertArrayNotHasKey('FLUSH_ME', $_SERVER);
        self::assertFalse(getenv('FLUSH_ME'));
    }

    public function testInstanceCallForwardingThroughMagicCall(): void
    {
        $file = $this->writeFile("MAGIC_CALL=ok\n");
        $facade = new DotENV();
        $facade->create($file);

        self::assertSame('ok', $facade->get('MAGIC_CALL'));
    }
}
