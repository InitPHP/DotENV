<?php

declare(strict_types=1);

namespace InitPHP\DotENV\Tests;

use InitPHP\DotENV\Repository;

final class ParsingTest extends DotEnvTestCase
{
    public function testIgnoresCommentLines(): void
    {
        $repo = $this->load(<<<ENV
            # hash comment
            ; semicolon comment
            // slash comment
            REAL=value
            ENV);

        self::assertSame('value', $repo->get('REAL'));
        self::assertNull($repo->get('# hash comment'));
    }

    public function testIgnoresBlankLines(): void
    {
        $repo = $this->load("A=1\n\n\nB=2\n");

        self::assertSame(1, $repo->get('A'));
        self::assertSame(2, $repo->get('B'));
    }

    public function testTrimsWhitespaceAroundKeyAndValue(): void
    {
        $repo = $this->load("  SPACED_KEY   =   spaced value   \n");

        self::assertSame('spaced value', $repo->get('SPACED_KEY'));
    }

    public function testStripsDoubleQuotes(): void
    {
        $repo = $this->load('QUOTED="hello world"' . "\n");

        self::assertSame('hello world', $repo->get('QUOTED'));
    }

    public function testStripsSingleQuotes(): void
    {
        $repo = $this->load("QUOTED='hello world'\n");

        self::assertSame('hello world', $repo->get('QUOTED'));
    }

    public function testStripsQuotesEvenWithSpacesAroundEquals(): void
    {
        // Regression: the quote check used to run against the untrimmed
        // value, so `KEY = "v"` kept its quotes.
        $repo = $this->load('SITE = "http://lvh.me"' . "\n");

        self::assertSame('http://lvh.me', $repo->get('SITE'));
    }

    public function testKeepsHashInsideQuotes(): void
    {
        $repo = $this->load('COLOR="#ffffff"' . "\n");

        self::assertSame('#ffffff', $repo->get('COLOR'));
    }

    public function testPreservesLeadingHashInUnquotedValue(): void
    {
        // Regression: `#ffffff` used to be swallowed entirely as a comment.
        $repo = $this->load("COLOR=#ffffff\n");

        self::assertSame('#ffffff', $repo->get('COLOR'));
    }

    public function testStripsInlineCommentAfterWhitespace(): void
    {
        $repo = $this->load("URL=http://lvh.me # inline comment\n");

        self::assertSame('http://lvh.me', $repo->get('URL'));
    }

    public function testStripsInlineCommentAfterQuotedValue(): void
    {
        $repo = $this->load('TOKEN="abc" # secret' . "\n");

        self::assertSame('abc', $repo->get('TOKEN'));
    }

    public function testDoesNotTreatHashWithoutLeadingSpaceAsComment(): void
    {
        $repo = $this->load("FRAGMENT=path#section\n");

        self::assertSame('path#section', $repo->get('FRAGMENT'));
    }

    public function testIgnoresLinesWithoutEqualsSign(): void
    {
        // Regression: a non-comment line with no '=' raised PHP warnings
        // and defined a junk key.
        $repo = $this->load("VALID=1\nLINE_WITHOUT_EQUALS\nOTHER=2\n");

        self::assertSame(1, $repo->get('VALID'));
        self::assertSame(2, $repo->get('OTHER'));
        self::assertNull($repo->get('LINE_WITHOUT_EQUALS'));
    }

    public function testStripsExportPrefix(): void
    {
        $repo = $this->load("export EXPORTED=shell\n");

        self::assertSame('shell', $repo->get('EXPORTED'));
        self::assertNull($repo->get('export EXPORTED'));
    }

    public function testKeyWithEqualsInValue(): void
    {
        $repo = $this->load("DSN=mysql:host=localhost;dbname=app\n");

        self::assertSame('mysql:host=localhost;dbname=app', $repo->get('DSN'));
    }

    private function load(string $contents): Repository
    {
        $repo = new Repository();
        $repo->create($this->writeFile($contents));

        return $repo;
    }
}
