<?php

declare(strict_types=1);

namespace InitPHP\DotENV\Tests;

use InitPHP\DotENV\Repository;

final class InterpolationTest extends DotEnvTestCase
{
    public function testSingleVariableInterpolation(): void
    {
        $repo = $this->load("SITE_URL=http://lvh.me\nPAGE_URL=\${SITE_URL}/page\n");

        self::assertSame('http://lvh.me/page', $repo->get('PAGE_URL'));
    }

    public function testMultipleVariablesOnOneLine(): void
    {
        // Regression: the greedy `\${(.+)}` pattern matched across both
        // references and resolved to an empty string.
        $repo = $this->load("HOST=localhost\nPORT=8080\nADDR=\${HOST}:\${PORT}\n");

        self::assertSame('localhost:8080', $repo->get('ADDR'));
    }

    public function testNestedInterpolation(): void
    {
        $repo = $this->load("A=root\nB=\${A}/b\nC=\${B}/c\n");

        self::assertSame('root/b/c', $repo->get('C'));
    }

    public function testMissingVariableResolvesToEmptyString(): void
    {
        $repo = $this->load("VALUE=\${DOES_NOT_EXIST}suffix\n");

        self::assertSame('suffix', $repo->get('VALUE'));
    }

    public function testSelfReferenceDoesNotRecurseForever(): void
    {
        // Regression: `A=${A}` used to recurse until the stack overflowed.
        $repo = $this->load("A=\${A}\n");

        self::assertSame('', $repo->get('A'));
    }

    public function testMutualReferenceDoesNotRecurseForever(): void
    {
        $repo = $this->load("B=\${C}\nC=\${B}\n");

        self::assertSame('', $repo->get('B'));
        self::assertSame('', $repo->get('C'));
    }

    public function testInterpolationStopsAtClosingBrace(): void
    {
        $repo = $this->load("NAME=world\nGREETING=hello-\${NAME}-!\n");

        self::assertSame('hello-world-!', $repo->get('GREETING'));
    }

    private function load(string $contents): Repository
    {
        $repo = new Repository();
        $repo->create($this->writeFile($contents));

        return $repo;
    }
}
