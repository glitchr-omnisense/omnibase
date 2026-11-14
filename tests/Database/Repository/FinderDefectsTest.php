<?php

namespace Tests\Base\Database\Repository;

use Base\Database\Repository\Finder\Parser;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityParser;
use Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository;
use Base\Entity\Layout\ImageCrop;
use Base\Entity\Thread;
use Omnibridge\Doctrine\Omnibase\Repository\Layout\ImageCropRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The defects of the finder language seen while its parser left Doctrine (plan "omnibase agnostic",
 * step F1), fixed before step F2: each word does what its name says, on the harness's database.
 */
final class FinderDefectsTest extends KernelTestCase
{
    private function entityManager(): EntityManagerInterface
    {
        self::bootKernel();

        return static::getContainer()->get('doctrine')->getManager();
    }

    private function threads(): ServiceEntityRepository
    {
        $repository = $this->entityManager()->getRepository(Thread::class);
        self::assertInstanceOf(ServiceEntityRepository::class, $repository);

        return $repository;
    }

    /** LowerEqualTo and GreaterEqualTo (and OlderEqualTo, YoungerEqualTo) compare with <= and >=: they called Expr::le() and ge(), which Doctrine does not have. */
    public function testTheEqualToComparisonsRun(): void
    {
        $threads = $this->threads();

        foreach (['findByIdLowerEqualTo' => [5, '<='], 'findByIdGreaterEqualTo' => [5, '>='], 'findByPublishedAtYoungerEqualTo' => ['-1 day', '>='], 'findByPublishedAtOlderEqualTo' => ['-1 day', '<=']] as $method => [$value, $comparison]) {
            $query = $threads->{$method}($value);
            self::assertInstanceOf(Query::class, $query, $method);
            self::assertStringContainsString(' '.$comparison.' :', $query->getDQL(), $method);
            self::assertIsArray($query->getResult(), $method);
        }
    }

    /** A negative word is read as itself, no longer as its positive one, which it ends with. */
    public function testTheNegativeWordsAreReadAsThemselves(): void
    {
        $threads = $this->threads();
        /** @var ServiceEntityParser $parser */
        $parser = (new \ReflectionProperty(ServiceEntityRepository::class, 'serviceParser'))->getValue($threads);

        foreach (['findBySlugNotStartingWith' => ['a-', 'slug', Parser::OPTION_NOT_STARTING_WITH], 'findBySlugNotEndingWith' => ['-z', 'slug', Parser::OPTION_NOT_ENDING_WITH], 'findByNotInstanceOf' => [Thread::class, 'notInstanceOf', Parser::OPTION_NOT_INSTANCEOF], 'findByNotClassOf' => [Thread::class, 'notClassOf', Parser::OPTION_NOT_CLASSOF], 'findByNotMemberOf' => [Thread::class, 'notMemberOf', Parser::OPTION_NOT_MEMBEROF]] as $method => [$value, $field, $option]) {
            $criterion = $parser->read($method, [$value])->criterions()[0];
            self::assertSame($field, $criterion->field, $method);
            self::assertTrue($criterion->is($option), $method);
        }

        self::assertStringContainsString('e.slug NOT LIKE :', $threads->findBySlugNotStartingWith('a-')->getDQL());
        self::assertStringContainsString('e.slug NOT LIKE :', $threads->findBySlugNotEndingWith('-z')->getDQL());
        self::assertStringContainsString('NOT(e INSTANCE OF ', $threads->findByStateAndNotInstanceOf('publish', Thread::class)->getDQL());

        // NotClassOf: anything but that class exactly - its subclasses included -, ClassOf's opposite (they were the same query).
        $classOf = $threads->findByStateAndClassOf('publish', Thread::class)->getDQL();
        $notClassOf = $threads->findByStateAndNotClassOf('publish', Thread::class)->getDQL();
        self::assertNotSame($classOf, $notClassOf);
        self::assertStringContainsString('NOT(e INSTANCE OF Base\\Entity\\Thread) OR ', $notClassOf);
        self::assertIsArray($threads->findByStateAndNotClassOf('publish', Thread::class)->getResult());
    }

    /** lengthOf<Collection>By counts the collection's members (SIZE): it built LENGTH() of the joined alias, no DQL. */
    public function testTheLengthOfACollectionIsItsSize(): void
    {
        self::assertIsArray($this->threads()->lengthOfTagsByState('publish'));
    }

    /** A DateTimeImmutable compared with a field of a mutable type is bound as the same moment: the type refused it. */
    public function testAnImmutableMomentIsComparedWithAMutableField(): void
    {
        $threads = $this->threads();
        $moment = new \DateTimeImmutable('2026-05-01 12:00:00');

        self::assertIsArray($threads->findByPublishedAtGreaterThan($moment)->getResult());
        self::assertNull($threads->cacheNextOneByClassOfAndState(['publishedAt' => $moment], Thread::class, 'publish'));
    }

    /** Null and NotNull bind no parameter: findBy...ParentNull with an order raised "Too many parameters". */
    public function testNullBindsNoParameter(): void
    {
        $threads = $this->threads();

        foreach ([$threads->findByParentNull(), $threads->findByParentNotNull(), $threads->findByStateAndParentNull('publish', [], ['id' => 'ASC']), $threads->findBySlugNull()] as $query) {
            self::assertInstanceOf(Query::class, $query);
            self::assertIsArray($query->getResult(), $query->getDQL());
        }
        self::assertStringContainsString('e_parent IS NULL', $threads->findByParentNull()->getDQL());
    }

    /** ImageCropRepository's two finders give the ratio as the selected expression, no longer as the offset (a TypeError). */
    public function testTheImageCropsClosestToARatioAreFound(): void
    {
        $crops = $this->entityManager()->getRepository(ImageCrop::class);
        self::assertInstanceOf(ImageCropRepository::class, $crops);

        self::assertNull($crops->oneClosestToRatio(1.5, 'no-such-image.jpg'));
        self::assertNull($crops->oneClosestToRatioAndSize(1.5, 300, 200, 'no-such-image.jpg'));
    }
}
