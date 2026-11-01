<?php

namespace Tests\Base\Database;

use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Attribute\Backend\Backends;
use Base\Database\Backend\ConditionalUpdates;
use Base\Database\Backend\EntityCache;
use Base\Database\Backend\RowLocks;
use Base\Database\UnitOfWorks;
use Base\Entity\Layout\Setting;
use Base\Entity\User\Identity;
use Base\Entity\User\Passkey;
use Base\Entity\User\Token;
use Base\Repository\User\Identities;
use Base\Repository\User\Passkeys;
use Base\Repository\User\Tokens;
use Base\Service\SettingBag;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The unit of work the core's services take (plan "omnibase agnostic", step
 * F2d): made from the entity manager they were handed, or the container's;
 * the repositories, the shapes and what only Doctrine does reached from it.
 */
final class UnitOfWorksTest extends KernelTestCase
{
    public function testAUnitOfWorkIsMadeFromTheEntityManagerOrTakenAsItIs(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $unitOfWork = UnitOfWorks::of($entityManager);

        self::assertInstanceOf(DoctrineUnitOfWork::class, $unitOfWork);
        self::assertSame($entityManager, $unitOfWork->entityManager());
        self::assertSame($unitOfWork, UnitOfWorks::of($unitOfWork));

        $this->expectException(\InvalidArgumentException::class);
        UnitOfWorks::of(new \stdClass());
    }

    public function testAPersistenceRegistersItsOwnManager(): void
    {
        $manager = new class {};
        $given = $this->createMock(UnitOfWork::class);
        UnitOfWorks::register($manager::class, static fn (object $persistence) => $given);

        self::assertSame($given, UnitOfWorks::of($manager));
    }

    public function testTheContainersUnitOfWorkIsDoctrinesOverItsEntityManager(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $unitOfWork = $container->get(UnitOfWork::class);

        self::assertInstanceOf(DoctrineUnitOfWork::class, $unitOfWork);
        self::assertSame($container->get('doctrine')->getManager(), $unitOfWork->entityManager());
        self::assertSame($unitOfWork, $container->get('Doctrine\ORM\EntityManagerInterface|Omnibridge\Persistence\UnitOfWork'), 'the union a deprecated constructor is autowired with');
    }

    public function testTheRepositoriesAndTheShapesThroughTheUnitOfWork(): void
    {
        self::bootKernel();
        // Made before any Doctrine event: its backends are registered by the unit of work itself.
        $unitOfWork = new DoctrineUnitOfWork(static::getContainer()->get('doctrine')->getManager());

        $repositories = UnitOfWorks::repositories($unitOfWork);
        self::assertInstanceOf(Identities::class, $repositories->of(Identity::class));
        self::assertInstanceOf(Passkeys::class, $repositories->of(Passkey::class));
        self::assertInstanceOf(Tokens::class, $repositories->of(Token::class));
        self::assertTrue(UnitOfWorks::shapes($unitOfWork)->shape(Token::class)->hasField('isRevoked'));
        self::assertFalse(UnitOfWorks::shapes($unitOfWork)->has(\stdClass::class));

        foreach ([RowLocks::class, ConditionalUpdates::class, EntityCache::class] as $backend) {
            self::assertInstanceOf($backend, Backends::of($unitOfWork, $backend));
        }
    }

    /** Laravel's container resolves no union type: the repositories' constructor takes the entity manager alone. */
    public function testTheRepositoriesAreAutowiredByAnyContainer(): void
    {
        $parameters = (new \ReflectionMethod(\Base\Bridge\Doctrine\DoctrineRepositories::class, '__construct'))->getParameters();

        self::assertCount(1, $parameters);
        self::assertInstanceOf(\ReflectionNamedType::class, $parameters[0]->getType());
        self::assertSame(EntityManagerInterface::class, $parameters[0]->getType()->getName());
    }

    public function testARowLockedThenAFieldComparedAndSet(): void
    {
        self::bootKernel();
        $unitOfWork = static::getContainer()->get(UnitOfWork::class);
        $setting = new Setting('f2d.unit_of_work.'.bin2hex(random_bytes(4)));
        $unitOfWork->persist($setting);
        $unitOfWork->flush();
        $connection = $unitOfWork->entityManager()->getConnection();

        try {
            $result = Backends::of($unitOfWork, RowLocks::class)->locked($setting, static function () use ($connection): string {
                self::assertTrue($connection->isTransactionActive());

                return 'done';
            });
            self::assertSame('done', $result);
            self::assertFalse($connection->isTransactionActive(), 'committed');

            try {
                Backends::of($unitOfWork, RowLocks::class)->locked($setting, static fn () => throw new \RuntimeException('refused'));
                self::fail('The work\'s exception is thrown again.');
            } catch (\RuntimeException $e) {
                self::assertSame('refused', $e->getMessage());
            }
            self::assertFalse($connection->isTransactionActive(), 'rolled back');

            $updates = Backends::of($unitOfWork, ConditionalUpdates::class);
            $path = $setting->getPath();
            self::assertTrue($updates->compareAndSet($setting, 'path', $path, $path.'.set'));
            self::assertFalse($updates->compareAndSet($setting, 'path', $path, $path.'.again'), 'the field no longer holds the value');
            self::assertSame($path.'.set', $connection->fetchOne(sprintf('SELECT %s FROM %s WHERE id = ?', ($metadata = $unitOfWork->entityManager()->getClassMetadata(Setting::class))->getColumnName('path'), $metadata->getTableName()), [$setting->getId()]));
        } finally {
            $unitOfWork->refresh($setting);
            $unitOfWork->remove($setting);
            $unitOfWork->flush();
        }
    }

    public function testTheServicesTakeTheEntityManagerStill(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $settings = $container->get(SettingBag::class);

        self::assertInstanceOf(SettingBag::class, $settings);
        $entityManager = $container->get('doctrine')->getManager();
        $tracker = new \Base\Security\ConnectionTracker($entityManager, $container->get(\Base\Repository\User\Connections::class));
        self::assertInstanceOf(\Base\Security\ConnectionTracker::class, $tracker, 'the deprecated entity manager is adapted');
    }
}
