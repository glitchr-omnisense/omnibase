<?php

namespace Tests\Base\Database\Repository;

use Base\Database\Repository\Repositories;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Layout\TextOverride;
use Base\Repository\Layout\TextOverrideRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Under Symfony, omnibase's repositories are handed out by
 * Base\Database\Repository\Repositories::of(): the core's, and an
 * application's App\Repository\* extending it, the very service the container
 * autowires. Doctrine's own getRepository() answers its plain EntityRepository
 * (omnibridge/doctrine's RepositoryFactory): omnibase's repositories are no
 * Doctrine repositories.
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
        $repository = static::getContainer()->get(Repositories::class)->of(TextOverride::class);

        self::assertInstanceOf(TextOverrideRepository::class, $repository);
        self::assertInstanceOf(ServiceEntityRepository::class, $repository);
        self::assertInstanceOf(EntityRepository::class, static::getContainer()->get('doctrine')->getManager()->getRepository(TextOverride::class), 'Doctrine\'s own getRepository(): its plain repository');
        self::assertSame(TextOverride::class, $repository->getClassName());
        self::assertFalse(is_subclass_of($repository, 'Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepositoryInterface'));
        self::assertSame(static::getContainer()->get(TextOverrideRepository::class), $repository);
    }

    public function testAnApplicationsRepositoryIsTaggedAndHandedOut(): void
    {
        $repository = static::getContainer()->get(Repositories::class)->of('App\\Entity\\User');

        self::assertInstanceOf('App\\Repository\\UserRepository', $repository);
        self::assertInstanceOf(ServiceEntityRepository::class, $repository);
        self::assertSame(static::getContainer()->get('App\\Repository\\UserRepository'), $repository);
        self::assertSame(0, $repository->count(['email' => 'nobody-c3b@example.invalid']));
    }
}
