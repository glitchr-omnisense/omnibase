<?php

namespace Base\Annotations\Annotation;

use Base\Annotations\AbstractAnnotation;
use Base\Annotations\AnnotationReader;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Exception;
use Symfony\Component\Uid\Uuid;

/**
 * Class GenerateUuid
 * package Base\Annotations\Annotation\GenerateUuid
 *
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("version", type = "integer"),
 *   @Attribute("namespace", type = "string"),
 * })
 */
class GenerateUuid extends AbstractAnnotation
{
    private $version;
    public const V1_MAC      = 1;
    public const V2_DCE      = 2;
    public const V3_MD5      = 3;
    public const V4_RANDOM   = 4;
    public const V5_SHA1     = 5;
    public const V6_SORTABLE = 6;

    public const UUID_NS_DNS  = "6ba7b810-9dad-11d1-80b4-00c04fd430c8";
    public const UUID_NS_URL  = "6ba7b811-9dad-11d1-80b4-00c04fd430c8";
    public const UUID_NS_OID  = "6ba7b812-9dad-11d1-80b4-00c04fd430c8";
    public const UUID_NS_X500 = "6ba7b814-9dad-11d1-80b4-00c04fd430c8";

    protected $namespace;

    public function __construct(array $data)
    {
        // Determine version
        $version       = $data['version'] ?? self::V4_RANDOM;
        $versionValid  = ($version >= self::V1_MAC && $version <= self::V6_SORTABLE);
        $this->version = ($versionValid) ? $version : self::V4_RANDOM;

        // Determine namespace
        $this->namespace = $data['namespace'] ?? null;
        if($this->version == self::V3_MD5 || $this->version == self::V5_SHA1) {

            if(!$this->namespace)
                throw new Exception("Namespace required for UUID v".$this->version);
        }
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getUuid($entity): string
    {
        switch ($this->version) {
            case self::V1_MAC:
                return Uuid::v1();
            case self::V2_DCE:
                throw new Exception("UUID v2 is not implemented.. sorry");
                //return Uuid::v2();
            case self::V3_MD5:
                return Uuid::v3(new Uuid($this->namespace), get_class($entity));
            case self::V4_RANDOM:
                return Uuid::v4();
            case self::V5_SHA1:
                return Uuid::v5(new Uuid($this->namespace), get_class($entity));
            case self::V6_SORTABLE:
                return Uuid::v6();
        }

        return null;
    }

    public function supports(string $target, ?string $targetValue = null, $object = null): bool
    {
        return ($target == AnnotationReader::TARGET_PROPERTY);
    }

    public function onFlush(OnFlushEventArgs $args, ClassMetadata $classMetadata, $entity, ?string $property = null)
    {
        if ($this->getFieldValue($entity, $property) === null) {

            $this->setFieldValue($entity, $property, $this->getUuid($entity));
            if ($this->getUnitOfWork()->getEntityChangeSet($entity))
                $this->getUnitOfWork()->recomputeSingleEntityChangeSet($classMetadata, $entity);
        }
    }
}
