<?php

namespace Tests\Base\Bridge\Symfony;

use Base\Database\Mapping\DescriptionShapes;
use Base\Database\Mapping\EntityShapes;
use Base\Database\Mapping\MetadataManipulator;
use Base\Database\Schema\EntitySchema;
use Base\Database\UnitOfWorks;
use Doctrine\ORM\EntityManagerInterface;
use Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Symfony\Omnibase\Form\FormMapping;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What the form and field types read of the mapping, on the neutral manipulator (omnibridge/symfony's FormMapping; plan
 * "omnibase agnostic", step B leftovers), against what they asked of Doctrine's ClassMetadataManipulator before: for every
 * class Doctrine maps, the form fields its mapping suggests (getFields()), each name's nullability and order (getMapping()'s
 * `nullable` and `orderBy`), the field and association names (getClassMetadata()'s) - on Doctrine's shapes and on the
 * Description the entities' folders give (the one omnibridge/eloquent maps them with).
 */
final class FormMappingTest extends KernelTestCase
{
    public function testItAnswersAsDoctrinesManipulatorForEveryClass(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        if (!class_exists(FormMapping::class)) {
            self::markTestSkipped('omnibridge/symfony has no FormMapping here.');
        }
        if (!$container->has('doctrine') || !$container->has(ClassMetadataManipulator::class)) {
            self::markTestSkipped('Doctrine is not installed: no metadata of its to compare.');
        }
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine')->getManager();
        /** @var ClassMetadataManipulator $doctrine */
        $doctrine = $container->get(ClassMetadataManipulator::class);

        $classes = [];
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if (!$metadata->isMappedSuperclass && !$metadata->isEmbeddedClass) {
                $classes[] = $metadata->getName();
            }
        }
        sort($classes);
        self::assertGreaterThan(50, \count($classes));

        $expected = self::doctrines($doctrine, $classes);
        self::assertSame($expected, self::neutral(new MetadataManipulator($doctrine->getShapes()), $classes), 'on Doctrine\'s shapes');
        self::assertSame($expected, self::neutral(new MetadataManipulator($this->description($entityManager)), $classes), 'on the Description');
    }

    /**
     * Doctrine's manipulator's answers, the field types named as they are now (its getFields() imports them by their
     * former names, Base\Field\Type\*).
     *
     * @param list<string> $classes
     */
    private static function doctrines(ClassMetadataManipulator $manipulator, array $classes): array
    {
        $answers = [];
        foreach ($classes as $class) {
            $metadata = $manipulator->getClassMetadata($class);
            $entry = ['fields' => self::attempt(fn () => self::named($manipulator->getFields($class))), 'fieldNames' => self::sorted($metadata->getFieldNames()), 'associationNames' => self::sorted($metadata->getAssociationNames()), 'properties' => []];
            foreach (array_keys($manipulator->getFieldNames($class) ?? []) as $name) {
                $mapping = self::attempt(fn () => $manipulator->getMapping($class, $name));
                $entry['properties'][$name] = [
                    // What the types read: `->nullable ?? false`, an order where the mapping has one (a refusal kept as such).
                    'nullable' => \is_string($mapping) ? $mapping : ($mapping?->nullable ?? false),
                    'orderBy' => \is_string($mapping) ? $mapping : (isset($mapping->orderBy) && \is_array($mapping->orderBy) ? array_map(self::direction(...), $mapping->orderBy) : null),
                ];
            }
            $answers[$class] = $entry;
        }

        return $answers;
    }

    /** @param list<string> $classes */
    private static function neutral(MetadataManipulator $manipulator, array $classes): array
    {
        $mapping = new FormMapping($manipulator);
        $answers = [];
        foreach ($classes as $class) {
            $shape = $manipulator->getShape($class);
            $entry = ['fields' => self::attempt(fn () => $mapping->fields($class)), 'fieldNames' => self::sorted($shape->fieldNames()), 'associationNames' => self::sorted($shape->associationNames()), 'properties' => []];
            foreach (array_keys($manipulator->getFieldNames($class) ?? []) as $name) {
                $entry['properties'][$name] = [
                    'nullable' => self::attempt(fn () => $mapping->nullable($class, $name)),
                    'orderBy' => self::attempt(fn () => $mapping->orderBy($class, $name)),
                ];
            }
            $answers[$class] = $entry;
        }

        return $answers;
    }

    private static function attempt(\Closure $answer): mixed
    {
        try {
            return $answer();
        } catch (\Throwable $e) {
            return 'refused: '.$e->getMessage();
        }
    }

    /** The former names of the field types (Base\Field\Type\*) as their classes' names. */
    private static function named(mixed $value): mixed
    {
        if (\is_array($value)) {
            return array_map(self::named(...), $value);
        }

        return \is_string($value) && str_starts_with($value, 'Base\\Field\\Type\\') && class_exists($value) ? (new \ReflectionClass($value))->getName() : $value;
    }

    /** An order's direction as the Description writes it: Doctrine 3.6 gives its SortDirection (Ascending, Descending). */
    private static function direction(mixed $direction): mixed
    {
        if ($direction instanceof \BackedEnum) {
            return $direction->value;
        }

        return $direction instanceof \UnitEnum ? (str_starts_with(strtoupper($direction->name), 'DESC') ? 'DESC' : 'ASC') : $direction;
    }

    /** @param list<string> $names */
    private static function sorted(array $names): array
    {
        sort($names);

        return $names;
    }

    /** The Description omnibridge/eloquent maps the entities with: the persistence's where it is not Doctrine, else built from the folders. */
    private function description(EntityManagerInterface $entityManager): EntityShapes
    {
        $container = static::getContainer();
        $shapes = UnitOfWorks::shapes($container->get(UnitOfWork::class));
        if ($shapes instanceof DescriptionShapes) {
            return $shapes;
        }
        $options = \Omnibridge\Symfony\Omnibase\Console\InstalledEntities::options($entityManager, (string) $container->getParameter('kernel.project_dir'));

        return new DescriptionShapes(EntitySchema::describe($options['paths'], $options));
    }
}
