<?php

declare(strict_types=1);

namespace InitPHP\DotENV\Tests;

use InitPHP\DotENV\Repository;

final class ValueTypeTest extends DotEnvTestCase
{
    /**
     * @dataProvider keywordProvider
     */
    public function testKeywordsAreCoerced(string $literal, mixed $expected): void
    {
        $repo = $this->load("VALUE={$literal}");

        self::assertSame($expected, $repo->get('VALUE'));
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function keywordProvider(): array
    {
        return [
            'true'        => ['true', true],
            'false'       => ['false', false],
            'null'        => ['null', null],
            'empty'       => ['empty', ''],
            'TRUE upper'  => ['TRUE', true],
            'False mixed' => ['False', false],
        ];
    }

    public function testEmptyAssignmentBecomesEmptyString(): void
    {
        $repo = $this->load('BLANK=');

        self::assertSame('', $repo->get('BLANK'));
    }

    public function testIntegerCoercion(): void
    {
        $repo = $this->load('PORT=8080');

        self::assertSame(8080, $repo->get('PORT'));
    }

    public function testNegativeIntegerCoercion(): void
    {
        $repo = $this->load('OFFSET=-42');

        self::assertSame(-42, $repo->get('OFFSET'));
    }

    public function testFloatCoercion(): void
    {
        $repo = $this->load('PI=3.14');

        self::assertSame(3.14, $repo->get('PI'));
    }

    /**
     * @dataProvider preservedStringProvider
     */
    public function testNonRoundTrippingNumbersStayStrings(string $literal): void
    {
        // Regression: aggressive intval()/floatval() used to mangle these.
        $repo = $this->load("VALUE={$literal}");

        self::assertSame($literal, $repo->get('VALUE'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function preservedStringProvider(): array
    {
        return [
            'leading zero'      => ['007'],
            'leading plus'      => ['+905551112233'],
            'beyond int max'    => ['99999999999999999999'],
            'scientific'        => ['1e3'],
            'phone with dashes' => ['555-12-34'],
        ];
    }

    private function load(string $contents): Repository
    {
        $repo = new Repository();
        $repo->create($this->writeFile($contents));

        return $repo;
    }
}
