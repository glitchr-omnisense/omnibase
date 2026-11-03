<?php

namespace Base\Field\Behaviour;

use Base\Database\Mapping\EntityShape;
use Base\Database\UnitOfWorks;
use Base\Field\FieldDescriptor;
use Base\Field\Kind;
use Base\Form\Definition\FormBinder;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\Properties;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Runtime;

/**
 * A related record's form embedded in its owner's (Kind::ASSOCIATION; plan "omnibase agnostic", step F5d,
 * slice 7), as omnibase's AssociationType builds it, on every host: one record, its fields; or several (a
 * to-many association), a collection (Collection) whose every entry is one record's form - an article's
 * comments, a comment's replies. What AssociationType decides itself is here, and it asks it:
 *
 * - options(): its defaults and what its normalizers make of them - several records are never required
 *   (no entry is a legitimate value: a Count constraint says why it refuses), an entry is required by
 *   entry_required; entries are added and deleted only inside a group;
 * - collection(): the options of the collection holding several records' forms (`_collection`);
 * - entry(): the options of one record's form among them.
 *
 * And for the hosts without Symfony's forms (the neutral FormView and FormBinder): isMultiple() (the
 * association's own word where the field gives none), targetClass(), fields() (a record's fields as
 * FieldDescriptors, from its shape: RecordFields), records() (the related records, by index), and bind()
 * (what is sent written into them: each entry's fields bound by the neutral binder, an entry added from
 * the prototype a new record told its owner, one taken out removed where the field deletes).
 */
final class Association
{
    public const DEFAULTS = [
        'class' => null, 'form_type' => null, 'embed' => null, 'autoload' => true, 'href' => null, 'html' => false,
        'entry_collapsed' => true, 'max_entries' => null, 'entry_offset' => 0, 'entry_required' => true,
        'fields' => [], 'keep_indexes' => true, 'length' => 0, 'excluded_fields' => [], 'recursive' => null,
        'multiple' => false, 'group' => true, 'row_group' => true, 'allow_add' => true, 'allow_delete' => true,
        'allow_entity' => true, 'allow_null' => null,
    ];

    /** The name of the collection holding several records' forms, and of an entry's index. */
    public const COLLECTION = '_collection';
    public const INDEX = '_index';

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function options(array $options): array
    {
        $options = array_replace(self::DEFAULTS, $options);
        $options['required'] = $options['multiple'] ? false : $options['entry_required'];
        if ($options['multiple']) {
            $options['data_class'] = null;
        }
        $options['allow_add'] = $options['group'] ? ($options['allow_add'] ?? null) : false;
        $options['allow_delete'] = $options['group'] ? ($options['allow_delete'] ?? null) : false;

        return $options;
    }

    /**
     * What the collection of several records' forms is given (AssociationType's `_collection`): its
     * entries this type, each one record's form (entry()).
     *
     * @param array<string, mixed> $options the association's, its class and multiplicity known
     *
     * @return array<string, mixed>
     */
    public static function collection(array $options): array
    {
        $collection = [
            'data_class' => null, 'label' => $options['label'] ?? false, 'html' => $options['html'], 'by_reference' => false,
            'allow_object' => true, 'length' => $options['group'] ? $options['length'] : max(1, $options['length']),
            'group' => $options['group'], 'row_group' => $options['row_group'], 'entry_collapsed' => $options['entry_collapsed'],
            'max_entries' => $options['max_entries'], 'entry_offset' => $options['entry_offset'],
            'entry_options' => self::entry($options),
        ];
        if (null !== $options['allow_add']) {
            $collection['allow_add'] = $options['group'] ? $options['allow_add'] : false;
        }
        if (null !== $options['allow_delete']) {
            $collection['allow_delete'] = $options['group'] ? $options['allow_delete'] : false;
        }

        return $collection;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function entry(array $options): array
    {
        $class = $options['class'] ?? null;
        unset($options['class']);

        return array_merge($options, [
            'href' => $options['href'] ?? null, 'allow_entity' => $options['allow_entity'],
            'data_class' => $class, 'multiple' => false, 'keep_indexes' => $options['keep_indexes'],
        ]);
    }

    /** The related records' class: the field's, else its association's target. */
    public static function targetClass(array $options, ?string $ownerClass, string $property): ?string
    {
        if (\is_string($options['class'] ?? null)) {
            return $options['class'];
        }
        $shape = self::shape($ownerClass);
        $property = self::property($ownerClass, $property);

        return null !== $shape && $shape->hasAssociation($property) ? $shape->targetClass($property) : null;
    }

    /** The association a field's property names: itself, or the one its alias designates (#[Alias]: a comment's replies). */
    public static function property(?string $ownerClass, string $property): string
    {
        $unitOfWork = Runtime::find(UnitOfWork::class);
        if (null === $ownerClass || !$unitOfWork instanceof UnitOfWork) {
            return $property;
        }
        try {
            return (new \Base\Database\Mapping\EntityFields(UnitOfWorks::shapes($unitOfWork)))->name($ownerClass, $property) ?? $property;
        } catch (\Throwable) {
            return $property;
        }
    }

    /** Several records: the field's word, else its association's (FormFactory::guessMultiple()). */
    public static function isMultiple(array $options, ?string $ownerClass, string $property): bool
    {
        $shape = self::shape($ownerClass);
        $property = self::property($ownerClass, $property);
        if (null !== $shape && $shape->hasAssociation($property)) {
            return $shape->isToMany($property);
        }

        return (bool) ($options['multiple'] ?? false);
    }

    /**
     * One record's fields, as AssociationType adds them: those named and, where it autoloads, the record's
     * others; a related record's own form, or a field kept out of records, left out.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, FieldDescriptor>
     */
    public static function fields(string $class, array $options, ?string $formName = null): array
    {
        $fields = RecordFields::of($class, (array) $options['fields'], (array) $options['excluded_fields']);
        if (!$options['autoload']) {
            $fields = array_intersect_key($fields, (array) $options['fields']);
        }
        $descriptors = [];
        foreach ($fields as $name => $field) {
            if ($options['recursive'] && null !== $formName && \array_key_exists($formName, $field)) {
                $field = $field[$formName];
            }
            if (!isset($field['allow_entity'])) {
                $field['allow_entity'] = $options['allow_entity'] ?? false;
            }
            if (null !== $descriptor = RecordFields::descriptor($class, (string) $name, $field)) {
                // What its column says of it (a record's select its class, several of them, its order: Guess).
                $descriptors[(string) $name] = Guess::from($descriptor->setProperty((string) $name), $class);
            }
        }

        return $descriptors;
    }

    /** @return array<int|string, object> the related records, by their index in the association */
    public static function records(mixed $value): array
    {
        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }
        if (null === $value) {
            return [];
        }

        return array_filter(\is_array($value) ? $value : [$value], 'is_object');
    }

    /**
     * What is sent for several records written into them: each entry sent (by its index) bound into the
     * record at that index, or into a new one (an entry added from the prototype, told its owner where the
     * association is its inverse side); a record whose entry was not sent removed where the field deletes,
     * kept otherwise (and always where a windowed page did not draw it). An entry's own collection of
     * records (a comment's replies, listed by a CollectionType) is not written from it, as AssociationType
     * leaves it.
     *
     * @param array<string, mixed>              $submitted the `_collection` part
     * @param array<string, mixed>              $options   the association's, its class known
     * @param array<string, FieldDescriptor>    $fields    one record's fields
     *
     * @return list<\Omnibridge\Contract\Violation> the entries' violations, under `_collection.<index>.<field>`
     */
    public static function bind(object $owner, string $property, array $submitted, array $options, array $fields, FormBinder $binder): array
    {
        $class = (string) $options['class'];
        $collection = Properties::get($owner, $property);
        $records = self::records($collection);
        $rendered = \array_key_exists('_rendered', $submitted) ? Collection::rendered($submitted['_rendered']) : null;
        unset($submitted['_rendered']);
        $fields = array_filter($fields, static fn (FieldDescriptor $field): bool => !\in_array($field->getKind(), [Kind::COLLECTION, Kind::ASSOCIATION], true));
        $definition = FormDefinition::new($property)->setCsrf(false)->setDataClass($class);
        foreach ($fields as $name => $field) {
            $definition->add($field);
        }

        $kept = [];
        $violations = [];
        foreach ($submitted as $index => $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $record = $records[$index] ?? null;
            if (null === $record) {
                if (false === $options['allow_add']) {
                    continue;
                }
                $record = new $class();
            }
            $binding = $binder->bind($definition, array_diff_key($entry, [self::INDEX => true]), $record);
            foreach ($binding->violations as $violation) {
                $violations[] = new \Omnibridge\Contract\Violation(self::COLLECTION.'.'.$index.'.'.$violation->path, $violation->message, $violation->code, $violation->parameters);
            }
            $kept[] = $record;
        }
        foreach ($records as $index => $record) {
            if (\in_array($record, $kept, true)) {
                continue;
            }
            if (false === $options['allow_delete'] || (null !== $rendered && !isset($rendered[(int) $index]))) {
                $kept[] = $record;
            }
        }

        $mappedBy = self::shape($owner::class)?->mappedBy(self::property($owner::class, $property));
        foreach ($records as $record) {
            if (!\in_array($record, $kept, true) && null !== $mappedBy && Properties::isReadable($record, $mappedBy)) {
                $inverse = Properties::get($record, $mappedBy);
                if ($inverse instanceof \Doctrine\Common\Collections\Collection) {
                    $inverse->removeElement($owner);
                } else {
                    Properties::set($record, $mappedBy, null);
                }
            }
        }
        foreach ($kept as $record) {
            if (null === $mappedBy || !Properties::isReadable($record, $mappedBy)) {
                continue;
            }
            $inverse = Properties::get($record, $mappedBy);
            if ($inverse instanceof \Doctrine\Common\Collections\Collection) {
                $inverse->contains($owner) || $inverse->add($owner);
            } elseif ($inverse !== $owner) {
                Properties::set($record, $mappedBy, $owner);
            }
        }
        if ($collection instanceof \Doctrine\Common\Collections\Collection) {
            foreach ($collection->toArray() as $record) {
                \in_array($record, $kept, true) || $collection->removeElement($record);
            }
            foreach ($kept as $record) {
                $collection->contains($record) || $collection->add($record);
            }
        } else {
            Properties::set($owner, $property, $kept);
        }

        return $violations;
    }

    private static function shape(?string $class): ?EntityShape
    {
        $unitOfWork = Runtime::find(UnitOfWork::class);
        if (null === $class || !$unitOfWork instanceof UnitOfWork) {
            return null;
        }
        try {
            $shapes = UnitOfWorks::shapes($unitOfWork);

            return $shapes->has($class) ? $shapes->shape($class) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
