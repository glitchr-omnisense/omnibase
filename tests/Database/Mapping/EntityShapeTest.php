<?php

namespace Tests\Base\Database\Mapping;

use Omnibridge\Doctrine\Omnibase\Behaviour\DoctrineBehaviours;
use Omnibridge\Doctrine\Omnibase\DoctrineShapes;
use Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork;
use Base\Database\Attribute\Backend\Backends;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Mapping\DescriptionShapes;
use Base\Database\Mapping\EntityShapes;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Doctrine\MetadataDescription;
use Omnibridge\Schema\Column;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\Entity;
use Omnibridge\Schema\Field;
use Omnibridge\Schema\Inheritance;
use Omnibridge\Schema\Relation;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The entity's shape (plan "omnibase agnostic", step F2c): what the core reads of a mapping, from a
 * Description, on no ORM; on Doctrine, the same answers ClassMetadataManipulator gave from the metadata.
 */
final class EntityShapeTest extends KernelTestCase
{
    public function testAShapeReadsTheDescription(): void
    {
        $article = new Entity('App\Entity\Article', 'article', parent: 'App\Entity\Thread', root: 'App\Entity\Thread',
            inheritance: new Inheritance(Inheritance::SINGLE_TABLE, new Column('discr', 'string'), ['article' => 'App\Entity\Article'], 'article'),
            identifier: ['id'],
            fields: ['id' => new Field('id', 'id', 'integer', id: true), 'title' => new Field('title', 'title', 'string')],
            relations: [
                'author' => new Relation('author', Relation::MANY_TO_ONE, 'App\Entity\User', true, inversedBy: 'articles'),
                'comments' => new Relation('comments', Relation::ONE_TO_MANY, 'App\Entity\Comment', false, mappedBy: 'article'),
            ],
        );
        $shapes = new DescriptionShapes(new Description(['App\Entity\Article' => $article, 'App\Entity\Base' => new Entity('App\Entity\Base', null, Entity::MAPPED_SUPERCLASS)]));

        self::assertTrue($shapes->has('App\Entity\Article'));
        self::assertFalse($shapes->has('App\Entity\Base'), 'a mapped superclass is no entity');
        self::assertFalse($shapes->has('App\Entity\Nothing'));
        self::assertSame(['App\Entity\Article'], $shapes->classes(), 'the entities listed, not the mapped superclass (step B1d)');

        $shape = $shapes->shape('App\Entity\Article');
        self::assertSame('App\Entity\Thread', $shape->rootClass());
        self::assertSame(['id'], $shape->identifier());
        self::assertSame('id', $shape->primaryKey());
        self::assertSame(['id', 'title'], $shape->fieldNames());
        self::assertSame('string', $shape->fieldType('title'));
        self::assertNull($shape->fieldType('author'));
        self::assertTrue($shape->isToOne('author') && $shape->isOwningSide('author'));
        self::assertTrue($shape->isToMany('comments') && $shape->isInverseSide('comments'));
        self::assertFalse($shape->isInverseSide('title'), 'a field is no side');
        self::assertSame('App\Entity\User', $shape->targetClass('author'));
        self::assertSame(['discr', ['article' => 'App\Entity\Article'], 'article'], [$shape->discriminatorColumn(), $shape->discriminatorMap(), $shape->discriminatorValue()]);

        self::assertSame('App\Entity\Base', $shapes->shape('App\Entity\Base')->className(), 'a mapped superclass has a shape');
        $this->expectException(\OutOfBoundsException::class);
        $shapes->shape('App\Entity\Nothing');
    }

    /** On Doctrine: every association of every mapped entity answers as the metadata did. */
    public function testTheManipulatorAnswersFromTheShapeAsFromTheMetadata(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        /** @var ClassMetadataManipulator $manipulator */
        $manipulator = static::getContainer()->get(ClassMetadataManipulator::class);

        $compared = 0;
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass || $metadata->reflClass?->isAbstract()) {
                continue;
            }

            $shape = $manipulator->getShape($metadata->getName());
            self::assertNotNull($shape, $metadata->getName());
            self::assertEquals(MetadataDescription::entity($metadata), $shape->entity);
            self::assertSame($metadata->rootEntityName, $shape->rootClass());
            self::assertSame($metadata->identifier, $shape->identifier());

            foreach (array_keys($metadata->associationMappings) as $property) {
                $where = $metadata->getName().'::'.$property;
                self::assertSame($manipulator->getAssociationType($metadata, $property), $manipulator->getAssociationType($metadata->getName(), $property), $where);
                self::assertSame($manipulator->isOwningSide($metadata, $property), $manipulator->isOwningSide($metadata->getName(), $property), $where);
                self::assertSame($manipulator->isInverseSide($metadata, $property), $manipulator->isInverseSide($metadata->getName(), $property), $where);
                ++$compared;
            }
            self::assertFalse($manipulator->getAssociationType($metadata->getName(), 'noSuchAssociation'));
        }
        self::assertGreaterThan(50, $compared);
        self::assertNull($manipulator->getShape(\stdClass::class));
        self::assertNull($manipulator->getShape($entityManager->getClassMetadata(\Base\Entity\Thread::class)), 'a ClassMetadata is read as it stands');
    }

    public function testDoctrinesShapesAreTheUnitOfWorksToo(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $unitOfWork = new DoctrineUnitOfWork($entityManager);
        DoctrineBehaviours::register();

        self::assertInstanceOf(DoctrineShapes::class, Backends::of($unitOfWork, EntityShapes::class));
        // The entities listed (step B1d): every entity the metadata factory loads, no mapped superclass.
        $classes = Backends::of($unitOfWork, EntityShapes::class)->classes();
        self::assertContains(\Base\Entity\Thread::class, $classes);
        self::assertSame(array_values(array_filter($classes, static fn (string $class) => Backends::of($unitOfWork, EntityShapes::class)->has($class))), $classes);
        self::assertSame($entityManager->getClassMetadata(\Base\Entity\Thread::class)->rootEntityName, Backends::of($unitOfWork, EntityShapes::class)->shape(\Base\Entity\Thread::class)->rootClass());
    }
}
