<?php

namespace Base\Contract;

/**
 * The generic contracts of omnisense/omnibridge under the names an application writes: Base\ (provided) and App\
 * (the application) are the only namespaces its code names, so each class of omnisense/omnibridge - the host's
 * contracts, the persistence's, the schema's description, the validation's, the options' - has a Base\ name, by one
 * rule (NAMES). They are the same types (class_alias): an object a host or a persistence built answers both names,
 * in a type declaration, an instanceof, an implements.
 *
 * Declared at once when the core is loaded (src/Resources/Functions.php): PHP checks a type against a name without
 * autoloading it, so an alias declared late would refuse the very object it names. Base\Contract\Runtime is the
 * core's own class (it extends omnisense/omnibridge's registry) and is not listed. A host's container answers the
 * Base\ ids as well: NAMES is the list it aliases.
 */
final class Contracts
{
    /** @var array<string, string> the Base\ name => the class of omnisense/omnibridge */
    public const NAMES = [
        'Base\Contract\Access' => 'Omnibridge\Contract\Access',
        'Base\Contract\Backend' => 'Omnibridge\Persistence\Backend',
        'Base\Contract\Container' => 'Omnibridge\Container',
        'Base\Contract\Container\Declarations' => 'Omnibridge\Container\Declarations',
        'Base\Contract\Container\Discovery' => 'Omnibridge\Container\Discovery',
        'Base\Contract\Environment' => 'Omnibridge\Contract\Environment',
        'Base\Contract\Files' => 'Omnibridge\Contract\Files',
        'Base\Contract\Lifecycle\EntityEvent' => 'Omnibridge\Persistence\Lifecycle\EntityEvent',
        'Base\Contract\Lifecycle\FlushEvent' => 'Omnibridge\Persistence\Lifecycle\FlushEvent',
        'Base\Contract\Lifecycle\Listeners' => 'Omnibridge\Persistence\Lifecycle\Listeners',
        'Base\Contract\Lifecycle\OnFlush' => 'Omnibridge\Persistence\Lifecycle\OnFlush',
        'Base\Contract\Lifecycle\PostFlush' => 'Omnibridge\Persistence\Lifecycle\PostFlush',
        'Base\Contract\Lifecycle\PostLoad' => 'Omnibridge\Persistence\Lifecycle\PostLoad',
        'Base\Contract\Lifecycle\PostPersist' => 'Omnibridge\Persistence\Lifecycle\PostPersist',
        'Base\Contract\Lifecycle\PostRemove' => 'Omnibridge\Persistence\Lifecycle\PostRemove',
        'Base\Contract\Lifecycle\PostUpdate' => 'Omnibridge\Persistence\Lifecycle\PostUpdate',
        'Base\Contract\Lifecycle\PreFlush' => 'Omnibridge\Persistence\Lifecycle\PreFlush',
        'Base\Contract\Lifecycle\PrePersist' => 'Omnibridge\Persistence\Lifecycle\PrePersist',
        'Base\Contract\Lifecycle\PreRemove' => 'Omnibridge\Persistence\Lifecycle\PreRemove',
        'Base\Contract\Lifecycle\PreUpdate' => 'Omnibridge\Persistence\Lifecycle\PreUpdate',
        'Base\Contract\Locales' => 'Omnibridge\Contract\Locales',
        'Base\Contract\Persistences' => 'Omnibridge\Persistence\Persistences',
        'Base\Contract\Requests' => 'Omnibridge\Contract\Requests',
        'Base\Contract\Roles' => 'Omnibridge\Contract\Roles',
        'Base\Contract\Routes' => 'Omnibridge\Contract\Routes',
        'Base\Contract\Schedule' => 'Omnibridge\Persistence\Schedule',
        'Base\Contract\Scope' => 'Omnibridge\Persistence\Scope',
        'Base\Contract\Sessions' => 'Omnibridge\Contract\Sessions',
        'Base\Contract\Snapshots' => 'Omnibridge\Persistence\Snapshots',
        'Base\Contract\SystemClock' => 'Omnibridge\SystemClock',
        'Base\Contract\Tokens' => 'Omnibridge\Contract\Tokens',
        'Base\Contract\Translations' => 'Omnibridge\Contract\Translations',
        'Base\Contract\UnitOfWork' => 'Omnibridge\Persistence\UnitOfWork',
        'Base\Contract\Urls' => 'Omnibridge\Contract\Urls',
        'Base\Contract\Validation' => 'Omnibridge\Contract\Validation',
        'Base\Contract\ViewSources' => 'Omnibridge\Contract\ViewSources',
        'Base\Contract\Views' => 'Omnibridge\Contract\Views',
        'Base\Contract\Violation' => 'Omnibridge\Contract\Violation',
        'Base\Database\Schema\Builder' => 'Omnibridge\Schema\Builder',
        'Base\Database\Schema\Builder\Draft' => 'Omnibridge\Schema\Builder\Draft',
        'Base\Database\Schema\Builder\Rule' => 'Omnibridge\Schema\Builder\Rule',
        'Base\Database\Schema\Builder\Tables' => 'Omnibridge\Schema\Builder\Tables',
        'Base\Database\Schema\CamelCaseNaming' => 'Omnibridge\Schema\CamelCaseNaming',
        'Base\Database\Schema\Column' => 'Omnibridge\Schema\Column',
        'Base\Database\Schema\Database\Cli' => 'Omnibridge\Schema\Database\Cli',
        'Base\Database\Schema\Database\ColumnTypes' => 'Omnibridge\Schema\Database\ColumnTypes',
        'Base\Database\Schema\Database\Literals' => 'Omnibridge\Schema\Database\Literals',
        'Base\Database\Schema\Database\MySqlSchema' => 'Omnibridge\Schema\Database\MySqlSchema',
        'Base\Database\Schema\Database\MySqlStatements' => 'Omnibridge\Schema\Database\MySqlStatements',
        'Base\Database\Schema\Database\SchemaCreator' => 'Omnibridge\Schema\Database\SchemaCreator',
        'Base\Database\Schema\Database\SchemaNotEmpty' => 'Omnibridge\Schema\Database\SchemaNotEmpty',
        'Base\Database\Schema\Database\SqliteSchema' => 'Omnibridge\Schema\Database\SqliteSchema',
        'Base\Database\Schema\Database\SqliteStatements' => 'Omnibridge\Schema\Database\SqliteStatements',
        'Base\Database\Schema\Database\Statements' => 'Omnibridge\Schema\Database\Statements',
        'Base\Database\Schema\Declaration\Attribute' => 'Omnibridge\Schema\Declaration\Attribute',
        'Base\Database\Schema\Declaration\AttributeReader' => 'Omnibridge\Schema\Declaration\AttributeReader',
        'Base\Database\Schema\Declaration\ClassDeclaration' => 'Omnibridge\Schema\Declaration\ClassDeclaration',
        'Base\Database\Schema\Declaration\DeclarationSet' => 'Omnibridge\Schema\Declaration\DeclarationSet',
        'Base\Database\Schema\Declaration\Declarations' => 'Omnibridge\Schema\Declaration\Declarations',
        'Base\Database\Schema\Declaration\Member' => 'Omnibridge\Schema\Declaration\Member',
        'Base\Database\Schema\Declaration\Vocabulary' => 'Omnibridge\Schema\Declaration\Vocabulary',
        'Base\Database\Schema\Description' => 'Omnibridge\Schema\Description',
        'Base\Database\Schema\Entity' => 'Omnibridge\Schema\Entity',
        'Base\Database\Schema\Exports' => 'Omnibridge\Schema\Exports',
        'Base\Database\Schema\Field' => 'Omnibridge\Schema\Field',
        'Base\Database\Schema\ForeignKey' => 'Omnibridge\Schema\ForeignKey',
        'Base\Database\Schema\Index' => 'Omnibridge\Schema\Index',
        'Base\Database\Schema\Inheritance' => 'Omnibridge\Schema\Inheritance',
        'Base\Database\Schema\JoinColumn' => 'Omnibridge\Schema\JoinColumn',
        'Base\Database\Schema\JoinTable' => 'Omnibridge\Schema\JoinTable',
        'Base\Database\Schema\Naming' => 'Omnibridge\Schema\Naming',
        'Base\Database\Schema\Relation' => 'Omnibridge\Schema\Relation',
        'Base\Database\Schema\Table' => 'Omnibridge\Schema\Table',
        'Base\Event\Events' => 'Omnibridge\Event\Events',
        'Base\Event\NamedEvent' => 'Omnibridge\Event\NamedEvent',
        'Base\Exception\ContainerException' => 'Omnibridge\Exception\ContainerException',
        'Base\Exception\MissingContractException' => 'Omnibridge\Exception\MissingContractException',
        'Base\Exception\PersistenceException' => 'Omnibridge\Exception\PersistenceException',
        'Base\Exception\ServiceNotFoundException' => 'Omnibridge\Exception\ServiceNotFoundException',
        'Base\Form\Coverage' => 'Omnibridge\Form\Coverage',
        'Base\Form\Endpoint' => 'Omnibridge\Form\Endpoint',
        'Base\Form\FieldAssets' => 'Omnibridge\Form\FieldAssets',
        'Base\Form\FieldBehaviour' => 'Omnibridge\Form\FieldBehaviour',
        'Base\Form\UnsupportedKind' => 'Omnibridge\Form\UnsupportedKind',
        'Base\Options\InvalidOption' => 'Omnibridge\Options\InvalidOption',
        'Base\Options\Placeholders' => 'Omnibridge\Options\Placeholders',
        'Base\Options\Processor' => 'Omnibridge\Options\Processor',
        'Base\Validation\AttributeConstraints' => 'Omnibridge\Validation\AttributeConstraints',
        'Base\Validation\Constraint\Choice' => 'Omnibridge\Validation\Constraint\Choice',
        'Base\Validation\Constraint\Constraint' => 'Omnibridge\Validation\Constraint\Constraint',
        'Base\Validation\Constraint\Count' => 'Omnibridge\Validation\Constraint\Count',
        'Base\Validation\Constraint\Email' => 'Omnibridge\Validation\Constraint\Email',
        'Base\Validation\Constraint\File' => 'Omnibridge\Validation\Constraint\File',
        'Base\Validation\Constraint\Length' => 'Omnibridge\Validation\Constraint\Length',
        'Base\Validation\Constraint\NotBlank' => 'Omnibridge\Validation\Constraint\NotBlank',
        'Base\Validation\Constraint\Range' => 'Omnibridge\Validation\Constraint\Range',
        'Base\Validation\Constraint\Regex' => 'Omnibridge\Validation\Constraint\Regex',
        'Base\Validation\Constraint\Unique' => 'Omnibridge\Validation\Constraint\Unique',
        'Base\Validation\Validator' => 'Omnibridge\Validation\Validator',
    ];

    private static bool $declared = false;

    /** Declares every name whose class is installed (an older omnisense/omnibridge lacks the newest): once a process. */
    public static function declare(): void
    {
        if (self::$declared) {
            return;
        }
        self::$declared = true;
        foreach (self::NAMES as $name => $class) {
            if ((class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class)) && !class_exists($name, false) && !interface_exists($name, false) && !trait_exists($name, false)) {
                class_alias($class, $name);
            }
        }
    }

    /** The Base\ name of a class of omnisense/omnibridge, the class itself for any other. */
    public static function name(string $class): string
    {
        static $names = null;
        $names ??= array_flip(self::NAMES);

        return $names[ltrim($class, '\\')] ?? $class;
    }
}
