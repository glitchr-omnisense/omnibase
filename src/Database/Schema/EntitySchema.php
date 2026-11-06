<?php

namespace Base\Database\Schema;

use Base\Database\Type\EnumType;
use Base\Database\Type\SetType;
use Base\Service\Model\ClassAliases;
use Base\Service\Model\ClassFinder;
use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Builder\Rule;
use Omnibridge\Schema\CamelCaseNaming;
use Omnibridge\Schema\Database\ColumnTypes;
use Omnibridge\Schema\Declaration\AttributeReader;
use Omnibridge\Schema\Declaration\Declarations;
use Omnibridge\Schema\Description;

/**
 * The schema of omnibase's entities and an application's, with no persistence
 * installed (plan "omnibase agnostic", answer 11 and step F7a): their
 * description (Omnibridge\Schema\Description) built from the entities'
 * folders - their declarations read by reflection, omnibase's naming and its
 * rules in the order Doctrine's listeners run them -, and what omnibase's own
 * column types are (Omnibridge\Schema\Database\ColumnTypes): its enumerations'
 * and sets' values, the serialized arrays and the passkeys' columns as the
 * portable types they are written as.
 *
 * What omnibridge:schema:create reads on every host (omnibridge/symfony's
 * console, omnibridge/laravel's artisan, src/Resources/schema.php for
 * omnisense/omnibridge's plain-PHP script):
 *
 *     $options = ['project_dir' => $projectDir];
 *     $description = EntitySchema::describe(EntitySchema::directories($projectDir), $options);
 *     (new Omnibridge\Schema\Database\SchemaCreator($pdo, EntitySchema::columnTypes($options)))->create($description);
 *
 * Options: project_dir (its src/Entity, src/Enum; the current folder by
 * default), enumerations (more folders of enumerations), types (column types
 * by name, a bundle's enumerations among them), resolve_target_entities
 * (interface => class), declarations (a Declarations source in place of the
 * attributes), rules (more rules, after omnibase's), column_types (more
 * ColumnTypes, over omnibase's: an application's own column types).
 *
 * The description is omnibridge/doctrine's Describer's (the same classes, the
 * same rules: SchemaDescriptionTest compares them), which feeds Doctrine; the
 * column types give the SQL Doctrine's types declare (the same test).
 */
final class EntitySchema
{
    /**
     * omnibase's column types that are no enumeration, by the portable type they are written as: the serialized
     * arrays of before DBAL 4 (a CLOB), the passkeys' base64 values and AAGUID (CLOBs), their trust path (JSON) -
     * omnibridge/doctrine's ArrayType and Passkey\{Base64, Aaguid, TrustPath}. Its datetime types (datetime,
     * datetimetz, utc_datetime, utc_datetime_immutable) are portable types already.
     */
    public const ALIASES = [
        'array' => 'text',
        'passkey_base64' => 'text',
        'passkey_aaguid' => 'text',
        'passkey_trust_path' => 'json',
    ];

    /**
     * The description of the entities the folders hold.
     *
     * @param list<string>         $directories the entities' folders (directories())
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

        $declarations = $options['declarations'] ?? null;

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
     * The column types by name that are classes of omnibase's: those given, then the enumerations of the core, of the
     * project (src/Enum) and of the folders given, each under its name (the first of a name kept).
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, class-string>
     */
    public static function types(array $options = []): array
    {
        $projectDir = rtrim((string) ($options['project_dir'] ?? getcwd()), '/');
        $types = $options['types'] ?? [];
        foreach (array_merge([self::coreDirectory('Enum'), $projectDir.'/src/Enum'], $options['enumerations'] ?? []) as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            foreach (ClassFinder::classes($directory) as $className) {
                // A plain PHP enum (Base\Enum\Allergen) is no column type.
                if (class_exists($className) && is_subclass_of($className, EnumType::class) && !(new \ReflectionClass($className))->isAbstract()) {
                    $types[$className::getStaticName()] ??= $className;
                }
            }
        }

        return $types;
    }

    /**
     * omnibase's column types as data: ALIASES, each enumeration's values (an ENUM on MySQL), each set's (a SET), then
     * the column_types given.
     *
     * @param array<string, mixed> $options
     */
    public static function columnTypes(array $options = []): ColumnTypes
    {
        $enumerations = [];
        $sets = [];
        foreach (self::types($options) as $name => $className) {
            // An enumeration without a value (BritishImperialUnits) types no column: getPermittedValues() refuses it.
            if (is_subclass_of($className, EnumType::class) && [] === (new \ReflectionClass($className))->getConstants()) {
                continue;
            }
            if (is_subclass_of($className, SetType::class)) {
                $sets[$name] = array_values($className::getPermittedValues());
            } elseif (is_subclass_of($className, EnumType::class)) {
                $enumerations[$name] = array_values($className::getPermittedValues());
            }
        }

        $types = new ColumnTypes(self::ALIASES, $enumerations, $sets);
        $more = $options['column_types'] ?? null;

        return $more instanceof ColumnTypes ? $types->with($more) : $types;
    }

    /**
     * The entities' folders of an omnibase application: the core's src/Entity, the project's src/Entity if it has one,
     * then those given (a bundle's).
     *
     * @param list<string> $more
     *
     * @return list<string>
     */
    public static function directories(string $projectDir, array $more = []): array
    {
        $directories = [self::coreDirectory('Entity')];
        if (is_dir($projectDir.'/src/Entity')) {
            $directories[] = rtrim($projectDir, '/').'/src/Entity';
        }

        return array_values(array_unique(array_merge($directories, $more)));
    }

    /** A folder of the core's src/ (Entity, Enum). */
    public static function coreDirectory(string $folder): string
    {
        return \dirname(__DIR__, 2).'/'.$folder;
    }
}
