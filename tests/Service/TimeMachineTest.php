<?php

namespace Tests\Base\Service;

use Base\DependencyInjection\BaseExtension;
use Base\Service\TimeMachine;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Regression coverage for the snapshot archive scope.
 *
 * The backup used to tar getcwd() wholesale while passing a single exclude,
 * and buildArchive() concatenated its --exclude flags without any separator -
 * so tar read the whole run as ONE pattern, matched nothing, and archived
 * vendor/ + node_modules/ + var/cache with no error whatsoever. On this app
 * that turned a ~1.2GB snapshot into a ~5GB one and filled the disk the
 * application itself runs on.
 *
 * These are deliberately filesystem-level (real tar, real du): the bug lived
 * entirely in the shell string that got built, so mocking it away would have
 * reproduced nothing.
 */
class TimeMachineTest extends TestCase
{
    private string $fixture;

    private function timeMachine(?string $cacheDir = null): TimeMachine
    {
        $rc = new ReflectionClass(TimeMachine::class);
        $timeMachine = $rc->newInstanceWithoutConstructor();

        $cache = $rc->getProperty('cacheDir');
        $cache->setAccessible(true);
        $cache->setValue($timeMachine, $cacheDir ?? sys_get_temp_dir());

        return $timeMachine;
    }

    protected function setUp(): void
    {
        $this->fixture = sys_get_temp_dir() . "/timemachine-test-" . bin2hex(random_bytes(4));
        foreach (["vendor/pkg", "node_modules/mod", "var/cache/pool", "var/storage", "src"] as $dir) {
            mkdir($this->fixture . "/" . $dir, 0777, true);
        }

        foreach ([
            "vendor/pkg/dependency.php",
            "node_modules/mod/dependency.js",
            "var/cache/pool/compiled.php",
            "var/storage/uploaded.bin",
            "src/Business.php",
        ] as $file) {
            file_put_contents($this->fixture . "/" . $file, str_repeat("x", 1024));
        }
    }

    protected function tearDown(): void
    {
        exec(sprintf('rm -rf %s %s.tar', escapeshellarg($this->fixture), escapeshellarg($this->fixture)));
    }

    private function members(string $tarball): array
    {
        exec(sprintf('tar -tf %s', escapeshellarg($tarball)), $lines);

        return array_values(array_filter($lines, fn($line) => !str_ends_with($line, "/")));
    }

    public function testBuildArchiveAppliesEveryExcludeNotJustTheFirst(): void
    {
        $tarball = $this->fixture . ".tar";
        $result  = $this->timeMachine()->buildArchive(
            $tarball,
            $this->fixture,
            ["./vendor", "./node_modules", "./var/cache"],
            false,
            false
        );

        $this->assertNotNull($result);
        $members = $this->members($tarball);

        // The actual regression: with the flags concatenated, all three of
        // these leaked into the archive.
        $this->assertSame([], array_values(array_filter(
            $members,
            fn($member) => (bool) preg_match('#(vendor|node_modules|var/cache)#', $member)
        )), "excluded paths leaked into the archive");

        // ..while real data must still be there.
        $this->assertContains("./var/storage/uploaded.bin", $members);
        $this->assertContains("./src/Business.php", $members);
    }

    public function testBuildArchiveSkipsEmptyExcludesWithoutSwallowingTheNextOne(): void
    {
        $tarball = $this->fixture . ".tar";
        $this->timeMachine()->buildArchive($tarball, $this->fixture, ["", "./vendor", null], false, false);

        $members = $this->members($tarball);
        $this->assertSame([], array_values(array_filter($members, fn($m) => str_contains($m, "vendor"))));
        $this->assertContains("./src/Business.php", $members);
    }

    public function testDefaultExcludesCoverDependencyAndBuildOutput(): void
    {
        $defaults = TimeMachine::DEFAULT_EXCLUDES;

        foreach (["./vendor", "./node_modules", "./var/cache"] as $expected) {
            $this->assertContains($expected, $defaults);
        }

        // ..and must never drop user data.
        $this->assertNotContains("./var/storage", $defaults);
    }

    public function testEstimateArchiveSizeShrinksOnceExcludesApply(): void
    {
        $timeMachine = $this->timeMachine();

        $everything = $timeMachine->estimateArchiveSize($this->fixture, []);
        $excluded   = $timeMachine->estimateArchiveSize($this->fixture, ["./vendor", "./node_modules", "./var/cache"]);

        $this->assertNotNull($everything);
        $this->assertNotNull($excluded);
        $this->assertLessThan($everything, $excluded);
    }

    public function testAssertEnoughFreeSpaceRefusesAnArchiveThatCannotFit(): void
    {
        $timeMachine = $this->timeMachine($this->fixture);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Not enough free space/');

        // Multiplier large enough that no filesystem could satisfy it.
        $timeMachine->assertEnoughFreeSpace($this->fixture, [], 1.0e9);
    }

    public function testAssertEnoughFreeSpaceAllowsASnapshotThatFits(): void
    {
        $timeMachine = $this->timeMachine($this->fixture);

        $timeMachine->assertEnoughFreeSpace($this->fixture, ["./vendor", "./node_modules"], 2.2);
        $this->addToAssertionCount(1);
    }

    public function testSetConfigurationExposesListValuedOptionsAsAnArrayParameter(): void
    {
        $container = new ContainerBuilder();
        (new BaseExtension())->setConfiguration($container, [
            "time_machine" => ["excludes" => ["./vendor", "./node_modules"]],
        ], "base");

        // Previously only the flattened leaves existed, leaving the option
        // unreadable as a single parameter.
        $this->assertTrue($container->hasParameter("base.time_machine.excludes"));
        $this->assertSame(["./vendor", "./node_modules"], $container->getParameter("base.time_machine.excludes"));
    }
}
