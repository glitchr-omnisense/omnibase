<?php

namespace Tests\Base\Database\Mapping;

use Base\Attributes\AttributeReader;
use Base\Database\Attribute\Alias;
use Base\Database\Attribute\Hierarchify;
use Base\Database\Mapping\Completors;
use Base\Database\Mapping\DescriptionShapes;
use Base\Database\Mapping\EntityFields;
use Base\Database\Mapping\EntityShapes;
use Base\Database\Mapping\MetadataManipulator;
use Base\Database\Schema\EntitySchema;
use Base\Database\UnitOfWorks;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Omnibridge\Persistence\Backend;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\Entity;
use Omnibridge\Schema\Field;
use Omnibridge\Schema\Relation;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The mapping the attribute reader and the attributes read, on the entities' shapes - the Description - instead of
 * Doctrine's ClassMetadataFactory (plan "omnibase agnostic", step E3):
 * - the completors (#[Alias]'s names, #[Hierarchify]'s hierarchy) are read from the classes, the same each time, a
 *   manipulator made again over an empty cache included (it lost them: in eloquent mode, after lesringostars'
 *   RepositoryInterfacesTest, EntityHydratorTest's comparison found Doctrine's manipulator without Tag's "articles");
 * - for every class Doctrine maps, MetadataManipulator answers as omnibridge/doctrine's ClassMetadataManipulator does from
 *   Doctrine's metadata, on Doctrine's shapes and on the Description the entities' folders give (the one omnibridge/eloquent
 *   maps them with), and so does the reader's own manipulator, whichever persistence the host chose. With
 *   METADATA_SNAPSHOT_FILE set, the reader's answers are written there as JSON: the same file in both modes.
 */
final class MetadataManipulatorTest extends KernelTestCase
{
    public function testTheCompletorsAreReadFromTheClasses(): void
    {
        self::assertSame(['aliasNames' => ['summary' => 'excerpt', 'heading' => 'title'], 'class' => CompletedArticle::class], Completors::payload(CompletedArticle::class));
        self::assertSame(['class' => CompletedTree::class, 'entityHierarchy' => ['parent'], 'entityHierarchySeparator' => '/'], Completors::payload(CompletedTree::class));
        self::assertSame([], Completors::payload(\stdClass::class));
        self::assertSame(Completors::of(CompletedArticle::class), Completors::of(new CompletedArticle()), 'one completor per class');

        $manipulator = new MetadataManipulator($this->shapes());
        self::assertSame(['summary' => 'excerpt', 'heading' => 'title', 'id' => 'id', 'title' => 'title', 'excerpt' => 'excerpt', 'parent' => 'parent'], $manipulator->getFieldNames(CompletedArticle::class));
        self::assertSame('title', $manipulator->getFieldName(CompletedArticle::class, 'heading'));
        self::assertTrue($manipulator->hasField(CompletedArticle::class, 'heading'));
        self::assertSame(CompletedTree::class, $manipulator->getTargetClass(CompletedArticle::class, 'parent'));
        self::assertSame(2, $manipulator->getAssociationType(CompletedArticle::class, 'parent'));
        self::assertTrue($manipulator->isToOneSide(CompletedArticle::class, 'parent'));
        self::assertSame('string', $manipulator->getTypeOfField(CompletedArticle::class, 'title'));
        self::assertSame('id', $manipulator->getPrimaryKey(new CompletedArticle()));
        self::assertSame([CompletedArticle::class, CompletedTree::class], $manipulator->getAllClassNames());
        self::assertSame(Completors::of(CompletedArticle::class), $manipulator->getClassMetadataCompletor(CompletedArticle::class));
        self::assertNull($manipulator->getClassMetadataCompletor(\stdClass::class));
        self::assertFalse($manipulator->isEntity(\stdClass::class));

        $article = new CompletedArticle();
        $manipulator->setFieldValue($article, 'heading', 'A title');
        self::assertSame('A title', $article->title);
        self::assertSame('A title', $manipulator->getFieldValue($article, 'title'));
    }

    /** A manipulator of Doctrine's made again over an empty cache answers the aliases, as the first did. */
    public function testDoctrinesManipulatorMadeAgainKeepsTheAliases(): void
    {
        $entityManager = $this->doctrine();
        $class = $this->aliased($entityManager);
        $first = static::getContainer()->get(ClassMetadataManipulator::class);
        $names = $first->getFieldNames($class);

        $cacheDir = sys_get_temp_dir().'/metadata-manipulator-'.bin2hex(random_bytes(4));
        try {
            $again = new ClassMetadataManipulator(static::getContainer()->get('doctrine'), $entityManager, $cacheDir);
            $again->warmUp($cacheDir);
            foreach (EntityFields::aliases($class) as $alias => $property) {
                self::assertSame($property, $again->getFieldName($class, $alias), $class.'::'.$alias);
                self::assertSame($property, $first->getFieldName($class, $alias), $class.'::'.$alias);
            }
            self::assertSame($names, $again->getFieldNames($class));
        } finally {
            (new \Symfony\Component\Filesystem\Filesystem())->remove($cacheDir);
        }
    }

    /** For every class Doctrine maps: the same answers from the shapes and the Description as from Doctrine's metadata. */
    public function testItAnswersAsDoctrinesManipulatorForEveryClass(): void
    {
        $entityManager = $this->doctrine();
        $container = static::getContainer();
        /** @var ClassMetadataManipulator $doctrine */
        $doctrine = $container->get(ClassMetadataManipulator::class);
        $classes = $this->classes($entityManager);

        $expected = self::snapshot($doctrine, $classes);
        self::assertSame($expected, self::snapshot(new MetadataManipulator($doctrine->getShapes()), $classes), 'on Doctrine\'s shapes');
        self::assertSame($expected, self::snapshot(new MetadataManipulator($this->description($entityManager)), $classes), 'on the Description');

        $reader = $container->get(AttributeReader::class);
        self::assertInstanceOf(MetadataManipulator::class, $reader->getClassMetadataManipulator());
        self::assertNotInstanceOf(ClassMetadataManipulator::class, $reader->getClassMetadataManipulator(), 'the reader reads the shapes, whatever the persistence');
        self::assertSame(UnitOfWorks::shapes($container->get(UnitOfWork::class))::class, $reader->getClassMetadataManipulator()->getShapes()::class, 'the persistence\'s');
        $read = self::snapshot($reader->getClassMetadataManipulator(), $classes);
        self::assertSame($expected, $read, 'the reader\'s, on '.$container->get(Backend::class)->name());

        $file = getenv('METADATA_SNAPSHOT_FILE');
        if (\is_string($file) && '' !== $file) {
            file_put_contents($file, json_encode($read, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");
        }
    }

    /**
     * What a manipulator answers of each class, by name of each of its properties: plain data, compared and written to JSON.
     *
     * @param list<string> $classes
     *
     * @return array<string, array<string, mixed>>
     */
    public static function snapshot(MetadataManipulator $manipulator, array $classes): array
    {
        $snapshot = [];
        foreach ($classes as $class) {
            $names = $manipulator->getFieldNames($class) ?? [];
            ksort($names);
            try {
                $primaryKey = $manipulator->getPrimaryKey($class);
            } catch (\Throwable) {
                $primaryKey = '(composite)';
            }
            $discriminatorMap = $manipulator->getDiscriminatorMap($class);
            ksort($discriminatorMap);
            $completor = $manipulator->getClassMetadataCompletor($class);
            $payload = null === $completor ? null : array_filter($completor->payload(), static fn ($value) => null !== $value);
            if (\is_array($payload)) {
                ksort($payload);
            }

            $entry = [
                'entity' => $manipulator->isEntity($class),
                'completor' => $payload,
                'names' => $names,
                'primaryKey' => $primaryKey ?: null,
                'root' => $manipulator->getRootEntityName($class),
                'discriminatorMap' => $discriminatorMap,
                'discriminatorValue' => $manipulator->getDiscriminatorValue($class),
                'properties' => [],
            ];
            foreach (array_keys($names) as $name) {
                $entry['properties'][$name] = [
                    'name' => $manipulator->getFieldName($class, $name),
                    'field' => $manipulator->hasField($class, $name),
                    'association' => $manipulator->hasAssociation($class, $name),
                    'kind' => $manipulator->getAssociationType($class, $name),
                    'toMany' => $manipulator->isToManySide($class, $name),
                    'owning' => $manipulator->isOwningSide($class, $name),
                    'inverse' => $manipulator->isInverseSide($class, $name),
                    'type' => $manipulator->getTypeOfField($class, $name),
                    'target' => $manipulator->getTargetClass($class, $name),
                    'entityName' => $manipulator->fetchEntityName($class, $name),
                    'path' => $manipulator->resolveFieldPath($class, $name),
                    'declaring' => $manipulator->getDeclaringEntity($class, $name),
                ];
            }
            $snapshot[$class] = $entry;
        }

        return $snapshot;
    }

    private function doctrine(): EntityManagerInterface
    {
        self::bootKernel();
        $container = static::getContainer();
        if (!$container->has('doctrine') || !$container->has(ClassMetadataManipulator::class)) {
            self::markTestSkipped('Doctrine is not installed: no metadata of its to compare.');
        }

        return $container->get('doctrine')->getManager();
    }

    /** @return list<string> the classes Doctrine maps: entities, mapped superclasses, embeddables */
    private function classes(EntityManagerInterface $entityManager): array
    {
        $classes = [];
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $classes[] = $metadata->getName();
        }
        sort($classes);
        self::assertGreaterThan(50, \count($classes));

        return $classes;
    }

    private function aliased(EntityManagerInterface $entityManager): string
    {
        foreach ($this->classes($entityManager) as $class) {
            if ([] !== EntityFields::aliases($class)) {
                return $class;
            }
        }
        self::fail('No mapped class declares an alias.');
    }

    /** The Description omnibridge/eloquent maps the entities with: the persistence's where it is not Doctrine, else built from the folders. */
    private function description(EntityManagerInterface $entityManager): EntityShapes
    {
        $container = static::getContainer();
        $shapes = UnitOfWorks::shapes($container->get(UnitOfWork::class));
        if ($shapes instanceof DescriptionShapes) {
            return $shapes;
        }
        if (!class_exists(\Omnibridge\Symfony\Omnibase\Console\InstalledEntities::class)) {
            self::markTestSkipped('omnibridge/symfony is not installed: no entity folders to describe.');
        }
        $options = \Omnibridge\Symfony\Omnibase\Console\InstalledEntities::options($entityManager, (string) $container->getParameter('kernel.project_dir'));

        return new DescriptionShapes(EntitySchema::describe($options['paths'], $options));
    }

    private function shapes(): DescriptionShapes
    {
        return new DescriptionShapes(new Description([
            CompletedArticle::class => new Entity(CompletedArticle::class, 'article', identifier: ['id'], fields: [
                'id' => new Field('id', 'id', 'integer', id: true),
                'title' => new Field('title', 'title', 'string'),
                'excerpt' => new Field('excerpt', 'excerpt', 'text', nullable: true),
            ], relations: [
                'parent' => new Relation('parent', Relation::MANY_TO_ONE, CompletedTree::class, true),
            ]),
            CompletedTree::class => new Entity(CompletedTree::class, 'tree', identifier: ['id'], fields: ['id' => new Field('id', 'id', 'integer', id: true)]),
        ]));
    }
}

#[Alias(column: 'excerpt', alias: 'summary')]
final class CompletedArticle
{
    public ?int $id = 7;
    public ?string $title = null;
    public ?string $excerpt = null;
    public ?CompletedTree $parent = null;
    public ?string $summary = null;

    #[Alias(column: 'title')]
    public ?string $heading = null;
}

#[Hierarchify('parent', separator: '/')]
final class CompletedTree
{
    public ?int $id = null;
}
