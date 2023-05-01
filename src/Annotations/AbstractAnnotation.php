<?php

namespace Base\Annotations;

use App\Entity\User;
use Base\Database\Entity\EntityHydratorInterface;
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

abstract class AbstractAnnotation implements AnnotationInterface
{
    /**
     * @return AnnotationReader|null
     */
    public static function getAnnotationReader(): ?AnnotationReader
    {
        return AnnotationReader::getInstance();
    }

    /**
     * @return string
     */
    public static function getEnvironment(): string
    {
        return AnnotationReader::getInstance()->getEnvironment();
    }

    /**
     * @return mixed
     */
    public static function getService()
    {
        return AnnotationReader::getInstance()->getService();
    }

    /**
     * @return string
     */
    public static function getProjectDir(): string
    {
        return AnnotationReader::getInstance()->getProjectDir();
    }

    /**
     * @return ParameterBagInterface
     */
    public static function getParameterBag(): ParameterBagInterface
    {
        return AnnotationReader::getInstance()->getParameterBag();
    }

    /**
     * @return mixed
     */
    public static function getDoctrineReader()
    {
        return AnnotationReader::getInstance()->getDoctrineReader();
    }

    /**
     * @return EntityManager|null
     */
    public static function getEntityManager(): ?EntityManager
    {
        return AnnotationReader::getInstance()->getEntityManager();
    }

    /**
     * @return EntityHydratorInterface
     */
    public static function getEntityHydrator(): EntityHydratorInterface
    {
        return AnnotationReader::getInstance()->getEntityHydrator();
    }

    /**
     * @param $className
     * @param string $property
     * @return false|string|null
     * @throws Exception
     */
    public static function getTypeOfField($className, string $property)
    {
        return AnnotationReader::getInstance()->getClassMetadataManipulator()->getTypeOfField($className, $property);
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
        return AnnotationReader::getInstance()->getClassMetadataManipulator();
    }

    public static function getClassMetadataCompletor(mixed $entityOrClassOrMetadata): ?ClassMetadataCompletor
    {
        return AnnotationReader::getInstance()->getClassMetadataManipulator()->getClassMetadataCompletor($entityOrClassOrMetadata);
    }

    public static function getFlysystem(): FlysystemInterface
    {
        return AnnotationReader::getInstance()->getFlysystem();
    }

    public static function getImpersonator(): ?User
    {
        return AnnotationReader::getInstance()->getImpersonator();
    }

    public static function getUser(): ?User
    {
        return AnnotationReader::getInstance()->getUser();
    }

    /**
     * @param $className
     * @return EntityRepository|ObjectRepository
     */
    public static function getRepository($className): EntityRepository|ObjectRepository
    {
        return AnnotationReader::getInstance()->getRepository($className);
    }

    /**
     * @param $url
     * @return string
     */
    public static function getAsset($url): string
    {
        return AnnotationReader::getInstance()->getAsset($url);
    }

    /**
     * @param $entityOrClassNameOrMetadataOrRefl
     * @param string $mappingPath
     * @param string|null $annotationClass
     * @return array
     * @throws Exception
     */
    public static function getAnnotations($entityOrClassNameOrMetadataOrRefl, string $mappingPath, ?string $annotationClass = null): array
    {
        if (!$entityOrClassNameOrMetadataOrRefl) {
            return [];
        }
        if (AnnotationReader::getInstance()->isEntity($entityOrClassNameOrMetadataOrRefl)) {
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

        $annotations = AnnotationReader::getInstance()->getPropertyAnnotations($entityOrClassNameOrMetadataOrRefl);
        foreach ($annotations as $column => $annotation) {
            if ($annotationClass !== null) {
                $annotations[$column] = array_filter($annotation, fn($a) => is_instanceof($a, $annotationClass));
            }
        }

        return $annotations[$mapping] ?? [];
    }

    /**
     * @param $entityOrClassNameOrMetadataOrRefl
     * @param string $mapping
     * @param string $annotationClass
     * @return mixed
     */
    public static function getAnnotation($entityOrClassNameOrMetadataOrRefl, string $mapping, string $annotationClass): mixed
    {
        $annotations = self::getAnnotations($entityOrClassNameOrMetadataOrRefl, $mapping, $annotationClass);
        return !empty($annotations) ? end($annotations) : null;
    }

    /**
     * @param $entityOrClassNameOrMetadataOrRefl
     * @param string $mapping
     * @param string $annotationClass
     * @return bool
     */
    public static function hasAnnotation($entityOrClassNameOrMetadataOrRefl, string $mapping, string $annotationClass): bool
    {
        $annotations = self::getAnnotations($entityOrClassNameOrMetadataOrRefl, $mapping, $annotationClass);
        return !empty($annotations);
    }

    /**
     * Minimize the use unit of work to very specific context.. (doctrine internal use only)
     * Please use getNativeEntity() to get back the
     */
    public static function getUnitOfWork(): UnitOfWork
    {
        return AnnotationReader::getInstance()->getEntityManager()->getUnitOfWork();
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
     * @param AbstractAnnotation $annotation
     * @return bool
     */
    public static function isSerializable(AbstractAnnotation $annotation): bool
    {
        try {
            return is_serializable($annotation);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public static function inDoctrineStack(): bool
    {
        $debug_backtrace = debug_backtrace();
        foreach ($debug_backtrace as $trace) {
            if (str_starts_with($trace["class"], "Doctrine")) {
                return true;
            }
        }

        return false;
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

        return AnnotationReader::getInstance()->getEntityHydrator()->hydrate($classname, array_merge($fields, $associations));
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

    abstract public function supports(string $target, ?string $targetValue = null, mixed $object = null): bool;

    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null)
    {
    }

    public function preFlush(PreFlushEventArgs $event, ClassMetadata $classMetadata, mixed $entity, ?string $property = null)
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
