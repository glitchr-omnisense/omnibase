<?php

namespace Tests\Base\Database\Entity;

use Base\Database\Attribute\Alias;
use Base\Database\Attribute\Backend\Backends;
use Base\Database\Backend\ColumnTypes;
use Base\Database\Entity\EntityHydrator;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Database\Mapping\DescriptionShapes;
use Base\Database\Mapping\EntityFields;
use Base\Database\Repository\Entities;
use Base\Database\Repository\Repositories;
use Base\Database\Type\SetType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Omnibridge\Doctrine\Omnibase\DoctrineUnitOfWork;
use Omnibridge\Persistence\Lifecycle\PreUpdate;
use Omnibridge\Persistence\Snapshots;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\Entity;
use Omnibridge\Schema\Field;
use Omnibridge\Schema\Relation;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * EntityHydrator on omnisense/omnibridge's UnitOfWork and the entities' shapes (plan "omnibase agnostic",
 * step F5c, item 3): on a Description and a unit of work of plain PHP, no ORM, it fills an entity, its
 * defaults, its aliases, its associations (by reference, by id through the repositories, to-many with
 * their inverse side), and rebuilds it as it was from the unit of work's Snapshots; on Doctrine, the
 * names, paths, targets and array types it reads answer as ClassMetadataManipulator answered them from
 * Doctrine's metadata, for every entity mapped.
 */
final class EntityHydratorTest extends KernelTestCase
{
    public function testItHydratesOnADescriptionAndAPlainUnitOfWork(): void
    {
        [$hydrator, $unitOfWork] = $this->plain();

        $author = new HydratedAuthor();
        $author->id = 7;
        $unitOfWork->known[HydratedAuthor::class][7] = $author;

        /** @var HydratedArticle $article */
        $article = $hydrator->hydrate(HydratedArticle::class, ['id' => 1, 'title' => 'A title', 'author' => 7]);
        self::assertInstanceOf(HydratedArticle::class, $article);
        self::assertSame('A title', $article->title);
        self::assertSame([], $article->tags, 'a json field holds an empty array by default');
        self::assertSame([], $article->roles, 'so does a set enumeration\'s');
        self::assertInstanceOf(Collection::class, $article->comments, 'a to-many association, an empty collection');
        self::assertSame($author, $article->author, 'a to-one association given by its id: the unit of work\'s reference');
        self::assertSame('A title', $article->heading, 'an alias is bound to its property');
        $article->heading = 'Another';
        self::assertSame('Another', $article->title);

        // By id, through the repositories (FETCH_ASSOCIATIONS), and a to-many inverse side given its owner.
        $comment = new HydratedComment();
        $unitOfWork->known[HydratedComment::class][3] = $comment;
        $fetched = $hydrator->hydrate(HydratedArticle::class, ['author_id' => 7, 'comments' => [3]], [], EntityHydrator::OBJECT_PROPERTIES | EntityHydrator::FETCH_ASSOCIATIONS);
        self::assertSame($author, $fetched->author);
        self::assertSame([$comment], array_values($fetched->comments->toArray()));
        self::assertSame($fetched, $comment->article, 'the owning side of an inverse association set');

        // As it was: the identifier and the unit of work's snapshot, a PreUpdate's changes read as before.
        $article->title = 'Changed';
        $unitOfWork->snapshots[spl_object_id($article)] = ['title' => 'A title', 'author' => $author];
        $original = $hydrator->getOriginalEntity($article);
        self::assertNotSame($article, $original);
        self::assertSame([1, 'A title', $author], [$original->id, $original->title, $original->author]);
        $before = $hydrator->getOriginalEntity(new PreUpdate($article, $unitOfWork, ['title' => ['First', 'Changed']]));
        self::assertSame('First', $before->title);

        self::assertSame(['id' => 1, 'title' => 'Changed'], array_intersect_key($hydrator->dehydrate($article), ['id' => 0, 'title' => 0]));
    }

    public function testAUnitOfWorkWithoutSnapshotsIsRefusedAloudForTheOriginal(): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $hydrator = new EntityHydrator($unitOfWork, $this->shapes());
        $article = new HydratedArticle();

        $this->expectException(\Base\Exception\UnsupportedBehaviourException::class);
        $hydrator->getOriginalEntityData($article);
    }

    public function testAPersistenceColumnTypesDecideWhatHoldsAnArray(): void
    {
        $unitOfWork = new class extends PlainUnitOfWork {};
        Backends::register(PlainUnitOfWork::class, Repositories::class, static fn (PlainUnitOfWork $unitOfWork) => new PlainRepositories($unitOfWork));
        Backends::register($unitOfWork::class, ColumnTypes::class, static fn () => new class implements ColumnTypes {
            public function isArray(string $type): bool
            {
                return 'string' === $type;
            }
        });

        $article = (new EntityHydrator($unitOfWork, $this->shapes()))->hydrate(HydratedArticle::class, []);
        self::assertSame([], $article->title);
        self::assertNull($article->tags);
    }

    /** On Doctrine: what the hydrator reads of the mapping answers as the manipulator answered it, for every entity. */
    public function testOnDoctrineItReadsTheMappingAsTheManipulatorDid(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine')->getManager();
        /** @var ClassMetadataManipulator $manipulator */
        $manipulator = $container->get(ClassMetadataManipulator::class);

        $hydrator = $container->get(EntityHydratorInterface::class);
        self::assertInstanceOf(EntityHydrator::class, $hydrator);
        self::assertInstanceOf(DoctrineUnitOfWork::class, $hydrator->getUnitOfWork());
        self::assertInstanceOf(Snapshots::class, $hydrator->getUnitOfWork());

        $fields = new EntityFields($manipulator->getShapes());
        $columnTypes = Backends::of(new DoctrineUnitOfWork($entityManager), ColumnTypes::class);
        $compared = $aliases = 0;
        $arrayTypes = [];
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
                continue;
            }
            $class = $metadata->getName();

            $names = $manipulator->getFieldNames($class);
            $neutral = $fields->names($class);
            ksort($names);
            ksort($neutral);
            self::assertSame($names, $neutral, $class.': the names');
            $aliases += \count(EntityFields::aliases($class));
            foreach (array_keys($names) as $name) {
                self::assertSame($manipulator->resolveFieldPath($class, $name), $fields->resolve($class, $name), $class.'::'.$name.': the path');
                self::assertSame($manipulator->fetchEntityName($class, $name), $fields->target($class, $name), $class.'::'.$name.': the target');
                self::assertSame($manipulator->hasAssociation($class, $name), $fields->hasAssociation($class, $name), $class.'::'.$name.': an association');
                ++$compared;
            }
            foreach ($metadata->fieldMappings as $mapping) {
                $doctrineType = $manipulator->getDoctrineType($mapping->type);
                $before = is_instanceof($doctrineType, JsonType::class) || is_instanceof($doctrineType, SetType::class);
                self::assertSame($before, $columnTypes->isArray($mapping->type), $class.'::'.$mapping->fieldName.' ('.$mapping->type.')');
                if ($before) {
                    $arrayTypes[$mapping->type] = true;
                }
            }
        }

        self::assertGreaterThan(100, $compared);
        self::assertGreaterThan(0, $aliases, 'the core\'s entities declare aliases (Thread, SlotIntl...)');
        self::assertArrayHasKey('json', $arrayTypes);
    }

    /** @return array{EntityHydrator, PlainUnitOfWork} */
    private function plain(): array
    {
        $unitOfWork = new PlainUnitOfWork();
        // No ColumnTypes registered: the neutral rule (json, a set enumeration).
        Backends::register(PlainUnitOfWork::class, Repositories::class, static fn (PlainUnitOfWork $unitOfWork) => new PlainRepositories($unitOfWork));

        return [new EntityHydrator($unitOfWork, $this->shapes()), $unitOfWork];
    }

    private function shapes(): DescriptionShapes
    {
        return new DescriptionShapes(new Description([
            HydratedArticle::class => new Entity(HydratedArticle::class, 'article', identifier: ['id'], fields: [
                'id' => new Field('id', 'id', 'integer', id: true),
                'title' => new Field('title', 'title', 'string'),
                'tags' => new Field('tags', 'tags', 'json', nullable: true),
                'roles' => new Field('roles', 'roles', HydratedRoles::class, nullable: true),
            ], relations: [
                'author' => new Relation('author', Relation::MANY_TO_ONE, HydratedAuthor::class, true),
                'comments' => new Relation('comments', Relation::ONE_TO_MANY, HydratedComment::class, false, mappedBy: 'article'),
            ]),
            HydratedAuthor::class => new Entity(HydratedAuthor::class, 'author', identifier: ['id'], fields: ['id' => new Field('id', 'id', 'integer', id: true)]),
            HydratedComment::class => new Entity(HydratedComment::class, 'comment', identifier: ['id'], fields: ['id' => new Field('id', 'id', 'integer', id: true)], relations: [
                'article' => new Relation('article', Relation::MANY_TO_ONE, HydratedArticle::class, true, inversedBy: 'comments'),
            ]),
        ]));
    }
}

/** A set enumeration (its values none: only its kind is read). */
final class HydratedRoles extends SetType
{
}

class HydratedArticle
{
    public ?int $id = null;
    public mixed $title = null;
    #[Alias(column: 'title')]
    public mixed $heading = null;
    public ?array $tags = null;
    public ?array $roles = null;
    public ?HydratedAuthor $author = null;
    public ?Collection $comments = null;
}

class HydratedAuthor
{
    public ?int $id = null;
}

class HydratedComment
{
    public ?int $id = null;
    public ?HydratedArticle $article = null;

    public function getArticle(): ?HydratedArticle
    {
        return $this->article;
    }

    public function setArticle(?HydratedArticle $article): void
    {
        $this->article = $article;
    }
}

/** A unit of work of plain PHP: the entities it knows by class and id, the snapshots it was given. */
class PlainUnitOfWork implements UnitOfWork, Snapshots
{
    /** @var array<class-string, array<int|string, object>> */
    public array $known = [];

    /** @var array<int, array<string, mixed>> */
    public array $snapshots = [];

    public function persist(object $entity): void {}
    public function remove(object $entity): void {}
    public function flush(): void {}
    public function refresh(object $entity): void {}
    public function contains(object $entity): bool { return false; }
    public function reference(string $class, mixed $id): object { return $this->known[$class][$id] ?? throw new \OutOfBoundsException($class.'#'.$id); }
    public function transactional(callable $work): mixed { return $work($this); }
    public function isNew(object $entity): bool { return true; }
    public function identifier(object $entity): mixed { return $entity->id ?? null; }
    public function className(object $entity): string { return $entity::class; }
    public function changes(object $entity): array { return []; }
    public function recompute(object $entity): void {}

    public function original(object $entity): array
    {
        return $this->snapshots[spl_object_id($entity)] ?? [];
    }
}

final class PlainRepositories implements Repositories
{
    public function __construct(private readonly PlainUnitOfWork $unitOfWork)
    {
    }

    public function of(string $class): Entities
    {
        return new PlainEntities($this->unitOfWork, $class);
    }

    public function byIds(string $class, array $ids): array
    {
        return array_values(array_filter(array_map(fn ($id) => $this->oneById($class, $id), $ids)));
    }

    public function oneById(string $class, mixed $id): ?object
    {
        return $this->unitOfWork->known[$class][$id] ?? null;
    }
}

final class PlainEntities implements Entities
{
    public function __construct(private readonly PlainUnitOfWork $unitOfWork, private readonly string $class)
    {
    }

    /** The finder language's cacheOneById(), cacheOneByUuid()... as an omnibase repository answers them. */
    public function __call(string $method, array $arguments): mixed
    {
        return str_starts_with($method, 'cacheOneBy') ? $this->find($arguments[0]) : throw new \BadMethodCallException($method);
    }

    public function find($id): ?object { return $this->unitOfWork->known[$this->class][$id] ?? null; }
    public function findAll(): array { return array_values($this->unitOfWork->known[$this->class] ?? []); }
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array { return []; }
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object { return null; }
    public function count(array $criteria = []): int { return 0; }
    public function persist($entity, bool $flush = false): void {}
    public function remove($entity, bool $flush = false): void {}
    public function flush(bool $autoclear = false) {}
}
