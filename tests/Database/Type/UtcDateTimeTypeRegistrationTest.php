<?php

namespace Tests\Base\Database\Type;

use Base\Database\Type\UtcDateTimeImmutableType;
use Base\Database\Type\UtcDateTimeType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** The bundle registers both types: an entity names them without any configuration. */
class UtcDateTimeTypeRegistrationTest extends KernelTestCase
{
    public function testTheBundleRegistersTheTypes(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
        // The connection's type provider (omnibridge/doctrine 4.0: not DBAL's static registry).
        $types = static::getContainer()->get('doctrine')->getConnection()->getConfiguration()->getTypeProvider();

        $this->assertInstanceOf(UtcDateTimeImmutableType::class, $types->get(UtcDateTimeImmutableType::NAME));
        $this->assertInstanceOf(UtcDateTimeType::class, $types->get(UtcDateTimeType::NAME));
    }
}
