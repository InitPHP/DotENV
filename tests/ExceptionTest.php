<?php

declare(strict_types=1);

namespace InitPHP\DotENV\Tests;

use InitPHP\DotENV\Exceptions\DotENVException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ExceptionTest extends TestCase
{
    public function testExtendsInvalidArgumentException(): void
    {
        $parent = (new ReflectionClass(DotENVException::class))->getParentClass();

        self::assertNotFalse($parent);
        self::assertSame(InvalidArgumentException::class, $parent->getName());
    }

    public function testIsCatchableAsInvalidArgumentException(): void
    {
        $caught = null;
        try {
            throw new DotENVException('boom');
        } catch (InvalidArgumentException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(DotENVException::class, $caught);
        self::assertSame('boom', $caught->getMessage());
    }
}
