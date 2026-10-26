<?php

namespace Base\Database\Schema;

use Base\Bridge\Doctrine\Types;
use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;
use Base\Service\Model\ClassAliases;
use Base\Service\Model\ClassFinder;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Types\Type;
use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Builder\Rule;
use Omnibridge\Schema\CamelCaseNaming;
use Omnibridge\Schema\Column;
use Omnibridge\Schema\Database\MySqlSchema;
use Omnibridge\Schema\Declaration\AttributeReader;
use Omnibridge\Schema\Declaration\Declarations;
use Omnibridge\Schema\Description;

/**
 * The description of an omnibase application's data (Omnibridge\Schema\
 * Description), built without any ORM from the entities' folders: their
 * declarations read by reflection, omnibase's naming, and omnibase's rules in
 * the order Doctrine's listeners run - the discriminators, the vaults, the
 * order columns, the translations, the extensions, the targets resolved
 * (audit "lesringostars face au master plan", R14).
 *
 *     $description = Describer::describe([$vendor.'/omnisense/omnibase/src/Entity', $projectDir.'/src/Entity'], ['project_dir' => $projectDir]);
 *
 * Options: project_dir (its src/Enum, as Mapping::register()), enumerations
 * (more folders of enumerations), types (by name, a bundle's), resolve_target_entities
 * (interface => class), declarations (a Declarations source in place of the
 * attributes: a set kept as data), rules (more rules, after omnibase's).
 *
 * Doctrine, on the same classes, gives the same description
 * (omnibridge/doctrine's MetadataDescription: tests/Bridge/Doctrine/SchemaDescriptionTest),
 * and a MySQL database made from it compares equal (mysqlTypes(): the SQL type
 * of each of omnibase's own columns - an ENUM or a SET of the enumeration's
 * values, a long text for the serialized arrays and the passkeys' columns).
 */
final class Describer
{
    /**
     * @param list<string>         $directories the entities' folders
     * @param array<string, mixed> $options
     */
    public static function describe(array $directories, array $options = []): Description
    {
        // App\Entity\X is Base\Entity\X where the application has none of its own: associations name them.
        ClassAliases::register();

        $classes = [];
        foreach ($directories as $directory) {
            $classes = array_merge($classes, ClassFinder::classes($directory));
        }

        $declarations = $options['declarations'] ?? new AttributeReader();

        return (new Builder($declarations instanceof Declarations ? $declarations : new AttributeReader(), new CamelCaseNaming(), self::rules($options)))->build($classes);
    }

    /**
     * omnibase's rules, in the order Doctrine's listeners run.
     *
     * @param array<string, mixed> $options
     *
     * @return list<Rule>
     */
    public static function rules(array $options = []): array
    {
        return [
            new DiscriminatorRule(),
            new VaultRule(),
            new OrderColumnRule(self::types($options)),
            new TranslationRule(),
            new TargetRule($options['resolve_target_entities'] ?? []),
            new ExtensionRule(),
            ...($options['rules'] ?? []),
        ];
    }

    /**
     * The column types by name: the core's, the enumerations of the core and of the project, those given.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, class-string>
     */
    public static function types(array $options = []): array
    {
        $projectDir = rtrim($options['project_dir'] ?? (string) getcwd(), '/');

        return Types::core() + ($options['types'] ?? []) + Types::enumerations(array_merge([Types::coreEnumerationDirectory(), $projectDir.'/src/Enum'], $options['enumerations'] ?? []));
    }

    /**
     * The SQL type MySQL writes for each of omnibase's own column types (Omnibridge\Schema\Database\MySqlSchema's
     * $types): ENUM or SET of the enumeration's values; for any other type of the family, what the type itself
     * declares for MySQL (a long text for the serialized arrays, a tiny text for a passkey's aaguid, json for its
     * trust path), lower-cased as information_schema writes it.
     *
     * @param array<string, class-string> $types by name (types())
     *
     * @return array<string, \Closure(Column): string>
     */
    public static function mysqlTypes(array $types): array
    {
        $sql = [];
        foreach ($types as $name => $className) {
            if (is_subclass_of($className, SetType::class)) {
                $sql[$name] = static fn (Column $column) => MySqlSchema::set(new Column($column->name, $name, values: array_values($className::getPermittedValues())));
            } elseif (is_subclass_of($className, EnumType::class)) {
                $sql[$name] = static fn (Column $column) => MySqlSchema::enumeration(new Column($column->name, $name, values: array_values($className::getPermittedValues())));
            } elseif (is_subclass_of($className, Type::class) && !str_starts_with($className, 'Doctrine\\')) {
                $sql[$name] = static function (Column $column) use ($name, $className): string {
                    if (!Type::hasType($name)) {
                        Type::addType($name, $className);
                    }
                    $declaration = Type::getType($name)->getSQLDeclaration(array_filter([
                        'length' => $column->length, 'precision' => $column->precision, 'scale' => $column->scale,
                        'fixed' => $column->fixed, 'unsigned' => $column->unsigned, 'notnull' => !$column->nullable,
                    ], static fn ($value) => null !== $value), new MySQLPlatform());

                    return strtolower(preg_replace('/\s+/', ' ', $declaration) ?? $declaration);
                };
            }
        }

        return $sql;
    }
}
