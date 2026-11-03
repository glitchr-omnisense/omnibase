<?php

namespace Base\Field\Behaviour;

use Base\Database\Mapping\EntityShape;
use Base\Database\Type\Enumerations;
use Base\Database\UnitOfWorks;
use Base\Field\FieldDescriptor;
use Base\Field\Kind;
use Base\Field\Kinds;
use Base\Form\Definition\FormDefinition;
use Omnibridge\Persistence\UnitOfWork;
use Omnibridge\Runtime;

/**
 * A record's fields as a form embedding it lists them (plan "omnibase agnostic", step F5d): what
 * ClassMetadataManipulator::getFields() answers omnibase's TranslationType and AssociationType from
 * Doctrine's metadata, read here from the entity's shape (the unit of work's EntityShapes) on any host.
 *
 * of() gives the fields named first, in their order, then the record's other columns in its order (the
 * identifier, the translation's own record and language left out, and the excluded ones), each with the
 * kind its column says - a string a text, a text a textarea, a list (json, array) a select of free tags,
 * an integer a number, a date and time a datetime picker, an enumeration a select of its values - and
 * the options given over it; a column no kind fits is left out. descriptor() makes one of them a field
 * as AssociationType adds it: its kind (a host type named by `form_type` read as the kind it renders), its
 * options, not required where its column may be empty and nothing says otherwise.
 */
final class RecordFields
{
    /** What every embedded form leaves out (ClassMetadataManipulator's global excluded fields). */
    public const EXCLUDED = ['id', 'translatable', 'locale'];

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $fields   name => options (a host type's name alone, or [])
     * @param list<string>         $excluded
     *
     * @return array<string, array<string, mixed>> name => options, with `kind` or `form_type`
     */
    public static function of(string $class, array $fields = [], array $excluded = []): array
    {
        if ([] !== $fields && array_is_list($fields)) {
            throw new \InvalidArgumentException("Associative array expected for 'fields' parameter, '".\gettype($fields)."' received");
        }
        $shape = self::shape($class);
        if (null === $shape) {
            throw new \RuntimeException(sprintf('No shape of "%s" in the unit of work: its fields cannot be listed.', $class));
        }
        $keys = array_keys($fields);

        $valid = [];
        foreach ($shape->fieldNames() as $name) {
            $valid[$name] = str_starts_with($name, '_') ? [] : self::guess($shape, $name);
        }
        foreach ($shape->associationNames() as $name) {
            // The related records a form could embed (their side of the association): a translations field, or an
            // embedded form's own, which an embedding form does not add.
            if ($shape->isInverseSide($name)) {
                $valid[$name] = 'translations' === $name ? ['kind' => Kind::TRANSLATION, 'translatable_class' => $shape->className()] : ['kind' => Kind::ASSOCIATION, 'class' => $shape->targetClass($name)];
            }
        }

        $fields = array_map(static fn (mixed $field): mixed => \is_array($field) || null === $field ? $field : ['form_type' => $field], $fields);
        foreach ($fields as $name => $field) {
            if (\is_array($field) && [] !== $field) {
                if (!isset($field['form_type']) && !isset($field['kind'])) {
                    $field += array_intersect_key($valid[$name] ?? [], ['form_type' => true, 'kind' => true]);
                }
                $valid[$name] = $field;
                unset($fields[$name]);
            }
        }

        $excluded = array_merge(self::EXCLUDED, $excluded);
        $valid = array_filter($valid, static fn (string $name): bool => !\in_array($name, $excluded, true), \ARRAY_FILTER_USE_KEY);
        if ([] === $fields) {
            return array_filter(array_replace(array_fill_keys($keys, null), $valid));
        }

        $unmapped = [];
        $unknown = [];
        foreach ($fields as $name => $field) {
            if (\in_array($name, $excluded, true) || \array_key_exists($name, $valid)) {
                continue;
            }
            if (false === ($field['mapped'] ?? true)) {
                $unmapped[$name] = $field;
                continue;
            }
            $unknown[] = $name;
        }
        if ([] !== $unknown) {
            throw new \RuntimeException(sprintf("Field(s) '%s' doesn't exist in %s", implode(', ', $unknown), $class));
        }
        foreach ($fields as $name => $field) {
            if (\in_array($name, $excluded, true) || isset($unmapped[$name]) || null === $field) {
                continue;
            }
            if (false === ($field['display'] ?? true)) {
                unset($valid[$name]);
                continue;
            }
            $valid[$name] = $field + $valid[$name];
        }

        return array_filter(array_replace(array_fill_keys($keys, null), $valid + $unmapped));
    }

    /**
     * One of them as a field (AssociationType's child): null where nothing renders it - no kind, a
     * related record (an embedded form's own), a field kept out of records (allow_entity: false).
     *
     * @param array<string, mixed> $field
     */
    public static function descriptor(string $class, string $name, array $field): ?FieldDescriptor
    {
        $type = $field['form_type'] ?? null;
        $kind = $field['kind'] ?? (\is_string($type) ? self::kindOf($type) : null);
        if (null === $kind || Kind::ASSOCIATION === $kind || false === ($field['allow_entity'] ?? true)) {
            return null;
        }
        if (!\array_key_exists('required', $field) && (self::shape($class)?->entity->fields[$name]->nullable ?? false)) {
            $field['required'] = false;
        }
        $label = $field['label'] ?? null;
        $required = $field['required'] ?? null;
        unset($field['form_type'], $field['kind'], $field['allow_entity'], $field['label'], $field['required'], $field['locale_options'], $field['display']);

        $descriptor = FormDefinition::field($name, $kind)->setFormTypeOptions($field);
        if (Kind::CUSTOM === $kind && \is_string($type)) {
            $descriptor->setFormType($type);
        }
        if (null !== $label) {
            $descriptor->setLabel($label);
        }
        if (\is_bool($required)) {
            $descriptor->setRequired($required);
        }

        return $descriptor;
    }

    /**
     * The kind a host type's name renders: the host's map (Kinds::kind()), else its name read as the
     * kind's (Base\Field\Type\TextareaType or Symfony's TextareaType a textarea, SelectType a choice,
     * EmojiPickerType an emoji); a type no kind names is custom, the type its own.
     */
    public static function kindOf(string $type): string
    {
        foreach (array_keys(Kinds::ADAPTERS) as $host) {
            if (null !== $kind = Kinds::kind($type, $host)) {
                return $kind;
            }
        }
        $short = preg_replace('/Type$/', '', substr(strrchr('\\'.ltrim($type, '\\'), '\\'), 1));
        $name = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', (string) $short));
        $name = ['select' => Kind::CHOICE, 'entity' => Kind::ASSOCIATION, 'date_time' => Kind::DATETIME, 'date_time_picker' => Kind::DATETIME_PICKER, 'emoji_picker' => Kind::EMOJI][$name] ?? $name;

        return \in_array($name, Kind::all(), true) ? $name : Kind::CUSTOM;
    }

    /** @return array<string, mixed> what a column says of its field, [] when nothing fits */
    private static function guess(EntityShape $shape, string $name): array
    {
        if ('id' === $name || 'uuid' === $name) {
            return ['kind' => Kind::HIDDEN];
        }
        $type = $shape->fieldType($name);
        $enum = $shape->entity->fields[$name]->enum ?? null;
        if (\is_string($enum) && '' !== $enum) {
            return ['kind' => Kind::CHOICE, 'class' => $enum];
        }

        return match ($type) {
            'datetime', 'datetime_immutable' => ['kind' => Kind::DATETIME_PICKER],
            'array', 'json' => ['kind' => Kind::CHOICE, 'tags' => true],
            'integer' => ['kind' => Kind::NUMBER],
            'string' => ['kind' => Kind::TEXT],
            'text' => ['kind' => Kind::TEXTAREA],
            default => null !== ($enumeration = Enumerations::classOf($type)) ? ['kind' => Kind::CHOICE, 'class' => $enumeration] : [],
        };
    }

    private static function shape(string $class): ?EntityShape
    {
        $unitOfWork = Runtime::find(UnitOfWork::class);
        if (!$unitOfWork instanceof UnitOfWork) {
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
