<?php

namespace Tests\Base\Attributes;

use Base\Bridge\Doctrine\Behaviour\DoctrineBehaviours;
use Base\Bridge\Doctrine\Behaviour\DoctrineCacheBackend;
use Base\Bridge\Doctrine\Behaviour\DoctrineSlugifyBackend;
use Base\Bridge\Doctrine\Behaviour\DoctrineVaultBackend;
use Base\Bridge\Doctrine\DoctrineUnitOfWork;
use Base\Database\Attribute\Backend\Backends;
use Base\Database\Attribute\Backend\CacheBackend;
use Base\Database\Attribute\Backend\SlugifyBackend;
use Base\Database\Attribute\Backend\VaultBackend;
use Base\Database\Attribute\Cache;
use Base\Entity\Layout\TextOverride;
use Base\Exception\UnsupportedBehaviourException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Omnibridge\Persistence\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The behaviours' parts of the persistence (plan "omnibase agnostic", step F2b): #[Vault], #[Slugify] and
 * #[Cache] answer the neutral hooks and ask what only a persistence can do of a small interface,
 * registered per unit of work class - Doctrine's in Base\Bridge\Doctrine\Behaviour -, refused aloud where
 * the persistence has none.
 */
final class BehaviourBackendsTest extends KernelTestCase
{
    public function testAPersistenceWithoutTheBehavioursPartIsRefusedAloud(): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        self::assertFalse(Backends::has($unitOfWork, VaultBackend::class));

        $this->expectException(UnsupportedBehaviourException::class);
        $this->expectExceptionMessage('No '.VaultBackend::class.' for the unit of work');
        Backends::of($unitOfWork, VaultBackend::class);
    }

    public function testOneBackendPerUnitOfWorkBuiltFromIt(): void
    {
        $persistence = new class() implements UnitOfWork {
            public function persist(object $entity): void {}
            public function remove(object $entity): void {}
            public function flush(): void {}
            public function refresh(object $entity): void {}
            public function contains(object $entity): bool { return false; }
            public function reference(string $class, mixed $id): object { return new \stdClass(); }
            public function transactional(callable $work): mixed { return $work($this); }
            public function isNew(object $entity): bool { return true; }
            public function identifier(object $entity): mixed { return null; }
            public function className(object $entity): string { return $entity::class; }
            public function changes(object $entity): array { return []; }
            public function recompute(object $entity): void {}
        };
        $other = clone $persistence;

        Backends::register($persistence::class, VaultBackend::class, static fn (UnitOfWork $unitOfWork) => new class($unitOfWork) implements VaultBackend {
            public array $scheduled = [];

            public function __construct(public readonly UnitOfWork $unitOfWork) {}

            public function scheduleUpdate(object $entity): void
            {
                $this->scheduled[] = $entity;
            }
        });

        $backend = Backends::of($persistence, VaultBackend::class);
        self::assertSame($persistence, $backend->unitOfWork);
        self::assertSame($backend, Backends::of($persistence, VaultBackend::class), 'one per unit of work');
        self::assertNotSame($backend, Backends::of($other, VaultBackend::class));
        self::assertSame($other, Backends::of($other, VaultBackend::class)->unitOfWork);
    }

    public function testDoctrinesPartsAreRegisteredForItsUnitOfWork(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $unitOfWork = new DoctrineUnitOfWork($entityManager);

        DoctrineBehaviours::register();
        self::assertInstanceOf(DoctrineVaultBackend::class, Backends::of($unitOfWork, VaultBackend::class));
        self::assertInstanceOf(DoctrineSlugifyBackend::class, Backends::of($unitOfWork, SlugifyBackend::class));
        self::assertInstanceOf(DoctrineCacheBackend::class, Backends::of($unitOfWork, CacheBackend::class));

        // The pending writes: a persisted entity is scheduled for insertion until the flush.
        $override = (new TextOverride())->setDomain('behaviour-backends-test')->setKey('greeting')->setLocale('en-GB')->setValue('Hello');
        $entityManager->persist($override);
        try {
            self::assertContains($override, Backends::of($unitOfWork, SlugifyBackend::class)->pendingWrites());
        } finally {
            $entityManager->detach($override);
        }
        self::assertNotContains($override, Backends::of($unitOfWork, SlugifyBackend::class)->pendingWrites());
    }

    /** #[Cache] numbers the association types as Doctrine does, without loading it to build the attribute. */
    public function testTheCachesAssociationFlagsAreDoctrines(): void
    {
        self::assertSame(ClassMetadata::ONE_TO_ONE, Cache::FLAG_ONE_TO_ONE);
        self::assertSame(ClassMetadata::MANY_TO_ONE, Cache::FLAG_MANY_TO_ONE);
        self::assertSame(ClassMetadata::ONE_TO_MANY, Cache::FLAG_ONE_TO_MANY);
        self::assertSame(ClassMetadata::MANY_TO_MANY, Cache::FLAG_MANY_TO_MANY);
        self::assertSame(ClassMetadata::ONE_TO_MANY + ClassMetadata::MANY_TO_MANY, (new Cache(associations: Cache::TO_MANY))->associations);
    }
}
