<?php

namespace Tests\Base\Database\Repository;

use Base\Database\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Regression coverage for the finder-DSL parser (ServiceEntityParser).
 *
 * The parser needs a real EntityManager (ClassMetadata is its symbol table), so
 * this boots the host application kernel and drives a real repository. It does
 * NOT execute SQL: it asserts on the *built* Query (maxResults), which is a pure
 * function of the parsed method name — deterministic and independent of DB rows.
 *
 * Runs under the host app's PHPUnit (`make tests glitchr`, KERNEL_CLASS=App\Kernel).
 * Skips cleanly when the bundle is tested standalone (no host kernel).
 */
class ServiceEntityParserTest extends KernelTestCase
{
    private const ENTITY = 'App\\Entity\\Newsletter\\Subscriber';

    private function repository(): ServiceEntityRepository
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (run via `make tests glitchr`).');
        }
        if (!class_exists(self::ENTITY)) {
            self::markTestSkipped('Host entity ' . self::ENTITY . ' not available.');
        }

        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository(self::ENTITY);

        if (!$repo instanceof ServiceEntityRepository) {
            self::markTestSkipped(self::ENTITY . ' is not backed by a base ServiceEntityRepository.');
        }

        return $repo;
    }

    /**
     * Bug regression: findLast<N>By must LIMIT to N.
     *
     * Before the fix, __findLastBy did `$limit = array_unshift($criteria)`, which
     * returns the element COUNT of the criteria array (a constant ~2 here) and
     * discards N entirely — so findLast3 and findLast5 produced the SAME limit.
     * Asserting distinct N values pins the bug shut.
     */
    public function testFindLastByRespectsTheRequestedCount(): void
    {
        $repo = $this->repository();

        $q3 = $repo->findLast3ById(1);
        $q5 = $repo->findLast5ById(1);

        $this->assertInstanceOf(Query::class, $q3);
        $this->assertInstanceOf(Query::class, $q5);
        $this->assertSame(3, $q3->getMaxResults(), 'findLast3By must LIMIT 3');
        $this->assertSame(5, $q5->getMaxResults(), 'findLast5By must LIMIT 5');
    }

    /**
     * Sanity: the sibling special (__findAtMostBy, already correct) proves the
     * harness and the special-count plumbing work — so the test above is really
     * exercising the fixed code, not passing vacuously.
     */
    public function testFindAtMostBySiblingStillLimits(): void
    {
        $repo = $this->repository();

        $q2 = $repo->findAtMost2ById(1);

        $this->assertInstanceOf(Query::class, $q2);
        $this->assertSame(2, $q2->getMaxResults(), 'findAtMost2By must LIMIT 2');
    }

    /**
     * Characterization: a plain findOneBy resolves and returns a single result
     * (object|null), not a Query — guards the request-type dispatch.
     */
    public function testFindOneByResolvesToScalarResult(): void
    {
        $repo = $this->repository();

        $result = $repo->findOneById(-1); // id -1 never exists -> deterministic null

        $this->assertNull($result);
    }

    /**
     * Query-by-Example: criteriaFromSelect() maps only the *set* mapped fields and
     * skips null/unset ones. Asserted directly (no DB) via the protected helper.
     */
    public function testCriteriaFromSelectMapsSetFieldsAndSkipsUnset(): void
    {
        $repo = $this->repository();

        // Bypass the constructor so only what we set is present.
        $select = (new \ReflectionClass(self::ENTITY))->newInstanceWithoutConstructor();
        $select->setEmail('select@example.invalid');

        $method = new \ReflectionMethod($repo, 'criteriaFromSelect');
        $method->setAccessible(true);
        $criteria = $method->invoke($repo, $select);

        $this->assertArrayHasKey('email', $criteria, 'a set field must appear in the criteria');
        $this->assertSame($select->getEmail(), $criteria['email']);
        $this->assertArrayNotHasKey('phone', $criteria, 'an unset/null field must be skipped');
        $this->assertArrayNotHasKey('newsletters', $criteria, 'a to-many association must be skipped');
    }

    /**
     * findByExample() must refuse a select with no queryable set fields, rather
     * than silently degrading to a whole-table fetch. A bare stdClass has no
     * mapped fields, so this is deterministic and entity-independent.
     */
    public function testFindByExampleRejectsASelectWithNoCriteria(): void
    {
        $repo = $this->repository();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/no queryable set fields/');
        $repo->findByExample(new \stdClass());
    }

    /**
     * End-to-end: a hydrated select drives a real query. A bogus email matches no
     * row, so the result is deterministically empty while still exercising the
     * full reflection -> criteria -> parser -> QueryBuilder -> SQL path.
     */
    public function testFindByExampleQueriesBySetFields(): void
    {
        $repo = $this->repository();

        $select = (new \ReflectionClass(self::ENTITY))->newInstanceWithoutConstructor();
        $select->setEmail('__no_such_subscriber__@example.invalid');

        $result = $repo->findByExample($select);

        $this->assertSame([], $result);
    }

    /**
     * DSL "Model" clause: findByModel($select) expands the select's set fields into
     * an AND-combined query. Returns a Query (like every other find* magic method).
     */
    public function testModelClauseQueriesBySelect(): void
    {
        $repo = $this->repository();
        $select = (new \ReflectionClass(self::ENTITY))->newInstanceWithoutConstructor();
        $select->setEmail('__no_such_subscriber__@example.invalid');

        $query = $repo->findByModel($select);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertStringContainsStringIgnoringCase('email', $query->getDQL());
        $this->assertSame([], $query->getResult());
    }

    /**
     * The whole point of the DSL form over findByExample(): the select composes
     * with further criteria — findByModelAnd<Field><Operator>($select, $arg).
     */
    public function testModelClauseComposesWithAnAndCriterion(): void
    {
        $repo = $this->repository();
        $select = (new \ReflectionClass(self::ENTITY))->newInstanceWithoutConstructor();
        $select->setEmail('__no_such_subscriber__@example.invalid');

        $query = $repo->findByModelAndIdGreaterThan($select, 0);

        $dql = $query->getDQL();
        $this->assertStringContainsStringIgnoringCase('email', $dql, 'select field present');
        $this->assertStringContainsStringIgnoringCase('.id', $dql, 'composed criterion present');
        $this->assertSame([], $query->getResult());
    }

    /**
     * Model composes with "And" only. Mixing "Or" would imply parentheses the DSL
     * does not support, so it must fail loudly rather than silently mis-group.
     */
    public function testModelClauseRejectsOrComposition(): void
    {
        $repo = $this->repository();
        $select = (new \ReflectionClass(self::ENTITY))->newInstanceWithoutConstructor();
        $select->setEmail('select@example.invalid');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/cannot be combined with "Or"/');
        $repo->findByModelOrIdGreaterThan($select, 0);
    }

    /**
     * A select with no queryable set fields must fail, not silently drop the clause
     * (which would degrade the query to a whole-table scan).
     */
    public function testModelClauseRejectsAnEmptySelect(): void
    {
        $repo = $this->repository();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/no queryable set fields/');
        $repo->findByModel(new \stdClass());
    }

    /**
     * The select clause may be named by the entity's (or an ancestor's) short class
     * name, not only the generic "Model" keyword: findBySubscriber($select).
     */
    public function testSelectClauseAcceptsTheEntityShortName(): void
    {
        $repo = $this->repository();
        $select = (new \ReflectionClass(self::ENTITY))->newInstanceWithoutConstructor();
        $select->setEmail('__no_such_subscriber__@example.invalid');

        $query = $repo->findBySubscriber($select);   // == findByModel($select)

        $this->assertInstanceOf(Query::class, $query);
        $this->assertStringContainsStringIgnoringCase('email', $query->getDQL());
        $this->assertSame([], $query->getResult());
    }

    /**
     * findByPartialModel($select) switches each select field from "=" to "LIKE"
     * (wildcards supplied by the select value). Contrast with plain findByModel.
     */
    public function testPartialSelectUsesLike(): void
    {
        $repo = $this->repository();
        $select = (new \ReflectionClass(self::ENTITY))->newInstanceWithoutConstructor();
        $select->setEmail('%@example.invalid');

        $exact   = $repo->findByModel($select)->getDQL();
        $partial = $repo->findByPartialModel($select)->getDQL();

        $this->assertStringContainsStringIgnoringCase(' = ', $exact, 'plain Model uses equality');
        $this->assertStringNotContainsStringIgnoringCase('LIKE', $exact);
        $this->assertStringContainsStringIgnoringCase('LIKE', $partial, 'Partial Model uses LIKE');
        $this->assertSame([], $repo->findByPartialModel($select)->getResult());
    }
}
