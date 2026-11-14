<?php

namespace Base\Attributes;

use App\Entity\User;
use Base\Database\Entity\EntityHydratorInterface;
use Omnibridge\Doctrine\Omnibase\Database\Event\DoctrineQueryEventArgs;
use Omnibridge\Doctrine\Omnibase\Database\Event\ResolveDiscriminatorEventArgs;
use Base\Database\Mapping\Completor;
use Base\Database\Mapping\MetadataManipulator;
use Base\Service\Storages;
use Base\Service\ParameterBagInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Event\LifecycleEventArgs as BaseLifecycleEventArgs;

use Doctrine\ORM\Event\OnFlushEventArgs;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ObjectRepository;
use Omnibridge\Persistence\Lifecycle;
use Base\Database\Attribute\Backend\Backends;
use Base\Database\Mapping\EntityShape;
use Base\Database\Mapping\EntityShapes;
use Base\Database\Entity\EntityValues;
use Base\Database\UnitOfWorks;
use Base\Exception\UnsupportedBehaviourException;
use Omnibridge\Persistence\Snapshots;
use Omnibridge\Persistence\UnitOfWork as NeutralUnitOfWork;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Exception;
use Omnibridge\Persistence\Persistences;

abstract class AbstractAttribute implements AttributeInterface
{
    /**
     * @return AttributeReader|null
     */
    public static function getAttributeReader(): ?AttributeReader
    {
        return AttributeReader::getInstance();
    }

    /**
     * @return string
     */
    public static function getEnvironment(): string
    {
        return AttributeReader::getInstance()->getEnvironment();
    }

    /**
     * @return string
     */
    public static function getProjectDir(): string
    {
        return AttributeReader::getInstance()->getProjectDir();
    }

    /**
     * @return ParameterBagInterface
     */
    public static function getParameterBag(): ParameterBagInterface
    {
        return AttributeReader::getInstance()->getParameterBag();
    }

    /**
     * @return EntityManager|null
     */
    public static function getEntityManager(): ?EntityManager
    {
        return AttributeReader::getInstance()->getEntityManager();
    }

    /**
     * @return EntityHydratorInterface
     */
    public static function getEntityHydrator(): EntityHydratorInterface
    {
        return AttributeReader::getInstance()->getEntityHydrator();
    }

    /**
     * @param $className
     * @param string $property
     * @return false|string|null
     * @throws Exception
     */
    public static function getTypeOfField($className, string $property)
    {
        return AttributeReader::getInstance()->getClassMetadataManipulator()->getTypeOfField($className, $property);
    }

    /**
     * @param $objectOrClass
     * @return ClassMetadata|null
     */
    public static function getClassMetadata($objectOrClass): ?ClassMetadata
    {
        return self::getEntityManager()->getClassMetadata(is_object($objectOrClass) ? get_class($objectOrClass) : $objectOrClass);
    }

    /**
     * The reader's manipulator: omnisense/omnibase's MetadataManipulator on the persistence's shapes (plan "omnibase
     * agnostic", step E3), omnibridge/doctrine's ClassMetadataManipulator where a reader was made with that one.
     */
    public static function getClassMetadataManipulator(): null|MetadataManipulator|\Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator
    {
        return AttributeReader::getInstance()->getClassMetadataManipulator();
    }

    public static function getClassMetadataCompletor(mixed $entityOrClassOrMetadata): null|Completor|\Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataCompletor
    {
        return AttributeReader::getInstance()->getClassMetadataManipulator()->getClassMetadataCompletor($entityOrClassOrMetadata);
    }

    public static function getFlysystem(): Storages
    {
        return AttributeReader::getInstance()->getFlysystem();
    }

    public static function getImpersonator(): ?User
    {
        return AttributeReader::getInstance()->getImpersonator();
    }

    public static function getUser(): ?User
    {
        $user = AttributeReader::getInstance()->getUser();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param $className
     * @return EntityRepository|ObjectRepository
     */
    public static function getRepository($className): EntityRepository|ObjectRepository
    {
        return AttributeReader::getInstance()->getRepository($className);
    }

    /**
     * @param $url
     * @return string
     */
    public static function getAsset($url): string
    {
        return AttributeReader::getInstance()->getAsset($url);
    }

    /**
     * Resolve the attribute instances mapped at $mappingPath for an
     * entity/class (optionally filtered to $attributeClass). Named
     * resolveAttributes rather than getAttributes: several #[Attribute]
     * subclasses (e.g. IsGranted) have their own unrelated instance-level
     * getAttributes()/setAttributes() pair, which a same-named static method
     * here would collide with (incompatible static/instance override).
     *
     * @param $entityOrClassNameOrMetadataOrRefl
     * @param string $mappingPath
     * @param string|null $attributeClass
     * @return array
     * @throws Exception
     */
    public static function resolveAttributes($entityOrClassNameOrMetadataOrRefl, string $mappingPath, ?string $attributeClass = null): array
    {
        if (!$entityOrClassNameOrMetadataOrRefl) {
            return [];
        }
        if (AttributeReader::getInstance()->isEntity($entityOrClassNameOrMetadataOrRefl)) {
            $entityOrClassNameOrMetadataOrRefl = is_object($entityOrClassNameOrMetadataOrRefl) ? get_class($entityOrClassNameOrMetadataOrRefl) : $entityOrClassNameOrMetadataOrRefl;
        }

        $mapping = $mappingPath;
        if (($dot = strpos($mapping, ".")) > 0) {
            $fieldPath = trim(substr($mapping, 0, $dot));
            $mapping = trim(substr($mapping, $dot + 1));

            $entityOrClassNameOrMetadataOrRefl = self::getClassMetadataManipulator()->getTargetClass($entityOrClassNameOrMetadataOrRefl, $fieldPath);
            if (!$entityOrClassNameOrMetadataOrRefl) {
                return [];
            }
        }

        $attributes = AttributeReader::getInstance()->getPropertyAttributes($entityOrClassNameOrMetadataOrRefl);
        foreach ($attributes as $column => $attribute) {
            if ($attributeClass !== null) {
                $attributes[$column] = array_filter($attribute, fn($a) => is_instanceof($a, $attributeClass));
            }
        }

        return $attributes[$mapping] ?? [];
    }

    /**
     * @param $entityOrClassNameOrMetadataOrRefl
     * @param string $mapping
     * @param string $attributeClass
     * @return mixed
     */
    public static function getAttribute($entityOrClassNameOrMetadataOrRefl, string $mapping, string $attributeClass): mixed
    {
        $attributes = self::resolveAttributes($entityOrClassNameOrMetadataOrRefl, $mapping, $attributeClass);
        return !empty($attributes) ? end($attributes) : null;
    }

    /**
     * @param $entityOrClassNameOrMetadataOrRefl
     * @param string $mapping
     * @param string $attributeClass
     * @return bool
     */
    public static function hasAttribute($entityOrClassNameOrMetadataOrRefl, string $mapping, string $attributeClass): bool
    {
        $attributes = self::resolveAttributes($entityOrClassNameOrMetadataOrRefl, $mapping, $attributeClass);
        return !empty($attributes);
    }

    /**
     * Minimize the use unit of work to very specific context.. (doctrine internal use only)
     * Please use getNativeEntity() to get back the
     */
    public static function getUnitOfWork(): UnitOfWork
    {
        return AttributeReader::getInstance()->getEntityManager()->getUnitOfWork();
    }

    /**
     * The entity's changes, computed again first. Given the unit of work of the hook's event (plan "omnibase agnostic",
     * step F7c), omnisense/omnibridge's contract answers - recompute(), then changes() -, whichever persistence keeps
     * the entity; without one, Doctrine's unit of work (the attributes on Doctrine's metadata: until the vocabulary step, E3).
     *
     * @param $entity
     * @return array[]|PersistentCollection|\mixed[][]
     */
    public static function getEntityChangeSet($entity, ?NeutralUnitOfWork $unitOfWork = null): array|PersistentCollection
    {
        if (null !== $unitOfWork) {
            $unitOfWork->recompute($entity);

            return $unitOfWork->changes($entity);
        }

        // (NB: /!\ computeChangeSets != recomputeSingleChangeSets)
        self::getUnitOfWork()->recomputeSingleEntityChangeSet(
            self::getClassMetadata($entity),
            $entity
        );

        return self::getUnitOfWork()->getEntityChangeSet($entity);
    }

    protected static $entitySerializer = null;

    /**
     * @return Serializer|null
     */
    public static function getSerializer(): ?Serializer
    {
        if (!self::$entitySerializer) {
            self::$entitySerializer = new Serializer([new DateTimeNormalizer(), new ObjectNormalizer()], [new JsonEncoder()]);
        }

        return self::$entitySerializer;
    }

    /**
     * @param AbstractAttribute $attribute
     * @return bool
     */
    public static function isSerializable(AbstractAttribute $attribute): bool
    {
        try {
            return is_serializable($attribute);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param $entity
     * @param string $property
     * @return EntityRepository the repository of the class declaring $property (an omnibase
     *                          repository, Omnibridge\Doctrine\Omnibase\Database\Repository\ServiceEntityRepository, or any other)
     * @throws NotSupported
     */
    public function getPropertyOwnerRepository($entity, string $property, ?NeutralUnitOfWork $unitOfWork = null): EntityRepository|\Base\Database\Repository\Entities
    {
        if (null !== $unitOfWork) {
            // The persistence's repositories (plan "omnibase agnostic", step F7c): the same classes asked.
            $repositories = UnitOfWorks::repositories($unitOfWork);
            $className = $unitOfWork->className($entity);
            $repository = $repositories->of($className);
            while ($className = get_parent_class($className)) {
                if (property_exists($className, $property)) {
                    $repository = $repositories->of($className);
                }
            }

            return $repository;
        }

        $className = get_class($entity);
        $repository = $this->getEntityManager()->getRepository($className);

        while ($className = get_parent_class($className)) {
            if (property_exists($className, $property)) {
                $repository = $this->getEntityManager()->getRepository($className);
            }
        }

        return $repository;
    }

    /**
     * @param $classname
     * @param $data
     * @return object|null
     */
    public static function getEntityFromData($classname, $data, ?NeutralUnitOfWork $unitOfWork = null): ?object
    {
        if ($data === null) {
            return null;
        }

        $fieldNames = null !== $unitOfWork
            ? UnitOfWorks::shapes($unitOfWork)->shape($classname)->fieldNames()
            : self::getClassMetadata($classname)->getFieldNames();
        $fields = array_intersect_key($data, array_flip($fieldNames));
        $associations = array_diff_key($data, array_flip($fieldNames));

        // Without the constructor: the snapshot is filled from $data, and an
        // entity may require constructor arguments (Widget\Slot needs a path).
        return object_hydrate((new \ReflectionClass($classname))->newInstanceWithoutConstructor(), array_merge($fields, $associations));
    }

    /**
     * @param $entity
     * @return object|null
     * @throws MappingException
     */
    public static function getOriginalEntity($entity, ?NeutralUnitOfWork $unitOfWork = null): ?object
    {
        return self::getEntityFromData(get_class($entity), self::getOriginalEntityData($entity, $unitOfWork), $unitOfWork);
    }

    /**
     * @param $entity
     * @return array|null
     * @throws MappingException
     */
    public static function getOriginalEntityData($entity, ?NeutralUnitOfWork $unitOfWork = null): ?array
    {
        if (null !== $unitOfWork) {
            // What the unit of work remembers of the entity (omnisense/omnibridge's Snapshots), its identifier beside.
            if (!$unitOfWork instanceof Snapshots) {
                throw UnsupportedBehaviourException::for(Snapshots::class, $unitOfWork);
            }
            $primaryKey = UnitOfWorks::shapes($unitOfWork)->shape($entity)->primaryKey();
            $entityData = $unitOfWork->original($entity);
            if (null !== $primaryKey) {
                $entityData[$primaryKey] = self::getFieldValue($entity, $primaryKey, $unitOfWork);
            }

            return $entityData;
        }

        $primaryKey = self::getClassMetadataManipulator()->getPrimaryKey($entity); // primaryKey information missing

        try {
            $entityData = self::getUnitOfWork()->getOriginalEntityData($entity);
        } catch (\Throwable $e) {
            // An entity whose row is gone, whichever persistence says it (omnisense/omnibridge's Persistences).
            if (!Persistences::isNotFound($e)) {
                throw $e;
            }

            return null;
        }

        $entityData[$primaryKey] = self::getFieldValue($entity, $primaryKey);
        return $entityData;
    }

    /**
     * @param $entity
     * @return object|null
     */
    public static function getOldEntity($entity, ?NeutralUnitOfWork $unitOfWork = null): ?object
    {
        return self::getEntityFromData(get_class($entity), self::getOldEntityData($entity, $unitOfWork), $unitOfWork);
    }

    /**
     * @param $entity
     * @return array|null
     */
    public static function getOldEntityData($entity, ?NeutralUnitOfWork $unitOfWork = null): ?array
    {
        $changeSet = self::getEntityChangeSet($entity, $unitOfWork);

        // Replace original entity values by the changeSet
        //   It happens that "original" entity data doesn't mean,
        //   original value before form submission
        $entityData = self::getOriginalEntityData($entity, $unitOfWork);

        foreach ($entityData as $key => $_) {
            if (array_key_exists($key, $changeSet)) {
                $entityData[$key] = $changeSet[$key][0];
            }
        }

        return $entityData;
    }

    /**
     * @param $entity
     * @param string $property
     * @return bool
     */
    public static function hasField($entity, string $property): bool
    {
        return self::getClassMetadataManipulator()->hasField($entity, $property);
    }

    /**
     * @param $entity
     * @param string $property
     * @return mixed
     * @throws Exception
     */
    public static function getFieldValue($entity, string $property, ?NeutralUnitOfWork $unitOfWork = null)
    {
        if (self::neutral($unitOfWork)) {
            return EntityValues::of($unitOfWork)->get($entity, $property);
        }

        return self::getClassMetadataManipulator()->getFieldValue($entity, $property);
    }

    /**
     * @param $entity
     * @param string $property
     * @param $value
     * @return MetadataManipulator|false
     */
    public static function setFieldValue($entity, string $property, $value, ?NeutralUnitOfWork $unitOfWork = null): false|MetadataManipulator|\Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator|EntityValues
    {
        if (self::neutral($unitOfWork)) {
            $values = EntityValues::of($unitOfWork);
            if (!$values->isEntity($entity)) {
                return false;
            }
            $values->set($entity, $property, $value);

            return $values;
        }

        return self::getClassMetadataManipulator()->setFieldValue($entity, $property, $value);
    }

    /**
     * @param $entity
     * @param string $property
     * @return bool
     */
    public static function hasProperty($entity, string $property): bool
    {
        return property_exists($entity, $property);
    }

    /**
     * @param $entity
     * @param string $property
     * @return mixed|null
     */
    public static function getPropertyValue($entity, string $property, ?NeutralUnitOfWork $unitOfWork = null)
    {
        if (self::neutral($unitOfWork)) {
            return EntityValues::of($unitOfWork)->property($entity, $property);
        }

        return self::getClassMetadataManipulator()->getPropertyValue($entity, $property);
    }

    /**
     * @param $entity
     * @param string $property
     * @param $value
     * @return MetadataManipulator
     */
    public static function setPropertyValue($entity, string $property, $value, ?NeutralUnitOfWork $unitOfWork = null): MetadataManipulator|\Omnibridge\Doctrine\Omnibase\Database\Mapping\ClassMetadataManipulator|EntityValues
    {
        if (self::neutral($unitOfWork)) {
            $values = EntityValues::of($unitOfWork);
            $values->setProperty($entity, $property, $value);

            return $values;
        }

        return self::getClassMetadataManipulator()->setPropertyValue($entity, $property, $value);
    }

    /**
     * Whether the values are read on the unit of work's persistence (plan "omnibase agnostic", step F7c): given one
     * whose persistence registered its entities' shapes (EntityShapes). Otherwise Doctrine's metadata, as before.
     */
    private static function neutral(?NeutralUnitOfWork $unitOfWork): bool
    {
        return null !== $unitOfWork && Backends::has($unitOfWork, EntityShapes::class);
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
    {
    }

    /**
     * What the attribute adds to its class's completor (plan "omnibase agnostic", step E3: #[Alias]'s names,
     * #[Hierarchify]'s hierarchy), on no ORM: Base\Database\Mapping\Completors calls it for each attribute of the class,
     * its methods and its properties - the target and the class, method or property it is on.
     */
    public function complete(Completor $completor, string $target, ?string $targetValue = null): void
    {
    }

    public function resolveDiscriminator(ResolveDiscriminatorEventArgs $args): void
    {
    }

    public function preQuery(DoctrineQueryEventArgs $args): void
    {
    }

    public function onQuery(DoctrineQueryEventArgs $args): void
    {
    }
    
    public function postQuery(DoctrineQueryEventArgs $args): void
    {
    }

    public function preFlush(PreFlushEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null): void
    {
    }

    public function onFlush(OnFlushEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
    }

    public function postFlush(PostFlushEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
    }

    public function prePersist(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
    }

    public function preUpdate(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
    }

    public function preRemove(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
    }

    public function postLoad(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
    }

    public function postPersist(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
    }

    public function postUpdate(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
    }

    public function postRemove(BaseLifecycleEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
    {
    }

    /*
     * The neutral hooks (plan "omnibase agnostic", step F2b): the same moments as the hooks above, with
     * omnisense/omnibridge's lifecycle events - the entity and its unit of work, no ORM type - in place of
     * Doctrine's arguments. A persistence dispatches them (Omnibridge\Doctrine\Omnibase\DatabaseSubscriber\AttributeSubscriber
     * relays Doctrine's: the neutral hook first, then the Doctrine-typed one, kept for the attributes of
     * the applications and bundles that override it, deprecated). An attribute of the core answers here;
     * an entity's property hook receives its property, a class hook null.
     */

    public function onPrePersist(Lifecycle\PrePersist $event, ?string $property = null): void
    {
    }

    public function onPreUpdate(Lifecycle\PreUpdate $event, ?string $property = null): void
    {
    }

    public function onPreRemove(Lifecycle\PreRemove $event, ?string $property = null): void
    {
    }

    public function onPostLoad(Lifecycle\PostLoad $event, ?string $property = null): void
    {
    }

    public function onPostPersist(Lifecycle\PostPersist $event, ?string $property = null): void
    {
    }

    public function onPostUpdate(Lifecycle\PostUpdate $event, ?string $property = null): void
    {
    }

    public function onPostRemove(Lifecycle\PostRemove $event, ?string $property = null): void
    {
    }

    /** A flush's moments, for one entity it writes (insertions, updates, deletions). */
    public function onPreFlush(Lifecycle\PreFlush $event, object $entity, ?string $property = null): void
    {
    }

    public function onOnFlush(Lifecycle\OnFlush $event, object $entity, ?string $property = null): void
    {
    }

    public function onPostFlush(Lifecycle\PostFlush $event, object $entity, ?string $property = null): void
    {
    }

    /**
     * The persistence's part of a behaviour, for the unit of work the event carries (plan "omnibase
     * agnostic", step F2b): a VaultBackend, SlugifyBackend, CacheBackend... of
     * Base\Database\Attribute\Backend, refused aloud when the persistence installed has none.
     *
     * @template T of object
     *
     * @param class-string<T> $behaviour
     *
     * @return T
     */
    protected function backend(Lifecycle\EntityEvent|Lifecycle\FlushEvent $event, string $behaviour): object
    {
        return Backends::of($event->unitOfWork, $behaviour);
    }

    /**
     * The entity's shape - fields, associations, identifier, root - as the persistence of the event's
     * unit of work maps it (plan "omnibase agnostic", step F2c), in place of Doctrine's ClassMetadata.
     */
    protected function shape(Lifecycle\EntityEvent|Lifecycle\FlushEvent $event, string|object $class): EntityShape
    {
        return $this->backend($event, EntityShapes::class)->shape($class);
    }
}
