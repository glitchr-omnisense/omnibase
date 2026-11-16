<?php

namespace Tests\Base\Bridge\Doctrine;

use Omnibridge\Doctrine\Omnibase\Mapping;
use Base\Service\Storages;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tests\Base\Bridge\Doctrine\Fixtures\Services\Counted;
use Tests\Base\Bridge\Doctrine\Fixtures\Services\CountedRepository;

require_once __DIR__.'/fixtures/services/Counted.php';
require_once __DIR__.'/fixtures/services/CountedRepository.php';

/**
 * An entity manager built by hand gives a repository the services it takes
 * beside the finders (audit "lesringostars face au master plan", R7: the
 * site's CommentRepository, its cache pool first), each by its type from the
 * host (Mapping::register()'s `services`, read by its Repositories); a
 * service the host has not is null where the parameter allows it, else the
 * repository is refused naming it. Mapping::register()'s `flysystem` is the
 * attribute reader's (#[Uploader]: an upload's public path, an article's audio).
 */
final class RepositoryServicesTest extends TestCase
{
    private function entityManager(): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([__DIR__.'/fixtures/services'], true);
        $configuration->enableNativeLazyObjects(true);
        Mapping::configure($configuration);

        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);
    }

    public function testARepositoryIsGivenTheHostsServicesByType(): void
    {
        $pool = new ArrayAdapter();
        $flysystem = $this->createStub(Storages::class);
        $entityManager = $this->entityManager();
        $mapping = Mapping::register($entityManager, ['filters' => false, 'flysystem' => $flysystem, 'services' => static fn (string $type) => CacheItemPoolInterface::class === $type ? $pool : null]);

        $repository = $mapping->repositories->of(Counted::class);
        self::assertInstanceOf(CountedRepository::class, $repository);
        self::assertSame($pool, $repository->cache, 'the host\'s pool, by its type');
        self::assertNull($repository->nothing, 'a service the host has not: null, the parameter allowing it');
        self::assertSame(Counted::class, $repository->getClassName());
        self::assertSame($flysystem, $mapping->attributeReader->getFlysystem(), 'the reader reads the files through the host\'s Flysystem');
    }

    public function testARepositoryWhoseServiceTheHostHasNotIsRefused(): void
    {
        $mapping = Mapping::register($this->entityManager(), ['filters' => false, 'services' => static fn (string $type) => null]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('asks Psr\Cache\CacheItemPoolInterface $cache');
        $mapping->repositories->of(Counted::class);
    }
}
