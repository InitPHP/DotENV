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

    public function testSelfReferenceWithSurroundingTextResolvesOnce(): void
    {
        // Regression: the cyclic value used to be double-counted ("-tail-tail")
        // because the recursive lookup re-resolved the whole value and cached
        // the partial result.
        $repo = $this->load("A=\${A}-tail\nB=pre-\${B}-post\n");

        self::assertSame('-tail', $repo->get('A'));
        self::assertSame('pre--post', $repo->get('B'));
    }

    public function testRepeatedReadOfCyclicValueIsStable(): void
    {
        $repo = $this->load("A=\${A}-tail\n");

        self::assertSame($repo->get('A'), $repo->get('A'));
    }

    public function testWhitespaceInsideBracesIsIgnored(): void
    {
        $repo = $this->load("NAME=world\nGREETING=hello \${ NAME }\n");

        self::assertSame('hello world', $repo->get('GREETING'));
    }

    public function testEmptyBracesAreLeftLiteral(): void
    {
        // `${}` has nothing between the braces, so the reference pattern does
        // not match it and it is kept verbatim.
        $repo = $this->load('VALUE=a${}b' . "\n");

        self::assertSame('a${}b', $repo->get('VALUE'));
    }

    public function testWhitespaceOnlyBracesResolveToEmpty(): void
    {
        // `${ }` matches the pattern but the name trims to empty.
        $repo = $this->load('VALUE=a${ }b' . "\n");

        self::assertSame('ab', $repo->get('VALUE'));
    }

    public function testScalarReferencesUsePhpStringCast(): void
    {
        // Documented behaviour: a reference is inserted via PHP's string cast,
        // then the whole value is re-coerced. So a referenced `true` becomes
        // "1" (and is then coerced to int 1); a referenced `false` becomes "".
        $repo = $this->load("FLAG=true\nWRAP=\${FLAG}\nOFF=false\nOFF_WRAP=x\${OFF}y\n");

        self::assertSame(1, $repo->get('WRAP'));
        self::assertSame('xy', $repo->get('OFF_WRAP'));
    }

    private function load(string $contents): Repository
    {
        $repo = new Repository();
        $repo->create($this->writeFile($contents));

        return $repo;
    }
}
