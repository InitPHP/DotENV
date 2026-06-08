<?php

declare(strict_types=1);

namespace InitPHP\DotENV\Tests;

use InitPHP\DotENV\Repository;

final class PriorityTest extends DotEnvTestCase
{
    public function testEnvTakesPriorityOverServer(): void
    {
        $_ENV['PRIORITY'] = 'from_env';
        $_SERVER['PRIORITY'] = 'from_server';

        self::assertSame('from_env', (new Repository())->get('PRIORITY'));
    }

    public function testServerTakesPriorityOverGetenv(): void
    {
        unset($_ENV['PRIORITY_SG']);
        $_SERVER['PRIORITY_SG'] = 'from_server';
        $this->putenvValue('PRIORITY_SG', 'from_getenv');

        self::assertSame('from_server', (new Repository())->get('PRIORITY_SG'));
    }

    public function testFallsBackToGetenv(): void
    {
        unset($_ENV['ONLY_GETENV'], $_SERVER['ONLY_GETENV']);
        $this->putenvValue('ONLY_GETENV', 'here');

        self::assertSame('here', (new Repository())->get('ONLY_GETENV'));
    }

    public function testReturnsDefaultWhenMissing(): void
    {
        self::assertSame('fallback', (new Repository())->get('TOTALLY_UNSET_KEY', 'fallback'));
    }

    public function testDefaultIsNullByDefault(): void
    {
        self::assertNull((new Repository())->get('TOTALLY_UNSET_KEY'));
    }

    public function testCreateDoesNotOverwriteExistingEnvValue(): void
    {
        $_ENV['EXISTING_KEY'] = 'original';
        $file = $this->writeFile("EXISTING_KEY=replacement\n");

        (new Repository())->create($file);

        self::assertSame('original', $_ENV['EXISTING_KEY']);
        self::assertSame('original', (new Repository())->get('EXISTING_KEY'));
    }

    public function testCreatePopulatesAllThreeStores(): void
    {
        $file = $this->writeFile("WRITTEN=value\n");
        (new Repository())->create($file);

        self::assertSame('value', $_ENV['WRITTEN']);
        self::assertSame('value', $_SERVER['WRITTEN']);
        self::assertSame('value', getenv('WRITTEN'));
    }
}
