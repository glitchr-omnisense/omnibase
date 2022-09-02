<?php

namespace Base\Database\Mapping\Factory;

use Base\Database\Mapping\NamingStrategy;
use Base\Database\TranslatableInterface;
use Base\Database\TranslationInterface;
use Base\Exception\MissingDiscriminatorMapException;
use Base\Exception\MissingDiscriminatorValueException;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

use Doctrine\Common\EventManager;
use Doctrine\Common\Proxy\Proxy;
use Doctrine\DBAL\Platforms;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\Deprecations\Deprecation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnClassMetadataNotFoundEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Id\BigIntegerIdentityGenerator;
use Doctrine\ORM\Id\IdentityGenerator;
use Doctrine\ORM\Id\SequenceGenerator;
use Doctrine\ORM\Id\UuidGenerator;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Mapping\Exception\CannotGenerateIds;
use Doctrine\ORM\Mapping\Exception\InvalidCustomGenerator;
use Doctrine\ORM\Mapping\Exception\UnknownGeneratorType;
use Doctrine\Persistence\Mapping\AbstractClassMetadataFactory;
use Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\ReflectionService;
use ReflectionClass;
use ReflectionException;

/**
 *
 * Custom class metadata factory
 * @author Marco Meyer <marco.meyerconde@gmail.com>
 *
 * The ClassMetadataFactory is used to create ClassMetadata objects that contain all the
 * metadata mapping information of a class which describes how a class should be mapped
 * to a relational database.
 *
 * @method ClassMetadata[] getAllMetadata()
 * @method ClassMetadata[] getLoadedMetadata()
 * @method ClassMetadata getMetadataFor($className)
 */

class ClassMetadataFactory extends AbstractClassMetadataFactory
{
    /** @var EntityManagerInterface|null */
    private $em;

    /** @var AbstractPlatform */
    private $targetPlatform;

    /** @var MappingDriver */
    private $driver;

    /** @var EventManager */
    private $evm;

    /** @var mixed[] */
    private $embeddablesActiveNesting = [];

    /**
     * @return void
     */
    public function setEntityManager(EntityManagerInterface $em) { $this->em = $em; }

    /**
     * {@inheritDoc}
     */
    protected function initialize() : void
    {
        $this->driver      = $this->em->getConfiguration()->getMetadataDriverImpl();
        $this->evm         = $this->em->getEventManager();
        $this->initialized = true;
    }

    /**
     * {@inheritDoc}
     */
    protected function onNotFoundMetadata($className) : ?ClassMetadata
    {
        if (! $this->evm->hasListeners(Events::onClassMetadataNotFound))
            return null;

        $eventArgs = new OnClassMetadataNotFoundEventArgs($className, $this->em);

        $this->evm->dispatchEvent(Events::onClassMetadataNotFound, $eventArgs);

        return $eventArgs->getFoundMetadata();
    }

    public function getAllClassNames()
    {
        if (! $this->initialized) {
            $this->initialize();
        }

        $driver = $this->getDriver();
        return $driver->getAllClassNames();
    }

    /**
     * {@inheritDoc}
     */
    protected function doLoadMetadata($class, $parent, $rootEntityFound, array $nonSuperclassParents) : void
    {
        /** @var ClassMetadata $class */
        /** @var ClassMetadata $parent */

        if ($parent) {
            $class->setInheritanceType($parent->inheritanceType);
            $class->setDiscriminatorColumn($parent->discriminatorColumn);
            $class->setIdGeneratorType($parent->generatorType);
            $this->addInheritedFields($class, $parent);
            $this->addInheritedRelations($class, $parent);
            $this->addInheritedEmbeddedClasses($class, $parent);
            $class->setIdentifier($parent->identifier);
            $class->setVersioned($parent->isVersioned);
            $class->setVersionField($parent->versionField);
            $class->setDiscriminatorMap($parent->discriminatorMap);
            $class->setLifecycleCallbacks($parent->lifecycleCallbacks);
            $class->setChangeTrackingPolicy($parent->changeTrackingPolicy);

            if (! empty($parent->customGeneratorDefinition)) {
                $class->setCustomGeneratorDefinition($parent->customGeneratorDefinition);
            }

            if ($parent->isMappedSuperclass) {
                $class->setCustomRepositoryClass($parent->customRepositoryClassName);
            }
        }

        // Invoke driver
        try {
            $this->driver->loadMetadataForClass($class->getName(), $class);
        } catch (ReflectionException $e) {
            throw MappingException::reflectionFailure($class->getName(), $e);
        }

        // If this class has a parent the id generator strategy is inherited.
        // However this is only true if the hierarchy of parents contains the root entity,
        // if it consists of mapped superclasses these don't necessarily include the id field.
        if ($parent && $rootEntityFound) {
            $this->inheritIdGeneratorMapping($class, $parent);
        } else {
            $this->completeIdGeneratorMapping($class);
        }

        if (! $class->isMappedSuperclass) {
            foreach ($class->embeddedClasses as $property => $embeddableClass) {
                if (isset($embeddableClass['inherited'])) {
                    continue;
                }

                if (! (isset($embeddableClass['class']) && $embeddableClass['class'])) {
                    throw MappingException::missingEmbeddedClass($property);
                }

                if (isset($this->embeddablesActiveNesting[$embeddableClass['class']])) {
                    throw MappingException::infiniteEmbeddableNesting($class->name, $property);
                }

                $this->embeddablesActiveNesting[$class->name] = true;

                $embeddableMetadata = $this->getMetadataFor($embeddableClass['class']);

                if ($embeddableMetadata->isEmbeddedClass) {
                    $this->addNestedEmbeddedClasses($embeddableMetadata, $class, $property);
                }

                $identifier = $embeddableMetadata->getIdentifier();

                if (! empty($identifier)) {
                    $this->inheritIdGeneratorMapping($class, $embeddableMetadata);
                }

                $class->inlineEmbeddable($property, $embeddableMetadata);

                unset($this->embeddablesActiveNesting[$class->name]);
            }
        }

        if ($parent) {

            if ($parent->isInheritanceTypeSingleTable()) {
                $class->setPrimaryTable($parent->table);
            }

            $this->addInheritedIndexes($class, $parent);

            if ($parent->cache) {
                $class->cache = $parent->cache;
            }

            if ($parent->containsForeignIdentifier) {
                $class->containsForeignIdentifier = true;
            }

            if (! empty($parent->namedQueries)) {
                $this->addInheritedNamedQueries($class, $parent);
            }

            if (! empty($parent->namedNativeQueries)) {
                $this->addInheritedNamedNativeQueries($class, $parent);
            }

            if (! empty($parent->sqlResultSetMappings)) {
                $this->addInheritedSqlResultSetMappings($class, $parent);
            }

            if (! empty($parent->entityListeners) && empty($class->entityListeners)) {
                $class->entityListeners = $parent->entityListeners;
            }
        }

        $class->setParentClasses($nonSuperclassParents);

        if ($class->isRootEntity() && ! $class->isInheritanceTypeNone() && ! $class->discriminatorMap) {
            $this->addDiscriminatorMapClass($class);
        }

        if ($this->evm->hasListeners(Events::loadClassMetadata)) {
            $eventArgs = new LoadClassMetadataEventArgs($class, $this->em);
            $this->evm->dispatchEvent(Events::loadClassMetadata, $eventArgs);
        }

        if ($class->changeTrackingPolicy === ClassMetadataInfo::CHANGETRACKING_NOTIFY) {
            Deprecation::trigger(
                'doctrine/orm',
                'https://github.com/doctrine/orm/issues/8383',
                'NOTIFY Change Tracking policy used in "%s" is deprecated, use deferred explicit instead.',
                $class->name
            );
        }

        $this->validateRuntimeMetadata($class, $parent);
    }

    /**
     * Validate runtime metadata is correctly defined.
     *
     * @param ClassMetadata               $class
     * @param ClassMetadataInterface|null $parent
     *
     * @return void
     *
     * @throws MappingException
     */
    protected function validateRuntimeMetadata($class, $parent)
    {
        if (! $class->reflClass) {
            // only validate if there is a reflection class instance
            return;
        }

        $class->validateIdentifier();
        $class->validateAssociations();
        $class->validateLifecycleCallbacks($this->getReflectionService());

        // verify inheritance
        if (! $class->isMappedSuperclass && ! $class->isInheritanceTypeNone()) {
            if (! $parent) {
                if (count($class->discriminatorMap) === 0) {
                    throw MappingException::missingDiscriminatorMap($class->name);
                }

                if (! $class->discriminatorColumn) {
                    throw MappingException::missingDiscriminatorColumn($class->name);
                }

                foreach ($class->subClasses as $subClass) {
                    if ((new ReflectionClass($subClass))->name !== $subClass) {
                        throw MappingException::invalidClassInDiscriminatorMap($subClass, $class->name);
                    }
                }
            } else {
                assert($parent instanceof ClassMetadataInfo); // https://github.com/doctrine/orm/issues/8746
                if (
                    ! $class->reflClass->isAbstract()
                    && ! in_array($class->name, $class->discriminatorMap, true)
                ) {
                    throw MappingException::mappedClassNotPartOfDiscriminatorMap($class->name, $class->rootEntityName);
                }
            }
        } elseif ($class->isMappedSuperclass && $class->name === $class->rootEntityName && (count($class->discriminatorMap) || $class->discriminatorColumn)) {
            // second condition is necessary for mapped superclasses in the middle of an inheritance hierarchy
            throw MappingException::noInheritanceOnMappedSuperClass($class->name);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function newClassMetadataInstance($className) : ClassMetadata
    {
        return new ClassMetadata($className, $this->em->getConfiguration()->getNamingStrategy());
    }

    /**
     * Adds a default discriminator map if no one is given
     *
     * If an entity is of any inheritance type and does not contain a
     * discriminator map, then the map is generated automatically. This process
     * is expensive computation wise.
     *
     * The automatically generated discriminator map contains the lowercase short name of
     * each class as key.
     *
     * @throws MappingException
     */
    private function addDiscriminatorMapClass(ClassMetadata $classMetadata): void
    {
        $allClasses = $this->driver->getAllClassNames();
        $fqcn       = $classMetadata->getName();
        $map        = [$this->getShortName($classMetadata->name) => $fqcn];

        $duplicates = [];
        foreach ($allClasses as $subClass) {
            if (is_subclass_of($subClass, $fqcn)) {

                $shortName = $this->getShortName($subClass);
                if (isset($map[$shortName]))
                    $duplicates[] = $shortName;

                $map[$shortName] = $subClass;
            }
        }

        if ($duplicates) {
            throw MappingException::duplicateDiscriminatorEntry($classMetadata->name, $duplicates, $map);
        }

        $classMetadata->setDiscriminatorMap($map);
    }

    /**
     * Gets the lower-case short name of a class.
     *
     * @psalm-param class-string $className
     */
    private function getShortName(string $className): string
    {
        // if (strpos($className, '\\') === false) {
        //     return mb_strtolower($className);
        // }

        // $parts = explode('\\', $className);

        // return mb_strtolower(end($parts));

        return $className;
    }

    /**
     * Adds inherited fields to the subclass mapping.
     */
    private function addInheritedFields(ClassMetadata $subClass, ClassMetadata $parentClass): void
    {
        foreach ($parentClass->fieldMappings as $mapping) {
            if (! isset($mapping['inherited']) && ! $parentClass->isMappedSuperclass) {
                $mapping['inherited'] = $parentClass->name;
            }

            if (! isset($mapping['declared'])) {
                $mapping['declared'] = $parentClass->name;
            }

            $subClass->addInheritedFieldMapping($mapping);
        }

        foreach ($parentClass->reflFields as $name => $field) {
            $subClass->reflFields[$name] = $field;
        }
    }

    /**
     * Adds inherited association mappings to the subclass mapping.
     *
     * @throws MappingException
     */
    private function addInheritedRelations(ClassMetadata $subClass, ClassMetadata $parentClass): void
    {
        foreach ($parentClass->associationMappings as $field => $mapping) {
            if ($parentClass->isMappedSuperclass) {
                if ($mapping['type'] & ClassMetadata::TO_MANY && ! $mapping['isOwningSide']) {
                    throw MappingException::illegalToManyAssociationOnMappedSuperclass($parentClass->name, $field);
                }

                $mapping['sourceEntity'] = $subClass->name;
            }

            //$subclassMapping = $mapping;
            if (! isset($mapping['inherited']) && ! $parentClass->isMappedSuperclass) {
                $mapping['inherited'] = $parentClass->name;
            }

            if (! isset($mapping['declared'])) {
                $mapping['declared'] = $parentClass->name;
            }

            $subClass->addInheritedAssociationMapping($mapping);
        }
    }

    private function addInheritedEmbeddedClasses(ClassMetadata $subClass, ClassMetadata $parentClass): void
    {
        foreach ($parentClass->embeddedClasses as $field => $embeddedClass) {
            if (! isset($embeddedClass['inherited']) && ! $parentClass->isMappedSuperclass) {
                $embeddedClass['inherited'] = $parentClass->name;
            }

            if (! isset($embeddedClass['declared'])) {
                $embeddedClass['declared'] = $parentClass->name;
            }

            $subClass->embeddedClasses[$field] = $embeddedClass;
        }
    }

    /**
     * Adds nested embedded classes metadata to a parent class.
     *
     * @param ClassMetadata $subClass    Sub embedded class metadata to add nested embedded classes metadata from.
     * @param ClassMetadata $parentClass Parent class to add nested embedded classes metadata to.
     * @param string        $prefix      Embedded classes' prefix to use for nested embedded classes field names.
     */
    private function addNestedEmbeddedClasses(
        ClassMetadata $subClass,
        ClassMetadata $parentClass,
        string $prefix
    ): void {
        foreach ($subClass->embeddedClasses as $property => $embeddableClass) {
            if (isset($embeddableClass['inherited'])) {
                continue;
            }

            $embeddableMetadata = $this->getMetadataFor($embeddableClass['class']);

            $parentClass->mapEmbedded(
                [
                    'fieldName' => $prefix . '.' . $property,
                    'class' => $embeddableMetadata->name,
                    'columnPrefix' => $embeddableClass['columnPrefix'],
                    'declaredField' => $embeddableClass['declaredField']
                            ? $prefix . '.' . $embeddableClass['declaredField']
                            : $prefix,
                    'originalField' => $embeddableClass['originalField'] ?: $property,
                ]
            );
        }
    }

    /**
     * Copy the table indices from the parent class superclass to the child class
     */
    private function addInheritedIndexes(ClassMetadata $subClass, ClassMetadata $parentClass): void
    {
        if (! $parentClass->isMappedSuperclass) {
            return;
        }

        foreach (['uniqueConstraints', 'indexes'] as $indexType) {
            if (isset($parentClass->table[$indexType])) {
                foreach ($parentClass->table[$indexType] as $indexName => $index) {
                    if (isset($subClass->table[$indexType][$indexName])) {
                        continue; // Let the inheriting table override indices
                    }

                    $subClass->table[$indexType][$indexName] = $index;
                }
            }
        }
    }

    /**
     * Adds inherited named queries to the subclass mapping.
     */
    private function addInheritedNamedQueries(ClassMetadata $subClass, ClassMetadata $parentClass): void
    {
        foreach ($parentClass->namedQueries as $name => $query) {
            if (! isset($subClass->namedQueries[$name])) {
                $subClass->addNamedQuery(
                    [
                        'name'  => $query['name'],
                        'query' => $query['query'],
                    ]
                );
            }
        }
    }

    /**
     * Adds inherited named native queries to the subclass mapping.
     */
    private function addInheritedNamedNativeQueries(ClassMetadata $subClass, ClassMetadata $parentClass): void
    {
        foreach ($parentClass->namedNativeQueries as $name => $query) {
            if (! isset($subClass->namedNativeQueries[$name])) {
                $subClass->addNamedNativeQuery(
                    [
                        'name'              => $query['name'],
                        'query'             => $query['query'],
                        'isSelfClass'       => $query['isSelfClass'],
                        'resultSetMapping'  => $query['resultSetMapping'],
                        'resultClass'       => $query['isSelfClass'] ? $subClass->name : $query['resultClass'],
                    ]
                );
            }
        }
    }

    /**
     * Adds inherited sql result set mappings to the subclass mapping.
     */
    private function addInheritedSqlResultSetMappings(ClassMetadata $subClass, ClassMetadata $parentClass) :void
    {
        foreach ($parentClass->sqlResultSetMappings as $name => $mapping) {
            if (! isset($subClass->sqlResultSetMappings[$name])) {
                $entities = [];
                foreach ($mapping['entities'] as $entity) {
                    $entities[] = [
                        'fields'                => $entity['fields'],
                        'isSelfClass'           => $entity['isSelfClass'],
                        'discriminatorColumn'   => $entity['discriminatorColumn'],
                        'entityClass'           => $entity['isSelfClass'] ? $subClass->name : $entity['entityClass'],
                    ];
                }

                $subClass->addSqlResultSetMapping(
                    [
                        'name'          => $mapping['name'],
                        'columns'       => $mapping['columns'],
                        'entities'      => $entities,
                    ]
                );
            }
        }
    }

    /**
     * Completes the ID generator mapping. If "auto" is specified we choose the generator
     * most appropriate for the targeted database platform.
     *
     * @throws ORMException
     */
    private function completeIdGeneratorMapping(ClassMetadataInfo $classMetadata): void
    {
        $idGenType = $classMetadata->generatorType;
        if ($idGenType === ClassMetadata::GENERATOR_TYPE_AUTO) {
            $classMetadata->setIdGeneratorType($this->determineIdGeneratorStrategy($this->getTargetPlatform()));
        }

        // Create & assign an appropriate ID generator instance
        switch ($classMetadata->generatorType) {
            case ClassMetadata::GENERATOR_TYPE_IDENTITY:
                $sequenceName = null;
                $fieldName    = $classMetadata->identifier ? $classMetadata->getSingleIdentifierFieldName() : null;

                // Platforms that do not have native IDENTITY support need a sequence to emulate this behaviour.
                if ($this->getTargetPlatform()->usesSequenceEmulatedIdentityColumns()) {
                    $columnName     = $classMetadata->getSingleIdentifierColumnName();
                    $quoted         = isset($classMetadata->fieldMappings[$fieldName]['quoted']) || isset($classMetadata->table['quoted']);
                    $sequencePrefix = $classMetadata->getSequencePrefix($this->getTargetPlatform());
                    $sequenceName   = $this->getTargetPlatform()->getIdentitySequenceName($sequencePrefix, $columnName);
                    $definition     = [
                        'sequenceName' => $this->truncateSequenceName($sequenceName),
                    ];

                    if ($quoted) {
                        $definition['quoted'] = true;
                    }

                    $sequenceName = $this
                        ->em
                        ->getConfiguration()
                        ->getQuoteStrategy()
                        ->getSequenceName($definition, $classMetadata, $this->getTargetPlatform());
                }

                $generator = $fieldName && $classMetadata->fieldMappings[$fieldName]['type'] === 'bigint'
                    ? new BigIntegerIdentityGenerator($sequenceName)
                    : new IdentityGenerator($sequenceName);

                $classMetadata->setIdGenerator($generator);

                break;

            case ClassMetadata::GENERATOR_TYPE_SEQUENCE:
                // If there is no sequence definition yet, create a default definition
                $definition = $classMetadata->sequenceGeneratorDefinition;

                if (! $definition) {
                    $fieldName    = $classMetadata->getSingleIdentifierFieldName();
                    $sequenceName = $classMetadata->getSequenceName($this->getTargetPlatform());
                    $quoted       = isset($classMetadata->fieldMappings[$fieldName]['quoted']) || isset($classMetadata->table['quoted']);

                    $definition = [
                        'sequenceName'      => $this->truncateSequenceName($sequenceName),
                        'allocationSize'    => 1,
                        'initialValue'      => 1,
                    ];

                    if ($quoted) {
                        $definition['quoted'] = true;
                    }

                    $classMetadata->setSequenceGeneratorDefinition($definition);
                }

                $sequenceGenerator = new SequenceGenerator(
                    $this->em->getConfiguration()->getQuoteStrategy()->getSequenceName($definition, $classMetadata, $this->getTargetPlatform()),
                    (int) $definition['allocationSize']
                );
                $classMetadata->setIdGenerator($sequenceGenerator);
                break;

            case ClassMetadata::GENERATOR_TYPE_NONE:
                $classMetadata->setIdGenerator(new AssignedGenerator());
                break;

            case ClassMetadata::GENERATOR_TYPE_UUID:
                Deprecation::trigger(
                    'doctrine/orm',
                    'https://github.com/doctrine/orm/issues/7312',
                    'Mapping for %s: the "UUID" id generator strategy is deprecated with no replacement',
                    $classMetadata->name
                );
                $classMetadata->setIdGenerator(new UuidGenerator());
                break;

            case ClassMetadata::GENERATOR_TYPE_CUSTOM:
                $definition = $classMetadata->customGeneratorDefinition;
                if ($definition === null) {
                    throw InvalidCustomGenerator::onClassNotConfigured();
                }

                if (! class_exists($definition['class'])) {
                    throw InvalidCustomGenerator::onMissingClass($definition);
                }

                $classMetadata->setIdGenerator(new $definition['class']());
                break;

            default:
                throw UnknownGeneratorType::create($classMetadata->generatorType);
        }
    }

    private function determineIdGeneratorStrategy(AbstractPlatform $platform): int
    {
        if (
            $platform instanceof Platforms\OraclePlatform
            || $platform instanceof Platforms\PostgreSQL94Platform
            || $platform instanceof Platforms\PostgreSQLPlatform
        ) {
            return ClassMetadata::GENERATOR_TYPE_SEQUENCE;
        }

        if ($platform->supportsIdentityColumns()) {
            return ClassMetadata::GENERATOR_TYPE_IDENTITY;
        }

        if ($platform->supportsSequences()) {
            return ClassMetadata::GENERATOR_TYPE_SEQUENCE;
        }

        throw CannotGenerateIds::withPlatform($platform);
    }

    private function truncateSequenceName(string $schemaElementName): string
    {
        $platform = $this->getTargetPlatform();
        if (! in_array($platform->getName(), ['oracle', 'sqlanywhere'], true)) {
            return $schemaElementName;
        }

        $maxIdentifierLength = $platform->getMaxIdentifierLength();

        if (strlen($schemaElementName) > $maxIdentifierLength) {
            return substr($schemaElementName, 0, $maxIdentifierLength);
        }

        return $schemaElementName;
    }

    /**
     * Inherits the ID generator mapping from a parent class.
     */
    private function inheritIdGeneratorMapping(ClassMetadataInfo $classMetadata, ClassMetadataInfo $parent): void
    {
        if ($parent->isIdGeneratorSequence()) {
            $classMetadata->setSequenceGeneratorDefinition($parent->sequenceGeneratorDefinition);
        }

        if ($parent->generatorType) {
            $classMetadata->setIdGeneratorType($parent->generatorType);
        }

        if ($parent->idGenerator) {
            $classMetadata->setIdGenerator($parent->idGenerator);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function wakeupReflection(ClassMetadataInterface $classMetadata, ReflectionService $reflService) : void
    {
        assert($classMetadata instanceof ClassMetadata);
        $classMetadata->wakeupReflection($reflService);
    }

    /**
     * {@inheritDoc}
     */
    protected function initializeReflection(ClassMetadataInterface $classMetadata, ReflectionService $reflService) : void
    {
        assert($classMetadata instanceof ClassMetadata);
        $classMetadata->initializeReflection($reflService);
    }

    /**
     * {@inheritDoc}
     */
    protected function getFqcnFromAlias($namespaceAlias, $simpleClassName) : string
    {
        /** @psalm-var class-string */
        return $this->em->getConfiguration()->getEntityNamespace($namespaceAlias) . '\\' . $simpleClassName;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDriver() : MappingDriver
    {
        return $this->driver;
    }

    /**
     * {@inheritDoc}
     */
    public function isEntity($class) : bool
    {
        if ($class instanceof ClassMetadataInterface)
            return isset($class->isMappedSuperclass) && $class->isMappedSuperclass === false;
        else if (is_object($class))
            $class = ($class instanceof Proxy) ? get_parent_class($class) : get_class($class);

        return ! $this->em->getMetadataFactory()->isTransient($class);
    }

    /**
     * @return Platforms\AbstractPlatform
     */
    private function getTargetPlatform()
    {
        if (! $this->targetPlatform) {
            $this->targetPlatform = $this->em->getConnection()->getDatabasePlatform();
        }

        return $this->targetPlatform;
    }


    /**
     * {@inheritDoc}
     */
    protected function loadMetadata($name) : array
    {
        $loadedMetadata = parent::loadMetadata($name);

        foreach($loadedMetadata as $key => $classMetadata)
            $classMetadataList[] = $this->getMetadataFor($classMetadata);
        foreach($classMetadataList as $key => $classMetadata)
            $classMetadata = $this->resolveDiscriminatorValue($classMetadata);

        return $loadedMetadata;
    }

    /**
     * Populates the discriminator value of the given metadata (if not set) by iterating over discriminator
     * map classes and looking for a fitting one.
     *
     * @return void
     *
     * @throws MappingException
     */

    private function resolveDiscriminatorValue(ClassMetadata $classMetadata)
    {
        //If translatable object: preprocess inheritanceType, discriminatorMap, discriminatorColumn, discriminatorValue
        if (is_subclass_of($classMetadata->getName(), TranslationInterface::class, true)) {

            if(!str_ends_with($classMetadata->getName(), NamingStrategy::TABLE_I18N_SUFFIX))
                throw new \Exception("Invalid class name for \"".$classMetadata->getName()."\"");

            $translatableClass = $classMetadata->getName()::getTranslatableEntityClass();
            $translatableMetadata = $this->getMetadataFor($translatableClass);

            if(!$classMetadata->discriminatorMap) {
                $classMetadata->discriminatorMap = array_filter(array_map(function($className) {

                    return (is_subclass_of($className, TranslatableInterface::class, true))
                        ? $className::getTranslationEntityClass(false, false)
                        : null;

                }, $translatableMetadata->discriminatorMap), fn($c) => $c !== null);
            }

            $classMetadata->inheritanceType     = $translatableMetadata->inheritanceType;
            $classMetadata->discriminatorColumn = $translatableMetadata->discriminatorColumn;
            if($classMetadata->discriminatorMap) {

                if(!in_array($classMetadata->getName(), $classMetadata->discriminatorMap))
                    throw new MissingDiscriminatorMapException(
                        "Discriminator map missing for \"".$classMetadata->getName().
                        "\". Did you forgot to implement \"".TranslatableInterface::class.
                        "\" in \"".$classMetadata->getName()::getTranslatableEntityClass()."\".");

                $classMetadata->discriminatorValue  = array_flip($translatableMetadata->discriminatorMap)[$translatableMetadata->getName()] ?? null;
                if(!$classMetadata->discriminatorValue)
                    throw new MissingDiscriminatorValueException("Discriminator value missing for \"".$classMetadata->getName()."\".");
            }
        }

        if ($classMetadata->discriminatorValue || ! $classMetadata->discriminatorMap ||
            $classMetadata->isMappedSuperclass || ! $classMetadata->reflClass || $classMetadata->reflClass->isAbstract()) {
            return;
        }

        // minor optimization: avoid loading related metadata when not needed
        foreach ($classMetadata->discriminatorMap as $discriminatorValue => $discriminatorClass) {
            if ($discriminatorClass === $classMetadata->name) {
                $classMetadata->discriminatorValue = $discriminatorValue;
                return;
            }
        }

        // iterate over discriminator mappings and resolve actual referenced classes according to existing metadata
        foreach ($classMetadata->discriminatorMap as $discriminatorValue => $discriminatorClass) {
            if ($classMetadata->name === $this->getMetadataFor($discriminatorClass)->getName()) {
                $classMetadata->discriminatorValue = $discriminatorValue;
                return;
            }
        }

        throw MappingException::mappedClassNotPartOfDiscriminatorMap($classMetadata->name, $classMetadata->rootEntityName);
    }
}
