<?php

declare(strict_types=1);

namespace InitPHP\DotENV\Tests;

use InitPHP\DotENV\DotENV;
use InitPHP\DotENV\Drift\DriftReport;
use InitPHP\DotENV\Exceptions\DotENVException;
use InitPHP\DotENV\Exceptions\DriftException;
use InitPHP\DotENV\Repository;
use InvalidArgumentException;

use function env_drift;

final class DriftTest extends DotEnvTestCase
{
    public function testMissingKeyAgainstRequiredList(): void
    {
        $repo = $this->load("DB_HOST=localhost\n");

        $report = $repo->drift(['DB_HOST', 'DB_PORT']);

        self::assertTrue($report->hasDrift());
        self::assertSame(['DB_PORT'], $report->getMissing());
        self::assertSame([], $report->getExtra());
        self::assertSame([], $report->getEmpty());
    }

    public function testMissingKeyAgainstReferenceFile(): void
    {
        $repo = $this->load("APP_NAME=DotENV\n");
        $example = $this->writeFile("APP_NAME=\nAPP_KEY=\n", '.env.example');

        $report = $repo->drift($example);

        self::assertSame(['APP_KEY'], $report->getMissing());
    }

    public function testNoDriftYieldsEmptyReport(): void
    {
        $repo = $this->load("A=1\nB=2\n");

        $report = $repo->drift(['A', 'B']);

        self::assertFalse($report->hasDrift());
        self::assertTrue($report->isEmpty());
        self::assertSame(0, $report->count());
        self::assertSame('No environment drift detected.', (string) $report);
    }

    public function testExtraKeyIsIgnoredByDefault(): void
    {
        $repo = $this->load("WANTED=1\nUNDOCUMENTED=2\n");

        $report = $repo->drift(['WANTED']);

        self::assertFalse($report->hasDrift());
        self::assertSame([], $report->getExtra());
    }

    public function testExtraKeyDetectedWhenEnabled(): void
    {
        $repo = $this->load("WANTED=1\nUNDOCUMENTED=2\n");

        $report = $repo->drift(['WANTED'], ['extra' => true]);

        self::assertTrue($report->hasDrift());
        self::assertSame(['UNDOCUMENTED'], $report->getExtra());
        self::assertSame([], $report->getMissing());
    }

    public function testEmptyRequiredKeyIgnoredByDefault(): void
    {
        $repo = $this->load("FILLED=value\nBLANK=empty\n");

        $report = $repo->drift(['FILLED', 'BLANK']);

        self::assertFalse($report->hasDrift());
        self::assertSame([], $report->getEmpty());
    }

    public function testEmptyRequiredKeyDetectedWhenEnabled(): void
    {
        $repo = $this->load("FILLED=value\nBLANK=empty\n");

        $report = $repo->drift(['FILLED', 'BLANK'], ['empty' => true]);

        self::assertTrue($report->hasDrift());
        self::assertSame(['BLANK'], $report->getEmpty());
        self::assertSame([], $report->getMissing());
    }

    public function testAssociativeArrayReferenceUsesKeys(): void
    {
        $repo = $this->load("DB_HOST=localhost\n");

        $report = $repo->drift(['DB_HOST' => 'localhost', 'DB_PORT' => 3306]);

        self::assertSame(['DB_PORT'], $report->getMissing());
    }

    public function testAssertNoDriftPassesWhenClean(): void
    {
        $repo = $this->load("A=1\n");

        // Must not throw; the explicit assertion confirms a clean report so
        // the test still asserts something (no risky/no-assertion warning).
        $repo->assertNoDrift(['A']);

        self::assertFalse($repo->drift(['A'])->hasDrift());
    }

    public function testAssertNoDriftThrowsOnDrift(): void
    {
        $repo = $this->load("A=1\n");

        $this->expectException(DriftException::class);
        $this->expectExceptionMessage('Missing');

        $repo->assertNoDrift(['A', 'B']);
    }

    public function testDriftExceptionCarriesReportAndIsCatchableAsDotENVException(): void
    {
        $repo = $this->load("A=1\n");

        $caught = null;
        try {
            $repo->assertNoDrift(['A', 'MISSING_ONE']);
        } catch (DotENVException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(DriftException::class, $caught);
        self::assertInstanceOf(InvalidArgumentException::class, $caught);
        self::assertSame(['MISSING_ONE'], $caught->getReport()->getMissing());
    }

    public function testMissingReferenceFilePathThrows(): void
    {
        $repo = new Repository();

        $this->expectException(DotENVException::class);
        $this->expectExceptionMessage('could not be found');

        $repo->drift('/no/such/path/.env.example');
    }

    public function testReferenceFileWithArbitraryNameIsAccepted(): void
    {
        // A reference file need not be named `.env`/`.env.php` — the loadable
        // filename allow-list does not apply to references.
        $repo = $this->load("PRESENT=1\n");
        $example = $this->writeFile("PRESENT=\nABSENT=\n", '.env.sample');

        $report = $repo->drift($example);

        self::assertSame(['ABSENT'], $report->getMissing());
    }

    public function testDirectoryReferenceResolvesToEnvFileInside(): void
    {
        $repo = $this->load("HOST=localhost\n");
        $dir = $this->writeDir("HOST=\nPORT=\n");

        $report = $repo->drift($dir);

        self::assertSame(['PORT'], $report->getMissing());
    }

    public function testDirectoryReferenceWithoutEnvFileThrows(): void
    {
        $repo = new Repository();

        $this->expectException(DotENVException::class);
        $this->expectExceptionMessage('could not be found in the directory');

        $repo->drift($this->makeDir());
    }

    public function testPhpReferenceFileUsesItsKeys(): void
    {
        $repo = $this->load("A=1\n");
        $php = $this->writeFile("<?php return ['A' => 1, 'B' => 2];", '.env.php');

        $report = $repo->drift($php);

        self::assertSame(['B'], $report->getMissing());
    }

    public function testPhpReferenceFileNotReturningArrayThrows(): void
    {
        $repo = new Repository();
        $php = $this->writeFile('<?php return "not-an-array";', '.env.php');

        $this->expectException(DotENVException::class);
        $this->expectExceptionMessage('must return an associative array');

        $repo->drift($php);
    }

    public function testUnreadableReferenceFileThrows(): void
    {
        $file = $this->writeFile("KEY=\n", '.env.example');
        chmod($file, 0000);
        if (is_readable($file)) {
            self::markTestSkipped('Filesystem does not enforce read permissions here (running as root?).');
        }

        $this->expectException(DotENVException::class);
        $this->expectExceptionMessage('could not be read');

        (new Repository())->drift($file);
    }

    public function testReportToArrayExposesEveryBucket(): void
    {
        $report = new DriftReport(['M'], ['E'], ['B']);

        self::assertSame(
            ['missing' => ['M'], 'extra' => ['E'], 'empty' => ['B']],
            $report->toArray()
        );
        self::assertSame(3, $report->count());
    }

    public function testReportToStringListsEveryBucket(): void
    {
        $report = new DriftReport(['M'], ['E'], ['B']);
        $text = (string) $report;

        self::assertStringContainsString('Missing', $text);
        self::assertStringContainsString('Empty', $text);
        self::assertStringContainsString('Extra', $text);
        self::assertStringContainsString('M', $text);
    }

    public function testDriftIsReadOnlyAndDoesNotDefineKeys(): void
    {
        $repo = $this->load("A=1\n");

        $repo->drift(['UNRELATED_KEY']);

        self::assertNull($repo->get('UNRELATED_KEY'));
        self::assertArrayNotHasKey('UNRELATED_KEY', $_ENV);
        self::assertArrayNotHasKey('UNRELATED_KEY', $_SERVER);
        self::assertFalse(getenv('UNRELATED_KEY'));
    }

    public function testFacadeForwardsDrift(): void
    {
        DotENV::create($this->writeFile("A=1\n"));

        $report = DotENV::drift(['A', 'B']);

        self::assertSame(['B'], $report->getMissing());
    }

    public function testFacadeAssertNoDriftThrows(): void
    {
        DotENV::create($this->writeFile("A=1\n"));

        $this->expectException(DriftException::class);

        DotENV::assertNoDrift(['A', 'B']);
    }

    public function testGlobalHelperForwardsDrift(): void
    {
        DotENV::create($this->writeFile("A=1\n"));

        $report = env_drift(['A', 'B']);

        self::assertInstanceOf(DriftReport::class, $report);
        self::assertSame(['B'], $report->getMissing());
    }

    /**
     * Loads the given `.env` contents into a fresh repository and returns it.
     */
    private function load(string $contents): Repository
    {
        $repo = new Repository();
        $repo->create($this->writeFile($contents));

        return $repo;
    }
}
