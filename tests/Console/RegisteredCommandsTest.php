<?php

namespace Tests\Base\Console;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The commands of src/Console/Command are registered and run: six were registered by no
 * configuration (autocomplete:entities, icon:enums, timemachine:storage, translation:crud,
 * translation:entities, translation:enums). icon:controllers (it read a router the command has
 * not) and icon:crud (it asked a class name whether it was a CRUD controller, and looked for the
 * CRUD controllers where the core no longer keeps them) are removed.
 */
final class RegisteredCommandsTest extends KernelTestCase
{
    public static function commands(): iterable
    {
        yield ['autocomplete:entities', []];
        yield ['icon:enums', []];
        yield ['timemachine:storage', []];
        yield ['translation:crud', ['--locale' => 'en']];
        yield ['translation:entities', ['--locale' => 'en']];
        yield ['translation:enums', ['--locale' => 'en']];
    }

    /** @dataProvider commands */
    public function testTheCommandIsRegisteredAndRuns(string $name, array $arguments): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness).');
        }
        self::bootKernel();
        $tester = new CommandTester((new Application(static::$kernel))->find($name));
        self::assertSame(Command::SUCCESS, $tester->execute($arguments, ['capture_stderr_separately' => true]), $tester->getDisplay());
    }

    public function testTheBrokenOnesAreGone(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness).');
        }
        self::bootKernel();
        $application = new Application(static::$kernel);
        self::assertFalse($application->has('icon:controllers'));
        self::assertFalse($application->has('icon:crud'));
    }
}
