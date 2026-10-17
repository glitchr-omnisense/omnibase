<?php

namespace Tests\Base\Database\Repository;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Layout\TextOverride;
use Base\Repository\Layout\TextOverrideRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * omnibase's ServiceEntityRepository is doctrine/orm's EntityRepository built
 * from the ManagerRegistry (plan "omnibase agnostique", step C3b), no longer
 * DoctrineBundle's ServiceEntityRepository. Under Symfony nothing changes for
 * a caller: getRepository() hands out the repository service - the core's,
 * and an application's App\Repository\* extending it, tagged
 * doctrine.repository_service by BaseExtension - the one the container
 * autowires. Built by Doctrine's default factory instead (new $class($em,
 * $metadata)), a repository whose constructor takes the registry would die.
 */
final class RepositoryServiceTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
    }

    public function testTheCoresRepositoryIsTheServiceOnDoctrineOrmAlone(): void
    {
        $repository = static::getContainer()->get('doctrine')->getManager()->getRepository(TextOverride::class);

        self::assertInstanceOf(TextOverrideRepository::class, $repository);
        self::assertInstanceOf(ServiceEntityRepository::class, $repository);
        self::assertInstanceOf(EntityRepository::class, $repository);
        self::assertSame(TextOverride::class, $repository->getClassName());
        self::assertFalse(is_subclass_of($repository, 'Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepositoryInterface'));
        self::assertSame(static::getContainer()->get(TextOverrideRepository::class), $repository);
    }

    public function testAnApplicationsRepositoryIsTaggedAndHandedOut(): void
    {
        $repository = static::getContainer()->get('doctrine')->getManager()->getRepository('App\\Entity\\User');

        self::assertInstanceOf('App\\Repository\\UserRepository', $repository);
        self::assertInstanceOf(ServiceEntityRepository::class, $repository);
        self::assertSame(static::getContainer()->get('App\\Repository\\UserRepository'), $repository);
        self::assertSame(0, $repository->count(['email' => 'nobody-c3b@example.invalid']));
    }
}
