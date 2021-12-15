<?php

namespace Base\Annotations;

use Base\Service\BaseService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;

use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use ReflectionClass;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

abstract class AbstractAnnotation implements AnnotationInterface
{
    public static function getAnnotationReader() { return AnnotationReader::getInstance(); }
    public static function getProjectDir() { return AnnotationReader::getInstance()->getProjectDir(); }
    public static function getParameterBag() { return AnnotationReader::getInstance()->getParameterBag(); }
    public static function getFilesystem(string $storage) { return AnnotationReader::getInstance()->getFilesystem($storage); }
    public static function getAdapter($storage) { return AnnotationReader::getInstance()->getFilesystemAdapter($storage); }
    public static function getPathPrefixer($storage) { return AnnotationReader::getInstance()->getFilesystemPathPrefixer($storage); }
    public static function getDoctrineReader() { return AnnotationReader::getInstance()->getDoctrineReader(); }
    public static function getEntityManager() { return AnnotationReader::getInstance()->getEntityManager(); }
    public static function getClassMetadata($className): ?ClassMetadata { return self::getEntityManager()->getClassMetadata($className); }
    public static function getRepository($className) { return AnnotationReader::getInstance()->getRepository($className); }
    public static function getAsset($url) { return AnnotationReader::getInstance()->getAsset($url); }

    public static function getAnnotation($entity, string $mapping)
    {
        $classname = (is_object($entity) ? get_class($entity) : (is_string($entity) ? $entity : null));
        if(!$classname) return null;
        
        $annotations = AnnotationReader::getInstance()->getPropertyAnnotations($classname, static::class);
        if(!array_key_exists($mapping, $annotations))
            throw new Exception("Annotation \"".static::class."\" not found in the mapped property \"$mapping\". Did you forget to clear cache ?");

        return end($annotations[$mapping]);
    }
    
    /**
     * Minimize the use unit of work to very specific context.. (doctrine internal use only)
     * Please use getNativeEntity() to get back the
     */
    public static function getUnitOfWork() { return AnnotationReader::getInstance()->getEntityManager()->getUnitOfWork(); }
    public static function getEntityChangeSet($entity)
    {
        // (NB: /!\ computeChangeSets != recomputeSingleChangeSets)
        self::getUnitOfWork()->recomputeSingleEntityChangeSet(
            self::getClassMetadata(get_class($entity)), $entity
        );

        return self::getUnitOfWork()->getEntityChangeSet($entity);
    }

    protected static $entitySerializer = null;
    public static function getSerializer()
    {
        if (!self::$entitySerializer)
            self::$entitySerializer = new Serializer([new DateTimeNormalizer(), new ObjectNormalizer()], [new JsonEncoder()]);

        return self::$entitySerializer;
    }

    public static function isWithinDoctrine()
    {
        $debug_backtrace = debug_backtrace();
        foreach($debug_backtrace as $trace)
            if(str_starts_with($trace["class"], "Doctrine")) return true;

        return false;
    }

    public function getPropertyOwnerRepository($entity, string $property)
    {
        $className = get_class($entity);
        $repository = $this->getEntityManager()->getRepository($className);

        while($className = get_parent_class($className)) {

            if(property_exists($className, $property))
                $repository = $this->getEntityManager()->getRepository($className);
        }

        return $repository;
    }

    public static function getEntityFromData($classname, $data)
    {
        $fieldNames          = self::getClassMetadata($classname)->getFieldNames();

        $fields  = array_intersect_key($data, array_flip($fieldNames));
        $associations = array_diff_key($data, array_flip($fieldNames));

        $entity = self::getSerializer()->deserialize(json_encode($fieldNames), $classname, 'json');
        foreach ($fields as $property => $data)
            self::setFieldValue($entity, $property, $data);

        foreach($associations as $property => $data)
            self::setFieldValue($entity, $property, $data);

        return $entity;
    }

    public static function getOriginalEntity($entity) { return self::getEntityFromData(get_class($entity), self::getOriginalEntityData($entity)); }
    public static function getOriginalEntityData($entity)
    {
        $primaryKey = self::getPrimaryKey($entity); // primaryKey information missing

        $entityData =self::getUnitOfWork()->getOriginalEntityData($entity);
        $entityData[$primaryKey] = self::getFieldValue($entity, $primaryKey);

        return $entityData;
    }

    public static function getOldEntity($entity) { return self::getEntityFromData(get_class($entity), self::getOldEntityData($entity)); }
    public static function getOldEntityData($entity)
    {
        $changeSet  = self::getEntityChangeSet($entity);

        // Replace original entity values by the changeSet
        //   It happens that "original" entity data doesn't mean,
        //   original value before form submission
        $entityData = self::getOriginalEntityData($entity);
        foreach($entityData as $key => $dummy)
            if(array_key_exists($key, $changeSet)) $entityData[$key] = $changeSet[$key][0];

        return $entityData;
    }

    public static function getPrimaryKey($entity) { return self::getClassMetadata(get_class($entity))->getSingleIdentifierFieldName(); }
    public static function hasField($entity, string $property) { return property_exists($entity, $property); }
    public static function getTypeOfField($entity, string $property)
    {
        if(!$entity || !is_object($entity)) return null;

        $classMetadata = self::getClassMetadata(get_class($entity));
        if( ($dot = strpos($property, ".")) > 0 ) {
        
            $field    = trim(substr($property, 0, $dot));
            $property = trim(substr($property,    $dot+1));
            
            if(!$classMetadata->hasAssociation($field))
                throw new \Exception("No association found for field \"$field\" in \"".get_class($entity)."\"");

            $entity = self::getTypeOfField($entity, $field);
            if ($entity instanceof ArrayCollection)
                $entity = $entity->first();
            else if(is_array($entity))
                $entity = current($entity) ?? null;
            
            return self::getTypeOfField($entity, $property);
        }
        
        return ($classMetadata->hasField($property) ? $classMetadata->getTypeOfField($property) : null);
    }

    public static function getFieldValue($entity, string $property)
    {
        if(!$entity) return null;

        $classMetadata = self::getClassMetadata(get_class($entity));
        if( ($dot = strpos($property, ".")) > 0 ) {

            $field    = trim(substr($property, 0, $dot));
            $property = trim(substr($property,    $dot+1));

            if(!$classMetadata->hasAssociation($field))
                throw new \Exception("No association found for field \"$field\" in \"".get_class($entity)."\"");

            $entity = self::getFieldValue($entity, $field);
            if ($entity instanceof ArrayCollection)
                $entity = $entity->first();
            else if(is_array($entity))
                $entity = current($entity) ?? null;

            return self::getFieldValue($entity, $property);
        }

        if ($classMetadata->hasField($property))
            return $classMetadata->getFieldValue($entity, $property);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        return $propertyAccessor->getValue($entity, $property);
    }

    public static function setFieldValue($entity, string $property, $value)
    {
        $classMetadata = self::getClassMetadata(get_class($entity));
        if($classMetadata->hasField($property))
            return $classMetadata->setFieldValue($entity, $property, $value);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        return $propertyAccessor->setValue($entity, $property, $value);
    }

    abstract public function supports(ClassMetadata $classMetadata, string $target, ?string $targetValue = null, $entity = null): bool;
    public function loadClassMetadata(ClassMetadata $classMetadata, string $target, ?string $targetValue = null) {}

    public function onFlush(OnFlushEventArgs $args, ClassMetadata $classMetadata, $entity, ?string $property = null) { }

    public function prePersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null) {}
    public function preUpdate (LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null) {}
    public function preRemove (LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null) {}

    public function postLoad   (LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null) {}
    public function postPersist(LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null) {}
    public function postUpdate (LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null) {}
    public function postRemove (LifecycleEventArgs $event, ClassMetadata $classMetadata, $entity, ?string $property = null) {}
}
