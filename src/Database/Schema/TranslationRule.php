<?php

namespace Base\Database\Schema;

use Base\Database\Entity\Extension\TranslatableInterface;
use Base\Database\Entity\Extension\TranslationInterface;
use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Builder\Draft;
use Omnibridge\Schema\Builder\Rule;
use Omnibridge\Schema\CamelCaseNaming;
use Omnibridge\Schema\Declaration\ClassDeclaration;

/**
 * The translations' mapping, as Omnibridge\Doctrine\Omnibase\DatabaseSubscriber\IntlSubscriber gives it
 * to Doctrine, on the description built without it:
 *
 * - before its declaration is read, a translation class (ThreadIntl, the
 *   TranslationInterface) takes the inheritance of its translatable (Thread):
 *   its type and its discriminator column, its map translated class by class
 *   (each translatable of the map to its own translation class, where one
 *   exists), its subclasses likewise, and its own value, the translatable's;
 * - once the class is whole, a translatable gets the `translations` relation
 *   (one-to-many, mapped by `translatable`, indexed by locale) and a
 *   translation the `translatable` relation (many-to-one, the join column
 *   translatable_id deleted in cascade), the `locale` column (string, 5) and,
 *   on the root of its hierarchy, the unique constraint
 *   <table>_unique_translation over translatable_id and locale (a #[Vault]
 *   adds its column to it first: VaultRule comes before).
 */
final class TranslationRule implements Rule
{
    /**
     * The names the translations are mapped under - omnibridge/doctrine's TranslatableWalker queries them by the same
     * values (SchemaDescriptionTest compares both) -, here so that the description is built with no persistence
     * installed (plan "omnibase agnostic", step F7a; they were read from the walker's class).
     */
    public const LOCALE = 'locale';
    public const FOREIGN_KEY = 'translatable_id';
    public const COLUMN_NAME = 'translations';
    public const SALT = 'unique_translation';

    public function before(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
        if (!is_subclass_of($class->name, TranslationInterface::class)) {
            return;
        }
        if (!str_ends_with($class->name, CamelCaseNaming::TABLE_I18N_SUFFIX)) {
            throw new \LogicException(sprintf('Invalid class name for "%s".', $class->name));
        }

        $translatable = $builder->draft($class->name::getTranslatableEntityClass());

        if (!$class->discriminatorMap) {
            $class->discriminatorMap = array_filter(array_map(
                static fn (string $className) => is_subclass_of($className, TranslatableInterface::class) ? $className::getTranslationEntityClass(false) : null,
                $translatable->discriminatorMap,
            ), static fn ($className) => null !== $className);
        }

        $subClasses = [];
        foreach ($translatable->subClasses as $subClass) {
            $translation = $subClass::getTranslationEntityClass();
            if (\is_string($translation) && $translation !== $class->name) {
                $subClasses[] = $translation;
            }
        }

        $class->subClasses = array_values(array_unique($subClasses));
        $class->inheritanceType = $translatable->inheritanceType;
        $class->discriminatorColumn = $translatable->discriminatorColumn;
        if ($class->discriminatorMap) {
            if (!in_array($class->name, $class->discriminatorMap, true)) {
                throw new \LogicException(sprintf('Discriminator map missing for "%s".', $class->name));
            }
            $class->discriminatorValue = array_flip($translatable->discriminatorMap)[$translatable->name] ?? throw new \LogicException(sprintf('Discriminator value missing for "%s".', $class->name));
        }
    }

    public function declared(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
    }

    public function loaded(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
        if ($class->isMappedSuperclass) {
            return;
        }

        if (is_subclass_of($class->name, TranslatableInterface::class) && !$class->hasAssociation(self::COLUMN_NAME)) {
            $class->mapRelation([
                'fieldName' => self::COLUMN_NAME,
                'type' => 'one_to_many',
                'mappedBy' => 'translatable',
                'indexBy' => self::LOCALE,
                'targetEntity' => $class->name::getTranslationEntityClass(),
            ]);
        }

        if (!is_subclass_of($class->name, TranslationInterface::class)) {
            return;
        }

        if (!$class->hasAssociation('translatable')) {
            $class->mapRelation([
                'fieldName' => 'translatable',
                'type' => 'many_to_one',
                'inversedBy' => self::COLUMN_NAME,
                'joinColumns' => [['name' => self::FOREIGN_KEY, 'referencedColumnName' => 'id', 'onDelete' => 'CASCADE']],
                'targetEntity' => $class->name::getTranslatableEntityClass(),
            ]);
        }

        if ($class->isRootEntity()) {
            $name = $builder->naming()->classToTableName($class->rootEntityName).'_'.self::SALT;
            $class->table['uniqueConstraints'][$name] ??= [];
            $class->table['uniqueConstraints'][$name]['columns'] = array_unique(array_merge(
                $class->table['uniqueConstraints'][$name]['columns'] ?? [],
                [self::FOREIGN_KEY, self::LOCALE],
            ));
        }

        if (!$class->hasField(self::LOCALE) && !$class->hasAssociation(self::LOCALE)) {
            $class->mapField(['fieldName' => self::LOCALE, 'type' => 'string', 'length' => 5]);
        }
    }
}
