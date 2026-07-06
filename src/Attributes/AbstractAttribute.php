<?php

namespace Base\Attributes;

use App\Entity\User;
use Base\Database\Entity\EntityHydratorInterface;
use Base\Database\Event\DoctrineQueryEventArgs;
use Base\Database\Event\ResolveDiscriminatorEventArgs;
use Base\Database\Mapping\ClassMetadataManipulator;
use Base\Database\Mapping\ClassMetadataCompletor;
use Base\Service\FlysystemInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepositoryInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Exception;

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

    public static function getClassMetadataManipulator(): ?ClassMetadataManipulator
    {
        return AttributeReader::getInstance()->getClassMetadataManipulator();
    }

    public static function getClassMetadataCompletor(mixed $entityOrClassOrMetadata): ?ClassMetadataCompletor
    {
        return AttributeReader::getInstance()->getClassMetadataManipulator()->getClassMetadataCompletor($entityOrClassOrMetadata);
    }

    public static function getFlysystem(): FlysystemInterface
    {
        return AttributeReader::getInstance()->getFlysystem();
    }

    public static function getImpersonator(): ?User
    {
        return AttributeReader::getInstance()->getImpersonator();
    }

    public static function getUser(): ?User
    {
        return AttributeReader::getInstance()->getUser();
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
     * @param $entity
     * @return array[]|PersistentCollection|\mixed[][]
     */
    public static function getEntityChangeSet($entity): array|PersistentCollection
    {
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
     * @return ServiceEntityRepositoryInterface
     * @throws NotSupported
     */
    public function getPropertyOwnerRepository($entity, string $property): ServiceEntityRepositoryInterface
    {
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
    public static function getEntityFromData($classname, $data): ?object
    {
        if ($data === null) {
            return null;
        }

        $fieldNames = self::getClassMetadata($classname)->getFieldNames();
        $fields = array_intersect_key($data, array_flip($fieldNames));
        $associations = array_diff_key($data, array_flip($fieldNames));

        return object_hydrate(new $classname, array_merge($fields, $associations));
    }

    /**
     * @param $entity
     * @return object|null
     * @throws MappingException
     */
    public static function getOriginalEntity($entity): ?object
    {
        return self::getEntityFromData(get_class($entity), self::getOriginalEntityData($entity));
    }

    /**
     * @param $entity
     * @return array|null
     * @throws MappingException
     */
    public static function getOriginalEntityData($entity): ?array
    {
        $primaryKey = self::getClassMetadataManipulator()->getPrimaryKey($entity); // primaryKey information missing

        try {
            $entityData = self::getUnitOfWork()->getOriginalEntityData($entity);
        } catch (EntityNotFoundException $e) {
            return null;
        }

        $entityData[$primaryKey] = self::getFieldValue($entity, $primaryKey);
        return $entityData;
    }

    /**
     * @param $entity
     * @return object|null
     */
    public static function getOldEntity($entity): ?object
    {
        return self::getEntityFromData(get_class($entity), self::getOldEntityData($entity));
    }

    /**
     * @param $entity
     * @return array|null
     */
    public static function getOldEntityData($entity): ?array
    {
        $changeSet = self::getEntityChangeSet($entity);

        // Replace original entity values by the changeSet
        //   It happens that "original" entity data doesn't mean,
        //   original value before form submission
        $entityData = self::getOriginalEntityData($entity);

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
    public static function getFieldValue($entity, string $property)
    {
	return self::getClassMetadataManipulator()->getFieldValue($entity, $property);
    }

    /**
     * @param $entity
     * @param string $property
     * @param $value
     * @return ClassMetadataManipulator|false
     */
    public static function setFieldValue($entity, string $property, $value): false|ClassMetadataManipulator
    {
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
    public static function getPropertyValue($entity, string $property)
    {
        return self::getClassMetadataManipulator()->getPropertyValue($entity, $property);
    }

    /**
     * @param $entity
     * @param string $property
     * @param $value
     * @return ClassMetadataManipulator
     */
    public static function setPropertyValue($entity, string $property, $value): ClassMetadataManipulator
    {
        return self::getClassMetadataManipulator()->setPropertyValue($entity, $property, $value);
    }

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null): void
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
}
