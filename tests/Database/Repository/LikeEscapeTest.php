<?php

namespace Tests\Base\Database\Repository;

use Base\Entity\Thread;
use Base\Entity\ThreadIntl;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Base\ReleasesWhatItHolds;

/**
 * StartingWith, EndingWith and their Not read their value literally: omnibridge/doctrine escaped its "_" as "\_" with
 * no ESCAPE clause, which MySQL reads with its default escape and SQLite does not (it has none), so on SQLite - the
 * harness - `findByTitleStartingWith('esc_')` matched nothing, and a "%" in the value stayed a wildcard everywhere. The
 * value's "%", "_" and "!" are escaped with "!" and the LIKE says ESCAPE '!', the same on every platform:
 * - the finder on the harness's database (SQLite);
 * - the SQL Doctrine writes of the same DQL for MySQL;
 * - the escaped patterns read the same by SQLite and by MySQL (MySQL where OMNIBASE_TEST_MYSQL_URL names one, e.g.
 *   mysql://root:secret@mysql:3306/test?serverVersion=8.0, and pdo_mysql is loaded - the harness's image has not it).
 */
final class LikeEscapeTest extends KernelTestCase
{
    use ReleasesWhatItHolds;

    /** @var list<int> */
    private array $threads = [];

    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (the omnibase harness, or an application\'s suite).');
        }
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        $em = $this->entityManager();
        foreach ($this->threads as $id) {
            // Removed without the soft deletion (a trashed thread is a row still): its translations, then itself.
            $em->createQuery('DELETE FROM '.Thread::getTranslationEntityClass().' i WHERE i.translatable = :thread')->setParameter('thread', $id)->execute();
            $em->createQuery('DELETE FROM '.Thread::class.' t WHERE t.id = :thread')->setParameter('thread', $id)->execute();
        }
        parent::tearDown();
        $this->releaseWhatItHolds();
    }

    public function testTheFinderReadsTheValueLiterally(): void
    {
        // On the threads' titles (ThreadIntl), which are kept as written; a slug is rewritten (#[Slugify]).
        $tag = bin2hex(random_bytes(3));
        $underscore = $this->thread('esc_'.$tag.'_50%');
        $letter = $this->thread('escx'.$tag.'x50x');
        $repository = $this->entityManager()->getRepository(ThreadIntl::class);
        $ids = static fn (Query $query): array => array_map(static fn (ThreadIntl $text) => $text->getTranslatable()?->getId(), $query->getResult());

        self::assertSame([$underscore], $ids($repository->findByTitleStartingWith('esc_'.$tag)), '"_" is an underscore, not any character');
        self::assertSame([$underscore], $ids($repository->findByTitleEndingWith($tag.'_50%')), '"%" is a percent sign');
        self::assertSame([$letter], $ids($repository->findByTitleEndingWith($tag.'x50x')));
        self::assertSame([], $ids($repository->findByTitleStartingWith('esc%'.$tag)));
        self::assertSame([$underscore], $ids($repository->findByInsensitiveTitleStartingWith('ESC_'.strtoupper($tag))));
        self::assertNotContains($underscore, $ids($repository->findByTitleNotStartingWith('esc_')));
        self::assertContains($letter, $ids($repository->findByTitleNotStartingWith('esc_')));

        $query = $repository->findByTitleStartingWith('esc_'.$tag);
        self::assertStringContainsString("ESCAPE '!'", $query->getDQL());
        self::assertSame(['esc!_'.$tag.'%'], array_values(array_map(static fn ($parameter) => $parameter->getValue(), $query->getParameters()->toArray())));
    }

    public function testTheSqlForMySqlSaysTheEscape(): void
    {
        $dql = $this->entityManager()->getRepository(ThreadIntl::class)->findByTitleStartingWith('esc_')->getDQL();

        // An entity manager of the same configuration on MySQL's platform (the walker quotes the escape through a
        // connection: SQLite's in memory, which quotes "!" as MySQL does).
        $mysql = new EntityManager(DriverManager::getConnection(['driverClass' => SqliteAsMySqlDriver::class, 'memory' => true]), $this->entityManager()->getConfiguration());
        self::assertMatchesRegularExpression("/ LIKE \\? ESCAPE '!'/", $mysql->createQuery($dql)->getSQL());
    }

    public function testTheEscapedPatternsOnSqliteAndMySql(): void
    {
        $connections = ['sqlite' => DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])];
        if (($url = getenv('OMNIBASE_TEST_MYSQL_URL')) && \extension_loaded('pdo_mysql')) {
            $connections['mysql'] = DriverManager::getConnection((new \Doctrine\DBAL\Tools\DsnParser(['mysql' => 'pdo_mysql']))->parse($url));
        }

        // [subject, pattern, LIKE ... ESCAPE '!' matches]: what the finder binds for StartingWith / EndingWith.
        $cases = [
            ['esc_ape', 'esc!_%', true],
            ['escxape', 'esc!_%', false],
            ['50%off', '50!%%', true],
            ['50 off', '50!%%', false],
            ['say!', '%say!!', true],
            ['back\\slash', 'back\\%', true],
        ];
        foreach ($connections as $platform => $connection) {
            \assert($connection instanceof Connection);
            foreach ($cases as [$subject, $pattern, $matches]) {
                $found = (int) $connection->fetchOne("SELECT CASE WHEN ? LIKE ? ESCAPE '!' THEN 1 ELSE 0 END", [$subject, $pattern]);
                self::assertSame($matches ? 1 : 0, $found, "$platform: '$subject' LIKE '$pattern' ESCAPE '!'");
            }
        }
        if (!isset($connections['mysql'])) {
            self::assertCount(1, $connections, 'SQLite only: no MySQL named by OMNIBASE_TEST_MYSQL_URL, or no pdo_mysql');
        }
    }

    private function thread(string $title): int
    {
        $thread = new Thread(null, null, $title);
        $em = $this->entityManager();
        $em->persist($thread);
        $em->flush();

        return $this->threads[] = $thread->getId();
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }
}

/** SQLite's PDO driver answering MySQL's platform: Doctrine writes MySQL's SQL with no MySQL server nor pdo_mysql. */
final class SqliteAsMySqlDriver extends \Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware
{
    public function __construct()
    {
        parent::__construct(new \Doctrine\DBAL\Driver\PDO\SQLite\Driver());
    }

    public function getDatabasePlatform(\Doctrine\DBAL\ServerVersionProvider $versionProvider): \Doctrine\DBAL\Platforms\AbstractPlatform
    {
        return new \Doctrine\DBAL\Platforms\MySQL80Platform();
    }
}
