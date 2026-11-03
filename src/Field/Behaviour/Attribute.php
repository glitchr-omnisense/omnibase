<?php

namespace Base\Field\Behaviour;

use Base\Database\Entity\Extension\TranslatableInterface;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractActionAdapter;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractAdapter;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractRuleAdapter;
use Base\Entity\Layout\Attribute\Adapter\Common\AbstractScopeAdapter;
use Base\Entity\Layout\Attribute\Common\AbstractAction;
use Base\Entity\Layout\Attribute\Common\AbstractAttribute;
use Base\Entity\Layout\Attribute\Common\AbstractRule;
use Base\Entity\Layout\Attribute\Common\AbstractScope;
use Base\Field\FieldDescriptor;
use Base\Field\Kind;
use Base\Form\Definition\FormBinder;
use Base\Form\Definition\FormDefinition;
use Base\Form\Definition\Properties;

/**
 * A record's attributes (Kind::ATTRIBUTE; plan "omnibase agnostic", step F5d, slice 7) - an account's
 * hyperlinks -, as omnibase's AttributeType builds them, on every host: a select of the adapters offered
 * (searched remotely, the field's `filter` narrowing them to its adapters' classes), then, for each
 * attribute held, its value drawn by its adapter's field (a hyperlink's pattern, a number...). What
 * AttributeType decides itself is here, and it asks it:
 *
 * - options(): its defaults;
 * - classes(): the attribute's class (the field's, else Attribute) and its adapters' (a rule's, a scope's,
 *   an action's, any adapter otherwise);
 * - choice(): the options of its select (`choice`): the adapters' class, their code searched, the filter,
 *   disabled while its record is not stored;
 * - key() and value(): an attribute's part, named by its adapter's code and its id, and the field its
 *   adapter draws its value with.
 *
 * And for the hosts without Symfony's forms: bind() writes what is sent into the record's attributes - an
 * attribute kept for each adapter still chosen (its value written from its part), a new one for an adapter
 * newly chosen, the others taken out. A translated attribute's value (its `intl` part, a TranslationType of
 * several records at once) is not drawn there: such a field, once it holds one, is refused aloud by those hosts
 * (translated()). The core's attributes (Attribute, Hyperlink) are all translated: on those hosts, the adapters
 * are chosen and the attributes made or taken out; their values are written where Symfony's forms draw them.
 */
final class Attribute
{
    public const DEFAULTS = [
        'abstract_class' => null, 'class' => null, 'recursive' => false, 'multiple' => null, 'multivalue' => false,
        'filter' => null, 'filter_code' => null, 'sortable' => null, 'allow_add' => true, 'allow_delete' => true,
    ];

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
        if (null !== $options['class'] && !class_exists($options['class'])) {
            throw new \InvalidArgumentException('"class" option is "'.$options['class'].'", but the class itself doesn\'t exists');
        }
        if (null !== $options['abstract_class'] && (!class_exists($options['abstract_class']) || !is_a($options['abstract_class'], AbstractAdapter::class, true))) {
            throw new \InvalidArgumentException('"abstract_class" option is "'.$options['abstract_class'].'", but doesn\'t inherit from "'.AbstractAdapter::class.'"');
        }
        if ($options['multiple']) {
            $options['data_class'] = null;
        }

        return $options;
    }

    /**
     * The attribute's class and its adapters', as AttributeType settles them before building its parts.
     *
     * @param array<string, mixed> $options
     *
     * @return array{class: class-string, abstract_class: class-string}
     */
    public static function classes(array $options, ?string $guessed = null): array
    {
        $class = $options['class'] ?? $guessed;
        if (!\is_string($class) || !is_a($class, AbstractAttribute::class, true)) {
            $class = \Base\Entity\Layout\Attribute::class;
        }
        $abstract = match (true) {
            is_a($class, AbstractRule::class, true) => AbstractRuleAdapter::class,
            is_a($class, AbstractScope::class, true) => AbstractScopeAdapter::class,
            is_a($class, AbstractAction::class, true) => AbstractActionAdapter::class,
            default => $options['abstract_class'] ?? AbstractAdapter::class,
        };

        return ['class' => $class, 'abstract_class' => $abstract];
    }

    /**
     * Its select of adapters (SelectType's options, as AttributeType gives them).
     *
     * @param array<string, mixed> $options the attribute's, its classes, multiplicity and order known
     *
     * @return array<string, mixed>
     */
    public static function choice(array $options, ?object $record): array
    {
        return [
            'class' => $options['abstract_class'],
            'autocomplete_fields' => ['code' => $options['filter_code']],
            'choice_filter' => $options['filter'],
            'multiple' => $options['multiple'],
            'multivalue' => $options['multivalue'],
            'href' => false,
            'disable' => null !== $record && method_exists($record, 'getId') && null === $record->getId(),
            'sortable' => $options['sortable'],
            'dropdownCssClass' => 'field-attribute-dropdown',
            'containerCssClass' => 'field-attribute-selection',
        ];
    }

    /** @return list<AbstractAttribute> the attributes held, the first only for a single one */
    public static function held(mixed $value, bool $multiple): array
    {
        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value, false);
        }
        $held = array_values(array_filter(null === $value ? [] : (\is_array($value) ? $value : [$value]), static fn (mixed $attribute): bool => $attribute instanceof AbstractAttribute));

        return $multiple ? $held : \array_slice($held, 0, 1);
    }

    /** An attribute's part's name: its adapter's code and its id. */
    public static function key(AbstractAttribute $attribute): string
    {
        return $attribute->getAdapter()?->getCode().'-'.$attribute->getId();
    }

    public static function translated(AbstractAttribute $attribute): bool
    {
        return $attribute instanceof TranslatableInterface;
    }

    /**
     * The field an attribute's value is drawn with: its adapter's type and options, its label and help, never
     * required (AttributeType's part: an AssociationType of the one field `value`).
     */
    public static function value(AbstractAttribute $attribute): FieldDescriptor
    {
        $adapter = $attribute->getAdapter();
        $field = array_merge($adapter?->getOptions() ?? [], [
            'label' => $adapter?->getLabel(), 'help' => null !== $adapter && method_exists($adapter, 'getHelp') ? $adapter->getHelp() : null,
            'required' => false, 'form_type' => null !== $adapter ? $adapter::getType() : null,
        ]);
        $descriptor = RecordFields::descriptor($attribute::class, 'value', $field) ?? FormDefinition::field('value', Kind::TEXT);
        if (null !== $field['help']) {
            $descriptor->setHelp((string) $field['help']);
        }

        return $descriptor->setProperty('value');
    }

    /**
     * What is sent written into the record's attributes (AttributeType::mapFormsToData()): the adapters
     * chosen (their ids under `choice`), each attribute kept for an adapter still chosen - its value written
     * from its part ({code}-{id}[value]) -, a new one for an adapter newly chosen.
     *
     * @param array<string, mixed> $submitted
     * @param array<string, mixed> $options   the attribute's, its classes known
     * @param list<AbstractAdapter> $chosen   the adapters chosen, as the select's binding reads them
     */
    public static function bind(object $owner, string $property, array $submitted, array $options, array $chosen, FormBinder $binder): void
    {
        $class = $options['class'];
        $held = self::held(Properties::get($owner, $property), true);
        if (!$options['multiple']) {
            $adapter = $chosen[0] ?? null;
            Properties::set($owner, $property, $adapter instanceof AbstractAdapter ? ($held[0] ?? null)?->getAdapter() === $adapter ? $held[0] : new $class($adapter) : null);

            return;
        }
        $kept = [];
        foreach ($chosen as $adapter) {
            if (!$adapter instanceof AbstractAdapter) {
                throw new \InvalidArgumentException('Invalid argument passed to attribute choice, expected class inheriting form '.AbstractAdapter::class);
            }
            $attribute = null;
            foreach ($held as $candidate) {
                if ($candidate->getAdapter() === $adapter) {
                    $attribute = $candidate;
                    break;
                }
            }
            if (null === $attribute) {
                $kept[] = new $class($adapter);
                continue;
            }
            $part = $submitted[self::key($attribute)] ?? null;
            if (\is_array($part) && \array_key_exists('value', $part) && !self::translated($attribute)) {
                $value = $binder->reverse($part['value'], Behaviours::complete(self::value($attribute)));
                $attribute->set(value: null === $value || [] === $value ? null : $value);
            }
            $kept[] = $attribute;
        }
        foreach ($held as $attribute) {
            if (!\in_array($attribute, $kept, true) && $attribute instanceof TranslatableInterface) {
                $attribute->clearTranslations();
            }
        }
        $collection = Properties::get($owner, $property);
        if ($collection instanceof \Doctrine\Common\Collections\Collection) {
            $collection->clear();
            foreach ($kept as $attribute) {
                $collection->add($attribute);
            }

            return;
        }
        Properties::set($owner, $property, $kept);
    }
}
