<?php

namespace Tests\Base\Console;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * doctrine:database:import reads a CSV (or .txt) file as one spreadsheet named after the file:
 * the csv case fell through into the default one and threw "File extension couldn't be
 * determine..". A sheet whose first column names no entity is ignored, as a spreadsheet's is.
 */
final class DoctrineDatabaseImportCsvTest extends KernelTestCase
{
    public function testACsvFileIsReadAsOneSpreadsheet(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness).');
        }
        self::bootKernel();
        $file = sys_get_temp_dir().'/omnibase-import-'.bin2hex(random_bytes(4)).'.csv';
        file_put_contents($file, "NotAnEntity,name\ncomment,comment\n1,first\n");
        try {
            $tester = new CommandTester((new Application(static::$kernel))->find('doctrine:database:import'));
            $tester->execute(['path' => $file, '--extension' => 'csv', '--spreadsheet' => '0'], ['capture_stderr_separately' => true, 'interactive' => false]);
            self::assertStringNotContainsString("couldn't be determine", $tester->getDisplay());
            self::assertStringContainsString('is ignored, no valid entity found', $tester->getDisplay());
        } finally {
            @unlink($file);
        }
    }
}
