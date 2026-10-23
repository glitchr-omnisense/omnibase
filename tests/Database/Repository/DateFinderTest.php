<?php

namespace Tests\Base\Database\Repository;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Entity\Layout\TextOverride;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The finder's YoungerThan and OlderThan compare a date with now moved by the
 * age they are given ("countByStateAndPublishedAtYoungerThan($states, 3600 * 48)":
 * published these two days). Since 2023-01 the age was given to
 * round_datetime() as its precision, so the bound was now rounded to the
 * nearest multiple of the age - up to half the age before or after now:
 * YoungerThan matched a fraction of the age or nothing (lesringostars' menu
 * never marked a recent article) and OlderThan did not wait the age.
 * TextOverride's updatedAt is a datetime_immutable: the bound, a DateTime, is
 * bound with the mutable type (DBAL refused it before).
 *
 * Runs against the host application's database (the omnibase harness: a
 * fresh SQLite one), on omnibase's own TextOverride rows, written here and
 * removed after.
 */
class DateFinderTest extends KernelTestCase
{
    private const DOMAIN = 'date-finder-test';

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }

        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->purge();
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $this->purge();
        }
        parent::tearDown();
    }

    private function purge(): void
    {
        $this->em->createQuery('DELETE FROM '.TextOverride::class.' t WHERE t.domain = :d')
            ->setParameter('d', self::DOMAIN)
            ->execute();
    }

    private function repository(): ServiceEntityRepository
    {
        $repository = $this->em->getRepository(TextOverride::class);
        $this->assertInstanceOf(ServiceEntityRepository::class, $repository);

        return $repository;
    }

    /** A row of the test, updated $ago. */
    private function write(string $key, string $ago): void
    {
        $this->em->persist((new TextOverride())->setDomain(self::DOMAIN)->setKey($key)->setLocale('fr')->setValue($key));
        $this->em->flush();
        $this->em->createQuery('UPDATE '.TextOverride::class.' t SET t.updatedAt = :at WHERE t.domain = :d AND t.key = :k')
            ->setParameter('at', new \DateTimeImmutable($ago), 'datetime_immutable')
            ->setParameter('d', self::DOMAIN)
            ->setParameter('k', $key)
            ->execute();
        $this->em->clear();
    }

    public function testYoungerThanIsWhatHappenedWithinTheAge(): void
    {
        $this->write('an-hour-ago', '-1 hour');
        $this->write('three-days-ago', '-3 days');
        $repository = $this->repository();

        $this->assertSame(1, $repository->countByDomainAndUpdatedAtYoungerThan(self::DOMAIN, 3600 * 48), 'in seconds');
        $this->assertSame(1, $repository->countByDomainAndUpdatedAtYoungerThan(self::DOMAIN, '+2 days'), 'as a relative date');
        $this->assertSame(2, $repository->countByDomainAndUpdatedAtYoungerThan(self::DOMAIN, 3600 * 24 * 7));
        $this->assertSame(0, $repository->countByDomainAndUpdatedAtYoungerThan(self::DOMAIN, 60));
    }

    public function testOlderThanIsWhatHappenedBeforeTheAge(): void
    {
        $this->write('an-hour-ago', '-1 hour');
        $this->write('three-days-ago', '-3 days');
        $repository = $this->repository();

        $this->assertSame(1, $repository->countByDomainAndUpdatedAtOlderThan(self::DOMAIN, 3600 * 48));
        $this->assertSame(0, $repository->countByDomainAndUpdatedAtOlderThan(self::DOMAIN, 3600 * 24 * 7));
        $this->assertSame(2, $repository->countByDomainAndUpdatedAtOlderThan(self::DOMAIN, 60));
    }
}
